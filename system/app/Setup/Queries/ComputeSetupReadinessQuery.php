<?php

namespace App\Setup\Queries;

use App\Ai\Knowledge\EvidenceBundle;
use App\Ai\Policy\EmergencyStop;
use App\Ai\Policy\UsageBudgetService;
use App\Assessment\Support\TestKitEligibility;
use App\Models\Admission\AdmissionBenchmarkActivation;
use App\Models\Ai\KnowledgeSourceVersion;
use App\Models\Ai\XiamUsageReservation;
use App\Models\Assessment\TestKitVersion;
use App\Models\Communications\Announcement;
use App\Models\Communications\PublicPolicy;
use App\Models\Foundation\AcademicCycle;
use App\Models\Foundation\ApplicationWindow;
use App\Models\Foundation\AptitudeArea;
use App\Models\Foundation\Institution;
use App\Models\Foundation\IntakeFieldPolicy;
use App\Models\Foundation\IntakeRequirement;
use App\Models\Foundation\Program;
use App\Models\Foundation\ProgramQuota;
use App\Models\Foundation\Room;
use App\Models\ResultSheet\ResultSheetTemplate;
use App\Models\User;
use App\Setup\Registry\SetupReadinessRegistry;
use App\Staffing\Queries\StaffAccountReadiness;

/**
 * Setup readiness grouped by staff workflow while preserving create-then-activate checks.
 *
 * @return array{overall_percent: int, passed: int, total: int, groups: list<array{key: string, label: string, categories: list<array<string, mixed>>}>, stages: list<array{stage: int, title: string, cards: list<array<string, mixed>>}>, cards: list<array<string, mixed>>}
 */
final class ComputeSetupReadinessQuery
{
    public function __construct(
        private readonly EmergencyStop $emergencyStop,
        private readonly UsageBudgetService $usageBudget,
        private readonly SetupReadinessRegistry $registry,
        private readonly StaffAccountReadiness $staffAccountReadiness,
        private readonly SetupSeatingApplicabilityQuery $seatingApplicability = new SetupSeatingApplicabilityQuery,
    ) {}

    public function handle(?User $actor = null): array
    {
        $institution = Institution::current();
        $staffReadiness = $actor && (int) ($actor->actorScope()->institutionId ?? 0) === (int) $institution->id
            ? $this->staffAccountReadiness->handle($actor)
            : null;
        $staffSummary = $staffReadiness['summary'] ?? null;
        $staffCycle = $staffReadiness['cycle_summary'] ?? null;

        $groups = [
            [
                'key' => 'exam_preparation',
                'label' => 'Exam preparation',
                'categories' => [$this->omr(), $this->rooms(), $this->resultTemplates(), $this->admissionSupport()],
            ],
            [
                'key' => 'institutional_setup',
                'label' => 'Institutional setup',
                'categories' => [
                    $this->cycles(),
                    $this->programs(),
                    $this->privacy(),
                    $this->requirements(),
                    $this->intakeFields(),
                    $this->aptitude(),
                    $this->quotas(),
                    $this->xiamKnowledgeGovernance($actor),
                    $this->publicCommunications(),
                    $this->notifications(),
                    $staffSummary === null ? null : $this->category(
                        key: 'staff_accounts',
                        label: 'Staff account preparation',
                        href: '/staff/personnel',
                        capability: 'hr.staff.manage',
                        checks: [$this->check(
                            'Academic staff coverage reviewed',
                            ($staffCycle['status'] ?? '') === 'ready',
                            'important',
                            $staffCycle['detail'] ?? 'No active academic cycle.',
                            '/staff/personnel',
                            'Review personnel',
                        )],
                    ),
                ],
            ],
        ];

        foreach ($groups as &$group) {
            $group['categories'] = array_values(array_filter($group['categories']));
        }
        unset($group);

        $all = collect($groups)->pluck('categories')->flatten(1)->pluck('checks')->flatten(1);
        $total = $all->count();
        $passed = $all->where('passed', true)->count();

        $definitions = $this->registry->forInstitution($institution);
        $stages = [];
        $cards = [];

        foreach ($this->registry->byStage() as $stageDefinition) {
            $stageCards = [];

            foreach ($definitions as $definition) {
                if ($definition->stage() !== $stageDefinition['stage']) {
                    continue;
                }

                $stageCards[] = [
                    'key' => $definition->key(),
                    'label' => $definition->label(),
                    'description' => $definition->description(),
                    'icon' => $definition->icon(),
                    'href' => $definition->href(),
                    'desk' => $definition->desk(),
                    'capability' => $definition->key() === 'admission_support' ? 'decision_support' : $definition->capability(),
                    'status' => $definition->status($institution),
                    'summary_metric' => $definition->summaryMetric($institution),
                    'contributes' => $definition->contributesToReadiness(),
                    'checks' => $definition->checks($institution),
                    'in_navigation' => $this->registry->isVisibleInNavigation($definition->key()),
                    'navigation_context' => match ($definition->key()) {
                        'exam_materials' => 'Test setup',
                        'answer_sheets', 'booklets' => 'Resource Library',
                        default => null,
                    },
                ];
            }

            $stages[] = [
                'stage' => $stageDefinition['stage'],
                'title' => $stageDefinition['title'],
                'cards' => $stageCards,
            ];
            array_push($cards, ...$stageCards);
        }

        return [
            'overall_percent' => $total === 0 ? 0 : (int) round(($passed / $total) * 100),
            'passed' => $passed,
            'total' => $total,
            'groups' => $groups,
            'stages' => $stages,
            'cards' => $cards,
        ];

    }

    /**
     * @return array<string, mixed>
     */
    private function admissionSupport(): array
    {
        $institutionId = Institution::current()->id;
        $cycle = AcademicCycle::query()
            ->where('institution_id', $institutionId)
            ->where('is_active', true)
            ->first();
        $activation = $cycle === null
            ? null
            : AdmissionBenchmarkActivation::query()
                ->where('institution_id', $institutionId)
                ->where('academic_cycle_id', $cycle->id)
                ->whereNull('superseded_at')
                ->with('benchmarkSet')
                ->orderByDesc('sequence')
                ->first();
        $hasActive = $activation !== null;
        $setName = $activation?->benchmarkSet?->name ?? 'Unnamed benchmark set';

        return $this->category(
            key: 'admission_support',
            label: 'Decision Support',
            href: '/setup/decision-support',
            capability: 'decision_support',
            checks: [
                $this->check(
                    'Active academic cycle has an active benchmark set',
                    $hasActive,
                    'attention',
                    $hasActive
                        ? "Active benchmark set: {$setName}"
                        : 'Calibrate or import benchmarks to enable course compatibility matching.',
                    '/setup/decision-support',
                    'Open Decision Support',
                ),
            ],
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function publicCommunications(): array
    {
        $institutionId = Institution::current()->id;
        $announcements = Announcement::query()
            ->publiclyEligible($institutionId, now())
            ->count();
        $policies = PublicPolicy::query()
            ->where('institution_id', $institutionId)
            ->where('status', 'published')
            ->whereNotNull('published_revision_id')
            ->count();

        return $this->category(
            key: 'public_communications',
            label: 'Public communications & standing policies',
            href: '/communications',
            capability: 'communications',
            checks: [
                $this->check(
                    'Active cycle announcements published',
                    $announcements > 0,
                    'attention',
                    $announcements > 0 ? "{$announcements} published announcement(s)." : 'Publish cycle announcements or applicant advisories.',
                    '/communications',
                    'Manage announcements',
                    'announcements',
                ),
                $this->check(
                    'Standing public policies active',
                    $policies > 0,
                    'attention',
                    $policies > 0 ? "{$policies} published public policy/policies." : 'Publish institutional or program-specific public policies.',
                    '/communications/policies',
                    'Manage public policies',
                    'public_policies',
                ),
            ],
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function cycles(): array
    {
        $total = AcademicCycle::query()->count();
        $active = AcademicCycle::query()->where('is_active', true)->first();
        $publishedWindows = ApplicationWindow::query()->where('status', 'published')->count();
        $activeHasWindow = $active !== null && $active->primaryWindow() !== null;

        return $this->category(
            key: 'cycles',
            label: 'Academic years & application windows',
            href: '/setup/cycles',
            capability: 'setup.cycles.manage',
            checks: [
                $this->check(
                    'At least one academic year (with semester) created',
                    $total > 0,
                    'critical',
                    $total > 0 ? "{$total} academic year record(s) on file." : 'Create a school year and semester before applications open.',
                ),
                $this->check(
                    'An academic year is activated',
                    $active !== null,
                    'critical',
                    $active !== null
                        ? "Active: {$active->staffLabel()}."
                        : 'Activate one academic year so applications can open.',
                ),
                $this->check(
                    'Active year has an application window',
                    $activeHasWindow && $publishedWindows > 0,
                    'critical',
                    $activeHasWindow && $publishedWindows > 0
                        ? "Window: {$active->applicationWindowLabel()}."
                        : 'Set application window dates on the active year, then activate to publish.',
                ),
            ],
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function notifications(): array
    {
        return $this->category(
            key: 'notifications',
            label: 'Communications & notification policies',
            href: '/setup/notifications',
            capability: 'notifications',
            checks: $this->registry->get('notifications')->checks(Institution::current()),
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function programs(): array
    {
        $total = Program::query()->count();
        $active = Program::query()->where('is_active', true)->count();

        return $this->category(
            key: 'programs',
            label: 'Programs & courses',
            href: '/setup/programs',
            capability: 'setup.programs.manage',
            checks: [
                $this->check(
                    'At least one program/course created',
                    $total > 0,
                    'critical',
                    $total > 0 ? "{$total} program(s) configured." : 'Applicants need programs to apply for.',
                ),
                $this->check(
                    'At least one program is active',
                    $active > 0,
                    'critical',
                    $active > 0
                        ? "{$active} of {$total} program(s) active."
                        : ($total > 0
                            ? "All {$total} program(s) are deactivated."
                            : 'No programs to activate.'),
                ),
            ],
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function rooms(): array
    {
        $examRoomsQuery = Room::query()->where('space_kind', 'room');
        $total = (clone $examRoomsQuery)->count();
        $active = (clone $examRoomsQuery)->where('is_active', true)->count();
        $capacity = (int) (clone $examRoomsQuery)->where('is_active', true)->sum('capacity');
        $institution = Institution::current();
        $usesSeating = $this->seatingApplicability->isApplicable($institution);
        $arrangementReady = $usesSeating
            ? (clone $examRoomsQuery)
                ->where('is_active', true)
                ->whereNotNull('default_seating_arrangement_id')
                ->whereHas('defaultSeatingArrangement', fn ($query) => $query->where('is_active', true))
                ->count()
            : 0;
        $checks = [
            $this->check(
                'At least one exam room created',
                $total > 0,
                'critical',
                $total > 0 ? "{$total} exam room(s) configured." : 'Exam rooms are required for test scheduling.',
            ),
            $this->check(
                'At least one exam room is active',
                $active > 0,
                'important',
                $active > 0
                    ? "{$active} active exam room(s); capacity {$capacity}."
                    : ($total > 0
                        ? "All {$total} exam room(s) are deactivated."
                        : 'No exam rooms to activate.'),
            ),
        ];

        if ($usesSeating) {
            $checks[] = $this->check(
                'At least one active exam room has a default seating arrangement',
                $arrangementReady > 0,
                'important',
                $arrangementReady > 0
                    ? "{$arrangementReady} active exam room(s) have a default seating arrangement."
                    : 'Create and activate a default seating arrangement before scheduling seats.',
            );
        }

        return $this->category(
            key: 'rooms',
            label: 'Campus Venues & Exam Rooms',
            href: '/setup/rooms',
            capability: 'setup.rooms.manage',
            checks: $checks,
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function requirements(): array
    {
        $institutionId = Institution::current()->id;
        $total = IntakeRequirement::query()->where('institution_id', $institutionId)->count();
        $active = IntakeRequirement::query()->where('institution_id', $institutionId)->where('is_active', true)->count();

        return $this->category(
            key: 'requirements',
            label: 'Application Requirements',
            href: '/setup/requirements',
            capability: 'registrar.requirements.manage',
            checks: [
                $this->check(
                    'At least one document requirement configured',
                    $total > 0,
                    'important',
                    $total > 0 ? "{$total} requirement(s) configured." : 'Define physical credentials for Registrar review.',
                ),
                $this->check(
                    'At least one requirement is active',
                    $active > 0,
                    'important',
                    $active > 0
                        ? "{$active} of {$total} requirement(s) active."
                        : ($total > 0 ? 'All requirements deactivated.' : 'No requirements to activate.'),
                ),
            ],
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function intakeFields(): array
    {
        $institutionId = Institution::current()->id;
        $activeCycle = AcademicCycle::query()->where('institution_id', $institutionId)->where('is_active', true)->first();

        $hasActiveCycle = $activeCycle !== null;
        $totalPolicies = 0;
        $activeVersion = 1;

        if ($hasActiveCycle) {
            $policies = IntakeFieldPolicy::query()->forCycle($institutionId, $activeCycle->id)->get();
            $totalPolicies = $policies->count();
            $activeVersion = $policies->first()?->version ?? 1;
        }

        return $this->category(
            key: 'intake_fields',
            label: 'Application Form Fields',
            href: '/setup/intake-fields',
            capability: 'registrar.requirements.manage',
            checks: [
                $this->check(
                    'Active academic cycle has application field policy',
                    $totalPolicies > 0,
                    'important',
                    $totalPolicies > 0
                        ? "Policy active (version {$activeVersion}, {$totalPolicies} fields governed)."
                        : ($hasActiveCycle
                            ? 'Configure field requirement levels for the active academic cycle.'
                            : 'Activate an academic cycle before configuring field policies.'),
                ),
                $this->check(
                    'Non-negotiable identity floor enforced',
                    true,
                    'critical',
                    'first_name, last_name, birthdate, sex, and course_preference_1 locked as system-mandatory.',
                ),
            ],
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function privacy(): array
    {
        $institutionId = (int) Institution::current()->getKey();
        $total = PublicPolicy::query()
            ->where('institution_id', $institutionId)
            ->where('kind', 'privacy')
            ->count();
        $hasDesignatedPrivacyPolicy = PublicPolicy::designatedIntake($institutionId) !== null;

        return $this->category(
            key: 'privacy',
            label: 'Application Privacy Policy',
            href: '/communications/policies',
            capability: 'communications.policies.manage',
            checks: [
                $this->check(
                    'At least one privacy public policy created',
                    $total > 0,
                    'important',
                    $total > 0 ? "{$total} privacy public policy record(s)." : 'Create a privacy policy before designating the current Application Privacy Policy revision.',
                ),
                $this->check(
                    'A designated published Application Privacy Policy revision is available',
                    $hasDesignatedPrivacyPolicy,
                    'important',
                    $hasDesignatedPrivacyPolicy
                        ? 'An immutable designated privacy PublicPolicy revision is available.'
                        : 'Use Public Policies to publish a privacy revision and explicitly designate it; new applications stay unavailable until then.',
                ),
            ],
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function aptitude(): array
    {
        $total = AptitudeArea::query()->count();
        $active = AptitudeArea::query()->where('is_active', true)->count();

        return $this->category(
            key: 'aptitude',
            label: 'Test setup',
            href: '/setup/exam-materials/test-kits',
            capability: 'assessment',
            checks: [
                $this->check(
                    'At least one aptitude area created',
                    $total > 0,
                    'critical',
                    $total > 0 ? "{$total} area(s) configured." : 'Required for scoring — Test Administrator owns this.',
                ),
                $this->check(
                    'At least one aptitude area is active',
                    $active > 0,
                    'critical',
                    $active > 0
                        ? "{$active} of {$total} area(s) active."
                        : ($total > 0
                            ? "All {$total} area(s) deactivated — cannot score."
                            : 'No areas to activate.'),
                ),
            ],
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function quotas(): array
    {
        $activePrograms = Program::query()->where('is_active', true)->count();
        $cycle = AcademicCycle::query()->where('is_active', true)->first()
            ?? AcademicCycle::query()->orderByDesc('opens_on')->first();
        $quotaCount = $cycle
            ? ProgramQuota::query()->where('academic_cycle_id', $cycle->id)->count()
            : 0;
        $covered = $cycle && $activePrograms > 0
            ? ProgramQuota::query()
                ->where('academic_cycle_id', $cycle->id)
                ->whereIn('program_id', Program::query()->where('is_active', true)->select('id'))
                ->count()
            : 0;
        $allCovered = $activePrograms > 0 && $covered >= $activePrograms;

        return $this->category(
            key: 'quotas',
            label: 'Program quotas',
            href: '/setup/quotas',
            capability: 'quota',
            checks: [
                $this->check(
                    'At least one program quota set for the active cycle',
                    $quotaCount > 0,
                    'important',
                    $quotaCount > 0
                        ? "{$quotaCount} quota row(s) for ".($cycle?->code ?? 'cycle').'.'
                        : 'Program Heads / TA set slots before result release (D-087).',
                ),
                $this->check(
                    'Every active program has a quota for the cycle',
                    $allCovered,
                    'important',
                    $activePrograms === 0
                        ? 'No active programs yet.'
                        : ($allCovered
                            ? "All {$activePrograms} active program(s) have quotas."
                            : "{$covered} of {$activePrograms} active program(s) have quotas."),
                ),
            ],
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function xiamKnowledgeGovernance(?User $actor = null): array
    {
        $institutionId = Institution::current()->id;
        $sourceClasses = [
            EvidenceBundle::SOURCE_PUBLIC_FAQ,
            EvidenceBundle::SOURCE_INSTITUTION_POLICY,
        ];
        $sourceScope = fn ($query) => $query
            ->where('institution_id', $institutionId)
            ->whereIn('source_class', $sourceClasses);

        $activeKnowledgeSources = KnowledgeSourceVersion::query()
            ->where('status', KnowledgeSourceVersion::STATUS_ACTIVE)
            ->whereNotNull('approved_by')
            ->whereNotNull('approved_at')
            ->whereNotNull('published_at')
            ->whereHas('source', $sourceScope)
            ->whereHas('indexEntry', fn ($query) => $query->where('status', 'active'))
            ->distinct()
            ->count('knowledge_source_id');
        $pendingPublicationWork = KnowledgeSourceVersion::query()
            ->whereHas('source', $sourceScope)
            ->where(function ($query): void {
                $query->whereNotIn('status', [
                    KnowledgeSourceVersion::STATUS_ACTIVE,
                    KnowledgeSourceVersion::STATUS_REJECTED,
                    KnowledgeSourceVersion::STATUS_SUPERSEDED,
                    KnowledgeSourceVersion::STATUS_REVOKED,
                    KnowledgeSourceVersion::STATUS_DELETED,
                ])->orWhere(fn ($query) => $query
                    ->where('status', KnowledgeSourceVersion::STATUS_ACTIVE)
                    ->where(function ($query) {
                        $query->whereNull('approved_by')
                            ->orWhereNull('approved_at')
                            ->orWhereNull('published_at')
                            ->orWhereDoesntHave('indexEntry', fn ($query) => $query->where('status', 'active'));
                    }));
            })
            ->count();
        $unresolvedUsageHolds = XiamUsageReservation::query()
            ->where('state', XiamUsageReservation::STATE_UNKNOWN)
            ->where(fn ($query) => $query
                ->where('institution_id', $institutionId)
                ->orWhereNull('institution_id'))
            ->count();
        $hasDesignatedPrivacyPolicy = PublicPolicy::designatedIntake($institutionId) !== null;
        $askCountMeterConfigured = (string) config('xiam.usage.unit', 'asks') === 'asks'
            && $this->usageBudget->limit() > 0;
        $emergencyStopActive = $this->emergencyStop->active($actor?->actorScope()->institutionId);

        return $this->category(
            key: 'xiam_knowledge_governance',
            label: 'Admissions Policies & AI Assistant',
            href: '/knowledge',
            capability: 'knowledge',
            checks: [
                $this->check(
                    'A designated published privacy policy is available',
                    $hasDesignatedPrivacyPolicy,
                    'critical',
                    $hasDesignatedPrivacyPolicy
                        ? 'A same-institution designated published PublicPolicy revision is available.'
                        : 'No designated published privacy revision is available; public applications remain unavailable until an authorized publisher selects one.',
                    '/communications/policies',
                    'Open public policies',
                    'public_policies',
                ),
                $this->check(
                    'Approved policy/FAQ knowledge is active and indexed',
                    $activeKnowledgeSources > 0,
                    'critical',
                    $activeKnowledgeSources > 0
                        ? "{$activeKnowledgeSources} institution-scoped approved procedure/FAQ source(s) are active and indexed. Applicant retrieval remains separately gated."
                        : 'No procedure/FAQ source is active and indexed. Review retained sources in Setup → FAQs & Internal Procedures; applicant retrieval remains separately gated and publication does not enable applicant AI answers.',
                    '/knowledge',
                    'Manage Policies & FAQs',
                    'knowledge',
                ),
                $this->check(
                    'Ask-count metering is configured',
                    $askCountMeterConfigured,
                    'important',
                    $askCountMeterConfigured
                        ? 'The current usage gate counts asks only; daily ask ceiling is active.'
                        : 'A positive ask-count limit is not configured in AI Control Center.',
                    '/setup/ai-center',
                    'Configure Limits',
                    'ai.governance.office.manage',
                ),
                $this->check(
                    'Unknown usage reservation holds are reconciled',
                    $unresolvedUsageHolds === 0,
                    'important',
                    $unresolvedUsageHolds === 0
                        ? 'No unknown usage holds are recorded for this institution.'
                        : "{$unresolvedUsageHolds} unknown usage hold(s) remain to be reconciled.",
                    '/setup/ai-center',
                    'Reconcile Holds',
                    'ai.governance.office.manage',
                ),
                $this->check(
                    'Emergency stop circuit breaker is disarmed',
                    ! $emergencyStopActive,
                    'critical',
                    ! $emergencyStopActive
                        ? 'The emergency circuit breaker is disarmed; normal AI assistance is active.'
                        : 'The emergency stop is ACTIVE. Assistant proposals and tool execution are currently blocked.',
                    '/setup/ai-center',
                    'Circuit Breaker',
                    'ai.governance.institution.manage',
                ),
            ],
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function omr(): array
    {
        $institutionId = (int) Institution::current()->id;
        $activeCycle = AcademicCycle::query()
            ->where('institution_id', $institutionId)
            ->where('is_active', true)
            ->first();
        $operationalBundleExists = $activeCycle !== null && TestKitVersion::query()
            ->where('status', TestKitVersion::STATUS_ACTIVE)
            ->whereNull('revoked_at')
            ->whereHas('testKit', fn ($query) => $query->where('institution_id', $institutionId))
            ->with([
                'testKit',
                'templateVersion',
                'bookletVersion',
                'bookletSet',
                'testInstrumentVersion',
                'selectedAcceptanceReceipt',
            ])
            ->get()
            ->contains(fn (TestKitVersion $version): bool => app(TestKitEligibility::class)
                ->evaluate(
                    $version,
                    $activeCycle,
                    TestKitEligibility::PURPOSE_NEW_USE,
                    operation: $version->testInstrumentVersion?->isFinalOnly() === true ? 'external_final' : 'omr',
                )['eligible']);
        $paper = (string) config('print.paper', 'long');

        return $this->category(
            key: 'omr',
            label: 'Test Kit readiness',
            href: '/setup/exam-materials/test-kits',
            capability: 'assessment',
            checks: [
                $this->check(
                    'An active Test Kit bundle is available',
                    $operationalBundleExists,
                    'critical',
                    $operationalBundleExists
                        ? 'An accepted, institution-scoped Test Kit bundle is active for new operational use.'
                        : 'No active Test Kit bundle is available. Assemble and rehearse a candidate, then complete its separate operational gates.',
                ),
                $this->check(
                    'Answer-sheet paper size configured',
                    in_array($paper, ['long', 'legal'], true),
                    'warning',
                    "Printing paper is set to {$paper}.",
                ),
            ],
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function resultTemplates(): array
    {
        $total = ResultSheetTemplate::query()->count();
        $active = ResultSheetTemplate::query()->where('is_active', true)->first();

        return $this->category(
            key: 'result_templates',
            label: 'Result sheets',
            href: '/setup/result-templates',
            capability: 'assessment',
            checks: [
                $this->check(
                    'At least one result-sheet template created',
                    $total > 0,
                    'critical',
                    $total > 0 ? "{$total} template(s) configured." : 'Create a result sheet template for exam results.',
                ),
                $this->check(
                    'An active result-sheet template exists',
                    $active !== null,
                    'critical',
                    $active !== null
                        ? "Active: {$active->name} ({$active->mode})."
                        : 'Activate one template so results can be printed.',
                ),
            ],
        );
    }

    /**
     * @param  list<array{label: string, passed: bool, severity: string, detail: ?string, action_href?: ?string, action_label?: ?string, action_capability?: ?string}>  $checks
     * @return array<string, mixed>
     */
    private function category(
        string $key,
        string $label,
        string $href,
        string $capability,
        array $checks,
    ): array {
        return [
            'key' => $key,
            'label' => $label,
            'href' => $href,
            'capability' => $capability,
            'checks' => $checks,
        ];
    }

    /**
     * @return array{label: string, passed: bool, severity: string, detail: ?string, action_href?: ?string, action_label?: ?string, action_capability?: ?string}
     */
    private function check(
        string $label,
        bool $passed,
        string $severity,
        ?string $detail = null,
        ?string $actionHref = null,
        ?string $actionLabel = null,
        ?string $actionCapability = null,
    ): array {
        return [
            'label' => $label,
            'passed' => $passed,
            'severity' => $severity,
            'detail' => $detail,
            'action_href' => $actionHref,
            'action_label' => $actionLabel,
            'action_capability' => $actionCapability,
        ];
    }
}
