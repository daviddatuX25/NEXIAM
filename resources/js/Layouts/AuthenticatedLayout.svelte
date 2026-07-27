<script>
  import { Link, router } from '@inertiajs/svelte';
  import { usePage } from '@inertiajs/svelte';
  import { ChevronDown, ChevronRight, Menu, LayoutDashboard, Users, FileText, Calendar, GraduationCap, Settings, ScrollText, Activity, Sun, Moon, SendHorizonal, BarChart3, Wrench } from 'lucide-svelte';
  import NotificationDropdown from '@/Components/NotificationDropdown.svelte';
  import ToastManager from '@/Components/ToastManager.svelte';
  import { Button } from '@/Components/ui/button';
  import { registerSounds } from '@/lib/notifStore.js';

  let { children, breadcrumbs = [] } = $props();

  // Preload notification sounds so they play instantly
  registerSounds({
    action: '/sounds/action.wav',
    background: '/sounds/background.mp3',
  });

  const page = usePage();
  const user = $derived($page.props.auth?.user ?? null);
  const roles = $derived(user?.roles?.map((r) => r.name) ?? []);
  const headTitle = $derived(
    breadcrumbs.length > 0
      ? `${breadcrumbs[breadcrumbs.length - 1].label} - SecureCAT`
      : 'SecureCAT'
  );
  let sidebarOpen = $state(false);
  let breadcrumbOpen = $state(false);

  $effect(() => {
    $page.url; // re-runs whenever the URL changes
    breadcrumbOpen = false;
  });

  let userDropdownOpen = $state(false);
  let darkMode = $state(typeof document !== 'undefined' && document.documentElement.classList.contains('dark'));
  let adminExpanded = $state(true);

  function toggleTheme() {
    document.documentElement.classList.toggle('dark');
    darkMode = document.documentElement.classList.contains('dark');
    localStorage.setItem('theme', darkMode ? 'dark' : 'light');
  }

  $effect(() => {
    if (typeof document !== 'undefined' && document.documentElement) {
      darkMode = document.documentElement.classList.contains('dark');
    }
  });

  function hasRole(r) {
    return roles.includes(r);
  }

  function canSee(requiredRoles, item) {
    if (requiredRoles.includes('*')) return true;
    if (!requiredRoles.some((r) => hasRole(r))) return false;
    if (item.featureFlag && !$page.props[item.featureFlag]) return false;
    return true;
  }

  function logout() {
    router.post('/logout');
  }

  const navSections = $derived([
    { label: null, items: [
      { href: '/dashboard', label: 'Dashboard', icon: LayoutDashboard, roles: ['*'], activeFor: ['/dashboard'] },
    ]},
    { label: 'Admissions', items: [
      { href: '/admin/applications', label: 'Applications', icon: FileText, roles: ['super_admin', 'registrar_administrator', 'staff'], activeFor: ['/admin/applications', '/admin/privacy-policies', '/admin/admission-slip-templates'] },
      { href: '/admin/exam-scheduling', label: 'Exam Scheduling', icon: Calendar, roles: ['super_admin', 'registrar_administrator'], activeFor: ['/admin/exam-scheduling'] },
    ]},
    { label: 'Assessment', items: [
      { href: '/proctor/my-sessions', label: 'My Sessions', icon: Calendar, roles: ['proctor'], activeFor: ['/proctor/my-sessions', '/proctor/sessions'] },
      { href: '/admin/exam-monitoring', label: 'Exam Monitoring', icon: Activity, roles: ['super_admin', 'test_administrator'], activeFor: ['/admin/exam-monitoring', '/admin/test-admin'] },
      { href: '/admin/grading', label: 'Grading', icon: GraduationCap, roles: ['super_admin', 'test_administrator'], activeFor: ['/admin/grading'] },
      { href: '/admin/release', label: 'Release', icon: SendHorizonal, roles: ['super_admin', 'test_administrator'], activeFor: ['/admin/release'], exclude: ['/admin/release/result-templates'] },
    ]},
    { label: 'Reports', items: [
      { href: '/admin/reports', label: 'Reports', icon: BarChart3, roles: ['super_admin', 'registrar_administrator', 'test_administrator'], activeFor: ['/admin/reports'] },
    ]},
    { label: 'System', collapsible: true, items: [
      { href: '/admin/setup', label: 'Setup', icon: Wrench, roles: ['super_admin', 'registrar_administrator', 'test_administrator'], activeFor: ['/admin/setup', '/admin/academic-years', '/admin/courses', '/admin/rooms', '/admin/aptitude-areas', '/admin/settings', '/admin/ai-companion', '/admin/admission-slip-templates', '/admin/privacy-policies', '/admin/release/result-templates'] },
      { href: '/admin/users', label: 'Users', icon: Users, roles: ['super_admin'], activeFor: ['/admin/users'] },
      { href: '/admin/logs', label: 'Audit Log', icon: ScrollText, roles: ['super_admin'], activeFor: ['/admin/logs'] },
    ]},
  ].map((section) => ({
    ...section,
    items: section.items.filter((item) => canSee(item.roles, item)),
  })).filter((section) => section.items.length > 0));

  function closeDropdowns() {
    userDropdownOpen = false;
    sidebarOpen = false;
  }

  function isNavActive(item) {
    const rawUrl = $page.url || '';
    // Strip query params and hash so ?search=foo&page=2 doesn't break matching
    const url = rawUrl.split('?')[0].split('#')[0];
    const href = item.href ?? '';

    // Check exclude list first
    if (item.exclude) {
      for (const prefix of item.exclude) {
        if (url === prefix || url === prefix + '/') return false;
        if (prefix !== '/' && url.startsWith(prefix + '/')) return false;
      }
    }

    if (url === href || url === href + '/') return true;
    if (href !== '/' && url.startsWith(href + '/')) return true;

    // Check activeFor prefixes for shared pages & child routes
    if (item.activeFor) {
      for (const prefix of item.activeFor) {
        if (url === prefix || url === prefix + '/') return true;
        if (prefix !== '/' && url.startsWith(prefix + '/')) return true;
      }
    }

    return false;
  }
</script>

<svelte:head>
  <title>{headTitle}</title>
</svelte:head>

<div class="h-dvh w-full max-w-[100vw] flex overflow-hidden">
  <!-- Sidebar backdrop (mobile) - only show when sidebar is open -->
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
    class="fixed inset-y-0 left-0 z-50 w-64 flex-shrink-0 glass-panel border-r flex flex-col transform transition-transform duration-200 ease-in-out -translate-x-full md:translate-x-0 {sidebarOpen ? 'translate-x-0' : ''} print:hidden"
  >
    <div class="flex flex-col h-full overflow-hidden">
      <div class="h-20 flex items-center px-6 border-b border-border/50 shrink-0">
        <Link href="/dashboard" class="flex items-center gap-3" onclick={closeDropdowns}>
          <img src="/img/securecat-logo.png" alt="SecureCAT" class="w-10 h-10 rounded-xl shadow-lg shadow-primary/30" />
          <span class="font-bold text-xl tracking-tight text-foreground">SecureCAT</span>
        </Link>
        <Button variant="ghost" size="icon" class="md:hidden ml-auto" onclick={() => (sidebarOpen = !sidebarOpen)} aria-label="Close menu">
          <Menu class="h-5 w-5" />
        </Button>
      </div>

      <nav class="flex-1 overflow-y-auto scrollbar-hide p-4 space-y-2">
        {#each navSections as section}
          <div class="space-y-2">
            {#if section.label}
              {#if section.collapsible}
                <button
                  type="button"
                  class="w-full flex items-center justify-between px-4 text-xs font-semibold text-muted-foreground uppercase tracking-wider mb-2 hover:text-foreground transition-colors"
                  onclick={() => { adminExpanded = !adminExpanded; }}
                >
                  {section.label}
                  <ChevronDown class="h-4 w-4 transition-transform {adminExpanded ? 'rotate-180' : ''}" />
                </button>
                {#if adminExpanded}
                  {#each section.items as item}
                    <Link
                      href={item.href}
                      class="nav-item flex items-center gap-3 px-4 py-3 rounded-xl font-medium transition-all duration-300 {isNavActive(item)
                        ? 'bg-primary text-primary-foreground shadow-md shadow-primary/40'
                        : 'text-foreground/80 hover:text-foreground hover:bg-accent hover:translate-x-1'}"
                      onclick={closeDropdowns}
                    >
                      <item.icon class="w-5 h-5 shrink-0" />
                      {item.label}
                    </Link>
                  {/each}
                {/if}
              {:else}
                <p class="px-4 text-xs font-semibold text-muted-foreground uppercase tracking-wider mb-2">{section.label}</p>
                {#each section.items as item}
                  <Link
                    href={item.href}
                    class="nav-item flex items-center gap-3 px-4 py-3 rounded-xl font-medium transition-all duration-300 {isNavActive(item)
                      ? 'bg-primary text-primary-foreground shadow-md shadow-primary/40'
                      : 'text-foreground/80 hover:text-foreground hover:bg-accent hover:translate-x-1'}"
                    onclick={closeDropdowns}
                  >
                    <item.icon class="w-5 h-5 shrink-0" />
                    {item.label}
                  </Link>
                {/each}
              {/if}
            {:else}
              {#each section.items as item}
                <Link
                  href={item.href}
                  class="nav-item flex items-center gap-3 px-4 py-3 rounded-xl font-medium transition-all duration-300 {isNavActive(item)
                    ? 'bg-primary text-primary-foreground shadow-md shadow-primary/40'
                    : 'text-foreground/80 hover:text-foreground hover:bg-accent hover:translate-x-1'}"
                  onclick={closeDropdowns}
                >
                  <item.icon class="w-5 h-5 shrink-0" />
                  {item.label}
                </Link>
              {/each}
            {/if}
          </div>
        {/each}
      </nav>
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
          <div class="w-10 h-10 rounded-full bg-primary/20 border-2 border-primary/20 flex items-center justify-center text-sm font-semibold text-foreground shrink-0">
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
            class="absolute left-0 right-0 bottom-full mb-1 rounded-xl border border-border bg-card-solid py-1 shadow-lg z-50"
            role="menu"
          >
            <Link
              href="/profile"
              class="block w-full px-4 py-2 text-left text-sm text-foreground hover:bg-accent transition-colors min-h-[44px] rounded-lg"
              role="menuitem"
              onclick={() => { userDropdownOpen = false; }}
            >
              Profile
            </Link>
            <div class="my-1 border-t border-border/50"></div>
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

  <div class="flex min-w-0 flex-1 flex-col md:pl-64 relative print:pl-0">
    <!-- Header -->
    <header class="shrink-0 z-20 h-20 glass-panel border-b border-l-0 flex items-center justify-between px-4 lg:px-8 relative print:hidden">
      <div class="flex items-center gap-4">
        <Button variant="ghost" size="icon" class="md:hidden" onclick={() => (sidebarOpen = true)} aria-label="Open menu">
          <Menu class="h-5 w-5" />
        </Button>
        {#if breadcrumbs.length === 0}
          <span class="text-2xl font-semibold tracking-tight text-foreground">SecureCAT</span>
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

          <!-- Mobile (< md): collapsed ••• › Current ▾ with dropdown -->
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
              <!-- Invisible backdrop: tapping anywhere outside the dropdown closes it -->
              <button
                type="button"
                class="fixed inset-0 z-10"
                aria-label="Close breadcrumb trail"
                onclick={() => (breadcrumbOpen = false)}
              ></button>

              <!-- Dropdown panel: appears directly below the sticky header bar -->
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
        <Button variant="ghost" size="icon" onclick={toggleTheme} aria-label={darkMode ? 'Switch to light mode' : 'Switch to dark mode'} class="rounded-full min-h-[44px] min-w-[44px]">
          <Sun class="h-5 w-5 dark:hidden" />
          <Moon class="h-5 w-5 hidden dark:block" />
        </Button>
        <NotificationDropdown />
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

<style>
  .scrollbar-hide {
    -ms-overflow-style: none;
    scrollbar-width: none;
  }
  .scrollbar-hide::-webkit-scrollbar {
    display: none;
  }
</style>
