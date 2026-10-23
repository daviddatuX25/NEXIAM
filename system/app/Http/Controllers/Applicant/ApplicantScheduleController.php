<?php

namespace App\Http\Controllers\Applicant;

use App\Admission\Support\ApplicantScope;
use App\Examination\Actions\ChangeApplicantExamSlot;
use App\Examination\Actions\ReconcileOverdueExamSessions;
use App\Examination\Actions\ReserveExamSlot;
use App\Examination\Queries\ApplicantSlotCalendar;
use App\Http\Controllers\Controller;
use App\Http\Requests\Examination\SwitchExamSlotRequest;
use App\Models\Admission\AdmissionAttempt;
use App\Models\Examination\ExamAssignment;
use App\Models\Examination\ExamAttendanceRecord;
use App\Models\Examination\ExamSession;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;
use RuntimeException;

class ApplicantScheduleController extends Controller
{
    public function __construct(private readonly ApplicantSlotCalendar $calendar) {}

    public function show(Request $request): Response
    {
        $user = $request->user();
        $identityId = ApplicantScope::identityIdForUser($user);

        if ($identityId === null) {
            return $this->renderSchedule(
                pageState: 'denied',
                message: 'This account is not linked to an applicant identity.',
            );
        }

        $attempt = AdmissionAttempt::query()
            ->with(['identity:id,first_name,last_name,email'])
            ->where('applicant_identity_id', $identityId)
            ->orderByDesc('id')
            ->first();

        if ($attempt === null) {
            return $this->renderSchedule(
                pageState: 'denied',
                message: 'No application found for this account.',
            );
        }

        $attemptPayload = [
            'id' => $attempt->id,
            'reference_code' => $attempt->reference_code,
            'status' => $attempt->status,
            'academic_cycle_id' => $attempt->academic_cycle_id,
        ];

        $application = $this->applicationPayload($attempt);

        if (! $attempt->isExamEligible()) {
            return $this->renderSchedule(
                pageState: 'pending_acceptance',
                attempt: $attemptPayload,
                application: $application,
                message: 'Exam scheduling opens after your application is accepted.',
            );
        }

        $assignment = ExamAssignment::query()
            ->active()
            ->with([
                'examSession.room:id,code,name,building_name,floor_level,directions_hint,photo_path,latitude,longitude',
                'examSession.currentLogisticsPlan',
                'examSession.currentMaterialSnapshot',
                'activeSeatAllocation.sessionSeat',
                'activeLogisticsSeatAllocation.seat',
                'attendanceRecord',
                'submissionRecord',
            ])
            ->where('admission_attempt_id', $attempt->id)
            ->latest('id')
            ->first();

        if ($assignment !== null && $assignment->examSession !== null) {
            $reconciledSession = app(ReconcileOverdueExamSessions::class)->reconcileSessionIfOverdue($assignment->examSession);
            if ($reconciledSession->status !== $assignment->examSession->status) {
                $assignment = $assignment->fresh([
                    'examSession.room:id,code,name,building_name,floor_level,directions_hint,photo_path,latitude,longitude',
                    'examSession.currentLogisticsPlan',
                    'examSession.currentMaterialSnapshot',
                    'activeSeatAllocation.sessionSeat',
                    'activeLogisticsSeatAllocation.seat',
                    'attendanceRecord',
                    'submissionRecord',
                ]);
            }
            if ($assignment !== null && ! $assignment->isActive()) {
                $assignment = null;
            }
        }

        if ($assignment !== null) {
            $session = $assignment->examSession;
            $published = in_array($session?->status, [
                ExamSession::STATUS_PUBLISHED,
                ExamSession::STATUS_IN_PROGRESS,
                ExamSession::STATUS_COMPLETED,
            ], true);
            $attendanceOutcome = $assignment->attendanceRecord?->outcome;
            $examCompleted = $assignment->submissionRecord?->submitted_at !== null
                || ($session?->status === ExamSession::STATUS_COMPLETED
                    && $attendanceOutcome === ExamAttendanceRecord::OUTCOME_PRESENT);

            $room = $session?->room;
            $venue = $room === null ? null : [
                'id' => $room->id,
                'code' => $room->code,
                'name' => $room->name,
                'building_name' => $room->building_name,
                'floor_level' => $room->floor_level,
                'directions_hint' => $room->directions_hint,
                'photo_url' => $room->photo_path === null
                    ? null
                    : (str_starts_with($room->photo_path, '/')
                        ? $room->photo_path
                        : Storage::disk('public')->url($room->photo_path)),
                'latitude' => $room->latitude,
                'longitude' => $room->longitude,
            ];

            $plan = $session?->currentLogisticsPlan;
            if ($plan !== null) {
                $logisticsAllocation = $assignment->activeLogisticsSeatAllocation;
                $seat = $logisticsAllocation?->seat;
                $hasAssignedSeatInstruction = $plan->seating_mode === ExamSession::SEATING_MODE_ASSIGNED_SEATING
                    && $plan->published_at !== null
                    && $plan->is_current
                    && $logisticsAllocation !== null
                    && $logisticsAllocation->effective_to === null
                    && (int) $logisticsAllocation->exam_assignment_id === (int) $assignment->id
                    && (int) $logisticsAllocation->exam_session_id === (int) $session->id
                    && (int) $logisticsAllocation->logistics_plan_id === (int) $plan->id
                    && $seat !== null
                    && (int) $seat->logistics_plan_id === (int) $plan->id;
            } else {
                $snapshot = $session?->hasLogisticsGeneration() ? null : $session?->currentMaterialSnapshot;
                $allocation = $assignment->activeSeatAllocation;
                $seat = $allocation?->sessionSeat;
                $hasAssignedSeatInstruction = $snapshot?->is_current
                    && $snapshot->seating_mode === ExamSession::SEATING_MODE_ASSIGNED_SEATING
                    && (int) $snapshot->exam_session_id === (int) $session->id
                    && $allocation !== null && $allocation->effective_to === null
                    && (int) $allocation->exam_assignment_id === (int) $assignment->id
                    && $seat !== null && (int) $seat->material_snapshot_id === (int) $snapshot->id;
            }

            return $this->renderSchedule(
                pageState: $published ? 'assigned_published' : 'assigned_tentative',
                attempt: $attemptPayload,
                assignment: [
                    'id' => $assignment->id,
                    'version' => $assignment->version,
                    'source' => $assignment->source,
                    'source_label' => $assignment->source === ExamAssignment::SOURCE_APPLICANT_RESERVED
                        ? 'Reserved by you'
                        : 'Assigned by Guidance',
                    'exam_completed' => $examCompleted,
                    'attendance_outcome' => $attendanceOutcome,
                    'session' => $session ? [
                        'code' => $session->code,
                        'status' => $session->status,
                        'date' => $session->starts_at?->toDateString(),
                        'start_time' => $session->starts_at?->format('H:i'),
                        'end_date' => $session->effective_end_at?->toDateString(),
                        'end_time' => $session->effective_end_at?->format('H:i'),
                        'scheduled_at' => $session->starts_at?->toIso8601String(),
                        'effective_end_at' => $session->effective_end_at?->toIso8601String(),
                        'started_at' => $session->started_at?->toIso8601String(),
                        'closed_at' => $session->closed_at?->toIso8601String(),
                        'room_id' => $room?->id,
                        'room_code' => $room?->code,
                        'room' => $room === null ? null : ($room->code.' — '.$room->name),
                        'venue' => $venue,
                        'published' => $published,
                    ] : null,
                ],
                slotSwitch: $session?->status === ExamSession::STATUS_DRAFT
                    ? ['target_calendar_days' => $this->calendar->calendarDays($attempt, excludeSessionId: (int) $session->id)]
                    : null,
                message: $examCompleted
                    ? 'Exam Completed'
                    : ($published
                        ? ($assignment->source === ExamAssignment::SOURCE_APPLICANT_RESERVED
                            ? 'Reserved by you'
                            : 'Assigned by Guidance')
                        : 'You are assigned to an exam session. Staff will publish the schedule when ready.'),
                hasAssignedSeatInstruction: $hasAssignedSeatInstruction,
            );
        }

        $sessions = $this->calendar->openSlots($attempt);

        if ($sessions->isEmpty()) {
            return $this->renderSchedule(
                pageState: 'waiting_for_reservation_open',
                attempt: $attemptPayload,
                application: $application,
                message: 'No reservation window is open yet. Check back after Guidance opens a session for booking.',
            );
        }

        $calendarDays = $this->calendar->calendarDays($attempt);

        return $this->renderSchedule(
            pageState: 'calendar_open',
            attempt: $attemptPayload,
            application: $application,
            calendarDays: $calendarDays,
            message: 'Select an available exam slot and confirm your reservation.',
        );
    }

    public function reserve(Request $request, ReserveExamSlot $action): RedirectResponse
    {
        $validated = $request->validate([
            'exam_session_id' => ['required', 'integer', 'exists:exam_sessions,id'],
            'admission_attempt_id' => ['required', 'integer', 'exists:admission_attempts,id'],
            'idempotency_key' => ['nullable', 'string', 'max:120'],
        ]);

        $attempt = AdmissionAttempt::query()->findOrFail((int) $validated['admission_attempt_id']);
        abort_unless(ApplicantScope::attemptBelongsToUser($attempt, $request->user()), 403);

        $session = ExamSession::query()->findOrFail((int) $validated['exam_session_id']);

        try {
            $action->handle(
                $session,
                $attempt,
                $request->user(),
                $validated['idempotency_key'] ?? null,
            );
        } catch (RuntimeException $exception) {
            return back()->withErrors(['reserve' => $exception->getMessage()]);
        }

        return redirect()
            ->route('applicant.schedule')
            ->with('status', 'Exam slot reserved.');
    }

    public function switch(SwitchExamSlotRequest $request, ChangeApplicantExamSlot $action): RedirectResponse
    {
        $validated = $request->validated();
        $attempt = AdmissionAttempt::query()->findOrFail((int) $validated['admission_attempt_id']);
        abort_unless(ApplicantScope::attemptBelongsToUser($attempt, $request->user()), 403);
        $session = ExamSession::query()->findOrFail((int) $validated['exam_session_id']);

        try {
            $action->handle(
                $session,
                $attempt,
                $request->user(),
                (int) $validated['expected_assignment_id'],
                (int) $validated['expected_assignment_version'],
                $validated['idempotency_key'],
            );
        } catch (RuntimeException $exception) {
            return back()->withErrors(['switch' => $exception->getMessage()]);
        }

        return redirect()->route('applicant.schedule')->with('status', 'Appointment changed.');
    }

    /**
     * @param  array<string, mixed>|null  $attempt
     * @param  array<string, mixed>|null  $assignment
     * @param  list<array<string, mixed>>  $calendarDays
     */
    private function renderSchedule(
        string $pageState,
        ?array $attempt = null,
        ?array $assignment = null,
        array $calendarDays = [],
        string $message = '',
        ?array $application = null,
        bool $hasAssignedSeatInstruction = false,
        ?array $slotSwitch = null,
    ): Response {
        return Inertia::render('Applicant/Schedule', [
            'page_state' => $pageState,
            'attempt' => $attempt,
            'assignment' => $assignment,
            'calendar_days' => $calendarDays,
            'slot_switch' => $slotSwitch,
            'message' => $message,
            'application' => $application,
            'pipeline' => $this->pipelineFor($pageState, $attempt, $assignment),
            'has_assigned_seat_instruction' => $hasAssignedSeatInstruction,
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function applicationPayload(AdmissionAttempt $attempt): array
    {
        $needsCorrection = $attempt->status === AdmissionAttempt::STATUS_NEEDS_CORRECTION;
        $canQuietAmend = $attempt->status === AdmissionAttempt::STATUS_PENDING;
        $frozenAfterAccept = $attempt->status === AdmissionAttempt::STATUS_ACCEPTED;

        return [
            'id' => $attempt->id,
            'status' => $attempt->status,
            'needs_correction' => $needsCorrection,
            'correction_notes' => $attempt->correction_notes,
            'can_quiet_amend' => $canQuietAmend,
            'frozen_after_accept' => $frozenAfterAccept,
            'correction_href' => ($needsCorrection || $canQuietAmend)
                ? route('applicant.correction', $attempt, false)
                : null,
        ];
    }

    /**
     * @param  array<string, mixed>|null  $attempt
     * @param  array<string, mixed>|null  $assignment
     * @return list<array{id: string, label: string, status: string, summary: string|null}>
     */
    private function pipelineFor(
        string $pageState,
        ?array $attempt,
        ?array $assignment,
    ): array {
        if ($pageState === 'denied') {
            return [];
        }

        $acceptedDone = $pageState !== 'pending_acceptance';
        $windowStatus = match ($pageState) {
            'pending_acceptance' => 'upcoming',
            'waiting_for_reservation_open' => 'current',
            'calendar_open' => 'current',
            default => 'done',
        };
        $isExamCompleted = ! empty($assignment['exam_completed']);
        // Only one "Now" at a time: while picking a slot, appointment stays Next.
        // When exam attendance is already recorded, appointment is Done (not Next).
        $appointmentStatus = match ($pageState) {
            'assigned_tentative', 'assigned_published' => $isExamCompleted ? 'done' : 'upcoming',
            default => 'upcoming',
        };
        $officialStatus = match ($pageState) {
            'assigned_tentative', 'assigned_published' => ($assignment['session']['published'] ?? false) || $isExamCompleted ? 'done' : 'current',
            default => 'upcoming',
        };
        $windowSummary = match ($pageState) {
            'pending_acceptance' => 'Opens after acceptance.',
            'waiting_for_reservation_open' => 'Guidance has not opened a session for booking yet — or this wave is staff-assign only.',
            'calendar_open' => 'Slots are open. Pick a time below.',
            'assigned_tentative', 'assigned_published' => 'You already have an exam appointment.',
            default => null,
        };

        $appointmentSummary = match ($pageState) {
            'assigned_tentative', 'assigned_published' => $isExamCompleted
                ? 'Exam Completed'
                : (isset($assignment['session'])
                    ? trim(($assignment['session']['date'] ?? '').' · '.($assignment['session']['room'] ?? ''))
                    : 'Exam slot assigned.'),
            'calendar_open' => 'After you confirm below, your exam slot lands here.',
            'waiting_for_reservation_open' => 'Waiting until a session opens or Guidance assigns your slot.',
            default => 'Your reserved or assigned exam slot will show here.',
        };

        $officialSummary = match ($pageState) {
            'assigned_tentative', 'assigned_published' => $isExamCompleted
                ? 'Exam Completed'
                : (($assignment['session']['published'] ?? false)
                    ? 'Published — this is your official exam schedule.'
                    : 'Assigned; waiting for staff to publish the official schedule.'),
            default => 'Official schedule confirmation will appear once assigned.',
        };

        return [
            [
                'id' => 'accepted',
                'label' => 'Application accepted',
                'status' => $acceptedDone ? 'done' : 'current',
                'summary' => $acceptedDone
                    ? ('Ref '.($attempt['reference_code'] ?? '—').' is exam-eligible.')
                    : 'Registrar must accept before you can schedule.',
            ],
            [
                'id' => 'window',
                'label' => 'Schedule / reserve',
                'status' => $windowStatus,
                'summary' => $windowSummary,
            ],
            [
                'id' => 'seat',
                'label' => 'Appointment',
                'status' => $appointmentStatus,
                'summary' => $appointmentSummary,
            ],
            [
                'id' => 'official',
                'label' => 'Official schedule',
                'status' => $officialStatus,
                'summary' => $officialSummary,
            ],
        ];
    }
}
