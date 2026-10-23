<script module>
    let visitPaused = false;
</script>

<script>
    import { Link } from '@inertiajs/svelte';
    import { onMount, tick } from 'svelte';
    import { AlertTriangle } from 'lucide-svelte';

    let { alerts = [] } = $props();
    let rail = $state(null);
    let originalGroup = $state(null);
    let userPaused = $state(visitPaused);
    let hovered = $state(false);
    let focused = $state(false);
    let pointerHeld = $state(false);
    let pageVisible = $state(true);
    let reducedMotion = $state(false);
    let overflowing = $state(false);
    let cycleWidth = 0;
    let frame = 0;
    let lastTime = 0;
    let position = 0;
    let measureVersion = 0;
    const items = $derived(alerts.slice(0, 3));
    const stopped = $derived(
        userPaused ||
            hovered ||
            focused ||
            pointerHeld ||
            !pageVisible ||
            reducedMotion ||
            !overflowing,
    );

    function cancelFrame() {
        if (frame) cancelAnimationFrame(frame);
        frame = 0;
        lastTime = 0;
    }

    function schedule() {
        if (!stopped && !frame) frame = requestAnimationFrame(advance);
    }

    function advance(time) {
        frame = 0;
        if (stopped || !rail || !cycleWidth) return;
        const elapsed = lastTime ? Math.min(time - lastTime, 100) : 0;
        lastTime = time;
        position = (position + elapsed * 0.02) % cycleWidth;
        rail.scrollLeft = position;
        schedule();
    }

    function measure() {
        if (!rail || !originalGroup?.parentElement) return;
        const gap =
            parseFloat(
                getComputedStyle(originalGroup.parentElement).columnGap || '0',
            ) || 0;
        const width = originalGroup.getBoundingClientRect().width;
        cycleWidth = width + gap;
        overflowing = width > rail.clientWidth + 1;
        position = overflowing ? rail.scrollLeft % cycleWidth : 0;
        if (!overflowing) rail.scrollLeft = 0;
        if (stopped) cancelFrame();
        else schedule();
    }

    function persistStop() {
        userPaused = true;
        visitPaused = true;
        cancelFrame();
    }

    function onWheel(event) {
        if (event.deltaX || event.deltaY) persistStop();
    }

    function syncVisibility() {
        pageVisible = document.visibilityState === 'visible';
        if (stopped) cancelFrame();
        else schedule();
    }

    function syncMotion(event) {
        reducedMotion = event.matches;
        measure();
    }

    onMount(() => {
        const motionPreference = window.matchMedia(
            '(prefers-reduced-motion: reduce)',
        );
        const resizeObserver = new ResizeObserver(measure);
        motionPreference.addEventListener('change', syncMotion);
        document.addEventListener('visibilitychange', syncVisibility);
        pageVisible = document.visibilityState === 'visible';
        reducedMotion = motionPreference.matches;

        let observedRail = null;
        let observedGroup = null;
        const reconcileObservedElements = () => {
            if (observedRail !== rail) {
                if (observedRail) {
                    resizeObserver.unobserve(observedRail);
                    observedRail.removeEventListener('wheel', onWheel);
                }
                observedRail = rail;
                if (observedRail) {
                    resizeObserver.observe(observedRail);
                    observedRail.addEventListener('wheel', onWheel, {
                        passive: true,
                    });
                }
            }
            if (observedGroup !== originalGroup) {
                if (observedGroup) resizeObserver.unobserve(observedGroup);
                observedGroup = originalGroup;
                if (observedGroup) resizeObserver.observe(observedGroup);
            }
        };

        const settle = async () => {
            const version = ++measureVersion;
            await tick();
            if (version !== measureVersion) return;
            reconcileObservedElements();
            measure();
        };
        void settle();

        return () => {
            measureVersion++;
            cancelFrame();
            resizeObserver.disconnect();
            observedRail?.removeEventListener('wheel', onWheel);
            motionPreference.removeEventListener('change', syncMotion);
            document.removeEventListener('visibilitychange', syncVisibility);
        };
    });

    $effect(() => {
        items;
        if (typeof window !== 'undefined') {
            const version = ++measureVersion;
            void tick().then(() => {
                if (version !== measureVersion || !rail || !originalGroup)
                    return;
                measure();
            });
        }
    });

    $effect(() => {
        if (stopped) cancelFrame();
        else schedule();
    });
</script>

{#if items.length > 0}
    <div class="border-b border-[#5e0c0e] bg-[#7B1113] text-white shadow-xs">
        <div
            class="mx-auto flex min-h-[44px] max-w-7xl px-3 sm:px-6 lg:px-8"
            role="region"
            aria-label="Public announcements"
            title="Hold or press Escape to pause moving titles."
            onmouseenter={() => (hovered = true)}
            onmouseleave={() => { hovered = false; pointerHeld = false; }}
            onfocusin={() => (focused = true)}
            onfocusout={(event) => {
                if (!event.currentTarget.contains(event.relatedTarget))
                    focused = false;
            }}
            onpointerdown={() => (pointerHeld = true)}
            onpointerup={() => (pointerHeld = false)}
            onpointercancel={() => (pointerHeld = false)}
            onkeydown={(event) => {
                if (event.key === 'Escape') persistStop();
            }}
        >
            <span class="sr-only">
                Moving announcement titles stop when hovered or focused. Press
                Escape or press and hold here to stop them for this visit.
                Scroll horizontally or use the links to browse titles.
            </span>
            <div
                bind:this={rail}
                class="focus-visible:ring-white min-w-0 flex-1 overflow-x-hidden scrollbar-none sm:overflow-x-auto sm:scrollbar-none [&::-webkit-scrollbar]:hidden [-ms-overflow-style:none] [scrollbar-width:none] focus-visible:ring-2 focus-visible:outline-none"
                role="region"
                aria-label="Announcement titles; scroll horizontally to browse"
                tabindex="0"
                onscroll={() => {
                    if (stopped && rail && cycleWidth)
                        position = rail.scrollLeft % cycleWidth;
                }}
            >
                <div class="flex w-max items-center whitespace-nowrap">
                    <div
                        bind:this={originalGroup}
                        class="flex shrink-0 items-center"
                    >
                        {#each items as alert (alert.id)}
                            <Link
                                href={alert.href}
                                class="text-white/95 hover:text-white focus-visible:ring-white inline-flex min-h-[44px] shrink-0 items-center gap-3 py-1 pr-6 text-sm focus-visible:ring-2 focus-visible:outline-none sm:pr-8"
                            >
                                {@render NoticeContent(alert)}
                            </Link>
                        {/each}
                    </div>
                    {#if overflowing}
                        <div
                            class="flex shrink-0 items-center"
                            aria-hidden="true"
                            inert
                        >
                            {#each items as alert (alert.id)}
                                <span
                                    class="inline-flex min-h-[44px] shrink-0 items-center gap-3 py-1 pr-6 text-sm sm:pr-8"
                                >
                                    {@render NoticeContent(alert)}
                                </span>
                            {/each}
                        </div>
                    {/if}
                </div>
            </div>
        </div>
    </div>
{/if}

{#snippet NoticeContent(alert)}
    <div class="inline-flex items-center gap-2 text-xs">
        {#if alert.priority === 'urgent'}
            <span class="inline-flex items-center gap-1 font-bold text-amber-300 text-xs shrink-0">
                <AlertTriangle class="size-3.5 text-amber-300 shrink-0" aria-hidden="true" />
                <span class="uppercase tracking-wider text-[11px]">Urgent</span>
            </span>
        {/if}
        <span class="font-medium text-white tracking-tight max-w-[380px] truncate sm:max-w-none hover:underline underline-offset-2">
            {alert.title}
        </span>
        <span class="text-white/40 pl-3 shrink-0 select-none" aria-hidden="true">✦</span>
    </div>
{/snippet}
