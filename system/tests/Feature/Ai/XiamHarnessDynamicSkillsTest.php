<?php

declare(strict_types=1);

use App\Ai\Adapters\LaravelAi\LaravelAiGateway;
use App\Ai\Adapters\LaravelAi\XiamAgent;
use App\Ai\Context\InvocationContext;
use App\Ai\Contracts\AiGateway;
use App\Ai\Gateway\AssistantToolGateway;
use App\Ai\Skills\InstructionSkillCatalog;
use App\Ai\Streaming\VercelUiMessageStreamProjector;
use App\Models\Admission\AdmissionAttempt;
use App\Models\Ai\XiamRun;
use App\Models\Ai\XiamRunEvent;
use App\Models\Examination\ExamSession;
use App\Models\Foundation\AcademicCycle;
use App\Models\Foundation\Institution;
use App\Models\Foundation\Room;
use App\Models\User;
use Illuminate\Support\Str;
use Laravel\Ai\Responses\Data\ToolCall;

beforeEach(function (): void {
    config(['xiam.process_inline' => true, 'xiam.ai.gateway' => 'fake', 'xiam.enabled' => true]);
});

/** @return array{actor: User, context: InvocationContext} */
function dynamicSkillsStaffContext(): array
{
    seedE2Foundation();
    $actor = User::query()->where('email', 'registrar.admin@nexiam.test')->firstOrFail();

    return [
        'actor' => $actor,
        'context' => new InvocationContext(
            actorId: (string) $actor->id,
            actorType: 'staff',
            institutionId: (int) $actor->actorScope()->institutionId,
            surfaceProfile: 'staff',
            mode: 'assistant',
            contextEpoch: 'dynamic-skills-test-epoch',
            purpose: 'assistant_tool',
            correlationId: (string) Str::uuid(),
        ),
    ];
}

test('registrar can make an operational briefing, greet Xiam, then resume the briefing', function (): void {
    seedE2Foundation();
    $registrar = User::query()->where('email', 'registrar.admin@nexiam.test')->firstOrFail();
    $this->actingAs($registrar);
    config(['xiam.ai.gateway' => 'laravel']);
    app()->forgetInstance(AiGateway::class);
    app()->instance(AiGateway::class, new LaravelAiGateway);
    XiamAgent::fake([
        new ToolCall('briefing-summary-1', 'workspace_summary_1', []),
        new ToolCall('briefing-today-1', 'exams_sessions_today_1', []),
        'Operational briefing: pending admission reviews and today’s exam sessions are ready.',
        'Hello! How can I help?',
        new ToolCall('briefing-summary-2', 'workspace_summary_1', []),
        new ToolCall('briefing-today-2', 'exams_sessions_today_1', []),
        'Operational briefing: pending admission reviews are ready to work today.',
    ]);

    $seenRunIds = [];
    $postTurn = function (string $message, string $key) use (&$seenRunIds): XiamRun {
        test()->postJson(route('xiam.turns.store'), [
            'message' => $message,
            'idempotency_key' => $key,
        ])->assertAccepted();

        $run = XiamRun::query()->whereNotIn('id', $seenRunIds)->latest('created_at')->firstOrFail();
        $seenRunIds[] = $run->id;

        return $run;
    };
    $event = static fn (XiamRun $run, string $type): ?XiamRunEvent => XiamRunEvent::query()
        ->where('run_id', $run->id)
        ->where('type', $type)
        ->first();
    $hasNoRefusal = static fn (XiamRun $run): bool => ! XiamRunEvent::query()
        ->where('run_id', $run->id)
        ->where('type', 'assistant.refusal')
        ->exists();

    $briefingRun = $postTurn('whats up today?', 'dynamic-briefing-1');
    $briefingEvents = XiamRunEvent::query()->where('run_id', $briefingRun->id)->orderBy('seq')->get();
    $briefingMessage = $event($briefingRun, 'assistant.message');
    $briefingCitations = $event($briefingRun, 'assistant.citations');
    $citations = $briefingCitations?->payload['citations'] ?? [];
    $citationLocators = collect($citations)->pluck('locator');

    expect($briefingRun->fresh()->status)->toBe(XiamRun::STATUS_SUCCEEDED)
        ->and($hasNoRefusal($briefingRun))->toBeTrue()
        ->and($citationLocators)->toContain('workspace.summary@1', 'exams.sessions.today@1')
        ->and(collect($citations)->where('source_class', 'staff_operational_fact'))->toHaveCount(2)
        ->and($briefingEvents->where('type', 'tool.call')->pluck('payload.tool')->all())->toBe([
            'workspace.summary@1',
            'exams.sessions.today@1',
        ])
        ->and($briefingEvents->where('type', 'tool.result')->pluck('payload.tool')->all())->toBe([
            'workspace.summary@1',
            'exams.sessions.today@1',
        ])
        ->and($briefingEvents->where('type', 'tool.result')->pluck('payload.successful')->every(fn (bool $successful): bool => $successful))->toBeTrue()
        ->and($briefingMessage?->payload['content'] ?? '')->toContain('Operational briefing');

    $greetingRun = $postTurn('hello', 'dynamic-briefing-2');
    $greetingEvents = XiamRunEvent::query()->where('run_id', $greetingRun->id)->get();
    $greetingStream = implode('', iterator_to_array(app(VercelUiMessageStreamProjector::class)->project(
        XiamRunEvent::query()->where('run_id', $greetingRun->id)->orderBy('seq')->get(),
    )));

    expect($greetingRun->fresh()->status)->toBe(XiamRun::STATUS_SUCCEEDED)
        ->and($hasNoRefusal($greetingRun))->toBeTrue()
        ->and($greetingEvents->where('type', 'tool.call'))->toBeEmpty()
        ->and($greetingStream)->not->toContain('"emotion":"sad"');

    $resumeRun = $postTurn('i mean what can we work today', 'dynamic-briefing-3');
    $resumeMessage = $event($resumeRun, 'assistant.message');
    $resumeEvents = XiamRunEvent::query()->where('run_id', $resumeRun->id)->orderBy('seq')->get();
    $resumeTools = $resumeEvents->where('type', 'tool.call')->pluck('payload.tool');
    expect($resumeRun->fresh()->status)->toBe(XiamRun::STATUS_SUCCEEDED)
        ->and($hasNoRefusal($resumeRun))->toBeTrue()
        ->and($resumeMessage?->payload['content'] ?? '')->toContain('Operational briefing')
        ->and($resumeMessage?->payload['content'] ?? '')->toContain('pending admission')
        ->and($resumeEvents->where('type', 'tool.result')->pluck('payload.tool')->values()->all())->toBe(['workspace.summary@1', 'exams.sessions.today@1'])
        ->and($resumeTools->values()->all())->toBe(['workspace.summary@1', 'exams.sessions.today@1']);
});

test('model selects an authorized read tool and gateway enforces actor scope', function (): void {
    ['actor' => $registrar, 'context' => $context] = dynamicSkillsStaffContext();
    config(['xiam.ai.gateway' => 'laravel']);
    app()->forgetInstance(AiGateway::class);
    app()->instance(AiGateway::class, new LaravelAiGateway);
    XiamAgent::fake([
        new ToolCall('model-selected-summary', 'workspace_summary_1', []),
        'The operational workspace briefing is ready.',
    ]);

    $accepted = $this->actingAs($registrar)->postJson(route('xiam.turns.store'), [
        'message' => 'Give me an operational briefing.',
        'idempotency_key' => 'dynamic-model-selected-read',
    ])->assertAccepted()->json();
    $run = XiamRun::query()->findOrFail($accepted['run_id']);
    $runEvents = XiamRunEvent::query()->where('run_id', $run->id)->get();
    $toolCall = $runEvents->firstWhere('type', 'tool.call');
    $toolResult = $runEvents->firstWhere('type', 'tool.result');
    $message = $runEvents->firstWhere('type', 'assistant.message');
    $citations = $runEvents->firstWhere('type', 'assistant.citations')?->payload['citations'] ?? [];
    $clerk = User::query()->where('email', 'registrar.clerk@nexiam.test')->firstOrFail();
    $denied = app(AssistantToolGateway::class)->invoke($clerk, $context, 'workspace.summary@1', []);

    expect($run->fresh()->status)->toBe(XiamRun::STATUS_SUCCEEDED)
        ->and($toolCall?->payload['tool'] ?? null)->toBe('workspace.summary@1')
        ->and($toolResult?->payload['tool'] ?? null)->toBe('workspace.summary@1')
        ->and($toolResult?->payload['successful'] ?? false)->toBeTrue()
        ->and($message?->payload['content'] ?? '')->toContain('operational workspace briefing')
        ->and(collect($citations)->pluck('locator'))->toContain('workspace.summary@1')
        ->and($denied->ok)->toBeFalse()
        ->and($denied->reason)->toBe('invocation_actor_mismatch');
});

test('instruction skill catalog discovers all skills and filters them by actor surface', function (): void {
    $catalog = app(InstructionSkillCatalog::class);
    $skills = collect($catalog->all())->keyBy('name');

    expect($skills->keys()->sort()->values()->all())->toBe([
        'applicant-slot-booking',
        'consultation-scheduling',
        'conversation',
        'exam-scheduling',
        'policy-inquiry',
        'setup-governance',
        'workspace-briefing',
    ]);

    foreach ($skills as $skill) {
        expect($skill->instructions)->not->toBeEmpty()
            ->and($skill->contentHash)->toMatch('/^[a-f0-9]{64}$/');
    }

    $staffSkills = collect($catalog->forSurface('staff'))->pluck('name');
    $applicantSkills = collect($catalog->forSurface('applicant'))->pluck('name');

    expect($staffSkills)->toContain('workspace-briefing', 'exam-scheduling', 'consultation-scheduling', 'setup-governance')
        ->and($staffSkills)->not->toContain('applicant-slot-booking')
        ->and($applicantSkills)->toContain('applicant-slot-booking')
        ->and($applicantSkills)->not->toContain('workspace-briefing');
});

test('Xiam agent includes Tier 1 catalog and Tier 2 active skill instructions', function (): void {
    ['actor' => $registrar, 'context' => $context] = dynamicSkillsStaffContext();
    $instructions = (string) XiamAgent::forRun(
        $registrar,
        $context,
        activeSkill: 'workspace-briefing',
    )->instructions();

    expect($instructions)->toContain('<available_skills>')
        ->and($instructions)->toContain('<active_skill name="workspace-briefing"')
        ->and($instructions)->toContain('approved_operational_evidence');
});

test('ambient read tools return local institution counts and exclude foreign attempts and sessions', function (): void {
    ['actor' => $registrar, 'context' => $context] = dynamicSkillsStaffContext();
    $localCycle = AcademicCycle::query()->where('code', 'SYN-CYCLE-2026')->firstOrFail();
    $localInstitutionId = (int) $registrar->actorScope()->institutionId;
    $baselineAttempts = AdmissionAttempt::query()
        ->whereHas('academicCycle', fn ($query) => $query->where('institution_id', $localInstitutionId))
        ->whereIn('status', [AdmissionAttempt::STATUS_PENDING, AdmissionAttempt::STATUS_NEEDS_CORRECTION, 'submitted', 'under_review', 'pending_verification'])
        ->count();
    $baselineTodaySessions = ExamSession::query()
        ->whereHas('academicCycle', fn ($query) => $query->where('institution_id', $localInstitutionId))
        ->whereBetween('starts_at', [now()->copy()->startOfDay(), now()->copy()->endOfDay()])
        ->where('status', '!=', ExamSession::STATUS_CANCELLED)
        ->count();

    $foreignInstitution = Institution::query()->create([
        'code' => 'DYN-FOREIGN-'.strtoupper(Str::random(8)),
        'name' => 'Foreign Dynamic Skills Institution',
        'slug' => 'dyn-foreign-'.strtolower(Str::random(8)),
        'is_active' => true,
    ]);
    $foreignCycle = AcademicCycle::query()->create([
        'institution_id' => $foreignInstitution->id,
        'code' => 'DYN-CYCLE-'.strtoupper(Str::random(8)),
        'name' => 'Foreign Dynamic Skills Cycle',
        'opens_on' => now()->subDay()->toDateString(),
        'closes_on' => now()->addYear()->toDateString(),
        'school_year' => '2026-2027',
        'semester' => '1',
        'is_active' => false,
    ]);
    $foreignRoom = Room::query()->create([
        'institution_id' => $foreignInstitution->id,
        'code' => 'DYN-ROOM-'.strtoupper(Str::random(8)),
        'name' => 'Foreign Dynamic Skills Room',
        'capacity' => 30,
        'is_active' => true,
    ]);
    $localRoom = Room::query()->where('institution_id', $localInstitutionId)->firstOrFail();
    $sessionTime = now()->copy()->startOfDay()->addHours(10);
    $localSession = ExamSession::query()->create([
        'academic_cycle_id' => $localCycle->id,
        'room_id' => $localRoom->id,
        'code' => 'EX-DYN-LOCAL-'.strtoupper(Str::random(6)),
        'starts_at' => $sessionTime,
        'effective_end_at' => $sessionTime->copy()->addHours(2),
        'status' => ExamSession::STATUS_DRAFT,
        'version' => 1,
    ]);
    $foreignSession = ExamSession::query()->create([
        'academic_cycle_id' => $foreignCycle->id,
        'room_id' => $foreignRoom->id,
        'code' => 'EX-DYN-FOREIGN-'.strtoupper(Str::random(6)),
        'starts_at' => $sessionTime,
        'effective_end_at' => $sessionTime->copy()->addHours(2),
        'status' => ExamSession::STATUS_DRAFT,
        'version' => 1,
    ]);

    $localAttempt = submitSyntheticApplicant('dynamic-local-'.strtolower(Str::random(8)).'@example.test', 'DynamicLocal');
    $foreignAttempt = submitSyntheticApplicant('dynamic-foreign-'.strtolower(Str::random(8)).'@example.test', 'DynamicForeign');
    $foreignAttempt['attempt']->update([
        'institution_id' => $foreignInstitution->id,
        'academic_cycle_id' => $foreignCycle->id,
    ]);

    // Completed session earlier today must NOT inflate upcoming_sessions_count
    $baselineUpcoming = ExamSession::query()
        ->whereHas('academicCycle', fn ($query) => $query->where('institution_id', $localInstitutionId))
        ->where('starts_at', '>=', now())
        ->whereNotIn('status', [ExamSession::STATUS_CANCELLED, ExamSession::STATUS_COMPLETED])
        ->count();
    ExamSession::query()->create([
        'academic_cycle_id' => $localCycle->id,
        'room_id' => $localRoom->id,
        'code' => 'EX-DYN-COMPLETED-'.strtoupper(Str::random(6)),
        'starts_at' => now()->copy()->startOfDay()->addHour(),
        'effective_end_at' => now()->copy()->startOfDay()->addHours(2),
        'status' => ExamSession::STATUS_COMPLETED,
        'version' => 1,
    ]);

    $gateway = app(AssistantToolGateway::class);
    $summary = $gateway->invoke($registrar, $context, 'workspace.summary@1', []);
    $sessions = $gateway->invoke($registrar, $context, 'exams.sessions.today@1', []);

    expect($summary->ok)->toBeTrue()
        ->and($summary->data['summary']['institution_id'] ?? null)->toBe($localInstitutionId)
        ->and($summary->data['summary']['pending_reviews_count'] ?? null)->toBe($baselineAttempts + 1)
        ->and($summary->data['summary']['today_sessions_count'] ?? null)->toBe($baselineTodaySessions + 2)
        ->and($summary->data['summary']['upcoming_sessions_count'] ?? null)->toBe($baselineUpcoming)
        ->and($sessions->ok)->toBeTrue()
        ->and($sessions->data['today_count'] ?? null)->toBe($baselineTodaySessions + 2)
        ->and(collect($sessions->data['sessions'] ?? [])->pluck('code'))->toContain($localSession->code)
        ->and(collect($sessions->data['sessions'] ?? [])->pluck('code'))->not->toContain($foreignSession->code)
        ->and((int) $localAttempt['attempt']->fresh()->institution_id)->toBe($localInstitutionId)
        ->and((int) $foreignAttempt['attempt']->fresh()->institution_id)->toBe((int) $foreignInstitution->id);

});

test('Laravel AI gateway spotlights escaped operational evidence for the active skill', function (): void {
    ['actor' => $registrar, 'context' => $context] = dynamicSkillsStaffContext();
    $recordedPrompt = '';
    XiamAgent::fake(static function (string $prompt) use (&$recordedPrompt): string {
        $recordedPrompt = $prompt;

        return 'Briefing received.';
    });

    iterator_to_array((new LaravelAiGateway)->stream([
        'actor' => $registrar,
        'context' => $context,
        'prompt' => 'What can we work today?',
        'conversation_id' => (string) Str::uuid(),
        'run_id' => (string) Str::uuid(),
        'turn_id' => (string) Str::uuid(),
        'mode' => 'assistant',
        'active_skill' => 'workspace-briefing',
        'operational_evidence' => [
            'workspace.summary@1' => ['summary' => ['active_cycle_name' => 'Cycle 2026 <script>']],
        ],
    ]));

    expect($recordedPrompt)->toContain('<approved_operational_evidence>')
        ->and($recordedPrompt)->toContain('&lt;script&gt;')
        ->and($recordedPrompt)->not->toContain('<script>');
});

test('Vercel stream labels each skill phase and only adds sadness for explicit refusal', function (): void {
    $projector = app(VercelUiMessageStreamProjector::class);
    $labels = [
        'ambient_briefing' => 'Reading live workspace & session queues…',
        'conversational' => 'Preparing response…',
        'policy_inquiry' => 'Checking approved institutional policies…',
        'action_proposal' => 'Preparing operational proposal…',
    ];
    $projected = '';

    foreach ($labels as $phase => $label) {
        $event = new XiamRunEvent([
            'run_id' => 'dynamic-skill-'.$phase,
            'type' => 'skill.plan',
            'payload' => ['phase' => $phase, 'skill' => $phase.'-skill'],
        ]);
        $stream = implode('', iterator_to_array($projector->project([$event])));
        $projected .= $stream;

        expect($stream)->toContain('"type":"data-xiam-steps"')
            ->and($stream)->toContain($label)
            ->and($stream)->toContain('"skill":"'.$phase.'-skill"')
            ->and($stream)->not->toContain('"emotion":"sad"');
    }

    $refusal = new XiamRunEvent([
        'run_id' => 'dynamic-skill-refusal',
        'type' => 'assistant.refusal',
        'payload' => ['reason' => 'no_grounded_support'],
    ]);
    $refusalStream = implode('', iterator_to_array($projector->project([$refusal])));

    expect($projected)->not->toContain('"emotion":"sad"')
        ->and($refusalStream)->toContain('"emotion":"sad"');
});
