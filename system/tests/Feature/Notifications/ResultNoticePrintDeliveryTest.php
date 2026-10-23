<?php

use App\Admission\Actions\AcceptAdmissionAttempts;
use App\Authorization\CapabilityAuthorizer;
use App\Consultation\Actions\ManageConsultationPolicyAction;
use App\Consultation\Actions\PublishConsultationSession;
use App\Consultation\Actions\ReserveConsultationSlot;
use App\Consultation\Actions\StartOnlineConsultation;
use App\Consultation\Support\AttemptResultReadiness;
use App\Consultation\Support\ConsultationMediaReadiness;
use App\Consultation\Support\ConsultationPolicyResolver;
use App\DocumentTemplate\Rendering\DocxTemplateFiller;
use App\DocumentTemplate\Rendering\LibreOfficeConverter;
use App\Foundation\Actions\RecordAuditEvent;
use App\Jobs\Notifications\ProcessNotificationDelivery;
use App\Models\Assessment\GradingSession;
use App\Models\Assessment\ScoreRecord;
use App\Models\Consultation\ConsultationAssignment;
use App\Models\Consultation\ConsultationEncounter;
use App\Models\Consultation\ConsultationPolicy;
use App\Models\Consultation\ConsultationSession;
use App\Models\Examination\ExamAssignment;
use App\Models\Examination\ExamAttendanceRecord;
use App\Models\Examination\ExamSession;
use App\Models\Examination\ExamSubmissionRecord;
use App\Models\Foundation\AcademicCycle;
use App\Models\Foundation\AptitudeArea;
use App\Models\Foundation\Room;
use App\Models\Notifications\CommunicationTemplate;
use App\Models\Notifications\NotificationDeliveryAttemptArtifact;
use App\Models\Release\ResultDocument;
use App\Models\User;
use App\Notifications\NotificationBusinessValidity;
use App\Notifications\NotificationDelivery;
use App\Notifications\NotificationIntentDispatcher;
use App\Notifications\Services\NoticeDocumentAttachmentCompiler;
use App\Notifications\Templates\Actions\NoticeDocumentWorkflow;
use App\Notifications\Templates\Actions\StoreCommunicationTemplateDocument;
use App\Notifications\Templates\CanonicalReleasedResultBinding;
use App\Notifications\Templates\CommunicationTemplatePayload;
use App\Notifications\Templates\CommunicationTemplateRegistry;
use App\Notifications\Templates\Email\NoticeEmailRenderer;
use App\Notifications\Templates\NoticeDeliverySchema3;
use App\Notifications\Templates\NoticePrintContext;
use App\Printing\PrintRenderer;
use App\Release\Actions\PrepareResultDocument;
use App\Release\Actions\ReleaseResult;
use App\Release\Actions\UnreleaseResult;
use App\Scheduling\AssignmentPlanningMutex;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\Support\ResultSheetDocxFixture;

beforeEach(function (): void {
    Storage::fake('local');
    seedE2Foundation();
    Queue::fake();
    Mail::fake();
});

function resultNoticeReadyFixture(string $name = 'Canonical'): array
{
    $submission = submitSyntheticApplicant('result-notice-'.Str::uuid().'@example.test', $name);
    $registrar = User::query()->where('email', 'registrar.clerk@nexiam.test')->firstOrFail();
    test()->actingAs($registrar)->post(route('admissions.accept', $submission['attempt']), ['expected_version' => 1])->assertRedirect();
    $attempt = $submission['attempt']->fresh();
    $actor = User::query()->where('email', 'guidance.counselor@nexiam.test')->firstOrFail();
    $cycle = AcademicCycle::query()->findOrFail($attempt->academic_cycle_id);
    $room = Room::query()->where('code', 'RM-101')->firstOrFail();
    $session = ExamSession::query()->create(['academic_cycle_id' => $cycle->id, 'room_id' => $room->id,
        'code' => 'RESULT-NOTICE-'.Str::uuid(), 'status' => ExamSession::STATUS_COMPLETED,
        'starts_at' => now()->subDays(2), 'effective_end_at' => now()->subDays(2)->addHours(2), 'closed_at' => now()->subDay()]);
    $assignment = ExamAssignment::query()->create(['exam_session_id' => $session->id, 'admission_attempt_id' => $attempt->id,
        'status' => ExamAssignment::STATUS_ASSIGNED, 'source' => ExamAssignment::SOURCE_STAFF_ASSIGNED]);
    ExamAttendanceRecord::query()->create(['exam_assignment_id' => $assignment->id, 'outcome' => ExamAttendanceRecord::OUTCOME_PRESENT, 'recorded_by_user_id' => $actor->id, 'idempotency_key' => 'att-'.$assignment->id, 'recorded_at' => now()]);
    ExamSubmissionRecord::query()->create(['exam_assignment_id' => $assignment->id, 'recorded_by_user_id' => $actor->id, 'idempotency_key' => 'sub-'.$assignment->id, 'submitted_at' => now()]);
    $grading = GradingSession::query()->create(['exam_session_id' => $session->id,
        'status' => GradingSession::STATUS_FINALIZED, 'opened_at' => now()->subDay(), 'finalized_at' => now()]);
    $score = null;
    foreach (AptitudeArea::query()->where('is_active', true)->get() as $area) {
        $score = ScoreRecord::query()->create(['grading_session_id' => $grading->id, 'admission_attempt_id' => $attempt->id,
            'aptitude_area_id' => $area->id, 'score' => '12', 'converted_score' => 'Frozen-rating',
            'idempotency_key' => 'result-notice-score-'.$grading->id.'-'.$attempt->id.'-'.$area->id,
            'source' => ScoreRecord::SOURCE_MANUAL, 'recorded_by_user_id' => $actor->id]);
    }
    $document = app(PrepareResultDocument::class)->handle($attempt, $actor);

    return compact('attempt', 'actor', 'cycle', 'room', 'document', 'score');
}

function resultNoticePublish(string $slug, array $print = []): CommunicationTemplate
{
    $slot = CommunicationTemplateRegistry::findBySlug('results', $slug);
    $cycle = AcademicCycle::query()->where('code', 'SYN-CYCLE-2026')->firstOrFail();
    $actor = User::query()->where('email', 'guidance.counselor@nexiam.test')->firstOrFail();
    $template = CommunicationTemplate::query()->firstOrCreate(['institution_id' => $cycle->institution_id,
        'domain' => 'results', 'slug' => $slug], ['event_key' => $slot['event_key'], 'name' => $slot['name'], 'active_version' => 0]);
    $next = (int) $template->versions()->max('version') + 1;
    $blueprint = ['schema_version' => 'notice-email-1', 'layout' => 'flow',
        'blocks' => [['type' => 'paragraph', 'text' => 'Notice for {{applicant_name}} at {{institution_name}}']]];
    $template->versions()->create(['version' => $next, 'content_schema_version' => 'notice-content-2',
        'subject' => 'Notice for {{applicant_name}}', 'body' => 'Independent short caption',
        'email_subject' => '', 'email_body' => '', 'in_app_title' => '', 'in_app_body' => '',
        'email_blueprint' => $blueprint, 'email_renderer_version' => 'notice-email-1',
        'content_sha256' => CommunicationTemplatePayload::hash($blueprint), 'document_mode' => 'none',
        'created_by_user_id' => $actor->id, ...$print]);
    $template->update(['active_version' => $next]);

    return $template;
}

function resultNoticeBuilder(): array
{
    return ['schema_version' => 1, 'layout_paradigm' => 'flow', 'page' => ['paper_size' => 'a4',
        'orientation' => 'portrait', 'width_mm' => 210, 'height_mm' => 297, 'margin_mm' => 12],
        'components' => [['id' => 'identity', 'type' => 'field', 'x' => 0, 'y' => 0, 'w' => 180, 'h' => 15,
            'props' => ['label' => 'Applicant', 'binding' => 'applicant_reference']],
            ['id' => 'scores', 'type' => 'score_table', 'x' => 0, 'y' => 20, 'w' => 180, 'h' => 50, 'props' => []],
            ['id' => 'row-alias', 'type' => 'text', 'x' => 0, 'y' => 75, 'w' => 180, 'h' => 20,
                'props' => ['text' => '{{score_table}}']],
            ['id' => 'field-row-alias', 'type' => 'field', 'x' => 0, 'y' => 100, 'w' => 180, 'h' => 20,
                'props' => ['label' => 'Canonical rows', 'binding' => 'score_table']]]];
}

function resultNoticeMail(array $fixture): NotificationDelivery
{
    return NotificationDelivery::query()->where('event_type', 'result.released.v1')
        ->where('recipient_id', $fixture['attempt']->applicant_identity_id)->where('channel', 'mail')->latest('created_at')->firstOrFail();
}

function resultNoticeRemovePdf(string $path): void
{
    @unlink($path);
    @rmdir(dirname($path));
}
test('actual admission acceptance freezes its canonical authored offer without caller score data', function (): void {
    $submission = submitSyntheticApplicant('offer-notice-'.Str::uuid().'@example.test', 'Offer Candidate');
    $actor = User::query()->where('email', 'registrar.clerk@nexiam.test')->firstOrFail();
    $cycle = AcademicCycle::query()->where('code', 'SYN-CYCLE-2026')->firstOrFail();
    $slot = CommunicationTemplateRegistry::findBySlug('admissions', 'accepted');
    $family = CommunicationTemplate::query()->firstOrCreate([
        'institution_id' => $cycle->institution_id,
        'domain' => 'admissions',
        'slug' => 'accepted',
    ], [
        'event_key' => $slot['event_key'],
        'name' => $slot['name'],
        'active_version' => 1,
        'family_revision' => 1,
    ]);
    $blueprint = ['schema_version' => 'notice-email-1', 'layout' => 'flow',
        'blocks' => [['type' => 'paragraph', 'text' => 'Your application {{application_no}} is accepted.']]];
    $next = (int) $family->versions()->max('version') + 1;
    $family->versions()->create([
        'version' => $next,
        'content_schema_version' => 'notice-content-3',
        'subject' => 'Offer for {{applicant_name}}',
        'body' => 'Your application is accepted.',
        'email_subject' => '',
        'email_body' => '',
        'in_app_title' => '',
        'in_app_body' => '',
        'email_blueprint' => $blueprint,
        'email_renderer_version' => 'notice-email-1',
        'content_sha256' => CommunicationTemplatePayload::hash($blueprint),
        'action_route' => 'applicant.portal',
        'document_mode' => 'none',
        'created_by_user_id' => $actor->id,
    ]);
    $family->update(['active_version' => $next]);

    $this->actingAs($actor)->post(route('admissions.accept', $submission['attempt']), ['expected_version' => 1])->assertRedirect();

    $deliveries = NotificationDelivery::query()->where('event_type', 'admission.accepted.v1')
        ->where('recipient_id', $submission['attempt']->applicant_identity_id)->get();
    expect($deliveries)->not->toBeEmpty();
    foreach ($deliveries as $delivery) {
        expect($delivery->payload['snapshot_schema_version'])->toBe('notice-delivery-3')
            ->and($delivery->payload['event_resource']['attempt_id'])->toBe($submission['attempt']->id)
            ->and($delivery->payload['context']['values'])->not->toHaveKeys(['scores', 'score_rows', 'overall_pct'])
            ->and(NoticeDeliverySchema3::valid($delivery->payload))->toBeTrue();
    }
    $bulkSubmission = submitSyntheticApplicant('bulk-offer-notice-'.Str::uuid().'@example.test', 'Bulk Offer Candidate');
    $bulkResult = app(AcceptAdmissionAttempts::class)->handle(
        [(int) $bulkSubmission['attempt']->id], (int) $cycle->id, $actor,
    );
    $bulkDeliveries = NotificationDelivery::query()->where('event_type', 'admission.accepted.v1')
        ->where('recipient_id', $bulkSubmission['attempt']->applicant_identity_id)->get();
    expect($bulkResult['accepted_count'])->toBe(1)->and($bulkDeliveries)->not->toBeEmpty();
    foreach ($bulkDeliveries as $delivery) {
        expect($delivery->payload['snapshot_schema_version'])->toBe('notice-delivery-3')
            ->and($delivery->payload['event_resource']['attempt_id'])->toBe($bulkSubmission['attempt']->id)
            ->and(NoticeDeliverySchema3::valid($delivery->payload))->toBeTrue();
    }
});

test('actual result releases freeze general own context and unchanged channels with exact replay', function (string $mode): void {
    $fixture = resultNoticeReadyFixture();
    resultNoticePublish($mode === 'online' ? 'released-online' : 'released-f2f');
    app()->instance(NoticeEmailRenderer::class, Mockery::mock(NoticeEmailRenderer::class)->shouldNotReceive('render')->getMock());
    $release = app(ReleaseResult::class)->handle($fixture['attempt'], $fixture['document'], $fixture['actor'], 'notice-release', $mode);
    $rows = NotificationDelivery::query()->where('event_type', 'result.released.v1')->where('recipient_id', $fixture['attempt']->applicant_identity_id)->orderBy('id')->get();
    $expectedChannels = $mode === 'online' ? ['in_app', 'mail'] : ['in_app'];
    expect($rows)->toHaveCount(count($expectedChannels))
        ->and($rows->pluck('channel')->sort()->values()->all())->toBe($expectedChannels);
    foreach ($rows as $row) {
        $payload = $row->payload;
        expect($payload['snapshot_schema_version'])->toBe('notice-delivery-2')
            ->and($payload['context']['values']['applicant_name'])->toBe($fixture['attempt']->identity->fullName())
            ->and($payload['context']['values']['portal_url'])->toBe(rtrim(config('app.url'), '/').route('applicant.portal', [], false))
            ->and($payload['context']['values'])->not->toHaveKeys(['scores', 'overall_pct', 'access_key', 'counselor_comments'])
            ->and($payload['print'])->toBeNull()
            ->and($payload['event_resource']['release_id'])->toBe($release->id)
            ->and(app(NotificationBusinessValidity::class)->isCurrent($row))->toBeTrue();
    }
    $frozen = $rows->pluck('payload', 'id')->all();
    $replay = app(ReleaseResult::class)->handle($fixture['attempt'], $fixture['document'], $fixture['actor'], 'notice-release', $mode);
    expect($replay->id)->toBe($release->id)
        ->and(NotificationDelivery::query()->whereIn('id', $rows->pluck('id'))->orderBy('id')->get()->pluck('payload', 'id')->all())->toBe($frozen);
    Mail::assertNothingSent();
})->with(['online', 'f2f']);

test('actual retraction and consultation publication reservation retain own recipient and I-only reservation', function (): void {
    $first = resultNoticeReadyFixture('First');
    $second = resultNoticeReadyFixture('Second');
    foreach (['released-online', 'unreleased', 'consultation-published', 'consultation-reserved'] as $slug) {
        resultNoticePublish($slug);
    }
    $release = app(ReleaseResult::class)->handle($first['attempt'], $first['document'], $first['actor'], 'retract-notice', 'online');
    app(UnreleaseResult::class)->handle($release, $first['actor'], 'Review required');
    $retracted = NotificationDelivery::query()->where('event_type', 'result.unreleased.v1')->get();
    expect($retracted)->toHaveCount(2)->and($retracted->first()->payload['print'])->toBeNull();
    $session = ConsultationSession::query()->create(['academic_cycle_id' => $first['cycle']->id,
        'room_id' => $first['room']->id, 'code' => 'OWN-CONSULTATION', 'status' => ConsultationSession::STATUS_DRAFT,
        'booking_mode' => ConsultationSession::BOOKING_OPEN_FOR_RESERVATION, 'scheduled_at' => now()->addDay(), 'version' => 1]);
    ConsultationAssignment::query()->create(['consultation_session_id' => $session->id, 'admission_attempt_id' => $first['attempt']->id,
        'status' => ConsultationAssignment::STATUS_ASSIGNED, 'source' => ConsultationAssignment::SOURCE_STAFF_ASSIGNED]);
    $session = app(PublishConsultationSession::class)->handle($session, $first['actor'], 1);
    app(ReserveConsultationSlot::class)->handle($session, $second['attempt'], $second['actor'], 'reserve-notice');
    $published = NotificationDelivery::query()->where('event_type', 'consultation.session_published.v1')->get();
    $reserved = NotificationDelivery::query()->where('event_type', 'consultation.slot_reserved.v1')->get();
    expect($published)->toHaveCount(2)->and($reserved)->toHaveCount(1)
        ->and($reserved->first()->channel)->toBe('in_app')
        ->and($reserved->first()->recipient_id)->toBe($second['attempt']->applicant_identity_id);
    foreach ([$published->first(), $reserved->first()] as $row) {
        expect($row->payload['context']['values']['session_code'])->toBe('OWN-CONSULTATION')
            ->and($row->payload['context']['values']['consultation_date'])->toBe($session->scheduled_at->format('F j, Y'))
            ->and($row->payload['context']['values']['room_name'])->toBe($first['room']->name)
            ->and($row->payload['print'] ?? null)->toBeNull();
    }
    expect($reserved->first()->payload)->not->toHaveKey('print')
        ->and($reserved->first()->payload)->not->toHaveKey('email')
        ->and($reserved->first()->payload)->not->toHaveKey('template');
});

test('actual online result release freezes selected schema three scorecard from canonical released data', function (): void {
    $fixture = resultNoticeReadyFixture('Schema Three Result');
    $slot = CommunicationTemplateRegistry::findBySlug('results', 'released-online');
    $family = CommunicationTemplate::query()->create([
        'institution_id' => $fixture['attempt']->institution_id,
        'domain' => 'results',
        'slug' => 'released-online',
        'event_key' => $slot['event_key'],
        'name' => $slot['name'],
        'active_version' => 1,
        'family_revision' => 1,
        'document_revision' => 1,
    ]);
    $blueprint = resultNoticeBuilder();
    $workflow = app(NoticeDocumentWorkflow::class);
    $savedDocument = $workflow->save($family, [
        'base_version' => 0,
        'document_revision' => $family->document_revision,
        'lock_version' => null,
        'idempotency_key' => 'result-schema3-document-draft',
        'state' => ['source_kind' => 'builder', 'document_asset_id' => null, 'builder_json' => $blueprint,
            'paper_size' => 'a4', 'orientation' => 'portrait'],
    ], $fixture['actor']);
    $documentDraft = $savedDocument['document_draft'];
    $preview = $workflow->preview($family->fresh(), [
        'base_version' => 0,
        'document_revision' => $savedDocument['document_revision'],
        'lock_version' => $documentDraft['lock_version'],
        'render_sha256' => $savedDocument['render_sha256'],
    ], $fixture['actor']);
    $documentVersion = $workflow->createVersion($family->fresh(), [
        'base_version' => 0,
        'document_revision' => $savedDocument['document_revision'],
        'lock_version' => $documentDraft['lock_version'],
        'idempotency_key' => 'result-schema3-document-version',
        'render_sha256' => $savedDocument['render_sha256'],
        'preview_receipt' => $preview['preview_receipt'],
    ], $fixture['actor']);
    $messageBlueprint = ['schema_version' => 'notice-email-1', 'layout' => 'flow',
        'blocks' => [['type' => 'paragraph', 'text' => 'Your result document is ready, {{applicant_name}}.']]];
    $family->versions()->create([
        'version' => 1,
        'content_schema_version' => 'notice-content-3',
        'subject' => 'Result document for {{applicant_name}}',
        'body' => 'Your official scorecard is ready.',
        'email_subject' => '',
        'email_body' => '',
        'in_app_title' => '',
        'in_app_body' => '',
        'email_blueprint' => $messageBlueprint,
        'email_renderer_version' => 'notice-email-1',
        'content_sha256' => CommunicationTemplatePayload::hash($messageBlueprint),
        'document_revision_id' => $documentVersion['document_version']['id'],
        'document_mode' => 'none',
        'created_by_user_id' => $fixture['actor']->id,
    ]);

    $release = app(ReleaseResult::class)->handle(
        $fixture['attempt'], $fixture['document'], $fixture['actor'], 'schema-three-release', 'online',
    );
    $mail = resultNoticeMail($fixture);
    $print = $mail->payload['print'];
    $binding = $print['canonical_released_result'];

    expect($mail->payload['snapshot_schema_version'])->toBe('notice-delivery-3')
        ->and($mail->payload['event_resource']['release_id'])->toBe($release->id)
        ->and($binding['release_id'])->toBe($release->id)
        ->and($binding['document_id'])->toBe($fixture['document']->id)
        ->and($binding['score_rows'][0]['converted'])->toBe('Frozen-rating')
        ->and($mail->payload['context']['values'])->not->toHaveKeys(['score_rows', 'scores_rows', 'overall_pct'])
        ->and(NoticeDeliverySchema3::valid($mail->payload))->toBeTrue()
        ->and(app(NotificationBusinessValidity::class)->isCurrent($mail))->toBeTrue();

    $fixture['score']->update(['converted_score' => 'Changed-rating']);
    $fixture['attempt']->identity->update(['first_name' => 'Changed current name']);
    $renderer = Mockery::mock(PrintRenderer::class);
    $renderer->shouldReceive('render')->once()->withArgs(function (string $html, array $options): bool {
        expect($html)->toContain('Frozen-rating')
            ->not->toContain('Changed-rating', 'Changed current name', '{{');
        expect(substr_count($html, 'Frozen-rating'))->toBe(3)
            ->and(substr_count($html, 'class="notice-score-rows"'))->toBe(2)
            ->and($html)->not->toContain('&lt;tr', '<span><table');

        return $options['format'] === 'a4';
    })->andReturn('%PDF-1.4 schema-three-scorecard');
    $compiler = new NoticeDocumentAttachmentCompiler(app(DocxTemplateFiller::class), app(LibreOfficeConverter::class), null, $renderer);
    $path = $compiler->compileFrozenPdf($print, $mail);
    try {
        expect(file_get_contents($path))->toBe('%PDF-1.4 schema-three-scorecard');
    } finally {
        resultNoticeRemovePdf($path);
    }
    $failedPdfCompiler = Mockery::mock(NoticeDocumentAttachmentCompiler::class);
    $failedPdfCompiler->shouldReceive('compileFrozenPdf')->once()
        ->andThrow(new RuntimeException('notice_print_source_invalid'));
    app()->instance(NoticeDocumentAttachmentCompiler::class, $failedPdfCompiler);
    app()->call([new ProcessNotificationDelivery($mail->id), 'handle']);
    $inbox = NotificationDelivery::query()->where('event_type', 'result.released.v1')
        ->where('recipient_id', $fixture['attempt']->applicant_identity_id)
        ->where('channel', NotificationDelivery::CHANNEL_IN_APP)->firstOrFail();
    app()->call([new ProcessNotificationDelivery($inbox->id), 'handle']);
    expect($mail->fresh()->status)->toBe(NotificationDelivery::STATUS_RETRYING)
        ->and($mail->fresh()->last_error)->toBe('notice_print_source_invalid')
        ->and($inbox->fresh()->status)->toBe(NotificationDelivery::STATUS_DELIVERED);
    Mail::assertNothingSent();
});
test('actual online invitation remains inbox only without conference room credentials or score context', function (): void {
    $fixture = resultNoticeReadyFixture();
    $document = app(PrepareResultDocument::class)->handle($fixture['attempt']->fresh(), $fixture['actor']);
    $attempt = $fixture['attempt']->fresh();
    expect((string) $attempt->current_result_document_id)->toBe((string) $document->id)
        ->and($document->status)->toBe(ResultDocument::STATUS_READY)
        ->and(AttemptResultReadiness::isReady($attempt))->toBeTrue();
    app(ManageConsultationPolicyAction::class)->handle($fixture['cycle'], $fixture['actor'], [
        'normal_method' => ConsultationPolicy::METHOD_ONLINE,
        'allow_online_exceptions' => false,
        'expected_version' => 0,
    ]);
    $starter = new StartOnlineConsultation(app(CapabilityAuthorizer::class), app(AssignmentPlanningMutex::class),
        app(ConsultationPolicyResolver::class), app(ConsultationMediaReadiness::class), app(RecordAuditEvent::class),
        app(NotificationIntentDispatcher::class), static fn (): array => ['ready' => true, 'reason' => null]);
    $receipt = $starter->handle($fixture['attempt']->fresh(), $fixture['actor'], 'online-notice');
    $rows = NotificationDelivery::query()->where('event_type', 'consultation.online_invited.v1')->get();
    $encounter = ConsultationEncounter::query()->findOrFail($receipt['encounter_id']);
    expect($rows)->toHaveCount(1)->and($rows->first()->channel)->toBe('in_app')
        ->and($rows->first()->payload)->not->toHaveKey('print')
        ->and($rows->first()->payload)->not->toHaveKey('email')
        ->and($rows->first()->payload['event_resource']['schedule_generation'])->toBe($encounter->schedule_generation)
        ->and($rows->first()->payload['context']['values'])->not->toHaveKeys(['room_name', 'scores', 'access_key'])
        ->and(json_encode($rows->first()->payload))->not->toContain($encounter->room_name)
        ->and(app(NotificationBusinessValidity::class)->isCurrent($rows->first()))->toBeTrue();
});

test('canonical print builder binds immutable provider data and records real source output hashes on fresh retry artifacts', function (): void {
    $fixture = resultNoticeReadyFixture();
    $blueprint = resultNoticeBuilder();
    resultNoticePublish('released-online', ['document_mode' => 'builder', 'builder_json' => $blueprint,
        'paper_size' => 'a4', 'orientation' => 'portrait']);
    app(ReleaseResult::class)->handle($fixture['attempt'], $fixture['document'], $fixture['actor'], 'print-binding', 'online');
    $mail = resultNoticeMail($fixture);
    $binding = $mail->payload['print']['canonical_released_result'];
    expect($binding['document_id'])->toBe($fixture['document']->id)
        ->and($binding['data_sha256'])->toBe(CommunicationTemplatePayload::hash($fixture['document']->snapshot_payload_json))
        ->and($binding['score_rows'][0]['converted'])->toBe('Frozen-rating')
        ->and($mail->payload['context']['values'])->not->toHaveKey('scores_rows');
    $fixture['score']->update(['converted_score' => 'Changed-rating']);
    $fixture['attempt']->identity->update(['first_name' => 'Changed current name']);
    resultNoticePublish('released-online');
    $renderer = Mockery::mock(PrintRenderer::class);
    $renderer->shouldReceive('render')->twice()->withArgs(function (string $html, array $options): bool {
        expect($html)->toContain('Frozen-rating')->not->toContain('Changed-rating', 'Changed current name', '{{');
        expect(substr_count($html, 'Frozen-rating'))->toBe(3)->and(substr_count($html, 'class="notice-score-rows"'))->toBe(2)
            ->and($html)->not->toContain('&lt;tr', '<span><table');

        return $options['format'] === 'a4';
    })->andReturn('%PDF-1.4 canonical-test');
    $compiler = new NoticeDocumentAttachmentCompiler(app(DocxTemplateFiller::class), app(LibreOfficeConverter::class), null, $renderer);
    $paths = [];
    foreach ([1, 2] as $attempt) {
        $mail->update(['attempts' => $attempt]);
        $path = $compiler->compileFrozenPdf($mail->payload['print'], $mail);
        $paths[] = $path;
        try {
            $artifact = NotificationDeliveryAttemptArtifact::query()->where('delivery_id', $mail->id)->where('attempt', $attempt)->firstOrFail();
            expect($artifact->source_sha256)->toBe(CommunicationTemplatePayload::hash($blueprint))
                ->and($artifact->checksum)->toBe(hash_file('sha256', $path))
                ->and($artifact->canonical_binding['document_id'])->toBe($fixture['document']->id)
                ->and($artifact->canonical_binding)->not->toHaveKeys(['replacements', 'scores', 'document_path']);
        } finally {
            resultNoticeRemovePdf($path);
        }
        expect(is_file($path))->toBeFalse()->and(is_dir(dirname($path)))->toBeFalse();
    }
    $artifactBeforeRetry = NotificationDeliveryAttemptArtifact::query()
        ->where('delivery_id', $mail->id)->where('attempt', 2)->firstOrFail();
    $conflictingRenderer = Mockery::mock(PrintRenderer::class);
    $conflictingRenderer->shouldReceive('render')->once()->andReturn('%PDF-1.5 conflicting retry bytes');
    $conflictingCompiler = new NoticeDocumentAttachmentCompiler(
        app(DocxTemplateFiller::class), app(LibreOfficeConverter::class), null, $conflictingRenderer,
    );
    expect(fn () => $conflictingCompiler->compileFrozenPdf($mail->payload['print'], $mail))
        ->toThrow(RuntimeException::class, 'notice_attempt_artifact_conflict')
        ->and(NotificationDeliveryAttemptArtifact::query()->where('delivery_id', $mail->id)->where('attempt', 2)->firstOrFail()->checksum)
        ->toBe($artifactBeforeRetry->checksum);
    $changed = $binding;
    $changed['recipient']['id']++;
    expect(fn () => app(CanonicalReleasedResultBinding::class)->verify($changed, $mail))->toThrow(RuntimeException::class, 'released_result_binding_invalid');
});

test('selected canonical Word uses retained original and typed frozen values with converter failure cleanup', function (): void {
    $fixture = resultNoticeReadyFixture();
    $template = resultNoticePublish('released-online');
    $word = ResultSheetDocxFixture::createValid(['applicant_reference', 'overall_pct']);
    try {
        $asset = app(StoreCommunicationTemplateDocument::class)->store($template, new UploadedFile($word, 'official.docx', null, null, true), $fixture['actor']);
        $analysis = app(NoticePrintContext::class)->analyzeDocx($template, Storage::disk('local')->path($asset->storage_path));
        expect($analysis['valid'])->toBeTrue()->and($analysis['found'])->toContain('applicant_reference', 'overall_pct');
        resultNoticePublish('released-online', ['document_mode' => 'docx', 'document_asset_id' => $asset->id]);
        app(ReleaseResult::class)->handle($fixture['attempt'], $fixture['document'], $fixture['actor'], 'word-binding', 'online');
        $mail = resultNoticeMail($fixture);
        $filled = null;
        $converter = Mockery::mock(LibreOfficeConverter::class);
        $converter->shouldReceive('isAvailable')->once()->andReturnTrue();
        $converter->shouldReceive('convertToPdf')->once()->andReturnUsing(function (string $path) use (&$filled, $mail): string {
            $filled = $path;
            $text = implode('', ResultSheetDocxFixture::decodeWordXmlParts($path));
            expect($text)->toContain((string) $mail->payload['print']['canonical_released_result']['replacements']['overall_pct'])
                ->not->toContain('{{overall_pct}}');
            throw new RuntimeException('test_converter_failure');
        });
        $compiler = new NoticeDocumentAttachmentCompiler(app(DocxTemplateFiller::class), $converter);
        expect(fn () => $compiler->compileFrozenPdf($mail->payload['print'], $mail))->toThrow(RuntimeException::class, 'test_converter_failure');
        expect(is_string($filled))->toBeTrue()->and(is_file($filled))->toBeFalse()
            ->and(hash_file('sha256', Storage::disk('local')->path($asset->storage_path)))->toBe($asset->checksum)
            ->and($fixture['attempt']->fresh()->isAccepted())->toBeTrue()
            ->and(NotificationDelivery::query()->where('event_type', 'result.released.v1')->where('channel', 'in_app')->exists())->toBeTrue();
    } finally {
        ResultSheetDocxFixture::delete($word);
    }
});

test('missing canonical data fails selected print postcommit without attachment downgrade or inbox rollback', function (): void {
    $fixture = resultNoticeReadyFixture();
    $fixture['document']->update(['snapshot_payload_json' => null]);
    resultNoticePublish('released-online', ['document_mode' => 'builder', 'builder_json' => resultNoticeBuilder(),
        'paper_size' => 'a4', 'orientation' => 'portrait']);
    $release = app(ReleaseResult::class)->handle($fixture['attempt'], $fixture['document'], $fixture['actor'], 'missing-data', 'online');
    $mail = resultNoticeMail($fixture);
    expect($mail->payload['print']['canonical_released_result']['preparation_failure']['code'])->toBe('released_result_data_unavailable');
    app()->call([new ProcessNotificationDelivery($mail->id), 'handle']);
    $inbox = NotificationDelivery::query()->where('event_type', 'result.released.v1')->where('channel', 'in_app')->firstOrFail();
    app()->call([new ProcessNotificationDelivery($inbox->id), 'handle']);
    expect($release->fresh()->is_current)->toBeTrue()->and($fixture['attempt']->fresh()->isAccepted())->toBeTrue()
        ->and($mail->fresh()->status)->toBe(NotificationDelivery::STATUS_RETRYING)
        ->and($mail->fresh()->last_error)->toBe('released_result_data_unavailable')
        ->and($inbox->fresh()->status)->toBe(NotificationDelivery::STATUS_DELIVERED);
    Mail::assertNothingSent();
});

test('token bearing unsupported Word stories and unfilled macros fail before conversion with intermediate cleanup', function (): void {
    $template = resultNoticePublish('released-online');
    $word = ResultSheetDocxFixture::createValid(['applicant_reference']);
    $allocated = tempnam(sys_get_temp_dir(), 'notice-unfilled-');
    $filled = $allocated.'.docx';
    rename($allocated, $filled);
    try {
        $zip = new ZipArchive;
        expect($zip->open($word))->toBeTrue();
        $zip->addFromString('word/footnotes.xml', '<w:footnotes xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main"><w:footnote w:id="1"><w:p><w:r><w:t>{{hidden_unknown}} {{broken</w:t></w:r></w:p></w:footnote></w:footnotes>');
        $types = $zip->getFromName('[Content_Types].xml');
        $zip->addFromString('[Content_Types].xml', str_replace('</Types>', '<Override PartName="/word/footnotes.xml" ContentType="application/vnd.openxmlformats-officedocument.wordprocessingml.footnotes+xml"/></Types>', $types));
        $rels = $zip->getFromName('word/_rels/document.xml.rels');
        $zip->addFromString('word/_rels/document.xml.rels', str_replace('</Relationships>', '<Relationship Id="rIdFootnote" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/footnotes" Target="footnotes.xml"/></Relationships>', $rels));
        $body = $zip->getFromName('word/document.xml');
        $zip->addFromString('word/document.xml', str_replace('</w:body>', '<w:p><w:r><w:footnoteReference w:id="1"/></w:r></w:p></w:body>', $body));
        $zip->close();
        $analysis = app(NoticePrintContext::class)->analyzeDocx($template, $word);
        expect($analysis['valid'])->toBeFalse()->and($analysis['unknown'])->toContain('hidden_unknown')
            ->and($analysis['unresolved'])->toContain('unsupported_part:word/footnotes.xml', '{{broken');
        $filler = Mockery::mock(DocxTemplateFiller::class);
        $filler->shouldReceive('fill')->once()->andReturnUsing(function () use ($word, $filled): string {
            copy($word, $filled);

            return $filled;
        });
        $converter = Mockery::mock(LibreOfficeConverter::class);
        $converter->shouldReceive('isAvailable')->once()->andReturnTrue();
        $converter->shouldNotReceive('convertToPdf');
        $compiler = new NoticeDocumentAttachmentCompiler($filler, $converter);
        expect(fn () => $compiler->compileNoticePdf($word, [], null, hash_file('sha256', $word)))
            ->toThrow(RuntimeException::class, 'notice_print_unresolved_tokens');
        expect(is_file($filled))->toBeFalse()->and(is_file($word))->toBeTrue();
    } finally {
        ResultSheetDocxFixture::delete($word);
        @unlink($filled);
    }
});
