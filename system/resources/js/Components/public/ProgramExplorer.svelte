<script>
    import { onMount } from 'svelte';
    import { ArrowUpRight, GraduationCap } from 'lucide-svelte';
    import { Link } from '@inertiajs/svelte';

    let { programs = [], preview = false } = $props();
    const rows = $derived.by(() => {
        if (programs.length < 2) return programs.length ? [programs] : [];
        const split = Math.ceil(programs.length / 2);
        return [programs.slice(0, split), programs.slice(split)];
    });
    let rails = [];
    let groups = [];
    let cycles = [];
    let positions = [];
    let initialized = [];
    let overflowing = $state([]);
    let failedImages = $state({});
    let reducedMotion = $state(false);
    let running = $state(false);
    let visibleRows = [];
    let pageVisible = true;
    let pausedRows = [];
    let hoveredRows = [];
    let heldRows = [];
    let focusedRows = [];
    let resumeTimers = [];
    let frame = 0;
    let lastTime = 0;
    const canAnimate = $derived(!reducedMotion && overflowing.some(Boolean));

    function suspend() {
        cancelAnimationFrame(frame);
        frame = 0;
        lastTime = 0;
    }

    function schedule() {
        if (
            running &&
            pageVisible &&
            visibleRows.some(
                (visible, index) =>
                    visible && overflowing[index] && !pausedRows[index],
            ) &&
            !frame
        ) {
            frame = requestAnimationFrame(animate);
        }
    }

    function animate(time) {
        frame = 0;
        if (!running || !pageVisible || !canAnimate) return;
        const distance = lastTime ? Math.min(time - lastTime, 100) * 0.035 : 0;
        lastTime = time;
        for (let index = 0; index < rails.length; index++) {
            if (!overflowing[index] || !visibleRows[index] || pausedRows[index])
                continue;
            const cycle = cycles[index];
            positions[index] =
                (positions[index] +
                    (index === 0 ? distance : -distance) +
                    cycle) %
                cycle;
            rails[index].scrollLeft = positions[index];
        }
        schedule();
    }

    function pauseRow(index) {
        clearTimeout(resumeTimers[index]);
        pausedRows[index] = true;
    }

    function releaseRow(index) {
        clearTimeout(resumeTimers[index]);
        resumeTimers[index] = setTimeout(() => {
            if (hoveredRows[index] || heldRows[index] || focusedRows[index])
                return;
            positions[index] = cycles[index]
                ? rails[index].scrollLeft % cycles[index]
                : 0;
            pausedRows[index] = false;
            lastTime = 0;
            schedule();
        }, 180);
    }

    function wheelRow(event, index) {
        if (!overflowing[index]) return;
        const delta = event.deltaX || event.deltaY;
        if (!delta) return;
        event.preventDefault();
        pauseRow(index);
        const pixels =
            delta *
            (event.deltaMode === 1
                ? 16
                : event.deltaMode === 2
                  ? rails[index].clientWidth
                  : 1);
        const cycle = cycles[index];
        rails[index].scrollLeft =
            (((rails[index].scrollLeft + pixels) % cycle) + cycle) % cycle;
        releaseRow(index);
    }

    function measure() {
        cycles = groups.map((group, index) =>
            group
                ? group.getBoundingClientRect().width +
                  parseFloat(getComputedStyle(rails[index]).columnGap || '0')
                : 0,
        );
        overflowing = groups.map((group, index) =>
            Boolean(
                group &&
                group.getBoundingClientRect().width >
                    rails[index].clientWidth + 1,
            ),
        );
        rails.forEach((rail, index) => {
            if (!initialized[index] && rail.clientWidth > 0) {
                rail.scrollLeft =
                    index === 0
                        ? 0
                        : Math.max(
                              0,
                              groups[index].getBoundingClientRect().width -
                                  rail.clientWidth,
                          );
                positions[index] = rail.scrollLeft;
                initialized[index] = true;
            }
        });
        if (canAnimate) {
            running = true;
            schedule();
        } else if (!canAnimate) {
            running = false;
            suspend();
        }
    }

    onMount(() => {
        const motion = window.matchMedia('(prefers-reduced-motion: reduce)');
        pageVisible = document.visibilityState === 'visible';
        const syncMotion = () => {
            reducedMotion = motion.matches;
            measure();
        };
        const visibility = () => {
            pageVisible = document.visibilityState === 'visible';
            if (pageVisible) schedule();
            else suspend();
        };
        const resize = new ResizeObserver(measure);
        const observer = new IntersectionObserver((entries) => {
            for (const entry of entries)
                visibleRows[rails.indexOf(entry.target)] = entry.isIntersecting;
            if (visibleRows.some(Boolean)) schedule();
            else suspend();
        });
        const wheelHandlers = rails.map(
            (_, index) => (event) => wheelRow(event, index),
        );
        rails.forEach((rail, index) => {
            resize.observe(rail);
            resize.observe(groups[index]);
            observer.observe(rail);
            rail.addEventListener('wheel', wheelHandlers[index], {
                passive: false,
            });
        });
        syncMotion();
        motion.addEventListener('change', syncMotion);
        document.addEventListener('visibilitychange', visibility);
        return () => {
            suspend();
            resumeTimers.forEach(clearTimeout);
            rails.forEach((rail, index) =>
                rail.removeEventListener('wheel', wheelHandlers[index]),
            );
            resize.disconnect();
            observer.disconnect();
            motion.removeEventListener('change', syncMotion);
            document.removeEventListener('visibilitychange', visibility);
        };
    });
</script>

<section id="programs" class="min-w-0 scroll-mt-24">
    <div
        class="border-border/60 flex flex-col gap-5 border-b pb-7 md:flex-row md:items-end md:justify-between"
    >
        <div class="max-w-2xl">
            <p
                class="text-primary text-xs font-bold tracking-[0.22em] uppercase"
            >
                Find your direction
            </p>
            <h2
                class="text-foreground mt-3 text-3xl leading-tight font-semibold tracking-[-0.035em] sm:text-4xl"
            >
                Programs that make room for your questions.
            </h2>
        </div>
        <p class="text-muted-foreground max-w-sm text-sm leading-6">
            Start with an area that feels close to your interests, then ask the
            people who can help you see what comes next.
        </p>
    </div>

    {#if programs.length}
        {#each rows as row, index (index)}
            <div class="mt-6">
                <div
                    bind:this={rails[index]}
                    data-program-row={index}
                    class="focus-visible:ring-ring flex [scrollbar-width:none] gap-3 overflow-x-auto overscroll-x-contain pb-2 focus-visible:ring-2 focus-visible:outline-none [&::-webkit-scrollbar]:hidden"
                    role="region"
                    aria-label="{index === 0
                        ? 'First'
                        : 'Second'} row of programs"
                    tabindex="0"
                    onpointerenter={(event) => {
                        if (event.pointerType === 'mouse') {
                            hoveredRows[index] = true;
                            pauseRow(index);
                        }
                    }}
                    onpointerleave={() => {
                        hoveredRows[index] = false;
                        heldRows[index] = false;
                        releaseRow(index);
                    }}
                    onpointerdown={() => {
                        heldRows[index] = true;
                        pauseRow(index);
                    }}
                    onpointerup={() => {
                        heldRows[index] = false;
                        releaseRow(index);
                    }}
                    onpointercancel={() => {
                        heldRows[index] = false;
                        releaseRow(index);
                    }}
                    onkeydown={() => pauseRow(index)}
                    onfocusin={() => {
                        if (!heldRows[index]) focusedRows[index] = true;
                        pauseRow(index);
                    }}
                    onfocusout={(event) => {
                        if (
                            !event.currentTarget.contains(event.relatedTarget)
                        ) {
                            focusedRows[index] = false;
                            releaseRow(index);
                        }
                    }}
                    onscroll={() => {
                        if (pausedRows[index] && !heldRows[index])
                            releaseRow(index);
                    }}
                >
                    <div
                        bind:this={groups[index]}
                        data-program-cycle
                        class="flex shrink-0 gap-3"
                    >
                        {#each row as program (program.code)}
                            <div class="w-[min(76vw,19rem)] shrink-0">
                                {@render ProgramCard(program)}
                            </div>
                        {/each}
                    </div>
                    {#if overflowing[index]}
                        <div
                            class="flex shrink-0 gap-3"
                            aria-hidden="true"
                            inert
                        >
                            {#each row as program (program.code)}
                                <div class="w-[min(76vw,19rem)] shrink-0">
                                    {@render ProgramCard(program)}
                                </div>
                            {/each}
                        </div>
                    {/if}
                </div>
            </div>
        {/each}
        {#if preview}<p class="text-muted-foreground mt-4 text-xs">
                Program names come from the seeded preview catalog and can be
                replaced by approved catalog content.
            </p>{/if}
    {:else}
        <div
            class="border-border bg-muted/30 mt-8 rounded-[1.25rem] border border-dashed p-8 text-center"
        >
            <p class="text-foreground font-semibold">
                Program information is being prepared.
            </p>
            <p class="text-muted-foreground mt-2 text-sm">
                Contact the campus for the latest approved program list.
            </p>
        </div>
    {/if}
</section>

{#snippet ProgramCard(program)}
    <article
        data-program-card
        class="border-border/70 bg-card group relative isolate flex h-full min-h-[13rem] flex-col justify-between overflow-hidden rounded-[1.25rem] border p-4 sm:p-5"
    >
        {#if program.image?.src && !failedImages[program.image.src]}
            <img
                src={program.image.src}
                alt=""
                aria-hidden="true"
                data-source={program.image.source ?? undefined}
                loading="lazy"
                decoding="async"
                class="absolute inset-0 -z-10 h-full w-full object-cover"
                onerror={() => (failedImages[program.image.src] = true)}
            />
        {/if}
        <div
            class="from-card/95 via-card/90 to-card/85 pointer-events-none absolute inset-0 -z-10 bg-gradient-to-r"
        ></div>
        <div>
            <div class="flex items-start justify-between gap-3">
                <span
                    class="bg-primary/10 text-primary flex size-9 shrink-0 items-center justify-center rounded-full"
                    aria-hidden="true"><GraduationCap class="size-4" /></span
                >
                <span
                    class="text-muted-foreground font-mono text-[0.7rem] font-semibold tracking-[0.12em]"
                    >{program.code}</span
                >
            </div>
            <h3
                class="text-foreground mt-4 text-lg leading-snug font-semibold tracking-[-0.02em]"
            >
                {program.name}
            </h3>
            {#if program.summary}
                <p class="text-muted-foreground mt-2 text-sm leading-5">
                    {program.summary}
                </p>
            {/if}
        </div>
        <Link
            href={`/programs/${encodeURIComponent(program.code)}`}
            class="text-foreground border-border hover:border-primary/50 hover:text-primary focus-visible:ring-ring mt-4 inline-flex min-h-[44px] w-fit items-center gap-2 rounded-lg px-3 py-2 text-sm font-semibold focus-visible:ring-2 focus-visible:outline-none"
            >View program
            <ArrowUpRight class="size-4" /></Link
        >
    </article>
{/snippet}
