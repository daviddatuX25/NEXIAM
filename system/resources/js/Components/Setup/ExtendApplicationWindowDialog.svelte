<script>
  import * as Dialog from '@/Components/ui/dialog';
  import { Button } from '@/Components/ui/button';
  import { Input } from '@/Components/ui/input';
  import { Label } from '@/Components/ui/label';
  import { router } from '@inertiajs/svelte';
  import { newIdempotencyKey } from '@/lib/utils.js';
  import { addUtcDateDays, addUtcDays, formatUtcDate, formatUtcInstant } from '@/lib/utc-calendar.js';

  let { calendar } = $props();
  let open = $state(false);
  let days = $state(1);
  let reason = $state('');
  let busy = $state(false);
  let message = $state('');
  let stale = $state(false);
  let pendingCommand = $state(null);

  const newDeadline = $derived(addUtcDays(calendar?.closes_at, days));
  const newCycleDeadline = $derived(addUtcDateDays(calendar?.cycle_closes_on, days));
  const validDays = $derived(Number.isSafeInteger(days) && days > 0 && newDeadline !== null && newCycleDeadline !== null);
  const validReason = $derived(typeof reason === 'string' && reason.trim().length >= 3 && reason.trim().length <= 500);

  function fingerprint() {
    return JSON.stringify({
      cycle_id: calendar?.cycle_id,
      window_id: calendar?.window_id,
      days,
      baseline: calendar?.baseline,
      reason: reason.trim(),
    });
  }

  function commandKey() {
    const currentFingerprint = fingerprint();
    if (pendingCommand?.fingerprint === currentFingerprint) return pendingCommand.key;

    pendingCommand = { fingerprint: currentFingerprint, key: newIdempotencyKey() };
    return pendingCommand.key;
  }

  function chooseDays(value) {
    days = value;
    message = '';
    stale = false;
  }

  function updateCustomDays(event) {
    const value = event.currentTarget.value;
    days = value === '' ? null : Number(value);
    message = '';
    stale = false;
  }

  function submit(event) {
    event.preventDefault();
    if (busy || !calendar?.can_extend || !validDays || !validReason) return;

    busy = true;
    message = '';
    stale = false;
    router.post(`/setup/cycles/${calendar.cycle_id}/application-window/extend`, {
      window_id: calendar.window_id,
      days,
      baseline: calendar.baseline,
      idempotency_key: commandKey(),
      reason: reason.trim(),
    }, {
      preserveScroll: true,
      preserveState: true,
      onSuccess: () => {
        open = false;
        days = 1;
        reason = '';
        pendingCommand = null;
      },
      onError: (validationErrors) => {
        const errors = validationErrors ?? {};
        message = Object.values(errors).find((value) => typeof value === 'string')
          ?? 'The application window could not be extended. Review the dates and try again.';
        stale = Boolean(errors.baseline || /refresh/i.test(errors.window_id ?? ''));
      },
      onFinish: () => {
        busy = false;
      },
    });
  }

  function refreshCalendar() {
    busy = true;
    router.reload({
      only: ['application_calendar'],
      preserveScroll: true,
      onSuccess: () => {
        message = '';
        stale = false;
        pendingCommand = null;
      },
      onFinish: () => {
        busy = false;
      },
    });
  }
</script>

<Dialog.Root bind:open>
  <Dialog.Trigger class="border-input bg-background hover:bg-accent hover:text-accent-foreground focus-visible:ring-ring inline-flex min-h-10 items-center justify-center rounded-md border px-3 text-sm font-medium focus-visible:outline-none focus-visible:ring-2" disabled={busy}>
    Extend deadline
  </Dialog.Trigger>
  <Dialog.Portal>
    <Dialog.Overlay />
    <Dialog.Content class="max-h-[90dvh] w-[calc(100vw-2rem)] overflow-y-auto sm:max-w-lg">
      <Dialog.Header>
        <Dialog.Title>Extend application deadline</Dialog.Title>
        <Dialog.Description>
          Add calendar days to the current closing date. This action does not activate, publish, or reopen a window.
        </Dialog.Description>
      </Dialog.Header>

      <form class="mt-4 space-y-4" onsubmit={submit}>
        <div class="grid grid-cols-3 gap-2" aria-label="Quick extension choices">
          {#each [1, 3, 10] as preset (preset)}
            <Button
              type="button"
              variant={days === preset ? 'default' : 'outline'}
              class="min-h-10"
              aria-pressed={days === preset}
              disabled={busy}
              onclick={() => chooseDays(preset)}
            >
              +{preset} day{preset === 1 ? '' : 's'}
            </Button>
          {/each}
        </div>

        <div class="space-y-1.5">
          <Label for="application-extension-days">Custom days</Label>
          <Input
            id="application-extension-days"
            type="number"
            min="1"
            step="1"
            inputmode="numeric"
            value={days ?? ''}
            oninput={updateCustomDays}
            disabled={busy}
            required
            aria-describedby="application-extension-days-help"
            class="min-h-10"
          />
          <p id="application-extension-days-help" class="text-xs text-muted-foreground">Enter a positive whole number within the supported calendar date range.</p>
        </div>

        <div class="rounded-lg border border-border bg-muted/20 p-3 text-sm">
          <dl class="grid grid-cols-[minmax(0,1fr)_minmax(0,1fr)] gap-x-3 gap-y-2">
            <dt class="text-muted-foreground">Current deadline</dt>
            <dd class="break-words text-right font-medium">{formatUtcInstant(calendar?.closes_at)}</dd>
            <dt class="text-muted-foreground">New deadline</dt>
            <dd class="break-words text-right font-medium">{newDeadline ? formatUtcInstant(newDeadline) : 'Outside supported date range'}</dd>
            <dt class="text-muted-foreground">Academic-year close</dt>
            <dd class="break-words text-right font-medium">{formatUtcDate(calendar?.cycle_closes_on)} → {newCycleDeadline ? formatUtcDate(newCycleDeadline) : 'Outside supported date range'}</dd>
            <dt class="text-muted-foreground">Added</dt>
            <dd class="text-right font-medium">{validDays ? `${days} calendar day${days === 1 ? '' : 's'}` : 'Choose a positive whole number'}</dd>
          </dl>
          <p class="mt-3 text-xs text-muted-foreground">The existing closing time is retained. Both linked close dates move together in UTC.</p>
        </div>

        <div class="space-y-1.5">
          <Label for="application-extension-reason">Reason</Label>
          <textarea
            id="application-extension-reason"
            bind:value={reason}
            rows="3"
            minlength="3"
            maxlength="500"
            required
            disabled={busy}
            class="border-input bg-background placeholder:text-muted-foreground focus-visible:ring-ring w-full resize-y rounded-md border px-3 py-2 text-sm focus-visible:outline-none focus-visible:ring-2"
            placeholder="Briefly explain why the application period needs more time."
          ></textarea>
        </div>

        {#if message}
          <div class="space-y-2" role="alert" aria-live="assertive">
            <p class="text-sm text-destructive">{message}</p>
            {#if stale}
              <Button type="button" variant="outline" class="min-h-10" disabled={busy} onclick={refreshCalendar}>Refresh calendar</Button>
            {/if}
          </div>
        {/if}

        <Dialog.Footer class="flex flex-col-reverse gap-2 sm:flex-row sm:justify-end">
          <Button type="button" variant="outline" class="min-h-10" disabled={busy} onclick={() => (open = false)}>Cancel</Button>
          <Button type="submit" class="min-h-10" disabled={busy || !validDays || !validReason}>
            {busy ? 'Extending…' : 'Confirm extension'}
          </Button>
        </Dialog.Footer>
      </form>
    </Dialog.Content>
  </Dialog.Portal>
</Dialog.Root>
