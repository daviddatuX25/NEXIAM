<script>
    import { onDestroy } from 'svelte';
    import { SvelteURLSearchParams } from 'svelte/reactivity';
    import { Button } from '@/Components/ui/button';
    import { Input } from '@/Components/ui/input';
    import { AlertCircle, Search, LoaderCircle } from 'lucide-svelte';

    let {
        onSelect = () => {},
        contextKey = '',
        label = 'Find an applicant',
        placeholder = 'Search name or reference number',
    } = $props();

    let query = $state('');
    let cycleId = $state('');
    let cycles = $state([]);
    let results = $state([]);
    let page = $state(1);
    let hasMore = $state(false);
    let isLoading = $state(false);
    let errorMessage = $state('');
    let requestVersion = 0;
    let controller = null;
    let debounceTimer = null;
    let observedContext = '';

    function invalidate() {
        requestVersion++;
        controller?.abort();
        controller = null;
        if (debounceTimer !== null) {
            clearTimeout(debounceTimer);
            debounceTimer = null;
        }
        isLoading = false;
    }

    async function requestResults(targetPage, append = false) {
        const normalizedQuery = query.trim();
        if (normalizedQuery.length < 2) {
            results = [];
            hasMore = false;
            page = 1;
            errorMessage = '';
            return;
        }

        invalidate();
        const version = requestVersion;
        const requestController = new AbortController();
        controller = requestController;
        isLoading = true;
        errorMessage = '';
        try {
            const params = new SvelteURLSearchParams({ q: normalizedQuery, page: String(targetPage) });
            if (cycleId) params.set('academic_cycle_id', cycleId);
            const response = await fetch(`/staff/scan/search?${params}`, {
                credentials: 'same-origin',
                headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                signal: requestController.signal,
            });
            const payload = await response.json().catch(() => ({}));
            if (!response.ok) throw new Error(payload.message ?? 'Applicant search is unavailable.');
            if (version !== requestVersion || requestController.signal.aborted) return;

            const found = Array.isArray(payload.results) ? payload.results : [];
            cycles = Array.isArray(payload.cycles) ? payload.cycles : [];
            results = append ? [...results, ...found] : found;
            page = Number(payload.pagination?.current_page ?? targetPage);
            hasMore = payload.pagination?.has_more === true;
        } catch (error) {
            if (error.name !== 'AbortError' && version === requestVersion) {
                results = [];
                hasMore = false;
                errorMessage = error.message || 'Applicant search is unavailable.';
            }
        } finally {
            if (version === requestVersion) {
                isLoading = false;
                if (controller === requestController) controller = null;
            }
        }
    }

    function searchLater() {
        invalidate();
        results = [];
        hasMore = false;
        page = 1;
        errorMessage = '';
        if (query.trim().length < 2) return;
        debounceTimer = setTimeout(() => {
            debounceTimer = null;
            void requestResults(1);
        }, 250);
    }

    function loadMore() {
        if (!isLoading && hasMore) void requestResults(page + 1, true);
    }

    function selectCycle(event) {
        cycleId = event.currentTarget.value;
        searchLater();
    }

    $effect(() => {
        const nextContext = String(contextKey);
        if (nextContext !== observedContext) {
            observedContext = nextContext;
            invalidate();
            query = '';
            cycleId = '';
            cycles = [];
            results = [];
            hasMore = false;
            page = 1;
            errorMessage = '';
        }
    });

    $effect(() => {
        query;
        cycleId;
        observedContext;
        searchLater();
    });

    onDestroy(invalidate);
</script>

<section class="space-y-3" aria-label={label}>
    <div class="grid gap-2 sm:grid-cols-[minmax(0,1fr)_auto]">
        <label class="grid gap-1.5 text-sm font-medium">
            <span>{label}</span>
            <span class="relative">
                <Search class="pointer-events-none absolute left-3 top-1/2 size-4 -translate-y-1/2 text-muted-foreground" aria-hidden="true" />
                <Input bind:value={query} {placeholder} autocomplete="off" class="pl-9" />
            </span>
        </label>
        <label class="grid gap-1.5 text-sm font-medium sm:min-w-44">
            <span>Academic cycle</span>
            <select value={cycleId} onchange={selectCycle} class="min-h-10 rounded-md border border-input bg-background px-3 text-sm">
                <option value="">Current cycle</option>
                {#each cycles as cycle (cycle.id)}
                    <option value={String(cycle.id)}>{cycle.label}</option>
                {/each}
            </select>
        </label>
    </div>

    {#if query.trim().length < 2}
        <p class="text-xs text-muted-foreground">Enter at least two characters to search by name or reference.</p>
    {:else if isLoading && results.length === 0}
        <p class="flex items-center gap-2 text-sm text-muted-foreground" role="status"><LoaderCircle class="size-4 animate-spin" aria-hidden="true" /> Searching applicants…</p>
    {:else if errorMessage}
        <p class="flex items-start gap-2 text-sm text-destructive" role="alert"><AlertCircle class="mt-0.5 size-4 shrink-0" aria-hidden="true" />{errorMessage}</p>
    {:else if results.length === 0}
        <p class="text-sm text-muted-foreground">No applicants match this search in the selected cycle.</p>
    {:else}
        <ul class="divide-y divide-border rounded-lg border border-border" aria-label="Applicant search results">
            {#each results as result (result.id)}
                <li>
                    <button type="button" class="flex min-h-14 w-full items-center justify-between gap-3 px-3 py-2 text-left transition-colors hover:bg-muted/50 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring" onclick={() => onSelect(result)}>
                        <span class="min-w-0">
                            <span class="block truncate text-sm font-medium text-foreground">{result.name}</span>
                            <span class="block truncate text-xs text-muted-foreground">{result.reference_code} · {result.cycle_label}</span>
                        </span>
                        <span class="shrink-0 text-xs font-medium text-primary">Open</span>
                    </button>
                </li>
            {/each}
        </ul>
        {#if hasMore}
            <Button variant="outline" class="w-full" disabled={isLoading} onclick={loadMore}>
                {isLoading ? 'Loading…' : 'Load more applicants'}
            </Button>
        {/if}
    {/if}
</section>
