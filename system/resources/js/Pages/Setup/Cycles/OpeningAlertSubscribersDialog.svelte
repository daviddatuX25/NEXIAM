<script>
    import * as Dialog from '@/Components/ui/dialog';
    import { Button } from '@/Components/ui/button';
    import { Input } from '@/Components/ui/input';
    import { Label } from '@/Components/ui/label';
    import { SvelteURLSearchParams } from 'svelte/reactivity';

    let open = $state(false);
    let busy = $state(false);
    let message = $state('');
    let search = $state('');
    let status = $state('');
    let appliedSearch = '';
    let appliedStatus = '';
    let rows = $state([]);
    let page = $state(1);
    let requestedPage = $state(1);
    let lastPage = $state(1);
    let total = $state(null);
    let controller;
    let generation = 0;

    const labels = {
        pending: 'Awaiting email confirmation',
        verified: 'Waiting for next opening',
        consumed: 'Opening alert prepared',
        unsubscribed: 'Unsubscribed',
        expired: 'Expired',
    };

    const deliveryLabels = {
        pending: 'Email queued',
        processing: 'Email in progress',
        delivered: 'Mail transport accepted',
        retrying: 'Retry scheduled',
        failed_terminal: 'Email delivery failed',
        outcome_unknown: 'Mail outcome unknown',
        superseded: 'Email no longer applicable',
    };

    $effect(() => {
        if (open) {
            load(1);
        }
        return () => {
            generation += 1;
            controller?.abort();
        };
    });

    async function load(targetPage) {
        requestedPage = targetPage;
        const requestGeneration = ++generation;
        controller?.abort();
        controller = new AbortController();
        busy = true;
        message = '';
        rows = [];
        total = null;
        try {
            const query = new SvelteURLSearchParams({ page: String(targetPage) });
            if (appliedSearch) query.set('search', appliedSearch);
            if (appliedStatus) query.set('status', appliedStatus);
            const response = await fetch(`/setup/application-opening-alerts?${query}`, {
                headers: { Accept: 'application/json' },
                credentials: 'same-origin',
                signal: controller.signal,
            });
            if (!response.ok) {
                throw new Error(response.status === 403
                    ? 'You no longer have access to these signups.'
                    : response.status === 429
                      ? 'Too many requests. Wait a moment, then try again.'
                      : 'Signups could not be loaded. Try again.');
            }
            const data = await response.json();
            if (requestGeneration !== generation) return;
            rows = data.data;
            page = data.current_page;
            lastPage = data.last_page;
            total = data.total;
        } catch (error) {
            if (requestGeneration === generation && error?.name !== 'AbortError') {
                message = error instanceof Error ? error.message : 'Signups could not be loaded. Try again.';
            }
        } finally {
            if (requestGeneration === generation) busy = false;
        }
    }

    function filter(event) {
        event.preventDefault();
        appliedSearch = search.trim();
        appliedStatus = status;
        load(1);
    }

    function date(value) {
        return value ? new Intl.DateTimeFormat(undefined, {
            dateStyle: 'medium', timeZone: 'UTC',
        }).format(new Date(value)) : 'Not confirmed';
    }
</script>

<Dialog.Root bind:open>
    <Dialog.Trigger>
        {#snippet child({ props })}
            <Button {...props} variant="outline">View opening-alert signups</Button>
        {/snippet}
    </Dialog.Trigger>
    <Dialog.Portal>
        <Dialog.Overlay />
        <Dialog.Content class="max-h-[90dvh] min-w-0 overflow-y-auto sm:max-w-3xl">
            <Dialog.Header>
                <Dialog.Title>Application opening-alert signups</Dialog.Title>
                <Dialog.Description>
                    People who asked for an email about the next opening. These are not applications or reserved places. Dates are shown in UTC.
                </Dialog.Description>
            </Dialog.Header>
            <form onsubmit={filter} class="my-4 flex flex-col gap-3 sm:flex-row sm:items-end">
                <div class="min-w-0 flex-1 space-y-1.5">
                    <Label for="opening-alert-search">Email address</Label>
                    <Input id="opening-alert-search" bind:value={search} maxlength={160} placeholder="Search email" type="search" />
                </div>
                <div class="space-y-1.5">
                    <Label for="opening-alert-status">Status</Label>
                    <select id="opening-alert-status" bind:value={status} class="border-input bg-background focus-visible:ring-ring min-h-10 w-full rounded-md border px-3 text-sm focus-visible:ring-2 sm:w-52">
                        <option value="">All statuses</option>
                        {#each Object.entries(labels) as [value, label] (value)}
                            <option {value}>{label}</option>
                        {/each}
                    </select>
                </div>
                <Button type="submit" variant="outline" disabled={busy} class="min-h-10">Search</Button>
            </form>
            <div aria-live="polite" aria-busy={busy} class="min-h-36">
                {#if busy}
                    <p class="text-muted-foreground py-8 text-center text-sm">Loading signups…</p>
                {:else if message}
                    <div class="space-y-3 py-4" role="alert">
                        <p class="text-destructive text-sm">{message}</p>
                        <Button variant="outline" onclick={() => load(requestedPage)}>Try again</Button>
                    </div>
                {:else if rows.length === 0}
                    <p class="text-muted-foreground py-8 text-center text-sm">No signups match this view.</p>
                {:else}
                    <ul class="divide-border divide-y">
                        {#each rows as row (row.id)}
                            <li class="flex min-w-0 flex-col gap-1 py-3 sm:flex-row sm:items-start sm:justify-between sm:gap-4">
                                <div class="min-w-0">
                                    <p class="break-all text-sm font-medium">{row.email ?? 'Email address removed'}</p>
                                    <p class="text-muted-foreground text-xs">Signed up {date(row.created_at)} · {row.verified_at ? `Confirmed ${date(row.verified_at)}` : 'Not confirmed'}</p>
                                </div>
                                <div class="text-muted-foreground space-y-1 text-xs sm:text-right">
                                    <p>{labels[row.state] ?? row.state}</p>
                                    {#if row.delivery_status}
                                        <p>{deliveryLabels[row.delivery_status] ?? row.delivery_status}</p>
                                    {/if}
                                </div>
                            </li>
                        {/each}
                    </ul>
                {/if}
            </div>
            <div class="border-border mt-4 flex flex-wrap items-center justify-between gap-3 border-t pt-4">
                <p class="text-muted-foreground text-sm">{total === null ? 'Signups' : `${total} signups · Page ${page} of ${lastPage}`}</p>
                <div class="flex gap-2" aria-label="Signup pages">
                    <Button variant="outline" disabled={busy || page <= 1} onclick={() => load(page - 1)}>Previous</Button>
                    <Button variant="outline" disabled={busy || page >= lastPage} onclick={() => load(page + 1)}>Next</Button>
                </div>
            </div>
        </Dialog.Content>
    </Dialog.Portal>
</Dialog.Root>
