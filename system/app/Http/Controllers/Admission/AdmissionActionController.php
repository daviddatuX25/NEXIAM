<?php

namespace App\Http\Controllers\Admission;

use App\Admission\Actions\AcceptAdmissionAttempt;
use App\Admission\Actions\ArchiveAdmissionAttempt;
use App\Admission\Actions\AuthorizeAcceptedAdmissionAmendment;
use App\Admission\Actions\BulkVerifyAdmissionAttemptDocuments;
use App\Admission\Actions\CorrectAdmissionAttempt;
use App\Admission\Actions\DismissAdmissionAttempt;
use App\Admission\Actions\EnsureAttemptRequirementChecklist;
use App\Admission\Actions\ReconsiderAdmissionAttempt;
use App\Admission\Actions\ReissueApplicantAccessKey;
use App\Admission\Actions\ReopenAdmissionAttempt;
use App\Admission\Actions\RequestApplicationCorrection;
use App\Authorization\CapabilityAuthorizer;
use App\Authorization\CapabilityCatalog;
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
use App\Models\Foundation\Institution;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use RuntimeException;

/**
 * Admission-attempt state transitions. Each method delegates to a domain
 * Action after enforcing fresh, resource-scoped Registrar authorization.
 */
class AdmissionActionController extends Controller
{
    public function accept(
        AcceptAdmissionRequest $request,
        AdmissionAttempt $attempt,
        AcceptAdmissionAttempt $action,
        CapabilityAuthorizer $authorizer,
        BulkVerifyAdmissionAttemptDocuments $bulkVerify,
        EnsureAttemptRequirementChecklist $ensureChecklist,
    ): RedirectResponse {
        $this->requireScopedCapability($request, $attempt, $authorizer, CapabilityCatalog::REGISTRAR_APPLICATIONS_ACCEPT);

        $verifyDocuments = $request->boolean('verify_documents');
        if ($verifyDocuments) {
            $this->requireScopedCapability($request, $attempt, $authorizer, CapabilityCatalog::REGISTRAR_DOCUMENTS_VERIFY);
        }

        $accepted = $action->handle($attempt, $request->user(), $request->integer('expected_version'));

        if ($verifyDocuments) {
            $accepted->refresh();
            $set = $ensureChecklist->handle($accepted, $request->user());
            if ($set !== null) {
                $unverifiedItems = $set->items()
                    ->whereDoesntHave('latestVerification', fn ($q) => $q->where('status', 'verified'))
                    ->get();

                if ($unverifiedItems->isNotEmpty()) {
                    $itemsPayload = $unverifiedItems->map(fn ($item) => [
                        'requirement_item_id' => (int) $item->id,
                        'expected_latest_verification_id' => $item->latestVerification?->id,
                    ])->all();

                    $bulkVerify->handle(
                        attempt: $accepted,
                        requirementSetId: (int) $set->id,
                        items: $itemsPayload,
                        actor: $request->user(),
                        expectedVersion: (int) $accepted->version,
                        idempotencyKey: 'accept-verify-'.$accepted->id.'-'.$accepted->version,
                    );

                    $verifiedCount = $unverifiedItems->count();

                    return back()->with('status', "Application accepted and {$verifiedCount} document requirement(s) verified.");
                }
            }

            return back()->with('status', 'Application accepted and all document requirements verified.');
        }

        return back()->with('status', 'Application accepted.');
    }

    public function dismiss(
        DismissAdmissionRequest $request,
        AdmissionAttempt $attempt,
        DismissAdmissionAttempt $action,
        CapabilityAuthorizer $authorizer,
    ): RedirectResponse {
        $this->requireScopedCapability($request, $attempt, $authorizer, CapabilityCatalog::REGISTRAR_APPLICATIONS_DISMISS);
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
        CapabilityAuthorizer $authorizer,
    ): RedirectResponse {
        $this->requireScopedCapability($request, $attempt, $authorizer, CapabilityCatalog::REGISTRAR_APPLICATIONS_CORRECT);
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

        return redirect()->route('admissions.show', $attempt)->with('status', $message);
    }

    public function requestCorrection(
        RequestApplicationCorrectionRequest $request,
        AdmissionAttempt $attempt,
        RequestApplicationCorrection $action,
        CapabilityAuthorizer $authorizer,
    ): RedirectResponse {
        $this->requireScopedCapability($request, $attempt, $authorizer, CapabilityCatalog::REGISTRAR_APPLICATIONS_CORRECT);
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
        CapabilityAuthorizer $authorizer,
    ): RedirectResponse {
        $this->requireScopedCapability($request, $attempt, $authorizer, CapabilityCatalog::REGISTRAR_APPLICATIONS_CORRECT);
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

        return redirect()->route('admissions.show', $attempt)->with(
            'status',
            'Authorized amendment recorded. Application stays accepted — exam assignment does not require a retake.',
        );
    }

    public function reconsider(
        ReconsiderAdmissionRequest $request,
        AdmissionAttempt $attempt,
        ReconsiderAdmissionAttempt $action,
        CapabilityAuthorizer $authorizer,
    ): RedirectResponse {
        $this->requireScopedCapability($request, $attempt, $authorizer, CapabilityCatalog::REGISTRAR_APPLICATIONS_CORRECT);
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
        CapabilityAuthorizer $authorizer,
    ): RedirectResponse {
        $this->requireScopedCapability($request, $attempt, $authorizer, CapabilityCatalog::REGISTRAR_APPLICATIONS_CORRECT);
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
        CapabilityAuthorizer $authorizer,
    ): RedirectResponse {
        $this->requireScopedCapability($request, $attempt, $authorizer, CapabilityCatalog::REGISTRAR_APPLICATIONS_CORRECT);
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
        CapabilityAuthorizer $authorizer,
    ): RedirectResponse {
        $this->requireScopedCapability($request, $attempt, $authorizer, CapabilityCatalog::REGISTRAR_APPLICATIONS_CORRECT);
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

        $request->session()->put('reissued_access_key', $result['access_token']);
        $request->session()->put('credential_handoff', $result['credential_handoff']);

        return redirect()
            ->route('admissions.show', $result['attempt'])
            ->with('status', 'Access key reissued. Copy it now — it will not be shown again.');
    }

    private function requireScopedCapability(
        Request $request,
        AdmissionAttempt $attempt,
        CapabilityAuthorizer $authorizer,
        string $capability,
    ): void {
        $user = $request->user();
        if (! $user instanceof User) {
            abort(403);
        }

        $actorInstitutionId = $user->staffProfile?->institution_id ?? Institution::current()->id;
        $scopedAttempt = AdmissionAttempt::query()
            ->forWorkspace()
            ->whereKey($attempt->getKey())
            ->where('institution_id', $actorInstitutionId)
            ->whereHas('academicCycle', fn ($cycle) => $cycle->where('institution_id', $actorInstitutionId))
            ->firstOrFail();

        abort_unless($authorizer->decideFresh($user, $capability, [
            'institution_id' => (int) $scopedAttempt->institution_id,
            'office' => 'registrar',
        ])->allowed, 403);
    }
}
