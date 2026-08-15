<?php

namespace App\Http\Controllers\Consultation;

use App\Authorization\CapabilityAuthorizer;
use App\Authorization\CapabilityCatalog;
use App\Consultation\Actions\AssignApplicantToConsultationSession;
use App\Consultation\Actions\CancelConsultationAssignment;
use App\Consultation\Actions\CancelConsultationSession;
use App\Consultation\Actions\CloseConsultationSessionReservation;
use App\Consultation\Actions\CompleteConsultationSession;
use App\Consultation\Actions\CreateConsultationSession;
use App\Consultation\Actions\OpenConsultationSessionReservation;
use App\Consultation\Actions\PublishConsultationSession;
use App\Consultation\Actions\ReassignApplicantConsultationSession;
use App\Consultation\Actions\UnpublishConsultationSession;
use App\Consultation\Actions\UpdateDraftConsultationSession;
use App\Consultation\Queries\ConsultationSessionDetailQuery;
use App\Consultation\Queries\ConsultationSessionListQuery;
use App\Consultation\Support\ConsultationSessionPresenter;
use App\Http\Controllers\Controller;
use App\Http\Requests\Consultation\AssignConsultationRequest;
use App\Http\Requests\Consultation\CancelConsultationAssignmentRequest;
use App\Http\Requests\Consultation\CancelConsultationSessionRequest;
use App\Http\Requests\Consultation\CloseConsultationReservationRequest;
use App\Http\Requests\Consultation\CompleteConsultationSessionRequest;
use App\Http\Requests\Consultation\CreateConsultationSessionRequest;
use App\Http\Requests\Consultation\OpenConsultationReservationRequest;
use App\Http\Requests\Consultation\PublishConsultationSessionRequest;
use App\Http\Requests\Consultation\ReassignConsultationRequest;
use App\Http\Requests\Consultation\UnpublishConsultationSessionRequest;
use App\Http\Requests\Consultation\UpdateConsultationSessionRequest;
use App\Models\Admission\AdmissionAttempt;
use App\Models\Consultation\ConsultationAssignment;
use App\Models\Consultation\ConsultationSession;
use App\Models\Foundation\AcademicCycle;
use App\Models\Foundation\Room;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use RuntimeException;

/**
 * Consultation scheduling surface: session CRUD, lifecycle transitions, roster
 * management, and the booking toggle. Each method delegates to a domain
 * Action; reads delegate to consultation queries. The terminal conduct step
 * (decision recording) stays on ReleaseController::consultation.
 */
class ConsultationSessionController extends Controller
{
    public function __construct(
        private readonly ConsultationSessionPresenter $presenter,
        private readonly ConsultationSessionListQuery $listQuery,
        private readonly ConsultationSessionDetailQuery $detailQuery,
    ) {}

    public function index(Request $request, CapabilityAuthorizer $authorizer): Response
    {
        if (! $authorizer->allows($request->user(), CapabilityCatalog::GUIDANCE_CONSULTATION_PLAN)) {
            return Inertia::render('Consultation/ConsultationSessions', [
                'page_state' => 'denied',
                'sessions' => [],
                'filters' => [],
                'statuses' => $this->presenter->statusOptions(),
                'cycles' => [],
                'booking_modes' => $this->presenter->bookingModeOptions(),
            ]);
        }

        $payload = $this->listQuery->forScheduling($request);

        return Inertia::render('Consultation/ConsultationSessions', [
            'page_state' => $payload['sessions']->isEmpty() ? 'empty' : 'ready',
            'sessions' => $payload['sessions'],
            'filters' => $payload['filters'],
            'statuses' => $this->presenter->statusOptions(),
            'cycles' => $this->presenter->cycleOptions(),
            'booking_modes' => $this->presenter->bookingModeOptions(),
        ]);
    }

    public function create(Request $request, CapabilityAuthorizer $authorizer): Response
    {
        if (! $authorizer->allows($request->user(), CapabilityCatalog::GUIDANCE_CONSULTATION_PLAN)) {
            abort(403);
        }

        return Inertia::render('Consultation/ConsultationSessionCreate', [
            'cycles' => $this->presenter->cycleOptions(),
            'rooms' => $this->presenter->roomOptions(),
            'booking_modes' => $this->presenter->bookingModeOptions(),
            'default_cycle_id' => AcademicCycle::active()?->id,
        ]);
    }

    public function store(CreateConsultationSessionRequest $request, CreateConsultationSession $action): RedirectResponse
    {
        try {
            $session = $action->handle(
                AcademicCycle::query()->findOrFail($request->integer('academic_cycle_id')),
                Room::query()->findOrFail($request->integer('room_id')),
                $request->string('code')->toString(),
                $request->string('scheduled_at')->toString(),
                $request->string('booking_mode')->toString(),
                $request->user(),
            );
        } catch (RuntimeException $exception) {
            return back()->withErrors(['schedule' => $exception->getMessage()])->with('page_state', 'conflict');
        }

        return redirect()
            ->route('consultation.sessions.show', $session)
            ->with('status', 'Consultation session created.');
    }

    public function show(
        Request $request,
        ConsultationSession $session,
        CapabilityAuthorizer $authorizer,
    ): Response {
        if (! $authorizer->allowsAny($request->user(), [
            CapabilityCatalog::GUIDANCE_CONSULTATION_PLAN,
            CapabilityCatalog::GUIDANCE_CONSULTATION_DECIDE,
        ])) {
            abort(403);
        }

        $payload = $this->detailQuery->forShow($session);
        $canPlan = $authorizer->allows($request->user(), CapabilityCatalog::GUIDANCE_CONSULTATION_PLAN);

        return Inertia::render('Consultation/ConsultationSessionShow', [
            'session' => $payload['session'],
            'assigned_applicants' => $payload['assigned'],
            'ready_pool' => $payload['ready_pool'],
            'reassign_targets' => $payload['reassign_targets'],
            'publish_block_reason' => $payload['publish_block_reason'],
            'is_publishable' => $payload['publish_block_reason'] === null && $session->status === ConsultationSession::STATUS_DRAFT,
            'is_completable' => in_array($session->status, [ConsultationSession::STATUS_PUBLISHED, ConsultationSession::STATUS_IN_PROGRESS], true),
            'can_plan' => $canPlan,
            'can_decide' => $authorizer->allows($request->user(), CapabilityCatalog::GUIDANCE_CONSULTATION_DECIDE),
        ]);
    }

    public function edit(
        Request $request,
        ConsultationSession $session,
        CapabilityAuthorizer $authorizer,
    ): Response|RedirectResponse {
        if (! $authorizer->allows($request->user(), CapabilityCatalog::GUIDANCE_CONSULTATION_PLAN)) {
            abort(403);
        }

        if ($session->status !== ConsultationSession::STATUS_DRAFT) {
            return redirect()
                ->route('consultation.sessions.show', $session)
                ->withErrors(['edit' => 'Only draft sessions may be edited.']);
        }

        $session->load(['room', 'academicCycle']);

        return Inertia::render('Consultation/ConsultationSessionEdit', [
            'session' => $this->presenter->detailPayload($session),
            'cycles' => $this->presenter->cycleOptions(),
            'rooms' => $this->presenter->roomOptions(),
            'booking_modes' => $this->presenter->bookingModeOptions(),
        ]);
    }

    public function update(
        UpdateConsultationSessionRequest $request,
        ConsultationSession $session,
        UpdateDraftConsultationSession $action,
    ): RedirectResponse {
        try {
            $session = $action->handle(
                $session,
                AcademicCycle::query()->findOrFail($request->integer('academic_cycle_id')),
                Room::query()->findOrFail($request->integer('room_id')),
                $request->string('code')->toString(),
                $request->string('scheduled_at')->toString(),
                $request->string('booking_mode')->toString(),
                $request->user(),
                $request->integer('expected_version'),
            );
        } catch (RuntimeException $exception) {
            return back()->withErrors(['schedule' => $exception->getMessage()])->with('page_state', 'conflict');
        }

        return redirect()
            ->route('consultation.sessions.show', $session)
            ->with('status', 'Consultation session updated.');
    }

    public function assign(
        AssignConsultationRequest $request,
        ConsultationSession $session,
        AssignApplicantToConsultationSession $action,
    ): RedirectResponse {
        try {
            $action->handle(
                $session,
                AdmissionAttempt::query()->findOrFail($request->integer('admission_attempt_id')),
                $request->user(),
            );
        } catch (RuntimeException $exception) {
            return back()->withErrors(['assign' => $exception->getMessage()])->with('page_state', 'conflict');
        }

        return back()->with('status', 'Applicant assigned.');
    }

    public function cancelAssignment(
        CancelConsultationAssignmentRequest $request,
        ConsultationSession $session,
        ConsultationAssignment $assignment,
        CancelConsultationAssignment $action,
    ): RedirectResponse {
        try {
            $action->handle($session, $assignment, $request->user(), $request->validated()['reason'] ?? null);
        } catch (RuntimeException $exception) {
            return back()->withErrors(['cancel_assignment' => $exception->getMessage()])->with('page_state', 'conflict');
        }

        return back()->with('status', 'Applicant cancelled from the session.');
    }

    public function reassign(
        ReassignConsultationRequest $request,
        ConsultationSession $session,
        ReassignApplicantConsultationSession $action,
    ): RedirectResponse {
        $target = ConsultationSession::query()->findOrFail((int) $request->validated()['target_session_id']);
        $moved = 0;

        try {
            foreach ($request->validated()['assignment_ids'] as $assignmentId) {
                $assignment = ConsultationAssignment::query()->findOrFail((int) $assignmentId);
                $action->handle(
                    $session,
                    $assignment,
                    $target,
                    $request->user(),
                    $request->validated()['reason'] ?? null,
                );
                $moved++;
            }
        } catch (RuntimeException $exception) {
            return back()->withErrors(['reassign' => $exception->getMessage()])->with('page_state', 'conflict');
        }

        return back()->with('status', $moved === 1
            ? 'Applicant reassigned.'
            : "{$moved} applicants reassigned.");
    }

    public function publish(
        PublishConsultationSessionRequest $request,
        ConsultationSession $session,
        PublishConsultationSession $action,
    ): RedirectResponse {
        $block = $this->presenter->publishBlockReason($session->loadCount([
            'assignments as assigned_count' => fn ($q) => $q->active(),
        ])->load('room'));

        if ($block !== null) {
            return back()->withErrors(['publish' => $block])->with('page_state', 'conflict');
        }

        try {
            $action->handle($session, $request->user(), $request->integer('expected_version'));
        } catch (RuntimeException $exception) {
            return back()->withErrors(['publish' => $exception->getMessage()])->with('page_state', 'conflict');
        }

        return back()->with('status', 'Session published.');
    }

    public function unpublish(
        UnpublishConsultationSessionRequest $request,
        ConsultationSession $session,
        UnpublishConsultationSession $action,
    ): RedirectResponse {
        try {
            $action->handle(
                $session,
                $request->user(),
                $request->integer('expected_version'),
                $request->string('reason')->toString(),
            );
        } catch (RuntimeException $exception) {
            return back()->withErrors(['unpublish' => $exception->getMessage()])->with('page_state', 'conflict');
        }

        return back()->with('status', 'Session unpublished to draft.');
    }

    public function complete(
        CompleteConsultationSessionRequest $request,
        ConsultationSession $session,
        CompleteConsultationSession $action,
    ): RedirectResponse {
        try {
            $action->handle($session, $request->user(), $request->integer('expected_version'));
        } catch (RuntimeException $exception) {
            return back()->withErrors(['complete' => $exception->getMessage()])->with('page_state', 'conflict');
        }

        return back()->with('status', 'Session completed.');
    }

    public function cancel(
        CancelConsultationSessionRequest $request,
        ConsultationSession $session,
        CancelConsultationSession $action,
    ): RedirectResponse {
        try {
            $action->handle(
                $session,
                $request->user(),
                $request->integer('expected_version'),
                $request->string('reason')->toString(),
            );
        } catch (RuntimeException $exception) {
            return back()->withErrors(['cancel' => $exception->getMessage()])->with('page_state', 'conflict');
        }

        return back()->with('status', 'Session cancelled.');
    }

    public function openReservation(
        OpenConsultationReservationRequest $request,
        ConsultationSession $session,
        OpenConsultationSessionReservation $action,
    ): RedirectResponse {
        try {
            $action->handle(
                $session,
                $request->user(),
                $request->integer('expected_version'),
                $request->validated()['reason'] ?? null,
            );
        } catch (RuntimeException $exception) {
            return back()->withErrors(['open_reservation' => $exception->getMessage()])->with('page_state', 'conflict');
        }

        return back()->with('status', 'Session opened for applicant reservation.');
    }

    public function closeReservation(
        CloseConsultationReservationRequest $request,
        ConsultationSession $session,
        CloseConsultationSessionReservation $action,
    ): RedirectResponse {
        try {
            $action->handle(
                $session,
                $request->user(),
                $request->integer('expected_version'),
                $request->validated()['reason'] ?? null,
            );
        } catch (RuntimeException $exception) {
            return back()->withErrors(['close_reservation' => $exception->getMessage()])->with('page_state', 'conflict');
        }

        return back()->with('status', 'Applicant reservation closed.');
    }
}
