<script>
  let {
    suggestions = [],
    value = $bindable(''),
    mode = 'replace',
    separator = '\n',
    maxlength = 2000,
    disabled = false,
    onSelect = undefined,
    ariaLabel = 'Quick suggestions',
    class: className = '',
  } = $props();

  function applySuggestion(item) {
    const candidateText = (typeof item === 'string' ? item : item.text ?? item.template ?? item.label ?? '').trim();
    if (!candidateText) return;
    const currentTrimmed = String(value ?? '').trim();
    let nextValue = candidateText;
    if (mode === 'append') {
      if (!currentTrimmed) {
        nextValue = candidateText;
      } else if (currentTrimmed.includes(candidateText)) {
        return;
      } else {
        nextValue = `${currentTrimmed}${separator}${candidateText}`;
      }
    }
    if (maxlength && nextValue.length > maxlength) return;
    value = nextValue;
    onSelect?.(nextValue, item);
  }
</script>

{#if suggestions?.length && !disabled}
  <div class="flex flex-wrap items-center gap-1.5 {className}" role="group" aria-label={ariaLabel}>
    <span class="text-muted-foreground/80 mr-0.5 text-[11px] font-normal select-none" aria-hidden="true">{mode === 'append' ? '+ Add:' : 'Suggestions:'}</span>
    {#each suggestions as item, index (typeof item === 'string' ? item : item.code ?? `${item.label}-${index}`)}
      {@const label = typeof item === 'string' ? item : item.label}
      <button
        type="button"
        {disabled}
        class="border-border/70 bg-muted/35 text-muted-foreground hover:border-border hover:bg-muted hover:text-foreground focus-visible:ring-ring inline-flex items-center rounded-full border px-2.5 py-0.5 text-xs font-normal transition-colors focus-visible:ring-2 focus-visible:outline-none disabled:pointer-events-none disabled:opacity-50"
        onclick={() => applySuggestion(item)}
      >
        {#if mode === 'append'}<span class="mr-1 opacity-70" aria-hidden="true">+</span>{/if}{label}
      </button>
    {/each}
  </div>
{/if}
