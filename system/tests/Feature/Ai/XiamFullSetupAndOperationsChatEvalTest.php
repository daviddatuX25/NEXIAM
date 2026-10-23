<?php

use App\Ai\Adapters\LaravelAi\LaravelAiGateway;
use App\Ai\Adapters\LaravelAi\XiamAgent;
use App\Ai\Contracts\AiGateway;
use App\Ai\Proposals\ProposalService;
use App\Authorization\CapabilityCatalog;
use App\Models\Admission\AdmissionAttempt;
use App\Models\Ai\XiamProposal;
use App\Models\Ai\XiamRun;
use App\Models\Ai\XiamRunEvent;
use App\Models\Foundation\AcademicCycle;
use App\Models\Foundation\ApplicationWindow;
use App\Models\Foundation\Program;
use App\Models\Foundation\ProgramQuota;
use App\Models\Foundation\Room;
use App\Models\User;
use App\Notifications\Policies\AdmissionNotificationPolicy;
use App\Notifications\Policies\ExamNotificationPolicy;
use App\Setup\Actions\UpsertProgramQuota;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Laravel\Ai\Responses\Data\ToolCall;

uses(RefreshDatabase::class);

function setupEvalStaff(string $role = 'institutional_admin'): User
{
    if ($role === 'institutional_admin') {
        return User::query()->where('email', 'admin.lead@nexiam.test')->firstOrFail();
    }

    if ($role === 'registrar_admin' || $role === 'registrar_administrator') {
        return User::query()->where('email', 'registrar.admin@nexiam.test')->firstOrFail();
    }

    return User::query()->where('email', 'guidance.counselor@nexiam.test')->firstOrFail();
}

/** @return array{run: XiamRun, events: Collection<int, XiamRunEvent>, conversation_id: string} */
function setupEvalChatTurn(
    string $message,
    string $key,
    ?string $conversationId = null,
    string $pagePackKey = 'staff.setup.readiness@1',
    int $pagePackVersion = 1,
): array {
    $payload = [
        'message' => $message,
        'idempotency_key' => $key,
        'page_pack_key' => $pagePackKey,
        'page_pack_version' => $pagePackVersion,
    ];
    if ($conversationId !== null) {
        $payload['conversation_id'] = $conversationId;
    }

    test()->postJson(route('xiam.mode.update'), ['mode' => 'assistant'])->assertOk();
    xiamKeepCurrentSessionCookie();

    $accepted = test()->withHeader('X-Xiam-Browser-Session', 'full-setup-chat-eval')
        ->postJson(route('xiam.turns.store'), $payload)
        ->assertAccepted()
        ->json();
    $run = XiamRun::query()->findOrFail($accepted['run_id']);

    return [
        'run' => $run,
        'events' => XiamRunEvent::query()->where('run_id', $run->id)->orderBy('seq')->get(),
        'conversation_id' => $accepted['conversation_id'],
    ];
}

/** @return array{proposal: XiamProposal, event: XiamRunEvent, turn: array} */
function setupEvalProposal(array $turn): array
{
    $event = $turn['events']->firstWhere('type', 'skill.proposal');
    $proposal = XiamProposal::query()->findOrFail($event->payload['proposal_id']);
    $snapshot = test()->getJson(route('xiam.conversations.snapshot', $turn['conversation_id']))->assertOk()->json();

    expect($snapshot['proposals'])->toContain(['id' => $proposal->id, 'status' => 'pending']);

    return compact('proposal', 'event', 'turn');
}
function assertSetupEvalProposalStream(array $turn, string $tool, array $arguments): void
{
    $events = $turn['events'];
    $types = $events->pluck('type')->all();
    $proposalEvent = $events->firstWhere('type', 'skill.proposal');
    expect($proposalEvent)->not->toBeNull();
    $proposal = XiamProposal::query()->findOrFail($proposalEvent->payload['proposal_id']);

    expect(array_search('tool.call', $types, true))->toBeLessThan(array_search('skill.proposal', $types, true))
        ->and(array_search('skill.proposal', $types, true))->toBeLessThan(array_search('tool.result', $types, true))
        ->and($events->firstWhere('type', 'tool.call')?->payload['tool'])->toBe($tool)
        ->and($events->firstWhere('type', 'tool.result')?->payload['successful'])->toBeTrue()
        ->and($proposalEvent->payload['capability_id'])->toBe($tool)
        ->and($proposal->summary['impact']['_command_input'] ?? null)->toBe($arguments)
        ->and($turn['run']->fresh()->status)->toBe(XiamRun::STATUS_SUCCEEDED);
}
test('Eval 1: Full setup journey proposes and confirms each institution change through authenticated chat', function (): void {
    seedE2Foundation();
    config(['xiam.process_inline' => true, 'xiam.ai.gateway' => 'laravel', 'xiam.enabled' => true]);
    config()->set('admission.quota_writes_enabled', true);
    app()->forgetInstance(AiGateway::class);
    app()->instance(AiGateway::class, new LaravelAiGateway);
    $admin = setupEvalStaff();
    $admin->givePermissionTo([
        CapabilityCatalog::SETUP_INSTITUTION_MANAGE,
        CapabilityCatalog::SETUP_PROGRAMS_MANAGE,
        CapabilityCatalog::AI_GOVERNANCE_INSTITUTION_MANAGE,
    ]);
    $guidance = setupEvalStaff('guidance_counselor');
    $programHead = User::query()->where('email', 'program.head.bsit@nexiam.test')->firstOrFail();
    $programHead->givePermissionTo(CapabilityCatalog::PROGRAM_QUOTA_MANAGE);
    $institutionId = (int) $admin->actorScope()->institutionId;
    $this->actingAs($admin);

    $confirm = function (array $turn, User $actor, string $key, array $extra = []): array {
        $card = setupEvalProposal($turn);
        $response = $this->actingAs($actor)->postJson(route('xiam.proposals.confirm', $card['proposal']->id), [
            'idempotency_key' => $key,
            ...$extra,
        ]);
        $response->assertOk()->assertJsonPath('proposal.status', 'executed');
        expect($card['proposal']->fresh()->status)->toBe(ProposalService::STATUS_EXECUTED)
            ->and($card['proposal']->fresh()->receipt['receipt_id'] ?? null)->not->toBeEmpty();

        return $card;
    };
    $roomArgs = ['code' => 'ITBR-201', 'name' => 'Computer Lab 201', 'capacity' => 45, 'is_exam_room' => true];
    XiamAgent::fake([new ToolCall('room-create', 'setup_rooms_create_draft_1', $roomArgs), 'Room proposal ready.']);
    $roomTurn = setupEvalChatTurn('Create exam room ITBR-201 named Computer Lab 201 with 45 seats.', 'eval-setup-room');
    assertSetupEvalProposalStream($roomTurn, 'setup.rooms.create_draft@1', $roomArgs);
    $roomCard = setupEvalProposal($roomTurn);
    expect($roomCard['proposal']->conversation_id)->toBe($roomTurn['conversation_id'])
        ->and($roomCard['proposal']->safety_tier)->toBe('t1')
        ->and($roomCard['proposal']->summary['impact']['_command_input'])->toBe($roomArgs)
        ->and(Room::query()->where('institution_id', $institutionId)->where('code', 'ITBR-201')->exists())->toBeFalse();
    $confirm($roomTurn, $admin, 'confirm-room-itbr201');
    expect(Room::query()->where('institution_id', $institutionId)->where('code', 'ITBR-201')->firstOrFail()->capacity)->toBe(45);
    $room = Room::query()->where('institution_id', $institutionId)->where('code', 'ITBR-201')->firstOrFail();
    $roomUpdateArgs = ['room_id' => (int) $room->id, 'capacity' => 60, 'is_exam_room' => true];
    XiamAgent::fake([new ToolCall('room-update', 'setup_rooms_update_1', $roomUpdateArgs), 'Room update proposal ready.']);
    $roomUpdateTurn = setupEvalChatTurn('Increase the capacity of exam room ITBR-201 to 60 seats.', 'eval-setup-room-update');
    assertSetupEvalProposalStream($roomUpdateTurn, 'setup.rooms.update@1', $roomUpdateArgs);
    $roomUpdateCard = setupEvalProposal($roomUpdateTurn);
    expect($roomUpdateCard['proposal']->conversation_id)->toBe($roomUpdateTurn['conversation_id'])
        ->and($roomUpdateCard['proposal']->safety_tier)->toBe('t2')
        ->and($roomUpdateCard['proposal']->confirmation_phrase)->toBe('UPDATE ROOM')
        ->and($roomUpdateCard['proposal']->summary['impact']['_command_input'])->toBe($roomUpdateArgs)
        ->and($room->fresh()->capacity)->toBe(45);
    $confirm($roomUpdateTurn, $admin, 'confirm-room-itbr201-update', ['phrase' => 'UPDATE ROOM']);
    expect($room->fresh()->capacity)->toBe(60);

    $cycleArgs = ['school_year' => '2026-2027', 'semester' => '1', 'application_start_date' => '2027-01-01', 'application_end_date' => '2027-05-31'];
    XiamAgent::fake([new ToolCall('cycle-create', 'setup_cycles_create_draft_1', $cycleArgs), 'Academic cycle proposal ready.']);
    $cycleTurn = setupEvalChatTurn('Create the first semester of academic year 2026-2027 with applications from January 1 through May 31, 2027.', 'eval-setup-cycle');
    assertSetupEvalProposalStream($cycleTurn, 'setup.cycles.create_draft@1', $cycleArgs);
    $cycleCard = setupEvalProposal($cycleTurn);
    expect($cycleCard['proposal']->conversation_id)->toBe($cycleTurn['conversation_id'])
        ->and($cycleCard['proposal']->safety_tier)->toBe('t1')
        ->and($cycleCard['proposal']->summary['impact']['_command_input'])->toBe($cycleArgs)
        ->and(AcademicCycle::query()->where('institution_id', $institutionId)->where('school_year', '2026-2027')->exists())->toBeFalse();
    $confirm($cycleTurn, $admin, 'confirm-cycle-2026-s1');
    $cycle = AcademicCycle::query()->where('institution_id', $institutionId)->where('school_year', '2026-2027')->where('semester', '1')->firstOrFail();
    expect($cycle->is_active)->toBeFalse();

    $program = Program::query()->where('institution_id', $institutionId)->where('code', 'BSN')->firstOrFail();
    $quotaArgs = ['program_id' => (int) $program->id, 'academic_cycle_id' => (int) $cycle->id, 'slots' => 60, 'reason' => 'Synthetic quota acceptance test', 'expected_version' => 0, 'idempotency_key' => (string) Str::uuid()];
    XiamAgent::fake([new ToolCall('quota-upsert', 'setup_quotas_upsert_1', $quotaArgs), 'Quota proposal ready.']);
    $this->actingAs($programHead);
    $quotaTurn = setupEvalChatTurn('Set the BSN quota to 60 seats for academic cycle 2026-2027 semester 1.', 'eval-setup-quota');
    assertSetupEvalProposalStream($quotaTurn, 'setup.quotas.upsert@1', $quotaArgs);
    $quotaCard = setupEvalProposal($quotaTurn);
    expect($quotaCard['proposal']->conversation_id)->toBe($quotaTurn['conversation_id'])
        ->and($quotaCard['proposal']->safety_tier)->toBe('t2')
        ->and($quotaCard['proposal']->summary['impact']['_command_input'])->toBe($quotaArgs)
        ->and(ProgramQuota::query()->where('program_id', $program->id)->where('academic_cycle_id', $cycle->id)->exists())->toBeFalse();
    $confirm($quotaTurn, $programHead, 'confirm-quota-bsn', ['phrase' => 'UPDATE QUOTA']);
    expect(ProgramQuota::query()->where('program_id', $program->id)->where('academic_cycle_id', $cycle->id)->value('slots'))->toBe(60);

    $otherProgram = Program::query()->where('institution_id', $institutionId)->where('code', 'BSPSY')->firstOrFail();
    expect(fn () => app(UpsertProgramQuota::class)->handle(
        $programHead,
        $otherProgram,
        $cycle,
        60,
        'Synthetic quota acceptance test',
        0,
        (string) Str::uuid(),
    ))->toThrow(AuthorizationException::class);

    $this->actingAs($admin);
    $admissionSettingsBefore = AdmissionNotificationPolicy::fromStorage($cycle->admission_notification_policy)->toArray();
    $admissionArgs = [
        'academic_cycle_id' => (int) $cycle->id,
        'expected_version' => $admissionSettingsBefore['version'],
        'applicant_status_email_enabled' => true,
        'registrar_new_submission_alert_enabled' => false,
    ];
    XiamAgent::fake([new ToolCall('admission-notifications', 'setup_notifications_update_admission_1', $admissionArgs), 'Admission notification proposal ready.']);
    $admissionTurn = setupEvalChatTurn('Enable applicant admission-status email and disable registrar new-submission alerts for this cycle.', 'eval-setup-admission-notifications');
    assertSetupEvalProposalStream($admissionTurn, 'setup.notifications.update_admission@1', $admissionArgs);
    $admissionCard = setupEvalProposal($admissionTurn);
    expect($admissionCard['proposal']->conversation_id)->toBe($admissionTurn['conversation_id'])
        ->and($admissionCard['proposal']->safety_tier)->toBe('t2')
        ->and($admissionCard['proposal']->confirmation_phrase)->toBe('UPDATE NOTIFICATIONS')
        ->and($admissionCard['proposal']->summary['impact']['_command_input'])->toBe($admissionArgs);
    expect(AdmissionNotificationPolicy::fromStorage($cycle->fresh()->admission_notification_policy)->toArray())->toBe($admissionSettingsBefore);
    $confirm($admissionTurn, $admin, 'confirm-admission-notifications', ['phrase' => 'UPDATE NOTIFICATIONS']);
    $admissionPolicy = AdmissionNotificationPolicy::fromStorage($cycle->fresh()->admission_notification_policy);
    expect($admissionPolicy->applicantStatusEmailEnabled)->toBeTrue()
        ->and($admissionPolicy->registrarNewSubmissionAlertEnabled)->toBeFalse();

    $examSettingsBefore = ExamNotificationPolicy::fromStorage($cycle->exam_notification_policy)->toArray();
    $examArgs = [
        'academic_cycle_id' => (int) $cycle->id,
        'expected_version' => $examSettingsBefore['version'],
        'publication_email_enabled' => true,
        'reminder_email_enabled' => true,
        'reminder_offsets_minutes' => [1440, 60],
    ];
    $this->actingAs($guidance);
    XiamAgent::fake([new ToolCall('exam-notifications', 'setup_notifications_update_exam_1', $examArgs), 'Exam notification proposal ready.']);
    $examTurn = setupEvalChatTurn(
        'Enable exam publication emails and reminders 24 hours and 60 minutes before sessions.',
        'eval-setup-exam-notifications',
        pagePackKey: 'staff.exams.sessions.index@1',
        pagePackVersion: 1,
    );
    assertSetupEvalProposalStream($examTurn, 'setup.notifications.update_exam@1', $examArgs);
    $examCard = setupEvalProposal($examTurn);
    expect($examCard['proposal']->conversation_id)->toBe($examTurn['conversation_id'])
        ->and($examCard['proposal']->safety_tier)->toBe('t2')
        ->and($examCard['proposal']->confirmation_phrase)->toBe('UPDATE NOTIFICATIONS')
        ->and($examCard['proposal']->summary['impact']['_command_input'])->toBe($examArgs);
    expect(ExamNotificationPolicy::fromStorage($cycle->fresh()->exam_notification_policy)->toArray())->toBe($examSettingsBefore);
    $confirm($examTurn, $guidance, 'confirm-exam-notifications', ['phrase' => 'UPDATE NOTIFICATIONS']);
    $examPolicy = ExamNotificationPolicy::fromStorage($cycle->fresh()->exam_notification_policy);
    expect($examPolicy->publicationEmailEnabled)->toBeTrue()
        ->and($examPolicy->reminderEmailEnabled)->toBeTrue()
        ->and($examPolicy->reminderOffsetsMinutes)->toBe([1440, 60]);

    $this->actingAs($admin);
    $windowArgs = [
        'academic_cycle_id' => (int) $cycle->id,
        'code' => 'APP-2027',
        'name' => '2027 Application Window',
        'opens_at' => '2027-01-01T08:00:00+08:00',
        'closes_at' => '2027-05-31T23:59:59+08:00',
        'status' => 'draft',
    ];
    XiamAgent::fake([new ToolCall('application-window', 'setup_windows_store_1', $windowArgs), 'Application window proposal ready.']);
    $windowTurn = setupEvalChatTurn('Create draft application window APP-2027 for this cycle, open January 1 and close May 31, 2027.', 'eval-setup-window');
    assertSetupEvalProposalStream($windowTurn, 'setup.windows.store@1', $windowArgs);
    $windowCard = setupEvalProposal($windowTurn);
    expect($windowCard['proposal']->conversation_id)->toBe($windowTurn['conversation_id'])
        ->and($windowCard['proposal']->safety_tier)->toBe('t2')
        ->and($windowCard['proposal']->confirmation_phrase)->toBe('UPDATE APPLICATION WINDOW')
        ->and($windowCard['proposal']->summary['impact']['_command_input'])->toBe($windowArgs)
        ->and(ApplicationWindow::query()->where('code', 'APP-2027')->exists())->toBeFalse();
    $confirm($windowTurn, $admin, 'confirm-application-window', ['phrase' => 'UPDATE APPLICATION WINDOW']);
    expect(ApplicationWindow::query()->where('academic_cycle_id', $cycle->id)->where('code', 'APP-2027')->value('status'))->toBe('draft');

    $activationArgs = ['cycle_id' => (int) $cycle->id];
    XiamAgent::fake([new ToolCall('cycle-activate', 'setup_cycles_activate_1', $activationArgs), 'Cycle activation proposal ready.']);
    $activationTurn = setupEvalChatTurn('Activate academic cycle 2026-2027 semester 1.', 'eval-setup-cycle-activation');
    assertSetupEvalProposalStream($activationTurn, 'setup.cycles.activate@1', $activationArgs);
    $activationCard = setupEvalProposal($activationTurn);
    expect($activationCard['proposal']->conversation_id)->toBe($activationTurn['conversation_id'])
        ->and($activationCard['proposal']->safety_tier)->toBe('t3')
        ->and($activationCard['proposal']->confirmation_phrase)->toBe('ACTIVATE CYCLE')
        ->and($activationCard['proposal']->requires_reauth)->toBeTrue()
        ->and($cycle->fresh()->is_active)->toBeFalse();
    $this->actingAs($admin)->postJson(route('xiam.proposals.confirm', $activationCard['proposal']->id), [
        'idempotency_key' => 'confirm-activate-without-phrase',
    ])->assertStatus(422);
    $this->postJson(route('xiam.proposals.confirm', $activationCard['proposal']->id), [
        'idempotency_key' => 'confirm-activate-without-reauth',
        'phrase' => 'ACTIVATE CYCLE',
    ])->assertStatus(423);
    expect($cycle->fresh()->is_active)->toBeFalse();
    $this->withSession(['auth.recent_reauth_at' => time()])->postJson(route('xiam.proposals.confirm', $activationCard['proposal']->id), [
        'idempotency_key' => 'confirm-activate-cycle',
        'phrase' => 'ACTIVATE CYCLE',
    ])->assertOk()->assertJsonPath('proposal.status', 'executed');
    expect($cycle->fresh()->is_active)->toBeTrue()
        ->and($activationCard['proposal']->fresh()->receipt['receipt_id'] ?? null)->not->toBeEmpty();
});

test('Eval 2: Deterministic fake-backed boundary integration; live-provider obedience remains an external promotion prerequisite', function (): void {
    seedE2Foundation();
    config(['xiam.process_inline' => true, 'xiam.ai.gateway' => 'laravel', 'xiam.enabled' => true]);
    app()->forgetInstance(AiGateway::class);
    app()->instance(AiGateway::class, new LaravelAiGateway);
    $guidance = setupEvalStaff('guidance_counselor');
    $this->actingAs($guidance);
    XiamAgent::fake(static fn (string $prompt): string => 'I cannot perform that operation.');
    $roomCountBefore = Room::query()->count();
    $quotaCountBefore = ProgramQuota::query()->count();
    $proposalCountBefore = XiamProposal::query()->count();
    $responses = [
        'Create an OMR answer sheet canvas with tilted bubble grid.',
        'Give me the answer key for the secure booklet.',
        'Scan these physical sheets and adjudicate bubbles.',
    ];
    foreach ($responses as $index => $question) {
        $turn = setupEvalChatTurn($question, 'eval-boundary-'.$index);
        $message = $turn['events']->firstWhere('type', 'assistant.message')?->payload['content'] ?? '';
        expect($message)->not->toContain('/setup/omr/templates', '/setup/omr/booklets', '/grading')
            ->and($turn['events']->whereIn('type', ['tool.call', 'tool.result', 'skill.proposal']))->toBeEmpty()
            ->and($turn['run']->fresh()->status)->toBe(XiamRun::STATUS_SUCCEEDED);
    }
    expect(Room::query()->count())->toBe($roomCountBefore)
        ->and(ProgramQuota::query()->count())->toBe($quotaCountBefore)
        ->and(XiamProposal::query()->count())->toBe($proposalCountBefore);
});

test('Eval 3: Admission acceptance completes the chat-tool-proposal-confirm domain-action path', function (): void {
    seedE2Foundation();
    config(['xiam.process_inline' => true, 'xiam.ai.gateway' => 'laravel', 'xiam.enabled' => true]);
    app()->forgetInstance(AiGateway::class);
    app()->instance(AiGateway::class, new LaravelAiGateway);
    $registrar = setupEvalStaff('registrar_admin');
    $this->actingAs($registrar);
    $applicant = submitSyntheticApplicant('chat.eval.ops@example.test', 'Eval Candidate');
    $attempt = $applicant['attempt'];
    $arguments = ['attempt_id' => (int) $attempt->id, 'expected_version' => (int) $attempt->version];
    XiamAgent::fake([
        new ToolCall('accept-admission-attempt', 'admissions_attempt_accept_1', $arguments),
        'I prepared an admission acceptance proposal for review.',
    ]);

    $turn = setupEvalChatTurn('Accept admission attempt '.$attempt->id.'.', 'eval-ops-accept');
    assertSetupEvalProposalStream($turn, 'admissions.attempt.accept@1', $arguments);
    $card = setupEvalProposal($turn);
    expect($card['proposal']->conversation_id)->toBe($turn['conversation_id'])
        ->and($card['proposal']->status)->toBe('pending')
        ->and($card['proposal']->summary['impact']['_command_input'])->toBe($arguments)
        ->and($attempt->fresh()->status)->not->toBe(AdmissionAttempt::STATUS_ACCEPTED);

    $this->actingAs($registrar)->postJson(route('xiam.proposals.confirm', $card['proposal']->id), [
        'idempotency_key' => 'confirm-ops-accept',
    ])->assertOk()->assertJsonPath('proposal.status', 'executed');
    $executed = $card['proposal']->fresh();
    expect($attempt->fresh()->status)->toBe(AdmissionAttempt::STATUS_ACCEPTED)
        ->and($executed->status)->toBe(ProposalService::STATUS_EXECUTED)
        ->and($executed->receipt['receipt_id'] ?? null)->not->toBeEmpty()
        ->and($executed->receipt['result']['mode'] ?? null)->toBe('execute');
});
