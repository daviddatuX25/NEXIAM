<script>
    import {
        ArrowRight,
        CalendarDays,
        KeyRound,
        ShieldCheck,
    } from 'lucide-svelte';
    import { Link } from '@inertiajs/svelte';
    import { reveal } from '@/lib/motion.js';
    import { publicApplyCtaLabel } from '@/lib/public-application-cta';

    let {
        activeSeason = null,
        hasSubmittedApplication = false,
        openingAlertsAvailable = false,
        intakeOpen = null,
    } = $props();
    const publicIntakeOpen = $derived(
        typeof intakeOpen === 'boolean'
            ? intakeOpen
            : activeSeason?.is_open === true,
    );
    const applyLabel = $derived(
        publicApplyCtaLabel(publicIntakeOpen, openingAlertsAvailable),
    );
    const secondaryApplyLabel = $derived(
        publicIntakeOpen
            ? 'Start an application'
            : publicApplyCtaLabel(false, openingAlertsAvailable, true),
    );
</script>

<section
    id="admissions"
    class="scroll-mt-24"
    use:reveal={{ y: 20, duration: 0.68 }}
>
    <div
        class="border-primary/20 overflow-hidden rounded-[1.5rem] border bg-[linear-gradient(135deg,oklch(0.95_0.04_145),oklch(0.89_0.07_150))] p-6 shadow-[0_28px_80px_-44px_oklch(0.32_0.12_150_/_0.6)] sm:p-8 lg:p-10 dark:bg-[linear-gradient(135deg,oklch(0.24_0.06_150),oklch(0.18_0.05_155))]"
    >
        <div class="grid gap-10 lg:grid-cols-[1.05fr_0.95fr] lg:items-end">
            <div>
                <p
                    class="text-primary text-xs font-bold tracking-[0.22em] uppercase"
                >
                    A clear way in
                </p>
                <h2
                    class="text-foreground mt-3 max-w-[13ch] text-3xl leading-[1.02] font-semibold tracking-[-0.045em] sm:text-4xl"
                >
                    Ready when you are.
                </h2>
                <p
                    class="text-muted-foreground mt-5 max-w-[48ch] text-base leading-7"
                >
                    Explore the requirements, submit your application, and keep
                    your access details close as your next steps take shape.
                </p>
                {#if publicIntakeOpen}
                    <p class="text-primary mt-6 text-sm font-medium">
                        Applications for A.Y. {activeSeason.academic_year} are now
                        open.
                    </p>
                {:else}
                    <p class="text-muted-foreground mt-6 text-sm">
                        {openingAlertsAvailable
                            ? 'Get an email when applications next open.'
                            : 'The next admissions window will appear here when applications open.'}
                    </p>
                {/if}
            </div>

            <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-1">
                {#if hasSubmittedApplication}
                    <Link
                        href="/applicant/login"
                        class="group bg-primary text-primary-foreground shadow-primary/20 hover:bg-primary/90 focus-visible:ring-ring flex min-h-14 items-center justify-between gap-4 rounded-full px-5 py-3 font-semibold shadow-lg transition hover:-translate-y-0.5 focus-visible:ring-2 focus-visible:outline-none"
                    >
                        <span class="inline-flex items-center gap-2"
                            ><KeyRound class="size-4" /> Proceed to Portal</span
                        >
                        <ArrowRight
                            class="size-5 transition-transform group-hover:translate-x-1"
                        />
                    </Link>
                    <Link
                        href="/apply"
                        class="group border-border/80 bg-background/65 text-foreground hover:border-primary/50 hover:text-primary focus-visible:ring-ring flex min-h-14 items-center justify-between gap-4 rounded-full border px-5 py-3 font-semibold transition focus-visible:ring-2 focus-visible:outline-none"
                    >
                        <span>{secondaryApplyLabel}</span>
                        <ArrowRight
                            class="size-5 transition-transform group-hover:translate-x-1"
                        />
                    </Link>
                {:else}
                    <Link
                        href="/apply"
                        class="group bg-primary text-primary-foreground shadow-primary/20 hover:bg-primary/90 focus-visible:ring-ring flex min-h-14 items-center justify-between gap-4 rounded-full px-5 py-3 font-semibold shadow-lg transition hover:-translate-y-0.5 focus-visible:ring-2 focus-visible:outline-none"
                    >
                        <span>{applyLabel}</span>
                        <ArrowRight
                            class="size-5 transition-transform group-hover:translate-x-1"
                        />
                    </Link>
                    <Link
                        href="/applicant/login"
                        class="group border-border/80 bg-background/65 text-foreground hover:border-primary/50 hover:text-primary focus-visible:ring-ring flex min-h-14 items-center justify-between gap-4 rounded-full border px-5 py-3 font-semibold transition focus-visible:ring-2 focus-visible:outline-none"
                    >
                        <span class="inline-flex items-center gap-2"
                            ><KeyRound class="size-4" /> Applicant Portal</span
                        >
                        <ArrowRight
                            class="size-5 transition-transform group-hover:translate-x-1"
                        />
                    </Link>
                {/if}
            </div>
        </div>

        <div
            class="border-primary/15 mt-9 grid gap-4 border-t pt-6 sm:grid-cols-3"
        >
            <div class="flex gap-3">
                <ShieldCheck class="text-primary mt-0.5 size-5 shrink-0" />
                <div>
                    <p class="text-foreground text-sm font-semibold">
                        Prepare once
                    </p>
                    <p class="text-muted-foreground mt-1 text-xs leading-5">
                        Review the published requirements before you submit.
                    </p>
                </div>
            </div>
            <div class="flex gap-3">
                <CalendarDays class="text-primary mt-0.5 size-5 shrink-0" />
                <div>
                    <p class="text-foreground text-sm font-semibold">
                        Keep your place
                    </p>
                    <p class="text-muted-foreground mt-1 text-xs leading-5">
                        Use the portal to follow schedules and status.
                    </p>
                </div>
            </div>
            <div class="flex gap-3">
                <KeyRound class="text-primary mt-0.5 size-5 shrink-0" />
                <div>
                    <p class="text-foreground text-sm font-semibold">
                        Follow your progress
                    </p>
                    <p class="text-muted-foreground mt-1 text-xs leading-5">
                        Return to the applicant portal for your next steps.
                    </p>
                </div>
            </div>
        </div>
    </div>
</section>
