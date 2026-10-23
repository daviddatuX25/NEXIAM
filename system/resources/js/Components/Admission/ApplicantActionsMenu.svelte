<script>
    import { onDestroy } from 'svelte';
    import { SvelteURLSearchParams } from 'svelte/reactivity';
    import { router, usePage } from '@inertiajs/svelte';
    import { Button } from '@/Components/ui/button';
    import * as DropdownMenu from '@/Components/ui/dropdown-menu';
    import { groupApplicantDestinations } from '@/lib/applicant-actions';
    import { ChevronDown, LoaderCircle, AlertCircle } from 'lucide-svelte';

    let {
        attemptId = null,
        context = {},
        label = 'Applicant actions',
        class: className = '',
    } = $props();

    const page = usePage();
    let open = $state(false);
    let loaded = $state(false);
    let loading = $state(false);
    let errorMessage = $state('');
    let destinations = $state([]);
    let controller = null;
    let requestVersion = 0;
    let contextSignature = '';
    const actorContext = $derived([
        page.props.auth?.user?.id ?? '',
        page.props.auth?.staff_inactivity_generation ?? '',
        page.props.practice_workspace?.id ?? page.props.practice_workspace?.key ?? '',
        JSON.stringify(page.props.auth?.user?.scope ?? {}),
        attemptId ?? '',
        ...Object.entries(context ?? {}).sort(([left], [right]) => left.localeCompare(right)).flat(),
    ].join(':'));
    const groupedDestinations = $derived(groupApplicantDestinations(destinations));

    function clearProjection() {
        requestVersion++;
        controller?.abort();
        controller = null;
        loading = false;
        loaded = false;
        destinations = [];
        errorMessage = '';
    }

    async function loadProjection() {
        if (!attemptId || loading) return;
        clearProjection();
        const version = requestVersion;
        const requestController = new AbortController();
        controller = requestController;
        loading = true;
        const query = new SvelteURLSearchParams();
        for (const [key, value] of Object.entries(context ?? {})) {
            if (value !== null && value !== undefined && value !== '') query.set(key, String(value));
        }
        const suffix = query.size ? `?${query}` : '';
        try {
            const response = await fetch(`/staff/scan/applicants/${encodeURIComponent(attemptId)}/actions${suffix}`, {
                credentials: 'same-origin',
                headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                signal: requestController.signal,
            });
            const payload = await response.json().catch(() => ({}));
            if (!response.ok) throw new Error(payload.message ?? 'Applicant actions are unavailable.');
            if (version !== requestVersion || requestController.signal.aborted) return;
            destinations = Array.isArray(payload.destinations) ? payload.destinations : [];
            loaded = true;
        } catch (error) {
            if (error.name !== 'AbortError' && version === requestVersion) errorMessage = error.message || 'Applicant actions are unavailable.';
        } finally {
            if (version === requestVersion) {
                loading = false;
                if (controller === requestController) controller = null;
            }
        }
    }

    function handleOpenChange(value) {
        open = value;
        if (value) void loadProjection();
        else clearProjection();
    }

    $effect(() => {
        const nextSignature = actorContext;
        if (nextSignature !== contextSignature) {
            contextSignature = nextSignature;
            open = false;
            clearProjection();
        }
    });

    onDestroy(clearProjection);
</script>

{#if attemptId}
    <DropdownMenu.Root bind:open onOpenChange={handleOpenChange}>
        <DropdownMenu.Trigger>
            {#snippet child({ props })}
                <Button {...props} type="button" variant="outline" class={`min-h-11 gap-2 ${className}`}>
                    {label}<ChevronDown class="size-4" aria-hidden="true" />
                </Button>
            {/snippet}
        </DropdownMenu.Trigger>
        <DropdownMenu.Content align="end" class="w-72 max-w-[calc(100vw-2rem)]" aria-label={label}>
            {#if loading}
                <p class="flex items-center gap-2 px-2 py-3 text-sm text-muted-foreground" role="status"><LoaderCircle class="size-4 animate-spin" aria-hidden="true" />Loading authorized destinations…</p>
            {:else if errorMessage}
                <p class="flex items-start gap-2 px-2 py-2 text-sm text-destructive" role="alert"><AlertCircle class="mt-0.5 size-4 shrink-0" aria-hidden="true" />{errorMessage}</p>
                <DropdownMenu.Item onSelect={(event) => { event.preventDefault(); void loadProjection(); }} class="min-h-11">Retry</DropdownMenu.Item>
            {:else if loaded && destinations.length === 0}
                <p class="px-2 py-3 text-sm text-muted-foreground">No other applicant destinations are available here.</p>
            {:else}
                {#each groupedDestinations as [group, links] (group)}
                    <p class="px-2 py-1 text-xs font-semibold text-muted-foreground">{group}</p>
                    {#each links as destination (destination.key + destination.url)}
                        {#if destination.kind === 'document'}
                            <DropdownMenu.Item>
                                {#snippet child({ props })}
                                    <a {...props} class="flex min-h-11 items-center rounded-md px-2 py-2 text-sm font-medium" href={destination.url} target="_blank" rel="noopener noreferrer">{destination.label}</a>
                                {/snippet}
                            </DropdownMenu.Item>
                        {:else}
                            <DropdownMenu.Item class="min-h-11 text-sm" onSelect={() => router.visit(destination.url)}>
                                {destination.label}
                            </DropdownMenu.Item>
                        {/if}
                    {/each}
                {/each}
            {/if}
        </DropdownMenu.Content>
    </DropdownMenu.Root>
{/if}
