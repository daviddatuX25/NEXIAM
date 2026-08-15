<?php

namespace App\Http\Controllers\Applicant;

use App\Admission\Support\ApplicantScope;
use App\Consultation\Actions\ReserveConsultationSlot;
use App\Consultation\Queries\ApplicantConsultationCalendar;
use App\Consultation\Support\AttemptConsultationStatus;
use App\Consultation\Support\AttemptResultReadiness;
use App\Http\Controllers\Controller;
use App\Http\Requests\Consultation\ReserveConsultationRequest;
use App\Models\Admission\AdmissionAttempt;
use App\Models\Consultation\ConsultationAssignment;
use App\Models\Consultation\ConsultationSession;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use RuntimeException;

/**
 * Applicant consultation page: result-ready gate, open sessions + capacity,
 * own assignment, FCFS reservation through the shared ReservationGuard.
 */
class ApplicantConsultationController extends Controller
{
    public function __construct(private readonly ApplicantConsultationCalendar $calendar) {}

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
            'advised' => AttemptConsultationStatus::isAdvised($attempt),
        ];

        if (! AttemptResultReadiness::isReady($attempt)) {
            return $this->renderConsultation(
                pageState: 'not_result_ready',
                attempt: $attemptPayload,
                message: 'Consultation unlocks after your result is ready.',
            );
        }

        $assignment = ConsultationAssignment::query()
            ->active()
            ->with(['consultationSession.room:id,code,name'])
            ->where('admission_attempt_id', $attempt->id)
            ->first();

        if ($assignment !== null) {
            $session = $assignment->consultationSession;
            $published = in_array($session?->status, [
                ConsultationSession::STATUS_PUBLISHED,
                ConsultationSession::STATUS_IN_PROGRESS,
                ConsultationSession::STATUS_COMPLETED,
            ], true);

            return $this->renderConsultation(
                pageState: 'assigned_or_reserved',
                attempt: $attemptPayload,
                assignment: [
                    'id' => $assignment->id,
                    'source' => $assignment->source,
                    'session' => $session ? [
                        'code' => $session->code,
                        'status' => $session->status,
                        'scheduled_at' => $session->scheduled_at?->toIso8601String(),
                        'room' => $session->room?->name ?: $session->room?->code,
                        'published' => $published,
                    ] : null,
                ],
                message: $published
                    ? 'Your consultation is confirmed.'
                    : 'You are scheduled for consultation. Staff will publish the schedule when ready.',
            );
        }

        $sessions = $this->calendar->openSessions($attempt);

        if ($sessions->isEmpty()) {
            return $this->renderConsultation(
                pageState: 'no_open_sessions',
                attempt: $attemptPayload,
                message: 'No consultation session is open for booking yet. Check back later.',
            );
        }

        return $this->renderConsultation(
            pageState: 'booking_open',
            attempt: $attemptPayload,
            sessions: $sessions->map(fn (ConsultationSession $session) => $this->calendar->slotPayload($session))->values()->all(),
            message: 'Select a consultation session and confirm your reservation.',
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
            ->with('status', 'Consultation slot reserved.');
    }

    /**
     * @param  array<string, mixed>|null  $attempt
     * @param  array<string, mixed>|null  $assignment
     * @param  list<array<string, mixed>>  $sessions
     */
    private function renderConsultation(
        string $pageState,
        ?array $attempt = null,
        ?array $assignment = null,
        array $sessions = [],
        string $message = '',
    ): Response {
        return Inertia::render('Applicant/Consultation', [
            'page_state' => $pageState,
            'attempt' => $attempt,
            'assignment' => $assignment,
            'sessions' => $sessions,
            'message' => $message,
        ]);
    }
}
