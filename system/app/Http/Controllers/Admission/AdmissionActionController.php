<?php

namespace App\Http\Controllers\Admission;

use App\Admission\Actions\AcceptAdmissionAttempt;
use App\Admission\Actions\ArchiveAdmissionAttempt;
use App\Admission\Actions\AuthorizeAcceptedAdmissionAmendment;
use App\Admission\Actions\CorrectAdmissionAttempt;
use App\Admission\Actions\DismissAdmissionAttempt;
use App\Admission\Actions\ReconsiderAdmissionAttempt;
use App\Admission\Actions\ReissueApplicantAccessKey;
use App\Admission\Actions\ReopenAdmissionAttempt;
use App\Admission\Actions\RequestApplicationCorrection;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admission\AcceptAdmissionRequest;
use App\Http\Requests\Admission\ArchiveAdmissionRequest;
use App\Http\Requests\Admission\AuthorizeAcceptedAmendmentRequest;
use App\Http\Requests\Admission\CorrectAdmissionRequest;
use App\Http\Requests\Admission\DismissAdmissionRequest;
use App\Http\Requests\Admission\ReconsiderAdmissionRequest;
use App\Http\Requests\Admission\ReissueAccessKeyRequest;
use App\Http\Requests\Admission\ReopenAdmissionRequest;
use App\Http\Requests\Admission\RequestApplicationCorrectionRequest;
use App\Models\Admission\AdmissionAttempt;
use Illuminate\Http\RedirectResponse;
use RuntimeException;

/**
 * Admission-attempt state transitions. Each method is a thin delegate to a
 * domain Action; versioning and capability gates live in the Form Requests
 * and route middleware respectively (GF-21 — never role labels).
 */
class AdmissionActionController extends Controller
{
    public function accept(
        AcceptAdmissionRequest $request,
        AdmissionAttempt $attempt,
        AcceptAdmissionAttempt $action,
    ): RedirectResponse {
        $action->handle($attempt, $request->user(), $request->integer('expected_version'));

        return back()->with('status', 'Application accepted.');
    }

    public function dismiss(
        DismissAdmissionRequest $request,
        AdmissionAttempt $attempt,
        DismissAdmissionAttempt $action,
    ): RedirectResponse {
        $action->handle(
            $attempt,
            $request->user(),
            $request->string('reason')->toString(),
            $request->integer('expected_version'),
        );

        return back()->with('status', 'Application dismissed.');
    }

    public function correct(
        CorrectAdmissionRequest $request,
        AdmissionAttempt $attempt,
        CorrectAdmissionAttempt $action,
    ): RedirectResponse {
        $payload = $request->correctionPayload();
        $wasNeedsCorrection = $attempt->status === AdmissionAttempt::STATUS_NEEDS_CORRECTION;

        try {
            $action->handle(
                $attempt,
                $request->user(),
                (int) $payload['expected_version'],
                $payload,
            );
        } catch (RuntimeException $e) {
            return back()->withErrors(['version' => $e->getMessage()]);
        }

        $message = $wasNeedsCorrection
            ? 'Office fix recorded — application returned to pending review.'
            : 'Application fields updated.';

        return back()->with('status', $message);
    }

    public function requestCorrection(
        RequestApplicationCorrectionRequest $request,
        AdmissionAttempt $attempt,
        RequestApplicationCorrection $action,
    ): RedirectResponse {
        try {
            $action->handle(
                $attempt,
                $request->user(),
                $request->integer('expected_version'),
                $request->string('reason')->toString(),
            );
        } catch (RuntimeException $e) {
            return back()->withErrors(['version' => $e->getMessage()]);
        }

        return back()->with('status', 'Applicant asked to correct this application.');
    }

    public function authorizeAmendment(
        AuthorizeAcceptedAmendmentRequest $request,
        AdmissionAttempt $attempt,
        AuthorizeAcceptedAdmissionAmendment $action,
    ): RedirectResponse {
        $payload = $request->amendmentPayload();

        try {
            $action->handle(
                $attempt,
                $request->user(),
                (int) $payload['expected_version'],
                (string) $payload['reason'],
                $payload,
            );
        } catch (RuntimeException $e) {
            return back()->withErrors(['version' => $e->getMessage()]);
        }

        return back()->with(
            'status',
            'Authorized amendment recorded. Application stays accepted — exam assignment does not require a retake.',
        );
    }

    public function reconsider(
        ReconsiderAdmissionRequest $request,
        AdmissionAttempt $attempt,
        ReconsiderAdmissionAttempt $action,
    ): RedirectResponse {
        try {
            $action->handle(
                $attempt,
                $request->user(),
                $request->integer('expected_version'),
                $request->string('reason')->toString(),
            );
        } catch (RuntimeException $e) {
            return back()->withErrors(['version' => $e->getMessage()]);
        }

        return back()->with(
            'status',
            'Acceptance reconsidered — returned to pending. Existing exam links preserved for reconciliation; new scheduling requires accept again.',
        );
    }

    public function reopen(
        ReopenAdmissionRequest $request,
        AdmissionAttempt $attempt,
        ReopenAdmissionAttempt $action,
    ): RedirectResponse {
        try {
            $action->handle(
                $attempt,
                $request->user(),
                $request->integer('expected_version'),
                $request->string('reason')->toString(),
            );
        } catch (RuntimeException $e) {
            return back()->withErrors(['version' => $e->getMessage()]);
        }

        return back()->with('status', 'Application reopened without deleting history.');
    }

    public function archive(
        ArchiveAdmissionRequest $request,
        AdmissionAttempt $attempt,
        ArchiveAdmissionAttempt $action,
    ): RedirectResponse {
        try {
            $action->handle($attempt, $request->user(), $request->integer('expected_version'));
        } catch (RuntimeException $e) {
            return back()->withErrors(['version' => $e->getMessage()]);
        }

        return back()->with('status', 'Application archived.');
    }

    public function reissueAccessKey(
        ReissueAccessKeyRequest $request,
        AdmissionAttempt $attempt,
        ReissueApplicantAccessKey $action,
    ): RedirectResponse {
        try {
            $result = $action->handle(
                $attempt,
                $request->user(),
                $request->integer('expected_version'),
                $request->string('reason')->toString(),
            );
        } catch (RuntimeException $e) {
            return back()->withErrors(['version' => $e->getMessage()]);
        }

        return redirect()
            ->route('admissions.show', $result['attempt'])
            ->with('status', 'Access key reissued. Copy it now — it will not be shown again.')
            ->with('reissued_access_key', $result['access_token']);
    }
}
