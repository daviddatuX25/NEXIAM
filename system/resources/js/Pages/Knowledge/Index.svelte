<script>
    /**
     * Internal Procedures & Staff FAQs governance center (GF-22, AD-25).
     * Retained knowledge sources; canonical public policies belong to Communications.
     */
    import StaffLayout from '@/Pages/Staff/StaffLayout.svelte';
    import PageHeaderBar from '@/Components/kit/PageHeaderBar.svelte';
    import SwitchableListView from '@/Components/kit/SwitchableListView.svelte';
    import FilterToolbar from '@/Components/kit/FilterToolbar.svelte';
    import OperationalCard from '@/Components/kit/OperationalCard.svelte';
    import { Badge } from '@/Components/ui/badge';
    import { Button } from '@/Components/ui/button';
    import { Input } from '@/Components/ui/input';
    import * as Select from '@/Components/ui/select';
    import * as Table from '@/Components/ui/table';
    import { Link, router } from '@inertiajs/svelte';
    import {
        AlertCircle,
        ArrowRight,
        Ban,
        BookOpen,
        Bot,
        CheckCircle2,
        ChevronDown,
        ChevronRight,
        Clock,
        ExternalLink,
        Eye,
        FileText,
        Globe,
        HelpCircle,
        Lock,
        Plus,
        RotateCcw,
        Search,
        ShieldCheck,
        Sparkles,
        Users,
        X,
    } from 'lucide-svelte';

    let {
        page_state = 'ready',
        sources = [],
        filters = {},
        statuses = [],
        capabilities = { manage: false, approve: false },
        current_office = null,
        message = null,
    } = $props();

    let viewMode = $state('responsive');
    let activeTab = $state('all');
    let clientSearch = $state(filters.search ?? '');
    let filterOffice = $state('all');
    let filterStatus = $state(filters.status ?? '');
    let expandedCardId = $state(null);
    let debounceTimer;

    // Bento Telemetry Counts
    const totalCount = $derived(sources.length);
    const activeCount = $derived(
        sources.filter((s) => s.latest_version?.status === 'active').length,
    );
    const reviewCount = $derived(
        sources.filter((s) => s.latest_version?.status === 'review_required')
            .length,
    );
    const policyCount = $derived(
        sources.filter((s) => s.source_class === 'institution_policy').length,
    );
    const faqCount = $derived(
        sources.filter((s) => s.source_class === 'public_faq').length,
    );

    const knowledgeContext = $derived.by(() => {
        const safetyKey = `${page_state}:${totalCount}:${activeCount}:${reviewCount}:${capabilities?.manage ? 'm' : 'nom'}:${capabilities?.approve ? 'a' : 'noa'}`;

        return {
            entityKey: 'xiam-knowledge-hub',
            safetyKey,
            freshness: 'current',
            pageState: page_state,
            totalCount,
            activeCount,
            reviewCount,
            policyCount,
            faqCount,
            canManage: Boolean(capabilities?.manage),
            canApprove: Boolean(capabilities?.approve),
        };
    });
    const filteredSources = $derived.by(() => {
        let list = sources;

        if (activeTab === 'policies') {
            list = list.filter((s) => s.source_class === 'institution_policy');
        } else if (activeTab === 'faqs') {
            list = list.filter((s) => s.source_class === 'public_faq');
        } else if (activeTab === 'review') {
            list = list.filter(
                (s) => s.latest_version?.status === 'review_required',
            );
        }

        if (filterOffice && filterOffice !== 'all') {
            list = list.filter((s) => s.owning_office === filterOffice);
        }

        if (filterStatus && filterStatus !== 'all' && filterStatus !== '') {
            list = list.filter(
                (s) => s.latest_version?.status === filterStatus,
            );
        }

        if (clientSearch.trim()) {
            const q = clientSearch.trim().toLowerCase();
            list = list.filter((s) => {
                const title = (s.title ?? '').toLowerCase();
                const key = (s.key ?? '').toLowerCase();
                const question = (
                    s.latest_version?.question ?? ''
                ).toLowerCase();
                const answer = (s.latest_version?.answer ?? '').toLowerCase();
                const body = (s.latest_version?.body_text ?? '').toLowerCase();
                return (
                    title.includes(q) ||
                    key.includes(q) ||
                    question.includes(q) ||
                    answer.includes(q) ||
                    body.includes(q)
                );
            });
        }

        return list;
    });

    function applyServerFilters() {
        router.get(
            '/knowledge',
            {
                search: clientSearch || undefined,
                status: filterStatus || undefined,
            },
            { preserveState: true, replace: true },
        );
    }

    function onSearchInput() {
        clearTimeout(debounceTimer);
        debounceTimer = setTimeout(applyServerFilters, 400);
    }

    function clearSearch() {
        clientSearch = '';
        applyServerFilters();
    }

    function resetAllFilters() {
        clientSearch = '';
        filterOffice = 'all';
        filterStatus = '';
        activeTab = 'all';
        applyServerFilters();
    }

    function toggleCardExpand(id) {
        expandedCardId = expandedCardId === id ? null : id;
    }

    function statusConfig(status) {
        switch (status) {
            case 'active':
                return {
                    label: 'Active Source',
                    badgeClass:
                        'bg-emerald-500/10 text-emerald-700 dark:text-emerald-400 border-emerald-500/20',
                    dotClass: 'bg-emerald-500',
                    icon: Sparkles,
                };
            case 'review_required':
                return {
                    label: 'Needs Review',
                    badgeClass:
                        'bg-amber-500/10 text-amber-700 dark:text-amber-400 border-amber-500/20',
                    dotClass: 'bg-amber-500',
                    icon: Clock,
                };
            case 'approved':
                return {
                    label: 'Approved',
                    badgeClass:
                        'bg-sky-500/10 text-sky-700 dark:text-sky-400 border-sky-500/20',
                    dotClass: 'bg-sky-500',
                    icon: ShieldCheck,
                };
            case 'draft':
                return {
                    label: 'Draft',
                    badgeClass:
                        'bg-slate-500/10 text-slate-700 dark:text-slate-300 border-slate-500/20',
                    dotClass: 'bg-slate-400',
                    icon: FileText,
                };
            case 'extracting':
            case 'indexing':
                return {
                    label:
                        status === 'indexing' ? 'Indexing...' : 'Extracting...',
                    badgeClass:
                        'bg-purple-500/10 text-purple-700 dark:text-purple-400 border-purple-500/20 animate-pulse',
                    dotClass: 'bg-purple-500',
                    icon: Bot,
                };
            case 'revoked':
                return {
                    label: 'Revoked',
                    badgeClass:
                        'bg-rose-500/10 text-rose-700 dark:text-rose-400 border-rose-500/20',
                    dotClass: 'bg-rose-500',
                    icon: Ban,
                };
            case 'index_failed':
            case 'rejected':
                return {
                    label:
                        status === 'index_failed' ? 'Index Failed' : 'Rejected',
                    badgeClass:
                        'bg-red-500/10 text-red-700 dark:text-red-400 border-red-500/20',
                    dotClass: 'bg-red-500',
                    icon: AlertCircle,
                };
            default:
                return {
                    label: status ? status.replaceAll('_', ' ') : 'Draft',
                    badgeClass: 'bg-muted text-muted-foreground border-border',
                    dotClass: 'bg-muted-foreground',
                    icon: FileText,
                };
        }
    }

    function audienceBadge(audience) {
        if (audience === 'staff_and_applicant_inquiry') {
            return {
                label: 'Public & Applicants',
                icon: Globe,
                class: 'bg-emerald-500/10 text-emerald-700 dark:text-emerald-300 border-emerald-500/20',
            };
        }
        if (audience === 'cross_office_staff') {
            return {
                label: 'Cross-Office',
                icon: Users,
                class: 'bg-purple-500/10 text-purple-700 dark:text-purple-300 border-purple-500/20',
            };
        }
        return {
            label: 'Office Internal',
            icon: Lock,
            class: 'bg-muted/80 text-muted-foreground border-border/70',
        };
    }

    function officeLabel(office) {
        if (!office) return 'Unassigned';
        if (office === 'admin') return 'Central Admin';
        if (office === 'registrar') return 'Registrar';
        if (office === 'guidance') return 'Guidance';
        if (typeof office === 'object')
            return office.label ?? office.name ?? office.code ?? 'Unassigned';
        return String(office);
    }

    function normalizeOffice(office) {
        const value =
            typeof office === 'object' && office !== null
                ? (office.code ?? office.slug ?? office.name ?? office.label)
                : office;
        return String(value ?? '').toLowerCase();
    }

    function canEditSource(source) {
        return (
            source.can_edit === true ||
            (normalizeOffice(source.owning_office) !== '' &&
                normalizeOffice(source.owning_office) ===
                    normalizeOffice(current_office))
        );
    }

    function consensusLabel(source) {
        return Array.isArray(source.consensus_signatures)
            ? source.consensus_signatures.map((signature) => {
                  const office = officeLabel(signature.office).replace(
                      ' Office',
                      '',
                  );
                  return `${office} ${signature.signed ? '✓' : '⏳'}`;
              })
            : [];
    }

    function needsPolicyReview(source) {
        return (
            source.upstream_policy_status === 'needs_review' ||
            source.governing_policy_status === 'needs_review'
        );
    }

    function formatDate(isoString) {
        if (!isoString) return '—';
        try {
            const d = new Date(isoString);
            if (Number.isNaN(d.getTime())) return isoString;
            return new Intl.DateTimeFormat('en-US', {
                month: 'short',
                day: 'numeric',
                year: 'numeric',
            }).format(d);
        } catch {
            return isoString;
        }
    }

    const breadcrumbs = [
        { label: 'Communications & Support' },
        { label: 'FAQs' },
    ];
</script>

<svelte:head>
    <title>FAQs · NEXIAM</title>
</svelte:head>

<StaffLayout {breadcrumbs}>
    <div class="mx-auto max-w-7xl min-w-0 space-y-6 p-4 sm:p-6 lg:p-8">
        <!-- Page Header -->
        <PageHeaderBar
            title="FAQs"
            description="Manage official questions and verified answers. Canonical public policies belong to Communications → Public policies."
            tourKey="xiam-knowledge"
            context={knowledgeContext}
        >
            {#snippet actions()}
                <div class="flex items-center gap-2" data-tour="add-actions">
                    {#if capabilities.manage}
                        <Link href="/knowledge/create?type=policy">
                            <Button
                                variant="outline"
                                class="min-h-10 gap-2 font-medium"
                            >
                                <FileText class="size-4" />
                                Add Procedure
                            </Button>
                        </Link>
                        <Link href="/knowledge/create?type=faq">
                            <Button
                                class="min-h-10 gap-2 font-medium shadow-xs"
                            >
                                <Plus class="size-4" />
                                Add FAQ
                            </Button>
                        </Link>
                    {/if}
                </div>
            {/snippet}
        </PageHeaderBar>

        <!-- Error / Denied States -->
        {#if page_state === 'denied'}
            <div
                role="alert"
                class="border-destructive/30 bg-destructive/5 space-y-3 rounded-xl border p-6 text-center"
            >
                <Lock class="text-destructive/80 mx-auto size-8" />
                <h3 class="text-foreground font-semibold">Access Restricted</h3>
                <p class="text-muted-foreground mx-auto max-w-md text-sm">
                    You do not have permission to view or manage institutional
                    knowledge sources. Contact your system administrator for
                    guidance.
                </p>
            </div>
        {:else if ['failed', 'error', 'conflict'].includes(page_state)}
            <div
                role="alert"
                class="border-destructive/40 bg-destructive/5 text-destructive flex items-start gap-3 rounded-xl border px-4 py-3 text-sm"
            >
                <AlertCircle class="mt-0.5 size-5 shrink-0" />
                <div>
                    <p class="font-medium">Knowledge catalog unavailable</p>
                    <p class="text-destructive/80 mt-0.5 text-xs">
                        {message ??
                            'Failed to load policy sources. Please refresh or try again later.'}
                    </p>
                </div>
            </div>
        {:else}
            <!-- Executive Bento Telemetry Grid -->
            <section
                data-tour="governance-pipeline"
                aria-label="Catalog Telemetry"
                class="grid grid-cols-2 gap-3 sm:gap-4 lg:grid-cols-4"
            >
                <!-- Card 1: Active in Assistant (Emerald Beacon) -->
                <div
                    data-tour="active-telemetry"
                    class="border-border/80 bg-card relative overflow-hidden rounded-xl border p-4 shadow-xs transition-all duration-200 hover:border-emerald-500/30 sm:p-5 dark:shadow-[inset_0_1px_0_0_rgba(255,255,255,0.06)]"
                >
                    <div class="flex items-start justify-between">
                        <div>
                            <p
                                class="text-muted-foreground font-mono text-[11px] font-bold tracking-wider uppercase"
                            >
                                Active Sources
                            </p>
                            <h3
                                class="text-foreground mt-1 font-mono text-2xl font-extrabold tracking-tight tabular-nums sm:text-3xl"
                            >
                                {activeCount}
                            </h3>
                        </div>
                        <div
                            class="relative flex size-9 items-center justify-center rounded-lg border border-emerald-500/20 bg-emerald-500/10 text-emerald-600 dark:text-emerald-400"
                        >
                            <span
                                class="absolute -top-0.5 -right-0.5 flex size-2"
                            >
                                <span
                                    class="absolute inline-flex size-full animate-ping rounded-full bg-emerald-400 opacity-75"
                                ></span>
                                <span
                                    class="relative inline-flex size-2 rounded-full bg-emerald-500"
                                ></span>
                            </span>
                            <Sparkles class="size-4.5" />
                        </div>
                    </div>
                    <p
                        class="text-muted-foreground mt-3 flex items-center gap-1.5 text-xs"
                    >
                        <span
                            class="inline-block size-1.5 rounded-full bg-emerald-500"
                        ></span>
                        Indexed in local RAG
                    </p>
                </div>

                <!-- Card 2: Needs Review (Amber Alert) -->
                <div
                    class={`relative overflow-hidden rounded-xl border p-4 shadow-xs transition-all duration-200 sm:p-5 dark:shadow-[inset_0_1px_0_0_rgba(255,255,255,0.06)] ${reviewCount > 0 ? 'border-amber-500/40 bg-amber-500/[0.03]' : 'border-border/80 bg-card'}`}
                >
                    <div class="flex items-start justify-between">
                        <div>
                            <p
                                class="text-muted-foreground font-mono text-[11px] font-bold tracking-wider uppercase"
                            >
                                Pending Review
                            </p>
                            <h3
                                class="text-foreground mt-1 font-mono text-2xl font-extrabold tracking-tight tabular-nums sm:text-3xl"
                            >
                                {reviewCount}
                            </h3>
                        </div>
                        <div
                            class="flex size-9 items-center justify-center rounded-lg border border-amber-500/20 bg-amber-500/10 text-amber-600 dark:text-amber-400"
                        >
                            <Clock class="size-4.5" />
                        </div>
                    </div>
                    <p
                        class="text-muted-foreground mt-3 flex items-center gap-1.5 text-xs"
                    >
                        {#if reviewCount > 0}
                            <span
                                class="inline-block size-1.5 rounded-full bg-amber-500"
                            ></span>
                            <span
                                class="font-medium text-amber-600 dark:text-amber-400"
                                >Action required by head</span
                            >
                        {:else}
                            <span
                                class="bg-muted-foreground inline-block size-1.5 rounded-full"
                            ></span>
                            All drafts cleared
                        {/if}
                    </p>
                </div>

                <!-- Card 3: Internal Procedures & Staff FAQs -->
                <div
                    class="border-border/80 bg-card relative overflow-hidden rounded-xl border p-4 shadow-xs transition-all duration-200 hover:border-sky-500/30 sm:p-5 dark:shadow-[inset_0_1px_0_0_rgba(255,255,255,0.06)]"
                >
                    <div class="flex items-start justify-between">
                        <div>
                            <p
                                class="text-muted-foreground font-mono text-[11px] font-bold tracking-wider uppercase"
                            >
                                Internal Procedures
                            </p>
                            <h3
                                class="text-foreground mt-1 font-mono text-2xl font-extrabold tracking-tight tabular-nums sm:text-3xl"
                            >
                                {policyCount}
                            </h3>
                        </div>
                        <div
                            class="flex size-9 items-center justify-center rounded-lg border border-sky-500/20 bg-sky-500/10 text-sky-600 dark:text-sky-400"
                        >
                            <BookOpen class="size-4.5" />
                        </div>
                    </div>
                    <p
                        class="text-muted-foreground mt-3 flex items-center gap-1.5 text-xs"
                    >
                        <span
                            class="inline-block size-1.5 rounded-full bg-sky-500"
                        ></span>
                        Staff procedures, not public policies
                    </p>
                </div>

                <!-- Card 4: Applicant FAQs -->
                <div
                    class="border-border/80 bg-card relative overflow-hidden rounded-xl border p-4 shadow-xs transition-all duration-200 hover:border-purple-500/30 sm:p-5 dark:shadow-[inset_0_1px_0_0_rgba(255,255,255,0.06)]"
                >
                    <div class="flex items-start justify-between">
                        <div>
                            <p
                                class="text-muted-foreground font-mono text-[11px] font-bold tracking-wider uppercase"
                            >
                                Applicant FAQs
                            </p>
                            <h3
                                class="text-foreground mt-1 font-mono text-2xl font-extrabold tracking-tight tabular-nums sm:text-3xl"
                            >
                                {faqCount}
                            </h3>
                        </div>
                        <div
                            class="flex size-9 items-center justify-center rounded-lg border border-purple-500/20 bg-purple-500/10 text-purple-600 dark:text-purple-400"
                        >
                            <HelpCircle class="size-4.5" />
                        </div>
                    </div>
                    <p
                        class="text-muted-foreground mt-3 flex items-center gap-1.5 text-xs"
                    >
                        <span
                            class="inline-block size-1.5 rounded-full bg-purple-500"
                        ></span>
                        Public inquiries
                    </p>
                </div>
            </section>

            <!-- Navigation & Filter Bar -->
            <div
                class="border-border/80 bg-card space-y-4 rounded-xl border p-4 shadow-xs"
            >
                <div
                    class="flex flex-col justify-between gap-4 lg:flex-row lg:items-center"
                >
                    <!-- Segmented Lifecycle / Category Tabs -->
                    <div
                        class="bg-muted/50 border-border/50 flex flex-wrap items-center gap-1.5 rounded-lg border p-1 text-sm"
                    >
                        <button
                            type="button"
                            class={`rounded-md px-3 py-1.5 text-xs font-medium transition-all ${activeTab === 'all' ? 'bg-background text-foreground font-semibold shadow-xs' : 'text-muted-foreground hover:text-foreground'}`}
                            onclick={() => (activeTab = 'all')}
                        >
                            All Items ({totalCount})
                        </button>
                        <button
                            type="button"
                            class={`flex items-center gap-1.5 rounded-md px-3 py-1.5 text-xs font-medium transition-all ${activeTab === 'policies' ? 'bg-background text-foreground font-semibold shadow-xs' : 'text-muted-foreground hover:text-foreground'}`}
                            onclick={() => (activeTab = 'policies')}
                        >
                            <FileText class="size-3.5" />
                            Policies ({policyCount})
                        </button>
                        <button
                            type="button"
                            class={`flex items-center gap-1.5 rounded-md px-3 py-1.5 text-xs font-medium transition-all ${activeTab === 'faqs' ? 'bg-background text-foreground font-semibold shadow-xs' : 'text-muted-foreground hover:text-foreground'}`}
                            onclick={() => (activeTab = 'faqs')}
                        >
                            <HelpCircle class="size-3.5" />
                            FAQs ({faqCount})
                        </button>
                        <button
                            type="button"
                            class={`flex items-center gap-1.5 rounded-md px-3 py-1.5 text-xs font-medium transition-all ${activeTab === 'review' ? 'bg-background text-foreground font-semibold shadow-xs' : 'text-muted-foreground hover:text-foreground'}`}
                            onclick={() => (activeTab = 'review')}
                        >
                            <Clock class="size-3.5" />
                            Needs Review
                            {#if reviewCount > 0}
                                <span
                                    class="py-0.2 inline-flex items-center justify-center rounded-full bg-amber-500 px-1.5 font-mono text-[10px] font-bold text-white"
                                >
                                    {reviewCount}
                                </span>
                            {/if}
                        </button>
                    </div>

                    <!-- Search & Controls -->
                    <div class="flex flex-wrap items-center gap-2.5">
                        <!-- Search Input -->
                        <div
                            class="relative min-w-[220px] flex-1 sm:min-w-[260px] sm:flex-initial"
                        >
                            <Search
                                class="text-muted-foreground pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2"
                            />
                            <Input
                                id="knowledge-search"
                                type="search"
                                placeholder="Search title, Q&A, or text..."
                                class="min-h-9.5 pr-8 pl-9 text-xs sm:text-sm"
                                bind:value={clientSearch}
                                oninput={onSearchInput}
                            />
                            {#if clientSearch}
                                <button
                                    type="button"
                                    class="text-muted-foreground hover:text-foreground absolute top-1/2 right-2.5 -translate-y-1/2 rounded p-0.5"
                                    onclick={clearSearch}
                                    aria-label="Clear search"
                                >
                                    <X class="size-3.5" />
                                </button>
                            {/if}
                        </div>

                        <!-- Office Filter -->
                        <Select.Root
                            type="single"
                            value={filterOffice}
                            onValueChange={(v) => (filterOffice = v ?? 'all')}
                        >
                            <Select.Trigger class="min-h-9.5 w-[140px] text-xs">
                                {filterOffice === 'all'
                                    ? 'All Offices'
                                    : officeLabel(filterOffice)}
                            </Select.Trigger>
                            <Select.Content>
                                <Select.Item value="all"
                                    >All Offices</Select.Item
                                >
                                <Select.Item value="registrar"
                                    >Registrar</Select.Item
                                >
                                <Select.Item value="admin">Central Admin</Select.Item>
                                <Select.Item value="guidance"
                                    >Guidance</Select.Item
                                >
                            </Select.Content>
                        </Select.Root>

                        <!-- Status Filter -->
                        <Select.Root
                            type="single"
                            value={filterStatus}
                            onValueChange={(v) => {
                                filterStatus = v ?? '';
                                applyServerFilters();
                            }}
                        >
                            <Select.Trigger class="min-h-9.5 w-[150px] text-xs">
                                {statuses.find((s) => s.value === filterStatus)
                                    ?.label ?? 'All Statuses'}
                            </Select.Trigger>
                            <Select.Content>
                                <Select.Item value="">All Statuses</Select.Item>
                                {#each statuses as s (s.value)}
                                    <Select.Item value={s.value}
                                        >{s.label}</Select.Item
                                    >
                                {/each}
                            </Select.Content>
                        </Select.Root>
                    </div>
                </div>

                {#if clientSearch || filterOffice !== 'all' || filterStatus !== ''}
                    <div
                        class="text-muted-foreground border-border/50 flex items-center justify-between border-t pt-2 text-xs"
                    >
                        <span>
                            Showing <strong class="text-foreground"
                                >{filteredSources.length}</strong
                            >
                            of {totalCount} items
                        </span>
                        <button
                            type="button"
                            class="text-primary inline-flex items-center gap-1 font-medium hover:underline"
                            onclick={resetAllFilters}
                        >
                            <RotateCcw class="size-3" />
                            Reset filters
                        </button>
                    </div>
                {/if}
            </div>

            <!-- Main Content Area: Switchable Cards / Table -->
            {#if filteredSources.length === 0}
                <div
                    class="border-border/80 bg-card space-y-4 rounded-xl border border-dashed p-10 text-center"
                >
                    <div
                        class="bg-muted/60 text-muted-foreground mx-auto flex size-12 items-center justify-center rounded-full"
                    >
                        <BookOpen class="size-6" />
                    </div>
                    <div class="space-y-1">
                        <h3 class="text-foreground text-base font-semibold">
                            {clientSearch ||
                            filterOffice !== 'all' ||
                            filterStatus !== ''
                                ? 'No matching policies or FAQs found'
                                : 'No knowledge sources established yet'}
                        </h3>
                        <p
                            class="text-muted-foreground mx-auto max-w-md text-xs sm:text-sm"
                        >
                            {clientSearch ||
                            filterOffice !== 'all' ||
                            filterStatus !== ''
                                ? 'Try broadening your search term or resetting the office/status filters.'
                                : 'Draft an internal staff procedure or explanatory FAQ for review. Canonical public policies are managed in Communications.'}
                        </p>
                    </div>
                    {#if clientSearch || filterOffice !== 'all' || filterStatus !== ''}
                        <Button
                            variant="outline"
                            size="sm"
                            onclick={resetAllFilters}
                            class="gap-1.5 text-xs"
                        >
                            <RotateCcw class="size-3.5" />
                            Clear all filters
                        </Button>
                    {:else if capabilities.manage}
                        <div
                            class="flex items-center justify-center gap-3 pt-2"
                        >
                            <Link href="/knowledge/create?type=policy">
                                <Button
                                    variant="outline"
                                    size="sm"
                                    class="gap-1.5"
                                >
                                    <FileText class="size-4" />
                                    Add First Procedure
                                </Button>
                            </Link>
                            <Link href="/knowledge/create?type=faq">
                                <Button size="sm" class="gap-1.5">
                                    <Plus class="size-4" />
                                    Add First FAQ
                                </Button>
                            </Link>
                        </div>
                    {/if}
                </div>
            {:else}
                <SwitchableListView bind:viewMode>
                    <!-- Table Presentation -->
                    {#snippet table()}
                        <div
                            class="border-border/80 bg-card overflow-hidden rounded-xl border shadow-xs"
                        >
                            <Table.Root class="w-full text-sm">
                                <Table.Header class="bg-muted/40 text-xs">
                                    <Table.Row>
                                        <Table.Head
                                            class="text-foreground px-4 py-3.5 font-semibold"
                                            >Document / Question</Table.Head
                                        >
                                        <Table.Head
                                            class="text-foreground px-4 py-3.5 font-semibold"
                                            >Type</Table.Head
                                        >
                                        <Table.Head
                                            class="text-foreground px-4 py-3.5 font-semibold"
                                            >Scope & Office</Table.Head
                                        >
                                        <Table.Head
                                            class="text-foreground px-4 py-3.5 font-semibold"
                                            >Governance</Table.Head
                                        >
                                        <Table.Head
                                            class="text-foreground px-4 py-3.5 font-semibold"
                                            >Updated</Table.Head
                                        >
                                        <Table.Head
                                            class="text-foreground px-4 py-3.5 text-right font-semibold"
                                            >Actions</Table.Head
                                        >
                                    </Table.Row>
                                </Table.Header>
                                <Table.Body>
                                    {#each filteredSources as row (row.id)}
                                        {@const status = statusConfig(
                                            row.latest_version?.status,
                                        )}
                                        {@const aud = audienceBadge(
                                            row.audience,
                                        )}
                                        {@const isFaq =
                                            row.source_class === 'public_faq'}
                                        <Table.Row
                                            class="hover:bg-muted/30 group transition-colors"
                                        >
                                            <Table.Cell
                                                class="max-w-md px-4 py-3.5 align-top"
                                            >
                                                <div class="space-y-1">
                                                    <Link
                                                        href={`/knowledge/${row.id}`}
                                                        class="text-foreground hover:text-primary block font-medium transition-colors"
                                                    >
                                                        {#if isFaq && row.latest_version?.question}
                                                            {row.latest_version
                                                                .question}
                                                        {:else}
                                                            {row.title}
                                                        {/if}
                                                    </Link>
                                                    <div
                                                        class="text-muted-foreground flex items-center gap-2 text-xs"
                                                    >
                                                        <span
                                                            class="bg-muted/60 border-border/60 rounded border px-1.5 py-0.5 font-mono text-[11px]"
                                                            >{row.key}</span
                                                        >
                                                    </div>
                                                    {#if isFaq && row.latest_version?.answer}
                                                        <p
                                                            class="text-muted-foreground mt-1 line-clamp-1 text-xs"
                                                        >
                                                            A: {row
                                                                .latest_version
                                                                .answer}
                                                        </p>
                                                    {:else if row.latest_version?.body_text}
                                                        <p
                                                            class="text-muted-foreground mt-1 line-clamp-1 text-xs"
                                                        >
                                                            {row.latest_version
                                                                .body_text}
                                                        </p>
                                                    {/if}
                                                </div>
                                            </Table.Cell>
                                            <Table.Cell
                                                class="px-4 py-3.5 align-top whitespace-nowrap"
                                            >
                                                <span
                                                    class="bg-muted/60 border-border/60 inline-flex items-center gap-1.5 rounded-md border px-2 py-1 text-xs font-medium"
                                                >
                                                    {#if isFaq}
                                                        <HelpCircle
                                                            class="size-3.5 text-purple-600 dark:text-purple-400"
                                                        />
                                                        FAQ
                                                    {:else}
                                                        <FileText
                                                            class="size-3.5 text-sky-600 dark:text-sky-400"
                                                        />
                                                        Policy
                                                    {/if}
                                                </span>
                                            </Table.Cell>
                                            <Table.Cell
                                                class="px-4 py-3.5 align-top whitespace-nowrap"
                                            >
                                                <div class="space-y-1">
                                                    <div
                                                        class="text-foreground flex items-center gap-1.5 text-xs font-medium"
                                                    >
                                                        <span
                                                            class="bg-primary size-1.5 rounded-full"
                                                        ></span>
                                                        {officeLabel(
                                                            row.owning_office,
                                                        )}
                                                    </div>
                                                    <span
                                                        class={`inline-flex items-center gap-1 rounded border px-2 py-0.5 text-[11px] ${aud.class}`}
                                                    >
                                                        <aud.icon
                                                            class="size-3"
                                                        />
                                                        {aud.label}
                                                    </span>
                                                    {#if canEditSource(row)}
                                                        <span class="inline-flex w-fit rounded border border-emerald-500/20 bg-emerald-500/10 px-1.5 py-0.5 text-[10px] font-medium text-emerald-700 dark:text-emerald-300">Your office · editable</span>
                                                    {:else}
                                                        <span class="border-border/70 bg-muted/50 text-muted-foreground inline-flex w-fit rounded border px-1.5 py-0.5 text-[10px] font-medium">Other office · view only</span>
                                                    {/if}
                                                </div>
                                            </Table.Cell>
                                            <Table.Cell
                                                class="px-4 py-3.5 align-top whitespace-nowrap"
                                            >
                                                <div class="space-y-1">
                                                    <span
                                                        class={`inline-flex items-center gap-1.5 rounded-full border px-2.5 py-1 text-xs font-medium ${status.badgeClass}`}
                                                    >
                                                        <status.icon
                                                            class="size-3"
                                                        />
                                                        {status.label}
                                                    </span>
                                                    <div
                                                        class="text-muted-foreground font-mono text-[11px]"
                                                    >
                                                        v{row.latest_version
                                                            ?.version ?? 1}
                                                    </div>
                                                    {#if consensusLabel(row).length > 0}
                                                        <div class="flex flex-wrap gap-1">
                                                            {#each consensusLabel(row) as signature (signature)}
                                                                <span class="inline-flex rounded border border-indigo-500/20 bg-indigo-500/10 px-1.5 py-0.5 text-[10px] font-medium text-indigo-700 dark:text-indigo-300">
                                                                    {signature}
                                                                </span>
                                                            {/each}
                                                        </div>
                                                    {/if}
                                                    {#if needsPolicyReview(row)}
                                                        <span class="inline-flex items-center gap-1 rounded-full border border-amber-500/30 bg-amber-500/10 px-2 py-0.5 text-[10px] font-semibold text-amber-700 dark:text-amber-300">
                                                            <Clock class="size-3" />
                                                            Policy needs review
                                                        </span>
                                                    {/if}
                                                </div>
                                            </Table.Cell>
                                            <Table.Cell
                                                class="text-muted-foreground px-4 py-3.5 align-top text-xs whitespace-nowrap"
                                            >
                                                {formatDate(
                                                    row.latest_version
                                                        ?.updated_at ??
                                                        row.updated_at,
                                                )}
                                            </Table.Cell>
                                            <Table.Cell
                                                class="px-4 py-3.5 text-right align-top whitespace-nowrap"
                                            >
                                                <Link
                                                    href={`/knowledge/${row.id}`}
                                                >
                                                    <Button
                                                        variant="ghost"
                                                        size="sm"
                                                        class="hover:text-primary h-8 gap-1 text-xs"
                                                    >
                                                        {#if canEditSource(row)}
                                                            <span>Edit</span>
                                                            <ArrowRight
                                                                class="size-3.5"
                                                            />
                                                        {:else}
                                                            <Eye class="size-3.5" />
                                                            <span>View Only</span>
                                                        {/if}
                                                    </Button>
                                                </Link>
                                            </Table.Cell>
                                        </Table.Row>
                                    {/each}
                                </Table.Body>
                            </Table.Root>
                        </div>
                    {/snippet}

                    <!-- Cards Presentation -->
                    {#snippet cards()}
                        <ul
                            class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-3"
                        >
                            {#each filteredSources as item (item.id)}
                                {@const status = statusConfig(
                                    item.latest_version?.status,
                                )}
                                {@const aud = audienceBadge(item.audience)}
                                {@const isFaq =
                                    item.source_class === 'public_faq'}
                                {@const isExpanded = expandedCardId === item.id}
                                <li
                                    class="group border-border/80 bg-card hover:border-primary/40 flex flex-col justify-between rounded-xl border p-5 shadow-xs transition-all duration-200 hover:shadow-md dark:shadow-[inset_0_1px_0_0_rgba(255,255,255,0.06)]"
                                >
                                    <div class="space-y-3.5">
                                        <!-- Top Badge Bar -->
                                        <div
                                            class="flex items-center justify-between gap-2"
                                        >
                                            <div
                                                class="flex items-center gap-1.5"
                                            >
                                                <span
                                                    class="bg-muted/80 text-foreground border-border/60 inline-flex items-center gap-1 rounded-md border px-2 py-0.5 text-[11px] font-semibold tracking-wider uppercase"
                                                >
                                                    {#if isFaq}
                                                        <HelpCircle
                                                            class="size-3 text-purple-600 dark:text-purple-400"
                                                        />
                                                        FAQ
                                                    {:else}
                                                        <FileText
                                                            class="size-3 text-sky-600 dark:text-sky-400"
                                                        />
                                                        Policy
                                                    {/if}
                                                </span>
                                                <span
                                                    class="text-muted-foreground text-xs font-medium"
                                                >
                                                    {officeLabel(
                                                        item.owning_office,
                                                    )}
                                                </span>
                                            </div>
                                            <span
                                                class={`inline-flex items-center gap-1 rounded-full border px-2 py-0.5 text-[11px] font-semibold ${status.badgeClass}`}
                                            >
                                                <status.icon class="size-3" />
                                                {status.label}
                                            </span>
                                        </div>

                                        <!-- Title / Question -->
                                        <div class="space-y-1">
                                            <Link
                                                href={`/knowledge/${item.id}`}
                                                class="block"
                                            >
                                                <h4
                                                    class="text-foreground group-hover:text-primary text-sm leading-snug font-bold transition-colors"
                                                >
                                                    {#if isFaq && item.latest_version?.question}
                                                        {item.latest_version
                                                            .question}
                                                    {:else}
                                                        {item.title}
                                                    {/if}
                                                </h4>
                                            </Link>
                                            <div
                                                class="flex flex-wrap items-center gap-2"
                                            >
                                                <span
                                                    class="text-muted-foreground/80 bg-muted/40 rounded px-1.5 py-0.5 font-mono text-[10px]"
                                                >
                                                    {item.key}
                                                </span>
                                                <span
                                                    class={`py-0.2 inline-flex items-center gap-1 rounded border px-1.5 text-[10px] ${aud.class}`}
                                                >
                                                    <aud.icon
                                                        class="size-2.5"
                                                    />
                                                    {aud.label}
                                                </span>
                                                {#if canEditSource(item)}
                                                    <span class="inline-flex w-fit rounded border border-emerald-500/20 bg-emerald-500/10 px-1.5 py-0.5 text-[10px] font-medium text-emerald-700 dark:text-emerald-300">Your office · editable</span>
                                                {:else}
                                                    <span class="border-border/70 bg-muted/50 text-muted-foreground inline-flex w-fit rounded border px-1.5 py-0.5 text-[10px] font-medium">Other office · view only</span>
                                                {/if}
                                                {#if consensusLabel(item).length > 0}
                                                    <div class="flex flex-wrap gap-1">
                                                        {#each consensusLabel(item) as signature (signature)}
                                                            <span class="inline-flex rounded border border-indigo-500/20 bg-indigo-500/10 px-1.5 py-0.5 text-[10px] font-medium text-indigo-700 dark:text-indigo-300">
                                                                {signature}
                                                            </span>
                                                        {/each}
                                                    </div>
                                                {/if}
                                                {#if needsPolicyReview(item)}
                                                    <span class="inline-flex items-center gap-1 rounded-full border border-amber-500/30 bg-amber-500/10 px-2 py-0.5 text-[10px] font-semibold text-amber-700 dark:text-amber-300">
                                                        <Clock class="size-3" />
                                                        Policy needs review
                                                    </span>
                                                {/if}
                                            </div>
                                            </div>

                                        <!-- Excerpt Preview -->
                                        {#if isFaq && item.latest_version?.answer}
                                            <div
                                                class="bg-muted/30 border-border/40 text-muted-foreground rounded-lg border p-2.5 text-xs"
                                            >
                                                <p
                                                    class="text-foreground/90 mb-1 text-[11px] font-semibold tracking-wider uppercase"
                                                >
                                                    Answer Preview
                                                </p>
                                                <p
                                                    class={isExpanded
                                                        ? 'whitespace-pre-wrap'
                                                        : 'line-clamp-3 leading-relaxed'}
                                                >
                                                    {item.latest_version.answer}
                                                </p>
                                            </div>
                                        {:else if item.latest_version?.body_text}
                                            <div
                                                class="bg-muted/30 border-border/40 text-muted-foreground rounded-lg border p-2.5 text-xs"
                                            >
                                                <p
                                                    class="text-foreground/90 mb-1 text-[11px] font-semibold tracking-wider uppercase"
                                                >
                                                    Policy Scope
                                                </p>
                                                <p
                                                    class={isExpanded
                                                        ? 'whitespace-pre-wrap'
                                                        : 'line-clamp-3 leading-relaxed'}
                                                >
                                                    {item.latest_version
                                                        .body_text}
                                                </p>
                                            </div>
                                        {/if}
                                    </div>

                                    <!-- Footer -->
                                    <div
                                        class="border-border/60 mt-4 flex items-center justify-between border-t pt-3 text-xs"
                                    >
                                        <div
                                            class="text-muted-foreground flex items-center gap-2 font-mono text-[11px]"
                                        >
                                            <span
                                                >v{item.latest_version
                                                    ?.version ?? 1}</span
                                            >
                                            <span>•</span>
                                            <span
                                                >{formatDate(
                                                    item.latest_version
                                                        ?.updated_at ??
                                                        item.updated_at,
                                                )}</span
                                            >
                                        </div>
                                        <div class="flex items-center gap-1.5">
                                            {#if (isFaq && item.latest_version?.answer?.length > 120) || (!isFaq && item.latest_version?.body_text?.length > 120)}
                                                <Button
                                                    variant="ghost"
                                                    size="sm"
                                                    class="text-muted-foreground hover:text-foreground h-7 px-2 text-[11px]"
                                                    onclick={() =>
                                                        toggleCardExpand(
                                                            item.id,
                                                        )}
                                                >
                                                    {isExpanded
                                                        ? 'Show less'
                                                        : 'Quick read'}
                                                </Button>
                                            {/if}
                                            <Link
                                                href={`/knowledge/${item.id}`}
                                            >
                                                <Button
                                                    size="sm"
                                                    variant="outline"
                                                    class="group-hover:border-primary/50 h-7.5 gap-1 px-2.5 text-xs"
                                                >
                                                    {#if canEditSource(item)}
                                                        <span>Edit</span>
                                                        <ArrowRight class="size-3" />
                                                    {:else}
                                                        <Eye class="size-3" />
                                                        <span>View Only</span>
                                                    {/if}
                                                </Button>
                                            </Link>
                                        </div>
                                    </div>
                                </li>
                            {/each}
                        </ul>
                    {/snippet}
                </SwitchableListView>
            {/if}
        {/if}
    </div>
</StaffLayout>
