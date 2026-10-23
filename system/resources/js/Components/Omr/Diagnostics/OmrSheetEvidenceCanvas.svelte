<script lang="ts">
    import { Maximize2, Move, ZoomIn, ZoomOut } from 'lucide-svelte';
    import {
        normalizeEvidence,
        type NormalizedItem,
    } from '../../../lib/omrDiagnosticEvidence.js';
    import type { Region } from '../../../lib/omrRegionCrop.js';

    let {
        sheetMap = null,
        imageUrl = null,
        selectedId = null,
        blind = false,
        mode = 'markings',
        onSelect = null,
    } = $props();

    let svgElement = $state<SVGSVGElement | null>(null);
    let view = $state({ x: 0, y: 0, w: 1, h: 1 });
    let drag = $state<{
        pointerId: number;
        x: number;
        y: number;
        viewX: number;
        viewY: number;
    } | null>(null);
    let lastDimensions = '';

    const width = $derived(Number(sheetMap?.width ?? 0));
    const height = $derived(Number(sheetMap?.height ?? 0));
    const ready = $derived(Boolean(imageUrl && width > 0 && height > 0));
    const items = $derived<NormalizedItem[]>(
        (Array.isArray(sheetMap?.items) ? sheetMap.items : [])
            .map(
                (item: Record<string, unknown>) =>
                    normalizeEvidence({ items: [item] })?.items?.[0] ?? null,
            )
            .filter(
                (item: NormalizedItem | null): item is NormalizedItem =>
                    item !== null,
            ),
    );

    function itemId(item: NormalizedItem): string {
        return String(item.id ?? item.item_id ?? item.label);
    }
    function isSheetRegion(region: Region): boolean {
        return (
            region.x >= 0 &&
            region.y >= 0 &&
            region.x + region.w <= 1 &&
            region.y + region.h <= 1
        );
    }

    function resetView(): void {
        if (width <= 0 || height <= 0) return;
        view = { x: 0, y: 0, w: width, h: height };
    }

    function zoom(
        factor: number,
        anchorX = view.x + view.w / 2,
        anchorY = view.y + view.h / 2,
    ): void {
        if (!ready) return;
        const nextWidth = Math.min(
            width,
            Math.max(width * 0.12, view.w * factor),
        );
        const nextHeight = nextWidth * (height / width);
        const ratioX = (anchorX - view.x) / view.w;
        const ratioY = (anchorY - view.y) / view.h;
        const nextX = Math.min(
            width - nextWidth,
            Math.max(0, anchorX - nextWidth * ratioX),
        );
        const nextY = Math.min(
            height - nextHeight,
            Math.max(0, anchorY - nextHeight * ratioY),
        );
        view = { x: nextX, y: nextY, w: nextWidth, h: nextHeight };
    }

    function chooseItem(item: NormalizedItem): void {
        onSelect?.(item);
    }

    function wheelZoom(event: WheelEvent): void {
        if (!svgElement || !ready) return;
        event.preventDefault();
        const bounds = svgElement.getBoundingClientRect();
        const anchorX =
            view.x + ((event.clientX - bounds.left) / bounds.width) * view.w;
        const anchorY =
            view.y + ((event.clientY - bounds.top) / bounds.height) * view.h;
        zoom(event.deltaY < 0 ? 0.82 : 1.22, anchorX, anchorY);
    }

    function startPan(event: PointerEvent): void {
        if (!svgElement || event.button !== 0) return;
        const target = event.target as Element | null;
        if (target?.closest('[data-sheet-item]')) return;
        svgElement.setPointerCapture(event.pointerId);
        drag = {
            pointerId: event.pointerId,
            x: event.clientX,
            y: event.clientY,
            viewX: view.x,
            viewY: view.y,
        };
    }

    function pan(event: PointerEvent): void {
        if (!drag || drag.pointerId !== event.pointerId || !svgElement) return;
        const bounds = svgElement.getBoundingClientRect();
        const nextX =
            drag.viewX - ((event.clientX - drag.x) / bounds.width) * view.w;
        const nextY =
            drag.viewY - ((event.clientY - drag.y) / bounds.height) * view.h;
        view = {
            ...view,
            x: Math.min(width - view.w, Math.max(0, nextX)),
            y: Math.min(height - view.h, Math.max(0, nextY)),
        };
    }

    function stopPan(event: PointerEvent): void {
        if (drag?.pointerId === event.pointerId) drag = null;
    }

    function observedCodes(item: NormalizedItem): string[] {
        const observed = item.machine?.observed_options ?? [];
        return observed.length > 0
            ? observed
            : item.machine?.selected_option
              ? [item.machine.selected_option]
              : [];
    }

    function machineTone(item: NormalizedItem, code: string): string {
        if (!observedCodes(item).includes(code)) return 'none';
        if (item.machine?.state === 'ambiguous') return 'ambiguous';
        if (blind || item.expected === null) return 'neutral';
        return item.expected === code ? 'match' : 'mismatch';
    }
    type SheetStencilGeometry = {
        card: Region;
        hole: { cx: number; cy: number; rx: number; ry: number } | null;
        path: string;
        expectedCode: string | null;
    };

    function makeSheetStencilGeometry(
        item: NormalizedItem,
        itemRegion: Region,
        sheetWidth: number,
        sheetHeight: number,
    ): SheetStencilGeometry | null {
        if (!isSheetRegion(itemRegion)) return null;
        const validOptions = item.options.filter(
            (option) =>
                option.region !== null && isSheetRegion(option.region),
        );
        if (validOptions.length === 0) return null;
        const expectedOption =
            item.expected === null
                ? null
                : (validOptions.find(
                      (option) => option.code === item.expected,
                  ) ?? null);
        if (item.expected !== null && !expectedOption) return null;
        const expectedRegion = expectedOption?.region ?? null;
        if (item.expected !== null && !expectedRegion) return null;

        let minX = itemRegion.x * sheetWidth;
        let minY = itemRegion.y * sheetHeight;
        let maxX = (itemRegion.x + itemRegion.w) * sheetWidth;
        let maxY = (itemRegion.y + itemRegion.h) * sheetHeight;
        for (const option of validOptions) {
            const region = option.region;
            if (!region) continue;
            const radiusX = region.w * sheetWidth * 0.59;
            const radiusY = region.h * sheetHeight * 0.59;
            const centerX = (region.x + region.w / 2) * sheetWidth;
            const centerY = (region.y + region.h / 2) * sheetHeight;
            minX = Math.min(minX, centerX - radiusX);
            minY = Math.min(minY, centerY - radiusY);
            maxX = Math.max(maxX, centerX + radiusX);
            maxY = Math.max(maxY, centerY + radiusY);
        }

        const left = Math.max(0, Math.min(sheetWidth, minX));
        const top = Math.max(0, Math.min(sheetHeight, minY));
        const right = Math.max(0, Math.min(sheetWidth, maxX));
        const bottom = Math.max(0, Math.min(sheetHeight, maxY));
        if (right <= left || bottom <= top) return null;

        const card = { x: left, y: top, w: right - left, h: bottom - top };
        const hole = expectedRegion
            ? {
                  cx: (expectedRegion.x + expectedRegion.w / 2) * sheetWidth,
                  cy: (expectedRegion.y + expectedRegion.h / 2) * sheetHeight,
                  rx: expectedRegion.w * sheetWidth * 0.59,
                  ry: expectedRegion.h * sheetHeight * 0.59,
              }
            : null;
        const rectanglePath = `M ${card.x} ${card.y} H ${card.x + card.w} V ${card.y + card.h} H ${card.x} Z`;
        const path = hole
            ? `${rectanglePath} M ${hole.cx - hole.rx} ${hole.cy} a ${hole.rx} ${hole.ry} 0 1 0 ${hole.rx * 2} 0 a ${hole.rx} ${hole.ry} 0 1 0 ${-hole.rx * 2} 0 Z`
            : rectanglePath;

        return { card, hole, path, expectedCode: expectedOption?.code ?? null };
    }

    $effect(() => {
        const dimensions = `${width}:${height}`;
        if (ready && dimensions !== lastDimensions) {
            lastDimensions = dimensions;
            resetView();
        }
    });
</script>

<section
    class="border-border bg-muted/20 flex h-full min-h-0 min-w-0 flex-col overflow-hidden rounded-b-xl border"
    aria-label="Full-sheet evidence map"
>
    <div
        class="border-border bg-background flex items-center justify-between gap-2 border-b px-3 py-2"
    >
        <div class="flex min-w-0 items-center gap-2 text-xs font-semibold">
            <Move class="text-muted-foreground size-3.5" aria-hidden="true" />
            <span class="truncate"
                >{mode === 'raw'
                    ? 'Raw capture'
                    : mode === 'stencil'
                      ? 'Answer stencil'
                      : 'Marked sheet'}</span
            >
            <span class="text-muted-foreground hidden font-normal sm:inline"
                >{items.length} mapped sheet items</span
            >
        </div>
        <div class="flex items-center gap-1.5">
            <button
                type="button"
                class="icon-button"
                aria-label="Zoom out"
                onclick={() => zoom(1.22)}><ZoomOut class="size-3.5" /></button
            >
            <button
                type="button"
                class="icon-button"
                aria-label="Zoom in"
                onclick={() => zoom(0.82)}><ZoomIn class="size-3.5" /></button
            >
            <button
                type="button"
                class="icon-button"
                aria-label="Fit full sheet"
                onclick={resetView}><Maximize2 class="size-3.5" /></button
            >
        </div>
    </div>

    {#if ready}
        <div
            class="relative min-h-0 min-w-0 flex-1 overflow-hidden bg-slate-900 p-2"
        >
            <svg
                bind:this={svgElement}
                class:cursor-grabbing={drag !== null}
                class="h-full min-h-0 w-full cursor-grab touch-none select-none"
                viewBox={`${view.x} ${view.y} ${view.w} ${view.h}`}
                preserveAspectRatio="xMidYMid meet"
                role="img"
                aria-label={`${mode === 'raw' ? 'Raw captured' : mode === 'stencil' ? 'Expected-answer stencil' : 'Machine-marked'} answer sheet`}
                onwheel={wheelZoom}
                onpointerdown={startPan}
                onpointermove={pan}
                onpointerup={stopPan}
                onpointercancel={stopPan}
            >
                <image href={imageUrl} x="0" y="0" {width} {height} />
                {#each items as item (itemId(item))}
                    {@const selected =
                        itemId(item) === String(selectedId ?? '') ||
                        (item.item_id != null &&
                            String(item.item_id) ===
                                String(selectedId ?? ''))}
                    {@const itemRegion = item.crop_rect ?? item.region}
                    {@const stencil =
                        mode === 'stencil' && !blind && itemRegion
                            ? makeSheetStencilGeometry(
                                  item,
                                  itemRegion,
                                  width,
                                  height,
                              )
                            : null}
                    {#if itemRegion}
                        <g
                            data-sheet-item={itemId(item)}
                            class="sheet-item"
                            role="button"
                            tabindex="0"
                            aria-label={`Inspect ${item.label}`}
                            onclick={() => chooseItem(item)}
                            onkeydown={(event) => {
                                if (
                                    event.key === 'Enter' ||
                                    event.key === ' '
                                ) {
                                    event.preventDefault();
                                    chooseItem(item);
                                }
                            }}
                        >
                            {#if stencil}
                                <g
                                    data-omr-sheet-stencil={itemId(item)}
                                    class="stencil-item"
                                >
                                    <path
                                        data-omr-stencil-card
                                        class="stencil-card"
                                        d={stencil.path}
                                        fill="#ffffff"
                                        fill-opacity="1"
                                        fill-rule="evenodd"
                                        stroke="none"
                                    />
                                    <rect
                                        class="stencil-card-border"
                                        x={stencil.card.x}
                                        y={stencil.card.y}
                                        width={stencil.card.w}
                                        height={stencil.card.h}
                                        fill="none"
                                        stroke="rgb(148 163 184 / 0.65)"
                                        stroke-width="1"
                                        vector-effect="non-scaling-stroke"
                                        pointer-events="none"
                                    />
                                    {#if stencil.hole}
                                        <ellipse
                                            data-omr-stencil-hole
                                            data-option={stencil.expectedCode}
                                            class="stencil-hole-rim"
                                            cx={stencil.hole.cx}
                                            cy={stencil.hole.cy}
                                            rx={stencil.hole.rx}
                                            ry={stencil.hole.ry}
                                            fill="none"
                                            stroke="rgb(100 116 139 / 0.85)"
                                            stroke-width="1"
                                            vector-effect="non-scaling-stroke"
                                            pointer-events="none"
                                        />
                                    {/if}
                                </g>
                            {/if}
                            {#if selected}
                                <rect
                                    class="selected-item"
                                    x={itemRegion.x * width}
                                    y={itemRegion.y * height}
                                    width={itemRegion.w * width}
                                    height={itemRegion.h * height}
                                    rx="5"
                                />
                            {/if}
                            {#each item.options as option (option.code)}
                                {#if option.region}
                                    {@const centerX =
                                        (option.region.x +
                                            option.region.w / 2) *
                                        width}
                                    {@const centerY =
                                        (option.region.y +
                                            option.region.h / 2) *
                                        height}
                                    {@const radiusX =
                                        (option.region.w * width) / 2}
                                    {@const radiusY =
                                        (option.region.h * height) / 2}
                                    {@const tone = machineTone(
                                        item,
                                        option.code,
                                    )}
                                    {#if mode === 'markings' && tone !== 'none'}
                                        <ellipse
                                            class={`machine-fill ${tone}`}
                                            cx={centerX}
                                            cy={centerY}
                                            rx={radiusX * 0.78}
                                            ry={radiusY * 0.78}
                                        />
                                    {/if}
                                    {#if mode === 'markings'}
                                        <ellipse
                                            class="geometry-ring"
                                            class:selected-ring={selected}
                                            cx={centerX}
                                            cy={centerY}
                                            rx={radiusX}
                                            ry={radiusY}
                                        />
                                    {/if}
                                    {#if mode === 'markings' && !blind && item.expected === option.code}
                                        <ellipse
                                            class="expected-ring"
                                            cx={centerX}
                                            cy={centerY}
                                            rx={radiusX * 1.14}
                                            ry={radiusY * 1.14}
                                        />
                                    {/if}
                                {/if}
                            {/each}
                            <rect
                                class="item-hit-area"
                                x={itemRegion.x * width}
                                y={itemRegion.y * height}
                                width={itemRegion.w * width}
                                height={itemRegion.h * height}
                            />
                        </g>
                    {/if}
                {/each}
            </svg>
            <p
                class="pointer-events-none absolute bottom-3 left-3 rounded-md bg-slate-950/85 px-2 py-1 text-[10px] text-slate-200"
            >
                Scroll to zoom · drag to pan
            </p>
        </div>
    {:else}
        <div
            class="text-muted-foreground flex min-h-0 flex-1 items-center justify-center p-6 text-center text-xs"
        >
            Full-sheet mapping is unavailable because aligned-image dimensions
            or geometry are missing.
        </div>
    {/if}
</section>

<style>
    .icon-button {
        display: inline-flex;
        min-height: 1.875rem;
        width: 1.875rem;
        align-items: center;
        justify-content: center;
        border: 1px solid var(--border);
        border-radius: 0.375rem;
        background: var(--background);
        color: var(--muted-foreground);
    }

    .icon-button:focus-visible {
        outline: 2px solid var(--ring);
        outline-offset: 2px;
    }

    .sheet-item {
        cursor: pointer;
    }

    .item-hit-area {
        fill: transparent;
        pointer-events: all;
    }

    .geometry-ring,
    .expected-ring,
    .selected-item {
        fill: none;
        vector-effect: non-scaling-stroke;
    }

    .geometry-ring {
        stroke: rgb(56 189 248 / 0.58);
        stroke-width: 1.25;
    }

    .geometry-ring.selected-ring {
        stroke: rgb(255 255 255 / 0.95);
        stroke-width: 2;
    }

    .expected-ring {
        stroke: rgb(16 185 129 / 0.95);
        stroke-width: 2;
        stroke-dasharray: 5 3;
    }

    .selected-item {
        stroke: rgb(250 204 21 / 0.95);
        stroke-width: 2;
    }

    .stencil-card {
        forced-color-adjust: none;
    }

    .stencil-card-border,
    .stencil-hole-rim {
        forced-color-adjust: none;
    }

    .machine-fill {
        stroke-width: 1.5;
        vector-effect: non-scaling-stroke;
    }

    .machine-fill.match {
        fill: rgb(16 185 129 / 0.42);
        stroke: rgb(5 150 105 / 0.9);
    }

    .machine-fill.mismatch {
        fill: rgb(239 68 68 / 0.48);
        stroke: rgb(220 38 38 / 0.95);
    }

    .machine-fill.ambiguous {
        fill: rgb(245 158 11 / 0.48);
        stroke: rgb(217 119 6 / 0.95);
    }

    .machine-fill.neutral {
        fill: rgb(59 130 246 / 0.42);
        stroke: rgb(37 99 235 / 0.95);
    }

    @media (forced-colors: active) {
        .geometry-ring,
        .expected-ring,
        .selected-item,
        .machine-fill {
            stroke: CanvasText;
        }
    }
</style>
