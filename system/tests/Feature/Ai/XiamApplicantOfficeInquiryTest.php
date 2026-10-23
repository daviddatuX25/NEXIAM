<?php

use App\Admission\Support\ApplicantOfficeInquiryAccess;
use App\Ai\Adapters\LaravelAi\LaravelAiGateway;
use App\Ai\Adapters\LaravelAi\XiamAgent;
use App\Ai\Context\InvocationContext;
use App\Ai\Contracts\AiGateway;
use App\Ai\Gateway\AssistantToolCatalog;
use App\Ai\Proposals\ProposalService;
use App\Application\Capabilities\Handlers\Admission\OfficeInquiryOpenHandler;
use App\Application\Capabilities\Handlers\Admission\OfficeInquiryReplyHandler;
use App\Authorization\CapabilityAuthorizer;
use App\Authorization\CapabilityCatalog;
use App\Models\Admission\ApplicantOfficeInquiry;
use App\Models\Admission\ApplicantOfficeInquiryDemoActor;
use App\Models\Admission\ApplicantOfficeInquiryMessage;
use App\Models\Ai\XiamProposal;
use App\Models\Ai\XiamRun;
use App\Models\Ai\XiamRunEvent;
use App\Models\Foundation\Operation;
use App\Models\User;
use App\Notifications\NotificationDelivery;
use Database\Seeders\ApplicantOfficeInquiryDemoSeeder;
use Laravel\Ai\Responses\Data\ToolCall;

test('synthetic applicant chat proposes exact office drafts and confirms each ordinary action once on retry', function (): void {
    seedE2Foundation();
    $this->seed(ApplicantOfficeInquiryDemoSeeder::class);
    config(['xiam.process_inline' => true, 'xiam.ai.gateway' => 'laravel', 'xiam.enabled' => true]);
    app()->forgetInstance(AiGateway::class);
    app()->instance(AiGateway::class, new LaravelAiGateway);
    $actor = ApplicantOfficeInquiryDemoActor::query()
        ->where('namespace', ApplicantOfficeInquiryDemoActor::NAMESPACE)
        ->where('actor_type', ApplicantOfficeInquiryDemoActor::TYPE_APPLICANT)
        ->orderBy('id')->firstOrFail()->user;
    $this->actingAs($actor)->withHeader('X-Xiam-Browser-Session', 'office-inquiry-chat-eval');
    $this->postJson(route('xiam.mode.update'), ['mode' => 'assistant'])->assertOk();
    xiamKeepCurrentSessionCookie();
    $attempt = app(ApplicantOfficeInquiryAccess::class)->applicantAttempt($actor);
    $attemptBefore = $attempt->getAttributes();
    $rootsBefore = ApplicantOfficeInquiry::query()->count();
    $messagesBefore = ApplicantOfficeInquiryMessage::query()->count();
    $noticesBefore = NotificationDelivery::query()->where('event_type', 'admission.office_inquiry_message.v1')->count();

    $turn = function (string $message, string $key, ?string $conversationId = null, ?string $pagePackKey = 'applicant.inquiries.index@1'): array {
        $payload = [
            'message' => $message,
            'idempotency_key' => $key,
            'page_pack_key' => $pagePackKey,
            ...($pagePackKey === null ? [] : ['page_pack_version' => 1]),
            ...($conversationId === null ? [] : ['conversation_id' => $conversationId]),
        ];
        $accepted = $this->postJson(route('xiam.turns.store'), $payload)->assertAccepted()->json();
        $run = XiamRun::query()->findOrFail($accepted['run_id']);
        $events = XiamRunEvent::query()->where('run_id', $run->id)->orderBy('seq')->get();
        expect($run->status)->toBe(XiamRun::STATUS_SUCCEEDED);
        $toolResult = $events->firstWhere('type', 'tool.result');
        if ($toolResult !== null) {
            expect($toolResult->payload['successful'])->toBeTrue();
        }

        return ['conversation_id' => $accepted['conversation_id'], 'run_id' => $accepted['run_id'], 'events' => $events];
    };
    $proposalFor = function (array $turn, string $tool, array $command): XiamProposal {
        $event = $turn['events']->firstWhere('type', 'skill.proposal');
        expect($event)->not->toBeNull()
            ->and($turn['events']->firstWhere('type', 'tool.call')?->payload['tool'])->toBe($tool);
        $proposal = XiamProposal::query()->findOrFail($event->payload['proposal_id']);
        $this->getJson(route('xiam.conversations.snapshot', $turn['conversation_id']))
            ->assertOk()->assertJsonFragment(['id' => $proposal->id, 'status' => 'pending']);
        expect($proposal->summary['impact']['_command_input'])->toBe($command)
            ->and($proposal->conversation_id)->toBe($turn['conversation_id'])
            ->and($proposal->status)->toBe(ProposalService::STATUS_PENDING)
            ->and(json_encode($event->payload, JSON_THROW_ON_ERROR))->not->toContain('_command_input', 'command_input');

        return $proposal;
    };
    $confirmAndRetry = function (XiamProposal $proposal, string $key): array {
        $first = $this->postJson(route('xiam.proposals.confirm', $proposal->id), ['idempotency_key' => $key])
            ->assertOk()->assertJsonPath('proposal.status', 'executed')->json();
        $retry = $this->postJson(route('xiam.proposals.confirm', $proposal->id), ['idempotency_key' => $key])
            ->assertOk()->assertJsonPath('proposal.status', 'executed')->json();
        $proposal->refresh();
        expect($proposal->status)->toBe(ProposalService::STATUS_EXECUTED)
            ->and($proposal->receipt['result']['receipt_id'] ?? null)->not->toBeEmpty()
            ->and($retry['result'])->toBe($first['result'])
            ->and(Operation::query()->where('idempotency_key', 'like', '%:'.$proposal->execution_id)->count())->toBe(1);

        return $proposal->receipt['result'];
    };

    // Only SDK model selection is fake; ingress, gateway, proposal, Actions and notices are real.
    XiamAgent::fake([new ToolCall('inquiry-options', 'admission_office_inquiries_options_1', []), 'Synthetic office options are available.']);
    $optionsTurn = $turn('Which offices can receive my synthetic inquiry?', 'office-chat-options');
    expect($optionsTurn['events']->firstWhere('type', 'tool.call')?->payload['tool'])
        ->toBe('admission.office_inquiries.options@1')
        ->and($optionsTurn['events']->firstWhere('type', 'knowledge.retrieval'))->toBeNull();

    $draft = ['office' => 'registrar', 'subject' => 'Synthetic document question', 'body' => 'Please explain the synthetic checklist, without granting an admission exception.'];
    expect(fn () => app(OfficeInquiryOpenHandler::class)->handle([...$draft, 'execute' => true], $actor))
        ->toThrow(RuntimeException::class, 'action_execution_identity_invalid');
    XiamAgent::fake([new ToolCall('inquiry-open', 'admission_office_inquiries_open_1', $draft), 'Review the exact synthetic message before sending.']);
    $openTurn = $turn('Draft this Registrar inquiry: '.$draft['body'], 'office-chat-open', $optionsTurn['conversation_id']);
    $openProposal = $proposalFor($openTurn, 'admission.office_inquiries.open@1', $draft);
    expect($openProposal->summary['impact']['office_label'])->toBe('Registrar')
        ->and($openProposal->summary['impact']['subject'])->toBe($draft['subject'])
        ->and($openProposal->summary['impact']['body'])->toBe($draft['body'])
        ->and(ApplicantOfficeInquiry::query()->count())->toBe($rootsBefore)
        ->and(ApplicantOfficeInquiryMessage::query()->count())->toBe($messagesBefore)
        ->and(NotificationDelivery::query()->where('event_type', 'admission.office_inquiry_message.v1')->count())->toBe($noticesBefore);
    $openReceipt = $confirmAndRetry($openProposal, 'office-confirm-open');

    $sessionView = $this->getJson(route('xiam.mode.show'))->assertOk()->json();
    $demotion = $this->postJson(route('xiam.mode.update'), [
        'mode' => 'inquiry',
        'context_epoch' => $sessionView['context_epoch'],
    ])->assertOk()->assertJsonPath('active_mode', 'inquiry')->json();
    xiamKeepCurrentSessionCookie();
    $this->postJson(route('xiam.proposals.confirm', $openProposal->id), [
        'idempotency_key' => 'office-confirm-open',
    ])->assertOk()
        ->assertJsonPath('proposal.status', ProposalService::STATUS_EXECUTED)
        ->assertJsonPath('result.inquiry_id', $openReceipt['inquiry_id']);
    $this->postJson(route('xiam.mode.update'), [
        'mode' => 'assistant',
        'context_epoch' => $demotion['context_epoch'],
    ])->assertOk()->assertJsonPath('active_mode', 'assistant');
    xiamKeepCurrentSessionCookie();

    $inquiry = ApplicantOfficeInquiry::query()->findOrFail($openReceipt['inquiry_id']);
    $firstMessage = ApplicantOfficeInquiryMessage::query()->findOrFail($openReceipt['message_id']);
    expect(ApplicantOfficeInquiry::query()->count())->toBe($rootsBefore + 1)
        ->and(ApplicantOfficeInquiryMessage::query()->count())->toBe($messagesBefore + 1)
        ->and((string) $inquiry->admission_attempt_id)->toBe((string) $attempt->id)
        ->and((string) $inquiry->applicant_identity_id)->toBe((string) $attempt->applicant_identity_id)
        ->and((string) $firstMessage->applicant_office_inquiry_id)->toBe((string) $inquiry->id)
        ->and($firstMessage->body)->toBe($draft['body'])
        ->and($openReceipt['href'])->toBe(route('applicant.inquiries.show', ['inquiry' => $inquiry->id], false));

    $reply = ['inquiry_id' => (int) $inquiry->id, 'body' => 'Synthetic follow-up: please clarify the checklist only.'];
    expect(fn () => app(OfficeInquiryReplyHandler::class)->handle([...$reply, 'execute' => true], $actor))
        ->toThrow(RuntimeException::class, 'action_execution_identity_invalid');
    XiamAgent::fake([new ToolCall('inquiry-reply', 'admission_office_inquiries_reply_1', $reply), 'Review this exact follow-up before sending.']);
    $replyTurn = $turn('Draft a follow-up to my inquiry: '.$reply['body'], 'office-chat-reply', $openTurn['conversation_id']);
    $replyProposal = $proposalFor($replyTurn, 'admission.office_inquiries.reply@1', $reply);
    expect(ApplicantOfficeInquiryMessage::query()->where('applicant_office_inquiry_id', $inquiry->id)->count())->toBe(1)
        ->and($replyProposal->summary['impact']['body'])->toBe($reply['body']);
    $replyReceipt = $confirmAndRetry($replyProposal, 'office-confirm-reply');
    expect((string) $replyReceipt['inquiry_id'])->toBe((string) $inquiry->id)
        ->and($replyReceipt['sequence'])->toBe('2')
        ->and(ApplicantOfficeInquiry::query()->count())->toBe($rootsBefore + 1)
        ->and(ApplicantOfficeInquiryMessage::query()->where('applicant_office_inquiry_id', $inquiry->id)->count())->toBe(2)
        ->and($attempt->fresh()->getAttributes())->toBe($attemptBefore);

    $notices = NotificationDelivery::query()->where('event_type', 'admission.office_inquiry_message.v1')
        ->where('payload->inquiry_id', (string) $inquiry->id)->get();
    expect($notices)->not->toBeEmpty()
        ->and($notices->pluck('payload.message_id')->unique()->sort()->values()->all())
        ->toBe(collect([$openReceipt['message_id'], $replyReceipt['message_id']])->sort()->values()->all());
    foreach ($notices as $notice) {
        expect($notice->channel)->toBe(NotificationDelivery::CHANNEL_IN_APP)
            ->and($notice->recipient_type)->toBe(User::class)
            ->and((string) $notice->recipient_id)->not->toBe((string) $actor->id)
            ->and($notice->payload['attempt_id'])->toBe((string) $attempt->id)
            ->and($notice->payload['subject'])->toBe('New office inquiry message')
            ->and(json_encode($notice->payload, JSON_THROW_ON_ERROR))->not->toContain($draft['body'], $draft['subject'], $reply['body']);
    }
    $this->withHeader('X-Xiam-Browser-Session', 'office-inquiry-cross-page-null-pack');
    // Model-led handoff remains discoverable from a turn with no page pack.
    $unavailableAnswer = 'There are no currently available examination slots.';
    XiamAgent::fake([$unavailableAnswer]);
    $noSlotsTurn = $turn('Are there any open exam slots?', 'office-chat-no-slots', null, null);

    $handoffRequest = 'can i chat a staff.. tell them i want to switch courses';
    XiamAgent::fake([
        new ToolCall('inquiry-options', 'admission_office_inquiries_options_1', []),
        'Registrar and Guidance can receive office inquiries. Which office would you like?',
    ]);
    $optionsTurn = $turn($handoffRequest, 'office-chat-cross-page-options', $noSlotsTurn['conversation_id'], null);
    expect($optionsTurn['events']->firstWhere('type', 'tool.call')?->payload['tool'])
        ->toBe('admission.office_inquiries.options@1')
        ->and(ApplicantOfficeInquiry::query()->count())->toBe($rootsBefore + 1)
        ->and(ApplicantOfficeInquiryMessage::query()->count())->toBe($messagesBefore + 2);

    $handoffDraft = [
        'office' => 'registrar',
        'subject' => 'Request to discuss switching courses',
        'body' => 'I want to switch courses.',
    ];
    XiamAgent::fake([
        new ToolCall('inquiry-open', 'admission_office_inquiries_open_1', $handoffDraft),
        'Review the exact Registrar inquiry before sending.',
    ]);
    $proposalTurn = $turn(
        'Registrar, and tell them exactly that I want to switch courses.',
        'office-chat-cross-page-proposal',
        $optionsTurn['conversation_id'],
        null,
    );
    $handoffProposal = $proposalFor($proposalTurn, 'admission.office_inquiries.open@1', $handoffDraft);
    expect($handoffProposal->summary['impact']['body'])->toBe($handoffDraft['body'])
        ->and(ApplicantOfficeInquiry::query()->count())->toBe($rootsBefore + 1)
        ->and(ApplicantOfficeInquiryMessage::query()->count())->toBe($messagesBefore + 2)
        ->and($attempt->fresh()->getAttributes())->toBe($attemptBefore);

    ApplicantOfficeInquiryDemoActor::query()->where('user_id', $actor->id)->delete();
    $actor->revokePermissionTo(CapabilityCatalog::APPLICANT_OFFICE_INQUIRY_USE);
    $context = new InvocationContext(
        actorId: (string) $actor->id,
        actorType: 'applicant',
        institutionId: $attempt->institution_id,
        surfaceProfile: 'applicant',
        mode: 'assistant',
        contextEpoch: XiamRun::query()->findOrFail($proposalTurn['run_id'])->conversation->context_epoch,
        purpose: 'assistant_turn',
        correlationId: 'disabled-office-inquiry-tools',
    );
    $catalog = app(AssistantToolCatalog::class);
    $toolIds = collect($catalog->forActor($actor, $context, app(CapabilityAuthorizer::class)))
        ->map(fn ($tool): string => $tool->id());
    expect(app(ApplicantOfficeInquiryAccess::class)->availability($actor)['available'])->toBeFalse()
        ->and($toolIds->filter(fn (string $id): bool => str_starts_with($id, 'admission.office_inquiries.'))->all())->toBe([]);
});
