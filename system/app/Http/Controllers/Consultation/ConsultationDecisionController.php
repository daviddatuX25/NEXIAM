<?php

namespace App\Http\Controllers\Consultation;

use App\Authorization\CapabilityAuthorizer;
use App\Authorization\CapabilityCatalog;
use App\Http\Controllers\Controller;
use App\Http\Requests\Release\ConsultationDecisionRequest;
use App\Models\Admission\AdmissionAttempt;
use App\Release\Actions\RecordConsultationDecision;
use Illuminate\Http\RedirectResponse;
use RuntimeException;

class ConsultationDecisionController extends Controller
{
    public function store(
        ConsultationDecisionRequest $request,
        AdmissionAttempt $attempt,
        RecordConsultationDecision $action,
        CapabilityAuthorizer $authorizer,
    ): RedirectResponse {
        $validated = $request->validated();
        $poolFieldsSupplied = array_key_exists('default_program_code', $request->all())
            || array_key_exists('pool_options', $request->all())
            || array_key_exists('pool_reason', $request->all());

        $canManageSelection = $authorizer->allows($request->user(), CapabilityCatalog::GUIDANCE_SELECTION_MANAGE);

        if (($poolFieldsSupplied || $validated['decision_type'] === 'refer') && ! $canManageSelection) {
            abort(403);
        }

        if ($validated['decision_type'] === 'refer'
            && ! $authorizer->allows($request->user(), CapabilityCatalog::GUIDANCE_FOLLOW_UP_MANAGE)) {
            abort(403);
        }

        try {
            $action->handle(
                $attempt,
                $request->user(),
                $validated['decision_type'],
                $validated['notes'] ?? null,
                $validated['idempotency_key'],
                $validated['target_program_code'] ?? null,
                $validated['referral_reason'] ?? null,
                $validated['default_program_code'] ?? null,
                $validated['pool_options'] ?? null,
                $validated['pool_reason'] ?? null,
                $validated['encounter_id'] ?? null,
            );
        } catch (RuntimeException $exception) {
            return back()->withErrors(['decision' => $exception->getMessage()]);
        }

        return back()->with('status', 'Consultation decision recorded.');
    }
}
