<?php
declare(strict_types=1);

namespace NotificationChannels\FCM\Tests\Resources;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Notifications\Notifiable;
use Kreait\Firebase\Messaging\MessageTarget;

class TestModel extends Model
{
    use Notifiable;

    public function __construct(
        protected ?string $tokenType = MessageTarget::TOKEN,
        protected bool    $useMultiToken = false)
    {
        parent::__construct([]);
    }

    public function routeNotificationForFCMTargetType(): ?string
    {
        return $this->tokenType;
    }

    public function routeNotificationForFCM($notification): string|array|null
    {
        switch ($this->tokenType) {
            case MessageTarget::TOKEN:
                if ($this->useMultiToken) {
                    return ['valid-token-1', 'valid-token-2'];
                }
                return 'single-token';

            case MessageTarget::CONDITION:
                return "'valid-topic1' in Topics";

            case MessageTarget::TOPIC:
                return 'valid-topic';

            default:
                return null;
        }
    }
}
