<?php

use App\Ai\Contracts\KnowledgeIndexPort;
use App\Ai\Knowledge\KnowledgeLifecycle;
use App\Ai\Knowledge\KnowledgeRouter;
use App\Models\Ai\KnowledgeSourceVersion;
use App\Models\Ai\NexiRun;
use App\Models\Ai\NexiRunEvent;
use App\Models\User;

beforeEach(function (): void {
    config(['nexi.process_inline' => true, 'nexi.ai.gateway' => 'fake']);
});

test('policy inquiry cites active knowledge and refuses after revoke', function (): void {
    seedE2Foundation();
    $registrar = User::query()->where('email', 'registrar.admin@nexiam.test')->firstOrFail();
    $guidance = User::query()->where('email', 'guidance.counselor@nexiam.test')->firstOrFail();
    $lifecycle = app(KnowledgeLifecycle::class);
    $index = app(KnowledgeIndexPort::class);

    $this->actingAs($registrar);
    $version = $lifecycle->createDraft($registrar, [
        'title' => 'Quota ceiling policy',
        'body_text' => 'Program quota ceilings are enforced before release consultation for every applicant cohort.',
    ]);
    $lifecycle->startExtraction($version);
    $version->refresh();
    $lifecycle->approve($guidance, $version);
    $lifecycle->publish($guidance, $version->fresh());
    $version->refresh();
    expect($version->status)->toBe(KnowledgeSourceVersion::STATUS_ACTIVE)
        ->and($index->retrieve('quota ceilings', 5, [
            'audiences' => ['staff_and_applicant_inquiry', 'staff_only'],
            'purpose' => 'policy_inquiry',
        ]))->not->toBeEmpty();

    $this->actingAs($guidance)
        ->postJson(route('nexi.turns.store'), [
            'message' => 'Tell me about program quota ceilings before release',
            'idempotency_key' => 'policy-cite-1',
        ])
        ->assertAccepted();

    $run1 = NexiRun::query()->latest('created_at')->firstOrFail();
    $message = NexiRunEvent::query()->where('run_id', $run1->id)->where('type', 'assistant.message')->first();
    $retrieval = NexiRunEvent::query()->where('run_id', $run1->id)->where('type', 'knowledge.retrieval')->first();
    expect($retrieval?->payload['outcome'] ?? null)->toBe('support')
        ->and($message)->not->toBeNull()
        ->and($message->payload['citations'] ?? [])->not->toBeEmpty()
        ->and($message->payload['content'] ?? '')->toContain('Quota');

    $lifecycle->revoke($guidance, $version->fresh(), 'stale');
    expect($index->retrieve('quota ceilings', 5, [
        'audiences' => ['staff_and_applicant_inquiry', 'staff_only'],
        'purpose' => 'policy_inquiry',
    ]))->toBeEmpty();

    $this->actingAs($guidance)
        ->postJson(route('nexi.turns.store'), [
            'message' => 'Tell me about program quota ceilings before release again',
            'idempotency_key' => 'policy-cite-2',
        ])
        ->assertAccepted();

    $run2 = NexiRun::query()->where('id', '!=', $run1->id)->latest('created_at')->firstOrFail();
    $refusal = NexiRunEvent::query()->where('run_id', $run2->id)->where('type', 'assistant.refusal')->first();
    $secondMessage = NexiRunEvent::query()->where('run_id', $run2->id)->where('type', 'assistant.message')->first();
    $retrieval2 = NexiRunEvent::query()->where('run_id', $run2->id)->where('type', 'knowledge.retrieval')->first();

    expect($retrieval2?->payload['outcome'] ?? null)->toBe('no_support')
        ->and($refusal)->not->toBeNull()
        ->and($secondMessage->payload['citations'] ?? [])->toBe([]);
});


test('index includes title so program-name questions can match', function (): void {
    seedE2Foundation();
    $registrar = User::query()->where('email', 'registrar.admin@nexiam.test')->firstOrFail();
    $guidance = User::query()->where('email', 'guidance.counselor@nexiam.test')->firstOrFail();
    $lifecycle = app(KnowledgeLifecycle::class);
    $index = app(KnowledgeIndexPort::class);

    $this->actingAs($registrar);
    $version = $lifecycle->createDraft($registrar, [
        'title' => 'BSMATH Requirements',
        'body_text' => 'Must know basic operations for program entry.',
    ]);
    $lifecycle->startExtraction($version);
    $version->refresh();
    $lifecycle->approve($guidance, $version);
    $lifecycle->publish($guidance, $version->fresh());

    expect($index->retrieve('BSMATH', 5, [
        'audiences' => ['staff_and_applicant_inquiry', 'staff_only'],
        'purpose' => 'policy_inquiry',
    ]))->not->toBeEmpty();
});

test('knowledge router returns no_support for unrelated staff query', function (): void {
    seedE2Foundation();
    $router = app(KnowledgeRouter::class);
    $result = $router->resolvePolicyInquiry('zzzz-unrelated-xyz-no-corpus', [
        'actor_type' => 'staff',
        'institution_id' => 1,
        'purpose' => 'policy_inquiry',
    ]);

    expect($result->hasSupport())->toBeFalse()
        ->and($result->outcome)->toBe('no_support')
        ->and($result->hits)->toBeEmpty();
});
