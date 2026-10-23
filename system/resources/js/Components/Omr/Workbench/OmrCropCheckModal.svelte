<script lang="ts">
    import { onDestroy } from 'svelte';
    import type { ViewfinderQuad } from '@/lib/omrViewfinderWorker';

    type Point = { x: number; y: number };
    let {
        file,
        initialQuad = null,
        disabled = false,
        onUse,
        onKeep,
        onCancel,
    }: {
        file: File;
        initialQuad?: ViewfinderQuad | null;
        disabled?: boolean;
        onUse: (quad: ViewfinderQuad) => void;
        onKeep: () => void;
        onCancel: () => void;
    } = $props();

    let previewUrl = $state('');
    let corners = $state<ViewfinderQuad>(initialQuad ? structuredClone(initialQuad) : [
        { x: 0.08, y: 0.08 },
        { x: 0.92, y: 0.08 },
        { x: 0.92, y: 0.92 },
        { x: 0.08, y: 0.92 },
    ]);
    let activeCorner = $state<number | null>(null);
    let imageWidth = $state(0);
    let imageHeight = $state(0);

    $effect(() => {
        previewUrl = URL.createObjectURL(file);
    });
    onDestroy(() => {
        if (previewUrl) URL.revokeObjectURL(previewUrl);
    });

    const cornerNames = ['TL', 'TR', 'BR', 'BL'];
    const validQuad = $derived.by(() => {
        if (corners.some(({ x, y }) => x < 0 || x > 1 || y < 0 || y > 1)) return false;
        const crosses = corners.map((point, index) => {
            const next = corners[(index + 1) % 4];
            const after = corners[(index + 2) % 4];
            return (next.x - point.x) * (after.y - next.y) - (next.y - point.y) * (after.x - next.x);
        });
        return crosses.every((cross) => cross > 0.0001) || crosses.every((cross) => cross < -0.0001);
    });

    function onImageLoaded(event: Event): void {
        const image = event.currentTarget as HTMLImageElement;
        imageWidth = image.naturalWidth;
        imageHeight = image.naturalHeight;
    }

    function moveCorner(event: PointerEvent): void {
        if (activeCorner === null || !event.currentTarget) return;
        const rect = (event.currentTarget as SVGSVGElement).getBoundingClientRect();
        corners[activeCorner] = {
            x: Math.max(0, Math.min(1, (event.clientX - rect.left) / rect.width)),
            y: Math.max(0, Math.min(1, (event.clientY - rect.top) / rect.height)),
        };
        corners = [...corners] as ViewfinderQuad;
    }

    function nudgeCorner(index: number, axis: 'x' | 'y', amount: number): void {
        const point = corners[index];
        corners[index] = { ...point, [axis]: Math.max(0, Math.min(1, point[axis] + amount)) };
        corners = [...corners] as ViewfinderQuad;
    }
</script>

<div class="fixed inset-0 z-[120] flex items-end justify-center bg-black/60 backdrop-blur-sm sm:items-center sm:p-4" role="presentation" onclick={(event) => { if (event.target === event.currentTarget) onCancel(); }}>
    <section class="bg-card text-card-foreground border-border flex max-h-[94dvh] w-full flex-col overflow-hidden rounded-t-2xl border shadow-2xl sm:max-w-4xl sm:rounded-2xl" role="dialog" aria-modal="true" aria-labelledby="omr-crop-check-title" data-omr-crop-check>
        <header class="border-border flex items-center justify-between gap-4 border-b px-4 py-3 sm:px-6">
            <div><h2 id="omr-crop-check-title" class="text-base font-semibold">Crop Check</h2><p class="text-muted-foreground text-xs">Adjust the page corners, or keep the original image.</p></div>
            <button type="button" class="rounded-md px-3 py-2 text-sm hover:bg-muted" onclick={onCancel}>Cancel</button>
        </header>
        <div class="min-h-0 flex-1 overflow-auto p-3 sm:p-5">
            <div class="relative mx-auto w-fit max-w-full" style:aspect-ratio={imageWidth && imageHeight ? `${imageWidth} / ${imageHeight}` : undefined}>
                <img src={previewUrl} alt="Captured answer sheet for crop review" onload={onImageLoaded} class="block max-h-[58dvh] max-w-full object-contain" />
                <svg viewBox="0 0 1 1" preserveAspectRatio="none" class="absolute inset-0 size-full touch-none" onpointermove={moveCorner} onpointerup={() => (activeCorner = null)} onpointercancel={() => (activeCorner = null)}>
                    <polygon points={corners.map(({ x, y }) => `${x},${y}`).join(' ')} fill="rgba(16,185,129,.12)" stroke={validQuad ? '#34d399' : '#fb7185'} stroke-width="0.005" vector-effect="non-scaling-stroke" />
                    {#each corners as corner, index (cornerNames[index])}
                        <circle cx={corner.x} cy={corner.y} r="0.022" fill="#fff" stroke="#059669" stroke-width="0.006" vector-effect="non-scaling-stroke" tabindex="0" role="slider" aria-label={`${cornerNames[index]} crop corner`} aria-valuetext={`${Math.round(corner.x * 100)}%, ${Math.round(corner.y * 100)}%`} onpointerdown={(event) => { activeCorner = index; (event.currentTarget as SVGCircleElement).setPointerCapture(event.pointerId); }} onkeydown={(event) => { const amount = event.shiftKey ? 0.01 : 0.002; if (event.key === 'ArrowLeft') { event.preventDefault(); nudgeCorner(index, 'x', -amount); } if (event.key === 'ArrowRight') { event.preventDefault(); nudgeCorner(index, 'x', amount); } if (event.key === 'ArrowUp') { event.preventDefault(); nudgeCorner(index, 'y', -amount); } if (event.key === 'ArrowDown') { event.preventDefault(); nudgeCorner(index, 'y', amount); } }} />
                        <text x={corner.x} y={corner.y - 0.035} text-anchor="middle" font-size="0.035" fill="#fff" stroke="#111" stroke-width="0.003" paint-order="stroke">{cornerNames[index]}</text>
                    {/each}
                </svg>
            </div>
            {#if !validQuad}<p class="mt-3 text-center text-xs text-rose-600" role="alert">Corners must form a non-crossing quadrilateral.</p>{/if}
        </div>
        <footer class="border-border flex flex-col-reverse gap-2 border-t px-4 py-3 sm:flex-row sm:justify-end sm:px-6">
            <button type="button" class="rounded-md border px-4 py-2 text-sm" onclick={onCancel}>Retake / Cancel</button>
            <button type="button" class="rounded-md border px-4 py-2 text-sm disabled:cursor-not-allowed disabled:opacity-50" onclick={onKeep} disabled={disabled}>Keep Original</button>
            <button type="button" class="bg-primary text-primary-foreground rounded-md px-4 py-2 text-sm font-semibold disabled:cursor-not-allowed disabled:opacity-50" disabled={!validQuad || disabled} onclick={() => onUse(corners)}>Straighten &amp; Use</button>
        </footer>
    </section>
</div>
