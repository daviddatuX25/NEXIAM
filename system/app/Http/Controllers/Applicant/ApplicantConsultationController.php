<?php

namespace App\Http\Controllers\Applicant;

use App\Admission\Support\ApplicantScope;
use App\Admission\Support\AttemptProgramFacts;
use App\Assessment\Support\IndividualScoringReadiness;
use App\Consultation\Actions\ChangeApplicantConsultationSlot;
use App\Consultation\Actions\ReserveConsultationSlot;
use App\Consultation\Queries\ApplicantConsultationCalendar;
use App\Http\Controllers\Controller;
use App\Http\Requests\Consultation\ReserveConsultationRequest;
use App\Http\Requests\Consultation\SwitchConsultationSlotRequest;
use App\Models\Admission\AdmissionAttempt;
use App\Models\Consultation\ConsultationAssignment;
use App\Models\Consultation\ConsultationSession;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use RuntimeException;

/**
 * Applicant release schedule: result-ready gate, open sessions + capacity,
 * own assignment, FCFS reservation through the shared ReservationGuard.
 */
class ApplicantConsultationController extends Controller
{
    public function __construct(
        private readonly ApplicantConsultationCalendar $calendar,
        private readonly IndividualScoringReadiness $scoringReadiness,
    ) {}

    public function show(Request $request): Response
    {
        $user = $request->user();
        $identityId = ApplicantScope::identityIdForUser($user);

        if ($identityId === null) {
            return $this->renderConsultation(
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
            return $this->renderConsultation(
                pageState: 'denied',
                message: 'No application found for this account.',
            );
        }

        $attemptPayload = [
            'id' => $attempt->id,
            'reference_code' => $attempt->reference_code,
            'status' => $attempt->status,
            'advised' => AttemptProgramFacts::isAdvised($attempt),
        ];

        if ($attemptPayload['advised']) {
            return $this->renderConsultation(
                pageState: 'advised_completed',
                attempt: $attemptPayload,
                message: 'Release Day completed',
            );
        }

        $assignment = ConsultationAssignment::query()
            ->active()
            ->with(['consultationSession.room:id,code,name,building_name,floor_level,photo_path,directions_hint,latitude,longitude'])
            ->where('admission_attempt_id', $attempt->id)
            ->first();

        $scoringReady = $this->scoringReadiness->forAttempt($attempt)['ready'];

        if ($assignment === null && ! $scoringReady) {
            return $this->renderConsultation(
                pageState: 'not_result_ready',
                attempt: $attemptPayload,
                message: 'Release Day appointment scheduling opens when your individual scoring is complete.',
            );
        }

        if ($assignment !== null) {
            $session = $assignment->consultationSession;
            $published = in_array($session?->status, [
                ConsultationSession::STATUS_PUBLISHED,
                ConsultationSession::STATUS_IN_PROGRESS,
                ConsultationSession::STATUS_COMPLETED,
            ], true);
            $targetCalendarDays = $session?->status === ConsultationSession::STATUS_DRAFT
                ? $this->calendar->calendarDays($attempt, excludeSessionId: (int) $session->id)
                : null;

            return $this->renderConsultation(
                pageState: 'assigned_or_reserved',
                attempt: $attemptPayload,
                assignment: [
                    'scoring_blocker' => $scoringReady ? null : 'Your individual scoring is incomplete. This appointment remains held while the scoring issue is resolved.',
                    'id' => $assignment->id,
                    'version' => $assignment->version,
                    'source' => $assignment->source,
                    'session' => $session ? [
                        'code' => $session->code,
                        'status' => $session->status,
                        'scheduled_at' => $session->scheduled_at?->toIso8601String(),
                        'room' => $session->room?->name ?: $session->room?->code,
                        'venue' => $session->room ? [
                            'id' => $session->room->id,
                            'code' => $session->room->code,
                            'name' => $session->room->name,
                            'building_name' => $session->room->building_name,
                            'floor_level' => $session->room->floor_level,
                            'photo_path' => $session->room->photo_path,
                            'directions_hint' => $session->room->directions_hint,
                            'latitude' => $session->room->latitude,
                            'longitude' => $session->room->longitude,
                        ] : null,
                        'published' => $published,
                    ] : null,
                ],
                slotSwitch: $targetCalendarDays !== null ? ['target_calendar_days' => $targetCalendarDays] : null,
                message: $published
                    ? 'Your Release Day appointment is confirmed.'
                    : 'Your Release Day appointment is scheduled. Staff will publish the schedule when ready.',
            );
        }

        $sessions = $this->calendar->openSessions($attempt);

        if ($sessions->isEmpty()) {
            return $this->renderConsultation(
                pageState: 'no_open_sessions',
                attempt: $attemptPayload,
                message: 'No Release Day appointment is open for booking yet. Check back later.',
            );
        }
        $calendarDays = $this->calendar->calendarDays($attempt);

        return $this->renderConsultation(
            pageState: 'booking_open',
            attempt: $attemptPayload,
            sessions: $sessions->map(fn (ConsultationSession $session) => $this->calendar->slotPayload($session))->values()->all(),
            calendarDays: $calendarDays,
            message: 'Select a Release Day appointment and confirm your reservation.',
        );
    }

    public function reserve(ReserveConsultationRequest $request, ReserveConsultationSlot $action): RedirectResponse
    {
        $validated = $request->validated();

        $attempt = AdmissionAttempt::query()->findOrFail((int) $validated['admission_attempt_id']);
        abort_unless(ApplicantScope::attemptBelongsToUser($attempt, $request->user()), 403);

        $session = ConsultationSession::query()->findOrFail((int) $validated['consultation_session_id']);

        try {
            $action->handle(
                $session,
                $attempt,
                $request->user(),
                $validated['idempotency_key'] ?? null,
            );
        } catch (RuntimeException $exception) {
            return back()->withErrors(['reserve' => $exception->getMessage()])->with('page_state', 'conflict');
        }

        return redirect()
            ->route('applicant.consultation')
            ->with('status', 'Release Day appointment reserved.');
    }

    public function switch(SwitchConsultationSlotRequest $request, ChangeApplicantConsultationSlot $action): RedirectResponse
    {
        $validated = $request->validated();

        $attempt = AdmissionAttempt::query()->findOrFail((int) $validated['admission_attempt_id']);
        abort_unless(ApplicantScope::attemptBelongsToUser($attempt, $request->user()), 403);

        $targetSession = ConsultationSession::query()->findOrFail((int) $validated['consultation_session_id']);

        try {
            $action->handle(
                $targetSession,
                $attempt,
                $request->user(),
                (int) $validated['expected_assignment_id'],
                (int) $validated['expected_assignment_version'],
                $validated['idempotency_key'],
            );
        } catch (RuntimeException $exception) {
            return back()->withErrors(['switch' => $exception->getMessage()]);
        }

        return redirect()
            ->route('applicant.consultation')
            ->with('status', 'Release Day appointment changed.');
    }

    /**
     * @param  array<string, mixed>|null  $attempt
     * @param  array<string, mixed>|null  $assignment
     * @param  list<array<string, mixed>>  $sessions
     * @param  list<array<string, mixed>>  $calendarDays
     */
    private function renderConsultation(
        string $pageState,
        ?array $attempt = null,
        ?array $assignment = null,
        array $sessions = [],
        array $calendarDays = [],
        ?array $slotSwitch = null,
        string $message = '',
    ): Response {

        return Inertia::render('Applicant/ReleaseSchedule', [
            'page_state' => $pageState,
            'attempt' => $attempt,
            'assignment' => $assignment,
            'sessions' => $sessions,
            'calendar_days' => $calendarDays,
            'slot_switch' => $slotSwitch,
            'message' => $message,
        ]);
    }
}
