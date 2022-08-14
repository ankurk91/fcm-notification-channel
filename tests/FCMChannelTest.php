<?php
declare(strict_types=1);

namespace NotificationChannels\FCM\Tests;

use Closure;
use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Notification;
use Kreait\Firebase\Contract\Messaging as MessagingClient;
use Kreait\Firebase\Exception\Messaging\MessagingError;
use Kreait\Firebase\Exception\Messaging\NotFound;
use Kreait\Firebase\Messaging\MessageTarget;
use Kreait\Firebase\Messaging\MulticastSendReport;
use Kreait\Laravel\Firebase\Facades\Firebase;
use Kreait\Laravel\Firebase\FirebaseProject;
use Mockery;
use NotificationChannels\FCM\Exception\HttpException;
use NotificationChannels\FCM\Exception\InvalidRecipientException;
use NotificationChannels\FCM\Exception\RuntimeException;
use NotificationChannels\FCM\FCMChannel;
use NotificationChannels\FCM\Tests\Resources\InvalidTestNotification;
use NotificationChannels\FCM\Tests\Resources\TestModel;
use NotificationChannels\FCM\Tests\Resources\TestNotification;
use Roave\BetterReflection\Reflection\ReflectionObject;

class FCMChannelTest extends TestCase
{
    protected FCMChannel $channel;
    protected Dispatcher $events;

    protected function setUp(): void
    {
        $this->events = Mockery::mock(Dispatcher::class);
        $this->channel = new FCMChannel($this->events);

        parent::setUp();
    }

    protected function mockMessaging(Closure $mock): void
    {
        $messaging = $this->mock(MessagingClient::class, $mock);

        $project = $this->mock(FirebaseProject::class, function ($mock) use ($messaging) {
            $mock->shouldReceive('messaging')->withNoArgs()->andReturn($messaging);
        });

        Firebase::shouldReceive('project')
            ->withAnyArgs()
            ->andReturn($project);
    }

    protected function getPropertyValue(object $object, string $property)
    {
        return ReflectionObject::createFromInstance($object)
            ->getProperty($property)
            ->getValue($object);
    }

    /** @test */
    public function an_exception_is_thrown_if_to_FCM_method_is_missing()
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Notification class is missing toFCM method.');

        $this->channel->send(new TestModel, new InvalidTestNotification);
    }

    /** @test */
    public function a_message_can_be_send_to_a_target()
    {
        $this->mockMessaging(function ($mock) {
            $mock->shouldReceive('send')->withArgs(function ($message) {
                $this->assertEquals('single-token', $this->getPropertyValue($message, 'target')->value());

                $this->assertEquals([
                    'title' => 'A notification title',
                    'body' => 'A notification body',
                ], $this->getPropertyValue($message, 'notification')->jsonSerialize());

                return true;
            })->andReturn(['response-key' => 1]);
        });

        $response = $this->channel->send(new TestModel, new TestNotification);

        $this->assertIsArray($response);
        $this->assertIsArray(Arr::first($response));
        $this->assertArrayHasKey('response-key', Arr::first($response));
    }

    /** @test */
    public function a_message_can_be_send_to_a_topic()
    {
        $this->mockMessaging(function ($mock) {
            $mock->shouldReceive('send')->withArgs(function ($message) {
                $this->assertEquals('valid-topic', $this->getPropertyValue($message, 'target')->value());

                $this->assertEquals([
                    'title' => 'A notification title',
                    'body' => 'A notification body',
                ], $this->getPropertyValue($message, 'notification')->jsonSerialize());

                return true;
            })->andReturn(['response-key' => 2]);
        });

        $response = $this->channel->send(new TestModel(MessageTarget::TOPIC), new TestNotification);

        $this->assertIsArray($response);
        $this->assertIsArray(Arr::first($response));
        $this->assertArrayHasKey('response-key', Arr::first($response));
    }

    /** @test */
    public function a_message_can_be_send_to_a_condition()
    {
        $this->mockMessaging(function ($mock) {
            $mock->shouldReceive('send')->withArgs(function ($message) {
                $this->assertEquals("'valid-topic1' in Topics", $this->getPropertyValue($message, 'target')->value());

                $this->assertEquals([
                    'title' => 'A notification title',
                    'body' => 'A notification body',
                ], $this->getPropertyValue($message, 'notification')->jsonSerialize());

                return true;
            })->andReturn(['response-key' => 2]);
        });

        $response = $this->channel->send(new TestModel(MessageTarget::CONDITION), new TestNotification);

        $this->assertIsArray($response);
        $this->assertIsArray(Arr::first($response));
        $this->assertArrayHasKey('response-key', Arr::first($response));
    }

    /** @test */
    public function a_message_can_be_send_to_multicast()
    {
        $this->mockMessaging(function ($mock) {
            $mock->shouldReceive('sendMulticast')->withArgs(function ($message) {
                $this->assertEquals([
                    'title' => 'A notification title',
                    'body' => 'A notification body',
                ], $this->getPropertyValue($message, 'notification')->jsonSerialize());

                return true;
            })->andReturn(MulticastSendReport::withItems([]));
        });

        $response = $this->channel->send(new TestModel(MessageTarget::TOKEN, true), new TestNotification);

        $this->assertIsArray($response);
        $this->assertInstanceOf(MulticastSendReport::class, Arr::first($response));
    }

    /** @test */
    public function an_exception_will_be_thrown_when_failed()
    {
        $this->mockMessaging(function ($mock) {
            $mock->shouldReceive('send')->andThrows(new MessagingError('A messaging error.'));
        });

        $this->events->shouldReceive('dispatch')->once()->withAnyArgs();
        $this->expectException(HttpException::class);
        $this->expectExceptionMessage('A messaging error.');

        $this->channel->send(new TestModel(), new TestNotification);
    }

    /** @test */
    public function throws_exception_on_token_not_found()
    {
        $this->mockMessaging(function ($mock) {
            $mock->shouldReceive('send')->andThrows(new NotFound('Invalid token.'));
        });

        $this->events->shouldReceive('dispatch')->once()->withAnyArgs();
        $this->expectException(InvalidRecipientException::class);
        $this->expectExceptionMessage('Invalid token.');

        $this->channel->send(new TestModel(), new TestNotification);
    }

    /** @test */
    public function nothing_is_sent_when_no_token_is_supplied()
    {
        $response = $this->channel->send(new TestModel(null), new TestNotification);

        $this->assertIsArray($response);
        $this->assertEmpty($response);
    }

    /** @test */
    public function it_supports_anonymous_notifiable()
    {
        $this->mockMessaging(function ($mock) {
            $mock->shouldReceive('send')->withArgs(function ($message) {
                $this->assertEquals('token_1', $this->getPropertyValue($message, 'target')->value());

                $this->assertEquals([
                    'title' => 'A notification title',
                    'body' => 'A notification body',
                ], $this->getPropertyValue($message, 'notification')->jsonSerialize());

                return true;
            })->andReturn(['response-key' => 1]);
        });

        Event::fake();

        Notification::route('FCM', 'token_1')
            ->route('FCMTargetType', MessageTarget::TOKEN)
            ->notify(new TestNotification);
    }
}
