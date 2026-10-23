<?php

namespace App\Http\Controllers\Staff;

use App\Admission\Queries\ApplicantActionMenuQuery;
use App\Admission\Queries\ApplicantLookupQuery;
use App\Admission\Support\ApplicantQrCode;
use App\Admission\Support\ApplicantReference;
use App\Authorization\CapabilityAuthorizer;
use App\Authorization\CapabilityCatalog;
use App\Foundation\Actions\RecordAuditEvent;
use App\Http\Controllers\Controller;
use App\Http\Requests\Staff\ApplicantLookupRequest;
use App\Models\Admission\AdmissionAttempt;
use App\Models\Foundation\AcademicCycle;
use Illuminate\Http\JsonResponse;
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

    public function search(ApplicantLookupRequest $request, ApplicantLookupQuery $lookup): JsonResponse
    {
        return response()->json($lookup->search($request->user(), $request->validated()))
            ->header('Cache-Control', 'private, no-store');
    }

    public function actions(
        ApplicantLookupRequest $request,
        AdmissionAttempt $attempt,
        ApplicantActionMenuQuery $menu,
    ): JsonResponse {
        return response()->json($menu->projection($attempt, $request->user(), $request->validated()))
            ->header('Cache-Control', 'private, no-store');
    }

    public function resolve(
        ApplicantLookupRequest $request,
        ApplicantActionMenuQuery $menu,
        RecordAuditEvent $audit,
    ): Response|JsonResponse {
        $input = $request->validated();
        $code = trim($input['code']);
        try {
            $attempt = AdmissionAttempt::query()->forWorkspace()->find(ApplicantQrCode::verify(ApplicantQrCode::normalize($code)));
        } catch (RuntimeException) {
            $cycle = AcademicCycle::query()->where('institution_id', $request->user()->actorScope()->institutionId)
                ->where('code', $request->user()->actorScope()->activeCycleCode)->where('is_active', true)->first();
            $references = ApplicantReference::candidateCodes($code, $cycle);
            $attempt = $references === [] ? null : AdmissionAttempt::query()->forWorkspace()->whereIn('reference_code', $references)->first();
        }
        $payload = $attempt === null
            ? ['scan_result' => ['state' => 'no_destination', 'message' => 'No applicant destination is available in your current scope.'], 'destinations' => [], 'default_destination' => null, 'requires_choice' => false]
            : $menu->projection($attempt, $request->user(), $input);
        if (($payload['scan_result']['state'] ?? null) === 'resolved') {
            $audit->handle('applicant.qr_resolved', [
                'attempt_id' => $attempt->id, 'actor_id' => $request->user()->id,
            ], (string) $request->user()->id);
        }

        return $request->wantsJson()
            ? response()->json($payload)->header('Cache-Control', 'private, no-store')
            : Inertia::render('Staff/ApplicantScan', ['page_state' => 'ready', ...$payload]);
    }
}
