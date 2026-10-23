<?php

namespace App\Http\Controllers\Setup;

use App\Authorization\CapabilityAuthorizer;
use App\Authorization\CapabilityCatalog;
use App\Http\Controllers\Controller;
use App\Http\Requests\Setup\UpdateAdmissionNotificationPolicyRequest;
use App\Http\Requests\Setup\UpdateExamNotificationPolicyRequest;
use App\Models\Foundation\AcademicCycle;
use App\Setup\Actions\UpdateAdmissionNotificationPolicy;
use App\Setup\Actions\UpdateExamNotificationPolicy;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class CycleNotificationPolicyController extends Controller
{
    public function edit(
        Request $request,
        AcademicCycle $cycle,
        CapabilityAuthorizer $authorizer,
    ): RedirectResponse {
        $admissionAllowed = $authorizer->allows(
            $request->user(),
            CapabilityCatalog::SETUP_INSTITUTION_MANAGE,
            ['institution_id' => (int) $cycle->institution_id],
        ) || $authorizer->allows(
            $request->user(),
            CapabilityCatalog::REGISTRAR_APPLICATIONS_VIEW,
            ['institution_id' => (int) $cycle->institution_id, 'office' => 'registrar'],
        );
        $examAllowed = $authorizer->allows(
            $request->user(),
            CapabilityCatalog::GUIDANCE_EXAM_SCHEDULE,
            ['institution_id' => (int) $cycle->institution_id, 'office' => 'guidance'],
        );

        abort_unless($admissionAllowed || $examAllowed, 403);
        abort_unless($cycle->is_active, 409, 'Email & Notifications manages the active academic year. Open it from Setup.');

        return redirect()->route('setup.notifications');
    }

    public function updateAdmissions(
        UpdateAdmissionNotificationPolicyRequest $request,
        AcademicCycle $cycle,
        CapabilityAuthorizer $authorizer,
        UpdateAdmissionNotificationPolicy $action,
    ): RedirectResponse {
        abort_unless(
            $authorizer->allows(
                $request->user(),
                CapabilityCatalog::SETUP_INSTITUTION_MANAGE,
                ['institution_id' => (int) $cycle->institution_id],
            ) || $authorizer->allows(
                $request->user(),
                CapabilityCatalog::REGISTRAR_APPLICATIONS_VIEW,
                ['institution_id' => (int) $cycle->institution_id, 'office' => 'registrar'],
            ),
            403,
        );

        try {
            $action->handle($cycle, $request->user(), $request->validated());
        } catch (ValidationException $exception) {
            return back()->withErrors($exception->errors());
        }

        return back()->with('status', 'Admissions notification policy updated.');
    }

    public function updateExamination(
        UpdateExamNotificationPolicyRequest $request,
        AcademicCycle $cycle,
        CapabilityAuthorizer $authorizer,
        UpdateExamNotificationPolicy $action,
    ): RedirectResponse {
        abort_unless($authorizer->allows(
            $request->user(),
            CapabilityCatalog::GUIDANCE_EXAM_SCHEDULE,
            ['institution_id' => (int) $cycle->institution_id, 'office' => 'guidance'],
        ), 403);

        try {
            $action->handle($cycle, $request->user(), $request->validated());
        } catch (ValidationException $exception) {
            return back()->withErrors($exception->errors());
        }

        return back()->with('status', 'Examination notification policy updated.');
    }
}
