<?php

use App\Admission\Actions\AuthenticateApplicantWithAccessKey;
use App\Ai\Adapters\LaravelAi\XiamInquiryAgent;
use App\Ai\Contracts\AiGateway;
use App\Ai\Policy\InputMinimizer;
use App\Ai\Telemetry\GenAiTelemetryContext;
use App\Ai\Telemetry\GenAiTelemetryListener;
use App\Models\Ai\XiamRun;
use App\Models\Ai\XiamRunEvent;
use App\Models\Ai\XiamTurn;
use App\Models\User;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Support\Facades\Event;
use Laravel\Ai\Contracts\Providers\TextProvider;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Events\AgentFailed;
use Laravel\Ai\Events\AgentStreamed;
use Laravel\Ai\Events\InvokingTool;
use Laravel\Ai\Events\PromptingAgent;
use Laravel\Ai\Events\ToolInvoked;
use Laravel\Ai\Prompts\AgentPrompt;
use Laravel\Ai\Responses\Data\Meta;
use Laravel\Ai\Responses\Data\Usage;
use Laravel\Ai\Responses\StreamedAgentResponse;
use Laravel\Ai\Tools\Request;

beforeEach(function (): void {
    config(['xiam.process_inline' => true, 'xiam.ai.gateway' => 'fake']);
    GenAiTelemetryListener::clear();
    GenAiTelemetryContext::clear();
});

test('raw applicant access key or password in turn input throws credential_input_refused', function (): void {
    seedE2Foundation();
    $applicant = submitSyntheticApplicant('ai.privacy.cred@example.test', 'CredApplicant');
    $user = app(AuthenticateApplicantWithAccessKey::class)->handle($applicant['access_token'], '2001-01-01');
    $this->actingAs($user);

    // 1. Direct app_key prefix
    $this->postJson(route('xiam.turns.store'), [
        'message' => 'My secret key is app_key_test_12345678',
        'page_pack_key' => 'applicant.schedule@1',
    ])->assertStatus(422)
        ->assertJson(['error' => 'credential_input_refused']);

    // 2. Formatted applicant access key
    $this->postJson(route('xiam.turns.store'), [
        'message' => 'Can you check my access key K9NM-3X4Y please?',
        'page_pack_key' => 'applicant.schedule@1',
    ])->assertStatus(422)
        ->assertJson(['error' => 'credential_input_refused']);

    // 3. Password assignment
    $this->postJson(route('xiam.turns.store'), [
        'message' => 'Here is my password: SuperSecret123!',
        'page_pack_key' => 'applicant.schedule@1',
    ])->assertStatus(422)
        ->assertJson(['error' => 'credential_input_refused']);
});

test('raw PII in turn input is minimized to placeholders while preserving domain identifiers', function (): void {
    seedE2Foundation();
    $applicant = submitSyntheticApplicant('ai.privacy.pii@example.test', 'PiiApplicant');
    $user = app(AuthenticateApplicantWithAccessKey::class)->handle($applicant['access_token'], '2001-01-01');
    $this->actingAs($user);

    $rawInput = 'My LRN is 123456789012, phone 09171234567, email student@deped.gov.ph for application APP-2026-00001 on session EX-PUB-READY in RM-101.';

    $this->postJson(route('xiam.turns.store'), [
        'message' => $rawInput,
        'page_pack_key' => 'applicant.schedule@1',
    ])->assertAccepted();

    $turn = XiamTurn::query()->latest('created_at')->firstOrFail();

    // Raw PII must be redacted in persisted turn
    expect($turn->input_text)->not->toContain('123456789012')
        ->and($turn->input_text)->not->toContain('09171234567')
        ->and($turn->input_text)->not->toContain('student@deped.gov.ph')
        ->and($turn->input_text)->toContain(InputMinimizer::REDACTED_LRN)
        ->and($turn->input_text)->toContain(InputMinimizer::REDACTED_PHONE)
        ->and($turn->input_text)->toContain(InputMinimizer::REDACTED_EMAIL)
        // Safe domain identifiers preserved
        ->and($turn->input_text)->toContain('APP-2026-00001')
        ->and($turn->input_text)->toContain('EX-PUB-READY')
        ->and($turn->input_text)->toContain('RM-101');
});

test('output firewall rejects responses containing leaked credentials or raw PII', function (): void {
    seedE2Foundation();
    $registrar = User::query()->where('email', 'registrar.admin@nexiam.test')->firstOrFail();
    $this->actingAs($registrar);

    // Mock gateway returning leaked credential
    $leakGateway = new class implements AiGateway
    {
        public string $text = 'Here is the key: K9NM-3X4Y';

        public function generate(array $request): array
        {
            return ['text' => $this->text, 'finish_reason' => 'stop'];
        }

        public function stream(array $request): iterable
        {
            yield ['type' => 'delta', 'text' => $this->text];
            yield ['type' => 'done', 'finish_reason' => 'stop'];
        }

        public function health(): array
        {
            return ['ok' => true];
        }

        public function requestCancel(string $runId): bool
        {
            return false;
        }
    };

    app()->instance(AiGateway::class, $leakGateway);

    $this->postJson(route('xiam.turns.store'), [
        'message' => 'Tell me the admission readiness',
    ])->assertAccepted();

    $run = XiamRun::query()->latest('created_at')->firstOrFail();
    $refusal = XiamRunEvent::query()->where('run_id', $run->id)->where('type', 'assistant.refusal')->latest('seq')->first();
    $message = XiamRunEvent::query()->where('run_id', $run->id)->where('type', 'assistant.message')->first();

    expect($refusal?->payload['reason'] ?? null)->toBe('output_validation_rejected')
        ->and($message?->payload['content'] ?? '')->toContain('cannot safely provide a generated answer')
        ->and($message?->payload['content'] ?? '')->not->toContain('K9NM-3X4Y');

    // Test with leaked raw PII
    $leakGateway->text = 'Student phone is 09171234567 and LRN is 123456789012';

    $this->postJson(route('xiam.turns.store'), [
        'message' => 'Tell me student details',
    ])->assertAccepted();

    $run2 = XiamRun::query()->where('id', '!=', $run->id)->latest('created_at')->firstOrFail();
    $refusal2 = XiamRunEvent::query()->where('run_id', $run2->id)->where('type', 'assistant.refusal')->latest('seq')->first();
    expect($refusal2?->payload['reason'] ?? null)->toBe('output_validation_rejected');
});

test('zero-body telemetry listener records metadata on laravel/ai events without text bodies', function (): void {
    GenAiTelemetryListener::clear();
    GenAiTelemetryContext::set('run-uuid-1234', 'corr-uuid-5678');

    $agent = new XiamInquiryAgent;
    $provider = Mockery::mock(TextProvider::class);

    $prompt = new AgentPrompt(
        agent: $agent,
        prompt: 'What is my secret password123 and SSN?',
        attachments: [],
        provider: $provider,
        model: 'gpt-4o-mini',
    );

    // 1. PromptingAgent
    Event::dispatch(new PromptingAgent('inv-1', $prompt));

    // 2. AgentStreamed
    $response = new StreamedAgentResponse(
        invocationId: 'inv-1',
        events: collect(),
        meta: new Meta(provider: 'openai', model: 'gpt-4o-mini'),
    );
    $response->usage = new Usage(promptTokens: 25, completionTokens: 40);
    Event::dispatch(new AgentStreamed('inv-1', $prompt, $response));

    // 3. InvokingTool
    $tool = new class implements Tool
    {
        public string $name = 'exams.sessions.list';

        public function description(): string
        {
            return 'List sessions';
        }

        public function handle(Request $request): string
        {
            return '[]';
        }

        public function schema(JsonSchema $schema): array
        {
            return [];
        }
    };
    Event::dispatch(new InvokingTool('inv-1', 'tool-inv-1', $agent, $tool, ['secret_arg' => 'secret_value']));

    // 4. ToolInvoked
    Event::dispatch(new ToolInvoked('inv-1', 'tool-inv-1', $agent, $tool, ['secret_arg' => 'secret_value'], ['secret_result' => 'val'], 15.4));

    // 5. AgentFailed
    Event::dispatch(new AgentFailed('inv-1', $prompt, new RuntimeException('RAW_PROVIDER_ERROR_CONTAINING_SECRET_123')));

    $records = GenAiTelemetryListener::recorded();
    expect($records)->toHaveCount(5);

    // Assert zero-body: NO prompt text, NO response text, NO tool arguments, NO result text
    $serialized = json_encode($records, JSON_THROW_ON_ERROR);
    expect($serialized)->not->toContain('What is my secret password123')
        ->and($serialized)->not->toContain('secret_arg')
        ->and($serialized)->not->toContain('secret_value')
        ->and($serialized)->not->toContain('secret_result')
        ->and($serialized)->not->toContain('RAW_PROVIDER_ERROR_CONTAINING_SECRET_123');
    // Assert metadata present
    expect($records[0]['event'])->toBe('genai.prompting')
        ->and($records[0]['run_id'])->toBe('run-uuid-1234')
        ->and($records[0]['correlation_id'])->toBe('corr-uuid-5678')
        ->and($records[0]['model'])->toBe('gpt-4o-mini')
        ->and($records[1]['event'])->toBe('genai.streamed')
        ->and($records[1]['usage']['prompt_tokens'])->toBe(25)
        ->and($records[2]['event'])->toBe('genai.tool_invoking')
        ->and($records[2]['tool_name'])->toBe('exams.sessions.list')
        ->and($records[3]['event'])->toBe('genai.tool_invoked')
        ->and($records[3]['latency_ms'])->toBe(15.4)
        ->and($records[3]['status'])->toBe('completed')
        ->and($records[4]['event'])->toBe('genai.agent_failed')
        ->and($records[4]['error_class'])->toBe(RuntimeException::class)
        ->and($records[4])->not->toHaveKey('error_message')
        ->and($records[4]['status'])->toBe('failed');
});

test('gateway tool_call and tool_result events persist only allowlisted tool metadata without arguments results or raw call ids', function (): void {
    seedE2Foundation();
    $registrar = User::query()->where('email', 'registrar.admin@nexiam.test')->firstOrFail();
    $this->actingAs($registrar);

    $gateway = new class implements AiGateway
    {
        public function generate(array $request): array
        {
            return ['text' => 'Admission readiness summary is available.', 'finish_reason' => 'stop'];
        }

        public function stream(array $request): iterable
        {
            yield [
                'type' => 'tool_call',
                'id' => 'call_secret_id_901',
                'tool' => 'exams_sessions_list_1',
                'arguments' => ['secret_token' => 'SECRET_TOOL_ARG_VALUE_999'],
            ];
            yield [
                'type' => 'tool_result',
                'id' => 'call_secret_id_901',
                'tool' => 'exams_sessions_list_1',
                'result' => ['secret_payload' => 'SECRET_TOOL_RESULT_BODY_888'],
                'successful' => true,
            ];
            yield [
                'type' => 'tool_call',
                'id' => 'call_secret_id_902',
                'tool' => 'unregistered_tool_SECRET_NAME_777',
                'arguments' => ['leak' => 'SECRET_UNLISTED_ARG_666'],
            ];
            yield [
                'type' => 'tool_result',
                'id' => 'call_secret_id_902',
                'tool' => 'unregistered_tool_SECRET_NAME_777',
                'result' => 'SECRET_UNLISTED_RESULT_555',
                'successful' => false,
            ];
            yield ['type' => 'delta', 'text' => 'Admission readiness summary is available.'];
            yield ['type' => 'done', 'finish_reason' => 'stop'];
        }

        public function health(): array
        {
            return ['ok' => true];
        }

        public function requestCancel(string $runId): bool
        {
            return false;
        }
    };

    app()->instance(AiGateway::class, $gateway);

    $this->postJson(route('xiam.turns.store'), [
        'message' => 'Tell me the admission readiness',
    ])->assertAccepted();

    $run = XiamRun::query()->latest('created_at')->firstOrFail();
    $events = XiamRunEvent::query()->where('run_id', $run->id)->orderBy('seq')->get();
    $toolCalls = $events->where('type', 'tool.call')->values();
    $toolResults = $events->where('type', 'tool.result')->values();
    $message = $events->firstWhere('type', 'assistant.message');

    expect($toolCalls)->toHaveCount(2)
        ->and($toolCalls[0]->payload)->toBe(['tool' => 'exams.sessions.list@1'])
        ->and($toolCalls[0]->payload)->not->toHaveKeys(['id', 'arguments'])
        ->and($toolCalls[1]->payload)->toBe(['tool' => null])
        ->and($toolCalls[1]->payload)->not->toHaveKeys(['id', 'arguments'])
        ->and($toolResults)->toHaveCount(2)
        ->and($toolResults[0]->payload)->toBe([
            'tool' => 'exams.sessions.list@1',
            'successful' => true,
        ])
        ->and($toolResults[0]->payload)->not->toHaveKeys(['id', 'result'])
        ->and($toolResults[1]->payload)->toBe([
            'tool' => null,
            'successful' => false,
        ])
        ->and($toolResults[1]->payload)->not->toHaveKeys(['id', 'result'])
        ->and($message?->payload['content'] ?? null)->toBe('Admission readiness summary is available.');

    $serializedEvents = json_encode($events->pluck('payload')->all(), JSON_THROW_ON_ERROR);
    expect($serializedEvents)->not->toContain('call_secret_id_901')
        ->and($serializedEvents)->not->toContain('call_secret_id_902')
        ->and($serializedEvents)->not->toContain('SECRET_TOOL_ARG_VALUE_999')
        ->and($serializedEvents)->not->toContain('SECRET_TOOL_RESULT_BODY_888')
        ->and($serializedEvents)->not->toContain('unregistered_tool_SECRET_NAME_777')
        ->and($serializedEvents)->not->toContain('SECRET_UNLISTED_ARG_666')
        ->and($serializedEvents)->not->toContain('SECRET_UNLISTED_RESULT_555');
});
