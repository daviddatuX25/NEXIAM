<script>
  let { state = 'ready' } = $props();
</script>

<p class="text-xs font-semibold uppercase tracking-wider text-muted-foreground" data-testid="page-state">
  Page state: <span class="text-foreground">{state}</span>
</p>
