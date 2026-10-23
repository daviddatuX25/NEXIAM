<script>
    import { Maximize2, ZoomIn, ZoomOut } from 'lucide-svelte';
    import { flushSync } from 'svelte';
    import { buildBookletAnswerPreviewModel } from '@/lib/bookletAnswerPreviewModel';

    let {
        page = null,
        bubbles = [],
        answers = [],
        readOnly = false,
        structuralOnly = false,
        status = 'loading',
        errorCode = null,
        activePageNo = 1,
        scopeLabel = null,
        pages = [],
        pageCount = null,
        onPageChange = () => {},
    } = $props();
    let loadedRasterKey = $state(null);
    let failedRasterKey = $state(null);
    let zoom = $state(1);
    let currentPageNo = $state(1);
    let canvasElement = $state(null);
    let canvasWidth = $state(0);
    let viewportHeight = $state(0);

    const rasterKey = $derived(
        page?.raster_url
            ? `${page.raster_url}:${page.raster_sha256 ?? ''}`
            : null,
    );
    const imageLoaded = $derived(rasterKey !== null && loadedRasterKey === rasterKey);
    const imageFailed = $derived(rasterKey !== null && failedRasterKey === rasterKey);

    $effect(() => {
        if (!canvasElement) return;

        const updateViewport = () => { viewportHeight = window.innerHeight; };
        updateViewport();
        window.addEventListener('resize', updateViewport);
        if (typeof ResizeObserver === 'undefined') {
            return () => window.removeEventListener('resize', updateViewport);
        }

        const resizeObserver = new ResizeObserver(([entry]) => { canvasWidth = entry.contentRect.width; });
        resizeObserver.observe(canvasElement);
        return () => {
            resizeObserver.disconnect();
            window.removeEventListener('resize', updateViewport);
        };
    });

    const fitPageWidth = $derived.by(() => {
        if (!page?.width_px || !page?.height_px) return 0;
        const availableWidth = canvasWidth > 0
            ? canvasWidth
            : typeof window === 'undefined' ? page.width_px : window.innerWidth;
        const availableHeight = viewportHeight > 0
            ? viewportHeight * 0.62
            : typeof window === 'undefined' ? page.height_px : window.innerHeight * 0.62;
        return page.width_px * Math.min(availableWidth / page.width_px, availableHeight / page.height_px, 1);
    });

    $effect(() => { currentPageNo = activePageNo; });

    const preview = $derived(buildBookletAnswerPreviewModel({ bubbles, answers }));
    const missingPositionContext = $derived(
        preview.missingPositions
            .slice(0, 3)
            .map((missing) => `Page ${missing.page_no}${missing.question_no ? ` · Item ${missing.question_no}` : ''}`)
            .join(', '),
    );
    const remainingMissingCount = $derived(Math.max(0, preview.missingPositions.length - 3));
    const totalPages = $derived(pageCount ?? page?.page_count ?? (pages.length || 1));
    const canShowPage = $derived(
        status === 'ready' && page && typeof page.raster_url === 'string' && page.raster_url.length > 0 &&
        Number.isFinite(page.width_px) && page.width_px > 0 && Number.isFinite(page.height_px) && page.height_px > 0 && !imageFailed,
    );

    function changePage(nextPage) {
        if (nextPage >= 1 && nextPage <= totalPages) {
            flushSync(() => { currentPageNo = nextPage; });
            onPageChange(nextPage);
        }
    }

    function markRasterLoaded(event) {
        const image = event.currentTarget;
        if (image.getAttribute('href') !== page?.raster_url ||
            (image.getAttribute('data-raster-sha256') ?? '') !== (page?.raster_sha256 ?? '')) return;
        loadedRasterKey = rasterKey;
    }

    function markRasterFailed(event) {
        const image = event.currentTarget;
        if (image.getAttribute('href') !== page?.raster_url ||
            (image.getAttribute('data-raster-sha256') ?? '') !== (page?.raster_sha256 ?? '')) return;
        failedRasterKey = rasterKey;
    }

    function bubbleTitle(bubble, option) {
        return `Page ${bubble.page_no}, item ${bubble.question_no ?? 'unfilled'}, option ${option}`;
    }

    function bubblePositions(missing) {
        const bubble = bubbles.find((item) => item.physical_item_uid === missing.physical_item_uid);
        return Object.values(bubble?.options ?? {}).filter((option) =>
            Number.isFinite(option?.cx) && Number.isFinite(option?.cy) && Number.isFinite(option?.r) && option.r > 0,
        );
    }
</script>

<section class="rounded-lg border border-border bg-card text-card-foreground shadow-sm" aria-label="Answer Sheet key preview" data-read-only={readOnly}>
    <header class="flex flex-wrap items-center justify-between gap-3 border-b border-border px-4 py-3">
        <div>
            <h2 class="text-sm font-semibold">{scopeLabel ? `${scopeLabel} · Answer Sheet` : 'Selected Answer Sheet'}</h2>
            <p class="text-xs text-muted-foreground">
                Key preview only. This does not detect marks or score a response.
                {#if readOnly}<span class="font-medium">Read-only</span>{/if}
            </p>
        </div>
        {#if totalPages > 1}
            <nav class="flex items-center gap-2" aria-label="Answer Sheet pages">
                <button type="button" class="rounded-md border border-border px-3 py-2 text-sm hover:bg-muted focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring disabled:opacity-50" aria-label="Previous page" disabled={currentPageNo <= 1} onclick={() => changePage(currentPageNo - 1)}>Previous</button>
                <span class="min-w-16 text-center text-sm" aria-live="polite">Page {currentPageNo} of {totalPages}</span>
                <button type="button" class="rounded-md border border-border px-3 py-2 text-sm hover:bg-muted focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring disabled:opacity-50" aria-label="Next page" disabled={currentPageNo >= totalPages} onclick={() => changePage(currentPageNo + 1)}>Next</button>
            </nav>
        {/if}
    </header>

    {#if status === 'loading'}
        <div class="p-8 text-center text-sm text-muted-foreground" role="status">Loading selected Answer Sheet…</div>
    {:else if status === 'error' || status === 'unavailable' || imageFailed || !canShowPage}
        <div class="p-8 text-center" role="status">
            <p class="font-medium">Answer Sheet preview unavailable</p>
            <p class="mt-1 text-sm text-muted-foreground">
                {#if errorCode}
                    Preview could not be displayed ({errorCode}).
                {:else if status === 'error'}
                    The selected Answer Sheet preview failed to load.
                {:else if imageFailed}
                    The selected Answer Sheet page could not be loaded.
                {:else}
                    No usable raster page is available for the selected Answer Sheet.
                {/if}
            </p>
        </div>
    {:else}
        <div class="flex flex-wrap items-center justify-between gap-3 border-b border-border px-4 py-2">
            <p class="text-xs text-muted-foreground">Graphite shading reflects the exact saved Kit key. This preview is read-only.</p>
            <div class="flex items-center gap-1.5" aria-label="Preview zoom controls">
                <button type="button" class="inline-flex size-9 items-center justify-center rounded-md border border-border hover:bg-muted focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring disabled:opacity-50" aria-label="Zoom out" disabled={zoom <= 0.75} onclick={() => (zoom = Math.max(0.75, zoom - 0.25))}><ZoomOut class="size-4" aria-hidden="true" /></button>
                <button type="button" class="inline-flex size-9 items-center justify-center rounded-md border border-border hover:bg-muted focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring" aria-label="Fit full page" onclick={() => (zoom = 1)}><Maximize2 class="size-4" aria-hidden="true" /></button>
                <button type="button" class="inline-flex size-9 items-center justify-center rounded-md border border-border hover:bg-muted focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring disabled:opacity-50" aria-label="Zoom in" disabled={zoom >= 2} onclick={() => (zoom = Math.min(2, zoom + 0.25))}><ZoomIn class="size-4" aria-hidden="true" /></button>
            </div>
        </div>

        <div bind:this={canvasElement} class="max-w-full overflow-auto p-3">
            <svg class="mx-auto block h-auto" style={`width: ${fitPageWidth * zoom}px`} viewBox={`0 0 ${page.width_px} ${page.height_px}`} preserveAspectRatio="xMidYMid meet" role="img" aria-label={`Answer Sheet page ${currentPageNo} with ${imageLoaded ? preview.overlays.length : 0} shaded answers`}>
                {#key rasterKey}
                    <image href={page.raster_url} data-raster-sha256={page.raster_sha256 ?? ''} x="0" y="0" width={page.width_px} height={page.height_px} preserveAspectRatio="xMidYMid meet" onload={markRasterLoaded} onerror={markRasterFailed} />
                {/key}
                {#if imageLoaded}
                    {#each preview.overlays as overlay (overlay.physical_item_uid)}
                        <circle cx={overlay.geometry.cx} cy={overlay.geometry.cy} r={Math.max(0, overlay.geometry.r * 0.68)} fill="#292929" fill-opacity="0.78" aria-label={bubbleTitle(bubbles.find((bubble) => bubble.physical_item_uid === overlay.physical_item_uid), overlay.option)}>
                            <title>{bubbleTitle(bubbles.find((bubble) => bubble.physical_item_uid === overlay.physical_item_uid), overlay.option)}</title>
                        </circle>
                    {/each}
                    {#each preview.missingPositions as missing (missing.physical_item_uid)}
                        {#each bubblePositions(missing) as position, index (`${missing.physical_item_uid}:${index}`)}
                            <circle cx={position.cx} cy={position.cy} r={position.r + 2} fill="none" stroke="currentColor" stroke-width="3" stroke-dasharray="5 4" class="text-amber-800 dark:text-amber-200" aria-hidden="true" />
                        {/each}
                    {/each}
                {/if}
            </svg>
            {#if !imageLoaded && !imageFailed}
                <p class="mt-2 text-center text-xs text-muted-foreground" role="status">Loading Answer Sheet page…</p>
            {/if}
        </div>
    {/if}

    {#if canShowPage}
        <footer class="border-t border-border px-4 py-3">
            <div class="flex flex-wrap items-baseline justify-between gap-2">
                <h3 class="text-sm font-medium" id="missing-answers-heading">{structuralOnly ? 'Unfilled locations' : 'Missing answers'}: {preview.missingPositions.length}</h3>
                <p class="text-xs text-muted-foreground">{structuralOnly ? 'No answer-key marks are shown until the saved composition is complete and compatible.' : 'Unanswered positions are not assigned an option.'}</p>
            </div>
            {#if missingPositionContext}
                <p class="mt-2 text-sm text-muted-foreground" aria-label="Missing answer positions">{missingPositionContext}{#if remainingMissingCount > 0}, and {remainingMissingCount} more{/if}</p>
            {:else}
                <p class="mt-2 text-sm text-muted-foreground">All mapped positions in this selection have a valid answer.</p>
            {/if}
        </footer>
    {/if}
</section>
