<?php

declare(strict_types=1);

use App\Models\Foundation\AuditEvent;
use App\Models\Foundation\Institution;
use App\Models\Notifications\CommunicationTemplate;
use App\Models\Notifications\CommunicationTemplateDocument;
use App\Models\Notifications\CommunicationTemplateReceipt;
use App\Models\User;
use App\Notifications\Templates\Actions\ImportCommunicationTemplateDocument;
use App\Notifications\Templates\Actions\ReviewCommunicationTemplateImport;
use App\Notifications\Templates\Actions\SaveCommunicationTemplateDraft;
use App\Notifications\Templates\Actions\StoreCommunicationTemplateDocument;
use App\Notifications\Templates\CommunicationTemplatePayload;
use App\Notifications\Templates\CommunicationTemplateState;
use App\Notifications\Templates\ProvisionCanonicalCommunicationTemplates;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;

beforeEach(function (): void {
    seedE2Foundation();
    Storage::fake('local');
    $this->institution = Institution::query()->firstOrFail();
    $this->actor = User::query()->where('email', 'registrar.admin@nexiam.test')->firstOrFail();
    app(ProvisionCanonicalCommunicationTemplates::class)->provision($this->institution, $this->actor);
    $this->template = CommunicationTemplate::query()->where('institution_id', $this->institution->id)->where('domain', 'admissions')->where('slug', 'accepted')->firstOrFail();
    $this->paths = [];
});

afterEach(function (): void {
    foreach ($this->paths as $path) {
        if (is_file($path)) {
            unlink($path);
        }
    }
});

function createLifecycleTestDocx(string $documentXml, array $extraFiles = []): string
{
    $path = tempnam(sys_get_temp_dir(), 'notice-import-test-');
    $zip = new ZipArchive;
    $zip->open($path, ZipArchive::CREATE | ZipArchive::OVERWRITE);
    $files = ['[Content_Types].xml' => '<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types"/>',
        '_rels/.rels' => '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"/>',
        'word/document.xml' => $documentXml,
        'word/_rels/document.xml.rels' => '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"/>'];
    foreach ([...$files, ...$extraFiles] as $name => $bytes) {
        $zip->addFromString($name, $bytes);
    }
    $zip->close();

    return $path;
}

function lifecycleUpload(object $test, string $body = '<w:p><w:r><w:t>Dear {{applicant_name}}, welcome.</w:t></w:r></w:p>', array $extra = []): UploadedFile
{
    $xml = '<w:document xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships" xmlns:wp="http://schemas.openxmlformats.org/drawingml/2006/wordprocessingDrawing" xmlns:a="http://schemas.openxmlformats.org/drawingml/2006/main"><w:body>'.$body.'</w:body></w:document>';
    $path = createLifecycleTestDocx($xml, $extra);
    $test->paths[] = $path;

    return new UploadedFile($path, 'notice.docx', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document', null, true);
}

function lifecycleGuards(CommunicationTemplate $template, string $key): array
{
    $template->refresh();

    return ['base_version' => $template->active_version, 'family_revision' => $template->family_revision,
        'lock_version' => $template->draft()->first()?->lock_version, 'idempotency_key' => $key];
}

function lifecycleReviewData(CommunicationTemplate $template, string $key): array
{
    $draft = $template->draft()->firstOrFail();
    $resolutions = [];
    foreach ($draft->import_provenance['findings'] as $finding) {
        $resolutions[$finding['id']] = ['disposition' => 'acknowledged'];
    }

    return [...lifecycleGuards($template, $key), 'report_id' => $draft->import_provenance['report_id'],
        'report_sha256' => $draft->import_provenance['report_sha256'],
        'email_blueprint_sha256' => CommunicationTemplateState::emailDigest($draft->email_blueprint), 'resolutions' => $resolutions];
}

function lifecycleRejected(callable $operation, int $status): void
{
    try {
        $operation();
    } catch (ValidationException $exception) {
        expect($exception->status)->toBe($status);

        return;
    } catch (HttpResponseException $exception) {
        expect($exception->getResponse()->getStatusCode())->toBe($status);

        return;
    } catch (HttpExceptionInterface $exception) {
        expect($exception->getStatusCode())->toBe($status);

        return;
    }
    throw new RuntimeException('Expected operation rejection.');
}

test('import stores document and updates draft with candidate blueprint and provenance', function (): void {
    $file = lifecycleUpload($this);
    $bytes = file_get_contents($file->getRealPath());
    $result = app(ImportCommunicationTemplateDocument::class)->handle($this->template, $file, $this->actor, false, lifecycleGuards($this->template, 'first-import'));
    $draft = $this->template->draft()->firstOrFail();
    $source = CommunicationTemplateDocument::findOrFail($result['import']['source_asset_id']);
    $report = CommunicationTemplateDocument::findOrFail($result['import']['report_id']);
    expect($result['_status'])->toBe(201)->and($result['import']['installed'])->toBeTrue()
        ->and($source->communication_template_id)->toBe($this->template->id)
        ->and($source->institution_id)->toBe($this->institution->id)
        ->and(Storage::disk('local')->get($source->storage_path))->toBe($bytes)
        ->and($source->checksum)->toBe(hash('sha256', $bytes))
        ->and($report->checksum)->toBe(hash('sha256', Storage::disk('local')->get($report->storage_path)))
        ->and($draft->email_blueprint)->toBe($result['import']['candidate_email_blueprint'])
        ->and($draft->import_provenance['review_acknowledgment'])->toBeNull()
        ->and($draft->document_mode)->toBe('none')
        ->and($draft->lock_version)->toBe(1)
        ->and($draft->content_sha256)->toBe(app(CommunicationTemplateState::class)->digest($this->template, $draft));
    // The original remains usable after the browser upload object is gone.
    unlink($file->getRealPath());
    expect(Storage::disk('local')->exists($source->storage_path))->toBeTrue();
    app(ReviewCommunicationTemplateImport::class)->handle($this->template, $this->actor, lifecycleReviewData($this->template, 'resumed-private-source-review'));
    expect($this->template->draft()->firstOrFail()->import_provenance['review_acknowledgment'])->not->toBeNull();
});

test('import does not overwrite draft when replace_email is false and authored content exists', function (): void {
    app(ImportCommunicationTemplateDocument::class)->handle($this->template, lifecycleUpload($this), $this->actor, false, lifecycleGuards($this->template, 'original'));
    app(ReviewCommunicationTemplateImport::class)->handle($this->template, $this->actor, lifecycleReviewData($this->template, 'original-review'));
    $before = $this->template->draft()->firstOrFail();
    $oldSource = $before->import_provenance['source_asset_id'];
    $oldReport = $before->import_provenance['report_id'];
    $result = app(ImportCommunicationTemplateDocument::class)->handle($this->template, lifecycleUpload($this, '<w:p><w:r><w:t>New candidate</w:t></w:r></w:p>'), $this->actor, false, lifecycleGuards($this->template, 'replacement'));
    $after = $this->template->draft()->firstOrFail();
    expect($result['import']['installed'])->toBeFalse()->and($after->email_blueprint)->toBe($before->email_blueprint)
        ->and($after->import_provenance['source_asset_id'])->not->toBe($oldSource)
        ->and($after->import_provenance['report_id'])->not->toBe($oldReport)
        ->and($after->import_provenance['review_acknowledgment'])->toBeNull()
        ->and($after->content_sha256)->not->toBe($before->content_sha256)
        ->and($after->lock_version)->toBe($before->lock_version + 1)
        ->and($after->subject)->toBe($before->subject)->and($after->body)->toBe($before->body);
    // Explicit compared replacement uses the ordinary guarded full-state save.
    $state = CommunicationTemplateState::state($after);
    $state['email_blueprint'] = $after->import_provenance['candidate_email_blueprint'];
    app(SaveCommunicationTemplateDraft::class)->save($this->template, [...lifecycleGuards($this->template, 'confirm-comparison'), 'state' => $state, 'stage' => 2], $this->actor);
    expect($this->template->draft()->firstOrFail()->email_blueprint)->toBe($result['import']['candidate_email_blueprint']);
});

test('import rejects unsafe docx before saving or mutating state', function (): void {
    $file = lifecycleUpload($this, '<w:p/>', ['word/payload.xml' => '<!DOCTYPE root [<!ENTITY x SYSTEM "file:///etc/passwd">]><root>&x;</root>']);
    $generation = $this->template->family_revision;
    lifecycleRejected(fn () => app(ImportCommunicationTemplateDocument::class)->handle($this->template, $file, $this->actor, false, lifecycleGuards($this->template, 'unsafe')), 422);
    expect(CommunicationTemplateDocument::where('communication_template_id', $this->template->id)->count())->toBe(0)
        ->and($this->template->fresh()->family_revision)->toBe($generation)
        ->and(Storage::disk('local')->allFiles())->toBe([]);
});

test('review records staff acknowledgment and recalculates content_sha256', function (): void {
    app(ImportCommunicationTemplateDocument::class)->handle($this->template, lifecycleUpload($this, '<w:p><w:r><w:t>Body</w:t><w:br w:type="page"/></w:r></w:p>'), $this->actor, false, lifecycleGuards($this->template, 'review-source'));
    $before = $this->template->draft()->firstOrFail();
    $data = lifecycleReviewData($this->template, 'review');
    $result = app(ReviewCommunicationTemplateImport::class)->handle($this->template, $this->actor, $data);
    $after = $this->template->draft()->firstOrFail();
    expect($after->import_provenance['review_acknowledgment']['actor_id'])->toBe((string) $this->actor->id)
        ->and($after->import_provenance['review_acknowledgment']['email_blueprint_sha256'])->toBe($data['email_blueprint_sha256'])
        ->and($after->lock_version)->toBe($before->lock_version + 1)->and($result['_status'])->toBe(200)
        ->and($after->content_sha256)->not->toBe($before->content_sha256);
    $replay = app(ReviewCommunicationTemplateImport::class)->handle($this->template, $this->actor, $data);
    expect($replay['replayed'])->toBeTrue()->and($this->template->draft()->firstOrFail()->lock_version)->toBe($after->lock_version);
});

test('review rejects mismatched report checksum', function (): void {
    app(ImportCommunicationTemplateDocument::class)->handle($this->template, lifecycleUpload($this), $this->actor, false, lifecycleGuards($this->template, 'report-source'));
    $before = $this->template->draft()->firstOrFail();
    $data = lifecycleReviewData($this->template, 'bad-report');
    $data['report_sha256'] = str_repeat('0', 64);
    lifecycleRejected(fn () => app(ReviewCommunicationTemplateImport::class)->handle($this->template, $this->actor, $data), 422);
    expect($this->template->draft()->firstOrFail()->lock_version)->toBe($before->lock_version);
});

test('review rejects when report contains unresolvable blocker findings', function (): void {
    app(ImportCommunicationTemplateDocument::class)->handle($this->template, lifecycleUpload($this), $this->actor, false, lifecycleGuards($this->template, 'blocked-source'));
    $draft = $this->template->draft()->firstOrFail();
    $provenance = $draft->import_provenance;
    $asset = CommunicationTemplateDocument::findOrFail($provenance['report_id']);
    $report = json_decode(Storage::disk('local')->get($asset->storage_path), true);
    $report['findings'][] = ['id' => 'security-blocker', 'source_part' => 'word/document.xml', 'source_location' => '/w:document', 'kind' => 'security_violation', 'severity' => 'blocker', 'disposition' => 'unwaivable', 'source_text' => ''];
    $bytes = CommunicationTemplatePayload::json($report);
    Storage::disk('local')->put($asset->storage_path, $bytes);
    $asset->update(['checksum' => hash('sha256', $bytes), 'file_size_bytes' => strlen($bytes)]);
    $provenance['report_sha256'] = $asset->checksum;
    $provenance['findings'] = $report['findings'];
    $draft->update(['import_provenance' => $provenance]);
    lifecycleRejected(fn () => app(ReviewCommunicationTemplateImport::class)->handle($this->template, $this->actor, lifecycleReviewData($this->template, 'waive-security')), 422);
    expect($this->template->draft()->firstOrFail()->import_provenance['review_acknowledgment'])->toBeNull();
});

test('critical omitted header needs restored source content not arbitrary corrected labels', function (): void {
    $extra = ['word/header1.xml' => '<w:hdr xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main"><w:p><w:r><w:t>Bring government ID</w:t></w:r></w:p></w:hdr>'];
    app(ImportCommunicationTemplateDocument::class)->handle($this->template, lifecycleUpload($this, '<w:p><w:r><w:t>Body</w:t></w:r></w:p>', $extra), $this->actor, false, lifecycleGuards($this->template, 'header-import'));
    $data = lifecycleReviewData($this->template, 'false-correction');
    $finding = array_key_first($data['resolutions']);
    $data['resolutions'][$finding] = ['disposition' => 'corrected', 'block_paths' => ['blocks.0']];
    lifecycleRejected(fn () => app(ReviewCommunicationTemplateImport::class)->handle($this->template, $this->actor, $data), 422);
    $draft = $this->template->draft()->firstOrFail();
    $state = CommunicationTemplateState::state($draft);
    $state['email_blueprint']['blocks'][] = ['type' => 'paragraph', 'text' => 'Unrelated new copy'];
    app(SaveCommunicationTemplateDraft::class)->save($this->template, [...lifecycleGuards($this->template, 'unrelated-edit'), 'state' => $state, 'stage' => 2], $this->actor);
    $data = lifecycleReviewData($this->template, 'unrelated-correction');
    $data['resolutions'][$finding] = ['disposition' => 'corrected', 'block_paths' => ['blocks.1']];
    lifecycleRejected(fn () => app(ReviewCommunicationTemplateImport::class)->handle($this->template, $this->actor, $data), 422);
    $draft = $this->template->draft()->firstOrFail();
    $state = CommunicationTemplateState::state($draft);
    $state['email_blueprint']['blocks'][1]['text'] = 'Bring government ID';
    app(SaveCommunicationTemplateDraft::class)->save($this->template, [...lifecycleGuards($this->template, 'real-edit'), 'state' => $state, 'stage' => 2], $this->actor);
    $data = lifecycleReviewData($this->template, 'real-correction');
    $data['resolutions'][$finding] = ['disposition' => 'corrected', 'block_paths' => ['blocks.1']];
    app(ReviewCommunicationTemplateImport::class)->handle($this->template, $this->actor, $data);
    expect($this->template->draft()->firstOrFail()->import_provenance['review_acknowledgment']['resolutions'][$finding]['disposition'])->toBe('corrected');
});

test('import persists image bytes as an opaque private family owned asset', function (): void {
    $png = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+jRZkAAAAASUVORK5CYII=');
    $body = '<w:p><w:r><w:t>Before</w:t><w:drawing><wp:inline><wp:extent cx="952500" cy="952500"/><wp:docPr id="1" descr="Institution logo"/><a:blip r:embed="image1"/></wp:inline></w:drawing><w:t>After</w:t></w:r></w:p>';
    $extra = ['word/media/caller-chosen.png' => $png, 'word/_rels/document.xml.rels' => '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="image1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/image" Target="media/caller-chosen.png"/></Relationships>'];
    $result = app(ImportCommunicationTemplateDocument::class)->handle($this->template, lifecycleUpload($this, $body, $extra), $this->actor, false, lifecycleGuards($this->template, 'image-import'));
    $block = $result['import']['candidate_email_blueprint']['blocks'][1];
    $image = CommunicationTemplateDocument::findOrFail($block['asset_id']);
    expect($image->id)->not->toBe(hash('sha256', $png))->and($image->communication_template_id)->toBe($this->template->id)
        ->and($image->institution_id)->toBe($this->institution->id)->and($image->storage_path)->not->toContain('caller-chosen')
        ->and($image->checksum)->toBe(hash('sha256', $png))->and(Storage::disk('local')->get($image->storage_path))->toBe($png)
        ->and(Storage::disk('local')->getVisibility($image->storage_path))->toBe('private');
});

test('failed conversion preserves old work and cleans only fresh candidate files', function (): void {
    app(ImportCommunicationTemplateDocument::class)->handle($this->template, lifecycleUpload($this), $this->actor, false, lifecycleGuards($this->template, 'good-import'));
    $before = $this->template->draft()->firstOrFail();
    $files = Storage::disk('local')->allFiles();
    $count = CommunicationTemplateDocument::count();
    $auditCount = AuditEvent::where('event_type', 'communication_template.document_imported')->count();
    lifecycleRejected(fn () => app(ImportCommunicationTemplateDocument::class)->handle($this->template, lifecycleUpload($this, '<w:p><w:r><w:t>{{secret_password}}</w:t></w:r></w:p>'), $this->actor, false, lifecycleGuards($this->template, 'failed-conversion')), 422);
    $after = $this->template->draft()->firstOrFail();
    expect($after->email_blueprint)->toBe($before->email_blueprint)->and($after->import_provenance)->toBe($before->import_provenance)
        ->and($after->lock_version)->toBe($before->lock_version)->and(CommunicationTemplateDocument::count())->toBe($count)
        ->and(Storage::disk('local')->allFiles())->toBe($files)
        ->and(AuditEvent::where('event_type', 'communication_template.document_imported')->count())->toBe($auditCount);
});

test('same import retry creates no second source report revision or audit and changed key payload conflicts', function (): void {
    $file = lifecycleUpload($this);
    $guards = lifecycleGuards($this->template, 'same-import');
    $first = app(ImportCommunicationTemplateDocument::class)->handle($this->template, $file, $this->actor, false, $guards);
    $files = Storage::disk('local')->allFiles();
    $replay = app(ImportCommunicationTemplateDocument::class)->handle($this->template, $file, $this->actor, false, $guards);
    expect($replay['replayed'])->toBeTrue()->and($replay['import'])->toBe($first['import'])
        ->and($replay['draft'])->toBe($first['draft'])->and(Storage::disk('local')->allFiles())->toBe($files)
        ->and(CommunicationTemplateReceipt::where('operation', 'document_imported')->count())->toBe(1)
        ->and(AuditEvent::where('event_type', 'communication_template.document_imported')->count())->toBe(1);
    lifecycleRejected(fn () => app(ImportCommunicationTemplateDocument::class)->handle($this->template, $file, $this->actor, true, $guards), 409);
});

test('stale first import and stale review never associate files or overwrite author work', function (): void {
    $guards = lifecycleGuards($this->template, 'stale-first');
    app(ImportCommunicationTemplateDocument::class)->handle($this->template, lifecycleUpload($this), $this->actor, false, lifecycleGuards($this->template, 'wins'));
    $files = Storage::disk('local')->allFiles();
    lifecycleRejected(fn () => app(ImportCommunicationTemplateDocument::class)->handle($this->template, lifecycleUpload($this), $this->actor, false, $guards), 409);
    expect(Storage::disk('local')->allFiles())->toBe($files);
    $review = lifecycleReviewData($this->template, 'stale-review');
    app(ImportCommunicationTemplateDocument::class)->handle($this->template, lifecycleUpload($this), $this->actor, false, lifecycleGuards($this->template, 'new-source'));
    lifecycleRejected(fn () => app(ReviewCommunicationTemplateImport::class)->handle($this->template, $this->actor, $review), 409);
    expect($this->template->draft()->firstOrFail()->import_provenance['review_acknowledgment'])->toBeNull();
});

test('stale final email digest and corrupted persisted source or report cannot certify review', function (): void {
    app(ImportCommunicationTemplateDocument::class)->handle($this->template, lifecycleUpload($this), $this->actor, false, lifecycleGuards($this->template, 'source'));
    $data = lifecycleReviewData($this->template, 'stale-email');
    $data['email_blueprint_sha256'] = str_repeat('0', 64);
    lifecycleRejected(fn () => app(ReviewCommunicationTemplateImport::class)->handle($this->template, $this->actor, $data), 422);
    foreach (['source_asset_id', 'report_id'] as $field) {
        $draft = $this->template->draft()->firstOrFail();
        $asset = CommunicationTemplateDocument::findOrFail($draft->import_provenance[$field]);
        $bytes = Storage::disk('local')->get($asset->storage_path);
        Storage::disk('local')->put($asset->storage_path, 'corrupt');
        lifecycleRejected(fn () => app(ReviewCommunicationTemplateImport::class)->handle($this->template, $this->actor, lifecycleReviewData($this->template, 'corrupt-'.$field)), 422);
        Storage::disk('local')->put($asset->storage_path, $bytes);
    }
    expect($this->template->draft()->firstOrFail()->import_provenance['review_acknowledgment'])->toBeNull();
});

test('completed import replay still requires fresh capability', function (): void {
    $file = lifecycleUpload($this);
    $guards = lifecycleGuards($this->template, 'revoked-import');
    app(ImportCommunicationTemplateDocument::class)->handle($this->template, $file, $this->actor, false, $guards);
    $this->actor->syncRoles([]);
    $this->actor->syncPermissions([]);
    lifecycleRejected(fn () => app(ImportCommunicationTemplateDocument::class)->handle($this->template, $file, $this->actor, false, $guards), 403);
});

test('editing reviewed email invalidates acknowledgment and old review cannot certify changed content', function (): void {
    app(ImportCommunicationTemplateDocument::class)->handle($this->template, lifecycleUpload($this), $this->actor, false, lifecycleGuards($this->template, 'edit-source'));
    app(ReviewCommunicationTemplateImport::class)->handle($this->template, $this->actor, lifecycleReviewData($this->template, 'initial-ack'));
    $oldReview = lifecycleReviewData($this->template, 'old-email-review');
    $draft = $this->template->draft()->firstOrFail();
    $state = CommunicationTemplateState::state($draft);
    $state['email_blueprint']['blocks'][] = ['type' => 'paragraph', 'text' => 'Manually corrected text'];
    app(SaveCommunicationTemplateDraft::class)->save($this->template, [...lifecycleGuards($this->template, 'email-edit'), 'state' => $state, 'stage' => 2], $this->actor);
    expect($this->template->draft()->firstOrFail()->import_provenance['review_acknowledgment'])->toBeNull();
    lifecycleRejected(fn () => app(ReviewCommunicationTemplateImport::class)->handle($this->template, $this->actor, $oldReview), 409);
    $oldReview = [...$oldReview, ...lifecycleGuards($this->template, 'stale-digest-fresh-guards')];
    lifecycleRejected(fn () => app(ReviewCommunicationTemplateImport::class)->handle($this->template, $this->actor, $oldReview), 422);
});

test('review cannot acknowledge unknown or malformed final email tokens', function (string $text): void {
    app(ImportCommunicationTemplateDocument::class)->handle($this->template, lifecycleUpload($this), $this->actor, false, lifecycleGuards($this->template, 'token-source'));
    $draft = $this->template->draft()->firstOrFail();
    $blueprint = $draft->email_blueprint;
    $blueprint['blocks'][] = ['type' => 'paragraph', 'text' => $text];
    $draft->update(['email_blueprint' => $blueprint]);
    lifecycleRejected(fn () => app(ReviewCommunicationTemplateImport::class)->handle($this->template, $this->actor, lifecycleReviewData($this->template, 'unsafe-token-review')), 422);
    expect($this->template->draft()->firstOrFail()->import_provenance['review_acknowledgment'])->toBeNull();
})->with(['{{password}}', '{{applicant_name']);

test('first import requires full family draft guards and operation identity before writing private assets', function (): void {
    $generation = $this->template->family_revision;
    lifecycleRejected(fn () => app(ImportCommunicationTemplateDocument::class)->handle($this->template, lifecycleUpload($this), $this->actor), 422);
    expect($this->template->draft()->exists())->toBeFalse()
        ->and($this->template->fresh()->family_revision)->toBe($generation)
        ->and(CommunicationTemplateDocument::where('communication_template_id', $this->template->id)->count())->toBe(0)
        ->and(Storage::disk('local')->allFiles())->toBe([]);
});

test('initial import retains historical short copy and raw channel comparison without rewriting the version', function (): void {
    $base = $this->template->versions()->where('version', $this->template->active_version)->firstOrFail();
    $version = $base->replicate()->fill(['version' => $base->version + 1, 'content_schema_version' => 'notice-content-1',
        'subject' => null, 'body' => null, 'email_subject' => 'Historical shared subject',
        'email_body' => '<p>Historical <strong>rich HTML</strong></p>', 'in_app_title' => 'Original portal title',
        'in_app_body' => 'Original portal caption', 'email_blueprint' => null]);
    $version->save();
    $this->template->update(['active_version' => $version->version]);
    $original = $version->fresh()->getAttributes();
    $result = app(ImportCommunicationTemplateDocument::class)->handle($this->template, lifecycleUpload($this), $this->actor, false, lifecycleGuards($this->template, 'from-history'));
    expect($result['draft']['state']['subject'])->toBe('Historical shared subject')
        ->and($result['draft']['state']['body'])->toBe('Original portal caption')
        ->and($result['draft']['historical_content']['email_body'])->toBe('<p>Historical <strong>rich HTML</strong></p>')
        ->and($result['draft']['historical_content']['in_app_title'])->toBe('Original portal title')
        ->and($version->fresh()->getAttributes())->toBe($original);
});

test('unsafe split field import cannot retain a printable original or a waivable report', function (): void {
    $body = '<w:p><w:r><w:fldChar w:fldCharType="begin"/></w:r><w:r><w:instrText>INCLUDE</w:instrText></w:r><w:r><w:instrText>PICTURE "https://remote.test/image"</w:instrText></w:r><w:r><w:fldChar w:fldCharType="end"/></w:r></w:p>';
    lifecycleRejected(fn () => app(ImportCommunicationTemplateDocument::class)->handle($this->template, lifecycleUpload($this, $body), $this->actor, false, lifecycleGuards($this->template, 'split-field-rejection')), 422);
    expect($this->template->draft()->exists())->toBeFalse()
        ->and(CommunicationTemplateDocument::where('communication_template_id', $this->template->id)->count())->toBe(0)
        ->and(Storage::disk('local')->allFiles())->toBe([]);
});

test('critical review accepts genuinely restored schema typed tokens and string table cells', function (string $sourceText, array $replacement): void {
    $extra = ['word/header1.xml' => '<w:hdr xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main"><w:p><w:r><w:t>'.$sourceText.'</w:t></w:r></w:p></w:hdr>'];
    app(ImportCommunicationTemplateDocument::class)->handle($this->template, lifecycleUpload($this, '<w:p><w:r><w:t>Body</w:t></w:r></w:p>', $extra), $this->actor, false, lifecycleGuards($this->template, 'typed-correction-source'));
    $draft = $this->template->draft()->firstOrFail();
    $state = CommunicationTemplateState::state($draft);
    $state['email_blueprint']['blocks'][] = $replacement;
    app(SaveCommunicationTemplateDraft::class)->save($this->template, [...lifecycleGuards($this->template, 'typed-correction-save'), 'state' => $state, 'stage' => 2], $this->actor);
    $data = lifecycleReviewData($this->template, 'typed-correction-review');
    $id = array_key_first($data['resolutions']);
    $data['resolutions'][$id] = ['disposition' => 'corrected', 'block_paths' => ['blocks.1']];
    $result = app(ReviewCommunicationTemplateImport::class)->handle($this->template, $this->actor, $data);
    expect($result['draft']['state']['import_provenance']['review_acknowledgment']['resolutions'][$id]['disposition'])->toBe('corrected');
})->with([
    ['Dear {{applicant_name}}', ['type' => 'paragraph', 'runs' => [['text' => 'Dear '], ['token' => 'applicant_name']]]],
    ['Bring ID', ['type' => 'table', 'rows' => [['Bring ID']]]],
]);

test('scorecard print-only source accepts official tokens while preserving reviewed email and release authority', function (): void {
    $registrar = $this->actor;
    $release = User::query()->where('email', 'guidance.counselor@nexiam.test')->firstOrFail();
    $family = CommunicationTemplate::query()->where('institution_id', $this->institution->id)
        ->where('domain', 'results')->where('slug', 'released-online')->firstOrFail();
    $action = app(ImportCommunicationTemplateDocument::class);
    $emailFile = lifecycleUpload($this, '<w:p><w:r><w:t>Dear {{applicant_name}}, your released result is available.</w:t></w:r></w:p>');
    $emailGuards = lifecycleGuards($family, 'scorecard-email');
    $emailResult = $action->handle($family, $emailFile, $release, false, $emailGuards);
    app(ReviewCommunicationTemplateImport::class)->handle($family, $release, lifecycleReviewData($family, 'scorecard-email-review'));
    $oldSource = app(StoreCommunicationTemplateDocument::class)->store($family,
        lifecycleUpload($this, '<w:p><w:r><w:t>{{applicant_reference}} original scorecard</w:t></w:r></w:p>'), $release);
    $state = CommunicationTemplateState::state($family->draft()->firstOrFail());
    $state['document_mode'] = 'docx';
    $state['document_asset_id'] = $oldSource->id;
    app(SaveCommunicationTemplateDraft::class)->save($family,
        [...lifecycleGuards($family, 'old-scorecard-source'), 'state' => $state, 'stage' => 2], $release);
    $before = $family->draft()->firstOrFail();
    $beforeFiles = Storage::disk('local')->allFiles();
    $file = lifecycleUpload($this, '<w:p>
        <w:r><w:t>Reference {{applicant_</w:t></w:r>
        <w:r><w:t>reference}}</w:t></w:r>
        <w:r><w:t> Overall {{overall_pct}}</w:t></w:r>
        </w:p>');
    lifecycleRejected(fn () => $action->handle($family, $file, $release, false, lifecycleGuards($family, 'scorecard-as-email')), 422);
    expect(Storage::disk('local')->allFiles())->toBe($beforeFiles);
    $guards = lifecycleGuards($family, 'scorecard-print');
    $result = $action->handle($family, $file, $release, false, $guards, 'print');
    $after = $family->draft()->firstOrFail();
    $source = CommunicationTemplateDocument::findOrFail($result['source']['asset_id']);
    expect($result['_status'])->toBe(201)
        ->and($result)->not->toHaveKey('import')
        ->and($result['diagnostics']['valid'])->toBeTrue()
        ->and($result['diagnostics']['found'])->toContain('applicant_reference', 'overall_pct')
        ->and($result['source'])->toBe(['asset_id' => $source->id, 'sha256' => $source->checksum,
            'filename' => 'notice.docx', 'mime' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document', 'purpose' => 'print'])
        ->and($source->communication_template_id)->toBe($family->id)
        ->and($source->checksum)->toBe(hash_file('sha256', $file->getRealPath()))
        ->and(Storage::disk('local')->getVisibility($source->storage_path))->toBe('private')
        ->and($after->document_asset_id)->toBe($source->id)
        ->and($after->document_source_drafts['docx']['document_asset_id'])->toBe($oldSource->id)
        ->and($after->email_blueprint)->toBe($before->email_blueprint)
        ->and($after->import_provenance)->toBe($before->import_provenance)
        ->and($after->content_sha256)->not->toBe($before->content_sha256)
        ->and($after->lock_version)->toBe($before->lock_version + 1);
    $audit = AuditEvent::query()->where('event_type', 'communication_template.document_imported')
        ->where('payload->operation_id', $result['operation_id'])->firstOrFail();
    expect($audit->payload['source_asset_id'])->toBe($source->id)
        ->and($audit->payload['source_sha256'])->toBe($source->checksum)
        ->and($audit->payload['purpose'])->toBe('print');
    $files = Storage::disk('local')->allFiles();
    $replay = $action->handle($family, $file, $release, false, $guards, 'print');
    expect($replay['replayed'])->toBeTrue()
        ->and($replay['source'])->toBe($result['source'])
        ->and(Storage::disk('local')->allFiles())->toBe($files)
        ->and($family->draft()->firstOrFail()->lock_version)->toBe($after->lock_version);
    lifecycleRejected(fn () => $action->handle($family, $file, $release, false, $guards), 409);
    $emailReplay = $action->handle($family, $emailFile, $release, false, [...$emailGuards, 'purpose' => 'email'], 'email');
    expect($emailReplay['replayed'])->toBeTrue()->and($emailReplay['import'])->toBe($emailResult['import']);
    lifecycleRejected(fn () => $action->handle($family, $file, $registrar, false, lifecycleGuards($family, 'registrar-scorecard'), 'print'), 403);
    lifecycleRejected(fn () => $action->handle($family, $file, $release, false,
        [...lifecycleGuards($family, 'caller-score-values'), 'score_values' => ['overall_pct' => 100]], 'print'), 422);
    expect(Storage::disk('local')->allFiles())->toBe($files)
        ->and($family->draft()->firstOrFail()->import_provenance)->toBe($before->import_provenance);
});
