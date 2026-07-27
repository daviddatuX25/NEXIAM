<?php

namespace App\Policies;

use App\Models\AptitudeArea;
use App\Models\User;

/**
 * Aptitude areas are managed by test_administrator and super_admin only.
 */
class AptitudeAreaPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasAnyRole(['super_admin', 'test_administrator']);
    }

    public function create(User $user): bool
    {
        return $user->hasAnyRole(['super_admin', 'test_administrator']);
    }

    public function update(User $user, AptitudeArea $aptitudeArea): bool
    {
        return $user->hasAnyRole(['super_admin', 'test_administrator']);
    }

    public function reorder(User $user): bool
    {
        return $user->hasAnyRole(['super_admin', 'test_administrator']);
    }
}
