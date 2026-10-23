<script>
    /**
     * Knowledge Source Detail & Governance Inspector (GF-22, AD-25).
     * Verified review, cryptographic approval evidence, and publication control.
     */
    import StaffLayout from '@/Pages/Staff/StaffLayout.svelte';
    import PageHeaderBar from '@/Components/kit/PageHeaderBar.svelte';
    import { Badge } from '@/Components/ui/badge';
    import { Button } from '@/Components/ui/button';
    import { Input } from '@/Components/ui/input';
    import * as Table from '@/Components/ui/table';
    import { Link, router, usePage } from '@inertiajs/svelte';
    import {
        AlertCircle,
        ArrowLeft,
        Ban,
        BookOpen,
        Bot,
        Check,
        CheckCircle2,
        Clock,
        Copy,
        ExternalLink,
        FileCheck,
        FileText,
        Globe,
        HelpCircle,
        Key,
        Lock,
        RefreshCw,
        Send,
        Shield,
        ShieldAlert,
        ShieldCheck,
        Sparkles,
        UserCheck,
        Users,
    } from 'lucide-svelte';

    let {
        page_state = 'ready',
        source = {},
        version = null,
        message = null,
        current_office = null,
        consensusSignatures: pageConsensusSignatures = null,
        governingPolicyDependencies: pageGoverningPolicyDependencies = null,
        requiredOffices: pageRequiredOffices = [],
        capabilities = {
            manage: false,
            approve: false,
            approve_current_version: false,
            cross_office_approve: false,
            establish_cross_office: false,
            establish_cross_office_current_version: false,
            publish_current_version: false,
            revoke_current_version: false,
            extract_current_version: false,
            can_recertify: false,
        },
    } = $props();

    const page = usePage();
    const flashStatus = $derived(page.props.flash?.status ?? null);
    const flashError = $derived(page.props.flash?.error ?? null);
    const isCrossOffice = $derived(source?.audience === 'cross_office_staff');
    const isFaq = $derived(source?.source_class === 'public_faq');
    const officeApprovals = $derived(
        Array.isArray(version?.cross_office_approvals)
            ? version.cross_office_approvals
            : [],
    );
    const consensusSignatures = $derived(
        Array.isArray(pageConsensusSignatures)
            ? pageConsensusSignatures
            : Array.isArray(source?.consensus_signatures)
              ? source.consensus_signatures
              : Array.isArray(version?.consensus_signatures)
                ? version.consensus_signatures
                : [],
    );
    const governingPolicyDependencies = $derived(
        Array.isArray(pageGoverningPolicyDependencies)
            ? pageGoverningPolicyDependencies
            : Array.isArray(source?.governing_policy_dependencies)
              ? source.governing_policy_dependencies
              : Array.isArray(version?.governing_policy_dependencies)
                ? version.governing_policy_dependencies
                : [],
    );
    const needsPolicyRecertification = $derived(
        governingPolicyDependencies.some(
            (dependency) => dependency.status === 'needs_review',
        ),
    );
    const consensusChecklist = $derived.by(() => {
        const offices =
            pageRequiredOffices.length > 0
                ? pageRequiredOffices
                : Array.isArray(source?.participating_offices) &&
                    source.participating_offices.length > 0
                  ? source.participating_offices
                  : consensusSignatures.map((signature) => signature.office);

        return offices.map((office) => {
            const signature = consensusSignatures.find(
                (item) =>
                    normalizeOffice(item.office) === normalizeOffice(office),
            );

            return {
                office,
                signed: signature?.signed === true,
                signerName: signature?.signer_name ?? null,
            };
        });
    });
    const requiredJointOffices = [
        { key: 'registrar', label: 'Registrar Office' },
        { key: 'guidance', label: 'Guidance Office' },
    ];

    const signedJointOfficeCount = $derived(
        requiredJointOffices.filter((office) => approvalForOffice(office.key))
            .length,
    );
    const jointApprovalComplete = $derived(
        signedJointOfficeCount === requiredJointOffices.length,
    );

    const canApproveVersion = $derived(
        isCrossOffice
            ? capabilities?.cross_office_approve === true
            : capabilities?.approve_current_version === true,
    );

    const canEstablishCrossOffice = $derived(
        source?.audience === 'staff_only' &&
            version?.status === 'review_required' &&
            capabilities?.establish_cross_office_current_version === true,
    );

    const canPublishVersion = $derived(
        capabilities?.approve === true &&
            ['approved', 'index_failed'].includes(version?.status) &&
            (!isCrossOffice || jointApprovalComplete),
    );

    const canExtract = $derived(
        capabilities?.manage &&
            ['draft', 'rejected', 'index_failed'].includes(version?.status),
    );

    const owningOffice = $derived(officeLabel(source?.owning_office));

    let revokeReason = $state('');
    let copiedDigest = $state(false);

    function copyDigest(text) {
        if (!text) return;
        navigator.clipboard.writeText(text);
        copiedDigest = true;
        setTimeout(() => (copiedDigest = false), 2000);
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
        const normalized = String(value ?? '').toLowerCase();

        if (normalized.includes('registrar')) return 'registrar';
        if (normalized.includes('guidance')) return 'guidance';
        return normalized;
    }

    function approvalForOffice(officeKey) {
        return (
            officeApprovals.find(
                (approval) => normalizeOffice(approval.office) === officeKey,
            ) ?? null
        );
    }

    function formatTimestamp(value) {
        if (typeof value !== 'string' || value === '') return 'Not recorded';
        const timestamp = new Date(value);
        if (Number.isNaN(timestamp.getTime())) return value;
        return new Intl.DateTimeFormat('en-US', {
            month: 'short',
            day: 'numeric',
            year: 'numeric',
            hour: 'numeric',
            minute: '2-digit',
        }).format(timestamp);
    }

    function statusConfig(status) {
        switch (status) {
            case 'active':
                return {
                    label: 'Active & Published',
                    badgeClass:
                        'bg-emerald-500/10 text-emerald-700 dark:text-emerald-400 border-emerald-500/20',
                    dotClass: 'bg-emerald-500',
                    icon: Sparkles,
                    desc: 'Live in Xiam RAG retrieval and cited for applicant queries.',
                };
            case 'review_required':
                return {
                    label: 'Needs Review',
                    badgeClass:
                        'bg-amber-500/10 text-amber-700 dark:text-amber-400 border-amber-500/20',
                    dotClass: 'bg-amber-500',
                    icon: Clock,
                    desc: 'Awaiting independent office-head review and cryptographic signoff.',
                };
            case 'approved':
                return {
                    label: 'Approved (Ready to Publish)',
                    badgeClass:
                        'bg-sky-500/10 text-sky-700 dark:text-sky-400 border-sky-500/20',
                    dotClass: 'bg-sky-500',
                    icon: ShieldCheck,
                    desc: 'Signed off. An authorized approver can now publish this version to the corpus.',
                };
            case 'draft':
                return {
                    label: 'Draft',
                    badgeClass:
                        'bg-slate-500/10 text-slate-700 dark:text-slate-300 border-slate-500/20',
                    dotClass: 'bg-slate-400',
                    icon: FileText,
                    desc: 'Draft version. Extraction must be initiated to prepare digest fixtures.',
                };
            case 'extracting':
            case 'indexing':
                return {
                    label:
                        status === 'indexing'
                            ? 'Indexing into Corpus...'
                            : 'Extracting Knowledge...',
                    badgeClass:
                        'bg-purple-500/10 text-purple-700 dark:text-purple-400 border-purple-500/20 animate-pulse',
                    dotClass: 'bg-purple-500',
                    icon: RefreshCw,
                    desc: 'Background processing in progress. Please refresh in a few moments.',
                };
            case 'revoked':
                return {
                    label: 'Revoked',
                    badgeClass:
                        'bg-rose-500/10 text-rose-700 dark:text-rose-400 border-rose-500/20',
                    dotClass: 'bg-rose-500',
                    icon: Ban,
                    desc: 'Revoked by authority. Excluded from all search indices and retrieval.',
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
                    desc: 'Version could not be verified or processed. Review the error details below.',
                };
            default:
                return {
                    label: status ? status.replaceAll('_', ' ') : 'Unknown',
                    badgeClass: 'bg-muted text-muted-foreground border-border',
                    dotClass: 'bg-muted-foreground',
                    icon: FileText,
                    desc: 'Lifecycle status unrecognized.',
                };
        }
    }

    // Stepper state
    const currentStep = $derived.by(() => {
        if (!version) return 1;
        if (version.status === 'draft') return 1;
        if (['extracting', 'review_required'].includes(version.status))
            return 2;
        if (version.status === 'approved') return 3;
        if (version.status === 'active') return 4;
        return 2;
    });

    function extract() {
        router.post(`/knowledge/${source.id}/extract`, {
            version_id: version.id,
        });
    }

    function establishCrossOffice() {
        router.post(`/knowledge/${source.id}/cross-office`, {
            version_id: version.id,
        });
    }

    function approve() {
        router.post(`/knowledge/${source.id}/approve`, {
            version_id: version.id,
        });
    }

    function publish() {
        router.post(`/knowledge/${source.id}/publish`, {
            version_id: version.id,
        });
    }

    function revoke() {
        if (
            !confirm(
                'Revoke this active version? Knowledge retrieval will deny immediately.',
            )
        )
            return;
        router.post(`/knowledge/${source.id}/revoke`, {
            version_id: version.id,
            reason: revokeReason || 'revoked',
        });
    }
    function recertifyPolicyDependency() {
        router.post(route('knowledge.recertify', source.id), {
            version_id: version.id,
        });
    }

    const breadcrumbs = [
        { label: 'Setup', href: '/setup' },
        { label: 'FAQs & Internal Procedures', href: '/knowledge' },
        {
            label:
                page_state === 'denied'
                    ? 'Unavailable'
                    : (source?.title ?? 'Item'),
        },
    ];
</script>

<svelte:head>
    <title
        >{page_state === 'denied'
            ? 'Source Unavailable'
            : (source?.title ?? 'Knowledge Detail')} · Xiam Knowledge & Rules · NEXIAM</title
    >
</svelte:head>

<StaffLayout {breadcrumbs}>
    <div class="mx-auto max-w-7xl min-w-0 space-y-6 p-4 sm:p-6 lg:p-8">
        <!-- Top Return & Action Bar -->
        <div
            class="border-border/70 flex flex-col justify-between gap-4 border-b pb-4 sm:flex-row sm:items-center"
        >
            <div class="flex items-center gap-3">
                <Link href="/knowledge">
                    <Button
                        variant="outline"
                        size="sm"
                        class="h-9 gap-1.5 text-xs font-medium"
                    >
                        <ArrowLeft class="size-3.5" />
                        FAQs & Internal Procedures
                    </Button>
                </Link>
                <div class="bg-border/80 h-4 w-px"></div>
                <span
                    class="text-muted-foreground bg-muted/60 border-border/60 rounded border px-2 py-0.5 font-mono text-xs"
                >
                    {source?.key ?? '—'}
                </span>
            </div>

            <!-- Quick Action Buttons -->
            {#if version && page_state === 'ready'}
                <div class="flex flex-wrap items-center gap-2">
                    {#if canExtract}
                        <Button
                            size="sm"
                            variant="outline"
                            class="min-h-9 gap-1.5 text-xs"
                            onclick={extract}
                        >
                            <RefreshCw class="size-3.5" />
                            Run Extraction
                        </Button>
                    {/if}

                    {#if source.audience === 'staff_only' && version.status === 'review_required' && canEstablishCrossOffice}
                        <Button
                            size="sm"
                            variant="outline"
                            class="min-h-9 gap-1.5 text-xs"
                            onclick={establishCrossOffice}
                        >
                            <Users class="size-3.5" />
                            Establish Cross-Office Scope
                        </Button>
                    {/if}

                    {#if version.status === 'review_required' && canApproveVersion}
                        <Button
                            size="sm"
                            class="min-h-9 gap-1.5 bg-amber-600 text-xs text-white shadow-xs hover:bg-amber-700"
                            onclick={approve}
                        >
                            <CheckCircle2 class="size-3.5" />
                            {isCrossOffice
                                ? 'Sign For My Office'
                                : 'Approve Version'}
                        </Button>
                    {/if}


                    {#if canPublishVersion}
                        <Button
                            size="sm"
                            class="min-h-9 gap-1.5 bg-emerald-600 text-xs text-white shadow-xs hover:bg-emerald-700"
                            onclick={publish}
                        >
                            <Send class="size-3.5" />
                            Publish to Active Corpus
                        </Button>
                    {/if}
                </div>
            {/if}
        </div>

        <!-- Flash Messages -->
        {#if flashStatus}
            <div
                role="status"
                class="flex items-center gap-2 rounded-xl border border-emerald-500/30 bg-emerald-500/10 px-4 py-3 text-sm text-emerald-800 dark:text-emerald-300"
            >
                <CheckCircle2 class="size-4 shrink-0" />
                <span>{flashStatus}</span>
            </div>
        {/if}
        {#if flashError}
            <div
                role="alert"
                class="border-destructive/40 bg-destructive/10 text-destructive flex items-center gap-2 rounded-xl border px-4 py-3 text-sm"
            >
                <AlertCircle class="size-4 shrink-0" />
                <span>{flashError}</span>
            </div>
        {/if}

        <!-- Page Errors -->
        {#if page_state === 'denied'}
            <div
                role="alert"
                class="border-destructive/30 bg-destructive/5 space-y-3 rounded-xl border p-8 text-center"
            >
                <Lock class="text-destructive/80 mx-auto size-9" />
                <h3 class="text-foreground text-base font-semibold">
                    Access Restricted
                </h3>
                <p class="text-muted-foreground mx-auto max-w-md text-sm">
                    You do not have authorization to view this knowledge source
                    or its owning office scope.
                </p>
            </div>
        {:else if page_state === 'conflict'}
            <div
                role="alert"
                class="rounded-xl border border-amber-500/40 bg-amber-500/10 p-4 text-sm text-amber-800 dark:text-amber-200"
            >
                <p class="font-medium">Version conflict detected</p>
                <p class="mt-1 text-xs">
                    This source was updated by another staff member while you
                    were inspecting it. Please refresh before proceeding.
                </p>
            </div>
        {:else if ['failed', 'error'].includes(page_state)}
            <div
                role="alert"
                class="border-destructive/40 bg-destructive/5 text-destructive rounded-xl border p-4 text-sm"
            >
                {message ?? 'Knowledge source details could not be loaded.'}
            </div>
        {:else if !version}
            <div
                class="border-border bg-card text-muted-foreground rounded-xl border p-6 text-center text-sm"
            >
                No active version is available for this knowledge source.
            </div>
        {:else}
            {@const status = statusConfig(version.status)}

            <!-- Header Card with Governance Stepper -->
            <section
                class="border-border/80 bg-card space-y-6 rounded-2xl border p-5 shadow-xs sm:p-6 dark:shadow-[inset_0_1px_0_0_rgba(255,255,255,0.06)]"
            >
                <div
                    class="flex flex-col justify-between gap-4 lg:flex-row lg:items-center"
                >
                    <div class="space-y-1.5">
                        <div class="flex items-center gap-2">
                            <span
                                class="bg-muted/80 text-foreground border-border/60 inline-flex items-center gap-1 rounded-md border px-2 py-0.5 text-[11px] font-semibold tracking-wider uppercase"
                            >
                                {#if isFaq}
                                    <HelpCircle
                                        class="size-3 text-purple-600 dark:text-purple-400"
                                    />
                                    Applicant FAQ
                                {:else}
                                    <FileText
                                        class="size-3 text-sky-600 dark:text-sky-400"
                                    />
                                    Internal Procedure
                                {/if}
                            </span>
                            <span
                                class="text-muted-foreground text-xs font-medium"
                            >
                                {owningOffice} Office
                            </span>
                        </div>
                        <h1
                            class="text-foreground text-xl font-bold tracking-tight sm:text-2xl"
                        >
                            {source?.title ?? 'Untitled Source'}
                        </h1>
                        <p class="text-muted-foreground text-xs">
                            {status.desc}
                        </p>
                    </div>

                    <div class="flex items-center gap-3">
                        <span
                            class={`inline-flex items-center gap-1.5 rounded-full border px-3 py-1 text-xs font-semibold ${status.badgeClass}`}
                        >
                            <status.icon class="size-3.5" />
                            {status.label}
                        </span>
                        <span
                            class="bg-muted/60 border-border/60 text-muted-foreground rounded border px-2 py-1 font-mono text-xs"
                        >
                            v{version.version}
                        </span>
                    </div>
                </div>

                <!-- 4-Stage Governance Stepper -->
                <div class="border-border/60 border-t pt-4">
                    <p
                        class="text-muted-foreground mb-3 font-mono text-[11px] font-bold tracking-wider uppercase"
                    >
                        Governance Lifecycle Pipeline
                    </p>
                    <div
                        class="grid grid-cols-2 gap-2.5 text-xs sm:gap-3 md:grid-cols-4"
                    >
                        <!-- Stage 1 -->
                        <div
                            class="flex items-center gap-2.5 rounded-xl border border-emerald-500/20 bg-emerald-500/5 p-3 text-emerald-700 dark:text-emerald-300"
                        >
                            <div
                                class="flex size-6 shrink-0 items-center justify-center rounded-full bg-emerald-500/20"
                            >
                                <Check
                                    class="size-3.5 text-emerald-600 dark:text-emerald-400"
                                />
                            </div>
                            <div class="min-w-0">
                                <p class="text-foreground font-semibold">
                                    1. Draft Created
                                </p>
                                <p
                                    class="text-muted-foreground truncate text-[10px]"
                                >
                                    Authoring complete
                                </p>
                            </div>
                        </div>

                        <!-- Stage 2 -->
                        <div
                            class={`flex items-center gap-2.5 rounded-xl border p-3 ${currentStep >= 2 ? 'border-emerald-500/20 bg-emerald-500/5 text-emerald-700 dark:text-emerald-300' : 'bg-muted/30 border-border/60 text-muted-foreground'}`}
                        >
                            <div
                                class={`flex size-6 shrink-0 items-center justify-center rounded-full ${currentStep >= 2 ? 'bg-emerald-500/20 text-emerald-600 dark:text-emerald-400' : 'bg-muted text-muted-foreground'}`}
                            >
                                {#if currentStep > 2}
                                    <Check class="size-3.5" />
                                {:else if currentStep === 2}
                                    <span
                                        class="size-2 animate-ping rounded-full bg-amber-500"
                                    ></span>
                                {:else}
                                    <span class="font-mono text-[10px]">2</span>
                                {/if}
                            </div>
                            <div class="min-w-0">
                                <p class="text-foreground font-semibold">
                                    2. Extraction & Digest
                                </p>
                                <p
                                    class="text-muted-foreground truncate text-[10px]"
                                >
                                    Digest fixture sealed
                                </p>
                            </div>
                        </div>

                        <!-- Stage 3 -->
                        <div
                            class={`flex items-center gap-2.5 rounded-xl border p-3 ${currentStep >= 3 ? 'border-emerald-500/20 bg-emerald-500/5 text-emerald-700 dark:text-emerald-300' : currentStep === 2 ? 'border-amber-500/30 bg-amber-500/5' : 'bg-muted/30 border-border/60 text-muted-foreground'}`}
                        >
                            <div
                                class={`flex size-6 shrink-0 items-center justify-center rounded-full ${currentStep >= 3 ? 'bg-emerald-500/20 text-emerald-600 dark:text-emerald-400' : currentStep === 2 ? 'bg-amber-500/20 text-amber-600' : 'bg-muted text-muted-foreground'}`}
                            >
                                {#if currentStep >= 3}
                                    <Check class="size-3.5" />
                                {:else if currentStep === 2}
                                    <Clock class="size-3.5" />
                                {:else}
                                    <span class="font-mono text-[10px]">3</span>
                                {/if}
                            </div>
                            <div class="min-w-0">
                                <p class="text-foreground font-semibold">
                                    3. Office Review
                                </p>
                                <p
                                    class="text-muted-foreground truncate text-[10px]"
                                >
                                    {isCrossOffice
                                        ? `${signedJointOfficeCount}/2 Signatures`
                                        : 'Supervisor signoff'}
                                </p>
                            </div>
                        </div>

                        <!-- Stage 4 -->
                        <div
                            class={`flex items-center gap-2.5 rounded-xl border p-3 ${currentStep >= 4 ? 'border-emerald-500/30 bg-emerald-500/10 text-emerald-700 dark:text-emerald-300' : 'bg-muted/30 border-border/60 text-muted-foreground'}`}
                        >
                            <div
                                class={`flex size-6 shrink-0 items-center justify-center rounded-full ${currentStep >= 4 ? 'bg-emerald-500/20 text-emerald-600 dark:text-emerald-400' : 'bg-muted text-muted-foreground'}`}
                            >
                                {#if currentStep >= 4}
                                    <Sparkles class="size-3.5" />
                                {:else}
                                    <span class="font-mono text-[10px]">4</span>
                                {/if}
                            </div>
                            <div class="min-w-0">
                                <p class="text-foreground font-semibold">
                                    4. Active Corpus
                                </p>
                                <p
                                    class="text-muted-foreground truncate text-[10px]"
                                >
                                    Live in Xiam RAG
                                </p>
                            </div>
                        </div>
                    </div>
                </div>
            </section>

            <!-- Two-Column Main Layout -->
            <div class="grid grid-cols-1 items-start gap-6 lg:grid-cols-12">
                <div class="space-y-6 lg:col-span-8">
                    {#if isFaq}
                        <!-- Official Question & Answer Cards -->
                        <div
                            class="border-border/80 bg-card space-y-5 rounded-xl border p-5 shadow-xs sm:p-6"
                        >
                            <div class="space-y-2">
                                <div class="flex items-center gap-2">
                                    <div
                                        class="flex size-6 items-center justify-center rounded-md bg-purple-500/10 text-xs font-bold text-purple-600 dark:text-purple-400"
                                    >
                                        Q
                                    </div>
                                    <h3
                                        class="text-muted-foreground font-mono text-xs font-bold tracking-wider uppercase"
                                    >
                                        Applicant Question
                                    </h3>
                                </div>
                                <p
                                    class="text-foreground pl-8 text-base leading-snug font-bold sm:text-lg"
                                >
                                    {version.question ||
                                        'No question text recorded.'}
                                </p>
                            </div>

                            <div class="bg-border/60 h-px"></div>

                            <div class="space-y-2">
                                <div class="flex items-center gap-2">
                                    <div
                                        class="flex size-6 items-center justify-center rounded-md bg-emerald-500/10 text-xs font-bold text-emerald-600 dark:text-emerald-400"
                                    >
                                        A
                                    </div>
                                    <h3
                                        class="text-muted-foreground font-mono text-xs font-bold tracking-wider uppercase"
                                    >
                                        Official Answer
                                    </h3>
                                </div>
                                <div
                                    class="prose prose-sm dark:prose-invert text-foreground/90 max-w-none pl-8 leading-relaxed whitespace-pre-wrap"
                                >
                                    {version.answer ||
                                        'No official answer recorded.'}
                                </div>
                            </div>
                        </div>
                        {#if consensusChecklist.length > 0}
                            <section
                                aria-labelledby="consensus-signatures-heading"
                                class="border-border/80 bg-card space-y-3 rounded-xl border p-5 shadow-xs sm:p-6"
                            >
                                <div class="flex items-center gap-2">
                                    <Users class="text-primary size-4" />
                                    <h3
                                        id="consensus-signatures-heading"
                                        class="text-muted-foreground font-mono text-xs font-bold tracking-wider uppercase"
                                    >
                                        Consensus Signatures
                                    </h3>
                                </div>
                                <ul class="space-y-2">
                                    {#each consensusChecklist as signature (normalizeOffice(signature.office))}
                                        <li
                                            class="border-border/60 bg-muted/20 flex flex-wrap items-center justify-between gap-2 rounded-lg border px-3 py-2 text-sm"
                                        >
                                            <span class="font-medium">
                                                {officeLabel(signature.office)}
                                            </span>
                                            <span
                                                class={`inline-flex items-center gap-1.5 rounded-full border px-2.5 py-1 text-xs font-medium ${signature.signed ? 'border-emerald-500/30 bg-emerald-500/10 text-emerald-700 dark:text-emerald-300' : 'border-amber-500/30 bg-amber-500/10 text-amber-700 dark:text-amber-300'}`}
                                            >
                                                {#if signature.signed}
                                                    <Check class="size-3.5" />
                                                    Signed
                                                    {#if signature.signerName}
                                                        · {signature.signerName}
                                                    {/if}
                                                {:else}
                                                    <Clock class="size-3.5" />
                                                    Awaiting signature
                                                {/if}
                                            </span>
                                        </li>
                                    {/each}
                                </ul>
                            </section>
                        {/if}

                        {#if governingPolicyDependencies.length > 0}
                            <section
                                aria-labelledby="governing-policy-heading"
                                class="border-border/80 bg-card space-y-3 rounded-xl border p-5 shadow-xs sm:p-6"
                            >
                                <div class="flex items-center gap-2">
                                    <BookOpen class="text-primary size-4" />
                                    <h3
                                        id="governing-policy-heading"
                                        class="text-muted-foreground font-mono text-xs font-bold tracking-wider uppercase"
                                    >
                                        Linked Governing Policy
                                    </h3>
                                </div>
                                <ul class="space-y-2">
                                    {#each governingPolicyDependencies as dependency}
                                        {@const reviewNeeded =
                                            dependency.status === 'needs_review'}
                                        <li
                                            class="border-border/60 bg-muted/20 flex flex-col gap-2 rounded-lg border p-3 sm:flex-row sm:items-center sm:justify-between"
                                        >
                                            <div class="min-w-0">
                                                <p class="text-foreground text-sm font-medium">
                                                    {dependency.title ??
                                                        dependency.policy_title ??
                                                        dependency.policy?.title ??
                                                        'Governing policy'}
                                                </p>
                                                <p class="text-muted-foreground mt-0.5 text-xs">
                                                    Revision
                                                    {dependency.revision_label ??
                                                        dependency.public_policy_revision?.revision_label ??
                                                        dependency.public_policy_revision_id ??
                                                        '—'}
                                                </p>
                                            </div>
                                            <span
                                                class={`inline-flex w-fit shrink-0 items-center gap-1.5 rounded-full border px-2.5 py-1 text-xs font-medium ${reviewNeeded ? 'border-amber-500/30 bg-amber-500/10 text-amber-700 dark:text-amber-300' : 'border-emerald-500/30 bg-emerald-500/10 text-emerald-700 dark:text-emerald-300'}`}
                                            >
                                                {#if reviewNeeded}
                                                    <Clock class="size-3.5" />
                                                    Needs review
                                                {:else}
                                                    <Check class="size-3.5" />
                                                    Current
                                                {/if}
                                            </span>
                                        </li>
                                    {/each}
                                </ul>
                                {#if needsPolicyRecertification && capabilities?.can_recertify === true}
                                    <Button
                                        variant="outline"
                                        class="min-h-10 gap-2 border-amber-500/40 text-xs text-amber-700 hover:bg-amber-500/10 dark:text-amber-300"
                                        onclick={recertifyPolicyDependency}
                                    >
                                        <RefreshCw class="size-3.5" />
                                        Recertify Policy Dependency
                                    </Button>
                                {/if}
                            </section>
                        {/if}

                    {:else}
                        <!-- Policy Document Canvas -->
                        <div
                            class="border-border/80 bg-card space-y-3 rounded-xl border p-5 shadow-xs sm:p-6"
                        >
                            <div class="flex items-center justify-between">
                                <div class="flex items-center gap-2">
                                    <BookOpen
                                        class="size-4.5 text-sky-600 dark:text-sky-400"
                                    />
                                    <h3
                                        class="text-muted-foreground font-mono text-xs font-bold tracking-wider uppercase"
                                    >
                                        Governing Policy Content
                                    </h3>
                                </div>
                                <span class="text-muted-foreground text-xs"
                                    >Canonical Text</span
                                >
                            </div>
                            <div
                                class="bg-muted/20 border-border/60 text-foreground rounded-lg border p-4 font-sans text-sm leading-relaxed whitespace-pre-wrap sm:p-5"
                            >
                                {version.body_text ||
                                    version.extraction_text ||
                                    'No policy text available.'}
                            </div>
                        </div>
                    {/if}

                    <!-- AI Grounding & Retrieval Verification -->
                    <div
                        class="border-border/80 bg-card space-y-3 rounded-xl border p-5 shadow-xs"
                    >
                        <div
                            class="text-foreground flex items-center gap-2 text-sm font-semibold"
                        >
                            <Bot class="text-primary size-4.5" />
                            <span>Xiam Grounding & Retrieval Contract</span>
                        </div>
                        <p
                            class="text-muted-foreground text-xs leading-relaxed"
                        >
                            {#if source.audience === 'staff_and_applicant_inquiry'}
                                When published and active, this source is
                                indexed for authenticated applicant inquiry and
                                staff portals. It is strictly cited through
                                groundings and evidence bundles.
                            {:else if source.audience === 'cross_office_staff'}
                                Restricted to authorized staff in Registrar and
                                Guidance offices. It requires dual-office head
                                signatures before any AI retrieval can reference
                                it.
                            {:else}
                                Internal to the {owningOffice} office only. General
                                staff outside this office and applicants cannot retrieve
                                this content.
                            {/if}
                        </p>
                    </div>

                    <!-- Citation Probes (Fixtures) -->
                    {#if (version.fixtures ?? []).length > 0}
                        <div
                            class="border-border/80 bg-card space-y-3 rounded-xl border p-5 shadow-xs"
                        >
                            <div class="flex items-center justify-between">
                                <h3
                                    class="text-muted-foreground font-mono text-xs font-bold tracking-wider uppercase"
                                >
                                    Verification Probes ({version.fixtures
                                        .length})
                                </h3>
                                <span class="text-muted-foreground text-[11px]"
                                    >Sealed digest test vectors</span
                                >
                            </div>
                            <div
                                class="border-border/60 overflow-x-auto rounded-lg border"
                            >
                                <Table.Root class="w-full text-xs">
                                    <Table.Header class="bg-muted/40">
                                        <Table.Row>
                                            <Table.Head class="px-3 py-2"
                                                >Kind</Table.Head
                                            >
                                            <Table.Head class="px-3 py-2"
                                                >Probe Query</Table.Head
                                            >
                                            <Table.Head class="px-3 py-2"
                                                >Expected Outcome</Table.Head
                                            >
                                        </Table.Row>
                                    </Table.Header>
                                    <Table.Body>
                                        {#each version.fixtures as fix (fix.id)}
                                            <Table.Row>
                                                <Table.Cell
                                                    class="px-3 py-2 font-mono font-semibold"
                                                    >{fix.kind}</Table.Cell
                                                >
                                                <Table.Cell class="px-3 py-2"
                                                    >{fix.query_text}</Table.Cell
                                                >
                                                <Table.Cell
                                                    class="text-muted-foreground px-3 py-2"
                                                    >{fix.expected_outcome}</Table.Cell
                                                >
                                            </Table.Row>
                                        {/each}
                                    </Table.Body>
                                </Table.Root>
                            </div>
                        </div>
                    {/if}

                    <!-- Revoke Controls (for active versions) -->
                    {#if capabilities.approve && ['active', 'approved', 'indexing'].includes(version.status)}
                        <div
                            class="space-y-3 rounded-xl border border-rose-500/30 bg-rose-500/[0.03] p-5"
                        >
                            <h3
                                class="flex items-center gap-1.5 font-mono text-xs font-bold tracking-wider text-rose-600 uppercase dark:text-rose-400"
                            >
                                <Ban class="size-4" />
                                Revocation Controls
                            </h3>
                            <p class="text-muted-foreground text-xs">
                                Revoking an active source immediately expunges
                                it from all local lexical and vector RAG
                                indices.
                            </p>
                            <div class="flex flex-col gap-2 sm:flex-row">
                                <Input
                                    class="min-h-9 text-xs"
                                    placeholder="Reason for revocation (e.g. replaced by newer resolution)"
                                    bind:value={revokeReason}
                                />
                                <Button
                                    variant="destructive"
                                    size="sm"
                                    class="min-h-9 shrink-0 gap-1.5 text-xs font-medium"
                                    onclick={revoke}
                                >
                                    <Ban class="size-3.5" />
                                    Revoke Version
                                </Button>
                            </div>
                        </div>
                    {/if}
                </div>

                <!-- Provenance & Governance Column (4 cols) -->
                <div class="space-y-5 lg:col-span-4">
                    <!-- Bento Card: Cryptographic Fingerprint -->
                    <div
                        class="border-border/80 bg-card space-y-4 rounded-xl border p-5 shadow-xs"
                    >
                        <div
                            class="text-foreground flex items-center gap-2 text-sm font-semibold"
                        >
                            <Key class="text-primary size-4" />
                            <span>Cryptographic Provenance</span>
                        </div>

                        <div class="space-y-3 text-xs">
                            <div>
                                <span
                                    class="text-muted-foreground block font-mono text-[11px] tracking-wider uppercase"
                                    >Publication Digest</span
                                >
                                {#if version.publication_digest}
                                    <div
                                        class="bg-muted/40 border-border/60 mt-1 flex items-center justify-between rounded-lg border p-2 font-mono text-[11px]"
                                    >
                                        <span
                                            class="max-w-[200px] truncate"
                                            title={version.publication_digest}
                                        >
                                            {version.publication_digest.slice(
                                                0,
                                                10,
                                            )}...{version.publication_digest.slice(
                                                -10,
                                            )}
                                        </span>
                                        <button
                                            type="button"
                                            class="text-muted-foreground hover:text-foreground p-1"
                                            onclick={() =>
                                                copyDigest(
                                                    version.publication_digest,
                                                )}
                                            title="Copy full digest"
                                        >
                                            {#if copiedDigest}
                                                <Check
                                                    class="size-3.5 text-emerald-500"
                                                />
                                            {:else}
                                                <Copy class="size-3.5" />
                                            {/if}
                                        </button>
                                    </div>
                                    <div
                                        class="mt-1 flex items-center gap-1 text-[10px] font-medium text-emerald-600 dark:text-emerald-400"
                                    >
                                        <ShieldCheck class="size-3" />
                                        Verified SHA-256 Digest
                                    </div>
                                {:else}
                                    <p
                                        class="text-muted-foreground mt-0.5 text-xs italic"
                                    >
                                        Pending signoff and sealing
                                    </p>
                                {/if}
                            </div>

                            {#if version.extraction_digest}
                                <div>
                                    <span
                                        class="text-muted-foreground block font-mono text-[11px] tracking-wider uppercase"
                                        >Extraction Digest</span
                                    >
                                    <p
                                        class="text-muted-foreground bg-muted/30 mt-0.5 truncate rounded px-2 py-1 font-mono text-[10px]"
                                    >
                                        {version.extraction_digest}
                                    </p>
                                </div>
                            {/if}

                            <div>
                                <span
                                    class="text-muted-foreground block font-mono text-[11px] tracking-wider uppercase"
                                    >Version Identifier</span
                                >
                                <p
                                    class="text-muted-foreground bg-muted/30 mt-0.5 truncate rounded px-2 py-1 font-mono text-[10px]"
                                >
                                    {version.id}
                                </p>
                            </div>
                        </div>
                    </div>

                    <!-- Dual Office Approvals Card (for Cross-Office) -->
                    {#if isCrossOffice}
                        <div
                            class="space-y-3 rounded-xl border border-purple-500/30 bg-purple-500/[0.03] p-5 shadow-xs"
                        >
                            <div class="flex items-center justify-between">
                                <span
                                    class="flex items-center gap-1.5 font-mono text-xs font-bold tracking-wider text-purple-700 uppercase dark:text-purple-300"
                                >
                                    <Users class="size-3.5" />
                                    Joint Office Signatures
                                </span>
                                <span
                                    class="font-mono text-[11px] font-bold text-purple-700 dark:text-purple-300"
                                >
                                    {signedJointOfficeCount} of 2
                                </span>
                            </div>
                            <p class="text-muted-foreground text-xs">
                                Both office heads must sign this exact version
                                digest before publication is unlocked.
                            </p>
                            <div class="space-y-2">
                                {#each requiredJointOffices as office (office.key)}
                                    {@const approval = approvalForOffice(
                                        office.key,
                                    )}
                                    <div
                                        class={`space-y-1 rounded-lg border p-3 text-xs ${approval ? 'border-emerald-500/20 bg-emerald-500/5' : 'bg-muted/40 border-border/60'}`}
                                    >
                                        <div
                                            class="flex items-center justify-between"
                                        >
                                            <span
                                                class="text-foreground font-semibold"
                                                >{office.label}</span
                                            >
                                            {#if approval}
                                                <span
                                                    class="inline-flex items-center gap-1 text-[10px] font-semibold text-emerald-600 dark:text-emerald-400"
                                                >
                                                    <CheckCircle2
                                                        class="size-3"
                                                    />
                                                    Signed
                                                </span>
                                            {:else}
                                                <span
                                                    class="text-muted-foreground text-[10px] italic"
                                                    >Awaiting Signature</span
                                                >
                                            {/if}
                                        </div>
                                        {#if approval}
                                            <p
                                                class="text-muted-foreground text-[11px]"
                                            >
                                                Approved: {formatTimestamp(
                                                    approval.approved_at,
                                                )}
                                            </p>
                                        {/if}
                                    </div>
                                {/each}
                            </div>
                        </div>
                    {/if}

                    <!-- Audit Timeline Bento Card -->
                    <div
                        class="border-border/80 bg-card space-y-3 rounded-xl border p-5 shadow-xs"
                    >
                        <h3
                            class="text-muted-foreground font-mono text-xs font-bold tracking-wider uppercase"
                        >
                            Audit & Governance Trail
                        </h3>
                        <dl class="space-y-3 text-xs">
                            <div
                                class="border-border/40 flex items-center justify-between border-b pb-2"
                            >
                                <dt class="text-muted-foreground">Created</dt>
                                <dd class="text-foreground font-medium">
                                    {formatTimestamp(version.created_at)}
                                </dd>
                            </div>
                            <div
                                class="border-border/40 flex items-center justify-between border-b pb-2"
                            >
                                <dt class="text-muted-foreground">Approved</dt>
                                <dd class="text-foreground font-medium">
                                    {formatTimestamp(version.approved_at)}
                                </dd>
                            </div>
                            <div
                                class="border-border/40 flex items-center justify-between border-b pb-2"
                            >
                                <dt class="text-muted-foreground">Published</dt>
                                <dd class="text-foreground font-medium">
                                    {formatTimestamp(version.published_at)}
                                </dd>
                            </div>
                            {#if version.revoked_at}
                                <div
                                    class="flex items-center justify-between text-rose-600 dark:text-rose-400"
                                >
                                    <dt>Revoked</dt>
                                    <dd class="font-medium">
                                        {formatTimestamp(version.revoked_at)}
                                    </dd>
                                </div>
                            {/if}
                        </dl>
                    </div>
                </div>
            </div>
        {/if}
    </div>
</StaffLayout>
