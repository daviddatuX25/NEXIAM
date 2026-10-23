<?php

declare(strict_types=1);

use App\Ai\Adapters\LaravelAi\LaravelAiGateway;
use App\Ai\Adapters\LaravelAi\XiamAgent;
use App\Ai\Adapters\LaravelAi\XiamProjectorRegistry;
use App\Ai\Context\InvocationContext;
use App\Ai\Contracts\AiGateway;
use App\Ai\Gateway\AssistantToolGateway;
use App\Ai\Knowledge\Actions\CreateKnowledgeDraft;
use App\Ai\Knowledge\KnowledgeLifecycle;
use App\Models\Ai\XiamRun;
use App\Models\Ai\XiamRunEvent;
use App\Models\ResultSheet\ResultSheetTemplate;
use App\Models\User;
use Illuminate\Support\Str;
use Laravel\Ai\Responses\Data\ToolCall;

beforeEach(function (): void {
    config(['xiam.process_inline' => true, 'xiam.ai.gateway' => 'fake', 'xiam.enabled' => true]);
});

test('staff conversation answers release readiness and result sheet questions with live operational evidence', function (): void {
    seedE2Foundation();
    ResultSheetTemplate::query()->delete();
    ResultSheetTemplate::query()->create([
        'name' => 'Official A4 Result Sheet',
        'mode' => 'html',
        'paper_size' => 'a4',
        'orientation' => 'portrait',
        'logical_unit' => 'full',
        'content' => '<div>Result</div>',
        'is_active' => true,
    ]);
    ResultSheetTemplate::query()->create([
        'name' => 'Legacy Draft Template',
        'mode' => 'html',
        'paper_size' => 'a4',
        'orientation' => 'portrait',
        'logical_unit' => 'full',
        'content' => '<div>Draft</div>',
        'is_active' => false,
    ]);

    $actor = User::query()->where('email', 'guidance.counselor@nexiam.test')->firstOrFail();
    $this->actingAs($actor);
    config(['xiam.ai.gateway' => 'laravel']);
    app()->forgetInstance(AiGateway::class);
    app()->instance(AiGateway::class, new LaravelAiGateway);
    XiamAgent::fake([
        new ToolCall('readiness-setup', 'setup_readiness_read_1', []),
        new ToolCall('readiness-templates', 'result_sheet_templates_list_1', []),
        new ToolCall('readiness-release', 'results_release_overview_1', []),
        'Release & Setup Readiness Summary: setup checks are available. Official A4 Result Sheet is configured.',
        new ToolCall('templates-access', 'result_sheet_templates_list_1', []),
        'Result sheet templates status: Official A4 Result Sheet is active.',
        new ToolCall('templates-count', 'result_sheet_templates_list_1', []),
        'There is 1 active template(s): Official A4 Result Sheet.',
    ]);

    $postTurn = function (string $message, string $idempotencyKey): XiamRun {
        $response = test()->postJson(route('xiam.turns.store'), [
            'message' => $message,
            'idempotency_key' => $idempotencyKey,
        ])->assertAccepted();

        return XiamRun::query()->findOrFail($response->json('run_id'));
    };
    $events = static fn (XiamRun $run) => XiamRunEvent::query()
        ->where('run_id', $run->id)
        ->orderBy('seq')
        ->get();
    $event = static fn (XiamRun $run, string $type): ?XiamRunEvent => $events($run)
        ->firstWhere('type', $type);
    $assertToolRead = static function (XiamRun $run, array $tools) use ($events): void {
        $runEvents = $events($run);
        $toolCalls = $runEvents->where('type', 'tool.call')->values();
        $toolResults = $runEvents->where('type', 'tool.result')->values();

        expect($toolCalls->pluck('payload.tool')->all())->toBe($tools)
            ->and($toolResults->pluck('payload.tool')->all())->toBe($tools)
            ->and($toolResults->pluck('payload.successful')->every(fn (bool $successful): bool => $successful))->toBeTrue();
    };
    $refusalCount = static fn (XiamRun $run): int => XiamRunEvent::query()
        ->where('run_id', $run->id)
        ->where('type', 'assistant.refusal')
        ->count();

    $readinessRun = $postTurn('are we ready for release?', 'model-first-operational-1');
    $readinessCitations = $event($readinessRun, 'assistant.citations')?->payload['citations'] ?? [];
    $readinessMessage = $event($readinessRun, 'assistant.message')?->payload['content'] ?? '';

    expect($readinessRun->fresh()->status)->toBe(XiamRun::STATUS_SUCCEEDED)
        ->and($refusalCount($readinessRun))->toBe(0)
        ->and($readinessMessage)->toContain('Release & Setup Readiness Summary', 'Official A4 Result Sheet')
        ->and(collect($readinessCitations)->pluck('locator'))->toContain(
            'setup.readiness.read@1',
            'result_sheet.templates.list@1',
            'results.release.overview@1',
        )
        ->and(collect($readinessCitations)->pluck('title'))->not->toContain('Synthetic applicant status FAQ');
    $assertToolRead($readinessRun, [
        'setup.readiness.read@1',
        'result_sheet.templates.list@1',
        'results.release.overview@1',
    ]);

    $accessRun = $postTurn('dont you have acces to the result sheets?', 'model-first-operational-2');
    $accessCitations = $event($accessRun, 'assistant.citations')?->payload['citations'] ?? [];
    $accessMessage = $event($accessRun, 'assistant.message')?->payload['content'] ?? '';
    expect($accessRun->fresh()->status)->toBe(XiamRun::STATUS_SUCCEEDED)
        ->and($refusalCount($accessRun))->toBe(0)
        ->and($accessMessage)->toContain('Result sheet templates status', 'Official A4 Result Sheet')
        ->and(collect($accessCitations)->pluck('locator'))->toContain('result_sheet.templates.list@1')
        ->and($accessMessage)->not->toContain('applicant portal', 'I do not have access to applicant result sheets');
    $assertToolRead($accessRun, ['result_sheet.templates.list@1']);

    $countRun = $postTurn('how many templates of result sheets are active?', 'model-first-operational-3');
    $countCitations = $event($countRun, 'assistant.citations')?->payload['citations'] ?? [];
    $countMessage = $event($countRun, 'assistant.message')?->payload['content'] ?? '';

    expect($countRun->fresh()->status)->toBe(XiamRun::STATUS_SUCCEEDED)
        ->and($refusalCount($countRun))->toBe(0)
        ->and($countMessage)->toContain('1 active template(s)', 'Official A4 Result Sheet')
        ->and(collect($countCitations)->pluck('locator'))->toContain('result_sheet.templates.list@1');
    $assertToolRead($countRun, ['result_sheet.templates.list@1']);
});

test('setup readiness tool projection preserves the complete nested readiness tree', function (): void {
    seedE2Foundation();

    $actor = User::query()->where('email', 'guidance.counselor@nexiam.test')->firstOrFail();
    $context = new InvocationContext(
        actorId: (string) $actor->id,
        actorType: 'staff',
        institutionId: (int) $actor->actorScope()->institutionId,
        surfaceProfile: 'staff',
        mode: 'assistant',
        contextEpoch: 'model-first-readiness-epoch',
        purpose: 'assistant_tool',
        correlationId: (string) Str::uuid(),
    );

    $result = app(AssistantToolGateway::class)->invoke($actor, $context, 'setup.readiness.read@1', []);
    $projected = app(XiamProjectorRegistry::class)->project('setup.readiness.read@1', $result->data);
    $groups = $projected['groups'] ?? [];

    expect($result->ok)->toBeTrue()
        ->and($projected)->toHaveKeys(['overall_percent', 'passed', 'total', 'blocking_count', 'ready', 'groups'])
        ->and($groups)->not->toBeEmpty()
        ->and($groups[0]['categories'] ?? [])->not->toBeEmpty()
        ->and($groups[0]['categories'][0]['checks'] ?? [])->not->toBeEmpty();
});

test('result template and release overview tools return accurate safe operational projections', function (): void {
    seedE2Foundation();
    ResultSheetTemplate::query()->delete();
    ResultSheetTemplate::query()->create([
        'name' => 'Official A4 Result Sheet',
        'mode' => 'html',
        'paper_size' => 'a4',
        'orientation' => 'portrait',
        'logical_unit' => 'full',
        'content' => '<div>Private template source</div>',
        'builder_json' => ['private' => 'builder payload'],
        'is_active' => true,
    ]);
    ResultSheetTemplate::query()->create([
        'name' => 'Legacy Draft Template',
        'mode' => 'html',
        'paper_size' => 'a4',
        'orientation' => 'portrait',
        'logical_unit' => 'full',
        'content' => '<div>Draft source</div>',
        'builder_json' => ['private' => 'draft payload'],
        'is_active' => false,
    ]);

    $actor = User::query()->where('email', 'guidance.counselor@nexiam.test')->firstOrFail();
    $context = new InvocationContext(
        actorId: (string) $actor->id,
        actorType: 'staff',
        institutionId: (int) $actor->actorScope()->institutionId,
        surfaceProfile: 'staff',
        mode: 'assistant',
        contextEpoch: 'model-first-projection-epoch',
        purpose: 'assistant_tool',
        correlationId: (string) Str::uuid(),
    );
    $gateway = app(AssistantToolGateway::class);
    $projectors = app(XiamProjectorRegistry::class);

    $templatesResult = $gateway->invoke($actor, $context, 'result_sheet.templates.list@1', []);
    $templates = $projectors->project('result_sheet.templates.list@1', $templatesResult->data);
    $releaseResult = $gateway->invoke($actor, $context, 'results.release.overview@1', []);
    $release = $projectors->project('results.release.overview@1', $releaseResult->data);

    expect($templatesResult->ok)->toBeTrue()
        ->and($templates['summary']['total_count'] ?? null)->toBe(2)
        ->and($templates['summary']['active_count'] ?? null)->toBe(1)
        ->and($templates['summary']['active_template_name'] ?? null)->toBe('Official A4 Result Sheet')
        ->and($templates['templates'][0]['display_name'] ?? null)->toBe('Official A4 Result Sheet')
        ->and(json_encode($templates, JSON_THROW_ON_ERROR))->not->toContain('content', 'builder_json', 'Private template source', 'private')
        ->and($releaseResult->ok)->toBeTrue()
        ->and($release['summary'] ?? [])->toHaveKeys(['total_grading_sessions', 'total_examinees', 'total_released']);

    $clerk = User::query()->where('email', 'registrar.clerk@nexiam.test')->firstOrFail();
    $clerkContext = new InvocationContext(
        actorId: (string) $clerk->id,
        actorType: 'staff',
        institutionId: (int) $clerk->actorScope()->institutionId,
        surfaceProfile: 'staff',
        mode: 'assistant',
        contextEpoch: 'model-first-projection-clerk',
        purpose: 'assistant_tool',
        correlationId: (string) Str::uuid(),
    );
    expect($gateway->invoke($clerk, $clerkContext, 'result_sheet.templates.list@1', [])->ok)->toBeFalse()
        ->and($gateway->invoke($clerk, $clerkContext, 'results.release.overview@1', [])->ok)->toBeFalse();
});

test('on-demand knowledge search is included in final citations through run evidence collection', function (): void {
    seedE2Foundation();

    $registrar = User::query()->where('email', 'registrar.admin@nexiam.test')->firstOrFail();
    $clerk = User::query()->where('email', 'registrar.clerk@nexiam.test')->firstOrFail();
    $version = app(CreateKnowledgeDraft::class)->handle($clerk, [
        'title' => 'Entrance Exam Retake Policy',
        'body_text' => 'Applicants may retake the entrance examination only in the next academic cycle.',
    ]);
    $lifecycle = app(KnowledgeLifecycle::class);
    $lifecycle->startExtraction($version);
    $version->refresh();
    $lifecycle->approve($registrar, $version);
    $lifecycle->publish($registrar, $version->fresh());

    config(['xiam.ai.gateway' => 'laravel']);
    app()->forgetInstance(AiGateway::class);
    app()->instance(AiGateway::class, new LaravelAiGateway);

    XiamAgent::fake([
        new ToolCall('call-ksearch-1', 'knowledge_search_1', ['query' => 'Entrance Exam Retake Policy']),
        'Applicants may retake the entrance examination only in the next academic cycle.',
    ]);

    $response = $this->actingAs($registrar)
        ->postJson(route('xiam.turns.store'), [
            'message' => 'What are the rules for retaking the entrance exam?',
            'idempotency_key' => 'model-first-knowledge-search-1',
        ])
        ->assertAccepted();
    $run = XiamRun::query()->findOrFail($response->json('run_id'));
    $events = XiamRunEvent::query()->where('run_id', $run->id)->get();
    $toolCall = $events->firstWhere('type', 'tool.call');
    $toolResult = $events->firstWhere('type', 'tool.result');
    $citations = $events->firstWhere('type', 'assistant.citations')?->payload['citations'] ?? [];

    expect($run->fresh()->status)->toBe(XiamRun::STATUS_SUCCEEDED)
        ->and($toolCall?->payload['tool'] ?? null)->toBe('knowledge.search@1')
        ->and($toolResult?->payload['tool'] ?? null)->toBe('knowledge.search@1')
        ->and(collect($citations)->pluck('title'))->toContain('Entrance Exam Retake Policy');
});
