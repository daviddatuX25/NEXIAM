<script>
  let { title, visible = undefined, class: className = '', children } = $props();
</script>

{#if visible !== false}
  <div class="space-y-2.5 {className}">
    <h4 class="text-sm font-semibold tracking-tight text-foreground">{title}</h4>
    <div class="space-y-2 text-sm leading-relaxed text-muted-foreground">
      {@render children?.()}
    </div>
  </div>
{/if}
