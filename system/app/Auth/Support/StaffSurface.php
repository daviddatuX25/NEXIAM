<?php

declare(strict_types=1);

namespace App\Auth\Support;

use App\Models\User;
use Illuminate\Http\Request;

class StaffSurface
{
    public function allows(Request $request): bool
    {
        if ($request->session()->get('auth.surface') !== 'staff') {
            return false;
        }

        $user = $request->user();

        return $user instanceof User
            && $user->staffProfile()->first()?->isActive() === true;
    }
}
