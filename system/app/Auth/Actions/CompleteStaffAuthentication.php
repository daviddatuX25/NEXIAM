<?php

declare(strict_types=1);

namespace App\Auth\Actions;

use App\Admission\Support\ApplicantQrCode;
use App\Auth\Support\StaffAuthenticationProof;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use RuntimeException;

class CompleteStaffAuthentication
{
    private const PENDING_LOOKUP_KEY = 'auth.pending_applicant_lookup';

    public function __construct(private readonly EstablishStaffSession $establishStaffSession) {}

    public function handle(StaffAuthenticationProof $proof, Request $request, bool $remember = false): RedirectResponse
    {
        $this->establishStaffSession->handle($proof, $request, $remember);

        return $this->redirectAfterEstablished($request);
    }

    public function redirectAfterEstablished(Request $request): RedirectResponse
    {
        $pendingLookup = $request->session()->pull(self::PENDING_LOOKUP_KEY);
        $request->session()->forget('url.intended');

        return $this->isValidPendingLookup($pendingLookup)
            ? redirect()->route('staff.scan', ['code' => $pendingLookup['locator']])
            : redirect()->route('staff.home');
    }

    private function isValidPendingLookup(mixed $pendingLookup): bool
    {
        if (! is_array($pendingLookup)
            || ! is_string($pendingLookup['locator'] ?? null)
            || ! is_int($pendingLookup['expires_at'] ?? null)
            || $pendingLookup['expires_at'] <= now()->timestamp) {
            return false;
        }

        try {
            ApplicantQrCode::normalize($pendingLookup['locator']);
        } catch (RuntimeException) {
            return false;
        }

        return true;
    }
}
