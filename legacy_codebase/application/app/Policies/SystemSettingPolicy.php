<?php

namespace App\Policies;

use App\Models\SystemSetting;
use App\Models\User;

class SystemSettingPolicy
{
    /**
     * Only super_admin can view or update system settings.
     */
    public function viewAny(User $user): bool
    {
        return $user->hasRole('super_admin');
    }

    public function update(User $user, SystemSetting $setting): bool
    {
        return $user->hasRole('super_admin');
    }
}
