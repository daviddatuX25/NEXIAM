<script>
  /**
   * Authenticated staff shell — nav chrome only.
   *
   * This is a presentational port of the legacy AuthenticatedLayout. The
   * legacy version hardcoded role-string arrays per nav item and included
   * a NotificationDropdown with polling/sound logic; both are dropped
   * here. Nav items are a small generic placeholder list — any real
   * journey navigation must come from a server-authored, capability-aware
   * nav contract (GF-22), never from a client-side role check.
   */
  import { Link, router } from '@inertiajs/svelte';
  import { usePage } from '@inertiajs/svelte';
  import { QrCode,
    ChevronDown,
    ChevronRight,
    Menu,
    LayoutDashboard,
    Sun,
    Moon,
    Users,
    ClipboardList,
    Calendar,
    Activity,
    Shield,
    FileCheck,
    MessagesSquare,
    Send,
    BookOpen,
    Settings,
  } from 'lucide-svelte';
  import { isStaffNavActive } from '@/Pages/Staff/staff-nav.js';
  import ToastManager from '@/Components/kit/ToastManager.svelte';
  import NotificationBell from '@/Components/kit/NotificationBell.svelte';
  import NexiDock from '@/Components/kit/Nexi/NexiDock.svelte';
  import ApplicantScanModal from '@/Components/kit/ApplicantScanModal.svelte';
  import { Button } from '@/Components/ui/button';

  let { children, breadcrumbs = [] } = $props();

  const page = usePage();
  const user = $derived(page.props.auth?.user ?? null);
  const headTitle = $derived(
    breadcrumbs.length > 0
      ? `${breadcrumbs[breadcrumbs.length - 1].label} - NEXIAM`
      : 'NEXIAM'
  );
  let sidebarOpen = $state(false);
  import { scanModal, openScanModal, closeScanModal } from '@/lib/scan-modal';
  let breadcrumbOpen = $state(false);

  $effect(() => {
    page.url; // re-runs whenever the URL changes
    breadcrumbOpen = false;
  });

  let userDropdownOpen = $state(false);
  let darkMode = $state(typeof document !== 'undefined' && document.documentElement.classList.contains('dark'));

  function toggleTheme() {
    document.documentElement.classList.toggle('dark');
    darkMode = document.documentElement.classList.contains('dark');
    localStorage.setItem('theme', darkMode ? 'dark' : 'light');
  }

  function logout() {
    router.post('/logout');
  }

  const iconMap = {
    LayoutDashboard,
    Users,
    ClipboardList,
    Calendar,
    Activity,
    Shield,
    FileCheck,
    MessagesSquare,
    Send,
    BookOpen,
    Settings,
  };

  const navSections = $derived(
    (page.props.staff_navigation ?? [
      {
        label: null,
        items: [
          { href: '/staff', label: 'Home', icon: 'LayoutDashboard', match: 'exact' },
        ],
      },
    ]).map((section) => ({
      ...section,
      items: (section.items ?? []).map((item) => ({
        ...item,
        icon: iconMap[item.icon] ?? LayoutDashboard,
      })),
    }))
  );

  const navItems = $derived(navSections.flatMap((section) => section.items));

  function closeDropdowns() {
    userDropdownOpen = false;
    sidebarOpen = false;
  }

  function isNavActive(item) {
    return isStaffNavActive(item, navItems, page.url || '');
  }
</script>

<svelte:head>
  <title>{headTitle}</title>
</svelte:head>

<div class="h-dvh w-full max-w-[100vw] flex overflow-hidden">
  {#if sidebarOpen}
  <button
    type="button"
    class="fixed inset-0 z-40 bg-black/50 lg:hidden print:hidden"
    aria-label="Close sidebar"
    onclick={closeDropdowns}
  ></button>
  {/if}

  <!-- Sidebar -->
  <aside
    class="fixed inset-y-0 left-0 z-50 w-56 flex-shrink-0 glass-panel border-r flex flex-col transform transition-transform duration-200 ease-in-out -translate-x-full md:translate-x-0 {sidebarOpen ? 'translate-x-0' : ''} print:hidden"
  >
    <div class="flex flex-col h-full overflow-hidden">
      <div class="h-14 flex items-center px-4 border-b border-border/50 shrink-0">
        <Link href="/staff" class="flex items-center gap-3" onclick={closeDropdowns}>
          <span class="flex size-8 items-center justify-center rounded-lg bg-primary text-primary-foreground font-bold text-sm shrink-0" aria-hidden="true">N</span>
          <span class="font-semibold text-base tracking-tight text-foreground">NEXIAM</span>
        </Link>
        <Button variant="ghost" size="icon" class="md:hidden ml-auto" onclick={() => (sidebarOpen = !sidebarOpen)} aria-label="Close menu">
          <Menu class="h-5 w-5" />
        </Button>
      </div>

      <nav class="flex-1 overflow-y-auto scrollbar-hide p-2 space-y-1">
        {#each navSections as section}
          <div class="space-y-0.5">
            {#if section.label}
              <p class="px-3 pt-2 pb-0.5 text-xs font-semibold uppercase tracking-wider text-muted-foreground/70">
                {section.label}
              </p>
            {/if}
            {#each section.items as item}
              <Link
                href={item.href}
                class="nav-item flex items-center gap-2.5 px-3 py-2 rounded-md text-sm font-medium transition-colors duration-150 {isNavActive(item)
                  ? 'bg-primary text-primary-foreground shadow-sm'
                  : 'text-foreground/80 hover:text-foreground hover:bg-accent'}"
                onclick={closeDropdowns}
              >
                <item.icon class="w-4 h-4 shrink-0" />
                {item.label}
              </Link>
            {/each}
          </div>
        {/each}
      </nav>
    </div>

    <!-- Scan applicant QR — pinned above the profile block (desktop + mobile) -->
    <div class="p-3 pb-0 shrink-0">
        <Button
            variant="default"
            size="lg"
            class="w-full min-h-[44px] gap-2 font-semibold shadow-sm"
            onclick={() => openScanModal()}
        >
            <QrCode class="size-4" />
            Scan Applicant QR
        </Button>
    </div>

    <!-- User block in sidebar footer -->
    <div class="p-4 border-t border-border/50 shrink-0">
      <div class="relative">
        <button
          type="button"
          class="flex items-center gap-3 px-4 py-3 rounded-xl bg-muted/50 hover:bg-muted w-full text-left transition-colors min-h-[44px]"
          onclick={() => { userDropdownOpen = !userDropdownOpen; }}
          aria-expanded={userDropdownOpen}
          aria-haspopup="true"
        >
          <div class="w-10 h-10 rounded-xl bg-primary/20 border-2 border-primary/20 flex items-center justify-center text-sm font-semibold text-foreground shrink-0">
            {(user?.name ?? 'U').split(/\s+/).map(n => n[0]).join('').slice(0, 2).toUpperCase()}
          </div>
          <div class="flex-1 min-w-0">
            <p class="text-sm font-semibold truncate text-foreground">{user?.name ?? 'User'}</p>
            <p class="text-xs text-muted-foreground truncate">{user?.email ?? ''}</p>
          </div>
          <ChevronDown class="h-4 w-4 text-muted-foreground shrink-0" />
        </button>
        {#if userDropdownOpen}
          <div
            class="absolute left-0 right-0 bottom-full mb-1 rounded-lg border border-border bg-card-solid py-1 shadow-lg z-50"
            role="menu"
          >
            <button
              type="button"
              class="w-full px-4 py-2 text-left text-sm text-foreground hover:bg-accent transition-colors min-h-[44px] rounded-lg"
              role="menuitem"
              onclick={() => { logout(); userDropdownOpen = false; }}
            >
              Sign out
            </button>
          </div>
        {/if}
      </div>
    </div>
  </aside>

  <div class="flex min-w-0 flex-1 flex-col md:pl-56 relative print:pl-0">
    <!-- Header -->
    <header class="shrink-0 z-20 h-20 glass-panel border-b border-l-0 flex items-center justify-between px-4 lg:px-8 relative print:hidden">
      <div class="flex items-center gap-4">
        <Button variant="ghost" size="icon" class="md:hidden" onclick={() => (sidebarOpen = true)} aria-label="Open menu">
          <Menu class="h-5 w-5" />
        </Button>
        {#if breadcrumbs.length === 0}
          <span class="text-2xl font-semibold tracking-tight text-foreground">NEXIAM</span>
        {:else if breadcrumbs.length === 1}
          <h1 class="text-2xl font-semibold tracking-tight text-foreground">{breadcrumbs[0].label}</h1>
        {:else}
          <!-- Desktop (md+): full inline trail, always visible -->
          <nav class="hidden md:flex items-center gap-2 text-sm" aria-label="Breadcrumb">
            {#each breadcrumbs as crumb, i}
              {#if i > 0}
                <ChevronRight class="h-4 w-4 text-muted-foreground/50 shrink-0" aria-hidden="true" />
              {/if}
              {#if crumb.href && i < breadcrumbs.length - 1}
                <Link
                  href={crumb.href}
                  class="text-muted-foreground hover:text-foreground transition-colors font-medium shrink-0"
                >
                  {crumb.label}
                </Link>
              {:else}
                <span
                  class="font-semibold text-foreground truncate max-w-[180px] sm:max-w-none"
                  aria-current={i === breadcrumbs.length - 1 ? 'page' : undefined}
                >
                  {crumb.label}
                </span>
              {/if}
            {/each}
          </nav>

          <!-- Mobile (< md): collapsed dropdown trail -->
          <div class="flex items-center md:hidden">
            <button
              type="button"
              class="flex items-center gap-2 text-sm"
              onclick={() => (breadcrumbOpen = !breadcrumbOpen)}
              aria-expanded={breadcrumbOpen}
              aria-label="Breadcrumb navigation"
            >
              <span class="rounded bg-muted px-2 py-0.5 text-xs font-semibold text-muted-foreground">•••</span>
              <ChevronRight class="h-4 w-4 text-muted-foreground/50 shrink-0" aria-hidden="true" />
              <span class="font-semibold text-foreground">{breadcrumbs[breadcrumbs.length - 1].label}</span>
              <ChevronDown class="h-4 w-4 text-muted-foreground ml-1 transition-transform {breadcrumbOpen ? 'rotate-180' : ''}" />
            </button>

            {#if breadcrumbOpen}
              <button
                type="button"
                class="fixed inset-0 z-10"
                aria-label="Close breadcrumb trail"
                onclick={() => (breadcrumbOpen = false)}
              ></button>

              <div class="absolute left-0 right-0 top-full z-20 border-b border-border bg-card shadow-lg">
                {#each breadcrumbs as crumb, i}
                  <div class="flex items-center gap-3 border-b border-border/50 px-5 py-3 last:border-0">
                    <div class="h-1.5 w-1.5 shrink-0 rounded-full {i === breadcrumbs.length - 1 ? 'bg-primary' : 'bg-muted-foreground/30'}"></div>
                    {#if crumb.href && i < breadcrumbs.length - 1}
                      <Link
                        href={crumb.href}
                        class="text-sm text-muted-foreground hover:text-foreground transition-colors"
                        onclick={() => (breadcrumbOpen = false)}
                      >
                        {crumb.label}
                      </Link>
                    {:else}
                      <span class="text-sm font-semibold text-foreground">{crumb.label}</span>
                    {/if}
                  </div>
                {/each}
              </div>
            {/if}
          </div>
        {/if}
      </div>

      <div class="flex items-center gap-2 sm:gap-4">
        <NotificationBell />
        <Button variant="ghost" size="icon" onclick={toggleTheme} aria-label={darkMode ? 'Switch to light mode' : 'Switch to dark mode'} class="rounded-full min-h-[44px] min-w-[44px]">
          <Sun class="h-5 w-5 dark:hidden" />
          <Moon class="h-5 w-5 hidden dark:block" />
        </Button>
        <ToastManager />
      </div>
    </header>

    <!-- Main content -->
    <main class="flex-1 min-w-0 overflow-x-hidden overflow-y-auto p-4 lg:p-8 scroll-smooth" id="main-content">
      <div class="min-w-0 w-full max-w-full">
        {@render children?.()}
      </div>
    </main>
  </div>
</div>

<NexiDock />

<ApplicantScanModal open={$scanModal.open} preselect={$scanModal.preselect} onOpenChange={() => closeScanModal()} />

<style>
  .scrollbar-hide {
    -ms-overflow-style: none;
    scrollbar-width: none;
  }
  .scrollbar-hide::-webkit-scrollbar {
    display: none;
  }
</style>
