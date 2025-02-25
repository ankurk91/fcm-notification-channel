# Changelog

## [2.2.0](https://github.com/ankurk91/fcm-notification-channel/compare/2.1.0..2.2.0)

* Allow Laravel 12
* Drop Laravel 10

## [2.1.0](https://github.com/ankurk91/fcm-notification-channel/compare/2.0.0..2.1.0)

* Allow Laravel 11

## [2.0.0](https://github.com/ankurk91/fcm-notification-channel/compare/1.5.1..2.0.0)

* Require `kreait/firebase-php@^7.5` due to [Discontinued FCM Messaging API](https://github.com/kreait/firebase-php/issues/804)

## [1.5.1](https://github.com/ankurk91/fcm-notification-channel/compare/1.5.0..1.5.1)

* Add support for Laravel 10

## [1.5.0](https://github.com/ankurk91/fcm-notification-channel/compare/1.4.0..1.5.0)

* Bump `kreait/laravel-firebase` package to v5.x
* Drop php 8.0 support

## 1.4.0

* Drop Laravel 8 support
* Test on php 8.2

## 1.3.0

* Throw a different exception on Invalid token error

## 1.1.0

* Dispatch `NotificationFailed` event on failure
* Improve exception handling

## 1.0.0

* Initial release
