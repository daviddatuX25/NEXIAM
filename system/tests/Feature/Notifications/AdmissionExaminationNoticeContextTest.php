<?php

use App\Admission\Actions\AcceptAdmissionAttempts;
use App\Authorization\CapabilityCatalog;
use App\Examination\Actions\AssignApplicantToExamSession;
use App\Examination\Actions\CancelExamSession;
use App\Examination\Actions\CreateExamSession;
use App\Examination\Actions\PublishExamSession;
use App\Models\Admission\AdmissionAttempt;
use App\Models\Assessment\OmrQuestionBookletVersion;
use App\Models\Examination\ExamSession;
use App\Models\Foundation\AcademicCycle;
use App\Models\Foundation\Room;
use App\Models\Notifications\CommunicationTemplate;
use App\Models\User;
use App\Notifications\NotificationDelivery;
use App\Notifications\NotificationIntent;
use App\Notifications\NotificationIntentDispatcher;
use App\Notifications\Templates\AdmissionExaminationNoticeContext;
use App\Notifications\Templates\CommunicationTemplateRegistry;
use App\Notifications\Templates\Email\NoticeEmailAssetVerifier;
use App\Notifications\Templates\Email\NoticeEmailRenderer;
use Database\Seeders\ExamMaterialRoutingDemoSeeder;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;

beforeEach(function (): void {
    seedE2Foundation();
    Queue::fake();
    Mail::fake();
});

function producerNoticeVersion(string $domain, string $slug, string $text): CommunicationTemplate
{
    $cycle = AcademicCycle::query()->where('code', 'SYN-CYCLE-2026')->firstOrFail();
    $slot = CommunicationTemplateRegistry::findBySlug($domain, $slug);
    $template = CommunicationTemplate::query()->firstOrCreate([
        'institution_id' => $cycle->institution_id, 'domain' => $domain, 'slug' => $slug,
    ], ['event_key' => $slot['event_key'], 'name' => $slot['name']]);
    $number = ($template->versions()->max('version') ?? 0) + 1;
    $blueprint = ['schema_version' => 'notice-email-1', 'layout' => 'flow',
        'blocks' => [['type' => 'paragraph', 'text' => $text]]];
    $template->versions()->create(['version' => $number, 'content_schema_version' => 'notice-content-2',
        'subject' => $domain === 'admissions' ? 'Notice {{applicant_name}}' : 'Notice {{session_code}}',
        'body' => 'Configured short caption', 'email_blueprint' => $blueprint,
        'email_subject' => '', 'email_body' => '', 'in_app_title' => '', 'in_app_body' => '',
        'email_renderer_version' => 'notice-email-1', 'content_sha256' => hash('sha256', json_encode($blueprint, JSON_THROW_ON_ERROR)),
        'document_mode' => 'none', 'created_by_user_id' => User::query()->where('email', 'guidance.counselor@nexiam.test')->value('id')]);
    $template->update(['active_version' => $number]);

    return $template;
}

test('bulk acceptance freezes distinct actual identities with policy owned channels and no producer IO', function (): void {
    $first = submitSyntheticApplicant('bridge.bulk.alpha@example.test', 'DistinctAlpha')['attempt']->fresh();
    $second = submitSyntheticApplicant('bridge.bulk.beta@example.test', 'DistinctBeta')['attempt']->fresh();
    producerNoticeVersion('admissions', 'accepted', '{{applicant_name}} {{application_no}} {{preferred_program}} {{portal_url}}');
    $cycle = $first->academicCycle;
    $cycle->update(['admission_notification_policy' => ['schema_version' => 1, 'version' => 1, 'applicant_status_email_enabled' => true,
        'registrar_new_submission_alert_enabled' => true]]);
    app()->instance(NoticeEmailRenderer::class, Mockery::mock(NoticeEmailRenderer::class)->shouldNotReceive('render')->getMock());
    app()->instance(NoticeEmailAssetVerifier::class, Mockery::mock(NoticeEmailAssetVerifier::class)->shouldNotReceive('verify')->getMock());
    Storage::shouldReceive('disk')->never();
    $registrar = User::query()->where('email', 'registrar.clerk@nexiam.test')->firstOrFail();
    app(AcceptAdmissionAttempts::class)->handle([$first->id, $second->id], $cycle->id, $registrar);
    expect(CommunicationTemplateRegistry::supportsPdf('admissions', 'accepted', 'admission.accepted.v1'))->toBeTrue()
        ->and(CommunicationTemplateRegistry::supportsPdf('examination', 'accepted', 'admission.accepted.v1'))->toBeFalse()
        ->and(CommunicationTemplateRegistry::supportsPdf('admissions', 'submitted', 'admission.submitted.v1'))->toBeFalse();
    foreach ([$first, $second] as $attempt) {
        $rows = NotificationDelivery::query()->where('event_type', 'admission.accepted.v1')
            ->where('recipient_id', $attempt->applicant_identity_id)->get();
        expect($rows->pluck('channel')->sort()->values()->all())->toBe(['in_app', 'mail']);
        $payload = $rows->first()->payload;
        expect($payload['snapshot_schema_version'])->toBe('notice-delivery-2')
            ->and($payload['context']['values']['applicant_name'])->toBe($attempt->identity->fullName())
            ->and($payload['context']['values']['application_no'])->toBe($attempt->reference_code)
            ->and($payload['event_resource']['attempt_id'])->toBe($attempt->id)
            ->and($payload['email']['preparation_failure'])->toBeNull()
            ->and($payload['print'])->toBeNull()
            ->and($payload['context']['values'])->not->toHaveKeys(['email', 'birthdate', 'access_key', 'scores'])
            ->and($attempt->fresh()->status)->toBe(AdmissionAttempt::STATUS_ACCEPTED);
    }
    Mail::assertNothingSent();
});

test('real publication and cancellation freeze own assignment seat and persisted reason while preserving channel policy', function (): void {
    $guidance = User::query()->where('email', 'guidance.counselor@nexiam.test')->firstOrFail();
    $cycle = AcademicCycle::query()->where('code', 'SYN-CYCLE-2026')->firstOrFail();
    $cycle->update(['exam_notification_policy' => ['schema_version' => 1, 'version' => 1, 'publication_email_enabled' => false,
        'reminder_email_enabled' => true, 'reminder_offsets_minutes' => [1440]]]);
    $room = Room::query()->where('code', 'RM-101')->firstOrFail();
    $guidance->givePermissionTo(CapabilityCatalog::SETUP_INSTITUTION_MANAGE);
    enableE2AdvancedExamFeaturePolicy($guidance);
    $materials = ensurePublishReadyExamMaterials($guidance, $room);
    $session = app(CreateExamSession::class)->handle($cycle, $room, 'NOTICE-REAL-PUBLISH',
        now()->addDays(4)->setTime(9, 0)->toDateTimeString(), now()->addDays(4)->setTime(11, 0)->toDateTimeString(),
        $guidance, ExamSession::SEATING_MODE_ASSIGNED_SEATING, $materials['booklet'], $materials['arrangement']);
    $attempts = [];
    foreach (['SeatAlpha', 'SeatBeta'] as $name) {
        $attempt = submitSyntheticApplicant('notice.'.strtolower($name).'@example.test', $name)['attempt']->fresh();
        $attempt->update(['status' => AdmissionAttempt::STATUS_ACCEPTED, 'accepted_at' => now()]);
        app(AssignApplicantToExamSession::class)->handle($session, $attempt, $guidance);
        $attempts[] = $attempt;
    }
    producerNoticeVersion('examination', 'session-published', '{{applicant_name}} {{application_no}} {{session_code}} {{seat_number}} {{portal_url}}');
    producerNoticeVersion('examination', 'session-cancelled', '{{applicant_name}} {{session_code}} {{cancellation_reason}}');
    $session = app(PublishExamSession::class)->handle($session, $guidance, $session->version);
    expect(CommunicationTemplateRegistry::supportsPdf('examination', 'session-published', 'exam.session_published.v1'))->toBeTrue()
        ->and(CommunicationTemplateRegistry::supportsPdf('examination', 'session-cancelled', 'exam.session_cancelled.v1'))->toBeFalse();
    foreach ($attempts as $attempt) {
        $row = NotificationDelivery::query()->where('event_type', 'exam.session_published.v1')
            ->where('recipient_id', $attempt->applicant_identity_id)->sole();
        $assignment = $session->assignments()->where('admission_attempt_id', $attempt->id)->firstOrFail();
        expect($row->channel)->toBe('in_app')
            ->and($row->payload['context']['values']['applicant_name'])->toBe($attempt->identity->fullName())
            ->and($row->payload['context']['values']['seat_number'])->toBe($assignment->activeSeatAllocation->sessionSeat->seat_label)
            ->and($row->payload['context']['values'])->not->toHaveKey('building')
            ->and($row->payload['event_resource']['assignment_id'])->toBe($assignment->id)
            ->and($row->payload['email']['preparation_failure'])->toBeNull();
    }
    $session = app(CancelExamSession::class)->handle($session, $guidance, $session->version, 'Verified venue closure');
    expect($session->status)->toBe(ExamSession::STATUS_CANCELLED);
    foreach ($attempts as $attempt) {
        $rows = NotificationDelivery::query()->where('template_key', 'exam.session_cancelled')
            ->where('recipient_id', $attempt->applicant_identity_id)->get();
        expect($rows->pluck('channel')->sort()->values()->all())->toBe(['in_app', 'mail']);
        expect($rows->first()->payload['context']['values']['cancellation_reason'])->toBe('Verified venue closure')
            ->and($rows->first()->payload['context']['values']['applicant_name'])->toBe($attempt->identity->fullName())
            ->and($rows->first()->payload['print'])->toBeNull();
    }
    Mail::assertNothingSent();
});

test('simple capacity publication notice omits the internal logical seat from recipient values', function (): void {
    seedE2OmrFoundation();
    (new ExamMaterialRoutingDemoSeeder)->run();

    $guidance = User::query()->where('email', 'guidance.counselor@nexiam.test')->firstOrFail();
    $cycle = AcademicCycle::query()->where('code', 'SYN-CYCLE-2026')->firstOrFail();
    $room = Room::query()->where('code', 'RM-101')->firstOrFail();
    $booklet = OmrQuestionBookletVersion::query()
        ->whereHas('booklet', fn ($query) => $query->where('code', ExamMaterialRoutingDemoSeeder::BOOKLET_CODE))
        ->whereNotNull('finalized_at')
        ->firstOrFail();
    $cycle->update(['exam_notification_policy' => ['schema_version' => 1, 'version' => 1, 'publication_email_enabled' => false,
        'reminder_email_enabled' => true, 'reminder_offsets_minutes' => [1440]]]);
    $session = app(CreateExamSession::class)->handle($cycle, $room, 'NOTICE-SIMPLE-PUBLISH',
        now()->addDays(4)->setTime(9, 0)->toDateTimeString(), now()->addDays(4)->setTime(11, 0)->toDateTimeString(),
        $guidance, ExamSession::SEATING_MODE_SIMPLE_CAPACITY, $booklet, null);
    $attempt = submitSyntheticApplicant('notice.simple@example.test', 'Simple Notice')['attempt']->fresh();
    $attempt->update(['status' => AdmissionAttempt::STATUS_ACCEPTED, 'accepted_at' => now()]);
    app(AssignApplicantToExamSession::class)->handle($session, $attempt, $guidance);
    producerNoticeVersion('examination', 'session-published', '{{applicant_name}} {{application_no}} {{session_code}} {{portal_url}}');

    $session = app(PublishExamSession::class)->handle($session, $guidance, $session->version);
    $assignment = $session->assignments()->where('admission_attempt_id', $attempt->id)->firstOrFail();
    $allocation = $assignment->activeSeatAllocation;
    $delivery = NotificationDelivery::query()->where('event_type', 'exam.session_published.v1')
        ->where('recipient_id', $attempt->applicant_identity_id)->sole();
    $context = app(AdmissionExaminationNoticeContext::class)->resolve(
        NotificationIntent::v1('exam.session_published.v1', 'exam.session_published', [
            ...AdmissionExaminationNoticeContext::examinationPayload($session, 'session-published'),
            'exam_session_id' => $session->id,
        ]),
        $attempt->identity,
    );

    expect($session->seating_mode)->toBe(ExamSession::SEATING_MODE_SIMPLE_CAPACITY)
        ->and($session->seating_arrangement_id)->toBeNull()
        ->and($allocation)->not->toBeNull()
        ->and($allocation->effective_to)->toBeNull()
        ->and($allocation->sessionSeat->seat_label)->not->toBeEmpty()
        ->and($allocation->sessionSeat->materialSnapshot->is_current)->toBeTrue()
        ->and($allocation->sessionSeat->materialSnapshot->seating_mode)->toBe(ExamSession::SEATING_MODE_SIMPLE_CAPACITY)
        ->and($context['values'])->not->toHaveKey('seat_number')
        ->and($delivery->channel)->toBe(NotificationDelivery::CHANNEL_IN_APP)
        ->and($delivery->payload['context']['values'])->not->toHaveKey('seat_number')
        ->and($delivery->payload['context']['values']['applicant_name'])->toBe($attempt->identity->fullName())
        ->and($delivery->payload['context']['values']['application_no'])->toBe($attempt->reference_code)
        ->and($delivery->payload['event_resource']['assignment_id'])->toBe($assignment->id);
    Mail::assertNothingSent();
});

test('built-in dispatch freezes configured copy without authored versions and never adds mail on replay', function (): void {
    $attempt = submitSyntheticApplicant('builtin.freeze@example.test', 'Builtin Freeze')['attempt']->fresh();
    $identity = $attempt->identity;
    $original = NotificationDelivery::query()->where('event_type', 'admission.submitted.v1')
        ->where('recipient_id', $identity->id)->where('channel', NotificationDelivery::CHANNEL_IN_APP)->firstOrFail();
    $eventId = $original->event_id;
    $originalPayload = $original->payload;
    expect($original->template_version)->toBe(1)
        ->and($originalPayload['portal']['title'])->toBe('Application Received')
        ->and($originalPayload['portal']['body'])->toBe("Your application {$attempt->reference_code} has been submitted successfully.")
        ->and($originalPayload)->not->toHaveKey('template')
        ->and($originalPayload)->not->toHaveKey('email')
        ->and($originalPayload)->not->toHaveKey('print');

    $messages = config('notification-messages.builtins');
    $messages['admissions.submitted']['title'] = 'New configured title';
    $messages['admissions.submitted']['body'] = 'Configured body for {{application_no}}.';
    config(['notification-messages.builtins' => $messages]);
    $intent = NotificationIntent::v1('admission.submitted.v1', 'admission.submitted', [
        ...AdmissionExaminationNoticeContext::admissionPayload($attempt, 'submitted'),
        'action_label' => 'Open applicant portal',
        'action_url' => AdmissionExaminationNoticeContext::url('applicant.portal'),
        'attempt_id' => $attempt->id,
        'reference_code' => $attempt->reference_code,
    ]);
    $dispatcher = app(NotificationIntentDispatcher::class);
    expect($dispatcher->dispatch($intent, $eventId, [$identity], ['in_app', 'mail']))->toBeEmpty();
    $newEventId = hash('sha256', 'built-in-config-change-for-new-occurrence');
    $created = $dispatcher->dispatch($intent, $newEventId, [$identity], ['in_app', 'mail']);
    $newDelivery = $created->sole();

    expect($newDelivery->channel)->toBe(NotificationDelivery::CHANNEL_IN_APP)
        ->and($newDelivery->payload['portal']['title'])->toBe('New configured title')
        ->and($newDelivery->payload['portal']['body'])->toBe("Configured body for {$attempt->reference_code}.")
        ->and(NotificationDelivery::query()->where('event_id', $eventId)->count())->toBe(1)
        ->and(NotificationDelivery::query()->where('event_id', $newEventId)->where('channel', NotificationDelivery::CHANNEL_MAIL)->exists())->toBeFalse()
        ->and($original->fresh()->payload)->toBe($originalPayload);
});
