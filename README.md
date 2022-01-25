# FCM Notification Channel for Laravel

[![Packagist](https://badgen.net/packagist/v/ankurk91/fcm-notification-channel)](https://packagist.org/packages/ankurk91/fcm-notification-channel)
[![GitHub-tag](https://badgen.net/github/tag/ankurk91/fcm-notification-channel)](https://github.com/ankurk91/fcm-notification-channel/releases)
[![License](https://badgen.net/packagist/license/ankurk91/fcm-notification-channel)](LICENSE.txt)
[![Downloads](https://badgen.net/packagist/dt/ankurk91/fcm-notification-channel)](https://packagist.org/packages/ankurk91/fcm-notification-channel/stats)
[![GH-Actions](https://github.com/ankurk91/fcm-notification-channel/workflows/tests/badge.svg)](https://github.com/ankurk91/fcm-notification-channel/actions)
[![codecov](https://codecov.io/gh/ankurk91/fcm-notification-channel/branch/main/graph/badge.svg)](https://codecov.io/gh/ankurk91/fcm-notification-channel)

Send [Firebase](https://firebase.google.com/docs/cloud-messaging) push notifications with Laravel php framework.

## Installation

You can install this package via composer:

```bash
composer require ankurk91/fcm-notification-channel
```

This package relies on [laravel-firebase](https://github.com/kreait/laravel-firebase) package to interact with Firebase
services. Configure it properly before proceeding.

## Usage

You can use the FCM channel in the `via()` method inside your Notification class:

```php
<?php
namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;
use Illuminate\Contracts\Queue\ShouldQueue;
use NotificationChannels\FCM\FCMChannel;
use Kreait\Firebase\Messaging\CloudMessage;

class ExampleNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function via($notifiable): array
    {
        return [FCMChannel::class];
    }

    public function toFCM($notifiable): CloudMessage
    {
        return CloudMessage::new()
            ->withDefaultSounds()
            ->withNotification([
                'title' => 'Hello',
                'body' => 'Message body',
            ])         
            ->withData([
                'key' => 'string value'
            ]);
    }    
}
```

Prepare your Notifiable model:

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Notifications\Notifiable;
use Illuminate\Notifications\Notification;
use Illuminate\Foundation\Auth\User as Authenticatable;

class User extends Authenticatable
{
    use Notifiable;
    
    protected $guarded = [];
    
    /**
    * Assuming that you have a database table which stores device tokens.
    */
    public function deviceTokens(): HasMany
    {
        return $this->hasMany(DeviceToken::class);
    }
    
    public function routeNotificationForFCM(Notification $notification): string|array|null
    {
         return $this->deviceTokens->pluck('token')->toArray();
    }
    
    /**
    * Optional method to determine which message target to use
    * We will use TOKEN type when not specified
    * @see \Kreait\Firebase\Messaging\MessageTarget::TYPES
    */
    public function routeNotificationForFCMTargetType(Notification $notification): ?string
    {
        return \Kreait\Firebase\Messaging\MessageTarget::TOKEN;
    }
    
    /**
    * Optional method to determine which Firebase project to use
    * We will use default project when not specified
    */
    public function routeNotificationForFCMProject(Notification $notification): ?string;
    {
        return config('firebase.default');
    }   
}
```

## Send to topics or conditions

You can use Laravel's [on-demand](https://laravel.com/docs/8.x/notifications#on-demand-notifications) notifications:

```php
<?php

use Illuminate\Support\Facades\Notification;
use Kreait\Firebase\Messaging\MessageTarget;
use App\Notification\ExampleNotification;

Notification::route('FCM', 'your topic')
    ->route('FCMTargetType', MessageTarget::TOPIC)
    ->notify(new ExampleNotification());
```

## Events

You can consume Laravel's inbuilt notification [events](https://laravel.com/docs/8.x/notifications#notification-events)

```php
<?php

namespace App\Providers;

use Illuminate\Notifications\Events;
use Illuminate\Foundation\Support\Providers\EventServiceProvider as ServiceProvider;

class EventServiceProvider extends ServiceProvider
{
    protected $listen = [
        Events\NotificationSent::class => [
            \App\Listeners\FCMNotificationSent::class,
        ],
        Events\NotificationFailed::class => [
            \App\Listeners\FCMNotificationFailed::class,
        ],
    ];    
}
```

Here is the example of the failed event listener class

```php
<?php

namespace App\Listeners;

use App\Models\User;
use NotificationChannels\FCM\FCMChannel;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Events\NotificationFailed;

class FCMNotificationFailed implements ShouldQueue
{
    public function handle(NotificationFailed $event)
    {
        if ($event->channel !== FCMChannel::class) {
            return;
        }

        /** @var User $user */
        $user = $event->notifiable;
        
        // Delete invalid device tokens from database      
    }
}
```

Additionally, you may want to ignore this exception in your `app/Exceptions/Handler.php`

```php
protected $dontReport = [
    \NotificationChannels\FCM\Exception\InvalidRecipientException::class,
];
```

### Changelog

Please see [CHANGELOG](CHANGELOG.md) for more information what has changed recently.

### Testing

```bash
composer test
```

### Security

If you discover any security issue, please email `pro.ankurk1[at]gmail[dot]com` instead of using the issue tracker.

### Attribution

The package based on [this](https://github.com/kreait/laravel-firebase/pull/69) PR

### License

This package is licensed under [MIT License](https://opensource.org/licenses/MIT).
