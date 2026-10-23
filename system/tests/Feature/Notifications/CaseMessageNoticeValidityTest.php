<?php

use App\Admission\Actions\AcknowledgeAssistedProgramSelection;
use App\Admission\Actions\OpenApplicantSupportConversation;
use App\Admission\Actions\SendAdmissionSupportMessage;
use App\Admission\Actions\SetGuidanceProgramPool;
use App\Authorization\CapabilityCatalog;
use App\Foundation\Actions\RecordAuditEvent;
use App\Jobs\Notifications\ProcessNotificationDelivery;
use App\Models\Admission\AdmissionAttempt;
use App\Models\Admission\ApplicantIdentity;
use App\Models\Admission\EnrollmentStamp;
use App\Models\Admission\RecordThread;
use App\Models\Admission\RecordThreadMessage;
use App\Models\Foundation\Program;
use App\Models\User;
use App\Notifications\NotificationBusinessValidity;
use App\Notifications\NotificationDelivery;
use App\Notifications\NotificationProjection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;
use Tests\TestCase;

require_once __DIR__.'/../../Support/CaseMessagingHelpers.php';

beforeEach(function (): void {
    seedE2Foundation();
});

/**
 * @return array{attempt: AdmissionAttempt, applicant: User, guidance: User, program: Program, program_head: User}
 */
function caseMessageNoticeFixture(TestCase $test, string $email): array
{
    [$attempt, $applicant, $guidance] = buildMessagingReadyAttempt($test, $email);
    $program = Program::query()->where('code', 'BSN')->firstOrFail();
    app(SetGuidanceProgramPool::class)->handle($attempt, $guidance, $program->code, [
        ['program_code' => $program->code, 'display_order' => 1, 'basis' => 'preference'],
    ], null, 'case-notice-pool-'.md5($email));
    app(AcknowledgeAssistedProgramSelection::class)->handle($attempt, $applicant, 'case-notice-selection-'.md5($email));
    $programHead = User::query()->where('email', 'program.head.bsit@nexiam.test')->firstOrFail();

    return [
        'attempt' => $attempt,
        'applicant' => $applicant,
        'guidance' => $guidance,
        'program' => $program,
        'program_head' => $programHead,
    ];
}

function caseMessageNoticeDelivery(RecordThread $thread, RecordThreadMessage $message, User|ApplicantIdentity $recipient): NotificationDelivery
{
    return NotificationDelivery::query()->create([
        'id' => (string) Str::uuid(),
        'event_id' => hash('sha256', 'case-notice-test:'.$thread->id.':'.$message->id.':'.Str::uuid()),
        'event_type' => 'admission.case_message_sent.v1',
        'event_version' => 1,
        'recipient_type' => $recipient::class,
        'recipient_id' => $recipient->getKey(),
        'channel' => NotificationDelivery::CHANNEL_IN_APP,
        'template_key' => 'admission.case_message_sent',
        'template_version' => 1,
        'payload' => [
            'institution_id' => $thread->attempt()->value('institution_id'),
            'attempt_id' => $thread->admission_attempt_id,
            'thread_id' => $thread->id,
            'message_id' => $message->id,
            'subject' => 'New case message',
            'body' => 'A new message is available in your case conversation.',
            'action_url' => route('applicant.admission-support'),
        ],
        'status' => NotificationDelivery::STATUS_PENDING,
        'attempts' => 0,
    ]);
}

function processCaseMessageNotice(NotificationDelivery $delivery): void
{
    app()->call([
        app(ProcessNotificationDelivery::class, ['deliveryId' => $delivery->id]),
        'handle',
    ], [
        'recordAuditEvent' => app(RecordAuditEvent::class),
        'businessValidity' => app(NotificationBusinessValidity::class),
    ]);
}

function expectCaseNoticeRoute(array $row, string $path, array $expectedQuery): void
{
    $url = parse_url($row['action_url']);
    parse_str($url['query'] ?? '', $query);

    expect($url['path'] ?? null)->toBe($path)
        ->and($query)->toBe($expectedQuery);
}

test('case notice delivery and list projection require exact message membership and current recipient access', function (): void {
    $fixture = caseMessageNoticeFixture($this, 'case-notice-consumer@example.test');
    NotificationDelivery::forUser($fixture['applicant'])
        ->where('channel', NotificationDelivery::CHANNEL_IN_APP)
        ->update(['read_at' => now()]);
    NotificationDelivery::forUser($fixture['program_head'])
        ->where('channel', NotificationDelivery::CHANNEL_IN_APP)
        ->update(['read_at' => now()]);
    Queue::fake();
    $thread = app(OpenApplicantSupportConversation::class)->handle(
        $fixture['attempt'],
        $fixture['program']->id,
        'Applicant support',
        'Please review my admission question.',
        $fixture['program_head'],
        (string) Str::uuid(),
    );
    $message = $thread->messages()->firstOrFail();
    $identity = $fixture['attempt']->identity;
    $applicantDelivery = NotificationDelivery::query()
        ->where('event_type', 'admission.case_message_sent.v1')
        ->where('recipient_type', ApplicantIdentity::class)
        ->where('recipient_id', $identity->id)
        ->where('payload->message_id', $message->id)
        ->firstOrFail();

    processCaseMessageNotice($applicantDelivery);
    $applicantProjection = app(NotificationProjection::class);
    $applicantBell = $applicantProjection->forUser($fixture['applicant']);
    $applicantList = $applicantProjection->list($fixture['applicant']);
    $applicantRow = collect($applicantList)->firstWhere('id', $applicantDelivery->id);

    expect($applicantDelivery->fresh()->status)->toBe(NotificationDelivery::STATUS_DELIVERED)
        ->and($applicantBell['unread_count'])->toBe(1)
        ->and(collect($applicantBell['recent'])->pluck('id'))->toContain($applicantDelivery->id)
        ->and($applicantRow)->not->toBeNull();
    expectCaseNoticeRoute($applicantRow, '/applicant/admission-support', [
        'group' => 'program_support',
        'thread_id' => (string) $thread->id,
        'attempt_id' => (string) $fixture['attempt']->id,
        'program_code' => $fixture['program']->code,
        'cycle_code' => $fixture['attempt']->academicCycle()->value('code'),
    ]);

    $reply = app(SendAdmissionSupportMessage::class)->handle(
        $thread,
        $fixture['applicant'],
        'I am following up on my question.',
        (string) Str::uuid(),
    );
    $staffDelivery = NotificationDelivery::query()
        ->where('event_type', 'admission.case_message_sent.v1')
        ->where('recipient_type', User::class)
        ->where('recipient_id', $fixture['program_head']->id)
        ->where('payload->message_id', $reply->id)
        ->firstOrFail();

    processCaseMessageNotice($staffDelivery);
    $staffList = app(NotificationProjection::class)->list($fixture['program_head']);
    $staffRow = collect($staffList)->firstWhere('id', $staffDelivery->id);

    expect($staffDelivery->fresh()->status)->toBe(NotificationDelivery::STATUS_DELIVERED)
        ->and($staffRow)->not->toBeNull();
    expectCaseNoticeRoute($staffRow, '/consultation/applicants/'.$fixture['attempt']->id, [
        'thread_id' => (string) $thread->id,
        'program_id' => (string) $fixture['program']->id,
    ]);

    $wrongMessage = caseMessageNoticeDelivery($thread, $reply, $fixture['applicant']->applicantAccount->identity);
    $wrongMessage->update(['payload' => [...$wrongMessage->payload, 'message_id' => '99999999']]);
    processCaseMessageNotice($wrongMessage);

    expect($wrongMessage->fresh()->status)->toBe(NotificationDelivery::STATUS_SUPERSEDED)
        ->and(collect(app(NotificationProjection::class)->list($fixture['applicant']))->pluck('id'))->not->toContain($wrongMessage->id)
        ->and(app(NotificationProjection::class)->unreadCount($fixture['applicant']))->toBe(1);
});

test('case notices are suppressed after the exact attempt is enrolled and internal or sender messages never reach applicants', function (): void {
    $fixture = caseMessageNoticeFixture($this, 'case-notice-revoked@example.test');
    NotificationDelivery::forUser($fixture['applicant'])
        ->where('channel', NotificationDelivery::CHANNEL_IN_APP)
        ->update(['read_at' => now()]);
    Queue::fake();
    $thread = app(OpenApplicantSupportConversation::class)->handle(
        $fixture['attempt'],
        $fixture['program']->id,
        'Applicant support',
        'A body-free notice is expected.',
        $fixture['program_head'],
        (string) Str::uuid(),
    );
    $message = $thread->messages()->firstOrFail();
    $identity = $fixture['attempt']->identity;
    $delivery = NotificationDelivery::query()
        ->where('event_type', 'admission.case_message_sent.v1')
        ->where('recipient_type', ApplicantIdentity::class)
        ->where('recipient_id', $identity->id)
        ->where('payload->message_id', $message->id)
        ->firstOrFail();

    $internal = app(SendAdmissionSupportMessage::class)->openInternalThread(
        $fixture['attempt'],
        $fixture['program']->id,
        'Internal case review',
        'Internal staff discussion only.',
        $fixture['program_head'],
        (string) Str::uuid(),
    );
    $internalMessage = $internal->messages()->firstOrFail();
    $forApplicant = caseMessageNoticeDelivery($internal, $internalMessage, $identity);
    processCaseMessageNotice($forApplicant);

    expect($forApplicant->fresh()->status)->toBe(NotificationDelivery::STATUS_SUPERSEDED)
        ->and(collect(app(NotificationProjection::class)->list($fixture['applicant']))->pluck('id'))->not->toContain($forApplicant->id);

    $applicantReply = app(SendAdmissionSupportMessage::class)->handle(
        $thread,
        $fixture['applicant'],
        'My follow-up is still in the current case thread.',
        (string) Str::uuid(),
    );
    expect(NotificationDelivery::query()
        ->where('event_type', 'admission.case_message_sent.v1')
        ->where('recipient_type', ApplicantIdentity::class)
        ->where('recipient_id', $identity->id)
        ->where('payload->message_id', $applicantReply->id)
        ->exists())->toBeFalse();

    EnrollmentStamp::query()->create([
        'admission_attempt_id' => $fixture['attempt']->id,
        'stamp_type' => EnrollmentStamp::STAMP_ENROLLED,
        'stamped_by_user_id' => $fixture['guidance']->id,
        'reason' => 'Case notice validity regression fixture',
        'version' => 1,
        'created_at' => now(),
    ]);
    processCaseMessageNotice($delivery);

    expect($delivery->fresh()->status)->toBe(NotificationDelivery::STATUS_SUPERSEDED)
        ->and(app(NotificationProjection::class)->unreadCount($fixture['applicant']))->toBe(0)
        ->and(collect(app(NotificationProjection::class)->list($fixture['applicant']))->pluck('id'))->not->toContain($delivery->id);
});

test('case notice delivery and projection recheck the applicants current thread capability', function (): void {
    $fixture = caseMessageNoticeFixture($this, 'case-notice-capability-revoked@example.test');
    NotificationDelivery::forUser($fixture['applicant'])
        ->where('channel', NotificationDelivery::CHANNEL_IN_APP)
        ->update(['read_at' => now()]);
    Queue::fake();
    $thread = app(OpenApplicantSupportConversation::class)->handle(
        $fixture['attempt'],
        $fixture['program']->id,
        'Applicant support',
        'The recipient capability will be revoked.',
        $fixture['program_head'],
        (string) Str::uuid(),
    );
    $message = $thread->messages()->firstOrFail();
    $delivery = NotificationDelivery::query()
        ->where('event_type', 'admission.case_message_sent.v1')
        ->where('recipient_type', ApplicantIdentity::class)
        ->where('recipient_id', $fixture['attempt']->applicant_identity_id)
        ->where('payload->message_id', $message->id)
        ->firstOrFail();

    $fixture['applicant']->revokePermissionTo(CapabilityCatalog::RECORD_THREAD_PARTICIPATE);
    $fixture['applicant']->unsetRelation('permissions');

    expect(app(NotificationBusinessValidity::class)->isCurrent($delivery))->toBeFalse();
    processCaseMessageNotice($delivery);

    expect($delivery->fresh()->status)->toBe(NotificationDelivery::STATUS_SUPERSEDED)
        ->and(app(NotificationProjection::class)->unreadCount($fixture['applicant']->fresh()))->toBe(0)
        ->and(collect(app(NotificationProjection::class)->list($fixture['applicant']->fresh()))->pluck('id'))->not->toContain($delivery->id);
});

test('rolled-back case notice dispatch leaves no delivery and exact replay creates one recoverable notice', function (): void {
    $fixture = caseMessageNoticeFixture($this, 'case-notice-rollback@example.test');
    Queue::fake();
    $idempotencyKey = (string) Str::uuid();

    try {
        DB::transaction(function () use ($fixture, $idempotencyKey): void {
            app(OpenApplicantSupportConversation::class)->handle(
                $fixture['attempt'],
                $fixture['program']->id,
                'Replay-safe support',
                'This transaction will roll back.',
                $fixture['program_head'],
                $idempotencyKey,
            );
            throw new RuntimeException('force_case_notice_rollback');
        });
    } catch (RuntimeException $exception) {
        expect($exception->getMessage())->toBe('force_case_notice_rollback');
    }

    expect(RecordThread::query()->where('admission_attempt_id', $fixture['attempt']->id)->exists())->toBeFalse()
        ->and(NotificationDelivery::query()->where('event_type', 'admission.case_message_sent.v1')->exists())->toBeFalse();

    $created = app(OpenApplicantSupportConversation::class)->handle(
        $fixture['attempt'],
        $fixture['program']->id,
        'Replay-safe support',
        'This committed message has one notice.',
        $fixture['program_head'],
        $idempotencyKey,
    );
    $replayed = app(OpenApplicantSupportConversation::class)->handle(
        $fixture['attempt'],
        $fixture['program']->id,
        'Replay-safe support',
        'This committed message has one notice.',
        $fixture['program_head'],
        $idempotencyKey,
    );

    $notices = NotificationDelivery::query()
        ->where('event_type', 'admission.case_message_sent.v1')
        ->where('recipient_type', ApplicantIdentity::class)
        ->where('recipient_id', $fixture['attempt']->applicant_identity_id)
        ->get();

    expect($replayed->id)->toBe($created->id)
        ->and($notices)->toHaveCount(1);
});
