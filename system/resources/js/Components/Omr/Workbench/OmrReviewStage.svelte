<script lang="ts">
    import OmrEvidenceDock from './OmrEvidenceDock.svelte';
    import OmrRecoveryCard from './OmrRecoveryCard.svelte';
    import type {
        OmrWorkbenchProjection,
        OmrWorkbenchRecoveryAction,
        OmrWorkbenchRecoveryItem,
    } from '@/lib/omrWorkbench';

    type EvidenceCallback = (...args: never[]) => unknown;
    type EvidencePlacement = 'embedded' | 'focus' | 'mobile';
    type OmrRecoveryActionHandler = (
        action: OmrWorkbenchRecoveryAction,
        recovery: OmrWorkbenchRecoveryItem,
    ) => void | Promise<void>;

    type ReviewStageProps = {
        projection: OmrWorkbenchProjection;
        evidencePage?: unknown;
        placement?: EvidencePlacement;
        labels?: Record<string, string | undefined>;
        returnUrl?: string | null;
        loading?: boolean;
        error?: unknown;
        onRetry?: EvidenceCallback | null;
        onPageChange?: EvidenceCallback | null;
        onPerPageChange?: EvidenceCallback | null;
        onGroupChange?: EvidenceCallback | null;
        onFilterChange?: EvidenceCallback | null;
        onRowOpen?: EvidenceCallback | null;
        onTabChange?: EvidenceCallback | null;
        onViewChange?: EvidenceCallback | null;
        initialTab?: string;
        mobileRailExpanded?: boolean;
        contextSummary?: Record<string, unknown> | null;
        idPrefix?: string;
        reviewable?: boolean;
        blind?: boolean;
        reviewReasons?: unknown[];
        onDirtyChange?: EvidenceCallback | null;
        onDirtyNavigation?: EvidenceCallback | null;
        onSaveDecision?: EvidenceCallback | null;
        onRecoveryAction?: OmrRecoveryActionHandler | null;
        recoveryBusy?: boolean;
        sheets?: Array<any>;
        activeSheetId?: number | null;
        onSelectSheet?: ((sheet: any) => void) | null;
    };
    let {
        projection,
        evidencePage = undefined,
        placement = 'embedded',
        labels = {},
        returnUrl = null,
        loading = false,
        error = null,
        onRetry = null,
        onPageChange = null,
        onPerPageChange = null,
        onGroupChange = null,
        onFilterChange = null,
        onRowOpen = null,
        onTabChange = null,
        onViewChange = null,
        initialTab = 'aligned',
        mobileRailExpanded = $bindable(false),
        contextSummary = null,
        idPrefix = 'omr-review',
        reviewable = false,
        blind = false,
        reviewReasons = [],
        onDirtyChange = null,
        onDirtyNavigation = null,
        onSaveDecision = null,
        onSaveAndNext = null,
        onRecoveryAction = null,
        recoveryBusy = false,
        sheets = [],
        activeSheetId = null,
        onSelectSheet = null,
    } = $props();

    function projectedRecoveryItems(
        value: unknown,
    ): OmrWorkbenchRecoveryItem[] {
        if (!Array.isArray(value)) return [];

        return value.filter(
            (item): item is OmrWorkbenchRecoveryItem =>
                item !== null &&
                typeof item === 'object' &&
                !Array.isArray(item),
        );
    }

    const recoveryItems = $derived.by(() => {
        const items = projectedRecoveryItems(projection?.recovery?.items);
        if (items.length > 0) return items;

        return (projection?.sheets ?? [])
            .map((sheet) => sheet.recovery)
            .filter(
                (item): item is OmrWorkbenchRecoveryItem =>
                    item !== null &&
                    item !== undefined &&
                    typeof item === 'object' &&
                    !Array.isArray(item),
            );
    });

    const attentionItems = $derived.by(() => {
        const items = projection?.recovery?.needs_attention;
        return Array.isArray(items) ? items : [];
    });
    function needsReview(sheet: any): boolean {
        const status = String(sheet?.status ?? '').toLowerCase();

        return Boolean(sheet?.recovery) ||
            ['needs_review', 'failed', 'failed_terminal'].includes(status);
    }

    const needsReviewSheets = $derived((sheets ?? []).filter(needsReview));
    const nextNeedsReviewSheet = $derived.by(() => {
        const remaining = needsReviewSheets.filter(
            (sheet) => sheet.id !== activeSheetId,
        );

        return remaining[0] ?? needsReviewSheets[0] ?? null;
    });

    function openNextNeedsReview(): void {
        if (nextNeedsReviewSheet) onSelectSheet?.(nextNeedsReviewSheet);
    }

    const resolvedEvidencePage = $derived(evidencePage ?? projection?.evidence);
    const evidencePlacement = $derived(placement);
    const reviewLayoutClasses = $derived.by(() =>
        placement === 'mobile'
            ? 'flex min-w-0 flex-col gap-3'
            : 'grid min-w-0 gap-4 lg:grid-cols-[minmax(0,42fr)_minmax(0,58fr)] lg:items-start',
    );
    const recoveryColumnClasses = $derived.by(() =>
        placement === 'mobile' ? 'order-1 min-w-0' : 'order-1 min-w-0',
    );
    const evidenceColumnClasses = $derived.by(() =>
        placement === 'mobile' ? 'order-2 min-w-0' : 'order-2 min-w-0',
    );
</script>

<section
    class="border-border bg-card min-w-0 overflow-hidden rounded-xl border shadow-xs"
    data-omr-review-stage
    aria-labelledby="omr-review-stage-title"
>
    <header
        class="border-border/70 flex flex-col gap-2 border-b px-4 py-4 sm:px-5"
    >
        <div
            class="text-primary text-xs font-semibold tracking-[0.12em] uppercase"
        >
            OMR workbench
        </div>
        <h2
            id="omr-review-stage-title"
            class="text-lg font-semibold tracking-tight"
        >
            Evidence &amp; review
        </h2>
        <p class="text-muted-foreground max-w-3xl text-sm leading-relaxed">
            Review the server-projected evidence and resolve any blocked sheets
            before completion.
        </p>
    </header>

    <div class="min-w-0 space-y-4 p-3 sm:p-4">
        <div class={reviewLayoutClasses} data-omr-review-layout>
            <aside
                class={recoveryColumnClasses}
                data-omr-review-recovery
                aria-label="OMR recovery"
            >
                {#if attentionItems.length > 0}
                    <div
                        class="border-border/70 bg-muted/20 text-muted-foreground mb-3 rounded-lg border px-3 py-2.5 text-xs leading-relaxed"
                        data-omr-needs-attention
                        role="status"
                        aria-live="polite"
                    >
                        <p class="text-foreground font-semibold">
                            Needs attention
                        </p>
                        <ul class="mt-1 space-y-1">
                            {#each attentionItems as attention, index (`${attention.owner ?? 'owner'}-${index}`)}
                                <li>
                                    {attention.owner ?? 'Assigned owner'} · {attention.count ??
                                        0} item{attention.count === 1
                                        ? ''
                                        : 's'}
                                </li>
                            {/each}
                        </ul>
                    </div>
                {/if}

                {#if recoveryItems.length > 0}
                    <div class="space-y-3" data-omr-recovery-list>
                        {#each recoveryItems as recovery, index (`${recovery.capture_id ?? recovery.support_code ?? index}`)}
                            <OmrRecoveryCard
                                {recovery}
                                onAction={onRecoveryAction}
                                busy={recoveryBusy}
                                idPrefix={`${idPrefix}-recovery-${index}`}
                            />
                        {/each}
                    </div>
                {:else if attentionItems.length === 0}
                    <div
                        class="border-border/70 bg-muted/20 text-muted-foreground rounded-lg border border-dashed px-3 py-4 text-sm"
                        data-omr-recovery-empty
                    >
                        No recovery actions are currently projected.
                    </div>
                {/if}
            </aside>

            <div class={evidenceColumnClasses} data-omr-review-evidence>
                <OmrEvidenceDock
                    page={resolvedEvidencePage}
                    {labels}
                    {returnUrl}
                    {loading}
                    {error}
                    {onRetry}
                    {onPageChange}
                    {onPerPageChange}
                    {onGroupChange}
                    {onFilterChange}
                    {onRowOpen}
                    {onTabChange}
                    {onViewChange}
                    {initialTab}
                    bind:mobileRailExpanded
                    {contextSummary}
                    idPrefix={`${idPrefix}-evidence`}
                    {reviewable}
                    {blind}
                    {reviewReasons}
                    {onDirtyChange}
                    {onDirtyNavigation}
                    {onSaveDecision}
                    {onSaveAndNext}
                    placement={evidencePlacement}
                />
            </div>
        </div>
        {#if sheets && sheets.length > 0}
            <div
                class="border-border/70 border-t pt-3 font-mono text-xs"
                data-omr-review-reel
            >
                <div
                    class="text-muted-foreground mb-2 flex flex-wrap items-center justify-between gap-2 text-xs"
                >
                    <span
                        class="text-[10px] font-semibold tracking-wider uppercase"
                        >Batch Sheets:</span
                    >
                    <button
                        type="button"
                        class="border-primary/30 bg-primary/10 text-primary hover:bg-primary/15 rounded-md border px-2.5 py-1.5 text-xs font-semibold transition-colors disabled:cursor-not-allowed disabled:opacity-50"
                        disabled={!nextNeedsReviewSheet}
                        onclick={openNextNeedsReview}
                    >
                        Next needs review ({needsReviewSheets.length})
                    </button>
                </div>
                <p class="text-muted-foreground mb-2 text-[11px]">
                    Click a sheet to inspect its evidence.
                </p>
                <div
                    class="flex items-center gap-2 overflow-x-auto pb-1.5"
                    role="tablist"
                    aria-label="Batch sheets reel"
                >
                    {#each sheets as sheet (sheet.id)}
                        <button
                            type="button"
                            role="tab"
                            aria-selected={activeSheetId === sheet.id}
                            onclick={() => onSelectSheet?.(sheet)}
                            class={`flex shrink-0 items-center gap-2 rounded-lg border px-3 py-1.5 font-mono text-xs font-bold transition-all ${activeSheetId === sheet.id ? 'border-primary bg-primary/10 text-primary ring-primary/40 ring-1' : 'border-border/80 bg-background hover:bg-muted text-muted-foreground'}`}
                            data-omr-reel-sheet={sheet.id}
                        >
                            <span
                                >#{sheet.id}
                                {sheet.detected_applicant_number ??
                                    sheet.client_capture_id ??
                                    ''}</span
                            >
                            {#if sheet.recovery}
                                <span
                                    class="size-2 rounded-full bg-amber-500"
                                    title="Needs Check"
                                ></span>
                            {:else}
                                <span
                                    class="text-emerald-500 text-[10px] font-bold"
                                    title="Clean">✓</span
                                >
                            {/if}
                        </button>
                    {/each}
                </div>
            </div>
        {/if}

    </div>
</section>
