<script>
  import { FileText, Folder } from 'lucide-svelte';

  let {
    id = null,
    label,
    code = null,
    itemKind = 'document',
    isMandatory = true,
    trackLabel = null,
    density = 'comfortable',
    class: className = '',
    status = null,
    metadata = null,
    content = null,
    actions = null,
    selectable = null,
  } = $props();

  const headingId = $derived(id ?? `document-requirement-${label.toLowerCase().replace(/[^a-z0-9]+/g, '-')}`);
  const isContainer = $derived(itemKind === 'container');
  const compact = $derived(density === 'compact');
</script>

<article
  data-requirement-card
  data-item-kind={isContainer ? 'container' : 'document'}
  aria-labelledby={headingId}
  class="relative min-w-0 rounded-xl border transition-shadow {isContainer ? 'border-amber-500/20 bg-card' : 'border-border/80 bg-card shadow-[0_2px_8px_-2px_rgba(0,0,0,0.06)]'} {className}"
>
  <div aria-hidden="true" class="pointer-events-none absolute inset-0 overflow-hidden rounded-xl">
    {#if isContainer}
      <div class="h-2 border-b border-amber-500/20 bg-amber-500/[0.06] dark:bg-amber-400/[0.06]"></div>
    {:else}
      <div class="absolute top-0 right-0 size-5 rounded-bl-lg border-b border-l border-border/70 bg-muted/60"></div>
    {/if}
  </div>

  <div class="relative min-w-0">
    <header class="flex min-w-0 items-center justify-between gap-3 border-b border-dashed border-border/70 {compact ? 'px-3 py-1.5' : 'px-4 py-3'}">
        {#if selectable}
          <div class="flex items-center shrink-0">
            {@render selectable()}
          </div>
        {/if}
      <div class="flex min-w-0 flex-wrap items-center gap-2">
        {#if isContainer}
          <Folder class="size-4 shrink-0 text-amber-700 dark:text-amber-300" aria-hidden="true" />
          <span class="text-xs font-medium text-foreground">Folder / Envelope</span>
        {:else}
          <FileText class="size-4 shrink-0 text-muted-foreground" aria-hidden="true" />
          <span class="text-xs font-medium text-foreground">Document / Paper</span>
        {/if}
        {#if code}
          <span class="font-mono text-[11px] text-muted-foreground">{code}</span>
        {/if}
        <span class="rounded-full px-2 py-0.5 text-[11px] font-medium {isMandatory ? 'bg-amber-500/10 text-amber-800 dark:text-amber-300' : 'bg-muted text-muted-foreground'}">
          {isMandatory ? 'Mandatory' : 'Optional'}
        </span>
        {#if trackLabel}
          <span class="text-xs text-muted-foreground">{trackLabel}</span>
        {/if}
      </div>
      {#if status}
        <div class="shrink-0">{@render status()}</div>
      {/if}
    </header>

    <div class="min-w-0 {compact ? 'px-3 py-2' : 'p-4'}">
      <h3 id={headingId} class="min-w-0 break-words text-foreground {compact ? 'text-sm font-semibold leading-snug' : 'font-semibold'}">{label}</h3>
      {#if metadata}
        <div class="mt-2">{@render metadata()}</div>
      {/if}
      {#if content}
        <div class="mt-2 empty:hidden">{@render content()}</div>
      {/if}
    </div>

    {#if actions}
      <div data-requirement-card-actions class="flex flex-nowrap items-center justify-end gap-2 whitespace-nowrap shrink-0 border-t border-border/60 {compact ? 'px-3 py-1.5' : 'px-4 py-3'}">
        {@render actions()}
      </div>
    {/if}
  </div>
</article>
