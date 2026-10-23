<script lang="ts">
    import type { Snippet } from 'svelte';
    import { ArrowLeft, Camera, RefreshCw, Trash2, Upload } from 'lucide-svelte';
    import OmrRegionCrop from '../Diagnostics/OmrRegionCrop.svelte';

    let {
        captureContent,
        activeStageView = 'live',
        evidencePage = null,
        activeSheet = null,
        sheets = [],
        activeSheetId = null,
        onSelectSheet,
        onSwitchToLive,
        onSwitchToGallery,
        onRetakeSheet,
        onDiscardSheet,
    }: {
        captureContent?: Snippet;
        activeStageView?: 'live' | 'gallery' | 'review';
        evidencePage?: unknown;
        activeSheet?: any;
        sheets?: Array<any>;
        activeSheetId?: number | null;
        onSelectSheet?: (sheet: any) => void;
        onSwitchToLive?: () => void;
        onSwitchToGallery?: () => void;
        onRetakeSheet?: (sheet: any) => void;
        onDiscardSheet?: (sheet: any) => void;
    } = $props();

    let activeCaptureType = $state<'auto' | 'single' | 'batch'>('auto');
    let autoSnapEnabled = $state(true);
    let canvasMode = $state<'raw' | 'stencil' | 'markings'>('markings');
    let activeAptitudeArea = $state('all');

    const evidenceRecord = $derived(
        evidencePage !== null && typeof evidencePage === 'object'
            ? (evidencePage as Record<string, any>)
            : {},
    );
    const evidenceGroups = $derived(
        Array.isArray(evidenceRecord.groups) ? evidenceRecord.groups : [],
    );
    const aptitudeAreas = $derived(
        evidenceGroups.filter(
            (group: any) =>
                group.group_type === 'aptitude_area' ||
                group.area_key !== null && group.area_key !== undefined,
        ),
    );
    const evidenceItems = $derived(
        Array.isArray(evidenceRecord.items) ? evidenceRecord.items : [],
    );
    const visibleEvidenceItems = $derived(
        activeAptitudeArea === 'all'
            ? evidenceItems
            : evidenceItems.filter(
                  (item: any) =>
                      item.area_key === activeAptitudeArea ||
                      item.group_type === activeAptitudeArea,
              ),
    );
    const focusItem = $derived(
        visibleEvidenceItems[0] ??
            (activeAptitudeArea === 'all' ? evidenceItems[0] : null),
    );
    const assetUrls = $derived(
        evidenceRecord.lazy_asset_urls &&
            typeof evidenceRecord.lazy_asset_urls === 'object'
            ? evidenceRecord.lazy_asset_urls
            : {},
    );
    const evidenceImageUrl = $derived(
        canvasMode === 'raw'
            ? (assetUrls.input ?? null)
            : canvasMode === 'markings'
              ? (assetUrls.overlay ?? assetUrls.aligned ?? assetUrls.input ?? null)
              : (assetUrls.aligned ?? assetUrls.input ?? null),
    );

    function sheetLabel(sheet: any): string {
        return String(
            sheet.detected_applicant_number ??
                sheet.client_capture_id ??
                `Sheet #${sheet.id}`,
        );
    }
</script>

<section
    class="relative flex h-full min-h-0 w-full flex-1 flex-col overflow-hidden select-none rounded-xl border border-slate-700 bg-slate-950 text-slate-100"
    data-omr-stage="capture"
    aria-label="OMR capture"
>
    {#if activeStageView === 'review'}
        <header
            class="h-12 shrink-0 px-4 border-b border-slate-800 bg-slate-900/60 flex items-center justify-between font-mono text-xs z-10"
        >
            <div class="flex min-w-0 items-center gap-2">
                <button
                    type="button"
                    onclick={() => onSwitchToLive?.()}
                    class="inline-flex items-center gap-1.5 text-xs text-slate-300 hover:text-white px-2.5 py-1 rounded-lg border border-slate-800 bg-slate-950 hover:bg-slate-900 transition-colors"
                >
                    <ArrowLeft class="size-3.5 text-emerald-400" aria-hidden="true" />
                    <span>Back to Camera</span>
                </button>
                <span class="text-slate-700">|</span>
                <span class="font-bold text-slate-100 font-sans text-xs truncate">
                    {activeSheet
                        ? (activeSheet.detected_applicant_number ??
                          activeSheet.client_capture_id ??
                          `Sheet #${activeSheet.id}`)
                        : 'Sheet Inspection'}
                </span>
                {#if onRetakeSheet && activeSheet?.capture_retake}
                    <button
                        type="button"
                        onclick={() => onRetakeSheet?.(activeSheet)}
                        class="inline-flex items-center gap-1.5 text-xs text-rose-400 hover:text-rose-300 px-2 py-0.5 rounded-lg border border-rose-900/50 bg-rose-950/40 hover:bg-rose-900/50 transition-colors font-sans"
                        title="Discard and retake this sheet"
                    >
                        <RefreshCw class="size-3 text-rose-400" aria-hidden="true" />
                        <span>Retake</span>
                    </button>
                {/if}
                {#if onDiscardSheet && activeSheet}
                    <button
                        type="button"
                        onclick={() => onDiscardSheet?.(activeSheet)}
                        class="inline-flex items-center gap-1 text-xs text-rose-400 hover:text-rose-300 px-2 py-0.5 rounded-lg border border-rose-900/50 bg-rose-950/40 hover:bg-rose-900/50 transition-colors font-sans"
                        title="Permanently discard this sheet"
                    >
                        <Trash2 class="size-3 text-rose-400" aria-hidden="true" />
                        <span>Discard</span>
                    </button>
                {/if}
            </div>
            <div class="flex items-center gap-1 bg-slate-950 p-1 rounded-lg border border-slate-800 text-[11px] font-mono">
                <button
                    type="button"
                    class="px-2 py-0.5 rounded font-bold {canvasMode === 'raw'
                        ? 'bg-slate-800 text-white'
                        : 'text-slate-300 hover:text-white'}"
                    aria-pressed={canvasMode === 'raw'}
                    onclick={() => (canvasMode = 'raw')}>Raw</button
                >
                <button
                    type="button"
                    class="px-2 py-0.5 rounded font-bold {canvasMode === 'stencil'
                        ? 'bg-emerald-500/20 text-emerald-300 border border-emerald-500/40'
                        : 'text-slate-300 hover:text-white'}"
                    aria-pressed={canvasMode === 'stencil'}
                    onclick={() => (canvasMode = 'stencil')}>Stencil</button
                >
                <button
                    type="button"
                    class="px-2 py-0.5 rounded font-bold {canvasMode === 'markings'
                        ? 'bg-slate-800 text-white'
                        : 'text-slate-300 hover:text-white'}"
                    aria-pressed={canvasMode === 'markings'}
                    onclick={() => (canvasMode = 'markings')}>Markings</button
                >
            </div>
        </header>

        {#if aptitudeAreas.length > 0}
            <nav
                class="shrink-0 flex items-center gap-1 overflow-x-auto border-b border-slate-800 bg-slate-900/30 px-3 py-1.5 font-mono text-[11px]"
                aria-label="Aptitude areas"
            >
                <button
                    type="button"
                    class="shrink-0 rounded-md px-2.5 py-1 {activeAptitudeArea ===
                    'all'
                        ? 'bg-slate-800 text-slate-100'
                        : 'text-slate-400 hover:text-slate-200'}"
                    aria-pressed={activeAptitudeArea === 'all'}
                    onclick={() => (activeAptitudeArea = 'all')}>All areas</button
                >
                {#each aptitudeAreas as area (area.key ?? area.area_key)}
                    {@const areaKey = area.area_key ?? area.key}
                    <button
                        type="button"
                        class="shrink-0 rounded-md px-2.5 py-1 {activeAptitudeArea ===
                        areaKey
                            ? 'bg-emerald-500/20 text-emerald-300'
                            : 'text-slate-400 hover:text-slate-200'}"
                        aria-pressed={activeAptitudeArea === areaKey}
                        onclick={() => (activeAptitudeArea = areaKey)}
                    >
                        {area.label ?? areaKey}
                    </button>
                {/each}
            </nav>
        {/if}

        <div
            class="flex-1 min-h-0 relative flex items-center justify-center p-3 overflow-hidden bg-slate-950"
        >
            {#if evidenceImageUrl}
                <div class="grid h-full min-h-0 w-full grid-cols-1 gap-3 overflow-hidden md:grid-cols-[minmax(0,1.2fr)_minmax(0,0.8fr)]">
                    <div class="flex min-h-0 items-center justify-center overflow-hidden rounded-lg border border-slate-800 bg-slate-900/60 p-2">
                        <img
                            src={evidenceImageUrl}
                            alt="Captured answer sheet evidence"
                            class="max-h-full max-w-full object-contain"
                        />
                    </div>
                    {#if focusItem}
                        <div class="flex min-h-0 flex-col justify-center overflow-hidden rounded-lg border border-amber-400/40 bg-slate-900/60 p-3">
                            <div class="mb-2 flex items-center justify-between gap-2 font-mono text-[10px] text-amber-300">
                                <span class="truncate">{focusItem.area_key ?? 'Sheet'} · {focusItem.label ?? `Question ${focusItem.question_no ?? ''}`}</span>
                                <span class="shrink-0 rounded bg-amber-400/10 px-1.5 py-0.5">FOCUSED CROP</span>
                            </div>
                            <div class="min-h-0 overflow-hidden">
                                <OmrRegionCrop
                                    item={focusItem}
                                    imageUrl={assetUrls.aligned ?? evidenceImageUrl}
                                    region={focusItem.crop_rect ?? focusItem.region}
                                    options={focusItem.options ?? []}
                                    mode={canvasMode}
                                    alt={`Focused evidence for ${focusItem.label ?? focusItem.question_no ?? 'selected item'}`}
                                    title="Focused answer area"
                                    width={360}
                                    id="workbench-document-viewer-crop"
                                />
                            </div>
                        </div>
                    {/if}
                </div>
            {:else}
                <div class="text-center font-mono text-xs text-slate-500">
                    Evidence image is unavailable for this sheet.
                </div>
            {/if}
        </div>

        <footer
            class="p-2.5 border-t border-slate-800 bg-slate-900/60 font-mono text-xs z-10 shrink-0"
        >
            <div class="mb-2 flex items-center justify-between text-slate-400">
                <span>Batch Sheets:</span>
                <span class="text-[10px]">{sheets.length} sheets</span>
            </div>
            <div class="flex items-center gap-2 overflow-x-auto pb-0.5">
                {#each sheets as sheet (sheet.id)}
                    <button
                        type="button"
                        class="inline-flex shrink-0 items-center gap-1.5 rounded-lg border px-2.5 py-1.5 font-mono text-[11px] transition-colors {sheet.id ===
                        activeSheetId
                            ? 'border-amber-400 bg-amber-400/10 text-amber-300'
                            : 'border-slate-800 bg-slate-950 text-slate-400 hover:text-slate-200'}"
                        aria-pressed={sheet.id === activeSheetId}
                        onclick={() => onSelectSheet?.(sheet)}
                    >
                        <span>#{sheet.id} {sheetLabel(sheet)}</span>
                        {#if sheet.id === activeSheetId}
                            <span class="size-1.5 rounded-full bg-amber-400" aria-label="Selected"></span>
                        {:else if sheet.reviewed_at || sheet.status === 'reviewed'}
                            <span class="text-emerald-400" aria-label="Reviewed">✓</span>
                        {/if}
                    </button>
                {/each}
            </div>
        </footer>
    {:else}
        <div class="flex-1 min-h-0 relative flex flex-col overflow-hidden" class:live-capture-content={activeStageView === 'live'}>
            {#if activeStageView === 'gallery'}
                <div class="flex h-full min-w-0 w-full flex-col gap-3 overflow-hidden p-3 font-mono text-xs">
                    <div class="flex items-center justify-between gap-3 border-b border-border/60 pb-2">
                        <span class="font-sans font-bold text-sm text-foreground">Scans Gallery</span>
                        <button
                            type="button"
                            class="border-border bg-card text-foreground hover:bg-muted inline-flex shrink-0 items-center gap-1.5 rounded-lg border px-3 py-1.5 font-bold"
                            onclick={() => onSwitchToLive?.()}
                        >
                            <Camera class="size-3.5" aria-hidden="true" />
                            Return to Camera
                        </button>
                    </div>
                    <div class="grid min-h-0 flex-1 grid-cols-2 gap-3 overflow-y-auto p-1 sm:grid-cols-4">
                        {#each sheets as sheet (sheet.id)}
                            {@const thumbUrl = sheet.lazy_asset_urls?.input ?? sheet.asset_urls?.thumbnail ?? sheet.asset_urls?.input ?? null}
                            <button
                                type="button"
                                class="group min-w-0 rounded-lg p-1 text-left transition hover:bg-muted/50 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-primary border border-border/60 bg-card"
                                class:ring-2={sheet.id === activeSheetId}
                                class:ring-primary={sheet.id === activeSheetId}
                                aria-label={`Open sheet ${sheetLabel(sheet)}`}
                                onclick={() => onSelectSheet?.(sheet)}
                            >
                                <div class="aspect-[3/4] rounded-lg border border-border/40 bg-slate-900/60 overflow-hidden relative flex items-center justify-center">
                                    {#if thumbUrl}
                                        <img
                                            src={thumbUrl}
                                            alt={`Scan #${sheet.id}`}
                                            class="w-full h-full object-cover"
                                            loading="lazy"
                                        />
                                    {:else}
                                        <div class="p-2 text-center text-[10px] text-muted-foreground font-mono">
                                            Scan #{sheet.id}
                                        </div>
                                    {/if}
                                    <div class="absolute top-1 right-1">
                                        <span class="px-1.5 py-0.5 rounded text-[9px] font-bold {sheet.recovery ? 'bg-amber-500/20 text-amber-400 border border-amber-500/40' : 'bg-emerald-500/20 text-emerald-400 border border-emerald-500/40'}">
                                            {sheet.recovery ? 'Needs Check' : 'Clean'}
                                        </span>
                                    </div>
                                </div>
                                <div class="px-1 pt-2">
                                    <div class="text-foreground truncate font-sans text-xs font-bold">{sheetLabel(sheet)}</div>
                                    <div class:text-amber-500={sheet.recovery} class:font-semibold={sheet.recovery} class:text-muted-foreground={!sheet.recovery} class="text-[11px]">{sheet.recovery ? 'Needs Check' : 'Clean'}</div>
                                </div>
                            </button>
                        {/each}
                    </div>
                    <div class="border-border/70 text-muted-foreground flex items-center justify-between border-t pt-3 text-xs">
                        <span>Showing {sheets.length} sheet{sheets.length === 1 ? '' : 's'}</span>
                        <button
                            type="button"
                            class="text-primary hover:text-primary/80 font-sans font-bold"
                            onclick={() => onSwitchToLive?.()}
                        >Ingest New Sheet</button>
                    </div>
                </div>
            {:else if captureContent}
                {@render captureContent()}
            {/if}
        </div>
    {/if}
</section>

<style>
    .live-capture-content {
        overflow-y: auto;
        color: var(--foreground);
    }

    .live-capture-content > :global([data-omr-camera-deck]:not(:first-child)) {
        flex: 1 0 20rem;
        min-height: 20rem;
    }
</style>

