<?php

namespace App\Policies;

use App\Models\AcademicYear;
use App\Models\User;

class AcademicYearPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasAnyRole(['super_admin', 'registrar_administrator']);
    }

    public function create(User $user): bool
    {
        return $user->hasAnyRole(['super_admin', 'registrar_administrator']);
    }

    public function update(User $user, AcademicYear $academicYear): bool
    {
        return $user->hasAnyRole(['super_admin', 'registrar_administrator']);
    }

    public function activate(User $user, AcademicYear $academicYear): bool
    {
        return $user->hasAnyRole(['super_admin', 'registrar_administrator']);
    }
}
