<?php

namespace App\Http\Controllers\Examination;

use App\Authorization\CapabilityAuthorizer;
use App\Authorization\CapabilityCatalog;
use App\Examination\Actions\ReconcileOverdueExamSessions;
use App\Examination\Queries\ExamBookletDistributionQuery;
use App\Examination\Queries\MonitoringRosterQuery;
use App\Http\Controllers\Controller;
use App\Models\Examination\ExamAssignment;
use App\Models\Examination\ExamSession;
use App\Models\Foundation\AcademicCycle;
use App\Models\Foundation\Institution;
use App\Models\Foundation\Room;
use App\Notifications\Policies\ExamNotificationPolicy;
use App\Testing\PracticeWorkspace;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

/**
 * HTTP adapter for Examinations & Testing-Day Conduct.
 *
 * This controller exposes the live exam-day monitoring hub and the per-session
 * conduct view. It coordinates the projection and authorization boundary for
 * five related operational responsibilities:
 *
 * - live examinee check-in and roster visibility;
 * - Test Administrator capability enforcement for session-level access;
 * - walk-in and transfer accommodation entry points;
 * - attendance and submission tracking during room operations; and
 * - real-time room and cycle telemetry for capacity awareness.
 *
 * Test Administrators authorized by scoped exam-management capabilities may access conduct sessions.
 * Historical duty records are retained as history and do not authorize access.
 * Monitoring roster and telemetry data are supplied by query services, while
 * state changes are delegated to dedicated domain actions and request objects.
 *
 * Scheduling and session publication are separate responsibilities. This
 * controller is concerned with the published/in-progress testing-day conduct
 * phase and presents its current state through Inertia; it does not make the
 * browser or route parameters authoritative for attendance, accommodation,
 * or submission outcomes.
 */
class ExamMonitoringController extends Controller
{
    public function monitoringIndex(
        Request $request,
        CapabilityAuthorizer $authorizer,
        MonitoringRosterQuery $query,
        ReconcileOverdueExamSessions $reconcileOverdue,
    ): Response {
        $user = $request->user();
        $institutionId = (int) ($user?->staffProfile?->institution_id ?? Institution::current()->id);
        $canManage = $authorizer->allowsAny(
            $user,
            CapabilityCatalog::examSessionManagementCapabilities(),
            ['institution_id' => $institutionId, 'office' => 'guidance'],
        );
        if (! $canManage) {
            abort(403, 'Unauthorized access to examination monitoring.');
        }

        $filters = [
            'search' => $request->string('search')->toString() ?: null,
            'date' => $request->string('date')->toString() ?: null,
            'room_id' => $request->filled('room_id') ? (int) $request->input('room_id') : null,
            'tab' => $request->string('tab')->toString() ?: null,
        ];

        $activeCycle = AcademicCycle::query()
            ->where('institution_id', $institutionId)
            ->where('is_active', true)
            ->first();
        if ($activeCycle !== null) {
            $reconcileOverdue->handle(includePublished: true, academicCycleId: (int) $activeCycle->id);
        }
        $activeSessions = $query->activeSessionsFor($activeCycle?->id, $filters);
        $historySessions = $query->historySessionsFor($activeCycle?->id, $filters, 15);
        $telemetry = $query->telemetryForCycle($activeCycle);
        $rooms = Room::query()->where('institution_id', $institutionId)->orderBy('name')->get(['id', 'code', 'name']);

        $canAccessCycleNotifications = $authorizer->allows(
            $user,
            CapabilityCatalog::GUIDANCE_EXAM_SCHEDULE,
            ['institution_id' => $institutionId, 'office' => 'guidance'],
        );

        $examPolicy = ExamNotificationPolicy::fromStorage($activeCycle?->exam_notification_policy);
        $reminderWindows = collect($examPolicy->reminderOffsetsMinutes)
            ->sortDesc()
            ->map(function (int $minutes) {
                if ($minutes % 60 === 0) {
                    return ($minutes / 60).'h';
                }

                return $minutes.'m';
            })
            ->values()
            ->all();

        if (empty($reminderWindows)) {
            $reminderWindows = config('examination.thresholds.reminder_windows', ['72h', '24h', '2h', '1h']);
        }

        $hasSessions = $activeSessions->isNotEmpty() || $historySessions->total() > 0;

        return Inertia::render('Examination/MonitoringIndex', [
            'page_state' => $hasSessions ? 'ready' : 'empty',
            'sessions' => $activeSessions,
            'active_sessions' => $activeSessions,
            'history_sessions' => $historySessions,
            'filters' => $filters,
            'rooms' => $rooms,
            'active_academic_cycle' => $activeCycle ? [
                'id' => $activeCycle->id,
                'code' => $activeCycle->code,
                'name' => $activeCycle->staffLabel(),
            ] : null,
            'can_manage' => $canManage,
            'telemetry' => $telemetry,
            'thresholds' => [
                'reminder_windows' => $reminderWindows,
                'auto_close_grace_minutes' => (int) config('examination.thresholds.auto_close_grace_minutes', 15),
                'door_checkin_lead_minutes' => (int) config('examination.thresholds.door_checkin_lead_minutes', 30),
                'absence_warning_threshold_percent' => (int) config('examination.thresholds.absence_warning_threshold_percent', 20),
                'cycle_label' => $activeCycle ? $activeCycle->staffLabel() : 'Active Cycle',
                'active_cycle_id' => $activeCycle?->id,
                'cycle_notifications_url' => ($canAccessCycleNotifications && $activeCycle)
                    ? route('setup.notifications')
                    : null,
            ],
        ]);
    }

    public function monitoring(
        Request $request,
        ExamSession $session,
        CapabilityAuthorizer $authorizer,
        MonitoringRosterQuery $query,
        ReconcileOverdueExamSessions $reconcileOverdue,
    ): Response {
        $user = $request->user();
        $session->loadMissing('academicCycle', 'room');
        $sessionInstitutionId = (int) ($session->academicCycle?->institution_id ?? $session->room?->institution_id);
        $actorInstitutionId = (int) ($user?->staffProfile?->institution_id ?? Institution::current()->id);
        if ($sessionInstitutionId !== $actorInstitutionId
            || (string) ($session->practice_fixture_set_id ?? '') !== (string) (app(PracticeWorkspace::class)->currentId() ?? '')) {
            abort(404);
        }
        $scope = ['institution_id' => $sessionInstitutionId, 'office' => 'guidance'];
        $canManage = $authorizer->allowsAny($user, CapabilityCatalog::examSessionManagementCapabilities(), $scope);
        if (! $canManage) {
            abort(403);
        }

        if (! $request->filled('focus_assignment_id')) {
            $session = $reconcileOverdue->reconcileSessionIfOverdue($session);
        }
        $validated = $request->validate([
            'focus_assignment_id' => [
                'nullable',
                'integer',
                Rule::exists(ExamAssignment::class, 'id')
                    ->where('exam_session_id', $session->id),
            ],
        ]);

        $payload = $query->forSession($session);
        $accommodationContext = $query->accommodationContextFor($session, $canManage);
        $requestedReturn = $request->string('return_to')->toString();
        $returnTo = preg_match('#^/scoring/sessions/[0-9]+/omr\?omr_sheet=[0-9]+$#D', $requestedReturn) === 1
            || preg_match('#^/scoring/omr\?applicant_number=[A-Za-z0-9._%+-]+$#D', $requestedReturn) === 1
            || preg_match('#^/scoring/omr\?pending_capture=[0-9]+$#D', $requestedReturn) === 1
            ? $requestedReturn
            : null;

        $snapshot = $session->currentMaterialSnapshot;
        $isPhysicalSeating = $snapshot?->seating_mode === ExamSession::SEATING_MODE_ASSIGNED_SEATING;
        $hasMultipleSets = count($snapshot?->required_set_codes ?? []) > 1
            || ($snapshot !== null && $snapshot->sets()->count() > 1);

        return Inertia::render('Examination/Monitoring', [
            'session' => $payload['session'],
            'roster' => $payload['roster'],
            'focus_assignment_id' => $validated['focus_assignment_id'] ?? null,
            'desks' => $payload['desks'],
            'offline_actor_id' => (int) $user->getKey(),
            'can_manage' => $canManage,
            'accommodate_candidates' => $accommodationContext['accommodate_candidates'],
            'transfer_target_sessions' => $accommodationContext['transfer_target_sessions'],
            'session_remaining_capacity' => $accommodationContext['session_remaining_capacity'],
            'is_physical_seating' => $isPhysicalSeating,
            'has_multiple_sets' => $hasMultipleSets,
            'grandfathered_alternate_sets' => $hasMultipleSets && ! Institution::current()->usesMultiSetBooklets(),
            'can_start' => $session->status === ExamSession::STATUS_PUBLISHED && $session->closed_at === null,
            'can_record' => $session->attendanceIsOpen(),
            'submission_repair' => $returnTo !== null && $session->status === ExamSession::STATUS_COMPLETED,
            'return_to' => $returnTo,
            'thresholds' => [
                'door_checkin_lead_minutes' => (int) config('examination.thresholds.door_checkin_lead_minutes', 30),
                'auto_close_grace_minutes' => (int) config('examination.thresholds.auto_close_grace_minutes', 15),
                'absence_warning_threshold_percent' => (int) config('examination.thresholds.absence_warning_threshold_percent', 20),
            ],
        ]);
    }

    public function distribution(
        Request $request,
        ExamSession $session,
        ExamBookletDistributionQuery $query,
        CapabilityAuthorizer $authorizer,
    ): JsonResponse {
        $user = $request->user();
        $session->loadMissing('academicCycle', 'room');
        $sessionInstitutionId = (int) ($session->academicCycle?->institution_id ?? $session->room?->institution_id);
        $actorInstitutionId = (int) ($user?->staffProfile?->institution_id ?? Institution::current()->id);
        if ($sessionInstitutionId !== $actorInstitutionId
            || (string) ($session->practice_fixture_set_id ?? '') !== (string) (app(PracticeWorkspace::class)->currentId() ?? '')) {
            abort(404);
        }
        $canManage = $authorizer->allowsAny(
            $user,
            CapabilityCatalog::examSessionManagementCapabilities(),
            ['institution_id' => $sessionInstitutionId, 'office' => 'guidance'],
        );
        if (! $canManage) {
            abort(403);
        }
        $validated = $request->validate([
            'expected_revision' => ['nullable', 'integer', 'min:1'],
        ]);

        try {
            $payload = $query->handle(
                $session,
                $request->user(),
                isset($validated['expected_revision']) ? (int) $validated['expected_revision'] : null,
            );
        } catch (AuthorizationException $exception) {
            return response()->json(['message' => $exception->getMessage() ?: 'Forbidden.'], 403)
                ->header('Cache-Control', 'private, no-store');
        } catch (\RuntimeException $exception) {
            return response()->json(['message' => $exception->getMessage()], 409)
                ->header('Cache-Control', 'private, no-store');
        }

        return response()->json($payload)->header('Cache-Control', 'private, no-store');
    }
}
