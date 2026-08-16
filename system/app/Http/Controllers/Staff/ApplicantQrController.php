<?php

namespace App\Http\Controllers\Staff;

use App\Admission\Queries\ApplicantActionMenuQuery;
use App\Admission\Support\ApplicantQrCode;
use App\Authorization\CapabilityAuthorizer;
use App\Authorization\CapabilityCatalog;
use App\Http\Controllers\Controller;
use App\Models\Admission\AdmissionAttempt;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use RuntimeException;

class ApplicantQrController extends Controller
{
    public function scan(Request $request, CapabilityAuthorizer $authorizer): Response
    {
        $allowed = $authorizer->allowsAny($request->user(), [
            CapabilityCatalog::APPLICANT_QR_RESOLVE,
        ]);

        return Inertia::render('Staff/ApplicantScan', [
            'page_state' => $allowed ? 'ready' : 'denied',
        ]);
    }

    /**
     * Resolve the scanned locator and open the actor-specific action hub.
     */
    public function resolve(
        Request $request,
        ApplicantActionMenuQuery $menu,
        CapabilityAuthorizer $authorizer,
    ): Response|\Illuminate\Http\JsonResponse {
        if (! $authorizer->allows($request->user(), CapabilityCatalog::APPLICANT_QR_RESOLVE)) {
            return Inertia::render('Staff/ApplicantScan', ['page_state' => 'denied']);
        }

        $code = $request->string('code')->toString();

        try {
            $attemptId = ApplicantQrCode::verify($code);
            $attempt = AdmissionAttempt::query()->find($attemptId);
        } catch (RuntimeException $exception) {
            return Inertia::render('Staff/ApplicantScan', [
                'page_state' => 'ready',
                'scan_result' => ['state' => 'invalid', 'message' => $exception->getMessage()],
            ]);
        }

        if ($attempt === null) {
            return Inertia::render('Staff/ApplicantScan', [
                'page_state' => 'ready',
                'scan_result' => ['state' => 'invalid', 'message' => 'Code could not be resolved.'],
            ]);
        }

        $destinations = $menu->for($attempt, $request->user());

        $payload = [
            'scan_result' => ['state' => 'resolved', 'message' => 'Applicant resolved.'],
            'scanned_attempt' => [
                'id' => $attempt->id,
                'reference_code' => $attempt->reference_code,
            ],
            'destinations' => $destinations,
        ];

        // The shared scan MODAL resolves via axios (JSON) so the current page
        // is never swapped; the standalone /staff/scan page gets the render.
        if ($request->wantsJson()) {
            return response()->json($payload);
        }

        if ($destinations === []) {
            $payload['scan_result'] = ['state' => 'no_destination', 'message' => 'No action is available for your current duty or scope.'];
        }

        return Inertia::render('Staff/ApplicantScan', [
            'page_state' => 'ready',
            ...$payload,
        ]);
    }
}
