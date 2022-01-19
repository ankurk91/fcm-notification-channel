<?php
declare(strict_types=1);

namespace NotificationChannels\FCM;

use Illuminate\Notifications\Notification;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;
use Kreait\Firebase\Contract\Messaging as MessagingClient;
use Kreait\Firebase\Exception\FirebaseException;
use Kreait\Firebase\Exception\MessagingException;
use Kreait\Firebase\Messaging\CloudMessage;
use Kreait\Firebase\Messaging\MessageTarget;
use Kreait\Laravel\Firebase\Facades\Firebase;
use NotificationChannels\FCM\Exception\HttpException;
use NotificationChannels\FCM\Exception\RuntimeException;

class FCMChannel
{
    /**
     * @see \Kreait\Firebase\Messaging\Http\Request\SendMessageToTokens
     */
    protected const MAX_AMOUNT_OF_TOKENS = 500;

    /**
     * Send the notification to firebase.
     *
     * @param mixed $notifiable
     * @param Notification $notification
     * @return array
     *
     * @throws RuntimeException|FirebaseException
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
                $chunkedTokens = array_chunk($targetValue, self::MAX_AMOUNT_OF_TOKENS);

                $responses = [];
                foreach ($chunkedTokens as $chunkedToken) {
                    $responses[] = $client->sendMulticast($message, $chunkedToken);
                }

                return $responses;
            }

            // Set target and type since we are sure that target is single
            $message = $message->withChangedTarget($targetType, Arr::first($targetValue));

            // Send to single target
            return [
                $client->send($message)
            ];
        } catch (MessagingException $exception) {
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
            throw new RuntimeException('Notification is missing toFCM method.');
        }

        return $notification->toFCM($notifiable);
    }

    /**
     * Get the target and type from notifiable.
     *
     * @param mixed $notifiable
     * @param Notification $notification
     * @return array
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
     * Get firebase messaging instance for the correct project.
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
}
