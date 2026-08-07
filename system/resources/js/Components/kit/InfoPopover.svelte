<script>
  /**
   * Contextual help — single glyph: question mark (CircleHelp).
   * Opens on hover/focus; click also toggles for touch. No separate "?" badge label.
   */
  import { CircleHelp } from 'lucide-svelte';

  let { content, class: className = '' } = $props();

  let open = $state(false);
  let buttonEl = $state(null);
  let panelEl = $state(null);
  let leaveTimer = null;

  function clearLeave() {
    if (leaveTimer) {
      clearTimeout(leaveTimer);
      leaveTimer = null;
    }
  }

  function show() {
    clearLeave();
    open = true;
  }

  function hideSoon() {
    clearLeave();
    leaveTimer = setTimeout(() => {
      open = false;
    }, 120);
  }

  function toggle() {
    clearLeave();
    open = !open;
  }

  function handleKeydown(e) {
    if (e.key === 'Escape') open = false;
  }

  function handleOutsideClick(e) {
    if (!open) return;
    if (buttonEl?.contains(e.target)) return;
    if (panelEl?.contains(e.target)) return;
    open = false;
  }
</script>

<svelte:window onkeydown={handleKeydown} onmousedown={handleOutsideClick} />

<span class="relative inline-flex items-center {className}">
  <button
    bind:this={buttonEl}
    type="button"
    class="inline-flex size-6 items-center justify-center rounded-full text-muted-foreground transition-colors hover:bg-muted hover:text-foreground focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring"
    onclick={toggle}
    onmouseenter={show}
    onmouseleave={hideSoon}
    onfocus={show}
    onblur={hideSoon}
    aria-expanded={open}
    aria-label="More information"
  >
    <CircleHelp class="h-4 w-4" />
  </button>

  {#if open}
    <div
      bind:this={panelEl}
      role="tooltip"
      class="absolute bottom-full left-1/2 z-50 mb-2 w-64 -translate-x-1/2 rounded-xl border border-border/70 bg-card p-3 text-sm text-foreground shadow-lg"
      onmouseenter={show}
      onmouseleave={hideSoon}
    >
      {content}
      <div
        class="absolute left-1/2 top-full -translate-x-1/2 border-4 border-transparent border-t-border"
        aria-hidden="true"
      ></div>
    </div>
  {/if}
</span>
