<?php

namespace App\Ai\Gateway;

use App\Ai\Context\InvocationContext;
use App\Ai\Policy\EmergencyStop;
use App\Application\Capabilities\CapabilityKind;
use App\Models\Ai\XiamInstitutionPolicy;
use App\Models\User;
use Illuminate\Support\Facades\Schema;

/**
 * Server-owned policy envelope and mode-narrowing gate for Xiam tool execution.
 */
final class ToolPolicyGate
{
    public const ENVELOPE_BLOCKED = 'blocked';

    public const ENVELOPE_FAQ_ONLY = 'faq_only';

    public const ENVELOPE_READ_ALLOWED = 'read_allowed';

    public const ENVELOPE_READ_DENIED = 'read_denied';

    public const ENVELOPE_PROPOSE_ALLOWED = 'propose_allowed';

    public function __construct(private readonly EmergencyStop $emergencyStop) {}

    /**
     * Resolve the active tool execution envelope for the current turn context and actor.
     */
    public function resolveEnvelope(InvocationContext $context, ?User $actor = null): string
    {
        if ($this->emergencyStop->active($context->institutionId)) {
            return self::ENVELOPE_BLOCKED;
        }
        if (! (bool) config('xiam.enabled', true)
            || in_array((string) config('xiam.mode', 'degraded'), ['disabled', 'suspended'], true)) {
            return self::ENVELOPE_BLOCKED;
        }

        if ($context->surfaceProfile === 'public_faq' || $context->actorType === 'public') {
            return self::ENVELOPE_FAQ_ONLY;
        }

        if ($actor !== null && (string) $actor->getAuthIdentifier() !== $context->actorId) {
            return self::ENVELOPE_BLOCKED;
        }

        if ($context->actorType === 'staff') {
            if ($actor !== null) {
                $profile = $actor->staffProfile()->first();
                $institutionId = $actor->actorScope()->institutionId;

                if ($profile === null
                    || ! $profile->isActive()
                    || ($institutionId !== null && $context->institutionId !== null && (int) $institutionId !== $context->institutionId)) {
                    return self::ENVELOPE_BLOCKED;
                }
            }

            return $this->applyModeCeiling($context, self::ENVELOPE_PROPOSE_ALLOWED);
        }

        if ($context->actorType === 'applicant') {
            if ($actor !== null) {
                $account = $actor->applicantAccount()->with('identity')->first();
                $institutionId = $account?->identity?->institution_id;

                if ($account === null
                    || ($institutionId !== null && $context->institutionId !== null && (int) $institutionId !== $context->institutionId)) {
                    return self::ENVELOPE_BLOCKED;
                }
            }

            try {
                $policy = $this->policyForInstitution($context->institutionId);
            } catch (\Throwable) {
                return self::ENVELOPE_BLOCKED;
            }

            if ($policy?->applicant_inquiry_enabled === false) {
                return self::ENVELOPE_READ_DENIED;
            }

            $selfBookingEnabled = $policy?->applicant_self_booking_enabled
                ?? (bool) config('xiam.applicant_self_booking_enabled', true);
            $envelope = $selfBookingEnabled
                ? self::ENVELOPE_PROPOSE_ALLOWED
                : self::ENVELOPE_READ_ALLOWED;

            return $this->applyModeCeiling($context, $envelope);
        }

        return self::ENVELOPE_FAQ_ONLY;
    }

    private function applyModeCeiling(InvocationContext $context, string $envelope): string
    {
        if ($context->mode === 'inquiry' && $envelope === self::ENVELOPE_PROPOSE_ALLOWED) {
            return self::ENVELOPE_READ_ALLOWED;
        }

        return $envelope;
    }

    /**
     * Check if a given tool definition is permitted under the active envelope.
     */
    public function allowsTool(string $envelope, AssistantToolDefinition $tool, ?int $institutionId = null): bool
    {
        if (in_array($envelope, [self::ENVELOPE_BLOCKED, self::ENVELOPE_FAQ_ONLY, self::ENVELOPE_READ_DENIED], true)) {
            return false;
        }

        if ($institutionId !== null && $institutionId > 0) {
            try {
                $policy = $this->policyForInstitution($institutionId);
                if ($policy !== null && (
                    ! $policy->isToolEnabled($tool->id())
                    || ($this->isApplicantInquiryTool($tool->id()) && $policy->applicant_inquiry_enabled === false)
                )) {
                    return false;
                }
            } catch (\Throwable) {
                return false;
            }
        }
        if ($envelope === self::ENVELOPE_READ_ALLOWED) {
            return $tool->kind === CapabilityKind::Read;
        }

        if ($envelope === self::ENVELOPE_PROPOSE_ALLOWED) {
            return in_array($tool->kind, [CapabilityKind::Read, CapabilityKind::Propose], true);
        }

        return false;
    }

    private function policyForInstitution(?int $institutionId): ?XiamInstitutionPolicy
    {
        if ($institutionId === null || $institutionId < 1 || ! Schema::hasTable('xiam_institution_policies')) {
            return null;
        }

        return XiamInstitutionPolicy::query()
            ->where('institution_id', $institutionId)
            ->first();
    }

    private function isApplicantInquiryTool(string $toolId): bool
    {
        return str_starts_with($toolId, 'admission.office_inquiries.');
    }

    /**
     * @return list<CapabilityKind>
     */
    public function allowedKinds(string $envelope): array
    {
        return match ($envelope) {
            self::ENVELOPE_READ_ALLOWED => [CapabilityKind::Read],
            self::ENVELOPE_PROPOSE_ALLOWED => [CapabilityKind::Read, CapabilityKind::Propose],
            default => [],
        };
    }
}
