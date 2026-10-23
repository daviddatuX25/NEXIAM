<?php

use App\Authorization\CapabilityCatalog;
use App\Models\Admission\AdmissionAttempt;
use App\Models\Examination\ExamSession;
use App\Models\Foundation\AcademicCycle;
use App\Models\Foundation\AuditEvent;
use App\Models\Foundation\Program;
use App\Models\Foundation\ProgramQuota;
use App\Models\Foundation\Room;
use App\Models\User;
use App\Notifications\NotificationDelivery;
use App\Testing\PracticeFixtureSet;
use App\Testing\PracticePreparationOperation;
use App\Testing\PracticeWorkspace;
use App\Testing\Queries\InstitutionalOmrBookletQuery;
use App\Testing\Queries\PracticePrerequisites;
use App\Testing\Seeders\Stages\BaseStageSeeder;
use Database\Seeders\ApplicantOfficeInquiryDemoSeeder;
use Database\Seeders\PublicIntakeConsentDemoSeeder;
use Inertia\Testing\AssertableInertia as Assert;

function preparationInput(string $action = 'prepare', ?PracticeFixtureSet $set = null): array
{
    return ['purpose' => 'demo', 'scenario' => 'application-review', 'action' => $action] + ($set ? ['set_id' => $set->id] : []);
}

function preparationOperator(): User
{
    seedE2Foundation();

    return User::query()->where('email', 'guidance.counselor@nexiam.test')->firstOrFail();
}

function preparationOwnedSet(User $operator): PracticeFixtureSet
{
    return PracticeFixtureSet::query()->create([
        'label' => 'Owned rehearsal', 'institution_id' => $operator->actorScope()->institutionId,
        'academic_cycle_id' => AcademicCycle::active()->id, 'scenario' => 'application-review',
        'purpose' => 'demo', 'status' => 'active', 'revision' => 1, 'created_by_user_id' => $operator->id,
        'actor_bindings' => ['guidance' => (int) $operator->id], 'material_snapshot' => [], 'credentials' => [],
    ]);
}

test('practice preparation requires authentication without exposing scenario controls', function (): void {
    $this->get('/demo/preparation?purpose=demo&scenario=application-review')->assertRedirect(route('login'));
});

test('ordinary actors and production cannot inspect or preview private practice data', function (): void {
    preparationOperator();
    $this->actingAs(User::factory()->create())->getJson('/demo/preparation')->assertForbidden();
    $this->postJson('/demo/preparation/preview', preparationInput())->assertForbidden();
    $this->app->detectEnvironment(static fn (): string => 'production');
    $this->getJson('/demo/preparation')->assertForbidden();
    $this->postJson('/demo/preparation/preview', preparationInput())->assertForbidden();
    $this->app->detectEnvironment(static fn (): string => 'testing');
    expect(PracticePreparationOperation::query()->count())->toBe(0);
});

test('index offers fixed scenarios and real prerequisites without creating domain data', function (): void {
    $operator = preparationOperator();
    $before = AdmissionAttempt::query()->count();
    $this->actingAs($operator)->getJson('/demo/preparation?purpose=demo&scenario=application-review')
        ->assertOk()->assertJsonCount(8, 'scenarios')->assertJsonPath('selected_set', null)
        ->assertJsonPath('endpoints.preview', '/demo/preparation/preview');
    $this->get('/demo/preparation?purpose=research&scenario=student')->assertOk()
        ->assertInertia(fn (Assert $page) => $page->component('Foundation/PracticePreparation')->has('scenarios', 4));
    expect(AdmissionAttempt::query()->count())->toBe($before)->and(PracticeFixtureSet::query()->count())->toBe(0);
});

test('preview is an expiring exact actor owned meta operation and cancellation writes no cases', function (): void {
    $operator = preparationOperator();
    $count = AdmissionAttempt::query()->count();
    $response = $this->actingAs($operator)->postJson('/demo/preparation/preview', preparationInput())->assertOk()
        ->assertJsonPath('can_confirm', true)->assertJsonStructure(['operation' => ['id', 'action', 'expires_at', 'confirm_url'], 'impact' => ['creates', 'replaces', 'preserves']]);
    $operation = PracticePreparationOperation::query()->findOrFail($response->json('operation.id'));
    expect($operation->actor_user_id)->toBe($operator->id)->and($operation->status)->toBe('preview')
        ->and($operation->expires_at->isFuture())->toBeTrue()->and(AdmissionAttempt::query()->count())->toBe($count)
        ->and(PracticeFixtureSet::query()->count())->toBe(0)->and($operation->result)->toBe([]);
});

test('unknown scenario command and extraneous exact action inputs are rejected', function (array $extra): void {
    $operator = preparationOperator();
    $this->actingAs($operator)->postJson('/demo/preparation/preview', array_replace(preparationInput(), $extra))->assertUnprocessable();
    expect(PracticePreparationOperation::query()->count())->toBe(0)->and(PracticeFixtureSet::query()->count())->toBe(0);
})->with([[['command' => 'migrate:fresh']], [['scenario' => '../setup']], [['action' => 'run']], [['preset' => 'live']], [['purpose' => 'production']]]);

test('confirmation prepares fresh natural cases while preserving institutional and unknown records', function (): void {
    $operator = preparationOperator();
    $unknown = submitSyntheticApplicant('cohort.revw01@nexiam.test', 'Santos')['attempt'];
    $unknownBefore = $unknown->getAttributes();
    $identityBefore = $unknown->identity->getAttributes();
    $configuration = [Program::query()->get()->toArray(), ProgramQuota::query()->get()->toArray(), Room::query()->get()->toArray()];
    $preview = $this->actingAs($operator)->postJson('/demo/preparation/preview', preparationInput())->assertOk();
    $confirmed = $this->postJson($preview->json('operation.confirm_url'), [])->assertOk();
    $set = PracticeFixtureSet::query()->findOrFail($confirmed->json('set_id'));
    $cases = AdmissionAttempt::query()->where('practice_fixture_set_id', $set->id)->get();
    expect($cases)->toHaveCount(3)->and($set->credentials)->toHaveCount(3)
        ->and($unknown->fresh()->getAttributes())->toBe($unknownBefore)->and($unknown->identity->fresh()->getAttributes())->toBe($identityBefore)
        ->and([Program::query()->get()->toArray(), ProgramQuota::query()->get()->toArray(), Room::query()->get()->toArray()])->toBe($configuration);
    foreach ($cases as $attempt) {
        expect($attempt->status)->toBe('pending')->and($attempt->identity->practice_fixture_set_id)->toBe($set->id)
            ->and($attempt->identity->last_name)->not->toBe('Reviewer');
    }
    $stored = PracticePreparationOperation::query()->findOrFail($preview->json('operation.id'));
    expect($stored->result)->not->toHaveKey('preparation');
    foreach ($set->credentials as $credentials) {
        expect(json_encode($stored->result))->not->toContain($credentials['access_key'])
            ->and(json_encode($stored->prerequisite_snapshot))->not->toContain($credentials['access_key']);
    }
});

test('replaying a committed operation returns its set with no duplicate cases or completion audit', function (): void {
    $operator = preparationOperator();
    $preview = $this->actingAs($operator)->postJson('/demo/preparation/preview', preparationInput())->assertOk();
    $first = $this->postJson($preview->json('operation.confirm_url'), [])->assertOk();
    $count = AdmissionAttempt::query()->count();
    $audits = AuditEvent::query()->where('event_type', 'practice.preparation_completed')->count();
    $this->postJson($preview->json('operation.confirm_url'), [])->assertOk()->assertJsonPath('set_id', $first->json('set_id'))->assertJsonPath('result', $first->json('result'));
    expect(AdmissionAttempt::query()->count())->toBe($count)->and(PracticeFixtureSet::query()->count())->toBe(1)
        ->and(AuditEvent::query()->where('event_type', 'practice.preparation_completed')->count())->toBe($audits);
});

test('foreign operation or foreign set cannot be inspected or confirmed', function (): void {
    $operator = preparationOperator();
    $preview = $this->actingAs($operator)->postJson('/demo/preparation/preview', preparationInput())->assertOk();
    $other = User::query()->where('email', 'registrar.clerk@nexiam.test')->firstOrFail();
    $other->givePermissionTo(CapabilityCatalog::DEMO_STAGE_CONTROL);
    $this->actingAs($other)->postJson($preview->json('operation.confirm_url'), [])->assertNotFound();
    $foreign = preparationOwnedSet($operator);
    $this->getJson('/demo/preparation?purpose=demo&scenario=application-review&set_id='.$foreign->id)->assertNotFound();
    $this->postJson('/demo/preparation/preview', preparationInput('retire', $foreign))->assertNotFound();
    expect($foreign->fresh()->status)->toBe('active');
});

test('expired revoked and changed material previews fail without domain writes', function (string $change): void {
    $operator = preparationOperator();
    $count = AdmissionAttempt::query()->count();
    $preview = $this->actingAs($operator)->postJson('/demo/preparation/preview', preparationInput())->assertOk();
    $operation = PracticePreparationOperation::query()->findOrFail($preview->json('operation.id'));
    if ($change === 'expired') {
        $operation->update(['expires_at' => now()->subSecond()]);
    } elseif ($change === 'material') {
        Program::query()->firstOrFail()->update(['name' => 'Revised institutional title']);
    } else {
        $operator->roles()->where('name', 'guidance_counselor')->firstOrFail()->revokePermissionTo(CapabilityCatalog::DEMO_STAGE_CONTROL);
    }
    $this->postJson($preview->json('operation.confirm_url'), [])->assertStatus($change === 'revoked' ? 403 : 409);
    expect(AdmissionAttempt::query()->count())->toBe($count)->and(PracticeFixtureSet::query()->count())->toBe(0);
})->with(['expired', 'material', 'revoked']);

test('missing exam material blocks dependent confirmation but not application review', function (): void {
    $operator = preparationOperator();
    $count = AdmissionAttempt::query()->count();
    $this->actingAs($operator)->postJson('/demo/preparation/preview', preparationInput())->assertOk()->assertJsonPath('can_confirm', true);
    $preview = $this->postJson('/demo/preparation/preview', ['purpose' => 'demo', 'scenario' => 'exam-scheduling-attendance', 'action' => 'prepare'])->assertOk()->assertJsonPath('can_confirm', false);
    $this->postJson($preview->json('operation.confirm_url'), [])->assertUnprocessable();
    expect(AdmissionAttempt::query()->count())->toBe($count)->and(PracticeFixtureSet::query()->count())->toBe(0);
});

test('replacement retires retained historical cases and creates a fresh successor', function (): void {
    $operator = preparationOperator();
    $preview = $this->actingAs($operator)->postJson('/demo/preparation/preview', preparationInput())->assertOk();
    $first = $this->postJson($preview->json('operation.confirm_url'), [])->assertOk();
    $old = PracticeFixtureSet::query()->findOrFail($first->json('set_id'));
    $attempt = AdmissionAttempt::query()->where('practice_fixture_set_id', $old->id)->firstOrFail();
    $attempt->update(['status' => 'dismissed']);
    $replace = $this->postJson('/demo/preparation/preview', preparationInput('replace', $old))->assertOk()->assertJsonPath('can_confirm', true);
    $new = $this->postJson($replace->json('operation.confirm_url'), [])->assertOk();
    $successor = PracticeFixtureSet::query()->findOrFail($new->json('set_id'));
    expect($old->fresh()->status)->toBe('retired')->and($old->fresh()->retired_at)->not->toBeNull()
        ->and($successor->predecessor_set_id)->toBe($old->id)->and($attempt->fresh()->status)->toBe('dismissed')
        ->and(AdmissionAttempt::query()->where('practice_fixture_set_id', $old->id)->count())->toBe(3);
});

test('retirement retains history and stale case or revision changes conflict', function (string $change): void {
    $operator = preparationOperator();
    $preview = $this->actingAs($operator)->postJson('/demo/preparation/preview', preparationInput())->assertOk();
    $confirmed = $this->postJson($preview->json('operation.confirm_url'), [])->assertOk();
    $set = PracticeFixtureSet::query()->findOrFail($confirmed->json('set_id'));
    $retire = $this->postJson('/demo/preparation/preview', preparationInput('retire', $set))->assertOk();
    if ($change === 'case') {
        AdmissionAttempt::query()->where('practice_fixture_set_id', $set->id)->firstOrFail()->update(['status' => 'dismissed']);
    } else {
        $set->increment('revision');
    }
    $this->postJson($retire->json('operation.confirm_url'), [])->assertConflict();
    expect($set->fresh()->status)->toBe('active')->and(AdmissionAttempt::query()->where('practice_fixture_set_id', $set->id)->count())->toBe(3);
})->with(['case', 'revision']);

test('timing and reminder need distinct exact operations and never touch unknown sessions', function (): void {
    $operator = preparationOperator();
    $set = preparationOwnedSet($operator);
    $room = Room::query()->where('institution_id', $set->institution_id)->active()->examRooms()->firstOrFail();
    $session = app(PracticeWorkspace::class)->run($set, fn () => ExamSession::factory()->create(['academic_cycle_id' => $set->academic_cycle_id, 'room_id' => $room->id, 'status' => 'published', 'starts_at' => now()->addDays(5), 'effective_end_at' => now()->addDays(5)->addHours(2)]));
    $unknown = ExamSession::factory()->create(['academic_cycle_id' => $set->academic_cycle_id, 'room_id' => $room->id, 'starts_at' => now()->addDays(7), 'effective_end_at' => now()->addDays(7)->addHours(2)]);
    $before = $unknown->fresh()->getAttributes();
    $deliveryCount = NotificationDelivery::query()->count();
    $this->actingAs($operator)->postJson('/demo/preparation/preview', preparationInput('time', $set) + ['session_id' => $unknown->id, 'preset' => '1h-reminder'])->assertOk()->assertJsonPath('can_confirm', false);
    $preview = $this->postJson('/demo/preparation/preview', preparationInput('time', $set) + ['session_id' => $session->id, 'preset' => '1h-reminder'])->assertOk()->assertJsonPath('can_confirm', true);
    $this->postJson($preview->json('operation.confirm_url'), [])->assertOk();
    expect($session->fresh()->starts_at->between(now()->addMinutes(59), now()->addMinutes(61)))->toBeTrue()
        ->and($session->fresh()->status)->toBe('published')->and($unknown->fresh()->getAttributes())->toBe($before)
        ->and(NotificationDelivery::query()->count())->toBe($deliveryCount);
});

test('confirm accepts only the sealed operation identity and cannot change its action', function (): void {
    $operator = preparationOperator();
    $preview = $this->actingAs($operator)->postJson('/demo/preparation/preview', preparationInput())->assertOk();
    $this->postJson($preview->json('operation.confirm_url'), ['action' => 'retire'])->assertUnprocessable();
    expect(PracticeFixtureSet::query()->count())->toBe(0);
});

test('multiple eligible booklets remain preserved and an exact bounded selection is frozen for preparation', function (): void {
    $operator = preparationOperator();
    seedE2ApprovedOmrMaterialForPipelineTests();
    $first = app(InstitutionalOmrBookletQuery::class)->eligible()->firstOrFail();
    $second = $first->replicate();
    $second->version = (int) $first->version + 1;
    $second->save();
    $input = ['purpose' => 'demo', 'scenario' => 'exam-scheduling-attendance', 'action' => 'prepare'];
    $ambiguous = $this->actingAs($operator)->postJson('/demo/preparation/preview', $input)->assertOk();
    expect(collect($ambiguous->json('prerequisites'))->firstWhere('key', 'exam-material')['status'])->toBe('missing');
    $selected = $this->postJson('/demo/preparation/preview', $input + ['booklet_version_id' => $second->id])->assertOk();
    expect(collect($selected->json('prerequisites'))->firstWhere('key', 'exam-material')['status'])->toBe('available');
    $operation = PracticePreparationOperation::query()->findOrFail($selected->json('operation.id'));
    expect($operation->parameters['booklet_version_id'])->toBe($second->id)
        ->and($operation->prerequisite_snapshot['material_snapshot']['booklet_version_id'])->toBe($second->id)
        ->and($first->fresh()->status)->toBe('active')->and($second->fresh()->status)->toBe('active');
    $this->getJson('/demo/preparation?purpose=demo&scenario=exam-scheduling-attendance&booklet_version_id='.$second->id)
        ->assertOk()->assertJsonCount(2, 'materials.booklets')->assertJsonPath('materials.selected_booklet_version_id', $second->id);
    $this->postJson('/demo/preparation/preview', $input + ['booklet_version_id' => 999999])->assertUnprocessable();
    expect(PracticeFixtureSet::query()->count())->toBe(0);
});

test('booklet override cannot change an existing set through retirement timing reminders or pairing', function (string $action): void {
    $operator = preparationOperator();
    $set = preparationOwnedSet($operator);
    $this->actingAs($operator)->postJson('/demo/preparation/preview', preparationInput($action, $set) + ['booklet_version_id' => 1])->assertUnprocessable();
    expect($set->fresh()->material_snapshot)->toBe([])->and(PracticePreparationOperation::query()->count())->toBe(0);
})->with(['retire', 'time', 'reminder', 'pair']);

test('student preparation selects registered currently permitted office counterparts without repairing earlier accounts', function (): void {
    $operator = preparationOperator();
    (new ApplicantOfficeInquiryDemoSeeder)->run();
    $registeredRegistrar = User::query()->where('email', ApplicantOfficeInquiryDemoSeeder::REGISTRAR_EMAIL)->firstOrFail();
    $registeredGuidance = User::query()->where('email', ApplicantOfficeInquiryDemoSeeder::GUIDANCE_EMAIL)->firstOrFail();
    $registeredGuidance->givePermissionTo(CapabilityCatalog::GUIDANCE_EXAM_SCHEDULE, CapabilityCatalog::GUIDANCE_SCORING_MANUAL, CapabilityCatalog::GUIDANCE_RESULTS_PREPARE);
    $earlierRegistrar = User::query()->where('email', 'registrar.clerk@nexiam.test')->firstOrFail();
    $snapshot = app(PracticePrerequisites::class)->snapshot($operator, ['purpose' => 'research', 'scenario' => 'student', 'action' => 'prepare']);
    expect(collect($snapshot['prerequisites'])->firstWhere('key', 'privacy')['status'])->toBe('missing')
        ->and($snapshot['materials'])->not->toHaveKey('public_policy_id');
    (new PublicIntakeConsentDemoSeeder)->run();
    $beforePermissions = $earlierRegistrar->fresh()->getAllPermissions()->pluck('name')->sort()->values()->all();
    $snapshot = app(PracticePrerequisites::class)->snapshot($operator, ['purpose' => 'research', 'scenario' => 'student', 'action' => 'prepare']);

    expect(collect($snapshot['prerequisites'])->firstWhere('key', 'privacy')['status'])->toBe('available')
        ->and($snapshot['materials']['public_policy_id'])->not->toBeNull()
        ->and($snapshot['materials']['public_policy_revision_id'])->not->toBeNull()
        ->and(preg_match('/\A[a-f0-9]{64}\z/', $snapshot['materials']['public_policy_revision_digest']))->toBe(1)
        ->and($snapshot['actor_bindings']['registrar'])->toBe($registeredRegistrar->id)
        ->and($snapshot['actor_bindings']['guidance'])->toBe($registeredGuidance->id)
        ->and($earlierRegistrar->fresh()->getAllPermissions()->pluck('name')->sort()->values()->all())->toBe($beforePermissions);
    $registeredRegistrar->revokePermissionTo(CapabilityCatalog::OFFICE_INQUIRY_HANDLE);
    $after = app(PracticePrerequisites::class)->snapshot($operator, ['purpose' => 'research', 'scenario' => 'student', 'action' => 'prepare']);
    expect($after['actor_bindings'])->not->toHaveKey('registrar')
        ->and($registeredRegistrar->fresh()->hasPermissionTo(CapabilityCatalog::OFFICE_INQUIRY_HANDLE))->toBeFalse();
});

test('practice scoring refuses a missing explicit Kit before creating scoring state', function (): void {
    $producer = new class extends BaseStageSeeder
    {
        public function seed(): array
        {
            $this->preflightScores();

            return [];
        }
    };
    $set = new PracticeFixtureSet(['material_snapshot' => []]);
    expect(fn () => $producer->forSet($set)->seed())->toThrow(
        RuntimeException::class,
        'Select an explicit locked Test Kit',
    );
});
