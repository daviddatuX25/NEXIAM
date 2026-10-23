<script lang="ts">
  import { router } from '@inertiajs/svelte';
  import ApplicantLayout from '@/Pages/Applicant/ApplicantLayout.svelte';
  import { Button } from '@/Components/ui/button';
  import { Input } from '@/Components/ui/input';
  import { Badge } from '@/Components/ui/badge';
  import * as Table from '@/Components/ui/table';
  import SwitchableListView from '@/Components/kit/SwitchableListView.svelte';
  import ActiveFilterChips from '@/Components/kit/ActiveFilterChips.svelte';
  import SimplePagination from '@/Components/kit/SimplePagination.svelte';
  import {
    History,
    Search,
    ShieldCheck,
    ChevronDown,
    X,
    ArrowUpDown,
    ArrowUp,
    ArrowDown,
    Filter,
  } from 'lucide-svelte';

  type Category = 'all' | 'application' | 'examination' | 'results_release' | 'clearance' | 'security';
  type ActorOffice = 'all' | 'registrar' | 'guidance' | 'program_head' | 'self' | 'system';
  type AuditEvent = {
    id: string;
    event_type: string;
    label: string;
    category: Exclude<Category, 'all'>;
    actor_display: string;
    direction: 'by_you' | 'on_your_record';
    occurred_at: string | null;
  };
  type Paginator = {
    data: AuditEvent[];
    current_page: number;
    last_page: number;
    per_page?: number;
    total: number;
    from?: number;
    to?: number;
    prev_page_url?: string | null;
    next_page_url?: string | null;
  };
  type Filters = {
    category: Category;
    actor_office: ActorOffice;
    search: string;
    sort: 'newest' | 'oldest';
    page: number;
  };
  type CategoryCounts = Record<string, number>;

  let {
    events = { data: [], current_page: 1, last_page: 1, total: 0 },
    filters = { category: 'all', actor_office: 'all', search: '', sort: 'newest', page: 1 },
    category_counts = { application: 0, examination: 0, results_release: 0, clearance: 0, security: 0 },
    back_url = '/applicant/admission-support',
  } = $props<{
    events?: Paginator;
    filters?: Filters;
    category_counts?: CategoryCounts;
    back_url?: string;
  }>();

  let viewMode = $state('responsive');
  let searchText = $state(filters.search ?? '');
  let mobileFiltersOpen = $state(false);

  $effect(() => {
    searchText = filters.search ?? '';
  });

  const categories: { value: Category; label: string }[] = [
    { value: 'all', label: 'All' },
    { value: 'application', label: 'Application' },
    { value: 'examination', label: 'Examination' },
    { value: 'results_release', label: 'Results & Release' },
    { value: 'clearance', label: 'Clearance' },
    { value: 'security', label: 'Security' },
  ];

  function categoryLabel(cat: string): string {
    const found = categories.find((c) => c.value === cat);
    return found ? found.label : cat;
  }

  function officeLabel(office: ActorOffice): string {
    switch (office) {
      case 'registrar':
        return 'Registrar Desk';
      case 'guidance':
        return 'Guidance Office';
      case 'program_head':
        return 'Program Head';
      case 'self':
        return 'You';
      case 'system':
        return 'Assessment System';
      default:
        return 'All offices';
    }
  }

  function countFor(category: Category): number {
    if (category === 'all') {
      return Object.keys(category_counts).reduce((total, key) => total + (category_counts[key] ?? 0), 0);
    }
    return category_counts[category] ?? 0;
  }

  function categoryBadgeVariant(cat: string): 'default' | 'secondary' | 'outline' {
    switch (cat) {
      case 'clearance':
      case 'application':
        return 'default';
      case 'examination':
      case 'results_release':
        return 'secondary';
      default:
        return 'outline';
    }
  }

  function navigate(changes: Partial<Filters>): void {
    const next: Filters = {
      category: changes.category ?? filters.category,
      actor_office: changes.actor_office ?? filters.actor_office,
      search: changes.search !== undefined ? changes.search : searchText,
      sort: changes.sort ?? filters.sort,
      page: changes.page ?? 1,
    };
    router.get('/applicant/access-history', next, {
      preserveState: true,
      preserveScroll: true,
      replace: true,
    });
  }

  function formatDateTime(value: string | null): string {
    if (!value) return '—';
    return new Date(value).toLocaleString(undefined, {
      year: 'numeric',
      month: 'short',
      day: 'numeric',
      hour: '2-digit',
      minute: '2-digit',
    });
  }

  const activeSecondaryCount = $derived(
    (filters.actor_office !== 'all' ? 1 : 0) + (filters.sort !== 'newest' ? 1 : 0),
  );

  const activeFilterChips = $derived([
    ...(filters.search ? [{ id: 'search', label: `Search: ${filters.search}` }] : []),
    ...(filters.category !== 'all' ? [{ id: 'category', label: `Category: ${categoryLabel(filters.category)}` }] : []),
    ...(filters.actor_office !== 'all' ? [{ id: 'actor_office', label: `Office: ${officeLabel(filters.actor_office)}` }] : []),
    ...(filters.sort !== 'newest' ? [{ id: 'sort', label: 'Sort: Oldest first' }] : []),
  ]);

  function clearFilter(id: string): void {
    if (id === 'search') {
      searchText = '';
      navigate({ search: '', page: 1 });
    } else if (id === 'category') {
      navigate({ category: 'all', page: 1 });
    } else if (id === 'actor_office') {
      navigate({ actor_office: 'all', page: 1 });
    } else if (id === 'sort') {
      navigate({ sort: 'newest', page: 1 });
    }
  }

  function clearAllFilters(): void {
    searchText = '';
    navigate({
      search: '',
      category: 'all',
      actor_office: 'all',
      sort: 'newest',
      page: 1,
    });
  }
</script>

<svelte:head>
  <title>Privacy &amp; access history</title>
</svelte:head>

<ApplicantLayout title="Access history">
  <div class="mx-auto max-w-5xl space-y-6">
    <div class="flex items-start justify-between gap-4">
      <div class="space-y-1">
        <div class="flex items-center gap-2">
          <History class="size-5 text-muted-foreground" />
          <h1 class="text-2xl font-semibold tracking-tight text-foreground">Privacy &amp; access history</h1>
        </div>
        <p class="text-sm text-muted-foreground">A plain-language record of activity on your applications and actions by you.</p>
      </div>
      <Button variant="outline" size="sm" href={back_url} class="shrink-0">Back to Messages</Button>
    </div>

    <!-- Canvas-first Search and Filters Toolbar -->
    <div class="sticky top-0 z-20 space-y-3 rounded-xl border border-border/40 bg-background/95 p-3 backdrop-blur-md shadow-xs sm:p-4">
      <div class="flex flex-col gap-3 md:flex-row md:items-end">
        <!-- Search Input -->
        <div class="relative w-full flex-1 md:min-w-[280px]">
          <label for="activity-search" class="sr-only">Search activity</label>
          <div class="relative flex items-center">
            <Search class="pointer-events-none absolute left-3.5 size-4 text-muted-foreground" aria-hidden="true" />
            <Input
              id="activity-search"
              type="search"
              bind:value={searchText}
              placeholder="Search descriptions, offices, or you…"
              class="h-11 min-h-[44px] w-full rounded-xl border border-input bg-background pl-10 pr-24 text-sm shadow-xs outline-none focus-visible:ring-2 focus-visible:ring-ring dark:bg-input/30 md:min-h-[40px]"
              onkeydown={(event) => {
                if (event.key === 'Enter') {
                  event.preventDefault();
                  navigate({ search: searchText, page: 1 });
                }
              }}
            />
            <div class="absolute right-1.5 flex items-center gap-1">
              {#if searchText}
                <button
                  type="button"
                  onclick={() => {
                    searchText = '';
                    navigate({ search: '', page: 1 });
                  }}
                  class="grid size-8 place-items-center rounded-md text-muted-foreground hover:bg-muted"
                  aria-label="Clear search"
                >
                  <X class="size-4" />
                </button>
              {/if}
              <Button
                size="sm"
                variant="secondary"
                class="h-8 px-3 text-xs"
                onclick={() => navigate({ search: searchText, page: 1 })}
              >
                Search
              </Button>
            </div>
          </div>
        </div>

        <!-- Mobile Filter Toggle Button -->
        <div class="flex items-center justify-between gap-2 md:hidden">
          <button
            type="button"
            class="inline-flex min-h-[44px] w-full items-center justify-between rounded-lg border border-input bg-background px-3 text-sm font-medium text-foreground shadow-xs dark:bg-input/30"
            aria-expanded={mobileFiltersOpen}
            onclick={() => (mobileFiltersOpen = !mobileFiltersOpen)}
          >
            <span class="inline-flex items-center gap-2">
              <Filter class="size-4 text-muted-foreground" />
              <span>Secondary Filters ({activeSecondaryCount})</span>
            </span>
            <ChevronDown class="size-4 shrink-0 transition-transform {mobileFiltersOpen ? 'rotate-180' : ''}" />
          </button>
        </div>

        <!-- Secondary Filters (Actor Office, Sort) -->
        <div class={mobileFiltersOpen ? 'grid grid-cols-1 gap-3 pt-2 sm:grid-cols-2 md:flex md:flex-wrap md:items-end md:pt-0' : 'hidden md:flex md:flex-wrap md:items-end md:pt-0'}>
          <label class="space-y-1">
            <span class="block text-xs font-medium text-muted-foreground">Actor office</span>
            <div class="relative flex h-10 min-h-[44px] items-center rounded-md border border-input bg-background pr-2 text-foreground shadow-xs dark:bg-input/30 md:h-9 md:min-h-9">
              <select
                class="min-w-0 flex-1 truncate appearance-none bg-transparent px-3 text-xs outline-none"
                value={filters.actor_office}
                onchange={(event) => navigate({ actor_office: event.currentTarget.value as ActorOffice, page: 1 })}
              >
                <option value="all">All offices and you</option>
                <option value="registrar">Registrar Desk</option>
                <option value="guidance">Guidance Office</option>
                <option value="program_head">Program Head</option>
                <option value="self">You</option>
                <option value="system">Assessment System</option>
              </select>
              <ChevronDown class="size-4 shrink-0 text-muted-foreground pointer-events-none" aria-hidden="true" />
            </div>
          </label>

          <label class="space-y-1">
            <span class="block text-xs font-medium text-muted-foreground">Sort order</span>
            <div class="relative flex h-10 min-h-[44px] items-center rounded-md border border-input bg-background pr-2 text-foreground shadow-xs dark:bg-input/30 md:h-9 md:min-h-9">
              <select
                class="min-w-0 flex-1 truncate appearance-none bg-transparent px-3 text-xs outline-none"
                value={filters.sort}
                onchange={(event) => navigate({ sort: event.currentTarget.value as Filters['sort'], page: 1 })}
              >
                <option value="newest">Newest first</option>
                <option value="oldest">Oldest first</option>
              </select>
              <ChevronDown class="size-4 shrink-0 text-muted-foreground pointer-events-none" aria-hidden="true" />
            </div>
          </label>
        </div>
      </div>

      <!-- Categories Segmented Filter Nav -->
      <div class="border-t border-border/40 pt-2.5">
        <nav aria-label="Activity categories" class="flex gap-1.5 overflow-x-auto pb-1 scrollbar-none">
          {#each categories as category (category.value)}
            {@const isActive = filters.category === category.value}
            <button
              type="button"
              aria-current={isActive ? 'page' : undefined}
              class={`inline-flex shrink-0 items-center gap-2 rounded-full px-3.5 py-1.5 text-xs font-medium transition-colors ${
                isActive
                  ? 'bg-primary text-primary-foreground shadow-xs'
                  : 'border border-border bg-background text-muted-foreground hover:bg-muted hover:text-foreground'
              }`}
              onclick={() => navigate({ category: category.value, page: 1 })}
            >
              <span>{category.label}</span>
              <span
                class={`rounded-full px-1.5 py-0.2 text-[10px] font-semibold tabular-nums ${
                  isActive
                    ? 'bg-primary-foreground/20 text-primary-foreground'
                    : 'bg-muted text-muted-foreground'
                }`}
              >
                {countFor(category.value)}
              </span>
            </button>
          {/each}
        </nav>
      </div>

      <!-- Active Filter Chips -->
      <ActiveFilterChips chips={activeFilterChips} onRemove={clearFilter} onClearAll={clearAllFilters} />
    </div>

    <!-- Content / State Display -->
    {#if events.total === 0 && filters.category === 'all' && filters.actor_office === 'all' && !filters.search}
      <div class="rounded-xl border border-dashed border-border bg-card p-12 text-center">
        <ShieldCheck class="mx-auto mb-3 size-10 text-muted-foreground/60" />
        <h2 class="text-base font-semibold text-foreground">No privacy or access activity recorded yet</h2>
        <p class="mx-auto mt-1 max-w-sm text-sm text-muted-foreground">Activity by you and administrative offices on your applications will appear here.</p>
      </div>
    {:else if events.data.length === 0}
      <div class="rounded-xl border border-dashed border-border bg-card p-12 text-center">
        <Search class="mx-auto mb-3 size-10 text-muted-foreground/60" />
        <h2 class="text-base font-semibold text-foreground">No matching activity found</h2>
        <p class="mx-auto mt-1 max-w-sm text-sm text-muted-foreground">No access logs match your active search or filter criteria. Try clearing filters or selecting another category.</p>
        <Button variant="outline" size="sm" class="mt-4" onclick={clearAllFilters}>
          Clear all filters
        </Button>
      </div>
    {:else}
      <SwitchableListView bind:viewMode cardsInset={false}>
        {#snippet table()}
          <div class="rounded-xl border border-border bg-card shadow-2xs overflow-hidden">
            <Table.Root class="w-full text-sm">
              <Table.Header class="bg-muted/40 border-b border-border">
                <Table.Row>
                  <Table.Head class="w-[190px] px-4 py-3 text-left">
                    <button
                      type="button"
                      onclick={() => navigate({ sort: filters.sort === 'newest' ? 'oldest' : 'newest', page: 1 })}
                      class="inline-flex min-h-[36px] items-center gap-1.5 rounded-sm font-medium hover:text-primary focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring"
                      aria-label="Sort by date ({filters.sort === 'newest' ? 'newest first' : 'oldest first'})"
                    >
                      <span>Date &amp; Time</span>
                      {#if filters.sort === 'newest'}
                        <ArrowDown class="size-3.5 text-primary" aria-hidden="true" />
                      {:else}
                        <ArrowUp class="size-3.5 text-primary" aria-hidden="true" />
                      {/if}
                    </button>
                  </Table.Head>
                  <Table.Head class="px-4 py-3 text-left">Activity</Table.Head>
                  <Table.Head class="px-4 py-3 text-left">Category</Table.Head>
                  <Table.Head class="px-4 py-3 text-left">Office / Actor</Table.Head>
                  <Table.Head class="px-4 py-3 text-right">Context</Table.Head>
                </Table.Row>
              </Table.Header>
              <Table.Body>
                {#each events.data as event (event.id)}
                  <Table.Row class="border-b border-border/40 hover:bg-muted/30 transition-colors">
                    <Table.Cell class="font-mono text-xs text-muted-foreground whitespace-nowrap px-4 py-3.5">
                      <time datetime={event.occurred_at ?? undefined}>{formatDateTime(event.occurred_at)}</time>
                    </Table.Cell>
                    <Table.Cell class="px-4 py-3.5 font-medium text-foreground">
                      {event.label}
                    </Table.Cell>
                    <Table.Cell class="px-4 py-3.5 whitespace-nowrap">
                      <Badge variant={categoryBadgeVariant(event.category)} class="capitalize text-xs font-normal">
                        {categoryLabel(event.category)}
                      </Badge>
                    </Table.Cell>
                    <Table.Cell class="px-4 py-3.5 text-xs text-muted-foreground whitespace-nowrap">
                      {event.actor_display}
                    </Table.Cell>
                    <Table.Cell class="px-4 py-3.5 text-right whitespace-nowrap">
                      <span
                        class={`inline-flex items-center rounded-full px-2 py-0.5 text-xs font-medium ${
                          event.direction === 'by_you'
                            ? 'bg-primary/10 text-primary dark:bg-primary/20'
                            : 'bg-muted text-muted-foreground'
                        }`}
                      >
                        {event.direction === 'by_you' ? 'By you' : 'On your record'}
                      </span>
                    </Table.Cell>
                  </Table.Row>
                {/each}
              </Table.Body>
            </Table.Root>
          </div>
        {/snippet}

        {#snippet cards()}
          <div class="space-y-3">
            {#each events.data as event (event.id)}
              <div class="rounded-xl border border-border bg-card p-4 shadow-2xs space-y-2.5">
                <div class="flex flex-wrap items-center justify-between gap-2">
                  <div class="flex items-center gap-2">
                    <span
                      class={`inline-flex items-center rounded-full px-2 py-0.5 text-xs font-medium ${
                        event.direction === 'by_you'
                          ? 'bg-primary/10 text-primary dark:bg-primary/20'
                          : 'bg-muted text-muted-foreground'
                      }`}
                    >
                      {event.direction === 'by_you' ? 'By you' : 'On your record'}
                    </span>
                    <span class="text-xs font-medium text-muted-foreground">{event.actor_display}</span>
                  </div>
                  <Badge variant={categoryBadgeVariant(event.category)} class="capitalize text-xs font-normal">
                    {categoryLabel(event.category)}
                  </Badge>
                </div>
                <p class="font-medium text-sm text-foreground">{event.label}</p>
                <div class="flex items-center justify-between border-t border-border/40 pt-2 text-xs text-muted-foreground">
                  <span>Occurred at</span>
                  <time class="font-mono tabular-nums" datetime={event.occurred_at ?? undefined}>
                    {formatDateTime(event.occurred_at)}
                  </time>
                </div>
              </div>
            {/each}
          </div>
        {/snippet}
      </SwitchableListView>

      <SimplePagination data={events} />
    {/if}
  </div>
</ApplicantLayout>
