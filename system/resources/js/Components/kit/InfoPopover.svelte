<script>
  /**
   * Contextual help — single glyph: question mark (CircleHelp).
   * Opens on hover/focus; click also toggles for touch. No separate "?" badge label.
   */
  import { HelpCircle } from 'lucide-svelte';

  let {
    content = '',
    title = '',
    align = 'center',
    class: className = '',
  } = $props();

  let open = $state(false);
  let pinned = $state(false);
  let buttonEl = $state(null);
  let panelEl = $state(null);
  let shiftX = $state(0);
  let leaveTimer = null;
  const tooltipId = 'tip-' + Math.random().toString(36).slice(2, 9);

  function clampToViewport() {
    if (!open || !panelEl || typeof window === 'undefined') return;
    const rect = panelEl.getBoundingClientRect();
    const margin = 12;
    const vw = document.documentElement.clientWidth || window.innerWidth;
    if (rect.left < margin) {
      shiftX = Math.round(margin - rect.left);
    } else if (rect.right > vw - margin) {
      shiftX = Math.round(vw - margin - rect.right);
    }
  }

  $effect(() => {
    if (open && panelEl) {
      shiftX = 0;
      queueMicrotask(clampToViewport);
    } else {
      shiftX = 0;
    }
  });
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
    if (pinned) return;
    clearLeave();
    leaveTimer = setTimeout(() => {
      if (!pinned) open = false;
    }, 120);
  }

  function toggle() {
    clearLeave();
    if (!open) {
      open = true;
      pinned = true;
    } else if (!pinned) {
      pinned = true;
    } else {
      pinned = false;
      open = false;
    }
  }

  function handleKeydown(e) {
    if (e.key === 'Escape') {
      pinned = false;
      open = false;
    }
  }

  function handleOutsideClick(e) {
    if (!open) return;
    if (buttonEl?.contains(e.target)) return;
    if (panelEl?.contains(e.target)) return;
    pinned = false;
    open = false;
  }
</script>

<svelte:window onkeydown={handleKeydown} onmousedown={handleOutsideClick} onresize={() => { shiftX = 0; queueMicrotask(clampToViewport); }} />

<span class="relative inline-flex items-center {className}">
  <button
    bind:this={buttonEl}
    type="button"
    class="inline-flex size-5 items-center justify-center rounded-full text-muted-foreground transition-colors hover:bg-muted hover:text-foreground focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring"
    onclick={toggle}
    onmouseenter={show}
    onmouseleave={hideSoon}
    onfocus={show}
    onblur={hideSoon}
    aria-expanded={open}
    aria-describedby={open ? tooltipId : undefined}
    aria-label="More information"
  >
    <HelpCircle class="size-3.5" />
  </button>

  {#if open}
    <div
      bind:this={panelEl}
      id={tooltipId}
      role="tooltip"
      style={shiftX !== 0 ? `margin-left: ${shiftX}px;` : undefined}
      class={`absolute bottom-full z-50 mb-2 w-72 max-w-[calc(100vw-1.5rem)] rounded-xl border border-border/80 bg-card/95 p-3 text-xs leading-relaxed text-muted-foreground shadow-xl backdrop-blur-md transition-all ${
        align === 'left'
          ? 'left-0'
          : align === 'right'
          ? 'right-0'
          : 'left-1/2 -translate-x-1/2'
      }`}
      onmouseenter={show}
      onmouseleave={hideSoon}
    >
      {#if title}
        <div class="mb-1 text-xs font-semibold text-foreground">{title}</div>
      {/if}
      <div class="whitespace-pre-line">{content}</div>
      <div
        class={`absolute top-full border-4 border-transparent border-t-border/80 ${
          align === 'left'
            ? 'left-4'
            : align === 'right'
            ? 'right-4'
            : 'left-1/2 -translate-x-1/2'
        }`}
        aria-hidden="true"
      ></div>
    </div>
  {/if}
</span>
