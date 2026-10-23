<script>
  import { onMount, tick } from 'svelte';
  import StaffLayout from '@/Pages/Staff/StaffLayout.svelte';
  import PageHeaderBar from '@/Components/kit/PageHeaderBar.svelte';
  import SimplePagination from '@/Components/kit/SimplePagination.svelte';
  import * as Card from '@/Components/ui/card';
  import { Badge } from '@/Components/ui/badge';
  import { Button } from '@/Components/ui/button';
  import * as Table from '@/Components/ui/table';
  import { Link, router } from '@inertiajs/svelte';
  import { ArrowLeft, History, Users } from 'lucide-svelte';

  let dialogElement = $state(null);

  onMount(() => {
    window.addEventListener('keydown', onKeyDown);

    return () => {
      window.removeEventListener('keydown', onKeyDown);
    };
  });

  async function focusDialog() {
    await tick();
    if (isOpen) {
      dialogElement?.focus();
    }
  }

  function onKeyDown(event) {
    if (!isOpen) return;

    if (event.key === 'Escape') {
      event.preventDefault();
      closeModals();
      return;
    }

    if (event.key !== 'Tab' || !dialogElement) return;

    const focusableElements = dialogElement.querySelectorAll(
      'a[href], button:not([disabled]), textarea:not([disabled]), input:not([disabled]), select:not([disabled]), [tabindex]:not([tabindex="-1"])',
    );
    const firstFocusableElement = focusableElements[0];
    const lastFocusableElement = focusableElements[focusableElements.length - 1];

    if (!firstFocusableElement) {
      event.preventDefault();
      dialogElement.focus();
    } else if (
      event.shiftKey
      && (
        document.activeElement === dialogElement
        || document.activeElement === firstFocusableElement
        || !dialogElement.contains(document.activeElement)
      )
    ) {
      event.preventDefault();
      lastFocusableElement.focus();
    } else if (
      !event.shiftKey
      && (document.activeElement === lastFocusableElement || !dialogElement.contains(document.activeElement))
    ) {
      event.preventDefault();
      firstFocusableElement.focus();
    }
  }


  let {
    occurrence = {},
    deliveries = { data: [] },
    history = { data: [] },
    can_retry = false,
  } = $props();

  let isOpen = $state(false);
  let activeDelivery = $state(null);
  let reason = $state('');
  let acknowledgeRisk = $state(false);
  let isSubmitting = $state(false);
  let errorMessage = $state('');
  let modalType = $state('retry');

  const deliveryRows = $derived(deliveries?.data ?? []);
  const historyRows = $derived(history?.data ?? []);
  const breadcrumbs = [
    { label: 'Setup', href: '/setup' },
    { label: 'Email & Notifications', href: '/setup/notifications' },
    { label: 'Occurrence details' },
  ];

  function domainLabel(value) {
    return { admissions: 'Admissions', examination: 'Examinations', results: 'Results' }[value] ?? value ?? 'Notification';
  }

  function statusLabel(value) {
    return {
      pending: 'In Flight',
      queued: 'In Flight',
      processing: 'In Flight',
      in_progress: 'In Flight',
      retrying: 'In Flight',
      retry_scheduled: 'In Flight',
      delivered: 'Delivered',
      accepted_mail: 'Delivered',
      available_in_app: 'Delivered',
      failed: 'Bounced / Failed',
      failed_terminal: 'Bounced / Failed',
      outcome_unknown: 'In Flight',
      superseded: 'Notice Outdated',
      unknown: 'In Flight',
      mixed: 'Mixed status',
    }[value] ?? value ?? 'Unknown';
  }

  function recipientTypeLabel(value) {
    return value === 'candidate' ? 'Candidate' : value === 'staff' ? 'Staff' : value ?? '—';
  }

  function channelLabel(value) {
    return value === 'email' ? 'Email' : value === 'in_app' ? 'Portal Inbox' : value ?? '—';
  }

  function humanEventName(event) {
    return {
      'notification.delivery_processing': 'Preparing dispatch',
      'notification.delivery_delivered': 'Delivered to recipient',
      'notification.delivery_failed': 'Delivery failed',
      'notification.delivery_superseded': 'Marked outdated (superseded)',
      'notification.delivery_manually_retried': 'Manually resent by staff',
      'notification.outcome_unknown': 'Unconfirmed delivery outcome',
    }[event] ?? event ?? 'Operational update';
  }

  function statusVariant(value) {
    if (value === 'delivered' || value === 'accepted_mail' || value === 'available_in_app') return 'default';
    if (value === 'failed' || value === 'failed_terminal') return 'destructive';
    if (value === 'pending' || value === 'queued') return 'secondary';
    return 'outline';
  }

  function statusClass(value) {
    if (value === 'retrying' || value === 'retry_scheduled') return 'border-amber-500/40 bg-amber-500/10 text-amber-700 dark:text-amber-300';
    if (value === 'processing' || value === 'in_progress') return 'border-blue-500/40 bg-blue-500/10 text-blue-700 dark:text-blue-300';
    return '';
  }

  function occurredAt(value) {
    if (!value) return '—';
    const date = new Date(value);
    return Number.isNaN(date.getTime()) ? value : date.toLocaleString();
  }

  function openRetryModal(delivery) {
    modalType = 'retry';
    activeDelivery = delivery;
    reason = '';
    acknowledgeRisk = false;
    errorMessage = '';
    isOpen = true;
    focusDialog();
  }

  function openReviewOutcomeModal(delivery) {
    modalType = 'review';
    activeDelivery = delivery;
    reason = '';
    acknowledgeRisk = false;
    errorMessage = '';
    isOpen = true;
    focusDialog();
  }

  function closeModals() {
    if (isSubmitting) return;
    isOpen = false;
    activeDelivery = null;
    reason = '';
    acknowledgeRisk = false;
    errorMessage = '';
  }

  function submitRetry() {
    if (!activeDelivery || isSubmitting) return;
    if (modalType === 'review' && (!acknowledgeRisk || !reason.trim())) return;

    isSubmitting = true;
    errorMessage = '';
    const payload = {
      reason: reason.trim(),
      expected_version: activeDelivery.version,
    };
    if (modalType === 'review') {
      payload.acknowledge_duplicate_risk = true;
    }

    router.post(
      `/setup/notifications/occurrences/${occurrence.occurrence_id}/deliveries/${activeDelivery.delivery_id}/retry`,
      payload,
      {
        preserveScroll: true,
        onSuccess: () => {
          isSubmitting = false;
          closeModals();
        },
        onError: (errors) => {
          const message = errors?.reason
            ?? errors?.acknowledge_duplicate_risk
            ?? errors?.delivery
            ?? 'Unable to queue this delivery for retry. Please review the delivery and try again.';
          errorMessage = Array.isArray(message) ? message[0] : message;
        },
        onHttpException: (error) => {
          isSubmitting = false;
          if (error.status === 409) {
            errorMessage = 'The delivery status was updated by another process. Please refresh the page.';
          } else if (error.status === 422) {
            errorMessage = error.response?.data?.message ?? 'This delivery cannot be retried.';
          } else if (error.status === 403) {
            errorMessage = 'You do not have permission to retry this delivery.';
          } else {
            errorMessage = 'An error occurred while queueing the retry.';
          }
        },
        onNetworkError: () => {
          isSubmitting = false;
          errorMessage = 'Network error. Please check your connection and try again.';
        },

        onFinish: () => {
          isSubmitting = false;
        },
      },
    );
  }

 

</script>


<svelte:head><title>Notification Occurrence · Setup</title></svelte:head>

<StaffLayout {breadcrumbs}>
  <div class="min-w-0 space-y-6 p-4 pb-40 sm:p-6 sm:pb-40">
    <PageHeaderBar
      title="Notification Occurrence"
      description="Scoped read-only delivery details. Recipient addresses and raw provider payloads are not shown."
    >
      {#snippet actions()}
        <Link href="/setup/notifications" class="inline-flex min-h-11 items-center gap-2 rounded-lg border border-border/80 bg-card/85 px-3 py-2 text-sm font-medium text-foreground transition-colors hover:border-primary/50 hover:bg-muted focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring"><ArrowLeft class="size-4" />Back to notifications</Link>
      {/snippet}
    </PageHeaderBar>

    <Card.Root class="border-border/70 shadow-xs">
      <Card.Header class="border-b border-border/60">
        <div class="flex flex-wrap items-start justify-between gap-3">
          <div class="space-y-1">
            <Card.Description>{domainLabel(occurrence.domain)} · {occurredAt(occurrence.occurred_at)}</Card.Description>
            <Card.Title class="text-base">{occurrence.safe_summary ?? 'Notification details unavailable'}</Card.Title>
          </div>
          <Badge variant={statusVariant(occurrence.status)} class="capitalize {statusClass(occurrence.status)}">{statusLabel(occurrence.status)}</Badge>
        </div>
      </Card.Header>
      <Card.Content class="grid gap-4 p-4 sm:grid-cols-2">
        <div class="flex items-center gap-3 rounded-lg bg-muted/40 p-3">
          <Users class="size-4 text-muted-foreground" />
          <div><p class="text-xs text-muted-foreground">Recipient deliveries</p><p class="font-semibold">{occurrence.recipient_count ?? deliveryRows.length}</p></div>
        </div>
        <div class="rounded-lg bg-muted/40 p-3">
          <p class="text-xs text-muted-foreground">Channels</p>
          <div class="mt-1 flex flex-wrap gap-1.5">
            {#each occurrence.channel_summaries ?? [] as item (item.channel)}
              <Badge variant="outline">{item.channel}: {statusLabel(item.status)}{item.count != null ? ` · ${item.count}` : ''}</Badge>
            {:else}
              <span class="text-sm">No channel summary available</span>
            {/each}
          </div>
        </div>
      </Card.Content>
    </Card.Root>

    <section aria-labelledby="recipient-deliveries-heading" class="space-y-3">
      <div class="space-y-1">
        <h2 id="recipient-deliveries-heading" class="text-base font-semibold text-foreground">Recipient deliveries</h2>
        <p class="text-sm text-muted-foreground">Delivery status and resend options for this notification.</p>
      </div>
      <Card.Root class="overflow-hidden border-border/70 shadow-xs">
        {#if deliveryRows.length > 0}
          <div class="overflow-x-auto">
            <Table.Root class="min-w-[720px]">
              <Table.Header class="bg-muted/40">
                <Table.Row><Table.Head>Recipient</Table.Head><Table.Head>Role / Type</Table.Head><Table.Head>Channel</Table.Head><Table.Head>Status</Table.Head><Table.Head>Dispatched</Table.Head><Table.Head class="text-right">Actions</Table.Head></Table.Row>
              </Table.Header>
            <Table.Body>
              {#each deliveryRows as delivery (delivery.delivery_id)}
                <Table.Row>
                  <Table.Cell class="font-medium">
                    <div>{delivery.recipient_label ?? 'Recipient label unavailable'}</div>
                    <details class="mt-1 text-xs font-normal text-muted-foreground">
                      <summary class="cursor-pointer">Troubleshooting details</summary>
                      <div class="mt-1 space-y-1">
                        <p>Diagnostic: {delivery.diagnostic_redacted ?? 'Not available'}</p>
                        <p>Provider reference: {delivery.provider_reference_redacted ?? 'Not available'}</p>
                        <p>Attempts: {delivery.attempt_count ?? '—'}</p>
                      </div>
                    </details>
                  </Table.Cell>
                  <Table.Cell>{recipientTypeLabel(delivery.recipient_type)}</Table.Cell>
                  <Table.Cell>{channelLabel(delivery.channel)}</Table.Cell>
                  <Table.Cell><Badge variant={statusVariant(delivery.status)} class="capitalize {statusClass(delivery.status)}">{statusLabel(delivery.status)}</Badge></Table.Cell>
                  <Table.Cell class="whitespace-nowrap">{occurredAt(delivery.accepted_at)}</Table.Cell>
                  <Table.Cell class="text-right">
                    {#if delivery.status === 'superseded'}
                      <Badge variant="outline" class="text-muted-foreground text-xs">Notice Outdated</Badge>
                    {:else if delivery.status === 'failed_terminal' || delivery.status === 'failed'}
                      {#if can_retry}
                        <Button variant="outline" size="sm" class="h-8 text-xs" onclick={() => openRetryModal(delivery)}>Resend Notice</Button>
                      {:else}
                        <span class="text-xs text-muted-foreground">—</span>
                      {/if}
                    {:else if delivery.status === 'outcome_unknown'}
                      {#if can_retry}
                        <Button variant="outline" size="sm" class="h-8 border-amber-500/50 text-amber-700 hover:bg-amber-500/10 dark:text-amber-300 text-xs" onclick={() => openReviewOutcomeModal(delivery)}>Review outcome</Button>
                      {:else}
                        <span class="text-xs text-muted-foreground">—</span>
                      {/if}
                    {:else}
                      <span class="text-xs text-muted-foreground">—</span>
                    {/if}
                  </Table.Cell>
                </Table.Row>
              {/each}
            </Table.Body>
              
            </Table.Root>
          </div>
        {:else}
          <Card.Content class="p-8 text-center text-sm text-muted-foreground">No recipient deliveries are available for this occurrence.</Card.Content>
        {/if}
      </Card.Root>
      <SimplePagination data={deliveries} />
    </section>

    <section aria-labelledby="history-heading" class="space-y-3">
      <div class="flex items-center gap-2">
        <History class="size-4 text-primary" />
        <h2 id="history-heading" class="text-base font-semibold text-foreground">Redacted operational history</h2>
      </div>
      <Card.Root class="border-border/70 shadow-xs">
        {#if historyRows.length > 0}
          <Card.Content class="divide-y divide-border/60 p-0">
            {#each historyRows as entry, index (`${entry.occurred_at ?? 'history'}-${entry.event ?? 'event'}-${index}`)}
              <div class="flex flex-wrap items-center justify-between gap-2 p-4">
                <p class="text-sm font-medium">{humanEventName(entry.event)}</p>
                <time class="text-xs text-muted-foreground">{occurredAt(entry.occurred_at)}</time>
              </div>
            {/each}
          </Card.Content>
        {:else}
          <Card.Content class="p-4 text-sm text-muted-foreground">
            No matching delivery history is available for this occurrence.
          </Card.Content>
        {/if}
      </Card.Root>
      <SimplePagination data={history} />
    </section>
    {#if isOpen}
      <div class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4" role="presentation">
        <div
          bind:this={dialogElement}
          class="max-h-[90vh] w-full max-w-lg space-y-4 overflow-y-auto rounded-xl border border-border bg-card p-5 shadow-xl"
          role="dialog"
          aria-modal="true"
          aria-labelledby="retry-dialog-title"
          aria-describedby="retry-dialog-description"
          tabindex="-1"
        >
          {#if modalType === 'review'}
            <div class="space-y-1">
              <h2 id="retry-dialog-title" class="text-lg font-semibold">Confirm Resend Notice</h2>
              <p id="retry-dialog-description" class="sr-only">Review duplicate-delivery risk before resending.</p>
            </div>
            <div class="rounded-lg border border-amber-500/40 bg-amber-500/10 p-3 text-sm text-amber-800 dark:text-amber-200" role="alert">
              The mail delivery was interrupted earlier. The recipient may have already received this notification. Resending may deliver a duplicate copy.
            </div>
            <label class="flex items-start gap-2 text-sm">
              <input type="checkbox" class="mt-1 size-4 accent-primary" bind:checked={acknowledgeRisk} required />
              <span>I understand this delivery may produce a duplicate notice and accept the risk</span>
            </label>
              <div class="space-y-2">
                <label for="retry-reason" class="text-sm font-medium">Reason for resending (required)</label>
                <textarea
                  id="retry-reason"
                  class="min-h-24 w-full rounded-md border border-input bg-background px-3 py-2 text-sm outline-none focus-visible:ring-2 focus-visible:ring-ring"
                  bind:value={reason}
                  disabled={isSubmitting}
                  required
                ></textarea>
              </div>
          {:else}
            <div class="space-y-1">
              <h2 id="retry-dialog-title" class="text-lg font-semibold">Resend Notice</h2>
              <p id="retry-dialog-description" class="text-sm text-muted-foreground">Send a fresh copy of this notification to {activeDelivery.recipient_label}.</p>
            </div>
            <div class="space-y-2">
              <label for="retry-reason" class="text-sm font-medium">Audit note (optional)</label>
              <textarea
                id="retry-reason"
                class="min-h-24 w-full rounded-md border border-input bg-background px-3 py-2 text-sm outline-none focus-visible:ring-2 focus-visible:ring-ring"
                bind:value={reason}
                disabled={isSubmitting}
              ></textarea>
            </div>
          {/if}

          {#if errorMessage}
            <p class="text-sm text-destructive" role="alert">{errorMessage}</p>
          {/if}

          <div class="flex justify-end gap-2">
            <Button variant="outline" type="button" onclick={closeModals} disabled={isSubmitting}>Cancel</Button>
            <Button
              type="button"
              onclick={submitRetry}
              disabled={isSubmitting || (modalType === 'review' && (!acknowledgeRisk || !reason.trim()))}
            >
              {#if isSubmitting}
                Queueing...
              {:else if modalType === 'review'}
                Confirm &amp; Resend
              {:else}
                Resend Notice Now
              {/if}
            </Button>
          </div>
        </div>
      </div>
    {/if}
  </div>
</StaffLayout>
