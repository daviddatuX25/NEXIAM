<?php

namespace App\Http\Controllers\Auth;

use App\Foundation\Actions\RecordAuditEvent;
use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
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

    public function store(LoginRequest $request, RecordAuditEvent $recordAuditEvent): RedirectResponse
    {
        $request->authenticate();
        $request->session()->regenerate();

        $user = $request->user();
        $recordAuditEvent->handle('auth.staff_login', [
            'user_id' => $user?->id,
            'email' => $user?->email,
            'office' => $user?->actorScope()->office,
        ], $user ? (string) $user->id : 'anonymous');

        if ($user !== null && $user->applicantAccount()->exists() && ! $user->staffProfile()->exists()) {
            return redirect()->intended(route('applicant.portal'));
        }

        return redirect()->intended(route('staff.home'));
    }

    public function destroy(Request $request, RecordAuditEvent $recordAuditEvent): RedirectResponse
    {
        $user = $request->user();
        $isApplicantOnly = $user !== null
            && $user->applicantAccount()->exists()
            && ! $user->staffProfile()->exists();

        Auth::guard('web')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        $recordAuditEvent->handle($isApplicantOnly ? 'auth.applicant_logout' : 'auth.staff_logout', [
            'user_id' => $user?->id,
        ], $user ? (string) $user->id : 'anonymous');

        return redirect()->route($isApplicantOnly ? 'applicant.login' : 'login');
    }
}
