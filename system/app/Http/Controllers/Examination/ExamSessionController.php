<?php

namespace App\Http\Controllers\Examination;

use App\Authorization\CapabilityAuthorizer;
use App\Authorization\CapabilityCatalog;
use App\Examination\Actions\AssignApplicantToExamSession;
use App\Examination\Actions\BulkAssignApplicantsToExamSession;
use App\Examination\Actions\CancelExamSession;
use App\Examination\Actions\CloseExamSession;
use App\Examination\Actions\CloseExamSessionReservation;
use App\Examination\Actions\CreateExamSession;
use App\Examination\Actions\ExtendExamSession;
use App\Examination\Actions\OpenExamSessionReservation;
use App\Examination\Actions\PublishExamSession;
use App\Examination\Actions\ReassignApplicantExamSession;
use App\Examination\Actions\ReconcileOverdueExamSessions;
use App\Examination\Actions\RemoveApplicantFromExamSession;
use App\Examination\Actions\ResolveExamSnapshotInterpretation;
use App\Examination\Actions\RevertStartedExamSession;
use App\Examination\Actions\StartExamSession;
use App\Examination\Actions\UnpublishExamSession;
use App\Examination\Actions\UpdateDraftExamSession;
use App\Examination\Queries\ExamSessionDetailQuery;
use App\Examination\Queries\ExamSessionListQuery;
use App\Examination\Support\ExamSessionPresenter;
use App\Foundation\SeatingArrangementPreview;
use App\Http\Controllers\Controller;
use App\Http\Requests\Examination\AssignExamRequest;
use App\Http\Requests\Examination\BulkExamSessionActionRequest;
use App\Http\Requests\Examination\CancelExamSessionRequest;
use App\Http\Requests\Examination\CloseExamSessionRequest;
use App\Http\Requests\Examination\CloseReservationRequest;
use App\Http\Requests\Examination\CreateExamSessionRequest;
use App\Http\Requests\Examination\ExtendExamSessionRequest;
use App\Http\Requests\Examination\OpenReservationRequest;
use App\Http\Requests\Examination\PublishExamSessionRequest;
use App\Http\Requests\Examination\ReassignExamRequest;
use App\Http\Requests\Examination\RemoveAssignmentRequest;
use App\Http\Requests\Examination\ResolveExamSnapshotInterpretationRequest;
use App\Http\Requests\Examination\RevertStartedExamSessionRequest;
use App\Http\Requests\Examination\StartExamSessionRequest;
use App\Http\Requests\Examination\UnpublishExamSessionRequest;
use App\Http\Requests\Examination\UpdateExamSessionRequest;
use App\Http\Requests\Scheduling\CommitCohortDistributionRequest;
use App\Http\Requests\Scheduling\PreviewCohortDistributionRequest;
use App\Models\Admission\AdmissionAttempt;
use App\Models\Examination\ExamAssignment;
use App\Models\Examination\ExamSession;
use App\Models\Examination\ExamSessionMaterialSnapshot;
use App\Models\Foundation\AcademicCycle;
use App\Models\Foundation\Institution;
use App\Models\Foundation\Program;
use App\Models\Foundation\Room;
use App\Models\Foundation\SeatingArrangement;
use App\Notifications\Policies\ExamNotificationPolicy;
use App\Scheduling\Actions\CommitCohortDistribution;
use App\Scheduling\Actions\PreviewCohortDistribution;
use App\Scheduling\AssignmentInvariant;
use App\Scheduling\AssignmentPlanningMutex;
use App\Scheduling\CandidatePoolQuery;
use App\Scheduling\Exceptions\StalePlanConflictException;
use App\Scheduling\Queries\SchedulingWaitlistQuery;
use App\Scheduling\Queries\SessionCodeSuggestions;
use App\Testing\PracticeWorkspace;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;
use RuntimeException;

/**
 * Exam Session Controller (Domain: Examinations & Scheduling Operations)
 *
 * Architectural Purpose:
 * Manages institutional entrance examination sessions, room capacity allocations,
 * examinee cohort rosters, and lifecycle state machines:
 * - Session CRUD: Draft creation, room selection, seating arrangements, and scheduling.
 * - Lifecycle Transitions: Draft -> Published -> In Progress -> Completed/Cancelled.
 * - Bulk Operations: Multi-session publish, reservation toggling, and bulk cancellations.
 * - Cohort Auto-Distribution: Algorithmic distribution of unassigned candidates across slots.
 * - Conduct Boundary: Testing-day conduct, attendance, and walk-ins transfer to Exam Monitoring.
 *
 * Walkthrough Integration:
 * Serves as the operational backing for the 'exam-sessions' Xiam tutorial.
 */
class ExamSessionController extends Controller
{
    private readonly CapabilityAuthorizer $authorizer;

    private readonly AssignmentPlanningMutex $planningMutex;

    public function __construct(
        private readonly ExamSessionPresenter $presenter,
        private readonly ExamSessionListQuery $listQuery,
        private readonly ExamSessionDetailQuery $detailQuery,
        ?CapabilityAuthorizer $authorizer = null,
        ?AssignmentPlanningMutex $planningMutex = null,
    ) {
        $this->authorizer = $authorizer ?? app(CapabilityAuthorizer::class);
        $this->planningMutex = $planningMutex ?? app(AssignmentPlanningMutex::class);
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
                'suggestion_cycle_id' => null,
                'selected_cycle_id' => null,
                'waitlist_counts' => ['ready' => 0, 'not_ready' => 0],
                'programs' => [],
                'sort_options' => [],
            ]);
        }

        $institutionId = (int) ($request->user()?->actorScope()?->institutionId ?? Institution::query()->value('id') ?? 1);
        $hasExplicitCycle = $request->query->has('academic_cycle_id');
        $requestedCycleId = filter_var($request->query('academic_cycle_id'), FILTER_VALIDATE_INT);
        $selectedCycleIsInstitutionOwned = $requestedCycleId !== false
            && $requestedCycleId > 0
            && AcademicCycle::query()
                ->whereKey($requestedCycleId)
                ->where('institution_id', $institutionId)
                ->exists();
        $reconcileCycleId = $hasExplicitCycle && $selectedCycleIsInstitutionOwned
            ? (int) $requestedCycleId
            : AcademicCycle::query()
                ->where('institution_id', $institutionId)
                ->where('is_active', true)
                ->value('id');
        if ($reconcileCycleId !== null) {
            app(ReconcileOverdueExamSessions::class)->handle(includePublished: true, academicCycleId: (int) $reconcileCycleId);
        }
        $payload = $this->listQuery->forScheduling($request);
        $selectedCycleId = $hasExplicitCycle && $selectedCycleIsInstitutionOwned ? (int) $requestedCycleId : null;
        $activeCycleId = $payload['filters']['academic_cycle_id'] ?? AcademicCycle::active()?->id;
        if ($hasExplicitCycle) {
            $suggestionCycleId = $selectedCycleId;
        } else {
            $activeCycles = AcademicCycle::query()
                ->where('institution_id', $institutionId)
                ->where('is_active', true)
                ->limit(2)
                ->get(['id']);
            $suggestionCycleId = $activeCycles->count() === 1 ? (int) $activeCycles->first()->id : null;
        }

        $distribution = [
            'pool_count' => $activeCycleId ? app(CandidatePoolQuery::class)->forExam((int) $activeCycleId, $institutionId)->count() : 0,
            'eligible_capacity' => $activeCycleId ? (int) ExamSession::query()->forWorkspace()->whereHas('academicCycle', fn ($q) => $q->where('institution_id', $institutionId))->distributionEligible((int) $activeCycleId)->with('room')->get()->sum(fn ($s) => app(AssignmentInvariant::class)->remainingCapacity($s)) : 0,
            'eligible_sessions_count' => $activeCycleId ? ExamSession::query()->forWorkspace()->whereHas('academicCycle', fn ($q) => $q->where('institution_id', $institutionId))->distributionEligible((int) $activeCycleId)->count() : 0,
            'active_cycle_id' => $activeCycleId ? (int) $activeCycleId : null,
        ];

        $waitlistQuery = app(SchedulingWaitlistQuery::class);
        $waitlistCounts = $waitlistQuery->countsForDomain(
            'exam',
            $institutionId,
            $activeCycleId ? (int) $activeCycleId : null,
        );

        $sortOptions = [
            ['value' => 'starts_at', 'label' => 'Starts At'],
            ['value' => 'code', 'label' => 'Session Code'],
            ['value' => 'status', 'label' => 'Status'],
            ['value' => 'created_at', 'label' => 'Created Date'],
        ];

        $programs = Program::query()
            ->where('institution_id', $institutionId)
            ->orderBy('name')
            ->get(['id', 'code', 'name'])
            ->map(fn (Program $p) => ['code' => $p->code, 'name' => $p->name, 'id' => $p->id])
            ->all();

        return Inertia::render('Examination/Index', [
            'page_state' => $payload['sessions']->isEmpty() ? 'empty' : 'ready',
            'sessions' => $payload['sessions'],
            'filters' => $payload['filters'],
            'statuses' => $this->presenter->statusOptions(),
            'cycles' => $this->presenter->cycleOptions(),
            'distribution' => $distribution,
            'suggestion_cycle_id' => $suggestionCycleId,
            'selected_cycle_id' => $selectedCycleId,
            'waitlist_counts' => $waitlistCounts,
            'programs' => $programs,
            'sort_options' => $sortOptions,
        ]);
    }

    public function waitlist(Request $request, CapabilityAuthorizer $authorizer): Response
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
        $canManage = $request->user() !== null && $authorizer->allowsAny($request->user(), CapabilityCatalog::examSessionManagementCapabilities());
        if ($canManage && $activeCycleId !== null && ! $request->filled('focus_attempt_id')) {
            app(ReconcileOverdueExamSessions::class)->handle(includePublished: true, academicCycleId: $activeCycleId);
        }
        $activeCycle = $activeCycleId === null ? null : AcademicCycle::query()->find($activeCycleId);
        $waitlistData = app(SchedulingWaitlistQuery::class)->forDomain(
            'exam',
            $institutionId,
            $activeCycleId,
            $request->all(),
            null,
            true,
        );
        $sessions = $activeCycleId === null ? [] : ExamSession::query()
            ->forWorkspace()
            ->whereHas('academicCycle', fn ($query) => $query->where('institution_id', $institutionId))
            ->where('academic_cycle_id', $activeCycleId)
            ->where('status', ExamSession::STATUS_DRAFT)
            ->whereHas('room', fn ($query) => $query->where('is_active', true))
            ->with('room:id,code,name,capacity,is_active')
            ->withCount(['assignments as assigned_count' => fn ($query) => $query->active()])
            ->orderBy('starts_at')
            ->get(['id', 'code', 'status', 'booking_mode', 'academic_cycle_id', 'starts_at', 'effective_end_at', 'room_id', 'version'])
            ->map(fn (ExamSession $session) => [
                'id' => (int) $session->id,
                'code' => $session->code,
                'status' => $session->status,
                'booking_mode' => $session->booking_mode,
                'academic_cycle_id' => (int) $session->academic_cycle_id,
                'starts_at' => $session->starts_at?->toIso8601String(),
                'ends_at' => $session->effective_end_at?->toIso8601String(),
                'assigned_count' => (int) $session->assigned_count,
                'version' => (int) $session->version,
                'room' => $session->room ? [
                    'id' => (int) $session->room->id,
                    'code' => $session->room->code,
                    'name' => $session->room->name,
                    'capacity' => (int) $session->room->capacity,
                ] : null,
            ])
            ->all();

        $distribution = [
            'pool_count' => $activeCycleId ? app(CandidatePoolQuery::class)->forExam($activeCycleId, $institutionId)->count() : 0,
            'eligible_capacity' => $activeCycleId ? (int) ExamSession::query()->forWorkspace()->whereHas('academicCycle', fn ($q) => $q->where('institution_id', $institutionId))->distributionEligible($activeCycleId)->with('room')->get()->sum(fn ($s) => app(AssignmentInvariant::class)->remainingCapacity($s)) : 0,
            'eligible_sessions_count' => $activeCycleId ? ExamSession::query()->forWorkspace()->whereHas('academicCycle', fn ($q) => $q->where('institution_id', $institutionId))->distributionEligible($activeCycleId)->count() : 0,
            'active_cycle_id' => $activeCycleId,
        ];

        return Inertia::render('Examination/Waitlist', [
            'waitlist' => $waitlistData,
            'programs' => Program::query()
                ->where('institution_id', $institutionId)
                ->where('is_active', true)
                ->orderBy('name')
                ->get(['id', 'code', 'name'])
                ->map(fn (Program $program) => ['code' => $program->code, 'name' => $program->name, 'id' => (int) $program->id])
                ->all(),
            'sessions' => $sessions,
            'cycles' => $this->presenter->cycleOptions(),
            'active_cycle_id' => $activeCycleId,
            'active_cycle' => $activeCycle ? [
                'id' => (int) $activeCycle->id,
                'code' => $activeCycle->code,
                'name' => $activeCycle->staffLabel(),
                'label' => $activeCycle->staffLabel(),
            ] : null,
            'distribution' => $distribution,
            'can_manage' => $canManage,
            'breadcrumbParent' => ['label' => 'Exam Scheduling', 'href' => route('exams.sessions.index')],
        ]);
    }

    public function create(CapabilityAuthorizer $authorizer, Request $request, SessionCodeSuggestions $codeSuggestions): Response|RedirectResponse
    {
        if (! $authorizer->allowsAny($request->user(), CapabilityCatalog::examSessionManagementCapabilities())) {
            abort(403);
        }
        $enableSeatingArrangements = Institution::current()->usesSeatingArrangements();
        $arrangementPreview = $enableSeatingArrangements
            && $request->string('seating_mode')->toString() === ExamSession::SEATING_MODE_ASSIGNED_SEATING
            && $request->filled('seating_arrangement_id')
            ? app(SeatingArrangementPreview::class)->forArrangement(
                SeatingArrangement::query()->findOrFail($request->integer('seating_arrangement_id')),
            )
            : [];

        $cycles = $this->presenter->cycleOptions();
        $cycleIds = AcademicCycle::query()
            ->where('institution_id', Institution::current()->id)
            ->whereIn('id', array_column($cycles, 'id'))
            ->pluck('id')
            ->map(fn ($id): int => (int) $id)
            ->all();

        return Inertia::render('Examination/Create', [
            'session_code_suggestions' => $codeSuggestions->forCycles('exam', $cycleIds),
            'cycles' => $cycles,
            'rooms' => $this->presenter->roomOptions(),
            'test_kits' => $this->presenter->testKitOptions(),
            'seating_arrangements' => $enableSeatingArrangements ? $this->presenter->seatingArrangementOptions() : [],
            'arrangement_preview' => $arrangementPreview,
            'default_cycle_id' => AcademicCycle::active()?->id,
            'cycle_exam_notification_policy' => ExamNotificationPolicy::fromStorage(AcademicCycle::active()?->exam_notification_policy)->toArray(),
            'enable_seating_arrangements' => $enableSeatingArrangements,
            'can_manage_test_kits' => $authorizer->allows($request->user(), CapabilityCatalog::GUIDANCE_ASSESSMENT_CATALOG),
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
                $request->string('seating_mode')->toString(),
                null,
                $request->filled('seating_arrangement_id')
                    ? SeatingArrangement::query()->findOrFail($request->integer('seating_arrangement_id'))
                    : null,
                $request->notificationPolicy(),
                testKitVersionId: isset($request->validated()['test_kit_version_id'])
                    ? (int) $request->validated()['test_kit_version_id']
                    : null,
                autoAssignEnabled: $request->boolean('auto_assign_enabled'),
            );
        } catch (ValidationException $exception) {
            return back()->withErrors($exception->errors())->with('page_state', 'conflict');
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
        ReconcileOverdueExamSessions $reconcileOverdue,
    ): Response {
        if (! $authorizer->allowsAny($request->user(), CapabilityCatalog::examSessionManagementCapabilities())) {
            abort(403);
        }

        $institutionId = (int) $request->user()->actorScope()->institutionId;
        abort_unless((int) $session->academicCycle?->institution_id === $institutionId, 404);
        $focus = $request->validate([
            'focus_assignment_id' => ['nullable', 'integer', Rule::exists(ExamAssignment::class, 'id')->where('exam_session_id', $session->id)],
        ]);
        if (! isset($focus['focus_assignment_id'])) {
            $session = $reconcileOverdue->reconcileSessionIfOverdue($session);
        }
        $detail = $this->detailQuery->query($session->id, $institutionId);
        $payload = ($detail['forInertia'])();

        $isPhysicalSeating = $payload['session']['seating_mode'] === ExamSession::SEATING_MODE_ASSIGNED_SEATING;
        $validated = $request->validate([
            'focus_assignment_id' => [
                'nullable',
                'integer',
                Rule::in(collect($payload['assigned'])->pluck('id')->all()),
            ],
        ]);

        return Inertia::render('Examination/Show', [
            'session' => $payload['session'],
            'assigned_applicants' => $payload['assigned'],
            'available_applicants' => $payload['available'],
            'reassign_targets' => $payload['reassign_targets'],
            'publish_block_reason' => $payload['publish_block_reason'],
            'is_publishable' => $payload['publish_block_reason'] === null && $session->status === ExamSession::STATUS_DRAFT,
            'can_manage_test_kits' => $authorizer->allows($request->user(), CapabilityCatalog::GUIDANCE_ASSESSMENT_CATALOG),
            'focus_assignment_id' => isset($validated['focus_assignment_id']) ? (int) $validated['focus_assignment_id'] : null,
            'is_physical_seating' => $isPhysicalSeating,
            'snapshot_interpretation' => $session->currentMaterialSnapshot === null ? null : [
                'id' => (int) $session->currentMaterialSnapshot->id,
                'revision' => (int) $session->currentMaterialSnapshot->revision,
                'seating_mode' => $session->currentMaterialSnapshot->seating_mode,
            ],
        ]);
    }

    public function resolveSnapshotInterpretation(
        ResolveExamSnapshotInterpretationRequest $request,
        ExamSession $session,
        ExamSessionMaterialSnapshot $snapshot,
        ResolveExamSnapshotInterpretation $action,
    ): RedirectResponse {
        abort_unless($request->authorize(), 403);

        try {
            $action->handle(
                $session,
                $snapshot,
                $request->user(),
                $request->integer('expected_version'),
                $request->string('seating_mode')->toString(),
                $request->string('reason')->toString(),
                $request->input('provenance_notes'),
            );
        } catch (RuntimeException $exception) {
            return back()->withErrors(['interpretation' => $exception->getMessage()])->with('page_state', 'conflict');
        }

        return back()->with('status', 'Exam session seating interpretation resolved.');
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

        $session->load([
            'room',
            'academicCycle',
            'bookletVersion.booklet',
            'bookletVersion.templateVersion.template',
            'seatingArrangement',
            'currentMaterialSnapshot.sets:id,snapshot_id,set_code',
        ]);
        $enableSeatingArrangements = Institution::current()->usesSeatingArrangements();
        $arrangementPreview = $enableSeatingArrangements
            && $session->seating_mode === ExamSession::SEATING_MODE_ASSIGNED_SEATING
            && $session->seatingArrangement !== null
            ? app(SeatingArrangementPreview::class)->forArrangement($session->seatingArrangement)
            : [];

        return Inertia::render('Examination/Edit', [
            'session' => $this->presenter->detailPayload($session),
            'cycles' => $this->presenter->cycleOptions(),
            'rooms' => $this->presenter->roomOptions(),
            'test_kits' => $this->presenter->testKitOptions($session->academic_cycle_id),
            'seating_arrangements' => $enableSeatingArrangements ? $this->presenter->seatingArrangementOptions($session->room_id) : [],
            'arrangement_preview' => $arrangementPreview,
            'cycle_exam_notification_policy' => ExamNotificationPolicy::fromStorage($session->academicCycle?->exam_notification_policy)->toArray(),
            'enable_seating_arrangements' => $enableSeatingArrangements,
            'can_manage_test_kits' => $authorizer->allows($request->user(), CapabilityCatalog::GUIDANCE_ASSESSMENT_CATALOG),
        ]);
    }

    public function update(
        UpdateExamSessionRequest $request,
        ExamSession $session,
        UpdateDraftExamSession $action,
    ): RedirectResponse {
        $validated = $request->validated();
        $testKitVersionId = array_key_exists('test_kit_version_id', $validated)
            ? ($validated['test_kit_version_id'] === null ? null : (int) $validated['test_kit_version_id'])
            : $session->test_kit_version_id;
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
                $request->string('seating_mode')->toString(),
                null,
                $request->filled('seating_arrangement_id')
                    ? SeatingArrangement::query()->findOrFail($request->integer('seating_arrangement_id'))
                    : null,
                $request->notificationPolicy(),
                $request->boolean('confirm_clear_seating_arrangement'),
                testKitVersionId: $testKitVersionId,
                autoAssignEnabled: $request->has('auto_assign_enabled') ? $request->boolean('auto_assign_enabled') : null,
            );
        } catch (ValidationException $exception) {
            return back()->withErrors($exception->errors())->with('page_state', 'conflict');
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

    public function bulkAssign(
        Request $request,
        ExamSession $session,
        BulkAssignApplicantsToExamSession $action,
    ): RedirectResponse {
        $this->authorizer->authorize($request->user(), CapabilityCatalog::GUIDANCE_EXAM_SCHEDULE);

        $validated = $request->validate([
            'admission_attempt_ids' => ['required', 'array', 'min:1'],
            'admission_attempt_ids.*' => ['integer'],
        ]);

        $ids = $validated['admission_attempt_ids'];
        $actorId = (int) $request->user()->id;

        $action->handle($session, $ids, $actorId);

        return back()->with('success', 'Assigned '.count($ids).' applicants to session.');
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

        return redirect()->route('exams.sessions.show', $session)->with('status', 'Applicant removed.');
    }

    public function bulkPublish(
        BulkExamSessionActionRequest $request,
        PublishExamSession $action,
        CapabilityAuthorizer $authorizer,
    ): RedirectResponse {
        abort_unless($authorizer->allows($request->user(), CapabilityCatalog::GUIDANCE_EXAM_SCHEDULE), 403);

        $published = 0;
        $skippedReasons = [];
        $sessions = ExamSession::query()->forWorkspace()->whereIn('id', $request->validated()['session_ids'])->get();

        foreach ($sessions as $session) {
            if ($session->status !== ExamSession::STATUS_DRAFT) {
                $skippedReasons[] = "{$session->code}: Session is {$session->status}, not draft";

                continue;
            }

            try {
                $action->handle($session, $request->user(), expectedVersion: $session->version);
                $published++;
            } catch (RuntimeException $exception) {
                $skippedReasons[] = "{$session->code}: {$exception->getMessage()}";
            }
        }

        $statusMessage = "{$published} sessions published.";
        if ($skippedReasons !== []) {
            $skippedCount = count($skippedReasons);
            $reasonSummary = rtrim(implode('; ', $skippedReasons), '.');
            $statusMessage = "{$published} sessions published; {$skippedCount} skipped: {$reasonSummary}.";
        }

        return back()->with($skippedReasons === [] ? 'status' : ($published > 0 ? 'warning' : 'error'), $statusMessage);
    }

    public function bulkBookingMode(
        BulkExamSessionActionRequest $request,
        OpenExamSessionReservation $openReservation,
        CloseExamSessionReservation $closeReservation,
        CapabilityAuthorizer $authorizer,
    ): RedirectResponse {
        abort_unless($authorizer->allows($request->user(), CapabilityCatalog::GUIDANCE_EXAM_SCHEDULE), 403);

        $validated = $request->validated();
        $mode = $validated['booking_mode'];
        $sessionIds = $validated['session_ids'];
        $institutionId = (int) ($request->user()?->actorScope()?->institutionId ?? Institution::query()->value('id') ?? 1);
        $sessions = ExamSession::query()->forWorkspace()
            ->whereHas('academicCycle', fn ($query) => $query->where('institution_id', $institutionId))
            ->whereIn('id', $sessionIds)
            ->get();

        if ($sessions->count() !== count(array_unique($sessionIds))) {
            abort(404);
        }

        $updatedCount = 0;
        $skippedReasons = [];
        foreach ($sessions as $session) {
            try {
                if ($mode === ExamSession::BOOKING_OPEN_FOR_RESERVATION) {
                    $openReservation->handle($session, $request->user(), (int) $session->version);
                } else {
                    $closeReservation->handle($session, $request->user(), (int) $session->version);
                }
                $updatedCount++;
            } catch (ValidationException|RuntimeException $exception) {
                $reasons = $exception instanceof ValidationException
                    ? implode(' ', $exception->validator->errors()->all())
                    : $exception->getMessage();
                $skippedReasons[] = "{$session->code}: {$reasons}";
            }
        }

        $statusMessage = "{$updatedCount} sessions updated to {$mode}.";
        if ($skippedReasons !== []) {
            $skippedCount = count($skippedReasons);
            $statusMessage .= " {$skippedCount} skipped: ".implode('; ', $skippedReasons);
        }

        return back()->with($skippedReasons === [] ? 'status' : ($updatedCount > 0 ? 'warning' : 'error'), $statusMessage);
    }

    public function bulkCancel(
        BulkExamSessionActionRequest $request,
        CancelExamSession $action,
        CapabilityAuthorizer $authorizer,
    ): RedirectResponse {
        abort_unless($authorizer->allows($request->user(), CapabilityCatalog::GUIDANCE_EXAM_SCHEDULE), 403);

        $reason = $request->validated()['reason'] ?? 'Bulk cancelled by administrator';
        $cancelled = 0;
        $skippedReasons = [];
        $sessions = ExamSession::query()->forWorkspace()->whereIn('id', $request->validated()['session_ids'])->get();

        foreach ($sessions as $session) {
            try {
                $action->handle(
                    $session,
                    $request->user(),
                    expectedVersion: $session->version,
                    reason: $reason,
                );
                $cancelled++;
            } catch (RuntimeException $exception) {
                $skippedReasons[] = "{$session->code}: {$exception->getMessage()}";
            }
        }

        $message = "{$cancelled} sessions cancelled.";
        if ($skippedReasons !== []) {
            $skippedCount = count($skippedReasons);
            $message = "{$cancelled} sessions cancelled; {$skippedCount} skipped: ".implode('; ', $skippedReasons);
        }

        return back()->with($skippedReasons === [] ? 'status' : ($cancelled > 0 ? 'warning' : 'error'), $message);
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
            $action->handle(
                $session,
                $request->user(),
                $request->integer('expected_version'),
                testKitVersionId: isset($request->validated()['test_kit_version_id'])
                    ? (int) $request->validated()['test_kit_version_id']
                    : null,
            );
        } catch (RuntimeException $exception) {
            return back()->withErrors(['publish' => $exception->getMessage()])->with('page_state', 'conflict');
        }

        return back()->with('status', 'Session published.');
    }

    public function start(StartExamSessionRequest $request, ExamSession $session, StartExamSession $action, CapabilityAuthorizer $authorizer): RedirectResponse
    {
        $user = $request->user();
        $session->loadMissing('academicCycle', 'room');
        $sessionInstitutionId = (int) ($session->academicCycle?->institution_id ?? $session->room?->institution_id);
        $actorInstitutionId = (int) ($user?->staffProfile?->institution_id ?? Institution::current()->id);
        if ($sessionInstitutionId !== $actorInstitutionId
            || (string) ($session->practice_fixture_set_id ?? '') !== (string) (app(PracticeWorkspace::class)->currentId() ?? '')) {
            abort(404);
        }
        $scope = ['institution_id' => $sessionInstitutionId, 'office' => 'guidance'];
        $canStart = $authorizer->allows($user, CapabilityCatalog::GUIDANCE_EXAM_SCHEDULE, $scope);

        if (! $canStart) {
            abort(403);
        }

        try {
            $action->handle(
                $session,
                $user,
                $request->integer('expected_version'),
                $request->filled('duration_minutes') ? $request->integer('duration_minutes') : null,
            );
        } catch (RuntimeException $exception) {
            return back()->withErrors(['start' => $exception->getMessage()])->with('page_state', 'conflict');
        }

        return back()->with('status', 'Session started.');
    }

    public function revertStart(
        RevertStartedExamSessionRequest $request,
        ExamSession $session,
        RevertStartedExamSession $action,
        CapabilityAuthorizer $authorizer,
    ): RedirectResponse {
        $user = $request->user();
        $session->loadMissing('academicCycle', 'room');
        $sessionInstitutionId = (int) ($session->academicCycle?->institution_id ?? $session->room?->institution_id);
        $actorInstitutionId = (int) ($user?->staffProfile?->institution_id ?? Institution::current()->id);
        if ($sessionInstitutionId !== $actorInstitutionId
            || (string) ($session->practice_fixture_set_id ?? '') !== (string) (app(PracticeWorkspace::class)->currentId() ?? '')) {
            abort(404);
        }
        $scope = ['institution_id' => $sessionInstitutionId, 'office' => 'guidance'];
        $canRevert = $authorizer->allows($user, CapabilityCatalog::GUIDANCE_EXAM_SCHEDULE, $scope);

        if (! $canRevert) {
            abort(403);
        }

        try {
            $action->handle($session, $user, $request->integer('expected_version'));
        } catch (RuntimeException $exception) {
            return back()->withErrors(['revert_start' => $exception->getMessage()])->with('page_state', 'conflict');
        }

        return back()->with('status', 'Session returned to published schedule.');
    }

    public function close(CloseExamSessionRequest $request, ExamSession $session, CloseExamSession $action, CapabilityAuthorizer $authorizer): RedirectResponse
    {
        $user = $request->user();
        $session->loadMissing('academicCycle', 'room');
        $sessionInstitutionId = (int) ($session->academicCycle?->institution_id ?? $session->room?->institution_id);
        $actorInstitutionId = (int) ($user?->staffProfile?->institution_id ?? Institution::current()->id);
        if ($sessionInstitutionId !== $actorInstitutionId
            || (string) ($session->practice_fixture_set_id ?? '') !== (string) (app(PracticeWorkspace::class)->currentId() ?? '')) {
            abort(404);
        }
        if (! $authorizer->allows(
            $user,
            CapabilityCatalog::GUIDANCE_EXAM_SCHEDULE,
            ['institution_id' => $sessionInstitutionId, 'office' => 'guidance'],
        )) {
            abort(403);
        }

        try {
            $closed = $action->handle($session, $request->user(), $request->integer('expected_version'));
        } catch (RuntimeException $exception) {
            return back()->withErrors(['close' => $exception->getMessage()])->with('page_state', 'conflict');
        }

        return back()->with(
            'status',
            $closed->status === ExamSession::STATUS_CANCELLED
                ? 'Session closed as cancelled (zero present examinees). Absentees returned to Waitlist.'
                : 'Session closed. Proceed to Scoring.'
        );
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

        return redirect()->route('exams.sessions.show', $session)->with('status', 'Session cancelled.');
    }

    public function extend(ExtendExamSessionRequest $request, ExamSession $session, ExtendExamSession $action, CapabilityAuthorizer $authorizer): RedirectResponse
    {
        $user = $request->user();
        $session->loadMissing('academicCycle', 'room');
        $sessionInstitutionId = (int) ($session->academicCycle?->institution_id ?? $session->room?->institution_id);
        $actorInstitutionId = (int) ($user?->staffProfile?->institution_id ?? Institution::current()->id);
        if ($sessionInstitutionId !== $actorInstitutionId
            || (string) ($session->practice_fixture_set_id ?? '') !== (string) (app(PracticeWorkspace::class)->currentId() ?? '')) {
            abort(404);
        }
        if (! $authorizer->allows(
            $user,
            CapabilityCatalog::GUIDANCE_EXAM_SCHEDULE,
            ['institution_id' => $sessionInstitutionId, 'office' => 'guidance'],
        )) {
            abort(403);
        }

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
        $target = ExamSession::query()->forWorkspace()->findOrFail((int) $request->validated()['target_session_id']);
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

        return redirect()->route('exams.sessions.show', $session)->with('status', $moved === 1
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

    public function previewDistribution(
        PreviewCohortDistributionRequest $request,
        PreviewCohortDistribution $action,
    ): JsonResponse {
        $plan = $action->handle(
            AssignmentInvariant::DOMAIN_EXAM,
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
        try {
            $result = $action->handle(
                AssignmentInvariant::DOMAIN_EXAM,
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

    public function toggleAutoAssign(Request $request, ExamSession $session): RedirectResponse
    {
        $this->authorizer->authorize($request->user(), CapabilityCatalog::GUIDANCE_EXAM_SCHEDULE);

        if ($session->status !== ExamSession::STATUS_DRAFT || $session->booking_mode !== ExamSession::BOOKING_STAFF_ONLY) {
            return back()->withErrors(['auto_assign' => 'Auto-assign toggle is only available for draft staff-only sessions.'])->with('page_state', 'conflict');
        }

        try {
            $this->planningMutex->run(
                AssignmentPlanningMutex::DOMAIN_EXAM,
                (int) $session->academic_cycle_id,
                function () use ($request, $session): void {
                    $locked = ExamSession::query()->lockForUpdate()->findOrFail($session->id);
                    if ($locked->status !== ExamSession::STATUS_DRAFT || $locked->booking_mode !== ExamSession::BOOKING_STAFF_ONLY) {
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
}
