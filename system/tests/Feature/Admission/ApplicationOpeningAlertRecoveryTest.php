<?php

use App\Admission\Actions\ObserveApplicationOpeningAvailability;
use App\Admission\Actions\ReconcileApplicationOpeningAlerts;
use App\Foundation\Actions\RecordAuditEvent;
use App\Jobs\Notifications\ProcessNotificationDelivery;
use App\Models\Admission\ApplicationOpeningAlertReceipt;
use App\Models\Admission\ApplicationOpeningOccurrence;
use App\Models\Admission\ApplicationOpeningSubscription;
use App\Models\Communications\PublicPolicy;
use App\Models\Foundation\AcademicCycle;
use App\Models\Foundation\ApplicationWindow;
use App\Models\Foundation\Institution;
use App\Notifications\NotificationBusinessValidity;
use App\Notifications\NotificationDelivery;
use Carbon\CarbonImmutable;
use Database\Seeders\PublicIntakeConsentDemoSeeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;

function openingAlertRecoveryFixture(string $email = 'recovery@example.test'): array
{
    seedE2Foundation();
    (new PublicIntakeConsentDemoSeeder)->run();
    config(['application-opening-alerts.enabled' => true]);
    Queue::fake();

    $institution = Institution::current();
    $cycle = AcademicCycle::query()->where('code', 'SYN-CYCLE-2026')->firstOrFail();
    $cycle->forceFill([
        'opens_on' => now()->subMonth()->toDateString(),
        'closes_on' => now()->addMonth()->toDateString(),
        'is_active' => true,
    ])->save();
    $window = ApplicationWindow::query()->where('academic_cycle_id', $cycle->id)->firstOrFail();
    $window->forceFill([
        'opens_at' => now()->subHour(),
        'closes_at' => now()->addWeek(),
        'status' => 'published',
    ])->save();
    $availability = app(ObserveApplicationOpeningAvailability::class)->observeInstitution((int) $institution->id);
    $subscription = ApplicationOpeningSubscription::query()->create([
        'institution_id' => $institution->id,
        'email' => $email,
        'normalized_email' => mb_strtolower($email),
        'state' => ApplicationOpeningSubscription::STATE_VERIFIED,
        'generation' => 1,
        'consented_at' => now()->subDays(2),
        'consent_version' => 'opening-alerts-v1',
        'verified_at' => now()->subDays(2),
        'expires_at' => now()->addYear(),
    ]);

    return compact('institution', 'cycle', 'window', 'availability', 'subscription');
}

function openOpeningAlertDeliveries(ApplicationOpeningSubscription $subscription): Collection
{
    return NotificationDelivery::query()
        ->where('recipient_type', ApplicationOpeningSubscription::class)
        ->where('recipient_id', $subscription->id)
        ->where('event_type', 'application.opening_alert.opened.v1')
        ->orderBy('created_at')->get();
}

function processStaleOpeningAlertDelivery(NotificationDelivery $delivery): void
{
    app()->call([new ProcessNotificationDelivery((string) $delivery->id), 'handle'], [
        'recordAuditEvent' => app(RecordAuditEvent::class),
        'businessValidity' => app(NotificationBusinessValidity::class),
    ]);
}

test('duplicate reconciliations create one opening receipt and one delivery per subscription generation', function (): void {
    $fixture = openingAlertRecoveryFixture();
    $reconciler = app(ReconcileApplicationOpeningAlerts::class);

    $reconciler->handle();
    $reconciler->handle();

    expect(openOpeningAlertDeliveries($fixture['subscription']))->toHaveCount(1)
        ->and(ApplicationOpeningAlertReceipt::query()
            ->where('subscription_id', $fixture['subscription']->id)
            ->where('generation', 1)->count())->toBe(1);
});

test('a superseded prior generation cannot restore a newer confirmed and consumed signup', function (): void {
    $fixture = openingAlertRecoveryFixture('new-generation@example.test');
    $reconciler = app(ReconcileApplicationOpeningAlerts::class);
    $reconciler->handle();
    $oldDelivery = openOpeningAlertDeliveries($fixture['subscription'])->sole();
    $oldDelivery->forceFill(['status' => NotificationDelivery::STATUS_SUPERSEDED])->save();
    $oldReceipt = ApplicationOpeningAlertReceipt::query()->where('subscription_id', $fixture['subscription']->id)->sole();

    $subscription = $fixture['subscription']->fresh();
    $subscription->forceFill([
        'state' => ApplicationOpeningSubscription::STATE_CONSUMED,
        'generation' => 2,
        'consented_at' => now(),
        'consent_version' => 'opening-alerts-v1',
        'verified_at' => now(),
        'consumed_at' => now(),
        'expires_at' => now()->addYear(),
    ])->save();
    $fixture['cycle']->forceFill(['is_active' => false])->save();
    app(ObserveApplicationOpeningAvailability::class)->observeSourceChange($fixture['cycle']);
    $fixture['cycle']->forceFill(['is_active' => true])->save();
    app(ObserveApplicationOpeningAvailability::class)->observeSourceChange($fixture['cycle']);

    $reconciler->handle();

    expect($oldReceipt->fresh()->generation)->toBe(1)
        ->and($subscription->fresh()->generation)->toBe(2)
        ->and($subscription->fresh()->state)->toBe(ApplicationOpeningSubscription::STATE_CONSUMED)
        ->and($subscription->fresh()->consumed_at)->not->toBeNull();
});

test('unsent deadline changes A to B to A supersede old snapshots and leave one current delivery', function (): void {
    $fixture = openingAlertRecoveryFixture('deadline-revision@example.test');
    $reconciler = app(ReconcileApplicationOpeningAlerts::class);
    $observer = app(ObserveApplicationOpeningAvailability::class);
    $reconciler->handle();
    $first = openOpeningAlertDeliveries($fixture['subscription'])->sole();
    $deadlineA = $fixture['window']->closes_at->toIso8601String();

    $deadlineB = now()->addWeeks(2);
    $fixture['window']->forceFill(['closes_at' => $deadlineB])->save();
    $observer->observeSourceChange($fixture['window']);
    processStaleOpeningAlertDelivery($first);
    $reconciler->handle();
    $second = openOpeningAlertDeliveries($fixture['subscription'])->firstWhere('id', '!=', $first->id);

    $fixture['window']->forceFill(['closes_at' => CarbonImmutable::parse($deadlineA)])->save();
    $observer->observeSourceChange($fixture['window']);
    processStaleOpeningAlertDelivery($second);
    $reconciler->handle();
    $deliveries = openOpeningAlertDeliveries($fixture['subscription']);
    $current = $deliveries->firstWhere('status', NotificationDelivery::STATUS_PENDING);

    $reconciler->handle();

    expect($first->fresh()->status)->toBe(NotificationDelivery::STATUS_SUPERSEDED)
        ->and($second->fresh()->status)->toBe(NotificationDelivery::STATUS_SUPERSEDED)
        ->and($current)->not->toBeNull()
        ->and($current->payload['event_resource']['closes_at'])->toBe($deadlineA)
        ->and(app(NotificationBusinessValidity::class)->isCurrent($current->fresh()))->toBeTrue()
        ->and(openOpeningAlertDeliveries($fixture['subscription']))->toHaveCount(3);
});

test('outcome-unknown provider attempts are never reissued by opening reconciliation', function (): void {
    $fixture = openingAlertRecoveryFixture('unknown-provider@example.test');
    $reconciler = app(ReconcileApplicationOpeningAlerts::class);
    $reconciler->handle();
    $delivery = openOpeningAlertDeliveries($fixture['subscription'])->sole();
    $delivery->forceFill([
        'status' => NotificationDelivery::STATUS_OUTCOME_UNKNOWN,
        'outcome_unknown_at' => now(),
    ])->save();
    $fixture['window']->forceFill(['closes_at' => now()->addWeeks(2)])->save();
    app(ObserveApplicationOpeningAvailability::class)->observeSourceChange($fixture['window']);

    $reconciler->handle();

    expect($delivery->fresh()->status)->toBe(NotificationDelivery::STATUS_OUTCOME_UNKNOWN)
        ->and(openOpeningAlertDeliveries($fixture['subscription']))->toHaveCount(1);
});

test('reopen recovery reaches an eligible receipt after more than 500 obsolete generations', function (): void {
    $fixture = openingAlertRecoveryFixture('starved-eligible@example.test');
    $fixture['cycle']->forceFill(['is_active' => false])->save();
    app(ObserveApplicationOpeningAvailability::class)->observeSourceChange($fixture['cycle']);
    $fixture['cycle']->forceFill(['is_active' => true])->save();
    $availability = app(ObserveApplicationOpeningAvailability::class)->observeSourceChange($fixture['cycle']);
    $oldOccurrence = ApplicationOpeningOccurrence::query()->create([
        'institution_id' => $fixture['institution']->id,
        'academic_cycle_id' => $fixture['cycle']->id,
        'application_window_id' => $fixture['window']->id,
        'availability_generation' => 1,
        'opened_at' => now()->subDay(),
        'closes_at' => $fixture['window']->closes_at,
        'observed_at' => now()->subDay(),
        'availability_key' => hash('sha256', (string) Str::uuid()),
        'fanout_complete' => true,
    ]);

    $oldRows = [];
    $eligibleSubscription = null;
    for ($index = 0; $index < 502; $index++) {
        $generation = $index === 501 ? 1 : 2;
        $email = "old-generation-{$index}@example.test";
        $subscription = ApplicationOpeningSubscription::query()->create([
            'institution_id' => $fixture['institution']->id,
            'email' => $email,
            'normalized_email' => $email,
            'state' => ApplicationOpeningSubscription::STATE_CONSUMED,
            'generation' => $generation,
            'consented_at' => now()->subDays(3),
            'consent_version' => 'opening-alerts-v1',
            'verified_at' => now()->subDays(2),
            'consumed_at' => now()->subDay(),
            'expires_at' => now()->addYear(),
        ]);
        if ($index === 501) {
            $eligibleSubscription = $subscription;
        }
        $deliveryId = (string) Str::uuid();
        $oldRows[] = [
            'subscription' => $subscription,
            'delivery_id' => $deliveryId,
            'delivery' => [
                'id' => $deliveryId,
                'event_id' => hash('sha256', "old-opening-receipt:{$subscription->id}"),
                'event_type' => 'application.opening_alert.opened.v1',
                'event_version' => 1,
                'recipient_type' => ApplicationOpeningSubscription::class,
                'recipient_id' => $subscription->id,
                'channel' => NotificationDelivery::CHANNEL_MAIL,
                'template_key' => 'application.opening_alert.opened',
                'template_version' => 1,
                'payload' => json_encode([
                    'snapshot_schema_version' => 'application-opening-alert-1',
                    'subscription_id' => $subscription->id,
                    'institution_id' => $fixture['institution']->id,
                    'event_resource' => ['subscription_id' => $subscription->id, 'generation' => 1],
                    'recipient' => ['type' => ApplicationOpeningSubscription::class, 'id' => $subscription->id],
                ], JSON_THROW_ON_ERROR),
                'status' => NotificationDelivery::STATUS_SUPERSEDED,
                'attempts' => 0,
                'created_at' => now()->subDay(),
                'updated_at' => now(),
            ],
        ];
    }
    DB::table('notification_deliveries')->insert(array_column($oldRows, 'delivery'));
    DB::table('application_opening_alert_receipts')->insert(array_map(
        fn (array $row): array => [
            'subscription_id' => $row['subscription']->id,
            'generation' => 1,
            'occurrence_id' => $oldOccurrence->id,
            'delivery_id' => $row['delivery_id'],
            'status' => 'claimed',
            'claimed_at' => now()->subDay(),
            'created_at' => now()->subDay(),
            'updated_at' => now(),
        ],
        $oldRows,
    ));

    app(ReconcileApplicationOpeningAlerts::class)->handle();

    $oldEligibleReceipt = ApplicationOpeningAlertReceipt::query()
        ->where('subscription_id', $eligibleSubscription->id)->where('occurrence_id', $oldOccurrence->id)->firstOrFail();
    expect($oldEligibleReceipt->status)->toBe('superseded')
        ->and(ApplicationOpeningAlertReceipt::query()->where('subscription_id', $eligibleSubscription->id)->count())->toBe(2);
});

test('a scheduled scan detects the clock opening without another staff save', function (): void {
    $clock = CarbonImmutable::parse('2026-10-23 12:00:00', 'UTC');
    Carbon::setTestNow($clock);
    try {
        $fixture = openingAlertRecoveryFixture('clock-opening@example.test');
        $fixture['cycle']->update(['opens_on' => '2026-10-23', 'closes_on' => '2026-11-24']);
        $fixture['window']->update(['opens_at' => '2026-10-24 12:00:00', 'closes_at' => '2026-11-24 23:59:59']);
        $reconciler = app(ReconcileApplicationOpeningAlerts::class);
        $reconciler->handle();
        expect(openOpeningAlertDeliveries($fixture['subscription']))->toHaveCount(0);

        Carbon::setTestNow($clock->addDay()->addMinute());
        $reconciler->handle();
        expect(openOpeningAlertDeliveries($fixture['subscription']))->toHaveCount(1)
            ->and($fixture['subscription']->fresh()->state)->toBe(ApplicationOpeningSubscription::STATE_CONSUMED);
        $reconciler->handle();
        expect(openOpeningAlertDeliveries($fixture['subscription']))->toHaveCount(1);
    } finally {
        Carbon::setTestNow();
    }
});

test('opening alerts wait for the real designated privacy policy and recover when it is restored', function (): void {
    $fixture = openingAlertRecoveryFixture('policy-readiness@example.test');
    $policy = PublicPolicy::designatedIntake((int) $fixture['institution']->id);
    $policy->update(['is_designated_intake' => false]);
    $reconciler = app(ReconcileApplicationOpeningAlerts::class);
    $reconciler->handle();
    expect(openOpeningAlertDeliveries($fixture['subscription']))->toHaveCount(0)
        ->and($fixture['subscription']->fresh()->state)->toBe(ApplicationOpeningSubscription::STATE_VERIFIED);

    $policy->update(['is_designated_intake' => true]);
    $reconciler->handle();
    expect(openOpeningAlertDeliveries($fixture['subscription']))->toHaveCount(1)
        ->and($fixture['subscription']->fresh()->state)->toBe(ApplicationOpeningSubscription::STATE_CONSUMED);
});
