<?php

namespace App\Http\Controllers\Registrar;

use App\Admission\Actions\RecordRequirementInternalNote;
use App\Http\Controllers\Controller;
use App\Http\Requests\Registrar\RecordRequirementInternalNoteRequest;
use App\Models\Admission\AdmissionAttempt;
use App\Models\Admission\AdmissionAttemptRequirementItem;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\RedirectResponse;
use InvalidArgumentException;
use RuntimeException;

class AdmissionRequirementInternalNoteController extends Controller
{
    public function store(
        RecordRequirementInternalNoteRequest $request,
        AdmissionAttempt $attempt,
        RecordRequirementInternalNote $action,
        ?AdmissionAttemptRequirementItem $requirementItem = null,
    ): RedirectResponse {
        $validated = $request->validated();

        try {
            $action->handle(
                attempt: $attempt,
                requirementSetId: isset($validated['requirement_set_id']) ? (int) $validated['requirement_set_id'] : null,
                requirementItemId: $requirementItem !== null
                    ? (int) $requirementItem->getKey()
                    : (isset($validated['requirement_item_id']) ? (int) $validated['requirement_item_id'] : null),
                expectedLatestVerificationId: isset($validated['expected_latest_verification_id'])
                    ? (int) $validated['expected_latest_verification_id']
                    : null,
                expectedLatestNoteId: isset($validated['expected_latest_note_id'])
                    ? (int) $validated['expected_latest_note_id']
                    : null,
                actor: $request->user(),
                expectedVersion: (int) $validated['expected_version'],
                internalRemarks: $validated['internal_remarks'] ?? null,
                idempotencyKey: (string) $validated['idempotency_key'],
                intakeRequirementId: isset($validated['intake_requirement_id']) ? (int) $validated['intake_requirement_id'] : null,
            );
        } catch (ModelNotFoundException $exception) {
            throw $exception;
        } catch (RuntimeException $exception) {
            return back()->withErrors(['conflict' => $exception->getMessage()]);
        } catch (InvalidArgumentException $exception) {
            return response()->json(['errors' => ['internal_remarks' => [$exception->getMessage()]]], 422);
        }

        return back()->with('status', 'Internal note saved.');
    }
}
