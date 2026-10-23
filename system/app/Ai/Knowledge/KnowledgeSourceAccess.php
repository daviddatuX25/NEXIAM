<?php

namespace App\Ai\Knowledge;

use App\Authorization\CapabilityAuthorizer;
use App\Authorization\CapabilityCatalog;
use App\Models\Ai\KnowledgeSource;
use App\Models\Ai\KnowledgeSourceApproval;
use App\Models\Ai\KnowledgeSourceReconciliation;
use App\Models\Ai\KnowledgeSourceVersion;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;

/** GF-21 capability and institution/office resource checks for knowledge sources. */
final class KnowledgeSourceAccess
{
    public const array OFFICES = ['registrar', 'guidance', 'admin'];

    public function __construct(
        private readonly CapabilityAuthorizer $authorizer,
        private readonly PublicationDigest $digest,
    ) {}

    public function canManageCatalog(User $actor): bool
    {
        return $this->hasCapabilityInActorOffice($actor, CapabilityCatalog::KNOWLEDGE_SOURCES_MANAGE);
    }

    public function canApproveCatalog(User $actor): bool
    {
        return $this->hasCapabilityInActorOffice($actor, CapabilityCatalog::KNOWLEDGE_SOURCES_APPROVE);
    }

    public function canViewCatalog(User $actor): bool
    {
        return $this->canManageCatalog($actor) || $this->canApproveCatalog($actor);
    }

    public function requireManageCatalog(User $actor): void
    {
        if (! $this->canManageCatalog($actor)) {
            throw new AuthorizationException;
        }
    }

    public function requireViewSource(User $actor, KnowledgeSource $source, ?KnowledgeSourceVersion $latest = null): void
    {
        if (! $this->canViewCatalog($actor)) {
            throw new AuthorizationException;
        }

        abort_unless($this->canViewSource($actor, $source, $latest), 404);
    }

    public function canViewSource(User $actor, KnowledgeSource $source, ?KnowledgeSourceVersion $latest = null): bool
    {
        $context = $this->officeContext($actor);
        if ($context === null || ! $this->canViewCatalog($actor)
            || ! $this->hasSupportedGovernanceMetadata($source)
            || $source->institution_id === null
            || (int) $source->institution_id !== $context['institution_id']
            || ! in_array($source->owning_office, self::OFFICES, true)) {
            return false;
        }

        $latest ??= $source->versions()->orderByDesc('version')->first();

        if ($this->isHeld($latest)) {
            return $context['office'] === $source->owning_office;
        }

        return match ($source->audience) {
            KnowledgeSource::AUDIENCE_STAFF_ONLY => $context['office'] === $source->owning_office,
            KnowledgeSource::AUDIENCE_STAFF_AND_APPLICANT_INQUIRY => $context['office'] === $source->owning_office
                || $this->hasPublishedInstitutionalVisibility($source, $latest),
            KnowledgeSource::AUDIENCE_CROSS_OFFICE_STAFF => $context['office'] === $source->owning_office
                || $this->isOfficeHead($actor, $context['office'])
                || $this->hasPublishedCrossOfficeVisibility($source, $latest),
            default => false,
        };
    }

    public function hasPublishedInstitutionalVisibility(KnowledgeSource $source, ?KnowledgeSourceVersion $latest = null): bool
    {
        $latest ??= $source->versions()->orderByDesc('version')->first();

        return $latest instanceof KnowledgeSourceVersion
            && ! $this->isHeld($latest)
            && $latest->status === KnowledgeSourceVersion::STATUS_ACTIVE
            && $latest->publication_digest !== null
            && $latest->publication_digest === $this->digest->forVersion($latest);
    }

    public function hasPublishedCrossOfficeVisibility(KnowledgeSource $source, ?KnowledgeSourceVersion $latest = null): bool
    {
        $latest ??= $source->versions()
            ->with('officeApprovals')
            ->orderByDesc('version')
            ->first();
        if (! $latest instanceof KnowledgeSourceVersion
            || $this->isHeld($latest)
            || $latest->status !== KnowledgeSourceVersion::STATUS_ACTIVE
            || $latest->publication_digest === null
            || $latest->publication_digest !== $this->digest->forVersion($latest)) {
            return false;
        }

        $approvals = $latest->officeApprovals;
        if ($approvals->pluck('office')->sort()->values()->all() !== ['guidance', 'registrar']) {
            return false;
        }

        return ! $approvals->contains(fn (KnowledgeSourceApproval $approval): bool => $approval->publication_digest !== $latest->publication_digest
            || $approval->approver_id === $latest->created_by);
    }

    private function isHeld(?KnowledgeSourceVersion $version): bool
    {
        if (! $version instanceof KnowledgeSourceVersion) {
            return false;
        }

        if ($version->relationLoaded('latestReconciliation')) {
            $latestReconciliation = $version->latestReconciliation;
        } elseif ($version->relationLoaded('reconciliations')) {
            $latestReconciliation = $version->reconciliations->sortByDesc('created_at')->first();
        } else {
            $latestReconciliation = KnowledgeSourceReconciliation::query()
                ->where('knowledge_source_version_id', $version->getKey())
                ->latest('created_at')
                ->first();
        }

        return $latestReconciliation !== null
            && $latestReconciliation->disposition === KnowledgeSourceReconciliation::DISPOSITION_HOLD;
    }

    public function canManageSource(User $actor, KnowledgeSource $source): bool
    {
        $context = $this->officeContext($actor);

        return $context !== null
            && $this->isOwnedByActorOffice($actor, $source)
            && $this->authorizer->allows($actor, CapabilityCatalog::KNOWLEDGE_SOURCES_MANAGE, [
                'office' => $context['office'],
                'institution_id' => $context['institution_id'],
            ]);
    }

    public function requireManageSource(User $actor, KnowledgeSource $source): void
    {
        if (! $this->hasCapabilityInActorOffice($actor, CapabilityCatalog::KNOWLEDGE_SOURCES_MANAGE)) {
            throw new AuthorizationException;
        }

        abort_unless($this->isOwnedByActorOffice($actor, $source), 404);
    }

    public function canApproveSource(User $actor, KnowledgeSource $source): bool
    {
        $context = $this->officeContext($actor);
        if ($context === null || ! $this->hasSupportedGovernanceMetadata($source)
            || $source->institution_id === null
            || (int) $source->institution_id !== $context['institution_id']) {
            return false;
        }

        if ($source->audience === KnowledgeSource::AUDIENCE_CROSS_OFFICE_STAFF) {
            return in_array($context['office'], self::OFFICES, true)
                && $this->isOfficeHead($actor, $context['office'])
                && $this->hasCapabilityInActorOffice($actor, CapabilityCatalog::KNOWLEDGE_SOURCES_APPROVE);
        }

        return $source->owning_office === $context['office']
            && $this->hasCapabilityInActorOffice($actor, CapabilityCatalog::KNOWLEDGE_SOURCES_APPROVE);
    }

    public function requireApproveSource(User $actor, KnowledgeSource $source): void
    {
        $context = $this->officeContext($actor);
        if ($context === null
            || ! $this->authorizer->allows($actor, CapabilityCatalog::KNOWLEDGE_SOURCES_APPROVE, [
                'office' => $context['office'],
                'institution_id' => $context['institution_id'],
            ])) {
            throw new AuthorizationException;
        }

        abort_unless(
            $this->hasSupportedGovernanceMetadata($source)
                && $source->institution_id !== null
                && (int) $source->institution_id === $context['institution_id']
                && in_array($source->owning_office, self::OFFICES, true),
            404,
        );

        if ($source->audience === KnowledgeSource::AUDIENCE_CROSS_OFFICE_STAFF) {
            abort_unless(in_array($context['office'], self::OFFICES, true), 404);
            if (! $this->isOfficeHead($actor, $context['office'])) {
                throw new AuthorizationException;
            }

            return;
        }

        abort_unless($source->owning_office === $context['office'], 404);
    }

    public function canEstablishCrossOffice(User $actor, KnowledgeSource $source): bool
    {
        $context = $this->officeContext($actor);

        return $context !== null
            && $this->hasSupportedGovernanceMetadata($source)
            && $source->audience !== KnowledgeSource::AUDIENCE_CROSS_OFFICE_STAFF
            && $this->isOwnedByActorOffice($actor, $source)
            && $this->isOfficeHead($actor, $context['office'])
            && $this->authorizer->allows($actor, CapabilityCatalog::KNOWLEDGE_SOURCES_APPROVE, [
                'office' => $context['office'],
                'institution_id' => $context['institution_id'],
            ]);
    }

    public function requireEstablishCrossOffice(User $actor, KnowledgeSource $source): void
    {
        $context = $this->officeContext($actor);
        if ($context === null
            || ! $this->authorizer->allows($actor, CapabilityCatalog::KNOWLEDGE_SOURCES_APPROVE, [
                'office' => $context['office'],
                'institution_id' => $context['institution_id'],
            ])) {
            throw new AuthorizationException;
        }

        abort_unless(
            $this->hasSupportedGovernanceMetadata($source)
                && $this->isOwnedByActorOffice($actor, $source)
                && $source->audience !== KnowledgeSource::AUDIENCE_CROSS_OFFICE_STAFF,
            404,
        );

        if (! $this->isOfficeHead($actor, $context['office'])) {
            throw new AuthorizationException;
        }
    }

    public function isOfficeHead(User $actor, string $office): bool
    {
        if (! in_array($office, self::OFFICES, true)) {
            return false;
        }

        $context = $this->officeContext($actor);
        if ($context === null || $context['office'] !== $office) {
            return false;
        }

        return $actor->hasRole(match ($office) {
            'registrar' => 'registrar_admin',
            'guidance' => 'guidance_counselor',
            'admin' => 'institutional_admin',
            default => null,
        });
    }

    /** @return array{institution_id: int, office: string}|null */
    private function officeContext(User $actor): ?array
    {
        $profile = $actor->staffProfile;
        $scope = $actor->actorScope();
        if ($profile === null || ! $profile->isActive() || $scope->institutionId === null
            || ! in_array($scope->office, self::OFFICES, true)) {
            return null;
        }

        return [
            'institution_id' => $scope->institutionId,
            'office' => $scope->office,
        ];
    }

    private function hasCapabilityInActorOffice(User $actor, string $capability): bool
    {
        $context = $this->officeContext($actor);

        return $context !== null && $this->authorizer->allows($actor, $capability, [
            'office' => $context['office'],
            'institution_id' => $context['institution_id'],
        ]);
    }

    private function hasSupportedGovernanceMetadata(KnowledgeSource $source): bool
    {
        if (! in_array($source->source_class, [
            KnowledgeSource::SOURCE_CLASS_POLICY,
            KnowledgeSource::SOURCE_CLASS_PUBLIC_FAQ,
        ], true) || $source->purpose !== 'policy_inquiry') {
            return false;
        }

        return match ($source->audience) {
            KnowledgeSource::AUDIENCE_STAFF_ONLY => $source->classification === KnowledgeSource::CLASSIFICATION_OFFICE_INTERNAL,
            KnowledgeSource::AUDIENCE_STAFF_AND_APPLICANT_INQUIRY => $source->classification === KnowledgeSource::CLASSIFICATION_PUBLIC_INSTITUTIONAL,
            KnowledgeSource::AUDIENCE_CROSS_OFFICE_STAFF => $source->classification === KnowledgeSource::CLASSIFICATION_OFFICE_INTERNAL,
            default => false,
        };
    }

    private function isOwnedByActorOffice(User $actor, KnowledgeSource $source): bool
    {
        $context = $this->officeContext($actor);

        return $context !== null
            && $this->hasSupportedGovernanceMetadata($source)
            && $source->institution_id !== null
            && (int) $source->institution_id === $context['institution_id']
            && in_array($source->owning_office, self::OFFICES, true)
            && $source->owning_office === $context['office'];
    }
}
