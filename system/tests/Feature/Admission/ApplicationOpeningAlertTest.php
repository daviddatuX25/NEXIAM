<?php

use App\Admission\Actions\ObserveApplicationOpeningAvailability;
use App\Admission\Actions\PurgeApplicationOpeningAlertData;
use App\Models\Admission\ApplicantIdentity;
use App\Models\Admission\ApplicationOpeningAlertReceipt;
use App\Models\Admission\ApplicationOpeningOccurrence;
use App\Models\Admission\ApplicationOpeningSubscription;
use App\Models\Admission\ApplicationOpeningToken;
use App\Models\Foundation\AcademicCycle;
use App\Models\Foundation\ApplicationWindow;
use App\Models\Foundation\Institution;
use App\Models\User;
use App\Notifications\ApplicationOpeningAlertDelivery;
use App\Notifications\Mail\RenderApplicationOpeningAlertMail;
use App\Notifications\NotificationDelivery;
use Database\Seeders\PublicIntakeConsentDemoSeeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;

function closeCurrentApplicationWindowForAlertTest(): void
{
    seedE2Foundation();
    config(['application-opening-alerts.enabled' => true]);
    $cycle = AcademicCycle::query()->where('code', 'SYN-CYCLE-2026')->firstOrFail();
    $window = ApplicationWindow::query()->where('academic_cycle_id', $cycle->id)->firstOrFail();
    $window->forceFill([
        'opens_at' => now()->addDay(),
        'closes_at' => now()->addMonth(),
    ])->save();
}

test('opening alert signup is generic, normalized and separate from applicant accounts until explicit mailbox confirmation', function (): void {
    closeCurrentApplicationWindowForAlertTest();
    Queue::fake();
    $userCount = User::query()->count();
    $identityCount = ApplicantIdentity::query()->count();

    $response = $this->postJson(route('apply.opening-alerts.store'), [
        'email' => '  Visitor.One@Example.test ',
        'consent' => true,
    ])->assertOk()->assertJsonPath('status', 'confirmation_sent');
    $response->assertJsonMissingPath('email');

    $subscription = ApplicationOpeningSubscription::query()->sole();
    expect($subscription->normalized_email)->toBe('visitor.one@example.test')
        ->and($subscription->state)->toBe(ApplicationOpeningSubscription::STATE_PENDING)
        ->and($subscription->consented_at)->not->toBeNull()
        ->and($subscription->generation)->toBe(1)
        ->and(User::query()->count())->toBe($userCount)
        ->and(ApplicantIdentity::query()->count())->toBe($identityCount);

    $token = ApplicationOpeningToken::query()->where('subscription_id', $subscription->id)->where('purpose', 'verify')->sole();
    $plainToken = $token->encrypted_token;
    $verificationDelivery = NotificationDelivery::query()
        ->where('recipient_type', ApplicationOpeningSubscription::class)
        ->where('recipient_id', $subscription->id)
        ->where('event_type', 'application.opening_alert.verify.v1')
        ->sole();
    expect($verificationDelivery->channel)->toBe(NotificationDelivery::CHANNEL_MAIL)
        ->and($verificationDelivery->payload['snapshot_schema_version'])->toBe('application-opening-alert-1')
        ->and(json_encode($verificationDelivery->payload))->not->toContain('visitor.one@example.test', $plainToken);
    $confirmationPage = $this->get(route('apply.opening-alerts.show', ['token' => $plainToken, 'purpose' => 'verify']))
        ->assertOk()
        ->assertHeader('Referrer-Policy', 'no-referrer')
        ->assertInertia(fn ($page) => $page->component('Admission/OpeningAlert')->where('state', 'verify'));
    expect($confirmationPage->headers->hasCacheControlDirective('no-store'))->toBeTrue();
    expect($subscription->fresh()->state)->toBe(ApplicationOpeningSubscription::STATE_PENDING)
        ->and($token->fresh()->consumed_at)->toBeNull();

    $this->post(route('apply.opening-alerts.resolve', $plainToken), ['purpose' => 'verify'])
        ->assertOk()->assertInertia(fn ($page) => $page->where('state', 'confirmed'));
    expect($subscription->fresh()->state)->toBe(ApplicationOpeningSubscription::STATE_VERIFIED)
        ->and($subscription->fresh()->verified_at)->not->toBeNull()
        ->and($token->fresh()->consumed_at)->not->toBeNull();

    $this->postJson(route('apply.opening-alerts.store'), [
        'email' => 'visitor.one@example.test',
        'consent' => true,
    ])->assertOk()->assertJsonPath('status', 'confirmation_sent')
        ->assertJsonMissingPath('email');
    expect(ApplicationOpeningSubscription::query()->count())->toBe(1);
});

test('unsubscribe links remain scanner-safe and require an explicit matching POST', function (): void {
    closeCurrentApplicationWindowForAlertTest();
    $subscription = ApplicationOpeningSubscription::query()->create([
        'institution_id' => Institution::current()->id,
        'email' => 'unsubscribe@example.test',
        'normalized_email' => 'unsubscribe@example.test',
        'state' => ApplicationOpeningSubscription::STATE_VERIFIED,
        'generation' => 1,
        'consented_at' => now()->subDay(),
        'consent_version' => 'opening-alerts-v1',
        'verified_at' => now()->subDay(),
        'expires_at' => now()->addYear(),
    ]);
    $plainToken = Str::random(64);
    $token = ApplicationOpeningToken::query()->create([
        'id' => (string) Str::uuid(),
        'subscription_id' => $subscription->id,
        'generation' => 1,
        'purpose' => 'unsubscribe',
        'token_hash' => hash('sha256', $plainToken),
        'encrypted_token' => $plainToken,
        'expires_at' => now()->addYear(),
    ]);

    $this->get(route('apply.opening-alerts.show', ['token' => $plainToken, 'purpose' => 'unsubscribe']))
        ->assertOk()->assertInertia(fn ($page) => $page->where('state', 'unsubscribe'));
    expect($subscription->fresh()->state)->toBe(ApplicationOpeningSubscription::STATE_VERIFIED)
        ->and($token->fresh()->consumed_at)->toBeNull();

    $this->post(route('apply.opening-alerts.resolve', $plainToken), ['purpose' => 'unsubscribe'])
        ->assertOk()->assertInertia(fn ($page) => $page->where('state', 'unsubscribed'));
    expect($subscription->fresh()->state)->toBe(ApplicationOpeningSubscription::STATE_UNSUBSCRIBED)
        ->and($subscription->fresh()->unsubscribed_at)->not->toBeNull()
        ->and($token->fresh()->consumed_at)->not->toBeNull()
        ->and($token->fresh()->encrypted_token)->toBeNull();
});

test('opening alerts fail closed until the feature is enabled', function (): void {
    seedE2Foundation();
    config(['application-opening-alerts.enabled' => false]);

    $this->postJson(route('apply.opening-alerts.store'), [
        'email' => 'closed@example.test',
        'consent' => true,
    ])->assertStatus(503);
    expect(ApplicationOpeningSubscription::query()->count())->toBe(0);
});

test('opaque prefill token binds an editable email only into the anonymous session and redirects away from the token', function (): void {
    seedE2Foundation();
    config(['application-opening-alerts.enabled' => true]);
    $institution = Institution::current();
    $subscription = ApplicationOpeningSubscription::query()->create([
        'institution_id' => $institution->id,
        'email' => 'prefill@example.test',
        'normalized_email' => 'prefill@example.test',
        'state' => ApplicationOpeningSubscription::STATE_CONSUMED,
        'generation' => 1,
        'consented_at' => now()->subDay(),
        'consent_version' => 'opening-alerts-v1',
        'verified_at' => now()->subDay(),
        'consumed_at' => now(),
        'expires_at' => now()->addYear(),
    ]);
    $plainToken = Str::random(64);
    ApplicationOpeningToken::query()->create([
        'id' => (string) Str::uuid(),
        'subscription_id' => $subscription->id,
        'generation' => 1,
        'purpose' => 'prefill',
        'token_hash' => hash('sha256', $plainToken),
        'encrypted_token' => $plainToken,
        'expires_at' => now()->addMinutes(15),
    ]);

    $prefillRedirect = $this->get(route('apply.opening-alerts.prefill', $plainToken))
        ->assertRedirect('/apply')
        ->assertSessionHas('application_opening_prefill.email', 'prefill@example.test');
    expect($prefillRedirect->headers->hasCacheControlDirective('no-store'))->toBeTrue();
    expect(ApplicationOpeningToken::query()->where('token_hash', hash('sha256', $plainToken))->value('consumed_at'))->toBeNull();
    $this->withSession([])->get(route('apply.opening-alerts.prefill', $plainToken))
        ->assertRedirect('/apply')
        ->assertSessionHas('application_opening_prefill.email', 'prefill@example.test');
});

test('opening alert freezes the active authored template and renders scoped links in HTML and text', function (): void {
    seedE2Foundation();
    (new PublicIntakeConsentDemoSeeder)->run();
    config(['application-opening-alerts.enabled' => true]);
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
    $availability = app(ObserveApplicationOpeningAvailability::class)
        ->observeInstitution((int) $institution->id);
    $subscription = ApplicationOpeningSubscription::query()->create([
        'institution_id' => $institution->id,
        'email' => 'opening@example.test',
        'normalized_email' => 'opening@example.test',
        'state' => ApplicationOpeningSubscription::STATE_CONSUMED,
        'generation' => 1,
        'consented_at' => now()->subDay(),
        'consent_version' => 'opening-alerts-v1',
        'verified_at' => now()->subHours(2),
        'consumed_at' => now(),
        'expires_at' => now()->addYear(),
    ]);
    $occurrence = ApplicationOpeningOccurrence::query()->create([
        'institution_id' => $institution->id,
        'academic_cycle_id' => $cycle->id,
        'application_window_id' => $window->id,
        'availability_generation' => $availability->generation,
        'opened_at' => now()->subHour(),
        'closes_at' => $window->closes_at,
        'observed_at' => now(),
        'availability_key' => hash('sha256', (string) Str::uuid()),
        'fanout_complete' => true,
    ]);
    $unsubscribe = ApplicationOpeningToken::query()->create([
        'id' => (string) Str::uuid(),
        'subscription_id' => $subscription->id,
        'generation' => 1,
        'purpose' => 'unsubscribe',
        'token_hash' => hash('sha256', $unsubscribePlain = Str::random(64)),
        'encrypted_token' => $unsubscribePlain,
        'expires_at' => now()->addYear(),
    ]);
    $prefill = ApplicationOpeningToken::query()->create([
        'id' => (string) Str::uuid(),
        'subscription_id' => $subscription->id,
        'generation' => 1,
        'purpose' => 'prefill',
        'token_hash' => hash('sha256', $prefillPlain = Str::random(64)),
        'encrypted_token' => $prefillPlain,
        'expires_at' => now()->addHour(),
    ]);
    $receipt = ApplicationOpeningAlertReceipt::query()->create([
        'subscription_id' => $subscription->id,
        'generation' => 1,
        'occurrence_id' => $occurrence->id,
        'status' => 'claimed',
        'claimed_at' => now(),
    ]);
    $delivery = DB::transaction(function () use ($subscription, $occurrence, $cycle, $window, $institution, $unsubscribe, $prefill, $receipt) {
        $delivery = app(ApplicationOpeningAlertDelivery::class)->openingAfterCommit(
            $subscription, $occurrence, $cycle, $window, $institution, $unsubscribe, $prefill,
        );
        $receipt->forceFill(['delivery_id' => $delivery?->id])->save();

        return $delivery;
    });
    $rendered = app(RenderApplicationOpeningAlertMail::class)->render($delivery->fresh());
    expect($delivery->payload['snapshot_schema_version'])->toBe('application-opening-alert-1')
        ->and($delivery->payload['subscription_id'])->toBe($subscription->id)
        ->and($delivery->payload['recipient'])->not->toHaveKey('email')
        ->and(json_encode($delivery->payload))->not->toContain($unsubscribePlain, $prefillPlain, 'opening@example.test')
        ->and($rendered['production_html'])->toContain($unsubscribePlain, $prefillPlain)
        ->and($rendered['text'])->toContain($unsubscribePlain, $prefillPlain);
});

test('retention holds preserve addresses while unheld unverified records erase at the approved boundary', function (): void {
    seedE2Foundation();
    config(['application-opening-alerts.enabled' => true]);
    $institutionId = (int) Institution::current()->id;
    $old = now()->subDays(100);
    $pendingHeld = ApplicationOpeningSubscription::query()->create([
        'institution_id' => $institutionId,
        'email' => 'held-pending@example.test',
        'normalized_email' => 'held-pending@example.test',
        'state' => ApplicationOpeningSubscription::STATE_PENDING,
        'generation' => 1,
        'consented_at' => $old,
        'consent_version' => 'opening-alerts-v1',
        'expires_at' => now()->addYear(),
        'retention_hold' => true,
    ]);
    $pendingErase = ApplicationOpeningSubscription::query()->create([
        'institution_id' => $institutionId,
        'email' => 'erase-pending@example.test',
        'normalized_email' => 'erase-pending@example.test',
        'state' => ApplicationOpeningSubscription::STATE_PENDING,
        'generation' => 1,
        'consented_at' => $old,
        'consent_version' => 'opening-alerts-v1',
        'expires_at' => now()->addYear(),
    ]);
    $terminalHeld = ApplicationOpeningSubscription::query()->create([
        'institution_id' => $institutionId,
        'email' => 'held-terminal@example.test',
        'normalized_email' => 'held-terminal@example.test',
        'state' => ApplicationOpeningSubscription::STATE_CONSUMED,
        'generation' => 1,
        'consented_at' => $old,
        'consent_version' => 'opening-alerts-v1',
        'verified_at' => $old,
        'consumed_at' => $old,
        'expires_at' => now()->addYear(),
        'retention_hold' => true,
    ]);
    $pendingErase->forceFill(['created_at' => $old, 'updated_at' => $old])->save();
    $pendingHeld->forceFill(['created_at' => $old, 'updated_at' => $old])->save();
    $terminalHeld->forceFill(['created_at' => $old, 'updated_at' => $old])->save();

    app(PurgeApplicationOpeningAlertData::class)->handle();

    expect($pendingHeld->fresh()->email)->toBe('held-pending@example.test')
        ->and($terminalHeld->fresh()->email)->toBe('held-terminal@example.test')
        ->and($pendingErase->fresh()->email)->toBeNull()
        ->and($pendingErase->fresh()->normalized_email)->toBeNull()
        ->and($pendingErase->fresh()->address_erased_at)->not->toBeNull();
});
