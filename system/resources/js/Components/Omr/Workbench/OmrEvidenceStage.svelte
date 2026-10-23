<script lang="ts">
    import { onDestroy } from 'svelte';
    import {
        AlertTriangle,
        Check,
        ChevronLeft,
        ChevronRight,
        Circle,
        Hand,
        Save,
        RefreshCw,
        Trash2,
    } from 'lucide-svelte';
    import OmrRegionCrop from '../Diagnostics/OmrRegionCrop.svelte';
    import OmrSheetEvidenceCanvas from '../Diagnostics/OmrSheetEvidenceCanvas.svelte';
    import type { EvidencePage } from '@/lib/omrDiagnosticEvidence';
    import type { OmrWorkbenchSheet } from '@/lib/omrWorkbench';

    type RecordValue = Record<string, unknown>;
    type ReviewReason = { code: string; label: string };
    type BubbleItem = RecordValue & {
        id?: number | string | null;
        item_id?: string | null;
        label?: string;
        question_no?: number | null;
        options?: Array<{ code: string; mean?: number | null }>;
    };

    let {
        evidencePage = null,
        contextSummary = {},
        sheets = [],
        activeSheet = null,
        activeSheetId = null,
        stageView = 'review',
        selectedItemKey = null,
        selectedDecision = null,
        selectedReason = '',
        selectedNote = '',
        saving = false,
        saveError = null,
        onSelectSheet,
        onSelectBubbleItem,
        onDecisionChange,
        onReasonChange,
        onNoteChange,
        onSaveDecision,
        onSaveAndNext,
        onPageChange,
        onGroupChange,
        onFilterChange,
        onViewChange,
        focusOpen = false,
        onFocusOpenChange,
        onRetakeSheet,
        onDiscardSheet,
    }: {
        evidencePage?: EvidencePage | RecordValue | null;
        contextSummary?: RecordValue;
        sheets?: OmrWorkbenchSheet[];
        activeSheet?: OmrWorkbenchSheet | null;
        activeSheetId?: number | null;
        stageView?: 'review';
        selectedItemKey?: string | null;
        selectedDecision?: string | null;
        selectedReason?: string;
        selectedNote?: string;
        saving?: boolean;
        saveError?: string | null;
        focusOpen?: boolean;
        onFocusOpenChange?: (open: boolean) => void;
        onSelectSheet?: (sheet: OmrWorkbenchSheet) => void;
        onSelectBubbleItem?: (item: BubbleItem) => void;
        onDecisionChange?: (item: BubbleItem, mark: string) => void;
        onReasonChange?: (item: BubbleItem, reason: string) => void;
        onNoteChange?: (item: BubbleItem, note: string) => void;
        onSaveDecision?: () => void | Promise<void>;
        onSaveAndNext?: () => void | Promise<void>;
        onPageChange?: (value: RecordValue) => void;
        onGroupChange?: (value: RecordValue) => void;
        onFilterChange?: (value: RecordValue) => void;
        onViewChange?: (value: RecordValue) => void;
        onRetakeSheet?: (sheet: OmrWorkbenchSheet) => void;
        onDiscardSheet?: (sheet: OmrWorkbenchSheet) => void;
    } = $props();

    let dialogEl = $state<HTMLDivElement | null>(null);
    let selectedItem = $state<BubbleItem | null>(null);
    let mobilePane = $state<'sheet' | 'decision'>('sheet');
    let previousFocus: HTMLElement | null = null;
    let previousSheetId = activeSheetId;
    let canvasMode = $state<'raw' | 'markings'>('markings');
    const pageRecord = $derived(asRecord(evidencePage));
    const sheetMap = $derived(asRecord(pageRecord.sheet_map));
    const lazyAssets = $derived(asRecord(pageRecord.lazy_asset_urls));
    const imageUrl = $derived(
        stringValue(lazyAssets.overlay) ??
            stringValue(lazyAssets.aligned) ??
            stringValue(lazyAssets.input),
    );
    const candidateNameField = $derived.by(() => {
        const fields = Array.isArray(sheetMap.document_fields)
            ? sheetMap.document_fields
            : [];
        return (
            fields
                .map(asRecord)
                .find((field) => field.type === 'candidate_name') ?? null
        );
    });
    const identityText = $derived.by(() => {
        const identity = asRecord(sheetMap.identity_write_in);
        const fromSheet =
            stringValue(identity.detected_value) ??
            stringValue(identity.value) ??
            stringValue(identity.applicant_number) ??
            stringValue(identity.examinee_id);
        return fromSheet ?? stringValue(contextSummary.examinee_id) ?? '—';
    });
    const candidateName = $derived(
        stringValue(candidateNameField?.value) ??
            stringValue(candidateNameField?.text) ??
            stringValue(contextSummary.applicant_name) ??
            'Not identified',
    );
    const reasons = $derived(
        Array.isArray(pageRecord.review_reasons)
            ? (pageRecord.review_reasons as ReviewReason[])
            : [],
    );
    const evidenceItems = $derived(
        Array.isArray(pageRecord.items)
            ? (pageRecord.items as BubbleItem[])
            : [],
    );
    const selectedEvidenceItem = $derived.by(() => {
        if (selectedItem === null) return null;
        const key = itemKey(selectedItem);
        return evidenceItems.find((item) => itemKey(item) === key) ?? null;
    });
    const selectedCropItem = $derived.by(() => {
        const source = selectedEvidenceItem ?? selectedItem;
        if (!source) return null;
        return {
            ...source,
            crop_rect: source.crop_rect ?? source.region,
            label:
                source.label ??
                `Question ${source.question_no ?? ''}`.trim(),
        };
    });
    const options = $derived(
        Array.isArray(selectedCropItem?.options)
            ? selectedCropItem.options
            : [],
    );
    const filmstripPosition = $derived(
        sheets.findIndex((sheet) => sheet.id === activeSheetId),
    );
    const currentPage = $derived(Number(pageRecord.page ?? 1));

    function asRecord(value: unknown): RecordValue {
        return value !== null && typeof value === 'object'
            ? (value as RecordValue)
            : {};
    }

    function stringValue(value: unknown): string | null {
        if (typeof value === 'string' && value.trim() !== '') return value;
        if (typeof value === 'number' && Number.isFinite(value)) return String(value);
        return null;
    }

    function itemKey(item: BubbleItem): string {
        return String(item.id ?? item.item_id ?? item.label ?? item.question_no ?? '');
    }

    function displayValue(...values: unknown[]): string {
        for (const value of values) {
            const text = stringValue(value);
            if (text !== null) return text;
        }
        return '—';
    }
    function densityLabel(option: { code: string; mean?: number | null }): string {
        const means = asRecord(selectedEvidenceItem?.means);
        const machine = asRecord(selectedEvidenceItem?.machine);
        const observations = Array.isArray(machine.option_observations)
            ? machine.option_observations.map(asRecord)
            : [];
        const observation = observations.find((entry) => entry.code === option.code);
        const raw = option.mean ?? means[option.code] ?? observation?.mean;
        if (raw === null || raw === undefined || raw === '') return '—';
        const density = Number(raw);
        if (!Number.isFinite(density)) return '—';
        return `${Math.round(density >= 0 && density <= 1 ? density * 100 : density)}%`;
    }

    function selectBubble(item: BubbleItem): void {
        selectedItem = item;
        mobilePane = 'decision';
        onSelectBubbleItem?.(item);
    }

    function chooseDecision(mark: string): void {
        if (!selectedEvidenceItem) return;
        onDecisionChange?.(selectedEvidenceItem, mark);
    }

    function chooseReason(reason: string): void {
        if (!selectedEvidenceItem) return;
        onReasonChange?.(selectedEvidenceItem, reason);
    }
    function chooseNote(note: string): void {
        if (!selectedEvidenceItem) return;
        onNoteChange?.(selectedEvidenceItem, note);
    }

    function moveSheet(offset: number): void {
        const next = sheets[filmstripPosition + offset];
        if (next) onSelectSheet?.(next);
    }

    function openFocus(): void {
        previousFocus = typeof document !== 'undefined' ? (document.activeElement as HTMLElement) : null;
        onFocusOpenChange?.(true);
    }
    function closeFocus(): void {
        onFocusOpenChange?.(false);
    }

    function restoreFocus(): void {
        const opener = previousFocus;
        previousFocus = null;
        if (opener instanceof HTMLElement && typeof document !== 'undefined' && document.contains(opener)) {
            opener.focus();
        }
    }

    onDestroy(restoreFocus);

    function handleKeydown(event: KeyboardEvent): void {
        if (focusOpen) {
            if (event.key === 'Escape') {
                event.preventDefault();
                closeFocus();
                return;
            }
            if (event.key !== 'Tab') return;

            const elements = [...(dialogEl?.querySelectorAll<HTMLElement>(
                'button:not([disabled]), a[href], input:not([disabled]), select:not([disabled]), textarea:not([disabled]), [tabindex]:not([tabindex="-1"])',
            ) ?? [])].filter(
                (el) =>
                    !el.hidden &&
                    el.getAttribute('aria-hidden') !== 'true' &&
                    (el.offsetParent !== null || el.offsetWidth > 0),
            );

            if (elements.length > 0) {
                const first = elements[0];
                const last = elements.at(-1);
                const active = document.activeElement;

                if (active === dialogEl || !dialogEl?.contains(active)) {
                    event.preventDefault();
                    first?.focus();
                    return;
                }

                if (active === first && event.shiftKey) {
                    event.preventDefault();
                    first?.focus();
                } else if (!event.shiftKey && active === last) {
                    event.preventDefault();
                    first?.focus();
                }
            }
            return;
        }
        if (stageView !== 'review' || event.defaultPrevented) return;
        const target = event.target;
        if (
            target instanceof HTMLElement &&
            target.closest('input, textarea, select, button, [contenteditable="true"]')
        ) return;
        if (event.key === 'ArrowLeft') {
            event.preventDefault();
            moveSheet(-1);
        } else if (event.key === 'ArrowRight') {
            event.preventDefault();
            moveSheet(1);
        }
    }

    function changeMode(mode: 'raw' | 'markings'): void {
        canvasMode = mode;
        emitViewChange();
    }

    function emitViewChange(): void {
        const state = { area: pageRecord.area ?? null, filter: pageRecord.filter ?? null, page: currentPage };
        onViewChange?.(state);
        onPageChange?.({ page: currentPage });
        onGroupChange?.({ area: pageRecord.area ?? null });
        onFilterChange?.({ filter: pageRecord.filter ?? null });
    }

    $effect(() => {
        if (focusOpen && dialogEl) {
            dialogEl.focus();
        } else if (!focusOpen && previousFocus) {
            restoreFocus();
        }
    });

    $effect(() => {
        selectedDecision;
        selectedReason;
        if (selectedItemKey !== null) {
            selectedItem =
                evidenceItems.find(
                    (item) => itemKey(item) === selectedItemKey,
                ) ?? selectedItem;
        }
    });
</script>

<svelte:window onkeydown={handleKeydown} />

<div
    role={focusOpen ? 'dialog' : 'region'}
    aria-modal={focusOpen ? 'true' : undefined}
    aria-label={focusOpen ? 'Focused OMR evidence review' : 'OMR evidence review'}
    tabindex={focusOpen ? -1 : undefined}
    bind:this={dialogEl}
    class={focusOpen
        ? 'fixed inset-0 z-50 flex items-stretch justify-stretch bg-black/60 backdrop-blur-sm sm:items-center sm:justify-center sm:p-4'
        : 'bg-background text-foreground flex h-full min-h-0 w-full flex-col overflow-hidden'}
    data-omr-evidence-stage
    data-stage-view={stageView}
>
    <div class={focusOpen
        ? 'bg-background text-foreground flex h-full min-h-0 w-full flex-col overflow-hidden sm:h-[calc(100dvh-2rem)] sm:w-[calc(100dvw-2rem)] sm:max-h-[56rem] sm:max-w-[96rem] sm:rounded-xl sm:border sm:border-border sm:shadow-2xl'
        : 'flex h-full min-h-0 w-full flex-col overflow-hidden'}>
    <header class="border-border bg-card grid shrink-0 grid-cols-2 gap-3 border-b px-3 py-3 sm:px-5 xl:grid-cols-[minmax(0,1fr)_minmax(0,1fr)_minmax(0,1.4fr)_auto] xl:items-center">
        <div class="min-w-0">
            <p class="text-muted-foreground text-[10px] font-semibold uppercase tracking-[0.16em]">Detected Applicant Number (read-only)</p>
            <div class="mt-1 flex flex-wrap gap-1" aria-label={`Detected Applicant Number ${identityText}, read-only`}>
                {#each [...identityText] as digit, index (`${index}-${digit}`)}
                    <span class="border-border bg-background flex size-7 items-center justify-center rounded-md border font-mono text-sm font-bold">{digit}</span>
                {/each}
            </div>
        </div>
        <div class="flex min-w-0 items-center gap-3">
            {#if candidateNameField && imageUrl}
                <div class="border-border bg-muted/30 h-12 w-24 shrink-0 overflow-hidden rounded-md border">
                    <OmrRegionCrop
                        item={candidateNameField}
                        imageUrl={stringValue(lazyAssets.aligned) ?? imageUrl}
                        region={candidateNameField.region ?? candidateNameField.crop_rect}
                        alt="Candidate name field crop"
                        title="Candidate name"
                        width={240}
                        id={`candidate-name-${activeSheetId ?? 'sheet'}`}
                    />
                </div>
                <div class="min-w-0">
                    <p class="text-muted-foreground text-[10px] font-semibold uppercase tracking-[0.16em]">Candidate</p>
                    <p class="text-foreground truncate text-sm font-semibold" title={candidateName}>{candidateName}</p>
                </div>
            {:else}
                <div class="min-w-0 rounded-full border border-border bg-muted/30 px-3 py-1.5">
                    <p class="text-muted-foreground text-[9px] font-semibold uppercase tracking-[0.16em]">Candidate</p>
                    <p class="text-foreground truncate text-sm font-semibold" title={candidateName}>{candidateName}</p>
                </div>
            {/if}
        </div>
        <dl class="col-span-full grid min-w-0 grid-cols-3 gap-2 xl:col-span-1">
            <div class="min-w-0"><dt class="text-muted-foreground text-[9px] uppercase">Answer Sheet Serial</dt><dd class="truncate font-semibold">{displayValue(activeSheet?.serial, activeSheet?.serial_number, activeSheet?.client_capture_id, contextSummary.answer_sheet_serial, contextSummary.answer_sheet_number)}</dd></div>
            <div class="min-w-0"><dt class="text-muted-foreground text-[9px] uppercase">Sheet Number</dt><dd class="truncate font-semibold">{displayValue(activeSheet?.sheet_number, activeSheet?.id, contextSummary.sheet_number)}</dd></div>
            <div class="min-w-0"><dt class="text-muted-foreground text-[9px] uppercase">Room / Session</dt><dd class="truncate font-semibold">{displayValue(contextSummary.room_name, asRecord(contextSummary.room).name, contextSummary.room, contextSummary.exam_session, contextSummary.session_name, contextSummary.Session, contextSummary.session)}</dd></div>
        </dl>
        <nav class="col-span-full flex gap-2 xl:hidden" aria-label="Review pane">
            <button type="button" class={`flex-1 rounded-md border px-3 py-2 text-xs font-semibold ${mobilePane === 'sheet' ? 'border-primary bg-primary/10 text-primary' : 'border-border bg-background'}`} aria-pressed={mobilePane === 'sheet'} onclick={() => (mobilePane = 'sheet')}>Sheet</button>
            <button type="button" class={`flex-1 rounded-md border px-3 py-2 text-xs font-semibold ${mobilePane === 'decision' ? 'border-primary bg-primary/10 text-primary' : 'border-border bg-background'}`} aria-pressed={mobilePane === 'decision'} onclick={() => (mobilePane = 'decision')}>Decision</button>
        </nav>
        <div class="col-span-full flex flex-wrap items-center gap-2 xl:contents" aria-label="Selected page actions">
        <button
            type="button"
            class="border-border bg-background text-foreground hover:bg-muted min-h-11 rounded-md border px-3 py-2 text-xs font-semibold"
            aria-label="Focus evidence"
            hidden={focusOpen}
            disabled={focusOpen}
            onclick={openFocus}
        >Focus evidence</button>
        {#if onRetakeSheet && activeSheet?.capture_retake}
            <button
                type="button"
                class="border-rose-500/40 bg-rose-500/10 text-rose-700 dark:text-rose-300 hover:bg-rose-500/20 inline-flex min-h-11 items-center gap-1.5 rounded-md border px-3 py-2 text-xs font-semibold"
                aria-label="Retake page"
                onclick={() => onRetakeSheet?.(activeSheet)}
            >
                <RefreshCw class="size-3.5" aria-hidden="true" />
                <span>Retake page</span>
            </button>
        {/if}
        {#if onDiscardSheet && activeSheet}
            <button
                type="button"
                class="border-rose-600/40 bg-rose-600/10 text-rose-700 dark:text-rose-300 hover:bg-rose-600/20 inline-flex min-h-11 items-center gap-1.5 rounded-md border px-3 py-2 text-xs font-semibold"
                aria-label="Remove capture"
                onclick={() => onDiscardSheet?.(activeSheet)}
            >
                <Trash2 class="size-3.5" aria-hidden="true" />
                <span>Remove capture</span>
            </button>
        {/if}
        {#if focusOpen}
            <button type="button" class="border-border bg-background text-foreground hover:bg-muted rounded-md border px-3 py-2 text-xs font-semibold" aria-label="Close focus view" onclick={closeFocus}>Close</button>
        {/if}
        </div>
    </header>

    <div class="grid min-h-0 flex-1 grid-cols-1 grid-rows-[minmax(0,1fr)] overflow-hidden xl:grid-cols-[minmax(0,7fr)_minmax(20rem,2.3fr)]">
        <main class={`border-border min-h-0 min-w-0 flex-col overflow-hidden xl:flex xl:border-r ${mobilePane === 'sheet' ? 'flex' : 'hidden'}`}>
            <div class="border-border bg-background flex shrink-0 items-center justify-between gap-2 border-b px-3 py-2">
                <div class="flex min-w-0 items-center gap-2">
                    <Hand class="text-muted-foreground size-4 shrink-0" aria-hidden="true" />
                    <span class="truncate text-xs font-semibold">Paper Evidence Theater</span>
                    <span class="text-muted-foreground hidden text-[10px] sm:inline">Drag to pan · scroll or buttons to zoom</span>
                </div>
                <div class="flex shrink-0 items-center gap-1">
                    <div class="border-border bg-muted/40 flex rounded-md border p-0.5" role="group" aria-label="Evidence image mode">
                        <button type="button" class={`rounded px-2 py-1 text-[10px] ${canvasMode === 'raw' ? 'bg-card text-foreground shadow-xs' : 'text-muted-foreground'}`} aria-pressed={canvasMode === 'raw'} onclick={() => changeMode('raw')}>Raw</button>
                        <button type="button" class={`rounded px-2 py-1 text-[10px] ${canvasMode === 'markings' ? 'bg-card text-foreground shadow-xs' : 'text-muted-foreground'}`} aria-pressed={canvasMode === 'markings'} onclick={() => changeMode('markings')}>Markings</button>
                    </div>
                </div>
            </div>
            <div class="bg-muted/20 min-h-0 flex-1 overflow-auto p-2 sm:p-3">
                {#if imageUrl && Number(sheetMap.width) > 0 && Number(sheetMap.height) > 0}
                    <div class="mx-auto h-full min-h-0 w-full max-w-5xl">
                        <OmrSheetEvidenceCanvas
                            {sheetMap}
                            imageUrl={canvasMode === 'raw' ? (stringValue(lazyAssets.input) ?? imageUrl) : imageUrl}
                            selectedId={selectedItemKey ?? (selectedItem ? itemKey(selectedItem) : null)}
                            blind={pageRecord.blind_review === true}
                            mode={canvasMode}
                            onSelect={selectBubble}
                        />
                    </div>
                {:else}
                    <div class="border-border bg-card text-muted-foreground flex h-full min-h-0 items-center justify-center rounded-lg border border-dashed p-6 text-center text-sm" role="status">Sheet geometry or evidence image is unavailable.</div>
                {/if}
            </div>
        </main>

        <aside class={`border-border bg-card min-h-0 min-w-0 flex-col overflow-hidden border-t xl:flex xl:border-t-0 ${mobilePane === 'decision' ? 'flex' : 'hidden'}`} aria-label="Item decision drawer">
            <header class="border-border flex shrink-0 items-center justify-between gap-2 border-b px-4 py-3">
                <div class="min-w-0"><h2 class="text-sm font-bold">Item Decision</h2><p class="text-muted-foreground truncate text-[11px]">{selectedCropItem?.label ?? 'Select a bubble or item on the sheet'}</p></div>
                <div class="flex items-center gap-2">
                    {#if selectedEvidenceItem}
                        <span class="border-amber-500/30 bg-amber-500/10 text-amber-700 dark:text-amber-300 inline-flex shrink-0 items-center gap-1 rounded border px-2 py-1 font-mono text-[9px] font-bold"><AlertTriangle class="size-3" /> REVIEW</span>
                    {/if}
                    <button type="button" class="border-border rounded-md border px-2 py-1 text-[10px] font-semibold xl:hidden" onclick={() => (mobilePane = 'sheet')}>Back to Sheet</button>
                </div>
            </header>
            <div class="min-h-0 flex-1 space-y-3 overflow-y-auto p-3">
                {#if selectedCropItem}
                    {#if imageUrl}
                        <div class="border-border bg-muted/20 flex min-h-32 items-center justify-center overflow-hidden rounded-lg border p-2">
                            <OmrRegionCrop
                                item={selectedCropItem}
                                imageUrl={stringValue(lazyAssets.aligned) ?? imageUrl}
                                region={selectedCropItem.crop_rect}
                                options={selectedCropItem.options ?? []}
                                mode={canvasMode}
                                alt={`High-resolution crop for ${selectedCropItem.label}`}
                                title="Selected question crop"
                                width={460}
                                id={`selected-item-${itemKey(selectedCropItem)}`}
                            />
                        </div>
                    {/if}
                    <section class="border-border bg-background rounded-lg border p-3" aria-label="Density readings">
                        <h3 class="text-muted-foreground mb-2 text-[10px] font-bold uppercase tracking-wider">Bubble density readings</h3>
                        <div class="flex flex-wrap gap-2">
                            {#each options as option (option.code)}
                                <span class="border-border bg-muted/30 rounded-md border px-2 py-1 font-mono text-xs"><strong>{option.code}:</strong> {densityLabel(option)}</span>
                            {/each}
                        </div>
                    </section>
                    <fieldset class="grid grid-cols-5 gap-1.5" disabled={saving || !selectedEvidenceItem || selectedEvidenceItem.reviewable === false}>
                        <legend class="text-muted-foreground mb-1 text-[10px] font-bold uppercase">Decision</legend>
                        {#each options as option (option.code)}
                            <button type="button" class={`border-border rounded-lg border py-2 text-sm font-bold ${selectedDecision === option.code ? 'border-primary bg-primary text-primary-foreground' : 'bg-background hover:bg-muted'}`} aria-pressed={selectedDecision === option.code} onclick={() => chooseDecision(option.code)}>{option.code}</button>
                        {/each}
                        <button type="button" class={`border-border rounded-lg border py-2 text-xs font-semibold ${selectedDecision === 'blank' ? 'border-primary bg-primary text-primary-foreground' : 'bg-background hover:bg-muted'}`} aria-pressed={selectedDecision === 'blank'} onclick={() => chooseDecision('blank')}>Void</button>
                    </fieldset>
                    <label class="grid gap-1.5 text-xs">
                        <span class="text-muted-foreground font-semibold">Review reason</span>
                        <select class="border-border bg-background text-foreground min-w-0 rounded-md border px-3 py-2" value={selectedReason} disabled={saving || !selectedEvidenceItem} onchange={(event) => chooseReason(event.currentTarget.value)}>
                            <option value="">Select a reason</option>
                            {#each reasons as reason (reason.code)}<option value={reason.code}>{reason.label}</option>{/each}
                        </select>
                    </label>
                    <label class="grid gap-1.5 text-xs">
                        <span class="text-muted-foreground font-semibold">Review note</span>
                        <textarea
                            class="border-border bg-background text-foreground min-w-0 resize-y rounded-md border px-3 py-2"
                            rows="2"
                            maxlength={Number(pageRecord.review_note_max) || 500}
                            value={selectedNote}
                            disabled={saving || !selectedEvidenceItem}
                            oninput={(event) => chooseNote(event.currentTarget.value)}
                        ></textarea>
                    </label>
                {:else}
                    <div class="border-border bg-background text-muted-foreground flex min-h-40 items-center justify-center rounded-lg border border-dashed p-5 text-center text-sm">Choose a question bubble on the paper to inspect its crop and record a decision.</div>
                {/if}
                {#if saveError}
                    <p class="border-destructive/30 bg-destructive/10 text-destructive rounded-md border px-3 py-2 text-xs" role="alert">{saveError}</p>
                {/if}
            </div>
            <footer class="border-border bg-card grid shrink-0 grid-cols-1 gap-2 border-t p-3 sm:grid-cols-2 xl:grid-cols-1 2xl:grid-cols-2">
                <button type="button" class="border-border bg-background text-foreground hover:bg-muted inline-flex items-center justify-center gap-2 rounded-lg border px-3 py-2.5 text-xs font-semibold disabled:opacity-50" disabled={saving || !selectedDecision || !selectedEvidenceItem} onclick={() => void onSaveDecision?.()}><Save class="size-3.5" />Save Decision</button>
                <button type="button" class="bg-primary text-primary-foreground hover:bg-primary/90 inline-flex items-center justify-center gap-2 rounded-lg px-3 py-2.5 text-xs font-semibold disabled:opacity-50" disabled={saving || !selectedDecision || !selectedEvidenceItem} onclick={() => void onSaveAndNext?.()}>Save &amp; Next Flagged <ChevronRight class="size-3.5" /></button>
            </footer>
        </aside>
    </div>

    {#if !focusOpen}
        <footer class="border-border bg-card flex shrink-0 items-center gap-2 border-t px-2 py-2 sm:px-4" aria-label="Batch sheet navigation">
            <button type="button" class="border-border bg-background hover:bg-muted text-foreground inline-flex shrink-0 items-center gap-1 rounded-md border px-2 py-1.5 text-[10px] font-semibold sm:px-3 sm:text-xs" disabled={filmstripPosition <= 0} onclick={() => moveSheet(-1)}><ChevronLeft class="size-3.5" /><span>Prev Sheet</span></button>
            <div class="flex min-w-0 flex-1 items-center gap-1 overflow-x-auto py-0.5" role="tablist" aria-label="Sheets in this batch">
                {#each sheets as sheet (sheet.id)}
                    {@const active = sheet.id === activeSheetId}
                    {@const flagged = Boolean(sheet.recovery) || ['needs_review', 'review_required', 'failed'].includes(String(sheet.status ?? '').toLowerCase())}
                    {@const reviewed = Boolean(sheet.reviewed_at) || sheet.status === 'reviewed'}
                    <button type="button" role="tab" class={`border-border inline-flex shrink-0 items-center gap-1 rounded-md border px-2 py-1.5 font-mono text-[10px] ${active ? 'border-primary bg-primary/10 text-primary ring-1 ring-primary/30' : 'bg-background text-muted-foreground hover:bg-muted'}`} aria-selected={active} aria-label={`Open sheet #${sheet.id}${flagged ? ', needs review' : reviewed ? ', reviewed' : ''}`} onclick={() => onSelectSheet?.(sheet)}>
                        {#if active}<Circle class="size-2.5 fill-current text-primary" />{/if}
                        {#if flagged}<AlertTriangle class="size-3 text-amber-600" />{:else if reviewed}<Check class="size-3 text-emerald-600" />{/if}
                        <span>#{sheet.id}</span>
                    </button>
                {/each}
            </div>
            <button type="button" class="border-border bg-background hover:bg-muted text-foreground inline-flex shrink-0 items-center gap-1 rounded-md border px-2 py-1.5 text-[10px] font-semibold sm:px-3 sm:text-xs" disabled={filmstripPosition < 0 || filmstripPosition >= sheets.length - 1} onclick={() => moveSheet(1)}><span>Next Sheet</span><ChevronRight class="size-3.5" /></button>
        </footer>
    {/if}
</div>
</div>

