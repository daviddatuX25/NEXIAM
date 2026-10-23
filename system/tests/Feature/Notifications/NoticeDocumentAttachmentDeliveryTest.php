<?php

declare(strict_types=1);

use App\Models\Admission\ApplicantIdentity;
use App\Models\Examination\ExamAssignment;
use App\Models\Examination\ExamSession;
use App\Models\Foundation\AcademicCycle;
use App\Models\Foundation\Institution;
use App\Models\Foundation\Room;
use App\Models\Notifications\CommunicationTemplate;
use App\Models\Notifications\CommunicationTemplateVersion;
use App\Models\Notifications\NoticeDocumentDraft;
use App\Models\Notifications\NoticeDocumentVersion;
use App\Models\Notifications\NotificationDeliveryAttemptArtifact;
use App\Models\User;
use App\Notifications\Mail\NotificationMailable;
use App\Notifications\NotificationDelivery;
use App\Notifications\NotificationIntent;
use App\Notifications\NotificationIntentDispatcher;
use App\Notifications\Policies\ExamNotificationPolicy;
use App\Notifications\Services\NoticeDocumentAttachmentCompiler;
use App\Notifications\Templates\Actions\NoticeDocumentWorkflow;
use App\Notifications\Templates\Actions\PublishNoticeMessageVersion;
use App\Notifications\Templates\Actions\SaveNoticeMessageDraft;
use App\Notifications\Templates\NoticeMessageOutputReceipt;
use App\Notifications\Templates\NoticeMessageState;
use App\Notifications\Templates\Surfaces\NoticeTemplateSurface;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Symfony\Component\HttpKernel\Exception\HttpException;

beforeEach(function (): void {
    seedE2Foundation();
    Storage::fake('local');
    $this->institution = Institution::firstOrFail();
    $this->actor = User::query()->where('email', 'guidance.counselor@nexiam.test')->firstOrFail();

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

    $this->initialMessageVersion = $this->family->versions()->create([
        'version' => 1,
        'content_schema_version' => 'notice-content-3',
        'subject' => 'Examination Permit for {{applicant_name}}',
        'body' => 'Your examination session has been published.',
        'email_subject' => 'Initial email subject',
        'email_body' => 'Initial body',
        'in_app_title' => 'Initial in app title',
        'in_app_body' => 'Initial in app body',
        'email_blueprint' => [
            'schema_version' => 'notice-email-1',
            'layout' => 'flow',
            'blocks' => [
                ['type' => 'paragraph', 'text' => 'Hello {{applicant_name}}, your permit is attached.'],
            ],
        ],
        'email_renderer_version' => 'notice-email-1',
        'document_mode' => 'none',
        'content_sha256' => hash('sha256', 'initial-version-digest'),
        'created_by_user_id' => $this->actor->id,
    ]);

    $this->otherFamily = CommunicationTemplate::query()->create([
        'institution_id' => $this->institution->id,
        'domain' => 'admissions',
        'slug' => 'accepted',
        'event_key' => 'admission.accepted.v1',
        'name' => 'Admissions accepted offer',
        'active_version' => 1,
        'family_revision' => 1,
        'document_revision' => 1,
    ]);
});

function createReadyDocumentVersion(CommunicationTemplate $family, User $actor, string $title = 'Permit Document'): NoticeDocumentVersion
{
    $surface = new NoticeTemplateSurface($family->domain, $family->slug);
    $blueprint = $surface->defaultBlueprint();
    $blueprint['components'] = [
        ['type' => 'header', 'props' => ['title' => $title, 'subtitle' => 'Official Schedule']],
        ['type' => 'field', 'props' => ['label' => 'App No', 'binding' => 'application_no']],
    ];

    $workflow = app(NoticeDocumentWorkflow::class);
    $baseVersion = (int) ($family->documentVersions()->max('version') ?? 0);
    $savedDraft = $workflow->save($family, [
        'base_version' => $baseVersion,
        'document_revision' => $family->document_revision,
        'lock_version' => null,
        'idempotency_key' => 'doc-draft-'.uniqid(),
        'state' => [
            'source_kind' => 'builder',
            'document_asset_id' => null,
            'builder_json' => $blueprint,
            'paper_size' => 'a4',
            'orientation' => 'portrait',
        ],
    ], $actor);

    $draft = $savedDraft['document_draft'];
    $renderSha = $savedDraft['render_sha256'];

    $preview = $workflow->preview($family->fresh(), [
        'base_version' => $baseVersion,
        'document_revision' => $savedDraft['document_revision'],
        'lock_version' => $draft['lock_version'],
        'render_sha256' => $renderSha,
    ], $actor);

    $versionResult = $workflow->createVersion($family->fresh(), [
        'base_version' => $baseVersion,
        'document_revision' => $savedDraft['document_revision'],
        'lock_version' => $draft['lock_version'],
        'idempotency_key' => 'doc-version-'.uniqid(),
        'render_sha256' => $renderSha,
        'preview_receipt' => $preview['preview_receipt'],
    ], $actor);

    return NoticeDocumentVersion::query()->findOrFail($versionResult['document_version']['id']);
}

test('message draft selects ready same-family document version or none', function (): void {
    $docVersion = createReadyDocumentVersion($this->family, $this->actor);

    $saveAction = app(SaveNoticeMessageDraft::class);
    $saved = $saveAction->save($this->family, [
        'base_version' => $this->family->active_version,
        'family_revision' => $this->family->family_revision,
        'lock_version' => null,
        'idempotency_key' => 'save-msg-1',
        'state' => [
            'subject' => 'Examination Permit Ready',
            'body' => 'Your permit is ready for download.',
            'action_label' => null,
            'action_route' => null,
            'email_blueprint' => [
                'schema_version' => 'notice-email-1',
                'layout' => 'flow',
                'blocks' => [['type' => 'paragraph', 'text' => 'Hello {{applicant_name}}']],
            ],
            'document_revision_id' => $docVersion->id,
        ],
    ], $this->actor);

    $draft = $this->family->draft()->firstOrFail();
    expect($draft->document_revision_id)->toBe($docVersion->id)
        ->and($saved['draft']['state']['document_revision_id'])->toBe($docVersion->id);

    $savedNone = $saveAction->save($this->family->fresh(), [
        'base_version' => $this->family->fresh()->active_version,
        'family_revision' => $this->family->fresh()->family_revision,
        'lock_version' => $draft->lock_version,
        'draft_id' => $draft->id,
        'idempotency_key' => 'save-msg-none',
        'state' => [
            'subject' => 'Examination Permit Ready',
            'body' => 'Your permit is ready for download.',
            'action_label' => null,
            'action_route' => null,
            'email_blueprint' => [
                'schema_version' => 'notice-email-1',
                'layout' => 'flow',
                'blocks' => [['type' => 'paragraph', 'text' => 'Hello {{applicant_name}}']],
            ],
            'document_revision_id' => null,
        ],
    ], $this->actor);

    expect($this->family->draft()->firstOrFail()->document_revision_id)->toBeNull()
        ->and($savedNone['draft']['state']['document_revision_id'])->toBeNull();
});

test('saving document draft does not mutate message draft or invalidate email/portal proof', function (): void {
    $docVersion = createReadyDocumentVersion($this->family, $this->actor);
    $family = $this->family->fresh();

    $savedMsg = app(SaveNoticeMessageDraft::class)->save($family, [
        'base_version' => $family->active_version,
        'family_revision' => $family->family_revision,
        'lock_version' => null,
        'idempotency_key' => 'save-msg-proof-1',
        'state' => [
            'subject' => 'Permit Ready',
            'body' => 'Download permit.',
            'action_label' => null,
            'action_route' => null,
            'email_blueprint' => [
                'schema_version' => 'notice-email-1',
                'layout' => 'flow',
                'blocks' => [['type' => 'paragraph', 'text' => 'Hello {{applicant_name}}']],
            ],
            'document_revision_id' => $docVersion->id,
        ],
    ], $this->actor);

    $msgDraft = $this->family->draft()->firstOrFail();
    $emailReceipt = app(NoticeMessageOutputReceipt::class)->issue($family, $msgDraft, $this->actor, 'email');
    $portalReceipt = app(NoticeMessageOutputReceipt::class)->issue($family, $msgDraft, $this->actor, 'portal');

    // Mutate Document Draft
    $workflow = app(NoticeDocumentWorkflow::class);
    $surface = new NoticeTemplateSurface($family->domain, $family->slug);
    $blueprint = $surface->defaultBlueprint();
    $docBaseVersion = (int) ($family->documentVersions()->max('version') ?? 0);
    $docDraft = $workflow->save($family->fresh(), [
        'base_version' => $docBaseVersion,
        'document_revision' => $family->fresh()->document_revision,
        'lock_version' => null,
        'idempotency_key' => 'mutate-doc-draft',
        'state' => [
            'source_kind' => 'builder',
            'document_asset_id' => null,
            'builder_json' => $blueprint,
            'paper_size' => 'letter',
            'orientation' => 'landscape',
        ],
    ], $this->actor);

    // Message draft and proofs remain unchanged and valid
    $freshMsgDraft = $this->family->draft()->firstOrFail();
    expect($freshMsgDraft->id)->toBe($msgDraft->id)
        ->and($freshMsgDraft->lock_version)->toBe($msgDraft->lock_version)
        ->and($freshMsgDraft->document_revision_id)->toBe($docVersion->id);

    // Receipts still verify cleanly
    app(NoticeMessageOutputReceipt::class)->verify($this->family->fresh(), $freshMsgDraft, $this->actor, 'email', $emailReceipt);
    app(NoticeMessageOutputReceipt::class)->verify($this->family->fresh(), $freshMsgDraft, $this->actor, 'portal', $portalReceipt);
});

test('saving message draft does not mutate document draft or invalidate PDF proof', function (): void {
    $family = $this->family->fresh();
    $surface = new NoticeTemplateSurface($family->domain, $family->slug);
    $blueprint = $surface->defaultBlueprint();
    $blueprint['components'] = [
        ['type' => 'header', 'props' => ['title' => 'Permit', 'subtitle' => 'Schedule']],
        ['type' => 'field', 'props' => ['label' => 'App No', 'binding' => 'application_no']],
    ];

    $workflow = app(NoticeDocumentWorkflow::class);
    $docBaseVersion = (int) ($family->documentVersions()->max('version') ?? 0);
    $docDraft = $workflow->save($family, [
        'base_version' => $docBaseVersion,
        'document_revision' => $family->document_revision,
        'lock_version' => null,
        'idempotency_key' => 'doc-draft-test',
        'state' => [
            'source_kind' => 'builder',
            'document_asset_id' => null,
            'builder_json' => $blueprint,
            'paper_size' => 'a4',
            'orientation' => 'portrait',
        ],
    ], $this->actor);

    $preview = $workflow->preview($family->fresh(), [
        'base_version' => $docBaseVersion,
        'document_revision' => $family->fresh()->document_revision,
        'lock_version' => $docDraft['document_draft']['lock_version'],
        'render_sha256' => $docDraft['render_sha256'],
    ], $this->actor);
    // Now save a message draft
    app(SaveNoticeMessageDraft::class)->save($family->fresh(), [
        'base_version' => $family->fresh()->active_version,
        'family_revision' => $family->fresh()->family_revision,
        'lock_version' => null,
        'idempotency_key' => 'msg-draft-test',
        'state' => [
            'subject' => 'Permit Message Title',
            'body' => 'Permit message body.',
            'action_label' => null,
            'action_route' => null,
            'email_blueprint' => [
                'schema_version' => 'notice-email-1',
                'layout' => 'flow',
                'blocks' => [['type' => 'paragraph', 'text' => 'Hello {{applicant_name}}']],
            ],
        ],
    ], $this->actor);

    // Document draft is untouched
    $reloadedDocDraft = NoticeDocumentDraft::query()->where('communication_template_id', $family->id)->firstOrFail();
    expect($reloadedDocDraft->lock_version)->toBe($docDraft['document_draft']['lock_version'])
        ->and($reloadedDocDraft->state['paper_size'])->toBe('a4');

    // Document version publication succeeds with original preview receipt
    $publishedVersion = $workflow->createVersion($family->fresh(), [
        'base_version' => $docBaseVersion,
        'document_revision' => $family->fresh()->document_revision,
        'lock_version' => $reloadedDocDraft->lock_version,
        'idempotency_key' => 'doc-version-publish',
        'render_sha256' => $docDraft['render_sha256'],
        'preview_receipt' => $preview['preview_receipt'],
    ], $this->actor);

    expect($publishedVersion['document_version']['version'])->toBeGreaterThanOrEqual(1);
});

test('message publication pins exact selected document version and later document versions do not alter published message', function (): void {
    $docVersion1 = createReadyDocumentVersion($this->family, $this->actor, 'Permit V1');

    $family = $this->family->fresh();
    app(SaveNoticeMessageDraft::class)->save($family, [
        'base_version' => $family->active_version,
        'family_revision' => $family->family_revision,
        'lock_version' => null,
        'idempotency_key' => 'msg-save-doc1',
        'state' => [
            'subject' => 'Your Exam Permit V1',
            'body' => 'Here is your permit.',
            'action_label' => null,
            'action_route' => null,
            'email_blueprint' => [
                'schema_version' => 'notice-email-1',
                'layout' => 'flow',
                'blocks' => [['type' => 'paragraph', 'text' => 'Hello {{applicant_name}}']],
            ],
            'document_revision_id' => $docVersion1->id,
        ],
    ], $this->actor);

    $msgDraft = $this->family->draft()->firstOrFail();
    $family = $this->family->fresh();

    // Check attachment via check endpoint
    $response = $this->actingAs($this->actor)->postJson(
        route('setup.notifications.templates.attachment-check', ['domain' => 'examination', 'slug' => 'session-published']),
        [
            'base_version' => $family->active_version,
            'family_revision' => $family->family_revision,
            'lock_version' => $msgDraft->lock_version,
            'document_revision_id' => $docVersion1->id,
            'binding_sha256' => app(NoticeMessageState::class)->bindingDigest(
                $family,
                ['document_revision_id' => $docVersion1->id],
            ),
        ]
    );

    $response->assertOk();
    $attachmentReceipt = $response->json('attachment_receipt');

    $emailReceipt = app(NoticeMessageOutputReceipt::class)->issue($family, $msgDraft, $this->actor, 'email');
    $portalReceipt = app(NoticeMessageOutputReceipt::class)->issue($family, $msgDraft, $this->actor, 'portal');

    $publishAction = app(PublishNoticeMessageVersion::class);
    $published = $publishAction->publish($family, [
        'base_version' => $family->active_version,
        'family_revision' => $family->family_revision,
        'lock_version' => $msgDraft->lock_version,
        'draft_id' => $msgDraft->id,
        'idempotency_key' => 'publish-v2',
        'content_sha256' => $msgDraft->content_sha256,
        'preview_receipts' => [
            'email' => $emailReceipt,
            'portal' => $portalReceipt,
            'attachment' => $attachmentReceipt,
        ],
    ], $this->actor);

    $publishedMessageVersion = CommunicationTemplateVersion::query()->findOrFail($published['version']['id']);
    expect($publishedMessageVersion->document_revision_id)->toBe($docVersion1->id);

    // Create a new ready document version V2
    $docVersion2 = createReadyDocumentVersion($this->family->fresh(), $this->actor, 'Permit V2');

    // Published message version retains docVersion1
    expect($publishedMessageVersion->fresh()->document_revision_id)->toBe($docVersion1->id);
});

test('publish replay returns original version with same pin', function (): void {
    $docVersion = createReadyDocumentVersion($this->family, $this->actor);
    $family = $this->family->fresh();

    app(SaveNoticeMessageDraft::class)->save($family, [
        'base_version' => $family->active_version,
        'family_revision' => $family->family_revision,
        'lock_version' => null,
        'idempotency_key' => 'msg-replay-save',
        'state' => [
            'subject' => 'Permit Replay',
            'body' => 'Permit caption',
            'action_label' => null,
            'action_route' => null,
            'email_blueprint' => [
                'schema_version' => 'notice-email-1',
                'layout' => 'flow',
                'blocks' => [['type' => 'paragraph', 'text' => 'Hello {{applicant_name}}']],
            ],
            'document_revision_id' => $docVersion->id,
        ],
    ], $this->actor);

    $draft = $this->family->draft()->firstOrFail();
    $family = $this->family->fresh();

    $response = $this->actingAs($this->actor)->postJson(
        route('setup.notifications.templates.attachment-check', ['domain' => 'examination', 'slug' => 'session-published']),
        [
            'base_version' => $family->active_version,
            'family_revision' => $family->family_revision,
            'lock_version' => $draft->lock_version,
            'document_revision_id' => $docVersion->id,
            'binding_sha256' => app(NoticeMessageState::class)->bindingDigest(
                $family,
                ['document_revision_id' => $docVersion->id],
            ),
        ]
    );
    $response->assertOk();
    $attachmentReceipt = $response->json('attachment_receipt');
    $emailReceipt = app(NoticeMessageOutputReceipt::class)->issue($family, $draft, $this->actor, 'email');
    $portalReceipt = app(NoticeMessageOutputReceipt::class)->issue($family, $draft, $this->actor, 'portal');
    $publishData = [
        'base_version' => $family->active_version,
        'family_revision' => $family->family_revision,
        'lock_version' => $draft->lock_version,
        'draft_id' => $draft->id,
        'idempotency_key' => 'publish-replay-key',
        'content_sha256' => $draft->content_sha256,
        'document_revision_id' => $docVersion->id,
        'preview_receipts' => ['email' => $emailReceipt, 'portal' => $portalReceipt, 'attachment' => $attachmentReceipt],
    ];

    $result = app(PublishNoticeMessageVersion::class)->publish($family, $publishData, $this->actor);
    $replay = app(PublishNoticeMessageVersion::class)->publish($this->family->fresh(), $publishData, $this->actor);

    expect($replay['replayed'])->toBeTrue()
        ->and($replay['version']['id'])->toBe($result['version']['id'])
        ->and($replay['version']['document_revision_id'])->toBe($docVersion->id);
});

test('producer event freezes notice-delivery-3 with pinned document tuple without running PDF compile or provider IO', function (): void {
    $docVersion = createReadyDocumentVersion($this->family, $this->actor);
    $family = $this->family->fresh();

    app(SaveNoticeMessageDraft::class)->save($family, [
        'base_version' => $family->active_version,
        'family_revision' => $family->family_revision,
        'lock_version' => null,
        'idempotency_key' => 'msg-producer-save',
        'state' => [
            'subject' => 'Exam Permit {{applicant_name}}',
            'body' => 'Your session is published.',
            'action_label' => null,
            'action_route' => null,
            'email_blueprint' => [
                'schema_version' => 'notice-email-1',
                'layout' => 'flow',
                'blocks' => [['type' => 'paragraph', 'text' => 'Hello {{applicant_name}} at {{institution_name}}']],
            ],
            'document_revision_id' => $docVersion->id,
        ],
    ], $this->actor);

    $draft = $this->family->draft()->firstOrFail();
    $family = $this->family->fresh();

    $check = $this->actingAs($this->actor)->postJson(
        route('setup.notifications.templates.attachment-check', ['domain' => 'examination', 'slug' => 'session-published']),
        [
            'base_version' => $family->active_version,
            'family_revision' => $family->family_revision,
            'lock_version' => $draft->lock_version,
            'document_revision_id' => $docVersion->id,
            'binding_sha256' => app(NoticeMessageState::class)->bindingDigest(
                $family,
                ['document_revision_id' => $docVersion->id],
            ),
        ]
    );
    $check->assertOk();

    app(PublishNoticeMessageVersion::class)->publish($family, [
        'base_version' => $family->active_version,
        'family_revision' => $family->family_revision,
        'lock_version' => $draft->lock_version,
        'draft_id' => $draft->id,
        'idempotency_key' => 'publish-producer-test',
        'content_sha256' => $draft->content_sha256,
        'document_revision_id' => $docVersion->id,
        'preview_receipts' => [
            'email' => app(NoticeMessageOutputReceipt::class)->issue($family, $draft, $this->actor, 'email'),
            'portal' => app(NoticeMessageOutputReceipt::class)->issue($family, $draft, $this->actor, 'portal'),
            'attachment' => $check->json('attachment_receipt'),
        ],
    ], $this->actor);

    // Setup recipients
    $cycle = AcademicCycle::query()->where('code', 'SYN-CYCLE-2026')->firstOrFail();
    $room = Room::query()->where('code', 'RM-101')->firstOrFail();
    $session = ExamSession::query()->create([
        'academic_cycle_id' => $cycle->id,
        'room_id' => $room->id,
        'code' => 'SESS-FREEZE-1',
        'status' => ExamSession::STATUS_PUBLISHED,
        'booking_mode' => ExamSession::BOOKING_STAFF_ONLY,
        'starts_at' => now()->addDays(2),
        'effective_end_at' => now()->addDays(2)->addHours(2),
        'version' => 1,
        'notification_policy_snapshot' => ExamNotificationPolicy::defaults()->snapshot(1),
    ]);

    $app1 = submitSyntheticApplicant('alpha.producer@example.test', 'Alpha Candidate');
    $assignment1 = ExamAssignment::query()->create([
        'exam_session_id' => $session->id,
        'admission_attempt_id' => $app1['attempt']->id,
        'status' => ExamAssignment::STATUS_ASSIGNED,
        'source' => ExamAssignment::SOURCE_STAFF_ASSIGNED,
        'version' => 1,
    ]);

    $app2 = submitSyntheticApplicant('beta.producer@example.test', 'Beta Candidate');
    $assignment2 = ExamAssignment::query()->create([
        'exam_session_id' => $session->id,
        'admission_attempt_id' => $app2['attempt']->id,
        'status' => ExamAssignment::STATUS_ASSIGNED,
        'source' => ExamAssignment::SOURCE_STAFF_ASSIGNED,
        'version' => 1,
    ]);

    $intent = new NotificationIntent(
        eventType: 'exam.session_published.v1',
        eventVersion: 1,
        templateKey: 'exam.session_published',
        templateVersion: 1,
        payload: [
            'domain' => 'examination',
            'canonical_slug' => 'session-published',
            'session_id' => $session->id,
            'session_version' => $session->version,
            'institution_id' => $this->institution->id,
        ]
    );

    $dispatcher = app(NotificationIntentDispatcher::class);
    $deliveries = $dispatcher->dispatch(
        $intent,
        'session-published-event-1',
        [$app1['attempt']->identity, $app2['attempt']->identity],
        [NotificationDelivery::CHANNEL_MAIL]
    );

    expect($deliveries)->toHaveCount(2);

    $delivery1 = $deliveries->firstWhere('recipient_id', $app1['attempt']->identity->id);
    $delivery2 = $deliveries->firstWhere('recipient_id', $app2['attempt']->identity->id);

    expect($delivery1->payload['snapshot_schema_version'])->toBe('notice-delivery-3')
        ->and($delivery1->payload['print'])->toMatchArray([
            'mode' => 'notice_document',
            'document_revision_id' => $docVersion->id,
            'version' => $docVersion->version,
            'render_sha256' => $docVersion->render_sha256,
            'definition_sha256' => $docVersion->definition_sha256,
        ])
        ->and($delivery1->payload['print'])->not->toHaveKey('version_id')
        ->and($delivery1->payload['print'])->not->toHaveKey('definition')
        ->and($delivery1->payload['context']['values']['application_no'])->toBe($app1['attempt']->reference_code)
        ->and($delivery2->payload['context']['values']['application_no'])->toBe($app2['attempt']->reference_code)
        ->and($delivery1->payload['context']['values']['application_no'])->not->toBe($delivery2->payload['context']['values']['application_no']);

    // Zero artifacts written during dispatch (no PDF compilation inside producer transaction)
    expect(NotificationDeliveryAttemptArtifact::query()->count())->toBe(0);
});
test('schema three mailable cannot downgrade a selected document pin to attachment-free mail', function (): void {
    $payload = [
        'snapshot_schema_version' => 'notice-delivery-3',
        'institution_id' => (int) $this->institution->id,
        'domain' => 'examination',
        'canonical_slug' => 'session-published',
        'event_type' => 'exam.session_published.v1',
        'template_key' => 'exam.session_published',
        'recipient' => ['type' => ApplicantIdentity::class, 'id' => 1, 'email' => 'candidate@example.test'],
        'event_resource' => ['session_id' => 1, 'attempt_id' => 1, 'identity_id' => 1],
        'template' => ['id' => (string) Str::uuid(), 'version' => 1,
            'content_sha256' => str_repeat('a', 64), 'content_schema_version' => 'notice-content-3',
            'email_schema_version' => 'notice-email-1', 'email_renderer_version' => 'notice-email-1'],
        'context' => ['values' => ['applicant_name' => 'Candidate'], 'missing_required_keys' => []],
        'email' => ['subject_template' => 'Permit', 'blueprint' => ['schema_version' => 'notice-email-1',
            'layout' => 'flow', 'blocks' => [['type' => 'paragraph', 'text' => 'Permit']]],
            'assets' => [], 'preparation_failure' => null],
        'portal' => ['title' => 'Permit', 'body' => 'Ready', 'action_label' => null, 'action_url' => null],
        'print' => ['mode' => 'notice_document', 'document_revision_id' => 1, 'version' => 1,
            'state' => [], 'assets' => [], 'definition_sha256' => str_repeat('b', 64), 'render_sha256' => str_repeat('c', 64)],
    ];

    expect(fn () => (new NotificationMailable(
        'exam.session_published', 1, $payload,
    ))->attachments())->toThrow(RuntimeException::class, 'notice_print_attachment_missing');
});

test('post-commit delivery compiles recipient-specific PDF with actual application_no and records delivery attempt artifact', function (): void {
    $docVersion = createReadyDocumentVersion($this->family, $this->actor);
    $family = $this->family->fresh();

    app(SaveNoticeMessageDraft::class)->save($family, [
        'base_version' => $family->active_version,
        'family_revision' => $family->family_revision,
        'lock_version' => null,
        'idempotency_key' => 'msg-postcommit-save',
        'state' => [
            'subject' => 'Exam Permit {{applicant_name}}',
            'body' => 'Your session is published.',
            'action_label' => null,
            'action_route' => null,
            'email_blueprint' => [
                'schema_version' => 'notice-email-1',
                'layout' => 'flow',
                'blocks' => [['type' => 'paragraph', 'text' => 'Hello {{applicant_name}}']],
            ],
            'document_revision_id' => $docVersion->id,
        ],
    ], $this->actor);

    $draft = $this->family->draft()->firstOrFail();
    $family = $this->family->fresh();

    $check = $this->actingAs($this->actor)->postJson(
        route('setup.notifications.templates.attachment-check', ['domain' => 'examination', 'slug' => 'session-published']),
        [
            'base_version' => $family->active_version,
            'family_revision' => $family->family_revision,
            'lock_version' => $draft->lock_version,
            'document_revision_id' => $docVersion->id,
            'binding_sha256' => app(NoticeMessageState::class)->bindingDigest($family, ['document_revision_id' => $docVersion->id]),
        ]
    );
    $check->assertOk();

    app(PublishNoticeMessageVersion::class)->publish($family, [
        'base_version' => $family->active_version,
        'family_revision' => $family->family_revision,
        'lock_version' => $draft->lock_version,
        'draft_id' => $draft->id,
        'idempotency_key' => 'publish-postcommit-test',
        'content_sha256' => $draft->content_sha256,
        'document_revision_id' => $docVersion->id,
        'preview_receipts' => [
            'email' => app(NoticeMessageOutputReceipt::class)->issue($family, $draft, $this->actor, 'email'),
            'portal' => app(NoticeMessageOutputReceipt::class)->issue($family, $draft, $this->actor, 'portal'),
            'attachment' => $check->json('attachment_receipt'),
        ],
    ], $this->actor);

    $cycle = AcademicCycle::query()->where('code', 'SYN-CYCLE-2026')->firstOrFail();
    $room = Room::query()->where('code', 'RM-101')->firstOrFail();
    $session = ExamSession::query()->create([
        'academic_cycle_id' => $cycle->id,
        'room_id' => $room->id,
        'code' => 'SESS-DELIVERY-1',
        'status' => ExamSession::STATUS_PUBLISHED,
        'booking_mode' => ExamSession::BOOKING_STAFF_ONLY,
        'starts_at' => now()->addDays(2),
        'effective_end_at' => now()->addDays(2)->addHours(2),
        'version' => 1,
        'notification_policy_snapshot' => ExamNotificationPolicy::defaults()->snapshot(1),
    ]);

    $app = submitSyntheticApplicant('gamma.delivery@example.test', 'Gamma Candidate');
    ExamAssignment::query()->create([
        'exam_session_id' => $session->id,
        'admission_attempt_id' => $app['attempt']->id,
        'status' => ExamAssignment::STATUS_ASSIGNED,
        'source' => ExamAssignment::SOURCE_STAFF_ASSIGNED,
        'version' => 1,
    ]);

    $intent = new NotificationIntent(
        eventType: 'exam.session_published.v1',
        eventVersion: 1,
        templateKey: 'exam.session_published',
        templateVersion: 1,
        payload: [
            'domain' => 'examination',
            'canonical_slug' => 'session-published',
            'session_id' => $session->id,
            'session_version' => $session->version,
            'institution_id' => $this->institution->id,
        ]
    );

    $deliveries = app(NotificationIntentDispatcher::class)->dispatch(
        $intent,
        'delivery-event-1',
        [$app['attempt']->identity],
        [NotificationDelivery::CHANNEL_MAIL]
    );

    $delivery = $deliveries->firstOrFail();

    $compiler = app(NoticeDocumentAttachmentCompiler::class);
    $pdfPath = $compiler->compileFrozenPdf($delivery->payload['print'], $delivery);

    expect(is_file($pdfPath))->toBeTrue();
    $contents = file_get_contents($pdfPath);
    expect(str_starts_with($contents, '%PDF-'))->toBeTrue();

    // Check delivery attempt artifact recorded
    $artifact = NotificationDeliveryAttemptArtifact::query()->where('delivery_id', $delivery->id)->firstOrFail();
    expect($artifact->mime_type)->toBe('application/pdf')
        ->and($artifact->file_size_bytes)->toBe(filesize($pdfPath))
        ->and($artifact->checksum)->toBe(hash_file('sha256', $pdfPath));

    @unlink($pdfPath);
});

test('foreign document version from another family is rejected with 422', function (): void {
    $registrar = User::query()->where('email', 'registrar.admin@nexiam.test')->firstOrFail();
    $foreignDocVersion = createReadyDocumentVersion($this->otherFamily, $registrar);
    $family = $this->family->fresh();

    expect(fn () => app(SaveNoticeMessageDraft::class)->save($family, [
        'base_version' => $family->active_version,
        'family_revision' => $family->family_revision,
        'lock_version' => null,
        'idempotency_key' => 'save-foreign-attempt',
        'state' => [
            'subject' => 'Subject',
            'body' => 'Body',
            'action_label' => null,
            'action_route' => null,
            'email_blueprint' => [
                'schema_version' => 'notice-email-1',
                'layout' => 'flow',
                'blocks' => [['type' => 'paragraph', 'text' => 'Hello {{applicant_name}}']],
            ],
            'document_revision_id' => $foreignDocVersion->id,
        ],
    ], $this->actor))->toThrow(HttpException::class);

    app(SaveNoticeMessageDraft::class)->save($family, [
        'base_version' => $family->active_version,
        'family_revision' => $family->family_revision,
        'lock_version' => null,
        'idempotency_key' => 'save-valid-draft',
        'state' => [
            'subject' => 'Subject',
            'body' => 'Body',
            'action_label' => null,
            'action_route' => null,
            'email_blueprint' => [
                'schema_version' => 'notice-email-1',
                'layout' => 'flow',
                'blocks' => [['type' => 'paragraph', 'text' => 'Hello {{applicant_name}}']],
            ],
            'document_revision_id' => null,
        ],
    ], $this->actor);

    $family = $this->family->fresh();
    $draft = $this->family->draft()->firstOrFail();
    $response = $this->actingAs($this->actor)->postJson(
        route('setup.notifications.templates.attachment-check', ['domain' => 'examination', 'slug' => 'session-published']),
        [
            'base_version' => $family->active_version,
            'family_revision' => $family->family_revision,
            'lock_version' => $draft->lock_version,
            'document_revision_id' => $foreignDocVersion->id,
            'binding_sha256' => hash('sha256', 'fake-binding'),
        ]
    );

    $response->assertStatus(422);
});
