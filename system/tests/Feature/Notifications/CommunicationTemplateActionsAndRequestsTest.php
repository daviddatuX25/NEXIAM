<?php

use App\Foundation\Actions\RecordAuditEvent;
use App\Http\Requests\Notifications\DiscardCommunicationTemplateDraftRequest;
use App\Http\Requests\Notifications\PreviewCommunicationTemplateRequest;
use App\Http\Requests\Notifications\PublishCommunicationTemplateVersionRequest;
use App\Http\Requests\Notifications\SaveCommunicationTemplateDraftRequest;
use App\Models\Foundation\AuditEvent;
use App\Models\Foundation\Institution;
use App\Models\Notifications\CommunicationTemplate;
use App\Models\Notifications\CommunicationTemplateDocument;
use App\Models\Notifications\CommunicationTemplateDraft;
use App\Models\Notifications\CommunicationTemplateReceipt;
use App\Models\Notifications\CommunicationTemplateVersion;
use App\Models\User;
use App\Notifications\Templates\Actions\DiscardCommunicationTemplateDraft;
use App\Notifications\Templates\Actions\ImportCommunicationTemplateDocument;
use App\Notifications\Templates\Actions\PublishCommunicationTemplateVersion;
use App\Notifications\Templates\Actions\ReviewCommunicationTemplateImport;
use App\Notifications\Templates\Actions\SaveCommunicationTemplateDraft;
use App\Notifications\Templates\Actions\SaveNoticeMessageDraft;
use App\Notifications\Templates\Actions\StoreCommunicationTemplateDocument;
use App\Notifications\Templates\CommunicationTemplatePayload;
use App\Notifications\Templates\CommunicationTemplatePreviewReceipt;
use App\Notifications\Templates\CommunicationTemplateState;
use App\Notifications\Templates\NoticeMessageOutputReceipt;
use App\Notifications\Templates\NoticeMessageState;
use App\Notifications\Templates\Queries\NoticeMessageComposerQuery;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpException;

beforeEach(function (): void {
    seedE2Foundation();
    Storage::fake('local');
    $this->actor = User::query()->where('email', 'registrar.admin@nexiam.test')->firstOrFail();
    $this->template = createTestTemplate();
});

function createTestTemplate(string $domain = 'admissions', string $slug = 'accepted'): CommunicationTemplate
{
    $template = CommunicationTemplate::query()->create(['institution_id' => Institution::firstOrFail()->id,
        'domain' => $domain, 'slug' => $slug, 'event_key' => $domain === 'admissions' ? 'admission.accepted.v1' : 'exam.reminder.v1',
        'name' => 'Canonical Notice', 'active_version' => 1, 'family_revision' => 1]);
    CommunicationTemplateVersion::query()->create(['communication_template_id' => $template->id, 'version' => 1,
        'email_subject' => 'Historical Email', 'email_body' => '<p>Historical email body</p>',
        'in_app_title' => 'Historical Portal', 'in_app_body' => 'Historical portal copy', 'document_mode' => 'none']);

    return $template;
}

function noticeStateFixture(string $subject = 'Saved subject'): array
{
    return ['subject' => $subject, 'body' => 'Saved portal caption', 'action_label' => null, 'action_route' => null,
        'email_blueprint' => ['schema_version' => 'notice-email-1', 'layout' => 'flow', 'blocks' => [['type' => 'paragraph', 'text' => str_repeat('Email content ', 60)]]],
        'document_mode' => 'none', 'document_asset_id' => null, 'builder_json' => null,
        'paper_size' => null, 'orientation' => null, 'document_source_drafts' => null, 'import_provenance' => null];
}

function primeActionAwareMessageSource(CommunicationTemplate $template): void
{
    DB::table('communication_template_versions')->where('communication_template_id', $template->id)->where('version', 1)
        ->update(['content_schema_version' => 'notice-content-3', 'email_renderer_version' => 'notice-message-email-2',
            'subject' => 'Historical Email', 'body' => 'Historical portal copy',
            'email_blueprint' => json_encode(['schema_version' => 'notice-email-1', 'layout' => 'flow',
                'blocks' => [['type' => 'paragraph', 'text' => 'Existing message content.']]], JSON_THROW_ON_ERROR)]);
}
function noticeSaveFixture(CommunicationTemplate $template, string $key = 'save-first', string $subject = 'Saved subject'): array
{
    $template = $template->fresh();
    $draft = $template->draft()->first();

    return ['base_version' => $template->active_version, 'family_revision' => $template->family_revision,
        'lock_version' => $draft?->lock_version, 'idempotency_key' => $key, 'stage' => 3, 'state' => noticeStateFixture($subject)];
}

function communicationTemplateAuditCount(CommunicationTemplate $template, ?string $eventType = null): int
{
    $query = AuditEvent::query();
    if ($eventType === null) {
        $query->where('event_type', 'like', 'communication_template.%');
    } else {
        $query->where('event_type', $eventType);
    }

    return $query->get()->filter(fn (AuditEvent $event): bool => (string) ($event->payload['template_id'] ?? '') === (string) $template->id)
        ->count();
}

function assertNoticeConflict(callable $operation, string $code): void
{
    try {
        $operation();
        test()->fail('Expected a notice conflict.');
    } catch (HttpResponseException $exception) {
        expect($exception->getResponse()->getStatusCode())->toBe(409)
            ->and(json_decode($exception->getResponse()->getContent(), true)['error'])->toBe($code);
    }
}

function noticePublishFixture(CommunicationTemplate $template, User $actor, string $key = 'publish-first'): array
{
    $template = $template->fresh();
    $draft = $template->draft()->firstOrFail();
    $receipts = [];
    foreach (['email', 'portal'] as $view) {
        $receipts[$view] = app(CommunicationTemplatePreviewReceipt::class)->issue($template, $draft, $actor, $view, hash('sha256', 'synthetic-'.$view));
    }

    return ['base_version' => $template->active_version, 'family_revision' => $template->family_revision,
        'lock_version' => $draft->lock_version, 'idempotency_key' => $key,
        'content_sha256' => $draft->content_sha256, 'preview_receipts' => $receipts];
}

test('save persists complete state actual stage and canonical digest without truncating email to caption', function (): void {
    $result = app(SaveCommunicationTemplateDraft::class)->save($this->template, noticeSaveFixture($this->template), $this->actor);
    $draft = $this->template->draft()->firstOrFail();
    expect($result['_status'])->toBe(201)->and($result['draft']['stage'])->toBe(3)
        ->and($result['draft']['lock_version'])->toBe(1)->and($result['draft']['state'])->toBe(noticeStateFixture())
        ->and($result['draft']['email_blueprint_sha256'])->toBe(CommunicationTemplateState::emailDigest($draft->email_blueprint))
        ->and($result['content_sha256'])->toBe($draft->computeContentDigest())
        ->and($this->template->fresh()->family_revision)->toBe(2)
        ->and(CommunicationTemplateReceipt::query()->findOrFail($result['operation_id'])->response_payload)->toBe($result);
    expect(AuditEvent::query()->where('event_type', 'communication_template.draft_saved')->count())->toBe(1);
});

test('notice message drafts pin action-aware renderer and receipts distinguish enabled from disabled canonical actions', function (): void {
    primeActionAwareMessageSource($this->template);
    $save = app(SaveNoticeMessageDraft::class);
    $state = ['subject' => 'Saved subject', 'body' => 'Saved portal caption', 'action_label' => null, 'action_route' => null,
        'email_blueprint' => ['schema_version' => 'notice-email-1', 'layout' => 'flow',
            'blocks' => [['type' => 'paragraph', 'text' => 'Independent Email content.']]],
        'document_revision_id' => null];
    $first = $save->save($this->template, ['base_version' => 1, 'family_revision' => 1, 'lock_version' => null,
        'idempotency_key' => 'notice-disabled-action', 'state' => $state], $this->actor);
    $draft = $this->template->draft()->firstOrFail();
    $messageState = app(NoticeMessageState::class);
    $persisted = $messageState->persisted($draft);
    expect($draft->action_label)->toBeNull()->and($draft->action_route)->toBeNull()
        ->and($draft->email_renderer_version)->toBe('notice-message-email-2')
        ->and($draft->email_blueprint['schema_version'])->toBe('notice-email-1')
        ->and($first['output_digests']['email_sha256'])->toBe($messageState->emailDigest(
            $this->template, $persisted, 'notice-message-email-2'))
        ->and($messageState->emailDigest($this->template, $persisted, 'notice-email-1'))->toBe(CommunicationTemplatePayload::hash([
            'schema' => 'notice-content-3', 'email_schema' => 'notice-email-1', 'renderer' => 'notice-email-1',
            'blueprint' => $persisted['email_blueprint'], 'subject' => $persisted['subject'], 'action_label' => null,
            'action_route' => null, 'assets' => [], 'context' => 'notice-synthetic-context-1']));
    $receipts = app(NoticeMessageOutputReceipt::class);
    $receipt = $receipts->issue($this->template, $draft, $this->actor, 'email');
    $receipts->verify($this->template, $draft, $this->actor, 'email', $receipt, $first['output_digests']['email_sha256']);

    $state['action_label'] = 'Open applicant portal';
    $state['action_route'] = 'applicant.portal';
    $second = $save->save($this->template, ['base_version' => 1, 'family_revision' => $this->template->fresh()->family_revision,
        'lock_version' => $draft->lock_version, 'idempotency_key' => 'notice-enabled-action', 'state' => $state], $this->actor);
    $current = $this->template->draft()->firstOrFail();
    expect($second['draft']['state']['action_label'])->toBe('Open applicant portal')
        ->and($second['draft']['state']['action_route'])->toBe('applicant.portal')
        ->and($second['output_digests']['email_sha256'])->not->toBe($first['output_digests']['email_sha256'])
        ->and($second['output_digests']['portal_sha256'])->not->toBe($first['output_digests']['portal_sha256'])
        ->and($current->email_renderer_version)->toBe('notice-message-email-2');
    expect(fn () => $receipts->verify($this->template, $current, $this->actor, 'email', $receipt))
        ->toThrow(HttpResponseException::class);
});

test('composer digests and receipts follow persisted content3 renderer pins without upgrading historical output', function (): void {
    primeActionAwareMessageSource($this->template);
    DB::table('communication_template_versions')->where('communication_template_id', $this->template->id)->where('version', 1)
        ->update(['email_renderer_version' => 'notice-email-1']);
    $family = $this->template->fresh();
    $state = ['subject' => 'Pinned subject', 'body' => 'Pinned portal body', 'action_label' => null, 'action_route' => null,
        'email_blueprint' => ['schema_version' => 'notice-email-1', 'layout' => 'flow',
            'blocks' => [['type' => 'paragraph', 'text' => 'Pinned historical email.']]],
        'document_revision_id' => null];
    $messageState = app(NoticeMessageState::class);
    $oldContentDigest = $messageState->contentDigest($family, $state, 'notice-email-1');
    $oldDraft = CommunicationTemplateDraft::query()->create([...$state, 'communication_template_id' => $family->id,
        'base_version' => 1, 'lock_version' => 1, 'stage' => 3, 'content_schema_version' => 'notice-content-3',
        'email_renderer_version' => 'notice-email-1', 'document_mode' => 'none', 'email_subject' => '', 'email_body' => '',
        'in_app_title' => '', 'in_app_body' => '', 'content_sha256' => $oldContentDigest, 'updated_by_user_id' => $this->actor->id]);
    $query = app(NoticeMessageComposerQuery::class);
    $oldEditor = $query->editor($this->actor, 'admissions', 'accepted');
    expect($oldEditor['email_renderer_version'])->toBe('notice-email-1')
        ->and($oldEditor['content_schema_version'])->toBe('notice-content-3')
        ->and($oldEditor['content_sha256'])->toBe($oldContentDigest)
        ->and($oldEditor['output_digests']['email_sha256'])->toBe($messageState->emailDigest($family, $state, 'notice-email-1'))
        ->and($oldEditor['output_digests']['portal_sha256'])->toBe($messageState->portalDigest($state, 'notice-email-1'));
    $receipts = app(NoticeMessageOutputReceipt::class);
    $oldReceipt = $receipts->issue($family, $oldDraft, $this->actor, 'email');
    $receipts->verify($family, $oldDraft, $this->actor, 'email', $oldReceipt, $oldEditor['output_digests']['email_sha256']);

    DB::table('communication_template_drafts')->where('id', $oldDraft->id)->delete();
    $family = $this->template->fresh();
    $saved = app(SaveNoticeMessageDraft::class)->save($family, ['base_version' => 1, 'family_revision' => 1,
        'lock_version' => null, 'idempotency_key' => 'new-renderer-pin', 'state' => $state], $this->actor);
    $newDraft = $family->draft()->firstOrFail();
    $newEditor = $query->editor($this->actor, 'admissions', 'accepted');
    expect($saved['content_schema_version'])->toBe('notice-content-3')
        ->and($saved['email_renderer_version'])->toBe('notice-message-email-2')
        ->and($newEditor['email_renderer_version'])->toBe('notice-message-email-2')
        ->and($newEditor['content_sha256'])->toBe($saved['content_sha256'])
        ->and($newEditor['output_digests'])->toBe($saved['output_digests']);
    $newReceipt = $receipts->issue($family, $newDraft, $this->actor, 'email');
    $receipts->verify($family, $newDraft, $this->actor, 'email', $newReceipt, $newEditor['output_digests']['email_sha256']);
});

test('notice message save rejects forged family routes and preserves legacy conversion blockers without mutation', function (): void {
    primeActionAwareMessageSource($this->template);
    $save = app(SaveNoticeMessageDraft::class);
    $state = ['subject' => 'Saved subject', 'body' => 'Saved portal caption', 'action_label' => 'Forged destination',
        'action_route' => 'applicant.schedule', 'email_blueprint' => ['schema_version' => 'notice-email-1', 'layout' => 'flow',
            'blocks' => [['type' => 'paragraph', 'text' => 'Email content.']]], 'document_revision_id' => null];
    $payload = ['base_version' => 1, 'family_revision' => 1, 'lock_version' => null, 'idempotency_key' => 'forged-route', 'state' => $state];
    expect(fn () => $save->save($this->template, $payload, $this->actor))->toThrow(HttpException::class)
        ->and($this->template->draft()->exists())->toBeFalse()
        ->and($this->template->fresh()->family_revision)->toBe(1);

    $tooLong = [...$state, 'action_label' => str_repeat('A', 65), 'action_route' => 'applicant.portal'];
    expect(fn () => $save->save($this->template, [...$payload, 'idempotency_key' => 'oversized-action-label', 'state' => $tooLong], $this->actor))
        ->toThrow(ValidationException::class)
        ->and($this->template->draft()->exists())->toBeFalse()
        ->and($this->template->fresh()->family_revision)->toBe(1);

    actingAsAdmissionSupportStaff($this, $this->actor)->postJson(route('setup.notifications.templates.drafts.store', [
        'domain' => 'admissions', 'slug' => 'accepted',
    ]), ['state' => $tooLong, 'base_version' => 1, 'family_revision' => 1, 'lock_version' => null,
        'idempotency_key' => 'oversized-action-label-http'])
        ->assertUnprocessable()->assertJsonValidationErrors('state.action_label');
    expect($this->template->draft()->exists())->toBeFalse()
        ->and($this->template->fresh()->family_revision)->toBe(1);

    DB::table('communication_template_versions')->where('communication_template_id', $this->template->id)->where('version', 1)
        ->update(['content_schema_version' => 'notice-content-1', 'email_renderer_version' => 'legacy']);
    $source = $this->template->activeVersion();
    $legacyPayload = ['base_version' => 1, 'family_revision' => 1, 'lock_version' => null,
        'idempotency_key' => 'blocked-legacy-title', 'source_version' => $source->version,
        'state' => [...$state, 'action_label' => null, 'action_route' => null]];
    $activePayload = $legacyPayload;
    unset($activePayload['source_version']);
    expect(fn () => $save->save($this->template, [...$activePayload, 'idempotency_key' => 'blocked-active-version'], $this->actor))
        ->toThrow(HttpException::class)
        ->and($this->template->draft()->exists())->toBeFalse()
        ->and($this->template->fresh()->family_revision)->toBe(1);
    expect(fn () => $save->save($this->template, $legacyPayload, $this->actor))->toThrow(HttpException::class)
        ->and($this->template->draft()->exists())->toBeFalse()
        ->and($this->template->fresh()->family_revision)->toBe(1)
        ->and(communicationTemplateAuditCount($this->template))->toBe(0);

    DB::table('communication_template_versions')->where('communication_template_id', $this->template->id)->where('version', 1)
        ->update(['in_app_title' => 'Historical Email', 'action_label' => 'Open applicant portal', 'action_route' => 'applicant.login']);
    $source = $this->template->activeVersion();
    expect(fn () => $save->save($this->template, [...$legacyPayload, 'idempotency_key' => 'blocked-legacy-action',
        'source_version' => $source->version], $this->actor))->toThrow(HttpException::class)
        ->and($this->template->draft()->exists())->toBeFalse()
        ->and($this->template->fresh()->family_revision)->toBe(1)
        ->and(communicationTemplateAuditCount($this->template))->toBe(0);
});

test('exact save replay returns original successful envelope after later saves and after discard without resurrection', function (): void {
    $saver = app(SaveCommunicationTemplateDraft::class);
    $payload = noticeSaveFixture($this->template);
    $first = $saver->save($this->template, $payload, $this->actor);
    $second = $saver->save($this->template, noticeSaveFixture($this->template, 'save-second', 'Later subject'), $this->actor);
    expect($second['_status'])->toBe(200)->and($second['draft']['lock_version'])->toBe(2);
    $before = communicationTemplateAuditCount($this->template);
    expect($saver->save($this->template, $payload, $this->actor))->toBe([...$first, 'replayed' => true]);
    $discard = ['base_version' => 1, 'family_revision' => 3, 'lock_version' => 2, 'idempotency_key' => 'discard'];
    $discarded = app(DiscardCommunicationTemplateDraft::class)->discard($this->template, $discard, $this->actor);
    expect($saver->save($this->template, $payload, $this->actor))->toBe([...$first, 'replayed' => true])
        ->and(app(DiscardCommunicationTemplateDraft::class)->discard($this->template, $discard, $this->actor))->toBe([...$discarded, 'replayed' => true])
        ->and($this->template->draft()->exists())->toBeFalse()
        ->and($this->template->fresh()->family_revision)->toBe(4)
        ->and(communicationTemplateAuditCount($this->template))->toBe($before + 1);
    assertNoticeConflict(fn () => $saver->save($this->template, [...$payload, 'idempotency_key' => 'late-autosave'], $this->actor), 'template_conflict');
});

test('publish pins immutable complete state exact publication replay and original save receipt after deletion', function (): void {
    $saver = app(SaveCommunicationTemplateDraft::class);
    $savePayload = noticeSaveFixture($this->template);
    $saved = $saver->save($this->template, $savePayload, $this->actor);
    $old = $this->template->activeVersion()->getAttributes();
    $publishPayload = noticePublishFixture($this->template, $this->actor);
    $published = app(PublishCommunicationTemplateVersion::class)->publish($this->template, $publishPayload, $this->actor);
    expect($published['version']['version'])->toBe(2)->and($published['active_version'])->toBe(2)
        ->and($published['family_revision'])->toBe(3)->and($published['draft'])->toBeNull()
        ->and($this->template->versions()->where('version', 1)->firstOrFail()->getAttributes())->toBe($old);
    $later = $saver->save($this->template, noticeSaveFixture($this->template, 'new-draft', 'New publication'), $this->actor);
    $next = app(PublishCommunicationTemplateVersion::class)->publish($this->template, noticePublishFixture($this->template, $this->actor, 'publish-next'), $this->actor);
    $audits = communicationTemplateAuditCount($this->template);
    expect($next['active_version'])->toBe(3)
        ->and(app(PublishCommunicationTemplateVersion::class)->publish($this->template, $publishPayload, $this->actor))->toBe([...$published, 'replayed' => true])
        ->and($saver->save($this->template, $savePayload, $this->actor))->toBe([...$saved, 'replayed' => true])
        ->and(communicationTemplateAuditCount($this->template))->toBe($audits)->and($this->template->versions()->count())->toBe(3);
    $version = $this->template->versions()->where('version', 2)->firstOrFail();
    expect(fn () => $version->update(['subject' => 'Overwrite']))->toThrow(LogicException::class)
        ->and(fn () => $version->delete())->toThrow(LogicException::class);
});

test('payload changing same key stage source choice action or nested content is rejected without mutation', function (): void {
    $payload = noticeSaveFixture($this->template);
    app(SaveCommunicationTemplateDraft::class)->save($this->template, $payload, $this->actor);
    $before = [$this->template->fresh()->family_revision, $this->template->draft()->firstOrFail()->getAttributes(), communicationTemplateAuditCount($this->template)];
    foreach ([['stage' => 2], ['state' => noticeStateFixture('Changed')], ['family_revision' => 2]] as $change) {
        assertNoticeConflict(fn () => app(SaveCommunicationTemplateDraft::class)->save($this->template, [...$payload, ...$change], $this->actor), 'idempotency_payload_conflict');
    }
    expect([$this->template->fresh()->family_revision, $this->template->draft()->firstOrFail()->getAttributes(), communicationTemplateAuditCount($this->template)])->toBe($before);
});

test('same active version competing writes stale publish and stale discard do not mutate or audit', function (): void {
    $saver = app(SaveCommunicationTemplateDraft::class);
    $saver->save($this->template, noticeSaveFixture($this->template), $this->actor);
    $competing = noticeSaveFixture($this->template, 'other-editor');
    $stalePublish = noticePublishFixture($this->template, $this->actor);
    $saver->save($this->template, noticeSaveFixture($this->template, 'winner', 'Winner'), $this->actor);
    $before = [$this->template->fresh()->family_revision, communicationTemplateAuditCount($this->template), CommunicationTemplateReceipt::query()->where('communication_template_id', $this->template->id)->count()];
    assertNoticeConflict(fn () => $saver->save($this->template, $competing, $this->actor), 'template_conflict');
    assertNoticeConflict(fn () => app(PublishCommunicationTemplateVersion::class)->publish($this->template, $stalePublish, $this->actor), 'template_conflict');
    assertNoticeConflict(fn () => app(DiscardCommunicationTemplateDraft::class)->discard($this->template, [...$competing, 'idempotency_key' => 'stale-discard'], $this->actor), 'template_conflict');
    $badLock = noticeSaveFixture($this->template, 'bad-lock');
    $badLock['lock_version'] = 1;
    assertNoticeConflict(fn () => $saver->save($this->template, $badLock, $this->actor), 'draft_conflict');
    expect($this->template->draft()->firstOrFail()->subject)->toBe('Winner')
        ->and([$this->template->fresh()->family_revision, communicationTemplateAuditCount($this->template), CommunicationTemplateReceipt::query()->where('communication_template_id', $this->template->id)->count()])->toBe($before);
});

test('mandatory guards and full state have no Action bypass or unlocked legacy field merging', function (): void {
    $payload = noticeSaveFixture($this->template);
    foreach (['base_version', 'family_revision', 'lock_version', 'idempotency_key', 'stage', 'state'] as $field) {
        $missing = $payload;
        unset($missing[$field]);
        expect(fn () => app(SaveCommunicationTemplateDraft::class)->save($this->template, $missing, $this->actor))->toThrow(ValidationException::class);
    }
    $partial = $payload;
    unset($partial['state']['subject']);
    expect(fn () => app(SaveCommunicationTemplateDraft::class)->save($this->template, $partial, $this->actor))->toThrow(ValidationException::class)
        ->and($this->template->draft()->exists())->toBeFalse()->and($this->template->fresh()->family_revision)->toBe(1);
});

test('fresh foreign scope or unauthorized principal cannot obtain even an existing success receipt', function (): void {
    $payload = noticeSaveFixture($this->template);
    app(SaveCommunicationTemplateDraft::class)->save($this->template, $payload, $this->actor);
    $unauthorized = User::query()->where('email', 'guidance.counselor@nexiam.test')->firstOrFail();
    expect(fn () => app(SaveCommunicationTemplateDraft::class)->save($this->template, $payload, $unauthorized))->toThrow(HttpException::class);
    $foreign = Institution::query()->create(['name' => 'Foreign institution', 'code' => 'FOREIGN']);
    DB::table('communication_templates')->where('id', $this->template->id)->update(['institution_id' => $foreign->id]);
    expect(fn () => app(SaveCommunicationTemplateDraft::class)->save($this->template, $payload, $this->actor))->toThrow(HttpException::class);
});

test('canonical payload and content digest ignore recursive key ordering but include actions and list order', function (): void {
    $state = noticeStateFixture();
    $reordered = array_reverse($state, true);
    $reordered['email_blueprint'] = array_reverse($state['email_blueprint'], true);
    expect(CommunicationTemplatePayload::hash($state))->toBe(CommunicationTemplatePayload::hash($reordered));
    $payload = noticeSaveFixture($this->template);
    $saved = app(SaveCommunicationTemplateDraft::class)->save($this->template, $payload, $this->actor);
    expect(app(SaveCommunicationTemplateDraft::class)->save($this->template, [...$payload, 'state' => $reordered], $this->actor))->toBe([...$saved, 'replayed' => true]);
    $state['action_label'] = 'Changed action';
    expect(app(CommunicationTemplateState::class)->digest($this->template, $state))->not->toBe($saved['content_sha256']);
});

test('audit failure rolls back family draft and receipt atomically', function (): void {
    $this->mock(RecordAuditEvent::class, fn ($mock) => $mock->shouldReceive('handle')->once()->andThrow(new RuntimeException('Audit unavailable')));
    expect(fn () => app(SaveCommunicationTemplateDraft::class)->save($this->template, noticeSaveFixture($this->template), $this->actor))->toThrow(RuntimeException::class, 'Audit unavailable')
        ->and($this->template->fresh()->family_revision)->toBe(1)->and($this->template->draft()->exists())->toBeFalse()
        ->and(CommunicationTemplateReceipt::query()->count())->toBe(0);
});

test('unreviewed malformed provenance and stale previews cannot publish or create a success audit', function (): void {
    $saved = app(SaveCommunicationTemplateDraft::class)->save($this->template, noticeSaveFixture($this->template), $this->actor);
    $payload = noticePublishFixture($this->template, $this->actor);
    $payload['preview_receipts'] = [];
    expect(fn () => app(PublishCommunicationTemplateVersion::class)->publish($this->template, $payload, $this->actor))->toThrow(ValidationException::class);
    $payload = noticePublishFixture($this->template, $this->actor);
    $draft = $this->template->draft()->firstOrFail();
    DB::table('communication_template_drafts')->where('id', $draft->id)->update(['stage' => 1]);
    expect(fn () => app(PublishCommunicationTemplateVersion::class)->publish($this->template, $payload, $this->actor))->toThrow(HttpException::class);
    expect($this->template->versions()->count())->toBe(1)->and($this->template->fresh()->active_version)->toBe(1)
        ->and(AuditEvent::query()->where('event_type', 'communication_template.version_published')->count())->toBe(0);
});

test('caller provenance images foreign ownership mixed print and ineligible PDF are rejected before association', function (): void {
    $state = noticeStateFixture();
    $state['import_provenance'] = ['actor_id' => $this->actor->id, 'review_acknowledgment' => ['valid' => true]];
    expect(fn () => app(SaveCommunicationTemplateDraft::class)->save($this->template, [...noticeSaveFixture($this->template), 'state' => $state], $this->actor))->toThrow(HttpException::class);
    $state = noticeStateFixture();
    $state['document_asset_id'] = (string) Str::uuid();
    expect(fn () => app(SaveCommunicationTemplateDraft::class)->save($this->template, [...noticeSaveFixture($this->template), 'state' => $state], $this->actor))->toThrow(HttpException::class);
    $reminder = createTestTemplate('examination', 'reminder');
    $counselor = User::query()->where('email', 'guidance.counselor@nexiam.test')->firstOrFail();
    $state = noticeStateFixture();
    $state['document_mode'] = 'docx';
    $state['document_asset_id'] = (string) Str::uuid();
    expect(fn () => app(SaveCommunicationTemplateDraft::class)->save($reminder, [...noticeSaveFixture($reminder), 'state' => $state], $counselor))->toThrow(HttpException::class);
    expect($this->template->draft()->exists())->toBeFalse()->and($reminder->draft()->exists())->toBeFalse();
});

test('asset pruning retains active draft version provenance receipts and frozen payloads while excluding finding hashes and deleting orphans', function (): void {
    $documents = [];
    foreach (['inactive', 'provenance', 'inline', 'receipt', 'frozen', 'version', 'orphan'] as $name) {
        $bytes = 'bytes-'.$name;
        $documents[$name] = CommunicationTemplateDocument::query()->create(['communication_template_id' => $this->template->id,
            'institution_id' => $this->template->institution_id, 'original_name' => $name.'.json',
            'storage_path' => 'communication-templates/'.$this->template->id.'/'.$name.'.json', 'checksum' => hash('sha256', $bytes),
            'mime_type' => 'application/json', 'file_size_bytes' => strlen($bytes)]);
        Storage::disk('local')->put($documents[$name]->storage_path, $bytes);
    }
    CommunicationTemplateDraft::query()->create(['communication_template_id' => $this->template->id, 'base_version' => 1,
        'email_subject' => '', 'email_body' => '', 'in_app_title' => '', 'in_app_body' => '', 'document_mode' => 'none',
        'document_source_drafts' => ['docx' => ['document_asset_id' => $documents['inactive']->id]],
        'import_provenance' => ['source_asset_id' => $documents['provenance']->id,
            'findings' => [['id' => hash('sha256', 'deterministic finding identity, not a document UUID')]]],
        'email_blueprint' => ['blocks' => [['type' => 'image', 'asset_id' => $documents['inline']->id]]]]);
    CommunicationTemplateVersion::query()->create(['communication_template_id' => $this->template->id, 'version' => 2,
        'email_subject' => 'Published Email', 'email_body' => '<p>Published email body</p>',
        'in_app_title' => 'Published Portal', 'in_app_body' => 'Published portal copy', 'document_mode' => 'docx',
        'document_asset_id' => $documents['version']->id,
        'import_provenance' => ['source_asset_id' => $documents['version']->id]]);
    CommunicationTemplateReceipt::query()->create(['institution_id' => $this->template->institution_id, 'domain' => $this->template->domain,
        'canonical_slug' => $this->template->slug, 'operation' => 'imported', 'idempotency_key' => 'retain-receipt',
        'payload_sha256' => str_repeat('a', 64), 'response_payload' => ['draft' => ['state' => ['document_asset_id' => $documents['receipt']->id]]]]);
    DB::table('notification_deliveries')->insert(['id' => (string) Str::uuid(), 'event_id' => 'retention-proof', 'event_type' => 'exam.reminder.v1',
        'recipient_type' => 'user', 'recipient_id' => $this->actor->id, 'channel' => 'mail', 'template_key' => 'exam.reminder',
        'payload' => json_encode(['email' => ['assets' => [['id' => $documents['frozen']->id]]]], JSON_THROW_ON_ERROR)]);
    app(StoreCommunicationTemplateDocument::class)->deletePending([hash('sha256', 'finding hash must not be resolved as a document identifier')]);

    expect(app(StoreCommunicationTemplateDocument::class)->pruneUnreferenced($this->template))->toBe(1);
    foreach (array_diff(array_keys($documents), ['orphan']) as $name) {
        expect($documents[$name]->fresh())->not->toBeNull()->and(Storage::disk('local')->exists($documents[$name]->storage_path))->toBeTrue();
    }
    expect($documents['orphan']->fresh())->toBeNull();
});

test('current notice requests require complete revision guards and no generated keys', function (): void {
    foreach ([SaveCommunicationTemplateDraftRequest::class, PublishCommunicationTemplateVersionRequest::class,
        DiscardCommunicationTemplateDraftRequest::class, PreviewCommunicationTemplateRequest::class] as $class) {
        $request = new $class;
        $validation = Validator::make([], $request->rules());
        expect($validation->fails())->toBeTrue()->and($validation->errors()->has('base_version'))->toBeTrue()
            ->and($validation->errors()->has('family_revision'))->toBeTrue()->and($validation->errors()->has('lock_version'))->toBeTrue();
        if ($class !== PreviewCommunicationTemplateRequest::class) {
            expect($validation->errors()->has('idempotency_key'))->toBeTrue();
        }
    }
    $request = new SaveCommunicationTemplateDraftRequest;
    expect(Validator::make(noticeSaveFixture($this->template), $request->rules())->passes())->toBeTrue();
    $state = noticeStateFixture();
    $state['email_subject'] = 'Competing legacy subject';
    expect(Validator::make([...noticeSaveFixture($this->template), 'state' => $state], $request->rules())->fails())->toBeTrue();
});

test('historical begin from version preserves original channel bytes as incomplete read-only comparison', function (): void {
    DB::table('communication_template_versions')->where('communication_template_id', $this->template->id)->where('version', 1)
        ->update(['action_route' => 'applicant.schedule', 'paper_size' => 'a4', 'orientation' => 'portrait']);
    $original = $this->template->activeVersion()->getAttributes();
    $payload = [...noticeSaveFixture($this->template), 'source_version' => 1,
        'state' => CommunicationTemplateState::draftStateFromVersion($this->template->activeVersion())];
    $saved = app(SaveCommunicationTemplateDraft::class)->save($this->template, $payload, $this->actor);
    expect($saved['draft']['state']['subject'])->toBe('Historical Email')
        ->and($saved['draft']['state']['body'])->toBe('Historical portal copy')
        ->and($saved['draft']['state']['email_blueprint'])->toBeNull()
        ->and($saved['draft']['state']['import_provenance'])->toBeNull()
        ->and($saved['draft']['state']['action_route'])->toBe('applicant.portal')
        ->and($saved['draft']['state']['paper_size'])->toBeNull()
        ->and($saved['draft']['state']['orientation'])->toBeNull()
        ->and($saved['draft']['diagnostics'][0]['code'])->toBe('needs_semantic_authoring')
        ->and($saved['draft']['historical_content']['email_body'])->toBe('<p>Historical email body</p>')
        ->and($this->template->activeVersion()->getAttributes())->toBe($original)
        ->and($this->template->fresh()->active_version)->toBe(1);
    $next = noticeSaveFixture($this->template, 'author-actual-semantic-email');
    $authored = app(SaveCommunicationTemplateDraft::class)->save($this->template, $next, $this->actor);
    expect($authored['draft']['diagnostics'])->toBe([])
        ->and($authored['draft']['historical_content'])->toBe($saved['draft']['historical_content'])
        ->and($this->template->activeVersion()->getAttributes())->toBe($original);
});

test('old successful preview receipt cannot certify newer content even with current generation guards', function (): void {
    app(SaveCommunicationTemplateDraft::class)->save($this->template, noticeSaveFixture($this->template), $this->actor);
    $oldCheck = noticePublishFixture($this->template, $this->actor);
    app(SaveCommunicationTemplateDraft::class)->save($this->template, noticeSaveFixture($this->template, 'changed-email', 'Changed'), $this->actor);
    $current = noticePublishFixture($this->template, $this->actor);
    $current['preview_receipts'] = $oldCheck['preview_receipts'];
    try {
        app(PublishCommunicationTemplateVersion::class)->publish($this->template, $current, $this->actor);
        $this->fail('A stale preview must not publish.');
    } catch (HttpResponseException $exception) {
        expect($exception->getResponse()->getStatusCode())->toBe(422);
    }
    expect($this->template->versions()->count())->toBe(1)->and($this->template->draft()->exists())->toBeTrue()
        ->and($this->template->fresh()->active_version)->toBe(1)
        ->and(communicationTemplateAuditCount($this->template, 'communication_template.version_published'))->toBe(0);
});

test('source checksum metadata is bound into operation payload and content fingerprint rather than opaque ID alone', function (): void {
    $bytes = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNk+A8AAQUBAScY42YAAAAASUVORK5CYII=', true);
    $asset = app(StoreCommunicationTemplateDocument::class)->storeImageBytes($this->template, $bytes, 'image/png', $this->actor);
    expect(Storage::disk('local')->getVisibility($asset->storage_path))->toBe('private');
    $payload = noticeSaveFixture($this->template);
    $payload['state']['email_blueprint']['blocks'][] = ['type' => 'image', 'asset_id' => $asset->id, 'width' => 100, 'alt' => 'Owned pixel'];
    $saved = app(SaveCommunicationTemplateDraft::class)->save($this->template, $payload, $this->actor);
    $before = communicationTemplateAuditCount($this->template);
    DB::table('communication_template_documents')->where('id', $asset->id)->update(['checksum' => hash('sha256', $bytes.'modified')]);
    assertNoticeConflict(fn () => app(SaveCommunicationTemplateDraft::class)->save($this->template, $payload, $this->actor), 'idempotency_payload_conflict');
    expect(app(CommunicationTemplateState::class)->digest($this->template, $this->template->draft()->firstOrFail()))->not->toBe($saved['content_sha256'])
        ->and(communicationTemplateAuditCount($this->template))->toBe($before)->and($this->template->fresh()->family_revision)->toBe(2);
});

test('a loaded principal whose staff office changes is freshly denied before successful receipt replay', function (): void {
    $payload = noticeSaveFixture($this->template);
    app(SaveCommunicationTemplateDraft::class)->save($this->template, $payload, $this->actor);
    $this->actor->load('staffProfile', 'roles', 'permissions');
    $before = [CommunicationTemplateReceipt::query()->where('communication_template_id', $this->template->id)->count(), communicationTemplateAuditCount($this->template), $this->template->fresh()->family_revision];
    DB::table('staff_profiles')->where('user_id', $this->actor->id)->update(['office' => 'guidance']);
    expect(fn () => app(SaveCommunicationTemplateDraft::class)->save($this->template, $payload, $this->actor))->toThrow(HttpException::class);
    expect([CommunicationTemplateReceipt::query()->where('communication_template_id', $this->template->id)->count(), communicationTemplateAuditCount($this->template), $this->template->fresh()->family_revision])->toBe($before);
});

test('null lock is create only and an update cannot recreate a deleted draft at the same active version', function (): void {
    $first = app(SaveCommunicationTemplateDraft::class)->save($this->template, noticeSaveFixture($this->template), $this->actor);
    $wrongCreate = noticeSaveFixture($this->template, 'second-create');
    $wrongCreate['lock_version'] = null;
    assertNoticeConflict(fn () => app(SaveCommunicationTemplateDraft::class)->save($this->template, $wrongCreate, $this->actor), 'draft_conflict');
    $lateUpdate = noticeSaveFixture($this->template, 'late-update');
    app(DiscardCommunicationTemplateDraft::class)->discard($this->template, ['base_version' => 1,
        'family_revision' => 2, 'lock_version' => 1, 'idempotency_key' => 'discard-create-distinction'], $this->actor);
    $lateUpdate['family_revision'] = $this->template->fresh()->family_revision;
    assertNoticeConflict(fn () => app(SaveCommunicationTemplateDraft::class)->save($this->template, $lateUpdate, $this->actor), 'draft_conflict');
    expect($this->template->draft()->exists())->toBeFalse()->and($this->template->fresh()->active_version)->toBe(1)
        ->and(CommunicationTemplateReceipt::query()->where('operation', 'save_draft')->count())->toBe(1);
});

test('canonical family return URLs use registered domain family routes and action bindings cannot target arbitrary registered endpoints', function (): void {
    $state = noticeStateFixture();
    foreach (['applicant.application', 'admissions.index', 'setup.notifications.templates.publish'] as $binding) {
        $state['action_route'] = $binding;
        expect(fn () => app(SaveCommunicationTemplateDraft::class)->save($this->template,
            [...noticeSaveFixture($this->template, 'rejected-'.$binding), 'state' => $state], $this->actor))->toThrow(HttpException::class);
    }
    $state['action_route'] = 'applicant.portal';
    app(SaveCommunicationTemplateDraft::class)->save($this->template, [...noticeSaveFixture($this->template), 'state' => $state], $this->actor);
    $published = app(PublishCommunicationTemplateVersion::class)->publish($this->template, noticePublishFixture($this->template, $this->actor), $this->actor);
    $expected = route('setup.notifications.admissions.templates.studio', ['slug' => $this->template->slug]);
    expect($published['return_url'])->toBe($expected);
    $created = app(SaveCommunicationTemplateDraft::class)->save($this->template, noticeSaveFixture($this->template, 'next-draft'), $this->actor);
    $discarded = app(DiscardCommunicationTemplateDraft::class)->discard($this->template, ['base_version' => 2,
        'family_revision' => $created['family_revision'], 'lock_version' => 1, 'idempotency_key' => 'discard-return-url'], $this->actor);
    expect($discarded['return_url'])->toBe($expected);
});

test('source version first email edit preserves authored full state invalidates review and rejects forged provenance', function (): void {
    $upload = UploadedFile::fake()->create('trusted-copy.docx')->mimeType('application/vnd.openxmlformats-officedocument.wordprocessingml.document');
    $archive = new ZipArchive;
    expect($archive->open($upload->getPathname(), ZipArchive::CREATE | ZipArchive::OVERWRITE))->toBeTrue();
    $archive->addFromString('[Content_Types].xml', '<?xml version="1.0"?><Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types"><Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/><Default Extension="xml" ContentType="application/xml"/><Override PartName="/word/document.xml" ContentType="application/vnd.openxmlformats-officedocument.wordprocessingml.document.main+xml"/></Types>');
    $archive->addFromString('_rels/.rels', '<?xml version="1.0"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="word/document.xml"/></Relationships>');
    $archive->addFromString('word/document.xml', '<?xml version="1.0"?><w:document xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main"><w:body><w:p><w:r><w:t>Imported notice for {{applicant_name}}</w:t></w:r></w:p></w:body></w:document>');
    $archive->close();
    $import = app(ImportCommunicationTemplateDocument::class)->handle($this->template, $upload, $this->actor, false,
        ['base_version' => 1, 'family_revision' => 1, 'lock_version' => null, 'idempotency_key' => 'copy-import']);
    foreach ([$import['import']['source_asset_id'], $import['import']['report_id']] as $id) {
        $stored = CommunicationTemplateDocument::query()->findOrFail($id);
        expect(Storage::disk('local')->getVisibility($stored->storage_path))->toBe('private');
    }
    $review = app(ReviewCommunicationTemplateImport::class)->handle($this->template, $this->actor, [
        'base_version' => 1, 'family_revision' => $import['family_revision'], 'lock_version' => $import['draft']['lock_version'],
        'idempotency_key' => 'copy-review', 'report_id' => $import['import']['report_id'],
        'report_sha256' => $import['import']['report_sha256'], 'email_blueprint_sha256' => $import['draft']['email_blueprint_sha256'],
        'resolutions' => [],
    ]);
    app(SaveCommunicationTemplateDraft::class)->save($this->template, [
        'base_version' => 1, 'family_revision' => $review['family_revision'], 'lock_version' => $review['draft']['lock_version'],
        'idempotency_key' => 'copy-check-stage', 'stage' => 3, 'state' => $review['draft']['state'],
    ], $this->actor);
    app(PublishCommunicationTemplateVersion::class)->publish($this->template, noticePublishFixture($this->template, $this->actor), $this->actor);
    $source = $this->template->fresh()->activeVersion();
    $state = CommunicationTemplateState::draftStateFromVersion($source);
    $state['subject'] = 'First edited subject';
    $state['body'] = 'First edited caption';
    $state['email_blueprint']['blocks'][] = ['type' => 'paragraph', 'text' => 'First authored additional email block.'];
    $payload = [...noticeSaveFixture($this->template, 'edited-source-create'), 'source_version' => $source->version, 'state' => $state];
    $forged = $payload;
    $forged['idempotency_key'] = 'forged-source-ack';
    $forged['state']['import_provenance']['review_acknowledgment']['actor_id'] = '999999';
    $before = [communicationTemplateAuditCount($this->template), $this->template->fresh()->family_revision];
    expect(fn () => app(SaveCommunicationTemplateDraft::class)->save($this->template, $forged, $this->actor))->toThrow(HttpException::class);
    expect($this->template->draft()->exists())->toBeFalse()
        ->and([communicationTemplateAuditCount($this->template), $this->template->fresh()->family_revision])->toBe($before);
    $created = app(SaveCommunicationTemplateDraft::class)->save($this->template, $payload, $this->actor);
    expect($created['draft']['state']['subject'])->toBe('First edited subject')
        ->and($created['draft']['state']['body'])->toBe('First edited caption')
        ->and($created['draft']['state']['email_blueprint'])->toBe($state['email_blueprint'])
        ->and($created['draft']['state']['import_provenance']['source_sha256'])->toBe($source->import_provenance['source_sha256'])
        ->and($created['draft']['state']['import_provenance']['report_sha256'])->toBe($source->import_provenance['report_sha256'])
        ->and($created['draft']['state']['import_provenance']['review_acknowledgment'])->toBeNull()
        ->and($source->fresh()->import_provenance['review_acknowledgment'])->not->toBeNull();
});

test('historical message copy validates and persists only through the authenticated draft HTTP contract', function (): void {
    $staff = User::query()->where('email', 'guidance.counselor@nexiam.test')->firstOrFail();
    $family = createTestTemplate('examination', 'reminder');
    $blueprint = ['schema_version' => 'notice-email-1', 'layout' => 'flow',
        'blocks' => [['type' => 'paragraph', 'text' => 'Historical message for {{applicant_name}}.']]];
    DB::table('communication_template_versions')->where('communication_template_id', $family->id)->where('version', 1)
        ->update(['content_schema_version' => 'notice-content-3', 'subject' => 'Current reminder',
            'body' => 'Current portal message.', 'email_blueprint' => json_encode($blueprint, JSON_THROW_ON_ERROR)]);
    CommunicationTemplateVersion::query()->create([
        'communication_template_id' => $family->id, 'version' => 2, 'content_schema_version' => 'notice-content-1',
        'subject' => 'Legacy title', 'body' => 'Legacy portal copy.', 'action_label' => null, 'action_route' => null,
        'email_subject' => 'Different legacy email title',
        'email_body' => '<p>Preserve this text.</p><video src="https://media.example.edu/notice.mp4">Video</video>',
        'in_app_title' => 'Legacy portal title', 'in_app_body' => 'Legacy portal copy.',
        'email_blueprint' => null, 'email_renderer_version' => 'legacy', 'document_mode' => 'none',
    ]);
    $family->update(['active_version' => 2]);
    $foreign = $this->template;
    CommunicationTemplateVersion::query()->create([
        'communication_template_id' => $foreign->id, 'version' => 3, 'email_subject' => 'Foreign version',
        'email_body' => '<p>Other family.</p>', 'in_app_title' => 'Other family', 'in_app_body' => 'Other family',
        'document_mode' => 'none',
    ]);

    $url = route('setup.notifications.templates.drafts.store', ['domain' => 'examination', 'slug' => 'reminder']);
    $payload = [
        'base_version' => 2, 'family_revision' => 1, 'lock_version' => null, 'idempotency_key' => 'http-history-copy',
        'source_version' => 1,
        'state' => ['subject' => 'Edited historical subject', 'body' => 'Edited historical portal copy.',
            'action_label' => null, 'action_route' => null, 'email_blueprint' => $blueprint, 'document_revision_id' => null],
    ];
    $receiptCount = fn (): int => CommunicationTemplateReceipt::query()->where('institution_id', $family->institution_id)
        ->where('domain', $family->domain)->where('canonical_slug', $family->slug)->where('operation', 'message_save_draft')->count();
    $snapshot = fn (): array => [
        $family->fresh()->family_revision, $family->fresh()->active_version, $family->draft()->first()?->getAttributes(),
        $receiptCount(), communicationTemplateAuditCount($family),
    ];
    $familyAuditCountBefore = communicationTemplateAuditCount($family);
    $request = actingAsAdmissionSupportStaff($this, $staff);

    $blockedSource = [...$payload, 'idempotency_key' => 'http-blocked-history-source', 'source_version' => 2];
    $request->postJson($url, $blockedSource)->assertUnprocessable();
    foreach ([0, -1, null, 'not-a-version', ['version' => 1]] as $invalidSource) {
        $invalid = [...$payload, 'idempotency_key' => 'http-invalid-source-'.(is_array($invalidSource) ? 'array' : (string) $invalidSource),
            'source_version' => $invalidSource];
        $request->postJson($url, $invalid)->assertUnprocessable()->assertJsonValidationErrors('source_version');
    }
    $request->postJson($url, [...$payload, 'idempotency_key' => 'http-foreign-source', 'source_version' => 3])->assertNotFound();
    expect($snapshot())->toBe([1, 2, null, 0, $familyAuditCountBefore]);

    $registrar = User::query()->where('email', 'registrar.clerk@nexiam.test')->firstOrFail();
    actingAsAdmissionSupportStaff($this, $registrar)->postJson($url, $payload)->assertForbidden();
    expect($snapshot())->toBe([1, 2, null, 0, $familyAuditCountBefore]);
    $request = actingAsAdmissionSupportStaff($this, $staff);

    $created = $request->postJson($url, $payload)->assertCreated()->assertJsonPath('replayed', false)->json();
    expect($created['draft']['state'])->toBe($payload['state'])
        ->and($created['draft']['id'])->not->toBeEmpty()
        ->and($created['operation_id'])->not->toBeEmpty();
    $stateAfterCreate = $snapshot();
    expect($stateAfterCreate)->toBe([2, 2, $family->draft()->firstOrFail()->getAttributes(), 1, $familyAuditCountBefore + 1]);

    $replay = $request->postJson($url, $payload)->assertCreated()->assertJsonPath('replayed', true)->json();
    expect($replay['operation_id'])->toBe($created['operation_id'])
        ->and($replay['draft']['id'])->toBe($created['draft']['id'])
        ->and($snapshot())->toBe($stateAfterCreate);

    $changedSource = $payload;
    $changedSource['source_version'] = 2;
    $request->postJson($url, $changedSource)->assertConflict();
    $changedState = $payload;
    $changedState['state']['subject'] = 'Different payload';
    $request->postJson($url, $changedState)->assertConflict();
    $request->postJson($url, [...$payload, 'source_version' => 1, 'idempotency_key' => 'http-existing-draft-source'])->assertConflict();
    expect($snapshot())->toBe($stateAfterCreate);
});
