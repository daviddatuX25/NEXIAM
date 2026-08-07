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

  let {
    search = $bindable(''),
    searchPlaceholder = 'Search…',
    searchLabel = '',
    /** Snippet: desktop filter controls (status, season, dates, …) */
    desktopFilters,
    /** Snippet: mobile panel body (same controls, stacked, full width) */
    mobileFilters,
    class: className = '',
  } = $props();

  let filtersOpen = $state(false);
</script>

<div class="flex flex-col gap-3 {className}">
  <div class="flex flex-wrap items-end gap-3">
    <div class="relative min-w-[160px] max-w-none md:max-w-[220px] flex-1 min-w-0 md:flex-none w-full sm:w-auto">
      {#if searchLabel}
        <label class="mb-1 block text-xs font-medium text-muted-foreground" for="kit-filter-search">
          {searchLabel}
        </label>
      {/if}
      <div class="relative">
        <Search
          class="pointer-events-none absolute left-2.5 top-1/2 h-4 w-4 -translate-y-1/2 text-muted-foreground"
        />
        <Input
          id="kit-filter-search"
          type="search"
          placeholder={searchPlaceholder}
          bind:value={search}
          class="h-10 w-full min-h-[44px] pl-8 md:min-h-[40px]"
        />
      </div>
    </div>

    {#if mobileFilters}
      <details class="relative md:hidden" bind:open={filtersOpen}>
        <summary
          class="flex min-h-[44px] min-w-[44px] cursor-pointer list-none items-center gap-2 rounded-lg border border-input bg-background px-3 py-2 text-sm font-medium hover:bg-muted/50"
        >
          <Filter class="h-4 w-4" />
          <span>Filters</span>
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
  </div>
</div>
