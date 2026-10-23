<?php

use App\Admission\Actions\AuthenticateApplicantWithAccessKey;
use App\Admission\Actions\SetGuidanceProgramPool;
use App\Models\Admission\AdmissionAttempt;
use App\Models\Admission\AdmissionSupportSnapshot;
use App\Models\Admission\ProgramPool;
use App\Models\Admission\ProgramPoolOption;
use App\Models\Admission\ProgramSelection;
use App\Models\Assessment\GradingSession;
use App\Models\Examination\ExamAssignment;
use App\Models\Examination\ExamSession;
use App\Models\Foundation\AcademicCycle;
use App\Models\Foundation\Program;
use App\Models\Foundation\ProgramQuota;
use App\Models\Foundation\Room;
use App\Models\Release\ConsultationDecision;
use App\Models\Release\ResultDocument;
use App\Models\Release\ResultRelease;
use App\Models\User;
use App\Release\Actions\ReleaseResult;

function applicantProgramChoicePool(object $test, string $email): array
{
    seedE2Foundation();
    $result = submitSyntheticApplicant($email, 'Program'.substr(md5($email), 0, 6));
    $applicant = app(AuthenticateApplicantWithAccessKey::class)->handle(
        $result['access_token'],
        '2001-01-01',
    );
    $attempt = $result['attempt'];
    $guidance = User::query()->where('email', 'guidance.counselor@nexiam.test')->firstOrFail();
    $cycle = AcademicCycle::query()->first();

    $attempt->update(['status' => AdmissionAttempt::STATUS_ACCEPTED]);

    foreach ([DEFAULT_PROGRAM, OTHER_PROGRAM] as $code) {
        $prog = Program::query()->where('code', $code)->firstOrFail();
        ProgramQuota::query()->updateOrCreate(
            ['program_id' => $prog->id, 'academic_cycle_id' => $cycle->id],
            ['slots' => 5],
        );
    }

    $room = Room::query()->where('code', 'RM-101')->firstOrFail();
    $examSession = ExamSession::query()->create([
        'academic_cycle_id' => $cycle->id,
        'room_id' => $room->id,
        'code' => 'EX-PC-'.substr(md5($email), 0, 12),
        'status' => ExamSession::STATUS_COMPLETED,
        'starts_at' => now()->subDays(5),
        'effective_end_at' => now()->subDays(5)->addHours(2),
        'closed_at' => now()->subDays(5),
    ]);
    ExamAssignment::query()->create([
        'exam_session_id' => $examSession->id,
        'admission_attempt_id' => $attempt->id,
        'status' => ExamAssignment::STATUS_ASSIGNED,
        'source' => ExamAssignment::SOURCE_STAFF_ASSIGNED,
    ]);
    GradingSession::query()->create([
        'exam_session_id' => $examSession->id,
        'status' => GradingSession::STATUS_FINALIZED,
        'opened_at' => now()->subDays(4),
        'finalized_at' => now()->subDays(3),
    ]);

    $resultDocument = ResultDocument::query()->create([
        'admission_attempt_id' => $attempt->id,
        'template_version' => 'test-template-v1',
        'checksum' => hash('sha256', 'program-choice-'.$email),
        'status' => ResultDocument::STATUS_READY,
        'version' => 1,
    ]);
    $attempt->forceFill(['current_result_document_id' => $resultDocument->id])->save();

    ResultRelease::query()->create([
        'admission_attempt_id' => $attempt->id,
        'result_document_id' => $resultDocument->id,
        'release_version' => 1,
        'mode' => ReleaseResult::MODE_ONLINE,
        'released_by_user_id' => $guidance->id,
        'is_current' => true,
        'released_at' => now(),
        'idempotency_key' => 'rel-choice-'.md5($email),
    ]);

    ConsultationDecision::query()->create([
        'admission_attempt_id' => $attempt->id,
        'counselor_user_id' => $guidance->id,
        'decision_type' => 'approve',
        'decided_at' => now(),
        'idempotency_key' => 'dec-choice-'.md5($email),
        'notes' => 'Qualified.',
    ]);

    app(SetGuidanceProgramPool::class)->handle(
        $attempt,
        $guidance,
        DEFAULT_PROGRAM,
        [
            ['program_code' => DEFAULT_PROGRAM, 'display_order' => 1, 'basis' => 'preference'],
            ['program_code' => OTHER_PROGRAM, 'display_order' => 2, 'basis' => 'guidance_option'],
        ],
        'Applicant-facing choice fixture.',
        'applicant-program-choice-pool-'.md5($email),
    );

    return [$attempt, $applicant, $guidance];
}

test('program choice returns a narrow applicant-safe support projection', function (): void {
    [$attempt, $applicant, $guidance] = applicantProgramChoicePool($this, 'program-choice-safe@example.test');
    ConsultationDecision::query()->create([
        'admission_attempt_id' => $attempt->id,
        'decision_type' => 'approve',
        'counselor_user_id' => $guidance->id,
        'notes' => 'PRIVATE_COUNSELOR_NOTE_SENTINEL',
        'version' => 99,
        'idempotency_key' => 'program-choice-private-note-'.$attempt->id,
    ]);
    AdmissionSupportSnapshot::query()->create([
        'admission_attempt_id' => $attempt->id,
        'academic_cycle_id' => $attempt->academic_cycle_id,
        'version' => 99,
        'engine_slot' => 'tabfm_pairwise',
        'engine_version' => 'private-snapshot-version',
        'input_digest' => hash('sha256', 'private-snapshot'),
        'payload' => [
            'status' => 'evaluated',
            'raw_omr_evidence' => 'RAW_OMR_EVIDENCE_SENTINEL',
            'evaluator_private_commentary' => 'PRIVATE_EVALUATOR_NOTE_SENTINEL',
            'recommendations' => [],
        ],
        'trigger_event' => 'test_snapshot',
        'created_at' => now(),
    ]);

    $this->actingAs($applicant)
        ->get(route('applicant.program-choice'))
        ->assertOk()
        ->assertInertia(function ($page) {
            $page->component('Applicant/ProgramChoice')
                ->where('page_state', 'ready')
                ->where('support.readiness.accepted', true)
                ->where('support.readiness.result_released', true)
                ->where('support.readiness.advised', true)
                ->where('support.program_pool.default.program_code', DEFAULT_PROGRAM)
                ->where('support.program_pool.default.program_name', Program::query()->where('code', DEFAULT_PROGRAM)->value('name'))
                ->where('support.readiness.selection.source', 'guidance_default')
                ->where('support.tabfm.is_admission_decision', false)
                ->where('support.tabfm.requires_counselor_interpretation', true);

            $support = $page->toArray()['props']['support'];
            expect(array_keys($support['readiness']))->toBe([
                'accepted', 'result_released', 'advised', 'selection', 'enrolling', 'enrolled',
            ])->and(array_keys($support))->toBe(['readiness', 'program_pool', 'actions', 'analysis', 'tabfm'])
                ->and($support['actions'])->toMatchArray([
                    'state' => 'pending_acknowledgement',
                    'can_choose' => true,
                    'has_recommendation' => true,
                ])
                ->and(json_encode($support, JSON_THROW_ON_ERROR))->not->toContain('PRIVATE_COUNSELOR_NOTE_SENTINEL')
                ->and(json_encode($support, JSON_THROW_ON_ERROR))->not->toContain('PRIVATE_EVALUATOR_NOTE_SENTINEL')
                ->and(json_encode($support, JSON_THROW_ON_ERROR))->not->toContain('RAW_OMR_EVIDENCE_SENTINEL')
                ->and(json_encode($support, JSON_THROW_ON_ERROR))->not->toContain('recorded_result')
                ->and(json_encode($support, JSON_THROW_ON_ERROR))->not->toContain('payload');

            return $page;
        });
});

test('no current recommendation yields an honest empty state with no applicant actions', function (): void {
    [$attempt, $applicant] = applicantProgramChoicePool($this, 'program-choice-no-pool@example.test');
    ProgramSelection::query()->where('admission_attempt_id', $attempt->id)->delete();
    ProgramPool::query()->where('admission_attempt_id', $attempt->id)->delete();
    $this->actingAs($applicant)
        ->get(route('applicant.program-choice'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('support.program_pool.default', null)
            ->where('support.program_pool.options', [])
            ->where('support.actions.state', 'awaiting_recommendation')
            ->where('support.actions.can_acknowledge', false)
            ->where('support.actions.can_choose', false)
            ->where('support.actions.has_recommendation', false));
});

test('confirmed sole Guidance option remains distinct from recommendation and has no chooser', function (): void {
    [$attempt, $applicant] = applicantProgramChoicePool($this, 'program-choice-single-confirmed@example.test');
    $defaultProgramId = (int) Program::query()->where('code', DEFAULT_PROGRAM)->value('id');
    $pool = ProgramPool::currentFor($attempt->id);
    ProgramPoolOption::query()
        ->where('program_pool_id', $pool->id)
        ->where('program_id', '!=', $defaultProgramId)
        ->delete();

    $this->actingAs($applicant)
        ->post(route('applicant.program-choice.acknowledge'), [
            'idempotency_key' => 'program-choice-confirm-single-'.$attempt->id,
        ])
        ->assertRedirect();

    $this->get(route('applicant.program-choice'))
        ->assertInertia(fn ($page) => $page
            ->where('support.readiness.selection.source', 'guidance_default')
            ->where('support.readiness.selection.acknowledged', true)
            ->where('support.actions.state', 'confirmed')
            ->where('support.actions.can_acknowledge', false)
            ->where('support.actions.can_choose', false)
            ->where('support.program_pool.options', []));
});

test('program choice actions remain unavailable before result release', function (): void {
    [$attempt, $applicant] = applicantProgramChoicePool($this, 'program-choice-prerequisites@example.test');
    ResultRelease::query()->where('admission_attempt_id', $attempt->id)->delete();

    $this->actingAs($applicant)
        ->get(route('applicant.program-choice'))
        ->assertInertia(fn ($page) => $page
            ->where('support.actions.state', 'awaiting_release')
            ->where('support.actions.can_acknowledge', false)
            ->where('support.actions.can_choose', false)
            ->where('support.program_pool.options', []));
});

test('latest referral decision leaves program choice read-only', function (): void {
    [$attempt, $applicant, $guidance] = applicantProgramChoicePool($this, 'program-choice-latest-referral@example.test');
    ConsultationDecision::query()->create([
        'admission_attempt_id' => $attempt->id,
        'decision_type' => 'refer',
        'counselor_user_id' => $guidance->id,
        'notes' => 'Private referral note.',
        'version' => 100,
        'idempotency_key' => 'program-choice-latest-referral-'.$attempt->id,
    ]);

    $this->actingAs($applicant)
        ->get(route('applicant.program-choice'))
        ->assertInertia(fn ($page) => $page
            ->where('support.actions.state', 'awaiting_advice')
            ->where('support.actions.can_acknowledge', false)
            ->where('support.actions.can_choose', false)
            ->where('support.program_pool.options', []));
});

test('held F2F result hides TabFM signals until the encounter is advised', function (): void {
    [$attempt, $applicant] = applicantProgramChoicePool($this, 'program-choice-held-f2f@example.test');
    ResultRelease::query()
        ->where('admission_attempt_id', $attempt->id)
        ->where('is_current', true)
        ->update(['mode' => ReleaseResult::MODE_F2F]);
    ConsultationDecision::query()
        ->where('admission_attempt_id', $attempt->id)
        ->delete();

    $this->actingAs($applicant)
        ->get(route('applicant.program-choice'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('support.readiness.result_released', true)
            ->where('support.readiness.advised', false)
            ->where('support.tabfm.status', 'not_ready')
            ->where('support.tabfm.matches', [])
        );
});

test('program choice acknowledge and select endpoints preserve idempotent action receipts', function (): void {
    [$attempt, $applicant] = applicantProgramChoicePool($this, 'program-choice-actions@example.test');

    $this->actingAs($applicant);
    $acknowledgePayload = ['idempotency_key' => 'http-choice-ack-'.md5((string) $attempt->id)];
    $this->post(route('applicant.program-choice.acknowledge'), $acknowledgePayload)->assertRedirect();
    $this->post(route('applicant.program-choice.acknowledge'), $acknowledgePayload)->assertRedirect();

    $selectionPayload = [
        'idempotency_key' => 'http-choice-select-'.md5((string) $attempt->id),
        'program_code' => OTHER_PROGRAM,
    ];
    $this->post(route('applicant.program-choice.select'), $selectionPayload)->assertRedirect();
    $this->post(route('applicant.program-choice.select'), $selectionPayload)->assertRedirect();

    $selections = ProgramSelection::query()
        ->where('admission_attempt_id', $attempt->id)
        ->orderBy('id')
        ->get();
    expect($selections)->toHaveCount(2)
        ->and($selections[0]->status)->toBe(ProgramSelection::STATUS_SUPERSEDED)
        ->and($selections[1]->source)->toBe(ProgramSelection::SOURCE_STUDENT_SELF)
        ->and($selections[1]->program_id)->toBe(Program::query()->where('code', OTHER_PROGRAM)->value('id'));
});

test('acknowledging a recommendation preserves real alternate choices until the existing lock', function (): void {
    [$attempt, $applicant] = applicantProgramChoicePool($this, 'choice-confirmed-alternates@example.test');
    $this->actingAs($applicant)
        ->post(route('applicant.program-choice.acknowledge'), ['idempotency_key' => 'choice-confirmed-alternates-ack'])
        ->assertRedirect();

    $this->get(route('applicant.program-choice'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('support.actions.state', 'confirmed')
            ->where('support.actions.can_acknowledge', false)
            ->where('support.actions.can_choose', true)
            ->where('support.actions.can_cancel', true)
            ->where('support.program_pool.options.0.program_code', OTHER_PROGRAM));
});

test('an active Guidance default without applicant acknowledgement is not called confirmed', function (): void {
    [$attempt, $applicant] = applicantProgramChoicePool($this, 'choice-active-unacknowledged@example.test');
    ProgramSelection::query()->where('admission_attempt_id', $attempt->id)
        ->update(['status' => ProgramSelection::STATUS_ACTIVE, 'applicant_acknowledged_at' => null]);

    $this->actingAs($applicant)->get(route('applicant.program-choice'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('support.actions.state', 'pending_acknowledgement')
            ->where('support.actions.can_acknowledge', true)
            ->where('support.readiness.selection.acknowledged', false));
});

test('program choice projects subtle quota metrics for default recommendation and alternatives', function (): void {
    [$attempt, $applicant] = applicantProgramChoicePool($this, 'choice-quota-projection@example.test');
    $defaultProg = Program::query()->where('code', DEFAULT_PROGRAM)->firstOrFail();
    $otherProg = Program::query()->where('code', OTHER_PROGRAM)->firstOrFail();

    ProgramQuota::query()->updateOrCreate(
        ['academic_cycle_id' => $attempt->academic_cycle_id, 'program_id' => $defaultProg->id],
        ['slots' => 100, 'updated_by_user_id' => 1],
    );
    ProgramQuota::query()->updateOrCreate(
        ['academic_cycle_id' => $attempt->academic_cycle_id, 'program_id' => $otherProg->id],
        ['slots' => 50, 'updated_by_user_id' => 1],
    );

    $this->actingAs($applicant)->get(route('applicant.program-choice'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('support.program_pool.default.quota.configured', true)
            ->where('support.program_pool.default.quota.capacity', 100)
            ->where('support.program_pool.default.quota.committed', 1)
            ->where('support.program_pool.default.quota.remaining', 99)
            ->where('support.program_pool.options.0.quota.configured', true)
            ->where('support.program_pool.options.0.quota.capacity', 50)
            ->where('support.program_pool.options.0.quota.committed', 0)
            ->where('support.program_pool.options.0.quota.remaining', 50)
            ->has('support.program_pool.quota_as_of'));
});
