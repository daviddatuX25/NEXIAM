<?php

namespace App\Http\Controllers\Examination;

use App\Authorization\CapabilityAuthorizer;
use App\Authorization\CapabilityCatalog;
use App\Examination\Actions\AssignApplicantToExamSession;
use App\Examination\Actions\CancelExamSession;
use App\Examination\Actions\CloseExamSession;
use App\Examination\Actions\CloseExamSessionReservation;
use App\Examination\Actions\CreateExamSession;
use App\Examination\Actions\ExtendExamSession;
use App\Examination\Actions\OpenExamSessionReservation;
use App\Examination\Actions\PublishExamSession;
use App\Examination\Actions\ReassignApplicantExamSession;
use App\Examination\Actions\RemoveApplicantFromExamSession;
use App\Examination\Actions\UnpublishExamSession;
use App\Examination\Actions\UpdateDraftExamSession;
use App\Examination\Queries\ExamSessionDetailQuery;
use App\Examination\Queries\ExamSessionListQuery;
use App\Examination\Support\EligibleProctorCandidates;
use App\Examination\Support\ExamSessionPresenter;
use App\Http\Controllers\Controller;
use App\Http\Requests\Examination\AssignExamRequest;
use App\Http\Requests\Examination\CancelExamSessionRequest;
use App\Http\Requests\Examination\CloseExamSessionRequest;
use App\Http\Requests\Examination\CloseReservationRequest;
use App\Http\Requests\Examination\CreateExamSessionRequest;
use App\Http\Requests\Examination\ExtendExamSessionRequest;
use App\Http\Requests\Examination\OpenReservationRequest;
use App\Http\Requests\Examination\PublishExamSessionRequest;
use App\Http\Requests\Examination\ReassignExamRequest;
use App\Http\Requests\Examination\RemoveAssignmentRequest;
use App\Http\Requests\Examination\UnpublishExamSessionRequest;
use App\Http\Requests\Examination\UpdateExamSessionRequest;
use App\Models\Admission\AdmissionAttempt;
use App\Models\Examination\ExamAssignment;
use App\Models\Examination\ExamSession;
use App\Models\Foundation\AcademicCycle;
use App\Models\Foundation\Room;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Inertia\Inertia;
use Inertia\Response;
use RuntimeException;

/**
 * Exam Scheduling surface: session plan CRUD, lifecycle transitions, roster
 * management, and applicant reservation. Each method is a thin delegate to a
 * domain Action; reads delegate to ExamSession queries. Conduct-phase views
 * (Exam Monitoring) live in ExamMonitoringController.
 */
class ExamSessionController extends Controller
{
    public function __construct(
        private readonly ExamSessionPresenter $presenter,
        private readonly ExamSessionListQuery $listQuery,
        private readonly ExamSessionDetailQuery $detailQuery,
    ) {
    }

    public function index(Request $request, CapabilityAuthorizer $authorizer): Response
    {
        if (! $authorizer->allowsAny($request->user(), CapabilityCatalog::examSessionManagementCapabilities())) {
            return Inertia::render('Examination/Index', [
                'page_state' => 'denied',
                'sessions' => [],
                'filters' => [],
                'statuses' => $this->presenter->statusOptions(),
                'cycles' => [],
            ]);
        }

        $payload = $this->listQuery->forScheduling($request);

        return Inertia::render('Examination/Index', [
            'page_state' => $payload['sessions']->isEmpty() ? 'empty' : 'ready',
            'sessions' => $payload['sessions'],
            'filters' => $payload['filters'],
            'statuses' => $this->presenter->statusOptions(),
            'cycles' => $this->presenter->cycleOptions(),
        ]);
    }

    public function create(CapabilityAuthorizer $authorizer, Request $request): Response|RedirectResponse
    {
        if (! $authorizer->allowsAny($request->user(), CapabilityCatalog::examSessionManagementCapabilities())) {
            abort(403);
        }

        return Inertia::render('Examination/Create', [
            'cycles' => $this->presenter->cycleOptions(),
            'rooms' => $this->presenter->roomOptions(),
            'default_cycle_id' => AcademicCycle::active()?->id,
        ]);
    }

    public function store(CreateExamSessionRequest $request, CreateExamSession $action): RedirectResponse
    {
        try {
            $session = $action->handle(
                AcademicCycle::query()->findOrFail($request->integer('academic_cycle_id')),
                Room::query()->findOrFail($request->integer('room_id')),
                $request->string('code')->toString(),
                $request->string('starts_at')->toString(),
                $request->string('effective_end_at')->toString(),
                $request->user(),
            );
        } catch (RuntimeException $exception) {
            return back()->withErrors(['schedule' => $exception->getMessage()])->with('page_state', 'conflict');
        }

        return redirect()
            ->route('exams.sessions.show', $session)
            ->with('status', 'Exam session created.');
    }

    public function show(
        Request $request,
        ExamSession $session,
        CapabilityAuthorizer $authorizer,
        EligibleProctorCandidates $eligibleProctors,
    ): Response
    {
        if (! $authorizer->allowsAny($request->user(), CapabilityCatalog::examSessionManagementCapabilities())) {
            abort(403);
        }

        $payload = $this->detailQuery->forShow($session);
        $canAssignProctor = $authorizer->allows($request->user(), CapabilityCatalog::GUIDANCE_PROCTOR_ASSIGN);

        return Inertia::render('Examination/Show', [
            'session' => $payload['session'],
            'assigned_applicants' => $payload['assigned'],
            'available_applicants' => $payload['available'],
            'reassign_targets' => $payload['reassign_targets'],
            'publish_block_reason' => $payload['publish_block_reason'],
            'is_publishable' => $payload['publish_block_reason'] === null && $session->status === ExamSession::STATUS_DRAFT,
            'can_assign_proctor' => $canAssignProctor,
            'eligible_proctors' => $canAssignProctor ? $eligibleProctors->forSession($session) : [],
        ]);
    }

    public function edit(Request $request, ExamSession $session, CapabilityAuthorizer $authorizer): Response|RedirectResponse
    {
        if (! $authorizer->allowsAny($request->user(), CapabilityCatalog::examSessionManagementCapabilities())) {
            abort(403);
        }

        if ($session->status !== ExamSession::STATUS_DRAFT) {
            return redirect()
                ->route('exams.sessions.show', $session)
                ->withErrors(['edit' => 'Only draft sessions may be edited.']);
        }

        $session->load(['room', 'academicCycle']);

        return Inertia::render('Examination/Edit', [
            'session' => $this->presenter->detailPayload($session),
            'cycles' => $this->presenter->cycleOptions(),
            'rooms' => $this->presenter->roomOptions(),
        ]);
    }

    public function update(
        UpdateExamSessionRequest $request,
        ExamSession $session,
        UpdateDraftExamSession $action,
    ): RedirectResponse {
        try {
            $session = $action->handle(
                $session,
                AcademicCycle::query()->findOrFail($request->integer('academic_cycle_id')),
                Room::query()->findOrFail($request->integer('room_id')),
                $request->string('code')->toString(),
                $request->string('starts_at')->toString(),
                $request->string('effective_end_at')->toString(),
                $request->user(),
                $request->integer('expected_version'),
            );
        } catch (RuntimeException $exception) {
            return back()->withErrors(['schedule' => $exception->getMessage()])->with('page_state', 'conflict');
        }

        return redirect()
            ->route('exams.sessions.show', $session)
            ->with('status', 'Exam session updated.');
    }

    public function assign(AssignExamRequest $request, ExamSession $session, AssignApplicantToExamSession $action): RedirectResponse
    {
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

    public function remove(
        RemoveAssignmentRequest $request,
        ExamSession $session,
        ExamAssignment $assignment,
        RemoveApplicantFromExamSession $action,
    ): RedirectResponse {
        try {
            $action->handle($session, $assignment, $request->user(), $request->validated()['reason'] ?? null);
        } catch (RuntimeException $exception) {
            return back()->withErrors(['remove' => $exception->getMessage()])->with('page_state', 'conflict');
        }

        return back()->with('status', 'Applicant removed.');
    }

    public function publish(PublishExamSessionRequest $request, ExamSession $session, PublishExamSession $action): RedirectResponse
    {
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

    public function close(CloseExamSessionRequest $request, ExamSession $session, CloseExamSession $action): RedirectResponse
    {
        try {
            $action->handle($session, $request->user(), $request->integer('expected_version'));
        } catch (RuntimeException $exception) {
            return back()->withErrors(['close' => $exception->getMessage()])->with('page_state', 'conflict');
        }

        return back()->with('status', 'Session closed; grading opened.');
    }

    public function unpublish(UnpublishExamSessionRequest $request, ExamSession $session, UnpublishExamSession $action): RedirectResponse
    {
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

    public function cancel(CancelExamSessionRequest $request, ExamSession $session, CancelExamSession $action): RedirectResponse
    {
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

    public function extend(ExtendExamSessionRequest $request, ExamSession $session, ExtendExamSession $action): RedirectResponse
    {
        try {
            $action->handle(
                $session,
                $request->user(),
                $request->integer('expected_version'),
                Carbon::parse($request->string('effective_end_at')),
            );
        } catch (RuntimeException $exception) {
            return back()->withErrors(['extend' => $exception->getMessage()])->with('page_state', 'conflict');
        }

        return back()->with('status', 'Session deadline extended.');
    }

    public function reassign(
        ReassignExamRequest $request,
        ExamSession $session,
        ReassignApplicantExamSession $action,
    ): RedirectResponse {
        $target = ExamSession::query()->findOrFail((int) $request->validated()['target_session_id']);
        $moved = 0;

        try {
            foreach ($request->validated()['assignment_ids'] as $assignmentId) {
                $assignment = ExamAssignment::query()->findOrFail((int) $assignmentId);
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

    public function openReservation(
        OpenReservationRequest $request,
        ExamSession $session,
        OpenExamSessionReservation $action,
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
        CloseReservationRequest $request,
        ExamSession $session,
        CloseExamSessionReservation $action,
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
