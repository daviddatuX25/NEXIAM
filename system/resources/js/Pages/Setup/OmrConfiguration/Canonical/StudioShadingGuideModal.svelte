<script>
    import {
        Dialog,
        DialogContent,
        DialogHeader,
        DialogTitle,
        DialogDescription,
        DialogFooter,
    } from '@/Components/ui/dialog';
    import {
        Check,
        CheckCircle2,
        XCircle,
        Printer,
        ShieldAlert,
        QrCode,
        Scan,
        Sparkles,
        AlertTriangle,
        ArrowRight,
        ArrowLeft,
        RotateCcw,
        Minus,
        Plus,
        FileImage,
        ChevronLeft,
        ChevronRight,
    } from 'lucide-svelte';

    let {
        open = $bindable(false),
        onOpenChange = () => {},
        challenge = null,
        method = null,
        isUploadedSource = false,
        onOpenPrintPdf = () => {},
    } = $props();

    let activeScreen = $state(1);
    let currentPage = $state(1);
    let zoom = $state(1);
    let panX = $state(0);
    let panY = $state(0);
    let isDragging = $state(false);

    let dragStartX = 0;
    let dragStartY = 0;
    let panStartX = 0;
    let panStartY = 0;
    let viewerContainer = $state(null);

    const guide = $derived(challenge?.guide ?? null);
    const guideSections = $derived(
        Array.isArray(guide?.sections) ? guide.sections : [],
    );
    const sheetPages = $derived(
        Array.isArray(challenge?.sheet_pages) ? challenge.sheet_pages : [],
    );
    const totalPages = $derived(
        Math.max(
            1,
            ...sheetPages.map((page) => Number(page?.page_no) || 1),
            ...guideSections.map((section) => Number(section?.page_no) || 1),
        ),
    );
    const activeSheetPage = $derived(
        sheetPages.find((p) => Number(p.page_no) === Number(currentPage)) ||
            sheetPages[0] ||
            null,
    );
    const activeGuideSections = $derived(
        guideSections.filter(
            (section) => Number(section?.page_no) === Number(currentPage),
        ),
    );
    const isHandShaded = $derived(
        method === 'hand_shaded' || guide?.method === 'hand_shaded',
    );
    const sheetImageUrl = $derived(
        activeSheetPage?.url ||
            (challenge?.print_mime?.startsWith?.('image/')
                ? challenge?.print_url
                : null) ||
            null,
    );

    const printPdfUrl = $derived(challenge?.print_url || null);
    const guidePdfUrl = $derived(
        challenge?.guide_pdf_url || challenge?.guide_url || null,
    );
    const isFullQualification = $derived(
        challenge?.mode === 'full' || guide?.mode === 'full',
    );

    const calibrationDisclaimer = $derived(
        guide?.calibration_disclaimer ||
            'Calibration-ID disclaimer: this challenge shades deterministic calibration digits derived from the mapped identity grid (if one is mapped). They exist for THIS challenge only — they are not a real applicant identity and never enter grading.',
    );
    const isReferencePreview = $derived(
        Boolean(
            activeSheetPage?.reference_only ||
                isUploadedSource ||
                challenge?.renderer_version === 'supplier_original',
        ),
    );
    const hasReferenceViewer = $derived(
        sheetPages.length > 0 &&
            (activeSheetPage?.reference_only ||
                isUploadedSource ||
                challenge?.renderer_version === 'supplier_original') &&
            Boolean(sheetImageUrl),
    );
    const activePageTargetCount = $derived(
        activeSheetPage?.bubbles?.filter((bubble) => bubble.shaded).length ??
            activeSheetPage?.target_count ??
            0,
    );
    const selectedSheetPage = $derived(currentPage);

    function displayMarkLabel(label) {
        const value = String(label ?? '');
        const question = value.match(/(?:_Q_?|_)(\d+)$/);

        return question ? `Question ${Number(question[1])}` : value;
    }

    function clampZoom(val) {
        return Math.min(3.0, Math.max(0.5, Math.round(val * 100) / 100));
    }

    function resetZoom() {
        zoom = 1;
        panX = 0;
        panY = 0;
        isDragging = false;
    }

    function zoomBy(step) {
        const oldZoom = zoom;
        const newZoom = clampZoom(oldZoom + step);
        if (newZoom === oldZoom) return;
        if (newZoom <= 1) {
            zoom = newZoom;
            panX = 0;
            panY = 0;
        } else {
            const factor = newZoom / oldZoom;
            zoom = newZoom;
            panX = Math.round(panX * factor * 100) / 100;
            panY = Math.round(panY * factor * 100) / 100;
        }
    }

    function handleWheel(e) {
        e.preventDefault();
        const oldZoom = zoom;
        const step = e.deltaY < 0 ? 0.25 : -0.25;
        const newZoom = clampZoom(oldZoom + step);
        if (newZoom === oldZoom) return;

        if (newZoom <= 1) {
            zoom = newZoom;
            panX = 0;
            panY = 0;
            return;
        }

        if (viewerContainer) {
            const rect = viewerContainer.getBoundingClientRect();
            const Px = e.clientX - (rect.left + rect.width / 2);
            const Py = e.clientY - (rect.top + rect.height / 2);
            const ratio = newZoom / oldZoom;
            panX = Math.round((panX * ratio + Px * (1 - ratio)) * 100) / 100;
            panY = Math.round((panY * ratio + Py * (1 - ratio)) * 100) / 100;
        } else {
            const ratio = newZoom / oldZoom;
            panX = Math.round(panX * ratio * 100) / 100;
            panY = Math.round(panY * ratio * 100) / 100;
        }
        zoom = newZoom;
    }

    function handleDoubleClick(e) {
        if (zoom > 1.05) {
            resetZoom();
        } else {
            const newZoom = 1.5;
            if (viewerContainer) {
                const rect = viewerContainer.getBoundingClientRect();
                const Px = e.clientX - (rect.left + rect.width / 2);
                const Py = e.clientY - (rect.top + rect.height / 2);
                const ratio = newZoom / zoom;
                panX =
                    Math.round((panX * ratio + Px * (1 - ratio)) * 100) / 100;
                panY =
                    Math.round((panY * ratio + Py * (1 - ratio)) * 100) / 100;
            }
            zoom = newZoom;
        }
    }

    function handlePointerDown(e) {
        if (e.button !== 0) return;
        if (zoom <= 1) return;
        isDragging = true;
        dragStartX = e.clientX;
        dragStartY = e.clientY;
        panStartX = panX;
        panStartY = panY;
        try {
            e.currentTarget.setPointerCapture?.(e.pointerId);
        } catch {}
        e.preventDefault();
    }

    function handlePointerMove(e) {
        if (!isDragging) return;
        panX = panStartX + (e.clientX - dragStartX);
        panY = panStartY + (e.clientY - dragStartY);
    }

    function handlePointerUp(e) {
        if (isDragging) {
            isDragging = false;
            try {
                e.currentTarget.releasePointerCapture?.(e.pointerId);
            } catch {}
        }
    }

    function handleKeyDown(e) {
        if (e.key === '+' || e.key === '=' || e.key === 'NumpadAdd') {
            e.preventDefault();
            zoomBy(0.25);
        } else if (
            e.key === '-' ||
            e.key === '_' ||
            e.key === 'NumpadSubtract'
        ) {
            e.preventDefault();
            zoomBy(-0.25);
        } else if (e.key === '0' || e.key === 'Numpad0') {
            e.preventDefault();
            resetZoom();
        } else if (e.key === 'ArrowUp') {
            if (zoom > 1) {
                e.preventDefault();
                panY += 40;
            }
        } else if (e.key === 'ArrowDown') {
            if (zoom > 1) {
                e.preventDefault();
                panY -= 40;
            }
        } else if (e.key === 'ArrowLeft') {
            if (zoom > 1) {
                e.preventDefault();
                panX += 40;
            }
        } else if (e.key === 'ArrowRight') {
            if (zoom > 1) {
                e.preventDefault();
                panX -= 40;
            }
        }
    }

    function handleOpenChange(value) {
        open = value;
        if (!value) {
            activeScreen = 1;
            currentPage = 1;
            resetZoom();
        }
        onOpenChange(value);
    }

    function goToPage(pageNo) {
        if (pageNo >= 1 && pageNo <= totalPages) {
            currentPage = pageNo;
            resetZoom();
        }
    }

    function focusBubble(label, option) {
        const bubble = activeSheetPage?.bubbles?.find(
            (item) => item.label === label && item.option === option,
        );

        if (!bubble || !viewerContainer) {
            return;
        }

        const image = viewerContainer.querySelector('img');
        const scale =
            (image?.clientWidth ?? 0) /
            (Number(activeSheetPage?.width_px) || 2480);
        zoom = 1.6;
        panX =
            viewerContainer.clientWidth / 2 -
            (bubble.x + bubble.w / 2) * scale * zoom;
        panY =
            viewerContainer.clientHeight / 2 -
            (bubble.y + bubble.h / 2) * scale * zoom;
    }
</script>
{#snippet zoomableViewer()}
    <!-- Toolbar: Page Switcher + Zoom Controls -->
    <div
        class="border-border bg-card flex flex-wrap items-center justify-between gap-2 rounded-lg border p-1.5 text-xs shadow-2xs"
    >
        <!-- Multi-Page Switcher Controls -->
        <div class="flex items-center gap-1.5">
            {#if totalPages > 1}
                <div
                    class="border-border bg-muted/30 inline-flex items-center rounded-md border p-0.5"
                >
                    <button
                        type="button"
                        disabled={currentPage <= 1}
                        onclick={() => goToPage(currentPage - 1)}
                        aria-label="Previous sheet page"
                        class="hover:bg-background rounded p-1 transition disabled:pointer-events-none disabled:opacity-30"
                    >
                        <ChevronLeft class="size-3.5" />
                    </button>
                    <div class="flex items-center gap-0.5 px-1">
                        {#each Array.from({ length: totalPages }, (_, i) => i + 1) as p}
                            <button
                                type="button"
                                onclick={() => goToPage(p)}
                                aria-current={currentPage === p ? 'page' : undefined}
                                class="{isReferencePreview ? 'px-2 py-0.5' : 'size-5'} rounded text-[10px] font-bold transition {currentPage === p
                                    ? 'bg-slate-900 text-white dark:bg-slate-100 dark:text-slate-900'
                                    : 'text-muted-foreground hover:bg-background'}"
                            >
                                {isReferencePreview ? `Page ${p}` : p}
                            </button>
                        {/each}
                    </div>
                    <button
                        type="button"
                        disabled={currentPage >= totalPages}
                        onclick={() => goToPage(currentPage + 1)}
                        aria-label="Next sheet page"
                        class="hover:bg-background rounded p-1 transition disabled:pointer-events-none disabled:opacity-30"
                    >
                        <ChevronRight class="size-3.5" />
                    </button>
                </div>
                <span class="text-muted-foreground text-[11px] font-medium">
                    Page {currentPage} of {totalPages}
                </span>
            {/if}
        </div>

        <!-- Zoom toolbar controls -->
        <div class="flex items-center gap-1">
            <button
                type="button"
                onclick={() => zoomBy(-0.25)}
                disabled={zoom <= 0.5}
                aria-label="Zoom out"
                title="Zoom out (-)"
                class="border-border bg-background hover:bg-muted rounded-md border p-1.5 transition disabled:pointer-events-none disabled:opacity-30"
            >
                <Minus class="text-muted-foreground size-3.5" />
            </button>
            <span
                class="text-foreground min-w-[3.2rem] px-2 text-center font-mono text-xs font-bold"
                aria-label="Current zoom level"
            >
                {Math.round(zoom * 100)}%
            </span>
            <button
                type="button"
                onclick={() => zoomBy(0.25)}
                disabled={zoom >= 3.0}
                aria-label="Zoom in"
                title="Zoom in (+)"
                class="border-border bg-background hover:bg-muted rounded-md border p-1.5 transition disabled:pointer-events-none disabled:opacity-30"
            >
                <Plus class="text-muted-foreground size-3.5" />
            </button>
            <button
                type="button"
                onclick={resetZoom}
                aria-label="Fit to view"
                title="Fit to view (0)"
                class="border-border bg-background hover:bg-muted text-foreground ml-1 inline-flex items-center gap-1 rounded-md border px-2.5 py-1 text-[11px] font-semibold transition"
            >
                <RotateCcw class="text-muted-foreground size-3" />
                <span>Fit</span>
            </button>
        </div>
    {#if isHandShaded}
        <div class="flex items-center gap-4 px-1 text-[11px] text-muted-foreground">
            <span class="inline-flex items-center gap-1.5">
                <span class="inline-block size-3.5 rounded-full border-2 border-amber-500 bg-slate-900"></span>
                <strong class="text-foreground">Target bubble</strong> (shade this)
            </span>
            <span class="inline-flex items-center gap-1.5">
                <span class="inline-block size-3 rounded-full border border-slate-400"></span>
                Leave blank
            </span>
        </div>
    {/if}
    </div>

    <!-- Zoomable Viewer Frame -->
    {#if sheetImageUrl}
        <!-- svelte-ignore a11y_no_noninteractive_tabindex, a11y_no_noninteractive_element_interactions -->
        <div
            bind:this={viewerContainer}
            role="region"
            tabindex="0"
            aria-label="Zoomable challenge sheet viewer"
            class="border-border relative flex h-[400px] w-full touch-none items-center justify-center overflow-hidden rounded-xl border bg-slate-950/5 select-none focus-visible:ring-2 focus-visible:ring-blue-500 focus-visible:outline-none sm:h-[480px] dark:bg-slate-900/40 {zoom > 1
                ? isDragging
                    ? 'cursor-grabbing'
                    : 'cursor-grab'
                : 'cursor-default'}"
            onwheel={handleWheel}
            onpointerdown={handlePointerDown}
            onpointermove={handlePointerMove}
            onpointerup={handlePointerUp}
            onpointercancel={handlePointerUp}
            ondblclick={handleDoubleClick}
            onkeydown={handleKeyDown}
        >
            <div
                class="pointer-events-none inline-flex origin-center items-center justify-center transition-transform duration-75 ease-out"
                style="transform: translate({panX}px, {panY}px) scale({zoom});"
            >
                <div class="relative inline-block">
                    <img
                        src={sheetImageUrl}
                        alt="Challenge sheet page {currentPage}"
                        class="border-border/80 max-h-[380px] w-auto max-w-full rounded-lg border bg-white object-contain shadow-sm select-none sm:max-h-[460px]"
                        draggable="false"
                    />
                    {#if Array.isArray(activeSheetPage?.bubbles) && activeSheetPage.bubbles.length > 0}
                        <svg
                            viewBox="0 0 {activeSheetPage?.width_px || 2480} {activeSheetPage?.height_px || 3508}"
                            class="pointer-events-none absolute inset-0 h-full w-full"
                            data-testid="reference-bubble-overlay"
                        >
                                {#each activeSheetPage.bubbles as b (`${b.label}:${b.option}:${b.x}:${b.y}`)}
                                    {#if !b.shaded}
                                        <ellipse
                                            cx={b.x + b.w / 2}
                                            cy={b.y + b.h / 2}
                                            rx={(b.w / 2) * 0.88}
                                            ry={(b.h / 2) * 0.88}
                                            fill="none"
                                            stroke="rgba(148, 163, 184, 0.35)"
                                            stroke-width="1.5"
                                        />
                                    {:else}
                                        <circle
                                            cx={b.x + b.w / 2}
                                            cy={b.y + b.h / 2}
                                            r={(b.w / 2) * 1.55}
                                            fill="rgba(245, 158, 11, 0.28)"
                                            stroke="#f59e0b"
                                            stroke-width="3"
                                        />
                                        <ellipse
                                            cx={b.x + b.w / 2}
                                            cy={b.y + b.h / 2}
                                            rx={(b.w / 2) * 0.88}
                                            ry={(b.h / 2) * 0.88}
                                            fill="#0f172a"
                                            stroke="#f59e0b"
                                            stroke-width="2"
                                        />
                                        <text
                                            x={b.x + b.w / 2}
                                            y={b.y - 5}
                                            fill="#f59e0b"
                                            font-size="18"
                                            font-weight="bold"
                                            text-anchor="middle"
                                        >
                                            {b.option}
                                        </text>
                                    {/if}
                                {/each}
                        </svg>
                    {/if}
                </div>
            </div>
        </div>
    {:else}
        <div
            class="border-border flex min-h-[350px] flex-col items-center justify-center space-y-2.5 rounded-xl border bg-slate-950/5 p-8 text-center dark:bg-slate-900/40"
        >
            <FileImage class="text-muted-foreground/60 mx-auto size-8" />
            {#if isUploadedSource}
                <div class="text-foreground text-xs font-semibold">
                    Supplier Blank Reference
                </div>
                <p
                    class="text-muted-foreground max-w-sm text-[11px] leading-relaxed"
                >
                    Supplier Blank Reference — Capture all required pages using genuine supplier-issued stock. Open the Guide PDF below for page-by-page instructions.
                </p>
            {:else}
                <div class="text-foreground text-xs font-semibold">
                    Challenge Sheet Available
                </div>
                <p
                    class="text-muted-foreground max-w-sm text-[11px] leading-relaxed"
                >
                    Physical sheet preview is ready. You can
                    download or print the PDF directly for physical
                    verification.
                </p>
            {/if}
            {#if printPdfUrl}
                <button
                    type="button"
                    onclick={() => onOpenPrintPdf(printPdfUrl)}
                    class="border-border bg-card hover:bg-muted text-foreground inline-flex items-center gap-1.5 rounded-lg border px-3 py-1.5 text-xs font-semibold transition"
                >
                    <Printer class="size-3.5" />
                    <span>Open sheet PDF</span>
                </button>
            {/if}
        </div>
    {/if}

    <div
        class="text-muted-foreground flex flex-wrap items-center justify-between gap-1 px-1 text-[10px]"
    >
        <span
            >Mouse wheel to zoom &bull; Drag to pan &bull;
            Double click to toggle 150%</span
        >
        <span>Keyboard: + / - / 0, Arrow keys to pan</span>
    </div>
{/snippet}

<Dialog {open} onOpenChange={handleOpenChange}>
    <DialogContent
        class="text-foreground bg-background flex max-h-[92vh] w-full max-w-4xl flex-col p-6 sm:max-w-4xl"
    >
        <DialogHeader class="shrink-0 space-y-2 pr-6">
            <div class="flex flex-wrap items-center gap-2">
                <DialogTitle
                    class="text-foreground text-base font-bold sm:text-lg"
                >
                    Physical tests
                </DialogTitle>
                <span
                    class="rounded-full px-2 py-0.5 text-[10px] font-bold tracking-wider uppercase {isFullQualification
                        ? 'bg-emerald-100 text-emerald-800 dark:bg-emerald-900/40 dark:text-emerald-300'
                        : 'bg-amber-100 text-amber-800 dark:bg-amber-900/40 dark:text-amber-300'}"
                >
                    {isFullQualification
                        ? 'Full Challenge (Qualification)'
                        : 'Quick Rehearsal'}
                </span>
            </div>
            <DialogDescription
                class="text-muted-foreground text-xs leading-relaxed"
            >
                Review the marking guide, then copy the shaded reference.
            </DialogDescription>

            <!-- 2-Screen Stepper / Navigation Bar -->
            <div
                class="bg-muted/40 border-border flex items-center gap-1 rounded-lg border p-1 text-xs"
                role="tablist"
                aria-label="Walkthrough steps"
            >
                <button
                    type="button"
                    role="tab"
                    aria-selected={activeScreen === 1}
                    aria-controls="guide-screen-1"
                    id="tab-screen-1"
                    onclick={() => (activeScreen = 1)}
                    class="inline-flex flex-1 items-center justify-center gap-2 rounded-md px-3 py-1.5 font-semibold transition-all focus-visible:ring-2 focus-visible:outline-none {activeScreen ===
                    1
                        ? 'bg-background text-foreground shadow-xs'
                        : 'text-muted-foreground hover:text-foreground hover:bg-background/50'}"
                >
                    <span
                        class="flex size-4.5 items-center justify-center rounded-full text-[10px] font-bold {activeScreen ===
                        1
                            ? 'bg-slate-900 text-white dark:bg-slate-100 dark:text-slate-900'
                            : 'bg-muted text-muted-foreground'}">1</span
                    >
                    <span>Instructions &amp; Standards</span>
                </button>
                <button
                    type="button"
                    role="tab"
                    aria-selected={activeScreen === 2}
                    aria-controls="guide-screen-2"
                    id="tab-screen-2"
                    onclick={() => (activeScreen = 2)}
                    class="inline-flex flex-1 items-center justify-center gap-2 rounded-md px-3 py-1.5 font-semibold transition-all focus-visible:ring-2 focus-visible:outline-none {activeScreen ===
                    2
                        ? 'bg-background text-foreground shadow-xs'
                        : 'text-muted-foreground hover:text-foreground hover:bg-background/50'}"
                >
                    <span
                        class="flex size-4.5 items-center justify-center rounded-full text-[10px] font-bold {activeScreen ===
                        2
                            ? 'bg-slate-900 text-white dark:bg-slate-100 dark:text-slate-900'
                            : 'bg-muted text-muted-foreground'}">2</span
                    >
                    <span
                        >{isHandShaded
                            ? 'Marks to Shade'
                            : 'Challenge Sheet & Marks'}</span
                    >
                </button>
            </div>
        </DialogHeader>

        <!-- Scrollable Body: Screen 1 vs Screen 2 -->
        {#if activeScreen === 1}
            <!-- Screen 1: Instructions & Standards -->
            <div
                id="guide-screen-1"
                role="tabpanel"
                aria-labelledby="tab-screen-1"
                class="-mr-1 space-y-5 overflow-y-auto py-2 pr-1 text-xs"
            >
                <!-- 1. Visual Shading Standards (CSS/SVG Examples) -->
                <div class="space-y-2.5">
                    <div class="flex items-center justify-between">
                        <h3
                            class="text-foreground flex items-center gap-1.5 text-xs font-bold tracking-wider uppercase"
                        >
                            <Sparkles
                                class="size-3.5 text-blue-600 dark:text-blue-400"
                            />
                            <span>Optical Mark Shading Standards</span>
                        </h3>
                        <span class="text-muted-foreground text-[11px]"
                            >High-contrast optical sensor rules</span
                        >
                    </div>

                    <div
                        class="grid grid-cols-1 gap-3 sm:grid-cols-2 lg:grid-cols-4"
                    >
                        <!-- Example 1: Empty / Unmarked -->
                        <div
                            class="border-border/80 bg-card flex flex-col items-center space-y-2 rounded-xl border p-3.5 text-center"
                        >
                            <div class="flex h-14 items-center justify-center">
                                <svg
                                    viewBox="0 0 60 60"
                                    class="size-12"
                                    aria-hidden="true"
                                >
                                    <circle
                                        cx="30"
                                        cy="30"
                                        r="20"
                                        fill="none"
                                        stroke="currentColor"
                                        stroke-width="2"
                                        class="text-slate-400 dark:text-slate-500"
                                    />
                                    <text
                                        x="30"
                                        y="36"
                                        text-anchor="middle"
                                        font-size="16"
                                        font-weight="bold"
                                        fill="currentColor"
                                        class="text-slate-600 dark:text-slate-400"
                                        >A</text
                                    >
                                </svg>
                            </div>
                            <div class="w-full space-y-1">
                                <span
                                    class="inline-block rounded-full bg-slate-100 px-2 py-0.5 text-[10px] font-bold text-slate-700 dark:bg-slate-800 dark:text-slate-300"
                                >
                                    Unmarked
                                </span>
                                <p
                                    class="text-muted-foreground text-[11px] leading-tight"
                                >
                                    Clean bubble with clear interior
                                </p>
                            </div>
                        </div>

                        <!-- Example 2: Correctly Filled -->
                        <div
                            class="flex flex-col items-center space-y-2 rounded-xl border border-emerald-300 bg-emerald-50/40 p-3.5 text-center dark:border-emerald-800/80 dark:bg-emerald-950/20"
                        >
                            <div class="flex h-14 items-center justify-center">
                                <svg
                                    viewBox="0 0 60 60"
                                    class="size-12"
                                    aria-hidden="true"
                                >
                                    <circle
                                        cx="30"
                                        cy="30"
                                        r="20"
                                        class="fill-slate-900 stroke-slate-900 dark:fill-slate-100 dark:stroke-slate-100"
                                        stroke-width="2"
                                    />
                                    <text
                                        x="30"
                                        y="36"
                                        text-anchor="middle"
                                        font-size="16"
                                        font-weight="bold"
                                        class="fill-white dark:fill-slate-900"
                                        >A</text
                                    >
                                </svg>
                            </div>
                            <div class="w-full space-y-1">
                                <span
                                    class="inline-flex items-center gap-1 rounded-full bg-emerald-100 px-2 py-0.5 text-[10px] font-bold text-emerald-800 dark:bg-emerald-900/60 dark:text-emerald-300"
                                >
                                    <Check class="size-3 stroke-[3]" />
                                    <span>Correct Fill</span>
                                </span>
                                <p
                                    class="text-muted-foreground text-[11px] leading-tight"
                                >
                                    100% solid, dark, complete coverage
                                </p>
                            </div>
                        </div>

                        <!-- Example 3: Incorrect Marks (Tick, Cross, Dot) -->
                        <div
                            class="flex flex-col items-center space-y-2 rounded-xl border border-red-200 bg-red-50/40 p-3.5 text-center dark:border-red-900/60 dark:bg-red-950/20"
                        >
                            <div
                                class="flex h-14 items-center justify-center gap-1.5"
                            >
                                <!-- Tick -->
                                <svg
                                    viewBox="0 0 36 36"
                                    class="size-7"
                                    aria-label="Tick mark"
                                    aria-hidden="true"
                                >
                                    <circle
                                        cx="18"
                                        cy="18"
                                        r="14"
                                        fill="none"
                                        stroke="currentColor"
                                        stroke-width="1.5"
                                        class="text-slate-400"
                                    />
                                    <path
                                        d="M10 18l5 5 11-11"
                                        fill="none"
                                        stroke="currentColor"
                                        stroke-width="2.5"
                                        stroke-linecap="round"
                                        stroke-linejoin="round"
                                        class="text-red-500"
                                    />
                                </svg>
                                <!-- Cross -->
                                <svg
                                    viewBox="0 0 36 36"
                                    class="size-7"
                                    aria-label="Cross mark"
                                    aria-hidden="true"
                                >
                                    <circle
                                        cx="18"
                                        cy="18"
                                        r="14"
                                        fill="none"
                                        stroke="currentColor"
                                        stroke-width="1.5"
                                        class="text-slate-400"
                                    />
                                    <path
                                        d="M11 11l14 14M25 11l-14 14"
                                        fill="none"
                                        stroke="currentColor"
                                        stroke-width="2.5"
                                        stroke-linecap="round"
                                        class="text-red-500"
                                    />
                                </svg>
                                <!-- Dot -->
                                <svg
                                    viewBox="0 0 36 36"
                                    class="size-7"
                                    aria-label="Dot mark"
                                    aria-hidden="true"
                                >
                                    <circle
                                        cx="18"
                                        cy="18"
                                        r="14"
                                        fill="none"
                                        stroke="currentColor"
                                        stroke-width="1.5"
                                        class="text-slate-400"
                                    />
                                    <circle
                                        cx="18"
                                        cy="18"
                                        r="3.5"
                                        class="fill-slate-800 dark:fill-slate-200"
                                    />
                                </svg>
                            </div>
                            <div class="w-full space-y-1">
                                <span
                                    class="inline-flex items-center gap-1 rounded-full bg-red-100 px-2 py-0.5 text-[10px] font-bold text-red-800 dark:bg-red-900/60 dark:text-red-300"
                                >
                                    <XCircle class="size-3" />
                                    <span>Invalid Marks</span>
                                </span>
                                <p
                                    class="text-muted-foreground text-[11px] leading-tight"
                                >
                                    No ticks, crosses, dots, or partial fills
                                </p>
                            </div>
                        </div>

                        <!-- Example 4: Clean Erasure -->
                        <div
                            class="flex flex-col items-center space-y-2 rounded-xl border border-amber-200 bg-amber-50/40 p-3.5 text-center dark:border-amber-900/60 dark:bg-amber-950/20"
                        >
                            <div class="flex h-14 items-center justify-center">
                                <svg
                                    viewBox="0 0 60 60"
                                    class="size-12"
                                    aria-hidden="true"
                                >
                                    <circle
                                        cx="30"
                                        cy="30"
                                        r="20"
                                        fill="none"
                                        stroke="currentColor"
                                        stroke-width="2"
                                        class="text-slate-400 dark:text-slate-500"
                                    />
                                    <circle
                                        cx="30"
                                        cy="30"
                                        r="18"
                                        class="fill-slate-400/15 dark:fill-slate-600/15"
                                    />
                                    <text
                                        x="30"
                                        y="36"
                                        text-anchor="middle"
                                        font-size="16"
                                        font-weight="bold"
                                        fill="currentColor"
                                        class="text-slate-400 opacity-50 dark:text-slate-500"
                                        >A</text
                                    >
                                    <path
                                        d="M16 44l28-28"
                                        stroke="currentColor"
                                        stroke-width="1.5"
                                        stroke-dasharray="3 2"
                                        class="text-amber-600/60 dark:text-amber-400/60"
                                    />
                                </svg>
                            </div>
                            <div class="w-full space-y-1">
                                <span
                                    class="inline-flex items-center gap-1 rounded-full bg-amber-100 px-2 py-0.5 text-[10px] font-bold text-amber-800 dark:bg-amber-900/60 dark:text-amber-300"
                                >
                                    <AlertTriangle class="size-3" />
                                    <span>Clean Erasure</span>
                                </span>
                                <p
                                    class="text-muted-foreground text-[11px] leading-tight"
                                >
                                    Erase completely; avoid dark smudges
                                </p>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- 2. Concise Physical Rules Grid -->
                <div class="space-y-2.5">
                    <h3
                        class="text-foreground text-xs font-bold tracking-wider uppercase"
                    >
                        Physical Marking &amp; Print Rules
                    </h3>
                    <div class="grid grid-cols-1 gap-2.5 sm:grid-cols-2">
                        <!-- Rule 1: Pencil -->
                        <div
                            class="border-border/80 bg-card flex items-start gap-2.5 rounded-xl border p-3"
                        >
                            <CheckCircle2
                                class="mt-0.5 size-4 shrink-0 text-emerald-600 dark:text-emerald-400"
                            />
                            <div class="space-y-0.5">
                                <h4 class="text-foreground text-xs font-bold">
                                    #2 (HB) Pencil or Dark Black Pen
                                </h4>
                                <p
                                    class="text-muted-foreground text-[11px] leading-relaxed"
                                >
                                    Use a dark graphite pencil (#2 / HB) or
                                    solid black ballpoint pen. Fill bubbles
                                    completely to achieve full optical density.
                                </p>
                            </div>
                        </div>

                        <!-- Rule 2: QR Code -->
                        <div
                            class="border-border/80 bg-card flex items-start gap-2.5 rounded-xl border p-3"
                        >
                            <QrCode
                                class="mt-0.5 size-4 shrink-0 text-blue-600 dark:text-blue-400"
                            />
                            <div class="space-y-0.5">
                                <h4 class="text-foreground text-xs font-bold">
                                    Pristine QR Code Area
                                </h4>
                                <p
                                    class="text-muted-foreground text-[11px] leading-relaxed"
                                >
                                    Do not fold, crease, smudge, or mark
                                    anywhere across the top corner QR code. The
                                    engine reads the QR to locate the exact
                                    plan.
                                </p>
                            </div>
                        </div>

                        <!-- Rule 3: Alignment Markers -->
                        <div
                            class="border-border/80 bg-card flex items-start gap-2.5 rounded-xl border p-3"
                        >
                            <Scan
                                class="mt-0.5 size-4 shrink-0 text-purple-600 dark:text-purple-400"
                            />
                            <div class="space-y-0.5">
                                <h4 class="text-foreground text-xs font-bold">
                                    Corner Alignment Targets
                                </h4>
                                <p
                                    class="text-muted-foreground text-[11px] leading-relaxed"
                                >
                                    Keep the four black corner finder targets
                                    and edge timing tracks clean. Never staple,
                                    punch holes, or draw lines across them.
                                </p>
                            </div>
                        </div>

                        <!-- Rule 4: Actual Size -->
                        <div
                            class="border-border/80 bg-card flex items-start gap-2.5 rounded-xl border p-3"
                        >
                            <Printer
                                class="mt-0.5 size-4 shrink-0 text-amber-600 dark:text-amber-400"
                            />
                            <div class="space-y-0.5">
                                <h4 class="text-foreground text-xs font-bold">
                                    Print at 100% (Actual Size)
                                </h4>
                                <p
                                    class="text-muted-foreground text-[11px] leading-relaxed"
                                >
                                    In your system print dialog, always choose <strong
                                        >Actual Size</strong
                                    > (100% scaling). Do not use “Fit to printable
                                    area” or page scaling.
                                </p>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- 3. Calibration-ID Disclaimer -->
                <div
                    class="flex items-start gap-2.5 rounded-xl border border-amber-200 bg-amber-50/50 p-3.5 text-xs leading-relaxed text-amber-900 dark:border-amber-900/60 dark:bg-amber-950/20 dark:text-amber-200"
                >
                    <ShieldAlert
                        class="mt-0.5 size-4 shrink-0 text-amber-700 dark:text-amber-400"
                    />
                    <div class="space-y-0.5">
                        <strong
                            class="block font-semibold text-amber-950 dark:text-amber-100"
                            >Calibration-ID disclaimer</strong
                        >
                        <span class="text-[11px]">{calibrationDisclaimer}</span>
                    </div>
                </div>
            </div>
        {:else}
            <!-- Screen 2: Challenge Sheet & Marks -->
            <div
                id="guide-screen-2"
                role="tabpanel"
                aria-labelledby="tab-screen-2"
                class="-mr-1 space-y-3 overflow-y-auto py-2 pr-1 text-xs"
            >
                <!-- Exact Copy Contract -->
                <div class="space-y-0.5">
                    <h3 class="text-foreground text-sm font-bold">
                        {isHandShaded
                            ? 'Shade these exact bubbles'
                            : 'Review the printed pattern'}
                    </h3>
                    <p class="text-muted-foreground text-xs">
                        {isHandShaded
                            ? 'On the blank printed sheet, shade only the bubbles listed below. Leave every other answer bubble blank.'
                            : 'Use this on-screen reference to check the generated marks before printing.'}
                    </p>
                </div>
                {#if isReferencePreview || isHandShaded}
                    <div
                        class="border-sky-200 bg-sky-50 text-sky-900 dark:border-sky-900/60 dark:bg-sky-950/20 dark:text-sky-200 flex items-center gap-2 rounded-lg border px-3 py-2 text-xs font-medium"
                        role="status"
                    >
                        <Sparkles class="size-4 shrink-0 text-sky-600 dark:text-sky-400" />
                        <span>
                            {isHandShaded
                                ? `Hand-Shading Reference Preview (Page ${currentPage} of ${sheetPages.length || totalPages}) — ${activePageTargetCount} target bubbles to shade on this page`
                                : `Recognition Reference Preview (Page ${currentPage} of ${sheetPages.length || totalPages}) — Use genuine supplier sheets for capture`}
                        </span>
                    </div>
                {/if}

                {#if isHandShaded}
                    {#if hasReferenceViewer}
                        {@render zoomableViewer()}
                    {:else if isUploadedSource && !sheetImageUrl}
                        <div
                            class="border-border flex min-h-[120px] flex-col items-center justify-center space-y-2 rounded-xl border bg-slate-950/5 p-6 text-center dark:bg-slate-900/40"
                        >
                            <FileImage class="text-muted-foreground/60 mx-auto size-8" />
                            <div class="text-foreground text-xs font-semibold">
                                Supplier Blank Reference
                            </div>
                            <p class="text-muted-foreground max-w-sm text-[11px] leading-relaxed">
                                Supplier Blank Reference — Capture all required pages using genuine supplier-issued stock. Open the Guide PDF below for page-by-page instructions.
                            </p>
                        </div>
                    {:else}
                        <div
                            class="border-border bg-card flex flex-wrap items-center justify-between gap-2 rounded-lg border p-1.5"
                        >
                            <span
                                class="text-muted-foreground px-2 text-[11px] font-medium"
                            >
                                Page {currentPage} of {totalPages}
                            </span>
                            {#if totalPages > 1}
                                <div
                                    class="border-border bg-muted/30 inline-flex items-center rounded-md border p-0.5"
                                >
                                    <button
                                        type="button"
                                        disabled={currentPage <= 1}
                                        onclick={() => goToPage(currentPage - 1)}
                                        aria-label="Previous guide page"
                                        class="hover:bg-background rounded p-1 transition disabled:pointer-events-none disabled:opacity-30"
                                    >
                                        <ChevronLeft class="size-3.5" />
                                    </button>
                                    {#each Array.from({ length: totalPages }, (_, index) => index + 1) as page}
                                        <button
                                            type="button"
                                            onclick={() => goToPage(page)}
                                            aria-current={currentPage === page
                                                ? 'page'
                                                : undefined}
                                            aria-label={`Guide page ${page}`}
                                            class="size-6 rounded text-[10px] font-bold transition {currentPage ===
                                            page
                                                ? 'bg-slate-900 text-white dark:bg-slate-100 dark:text-slate-900'
                                                : 'text-muted-foreground hover:bg-background'}"
                                        >
                                            {page}
                                        </button>
                                    {/each}
                                    <button
                                        type="button"
                                        disabled={currentPage >= totalPages}
                                        onclick={() => goToPage(currentPage + 1)}
                                        aria-label="Next guide page"
                                        class="hover:bg-background rounded p-1 transition disabled:pointer-events-none disabled:opacity-30"
                                    >
                                        <ChevronRight class="size-3.5" />
                                    </button>
                                </div>
                            {/if}
                        </div>
                    {/if}

                    {#if activeGuideSections.length > 0}
                        <div
                            class="space-y-3"
                            aria-label={`Marks to shade on page ${currentPage}`}
                        >
                            {#each activeGuideSections as section}
                                <section
                                    class="border-border/80 bg-card space-y-2 rounded-xl border p-3.5"
                                    aria-label={`${section.area} marks`}
                                >
                                    <div
                                        class="flex flex-wrap items-baseline justify-between gap-1"
                                    >
                                        <h4
                                            class="text-foreground text-xs font-bold"
                                        >
                                            {section.area}
                                        </h4>
                                        <span
                                            class="text-muted-foreground text-[10px]"
                                        >
                                            {section.labels?.length ?? 0} bubbles
                                        </span>
                                    </div>
                                    <div
                                        class="grid grid-cols-1 gap-2 sm:grid-cols-2 lg:grid-cols-3"
                                    >
                                        {#each section.labels ?? [] as mark}
                                            <div
                                                class="border-border bg-background flex min-w-0 items-center justify-between gap-3 rounded-lg border px-3 py-2"
                                            >
                                                <span
                                                    class="text-foreground min-w-0 truncate text-xs font-semibold"
                                                >
                                                    {displayMarkLabel(
                                                        mark.label,
                                                    )}
                                                </span>
                                                <span
                                                    class="flex size-8 shrink-0 items-center justify-center rounded-full bg-slate-900 text-sm font-bold text-white dark:bg-slate-100 dark:text-slate-900"
                                                    aria-label={`Shade option ${mark.option}`}
                                                >
                                                    {mark.option}
                                                </span>
                                                {#if hasReferenceViewer && activeSheetPage?.bubbles?.some((bubble) => bubble.label === mark.label)}
                                                    <button
                                                        type="button"
                                                        onclick={() => focusBubble(mark.label, mark.option)}
                                                        class="ml-2 text-[10px] font-semibold text-primary underline hover:text-primary/80"
                                                        title="Focus bubble on sheet"
                                                    >
                                                        Focus on sheet
                                                    </button>
                                                {/if}
                                            </div>
                                        {/each}
                                    </div>
                                </section>
                            {/each}
                        </div>
                    {:else if !hasReferenceViewer && !(isUploadedSource && !sheetImageUrl)}
                        <div
                            class="border-border bg-muted/30 rounded-xl border p-5 text-center"
                            role="status"
                        >
                            <p class="text-foreground text-xs font-semibold">
                                Marking pattern unavailable
                             </p>
                            <p class="text-muted-foreground mt-1 text-[11px]">
                                This saved challenge has no structured mark
                                list. Regenerate the challenge before hand
                                shading.
                            </p>
                        </div>
                    {/if}

                    {#if currentPage === 1 && Array.isArray(guide?.identity_columns) && guide.identity_columns.length > 0}
                        <section
                            class="border-border bg-muted/30 space-y-2 rounded-xl border p-3.5"
                            aria-label="Calibration digits to shade"
                        >
                            <div>
                                <h4 class="text-foreground text-xs font-bold">
                                    Applicant number calibration
                                </h4>
                                <p
                                    class="text-muted-foreground mt-0.5 text-[11px]"
                                >
                                    Shade one digit in each listed column. These
                                    digits identify only this calibration
                                    challenge.
                                </p>
                            </div>
                            <div class="flex flex-wrap gap-2">
                                {#each guide.identity_columns as column}
                                    <div
                                        class="border-border bg-background inline-flex items-center gap-2 rounded-lg border px-3 py-2"
                                    >
                                        <span
                                            class="text-muted-foreground text-[11px] font-medium"
                                        >
                                            {column.label}
                                        </span>
                                        <span
                                            class="flex size-7 items-center justify-center rounded-full bg-slate-900 text-xs font-bold text-white dark:bg-slate-100 dark:text-slate-900"
                                        >
                                            {column.digit}
                                        </span>
                                    </div>
                                {/each}
                            </div>
                        </section>
                    {/if}
                {:else}
                    {@render zoomableViewer()}
                {/if}
            </div>
        {/if}

        <DialogFooter
            class="border-border flex shrink-0 flex-col-reverse gap-2.5 border-t pt-3 sm:flex-row sm:items-center sm:justify-between"
        >
            <div class="flex flex-wrap items-center gap-2">
                {#if activeScreen === 2 && guidePdfUrl}
                    <button
                        type="button"
                        onclick={() => onOpenPrintPdf(guidePdfUrl)}
                        class="border-border bg-card hover:bg-muted text-foreground inline-flex items-center gap-1.5 rounded-lg border px-3 py-2 text-xs font-semibold transition focus-visible:ring-2 focus-visible:outline-none"
                    >
                        <FileImage class="text-muted-foreground size-3.5" />
                        <span>Open guide PDF (optional)</span>
                    </button>
                {/if}
                {#if activeScreen === 2 && printPdfUrl}
                    <button
                        type="button"
                        onclick={() => onOpenPrintPdf(printPdfUrl)}
                        class="border-border bg-card hover:bg-muted text-foreground inline-flex items-center gap-1.5 rounded-lg border px-3 py-2 text-xs font-semibold transition focus-visible:ring-2 focus-visible:outline-none"
                    >
                        <Printer class="text-muted-foreground size-3.5" />
                        <span
                            >{isHandShaded
                                ? 'Print blank sheet (optional)'
                                : 'Print sheet (optional)'}</span
                        >
                    </button>
                {/if}
            </div>

            <div class="flex items-center gap-2">
                {#if activeScreen === 2}
                    <button
                        type="button"
                        onclick={() => (activeScreen = 1)}
                        class="border-border bg-card hover:bg-muted text-foreground inline-flex items-center gap-1 rounded-lg border px-3.5 py-2 text-xs font-semibold transition focus-visible:ring-2 focus-visible:outline-none"
                    >
                        <ArrowLeft class="size-3.5" />
                        <span>Back to Instructions</span>
                    </button>
                {/if}

                <button
                    type="button"
                    onclick={() => handleOpenChange(false)}
                    class="border-border bg-card hover:bg-muted text-foreground rounded-lg border px-3.5 py-2 text-xs font-semibold transition focus-visible:ring-2 focus-visible:outline-none"
                >
                    Close
                </button>

                {#if activeScreen === 1}
                    <button
                        type="button"
                        onclick={() => (activeScreen = 2)}
                        class="inline-flex items-center justify-center gap-1.5 rounded-lg bg-slate-900 px-4 py-2 text-xs font-bold text-white shadow-xs transition hover:bg-slate-800 focus-visible:ring-2 focus-visible:outline-none dark:bg-slate-100 dark:text-slate-900 dark:hover:bg-white"
                    >
                        <span
                            >{isHandShaded
                                ? 'Next: View Marks to Shade'
                                : 'Next: View Challenge Sheet'}</span
                        >
                        <ArrowRight class="size-3.5" />
                    </button>
                {:else}
                    <button
                        type="button"
                        onclick={() => handleOpenChange(false)}
                        class="inline-flex items-center justify-center gap-1.5 rounded-lg bg-slate-900 px-4 py-2 text-xs font-bold text-white shadow-xs transition hover:bg-slate-800 focus-visible:ring-2 focus-visible:outline-none dark:bg-slate-100 dark:text-slate-900 dark:hover:bg-white"
                    >
                        <Check class="size-3.5 stroke-[2.5]" />
                        <span>Done — return to test</span>
                    </button>
                {/if}
            </div>
        </DialogFooter>
    </DialogContent>
</Dialog>
