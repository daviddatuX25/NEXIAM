<?php

namespace App\Ai\Skills;

use App\Ai\Grants\TaskGrantService;
use App\Models\Ai\NexiTaskGrant;
use App\Models\User;
use RuntimeException;

/**
 * Server skill engine: issues TaskGrants and enforces allowlist + cursor (AD-5).
 * Model recommendations are hints; this class rejects off-recipe tools.
 */
final class SkillEngine
{
    public function __construct(private readonly TaskGrantService $grants) {}

    public function startExamSessionSkill(
        User $actor,
        string $contextEpoch,
        ?string $originPackKey = null,
        ?int $originPackVersion = null,
        ?int $sessionId = null,
    ): NexiTaskGrant {
        $institutionId = $actor->staffProfile()?->institution_id
            ?? $actor->actorScope()->institutionId;

        return $this->grants->issue(
            actorType: 'staff',
            actorId: (string) $actor->id,
            sessionId: null,
            institutionId: $institutionId,
            surfaceKey: 'staff',
            skillKey: ExamSessionSchedulingSkill::KEY,
            skillVersion: ExamSessionSchedulingSkill::VERSION,
            allowedCapabilityIds: ExamSessionSchedulingSkill::allowedCapabilityIds(),
            contextEpoch: $contextEpoch,
            originPackKey: $originPackKey,
            originPackVersion: $originPackVersion,
            scopeBounds: [
                'cursor' => $sessionId !== null
                    ? ExamSessionSchedulingSkill::CURSOR_SESSION_LOADED
                    : ExamSessionSchedulingSkill::CURSOR_STARTED,
                'exam_session_id' => $sessionId,
                'guidance_digest' => sha1(ExamSessionSchedulingSkill::guidanceDigest()),
            ],
            expiresAt: now()->addHours(2),
        );
    }

    public function startApplicantSlotSkill(
        User $actor,
        string $contextEpoch,
        ?string $originPackKey = null,
        ?int $originPackVersion = null,
        ?int $attemptId = null,
    ): NexiTaskGrant {
        $institutionId = $actor->applicantAccount()?->institution_id
            ?? $actor->actorScope()->institutionId;

        return $this->grants->issue(
            actorType: 'applicant',
            actorId: (string) $actor->id,
            sessionId: null,
            institutionId: $institutionId,
            surfaceKey: 'applicant',
            skillKey: ApplicantChooseSlotSkill::KEY,
            skillVersion: ApplicantChooseSlotSkill::VERSION,
            allowedCapabilityIds: ApplicantChooseSlotSkill::allowedCapabilityIds(),
            contextEpoch: $contextEpoch,
            originPackKey: $originPackKey,
            originPackVersion: $originPackVersion,
            scopeBounds: [
                'cursor' => ApplicantChooseSlotSkill::CURSOR_STARTED,
                'admission_attempt_id' => $attemptId,
                'guidance_digest' => sha1(ApplicantChooseSlotSkill::guidanceDigest()),
            ],
            expiresAt: now()->addHours(2),
        );
    }

    public function assertToolAllowed(NexiTaskGrant $grant, string $capabilityId): void
    {
        if (! $grant->isUsable()) {
            throw new RuntimeException('grant_unusable');
        }

        $skillKey = (string) $grant->skill_key;
        if ($skillKey !== ExamSessionSchedulingSkill::KEY && $skillKey !== ApplicantChooseSlotSkill::KEY) {
            throw new RuntimeException('skill_mismatch');
        }

        $allowed = $grant->allowed_capability_ids ?? [];
        if (! in_array($capabilityId, $allowed, true)) {
            throw new RuntimeException('capability_not_on_skill_allowlist');
        }

        $transitions = match ($skillKey) {
            ApplicantChooseSlotSkill::KEY => ApplicantChooseSlotSkill::legalTransitions(),
            default => ExamSessionSchedulingSkill::legalTransitions(),
        };

        $cursor = (string) (($grant->scope_bounds['cursor'] ?? null) ?: array_key_first($transitions));
        $legal = $transitions[$cursor] ?? [];
        if (! in_array($capabilityId, $legal, true)) {
            throw new RuntimeException('capability_illegal_for_cursor');
        }
    }

    public function advanceCursor(NexiTaskGrant $grant, string $cursor): void
    {
        $transitions = match ((string) $grant->skill_key) {
            ApplicantChooseSlotSkill::KEY => ApplicantChooseSlotSkill::legalTransitions(),
            default => ExamSessionSchedulingSkill::legalTransitions(),
        };

        if (! array_key_exists($cursor, $transitions)) {
            throw new RuntimeException('unknown_cursor');
        }

        $bounds = $grant->scope_bounds ?? [];
        $bounds['cursor'] = $cursor;
        $grant->forceFill(['scope_bounds' => $bounds])->save();
    }

    public function markAwaitingConfirm(NexiTaskGrant $grant, string $proposalId): void
    {
        $bounds = $grant->scope_bounds ?? [];
        $bounds['cursor'] = ExamSessionSchedulingSkill::CURSOR_AWAITING_CONFIRM;
        $bounds['pending_proposal_id'] = $proposalId;
        $grant->forceFill(['scope_bounds' => $bounds])->save();
    }

    public function clearAwaitingConfirm(NexiTaskGrant $grant): void
    {
        $bounds = $grant->scope_bounds ?? [];
        $bounds['cursor'] = ExamSessionSchedulingSkill::CURSOR_SESSION_LOADED;
        unset($bounds['pending_proposal_id']);
        $grant->forceFill(['scope_bounds' => $bounds])->save();
    }
}
