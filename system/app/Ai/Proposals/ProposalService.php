<?php

namespace App\Ai\Proposals;

use App\Ai\Context\InvocationContext;
use App\Ai\Gateway\AssistantToolGateway;
use App\Ai\Grants\TaskGrantService;
use App\Ai\Orchestration\RunEventAppender;
use App\Ai\Policy\EmergencyStop;
use App\Ai\Skills\SkillEngine;
use App\Models\Ai\XiamConversation;
use App\Models\Ai\XiamInstitutionPolicy;
use App\Models\Ai\XiamProposal;
use App\Models\Ai\XiamRun;
use App\Models\Ai\XiamTaskGrant;
use App\Models\Ai\XiamTurn;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;

/**
 * Durable proposal create/confirm with a server-owned execution identity.
 */
final class ProposalService
{
    public const STATUS_PENDING = 'pending';

    public const STATUS_EXECUTING = 'executing';

    public const STATUS_EXECUTED = 'executed';

    public const STATUS_CONFLICTED = 'conflicted';

    public const STATUS_FAILED = 'failed';

    public const STATUS_INVALIDATED = 'invalidated';

    public const STATUS_CANCELLED = 'cancelled';

    public function __construct(
        private readonly AssistantToolGateway $gateway,
        private readonly SkillEngine $skills,
        private readonly RunEventAppender $events,
        private readonly EmergencyStop $emergencyStop,
        private readonly TaskGrantService $grants,
        private readonly ProposalSafetyPolicy $safetyPolicy,
    ) {}

    /**
     * @param  array<string, mixed>  $summary
     * @param  array<string, mixed>  $evidence
     * @param  ProposalReview|array<string, mixed>|null  $proposalView
     * @param  ProposalReview|array<string, mixed>|null  $proposalReview
     */
    public function create(
        User $actor,
        ?string $runId,
        string $conversationId,
        string $capabilityId,
        array $summary,
        array $evidence,
        ?XiamTaskGrant $grant = null,
        ?int $expectedVersion = null,
        ProposalReview|array|null $proposalView = null,
        ProposalReview|array|null $proposalReview = null,
    ): XiamProposal {
        $reviewCandidate = $proposalReview ?? $proposalView;
        $versionedProposalView = null;
        $normalizedCommandInput = null;

        if ($reviewCandidate instanceof ProposalReview) {
            $versionedProposalView = $reviewCandidate->review;
            $normalizedCommandInput = $reviewCandidate->commandInput;
        } elseif (is_array($reviewCandidate) && isset($reviewCandidate['version']) && $reviewCandidate['version'] === 1 && isset($reviewCandidate['sections'])) {
            $versionedProposalView = $reviewCandidate;
            $normalizedCommandInput = is_array($summary['_command_input'] ?? null)
                ? $summary['_command_input']
                : (is_array($reviewCandidate['command_input'] ?? null) ? $reviewCandidate['command_input'] : null);
        } elseif (is_array($reviewCandidate) && isset($reviewCandidate['proposal_review']) && is_array($reviewCandidate['proposal_review'])) {
            $versionedProposalView = $reviewCandidate['proposal_review'];
            $normalizedCommandInput = is_array($reviewCandidate['command_input'] ?? null)
                ? $reviewCandidate['command_input']
                : (is_array($summary['_command_input'] ?? null) ? $summary['_command_input'] : null);
        }

        if (! $reviewCandidate instanceof ProposalReview && is_array($versionedProposalView)) {
            $cmdCandidate = $normalizedCommandInput ?? ($summary['_command_input'] ?? null);
            $constructed = ProposalReview::fromResult($capabilityId, [
                'command_input' => is_array($cmdCandidate) ? $cmdCandidate : [],
                'proposal_review' => $versionedProposalView,
            ]);
            $normalizedCommandInput = $constructed->commandInput;
            $versionedProposalView = $constructed->review;
        }

        if ($normalizedCommandInput !== null) {
            $summary['_command_input'] = $normalizedCommandInput;
        }
        $conversation = XiamConversation::query()->find($conversationId);
        if ($conversation === null || (string) $conversation->actor_id !== (string) $actor->id || $conversation->status !== 'open') {
            throw new RuntimeException('conversation_denied');
        }

        $institutionId = $conversation->institution_id === null ? null : (int) $conversation->institution_id;
        if ($this->emergencyStop->active($institutionId)) {
            if ($grant !== null) {
                $this->grants->revoke($grant, 'emergency_stop');
            }

            throw new RuntimeException('emergency_stop');
        }

        if ($conversation->actor_type === 'applicant') {
            if (! $this->applicantPolicyAllows($institutionId)) {
                throw new RuntimeException('applicant_ai_disabled');
            }

            if ($capabilityId === 'exams.slot.reserve@1' && ! $this->applicantPolicyAllows($institutionId, requiresSelfBooking: true)) {
                throw new RuntimeException('applicant_self_booking_disabled');
            }
        }

        if ($conversation->actor_type === 'staff') {
            $staffProfile = $actor->staffProfile()->first();
            if ($staffProfile !== null && ! $staffProfile->isActive()) {
                throw new RuntimeException('actor_revoked');
            }
        } elseif ($conversation->actor_type === 'applicant') {
            if (! $actor->applicantAccount()->exists()) {
                throw new RuntimeException('actor_revoked');
            }
        }

        if ($grant !== null) {
            if (! $grant->isUsable()) {
                throw new RuntimeException('grant_unusable');
            }

            if ((string) $grant->actor_id !== (string) $actor->id) {
                throw new RuntimeException('grant_actor_mismatch');
            }

            if ((string) $grant->context_epoch !== (string) $conversation->context_epoch) {
                $this->grants->revoke($grant, 'stale_context_epoch');
                throw new RuntimeException('stale_context_epoch');
            }

            $allowed = $grant->allowed_capability_ids ?? [];
            if (! in_array($capabilityId, $allowed, true)) {
                throw new RuntimeException('capability_not_on_skill_allowlist');
            }

            $targetInput = is_array($summary['_command_input'] ?? null)
                ? $summary['_command_input']
                : $summary;
            $this->skills->assertTargetAllowed($grant, $capabilityId, $targetInput);
        }

        if ($runId === null) {
            DB::transaction(function () use ($conversationId, &$runId): void {
                if (DB::getDriverName() === 'pgsql') {
                    DB::statement('set transaction isolation level repeatable read');
                }

                $conversation = XiamConversation::query()->lockForUpdate()->findOrFail($conversationId);
                $turnId = (string) Str::uuid();
                $runId = (string) Str::uuid();
                $seq = (int) XiamTurn::query()
                    ->where('conversation_id', $conversation->id)
                    ->max('seq') + 1;
                XiamTurn::query()->create([
                    'id' => $turnId,
                    'conversation_id' => $conversation->id,
                    'seq' => $seq,
                    'mode' => 'assistant',
                    'input_text' => '[skill proposal]',
                ]);
                XiamRun::query()->create([
                    'id' => $runId,
                    'turn_id' => $turnId,
                    'conversation_id' => $conversation->id,
                    'status' => XiamRun::STATUS_SUCCEEDED,
                    'publication_version' => 'xiam.proposal.v1',
                    'gateway_route' => 'skill',
                    'started_at' => now(),
                    'finished_at' => now(),
                ]);
            });
        }

        $command = is_array($summary['_command_input'] ?? null)
            ? $summary['_command_input']
            : $summary;
        $safety = $this->safetyPolicy->evaluate($capabilityId, $command);
        $executionId = (string) Str::uuid();
        $proposalSummary = [
            'impact' => $summary,
            'evidence' => $evidence,
            'actor_id' => (string) $actor->id,
            'actor_type' => (string) $conversation->actor_type,
            'institution_id' => $conversation->institution_id === null ? null : (int) $conversation->institution_id,
            'surface_key' => $grant?->surface_key ?? (string) ($conversation->surface_key ?: 'staff'),
            'context_epoch' => (string) $conversation->context_epoch,
            'grant_id' => $grant?->id,
            'execution_id' => $executionId,
            'safety_tier' => $safety->tier,
            'confirmation_phrase' => $safety->confirmationPhrase,
        ];

        if ($versionedProposalView !== null) {
            $storedProposalView = $versionedProposalView;
        } else {
            $storedProposalView = is_array($proposalView) ? $proposalView : [
                'summary' => $summary,
                'evidence' => $evidence,
                'diff' => null,
                'preflight_checks' => $evidence['checks'] ?? null,
            ];
            $storedProposalView['safety_tier'] = $safety->tier;
            $storedProposalView['confirmation_phrase'] = $safety->confirmationPhrase;
        }
        $proposal = XiamProposal::query()->create([
            'id' => (string) Str::uuid(),
            'run_id' => $runId,
            'conversation_id' => $conversationId,
            'status' => self::STATUS_PENDING,
            'capability_id' => $capabilityId,
            'expected_version' => $expectedVersion,
            'execution_id' => $executionId,
            'canonical_digest' => $this->canonicalDigest([
                'capability_id' => $capabilityId,
                'execution_id' => $executionId,
                'safety_tier' => $safety->tier,
                'confirmation_phrase' => $safety->confirmationPhrase,
                'summary' => $proposalSummary,
            ]),
            'summary' => $proposalSummary,
            'proposal_view' => $storedProposalView,
            'safety_tier' => $safety->tier,
            'confirmation_phrase' => $safety->confirmationPhrase,
            'requires_reauth' => $safety->requiresReauth,
            'expires_at' => now()->addMinutes($safety->ttlMinutes),
        ]);

        if ($grant !== null) {
            $this->skills->markAwaitingConfirm($grant, $proposal->id);
        }

        return $proposal;
    }

    /**
     * @return array{proposal: XiamProposal, result: array<string, mixed>}
     */
    public function confirm(User $actor, string $proposalId, string $idempotencyKey, string $mode = 'assistant'): array
    {
        if (! in_array($mode, ['inquiry', 'assistant'], true)) {
            throw new RuntimeException('mode_unavailable');
        }
        if (trim($idempotencyKey) === '') {
            throw new RuntimeException('confirmation_idempotency_required');
        }

        try {
            /** @var array{state: string, proposal: XiamProposal, conversation?: XiamConversation, grant?: XiamTaskGrant|null} $claim */
            $claim = DB::transaction(function () use ($actor, $proposalId, $idempotencyKey, $mode): array {
                $proposal = XiamProposal::query()->lockForUpdate()->find($proposalId);
                if ($proposal === null) {
                    throw new RuntimeException('proposal_not_found');
                }

                $summary = is_array($proposal->summary) ? $proposal->summary : [];
                if ((string) ($summary['actor_id'] ?? '') !== (string) $actor->id) {
                    throw new RuntimeException('proposal_actor_mismatch');
                }

                $institutionId = XiamConversation::query()
                    ->whereKey($proposal->conversation_id)
                    ->value('institution_id');
                $institutionId = $institutionId === null ? null : (int) $institutionId;

                if ($this->emergencyStop->active($institutionId)
                    || ($proposal->status === self::STATUS_INVALIDATED && $proposal->failure_reason === 'emergency_stop')) {
                    if (in_array($proposal->status, [self::STATUS_PENDING, self::STATUS_EXECUTING], true)) {
                        $proposal->forceFill([
                            'status' => self::STATUS_INVALIDATED,
                            'failure_reason' => 'emergency_stop',
                        ])->save();
                        $this->clearGrantAwaiting($proposal);
                        $this->grants->revokeAllActive('emergency_stop');
                    }

                    throw new RuntimeException('emergency_stop');
                }

                $expectedDigest = $this->canonicalDigest([
                    'capability_id' => (string) $proposal->capability_id,
                    'execution_id' => (string) $proposal->execution_id,
                    'safety_tier' => (string) $proposal->safety_tier,
                    'confirmation_phrase' => $proposal->confirmation_phrase,
                    'summary' => $summary,
                ]);
                if (
                    trim((string) $proposal->execution_id) === ''
                    || (string) ($summary['execution_id'] ?? '') !== (string) $proposal->execution_id
                    || ! hash_equals($expectedDigest, (string) $proposal->canonical_digest)
                ) {
                    $proposal->forceFill([
                        'status' => self::STATUS_INVALIDATED,
                        'failure_reason' => 'proposal_digest_mismatch',
                    ])->save();
                    $this->clearGrantAwaiting($proposal);
                    throw new RuntimeException('proposal_digest_mismatch');
                }

                if ($proposal->status === self::STATUS_EXECUTED) {
                    if ($proposal->confirmation_idempotency_key !== $idempotencyKey) {
                        throw new RuntimeException('proposal_not_confirmable');
                    }

                    return [
                        'state' => 'already_executed',
                        'proposal' => $proposal,
                    ];
                }

                $commandInput = $this->extractCommandInput($proposal);

                if ($proposal->status === self::STATUS_EXECUTING) {
                    if ($proposal->confirmation_idempotency_key !== $idempotencyKey) {
                        throw new RuntimeException('proposal_claim_conflict');
                    }

                    if (is_array($proposal->receipt) && is_array($proposal->receipt['result'] ?? null)) {
                        $proposal->forceFill([
                            'status' => self::STATUS_EXECUTED,
                            'failure_reason' => null,
                        ])->save();

                        return [
                            'state' => 'recovered_receipt',
                            'proposal' => $proposal->fresh() ?? $proposal,
                        ];
                    }
                }

                if ($mode === 'inquiry') {
                    throw new RuntimeException('mode_inquiry_read_only');
                }

                if (! in_array($proposal->status, [self::STATUS_PENDING, self::STATUS_EXECUTING], true)) {
                    throw new RuntimeException('proposal_not_confirmable');
                }

                if ($proposal->expires_at !== null && $proposal->expires_at->isPast()) {
                    $proposal->forceFill([
                        'status' => self::STATUS_INVALIDATED,
                        'failure_reason' => 'proposal_expired',
                    ])->save();
                    $this->clearGrantAwaiting($proposal);
                    throw new RuntimeException('proposal_expired');
                }

                $conversation = XiamConversation::query()->lockForUpdate()->find($proposal->conversation_id);
                if ($conversation === null || $conversation->status !== 'open') {
                    $proposal->forceFill([
                        'status' => self::STATUS_INVALIDATED,
                        'failure_reason' => 'conversation_not_found',
                    ])->save();
                    $this->clearGrantAwaiting($proposal);
                    throw new RuntimeException('conversation_not_found');
                }

                if ((string) $conversation->actor_id !== (string) $actor->id) {
                    throw new RuntimeException('proposal_actor_mismatch');
                }
                if ($conversation->actor_type === 'applicant') {
                    $institutionId = $conversation->institution_id === null ? null : (int) $conversation->institution_id;
                    if (! $this->applicantPolicyAllows($institutionId)) {
                        throw new RuntimeException('applicant_ai_disabled');
                    }

                    if ($proposal->capability_id === 'exams.slot.reserve@1'
                        && ! $this->applicantPolicyAllows($institutionId, requiresSelfBooking: true)) {
                        throw new RuntimeException('applicant_self_booking_disabled');
                    }
                }

                if ((string) ($summary['context_epoch'] ?? '') !== (string) $conversation->context_epoch) {
                    $proposal->forceFill([
                        'status' => self::STATUS_INVALIDATED,
                        'failure_reason' => 'stale_context_epoch',
                    ])->save();
                    $this->clearGrantAwaiting($proposal);
                    throw new RuntimeException('stale_context_epoch');
                }

                if ($conversation->actor_type === 'staff') {
                    $staffProfile = $actor->staffProfile()->first();
                    if (
                        ($staffProfile !== null && ! $staffProfile->isActive())
                        || ($staffProfile !== null && $conversation->institution_id !== null && (int) $staffProfile->institution_id !== (int) $conversation->institution_id)
                    ) {
                        $proposal->forceFill([
                            'status' => self::STATUS_INVALIDATED,
                            'failure_reason' => 'actor_revoked',
                        ])->save();
                        $this->clearGrantAwaiting($proposal);
                        $this->grants->revokeAllForActor('staff', (string) $actor->id, 'actor_revoked');
                        throw new RuntimeException('actor_revoked');
                    }
                } elseif ($conversation->actor_type === 'applicant') {
                    if (! $actor->applicantAccount()->exists()) {
                        $proposal->forceFill([
                            'status' => self::STATUS_INVALIDATED,
                            'failure_reason' => 'actor_revoked',
                        ])->save();
                        $this->clearGrantAwaiting($proposal);
                        $this->grants->revokeAllForActor('applicant', (string) $actor->id, 'actor_revoked');
                        throw new RuntimeException('actor_revoked');
                    }
                }

                $grantId = isset($summary['grant_id']) && is_string($summary['grant_id']) && $summary['grant_id'] !== ''
                    ? $summary['grant_id']
                    : null;
                $grant = $this->resolveProposalGrant($proposal, $actor, $grantId);

                if ($grantId !== null || $grant !== null) {
                    if ($grant === null || ! $grant->isUsable()) {
                        $proposal->forceFill([
                            'status' => self::STATUS_INVALIDATED,
                            'failure_reason' => 'grant_unusable',
                        ])->save();
                        throw new RuntimeException('grant_unusable');
                    }

                    if ((string) $grant->actor_id !== (string) $actor->id) {
                        $proposal->forceFill([
                            'status' => self::STATUS_INVALIDATED,
                            'failure_reason' => 'grant_actor_mismatch',
                        ])->save();
                        throw new RuntimeException('grant_actor_mismatch');
                    }

                    if ((string) $grant->context_epoch !== (string) $conversation->context_epoch) {
                        $this->grants->revoke($grant, 'stale_context_epoch');
                        $proposal->forceFill([
                            'status' => self::STATUS_INVALIDATED,
                            'failure_reason' => 'stale_context_epoch',
                        ])->save();
                        throw new RuntimeException('stale_context_epoch');
                    }

                    $allowed = $grant->allowed_capability_ids ?? [];
                    if (! in_array((string) $proposal->capability_id, $allowed, true)) {
                        $proposal->forceFill([
                            'status' => self::STATUS_INVALIDATED,
                            'failure_reason' => 'capability_not_on_skill_allowlist',
                        ])->save();
                        $this->clearGrantAwaiting($proposal);
                        throw new RuntimeException('capability_not_on_skill_allowlist');
                    }

                    $pendingProposalId = $grant->scope_bounds['pending_proposal_id'] ?? null;
                    if ($pendingProposalId !== null && (string) $pendingProposalId !== (string) $proposal->id) {
                        $proposal->forceFill([
                            'status' => self::STATUS_INVALIDATED,
                            'failure_reason' => 'grant_proposal_mismatch',
                        ])->save();
                        throw new RuntimeException('grant_proposal_mismatch');
                    }

                    try {
                        $this->skills->assertTargetAllowed($grant, (string) $proposal->capability_id, $commandInput);
                    } catch (RuntimeException $e) {
                        $proposal->forceFill([
                            'status' => self::STATUS_INVALIDATED,
                            'failure_reason' => $e->getMessage(),
                        ])->save();
                        $this->clearGrantAwaiting($proposal);
                        throw $e;
                    }
                }

                if ($proposal->status === self::STATUS_PENDING) {
                    $claimed = XiamProposal::query()
                        ->whereKey($proposal->id)
                        ->where('status', self::STATUS_PENDING)
                        ->update([
                            'status' => self::STATUS_EXECUTING,
                            'confirmation_idempotency_key' => $idempotencyKey,
                            'claimed_at' => now(),
                        ]);

                    if ($claimed !== 1) {
                        throw new RuntimeException('proposal_claim_conflict');
                    }
                }

                return [
                    'state' => 'claimed',
                    'proposal' => $proposal->fresh() ?? $proposal,
                    'conversation' => $conversation,
                    'grant' => $grant,
                ];
            });
        } catch (RuntimeException $e) {
            $invalidatingReasons = [
                'emergency_stop',
                'grant_target_mismatch',
                'proposal_digest_mismatch',
                'stale_context_epoch',
                'actor_revoked',
                'grant_unusable',
                'grant_actor_mismatch',
            ];
            if (in_array($e->getMessage(), $invalidatingReasons, true)) {
                XiamProposal::query()->whereKey($proposalId)->update([
                    'status' => self::STATUS_INVALIDATED,
                    'failure_reason' => $e->getMessage(),
                ]);
            }
            throw $e;
        }

        /** @var XiamProposal $proposal */
        $proposal = $claim['proposal'];

        if ($claim['state'] === 'already_executed') {
            return [
                'proposal' => $proposal,
                'result' => is_array($proposal->receipt['result'] ?? null) ? $proposal->receipt['result'] : [],
            ];
        }

        if ($claim['state'] === 'recovered_receipt') {
            $resultData = is_array($proposal->receipt['result'] ?? null) ? $proposal->receipt['result'] : [];
            $this->clearGrantAwaiting($proposal);

            return [
                'proposal' => $proposal,
                'result' => $resultData,
            ];
        }

        $summary = is_array($proposal->summary) ? $proposal->summary : [];
        /** @var XiamConversation $conversation */
        $conversation = $claim['conversation'] ?? XiamConversation::query()->findOrFail($proposal->conversation_id);
        $surfaceKey = (string) ($summary['surface_key'] ?? ($conversation->surface_key ?: ($conversation->actor_type === 'applicant' ? 'applicant' : 'staff')));
        $capabilityId = (string) $proposal->capability_id;
        if (! str_contains($capabilityId, '@')) {
            $this->failClaim($proposal, 'capability_id_invalid');
            $this->clearGrantAwaiting($proposal);
            throw new RuntimeException('capability_id_invalid');
        }

        $commandInput = $this->extractCommandInput($proposal);
        $grantId = isset($summary['grant_id']) && is_string($summary['grant_id']) && $summary['grant_id'] !== ''
            ? $summary['grant_id']
            : ($claim['grant']?->id ?? null);

        $context = new InvocationContext(
            actorId: (string) $actor->id,
            actorType: (string) $conversation->actor_type,
            institutionId: $conversation->institution_id === null ? null : (int) $conversation->institution_id,
            surfaceProfile: $surfaceKey,
            mode: 'assistant',
            contextEpoch: (string) $conversation->context_epoch,
            purpose: 'proposal_confirmation',
            correlationId: (string) $proposal->id,
            executionId: (string) $proposal->execution_id,
            canonicalDigest: (string) $proposal->canonical_digest,
            grantId: $grantId,
        );

        $deniedByKernel = false;
        $data = [];

        try {
            $proposal = DB::transaction(function () use (
                $proposal,
                $actor,
                $capabilityId,
                $commandInput,
                $context,
                $idempotencyKey,
                &$deniedByKernel,
                &$data,
            ): XiamProposal {
                $locked = XiamProposal::query()->lockForUpdate()->findOrFail($proposal->id);
                if (
                    $locked->status !== self::STATUS_EXECUTING
                    || (string) $locked->confirmation_idempotency_key !== $idempotencyKey
                ) {
                    throw new RuntimeException('proposal_claim_conflict');
                }

                if ($this->emergencyStop->active($context->institutionId)) {

                    throw new RuntimeException('emergency_stop');
                }

                $result = $this->gateway->invoke(
                    actor: $actor,
                    context: $context,
                    toolId: $capabilityId,
                    input: $commandInput,
                    serverConfirmed: true,
                );

                if (! $result->ok) {
                    $deniedByKernel = $result->status === 'denied' || $result->reason === 'assistant_tool_not_registered';
                    $reason = is_string($result->reason) && $result->reason !== ''
                        ? $result->reason
                        : 'confirm_denied';

                    throw new RuntimeException($reason);
                }

                $data = is_array($result->data) ? $result->data : [];
                $receipt = $this->buildReceipt($locked, $idempotencyKey, $data);

                $locked->forceFill([
                    'status' => self::STATUS_EXECUTED,
                    'receipt' => $receipt,
                    'failure_reason' => null,
                ])->save();

                return $locked->fresh() ?? $locked;
            });
        } catch (Throwable $e) {
            $reason = $e->getMessage() !== '' ? $e->getMessage() : 'proposal_execution_failed';
            $failureStatus = $deniedByKernel ? self::STATUS_CONFLICTED : self::STATUS_FAILED;
            $this->failClaim($proposal, $reason, $failureStatus);
            $this->clearGrantAwaiting($proposal);
            $this->appendProposalEvent(
                $proposal,
                $deniedByKernel ? 'skill.proposal_conflicted' : 'skill.proposal_failed',
                [
                    'proposal_id' => $proposal->id,
                    'error' => $reason,
                ],
            );

            if ($e instanceof RuntimeException) {
                throw $e;
            }

            throw new RuntimeException($reason, 0, $e);
        }

        $receipt = is_array($proposal->receipt) ? $proposal->receipt : [];
        $this->clearGrantAwaiting($proposal);
        $this->appendProposalEvent($proposal, 'skill.proposal_confirmed', [
            'proposal_id' => $proposal->id,
            'capability_id' => $capabilityId,
            'execution_id' => $proposal->execution_id,
            'receipt_id' => $receipt['receipt_id'] ?? $this->deterministicReceiptId((string) $proposal->execution_id),
            'result' => $data,
        ]);

        return [
            'proposal' => $proposal,
            'result' => $data,
        ];
    }

    public function cancel(User $actor, string $proposalId): XiamProposal
    {
        $proposal = XiamProposal::query()->find($proposalId);
        if ($proposal === null) {
            throw new RuntimeException('proposal_not_found');
        }

        $summary = $proposal->summary ?? [];
        if ((string) ($summary['actor_id'] ?? '') !== (string) $actor->id) {
            throw new RuntimeException('proposal_actor_mismatch');
        }

        $cancelled = XiamProposal::query()
            ->whereKey($proposal->id)
            ->where('status', self::STATUS_PENDING)
            ->update(['status' => self::STATUS_CANCELLED]);

        if ($cancelled === 1) {
            $proposal->refresh();
            $this->appendProposalEvent($proposal, 'skill.proposal_cancelled', [
                'proposal_id' => $proposal->id,
            ]);
        }

        $this->clearGrantAwaiting($proposal);

        return $proposal->fresh() ?? $proposal;
    }

    private function failClaim(XiamProposal $proposal, string $reason, string $status = self::STATUS_FAILED): void
    {
        if (in_array($reason, [
            'stale_context_epoch',
            'stale_session_version',
            'Stale exam session roster or material state.',
            'only_draft_may_publish',
            'assign_required_before_publish',
            'attempt_not_exam_eligible',
            'target_not_found',
            'session_not_found',
            'emergency_stop',
            'grant_unusable',
            'grant_target_mismatch',
            'actor_revoked',
        ], true) || str_starts_with(mb_strtolower($reason), 'stale ') || str_starts_with($reason, 'envelope_denied:')) {
            $status = self::STATUS_INVALIDATED;
        }

        XiamProposal::query()
            ->whereKey($proposal->id)
            ->where('status', self::STATUS_EXECUTING)
            ->update([
                'status' => $status,
                'failure_reason' => $reason,
            ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function extractCommandInput(XiamProposal $proposal): array
    {
        $summary = is_array($proposal->summary) ? $proposal->summary : [];
        $impact = is_array($summary['impact'] ?? null) ? $summary['impact'] : [];

        if (is_array($summary['_command_input'] ?? null)) {
            $commandInput = $summary['_command_input'];
        } elseif (is_array($summary['command_input'] ?? null)) {
            $commandInput = $summary['command_input'];
        } elseif (is_array($impact['_command_input'] ?? null)) {
            $commandInput = $impact['_command_input'];
        } elseif (is_array($impact['command_input'] ?? null)) {
            $commandInput = $impact['command_input'];
        } else {
            $source = array_merge($impact, $summary);
            $commandInput = array_intersect_key($source, array_flip([
                'cycle_id',
                'session_id',
                'expected_version',
                'admission_attempt_id',
                'attempt_id',
                'attempt_ids',
                'academic_cycle_id',
                'program_id',
                'slots',
                'school_year',
                'semester',
                'application_start_date',
                'application_end_date',
                'room_id',
                'starts_at',
                'effective_end_at',
                'code',
                'name',
                'capacity',
                'is_exam_room',
                'decision',
                'status',
                'assignment_id',
                'grading_session_id',
                'scores',
                'document_ref',
                'reason',
                'notes',
                'applicant_status_email_enabled',
                'registrar_new_submission_alert_enabled',
                'publication_email_enabled',
                'reminder_email_enabled',
                'reminder_offsets_minutes',
                'opens_at',
                'closes_at',
                'exam_session_id',
                'version_id',
                'roster_material_fingerprint',
            ]));
        }

        $capabilityId = (string) $proposal->capability_id;
        if ($capabilityId === 'exams.slot.reserve@1') {
            $commandInput['idempotency_key'] = (string) $proposal->execution_id;
        }

        if (
            $capabilityId === 'exams.session.publish@1'
            && ! array_key_exists('roster_material_fingerprint', $commandInput)
            && isset($impact['roster_material_fingerprint'])
            && is_string($impact['roster_material_fingerprint'])
            && $impact['roster_material_fingerprint'] !== ''
        ) {
            $commandInput['roster_material_fingerprint'] = $impact['roster_material_fingerprint'];
        }

        return $commandInput;
    }

    /**
     * @param  array<string, mixed>  $result
     * @return array<string, mixed>
     */
    private function buildReceipt(XiamProposal $proposal, string $idempotencyKey, array $result): array
    {
        return [
            'receipt_id' => $this->deterministicReceiptId((string) $proposal->execution_id),
            'execution_id' => (string) $proposal->execution_id,
            'canonical_digest' => (string) $proposal->canonical_digest,
            'confirmation_idempotency_key' => $idempotencyKey,
            'capability_id' => (string) $proposal->capability_id,
            'result' => $result,
            'confirmed_at' => now()->toIso8601String(),
        ];
    }

    private function deterministicReceiptId(string $executionId): string
    {
        $hex = substr(hash('sha256', 'xiam.proposal.receipt:'.$executionId), 0, 32);

        return sprintf(
            '%s-%s-5%s-a%s-%s',
            substr($hex, 0, 8),
            substr($hex, 8, 4),
            substr($hex, 13, 3),
            substr($hex, 17, 3),
            substr($hex, 20, 12),
        );
    }

    private function resolveProposalGrant(XiamProposal $proposal, User $actor, ?string $grantId): ?XiamTaskGrant
    {
        if ($grantId !== null && $grantId !== '') {
            $grant = XiamTaskGrant::query()->lockForUpdate()->find($grantId);
            if ($grant !== null && $grant->status === XiamTaskGrant::STATUS_ACTIVE && $grant->expires_at !== null && $grant->expires_at->isPast()) {
                $grant->forceFill([
                    'status' => XiamTaskGrant::STATUS_EXPIRED,
                    'revoked_at' => now(),
                    'revoke_reason' => 'expired',
                ])->save();
            }

            return $grant;
        }

        return XiamTaskGrant::query()
            ->where('actor_id', (string) $actor->id)
            ->get()
            ->first(function (XiamTaskGrant $candidate) use ($proposal): bool {
                return ($candidate->scope_bounds['pending_proposal_id'] ?? null) === $proposal->id;
            });
    }

    /**
     * @param  array<string, mixed>  $value
     */
    private function canonicalDigest(array $value): string
    {
        $canonical = $this->canonicalize($value);

        return hash('sha256', json_encode($canonical, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
    }

    private function canonicalize(mixed $value): mixed
    {
        if (! is_array($value)) {
            return $value;
        }

        if (array_is_list($value)) {
            return array_map(fn (mixed $item): mixed => $this->canonicalize($item), $value);
        }

        ksort($value);

        foreach ($value as $key => $item) {
            $value[$key] = $this->canonicalize($item);
        }

        return $value;
    }

    private function appendProposalEvent(XiamProposal $proposal, string $type, array $payload): void
    {
        $run = XiamRun::query()->find($proposal->run_id);
        if ($run === null) {
            return;
        }

        $this->events->append($run, $type, $payload);
    }

    private function clearGrantAwaiting(XiamProposal $proposal): void
    {
        $grant = XiamTaskGrant::query()
            ->where('status', XiamTaskGrant::STATUS_ACTIVE)
            ->where('actor_id', (string) (($proposal->summary['actor_id'] ?? '')))
            ->get()
            ->first(function (XiamTaskGrant $grant) use ($proposal): bool {
                return ($grant->scope_bounds['pending_proposal_id'] ?? null) === $proposal->id;
            });

        if ($grant !== null) {
            $this->skills->clearAwaitingConfirm($grant);
        }
    }

    private function applicantPolicyAllows(?int $institutionId, bool $requiresSelfBooking = false): bool
    {
        try {
            $policy = $institutionId !== null && $institutionId > 0 && Schema::hasTable('xiam_institution_policies')
                ? XiamInstitutionPolicy::query()->where('institution_id', $institutionId)->first()
                : null;
        } catch (Throwable) {
            return false;
        }

        if ($policy?->applicant_inquiry_enabled === false) {
            return false;
        }

        return ! $requiresSelfBooking
            || ($policy?->applicant_self_booking_enabled ?? (bool) config('xiam.applicant_self_booking_enabled', true));
    }
}
