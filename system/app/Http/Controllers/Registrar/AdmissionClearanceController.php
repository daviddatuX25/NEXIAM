<?php

namespace App\Http\Controllers\Registrar;

use App\Admission\Actions\BulkVerifyAdmissionAttemptDocuments;
use App\Admission\Actions\ConfirmAdmissionClearance;
use App\Admission\Actions\InitializeAttemptRequirementSet;
use App\Admission\Actions\RevokeAdmissionClearance;
use App\Admission\Actions\VerifyAdmissionAttemptDocument;
use App\Http\Controllers\Controller;
use App\Http\Requests\Registrar\BulkVerifyDocumentsRequest;
use App\Http\Requests\Registrar\ConfirmClearanceRequest;
use App\Http\Requests\Registrar\InitializeRequirementsRequest;
use App\Http\Requests\Registrar\RevokeClearanceRequest;
use App\Http\Requests\Registrar\VerifyDocumentRequest;
use App\Models\Admission\AdmissionAttempt;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\RedirectResponse;
use RuntimeException;

class AdmissionClearanceController extends Controller
{
    public function initializeRequirements(
        InitializeRequirementsRequest $request,
        AdmissionAttempt $attempt,
        InitializeAttemptRequirementSet $action,
    ): RedirectResponse {
        $validated = $request->validated();

        try {
            $action->handle(
                $attempt,
                $request->user(),
                (int) $validated['expected_version'],
                (string) $validated['idempotency_key'],
            );
        } catch (RuntimeException $exception) {
            return back()->withErrors(['checklist' => $exception->getMessage()]);
        }

        return back()->with('status', 'Requirement checklist initialized.');
    }

    public function verifyDocument(
        VerifyDocumentRequest $request,
        AdmissionAttempt $attempt,
        VerifyAdmissionAttemptDocument $action,
    ): RedirectResponse {
        $validated = $request->validated();

        try {
            $action->handle(
                attempt: $attempt,
                requirementSetId: isset($validated['requirement_set_id']) ? (int) $validated['requirement_set_id'] : null,
                requirementItemId: isset($validated['requirement_item_id']) ? (int) $validated['requirement_item_id'] : null,
                expectedLatestVerificationId: isset($validated['expected_latest_verification_id'])
                    ? (int) $validated['expected_latest_verification_id']
                    : null,
                status: (string) $validated['status'],
                actor: $request->user(),
                expectedVersion: (int) $validated['expected_version'],
                applicantInstruction: $validated['applicant_instruction'] ?? null,
                internalRemarks: $validated['internal_remarks'] ?? null,
                idempotencyKey: (string) $validated['idempotency_key'],
                intakeRequirementId: isset($validated['intake_requirement_id']) ? (int) $validated['intake_requirement_id'] : null,
            );
        } catch (ModelNotFoundException $exception) {
            throw $exception;
        } catch (RuntimeException $exception) {
            return back()->withErrors(['verification' => $exception->getMessage()]);
        }

        return back()->with('status', 'Document requirement verification recorded.');
    }

    public function bulkVerifyDocuments(
        BulkVerifyDocumentsRequest $request,
        AdmissionAttempt $attempt,
        BulkVerifyAdmissionAttemptDocuments $action,
    ): RedirectResponse {
        $validated = $request->validated();

        try {
            $verifications = $action->handle(
                attempt: $attempt,
                requirementSetId: isset($validated['requirement_set_id']) ? (int) $validated['requirement_set_id'] : null,
                items: $validated['items'],
                actor: $request->user(),
                expectedVersion: (int) $validated['expected_version'],
                idempotencyKey: (string) $validated['idempotency_key'],
            );
        } catch (ModelNotFoundException $exception) {
            throw $exception;
        } catch (RuntimeException $exception) {
            return back()->withErrors(['verification' => $exception->getMessage()]);
        }

        $count = $verifications->count();

        return back()->with('status', "{$count} document requirement(s) verified.");
    }

    public function confirm(
        ConfirmClearanceRequest $request,
        AdmissionAttempt $attempt,
        ConfirmAdmissionClearance $action,
    ): RedirectResponse {
        $validated = $request->validated();

        try {
            $action->handle(
                attempt: $attempt,
                actor: $request->user(),
                expectedVersion: (int) $validated['expected_version'],
                idempotencyKey: (string) $validated['idempotency_key'],
            );
        } catch (RuntimeException $e) {
            return back()->withErrors(['clearance' => $e->getMessage()]);
        }

        return back()->with('status', 'Admission clearance confirmed.');
    }

    public function revoke(
        RevokeClearanceRequest $request,
        AdmissionAttempt $attempt,
        RevokeAdmissionClearance $action,
    ): RedirectResponse {
        $validated = $request->validated();

        try {
            $action->handle(
                attempt: $attempt,
                actor: $request->user(),
                expectedVersion: (int) $validated['expected_version'],
                reason: (string) $validated['reason'],
                idempotencyKey: (string) $validated['idempotency_key'],
            );
        } catch (RuntimeException $e) {
            return back()->withErrors(['clearance' => $e->getMessage()]);
        }

        return back()->with('status', 'Admission clearance revoked.');
    }
}
