<?php

namespace App\Http\Controllers\Setup;

use App\Http\Controllers\Controller;
use App\Http\Requests\Setup\UpdateExamNotificationPolicyRequest;
use App\Models\Examination\ExamSession;
use App\Models\Foundation\AcademicCycle;
use App\Models\Notifications\CommunicationTemplate;
use App\Notifications\Policies\ExamNotificationPolicy;
use App\Notifications\Support\CommunicationTemplateScope;
use App\Notifications\Templates\Queries\NoticeTemplateNavigationQuery;
use App\Setup\Actions\UpdateExamNotificationPolicy;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class NotificationExaminationDeskController extends Controller
{
    /**
     * Show institution-scoped Examination defaults and session overrides.
     */
    public function show(
        Request $request,
        CommunicationTemplateScope $scope,
        NoticeTemplateNavigationQuery $navigationQuery,
    ): Response {
        $user = $request->user();
        $institution = $scope->authorizeMetadata($user, 'examination');
        $institutionId = (int) $institution->id;
        $activeCycle = AcademicCycle::query()
            ->where('institution_id', $institutionId)
            ->where('is_active', true)
            ->first();

        $customSessions = ExamSession::query()
            ->whereNotNull('notification_policy')
            ->whereHas('academicCycle', fn ($query) => $query->where('institution_id', $institutionId))
            ->with(['room', 'academicCycle'])
            ->orderByDesc('id')
            ->limit(20)
            ->get()
            ->map(fn (ExamSession $session): array => [
                'id' => $session->id,
                'code' => $session->code,
                'status' => $session->status,
                'cycle_label' => $session->academicCycle?->staffLabel(),
                'starts_at' => $session->starts_at?->format('M d, Y h:i A') ?? 'TBA',
                'room_name' => $session->room?->name ?? 'Room '.$session->room_id,
                'publication_email_enabled' => (bool) ($session->notification_policy['publication_email_enabled'] ?? true),
                'reminder_email_enabled' => (bool) ($session->notification_policy['reminder_email_enabled'] ?? true),
                'reminder_offsets_minutes' => (array) ($session->notification_policy['reminder_offsets_minutes'] ?? []),
            ]);

        $notices = $scope->projectMetadataCollection(CommunicationTemplate::query()
            ->where('institution_id', $institutionId)
            ->where('domain', 'examination')
            ->with('draft:id,communication_template_id')
            ->orderBy('name')
            ->get(['id', 'domain', 'event_key', 'slug', 'active_version', 'family_revision', 'updated_at']));

        return Inertia::render('Setup/Notifications/Examination', [
            'active_cycle' => $activeCycle ? [
                'id' => $activeCycle->id,
                'label' => $activeCycle->staffLabel(),
            ] : null,
            'policy' => ExamNotificationPolicy::fromStorage($activeCycle?->exam_notification_policy)->toArray(),
            'navigation' => $navigationQuery->forDesk($user, 'examination'),
            'custom_sessions' => $customSessions,
            'notices' => $notices,
            'capabilities' => [
                'can_manage' => $scope->canAccessFullSource($request->user(), 'examination', $institution),
            ],
            'page_state' => 'ready',
        ]);
    }

    /**
     * Update the active institution cycle's Examination notification defaults.
     */
    public function update(
        UpdateExamNotificationPolicyRequest $request,
        CommunicationTemplateScope $scope,
        UpdateExamNotificationPolicy $action,
    ): RedirectResponse {
        $institution = $scope->authorizeFullSource($request->user(), 'examination');
        $institutionId = (int) $institution->id;

        $validated = $request->validated();

        $activeCycle = AcademicCycle::query()
            ->where('institution_id', $institutionId)
            ->where('is_active', true)
            ->firstOrFail();

        try {
            $action->handle($activeCycle, $request->user(), [
                'expected_version' => (int) $validated['expected_version'],
                'publication_email_enabled' => (bool) $validated['publication_email_enabled'],
                'reminder_email_enabled' => (bool) $validated['reminder_email_enabled'],
                'reminder_offsets_minutes' => array_map('intval', $validated['reminder_offsets_minutes']),
            ]);
        } catch (ValidationException $exception) {
            return back()->withErrors($exception->errors());
        }

        return back()->with('status', 'Examination notification policy updated.');
    }
}
