<script>
  import { X, Keyboard } from 'lucide-svelte';

  let { open = false, onClose = () => {} } = $props();

  const isMac = typeof navigator !== 'undefined' && /Mac|iPod|iPhone|iPad/.test(navigator.platform || navigator.userAgent);
  const modKey = isMac ? '⌘' : 'Ctrl';
  const altKey = isMac ? '⌥' : 'Alt';
  const shiftKey = isMac ? '⇧' : 'Shift';

  const shortcutSections = [
    {
      title: 'Selection & Tools',
      shortcuts: [
        { keys: ['V'], description: 'Select / Marquee tool' },
        { keys: ['H'], description: 'Pan / Hand tool' },
        { keys: ['Click'], description: 'Select component exclusively' },
        { keys: [shiftKey, 'Click'], description: 'Toggle component in selection' },
        { keys: ['Drag'], description: 'Marquee select on empty canvas' },
        { keys: [shiftKey, 'Drag'], description: 'Additive marquee selection' },
        { keys: [modKey, 'A'], description: 'Select all components' },
        { keys: ['Esc'], description: 'Deselect all or cancel active drag' },
      ],
    },
    {
      title: 'History & Duplication',
      shortcuts: [
        { keys: [modKey, 'Z'], description: 'Undo last change' },
        { keys: [modKey, shiftKey, 'Z'], description: 'Redo last change' },
        { keys: [modKey, 'D'], description: 'Duplicate selected components' },
        { keys: [altKey, 'Drag'], description: 'Duplicate while dragging' },
      ],
    },
    {
      title: 'Movement & Alignment',
      shortcuts: [
        { keys: ['Arrow keys'], description: 'Nudge selection 1 mm' },
        { keys: [shiftKey, 'Arrows'], description: 'Large nudge selection 5 mm' },
        { keys: [shiftKey, 'Drag'], description: 'Lock drag to horizontal / vertical axis' },
        { keys: ['Space', 'Drag'], description: 'Pan canvas workspace' },
      ],
    },
    {
      title: 'Layers & Organization',
      shortcuts: [
        { keys: [modKey, ']'], description: 'Bring forward one step' },
        { keys: [modKey, '['], description: 'Send backward one step' },
        { keys: [modKey, shiftKey, ']'], description: 'Bring to front (top layer)' },
        { keys: [modKey, shiftKey, '['], description: 'Send to back (bottom layer)' },
      ],
    },
    {
      title: 'Lock & Delete',
      shortcuts: [
        { keys: [modKey, 'L'], description: 'Toggle lock state of selection' },
        { keys: ['Delete / ⌫'], description: 'Delete selected unlocked components' },
      ],
    },
    {
      title: 'Help & Navigation',
      shortcuts: [
        { keys: ['? / F1'], description: 'Open this shortcut reference' },
      ],
    },
  ];

  function handleKeydown(event) {
    if (open && event.key === 'Escape') {
      event.preventDefault();
      onClose();
    }
  }
</script>

<svelte:window onkeydown={handleKeydown} />

{#if open}
  <div
    class="fixed inset-0 z-50 flex items-center justify-center bg-studio-overlay p-4 backdrop-blur-sm animate-in fade-in duration-150 motion-reduce:animate-none"
    role="dialog"
    tabindex="-1"
    aria-modal="true"
    aria-labelledby="shortcut-help-title"
    onclick={(event) => {
      if (event.target === event.currentTarget) onClose();
    }}
    onkeydown={(event) => {
      if (event.key === 'Escape') onClose();
    }}
  >
    <div
      class="relative flex max-h-[85vh] w-full max-w-2xl flex-col rounded-2xl border border-studio-border bg-studio-panel shadow-2xl overflow-hidden"
    >
      <!-- Dialog Header -->
      <div class="flex items-center justify-between border-b border-studio-border px-5 py-4">
        <div class="flex items-center gap-2.5">
          <div class="flex size-8 items-center justify-center rounded-lg bg-studio-selection text-studio-selection-foreground">
            <Keyboard class="size-4" />
          </div>
          <div>
            <h2 id="shortcut-help-title" class="text-sm font-bold text-studio-panel-foreground">
              Studio Keyboard Shortcuts
            </h2>
            <p class="text-xs text-studio-panel-muted-foreground">
              Fast, precision authoring with keyboard and gesture commands
            </p>
          </div>
        </div>
        <button
          type="button"
          class="rounded-lg p-1.5 text-studio-panel-muted-foreground hover:bg-studio-panel-muted hover:text-studio-panel-foreground transition-colors motion-reduce:transition-none"
          aria-label="Close shortcut dialog"
          onclick={onClose}
        >
          <X class="size-4" />
        </button>
      </div>

      <!-- Shortcuts Grid -->
      <div class="flex-1 overflow-y-auto p-5">
        <div class="grid grid-cols-1 gap-6 sm:grid-cols-2">
          {#each shortcutSections as section}
            <div class="space-y-2.5">
              <h3 class="text-[11px] font-bold uppercase tracking-wider text-studio-panel-muted-foreground">
                {section.title}
              </h3>
              <div class="space-y-1.5">
                {#each section.shortcuts as item}
                  <div class="flex items-center justify-between gap-3 text-xs">
                    <span class="text-studio-panel-foreground/80 text-[11px] leading-snug">{item.description}</span>
                    <div class="flex items-center gap-1 shrink-0">
                      {#each item.keys as key}
                        <kbd
                          class="inline-flex min-w-5 items-center justify-center rounded border border-studio-border bg-studio-panel-muted px-1.5 py-0.5 font-mono text-[10px] font-semibold text-studio-panel-foreground shadow-sm"
                        >
                          {key}
                        </kbd>
                      {/each}
                    </div>
                  </div>
                {/each}
              </div>
            </div>
          {/each}
        </div>
      </div>

      <!-- Dialog Footer -->
      <div class="flex items-center justify-between border-t border-studio-border bg-studio-panel-muted px-5 py-3 text-xs text-studio-panel-muted-foreground">
        <span>Shortcuts are active when canvas or controls are focused.</span>
        <button
          type="button"
          class="rounded-lg bg-studio-toolbar px-3 py-1.5 text-xs font-semibold text-studio-toolbar-foreground hover:bg-studio-shell transition-colors motion-reduce:transition-none"
          onclick={onClose}
        >
          Got it
        </button>
      </div>
    </div>
  </div>
{/if}
