<?php

declare(strict_types=1);

namespace App\Http\Controllers\Staff;

use App\Admission\Support\ApplicantQrCode;
use App\Http\Controllers\Controller;
use App\Models\Admission\AdmissionAttempt;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use RuntimeException;

final class ApplicantScanEntryController extends Controller
{
    private const PENDING_SESSION_KEY = 'auth.pending_applicant_lookup';

    public function show(Request $request, string $code): RedirectResponse
    {
        try {
            $locator = ApplicantQrCode::normalize($code);
        } catch (RuntimeException) {
            abort(404);
        }

        $user = $request->user();

        if ($user === null) {
            $request->session()->put(self::PENDING_SESSION_KEY, [
                'locator' => $locator,
                'expires_at' => now()->addMinutes(10)->timestamp,
            ]);

            return redirect()->route('login');
        }

        $attempt = AdmissionAttempt::query()->find(ApplicantQrCode::verify($locator));

        if ($request->session()->get('auth.surface') === 'staff') {
            return redirect()->route('staff.scan', ['code' => $locator]);
        }

        $applicantAccount = $user->applicantAccount()->first();

        if ($applicantAccount !== null) {
            abort_unless($attempt !== null && (int) $attempt->applicant_identity_id === (int) $applicantAccount->applicant_identity_id, 404);

            return redirect()->route('applicant.qr');
        }

        abort(403);
    }
}
