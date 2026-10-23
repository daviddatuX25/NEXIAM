<script>
    import { onMount } from 'svelte';
    import NexiamLogo from '@/Components/kit/NexiamLogo.svelte';
    let {
        institution = {},
        className = '',
    } = $props();

    let container = $state(null);
    let measureEl = $state(null);
    let overflowing = $state(false);

    const institutionAbbr = $derived.by(() => {
        if (typeof institution?.short_name === 'string' && institution.short_name.trim().length > 0) {
            return institution.short_name.trim();
        }
        if (typeof institution?.abbreviation === 'string' && institution.abbreviation.trim().length > 0) {
            return institution.abbreviation.trim();
        }
        if (typeof institution?.acronym === 'string' && institution.acronym.trim().length > 0) {
            return institution.acronym.trim();
        }
        if (typeof institution?.name === 'string' && institution.name.trim().length > 0) {
            const initials = institution.name
                .split(/\s+/)
                .filter((w) => w.length > 0 && !/^(of|the|and|in|at|for)$/i.test(w))
                .map((w) => w[0].toUpperCase())
                .join('');
            if (initials.length > 0) return initials;
        }
        return 'ISPSC';
    });

    const campusName = $derived.by(() => {
        if (!institution?.campus || typeof institution.campus !== 'string') return '';
        return institution.campus.replace(/\s+Campus$/i, '').trim();
    });

    const titleText = $derived.by(() => {
        const parts = [institutionAbbr];
        if (campusName) parts.push(campusName);
        parts.push('Admission Portal');
        return parts.join(' ');
    });

    function checkOverflow() {
        if (!container || !measureEl) return;
        const textWidth = measureEl.getBoundingClientRect().width;
        const containerWidth = container.clientWidth;
        overflowing = textWidth > containerWidth + 2;
    }

    onMount(() => {
        checkOverflow();
        if (typeof ResizeObserver === 'function' && container) {
            const ro = new ResizeObserver(() => checkOverflow());
            ro.observe(container);
            return () => ro.disconnect();
        }
    });
</script>

<div class="min-w-0 flex flex-col justify-center overflow-hidden {className}">
    <!-- Invisible measurer to reliably check if titleText overflows the container at any viewport width -->
    <span
        bind:this={measureEl}
        class="invisible absolute pointer-events-none whitespace-nowrap text-xs sm:text-sm font-bold tracking-tight opacity-0"
        aria-hidden="true"
    >
        {titleText}
    </span>

    <div
        bind:this={container}
        class="brand-title-container min-w-0 overflow-hidden relative {overflowing ? '[mask-image:linear-gradient(to_right,black_85%,transparent_100%)]' : ''}"
        title={titleText}
    >
        {#if overflowing}
            <div
                class="brand-marquee-track inline-flex items-center gap-6 whitespace-nowrap"
                tabindex="-1"
                aria-label={titleText}
            >
                <span class="text-foreground text-xs sm:text-sm font-bold tracking-tight leading-tight shrink-0">{titleText}</span>
                <span class="text-muted-foreground/60 text-xs shrink-0" aria-hidden="true">&bull;</span>
                <span class="text-foreground text-xs sm:text-sm font-bold tracking-tight leading-tight shrink-0" aria-hidden="true">{titleText}</span>
                <span class="text-muted-foreground/60 text-xs shrink-0" aria-hidden="true">&bull;</span>
            </div>
        {:else}
            <span
                class="text-foreground block truncate text-xs sm:text-sm font-bold tracking-tight leading-tight"
            >
                {titleText}
            </span>
        {/if}
    </div>
    <span
        class="text-muted-foreground/80 flex items-center gap-1.5 text-[10px] sm:text-[11px] font-medium leading-none mt-0.5"
    >
        <span>powered by</span>
        <span class="inline-flex items-center gap-1 font-bold text-foreground/85">
            <NexiamLogo variant="mark" size="xs" class="size-3.5 inline-block" />
            NEXIAM
        </span>
    </span>
</div>

<style>
    @keyframes brandMarqueeAnim {
        0% {
            transform: translateX(0);
        }
        100% {
            transform: translateX(-50%);
        }
    }

    .brand-marquee-track {
        display: inline-flex;
        width: max-content;
    }

    @media (prefers-reduced-motion: no-preference) {
        .brand-marquee-track {
            animation: brandMarqueeAnim 12s linear infinite;
        }
        .brand-marquee-track:hover,
        .brand-marquee-track:focus {
            animation-play-state: paused;
        }
    }
</style>
