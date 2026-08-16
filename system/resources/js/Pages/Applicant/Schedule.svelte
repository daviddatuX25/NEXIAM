<script>
  /**
   * Applicant exam schedule — mobile single-column status pipeline (legacy Portal feel).
   * Calendar lists sessions with booking_mode=open_for_reservation; reserve creates assignment.
   */
  import ApplicantLayout from '@/Pages/Applicant/ApplicantLayout.svelte';
  import SchedulePipeline from '@/Pages/Applicant/SchedulePipeline.svelte';
  import E2PageState from '@/Pages/Admission/E2PageState.svelte';
  import ReservationSlotList from '@/Components/applicant/ReservationSlotList.svelte';
  import ReservationConfirmSheet from '@/Components/applicant/ReservationConfirmSheet.svelte';
  import { Badge } from '@/Components/ui/badge';
  import { Button } from '@/Components/ui/button';
  import * as Card from '@/Components/ui/card';
  import { Link, router } from '@inertiajs/svelte';
  import { CalendarDays, CheckCircle2, Clock3, MapPin, ShieldAlert } from 'lucide-svelte';

  let {
    page_state = 'denied',
    attempt = null,
    assignment = null,
    application = null,
    calendar_days = [],
    message = '',
    pipeline = [],
  } = $props();

  let confirmOpen = $state(false);
  let selectedSlot = $state(null);
  let reserving = $state(false);

  const headline = $derived.by(() => {
    const map = {
      denied: 'Schedule unavailable',
      pending_acceptance: 'Waiting for acceptance',
      waiting_for_reservation_open: 'Scheduling not open yet',
      calendar_open: 'Choose your exam slot',
      assigned_published: 'Your exam seat',
    };
    return map[page_state] ?? 'Exam schedule';
  });

  function openConfirm(slot) {
    if (slot.full) return;
    selectedSlot = slot;
    confirmOpen = true;
  }

  function confirmReserve() {
    if (!selectedSlot || !attempt) return;
    reserving = true;
    router.post(
      '/applicant/schedule/reserve',
      {
        exam_session_id: selectedSlot.id,
        admission_attempt_id: attempt.id,
        idempotency_key: `reserve-${attempt.id}-${selectedSlot.id}`,
      },
      {
        preserveScroll: true,
        onFinish: () => {
          reserving = false;
          confirmOpen = false;
          selectedSlot = null;
        },
      },
    );
  }

  function weekday(dateStr) {
    return new Date(`${dateStr}T12:00:00`).toLocaleDateString(undefined, { weekday: 'short' });
  }

  function dayNumber(dateStr) {
    return new Date(`${dateStr}T12:00:00`).getDate();
  }

  function monthShort(dateStr) {
    return new Date(`${dateStr}T12:00:00`).toLocaleDateString(undefined, { month: 'short' });
  }

  const slotGroups = $derived(
    calendar_days.map((day) => ({
      date: day.date,
      label: String(dayNumber(day.date)),
      caption: `${weekday(day.date)} · ${monthShort(day.date)} · ${day.date}`,
      slots: day.slots.map((slot) => ({
        id: slot.id,
        timeLabel: `${slot.start_time}${slot.end_time ? `–${slot.end_time}` : ''}`,
        venue: slot.room ?? null,
        code: slot.code,
        remaining: slot.remaining,
        capacity: slot.capacity,
        full: slot.full,
      })),
    })),
  );

  const confirmRows = $derived(
    selectedSlot
      ? [
          { label: 'Session', value: selectedSlot.code },
          { label: 'Seats left', value: String(selectedSlot.remaining) },
        ]
      : [],
  );

  const confirmDescription = $derived(
    selectedSlot
      ? `Book ${selectedSlot.start_time}${selectedSlot.end_time ? `–${selectedSlot.end_time}` : ''} at ${selectedSlot.room ?? 'the assigned room'}?`
      : 'Confirm your selected exam slot.',
  );
</script>

<svelte:head><title>Your status</title></svelte:head>

<ApplicantLayout title="Your status">
  <div class="mx-auto max-w-lg space-y-5">
    <div class="space-y-1">
      <h1 class="text-2xl font-semibold tracking-tight">{headline}</h1>
      {#if attempt}
        <p class="font-mono text-sm text-muted-foreground">Ref {attempt.reference_code}</p>
      {/if}
    </div>

    <div class="sr-only">
      <E2PageState state={page_state} />
    </div>


    {#if application?.needs_correction}
      <Card.Root class="border-amber-500/40 bg-amber-500/10">
        <Card.Content class="space-y-3 p-4">
          <p class="font-medium">Registrar needs a correction</p>
          {#if application.correction_notes}
            <p class="text-sm text-muted-foreground">{application.correction_notes}</p>
          {/if}
          {#if application.correction_href}
            <Link href={application.correction_href}>
              <Button class="min-h-11 w-full">Fix application</Button>
            </Link>
          {/if}
        </Card.Content>
      </Card.Root>
    {:else if application?.can_quiet_amend && application.correction_href}
      <Card.Root>
        <Card.Content class="flex flex-col gap-3 p-4 sm:flex-row sm:items-center sm:justify-between">
          <p class="text-sm text-muted-foreground">Still pending — you can quietly update your application.</p>
          <Link href={application.correction_href}>
            <Button variant="outline" class="min-h-11">Update application</Button>
          </Link>
        </Card.Content>
      </Card.Root>
    {/if}

    <Card.Root class="overflow-hidden shadow-sm">
      <Card.Header class="border-b bg-muted/20 pb-3">
        <Card.Title class="text-lg">Your progress</Card.Title>
        <Card.Description>Admission → schedule → exam seat</Card.Description>
      </Card.Header>
      <Card.Content class="pt-5">
        {#if pipeline.length > 0}
          <SchedulePipeline stages={pipeline} />
        {:else}
          <p class="text-sm text-muted-foreground">{message || 'No schedule progress yet.'}</p>
        {/if}
      </Card.Content>
    </Card.Root>

    {#if page_state === 'calendar_open'}
      <Card.Root class="overflow-hidden border-primary/25 shadow-sm">
        <Card.Header class="border-b bg-primary/5 pb-3">
          <div>
            <Card.Title class="text-lg">Available slots</Card.Title>
            <Card.Description>
              Tap a time to claim your seat. Seats left update as others book.
            </Card.Description>
          </div>
        </Card.Header>
        <Card.Content class="space-y-4 pt-4">
          {#if slotGroups.length === 0}
            <p class="text-sm text-muted-foreground">No open sessions yet. Check back soon.</p>
          {:else}
            <ReservationSlotList groups={slotGroups} onSelect={openConfirm} />
          {/if}
          <p class="flex gap-2 text-xs leading-relaxed text-muted-foreground">
            <ShieldAlert class="mt-0.5 size-3.5 shrink-0" />
            Confirming books your seat immediately. Campus emergencies may still require rescheduling.
          </p>
        </Card.Content>
      </Card.Root>
    {:else if page_state === 'assigned_published' && assignment?.session}
      <Card.Root class="overflow-hidden border-primary/25 shadow-sm">
        <Card.Header class="border-b bg-primary/5 pb-3">
          <Card.Title class="text-lg">Exam seat</Card.Title>
          <Card.Description>
            {assignment.session.published ? 'Schedule published' : 'Assigned — awaiting official publish'}
          </Card.Description>
        </Card.Header>
        <Card.Content class="space-y-4 pt-4">
          <div class="flex items-start gap-3">
            <CheckCircle2 class="mt-0.5 size-5 text-primary" />
            <div>
              <p class="font-medium">
                {assignment.session.date} · {assignment.session.start_time}{#if assignment.session.end_time}–{assignment.session.end_time}{/if}
              </p>
              <p class="text-sm text-muted-foreground">{assignment.session.code}</p>
            </div>
          </div>
          <div class="flex items-start gap-3">
            <MapPin class="mt-0.5 size-5 text-primary" />
            <p class="font-medium">{assignment.session.room ?? 'Room TBD'}</p>
          </div>
          <Badge variant={assignment.session.published ? 'default' : 'secondary'}>
            {assignment.session.published ? 'Official schedule' : 'Awaiting publish'}
          </Badge>
          {#if assignment.source === 'applicant_reserved'}
            <p class="text-xs text-muted-foreground">You reserved this seat yourself.</p>
          {:else if assignment.source === 'staff_assigned'}
            <p class="text-xs text-muted-foreground">Guidance assigned this seat.</p>
          {/if}
        </Card.Content>
      </Card.Root>
    {:else if page_state === 'waiting_for_reservation_open'}
      <Card.Root>
        <Card.Content class="space-y-3 p-6 text-center">
          <CalendarDays class="mx-auto size-8 text-muted-foreground" />
          <p class="font-medium">Reservation window closed or not opened</p>
          <p class="text-sm text-muted-foreground">
            {message ||
              'When Guidance opens a session for booking, you can pick a time here. Staff may also assign you directly.'}
          </p>
        </Card.Content>
      </Card.Root>
    {:else if page_state === 'pending_acceptance'}
      <Card.Root>
        <Card.Content class="space-y-3 p-6 text-center">
          <Clock3 class="mx-auto size-8 text-muted-foreground" />
          <p class="font-medium">Acceptance comes first</p>
          <p class="text-sm text-muted-foreground">
            {message || 'Exam scheduling unlocks after the registrar accepts your application.'}
          </p>
        </Card.Content>
      </Card.Root>
    {:else if page_state === 'denied'}
      <Card.Root>
        <Card.Content class="p-6 text-center text-sm text-muted-foreground">
          {message || 'This account cannot access exam scheduling.'}
        </Card.Content>
      </Card.Root>
    {/if}
  </div>

  <ReservationConfirmSheet
    open={confirmOpen}
    title="Confirm exam seat"
    description={confirmDescription}
    rows={confirmRows}
    confirmLabel="Confirm seat"
    busyLabel="Booking…"
    busy={reserving}
    onConfirm={confirmReserve}
    onClose={() => {
      confirmOpen = false;
      selectedSlot = null;
    }}
  />
</ApplicantLayout>
