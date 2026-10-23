<script>
  /**
   * Authenticated staff shell — nav chrome only.
   *
   * This is a presentational port of the legacy AuthenticatedLayout. Navigation
   * groups and their capability-filtered destinations come from the server-authored
   * staff navigation contract (GF-22), never client-side role checks.
   */
  import { Link, router, usePage } from '@inertiajs/svelte';
  import { onMount } from 'svelte';
  import * as Dialog from '@/Components/ui/dialog';
  import { QrCode,
    ChevronDown,
    ChevronRight,
    Menu,
    X,
    LayoutDashboard,
    Users,
    ClipboardList,
    Calendar,
    Activity,
    Shield,
    FileCheck,
    MessagesSquare,
    Megaphone,
    Send,
    BookOpen,
    Settings,
    Printer,
    FileSpreadsheet,
    UserRound,
    LogOut,
    GraduationCap,
    BadgeCheck,
    CircleHelp,
  } from 'lucide-svelte';
  import { sound } from '@/lib/sound.js';
  import {
    activeStaffNavSectionKey,
    flattenStaffNavSections,
    isStaffNavActive,
    loadStaffNavSectionState,
    setStaffNavSectionExpanded,
    staffNavStorageKey,
  } from '@/Pages/Staff/staff-nav.js';
  import ToastManager from '@/Components/kit/ToastManager.svelte';
  import NotificationBell from '@/Components/kit/NotificationBell.svelte';
  import NavBadge from '@/Components/kit/NavBadge.svelte';
  import PreferencesMenu from '@/Components/kit/PreferencesMenu.svelte';
  import AvatarBadge from '@/Components/kit/AvatarBadge.svelte';
  import ApplicantScanModal from '@/Components/kit/ApplicantScanModal.svelte';
  import IdleTimeoutGuard from '@/Components/kit/IdleTimeoutGuard.svelte';
  import InstitutionalBackdrop from '@/Components/kit/InstitutionalBackdrop.svelte';
  import { Button } from '@/Components/ui/button';
  import NexiamLogo from '@/Components/kit/NexiamLogo.svelte';
  import { clearStaffIdleMarker } from '@/lib/staff-idle-marker';
  import { closeStaffIdleAlert } from '@/lib/staff-idle-alerts.svelte';
  import { notifyRealtimePulseObservation } from '@/lib/realtime-pulse.svelte';
  let { children, breadcrumbs = [] } = $props();

  const page = usePage();
  const user = $derived(page.props.auth?.user);
  const canResolveApplicant = $derived((user?.capabilities ?? []).includes('applicant.qr.resolve'));
  const institution = $derived(page.props.institution);
  let logoLoadError = $state(false);
  const navBadges = $derived(page.props.navigation_badges?.items ?? {});
  const officeTitle = $derived.by(() => {
    const office = String(user?.office ?? '').trim();
    if (!office) return 'Staff Office';

    if (office.toLowerCase() === 'program') {
      return user?.scope?.department_id != null ? 'College Dean' : 'Program Head';
    }

    const labels = {
      admin: 'Admin Office',
      guidance: 'Guidance Office',
      guidance_counselor: 'Guidance Office',
      registrar: 'Registrar Office',
      registrar_admin: 'Registrar Office',
      admissions: 'Admissions Office',
      assessment: 'Assessment Office',
      examination: 'Examination Office',
      clinic: 'Campus Clinic',
    };
    return labels[office.toLowerCase()]
      ?? office.replaceAll('_', ' ').replace(/\b\w/g, (character) => character.toUpperCase());
  });
  onMount(() => {
    if (user?.is_staff !== true) return;
    try {
      localStorage.setItem('nexiam.last_portal', 'staff');
    } catch {
      // Portal hints are optional and never participate in authorization.
    }
  });
  const headTitle = $derived(
    breadcrumbs.length > 0
      ? `${breadcrumbs[breadcrumbs.length - 1].label} - NEXIAM`
      : 'NEXIAM'
  );
  let sidebarOpen = $state(false);
  import { scanModal, openScanModal, closeScanModal } from '@/lib/scan-modal';
  let breadcrumbOpen = $state(false);

  $effect(() => {
    notifyRealtimePulseObservation(page.props);
  });

  $effect(() => {
    page.url; // re-runs whenever the URL changes
    breadcrumbOpen = false;
  });
  let logoutConfirmOpen = $state(false);

  function handleNavClick(e) {
    const target = e.target.closest('a, button, [role="button"]');
    if (!target) return;
    if (target.closest('aside') || target.closest('header') || target.closest('[aria-label*="breadcrumb" i]')) {
      sound.tick();
    }
  }

  function promptLogout() {
    closeDropdowns();
    logoutConfirmOpen = true;
  }

  function confirmLogout() {
    logoutConfirmOpen = false;
    clearStaffIdleMarker();
    try {
      closeStaffIdleAlert();
    } catch {
      // The browser alert must not block staff sign-out.
    }
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
    Megaphone,
    Send,
    BookOpen,
    Settings,
    Printer,
    FileSpreadsheet,
    GraduationCap,
    BadgeCheck,
    CircleHelp,
  };
  const navSections = $derived(
    (Array.isArray(page.props.staff_navigation)
      ? page.props.staff_navigation
      : [
          {
            label: null,
            items: [
              { href: '/staff', label: 'Home', icon: 'LayoutDashboard', match: 'exact' },
            ],
          },
        ]
    ).map((section) => ({
      ...section,
      items: (Array.isArray(section?.items) ? section.items : Object.values(section?.items ?? {})).map((item) => ({
        ...item,
        icon: iconMap[item.icon] ?? LayoutDashboard,
      })),
    }))
  );

  const navItems = $derived(flattenStaffNavSections(navSections));

  let navSectionState = $state({ key: null, expanded: {} });
  let lastNavIdentity = null;
  let lastNavPath = null;

  function getStaffNavStorage() {
    try {
      return window.localStorage;
    } catch {
      return undefined;
    }
  }

  $effect(() => {
    const actorId = user?.id;
    const generation = page.props.auth?.staff_inactivity_generation;
    const storageKey = staffNavStorageKey(actorId, generation);
    const identity = `${actorId ?? ''}:${generation ?? ''}`;
    const path = (page.url || '/').split('?')[0].split('#')[0] || '/';
    const activeSection = activeStaffNavSectionKey(navSections, navItems, page.url || '');
    const identityChanged = identity !== lastNavIdentity;
    const enteredPath = path !== lastNavPath;

    if (identityChanged || storageKey !== navSectionState.key) {
      navSectionState = loadStaffNavSectionState(navSections, actorId, generation, getStaffNavStorage());
    }
    if (activeSection !== null && (identityChanged || enteredPath)) {
      navSectionState = setStaffNavSectionExpanded(navSectionState, activeSection, true, getStaffNavStorage());
    }

    lastNavIdentity = identity;
    lastNavPath = path;
  });

  function toggleNavSection(section) {
    navSectionState = setStaffNavSectionExpanded(
      navSectionState,
      section.key,
      navSectionState.expanded[section.key] !== true,
      getStaffNavStorage(),
    );
  }

  function closeDropdowns() {
    sidebarOpen = false;
  }

  function isNavActive(item) {
    return isStaffNavActive(item, navItems, page.url || '');
  }

</script>

<svelte:head>
  <title>{headTitle}</title>
</svelte:head>

<div class="h-dvh w-full max-w-[100vw] flex overflow-hidden relative" onclick={handleNavClick}>
  <!-- AMBIENT RADIAL MESH GLOW (Exact match to UI Lab) -->
  <div class="pointer-events-none fixed inset-0 z-0 bg-[radial-gradient(circle_at_82%_15%,oklch(0.82_0.12_145_/_0.05),transparent_35%),radial-gradient(circle_at_12%_85%,oklch(0.9_0.08_85_/_0.06),transparent_35%)] dark:bg-[radial-gradient(circle_at_82%_15%,oklch(0.38_0.1_145_/_0.18),transparent_35%),radial-gradient(circle_at_12%_85%,oklch(0.35_0.07_85_/_0.15),transparent_35%)]"></div>
  <InstitutionalBackdrop fixed={true} opacity="opacity-[0.11] sm:opacity-[0.14] dark:opacity-[0.22]" />
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
        <Link href="/" class="flex items-center gap-2.5 hover:opacity-85 transition-opacity min-w-0" onclick={closeDropdowns} title="{institution?.name ?? 'NEXIAM'} Public Homepage" aria-label="{institution?.name ?? 'NEXIAM'} Public Homepage">
          {#if institution?.logo_url && !logoLoadError}
            <img src={institution.logo_url} alt="Logo" class="size-8 rounded-lg object-contain shrink-0" onerror={() => (logoLoadError = true)} />
          {:else}
            <span class="flex size-8 items-center justify-center rounded-lg bg-primary text-primary-foreground font-bold text-sm shrink-0" aria-hidden="true">
              {institution?.short_name?.slice(0, 1) ?? 'N'}
            </span>
          {/if}
          <div class="min-w-0 flex flex-col leading-tight">
            <span class="font-semibold text-sm tracking-tight text-foreground truncate">{institution?.short_name ?? 'NEXIAM'}</span>
            <span class="text-[10px] font-medium text-muted-foreground truncate">{officeTitle}</span>
          </div>
        </Link>
        <Button variant="ghost" size="icon" class="md:hidden ml-auto" onclick={() => (sidebarOpen = false)} aria-label="Close menu">
          <X class="h-5 w-5" />
        </Button>
      </div>

      <nav class="flex-1 overflow-y-auto scrollbar-hide p-2 space-y-1">
        {#each navSections as section}
          <div class="space-y-0.5">
            {#if section.label}
              {#if section.collapsible === true && section.key}
                {@const isExpanded = navSectionState.expanded[section.key] === true}
                {@const containsActiveItem = section.items.some((item) => isNavActive(item))}
                <button
                  type="button"
                  class="flex min-h-10 w-full items-center gap-2 rounded-md px-3 py-2 text-left text-xs font-semibold uppercase tracking-wider text-muted-foreground/70 transition-colors hover:bg-accent hover:text-foreground focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring"
                  aria-expanded={isExpanded}
                  aria-controls="staff-nav-section-{section.key}"
                  onclick={() => toggleNavSection(section)}
                >
                  <span class="min-w-0 flex-1 whitespace-normal break-words text-left leading-tight">{section.label}</span>
                  {#if containsActiveItem}<span class="sr-only">Contains current page</span><span class="size-1.5 shrink-0 rounded-full bg-primary" aria-hidden="true"></span>{/if}
                  {#if section.key === 'communications_support' && !isExpanded && navBadges.messaging}
                    <NavBadge badge={navBadges.messaging} active={containsActiveItem} />
                  {/if}
                  <ChevronDown class="size-4 shrink-0 transition-transform {isExpanded ? 'rotate-180' : ''}" aria-hidden="true" />
                </button>
                <div id="staff-nav-section-{section.key}" hidden={!isExpanded}>
                    {#each section.items as item}
                      <Link
                        href={item.href}
                        class="nav-item flex items-center gap-2.5 rounded-md px-3 py-2 text-sm font-medium transition-colors duration-150 {isNavActive(item)
                          ? 'bg-primary text-primary-foreground shadow-sm'
                          : 'text-foreground/80 hover:bg-accent hover:text-foreground'}"
                        onclick={closeDropdowns}
                      >
                        <item.icon class="size-4 shrink-0" />
                        <span class="min-w-0 flex-1 truncate">{item.label}</span>
                        {#if item.badge_key && navBadges[item.badge_key]}<NavBadge badge={navBadges[item.badge_key]} active={isNavActive(item)} />{/if}
                      </Link>
                    {/each}
                  </div>
              {:else}
                <p class="px-3 pt-2 pb-0.5 text-xs font-semibold uppercase tracking-wider text-muted-foreground/70">
                  {section.label}
                </p>
                {#each section.items as item}
                  <Link
                    href={item.href}
                    class="nav-item flex items-center gap-2.5 rounded-md px-3 py-2 text-sm font-medium transition-colors duration-150 {isNavActive(item)
                      ? 'bg-primary text-primary-foreground shadow-sm'
                      : 'text-foreground/80 hover:bg-accent hover:text-foreground'}"
                    onclick={closeDropdowns}
                  >
                    <item.icon class="size-4 shrink-0" />
                    <span class="min-w-0 flex-1 truncate">{item.label}</span>
                    {#if item.badge_key && navBadges[item.badge_key]}<NavBadge badge={navBadges[item.badge_key]} active={isNavActive(item)} />{/if}
                  </Link>
                {/each}
              {/if}
            {:else}
              {#each section.items as item}
                <Link
                  href={item.href}
                  class="nav-item flex items-center gap-2.5 rounded-md px-3 py-2 text-sm font-medium transition-colors duration-150 {isNavActive(item)
                    ? 'bg-primary text-primary-foreground shadow-sm'
                    : 'text-foreground/80 hover:bg-accent hover:text-foreground'}"
                  onclick={closeDropdowns}
                >
                  <item.icon class="size-4 shrink-0" />
                  <span class="min-w-0 flex-1 truncate">{item.label}</span>
                  {#if item.badge_key && navBadges[item.badge_key]}<NavBadge badge={navBadges[item.badge_key]} active={isNavActive(item)} />{/if}
                </Link>
              {/each}
            {/if}
          </div>
        {/each}
      </nav>
    </div>

      <div class="p-3 pb-0 shrink-0">
          <Button
              variant="default"
              size="lg"
              class="w-full min-h-[44px] gap-2 font-semibold shadow-sm"
              onclick={() => openScanModal()}
          >
              <QrCode class="size-4" />
              Scan QR code
          </Button>
      </div>

    <!-- User block in sidebar footer -->
    <div class="border-t border-border/50 p-3 shrink-0">
      <div class="flex items-center gap-2">
        <Link
          href="/staff/profile"
          class="flex min-h-[48px] min-w-0 flex-1 items-center gap-3 rounded-xl px-2 py-2 text-left transition-colors hover:bg-muted/70 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring"
          aria-label="Open profile for {user?.name ?? 'staff member'}"
          onclick={closeDropdowns}
        >
          <span class="relative shrink-0">
            <AvatarBadge avatar={user?.avatar} name={user?.name ?? ''} size="md" />
            <span class="absolute bottom-0 right-0 size-3 rounded-full border-2 border-card bg-emerald-500" aria-hidden="true" title="Session active"></span>
          </span>
          <span class="min-w-0 flex-1">
            <span class="block truncate text-sm font-semibold text-foreground">{user?.name ?? 'User'}</span>
            <span class="block truncate text-xs text-muted-foreground">{officeTitle}</span>
          </span>
        </Link>
        <button
          type="button"
          class="flex min-h-[44px] min-w-[44px] size-10 shrink-0 items-center justify-center rounded-xl text-muted-foreground transition-colors hover:bg-destructive/10 hover:text-destructive focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring"
          onclick={promptLogout}
          title="Sign out"
          aria-label="Sign out"
        >
          <LogOut class="size-5" aria-hidden="true" />
        </button>
      </div>
      <div class="mt-2 pt-2 border-t border-border/40 flex items-center justify-center gap-1.5 text-[10px] text-muted-foreground/80">
        <span>powered by</span>
        <span class="inline-flex items-center gap-1 font-bold text-foreground/80">
          <NexiamLogo variant="mark" size="xs" class="size-3 inline-block" />
          NEXIAM
        </span>
      </div>
    </div>
  </aside>

  <div class="flex min-h-0 min-w-0 flex-1 flex-col md:pl-56 relative z-10 print:pl-0">
    <!-- Header -->
    <header class="shrink-0 z-40 h-20 glass-panel border-b border-l-0 flex items-center justify-between gap-2 px-4 lg:px-8 relative print:hidden">
      <div class="flex min-w-0 flex-1 items-center gap-2 sm:gap-4">
        <Button variant="ghost" size="icon" class="md:hidden relative shrink-0" onclick={() => (sidebarOpen = !sidebarOpen)} aria-label={sidebarOpen ? "Close menu" : "Open menu"}>
          {#if sidebarOpen}
            <X class="h-5 w-5" />
          {:else}
            <Menu class="h-5 w-5" />
          {/if}
          {#if Object.values(navBadges).some(Boolean)}<span class="absolute right-1 top-1 size-2 rounded-full bg-destructive" aria-hidden="true"><span class="sr-only">Unread messages</span></span>{/if}
        </Button>
        {#if breadcrumbs.length === 0}
          <Link href="/staff" class="flex items-center gap-2 hover:opacity-85 transition-opacity" title="Staff Workspace" aria-label="Staff Workspace"><NexiamLogo variant="mark" size="xs" class="size-4" /><span class="text-sm font-semibold tracking-tight text-foreground">Staff Workspace</span></Link>
        {:else}
          <!-- The authenticated shell owns route context only (quiet wayfinding).
               The page canvas owns document headings via PageHeaderBar. -->
          <nav class="hidden min-w-0 flex-1 items-center gap-1.5 overflow-x-auto whitespace-nowrap text-xs scrollbar-hide lg:flex" aria-label="Breadcrumb">
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
                  class="font-semibold text-foreground truncate max-w-[180px] sm:max-w-none shrink-0"
                  aria-current={i === breadcrumbs.length - 1 ? 'page' : undefined}
                >
                  {crumb.label}
                </span>
              {/if}
            {/each}
          </nav>

          <!-- Mobile route context keeps the shell compact without owning the
               editor's Setup/Design/Verify workflow. -->
          <div class="flex min-w-0 flex-1 items-center gap-2 lg:hidden">
            <div class="min-w-0 flex-1">
              <button
                type="button"
                class="flex min-h-11 min-w-0 max-w-full items-center gap-2 text-sm"
                onclick={() => (breadcrumbOpen = !breadcrumbOpen)}
                aria-expanded={breadcrumbOpen}
                aria-label="Breadcrumb navigation"
              >
                <span class="rounded bg-muted px-2 py-0.5 text-xs font-semibold text-muted-foreground">Path</span>
                <ChevronRight class="h-4 w-4 text-muted-foreground/50 shrink-0" aria-hidden="true" />
                <span class="min-w-0 truncate font-semibold text-foreground">{breadcrumbs[breadcrumbs.length - 1].label}</span>
                <ChevronDown class="h-4 w-4 text-muted-foreground ml-1 shrink-0 transition-transform {breadcrumbOpen ? 'rotate-180' : ''}" aria-hidden="true" />
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
          </div>
        {/if}
      </div>

      <div class="flex shrink-0 items-center gap-2 sm:gap-4">
        <NotificationBell />
        <PreferencesMenu isStaff={true} />
        <ToastManager placement="below-anchor" />
      </div>
    </header>

    <!-- Main content -->
    <main class="flex-1 min-h-0 min-w-0 overflow-x-hidden overflow-y-auto p-4 lg:p-8 scroll-smooth relative z-10" id="main-content">
      <div class="min-w-0 w-full max-w-full">
        {@render children?.()}
      </div>
    </main>
  </div>
</div>

<Dialog.Root bind:open={logoutConfirmOpen}>
  <Dialog.Content class="sm:max-w-md">
    <Dialog.Header>
      <Dialog.Title>Sign out of staff workspace?</Dialog.Title>
      <Dialog.Description>
        You are about to end your active staff session. Unsaved changes in any active forms will be lost.
      </Dialog.Description>
    </Dialog.Header>
    <Dialog.Footer class="flex flex-col-reverse gap-2 sm:flex-row sm:justify-end">
      <Button type="button" variant="outline" onclick={() => (logoutConfirmOpen = false)}>
        Cancel
      </Button>
      <Button type="button" variant="destructive" onclick={confirmLogout}>
        Sign out
      </Button>
    </Dialog.Footer>
  </Dialog.Content>
</Dialog.Root>

<ApplicantScanModal open={$scanModal.open} preselect={$scanModal.preselect} general={!$scanModal.preselect} {canResolveApplicant} onOpenChange={() => closeScanModal()} />
<IdleTimeoutGuard />

<style>
  .scrollbar-hide {
    -ms-overflow-style: none;
    scrollbar-width: none;
  }
  .scrollbar-hide::-webkit-scrollbar {
    display: none;
  }
</style>
