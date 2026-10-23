<?php

declare(strict_types=1);

namespace App\Auth\Support;

use App\Models\User;

class StaffMfaPolicy
{
    public function isMfaRequired(User $user): bool
    {
        $state = $user->authenticationState;
        if ($state?->mfa_required === true) {
            return true;
        }
        if ($state?->mfa_exempt === true) {
            return false;
        }
        if (! config('ciam.mfa.enabled', true)) {
            return false;
        }
        $exemptRoles = config('ciam.mfa.exempt_roles', []);
        if (! empty($exemptRoles) && $user->hasAnyRole($exemptRoles)) {
            return false;
        }

        return true;
    }
}
