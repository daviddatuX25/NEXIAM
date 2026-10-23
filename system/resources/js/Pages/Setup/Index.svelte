<script>
    import StaffLayout from '@/Pages/Staff/StaffLayout.svelte';
    import PageHeaderBar from '@/Components/kit/PageHeaderBar.svelte';
    import FilterToolbar from '@/Components/kit/FilterToolbar.svelte';
    import {
        buildCardCorpus,
        cardMatchesTokens,
        tokenizeQuery,
    } from '@/lib/setup-search';
    import { Badge } from '@/Components/ui/badge';
    import { Button } from '@/Components/ui/button';
    import {
        ArrowRight,
        Award,
        BellRing,
        Binary,
        BookOpen,
        Brain,
        Building2,
        CalendarRange,
        CheckCircle2,
        ChevronDown,
        ChevronRight,
        CircleAlert,
        ClipboardCheck,
        Cpu,
        DoorOpen,
        FileBarChart2,
        FileSpreadsheet,
        GraduationCap,
        LayoutGrid,
        LockKeyhole,
        MessagesSquare,
        Newspaper,
        ReceiptText,
        ScanLine,
        Search,
        ShieldCheck,
        SlidersHorizontal,
        TrendingUp,
        Users,
        Bot,
        X,
    } from 'lucide-svelte';
    let {
        readiness = {
            overall_percent: 0,
            passed: 0,
            total: 0,
            groups: [],
            stages: [],
            cards: [],
        },
        capabilities = {
            setup: false,
            setup_institution: false,
            setup_cycles: false,
            setup_rooms: false,
            setup_programs: false,
            requirements: false,
            schedule: false,
            homepage_manage: false,
            program_public_profiles: false,
            ai_governance: false,
            assessment: false,
            scoring_omr: false,
            booklet: false,
            quota: false,
            knowledge: false,
            consultation_policy: false,
            report_templates: false,
            notifications: false,
            examination_notifications: false,
            results_notifications: false,
            admissions_notifications: false,
            communications: false,
            announcements: false,
            public_policies: false,
        },
        user_desk = 'all',
    } = $props();

    const hasAssessmentCatalog = $derived(
        Boolean(
            capabilities.assessment || capabilities.guidance_assessment_catalog,
        ),
    );
    let activeDesk = $state(user_desk || 'all');
    let showDiagnostics = $state(false);
    let searchQuery = $state('');
    const searchTokens = $derived(tokenizeQuery(searchQuery));
    const searchInputId = 'setup-search-input';

    const desks = [
        { key: 'all', label: 'All Desks' },
        { key: 'admin', label: 'Admin Desk' },
        { key: 'registrar', label: 'Registrar Desk' },
        { key: 'testing', label: 'Testing Unit' },
        { key: 'guidance', label: 'Guidance & Deans' },
        { key: 'system', label: 'System Ops' },
    ];

    const stageDescriptions = {
        1: 'Establish institutional information, academic calendars, degree programs, and official document templates.',
        2: 'Set the admission rules, required records, and applicant-facing policies.',
        3: 'Prepare rooms, examination materials, and operational logistics.',
        4: 'Prepare scoring and result outputs. Assessment profiles and norms are reached through Test Setup.',
        5: 'Govern admission decisions, quotas, consultation, and institutional knowledge.',
    };
    const iconByName = {
        CalendarRange,
        GraduationCap,
        Building2,
        Newspaper,
        ClipboardCheck,
        SlidersHorizontal,
        Award,
        ShieldCheck,
        BellRing,
        DoorOpen,
        LayoutGrid,
        FileSpreadsheet,
        BookOpen,
        ScanLine,
        Binary,
        Brain,
        TrendingUp,
        ReceiptText,
        FileBarChart2,
        Cpu,
        Users,
        MessagesSquare,
        Bot,
    };

    function cardVisibleForDesk(card) {
        if (
            card.capability === 'report_templates' &&
            capabilities.report_templates &&
            ['admin', 'registrar', 'guidance'].includes(activeDesk)
        ) {
            return true;
        }
        if (
            card.key === 'notifications' &&
            capabilities.notifications &&
            ['admin', 'registrar', 'testing', 'guidance'].includes(activeDesk)
        ) {
            return true;
        }
        if (activeDesk === 'all' || card.desk === activeDesk) return true;
        if (activeDesk === 'guidance' && card.desk === 'testing') {
            return (
                hasAssessmentCatalog ||
                capabilities.booklet ||
                capabilities.schedule
            );
        }

        if (activeDesk === 'guidance' && card.desk === 'system') {
            return capabilities.ai_governance;
        }

        return false;
    }

    function getCardCorpus(card, stageTitle) {
        return buildCardCorpus(card, stageTitle, requiredAccess(card));
    }

    function stageCardsWithIndependentSetup(stage) {
        const rawCards = stage.cards ?? [];
        if (
            stage.stage !== 3 ||
            !rawCards.some((card) => card.key === 'exam_materials') ||
            rawCards.some((card) => card.key === 'test_instruments')
        ) {
            return rawCards;
        }
        const result = [];
        for (const card of rawCards) {
            result.push(card);
            if (card.key === 'exam_materials') {
                result.push({
                    key: 'test_instruments',
                    label: 'Test Setup & Scoring Rules',
                    description:
                        'Configure standalone test setups, subtest sections, raw-score formulas, and percentile conversion tables.',
                    icon: 'Binary',
                    href: '/setup/test-instruments',
                    desk: 'testing',
                    capability: 'guidance.assessment.catalog',
                    status: 'ready',
                    summary_metric: 'Browse Resource Library',
                    contributes: false,
                    checks: [],
                    in_navigation: true,
                    navigation_context: 'Resource Library',
                });
            }
        }
        return result;
    }

    const allNavigableCards = $derived(
        (readiness.stages ?? []).flatMap((stage) => {
            const stageTitle = stage.title;
            return stageCardsWithIndependentSetup(stage)
                .filter((card) => card.in_navigation !== false)
                .map((card) => ({
                    ...card,
                    stageNumber: stage.stage,
                    corpus: getCardCorpus(card, stageTitle),
                }));
        }),
    );

    const globalMatchingCards = $derived(
        searchTokens.length === 0
            ? allNavigableCards
            : allNavigableCards.filter((card) =>
                  cardMatchesTokens(card.corpus, searchTokens),
              ),
    );

    const currentDeskMatchingCards = $derived(
        globalMatchingCards.filter((card) => cardVisibleForDesk(card)),
    );

    const otherDeskMatchCount = $derived(
        activeDesk === 'all'
            ? 0
            : globalMatchingCards.length - currentDeskMatchingCards.length,
    );

    const currentDeskLabel = $derived(
        desks.find((d) => d.key === activeDesk)?.label ?? 'Selected Desk',
    );

    function handleWindowKeydown(event) {
        if (
            event.key === '/' &&
            !event.ctrlKey &&
            !event.metaKey &&
            !event.altKey
        ) {
            const target = event.target;
            const isEditing =
                target instanceof HTMLInputElement ||
                target instanceof HTMLTextAreaElement ||
                target instanceof HTMLSelectElement ||
                (target instanceof HTMLElement && target.isContentEditable);
            if (!isEditing) {
                event.preventDefault();
                const el = document.getElementById(searchInputId);
                el?.focus();
            }
        } else if (event.key === 'Escape' && searchQuery) {
            searchQuery = '';
        }
    }
    const visibleStages = $derived(
        (readiness.stages ?? [])
            .map((stage) => {
                const stageTitle = stage.title;
                const matchingCards = stageCardsWithIndependentSetup(stage).filter((card) => {
                    if (card.in_navigation === false) return false;
                    if (!cardVisibleForDesk(card)) return false;
                    if (searchTokens.length === 0) return true;
                    const corpus = getCardCorpus(card, stageTitle);
                    return cardMatchesTokens(corpus, searchTokens);
                });
                return {
                    ...stage,
                    cards: matchingCards,
                };
            })
            .filter((stage) => stage.cards.length > 0)
            .sort((a, b) => a.stage - b.stage),
    );

    function cardCanOpen(card) {
        if (card.key === 'application_suggestions') {
            return Boolean(capabilities.application_suggestions);
        }
        switch (card.capability) {
            case 'hr.staff.manage':
                return Boolean(capabilities.staff_accounts);
            case 'decision_support':
                return Boolean(capabilities.decision_support);
            case 'assessment':
            case 'guidance.assessment.catalog':
                return hasAssessmentCatalog;
            case 'guidance.scoring.omr':
                return Boolean(capabilities.scoring_omr);
            case 'guidance.assessment.booklet.manage':
            case 'guidance.booklet.manage':
            case 'booklet':
                return capabilities.booklet ?? capabilities.assessment;
            case 'quota':
            case 'program.quota.manage':
                return capabilities.quota;
            case 'knowledge':
                return capabilities.knowledge;
            case 'guidance.consultation.plan':
            case 'consultation_policy':
                return capabilities.consultation_policy;
            case 'report_templates':
            case 'registrar.reports.view':
            case 'guidance.reports.view':
                return Boolean(capabilities.report_templates);
            case 'notifications':
                return (
                    capabilities.notifications ??
                    capabilities.setup_institution ??
                    capabilities.setup
                );
            case 'public_policies':
            case 'communications.policies.manage':
            case 'communications.policies.publish':
                return capabilities.public_policies;
            case 'announcements':
            case 'communications.announcements.manage':
            case 'communications.announcements.publish':
                return capabilities.announcements;
            case 'communications':
                return capabilities.communications;
            case 'setup.cycles.manage':
                return (
                    capabilities.setup_cycles ??
                    capabilities.setup_institution ??
                    capabilities.setup
                );
            case 'setup.rooms.manage':
                return (
                    capabilities.setup_rooms ??
                    capabilities.setup_institution ??
                    capabilities.setup
                );
            case 'registrar.requirements.manage':
            case 'requirements':
                return (
                    capabilities.requirements ??
                    capabilities.setup_institution ??
                    capabilities.setup
                );
            case 'guidance.exam.schedule':
            case 'schedule':
                return capabilities.schedule ?? capabilities.assessment;
            case 'program.public_profile.manage':
            case 'program_public_profiles':
                return capabilities.program_public_profiles ?? false;
            case 'communications.homepage.manage':
            case 'homepage_manage':
                return (
                    capabilities.homepage_manage ??
                    capabilities.setup_institution
                );
            case 'setup.institution.manage':
                return capabilities.setup_institution ?? capabilities.setup;
            case 'setup.programs.manage':
                return capabilities.setup_programs ?? capabilities.setup;
            case 'ai.governance.institution.manage':
            case 'ai.governance.office.manage':
            case 'ai_governance':
                return capabilities.ai_governance ?? capabilities.setup;
            case 'setup':
            case 'registrar.setup.manage':
            default:
                return (
                    capabilities.setup ||
                    capabilities.setup_institution ||
                    capabilities.setup_programs
                );
        }
    }

    function requiredAccess(card) {
        const labels = {
            decision_support: 'Scoped Decision Support access',
            assessment: 'Test Administrator',
            'guidance.assessment.catalog': 'Test Administrator',
            'guidance.scoring.omr': 'OMR Administrator',
            'guidance.assessment.booklet.manage': 'Booklet Management',
            'guidance.booklet.manage': 'Booklet Management',
            booklet: 'Booklet Management',
            quota: 'Program quota',
            'program.quota.manage': 'Program quota',
            knowledge: 'Knowledge-source',
            'guidance.consultation.plan': 'Consultation Planning',
            consultation_policy: 'Consultation Planning',
            public_policies: 'Public policy management',
            'communications.policies.manage': 'Public policy management',
            'communications.policies.publish': 'Public policy publication',
            announcements: 'Announcement management',
            'communications.announcements.manage': 'Announcement management',
            'communications.announcements.publish': 'Announcement publication',
            communications: 'Communications',
            'setup.institution.manage': 'Institutional Setup',
            'setup.cycles.manage': 'Academic Calendar Setup',
            'setup.rooms.manage': 'Venue & Room Setup',
            'registrar.requirements.manage': 'Registrar Requirements',
            requirements: 'Registrar Requirements',
            'guidance.exam.schedule': 'Exam Scheduling',
            schedule: 'Exam Scheduling',
            'program.public_profile.manage': 'Program Head / Dean',
            program_public_profiles: 'Program Head / Dean',
            'communications.homepage.manage': 'Homepage Studio',
            homepage_manage: 'Homepage Studio',
            report_templates: 'Report Templates',
            'registrar.reports.view': 'Report Templates',
            'guidance.reports.view': 'Report Templates',
            notifications: 'Email & Notifications',
            'setup.programs.manage': 'Program & Curriculum Setup',
            'ai.governance.institution.manage': 'AI Governance',
            'ai.governance.office.manage': 'AI Governance',
            ai_governance: 'AI Governance',
            setup: 'Institutional Setup',
            'registrar.setup.manage': 'Institutional Setup',
        };

        return labels[card.capability] ?? labels.setup;
    }

    function cardStatus(card) {
        if (card.status === 'critical') return 'critical';
        if (card.status === 'attention') return 'attention';
        return 'ready';
    }

    function statusVariant(status) {
        if (status === 'critical') return 'destructive';
        if (status === 'attention') return 'outline';
        return 'secondary';
    }

    const allChecks = $derived(
        (readiness.groups ?? []).flatMap((group) =>
            (group.categories ?? []).flatMap(
                (category) => category.checks ?? [],
            ),
        ),
    );
    const firstFailingCategory = $derived(
        (readiness.groups ?? [])
            .flatMap((group) => group.categories ?? [])
            .find((category) =>
                (category.checks ?? []).some((check) => !check.passed),
            ) ?? null,
    );
    const criticalBlockers = $derived(
        allChecks.filter(
            (check) => !check.passed && check.severity === 'critical',
        ),
    );
    const readinessContext = $derived({
        entityKey: 'setup-readiness',
        safetyKey: `${readiness.overall_percent ?? 0}:${readiness.passed ?? 0}:${readiness.total ?? 0}:${criticalBlockers.length}:${firstFailingCategory?.key ?? 'none'}`,
        freshness: 'current',
        overallPercent: readiness.overall_percent ?? 0,
        passedCount: readiness.passed ?? 0,
        totalCount: readiness.total ?? 0,
        isFullyReady:
            (readiness.total ?? 0) > 0 && readiness.passed === readiness.total,
        hasCriticalBlockers: criticalBlockers.length > 0,
        criticalBlockerCount: criticalBlockers.length,
        firstFailingCategoryTitle: firstFailingCategory?.title ?? null,
        firstFailingCategoryDestination:
            firstFailingCategory?.destination ?? null,
        groupsCount: (readiness.groups ?? []).length,
    });

    const breadcrumbs = [{ label: 'Setup' }];
</script>

<svelte:head>
    <title>System Setup · NEXIAM</title>
</svelte:head>

<svelte:window onkeydown={handleWindowKeydown} />

<StaffLayout {breadcrumbs}>
    <div class="min-w-0 space-y-6 p-4 sm:p-6">
        <PageHeaderBar
            tourKey="setup-readiness-hub"
            context={readinessContext}
            title="System Setup"
            description="Institutional readiness and cycle configuration organized by administrative lifecycle."
        />

        <section
            class="craft-card-depth border-border/70 bg-card rounded-2xl border p-4 sm:p-5"
            data-tour="setup-readiness-overview"
            aria-labelledby="setup-readiness-heading"
        >
            <div
                class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between"
            >
                <div class="min-w-0 flex-1 space-y-2">
                    <div class="flex flex-wrap items-center gap-2">
                        <h2
                            id="setup-readiness-heading"
                            class="text-base font-semibold"
                        >
                            Institutional readiness
                        </h2>
                        <Badge variant="secondary"
                            >{readiness.passed ?? 0} of {readiness.total ?? 0} checks
                            passed</Badge
                        >
                    </div>
                    <div
                        class="recessed-well h-2.5 overflow-hidden rounded-full p-0.5"
                        role="progressbar"
                        aria-label="Overall setup readiness"
                        aria-valuemin="0"
                        aria-valuemax="100"
                        aria-valuenow={readiness.overall_percent ?? 0}
                    >
                        <div
                            class="bg-primary h-full rounded-full transition-[width] duration-300"
                            style={`width: ${readiness.overall_percent ?? 0}%`}
                        ></div>
                    </div>
                </div>
                <div class="flex items-center gap-3 sm:justify-end">
                    <span class="text-2xl font-semibold tabular-nums"
                        >{readiness.overall_percent ?? 0}%</span
                    >
                    <Button
                        type="button"
                        variant="outline"
                        size="sm"
                        aria-expanded={showDiagnostics}
                        aria-controls="setup-readiness-diagnostics"
                        onclick={() => (showDiagnostics = !showDiagnostics)}
                    >
                        {showDiagnostics
                            ? 'Hide diagnostics'
                            : 'View diagnostics'}
                        {#if showDiagnostics}<ChevronDown
                                class="ml-1 size-4"
                            />{:else}<ChevronRight class="ml-1 size-4" />{/if}
                    </Button>
                </div>
            </div>

            {#if showDiagnostics}
                <div
                    id="setup-readiness-diagnostics"
                    class="border-border/60 mt-5 space-y-5 border-t pt-4"
                >
                    {#each readiness.groups ?? [] as group (group.key)}
                        <section
                            class="space-y-3"
                            aria-labelledby={`diagnostic-${group.key}`}
                        >
                            <h3
                                id={`diagnostic-${group.key}`}
                                class="text-sm font-semibold"
                            >
                                {group.label}
                            </h3>
                            <div
                                class="space-y-3"
                                data-tour="setup-readiness-checklist"
                            >
                                {#each group.categories ?? [] as category (category.key)}
                                    {@const checks = category.checks ?? []}
                                    {@const passedCount = checks.filter(
                                        (check) => check.passed,
                                    ).length}
                                    <div
                                        class="border-border/60 bg-muted/20 rounded-xl border p-3"
                                    >
                                        <div
                                            class="mb-2 flex flex-wrap items-center justify-between gap-2"
                                        >
                                            <h4 class="text-sm font-medium">
                                                {category.label}
                                            </h4>
                                            <span
                                                class="text-muted-foreground text-xs"
                                                >{passedCount} of {checks.length}
                                                passed</span
                                            >
                                        </div>
                                        <ul class="space-y-2">
                                            {#each checks as check (`${category.key}-${check.label}`)}
                                                {@const actionCapability =
                                                    check.action_capability ??
                                                    category.capability}
                                                <li
                                                    class="flex items-start gap-2 text-sm"
                                                >
                                                    {#if check.passed}
                                                        <CheckCircle2
                                                            class="mt-0.5 size-4 shrink-0 text-emerald-600 dark:text-emerald-400"
                                                            aria-hidden="true"
                                                        />
                                                    {:else}
                                                        <CircleAlert
                                                            class={`mt-0.5 size-4 shrink-0 ${check.severity === 'critical' ? 'text-destructive' : 'text-amber-600 dark:text-amber-400'}`}
                                                            aria-hidden="true"
                                                        />
                                                    {/if}
                                                    <span
                                                        class="min-w-0 flex-1"
                                                    >
                                                        <span
                                                            class="font-medium"
                                                            >{check.label}</span
                                                        >
                                                        {#if check.detail}<span
                                                                class="text-muted-foreground block text-xs"
                                                                >{check.detail}</span
                                                            >{/if}
                                                    </span>
                                                    {#if check.action_href && cardCanOpen( { capability: actionCapability } )}
                                                        <Button
                                                            href={check.action_href}
                                                            size="sm"
                                                            variant="ghost"
                                                            class="h-7 shrink-0 px-2 text-xs"
                                                        >
                                                            {check.action_label ??
                                                                'Configure'}
                                                            <ArrowRight
                                                                class="ml-1 size-3.5"
                                                            />
                                                        </Button>
                                                    {/if}
                                                </li>
                                            {/each}
                                        </ul>
                                    </div>
                                {/each}
                            </div>
                        </section>
                    {/each}
                </div>
            {/if}
        </section>

        <div
            class="bg-background/80 sticky top-0 z-20 space-y-3 py-2 backdrop-blur-md"
        >
            <div class="flex items-center gap-2">
                <div class="min-w-0 flex-1">
                    <FilterToolbar
                        bind:search={searchQuery}
                        searchPlaceholder="Search setup modules, settings, keywords, or checks… (Press '/' to focus)"
                        searchLabel=""
                        comboboxProps={{ 'aria-label': 'Search setup modules' }}
                        inputId={searchInputId}
                        onSearchClear={() => (searchQuery = '')}
                    />
                </div>
                {#if searchQuery}
                    <Button
                        type="button"
                        variant="ghost"
                        size="sm"
                        class="h-11 min-h-[44px] shrink-0 gap-1 text-xs md:min-h-[40px]"
                        onclick={() => (searchQuery = '')}
                        aria-label="Clear search"
                    >
                        <X class="size-4" />
                        Clear
                    </Button>
                {/if}
            </div>

            <div
                class="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between"
            >
                <nav
                    aria-label="Filter setup by administrative desk"
                    class="flex gap-2 overflow-x-auto pb-1"
                >
                    {#each desks as desk (desk.key)}
                        <button
                            type="button"
                            class={`focus-visible:ring-ring shrink-0 rounded-full border px-3.5 py-2 text-sm font-medium transition-colors focus-visible:ring-2 focus-visible:outline-none ${activeDesk === desk.key ? 'border-primary bg-primary text-primary-foreground' : 'border-border/70 bg-card text-muted-foreground hover:bg-muted hover:text-foreground'}`}
                            aria-pressed={activeDesk === desk.key}
                            onclick={() => (activeDesk = desk.key)}
                        >
                            {desk.label}
                        </button>
                    {/each}
                </nav>

                {#if searchQuery.trim()}
                    <div
                        role="status"
                        aria-live="polite"
                        aria-atomic="true"
                        class="text-muted-foreground flex flex-wrap items-center gap-1.5 text-xs"
                    >
                        <span>
                            {#if currentDeskMatchingCards.length === 0}
                                No modules match <strong class="text-foreground"
                                    >"{searchQuery.trim()}"</strong
                                >
                                in {currentDeskLabel}
                            {:else}
                                Showing <strong class="text-foreground"
                                    >{currentDeskMatchingCards.length}</strong
                                >
                                {currentDeskMatchingCards.length === 1
                                    ? 'module'
                                    : 'modules'}
                                {#if activeDesk !== 'all'}in {currentDeskLabel}{/if}
                            {/if}
                        </span>
                        {#if activeDesk !== 'all' && otherDeskMatchCount > 0}
                            <span class="text-muted-foreground/60">·</span>
                            <button
                                type="button"
                                class="text-primary font-medium underline-offset-2 hover:underline"
                                onclick={() => (activeDesk = 'all')}
                            >
                                {otherDeskMatchCount} more in other desks (Show all)
                            </button>
                        {/if}
                    </div>
                {/if}
            </div>
        </div>

        {#if searchQuery.trim() && currentDeskMatchingCards.length === 0}
            <div
                class="craft-card-depth border-border/70 bg-card space-y-4 rounded-2xl border p-8 text-center sm:p-12"
            >
                <div
                    class="bg-muted/60 text-muted-foreground mx-auto flex size-12 items-center justify-center rounded-2xl"
                >
                    <Search class="size-6" aria-hidden="true" />
                </div>
                <div class="space-y-1">
                    <h3 class="text-base font-semibold">
                        No setup modules match "{searchQuery.trim()}"
                    </h3>
                    <p class="text-muted-foreground mx-auto max-w-md text-sm">
                        No configuration cards or readiness checks matched your
                        search in {currentDeskLabel}.
                    </p>
                </div>
                {#if activeDesk !== 'all' && otherDeskMatchCount > 0}
                    <div class="space-y-2">
                        <p class="text-primary text-sm font-medium">
                            Found {otherDeskMatchCount}
                            {otherDeskMatchCount === 1
                                ? 'matching module'
                                : 'matching modules'} in other administrative desks.
                        </p>
                        <Button
                            type="button"
                            variant="outline"
                            size="sm"
                            onclick={() => (activeDesk = 'all')}
                        >
                            Search across all desks ({otherDeskMatchCount}
                            {otherDeskMatchCount === 1 ? 'result' : 'results'})
                        </Button>
                    </div>
                {:else}
                    <div>
                        <Button
                            type="button"
                            variant="secondary"
                            size="sm"
                            onclick={() => (searchQuery = '')}
                        >
                            Clear search query
                        </Button>
                    </div>
                {/if}
            </div>
        {:else if !searchQuery.trim() && visibleStages.length === 0}
            <div
                class="craft-card-depth border-border/70 bg-card text-muted-foreground rounded-2xl border p-8 text-center text-sm"
            >
                No setup modules are available for {currentDeskLabel}.
            </div>
        {:else}
            <div class="space-y-8">
                {#each visibleStages as stage (stage.stage)}
                    <section
                        class="space-y-4"
                        aria-labelledby={`setup-stage-${stage.stage}`}
                    >
                        <div class="flex items-start gap-3">
                            <span
                                class="bg-primary/10 text-primary flex size-9 shrink-0 items-center justify-center rounded-xl text-sm font-semibold"
                                >{String(stage.stage).padStart(2, '0')}</span
                            >
                            <div class="min-w-0">
                                <h2
                                    id={`setup-stage-${stage.stage}`}
                                    class="text-lg font-semibold"
                                >
                                    {stage.title}
                                </h2>
                                <p class="text-muted-foreground text-sm">
                                    {stageDescriptions[stage.stage] ??
                                        'Configure and verify the institutional settings for this lifecycle stage.'}
                                </p>
                            </div>
                        </div>

                        <ul
                            class="grid grid-cols-1 gap-4 md:grid-cols-2 lg:grid-cols-3"
                        >
                            {#each stage.cards as card (card.key)}
                                {@const Icon =
                                    iconByName[card.icon] ?? ClipboardCheck}
                                {@const allowed = cardCanOpen(card)}
                                {@const status = cardStatus(card)}
                                <li
                                    class="craft-card-depth border-border/70 bg-card flex min-w-0 flex-col rounded-2xl border p-4 transition-shadow hover:shadow-md"
                                >
                                    <div
                                        class="flex items-start justify-between gap-3"
                                    >
                                        <span
                                            class="bg-primary/10 text-primary flex size-10 shrink-0 items-center justify-center rounded-xl"
                                        >
                                            <Icon
                                                class="size-5"
                                                aria-hidden="true"
                                            />
                                        </span>
                                        <Badge
                                            variant={card.navigation_context
                                                ? 'secondary'
                                                : statusVariant(status)}
                                            class={card.navigation_context
                                                ? ''
                                                : status === 'attention'
                                                  ? 'border-amber-500/40 bg-amber-500/10 text-amber-700 dark:text-amber-300'
                                                  : status === 'ready'
                                                    ? 'border-emerald-600/20 bg-emerald-600/10 text-emerald-700 dark:text-emerald-300'
                                                    : ''}
                                        >
                                            {card.navigation_context ??
                                                (status === 'ready'
                                                    ? 'Ready'
                                                    : status === 'attention'
                                                      ? 'Attention'
                                                      : 'Critical')}
                                        </Badge>
                                    </div>
                                    <div class="mt-4 min-w-0 flex-1 space-y-2">
                                        <h3 class="leading-snug font-semibold">
                                            {card.label}
                                            {#if card.navigation_context}<span
                                                    class="text-muted-foreground mt-1 block text-xs font-medium"
                                                    >{card.navigation_context ===
                                                    'Test setup'
                                                        ? 'Primary destination'
                                                        : 'Secondary destination'}</span
                                                >{/if}
                                        </h3>
                                        <p
                                            class="text-primary text-sm font-medium"
                                        >
                                            {card.navigation_context ===
                                            'Test setup'
                                                ? 'Open Test Kits'
                                                : card.navigation_context ===
                                                    'Resource Library'
                                                  ? 'Browse Resource Library'
                                                  : (card.summary_metric ??
                                                    'Configuration status available')}
                                        </p>
                                        <p
                                            class="text-muted-foreground line-clamp-2 text-sm"
                                        >
                                            {card.description}
                                        </p>
                                    </div>
                                    <div
                                        class="border-border/60 mt-4 flex items-center justify-between gap-2 border-t pt-3"
                                    >
                                        {#if !allowed}
                                            <span
                                                class="text-muted-foreground flex min-w-0 items-center gap-1.5 text-xs"
                                                role="note"
                                            >
                                                <LockKeyhole
                                                    class="size-3.5 shrink-0"
                                                    aria-hidden="true"
                                                />
                                                <span
                                                    >Requires {requiredAccess(
                                                        card,
                                                    )} access</span
                                                >
                                            </span>
                                        {:else}
                                            <span
                                                class="text-muted-foreground text-xs"
                                                >Open configuration</span
                                            >
                                        {/if}
                                        <Button
                                            href={allowed
                                                ? card.href
                                                : undefined}
                                            disabled={!allowed || !card.href}
                                            variant={allowed
                                                ? 'outline'
                                                : 'ghost'}
                                            size="sm"
                                            class="shrink-0"
                                            aria-label={`${allowed ? 'Open' : 'Locked'} ${card.label}`}
                                        >
                                            {#if allowed}Open <ArrowRight
                                                    class="ml-1 size-4"
                                                    aria-hidden="true"
                                                />{:else}Locked{/if}
                                        </Button>
                                    </div>
                                </li>
                            {/each}
                        </ul>
                    </section>
                {/each}
            </div>
        {/if}
    </div>
</StaffLayout>
