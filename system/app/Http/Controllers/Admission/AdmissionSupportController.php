<?php

namespace App\Http\Controllers\Admission;

use App\Admission\Queries\CaseConversationQuery;
use App\Admission\Support\AttemptProgramFacts;
use App\Admission\Support\QuotaReservation;
use App\Admission\Support\RecordThreadService;
use App\Authorization\CapabilityAuthorizer;
use App\Authorization\CapabilityCatalog;
use App\Authorization\GovernanceAuthorizer;
use App\Consultation\Queries\ConsultationAdmissionSupportQuery;
use App\Consultation\Queries\OnlineConsultationDetailQuery;
use App\Consultation\Queries\ProgramAdvisingNoteQuery;
use App\Consultation\Services\JitsiMediaAdapter;
use App\Foundation\Actions\RecordAuditEvent;
use App\Http\Controllers\Controller;
use App\Models\Admission\AdmissionAttempt;
use App\Models\Admission\ProgramAdvisingNoteUsage;
use App\Models\Admission\ProgramFollowUp;
use App\Models\Admission\ProgramPool;
use App\Models\Admission\ProgramSelection;
use App\Models\Admission\RecordThread;
use App\Models\Consultation\ConsultationEncounter;
use App\Models\Foundation\AcademicCycle;
use App\Models\Foundation\Program;
use App\Models\User;
use App\ProgramHead\Queries\ProgramHeadApplicantQueries;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Shared per-applicant admission-support workspace (founder 2026-08-15).
 *
 * ONE inner page per applicant, consumed by Guidance (full counseling
 * workspace) and Program Head (assigned-program record + scoped threads),
 * with the two reserved model-visualization slots. Registrar is NOT here —
 * it keeps the dedicated eligibility page for status checks and stamps.
 */
class AdmissionSupportController extends Controller
{
    public function __construct(
        private readonly CapabilityAuthorizer $authorizer,
        private readonly ConsultationAdmissionSupportQuery $support,
        private readonly ProgramHeadApplicantQueries $phQueries,
        private readonly RecordThreadService $threads,
        private readonly CaseConversationQuery $conversations,
        private readonly RecordAuditEvent $audit,
        private readonly GovernanceAuthorizer $governance,
        private readonly ProgramAdvisingNoteQuery $notes,
        private readonly OnlineConsultationDetailQuery $onlineConsultation,
    ) {}

    public function show(Request $request, AdmissionAttempt $attempt, JitsiMediaAdapter $media): Response
    {
        $user = User::query()->findOrFail($request->user()->id);
        $isGuidance = $this->hasGuidanceScope($attempt, $user);
        $requestedContext = $request->query->has('program_code') || $request->query->has('cycle_code');
        if ($requestedContext) {
            $programCode = $request->query('program_code');
            $cycleCode = $request->query('cycle_code', $attempt->academicCycle?->code);
            abort_unless(is_string($programCode) && $programCode !== '' && is_string($cycleCode) && $cycleCode !== '', 404);
            $cycle = AcademicCycle::query()->where('institution_id', $attempt->institution_id)
                ->where('code', $cycleCode)->where('is_active', true)->whereKey($attempt->academic_cycle_id)->first();
            $program = Program::query()->where('institution_id', $attempt->institution_id)
                ->where('code', $programCode)->where('is_active', true)->first();
            abort_unless($cycle !== null && $program !== null && $this->attemptIsRelatedToProgram($attempt, $program), 404);
            if (! $isGuidance) {
                $authorizedProgram = $this->phProgramFor($attempt, $user, $programCode);
                abort_unless($authorizedProgram !== null && (int) $authorizedProgram->id === (int) $program->id, 404);

                return $this->render($request, $attempt, 'program_head', $authorizedProgram);
            }
        }

        if ($isGuidance) {
            $encounterId = null;
            if ($request->has('encounter_id')) {
                $validated = $request->validate(['encounter_id' => ['required', 'integer', 'min:1']]);
                $encounter = ConsultationEncounter::query()->whereKey($validated['encounter_id'])
                    ->where('admission_attempt_id', $attempt->id)->where('institution_id', $attempt->institution_id)
                    ->where('counselor_user_id', $user->id)->where('medium', ConsultationEncounter::MEDIUM_ONLINE)->firstOrFail();
                abort_unless($this->authorizer->decideFresh($user, CapabilityCatalog::GUIDANCE_CONSULTATION_DECIDE, ['institution_id' => (int) $attempt->institution_id, 'office' => 'guidance'])->allowed, 403);
                abort_unless(in_array($encounter->status, [ConsultationEncounter::STATUS_OUTCOME_RECORDED, ConsultationEncounter::STATUS_CLOSED_WITH_OUTCOME], true) || $media->accessWindowEnd($encounter) !== null, 409);
                $encounterId = (int) $encounter->id;
            }

            return $this->render($request, $attempt, 'guidance', onlineEncounterId: $encounterId);
        }
        $program = $this->phProgramFor($attempt, $user);
        if ($program !== null) {
            return $this->render($request, $attempt, 'program_head', $program);
        }

        return Inertia::render('Admission/ApplicantAdmissionSupport', [
            'page_state' => 'denied', 'role' => null, 'attempt' => null,
            'online_consultation' => ['visible' => false, 'can_start' => false, 'reason' => null, 'exception_mode' => false, 'encounter_url' => null],
        ]);
    }

    public function supportJson(Request $request, AdmissionAttempt $attempt): JsonResponse
    {
        if (! $this->hasGuidanceScope($attempt, $request->user())) {
            return response()->json(['page_state' => 'denied'], 403);
        }
        $this->auditProjectionOpen($attempt, $request->user(), 'guidance');

        return response()->json(['support' => $this->support->for($attempt)]);
    }

    private function render(Request $request, AdmissionAttempt $attempt, string $role, ?Program $program = null, ?int $onlineEncounterId = null): Response
    {
        $isGuidance = $role === 'guidance';
        $only = array_filter(explode(',', (string) $request->header('X-Inertia-Partial-Data', '')));
        $backgroundShellPoll = $only !== [] && array_intersect($only, [
            'support',
            'online_consultation',
            'available_programs',
            'ph_record',
            'threads',
            'selected_conversation',
            'published_advising_notes',
        ]) === [];
        if (! $backgroundShellPoll) {
            $this->auditProjectionOpen($attempt, $request->user(), $role, $program);
        }

        $resolvePageData = function () use ($request, $attempt, $program, $isGuidance): array {
            static $memo = null;
            if ($memo !== null) {
                return $memo;
            }

            $support = $isGuidance ? $this->support->for($attempt) : null;
            $onlineConsultation = $isGuidance
                ? $this->onlineConsultation->forGuidance($attempt, $request->user())
                : ['visible' => false, 'can_start' => false, 'reason' => null, 'exception_mode' => false, 'encounter_url' => null];
            $availablePrograms = $isGuidance
                ? Program::query()
                    ->where('institution_id', $attempt->institution_id)
                    ->where('is_active', true)
                    ->where('is_admission_offered', true)
                    ->orderBy('name')
                    ->get()
                    ->map(function (Program $candidate) use ($attempt): array {
                        $snapshot = QuotaReservation::varianceSnapshot($attempt, (int) $candidate->id);
                        $capacity = (int) $snapshot['capacity'];
                        $committed = (int) $snapshot['committed'];
                        $remaining = (int) $snapshot['remaining'];
                        $configured = (bool) $snapshot['configured'];

                        return [
                            'id' => (int) $candidate->id,
                            'code' => (string) $candidate->code,
                            'name' => (string) $candidate->name,
                            'configured' => $configured,
                            'capacity' => $capacity,
                            'committed' => $committed,
                            'remaining' => $remaining,
                            'over_capacity' => (int) $snapshot['over_capacity'],
                            'quota_percentage' => ($configured && $capacity > 0)
                                ? round(($committed / $capacity) * 100)
                                : null,
                        ];
                    })
                    ->values()
                    ->all()
                : [];
            $phRecord = $program === null
                ? null
                : $this->phQueries->protectedRecord($attempt, $program, $request->user());
            $threadProps = $this->threadProjection($request, $attempt, $program);
            $publishedAdvisingNotes = $isGuidance || ($phRecord['tier'] ?? null) === 'B'
                ? $this->publishedNotesFor($attempt, User::query()->findOrFail($request->user()->id), $program)
                : [];

            return $memo = [
                'support' => $support,
                'online_consultation' => $onlineConsultation,
                'available_programs' => $availablePrograms,
                'ph_record' => $phRecord,
                'threads' => $threadProps['threads'],
                'selected_conversation' => $threadProps['selected_conversation'],
                'published_advising_notes' => $publishedAdvisingNotes,
            ];
        };

        $props = [
            'page_state' => 'ready',
            'role' => $role,
            'online_encounter_id' => $isGuidance ? $onlineEncounterId : null,
            'attempt' => [
                'id' => $attempt->id,
                'reference_code' => $attempt->reference_code,
                'cycle_code' => $attempt->academicCycle?->code,
                'name' => $attempt->identity
                    ? trim($attempt->identity->first_name.' '.$attempt->identity->last_name)
                    : $attempt->reference_code,
            ],
            'support' => fn () => $resolvePageData()['support'],
            'online_consultation' => fn () => $resolvePageData()['online_consultation'],
            'available_programs' => fn () => $resolvePageData()['available_programs'],
            'ph_record' => fn () => $resolvePageData()['ph_record'],
            'ph_program' => $program === null ? null : ['code' => $program->code, 'name' => $program->name],
            'threads' => fn () => $resolvePageData()['threads'],
            'selected_conversation' => fn () => $resolvePageData()['selected_conversation'],
            'published_advising_notes' => fn () => $resolvePageData()['published_advising_notes'],
        ];

        return Inertia::render('Admission/ApplicantAdmissionSupport', $props);
    }

    /** @return list<array<string, mixed>> */
    private function publishedNotesFor(AdmissionAttempt $attempt, User $actor, ?Program $program): array
    {
        $cycle = AcademicCycle::query()
            ->where('institution_id', $attempt->institution_id)
            ->whereKey($attempt->academic_cycle_id)
            ->where('is_active', true)
            ->first();
        if ($cycle === null) {
            return [];
        }

        $codes = $program === null ? array_values(array_unique(array_filter([
            $attempt->preferred_program_code,
            $attempt->program_preference_1_code,
            $attempt->program_preference_2_code,
            $attempt->program_preference_3_code,
            ...$this->currentPoolProgramCodes($attempt),
        ]))) : [$program->code];
        $programs = Program::query()
            ->where('institution_id', $attempt->institution_id)
            ->where('is_active', true)
            ->when($program !== null, fn ($query) => $query->whereKey($program->id))
            ->when($program === null, function ($query) use ($attempt, $codes): void {
                $selection = AttemptProgramFacts::currentSelection($attempt);
                $followUps = ProgramFollowUp::query()->where('admission_attempt_id', $attempt->id)->select('program_id');
                $query->where(function ($eligible) use ($codes, $selection, $followUps): void {
                    $eligible->whereIn('code', $codes)
                        ->orWhereIn('id', $followUps);
                    if ($selection !== null) {
                        $eligible->orWhere('id', $selection->program_id);
                    }
                });
            })
            ->orderBy('code')
            ->get();
        $canUse = $program !== null || (AttemptProgramFacts::isAccepted($attempt) && AttemptProgramFacts::isResultReady($attempt));
        $published = [];
        foreach ($programs as $candidate) {
            if (! $this->governance->allows($actor, CapabilityCatalog::ADMISSION_ADVISING_NOTE_VIEW, $candidate, $cycle)) {
                continue;
            }
            foreach ($this->notes->publishedFor($actor, $candidate, $cycle) as $note) {
                $note['details_url'] = route('setup.decision-support.index', ['program_id' => $candidate->id, 'cycle_id' => $cycle->id, 'note_id' => $note['note_id']]);
                $note['can_record_use'] = $canUse;
                $published[] = $note;
            }
        }
        $revisionIds = array_column($published, 'revision_id');
        $usageByRevision = [];
        if ($revisionIds !== []) {
            $latestUsageIds = ProgramAdvisingNoteUsage::query()
                ->selectRaw('MAX(id)')
                ->where('admission_attempt_id', $attempt->id)
                ->where('actor_user_id', $actor->id)
                ->whereIn('revision_id', $revisionIds)
                ->groupBy('revision_id');
            $usageByRevision = ProgramAdvisingNoteUsage::query()
                ->whereIn('id', $latestUsageIds)
                ->get()
                ->keyBy('revision_id')
                ->all();
        }
        foreach ($published as &$note) {
            $usage = $usageByRevision[$note['revision_id']] ?? null;
            $note['usage_receipt'] = $usage?->only(['id', 'revision_id', 'actor_user_id', 'used_at']);
        }
        unset($note);

        return $published;
    }

    private function auditProjectionOpen(
        AdmissionAttempt $attempt,
        User $actor,
        string $role,
        ?Program $program = null,
    ): void {
        $this->audit->handle('admission.support_projection_opened', [
            'attempt_id' => $attempt->id,
            'institution_id' => $attempt->institution_id,
            'academic_cycle_id' => $attempt->academic_cycle_id,
            'role' => $role,
            'program_id' => $program?->id,
        ], (string) $actor->id);
    }

    private function hasGuidanceScope(AdmissionAttempt $attempt, User $user): bool
    {
        if (! $this->authorizer->decideFresh($user, CapabilityCatalog::GUIDANCE_CONSULTATION_DECIDE)->allowed
            && ! $this->authorizer->decideFresh($user, CapabilityCatalog::GUIDANCE_SELECTION_MANAGE)->allowed) {
            return false;
        }

        $profile = $user->staffProfile()->first();
        $scope = $user->actorScope();
        $cycle = AcademicCycle::query()->find($attempt->academic_cycle_id);

        return $profile !== null
            && $profile->isActive()
            && $profile->deactivated_at === null
            && $profile->office === 'guidance'
            && (int) $profile->institution_id === (int) $attempt->institution_id
            && $scope->institutionId === (int) $attempt->institution_id
            && $cycle !== null
            && $cycle->is_active
            && $scope->activeCycleCode !== null
            && $scope->activeCycleCode === $cycle->code;
    }

    private function threadProjection(Request $request, AdmissionAttempt $attempt, ?Program $program): array
    {
        $actor = User::query()->findOrFail($request->user()->id);
        $cycle = AcademicCycle::query()->findOrFail($attempt->academic_cycle_id);
        $expectedProgramCode = $program?->code;
        $requestedProgramCode = $request->query('program_code');
        $requestedCycleCode = $request->query('cycle_code');
        $threadType = $request->query('thread_type');
        $selectedId = $request->query('thread_id');

        if ($request->query->has('thread_id') && (! is_string($selectedId) || $selectedId === '')) {
            abort(404, 'Thread not found.');
        }

        if (($expectedProgramCode !== null
                && $requestedProgramCode !== null
                && $requestedProgramCode !== $expectedProgramCode)
            || ($requestedCycleCode !== null && $requestedCycleCode !== $cycle->code)) {
            if ($selectedId !== null) {
                abort(404, 'Thread not found.');
            }
        }

        $summaries = [];
        foreach ([RecordThread::TYPE_INTERNAL_PH_GUIDANCE, RecordThread::TYPE_APPLICANT_SUPPORT] as $type) {
            $page = $this->conversations->summariesForAttempt($attempt, $type, $actor);
            foreach ($page['data'] as $summary) {
                if ($expectedProgramCode === null || $summary['program_code'] === $expectedProgramCode) {
                    $summaries[$summary['id']] = $summary;
                }
            }
        }
        $selectedConversation = null;
        if (is_string($selectedId) && $selectedId !== '') {
            $selected = $this->conversations->selectedSummary(
                $selectedId,
                $actor,
                is_string($threadType) ? $threadType : null,
                (string) $attempt->id,
                $requestedProgramCode ?? $expectedProgramCode,
                $requestedCycleCode ?? $cycle->code,
            );
            if ($expectedProgramCode !== null && $selected['program_code'] !== $expectedProgramCode) {
                abort(404, 'Thread not found.');
            }
            $summaries[$selected['id']] = $selected;

            $selectedThread = RecordThread::query()->findOrFail($selected['id']);
            $snapshot = $this->conversations->forThread($selectedThread, $actor);
            $selectedConversation = [
                ...$snapshot,
                'snapshot_url' => $selected['snapshot_url'],
                'send_url' => $selected['send_url'],
                'read_url' => $selected['read_url'],
                'principal_key' => $selected['principal_key'],
                'attempt_key' => $selected['attempt_key'],
                'summary' => $selected,
            ];
        }

        $threadMeta = RecordThread::query()
            ->whereIn('id', array_keys($summaries))
            ->get(['id', 'program_id', 'created_at'])
            ->keyBy('id');

        $projections = [];
        foreach ($summaries as $summary) {
            $meta = $threadMeta->get($summary['id']);
            $projections[] = [
                ...$summary,
                'program_id' => $meta?->program_id,
                'created_at' => self::isoOrNull($meta?->created_at),
            ];
        }

        return [
            'threads' => $projections,
            'selected_conversation' => $selectedConversation,
        ];
    }

    private function phProgramFor(AdmissionAttempt $attempt, User $user, ?string $requestedCode = null): ?Program
    {
        if (! $this->authorizer->decideFresh($user, CapabilityCatalog::PROGRAM_HEAD_VIEW)->allowed) {
            return null;
        }
        $profile = $user->staffProfile()->first();
        $scope = $user->actorScope();
        $cycle = AcademicCycle::query()->find($attempt->academic_cycle_id);
        if ($profile === null || ! $profile->isActive() || $profile->office !== 'program'
            || (int) $profile->institution_id !== (int) $attempt->institution_id
            || $scope->institutionId !== (int) $attempt->institution_id || $cycle === null
            || ! $cycle->is_active || $scope->activeCycleCode !== $cycle->code) {
            return null;
        }
        $scopedCodes = $scope->programCodes;
        if ($scopedCodes === []) {
            return null;
        }
        if ($requestedCode !== null) {
            if (! in_array($requestedCode, $scopedCodes, true)) {
                return null;
            }
            $requestedProgram = Program::query()->where('institution_id', $attempt->institution_id)
                ->where('code', $requestedCode)->where('is_active', true)->first();

            return $requestedProgram !== null && $this->attemptIsRelatedToProgram($attempt, $requestedProgram) ? $requestedProgram : null;
        }
        $selection = ProgramSelection::query()->where('admission_attempt_id', $attempt->id)
            ->whereIn('status', ProgramSelection::NON_TERMINAL_STATUSES)->latest('id')->first();
        $candidateCodes = array_filter([
            $selection?->program?->code,
            ...ProgramFollowUp::query()->where('admission_attempt_id', $attempt->id)->with('program:id,code')
                ->orderByDesc('id')->get()->pluck('program.code')->all(),
            $attempt->program_preference_1_code, $attempt->program_preference_2_code, $attempt->program_preference_3_code,
            ...$this->currentPoolProgramCodes($attempt),
        ]);
        $code = collect($candidateCodes)->first(fn ($candidate) => in_array($candidate, $scopedCodes, true));

        return $code === null ? null : Program::query()->where('institution_id', $attempt->institution_id)
            ->where('code', $code)->where('is_active', true)->first();
    }

    private function attemptIsRelatedToProgram(AdmissionAttempt $attempt, Program $program): bool
    {
        if ($attempt->status !== AdmissionAttempt::STATUS_ACCEPTED
            || ! AdmissionAttempt::query()->forWorkspace()->whereKey($attempt->id)->exists()) {
            return false;
        }
        if (in_array($program->code, [
            $attempt->program_preference_1_code, $attempt->program_preference_2_code, $attempt->program_preference_3_code,
        ], true)) {
            return true;
        }

        return ProgramFollowUp::query()->where('admission_attempt_id', $attempt->id)->where('program_id', $program->id)->exists()
            || ProgramSelection::query()->where('admission_attempt_id', $attempt->id)->where('program_id', $program->id)
                ->whereIn('status', ProgramSelection::NON_TERMINAL_STATUSES)->exists()
            || in_array($program->code, $this->currentPoolProgramCodes($attempt), true);
    }

    /**
     * @return list<string>
     */
    private function currentPoolProgramCodes(AdmissionAttempt $attempt): array
    {
        return ProgramPool::currentFor((int) $attempt->id)?->options()
            ->with('program:id,code')
            ->get()
            ->pluck('program.code')
            ->filter()
            ->values()
            ->all() ?? [];
    }

    private static function isoOrNull(mixed $value): ?string
    {
        return $value === null ? null : Carbon::parse($value)->toIso8601String();
    }
}
