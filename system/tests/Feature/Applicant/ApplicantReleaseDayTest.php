<?php

use App\Admission\Actions\AuthenticateApplicantWithAccessKey;
use App\Admission\Actions\SetGuidanceProgramPool;
use App\Models\Admission\AdmissionAttempt;
use App\Models\Admission\EnrollmentStamp;
use App\Models\Admission\ProgramFollowUp;
use App\Models\Assessment\GradingSession;
use App\Models\Consultation\ConsultationAssignment;
use App\Models\Consultation\ConsultationEncounter;
use App\Models\Consultation\ConsultationSession;
use App\Models\Examination\ExamAssignment;
use App\Models\Examination\ExamSession;
use App\Models\Foundation\AcademicCycle;
use App\Models\Foundation\Program;
use App\Models\Foundation\ProgramQuota;
use App\Models\Foundation\Room;
use App\Models\Release\ConsultationDecision;
use App\Models\Release\ResultDocument;
use App\Models\Release\ResultRelease;
use App\Models\ResultSheet\ResultSheetTemplate;
use App\Models\User;
use App\Release\Actions\ReleaseResult;
use App\Support\SensitiveAssetStore;
use Illuminate\Support\Facades\Storage;

function releaseDayScenario(string $email, string $mode = ReleaseResult::MODE_ONLINE): array
{
    seedE2Foundation();
    $application = submitSyntheticApplicant($email, 'Release'.substr(md5($email), 0, 6));
    $applicant = app(AuthenticateApplicantWithAccessKey::class)->handle($application['access_token'], '2001-01-01');
    $attempt = $application['attempt'];
    $guidance = User::query()->where('email', 'guidance.counselor@nexiam.test')->firstOrFail();
    $document = ResultDocument::query()->create([
        'admission_attempt_id' => $attempt->id,
        'version' => 1,
        'status' => ResultDocument::STATUS_READY,
        'template_version' => 'release-day-test-v1',
        'checksum' => hash('sha256', $email),
    ]);
    Storage::fake('sensitive');
    $pdfBytes = "%PDF-1.7\nApplicant result fixture\n%%EOF\n";
    $pdfPath = 'results/'.$attempt->id.'/result-'.$document->id.'.pdf';
    app(SensitiveAssetStore::class)->store($pdfBytes, $pdfPath);
    $document->update(['artifact_manifest' => ['pdf' => [
        'status' => 'ready',
        'path' => $pdfPath,
        'size' => strlen($pdfBytes),
        'sha256' => hash('sha256', $pdfBytes),
    ]]]);
    $attempt->forceFill(['current_result_document_id' => $document->id])->save();
    ResultRelease::query()->create([
        'admission_attempt_id' => $attempt->id,
        'result_document_id' => $document->id,
        'release_version' => 1,
        'mode' => $mode,
        'released_by_user_id' => $guidance->id,
        'is_current' => true,
        'released_at' => now(),
        'idempotency_key' => 'release-day-'.md5($email),
    ]);

    return [$attempt, $applicant, $guidance];
}

function addReleaseDayAppointment($attempt, string $status = ConsultationAssignment::STATUS_ASSIGNED): ConsultationAssignment
{
    $cycle = AcademicCycle::query()->findOrFail($attempt->academic_cycle_id);
    $room = Room::query()->where('code', 'RM-101')->firstOrFail();
    $session = ConsultationSession::query()->create([
        'academic_cycle_id' => $cycle->id,
        'room_id' => $room->id,
        'code' => 'RD-'.substr(md5((string) $attempt->id.microtime()), 0, 10),
        'status' => ConsultationSession::STATUS_COMPLETED,
        'booking_mode' => ConsultationSession::BOOKING_STAFF_ONLY,
        'scheduled_at' => now()->addDays(3)->setTime(9, 30),
        'completed_at' => now(),
        'version' => 1,
    ]);

    return ConsultationAssignment::query()->create([
        'consultation_session_id' => $session->id,
        'admission_attempt_id' => $attempt->id,
        'status' => $status,
        'source' => ConsultationAssignment::SOURCE_STAFF_ASSIGNED,
        'consulted_at' => $status === ConsultationAssignment::STATUS_CONSULTED ? now()->subDay() : null,
    ]);
}

function addReleaseDayDecision($attempt, int $counselorId, string $decisionType, string $key): ConsultationDecision
{
    return ConsultationDecision::query()->create([
        'admission_attempt_id' => $attempt->id,
        'counselor_user_id' => $counselorId,
        'decision_type' => $decisionType,
        'idempotency_key' => $key,
        'notes' => 'Private staff note that must not be disclosed.',
    ]);
}

test('scheduled session is not encounter completion and a decision supplies its timestamp', function (): void {
    [$attempt, $applicant, $guidance] = releaseDayScenario('release-day-scheduled@example.test');
    addReleaseDayAppointment($attempt);

    $this->actingAs($applicant)
        ->get(route('applicant.release-day'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Applicant/ReleaseDay')
            ->where('release_day.appointment.status', ConsultationAssignment::STATUS_ASSIGNED)
            ->where('release_day.completion.completed', false)
            ->where('release_day.completion.completed_at', null));

    $decision = addReleaseDayDecision($attempt, $guidance->id, 'approve', 'release-day-approved');
    $this->actingAs($applicant)
        ->get(route('applicant.release-day'))
        ->assertInertia(fn ($page) => $page
            ->where('release_day.completion.completed', true)
            ->where('release_day.completion.completed_at', $decision->created_at->toIso8601String()));
});

test('latest deferred decision does not preserve completion from an older approval', function (): void {
    [$attempt, $applicant, $guidance] = releaseDayScenario('release-day-deferred@example.test');
    addReleaseDayDecision($attempt, $guidance->id, 'approve', 'release-day-first-approval');
    $deferred = addReleaseDayDecision($attempt, $guidance->id, 'defer', 'release-day-latest-defer');
    $deferred->created_at = now()->addSecond();
    $deferred->save();

    $this->actingAs($applicant)
        ->get(route('applicant.release-day'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('release_day.completion.completed', false)
            ->where('release_day.completion.completed_at', null)
            ->where('release_day.decision.state', 'deferred')
            ->where('release_day.decision.recorded_at', $deferred->created_at->toIso8601String())
            ->where('release_day.follow_ups', []));
});

test('online result is available without attendance or an appointment', function (): void {
    [, $applicant] = releaseDayScenario('release-day-online-no-attendance@example.test');

    $this->actingAs($applicant)
        ->get(route('applicant.release-day'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('release_day.appointment', null)
            ->where('release_day.completion.completed', false)
            ->where('release_day.result.available', true)
            ->where('release_day.result.review_available', true)
            ->where('release_day.result.href', route('applicant.results.show')));
});

test('F2F result remains held before the latest encounter decision completes', function (): void {
    [, $applicant] = releaseDayScenario('release-day-held-f2f@example.test', ReleaseResult::MODE_F2F);

    $this->actingAs($applicant)
        ->get(route('applicant.release-day'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('release_day.result.available', false)
            ->where('release_day.result.review_available', true)
            ->where('release_day.result.href', route('applicant.results.show'))
            ->where('release_day.program_choice.available', true)
            ->where('release_day.program_choice.href', route('applicant.program-choice')));
});

test('program choice follows its own readiness and does not require an appointment', function (): void {
    [$attempt, $applicant, $guidance] = releaseDayScenario('release-day-choice-independent@example.test');
    addReleaseDayDecision($attempt, $guidance->id, 'modify', 'release-day-choice-decision');

    $this->actingAs($applicant)
        ->get(route('applicant.release-day'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('release_day.appointment', null)
            ->where('release_day.result.available', true)
            ->where('release_day.program_choice.available', true)
            ->where('release_day.program_choice.href', route('applicant.program-choice')));
});
test('program choice remains viewable after enrollment begins', function (): void {
    [$attempt, $applicant, $guidance] = releaseDayScenario('release-day-choice-enrolling@example.test');
    EnrollmentStamp::query()->create([
        'admission_attempt_id' => $attempt->id,
        'stamp_type' => EnrollmentStamp::STAMP_ENROLLED,
        'stamped_by_user_id' => $guidance->id,
        'reason' => 'Applicant portal read-only visibility fixture',
        'version' => 1,
    ]);

    $this->actingAs($applicant)
        ->get(route('applicant.release-day'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('release_day.program_choice.available', true)
            ->where('release_day.program_choice.href', route('applicant.program-choice')));
});

test('a historical consulted assignment remains the latest appointment', function (): void {
    [$attempt, $applicant] = releaseDayScenario('release-day-historical@example.test');
    addReleaseDayAppointment($attempt, ConsultationAssignment::STATUS_CONSULTED);

    $this->actingAs($applicant)
        ->get(route('applicant.release-day'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('release_day.appointment.status', ConsultationAssignment::STATUS_CONSULTED)
            ->where('release_day.appointment.scheduled_at', fn ($value) => is_string($value)));
});

test('online invitation is a redacted join projection and never exposes call secrets', function (): void {
    [$attempt, $applicant, $guidance] = releaseDayScenario('release-day-online-invitation@example.test');
    $encounter = ConsultationEncounter::query()->create([
        'institution_id' => $attempt->institution_id,
        'admission_attempt_id' => $attempt->id,
        'counselor_user_id' => $guidance->id,
        'consultation_policy_version' => 0,
        'medium' => ConsultationEncounter::MEDIUM_ONLINE,
        'booking_path' => ConsultationEncounter::BOOKING_PATH_DIRECT,
        'status' => ConsultationEncounter::STATUS_IN_PROGRESS,
        'room_name' => 'private-room-name',
        'access_starts_at' => now()->subMinutes(10),
        'access_ends_at' => now()->subMinute(),
        'idempotency_key' => 'private-encounter-key',
    ]);

    $response = $this->actingAs($applicant)->get(route('applicant.release-day'));
    $response->assertOk()->assertInertia(fn ($page) => $page
        ->where('release_day.online_encounter.status', ConsultationEncounter::STATUS_IN_PROGRESS)
        ->where('release_day.online_encounter.access_starts_at', $encounter->access_starts_at->toIso8601String())
        ->where('release_day.online_encounter.href', null)
        ->where('release_day.online_encounter.unavailable_reason', fn ($value) => is_string($value) && $value !== '')
        ->missing('release_day.online_encounter.room_name')
        ->missing('release_day.online_encounter.idempotency_key')
        ->missing('release_day.online_encounter.counselor_user_id')
        ->missing('release_day.online_encounter.consultation_policy_version'));
});
test('empty account has no release-day projection', function (): void {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get(route('applicant.release-day'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('page_state', 'empty')
            ->where('release_day', null));
});

test('failed current PDF remains reviewable but is not available for download', function (): void {
    [$attempt, $applicant] = releaseDayScenario('release-day-failed-pdf@example.test');
    ResultDocument::query()->where('admission_attempt_id', $attempt->id)->firstOrFail()
        ->update(['artifact_manifest' => ['pdf' => ['status' => 'failed']]]);

    $this->actingAs($applicant)
        ->get(route('applicant.release-day'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('release_day.result.available', false)
            ->where('release_day.result.review_available', true)
            ->where('release_day.result.href', route('applicant.results.show'))
            ->where('release_day.result.unavailable_reason', 'Your result is currently unavailable.'));
});

test('retracted result remains reviewable but is not available for download', function (): void {
    [$attempt, $applicant] = releaseDayScenario('release-day-retracted@example.test');
    ResultRelease::query()->where('admission_attempt_id', $attempt->id)->firstOrFail()->forceFill([
        'is_current' => false,
        'unreleased_at' => now(),
    ])->save();

    $this->actingAs($applicant)
        ->get(route('applicant.release-day'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('release_day.result.available', false)
            ->where('release_day.result.review_available', true)
            ->where('release_day.result.href', route('applicant.results.show')));
});

test('latest referred decision does not preserve completion from an older approval', function (): void {
    [$attempt, $applicant, $guidance] = releaseDayScenario('release-day-referred@example.test');
    addReleaseDayDecision($attempt, $guidance->id, 'approve', 'release-day-first-approval-referral');
    $referred = addReleaseDayDecision($attempt, $guidance->id, 'refer', 'release-day-latest-referral');
    $referred->created_at = now()->addSecond();
    $referred->save();

    $this->actingAs($applicant)
        ->get(route('applicant.release-day'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('release_day.completion.completed', false)
            ->where('release_day.completion.completed_at', null)
            ->where('release_day.decision.state', 'referred')
            ->where('release_day.follow_ups', []));
});
test('preparing PDF keeps My Results review link without claiming availability', function (): void {
    [$attempt, $applicant, $guidance] = releaseDayScenario('release-day-preparing-pdf@example.test', ReleaseResult::MODE_F2F);
    ResultRelease::query()->where('admission_attempt_id', $attempt->id)->delete();
    $decision = addReleaseDayDecision($attempt, $guidance->id, 'approve', 'release-day-preparing-decision');
    $document = ResultDocument::query()->where('admission_attempt_id', $attempt->id)->firstOrFail();
    ConsultationEncounter::query()->create([
        'institution_id' => $attempt->institution_id,
        'admission_attempt_id' => $attempt->id,
        'result_document_id' => $document->id,
        'result_document_version' => $document->version,
        'counselor_user_id' => $guidance->id,
        'medium' => ConsultationEncounter::MEDIUM_F2F,
        'booking_path' => ConsultationEncounter::BOOKING_PATH_DIRECT,
        'status' => ConsultationEncounter::STATUS_OUTCOME_RECORDED,
        'outcome_recorded_at' => $decision->created_at,
        'closed_at' => now(),
        'idempotency_key' => 'release-day-preparing-encounter',
    ]);
    $sourceBytes = '<html><body>Release Day result source</body></html>';
    $document->forceFill([
        'snapshot_payload_json' => [
            '_template' => ['mode' => ResultSheetTemplate::MODE_HTML],
            '_template_source' => [
                'bytes_base64' => base64_encode($sourceBytes),
                'sha256' => hash('sha256', $sourceBytes),
            ],
        ],
        'artifact_manifest' => ['pdf' => ['status' => 'pending']],
    ])->save();

    $this->actingAs($applicant)
        ->get(route('applicant.release-day'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('release_day.result.available', false)
            ->where('release_day.result.review_available', true)
            ->where('release_day.result.href', route('applicant.results.show'))
            ->where('release_day.result.unavailable_reason', 'Your official PDF is being prepared.'));
});
test('online invitation projection is limited to the signed-in applicant attempt', function (): void {
    [$ownedAttempt, $owner, $guidance] = releaseDayScenario('release-day-scope-owner@example.test');
    $otherApplication = submitSyntheticApplicant('release-day-scope-other@example.test', 'ReleaseOther');
    $otherApplicant = app(AuthenticateApplicantWithAccessKey::class)->handle($otherApplication['access_token'], '2001-01-01');
    $encounter = ConsultationEncounter::query()->create([
        'institution_id' => $ownedAttempt->institution_id,
        'admission_attempt_id' => $ownedAttempt->id,
        'counselor_user_id' => $guidance->id,
        'consultation_policy_version' => 0,
        'medium' => ConsultationEncounter::MEDIUM_ONLINE,
        'booking_path' => ConsultationEncounter::BOOKING_PATH_DIRECT,
        'status' => ConsultationEncounter::STATUS_INVITED,
        'room_name' => 'private-owner-room',
        'access_starts_at' => now()->subMinute(),
        'access_ends_at' => now()->addMinutes(10),
        'idempotency_key' => 'release-day-scope-owner-encounter',
    ]);

    $this->actingAs($otherApplicant)
        ->get(route('applicant.release-day'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('release_day.online_encounter', null)
            ->where('release_day.appointment', null)
            ->missing('release_day.online_encounter.href')
            ->missing('release_day.online_encounter.room_name')
            ->missing('release_day.online_encounter.idempotency_key'));
});
test('follow-up outcome separates asynchronous review and appearance without exposing reasons', function (): void {
    [$attempt, $applicant, $guidance] = releaseDayScenario('release-day-follow-up-safe@example.test');
    $program = Program::query()
        ->where('institution_id', $attempt->institution_id)
        ->firstOrFail();
    $followUp = ProgramFollowUp::query()->create([
        'admission_attempt_id' => $attempt->id,
        'program_id' => $program->id,
        'opened_by_user_id' => $guidance->id,
        'status' => ProgramFollowUp::STATUS_OPEN,
        'reason' => 'private internal follow-up reason',
        'idempotency_key' => 'release-day-safe-follow-up',
    ]);

    $this->actingAs($applicant)
        ->get(route('applicant.release-day'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('release_day.follow_ups.0.status', 'review_pending')
            ->where('release_day.follow_ups.0.instruction_recorded', false)
            ->where('release_day.follow_ups.0.office', null)
            ->missing('release_day.follow_ups.0.reason'));

    $followUp->update(['status' => ProgramFollowUp::STATUS_APPEARANCE_REQUESTED]);

    $this->actingAs($applicant)
        ->get(route('applicant.release-day'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('release_day.follow_ups.0.status', 'appearance_requested')
            ->where('release_day.follow_ups.0.program', $program->name)
            ->where('release_day.follow_ups.0.instruction_recorded', false)
            ->missing('release_day.follow_ups.0.reason')
            ->missing('release_day.follow_ups.0.resolution_reason'));
});
test('release day includes every open follow-up with appearances first even when another program is selected', function (): void {
    [$attempt, $applicant, $guidance] = releaseDayScenario('release-day-all-follow-ups@example.test');
    $programs = Program::query()
        ->where('institution_id', $attempt->institution_id)
        ->orderBy('id')
        ->limit(2)
        ->get();
    expect($programs)->toHaveCount(2);
    $selectedProgram = $programs[0];
    $requiredProgram = $programs[1];
    $attempt->update(['status' => AdmissionAttempt::STATUS_ACCEPTED]);
    addReleaseDayDecision($attempt, $guidance->id, 'approve', 'release-day-all-follow-ups-decision');
    foreach ([$selectedProgram, $requiredProgram] as $program) {
        ProgramQuota::query()->updateOrCreate(
            ['program_id' => $program->id, 'academic_cycle_id' => $attempt->academic_cycle_id],
            ['slots' => 2],
        );
    }
    $exam = ExamSession::query()->create([
        'academic_cycle_id' => $attempt->academic_cycle_id,
        'room_id' => Room::query()->where('institution_id', $attempt->institution_id)->firstOrFail()->id,
        'code' => 'EX-REQUIRED-FOLLOW-UPS',
        'status' => 'completed',
        'starts_at' => now()->subDays(2),
        'effective_end_at' => now()->subDays(2)->addHour(),
    ]);
    ExamAssignment::query()->create([
        'exam_session_id' => $exam->id,
        'admission_attempt_id' => $attempt->id,
        'status' => 'assigned',
    ]);
    GradingSession::query()->create([
        'exam_session_id' => $exam->id,
        'status' => 'finalized',
        'opened_at' => now()->subDay(),
        'finalized_at' => now()->subDay(),
    ]);
    app(SetGuidanceProgramPool::class)->handle($attempt->fresh(), $guidance, $selectedProgram->code, [
        ['program_code' => $selectedProgram->code, 'display_order' => 1, 'basis' => 'preference'],
        ['program_code' => $requiredProgram->code, 'display_order' => 2, 'basis' => 'preference'],
    ], null, 'release-day-all-follow-ups-pool');
    foreach ([
        [$selectedProgram, ProgramFollowUp::STATUS_OPEN, 'selected-program-private-reason'],
        [$requiredProgram, ProgramFollowUp::STATUS_APPEARANCE_REQUESTED, 'required-program-private-reason'],
    ] as [$program, $status, $reason]) {
        ProgramFollowUp::query()->create([
            'admission_attempt_id' => $attempt->id,
            'program_id' => $program->id,
            'opened_by_user_id' => $guidance->id,
            'status' => $status,
            'reason' => $reason,
            'idempotency_key' => 'release-day-open-follow-up-'.$program->id,
        ]);
    }

    $this->actingAs($applicant)
        ->get(route('applicant.release-day'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('release_day.follow_ups.0.status', 'appearance_requested')
            ->where('release_day.follow_ups.0.program', $requiredProgram->name)
            ->where('release_day.follow_ups.1.status', 'review_pending')
            ->where('release_day.follow_ups.1.program', $selectedProgram->name)
            ->missing('release_day.follow_ups.0.reason')
            ->missing('release_day.follow_ups.1.reason'));
});
