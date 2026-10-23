<?php

namespace App\Ai\Skills;

use App\Admission\Support\ApplicantScope;
use App\Ai\Grants\TaskGrantService;
use App\Models\Admission\AdmissionAttempt;
use App\Models\Ai\XiamTaskGrant;
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
    ): XiamTaskGrant {
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
    ): XiamTaskGrant {
        $account = $actor->applicantAccount()->with('identity')->first();
        $institutionId = $account?->identity?->institution_id;

        $identityId = ApplicantScope::identityIdForUser($actor);
        if ($attemptId !== null) {
            $attempt = AdmissionAttempt::query()->find($attemptId);
            if ($attempt === null || ! ApplicantScope::attemptBelongsToUser($attempt, $actor)) {
                throw new RuntimeException('grant_target_mismatch');
            }
        } elseif ($identityId !== null) {
            $resolvedAttemptId = AdmissionAttempt::query()
                ->where('applicant_identity_id', $identityId)
                ->orderByDesc('id')
                ->value('id');
            $attemptId = $resolvedAttemptId !== null ? (int) $resolvedAttemptId : null;
        }

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

    /**
     * @param  array<string, mixed>  $input
     */
    public function assertToolAllowed(XiamTaskGrant $grant, string $capabilityId, array $input = []): void
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

        if ($input !== [] && ! $this->hasForbiddenExecutionField($input)) {
            $this->assertTargetAllowed($grant, $capabilityId, $input);
        }
    }

    /**
     * @param  array<string, mixed>  $input
     */
    public function assertTargetAllowed(XiamTaskGrant $grant, string $capabilityId, array $input): void
    {
        $bounds = is_array($grant->scope_bounds) ? $grant->scope_bounds : [];

        $boundSessionId = isset($bounds['exam_session_id']) && (int) $bounds['exam_session_id'] > 0
            ? (int) $bounds['exam_session_id']
            : (isset($bounds['session_id']) && (int) $bounds['session_id'] > 0 ? (int) $bounds['session_id'] : null);

        if ($boundSessionId !== null) {
            if (array_key_exists('session_id', $input) && (int) $input['session_id'] !== $boundSessionId) {
                throw new RuntimeException('grant_target_mismatch');
            }

            if (array_key_exists('exam_session_id', $input) && (int) $input['exam_session_id'] !== $boundSessionId) {
                throw new RuntimeException('grant_target_mismatch');
            }
        } elseif (
            (string) $grant->skill_key === ExamSessionSchedulingSkill::KEY
            && in_array($capabilityId, [
                'exams.session.reservation.open@1',
                'exams.session.assign@1',
                'exams.session.publish@1',
            ], true)
        ) {
            throw new RuntimeException('grant_target_mismatch');
        }

        $boundAttemptId = isset($bounds['admission_attempt_id']) && (int) $bounds['admission_attempt_id'] > 0
            ? (int) $bounds['admission_attempt_id']
            : null;

        if ($boundAttemptId !== null) {
            if (array_key_exists('admission_attempt_id', $input) && (int) $input['admission_attempt_id'] !== $boundAttemptId) {
                throw new RuntimeException('grant_target_mismatch');
            }
        } elseif (
            (string) $grant->skill_key === ApplicantChooseSlotSkill::KEY
            && $capabilityId === 'exams.slot.reserve@1'
        ) {
            throw new RuntimeException('grant_target_mismatch');
        }

        if (isset($bounds['version_id']) && is_string($bounds['version_id']) && $bounds['version_id'] !== '') {
            if (array_key_exists('version_id', $input) && (string) $input['version_id'] !== $bounds['version_id']) {
                throw new RuntimeException('grant_target_mismatch');
            }
        }

        if (isset($bounds['source_id']) && is_string($bounds['source_id']) && $bounds['source_id'] !== '') {
            if (array_key_exists('source_id', $input) && (string) $input['source_id'] !== $bounds['source_id']) {
                throw new RuntimeException('grant_target_mismatch');
            }
        }
    }

    /**
     * @param  array<string, mixed>  $input
     */
    private function hasForbiddenExecutionField(array $input): bool
    {
        foreach (['execute', 'command', 'command_kind', 'command_name', 'confirmation_token'] as $field) {
            if (array_key_exists($field, $input)) {
                return true;
            }
        }

        return false;
    }

    public function advanceCursor(XiamTaskGrant $grant, string $cursor): void
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

    public function markAwaitingConfirm(XiamTaskGrant $grant, string $proposalId): void
    {
        $bounds = $grant->scope_bounds ?? [];
        $bounds['cursor'] = ExamSessionSchedulingSkill::CURSOR_AWAITING_CONFIRM;
        $bounds['pending_proposal_id'] = $proposalId;
        $grant->forceFill(['scope_bounds' => $bounds])->save();
    }

    public function clearAwaitingConfirm(XiamTaskGrant $grant): void
    {
        $bounds = $grant->scope_bounds ?? [];
        $bounds['cursor'] = (string) $grant->skill_key === ApplicantChooseSlotSkill::KEY
            ? ApplicantChooseSlotSkill::CURSOR_SLOTS_LOADED
            : ExamSessionSchedulingSkill::CURSOR_SESSION_LOADED;
        unset($bounds['pending_proposal_id']);
        $grant->forceFill(['scope_bounds' => $bounds])->save();
    }
}
