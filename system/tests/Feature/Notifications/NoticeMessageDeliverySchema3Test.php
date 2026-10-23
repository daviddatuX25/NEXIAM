<?php

use App\Admission\Actions\AuthenticateApplicantWithAccessKey;
use App\Jobs\Notifications\ProcessNotificationDelivery;
use App\Models\Admission\ApplicantIdentity;
use App\Models\Examination\ExamAssignment;
use App\Models\Examination\ExamSession;
use App\Models\Foundation\AcademicCycle;
use App\Models\Foundation\Room;
use App\Models\Notifications\CommunicationTemplate;
use App\Models\Notifications\CommunicationTemplateDocument;
use App\Models\User;
use App\Notifications\Channels\MailDeliveryChannel;
use App\Notifications\Mail\NotificationMailable;
use App\Notifications\NotificationBusinessValidity;
use App\Notifications\NotificationDelivery;
use App\Notifications\NotificationIntent;
use App\Notifications\NotificationProjection;
use App\Notifications\Policies\ExamNotificationPolicy;
use App\Notifications\Queries\NotificationOccurrenceQuery;
use App\Notifications\Services\NoticeDocumentAttachmentCompiler;
use App\Notifications\Templates\NoticeDeliverySchema3;
use App\Notifications\Templates\NoticeMessageState;
use App\Notifications\Templates\NotificationTemplateResolver;
use Illuminate\Mail\Mailer;
use Illuminate\Mail\Transport\ArrayTransport;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

beforeEach(function (): void {
    seedE2Foundation();
    Queue::fake();
    Mail::fake();
});

function noticeSchema3Fixture(string $schema = 'notice-content-3'): array
{
    $cycle = AcademicCycle::query()->where('code', 'SYN-CYCLE-2026')->firstOrFail();
    $room = Room::query()->where('code', 'RM-101')->firstOrFail();
    $actor = User::query()->where('email', 'guidance.counselor@nexiam.test')->firstOrFail();
    $family = CommunicationTemplate::query()->create([
        'institution_id' => $cycle->institution_id, 'domain' => 'examination', 'slug' => 'reminder',
        'event_key' => 'exam.reminder.v1', 'name' => 'Reminder', 'active_version' => 1,
    ]);
    $blueprint = ['schema_version' => 'notice-email-1', 'layout' => 'flow', 'blocks' => [
        ['type' => 'paragraph', 'text' => 'Hello {{applicant_name}} at {{institution_name}}; {{session_code}} in {{room_name}} at {{exam_time}}.'],
    ]];
    $version = $family->versions()->create([
        'version' => 1, 'content_schema_version' => $schema, 'subject' => 'Reminder for {{applicant_name}}',
        'body' => 'Your examination is {{session_code}} in {{room_name}}.', 'email_subject' => 'Old {{applicant_name}}',
        'email_body' => '<p>Old reminder for {{applicant_name}}</p>', 'in_app_title' => 'Old reminder',
        'in_app_body' => 'Old portal reminder.', 'email_blueprint' => $schema === 'notice-content-1' ? null : $blueprint,
        'email_renderer_version' => $schema === 'notice-content-1' ? 'legacy' : 'notice-email-1',
        'content_sha256' => $schema === 'notice-content-1' ? null : hash('sha256', json_encode($blueprint, JSON_THROW_ON_ERROR)),
        'document_mode' => 'none', 'document_asset_id' => null, 'builder_json' => null,
        'paper_size' => null, 'orientation' => null, 'document_source_drafts' => null,
        'import_provenance' => null, 'action_label' => 'View schedule', 'action_route' => 'applicant.portal',
        'created_by_user_id' => $actor->id,
    ]);
    $session = ExamSession::query()->create([
        'academic_cycle_id' => $cycle->id, 'room_id' => $room->id, 'code' => 'SCHEMA3-REMINDER',
        'status' => ExamSession::STATUS_PUBLISHED, 'booking_mode' => ExamSession::BOOKING_STAFF_ONLY,
        'starts_at' => now()->addHours(24), 'effective_end_at' => now()->addHours(26), 'version' => 1,
        'notification_policy_snapshot' => ExamNotificationPolicy::defaults()->snapshot(1),
    ]);
    $applicants = [];
    foreach (['Schema Three Alpha', 'Schema Three Beta'] as $name) {
        $applicant = submitSyntheticApplicant(strtolower(str_replace(' ', '-', $name)).'@example.test', $name);
        $user = app(AuthenticateApplicantWithAccessKey::class)->handle($applicant['access_token'], '2001-01-01');
        $assignment = ExamAssignment::query()->create([
            'exam_session_id' => $session->id, 'admission_attempt_id' => $applicant['attempt']->id,
            'status' => ExamAssignment::STATUS_ASSIGNED, 'source' => ExamAssignment::SOURCE_STAFF_ASSIGNED, 'version' => 1,
        ]);
        $applicants[] = ['identity' => $applicant['attempt']->identity, 'user' => $user, 'assignment' => $assignment];
    }

    return compact('cycle', 'room', 'actor', 'family', 'version', 'session', 'applicants');
}

function noticeSchema3Emit(): void
{
    expect(Artisan::call('notifications:send-exam-reminders', ['--window' => '24h']))->toBe(0);
}

function noticeSchema3Rows(array $applicant): Collection
{
    return NotificationDelivery::query()->where('event_type', 'exam.reminder.v1')
        ->where('recipient_id', $applicant['identity']->id)->get();
}

function noticeSchema3ActualMailer(): array
{
    $transport = new ArrayTransport;
    $mailer = new class('schema3-mail', app('view'), $transport, app('events')) extends Mailer
    {
        public array $captured = [];

        protected function sendMailable($mailable)
        {
            $this->captured[] = $mailable;

            return parent::sendMailable($mailable);
        }
    };
    $mailer->alwaysFrom('notice@example.test', 'Notice');
    Mail::swap($mailer);

    return [$mailer, $transport];
}

test('reminder freezes exact content three pins and separate actual portal snapshots per recipient', function (): void {
    $fixture = noticeSchema3Fixture();
    noticeSchema3Emit();

    foreach ($fixture['applicants'] as $applicant) {
        $rows = noticeSchema3Rows($applicant);
        $payload = $rows->firstOrFail()->payload;
        expect($rows)->toHaveCount(2)
            ->and($payload['snapshot_schema_version'])->toBe('notice-delivery-3')
            ->and($payload['event_type'])->toBe('exam.reminder.v1')
            ->and($payload['canonical_slug'])->toBe('reminder')
            ->and($payload['recipient']['id'])->toBe($applicant['identity']->id)
            ->and($payload['template'])->toMatchArray([
                'id' => $fixture['family']->id, 'version' => 1, 'content_schema_version' => 'notice-content-3',
                'email_schema_version' => 'notice-email-1', 'email_renderer_version' => 'notice-email-1',
            ])
            ->and($payload['context']['values']['applicant_name'])->toBe($applicant['identity']->fullName())
            ->and($payload['portal']['title'])->toBe('Reminder for '.$applicant['identity']->fullName())
            ->and($payload['portal']['body'])->toContain('SCHEMA3-REMINDER')
            ->and($payload['portal']['action_label'])->toBe('View schedule')
            ->and($payload['print'])->toBeNull()
            ->and($payload)->not->toHaveKeys(['document_snapshot', 'document_id', 'event_context'])
            ->and($rows->firstWhere('channel', 'in_app')->payload)->toBe($payload)
            ->and($rows->firstWhere('channel', 'mail')->payload)->toBe($payload);
    }

    $firstRows = noticeSchema3Rows($fixture['applicants'][0]);
    $firstInbox = $firstRows->firstWhere('channel', 'in_app');
    expect(app(NotificationBusinessValidity::class)->isCurrent($firstInbox))->toBeTrue();
    app()->call([(new ProcessNotificationDelivery($firstInbox->id)), 'handle']);
    expect($firstInbox->fresh()->status)->toBe(NotificationDelivery::STATUS_DELIVERED);

    $occurrences = app(NotificationOccurrenceQuery::class);
    $activity = $occurrences->activity($fixture['actor'], $occurrences->accessibleDomains($fixture['actor']), ['domain' => 'examination']);
    expect($activity->total())->toBe(2)
        ->and($occurrences->deliveries($fixture['actor'], $occurrences->occurrence($fixture['actor'], $activity->items()[0]['occurrence_id']))->total())->toBe(2);
});

test('schema three missing email context does not suppress independently frozen inbox content', function (): void {
    $fixture = noticeSchema3Fixture();
    $applicant = $fixture['applicants'][0];
    $applicant['identity']->update(['first_name' => '', 'middle_name' => null, 'last_name' => '', 'suffix' => null]);
    noticeSchema3Emit();

    $rows = noticeSchema3Rows($applicant);
    $mail = $rows->firstWhere('channel', 'mail');
    $inbox = $rows->firstWhere('channel', 'in_app');
    expect($rows)->toHaveCount(2)
        ->and($mail->payload['snapshot_schema_version'])->toBe('notice-delivery-3')
        ->and($mail->payload['email']['preparation_failure'])->toBe([
            'code' => 'email_context_missing', 'missing_context_keys' => ['applicant_name'],
        ])
        ->and($inbox->payload['portal']['body'])->toContain('SCHEMA3-REMINDER');

    app()->call([(new ProcessNotificationDelivery($inbox->id)), 'handle']);
    app()->call([(new ProcessNotificationDelivery($mail->id)), 'handle']);
    $visible = collect(app(NotificationProjection::class)->list($applicant['user']))->firstWhere('id', $inbox->id);
    expect($inbox->fresh()->status)->toBe(NotificationDelivery::STATUS_DELIVERED)
        ->and($mail->fresh()->last_error)->toBe('email_context_missing:applicant_name')
        ->and($visible['subject'])->toBe($inbox->payload['portal']['title'])
        ->and($visible['body'])->toBe($inbox->payload['portal']['body']);
    Mail::assertNothingSent();
});

test('schema three mail renders pinned message after latest publication changes and never attaches a document', function (): void {
    $fixture = noticeSchema3Fixture();
    $imageBytes = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNk+A8AAQUBAScY42YAAAAASUVORK5CYII=', true);
    Storage::fake('local');
    $assetId = (string) Str::uuid();
    $assetPath = 'communication-templates/'.$fixture['family']->id.'/'.$assetId.'.png';
    Storage::disk('local')->put($assetPath, $imageBytes);
    $asset = CommunicationTemplateDocument::query()->create([
        'id' => $assetId, 'communication_template_id' => $fixture['family']->id,
        'institution_id' => $fixture['cycle']->institution_id, 'original_name' => 'reminder.png',
        'storage_path' => $assetPath, 'checksum' => hash('sha256', $imageBytes), 'mime_type' => 'image/png',
        'file_size_bytes' => strlen($imageBytes), 'created_by_user_id' => $fixture['actor']->id,
    ]);
    $blueprint = $fixture['version']->email_blueprint;
    $blueprint['blocks'][] = ['type' => 'image', 'asset_id' => $asset->id, 'alt' => 'NEXIAM', 'width' => 1, 'height' => 1];
    $imageVersion = $fixture['family']->versions()->create(array_replace(
        $fixture['version']->only($fixture['version']->getFillable()),
        ['version' => 2, 'email_blueprint' => $blueprint,
            'content_sha256' => hash('sha256', json_encode($blueprint, JSON_THROW_ON_ERROR))]
    ));
    $fixture['family']->update(['active_version' => 2]);
    $fixture['version'] = $imageVersion;
    noticeSchema3Emit();

    $mail = noticeSchema3Rows($fixture['applicants'][0])->firstWhere('channel', 'mail');
    $frozen = $mail->payload;
    expect($frozen['template']['version'])->toBe(2)
        ->and($frozen['email']['assets'])->toHaveCount(1)
        ->and($frozen['email']['assets'][0])->toMatchArray(['id' => $asset->id, 'sha256' => $asset->checksum]);
    $newBlueprint = ['schema_version' => 'notice-email-1', 'layout' => 'flow', 'blocks' => [
        ['type' => 'paragraph', 'text' => 'Latest content must not be used.'],
    ]];
    $latestAttributes = array_replace($imageVersion->only($imageVersion->getFillable()), [
        'version' => 3, 'subject' => 'Latest subject', 'body' => 'Latest portal',
        'email_blueprint' => $newBlueprint, 'content_sha256' => hash('sha256', json_encode($newBlueprint, JSON_THROW_ON_ERROR)),
    ]);
    $fixture['family']->versions()->create($latestAttributes);
    $fixture['family']->update(['active_version' => 3]);
    $documentCompiler = Mockery::mock(NoticeDocumentAttachmentCompiler::class)
        ->shouldNotReceive('compileFrozenPdf')->getMock();
    app()->instance(NoticeDocumentAttachmentCompiler::class, $documentCompiler);
    [$mailer, $transport] = noticeSchema3ActualMailer();
    app(MailDeliveryChannel::class)->deliver($mail);
    $mime = $transport->messages()->first()->getOriginalMessage();
    expect($transport->messages())->toHaveCount(1)
        ->and($mime->getTextBody())->toContain($fixture['applicants'][0]['identity']->fullName())
        ->and($mime->getTextBody())->not->toContain('Latest content', 'View schedule')
        ->and($mime->getHtmlBody())->not->toContain(route('applicant.portal'))
        ->and($mime->getAttachments())->toHaveCount(1)
        ->and($mime->getAttachments()[0]->getDisposition())->toBe('inline')
        ->and($mime->getAttachments()[0]->getContentId())->toBe('notice-'.$asset->id.'@nexiam.inline')
        ->and(hash('sha256', $mime->getAttachments()[0]->getBody()))->toBe($asset->checksum)
        ->and($mail->fresh()->payload)->toBe($frozen);
});
test('schema three freezes and renders the explicit action from its immutable message version', function (): void {
    $fixture = noticeSchema3Fixture();
    $blueprint = $fixture['version']->email_blueprint;
    $state = ['subject' => $fixture['version']->subject, 'body' => $fixture['version']->body,
        'action_label' => '<View schedule>', 'action_route' => 'applicant.schedule',
        'email_blueprint' => $blueprint, 'document_revision_id' => null];
    $version = $fixture['family']->versions()->create(array_replace(
        $fixture['version']->only($fixture['version']->getFillable()),
        ['version' => 2, 'email_renderer_version' => 'notice-message-email-2',
            'action_label' => $state['action_label'], 'action_route' => $state['action_route'],
            'content_sha256' => app(NoticeMessageState::class)
                ->contentDigest($fixture['family'], $state, 'notice-message-email-2')],
    ));
    $fixture['family']->update(['active_version' => 2]);
    $fixture['version'] = $version;
    config(['app.url' => 'https://notices.example.test']);
    $originalUrl = 'https://notices.example.test/applicant/schedule';
    noticeSchema3Emit();

    $mail = noticeSchema3Rows($fixture['applicants'][0])->firstWhere('channel', 'mail');
    $action = ['enabled' => true, 'label' => '<View schedule>', 'route' => 'applicant.schedule',
        'url' => $originalUrl];
    expect($mail->payload['template']['email_renderer_version'])->toBe('notice-message-email-2')
        ->and($mail->payload['email']['action'])->toBe($action)
        ->and(NoticeDeliverySchema3::valid($mail->payload))->toBeTrue();
    $successorState = [...$state, 'subject' => 'Successor subject', 'body' => 'Successor body', 'action_label' => null, 'action_route' => null];
    $successor = $fixture['family']->versions()->create(array_replace(
        $version->only($version->getFillable()),
        ['version' => 3, 'subject' => 'Successor subject', 'body' => 'Successor body',
            'action_label' => null, 'action_route' => null,
            'content_sha256' => app(NoticeMessageState::class)
                ->contentDigest($fixture['family'], $successorState, 'notice-message-email-2')],
    ));
    $fixture['family']->update(['active_version' => $successor->version]);

    [$mailer, $transport] = noticeSchema3ActualMailer();
    config(['app.url' => 'https://successor.example.test']);
    app()->call([(new ProcessNotificationDelivery($mail->id)), 'handle']);
    $mime = $transport->messages()->first()->getOriginalMessage();
    expect($transport->messages())->toHaveCount(1)
        ->and(substr_count($mime->getHtmlBody(), '&lt;View schedule&gt;'))->toBe(1)
        ->and(substr_count($mime->getHtmlBody(), htmlspecialchars($action['url'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8')))->toBe(1)
        ->and($mime->getTextBody())->toEndWith('<View schedule>: '.$action['url'])
        ->and($mime->getAttachments())->toBe([])
        ->and($mail->fresh()->payload['email']['action'])->toBe($action)
        ->and($fixture['version']->fresh()->content_sha256)->toBe($version->content_sha256);
});

test('schema three rejects forged print pins and cannot be selected for unrelated families', function (): void {
    $fixture = noticeSchema3Fixture();
    noticeSchema3Emit();
    $mail = noticeSchema3Rows($fixture['applicants'][0])->firstWhere('channel', 'mail');
    $forged = $mail->payload;
    $forged['print'] = ['mode' => 'docx', 'source_asset_id' => 'forged'];
    $mail->payload = $forged;
    $mail->save();
    $compiler = Mockery::mock(NoticeDocumentAttachmentCompiler::class)
        ->shouldNotReceive('compileFrozenPdf')->getMock();
    app()->instance(NoticeDocumentAttachmentCompiler::class, $compiler);
    expect(fn () => app(MailDeliveryChannel::class)->deliver($mail))->toThrow(RuntimeException::class, 'notice_snapshot_integrity');
    expect(fn () => (new NotificationMailable(
        'exam.reminder', (int) $mail->template_version, $forged,
    ))->attachments())->toThrow(RuntimeException::class, 'notice_snapshot_integrity');

    // Attempting to freeze a version with an intent for an unrelated family (admissions.accepted)
    $unrelatedIntent = new NotificationIntent(
        eventType: 'admission.accepted.v1', templateKey: 'admission.accepted', eventVersion: 1, templateVersion: 1, payload: [],
    );
    expect(fn () => app(NotificationTemplateResolver::class)->freeze(
        $fixture['version'], $unrelatedIntent, ApplicantIdentity::class, (int) $fixture['applicants'][0]['identity']->id,
        ['institution_id' => $fixture['cycle']->institution_id, 'values' => [], 'resource' => []],
    ))->toThrow(RuntimeException::class, 'notice_snapshot_integrity');
});

test('historical content one and two retain delivery schema and renderer meanings', function (): void {
    $fixture = noticeSchema3Fixture('notice-content-1');
    noticeSchema3Emit();
    $legacyMail = noticeSchema3Rows($fixture['applicants'][0])->firstWhere('channel', 'mail');
    expect($legacyMail->payload['snapshot_schema_version'])->toBe('notice-delivery-2')
        ->and($legacyMail->payload['template']['content_schema_version'])->toBe('notice-content-1')
        ->and($legacyMail->payload['template']['email_schema_version'])->toBe('notice-email-legacy-1');

    $semanticBlueprint = [
        'schema_version' => 'notice-email-1', 'layout' => 'flow',
        'blocks' => [['type' => 'paragraph', 'text' => 'Semantic {{applicant_name}}']],
    ];
    $semanticAttributes = array_replace($fixture['version']->only($fixture['version']->getFillable()), [
        'version' => 2, 'content_schema_version' => 'notice-content-2',
        'subject' => 'Semantic {{applicant_name}}', 'body' => 'Semantic reminder.',
        'email_subject' => 'Legacy semantic email subject', 'email_body' => '<p>Legacy semantic email body</p>',
        'in_app_title' => 'Legacy semantic portal title', 'in_app_body' => 'Legacy semantic portal body',
        'email_blueprint' => $semanticBlueprint, 'email_renderer_version' => 'notice-email-1',
        'content_sha256' => hash('sha256', 'semantic'),
    ]);
    $semanticVersion = $fixture['family']->versions()->create($semanticAttributes);
    $legacyPayload = $legacyMail->payload;
    $intent = new NotificationIntent(
        eventType: 'exam.reminder.v1', templateKey: 'exam.reminder', eventVersion: 1, templateVersion: 1, payload: [],
    );
    $semanticPayload = app(NotificationTemplateResolver::class)->freeze(
        $semanticVersion, $intent, ApplicantIdentity::class, (int) $fixture['applicants'][0]['identity']->id,
        ['institution_id' => $fixture['cycle']->institution_id, 'values' => $legacyPayload['context']['values'],
            'resource' => $legacyPayload['event_resource']],
    );
    expect($semanticPayload['snapshot_schema_version'])->toBe('notice-delivery-2')
        ->and($semanticPayload['template']['content_schema_version'])->toBe('notice-content-2')
        ->and($semanticPayload['template']['email_schema_version'])->toBe('notice-email-1')
        ->and($semanticPayload['print'])->toBeNull()
        ->and($legacyMail->fresh()->payload)->toBe($legacyPayload);
});
test('schema three validator accepts every authorable canonical email family and rejects noncanonical tuples', function (): void {
    $families = [
        ['admissions', 'accepted', 'admission.accepted.v1', 'admission.accepted'],
        ['admissions', 'dismissed', 'admission.dismissed.v1', 'admission.dismissed'],
        ['admissions', 'correction-requested', 'admission.correction_requested.v1', 'admission.correction_requested'],
        ['examination', 'session-published', 'exam.session_published.v1', 'exam.session_published'],
        ['examination', 'reminder', 'exam.reminder.v1', 'exam.reminder'],
        ['examination', 'session-cancelled', 'exam.session_cancelled.v1', 'exam.session_cancelled'],
        ['examination', 'session-unpublished', 'exam.session_unpublished.v1', 'exam.session_unpublished'],
        ['results', 'released-online', 'result.released.v1', 'result.released.online'],
        ['results', 'released-f2f', 'result.released.v1', 'result.released.f2f'],
        ['results', 'unreleased', 'result.unreleased.v1', 'result.unreleased'],
        ['results', 'consultation-published', 'consultation.session_published.v1', 'consultation.session_published'],
    ];
    $blueprint = ['schema_version' => 'notice-email-1', 'layout' => 'flow',
        'blocks' => [['type' => 'paragraph', 'text' => 'Hello {{applicant_name}}']]];

    foreach ($families as [$domain, $slug, $eventType, $templateKey]) {
        $resource = match ([$domain, $slug]) {
            ['admissions', 'accepted'], ['admissions', 'dismissed'], ['admissions', 'correction-requested'] => ['attempt_id' => 21, 'identity_id' => 31],
            ['examination', 'reminder'] => ['id' => 41, 'assignment_id' => 51, 'assignment_version' => 1, 'starts_at' => now()->toIso8601String()],
            ['examination', 'session-published'], ['examination', 'session-cancelled'], ['examination', 'session-unpublished'] => ['session_id' => 41, 'attempt_id' => 21, 'identity_id' => 31],
            ['results', 'consultation-published'] => ['consultation_session_id' => 61, 'attempt_id' => 21, 'identity_id' => 31],
            default => ['release_id' => 71, 'release_version' => 2, 'attempt_id' => 21, 'identity_id' => 31],
        };
        $snapshot = [
            'snapshot_schema_version' => 'notice-delivery-3',
            'institution_id' => 11,
            'domain' => $domain,
            'canonical_slug' => $slug,
            'event_type' => $eventType,
            'template_key' => $templateKey,
            'recipient' => ['type' => ApplicantIdentity::class, 'id' => 31, 'email' => 'student@example.test'],
            'event_resource' => $resource,
            'template' => ['id' => (string) Str::uuid(), 'version' => 1, 'content_sha256' => str_repeat('a', 64),
                'content_schema_version' => 'notice-content-3', 'email_schema_version' => 'notice-email-1',
                'email_renderer_version' => 'notice-email-1'],
            'context' => ['values' => ['applicant_name' => 'Student'], 'missing_required_keys' => []],
            'email' => ['subject_template' => 'Notice {{applicant_name}}', 'blueprint' => $blueprint,
                'assets' => [], 'preparation_failure' => null],
            'portal' => ['title' => 'Notice', 'body' => 'Student', 'action_label' => null, 'action_url' => null],
            'print' => null,
        ];

        expect(NoticeDeliverySchema3::valid($snapshot))->toBeTrue("Failed tuple: {$domain}/{$slug}");
    }

    $invalid = $snapshot;
    $invalid['canonical_slug'] = 'submitted';
    expect(NoticeDeliverySchema3::valid($invalid))->toBeFalse();

    $invalid = $snapshot;
    $invalid['context']['values']['score_rows'] = ['caller supplied'];
    expect(NoticeDeliverySchema3::valid($invalid))->toBeFalse();
});
test('schema three rejects foreign released-result bindings and caller-supplied score context', function (): void {
    $payload = [
        'snapshot_schema_version' => 'notice-delivery-3',
        'institution_id' => 1,
        'domain' => 'results',
        'canonical_slug' => 'released-online',
        'event_type' => 'result.released.v1',
        'template_key' => 'result.released.online',
        'recipient' => ['type' => ApplicantIdentity::class, 'id' => 3, 'email' => 'applicant@example.test'],
        'event_resource' => ['id' => 8, 'release_id' => 8, 'release_version' => 2, 'attempt_id' => 7,
            'identity_id' => 3, 'mode' => 'online'],
        'template' => ['id' => (string) Str::uuid(), 'version' => 1, 'content_sha256' => str_repeat('b', 64),
            'content_schema_version' => 'notice-content-3', 'email_schema_version' => 'notice-email-1',
            'email_renderer_version' => 'notice-email-1'],
        'context' => ['values' => ['applicant_name' => 'Applicant'], 'missing_required_keys' => []],
        'email' => ['subject_template' => 'Results', 'blueprint' => ['schema_version' => 'notice-email-1',
            'layout' => 'flow', 'blocks' => [['type' => 'paragraph', 'text' => 'Results']]],
            'assets' => [], 'preparation_failure' => null],
        'portal' => ['title' => 'Results', 'body' => 'Ready', 'action_label' => null, 'action_url' => null],
        'print' => ['mode' => 'notice_document', 'document_revision_id' => 4, 'version' => 1,
            'state' => [], 'assets' => [], 'definition_sha256' => str_repeat('c', 64),
            'render_sha256' => str_repeat('d', 64), 'canonical_released_result' => [
                'binding_schema_version' => 'released-result-1', 'data_provider' => 'result-document.snapshot_payload_json',
                'institution_id' => 1, 'recipient' => ['type' => ApplicantIdentity::class, 'id' => 3],
                'attempt_id' => 7, 'release_id' => 8, 'release_version' => 2, 'mode' => 'online',
            ]],
    ];

    expect(NoticeDeliverySchema3::valid($payload))->toBeTrue();
    $payload['print']['canonical_released_result']['attempt_id'] = 99;
    expect(NoticeDeliverySchema3::valid($payload))->toBeFalse();
    $payload['print']['canonical_released_result']['attempt_id'] = 7;
    $payload['event_resource']['mode'] = 'f2f';
    expect(NoticeDeliverySchema3::valid($payload))->toBeFalse()
        ->and(NoticeDeliverySchema3::valid([
            ...$payload,
            'event_resource' => [...$payload['event_resource'], 'mode' => 'online'],
            'context' => ['values' => ['score_rows' => [['raw' => 10]]], 'missing_required_keys' => []],
        ]))->toBeFalse();
});
