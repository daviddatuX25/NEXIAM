<?php

use App\DocumentTemplate\Rendering\DocxTemplateFiller;
use App\DocumentTemplate\Rendering\LibreOfficeConverter;
use App\Models\Foundation\AuditEvent;
use App\Models\Foundation\Institution;
use App\Models\Notifications\CommunicationTemplate;
use App\Models\Notifications\CommunicationTemplateDocument;
use App\Models\Notifications\CommunicationTemplateDraft;
use App\Models\Notifications\CommunicationTemplateReceipt;
use App\Models\User;
use App\Notifications\Mail\NoticeTemplateTestMailable;
use App\Notifications\Services\NoticeDocumentAttachmentCompiler;
use App\Notifications\Templates\Actions\RenderCommunicationTemplatePreview;
use App\Notifications\Templates\Actions\SendCommunicationTemplateTestMail;
use App\Notifications\Templates\Actions\StoreCommunicationTemplateDocument;
use App\Notifications\Templates\CommunicationTemplatePayload;
use App\Notifications\Templates\CommunicationTemplatePreviewReceipt;
use App\Notifications\Templates\CommunicationTemplateState;
use App\Notifications\Templates\Email\NoticeEmailAssetVerifier;
use App\Notifications\Templates\Email\NoticeEmailRenderer;
use App\Notifications\Templates\Email\NoticeMessageEmailRenderer;
use App\Notifications\Templates\NoticeTemplateSyntheticPrint;
use App\Notifications\Templates\ProvisionCanonicalCommunicationTemplates;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Mail\PendingMail;
use Illuminate\Mail\SentMessage;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpException;

beforeEach(function (): void {
    seedE2Foundation();
    Storage::fake('local');
    config(['mail.default' => 'smtp', 'mail.mailers.smtp.transport' => 'smtp']);
});

function noticeCheckFixture(string $domain = 'examination', string $slug = 'reminder'): array
{
    app(ProvisionCanonicalCommunicationTemplates::class)->provision(Institution::firstOrFail(), null, $domain);
    $family = CommunicationTemplate::query()->where('domain', $domain)->where('slug', $slug)->firstOrFail();
    $actor = User::query()->where('email', $domain === 'admissions' ? 'registrar.admin@nexiam.test' : 'guidance.counselor@nexiam.test')->firstOrFail();
    $actor->forceFill(['email_verified_at' => now()])->save();
    $state = ['subject' => 'Dear {{applicant_name}}', 'body' => 'Synthetic portal caption', 'action_label' => null,
        'action_route' => null, 'email_blueprint' => ['schema_version' => 'notice-email-1', 'layout' => 'flow',
            'blocks' => [['type' => 'paragraph', 'text' => 'Full email for {{applicant_name}}']]],
        'document_mode' => 'none', 'document_asset_id' => null, 'builder_json' => null, 'paper_size' => null,
        'orientation' => null, 'document_source_drafts' => null, 'import_provenance' => null];
    $draft = $family->draft()->create([...$state, 'base_version' => $family->active_version, 'lock_version' => 1,
        'stage' => 3, 'content_schema_version' => 'notice-content-2', 'email_renderer_version' => 'notice-email-1',
        'content_sha256' => app(CommunicationTemplateState::class)->digest($family, $state),
        'email_subject' => $state['subject'], 'email_body' => '', 'in_app_title' => $state['subject'], 'in_app_body' => $state['body']]);

    return [$family, $draft, $actor];
}

function noticeCheckInput(CommunicationTemplate $family, CommunicationTemplateDraft $draft, string $view = 'email'): array
{
    return ['view' => $view, 'base_version' => $family->active_version, 'family_revision' => $family->family_revision,
        'lock_version' => $draft->lock_version, 'content_sha256' => $draft->content_sha256];
}

function noticeTestInput(CommunicationTemplate $family, CommunicationTemplateDraft $draft, User $actor, string $key = 'synthetic-send'): array
{
    $preview = app(RenderCommunicationTemplatePreview::class)->execute($family, noticeCheckInput($family, $draft), $actor);
    $input = noticeCheckInput($family, $draft);
    unset($input['view']);

    return [...$input, 'idempotency_key' => $key, 'preview_receipt' => $preview['preview_receipt']];
}

function noticeOwnedRaster(CommunicationTemplate $family, User $actor): CommunicationTemplateDocument
{
    $id = (string) Str::uuid();
    $bytes = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+jRZkAAAAASUVORK5CYII=');
    $path = 'communication-templates/'.$family->id.'/images/'.$id.'.png';
    Storage::disk('local')->put($path, $bytes);

    return CommunicationTemplateDocument::query()->create(['id' => $id, 'communication_template_id' => $family->id,
        'institution_id' => $family->institution_id, 'original_name' => 'safe.png', 'storage_path' => $path,
        'checksum' => hash('sha256', $bytes), 'mime_type' => 'image/png', 'file_size_bytes' => strlen($bytes), 'created_by_user_id' => $actor->id]);
}

test('email Check projects owned CID bytes without changing production markup and issues only requested branch', function (): void {
    [$family, $draft, $actor] = noticeCheckFixture();
    $image = noticeOwnedRaster($family, $actor);
    $blueprint = $draft->email_blueprint;
    $blueprint['blocks'][] = ['type' => 'image', 'asset_id' => $image->id, 'width' => 600, 'height' => 400, 'alt' => 'Synthetic logo'];
    $draft->update(['email_blueprint' => $blueprint]);
    $draft->update(['content_sha256' => app(CommunicationTemplateState::class)->digest($family, $draft)]);
    $result = app(RenderCommunicationTemplatePreview::class)->execute($family, noticeCheckInput($family, $draft), $actor);
    expect($result)->toHaveKeys(['email', 'preview_receipt', 'content_sha256'])->not->toHaveKeys(['portal', 'print'])
        ->and($result['email']['production_html'])->toContain('src="cid:notice-'.$image->id.'@nexiam.inline"')->not->toContain('data:image')
        ->and($result['email']['preview_html'])->toContain('data:image/png;base64,', 'Content-Security-Policy')->not->toContain('src="cid:', $image->storage_path)
        ->and($result['email']['assets'][0])->toBe(['id' => $image->id, 'sha256' => $image->checksum, 'cid' => 'notice-'.$image->id.'@nexiam.inline', 'mime' => 'image/png']);
    preg_match('/src="data:image\/png;base64,([^"]+)"/', $result['email']['preview_html'], $match);
    expect(hash('sha256', base64_decode($match[1], true)))->toBe($image->checksum);
    app(CommunicationTemplatePreviewReceipt::class)->verify($family, $draft, $actor, 'email', $result['preview_receipt']);
    $frozen = $draft->fresh()->toArray();
    expect(json_encode($frozen))->not->toContain('data:image', 'production_html', 'preview_html');
    $portal = app(RenderCommunicationTemplatePreview::class)->execute($family, noticeCheckInput($family, $draft, 'portal'), $actor);
    expect($portal)->toHaveKey('portal')->not->toHaveKeys(['email', 'print']);
    expect(fn () => app(RenderCommunicationTemplatePreview::class)->execute($family, noticeCheckInput($family, $draft, 'print'), $actor))->toThrow(HttpResponseException::class);
});

test('raster verifier rejects foreign pins corrupted mime size missing bytes and decoded pixel bounds', function (): void {
    [$family, $draft, $actor] = noticeCheckFixture();
    $image = noticeOwnedRaster($family, $actor);
    $blueprint = ['blocks' => [['type' => 'image', 'asset_id' => $image->id]]];
    $verifier = app(NoticeEmailAssetVerifier::class);
    $verified = $verifier->verify((int) $family->institution_id, $family->id, $blueprint);
    expect($verified[0]['sha256'])->toBe($image->checksum);
    expect(fn () => $verifier->verify((int) $family->institution_id, (string) Str::uuid(), $blueprint))->toThrow(RuntimeException::class);
    expect(fn () => $verifier->verify((int) $family->institution_id, $family->id, $blueprint, [['id' => $image->id, 'sha256' => str_repeat('0', 64), 'mime' => 'image/png', 'cid' => 'x@nexiam.inline']]))->toThrow(RuntimeException::class);
    $image->update(['mime_type' => 'image/jpeg']);
    expect(fn () => $verifier->verify((int) $family->institution_id, $family->id, $blueprint))->toThrow(RuntimeException::class);
    $image->update(['mime_type' => 'image/png', 'file_size_bytes' => 2097153]);
    expect(fn () => $verifier->verify((int) $family->institution_id, $family->id, $blueprint))->toThrow(RuntimeException::class);
    $bytes = $verified[0]['bytes'];
    $oversized = substr_replace($bytes, pack('NN', 5000, 5000), 16, 8);
    Storage::disk('local')->put($image->storage_path, $oversized);
    $image->update(['file_size_bytes' => strlen($oversized), 'checksum' => hash('sha256', $oversized)]);
    expect(fn () => $verifier->verify((int) $family->institution_id, $family->id, $blueprint))->toThrow(RuntimeException::class);
    Storage::disk('local')->put($image->storage_path, 'corrupted');
    expect(fn () => $verifier->verify((int) $family->institution_id, $family->id, $blueprint))->toThrow(RuntimeException::class);
    Storage::disk('local')->delete($image->storage_path);
    expect(fn () => $verifier->verify((int) $family->institution_id, $family->id, $blueprint))->toThrow(RuntimeException::class);
    expect(fn () => NoticeEmailAssetVerifier::assetIds(['blocks' => array_fill(0, 21, ['type' => 'image', 'asset_id' => $image->id])]))->toThrow(RuntimeException::class, 'email_asset_limit');
});

test('preview receipts reject stale content source review generation actor and view', function (): void {
    [$family, $draft, $actor] = noticeCheckFixture();
    $preview = app(RenderCommunicationTemplatePreview::class)->execute($family, noticeCheckInput($family, $draft), $actor);
    $receipts = app(CommunicationTemplatePreviewReceipt::class);
    expect(fn () => $receipts->verify($family, $draft, $actor, 'portal', $preview['preview_receipt']))->toThrow(HttpResponseException::class);
    $draft->update(['body' => 'Changed caption']);
    expect(fn () => $receipts->verify($family, $draft, $actor, 'email', $preview['preview_receipt']))->toThrow(HttpResponseException::class);
    expect(fn () => app(RenderCommunicationTemplatePreview::class)->execute($family, noticeCheckInput($family, $draft), $actor))->toThrow(HttpResponseException::class);
    $draft->update(['body' => 'Synthetic portal caption']);
    $family->increment('family_revision');
    expect(fn () => $receipts->verify($family, $draft, $actor, 'email', $preview['preview_receipt']))->toThrow(HttpResponseException::class);
});

test('test mail denies destination live facts unverified staff and stale Check without claiming a send', function (): void {
    [$family, $draft, $actor] = noticeCheckFixture();
    $input = noticeTestInput($family, $draft, $actor);
    $sender = app(SendCommunicationTemplateTestMail::class);
    foreach (['recipient' => 'applicant@example.test', 'context' => ['applicant_name' => 'Live Applicant']] as $key => $value) {
        expect(fn () => $sender->execute($family, [...$input, $key => $value], $actor))->toThrow(ValidationException::class);
    }
    $actor->forceFill(['email_verified_at' => null])->save();
    expect(fn () => $sender->execute($family, $input, $actor))->toThrow(HttpException::class);
    $actor->forceFill(['email_verified_at' => now()])->save();
    $draft->increment('lock_version');
    expect(fn () => $sender->execute($family, $input, $actor))->toThrow(HttpResponseException::class);
    expect(CommunicationTemplateReceipt::where('operation', 'test_mail')->count())->toBe(0);
});

test('observed test transport success replays original result after later content changes without resend', function (): void {
    [$family, $draft, $actor] = noticeCheckFixture();
    $input = noticeTestInput($family, $draft, $actor);
    $pending = Mockery::mock(PendingMail::class);
    $pending->shouldReceive('send')->once()->with(Mockery::on(function ($mail) use ($family): bool {
        expect($mail)->toBeInstanceOf(NoticeTemplateTestMailable::class);
        expect($mail->rendered['production_html'])->not->toContain('data:image')->toContain('Full email for');
        expect(CommunicationTemplateReceipt::where('operation', 'test_mail')->firstOrFail()->response_payload['status'])->toBe('claimed');
        expect(CommunicationTemplate::findOrFail($family->id)->active_version)->toBe($family->active_version);

        return true;
    }))->andReturn(Mockery::mock(SentMessage::class));
    Mail::shouldReceive('to')->once()->with($actor->email)->andReturn($pending);
    $sender = app(SendCommunicationTemplateTestMail::class);
    $result = $sender->execute($family, $input, $actor);
    $family->increment('family_revision');
    $draft->update(['body' => 'New content', 'lock_version' => 2]);
    $replayed = $sender->execute($family, $input, $actor);
    expect($result['status'])->toBe('sent')->and($replayed)->toBe([...$result, 'replayed' => true])
        ->and(CommunicationTemplateReceipt::where('operation', 'test_mail')->count())->toBe(1);
    expect(fn () => $sender->execute($family, [...$input, 'content_sha256' => str_repeat('0', 64)], $actor))->toThrow(HttpResponseException::class);
});

test('unknown provider send and inflight claim never resend or claim observed success', function (): void {
    [$family, $draft, $actor] = noticeCheckFixture();
    $input = noticeTestInput($family, $draft, $actor);
    $pending = Mockery::mock(PendingMail::class);
    $pending->shouldReceive('send')->once()->andThrow(new RuntimeException('private provider credentials'));
    Mail::shouldReceive('to')->once()->with($actor->email)->andReturn($pending);
    $sender = app(SendCommunicationTemplateTestMail::class);
    try {
        $sender->execute($family, $input, $actor);
        $this->fail('Unconfirmed send cannot return success.');
    } catch (HttpResponseException $exception) {
        expect($exception->getResponse()->getStatusCode())->toBe(503)
            ->and($exception->getResponse()->getContent())->not->toContain('private provider credentials');
    }
    expect(CommunicationTemplateReceipt::where('operation', 'test_mail')->firstOrFail()->response_payload['status'])->toBe('unknown');
    expect(fn () => $sender->execute($family, $input, $actor))->toThrow(HttpResponseException::class);
    CommunicationTemplateReceipt::where('operation', 'test_mail')->firstOrFail()->update(['response_payload' => ['status' => 'claimed']]);
    expect(fn () => $sender->execute($family, $input, $actor))->toThrow(HttpResponseException::class);
});

test('known unavailable provider returns truthful replayable failure', function (): void {
    [$family, $draft, $actor] = noticeCheckFixture();
    $input = noticeTestInput($family, $draft, $actor);
    config(['mail.default' => 'log', 'mail.mailers.log.transport' => 'log']);
    $sender = app(SendCommunicationTemplateTestMail::class);
    expect(fn () => $sender->execute($family, $input, $actor))->toThrow(HttpResponseException::class);
    expect(CommunicationTemplateReceipt::where('operation', 'test_mail')->firstOrFail()->response_payload['status'])->toBe('failed');
    expect(fn () => $sender->execute($family, $input, $actor))->toThrow(HttpResponseException::class);
});

function noticeOwnedDocx(CommunicationTemplate $family, User $actor, bool $includeApplicationNumber = true, bool $includePrintableText = true, bool $includeUnknownField = false): CommunicationTemplateDocument
{
    $id = (string) Str::uuid();
    $path = 'communication-templates/'.$family->id.'/'.$id.'.docx';
    $full = Storage::disk('local')->path($path);
    if (! is_dir(dirname($full))) {
        mkdir(dirname($full), 0700, true);
    }
    $zip = new ZipArchive;
    $zip->open($full, ZipArchive::CREATE | ZipArchive::OVERWRITE);
    $zip->addFromString('[Content_Types].xml', '<?xml version="1.0" encoding="UTF-8"?><Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types"><Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/><Default Extension="xml" ContentType="application/xml"/><Override PartName="/word/document.xml" ContentType="application/vnd.openxmlformats-officedocument.wordprocessingml.document.main+xml"/></Types>');
    $zip->addFromString('_rels/.rels', '<?xml version="1.0" encoding="UTF-8"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="word/document.xml"/></Relationships>');
    $zip->addFromString('word/_rels/document.xml.rels', '<?xml version="1.0" encoding="UTF-8"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"></Relationships>');
    $reference = $includeApplicationNumber ? ' — {{application_no}}' : '';
    $unknownField = $includeUnknownField ? ' {{unapproved_field}}' : '';
    $documentText = $includePrintableText ? 'Hello {{applicant_name}}'.$reference.$unknownField : '';
    $zip->addFromString('word/document.xml', '<w:document xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main"><w:body><w:p><w:r><w:t>'.$documentText.'</w:t></w:r></w:p></w:body></w:document>');
    $zip->close();

    return CommunicationTemplateDocument::query()->create(['id' => $id, 'communication_template_id' => $family->id,
        'institution_id' => $family->institution_id, 'original_name' => 'synthetic.docx', 'storage_path' => $path,
        'checksum' => hash_file('sha256', $full), 'mime_type' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        'file_size_bytes' => filesize($full), 'created_by_user_id' => $actor->id]);
}

test('source byte corruption and changed review invalidate an issued preview receipt', function (): void {
    [$family, $draft, $actor] = noticeCheckFixture();
    $source = noticeOwnedDocx($family, $actor);
    $reportData = ['source_sha256' => $source->checksum, 'converter_version' => 'docx-email-1', 'mapped_blocks_count' => 1,
        'mapped' => [['source_part' => 'word/document.xml', 'source_location' => '/w:document/w:body/w:p[1]', 'kind' => 'paragraph']], 'findings' => []];
    $report = app(StoreCommunicationTemplateDocument::class)
        ->storeReport($family, CommunicationTemplatePayload::json($reportData), $actor);
    $provenance = ['source_asset_id' => $source->id, 'source_sha256' => $source->checksum,
        'converter_version' => 'docx-email-1', 'report_id' => $report->id, 'report_sha256' => $report->checksum,
        'mapped_blocks' => 1, 'findings' => [], 'candidate_email_blueprint' => $draft->email_blueprint,
        'candidate_email_blueprint_sha256' => CommunicationTemplateState::emailDigest($draft->email_blueprint),
        'review_acknowledgment' => ['actor_id' => $actor->id, 'acknowledged_at' => now()->toIso8601String(),
            'report_sha256' => $report->checksum,
            'email_blueprint_sha256' => CommunicationTemplateState::emailDigest($draft->email_blueprint), 'resolutions' => []]];
    $draft->update(['import_provenance' => $provenance]);
    $draft->update(['content_sha256' => app(CommunicationTemplateState::class)->digest($family, $draft)]);
    $preview = app(RenderCommunicationTemplatePreview::class)->execute($family, noticeCheckInput($family, $draft), $actor);
    $bytes = Storage::disk('local')->get($source->storage_path);
    Storage::disk('local')->put($source->storage_path, 'corrupt source');
    expect(fn () => app(CommunicationTemplatePreviewReceipt::class)->verify($family, $draft, $actor, 'email', $preview['preview_receipt']))
        ->toThrow(HttpException::class);
    Storage::disk('local')->put($source->storage_path, $bytes);
    $provenance['review_acknowledgment'] = null;
    $draft->update(['import_provenance' => $provenance]);
    expect(fn () => app(CommunicationTemplatePreviewReceipt::class)->verify($family, $draft, $actor, 'email', $preview['preview_receipt']))
        ->toThrow(HttpResponseException::class);
});

test('production MIME retains CID raster bytes full plaintext and selected PDF without preview projection', function (): void {
    [$family, $draft, $actor] = noticeCheckFixture();
    $image = noticeOwnedRaster($family, $actor);
    $blueprint = $draft->email_blueprint;
    $blueprint['blocks'][] = ['type' => 'image', 'asset_id' => $image->id, 'width' => 600, 'alt' => 'Logo'];
    $draft->update(['email_blueprint' => $blueprint]);
    $rendered = app(RenderCommunicationTemplatePreview::class)->email($family, $draft);
    config(['mail.mailers.parity' => ['transport' => 'array']]);
    $sent = Mail::mailer('parity')->to($actor->email)->send(new NoticeTemplateTestMailable($rendered, ['bytes' => '%PDF-1.4 synthetic', 'sha256' => hash('sha256', '%PDF-1.4 synthetic')]));
    $mime = $sent->getSymfonySentMessage()->getOriginalMessage();
    expect($mime->getHtmlBody())->toBe($rendered['production_html'])->not->toContain('data:image')
        ->and($mime->getTextBody())->toBe($rendered['text'])
        ->and($mime->getAttachments())->toHaveCount(2);
    $inline = $mime->getAttachments()[0];
    expect($inline->getDisposition())->toBe('inline')->and($inline->getContentId())->toBe($rendered['assets'][0]['cid'])
        ->and(hash('sha256', $inline->getBody()))->toBe($image->checksum);
    expect($mime->getAttachments()[1]->getMediaSubtype())->toBe('pdf');
});

test('synthetic original DOCX preview uses real compiler seam and cleans output and filled intermediate on success and failure', function (): void {
    [$family, $draft, $actor] = noticeCheckFixture('admissions', 'accepted');
    $source = noticeOwnedDocx($family, $actor);
    $draft->update(['document_mode' => 'docx', 'document_asset_id' => $source->id]);
    $filler = Mockery::mock(DocxTemplateFiller::class);
    $converter = Mockery::mock(LibreOfficeConverter::class);
    $filled = null;
    $filler->shouldReceive('fill')->twice()->andReturnUsing(function (string $path, array $replacements) use (&$filled): string {
        $filled = app(DocxTemplateFiller::class)->fill($path, $replacements);

        return $filled;
    });
    $converter->shouldReceive('isAvailable')->twice()->andReturnTrue();
    $converter->shouldReceive('convertToPdf')->once()->andReturn('%PDF-1.4 synthetic');
    $compiler = new NoticeDocumentAttachmentCompiler($filler, $converter);
    $print = new NoticeTemplateSyntheticPrint(app(StoreCommunicationTemplateDocument::class), $compiler);
    $before = glob(sys_get_temp_dir().'/notice_pdf_*');
    $pdf = $print->render($family, $draft, RenderCommunicationTemplatePreview::syntheticContext($family));
    expect($pdf['bytes'])->toBe('%PDF-1.4 synthetic')->and($pdf['sha256'])->toBe(hash('sha256', $pdf['bytes']))
        ->and(file_exists($filled))->toBeFalse()->and(glob(sys_get_temp_dir().'/notice_pdf_*'))->toBe($before);
    $converter->shouldReceive('convertToPdf')->once()->andThrow(new RuntimeException('conversion failed'));
    expect(fn () => $print->render($family, $draft, RenderCommunicationTemplatePreview::syntheticContext($family)))->toThrow(RuntimeException::class, 'conversion failed');
    expect(file_exists($filled))->toBeFalse()->and(Storage::disk('local')->exists($source->storage_path))->toBeTrue();
});

test('a nonblank Word permit without application_no cannot obtain a current PDF receipt', function (): void {
    [$family, $draft, $actor] = noticeCheckFixture('admissions', 'accepted');
    $source = noticeOwnedDocx($family, $actor, false);
    $draft->update(['document_mode' => 'docx', 'document_asset_id' => $source->id]);
    $draft->update(['content_sha256' => app(CommunicationTemplateState::class)->digest($family, $draft)]);

    $response = actingAsAdmissionSupportStaff($this, $actor)->postJson(
        route('setup.notifications.templates.preview', ['domain' => 'admissions', 'slug' => 'accepted']),
        noticeCheckInput($family, $draft, 'print'),
    )->assertUnprocessable()->json();

    expect($response)->toHaveKey('error', 'print_required_token_missing')
        ->not->toHaveKey('preview_receipt')->not->toHaveKey('print');
    $before = [$family->fresh()->active_version, $family->versions()->count(), AuditEvent::query()->count(),
        CommunicationTemplateReceipt::query()->where('institution_id', $family->institution_id)->where('domain', $family->domain)
            ->where('canonical_slug', $family->slug)->where('operation', 'publish')->count()];
    $this->postJson(route('setup.notifications.templates.publish', ['domain' => 'admissions', 'slug' => 'accepted']), [
        'base_version' => $family->active_version, 'family_revision' => $family->family_revision,
        'lock_version' => $draft->lock_version, 'content_sha256' => $draft->content_sha256,
        'preview_receipts' => ['email' => 'invalid', 'portal' => 'invalid', 'print' => 'invalid'],
        'idempotency_key' => (string) Str::uuid(),
    ])->assertUnprocessable()->assertJsonValidationErrors('document_asset_id');
    $after = [$family->fresh()->active_version, $family->versions()->count(), AuditEvent::query()->count(),
        CommunicationTemplateReceipt::query()->where('institution_id', $family->institution_id)->where('domain', $family->domain)
            ->where('canonical_slug', $family->slug)->where('operation', 'publish')->count()];
    expect($after)->toBe($before);
});

test('a blank Word document cannot obtain a current PDF receipt', function (): void {
    [$family, $draft, $actor] = noticeCheckFixture('admissions', 'accepted');
    $source = noticeOwnedDocx($family, $actor, false, false);
    $draft->update(['document_mode' => 'docx', 'document_asset_id' => $source->id]);
    $draft->update(['content_sha256' => app(CommunicationTemplateState::class)->digest($family, $draft)]);

    $response = actingAsAdmissionSupportStaff($this, $actor)->postJson(
        route('setup.notifications.templates.preview', ['domain' => 'admissions', 'slug' => 'accepted']),
        noticeCheckInput($family, $draft, 'print'),
    )->assertUnprocessable()->json();

    expect($response)->toHaveKey('error', 'print_source_empty')
        ->not->toHaveKey('preview_receipt')->not->toHaveKey('print');
});

test('an unknown Word field cannot obtain a current PDF receipt', function (): void {
    [$family, $draft, $actor] = noticeCheckFixture('admissions', 'accepted');
    $source = noticeOwnedDocx($family, $actor, true, true, true);
    $draft->update(['document_mode' => 'docx', 'document_asset_id' => $source->id]);
    $draft->update(['content_sha256' => app(CommunicationTemplateState::class)->digest($family, $draft)]);

    $response = actingAsAdmissionSupportStaff($this, $actor)->postJson(
        route('setup.notifications.templates.preview', ['domain' => 'admissions', 'slug' => 'accepted']),
        noticeCheckInput($family, $draft, 'print'),
    )->assertUnprocessable()->json();

    expect($response)->toHaveKey('error', 'print_token_unknown')
        ->not->toHaveKey('preview_receipt')->not->toHaveKey('print');
});

test('selected eligible synthetic print failure cannot downgrade to attachment free successful mail', function (): void {
    [$family, $draft, $actor] = noticeCheckFixture('admissions', 'accepted');
    $source = noticeOwnedDocx($family, $actor);
    $draft->update(['document_mode' => 'docx', 'document_asset_id' => $source->id]);
    $draft->update(['content_sha256' => app(CommunicationTemplateState::class)->digest($family, $draft)]);
    $input = noticeTestInput($family, $draft, $actor);
    $print = Mockery::mock(NoticeTemplateSyntheticPrint::class);
    $print->shouldReceive('render')->once()->andThrow(new RuntimeException('converter unavailable'));
    app()->instance(NoticeTemplateSyntheticPrint::class, $print);
    Mail::shouldReceive('to')->never();
    expect(fn () => app(SendCommunicationTemplateTestMail::class)->execute($family, $input, $actor))->toThrow(HttpResponseException::class);
    expect(CommunicationTemplateReceipt::where('operation', 'test_mail')->firstOrFail()->response_payload['status'])->toBe('failed');
});

test('unavailable pinned email renderer returns safe failure and no success receipt', function (): void {
    [$family, $draft, $actor] = noticeCheckFixture();
    $draft->update(['email_renderer_version' => 'retained-but-unavailable']);
    try {
        app(RenderCommunicationTemplatePreview::class)->execute($family, noticeCheckInput($family, $draft), $actor);
        $this->fail('Unavailable pinned renderer cannot satisfy Check.');
    } catch (HttpResponseException $exception) {
        expect($exception->getResponse()->getStatusCode())->toBe(503);
        $payload = json_decode($exception->getResponse()->getContent(), true);
        expect($payload)->toHaveKey('error')->not->toHaveKeys(['preview_receipt', 'email']);
    }
});

test('incomplete historical semantic authoring is a validation failure not an unavailable renderer', function (): void {
    [$family, $draft, $actor] = noticeCheckFixture();
    $draft->update(['email_blueprint' => null]);
    $draft->update(['content_sha256' => app(CommunicationTemplateState::class)->digest($family, $draft)]);
    try {
        app(RenderCommunicationTemplatePreview::class)->execute($family, noticeCheckInput($family, $draft), $actor);
        $this->fail('Missing semantic blueprint cannot satisfy Check.');
    } catch (HttpResponseException $exception) {
        expect($exception->getResponse()->getStatusCode())->toBe(422);
        $payload = json_decode($exception->getResponse()->getContent(), true);
        expect($payload['error'])->toBe('email_blueprint_incomplete')
            ->and($payload['diagnostics'][0]['code'])->toBe('needs_semantic_authoring')
            ->and($payload)->not->toHaveKeys(['preview_receipt', 'email']);
    }
});
test('notice message renderer keeps historical output byte-identical and appends an escaped action only for its pinned version', function (): void {
    $renderer = app(NoticeMessageEmailRenderer::class);
    $blueprint = ['schema_version' => 'notice-email-1', 'layout' => 'flow',
        'blocks' => [['type' => 'paragraph', 'text' => 'Hello {{applicant_name}}.']]];
    $context = ['applicant_name' => 'Synthetic Applicant'];
    $tokens = ['applicant_name'];
    $action = ['enabled' => true, 'label' => '<Open & view>', 'route' => 'applicant.portal',
        'url' => 'https://example.test/portal?a=1&b=2'];
    $png = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNk+A8AAQUBAScY42YAAAAASUVORK5CYII=', true);
    $imageId = '11111111-1111-4111-8111-111111111111';
    $assets = [['id' => $imageId, 'bytes' => $png, 'sha256' => hash('sha256', $png), 'mime' => 'image/png',
        'cid' => 'image-'.$imageId.'@nexiam.inline']];
    $blueprint['blocks'][] = ['type' => 'image', 'asset_id' => $imageId, 'alt' => 'Synthetic mark', 'width' => 1, 'height' => 1];
    $historical = app(NoticeEmailRenderer::class)->render($blueprint, $context, $tokens,
        'notice-email-1', 'notice-email-1', $assets, 'Notice {{applicant_name}}');

    expect($renderer->render($blueprint, $context, $tokens, 'notice-email-1', 'notice-email-1', $assets,
        'Notice {{applicant_name}}', $action))->toBe($historical);

    $message = $renderer->render($blueprint, $context, $tokens, 'notice-email-1', 'notice-message-email-2',
        $assets, 'Notice {{applicant_name}}', $action);
    $disabled = $renderer->render($blueprint, $context, $tokens, 'notice-email-1', 'notice-message-email-2',
        $assets, 'Notice {{applicant_name}}', ['enabled' => false, 'label' => null, 'route' => null, 'url' => null]);
    $projected = $renderer->project($message['production_html'], $assets)['preview_html'];
    $parse = static function (string $html): DOMDocument {
        $document = new DOMDocument;
        $previous = libxml_use_internal_errors(true);
        $document->loadHTML($html);
        libxml_clear_errors();
        libxml_use_internal_errors($previous);

        return $document;
    };
    $productionDom = $parse($message['production_html']);
    $productionXpath = new DOMXPath($productionDom);
    $actionNode = $productionXpath->query('//a[@href="https://example.test/portal?a=1&b=2"]')->item(0);
    $imageNode = $productionXpath->query('//img[@src="cid:image-'.$imageId.'@nexiam.inline"]')->item(0);
    $projectedDom = $parse($projected);
    $projectedXpath = new DOMXPath($projectedDom);
    $projectedImage = $projectedXpath->query('//img')->item(0);
    $projectedImageBytes = $projectedImage instanceof DOMElement
        ? base64_decode(substr((string) $projectedImage->getAttribute('src'), strlen('data:image/png;base64,')), true)
        : false;
    $disabledDom = $parse($disabled['production_html']);
    $csp = $projectedXpath->query('//meta[translate(@http-equiv, "ABCDEFGHIJKLMNOPQRSTUVWXYZ", "abcdefghijklmnopqrstuvwxyz")="content-security-policy"]')->item(0);
    $disabledXpath = new DOMXPath($disabledDom);

    expect($message['subject'])->toBe('Notice Synthetic Applicant')
        ->and($message['assets'])->toBe($assets)
        ->and($actionNode)->toBeInstanceOf(DOMElement::class)
        ->and($actionNode->textContent)->toBe('<Open & view>')
        ->and($imageNode)->toBeInstanceOf(DOMElement::class)
        ->and($imageNode->getAttribute('src'))->toBe('cid:image-'.$imageId.'@nexiam.inline')
        ->and($message['text'])->toBe($historical['text']."\n\n<Open & view>: https://example.test/portal?a=1&b=2")
        ->and($message['text'])->toContain('[Synthetic mark]')
        ->and($csp)->toBeInstanceOf(DOMElement::class)
        ->and($csp->getAttribute('content'))->toBe(NoticeEmailRenderer::PREVIEW_CSP)
        ->and($projectedImageBytes)->toBe($png)
        ->and($projectedXpath->query('//a[@href]')->length)->toBe(0)
        ->and($disabled['subject'])->toBe($historical['subject'])
        ->and($disabled['text'])->toBe($historical['text'])
        ->and($disabledXpath->query('//a[@href="https://example.test/portal?a=1&b=2"]')->length)->toBe(0);
});

test('notice message renderer refuses unknown renderer pins and unsafe action URLs', function (): void {
    $renderer = app(NoticeMessageEmailRenderer::class);
    $blueprint = ['schema_version' => 'notice-email-1', 'layout' => 'flow',
        'blocks' => [['type' => 'paragraph', 'text' => 'Hello.']]];
    expect(fn () => $renderer->render($blueprint, [], [], 'notice-email-1', 'retired-renderer'))
        ->toThrow(RuntimeException::class, 'email_renderer_unavailable')
        ->and(fn () => $renderer->render($blueprint, [], [], 'notice-email-1', 'notice-message-email-2',
            [], '', ['enabled' => true, 'label' => 'Open', 'route' => 'applicant.portal', 'url' => 'javascript:alert(1)']))
        ->toThrow(RuntimeException::class, 'email_action_invalid');
});
