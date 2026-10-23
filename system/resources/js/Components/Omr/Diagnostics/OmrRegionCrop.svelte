<script lang="ts">
    import { onDestroy, onMount } from 'svelte';
    import { cropBox, type Region } from '../../../lib/omrRegionCrop.js';
    import {
        buildBubbleLayerRecipe,
        cropAspectRatio,
        normalizeOverlayInput,
        overlayAccessibleText,
        overlayLayerClass,
        overlayStateIcon,
        type BubbleOverlayInput,
        type BubbleOverlayRecipe,
    } from '../../../lib/omrBubbleEvidenceOverlay.js';
    import { presentOmrEvidenceReason } from './omrEvidenceReasons';

    let {
        /** Canonical item projection: machine, expected, geometry, review. */
        item = null,
        machine = null,
        expected = null,
        geometry = null,
        review = null,
        /** Pinned aligned-page or dedicated-crop provenance. */
        asset = null,
        page_asset = null,
        crop_source = null,
        /** Existing hosts use these URL props until their cutover lands. */
        cropUrl = null,
        imageUrl = null,
        region = null,
        options = [],
        detectedOption = null,
        expectedOption = null,
        state: itemState = null,
        blind = false,
        mode = 'markings',
        alt = '',
        title = '',
        width = 170,
        id = 'omr-region-crop',
    } = $props();

    let container = $state<HTMLElement | null>(null);
    let loaded = $state(false);
    let failed = $state(false);
    let visible = $state(false);
    let observer: IntersectionObserver | null = null;
    let naturalWidth = $state(0);
    let naturalHeight = $state(0);

    const input = $derived<BubbleOverlayInput>({
        item,
        machine,
        expected,
        geometry,
        review,
        asset,
        page_asset,
        crop_source,
        cropUrl,
        imageUrl,
        region,
        options,
        detectedOption,
        expectedOption,
        state: itemState,
    });
    const normalized = $derived(normalizeOverlayInput(input));
    const recipe = $derived<BubbleOverlayRecipe>(buildBubbleLayerRecipe(input));
    const displayMode = $derived(
        mode === 'raw' || mode === 'stencil' || mode === 'markings'
            ? mode
            : 'markings',
    );
    const stencilExpectedVisual = $derived(
        recipe.status === 'ready' && recipe.expected_option !== null
            ? (recipe.visuals.find(
                  (visual) =>
                      visual.code === recipe.expected_option &&
                      visual.expected_ring,
              ) ?? null)
            : null,
    );
    const stencilAvailable = $derived(
        displayMode === 'stencil' &&
            !blind &&
            recipe.status === 'ready' &&
            (recipe.expected_option === null ||
                stencilExpectedVisual !== null),
    );
    const stencilHole = $derived(stencilExpectedVisual?.geometry ?? null);
    function makeStencilCardPath(hole: Region | null): string {
        if (hole === null) return 'M 0 0 H 1 V 1 H 0 Z';
        const cx = hole.x + hole.w / 2;
        const cy = hole.y + hole.h / 2;
        const rx = hole.w * 0.59;
        const ry = hole.h * 0.59;
        return `M 0 0 H 1 V 1 H 0 Z M ${cx - rx} ${cy} a ${rx} ${ry} 0 1 0 ${rx * 2} 0 a ${rx} ${ry} 0 1 0 ${-rx * 2} 0 Z`;
    }
    const usesModeImage = $derived(
        displayMode === 'raw' && normalized.image_url !== null,
    );
    const sourceUrl = $derived(
        usesModeImage ? normalized.image_url : normalized.source_url,
    );
    const isDedicatedCrop = $derived(
        !usesModeImage &&
            (normalized.crop_url !== null ||
                normalized.geometry.crop_source?.kind === 'dedicated_crop' ||
                normalized.geometry.crop_source?.type === 'dedicated_crop'),
    );
    const usesAlignedPage = $derived(!isDedicatedCrop && sourceUrl !== null);
    const legacyViewport = $derived(
        normalized.legacy
            ? cropBox(
                  normalized.geometry.item_rect ??
                      normalized.geometry.crop_rect,
              )
            : null,
    );
    const displayViewport = $derived(
        recipe.status === 'ready' ? recipe.viewport : legacyViewport,
    );
    const ratio = $derived(
        recipe.status === 'ready'
            ? cropAspectRatio(recipe.viewport, recipe.asset)
            : naturalWidth > 0 && naturalHeight > 0 && displayViewport
              ? (displayViewport.w * naturalWidth) /
                (displayViewport.h * naturalHeight)
              : null,
    );
    const viewportStyle = $derived(
        ratio !== null && Number.isFinite(ratio)
            ? `aspect-ratio:${ratio};`
            : '',
    );
    const imageStyle = $derived(
        usesAlignedPage && displayViewport
            ? `position:absolute;width:${100 / displayViewport.w}%;max-width:none;height:auto;left:${(-displayViewport.x / displayViewport.w) * 100}%;top:${(-displayViewport.y / displayViewport.h) * 100}%;`
            : '',
    );
    const stateIcon = $derived(overlayStateIcon(recipe));
    const accessibleText = $derived(overlayAccessibleText(recipe));
    const modeAccessibleText = $derived(
        displayMode === 'raw'
            ? 'Raw captured evidence. No interpretive overlays are shown.'
            : displayMode === 'stencil'
              ? stencilAvailable
                  ? recipe.expected_option === null
                      ? 'Blank-question stencil. Mapped bubbles are covered; no answer hole is shown.'
                      : 'Expected-answer stencil. Non-expected bubbles are covered; the expected hole reveals the scan.'
                  : 'Expected-answer stencil unavailable. Expected truth is not disclosed.'
              : accessibleText,
    );
    const hatchId = $derived(
        `omr-hatch-${String(id).replace(/[^a-zA-Z0-9_-]/g, '-')}`,
    );
    const titleText = $derived(
        title || alt || recipe.state_label || 'Evidence crop',
    );
    const unavailableReasons = $derived(
        recipe.status === 'unavailable'
            ? recipe.unavailable_reasons.map(presentOmrEvidenceReason)
            : [],
    );
    const unavailableText = $derived(
        recipe.status === 'unavailable'
            ? unavailableReasons.map((reason) => reason.message).join('; ')
            : 'Crop unavailable',
    );

    function loadWhenVisible(): void {
        if (!sourceUrl || !visible) return;
        loaded = false;
        failed = false;
    }

    function imageLoaded(event: Event): void {
        const image = event.currentTarget as HTMLImageElement | null;
        naturalWidth = Number(image?.naturalWidth ?? 0);
        naturalHeight = Number(image?.naturalHeight ?? 0);
        loaded = true;
    }

    function imageFailed(): void {
        failed = true;
        loaded = false;
    }

    onMount(() => {
        if (!('IntersectionObserver' in window)) {
            visible = true;
            loadWhenVisible();
            return;
        }
        observer = new IntersectionObserver(
            (entries) => {
                if (entries.some((entry) => entry.isIntersecting)) {
                    visible = true;
                    observer?.disconnect();
                }
            },
            { rootMargin: '160px' },
        );
        if (container) observer.observe(container);
    });

    onDestroy(() => observer?.disconnect());
</script>

<div
    class="region-crop"
    class:aligned-page={usesAlignedPage}
    class:ready={recipe.status === 'ready'}
    class:unavailable={recipe.status === 'unavailable'}
    class:failed
    bind:this={container}
    id={id}
    role="group"
    aria-label={titleText}
    aria-describedby={`${id}-description`}
    data-omr-crop-status={recipe.status}
    data-omr-crop-mode={displayMode}
    data-omr-evidence-state={recipe.machine_state ?? 'unavailable'}
    style={viewportStyle}
>
    {#if sourceUrl && visible && !failed}
        <div class="crop-viewport">
            <img
                class="crop-img"
                class:aligned-page-crop={usesAlignedPage}
                style={imageStyle}
                src={sourceUrl}
                alt=""
                aria-hidden="true"
                {width}
                loading="lazy"
                decoding="async"
                onload={imageLoaded}
                onerror={imageFailed}
                draggable="false"
            />
            {#if displayMode === 'markings' && recipe.status === 'ready'}
                <svg
                    class="bubble-overlay"
                    viewBox="0 0 1 1"
                    preserveAspectRatio="none"
                    role="img"
                    aria-label={modeAccessibleText}
                    data-omr-bubble-overlay
                >
                    <title>{recipe.state_label}</title>
                    <desc id={`${id}-svg-description`}
                        >{modeAccessibleText}</desc
                    >
                    <defs>
                        <pattern
                            id={hatchId}
                            width="0.1"
                            height="0.1"
                            patternUnits="objectBoundingBox"
                            patternTransform="rotate(45)"
                        >
                            <path
                                class="hatch-line"
                                d="M 0 0 L 0 1"
                                vector-effect="non-scaling-stroke"
                            />
                        </pattern>
                    </defs>
                    {#each recipe.layers as layer, layerIndex (`${layer.kind}:${layer.code}:${layerIndex}`)}
                        <g
                            class={`bubble-layer ${overlayLayerClass(layer)}`}
                            data-layer={layer.kind}
                            data-option={layer.code}
                            role="img"
                            aria-label={layer.aria_label}
                        >
                            <ellipse
                                cx={layer.ellipse.cx}
                                cy={layer.ellipse.cy}
                                rx={layer.ellipse.rx}
                                ry={layer.ellipse.ry}
                                fill={layer.kind === 'candidate-hatch'
                                    ? `url(#${hatchId})`
                                    : layer.fill}
                                stroke={layer.stroke}
                                class="bubble-ellipse"
                                data-tone={layer.tone}
                                data-pattern={layer.pattern ?? undefined}
                            />
                            {#if layer.kind !== 'expected-ring'}
                                <text
                                    class="bubble-icon"
                                    x={layer.ellipse.cx}
                                    y={layer.ellipse.cy}
                                    aria-hidden="true">{layer.icon}</text
                                >
                            {/if}
                        </g>
                    {/each}
                </svg>
                <span class="overlay-state" aria-hidden="true"
                    ><span class="overlay-state-icon">{stateIcon}</span>
                    {recipe.state_label}</span
                >
            {:else if stencilAvailable}
                <svg
                    class="bubble-overlay stencil-overlay"
                    viewBox="0 0 1 1"
                    preserveAspectRatio="none"
                    role="img"
                    aria-label={modeAccessibleText}
                    data-omr-bubble-stencil
                >
                    <title>{recipe.expected_option === null
                            ? 'Blank-question stencil'
                            : 'Expected-answer stencil'}</title>
                    <desc id={`${id}-stencil-description`}>
                        {modeAccessibleText}
                    </desc>
                    <path
                        data-omr-stencil-card
                        class="stencil-card"
                        d={makeStencilCardPath(stencilHole)}
                        fill="#ffffff"
                        fill-opacity="1"
                        fill-rule="evenodd"
                        stroke="none"
                    />
                    <rect
                        class="stencil-card-border"
                        x="0"
                        y="0"
                        width="1"
                        height="1"
                        fill="none"
                        stroke="rgb(148 163 184 / 0.65)"
                        stroke-width="1"
                        vector-effect="non-scaling-stroke"
                        pointer-events="none"
                    />
                    {#if stencilHole && stencilExpectedVisual}
                        <ellipse
                            data-omr-stencil-hole
                            data-option={stencilExpectedVisual.code}
                            class="stencil-hole-rim"
                            cx={stencilHole.x + stencilHole.w / 2}
                            cy={stencilHole.y + stencilHole.h / 2}
                            rx={stencilHole.w * 0.59}
                            ry={stencilHole.h * 0.59}
                            fill="none"
                            stroke="rgb(100 116 139 / 0.85)"
                            stroke-width="1"
                            vector-effect="non-scaling-stroke"
                            pointer-events="none"
                        />
                    {/if}
                </svg>
            {:else if displayMode === 'stencil'}
                <span class="crop-note" role="status">
                    Stencil unavailable; expected geometry is hidden or missing.
                </span>
            {:else if displayMode === 'raw'}
                <span class="sr-only"
                    >Raw mode has no interpretive overlay.</span
                >
            {:else}
                <span class="crop-note" role="status">
                    {unavailableText}
                    {#each unavailableReasons as reason}
                        <details class="ml-1">
                            <summary class="cursor-pointer text-muted-foreground">Technical details</summary>
                            <span class="text-muted-foreground">{reason.technicalDetail}</span>
                        </details>
                    {/each}
                </span>
            {/if}
        </div>
    {:else if failed}
        <span class="crop-note" role="status">Crop unavailable</span>
    {:else if !sourceUrl}
        <span class="crop-note" role="status">
            {unavailableText}
            {#each unavailableReasons as reason}
                <details class="ml-1">
                    <summary class="cursor-pointer text-muted-foreground">Technical details</summary>
                    <span class="text-muted-foreground">{reason.technicalDetail}</span>
                </details>
            {/each}
        </span>
    {:else}
        <span class="crop-note" role="status">Loading crop…</span>
    {/if}

    <span id={`${id}-description`} class="sr-only">{modeAccessibleText}</span>
    {#if recipe.review.reviewed}
        <span class="review-summary" data-review-summary>
            <span class="review-badge">Reviewed</span>
            <span>{recipe.review.text}</span>
        </span>
    {/if}
</div>

<style>
    .region-crop {
        position: relative;
        width: 100%;
        min-height: 44px;
        overflow: hidden;
        border: 0;
        border-radius: 5px;
        background: var(--omr-stage-deep, #16211a);
        color: var(--omr-muted, #8a9a90);
        display: grid;
        place-items: center;
        font: 10px var(--font-mono, monospace);
        box-shadow: inset 0 0 0 1px var(--omr-border, #d8dee6);
    }
    .region-crop.aligned-page {
        min-height: 0;
    }
    .region-crop.ready {
        box-shadow: inset 0 0 0 1px color-mix(in srgb, #2f8f62 55%, transparent);
    }
    .region-crop.unavailable {
        box-shadow: inset 0 0 0 1px color-mix(in srgb, #c58b2e 60%, transparent);
    }
    .crop-viewport {
        position: relative;
        width: 100%;
        height: 100%;
        min-height: inherit;
        overflow: hidden;
    }
    .crop-img {
        display: block;
        width: 100%;
        height: auto;
        min-height: 44px;
        object-fit: contain;
        user-select: none;
    }
    .crop-img.aligned-page-crop {
        min-height: 0;
    }
    .bubble-overlay {
        position: absolute;
        inset: 0;
        display: block;
        width: 100%;
        height: 100%;
        overflow: visible;
        pointer-events: none;
    }
    .stencil-card,
    .stencil-card-border,
    .stencil-hole-rim {
        forced-color-adjust: none;
    }
    .bubble-layer {
        pointer-events: auto;
        outline: none;
    }
    .bubble-layer:focus-visible {
        outline: 2px solid currentColor;
        outline-offset: 2px;
    }
    .bubble-ellipse {
        vector-effect: non-scaling-stroke;
        stroke-width: 0.012;
    }
    .machine-fill .bubble-ellipse {
        stroke: none;
        fill-opacity: 0.38;
    }
    .machine-fill.match {
        color: #2fdd8d;
    }
    .machine-fill.mismatch {
        color: #ff6b68;
    }
    .machine-fill.ambiguous,
    .candidate-hatch.ambiguous,
    .candidate-hatch.unreadable {
        color: #f3b94d;
    }
    .machine-fill.neutral,
    .candidate-hatch.neutral {
        color: #5ca8d8;
    }
    .candidate-hatch .bubble-ellipse {
        fill-opacity: 0.18;
        stroke: currentColor;
        stroke-dasharray: 0.03 0.018;
    }
    .expected-ring {
        color: #4fda8b;
    }
    .expected-ring .bubble-ellipse {
        fill: none;
        stroke: currentColor;
        stroke-width: 0.025;
        stroke-dasharray: 0.045 0.02;
    }
    .bubble-icon {
        fill: currentColor;
        font: 700 0.09px var(--font-mono, monospace);
        text-anchor: middle;
        dominant-baseline: central;
        paint-order: stroke;
        stroke: #111a14;
        stroke-width: 0.015;
        pointer-events: none;
    }
    .hatch-line {
        fill: none;
        stroke: currentColor;
        stroke-width: 0.025;
    }
    .overlay-state {
        position: absolute;
        top: 5px;
        left: 5px;
        display: inline-flex;
        align-items: center;
        gap: 4px;
        max-width: calc(100% - 10px);
        padding: 3px 5px;
        border-radius: 3px;
        background: color-mix(in srgb, #0b1510 78%, transparent);
        color: #e8f3eb;
        font: 700 9px var(--font-mono, monospace);
        line-height: 1.2;
        pointer-events: none;
    }
    .overlay-state-icon {
        font-size: 11px;
        line-height: 1;
    }
    .crop-note {
        padding: 8px 6px;
        color: #f3d28d;
        text-align: center;
        line-height: 1.3;
    }
    .review-summary {
        position: absolute;
        right: 5px;
        bottom: 5px;
        left: 5px;
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 6px;
        padding: 3px 5px;
        background: color-mix(in srgb, #0b1510 82%, transparent);
        color: #e8f3eb;
        font: 700 9px var(--font-mono, monospace);
        line-height: 1.2;
    }
    .review-badge {
        border: 1px solid currentColor;
        border-radius: 999px;
        padding: 1px 4px;
        text-transform: uppercase;
        letter-spacing: 0.04em;
    }
    .sr-only {
        position: absolute;
        width: 1px;
        height: 1px;
        padding: 0;
        margin: -1px;
        overflow: hidden;
        clip: rect(0, 0, 0, 0);
        white-space: nowrap;
        border: 0;
    }
    @media (forced-colors: active) {
        .region-crop {
            border: 1px solid CanvasText;
            box-shadow: none;
        }
        .bubble-overlay {
            forced-color-adjust: none;
        }
        .machine-fill .bubble-ellipse {
            fill: Highlight;
            stroke: HighlightText;
            fill-opacity: 0.45;
        }
        .candidate-hatch .bubble-ellipse {
            fill: Canvas;
            stroke: Highlight;
            fill-opacity: 1;
        }
        .expected-ring .bubble-ellipse {
            stroke: HighlightText;
            stroke-dasharray: 0.05 0.025;
        }
        .bubble-icon,
        .overlay-state,
        .crop-note {
            color: CanvasText;
            background: Canvas;
            text-shadow: none;
        }
        .review-badge {
            color: CanvasText;
            border-color: CanvasText;
        }
    }
    @media (prefers-reduced-motion: reduce) {
        .region-crop,
        .crop-img,
        .bubble-overlay {
            transition: none;
        }
    }
</style>
