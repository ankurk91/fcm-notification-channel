<?php
declare(strict_types=1);

namespace NotificationChannels\FCM\Exception;

use Kreait\Firebase\Exception\FirebaseException;
use Kreait\Firebase\Exception\MessagingException;

class HttpException extends \RuntimeException implements FirebaseException
{
    public static function sendingFailed(MessagingException $exception): static
    {
        return new static('Failed to send notification.', $exception->getCode(), $exception);
    }
}
