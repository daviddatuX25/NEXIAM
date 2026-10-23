<script>
    import { ArrowRight } from 'lucide-svelte';

    /**
     * Public landing process strip — port of legacy
     * `resources/js/Components/blocks/ProcessFlow.svelte`.
     * Five glass phase cards on desktop, vertical timeline on mobile.
     * Office/color data is the legacy observed baseline (Registrar →
     * Guidance workflow); phases are presentation data only.
     */
    let {
        phases = [
            {
                number: 1,
                name: 'Application',
                description:
                    'Apply online, then submit your physical requirements at the Registrar to secure a schedule.',
                office: 'Registrar',
                color: '#C8401A',
            },
            {
                number: 2,
                name: 'Slot Confirmation',
                description:
                    'Receive your verified exam schedule, complete with room assignment and testing guidelines.',
                office: 'Registrar',
                color: '#1A5FC8',
            },
            {
                number: 3,
                name: 'Examination Day',
                description:
                    'Take your secure admission test in our designated testing centers with trained proctors.',
                office: 'Guidance',
                color: '#1A9C55',
            },
            {
                number: 4,
                name: 'Evaluation',
                description:
                    'Scores are computed accurately with strict oversight from test administrators to ensure exam security.',
                office: 'Guidance',
                color: '#8B2AC8',
            },
            {
                number: 5,
                name: 'Release of Results',
                description:
                    'Wait for the scheduled Release Day to receive the applicable result-release service and academic guidance.',
                office: 'Guidance',
                color: '#C87A1A',
            },
        ],
    } = $props();
</script>

<section class="relative z-10 w-full px-4 py-4 md:py-8">
    <div class="mx-auto max-w-[85rem]">
        <!-- Desktop: glass cards timeline -->
        <div class="hidden items-stretch justify-between lg:flex">
            {#each phases as phase, index (phase.number)}
                <div
                    class="group animate-in zoom-in-95 relative flex flex-1 items-center duration-500"
                    style="animation-delay: {index *
                        100}ms; animation-fill-mode: both;"
                >
                    <div
                        class="border-border/40 bg-card/40 group-hover:border-foreground/20 relative flex h-full w-full flex-col overflow-hidden rounded-3xl border p-6 backdrop-blur-xl transition-all duration-500 group-hover:-translate-y-2 group-hover:shadow-[0_8px_30px_rgb(0,0,0,0.12)]"
                    >
                        <div
                            class="pointer-events-none absolute -top-12 -right-12 h-40 w-40 rounded-full opacity-[0.08] blur-3xl transition-opacity duration-500 group-hover:opacity-30"
                            style="background-color: {phase.color};"
                            aria-hidden="true"
                        ></div>

                        <div class="relative z-10 mb-4 flex items-start gap-4">
                            <div
                                class="flex h-12 w-12 shrink-0 items-center justify-center rounded-2xl text-xl font-extrabold text-white shadow-lg ring-1 ring-white/20 transition-transform duration-500 group-hover:scale-110"
                                style="background-color: {phase.color}; background-image: linear-gradient(to bottom right, rgba(255,255,255,0.2), transparent);"
                            >
                                {phase.number}
                            </div>
                            <h3
                                class="text-foreground group-hover:text-primary pt-1.5 text-base leading-tight font-bold transition-colors duration-300"
                            >
                                {phase.name}
                            </h3>
                        </div>

                        <p
                            class="text-muted-foreground group-hover:text-foreground/80 relative z-10 flex-grow text-[13px] leading-relaxed transition-colors"
                        >
                            {phase.description}
                        </p>

                        <div class="relative z-10 mt-5">
                            <span
                                class="inline-flex items-center rounded-full border border-transparent px-3 py-1.5 text-[10px] font-black tracking-widest uppercase transition-all duration-300 group-hover:border-current/20"
                                style="background-color: {phase.color}15; color: {phase.color};"
                            >
                                {phase.office}
                            </span>
                        </div>
                    </div>
                </div>

                {#if index < phases.length - 1}
                    <div
                        class="animate-in fade-in text-border/60 flex w-10 flex-shrink-0 items-center justify-center duration-700"
                        style="animation-delay: {index * 100 + 50}ms;"
                        aria-hidden="true"
                    >
                        <ArrowRight class="h-5 w-5 stroke-[2.5]" />
                    </div>
                {/if}
            {/each}
        </div>

        <!-- Mobile/tablet: vertical glass timeline -->
        <div class="relative space-y-3 pt-2 lg:hidden">
            <div
                class="bg-border/40 absolute top-4 bottom-4 left-[17px] z-0 w-0.5 rounded-full"
                aria-hidden="true"
            ></div>

            {#each phases as phase, index (phase.number)}
                <div
                    class="animate-in fade-in slide-in-from-right-4 relative z-10 flex gap-3.5 duration-500"
                    style="animation-delay: {index *
                        100}ms; animation-fill-mode: both;"
                >
                    <div class="mt-1.5 shrink-0">
                        <div
                            class="ring-background flex h-9 w-9 items-center justify-center rounded-xl text-sm font-black text-white shadow-sm ring-[3px] transition-transform duration-300"
                            style="background-color: {phase.color}; background-image: linear-gradient(to bottom right, rgba(255,255,255,0.2), transparent);"
                        >
                            {phase.number}
                        </div>
                    </div>

                    <div
                        class="border-border/40 bg-card/40 relative flex-1 overflow-hidden rounded-2xl border p-3.5 shadow-sm backdrop-blur-xl"
                    >
                        <div
                            class="pointer-events-none absolute -top-10 -right-10 h-24 w-24 rounded-full opacity-[0.1] blur-2xl"
                            style="background-color: {phase.color};"
                            aria-hidden="true"
                        ></div>

                        <div
                            class="relative z-10 mb-1 flex items-center justify-between gap-2"
                        >
                            <h3
                                class="text-foreground text-[15px] leading-tight font-bold"
                            >
                                {phase.name}
                            </h3>
                            <span
                                class="inline-flex shrink-0 items-center rounded-full px-2 py-0.5 text-[9px] font-bold tracking-widest uppercase"
                                style="background-color: {phase.color}15; color: {phase.color};"
                            >
                                {phase.office}
                            </span>
                        </div>

                        <p
                            class="text-muted-foreground relative z-10 text-xs leading-snug"
                        >
                            {phase.description}
                        </p>
                    </div>
                </div>
            {/each}
        </div>
    </div>
</section>
