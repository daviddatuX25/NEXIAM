<?php

use App\Admission\Actions\OpenProgramFollowUp;
use App\Admission\Actions\ResolveProgramFollowUp;
use App\Admission\Actions\SetGuidanceProgramPool;
use App\Admission\Support\AttemptProgramFacts;
use App\Admission\Support\QuotaReservation;
use App\Authorization\CapabilityCatalog;
use App\Models\Admission\AdmissionAttempt;
use App\Models\Admission\EnrollmentStamp;
use App\Models\Admission\ProgramDecline;
use App\Models\Admission\ProgramFollowUp;
use App\Models\Admission\ProgramPool;
use App\Models\Admission\ProgramPoolOption;
use App\Models\Admission\ProgramSelection;
use App\Models\Assessment\GradingSession;
use App\Models\Assessment\ScoreRecord;
use App\Models\Consultation\ConsultationAssignment;
use App\Models\Consultation\ConsultationSession;
use App\Models\Examination\ExamAssignment;
use App\Models\Examination\ExamAttendanceRecord;
use App\Models\Examination\ExamSession;
use App\Models\Examination\ExamSubmissionRecord;
use App\Models\Foundation\AcademicCycle;
use App\Models\Foundation\AptitudeArea;
use App\Models\Foundation\AuditEvent;
use App\Models\Foundation\Institution;
use App\Models\Foundation\Operation;
use App\Models\Foundation\Program;
use App\Models\Foundation\ProgramQuota;
use App\Models\Foundation\Room;
use App\Models\Printing\PrintJob;
use App\Models\Printing\PrintJobItem;
use App\Models\Release\ConsultationDecision;
use App\Models\Release\ResultDocument;
use App\Models\Release\ResultRelease;
use App\Models\ResultSheet\ResultSheetTemplate;
use App\Models\User;
use App\Release\Actions\PrepareResultDocument;
use App\Release\Actions\RecordConsultationDecision;
use App\ResultSheet\Actions\MarkResultSheetsPrinted;
use App\Testing\PracticeFixtureSet;
use App\Testing\PracticeWorkspace;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Str;
use RuntimeException;
use Spatie\Permission\Models\Permission;

beforeEach(function (): void {
    seedE2Foundation();

    $this->guidance = User::query()->where('email', 'guidance.counselor@nexiam.test')->firstOrFail();
    $this->cycle = AcademicCycle::query()->where('code', 'SYN-CYCLE-2026')->firstOrFail();
    $this->room = Room::query()->where('code', 'RM-101')->firstOrFail();

    foreach (['BSN', 'BSPSY'] as $code) {
        $prog = Program::query()->where('code', $code)->firstOrFail();
        ProgramQuota::query()->updateOrCreate(
            ['program_id' => $prog->id, 'academic_cycle_id' => $this->cycle->id],
            ['slots' => 50],
        );
    }
});

function acceptUnifiedConsultationApplicant(string $email, string $lastName): array
{
    $result = submitSyntheticApplicant($email, $lastName);
    $registrar = User::query()->where('email', 'registrar.clerk@nexiam.test')->firstOrFail();

    test()->actingAs($registrar)
        ->post(route('admissions.accept', $result['attempt']), ['expected_version' => 1])
        ->assertRedirect();

    return $result;
}

function makeUnifiedConsultationResultReady(array $result, bool $finalized = true, bool $printed = true): void
{
    $attempt = $result['attempt'];
    $guidance = test()->guidance;
    $examSession = ExamSession::query()->create([
        'academic_cycle_id' => AcademicCycle::query()->where('code', 'SYN-CYCLE-2026')->firstOrFail()->id,
        'room_id' => Room::query()->where('code', 'RM-101')->firstOrFail()->id,
        'code' => 'EX-UNIFIED-'.str_replace('.', '', uniqid('', true)),
        'status' => ExamSession::STATUS_IN_PROGRESS,
        'starts_at' => now()->subDays(5),
        'effective_end_at' => now()->subDays(5)->addHours(2),
        'version' => 1,
    ]);
    $grading = GradingSession::query()->create([
        'exam_session_id' => $examSession->id,
        'status' => $finalized ? GradingSession::STATUS_FINALIZED : GradingSession::STATUS_OPEN,
        'opened_at' => now()->subDays(4),
        'finalized_at' => $finalized ? now()->subDays(3) : null,
    ]);
    $assignment = ExamAssignment::query()->create([
        'exam_session_id' => $examSession->id,
        'admission_attempt_id' => $attempt->id,
        'status' => ExamAssignment::STATUS_ASSIGNED,
        'source' => ExamAssignment::SOURCE_STAFF_ASSIGNED,
    ]);
    ExamAttendanceRecord::query()->create([
        'exam_assignment_id' => $assignment->id,
        'recorded_by_user_id' => $guidance->id,
        'outcome' => ExamAttendanceRecord::OUTCOME_PRESENT,
        'idempotency_key' => 'unified-attendance-'.$attempt->id,
        'recorded_at' => now(),
    ]);
    ExamSubmissionRecord::query()->create([
        'exam_assignment_id' => $assignment->id,
        'recorded_by_user_id' => $guidance->id,
        'idempotency_key' => 'unified-submission-'.$attempt->id,
        'submitted_at' => now(),
    ]);
    if ($finalized) {
        foreach (AptitudeArea::query()->where('is_active', true)->get() as $area) {
            ScoreRecord::query()->create([
                'grading_session_id' => $grading->id,
                'admission_attempt_id' => $attempt->id,
                'aptitude_area_id' => $area->id,
                'score' => min(20, (int) $area->max_items),
                'converted_score' => '85.00',
                'source' => ScoreRecord::SOURCE_MANUAL,
                'recorded_by_user_id' => $guidance->id,
                'idempotency_key' => 'unified-score-'.$attempt->id.'-'.$area->id,
            ]);
        }
    }
    $examSession->update(['status' => ExamSession::STATUS_COMPLETED]);
    if (! $finalized) {
        return;
    }

    if (ResultSheetTemplate::active() === null) {
        ResultSheetTemplate::query()->create([
            'name' => 'Unified Consultation Test Sheet',
            'mode' => ResultSheetTemplate::MODE_HTML,
            'paper_size' => 'a4',
            'orientation' => 'portrait',
            'logical_unit' => 'full',
            'content' => '<h1>{{applicant_name}}</h1><table><tbody class="scores-rows-placeholder"></tbody></table>',
            'is_active' => true,
        ]);
    }
    $document = app(PrepareResultDocument::class)->handle($attempt, $guidance);
    if ($printed) {
        app(MarkResultSheetsPrinted::class)->handle(
            $guidance,
            [(int) $document->id],
            true,
            idempotencyKey: 'unified-print-'.$attempt->id,
        );
    }
}

function unifiedConsultationAttempt(string $email, string $lastName): AdmissionAttempt
{
    $result = acceptUnifiedConsultationApplicant($email, $lastName);
    makeUnifiedConsultationResultReady($result);

    return $result['attempt'];
}

test('authorized counselor can atomically approve candidate with default program and alternate options', function (): void {
    $result = acceptUnifiedConsultationApplicant('unified-approve@example.test', 'UnifiedApprove');
    $attempt = $result['attempt'];
    $attempt->forceFill(['program_preference_1_code' => 'BSN'])->save();
    makeUnifiedConsultationResultReady($result);

    $this->actingAs($this->guidance)->post(route('consultation.decision', $attempt), [
        'decision_type' => 'approve',
        'default_program_code' => 'BSN',
        'pool_options' => [
            ['program_code' => 'BSN', 'display_order' => 1, 'basis' => 'preference'],
            ['program_code' => 'BSPSY', 'display_order' => 2, 'basis' => 'guidance_option'],
        ],
        'idempotency_key' => 'unified-approve-'.$attempt->id,
    ])->assertRedirect();
    $decision = ConsultationDecision::query()->where('admission_attempt_id', $attempt->id)->firstOrFail();
    $pool = ProgramPool::currentFor($attempt->id);

    expect($decision->decision_type)->toBe('approve')
        ->and(AttemptProgramFacts::isAdvised($attempt->fresh()))->toBeTrue()
        ->and($pool)->not->toBeNull()
        ->and($pool->options->first()->program->code)->toBe('BSN')
        ->and($pool->options)->toHaveCount(2);
});

test('authorized counselor can atomically record modify decision redirecting candidate to alternative program', function (): void {
    $attempt = unifiedConsultationAttempt('unified-modify@example.test', 'UnifiedModify');

    $this->actingAs($this->guidance)->post(route('consultation.decision', $attempt), [
        'decision_type' => 'modify',
        'default_program_code' => 'BSPSY',
        'pool_options' => [['program_code' => 'BSPSY', 'display_order' => 1, 'basis' => 'guidance_option']],
        'idempotency_key' => 'unified-modify-'.$attempt->id,
    ])->assertRedirect();

    $decision = ConsultationDecision::query()->where('admission_attempt_id', $attempt->id)->firstOrFail();
    $pool = ProgramPool::currentFor($attempt->id);

    expect($decision->decision_type)->toBe('modify')
        ->and(AttemptProgramFacts::isAdvised($attempt->fresh()))->toBeTrue()
        ->and($pool)->not->toBeNull()
        ->and($pool->options->first()->program->code)->toBe('BSPSY');
});

test('authorized counselor can record override with mandatory justification', function (): void {
    $attempt = unifiedConsultationAttempt('unified-override@example.test', 'UnifiedOverride');
    $notes = 'Dean athletic scholarship waiver';

    $this->actingAs($this->guidance)->post(route('consultation.decision', $attempt), [
        'decision_type' => 'override',
        'notes' => $notes,
        'default_program_code' => 'BSPSY',
        'pool_options' => [['program_code' => 'BSPSY', 'display_order' => 1, 'basis' => 'guidance_option']],
        'idempotency_key' => 'unified-override-'.$attempt->id,
    ])->assertRedirect();

    $decision = ConsultationDecision::query()->where('admission_attempt_id', $attempt->id)->firstOrFail();

    expect($decision->notes)->toBe($notes)
        ->and(AttemptProgramFacts::isAdvised($attempt->fresh()))->toBeTrue();
});

test('override and defer reject submission when notes are blank', function (): void {
    $overrideAttempt = unifiedConsultationAttempt('unified-blank-override@example.test', 'BlankOverride');
    $this->actingAs($this->guidance)->post(route('consultation.decision', $overrideAttempt), [
        'decision_type' => 'override', 'notes' => '', 'idempotency_key' => 'blank-override-'.$overrideAttempt->id,
    ])->assertSessionHasErrors(['notes']);

    $deferAttempt = unifiedConsultationAttempt('unified-blank-defer@example.test', 'BlankDefer');
    $this->actingAs($this->guidance)->post(route('consultation.decision', $deferAttempt), [
        'decision_type' => 'defer', 'notes' => '', 'idempotency_key' => 'blank-defer-'.$deferAttempt->id,
    ])->assertSessionHasErrors(['notes']);
});
test('direct decision action rejects blank override and defer notes', function (): void {
    $attempt = unifiedConsultationAttempt('unified-direct-blank-notes@example.test', 'DirectBlankNotes');
    $action = app(RecordConsultationDecision::class);

    expect(fn () => $action->handle($attempt, $this->guidance, 'override', ' ', 'direct-override-'.$attempt->id))
        ->toThrow(RuntimeException::class)
        ->and(fn () => $action->handle($attempt, $this->guidance, 'defer', null, 'direct-defer-'.$attempt->id))
        ->toThrow(RuntimeException::class);
});

test('refer requires target program code and referral reason', function (): void {
    $attempt = unifiedConsultationAttempt('unified-refer@example.test', 'UnifiedRefer');

    $this->actingAs($this->guidance)->post(route('consultation.decision', $attempt), [
        'decision_type' => 'refer',
        'idempotency_key' => 'unified-refer-'.$attempt->id,
    ])->assertSessionHasErrors(['target_program_code', 'referral_reason']);
});

test('submitting pool options requires GUIDANCE_SELECTION_MANAGE capability', function (): void {
    $attempt = unifiedConsultationAttempt('unified-capability@example.test', 'UnifiedCapability');
    Permission::findOrCreate('guidance.consultation.decide');
    Permission::findOrCreate('guidance.selection.manage');
    $limitedCounselor = User::factory()->create();
    $limitedCounselor->givePermissionTo('guidance.consultation.decide');

    $this->actingAs($limitedCounselor)->post(route('consultation.decision', $attempt), [
        'decision_type' => 'approve',
        'default_program_code' => 'BSN',
        'pool_options' => [['program_code' => 'BSN', 'display_order' => 1, 'basis' => 'guidance_option']],
        'idempotency_key' => 'unified-capability-'.$attempt->id,
    ])->assertForbidden();
});
test('referral requires follow-up management even with consultation decision capability', function (): void {
    $attempt = unifiedConsultationAttempt('unified-refer-decision-only@example.test', 'ReferDecisionOnly');
    Permission::findOrCreate('guidance.consultation.decide');
    $actor = User::factory()->create();
    $actor->givePermissionTo('guidance.consultation.decide');

    $this->actingAs($actor)->post(route('consultation.decision', $attempt), [
        'decision_type' => 'refer',
        'target_program_code' => 'BSN',
        'referral_reason' => 'Department interview requested',
        'idempotency_key' => 'refer-decision-only-'.$attempt->id,
    ])->assertForbidden();

    expect(ConsultationDecision::query()->where('admission_attempt_id', $attempt->id)->count())->toBe(0)
        ->and(ProgramPool::query()->where('admission_attempt_id', $attempt->id)->count())->toBe(0)
        ->and(ProgramFollowUp::query()->where('admission_attempt_id', $attempt->id)->count())->toBe(0);
});

test('referral requires follow-up management with selection management too', function (): void {
    $attempt = unifiedConsultationAttempt('unified-refer-no-follow-up@example.test', 'ReferNoFollowUp');
    Permission::findOrCreate('guidance.consultation.decide');
    Permission::findOrCreate('guidance.selection.manage');
    Permission::findOrCreate('guidance.follow_up.manage');
    $actor = User::factory()->create();
    $actor->givePermissionTo(['guidance.consultation.decide', 'guidance.selection.manage']);

    $this->actingAs($actor)->post(route('consultation.decision', $attempt), [
        'decision_type' => 'refer',
        'target_program_code' => 'BSN',
        'referral_reason' => 'Department interview requested',
        'idempotency_key' => 'refer-no-follow-up-'.$attempt->id,
    ])->assertForbidden();

    expect(ConsultationDecision::query()->where('admission_attempt_id', $attempt->id)->count())->toBe(0)
        ->and(ProgramPool::query()->where('admission_attempt_id', $attempt->id)->count())->toBe(0)
        ->and(ProgramFollowUp::query()->where('admission_attempt_id', $attempt->id)->count())->toBe(0);
});

test('direct referral action rejects decision-only actors without changing consultation state', function (): void {
    $attempt = unifiedConsultationAttempt('unified-direct-refer-decision-only@example.test', 'DirectReferDecisionOnly');
    $session = ConsultationSession::query()->create([
        'academic_cycle_id' => $this->cycle->id,
        'room_id' => $this->room->id,
        'code' => 'CS-DIRECT-'.str_replace('.', '', uniqid('', true)),
        'status' => ConsultationSession::STATUS_PUBLISHED,
        'scheduled_at' => now()->addDay(),
        'starts_at' => now()->addDay(),
        'ends_at' => now()->addDay()->addHour(),
    ]);
    $assignment = ConsultationAssignment::query()->create([
        'consultation_session_id' => $session->id,
        'admission_attempt_id' => $attempt->id,
        'status' => ConsultationAssignment::STATUS_ASSIGNED,
        'source' => ConsultationAssignment::SOURCE_STAFF_ASSIGNED,
    ]);
    $actor = User::factory()->create();
    Permission::findOrCreate(CapabilityCatalog::GUIDANCE_CONSULTATION_DECIDE);
    Permission::findOrCreate(CapabilityCatalog::GUIDANCE_SELECTION_MANAGE);
    Permission::findOrCreate(CapabilityCatalog::GUIDANCE_FOLLOW_UP_MANAGE);
    $actor->givePermissionTo(CapabilityCatalog::GUIDANCE_CONSULTATION_DECIDE);

    expect(fn () => app(RecordConsultationDecision::class)->handle(
        $attempt,
        $actor,
        'refer',
        null,
        'direct-refer-decision-only-'.$attempt->id,
        'BSN',
        'Department interview requested',
    ))->toThrow(RuntimeException::class);

    expect(ConsultationDecision::query()->where('admission_attempt_id', $attempt->id)->count())->toBe(0)
        ->and(ProgramPool::query()->where('admission_attempt_id', $attempt->id)->count())->toBe(0)
        ->and(ProgramFollowUp::query()->where('admission_attempt_id', $attempt->id)->count())->toBe(0)
        ->and(ConsultationAssignment::query()->whereKey($assignment->id)->value('status'))->toBe(ConsultationAssignment::STATUS_ASSIGNED);
});

test('decision-only defer requires GUIDANCE_CONSULTATION_DECIDE independently of pool capability', function (): void {
    $attempt = unifiedConsultationAttempt('unified-decision-capability@example.test', 'DecisionCapability');
    Permission::findOrCreate('guidance.consultation.decide');
    Permission::findOrCreate('guidance.selection.manage');
    $poolManagerWithoutDecision = User::factory()->create();
    $poolManagerWithoutDecision->givePermissionTo('guidance.selection.manage');

    $this->actingAs($poolManagerWithoutDecision)->post(route('consultation.decision', $attempt), [
        'decision_type' => 'defer',
        'notes' => 'Awaiting further review',
        'idempotency_key' => 'decision-capability-'.$attempt->id,
    ])->assertForbidden();
});

test('deferring an advised attempt preserves its active selection and held quota', function (): void {
    $result = acceptUnifiedConsultationApplicant('unified-defer-active-selection@example.test', 'DeferActiveSelection');
    $attempt = $result['attempt'];
    makeUnifiedConsultationResultReady($result);

    $this->actingAs($this->guidance)->post(route('consultation.decision', $attempt), [
        'decision_type' => 'approve',
        'default_program_code' => 'BSN',
        'pool_options' => [['program_code' => 'BSN', 'display_order' => 1, 'basis' => 'preference']],
        'idempotency_key' => 'active-selection-approve-'.$attempt->id,
    ])->assertRedirect();

    $selection = ProgramSelection::query()->where('admission_attempt_id', $attempt->id)->firstOrFail();
    $applicant = User::factory()->create();
    $selection->forceFill([
        'status' => ProgramSelection::STATUS_ACTIVE,
        'actor_user_id' => $applicant->id,
        'applicant_acknowledged_at' => now(),
    ])->save();
    $heldQuotaCount = QuotaReservation::committedAttemptCount($attempt, $selection->program_id);
    $decision = ConsultationDecision::query()->where('admission_attempt_id', $attempt->id)->latest('version')->firstOrFail();

    $this->actingAs($this->guidance)->post(route('consultation.decision', $attempt), [
        'decision_type' => 'defer',
        'notes' => 'Further review requested',
        'idempotency_key' => 'defer-active-selection-'.$attempt->id,
    ])->assertSessionHasErrors(['decision']);

    expect($selection->fresh()->status)->toBe(ProgramSelection::STATUS_ACTIVE)
        ->and(QuotaReservation::committedAttemptCount($attempt, $selection->program_id))->toBe($heldQuotaCount)
        ->and($heldQuotaCount)->toBe(1)
        ->and(ConsultationDecision::query()->where('admission_attempt_id', $attempt->id)->latest('version')->value('decision_type'))->toBe('approve')
        ->and($decision->fresh()->decision_type)->toBe('approve');
});

test('transaction rolls back decision if pool options fail validation', function (): void {
    $attempt = unifiedConsultationAttempt('unified-invalid-pool@example.test', 'UnifiedInvalidPool');

    $this->actingAs($this->guidance)->post(route('consultation.decision', $attempt), [
        'decision_type' => 'approve',
        'default_program_code' => 'BSN',
        'pool_options' => [['program_code' => 'NONEXISTENT', 'display_order' => 1, 'basis' => 'preference']],
        'idempotency_key' => 'unified-invalid-pool-'.$attempt->id,
    ])->assertSessionHasErrors(['pool_options.0.program_code']);

    expect(ConsultationDecision::query()->where('admission_attempt_id', $attempt->id)->exists())->toBeFalse()
        ->and(AttemptProgramFacts::isAdvised($attempt->fresh()))->toBeFalse();
});

test('changed alternate payload conflicts without mutating the consultation decision or pool', function (): void {
    $attempt = unifiedConsultationAttempt('unified-changed-payload@example.test', 'ChangedPayload');
    $payload = [
        'decision_type' => 'approve',
        'notes' => 'Approved after review',
        'default_program_code' => 'BSN',
        'pool_options' => [
            ['program_code' => 'BSN', 'display_order' => 1, 'basis' => 'preference'],
            ['program_code' => 'BSPSY', 'display_order' => 2, 'basis' => 'guidance_option'],
        ],
        'idempotency_key' => 'unified-changed-payload-'.$attempt->id,
    ];

    $this->actingAs($this->guidance)->post(route('consultation.decision', $attempt), $payload)->assertRedirect();

    $decision = ConsultationDecision::query()->where('admission_attempt_id', $attempt->id)->latest('version')->firstOrFail();
    $pool = ProgramPool::currentFor($attempt->id);
    $auditCount = AuditEvent::query()->count();

    $payload['pool_options'][1]['basis'] = 'preference';

    $this->actingAs($this->guidance)
        ->post(route('consultation.decision', $attempt), $payload)
        ->assertSessionHasErrors(['decision']);

    expect(ConsultationDecision::query()->where('admission_attempt_id', $attempt->id)->latest('version')->firstOrFail()->is($decision))->toBeTrue()
        ->and(ProgramPool::currentFor($attempt->id)->version)->toBe($pool->version)
        ->and(AuditEvent::query()->count())->toBe($auditCount);
});

test('idempotent replay returns existing decision without duplicate writes', function (): void {
    $attempt = unifiedConsultationAttempt('unified-idempotent@example.test', 'UnifiedIdempotent');
    $payload = [
        'decision_type' => 'approve',
        'default_program_code' => 'BSN',
        'pool_options' => [['program_code' => 'BSN', 'display_order' => 1, 'basis' => 'preference']],
        'idempotency_key' => 'unified-idempotent-'.$attempt->id,
    ];

    $this->actingAs($this->guidance)->post(route('consultation.decision', $attempt), $payload)->assertRedirect();
    $this->actingAs($this->guidance)->post(route('consultation.decision', $attempt), $payload)->assertRedirect();

    expect(ConsultationDecision::query()->where('admission_attempt_id', $attempt->id)->count())->toBe(1)
        ->and(ProgramPool::query()->where('admission_attempt_id', $attempt->id)->count())->toBe(1);
});
test('support projection returns program preference codes as strings for P1 selection', function (): void {
    $result = acceptUnifiedConsultationApplicant('unified-support@example.test', 'UnifiedSupport');
    $attempt = $result['attempt'];
    $attempt->forceFill(['program_preference_1_code' => 'BSN'])->save();

    $this->actingAs($this->guidance)
        ->getJson(route('admission-support.support', $attempt))
        ->assertOk()
        ->assertJsonPath('support.preferences.0', 'BSN');
});
test('workspace support projection includes ordered consultation decision history', function (): void {
    $attempt = unifiedConsultationAttempt('unified-history@example.test', 'History');
    $programHead = User::query()->where('email', 'program.head.bsit@nexiam.test')->firstOrFail();

    ConsultationDecision::query()->create([
        'admission_attempt_id' => $attempt->id,
        'counselor_user_id' => $this->guidance->id,
        'decision_type' => 'defer',
        'notes' => 'First review notes',
        'version' => 1,
        'idempotency_key' => 'history-first-'.$attempt->id,
    ]);
    ConsultationDecision::query()->create([
        'admission_attempt_id' => $attempt->id,
        'counselor_user_id' => $programHead->id,
        'decision_type' => 'refer',
        'notes' => 'Second review notes',
        'version' => 2,
        'idempotency_key' => 'history-second-'.$attempt->id,
    ]);

    $this->actingAs($this->guidance)
        ->getJson(route('admission-support.support', $attempt))
        ->assertOk()
        ->assertJsonPath('support.consultation_history.0.decision_type', 'defer')
        ->assertJsonPath('support.consultation_history.0.notes', 'First review notes')
        ->assertJsonPath('support.consultation_history.0.recorded_by', $this->guidance->name)
        ->assertJsonPath('support.consultation_history.0.version', 1)
        ->assertJsonPath('support.consultation_history.1.decision_type', 'refer')
        ->assertJsonPath('support.consultation_history.1.notes', 'Second review notes')
        ->assertJsonPath('support.consultation_history.1.recorded_by', $programHead->name)
        ->assertJsonPath('support.consultation_history.1.version', 2);
});

test('older idempotency key replay does not replace a newer decision revision', function (): void {
    $attempt = unifiedConsultationAttempt('unified-revision-replay@example.test', 'RevisionReplay');
    $payloadA = [
        'decision_type' => 'approve',
        'default_program_code' => 'BSN',
        'pool_options' => [['program_code' => 'BSN', 'display_order' => 1, 'basis' => 'preference']],
        'idempotency_key' => 'revision-A-'.$attempt->id,
    ];
    $this->actingAs($this->guidance)->post(route('consultation.decision', $attempt), $payloadA)->assertRedirect();
    $receiptA = ConsultationDecision::query()->where('idempotency_key', $payloadA['idempotency_key'])->firstOrFail();
    $payloadB = [
        'decision_type' => 'refer',
        'target_program_code' => 'BSPSY',
        'referral_reason' => 'Additional psychology review requested',
        'idempotency_key' => 'revision-B-'.$attempt->id,
    ];
    $this->actingAs($this->guidance)->post(route('consultation.decision', $attempt), $payloadB)->assertRedirect();
    $beforeReplay = ConsultationDecision::query()->where('admission_attempt_id', $attempt->id)->orderByDesc('version')->firstOrFail();
    $poolCount = ProgramPool::query()->where('admission_attempt_id', $attempt->id)->count();
    $auditCount = AuditEvent::query()->where('event_type', 'consultation.decision_recorded')->count();

    $this->actingAs($this->guidance)->post(route('consultation.decision', $attempt), $payloadA)->assertRedirect();

    $latest = ConsultationDecision::query()->where('admission_attempt_id', $attempt->id)->orderByDesc('version')->firstOrFail();
    expect($latest->decision_type)->toBe('refer')
        ->and($latest->idempotency_key)->toBe('revision-B-'.$attempt->id)
        ->and($receiptA->fresh()->decision_type)->toBe('approve')
        ->and($receiptA->fresh()->version)->toBe(1)
        ->and($poolCount)->toBe(ProgramPool::query()->where('admission_attempt_id', $attempt->id)->count())
        ->and($auditCount)->toBe(AuditEvent::query()->where('event_type', 'consultation.decision_recorded')->count())
        ->and($beforeReplay->decision_type)->toBe('refer');
});

test('ready result document alone does not satisfy F2F print evidence', function (): void {
    $result = acceptUnifiedConsultationApplicant('unified-unprinted@example.test', 'Unprinted');
    $attempt = $result['attempt'];
    makeUnifiedConsultationResultReady($result, printed: false);

    $this->actingAs($this->guidance)->post(route('consultation.decision', $attempt), [
        'decision_type' => 'defer',
        'notes' => 'Await printed sheet',
        'idempotency_key' => 'unprinted-'.$attempt->id,
    ])->assertSessionHasErrors(['decision']);

    expect(ConsultationDecision::query()->where('admission_attempt_id', $attempt->id)->exists())->toBeFalse();

    $document = ResultDocument::query()->where('admission_attempt_id', $attempt->id)->firstOrFail();
    ResultRelease::query()->create([
        'admission_attempt_id' => $attempt->id,
        'result_document_id' => $document->id,
        'released_by_user_id' => $this->guidance->id,
        'release_version' => 1,
        'is_current' => true,
        'released_at' => now(),
        'idempotency_key' => 'printed-'.$attempt->id,
        'mode' => 'f2f',
    ]);
    app(MarkResultSheetsPrinted::class)->handle(
        $this->guidance,
        [(int) $document->id],
        true,
        idempotencyKey: 'printed-receipt-'.$attempt->id,
    );

    $this->actingAs($this->guidance)->post(route('consultation.decision', $attempt), [
        'decision_type' => 'defer',
        'notes' => 'Printed sheet reviewed',
        'idempotency_key' => 'printed-decision-'.$attempt->id,
    ])->assertRedirect();
    expect(ConsultationDecision::query()->where('admission_attempt_id', $attempt->id)->firstOrFail()->decision_type)->toBe('defer');
});

test('historical printed release does not satisfy current F2F print evidence', function (): void {
    $result = acceptUnifiedConsultationApplicant('unified-historical-print@example.test', 'HistoricalPrint');
    $attempt = $result['attempt'];
    makeUnifiedConsultationResultReady($result, printed: false);
    $document = ResultDocument::query()->where('admission_attempt_id', $attempt->id)->firstOrFail();

    ResultRelease::query()->create([
        'admission_attempt_id' => $attempt->id,
        'result_document_id' => $document->id,
        'released_by_user_id' => $this->guidance->id,
        'release_version' => 1,
        'is_current' => false,
        'released_at' => now()->subDay(),
        'idempotency_key' => 'historical-printed-'.$attempt->id,
        'mode' => 'f2f',
    ])->forceFill(['result_printed_at' => now()->subDay()])->save();
    ResultRelease::query()->create([
        'admission_attempt_id' => $attempt->id,
        'result_document_id' => $document->id,
        'released_by_user_id' => $this->guidance->id,
        'release_version' => 2,
        'is_current' => true,
        'released_at' => now(),
        'idempotency_key' => 'current-unprinted-'.$attempt->id,
        'mode' => 'f2f',
    ]);

    $this->actingAs($this->guidance)->post(route('consultation.decision', $attempt), [
        'decision_type' => 'defer',
        'notes' => 'Current release is unprinted',
        'idempotency_key' => 'historical-print-decision-'.$attempt->id,
    ])->assertSessionHasErrors(['decision']);

    expect(ConsultationDecision::query()->where('admission_attempt_id', $attempt->id)->exists())->toBeFalse();
});

test('print item for a superseded release does not satisfy current F2F print evidence', function (): void {
    $result = acceptUnifiedConsultationApplicant('unified-superseded-print@example.test', 'SupersededPrint');
    $attempt = $result['attempt'];
    makeUnifiedConsultationResultReady($result, printed: false);
    $document = ResultDocument::query()->where('admission_attempt_id', $attempt->id)->firstOrFail();
    $historicalRelease = ResultRelease::query()->create([
        'admission_attempt_id' => $attempt->id,
        'result_document_id' => $document->id,
        'released_by_user_id' => $this->guidance->id,
        'release_version' => 1,
        'is_current' => false,
        'released_at' => now()->subDay(),
        'idempotency_key' => 'superseded-release-'.$attempt->id,
        'mode' => 'f2f',
    ]);
    ResultRelease::query()->create([
        'admission_attempt_id' => $attempt->id,
        'result_document_id' => $document->id,
        'released_by_user_id' => $this->guidance->id,
        'release_version' => 2,
        'is_current' => true,
        'released_at' => now(),
        'idempotency_key' => 'current-release-'.$attempt->id,
        'mode' => 'f2f',
    ]);
    $job = PrintJob::query()->create([
        'artifact_type' => PrintJob::ARTIFACT_RESULT_SHEET,
        'is_preview' => false,
        'scope_type' => 'release',
        'scope_id' => $historicalRelease->id,
        'status' => PrintJob::STATUS_READY,
        'file_path' => 'result-sheets/superseded.pdf',
        'file_checksum' => hash('sha256', 'superseded-print-'.$attempt->id),
        'requested_by_user_id' => $this->guidance->id,
        'progress_total' => 1,
        'progress_done' => 1,
    ]);
    PrintJobItem::query()->create([
        'print_job_id' => $job->id,
        'admission_attempt_id' => $attempt->id,
        'result_release_id' => $historicalRelease->id,
        'item_index' => 1,
        'status' => PrintJobItem::STATUS_READY,
        'file_path' => 'result-sheets/superseded-item.pdf',
        'file_checksum' => hash('sha256', 'superseded-item-'.$attempt->id),
    ]);

    $this->actingAs($this->guidance)->post(route('consultation.decision', $attempt), [
        'decision_type' => 'defer',
        'notes' => 'Only superseded print item exists',
        'idempotency_key' => 'superseded-print-decision-'.$attempt->id,
    ])->assertSessionHasErrors(['decision']);

    expect(ConsultationDecision::query()->where('admission_attempt_id', $attempt->id)->exists())->toBeFalse();
});

test('atomic advice preserves long decision notes and rejects mixed branch fields and incoherent pools', function (): void {
    $attempt = unifiedConsultationAttempt('unified-payload-contract@example.test', 'PayloadContract');
    $base = [
        'decision_type' => 'approve',
        'default_program_code' => 'BSN',
        'pool_options' => [
            ['program_code' => 'BSN', 'display_order' => 1, 'basis' => 'preference'],
        ],
        'idempotency_key' => 'payload-contract-'.$attempt->id,
    ];

    foreach ([
        ['decision_type' => 'refer', 'target_program_code' => 'BSN', 'referral_reason' => 'Referral', 'pool_options' => $base['pool_options']],
        ['decision_type' => 'defer', 'notes' => 'Wait', 'default_program_code' => 'BSN', 'pool_options' => $base['pool_options']],
        ['default_program_code' => null, 'pool_options' => $base['pool_options']],
        ['default_program_code' => 'BSN', 'pool_options' => [
            ['program_code' => 'BSPSY', 'display_order' => 1, 'basis' => 'guidance_option'],
        ]],
        ['pool_options' => [
            ['program_code' => 'BSN', 'display_order' => 1, 'basis' => 'preference'],
            ['program_code' => 'BSPSY', 'display_order' => 1, 'basis' => 'guidance_option'],
        ]],
    ] as $changes) {
        $this->actingAs($this->guidance)->post(route('consultation.decision', $attempt), array_replace($base, $changes, [
            'idempotency_key' => 'invalid-'.uniqid(),
        ]))->assertSessionHasErrors();
    }

    $notes = str_repeat('N', 5000);
    $this->actingAs($this->guidance)->post(route('consultation.decision', $attempt), [
        'decision_type' => 'override',
        'notes' => $notes,
        'idempotency_key' => 'long-notes-'.$attempt->id,
    ])->assertRedirect();
    expect(ConsultationDecision::query()->where('admission_attempt_id', $attempt->id)->latest('version')->firstOrFail()->notes)->toBe($notes);
});

test('referral reason accepts 500 characters and rejects 501', function (): void {
    $attempt = unifiedConsultationAttempt('unified-reason-boundary@example.test', 'ReasonBoundary');
    $this->actingAs($this->guidance)->post(route('consultation.decision', $attempt), [
        'decision_type' => 'refer',
        'target_program_code' => 'BSN',
        'referral_reason' => str_repeat('R', 501),
        'idempotency_key' => 'reason-too-long-'.$attempt->id,
    ])->assertSessionHasErrors(['referral_reason']);
    $this->actingAs($this->guidance)->post(route('consultation.decision', $attempt), [
        'decision_type' => 'refer',
        'target_program_code' => 'BSN',
        'referral_reason' => str_repeat('R', 500),
        'idempotency_key' => 'reason-boundary-'.$attempt->id,
    ])->assertRedirect();
    expect(ProgramFollowUp::query()->where('admission_attempt_id', $attempt->id)->firstOrFail()->reason)->toHaveLength(500);
});

test('direct referral action rejects reasons longer than 500 characters', function (): void {
    $attempt = unifiedConsultationAttempt('unified-direct-long-reason@example.test', 'DirectLongReason');

    expect(fn () => app(RecordConsultationDecision::class)->handle(
        $attempt,
        $this->guidance,
        'refer',
        null,
        'direct-long-referral-'.$attempt->id,
        'BSN',
        str_repeat('R', 501),
    ))->toThrow(RuntimeException::class);
});

test('consultation decisions are rejected after enrollment processing starts', function (): void {
    $attempt = unifiedConsultationAttempt('unified-enrolling-decision@example.test', 'EnrollingDecision');
    $this->actingAs($this->guidance)->post(route('consultation.decision', $attempt), [
        'decision_type' => 'approve',
        'default_program_code' => 'BSN',
        'pool_options' => [['program_code' => 'BSN', 'display_order' => 1, 'basis' => 'preference']],
        'idempotency_key' => 'enrolling-prior-approve-'.$attempt->id,
    ])->assertRedirect();

    $registrar = User::query()->where('email', 'registrar.clerk@nexiam.test')->firstOrFail();
    EnrollmentStamp::query()->create([
        'admission_attempt_id' => $attempt->id,
        'stamp_type' => EnrollmentStamp::STAMP_ENROLLING,
        'provisional_program_id' => Program::query()->where('code', 'BSN')->firstOrFail()->id,
        'stamped_by_user_id' => $registrar->id,
        'reason' => 'Enrollment processing started',
        'version' => 1,
        'idempotency_key' => 'enrolling-decision-stamp-'.$attempt->id,
        'created_at' => now(),
    ]);

    expect(fn () => app(RecordConsultationDecision::class)->handle(
        $attempt,
        $this->guidance,
        'defer',
        'Enrollment has already started',
        'enrolling-defer-'.$attempt->id,
    ))->toThrow(RuntimeException::class);
});
test('quota failure rolls back all consultation writes after the decision transaction begins', function (): void {
    $result = acceptUnifiedConsultationApplicant('unified-quota-rollback@example.test', 'QuotaRollback');
    $attempt = $result['attempt'];
    makeUnifiedConsultationResultReady($result, printed: false);
    $program = Program::query()->where('code', 'BSN')->firstOrFail();
    ProgramQuota::query()->where('program_id', $program->id)->where('academic_cycle_id', $attempt->academic_cycle_id)->update(['slots' => 0]);
    $session = ConsultationSession::query()->create([
        'academic_cycle_id' => $attempt->academic_cycle_id,
        'room_id' => $this->room->id,
        'code' => 'CONS-ROLLBACK-'.str_replace('.', '', uniqid('', true)),
        'booking_mode' => ConsultationSession::BOOKING_STAFF_ONLY,
        'status' => ConsultationSession::STATUS_PUBLISHED,
        'scheduled_at' => now()->addDay(),
        'version' => 1,
    ]);
    $assignment = ConsultationAssignment::query()->create([
        'consultation_session_id' => $session->id,
        'admission_attempt_id' => $attempt->id,
        'status' => ConsultationAssignment::STATUS_ASSIGNED,
        'source' => ConsultationAssignment::SOURCE_STAFF_ASSIGNED,
    ]);
    $job = PrintJob::query()->create([
        'artifact_type' => PrintJob::ARTIFACT_RESULT_SHEET,
        'is_preview' => false,
        'scope_type' => 'release',
        'scope_id' => 1,
        'status' => PrintJob::STATUS_READY,
        'file_path' => 'result-sheets/rollback.pdf',
        'file_checksum' => hash('sha256', 'rollback-print'),
        'requested_by_user_id' => $this->guidance->id,
        'progress_total' => 1,
        'progress_done' => 1,
    ]);
    PrintJobItem::query()->create([
        'print_job_id' => $job->id,
        'admission_attempt_id' => $attempt->id,
        'item_index' => 1,
        'status' => PrintJobItem::STATUS_READY,
        'file_path' => 'result-sheets/rollback-item.pdf',
        'file_checksum' => hash('sha256', 'rollback-item'),
    ]);
    $auditCount = AuditEvent::query()->count();

    $this->actingAs($this->guidance)->post(route('consultation.decision', $attempt), [
        'decision_type' => 'approve',
        'default_program_code' => 'BSN',
        'pool_options' => [['program_code' => 'BSN', 'display_order' => 1, 'basis' => 'preference']],
        'idempotency_key' => 'quota-rollback-'.$attempt->id,
    ])->assertSessionHasErrors(['decision']);

    expect(ConsultationDecision::query()->where('admission_attempt_id', $attempt->id)->exists())->toBeFalse()
        ->and(AttemptProgramFacts::isAdvised($attempt->fresh()))->toBeFalse()
        ->and($assignment->fresh()->status)->toBe(ConsultationAssignment::STATUS_ASSIGNED)
        ->and(ResultRelease::query()->where('admission_attempt_id', $attempt->id)->exists())->toBeFalse()
        ->and(ProgramPoolOption::query()->whereHas('pool', fn ($query) => $query->where('admission_attempt_id', $attempt->id))->exists())->toBeFalse()
        ->and(ProgramSelection::query()->where('admission_attempt_id', $attempt->id)->exists())->toBeFalse()
        ->and(AuditEvent::query()->count())->toBe($auditCount);
});
test('referral to a program outside the current pool appends a successor pool for PH clearance', function (): void {
    $attempt = unifiedConsultationAttempt('unified-referral-successor@example.test', 'ReferralSuccessor');
    $this->actingAs($this->guidance)->post(route('consultation.decision', $attempt), [
        'decision_type' => 'approve',
        'default_program_code' => 'BSPSY',
        'pool_options' => [['program_code' => 'BSPSY', 'display_order' => 4, 'basis' => 'guidance_option']],
        'idempotency_key' => 'successor-approve-'.$attempt->id,
    ])->assertRedirect();

    $referralPayload = [
        'decision_type' => 'refer',
        'target_program_code' => 'BSN',
        'referral_reason' => 'Department interview requested',
        'idempotency_key' => 'successor-refer-'.$attempt->id,
    ];
    $this->actingAs($this->guidance)->post(route('consultation.decision', $attempt), $referralPayload)->assertRedirect();
    $referralReceipt = ConsultationDecision::query()->where('idempotency_key', $referralPayload['idempotency_key'])->firstOrFail();
    $followUp = ProgramFollowUp::query()->where('admission_attempt_id', $attempt->id)->firstOrFail();
    $successor = ProgramPool::query()->whereKey($followUp->program_pool_id)->with('options.program')->firstOrFail();
    expect($successor->version)->toBe(2)
        ->and($successor->options->firstWhere('program.code', 'BSN')->display_order)->toBe(5)
        ->and($followUp->program_pool_id)->toBe($successor->id);

    $programHead = User::query()->where('email', 'program.head.bsit@nexiam.test')->firstOrFail();
    $programHead->staffProfile->update([
        'program_codes' => array_values(array_unique([...$programHead->staffProfile->program_codes, 'BSN'])),
    ]);
    $this->actingAs($programHead)
        ->post(route('program-head.follow-ups.resolve', ['program' => 'BSN', 'followUp' => $followUp->id]), [
            'outcome' => ProgramFollowUp::STATUS_CLEARED_BY_PH,
            'reason' => 'Cleared after interview',
            'idempotency_key' => 'successor-clear-'.$followUp->id,
        ])
        ->assertRedirect();
    expect($followUp->fresh()->status)->toBe(ProgramFollowUp::STATUS_CLEARED_BY_PH);

    $approval = ConsultationDecision::query()->where('admission_attempt_id', $attempt->id)
        ->where('decision_type', 'approve')->orderByDesc('version')->firstOrFail();
    $poolCount = ProgramPool::query()->where('admission_attempt_id', $attempt->id)->count();
    $followUpCount = ProgramFollowUp::query()->where('admission_attempt_id', $attempt->id)->count();
    $decisionCount = ConsultationDecision::query()->where('admission_attempt_id', $attempt->id)->count();
    $auditCount = AuditEvent::query()->where('event_type', 'consultation.decision_recorded')->count();
    $this->actingAs($this->guidance)->post(route('consultation.decision', $attempt), $referralPayload)->assertRedirect();

    $latest = ConsultationDecision::query()->where('admission_attempt_id', $attempt->id)->orderByDesc('version')->firstOrFail();
    expect($referralReceipt->fresh()->decision_type)->toBe('refer')
        ->and($referralReceipt->fresh()->version)->toBe(2)
        ->and($approval->fresh()->decision_type)->toBe('approve')
        ->and($approval->fresh()->version)->toBe(3)
        ->and($latest->decision_type)->toBe('approve')
        ->and($latest->version)->toBe(3)
        ->and(ConsultationDecision::query()->where('admission_attempt_id', $attempt->id)->count())->toBe($decisionCount)
        ->and(ProgramPool::query()->where('admission_attempt_id', $attempt->id)->count())->toBe($poolCount)
        ->and(ProgramFollowUp::query()->where('admission_attempt_id', $attempt->id)->count())->toBe($followUpCount)
        ->and(AuditEvent::query()->where('event_type', 'consultation.decision_recorded')->count())->toBe($auditCount);
});

test('re-referral after a Program Head decline uses a fresh successor pool version', function (): void {
    $attempt = unifiedConsultationAttempt('unified-referral-reconsider@example.test', 'ReferralReconsider');
    $firstReferral = [
        'decision_type' => 'refer',
        'target_program_code' => 'BSN',
        'referral_reason' => 'Initial department review',
        'idempotency_key' => 'reconsider-first-'.$attempt->id,
    ];
    $this->actingAs($this->guidance)->post(route('consultation.decision', $attempt), $firstReferral)->assertRedirect();

    $declinedFollowUp = ProgramFollowUp::query()->where('admission_attempt_id', $attempt->id)->firstOrFail();
    $declinedPool = ProgramPool::query()->whereKey($declinedFollowUp->program_pool_id)->firstOrFail();
    $programHead = User::query()->where('email', 'program.head.bsit@nexiam.test')->firstOrFail();
    app(ResolveProgramFollowUp::class)->handle(
        $declinedFollowUp,
        $programHead,
        ProgramFollowUp::STATUS_DECLINED_BY_PH,
        'Program eligibility not met',
        'reconsider-decline-'.$declinedFollowUp->id,
    );

    $this->actingAs($this->guidance)->post(route('consultation.decision', $attempt), [
        'decision_type' => 'refer',
        'target_program_code' => 'BSN',
        'referral_reason' => 'Reconsidered after new evidence',
        'idempotency_key' => 'reconsider-second-'.$attempt->id,
    ])->assertRedirect();

    $renewedFollowUp = ProgramFollowUp::query()
        ->where('admission_attempt_id', $attempt->id)
        ->where('status', ProgramFollowUp::STATUS_OPEN)
        ->firstOrFail();
    $renewedPool = ProgramPool::query()->whereKey($renewedFollowUp->program_pool_id)->with('options')->firstOrFail();

    expect($declinedFollowUp->fresh()->status)->toBe(ProgramFollowUp::STATUS_DECLINED_BY_PH)
        ->and(ProgramDecline::query()->where('admission_attempt_id', $attempt->id)
            ->where('program_pool_id', $declinedPool->id)->exists())->toBeTrue()
        ->and($renewedFollowUp->program_pool_id)->not->toBe($declinedPool->id)
        ->and($renewedPool->version)->toBe($declinedPool->version + 1)
        ->and($renewedPool->predecessor_id)->toBe($declinedPool->id)
        ->and($renewedPool->options->pluck('program_id')->all())->toBe($declinedPool->options()->pluck('program_id')->all())
        ->and($renewedPool->options->pluck('display_order')->all())->toBe($declinedPool->options()->pluck('display_order')->all())
        ->and(ProgramFollowUp::query()->where('admission_attempt_id', $attempt->id)->count())->toBe(2);
});

test('consultation referral reopening preserves standalone follow-up replay key', function (): void {
    $attempt = unifiedConsultationAttempt('unified-follow-up-reopen-key@example.test', 'ReopenKey');
    $this->actingAs($this->guidance)->post(route('consultation.decision', $attempt), [
        'decision_type' => 'approve',
        'default_program_code' => 'BSN',
        'pool_options' => [['program_code' => 'BSN', 'display_order' => 1, 'basis' => 'preference']],
        'idempotency_key' => 'reopen-key-approve-'.$attempt->id,
    ])->assertRedirect();

    $followUp = app(OpenProgramFollowUp::class)->handle(
        $attempt,
        $this->guidance,
        'BSN',
        'Standalone follow-up opening',
        'standalone-open-key-'.$attempt->id,
    );
    $originalKey = $followUp->idempotency_key;

    $this->actingAs($this->guidance)->post(route('consultation.decision', $attempt), [
        'decision_type' => 'refer',
        'target_program_code' => 'BSN',
        'referral_reason' => 'Consultation referral reopened',
        'idempotency_key' => 'reopen-key-referral-'.$attempt->id,
    ])->assertRedirect();
    $replayedFollowUp = app(OpenProgramFollowUp::class)->handle(

        $attempt,
        $this->guidance,
        'BSN',
        'Standalone follow-up opening',
        'standalone-open-key-'.$attempt->id,
    );

    expect($replayedFollowUp->id)->toBe($followUp->id)
        ->and($replayedFollowUp->idempotency_key)->toBe($originalKey)
        ->and($followUp->fresh()->idempotency_key)->toBe($originalKey)
        ->and($followUp->fresh()->status)->toBe(ProgramFollowUp::STATUS_OPEN)
        ->and($followUp->fresh()->version)->toBe(2)
        ->and(ProgramFollowUp::query()->where('admission_attempt_id', $attempt->id)->count())->toBe(1);
});

test('program pool rejects a program code owned by another institution', function (): void {
    $attempt = unifiedConsultationAttempt('unified-foreign-pool@example.test', 'ForeignPool');
    $this->actingAs($this->guidance)->post(route('consultation.decision', $attempt), [
        'decision_type' => 'approve',
        'default_program_code' => 'BSN',
        'pool_options' => [['program_code' => 'BSN', 'display_order' => 1, 'basis' => 'preference']],
        'idempotency_key' => 'foreign-pool-approve-'.$attempt->id,
    ])->assertRedirect();

    $foreignInstitution = Institution::query()->create([
        'code' => 'FOREIGN-POOL-'.$attempt->id,
        'name' => 'Foreign Pool Institution',
    ]);
    $foreignProgram = Program::query()->create([
        'institution_id' => $foreignInstitution->id,
        'department_id' => Program::query()->where('code', 'BSN')->value('department_id'),
        'code' => 'FOREIGN-POOL-PROGRAM-'.$attempt->id,
        'name' => 'Foreign Pool Program',
        'is_active' => true,
    ]);
    $poolCount = ProgramPool::query()->where('admission_attempt_id', $attempt->id)->count();

    expect(fn () => app(SetGuidanceProgramPool::class)->handle(
        $attempt,
        $this->guidance,
        $foreignProgram->code,
        [['program_code' => $foreignProgram->code, 'display_order' => 1, 'basis' => 'guidance_option']],
        null,
        'foreign-pool-set-'.$attempt->id,
    ))->toThrow(RuntimeException::class, 'Unknown program code:')
        ->and(ProgramPool::query()->where('admission_attempt_id', $attempt->id)->count())->toBe($poolCount);
});

test('program follow-up rejects a program code owned by another institution', function (): void {
    $attempt = unifiedConsultationAttempt('unified-foreign-follow-up@example.test', 'ForeignFollowUp');
    $this->actingAs($this->guidance)->post(route('consultation.decision', $attempt), [
        'decision_type' => 'approve',
        'default_program_code' => 'BSN',
        'pool_options' => [['program_code' => 'BSN', 'display_order' => 1, 'basis' => 'preference']],
        'idempotency_key' => 'foreign-follow-up-approve-'.$attempt->id,
    ])->assertRedirect();

    $foreignInstitution = Institution::query()->create([
        'code' => 'FOREIGN-FOLLOW-UP-'.$attempt->id,
        'name' => 'Foreign Follow-up Institution',
    ]);
    $foreignProgram = Program::query()->create([
        'institution_id' => $foreignInstitution->id,
        'department_id' => Program::query()->where('code', 'BSN')->value('department_id'),
        'code' => 'FOREIGN-FOLLOW-UP-PROGRAM-'.$attempt->id,
        'name' => 'Foreign Follow-up Program',
        'is_active' => true,
    ]);

    expect(fn () => app(OpenProgramFollowUp::class)->handle(
        $attempt,
        $this->guidance,
        $foreignProgram->code,
        'Foreign institution program must not be targeted.',
        'foreign-follow-up-open-'.$attempt->id,
    ))->toThrow(ModelNotFoundException::class);
});

test('typed consultation operations resolve to their attempt in an active practice set', function (): void {
    $institution = Institution::query()->where('code', 'SYN-INST-001')->firstOrFail();
    $cycle = AcademicCycle::query()->where('code', 'SYN-CYCLE-2026')->firstOrFail();
    $set = PracticeFixtureSet::query()->create([
        'label' => 'Consultation operation practice',
        'institution_id' => $institution->id,
        'academic_cycle_id' => $cycle->id,
        'scenario' => 'consultation',
        'purpose' => 'demo',
        'status' => 'active',
        'revision' => 1,
        'created_by_user_id' => $this->guidance->id,
        'actor_bindings' => ['guidance' => $this->guidance->id],
        'material_snapshot' => [],
        'credentials' => [],
    ]);
    $attempt = app(PracticeWorkspace::class)->run(
        $set,
        fn () => submitSyntheticApplicant('unified-practice-operation@example.test', 'PracticeOperation')['attempt'],
    );
    $commands = [
        'guidance.pool.set',
        'guidance.follow_up.open',
        'consultation.decision.record',
    ];

    app(PracticeWorkspace::class)->run($set, function () use ($attempt, $commands): void {
        foreach ($commands as $command) {
            Operation::query()->create([
                'idempotency_key' => $this->guidance->id.':'.$command.':attempt:'.$attempt->id.':'.Str::uuid(),
                'type' => $command,
                'status' => Operation::STATUS_SUCCEEDED,
                'attempts' => 0,
                'payload' => ['attempt_id' => (string) $attempt->id],
                'finished_at' => now(),
            ]);
        }

        expect(fn () => Operation::query()->create([
            'idempotency_key' => $this->guidance->id.':consultation.unknown:attempt:'.$attempt->id.':'.Str::uuid(),
            'type' => 'consultation.unknown',
            'status' => Operation::STATUS_SUCCEEDED,
            'attempts' => 0,
            'payload' => ['attempt_id' => (string) $attempt->id],
            'finished_at' => now(),
        ]))->toThrow(RuntimeException::class, 'Institutional configuration is read-only in a practice workspace.');
    });
});

test('program pool requires its advised default to be among validated local options before writing', function (): void {
    $attempt = unifiedConsultationAttempt('unified-default-option@example.test', 'DefaultOption');
    $this->actingAs($this->guidance)->post(route('consultation.decision', $attempt), [
        'decision_type' => 'approve',
        'default_program_code' => 'BSN',
        'pool_options' => [['program_code' => 'BSN', 'display_order' => 1, 'basis' => 'preference']],
        'idempotency_key' => 'default-option-approve-'.$attempt->id,
    ])->assertRedirect();
    $poolCount = ProgramPool::query()->where('admission_attempt_id', $attempt->id)->count();

    expect(fn () => app(SetGuidanceProgramPool::class)->handle(
        $attempt,
        $this->guidance,
        'BSPSY',
        [['program_code' => 'BSN', 'display_order' => 1, 'basis' => 'preference']],
        null,
        'default-outside-options-'.$attempt->id,
    ))->toThrow(RuntimeException::class, 'The advised default program is not in the option list.')
        ->and(ProgramPool::query()->where('admission_attempt_id', $attempt->id)->count())->toBe($poolCount);
});
