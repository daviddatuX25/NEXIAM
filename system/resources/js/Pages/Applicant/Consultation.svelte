<script>
  /**
   * Applicant consultation schedule — result-ready gate, open sessions,
   * own assignment, FCFS reservation (exam Schedule mirror).
   */
  import ApplicantLayout from '@/Pages/Applicant/ApplicantLayout.svelte';
  import E2PageState from '@/Pages/Admission/E2PageState.svelte';
  import ReservationSlotList from '@/Components/applicant/ReservationSlotList.svelte';
  import ReservationConfirmSheet from '@/Components/applicant/ReservationConfirmSheet.svelte';
  import { Badge } from '@/Components/ui/badge';
  import { Button } from '@/Components/ui/button';
  import * as Card from '@/Components/ui/card';
  import { router } from '@inertiajs/svelte';
  import { CalendarDays, CheckCircle2, Clock3, MapPin, ShieldAlert } from 'lucide-svelte';

  let {
    page_state = 'denied',
    attempt = null,
    assignment = null,
    sessions = [],
    message = '',
  } = $props();

  let confirmOpen = $state(false);
  let selectedSession = $state(null);
  let reserving = $state(false);

  const headline = $derived.by(() => {
    const map = {
      denied: 'Consultation unavailable',
      not_result_ready: 'Result not ready yet',
      no_open_sessions: 'Consultation booking closed',
      booking_open: 'Choose your consultation session',
      assigned_or_reserved: 'Your consultation schedule',
    };
    return map[page_state] ?? 'Consultation schedule';
  });

  function openConfirm(session) {
    if (session.full) return;
    selectedSession = session;
    confirmOpen = true;
  }

  function confirmReserve() {
    if (!selectedSession || !attempt) return;
    reserving = true;
    router.post(
      '/applicant/consultation/reserve',
      {
        consultation_session_id: selectedSession.id,
        admission_attempt_id: attempt.id,
        idempotency_key: `consultation-reserve-${attempt.id}-${selectedSession.id}`,
      },
      {
        preserveScroll: true,
        onFinish: () => {
          reserving = false;
          confirmOpen = false;
          selectedSession = null;
        },
      },
    );
  }

  function dateLabel(iso) {
    if (!iso) return 'Date TBD';
    return new Date(iso).toLocaleString(undefined, {
      month: 'short',
      day: 'numeric',
      year: 'numeric',
      hour: 'numeric',
      minute: '2-digit',
    });
  }

  const slotGroups = $derived([
    {
      date: null,
      slots: sessions.map((session) => ({
        id: session.id,
        timeLabel: dateLabel(session.scheduled_at),
        venue: session.room ?? null,
        code: session.code,
        remaining: session.remaining,
        capacity: session.capacity,
        full: session.full,
      })),
    },
  ]);

  const confirmRows = $derived(
    selectedSession
      ? [
          { label: 'Session', value: selectedSession.code },
          { label: 'Seats left', value: String(selectedSession.remaining) },
        ]
      : [],
  );

  const confirmDescription = $derived(
    selectedSession
      ? `Reserve ${dateLabel(selectedSession.scheduled_at)} at ${selectedSession.room ?? 'the assigned room'}?`
      : 'Confirm your selected consultation session.',
  );
</script>

<svelte:head><title>Consultation schedule</title></svelte:head>

<ApplicantLayout title="Consultation">
  <div class="mx-auto max-w-lg space-y-5">
    <div class="space-y-1">
      <h1 class="text-2xl font-semibold tracking-tight">{headline}</h1>
      {#if attempt}
        <p class="font-mono text-sm text-muted-foreground">Ref {attempt.reference_code}</p>
      {/if}
      {#if attempt?.advised}
        <div class="mt-2"><Badge variant="default">Advised</Badge></div>
      {/if}
    </div>

    <div class="sr-only">
      <E2PageState state={page_state} />
    </div>

    {#if page_state === 'booking_open'}
      <Card.Root class="overflow-hidden border-primary/25 shadow-sm">
        <Card.Header class="border-b bg-primary/5 pb-3">
          <div>
            <Card.Title class="text-lg">Available sessions</Card.Title>
            <Card.Description>
              Pick a session to reserve your consultation seat. Seats left update as others book.
            </Card.Description>
          </div>
        </Card.Header>
        <Card.Content class="space-y-4 pt-4">
          {#if slotGroups[0].slots.length === 0}
            <p class="text-sm text-muted-foreground">No open sessions yet. Check back soon.</p>
          {:else}
            <ReservationSlotList groups={slotGroups} onSelect={openConfirm} />
          {/if}
          <p class="flex gap-2 text-xs leading-relaxed text-muted-foreground">
            <ShieldAlert class="mt-0.5 size-3.5 shrink-0" />
            Confirming books your consultation immediately. The office may still reschedule you.
          </p>
        </Card.Content>
      </Card.Root>
    {:else if page_state === 'assigned_or_reserved' && assignment?.session}
      <Card.Root class="overflow-hidden border-primary/25 shadow-sm">
        <Card.Header class="border-b bg-primary/5 pb-3">
          <Card.Title class="text-lg">Consultation schedule</Card.Title>
          <Card.Description>
            {assignment.session.published ? 'Schedule published' : 'Scheduled — awaiting official publish'}
          </Card.Description>
        </Card.Header>
        <Card.Content class="space-y-4 pt-4">
          <div class="flex items-start gap-3">
            <CheckCircle2 class="mt-0.5 size-5 text-primary" />
            <div>
              <p class="font-medium">{dateLabel(assignment.session.scheduled_at)}</p>
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
            <p class="text-xs text-muted-foreground">You reserved this consultation yourself.</p>
          {:else if assignment.source === 'staff_assigned'}
            <p class="text-xs text-muted-foreground">Guidance assigned this consultation.</p>
          {/if}
        </Card.Content>
      </Card.Root>
    {:else if page_state === 'not_result_ready'}
      <Card.Root>
        <Card.Content class="space-y-3 p-6 text-center">
          <Clock3 class="mx-auto size-8 text-muted-foreground" />
          <p class="font-medium">Result not ready yet</p>
          <p class="text-sm text-muted-foreground">
            {message ||
              'Consultation unlocks after your result is ready. Check back once grading and result preparation are done.'}
          </p>
        </Card.Content>
      </Card.Root>
    {:else if page_state === 'no_open_sessions'}
      <Card.Root>
        <Card.Content class="space-y-3 p-6 text-center">
          <CalendarDays class="mx-auto size-8 text-muted-foreground" />
          <p class="font-medium">Booking not open</p>
          <p class="text-sm text-muted-foreground">
            {message ||
              'When Guidance opens a consultation session for booking, you can pick one here. Staff may also assign you directly.'}
          </p>
        </Card.Content>
      </Card.Root>
    {:else if page_state === 'denied'}
      <Card.Root>
        <Card.Content class="p-6 text-center text-sm text-muted-foreground">
          {message || 'This account cannot access consultation scheduling.'}
        </Card.Content>
      </Card.Root>
    {/if}
  </div>

  <ReservationConfirmSheet
    open={confirmOpen}
    title="Confirm consultation"
    description={confirmDescription}
    rows={confirmRows}
    confirmLabel="Confirm consultation"
    busyLabel="Booking…"
    busy={reserving}
    onConfirm={confirmReserve}
    onClose={() => {
      confirmOpen = false;
      selectedSession = null;
    }}
  />
</ApplicantLayout>
