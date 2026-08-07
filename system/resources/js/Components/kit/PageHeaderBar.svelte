<script>
  /**
   * Management page header: description + action cluster.
   * Legacy pattern: icon always visible; label `hidden sm:inline` (Create/Import/Export).
   */
  let {
    title = '',
    description = '',
    actions,
    class: className = '',
  } = $props();
</script>

<div class="flex items-start justify-between gap-3 min-w-0 {className}">
  <div class="min-w-0">
    {#if title}
      <h1 class="text-2xl font-semibold tracking-tight text-foreground sm:text-3xl">{title}</h1>
    {/if}
    {#if description}
      <p class="text-sm text-muted-foreground {title ? 'mt-1' : ''}">{description}</p>
    {/if}
  </div>
  {#if actions}
    <div class="flex flex-wrap items-center justify-end gap-2 sm:gap-3 shrink-0">
      {@render actions()}
    </div>
  {/if}
</div>
