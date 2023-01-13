<?php
declare(strict_types=1);

namespace NotificationChannels\FCM;

use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Notifications\Events\NotificationFailed;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;
use Kreait\Firebase\Contract\Messaging;
use Kreait\Firebase\Contract\Messaging as MessagingClient;
use Kreait\Firebase\Exception\FirebaseException;
use Kreait\Firebase\Exception\Messaging\NotFound;
use Kreait\Firebase\Exception\MessagingException;
use Kreait\Firebase\Messaging\CloudMessage;
use Kreait\Firebase\Messaging\Http\Request\SendMessageToTokens;
use Kreait\Firebase\Messaging\MessageTarget;
use Kreait\Laravel\Firebase\Facades\Firebase;
use NotificationChannels\FCM\Exception\HttpException;
use NotificationChannels\FCM\Exception\InvalidRecipientException;
use NotificationChannels\FCM\Exception\RuntimeException;
use Throwable;

class FCMChannel
{
    protected const BATCH_MESSAGE_LIMIT = Messaging::BATCH_MESSAGE_LIMIT;

    public function __construct(protected Dispatcher $events)
    {
        //
    }

    /**
     * Send the notification payload to firebase.
     *
     * @param mixed $notifiable
     * @param Notification $notification
     * @return array<mixed>
     *
     * @throws FirebaseException
     */
    public function send($notifiable, Notification $notification): array
    {
        // Build the target
        [$targetType, $targetValue] = $this->getTarget($notifiable, $notification);

        // Build the message
        $message = $this->getMessage($notifiable, $notification);

        // Check if there is a target, otherwise return an empty array
        if (empty($targetValue)) {
            return [];
        }

        // Make the messaging client
        $client = $this->getFirebaseMessaging($notifiable, $notification);

        // Send the message
        try {
            // Send multicast
            if ($this->canSendToMulticast($targetType, $targetValue)) {
                $chunkedTokens = array_chunk($targetValue, self::BATCH_MESSAGE_LIMIT);

                $responses = [];
                foreach ($chunkedTokens as $chunkedToken) {
                    $responses[] = $client->sendMulticast($message, $chunkedToken);
                }

                return $responses;
            }

            // Set the target and type; since we are sure that target is single
            $message = $message->withChangedTarget($targetType, Arr::first($targetValue));

            // Send to single target
            return [
                $client->send($message)
            ];
        } catch (NotFound $exception) {
            $this->emitFailedEvent($notifiable, $notification, $exception);

            throw InvalidRecipientException::make($exception);
        } catch (MessagingException $exception) {
            $this->emitFailedEvent($notifiable, $notification, $exception);

            throw HttpException::sendingFailed($exception);
        }
    }

    /**
     * Multicast can only be sent to token type.
     *
     * @param string $targetType
     * @param array $targetValue
     * @return bool
     */
    protected function canSendToMulticast(string $targetType, array $targetValue): bool
    {
        return $targetType === MessageTarget::TOKEN && count($targetValue) > 1;
    }

    /**
     * Get the message from notification.
     *
     * @param mixed $notifiable
     * @param Notification $notification
     * @return CloudMessage
     *
     * @throws RuntimeException
     */
    protected function getMessage($notifiable, Notification $notification): CloudMessage
    {
        if (!method_exists($notification, 'toFCM')) {
            throw new RuntimeException('Notification class is missing toFCM method.');
        }

        return $notification->toFCM($notifiable);
    }

    /**
     * Get the target and type from notifiable.
     *
     * @param mixed $notifiable
     * @param Notification $notification
     * @return array{
     *     targetType: string,
     *     targetValue: array,
     * }
     */
    protected function getTarget($notifiable, Notification $notification): array
    {
        $targetType = $notifiable->routeNotificationFor('FCMTargetType', $notification);
        $targetValue = $notifiable->routeNotificationFor('FCM', $notification);

        $targetType = (string)Str::of($targetType ?? MessageTarget::TOKEN)->lower();
        $targetValue = Arr::wrap($targetValue);

        return [
            $targetType,
            $targetValue,
        ];
    }

    /**
     * Get firebase messaging instance for the configured project.
     *
     * @param mixed $notifiable
     * @param Notification $notification
     * @return MessagingClient
     */
    protected function getFirebaseMessaging($notifiable, Notification $notification): MessagingClient
    {
        $project = $notifiable->routeNotificationFor('FCMProject', $notification);

        return Firebase::project($project ?? null)->messaging();
    }

    /**
     * Dispatch failed event.
     *
     * @param mixed $notifiable
     * @param Notification $notification
     * @param Throwable $exception
     */
    protected function emitFailedEvent($notifiable, Notification $notification, Throwable $exception): void
    {
        $this->events->dispatch(new NotificationFailed(
            $notifiable,
            $notification,
            self::class,
            [
                'message' => $exception->getMessage(),
                'exception' => $exception,
            ]
        ));
    }
}
