<script>
    import { ArrowRight, CalendarDays, KeyRound, ShieldCheck } from 'lucide-svelte';
    import { Link } from '@inertiajs/svelte';
    import { reveal } from '@/lib/motion.js';

    let { activeSeason = null } = $props();
</script>

<section id="admissions" class="scroll-mt-24" use:reveal={{ y: 20, duration: 0.68 }}>
    <div class="overflow-hidden rounded-[1.5rem] border border-primary/20 bg-[linear-gradient(135deg,oklch(0.95_0.04_145),oklch(0.89_0.07_150))] p-6 shadow-[0_28px_80px_-44px_oklch(0.32_0.12_150_/_0.6)] dark:bg-[linear-gradient(135deg,oklch(0.24_0.06_150),oklch(0.18_0.05_155))] sm:p-8 lg:p-10">
        <div class="grid gap-10 lg:grid-cols-[1.05fr_0.95fr] lg:items-end">
            <div>
                <p class="text-primary text-xs font-bold tracking-[0.22em] uppercase">A clear way in</p>
                <h2 class="text-foreground mt-3 max-w-[13ch] text-3xl font-semibold leading-[1.02] tracking-[-0.045em] sm:text-4xl">
                    Ready when you are.
                </h2>
                <p class="text-muted-foreground mt-5 max-w-[48ch] text-base leading-7">
                    Explore the requirements, submit your application, and keep your access details close as your next steps take shape.
                </p>
                {#if activeSeason}
                    <div class="mt-6 inline-flex items-center gap-2 rounded-full border border-primary/20 bg-background/60 px-3.5 py-2 text-sm font-medium text-primary">
                        <CalendarDays class="size-4" />
                        <span>Current window: {activeSeason.name}{#if activeSeason.window} · {activeSeason.window}{/if}</span>
                    </div>
                {:else}
                    <p class="text-muted-foreground mt-6 text-sm">The next admissions window will appear here when it is published.</p>
                {/if}
            </div>

            <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-1">
                <Link
                    href="/apply"
                    class="group flex min-h-14 items-center justify-between gap-4 rounded-full bg-primary px-5 py-3 font-semibold text-primary-foreground shadow-lg shadow-primary/20 transition hover:-translate-y-0.5 hover:bg-primary/90 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring"
                >
                    <span>Start your application</span>
                    <ArrowRight class="size-5 transition-transform group-hover:translate-x-1" />
                </Link>
                <Link
                    href="/applicant/login"
                    class="group flex min-h-14 items-center justify-between gap-4 rounded-full border border-border/80 bg-background/65 px-5 py-3 font-semibold text-foreground transition hover:border-primary/50 hover:text-primary focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring"
                >
                    <span class="inline-flex items-center gap-2"><KeyRound class="size-4" /> Applicant Portal</span>
                    <ArrowRight class="size-5 transition-transform group-hover:translate-x-1" />
                </Link>
            </div>
        </div>

        <div class="mt-9 grid gap-4 border-t border-primary/15 pt-6 sm:grid-cols-3">
            <div class="flex gap-3">
                <ShieldCheck class="mt-0.5 size-5 shrink-0 text-primary" />
                <div>
                    <p class="text-foreground text-sm font-semibold">Prepare once</p>
                    <p class="text-muted-foreground mt-1 text-xs leading-5">Review the published requirements before you submit.</p>
                </div>
            </div>
            <div class="flex gap-3">
                <CalendarDays class="mt-0.5 size-5 shrink-0 text-primary" />
                <div>
                    <p class="text-foreground text-sm font-semibold">Keep your place</p>
                    <p class="text-muted-foreground mt-1 text-xs leading-5">Use the portal to follow schedules and status.</p>
                </div>
            </div>
            <div class="flex gap-3">
                <KeyRound class="mt-0.5 size-5 shrink-0 text-primary" />
                <div>
                    <p class="text-foreground text-sm font-semibold">Bring your key</p>
                    <p class="text-muted-foreground mt-1 text-xs leading-5">Your access key and birthdate unlock the applicant view.</p>
                </div>
            </div>
        </div>
    </div>
</section>
