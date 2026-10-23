<?php

namespace App\Http\Controllers\Consultation;

use App\Authorization\CapabilityAuthorizer;
use App\Authorization\CapabilityCatalog;
use App\Consultation\Actions\AssignApplicantToConsultationSession;
use App\Consultation\Actions\BulkAssignApplicantsToConsultationSession;
use App\Consultation\Actions\CancelConsultationAssignment;
use App\Consultation\Actions\CancelConsultationSession;
use App\Consultation\Actions\CloseConsultationSessionReservation;
use App\Consultation\Actions\CompleteConsultationSession;
use App\Consultation\Actions\CreateConsultationSession;
use App\Consultation\Actions\OpenConsultationSessionReservation;
use App\Consultation\Actions\PublishConsultationSession;
use App\Consultation\Actions\ReassignApplicantConsultationSession;
use App\Consultation\Actions\StartConsultationSession;
use App\Consultation\Actions\UnpublishConsultationSession;
use App\Consultation\Actions\UpdateDraftConsultationSession;
use App\Consultation\Queries\ConsultationSessionDetailQuery;
use App\Consultation\Queries\ConsultationSessionListQuery;
use App\Consultation\Support\ConsultationSessionPresenter;
use App\Http\Controllers\Controller;
use App\Http\Requests\Consultation\AssignConsultationRequest;
use App\Http\Requests\Consultation\BulkConsultationSessionActionRequest;
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
use App\Http\Requests\Scheduling\CommitCohortDistributionRequest;
use App\Http\Requests\Scheduling\PreviewCohortDistributionRequest;
use App\Models\Admission\AdmissionAttempt;
use App\Models\Consultation\ConsultationAssignment;
use App\Models\Consultation\ConsultationSession;
use App\Models\Foundation\AcademicCycle;
use App\Models\Foundation\Institution;
use App\Models\Foundation\Program;
use App\Models\Foundation\Room;
use App\Scheduling\Actions\CommitCohortDistribution;
use App\Scheduling\Actions\PreviewCohortDistribution;
use App\Scheduling\AssignmentInvariant;
use App\Scheduling\AssignmentPlanningMutex;
use App\Scheduling\CandidatePoolQuery;
use App\Scheduling\Exceptions\StalePlanConflictException;
use App\Scheduling\Queries\SchedulingWaitlistQuery;
use App\Scheduling\Queries\SessionCodeSuggestions;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\JsonResponse;
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
    private readonly CapabilityAuthorizer $authorizer;

    private readonly AssignmentPlanningMutex $planningMutex;

    public function __construct(
        private readonly ConsultationSessionPresenter $presenter,
        private readonly ConsultationSessionListQuery $listQuery,
        private readonly ConsultationSessionDetailQuery $detailQuery,
        ?CapabilityAuthorizer $authorizer = null,
        ?AssignmentPlanningMutex $planningMutex = null,
    ) {
        $this->authorizer = $authorizer ?? app(CapabilityAuthorizer::class);
        $this->planningMutex = $planningMutex ?? app(AssignmentPlanningMutex::class);
    }

    public function index(Request $request, CapabilityAuthorizer $authorizer): Response
    {
        $institutionId = $this->currentInstitutionId($request);
        if (! $authorizer->allows($request->user(), CapabilityCatalog::GUIDANCE_CONSULTATION_PLAN, [
            'institution_id' => $institutionId,
            'office' => 'guidance',
        ])) {
            return Inertia::render('Consultation/ConsultationSessions', [
                'page_state' => 'denied',
                'sessions' => [],
                'filters' => [],
                'statuses' => $this->presenter->statusOptions(),
                'cycles' => [],
                'booking_modes' => $this->presenter->bookingModeOptions(),
                'waitlist_counts' => ['ready' => 0, 'not_ready' => 0],
                'sort_options' => [],
                'programs' => [],
                'can_manage' => false,
            ]);
        }

        $payload = $this->listQuery->forScheduling($request);

        $activeCycleId = $payload['filters']['academic_cycle_id'] ?? AcademicCycle::active()?->id;
        $distribution = [
            'pool_count' => $activeCycleId ? app(CandidatePoolQuery::class)->forConsultation((int) $activeCycleId, $institutionId)->count() : 0,
            'eligible_capacity' => $activeCycleId ? (int) ConsultationSession::query()->whereHas('academicCycle', fn ($q) => $q->where('institution_id', $institutionId))->distributionEligible((int) $activeCycleId)->with('room')->get()->sum(fn ($s) => app(AssignmentInvariant::class)->remainingCapacity($s)) : 0,
            'eligible_sessions_count' => $activeCycleId ? ConsultationSession::query()->whereHas('academicCycle', fn ($q) => $q->where('institution_id', $institutionId))->distributionEligible((int) $activeCycleId)->count() : 0,
            'active_cycle_id' => $activeCycleId ? (int) $activeCycleId : null,
        ];

        return Inertia::render('Consultation/ConsultationSessions', [
            'page_state' => $payload['sessions']->isEmpty() ? 'empty' : 'ready',
            'sessions' => $payload['sessions'],
            'filters' => $payload['filters'],
            'statuses' => $this->presenter->statusOptions(),
            'cycles' => $this->presenter->cycleOptions(),
            'booking_modes' => $this->presenter->bookingModeOptions(),
            'distribution' => $distribution,
            'waitlist_counts' => app(SchedulingWaitlistQuery::class)->countsForDomain(
                'consultation',
                $institutionId,
                $activeCycleId ? (int) $activeCycleId : null,
            ),
            'sort_options' => [
                ['value' => 'scheduled_at', 'label' => 'Scheduled Time'],
                ['value' => 'code', 'label' => 'Session Code'],
                ['value' => 'status', 'label' => 'Status'],
                ['value' => 'created_at', 'label' => 'Date Created'],
            ],
            'programs' => Program::query()
                ->where('institution_id', $institutionId)
                ->where('is_active', true)
                ->orderBy('name')
                ->get(['id', 'code', 'name'])
                ->map(fn ($program) => ['id' => $program->id, 'code' => $program->code, 'name' => $program->name])
                ->all(),
            'can_manage' => true,
        ]);
    }

    public function waitlist(Request $request): Response
    {
        $institutionId = (int) ($request->user()?->actorScope()?->institutionId ?? Institution::query()->value('id') ?? 1);
        $cycleId = $request->query('academic_cycle_id');
        $activeCycleId = is_numeric($cycleId)
            ? AcademicCycle::query()
                ->whereKey((int) $cycleId)
                ->where('institution_id', $institutionId)
                ->value('id')
            : AcademicCycle::query()
                ->where('institution_id', $institutionId)
                ->where('is_active', true)
                ->value('id');
        $activeCycleId = $activeCycleId ? (int) $activeCycleId : null;
        $waitlistData = app(SchedulingWaitlistQuery::class)->forDomain(
            'consultation',
            $institutionId,
            $activeCycleId,
            $request->all(),
            null,
            true,
        );
        $sessions = $activeCycleId === null ? [] : ConsultationSession::query()
            ->whereHas('academicCycle', fn ($query) => $query->where('institution_id', $institutionId))
            ->where('academic_cycle_id', $activeCycleId)
            ->openForReservation()
            ->whereIn('status', [ConsultationSession::STATUS_PUBLISHED, ConsultationSession::STATUS_IN_PROGRESS])
            ->withCount(['assignments as assigned_count' => fn ($query) => $query->active()->whereHas('admissionAttempt', fn ($attemptQuery) => $attemptQuery->forWorkspace())])
            ->orderBy('scheduled_at')
            ->get(['id', 'code', 'status', 'booking_mode', 'academic_cycle_id', 'scheduled_at', 'room_id'])
            ->map(fn (ConsultationSession $session) => [
                'id' => (int) $session->id,
                'code' => $session->code,
                'status' => $session->status,
                'booking_mode' => $session->booking_mode,
                'academic_cycle_id' => (int) $session->academic_cycle_id,
                'scheduled_at' => $session->scheduled_at?->toIso8601String(),
                'assigned_count' => (int) $session->assigned_count,
                'room' => $session->room ? [
                    'id' => (int) $session->room->id,
                    'code' => $session->room->code,
                    'name' => $session->room->name,
                    'capacity' => (int) $session->room->capacity,
                ] : null,
            ])
            ->all();

        return Inertia::render('Consultation/Waitlist', [
            'waitlist' => $waitlistData,
            'programs' => Program::query()
                ->where('institution_id', $institutionId)
                ->where('is_active', true)
                ->orderBy('name')
                ->get(['id', 'code', 'name'])
                ->map(fn (Program $program) => ['id' => (int) $program->id, 'code' => $program->code, 'name' => $program->name])
                ->all(),
            'sessions' => $sessions,
            'cycles' => $this->presenter->cycleOptions(),
            'active_cycle_id' => $activeCycleId,
            'can_manage' => true,
            'breadcrumbParent' => ['label' => 'Session Scheduling', 'href' => route('consultation.sessions.index')],
        ]);
    }

    public function create(Request $request, CapabilityAuthorizer $authorizer, SessionCodeSuggestions $codeSuggestions): Response
    {
        $institutionId = $this->requireCurrentInstitution($request);
        if (! $authorizer->allows($request->user(), CapabilityCatalog::GUIDANCE_CONSULTATION_PLAN, [
            'institution_id' => $institutionId,
            'office' => 'guidance',
        ])) {
            abort(403);
        }

        $options = $this->sessionOptionsForInstitution($institutionId);

        return Inertia::render('Consultation/ConsultationSessionCreate', [
            'session_code_suggestions' => $codeSuggestions->forCycles('release', array_map('intval', array_column($options['cycles'], 'id'))),
            'cycles' => $options['cycles'],
            'rooms' => $options['rooms'],
            'booking_modes' => $this->presenter->bookingModeOptions(),
            'default_cycle_id' => AcademicCycle::query()->where('institution_id', $institutionId)->where('is_active', true)->value('id'),
        ]);
    }

    public function store(CreateConsultationSessionRequest $request, CreateConsultationSession $action): RedirectResponse
    {
        $institutionId = $this->currentInstitutionId($request);
        abort_unless($this->authorizer->allows($request->user(), CapabilityCatalog::GUIDANCE_CONSULTATION_PLAN, [
            'institution_id' => $institutionId,
            'office' => 'guidance',
        ]), 403);

        $cycle = AcademicCycle::query()->findOrFail($request->integer('academic_cycle_id'));
        $room = Room::query()->findOrFail($request->integer('room_id'));
        abort_unless((int) $cycle->institution_id === $institutionId && (int) $room->institution_id === $institutionId, 404);

        try {
            $session = $action->handle(
                $cycle,
                $room,
                $request->string('code')->toString(),
                $request->string('scheduled_at')->toString(),
                $request->string('booking_mode')->toString(),
                $request->user(),
                autoAssignEnabled: $request->boolean('auto_assign_enabled'),
            );
        } catch (RuntimeException $exception) {
            return back()->withErrors(['schedule' => $exception->getMessage()])->with('page_state', 'conflict');
        }

        return redirect()
            ->route('consultation.sessions.show', $session)
            ->with('status', 'Release Day session created.');
    }

    public function show(
        Request $request,
        ConsultationSession $session,
        CapabilityAuthorizer $authorizer,
    ): Response {
        $institutionId = $this->currentInstitutionId($request);
        $sessionInstitutionId = $this->requireCurrentInstitution($request, $session);
        $scope = ['institution_id' => $sessionInstitutionId, 'office' => 'guidance'];
        if (! $authorizer->allowsAny($request->user(), [
            CapabilityCatalog::GUIDANCE_CONSULTATION_PLAN,
            CapabilityCatalog::GUIDANCE_CONSULTATION_DECIDE,
        ], $scope)) {
            abort(403);
        }

        $detail = $this->detailQuery->query($session->id, $institutionId);
        $payload = ($detail['forInertia'])();
        $canPlan = $authorizer->allows($request->user(), CapabilityCatalog::GUIDANCE_CONSULTATION_PLAN, $scope);

        return Inertia::render('Consultation/ConsultationSessionShow', [
            'session' => $payload['session'],
            'assigned_applicants' => $payload['assigned'],
            'ready_pool' => $payload['ready_pool'],
            'reassign_targets' => $payload['reassign_targets'],
            'publish_block_reason' => $payload['publish_block_reason'],
            'is_publishable' => $payload['publish_block_reason'] === null && $session->status === ConsultationSession::STATUS_DRAFT,
            'is_completable' => in_array($session->status, [ConsultationSession::STATUS_PUBLISHED, ConsultationSession::STATUS_IN_PROGRESS], true),
            'can_plan' => $canPlan,
            'can_decide' => $authorizer->allows($request->user(), CapabilityCatalog::GUIDANCE_CONSULTATION_DECIDE, $scope),
            'focus_assignment_id' => $request->query('focus_assignment_id'),
        ]);
    }

    public function edit(
        Request $request,
        ConsultationSession $session,
        CapabilityAuthorizer $authorizer,
    ): Response|RedirectResponse {
        $sessionInstitutionId = $this->requireCurrentInstitution($request, $session);
        if (! $authorizer->allows($request->user(), CapabilityCatalog::GUIDANCE_CONSULTATION_PLAN, [
            'institution_id' => $sessionInstitutionId,
            'office' => 'guidance',
        ])) {
            abort(403);
        }

        if ($session->status !== ConsultationSession::STATUS_DRAFT) {
            return redirect()
                ->route('consultation.sessions.show', $session)
                ->withErrors(['edit' => 'Only draft sessions may be edited.']);
        }

        $options = $this->sessionOptionsForInstitution($sessionInstitutionId);

        return Inertia::render('Consultation/ConsultationSessionEdit', [
            'session' => $this->presenter->detailPayload($session),
            'cycles' => $options['cycles'],
            'rooms' => $options['rooms'],
            'booking_modes' => $this->presenter->bookingModeOptions(),
        ]);
    }

    public function update(
        UpdateConsultationSessionRequest $request,
        ConsultationSession $session,
        UpdateDraftConsultationSession $action,
    ): RedirectResponse {
        $sessionInstitutionId = $this->requireCurrentInstitution($request, $session);
        abort_unless($this->authorizer->allows($request->user(), CapabilityCatalog::GUIDANCE_CONSULTATION_PLAN, [
            'institution_id' => $sessionInstitutionId,
            'office' => 'guidance',
        ]), 403);

        $cycle = AcademicCycle::query()->findOrFail($request->integer('academic_cycle_id'));
        $room = Room::query()->findOrFail($request->integer('room_id'));
        abort_unless((int) $cycle->institution_id === $sessionInstitutionId && (int) $room->institution_id === $sessionInstitutionId, 404);

        try {
            $session = $action->handle(
                $session,
                $cycle,
                $room,
                $request->string('code')->toString(),
                $request->string('scheduled_at')->toString(),
                $request->string('booking_mode')->toString(),
                $request->user(),
                $request->integer('expected_version'),
                autoAssignEnabled: $request->has('auto_assign_enabled') ? $request->boolean('auto_assign_enabled') : null,
            );
        } catch (RuntimeException $exception) {
            return back()->withErrors(['schedule' => $exception->getMessage()])->with('page_state', 'conflict');
        }

        return redirect()
            ->route('consultation.sessions.show', $session)
            ->with('status', 'Release Day session updated.');
    }

    public function assign(
        AssignConsultationRequest $request,
        ConsultationSession $session,
        AssignApplicantToConsultationSession $action,
    ): RedirectResponse {
        $this->requireCurrentInstitution($request, $session);
        try {
            $action->handle(
                $session,
                AdmissionAttempt::query()->findOrFail($request->integer('admission_attempt_id')),
                $request->user(),
            );
        } catch (RuntimeException $exception) {
            return back()->withErrors(['assign' => $exception->getMessage()])->with('page_state', 'conflict');
        }

        return back()->with('status', 'Applicant assigned to Release Day session.');
    }

    public function bulkAssign(
        Request $request,
        ConsultationSession $session,
        BulkAssignApplicantsToConsultationSession $action,
    ): RedirectResponse {
        $sessionInstitutionId = $this->requireCurrentInstitution($request, $session);
        if (! $this->authorizer->allows($request->user(), CapabilityCatalog::GUIDANCE_CONSULTATION_PLAN, [
            'institution_id' => $sessionInstitutionId,
            'office' => 'guidance',
        ])) {
            abort(403);
        }
        $validated = $request->validate([
            'admission_attempt_ids' => ['required', 'array', 'min:1'],
            'admission_attempt_ids.*' => ['integer'],
        ]);

        $result = $action->handle(
            $session,
            $validated['admission_attempt_ids'],
            (int) $request->user()->id,
        );

        return back()->with('status', 'Assigned '.$result['assigned_count'].' applicants to Release Day session.');
    }

    public function cancelAssignment(
        CancelConsultationAssignmentRequest $request,
        ConsultationSession $session,
        ConsultationAssignment $assignment,
        CancelConsultationAssignment $action,
    ): RedirectResponse {
        $this->requireCurrentInstitution($request, $session);
        try {
            $action->handle($session, $assignment, $request->user(), $request->validated()['reason'] ?? null);
        } catch (RuntimeException $exception) {
            return back()->withErrors(['cancel_assignment' => $exception->getMessage()])->with('page_state', 'conflict');
        }

        return back()->with('status', 'Applicant cancelled from the Release Day session.');
    }

    public function reassign(
        ReassignConsultationRequest $request,
        ConsultationSession $session,
        ReassignApplicantConsultationSession $action,
    ): RedirectResponse {
        $sessionInstitutionId = $this->requireCurrentInstitution($request, $session);
        $target = ConsultationSession::query()->findOrFail((int) $request->validated()['target_session_id']);
        abort_unless($this->requireCurrentInstitution($request, $target) === $sessionInstitutionId, 404);
        $moved = 0;

        try {
            foreach ($request->validated()['assignment_ids'] as $assignmentId) {
                $assignment = ConsultationAssignment::query()
                    ->where('consultation_session_id', $session->id)
                    ->findOrFail((int) $assignmentId);
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
            ? 'Applicant reassigned to a Release Day session.'
            : "{$moved} applicants reassigned to Release Day sessions.");
    }

    public function bulkPublish(
        BulkConsultationSessionActionRequest $request,
        PublishConsultationSession $action,
        CapabilityAuthorizer $authorizer,
    ): RedirectResponse {
        $institutionId = $this->currentInstitutionId($request);
        abort_unless($authorizer->allows($request->user(), CapabilityCatalog::GUIDANCE_CONSULTATION_PLAN, [
            'institution_id' => $institutionId,
            'office' => 'guidance',
        ]), 403);

        $published = 0;
        $skippedReasons = [];
        $sessions = $this->currentInstitutionSessions($request, $request->validated()['session_ids']);

        foreach ($sessions as $session) {
            if ($session->status !== ConsultationSession::STATUS_DRAFT) {
                $reason = "Session {$session->id} is {$session->status}, not draft";
                $skippedReasons[$reason] = ($skippedReasons[$reason] ?? 0) + 1;

                continue;
            }

            try {
                $action->handle($session, $request->user(), expectedVersion: (int) $session->version);
                $published++;
            } catch (RuntimeException $exception) {
                $reason = "Session {$session->id}: {$exception->getMessage()}";
                $skippedReasons[$reason] = ($skippedReasons[$reason] ?? 0) + 1;
            }
        }

        $statusMessage = "{$published} sessions published.";
        if ($skippedReasons !== []) {
            $skippedCount = array_sum($skippedReasons);
            $reasonSummary = collect($skippedReasons)
                ->map(fn (int $count, string $reason): string => "{$reason} ({$count})")
                ->implode('; ');
            $statusMessage = "{$published} sessions published; {$skippedCount} skipped: {$reasonSummary}.";
        }

        return back()->with('status', $statusMessage);
    }

    public function bulkBookingMode(
        BulkConsultationSessionActionRequest $request,
        OpenConsultationSessionReservation $openAction,
        CloseConsultationSessionReservation $closeAction,
        CapabilityAuthorizer $authorizer,
    ): RedirectResponse {
        $institutionId = $this->currentInstitutionId($request);
        abort_unless($authorizer->allows($request->user(), CapabilityCatalog::GUIDANCE_CONSULTATION_PLAN, [
            'institution_id' => $institutionId,
            'office' => 'guidance',
        ]), 403);

        $validated = $request->validated();
        $mode = $validated['booking_mode'] ?? null;
        $updated = 0;
        $skippedReasons = [];
        $sessions = $this->currentInstitutionSessions($request, $validated['session_ids']);

        foreach ($sessions as $session) {
            try {
                if ($mode === ConsultationSession::BOOKING_OPEN_FOR_RESERVATION) {
                    $openAction->handle($session, $request->user(), expectedVersion: (int) $session->version);
                } elseif ($mode === ConsultationSession::BOOKING_STAFF_ONLY) {
                    $closeAction->handle($session, $request->user(), expectedVersion: (int) $session->version);
                } else {
                    $reason = "Session {$session->id}: no booking mode was requested";
                    $skippedReasons[$reason] = ($skippedReasons[$reason] ?? 0) + 1;

                    continue;
                }

                $updated++;
            } catch (RuntimeException $exception) {
                $reason = "Session {$session->id}: {$exception->getMessage()}";
                $skippedReasons[$reason] = ($skippedReasons[$reason] ?? 0) + 1;
            }
        }

        $statusMessage = "{$updated} sessions updated to {$mode}.";
        if ($skippedReasons !== []) {
            $skippedCount = array_sum($skippedReasons);
            $reasonSummary = collect($skippedReasons)
                ->map(fn (int $count, string $reason): string => "{$reason} ({$count})")
                ->implode('; ');
            $statusMessage = "{$updated} sessions updated; {$skippedCount} skipped: {$reasonSummary}.";
        }

        return back()->with('status', $statusMessage);
    }

    public function bulkCancel(
        BulkConsultationSessionActionRequest $request,
        CancelConsultationSession $action,
        CapabilityAuthorizer $authorizer,
    ): RedirectResponse {
        $institutionId = $this->currentInstitutionId($request);
        abort_unless($authorizer->allows($request->user(), CapabilityCatalog::GUIDANCE_CONSULTATION_PLAN, [
            'institution_id' => $institutionId,
            'office' => 'guidance',
        ]), 403);

        $validated = $request->validated();
        $cancelled = 0;
        $skippedReasons = [];
        $sessions = $this->currentInstitutionSessions($request, $validated['session_ids']);

        foreach ($sessions as $session) {
            if (in_array($session->status, [ConsultationSession::STATUS_CANCELLED, ConsultationSession::STATUS_COMPLETED], true)) {
                $reason = "Session {$session->id} is already {$session->status}";
                $skippedReasons[$reason] = ($skippedReasons[$reason] ?? 0) + 1;

                continue;
            }

            try {
                $action->handle(
                    $session,
                    $request->user(),
                    expectedVersion: (int) $session->version,
                    reason: $validated['reason'] ?? null,
                );
                $cancelled++;
            } catch (RuntimeException $exception) {
                $reason = "Session {$session->id}: {$exception->getMessage()}";
                $skippedReasons[$reason] = ($skippedReasons[$reason] ?? 0) + 1;
            }
        }

        $statusMessage = "{$cancelled} sessions cancelled.";
        if ($skippedReasons !== []) {
            $skippedCount = array_sum($skippedReasons);
            $reasonSummary = collect($skippedReasons)
                ->map(fn (int $count, string $reason): string => "{$reason} ({$count})")
                ->implode('; ');
            $statusMessage = "{$cancelled} sessions cancelled; {$skippedCount} skipped: {$reasonSummary}.";
        }

        return back()->with('status', $statusMessage);
    }

    public function publish(
        PublishConsultationSessionRequest $request,
        ConsultationSession $session,
        PublishConsultationSession $action,
    ): RedirectResponse {
        $this->requireCurrentInstitution($request, $session);
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

        return back()->with('status', 'Release Day session published.');
    }

    public function unpublish(
        UnpublishConsultationSessionRequest $request,
        ConsultationSession $session,
        UnpublishConsultationSession $action,
    ): RedirectResponse {
        $this->requireCurrentInstitution($request, $session);
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

        return back()->with('status', 'Release Day session unpublished to draft.');
    }

    public function complete(
        CompleteConsultationSessionRequest $request,
        ConsultationSession $session,
        CompleteConsultationSession $action,
    ): RedirectResponse {
        $this->requireCurrentInstitution($request, $session);
        try {
            $action->handle($session, $request->user(), $request->integer('expected_version'));
        } catch (RuntimeException $exception) {
            return back()->withErrors(['complete' => $exception->getMessage()])->with('page_state', 'conflict');
        }

        return back()->with('status', 'Release Day session completed.');
    }

    public function open(
        CompleteConsultationSessionRequest $request,
        ConsultationSession $session,
        StartConsultationSession $action,
        CapabilityAuthorizer $authorizer,
    ): RedirectResponse {
        $sessionInstitutionId = $this->requireCurrentInstitution($request, $session);
        $scope = ['institution_id' => $sessionInstitutionId, 'office' => 'guidance'];
        if (! $authorizer->allowsAny($request->user(), [
            CapabilityCatalog::GUIDANCE_CONSULTATION_PLAN,
            CapabilityCatalog::GUIDANCE_CONSULTATION_DECIDE,
        ], $scope)) {
            abort(403);
        }

        try {
            $action->handle($session, $request->user(), $request->integer('expected_version'));
        } catch (RuntimeException $exception) {
            return back()->withErrors(['open' => $exception->getMessage()])->with('page_state', 'conflict');
        }

        return back()->with('status', "Release Day session opened on today's schedule.");
    }

    public function cancel(
        CancelConsultationSessionRequest $request,
        ConsultationSession $session,
        CancelConsultationSession $action,
    ): RedirectResponse {
        $this->requireCurrentInstitution($request, $session);
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

        return back()->with('status', 'Release Day session cancelled.');
    }

    public function openReservation(
        OpenConsultationReservationRequest $request,
        ConsultationSession $session,
        OpenConsultationSessionReservation $action,
    ): RedirectResponse {
        $this->requireCurrentInstitution($request, $session);
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

        return back()->with('status', 'Release Day session opened for applicant reservations.');
    }

    public function closeReservation(
        CloseConsultationReservationRequest $request,
        ConsultationSession $session,
        CloseConsultationSessionReservation $action,
    ): RedirectResponse {
        $this->requireCurrentInstitution($request, $session);
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

        return back()->with('status', 'Applicant reservations for the Release Day session closed.');
    }

    public function previewDistribution(
        PreviewCohortDistributionRequest $request,
        PreviewCohortDistribution $action,
    ): JsonResponse {
        $cycleId = (int) $request->validated('academic_cycle_id');
        $cycleInstitutionId = (int) AcademicCycle::query()->whereKey($cycleId)->value('institution_id');
        abort_unless($cycleInstitutionId === $this->currentInstitutionId($request), 404);
        $sessions = $this->currentInstitutionSessions($request, $request->validated('target_session_ids'));
        abort_unless($sessions->every(fn (ConsultationSession $session): bool => (int) $session->academic_cycle_id === $cycleId), 404);
        $plan = $action->handle(
            AssignmentInvariant::DOMAIN_CONSULTATION,
            (int) $request->validated('academic_cycle_id'),
            $request->validated('target_session_ids'),
            $request->validated('sort_strategy'),
            $request->validated('packing_strategy'),
            $request->user(),
        );

        return response()->json($plan);
    }

    public function commitDistribution(
        CommitCohortDistributionRequest $request,
        CommitCohortDistribution $action,
    ): JsonResponse {
        $cycleId = (int) $request->validated('academic_cycle_id');
        $cycleInstitutionId = (int) AcademicCycle::query()->whereKey($cycleId)->value('institution_id');
        abort_unless($cycleInstitutionId === $this->currentInstitutionId($request), 404);
        $sessions = $this->currentInstitutionSessions($request, $request->validated('target_session_ids'));
        abort_unless($sessions->every(fn (ConsultationSession $session): bool => (int) $session->academic_cycle_id === $cycleId), 404);
        try {
            $result = $action->handle(
                AssignmentInvariant::DOMAIN_CONSULTATION,
                (int) $request->validated('academic_cycle_id'),
                $request->validated('target_session_ids'),
                $request->validated('sort_strategy'),
                $request->validated('packing_strategy'),
                $request->validated('expected_plan_hash'),
                $request->validated('expected_session_versions'),
                $request->user(),
                (bool) $request->validated('auto_assign_enabled', false),
            );

            return response()->json($result);
        } catch (StalePlanConflictException $exception) {
            return response()->json([
                'error' => 'stale_plan',
                'message' => $exception->getMessage(),
                'stale_session_ids' => $exception->staleSessionIds,
                'context' => $exception->context,
            ], 409);
        }
    }

    public function toggleAutoAssign(Request $request, ConsultationSession $session): RedirectResponse
    {
        $sessionInstitutionId = $this->requireCurrentInstitution($request, $session);
        $scope = ['institution_id' => $sessionInstitutionId, 'office' => 'guidance'];
        if (! $this->authorizer->allows($request->user(), CapabilityCatalog::GUIDANCE_CONSULTATION_PLAN, $scope)) {
            $this->authorizer->authorize($request->user(), CapabilityCatalog::GUIDANCE_CONSULTATION_PLAN, $scope);
        }

        if ($session->status !== ConsultationSession::STATUS_DRAFT || $session->booking_mode !== ConsultationSession::BOOKING_STAFF_ONLY) {
            return back()->withErrors(['auto_assign' => 'Auto-assign toggle is only available for draft staff-only Release Day sessions.'])->with('page_state', 'conflict');
        }

        try {
            $this->planningMutex->run(
                AssignmentPlanningMutex::DOMAIN_CONSULTATION,
                (int) $session->academic_cycle_id,
                function () use ($request, $session): void {
                    $locked = ConsultationSession::query()->lockForUpdate()->findOrFail($session->id);
                    if ($locked->status !== ConsultationSession::STATUS_DRAFT || $locked->booking_mode !== ConsultationSession::BOOKING_STAFF_ONLY) {
                        throw new RuntimeException('Auto-assign toggle is only available for draft staff-only sessions.');
                    }

                    $targetState = $request->has('auto_assign_enabled')
                        ? $request->boolean('auto_assign_enabled')
                        : ! (bool) $locked->auto_assign_enabled;

                    $locked->auto_assign_enabled = $targetState;
                    $locked->version = (int) $locked->version + 1;
                    $locked->save();
                }
            );
        } catch (RuntimeException $exception) {
            return back()->withErrors(['auto_assign' => $exception->getMessage()])->with('page_state', 'conflict');
        }

        return back()->with('status', 'Auto-assign updated.');
    }

    private function currentInstitutionId(Request $request): int
    {
        return (int) ($request->user()?->staffProfile?->institution_id ?? Institution::current()->id);
    }

    private function requireCurrentInstitution(Request $request, ?ConsultationSession $session = null): int
    {
        $institutionId = $this->currentInstitutionId($request);
        if ($session === null) {
            return $institutionId;
        }

        $session->loadMissing(['academicCycle', 'room']);
        $sessionInstitutionId = (int) ($session->academicCycle?->institution_id ?? $session->room?->institution_id);
        abort_if(
            $sessionInstitutionId !== $institutionId
                || ($session->academicCycle !== null && (int) $session->academicCycle->institution_id !== $institutionId)
                || ($session->room !== null && (int) $session->room->institution_id !== $institutionId),
            404,
        );

        return $sessionInstitutionId;
    }

    /**
     * @param  array<int, int|string>  $sessionIds
     * @return Collection<int, ConsultationSession>
     */
    private function currentInstitutionSessions(Request $request, array $sessionIds): Collection
    {
        $sessions = ConsultationSession::query()->whereIn('id', $sessionIds)->get();
        abort_unless($sessions->count() === count(array_unique($sessionIds)), 404);

        foreach ($sessions as $session) {
            $this->requireCurrentInstitution($request, $session);
        }

        return $sessions;
    }

    /**
     * @return array{cycles: list<array{id: int, label: string, is_active: bool}>, rooms: list<array{id: int, label: string, capacity: int}>}
     */
    private function sessionOptionsForInstitution(int $institutionId): array
    {
        $cycleIds = array_fill_keys(
            AcademicCycle::query()->where('institution_id', $institutionId)->pluck('id')->all(),
            true,
        );
        $roomIds = array_fill_keys(
            Room::query()->where('institution_id', $institutionId)->where('is_active', true)->pluck('id')->all(),
            true,
        );

        return [
            'cycles' => array_values(array_filter(
                $this->presenter->cycleOptions(),
                fn (array $cycle): bool => isset($cycleIds[$cycle['id']]),
            )),
            'rooms' => array_values(array_filter(
                $this->presenter->roomOptions(),
                fn (array $room): bool => isset($roomIds[$room['id']]),
            )),
        ];
    }
}
