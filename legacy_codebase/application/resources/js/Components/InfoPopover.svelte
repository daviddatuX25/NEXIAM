<script>
  import { Info } from 'lucide-svelte';

  let { content, label = null } = $props();

  let open = $state(false);
  let buttonEl = $state(null);
  let panelEl = $state(null);

  function toggle() {
    open = !open;
  }

  function handleKeydown(e) {
    if (e.key === 'Escape') open = false;
  }

  function handleOutsideClick(e) {
    if (!open) return;
    if (buttonEl && buttonEl.contains(e.target)) return;
    if (panelEl && panelEl.contains(e.target)) return;
    e.preventDefault();
    open = false;
  }
</script>

<svelte:window onkeydown={handleKeydown} onmousedown={handleOutsideClick} />

<span class="relative inline-flex items-center gap-1">
  {#if label}
    <span class="rounded-full bg-muted px-2 py-0.5 text-xs font-semibold text-muted-foreground">{label}</span>
  {/if}
  <button
    bind:this={buttonEl}
    type="button"
    class="inline-flex h-5 w-5 items-center justify-center rounded-full text-muted-foreground hover:text-foreground hover:bg-muted transition-colors"
    onclick={toggle}
    aria-expanded={open}
    aria-label="More info"
  >
    <Info class="h-4 w-4" />
  </button>

  {#if open}
    <div
      bind:this={panelEl}
      role="tooltip"
      class="absolute bottom-full left-1/2 z-50 mb-2 w-64 -translate-x-1/2 rounded-xl border border-border bg-card p-3 text-sm text-foreground shadow-lg"
    >
      {content}
      <div class="absolute left-1/2 top-full -translate-x-1/2 border-4 border-transparent border-t-border" aria-hidden="true"></div>
    </div>
  {/if}
</span>
