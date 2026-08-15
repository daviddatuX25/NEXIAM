<?php

use App\Admission\Actions\AuthenticateApplicantWithAccessKey;
use App\Ai\Knowledge\KnowledgeLifecycle;
use App\Models\Ai\NexiConversation;
use App\Models\Ai\NexiRun;
use App\Models\Ai\NexiRunEvent;
use App\Models\User;

beforeEach(function (): void {
    config(['nexi.process_inline' => true, 'nexi.ai.gateway' => 'fake']);
});

function loginApplicant(string $accessToken, string $birthdate = '2001-01-01'): User
{
    $user = app(AuthenticateApplicantWithAccessKey::class)->handle($accessToken, $birthdate);
    test()->actingAs($user);

    return $user;
}

test('applicant cites shared policy and is denied staff_only corpus', function (): void {
    seedE2Foundation();
    $registrar = User::query()->where('email', 'registrar.admin@nexiam.test')->firstOrFail();
    $lifecycle = app(KnowledgeLifecycle::class);

    $this->actingAs($registrar);
    $shared = $lifecycle->createDraft($registrar, [
        'title' => 'Applicant visible policy',
        'body_text' => 'Portal applicants may ask about enrollment attestation requirements after acceptance.',
    ]);
    $lifecycle->startExtraction($shared);
    $shared->refresh();
    $lifecycle->approve($registrar, $shared);
    $lifecycle->publish($registrar, $shared->fresh());

    $staffOnly = $lifecycle->createDraft($registrar, [
        'title' => 'Internal registrar only memo',
        'body_text' => 'Registrar internal escalation ladder is confidential staff guidance only.',
    ]);
    $lifecycle->startExtraction($staffOnly);
    $staffOnly->refresh();
    $staffOnly->source->forceFill(['audience' => 'staff_only'])->save();
    $lifecycle->approve($registrar, $staffOnly);
    $lifecycle->publish($registrar, $staffOnly->fresh());

    $applicant = submitSyntheticApplicant('ai.app.cite@example.test', 'CitePerson');
    loginApplicant($applicant['access_token']);

    $this->postJson(route('nexi.turns.store'), [
        'message' => 'Tell me about enrollment attestation requirements',
        'idempotency_key' => 'app-cite-1',
        'page_pack_key' => 'applicant.schedule@1',
        'page_pack_version' => 1,
    ])->assertAccepted();

    $run = NexiRun::query()->latest('created_at')->firstOrFail();
    $conversation = NexiConversation::query()->findOrFail($run->conversation_id);
    expect($conversation->actor_type)->toBe('applicant');

    $message = NexiRunEvent::query()->where('run_id', $run->id)->where('type', 'assistant.message')->first();
    $retrieval = NexiRunEvent::query()->where('run_id', $run->id)->where('type', 'knowledge.retrieval')->first();
    expect($retrieval?->payload['outcome'] ?? null)->toBe('support')
        ->and($message?->payload['content'] ?? '')->toContain('Applicant visible policy')
        ->and(collect($retrieval?->payload['hits'] ?? [])->pluck('source_title')->all())
        ->not->toContain('Internal registrar only memo');

    $this->postJson(route('nexi.turns.store'), [
        'message' => 'What is the registrar internal escalation ladder?',
        'idempotency_key' => 'app-staffonly-1',
        'page_pack_key' => 'applicant.schedule@1',
        'page_pack_version' => 1,
    ])->assertAccepted();

    $run2 = NexiRun::query()->where('id', '!=', $run->id)->latest('created_at')->firstOrFail();
    $retrieval2 = NexiRunEvent::query()->where('run_id', $run2->id)->where('type', 'knowledge.retrieval')->first();
    expect($retrieval2?->payload['outcome'] ?? null)->toBe('no_support');
});

test('applicant own-status answers and unreleased scores are refused', function (): void {
    seedE2Foundation();
    $applicant = submitSyntheticApplicant('ai.app.status@example.test', 'StatusPerson');
    loginApplicant($applicant['access_token']);

    $this->postJson(route('nexi.turns.store'), [
        'message' => 'What is my application status?',
        'idempotency_key' => 'app-status-1',
        'page_pack_key' => 'applicant.schedule@1',
        'page_pack_version' => 1,
    ])->assertAccepted();

    $run = NexiRun::query()->latest('created_at')->firstOrFail();
    $own = NexiRunEvent::query()->where('run_id', $run->id)->where('type', 'own_data.projection')->first();
    $message = NexiRunEvent::query()->where('run_id', $run->id)->where('type', 'assistant.message')->first();
    expect($own?->payload['available'] ?? false)->toBeTrue()
        ->and($own?->payload['relevant'] ?? false)->toBeTrue()
        ->and($message?->payload['content'] ?? '')->toContain('application status')
        ->and($own?->payload['results_released'] ?? true)->toBeFalse();

    $this->postJson(route('nexi.turns.store'), [
        'message' => 'What is my exam score result?',
        'idempotency_key' => 'app-score-1',
        'page_pack_key' => 'applicant.schedule@1',
        'page_pack_version' => 1,
    ])->assertAccepted();

    $run2 = NexiRun::query()->where('id', '!=', $run->id)->latest('created_at')->firstOrFail();
    $message2 = NexiRunEvent::query()->where('run_id', $run2->id)->where('type', 'assistant.message')->first();
    expect($message2?->payload['content'] ?? '')->toContain('not released');
});

test('cross-applicant conversation snapshot is denied and clear segments epoch', function (): void {
    seedE2Foundation();
    $a = submitSyntheticApplicant('ai.app.a@example.test', 'Alpha');
    $b = submitSyntheticApplicant('ai.app.b@example.test', 'Beta');

    loginApplicant($a['access_token']);

    $this->postJson(route('nexi.turns.store'), [
        'message' => 'What is my application status?',
        'idempotency_key' => 'app-iso-a1',
        'page_pack_key' => 'applicant.schedule@1',
        'page_pack_version' => 1,
    ])->assertAccepted();

    $conversationId = NexiConversation::query()->where('actor_type', 'applicant')->latest('created_at')->value('id');
    expect($conversationId)->not->toBeNull();

    $clear = $this->postJson(route('nexi.conversations.clear'))->assertOk()->json();
    expect($clear['cleared'] ?? false)->toBeTrue()
        ->and($clear['conversation_id'] ?? null)->not->toBe($conversationId);

    $closed = NexiConversation::query()->findOrFail($conversationId);
    expect($closed->status)->toBe('closed');

    loginApplicant($b['access_token']);

    $this->getJson(route('nexi.conversations.snapshot', $conversationId))
        ->assertForbidden();
});
