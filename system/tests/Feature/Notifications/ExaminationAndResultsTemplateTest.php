<?php

declare(strict_types=1);

use App\Authorization\CapabilityCatalog;
use App\Models\Auth\StaffAuthenticationState;
use App\Models\Foundation\Institution;
use App\Models\Notifications\CommunicationTemplate;
use App\Models\Notifications\CommunicationTemplateDraft;
use App\Models\Notifications\CommunicationTemplateReceipt;
use App\Models\StaffProfile;
use App\Models\User;
use App\Notifications\Templates\CommunicationTemplatePayload;
use App\Notifications\Templates\ProvisionCanonicalCommunicationTemplates;
use App\Notifications\Templates\Queries\CommunicationTemplateEditorQuery;
use App\Notifications\Templates\Queries\NoticeMessageComposerQuery;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Permission;

beforeEach(function (): void {
    seedE2Foundation();
    $this->actor = User::query()->where('email', 'guidance.counselor@nexiam.test')->firstOrFail();
    $this->institution = Institution::current();
    foreach (['examination', 'results'] as $domain) {
        app(ProvisionCanonicalCommunicationTemplates::class)->provision($this->institution, $this->actor, $domain);
    }
});

function noticeScopedStaff(string $capability, ?Institution $institution = null, string $office = 'guidance'): User
{
    $actor = User::factory()->create();
    StaffProfile::query()->create(['user_id' => $actor->id, 'institution_id' => ($institution ?? Institution::current())->id,
        'office' => $office, 'status' => StaffProfile::STATUS_ACTIVE]);
    StaffAuthenticationState::query()->create([
        'user_id' => $actor->id,
        'session_version' => 1,
        'activation_completed_at' => now(),
        'password_set_at' => now(),
        'two_factor_confirmed_at' => now(),
        'two_factor_secret' => 'SECRET2FAKEY',
        'two_factor_generation' => (string) Str::uuid(),
        'must_change_password' => false,
    ]);
    $actor->givePermissionTo(Permission::findOrCreate($capability, 'web'));

    return $actor;
}

/** @return array<string, mixed> */
function scopedNoticeState(): array
{
    return ['subject' => 'Upcoming examination', 'body' => 'Please review your examination details.',
        'action_label' => 'Review examination details', 'action_route' => 'applicant.schedule', 'document_revision_id' => null,
        'email_blueprint' => ['schema_version' => 'notice-email-1', 'layout' => 'flow', 'blocks' => [
            ['type' => 'paragraph', 'runs' => [['text' => 'Dear '], ['token' => 'applicant_name'], ['text' => ', please prepare.']]],
        ]]];
}

test('guidance counselor opens compact examination desk with canonical metadata not synthetic notice copy', function (): void {
    $this->actingAs($this->actor)->get(route('setup.notifications.examination'))->assertOk()
        ->assertInertia(fn ($page) => $page->component('Setup/Notifications/Examination')->has('notices', 6)
            ->where('notices', function ($notices): bool {
                $publication = collect($notices)->firstWhere('slug', 'session-published');

                return $publication !== null && $publication['supports_pdf'] === true
                    && $publication['pdf_name'] === 'Examination-Permit.pdf'
                    && collect($notices)->every(fn (array $notice): bool => ! array_key_exists('sample_subject', $notice)
                        && ! array_key_exists('sample_body', $notice) && ! array_key_exists('document_asset_id', $notice));
            }));
});

test('explicit examination lifecycle provisions six active families and direct family pages do not mutate', function (): void {
    $templates = CommunicationTemplate::query()->where('domain', 'examination');
    expect($templates->count())->toBe(6)->and($templates->pluck('slug')->all())->toEqualCanonicalizing([
        'session-published', 'reminder', 'session-cancelled', 'session-unpublished', 'slot-reserved', 'session-completed',
    ]);
    $before = CommunicationTemplateDraft::query()->count();
    $this->actingAs($this->actor)->get('/setup/notifications/examination/templates/reminder')->assertOk()
        ->assertInertia(fn ($page) => $page->component('Setup/Notifications/Templates/Message')
            ->where('family.slug', 'reminder')->where('family.supports_pdf', false));
    expect(CommunicationTemplateDraft::query()->count())->toBe($before);
});

test('registrar admin cannot access examination family or protected editor despite registrar capability', function (): void {
    $registrar = User::query()->where('email', 'registrar.admin@nexiam.test')->firstOrFail();
    $this->actingAs($registrar)->get('/setup/notifications/examination/templates/reminder')->assertForbidden();
    $this->getJson('/setup/notifications/examination/templates/reminder/editor-state')->assertForbidden();
});

test('guidance can save and publish message only reminder through saved exact HTML and portal checks', function (): void {
    $template = CommunicationTemplate::query()->where('domain', 'examination')->where('slug', 'reminder')->firstOrFail();
    $parameters = ['domain' => 'examination', 'slug' => 'reminder'];
    $created = $this->actingAs($this->actor)->postJson(route('setup.notifications.templates.drafts.store', $parameters), [
        'state' => scopedNoticeState(), 'base_version' => $template->active_version,
        'family_revision' => $template->family_revision, 'lock_version' => null, 'idempotency_key' => (string) Str::uuid(),
    ])->assertCreated()->json();
    $guards = ['base_version' => 1, 'family_revision' => $created['family_revision'], 'lock_version' => 1, 'content_sha256' => $created['content_sha256']];
    $receipts = [];
    foreach (['email', 'portal'] as $view) {
        $receipts[$view] = $this->postJson(route('setup.notifications.templates.preview', $parameters), [
            'base_version' => $guards['base_version'], 'family_revision' => $guards['family_revision'],
            'lock_version' => $guards['lock_version'], 'view' => $view,
            'output_sha256' => $created['output_digests'][$view.'_sha256'],
        ])->assertOk()->json('preview_receipt');
    }
    $this->postJson(route('setup.notifications.templates.publish', $parameters), [...$guards,
        'preview_receipts' => $receipts, 'idempotency_key' => (string) Str::uuid()])->assertOk()->assertJsonPath('active_version', 2);
    expect($template->fresh()->active_version)->toBe(2)->and($template->versions()->where('version', 2)->value('document_mode'))->toBe('none');
    $this->get(route('setup.notifications.examination'))->assertOk()
        ->assertInertia(fn ($page) => $page->where('notices', function ($notices): bool {
            $reminder = collect($notices)->firstWhere('slug', 'reminder');

            return $reminder !== null && $reminder['active_version'] === 2 && ! array_key_exists('sample_body', $reminder);
        }));
});

test('release authorized guidance sees all Results families without an obsolete nested catalog', function (): void {
    $this->actingAs($this->actor)->get(route('setup.notifications.results'))->assertOk()
        ->assertInertia(fn ($page) => $page->component('Setup/Notifications/Results')->has('notices', 6)
            ->where('notices', fn ($notices): bool => collect($notices)->pluck('slug')->sort()->values()->all() === [
                'consultation-online-invited', 'consultation-published', 'consultation-reserved', 'released-f2f', 'released-online', 'unreleased',
            ]));
    $releaseOnly = noticeScopedStaff(CapabilityCatalog::GUIDANCE_RESULTS_RELEASE);
    $this->actingAs($releaseOnly)->get('/setup/notifications/results/templates/released-online')->assertOk();
    $this->getJson('/setup/notifications/results/templates/released-online/editor-state')->assertOk();
});

test('outsider without capability cannot access Results family metadata', function (): void {
    $this->actingAs(User::factory()->create())->get('/setup/notifications/results/templates/released-online')->assertForbidden();
});

test('Results metadata-only reader receives no private state context sources or editor endpoints', function (): void {
    $reader = noticeScopedStaff(CapabilityCatalog::GUIDANCE_ASSESSMENT_CATALOG);
    $template = CommunicationTemplate::query()->where('domain', 'results')->where('slug', 'released-online')->firstOrFail();
    $template->versions()->create(['version' => 2, 'content_schema_version' => 'notice-content-1',
        'email_subject' => 'PRIVATE CONTENT MARKER', 'email_body' => 'PRIVATE HTML MARKER',
        'in_app_title' => 'PRIVATE TITLE', 'in_app_body' => 'PRIVATE CAPTION', 'document_mode' => 'none']);
    $template->update(['active_version' => 2]);
    $query = app(CommunicationTemplateEditorQuery::class)->family($reader, 'results', 'released-online');
    expect($query)->not->toHaveKeys(['endpoints', 'state', 'sampleContext', 'documentAnalysis', 'historical_content'])
        ->and($query['family'])->not->toHaveKeys(['tokens', 'subject', 'body', 'email_blueprint', 'document_asset_id'])
        ->and($query['versions'][0])->not->toHaveKeys(['inspect_url', 'copy_state_url', 'subject', 'email_body', 'document_asset_id'])
        ->and(json_encode($query))->not->toContain('PRIVATE CONTENT MARKER', 'PRIVATE HTML MARKER');
    $this->actingAs($reader)->get('/setup/notifications/results/templates/released-online')->assertOk()
        ->assertInertia(fn ($page) => $page->component('Setup/Notifications/Templates/Studio')->where('can_edit', false)
            ->missing('endpoints')->missing('versions.0.email_body')->missing('sampleContext')->missing('draft.state'));
    $before = [$template->fresh()->family_revision, $template->versions()->count(), $template->draft()->count()];
    foreach (['edit', 'editor-state', 'edit?version=1', 'editor-state?version=1'] as $path) {
        $this->get('/setup/notifications/results/templates/released-online/'.$path)->assertForbidden();
    }
    foreach (['drafts', 'preview', 'publish', 'test-mail'] as $path) {
        $this->postJson('/setup/notifications/results/templates/released-online/'.$path, [])->assertForbidden();
    }
    foreach (['import', 'import-review'] as $path) {
        $this->postJson('/setup/notifications/results/templates/released-online/'.$path, [])->assertNotFound();
    }
    expect([$template->fresh()->family_revision, $template->versions()->count(), $template->draft()->count()])->toBe($before);
});

test('foreign institution and wrong office requests cannot reveal family content or mutate the draft', function (): void {
    $foreignInstitution = Institution::query()->create(['code' => 'FOREIGN-NOTICE', 'name' => 'Foreign']);
    foreach ([noticeScopedStaff(CapabilityCatalog::GUIDANCE_EXAM_SCHEDULE, $foreignInstitution),
        noticeScopedStaff(CapabilityCatalog::GUIDANCE_EXAM_SCHEDULE, null, 'registrar')] as $actor) {
        $this->actingAs($actor)->getJson('/setup/notifications/examination/templates/reminder/editor-state')->assertForbidden();
        $this->postJson('/setup/notifications/examination/templates/reminder/drafts', [])->assertForbidden();
    }
});

test('draft URL identity cannot bind another family and unknown history is hidden', function (): void {
    $template = CommunicationTemplate::query()->where('domain', 'examination')->where('slug', 'reminder')->firstOrFail();
    $other = CommunicationTemplate::query()->where('domain', 'examination')->where('slug', 'session-published')->firstOrFail();
    $draft = $other->draft()->create(['base_version' => 1, 'lock_version' => 1, 'stage' => 2,
        'subject' => 'Other family', 'body' => 'Private', 'email_subject' => 'Private', 'email_body' => '',
        'in_app_title' => 'Private', 'in_app_body' => 'Private', 'document_mode' => 'none', 'updated_by_user_id' => $this->actor->id]);
    $this->actingAs($this->actor)->putJson('/setup/notifications/examination/templates/reminder/drafts/'.$draft->id, [
        'state' => scopedNoticeState(), 'base_version' => 1, 'family_revision' => $template->family_revision,
        'lock_version' => 1, 'idempotency_key' => (string) Str::uuid(),
    ])->assertNotFound();
    $this->getJson('/setup/notifications/examination/templates/reminder/editor-state?version=99999')->assertNotFound();
    $this->getJson('/setup/notifications/examination/templates/reminder/editor-state?version[]=1')->assertNotFound();
    expect($draft->fresh()->subject)->toBe('Other family')->and($template->draft()->exists())->toBeFalse();
});

test('historical copy opens the selected message version and conflicts instead of replacing an existing draft', function (): void {
    $template = CommunicationTemplate::query()->where('domain', 'examination')->where('slug', 'reminder')->firstOrFail();
    $current = app(NoticeMessageComposerQuery::class)
        ->editor($this->actor, 'examination', 'reminder');
    $copyUrl = $current['versions'][0]['copy_state_url'];
    expect($copyUrl)->toBe(route('setup.notifications.templates.edit', [
        'domain' => 'examination', 'slug' => 'reminder', 'source_version' => 1,
    ]));
    $this->actingAs($this->actor)->get($copyUrl)->assertOk()
        ->assertInertia(fn ($page) => $page->component('Setup/Notifications/Templates/Message')
            ->where('copy_source_version', 1)->where('state.document_revision_id', null));
    $source = app(NoticeMessageComposerQuery::class)
        ->editor($this->actor, 'examination', 'reminder', null, 1);
    $created = $this->postJson('/setup/notifications/examination/templates/reminder/drafts', [
        'state' => $source['state'], 'base_version' => 1,
        'family_revision' => $source['family']['family_revision'], 'lock_version' => null, 'idempotency_key' => (string) Str::uuid(),
    ])->assertCreated()->json();
    $this->get($copyUrl)->assertConflict();
    expect($template->draft()->count())->toBe(1)->and($template->fresh()->family_revision)->toBe($created['family_revision']);
});

test('scorecard message authoring is email and Portal only while PDF checks live in the independent document workspace', function (): void {
    $parameters = ['domain' => 'results', 'slug' => 'released-online'];
    $template = CommunicationTemplate::query()->where($parameters)->firstOrFail();

    $this->actingAs($this->actor)
        ->get('/setup/notifications/results/templates/released-online/edit')
        ->assertOk()
        ->assertInertia(fn ($page) => $page->component('Setup/Notifications/Templates/Message')
            ->where('family.supports_pdf', true)
            ->where('read_only', false)
            ->where('state.document_revision_id', null)
            ->missing('state.scores_rows'));

    $this->postJson(route('setup.notifications.templates.preview', $parameters), [
        'base_version' => $template->active_version, 'family_revision' => $template->family_revision,
        'lock_version' => null, 'view' => 'print', 'output_sha256' => str_repeat('a', 64),
    ])->assertUnprocessable();
    $this->postJson('/setup/notifications/results/templates/released-online/import', [])->assertNotFound();
    $this->postJson('/setup/notifications/results/templates/released-online/import-review', [])->assertNotFound();

    $this->get(route('setup.notifications.templates.document.edit', $parameters))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->component('Setup/Notifications/Templates/PhysicalDocument'));
    $workspace = $this->getJson(route('setup.notifications.templates.document.editor-state', $parameters))
        ->assertOk()->json();
    expect($workspace['family']['slug'])->toBe('released-online')
        ->and($workspace['workspace'])->toHaveKeys(['draft', 'ready_versions', 'verified_images'])
        ->and($workspace['endpoints'])->toHaveKey('version')
        ->and($template->fresh()->family_revision)->toBe($template->family_revision);
});

test('legacy message-only conversion retains inactive print sources rich channels and provenance across saves', function (): void {
    $template = CommunicationTemplate::query()->where('domain', 'results')->where('slug', 'released-online')->firstOrFail();
    $legacy = $template->draft()->create([
        'base_version' => $template->active_version, 'lock_version' => 1, 'stage' => 2,
        'content_schema_version' => 'notice-content-2', 'document_mode' => 'none',
        'document_asset_id' => null, 'paper_size' => null, 'orientation' => null,
        'builder_json' => null,
        'document_source_drafts' => [
            'docx' => ['document_asset_id' => 'legacy-private-docx', 'paper_size' => 'letter'],
            'builder' => ['builder_json' => ['components' => [['type' => 'text', 'props' => ['text' => 'inactive print']]]]],
        ],
        'import_provenance' => [
            'source_asset_id' => 'legacy-email-import', 'source_sha256' => str_repeat('b', 64),
            'report_id' => 'legacy-report', 'report_sha256' => str_repeat('c', 64),
            'review_acknowledgment' => ['actor_id' => (string) $this->actor->id],
        ],
        'subject' => 'Old rich subject', 'body' => 'Old caption', 'action_label' => 'Open portal',
        'action_route' => 'applicant.portal',
        'email_blueprint' => ['schema_version' => 'notice-email-1', 'layout' => 'flow',
            'blocks' => [['type' => 'paragraph', 'runs' => [['text' => 'Old rich email body']]]]],
        'email_subject' => 'Original channel subject',
        'email_body' => '<p>Original channel HTML</p>',
        'in_app_title' => 'Original portal title',
        'in_app_body' => 'Original portal content',
    ]);
    $sourceDraftId = (string) $legacy->id;
    $newState = [
        'subject' => 'Updated message title', 'body' => 'Updated Portal caption',
        'action_label' => 'Open portal', 'action_route' => 'applicant.portal',
        'document_revision_id' => null,
        'email_blueprint' => ['schema_version' => 'notice-email-1', 'layout' => 'flow',
            'blocks' => [['type' => 'paragraph', 'runs' => [['text' => 'Updated rich email']]]]],
    ];
    $first = $this->actingAs($this->actor)->putJson(route('setup.notifications.templates.drafts.update', [
        'domain' => 'results', 'slug' => 'released-online', 'draft' => $sourceDraftId,
    ]), [
        'state' => $newState, 'base_version' => $template->active_version,
        'family_revision' => $template->family_revision, 'lock_version' => 1, 'idempotency_key' => (string) Str::uuid(),
    ])->assertOk()->json();
    $snapshot = CommunicationTemplateReceipt::query()->where('institution_id', $template->institution_id)
        ->where('domain', 'results')->where('canonical_slug', 'released-online')
        ->where('operation', 'legacy_draft_snapshot')->where('idempotency_key', 'legacy-draft-'.$sourceDraftId)
        ->firstOrFail();

    $second = $this->putJson($first['draft']['endpoints']['update'], [
        'state' => [...$newState, 'body' => 'Second Portal caption'],
        'base_version' => $template->active_version, 'family_revision' => $first['family_revision'],
        'lock_version' => $first['draft']['lock_version'], 'idempotency_key' => (string) Str::uuid(),
    ])->assertOk()->json();
    $snapshot->refresh();

    expect($snapshot->response_payload['source'])->toMatchArray([
        'draft_id' => $sourceDraftId,
        'document_source_drafts' => $legacy->document_source_drafts,
        'import_provenance' => $legacy->import_provenance,
        'email_blueprint' => $legacy->email_blueprint,
        'email_subject' => 'Original channel subject',
        'email_body' => '<p>Original channel HTML</p>',
        'in_app_title' => 'Original portal title',
        'in_app_body' => 'Original portal content',
    ])->and($snapshot->payload_sha256)->toBe(CommunicationTemplatePayload::hash($snapshot->response_payload))
        ->and($second['draft']['state']['body'])->toBe('Second Portal caption')
        ->and($second['draft']['state']['document_revision_id'])->toBeNull()
        ->and($template->documentDraft()->exists())->toBeFalse();
});
