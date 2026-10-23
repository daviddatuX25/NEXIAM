<?php

declare(strict_types=1);

use App\Models\Foundation\Institution;
use App\Models\Notifications\CommunicationTemplate;
use App\Models\Notifications\NoticeDocumentVersion;
use App\Models\User;
use App\Notifications\Templates\CommunicationTemplatePayload;
use App\Notifications\Templates\NoticeDocumentPin;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Storage;

beforeEach(function (): void {
    seedE2Foundation();
    Storage::fake('local');
    $this->actor = User::query()->where('email', 'guidance.counselor@nexiam.test')->firstOrFail();
    $this->family = CommunicationTemplate::query()->create([
        'institution_id' => Institution::firstOrFail()->id,
        'domain' => 'examination',
        'slug' => 'session-published',
        'event_key' => 'exam.session_published.v1',
        'name' => 'Examination schedule and permit',
        'active_version' => 1,
        'family_revision' => 1,
    ]);
    $this->existingPin = makeReadyDocument($this->family, 2);
    $this->family->versions()->create([
        'version' => 1,
        'content_schema_version' => 'notice-content-3',
        'subject' => 'Examination schedule',
        'body' => 'Check the examination schedule.',
        'action_label' => null,
        'action_route' => null,
        'document_revision_id' => $this->existingPin->id,
        'email_blueprint' => ['schema_version' => 'notice-email-1', 'layout' => 'flow', 'blocks' => [
            ['type' => 'paragraph', 'text' => 'Hello {{applicant_name}}'],
        ]],
        'email_renderer_version' => 'notice-message-email-2',
        'document_mode' => 'notice_document',
        'email_subject' => '',
        'email_body' => '',
        'in_app_title' => '',
        'in_app_body' => '',
    ]);
    $this->candidate = makeReadyDocument($this->family, 3);
});

function makeReadyDocument(CommunicationTemplate $family, int $version): NoticeDocumentVersion
{
    $state = [
        'source_kind' => 'builder',
        'document_asset_id' => null,
        'builder_json' => [],
        'paper_size' => 'a4',
        'orientation' => 'portrait',
        'pins' => [
            'paper_size' => 'a4',
            'orientation' => 'portrait',
            'catalog' => [],
            'image_sha256s' => [],
            'compiler' => 'notice-document-compiler-v1',
            'renderer' => 'notice-document-renderer-v1',
            'synthetic_context' => 'notice-document-synthetic-v1',
            'source_checksum' => null,
        ],
    ];
    $renderSha = NoticeDocumentPin::renderDigest($state);

    return $family->documentVersions()->create([
        'version' => $version,
        'state' => $state,
        'render_sha256' => $renderSha,
        'definition_sha256' => CommunicationTemplatePayload::hash($state),
        'proof_receipt' => Crypt::encryptString(json_encode([
            'family_id' => $family->id,
            'institution_id' => (int) $family->institution_id,
            'render_sha256' => $renderSha,
            'pdf_sha256' => str_repeat('a', 64),
        ], JSON_THROW_ON_ERROR)),
    ]);
}

function noticeCandidateParams(NoticeDocumentVersion $candidate): array
{
    return ['document_candidate_id' => $candidate->id, 'document_candidate_version' => $candidate->version];
}

test('composer GET returns the exact authorized ready candidate without changing the existing message pin', function (): void {
    $latestCandidate = makeReadyDocument($this->family, 4);
    $before = [
        $this->family->fresh()->active_version,
        $this->family->fresh()->document_revision,
        $this->family->versions()->count(),
        $this->family->draft()->count(),
        $this->family->activeVersion()->document_revision_id,
    ];
    $response = actingAsAdmissionSupportStaff($this, $this->actor)->get(route('setup.notifications.templates.edit', [
        'domain' => 'examination',
        'slug' => 'session-published',
        ...noticeCandidateParams($this->candidate),
    ]));

    $response->assertOk()->assertInertia(fn ($page) => $page
        ->component('Setup/Notifications/Templates/Message')
        ->where('family.id', (string) $this->family->id)
        ->where('family.domain', 'examination')
        ->where('family.slug', 'session-published')
        ->where('family.description', 'Sent to candidates when their examination session schedule is published.')
        ->where('family.channels', ['in_app', 'mail'])
        ->where('family.event_key', 'exam.session_published.v1')
        ->where('family.default_action_route', 'applicant.schedule')
        ->where('family.default_action_label', 'View Examination Permit')
        ->where('pending_document_candidate.id', (int) $this->candidate->id)
        ->where('pending_document_candidate.version', 3)
        ->where('pending_document_candidate.definition_sha256', $this->candidate->definition_sha256)
        ->where('pending_document_candidate.render_sha256', $this->candidate->render_sha256)
        ->where('state.action_route', null)
        ->where('state.document_revision_id', (int) $this->existingPin->id)
        ->where('read_only', false));

    expect((int) $this->existingPin->id)->not->toBe((int) $latestCandidate->id)
        ->and([
            $this->family->fresh()->active_version,
            $this->family->fresh()->document_revision,
            $this->family->versions()->count(),
            $this->family->draft()->count(),
            $this->family->activeVersion()->document_revision_id,
        ])->toBe($before);
});

test('candidate GET requires a complete pair and hides candidates outside this family or not ready without writes', function (): void {
    $parameters = ['domain' => 'examination', 'slug' => 'session-published'];
    $otherFamily = CommunicationTemplate::query()->create([
        'institution_id' => $this->family->institution_id,
        'domain' => 'admissions',
        'slug' => 'accepted',
        'event_key' => 'admission.accepted.v1',
        'name' => 'Admission offer',
        'active_version' => 1,
        'family_revision' => 1,
    ]);
    $foreignCandidate = makeReadyDocument($otherFamily, 1);
    $notReady = $this->family->documentVersions()->create([
        'version' => 4,
        'state' => [],
        'render_sha256' => str_repeat('b', 64),
        'definition_sha256' => str_repeat('c', 64),
        'proof_receipt' => Crypt::encryptString('{}'),
    ]);
    $before = [
        $this->family->fresh()->active_version,
        $this->family->fresh()->document_revision,
        $this->family->versions()->count(),
        $this->family->documentVersions()->count(),
        $this->family->draft()->count(),
    ];

    actingAsAdmissionSupportStaff($this, $this->actor)->getJson(route('setup.notifications.templates.editor-state', [
        ...$parameters,
        'document_candidate_id' => $this->candidate->id,
    ]))->assertUnprocessable();
    actingAsAdmissionSupportStaff($this, $this->actor)->getJson(route('setup.notifications.templates.editor-state', [
        ...$parameters,
        ...noticeCandidateParams($foreignCandidate),
    ]))->assertNotFound();
    actingAsAdmissionSupportStaff($this, $this->actor)->getJson(route('setup.notifications.templates.editor-state', [
        ...$parameters,
        ...noticeCandidateParams($notReady),
    ]))->assertNotFound();

    expect([
        $this->family->fresh()->active_version,
        $this->family->fresh()->document_revision,
        $this->family->versions()->count(),
        $this->family->documentVersions()->count(),
        $this->family->draft()->count(),
    ])->toBe($before);
});

test('candidate cannot be attached to historical inspection or a family without PDF eligibility', function (): void {
    $parameters = ['domain' => 'examination', 'slug' => 'session-published'];
    actingAsAdmissionSupportStaff($this, $this->actor)->getJson(route('setup.notifications.templates.editor-state', [
        ...$parameters,
        'version' => 1,
        ...noticeCandidateParams($this->candidate),
    ]))->assertNotFound();

    $reminder = CommunicationTemplate::query()->create([
        'institution_id' => $this->family->institution_id,
        'domain' => 'examination',
        'slug' => 'reminder',
        'event_key' => 'exam.reminder.v1',
        'name' => 'Reminder',
        'active_version' => 1,
        'family_revision' => 1,
    ]);
    $reminderCandidate = makeReadyDocument($reminder, 1);
    actingAsAdmissionSupportStaff($this, $this->actor)->getJson(route('setup.notifications.templates.editor-state', [
        'domain' => 'examination',
        'slug' => 'reminder',
        ...noticeCandidateParams($reminderCandidate),
    ]))->assertNotFound();
});
