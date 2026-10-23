<?php

declare(strict_types=1);

use App\Admission\Actions\AuthenticateApplicantWithAccessKey;
use App\Models\Admission\AdmissionAttempt;
use App\Models\Admission\ApplicantIdentity;
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
use App\Notifications\NotificationBusinessValidity;
use App\Notifications\NotificationDelivery;
use App\Notifications\NotificationProjection;
use App\Release\Actions\RecordConsultationDecision;
use App\Release\Support\ReleaseDayDecisionNotice;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

beforeEach(function (): void {
    seedE2Foundation();
    Storage::fake('sensitive');

    $cycle = AcademicCycle::query()->where('code', 'SYN-CYCLE-2026')->firstOrFail();
    foreach (['BSN', 'BSPSY'] as $programCode) {
        $program = Program::query()->where('code', $programCode)->firstOrFail();
        ProgramQuota::query()->updateOrCreate(
            ['program_id' => $program->id, 'academic_cycle_id' => $cycle->id],
            ['slots' => 50],
        );
    }
});

/** @return array{attempt: AdmissionAttempt, identity: ApplicantIdentity, guidance: User, access_key: string, document: ResultDocument} */
function releaseDayDecisionNoticeFixture(string $suffix): array
{
    $submitted = submitSyntheticApplicant('release-day-notice-'.$suffix.'@example.test', 'ReleaseNotice'.substr(md5($suffix), 0, 8));
    $attempt = $submitted['attempt'];
    $registrar = User::query()->where('email', 'registrar.clerk@nexiam.test')->firstOrFail();
    test()->actingAs($registrar)
        ->post(route('admissions.accept', $attempt), ['expected_version' => 1])
        ->assertRedirect();

    $guidance = User::query()->where('email', 'guidance.counselor@nexiam.test')->firstOrFail();
    $cycle = AcademicCycle::query()->where('code', 'SYN-CYCLE-2026')->firstOrFail();
    $room = Room::query()->where('code', 'RM-101')->firstOrFail();
    $examSession = ExamSession::query()->create([
        'academic_cycle_id' => $cycle->id,
        'room_id' => $room->id,
        'code' => 'EX-NOTICE-'.Str::uuid()->toString(),
        'status' => ExamSession::STATUS_COMPLETED,
        'starts_at' => now()->subDays(5),
        'effective_end_at' => now()->subDays(5)->addHours(2),
        'closed_at' => now()->subDays(5),
    ]);
    GradingSession::query()->create([
        'exam_session_id' => $examSession->id,
        'status' => GradingSession::STATUS_FINALIZED,
        'opened_at' => now()->subDays(4),
        'finalized_at' => now()->subDays(3),
    ]);
    ExamAssignment::query()->create([
        'exam_session_id' => $examSession->id,
        'admission_attempt_id' => $attempt->id,
        'status' => ExamAssignment::STATUS_ASSIGNED,
        'source' => ExamAssignment::SOURCE_STAFF_ASSIGNED,
    ]);
    $document = ResultDocument::query()->create([
        'admission_attempt_id' => $attempt->id,
        'template_version' => 'release-day-notice-test',
        'checksum' => hash('sha256', 'release-day-notice-'.$attempt->id),
        'status' => ResultDocument::STATUS_READY,
        'storage_path' => 'results/release-day-notice-'.$attempt->id.'.html',
        'preview_path' => 'results/release-day-notice-'.$attempt->id.'.html',
    ]);
    ResultRelease::query()->create([
        'admission_attempt_id' => $attempt->id,
        'result_document_id' => $document->id,
        'released_by_user_id' => $guidance->id,
        'release_version' => 1,
        'is_current' => true,
        'released_at' => now(),
        'idempotency_key' => 'release-day-notice-print-'.$attempt->id,
        'mode' => 'f2f',
    ])->forceFill(['result_printed_at' => now()])->save();

    $attempt->refresh();

    return [
        'attempt' => $attempt,
        'identity' => $attempt->identity,
        'guidance' => $guidance,
        'access_key' => $submitted['access_token'],
        'document' => $document,
    ];
}

test('each recorded Release Day outcome creates a generic own-identity in-app notice without requiring a portal account or PDF', function (): void {
    Queue::fake();

    $outcomes = [
        ['type' => 'approve', 'notes' => null, 'target' => null, 'reason' => null, 'default' => null],
        ['type' => 'modify', 'notes' => null, 'target' => null, 'reason' => null, 'default' => 'BSPSY'],
        ['type' => 'override', 'notes' => 'PRIVATE_OVERRIDE_NOTE', 'target' => null, 'reason' => null, 'default' => 'BSN'],
        ['type' => 'defer', 'notes' => 'PRIVATE_DEFER_NOTE', 'target' => null, 'reason' => null, 'default' => null],
        ['type' => 'refer', 'notes' => null, 'target' => 'BSN', 'reason' => 'PRIVATE_REFERRAL_REASON', 'default' => null],
    ];

    foreach ($outcomes as $outcome) {
        $fixture = releaseDayDecisionNoticeFixture($outcome['type']);
        $attempt = $fixture['attempt'];
        $identity = $fixture['identity'];
        $guidance = $fixture['guidance'];

        expect($identity->applicantAccount()->exists())->toBeFalse();

        $decision = app(RecordConsultationDecision::class)->handle(
            $attempt,
            $guidance,
            $outcome['type'],
            $outcome['notes'],
            'release-day-notice-'.$attempt->id,
            $outcome['target'],
            $outcome['reason'],
            $outcome['default'],
        );
        $notices = NotificationDelivery::query()
            ->where('event_type', ReleaseDayDecisionNotice::EVENT)
            ->where('recipient_type', ApplicantIdentity::class)
            ->where('recipient_id', $identity->id)
            ->get()
            ->filter(fn (NotificationDelivery $delivery): bool => (int) ($delivery->payload['attempt_id'] ?? 0) === (int) $attempt->id);
        $notice = $notices->sole();
        $payload = $notice->payload;
        $serializedPayload = json_encode($payload, JSON_THROW_ON_ERROR);

        expect($decision->decision_type)->toBe($outcome['type'])
            ->and($notice->channel)->toBe(NotificationDelivery::CHANNEL_IN_APP)
            ->and($notice->template_key)->toBe(ReleaseDayDecisionNotice::TEMPLATE)
            ->and($payload)->toMatchArray([
                'attempt_id' => (int) $attempt->id,
                'identity_id' => (int) $identity->id,
                'institution_id' => (int) $attempt->institution_id,
                'academic_cycle_id' => (int) $attempt->academic_cycle_id,
                'decision_id' => (int) $decision->id,
                'decision_generation' => (int) $decision->version,
                'subject' => 'Release Day update',
                'body' => 'There is an update to your Release Day information. Sign in to review it.',
                'action_label' => 'View Release Day',
                'action_url' => route('applicant.release-day', [], false),
            ])
            ->and(NotificationDelivery::query()
                ->where('event_type', ReleaseDayDecisionNotice::EVENT)
                ->where('payload->attempt_id', $attempt->id)
                ->where('channel', NotificationDelivery::CHANNEL_MAIL)
                ->exists())->toBeFalse()
            ->and(NotificationDelivery::query()
                ->where('event_type', 'result.digital_issued.v1')
                ->where('payload->consultation_decision_id', $decision->id)
                ->exists())->toBeFalse();

        foreach (['PRIVATE_OVERRIDE_NOTE', 'PRIVATE_DEFER_NOTE', 'PRIVATE_REFERRAL_REASON', 'BSN', 'BSPSY', 'score'] as $privateValue) {
            expect($serializedPayload)->not->toContain($privateValue);
        }
    }
});

test('decision replay deduplicates by decision and the applicant inbox hides delivered stale generations', function (): void {
    Queue::fake();
    $fixture = releaseDayDecisionNoticeFixture('replay-current');
    $attempt = $fixture['attempt'];
    $guidance = $fixture['guidance'];
    $applicant = app(AuthenticateApplicantWithAccessKey::class)->handle($fixture['access_key'], '2001-01-01');
    $projection = app(NotificationProjection::class);
    $unreadBefore = $projection->unreadCount($applicant);
    $action = app(RecordConsultationDecision::class);

    $first = $action->handle($attempt, $guidance, 'approve', null, 'release-day-notice-replay-'.$attempt->id);
    $replayed = $action->handle($attempt, $guidance, 'approve', null, 'release-day-notice-replay-'.$attempt->id);
    $firstNotice = NotificationDelivery::query()
        ->where('event_type', ReleaseDayDecisionNotice::EVENT)
        ->where('payload->decision_id', $first->id)
        ->firstOrFail();
    $firstNotice->update([
        'status' => NotificationDelivery::STATUS_DELIVERED,
        'delivered_at' => now(),
    ]);

    $latest = $action->handle(
        $attempt,
        $guidance,
        'defer',
        'PRIVATE_LATEST_NOTE',
        'release-day-notice-latest-'.$attempt->id,
    );
    $latestNotice = NotificationDelivery::query()
        ->where('event_type', ReleaseDayDecisionNotice::EVENT)
        ->where('payload->decision_id', $latest->id)
        ->firstOrFail();

    expect($replayed->id)->toBe($first->id)
        ->and(NotificationDelivery::query()
            ->where('event_type', ReleaseDayDecisionNotice::EVENT)
            ->where('recipient_type', ApplicantIdentity::class)
            ->where('recipient_id', $fixture['identity']->id)
            ->count())->toBe(2)
        ->and(app(NotificationBusinessValidity::class)->isCurrent($firstNotice->fresh()))->toBeFalse()
        ->and(app(NotificationBusinessValidity::class)->isCurrent($latestNotice))->toBeTrue()
        ->and($projection->unreadCount($applicant))->toBe($unreadBefore + 1);

    $forUserRows = collect($projection->forUser($applicant, 50)['recent']);
    $listedRows = collect($projection->list($applicant, 50));

    expect($forUserRows->pluck('id'))->not->toContain($firstNotice->id)
        ->and($listedRows->pluck('id'))->not->toContain($firstNotice->id)
        ->and($listedRows->firstWhere('id', $latestNotice->id))->toMatchArray([
            'subject' => 'Release Day update',
            'body' => 'There is an update to your Release Day information. Sign in to review it.',
            'action_label' => 'View Release Day',
            'action_url' => route('applicant.release-day', [], false),
        ]);
});

test("an older attempt notice is hidden when Release Day resolves the applicant's latest attempt", function (): void {
    Queue::fake();
    $fixture = releaseDayDecisionNoticeFixture('superseded-attempt');
    $decision = app(RecordConsultationDecision::class)->handle(
        $fixture['attempt'],
        $fixture['guidance'],
        'approve',
        null,
        'release-day-notice-superseded-attempt-'.$fixture['attempt']->id,
    );
    $notice = NotificationDelivery::query()
        ->where('event_type', ReleaseDayDecisionNotice::EVENT)
        ->where('payload->decision_id', $decision->id)
        ->firstOrFail();
    $applicant = app(AuthenticateApplicantWithAccessKey::class)->handle($fixture['access_key'], '2001-01-01');

    expect(app(NotificationBusinessValidity::class)->isCurrent($notice))->toBeTrue();
    $nextCycle = AcademicCycle::query()->create([
        'institution_id' => $fixture['attempt']->institution_id,
        'code' => 'RELEASE-NOTICE-NEXT',
        'name' => 'Next Release Notice Cycle',
        'opens_on' => now()->addYear()->toDateString(),
        'closes_on' => now()->addYear()->addMonths(6)->toDateString(),
        'is_active' => false,
    ]);

    AdmissionAttempt::query()->create([
        'applicant_identity_id' => $fixture['identity']->id,
        'institution_id' => $fixture['attempt']->institution_id,
        'academic_cycle_id' => $nextCycle->id,
        'application_window_id' => $fixture['attempt']->application_window_id,
        'reference_code' => 'SYN-RELEASE-DAY-NEXT-'.Str::uuid()->toString(),
        'status' => AdmissionAttempt::STATUS_PENDING,
        'applicant_type' => 'new',
        'privacy_policy_id' => $fixture['attempt']->privacy_policy_id,
    ]);

    expect(app(NotificationBusinessValidity::class)->isCurrent($notice->fresh()))->toBeFalse()
        ->and(collect(app(NotificationProjection::class)->list($applicant, 50))->pluck('id'))->not->toContain($notice->id);
});

test('a surrounding rollback removes the decision and its notification intent', function (): void {
    $fixture = releaseDayDecisionNoticeFixture('rollback');
    Queue::fake();

    try {
        DB::transaction(function () use ($fixture): void {
            app(RecordConsultationDecision::class)->handle(
                $fixture['attempt'],
                $fixture['guidance'],
                'approve',
                null,
                'release-day-notice-rollback-'.$fixture['attempt']->id,
            );

            throw new RuntimeException('force_release_day_notice_rollback');
        });
    } catch (RuntimeException $exception) {
        expect($exception->getMessage())->toBe('force_release_day_notice_rollback');
    }

    $notices = NotificationDelivery::query()
        ->where('event_type', ReleaseDayDecisionNotice::EVENT)
        ->get()
        ->filter(fn (NotificationDelivery $delivery): bool => (int) ($delivery->payload['attempt_id'] ?? 0) === (int) $fixture['attempt']->id);

    expect(ConsultationDecision::query()->where('admission_attempt_id', $fixture['attempt']->id)->exists())->toBeFalse()
        ->and($notices)->toBeEmpty();
});
