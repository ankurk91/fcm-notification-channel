<?php
declare(strict_types=1);

namespace NotificationChannels\FCM\Tests\Resources;

use Illuminate\Notifications\Notification;
use Kreait\Firebase\Messaging\CloudMessage;
use NotificationChannels\FCM\FCMChannel;

class TestNotification extends Notification
{
    public function via($notifiable): array
    {
        return [FCMChannel::class];
    }

    public function toFCM($notifiable): CloudMessage
    {
        return CloudMessage::new()
            ->withNotification([
                'title' => 'A notification title',
                'body' => 'A notification body',
            ]);
    }
}
