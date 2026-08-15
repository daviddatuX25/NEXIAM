<?php

use App\Ai\Adapters\Local\LocalKnowledgeIndexPort;
use App\Ai\Contracts\KnowledgeIndexPort;
use App\Ai\Knowledge\KnowledgeLifecycle;
use App\Ai\Knowledge\PublicationDigest;
use App\Models\Ai\KnowledgeCitationFixture;
use App\Models\Ai\KnowledgeSourceVersion;
use App\Models\User;
use Illuminate\Support\Facades\File;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function (): void {
    config(['nexi.process_inline' => true]);
});

test('registrar admin and guidance counselor one-step publish; clerks draft only', function (): void {
    seedE2Foundation();
    $guidance = User::query()->where('email', 'guidance.counselor@nexiam.test')->firstOrFail();
    $registrar = User::query()->where('email', 'registrar.admin@nexiam.test')->firstOrFail();
    $clerk = User::query()->where('email', 'registrar.clerk@nexiam.test')->firstOrFail();

    $this->actingAs($guidance)
        ->post(route('knowledge.store'), [
            'title' => 'Counselor one-step policy',
            'body_text' => 'Guidance counselors publish institutional policy in one create step.',
        ])
        ->assertRedirect();

    $version = KnowledgeSourceVersion::query()
        ->where('title', 'Counselor one-step policy')
        ->latest('id')
        ->firstOrFail();
    expect($version->status)->toBe(KnowledgeSourceVersion::STATUS_ACTIVE);

    $this->actingAs($clerk)
        ->post(route('knowledge.store'), [
            'title' => 'Clerk draft needs approval',
            'body_text' => 'Clerks may draft knowledge but cannot approve or publish alone.',
        ])
        ->assertRedirect();

    $clerkVersion = KnowledgeSourceVersion::query()
        ->where('title', 'Clerk draft needs approval')
        ->latest('id')
        ->firstOrFail();
    expect($clerkVersion->status)->toBe(KnowledgeSourceVersion::STATUS_REVIEW_REQUIRED);

    $this->actingAs($clerk)
        ->post(route('knowledge.approve', $clerkVersion->knowledge_source_id))
        ->assertForbidden();

    $this->actingAs($registrar)
        ->post(route('knowledge.approve', $clerkVersion->knowledge_source_id))
        ->assertRedirect();
    expect($clerkVersion->fresh()->status)->toBe(KnowledgeSourceVersion::STATUS_APPROVED);

    $this->actingAs($registrar)
        ->post(route('knowledge.publish', $clerkVersion->knowledge_source_id))
        ->assertRedirect();
    expect($clerkVersion->fresh()->status)->toBe(KnowledgeSourceVersion::STATUS_ACTIVE);

    $this->actingAs($registrar)
        ->post(route('knowledge.store'), [
            'title' => 'Registrar one-step policy',
            'body_text' => 'Registrar administrators publish institutional policy in one create step.',
        ])
        ->assertRedirect();

    $registrarVersion = KnowledgeSourceVersion::query()
        ->where('title', 'Registrar one-step policy')
        ->latest('id')
        ->firstOrFail();
    expect($registrarVersion->status)->toBe(KnowledgeSourceVersion::STATUS_ACTIVE);
});

test('admin one-step publish then revoke denies retrieve', function (): void {
    seedE2Foundation();
    $registrar = User::query()->where('email', 'registrar.admin@nexiam.test')->firstOrFail();
    /** @var LocalKnowledgeIndexPort $index */
    $index = app(KnowledgeIndexPort::class);

    $this->actingAs($registrar)
        ->post(route('knowledge.store'), [
            'title' => 'Quota policy',
            'body_text' => 'Program quota ceilings are enforced before release consultation.',
        ])
        ->assertRedirect();

    $version = KnowledgeSourceVersion::query()
        ->where('title', 'Quota policy')
        ->latest('id')
        ->firstOrFail();
    expect($version->status)->toBe(KnowledgeSourceVersion::STATUS_ACTIVE)
        ->and($index->retrieve('quota'))->not->toBeEmpty();

    $this->actingAs($registrar)
        ->post(route('knowledge.revoke', $version->knowledge_source_id), [
            'reason' => 'stale policy',
        ])
        ->assertRedirect();

    expect($version->fresh()->status)->toBe(KnowledgeSourceVersion::STATUS_REVOKED)
        ->and($index->retrieve('quota'))->toBeEmpty();
});

test('missing citation fixtures block approve and digest change blocks publish', function (): void {
    seedE2Foundation();
    $registrar = User::query()->where('email', 'registrar.admin@nexiam.test')->firstOrFail();
    $guidance = User::query()->where('email', 'guidance.counselor@nexiam.test')->firstOrFail();
    $lifecycle = app(KnowledgeLifecycle::class);

    $this->actingAs($registrar);
    $version = $lifecycle->createDraft($registrar, [
        'title' => 'Fixture gate policy',
        'body_text' => 'Guidance consultation requires complete assessment artifacts.',
    ]);
    $lifecycle->startExtraction($version);
    $version->refresh();
    expect($version->status)->toBe(KnowledgeSourceVersion::STATUS_REVIEW_REQUIRED);

    KnowledgeCitationFixture::query()->where('knowledge_source_version_id', $version->id)->delete();

    expect(fn () => $lifecycle->approve($guidance, $version->fresh()))
        ->toThrow(RuntimeException::class, 'fixtures_required');

    $version = $lifecycle->createDraft($registrar, [
        'title' => 'Digest gate policy',
        'body_text' => 'Release decisions must cite published institutional policy text.',
    ]);
    $lifecycle->startExtraction($version);
    $version->refresh();
    $lifecycle->approve($guidance, $version);
    $version->refresh();
    expect($version->status)->toBe(KnowledgeSourceVersion::STATUS_APPROVED);

    $fixture = $version->fixtures()->where('kind', 'citation')->firstOrFail();
    $fixture->forceFill(['query_text' => 'mutated-after-approve'])->save();

    expect(fn () => $lifecycle->publish($guidance, $version->fresh()))
        ->toThrow(RuntimeException::class, 'digest_invalidated');
});

test('knowledge index page is capability gated and lists sources', function (): void {
    seedE2Foundation();
    $guidance = User::query()->where('email', 'guidance.counselor@nexiam.test')->firstOrFail();
    $programHead = User::query()->where('email', 'program.head.bsn@nexiam.test')->firstOrFail();

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

test('application knowledge paths do not use Mixedbread as corpus authority', function (): void {
    $composer = json_decode(File::get(base_path('composer.json')), true, 512, JSON_THROW_ON_ERROR);
    $require = array_merge($composer['require'] ?? [], $composer['require-dev'] ?? []);

    expect(collect($require)->keys()->implode(' '))->not->toContain('mixedbread');

    expect(app(KnowledgeIndexPort::class))->toBeInstanceOf(LocalKnowledgeIndexPort::class)
        ->and(class_exists(PublicationDigest::class))->toBeTrue();
});
