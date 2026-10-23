<script lang="ts">
    import { AlertTriangle, Check, CheckCheck, ChevronLeft, ImageOff, ScanSearch } from 'lucide-svelte';
    import OmrRegionCrop from '../Diagnostics/OmrRegionCrop.svelte';
    export type RapidTriageDecision = string;
    export type RapidTriageCandidate = {
        id: string | number;
        name: string;
        applicantId: string;
        detail?: string;
    };
    export type RapidTriageItem = {
        id: string | number;
        type: 'ambiguous_bubble' | 'unresolved_applicant_id';
        sheet: { number: number | string };
        question?: { number: number | string };
        examinee?: { name?: string; applicantId?: string };
        room?: string;
        cropUrl?: string | null;
        imageUrl?: string | null;
        cropRect?: { x: number; y: number; w: number; h: number } | null;
        imageDimensions?: { width: number; height: number };
        evidence?: Record<string, unknown>;
        capture_id?: string | number;
        page_no?: number;
        item_id?: string | number;
        parent_version?: string | number;
        item_revision?: string | number;
        evidence_hash?: string;
        options?: Array<{ code: string; mean?: number | null }>;
        observedSelection?: string | null;
        reviewReason?: string;
        machineReading?: string;
        handwrittenId?: string;
        candidates?: RapidTriageCandidate[];
        selectedCandidateId?: string | number | null;
        confirmed?: boolean;
        selectedDecision?: RapidTriageDecision | null;
    };

    type Density = 'Compact' | 'Normal' | 'Large';
    type RapidTriageGridProps = {
        items?: RapidTriageItem[];
        density?: Density;
        onBack?: () => void;
        onDecisionChange?: (item: RapidTriageItem, decision: RapidTriageDecision) => void | Promise<void>;
        onConfirmDecision?: (item: RapidTriageItem, decision: RapidTriageDecision, reason: string) => unknown | Promise<unknown>;
        onConfirmMatch?: (item: RapidTriageItem, candidate: RapidTriageCandidate) => unknown | Promise<unknown>;
        onSelectAllConfirmed?: (items: RapidTriageItem[]) => void;
        onCommit?: (items: RapidTriageItem[]) => void | Promise<void>;
    };

    let {
        items = [],
        density = $bindable('Normal'),
        onBack = () => {},
        onDecisionChange = () => {},
        onConfirmDecision = () => {},
        onConfirmMatch = () => {},
        onSelectAllConfirmed = () => {},
        onCommit = () => {},
    }: RapidTriageGridProps = $props();

    let localDecisions = $state<Record<string, RapidTriageDecision>>({});
    let localCandidates = $state<Record<string, string | number>>({});
    let localConfirmed = $state<Record<string, boolean>>({});
    let busyItems = $state<Record<string, boolean>>({});
    let localReasons = $state<Record<string, string>>({});
    let decisionErrors = $state<Record<string, string>>({});
    let committing = $state(false);

    const flaggedCount = $derived(items.filter((item) => !isConfirmed(item)).length);
    const confirmedItems = $derived(items.filter(isConfirmed));
    const allResolved = $derived(items.length > 0 && flaggedCount === 0);

    function itemKey(item: RapidTriageItem): string {
        return String(item.id);
    }

    function availableDecisions(item: RapidTriageItem): string[] {
        const tokens = (item.options ?? []).map((option) => option.code);
        if (item.observedSelection && !tokens.includes(item.observedSelection)) {
            tokens.push(item.observedSelection);
        }
        return [...new Set(tokens)];
    }

    function currentReason(item: RapidTriageItem): string {
        return localReasons[itemKey(item)] ?? item.reviewReason ?? '';
    }

    function isConfirmed(item: RapidTriageItem): boolean {
        return item.confirmed === true || localConfirmed[itemKey(item)] === true;
    }

    function currentDecision(item: RapidTriageItem): RapidTriageDecision | null {
        return localDecisions[itemKey(item)] ?? item.selectedDecision ?? null;
    }

    function currentCandidate(item: RapidTriageItem): string | number | null {
        return localCandidates[itemKey(item)] ?? item.selectedCandidateId ?? null;
    }

    async function chooseDecision(item: RapidTriageItem, decision: RapidTriageDecision): Promise<void> {
        localDecisions[itemKey(item)] = decision;
        await onDecisionChange(item, decision);
    }

    function chooseCandidate(item: RapidTriageItem, candidate: RapidTriageCandidate): void {
        localCandidates[itemKey(item)] = candidate.id;
    }

    async function confirmDecision(item: RapidTriageItem): Promise<void> {
        const decision = currentDecision(item);
        const reason = currentReason(item).trim();
        if (!decision || !reason || busyItems[itemKey(item)]) return;

        busyItems[itemKey(item)] = true;
        decisionErrors[itemKey(item)] = '';
        try {
            const result = await onConfirmDecision(item, decision, reason);
            if (result !== false) localConfirmed[itemKey(item)] = true;
            else decisionErrors[itemKey(item)] = 'The decision was not acknowledged. Review the evidence and try again.';
        } catch (error) {
            decisionErrors[itemKey(item)] = error instanceof Error ? error.message : 'The decision could not be saved.';
        } finally {
            busyItems[itemKey(item)] = false;
        }
    }

    async function confirmMatch(item: RapidTriageItem): Promise<void> {
        const candidateId = currentCandidate(item);
        const candidate = (item.candidates ?? []).find((match) => match.id === candidateId);
        if (!candidate || busyItems[itemKey(item)]) return;

        busyItems[itemKey(item)] = true;
        try {
            const result = await onConfirmMatch(item, candidate);
            if (result !== false) localConfirmed[itemKey(item)] = true;
        } finally {
            busyItems[itemKey(item)] = false;
        }
    }

    async function commitBatch(): Promise<void> {
        if (!allResolved || committing) return;
        committing = true;
        try {
            await onCommit(items);
        } finally {
            committing = false;
        }
    }
</script>

<section class="triage-workspace min-h-0 flex flex-col bg-background text-foreground" aria-label="Rapid triage grid">
    <header class="triage-header flex flex-wrap items-center justify-between gap-3 border-b border-border bg-card px-4 py-3 sm:px-6">
        <div class="flex w-full flex-wrap items-center gap-2 sm:w-auto">
            <fieldset class="flex items-center rounded-lg border border-border bg-background p-1" aria-label="Card density">
                <legend class="sr-only">Card density</legend>
                {#each ['Compact', 'Normal', 'Large'] as option (option)}
                    <button
                        type="button"
                        aria-pressed={density === option}
                        class="rounded-md px-2.5 py-1.5 text-xs font-medium transition-colors focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring aria-pressed:bg-primary aria-pressed:text-primary-foreground"
                        class:active-density={density === option}
                        onclick={() => (density = option as Density)}
                    >{option}</button>
                {/each}
            </fieldset>
            <button type="button" class="inline-flex min-h-10 flex-1 items-center justify-center gap-2 rounded-lg border border-border bg-card px-3 text-sm font-medium hover:bg-muted focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring sm:flex-none" onclick={onBack}>
                <ChevronLeft size={16} aria-hidden="true" />
                Back to Scanner / Queue
            </button>
        </div>
    </header>

    <main class="min-h-0 flex-1 overflow-y-auto px-3 py-4 sm:px-6 sm:py-6">
        {#if items.length === 0}
            <div class="mx-auto flex max-w-xl flex-col items-center rounded-2xl border border-dashed border-border bg-card px-6 py-12 text-center">
                <CheckCheck size={28} class="text-muted-foreground" aria-hidden="true" />
                <h2 class="mt-3 font-semibold">No items need a decision</h2>
                <p class="mt-1 text-sm text-muted-foreground">New flagged sheets will appear here for review.</p>
            </div>
        {:else}
            <div class="triage-grid mx-auto grid w-full max-w-[1600px] grid-cols-1 gap-4 lg:grid-cols-2 2xl:grid-cols-3" class:compact={density === 'Compact'} class:large={density === 'Large'}>
                {#each items as item (item.id)}
                    {@const confirmed = isConfirmed(item)}
                    <article class="triage-card min-w-0 overflow-hidden rounded-2xl border border-border bg-card shadow-sm" class:resolved={confirmed}>
                        <div class="card-heading flex items-start justify-between gap-3 border-b border-border px-4 py-3">
                            <div class="min-w-0">
                                <h2 class="flex items-center gap-2 text-sm font-semibold leading-5 sm:text-base">
                                    {#if confirmed}<Check size={16} class="shrink-0 text-emerald-600" aria-hidden="true" />{:else}<AlertTriangle size={16} class="shrink-0 text-amber-600" aria-hidden="true" />{/if}
                                    <span class="truncate">Sheet #{item.sheet.number} · {item.type === 'ambiguous_bubble' ? `Q${item.question?.number ?? '—'}` : 'Unresolved Applicant ID'}</span>
                                </h2>
                                {#if item.type === 'ambiguous_bubble'}
                                    <p class="mt-1 truncate text-xs text-muted-foreground">{item.examinee?.name ?? 'Examinee not identified'}{item.examinee?.applicantId ? ` · ${item.examinee.applicantId}` : ''}</p>
                                {:else}
                                    <p class="mt-1 truncate text-xs text-muted-foreground">Room: {item.room ?? 'Not provided'}</p>
                                {/if}
                            </div>
                            <span class="shrink-0 rounded-full border border-border px-2 py-1 text-[10px] font-semibold uppercase tracking-wide text-muted-foreground">{confirmed ? 'Confirmed' : item.type === 'ambiguous_bubble' ? 'Needs decision' : 'Needs match'}</span>
                        </div>

                        {#if item.type === 'ambiguous_bubble'}
                            <div class="card-content space-y-4 p-4">
                                <div class="crop-frame flex min-h-36 items-center justify-center overflow-hidden rounded-xl border border-border bg-background sm:min-h-44" aria-label="Question bubble crop">
                                    {#if item.cropUrl}
                                        <img src={item.cropUrl} alt={`High-resolution crop for sheet ${item.sheet.number}, question ${item.question?.number ?? ''}`} class="max-h-64 w-full object-contain" loading="lazy" />
                                    {:else if item.imageUrl && item.cropRect && item.evidence}
                                        <OmrRegionCrop item={item.evidence} imageUrl={item.imageUrl} region={item.cropRect} alt={`Evidence crop for sheet ${item.sheet.number}, question ${item.question?.number ?? ''}`} title="Question evidence crop" width={460} id={`triage-crop-${item.id}`} />
                                    {:else}
                                        <div class="flex flex-col items-center gap-2 p-6 text-center text-sm text-muted-foreground"><ImageOff size={22} aria-hidden="true" /><span>Question crop unavailable</span></div>
                                    {/if}
                                </div>
                                <div class="flex items-center gap-2 rounded-lg bg-background px-3 py-2 text-xs sm:text-sm">
                                    <ScanSearch size={16} class="shrink-0 text-muted-foreground" aria-hidden="true" />
                                    <span class="font-medium text-muted-foreground">Machine selection</span>
                                    <span class="ml-auto text-right font-semibold">{item.machineReading ?? item.observedSelection ?? 'No machine selection recorded'}</span>
                                </div>
                                <div class="grid grid-cols-4 gap-2" role="group" aria-label={`Choose answer for question ${item.question?.number ?? ''}`}>
                                    {#each availableDecisions(item) as decision (decision)}
                                        <button
                                            type="button"
                                            disabled={confirmed || busyItems[itemKey(item)]}
                                            aria-pressed={currentDecision(item) === decision}
                                            class="answer-choice min-h-11 rounded-lg border border-border px-2 py-2 text-sm font-semibold transition-colors focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring disabled:cursor-not-allowed disabled:opacity-60"
                                            class:selected-choice={currentDecision(item) === decision}
                                            onclick={() => chooseDecision(item, decision)}
                                        >
                                            {#if item.observedSelection === decision}<span class="block text-[10px] font-normal">Observed</span>{/if}
                                            {decision}
                                        </button>
                                    {/each}
                                    <button type="button" disabled={confirmed || busyItems[itemKey(item)]} aria-pressed={currentDecision(item) === 'void'} class="answer-choice min-h-11 rounded-lg border border-border px-2 py-2 text-sm font-semibold transition-colors focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring disabled:cursor-not-allowed disabled:opacity-60" class:selected-choice={currentDecision(item) === 'void'} onclick={() => chooseDecision(item, 'void')}>Void</button>
                                </div>
                                <label class="grid gap-1.5 text-xs">
                                    <span class="font-semibold text-muted-foreground">Review reason (required)</span>
                                    <textarea class="min-h-20 resize-y rounded-lg border border-border bg-background px-3 py-2 text-sm" maxlength="500" value={currentReason(item)} disabled={confirmed || busyItems[itemKey(item)]} oninput={(event) => (localReasons[itemKey(item)] = event.currentTarget.value)} required></textarea>
                                </label>
                                {#if decisionErrors[itemKey(item)]}
                                    <p class="rounded-md border border-destructive/30 bg-destructive/10 px-3 py-2 text-xs text-destructive" role="alert">{decisionErrors[itemKey(item)]}</p>
                                {/if}
                                <button type="button" disabled={confirmed || !currentDecision(item) || !currentReason(item).trim() || busyItems[itemKey(item)]} class="primary-action inline-flex min-h-11 w-full items-center justify-center gap-2 rounded-lg px-4 text-sm font-semibold disabled:cursor-not-allowed disabled:opacity-50" onclick={() => confirmDecision(item)}>
                                    {#if confirmed}<Check size={16} aria-hidden="true" />Decision Confirmed{:else}<Check size={16} aria-hidden="true" />{busyItems[itemKey(item)] ? 'Saving decision…' : 'Confirm Decision'}{/if}
                                </button>
                            </div>
                        {:else}
                            <div class="card-content space-y-4 p-4">
                                <div class="id-crop flex min-h-28 items-center justify-center overflow-hidden rounded-xl border border-border bg-background px-4 py-5" aria-label="Handwritten Applicant ID crop">
                                    {#if item.cropUrl}
                                        <img src={item.cropUrl} alt="Handwritten Applicant ID crop" class="max-h-40 max-w-full object-contain" loading="lazy" />
                                    {:else}
                                        <div class="text-center">
                                            <p class="mt-2 font-mono text-xl font-semibold tracking-[0.34em] sm:text-2xl">{item.handwrittenId ?? 'Not identified'}</p>
                                        </div>
                                    {/if}
                                </div>
                                <div>
                                    <div class="mb-2 flex items-center justify-between gap-2">
                                        <h3 class="text-sm font-semibold">Candidate roster matches</h3>
                                        <span class="text-xs text-muted-foreground">{item.candidates?.length ?? 0} matches</span>
                                    </div>
                                    <div class="max-h-52 space-y-2 overflow-y-auto" role="radiogroup" aria-label="Select a candidate match">
                                        {#each item.candidates ?? [] as candidate (candidate.id)}
                                            <button type="button" role="radio" aria-checked={currentCandidate(item) === candidate.id} disabled={confirmed || busyItems[itemKey(item)]} class="candidate-option flex w-full items-center justify-between gap-3 rounded-xl border border-border bg-background p-3 text-left transition-colors focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring disabled:opacity-60" class:selected-candidate={currentCandidate(item) === candidate.id} onclick={() => chooseCandidate(item, candidate)}>
                                                <span class="min-w-0"><span class="block truncate text-sm font-medium">{candidate.name}</span><span class="mt-0.5 block truncate text-xs text-muted-foreground">{candidate.applicantId}{candidate.detail ? ` · ${candidate.detail}` : ''}</span></span>
                                                {#if currentCandidate(item) === candidate.id}<Check size={16} class="shrink-0 text-primary" aria-hidden="true" />{/if}
                                            </button>
                                        {:else}
                                            <p class="rounded-xl border border-dashed border-border p-4 text-center text-sm text-muted-foreground">No roster matches are available.</p>
                                        {/each}
                                    </div>
                                </div>
                                <button type="button" disabled={confirmed || currentCandidate(item) === null || busyItems[itemKey(item)]} class="primary-action inline-flex min-h-11 w-full items-center justify-center gap-2 rounded-lg px-4 text-sm font-semibold disabled:cursor-not-allowed disabled:opacity-50" onclick={() => confirmMatch(item)}>
                                    <Check size={16} aria-hidden="true" />
                                    {#if confirmed}Match Confirmed{:else}{busyItems[itemKey(item)] ? 'Linking applicant…' : 'Confirm Match & Link ID'}{/if}
                                </button>
                            </div>
                        {/if}
                    </article>
                {/each}
            </div>
        {/if}
    </main>

    <footer class="triage-footer flex flex-col gap-2 border-t border-border bg-card px-3 py-3 sm:flex-row sm:items-center sm:justify-between sm:px-6">
        <p class="text-xs text-muted-foreground">{confirmedItems.length} of {items.length} decision items confirmed</p>
        <div class="flex flex-col-reverse gap-2 sm:flex-row">
            <button type="button" disabled={confirmedItems.length === 0} class="inline-flex min-h-11 items-center justify-center rounded-lg border border-border bg-background px-4 text-sm font-medium hover:bg-muted focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring disabled:cursor-not-allowed disabled:opacity-50" onclick={() => onSelectAllConfirmed(confirmedItems)}>
                Select All Confirmed
            </button>
            <button type="button" disabled={!allResolved || committing} class="primary-action inline-flex min-h-11 items-center justify-center rounded-lg px-4 text-sm font-semibold disabled:cursor-not-allowed disabled:opacity-50" onclick={commitBatch}>
                {committing ? 'Committing batch…' : 'Commit & Finalize Batch'}
            </button>
        </div>
    </footer>
</section>

<style>
    .triage-workspace { height: 100%; min-height: 0; overflow: hidden; }
    .active-density { background: var(--primary); color: var(--primary-foreground); }
    .selected-choice { border-color: var(--primary); background: color-mix(in srgb, var(--primary) 12%, var(--card)); color: var(--foreground); box-shadow: inset 0 0 0 1px var(--primary); }
    .selected-choice :global(span:first-child) { color: var(--primary); }
    .primary-action { background: var(--primary); color: var(--primary-foreground); transition: filter 150ms ease; }
    .primary-action:not(:disabled):hover { filter: brightness(0.94); }
    .candidate-option.selected-candidate { border-color: var(--primary); box-shadow: inset 0 0 0 1px var(--primary); background: color-mix(in srgb, var(--primary) 8%, var(--card)); }
    .triage-grid.compact .card-content { padding: 0.75rem; gap: 0.75rem; }
    .triage-grid.compact .crop-frame { min-height: 7rem; }
    .triage-grid.large .card-content { padding: 1.25rem; gap: 1.25rem; }
    .triage-grid.large .crop-frame { min-height: 13rem; }
    @media (max-width: 639px) {
        .triage-workspace { height: 100%; min-height: 0; }
        .triage-header { padding-top: max(0.75rem, env(safe-area-inset-top)); }
        .triage-footer { padding-bottom: max(0.75rem, env(safe-area-inset-bottom)); }
        .triage-grid { grid-template-columns: minmax(0, 1fr); }
    }
    @media (prefers-reduced-motion: reduce) {
        *, *::before, *::after { scroll-behavior: auto !important; transition-duration: 0.01ms !important; }
    }
</style>
