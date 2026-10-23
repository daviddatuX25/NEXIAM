<?php

namespace App\Http\Controllers\Auth;

use App\Auth\Actions\EndStaffPresenceSession;
use App\Auth\Actions\RevokeStaffSessionLease;
use App\Foundation\Actions\RecordAuditEvent;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;

class AuthenticatedSessionController extends Controller
{
    public function create(): Response
    {
        return Inertia::render('Auth/Login');
    }

    public function destroy(
        Request $request,
        RecordAuditEvent $recordAuditEvent,
        EndStaffPresenceSession $endPresenceSession,
        RevokeStaffSessionLease $revokeLease,
    ): RedirectResponse {
        $user = $request->user();
        $isApplicantOnly = $user !== null
            && $user->applicantAccount()->exists()
            && ! $user->staffProfile()->exists();

        $staffScope = ($user !== null && $request->session()->get('auth.surface') === 'staff')
            ? $user->actorScope()
            : null;

        if ($user !== null && $request->session()->get('auth.surface') === 'staff') {
            $revokeLease->handle($user, $request->session()->getId(), now());
            $endPresenceSession->handle($request, 'logout');
        }
        Auth::guard('web')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        $recordAuditEvent->handle($isApplicantOnly ? 'auth.applicant_logout' : 'auth.staff_logout', [
            'institution_id' => $staffScope?->institutionId,
            'user_id' => $user?->id,
            'office' => $staffScope?->office,
        ], $user ? (string) $user->id : 'anonymous');

        return redirect()->route($isApplicantOnly ? 'applicant.login' : 'login');
    }
}
