<?php

use App\Ai\Adapters\Local\LocalKnowledgeIndexPort;
use App\Ai\Contracts\KnowledgeIndexPort;
use App\Ai\Knowledge\Actions\CreateKnowledgeDraft;
use App\Ai\Knowledge\KnowledgeLifecycle;
use App\Ai\Knowledge\PublicationDigest;
use App\Jobs\Ai\IndexKnowledgeVersionJob;
use App\Models\Ai\KnowledgeCitationFixture;
use App\Models\Ai\KnowledgeSourceVersion;
use App\Models\User;
use Illuminate\Support\Facades\File;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function (): void {
    config(['xiam.process_inline' => true]);
});

test('knowledge publication requires a different creator and approver', function (): void {
    seedE2Foundation();
    $registrar = User::query()->where('email', 'registrar.admin@nexiam.test')->firstOrFail();
    $clerk = User::query()->where('email', 'registrar.clerk@nexiam.test')->firstOrFail();
    $lifecycle = app(KnowledgeLifecycle::class);

    $response = $this->actingAs($registrar)
        ->post(route('knowledge.store'), [
            'title' => 'Registrar draft needs review',
            'body_text' => 'A knowledge source remains in review after extraction, including for approvers.',
        ])
        ->assertRedirect();

    $selfAuthoredVersion = KnowledgeSourceVersion::query()
        ->where('title', 'Registrar draft needs review')
        ->latest('id')
        ->firstOrFail();
    $response->assertRedirect(route('knowledge.show', $selfAuthoredVersion->knowledge_source_id));
    expect($selfAuthoredVersion->status)->toBe(KnowledgeSourceVersion::STATUS_REVIEW_REQUIRED)
        ->and($selfAuthoredVersion->approved_by)->toBeNull();
    $this->actingAs($registrar)
        ->get(route('knowledge.show', $selfAuthoredVersion->knowledge_source_id))
        ->assertInertia(fn (Assert $page) => $page
            ->component('Knowledge/Show')
            ->where('capabilities.approve_current_version', false));

    expect(fn () => $lifecycle->approve($registrar, $selfAuthoredVersion))
        ->toThrow(RuntimeException::class, 'creator_cannot_approve');
    expect(fn () => $lifecycle->publish($registrar, $selfAuthoredVersion))
        ->toThrow(RuntimeException::class, 'illegal_transition:review_required');

    $this->actingAs($registrar)
        ->post(route('knowledge.approve', $selfAuthoredVersion->knowledge_source_id), ['version_id' => $selfAuthoredVersion->id])
        ->assertRedirect()
        ->assertSessionHas('status', 'Approve denied: creator_cannot_approve');
    expect($selfAuthoredVersion->fresh()->status)->toBe(KnowledgeSourceVersion::STATUS_REVIEW_REQUIRED);

    $this->actingAs($clerk)
        ->post(route('knowledge.store'), [
            'title' => 'Clerk policy draft',
            'body_text' => 'Clerks can draft knowledge for review but cannot approve or publish it.',
        ])
        ->assertRedirect();

    $clerkVersion = KnowledgeSourceVersion::query()
        ->where('title', 'Clerk policy draft')
        ->latest('id')
        ->firstOrFail();
    expect($clerkVersion->status)->toBe(KnowledgeSourceVersion::STATUS_REVIEW_REQUIRED)
        ->and($clerkVersion->approved_by)->toBeNull();

    $this->actingAs($clerk)
        ->post(route('knowledge.approve', $clerkVersion->knowledge_source_id), ['version_id' => $clerkVersion->id])
        ->assertForbidden();

    $this->actingAs($registrar)
        ->get(route('knowledge.show', $clerkVersion->knowledge_source_id))
        ->assertInertia(fn (Assert $page) => $page
            ->component('Knowledge/Show')
            ->where('capabilities.approve_current_version', true));
    $this->actingAs($registrar)
        ->post(route('knowledge.approve', $clerkVersion->knowledge_source_id), ['version_id' => $clerkVersion->id])
        ->assertRedirect();
    expect($clerkVersion->fresh()->status)->toBe(KnowledgeSourceVersion::STATUS_APPROVED)
        ->and($clerkVersion->fresh()->approved_by)->toBe((string) $registrar->id);

    $this->actingAs($registrar)
        ->post(route('knowledge.publish', $clerkVersion->knowledge_source_id), ['version_id' => $clerkVersion->id])
        ->assertRedirect();
    expect($clerkVersion->fresh()->status)->toBe(KnowledgeSourceVersion::STATUS_ACTIVE);
});

test('publication cannot proceed without separate persisted approver metadata', function (): void {
    seedE2Foundation();
    $registrar = User::query()->where('email', 'registrar.admin@nexiam.test')->firstOrFail();
    $clerk = User::query()->where('email', 'registrar.clerk@nexiam.test')->firstOrFail();
    $lifecycle = app(KnowledgeLifecycle::class);

    $version = app(CreateKnowledgeDraft::class)->handle($clerk, [
        'title' => 'Approval metadata policy',
        'body_text' => 'Publication must rely on a separate approver recorded in the persisted version.',
    ]);
    $lifecycle->startExtraction($version);
    $version->refresh();

    expect(fn () => $lifecycle->publish($registrar, $version))
        ->toThrow(RuntimeException::class, 'illegal_transition:review_required');

    $version->forceFill([
        'status' => KnowledgeSourceVersion::STATUS_APPROVED,
        'approved_by' => null,
    ])->save();
    expect(fn () => $lifecycle->publish($registrar, $version))
        ->toThrow(RuntimeException::class, 'approver_required');

    $version->forceFill([
        'approved_by' => (string) $clerk->id,
    ])->save();
    expect(fn () => $lifecycle->publish($registrar, $version))
        ->toThrow(RuntimeException::class, 'creator_cannot_approve');
});

test('approved knowledge publication then revoke denies retrieve', function (): void {
    seedE2Foundation();
    $registrar = User::query()->where('email', 'registrar.admin@nexiam.test')->firstOrFail();
    $clerk = User::query()->where('email', 'registrar.clerk@nexiam.test')->firstOrFail();
    /** @var LocalKnowledgeIndexPort $index */
    $index = app(KnowledgeIndexPort::class);

    $this->actingAs($clerk)
        ->post(route('knowledge.store'), [
            'title' => 'Quota policy',
            'body_text' => 'Program quota ceilings are enforced before release consultation.',
        ])
        ->assertRedirect();

    $version = KnowledgeSourceVersion::query()
        ->where('title', 'Quota policy')
        ->latest('id')
        ->firstOrFail();
    expect($version->status)->toBe(KnowledgeSourceVersion::STATUS_REVIEW_REQUIRED);

    $this->actingAs($registrar)
        ->post(route('knowledge.approve', $version->knowledge_source_id), ['version_id' => $version->id])
        ->assertRedirect();
    $this->actingAs($registrar)
        ->post(route('knowledge.publish', $version->knowledge_source_id), ['version_id' => $version->id])
        ->assertRedirect();

    $filters = [
        'actor_id' => (string) $registrar->id,
        'actor_type' => 'staff',
        'institution_id' => (int) $registrar->actorScope()->institutionId,
        'owning_office' => 'registrar',
        'audiences' => ['staff_only'],
        'classifications' => ['office_internal'],
        'purpose' => 'policy_inquiry',
        'source_classes' => ['institution_policy'],
    ];
    expect($version->fresh()->status)->toBe(KnowledgeSourceVersion::STATUS_ACTIVE)
        ->and($index->retrieve('quota', 5, $filters))->not->toBeEmpty();

    $this->actingAs($registrar)
        ->post(route('knowledge.revoke', $version->knowledge_source_id), [
            'version_id' => $version->id,
            'reason' => 'stale policy',
        ])
        ->assertRedirect();

    expect($version->fresh()->status)->toBe(KnowledgeSourceVersion::STATUS_REVOKED)
        ->and($index->retrieve('quota', 5, $filters))->toBeEmpty();
});

test('revoke before delayed indexing keeps the version terminal and out of retrieval', function (): void {
    seedE2Foundation();
    $registrar = User::query()->where('email', 'registrar.admin@nexiam.test')->firstOrFail();
    $clerk = User::query()->where('email', 'registrar.clerk@nexiam.test')->firstOrFail();
    $lifecycle = app(KnowledgeLifecycle::class);
    /** @var LocalKnowledgeIndexPort $index */
    $index = app(KnowledgeIndexPort::class);

    $version = app(CreateKnowledgeDraft::class)->handle($clerk, [
        'title' => 'Delayed indexing policy',
        'body_text' => 'This policy must stay revoked if its queued index job runs later.',
    ]);
    $lifecycle->startExtraction($version);
    $version->refresh();
    $lifecycle->approve($registrar, $version);
    $version->forceFill(['status' => KnowledgeSourceVersion::STATUS_INDEXING])->save();

    $filters = [
        'actor_id' => (string) $registrar->id,
        'actor_type' => 'staff',
        'institution_id' => (int) $registrar->actorScope()->institutionId,
        'owning_office' => 'registrar',
        'audiences' => ['staff_only'],
        'classifications' => ['office_internal'],
        'purpose' => 'policy_inquiry',
        'source_classes' => ['institution_policy'],
    ];

    $this->actingAs($registrar)
        ->post(route('knowledge.revoke', $version->knowledge_source_id), [
            'version_id' => $version->id,
            'reason' => 'Obsolete before indexing',
        ])
        ->assertRedirect();

    expect($version->fresh()->status)->toBe(KnowledgeSourceVersion::STATUS_REVOKED)
        ->and($index->retrieve('delayed', 5, $filters))->toBeEmpty();

    IndexKnowledgeVersionJob::dispatchSync($version->id);

    expect($version->fresh()->status)->toBe(KnowledgeSourceVersion::STATUS_REVOKED)
        ->and($index->retrieve('delayed', 5, $filters))->toBeEmpty();
});

test('index failed publication retries only with its recorded separate approver', function (): void {
    seedE2Foundation();
    $registrar = User::query()->where('email', 'registrar.admin@nexiam.test')->firstOrFail();
    $clerk = User::query()->where('email', 'registrar.clerk@nexiam.test')->firstOrFail();
    $lifecycle = app(KnowledgeLifecycle::class);

    $version = app(CreateKnowledgeDraft::class)->handle($clerk, [
        'title' => 'Retry policy',
        'body_text' => 'Index failures may be retried without losing the recorded approval separation.',
    ]);
    $lifecycle->startExtraction($version);
    $version->refresh();
    $lifecycle->approve($registrar, $version);

    $version->forceFill([
        'status' => KnowledgeSourceVersion::STATUS_INDEX_FAILED,
        'failure_reason' => 'index_failed',
    ])->save();

    $lifecycle->publish($registrar, $version->fresh());

    expect($version->fresh()->status)->toBe(KnowledgeSourceVersion::STATUS_ACTIVE)
        ->and($version->fresh()->created_by)->toBe((string) $clerk->id)
        ->and($version->fresh()->approved_by)->toBe((string) $registrar->id);
});

test('missing citation fixtures block approve and digest change blocks publish', function (): void {
    seedE2Foundation();
    $registrar = User::query()->where('email', 'registrar.admin@nexiam.test')->firstOrFail();
    $clerk = User::query()->where('email', 'registrar.clerk@nexiam.test')->firstOrFail();
    $lifecycle = app(KnowledgeLifecycle::class);

    $this->actingAs($registrar);
    $version = app(CreateKnowledgeDraft::class)->handle($clerk, [
        'title' => 'Fixture gate policy',
        'body_text' => 'Guidance consultation requires complete assessment artifacts.',
    ]);
    $lifecycle->startExtraction($version);
    $version->refresh();
    expect($version->status)->toBe(KnowledgeSourceVersion::STATUS_REVIEW_REQUIRED);

    KnowledgeCitationFixture::query()->where('knowledge_source_version_id', $version->id)->delete();

    expect(fn () => $lifecycle->approve($registrar, $version->fresh()))
        ->toThrow(RuntimeException::class, 'fixtures_required');

    $version = app(CreateKnowledgeDraft::class)->handle($clerk, [
        'title' => 'Digest gate policy',
        'body_text' => 'Release decisions must cite published institutional policy text.',
    ]);
    $lifecycle->startExtraction($version);
    $version->refresh();
    $lifecycle->approve($registrar, $version);
    $version->refresh();
    expect($version->status)->toBe(KnowledgeSourceVersion::STATUS_APPROVED);

    $fixture = $version->fixtures()->where('kind', 'citation')->firstOrFail();
    $fixture->forceFill(['query_text' => 'mutated-after-approve'])->save();

    expect(fn () => $lifecycle->publish($registrar, $version->fresh()))
        ->toThrow(RuntimeException::class, 'digest_invalidated');
});

test('knowledge index page is capability gated and lists sources', function (): void {
    seedE2Foundation();
    $guidance = User::query()->where('email', 'guidance.counselor@nexiam.test')->firstOrFail();
    $programHead = User::query()->where('email', 'program.head.bsit@nexiam.test')->firstOrFail();

    $this->actingAs($programHead)
        ->get(route('knowledge.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Knowledge/Index')
            ->where('page_state', 'denied'));

    $this->actingAs($guidance)
        ->post(route('knowledge.store'), [
            'title' => 'Listed policy',
            'body_text' => 'Staff knowledge list shows lifecycle status for each source version.',
        ])
        ->assertRedirect();

    $this->actingAs($guidance)
        ->get(route('knowledge.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Knowledge/Index')
            ->where('page_state', 'ready')
            ->has('sources', 1));
});

test('typed FAQ remains office-only until authorized publication and owner revocation', function (): void {
    seedE2Foundation();
    $clerk = User::query()->where('email', 'registrar.clerk@nexiam.test')->firstOrFail();
    $registrar = User::query()->where('email', 'registrar.admin@nexiam.test')->firstOrFail();
    $guidance = User::query()->where('email', 'guidance.counselor@nexiam.test')->firstOrFail();

    $this->actingAs($clerk)->post(route('knowledge.store'), [
        'source_class' => 'public_faq',
        'title' => 'Verification procedure',
        'question' => 'How should Registrar staff verify an applicant?',
        'answer' => 'Registrar staff verify the identity and matching application record before proceeding.',
        'audience' => 'staff_only',
    ])->assertRedirect();

    $version = KnowledgeSourceVersion::query()->where('title', 'Verification procedure')->firstOrFail();
    expect($version->question)->toContain('Registrar staff')
        ->and($version->source->owning_office)->toBe('registrar')
        ->and($version->source->classification)->toBe('office_internal');
    $this->actingAs($guidance)->get(route('knowledge.show', $version->knowledge_source_id))->assertNotFound();
    $this->actingAs($guidance)->post(route('knowledge.approve', $version->knowledge_source_id), [
        'version_id' => $version->id,
    ])->assertNotFound();

    $this->actingAs($registrar)->post(route('knowledge.approve', $version->knowledge_source_id), [
        'version_id' => $version->id,
    ])->assertRedirect();
    $this->actingAs($registrar)->post(route('knowledge.publish', $version->knowledge_source_id), [
        'version_id' => $version->id,
    ])->assertRedirect();
    expect($version->fresh()->status)->toBe(KnowledgeSourceVersion::STATUS_ACTIVE);
    $this->actingAs($guidance)->get(route('knowledge.show', $version->knowledge_source_id))->assertNotFound();
    $this->actingAs($registrar)->post(route('knowledge.revoke', $version->knowledge_source_id), [
        'version_id' => $version->id,
        'reason' => 'Procedure replaced',
    ])->assertRedirect();
    expect($version->fresh()->status)->toBe(KnowledgeSourceVersion::STATUS_REVOKED);
});

test('cross-office FAQ requires independent office heads on the exact version', function (): void {
    seedE2Foundation();
    $clerk = User::query()->where('email', 'registrar.clerk@nexiam.test')->firstOrFail();
    $registrar = User::query()->where('email', 'registrar.admin@nexiam.test')->firstOrFail();
    $guidance = User::query()->where('email', 'guidance.counselor@nexiam.test')->firstOrFail();
    $guidanceClerk = User::query()->where('email', 'guidance.clerk@nexiam.test')->firstOrFail();

    $this->actingAs($clerk)->post(route('knowledge.store'), [
        'source_class' => 'public_faq',
        'title' => 'Shared examination handoff',
        'question' => 'Who records an examination handoff?',
        'answer' => 'The responsible staff record the handoff through their authorized office workflow.',
        'audience' => 'staff_only',
    ])->assertRedirect();
    $version = KnowledgeSourceVersion::query()->where('title', 'Shared examination handoff')->firstOrFail();
    $payload = ['version_id' => $version->id];

    $this->actingAs($clerk)->post(route('knowledge.cross-office', $version->knowledge_source_id), $payload)->assertForbidden();
    $this->actingAs($registrar)->post(route('knowledge.cross-office', $version->knowledge_source_id), $payload)->assertRedirect();
    expect($version->source->fresh()->audience)->toBe('cross_office_staff');
    $this->actingAs($guidanceClerk)->get(route('knowledge.show', $version->knowledge_source_id))->assertNotFound();

    $this->actingAs($registrar)->post(route('knowledge.approve', $version->knowledge_source_id), $payload)->assertRedirect();
    expect($version->fresh()->status)->toBe(KnowledgeSourceVersion::STATUS_REVIEW_REQUIRED);
    $this->actingAs($registrar)->post(route('knowledge.publish', $version->knowledge_source_id), $payload)->assertRedirect()
        ->assertSessionHas('status', 'Publish denied: illegal_transition:review_required');
    $this->actingAs($guidance)->post(route('knowledge.approve', $version->knowledge_source_id), $payload)->assertRedirect();
    expect($version->fresh()->status)->toBe(KnowledgeSourceVersion::STATUS_APPROVED)
        ->and($version->officeApprovals()->count())->toBe(2);
    $this->actingAs($registrar)->post(route('knowledge.publish', $version->knowledge_source_id), $payload)->assertRedirect();
    expect($version->fresh()->status)->toBe(KnowledgeSourceVersion::STATUS_ACTIVE);
    $this->actingAs($guidanceClerk)->get(route('knowledge.show', $version->knowledge_source_id))->assertOk();
    $this->actingAs($guidanceClerk)->post(route('knowledge.revoke', $version->knowledge_source_id), [
        ...$payload,
        'reason' => 'Must remain published',
    ])->assertForbidden();
});

test('authenticated public FAQ is readable across offices only after publication', function (): void {
    seedE2Foundation();
    $clerk = User::query()->where('email', 'registrar.clerk@nexiam.test')->firstOrFail();
    $registrar = User::query()->where('email', 'registrar.admin@nexiam.test')->firstOrFail();
    $guidance = User::query()->where('email', 'guidance.counselor@nexiam.test')->firstOrFail();
    $index = app(KnowledgeIndexPort::class);

    $this->actingAs($clerk)->post(route('knowledge.store'), [
        'source_class' => 'public_faq',
        'title' => 'Admissions contact',
        'question' => 'Where may I ask about enrollment?',
        'answer' => 'Contact the Registrar office through the authenticated portal for enrollment guidance.',
        'audience' => 'staff_and_applicant_inquiry',
    ])->assertRedirect();
    $version = KnowledgeSourceVersion::query()->where('title', 'Admissions contact')->firstOrFail();
    $scope = [
        'actor_id' => (string) $guidance->id,
        'actor_type' => 'staff',
        'institution_id' => (int) $guidance->actorScope()->institutionId,
        'owning_office' => 'guidance',
        'audiences' => ['staff_and_applicant_inquiry'],
        'classifications' => ['public_institutional'],
        'purpose' => 'policy_inquiry',
        'source_classes' => ['public_faq'],
    ];

    $this->actingAs($guidance)->get(route('knowledge.show', $version->knowledge_source_id))->assertNotFound();
    expect($index->retrieve('enrollment', 5, $scope))->toBeEmpty();
    $this->actingAs($registrar)->post(route('knowledge.approve', $version->knowledge_source_id), [
        'version_id' => $version->id,
    ])->assertRedirect();
    expect($index->retrieve('enrollment', 5, $scope))->toBeEmpty();
    $this->actingAs($registrar)->post(route('knowledge.publish', $version->knowledge_source_id), [
        'version_id' => $version->id,
    ])->assertRedirect();

    $this->actingAs($guidance)->get(route('knowledge.show', $version->knowledge_source_id))->assertOk();
    expect($index->retrieve('enrollment', 5, $scope))->not->toBeEmpty()
        ->and($index->retrieve('enrollment', 5, [
            ...$scope,
            'actor_type' => 'applicant',
        ]))->toBeEmpty()
        ->and($index->retrieve('enrollment', 5, [
            ...$scope,
            'owning_office' => 'registrar',
        ]))->toBeEmpty();
    $this->actingAs($guidance)->post(route('knowledge.revoke', $version->knowledge_source_id), [
        'version_id' => $version->id,
        'reason' => 'Outdated',
    ])->assertNotFound();
    $this->actingAs($registrar)->post(route('knowledge.revoke', $version->knowledge_source_id), [
        'version_id' => $version->id,
        'reason' => 'Outdated',
    ])->assertRedirect();
    expect($index->retrieve('enrollment', 5, $scope))->toBeEmpty();
});

test('application knowledge paths do not use Mixedbread as corpus authority', function (): void {
    $composer = json_decode(File::get(base_path('composer.json')), true, 512, JSON_THROW_ON_ERROR);
    $require = array_merge($composer['require'] ?? [], $composer['require-dev'] ?? []);

    expect(collect($require)->keys()->implode(' '))->not->toContain('mixedbread');

    expect(app(KnowledgeIndexPort::class))->toBeInstanceOf(LocalKnowledgeIndexPort::class)
        ->and(class_exists(PublicationDigest::class))->toBeTrue();
});
