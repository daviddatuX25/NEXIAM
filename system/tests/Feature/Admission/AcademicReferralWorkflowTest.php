<?php

use App\Admission\Actions\SendAdmissionSupportMessage;
use App\Admission\Actions\SubmitAdmissionAttempt;
use App\Admission\Data\AdmissionIntakeData;
use App\Admission\Support\RecordThreadService;
use App\Authorization\CapabilityCatalog;
use App\Models\Admission\AdmissionAttempt;
use App\Models\Admission\ProgramFollowUp;
use App\Models\Admission\RecordThread;
use App\Models\Communications\PublicPolicy;
use App\Models\Foundation\AcademicCycle;
use App\Models\Foundation\ApplicationWindow;
use App\Models\Foundation\Department;
use App\Models\Foundation\Institution;
use App\Models\Foundation\Program;
use App\Models\Release\ResultDocument;
use App\Models\Release\ResultRelease;
use App\Models\StaffProfile;
use App\Models\User;
use App\Notifications\NotificationBusinessValidity;
use App\Notifications\NotificationDelivery;
use App\ProgramHead\Queries\ProgramHeadApplicantQueries;
use App\ProgramHead\Support\AcademicReferralNotice;
use App\Release\Actions\RecordConsultationDecision;
use App\Testing\PracticeFixtureSet;
use App\Testing\PracticeWorkspace;
use Database\Seeders\PublicIntakeConsentDemoSeeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;
use Tests\TestCase;

require_once __DIR__.'/../../Support/CaseMessagingHelpers.php';

function academicReferralFixture(TestCase $test, string $email): array
{
    [$attempt, $applicant, $guidance] = buildMessagingReadyAttempt($test, $email);
    $program = Program::query()->where('code', 'BSIT')->firstOrFail();
    $head = User::query()->where('email', 'program.head.bsit@nexiam.test')->firstOrFail();
    $head->staffProfile->update(['program_codes' => ['BSIT']]);
    ResultRelease::query()->where('admission_attempt_id', $attempt->id)->update(['result_printed_at' => now()]);
    Queue::fake();

    return [$attempt, $applicant, $guidance, $program, $head->fresh()];
}

test('referral commit notifies only current target program reviewers and command replay does not duplicate attention', function (): void {
    [$attempt, , $guidance, $program, $head] = academicReferralFixture($this, 'academic-review-notice@example.test');
    $dean = User::factory()->create();
    $department = Department::query()->create([
        'institution_id' => $attempt->institution_id, 'code' => 'ACADEMIC-REVIEW',
        'name' => 'Synthetic review college', 'dean_user_id' => $dean->id, 'is_active' => true,
    ]);
    StaffProfile::query()->create([
        'user_id' => $dean->id, 'institution_id' => $attempt->institution_id,
        'office' => 'program', 'program_codes' => [], 'department_id' => $department->id,
        'active_cycle_code' => $attempt->academicCycle->code, 'status' => StaffProfile::STATUS_ACTIVE,
    ]);
    $dean->givePermissionTo([CapabilityCatalog::PROGRAM_HEAD_VIEW, CapabilityCatalog::PROGRAM_HEAD_FOLLOW_UP_RESOLVE]);
    $program->forceFill(['department_id' => $department->id])->save();
    foreach (StaffProfile::query()->where('office', 'program')->whereNotIn('user_id', [$head->id, $dean->id])->get() as $profile) {
        $profile->update(['program_codes' => array_values(array_diff($profile->program_codes ?? [], ['BSIT']))]);
    }
    $key = (string) Str::uuid();
    $action = app(RecordConsultationDecision::class);
    $decision = $action->handle($attempt, $guidance, 'refer', 'PRIVATE COUNSELOR NOTE', $key, 'BSIT', 'Targeted academic review');
    $replay = $action->handle($attempt, $guidance, 'refer', 'PRIVATE COUNSELOR NOTE', $key, 'BSIT', 'Targeted academic review');
    $notices = NotificationDelivery::query()->where('event_type', AcademicReferralNotice::EVENT)->get();

    expect($replay->id)->toBe($decision->id)
        ->and($notices->pluck('recipient_id')->map(fn ($id) => (int) $id)->sort()->values()->all())
        ->toBe(collect([$head->id, $dean->id])->map(fn ($id) => (int) $id)->sort()->values()->all())
        ->and($notices->pluck('channel')->unique()->all())->toBe(['in_app']);
    foreach ($notices as $notice) {
        expect(json_encode($notice->payload))->not->toContain('PRIVATE COUNSELOR NOTE', 'Targeted academic review')
            ->and($notice->payload['action_url'])->toContain('program_code=BSIT', 'tab=record')
            ->and(app(NotificationBusinessValidity::class)->isCurrent($notice))->toBeTrue();
    }
    $head->staffProfile->update(['program_codes' => []]);
    expect(app(NotificationBusinessValidity::class)->isCurrent($notices->firstWhere('recipient_id', $head->id)))->toBeFalse();
    $followUp = ProgramFollowUp::query()->where('admission_attempt_id', $attempt->id)->firstOrFail();
    $followUp->update(['status' => ProgramFollowUp::STATUS_CLEARED_BY_PH, 'resolved_at' => now()]);
    expect(app(NotificationBusinessValidity::class)->isCurrent($notices->firstWhere('recipient_id', $dean->id)))->toBeFalse();
});

test('rolled back referral retains neither academic decision nor notification intent', function (): void {
    [$attempt, , $guidance] = academicReferralFixture($this, 'academic-review-rollback@example.test');
    try {
        DB::transaction(function () use ($attempt, $guidance): void {
            app(RecordConsultationDecision::class)->handle($attempt, $guidance, 'refer', null, (string) Str::uuid(), 'BSIT', 'Review requested');
            throw new RuntimeException('Synthetic caller rollback');
        });
    } catch (RuntimeException $error) {
        expect($error->getMessage())->toBe('Synthetic caller rollback');
    }
    expect(ProgramFollowUp::query()->where('admission_attempt_id', $attempt->id)->exists())->toBeFalse()
        ->and(NotificationDelivery::query()->where('event_type', AcademicReferralNotice::EVENT)->exists())->toBeFalse();
});

test('closed historical referral does not expose protected results or private counselor notes without release', function (): void {
    [$attempt, , $guidance, $program, $head] = academicReferralFixture($this, 'academic-review-release-gate@example.test');
    app(RecordConsultationDecision::class)->handle($attempt, $guidance, 'refer', 'PRIVATE FINDINGS', (string) Str::uuid(), 'BSIT', 'Shared referral context');
    ResultRelease::query()->where('admission_attempt_id', $attempt->id)->update(['is_current' => false]);
    ProgramFollowUp::query()->where('admission_attempt_id', $attempt->id)->firstOrFail()->forceFill([
        'status' => ProgramFollowUp::STATUS_CLEARED_BY_PH,
        'resolved_at' => now(),
    ])->save();
    $record = app(ProgramHeadApplicantQueries::class)->protectedRecord($attempt, $program, $head);
    expect($record['tier'])->toBe('A')
        ->and($record['result_released'])->toBeFalse()
        ->and($record['blocked_reason'])->toContain('not been released')
        ->and($record)->not->toHaveKeys(['score_profile', 'advising_outcome', 'follow_ups', 'result_sheet_url']);
    actingAsMessagingActor($this, $head)->get(route('program-head.applicants.result-sheet', ['program' => 'BSIT', 'attempt' => $attempt->id]))
        ->assertForbidden();
});
test('open targeted referral grants assigned reviewer protected academic access before result issuance', function (): void {
    [$attempt, , $guidance, $program, $head] = academicReferralFixture($this, 'academic-review-preissuance@example.test');
    app(RecordConsultationDecision::class)->handle(
        $attempt,
        $guidance,
        'refer',
        'PRIVATE FINDINGS',
        (string) Str::uuid(),
        'BSIT',
        'Shared referral context',
    );
    ResultRelease::query()->where('admission_attempt_id', $attempt->id)->update(['is_current' => false]);

    $document = ResultDocument::query()->whereKey($attempt->current_result_document_id)->firstOrFail();
    $snapshot = $document->snapshot_payload_json;
    $snapshot['scores'] = [[
        'max' => 2,
        'pct' => 82,
        'raw' => '',
        'domain' => 'Raw A metric',
        'rating' => '82',
        'area_code' => 'RAW_A',
        'converted' => '82',
    ]];
    $document->forceFill(['snapshot_payload_json' => $snapshot])->save();

    $record = app(ProgramHeadApplicantQueries::class)->protectedRecord($attempt, $program, $head);
    ProgramFollowUp::query()->where('admission_attempt_id', $attempt->id)->firstOrFail()
        ->forceFill(['status' => ProgramFollowUp::STATUS_APPEARANCE_REQUESTED])
        ->save();
    $appearanceRecord = app(ProgramHeadApplicantQueries::class)->protectedRecord($attempt, $program, $head);
    $internalThread = app(SendAdmissionSupportMessage::class)->openInternalThread(
        $attempt,
        (int) $program->id,
        'Referral review',
        'Review the assigned referral.',
        $guidance,
        (string) Str::uuid(),
    );
    expect($appearanceRecord['tier'])->toBe('B')
        ->and($appearanceRecord['result_released'])->toBeFalse();
    $visibleThreads = app(RecordThreadService::class)->forAttempt(
        $attempt,
        RecordThread::TYPE_INTERNAL_PH_GUIDANCE,
        $head,
    );

    expect($record['tier'])->toBe('B')
        ->and($record['access_reason'])->toBe('targeted_follow_up')
        ->and($record['result_released'])->toBeFalse()
        ->and($record['score_profile']['series'])->toBe([[
            'area_code' => 'RAW_A',
            'area_name' => 'Raw A metric',
            'score' => '82',
            'scale' => 'converted',
            'percentile' => null,
        ]])
        ->and(array_column($record['score_profile']['series'], 'area_code'))->toBe(['RAW_A'])
        ->and(array_column($record['score_profile']['series'], 'raw_score'))->toBe([])
        ->and($record)->not->toHaveKey('applicant_evidence')
        ->and(json_encode($record))->not->toContain('PRIVATE FINDINGS')
        ->and(collect($visibleThreads)->pluck('id')->all())->toContain($internalThread->id);

    $sheet = actingAsMessagingActor($this, $head)
        ->get(route('program-head.applicants.result-sheet', ['program' => $program->code, 'attempt' => $attempt->id]))
        ->assertOk()
        ->assertHeader('Content-Type', 'application/pdf');
    expect($sheet->headers->hasCacheControlDirective('private'))->toBeTrue()
        ->and($sheet->headers->hasCacheControlDirective('no-store'))->toBeTrue();

    actingAsMessagingActor($this, $head)
        ->get(route('program-head.applicants.result-sheet', ['program' => 'BSN', 'attempt' => $attempt->id]))
        ->assertForbidden();

    $head->staffProfile->update(['program_codes' => []]);
    actingAsMessagingActor($this, $head)
        ->get(route('program-head.applicants.result-sheet', ['program' => $program->code, 'attempt' => $attempt->id]))
        ->assertForbidden();
});
test('academic applicant entry retains exact program and exposes only explicitly shared case context', function (): void {
    [$attempt, , $guidance, $program, $head] = academicReferralFixture($this, 'academic-review-exact-context@example.test');
    app(RecordConsultationDecision::class)->handle($attempt, $guidance, 'refer', 'PRIVATE FINDINGS', (string) Str::uuid(), 'BSIT', 'Shared referral context');
    actingAsMessagingActor($this, $head)->get(route('program-head.applicants.show', ['program' => 'BSIT', 'attempt' => $attempt->id]))
        ->assertRedirect(route('admission-support.show', [
            'attempt' => $attempt->id, 'program_code' => 'BSIT', 'cycle_code' => $attempt->academicCycle->code, 'tab' => 'record',
        ]));
    actingAsMessagingActor($this, $head)->get(route('admission-support.show', [
        'attempt' => $attempt->id, 'program_code' => 'BSIT', 'cycle_code' => $attempt->academicCycle->code, 'tab' => 'record',
    ]))->assertOk()->assertInertia(fn ($page) => $page
        ->where('role', 'program_head')->where('ph_program.code', 'BSIT')
        ->where('ph_record.tier', 'B')->where('ph_record.follow_ups.0.reason', 'Shared referral context')
        ->where('ph_record.advising_outcome.decision_type', 'refer')
        ->missing('ph_record.advising_outcome.notes')->where('support', null));
    $foreign = Program::query()->where('code', 'BSN')->firstOrFail();
    actingAsMessagingActor($this, $head)->get(route('admission-support.show', [
        'attempt' => $attempt->id, 'program_code' => $foreign->code, 'cycle_code' => $attempt->academicCycle->code,
    ]))->assertNotFound();
});

test('Guidance loses practice thread visibility and reply authority when its fixture binding is removed', function (): void {
    seedE2Foundation();
    $guidance = User::query()->where('email', 'guidance.counselor@nexiam.test')->firstOrFail();
    $head = User::query()->where('email', 'program.head.bsit@nexiam.test')->firstOrFail();
    $cycle = AcademicCycle::query()->where('code', 'SYN-CYCLE-2026')->firstOrFail();
    $head->staffProfile->update(['program_codes' => ['BSIT']]);
    $program = Program::query()->where('code', 'BSIT')->firstOrFail();
    $institution = Institution::query()->whereKey($cycle->institution_id)->firstOrFail();
    $window = ApplicationWindow::query()->where('code', 'SYN-WINDOW-2026-A')->firstOrFail();
    (new PublicIntakeConsentDemoSeeder)->run();
    $policy = PublicPolicy::designatedIntake((int) $institution->id);
    $revision = $policy->publishedRevision;
    $set = PracticeFixtureSet::query()->create([
        'label' => 'Synthetic referral practice isolation',
        'institution_id' => $cycle->institution_id,
        'academic_cycle_id' => $cycle->id,
        'scenario' => 'application-review',
        'purpose' => 'demo',
        'created_by_user_id' => $guidance->id,
        'actor_bindings' => ['guidance' => $guidance->id, 'academic-leader' => $head->id],
        'material_snapshot' => [],
        'credentials' => [],
    ]);
    $attempt = app(PracticeWorkspace::class)->run($set, function () use ($institution, $cycle, $window, $policy, $revision, $program, $guidance): AdmissionAttempt {
        $submission = app(SubmitAdmissionAttempt::class)->handle(
            $institution, $cycle, $window,
            new AdmissionIntakeData(
                firstName: 'Test',
                lastName: 'Practice Referral',
                birthdate: '2001-01-01',
                sex: 'male',
                contactEmail: 'academic-review-practice-binding@example.test',
                applicantType: 'new',
                programPreferenceCodes: ['BSIT'],
                termsAccepted: true,
                publicPolicyId: (int) $policy->id,
                publicPolicyRevisionId: (int) $revision->id,
                publicPolicyRevisionDigest: $revision->contentDigest(),
            ),
            'test',
        );
        $attempt = $submission['attempt'];
        $attempt->forceFill(['status' => AdmissionAttempt::STATUS_ACCEPTED])->save();
        ProgramFollowUp::query()->create([
            'admission_attempt_id' => $attempt->id,
            'program_id' => $program->id,
            'opened_by_user_id' => $guidance->id,
            'status' => ProgramFollowUp::STATUS_OPEN,
            'reason' => 'Synthetic practice review prerequisite',
            'idempotency_key' => (string) Str::uuid(),
            'version' => 1,
            'opened_at' => now(),
        ]);

        return $attempt;
    });
    $thread = app(SendAdmissionSupportMessage::class)->openInternalThread(
        $attempt, $program->id, 'Practice referral', 'Synthetic practice discussion.', $guidance, (string) Str::uuid(),
    );
    $threads = app(RecordThreadService::class);
    expect($threads->canRead($thread, $guidance))->toBeTrue()
        ->and($threads->canSend($thread, $guidance))->toBeTrue();

    $set->update(['actor_bindings' => ['academic-leader' => $head->id]]);
    expect($threads->canRead($thread, $guidance))->toBeFalse()
        ->and($threads->canSend($thread, $guidance))->toBeFalse()
        ->and($threads->readableThreadsQuery($guidance)->whereKey($thread->id)->exists())->toBeFalse()
        ->and($threads->canRead($thread, $head))->toBeTrue();
});

test('preissuance referral never substitutes catalog scores when its current document is missing', function (): void {
    [$attempt, , $guidance, $program, $head] = academicReferralFixture($this, 'academic-review-missing-document@example.test');
    app(RecordConsultationDecision::class)->handle($attempt, $guidance, 'refer', null, (string) Str::uuid(), 'BSIT', 'Review current evidence only.');
    ResultRelease::query()->where('admission_attempt_id', $attempt->id)->update(['is_current' => false]);
    $attempt->forceFill(['current_result_document_id' => null])->save();

    $record = app(ProgramHeadApplicantQueries::class)->protectedRecord($attempt, $program, $head);
    expect($record['tier'])->toBe('B')
        ->and($record['result_released'])->toBeFalse()
        ->and($record['score_profile']['series'])->toBe([])
        ->and($record['score_profile']['freshness'])->toBe('unavailable');
});
