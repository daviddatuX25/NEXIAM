<?php

declare(strict_types=1);

namespace App\Http\Controllers\Staff;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Staffing\Actions\GrantInstitutionalSetupDelegation;
use App\Staffing\Actions\RevokeInstitutionalSetupDelegation;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use RuntimeException;

class SetupDelegationController extends Controller
{
    public function grant(Request $request, User $user, GrantInstitutionalSetupDelegation $grant): RedirectResponse
    {
        $validated = $request->validate([
            'reason' => ['required', 'string', 'min:3', 'max:500'],
            'academic_cycle_id' => ['nullable', 'integer', 'exists:academic_cycles,id'],
            'scopes' => ['sometimes', 'array', 'min:1'],
            'scopes.*' => ['string', 'in:cycles,rooms,programs'],
        ]);
        $actor = $request->user();
        abort_unless($actor instanceof User, 403);

        try {
            $grant->handle(
                $actor,
                $user,
                (string) $validated['reason'],
                ! empty($validated['academic_cycle_id']) ? (int) $validated['academic_cycle_id'] : null,
                $validated['scopes'] ?? ['cycles', 'rooms', 'programs'],
            );
        } catch (RuntimeException $exception) {
            return back()->with('error', $exception->getMessage());
        }

        return back()->with('success', 'Institutional setup authority delegated.');
    }

    public function revoke(Request $request, User $user, RevokeInstitutionalSetupDelegation $revoke): RedirectResponse
    {
        $request->validate([
            'reason' => ['nullable', 'string', 'max:500'],
        ]);
        $actor = $request->user();
        abort_unless($actor instanceof User, 403);
        try {
            $revoke->handle(
                $actor,
                $user,
                trim((string) $request->input('reason', 'Institutional setup delegation revoked.'))
                    ?: 'Institutional setup delegation revoked.',
            );
        } catch (RuntimeException $exception) {
            return back()->with('error', $exception->getMessage());
        }

        return back()->with('success', 'Institutional setup authority revoked.');
    }
}
