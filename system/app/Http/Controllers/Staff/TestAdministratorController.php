<?php

namespace App\Http\Controllers\Staff;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Staffing\Actions\GrantTestAdministratorCapabilities;
use App\Staffing\Actions\RevokeTestAdministratorCapabilities;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use RuntimeException;

class TestAdministratorController extends Controller
{
    public function grant(
        Request $request,
        User $user,
        GrantTestAdministratorCapabilities $action,
    ): RedirectResponse {
        try {
            $action->handle($request->user(), $user);
        } catch (RuntimeException $exception) {
            return back()->withErrors(['ta' => $exception->getMessage()]);
        }

        return back()->with('status', 'Test Administrator capabilities granted.');
    }

    public function revoke(
        Request $request,
        User $user,
        RevokeTestAdministratorCapabilities $action,
    ): RedirectResponse {
        try {
            $action->handle($request->user(), $user);
        } catch (RuntimeException $exception) {
            return back()->withErrors(['ta' => $exception->getMessage()]);
        }

        return back()->with('status', 'Test Administrator capabilities revoked.');
    }
}
