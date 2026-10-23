<script>
  let {
    id = '',
    title = '',
    subtitle = '',
    status = null,
    selection = null,
    leading = null,
    titleSuffix = null,
    summary = null,
    details = null,
     actions = null,
     expanded = $bindable(),
   } = $props();
 
   const isExpanded = $derived(Boolean(expanded));
   const detailsId = `card-details-${id}`;
 </script>
<li
  class="group flex min-w-0 flex-col justify-between rounded-xl border border-border/80 bg-card p-4 shadow-xs transition-all hover:border-primary/40 hover:shadow-sm"
>
  <div class="min-w-0 space-y-2.5">
    <div class="flex items-start justify-between gap-3">
      <div class="flex min-w-0 items-start gap-2.5">
        {#if selection}
          <div class="pt-0.5">{@render selection()}</div>
        {/if}
        {#if leading}
          <div class="shrink-0">{@render leading()}</div>
        {/if}
        <div class="min-w-0">
          <div class="flex min-w-0 items-center gap-1.5">
            <h3 class="min-w-0 line-clamp-2 break-words text-sm font-bold text-foreground">{title}</h3>
            {#if titleSuffix}
              <div class="shrink-0">{@render titleSuffix()}</div>
            {/if}
          </div>
          {#if subtitle}
            <p class="truncate text-xs text-muted-foreground">{subtitle}</p>
          {/if}
        </div>
      </div>
      {#if status}
        <div class="shrink-0">{@render status()}</div>
      {/if}
    </div>

    {#if summary}
      <div class="text-xs text-muted-foreground">{@render summary()}</div>
    {/if}
  </div>

   {#if isExpanded && details}
     <div
       id={detailsId}
       class="mt-3 space-y-2.5 border-t border-border/60 pt-3 text-xs animate-in fade-in-50 duration-150"
     >
       {@render details()}
     </div>
   {/if}
  <div class="mt-3 flex min-w-0 items-center justify-between gap-2 border-t border-border/50 pt-2.5">
    <div class="min-w-0 flex-1">
      {#if actions}
        {@render actions()}
      {/if}
    </div>
    {#if details}
      <button
        type="button"
         class="inline-flex min-h-[36px] items-center gap-1 rounded-md px-2.5 text-xs font-semibold text-muted-foreground hover:bg-muted hover:text-foreground focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring"
         aria-expanded={isExpanded}
         aria-controls={detailsId}
         onclick={() => (expanded = !isExpanded)}
       >
         <span>{isExpanded ? 'Less' : 'Details'}</span>
         <span class="text-[10px] transition-transform {isExpanded ? 'rotate-180' : ''}">▼</span>
      </button>
    {/if}
  </div>
</li>
