<script>
  import PageHeaderBar from '@/Components/kit/PageHeaderBar.svelte';
  import StaffLayout from '@/Pages/Staff/StaffLayout.svelte';
  import BentoCard from '@/Components/kit/BentoCard.svelte';
  import ActiveFilterChips from '@/Components/kit/ActiveFilterChips.svelte';
  import SwitchableListView from '@/Components/kit/SwitchableListView.svelte';
  import BulkActionBar from '@/Components/kit/BulkActionBar.svelte';
  import SortDropdown from '@/Components/kit/SortDropdown.svelte';
  import SimplePagination from '@/Components/kit/SimplePagination.svelte';
  import AvatarBadge from '@/Components/kit/AvatarBadge.svelte';
  import { Badge } from '@/Components/ui/badge';
  import { Button } from '@/Components/ui/button';
  import { Checkbox } from '@/Components/ui/checkbox';
  import { Switch } from '@/Components/ui/switch';
  import * as Table from '@/Components/ui/table';
  import * as Select from '@/Components/ui/select';
  import { router } from '@inertiajs/svelte';
  import {
    Download,
    Search,
    Shield,
    X,
    Laptop,
    Clock3,
    Users,
    Radio,
    ArrowUp,
    ArrowDown,
    ArrowUpDown,
    Check,
    Copy,
    UserCheck,
    ClipboardCheck,
    MessagesSquare,
    SlidersHorizontal,
    Cpu,
    Eye,
    Layers,
    GitCommitVertical,
    Hash,
    FileText,
    Activity,
    RotateCcw,
    Sparkles
  } from 'lucide-svelte';

  let { audit = $bindable(), presence, can_export_audit = false } = $props();

  const breadcrumbs = [{ label: 'Audit Log' }];

  let activeTab = $state(
    typeof window !== 'undefined' && new URLSearchParams(window.location.search).get('tab') === 'presence' && presence
      ? 'presence'
      : 'audit'
  );

  let viewMode = $state('responsive');

  // Audit Filter state
  let filterScope = $state(audit.filters?.view_scope ?? (audit.can_supervise ? 'office' : 'own'));
  let filterCategory = $state(audit.filters?.category ?? '');
  let filterEvent = $state(audit.filters?.event ?? '');
  let filterDateFrom = $state(audit.filters?.date_from ?? '');
  let filterDateTo = $state(audit.filters?.date_to ?? '');
  let filterSearch = $state(audit.filters?.search ?? '');
  let filterSort = $state(audit.filters?.sort ?? 'created_at');
  let filterDir = $state(audit.filters?.dir ?? 'desc');
  // Search suggestion dropdown state
  let searchDropdownOpen = $state(false);
  let mobileFiltersOpen = $state(false);
  // Presence Filter state
  let presenceOffice = $state(presence?.filters?.office ?? 'all');
  let presenceState = $state(presence?.filters?.state ?? 'active');
  let presenceSearch = $state(presence?.filters?.search ?? '');

  // Selection & Modal state
  let selectedLogIds = $state([]);
  let selectedLog = $state(null);
  let modalTab = $state('overview');
  let copiedJson = $state(false);
  let copiedHash = $state(null);
  let copiedIds = $state(false);

  // Centralized domain metadata (avoids code smell of repeated switches)
  const DOMAIN_CONFIG = {
    admissions: {
      label: 'Admissions',
      icon: UserCheck,
      badgeClass: 'border-emerald-500/30 bg-emerald-500/10 text-emerald-700 dark:text-emerald-300',
    },
    assessment: {
      label: 'Assessment & OMR',
      icon: ClipboardCheck,
      badgeClass: 'border-indigo-500/30 bg-indigo-500/10 text-indigo-700 dark:text-indigo-300',
    },
    consultation: {
      label: 'Consultation & Inquiry',
      icon: MessagesSquare,
      badgeClass: 'border-purple-500/30 bg-purple-500/10 text-purple-700 dark:text-purple-300',
    },
    setup: {
      label: 'Institutional Setup',
      icon: SlidersHorizontal,
      badgeClass: 'border-amber-500/30 bg-amber-500/10 text-amber-700 dark:text-amber-300',
    },
    security: {
      label: 'Security & Access',
      icon: Shield,
      badgeClass: 'border-rose-500/30 bg-rose-500/10 text-rose-700 dark:text-rose-300',
    },
    system: {
      label: 'System & Operations',
      icon: Cpu,
      badgeClass: 'border-slate-500/30 bg-slate-500/10 text-slate-700 dark:text-slate-300',
    },
  };

  function getDomainMeta(domain) {
    return DOMAIN_CONFIG[domain] ?? DOMAIN_CONFIG.system;
  }

  const visibleLogIds = $derived(audit.logs.map((log) => log.id));
  const allSelected = $derived(
    visibleLogIds.length > 0 && visibleLogIds.every((id) => selectedLogIds.includes(id))
  );
  const someSelected = $derived(
    selectedLogIds.some((id) => visibleLogIds.includes(id)) && !allSelected
  );

  let resultIdentity = $state('');
  $effect(() => {
    const nextIdentity = JSON.stringify({
      ids: visibleLogIds,
      filters: audit.filters,
      pagination: audit.pagination,
      as_of: audit.as_of,
    });
    if (resultIdentity && resultIdentity !== nextIdentity) {
      selectedLogIds = [];
      copiedIds = false;
      selectedLog = null;
    }
    resultIdentity = nextIdentity;
  });

  const sortOptions = [
    { value: 'created_at', label: 'Timestamp' },
    { value: 'actor', label: 'Actor' },
    { value: 'event_type', label: 'Event Key' },
  ];

  const activeChips = $derived.by(() => {
    const chips = [];
    if (filterScope === 'own' && audit.can_supervise) {
      chips.push({ id: 'view_scope', label: 'Scope: My Actions Only' });
    }
    if (filterCategory) {
      const catLabel = audit.categories?.[filterCategory]?.label ?? filterCategory;
      chips.push({ id: 'category', label: `Domain: ${catLabel}` });
    }
    if (filterEvent) {
      chips.push({ id: 'event', label: `Event: ${filterEvent}` });
    }
    if (filterSearch) {
      chips.push({ id: 'search', label: `Search: "${filterSearch}"` });
    }
    if (filterDateFrom) {
      chips.push({ id: 'date_from', label: `From: ${filterDateFrom}` });
    }
    if (filterDateTo) {
      chips.push({ id: 'date_to', label: `To: ${filterDateTo}` });
    }
    return chips;
  });
  const activeFilterCount = $derived(activeChips.length);

  // Client-side quick event suggestions for dropdown
  const filteredEventSuggestions = $derived.by(() => {
    const q = (filterSearch ?? '').trim().toLowerCase();
    const all = audit.events ?? [];
    if (!q) {
      return all.slice(0, 6);
    }
    return all.filter((ev) => ev.toLowerCase().includes(q)).slice(0, 8);
  });

  function applyAuditFilters(overrides = {}) {
    searchDropdownOpen = false;
    router.get('/staff/logs', {
      tab: 'audit',
      view_scope: overrides.view_scope ?? (filterScope || undefined),
      category: overrides.category ?? (filterCategory || undefined),
      event: overrides.event ?? (filterEvent || undefined),
      date_from: overrides.date_from ?? (filterDateFrom || undefined),
      date_to: overrides.date_to ?? (filterDateTo || undefined),
      search: overrides.search ?? (filterSearch || undefined),
      sort: overrides.sort ?? (filterSort || undefined),
      dir: overrides.dir ?? (filterDir || undefined),
    }, { preserveState: true, preserveScroll: true });
  }

  function applyPresenceFilters() {
    router.get('/staff/logs', {
      tab: 'presence',
      office: presenceOffice === 'all' ? undefined : presenceOffice,
      state: presenceState,
      search: presenceSearch || undefined,
    }, { preserveState: true, preserveScroll: true });
  }

  function toggleColumnSort(column) {
    if (filterSort === column) {
      filterDir = filterDir === 'asc' ? 'desc' : 'asc';
    } else {
      filterSort = column;
      filterDir = column === 'created_at' ? 'desc' : 'asc';
    }
    applyAuditFilters();
  }

  function selectSuggestionEvent(eventName) {
    filterEvent = eventName;
    searchDropdownOpen = false;
    applyAuditFilters({ event: eventName });
  }

  function selectSuggestionDomain(domainKey) {
    filterCategory = domainKey;
    searchDropdownOpen = false;
    applyAuditFilters({ category: domainKey });
  }

  function removeChip(id) {
    if (id === 'view_scope') filterScope = 'office';
    if (id === 'category') filterCategory = '';
    if (id === 'event') filterEvent = '';
    if (id === 'search') filterSearch = '';
    if (id === 'date_from') filterDateFrom = '';
    if (id === 'date_to') filterDateTo = '';
    applyAuditFilters();
  }

  function clearAllFilters() {
    filterScope = audit.can_supervise ? 'office' : 'own';
    filterCategory = '';
    filterEvent = '';
    filterSearch = '';
    filterDateFrom = '';
    filterDateTo = '';
    filterSort = 'created_at';
    filterDir = 'desc';
    applyAuditFilters();
  }

  function toggleAllOnPage(checked) {
    if (checked) {
      selectedLogIds = audit.logs.map((l) => l.id);
    } else {
      selectedLogIds = [];
    }
  }

  function toggleRow(id) {
    if (selectedLogIds.includes(id)) {
      selectedLogIds = selectedLogIds.filter((x) => x !== id);
    } else {
      selectedLogIds = [...selectedLogIds, id];
    }
  }

  function copySelectedIds() {
    if (selectedLogIds.length === 0) return;
    navigator.clipboard.writeText(selectedLogIds.join(', '));
    copiedIds = true;
    setTimeout(() => {
      copiedIds = false;
    }, 2000);
  }

  function downloadCsv() {
    const params = new URLSearchParams();
    if (filterScope) params.set('view_scope', filterScope);
    if (filterCategory) params.set('category', filterCategory);
    if (filterEvent) params.set('event', filterEvent);
    if (filterDateFrom) params.set('date_from', filterDateFrom);
    if (filterDateTo) params.set('date_to', filterDateTo);
    if (filterSearch) params.set('search', filterSearch);
    if (filterSort) params.set('sort', filterSort);
    if (filterDir) params.set('dir', filterDir);
    window.location.href = `/staff/logs/export?${params.toString()}`;
  }

  const selectedExportUrl = $derived.by(() => {
    const filters = audit.filters;
    const params = new URLSearchParams({
      ids: selectedLogIds.join(','),
      view_scope: filters.view_scope ?? filterScope ?? '',
      category: filters.category ?? '',
      event: filters.event ?? '',
      date_from: filters.date_from ?? '',
      date_to: filters.date_to ?? '',
      search: filters.search ?? '',
      sort: filters.sort ?? 'created_at',
      dir: filters.dir ?? 'desc',
    });
    return `/staff/logs/export?${params.toString()}`;
  });

  function portal(node) {
    document.body.appendChild(node);
    return { destroy: () => node.remove() };
  }

  function openInspector(log) {
    selectedLog = log;
    modalTab = log.diff && log.diff.length > 0 ? 'diff' : 'overview';
    copiedJson = false;
    copiedHash = null;
  }

  function copyJsonPayload() {
    if (!selectedLog) return;
    navigator.clipboard.writeText(JSON.stringify(selectedLog.payload, null, 2));
    copiedJson = true;
    setTimeout(() => {
      copiedJson = false;
    }, 2000);
  }

  function copyHashString(text, key) {
    if (!text) return;
    navigator.clipboard.writeText(text);
    copiedHash = key;
    setTimeout(() => {
      copiedHash = null;
    }, 2000);
  }

  function formatDate(value) {
    if (!value) return '—';
    const parsed = new Date(value);
    return Number.isNaN(parsed.getTime()) ? '—' : parsed.toLocaleString();
  }

  function statusClass(status) {
    return status === 'online'
      ? 'border-emerald-500/30 bg-emerald-500/10 text-emerald-700 dark:text-emerald-300'
      : status === 'recent'
        ? 'border-amber-500/30 bg-amber-500/10 text-amber-800 dark:text-amber-300'
        : 'border-border bg-muted text-muted-foreground';
  }

  function diffKindClass(kind) {
    switch (kind) {
      case 'added':
        return 'border-emerald-500/30 bg-emerald-500/10 text-emerald-700 dark:text-emerald-300';
      case 'removed':
        return 'border-rose-500/30 bg-rose-500/10 text-rose-700 dark:text-rose-300';
      default:
        return 'border-amber-500/30 bg-amber-500/10 text-amber-700 dark:text-amber-300';
    }
  }
</script>

<svelte:head>
  <title>Staff Presence &amp; Audit Logs &bull; NEXIAM</title>
</svelte:head>

<StaffLayout {breadcrumbs}>
  <div class="min-w-0 space-y-6 p-4 sm:p-6 lg:p-8">
    <PageHeaderBar
      title={audit.can_supervise ? "Audit & Staff Presence" : "My Activity Trail"}
      description={audit.can_supervise ? "Review scoped security events, stored audit hashes, and staff workstation activity." : "Review your personal activity trail and recorded actions across the platform."}
    >
      {#snippet actions()}
        {#if can_export_audit}
          <Button
            type="button"
            variant="outline"
            size="sm"
            onclick={downloadCsv}
            class="h-9 gap-1.5 px-3 text-xs font-semibold"
          >
            <Download class="size-3.5" />
            Export CSV ({audit.pagination?.total ?? audit.total_filtered ?? audit.logs.length})
          </Button>
        {/if}
      {/snippet}
    </PageHeaderBar>

    {#if presence}
    <!-- Telemetry Cards -->
    <section aria-label="Active Staff Presence & Devices" class="grid grid-cols-1 gap-3 sm:grid-cols-3">
      <BentoCard class="flex items-center gap-3 p-4">
        <span class="rounded-xl bg-emerald-500/10 p-2 text-emerald-700"><Radio class="size-5" /></span>
        <div><p class="text-xs text-muted-foreground">Online Now</p><p class="text-2xl font-bold">{presence.summary?.online_staff ?? 0}</p></div>
      </BentoCard>
      <BentoCard class="flex items-center gap-3 p-4">
        <span class="rounded-xl bg-amber-500/10 p-2 text-amber-700"><Clock3 class="size-5" /></span>
        <div><p class="text-xs text-muted-foreground">Recent (30m)</p><p class="text-2xl font-bold">{presence.summary?.recent_staff ?? 0}</p></div>
      </BentoCard>
      <BentoCard class="flex items-center gap-3 p-4">
        <span class="rounded-xl bg-primary/10 p-2 text-primary"><Users class="size-5" /></span>
        <div class="min-w-0"><p class="text-xs text-muted-foreground">Active Sessions</p><p class="text-2xl font-bold">{presence.summary?.active_sessions ?? 0}</p><p class="truncate text-[11px] text-muted-foreground">{presence.scope?.label ?? ''}</p></div>
      </BentoCard>
    </section>

    <!-- Top View Tabs -->
    <div class="flex flex-wrap gap-2 border-b border-border" role="tablist" aria-label="Audit and presence views">
      <button
        type="button"
        role="tab"
        aria-selected={activeTab === 'audit'}
        onclick={() => (activeTab = 'audit')}
        class={`min-h-11 border-b-2 px-4 text-sm font-semibold focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring ${activeTab === 'audit' ? 'border-primary text-primary' : 'border-transparent text-muted-foreground hover:text-foreground'}`}
      >
        Audit Event Trail
      </button>
      <button
        type="button"
        role="tab"
        aria-selected={activeTab === 'presence'}
        onclick={() => (activeTab = 'presence')}
        class={`min-h-11 border-b-2 px-4 text-sm font-semibold focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring ${activeTab === 'presence' ? 'border-primary text-primary' : 'border-transparent text-muted-foreground hover:text-foreground'}`}
      >
        Live Staff Presence &amp; Devices
      </button>
    </div>
    {/if}
    {#if activeTab === 'presence' && presence}
      <!-- PRESENCE TAB (Preserved) -->
      <section role="tabpanel" aria-label="Live Staff Presence & Devices" class="space-y-4">
        <div class="sticky top-0 z-20 space-y-3 bg-background/80 py-2 backdrop-blur-md">
          <div class="flex flex-col justify-between gap-2 sm:flex-row sm:items-center">
            <div>
              <h2 class="text-lg font-bold">Staff sessions</h2>
              <p class="text-xs text-muted-foreground">Presence is inferred from the last eligible staff activity. Polling does not count as activity.</p>
            </div>
            <span class="w-fit rounded-full border border-border bg-muted px-3 py-1 text-xs font-semibold">{presence.scope.label}</span>
          </div>
          {#if presence.scope.can_filter_offices}
            <div class="flex flex-wrap gap-2" aria-label="Filter by office">
              {#each presence.office_options as option (option.value)}
                <button
                  type="button"
                  aria-pressed={presenceOffice === option.value}
                  onclick={() => { presenceOffice = option.value; applyPresenceFilters(); }}
                  class={`min-h-11 rounded-full border px-4 text-sm font-semibold shadow-xs focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring ${presenceOffice === option.value ? 'border-primary bg-primary text-primary-foreground' : 'border-input bg-background text-foreground hover:bg-muted dark:bg-input/30'}`}
                >
                  {option.label}
                </button>
              {/each}
            </div>
          {/if}
          <div class="flex flex-col gap-3 sm:flex-row sm:items-end">
            <label class="min-w-0 flex-1 text-xs font-semibold text-muted-foreground">Search staff
              <span class="mt-1 flex h-11 items-center gap-2 rounded-xl border border-input bg-background px-3 text-foreground shadow-xs focus-within:ring-2 focus-within:ring-ring dark:bg-input/30">
                <Search class="size-4 shrink-0" />
                <input
                  bind:value={presenceSearch}
                  onkeydown={(event) => event.key === 'Enter' && applyPresenceFilters()}
                  class="min-w-0 flex-1 bg-transparent text-sm outline-none"
                  placeholder="Name or email"
                />
              </span>
            </label>
            <div class="flex flex-wrap gap-2" aria-label="Filter by presence status">
              {#each [{value:'active',label:'All Active'},{value:'online',label:'Online Now'},{value:'recent',label:'Recent'},{value:'offline',label:'Offline'}] as option (option.value)}
                <button
                  type="button"
                  aria-pressed={presenceState === option.value}
                  onclick={() => { presenceState = option.value; applyPresenceFilters(); }}
                  class={`min-h-11 rounded-full border px-3 text-xs font-semibold shadow-xs focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring ${presenceState === option.value ? 'border-primary bg-primary text-primary-foreground' : 'border-input bg-background text-foreground hover:bg-muted dark:bg-input/30'}`}
                >
                  {option.label}
                </button>
              {/each}
            </div>
          </div>
        </div>

          <table class="hidden w-full text-left text-sm md:table">
            <thead class="border-b border-border bg-muted/40 text-xs font-bold text-muted-foreground">
              <tr class="border-b border-border/40 transition-colors hover:bg-muted/30">
                <th class="p-3">Staff Member</th>
                <th class="p-3">Office</th>
                <th class="p-3">Device</th>
                <th class="p-3">Current Workspace</th>
                <th class="p-3">Last Active</th>
                <th class="p-3">Signed In</th>
                <th class="p-3">IP</th>
                <th class="p-3">Status</th>
              </tr>
            </thead>
            <tbody class="divide-y divide-border">
              {#each presence.sessions as session (session.id)}
                <tr class="border-b border-border/40 align-top transition-colors hover:bg-muted/30">
                  <td class="p-3 font-semibold">
                    {session.user.name}
                    {#if session.is_current_session}
                      <span class="ml-1 text-[10px] font-normal text-muted-foreground">(You)</span>
                    {/if}
                  </td>
                  <td class="p-3"><span class="rounded-full border border-border px-2 py-1 text-xs">{session.office}</span></td>
                  <td class="p-3">
                    <span class="inline-flex items-center gap-1"><Laptop class="size-4" />{session.device_type}</span>
                    <span class="block text-xs text-muted-foreground">{session.browser} · {session.os}</span>
                  </td>
                  <td class="p-3">{session.workspace_area ?? '—'}</td>
                  <td class="p-3">{formatDate(session.last_active_at)}</td>
                  <td class="p-3">{session.logged_in_at ? formatDate(session.logged_in_at) : `Tracking started ${formatDate(session.first_observed_at)}`}</td>
                  <td class="p-3 font-mono text-xs">{session.ip_address ?? '—'}</td>
                  <td class="p-3"><span class={`rounded-full border px-2.5 py-1 text-xs font-semibold capitalize ${statusClass(session.status)}`}>{session.status}</span></td>
                </tr>
              {:else}
                <tr><td colspan="8" class="p-10 text-center text-muted-foreground">No staff sessions match these filters.</td></tr>
              {/each}
            </tbody>
          </table>
        <div class="space-y-3 md:hidden">
          {#each presence.sessions as session (session.id)}
            <BentoCard class="space-y-3 p-4">
              <div class="flex items-start justify-between gap-3">
                <div class="min-w-0">
                  <p class="font-bold">{session.user.name}{#if session.is_current_session}<span class="ml-1 text-xs font-normal text-muted-foreground">(You)</span>{/if}</p>
                  <p class="text-xs text-muted-foreground">{session.office} · {session.device_type}</p>
                </div>
                <span class={`shrink-0 rounded-full border px-2.5 py-1 text-xs font-bold capitalize ${statusClass(session.status)}`}>{session.status}</span>
              </div>
              <p class="text-sm">{session.browser} · {session.os}</p>
              <div class="grid grid-cols-2 gap-3 text-xs">
                <div><p class="text-muted-foreground">Workspace</p><p class="mt-1 font-semibold">{session.workspace_area ?? '—'}</p></div>
                <div><p class="text-muted-foreground">IP</p><p class="mt-1 font-mono">{session.ip_address ?? '—'}</p></div>
                <div><p class="text-muted-foreground">Last Active</p><p class="mt-1">{formatDate(session.last_active_at)}</p></div>
                <div><p class="text-muted-foreground">Signed In</p><p class="mt-1">{session.logged_in_at ? formatDate(session.logged_in_at) : `Tracking started ${formatDate(session.first_observed_at)}`}</p></div>
              </div>
            </BentoCard>
          {:else}
            <BentoCard class="p-8 text-center text-sm text-muted-foreground">No staff sessions match these filters.</BentoCard>
          {/each}
        </div>
      </section>
    {:else}
      <!-- AUDIT EVENT TRAIL TAB (Standard UI Kit Aligned) -->
      <section role="tabpanel" aria-label="Audit Event Trail" class="space-y-4">
        <div class="sticky top-0 z-20 space-y-3 bg-background/80 py-2 backdrop-blur-md">
        <div class="relative w-full">
            <label for="audit-search" class="sr-only">Search Audit Trail</label>
            <div class="relative flex items-center">
              <Search class="pointer-events-none absolute left-3.5 size-4 text-muted-foreground" aria-hidden="true" />
              <input
                id="audit-search"
                type="search"
                bind:value={filterSearch}
                onfocus={() => (searchDropdownOpen = true)}
                oninput={() => (searchDropdownOpen = true)}
                onkeydown={(e) => {
                  if (e.key === 'Enter') {
                    applyAuditFilters();
                    searchDropdownOpen = false;
                  } else if (e.key === 'Escape') {
                    searchDropdownOpen = false;
                  }
                }}
                placeholder="Search actor name, email, event key, ref code, attempt ID…"
                class="h-11 min-h-[44px] w-full rounded-lg border border-input bg-background pl-10 pr-24 text-sm text-foreground shadow-xs outline-none transition-colors focus:border-primary focus:ring-2 focus:ring-ring"
                role="combobox"
                aria-label="Search audit events"
                aria-autocomplete="list"
                aria-expanded={searchDropdownOpen}
                aria-controls="audit-search-suggestions"
              />
              <div class="absolute right-2 flex items-center gap-1">
                {#if filterSearch}
                  <button
                    type="button"
                    onclick={() => {
                      filterSearch = '';
                      applyAuditFilters({ search: '' });
                    }}
                    class="grid size-7 place-items-center rounded-md text-muted-foreground transition-colors hover:bg-muted hover:text-foreground"
                    aria-label="Clear search"
                  >
                    <X class="size-4" />
                  </button>
                {/if}
                <Button size="sm" class="h-8 min-h-[32px] px-3 text-xs font-semibold" onclick={() => {
                  applyAuditFilters();
                  searchDropdownOpen = false;
                }}>
                  Search
                </Button>
              </div>
            </div>

            {#if searchDropdownOpen}
              <div
                role="presentation"
                class="fixed inset-0 z-30"
                onclick={() => (searchDropdownOpen = false)}
              ></div>
              <div
                id="audit-search-suggestions"
                class="absolute left-0 top-full z-50 mt-2 w-full max-w-2xl rounded-xl border border-border bg-card p-4 shadow-lg"
                role="listbox"
                aria-label="Search suggestions"
              >
                <div class="flex items-center justify-between border-b border-border/50 pb-2">
                  <span class="flex items-center gap-1.5 text-[11px] font-bold uppercase tracking-wider text-muted-foreground">
                    <Sparkles class="size-3.5 text-primary" />
                    Quick Shortcuts &amp; Event Matches
                  </span>
                  <button
                    type="button"
                    class="rounded-md p-1 text-muted-foreground hover:bg-muted hover:text-foreground"
                    onclick={() => (searchDropdownOpen = false)}
                    aria-label="Close search suggestions"
                  >
                    <X class="size-4" />
                  </button>
                </div>

                <div>
                  <div class="mb-1.5 text-[10px] font-semibold uppercase tracking-wider text-muted-foreground">Filter by Domain Category</div>
                  <div class="flex flex-wrap gap-2">
                    {#each Object.entries(audit.categories ?? {}) as [key, cat]}
                      {@const meta = getDomainMeta(key)}
                      <button
                        type="button"
                        class="inline-flex min-h-[36px] items-center gap-1.5 rounded-lg border border-border bg-muted/40 px-3 py-1.5 text-xs font-semibold transition-colors hover:border-primary hover:bg-primary/10 hover:text-primary"
                        onclick={() => selectSuggestionDomain(key)}
                      >
                        <meta.icon class="size-3.5" />
                        <span>{cat.label}</span>
                      </button>
                    {/each}
                  </div>
                </div>

                {#if filteredEventSuggestions.length > 0}
                  <div class="border-t border-border/40 pt-2">
                    <div class="mb-1.5 text-[10px] font-semibold uppercase tracking-wider text-muted-foreground">Matching Event Keys ({filteredEventSuggestions.length})</div>
                    <div class="max-h-52 overflow-y-auto space-y-1">
                      {#each filteredEventSuggestions as ev}
                        <button
                          type="button"
                          class="flex w-full items-center justify-between rounded-lg px-3 py-2 text-left text-xs font-mono transition-colors hover:bg-muted"
                          onclick={() => selectSuggestionEvent(ev)}
                        >
                          <span class="truncate font-semibold text-foreground">{ev}</span>
                          <span class="ml-3 shrink-0 font-sans text-[11px] font-medium text-primary">Filter &rarr;</span>
                        </button>
                      {/each}
                    </div>
                  </div>
                {/if}
              </div>
            {/if}
          </div>

          <div class="flex flex-wrap items-center justify-between gap-2 md:hidden">
            <Button
              type="button"
              variant="outline"
              size="sm"
              class="gap-1.5"
              aria-expanded={mobileFiltersOpen}
              aria-controls="audit-mobile-filters"
              onclick={() => (mobileFiltersOpen = !mobileFiltersOpen)}
            >
              Filters ({activeFilterCount}) ▾
            </Button>
            <SortDropdown
              options={sortOptions}
              sort={filterSort}
              dir={filterDir}
              onchange={({ sort, dir }) => {
                filterSort = sort;
                filterDir = dir;
                applyAuditFilters();
              }}
            />
          </div>

          <div
            id="audit-mobile-filters"
            class={`${mobileFiltersOpen ? 'flex flex-col' : 'hidden'} gap-3 rounded-lg border border-border/60 bg-muted/20 p-3 md:flex md:flex-row md:flex-wrap md:items-end md:rounded-none md:border-0 md:bg-transparent md:p-0`}
          >
            {#if audit.can_supervise}
              <div class="flex min-w-0 flex-col gap-1">
                <span class="text-xs font-medium text-muted-foreground">Scope</span>
                <label
                  for="filter-only-me"
                  class="flex h-10 min-h-[44px] md:min-h-10 cursor-pointer select-none items-center gap-2.5 rounded-md border border-input bg-background px-3 transition-colors hover:bg-muted/40"
                >
                  <Switch
                    id="filter-only-me"
                    checked={filterScope === 'own'}
                    onCheckedChange={(checked) => {
                      filterScope = checked ? 'own' : 'office';
                      applyAuditFilters({ view_scope: filterScope });
                    }}
                    aria-label="Filter to only my actions"
                  />
                  <span class="text-xs font-medium text-foreground whitespace-nowrap">
                    {filterScope === 'own' ? 'Only my actions' : 'All office activity'}
                  </span>
                </label>
              </div>
            {/if}

            <label for="filter-category" class="flex min-w-[10rem] flex-1 flex-col gap-1">
              <span class="text-xs font-medium text-muted-foreground">Category</span>
              <Select.Root
                type="single"
                value={filterCategory || 'all'}
                onValueChange={(value) => {
                  filterCategory = value === 'all' ? '' : value ?? '';
                  applyAuditFilters();
                }}
              >
                <Select.Trigger id="filter-category" class="h-10 min-h-10 w-full truncate text-sm">
                  {audit.categories?.[filterCategory]?.label ?? 'All Categories'}
                </Select.Trigger>
                <Select.Content>
                  <Select.Item value="all">All Categories</Select.Item>
                  {#each Object.entries(audit.categories ?? {}) as [key, cat]}
                    <Select.Item value={key}>{cat.label}</Select.Item>
                  {/each}
                </Select.Content>
              </Select.Root>
            </label>

            <label for="filter-event" class="flex min-w-[12rem] flex-1 flex-col gap-1">
              <span class="text-xs font-medium text-muted-foreground">Event Type</span>
              <Select.Root
                type="single"
                value={filterEvent || 'all'}
                onValueChange={(value) => {
                  filterEvent = value === 'all' ? '' : value ?? '';
                  applyAuditFilters();
                }}
              >
                <Select.Trigger id="filter-event" class="h-10 min-h-10 w-full truncate text-sm">
                  {filterEvent || `All Event Types (${audit.events?.length ?? 0})`}
                </Select.Trigger>
                <Select.Content>
                  <Select.Item value="all">All Event Types ({audit.events?.length ?? 0})</Select.Item>
                  {#each audit.events ?? [] as ev}
                    <Select.Item value={ev}>{ev}</Select.Item>
                  {/each}
                </Select.Content>
              </Select.Root>
            </label>

            <label for="filter-date-from" class="flex w-full md:w-36 flex-col gap-1">
              <span class="text-xs font-medium text-muted-foreground">Date From</span>
              <input
                id="filter-date-from"
                type="date"
                bind:value={filterDateFrom}
                onchange={() => applyAuditFilters()}
                class="h-10 min-h-[44px] w-full rounded-md border border-input bg-background px-2.5 text-xs font-medium text-foreground focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring md:min-h-10"
              />
            </label>

            <label for="filter-date-to" class="flex w-full md:w-36 flex-col gap-1">
              <span class="text-xs font-medium text-muted-foreground">Date To</span>
              <input
                id="filter-date-to"
                type="date"
                bind:value={filterDateTo}
                onchange={() => applyAuditFilters()}
                class="h-10 min-h-[44px] w-full rounded-md border border-input bg-background px-2.5 text-xs font-medium text-foreground focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring md:min-h-10"
              />
            </label>

            <div class="flex items-center justify-between gap-2 md:ml-auto md:justify-end">
              <div class="hidden md:block">
                <SortDropdown
                  options={sortOptions}
                  sort={filterSort}
                  dir={filterDir}
                  onchange={({ sort, dir }) => {
                    filterSort = sort;
                    filterDir = dir;
                    applyAuditFilters();
                  }}
                />
              </div>
              <Button
                type="button"
                variant="ghost"
                size="sm"
                class="min-h-10 px-2.5 text-xs text-muted-foreground"
                onclick={clearAllFilters}
                title="Reset all filters"
              >
                <RotateCcw class="mr-1 size-3.5" /> Reset
              </Button>
            </div>
          </div>

          {#if activeChips.length > 0}
            <ActiveFilterChips
              chips={activeChips}
              onRemove={removeChip}
              onClearAll={clearAllFilters}
            />
          {/if}
        </div>

        <!-- Bulk Selection Action Bar -->
        <BulkActionBar
          count={selectedLogIds.length}
          hint="Audit events selected on current page"
          onClear={() => (selectedLogIds = [])}
          class="sticky bottom-4 z-20 shadow-lg"
        >
          {#snippet actions()}
            <Button
              size="sm"
              variant="outline"
              class="gap-1.5 min-h-[36px] text-xs font-medium"
              onclick={copySelectedIds}
            >
              {#if copiedIds}
                <Check class="size-3.5 text-emerald-600" />
                <span>Copied IDs</span>
              {:else}
                <Copy class="size-3.5" />
                <span>Copy IDs</span>
              {/if}
            </Button>
            {#if can_export_audit}
              <Button
                size="sm"
                class="gap-1.5 min-h-[36px] text-xs font-medium"
                href={selectedExportUrl}
              >
                <Download class="size-3.5" />
                <span>Export Selected (CSV)</span>
              </Button>
            {/if}
          {/snippet}
        </BulkActionBar>

        <!-- Switchable List View (Table & Cards using Table.Root) -->
        <SwitchableListView bind:viewMode cardsInset={false}>
          {#snippet table()}
            <Table.Root class="w-full min-w-[850px] text-sm">
              <Table.Header class="bg-muted/40 border-b border-border">
                <Table.Row class="border-b border-border/40 hover:bg-muted/30 transition-colors">
                  <Table.Head class="w-10 px-3 py-3 text-center align-middle">
                    <Checkbox
                      checked={allSelected}
                      indeterminate={someSelected}
                      onCheckedChange={(val) => toggleAllOnPage(!!val)}
                      aria-label="Select all events on page"
                    />
                  </Table.Head>
                  <Table.Head
                    class="px-4 py-3 text-left align-middle"
                    aria-sort={filterSort === 'created_at' ? (filterDir === 'asc' ? 'ascending' : 'descending') : 'none'}
                  >
                    <button
                      type="button"
                      onclick={() => toggleColumnSort('created_at')}
                      class="inline-flex min-h-[44px] items-center gap-1.5 rounded-sm font-bold transition-colors hover:text-primary focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring"
                    >
                      <span>Timestamp</span>
                      {#if filterSort === 'created_at'}
                        {#if filterDir === 'asc'}
                          <ArrowUp class="size-3.5 text-primary" aria-hidden="true" />
                        {:else}
                          <ArrowDown class="size-3.5 text-primary" aria-hidden="true" />
                        {/if}
                      {:else}
                        <ArrowUpDown class="size-3.5 opacity-40" aria-hidden="true" />
                      {/if}
                    </button>
                  </Table.Head>
                  <Table.Head
                    class="px-4 py-3 text-left align-middle"
                    aria-sort={filterSort === 'actor' ? (filterDir === 'asc' ? 'ascending' : 'descending') : 'none'}
                  >
                    <button
                      type="button"
                      onclick={() => toggleColumnSort('actor')}
                      class="inline-flex min-h-[44px] items-center gap-1.5 rounded-sm font-bold transition-colors hover:text-primary focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring"
                    >
                      <span>Actor</span>
                      {#if filterSort === 'actor'}
                        {#if filterDir === 'asc'}
                          <ArrowUp class="size-3.5 text-primary" aria-hidden="true" />
                        {:else}
                          <ArrowDown class="size-3.5 text-primary" aria-hidden="true" />
                        {/if}
                      {:else}
                        <ArrowUpDown class="size-3.5 opacity-40" aria-hidden="true" />
                      {/if}
                    </button>
                  </Table.Head>
                  <Table.Head
                    class="px-4 py-3 text-left align-middle"
                    aria-sort={filterSort === 'event_type' ? (filterDir === 'asc' ? 'ascending' : 'descending') : 'none'}
                  >
                    <button
                      type="button"
                      onclick={() => toggleColumnSort('event_type')}
                      class="inline-flex min-h-[44px] items-center gap-1.5 rounded-sm font-bold transition-colors hover:text-primary focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring"
                    >
                      <span>Domain &amp; Action</span>
                      {#if filterSort === 'event_type'}
                        {#if filterDir === 'asc'}
                          <ArrowUp class="size-3.5 text-primary" aria-hidden="true" />
                        {:else}
                          <ArrowDown class="size-3.5 text-primary" aria-hidden="true" />
                        {/if}
                      {:else}
                        <ArrowUpDown class="size-3.5 opacity-40" aria-hidden="true" />
                      {/if}
                    </button>
                  </Table.Head>
                  <Table.Head class="px-4 py-3 text-left align-middle">Summary / Context</Table.Head>
                  <Table.Head class="w-px whitespace-nowrap px-4 py-3 text-center align-middle">Inspect</Table.Head>
                </Table.Row>
              </Table.Header>
              <Table.Body>
                {#each audit.logs as log (log.id)}
                  {@const meta = getDomainMeta(log.domain)}
                  <Table.Row class="border-b border-border/40 transition-colors hover:bg-muted/30">
                    <Table.Cell class="w-10 px-3 py-3 text-center align-middle">
                      <Checkbox
                        checked={selectedLogIds.includes(log.id)}
                        onCheckedChange={() => toggleRow(log.id)}
                        aria-label={`Select event ${log.title || log.event_type}`}
                      />
                    </Table.Cell>
                    <Table.Cell class="px-4 py-3 text-left align-middle whitespace-nowrap">
                      <div class="font-mono text-xs font-semibold text-foreground">{log.created_at_human}</div>
                      <div class="text-[11px] text-muted-foreground">{log.created_at_relative}</div>
                    </Table.Cell>
                    <Table.Cell class="px-4 py-3 text-left align-middle">
                      <div class="flex items-center gap-2.5">
                        <AvatarBadge
                          name={log.actor_info?.name ?? log.actor}
                          role={log.actor_info?.role_label ?? ''}
                          avatarType={log.actor_info?.avatar?.type ?? 'preset'}
                          avatarPreset={log.actor_info?.avatar?.preset ?? 'records'}
                          size="sm"
                        />
                        <div class="min-w-0">
                          <p class="font-semibold text-foreground text-xs leading-tight truncate">{log.actor_info?.name ?? log.actor}</p>
                          <p class="text-[10px] text-muted-foreground leading-tight truncate">
                            {#if log.actor_info?.office}
                              <span class="capitalize">{log.actor_info.office}</span> ·
                            {/if}
                            <span class="font-mono">ID: {log.actor_token ?? log.actor_info?.id ?? '—'}</span>
                          </p>
                        </div>
                      </div>
                    </Table.Cell>
                    <Table.Cell class="px-4 py-3 text-left align-middle">
                      <div class="flex flex-col gap-1 items-start">
                        <span class={`inline-flex items-center gap-1 rounded-full border px-2 py-0.5 text-[10px] font-bold capitalize ${meta.badgeClass}`}>
                          <meta.icon class="size-3" />
                          <span>{meta.label}</span>
                        </span>
                        <span class="font-semibold text-xs text-foreground">{log.title}</span>
                        <span class="font-mono text-[10px] text-muted-foreground">{log.event_type}</span>
                      </div>
                    </Table.Cell>
                    <Table.Cell class="px-4 py-3 text-left align-middle text-xs text-foreground max-w-xs">
                      <p class="line-clamp-2">{log.summary}</p>
                      {#if log.diff && log.diff.length > 0}
                        <span class="mt-1 inline-flex items-center gap-1 rounded-md bg-amber-500/10 px-1.5 py-0.5 text-[10px] font-semibold text-amber-700 dark:text-amber-400">
                          <GitCommitVertical class="size-3" />
                          <span>{log.diff.length} field changes</span>
                        </span>
                      {/if}
                    </Table.Cell>
                    <Table.Cell class="w-px whitespace-nowrap px-4 py-3 text-center align-middle">
                      <Button
                        variant="outline"
                        size="sm"
                        class="min-h-[44px] md:min-h-[36px] gap-1 px-3 text-xs font-semibold"
                        onclick={() => openInspector(log)}
                      >
                        <Eye class="size-3.5" />
                        <span>Inspect</span>
                      </Button>
                    </Table.Cell>
                  </Table.Row>
                {:else}
                  <Table.Row class="border-b border-border/40 hover:bg-muted/30 transition-colors">
                    <Table.Cell colspan={6} class="py-12 text-center text-muted-foreground text-sm">
                      No audit events match the active search or filter criteria.
                    </Table.Cell>
                  </Table.Row>
                {/each}
              </Table.Body>
            </Table.Root>
          {/snippet}

          {#snippet cards()}
            <div class="grid grid-cols-1 gap-3 sm:grid-cols-2 xl:grid-cols-3">
              {#each audit.logs as log (log.id)}
                {@const meta = getDomainMeta(log.domain)}
                <BentoCard class="flex flex-col justify-between space-y-3 p-4">
                  <div>
                    <div class="flex items-start justify-between gap-2 border-b border-border/50 pb-2">
                      <div class="flex items-center gap-2">
                        <Checkbox
                          checked={selectedLogIds.includes(log.id)}
                          onCheckedChange={() => toggleRow(log.id)}
                          aria-label={`Select event ${log.title || log.event_type}`}
                        />
                        <span class={`inline-flex items-center gap-1 rounded-full border px-2 py-0.5 text-[10px] font-bold capitalize ${meta.badgeClass}`}>
                          <meta.icon class="size-3" />
                          <span>{meta.label}</span>
                        </span>
                      </div>
                      <span class="font-mono text-[10px] text-muted-foreground">{log.created_at_relative}</span>
                    </div>

                    <div class="mt-2.5 space-y-1">
                      <h3 class="font-bold text-sm text-foreground">{log.title}</h3>
                      <p class="font-mono text-[10px] text-muted-foreground">{log.event_type}</p>
                    </div>

                    <div class="mt-3 flex items-center gap-2 rounded-lg bg-muted/40 p-2 text-xs">
                      <AvatarBadge
                        name={log.actor_info?.name ?? log.actor}
                        role={log.actor_info?.role_label ?? ''}
                        avatarType={log.actor_info?.avatar?.type ?? 'preset'}
                        avatarPreset={log.actor_info?.avatar?.preset ?? 'records'}
                        size="sm"
                      />
                      <div class="min-w-0">
                        <p class="font-semibold text-foreground truncate">{log.actor_info?.name ?? log.actor}</p>
                        <p class="text-[10px] text-muted-foreground font-mono truncate">ID: {log.actor_token ?? log.actor_info?.id ?? '—'}</p>
                      </div>
                    </div>

                    <div class="mt-3 text-xs text-foreground">
                      <p class="line-clamp-2">{log.summary}</p>
                    </div>

                    {#if log.diff && log.diff.length > 0}
                      <div class="mt-2">
                        <span class="inline-flex items-center gap-1 rounded-md bg-amber-500/10 px-2 py-0.5 text-[10px] font-semibold text-amber-700 dark:text-amber-400">
                          <GitCommitVertical class="size-3" />
                          <span>{log.diff.length} changes recorded</span>
                        </span>
                      </div>
                    {/if}
                  </div>

                  <div class="border-t border-border/50 pt-3 flex justify-between items-center text-xs">
                    <span class="font-mono text-[10px] text-muted-foreground">{log.created_at_human}</span>
                    <Button
                      variant="outline"
                      size="sm"
                      class="min-h-[44px] md:min-h-[36px] gap-1 px-3 text-xs font-semibold"
                      onclick={() => openInspector(log)}
                    >
                      <Eye class="size-3.5" />
                      <span>Inspect</span>
                    </Button>
                  </div>
                </BentoCard>
              {:else}
                <div class="col-span-full py-12 text-center text-muted-foreground text-sm">
                  No audit events match the active search or filter criteria.
                </div>
              {/each}
            </div>
          {/snippet}
        </SwitchableListView>

        <!-- Pagination Controls -->
        {#if audit.pagination}
          <SimplePagination data={audit.pagination} />
        {/if}
      </section>
    {/if}
  </div>

  <!-- RICH EVENT INSPECTION MODAL -->
  {#if selectedLog}
    {@const meta = getDomainMeta(selectedLog.domain)}
    <div
      use:portal
      class="fixed inset-0 z-50 flex items-center justify-center bg-black/70 p-3 sm:p-5 backdrop-blur-sm"
      role="presentation"
      onclick={(event) => event.target === event.currentTarget && (selectedLog = null)}
    >
      <section
        role="dialog"
        aria-modal="true"
        aria-label={`Audit event ${selectedLog.title}`}
        class="flex max-h-[90vh] w-full max-w-3xl flex-col rounded-2xl border border-border bg-card shadow-2xl overflow-hidden"
      >
        <!-- Modal Header -->
        <div class="flex items-start justify-between border-b border-border p-5 bg-muted/30">
          <div class="space-y-1">
            <div class="flex items-center gap-2">
              <span class={`inline-flex items-center gap-1 rounded-full border px-2.5 py-0.5 text-xs font-bold capitalize ${meta.badgeClass}`}>
                <meta.icon class="size-3.5" />
                <span>{meta.label}</span>
              </span>
              <span class="font-mono text-xs text-muted-foreground">{selectedLog.created_at_human}</span>
            </div>
            <h2 class="text-xl font-bold text-foreground tracking-tight">{selectedLog.title}</h2>
            <p class="font-mono text-xs text-muted-foreground">{selectedLog.event_type}</p>
          </div>
          <button
            type="button"
            aria-label="Close"
            onclick={() => (selectedLog = null)}
            class="min-h-11 min-w-11 rounded-lg hover:bg-muted inline-flex items-center justify-center text-muted-foreground hover:text-foreground"
          >
            <X class="size-5" />
          </button>
        </div>

        <!-- Modal Body (Scrollable) -->
        <div class="flex-1 overflow-y-auto p-5 space-y-5">
          <!-- Actor Card (Prominent identity presentation) -->
          <div class="rounded-xl border border-border/80 bg-muted/40 p-4">
            <div class="text-[10px] font-bold uppercase tracking-wider text-muted-foreground mb-2">Attributable Principal / Actor</div>
            <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3">
              <div class="flex items-center gap-3">
                <AvatarBadge
                  name={selectedLog.actor_info?.name ?? selectedLog.actor}
                  role={selectedLog.actor_info?.role_label ?? ''}
                  avatarType={selectedLog.actor_info?.avatar?.type ?? 'preset'}
                  avatarPreset={selectedLog.actor_info?.avatar?.preset ?? 'records'}
                  size="md"
                />
                <div>
                  <h4 class="font-bold text-sm text-foreground">{selectedLog.actor_info?.name ?? selectedLog.actor}</h4>
                  <p class="text-xs text-muted-foreground">{selectedLog.actor_info?.email ?? selectedLog.actor_info?.role_label}</p>
                </div>
              </div>
              <div class="flex flex-wrap items-center gap-2 text-xs">
                {#if selectedLog.actor_info?.office}
                  <span class="rounded-full border border-border bg-card px-2.5 py-1 text-xs font-semibold capitalize">
                    Office: {selectedLog.actor_info.office}
                  </span>
                {/if}
                <span class="rounded-full border border-border bg-card px-2.5 py-1 text-xs font-mono font-medium">
                  Token: {selectedLog.actor_token ?? selectedLog.actor_info?.id}
                </span>
                <span class="rounded-full border border-primary/20 bg-primary/10 px-2.5 py-1 text-xs font-semibold text-primary capitalize">
                  {selectedLog.actor_info?.kind ?? 'User'}
                </span>
              </div>
            </div>
          </div>

          <!-- Inspector Tabs -->
          <div class="flex flex-wrap gap-1 border-b border-border pb-1" role="tablist">
            <button
              type="button"
              onclick={() => (modalTab = 'overview')}
              class={`min-h-[44px] md:min-h-[36px] rounded-lg px-3 py-1.5 text-xs font-semibold transition-colors flex items-center gap-1.5 ${modalTab === 'overview' ? 'bg-primary text-primary-foreground' : 'text-muted-foreground hover:bg-muted hover:text-foreground'}`}
            >
              <Activity class="size-3.5" />
              <span>Overview</span>
            </button>
            <button
              type="button"
              onclick={() => (modalTab = 'diff')}
              class={`min-h-[44px] md:min-h-[36px] rounded-lg px-3 py-1.5 text-xs font-semibold transition-colors flex items-center gap-1.5 ${modalTab === 'diff' ? 'bg-primary text-primary-foreground' : 'text-muted-foreground hover:bg-muted hover:text-foreground'}`}
            >
              <GitCommitVertical class="size-3.5" />
              <span>Changes &amp; Diff</span>
              {#if selectedLog.diff && selectedLog.diff.length > 0}
                <span class="ml-1 rounded-full bg-amber-500/20 px-1.5 py-0.2 text-[10px] font-bold text-amber-700 dark:text-amber-300">
                  {selectedLog.diff.length}
                </span>
              {/if}
            </button>
            <button
              type="button"
              onclick={() => (modalTab = 'metadata')}
              class={`min-h-[44px] md:min-h-[36px] rounded-lg px-3 py-1.5 text-xs font-semibold transition-colors flex items-center gap-1.5 ${modalTab === 'metadata' ? 'bg-primary text-primary-foreground' : 'text-muted-foreground hover:bg-muted hover:text-foreground'}`}
            >
              <Layers class="size-3.5" />
              <span>Metadata &amp; Context</span>
            </button>
            <button
              type="button"
              onclick={() => (modalTab = 'integrity')}
              class={`min-h-[44px] md:min-h-[36px] rounded-lg px-3 py-1.5 text-xs font-semibold transition-colors flex items-center gap-1.5 ${modalTab === 'integrity' ? 'bg-primary text-primary-foreground' : 'text-muted-foreground hover:bg-muted hover:text-foreground'}`}
            >
              <Hash class="size-3.5" />
              <span>Cryptographic Chain</span>
            </button>
            <button
              type="button"
              onclick={() => (modalTab = 'json')}
              class={`min-h-[44px] md:min-h-[36px] rounded-lg px-3 py-1.5 text-xs font-semibold transition-colors flex items-center gap-1.5 ${modalTab === 'json' ? 'bg-primary text-primary-foreground' : 'text-muted-foreground hover:bg-muted hover:text-foreground'}`}
            >
              <FileText class="size-3.5" />
              <span>Raw JSON</span>
            </button>
          </div>

          <!-- TAB 1: OVERVIEW -->
          {#if modalTab === 'overview'}
            <div class="space-y-4">
              <div class="rounded-xl border border-border bg-card p-4">
                <span class="text-[10px] font-bold uppercase tracking-wider text-muted-foreground">Action Summary</span>
                <p class="mt-1 text-sm font-medium text-foreground">{selectedLog.summary}</p>
              </div>

              <div class="grid grid-cols-1 sm:grid-cols-2 gap-3 text-xs">
                <div class="rounded-xl border border-border bg-muted/20 p-3">
                  <span class="text-muted-foreground">Event Key</span>
                  <p class="mt-1 font-mono font-bold text-foreground">{selectedLog.event_type}</p>
                </div>
                <div class="rounded-xl border border-border bg-muted/20 p-3">
                  <span class="text-muted-foreground">Recorded Timestamp</span>
                  <p class="mt-1 font-semibold text-foreground">{selectedLog.created_at_human} ({selectedLog.created_at_relative})</p>
                </div>
                <div class="rounded-xl border border-border bg-muted/20 p-3">
                  <span class="text-muted-foreground">System UUID</span>
                  <p class="mt-1 font-mono text-[11px] text-foreground">{selectedLog.id}</p>
                </div>
                <div class="rounded-xl border border-border bg-muted/20 p-3">
                  <span class="text-muted-foreground">Domain Classification</span>
                  <p class="mt-1 font-semibold text-foreground capitalize">{meta.label}</p>
                </div>
              </div>
            </div>

          <!-- TAB 2: CHANGES & DIFF -->
          {:else if modalTab === 'diff'}
            <div class="space-y-3">
              {#if selectedLog.diff && selectedLog.diff.length > 0}
                <div class="rounded-xl border border-border bg-card overflow-hidden">
                  <div class="bg-muted/60 px-4 py-2 text-xs font-bold text-muted-foreground border-b border-border">
                    Record Changes ({selectedLog.diff.length} field modifications)
                  </div>
                  <div class="divide-y divide-border">
                    {#each selectedLog.diff as item}
                      <div class="p-3 text-xs space-y-1">
                        <div class="flex items-center justify-between">
                          <span class="font-bold text-foreground">{item.label}</span>
                          <span class={`rounded-full border px-2 py-0.5 text-[10px] font-bold capitalize ${diffKindClass(item.kind)}`}>
                            {item.kind}
                          </span>
                        </div>
                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-2 mt-1">
                          <div class="rounded-lg bg-rose-500/5 border border-rose-500/20 p-2">
                            <span class="text-[10px] font-semibold text-rose-700 dark:text-rose-400">Previous / Before:</span>
                            <div class="mt-0.5 font-mono text-[11px] text-foreground truncate">
                              {item.old_value !== null && item.old_value !== undefined ? String(item.old_value) : '—'}
                            </div>
                          </div>
                          <div class="rounded-lg bg-emerald-500/5 border border-emerald-500/20 p-2">
                            <span class="text-[10px] font-semibold text-emerald-700 dark:text-emerald-400">Updated / After:</span>
                            <div class="mt-0.5 font-mono text-[11px] text-foreground truncate font-bold">
                              {item.new_value !== null && item.new_value !== undefined ? String(item.new_value) : '—'}
                            </div>
                          </div>
                        </div>
                      </div>
                    {/each}
                  </div>
                </div>
              {:else}
                <div class="rounded-xl border border-border bg-card p-8 text-center text-xs text-muted-foreground space-y-1">
                  <GitCommitVertical class="mx-auto size-6 opacity-40 text-muted-foreground" />
                  <p class="font-medium text-foreground">No field-level record diffs captured for this event.</p>
                  <p>This event represents a state query, notification, or point-in-time security action.</p>
                </div>
              {/if}
            </div>

          <!-- TAB 3: METADATA & CONTEXT -->
          {:else if modalTab === 'metadata'}
            <div class="space-y-3">
              <div class="grid grid-cols-1 sm:grid-cols-2 gap-2 text-xs">
                {#each Object.entries(selectedLog.payload ?? {}) as [key, value]}
                  <div class="rounded-xl border border-border bg-card p-3">
                    <span class="text-[10px] font-bold uppercase tracking-wider text-muted-foreground">
                      {key.replace(/_/g, ' ')}
                    </span>
                    <div class="mt-1 font-mono text-xs text-foreground truncate">
                      {#if typeof value === 'object' && value !== null}
                        {JSON.stringify(value)}
                      {:else}
                        {String(value)}
                      {/if}
                    </div>
                  </div>
                {/each}
              </div>
            </div>

          <!-- TAB 4: CRYPTOGRAPHIC CHAIN INTEGRITY -->
          {:else if modalTab === 'integrity'}
            <div class="space-y-4 text-xs">
              <div role="status" aria-label="Unverified audit integrity" class="rounded-xl border border-border bg-muted/40 p-4 flex items-start gap-3">
                <Shield class="size-5 text-muted-foreground shrink-0 mt-0.5" />
                <div>
                  <h4 class="font-bold text-foreground">Legacy stored hash values</h4>
                  <p class="mt-0.5 text-muted-foreground leading-relaxed">
                    These hash fields are stored legacy values, not a verified integrity result. This inspector does not check their authenticity or chain integrity.
                  </p>
                </div>
              </div>

              <div class="space-y-3">
                <div class="rounded-xl border border-border bg-card p-4 space-y-1">
                  <div class="flex items-center justify-between">
                    <span class="text-[10px] font-bold uppercase tracking-wider text-muted-foreground">Event Hash (Current Record)</span>
                    <Button
                      variant="ghost"
                      size="sm"
                      class="h-7 px-2 text-[11px] gap-1"
                      onclick={() => copyHashString(selectedLog.event_hash, 'current')}
                    >
                      {#if copiedHash === 'current'}
                        <Check class="size-3 text-emerald-600" />
                        <span>Copied</span>
                      {:else}
                        <Copy class="size-3" />
                        <span>Copy</span>
                      {/if}
                    </Button>
                  </div>
                  <div class="font-mono text-xs text-foreground bg-muted/50 p-2.5 rounded-lg break-all select-all">
                    {selectedLog.event_hash ?? 'Genesis / Standalone record'}
                  </div>
                </div>

                <div class="rounded-xl border border-border bg-card p-4 space-y-1">
                  <div class="flex items-center justify-between">
                    <span class="text-[10px] font-bold uppercase tracking-wider text-muted-foreground">Previous Event Hash (Preceding Block)</span>
                    <Button
                      variant="ghost"
                      size="sm"
                      class="h-7 px-2 text-[11px] gap-1"
                      onclick={() => copyHashString(selectedLog.prev_hash, 'prev')}
                    >
                      {#if copiedHash === 'prev'}
                        <Check class="size-3 text-emerald-600" />
                        <span>Copied</span>
                      {:else}
                        <Copy class="size-3" />
                        <span>Copy</span>
                      {/if}
                    </Button>
                  </div>
                  <div class="font-mono text-xs text-foreground bg-muted/50 p-2.5 rounded-lg break-all select-all">
                    {selectedLog.prev_hash ?? 'None (Initial audit genesis record)'}
                  </div>
                </div>
              </div>
            </div>

          <!-- TAB 5: RAW JSON -->
          {:else if modalTab === 'json'}
            <div class="space-y-2">
              <div class="flex items-center justify-between">
                <span class="text-[10px] font-bold uppercase tracking-wider text-muted-foreground">Sanitized Event Payload (JSON)</span>
                <Button
                  variant="outline"
                  size="sm"
                  class="h-7 gap-1 px-2.5 text-xs font-semibold"
                  onclick={copyJsonPayload}
                >
                  {#if copiedJson}
                    <Check class="size-3 text-emerald-600" />
                    <span>Copied JSON</span>
                  {:else}
                    <Copy class="size-3" />
                    <span>Copy JSON</span>
                  {/if}
                </Button>
              </div>
              <pre class="max-h-80 overflow-x-auto rounded-xl border border-border bg-muted/60 p-4 font-mono text-[11px] leading-relaxed text-foreground select-all">{JSON.stringify(selectedLog.payload, null, 2)}</pre>
            </div>
          {/if}
        </div>

        <!-- Modal Footer -->
        <div class="flex items-center justify-end gap-2 border-t border-border p-4 bg-muted/20">
          <Button
            type="button"
            class="min-h-11 px-5 font-bold"
            onclick={() => (selectedLog = null)}
          >
            Close Inspector
          </Button>
        </div>
      </section>
    </div>
  {/if}
</StaffLayout>
