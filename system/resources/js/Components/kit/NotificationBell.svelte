<script>
  /**
   * Own-data notification bell (GF-22 §5.5, GF-21).
   *
   * Reads the server-shared `notifications` prop ({ unread_count, recent, queue_active })
   * with live responsive polling, instant window focus reconciliation, and subtle
   * badge arrival pulse animations.
   *
   * Mark-read and mark-all POST to own-row endpoints; rows are hard-scoped server-side.
   */
  import { onMount, untrack } from 'svelte';
  import { router, usePage, usePoll } from '@inertiajs/svelte';
  import { Bell, Mail, CheckCheck, AlertTriangle, Inbox, RotateCw, Loader2 } from 'lucide-svelte';
  import * as Popover from '@/Components/ui/popover';
  import { formatNotificationTimestamp } from '@/lib/date-utils.js';
  import { sound } from '@/lib/sound.js';
  import { isPushSupported, getNotificationPermission, subscribeToPush } from '@/lib/push-notifications.js';
  let { notifications: propNotifications = undefined } = $props();
  const page = usePage();
  const notifications = $derived(propNotifications ?? page.props.notifications ?? null);
  const unreadCount = $derived(notifications?.unread_count ?? 0);
  const recent = $derived(notifications?.recent ?? []);
  const isApplicant = $derived(Boolean(page.props.auth?.user?.is_applicant || page.url?.startsWith('/applicant')));

  const isQueueActive = $derived(
    notifications?.queue_active ??
      recent.some(
        (item) =>
          item.status === 'pending' ||
          item.status === 'processing' ||
          item.status === 'retrying' ||
          item.email_delivery?.status === 'pending' ||
          item.email_delivery?.status === 'processing',
      ),
  );
  const NOTIFICATION_SOUND_COOLDOWN_MS = 5_000;

  let isOpen = $state(false);
  let showPushPrompt = $state(false);

  onMount(() => {
    if (isPushSupported() && getNotificationPermission() === 'default') {
      showPushPrompt = true;
    }
  });

  async function handleEnablePush() {
    const res = await subscribeToPush();
    if (res.success) {
      showPushPrompt = false;
    }
  }
  let isRefreshing = $state(false);
  let projectionError = $state(null);
  let isPulsing = $state(false);
  let previousCount = null;
  let lastReloadAt = 0;
  let previousNotificationIds = null;
  let lastNotificationSoundAt = null;

  // Responsive live poll (8s) while visible in the foreground
  usePoll(8_000, () => ({
    only: ['notifications', 'navigation_badges'],
    onSuccess: () => (projectionError = null),
    onHttpException: () => {
      projectionError = 'Notifications are temporarily unavailable.';
      return false;
    },
    onNetworkError: () => {
      projectionError = 'Could not refresh notifications. Check your connection and retry.';
      return false;
    },
  }), { mode: 'rest' });

  // Track unread count increments to trigger subtle pulse animation
  $effect(() => {
    const current = unreadCount;
    if (previousCount !== null && current > previousCount) {
      isPulsing = true;
      const timer = setTimeout(() => {
        isPulsing = false;
      }, 900);
      previousCount = current;
      return () => clearTimeout(timer);
    }
    previousCount = current;
  });

  // Sound only newly projected unread items; the first projection is a silent baseline.
  $effect(() => {
    if (!notifications) return;

    const currentIds = new Set(recent.map((item) => item.id));
    if (previousNotificationIds !== null) {
      const newUnreadIds = recent
        .filter((item) => !item.read && !previousNotificationIds.has(item.id))
        .map((item) => item.id);
      const documentIsVisible =
        typeof document !== 'undefined' && document.visibilityState === 'visible';
      const now = Date.now();
      const cooldownElapsed =
        lastNotificationSoundAt === null ||
        now - lastNotificationSoundAt >= NOTIFICATION_SOUND_COOLDOWN_MS;

      if (newUnreadIds.length > 0 && !untrack(() => isOpen) && documentIsVisible && cooldownElapsed) {
        sound.notification(() => {
          const stillVisible =
            typeof document !== 'undefined' && document.visibilityState === 'visible';
          const stillClosed = !untrack(() => isOpen);
          const unreadArrivalRemains = newUnreadIds.some((id) =>
            recent.some((item) => item.id === id && !item.read),
          );

          return stillVisible && stillClosed && unreadArrivalRemains;
        });
        lastNotificationSoundAt = now;
      }
    }

    previousNotificationIds = currentIds;
  });

  function reloadNotifications(force = false) {
    const now = Date.now();
    if (isRefreshing) return;
    if (!force && now - lastReloadAt < 2_000) return;
    isRefreshing = true;
    lastReloadAt = now;
    projectionError = null;
    router.reload({
      only: ['notifications', 'navigation_badges'],
      preserveState: true,
      preserveScroll: true,
      onSuccess: () => {
        projectionError = null;
      },
      onError: () => {
        projectionError = 'Could not refresh notifications. Retry the action.';
      },
      onHttpException: () => {
        projectionError = 'Notifications are temporarily unavailable.';
        return false;
      },
      onNetworkError: () => {
        projectionError = 'Could not refresh notifications. Check your connection and retry.';
        return false;
      },
      onFinish: () => {
        isRefreshing = false;
      },
    });
  }

  // Instant reconciliation on window focus, tab visibility return, and network reconnection
  onMount(() => {
    if (typeof window === 'undefined') return;

    function handleFocusOrVisibility() {
      if (document.visibilityState === 'visible') {
        reloadNotifications();
      }
    }

    window.addEventListener('focus', handleFocusOrVisibility);
    document.addEventListener('visibilitychange', handleFocusOrVisibility);
    window.addEventListener('online', handleFocusOrVisibility);

    return () => {
      window.removeEventListener('focus', handleFocusOrVisibility);
      document.removeEventListener('visibilitychange', handleFocusOrVisibility);
      window.removeEventListener('online', handleFocusOrVisibility);
    };
  });

  // Accelerated 4s polling while queue is processing in background or dropdown is open
  $effect(() => {
    if (!isOpen && !isQueueActive) return;
    if (typeof window === 'undefined') return;

    const intervalId = setInterval(() => {
      if (document.visibilityState === 'visible' && !isRefreshing) {
        reloadNotifications();
      }
    }, 4_000);

    return () => clearInterval(intervalId);
  });

  function emailDeliveryLabel(status) {
    return {
      pending: 'Email queued',
      processing: 'Email sending',
      retrying: 'Email retry scheduled',
      delivered: 'Email accepted by mail service',
      failed_terminal: 'Email delivery failed',
      outcome_unknown: 'Email outcome unknown',
    }[status] ?? 'Email status unavailable';
  }

  function markRead(item) {
    projectionError = null;
    router.post(`/notifications/${item.id}/read`, {}, {
      preserveScroll: true,
      onSuccess: () => {
        if (item.action_url && !item.failed) {
          isOpen = false;
          router.visit(item.action_url, { preserveScroll: false });
        }
      },
      onError: () => {
        projectionError = 'Could not mark this notification read. Retry the action.';
      },
      onHttpException: () => {
        projectionError = 'Could not mark this notification read. Retry the action.';
        return false;
      },
      onNetworkError: () => {
        projectionError = 'Could not mark this notification read. Check your connection and retry.';
        return false;
      },
    });
  }

  function markAllRead() {
    projectionError = null;
    router.post('/notifications/read-all', {}, {
      preserveScroll: true,
      onError: () => {
        projectionError = 'Could not mark notifications read. Retry the action.';
      },
      onHttpException: () => {
        projectionError = 'Could not mark notifications read. Retry the action.';
        return false;
      },
      onNetworkError: () => {
        projectionError = 'Could not mark notifications read. Check your connection and retry.';
        return false;
      },
    });
  }
</script>

<Popover.Root bind:open={isOpen} onOpenChange={(open) => { if (open) reloadNotifications(); }}>
  <Popover.Trigger
    class="relative flex h-11 w-11 items-center justify-center rounded-full text-foreground transition-colors hover:bg-accent min-h-[44px] min-w-[44px]"
    aria-label={`Notifications${unreadCount > 0 ? ` (${unreadCount} unread)` : ''}`}
  >
    <Bell class="h-5 w-5" />
    {#if unreadCount > 0}
      <span
        class="absolute top-1 right-1 flex h-4 min-w-4 items-center justify-center rounded-full bg-destructive px-1 text-[10px] font-bold leading-none text-white shadow-xs {isPulsing ? 'animate-badge-pop' : ''}"
        aria-hidden="true"
      >
        {#if isPulsing}
          <span
            class="absolute inset-0 rounded-full bg-destructive/60 animate-ping pointer-events-none"
            aria-hidden="true"
          ></span>
        {/if}
        <span class="relative z-10">{unreadCount > 99 ? '99+' : unreadCount}</span>
      </span>
    {/if}
  </Popover.Trigger>

  <Popover.Content
    align="center"
    side="bottom"
    sideOffset={8}
    collisionPadding={12}
    class="w-[min(24rem,calc(100vw-1.5rem))] max-h-[min(80dvh,32rem)] p-0 gap-0 overflow-hidden rounded-xl border border-border bg-card shadow-xl flex flex-col z-50 animate-in fade-in zoom-in-95 duration-100"
  >
    <div class="flex items-center justify-between border-b border-border/60 px-4 py-3 bg-muted/20">
      <div class="flex items-center gap-2">
        <h2 class="text-sm font-semibold text-foreground">Notifications</h2>
        {#if isRefreshing}
          <span class="inline-flex items-center gap-1 text-[11px] text-muted-foreground">
            <Loader2 class="h-3 w-3 animate-spin text-primary" />
            Checking…
          </span>
        {/if}
      </div>

      <div class="flex items-center gap-1">
        <button
          type="button"
          class="inline-flex items-center justify-center h-7 w-7 rounded-md text-muted-foreground hover:text-foreground hover:bg-accent transition-colors disabled:opacity-50"
          onclick={() => reloadNotifications(true)}
          disabled={isRefreshing}
          aria-label="Refresh notifications"
          title="Refresh notifications"
        >
          <RotateCw class="h-3.5 w-3.5 {isRefreshing ? 'animate-spin' : ''}" />
        </button>
        {#if unreadCount > 0}
          <button
            type="button"
            class="flex items-center gap-1.5 text-xs font-medium text-primary hover:text-primary/80 transition-colors h-7 px-2 rounded-md hover:bg-accent"
            onclick={markAllRead}
          >
            <CheckCheck class="h-3.5 w-3.5" />
            Mark all read
          </button>
        {/if}
      </div>
    </div>
    {#if showPushPrompt}
      <div class="flex items-center justify-between border-b border-border/60 bg-muted/30 px-4 py-2 text-xs">
        <span class="text-foreground">Get device alerts when away</span>
        <button type="button" class="font-medium text-primary hover:underline" onclick={handleEnablePush}>Enable</button>
      </div>
    {/if}

    {#if projectionError}
      <div role="alert" class="flex items-center justify-between gap-3 border-b border-border px-4 py-3 text-xs text-destructive">
        <span>{projectionError}</span>
        <button type="button" class="min-h-9 rounded-md px-2 font-medium text-primary hover:bg-accent" onclick={() => reloadNotifications(true)}>Retry</button>
      </div>
    {/if}

    {#if isQueueActive}
      <div
        role="status"
        class="flex items-center justify-between gap-2 border-b border-border/60 bg-primary/5 px-4 py-2 text-xs text-muted-foreground"
      >
        <div class="flex items-center gap-1.5 min-w-0">
          <span class="inline-flex h-2 w-2 shrink-0 rounded-full bg-muted-foreground/60"></span>
          <span class="truncate">Some notifications are queued, retrying, or processing</span>
        </div>
        <button
          type="button"
          class="shrink-0 font-medium text-primary hover:text-primary/80 text-xs underline underline-offset-2 hover:bg-accent/40 px-1.5 py-0.5 rounded transition-colors"
          onclick={() => reloadNotifications(true)}
          disabled={isRefreshing}
        >
          {isRefreshing ? 'Updating…' : 'Refresh'}
        </button>
      </div>
    {/if}

    <div class="flex-1 min-h-0 overflow-y-auto">
      {#if notifications === null}
        <div role="status" class="flex flex-col items-center gap-2 px-4 py-10 text-center">
          <Inbox class="h-8 w-8 text-muted-foreground/50" />
          <p class="text-sm text-muted-foreground">Notification status is unavailable.</p>
          <button type="button" class="min-h-9 rounded-md px-3 text-xs font-medium text-primary hover:bg-accent" onclick={() => reloadNotifications(true)}>Retry</button>
        </div>
      {:else if recent.length === 0}
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
                    <span class="h-2 w-2 rounded-full bg-current"></span>
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
                  <span class="mt-1 flex flex-wrap items-center gap-x-2 gap-y-1 text-[11px] text-muted-foreground/80">
                    <span>{formatNotificationTimestamp(item.created_at)}</span>
                    {#if item.failed}
                      <span class="inline-flex items-center gap-1 font-medium text-destructive">
                        <AlertTriangle class="h-3 w-3" />
                        Delivery failed
                      </span>
                    {:else if item.status === 'processing'}
                      <span class="inline-flex items-center gap-1 text-primary">
                        <Loader2 class="h-3 w-3 animate-spin" />
                        Processing…
                      </span>
                    {:else if item.status === 'retrying'}
                      <span class="inline-flex items-center gap-1 text-amber-500">
                        Retry scheduled
                      </span>
                    {/if}
                    {#if !isApplicant && item.email_delivery}
                      <span class="inline-flex items-center gap-1 {item.email_delivery.failed || item.email_delivery.outcome_unknown ? 'font-medium text-destructive' : ''}">
                        <Mail class="h-3 w-3" />
                        {emailDeliveryLabel(item.email_delivery.status)}
                      </span>
                    {/if}
                  </span>
                </span>
              </button>
            </li>
          {/each}
        </ul>
      {/if}
    </div>

    <div class="border-t border-border/60 px-4 py-2 bg-muted/10">
      <a
        href="/notifications"
        onclick={() => { isOpen = false; }}
        class="block py-1.5 text-center text-xs font-medium text-primary hover:text-primary/80 transition-colors"
      >
        View all notifications
      </a>
    </div>
  </Popover.Content>
</Popover.Root>

<style>
  @keyframes badge-pop {
    0% {
      transform: scale(0.85);
    }
    35% {
      transform: scale(1.22);
    }
    70% {
      transform: scale(0.95);
    }
    100% {
      transform: scale(1);
    }
  }

  .animate-badge-pop {
    animation: badge-pop 400ms cubic-bezier(0.16, 1, 0.3, 1) both;
  }

  @media (prefers-reduced-motion: reduce) {
    .animate-badge-pop {
      animation: none;
    }
  }
</style>
