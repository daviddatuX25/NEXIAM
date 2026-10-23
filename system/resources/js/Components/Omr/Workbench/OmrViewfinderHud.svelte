<script lang="ts">
    import {
        viewfinderFramingGuidance,
        type ViewfinderResult,
    } from '@/lib/omrViewfinderWorker';

    let {
        result = null,
        available = true,
        manual = false,
        videoWidth = 0,
        videoHeight = 0,
        lockProgress = 0,
        captureBlockedReason = null,
    }: {
        result?: ViewfinderResult | null;
        available?: boolean;
        manual?: boolean;
        videoWidth?: number;
        videoHeight?: number;
        lockProgress?: number;
        captureBlockedReason?: string | null;
    } = $props();

    const indicators = [
        { key: 'in_bounds', label: 'In frame' },
        { key: 'angle_ok', label: 'Angle' },
        { key: 'steady_ok', label: 'Steady' },
        { key: 'lighting_ok', label: 'Lighting' },
    ] as const;
    const locked = $derived(
        result !== null && Object.values(result.gates).every(Boolean),
    );
    const guidance = $derived(
        result?.quad ? viewfinderFramingGuidance(result) : [],
    );
    const status = $derived(
        captureBlockedReason
            ? `${locked ? 'Quality checks pass. ' : ''}${captureBlockedReason.toLowerCase().includes('capture is paused') ? captureBlockedReason : `Capture is paused: ${captureBlockedReason}`}`
            : !available
              ? 'Live guidance unavailable. Use the manual shutter.'
              : manual
                ? 'Manual shutter ready. Check the sheet before capturing.'
                : !result?.quad
                  ? 'Looking for the answer sheet. Manual capture is available.'
                  : locked
                    ? `Sheet aligned. Auto dwell ${Math.round(Math.max(0, Math.min(1, lockProgress)) * 100)}%.`
                    : 'Adjust the sheet using the guidance below.',
    );
    const polygon = $derived(
        result?.quad
            ?.map(({ x, y }) => `${x * videoWidth},${y * videoHeight}`)
            .join(' ') ?? '',
    );
</script>

{#if result?.quad && videoWidth > 0 && videoHeight > 0}
    <svg
        class="pointer-events-none absolute inset-0 z-10 size-full"
        viewBox={`0 0 ${videoWidth} ${videoHeight}`}
        preserveAspectRatio="xMidYMid slice"
        aria-hidden="true"
        data-omr-viewfinder-polygon
    >
        <polygon
            points={polygon}
            fill={locked
                ? 'rgba(16, 185, 129, .12)'
                : 'rgba(245, 158, 11, .11)'}
            stroke={locked ? '#34d399' : '#fbbf24'}
            stroke-width={Math.max(
                2,
                Math.min(videoWidth, videoHeight) * 0.005,
            )}
            stroke-linejoin="round"
        />
    </svg>
{/if}

<div
    class="pointer-events-none absolute right-2.5 bottom-2.5 z-20 max-w-[min(18rem,calc(100%-1.25rem))] rounded-lg border border-white/10 bg-[#0d1311]/85 p-2 text-white shadow-md backdrop-blur-sm"
    data-omr-viewfinder-hud
    data-capture-blocked={Boolean(captureBlockedReason)}
>
    <p
        class="text-[11px] leading-snug font-medium"
        role="status"
        aria-live="polite"
    >
        {status}
    </p>
    {#if guidance.length > 0}
        <ul class="mt-1 space-y-0.5 text-[10px] leading-snug text-amber-200" data-omr-framing-guidance>
            {#each guidance as item (item)}
                <li>{item}</li>
            {/each}
        </ul>
    {/if}
    <div
        class="mt-1.5 flex flex-wrap gap-1"
        aria-label="Camera quality checks"
    >
        {#each indicators as indicator (indicator.key)}
            {@const passed = result?.gates[indicator.key] === true}
            <span
                class={`inline-flex items-center gap-1 rounded px-1.5 py-0.5 text-[10px] font-medium leading-none ${passed ? 'bg-emerald-500/20 text-emerald-300' : 'bg-amber-500/20 text-amber-200'}`}
                data-omr-gate={indicator.key}
                data-passed={passed}
            >
                <span class={`size-1.5 rounded-full shrink-0 ${passed ? 'bg-emerald-400' : 'bg-amber-400'}`} aria-hidden="true"></span>
                <span>{indicator.label}: {passed ? 'Ready' : 'Adjust'}</span>
            </span>
        {/each}
    </div>
</div>

