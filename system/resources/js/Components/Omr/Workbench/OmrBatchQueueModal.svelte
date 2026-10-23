<script lang="ts">
    import { Check, CircleAlert, Trash2, X, Zap } from 'lucide-svelte';
    import type { OmrWorkbenchSheet } from '@/lib/omrWorkbench';

    type QueueFilter = 'all' | 'needs_review' | 'clean';

    let {
        open = false,
        sheets = [],
        scannedCount = 0,
        totalCount = 0,
        flaggedCount = 0,
        onClose,
        onSelectSheet,
        onOpenTriage,
        onCommit,
        canCommit = false,
        onRetakeSheet,
        onDiscardSheet,
    }: {
        open?: boolean;
        sheets?: OmrWorkbenchSheet[];
        scannedCount?: number;
        totalCount?: number;
        flaggedCount?: number;
        onClose?: () => void;
        onSelectSheet?: (sheet: OmrWorkbenchSheet) => void;
        onOpenTriage?: () => void;
        onCommit?: () => void;
        canCommit?: boolean;
        onRetakeSheet?: (sheet: OmrWorkbenchSheet) => void;
        onDiscardSheet?: (sheet: OmrWorkbenchSheet) => void;
    } = $props();

    let filter = $state<QueueFilter>('all');
    let dragStartY = $state<number | null>(null);
    let dragY = $state(0);
    let dragged = $state(false);

    const cleanCount = $derived(Math.max(0, sheets.length - flaggedCount));
    const completionPercent = $derived(
        totalCount > 0 ? Math.min(100, Math.round((scannedCount / totalCount) * 100)) : 0,
    );
    const filteredSheets = $derived.by(() => {
        const ordered = [...sheets].sort((a, b) => Number(isFlagged(b)) - Number(isFlagged(a)));
        if (filter === 'needs_review') return ordered.filter(isFlagged);
        if (filter === 'clean') return ordered.filter((sheet) => !isFlagged(sheet));
        return ordered;
    });

    function isFlagged(sheet: OmrWorkbenchSheet): boolean {
        const status = String(sheet.status ?? '').toLowerCase();
        return Boolean(sheet.recovery) || ['needs_review', 'failed', 'failed_terminal'].includes(status);
    }

    function problemBadges(sheet: OmrWorkbenchSheet): string[] {
        const failure = sheet.failure ?? {};
        const recovery = sheet.recovery ?? {};
        const description = [
            failure.code, failure.message, failure.detail,
            recovery.code, recovery.message, recovery.detail,
            sheet.status,
        ].filter((value) => typeof value === 'string').join(' ').toLowerCase();
        const badges: string[] = [];
        if (description.includes('ambiguous') || description.includes('bubble')) {
            const questionNo = sheet.question_no;
            badges.push(
                (typeof questionNo === 'number' && questionNo > 0) ||
                (typeof questionNo === 'string' && questionNo.trim() !== '')
                    ? `Q${questionNo}`
                    : 'Needs Review',
            );
        }
        if (description.includes('examinee') || description.includes('applicant number') || description.includes('identity') || description.includes('pairing')) {
            badges.push('Unresolved Applicant ID');
        }
        if (badges.length === 0 && isFlagged(sheet)) badges.push('Needs Review');
        return badges;
    }
    function thumbnailUrl(sheet: OmrWorkbenchSheet): string | null {
        const record = sheet as Record<string, unknown>;
        const lazyUrls = record.lazy_asset_urls as Record<string, unknown> | undefined;
        const assetUrls = record.asset_urls as Record<string, unknown> | undefined;
        const value = lazyUrls?.input ?? assetUrls?.thumbnail ?? assetUrls?.input;
        return typeof value === 'string' && value !== '' ? value : null;
    }

    function selectSheet(sheet: OmrWorkbenchSheet): void {
        onSelectSheet?.(sheet);
    }

    function startDrag(event: PointerEvent): void {
        if (event.pointerType === 'mouse') return;
        dragStartY = event.clientY;
        dragY = 0;
        dragged = false;
        (event.currentTarget as HTMLElement).setPointerCapture(event.pointerId);
    }

    function moveDrag(event: PointerEvent): void {
        if (dragStartY === null) return;
        dragY = Math.max(0, event.clientY - dragStartY);
        if (dragY > 8) dragged = true;
    }

    function endDrag(): void {
        if (dragY > 90) onClose?.();
        dragStartY = null;
        dragY = 0;
    }

    function handleHandleClick(): void {
        if (!dragged) onClose?.();
        dragged = false;
    }

    function onKeydown(event: KeyboardEvent): void {
        if (open && event.key === 'Escape') {
            event.preventDefault();
            onClose?.();
        }
    }

    $effect(() => {
        if (!open) return;
        const previousOverflow = document.body.style.overflow;
        document.body.style.overflow = 'hidden';
        return () => {
            document.body.style.overflow = previousOverflow;
        };
    });
</script>

<svelte:window onkeydown={onKeydown} />

{#if open}
    <div class="fixed inset-0 z-[100] flex items-end justify-center bg-black/55 backdrop-blur-sm lg:items-center lg:p-6" role="presentation" onclick={(event) => { if (event.target === event.currentTarget) onClose?.(); }}>
        <section
            class="queue-surface border-border bg-card text-card-foreground flex max-h-[92dvh] w-full flex-col overflow-hidden rounded-t-[1.75rem] border shadow-2xl lg:max-h-[min(85dvh,52rem)] lg:max-w-2xl lg:rounded-2xl"
            role="dialog"
            aria-modal="true"
            aria-labelledby="omr-batch-queue-title"
            data-omr-batch-queue-modal
            style:transform={dragY > 0 ? `translateY(${dragY}px)` : undefined}
            style:transition={dragStartY === null ? 'transform 220ms cubic-bezier(0.2, 0.8, 0.2, 1)' : 'none'}
        >
            <button type="button" class="mx-auto block w-full shrink-0 touch-none px-5 pt-3 lg:hidden" onpointerdown={startDrag} onpointermove={moveDrag} onpointerup={endDrag} onpointercancel={endDrag} onclick={handleHandleClick} aria-label="Close queue or drag down to dismiss">
                <span class="mx-auto block h-1.5 w-10 rounded-full bg-muted-foreground/35"></span>
            </button>
            <header class="border-border/70 flex shrink-0 items-start justify-between gap-4 border-b px-5 py-4 sm:px-6">
                <div class="min-w-0">
                    <h2 id="omr-batch-queue-title" class="text-lg font-semibold tracking-tight sm:text-xl">Batch Progress &amp; Queue ({scannedCount} of {totalCount || scannedCount} Scanned)</h2>
                    <p class="text-muted-foreground mt-1 text-sm">Review flagged sheets before finalizing this batch.</p>
                </div>
                <button type="button" class="border-border hover:bg-muted inline-flex size-9 shrink-0 items-center justify-center rounded-full border transition-colors" onclick={() => onClose?.()} aria-label="Close batch queue">
                    <X class="size-4" aria-hidden="true" />
                </button>
            </header>

            <div class="min-h-0 flex-1 overflow-y-auto px-5 py-4 sm:px-6">
                <div class="space-y-2" aria-label="Batch progress">
                    <div class="flex items-center justify-between gap-3 text-sm font-medium">
                        <span>{completionPercent}% Complete</span>
                        <span class="text-amber-700 dark:text-amber-300">{flaggedCount} Flagged</span>
                    </div>
                    <div class="bg-muted h-2.5 overflow-hidden rounded-full" role="progressbar" aria-valuenow={completionPercent} aria-valuemin="0" aria-valuemax="100" aria-label="Batch completion">
                        <div class="bg-primary h-full rounded-full transition-[width]" style={`width: ${completionPercent}%`}></div>
                    </div>
                </div>

                <nav class="border-border/70 mt-5 flex gap-1 border-b" aria-label="Filter batch sheets">
                    {#each [{ id: 'all', label: 'All', count: sheets.length }, { id: 'needs_review', label: 'Needs Review', count: flaggedCount }, { id: 'clean', label: 'Clean', count: cleanCount }] as tab (tab.id)}
                        <button type="button" class={`border-b-2 px-3 py-2.5 text-sm font-medium transition-colors ${filter === tab.id ? 'border-primary text-foreground' : 'border-transparent text-muted-foreground hover:text-foreground'}`} aria-pressed={filter === tab.id} onclick={() => (filter = tab.id as QueueFilter)}>
                            {tab.label} <span class="ml-1 font-mono text-xs">{tab.count}</span>
                        </button>
                    {/each}
                </nav>

                {#if filteredSheets.length > 0}
                    <ul class="divide-border/60 divide-y" aria-label="Batch sheets">
                        {#each filteredSheets as sheet (sheet.id)}
                            {@const thumbnail = thumbnailUrl(sheet)}
                            <li class="hover:bg-muted/40 flex w-full flex-wrap items-center justify-between gap-3 px-2 py-3 transition-colors sm:flex-nowrap">
                                <button type="button" class="focus-visible:outline-primary flex min-w-0 flex-1 basis-full items-center gap-3 text-left focus-visible:outline-2 focus-visible:outline-offset-[-2px] sm:basis-auto" onclick={() => selectSheet(sheet)}>
                                    <div class="border-border/70 bg-muted/30 size-12 shrink-0 overflow-hidden rounded-lg border">
                                        {#if thumbnail}
                                            <img src={thumbnail} alt="" class="size-full object-cover" loading="lazy" />
                                        {:else}
                                            <div class="text-muted-foreground flex size-full items-center justify-center font-mono text-[10px]">#{sheet.id}</div>
                                        {/if}
                                    </div>
                                    <div class="min-w-0 flex-1">
                                        <div class="flex items-center gap-2">
                                            <span class="truncate text-sm font-semibold">{sheet.detected_applicant_number ?? sheet.client_capture_id ?? `Sheet #${sheet.id}`}</span>
                                            {#if isFlagged(sheet)}
                                                <span class="inline-flex shrink-0 items-center gap-1 rounded-full bg-amber-500/10 px-2 py-0.5 text-[10px] font-semibold text-amber-700 dark:text-amber-300"><CircleAlert class="size-3" aria-hidden="true" />Needs Review</span>
                                            {:else}
                                                <span class="inline-flex shrink-0 items-center gap-1 rounded-full bg-emerald-500/10 px-2 py-0.5 text-[10px] font-semibold text-emerald-700 dark:text-emerald-300"><Check class="size-3" aria-hidden="true" />Clean</span>
                                            {/if}
                                        </div>
                                        <div class="mt-1 flex flex-wrap gap-1.5">
                                            {#each problemBadges(sheet) as badge (badge)}
                                                <span class="rounded border border-amber-500/30 bg-amber-500/10 px-1.5 py-0.5 text-[10px] font-medium text-amber-800 dark:text-amber-300">{badge}</span>
                                            {/each}
                                        </div>
                                    </div>
                                </button>
                                <div class="flex w-full shrink-0 items-center justify-end gap-2 sm:w-auto">
                                    {#if onDiscardSheet}
                                        <button
                                            type="button"
                                            class="text-muted-foreground hover:text-rose-600 dark:hover:text-rose-400 inline-flex min-h-11 min-w-11 items-center justify-center rounded p-1 transition-colors hover:bg-rose-500/10"
                                            title="Remove this capture from the current queue; retain its history"
                                            aria-label={`Remove capture #${sheet.id}`}
                                            onclick={() => onDiscardSheet?.(sheet)}
                                        >
                                            <Trash2 class="size-3.5" aria-hidden="true" />
                                        </button>
                                    {/if}
                                    {#if onRetakeSheet && sheet.capture_retake}
                                        <button
                                            type="button"
                                            class="min-h-11 rounded px-2 py-1 text-xs font-semibold text-rose-600 hover:bg-rose-50 dark:text-rose-400 dark:hover:bg-rose-950/40"
                                            onclick={() => onRetakeSheet?.(sheet)}
                                        >Retake page</button>
                                    {/if}
                                    <button
                                        type="button"
                                        class="text-primary min-h-11 text-xs font-semibold hover:underline"
                                        onclick={() => selectSheet(sheet)}
                                    >Review</button>
                                </div>
                            </li>
                        {/each}
                    </ul>
                {:else}
                    <p class="text-muted-foreground border-border/70 mt-4 rounded-xl border border-dashed px-4 py-8 text-center text-sm">No sheets in this filter.</p>
                {/if}
            </div>

            <footer class="border-border/70 bg-card/95 shrink-0 space-y-2 border-t px-5 py-4 pb-[max(1rem,env(safe-area-inset-bottom))] sm:px-6">
                <button type="button" class="bg-primary text-primary-foreground hover:bg-primary/90 inline-flex min-h-12 w-full items-center justify-center gap-2 rounded-xl px-4 py-3 text-sm font-semibold shadow-sm transition-colors disabled:cursor-not-allowed disabled:opacity-50" onclick={() => onOpenTriage?.()} disabled={flaggedCount === 0}>
                    <Zap class="size-4" aria-hidden="true" />
                    Open Rapid Triage Grid ({flaggedCount} Flagged)
                </button>
                {#if canCommit}
                    <button type="button" class="border-border bg-card hover:bg-muted inline-flex min-h-11 w-full items-center justify-center gap-2 rounded-xl border px-4 py-2.5 text-sm font-semibold transition-colors" onclick={() => onCommit?.()}>
                        <Check class="size-4" aria-hidden="true" />
                        Commit &amp; Finalize Batch
                    </button>
                {/if}
            </footer>
        </section>
    </div>
{/if}

<style>
    .queue-surface {
        animation: queue-in 220ms cubic-bezier(0.2, 0.8, 0.2, 1) both;
    }

    @keyframes queue-in {
        from { opacity: 0.6; transform: translateY(1rem) scale(0.99); }
        to { opacity: 1; transform: translateY(0) scale(1); }
    }

    @media (min-width: 1024px) {
        .queue-surface { animation-name: queue-in-desktop; }
        @keyframes queue-in-desktop {
            from { opacity: 0; transform: translateY(0.5rem) scale(0.98); }
            to { opacity: 1; transform: translateY(0) scale(1); }
        }
    }

    @media (prefers-reduced-motion: reduce) {
        .queue-surface { animation: none; }
    }
</style>
