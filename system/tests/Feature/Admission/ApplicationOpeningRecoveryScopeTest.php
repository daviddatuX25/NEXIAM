<?php

use App\Jobs\Notifications\ProcessNotificationDelivery;
use App\Models\Admission\ApplicationOpeningSubscription;
use App\Models\Foundation\Institution;
use App\Models\Notifications\CommunicationTemplate;
use App\Models\Notifications\CommunicationTemplateVersion;
use App\Models\User;
use App\Notifications\NotificationDelivery;
use App\Notifications\Templates\ProvisionCanonicalCommunicationTemplates;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;

test('opening alert recovery never queues unrelated admissions mail or anonymous push work', function (): void {
    seedE2Foundation();
    Queue::fake();
    $subscription = ApplicationOpeningSubscription::query()->create([
        'institution_id' => Institution::current()->id,
        'email' => 'scoped-recovery@example.test',
        'normalized_email' => 'scoped-recovery@example.test',
        'state' => 'pending',
        'generation' => 1,
        'consented_at' => now(),
        'consent_version' => 'opening-alerts-v1',
        'expires_at' => now()->addYear(),
    ]);
    $ids = [];
    foreach ([
        ['application.opening_alert.verify.v1', ApplicationOpeningSubscription::class, $subscription->id, 'mail'],
        ['admission.accepted.v1', User::class, User::query()->firstOrFail()->id, 'mail'],
        ['application.opening_alert.opened.v1', ApplicationOpeningSubscription::class, $subscription->id, 'push'],
    ] as [$event, $type, $recipient, $channel]) {
        $id = (string) Str::uuid();
        $ids[] = $id;
        NotificationDelivery::query()->create([
            'id' => $id, 'event_id' => hash('sha256', $id), 'event_type' => $event,
            'event_version' => 1, 'recipient_type' => $type, 'recipient_id' => $recipient,
            'channel' => $channel, 'template_key' => $event, 'template_version' => 1,
            'payload' => [], 'status' => NotificationDelivery::STATUS_PENDING, 'attempts' => 0,
        ]);
    }

    $this->artisan('notifications:recover-deliveries', ['--opening-alerts-only' => true])->assertSuccessful();
    Queue::assertPushed(ProcessNotificationDelivery::class, fn ($job): bool => $job->deliveryId === $ids[0]);
    Queue::assertNotPushed(ProcessNotificationDelivery::class, fn ($job): bool => in_array($job->deliveryId, [$ids[1], $ids[2]], true));
});

test('opening template provisioning creates only the requested families and preserves published versions on replay', function (): void {
    $institution = Institution::query()->create(['code' => 'OPENING-TEMPLATE-SCOPE', 'name' => 'Synthetic template scope']);
    $provision = app(ProvisionCanonicalCommunicationTemplates::class);
    $families = ['opening-alert-verify', 'opening-alert-opened'];
    $provision->provision($institution, null, 'admissions', $families);
    $templates = CommunicationTemplate::query()->where('institution_id', $institution->id)->get();
    expect($templates->pluck('slug')->sort()->values()->all())->toBe(['opening-alert-opened', 'opening-alert-verify']);
    $versions = CommunicationTemplateVersion::query()->whereIn('communication_template_id', $templates->modelKeys())->get()->toArray();
    $provision->provision($institution, null, 'admissions', $families);
    expect(CommunicationTemplateVersion::query()->whereIn('communication_template_id', $templates->modelKeys())->get()->toArray())->toBe($versions);
});
