<?php

declare(strict_types=1);

use App\Models\Foundation\Institution;
use App\Models\Notifications\CommunicationTemplate;
use App\Models\Notifications\CommunicationTemplateDocument;
use App\Models\Notifications\CommunicationTemplateDraft;
use App\Models\User;
use App\Notifications\Templates\Actions\RenderNoticeMessagePreview;
use App\Notifications\Templates\Actions\SaveNoticeMessageDraft;
use App\Notifications\Templates\Email\NoticeEmailRenderer;
use App\Notifications\Templates\Email\NoticeEmailSchema;
use App\Notifications\Templates\NoticeHistoricalMessageProjection;
use App\Notifications\Templates\NoticeMessageState;
use App\Notifications\Templates\Queries\NoticeMessageComposerQuery;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Symfony\Component\HttpKernel\Exception\HttpException;

beforeEach(function (): void {
    seedE2Foundation();
    Storage::fake('local');
    $this->actor = User::query()->where('email', 'guidance.counselor@nexiam.test')->firstOrFail();
    $this->family = createNoticeMessageRegressionFamily('reminder', 'exam.reminder.v1', 'Examination reminder');
});

function createNoticeMessageRegressionFamily(string $slug, string $eventKey, string $name): CommunicationTemplate
{
    $family = CommunicationTemplate::query()->create([
        'institution_id' => Institution::firstOrFail()->id,
        'domain' => 'examination',
        'slug' => $slug,
        'event_key' => $eventKey,
        'name' => $name,
        'active_version' => 1,
        'family_revision' => 1,
        'document_revision' => 1,
    ]);

    $family->versions()->create([
        'version' => 1,
        'content_schema_version' => 'notice-content-3',
        'subject' => 'Exam reminder',
        'body' => 'Check your examination schedule.',
        'action_label' => null,
        'action_route' => null,
        'email_subject' => '',
        'email_body' => '',
        'in_app_title' => '',
        'in_app_body' => '',
        'email_blueprint' => ['schema_version' => 'notice-email-1', 'layout' => 'flow',
            'blocks' => [['type' => 'paragraph', 'text' => 'Hello {{applicant_name}}']]],
        'email_renderer_version' => 'notice-email-1',
        'document_mode' => 'none',
        'content_sha256' => hash('sha256', 'initial-message-version'),
        'created_by_user_id' => test()->actor->id,
    ]);

    return $family;
}

function noticeMessageRegressionState(string $subject = 'Exam reminder'): array
{
    return [
        'subject' => $subject,
        'body' => 'Check your examination schedule.',
        'action_label' => null,
        'action_route' => null,
        'email_blueprint' => ['schema_version' => 'notice-email-1', 'layout' => 'flow',
            'blocks' => [['type' => 'paragraph', 'text' => 'Hello {{applicant_name}}']]],
        'document_revision_id' => null,
    ];
}

function saveNoticeMessageRegressionDraft(CommunicationTemplate $family, string $idempotencyKey = 'regression-message-save'): array
{
    $family = $family->fresh();

    return app(SaveNoticeMessageDraft::class)->save($family, [
        'base_version' => $family->active_version,
        'family_revision' => $family->family_revision,
        'lock_version' => null,
        'idempotency_key' => $idempotencyKey,
        'state' => noticeMessageRegressionState(),
    ], test()->actor);
}

function noticeMessageRegressionPreviewInput(CommunicationTemplate $family, string $view = 'email'): array
{
    $family = $family->fresh();
    $draft = $family->draft()->firstOrFail();
    $state = app(NoticeMessageState::class)->persisted($draft);
    $outputDigest = $view === 'email'
        ? app(NoticeMessageState::class)->emailDigest($family, $state)
        : app(NoticeMessageState::class)->portalDigest($state);

    return [
        'base_version' => $family->active_version,
        'family_revision' => $family->family_revision,
        'lock_version' => $draft->lock_version,
        'draft_id' => $draft->id,
        'view' => $view,
        'output_sha256' => $outputDigest,
    ];
}

test('schema1 historical email projects semantic blocks while preserving portal action separately from email subject', function (): void {
    $legacyBody = '<h2>Schedule update</h2><p>Dear <strong>{{applicant_name}}</strong>,</p><p>Your examination session has been cancelled.</p>';
    $legacyVersion = $this->family->versions()->create([
        'version' => 2,
        'content_schema_version' => 'notice-content-1',
        'subject' => 'Legacy combined subject',
        'body' => 'Legacy combined body.',
        'action_label' => 'Open schedule',
        'action_route' => 'applicant.schedule',
        'email_subject' => 'Session cancelled',
        'email_body' => $legacyBody,
        'in_app_title' => 'Session cancelled',
        'in_app_body' => 'Your examination session has been cancelled.',
        'email_blueprint' => null,
        'email_renderer_version' => 'legacy',
        'document_mode' => 'none',
        'created_by_user_id' => $this->actor->id,
    ]);
    $this->family->update(['active_version' => $legacyVersion->version]);

    $editor = app(NoticeMessageComposerQuery::class)->editor($this->actor, 'examination', 'reminder');
    $state = $editor['state'];
    $blueprint = $state['email_blueprint'];

    expect(NoticeEmailSchema::isValid($blueprint))->toBeTrue()
        ->and($blueprint['blocks'][0])->toMatchArray(['type' => 'heading', 'level' => 2, 'text' => 'Schedule update'])
        ->and($blueprint['blocks'][1]['runs'][1])->toMatchArray(['text' => '{{applicant_name}}', 'bold' => true])
        ->and($editor['conversion_review'])->toMatchArray([
            'source_schema_version' => 'notice-content-1',
            'source_renderer_version' => 'legacy',
            'status' => 'ready',
            'email_subject' => 'Session cancelled',
            'email_body' => $legacyBody,
            'in_app_title' => 'Session cancelled',
            'in_app_body' => 'Your examination session has been cancelled.',
            'blockers' => [],
        ])
        ->and($state['subject'])->toBe('Session cancelled')
        ->and($state['body'])->toBe('Your examination session has been cancelled.')
        ->and($state['action_label'])->toBe('Open schedule')
        ->and($state['action_route'])->toBe('applicant.schedule');

    actingAsAdmissionSupportStaff($this, $this->actor)->postJson(route('setup.notifications.templates.drafts.store', [
        'domain' => 'examination', 'slug' => 'reminder',
    ]), [
        'base_version' => $this->family->active_version,
        'family_revision' => $this->family->family_revision,
        'lock_version' => null,
        'idempotency_key' => 'historical-message-save',
        'source_version' => 2,
        'state' => $state,
    ])->assertCreated();
    $reopened = $this->getJson(route('setup.notifications.templates.editor-state', [
        'domain' => 'examination', 'slug' => 'reminder',
    ]))->assertOk()->json();
    expect($reopened['content_schema_version'])->toBe('notice-content-3')
        ->and($reopened['email_renderer_version'])->toBe(NoticeMessageState::NEW_EMAIL_RENDERER)
        ->and($legacyVersion->fresh()->email_subject)->toBe('Session cancelled')
        ->and($legacyVersion->fresh()->email_body)->toBe($legacyBody)
        ->and($reopened['state']['subject'])->toBe('Session cancelled')
        ->and($reopened['state']['body'])->toBe('Your examination session has been cancelled.')
        ->and($reopened['state']['action_label'])->toBe('Open schedule')
        ->and($reopened['state']['action_route'])->toBe('applicant.schedule')
        ->and($reopened['state']['email_blueprint'])->toBe($state['email_blueprint']);
});

test('schema1 historical email preserves nested runs, links, lists and stable token text', function (): void {
    $legacyBody = '<p>Dear <strong>{{applicant_name}}</strong>, <em>review <a href="https://example.edu/schedule">your <u>schedule</u></a></em>.</p>'
        .'<ol><li>Bring your permit<ul><li><strong>{{session_code}}</strong></li></ul></li><li>Arrive on time</li></ol>';
    $legacyVersion = $this->family->versions()->create([
        'version' => 2, 'content_schema_version' => 'notice-content-1', 'subject' => 'Legacy title',
        'body' => 'Legacy body.', 'action_label' => 'View schedule', 'action_route' => 'applicant.schedule',
        'email_subject' => 'Portal title', 'email_body' => $legacyBody,
        'in_app_title' => 'Portal title', 'in_app_body' => 'Portal body.',
        'email_blueprint' => null, 'email_renderer_version' => 'legacy', 'document_mode' => 'none',
        'created_by_user_id' => $this->actor->id,
    ]);
    $this->family->update(['active_version' => $legacyVersion->version]);

    $editor = actingAsAdmissionSupportStaff($this, $this->actor)->getJson(route('setup.notifications.templates.editor-state', [
        'domain' => 'examination', 'slug' => 'reminder', 'source_version' => 2,
    ]))->assertOk()->json();
    $blueprint = $editor['state']['email_blueprint'];

    expect(NoticeEmailSchema::isValid($blueprint))->toBeTrue()
        ->and($blueprint['blocks'][0]['runs'][0])->toMatchArray(['text' => 'Dear '])
        ->and($blueprint['blocks'][0]['runs'][1])->toMatchArray(['text' => '{{applicant_name}}', 'bold' => true])
        ->and($blueprint['blocks'][0]['runs'][3])->toMatchArray(['text' => 'review ', 'italic' => true])
        ->and($blueprint['blocks'][0]['runs'][4])->toMatchArray(['text' => 'your ', 'italic' => true, 'link' => 'https://example.edu/schedule'])
        ->and($blueprint['blocks'][1]['type'])->toBe('list')
        ->and($blueprint['blocks'][1]['ordered'])->toBeTrue()
        ->and($blueprint['blocks'][1]['items'][0]['blocks'][1]['type'])->toBe('list')
        ->and($editor['conversion_review']['status'])->toBe('ready');
    expect($legacyVersion->fresh()->email_body)->toBe($legacyBody);
    $rendered = app(NoticeEmailRenderer::class)->render(
        $blueprint,
        ['applicant_name' => 'Applicant Example', 'session_code' => 'EX-204'],
        ['applicant_name', 'session_code'],
        NoticeEmailSchema::SCHEMA_VERSION,
        NoticeEmailRenderer::VERSION,
    );
    expect($rendered['production_html'])->toContain('<strong>Applicant Example</strong>')
        ->and($rendered['production_html'])->toContain('<strong>EX-204</strong>')
        ->and(strpos($rendered['production_html'], 'Bring your permit'))->toBeLessThan(strpos($rendered['production_html'], 'EX-204'))
        ->and($rendered['text'])->toContain('Applicant Example')
        ->and($rendered['text'])->toContain('EX-204');
});

test('schema1 historical images map only through verified family-owned assets', function (): void {
    $imageBytes = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNk+A8AAQUBAScY42YAAAAASUVORK5CYII=', true);
    $assetId = (string) Str::uuid();
    $path = 'communication-templates/'.$this->family->id.'/historical-pixel.png';
    Storage::disk('local')->put($path, $imageBytes);
    CommunicationTemplateDocument::query()->create([
        'id' => $assetId,
        'communication_template_id' => $this->family->id,
        'institution_id' => $this->family->institution_id,
        'original_name' => 'historical-pixel.png',
        'storage_path' => $path,
        'checksum' => hash('sha256', $imageBytes),
        'mime_type' => 'image/png',
        'file_size_bytes' => strlen($imageBytes),
        'created_by_user_id' => $this->actor->id,
    ]);
    $legacyBody = '<p>Before <img src="'.$assetId.'" alt="Owned mark"> after</p>';
    $legacyVersion = $this->family->versions()->create([
        'version' => 2, 'content_schema_version' => 'notice-content-1',
        'subject' => 'Legacy title', 'body' => 'Legacy body.',
        'action_label' => null, 'action_route' => null,
        'email_subject' => 'Portal title', 'email_body' => $legacyBody,
        'in_app_title' => 'Portal title', 'in_app_body' => 'Portal body.',
        'email_blueprint' => null, 'email_renderer_version' => 'legacy', 'document_mode' => 'none',
        'created_by_user_id' => $this->actor->id,
    ]);
    $this->family->update(['active_version' => $legacyVersion->version]);

    $editor = app(NoticeMessageComposerQuery::class)->editor($this->actor, 'examination', 'reminder');

    expect($editor['conversion_review']['status'])->toBe('ready')
        ->and(NoticeEmailSchema::isValid($editor['state']['email_blueprint']))->toBeTrue()
        ->and($editor['state']['email_blueprint']['blocks'][1])->toMatchArray([
            'type' => 'image', 'asset_id' => $assetId, 'alt' => 'Owned mark', 'width' => 1, 'height' => 1,
        ])
        ->and($editor['assets'][0]['asset_id'])->toBe($assetId)
        ->and($legacyVersion->fresh()->email_body)->toBe($legacyBody);
});

test('content2 semantic source with a noncanonical action remains blocked from conversion', function (): void {
    $legacyVersion = $this->family->versions()->create([
        'version' => 2, 'content_schema_version' => 'notice-content-2',
        'subject' => 'Legacy message title', 'body' => 'Legacy message body.',
        'action_label' => 'Open unrelated destination', 'action_route' => 'applicant.login',
        'email_subject' => 'Legacy email title', 'email_body' => '<p>Legacy email body.</p>',
        'in_app_title' => 'Legacy portal title', 'in_app_body' => 'Legacy portal body.',
        'email_blueprint' => noticeMessageRegressionState()['email_blueprint'],
        'email_renderer_version' => 'notice-email-1', 'document_mode' => 'none',
        'created_by_user_id' => $this->actor->id,
    ]);
    $this->family->update(['active_version' => $legacyVersion->version]);

    $editor = app(NoticeMessageComposerQuery::class)->editor($this->actor, 'examination', 'reminder');

    expect($editor['state']['email_blueprint'])->toBeNull()
        ->and($editor['state']['action_label'])->toBe('Open unrelated destination')
        ->and($editor['state']['action_route'])->toBe('applicant.login')
        ->and($editor['conversion_review']['status'])->toBe('blocked')
        ->and($editor['conversion_review']['email_subject'])->toBe('Legacy email title')
        ->and(collect($editor['conversion_review']['blockers'])->pluck('code')->all())
        ->toContain('historical_action_not_canonical')
        ->and($legacyVersion->fresh()->action_route)->toBe('applicant.login');
});

test('unsupported historical email content remains available with a blocking review DTO', function (): void {
    $legacyBody = '<p>Preserve this text.</p><video src="https://media.example.edu/notice.mp4">Video instructions</video>';
    $legacyVersion = $this->family->versions()->create([
        'version' => 2, 'content_schema_version' => 'notice-content-1', 'subject' => 'Legacy title',
        'body' => 'Legacy body.', 'action_label' => 'Open schedule', 'action_route' => 'applicant.schedule',
        'email_subject' => 'Video notice', 'email_body' => $legacyBody,
        'in_app_title' => 'Portal title', 'in_app_body' => 'Portal body.',
        'email_blueprint' => null, 'email_renderer_version' => 'legacy', 'document_mode' => 'none',
        'created_by_user_id' => $this->actor->id,
    ]);
    $this->family->update(['active_version' => $legacyVersion->version]);

    $editor = actingAsAdmissionSupportStaff($this, $this->actor)->getJson(route('setup.notifications.templates.editor-state', [
        'domain' => 'examination', 'slug' => 'reminder', 'source_version' => 2,
    ]))->assertOk()->json();

    expect($editor['content_schema_version'])->toBe('notice-content-1')
        ->and($editor['email_renderer_version'])->toBe('legacy')
        ->and($editor['state']['email_blueprint'])->toBeNull()
        ->and($editor['state']['subject'])->toBe('Portal title')
        ->and($editor['state']['action_label'])->toBe('Open schedule')
        ->and($editor['state']['action_route'])->toBe('applicant.schedule')
        ->and($editor['conversion_review']['status'])->toBe('blocked')
        ->and($editor['conversion_review']['email_subject'])->toBe('Video notice')
        ->and($editor['conversion_review']['email_body'])->toBe($legacyBody)
        ->and($editor['conversion_review']['in_app_title'])->toBe('Portal title')
        ->and(collect($editor['conversion_review']['blockers'])->pluck('code')->all())
        ->toContain('unsupported_html_element', 'differing_channel_titles')
        ->and($legacyVersion->fresh()->email_body)->toBe($legacyBody);
});

test('historical projection blocks list elements outside list items without changing source markup', function (): void {
    $legacyBody = '<ul><li>Mapped item</li><p>Unmapped list child</p></ul>';
    $legacyVersion = $this->family->versions()->create([
        'version' => 2, 'content_schema_version' => 'notice-content-1', 'subject' => 'Legacy title', 'body' => 'Legacy body.',
        'action_label' => null, 'action_route' => null, 'email_subject' => 'Portal title', 'email_body' => $legacyBody,
        'in_app_title' => 'Portal title', 'in_app_body' => 'Portal body.',
        'email_blueprint' => null, 'email_renderer_version' => 'legacy', 'document_mode' => 'none',
        'created_by_user_id' => $this->actor->id,
    ]);
    $this->family->update(['active_version' => 2]);

    $editor = actingAsAdmissionSupportStaff($this, $this->actor)->getJson(route('setup.notifications.templates.editor-state', [
        'domain' => 'examination', 'slug' => 'reminder', 'source_version' => 2,
    ]))->assertOk()->json();

    expect($editor['state']['email_blueprint'])->toBeNull()
        ->and($editor['conversion_review']['status'])->toBe('blocked')
        ->and(collect($editor['conversion_review']['blockers'])->pluck('code')->all())->toContain('list_content_unmapped')
        ->and($legacyVersion->fresh()->email_body)->toBe($legacyBody);
});

test('historical projection blocks asymmetric titles, lossy styles, and explicit image heights', function (): void {
    $cases = [
        ['<p>Plain body</p>', 'Email title', ''],
        ['<p><span style="color: #abc; font-weight: bold">Styled text</span></p>', 'Portal title', 'Portal title'],
    ];
    foreach ($cases as $index => [$emailBody, $emailTitle, $portalTitle]) {
        $legacyVersion = $this->family->versions()->create([
            'version' => $index + 2, 'content_schema_version' => 'notice-content-1', 'subject' => 'Legacy title', 'body' => 'Legacy body.',
            'action_label' => null, 'action_route' => null, 'email_subject' => $emailTitle, 'email_body' => $emailBody,
            'in_app_title' => $portalTitle, 'in_app_body' => 'Portal body.',
            'email_blueprint' => null, 'email_renderer_version' => 'legacy', 'document_mode' => 'none',
            'created_by_user_id' => $this->actor->id,
        ]);

        $projection = app(NoticeHistoricalMessageProjection::class)->project($legacyVersion, $this->family);

        expect($projection['state']['email_blueprint'])->toBeNull()
            ->and($projection['conversion_review']['status'])->toBe('blocked')
            ->and($legacyVersion->fresh()->email_body)->toBe($emailBody);
    }

    $imageBytes = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNk+A8AAQUBAScY42YAAAAASUVORK5CYII=', true);
    $assetId = (string) Str::uuid();
    $path = 'communication-templates/'.$this->family->id.'/explicit-height.png';
    Storage::disk('local')->put($path, $imageBytes);
    CommunicationTemplateDocument::query()->create([
        'id' => $assetId, 'communication_template_id' => $this->family->id, 'institution_id' => $this->family->institution_id,
        'original_name' => 'explicit-height.png', 'storage_path' => $path, 'checksum' => hash('sha256', $imageBytes),
        'mime_type' => 'image/png', 'file_size_bytes' => strlen($imageBytes), 'created_by_user_id' => $this->actor->id,
    ]);
    $legacyBody = '<p><img src="'.$assetId.'" height="40" alt="Distorted"></p>';
    $legacyVersion = $this->family->versions()->create([
        'version' => 4, 'content_schema_version' => 'notice-content-1', 'subject' => 'Legacy title', 'body' => 'Legacy body.',
        'action_label' => null, 'action_route' => null, 'email_subject' => 'Portal title', 'email_body' => $legacyBody,
        'in_app_title' => 'Portal title', 'in_app_body' => 'Portal body.',
        'email_blueprint' => null, 'email_renderer_version' => 'legacy', 'document_mode' => 'none',
        'created_by_user_id' => $this->actor->id,
    ]);

    $projection = app(NoticeHistoricalMessageProjection::class)->project($legacyVersion, $this->family);
    expect($projection['state']['email_blueprint'])->toBeNull()
        ->and(collect($projection['conversion_review']['blockers'])->pluck('code')->all())
        ->toContain('image_explicit_height_unrepresentable')
        ->and($legacyVersion->fresh()->email_body)->toBe($legacyBody);
});
test('historical channel-title conflict cannot be saved as a new message draft', function (): void {
    $legacyVersion = $this->family->versions()->create([
        'version' => 2, 'content_schema_version' => 'notice-content-1',
        'subject' => 'Legacy combined title', 'body' => 'Legacy combined body.',
        'action_label' => null, 'action_route' => null,
        'email_subject' => 'Email title', 'email_body' => '<p>Preserved email content.</p>',
        'in_app_title' => 'Portal title', 'in_app_body' => 'Preserved portal content.',
        'email_blueprint' => null, 'email_renderer_version' => 'legacy', 'document_mode' => 'none',
        'created_by_user_id' => $this->actor->id,
    ]);
    $this->family->update(['active_version' => $legacyVersion->version]);
    $state = noticeMessageRegressionState('Portal title');

    try {
        app(SaveNoticeMessageDraft::class)->save($this->family, [
            'base_version' => $this->family->active_version,
            'family_revision' => $this->family->family_revision,
            'lock_version' => null,
            'idempotency_key' => 'blocked-historical-title-save',
            'source_version' => 2,
            'state' => $state,
        ], $this->actor);
        test()->fail('A client-authored blueprint cannot bypass an unresolved historical title conflict.');
    } catch (HttpException $exception) {
        expect($exception->getStatusCode())->toBe(422);
    }

    expect($this->family->draft()->exists())->toBeFalse()
        ->and($legacyVersion->fresh()->email_subject)->toBe('Email title')
        ->and($legacyVersion->fresh()->in_app_title)->toBe('Portal title')
        ->and($legacyVersion->fresh()->email_body)->toBe('<p>Preserved email content.</p>');
});
test('historical conversion rejects a source from a different template family', function (): void {
    $source = $this->family->versions()->create([
        'version' => 2, 'content_schema_version' => 'notice-content-1',
        'subject' => 'Legacy title', 'body' => 'Legacy body.',
        'action_label' => null, 'action_route' => null,
        'email_subject' => 'Portal title', 'email_body' => '<p>Message body.</p>',
        'in_app_title' => 'Portal title', 'in_app_body' => 'Message body.',
        'email_blueprint' => null, 'email_renderer_version' => 'legacy', 'document_mode' => 'none',
        'created_by_user_id' => $this->actor->id,
    ]);
    $otherFamily = createNoticeMessageRegressionFamily('other-reminder', 'exam.other_reminder.v1', 'Other reminder');

    expect(fn () => app(NoticeHistoricalMessageProjection::class)->project($source, $otherFamily))
        ->toThrow(RuntimeException::class, 'historical_source_mismatch');
});

test('invalid email content in preview check preserves actionable 422 diagnostics', function (): void {
    saveNoticeMessageRegressionDraft($this->family);
    $draft = $this->family->draft()->firstOrFail();
    $draft->update(['email_blueprint' => [
        'schema_version' => 'notice-email-1',
        'layout' => 'flow',
        'blocks' => [['type' => 'paragraph', 'text' => 'Cancellation reason: {{cancellation_reason}}']],
    ]]);

    try {
        app(RenderNoticeMessagePreview::class)->execute(
            $this->family->fresh(),
            noticeMessageRegressionPreviewInput($this->family),
            $this->actor,
        );
        $this->fail('The unavailable message token should produce an actionable 422 diagnostic.');
    } catch (HttpException $exception) {
        expect($exception->getStatusCode())->toBe(422)
            ->and($exception->getMessage())->toBe('email_token_unknown');
    }
});

test('attachment check request succeeds without idempotency_key', function (): void {
    $family = createNoticeMessageRegressionFamily('session-published', 'exam.session_published.v1', 'Examination session published and permit');
    $saved = saveNoticeMessageRegressionDraft($family, 'attachment-check-message-save');
    $family = $family->fresh();
    $draft = $family->draft()->firstOrFail();

    actingAsAdmissionSupportStaff($this, $this->actor)->postJson('/setup/notifications/examination/templates/session-published/attachment-check', [
        'base_version' => $family->active_version,
        'family_revision' => $family->family_revision,
        'lock_version' => $draft->lock_version,
        'document_revision_id' => null,
        'binding_sha256' => $saved['output_digests']['binding_sha256'],
    ])->assertOk()->assertJsonPath('document', null);
});

test('late output check rejects a draft mutated while the preview is rendering', function (): void {
    saveNoticeMessageRegressionDraft($this->family);
    $family = $this->family->fresh();
    $draft = $family->draft()->firstOrFail();
    $previewInput = noticeMessageRegressionPreviewInput($family);
    $realRenderer = app(NoticeEmailRenderer::class);
    $renderer = Mockery::mock(NoticeEmailRenderer::class);
    $renderer->shouldReceive('render')->once()->andReturnUsing(function (...$arguments) use ($realRenderer, $draft): array {
        $rendered = $realRenderer->render(...$arguments);
        CommunicationTemplateDraft::query()->whereKey($draft->id)->update(['subject' => 'Edited while the preview was rendering.']);

        return $rendered;
    });
    $renderer->shouldReceive('project')->once()->andReturnUsing(function (string $productionHtml, array $assets) use ($realRenderer): array {
        return $realRenderer->project($productionHtml, $assets);
    });
    app()->instance(NoticeEmailRenderer::class, $renderer);

    try {
        app(RenderNoticeMessagePreview::class)->execute($family, $previewInput, $this->actor);
        $this->fail('A preview for the prior draft state must be rejected after the draft changes.');
    } catch (HttpException $exception) {
        expect($exception->getStatusCode())->toBe(422)
            ->and($exception->getMessage())->toBe('This output changed while it was being checked.');
    }
});
