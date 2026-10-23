<script>
  let { title, visible = true, class: className = '', children } = $props();
</script>

{#if visible}
  <div class="space-y-2.5 {className}">
    <h4 class="border-b border-border/40 pb-1.5 text-xs font-semibold uppercase tracking-wider text-muted-foreground">
      {title}
    </h4>
    <div class="text-sm text-foreground">
      {@render children?.()}
    </div>
  </div>
{/if}
