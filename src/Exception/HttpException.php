<?php
declare(strict_types=1);

namespace NotificationChannels\FCM\Exception;

use Kreait\Firebase\Exception\HasErrors;
use Kreait\Firebase\Exception\MessagingException;

class HttpException extends RuntimeException implements MessagingException
{
    use HasErrors;

    public static function sendingFailed(MessagingException $exception): static
    {
        $instance = new static($exception->getMessage(), $exception->getCode(), $exception);
        $instance->errors = $exception->errors();

        return $instance;
    }
}
