<?php

namespace App\Ai\Surface;

use App\Models\Ai\XiamInstitutionPolicy;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;
use Throwable;

/**
 * Resolves exactly one SurfaceProfile from trusted server context.
 * Client URLs and role-name strings are never authority.
 */
final class SurfaceProfileResolver
{
    public function __construct(
        private readonly SurfaceProfileCatalog $catalog,
    ) {}

    /**
     * @return array{profile: SurfaceProfile}|array{profile: null, reason: string}
     */
    public function resolve(Request $request): array
    {
        $user = $request->user();

        if ($user === null) {
            return ['profile' => null, 'reason' => 'guest'];
        }

        $routeName = $request->route()?->getName();
        $isApplicantUser = $user->applicantAccount()->exists();
        $isStaffUser = $user->staffProfile()->exists();

        $candidates = [];

        if ($this->isBreakGlassContext($request, $user)) {
            $candidates[] = SurfaceProfileKey::BreakGlass;
        }

        if ($this->isOperationsRoute($routeName)) {
            $candidates[] = SurfaceProfileKey::Operations;
        }

        if ($this->isKnowledgeAdminRoute($routeName)) {
            $candidates[] = SurfaceProfileKey::KnowledgeAdmin;
        }

        if ($this->isProctorSessionContext($request, $user, $routeName)) {
            $candidates[] = SurfaceProfileKey::ProctorSession;
        }

        if ($isApplicantUser && $this->isApplicantRoute($routeName)) {
            $candidates[] = SurfaceProfileKey::Applicant;
        }

        if ($isStaffUser && $this->isStaffRoute($routeName)) {
            $candidates[] = SurfaceProfileKey::Staff;
        }

        // Authenticated applicant on applicant shell without a more specific surface.
        if ($candidates === [] && $isApplicantUser && ! $isStaffUser) {
            $candidates[] = SurfaceProfileKey::Applicant;
        }

        // Ordinary staff on staff shell.
        if ($candidates === [] && $isStaffUser && ! $isApplicantUser) {
            $candidates[] = SurfaceProfileKey::Staff;
        }
        if (in_array(SurfaceProfileKey::Applicant, $candidates, true) && $this->applicantAiDisabled($user)) {
            return ['profile' => null, 'reason' => 'applicant_ai_disabled'];
        }

        if ($candidates === []) {
            return ['profile' => null, 'reason' => 'unresolved'];
        }

        $unique = array_values(array_unique($candidates, SORT_REGULAR));

        if (count($unique) > 1) {
            // Deny-on-ambiguity: more than one leaf surface without a single winner by precedence
            // that clearly dominates (break-glass already wins if present).
            $winner = $this->pickByPrecedence($unique);

            if ($winner === null) {
                return ['profile' => null, 'reason' => 'ambiguous'];
            }

            // If both applicant and staff matched without higher surfaces, fail closed.
            if (
                in_array(SurfaceProfileKey::Applicant, $unique, true)
                && in_array(SurfaceProfileKey::Staff, $unique, true)
                && ! in_array($winner, [
                    SurfaceProfileKey::BreakGlass,
                    SurfaceProfileKey::Operations,
                    SurfaceProfileKey::KnowledgeAdmin,
                    SurfaceProfileKey::ProctorSession,
                ], true)
            ) {
                return ['profile' => null, 'reason' => 'ambiguous'];
            }

            return ['profile' => $this->catalog->get($winner)];
        }

        return ['profile' => $this->catalog->get($unique[0])];
    }

    /**
     * @param  list<SurfaceProfileKey>  $candidates
     */
    private function pickByPrecedence(array $candidates): ?SurfaceProfileKey
    {
        foreach (SurfaceProfileCatalog::precedence() as $key) {
            if (in_array($key, $candidates, true)) {
                return $key;
            }
        }

        return null;
    }

    private function isBreakGlassContext(Request $request, User $user): bool
    {
        // Break-glass sessions are not implemented in this cut.
        return false;
    }

    private function isOperationsRoute(?string $routeName): bool
    {
        if ($routeName === null) {
            return false;
        }

        return str_starts_with($routeName, 'ops.')
            || str_starts_with($routeName, 'operations.');
    }

    private function isKnowledgeAdminRoute(?string $routeName): bool
    {
        if ($routeName === null) {
            return false;
        }

        return str_starts_with($routeName, 'staff.knowledge.')
            || str_starts_with($routeName, 'knowledge.');
    }

    private function isProctorSessionContext(Request $request, User $user, ?string $routeName): bool
    {
        // ProctorSession is reserved for a future dedicated proctor terminal/guard.
        // General staff monitoring routes (/exams/monitoring) belong to the Staff surface.
        return false;
    }

    private function isApplicantRoute(?string $routeName): bool
    {
        if ($routeName === null) {
            return false;
        }

        return str_starts_with($routeName, 'applicant.');
    }

    private function applicantAiDisabled(User $user): bool
    {
        try {
            if (! Schema::hasTable('xiam_institution_policies')) {
                return false;
            }

            $institutionId = $user->applicantAccount()
                ->with('identity')
                ->first()
                ?->identity
                ?->institution_id;
            if ($institutionId === null) {
                return false;
            }

            $policy = XiamInstitutionPolicy::query()
                ->where('institution_id', $institutionId)
                ->first();

            return $policy !== null && $policy->applicant_inquiry_enabled === false;
        } catch (Throwable) {
            return true;
        }
    }

    private function isStaffRoute(?string $routeName): bool
    {
        if ($routeName === null) {
            return false;
        }

        if ($this->isApplicantRoute($routeName)) {
            return false;
        }

        return str_starts_with($routeName, 'staff.')
            || str_starts_with($routeName, 'setup.')
            || str_starts_with($routeName, 'admissions.')
            || str_starts_with($routeName, 'exams.')
            || str_starts_with($routeName, 'grading.')
            || str_starts_with($routeName, 'consultation.')
            || str_starts_with($routeName, 'release.')
            || $routeName === 'logout'
            || str_starts_with($routeName, 'ui-kit');
    }
}
