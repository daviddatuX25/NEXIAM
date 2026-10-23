<script>
  import { router, usePage } from '@inertiajs/svelte';
  import StaffLayout from '@/Pages/Staff/StaffLayout.svelte';
  import ApplicantLayout from '@/Pages/Applicant/ApplicantLayout.svelte';
  import PageHeaderBar from '@/Components/kit/PageHeaderBar.svelte';
  import { Mail, CheckCheck, AlertTriangle, Inbox, RotateCw, Loader2 } from 'lucide-svelte';
  import { Button } from '@/Components/ui/button';
  import { formatNotificationTimestamp } from '@/lib/date-utils.js';

  let { notifications = null } = $props();

  const page = usePage();
  const isApplicant = $derived(page.props.auth?.user?.is_applicant ?? false);
  const unreadCount = $derived(notifications?.unread_count ?? 0);
  const items = $derived(notifications?.recent ?? []);

  const isQueueActive = $derived(
    notifications?.queue_active ??
      items.some(
        (item) =>
          item.status === 'pending' ||
          item.status === 'processing' ||
          item.status === 'retrying' ||
          item.email_delivery?.status === 'pending' ||
          item.email_delivery?.status === 'processing',
      ),
  );

  let projectionError = $state(null);
  let isRefreshing = $state(false);

  function reloadNotifications() {
    if (isRefreshing) return;
    isRefreshing = true;
    projectionError = null;
    router.reload({
      only: ['notifications'],
      onSuccess: () => {
        projectionError = null;
      },
      onError: () => {
        projectionError = 'Could not load notifications. Retry the action.';
      },
      onHttpException: () => {
        projectionError = 'Notifications are temporarily unavailable.';
        return false;
      },
      onNetworkError: () => {
        projectionError = 'Could not load notifications. Check your connection and retry.';
        return false;
      },
      onFinish: () => {
        isRefreshing = false;
      },
    });
  }

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
          router.visit(item.action_url);
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
      onSuccess: () => (projectionError = null),
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

<svelte:head><title>Notifications</title></svelte:head>

{#if isApplicant}
<ApplicantLayout title="Notifications">
  <div class="min-w-0 space-y-6 p-4 sm:p-6">
    <PageHeaderBar title="Notifications" description="Updates about your application, exam schedule, and results.">
      {#snippet actions()}
        <Button variant="outline" class="min-h-10 gap-2" onclick={reloadNotifications} disabled={isRefreshing}>
          <RotateCw class="size-4 {isRefreshing ? 'animate-spin' : ''}" />
          Refresh
        </Button>
        {#if unreadCount > 0}
          <Button variant="outline" class="min-h-10 gap-2" onclick={markAllRead}>
            <CheckCheck class="size-4" />
            Mark all read
          </Button>
        {/if}
      {/snippet}
    </PageHeaderBar>

    {#if projectionError}
      <div role="alert" class="flex flex-wrap items-center justify-between gap-3 rounded-lg border border-destructive/30 bg-destructive/5 px-4 py-3 text-sm text-destructive">
        <span>{projectionError}</span>
        <Button variant="outline" class="min-h-9" onclick={reloadNotifications}>Retry</Button>
      </div>
    {/if}

    {#if isQueueActive}
      <div
        role="status"
        class="flex flex-wrap items-center justify-between gap-3 rounded-lg border border-primary/20 bg-primary/5 px-4 py-3 text-xs text-muted-foreground"
      >
        <div class="flex items-center gap-2">
          <span class="inline-flex size-2 shrink-0 rounded-full bg-muted-foreground/60"></span>
          <span class="font-medium text-foreground">Some notifications are queued, retrying, or processing</span>
        </div>
        <Button variant="ghost" size="sm" class="h-7 text-xs font-medium text-primary hover:text-primary/80" onclick={reloadNotifications} disabled={isRefreshing}>
          {isRefreshing ? 'Updating…' : 'Refresh'}
        </Button>
      </div>
    {/if}

    {#if notifications === null}
      <div role="status" class="flex flex-col items-center gap-3 rounded-xl border border-border bg-card px-6 py-14 text-center">
        <Inbox class="size-10 text-muted-foreground/50" />
        <p class="font-medium text-foreground">Notification status is unavailable.</p>
        <Button variant="outline" class="min-h-10" onclick={reloadNotifications}>Retry</Button>
      </div>
    {:else if items.length === 0}
      <div class="flex flex-col items-center gap-3 rounded-xl border border-border bg-card px-6 py-14 text-center">
        <Inbox class="size-10 text-muted-foreground/50" />
        <p class="font-medium text-foreground">No notifications yet.</p>
        <p class="text-sm text-muted-foreground">Updates about your exam schedule and results will appear here.</p>
      </div>
    {:else}
      <ul class="divide-y divide-border/60 rounded-xl border border-border bg-card">
        {#each items as item (item.id)}
          <li>
            <button
              type="button"
              class="flex w-full items-start gap-3 px-4 py-4 text-left transition-colors hover:bg-accent/60 {item.read ? '' : 'bg-primary/[0.03]'}"
              onclick={() => markRead(item)}
            >
              <span
                class="mt-0.5 flex size-9 shrink-0 items-center justify-center rounded-full bg-primary/15 text-primary"
                aria-hidden="true"
              >
                <span class="size-2 rounded-full bg-current"></span>
              </span>
              <span class="min-w-0 flex-1">
                <span class="flex items-center gap-2">
                  <span class="font-medium text-foreground">{item.subject}</span>
                  {#if !item.read}
                    <span class="size-1.5 shrink-0 rounded-full bg-primary" aria-label="Unread"></span>
                  {/if}
                </span>
                <span class="mt-0.5 text-sm leading-relaxed text-muted-foreground">{item.body}</span>
                <span class="mt-1 flex flex-wrap items-center gap-x-2 gap-y-1 text-xs text-muted-foreground/80">
                  <span>{formatNotificationTimestamp(item.created_at)}</span>
                  {#if item.failed}
                    <span class="inline-flex items-center gap-1 font-medium text-destructive">
                      <AlertTriangle class="size-3" />
                      Delivery failed
                    </span>
                  {:else if item.status === 'processing'}
                    <span class="inline-flex items-center gap-1 text-primary">
                      <Loader2 class="size-3 animate-spin" />
                      Processing…
                    </span>
                  {:else if item.status === 'retrying'}
                    <span class="inline-flex items-center gap-1 text-amber-500">
                      Retry scheduled
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
</ApplicantLayout>
{:else}
<StaffLayout breadcrumbs={[{ label: 'Notifications' }]}>
  <div class="min-w-0 space-y-6 p-4 sm:p-6">
    <PageHeaderBar title="Notifications" description="Updates about exam operations and your assigned duties.">
      {#snippet actions()}
        <Button variant="outline" class="min-h-10 gap-2" onclick={reloadNotifications} disabled={isRefreshing}>
          <RotateCw class="size-4 {isRefreshing ? 'animate-spin' : ''}" />
          Refresh
        </Button>
        {#if unreadCount > 0}
          <Button variant="outline" class="min-h-10 gap-2" onclick={markAllRead}>
            <CheckCheck class="size-4" />
            Mark all read
          </Button>
        {/if}
      {/snippet}
    </PageHeaderBar>

    {#if projectionError}
      <div role="alert" class="flex flex-wrap items-center justify-between gap-3 rounded-lg border border-destructive/30 bg-destructive/5 px-4 py-3 text-sm text-destructive">
        <span>{projectionError}</span>
        <Button variant="outline" class="min-h-9" onclick={reloadNotifications}>Retry</Button>
      </div>
    {/if}

    {#if isQueueActive}
      <div
        role="status"
        class="flex flex-wrap items-center justify-between gap-3 rounded-lg border border-primary/20 bg-primary/5 px-4 py-3 text-xs text-muted-foreground"
      >
        <div class="flex items-center gap-2">
          <span class="inline-flex size-2 shrink-0 rounded-full bg-muted-foreground/60"></span>
          <span class="font-medium text-foreground">Some notifications are queued, retrying, or processing</span>
        </div>
        <Button variant="ghost" size="sm" class="h-7 text-xs font-medium text-primary hover:text-primary/80" onclick={reloadNotifications} disabled={isRefreshing}>
          {isRefreshing ? 'Updating…' : 'Refresh'}
        </Button>
      </div>
    {/if}

    {#if notifications === null}
      <div role="status" class="flex flex-col items-center gap-3 rounded-xl border border-border bg-card px-6 py-14 text-center">
        <Inbox class="size-10 text-muted-foreground/50" />
        <p class="font-medium text-foreground">Notification status is unavailable.</p>
        <Button variant="outline" class="min-h-10" onclick={reloadNotifications}>Retry</Button>
      </div>
    {:else if items.length === 0}
      <div class="flex flex-col items-center gap-3 rounded-xl border border-border bg-card px-6 py-14 text-center">
        <Inbox class="size-10 text-muted-foreground/50" />
        <p class="font-medium text-foreground">No notifications yet.</p>
        <p class="text-sm text-muted-foreground">Exam scheduling, attendance, and lifecycle updates will appear here.</p>
      </div>
    {:else}
      <ul class="divide-y divide-border/60 rounded-xl border border-border bg-card">
        {#each items as item (item.id)}
          <li>
            <button
              type="button"
              class="flex w-full items-start gap-3 px-4 py-4 text-left transition-colors hover:bg-accent/60 {item.read ? '' : 'bg-primary/[0.03]'}"
              onclick={() => markRead(item)}
            >
              <span
                class="mt-0.5 flex size-9 shrink-0 items-center justify-center rounded-full bg-primary/15 text-primary"
                aria-hidden="true"
              >
                <span class="size-2 rounded-full bg-current"></span>
              </span>
              <span class="min-w-0 flex-1">
                <span class="flex items-center gap-2">
                  <span class="font-medium text-foreground">{item.subject}</span>
                  {#if !item.read}
                    <span class="size-1.5 shrink-0 rounded-full bg-primary" aria-label="Unread"></span>
                  {/if}
                </span>
                <span class="mt-0.5 text-sm leading-relaxed text-muted-foreground">{item.body}</span>
                <span class="mt-1 flex flex-wrap items-center gap-x-2 gap-y-1 text-xs text-muted-foreground/80">
                  <span>{formatNotificationTimestamp(item.created_at)}</span>
                  {#if item.failed}
                    <span class="inline-flex items-center gap-1 font-medium text-destructive">
                      <AlertTriangle class="size-3" />
                      Delivery failed
                    </span>
                  {:else if item.status === 'processing'}
                    <span class="inline-flex items-center gap-1 text-primary">
                      <Loader2 class="size-3 animate-spin" />
                      Processing…
                    </span>
                  {:else if item.status === 'retrying'}
                    <span class="inline-flex items-center gap-1 text-amber-500">
                      Retry scheduled
                    </span>
                  {/if}
                  {#if item.email_delivery}
                    <span class="inline-flex items-center gap-1 {item.email_delivery.failed || item.email_delivery.outcome_unknown ? 'font-medium text-destructive' : ''}">
                      <Mail class="size-3" />
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
</StaffLayout>
{/if}
