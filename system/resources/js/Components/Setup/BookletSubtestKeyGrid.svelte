<script>
    import { AlertCircle, CheckCircle2 } from 'lucide-svelte';

    /** @typedef {{ id: string | number; local_number: number; option_tokens: string[] }} BookletItem */
    /** @typedef {{ id: string | number; code: string; name: string; items: BookletItem[] }} BookletSubtest */

    /** @type {{ subtest: BookletSubtest; keys: Record<string, string>; readOnly?: boolean; onSelectKey: (item: BookletItem, token: string) => void; onClearKey: (item: BookletItem) => void }} */
    let {
        subtest,
        keys,
        readOnly = false,
        onSelectKey,
        onClearKey,
    } = $props();

    let answeredCount = $derived(
        subtest.items.filter((item) => item.key_required !== false && selectedToken(item) !== undefined).length,
    );
    let requiredCount = $derived(
        subtest.items.filter((item) => item.key_required !== false).length,
    );

    function selectedToken(item) {
        return keys[String(item.id)];
    }

    function handleSelect(item, token) {
        if (readOnly || !item.key_required) {
            return;
        }

        if (selectedToken(item) === token) {
            onClearKey(item);
            return;
        }

        onSelectKey(item, token);
    }
</script>

<section class="space-y-4" aria-labelledby="section-key-grid-title">
    <header class="flex flex-wrap items-center justify-between gap-3 border-b border-border pb-3">
        <div class="min-w-0">
            <h2 id="section-key-grid-title" class="truncate text-base font-semibold text-foreground">
                {subtest.name}
            </h2>
        </div>

        <div class="inline-flex shrink-0 items-center gap-1.5 rounded-full border border-border bg-muted/40 px-2.5 py-1 text-xs font-medium text-muted-foreground" aria-live="polite">
            {#if answeredCount === requiredCount}
                <CheckCircle2 class="size-3.5 text-emerald-600 dark:text-emerald-400" aria-hidden="true" />
            {:else}
                <AlertCircle class="size-3.5 text-amber-600 dark:text-amber-400" aria-hidden="true" />
            {/if}
            <span>{answeredCount} / {requiredCount} keys required</span>
        </div>
    </header>

    <div class="grid grid-cols-1 gap-2 xl:grid-cols-2">
        {#each subtest.items as item (item.id)}
            {@const selected = selectedToken(item)}
            <div class="flex min-w-0 items-center gap-3 rounded-lg border border-border/70 bg-card/50 px-3 py-2.5">
                <span class="flex size-8 shrink-0 items-center justify-center rounded-md bg-muted font-mono text-xs font-bold tabular-nums text-muted-foreground">
                    {String(item.local_number).padStart(2, '0')}
                </span>

                {#if item.key_required !== false}
                    <div class="flex min-w-0 flex-1 flex-wrap items-center gap-1.5">
                        {#each item.option_tokens as token (token)}
                            <button
                                type="button"
                                class={selected === token
                                    ? 'rounded-md bg-primary px-2.5 py-1 font-mono text-xs font-bold text-primary-foreground shadow-xs focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2'
                                    : 'rounded-md border border-input bg-background px-2.5 py-1 font-mono text-xs font-semibold text-foreground hover:bg-muted/50 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2'}
                                aria-pressed={selected === token}
                                aria-label="Item {item.local_number}: {token}{selected === token ? ', selected' : ''}"
                                disabled={readOnly}
                                onclick={() => handleSelect(item, token)}
                            >
                                {token}
                            </button>
                        {/each}
                    </div>
                    {#if selected === undefined}
                        <span class="shrink-0 text-[11px] font-medium text-muted-foreground/70">Unset</span>
                    {/if}
                {:else}
                    <span class="text-xs text-muted-foreground">No key required</span>
                {/if}
            </div>
        {/each}
    </div>
</section>
