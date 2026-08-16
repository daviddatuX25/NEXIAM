<script>
  /**
   * Shared reservation confirm sheet (2026-08-15-applicant-portal-interface).
   * Identical anatomy for exam seats and consultation bookings; copy is passed
   * in per page. Confirmation claims only the committed command — never
   * publication or delivery.
   */
  import { Button } from '@/Components/ui/button';
  import * as Dialog from '@/Components/ui/dialog';

  let {
    open = false,
    title = 'Confirm reservation',
    description = '',
    rows = [],
    confirmLabel = 'Confirm',
    busyLabel = 'Booking…',
    busy = false,
    onConfirm = () => {},
    onClose = () => {},
  } = $props();
</script>

<Dialog.Root bind:open>
  <Dialog.Content class="sm:max-w-md">
    <Dialog.Header>
      <Dialog.Title>{title}</Dialog.Title>
      {#if description}
        <Dialog.Description>{description}</Dialog.Description>
      {/if}
    </Dialog.Header>
    {#if rows.length > 0}
      <dl class="space-y-1 text-sm">
        {#each rows as row (row.label)}
          <div class="flex justify-between gap-4">
            <dt class="text-muted-foreground">{row.label}</dt>
            <dd>{row.value}</dd>
          </div>
        {/each}
      </dl>
    {/if}
    <Dialog.Footer>
      <Button type="button" variant="outline" class="min-h-11" disabled={busy} onclick={onClose}>
        Back
      </Button>
      <Button type="button" class="min-h-11" disabled={busy} onclick={onConfirm}>
        {busy ? busyLabel : confirmLabel}
      </Button>
    </Dialog.Footer>
  </Dialog.Content>
</Dialog.Root>
