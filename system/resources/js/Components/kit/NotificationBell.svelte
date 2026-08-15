<script>
  /**
   * Own-data notification bell (GF-22 §5.5, GF-21).
   *
   * Reads the server-shared `notifications` prop ({ unread_count, recent })
   * and polls it every 30s with `usePoll(..., { only: ['notifications'] })`
   * so the badge refreshes without a full navigation. Mark-read and mark-all
   * POST to own-row endpoints; rows are hard-scoped server-side.
   *
   * Truthful delivery (GF-22 §6): rows only surface an "email" affordance when
   * the delivery row is actually `delivered`; nothing here claims a provider
   * send that hasn't happened.
   */
  import { router, usePage, usePoll } from '@inertiajs/svelte';
  import { Bell, Mail, CheckCheck, AlertTriangle, Inbox } from 'lucide-svelte';
  import { onMount, onDestroy } from 'svelte';

  const page = usePage();

  const notifications = $derived(page.props.notifications ?? { unread_count: 0, recent: [] });
  const unreadCount = $derived(notifications.unread_count ?? 0);
  const recent = $derived(notifications.recent ?? []);

  let isOpen = $state(false);
  let container = $state(null);
  let hasInteracted = $state(false);

  usePoll(30_000, () => ({ only: ['notifications'] }));

  function toggle() {
    isOpen = !isOpen;
    if (isOpen) hasInteracted = true;
  }

  function close() {
    isOpen = false;
  }

  function handleClickOutside(e) {
    if (container && !container.contains(e.target)) close();
  }

  function handleKeydown(e) {
    if (e.key === 'Escape') close();
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

  function markRead(item) {
    router.post(`/notifications/${item.id}/read`, {}, {
      preserveScroll: true,
      onSuccess: () => {
        if (item.action_url && !item.failed) {
          router.visit(item.action_url, { preserveScroll: false });
        }
      },
    });
  }

  function markAllRead() {
    router.post('/notifications/read-all', {}, { preserveScroll: true });
  }
</script>

<div class="relative" bind:this={container}>
  <button
    type="button"
    class="relative flex h-11 w-11 items-center justify-center rounded-full text-foreground transition-colors hover:bg-accent min-h-[44px] min-w-[44px]"
    onclick={toggle}
    aria-haspopup="true"
    aria-expanded={isOpen}
    aria-label={`Notifications${unreadCount > 0 ? ` (${unreadCount} unread)` : ''}`}
  >
    <Bell class="h-5 w-5" />
    {#if unreadCount > 0}
      <span
        class="absolute top-1 right-1 flex h-4 min-w-4 items-center justify-center rounded-full bg-destructive px-1 text-[10px] font-bold leading-none text-white"
        aria-hidden="true"
      >
        {unreadCount > 99 ? '99+' : unreadCount}
      </span>
    {/if}
  </button>

  {#if isOpen}
    <div
      class="absolute right-0 z-[100] mt-2 w-96 max-w-[calc(100vw-2rem)] rounded-lg border border-border bg-card shadow-xl ring-1 ring-black/5 overflow-hidden animate-in fade-in zoom-in-95 duration-100"
      role="dialog"
      aria-label="Notifications"
    >
      <div class="flex items-center justify-between border-b border-border/60 px-4 py-3">
        <h2 class="text-sm font-semibold text-foreground">Notifications</h2>
        {#if unreadCount > 0}
          <button
            type="button"
            class="flex items-center gap-1.5 text-xs font-medium text-primary hover:text-primary/80 transition-colors min-h-[36px] px-2 rounded-md hover:bg-accent"
            onclick={markAllRead}
          >
            <CheckCheck class="h-4 w-4" />
            Mark all read
          </button>
        {/if}
      </div>

      <div class="max-h-96 overflow-y-auto">
        {#if recent.length === 0}
          <div class="flex flex-col items-center gap-2 px-4 py-10 text-center">
            <Inbox class="h-8 w-8 text-muted-foreground/50" />
            <p class="text-sm text-muted-foreground">No notifications yet.</p>
          </div>
        {:else}
          <ul class="divide-y divide-border/50">
            {#each recent as item (item.id)}
              <li>
                <button
                  type="button"
                  class="flex w-full items-start gap-3 px-4 py-3 text-left transition-colors hover:bg-accent/60 min-h-[52px] {item.read ? '' : 'bg-primary/[0.03]'}"
                  onclick={() => markRead(item)}
                >
                  <span
                    class="mt-1 flex h-8 w-8 shrink-0 items-center justify-center rounded-full {item.channel === 'mail' ? 'bg-muted text-muted-foreground' : 'bg-primary/15 text-primary'}"
                    aria-hidden="true"
                  >
                    {#if item.channel === 'mail'}
                      <Mail class="h-4 w-4" />
                    {:else}
                      <span class="h-2 w-2 rounded-full bg-current" />
                    {/if}
                  </span>

                  <span class="min-w-0 flex-1">
                    <span class="flex items-center gap-2">
                      <span class="truncate text-sm font-medium text-foreground">{item.subject}</span>
                      {#if !item.read}
                        <span class="h-1.5 w-1.5 shrink-0 rounded-full bg-primary" aria-label="Unread"></span>
                      {/if}
                    </span>
                    <span class="mt-0.5 line-clamp-2 text-xs leading-relaxed text-muted-foreground">{item.body}</span>
                    <span class="mt-1 flex items-center gap-2 text-[11px] text-muted-foreground/80">
                      {#if item.delivered_at}
                        <span>Delivered</span>
                      {:else if item.failed}
                        <span class="inline-flex items-center gap-1 text-destructive">
                          <AlertTriangle class="h-3 w-3" />
                          Delivery failed
                        </span>
                      {:else}
                        <span>Queued</span>
                      {/if}
                      {#if item.channel === 'mail'}
                        <span>· Email</span>
                      {/if}
                    </span>
                  </span>
                </button>
              </li>
            {/each}
          </ul>
        {/if}
      </div>

      <div class="border-t border-border/60 px-4 py-2">
        <a
          href="/notifications"
          class="block py-2 text-center text-xs font-medium text-primary hover:text-primary/80 transition-colors"
        >
          View all notifications
        </a>
      </div>
    </div>
  {/if}
</div>
