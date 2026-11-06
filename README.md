# FCM Notification Channel for Laravel

[![Packagist](https://badgen.net/packagist/v/ankurk91/fcm-notification-channel)](https://packagist.org/packages/ankurk91/fcm-notification-channel)
[![GitHub-tag](https://badgen.net/github/tag/ankurk91/fcm-notification-channel)](https://github.com/ankurk91/fcm-notification-channel/tags)
[![License](https://badgen.net/packagist/license/ankurk91/fcm-notification-channel)](LICENSE.txt)
[![Downloads](https://badgen.net/packagist/dt/ankurk91/fcm-notification-channel)](https://packagist.org/packages/ankurk91/fcm-notification-channel/stats)
[![GH-Actions](https://github.com/ankurk91/fcm-notification-channel/workflows/tests/badge.svg)](https://github.com/ankurk91/fcm-notification-channel/actions)
[![codecov](https://codecov.io/gh/ankurk91/fcm-notification-channel/branch/main/graph/badge.svg)](https://codecov.io/gh/ankurk91/fcm-notification-channel)

A Laravel notification channel that sends push notifications through
[Firebase Cloud Messaging (FCM)](https://firebase.google.com/docs/cloud-messaging), using the FCM HTTP v1 API.

- Send to a single device token, many device tokens (multicast), a topic, or a condition
- Send data payloads along with the notification
- Use several Firebase projects in one Laravel app
- Handle invalid tokens through Laravel's notification events
- Built on the [Firebase PHP SDK](https://firebase-php.readthedocs.io/) and [kreait/laravel-firebase](https://github.com/beste/laravel-firebase)

## Requirements

| Dependency                | Supported versions   |
|---------------------------|----------------------|
| PHP                       | 8.3, 8.4, 8.5        |
| Laravel                   | 12.x, 13.x           |
| `kreait/firebase-php`     | `^7.19` or `^8.0`    |
| `kreait/laravel-firebase` | `^6.1` or `^7.1`     |

Laravel 13 requires `kreait/laravel-firebase` 7.1+, which in turn requires `kreait/firebase-php` 8.x.
On Laravel 12 you can use either major version of the Firebase SDK.

## Installation

```bash
composer require ankurk91/fcm-notification-channel
```

## Configuration

The Firebase connection is configured by
[kreait/laravel-firebase](https://github.com/beste/laravel-firebase), which is installed as a dependency.

1. Create a [service account](https://firebase.google.com/docs/admin/setup#initialize-sdk) in the Firebase console
   and download its JSON key file.
2. Put the file in your project root and point to it from `.env`:

   ```dotenv
   # Relative or absolute path to the service account JSON file
   FIREBASE_CREDENTIALS=firebase-credentials.json
   ```

3. Keep the key file out of version control:

   ```gitignore
   /firebase-credentials*.json
   ```

To configure more than one Firebase project, publish the config file and add entries under `projects`:

```bash
php artisan vendor:publish --provider="Kreait\Laravel\Firebase\ServiceProvider" --tag=config
```

## Usage

### 1. Create a notification

Return `FCMChannel::class` from `via()` and build the message in `toFCM()`.

```php
<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;
use Kreait\Firebase\Messaging\CloudMessage;
use NotificationChannels\FCM\FCMChannel;

class OrderShipped extends Notification implements ShouldQueue
{
    use Queueable;

    public function via(object $notifiable): array
    {
        return [FCMChannel::class];
    }

    public function toFCM(object $notifiable): CloudMessage
    {
        return CloudMessage::new()
            ->withDefaultSounds()
            ->withNotification([
                'title' => 'Order shipped',
                'body' => 'Your order for a laptop has shipped.',
            ])
            ->withData([
                'orderId' => '123',
            ]);
    }
}
```

Do not set a target (token, topic or condition) on the `CloudMessage` in `toFCM()`.
The channel sets it from the notifiable's routes and replaces anything set there.

See the [Firebase PHP SDK docs](https://firebase-php.readthedocs.io/stable/cloud-messaging.html) for everything
`CloudMessage` supports, such as Android, APNs and web push options.

### 2. Prepare the notifiable model

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Kreait\Firebase\Messaging\MessageTarget;

class User extends Authenticatable
{
    use Notifiable;

    /**
     * Assumes a `device_tokens` table that stores one FCM token per row.
     */
    public function deviceTokens(): HasMany
    {
        return $this->hasMany(DeviceToken::class);
    }

    /**
     * Required. Return a token, a topic, a condition, an array of tokens, or null.
     */
    public function routeNotificationForFCM($notification): string|array|null
    {
        return $this->deviceTokens->pluck('token')->all();
    }

    /**
     * Optional. Defaults to MessageTarget::TOKEN.
     */
    public function routeNotificationForFCMTargetType($notification): ?string
    {
        return MessageTarget::TOKEN;
    }

    /**
     * Optional. Name of a project from config/firebase.php. Defaults to the default project.
     */
    public function routeNotificationForFCMProject($notification): ?string
    {
        return config('firebase.default');
    }
}
```

### 3. Send it

```php
$user->notify(new \App\Notifications\OrderShipped());
```

## How messages are routed

| Route value from `routeNotificationForFCM` | Target type          | What the channel does                                                              |
|--------------------------------------------|----------------------|------------------------------------------------------------------------------------|
| `null` or `[]`                             | any                  | Sends nothing and returns `null`. No exception, no `NotificationFailed` event.     |
| One token (string or one-item array)       | `token`              | Sends one message with `Messaging::send()`.                                        |
| Two or more tokens                         | `token`              | Sends a multicast with `Messaging::sendMulticast()`.                               |
| Topic name, e.g. `news`                    | `topic`              | Sends one message to the topic.                                                    |
| Condition, e.g. `'news' in topics`         | `condition`          | Sends one message to the condition.                                                |
| Array with more than one value             | `topic`, `condition` | Only the **first** value is used. Multicast is only possible for tokens.           |
| Any value                                  | anything else        | Throws `NotificationChannels\FCM\Exception\RuntimeException`.                      |

Target types are case-insensitive.

## Sending to topics, conditions and token lists

Use Laravel's [on-demand notifications](https://laravel.com/docs/13.x/notifications#on-demand-notifications) when
there is no notifiable model:

```php
<?php

use App\Notifications\OrderShipped;
use Illuminate\Support\Facades\Notification;
use Kreait\Firebase\Messaging\MessageTarget;

// A topic
Notification::route('FCM', 'news')
    ->route('FCMTargetType', MessageTarget::TOPIC)
    ->notify(new OrderShipped());

// A condition
Notification::route('FCM', "'news' in topics || 'deals' in topics")
    ->route('FCMTargetType', MessageTarget::CONDITION)
    ->notify(new OrderShipped());

// Several tokens (multicast), sent through a non-default Firebase project
Notification::route('FCM', ['token_1', 'token_2'])
    ->route('FCMTargetType', MessageTarget::TOKEN)
    ->route('FCMProject', 'secondary')
    ->notify(new OrderShipped());
```

## Return values

`FCMChannel::send()` returns a value that Laravel passes on as `$event->response` in the
[`NotificationSent`](https://laravel.com/docs/13.x/notifications#notification-events) event.

| Case                    | Return value                                                     |
|-------------------------|------------------------------------------------------------------|
| No recipient            | `null`                                                           |
| Single target           | `array`, the decoded FCM API response                            |
| Multicast (2+ tokens)   | `Kreait\Firebase\Messaging\MulticastSendReport`                  |

## Quick reference

| Item                 | Value                                                                                        |
|----------------------|----------------------------------------------------------------------------------------------|
| Composer package     | `ankurk91/fcm-notification-channel`                                                          |
| Channel class        | `NotificationChannels\FCM\FCMChannel`                                                        |
| Notification method  | `toFCM($notifiable): Kreait\Firebase\Messaging\CloudMessage` (required)                      |
| Recipient route      | `routeNotificationForFCM($notification): string\|array\|null` (required)                     |
| Target type route    | `routeNotificationForFCMTargetType($notification): ?string` (optional, default `token`)      |
| Firebase project     | `routeNotificationForFCMProject($notification): ?string` (optional, default project)         |
| On-demand route keys | `FCM`, `FCMTargetType`, `FCMProject`                                                         |
| Target types         | `MessageTarget::TOKEN`, `MessageTarget::TOPIC`, `MessageTarget::CONDITION`                   |
| Exceptions namespace | `NotificationChannels\FCM\Exception`                                                         |
| Service provider     | None. The channel needs no registration; `kreait/laravel-firebase` is auto-discovered.       |

## Errors and exceptions

All exceptions thrown by this package extend `NotificationChannels\FCM\Exception\RuntimeException`,
which extends PHP's `\RuntimeException`.

| Exception                                              | When it is thrown                                                                          | `NotificationFailed` dispatched |
|--------------------------------------------------------|--------------------------------------------------------------------------------------------|---------------------------------|
| `NotificationChannels\FCM\Exception\InvalidRecipientException` | FCM answers "not found", usually because the device token is no longer registered. | Yes                             |
| `NotificationChannels\FCM\Exception\HttpException`     | Any other FCM messaging error. Implements `Kreait\Firebase\Exception\MessagingException`, so `errors()` is available. | Yes |
| `NotificationChannels\FCM\Exception\RuntimeException`  | The notification has no `toFCM()` method, or the target type is not supported.             | No                              |

Points to keep in mind:

- **Multicast partial failures do not throw.** When some tokens in a multicast fail, the call still succeeds and the
  failures are inside the returned `MulticastSendReport`. Only a failure of the whole request throws.
- The original Firebase exception is available as `getPrevious()` on the thrown exception, and as
  `$event->data['exception']` in the `NotificationFailed` event.
- For queued notifications, a thrown exception fails the job, so Laravel's normal retry rules apply.

## Removing invalid tokens

Laravel 11+ discovers listeners in `app/Listeners` automatically. Handle both events:
`NotificationFailed` for single-token sends and `NotificationSent` for multicast reports.

### Single token: `NotificationFailed`

```php
<?php

namespace App\Listeners;

use App\Models\User;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Events\NotificationFailed;
use Illuminate\Support\Arr;
use Kreait\Laravel\Firebase\Facades\Firebase;
use NotificationChannels\FCM\FCMChannel;

class DeleteInvalidFcmTokensOnFailure implements ShouldQueue
{
    public function handle(NotificationFailed $event): void
    {
        if ($event->channel !== FCMChannel::class || ! $event->notifiable instanceof User) {
            return;
        }

        $user = $event->notifiable;
        $tokens = Arr::wrap($user->routeNotificationFor('FCM', $event->notification));

        if ($tokens === []) {
            return;
        }

        $project = $user->routeNotificationFor('FCMProject', $event->notification);
        $result = Firebase::project($project)->messaging()->validateRegistrationTokens($tokens);

        $invalid = array_merge($result['invalid'], $result['unknown']);

        if ($invalid !== []) {
            $user->deviceTokens()->whereIn('token', $invalid)->delete();
        }
    }
}
```

### Multicast: `NotificationSent`

```php
<?php

namespace App\Listeners;

use App\Models\User;
use Illuminate\Notifications\Events\NotificationSent;
use Kreait\Firebase\Messaging\MulticastSendReport;
use NotificationChannels\FCM\FCMChannel;

class DeleteInvalidFcmTokensAfterMulticast
{
    public function handle(NotificationSent $event): void
    {
        if ($event->channel !== FCMChannel::class
            || ! $event->response instanceof MulticastSendReport
            || ! $event->notifiable instanceof User) {
            return;
        }

        $invalid = array_merge($event->response->invalidTokens(), $event->response->unknownTokens());

        if ($invalid !== []) {
            $event->notifiable->deviceTokens()->whereIn('token', $invalid)->delete();
        }
    }
}
```

Read more about
[validating registration tokens](https://firebase-php.readthedocs.io/stable/cloud-messaging.html#validating-registration-tokens).

### Not reporting invalid recipients

`InvalidRecipientException` is expected whenever a user uninstalls the app. To keep it out of your error tracker,
add it to `dontReport` in `bootstrap/app.php`:

```php
use Illuminate\Foundation\Configuration\Exceptions;
use NotificationChannels\FCM\Exception\InvalidRecipientException;

->withExceptions(function (Exceptions $exceptions) {
    $exceptions->dontReport([
        InvalidRecipientException::class,
    ]);
})
```

## Upgrading to `kreait/firebase-php` 8

Moving to Laravel 13, or running `composer update` on Laravel 12, can install `kreait/firebase-php` 8.
This package works with both 7.x and 8.x, so you only need changes if your own `toFCM()` code uses
`CloudMessage` methods that 8.x removed or renamed:

| 7.x                                           | 8.x                                                               |
|-----------------------------------------------|-------------------------------------------------------------------|
| `CloudMessage::withTarget($type, $value)`     | Removed. Use `CloudMessage::new()`. The channel sets the target.  |
| `$message->withChangedTarget($type, $value)`  | Removed. The channel sets the target.                             |
| `$message->hasTarget()`, `$message->target()` | Removed                                                           |
| `toToken()`, `toTopic()`, `toCondition()`     | Deprecated. Use `withToken()`, `withTopic()`, `withCondition()`.  |

See the SDK's [UPGRADE-8.0](https://github.com/beste/firebase-php/blob/8.x/UPGRADE-8.0.md) guide for the full list.

## Common mistakes

- Returning a string such as `'fcm'` from `via()`. Return `FCMChannel::class`.
- Naming the method `toFcm()` or `toFirebase()`. PHP method names are case-insensitive, so `toFcm()` works, but
  `toFCM()` is the documented name. Any other name throws a `RuntimeException`.
- Setting the token, topic or condition inside `toFCM()`. The channel replaces it.
- Passing several topics or conditions as an array. Only the first one is used; send one notification per topic,
  or use a condition such as `'a' in topics || 'b' in topics`.
- Expecting `NotificationFailed` for multicast token failures. Inspect the `MulticastSendReport` in `NotificationSent`
  instead.
- Registering a service provider for this package. None is needed.

## Changelog

See [CHANGELOG](CHANGELOG.md) for what has changed recently.

## Testing

```bash
composer test
```

## Security

If you discover a security issue, please email `pro.ankurk1[at]gmail[dot]com` instead of using the issue tracker.

## Attribution

The package is based on [this](https://github.com/beste/laravel-firebase/pull/69) rejected PR.

## License

This package is licensed under the [MIT License](LICENSE.txt).
