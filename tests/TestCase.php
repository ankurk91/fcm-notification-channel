<?php

declare(strict_types=1);

namespace NotificationChannels\FCM\Tests;

use Kreait\Laravel\Firebase;

abstract class TestCase extends \Orchestra\Testbench\TestCase
{
    protected function getPackageProviders($app): array
    {
        return [
            Firebase\ServiceProvider::class,
        ];
    }
}
