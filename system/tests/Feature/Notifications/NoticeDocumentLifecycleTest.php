<?php

declare(strict_types=1);

use App\Authorization\CapabilityCatalog;
use App\DocumentTemplate\Services\DocumentThumbnailService;
use App\Foundation\Actions\RecordAuditEvent;
use App\Jobs\Notifications\RenderNoticeDocumentDraftThumbnailJob;
use App\Jobs\Notifications\RenderNoticeDocumentThumbnailJob;
use App\Models\Foundation\AuditEvent;
use App\Models\Foundation\Institution;
use App\Models\Notifications\CommunicationTemplate;
use App\Models\Notifications\CommunicationTemplateDocument;
use App\Models\Notifications\CommunicationTemplateDraft;
use App\Models\Notifications\NoticeDocumentDraft;
use App\Models\Notifications\NoticeDocumentVersion;
use App\Models\StaffProfile;
use App\Models\User;
use App\Notifications\NotificationDelivery;
use App\Notifications\Services\NoticeDocumentAttachmentCompiler;
use App\Notifications\Templates\Actions\NoticeDocumentWorkflow;
use App\Notifications\Templates\Actions\StoreCommunicationTemplateDocument;
use App\Notifications\Templates\NoticePrintContext;
use App\Notifications\Templates\Queries\CommunicationTemplateEditorQuery;
use App\Notifications\Templates\Queries\NoticeDocumentWorkspaceQuery;
use App\Notifications\Templates\Surfaces\NoticeTemplateSurface;
use App\ResultSheet\Support\ResultSheetPlaceholderService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use PhpOffice\PhpWord\IOFactory;
use PhpOffice\PhpWord\PhpWord;
use Spatie\Permission\Models\Permission;
use Symfony\Component\HttpKernel\Exception\HttpException;

beforeEach(function (): void {
    seedE2Foundation();
    $this->institution = Institution::firstOrFail();
    $this->actor = User::query()->where('email', 'guidance.counselor@nexiam.test')->firstOrFail();
    $this->registrar = User::query()->where('email', 'registrar.admin@nexiam.test')->firstOrFail();

    $this->family = CommunicationTemplate::query()->create([
        'institution_id' => $this->institution->id,
        'domain' => 'examination',
        'slug' => 'session-published',
        'event_key' => 'exam.session_published.v1',
        'name' => 'Examination session published and permit',
        'active_version' => 1,
        'family_revision' => 1,
        'document_revision' => 1,
    ]);

    $this->otherFamily = CommunicationTemplate::query()->create([
        'institution_id' => $this->institution->id,
        'domain' => 'examination',
        'slug' => 'reminder',
        'event_key' => 'exam.reminder.v1',
        'name' => 'Examination reminder',
        'active_version' => 1,
        'family_revision' => 1,
        'document_revision' => 1,
    ]);
});

function validDocxFile(string $content = 'Valid document content with {{application_no}}.'): UploadedFile
{
    $temp = tempnam(sys_get_temp_dir(), 'test_docx_').'.docx';
    $word = new PhpWord;
    $word->addSection()->addText($content);
    IOFactory::createWriter($word, 'Word2007')->save($temp);

    return new UploadedFile($temp, 'document.docx', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document', null, true);
}

function emptyDocxFile(): UploadedFile
{
    return validDocxFile(" \t \u{00A0} ");
}

function validBuilderState(): array
{
    $surface = new NoticeTemplateSurface('examination', 'session-published');
    $blueprint = $surface->defaultBlueprint();
    $blueprint['components'] = [
        ['type' => 'header', 'props' => ['title' => 'Examination Permit', 'subtitle' => 'Official Schedule & Permit']],
        ['type' => 'field', 'props' => ['label' => 'App No', 'binding' => 'application_no']],
    ];

    return [
        'source_kind' => 'builder',
        'document_asset_id' => null,
        'builder_json' => $blueprint,
        'paper_size' => 'a4',
        'orientation' => 'portrait',
    ];
}

function emptyBuilderState(): array
{
    $surface = new NoticeTemplateSurface('examination', 'session-published');
    $blueprint = $surface->defaultBlueprint();
    $blueprint['components'] = [];

    return [
        'source_kind' => 'builder',
        'document_asset_id' => null,
        'builder_json' => $blueprint,
        'paper_size' => 'a4',
        'orientation' => 'portrait',
    ];
}

test('document draft save allows empty work but preview and ready version reject it without writes', function (): void {
    $state = emptyBuilderState();
    $params = ['domain' => 'examination', 'slug' => 'session-published'];

    $saveRes = $this->actingAs($this->actor)->postJson(route('setup.notifications.templates.document.drafts.store', $params), [
        'base_version' => 0,
        'document_revision' => 1,
        'lock_version' => null,
        'idempotency_key' => (string) Str::uuid(),
        'state' => $state,
    ])->assertCreated()->json();

    expect($saveRes['document_draft'])->not->toBeNull()
        ->and(NoticeDocumentDraft::query()->count())->toBe(1);

    $renderSha = $saveRes['render_sha256'];

    $this->actingAs($this->actor)->postJson(route('setup.notifications.templates.document.preview', $params), [
        'base_version' => 0,
        'document_revision' => $saveRes['document_revision'],
        'lock_version' => $saveRes['document_draft']['lock_version'],
        'render_sha256' => $renderSha,
    ])->assertUnprocessable();

    expect(NoticeDocumentVersion::query()->count())->toBe(0);

    $this->actingAs($this->actor)->postJson(route('setup.notifications.templates.document.versions.store', $params), [
        'base_version' => 0,
        'document_revision' => $saveRes['document_revision'],
        'lock_version' => $saveRes['document_draft']['lock_version'],
        'idempotency_key' => (string) Str::uuid(),
        'render_sha256' => $renderSha,
        'preview_receipt' => 'invalid-proof-token',
    ])->assertUnprocessable();

    expect(NoticeDocumentVersion::query()->count())->toBe(0);
});

test('builder missing required application_no token is rejected on preview and version save', function (): void {
    $surface = new NoticeTemplateSurface('examination', 'session-published');
    $blueprint = $surface->defaultBlueprint();
    $blueprint['components'] = [
        ['type' => 'header', 'props' => ['title' => 'Notice Permit Header Only']],
    ];
    $state = [
        'source_kind' => 'builder',
        'document_asset_id' => null,
        'builder_json' => $blueprint,
        'paper_size' => 'a4',
        'orientation' => 'portrait',
    ];
    $params = ['domain' => 'examination', 'slug' => 'session-published'];

    $saveRes = $this->actingAs($this->actor)->postJson(route('setup.notifications.templates.document.drafts.store', $params), [
        'base_version' => 0,
        'document_revision' => 1,
        'lock_version' => null,
        'idempotency_key' => (string) Str::uuid(),
        'state' => $state,
    ])->assertCreated()->json();

    $this->actingAs($this->actor)->postJson(route('setup.notifications.templates.document.preview', $params), [
        'base_version' => 0,
        'document_revision' => $saveRes['document_revision'],
        'lock_version' => $saveRes['document_draft']['lock_version'],
        'render_sha256' => $saveRes['render_sha256'],
    ])->assertUnprocessable();

    $this->actingAs($this->actor)->postJson(route('setup.notifications.templates.document.versions.store', $params), [
        'base_version' => 0,
        'document_revision' => $saveRes['document_revision'],
        'lock_version' => $saveRes['document_draft']['lock_version'],
        'render_sha256' => $saveRes['render_sha256'],
        'preview_receipt' => 'invalid-proof-token',
        'idempotency_key' => 'missing-required-version',
    ])->assertUnprocessable();
    expect(NoticeDocumentVersion::query()->count())->toBe(0);
});

test('valid builder draft gets actual PDF preview, proof receipt, and saves ready immutable version', function (): void {
    $state = validBuilderState();
    $params = ['domain' => 'examination', 'slug' => 'session-published'];

    $saveRes = $this->actingAs($this->actor)->postJson(route('setup.notifications.templates.document.drafts.store', $params), [
        'base_version' => 0,
        'document_revision' => 1,
        'lock_version' => null,
        'idempotency_key' => (string) Str::uuid(),
        'state' => $state,
    ])->assertCreated()->json();

    $previewRes = $this->actingAs($this->actor)->postJson(route('setup.notifications.templates.document.preview', $params), [
        'base_version' => 0,
        'document_revision' => $saveRes['document_revision'],
        'lock_version' => $saveRes['document_draft']['lock_version'],
        'render_sha256' => $saveRes['render_sha256'],
    ])->assertOk()->json();

    expect($previewRes)->toHaveKeys(['render_sha256', 'pdf', 'preview_receipt', 'diagnostics'])
        ->and($previewRes['pdf']['pdf_base64'])->toBeString()
        ->and(base64_decode($previewRes['pdf']['pdf_base64']))->toStartWith('%PDF-')
        ->and($previewRes['preview_receipt'])->toBeString();
    $proof = json_decode(Crypt::decryptString($previewRes['preview_receipt']), true, 16, JSON_THROW_ON_ERROR);
    expect($proof['family_id'])->toBe($this->family->id)
        ->and($proof['actor_id'])->toBe($this->actor->id)
        ->and($proof['pdf_sha256'])->toBe(hash('sha256', base64_decode($previewRes['pdf']['pdf_base64'])));

    $versionRes = $this->actingAs($this->actor)->postJson(route('setup.notifications.templates.document.versions.store', $params), [
        'base_version' => 0,
        'document_revision' => $saveRes['document_revision'],
        'lock_version' => $saveRes['document_draft']['lock_version'],
        'idempotency_key' => 'test-version-save-key',
        'render_sha256' => $previewRes['render_sha256'],
        'preview_receipt' => $previewRes['preview_receipt'],
    ])->assertCreated()->json();

    expect($versionRes['document_version']['version'])->toBe(1)
        ->and($versionRes['document_draft'])->toBeNull()
        ->and(NoticeDocumentVersion::query()->count())->toBe(1)
        ->and(NoticeDocumentDraft::query()->count())->toBe(0);

    $auditCount = AuditEvent::query()->count();
    $replayRes = $this->actingAs($this->actor)->postJson(route('setup.notifications.templates.document.versions.store', $params), [
        'base_version' => 0,
        'document_revision' => $saveRes['document_revision'],
        'lock_version' => $saveRes['document_draft']['lock_version'],
        'idempotency_key' => 'test-version-save-key',
        'render_sha256' => $previewRes['render_sha256'],
        'preview_receipt' => $previewRes['preview_receipt'],
    ])->assertCreated()->json();

    expect($replayRes['replayed'])->toBeTrue()
        ->and(NoticeDocumentVersion::query()->count())->toBe(1);
    expect(AuditEvent::query()->count())->toBe($auditCount);
    $version = NoticeDocumentVersion::query()->firstOrFail();
    expect(fn () => $version->update(['version' => 2]))->toThrow(LogicException::class);
    expect(fn () => $version->delete())->toThrow(LogicException::class);
});

test('idempotency payload conflict returns 409 without mutation', function (): void {
    $state = validBuilderState();
    $params = ['domain' => 'examination', 'slug' => 'session-published'];

    $this->actingAs($this->actor)->postJson(route('setup.notifications.templates.document.drafts.store', $params), [
        'base_version' => 0,
        'document_revision' => 1,
        'lock_version' => null,
        'idempotency_key' => 'shared-key-123',
        'state' => $state,
    ])->assertCreated();

    $auditCount = AuditEvent::query()->count();
    $state['paper_size'] = 'letter';

    $this->actingAs($this->actor)->postJson(route('setup.notifications.templates.document.drafts.store', $params), [
        'base_version' => 0,
        'document_revision' => 1,
        'lock_version' => null,
        'idempotency_key' => 'shared-key-123',
        'state' => $state,
    ])->assertConflict();
    expect(NoticeDocumentDraft::query()->count())->toBe(1)
        ->and($this->family->fresh()->document_revision)->toBe(2)
        ->and(AuditEvent::query()->count())->toBe($auditCount);
});

test('stale document guards return 409 without write', function (): void {
    $state = validBuilderState();
    $params = ['domain' => 'examination', 'slug' => 'session-published'];

    $created = $this->actingAs($this->actor)->postJson(route('setup.notifications.templates.document.drafts.store', $params), [
        'base_version' => 0,
        'document_revision' => 1,
        'lock_version' => null,
        'idempotency_key' => (string) Str::uuid(),
        'state' => $state,
    ])->assertCreated()->json();

    $draftId = $created['document_draft']['id'];

    $this->actingAs($this->actor)->putJson(route('setup.notifications.templates.document.drafts.update', [...$params, 'draft' => $draftId]), [
        'base_version' => 0,
        'document_revision' => 999, // Stale revision
        'lock_version' => 1,
        'idempotency_key' => (string) Str::uuid(),
        'state' => $state,
    ])->assertConflict();
    $auditCount = AuditEvent::query()->count();
    $this->actingAs($this->actor)->putJson(route('setup.notifications.templates.document.drafts.update', [...$params, 'draft' => $draftId]), [
        'base_version' => 0, 'document_revision' => $created['document_revision'], 'lock_version' => 999,
        'idempotency_key' => 'stale-lock', 'state' => $state,
    ])->assertConflict()->assertJsonPath('error', 'document_conflict');
    expect($this->family->fresh()->document_revision)->toBe($created['document_revision'])
        ->and(AuditEvent::query()->count())->toBe($auditCount)
        ->and(NoticeDocumentDraft::query()->firstOrFail()->lock_version)->toBe(1);
});

test('docx upload requires valid docx file and binds to notice document draft privately', function (): void {
    $state = validBuilderState();
    $params = ['domain' => 'examination', 'slug' => 'session-published'];

    $created = $this->actingAs($this->actor)->postJson(route('setup.notifications.templates.document.drafts.store', $params), [
        'base_version' => 0,
        'document_revision' => 1,
        'lock_version' => null,
        'idempotency_key' => (string) Str::uuid(),
        'state' => $state,
    ])->assertCreated()->json();

    $draftId = $created['document_draft']['id'];
    $file = validDocxFile('Notice permit containing {{application_no}}.');

    $uploadRes = $this->actingAs($this->actor)->postJson(route('setup.notifications.templates.document.upload', $params), [
        'base_version' => 0,
        'document_revision' => $created['document_revision'],
        'lock_version' => $created['document_draft']['lock_version'],
        'idempotency_key' => (string) Str::uuid(),
        'document_draft_id' => $draftId,
        'document' => $file,
    ])->assertOk()->json();

    expect($uploadRes['document_draft']['state']['source_kind'])->toBe('docx')
        ->and($uploadRes['document_draft']['state']['document_asset_id'])->toBeString()
        ->and(CommunicationTemplateDocument::query()->count())->toBe(1);
});

test('pruning unreferenced documents preserves notice document draft and version assets', function (): void {
    $state = validBuilderState();
    $params = ['domain' => 'examination', 'slug' => 'session-published'];

    $created = $this->actingAs($this->actor)->postJson(route('setup.notifications.templates.document.drafts.store', $params), [
        'base_version' => 0,
        'document_revision' => 1,
        'lock_version' => null,
        'idempotency_key' => (string) Str::uuid(),
        'state' => $state,
    ])->assertCreated()->json();

    $draftId = $created['document_draft']['id'];
    $file = validDocxFile('Notice permit containing {{application_no}}.');

    $uploadRes = $this->actingAs($this->actor)->postJson(route('setup.notifications.templates.document.upload', $params), [
        'base_version' => 0,
        'document_revision' => $created['document_revision'],
        'lock_version' => $created['document_draft']['lock_version'],
        'idempotency_key' => (string) Str::uuid(),
        'document_draft_id' => $draftId,
        'document' => $file,
    ])->assertOk()->json();

    $assetId = $uploadRes['document_draft']['state']['document_asset_id'];
    expect($assetId)->toBeString();

    $pruned = app(StoreCommunicationTemplateDocument::class)->pruneUnreferenced($this->family);
    expect($pruned)->toBe(0)
        ->and(CommunicationTemplateDocument::query()->whereKey($assetId)->exists())->toBeTrue();
});

test('exact-version PDF and thumbnail endpoints require full source authorization and same family', function (): void {
    $state = validBuilderState();
    $params = ['domain' => 'examination', 'slug' => 'session-published'];

    $saveRes = $this->actingAs($this->actor)->postJson(route('setup.notifications.templates.document.drafts.store', $params), [
        'base_version' => 0,
        'document_revision' => 1,
        'lock_version' => null,
        'idempotency_key' => (string) Str::uuid(),
        'state' => $state,
    ])->assertCreated()->json();

    $previewRes = $this->actingAs($this->actor)->postJson(route('setup.notifications.templates.document.preview', $params), [
        'base_version' => 0,
        'document_revision' => $saveRes['document_revision'],
        'lock_version' => $saveRes['document_draft']['lock_version'],
        'render_sha256' => $saveRes['render_sha256'],
    ])->assertOk()->json();

    $versionRes = $this->actingAs($this->actor)->postJson(route('setup.notifications.templates.document.versions.store', $params), [
        'base_version' => 0,
        'document_revision' => $saveRes['document_revision'],
        'lock_version' => $saveRes['document_draft']['lock_version'],
        'idempotency_key' => (string) Str::uuid(),
        'render_sha256' => $previewRes['render_sha256'],
        'preview_receipt' => $previewRes['preview_receipt'],
    ])->assertCreated()->json();

    $versionId = $versionRes['document_version']['id'];

    // Authorized guidance counselor can download PDF
    $pdfRes = $this->actingAs($this->actor)->get(route('setup.notifications.templates.document.versions.pdf', [...$params, 'version' => $versionId]));
    $pdfRes->assertOk();
    expect($pdfRes->headers->get('Content-Type'))->toBe('application/pdf')
        ->and($pdfRes->getContent())->toStartWith('%PDF-');

    // Cross-office actor (Registrar) is denied for examination domain
    $this->actingAs($this->registrar)->get(route('setup.notifications.templates.document.versions.pdf', [...$params, 'version' => $versionId]))
        ->assertForbidden();

    // Cross-family request is denied / 404
    $this->actingAs($this->actor)->get(route('setup.notifications.templates.document.versions.pdf', [
        'domain' => 'examination',
        'slug' => 'reminder',
        'version' => $versionId,
    ]))->assertNotFound();
    $thumbnailRoute = route('setup.notifications.templates.document.versions.thumbnail', [...$params, 'version' => $versionId]);
    $this->actingAs($this->registrar)->get($thumbnailRoute)->assertForbidden();
    $this->actingAs($this->actor)->get(route('setup.notifications.templates.document.versions.thumbnail', [
        'domain' => 'examination', 'slug' => 'reminder', 'version' => $versionId,
    ]))->assertNotFound();
});

test('document mutations leave message pointer and family active version untouched', function (): void {
    $initialActive = $this->family->active_version;
    $initialRevision = $this->family->family_revision;
    $messageDraft = CommunicationTemplateDraft::query()->create([
        'communication_template_id' => $this->family->id,
        'base_version' => 1,
        'email_subject' => 'Preserved email subject',
        'email_body' => 'Preserved email body',
        'in_app_title' => 'Preserved title',
        'in_app_body' => 'Preserved body',
        'subject' => 'Preserved message',
        'body' => 'Preserved caption',
        'content_sha256' => str_repeat('a', 64),
    ]);
    $messageSnapshot = $messageDraft->fresh()->getRawOriginal();
    $delivery = NotificationDelivery::query()->create([
        'id' => (string) Str::uuid(), 'event_id' => (string) Str::uuid(),
        'event_type' => 'exam.session_published.v1', 'event_version' => 1,
        'recipient_type' => User::class, 'recipient_id' => $this->actor->id, 'channel' => 'in_app',
        'template_key' => 'examination.session-published', 'template_version' => 1,
        'payload' => ['frozen' => 'Preserved message'], 'redaction_class' => 'internal',
        'status' => 'delivered', 'attempts' => 1,
    ]);
    $deliverySnapshot = $delivery->fresh()->getRawOriginal();

    $state = validBuilderState();
    $params = ['domain' => 'examination', 'slug' => 'session-published'];

    $saveRes = $this->actingAs($this->actor)->postJson(route('setup.notifications.templates.document.drafts.store', $params), [
        'base_version' => 0,
        'document_revision' => 1,
        'lock_version' => null,
        'idempotency_key' => (string) Str::uuid(),
        'state' => $state,
    ])->assertCreated()->json();

    $previewRes = $this->actingAs($this->actor)->postJson(route('setup.notifications.templates.document.preview', $params), [
        'base_version' => 0,
        'document_revision' => $saveRes['document_revision'],
        'lock_version' => $saveRes['document_draft']['lock_version'],
        'render_sha256' => $saveRes['render_sha256'],
    ])->assertOk()->json();

    $this->actingAs($this->actor)->postJson(route('setup.notifications.templates.document.versions.store', $params), [
        'base_version' => 0,
        'document_revision' => $saveRes['document_revision'],
        'lock_version' => $saveRes['document_draft']['lock_version'],
        'idempotency_key' => (string) Str::uuid(),
        'render_sha256' => $previewRes['render_sha256'],
        'preview_receipt' => $previewRes['preview_receipt'],
    ])->assertCreated();

    $fresh = $this->family->fresh();
    expect($fresh->active_version)->toBe($initialActive)
        ->and($fresh->family_revision)->toBe($initialRevision)
        ->and($fresh->document_revision)->toBe(3);
    expect($messageDraft->fresh()->getRawOriginal())->toEqual($messageSnapshot)
        ->and($delivery->fresh()->getRawOriginal())->toEqual($deliverySnapshot);
});

test('document image upload stores verified image, exposes preview url, and resolves safely in builder', function (): void {
    $state = validBuilderState();
    $params = ['domain' => 'examination', 'slug' => 'session-published'];

    $saveRes = $this->actingAs($this->actor)->postJson(route('setup.notifications.templates.document.drafts.store', $params), [
        'base_version' => 0,
        'document_revision' => 1,
        'lock_version' => null,
        'idempotency_key' => (string) Str::uuid(),
        'state' => $state,
    ])->assertCreated()->json();

    $draftId = $saveRes['document_draft']['id'];

    $pngFile = UploadedFile::fake()->image('logo.png', 100, 100);

    $uploadPayload = [
        'base_version' => 0,
        'document_revision' => $saveRes['document_revision'],
        'lock_version' => $saveRes['document_draft']['lock_version'],
        'idempotency_key' => 'notice-image-upload-replay',
        'document_draft_id' => $draftId,
        'image' => $pngFile,
    ];
    $auditCount = AuditEvent::query()->count();
    $uploadRes = $this->actingAs($this->actor)->postJson(route('setup.notifications.templates.document.images.store', $params), $uploadPayload)
        ->assertCreated()->json();
    $auditCountAfterUpload = AuditEvent::query()->count();
    $replayRes = $this->actingAs($this->actor)->postJson(route('setup.notifications.templates.document.images.store', $params), $uploadPayload)
        ->assertCreated()->json();
    expect($replayRes['replayed'])->toBeTrue()
        ->and($replayRes['asset']['id'])->toBe($uploadRes['asset']['id'])
        ->and(CommunicationTemplateDocument::query()->count())->toBe(1)
        ->and(AuditEvent::query()->count())->toBe($auditCountAfterUpload);
    $pruned = app(StoreCommunicationTemplateDocument::class)->pruneUnreferenced($this->family);
    expect($pruned)->toBe(0)
        ->and(CommunicationTemplateDocument::query()->whereKey($uploadRes['asset']['id'])->exists())->toBeTrue()
        ->and($auditCountAfterUpload)->toBe($auditCount + 1);

    expect($uploadRes['asset']['id'])->toBeString()
        ->and($uploadRes['asset']['mime'])->toBe('image/png')
        ->and($uploadRes['preview_url'])->toBeString();

    $assetId = $uploadRes['asset']['id'];

    $imgRes = $this->actingAs($this->actor)->get(route('setup.notifications.templates.document.images.show', [...$params, 'asset' => $assetId]));
    $imgRes->assertOk();
    expect($imgRes->headers->get('Content-Type'))->toBe('image/png')
        ->and($imgRes->headers->get('Cache-Control'))->toContain('private')->toContain('no-store');

    // Add image component to builder blueprint referencing asset_id
    $blueprint = $state['builder_json'];
    $blueprint['components'][] = [
        'type' => 'image',
        'props' => [
            'asset_id' => $assetId,
            'src' => $uploadRes['preview_url'],
        ],
    ];
    $state['builder_json'] = $blueprint;

    $updateRes = $this->actingAs($this->actor)->putJson(route('setup.notifications.templates.document.drafts.update', [...$params, 'draft' => $draftId]), [
        'base_version' => 0,
        'document_revision' => $saveRes['document_revision'],
        'lock_version' => $saveRes['document_draft']['lock_version'],
        'idempotency_key' => (string) Str::uuid(),
        'state' => $state,
    ])->assertOk()->json();

    $previewRes = $this->actingAs($this->actor)->postJson(route('setup.notifications.templates.document.preview', $params), [
        'base_version' => 0,
        'document_revision' => $updateRes['document_revision'],
        'lock_version' => $updateRes['document_draft']['lock_version'],
        'render_sha256' => $updateRes['render_sha256'],
    ])->assertOk()->json();

    expect($previewRes['pdf']['pdf_base64'])->toBeString();
});

test('external and webp builder image sources are rejected before preview without writes', function (): void {
    $state = validBuilderState();
    $params = ['domain' => 'examination', 'slug' => 'session-published'];
    $auditCount = AuditEvent::query()->count();
    $baseGuards = [
        'base_version' => 0,
        'document_revision' => 1,
        'lock_version' => null,
    ];
    $state['builder_json']['components'][] = ['type' => 'image', 'props' => ['src' => 'https://evil.test/exploit.png']];

    $this->actingAs($this->actor)->postJson(route('setup.notifications.templates.document.drafts.store', $params), [
        ...$baseGuards,
        'idempotency_key' => 'external-image-source',
        'state' => $state,
    ])->assertUnprocessable();

    $state['builder_json']['components'][2]['props']['src'] = 'data:image/webp;base64,d2VicA==';
    $this->actingAs($this->actor)->postJson(route('setup.notifications.templates.document.drafts.store', $params), [
        ...$baseGuards,
        'idempotency_key' => 'webp-image-source',
        'state' => $state,
    ])->assertUnprocessable();

    expect(NoticeDocumentDraft::query()->count())->toBe(0)
        ->and(NoticeDocumentVersion::query()->count())->toBe(0)
        ->and(AuditEvent::query()->count())->toBe($auditCount);
});

test('exact DOCX upload retry returns the original source without duplicate revisions or audits', function (): void {
    $params = ['domain' => 'examination', 'slug' => 'session-published'];
    $state = validBuilderState();
    $created = $this->actingAs($this->actor)->postJson(route('setup.notifications.templates.document.drafts.store', $params), [
        'base_version' => 0,
        'document_revision' => 1,
        'lock_version' => null,
        'idempotency_key' => (string) Str::uuid(),
        'state' => $state,
    ])->assertCreated()->json();
    $payload = [
        'base_version' => 0,
        'document_revision' => $created['document_revision'],
        'lock_version' => $created['document_draft']['lock_version'],
        'idempotency_key' => 'notice-docx-upload-replay',
        'document_draft_id' => $created['document_draft']['id'],
        'document' => validDocxFile('Notice permit containing {{application_no}}.'),
    ];

    $upload = $this->actingAs($this->actor)->postJson(route('setup.notifications.templates.document.upload', $params), $payload)
        ->assertOk()->json();
    $auditCount = AuditEvent::query()->count();
    $revision = $upload['document_revision'];
    $lockVersion = $upload['document_draft']['lock_version'];
    $replay = $this->actingAs($this->actor)->postJson(route('setup.notifications.templates.document.upload', $params), $payload)
        ->assertOk()->json();

    expect($replay['replayed'])->toBeTrue()
        ->and($replay['source']['asset_id'])->toBe($upload['source']['asset_id'])
        ->and($replay['document_revision'])->toBe($revision)
        ->and($replay['document_draft']['lock_version'])->toBe($lockVersion)
        ->and(CommunicationTemplateDocument::query()->count())->toBe(1)
        ->and(AuditEvent::query()->count())->toBe($auditCount);
});

test('ready version warm thumbnail job actually stores png thumbnail in document-thumbnail store', function (): void {
    $state = validBuilderState();
    $params = ['domain' => 'examination', 'slug' => 'session-published'];

    $saveRes = $this->actingAs($this->actor)->postJson(route('setup.notifications.templates.document.drafts.store', $params), [
        'base_version' => 0,
        'document_revision' => 1,
        'lock_version' => null,
        'idempotency_key' => (string) Str::uuid(),
        'state' => $state,
    ])->assertCreated()->json();

    $previewRes = $this->actingAs($this->actor)->postJson(route('setup.notifications.templates.document.preview', $params), [
        'base_version' => 0,
        'document_revision' => $saveRes['document_revision'],
        'lock_version' => $saveRes['document_draft']['lock_version'],
        'render_sha256' => $saveRes['render_sha256'],
    ])->assertOk()->json();

    $versionRes = $this->actingAs($this->actor)->postJson(route('setup.notifications.templates.document.versions.store', $params), [
        'base_version' => 0,
        'document_revision' => $saveRes['document_revision'],
        'lock_version' => $saveRes['document_draft']['lock_version'],
        'idempotency_key' => (string) Str::uuid(),
        'render_sha256' => $previewRes['render_sha256'],
        'preview_receipt' => $previewRes['preview_receipt'],
    ])->assertCreated()->json();

    $versionId = (int) $versionRes['document_version']['id'];
    $version = NoticeDocumentVersion::query()->findOrFail($versionId);

    // Execute the warm job
    (new RenderNoticeDocumentThumbnailJob($versionId, $version->render_sha256))
        ->handle(app(NoticeDocumentWorkflow::class));

    $thumbBytes = app(NoticeDocumentWorkflow::class)->thumbnail($this->family, $version, $this->actor);
    expect($thumbBytes)->not->toBeNull()
        ->and($thumbBytes)->toStartWith("\x89PNG");
});

test('warm draft thumbnail refuses to write if draft was modified before job completes', function (): void {
    $state = validBuilderState();
    $params = ['domain' => 'examination', 'slug' => 'session-published'];

    $saveRes = $this->actingAs($this->actor)->postJson(route('setup.notifications.templates.document.drafts.store', $params), [
        'base_version' => 0,
        'document_revision' => 1,
        'lock_version' => null,
        'idempotency_key' => (string) Str::uuid(),
        'state' => $state,
    ])->assertCreated()->json();

    $draftId = (int) $saveRes['document_draft']['id'];
    $draft = NoticeDocumentDraft::query()->findOrFail($draftId);
    $initialRender = $saveRes['render_sha256'];

    // Mutate draft in DB before the job executes
    $state['paper_size'] = 'letter';
    $this->actingAs($this->actor)->putJson(route('setup.notifications.templates.document.drafts.update', [...$params, 'draft' => $draftId]), [
        'base_version' => 0,
        'document_revision' => $saveRes['document_revision'],
        'lock_version' => $saveRes['document_draft']['lock_version'],
        'idempotency_key' => (string) Str::uuid(),
        'state' => $state,
    ])->assertOk();

    // Now run the job with the initial render sha
    (new RenderNoticeDocumentDraftThumbnailJob($draftId, $initialRender))
        ->handle(app(NoticeDocumentWorkflow::class));

    // Check that no thumbnail was stored under the stale initial digest
    $thumbService = app(DocumentThumbnailService::class);
    $storedBytes = $thumbService->read('notice-document-drafts', $draftId, $thumbService->fingerprint($initialRender));
    expect($storedBytes)->toBeNull();
});

test('empty and required-missing Word drafts save but cannot preview or become ready', function (string $content): void {
    $workflow = app(NoticeDocumentWorkflow::class);
    $created = $workflow->save($this->family, [
        'base_version' => 0, 'document_revision' => 1, 'lock_version' => null,
        'idempotency_key' => 'word-draft', 'state' => emptyBuilderState(),
    ], $this->actor);
    $uploaded = $workflow->upload($this->family, validDocxFile($content), [
        'base_version' => 0, 'document_revision' => $created['document_revision'],
        'lock_version' => $created['document_draft']['lock_version'],
        'document_draft_id' => $created['document_draft']['id'], 'idempotency_key' => 'word-source',
    ], $this->actor);
    $guards = [
        'base_version' => 0, 'document_revision' => $uploaded['document_revision'],
        'lock_version' => $uploaded['document_draft']['lock_version'], 'render_sha256' => $uploaded['render_sha256'],
    ];
    $params = ['domain' => 'examination', 'slug' => 'session-published'];
    $auditCount = AuditEvent::query()->count();
    $this->actingAs($this->actor)->postJson(route('setup.notifications.templates.document.preview', $params), $guards)->assertUnprocessable();
    $this->postJson(route('setup.notifications.templates.document.versions.store', $params), [
        ...$guards, 'idempotency_key' => 'word-ready', 'preview_receipt' => 'invalid-receipt',
    ])->assertUnprocessable();
    expect(NoticeDocumentDraft::query()->count())->toBe(1)
        ->and(NoticeDocumentVersion::query()->count())->toBe(0)
        ->and(AuditEvent::query()->count())->toBe($auditCount);
})->with(['whitespace' => [" \t\u{00A0} "], 'required missing' => ['Examination permit without its reference']]);

test('changed uploaded bytes and a different draft route cannot replay an operation', function (): void {
    $workflow = app(NoticeDocumentWorkflow::class);
    $created = $workflow->save($this->family, [
        'base_version' => 0, 'document_revision' => 1, 'lock_version' => null,
        'idempotency_key' => 'create-for-byte-binding', 'state' => validBuilderState(),
    ], $this->actor);
    $guards = [
        'base_version' => 0, 'document_revision' => $created['document_revision'],
        'lock_version' => $created['document_draft']['lock_version'],
        'document_draft_id' => $created['document_draft']['id'], 'idempotency_key' => 'bound-upload',
    ];
    $upload = $workflow->upload($this->family, validDocxFile('Original {{application_no}}'), $guards, $this->actor);
    $count = AuditEvent::query()->count();
    $params = ['domain' => 'examination', 'slug' => 'session-published'];
    $this->actingAs($this->actor)->postJson(route('setup.notifications.templates.document.upload', $params), [
        ...$guards, 'document' => validDocxFile('Changed {{application_no}}'),
    ])->assertConflict();
    expect(CommunicationTemplateDocument::query()->count())->toBe(1)
        ->and(AuditEvent::query()->count())->toBe($count);
    $payload = [
        'base_version' => 0, 'document_revision' => $upload['document_revision'],
        'lock_version' => $upload['document_draft']['lock_version'], 'idempotency_key' => 'route-bound-update',
        'state' => validBuilderState(),
    ];
    $this->putJson(route('setup.notifications.templates.document.drafts.update', [
        ...$params, 'draft' => $created['document_draft']['id'],
    ]), $payload)->assertOk();
    $after = AuditEvent::query()->count();
    $this->putJson(route('setup.notifications.templates.document.drafts.update', [
        ...$params, 'draft' => $created['document_draft']['id'] + 1,
    ]), $payload)->assertConflict();
    expect(AuditEvent::query()->count())->toBe($after);
});

test('document render pins cannot substitute unverified compiler paper or catalog identities', function (): void {
    $workflow = app(NoticeDocumentWorkflow::class);
    $state = validBuilderState();
    $digest = $workflow->renderDigest($this->family, $state);
    $pins = [
        'source_checksum' => null, 'image_sha256s' => [], 'paper_size' => 'a4', 'orientation' => 'portrait',
        'catalog' => NoticePrintContext::catalog($this->family),
        'compiler' => 'notice-document-compiler-v1', 'renderer' => 'notice-document-renderer-v1',
        'synthetic_context' => 'notice-document-synthetic-v1',
    ];
    expect($workflow->renderDigest($this->family, [...$state, 'pins' => $pins]))->toBe($digest);
    foreach (['paper_size' => 'letter', 'compiler' => 'unverified', 'renderer' => 'unverified', 'synthetic_context' => 'unverified', 'catalog' => []] as $key => $value) {
        try {
            $workflow->renderDigest($this->family, [...$state, 'pins' => [...$pins, $key => $value]]);
            $this->fail('Changed render pins must be rejected.');
        } catch (HttpException $exception) {
            expect($exception->getStatusCode())->toBe(422);
        }
    }
});

test('draft thumbnail job stores actual PNG and fences a mutation during compilation', function (): void {
    $workflow = app(NoticeDocumentWorkflow::class);
    $created = $workflow->save($this->family, [
        'base_version' => 0, 'document_revision' => 1, 'lock_version' => null,
        'idempotency_key' => 'thumbnail-draft', 'state' => validBuilderState(),
    ], $this->actor);
    $draft = NoticeDocumentDraft::query()->firstOrFail();
    $job = new RenderNoticeDocumentDraftThumbnailJob((int) $draft->id, $created['render_sha256']);
    $job->handle($workflow);
    $thumbnails = app(DocumentThumbnailService::class);
    expect($thumbnails->read('notice-document-drafts', (int) $draft->id, $thumbnails->fingerprint($created['render_sha256'])))->toStartWith("\x89PNG");
    $thumbnails->purge('notice-document-drafts', (int) $draft->id);
    $compiler = app(NoticeDocumentAttachmentCompiler::class);
    $mock = Mockery::mock(NoticeDocumentAttachmentCompiler::class);
    $mock->shouldReceive('compileBuilderPdf')->once()->andReturnUsing(function (...$args) use ($compiler, $draft): string {
        $path = $compiler->compileBuilderPdf(...$args);
        $draft->update(['lock_version' => $draft->lock_version + 1]);

        return $path;
    });
    app()->instance(NoticeDocumentAttachmentCompiler::class, $mock);
    $job->handle(app(NoticeDocumentWorkflow::class));
    expect($thumbnails->read('notice-document-drafts', (int) $draft->id, $thumbnails->fingerprint($created['render_sha256'])))->toBeNull();
});

test('only the three canonical document families expose the independent workspace without creating drafts', function (): void {
    $query = app(NoticeDocumentWorkspaceQuery::class);
    foreach ([
        ['admissions', 'accepted', 'admission.accepted.v1', $this->registrar],
        ['examination', 'session-published', 'exam.session_published.v1', $this->actor],
        ['results', 'released-online', 'result.released.online.v1', $this->actor],
    ] as [$domain, $slug, $event, $actor]) {
        CommunicationTemplate::query()->firstOrCreate([
            'institution_id' => $this->institution->id, 'domain' => $domain, 'slug' => $slug,
        ], ['event_key' => $event, 'name' => 'Canonical document family']);
        $workspace = $query->editor($actor, $domain, $slug);
        expect($workspace)->toHaveKeys(['family', 'workspace', 'surface', 'builderDefaults', 'builderCatalog',
            'builderCapabilities', 'placeholderGroups', 'sampleContext', 'endpoints', 'return_url'])
            ->and($workspace['workspace']['draft'])->toBeNull()
            ->and($workspace['builderDefaults']['components'])->toBe([]);
        $parameters = ['domain' => $domain, 'slug' => $slug];
        $this->actingAs($actor)->get(route('setup.notifications.templates.document.edit', $parameters))
            ->assertOk()->assertInertia(fn ($page) => $page->component('Setup/Notifications/Templates/PhysicalDocument'));
        $this->getJson(route('setup.notifications.templates.document.editor-state', $parameters))
            ->assertOk()->assertJsonPath('family.slug', $slug);
    }
    expect(NoticeDocumentDraft::query()->count())->toBe(0);
    $this->actingAs($this->actor)->getJson(route('setup.notifications.templates.document.editor-state', [
        'domain' => 'examination', 'slug' => 'reminder',
    ]))->assertNotFound();
});

test('Results metadata-only readers see no protected document summary or PDF image thumbnail content', function (): void {
    CommunicationTemplate::query()->create([
        'institution_id' => $this->institution->id, 'domain' => 'results', 'slug' => 'released-online',
        'event_key' => 'result.released.online.v1', 'name' => 'Canonical scorecard',
    ]);
    $reader = User::factory()->create();
    StaffProfile::query()->create([
        'user_id' => $reader->id, 'institution_id' => $this->institution->id,
        'office' => 'guidance', 'status' => StaffProfile::STATUS_ACTIVE,
    ]);
    $reader->givePermissionTo(Permission::findOrCreate(CapabilityCatalog::GUIDANCE_ASSESSMENT_CATALOG, 'web'));
    $projection = app(CommunicationTemplateEditorQuery::class)->family($reader, 'results', 'released-online');
    expect($projection)->not->toHaveKey('document_summary')
        ->and($projection['endpoints'] ?? [])->not->toHaveKey('document_edit');
    $params = ['domain' => 'results', 'slug' => 'released-online'];
    foreach ([
        route('setup.notifications.templates.document.editor-state', $params),
        route('setup.notifications.templates.document.versions.pdf', [...$params, 'version' => 1]),
        route('setup.notifications.templates.document.versions.thumbnail', [...$params, 'version' => 1]),
        route('setup.notifications.templates.document.drafts.thumbnail', [...$params, 'draft' => 1]),
        route('setup.notifications.templates.document.images.show', [...$params, 'asset' => (string) Str::uuid()]),
    ] as $url) {
        $this->actingAs($reader)->getJson($url)->assertForbidden();
    }
});

test('scorecards require applicant_reference while offer documents require application_no', function (string $domain, string $slug, string $event, string $required): void {
    $family = CommunicationTemplate::query()->create([
        'institution_id' => $this->institution->id, 'domain' => $domain, 'slug' => $slug,
        'event_key' => $event, 'name' => 'Required source family',
    ]);
    $actor = $domain === 'admissions' ? $this->registrar : $this->actor;
    $state = validBuilderState();
    $state['builder_json']['components'][1]['props']['binding'] = $required;
    $workflow = app(NoticeDocumentWorkflow::class);
    $missing = $state;
    $missing['builder_json']['components'] = [$state['builder_json']['components'][0]];
    $created = $workflow->save($family, [
        'base_version' => 0, 'document_revision' => 1, 'lock_version' => null,
        'state' => $missing, 'idempotency_key' => 'required-source',
    ], $actor);
    $this->actingAs($actor)->postJson(route('setup.notifications.templates.document.preview', [
        'domain' => $domain, 'slug' => $slug,
    ]), [
        'base_version' => 0, 'document_revision' => $created['document_revision'],
        'lock_version' => $created['document_draft']['lock_version'], 'render_sha256' => $created['render_sha256'],
    ])->assertUnprocessable();
    $created = $workflow->save($family, [
        'base_version' => 0, 'document_revision' => $created['document_revision'],
        'lock_version' => $created['document_draft']['lock_version'],
        'state' => $state, 'idempotency_key' => 'required-source-filled',
    ], $actor, (int) $created['document_draft']['id']);
    $preview = $workflow->preview($family, [
        'base_version' => 0, 'document_revision' => $created['document_revision'],
        'lock_version' => $created['document_draft']['lock_version'], 'render_sha256' => $created['render_sha256'],
    ], $actor);
    expect(base64_decode($preview['pdf']['pdf_base64']))->toStartWith('%PDF-');
})->with([
    ['results', 'released-online', 'result.released.online.v1', 'applicant_reference'],
    ['admissions', 'accepted', 'admission.accepted.v1', 'application_no'],
]);

test('retention protects standalone draft ready version and replay receipt assets', function (): void {
    $workflow = app(NoticeDocumentWorkflow::class);
    $sources = app(StoreCommunicationTemplateDocument::class);
    $state = validBuilderState();
    $image = $sources->storeImage($this->family, UploadedFile::fake()->image('retained.png', 32, 32), $this->actor);
    $state['builder_json']['components'][] = ['type' => 'image', 'props' => ['asset_id' => $image->id, 'src' => '']];
    $created = $workflow->save($this->family, [
        'base_version' => 0, 'document_revision' => 1, 'lock_version' => null,
        'state' => $state, 'idempotency_key' => 'retention-draft',
    ], $this->actor);
    DB::table('notice_document_receipts')->where('canonical_slug', $this->family->slug)->delete();
    expect($sources->pruneUnreferenced($this->family))->toBe(0);
    $guards = [
        'base_version' => 0, 'document_revision' => $created['document_revision'],
        'lock_version' => $created['document_draft']['lock_version'], 'render_sha256' => $created['render_sha256'],
    ];
    $preview = $workflow->preview($this->family, $guards, $this->actor);
    $version = $workflow->createVersion($this->family, [
        ...$guards, 'idempotency_key' => 'retention-version', 'preview_receipt' => $preview['preview_receipt'],
    ], $this->actor);
    DB::table('notice_document_receipts')->where('canonical_slug', $this->family->slug)->delete();
    expect($sources->pruneUnreferenced($this->family))->toBe(0)
        ->and(NoticeDocumentDraft::query()->count())->toBe(0)
        ->and(NoticeDocumentVersion::query()->count())->toBe(1);
    $draft = $workflow->save($this->family, [
        'base_version' => 1, 'document_revision' => $version['document_revision'], 'lock_version' => null,
        'idempotency_key' => 'retention-receipt-draft', 'state' => emptyBuilderState(),
    ], $this->actor);
    $uploaded = $workflow->upload($this->family, validDocxFile(), [
        'base_version' => 1, 'document_revision' => $draft['document_revision'],
        'lock_version' => $draft['document_draft']['lock_version'], 'document_draft_id' => $draft['document_draft']['id'],
        'idempotency_key' => 'retention-upload',
    ], $this->actor);
    $workflow->discard($this->family, [
        'base_version' => 1, 'document_revision' => $uploaded['document_revision'],
        'lock_version' => $uploaded['document_draft']['lock_version'], 'idempotency_key' => 'retention-discard',
    ], $this->actor, (int) $uploaded['document_draft']['id']);
    expect($sources->pruneUnreferenced($this->family))->toBe(0)
        ->and($sources->verified($this->family, $uploaded['source']['asset_id'])->id)->toBe($uploaded['source']['asset_id']);
});

test('image content rejects a same-office foreign family and a corrupted checksum', function (): void {
    $sources = app(StoreCommunicationTemplateDocument::class);
    $foreign = CommunicationTemplate::query()->create([
        'institution_id' => $this->institution->id, 'domain' => 'results', 'slug' => 'released-online',
        'event_key' => 'result.released.online.v1', 'name' => 'Other eligible family',
    ]);
    $image = $sources->storeImage($foreign, UploadedFile::fake()->image('foreign.png', 16, 16), $this->actor);
    $params = ['domain' => 'examination', 'slug' => 'session-published', 'asset' => $image->id];
    $this->actingAs($this->actor)->getJson(route('setup.notifications.templates.document.images.show', $params))->assertUnprocessable();
    Storage::disk('local')->put($image->storage_path, 'corrupt image bytes');
    $this->getJson(route('setup.notifications.templates.document.images.show', [
        'domain' => 'results', 'slug' => 'released-online', 'asset' => $image->id,
    ]))->assertUnprocessable();
});

test('ready warm job ignores stale expected digest and a definition changed during compilation', function (): void {
    $workflow = app(NoticeDocumentWorkflow::class);
    $created = $workflow->save($this->family, [
        'base_version' => 0, 'document_revision' => 1, 'lock_version' => null,
        'idempotency_key' => 'warm-version-draft', 'state' => validBuilderState(),
    ], $this->actor);
    $guards = [
        'base_version' => 0, 'document_revision' => $created['document_revision'],
        'lock_version' => $created['document_draft']['lock_version'], 'render_sha256' => $created['render_sha256'],
    ];
    $preview = $workflow->preview($this->family, $guards, $this->actor);
    $ready = $workflow->createVersion($this->family, [
        ...$guards, 'idempotency_key' => 'warm-version-ready', 'preview_receipt' => $preview['preview_receipt'],
    ], $this->actor);
    $id = (int) $ready['document_version']['id'];
    $digest = $ready['document_version']['render_sha256'];
    $thumbs = app(DocumentThumbnailService::class);
    $thumbs->purge('notice-documents', $id);
    (new RenderNoticeDocumentThumbnailJob($id, str_repeat('f', 64)))->handle($workflow);
    expect($thumbs->read('notice-documents', $id, $thumbs->fingerprint(str_repeat('f', 64))))->toBeNull();
    $compiler = app(NoticeDocumentAttachmentCompiler::class);
    $mock = Mockery::mock(NoticeDocumentAttachmentCompiler::class);
    $mock->shouldReceive('compileBuilderPdf')->once()->andReturnUsing(function (...$args) use ($compiler, $id): string {
        $path = $compiler->compileBuilderPdf(...$args);
        DB::table('notice_document_versions')->where('id', $id)->update(['render_sha256' => str_repeat('e', 64)]);

        return $path;
    });
    app()->instance(NoticeDocumentAttachmentCompiler::class, $mock);
    (new RenderNoticeDocumentThumbnailJob($id, $digest))->handle(app(NoticeDocumentWorkflow::class));
    expect($thumbs->read('notice-documents', $id, $thumbs->fingerprint($digest)))->toBeNull();
});

test('failed upload transaction removes newly stored private file and leaves guards unchanged', function (): void {
    Storage::fake('local');
    $workflow = app(NoticeDocumentWorkflow::class);
    $created = $workflow->save($this->family, [
        'base_version' => 0, 'document_revision' => 1, 'lock_version' => null,
        'idempotency_key' => 'failed-upload-draft', 'state' => emptyBuilderState(),
    ], $this->actor);
    $audit = Mockery::mock(RecordAuditEvent::class);
    $audit->shouldReceive('handle')->once()->andThrow(new RuntimeException('audit_failed'));
    app()->instance(RecordAuditEvent::class, $audit);
    $failedWorkflow = app(NoticeDocumentWorkflow::class);
    expect(fn () => $failedWorkflow->upload($this->family, validDocxFile(), [
        'base_version' => 0, 'document_revision' => $created['document_revision'],
        'lock_version' => $created['document_draft']['lock_version'], 'document_draft_id' => $created['document_draft']['id'],
        'idempotency_key' => 'failed-upload',
    ], $this->actor))->toThrow(RuntimeException::class, 'audit_failed');
    expect(Storage::disk('local')->allFiles('communication-templates/'.$this->family->id))->toBe([])
        ->and(CommunicationTemplateDocument::query()->count())->toBe(0)
        ->and($this->family->fresh()->document_revision)->toBe($created['document_revision'])
        ->and(NoticeDocumentDraft::query()->firstOrFail()->lock_version)->toBe($created['document_draft']['lock_version']);
});

test('document discard exact replay precedes stale guards and a later autosave cannot resurrect it', function (): void {
    $workflow = app(NoticeDocumentWorkflow::class);
    $created = $workflow->save($this->family, [
        'base_version' => 0, 'document_revision' => 1, 'lock_version' => null,
        'idempotency_key' => 'discard-draft', 'state' => validBuilderState(),
    ], $this->actor);
    $guards = [
        'base_version' => 0, 'document_revision' => $created['document_revision'],
        'lock_version' => $created['document_draft']['lock_version'], 'idempotency_key' => 'discard-once',
    ];
    $id = (int) $created['document_draft']['id'];
    $discarded = $workflow->discard($this->family, $guards, $this->actor, $id);
    $count = AuditEvent::query()->count();
    $replayed = $workflow->discard($this->family, $guards, $this->actor, $id);
    expect($replayed['replayed'])->toBeTrue()
        ->and($replayed['document_revision'])->toBe($discarded['document_revision'])
        ->and(AuditEvent::query()->count())->toBe($count);
    $this->actingAs($this->actor)->putJson(route('setup.notifications.templates.document.drafts.update', [
        'domain' => 'examination', 'slug' => 'session-published', 'draft' => $id,
    ]), [...$guards, 'idempotency_key' => 'late-autosave', 'state' => validBuilderState()])
        ->assertConflict()->assertJsonPath('error', 'document_conflict');
    expect(NoticeDocumentDraft::query()->count())->toBe(0);
});

test('preview receipts reject cryptographic tampering and wrong owning actor family or draft', function (): void {
    $workflow = app(NoticeDocumentWorkflow::class);
    $created = $workflow->save($this->family, [
        'base_version' => 0, 'document_revision' => 1, 'lock_version' => null,
        'state' => validBuilderState(), 'idempotency_key' => 'proof-draft',
    ], $this->actor);
    $guards = [
        'base_version' => 0, 'document_revision' => $created['document_revision'],
        'lock_version' => $created['document_draft']['lock_version'], 'render_sha256' => $created['render_sha256'],
    ];
    $preview = $workflow->preview($this->family, $guards, $this->actor);
    $proof = json_decode(Crypt::decryptString($preview['preview_receipt']), true, 16, JSON_THROW_ON_ERROR);
    $receipts = [$preview['preview_receipt'].'tampered'];
    foreach (['actor_id' => 99999, 'family_id' => (string) Str::uuid(), 'draft_id' => 99999, 'document_revision' => 99999] as $key => $value) {
        $receipts[] = Crypt::encryptString(json_encode([...$proof, $key => $value], JSON_THROW_ON_ERROR));
    }
    $before = AuditEvent::query()->count();
    foreach ($receipts as $index => $receipt) {
        $this->actingAs($this->actor)->postJson(route('setup.notifications.templates.document.versions.store', [
            'domain' => 'examination', 'slug' => 'session-published',
        ]), [...$guards, 'idempotency_key' => 'invalid-proof-'.$index, 'preview_receipt' => $receipt])->assertUnprocessable();
    }
    expect(NoticeDocumentVersion::query()->count())->toBe(0)
        ->and(NoticeDocumentDraft::query()->count())->toBe(1)
        ->and(AuditEvent::query()->count())->toBe($before);
});

test('raw unsupported builder styles cannot obtain preview or Ready proof without writes', function (array $props): void {
    $workflow = app(NoticeDocumentWorkflow::class);
    $state = validBuilderState();
    $state['builder_json']['components'] = [
        ['type' => 'field', 'props' => ['label' => '', 'binding' => 'application_no', ...$props]],
    ];
    $params = ['domain' => 'examination', 'slug' => 'session-published'];
    $beforeAudit = AuditEvent::query()->count();
    $this->actingAs($this->actor)->postJson(route('setup.notifications.templates.document.drafts.store', $params), [
        'base_version' => 0, 'document_revision' => 1, 'lock_version' => null,
        'idempotency_key' => 'unsafe-style-draft', 'state' => $state,
    ])->assertUnprocessable();
    expect(NoticeDocumentDraft::query()->count())->toBe(0)
        ->and(AuditEvent::query()->count())->toBe($beforeAudit)
        ->and($this->family->fresh()->document_revision)->toBe(1);

    // Previously persisted raw definitions must also fail the real HTTP gates.
    $draft = NoticeDocumentDraft::query()->create([
        'communication_template_id' => $this->family->id, 'base_version' => 0,
        'document_revision' => 1, 'lock_version' => 1, 'stage' => 1, 'state' => $state,
    ]);
    $guards = [
        'base_version' => 0, 'document_revision' => 1, 'lock_version' => 1,
        'render_sha256' => $workflow->renderDigest($this->family, $state),
    ];
    $before = $draft->fresh()->getRawOriginal();
    $beforeReceipts = DB::table('notice_document_receipts')->count();
    $this->actingAs($this->actor)->postJson(route('setup.notifications.templates.document.preview', $params), $guards)->assertUnprocessable();
    $this->actingAs($this->actor)->postJson(route('setup.notifications.templates.document.versions.store', $params), [
        ...$guards, 'idempotency_key' => 'unsafe-style-ready', 'preview_receipt' => 'invalid-proof',
    ])->assertUnprocessable();
    expect($draft->fresh()->getRawOriginal())->toEqual($before)
        ->and(NoticeDocumentVersion::query()->count())->toBe(0)
        ->and(DB::table('notice_document_receipts')->count())->toBe($beforeReceipts)
        ->and(AuditEvent::query()->count())->toBe($beforeAudit)
        ->and($this->family->fresh()->document_revision)->toBe(1);
})->with([
    'font declaration injection' => [['typography' => ['font_family' => 'Arial;display:none']]],
    'color declaration injection' => [['typography' => ['text_color' => '#000;visibility:hidden']]],
    'part style injection' => [['text_styles' => ['value' => ['font_family' => 'Arial;opacity:0']]]],
    'zero font size' => [['typography' => ['font_size_pt' => 0]]],
    'zero part font size' => [['text_styles' => ['value' => ['font_size_pt' => 0]]]],
    'unsupported hidden style' => [['typography' => ['display' => 'none']]],
    'border declaration injection' => [['appearance' => ['border_style' => 'solid;display:none']]],
]);

test('safe drafts with invisible required fields cannot certify even alongside visible content or images', function (string $mode): void {
    $workflow = app(NoticeDocumentWorkflow::class);
    $state = validBuilderState();
    if ($mode === 'transparent') {
        $state['builder_json']['components'][1]['props']['text_styles']['value']['text_color'] = 'transparent';
    } elseif ($mode === 'alpha zero') {
        $state['builder_json']['components'][1]['props']['text_styles']['value']['text_color'] = '#00000000';
    } elseif ($mode === 'white on white') {
        $state['builder_json']['components'][1]['props']['text_styles']['value']['text_color'] = '#fff';
    } else {
        $state['builder_json']['component_templates']['field'] = [
            'revision' => 1, 'html' => '<div style="'.$mode.'">{{slot:value}}</div>',
        ];
    }
    $image = app(StoreCommunicationTemplateDocument::class)->storeImage(
        $this->family, UploadedFile::fake()->image('legitimate.png', 24, 24), $this->actor);
    $state['builder_json']['components'][] = ['type' => 'image', 'props' => ['asset_id' => $image->id, 'src' => '']];
    $created = $workflow->save($this->family, [
        'base_version' => 0, 'document_revision' => 1, 'lock_version' => null,
        'idempotency_key' => 'invisible-draft', 'state' => $state,
    ], $this->actor);
    $draft = NoticeDocumentDraft::query()->firstOrFail();
    $before = $draft->fresh()->getRawOriginal();
    $beforeAudit = AuditEvent::query()->count();
    $beforeReceipts = DB::table('notice_document_receipts')->count();
    $guards = [
        'base_version' => 0, 'document_revision' => $created['document_revision'],
        'lock_version' => $created['document_draft']['lock_version'], 'render_sha256' => $created['render_sha256'],
    ];
    $params = ['domain' => 'examination', 'slug' => 'session-published'];
    $this->actingAs($this->actor)->postJson(route('setup.notifications.templates.document.preview', $params), $guards)->assertUnprocessable();
    $this->actingAs($this->actor)->postJson(route('setup.notifications.templates.document.versions.store', $params), [
        ...$guards, 'idempotency_key' => 'invisible-ready', 'preview_receipt' => 'invalid-proof',
    ])->assertUnprocessable();
    expect($draft->fresh()->getRawOriginal())->toEqual($before)
        ->and(NoticeDocumentVersion::query()->count())->toBe(0)
        ->and(DB::table('notice_document_receipts')->count())->toBe($beforeReceipts)
        ->and(AuditEvent::query()->count())->toBe($beforeAudit)
        ->and($this->family->fresh()->document_revision)->toBe($created['document_revision']);
})->with(['transparent', 'alpha zero', 'white on white', 'display:none', 'visibility:hidden', 'opacity:0', 'font-size:0']);

test('valid closed styling and raster content compile the same canonical definition that was validated and stored', function (): void {
    $state = validBuilderState();
    $state['builder_json']['components'][1]['props']['typography'] = [
        'font_family' => 'Georgia, serif', 'font_size_pt' => 12, 'font_weight' => 700,
        'text_color' => '#123456', 'text_align' => 'left', 'line_height' => 1.5,
    ];
    $state['builder_json']['components'][1]['props']['text_styles']['value'] = [
        'font_family' => 'Verdana, sans-serif', 'font_size_pt' => 14, 'text_color' => '#123456',
    ];
    $state['builder_json']['components'][1]['props']['appearance'] = [
        'background_color' => '#fff', 'border_color' => '#123456', 'border_style' => 'dashed', 'border_width_px' => 1,
    ];
    $image = app(StoreCommunicationTemplateDocument::class)->storeImage(
        $this->family, UploadedFile::fake()->image('valid-styled.png', 24, 24), $this->actor);
    $state['builder_json']['components'][] = ['type' => 'image', 'props' => ['asset_id' => $image->id, 'src' => '']];
    $workflow = app(NoticeDocumentWorkflow::class);
    $created = $workflow->save($this->family, [
        'base_version' => 0, 'document_revision' => 1, 'lock_version' => null,
        'idempotency_key' => 'styled-draft', 'state' => $state,
    ], $this->actor);
    $stored = NoticeDocumentDraft::query()->firstOrFail()->state['builder_json'];
    $compiler = app(NoticeDocumentAttachmentCompiler::class);
    $mock = Mockery::mock(NoticeDocumentAttachmentCompiler::class);
    $mock->shouldReceive('compileBuilderPdf')->twice()->andReturnUsing(function (array $blueprint, ...$args) use ($stored, $compiler): string {
        $expected = $stored;
        $expected['components'][2]['props']['src'] = $blueprint['components'][2]['props']['src'];
        expect($blueprint)->toEqual($expected)
            ->and($blueprint['components'][2]['props']['src'])->toStartWith('data:image/png;base64,');

        return $compiler->compileBuilderPdf($blueprint, ...$args);
    });
    app()->instance(NoticeDocumentAttachmentCompiler::class, $mock);
    try {
        $workflow = app(NoticeDocumentWorkflow::class);
        $guards = [
            'base_version' => 0, 'document_revision' => $created['document_revision'],
            'lock_version' => $created['document_draft']['lock_version'], 'render_sha256' => $created['render_sha256'],
        ];
        $preview = $workflow->preview($this->family, $guards, $this->actor);
        expect(base64_decode($preview['pdf']['pdf_base64']))->toStartWith('%PDF-');
        $ready = $workflow->createVersion($this->family, [
            ...$guards, 'idempotency_key' => 'styled-ready', 'preview_receipt' => $preview['preview_receipt'],
        ], $this->actor);
        expect(NoticeDocumentVersion::query()->firstOrFail()->state['builder_json'])->toEqual($stored)
            ->and($ready['return_url'])->toEndWith('/edit');
    } finally {
        app()->instance(NoticeDocumentAttachmentCompiler::class, $compiler);
        Mockery::close();
    }
});

test('scorecard aliases and score rows use one server synthetic context for actual PDF preview and Ready', function (string $source): void {
    $family = CommunicationTemplate::query()->firstOrCreate(
        ['institution_id' => $this->institution->id, 'domain' => 'results', 'slug' => 'released-online'],
        ['event_key' => 'result.released.online.v1', 'name' => 'Alias scorecard', 'active_version' => 1, 'family_revision' => 1, 'document_revision' => 1]
    );
    $family->documentDraft()?->delete();
    $family->documentVersions()->delete();
    $family->update(['document_revision' => 1]);
    $workflow = app(NoticeDocumentWorkflow::class);
    $state = validBuilderState();
    $state['builder_json']['components'] = [
        ['type' => 'field', 'props' => ['label' => 'Applicant No', 'binding' => 'applicant_no']],
        ['type' => 'field', 'props' => ['label' => 'Name', 'binding' => 'full_name']],
        ['type' => 'text', 'props' => ['text' => '{{scores_rows}}', 'scores' => [['raw' => 999999]]]],
    ];
    $created = $workflow->save($family, [
        'base_version' => 0, 'document_revision' => 1, 'lock_version' => null,
        'idempotency_key' => 'alias-draft', 'state' => $source === 'builder' ? $state : emptyBuilderState(),
    ], $this->actor);
    if ($source === 'docx') {
        $created = $workflow->upload($family, validDocxFile('{{applicant_no}} {{full_name}} {{scores_rows}}'), [
            'base_version' => 0, 'document_revision' => $created['document_revision'],
            'lock_version' => $created['document_draft']['lock_version'],
            'document_draft_id' => $created['document_draft']['id'], 'idempotency_key' => 'alias-source',
        ], $this->actor);
    }
    $compiler = app(NoticeDocumentAttachmentCompiler::class);
    $mock = Mockery::mock(NoticeDocumentAttachmentCompiler::class);
    $method = $source === 'builder' ? 'compileBuilderPdf' : 'compileNoticePdf';
    $mock->shouldReceive($method)->twice()->andReturnUsing(function (...$args) use ($compiler, $method, $source): string {
        $values = $args[$source === 'builder' ? 5 : 1];
        expect($values['applicant_no'])->toBe('SYNTHETIC-PRINT')
            ->and($values['full_name'])->toBe('Synthetic scorecard applicant')
            ->and($values['scores_rows'])->not->toContain('999999');
        $sample = app(ResultSheetPlaceholderService::class)->sampleData();
        foreach ($sample['scores'] as $score) {
            expect($values['scores_rows'])->toContain($score['domain']);
        }

        return $compiler->{$method}(...$args);
    });
    app()->instance(NoticeDocumentAttachmentCompiler::class, $mock);
    try {
        $workflow = app(NoticeDocumentWorkflow::class);
        $guards = [
            'base_version' => 0, 'document_revision' => $created['document_revision'],
            'lock_version' => $created['document_draft']['lock_version'], 'render_sha256' => $created['render_sha256'],
        ];
        $response = $this->actingAs($this->actor)->postJson(route('setup.notifications.templates.document.preview', [
            'domain' => 'results', 'slug' => 'released-online',
        ]), $guards);

        $response->assertOk();
        $preview = $response->json();
        expect($preview['diagnostics']['valid'])->toBeTrue()
            ->and(base64_decode($preview['pdf']['pdf_base64']))->toStartWith('%PDF-');
        $workflow->createVersion($family, [
            ...$guards, 'idempotency_key' => 'alias-ready', 'preview_receipt' => $preview['preview_receipt'],
        ], $this->actor);
        expect(NoticeDocumentVersion::query()->count())->toBe(1);
    } finally {
        app()->instance(NoticeDocumentAttachmentCompiler::class, $compiler);
        Mockery::close();
    }
    $this->actingAs($this->actor)->postJson(route('setup.notifications.templates.document.drafts.store', [
        'domain' => 'results', 'slug' => 'released-online',
    ]), [
        'base_version' => 1, 'document_revision' => $family->fresh()->document_revision, 'lock_version' => null,
        'idempotency_key' => 'caller-score-context', 'state' => [...$state, 'scores' => [['raw' => 999999]]],
    ])->assertUnprocessable();
    expect(NoticeDocumentDraft::query()->count())->toBe(0);
})->with(['builder', 'docx']);
test('raw style injection attempting to hide required fields is rejected or fails nonblank validation', function (array $componentProps): void {
    $workflow = app(NoticeDocumentWorkflow::class);
    $state = validBuilderState();
    $state['builder_json']['components'] = [
        [
            'type' => 'field',
            'props' => [
                'label' => 'App No',
                'binding' => 'application_no',
                ...$componentProps,
            ],
        ],
    ];

    $params = ['domain' => 'examination', 'slug' => 'session-published'];

    $response = $this->actingAs($this->actor)->postJson(route('setup.notifications.templates.document.drafts.store', $params), [
        'base_version' => 0,
        'document_revision' => 1,
        'lock_version' => null,
        'idempotency_key' => (string) Str::uuid(),
        'state' => $state,
    ]);

    if ($response->status() === 422) {
        expect($response->status())->toBe(422);

        return;
    }

    $draft = NoticeDocumentDraft::query()->create([
        'communication_template_id' => $this->family->id,
        'base_version' => 0,
        'document_revision' => 1,
        'lock_version' => 1,
        'stage' => 1,
        'state' => $state,
    ]);

    $guards = [
        'base_version' => 0,
        'document_revision' => 1,
        'lock_version' => 1,
        'render_sha256' => $workflow->renderDigest($this->family, $state),
    ];

    $this->actingAs($this->actor)
        ->postJson(route('setup.notifications.templates.document.preview', $params), $guards)
        ->assertUnprocessable();
})->with([
    'flat font_family injection' => [['font_family' => 'Arial;display:none']],
    'typography font_family injection' => [['typography' => ['font_family' => 'Arial;display:none']]],
    'flat style display:none' => [['style' => 'display:none']],
    'display property' => [['display' => 'none']],
    'visibility property' => [['visibility' => 'hidden']],
    'opacity zero' => [['opacity' => 0]],
]);

test('approved scorecard aliases like applicant_no pass validation and compile successfully', function (string $alias): void {
    $family = CommunicationTemplate::query()->firstOrCreate(
        ['institution_id' => $this->institution->id, 'domain' => 'results', 'slug' => 'released-online'],
        ['event_key' => 'result.released.online.v1', 'name' => 'Scorecard with alias '.$alias, 'active_version' => 1, 'family_revision' => 1, 'document_revision' => 1]
    );
    $family->documentDraft()?->delete();
    $family->documentVersions()->delete();
    $family->update(['document_revision' => 1]);

    $workflow = app(NoticeDocumentWorkflow::class);
    $state = validBuilderState();
    $state['builder_json']['components'] = [
        ['type' => 'header', 'props' => ['title' => 'Official Scorecard', 'subtitle' => 'Institutional Assessment']],
        ['type' => 'field', 'props' => ['label' => 'Applicant', 'binding' => $alias]],
        ['type' => 'text', 'props' => ['text' => '{{scores_rows}}']],
    ];

    $created = $workflow->save($family, [
        'base_version' => 0,
        'document_revision' => 1,
        'lock_version' => null,
        'idempotency_key' => 'alias-draft-'.$alias,
        'state' => $state,
    ], $this->actor);

    $guards = [
        'base_version' => 0,
        'document_revision' => $created['document_revision'],
        'lock_version' => $created['document_draft']['lock_version'],
        'render_sha256' => $created['render_sha256'],
    ];

    $preview = $workflow->preview($family, $guards, $this->actor);
    expect($preview['diagnostics']['valid'])->toBeTrue()
        ->and($preview['diagnostics']['missing_required'])->toBe([])
        ->and($preview['diagnostics']['missing_context'])->toBe([])
        ->and(base64_decode($preview['pdf']['pdf_base64']))->toStartWith('%PDF-');

    $versionResult = $workflow->createVersion($family, [
        ...$guards,
        'idempotency_key' => 'alias-version-'.$alias,
        'preview_receipt' => $preview['preview_receipt'],
    ], $this->actor);

    expect($versionResult['document_version']['version'])->toBe(1);
})->with([
    'applicant_no',
    'applicant_number',
    'ref',
    'app_no',
]);
