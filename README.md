# FCM Notification Channel for Laravel

[![Packagist](https://badgen.net/packagist/v/ankurk91/fcm-notification-channel)](https://packagist.org/packages/ankurk91/fcm-notification-channel)
[![GitHub-tag](https://badgen.net/github/tag/ankurk91/fcm-notification-channel)](https://github.com/ankurk91/fcm-notification-channel/tags)
[![License](https://badgen.net/packagist/license/ankurk91/fcm-notification-channel)](LICENSE.txt)
[![Downloads](https://badgen.net/packagist/dt/ankurk91/fcm-notification-channel)](https://packagist.org/packages/ankurk91/fcm-notification-channel/stats)
[![GH-Actions](https://github.com/ankurk91/fcm-notification-channel/workflows/tests/badge.svg)](https://github.com/ankurk91/fcm-notification-channel/actions)
[![codecov](https://codecov.io/gh/ankurk91/fcm-notification-channel/branch/main/graph/badge.svg)](https://codecov.io/gh/ankurk91/fcm-notification-channel)

Send [Firebase](https://firebase.google.com/docs/cloud-messaging) push notifications with Laravel php framework.

## Highlights

* Using the latest Firebase HTTP v1 [API](https://firebase.google.com/docs/cloud-messaging/migrate-v1)
* Send message to a topic or condition :wink:
* Send message to a specific device or multiple devices (Multicast)
* Send additional RAW data with notification
* Supports multiple Firebase projects in single Laravel app:fire:
* Invalid token handling with event and listeners
* Fully tested package with automated test cases
* Powered by battle tested [Firebase php SDK](https://firebase-php.readthedocs.io/) :rocket:

## Installation

You can install this package via composer:

```bash
composer require "ankurk91/fcm-notification-channel"
```

## Configuration

This package relies on [laravel-firebase](https://github.com/kreait/laravel-firebase) package to interact with Firebase
services. Here is the minimal configuration you need in your `.env` file

```dotenv
# relative or full path to the Service Account JSON file
FIREBASE_CREDENTIALS=firebase-credentials.json
```

You will need to create a [service account](https://firebase.google.com/docs/admin/setup#initialize-sdk)
and place the JSON file in your project root.

Additionally, you can update your `.gitignore` file

```gitignore
/firebase-credentials*.json
```

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
                'title' => 'Order shipped',
                'body' => 'Your order for laptop is shipped.',
            ])         
            ->withData([
                'orderId' => '#123'
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
use Illuminate\Foundation\Auth\User as Authenticatable;

class User extends Authenticatable
{
    use Notifiable;
       
    /**
    * Assuming that you have a database table which stores device tokens.
    */
    public function deviceTokens(): HasMany
    {
        return $this->hasMany(DeviceToken::class);
    }
    
    public function routeNotificationForFCM($notification): string|array|null
    {
         return $this->deviceTokens->pluck('token')->toArray();
    }
    
    /**
    * Optional method to determine which message target to use
    * We will use TOKEN type when not specified
    * @see \Kreait\Firebase\Messaging\MessageTarget::TYPES
    */
    public function routeNotificationForFCMTargetType($notification): ?string
    {
        return \Kreait\Firebase\Messaging\MessageTarget::TOKEN;
    }
    
    /**
    * Optional method to determine which Firebase project to use
    * We will use default project when not specified
    */
    public function routeNotificationForFCMProject($notification): ?string
    {
        return config('firebase.default');
    }   
}
```

## Send to a topic or condition

This package is not limited to sending notification to tokens.

You can use Laravel's [on-demand](https://laravel.com/docs/9.x/notifications#on-demand-notifications) notifications to
send push notification to a topic or condition or multiple tokens.

```php
<?php

use Illuminate\Support\Facades\Notification;
use Kreait\Firebase\Messaging\MessageTarget;
use App\Notification\ExampleNotification;

Notification::route('FCM', 'topicA')
    ->route('FCMTargetType', MessageTarget::TOPIC)
    ->notify(new ExampleNotification());

Notification::route('FCM', "'TopicA' in topics")
    ->route('FCMTargetType', MessageTarget::CONDITION)
    ->notify(new ExampleNotification());

Notification::route('FCM', ['token_1', 'token_2'])
    ->route('FCMTargetType', MessageTarget::TOKEN)
    ->notify(new ExampleNotification());
```

## Events

You can consume Laravel's inbuilt notification [events](https://laravel.com/docs/9.x/notifications#notification-events)

```php
<?php

namespace App\Providers;

use Illuminate\Notifications\Events;
use Illuminate\Foundation\Support\Providers\EventServiceProvider as ServiceProvider;

class EventServiceProvider extends ServiceProvider
{
    protected $listen = [
        Events\NotificationSent::class => [
            //\App\Listeners\FCMNotificationSent::class,
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
use Illuminate\Support\Arr;
use NotificationChannels\FCM\FCMChannel;
use Illuminate\Contracts\Queue\ShouldQueue;
use Kreait\Laravel\Firebase\Facades\Firebase;
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
        
        $invalidTokens = $this->findInvalidTokens($user);
        if (count($invalidTokens)) {           
            $user->deviceTokens()->whereIn('token', $invalidTokens)->delete();
        }
    }
    
    protected function findInvalidTokens(User $user): array
    {
        $tokens = Arr::wrap($user->routeNotificationFor('FCM'));
        if (! count($tokens)) {
            return [];
        }

        $project = $user->routeNotificationFor('FCMProject');
        $response = Firebase::project($project)->messaging()->validateRegistrationTokens($tokens);

        return array_unique(array_merge($response['invalid'], $response['unknown']));
    }
}
```

Read more about validating device
tokens [here](https://firebase-php.readthedocs.io/en/stable/cloud-messaging.html#validating-registration-tokens)

Then; you may want to ignore this exception in your `app/Exceptions/Handler.php`

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

The package is based on [this](https://github.com/kreait/laravel-firebase/pull/69) rejected PR

### License

This package is licensed under [MIT License](https://opensource.org/licenses/MIT).
