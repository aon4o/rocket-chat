<?php

declare(strict_types=1);

namespace NotificationChannels\RocketChat\Test;

use GuzzleHttp\Client as GuzzleHttpClient;
use GuzzleHttp\Psr7\Response;
use Illuminate\Notifications\Notifiable;
use Illuminate\Notifications\Notification;
use Mockery;
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;
use NotificationChannels\RocketChat\Exceptions\CouldNotSendNotification;
use NotificationChannels\RocketChat\RocketChat;
use NotificationChannels\RocketChat\RocketChatMessage;
use NotificationChannels\RocketChat\RocketChatWebhookChannel;
use PHPUnit\Framework\TestCase;

final class RocketChatWebhookChannelTest extends TestCase
{
    use MockeryPHPUnitIntegration;

    public function test_it_can_send_a_notification(): void
    {
        $client = Mockery::mock(GuzzleHttpClient::class);

        $apiBaseUrl = 'http://localhost:3000';
        $token = ':token';
        $user_id = ':user_id';
        $channel = ':channel';

        $client->shouldReceive('post')->once()
            ->with(
                "{$apiBaseUrl}/api/v1/chat.postMessage",
                [
                    'headers' => [
                        'X-Auth-Token' => $token,
                        'X-User-Id' => $user_id,
                        'Rocket-Channel-Id' => $channel,
                        'Content-Type' => 'application/json',
                    ],
                    'json' => [
                        'text' => 'hello',
                        'channel' => $channel,
                    ],
                ]
            )->andReturn(new Response(200));

        $rocketChat = new RocketChat($client, $apiBaseUrl, $token, $user_id, $channel);
        $channel = new RocketChatWebhookChannel($rocketChat);
        $channel->send(new TestNotifiable(), new TestNotification());
    }

    public function test_it_handles_generic_errors(): void
    {
        $client = Mockery::mock(GuzzleHttpClient::class);
        $this->expectException(CouldNotSendNotification::class);

        $apiBaseUrl = 'http://localhost:3000';
        $token = ':token';
        $user_id = ':user_id';
        $channel = ':channel';

        $client->shouldReceive('post')->once()
            ->with(
                "{$apiBaseUrl}/api/v1/chat.postMessage",
                [
                    'headers' => [
                        'X-Auth-Token' => $token,
                        'X-User-Id' => $user_id,
                        'Rocket-Channel-Id' => $channel,
                        'Content-Type' => 'application/json',
                    ],
                    'json' => [
                        'text' => 'hello',
                        'channel' => $channel,
                    ],
                ]
            )->andThrow(new \Exception('Test'));

        $rocketChat = new RocketChat($client, $apiBaseUrl, $token, $user_id, $channel);
        $channel = new RocketChatWebhookChannel($rocketChat);
        $channel->send(new TestNotifiable(), new TestNotification());
    }

    public function test_it_does_not_send_a_message_when_channel_missed(): void
    {
        $this->expectException(CouldNotSendNotification::class);

        $rocketChat = new RocketChat(new GuzzleHttpClient(), '', '', '', '', '');
        $channel = new RocketChatWebhookChannel($rocketChat);
        $channel->send(new TestNotifiable(), new TestNotificationWithMissedChannel());
    }

    public function test_it_does_not_send_a_message_when_from_missed(): void
    {
        $this->expectException(CouldNotSendNotification::class);

        $rocketChat = new RocketChat(new GuzzleHttpClient(), '', '', '', '');
        $channel = new RocketChatWebhookChannel($rocketChat);
        $channel->send(new TestNotifiable(), new TestNotificationWithMissedFrom());
    }
}

class TestNotifiable
{
    use Notifiable;

    public function routeNotificationForRocketChat(): string
    {
        return '';
    }
}

class TestNotification extends Notification
{
    public function toRocketChat(): RocketChatMessage
    {
        return RocketChatMessage::create('hello')->from(':token')->to(':channel');
    }
}

class TestNotificationWithMissedChannel extends Notification
{
    public function toRocketChat(): RocketChatMessage
    {
        return RocketChatMessage::create('hello')->from(':token');
    }
}

class TestNotificationWithMissedFrom extends Notification
{
    public function toRocketChat(): RocketChatMessage
    {
        return RocketChatMessage::create('hello')->to(':channel');
    }
}
