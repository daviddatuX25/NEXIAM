<script>
  /**
   * Responsive filter toolbar (legacy Applications / Users / Exam Scheduling).
   *
   * - Search always visible (flex-1 on mobile, capped width on md+)
   * - Extra filters: desktop inline (`hidden md:flex`); mobile via Filters details panel
   * - Touch targets: min-h-[44px] mobile, ~40px desktop
   *
   * Presentational only — parent owns filter state and apply/debounce.
   */
  import { Search, Filter, ChevronDown } from 'lucide-svelte';
  import { Input } from '@/Components/ui/input';
  import SortDropdown from '@/Components/kit/SortDropdown.svelte';

  let {
    search = $bindable(''),
    searchValue = '',
    searchPlaceholder = 'Search…',
    searchLabel = '',
    inputId = '',
    onSearchInput = null,
    onSearchSubmit = null,
    onSearchClear = null,
    filters = null,
    actions = null,
    sortOptions = [],
    sortValue = '',
    sortDir = 'desc',
    onSortChange = null,
    sortSlot = null,
    mobileFilterCount = 0,
    /** Snippet: desktop filter controls (status, season, dates, …) */
    desktopFilters = null,
    /** Snippet: mobile panel body (same controls, stacked, full width) */
    mobileFilters = null,
    searchSuggestions = null,
    comboboxProps = {},
    onkeydown = null,
    class: className = '',
  } = $props();

  $effect(() => {
    if (searchValue && !search) {
      search = searchValue;
    }
  });
  const resolvedId = inputId || ('filter-search-' + Math.random().toString(36).slice(2, 8));

  let filtersOpen = $state(false);
</script>

<div class="flex flex-col gap-3 {className}">
  <div class="flex flex-wrap items-end gap-3">
    <div class="relative min-w-0 w-full flex-1 basis-full md:basis-0 md:min-w-[280px]">
      {#if searchLabel}
        <label class="mb-1 block text-xs font-medium text-muted-foreground" for={resolvedId}>
          {searchLabel}
        </label>
      {/if}
      <div class="relative">
        <Search
          class="pointer-events-none absolute left-2.5 top-1/2 h-4 w-4 -translate-y-1/2 text-muted-foreground"
        />
        <Input
          id={resolvedId}
          type="search"
          placeholder={searchPlaceholder}
          bind:value={search}
          {...comboboxProps}
          oninput={(e) => {
            onSearchInput?.(e.currentTarget.value);
            if (!e.currentTarget.value) {
              onSearchClear?.();
            }
          }}
          onkeydown={(e) => {
            onkeydown?.(e);
            if (e.key === 'Enter') {
              onSearchSubmit?.(search);
            }
          }}
          class="h-11 w-full min-h-[44px] border border-input bg-background text-foreground shadow-xs pl-10 pr-10 dark:bg-input/30 md:min-h-[40px]"
        />
        {#if searchSuggestions}
          {@render searchSuggestions()}
        {/if}
      </div>
    </div>

    {#if mobileFilters}
      <details class="relative md:hidden" bind:open={filtersOpen}>
        <summary
          class="flex min-h-[44px] min-w-[44px] cursor-pointer list-none items-center gap-2 rounded-lg border border-input bg-background px-3 py-2 text-sm font-medium hover:bg-muted/50"
        >
          <Filter class="h-4 w-4" />
          <span>Filters ({mobileFilterCount})</span>
          <ChevronDown class="h-4 w-4 transition-transform [details[open]_&]:rotate-180" />
        </summary>
        <div
          class="absolute right-0 top-full z-20 mt-1 flex w-[min(320px,calc(100vw-2rem))] flex-col gap-3 rounded-lg border border-border bg-card p-4 shadow-lg"
        >
          {@render mobileFilters()}
        </div>
      </details>
    {/if}

    {#if desktopFilters}
      <div class="hidden md:flex md:flex-wrap md:items-end md:gap-3">
        {@render desktopFilters()}
      </div>
    {/if}

    {#if filters}
      <div class="flex flex-wrap items-end gap-3">
        {@render filters()}
      </div>
    {/if}

    {#if sortSlot}
      {@render sortSlot()}
    {:else if sortOptions.length > 0}
      <SortDropdown
        options={sortOptions}
        sort={sortValue}
        dir={sortDir}
        onchange={onSortChange}
      />
    {/if}

    {#if actions}
      <div class="ml-auto flex items-center gap-2">
        {@render actions()}
      </div>
    {/if}
  </div>
</div>
