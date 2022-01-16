<?php
declare(strict_types=1);

namespace NotificationChannels\FCM\Exception;

use Kreait\Firebase\Exception\FirebaseException;

class RuntimeException extends \RuntimeException implements FirebaseException
{
    //
}
