<script>
    import { Link, usePage } from '@inertiajs/svelte';
    import { Menu, Moon, Sun, X } from 'lucide-svelte';
    import ToastManager from '@/Components/kit/ToastManager.svelte';

    let {
        children,
        institution = {
            name: 'Ilocos Sur Polytechnic State College',
            short_name: 'ISPSC',
            campus: 'Tagudin Campus',
        },
    } = $props();

    const page = usePage();
    const user = $derived(page.props.auth?.user ?? null);
    let mobileNavOpen = $state(false);
    let darkMode = $state(
        typeof document !== 'undefined' &&
            document.documentElement.classList.contains('dark'),
    );

    $effect(() => {
        const savedTheme = localStorage.getItem('theme');

        if (savedTheme !== 'dark' && savedTheme !== 'light') return;

        darkMode = savedTheme === 'dark';
        document.documentElement.classList.toggle('dark', darkMode);
    });

    const portalHref = $derived(
        user?.is_staff
            ? '/staff'
            : user?.is_applicant
              ? '/applicant/portal'
              : '/applicant/login',
    );
    const portalLabel = $derived(
        user?.is_staff ? 'Staff Portal' : 'Applicant Portal',
    );
    const staffHref = $derived(user?.is_staff ? '/staff' : '/login');
    const staffLabel = $derived(
        user?.is_staff ? 'Open staff portal' : 'Staff sign in',
    );

    const navItems = [
        { href: '#mission', label: 'About' },
        { href: '#programs', label: 'Programs' },
        { href: '#admissions', label: 'Admissions' },
        { href: '#campus-life', label: 'Campus life' },
        { href: '#news', label: 'News' },
    ];

    function closeMenu() {
        mobileNavOpen = false;
    }

    function toggleTheme() {
        document.documentElement.classList.toggle('dark');
        darkMode = document.documentElement.classList.contains('dark');
        localStorage.setItem('theme', darkMode ? 'dark' : 'light');
    }

    function isActive(href) {
        const [path, hash] = href.split('#');
        const current = page.url ?? '/';
        if (path && path !== current.split('?')[0]) return false;
        return hash ? current.includes(`#${hash}`) : current === '/';
    }
</script>

<div class="bg-background flex min-h-dvh flex-col font-sans">
    <ToastManager />

    <header
        class="border-border/70 bg-background/85 sticky top-0 z-50 border-b backdrop-blur-xl"
    >
        <div
            class="mx-auto flex min-h-16 max-w-7xl items-center justify-between gap-5 px-4 sm:px-6 lg:px-8"
        >
            <Link
                href="/"
                class="group flex min-w-0 items-center gap-3"
                aria-label={`${institution.name} home`}
                onclick={closeMenu}
            >
                <span
                    class="bg-primary text-primary-foreground flex size-9 shrink-0 items-center justify-center rounded-xl text-base font-bold shadow-sm transition group-hover:-rotate-3"
                    aria-hidden="true"
                >
                    {institution.short_name?.slice(0, 1) ?? 'I'}
                </span>
                <span class="min-w-0">
                    <span
                        class="text-foreground block truncate text-sm font-bold tracking-[-0.02em] sm:text-base"
                        >{institution.name}</span
                    >
                    <span
                        class="text-muted-foreground hidden text-[0.68rem] font-medium tracking-[0.14em] uppercase sm:block"
                        >{institution.campus}</span
                    >
                </span>
            </Link>

            <nav
                class="hidden items-center gap-5 text-sm font-medium lg:flex"
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

            <div class="hidden items-center gap-2 sm:flex">
                <Link
                    href={portalHref}
                    class="text-foreground hover:border-primary/45 hover:text-primary border-border focus-visible:ring-ring inline-flex min-h-11 items-center rounded-full border px-4 py-2 text-sm font-semibold transition focus-visible:ring-2 focus-visible:outline-none"
                >
                    {portalLabel}
                </Link>
                <Link
                    href={staffHref}
                    class="bg-primary text-primary-foreground hover:bg-primary/90 focus-visible:ring-ring inline-flex min-h-11 items-center rounded-full px-4 py-2 text-sm font-semibold shadow-sm transition focus-visible:ring-2 focus-visible:outline-none"
                >
                    {staffLabel}
                </Link>
            </div>

            <button
                type="button"
                class="text-foreground border-border hover:border-primary/45 hover:text-primary focus-visible:ring-ring hidden min-h-11 min-w-11 items-center justify-center rounded-full border transition focus-visible:ring-2 focus-visible:outline-none sm:inline-flex"
                aria-label={darkMode
                    ? 'Switch to light mode'
                    : 'Switch to dark mode'}
                aria-pressed={darkMode}
                onclick={toggleTheme}
            >
                {#if darkMode}
                    <Sun class="size-4" />
                {:else}
                    <Moon class="size-4" />
                {/if}
            </button>

            <button
                type="button"
                class="text-foreground border-border inline-flex min-h-11 min-w-11 items-center justify-center rounded-full border lg:hidden"
                aria-label={mobileNavOpen
                    ? 'Close navigation menu'
                    : 'Open navigation menu'}
                aria-expanded={mobileNavOpen}
                aria-controls="public-mobile-navigation"
                onclick={() => (mobileNavOpen = !mobileNavOpen)}
            >
                {#if mobileNavOpen}
                    <X class="size-5" />
                {:else}
                    <Menu class="size-5" />
                {/if}
            </button>
        </div>

        {#if mobileNavOpen}
            <div
                id="public-mobile-navigation"
                class="border-border/70 border-t lg:hidden"
            >
                <nav
                    class="mx-auto grid max-w-7xl gap-1 px-4 py-4 sm:px-6"
                    aria-label="Mobile institutional navigation"
                >
                    {#each navItems as item (item.href)}
                        <a
                            href={item.href}
                            class="text-foreground hover:bg-muted flex min-h-11 items-center rounded-lg px-3 py-2 text-sm font-semibold transition {isActive(
                                item.href,
                            )
                                ? 'bg-primary/10 text-primary'
                                : ''}"
                            onclick={closeMenu}
                        >
                            {item.label}
                        </a>
                    {/each}
                    <div
                        class="border-border/60 mt-2 grid gap-2 border-t pt-3 sm:grid-cols-2"
                    >
                        <Link
                            href={portalHref}
                            class="border-border text-foreground inline-flex min-h-11 items-center justify-center rounded-full border px-4 py-2 text-sm font-semibold"
                            onclick={closeMenu}
                        >
                            {portalLabel}
                        </Link>
                        <Link
                            href={staffHref}
                            class="bg-primary text-primary-foreground inline-flex min-h-11 items-center justify-center rounded-full px-4 py-2 text-sm font-semibold"
                            onclick={closeMenu}
                        >
                            {staffLabel}
                        </Link>
                    </div>
                    <button
                        type="button"
                        class="text-foreground border-border hover:border-primary/45 hover:text-primary focus-visible:ring-ring inline-flex min-h-11 items-center justify-center gap-2 rounded-full border px-4 py-2 text-sm font-semibold transition focus-visible:ring-2 focus-visible:outline-none sm:col-span-3"
                        aria-label={darkMode
                            ? 'Switch to light mode'
                            : 'Switch to dark mode'}
                        aria-pressed={darkMode}
                        onclick={toggleTheme}
                    >
                        {#if darkMode}
                            <Sun class="size-4" />
                            Use light mode
                        {:else}
                            <Moon class="size-4" />
                            Use dark mode
                        {/if}
                    </button>
                </nav>
            </div>
        {/if}
    </header>

    <main class="flex-grow">
        {@render children()}
    </main>

    <footer id="contact" class="border-border/70 bg-card/50 mt-auto border-t">
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
                        href="#mission"
                        class="text-muted-foreground hover:text-primary py-1 transition"
                        >About the institution</a
                    >
                    <a
                        href="#programs"
                        class="text-muted-foreground hover:text-primary py-1 transition"
                        >Programs</a
                    >
                    <a
                        href="#admissions"
                        class="text-muted-foreground hover:text-primary py-1 transition"
                        >Admissions</a
                    >
                    <a
                        href="#campus-life"
                        class="text-muted-foreground hover:text-primary py-1 transition"
                        >Campus life</a
                    >
                </div>
            </div>
            <div>
                <p class="text-foreground text-sm font-semibold">
                    NEXIAM services
                </p>
                <div class="mt-4 grid gap-2 text-sm">
                    <Link
                        href="/apply"
                        class="text-muted-foreground hover:text-primary py-1 transition"
                        >Start an application</Link
                    >
                    <Link
                        href="/applicant/login"
                        class="text-muted-foreground hover:text-primary py-1 transition"
                        >Applicant Portal</Link
                    >
                    <Link
                        href={staffHref}
                        class="text-muted-foreground hover:text-primary py-1 transition"
                        >{staffLabel}</Link
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
                <p class="text-muted-foreground/70">
                    Admissions and testing powered by NEXIAM.
                </p>
            </div>
        </div>
    </footer>
</div>
