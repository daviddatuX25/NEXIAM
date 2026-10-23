<?php

declare(strict_types=1);

namespace App\Notifications\Support;

use App\Authorization\CapabilityAuthorizer;
use App\Authorization\CapabilityCatalog;
use App\Models\Foundation\Institution;
use App\Models\Notifications\CommunicationTemplate;
use App\Models\User;
use App\Notifications\Templates\CommunicationTemplateRegistry;
use DateTimeInterface;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\ModelNotFoundException;

final class CommunicationTemplateScope
{
    public function __construct(
        private readonly CapabilityAuthorizer $authorizer,
    ) {}

    /**
     * Reconcile actor profile vs Institution::current() lookup cleanly.
     */
    public function resolveInstitution(?Authenticatable $actor): Institution
    {
        try {
            $current = Institution::current();
        } catch (ModelNotFoundException) {
            $current = null;
        }

        $actorInstitutionId = null;
        if ($actor instanceof User) {
            $actorInstitutionId = $actor->actorScope()->institutionId;
        }

        if ($actorInstitutionId !== null) {
            if ($current !== null && (int) $current->id === (int) $actorInstitutionId) {
                return $current;
            }

            if ($current !== null) {
                abort(403, 'Institution scope mismatch.');
            }

            $actorInstitution = Institution::query()->find($actorInstitutionId);
            if ($actorInstitution !== null) {
                return $actorInstitution;
            }
        }

        if ($current !== null) {
            return $current;
        }

        abort(404, 'No active institution context.');
    }

    public function resolveInstitutionId(?Authenticatable $actor): int
    {
        return (int) $this->resolveInstitution($actor)->id;
    }

    /**
     * Enforce office check for metadata reading:
     * - Institutional Admins with setup authority can read all domains
     * - Admissions: Registrar application visibility or setup authority
     * - Examination: Guidance exam scheduling
     * - Results: Guidance result release, catalog, or preparation
     */
    public function canAccessMetadata(?Authenticatable $actor, string $domain, ?Institution $institution = null): bool
    {
        if ($actor instanceof User && $actor->actorScope()->office === 'admin' && $this->permitsInstitutionSetup($actor, $institution)) {
            return true;
        }

        return match ($domain) {
            'admissions' => $this->permits($actor, [
                CapabilityCatalog::REGISTRAR_APPLICATIONS_VIEW,
                CapabilityCatalog::REGISTRAR_SETUP_MANAGE,
            ], 'registrar', $institution),
            'examination' => $this->permits($actor, [CapabilityCatalog::GUIDANCE_EXAM_SCHEDULE], 'guidance', $institution),
            'results' => $this->permits($actor, [
                CapabilityCatalog::GUIDANCE_RESULTS_RELEASE,
                CapabilityCatalog::GUIDANCE_ASSESSMENT_CATALOG,
                CapabilityCatalog::GUIDANCE_RESULTS_PREPARE,
            ], 'guidance', $institution),
            default => false,
        };
    }

    /**
     * Enforce strict office and capability checks for source content and authoring.
     */
    public function canAccessFullSource(?Authenticatable $actor, string $domain, ?Institution $institution = null): bool
    {
        if ($actor instanceof User && $actor->actorScope()->office === 'admin' && $this->permitsInstitutionSetup($actor, $institution)) {
            return true;
        }

        return match ($domain) {
            'admissions' => $this->permits($actor, [
                CapabilityCatalog::REGISTRAR_APPLICATIONS_VIEW,
                CapabilityCatalog::REGISTRAR_SETUP_MANAGE,
            ], 'registrar', $institution),
            'examination' => $this->permits($actor, [CapabilityCatalog::GUIDANCE_EXAM_SCHEDULE], 'guidance', $institution),
            'results' => $this->permits($actor, [CapabilityCatalog::GUIDANCE_RESULTS_RELEASE], 'guidance', $institution),
            default => false,
        };
    }

    /**
     * @param  list<string>  $capabilities
     */
    private function permits(?Authenticatable $actor, array $capabilities, string $office, ?Institution $institution): bool
    {
        if (! $actor instanceof User || $actor->actorScope()->office !== $office) {
            return false;
        }

        try {
            $current = Institution::current();
        } catch (ModelNotFoundException) {
            $current = null;
        }

        if ($current !== null && $institution !== null && (int) $institution->id !== (int) $current->id) {
            return false;
        }

        $institution ??= $current;
        if ($institution === null) {
            $institution = Institution::query()->find($actor->actorScope()->institutionId);
        }
        if ($institution === null) {
            return false;
        }

        foreach ($capabilities as $capability) {
            if ($this->authorizer->decideFresh($actor, $capability, [
                'institution_id' => (int) $institution->id,
                'office' => $office,
            ])->allowed) {
                return true;
            }
        }

        return false;
    }

    private function permitsInstitutionSetup(?Authenticatable $actor, ?Institution $institution): bool
    {
        if (! $actor instanceof User || $actor->actorScope()->office !== 'admin') {
            return false;
        }

        try {
            $current = Institution::current();
        } catch (ModelNotFoundException) {
            $current = null;
        }

        if ($current !== null && $institution !== null && (int) $institution->id !== (int) $current->id) {
            return false;
        }

        $institution ??= $current;
        if ($institution === null) {
            $institution = Institution::query()->find($actor->actorScope()->institutionId);
        }

        return $institution !== null
            && $this->authorizer->allows($actor, CapabilityCatalog::SETUP_INSTITUTION_MANAGE, [
                'institution_id' => (int) $institution->id,
            ]);
    }

    /**
     * Authorize metadata access, returning the resolved Institution or aborting.
     */
    public function authorizeMetadata(?Authenticatable $actor, string $domain, ?Institution $institution = null): Institution
    {
        abort_unless($actor !== null, 401);
        $institution ??= $this->resolveInstitution($actor);

        abort_unless(
            $this->canAccessMetadata($actor, $domain, $institution),
            403,
            "Forbidden: You do not have permission to view {$domain} communication templates."
        );

        return $institution;
    }

    /**
     * Authorize full-source access, returning the resolved Institution or aborting.
     */
    public function authorizeFullSource(?Authenticatable $actor, string $domain, ?Institution $institution = null): Institution
    {
        abort_unless($actor !== null, 401);
        $institution ??= $this->resolveInstitution($actor);

        abort_unless(
            $this->canAccessFullSource($actor, $domain, $institution),
            403,
            "Forbidden: You do not have permission to author or manage {$domain} communication templates."
        );

        return $institution;
    }

    /**
     * Project canonical communication template family metadata.
     * Exposes strictly the non-sensitive metadata and omits sample copy, blueprints, and asset IDs.
     *
     * Returns ONLY:
     * {id, domain, slug, name, description, active_version, family_revision, has_draft, updated_at, channels, supports_pdf, pdf_name, authoring_mode}
     *
     * @param  CommunicationTemplate|array<string, mixed>  $family
     * @return array<string, mixed>
     */
    public function projectMetadata(CommunicationTemplate|array $family): array
    {
        $isModel = $family instanceof CommunicationTemplate;
        $domain = (string) ($family['domain'] ?? '');
        $slug = (string) ($family['slug'] ?? $family['key'] ?? '');
        $slot = CommunicationTemplateRegistry::findBySlug($domain, $slug);
        $eventKey = $family['event_key'] ?? $slot['event_key'] ?? null;
        $hasDraft = $isModel
            ? ($family->relationLoaded('draft') ? $family->draft !== null : $family->draft()->exists())
            : (bool) ($family['has_draft'] ?? false);
        $updatedAt = $family['updated_at'] ?? null;
        $authoringMode = CommunicationTemplateRegistry::authoringMode($domain, $slug);
        $builtin = CommunicationTemplateRegistry::builtinMessageDefinition($domain, $slug);
        $supportsPdf = $slot !== null && $eventKey === $slot['event_key'] && CommunicationTemplateRegistry::supportsPdf(
            $domain, $slug, (string) $eventKey,
        );

        return [
            'id' => (string) ($family['id'] ?? ''),
            'domain' => $domain,
            'slug' => $slug,
            'name' => (string) ($slot['name'] ?? ''),
            'description' => $slot['description'] ?? null,
            'active_version' => (int) ($family['active_version'] ?? 0),
            'family_revision' => (int) ($family['family_revision'] ?? 0),
            'has_draft' => $hasDraft,
            'updated_at' => $updatedAt instanceof DateTimeInterface
                ? $updatedAt->format(DATE_ATOM)
                : ($updatedAt === null ? null : (string) $updatedAt),
            'channels' => $slot['channels'] ?? [],
            'supports_pdf' => $supportsPdf,
            'pdf_name' => $supportsPdf ? match ($domain) {
                'admissions' => 'Official-Offer-Letter.pdf',
                'examination' => 'Examination-Permit.pdf',
                'results' => 'Official-Scorecard.pdf',
            } : null,
            'authoring_mode' => $authoringMode,
            'type' => $authoringMode,
            'purpose' => $builtin['purpose'] ?? ($slot['description'] ?? null),
        ];
    }

    /**
     * Project a collection of template families to their metadata representation.
     *
     * @param  iterable<CommunicationTemplate|array<string, mixed>>  $families
     * @return list<array<string, mixed>>
     */
    public function projectMetadataCollection(iterable $families): array
    {
        $projected = [];
        foreach ($families as $family) {
            $projected[] = $this->projectMetadata($family);
        }

        return $projected;
    }
}
