<script lang="ts">
  let { title, visible = undefined, class: className = '', children } = $props();
</script>

{#if visible !== false}
  <div class="space-y-3 {className}">
    <h4 class="text-sm font-semibold tracking-tight border-b pb-2">{title}</h4>
    <div class="text-sm text-muted-foreground space-y-2">
      {@render children?.()}
    </div>
  </div>
{/if}
