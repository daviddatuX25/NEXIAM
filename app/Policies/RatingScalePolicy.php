<?php

namespace App\Policies;

use App\Models\RatingScale;
use App\Models\User;

class RatingScalePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasAnyRole(['super_admin', 'registrar_administrator', 'test_administrator']);
    }

    public function create(User $user): bool
    {
        return $user->hasAnyRole(['super_admin', 'registrar_administrator', 'test_administrator']);
    }

    public function update(User $user, RatingScale $ratingScale): bool
    {
        return $user->hasAnyRole(['super_admin', 'registrar_administrator', 'test_administrator']);
    }

    public function delete(User $user, RatingScale $ratingScale): bool
    {
        return $user->hasAnyRole(['super_admin', 'registrar_administrator', 'test_administrator']);
    }
}
