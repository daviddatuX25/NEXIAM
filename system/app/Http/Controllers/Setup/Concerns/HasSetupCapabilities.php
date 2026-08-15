<?php

namespace App\Http\Controllers\Setup\Concerns;

use App\Authorization\CapabilityAuthorizer;
use App\Authorization\CapabilityCatalog;
use Illuminate\Http\Request;

/**
 * Shared Setup-surface gates (GF-21). The Setup section spans three capability
 * scopes with different owners:
 *
 * - institutional catalog (programs, rooms, cycles, windows, privacy, grades)
 *   → `registrar.setup.manage` (Registrar + Guidance, D-087)
 * - aptitude catalog + scoring → `guidance.assessment.catalog` (Test Administrator)
 * - program quotas → `program.quota.manage` (Program Head / Guidance TA path)
 *
 * Route middleware never uses Spatie role labels; every page gate delegates to
 * the CapabilityAuthorizer decision authority.
 */
trait HasSetupCapabilities
{
    /**
     * Page prop consumed by the Setup shell for per-surface visibility.
     *
     * @return array{setup: bool, assessment: bool, quota: bool}
     */
    private function capabilities(CapabilityAuthorizer $authorizer, Request $request): array
    {
        $user = $request->user();

        return [
            'setup' => $authorizer->allows($user, CapabilityCatalog::REGISTRAR_SETUP_MANAGE),
            'assessment' => $authorizer->allows($user, CapabilityCatalog::GUIDANCE_ASSESSMENT_CATALOG),
            'quota' => $authorizer->allows($user, CapabilityCatalog::PROGRAM_QUOTA_MANAGE),
        ];
    }

    private function requireSetup(CapabilityAuthorizer $authorizer, Request $request): void
    {
        abort_unless($authorizer->allows($request->user(), CapabilityCatalog::REGISTRAR_SETUP_MANAGE), 403);
    }

    private function requireAssessment(CapabilityAuthorizer $authorizer, Request $request): void
    {
        abort_unless($authorizer->allows($request->user(), CapabilityCatalog::GUIDANCE_ASSESSMENT_CATALOG), 403);
    }
}
