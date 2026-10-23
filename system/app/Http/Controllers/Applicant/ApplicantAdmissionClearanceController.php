<?php

namespace App\Http\Controllers\Applicant;

use App\Admission\Support\ApplicantScope;
use App\Applicant\Support\ApplicantClearanceProjection;
use App\Http\Controllers\Controller;
use App\Models\Admission\AdmissionAttempt;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ApplicantAdmissionClearanceController extends Controller
{
    public function show(Request $request, ApplicantClearanceProjection $projection): Response
    {
        $user = $request->user();
        $identityId = ApplicantScope::identityIdForUser($user);
        $attempt = $identityId === null
            ? null
            : AdmissionAttempt::query()
                ->where('applicant_identity_id', $identityId)
                ->latest('id')
                ->first();

        return Inertia::render('Applicant/AdmissionClearance', [
            'page_state' => $attempt === null ? 'no_attempt' : 'ready',
            'clearance' => $attempt === null ? null : $projection->for($attempt, $user),
        ]);
    }
}
