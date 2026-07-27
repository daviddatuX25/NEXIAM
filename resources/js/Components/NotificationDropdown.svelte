<script>
  import { Bell, Check, CheckCheck, X } from 'lucide-svelte';
  import { usePoll } from '@inertiajs/svelte';
  import { usePage } from '@inertiajs/svelte';
  import { message } from '@/lib/toast.js';
  import { formatRelativeDate } from '@/lib/date-utils';

  let { initialNotifications = [] } = $props();
  const page = usePage();

  let notifications = $state(initialNotifications);
  let dropdownOpen = $state(false);

  // Track previous notification IDs for detecting new notifications
  let previousNotificationIds = $state(new Set());
  let previousCount = $state(0);

  // Debounce to prevent toast spam
  let lastToastTime = $state(0);
  const TOAST_COOLDOWN = 5000; // 5 seconds between toasts

  const unreadCount = $derived(notifications.filter(n => !n.read).length);
  const hasUnread = $derived(unreadCount > 0);

  // Poll every 45 seconds (within D-03's 30-60s range)
  const { start, stop } = usePoll(45000, {
    only: ['notifications'],
    onSuccess: (page) => {
      if (page.props.notifications) {
        // Get current notification IDs
        const currentIds = new Set(page.props.notifications.map(n => n.id));
        const currentCount = page.props.notifications.length;

        // Check for NEW notifications (not in previous set and unread)
        const newNotifications = page.props.notifications.filter(
          n => !previousNotificationIds.has(n.id) && !n.read
        );

        // Only show toast if dropdown is NOT open and we have new notifications
        const now = Date.now();
        if (!dropdownOpen && newNotifications.length > 0 && now - lastToastTime > TOAST_COOLDOWN) {
          // Show toast for the most recent new notification
          const latestNew = newNotifications[0];

          // All poll-based notifications use background-tier sound
          message(latestNew.message || 'New notification');

          lastToastTime = now;
        }

        // Update previous state
        previousNotificationIds = currentIds;
        previousCount = currentCount;
        notifications = page.props.notifications;
      }
    }
  });

  function toggleDropdown() {
    dropdownOpen = !dropdownOpen;
  }

  function closeDropdown() {
    dropdownOpen = false;
  }

  async function markAsRead(notificationId) {
    try {
      const csrf = page.props.csrf_token || document.querySelector('meta[name="csrf-token"]')?.content || '';
      await fetch(`/notifications/${notificationId}/read`, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrf, 'Accept': 'application/json' },
      });
      const idx = notifications.findIndex(n => n.id === notificationId);
      if (idx !== -1) {
        notifications[idx] = { ...notifications[idx], read: true };
      }
    } catch (e) {
      console.error('Failed to mark notification as read', e);
    }
  }

  async function markAllAsRead() {
    try {
      const csrf = page.props.csrf_token || document.querySelector('meta[name="csrf-token"]')?.content || '';
      await fetch('/notifications/read-all', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrf, 'Accept': 'application/json' },
      });
      notifications = notifications.map(n => ({ ...n, read: true }));
    } catch (e) {
      console.error('Failed to mark all as read', e);
    }
  }



  function getNotificationIcon(type) {
    // Map type to icon name string for dynamic rendering
    if (type?.includes('application_status')) return 'file-text';
    if (type?.includes('exam_session')) return 'calendar';
    if (type?.includes('result')) return 'graduation-cap';
    return 'bell';
  }
</script>

<div class="relative">
  <!-- Bell button with unread badge -->
  <button
    type="button"
    class="relative p-2 rounded-full hover:bg-muted text-foreground/80 transition-colors min-h-[44px] min-w-[44px] flex items-center justify-center"
    onclick={toggleDropdown}
    aria-label="Notifications"
    aria-expanded={dropdownOpen}
    aria-haspopup="true"
  >
    <Bell class="w-5 h-5" />
    {#if hasUnread}
      <span class="absolute top-1.5 right-1.5 w-2 h-2 bg-red-500 rounded-full" aria-hidden="true"></span>
    {/if}
  </button>

  <!-- Dropdown panel -->
  {#if dropdownOpen}
    <!-- Backdrop -->
    <button
      type="button"
      class="fixed inset-0 z-10"
      aria-label="Close notifications"
      onclick={closeDropdown}
    ></button>

    <!-- Dropdown -->
    <div
      class="absolute right-0 top-full mt-2 w-[calc(100vw-2rem)] sm:w-96 rounded-xl border border-border bg-card shadow-xl z-50 overflow-hidden"
      role="menu"
    >
      <!-- Header -->
      <div class="flex items-center justify-between px-4 py-3 border-b border-border">
        <h3 class="text-sm font-semibold text-foreground">Notifications</h3>
        {#if hasUnread}
          <button
            type="button"
            class="text-xs text-primary hover:text-primary/80 font-medium flex items-center gap-1"
            onclick={markAllAsRead}
          >
            <CheckCheck class="w-3.5 h-3.5" />
            Mark all read
          </button>
        {/if}
      </div>

      <!-- Notification list -->
      <div class="max-h-[80vh] overflow-y-auto scrollbar-hide">
        {#if notifications.length === 0}
          <!-- Empty state -->
          <div class="flex flex-col items-center justify-center py-12 px-4 text-center">
            <Bell class="w-8 h-8 text-muted-foreground/50 mb-3" />
            <p class="text-sm text-muted-foreground">No notifications yet</p>
          </div>
        {:else}
          <ul class="divide-y divide-border/50">
            {#each notifications as notification (notification.id)}
              <li>
                <button
                  type="button"
                  class="w-full flex items-start gap-3 px-4 py-3 hover:bg-accent/50 transition-colors text-left"
                  onclick={() => markAsRead(notification.id)}
                >
                  <!-- Unread dot -->
                  {#if !notification.read}
                    <span class="w-2 h-2 rounded-full bg-primary mt-2 shrink-0"></span>
                  {:else}
                    <span class="w-2"></span>
                  {/if}

                  <!-- Content -->
                  <div class="flex-1 min-w-0">
                    <p class="text-sm text-foreground leading-snug {notification.read ? '' : 'font-medium'}">
                      {notification.message}
                    </p>
                    <p class="text-xs text-muted-foreground mt-1">
                      {formatRelativeDate(notification.created_at)}
                    </p>
                  </div>

                  <!-- Read indicator -->
                  {#if !notification.read}
                    <Check class="w-4 h-4 text-primary shrink-0 mt-1" />
                  {/if}
                </button>
              </li>
            {/each}
          </ul>
        {/if}
      </div>

      <!-- Footer -->
      {#if notifications.length > 0}
        <div class="px-4 py-2 border-t border-border bg-muted/30">
          <p class="text-xs text-muted-foreground text-center">
            Checking for new notifications every 45 seconds
          </p>
        </div>
      {/if}
    </div>
  {/if}
</div>

<style>
  .scrollbar-hide {
    -ms-overflow-style: none;
    scrollbar-width: none;
  }
  .scrollbar-hide::-webkit-scrollbar {
    display: none;
  }
</style>