<?php

namespace App\Http\Controllers\Setup;

use App\Http\Controllers\Controller;
use App\Http\Requests\Setup\UpdateAdmissionNotificationPolicyRequest;
use App\Models\Foundation\AcademicCycle;
use App\Models\Notifications\CommunicationTemplate;
use App\Notifications\Policies\AdmissionNotificationPolicy;
use App\Notifications\Support\CommunicationTemplateScope;
use App\Notifications\Templates\Queries\NoticeTemplateNavigationQuery;
use App\Setup\Actions\UpdateAdmissionNotificationPolicy;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class NotificationAdmissionsDeskController extends Controller
{
    /**
     * Show institution-scoped Admissions notification defaults and notice catalog.
     */
    public function show(
        Request $request,
        CommunicationTemplateScope $scope,
        NoticeTemplateNavigationQuery $navigationQuery,
    ): Response {
        $user = $request->user();
        $institution = $scope->authorizeMetadata($user, 'admissions');
        $institutionId = (int) $institution->id;

        $activeCycle = AcademicCycle::query()
            ->where('institution_id', $institutionId)
            ->where('is_active', true)
            ->first();

        $notices = $scope->projectMetadataCollection(CommunicationTemplate::query()
            ->where('institution_id', $institutionId)
            ->where('domain', 'admissions')
            ->with('draft:id,communication_template_id')
            ->orderBy('name')
            ->get(['id', 'domain', 'event_key', 'slug', 'active_version', 'family_revision', 'updated_at']));

        return Inertia::render('Setup/Notifications/Admissions', [
            'active_cycle' => $activeCycle ? [
                'id' => $activeCycle->id,
                'label' => $activeCycle->staffLabel(),
            ] : null,
            'policy' => AdmissionNotificationPolicy::fromStorage($activeCycle?->admission_notification_policy)->toArray(),
            'navigation' => $navigationQuery->forDesk($user, 'admissions'),
            'notices' => $notices,
            'capabilities' => [
                'can_manage' => $scope->canAccessFullSource($request->user(), 'admissions', $institution),
            ],
            'page_state' => 'ready',
        ]);
    }

    /**
     * Update the active institution cycle's Admissions notification defaults.
     *
     * @throws ValidationException
     */
    public function update(
        UpdateAdmissionNotificationPolicyRequest $request,
        CommunicationTemplateScope $scope,
        UpdateAdmissionNotificationPolicy $action,
    ): RedirectResponse {
        $institution = $scope->authorizeFullSource($request->user(), 'admissions');
        $institutionId = (int) $institution->id;

        $validated = $request->validated();

        $activeCycle = AcademicCycle::query()
            ->where('institution_id', $institutionId)
            ->where('is_active', true)
            ->firstOrFail();

        try {
            $action->handle($activeCycle, $request->user(), [
                'expected_version' => (int) $validated['expected_version'],
                'applicant_status_email_enabled' => (bool) $validated['applicant_status_email_enabled'],
                'registrar_new_submission_alert_enabled' => (bool) $validated['registrar_new_submission_alert_enabled'],
            ]);
        } catch (ValidationException $exception) {
            return back()->withErrors($exception->errors());
        }

        return back()->with('status', 'Admissions notification policy updated.');
    }
}
