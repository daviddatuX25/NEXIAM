<script>
  import { Check, Copy } from 'lucide-svelte';

  let { tokens = [], onselect = () => {} } = $props();
  let copiedToken = $state(null);
  let copyTimer;

  async function copyToken(token) {
    try {
      await navigator.clipboard.writeText(token);
      copiedToken = token;
      clearTimeout(copyTimer);
      copyTimer = setTimeout(() => {
        copiedToken = null;
      }, 1600);
    } catch {
      copiedToken = null;
    }
  }
</script>

<section class="space-y-3" aria-labelledby="placeholder-picker-heading">
  <div class="flex flex-wrap items-baseline justify-between gap-x-3 gap-y-1">
    <h2 id="placeholder-picker-heading" class="text-sm font-semibold text-foreground">Available Placeholders</h2>
    <p class="text-xs text-muted-foreground">Click to insert into message</p>
  </div>

  {#if tokens.length > 0}
    <div class="flex flex-wrap gap-2">
      {#each tokens as token (token.token)}
        <div class="group flex max-w-full items-stretch overflow-hidden rounded-lg border border-border/70 bg-background shadow-xs transition-colors hover:border-primary/40 hover:bg-muted/40">
          <button
            type="button"
            class="flex min-w-0 flex-col items-start gap-1 px-3 py-2 text-left outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-inset"
            title={`Sample: ${token.sample}`}
            aria-label={`Insert ${token.token}, ${token.label}. Sample: ${token.sample}`}
            onclick={() => onselect(token.token)}
          >
            <code class="max-w-full truncate font-mono text-xs font-semibold text-foreground">{token.token}</code>
            <span class="max-w-full truncate text-xs text-muted-foreground">{token.label}</span>
          </button>
          <button
            type="button"
            class="flex shrink-0 items-center justify-center border-l border-border/60 px-2 text-muted-foreground transition-colors hover:bg-muted hover:text-foreground focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-inset"
            aria-label={copiedToken === token.token ? `Copied ${token.token}` : `Copy ${token.token}`}
            title={copiedToken === token.token ? 'Copied' : `Copy ${token.token}`}
            onclick={() => copyToken(token.token)}
          >
            {#if copiedToken === token.token}
              <Check class="size-3.5 text-emerald-600 dark:text-emerald-400" />
            {:else}
              <Copy class="size-3.5" />
            {/if}
          </button>
        </div>
      {/each}
    </div>
  {:else}
    <p class="rounded-lg border border-dashed border-border px-3 py-4 text-sm text-muted-foreground">
      No placeholders are available for this event.
    </p>
  {/if}

  <p class="sr-only" aria-live="polite">{copiedToken ? `${copiedToken} copied to clipboard.` : ''}</p>
</section>
