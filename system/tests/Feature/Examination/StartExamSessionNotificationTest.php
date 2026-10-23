<?php

use App\Admission\Actions\AcceptAdmissionAttempt;
use App\Admission\Actions\AuthenticateApplicantWithAccessKey;
use App\Authorization\CapabilityCatalog;
use App\Examination\Actions\AssignApplicantToExamSession;
use App\Examination\Actions\CreateExamSession;
use App\Examination\Actions\ExtendExamSession;
use App\Examination\Actions\PublishExamSession;
use App\Examination\Actions\RevertStartedExamSession;
use App\Examination\Actions\StartExamSession;
use App\Models\Admission\ApplicantIdentity;
use App\Models\Examination\ExamAssignment;
use App\Models\Examination\ExamSession;
use App\Models\Examination\ProctorDuty;
use App\Models\Foundation\AcademicCycle;
use App\Models\Foundation\Room;
use App\Models\User;
use App\Notifications\NotificationBusinessValidity;
use App\Notifications\NotificationDelivery;
use App\Notifications\NotificationProjection;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    seedE2Foundation();
    $this->freezeSecond();
    $this->actor = User::query()->where('email', 'guidance.counselor@nexiam.test')->firstOrFail();
    $this->actor->givePermissionTo(CapabilityCatalog::GUIDANCE_EXAM_SCHEDULE);
});

test('a forced early session start creates a readable notice for authorized test administrators', function (): void {
    $submission = submitSyntheticApplicant('forced-start-notice@example.test', 'Forced Start');
    $attempt = app(AcceptAdmissionAttempt::class)->handle($submission['attempt'], $this->actor, 1);
    $unassignedSubmission = submitSyntheticApplicant('unassigned-start-notice@example.test', 'Unassigned');
    $unassignedAttempt = app(AcceptAdmissionAttempt::class)->handle($unassignedSubmission['attempt'], $this->actor, 1);
    $cycle = AcademicCycle::query()->where('code', 'SYN-CYCLE-2026')->firstOrFail();
    $room = Room::query()->where('code', 'RM-101')->firstOrFail();
    $startsAt = now()->addHours(2);
    $session = app(CreateExamSession::class)->handle(
        $cycle,
        $room,
        'FORCE-START-NOTICE-DIAG',
        $startsAt->toDateTimeString(),
        $startsAt->copy()->addHour()->toDateTimeString(),
        $this->actor,
        ExamSession::SEATING_MODE_SIMPLE_CAPACITY,
    );
    app(AssignApplicantToExamSession::class)->handle($session, $attempt, $this->actor);
    $dutyOnlyClerk = User::query()->where('email', 'guidance.clerk@nexiam.test')->firstOrFail();
    Role::findByName('guidance_clerk')->revokePermissionTo(CapabilityCatalog::GUIDANCE_EXAM_SCHEDULE);
    ProctorDuty::query()->create([
        'exam_session_id' => $session->id,
        'user_id' => $dutyOnlyClerk->id,
        'status' => ProctorDuty::STATUS_ACTIVE,
        'idempotency_key' => 'force-start-historical-duty',
        'assigned_at' => now(),
    ]);
    $session = app(PublishExamSession::class)->handle($session, $this->actor, $session->version);

    expect(fn () => app(StartExamSession::class)->handle($session, $this->actor, $session->version - 1))
        ->toThrow(RuntimeException::class, 'Stale exam session version.');
    expect(NotificationDelivery::query()->where('event_type', 'exam.session_started_early.v1')->exists())->toBeFalse();
    try {
        DB::transaction(function () use ($session): void {
            app(StartExamSession::class)->handle($session->fresh(), $this->actor, $session->version);
            throw new RuntimeException('force outer rollback');
        });
    } catch (RuntimeException $exception) {
        expect($exception->getMessage())->toBe('force outer rollback');
    }
    expect($session->fresh()->status)->toBe(ExamSession::STATUS_PUBLISHED)
        ->and(NotificationDelivery::query()->where('event_type', 'exam.session_started_early.v1')->exists())->toBeFalse();
    Queue::fake();
    $started = app(StartExamSession::class)->handle($session, $this->actor, $session->version);
    expect($started->status)->toBe(ExamSession::STATUS_IN_PROGRESS);
    $eventId = hash('sha256', "exam.session_started:{$session->id}:v{$started->version}");
    $initialDeliveryCount = NotificationDelivery::query()->where('event_id', $eventId)->count();
    app(StartExamSession::class)->handle($started, $this->actor, $started->version);
    $eventDeliveries = NotificationDelivery::query()->where('event_id', $eventId)->get();

    $delivery = NotificationDelivery::query()
        ->where('event_type', 'exam.session_started.v1')
        ->where('recipient_type', User::class)
        ->where('recipient_id', $this->actor->id)
        ->where('channel', NotificationDelivery::CHANNEL_IN_APP)
        ->firstOrFail();
    $projection = collect(app(NotificationProjection::class)->list($this->actor))
        ->firstWhere('id', $delivery->id);

    expect($eventDeliveries)->toHaveCount($initialDeliveryCount);
    expect($eventDeliveries->pluck('channel')->unique()->all())->toBe([NotificationDelivery::CHANNEL_IN_APP])
        ->and($eventDeliveries->pluck('recipient_id')->map(static fn ($id): int => (int) $id)->all())->toContain($this->actor->id)
        ->and($projection)->not->toBeNull()
        ->and($projection['action_url'])->toBe(route('exams.sessions.monitoring', $session))
        ->and(NotificationDelivery::query()
            ->where('event_type', 'exam.session_started.v1')
            ->where('recipient_type', User::class)
            ->where('recipient_id', $dutyOnlyClerk->id)
            ->exists())->toBeFalse();

    $applicantIdentity = $attempt->identity;
    $applicantDelivery = NotificationDelivery::query()
        ->where('event_type', 'exam.session_started_early.v1')
        ->where('recipient_type', ApplicantIdentity::class)
        ->where('recipient_id', $applicantIdentity->id)
        ->where('channel', NotificationDelivery::CHANNEL_IN_APP)
        ->firstOrFail();
    expect(NotificationDelivery::query()
        ->where('event_type', 'exam.session_started_early.v1')
        ->where('recipient_type', ApplicantIdentity::class)
        ->pluck('recipient_id')->map(static fn ($id): int => (int) $id)->all())->toBe([(int) $applicantIdentity->id])
        ->and($unassignedAttempt->identity->id)->not->toBe($applicantIdentity->id);
    $applicantNotice = $applicantDelivery->payload;

    expect($applicantNotice['subject'])->toBe("Exam session {$session->code} started early")
        ->and($applicantNotice['body'])->toContain('Started at '.now()->setTimezone('Asia/Manila')->format('F j, Y g:i A T'))
        ->and($applicantNotice['body'])->toContain('expected end '.$session->effective_end_at->setTimezone('Asia/Manila')->format('F j, Y g:i A T'))
        ->and($applicantNotice['action_url'])->toBe(route('applicant.schedule'))
        ->and(app(NotificationBusinessValidity::class)->isCurrent($applicantDelivery))->toBeTrue();

    $reverted = app(RevertStartedExamSession::class)->handle($started, $this->actor, $started->version);
    $corrected = NotificationDelivery::query()
        ->where('event_type', 'exam.session_start_reverted.v1')
        ->where('recipient_type', ApplicantIdentity::class)
        ->where('recipient_id', $applicantIdentity->id)
        ->get();
    $correctionDelivery = NotificationDelivery::query()
        ->where('event_type', 'exam.session_start_reverted.v1')
        ->where('recipient_type', ApplicantIdentity::class)
        ->where('recipient_id', $applicantIdentity->id)
        ->firstOrFail();
    expect(app(NotificationBusinessValidity::class)->isCurrent($correctionDelivery))->toBeTrue();
    $applicantDelivery->refresh();
    app(RevertStartedExamSession::class)->handle($reverted, $this->actor, $reverted->version);

    expect($reverted->status)->toBe(ExamSession::STATUS_PUBLISHED)
        ->and($corrected)->toHaveCount(1)
        ->and(NotificationDelivery::query()->where('event_type', 'exam.session_start_reverted.v1')->count())->toBe(1)
        ->and(app(NotificationBusinessValidity::class)->isCurrent($applicantDelivery))->toBeFalse()
        ->and($applicantDelivery->status)->toBe(NotificationDelivery::STATUS_SUPERSEDED);
    $extendedAfterCorrection = app(ExtendExamSession::class)->handle(
        $reverted,
        $this->actor,
        $reverted->version,
        $reverted->effective_end_at->copy()->addMinutes(30),
    );
    expect(app(NotificationBusinessValidity::class)->isCurrent($correctionDelivery))->toBeFalse()
        ->and($extendedAfterCorrection->status)->toBe(ExamSession::STATUS_PUBLISHED);
});

test('on-time session starts retain staff notices without applicant start notices', function (): void {
    $submission = submitSyntheticApplicant('on-time-start-notice@example.test', 'On Time');
    $attempt = app(AcceptAdmissionAttempt::class)->handle($submission['attempt'], $this->actor, 1);
    $cycle = AcademicCycle::query()->where('code', 'SYN-CYCLE-2026')->firstOrFail();
    $room = Room::query()->where('code', 'RM-101')->firstOrFail();
    $startsAt = now()->addHours(2);
    $session = app(CreateExamSession::class)->handle(
        $cycle,
        $room,
        'ON-TIME-START-NOTICE',
        $startsAt->toDateTimeString(),
        $startsAt->copy()->addHour()->toDateTimeString(),
        $this->actor,
        ExamSession::SEATING_MODE_SIMPLE_CAPACITY,
    );
    app(AssignApplicantToExamSession::class)->handle($session, $attempt, $this->actor);
    $session = app(PublishExamSession::class)->handle($session, $this->actor, $session->version);

    $this->travelTo($startsAt);
    $started = app(StartExamSession::class)->handle($session, $this->actor, $session->version);

    expect(NotificationDelivery::query()->where('event_type', 'exam.session_started_early.v1')->exists())->toBeFalse()
        ->and(NotificationDelivery::query()
            ->where('event_type', 'exam.session_started.v1')
            ->where('recipient_type', User::class)
            ->where('recipient_id', $this->actor->id)
            ->exists())->toBeTrue()
        ->and($started->started_at->equalTo($startsAt))->toBeTrue();
});

test('published and live session end changes notify only assigned applicant identities', function (): void {
    $submission = submitSyntheticApplicant('extended-end-notice@example.test', 'Extended End');
    $attempt = app(AcceptAdmissionAttempt::class)->handle($submission['attempt'], $this->actor, 1);
    $cycle = AcademicCycle::query()->where('code', 'SYN-CYCLE-2026')->firstOrFail();
    $room = Room::query()->where('code', 'RM-101')->firstOrFail();
    $startsAt = now()->addHours(2);
    $session = app(CreateExamSession::class)->handle(
        $cycle,
        $room,
        'EXTENDED-END-NOTICE',
        $startsAt->toDateTimeString(),
        $startsAt->copy()->addHour()->toDateTimeString(),
        $this->actor,
        ExamSession::SEATING_MODE_SIMPLE_CAPACITY,
    );
    app(AssignApplicantToExamSession::class)->handle($session, $attempt, $this->actor);
    $session = app(PublishExamSession::class)->handle($session, $this->actor, $session->version);
    $newEnd = $session->effective_end_at->copy()->addMinutes(30);

    $extended = app(ExtendExamSession::class)->handle(
        $session,
        $this->actor,
        $session->version,
        $newEnd,
    );
    $delivery = NotificationDelivery::query()
        ->where('event_type', 'exam.session_expected_end_changed.v1')
        ->where('recipient_type', ApplicantIdentity::class)
        ->where('recipient_id', $attempt->identity->id)
        ->where('channel', NotificationDelivery::CHANNEL_IN_APP)
        ->firstOrFail();

    expect($delivery->payload['body'])->toContain($newEnd->format('F j, Y g:i A').' (campus time)')
        ->and($delivery->payload['action_url'])->toBe(route('applicant.schedule'))
        ->and(app(NotificationBusinessValidity::class)->isCurrent($delivery))->toBeTrue()
        ->and($extended->effective_end_at->equalTo($newEnd))->toBeTrue();
    $started = app(StartExamSession::class)->handle($extended, $this->actor, $extended->version);
    $liveEnd = $started->effective_end_at->copy()->addMinutes(15);
    $liveExtended = app(ExtendExamSession::class)->handle(
        $started,
        $this->actor,
        $started->version,
        $liveEnd,
    );
    $liveDelivery = NotificationDelivery::query()
        ->where('event_type', 'exam.session_expected_end_changed.v1')
        ->where('recipient_type', ApplicantIdentity::class)
        ->where('recipient_id', $attempt->identity->id)
        ->where('event_id', hash('sha256', "exam.session_expected_end_changed:{$session->id}:v{$liveExtended->version}"))
        ->firstOrFail();

    expect(app(NotificationBusinessValidity::class)->isCurrent($delivery))->toBeFalse()
        ->and(app(NotificationBusinessValidity::class)->isCurrent($liveDelivery))->toBeTrue()
        ->and($liveDelivery->payload['body'])->toContain($liveEnd->setTimezone('Asia/Manila')->format('F j, Y g:i A T'))
        ->and($liveExtended->effective_end_at->equalTo($liveEnd))->toBeTrue();
});

test('reverting an extended early start corrects its original recipients and preserves delivered history', function (bool $delivered): void {
    $fixture = submitSyntheticApplicant('extended-revert@example.test', 'Extended Revert');
    $attempt = app(AcceptAdmissionAttempt::class)->handle($fixture['attempt'], $this->actor, 1);
    $session = ExamSession::factory()->create([
        'status' => ExamSession::STATUS_PUBLISHED,
        'starts_at' => now()->addDay(),
        'effective_end_at' => now()->addDay()->addHour(),
    ]);
    ExamAssignment::factory()->create([
        'exam_session_id' => $session->id,
        'admission_attempt_id' => $attempt->id,
    ]);
    Queue::fake();
    $started = app(StartExamSession::class)->handle($session, $this->actor, $session->version, 30);
    $early = NotificationDelivery::query()->where('event_type', 'exam.session_started_early.v1')->firstOrFail();
    $started->increment('version');
    $started->refresh();
    expect(app(NotificationBusinessValidity::class)->isCurrent($early))->toBeTrue();
    if ($delivered) {
        $early->update(['status' => NotificationDelivery::STATUS_DELIVERED, 'delivered_at' => now()]);
    }
    $deliveredAt = $early->delivered_at?->toIso8601String();
    $extended = app(ExtendExamSession::class)->handle(
        $started, $this->actor, $started->version, $started->effective_end_at->copy()->addMinutes(15),
    );
    $applicant = app(AuthenticateApplicantWithAccessKey::class)->handle(
        $fixture['access_token'], '2001-01-01',
    );
    $visible = collect(app(NotificationProjection::class)->list($applicant));
    expect($visible->contains('id', $early->id))->toBe($delivered);
    $reverted = app(RevertStartedExamSession::class)->handle($extended, $this->actor, $extended->version);
    app(RevertStartedExamSession::class)->handle($reverted, $this->actor, $reverted->version);

    $corrections = NotificationDelivery::query()->where('event_type', 'exam.session_start_reverted.v1')->get();
    expect($corrections)->toHaveCount(1)
        ->and((int) $corrections->sole()->recipient_id)->toBe((int) $attempt->applicant_identity_id)
        ->and(app(NotificationBusinessValidity::class)->isCurrent($corrections->sole()))->toBeTrue()
        ->and($early->fresh()->status)->toBe($delivered ? NotificationDelivery::STATUS_DELIVERED : NotificationDelivery::STATUS_SUPERSEDED)
        ->and($early->fresh()->delivered_at?->toIso8601String())->toBe($deliveredAt)
        ->and(app(NotificationBusinessValidity::class)->isCurrent($early->fresh()))->toBeFalse();
})->with([false, true]);

test('early-start reversal notifies applicants who joined the running sitting after start', function (): void {
    $fixture = submitSyntheticApplicant('joined-after-start@example.test', 'Later Arrival');
    $attempt = app(AcceptAdmissionAttempt::class)->handle($fixture['attempt'], $this->actor, 1);
    $session = ExamSession::factory()->create([
        'status' => ExamSession::STATUS_PUBLISHED,
        'starts_at' => now()->addDay(),
        'effective_end_at' => now()->addDay()->addHour(),
    ]);
    Queue::fake();
    $started = app(StartExamSession::class)->handle($session, $this->actor, $session->version, 30);
    ExamAssignment::factory()->create([
        'exam_session_id' => $started->id,
        'admission_attempt_id' => $attempt->id,
    ]);
    expect(NotificationDelivery::query()->where('event_type', 'exam.session_started_early.v1')->exists())->toBeFalse();
    app(RevertStartedExamSession::class)->handle($started, $this->actor, $started->version);
    $correction = NotificationDelivery::query()->where('event_type', 'exam.session_start_reverted.v1')->sole();
    expect((int) $correction->recipient_id)->toBe((int) $attempt->applicant_identity_id)
        ->and(app(NotificationBusinessValidity::class)->isCurrent($correction))->toBeTrue();
});
