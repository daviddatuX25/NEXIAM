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
use App\Http\Controllers\Controller;
use App\Http\Requests\Examination\AssignExamRequest;
use App\Http\Requests\Examination\CreateExamSessionRequest;
use App\Http\Requests\Examination\PublishExamSessionRequest;
use App\Http\Requests\Examination\UpdateExamSessionRequest;
use App\Models\Admission\AdmissionAttempt;
use App\Models\Examination\ExamAssignment;
use App\Models\Examination\ExamSession;
use App\Models\Examination\ProctorDuty;
use App\Models\Foundation\AcademicCycle;
use App\Models\Foundation\Room;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Inertia\Inertia;
use Inertia\Response;
use RuntimeException;

class ExamSessionController extends Controller
{
    public function index(Request $request, CapabilityAuthorizer $authorizer): Response
    {
        if (! $authorizer->allows($request->user(), CapabilityCatalog::GUIDANCE_EXAM_SCHEDULE)) {
            return Inertia::render('Examination/Index', [
                'page_state' => 'denied',
                'sessions' => [],
                'filters' => [],
                'statuses' => $this->statusOptions(),
                'cycles' => [],
            ]);
        }

        $filters = [
            'search' => $request->string('search')->toString() ?: null,
            'status' => $request->string('status')->toString() ?: null,
            'academic_cycle_id' => $request->integer('academic_cycle_id') ?: null,
        ];

        $query = ExamSession::query()
            ->with([
                'room:id,code,name,capacity',
                'academicCycle:id,school_year,semester,name,is_active',
                'proctorDuties' => fn ($q) => $q->where('status', ProctorDuty::STATUS_ACTIVE)->with('user:id,name'),
            ])
            ->withCount([
                'assignments as assigned_count' => fn ($q) => $q->active(),
            ])
            ->latest('starts_at');

        if ($filters['search']) {
            $search = $filters['search'];
            $query->where(function ($inner) use ($search): void {
                $inner->where('code', 'like', "%{$search}%")
                    ->orWhereHas('room', fn ($room) => $room->where('name', 'like', "%{$search}%")
                        ->orWhere('code', 'like', "%{$search}%"));
            });
        }

        if ($filters['status']) {
            $query->where('status', $filters['status']);
        }

        if ($filters['academic_cycle_id']) {
            $query->where('academic_cycle_id', $filters['academic_cycle_id']);
        } else {
            $activeCycle = AcademicCycle::active();
            if ($activeCycle !== null) {
                $query->where('academic_cycle_id', $activeCycle->id);
                $filters['academic_cycle_id'] = $activeCycle->id;
            }
        }

        $sessions = $query->limit(100)->get()->map(fn (ExamSession $session) => $this->listPayload($session));

        return Inertia::render('Examination/Index', [
            'page_state' => $sessions->isEmpty() ? 'empty' : 'ready',
            'sessions' => $sessions,
            'filters' => $filters,
            'statuses' => $this->statusOptions(),
            'cycles' => $this->cycleOptions(),
        ]);
    }

    public function create(CapabilityAuthorizer $authorizer, Request $request): Response|RedirectResponse
    {
        if (! $authorizer->allows($request->user(), CapabilityCatalog::GUIDANCE_EXAM_SCHEDULE)) {
            abort(403);
        }

        return Inertia::render('Examination/Create', [
            'cycles' => $this->cycleOptions(),
            'rooms' => $this->roomOptions(),
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

    public function show(Request $request, ExamSession $session, CapabilityAuthorizer $authorizer): Response
    {
        if (! $authorizer->allows($request->user(), CapabilityCatalog::GUIDANCE_EXAM_SCHEDULE)) {
            abort(403);
        }

        $session->load([
            'room:id,code,name,capacity',
            'academicCycle:id,school_year,semester,name,is_active',
            'proctorDuties' => fn ($q) => $q->where('status', ProctorDuty::STATUS_ACTIVE)->with('user:id,name'),
            'assignments' => fn ($q) => $q->active()->with('admissionAttempt.identity'),
        ]);

        $activeAttemptIds = ExamAssignment::query()->active()->pluck('admission_attempt_id');

        $available = AdmissionAttempt::query()
            ->with('identity')
            ->where('status', AdmissionAttempt::STATUS_ACCEPTED)
            ->where('academic_cycle_id', $session->academic_cycle_id)
            ->whereNotIn('id', $activeAttemptIds)
            ->orderByDesc('id')
            ->limit(200)
            ->get()
            ->map(fn (AdmissionAttempt $attempt) => [
                'id' => $attempt->id,
                'reference_number' => $attempt->reference_code,
                'name' => $this->attemptName($attempt),
                'email' => $attempt->contact_email,
            ]);

        $assigned = $session->assignments->map(fn (ExamAssignment $assignment) => [
            'id' => $assignment->id,
            'reference_number' => $assignment->admissionAttempt?->reference_code,
            'name' => $this->attemptName($assignment->admissionAttempt),
            'source' => $assignment->source,
        ]);

        $publishBlock = $this->publishBlockReason($session);

        $reassignTargets = ExamSession::query()
            ->with('room:id,code,name,capacity')
            ->where('academic_cycle_id', $session->academic_cycle_id)
            ->where('id', '!=', $session->id)
            ->whereNotIn('status', [ExamSession::STATUS_CANCELLED, ExamSession::STATUS_COMPLETED])
            ->orderBy('starts_at')
            ->get()
            ->map(function (ExamSession $target) {
                $assignedCount = ExamAssignment::query()->active()->where('exam_session_id', $target->id)->count();
                $capacity = (int) ($target->room?->capacity ?? 0);

                return [
                    'id' => $target->id,
                    'code' => $target->code,
                    'status' => $target->status,
                    'date' => $target->starts_at?->toDateString(),
                    'start_time' => $target->starts_at?->format('H:i'),
                    'end_time' => $target->effective_end_at?->format('H:i'),
                    'room' => $target->room?->name ?: $target->room?->code,
                    'capacity' => $capacity,
                    'assigned_count' => $assignedCount,
                    'remaining' => max(0, $capacity - $assignedCount),
                    'full' => $capacity > 0 && $assignedCount >= $capacity,
                ];
            })
            ->values()
            ->all();

        return Inertia::render('Examination/Show', [
            'session' => $this->detailPayload($session),
            'assigned_applicants' => $assigned,
            'available_applicants' => $available,
            'reassign_targets' => $reassignTargets,
            'publish_block_reason' => $publishBlock,
            'is_publishable' => $publishBlock === null && $session->status === ExamSession::STATUS_DRAFT,
        ]);
    }

    public function edit(Request $request, ExamSession $session, CapabilityAuthorizer $authorizer): Response|RedirectResponse
    {
        if (! $authorizer->allows($request->user(), CapabilityCatalog::GUIDANCE_EXAM_SCHEDULE)) {
            abort(403);
        }

        if ($session->status !== ExamSession::STATUS_DRAFT) {
            return redirect()
                ->route('exams.sessions.show', $session)
                ->withErrors(['edit' => 'Only draft sessions may be edited.']);
        }

        $session->load(['room', 'academicCycle']);

        return Inertia::render('Examination/Edit', [
            'session' => $this->detailPayload($session),
            'cycles' => $this->cycleOptions(),
            'rooms' => $this->roomOptions(),
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
        Request $request,
        ExamSession $session,
        ExamAssignment $assignment,
        RemoveApplicantFromExamSession $action,
    ): RedirectResponse {
        $validated = $request->validate([
            'reason' => ['nullable', 'string', 'max:500'],
        ]);

        try {
            $action->handle($session, $assignment, $request->user(), $validated['reason'] ?? null);
        } catch (RuntimeException $exception) {
            return back()->withErrors(['remove' => $exception->getMessage()])->with('page_state', 'conflict');
        }

        return back()->with('status', 'Applicant removed.');
    }

    public function publish(PublishExamSessionRequest $request, ExamSession $session, PublishExamSession $action): RedirectResponse
    {
        $block = $this->publishBlockReason($session->loadCount([
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

    public function close(Request $request, ExamSession $session, CloseExamSession $action): RedirectResponse
    {
        $validated = $request->validate([
            'expected_version' => ['required', 'integer', 'min:1'],
        ]);

        try {
            $action->handle($session, $request->user(), (int) $validated['expected_version']);
        } catch (RuntimeException $exception) {
            return back()->withErrors(['close' => $exception->getMessage()])->with('page_state', 'conflict');
        }

        return back()->with('status', 'Session closed; grading opened.');
    }

    public function unpublish(Request $request, ExamSession $session, UnpublishExamSession $action): RedirectResponse
    {
        $validated = $request->validate([
            'expected_version' => ['required', 'integer', 'min:1'],
            'reason' => ['required', 'string', 'max:500'],
        ]);

        try {
            $action->handle(
                $session,
                $request->user(),
                (int) $validated['expected_version'],
                $validated['reason'],
            );
        } catch (RuntimeException $exception) {
            return back()->withErrors(['unpublish' => $exception->getMessage()])->with('page_state', 'conflict');
        }

        return back()->with('status', 'Session unpublished to draft.');
    }

    public function cancel(Request $request, ExamSession $session, CancelExamSession $action): RedirectResponse
    {
        $validated = $request->validate([
            'expected_version' => ['required', 'integer', 'min:1'],
            'reason' => ['required', 'string', 'max:500'],
        ]);

        try {
            $action->handle(
                $session,
                $request->user(),
                (int) $validated['expected_version'],
                $validated['reason'],
            );
        } catch (RuntimeException $exception) {
            return back()->withErrors(['cancel' => $exception->getMessage()])->with('page_state', 'conflict');
        }

        return back()->with('status', 'Session cancelled.');
    }

    public function extend(Request $request, ExamSession $session, ExtendExamSession $action): RedirectResponse
    {
        $validated = $request->validate([
            'expected_version' => ['required', 'integer', 'min:1'],
            'effective_end_at' => ['required', 'date'],
        ]);

        try {
            $action->handle(
                $session,
                $request->user(),
                (int) $validated['expected_version'],
                Carbon::parse($validated['effective_end_at']),
            );
        } catch (RuntimeException $exception) {
            return back()->withErrors(['extend' => $exception->getMessage()])->with('page_state', 'conflict');
        }

        return back()->with('status', 'Session deadline extended.');
    }



    public function reassign(
        Request $request,
        ExamSession $session,
        ReassignApplicantExamSession $action,
    ): RedirectResponse {
        $validated = $request->validate([
            'assignment_ids' => ['required', 'array', 'min:1'],
            'assignment_ids.*' => ['integer', 'exists:exam_assignments,id'],
            'target_session_id' => ['required', 'integer', 'exists:exam_sessions,id'],
            'reason' => ['nullable', 'string', 'max:500'],
        ]);

        $target = ExamSession::query()->findOrFail((int) $validated['target_session_id']);
        $moved = 0;

        try {
            foreach ($validated['assignment_ids'] as $assignmentId) {
                $assignment = ExamAssignment::query()->findOrFail((int) $assignmentId);
                $action->handle(
                    $session,
                    $assignment,
                    $target,
                    $request->user(),
                    $validated['reason'] ?? null,
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
        Request $request,
        ExamSession $session,
        OpenExamSessionReservation $action,
    ): RedirectResponse {
        $validated = $request->validate([
            'expected_version' => ['required', 'integer', 'min:1'],
            'reason' => ['nullable', 'string', 'max:500'],
        ]);

        try {
            $action->handle(
                $session,
                $request->user(),
                (int) $validated['expected_version'],
                $validated['reason'] ?? null,
            );
        } catch (RuntimeException $exception) {
            return back()->withErrors(['open_reservation' => $exception->getMessage()])->with('page_state', 'conflict');
        }

        return back()->with('status', 'Session opened for applicant reservation.');
    }

    public function closeReservation(
        Request $request,
        ExamSession $session,
        CloseExamSessionReservation $action,
    ): RedirectResponse {
        $validated = $request->validate([
            'expected_version' => ['required', 'integer', 'min:1'],
            'reason' => ['nullable', 'string', 'max:500'],
        ]);

        try {
            $action->handle(
                $session,
                $request->user(),
                (int) $validated['expected_version'],
                $validated['reason'] ?? null,
            );
        } catch (RuntimeException $exception) {
            return back()->withErrors(['close_reservation' => $exception->getMessage()])->with('page_state', 'conflict');
        }

        return back()->with('status', 'Applicant reservation closed.');
    }

    /**
     * @return list<array{value: string, label: string}>
     */
    private function statusOptions(): array
    {
        return [
            ['value' => ExamSession::STATUS_DRAFT, 'label' => 'Draft'],
            ['value' => ExamSession::STATUS_PUBLISHED, 'label' => 'Published'],
            ['value' => ExamSession::STATUS_IN_PROGRESS, 'label' => 'In progress'],
            ['value' => ExamSession::STATUS_COMPLETED, 'label' => 'Completed'],
            ['value' => ExamSession::STATUS_CANCELLED, 'label' => 'Cancelled'],
        ];
    }

    /**
     * @return list<array{id: int, label: string, is_active: bool}>
     */
    private function cycleOptions(): array
    {
        return AcademicCycle::query()
            ->orderByDesc('is_active')
            ->orderByDesc('id')
            ->get()
            ->map(fn (AcademicCycle $cycle) => [
                'id' => $cycle->id,
                'label' => $cycle->staffLabel(),
                'is_active' => (bool) $cycle->is_active,
            ])
            ->all();
    }

    /**
     * @return list<array{id: int, label: string, capacity: int}>
     */
    private function roomOptions(): array
    {
        return Room::query()
            ->where('is_active', true)
            ->orderBy('code')
            ->get()
            ->map(fn (Room $room) => [
                'id' => $room->id,
                'label' => trim(($room->name ?: $room->code).' (cap. '.$room->capacity.')'),
                'capacity' => $room->capacity,
            ])
            ->all();
    }

    /**
     * @return array<string, mixed>
     */
    private function listPayload(ExamSession $session): array
    {
        return [
            'id' => $session->id,
            'code' => $session->code,
            'status' => $session->status,
            'booking_mode' => $session->booking_mode ?? ExamSession::BOOKING_STAFF_ONLY,
            'reservation_opened_at' => $session->reservation_opened_at?->toIso8601String(),
            'reservation_closed_at' => $session->reservation_closed_at?->toIso8601String(),
            'starts_at' => $session->starts_at?->toIso8601String(),
            'effective_end_at' => $session->effective_end_at?->toIso8601String(),
            'date' => $session->starts_at?->toDateString(),
            'start_time' => $session->starts_at?->format('H:i'),
            'end_time' => $session->effective_end_at?->format('H:i'),
            'assigned_count' => (int) ($session->assigned_count ?? 0),
            'room' => $session->room ? [
                'id' => $session->room->id,
                'code' => $session->room->code,
                'name' => $session->room->name,
                'capacity' => $session->room->capacity,
            ] : null,
            'academic_cycle' => $session->academicCycle ? [
                'id' => $session->academicCycle->id,
                'label' => $session->academicCycle->staffLabel(),
            ] : null,
            'proctors' => $session->proctorDuties
                ->map(fn (ProctorDuty $duty) => [
                    'id' => $duty->user?->id,
                    'name' => $duty->user?->name,
                ])
                ->filter(fn (array $row) => filled($row['name']))
                ->values()
                ->all(),
            'is_publishable' => $session->status === ExamSession::STATUS_DRAFT
                && $this->publishBlockReason($session) === null,
            'publish_block_reason' => $session->status === ExamSession::STATUS_DRAFT
                ? $this->publishBlockReason($session)
                : null,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function detailPayload(ExamSession $session): array
    {
        return [
            ...$this->listPayload($session),
            'version' => $session->version,
            'academic_cycle_id' => $session->academic_cycle_id,
            'room_id' => $session->room_id,
        ];
    }

    private function publishBlockReason(ExamSession $session): ?string
    {
        if ($session->status !== ExamSession::STATUS_DRAFT) {
            return 'Only draft sessions may be published.';
        }

        if ($session->room === null) {
            return 'Room is required before publish.';
        }

        if ($session->starts_at === null || $session->effective_end_at === null) {
            return 'Schedule date and time are required.';
        }

        $assigned = (int) ($session->assigned_count
            ?? $session->assignments()->active()->count());

        if ($assigned < 1) {
            return 'Assign at least one applicant before publishing.';
        }

        return null;
    }

    private function attemptName(?AdmissionAttempt $attempt): string
    {
        if ($attempt === null) {
            return '—';
        }

        $identity = $attempt->identity;
        if ($identity === null) {
            return $attempt->reference_code;
        }

        return trim(implode(' ', array_filter([
            $identity->first_name,
            $identity->middle_name,
            $identity->last_name,
            $identity->suffix,
        ]))) ?: $attempt->reference_code;
    }
}
