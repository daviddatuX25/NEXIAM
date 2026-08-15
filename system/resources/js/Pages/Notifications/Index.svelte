<script>
  import { Link, router, usePage } from '@inertiajs/svelte';
  import StaffLayout from '@/Pages/Staff/StaffLayout.svelte';
  import ApplicantLayout from '@/Pages/Applicant/ApplicantLayout.svelte';
  import PageHeaderBar from '@/Components/kit/PageHeaderBar.svelte';
  import { Mail, CheckCheck, AlertTriangle, Inbox } from 'lucide-svelte';
  import { Button } from '@/Components/ui/button';

  let { notifications } = $props();

  const page = usePage();
  const isApplicant = $derived(page.props.auth?.user?.is_applicant ?? false);

  const unreadCount = $derived(notifications.unread_count ?? 0);
  const items = $derived(notifications.recent ?? []);

  function markRead(item) {
    router.post(`/notifications/${item.id}/read`, {}, {
      preserveScroll: true,
      onSuccess: () => {
        if (item.action_url && !item.failed) {
          router.visit(item.action_url);
        }
      },
    });
  }

  function markAllRead() {
    router.post('/notifications/read-all', {}, { preserveScroll: true });
  }

  function formatWhen(value) {
    if (!value) return '';
    const date = new Date(value);
    return date.toLocaleString(undefined, {
      month: 'short',
      day: 'numeric',
      hour: '2-digit',
      minute: '2-digit',
    });
  }
</script>

<svelte:head><title>Notifications</title></svelte:head>

{#if isApplicant}
<ApplicantLayout title="Notifications">
  <div class="min-w-0 space-y-6 p-4 sm:p-6">
    <PageHeaderBar title="Notifications" description="Updates about your application, exam schedule, and results.">
      {#snippet actions()}
        {#if unreadCount > 0}
          <Button variant="outline" class="min-h-10 gap-2" onclick={markAllRead}>
            <CheckCheck class="size-4" />
            Mark all read
          </Button>
        {/if}
      {/snippet}
    </PageHeaderBar>

    {#if items.length === 0}
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
                class="mt-0.5 flex size-9 shrink-0 items-center justify-center rounded-full {item.channel === 'mail' ? 'bg-muted text-muted-foreground' : 'bg-primary/15 text-primary'}"
                aria-hidden="true"
              >
                {#if item.channel === 'mail'}
                  <Mail class="size-4" />
                {:else}
                  <span class="size-2 rounded-full bg-current" />
                {/if}
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
                  <span>{formatWhen(item.created_at)}</span>
                  {#if item.delivered_at}
                    <span>· Delivered</span>
                  {:else if item.failed}
                    <span class="inline-flex items-center gap-1 font-medium text-destructive">
                      <AlertTriangle class="size-3" />
                      Delivery failed
                    </span>
                  {:else}
                    <span>· Queued</span>
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
</ApplicantLayout>
{:else}
<StaffLayout breadcrumbs={[{ label: 'Notifications' }]}>
  <div class="min-w-0 space-y-6 p-4 sm:p-6">
    <PageHeaderBar title="Notifications" description="Updates about exam operations and your assigned duties.">
      {#snippet actions()}
        {#if unreadCount > 0}
          <Button variant="outline" class="min-h-10 gap-2" onclick={markAllRead}>
            <CheckCheck class="size-4" />
            Mark all read
          </Button>
        {/if}
      {/snippet}
    </PageHeaderBar>

    {#if items.length === 0}
      <div class="flex flex-col items-center gap-3 rounded-xl border border-border bg-card px-6 py-14 text-center">
        <Inbox class="size-10 text-muted-foreground/50" />
        <p class="font-medium text-foreground">No notifications yet.</p>
        <p class="text-sm text-muted-foreground">Proctor assignments and exam lifecycle updates will appear here.</p>
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
                class="mt-0.5 flex size-9 shrink-0 items-center justify-center rounded-full {item.channel === 'mail' ? 'bg-muted text-muted-foreground' : 'bg-primary/15 text-primary'}"
                aria-hidden="true"
              >
                {#if item.channel === 'mail'}
                  <Mail class="size-4" />
                {:else}
                  <span class="size-2 rounded-full bg-current" />
                {/if}
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
                  <span>{formatWhen(item.created_at)}</span>
                  {#if item.delivered_at}
                    <span>· Delivered</span>
                  {:else if item.failed}
                    <span class="inline-flex items-center gap-1 font-medium text-destructive">
                      <AlertTriangle class="size-3" />
                      Delivery failed
                    </span>
                  {:else}
                    <span>· Queued</span>
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
</StaffLayout>
{/if}
