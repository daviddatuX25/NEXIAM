<script>
    import { Link, router, usePage } from '@inertiajs/svelte';
    import { Menu, X } from 'lucide-svelte';
    import * as Dialog from '@/Components/ui/dialog';
    import { Button } from '@/Components/ui/button';
    import ToastManager from '@/Components/kit/ToastManager.svelte';
    import PreferencesMenu from '@/Components/kit/PreferencesMenu.svelte';
    import AnnouncementTicker from '@/Components/public/AnnouncementTicker.svelte';
    import PublicMovingLauncher from '@/Components/public/PublicMovingLauncher.svelte';
    import InstitutionalBackdrop from '@/Components/kit/InstitutionalBackdrop.svelte';
    import BrandTitle from '@/Components/kit/BrandTitle.svelte';
    import NexiamLogo from '@/Components/kit/NexiamLogo.svelte';
    import { onMount } from 'svelte';
    import { getScale, setScale } from '@/lib/app-scale.svelte';
    import { sound } from '@/lib/sound.js';
    import { isApplicantSubmitted } from '@/lib/applicant-status.js';
    import {
        isPublicIntakeOpen,
        publicApplyCtaLabel,
    } from '@/lib/public-application-cta';

    let {
        children,
        alerts = [],
        homeHref = '/',
        navigation = null,
        institution: propInstitution = null,
        activeSeason: propActiveSeason = null,
    } = $props();

    const page = usePage();
    const institution = $derived({
        ...(page.props.institution ?? {}),
        ...(propInstitution ?? {}),
        logo_url: propInstitution?.logo_url || page.props.institution?.logo_url || null,
    });
    const publicIntakeOpen = $derived(
        isPublicIntakeOpen(propActiveSeason, page.props.public_intake_open),
    );
    const openingAlertsAvailable = $derived(
        page.props.opening_alerts_available === true,
    );
    const applyLabel = $derived(
        publicApplyCtaLabel(publicIntakeOpen, openingAlertsAvailable, true),
    );

    const user = $derived(page.props.auth?.user ?? null);
    const isStaff = $derived(Boolean(user && !user.is_applicant));
    let mobileNavOpen = $state(false);
    let header = $state(null);
    let headerHeight = $state(56);
    let logoLoadError = $state(false);
    let clientSubmitted = $state(false);

    const hasSubmittedApplication = $derived(
        Boolean(page.props.has_submitted_application || clientSubmitted)
    );

    onMount(() => {
        clientSubmitted = isApplicantSubmitted(page.props);
        const prevScale = getScale();
        setScale(100, false);
        const prevSoundEnabled = sound.enabled;
        sound.enabled = true;
        let headerObserver = null;
        if (header) {
            headerObserver = new ResizeObserver(() => {
                if (header) {
                    headerHeight = header.getBoundingClientRect().height;
                }
            });
            headerObserver.observe(header);
        }

        return () => {
            setScale(prevScale, false);
            sound.enabled = prevSoundEnabled;
            headerObserver?.disconnect();
        };
    });

    const portalHref = $derived(
        user?.is_applicant ? '/applicant/portal' : '/applicant/login',
    );

    const defaultNavigation = [
        { href: '/#mission', label: 'About' },
        { href: '/#programs', label: 'Programs' },
        { href: '/#admissions', label: 'Admissions' },
        { href: '/#campus-life', label: 'Campus life' },
        { href: '/#policies', label: 'Public policies' },
        { href: '/faqs', label: 'FAQs' },
    ];
    const navItems = $derived(
        navigation
            ? [
                  ...navigation.filter(
                      (item) =>
                          item.href !== '/#policies' &&
                          item.href !== '/faqs' &&
                          !item.href.includes('announcement'),
                  ),
                  { href: '/#policies', label: 'Public policies' },
                  { href: '/faqs', label: 'FAQs' },
              ]
            : defaultNavigation,
    );

    function closeMenu() {
        mobileNavOpen = false;
    }
    function handleLogoClick() {
        closeMenu();
        if (typeof window !== 'undefined' && (window.location.pathname === '/' || window.location.pathname === '')) {
            if (window.location.hash) {
                router.replace({ url: '/', preserveState: true, preserveScroll: true });
            }
            const prefersReduced = window.matchMedia?.('(prefers-reduced-motion: reduce)')?.matches ?? false;
            window.scrollTo({ top: 0, behavior: prefersReduced ? 'instant' : 'smooth' });
        }
    }

    function handleLogoBefore() {
        if (typeof window !== 'undefined' && (window.location.pathname === '/' || window.location.pathname === '') && !window.location.hash) {
            handleLogoClick();
            return false;
        }
    }

    function isActive(href) {
        const [path, hash] = href.split('#');
        const current = page.url ?? '/';
        const currentPath = current.split('?')[0];
        if (path && path !== currentPath) return false;
        if (hash) return current.includes(`#${hash}`);
        return path ? currentPath === path : currentPath === '/';
    }
</script>

<div
    class="public-home-shell flex min-h-dvh flex-col font-sans relative"
    style:--public-header-height={`${headerHeight}px`}
>
    <InstitutionalBackdrop fixed={true} opacity="opacity-[0.13] sm:opacity-[0.16] dark:opacity-[0.28]" />
    <ToastManager />

    <header
        bind:this={header}
        class="sticky top-0 z-50 border-b border-border/70 bg-background/95 supports-[backdrop-filter]:bg-background/85 supports-[backdrop-filter]:backdrop-blur-xl pt-[env(safe-area-inset-top)]"
    >
        <div
            class="mx-auto flex min-h-16 max-w-7xl items-center justify-between gap-2 px-4 sm:gap-4 sm:px-6 lg:px-8"
        >
            <Link
                href="/"
                class="group flex min-w-0 flex-1 items-center gap-2.5 sm:gap-3 xl:flex-none"
                aria-label={`${institution.name} home`}
                onbefore={handleLogoBefore}
                onclick={handleLogoClick}
            >
                {#if institution.logo_url && !logoLoadError}
                    <img
                        src={institution.logo_url}
                        alt="Logo"
                        class="size-9 shrink-0 rounded-xl object-contain shadow-sm transition group-hover:-rotate-3"
                        onerror={() => (logoLoadError = true)}
                    />
                {:else}
                    <span
                        class="bg-primary text-primary-foreground flex size-9 shrink-0 items-center justify-center rounded-xl text-base font-bold shadow-sm transition group-hover:-rotate-3"
                        aria-hidden="true"
                    >
                        {institution.short_name?.slice(0, 1) ?? 'I'}
                    </span>
                {/if}
                <BrandTitle {institution} />
            </Link>

            <nav
                class="hidden items-center gap-4 text-sm font-medium xl:flex"
                aria-label="Institutional navigation"
            >
                {#each navItems as item (item.href)}
                    <a
                        href={item.href}
                        class="text-muted-foreground hover:text-foreground min-h-11 px-1 py-3 whitespace-nowrap transition-colors {isActive(
                            item.href,
                        )
                            ? 'text-primary'
                            : ''}"
                    >
                        {item.label}
                    </a>
                {/each}
            </nav>

            <div class="hidden shrink-0 items-center gap-3 xl:flex">
                {#if isStaff}
                    <Link
                        href="/staff"
                        class="text-muted-foreground hover:text-primary focus-visible:ring-ring inline-flex min-h-[44px] items-center text-sm font-medium whitespace-nowrap focus-visible:ring-2 focus-visible:ring-ring"
                    >
                        Staff Workspace
                    </Link>
                    <Link
                        href="/apply"
                        class="bg-primary text-primary-foreground hover:bg-primary/90 focus-visible:ring-ring inline-flex min-h-[44px] items-center rounded-lg px-4 py-2 text-sm font-semibold focus-visible:ring-2 focus-visible:ring-ring"
                    >
                        {applyLabel}
                    </Link>
                {:else if hasSubmittedApplication}
                    <Link
                        href="/apply"
                        class="text-muted-foreground hover:text-primary focus-visible:ring-ring inline-flex min-h-[44px] items-center text-sm font-medium whitespace-nowrap focus-visible:ring-2 focus-visible:ring-ring"
                    >
                        {applyLabel}
                    </Link>
                    <Link
                        href={portalHref}
                        class="bg-primary text-primary-foreground hover:bg-primary/90 focus-visible:ring-ring inline-flex min-h-[44px] items-center rounded-lg px-4 py-2 text-sm font-semibold focus-visible:ring-2 focus-visible:ring-ring"
                    >
                        Proceed to Portal
                    </Link>
                {:else}
                    <Link
                        href={portalHref}
                        class="text-muted-foreground hover:text-primary focus-visible:ring-ring inline-flex min-h-[44px] items-center text-sm font-medium whitespace-nowrap focus-visible:ring-2 focus-visible:ring-ring"
                    >
                        Applicant Portal
                    </Link>
                    <Link
                        href="/apply"
                        class="bg-primary text-primary-foreground hover:bg-primary/90 focus-visible:ring-ring inline-flex min-h-[44px] items-center rounded-lg px-4 py-2 text-sm font-semibold focus-visible:ring-2 focus-visible:ring-ring"
                    >
                        {applyLabel}
                    </Link>
                {/if}
                <PreferencesMenu variant="public" />
            </div>
            <div class="flex items-center gap-2 xl:hidden shrink-0">
                <button
                    type="button"
                    class="text-foreground border-border/80 bg-background/90 hover:bg-accent/80 hover:border-primary/40 active:bg-accent motion-safe:active:scale-[0.97] shadow-2xs backdrop-blur-md focus-visible:ring-ring inline-flex min-h-[44px] min-w-[88px] shrink-0 items-center justify-center gap-2 rounded-full border px-3.5 py-2 text-sm font-semibold transition-all focus-visible:ring-2 focus-visible:outline-none"
                    aria-label={mobileNavOpen
                        ? 'Close navigation menu'
                        : 'Open navigation menu'}
                    aria-expanded={mobileNavOpen}
                    aria-controls="public-mobile-navigation"
                    onclick={() => (mobileNavOpen = !mobileNavOpen)}
                >
                    {#if mobileNavOpen}
                        <X class="size-5" />
                        <span>Close</span>
                    {:else}
                        <Menu class="size-5" />
                        <span>Menu</span>
                    {/if}
                </button>
            </div>
        </div>

        {#if mobileNavOpen}
            <div
                id="public-mobile-navigation"
                class="border-border/70 border-t xl:hidden"
            >
                <nav
                    class="mx-auto grid max-w-7xl gap-1 px-4 py-4 sm:px-6"
                    aria-label="Mobile institutional navigation"
                >
                    {#each navItems as item (item.href)}
                        <a
                            href={item.href}
                            class="text-foreground hover:bg-muted flex min-h-[44px] items-center rounded-lg px-3 py-2 text-sm font-semibold transition {isActive(
                                item.href,
                            )
                                ? 'bg-primary/10 text-primary'
                                : ''}"
                            onclick={closeMenu}
                        >
                            {item.label}
                        </a>
                    {/each}
                    <div class="border-border/60 mt-2 grid gap-2 border-t pt-3">
                        {#if hasSubmittedApplication && !isStaff}
                            <Link
                                href={portalHref}
                                class="bg-primary text-primary-foreground inline-flex min-h-[44px] items-center justify-center rounded-lg px-4 py-2 text-sm font-semibold"
                                onclick={closeMenu}
                            >
                                Proceed to Portal
                            </Link>
                            <Link
                                href="/apply"
                                class="text-muted-foreground hover:text-primary inline-flex min-h-[44px] items-center px-3 py-2 text-sm font-medium"
                                onclick={closeMenu}
                            >
                                {applyLabel}
                            </Link>
                        {:else}
                            <Link
                                href="/apply"
                                class="bg-primary text-primary-foreground inline-flex min-h-[44px] items-center justify-center rounded-lg px-4 py-2 text-sm font-semibold"
                                onclick={closeMenu}
                            >
                                {applyLabel}
                            </Link>
                            <Link
                                href={isStaff ? '/staff' : portalHref}
                                class="text-muted-foreground hover:text-primary inline-flex min-h-[44px] items-center px-3 py-2 text-sm font-medium"
                                onclick={closeMenu}
                            >
                                {isStaff ? 'Staff Workspace' : 'Applicant Portal'}
                            </Link>
                        {/if}
                    </div>
                    <div class="border-border/60 mt-3 flex items-center justify-between border-t pt-3 px-1">
                        <span class="text-xs font-medium text-muted-foreground">Accessibility & Display</span>
                        <PreferencesMenu variant="public" />
                    </div>
                </nav>
            </div>
        {/if}
        {#if alerts.length > 0}
            <AnnouncementTicker {alerts} />
        {/if}
    </header>

    <main class="flex-grow relative z-10">
        {@render children()}
    </main>

    <footer id="contact" class="border-border/70 bg-card/60 backdrop-blur-md mt-auto border-t relative z-10">
        <div
            class="mx-auto grid max-w-7xl gap-10 px-4 py-12 sm:px-6 md:grid-cols-[1.15fr_0.85fr_0.85fr] lg:px-8"
        >
            <div>
                <p
                    class="text-primary text-xs font-bold tracking-[0.22em] uppercase"
                >
                    Visit / contact
                </p>
                <h2
                    class="text-foreground mt-3 max-w-[16ch] text-2xl leading-tight font-semibold tracking-[-0.03em]"
                >
                    Begin with a conversation.
                </h2>
                <p
                    class="text-muted-foreground mt-4 max-w-[38ch] text-sm leading-6"
                >
                    {institution.visit_note ??
                        'Contact the campus for approved visit and inquiry details.'}
                </p>
            </div>
            <div>
                <p class="text-foreground text-sm font-semibold">Explore</p>
                <div class="mt-4 grid gap-2 text-sm">
                    <a
                        href="/#mission"
                        class="text-muted-foreground hover:text-primary py-1 transition"
                        >About the institution</a
                    >
                    <a
                        href="/#programs"
                        class="text-muted-foreground hover:text-primary py-1 transition"
                        >Programs</a
                    >
                    <a
                        href="/#admissions"
                        class="text-muted-foreground hover:text-primary py-1 transition"
                        >Admissions</a
                    >
                    <a
                        href="/#campus-life"
                        class="text-muted-foreground hover:text-primary py-1 transition"
                        >Campus life</a
                    >
                    <Link
                        href="/announcements"
                        class="text-muted-foreground hover:text-primary py-1 transition"
                        >Announcements</Link
                    >
                    <Link
                        href="/policies"
                        class="text-muted-foreground hover:text-primary py-1 transition"
                        >Public policies</Link
                    >
                    <Link
                        href="/faqs"
                        class="text-muted-foreground hover:text-primary py-1 transition"
                        >Frequently asked questions</Link
                    >
                </div>
            </div>
            <div>
                <p class="text-foreground text-sm font-semibold">
                    Applicant services
                </p>
                <div class="mt-4 grid gap-2 text-sm">
                    <Link
                        href="/apply"
                        class="text-muted-foreground hover:text-primary py-1 transition"
                        >{publicApplyCtaLabel(publicIntakeOpen, openingAlertsAvailable)}</Link
                    >
                    <Link
                        href={portalHref}
                        class="text-muted-foreground hover:text-primary py-1 transition"
                        >{hasSubmittedApplication && !isStaff ? 'Proceed to Portal' : 'Applicant Portal'}</Link
                    >
            </div>
        </div>
        </div>
        <div class="border-border/60 border-t">
            <div
                class="text-muted-foreground mx-auto flex max-w-7xl flex-col gap-2 px-4 py-5 text-xs sm:flex-row sm:items-center sm:justify-between sm:px-6 lg:px-8"
            >
                <p>
                    &copy; {new Date().getFullYear()}
                    {institution.name} · {institution.campus}.
                </p>
                <p class="text-muted-foreground/70 inline-flex items-center gap-1.5">
                    <span>Admissions and testing powered by</span>
                    <span class="inline-flex items-center gap-1 font-semibold text-foreground/80">
                        <NexiamLogo variant="mark" size="xs" class="size-3.5 inline-block" />
                        NEXIAM
                    </span>
                </p>
            </div>
        </div>
    </footer>
    {#if !user}
        <PublicMovingLauncher />
    {/if}
    {#if user}
        <aside
            class="fixed bottom-5 left-1/2 -translate-x-1/2 z-40 pointer-events-auto"
            aria-label="Active session"
        >
            <Link
                href={isStaff ? '/staff' : '/applicant/portal'}
                class="group inline-flex max-w-[calc(100vw-2rem)] items-center gap-2 rounded-full border border-border/80 bg-background/80 px-4 py-2 text-xs font-semibold text-foreground shadow-lg backdrop-blur-md transition-all hover:border-primary/50 hover:bg-background/95 active:scale-95 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring"
            >
                <span class="relative flex size-2 shrink-0">
                    <span class="absolute inline-flex size-full animate-ping rounded-full bg-emerald-400 opacity-75"></span>
                    <span class="relative inline-flex size-2 rounded-full bg-emerald-500"></span>
                </span>
                <span class="truncate">{isStaff ? 'Return to Staff Workspace' : 'Return to Portal'}</span>
                <span class="text-muted-foreground transition-transform group-hover:translate-x-0.5" aria-hidden="true">&rarr;</span>
            </Link>
        </aside>
    {/if}
</div>
<style>
    .public-home-shell
        :global(
            main
                :is(
                    #mission,
                    #programs,
                    #admissions,
                    #campus-life,
                    #policies
                )
        ) {
        scroll-margin-top: calc(var(--public-header-height) + 1rem);
    }
</style>
