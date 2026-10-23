<?php

declare(strict_types=1);

use App\Admission\Actions\AcceptAdmissionAttempt;
use App\Assessment\Actions\AcceptAssessmentRawResults;
use App\Authorization\CapabilityCatalog;
use App\Examination\Actions\AssignApplicantToExamSession;
use App\Examination\Actions\BindAssessmentAdministration;
use App\Examination\Actions\CreateExamSession;
use App\Foundation\Actions\RecordAuditEvent;
use App\Models\Admission\AdmissionAttempt;
use App\Models\Assessment\AssessmentRawResultRevision;
use App\Models\Assessment\AssessmentScoringRun;
use App\Models\Assessment\TestKitVersion;
use App\Models\Examination\AssessmentAdministration;
use App\Models\Examination\ExamAssignment;
use App\Models\Examination\ExamSession;
use App\Models\Foundation\AcademicCycle;
use App\Models\Foundation\Institution;
use App\Models\Foundation\Room;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

require_once __DIR__.'/../../Support/NormConversionFixtures.php';

const ASSESSMENT_BINDING_PG_DATABASE = 'nexiam_test_kit_acceptance_composition_20261015';

beforeEach(function (): void {
    if (DB::connection()->getDriverName() !== 'pgsql') {
        test()->markTestSkipped('Assessment binding transaction contracts require PostgreSQL.');
    }
    $database = (string) DB::scalar('SELECT current_database()');
    if (app()->environment() !== 'testing'
        || $database !== ASSESSMENT_BINDING_PG_DATABASE
        || config('database.default') !== 'pgsql'
        || config('database.connections.pgsql.database') !== ASSESSMENT_BINDING_PG_DATABASE
        || (string) config('database.connections.pgsql.url') !== '') {
        throw new RuntimeException('Assessment binding contracts require the exact isolated PostgreSQL acceptance database.');
    }

    DB::beginTransaction();
    $this->assessmentBindingPostgresTransaction = true;
    seedE2Foundation();
    $this->guidance = User::query()->where('email', 'guidance.counselor@nexiam.test')->firstOrFail();
    $capabilities = [
        CapabilityCatalog::GUIDANCE_ASSESSMENT_CATALOG,
        CapabilityCatalog::GUIDANCE_EXAM_SCHEDULE,
        CapabilityCatalog::GUIDANCE_SCORING_MANUAL,
    ];
    foreach ($capabilities as $capability) {
        Permission::findOrCreate($capability, 'web');
    }
    $this->guidance->givePermissionTo($capabilities);
});

afterEach(function (): void {
    if (! ($this->assessmentBindingPostgresTransaction ?? false)) {
        return;
    }
    while (DB::transactionLevel() > 0) {
        DB::rollBack();
    }
});

/** @return array{attempt:AdmissionAttempt,session:ExamSession,assignment:ExamAssignment,kit:TestKitVersion} */
function assessmentBindingPostgresSitting(TestCase $test, User $actor, string $label): array
{
    $institution = Institution::current();
    $cycle = AcademicCycle::query()->where('institution_id', $institution->id)->firstOrFail();
    $kit = createTestActiveKitVersionFor($test, $actor, $institution, $cycle, 'PG-BIND-'.Str::upper(Str::random(8)));
    $room = Room::query()->where('institution_id', $institution->id)->firstOrFail();
    $startsAt = now()->addDays(8)->setTime(9, 0);
    $session = app(CreateExamSession::class)->handle(
        $cycle,
        $room,
        'PG-BIND-SESSION-'.Str::upper(Str::random(8)),
        $startsAt->toDateTimeString(),
        $startsAt->copy()->addHours(2)->toDateTimeString(),
        $actor,
        ExamSession::SEATING_MODE_SIMPLE_CAPACITY,
        testKitVersionId: (int) $kit->id,
    );
    $submitted = submitSyntheticApplicant('pg-bind-'.Str::uuid().'@example.test', $label)['attempt'];
    $registrar = User::query()->where('email', 'registrar.clerk@nexiam.test')->firstOrFail();
    $attempt = app(AcceptAdmissionAttempt::class)->handle($submitted, $registrar, 1);
    $assignment = app(AssignApplicantToExamSession::class)->handle($session, $attempt, $actor);

    return compact('attempt', 'session', 'assignment', 'kit');
}

test('PostgreSQL exact Setup-only binding reuses identity, accepts manual raw input, and rejects a stale owner', function (): void {
    $fixture = assessmentBindingPostgresSitting($this, $this->guidance, 'exact-binding');
    $binder = app(BindAssessmentAdministration::class);
    $administration = $binder->handle($fixture['assignment'], $fixture['kit'], $this->guidance, 'manual');
    $reused = $binder->handle($fixture['assignment'], $fixture['kit'], $this->guidance, 'manual');

    expect($reused->id)->toBe($administration->id)
        ->and(AssessmentAdministration::query()->where('exam_assignment_id', $fixture['assignment']->id)->count())->toBe(1)
        ->and($administration->test_kit_version_id)->toBe($fixture['kit']->id)
        ->and($administration->test_instrument_version_id)->toBe($fixture['kit']->test_instrument_version_id)
        ->and($administration->manifest_sha256)->toBe($fixture['kit']->manifest_sha256)
        ->and($administration->acceptance_receipt_id)->toBeNull()
        ->and($administration->material_snapshot_id)->toBeNull()
        ->and($administration->exam_session_seat_id)->toBeNull();

    app(AcceptAssessmentRawResults::class)->handle(
        administration: $administration,
        metricValues: ['RAW_A' => 0],
        source: AssessmentScoringRun::SOURCE_MANUAL,
        actor: $this->guidance,
        idempotencyKey: 'pg-binding-zero-'.Str::uuid(),
    );
    expect(AssessmentRawResultRevision::query()
        ->where('administration_id', $administration->id)
        ->where('metric_code', 'RAW_A')
        ->where('is_current', true)
        ->value('raw_value'))->toBe('0.000000');

    $fixture['assignment']->forceFill([
        'status' => ExamAssignment::STATUS_REMOVED,
        'ended_at' => now(),
        'end_reason' => 'PostgreSQL stale-owner boundary fixture',
        'version' => (int) $fixture['assignment']->version + 1,
    ])->save();
    expect(fn () => app(AcceptAssessmentRawResults::class)->handle(
        administration: $administration,
        metricValues: ['RAW_A' => 1],
        source: AssessmentScoringRun::SOURCE_MANUAL,
        actor: $this->guidance,
        idempotencyKey: 'pg-binding-stale-owner-'.Str::uuid(),
    ))->toThrow(ValidationException::class);
    expect(AssessmentScoringRun::query()->where('administration_id', $administration->id)->count())->toBe(1);
});

test('PostgreSQL binding replacement rolls back supersession and insertion when audit fails', function (): void {
    $fixture = assessmentBindingPostgresSitting($this, $this->guidance, 'binding-audit-rollback');
    $binder = app(BindAssessmentAdministration::class);
    $original = $binder->handle($fixture['assignment'], $fixture['kit'], $this->guidance, 'manual');
    $replacementKit = createTestActiveKitVersionFor(
        $this,
        $this->guidance,
        Institution::current(),
        $fixture['session']->academicCycle,
        'PG-BIND-REPLACE-'.Str::upper(Str::random(8)),
    );
    $audit = Mockery::mock(RecordAuditEvent::class);
    $audit->shouldReceive('handle')->once()->andThrow(new RuntimeException('test audit failure'));
    app()->instance(RecordAuditEvent::class, $audit);

    expect(fn () => app(BindAssessmentAdministration::class)->handle(
        $fixture['assignment'],
        $replacementKit,
        $this->guidance,
        'manual',
        'Replace the unused exact Setup binding for the isolated rollback test.',
    ))->toThrow(RuntimeException::class, 'test audit failure');

    expect(AssessmentAdministration::query()->where('exam_assignment_id', $fixture['assignment']->id)->count())->toBe(1)
        ->and($original->fresh()->superseded_at)->toBeNull()
        ->and($original->fresh()->test_kit_version_id)->toBe($fixture['kit']->id);
});

test('PostgreSQL required raw audit failure rolls back the scoring run revisions and binding state', function (): void {
    $fixture = assessmentBindingPostgresSitting($this, $this->guidance, 'raw-audit-rollback');
    $administration = app(BindAssessmentAdministration::class)->handle($fixture['assignment'], $fixture['kit'], $this->guidance);
    $state = $administration->binding_state;
    $audit = Mockery::mock(RecordAuditEvent::class);
    $audit->shouldReceive('handle')->once()->andThrow(new RuntimeException('raw audit unavailable'));
    app()->instance(RecordAuditEvent::class, $audit);
    expect(fn () => app(AcceptAssessmentRawResults::class)->handle(
        administration: $administration,
        metricValues: ['RAW_A' => 1],
        source: AssessmentScoringRun::SOURCE_MANUAL,
        actor: $this->guidance,
        idempotencyKey: 'pg-raw-audit-'.Str::uuid(),
    ))->toThrow(RuntimeException::class, 'raw audit unavailable');
    expect(AssessmentScoringRun::query()->where('administration_id', $administration->id)->exists())->toBeFalse()
        ->and(AssessmentRawResultRevision::query()->where('administration_id', $administration->id)->exists())->toBeFalse()
        ->and($administration->fresh()->binding_state)->toBe($state);
});
