<?php

namespace App\Ai\Skills;

use App\Ai\Conversation\ContextEpochService;
use App\Ai\Grants\TaskGrantService;
use App\Ai\Proposals\ProposalService;
use App\Application\Capabilities\CapabilityInvocationRequest;
use App\Application\Capabilities\CapabilityKernel;
use App\Models\Ai\NexiConversation;
use App\Models\Ai\NexiProposal;
use App\Models\Ai\NexiTaskGrant;
use App\Models\User;
use RuntimeException;

/**
 * Assistant tool path: SkillEngine allowlist ∩ Kernel ∩ optional Proposal persist.
 */
final class SkillToolInvoker
{
    public function __construct(
        private readonly TaskGrantService $grants,
        private readonly SkillEngine $skills,
        private readonly CapabilityKernel $kernel,
        private readonly ProposalService $proposals,
        private readonly ContextEpochService $epochs,
    ) {}

    /**
     * @param  array<string, mixed>  $input
     * @return array{
     *     grant_id: string,
     *     capability_id: string,
     *     result: array<string, mixed>,
     *     proposal: array<string, mixed>|null
     * }
     */
    public function invoke(
        User $actor,
        string $grantId,
        string $capabilityId,
        array $input,
        ?string $conversationId = null,
    ): array {
        $grant = $this->grants->findActive($grantId);
        if ($grant === null) {
            throw new RuntimeException('grant_not_found');
        }

        if ((string) $grant->actor_id !== (string) $actor->id) {
            throw new RuntimeException('grant_actor_mismatch');
        }

        $this->skills->assertToolAllowed($grant, $capabilityId);

        if (! str_contains($capabilityId, '@')) {
            throw new RuntimeException('capability_id_invalid');
        }
        [$key, $version] = explode('@', $capabilityId, 2);

        $result = $this->kernel->invoke(new CapabilityInvocationRequest(
            key: $key,
            version: (int) $version,
            input: $input,
            actor: $actor,
            channel: 'nexi_safe',
            surfaceKey: $grant->surface_key,
            pagePackKey: $grant->origin_pack_key,
            correlationId: $grant->id,
        ));

        if (! $result->ok) {
            throw new RuntimeException($result->reason ?? 'capability_denied');
        }

        $data = is_array($result->data) ? $result->data : [];

        if ($capabilityId === 'exams.session.show@1') {
            $this->skills->advanceCursor($grant, ExamSessionSchedulingSkill::CURSOR_SESSION_LOADED);
            $bounds = $grant->scope_bounds ?? [];
            $bounds['exam_session_id'] = (int) ($input['session_id'] ?? ($bounds['exam_session_id'] ?? 0)) ?: null;
            $grant->forceFill(['scope_bounds' => $bounds])->save();
        }

        if ($capabilityId === 'exams.slots.list@1') {
            $this->skills->advanceCursor($grant, ApplicantChooseSlotSkill::CURSOR_SLOTS_LOADED);
        }

        $proposalPayload = null;
        if (($data['mode'] ?? null) === 'propose') {
            $conversation = $this->resolveConversation($actor, $conversationId, $grant);
            $impact = is_array($data['impact'] ?? null) ? $data['impact'] : [];
            $evidence = is_array($data['evidence'] ?? null) ? $data['evidence'] : [];
            $expectedVersion = isset($impact['expected_version']) ? (int) $impact['expected_version'] : null;

            $proposal = $this->proposals->create(
                actor: $actor,
                runId: null,
                conversationId: $conversation->id,
                capabilityId: (string) ($data['capability_id'] ?? $capabilityId),
                summary: [
                    ...$impact,
                    '_command_input' => array_intersect_key($input, array_flip(
                        $grant->surface_key === 'applicant'
                            ? ['exam_session_id', 'admission_attempt_id', 'idempotency_key']
                            : ['session_id', 'expected_version', 'admission_attempt_id', 'reason']
                    )),
                ],
                evidence: $evidence,
                grant: $grant->fresh() ?? $grant,
                expectedVersion: $expectedVersion,
            );

            $proposalPayload = $this->proposalArray($proposal);
        }

        return [
            'grant_id' => $grant->id,
            'capability_id' => $capabilityId,
            'result' => $data,
            'proposal' => $proposalPayload,
        ];
    }

    private function resolveConversation(User $actor, ?string $conversationId, NexiTaskGrant $grant): NexiConversation
    {
        if (is_string($conversationId) && $conversationId !== '') {
            $conversation = NexiConversation::query()->find($conversationId);
            if ($conversation === null || (string) $conversation->actor_id !== (string) $actor->id) {
                throw new RuntimeException('conversation_denied');
            }

            return $conversation;
        }

        $institutionId = $actor->staffProfile()?->institution_id
            ?? $actor->actorScope()->institutionId;

        return $this->epochs->resolveOpenConversation(
            'staff',
            (string) $actor->id,
            $institutionId,
            'staff',
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function proposalArray(NexiProposal $proposal): array
    {
        return [
            'id' => $proposal->id,
            'status' => $proposal->status,
            'capability_id' => $proposal->capability_id,
            'expected_version' => $proposal->expected_version,
            'summary' => $proposal->summary,
            'expires_at' => $proposal->expires_at?->toIso8601String(),
        ];
    }
}
