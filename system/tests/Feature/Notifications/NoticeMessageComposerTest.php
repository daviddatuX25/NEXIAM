<?php

use App\Models\Foundation\AuditEvent;
use App\Models\Foundation\Institution;
use App\Models\Notifications\CommunicationTemplate;
use App\Models\Notifications\CommunicationTemplateDocument;
use App\Models\Notifications\CommunicationTemplateReceipt;
use App\Models\User;
use App\Notifications\NotificationDelivery;
use App\Notifications\Templates\Actions\PublishNoticeMessageVersion;
use App\Notifications\Templates\Actions\RenderNoticeMessagePreview;
use App\Notifications\Templates\Actions\SaveNoticeMessageDraft;
use App\Notifications\Templates\Actions\StoreCommunicationTemplateDocument;
use App\Notifications\Templates\Actions\StoreNoticeMessageImage;
use App\Notifications\Templates\NoticeMessageOutputReceipt;
use App\Notifications\Templates\NoticeMessageState;
use App\Notifications\Templates\Queries\NoticeMessageComposerQuery;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpKernel\Exception\HttpException;

beforeEach(function (): void {
    seedE2Foundation();
    Storage::fake('local');
    $this->actor = User::query()->where('email', 'guidance.counselor@nexiam.test')->firstOrFail();
    $this->family = CommunicationTemplate::query()->create(['institution_id' => Institution::firstOrFail()->id,
        'domain' => 'examination', 'slug' => 'reminder', 'event_key' => 'exam.reminder.v1', 'name' => 'Examination reminder',
        'active_version' => 1, 'family_revision' => 1]);
    $this->oldVersion = $this->family->versions()->create(['version' => 1, 'content_schema_version' => 'notice-content-2',
        'subject' => 'Old reminder subject', 'body' => 'Old reminder caption', 'email_subject' => 'Old email subject',
        'email_body' => 'Historical email bytes', 'in_app_title' => 'Old portal title', 'in_app_body' => 'Old portal bytes',
        'email_blueprint' => ['schema_version' => 'notice-email-1', 'layout' => 'flow', 'blocks' => [['type' => 'paragraph', 'text' => 'Old email']]],
        'email_renderer_version' => 'notice-email-1', 'document_mode' => 'none']);
});

function reminderMessageState(string $subject = 'Exam reminder'): array
{
    return ['subject' => $subject, 'body' => 'Check your examination schedule.', 'action_label' => null, 'action_route' => null, 'document_revision_id' => null,
        'email_blueprint' => ['schema_version' => 'notice-email-1', 'layout' => 'flow', 'blocks' => [['type' => 'paragraph', 'text' => 'Hello {{applicant_name}}']]]];
}

function reminderMessageGuards(string $key, ?string $lockVersion = null): array
{
    $family = test()->family->fresh();

    return ['base_version' => $family->active_version, 'family_revision' => $family->family_revision, 'lock_version' => $lockVersion,
        'draft_id' => test()->family->draft()->value('id'), 'idempotency_key' => $key];
}

function saveReminderMessage(string $key = 'message-save', ?array $state = null): array
{
    return app(SaveNoticeMessageDraft::class)->save(test()->family, [...reminderMessageGuards($key), 'state' => $state ?? reminderMessageState()], test()->actor);
}

function checkReminderMessage(string $view, array $guards): array
{
    $family = test()->family->fresh();
    $draft = $family->draft()->firstOrFail();
    $message = app(NoticeMessageState::class)->persisted($draft);
    $previewGuards = $guards;
    unset($previewGuards['idempotency_key']);
    $rendererVersion = app(NoticeMessageState::class)->rendererVersion($draft);
    $digest = $view === 'email'
        ? app(NoticeMessageState::class)->emailDigest($family, $message, $rendererVersion)
        : app(NoticeMessageState::class)->portalDigest($message, $rendererVersion);

    return app(RenderNoticeMessagePreview::class)->execute($family,
        [...$previewGuards, 'view' => $view, 'output_sha256' => $digest], test()->actor);
}

test('schema3 save and reload expose only message fields and keep old publication intact', function (): void {
    $saved = saveReminderMessage();
    $draft = $this->family->draft()->firstOrFail();
    $reloaded = app(NoticeMessageComposerQuery::class)->editor($this->actor, 'examination', 'reminder');

    expect($draft->content_schema_version)->toBe('notice-content-3')
        ->and($draft->document_mode)->toBe('none')->and($draft->document_asset_id)->toBeNull()
        ->and($draft->builder_json)->toBeNull()->and($draft->paper_size)->toBeNull()->and($draft->orientation)->toBeNull()
        ->and($draft->document_source_drafts)->toBeNull()->and($draft->import_provenance)->toBeNull()
        ->and(array_keys($reloaded['state']))->toBe(NoticeMessageState::FIELDS)
        ->and($reloaded['state']['subject'])->toBe('Exam reminder')
        ->and($saved['output_digests']['email_sha256'])->toBe($reloaded['output_digests']['email_sha256'])
        ->and($this->oldVersion->fresh()->email_body)->toBe('Historical email bytes');
});
test('schema3 message DTO returns bare typed-token names and concrete current draft endpoints', function (): void {
    $saved = saveReminderMessage();
    $reloaded = app(NoticeMessageComposerQuery::class)->editor($this->actor, 'examination', 'reminder');

    expect($reloaded['tokens'])->toContain(['token' => 'applicant_name', 'label' => 'Applicant Name'])
        ->and($reloaded['draft']['endpoints']['update'])->toBe(route('setup.notifications.templates.drafts.update', [
            'domain' => 'examination', 'slug' => 'reminder', 'draft' => $saved['draft']['id'],
        ]));
});

test('portal caption changes only portal digest and message text changes only email digest', function (): void {
    $saved = saveReminderMessage();
    $before = $saved['output_digests'];
    $state = reminderMessageState();
    $state['body'] = 'A changed portal caption';
    $captionSave = app(SaveNoticeMessageDraft::class)->save($this->family->fresh(), [...reminderMessageGuards('caption-save', '1'), 'state' => $state], $this->actor);
    expect($captionSave['output_digests']['email_sha256'])->toBe($before['email_sha256'])
        ->and($captionSave['output_digests']['portal_sha256'])->not->toBe($before['portal_sha256']);
    $before = $captionSave['output_digests'];
    $state['email_blueprint']['blocks'][0]['text'] = 'Changed email only';
    $emailSave = app(SaveNoticeMessageDraft::class)->save($this->family->fresh(), [...reminderMessageGuards('email-save', '2'), 'state' => $state], $this->actor);
    expect($emailSave['output_digests']['email_sha256'])->not->toBe($before['email_sha256'])
        ->and($emailSave['output_digests']['portal_sha256'])->toBe($before['portal_sha256']);
    $state['subject'] = 'A new shared title';
    $shared = app(SaveNoticeMessageDraft::class)->save($this->family->fresh(), [...reminderMessageGuards('shared-save', '3'), 'state' => $state], $this->actor);
    expect($shared['output_digests']['email_sha256'])->not->toBe($emailSave['output_digests']['email_sha256'])
        ->and($shared['output_digests']['portal_sha256'])->not->toBe($emailSave['output_digests']['portal_sha256']);
});

test('current email and portal proofs publish an immutable future message and exact replay creates no second version', function (): void {
    saveReminderMessage();
    $guards = reminderMessageGuards('preview-guards', '1');
    $email = checkReminderMessage('email', $guards);
    $portal = checkReminderMessage('portal', $guards);
    $draft = $this->family->draft()->firstOrFail();
    $family = $this->family->fresh();
    $publish = ['base_version' => $family->active_version, 'family_revision' => $family->family_revision, 'lock_version' => $draft->lock_version,
        'draft_id' => $draft->id, 'idempotency_key' => 'message-publish', 'content_sha256' => $draft->content_sha256,
        'preview_receipts' => ['email' => $email['preview_receipt'], 'portal' => $portal['preview_receipt']]];
    $auditCount = AuditEvent::query()->where('event_type', 'communication_template.message_publish')->count();
    $result = app(PublishNoticeMessageVersion::class)->publish($family, $publish, $this->actor);
    $replay = app(PublishNoticeMessageVersion::class)->publish($this->family->fresh(), $publish, $this->actor);

    expect($result['version']['version'])->toBe(2)->and($replay['replayed'])->toBeTrue()
        ->and($this->family->versions()->count())->toBe(2)->and($this->family->fresh()->active_version)->toBe(2)
        ->and($this->family->versions()->where('version', 2)->value('content_schema_version'))->toBe('notice-content-3')
        ->and($this->family->versions()->where('version', 2)->value('document_mode'))->toBe('none')
        ->and($this->family->draft()->exists())->toBeFalse()
        ->and(CommunicationTemplateReceipt::query()->where('operation', 'message_publish')->count())->toBe(1)
        ->and(AuditEvent::query()->where('event_type', 'communication_template.message_publish')->count())->toBe($auditCount + 1);
});
test('an existing content3 draft publishes under its saved renderer pin', function (): void {
    saveReminderMessage();
    $draft = $this->family->draft()->firstOrFail();
    $draft->email_renderer_version = 'notice-email-1';
    $draft->content_sha256 = app(NoticeMessageState::class)->contentDigest(
        $this->family->fresh(),
        app(NoticeMessageState::class)->persisted($draft),
        'notice-email-1',
    );
    $draft->save();
    $guards = reminderMessageGuards('old-pin-check', '1');
    $email = checkReminderMessage('email', $guards);
    $portal = checkReminderMessage('portal', $guards);
    $draft = $this->family->draft()->firstOrFail();
    $family = $this->family->fresh();
    $input = ['base_version' => $family->active_version, 'family_revision' => $family->family_revision,
        'lock_version' => $draft->lock_version, 'draft_id' => $draft->id, 'idempotency_key' => 'old-pin-publish',
        'content_sha256' => app(NoticeMessageState::class)->contentDigest($family, app(NoticeMessageState::class)->persisted($draft), 'notice-email-1'),
        'preview_receipts' => ['email' => $email['preview_receipt'], 'portal' => $portal['preview_receipt']]];

    app(PublishNoticeMessageVersion::class)->publish($family, $input, $this->actor);

    expect($this->family->versions()->where('version', 2)->value('email_renderer_version'))->toBe('notice-email-1');
});

test('message HTTP flow saves, reloads, checks independent outputs and publishes future-only content', function (): void {
    $parameters = ['domain' => 'examination', 'slug' => 'reminder'];
    $editor = actingAsAdmissionSupportStaff($this, $this->actor)->getJson(route('setup.notifications.templates.editor-state', $parameters))
        ->assertOk()->json();
    $state = reminderMessageState('HTTP examination reminder');
    $deliveryCount = NotificationDelivery::query()->count();
    $created = $this->postJson(route('setup.notifications.templates.drafts.store', $parameters), [
        'state' => $state, 'base_version' => $editor['base_version'], 'family_revision' => $editor['family']['family_revision'],
        'lock_version' => null, 'idempotency_key' => 'http-message-create',
    ])->assertCreated()->json();
    expect($created['draft']['state'])->toBe($state)
        ->and($created['draft']['state'])->not->toHaveKeys(['document_mode', 'document_asset_id', 'builder_json', 'paper_size',
            'orientation', 'document_source_drafts', 'import_provenance']);

    $guards = ['base_version' => $editor['base_version'], 'family_revision' => $created['family_revision'],
        'lock_version' => $created['draft']['lock_version']];
    $email = $this->postJson(route('setup.notifications.templates.preview', $parameters), [
        ...$guards, 'view' => 'email', 'output_sha256' => $created['output_digests']['email_sha256'],
    ])->assertOk()->assertJsonPath('view', 'email')->assertJsonPath('output_sha256', $created['output_digests']['email_sha256'])->json();
    $portal = $this->postJson(route('setup.notifications.templates.preview', $parameters), [
        ...$guards, 'view' => 'portal', 'output_sha256' => $created['output_digests']['portal_sha256'],
    ])->assertOk()->assertJsonPath('view', 'portal')->assertJsonPath('output_sha256', $created['output_digests']['portal_sha256'])->json();

    $state['body'] = 'An updated portal-only caption.';
    $updated = $this->putJson(route('setup.notifications.templates.drafts.update', [...$parameters, 'draft' => $created['draft']['id']]), [
        'state' => $state, ...$guards, 'idempotency_key' => 'http-caption-update',
    ])->assertOk()->json();
    expect($updated['output_digests']['email_sha256'])->toBe($created['output_digests']['email_sha256'])
        ->and($updated['output_digests']['portal_sha256'])->not->toBe($created['output_digests']['portal_sha256']);
    $reloaded = $this->getJson(route('setup.notifications.templates.editor-state', $parameters))
        ->assertOk()->assertJsonPath('draft.state.body', 'An updated portal-only caption.')->json();
    $currentGuards = ['base_version' => $editor['base_version'], 'family_revision' => $updated['family_revision'],
        'lock_version' => $updated['draft']['lock_version']];
    $stalePublication = [...$currentGuards, 'content_sha256' => $updated['content_sha256'],
        'preview_receipts' => ['email' => $email['preview_receipt'], 'portal' => $portal['preview_receipt']],
        'idempotency_key' => 'http-stale-publication'];
    $beforeBlockedPublish = [$this->family->fresh()->active_version, $this->family->versions()->count(),
        CommunicationTemplateReceipt::query()->where('operation', 'message_publish')->count(),
        NotificationDelivery::query()->count()];
    $this->postJson(route('setup.notifications.templates.publish', $parameters), $stalePublication)->assertUnprocessable();
    expect([$this->family->fresh()->active_version, $this->family->versions()->count(),
        CommunicationTemplateReceipt::query()->where('operation', 'message_publish')->count(),
        NotificationDelivery::query()->count()])->toBe($beforeBlockedPublish);

    $portalCurrent = $this->postJson(route('setup.notifications.templates.preview', $parameters), [
        ...$currentGuards, 'view' => 'portal', 'output_sha256' => $updated['output_digests']['portal_sha256'],
    ])->assertOk()->assertJsonPath('portal.body', 'An updated portal-only caption.')->json();
    $publish = [...$currentGuards, 'content_sha256' => $updated['content_sha256'],
        'preview_receipts' => ['email' => $email['preview_receipt'], 'portal' => $portalCurrent['preview_receipt']],
        'idempotency_key' => 'http-message-publication'];
    $published = $this->postJson(route('setup.notifications.templates.publish', $parameters), $publish)
        ->assertOk()->assertJsonPath('draft', null)->json();
    $replayed = $this->postJson(route('setup.notifications.templates.publish', $parameters), $publish)
        ->assertOk()->assertJsonPath('replayed', true)->json();
    $publishedVersion = $this->family->versions()->where('version', 2)->firstOrFail();

    expect($published['version']['version'])->toBe(2)->and($replayed['version'])->toBe($published['version'])
        ->and($this->family->fresh()->active_version)->toBe(2)
        ->and($publishedVersion->email_renderer_version)->toBe(NoticeMessageState::NEW_EMAIL_RENDERER)
        ->and($publishedVersion->content_sha256)->toBe($updated['content_sha256'])
        ->and($publishedVersion->document_mode)->toBe('none')
        ->and($publishedVersion->document_asset_id)->toBeNull()
        ->and($publishedVersion->builder_json)->toBeNull()
        ->and($this->family->draft()->exists())->toBeFalse()
        ->and($this->family->versions()->count())->toBe(2)
        ->and(CommunicationTemplateReceipt::query()->where('operation', 'message_publish')->count())->toBe(1)
        ->and(NotificationDelivery::query()->count())->toBe($deliveryCount);
});
test('preview receipts invalidate only the changed output and preserve unaffected current proofs', function (): void {
    saveReminderMessage();
    $email = checkReminderMessage('email', reminderMessageGuards('email-before-caption', '1'));
    $portal = checkReminderMessage('portal', reminderMessageGuards('portal-before-caption', '1'));
    $state = reminderMessageState();
    $state['body'] = 'Caption-only edit';
    app(SaveNoticeMessageDraft::class)->save($this->family->fresh(), [...reminderMessageGuards('save-caption', '1'), 'state' => $state], $this->actor);
    $family = $this->family->fresh();
    $draft = $family->draft()->firstOrFail();
    app(NoticeMessageOutputReceipt::class)->verify($family, $draft, $this->actor, 'email', $email['preview_receipt']);
    try {
        app(NoticeMessageOutputReceipt::class)->verify($family, $draft, $this->actor, 'portal', $portal['preview_receipt']);
        test()->fail('A caption change must invalidate Portal proof.');
    } catch (HttpResponseException $exception) {
        expect($exception->getResponse()->getStatusCode())->toBe(422);
    }

    $portal = checkReminderMessage('portal', reminderMessageGuards('portal-after-caption', '2'));
    $state['email_blueprint']['blocks'][0]['text'] = 'Email-only edit';
    app(SaveNoticeMessageDraft::class)->save($this->family->fresh(), [...reminderMessageGuards('save-email', '2'), 'state' => $state], $this->actor);
    $family = $this->family->fresh();
    $draft = $family->draft()->firstOrFail();
    app(NoticeMessageOutputReceipt::class)->verify($family, $draft, $this->actor, 'portal', $portal['preview_receipt']);
    try {
        app(NoticeMessageOutputReceipt::class)->verify($family, $draft, $this->actor, 'email', $email['preview_receipt']);
        test()->fail('Email content change must invalidate Email proof.');
    } catch (HttpResponseException $exception) {
        expect($exception->getResponse()->getStatusCode())->toBe(422);
    }

    $email = checkReminderMessage('email', reminderMessageGuards('email-after-body', '3'));
    $state['subject'] = 'Shared title edit';
    app(SaveNoticeMessageDraft::class)->save($this->family->fresh(), [...reminderMessageGuards('save-title', '3'), 'state' => $state], $this->actor);
    $family = $this->family->fresh();
    $draft = $family->draft()->firstOrFail();
    foreach (['email' => $email['preview_receipt'], 'portal' => $portal['preview_receipt']] as $view => $receipt) {
        try {
            app(NoticeMessageOutputReceipt::class)->verify($family, $draft, $this->actor, $view, $receipt);
            test()->fail('A shared title change must invalidate both output proofs.');
        } catch (HttpResponseException $exception) {
            expect($exception->getResponse()->getStatusCode())->toBe(422);
        }
    }
});

test('stale draft guards do not write a version or replace the saved message', function (): void {
    saveReminderMessage();
    $before = $this->family->draft()->firstOrFail()->content_sha256;
    $saveAuditCount = AuditEvent::query()->where('event_type', 'communication_template.message_save_draft')->count();
    $stale = [...reminderMessageGuards('stale-write', '9'), 'state' => reminderMessageState('Not saved')];
    try {
        app(SaveNoticeMessageDraft::class)->save($this->family->fresh(), $stale, $this->actor);
        test()->fail('Expected stale guard conflict.');
    } catch (HttpResponseException $exception) {
        expect($exception->getResponse()->getStatusCode())->toBe(409);
    }
    expect($this->family->draft()->firstOrFail()->content_sha256)->toBe($before)
        ->and($this->family->versions()->count())->toBe(1)
        ->and(AuditEvent::query()->where('event_type', 'communication_template.message_save_draft')->count())->toBe($saveAuditCount);
});

test('private image upload preserves the draft and rejects invalid or foreign image bytes', function (): void {
    $initial = saveReminderMessage();
    $image = UploadedFile::fake()->image('reminder.png', 12, 10);
    $result = app(StoreNoticeMessageImage::class)->store($this->family->fresh(), $image, reminderMessageGuards('image-upload', '1'), $this->actor);
    $asset = CommunicationTemplateDocument::query()->whereKey($result['asset']['id'])->firstOrFail();
    $verified = app(StoreCommunicationTemplateDocument::class)->verified($this->family->fresh(), (string) $asset->id);
    expect($result['asset']['mime'])->toBe('image/png')->and($result['asset']['width'])->toBe(12)->and($result['asset']['height'])->toBe(10)
        ->and(str_starts_with($verified->storage_path, 'communication-templates/'.$this->family->id.'/'))
        ->and(Storage::disk('local')->exists($verified->storage_path))
        ->and(hash('sha256', Storage::disk('local')->get($verified->storage_path)))->toBe($asset->checksum)
        ->and($this->family->draft()->firstOrFail()->subject)->toBe('Exam reminder')
        ->and(app(NoticeMessageState::class)->emailDigest($this->family->fresh(),
            app(NoticeMessageState::class)->persisted($this->family->draft()->firstOrFail())))->toBe($initial['output_digests']['email_sha256']);

    $foreign = CommunicationTemplate::query()->create(['institution_id' => $this->family->institution_id, 'domain' => 'examination',
        'slug' => 'session-published', 'event_key' => 'exam.session.published.v1', 'name' => 'Foreign family']);
    try {
        app(StoreCommunicationTemplateDocument::class)->verified($foreign, (string) $asset->id);
        test()->fail('Expected foreign asset denial.');
    } catch (HttpException $exception) {
        expect($exception->getStatusCode())->toBe(422);
    }

    $badImage = UploadedFile::fake()->create('not-an-image.png', 2, 'image/png');
    try {
        app(StoreNoticeMessageImage::class)->store($this->family->fresh(), $badImage, reminderMessageGuards('invalid-image', '1'), $this->actor);
        test()->fail('Expected invalid image denial.');
    } catch (HttpException $exception) {
        expect($exception->getStatusCode())->toBe(422);
    }
    $current = $this->family->draft()->firstOrFail();
    expect($current->subject)->toBe('Exam reminder')->and(CommunicationTemplateDocument::query()->count())->toBe(1);
    $state = app(NoticeMessageState::class)->persisted($current);
    $state['email_blueprint']['blocks'][] = ['type' => 'image', 'asset_id' => $asset->id, 'alt' => 'Reminder graphic', 'width' => 12, 'height' => 10];
    $attached = app(SaveNoticeMessageDraft::class)->save($this->family->fresh(),
        [...reminderMessageGuards('attach-image', (string) $current->lock_version), 'state' => $state], $this->actor);
    expect($attached['output_digests']['email_sha256'])->not->toBe($initial['output_digests']['email_sha256'])
        ->and($attached['output_digests']['portal_sha256'])->toBe($initial['output_digests']['portal_sha256']);
});
