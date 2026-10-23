<?php

declare(strict_types=1);

namespace Tests\Feature\Notifications;

use App\Models\Notifications\PushSubscription;
use App\Models\User;
use App\Notifications\Channels\PushDeliveryChannel;
use App\Notifications\NotificationDelivery;
use App\Notifications\Services\WebPushService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Tests\TestCase;

class WebPushChannelAndSubscriptionTest extends TestCase
{
    use RefreshDatabase;

    public function test_vapid_key_endpoint_returns_configured_public_key(): void
    {
        $vapidKeys = WebPushService::generateVapidKeys();
        config([
            'services.webpush.vapid_public_key' => $vapidKeys['public_key'],
            'services.webpush.vapid_private_pem' => $vapidKeys['private_pem'],
        ]);

        seedE2Foundation();
        $user = User::query()->where('email', 'guidance.counselor@nexiam.test')->firstOrFail();

        $response = $this->actingAs($user)->getJson('/notifications/push-subscriptions/vapid-key');

        $response->assertOk()
            ->assertJson(['public_key' => $vapidKeys['public_key']]);
    }

    public function test_vapid_key_endpoint_rejects_missing_and_invalid_configuration(): void
    {
        seedE2Foundation();
        $user = User::query()->where('email', 'guidance.counselor@nexiam.test')->firstOrFail();
        config([
            'services.webpush.vapid_public_key' => null,
            'services.webpush.vapid_private_key' => null,
            'services.webpush.vapid_private_pem' => null,
        ]);

        $missingResponse = $this->actingAs($user)->getJson('/notifications/push-subscriptions/vapid-key');
        $missingResponse->assertStatus(503)->assertJson(['error' => 'vapid_configuration_unavailable']);

        $vapidKeys = WebPushService::generateVapidKeys();
        $mismatchedKeys = WebPushService::generateVapidKeys();
        config([
            'services.webpush.vapid_public_key' => $vapidKeys['public_key'],
            'services.webpush.vapid_private_pem' => $mismatchedKeys['private_pem'],
        ]);

        $invalidResponse = $this->actingAs($user)->getJson('/notifications/push-subscriptions/vapid-key');
        $invalidResponse->assertStatus(503)->assertJson(['error' => 'vapid_configuration_invalid']);
    }

    public function test_vapid_key_endpoint_requires_a_matching_raw_private_key(): void
    {
        seedE2Foundation();
        $user = User::query()->where('email', 'guidance.counselor@nexiam.test')->firstOrFail();
        $keys = WebPushService::generateVapidKeys();
        config([
            'services.webpush.vapid_public_key' => $keys['public_key'],
            'services.webpush.vapid_private_key' => $keys['private_key'],
            'services.webpush.vapid_private_pem' => null,
        ]);

        $this->actingAs($user)->getJson('/notifications/push-subscriptions/vapid-key')
            ->assertOk()
            ->assertJson(['public_key' => $keys['public_key']]);

        $otherKeys = WebPushService::generateVapidKeys();
        config(['services.webpush.vapid_private_key' => $otherKeys['private_key']]);

        $this->getJson('/notifications/push-subscriptions/vapid-key')
            ->assertStatus(503)
            ->assertJson(['error' => 'vapid_configuration_invalid']);
    }

    public function test_push_subscription_store_and_idempotent_update(): void
    {
        seedE2Foundation();
        $user = User::query()->where('email', 'guidance.counselor@nexiam.test')->firstOrFail();

        $endpoint = 'https://fcm.googleapis.com/fcm/send/test-device-token-'.Str::uuid();
        $payload = [
            'endpoint' => $endpoint,
            'p256dh' => 'BNcRdreALRFXTkOOUHK1EtK2wtaz5Ry4YfYCA_0QT9AcPP313T59P2O_S_LKD1X6a213A7k0-o',
            'auth' => 'tBHItJI5svbpez7KI4CCXg',
            'device_label' => 'Counselor Workstation Chrome',
        ];

        // Store
        $response = $this->actingAs($user)->putJson('/notifications/push-subscriptions', $payload);
        $response->assertOk()->assertJson(['status' => 'ok', 'device_label' => 'Counselor Workstation Chrome']);

        $subscriptionId = $response->json('id');
        $sub = PushSubscription::query()->findOrFail($subscriptionId);
        expect($sub->recipient_type)->toBe(User::class)
            ->and((int) $sub->recipient_id)->toBe((int) $user->id)
            ->and($sub->endpoint)->toBe($endpoint)
            ->and($sub->isActive())->toBeTrue();

        // Replay/update with same endpoint is idempotent
        $replay = $this->actingAs($user)->putJson('/notifications/push-subscriptions', [
            ...$payload,
            'device_label' => 'Updated Device Label',
        ]);
        $replay->assertOk()->assertJson(['id' => $subscriptionId, 'device_label' => 'Updated Device Label']);
        expect(PushSubscription::query()->where('endpoint_hash', hash('sha256', $endpoint))->count())->toBe(1);
    }

    public function test_push_delivery_rejects_missing_vapid_configuration(): void
    {
        seedE2Foundation();
        $user = User::query()->where('email', 'guidance.counselor@nexiam.test')->firstOrFail();
        $clientKeys = WebPushService::generateVapidKeys();
        config([
            'services.webpush.vapid_public_key' => null,
            'services.webpush.vapid_private_key' => null,
            'services.webpush.vapid_private_pem' => null,
        ]);
        $endpoint = 'https://push.example.com/'.Str::uuid();

        PushSubscription::query()->create([
            'id' => (string) Str::uuid(),
            'recipient_type' => User::class,
            'recipient_id' => $user->id,
            'endpoint' => $endpoint,
            'endpoint_hash' => hash('sha256', $endpoint),
            'p256dh' => $clientKeys['public_key'],
            'auth_token' => WebPushService::base64UrlEncode('16byteSecretAuth'),
            'vapid_key_id' => 'v1',
        ]);

        $delivery = NotificationDelivery::query()->create([
            'id' => (string) Str::uuid(),
            'event_id' => hash('sha256', 'test-event-missing-vapid'),
            'event_type' => 'test.push.event',
            'event_version' => 1,
            'recipient_type' => User::class,
            'recipient_id' => $user->id,
            'channel' => NotificationDelivery::CHANNEL_PUSH,
            'template_key' => 'test.push',
            'template_version' => 1,
            'payload' => ['title' => 'Test Alert', 'body' => 'Test notification content'],
            'status' => NotificationDelivery::STATUS_PENDING,
            'attempts' => 0,
        ]);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('vapid_configuration_unavailable');

        app(PushDeliveryChannel::class)->deliver($delivery);
    }

    public function test_push_subscription_revocation_enforces_ownership(): void
    {
        seedE2Foundation();
        $owner = User::query()->where('email', 'guidance.counselor@nexiam.test')->firstOrFail();
        $intruder = User::query()->where('email', 'registrar.clerk@nexiam.test')->firstOrFail();

        $sub = PushSubscription::query()->create([
            'id' => (string) Str::uuid(),
            'recipient_type' => User::class,
            'recipient_id' => $owner->id,
            'endpoint' => 'https://example.com/push/'.Str::uuid(),
            'endpoint_hash' => hash('sha256', 'https://example.com/push/test'),
            'p256dh' => 'mock-p256dh',
            'auth_token' => 'mock-auth',
            'vapid_key_id' => 'v1',
            'device_label' => 'Owner Phone',
        ]);

        // Intruder cannot revoke
        $this->actingAs($intruder)->deleteJson("/notifications/push-subscriptions/{$sub->id}")
            ->assertForbidden();
        expect($sub->fresh()->isActive())->toBeTrue();

        // Owner revokes
        $this->actingAs($owner)->deleteJson("/notifications/push-subscriptions/{$sub->id}")
            ->assertOk()->assertJson(['status' => 'revoked']);
        expect($sub->fresh()->isActive())->toBeFalse();
    }

    public function test_push_delivery_channel_reports_no_subscriptions_gracefully(): void
    {
        seedE2Foundation();
        $user = User::query()->where('email', 'guidance.counselor@nexiam.test')->firstOrFail();

        $delivery = NotificationDelivery::query()->create([
            'id' => (string) Str::uuid(),
            'event_id' => hash('sha256', 'test-event-1'),
            'event_type' => 'test.push.event',
            'event_version' => 1,
            'recipient_type' => User::class,
            'recipient_id' => $user->id,
            'channel' => NotificationDelivery::CHANNEL_PUSH,
            'template_key' => 'test.push',
            'template_version' => 1,
            'payload' => ['title' => 'Test Alert', 'body' => 'Test notification content', 'action_url' => '/test'],
            'status' => NotificationDelivery::STATUS_PENDING,
            'attempts' => 0,
        ]);

        $channel = app(PushDeliveryChannel::class);
        $result = $channel->deliver($delivery);

        expect($result)->toBe('no_subscriptions');
    }

    public function test_push_delivery_channel_sends_to_active_subscriptions_and_deactivates_on_410(): void
    {
        seedE2Foundation();
        $user = User::query()->where('email', 'guidance.counselor@nexiam.test')->firstOrFail();

        $vapidKeys = WebPushService::generateVapidKeys();
        config([
            'services.webpush.vapid_public_key' => $vapidKeys['public_key'],
            'services.webpush.vapid_private_pem' => $vapidKeys['private_pem'],
            'services.webpush.vapid_subject' => 'mailto:test@nexiam.edu.ph',
        ]);

        // Client EC P-256 key pair
        $clientKeys = WebPushService::generateVapidKeys();

        $deadEndpoint = 'https://push.example.com/dead/'.Str::uuid();
        $liveEndpoint = 'https://push.example.com/live/'.Str::uuid();

        $deadSub = PushSubscription::query()->create([
            'id' => (string) Str::uuid(),
            'recipient_type' => User::class,
            'recipient_id' => $user->id,
            'endpoint' => $deadEndpoint,
            'endpoint_hash' => hash('sha256', $deadEndpoint),
            'p256dh' => $clientKeys['public_key'],
            'auth_token' => WebPushService::base64UrlEncode('16byteSecretAuth'),
            'vapid_key_id' => 'v1',
        ]);

        $liveSub = PushSubscription::query()->create([
            'id' => (string) Str::uuid(),
            'recipient_type' => User::class,
            'recipient_id' => $user->id,
            'endpoint' => $liveEndpoint,
            'endpoint_hash' => hash('sha256', $liveEndpoint),
            'p256dh' => $clientKeys['public_key'],
            'auth_token' => WebPushService::base64UrlEncode('16byteSecretAuth'),
            'vapid_key_id' => 'v1',
        ]);

        Http::fake([
            $deadEndpoint => Http::response('Unregistered', 410),
            $liveEndpoint => Http::response('', 201),
        ]);

        $delivery = NotificationDelivery::query()->create([
            'id' => (string) Str::uuid(),
            'event_id' => hash('sha256', 'test-event-2'),
            'event_type' => 'test.push.event',
            'event_version' => 1,
            'recipient_type' => User::class,
            'recipient_id' => $user->id,
            'channel' => NotificationDelivery::CHANNEL_PUSH,
            'template_key' => 'test.push',
            'template_version' => 1,
            'payload' => ['title' => 'Important Test', 'body' => 'Schedule is live', 'action_url' => '/schedule'],
            'status' => NotificationDelivery::STATUS_PENDING,
            'attempts' => 0,
        ]);

        $channel = app(PushDeliveryChannel::class);
        $result = $channel->deliver($delivery);

        expect($result)->toBe('sent:1/2')
            ->and($deadSub->fresh()->isActive())->toBeFalse()
            ->and($liveSub->fresh()->isActive())->toBeTrue();
    }
}
