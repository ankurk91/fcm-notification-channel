<?php
declare(strict_types=1);

namespace NotificationChannels\FCM\Exception;

use Throwable;

class InvalidRecipientException extends RuntimeException
{
    public static function make(Throwable $exception): self
    {
        return new static($exception->getMessage(), $exception->getCode(), $exception);
    }
}
