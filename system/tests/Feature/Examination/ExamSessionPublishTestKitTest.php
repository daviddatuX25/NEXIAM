<?php

use App\Authorization\CapabilityCatalog;
use App\Examination\Actions\PublishExamSession;
use App\Models\Examination\ExamSession;
use App\Models\Examination\ExamSessionLogisticsPlan;
use App\Models\Examination\ExamSessionMaterialSnapshot;
use App\Models\Foundation\Institution;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    seedE2Foundation();
    $this->actor = User::query()->where('email', 'guidance.counselor@nexiam.test')->firstOrFail();
    foreach ([
        CapabilityCatalog::GUIDANCE_ASSESSMENT_CATALOG,
        CapabilityCatalog::GUIDANCE_BOOKLET_MANAGE,
        CapabilityCatalog::GUIDANCE_BOOKLET_CONTENT,
        CapabilityCatalog::GUIDANCE_EXAM_SCHEDULE,
    ] as $capability) {
        Permission::findOrCreate($capability, 'web');
    }
    $this->actor->givePermissionTo([
        CapabilityCatalog::GUIDANCE_ASSESSMENT_CATALOG,
        CapabilityCatalog::GUIDANCE_BOOKLET_MANAGE,
        CapabilityCatalog::GUIDANCE_BOOKLET_CONTENT,
        CapabilityCatalog::GUIDANCE_EXAM_SCHEDULE,
    ]);
});

require_once __DIR__.'/../../Support/ExamSessionKitFixture.php';

test('publishes a simple-capacity session without kit, material snapshot, or physical seats', function (): void {
    $fixture = examSessionNoKitFixture($this->actor);
    $session = $fixture['session'];

    $published = app(PublishExamSession::class)->handle($session, $this->actor, $session->version);
    $plan = ExamSessionLogisticsPlan::query()->where('exam_session_id', $session->id)->where('is_current', true)->firstOrFail();

    expect($published->status)->toBe(ExamSession::STATUS_PUBLISHED)
        ->and($plan->seating_mode)->toBe(ExamSession::SEATING_MODE_SIMPLE_CAPACITY)
        ->and($plan->capacity_limit)->toBe((int) $session->room->capacity)
        ->and($plan->seating_arrangement_id)->toBeNull()
        ->and($plan->seats()->count())->toBe(0)
        ->and(ExamSessionMaterialSnapshot::query()->where('exam_session_id', $session->id)->exists())->toBeFalse();
});

test('publication does not require an accepted Test Kit or OMR receipt', function (string $scenario): void {
    $fixture = $scenario === 'missing'
        ? examSessionNoKitFixture($this->actor)
        : examSessionKitFixture($this->actor);
    $session = $fixture['session'];

    app(PublishExamSession::class)->handle($session, $this->actor, $session->version);

    expect(ExamSessionLogisticsPlan::query()->where('exam_session_id', $session->id)->where('is_current', true)->exists())->toBeTrue()
        ->and(ExamSessionMaterialSnapshot::query()->where('exam_session_id', $session->id)->exists())->toBeFalse();
})->with([
    'no kit selected' => ['missing'],
    'kit selected but not accepted' => ['unaccepted'],
]);

test('HTTP publication remains independent from a submitted Test Kit selector', function (): void {
    $fixture = examSessionNoKitFixture($this->actor);
    $otherMaterials = testKitAcceptanceFixture($this->actor, Institution::current());

    $this->actingAs($this->actor)
        ->post(route('exams.sessions.publish', $fixture['session']), [
            'expected_version' => $fixture['session']->version,
            'test_kit_version_id' => $otherMaterials['version']->id,
        ])
        ->assertSessionHasNoErrors();

    expect(ExamSessionLogisticsPlan::query()->where('exam_session_id', $fixture['session']->id)->where('is_current', true)->exists())->toBeTrue()
        ->and(ExamSessionMaterialSnapshot::query()->where('exam_session_id', $fixture['session']->id)->exists())->toBeFalse();
});

test('HTTP publication does not require acceptance for a stored Test Kit intent', function (): void {
    $fixture = examSessionKitFixture($this->actor);

    $this->actingAs($this->actor)
        ->post(route('exams.sessions.publish', $fixture['session']), [
            'expected_version' => $fixture['session']->version,
        ])
        ->assertSessionHasNoErrors();

    expect(ExamSessionLogisticsPlan::query()->where('exam_session_id', $fixture['session']->id)->where('is_current', true)->exists())->toBeTrue()
        ->and(ExamSessionMaterialSnapshot::query()->where('exam_session_id', $fixture['session']->id)->exists())->toBeFalse();
});

test('HTTP create persists the exact selected Test Kit version', function (): void {
    $fixture = examSessionKitFixture($this->actor);
    $startsAt = now()->addDays(20);

    $this->actingAs($this->actor)
        ->post(route('exams.sessions.store'), [
            'academic_cycle_id' => $fixture['session']->academic_cycle_id,
            'room_id' => $fixture['session']->room_id,
            'code' => 'HTTP-KIT-CREATE-'.substr((string) $fixture['kit_version']->id, -5),
            'starts_at' => $startsAt->toDateTimeString(),
            'effective_end_at' => $startsAt->copy()->addHours(2)->toDateTimeString(),
            'seating_mode' => ExamSession::SEATING_MODE_SIMPLE_CAPACITY,
            'test_kit_version_id' => $fixture['kit_version']->id,
        ])
        ->assertSessionHasNoErrors();

    $created = ExamSession::query()->where('code', 'HTTP-KIT-CREATE-'.substr((string) $fixture['kit_version']->id, -5))->firstOrFail();

    expect($created->test_kit_version_id)->toBe($fixture['kit_version']->id);
});

test('HTTP update persists the exact selected Test Kit version', function (): void {
    $fixture = examSessionKitFixture($this->actor);
    $session = $fixture['session'];

    $this->actingAs($this->actor)
        ->put(route('exams.sessions.update', $session), [
            'academic_cycle_id' => $session->academic_cycle_id,
            'room_id' => $session->room_id,
            'code' => $session->code,
            'starts_at' => $session->starts_at->toDateTimeString(),
            'effective_end_at' => $session->effective_end_at->toDateTimeString(),
            'expected_version' => $session->version,
            'seating_mode' => $session->seating_mode,
            'seating_arrangement_id' => null,
            'test_kit_version_id' => $fixture['kit_version']->id,
        ])
        ->assertSessionHasNoErrors();

    expect($session->fresh()->test_kit_version_id)->toBe($fixture['kit_version']->id);
});

test('HTTP update preserves the stored Test Kit choice when omitted', function (): void {
    $fixture = examSessionKitFixture($this->actor);
    $session = $fixture['session'];

    $this->actingAs($this->actor)
        ->put(route('exams.sessions.update', $session), [
            'academic_cycle_id' => $session->academic_cycle_id,
            'room_id' => $session->room_id,
            'code' => $session->code,
            'starts_at' => $session->starts_at->toDateTimeString(),
            'effective_end_at' => $session->effective_end_at->toDateTimeString(),
            'expected_version' => $session->version,
            'seating_mode' => $session->seating_mode,
            'seating_arrangement_id' => null,
        ])
        ->assertSessionHasNoErrors();

    expect($session->fresh()->test_kit_version_id)->toBe($fixture['kit_version']->id);
});
