<script>
  let { value = 'month', onChange = () => {} } = $props();
</script>

<div class="inline-flex min-h-11 items-center rounded-lg bg-muted p-1" role="group" aria-label="Calendar view">
  {#each ['month', 'strip'] as view (view)}
    <button
      type="button"
      class="min-h-11 rounded-md px-3 text-sm font-medium capitalize transition-colors focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2 {value === view ? 'bg-background text-foreground shadow-sm' : 'text-muted-foreground hover:text-foreground'}"
      aria-pressed={value === view}
      onclick={() => onChange(view)}
    >
      {view === 'month' ? 'Month' : 'Strip'}
    </button>
  {/each}
</div>
