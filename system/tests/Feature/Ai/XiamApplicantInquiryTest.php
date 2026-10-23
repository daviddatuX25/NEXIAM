<?php

use App\Admission\Actions\AuthenticateApplicantWithAccessKey;
use App\Ai\Adapters\LaravelAi\LaravelAiGateway;
use App\Ai\Adapters\LaravelAi\XiamAgent;
use App\Ai\Contracts\AiGateway;
use App\Ai\Knowledge\Actions\CreateKnowledgeDraft;
use App\Ai\Knowledge\KnowledgeLifecycle;
use App\Models\Ai\XiamConversation;
use App\Models\Ai\XiamRun;
use App\Models\Ai\XiamRunEvent;
use App\Models\Examination\ExamSession;
use App\Models\Foundation\AcademicCycle;
use App\Models\Foundation\Room;
use App\Models\User;

beforeEach(function (): void {
    config(['xiam.process_inline' => true, 'xiam.ai.gateway' => 'fake']);
});

function loginApplicant(string $accessToken, string $birthdate = '2001-01-01'): User
{
    $user = app(AuthenticateApplicantWithAccessKey::class)->handle($accessToken, $birthdate);
    test()->actingAs($user);

    return $user;
}

test('applicant corpus retrieval remains unavailable for policy and internal memo questions', function (): void {
    seedE2Foundation();
    $registrar = User::query()->where('email', 'registrar.admin@nexiam.test')->firstOrFail();
    $clerk = User::query()->where('email', 'registrar.clerk@nexiam.test')->firstOrFail();
    $lifecycle = app(KnowledgeLifecycle::class);

    $this->actingAs($registrar);
    $shared = app(CreateKnowledgeDraft::class)->handle($clerk, [
        'title' => 'Applicant intended policy',
        'body_text' => 'Portal applicants may ask about enrollment attestation requirements after acceptance.',
        'audience' => 'staff_and_applicant_inquiry',
    ]);
    $lifecycle->startExtraction($shared);
    $shared->refresh();
    $lifecycle->approve($registrar, $shared);
    $lifecycle->publish($registrar, $shared->fresh());

    $staffOnly = app(CreateKnowledgeDraft::class)->handle($clerk, [
        'title' => 'Internal registrar only memo',
        'body_text' => 'Registrar internal escalation ladder is confidential staff guidance only.',
    ]);
    $lifecycle->startExtraction($staffOnly);
    $staffOnly->refresh();
    $lifecycle->approve($registrar, $staffOnly);
    $lifecycle->publish($registrar, $staffOnly->fresh());

    $applicant = submitSyntheticApplicant('ai.app.cite@example.test', 'CitePerson');
    loginApplicant($applicant['access_token']);

    config(['xiam.ai.gateway' => 'laravel']);
    app()->forgetInstance(AiGateway::class);
    app()->instance(AiGateway::class, new LaravelAiGateway);
    XiamAgent::fake(static fn (): string => 'I cannot retrieve the institutional policy corpus from the applicant assistant.');

    $response = $this->postJson(route('xiam.turns.store'), [
        'message' => 'Tell me about enrollment attestation requirements',
        'idempotency_key' => 'app-cite-1',
        'page_pack_key' => 'applicant.schedule@1',
        'page_pack_version' => 1,
    ])->assertAccepted();

    $run = XiamRun::query()->findOrFail($response->json('run_id'));
    $conversation = XiamConversation::query()->findOrFail($run->conversation_id);
    $events = XiamRunEvent::query()->where('run_id', $run->id)->get();
    $message = $events->firstWhere('type', 'assistant.message');

    expect($conversation->actor_type)->toBe('applicant')
        ->and($events->firstWhere('type', 'knowledge.retrieval'))->toBeNull()
        ->and($events->firstWhere('type', 'tool.call'))->toBeNull()
        ->and($events->firstWhere('type', 'assistant.citations'))->toBeNull()
        ->and($message?->payload['content'] ?? '')->not->toContain('Applicant intended policy', 'Internal registrar only memo', 'enrollment attestation requirements');

    XiamAgent::fake(static fn (): string => 'I cannot retrieve staff-only registrar guidance from the applicant assistant.');
    $response2 = $this->postJson(route('xiam.turns.store'), [
        'message' => 'What is the registrar internal escalation ladder?',
        'idempotency_key' => 'app-staffonly-1',
        'page_pack_key' => 'applicant.schedule@1',
        'page_pack_version' => 1,
    ])->assertAccepted();

    $run2 = XiamRun::query()->findOrFail($response2->json('run_id'));
    $events2 = XiamRunEvent::query()->where('run_id', $run2->id)->get();
    $message2 = $events2->firstWhere('type', 'assistant.message');
    expect($events2->firstWhere('type', 'knowledge.retrieval'))->toBeNull()
        ->and($events2->firstWhere('type', 'tool.call'))->toBeNull()
        ->and($events2->firstWhere('type', 'assistant.citations'))->toBeNull()
        ->and($message2?->payload['content'] ?? '')->not->toContain('confidential staff guidance', 'escalation ladder');
});

test('applicant inquiry cannot disclose institution-level examination or admission aggregates', function (): void {
    seedE2Foundation();
    $cycle = AcademicCycle::query()->where('code', 'SYN-CYCLE-2026')->firstOrFail();
    $room = Room::query()->where('code', 'RM-101')->firstOrFail();

    ExamSession::query()->create([
        'academic_cycle_id' => $cycle->id,
        'room_id' => $room->id,
        'code' => 'EX-AGG-1',
        'starts_at' => now()->addDays(3),
        'effective_end_at' => now()->addDays(3)->addHours(2),
        'status' => ExamSession::STATUS_PUBLISHED,
        'version' => 1,
    ]);

    $firstApplicant = submitSyntheticApplicant('ai.app.agg1@example.test', 'AggOne');
    submitSyntheticApplicant('ai.app.agg2@example.test', 'AggTwo');
    loginApplicant($firstApplicant['access_token']);

    config(['xiam.ai.gateway' => 'laravel']);
    app()->forgetInstance(AiGateway::class);
    app()->instance(AiGateway::class, new LaravelAiGateway);
    XiamAgent::fake(static fn (): string => 'I cannot retrieve institution-wide occupancy or admission totals through the applicant assistant.');

    $response = $this->postJson(route('xiam.turns.store'), [
        'message' => 'What is the room occupancy and admission quota across the institution?',
        'idempotency_key' => 'app-agg-1',
        'page_pack_key' => 'applicant.schedule@1',
        'page_pack_version' => 1,
    ])->assertAccepted();

    $run = XiamRun::query()->findOrFail($response->json('run_id'));
    $events = XiamRunEvent::query()->where('run_id', $run->id)->get();
    $message = $events->firstWhere('type', 'assistant.message');

    expect($events->firstWhere('type', 'knowledge.retrieval'))->toBeNull()
        ->and($events->firstWhere('type', 'tool.call'))->toBeNull()
        ->and($events->firstWhere('type', 'assistant.citations'))->toBeNull()
        ->and($message?->payload['content'] ?? '')->not->toContain('2 applicants', 'EX-AGG-1', 'room occupancy is 0');
});

test('applicant unreleased score request receives a limitation without score or result disclosure', function (): void {
    seedE2Foundation();
    $applicant = submitSyntheticApplicant('ai.app.status@example.test', 'StatusPerson');
    loginApplicant($applicant['access_token']);

    config(['xiam.ai.gateway' => 'laravel']);
    app()->forgetInstance(AiGateway::class);
    app()->instance(AiGateway::class, new LaravelAiGateway);
    XiamAgent::fake(static fn (): string => 'I cannot disclose an unreleased exam score or result.');

    $response = $this->postJson(route('xiam.turns.store'), [
        'message' => 'What is my exam score result?',
        'idempotency_key' => 'app-score-1',
        'page_pack_key' => 'applicant.schedule@1',
        'page_pack_version' => 1,
    ])->assertAccepted();

    $run = XiamRun::query()->findOrFail($response->json('run_id'));
    $events = XiamRunEvent::query()->where('run_id', $run->id)->get();
    $message = $events->firstWhere('type', 'assistant.message');
    expect($message?->payload['content'] ?? '')->toContain('cannot disclose')
        ->and($message?->payload['content'] ?? '')->not->toMatch('/\\b\\d+(?:\\.\\d+)?\\b/')
        ->and($events->firstWhere('type', 'tool.call'))->toBeNull()
        ->and($events->firstWhere('type', 'assistant.citations'))->toBeNull();
});

test('cross-applicant conversation snapshot is denied and clear segments epoch', function (): void {
    seedE2Foundation();
    $a = submitSyntheticApplicant('ai.app.a@example.test', 'Alpha');
    $b = submitSyntheticApplicant('ai.app.b@example.test', 'Beta');

    loginApplicant($a['access_token']);

    $this->postJson(route('xiam.turns.store'), [
        'message' => 'What is my application status?',
        'idempotency_key' => 'app-iso-a1',
        'page_pack_key' => 'applicant.schedule@1',
        'page_pack_version' => 1,
    ])->assertAccepted();

    $conversationId = XiamConversation::query()->where('actor_type', 'applicant')->latest('created_at')->value('id');
    expect($conversationId)->not->toBeNull();

    $clear = $this->postJson(route('xiam.conversations.clear'))->assertOk()->json();
    expect($clear['cleared'] ?? false)->toBeTrue()
        ->and($clear['conversation_id'] ?? null)->not->toBe($conversationId);

    $closed = XiamConversation::query()->findOrFail($conversationId);
    expect($closed->status)->toBe('closed');

    loginApplicant($b['access_token']);

    $this->getJson(route('xiam.conversations.snapshot', $conversationId))
        ->assertForbidden();
});
