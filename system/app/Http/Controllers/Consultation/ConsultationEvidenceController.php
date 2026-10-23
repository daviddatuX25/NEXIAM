<?php

namespace App\Http\Controllers\Consultation;

use App\Consultation\Actions\RecordSupplementalEvidence;
use App\Http\Requests\Consultation\RecordSupplementalEvidenceRequest;
use App\Models\Admission\AdmissionAttempt;
use Illuminate\Http\RedirectResponse;

class ConsultationEvidenceController
{
    public function store(RecordSupplementalEvidenceRequest $request, AdmissionAttempt $attempt, RecordSupplementalEvidence $action): RedirectResponse
    {
        $action->handle($attempt, $request->user(), $request->validated(), $request->input('idempotency_key'));

        return back()->with('status', 'Supplemental academic evidence recorded successfully.');
    }
}
