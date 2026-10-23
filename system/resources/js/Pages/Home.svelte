<script>
    import { Link, usePage } from '@inertiajs/svelte';
    import { onMount } from 'svelte';
    import { isApplicantSubmitted } from '@/lib/applicant-status.js';
    import {
        ArrowDownRight,
        ArrowRight,
        HelpCircle,
        MapPin,
    } from 'lucide-svelte';
    import AdmissionsGateway from '@/Components/public/AdmissionsGateway.svelte';
    import PublicPolicyLinks from '@/Components/public/PublicPolicyLinks.svelte';
    import MediaFrame from '@/Components/public/MediaFrame.svelte';
    import ProgramExplorer from '@/Components/public/ProgramExplorer.svelte';
    import StorySection from '@/Components/public/StorySection.svelte';
    import HomeLayout from '@/Layouts/HomeLayout.svelte';
    import {
        isPublicIntakeOpen,
        publicApplyCtaLabel,
    } from '@/lib/public-application-cta';
    import { reveal } from '@/lib/motion.js';
 
    let {
        institution = {
            name: 'Ilocos Sur Polytechnic State College',
            short_name: 'ISPSC',
            campus: 'Tagudin Campus',
            location: 'Tagudin, Ilocos Sur',
            kicker: 'A campus to study, belong, and begin',
            welcome:
                'Start with a question. Find a direction. Make this campus part of your story.',
            mission:
                'At Tagudin Campus, your first step can be practical: explore a program, ask a question, and meet the people who can help you decide.',
        },
        hero = {},
        activeSeason = null,
        programs = [],
        stories = [],
        alerts = [],
        publicPolicies = [],
        preview = {},
        homeHref = '/',
        navigation = null,
    } = $props();
 
    const previewEnabled = $derived(Boolean(preview?.enabled));
    const page = usePage();
    let clientSubmitted = $state(false);
    const hasSubmittedApplication = $derived(
        Boolean(page.props.has_submitted_application || clientSubmitted),
    );

    onMount(() => {
        clientSubmitted = isApplicantSubmitted(page.props);
    });

    const publicIntakeOpen = $derived(
        isPublicIntakeOpen(activeSeason, page.props.public_intake_open),
    );
    const openingAlertsAvailable = $derived(
        page.props.opening_alerts_available === true,
    );
    const publicApplyLabel = $derived(
        publicApplyCtaLabel(publicIntakeOpen, openingAlertsAvailable),
    );
    const secondaryApplyLabel = $derived(
        publicIntakeOpen
            ? 'Start an application'
            : publicApplyCtaLabel(false, openingAlertsAvailable, true),
    );
 </script>

<svelte:head>
    <title>{institution.name} — Welcome</title>
    <meta
        name="description"
        content={`${institution.name}, ${institution.campus}. Explore programs, campus life, and a clear path into admissions.`}
    />
</svelte:head>

<HomeLayout {institution} {alerts} {homeHref} {navigation} {activeSeason}>
    <div class="overflow-hidden">
        <section class="border-border/60 relative isolate border-b">
            <div
                class="pointer-events-none absolute inset-0 -z-10 bg-[radial-gradient(circle_at_78%_18%,oklch(0.82_0.12_145_/_0.28),transparent_30%),radial-gradient(circle_at_8%_88%,oklch(0.9_0.08_85_/_0.36),transparent_32%)] dark:bg-[radial-gradient(circle_at_78%_18%,oklch(0.38_0.1_145_/_0.25),transparent_30%),radial-gradient(circle_at_8%_88%,oklch(0.35_0.07_85_/_0.2),transparent_32%)]"
            ></div>
            <div
                class="mx-auto grid min-h-[calc(100svh-4rem)] max-w-7xl items-center gap-12 px-4 py-14 sm:px-6 sm:py-20 lg:grid-cols-[0.9fr_1.1fr] lg:gap-20 lg:px-8 lg:py-24"
            >
                <div
                    class="max-w-2xl min-w-0"
                    use:reveal={{ y: 18, duration: 0.7 }}
                >

                    <p
                        class="text-primary max-w-full text-xs leading-5 font-bold tracking-[0.22em] break-words uppercase"
                    >
                        {hero.eyebrow ??
                            `${institution.name} · ${institution.campus}`}
                    </p>
                    <h1
                        class="text-foreground mt-6 max-w-[11ch] text-5xl leading-[0.96] font-semibold tracking-[-0.06em] text-balance sm:text-6xl lg:text-7xl"
                    >
                        {hero.title ?? 'Find your next beginning.'}
                    </h1>
                    <p
                        class="text-muted-foreground mt-7 max-w-full text-lg leading-8 sm:max-w-[48ch] sm:text-xl"
                    >
                        {hero.lede ?? institution.welcome}
                    </p>

                    {#if publicIntakeOpen}
                        <p class="text-primary mt-7 text-sm font-medium">
                            Applications {activeSeason?.academic_year ? `for A.Y. ${activeSeason.academic_year} ` : ''}are now open.
                        </p>
                    {:else if activeSeason}
                        <p class="text-muted-foreground mt-7 text-sm">
                            {activeSeason?.academic_year ? `A.Y. ${activeSeason.academic_year}` : 'Applications are currently closed.'}
                        </p>
                    {/if}

                    <div
                        class="mt-9 flex flex-col gap-3 sm:flex-row sm:flex-wrap sm:items-center"
                    >
                        {#if hasSubmittedApplication}
                            <Link
                                href="/applicant/login"
                                class="group bg-primary text-primary-foreground shadow-primary/20 hover:bg-primary/90 focus-visible:ring-ring inline-flex min-h-14 items-center justify-center gap-4 rounded-full px-6 py-3 text-sm font-bold tracking-[0.08em] uppercase shadow-lg transition focus-visible:ring-2 focus-visible:outline-none"
                            >
                                Proceed to Portal
                                <ArrowRight class="size-5" />
                            </Link>
                            <a
                                href="#programs"
                                class="text-foreground hover:text-primary focus-visible:ring-ring inline-flex min-h-14 items-center justify-center gap-2 px-4 py-3 text-sm font-semibold transition focus-visible:ring-2 focus-visible:outline-none"
                            >
                                Explore programs
                                <ArrowDownRight class="size-4" />
                            </a>
                        {:else}
                            <Link
                                href="/apply"
                                class="group bg-primary text-primary-foreground shadow-primary/20 hover:bg-primary/90 focus-visible:ring-ring inline-flex min-h-14 items-center justify-center gap-4 rounded-full px-6 py-3 text-sm font-bold tracking-[0.08em] uppercase shadow-lg transition focus-visible:ring-2 focus-visible:outline-none"
                            >
                                {publicApplyLabel}
                                <ArrowRight class="size-5" />
                            </Link>
                            <a
                                href="#programs"
                                class="text-foreground hover:text-primary focus-visible:ring-ring inline-flex min-h-14 items-center justify-center gap-2 px-4 py-3 text-sm font-semibold transition focus-visible:ring-2 focus-visible:outline-none"
                            >
                                Explore programs
                                <ArrowDownRight class="size-4" />
                            </a>
                        {/if}
                    </div>

                    {#if hasSubmittedApplication}
                        <div
                            class="text-muted-foreground mt-6 flex flex-wrap items-center gap-x-2 text-sm"
                        >
                            <span>Need to submit another application?</span>
                            <Link
                                href="/apply"
                                class="hover:text-primary text-foreground font-semibold underline underline-offset-4 decoration-primary/40 transition inline-flex items-center gap-1"
                            >
                                <span>{secondaryApplyLabel}</span>
                            </Link>
                        </div>
                    {/if}
                </div>

                <div
                    class="relative min-w-0"
                    use:reveal={{ y: 28, duration: 0.82, delay: 0.08 }}
                >
                    <div
                        class="border-primary/25 bg-background/85 text-primary pointer-events-none absolute -top-5 right-8 z-10 hidden rounded-full border px-4 py-2 text-xs font-semibold shadow-sm backdrop-blur sm:block"
                    >
                        {hero.badge_text ?? institution.kicker}
                    </div>
                    <MediaFrame
                        image={hero.image}
                        ratio="aspect-[4/5] sm:aspect-[5/4]"
                        priority
                        showSource={previewEnabled}
                        class="shadow-[0_34px_100px_-45px_oklch(0.22_0.04_155_/_0.65)]"
                    />
                    <div
                        class="bg-background/90 border-border/70 absolute -bottom-5 left-4 z-10 max-w-[15rem] rounded-[1rem] border p-4 shadow-xl backdrop-blur sm:left-8"
                    >
                        <div class="flex items-start gap-3">
                            <MapPin
                                class="text-primary mt-0.5 size-4 shrink-0"
                            />
                            <div>
                                <p
                                    class="text-foreground text-sm font-semibold"
                                >
                                    {institution.campus}
                                </p>
                                <p
                                    class="text-muted-foreground mt-1 text-xs leading-5"
                                >
                                    {institution.location}
                                </p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <a
                href="#mission"
                class="text-muted-foreground hover:text-primary focus-visible:ring-ring mx-auto flex min-h-11 w-fit items-center gap-2 px-4 pb-6 text-xs font-semibold tracking-[0.18em] uppercase transition focus-visible:ring-2 focus-visible:outline-none"
            >
                Discover the institution
                <ArrowDownRight class="size-4" />
            </a>
        </section>

        <section
            id="mission"
            class="border-border/60 scroll-mt-24 border-b"
            use:reveal={{ y: 18, duration: 0.68 }}
        >
            <div
                class="mx-auto grid max-w-7xl gap-10 px-4 py-20 sm:px-6 sm:py-28 lg:grid-cols-[0.75fr_1.25fr] lg:gap-24 lg:px-8"
            >
                <div>
                    <p
                        class="text-primary text-xs font-bold tracking-[0.22em] uppercase"
                    >
                        Why this place matters
                    </p>
                    <h2
                        class="text-foreground mt-4 max-w-[12ch] text-4xl leading-[1.02] font-semibold tracking-[-0.05em] sm:text-5xl"
                    >
                        Learn close to what matters.
                    </h2>
                </div>
                <div class="max-w-2xl">
                    <p
                        class="text-foreground text-xl leading-8 tracking-[-0.015em] sm:text-2xl sm:leading-9"
                    >
                        {institution.mission}
                    </p>
                    <div
                        class="border-border/60 mt-9 grid gap-4 border-t pt-7 sm:grid-cols-3"
                    >
                        <div>
                            <p
                                class="text-primary text-xs font-bold tracking-[0.18em] uppercase"
                            >
                                People
                            </p>
                            <p
                                class="text-muted-foreground mt-2 text-sm leading-6"
                            >
                                Find guides, classmates, and conversations that
                                open a next step.
                            </p>
                        </div>
                        <div>
                            <p
                                class="text-primary text-xs font-bold tracking-[0.18em] uppercase"
                            >
                                Place
                            </p>
                            <p
                                class="text-muted-foreground mt-2 text-sm leading-6"
                            >
                                See how a campus can make learning feel grounded
                                and close to home.
                            </p>
                        </div>
                        <div>
                            <p
                                class="text-primary text-xs font-bold tracking-[0.18em] uppercase"
                            >
                                Practice
                            </p>
                            <p
                                class="text-muted-foreground mt-2 text-sm leading-6"
                            >
                                Choose a direction, test your questions, and
                                keep moving.
                            </p>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <div
            class="mx-auto grid max-w-7xl gap-24 px-4 py-20 sm:px-6 sm:py-28 lg:gap-32 lg:px-8"
        >
            <ProgramExplorer {programs} preview={previewEnabled} />

            <section id="campus-life" class="scroll-mt-24 space-y-16">
                <div class="max-w-2xl" use:reveal={{ y: 18, duration: 0.65 }}>
                    <p
                        class="text-primary text-xs font-bold tracking-[0.22em] uppercase"
                    >
                        Campus life
                    </p>
                    <h2
                        class="text-foreground mt-3 text-3xl leading-tight font-semibold tracking-[-0.035em] sm:text-4xl"
                    >
                        The experience is more than a timetable.
                    </h2>
                </div>
                {#if stories.length > 0}
                    {#each stories as story (story.id ?? story.title)}
                        <StorySection {story} preview={previewEnabled} />
                    {/each}
                {:else}
                    <div
                        class="border-border bg-muted/20 rounded-[1.25rem] border border-dashed p-8"
                    >
                        <p class="text-foreground font-semibold">
                            Campus stories are being prepared.
                        </p>
                        <p class="text-muted-foreground mt-2 text-sm">
                            Approved student and campus experiences will appear
                            here.
                        </p>
                    </div>
                {/if}
            </section>

            <AdmissionsGateway {activeSeason} {hasSubmittedApplication} {openingAlertsAvailable} intakeOpen={publicIntakeOpen} />

            <section id="faqs" class="scroll-mt-24" use:reveal={{ y: 18, duration: 0.65 }}>
                <div class="border-border/70 bg-card/60 relative overflow-hidden rounded-[1.75rem] border p-8 shadow-xs sm:p-10">
                    <div class="flex flex-col gap-6 md:flex-row md:items-center md:justify-between">
                        <div class="max-w-2xl space-y-3">
                            <div class="inline-flex items-center gap-2 text-xs font-bold tracking-[0.22em] text-primary uppercase">
                                <HelpCircle class="size-4" />
                                <span>Guidance & Questions</span>
                            </div>
                            <h2 class="text-2xl font-semibold tracking-[-0.03em] text-foreground sm:text-3xl">
                                Answers for admissions, exams, and campus life.
                            </h2>
                            <p class="text-sm leading-6 text-muted-foreground sm:text-base">
                                Explore official guidelines, entrance examination instructions, and policies verified by institutional offices.
                            </p>
                        </div>
                        <div class="flex shrink-0 flex-wrap items-center gap-3">
                            <button
                                type="button"
                                data-home-open-faq-modal
                                onclick={() =>
                                    window.dispatchEvent(
                                        new CustomEvent('nexiam:open-faq-inquiry-modal', {
                                            detail: { view: 'faqs' },
                                        }),
                                    )}
                                class="border-border bg-background text-foreground hover:bg-muted focus-visible:ring-ring inline-flex min-h-[44px] items-center gap-2 rounded-xl border px-5 py-2.5 text-sm font-semibold shadow-2xs transition focus-visible:ring-2 focus-visible:outline-none"
                            >
                                <HelpCircle class="text-primary size-4" />
                                <span>Ask an Office</span>
                            </button>
                            <Link
                                href="/faqs"
                                class="inline-flex min-h-[44px] items-center gap-2 rounded-xl bg-primary px-5 py-2.5 text-sm font-semibold text-primary-foreground shadow-xs transition hover:bg-primary/90 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring"
                            >
                                <span>Browse FAQs</span>
                                <ArrowRight class="size-4" />
                            </Link>
                        </div>
                    </div>
                </div>
            </section>

            <PublicPolicyLinks {publicPolicies} />
        </div>
    </div>
</HomeLayout>
