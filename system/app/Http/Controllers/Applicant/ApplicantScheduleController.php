<?php

namespace App\Http\Controllers\Applicant;

use App\Admission\Support\ApplicantScope;
use App\Examination\Actions\ReserveExamSlot;
use App\Examination\Queries\ApplicantSlotCalendar;
use App\Http\Controllers\Controller;
use App\Models\Admission\AdmissionAttempt;
use App\Models\Examination\ExamAssignment;
use App\Models\Examination\ExamSession;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use RuntimeException;

class ApplicantScheduleController extends Controller
{
    public function __construct(private readonly ApplicantSlotCalendar $calendar)
    {
    }

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
            ->with(['examSession.room:id,code,name'])
            ->where('admission_attempt_id', $attempt->id)
            ->first();

        if ($assignment !== null) {
            $session = $assignment->examSession;
            $published = in_array($session?->status, [
                ExamSession::STATUS_PUBLISHED,
                ExamSession::STATUS_IN_PROGRESS,
                ExamSession::STATUS_COMPLETED,
            ], true);

            return $this->renderSchedule(
                pageState: 'assigned_published',
                attempt: $attemptPayload,
                application: $application,
                assignment: [
                    'id' => $assignment->id,
                    'source' => $assignment->source,
                    'session' => $session ? [
                        'code' => $session->code,
                        'status' => $session->status,
                        'date' => $session->starts_at?->toDateString(),
                        'start_time' => $session->starts_at?->format('H:i'),
                        'end_time' => $session->effective_end_at?->format('H:i'),
                        'room' => $session->room?->name ?: $session->room?->code,
                        'published' => $published,
                    ] : null,
                ],
                message: $published
                    ? 'Your exam seat is confirmed.'
                    : 'You are assigned to a session. Staff will publish the schedule when ready.',
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
            ->with('status', 'Exam seat reserved.');
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
    ): Response {
        return Inertia::render('Applicant/Schedule', [
            'page_state' => $pageState,
            'attempt' => $attempt,
            'assignment' => $assignment,
            'calendar_days' => $calendarDays,
            'message' => $message,
            'application' => $application,
            'pipeline' => $this->pipelineFor($pageState, $attempt, $assignment),
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
        // Only one "Now" at a time: while picking a slot, seat stays Next.
        $seatStatus = match ($pageState) {
            'assigned_published' => 'done',
            default => 'upcoming',
        };
        $officialStatus = match ($pageState) {
            'assigned_published' => ($assignment['session']['published'] ?? false) ? 'done' : 'current',
            default => 'upcoming',
        };

        $windowSummary = match ($pageState) {
            'pending_acceptance' => 'Opens after acceptance.',
            'waiting_for_reservation_open' => 'Guidance has not opened a session for booking yet — or this wave is staff-assign only.',
            'calendar_open' => 'Slots are open. Pick a time below.',
            'assigned_published' => 'You already have a seat.',
            default => null,
        };

        $seatSummary = match ($pageState) {
            'assigned_published' => isset($assignment['session'])
                ? trim(($assignment['session']['date'] ?? '').' · '.($assignment['session']['room'] ?? ''))
                : 'Seat assigned.',
            'calendar_open' => 'After you confirm below, your seat lands here.',
            'waiting_for_reservation_open' => 'Waiting until a session opens or Guidance assigns you.',
            default => 'Your reserved or assigned seat will show here.',
        };

        $officialSummary = match ($pageState) {
            'assigned_published' => ($assignment['session']['published'] ?? false)
                ? 'Published — this is your official exam schedule.'
                : 'Assigned; waiting for staff to publish the announce.',
            default => 'Staff publish makes the schedule official.',
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
                'label' => 'Your exam seat',
                'status' => $seatStatus,
                'summary' => $seatSummary,
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
