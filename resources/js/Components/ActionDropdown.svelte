<script>
  import { Button } from '@/Components/ui/button';
  import { EllipsisVertical } from 'lucide-svelte';
  import { Link, router } from '@inertiajs/svelte';
  import { onMount, onDestroy } from 'svelte';

  /*
   * ActionDropdown Component
   * A premium, lightweight dropdown for row-level actions.
   * Based on the pattern identified in AuthenticatedLayout.
   *
   * Item options:
   * - label: string (required)
   * - icon: Svelte component (optional)
   * - href: string (optional) - for GET navigation
   * - method: string (optional) - 'post', 'put', 'delete' for form submission
   * - danger: boolean (optional) - if true, shows in red (destructive)
   * - onclick: function (optional) - custom handler
   * - disabled: boolean (optional)
   */

  let { items = [], triggerIcon: TriggerIcon = EllipsisVertical, align = 'right', confirmDelete = false } = $props();

  let isOpen = $state(false);
  let container = $state(null);

  function toggle() {
    // Don't toggle if no items available
    if (items.length === 0) {
      isOpen = false;
      return;
    }
    isOpen = !isOpen;
  }

  function close() {
    isOpen = false;
  }

  function handleKeydown(e) {
    if (e.key === 'Escape') close();
  }

  function handleClickOutside(e) {
    if (container && !container.contains(e.target)) {
      close();
    }
  }

  onMount(() => {
    if (typeof window !== 'undefined') {
      window.addEventListener('click', handleClickOutside);
      window.addEventListener('keydown', handleKeydown);
    }
  });

  onDestroy(() => {
    if (typeof window !== 'undefined') {
      window.removeEventListener('click', handleClickOutside);
      window.removeEventListener('keydown', handleKeydown);
    }
  });
</script>

<div class="relative inline-block text-left" bind:this={container}>
  <Button
    variant="ghost"
    size="icon"
    class="h-8 w-8 rounded-full hover:bg-muted transition-colors"
    onclick={toggle}
    aria-haspopup="true"
    aria-expanded={isOpen}
  >
    <TriggerIcon class="h-4 w-4" />
    <span class="sr-only">More actions</span>
  </Button>

  {#if isOpen && items.length > 0}
    <div
      class="absolute z-[100] mt-1 w-48 rounded-lg border border-border bg-card shadow-xl ring-1 ring-black/5 focus:outline-none overflow-hidden animate-in fade-in zoom-in-95 duration-100 {align === 'right' ? 'right-0' : 'left-0'}"
      role="menu"
      aria-orientation="vertical"
    >
      <div class="py-1" role="none">
        {#each items as item}
            {#if item.method === 'delete'}
              <button
                type="button"
                class="group flex w-full items-center px-3 py-2 text-left text-sm hover:bg-accent transition-colors disabled:opacity-50 disabled:cursor-not-allowed {item.danger ? 'text-destructive hover:text-destructive' : 'text-foreground'} {item.class ?? ''}"
                role="menuitem"
                onclick={() => {
                  if (confirmDelete && !confirm(`Are you sure you want to delete "${item.label}"? This action cannot be undone.`)) return;
                  if (item.onclick) {
                    item.onclick();
                  } else if (item.href) {
                    router.delete(item.href);
                  }
                  close();
                }}
                disabled={item.disabled}
              >
                {#if item.icon}
                  <item.icon class="mr-2.5 h-4 w-4 {item.danger ? 'text-destructive' : 'text-muted-foreground group-hover:text-foreground'}" />
                {/if}
                {item.label}
              </button>
            {:else if item.method && item.method !== 'get'}
              <form action={item.href} method="post" class="contents">
                <input type="hidden" name="_method" value={item.method} />
                <button
                  type="submit"
                  class="group flex w-full items-center px-3 py-2 text-left text-sm text-foreground hover:bg-accent transition-colors disabled:opacity-50 disabled:cursor-not-allowed {item.class ?? ''}"
                  role="menuitem"
                  onclick={(e) => {
                    if (item.onclick) item.onclick();
                    close();
                  }}
                  disabled={item.disabled}
                >
                  {#if item.icon}
                    <item.icon class="mr-2.5 h-4 w-4 text-muted-foreground group-hover:text-foreground transition-colors" />
                  {/if}
                  {item.label}
                </button>
              </form>
            {:else if item.href}
              <button
                type="button"
                class="group flex w-full items-center px-3 py-2 text-left text-sm text-foreground hover:bg-accent transition-colors disabled:opacity-50 disabled:cursor-not-allowed {item.class ?? ''}"
                role="menuitem"
                onclick={() => { item.onclick?.(); close(); }}
                disabled={item.disabled}
              >
                {#if item.icon}
                  <item.icon class="mr-2.5 h-4 w-4 text-muted-foreground group-hover:text-foreground transition-colors" />
                {/if}
                {item.label}
              </button>
            {/if}
          {/each}
      </div>
    </div>
  {/if}
</div>
