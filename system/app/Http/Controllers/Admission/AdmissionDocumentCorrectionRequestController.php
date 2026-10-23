<?php

namespace App\Http\Controllers\Admission;

use App\Admission\Actions\RequestAdmissionDocumentCorrection;
use App\Admission\Exceptions\DocumentCorrectionRequestRejected;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admission\RequestAdmissionDocumentCorrectionRequest as DocumentCorrectionRequestForm;
use App\Models\Admission\AdmissionAttempt;
use App\Models\User;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\RedirectResponse;

final class AdmissionDocumentCorrectionRequestController extends Controller
{
    public function store(
        DocumentCorrectionRequestForm $request,
        AdmissionAttempt $attempt,
        RequestAdmissionDocumentCorrection $action,
    ): RedirectResponse {
        try {
            /** @var User $actor */
            $actor = $request->user();
            $result = $action->handle($attempt, $actor, $request->validated());
        } catch (ModelNotFoundException) {
            abort(404);
        } catch (DocumentCorrectionRequestRejected $exception) {
            return back()
                ->withErrors(['document_request' => $exception->getMessage()])
                ->with('document_correction_request_outcome', 'definite_rejection');
        }

        $response = back()
            ->with('document_correction_request_recorded', (int) $result['receipt']->getKey())
            ->with('status', 'Document correction request recorded.');

        if ($result['delivery_failed']) {
            $response->with('document_correction_request_delivery', 'failed');
        }

        return $response;
    }
}
