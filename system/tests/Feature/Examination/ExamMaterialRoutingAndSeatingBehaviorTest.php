<?php

use App\Admission\Actions\AcceptAdmissionAttempt;
use App\Authorization\CapabilityCatalog;
use App\Examination\Actions\AssignApplicantToExamSession;
use App\Examination\Actions\CreateExamSession;
use App\Examination\Actions\PublishExamSession;
use App\Examination\Actions\RecordExamSubmission;
use App\Examination\Actions\RelocateExamAssignmentSeat;
use App\Examination\Actions\StartExamSession;
use App\Examination\Actions\UnpublishExamSession;
use App\Models\Admission\AdmissionAttempt;
use App\Models\Examination\ExamAssignment;
use App\Models\Examination\ExamAssignmentLogisticsSeatAllocation;
use App\Models\Examination\ExamAssignmentSeatAllocation;
use App\Models\Examination\ExamAttendanceRecord;
use App\Models\Examination\ExamSession;
use App\Models\Examination\ExamSessionLogisticsPlan;
use App\Models\Examination\ExamSessionLogisticsSeat;
use App\Models\Examination\ExamSessionMaterialSnapshot;
use App\Models\Examination\ExamSubmissionRecord;
use App\Models\Foundation\AcademicCycle;
use App\Models\Foundation\Institution;
use App\Models\Foundation\Room;
use App\Models\Foundation\SeatingArrangement;
use App\Models\StaffProfile;
use App\Models\User;
use App\Setup\Actions\UpdateInstitutionExamFeaturePolicy;
use Illuminate\Foundation\Testing\RefreshDatabase;
use RuntimeException;
use Spatie\Permission\Models\Permission;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    seedE2Foundation();

    $this->actor = User::query()->where('email', 'guidance.counselor@nexiam.test')->firstOrFail();
    $capabilities = [
        CapabilityCatalog::GUIDANCE_ASSESSMENT_CATALOG,
        CapabilityCatalog::GUIDANCE_BOOKLET_MANAGE,
        CapabilityCatalog::GUIDANCE_BOOKLET_CONTENT,
        CapabilityCatalog::GUIDANCE_EXAM_SCHEDULE,
    ];

    foreach ($capabilities as $capability) {
        Permission::findOrCreate($capability, 'web');
    }

    $this->actor->givePermissionTo($capabilities);
});

function emrFeaturePolicyActor(): User
{
    Permission::findOrCreate(CapabilityCatalog::SETUP_INSTITUTION_MANAGE, 'web');
    $actor = User::factory()->create();
    $actor->staffProfile()->create([
        'institution_id' => Institution::current()->id,
        'office' => 'registrar',
        'active_cycle_code' => AcademicCycle::query()->where('code', 'SYN-CYCLE-2026')->value('code'),
        'status' => StaffProfile::STATUS_ACTIVE,
    ]);
    $actor->givePermissionTo(CapabilityCatalog::SETUP_INSTITUTION_MANAGE);

    return $actor;
}

function emrEnableSeatingPolicy(): void
{
    $actor = emrFeaturePolicyActor();
    $institution = Institution::current();
    app(UpdateInstitutionExamFeaturePolicy::class)->updateSeatingAvailability(
        $actor,
        true,
        $institution->examFeaturePolicyVersion(),
    );
}

function emrBehaviorLogisticsSession(
    User $actor,
    string $code,
    string $pattern = SeatingArrangement::PATTERN_UNIFORM,
    int $rows = 2,
    int $columns = 2,
): array {
    emrEnableSeatingPolicy();
    $cycle = AcademicCycle::query()->where('code', 'SYN-CYCLE-2026')->firstOrFail();
    $room = Room::query()->where('code', 'RM-101')->firstOrFail();
    $arrangement = SeatingArrangement::factory()->create([
        'name' => $code.' logistics arrangement',
        'normalized_name' => mb_strtolower($code).' logistics arrangement',
        'rows' => $rows,
        'columns' => $columns,
        'booklet_pattern' => $pattern,
        'availability_mode' => SeatingArrangement::AVAILABILITY_ALL_ROOMS,
    ]);
    $session = app(CreateExamSession::class)->handle(
        $cycle,
        $room,
        $code,
        now()->addDay()->toDateTimeString(),
        now()->addDay()->addHours(2)->toDateTimeString(),
        $actor,
        ExamSession::SEATING_MODE_ASSIGNED_SEATING,
        null,
        $arrangement,
    );

    return compact('session', 'arrangement', 'cycle', 'room');
}

function emrBehaviorAcceptedAttempt(User $actor, string $email, string $lastName): AdmissionAttempt
{
    $result = submitSyntheticApplicant($email, $lastName);

    return app(AcceptAdmissionAttempt::class)->handle($result['attempt'], $actor, 1);
}

function emrBehaviorAssign(ExamSession $session, AdmissionAttempt $attempt, User $actor): ExamAssignment
{
    return app(AssignApplicantToExamSession::class)->handle($session, $attempt, $actor);
}

test('publishes advanced seating from exact logistics identity without paper or Set allocation', function (): void {
    $context = emrBehaviorLogisticsSession($this->actor, 'EMR-UNIFORM-A', SeatingArrangement::PATTERN_UNIFORM);
    $attempt = emrBehaviorAcceptedAttempt($this->actor, 'emr-uniform@example.test', 'Uniform');
    $assignment = emrBehaviorAssign($context['session'], $attempt, $this->actor);

    $published = app(PublishExamSession::class)->handle($context['session'], $this->actor, $context['session']->version);
    $plan = ExamSessionLogisticsPlan::query()->where('exam_session_id', $published->id)->where('is_current', true)->firstOrFail();
    $seats = ExamSessionLogisticsSeat::query()->where('logistics_plan_id', $plan->id)->orderBy('ordinal')->get();
    $allocation = ExamAssignmentLogisticsSeatAllocation::query()
        ->where('exam_assignment_id', $assignment->id)
        ->whereNull('effective_to')
        ->firstOrFail();

    expect($published->status)->toBe(ExamSession::STATUS_PUBLISHED)
        ->and($plan->seating_mode)->toBe(ExamSession::SEATING_MODE_ASSIGNED_SEATING)
        ->and($plan->seating_arrangement_id)->toBe($context['arrangement']->id)
        ->and($plan->arrangement_lock_version)->toBe($context['arrangement']->lock_version)
        ->and($seats)->toHaveCount(4)
        ->and($seats->first()->seat_label)->toBe('A1')
        ->and($allocation->logistics_plan_id)->toBe($plan->id)
        ->and($allocation->logistics_seat_id)->toBe($seats->first()->id)
        ->and($assignment->fresh()->booklet_set_id)->toBeNull()
        ->and(ExamSessionMaterialSnapshot::query()->where('exam_session_id', $published->id)->exists())->toBeFalse();
});

test('freezes an exact Set-free logistics manifest and allocates canonical roster order', function (): void {
    $context = emrBehaviorLogisticsSession($this->actor, 'EMR-MANIFEST', SeatingArrangement::PATTERN_UNIFORM);
    $firstAttempt = emrBehaviorAcceptedAttempt($this->actor, 'emr-roster-first@example.test', 'First');
    $secondAttempt = emrBehaviorAcceptedAttempt($this->actor, 'emr-roster-second@example.test', 'Second');

    $secondAssignment = emrBehaviorAssign($context['session'], $secondAttempt, $this->actor);
    $firstAssignment = emrBehaviorAssign($context['session'], $firstAttempt, $this->actor);
    $published = app(PublishExamSession::class)->handle($context['session'], $this->actor, $context['session']->version);
    $plan = ExamSessionLogisticsPlan::query()->where('exam_session_id', $published->id)->where('is_current', true)->firstOrFail();
    $seats = ExamSessionLogisticsSeat::query()->where('logistics_plan_id', $plan->id)->orderBy('ordinal')->get();
    $manifest = $seats->map(fn (ExamSessionLogisticsSeat $seat): array => [
        'ordinal' => (int) $seat->ordinal,
        'row_number' => (int) $seat->row_number,
        'column_number' => (int) $seat->column_number,
        'seat_label' => $seat->seat_label,
    ])->all();
    $allocations = ExamAssignmentLogisticsSeatAllocation::query()
        ->with(['assignment', 'seat'])
        ->whereIn('exam_assignment_id', [$firstAssignment->id, $secondAssignment->id])
        ->whereNull('effective_to')
        ->get()
        ->sortBy(fn (ExamAssignmentLogisticsSeatAllocation $allocation): int => (int) $allocation->assignment->admission_attempt_id)
        ->values()
        ->map(fn (ExamAssignmentLogisticsSeatAllocation $allocation): array => [
            'attempt_id' => (int) $allocation->assignment->admission_attempt_id,
            'seat_ordinal' => (int) $allocation->seat->ordinal,
        ])->all();

    expect($manifest)->toBe([
        ['ordinal' => 1, 'row_number' => 1, 'column_number' => 1, 'seat_label' => 'A1'],
        ['ordinal' => 2, 'row_number' => 1, 'column_number' => 2, 'seat_label' => 'A2'],
        ['ordinal' => 3, 'row_number' => 2, 'column_number' => 1, 'seat_label' => 'B1'],
        ['ordinal' => 4, 'row_number' => 2, 'column_number' => 2, 'seat_label' => 'B2'],
    ])
        ->and($plan->seat_manifest_hash)->toMatch('/^[a-f0-9]{64}$/')
        ->and($plan->arrangement_rows)->toBe(2)
        ->and($plan->arrangement_columns)->toBe(2)
        ->and($plan->arrangement_booklet_pattern)->toBe(SeatingArrangement::PATTERN_UNIFORM)
        ->and($allocations)->toBe([
            ['attempt_id' => $firstAttempt->id, 'seat_ordinal' => 1],
            ['attempt_id' => $secondAttempt->id, 'seat_ordinal' => 2],
        ])
        ->and($firstAssignment->fresh()->booklet_set_id)->toBeNull()
        ->and($secondAssignment->fresh()->booklet_set_id)->toBeNull()
        ->and(ExamSessionMaterialSnapshot::query()->where('exam_session_id', $published->id)->exists())->toBeFalse();
});

test('editing an arrangement after publication leaves the frozen logistics plan and seats unchanged', function (): void {
    $context = emrBehaviorLogisticsSession($this->actor, 'EMR-IMMUTABLE');
    $attempt = emrBehaviorAcceptedAttempt($this->actor, 'emr-immutable@example.test', 'Immutable');
    emrBehaviorAssign($context['session'], $attempt, $this->actor);
    $published = app(PublishExamSession::class)->handle($context['session'], $this->actor, $context['session']->version);
    $plan = ExamSessionLogisticsPlan::query()->where('exam_session_id', $published->id)->where('is_current', true)->firstOrFail();
    $beforeSeats = $plan->seats()->get()->map(fn (ExamSessionLogisticsSeat $seat): array => [
        'ordinal' => (int) $seat->ordinal,
        'row' => (int) $seat->row_number,
        'column' => (int) $seat->column_number,
        'label' => $seat->seat_label,
    ])->all();
    $manifestHash = $plan->seat_manifest_hash;

    $context['arrangement']->update([
        'rows' => 1,
        'columns' => 1,
        'booklet_pattern' => SeatingArrangement::PATTERN_UNIFORM,
        'lock_version' => 2,
    ]);

    $unchanged = $plan->fresh();
    $afterSeats = $unchanged->seats()->get()->map(fn (ExamSessionLogisticsSeat $seat): array => [
        'ordinal' => (int) $seat->ordinal,
        'row' => (int) $seat->row_number,
        'column' => (int) $seat->column_number,
        'label' => $seat->seat_label,
    ])->all();

    expect($unchanged->arrangement_rows)->toBe(2)
        ->and($unchanged->arrangement_columns)->toBe(2)
        ->and($unchanged->arrangement_booklet_pattern)->toBe(SeatingArrangement::PATTERN_UNIFORM)
        ->and($unchanged->arrangement_lock_version)->toBe(1)
        ->and($unchanged->seat_manifest_hash)->toBe($manifestHash)
        ->and($afterSeats)->toBe($beforeSeats);
});

test('unpublish and republish preserve the original historical material pins while revising logistics only', function (): void {
    $fixture = buildPaperGradingFixture($this, applicantCount: 1, close: false);
    $session = $fixture['session'];
    $assignment = $session->assignments()->firstOrFail();
    $historicalSnapshot = $fixture['historicalSnapshot'];
    $historicalAllocation = ExamAssignmentSeatAllocation::query()
        ->where('exam_assignment_id', $assignment->id)
        ->whereNull('effective_to')
        ->firstOrFail();
    $initialPlan = ExamSessionLogisticsPlan::query()
        ->where('exam_session_id', $session->id)
        ->where('is_current', true)
        ->firstOrFail();

    $unpublished = app(UnpublishExamSession::class)->handle(
        $session,
        $this->actor,
        $session->version,
        'Correct the room arrangement before release',
    );
    $republished = app(PublishExamSession::class)->handle($unpublished, $this->actor, $unpublished->version);
    $plans = ExamSessionLogisticsPlan::query()->where('exam_session_id', $session->id)->orderBy('revision')->get();

    expect($republished->status)->toBe(ExamSession::STATUS_PUBLISHED)
        ->and($plans)->toHaveCount(2)
        ->and($plans->first()->id)->toBe($initialPlan->id)
        ->and($plans->first()->is_current)->toBeFalse()
        ->and($plans->last()->is_current)->toBeTrue()
        ->and(ExamSessionMaterialSnapshot::query()->where('exam_session_id', $session->id)->count())->toBe(1)
        ->and($historicalSnapshot->fresh()->is_current)->toBeTrue()
        ->and($historicalSnapshot->fresh()->booklet_version_id)->toBe($fixture['booklet']->id)
        ->and($historicalSnapshot->fresh()->test_kit_version_id)->toBe($fixture['kitVersion']->id)
        ->and($historicalAllocation->fresh()->exam_session_seat_id)->toBe($fixture['historicalSeatsByAssignment'][$assignment->id]->id)
        ->and($historicalAllocation->fresh()->effective_to)->toBeNull();
});

test('unpublish is blocked when attendance evidence exists', function (): void {
    $fixture = buildPaperGradingFixture($this, applicantCount: 1, close: false);
    $session = $fixture['session'];
    $assignment = $session->assignments()->firstOrFail();
    $snapshot = $fixture['historicalSnapshot'];

    ExamAttendanceRecord::query()->create([
        'exam_assignment_id' => $assignment->id,
        'recorded_by_user_id' => $this->actor->id,
        'outcome' => ExamAttendanceRecord::OUTCOME_PRESENT,
        'idempotency_key' => 'emr-attendance-evidence',
        'recorded_at' => now(),
    ]);

    expect(fn () => app(UnpublishExamSession::class)->handle(
        $session->fresh(),
        $this->actor,
        $session->version,
        'Attempt to unpublish with attendance evidence',
    ))->toThrow(RuntimeException::class, 'attendance, submission, or capture evidence');

    expect($session->fresh()->status)->toBe(ExamSession::STATUS_PUBLISHED)
        ->and($snapshot->fresh()->is_current)->toBeTrue();
});

test('unpublish is blocked when submission evidence exists', function (): void {
    $fixture = buildPaperGradingFixture($this, applicantCount: 1, close: false);
    $session = $fixture['session'];
    $assignment = $session->assignments()->firstOrFail();
    $session = app(StartExamSession::class)->handle($session, $this->actor, $session->version);
    app(RecordExamSubmission::class)->handle($assignment, $this->actor, 'emr-submission-evidence');

    expect(fn () => app(UnpublishExamSession::class)->handle(
        $session->fresh(),
        $this->actor,
        $session->version,
        'Attempt to unpublish with submission evidence',
    ))->toThrow(RuntimeException::class, 'attendance, submission, or capture evidence');

    expect($session->fresh()->status)->toBe(ExamSession::STATUS_PUBLISHED)
        ->and(ExamSessionMaterialSnapshot::query()
            ->where('exam_session_id', $session->id)
            ->where('is_current', true)
            ->count())->toBe(1);
});

test('historical seat relocation appends allocation history, preserves Set pins, and rejects an occupied target', function (): void {
    $fixture = buildPaperGradingFixture($this, applicantCount: 3, close: false);
    $assignments = $fixture['session']->assignments()->orderBy('id')->get();
    $firstAssignment = $assignments[0];
    $secondAssignment = $assignments[1];
    $thirdAssignment = $assignments[2];
    $snapshot = $fixture['historicalSnapshot'];
    $seats = $snapshot->seats()->with('bookletSet')->orderBy('ordinal')->get();
    $thirdAllocation = ExamAssignmentSeatAllocation::query()
        ->where('exam_assignment_id', $thirdAssignment->id)
        ->whereNull('effective_to')
        ->firstOrFail();
    $thirdAllocation->update(['effective_to' => now()->addSecond()]);

    expect(fn () => app(RelocateExamAssignmentSeat::class)->handle(
        $firstAssignment,
        $seats[1],
        $this->actor,
        'Try occupied target',
    ))->toThrow(RuntimeException::class, 'already occupied');

    $replacement = app(RelocateExamAssignmentSeat::class)->handle(
        $firstAssignment,
        $seats[2],
        $this->actor,
        'Applicant requested a different desk',
    );
    $history = ExamAssignmentSeatAllocation::query()
        ->where('exam_assignment_id', $firstAssignment->id)
        ->orderBy('id')
        ->get();
    $activeSecond = ExamAssignmentSeatAllocation::query()
        ->where('exam_assignment_id', $secondAssignment->id)
        ->whereNull('effective_to')
        ->firstOrFail();

    expect($history)->toHaveCount(2)
        ->and($history->first()->effective_to)->not->toBeNull()
        ->and($replacement->reason)->toBe('Applicant requested a different desk')
        ->and($replacement->assigned_by_user_id)->toBe($this->actor->id)
        ->and($replacement->sessionSeat->is($seats[2]))->toBeTrue()
        ->and($replacement->sessionSeat->bookletSet->set_code)->toBe('A')
        ->and($activeSecond->sessionSeat->is($seats[1]))->toBeTrue()
        ->and($firstAssignment->fresh()->booklet_set_id)->toBeNull()
        ->and($thirdAllocation->fresh()->sessionSeat->materialSnapshot->is($snapshot))->toBeTrue();
});

test('simple-capacity submission does not capture historical material seat while relocation preserves its pins', function (): void {
    $fixture = buildPaperGradingFixture($this, applicantCount: 2, close: false);
    $session = $fixture['session'];
    $session->forceFill([
        'starts_at' => now()->addHour(),
        'effective_end_at' => now()->addHours(3),
    ])->save();
    $assignments = $session->assignments()->orderBy('id')->get();
    $assignment = $assignments[0];
    $otherAssignment = $assignments[1];
    $snapshot = $fixture['historicalSnapshot'];
    $seats = $snapshot->seats()->with('bookletSet')->orderBy('ordinal')->get();
    ExamAssignmentSeatAllocation::query()
        ->where('exam_assignment_id', $otherAssignment->id)
        ->whereNull('effective_to')
        ->firstOrFail()
        ->update(['effective_to' => now()->addSecond()]);

    $firstRelocation = app(RelocateExamAssignmentSeat::class)->handle(
        $assignment,
        $seats[1],
        $this->actor,
        'Move before submission',
    );
    $submission = app(RecordExamSubmission::class)->handle(
        $assignment,
        $this->actor,
        'emr-submission-freeze',
    );
    $secondRelocation = app(RelocateExamAssignmentSeat::class)->handle(
        $assignment,
        $seats[0],
        $this->actor,
        'Emergency physical move after submission',
    );

    $submission = $submission->fresh('seatAllocation');
    $active = $secondRelocation->fresh('sessionSeat');

    expect($submission->seat_allocation_id)->toBeNull()
        ->and($submission->seatAllocation)->toBeNull()
        ->and($firstRelocation->sessionSeat->is($seats[1]))->toBeTrue()
        ->and($active->sessionSeat->is($seats[0]))->toBeTrue()
        ->and($active->sessionSeat->bookletSet->set_code)->toBe('A')
        ->and($snapshot->fresh()->booklet_version_id)->toBe($fixture['booklet']->id);
});

test('simple-capacity submission does not depend on a paper seat allocation', function (): void {
    $fixture = buildGradingFixture($this, close: false);
    $session = $fixture['session'];
    $assignment = $session->assignments()->firstOrFail();
    $session = app(StartExamSession::class)->handle($session, $this->actor, $session->version);

    $submission = app(RecordExamSubmission::class)->handle($assignment, $this->actor, 'emr-no-paper-submission');
    $plan = ExamSessionLogisticsPlan::query()->where('exam_session_id', $session->id)->where('is_current', true)->firstOrFail();

    expect(ExamSubmissionRecord::query()->where('exam_assignment_id', $assignment->id)->exists())->toBeTrue()
        ->and($submission->seat_allocation_id)->toBeNull()
        ->and($plan->seating_mode)->toBe(ExamSession::SEATING_MODE_SIMPLE_CAPACITY)
        ->and($plan->seats()->count())->toBe(0)
        ->and(ExamSessionMaterialSnapshot::query()->where('exam_session_id', $session->id)->exists())->toBeFalse()
        ->and($session->fresh()->status)->toBe(ExamSession::STATUS_IN_PROGRESS);
});

test('resolve historical snapshot interpretation from exact saved paper pins', function (): void {
    $fixture = buildPaperGradingFixture($this, applicantCount: 1, close: false);
    $session = $fixture['session'];
    $snapshot = $fixture['historicalSnapshot'];
    $assignment = $session->assignments()->firstOrFail();
    $seat = $fixture['historicalSeatsByAssignment'][$assignment->id];
    $manifestRows = [[
        'ordinal' => (int) $seat->ordinal,
        'row_number' => (int) $seat->row_number,
        'column_number' => (int) $seat->column_number,
        'seat_label' => (string) $seat->seat_label,
        'booklet_set_id' => (int) $seat->booklet_set_id,
    ]];
    ExamSessionMaterialSnapshot::query()->whereKey($snapshot->id)->update([
        'seating_mode' => null,
        'capacity_limit' => null,
        'required_set_codes' => null,
        'seat_manifest_hash' => hash('sha256', json_encode($manifestRows, JSON_THROW_ON_ERROR)),
    ]);
    $snapshot->refresh();

    $this->actingAs($this->actor)
        ->post(route('exams.sessions.snapshots.interpretation', [$session, $snapshot]), [
            'expected_version' => $snapshot->revision,
            'seating_mode' => ExamSession::SEATING_MODE_ASSIGNED_SEATING,
            'reason' => 'Historical verified assigned arrangement',
        ])
        ->assertSessionHasErrors(['provenance_notes']);

    $this->actingAs($this->actor)
        ->post(route('exams.sessions.snapshots.interpretation', [$session, $snapshot]), [
            'expected_version' => $snapshot->revision,
            'seating_mode' => ExamSession::SEATING_MODE_ASSIGNED_SEATING,
            'reason' => 'Historical verified assigned arrangement',
            'provenance_notes' => 'Exact saved Kit, Booklet Set and frozen historical seat pins.',
        ])
        ->assertRedirect()
        ->assertSessionHas('status', 'Exam session seating interpretation resolved.');

    expect($snapshot->fresh()->seating_mode)->toBe(ExamSession::SEATING_MODE_ASSIGNED_SEATING)
        ->and($snapshot->fresh()->capacity_limit)->toBe(1)
        ->and($snapshot->fresh()->required_set_codes)->toBe(['A'])
        ->and($snapshot->fresh()->seat_manifest_hash)->toBe(hash('sha256', json_encode($manifestRows, JSON_THROW_ON_ERROR)));
});

test('artisan seating mode report and backfill commands execute successfully', function (): void {
    $this->artisan('exams:seating-mode-report')
        ->assertSuccessful();

    $this->artisan('exams:backfill-seating-mode', ['--dry-run' => true])
        ->assertSuccessful();
});
