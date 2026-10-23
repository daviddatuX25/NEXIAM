<?php

use App\Admission\Actions\AcceptAdmissionAttempt;
use App\Foundation\Actions\RecordAuditEvent;
use App\Jobs\Notifications\ProcessNotificationDelivery;
use App\Models\Admission\AdmissionAttempt;
use App\Models\Foundation\AcademicCycle;
use App\Models\Foundation\Institution;
use App\Models\Notifications\CommunicationTemplate;
use App\Models\Notifications\CommunicationTemplateVersion;
use App\Models\Notifications\NotificationDeliveryAttemptArtifact;
use App\Models\User;
use App\Notifications\Mail\NotificationMailable;
use App\Notifications\NotificationBusinessValidity;
use App\Notifications\NotificationDelivery;
use App\Notifications\Templates\Email\NoticeLegacyEmailRenderer;
use App\Notifications\Templates\NoticePrintContext;
use App\Notifications\Templates\ProvisionCanonicalCommunicationTemplates;
use App\Notifications\Templates\Surfaces\NoticeTemplateSurface;
use Illuminate\Mail\Events\MessageSending;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;
use ZipArchive;

beforeEach(function (): void {
    seedE2Foundation();
    foreach (['admissions', 'examination'] as $domain) {
        app(ProvisionCanonicalCommunicationTemplates::class)->provision(Institution::current(), null, $domain);
    }
});

/**
 * @return array<string, mixed>
 */
function noticeBridgeBlueprint(string $marker): array
{
    $blueprint = (new NoticeTemplateSurface('admissions', 'accepted'))->defaultBlueprint();
    $blueprint['components'] = [
        ['id' => 'header-1', 'type' => 'header', 'x' => 0, 'y' => 0, 'w' => 186, 'h' => 24,
            'props' => ['title' => 'Official offer', 'subtitle' => '{{applicant_name}}', 'align' => 'center']],
        ['id' => 'text-1', 'type' => 'text', 'x' => 0, 'y' => 28, 'w' => 186, 'h' => 14, 'props' => ['text' => $marker]],
    ];

    return $blueprint;
}

function noticeBridgeAdvanceTemplate(
    CommunicationTemplate $template,
    User $actor,
    array $overrides,
): CommunicationTemplateVersion {
    $current = $template->activeVersion() ?? throw new RuntimeException('The notice template has no active version.');
    $versionNumber = (int) $template->active_version + 1;

    $version = CommunicationTemplateVersion::query()->create(array_replace([
        'communication_template_id' => $template->id,
        'version' => $versionNumber,
        'content_schema_version' => 'notice-content-1',
        'email_renderer_version' => NoticeLegacyEmailRenderer::VERSION,
        'email_subject' => $current->email_subject,
        'email_body' => $current->email_body,
        'in_app_title' => $current->in_app_title,
        'in_app_body' => $current->in_app_body,
        'action_label' => $current->action_label,
        'action_route' => $current->action_route,
        'document_mode' => $current->document_mode,
        'document_asset_id' => $current->document_asset_id,
        'builder_json' => $current->builder_json,
        'paper_size' => $current->paper_size,
        'orientation' => $current->orientation,
        'created_by_user_id' => $actor->id,
    ], $overrides));

    $template->update(['active_version' => $versionNumber]);

    return $version;
}

/**
 * @param  array<string, mixed>  $blueprint
 * @return array{actor: User, attempt: AdmissionAttempt, template: CommunicationTemplate, version: CommunicationTemplateVersion, delivery: NotificationDelivery, blueprint: array<string, mixed>}
 */
function noticeBridgeAcceptedMailDelivery(string $email, array $blueprint): array
{
    $actor = User::query()->where('email', 'registrar.admin@nexiam.test')->firstOrFail();

    $template = CommunicationTemplate::query()->where('domain', 'admissions')->where('slug', 'accepted')->firstOrFail();
    $version = noticeBridgeAdvanceTemplate($template, $actor, [
        'email_subject' => 'V1 Official Offer', 'email_body' => 'V1 rendered body',
        'in_app_title' => 'V1 Official Offer', 'in_app_body' => 'V1 rendered body',
        'document_mode' => 'builder', 'document_asset_id' => null, 'builder_json' => $blueprint,
        'paper_size' => 'a4', 'orientation' => 'portrait',
    ]);

    $submitted = submitSyntheticApplicant($email, 'Builder');
    $cycle = AcademicCycle::query()->findOrFail($submitted['attempt']->academic_cycle_id);
    $cycle->update([
        'admission_notification_policy' => [
            'schema_version' => 1,
            'version' => 1,
            'applicant_status_email_enabled' => true,
            'registrar_new_submission_alert_enabled' => false,
        ],
    ]);
    Queue::fake();

    $attempt = app(AcceptAdmissionAttempt::class)->handle(
        $submitted['attempt'],
        $actor,
        (int) $submitted['attempt']->version,
    );

    $delivery = NotificationDelivery::query()
        ->where('event_type', 'admission.accepted.v1')
        ->where('recipient_id', $attempt->applicant_identity_id)
        ->where('channel', NotificationDelivery::CHANNEL_MAIL)
        ->firstOrFail();

    return [
        'actor' => $actor,
        'attempt' => $attempt,
        'template' => $template->fresh(),
        'version' => $version->fresh(),
        'delivery' => $delivery,
        'blueprint' => $blueprint,
    ];
}

function noticeBridgeProcessDelivery(NotificationDelivery $delivery): void
{
    (new ProcessNotificationDelivery($delivery->id))->handle(
        app(RecordAuditEvent::class),
        app(NotificationBusinessValidity::class),
    );
}

/** @param array<int, array<string, mixed>> $captured */
function noticeBridgeCaptureArrayTransport(array &$captured): void
{
    config(['mail.default' => 'bridge-array', 'mail.mailers.bridge-array.transport' => 'array']);
    Event::listen(MessageSending::class,
        function (MessageSending $event) use (&$captured): void {
            $attachments = $event->message->getAttachments();
            expect($attachments)->toHaveCount(1);
            $path = $event->data['attachmentPath'];
            expect($path)->toBeString()->and(is_file($path))->toBeTrue();
            $bytes = $attachments[0]->getBody();
            expect($bytes)->toStartWith('%PDF-');
            $captured[] = ['data' => $event->data, 'subject' => $event->message->getSubject(),
                'html' => $event->message->getHtmlBody(), 'attachment_name' => $attachments[0]->getFilename(),
                'attachment_sha256' => hash('sha256', $bytes), 'attachment_path' => $path];
        });
}

/** @return array<string, mixed> */
function noticeBridgeEditorState(string $marker): array
{
    $blueprint = (new NoticeTemplateSurface('admissions', 'accepted'))->defaultBlueprint();
    $blueprint['components'] = [
        ['id' => 'text-1', 'type' => 'text', 'x' => 0, 'y' => 0, 'w' => 186, 'h' => 14, 'props' => ['text' => $marker]],
        ['id' => 'reference-1', 'type' => 'text', 'x' => 0, 'y' => 16, 'w' => 186, 'h' => 14, 'props' => ['text' => 'Reference: {{application_no}}']],
    ];

    return ['subject' => 'Official offer', 'body' => 'Please review the offer in your portal.', 'action_label' => 'Open portal',
        'action_route' => 'applicant.portal', 'email_blueprint' => ['schema_version' => 'notice-email-1', 'layout' => 'flow',
            'blocks' => [['type' => 'paragraph', 'text' => 'Dear {{applicant_name}}, please review your offer.']]],
        'document_mode' => 'builder', 'builder_json' => $blueprint, 'paper_size' => 'a4', 'orientation' => 'portrait',
        'document_asset_id' => null, 'document_source_drafts' => null, 'import_provenance' => null];
}

test('shared studio edit routes enforce admissions and examination office capabilities', function (): void {
    $registrar = User::query()->where('email', 'registrar.admin@nexiam.test')->firstOrFail();
    $counselor = User::query()->where('email', 'guidance.counselor@nexiam.test')->firstOrFail();
    $this->actingAs($registrar)->get('/setup/notifications/admissions/templates/accepted/edit')->assertOk();
    $this->actingAs($counselor)->get('/setup/notifications/admissions/templates/accepted/edit')->assertForbidden();
    $this->get('/setup/notifications/examination/templates/session-published/edit')->assertOk();
    $this->actingAs($registrar)->get('/setup/notifications/examination/templates/session-published/edit')->assertForbidden();
});

test('reminder draft endpoint accepts message-only state without exposing print fields', function (): void {
    $actor = User::query()->where('email', 'guidance.counselor@nexiam.test')->firstOrFail();
    $template = CommunicationTemplate::query()->where('domain', 'examination')->where('slug', 'reminder')->firstOrFail();
    $parameters = ['domain' => 'examination', 'slug' => 'reminder'];
    $initial = $this->actingAs($actor)->getJson(route('setup.notifications.templates.editor-state', $parameters))
        ->assertOk()->json();
    expect($initial['state'])->toHaveKeys(['subject', 'body', 'action_label', 'action_route', 'email_blueprint', 'document_revision_id'])
        ->not->toHaveKeys(['document_mode', 'document_asset_id', 'builder_json', 'paper_size',
            'orientation', 'document_source_drafts', 'import_provenance']);
    $state = ['subject' => 'Your examination is tomorrow', 'body' => 'Please review your schedule.',
        'action_label' => 'View schedule', 'action_route' => 'applicant.schedule', 'document_revision_id' => null,
        'email_blueprint' => ['schema_version' => 'notice-email-1', 'layout' => 'flow',
            'blocks' => [['type' => 'paragraph', 'runs' => [['text' => 'Hello '], ['token' => 'applicant_name']]]]]];
    $result = $this->actingAs($actor)->postJson(route('setup.notifications.templates.drafts.store', $parameters), [
        'state' => $state, 'base_version' => $template->active_version,
        'family_revision' => $template->family_revision, 'lock_version' => null, 'idempotency_key' => (string) Str::uuid(),
    ])->assertCreated()->json();

    expect($result['draft']['state'])->toBe($state)
        ->and($result['draft']['state'])->not->toHaveKeys(['document_mode', 'document_asset_id', 'builder_json',
            'paper_size', 'orientation', 'document_source_drafts', 'import_provenance']);
});
test('unsupported legacy scorecard layout blocks message conversion without changing the original draft', function (): void {
    app(ProvisionCanonicalCommunicationTemplates::class)->provision(Institution::current(), null, 'results');
    $actor = User::query()->where('email', 'guidance.counselor@nexiam.test')->firstOrFail();
    $template = CommunicationTemplate::query()->where('domain', 'results')->where('slug', 'released-online')->firstOrFail();
    $legacy = $template->draft()->create([
        'base_version' => $template->active_version, 'lock_version' => 1, 'stage' => 2,
        'content_schema_version' => 'notice-content-2', 'document_mode' => 'docx',
        'document_asset_id' => (string) Str::uuid(), 'paper_size' => null, 'orientation' => null,
        'document_source_drafts' => ['docx' => ['document_asset_id' => (string) Str::uuid()]],
        'import_provenance' => ['source_sha256' => str_repeat('a', 64), 'review_acknowledgment' => ['actor_id' => 'preserved']],
        'subject' => 'Legacy scorecard title', 'body' => 'Legacy portal caption',
        'email_blueprint' => ['schema_version' => 'notice-email-1', 'layout' => 'flow',
            'blocks' => [['type' => 'paragraph', 'runs' => [['text' => 'Legacy rich body']]]]],
        'email_subject' => 'Original email subject', 'email_body' => '<p>Original HTML</p>',
        'in_app_title' => 'Original portal title', 'in_app_body' => 'Original portal body',
    ]);
    $original = $legacy->getAttributes();
    $state = [
        'subject' => 'Edited message title', 'body' => 'Edited portal caption',
        'action_label' => null, 'action_route' => null, 'document_revision_id' => null,
        'email_blueprint' => ['schema_version' => 'notice-email-1', 'layout' => 'flow',
            'blocks' => [['type' => 'paragraph', 'runs' => [['text' => 'Edited rich body']]]]],
    ];

    $this->actingAs($actor)->putJson(route('setup.notifications.templates.drafts.update', [
        'domain' => 'results', 'slug' => 'released-online', 'draft' => $legacy->id,
    ]), [
        'state' => $state, 'base_version' => $template->active_version,
        'family_revision' => $template->family_revision, 'lock_version' => 1, 'idempotency_key' => (string) Str::uuid(),
    ])->assertUnprocessable()->assertJsonPath('message',
        'Review the legacy print source before editing; its layout or source cannot be mapped safely. The original draft remains unchanged.');
    $unchangedFields = ['document_mode', 'document_asset_id', 'document_revision_id', 'notice_document_version_id',
        'paper_size', 'orientation', 'document_source_drafts', 'import_provenance', 'subject', 'body', 'action_label',
        'action_route', 'email_blueprint', 'email_subject', 'email_body', 'in_app_title', 'in_app_body',
        'content_schema_version', 'content_sha256'];
    $originalState = array_replace(array_fill_keys($unchangedFields, null),
        array_intersect_key($original, array_fill_keys($unchangedFields, true)));
    $unchangedState = array_replace(array_fill_keys($unchangedFields, null),
        array_intersect_key($legacy->fresh()->getAttributes(), array_fill_keys($unchangedFields, true)));
    ksort($originalState);
    ksort($unchangedState);
    expect($unchangedState)->toBe($originalState)
        ->and($template->documentDraft()->exists())->toBeFalse();
});

test('a verified raster image is meaningful printable DOCX source without extracted text', function (): void {
    $path = tempnam(sys_get_temp_dir(), 'notice-image-only-');
    expect($path)->toBeString();
    $zip = new ZipArchive;
    expect($zip->open($path, ZipArchive::CREATE | ZipArchive::OVERWRITE))->toBeTrue();
    $zip->addFromString('[Content_Types].xml', '<?xml version="1.0" encoding="UTF-8"?><Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types"><Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/><Default Extension="xml" ContentType="application/xml"/><Default Extension="png" ContentType="image/png"/><Override PartName="/word/document.xml" ContentType="application/vnd.openxmlformats-officedocument.wordprocessingml.document.main+xml"/><Override PartName="/word/header1.xml" ContentType="application/vnd.openxmlformats-officedocument.wordprocessingml.header+xml"/></Types>');
    $zip->addFromString('_rels/.rels', '<?xml version="1.0" encoding="UTF-8"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="word/document.xml"/></Relationships>');
    $zip->addFromString('word/_rels/document.xml.rels', '<?xml version="1.0" encoding="UTF-8"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/header" Target="header1.xml"/></Relationships>');
    $zip->addFromString('word/_rels/header1.xml.rels', '<?xml version="1.0" encoding="UTF-8"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/image" Target="media/image.png"/></Relationships>');
    $zip->addFromString('word/media/image.png', base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+jRZkAAAAASUVORK5CYII=', true));
    $zip->addFromString('word/document.xml', '<?xml version="1.0" encoding="UTF-8"?><w:document xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships"><w:body><w:p/><w:sectPr><w:headerReference w:type="default" r:id="rId1"/></w:sectPr></w:body></w:document>');
    $zip->addFromString('word/header1.xml', '<?xml version="1.0" encoding="UTF-8"?><w:hdr xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships" xmlns:wp="http://schemas.openxmlformats.org/drawingml/2006/wordprocessingDrawing" xmlns:a="http://schemas.openxmlformats.org/drawingml/2006/main" xmlns:pic="http://schemas.openxmlformats.org/drawingml/2006/picture"><w:p><w:r><w:drawing><wp:inline><a:graphic><a:graphicData><pic:pic><pic:blipFill><a:blip r:embed="rId1"/></pic:blipFill></pic:pic></a:graphicData></a:graphic></wp:inline></w:drawing></w:r></w:p></w:hdr>');
    $zip->close();

    try {
        $analysis = app(NoticePrintContext::class)->analyzeDocx(new CommunicationTemplate([
            'domain' => 'admissions', 'slug' => 'accepted', 'event_key' => 'admission.accepted.v1',
        ]), $path, ['all' => [], 'required' => [], 'recommended' => [], 'aliases' => []]);
        expect($analysis['valid'])->toBeTrue()->and($analysis['found'])->toBe([])
            ->and($analysis['has_printable_content'])->toBeTrue();
    } finally {
        @unlink($path);
    }
});

test('an unreferenced DOCX header cannot supply printable content or a required print field', function (): void {
    $path = tempnam(sys_get_temp_dir(), 'notice-unused-header-');
    expect($path)->toBeString();
    $zip = new ZipArchive;
    expect($zip->open($path, ZipArchive::CREATE | ZipArchive::OVERWRITE))->toBeTrue();
    $zip->addFromString('[Content_Types].xml', '<?xml version="1.0" encoding="UTF-8"?><Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types"><Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/><Default Extension="xml" ContentType="application/xml"/><Override PartName="/word/document.xml" ContentType="application/vnd.openxmlformats-officedocument.wordprocessingml.document.main+xml"/><Override PartName="/word/header1.xml" ContentType="application/vnd.openxmlformats-officedocument.wordprocessingml.header+xml"/></Types>');
    $zip->addFromString('_rels/.rels', '<?xml version="1.0" encoding="UTF-8"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="word/document.xml"/></Relationships>');
    $zip->addFromString('word/_rels/document.xml.rels', '<?xml version="1.0" encoding="UTF-8"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/header" Target="header1.xml"/></Relationships>');
    $zip->addFromString('word/document.xml', '<?xml version="1.0" encoding="UTF-8"?><w:document xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main"><w:body><w:p/><w:sectPr/></w:body></w:document>');
    $zip->addFromString('word/header1.xml', '<?xml version="1.0" encoding="UTF-8"?><w:hdr xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main"><w:p><w:r><w:t>{{application_no}}</w:t></w:r></w:p></w:hdr>');
    $zip->close();

    try {
        $analysis = app(NoticePrintContext::class)->analyzeDocx(new CommunicationTemplate([
            'domain' => 'admissions', 'slug' => 'accepted', 'event_key' => 'admission.accepted.v1',
        ]), $path);
        expect($analysis['valid'])->toBeFalse()
            ->and($analysis['found'])->toBe(['application_no'])
            ->and($analysis['missing_required'])->toBe([])
            ->and($analysis['has_printable_content'])->toBeFalse()
            ->and($analysis['unresolved'])->toContain('unsupported_part:word/header1.xml');
    } finally {
        @unlink($path);
    }
});

test('accepted notice delivery freezes its builder PDF snapshot and removes the temporary attachment', function (): void {
    $blueprint = noticeBridgeBlueprint('Frozen builder offer V1');
    $fixture = noticeBridgeAcceptedMailDelivery('builder-bridge-v1@example.test', $blueprint);
    $delivery = $fixture['delivery'];
    $snapshot = $delivery->payload['print'];
    $frozenBlueprint = $snapshot['blueprint'];
    expect($delivery->event_type)->toBe('admission.accepted.v1')
        ->and($delivery->payload['snapshot_schema_version'])->toBe('notice-delivery-2')
        ->and($delivery->payload['template']['version'])->toBe($fixture['version']->version)
        ->and($delivery->payload['context']['missing_required_keys'])->toBe([])
        ->and($delivery->payload['context']['values']['applicant_name'])->toBe($fixture['attempt']->identity->fullName())
        ->and($snapshot)->toBeArray()
        ->and($snapshot)->toMatchArray([
            'mode' => 'builder',
            'settings' => ['paper_size' => 'a4', 'orientation' => 'portrait'],
        ])
        ->and($frozenBlueprint['components'][1]['props']['text'])->toBe('Frozen builder offer V1');

    $captured = [];
    noticeBridgeCaptureArrayTransport($captured);
    noticeBridgeProcessDelivery($delivery);
    $delivery->refresh();
    expect($captured)->toHaveCount(1);
    $mail = $captured[0];
    expect($delivery->status)->toBe(NotificationDelivery::STATUS_DELIVERED)
        ->and($delivery->last_error)->toBeNull()
        ->and($mail['data']['__laravel_mailable'])->toBe(NotificationMailable::class)
        ->and($mail['data']['templateVersion'])->toBe($fixture['version']->version)
        ->and($mail['subject'])->toBe('V1 Official Offer')
        ->and($mail['data']['payload']['print']['mode'])->toBe('builder')
        ->and($mail['data']['payload']['print']['blueprint'])->toBe($frozenBlueprint)
        ->and($mail['attachment_name'])->toBe('notice.pdf')
        ->and(is_file($mail['attachment_path']))->toBeFalse();

    $artifact = NotificationDeliveryAttemptArtifact::query()
        ->where('delivery_id', $delivery->id)
        ->where('attempt', 1)
        ->firstOrFail();
    expect($artifact->checksum)->toBe($mail['attachment_sha256'])
        ->and($artifact->mime_type)->toBe('application/pdf')
        ->and($artifact->file_size_bytes)->toBeGreaterThan(0);
});

test('processing a delivery after a later publication uses the original immutable notice and builder snapshot', function (): void {
    $blueprintV1 = noticeBridgeBlueprint('Frozen builder offer V1');
    $fixture = noticeBridgeAcceptedMailDelivery('builder-bridge-immutable@example.test', $blueprintV1);
    $delivery = $fixture['delivery'];
    $template = $fixture['template'];
    $registrar = $fixture['actor'];

    $blueprintV2 = noticeBridgeBlueprint('Changed builder offer V2');
    $versionTwo = noticeBridgeAdvanceTemplate($template, $registrar, [
        'email_subject' => 'V2 Official Offer',
        'email_body' => 'V2 rendered body',
        'in_app_title' => 'V2 Official Offer',
        'in_app_body' => 'V2 rendered body',
        'document_mode' => 'builder',
        'document_asset_id' => null,
        'builder_json' => $blueprintV2,
        'paper_size' => 'letter',
        'orientation' => 'landscape',
    ]);

    $frozenBlueprint = $delivery->fresh()->payload['print']['blueprint'];
    expect($versionTwo->version)->toBe($fixture['version']->version + 1)
        ->and($template->fresh()->active_version)->toBe($versionTwo->version)
        ->and($frozenBlueprint['components'][1]['props']['text'])->toBe('Frozen builder offer V1')
        ->and($frozenBlueprint['components'][1]['props']['text'])->not->toBe('Changed builder offer V2');

    $captured = [];
    noticeBridgeCaptureArrayTransport($captured);
    noticeBridgeProcessDelivery($delivery);
    $delivery->refresh();
    expect($captured)->toHaveCount(1);
    $mail = $captured[0];
    expect($delivery->status)->toBe(NotificationDelivery::STATUS_DELIVERED)
        ->and($delivery->last_error)->toBeNull()
        ->and($mail['data']['templateVersion'])->toBe($fixture['version']->version)
        ->and($mail['subject'])->toBe('V1 Official Offer')
        ->and($mail['html'])->toContain('V1 rendered body')->not->toContain('V2 rendered body')
        ->and($mail['data']['payload']['print']['blueprint'])->toBe($frozenBlueprint)
        ->and($mail['attachment_name'])->toBe('notice.pdf')
        ->and(is_file($mail['attachment_path']))->toBeFalse();
});
