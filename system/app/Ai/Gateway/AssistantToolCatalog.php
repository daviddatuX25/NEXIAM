<?php

namespace App\Ai\Gateway;

use App\Admission\Support\ApplicantOfficeInquiryAccess;
use App\Ai\Context\InvocationContext;
use App\Application\Capabilities\CapabilityDefinition;
use App\Application\Capabilities\CapabilityDefinitions;
use App\Application\Capabilities\CapabilityKind;
use App\Authorization\CapabilityAuthorizer;
use App\Models\Ai\XiamInstitutionPolicy;
use App\Models\User;
use Illuminate\Support\Facades\Schema;
use RuntimeException;
use Throwable;

/**
 * Self-contained AI-only tool catalog (D-XIAM-01).
 *
 * Stores AssistantToolDefinition entries directly and filters by GF-21 capabilities.
 */
final class AssistantToolCatalog
{
    /** @var array<string, AssistantToolDefinition> */
    private array $tools = [];

    /**
     * @param  array<AssistantToolDefinition|CapabilityDefinition>|null  $definitions
     */
    public function __construct(?array $definitions = null)
    {
        $entries = $definitions ?? CapabilityDefinitions::all();
        foreach ($entries as $entry) {
            if ($entry instanceof CapabilityDefinition) {
                if (! in_array($entry->kind, [CapabilityKind::Read, CapabilityKind::Propose], true)
                    || ! in_array('xiam_safe', $entry->channels, true)) {
                    continue;
                }
                $tool = new AssistantToolDefinition($entry);
            } elseif ($entry instanceof AssistantToolDefinition) {
                if (! in_array($entry->kind, [CapabilityKind::Read, CapabilityKind::Propose], true)) {
                    continue;
                }
                if ($entry->capability !== null && ! in_array('xiam_safe', $entry->capability->channels, true)) {
                    continue;
                }
                $tool = $entry;
            } else {
                continue;
            }

            $id = $tool->id();
            if (isset($this->tools[$id])) {
                throw new RuntimeException("Duplicate assistant tool registration: {$id}");
            }

            $this->tools[$id] = $tool;
        }
    }

    public function get(string $key, int $version): ?AssistantToolDefinition
    {
        return $this->tools[$key.'@'.$version] ?? null;
    }

    public function getById(string $id): ?AssistantToolDefinition
    {
        return $this->tools[$id] ?? null;
    }

    /**
     * @return list<AssistantToolDefinition>
     */
    public function all(): array
    {
        return array_values($this->tools);
    }

    /**
     * @return list<AssistantToolDefinition>
     */
    public function forActor(User $actor, InvocationContext $context, CapabilityAuthorizer $authorizer): array
    {
        if ($context->actorType === 'staff') {
            $staffProfile = $actor->staffProfile()->first();
            if ($staffProfile !== null && ! $staffProfile->isActive()) {
                return [];
            }
        }

        $scope = [];
        if ($context->actorType === 'staff' && $context->institutionId !== null) {
            $scope['institution_id'] = $context->institutionId;
        }

        $institutionId = $context->institutionId;
        if ($institutionId === null && $context->actorType === 'applicant') {
            $institutionId = $actor->applicantAccount()->with('identity')->first()?->identity?->institution_id;
        }

        $policy = null;
        if ($institutionId !== null && $institutionId > 0) {
            try {
                if (Schema::hasTable('xiam_institution_policies')) {
                    $policy = XiamInstitutionPolicy::query()
                        ->where('institution_id', $institutionId)
                        ->first();
                }
            } catch (Throwable) {
                return [];
            }
        }

        if ($context->actorType === 'applicant' && $policy?->applicant_inquiry_enabled === false) {
            return [];
        }

        $selfBookingEnabled = $policy?->applicant_self_booking_enabled
            ?? (bool) config('xiam.applicant_self_booking_enabled', true);

        $allowed = [];
        $officeInquiryTools = [
            'admission.office_inquiries.options@1',
            'admission.office_inquiries.open@1',
            'admission.office_inquiries.reply@1',
        ];
        $officeInquiryAvailable = null;
        foreach ($this->tools as $tool) {
            if ($context->actorType === 'applicant'
                && ! $selfBookingEnabled
                && $tool->id() === 'exams.slot.reserve@1') {
                continue;
            }
            if (! $this->isToolAuthorized($tool, $actor, $authorizer, $scope)) {
                continue;
            }

            if (in_array($tool->id(), $officeInquiryTools, true)) {
                $officeInquiryAvailable ??= app(ApplicantOfficeInquiryAccess::class)->availability($actor)['available'];
                if (! $officeInquiryAvailable) {
                    continue;
                }
            }

            $allowed[] = $tool;
        }

        return $allowed;
    }

    private function isToolAuthorized(
        AssistantToolDefinition $tool,
        User $actor,
        CapabilityAuthorizer $authorizer,
        array $scope = []
    ): bool {
        $caps = $tool->applicationCapabilities;
        if ($caps === []) {
            return false;
        }

        if ($tool->authorizationMode === 'any') {
            return $authorizer->allowsAny($actor, $caps, $scope);
        }

        foreach ($caps as $cap) {
            if (! $authorizer->allows($actor, $cap, $scope)) {
                return false;
            }
        }

        return true;
    }

    /**
     * @return list<CapabilityDefinition>
     */
    public function capabilityDefinitions(): array
    {
        return array_map(
            static fn (AssistantToolDefinition $tool): CapabilityDefinition => $tool->capability,
            $this->all(),
        );
    }
}
