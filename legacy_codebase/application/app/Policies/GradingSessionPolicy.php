<?php

namespace App\Policies;

use App\Models\GradingSession;
use App\Models\User;

class GradingSessionPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasAnyRole(['super_admin', 'test_administrator', 'registrar_administrator']);
    }

    public function view(User $user, GradingSession $gradingSession): bool
    {
        return $user->hasAnyRole(['super_admin', 'test_administrator', 'registrar_administrator']);
    }

    public function create(User $user): bool
    {
        return $user->hasAnyRole(['super_admin', 'test_administrator', 'registrar_administrator']);
    }

    public function update(User $user, GradingSession $gradingSession): bool
    {
        return $user->hasAnyRole(['super_admin', 'test_administrator', 'registrar_administrator']);
    }

    public function delete(User $user, GradingSession $gradingSession): bool
    {
        return $user->hasRole('super_admin');
    }
}
