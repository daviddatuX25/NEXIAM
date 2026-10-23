<?php

use App\Authorization\CapabilityCatalog;
use App\Models\Admission\ApplicationOpeningSubscription;
use App\Models\Foundation\Institution;
use App\Models\User;
use App\Notifications\NotificationDelivery;
use App\Notifications\Queries\NotificationOccurrenceQuery;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Permission;

beforeEach(function (): void {
    seedE2Foundation();
    config(['application-opening-alerts.enabled' => true]);
});

function openingSubscriberFixture(Institution $institution, string $email, string $state = 'pending'): ApplicationOpeningSubscription
{
    return ApplicationOpeningSubscription::query()->create([
        'institution_id' => $institution->id,
        'email' => $email,
        'normalized_email' => mb_strtolower($email),
        'state' => $state,
        'generation' => 1,
        'consented_at' => now(),
        'consent_version' => 'opening-alerts-v1',
        'verified_at' => $state === 'verified' ? now() : null,
        'expires_at' => now()->addYear(),
    ]);
}

test('signup addresses require Registrar setup scope rather than cycle management', function (): void {
    $institution = Institution::current();
    openingSubscriberFixture($institution, 'private@example.test');
    $guidance = User::query()->where('email', 'guidance.counselor@nexiam.test')->firstOrFail();
    $permission = Permission::findOrCreate(CapabilityCatalog::SETUP_INSTITUTION_MANAGE, 'web');
    $guidance->givePermissionTo($permission);

    $this->actingAs($guidance)->getJson(route('setup.application-opening-alerts.index'))
        ->assertForbidden()->assertDontSee('private@example.test');

    $registrar = User::query()->where('email', 'registrar.clerk@nexiam.test')->firstOrFail();
    $this->actingAs($registrar)->getJson(route('setup.application-opening-alerts.index'))
        ->assertForbidden()->assertDontSee('private@example.test');

    $registrar->givePermissionTo($permission);
    $this->actingAs($registrar)->getJson(route('setup.application-opening-alerts.index'))
        ->assertOk()->assertJsonPath('data.0.email', 'private@example.test');
    $registrar->revokePermissionTo($permission);
    $this->actingAs($registrar)->getJson(route('setup.application-opening-alerts.index'))
        ->assertForbidden()->assertDontSee('private@example.test');
});

test('staff signup search and pages never include another institution', function (): void {
    $institution = Institution::current();
    $foreign = Institution::query()->create(['code' => 'FOREIGN-OPENING', 'name' => 'Foreign institution']);
    openingSubscriberFixture($foreign, 'foreign@example.test', 'verified');
    for ($index = 1; $index <= 17; $index++) {
        openingSubscriberFixture($institution, sprintf('person%02d@example.test', $index), 'verified');
    }
    openingSubscriberFixture($institution, 'pending@example.test');
    $registrar = User::query()->where('email', 'registrar.clerk@nexiam.test')->firstOrFail();
    $registrar->givePermissionTo(Permission::findOrCreate(CapabilityCatalog::SETUP_INSTITUTION_MANAGE, 'web'));

    $this->actingAs($registrar)->getJson(route('setup.application-opening-alerts.index', ['status' => 'verified']))
        ->assertOk()->assertJsonCount(15, 'data')->assertJsonPath('total', 17)
        ->assertJsonPath('last_page', 2)->assertDontSee('foreign@example.test')->assertDontSee('pending@example.test');
    $this->getJson(route('setup.application-opening-alerts.index', ['status' => 'verified', 'page' => 2]))
        ->assertOk()->assertJsonCount(2, 'data')->assertDontSee('foreign@example.test');
    $this->getJson(route('setup.application-opening-alerts.index', ['search' => 'PERSON03']))
        ->assertOk()->assertJsonPath('total', 1)->assertJsonPath('data.0.email', 'person03@example.test');
    $this->getJson(route('setup.application-opening-alerts.index', ['status' => 'not-a-state']))
        ->assertUnprocessable();
});

test('signup rate limiting shares a privacy safe address bucket across casing and source IPs', function (): void {
    foreach (['192.0.2.1', '192.0.2.2', '192.0.2.3'] as $index => $ip) {
        $this->withServerVariables(['REMOTE_ADDR' => $ip])->postJson(route('apply.opening-alerts.store'), [
            'email' => $index === 1 ? 'RATE@example.test' : 'rate@example.test',
            'consent' => true,
        ])->assertStatus(200);
    }
    $this->withServerVariables(['REMOTE_ADDR' => '192.0.2.4'])->postJson(route('apply.opening-alerts.store'), [
        'email' => 'Rate@example.test', 'consent' => true,
    ])->assertTooManyRequests();
});

test('opening mail appears only in the scoped Registrar delivery desk and labels anonymous recipients correctly', function (): void {
    $institution = Institution::current();
    $subscription = openingSubscriberFixture($institution, 'private-desk@example.test', 'verified');
    $foreign = Institution::query()->create(['code' => 'FOREIGN-DESK', 'name' => 'Other institution']);
    $foreignSubscription = openingSubscriberFixture($foreign, 'foreign-desk@example.test', 'verified');
    foreach ([$subscription, $foreignSubscription] as $recipient) {
        NotificationDelivery::query()->create([
            'id' => (string) Str::uuid(),
            'event_id' => hash('sha256', 'opening-desk-fixture:'.$recipient->id),
            'event_type' => 'application.opening_alert.opened.v1',
            'event_version' => 1,
            'recipient_type' => ApplicationOpeningSubscription::class,
            'recipient_id' => $recipient->id,
            'channel' => 'mail',
            'template_key' => 'application.opening_alert.opened',
            'template_version' => 1,
            'payload' => ['snapshot_schema_version' => 'application-opening-alert-1', 'subscription_id' => $recipient->id],
            'status' => 'failed_terminal',
            'attempts' => 5,
        ]);
    }
    $registrar = User::query()->where('email', 'registrar.clerk@nexiam.test')->firstOrFail();
    $registrar->givePermissionTo(Permission::findOrCreate(CapabilityCatalog::SETUP_INSTITUTION_MANAGE, 'web'));
    $query = app(NotificationOccurrenceQuery::class);
    $rows = $query->activity($registrar, $query->accessibleDomains($registrar), []);
    expect($rows->total())->toBe(1);
    $occurrence = $query->occurrence($registrar, $rows->items()[0]['occurrence_id']);
    expect($occurrence['event_type'])->toBe('application.opening_alert.opened.v1');
    $deliveries = $query->deliveries($registrar, $occurrence)->items();
    expect($deliveries[0]['recipient_type'])->toBe('opening_alert_subscriber')
        ->and($deliveries[0]['recipient_label'])->toBe('Opening-alert subscriber')
        ->and($deliveries[0]['status'])->toBe('failed_terminal');

    $cycleOnly = User::query()->where('email', 'admin.lead@nexiam.test')->firstOrFail();
    expect($query->activity($cycleOnly, $query->accessibleDomains($cycleOnly), [])->total())->toBe(0)
        ->and($query->occurrence($cycleOnly, $rows->items()[0]['occurrence_id']))->toBeNull();
});
