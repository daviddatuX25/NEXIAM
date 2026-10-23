<script>
    /**
     * Applicant Release Schedule — assigned appointment details or open reservation slots.
     */
    import ApplicantLayout from '@/Pages/Applicant/ApplicantLayout.svelte';
    import TourTriggerGlyph from '@/Components/kit/Walkthrough/TourTriggerGlyph.svelte';
    import XiamNpcDialog from '@/Components/kit/Walkthrough/XiamNpcDialog.svelte';
    import E2PageState from '@/Pages/Admission/E2PageState.svelte';
    import ReservationSlotList from '@/Components/applicant/ReservationSlotList.svelte';
    import ReservationConfirmSheet from '@/Components/applicant/ReservationConfirmSheet.svelte';
    import ApplicantScheduleCalendar from '@/Components/applicant/ApplicantScheduleCalendar.svelte';
    import { Badge } from '@/Components/ui/badge';
    import { Button } from '@/Components/ui/button';
    import * as Card from '@/Components/ui/card';
    import { Link, router, usePage } from '@inertiajs/svelte';
    import {
        CalendarDays,
        CheckCircle2,
        Clock3,
        MapPin,
        ShieldAlert,
    } from 'lucide-svelte';
    import {
        formatDate,
        formatDateTime,
        formatInstantTime,
        formatTimeRange,
    } from '@/lib/date-utils.js';

    let {
        page_state = 'denied',
        attempt = null,
        assignment = null,
        sessions = [],
        calendar_days = [],
        slot_switch = null,
        message = '',
    } = $props();
    const page = usePage();

    let confirmOpen = $state(false);
    let selectedSession = $state(null);
    let reserving = $state(false);
    let switchMode = $state(false);
    let switchKey = $state(null);
    let calendarView = $state('month');
    let reservationCalendarView = $state('strip');
    let selectedDate = $state(null);
    let visibleMonth = $state('');
    let tourOpen = $state(false);

    const isTentative = $derived(
        page_state === 'assigned_tentative' ||
            slot_switch !== null ||
            Boolean(assignment?.session && !assignment.session.published),
    );
    const tentativeDays = $derived(slot_switch?.target_calendar_days ?? []);
    const bookingCalendarDays = $derived.by(() => {
        if (calendar_days.length > 0) return calendar_days;

        const daysByDate = Object.create(null);
        for (const session of sessions) {
            const date = session.date ?? session.scheduled_at?.slice(0, 10);
            if (!date) continue;
            if (!daysByDate[date]) daysByDate[date] = { date, slots: [] };
            daysByDate[date].slots.push(session);
        }
        return Object.values(daysByDate);
    });
    const activeCalendarDays = $derived(
        page_state === 'booking_open' ? bookingCalendarDays : tentativeDays,
    );
    $effect(() => {
        const selectableDays = activeCalendarDays.filter((day) =>
            day.slots?.some((slot) => !slot.full && (slot.remaining ?? 1) > 0),
        );
        if (!selectableDays.some((day) => day.date === selectedDate)) {
            selectedDate = selectableDays[0]?.date ?? null;
            visibleMonth = selectedDate?.slice(0, 7) ?? '';
            return;
        }
        if (!visibleMonth && selectableDays.length)
            visibleMonth = selectedDate.slice(0, 7);
    });
    function selectDate(date) {
        selectedDate = date;
        visibleMonth = date.slice(0, 7);
        const daySlots = (
            activeCalendarDays.find((day) => day.date === date)?.slots ?? []
        ).filter((slot) => !slot.full && (slot.remaining ?? 1) > 0);
        if (
            page_state !== 'booking_open' &&
            daySlots.length === 1 &&
            assignment?.session
        ) {
            openSwitchConfirm(daySlots[0]);
        } else if (typeof document !== 'undefined') {
            const slotsId =
                page_state === 'booking_open'
                    ? 'release-reservation-slots-list'
                    : 'release-switch-slots-list';
            const slotsEl = document.getElementById(slotsId);
            if (typeof slotsEl?.scrollIntoView === 'function') {
                slotsEl.scrollIntoView({
                    behavior: 'smooth',
                    block: 'nearest',
                });
            }
        }
    }

    const selectedDaySlots = $derived(
        activeCalendarDays.find((day) => day.date === selectedDate)?.slots ??
            [],
    );

    const switchSlotCards = $derived(
        selectedDaySlots
            .filter((slot) => !slot.full && (slot.remaining ?? 1) > 0)
            .map((slot) => ({
                ...slot,
                date: selectedDate,
                timeLabel:
                    slot.time_label ??
                    (slot.start_time
                        ? formatTimeRange(slot.start_time, slot.end_time)
                        : formatInstantTime(slot.scheduled_at)),
                venue:
                    slot.room ?? slot.venue?.name ?? slot.venue?.code ?? null,
                code: slot.code ?? null,
            })),
    );

    const headline = $derived.by(() => {
        const map = {
            denied: 'Release Day appointment unavailable',
            not_result_ready: 'Release Day scheduling is not open yet',
            no_open_sessions: 'Release Day appointment booking closed',
            booking_open: 'Choose your Release Day appointment',
            assigned_or_reserved: 'Your Release Day appointment',
            advised_completed: 'Release Day appointment',
        };
        return map[page_state] ?? 'Release Day appointment';
    });

    const releaseScheduleContext = $derived.by(() => ({
        entityKey: String(attempt?.id ?? 'anonymous'),
        safetyKey: `${page_state}:${attempt?.advised ? 'advised' : 'unadvised'}`,
        freshness: 'current',
        pageState: page_state,
        isAdvised: Boolean(attempt?.advised),
        hasAppointment: Boolean(assignment?.session),
        openSlotsCount: sessions.length,
    }));

    function openConfirm(session) {
        if (session.full) return;
        selectedSession = session;
        switchMode = false;
        confirmOpen = true;
    }

    function openSwitchConfirm(session) {
        if (session.full || !assignment?.session || assignment.scoring_blocker) return;
        selectedSession = session;
        switchMode = true;
        switchKey = `switch-${attempt.id}-${session.id}-${Date.now()}`;
        confirmOpen = true;
    }

    function confirmReserve() {
        if (!selectedSession || !attempt) return;
        reserving = true;
        if (switchMode) {
            router.post(
                '/applicant/release-schedule/switch',
                {
                    consultation_session_id: selectedSession.id,
                    admission_attempt_id: attempt.id,
                    expected_assignment_id: assignment.id,
                    expected_assignment_version: assignment.version,
                    idempotency_key: switchKey,
                },
                {
                    preserveScroll: true,
                    onFinish: () => {
                        reserving = false;
                        confirmOpen = false;
                        selectedSession = null;
                        switchMode = false;
                        switchKey = null;
                    },
                },
            );
            return;
        }
        router.post(
            '/applicant/release-schedule/reserve',
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

    function weekday(dateStr) {
        return new Date(`${dateStr}T12:00:00`).toLocaleDateString(undefined, {
            weekday: 'short',
        });
    }

    function dayNumber(dateStr) {
        return new Date(`${dateStr}T12:00:00`).getDate();
    }

    function monthShort(dateStr) {
        return new Date(`${dateStr}T12:00:00`).toLocaleDateString(undefined, {
            month: 'short',
        });
    }

    function dateLabel(iso) {
        return iso
            ? `${formatDate(iso, 'long')} · ${formatInstantTime(iso)}`
            : formatDateTime(iso);
    }

    const slotGroups = $derived.by(() => {
        const day = bookingCalendarDays.find(
            (calendarDay) => calendarDay.date === selectedDate,
        );
        if (!day) {
            if (bookingCalendarDays.length > 0 || sessions.length === 0)
                return [];
            return [
                {
                    date: null,
                    slots: sessions.map((session) => ({
                        id: session.id,
                        scheduled_at: session.scheduled_at,
                        room: session.room ?? null,
                        timeLabel: dateLabel(session.scheduled_at),
                        venue: session.room ?? null,
                        code: session.code,
                        remaining: session.remaining,
                        capacity: session.capacity,
                        full: session.full,
                    })),
                },
            ];
        }

        return [
            {
                label: String(dayNumber(day.date)),
                caption: `${weekday(day.date)} · ${monthShort(day.date)} · ${formatDate(day.date, 'long')}`,
                slots: (day.slots ?? []).map((slot) => ({
                    id: slot.id,
                    date: day.date,
                    start_time: slot.start_time,
                    end_time: slot.end_time,
                    timeLabel: slot.time_label ?? dateLabel(slot.scheduled_at),
                    venue: slot.room ?? null,
                    code: slot.code,
                    remaining: slot.remaining,
                    capacity: slot.capacity,
                    full: slot.full,
                })),
            },
        ];
    });

    function currentAppointmentLabel() {
        const session = assignment?.session;
        if (!session) return '';
        const date = dateLabel(session.scheduled_at);
        const room =
            session.venue?.name ??
            session.venue?.code ??
            session.room ??
            'Room TBD';
        return `${date} · ${room}`;
    }

    function selectedAppointmentLabel(session) {
        const date = session.date
            ? formatDate(session.date, 'long')
            : dateLabel(session.scheduled_at);
        const time =
            session.time_label ??
            (session.start_time
                ? formatTimeRange(session.start_time, session.end_time)
                : formatInstantTime(session.scheduled_at));
        const room =
            session.room ??
            session.venue?.name ??
            session.venue?.code ??
            'Room TBD';
        return `${date} · ${time} · ${room}`;
    }

    const confirmRows = $derived(
        selectedSession
            ? switchMode
                ? [
                      {
                          label: 'Current Release Day appointment',
                          value: currentAppointmentLabel(),
                      },
                      {
                          label: 'New Release Day appointment',
                          value: selectedAppointmentLabel(selectedSession),
                      },
                  ]
                : [
                      {
                          label: 'Release Day appointment',
                          value: selectedSession.code,
                      },
                      {
                          label: 'Places left',
                          value: String(selectedSession.remaining),
                      },
                  ]
            : [],
    );

    const confirmDescription = $derived(
        switchMode
            ? 'Your current Release Day appointment stays until you confirm.'
            : selectedSession
              ? `Reserve ${selectedSession.date ? formatDate(selectedSession.date, 'long') : dateLabel(selectedSession.scheduled_at)}${selectedSession.start_time || selectedSession.end_time ? ` · ${formatTimeRange(selectedSession.start_time, selectedSession.end_time)}` : ''} at ${selectedSession.venue ?? selectedSession.room ?? 'the assigned room'}?`
              : 'Confirm your selected Release Day appointment.',
    );
</script>

<svelte:head><title>Release Day Scheduling</title></svelte:head>

<ApplicantLayout title="Release Day Scheduling">
    <div class="mx-auto max-w-lg space-y-5">
        <div class="space-y-1" data-tour="release-schedule-header">
            <div class="flex flex-wrap items-center gap-2">
                <h1 class="text-2xl font-semibold tracking-tight">
                    {headline}
                </h1>
                <TourTriggerGlyph
                    tourKey="applicant-consultation"
                    label="Need help with your Release Day appointment? Ask Kuya Xiam!"
                    onclick={() => (tourOpen = true)}
                />
            </div>
            {#if attempt}
                <p class="text-muted-foreground font-mono text-sm">
                    Ref {attempt.reference_code}
                </p>
            {/if}
        </div>

        <div class="sr-only">
            <E2PageState state={page_state} />
        </div>

        {#if page.props?.errors?.switch || page.props?.errors?.reserve}
            <Card.Root class="border-destructive/40 bg-destructive/10">
                <Card.Content class="text-destructive p-4 text-sm font-medium">
                    {page.props.errors.switch || page.props.errors.reserve}
                </Card.Content>
            </Card.Root>
        {/if}
        <!-- CONTENT PANELS -->
        <div data-tour="release-schedule-content">
            {#if assignment?.scoring_blocker}
                <p role="status" class="rounded-md border border-amber-500/40 bg-amber-500/10 p-3 text-sm">
                    {assignment.scoring_blocker}
                </p>
            {/if}
            {#if isTentative && assignment?.session}
                <Card.Root
                    class="overflow-hidden border-amber-500/40 shadow-sm"
                >
                    <Card.Header class="border-b bg-amber-500/10 pb-3">
                        <div
                            class="flex flex-wrap items-center justify-between gap-2"
                        >
                            <Card.Title class="text-lg"
                                >Release Day appointment</Card.Title
                            >
                            <Badge
                                class="border-amber-500/40 bg-amber-500/15 text-amber-800 dark:text-amber-200"
                                variant="outline">Tentative</Badge
                            >
                        </div>
                    </Card.Header>
                    <Card.Content class="space-y-4 pt-4">
                        <div class="flex min-w-0 items-start gap-3">
                            <CalendarDays
                                class="text-primary mt-0.5 size-5 shrink-0"
                            />
                            <div class="min-w-0">
                                <p class="font-medium break-words">
                                    {dateLabel(assignment.session.scheduled_at)}
                                </p>
                                <p class="text-muted-foreground text-sm">
                                    Scheduled At
                                </p>
                            </div>
                        </div>
                        <div class="flex min-w-0 items-start gap-3">
                            <MapPin
                                class="text-primary mt-0.5 size-5 shrink-0"
                            />
                            <p class="min-w-0 font-medium break-words">
                                {assignment.session.venue?.name ??
                                    assignment.session.venue?.code ??
                                    assignment.session.room ??
                                    'Room TBD'}
                            </p>
                        </div>
                        <p
                            class="text-muted-foreground text-sm leading-relaxed"
                        >
                            Need a special schedule arrangement?
                            <Link
                                href="/applicant/admission-support?group=offices"
                                class="text-primary font-medium underline underline-offset-4"
                                >Message Guidance →</Link
                            >
                        </p>
                    </Card.Content>
                </Card.Root>
                {#if !assignment?.scoring_blocker && (slot_switch?.target_calendar_days?.length ?? 0) > 0}
                    <ApplicantScheduleCalendar
                        headingId="release-available-dates"
                        days={tentativeDays}
                        {selectedDate}
                        {visibleMonth}
                        view={calendarView}
                        onViewChange={(view) => (calendarView = view)}
                        onSelectDate={selectDate}
                        onMonthChange={(month) => (visibleMonth = month)}
                    >
                        {#if switchSlotCards.length > 0}
                            <ul
                                id="release-switch-slots-list"
                                class="space-y-2"
                            >
                                {#each switchSlotCards as session (session.id)}
                                    <li
                                        class="hover:border-primary/50 hover:bg-muted/20 flex min-w-0 flex-wrap items-center justify-between gap-3 rounded-lg border p-3 transition"
                                    >
                                        <button
                                            type="button"
                                            class="focus-visible:ring-ring min-w-0 flex-1 space-y-1 rounded-md text-left focus-visible:ring-2 focus-visible:outline-none"
                                            onclick={() =>
                                                openSwitchConfirm(session)}
                                        >
                                            <div
                                                class="flex items-center gap-2"
                                            >
                                                <p class="font-medium">
                                                    {session.timeLabel}
                                                </p>
                                                {#if session.code}
                                                    <Badge
                                                        variant="outline"
                                                        class="text-xs"
                                                        >{session.code}</Badge
                                                    >
                                                {/if}
                                            </div>
                                            <p
                                                class="text-muted-foreground text-sm break-words"
                                            >
                                                {session.venue ?? 'Room TBD'}
                                            </p>
                                            <p
                                                class="text-muted-foreground text-xs"
                                            >
                                                {session.remaining ??
                                                    'Available'} seats left
                                            </p>
                                        </button>
                                        <Button
                                            type="button"
                                            class="min-h-11 shrink-0"
                                            onclick={() =>
                                                openSwitchConfirm(session)}
                                        >
                                            Switch Slot
                                        </Button>
                                    </li>
                                {/each}
                            </ul>
                        {/if}
                    </ApplicantScheduleCalendar>
                {:else if !assignment?.scoring_blocker && slot_switch !== null}
                    <p class="text-muted-foreground text-sm">
                        No alternative slots open for booking.
                    </p>
                {/if}
            {:else if page_state === 'booking_open'}
                <Card.Root class="border-primary/25 overflow-hidden shadow-sm">
                    <Card.Header class="bg-primary/5 border-b pb-3">
                        <div>
                            <Card.Title class="text-lg"
                                >Available Release Day appointments</Card.Title
                            >
                            <Card.Description>
                                Choose a date and time for your Release Day
                                appointment. Places left update as others
                                reserve.
                            </Card.Description>
                        </div>
                    </Card.Header>
                    <Card.Content class="space-y-4 pt-4">
                        {#if bookingCalendarDays.length === 0}
                            <p class="text-muted-foreground text-sm">
                                No Release Day appointments are open yet. Check
                                back soon.
                            </p>
                        {:else}
                            <ApplicantScheduleCalendar
                                headingId="release-reservation-dates"
                                days={bookingCalendarDays}
                                {selectedDate}
                                {visibleMonth}
                                view={reservationCalendarView}
                                onViewChange={(view) =>
                                    (reservationCalendarView = view)}
                                onSelectDate={selectDate}
                                onMonthChange={(month) =>
                                    (visibleMonth = month)}
                            >
                                {#if slotGroups.length === 0}
                                    <p class="text-muted-foreground text-sm">
                                        No Release Day appointments are
                                        currently available. Check back soon.
                                    </p>
                                {:else}
                                    <div id="release-reservation-slots-list">
                                        <ReservationSlotList
                                            groups={slotGroups}
                                            onSelect={openConfirm}
                                        />
                                    </div>
                                {/if}
                            </ApplicantScheduleCalendar>
                        {/if}
                        <p
                            class="text-muted-foreground flex gap-2 text-xs leading-relaxed"
                        >
                            <ShieldAlert class="mt-0.5 size-3.5 shrink-0" />
                            Confirming reserves your Release Day appointment. The
                            office may still reschedule it if needed.
                        </p>
                    </Card.Content>
                </Card.Root>
            {:else if page_state === 'assigned_or_reserved' && assignment?.session}
                <Card.Root class="border-primary/25 overflow-hidden shadow-sm">
                    <Card.Header class="bg-primary/5 border-b pb-3">
                        <Card.Title class="text-lg"
                            >Release Day appointment</Card.Title
                        >
                        <Card.Description>
                            {assignment.session.published
                                ? 'Official schedule published'
                                : 'Scheduled — awaiting official publish'}
                        </Card.Description>
                    </Card.Header>
                    <Card.Content class="space-y-4 pt-4">
                        <div class="flex items-start gap-3">
                            <CheckCircle2 class="text-primary mt-0.5 size-5" />
                            <div>
                                <p class="font-medium">
                                    {dateLabel(assignment.session.scheduled_at)}
                                </p>
                                <p class="text-muted-foreground text-sm">
                                    {assignment.session.code}
                                </p>
                            </div>
                        </div>
                        <div class="flex items-start gap-3">
                            <MapPin class="text-primary mt-0.5 size-5" />
                            <p class="font-medium">
                                {assignment.session.venue?.name ??
                                    assignment.session.venue?.code ??
                                    assignment.session.room ??
                                    'Room TBD'}
                            </p>
                        </div>
                        <Badge
                            variant={assignment.session.published
                                ? 'default'
                                : 'secondary'}
                        >
                            {assignment.session.published
                                ? 'Official schedule'
                                : 'Awaiting publish'}
                        </Badge>
                        {#if assignment.source === 'applicant_reserved'}
                            <p class="text-muted-foreground text-xs">
                                You reserved this Release Day appointment.
                            </p>
                        {:else if assignment.source === 'staff_assigned'}
                            <p class="text-muted-foreground text-xs">
                                This Release Day appointment was assigned by
                                staff.
                            </p>
                        {/if}
                        <Link
                            href="/applicant/release-day"
                            class="text-primary inline-flex items-center gap-1 text-sm font-medium underline-offset-4 hover:underline"
                        >
                            View Release Day details
                        </Link>
                    </Card.Content>
                </Card.Root>
            {:else if page_state === 'advised_completed'}
                <Card.Root
                    class="border-emerald-500/40 bg-emerald-500/5 shadow-sm"
                >
                    <Card.Content class="space-y-4 p-6 text-center">
                        <CheckCircle2
                            class="mx-auto size-10 text-emerald-600 dark:text-emerald-400"
                        />
                        <div class="space-y-1">
                            <h2 class="text-foreground text-lg font-semibold">
                                No appointment is listed here
                            </h2>
                            <p class="text-muted-foreground text-sm">
                                Your completed encounter is shown separately on
                                Release Day.
                            </p>
                        </div>
                        <Link
                            href="/applicant/release-day"
                            class="inline-block pt-1"
                        >
                            <Button type="button" class="min-h-11"
                                >View Release Day</Button
                            >
                        </Link>
                    </Card.Content>
                </Card.Root>
            {:else if page_state === 'not_result_ready'}
                <Card.Root>
                    <Card.Content class="space-y-3 p-6 text-center">
                        <Clock3 class="text-muted-foreground mx-auto size-8" />
                        <p class="font-medium">
                            Release scheduling is not open yet
                        </p>
                        <p class="text-muted-foreground text-sm">
                            {message ||
                                'Release Day appointments become available when your result is ready. Check back later.'}
                        </p>
                    </Card.Content>
                </Card.Root>
            {:else if page_state === 'no_open_sessions'}
                <Card.Root class="overflow-hidden shadow-sm">
                    <Card.Header class="bg-muted/20 border-b pb-3">
                        <div class="flex items-center gap-2.5">
                            <CalendarDays
                                class="text-muted-foreground size-5"
                            />
                            <Card.Title class="text-base"
                                >Release Day appointment booking</Card.Title
                            >
                        </div>
                    </Card.Header>
                    <Card.Content class="space-y-4 p-6">
                        <div
                            class="text-muted-foreground space-y-2 text-sm leading-relaxed"
                        >
                            <p class="text-foreground font-medium">
                                No Release Day appointments are open for booking
                                right now.
                            </p>
                            <p>
                                Check back later for open reservation dates or a
                                Release Day appointment assigned by staff.
                            </p>
                        </div>
                    </Card.Content>
                </Card.Root>
            {:else if page_state === 'denied'}
                <Card.Root>
                    <Card.Content
                        class="text-muted-foreground p-6 text-center text-sm"
                    >
                        {message ||
                            'This account cannot access Release Day appointment scheduling.'}
                    </Card.Content>
                </Card.Root>
            {/if}
        </div>
    </div>

    <ReservationConfirmSheet
        bind:open={confirmOpen}
        title={switchMode
            ? 'Switch Release Day appointment?'
            : 'Confirm release appointment'}
        description={confirmDescription}
        rows={confirmRows}
        confirmLabel={switchMode ? 'Confirm switch' : 'Confirm reservation'}
        busyLabel={switchMode ? 'Switching…' : 'Booking…'}
        busy={reserving}
        onConfirm={confirmReserve}
        onClose={() => {
            confirmOpen = false;
            selectedSession = null;
            switchMode = false;
            switchKey = null;
        }}
    />

    <XiamNpcDialog
        tourKey="applicant-consultation"
        context={releaseScheduleContext}
        bind:open={tourOpen}
    />
</ApplicantLayout>
