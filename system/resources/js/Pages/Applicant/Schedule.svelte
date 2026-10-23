<script>
    /**
     * Applicant exam schedule: appointment details or open slot selection.
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
    import { onMount } from 'svelte';
    import { Link, router, usePage } from '@inertiajs/svelte';
    import {
        formatDate,
        formatDateTime,
        formatInstantTime,
        formatTime,
        formatTimeRange,
    } from '@/lib/date-utils.js';
    import {
        CalendarDays,
        CheckCircle2,
        Clock3,
        Compass,
        MapPin,
        ShieldAlert,
    } from 'lucide-svelte';
    import VenueCompassHud from '@/Components/Wayfinding/VenueCompassHud.svelte';
    let {
        page_state = 'denied',
        attempt = null,
        assignment = null,
        application = null,
        calendar_days = [],
        slot_switch = null,
        message = '',
        has_assigned_seat_instruction = false,
    } = $props();
    const page = usePage();
    onMount(() => {
        let active = true;
        let inFlight = false;
        let timer = null;
        const refresh = () => {
            if (
                !active ||
                !assignment?.session ||
                document.visibilityState !== 'visible' ||
                !navigator.onLine ||
                inFlight
            ) {
                return;
            }
            inFlight = true;
            router.reload({
                only: [
                    'page_state',
                    'assignment',
                    'slot_switch',
                    'calendar_days',
                    'has_assigned_seat_instruction',
                    'pipeline',
                    'message',
                ],
                preserveState: true,
                preserveScroll: true,
                onFinish: () => {
                    inFlight = false;
                },
            });
        };
        const start = () => {
            if (
                document.visibilityState === 'visible' &&
                navigator.onLine &&
                !timer
            ) {
                timer = window.setInterval(refresh, 15_000);
            }
        };
        const stop = () => {
            if (timer) window.clearInterval(timer);
            timer = null;
        };
        const onVisibilityChange = () => {
            if (document.visibilityState === 'visible') {
                refresh();
                start();
            } else {
                stop();
            }
        };
        const onOnline = () => {
            refresh();
            start();
        };
        const onOffline = () => stop();
        if (document.visibilityState === 'visible' && navigator.onLine) start();
        document.addEventListener('visibilitychange', onVisibilityChange);
        window.addEventListener('online', onOnline);
        window.addEventListener('offline', onOffline);
        return () => {
            active = false;
            stop();
            document.removeEventListener(
                'visibilitychange',
                onVisibilityChange,
            );
            window.removeEventListener('online', onOnline);
            window.removeEventListener('offline', onOffline);
        };
    });

    function actualStatusLabel(status) {
        if (status === 'in_progress') return 'In progress';
        if (status === 'completed') return 'Completed';
        return assignment?.session?.published
            ? 'Schedule published'
            : 'Assigned — awaiting official publish';
    }

    function formatActualTimestamp(value) {
        return value ? formatDateTime(value) : 'Not recorded';
    }

    function scheduledDateRange(session) {
        const startDate = session.date
            ? formatDate(session.date, 'long')
            : formatDate(session.scheduled_at, 'long');
        const endDate =
            session.end_date && session.end_date !== session.date
                ? ` – ${formatDate(session.end_date, 'long')}`
                : '';
        return `${startDate}${endDate}`;
    }

    function scheduledTimeRange(session) {
        return session.start_time
            ? formatTimeRange(session.start_time, session.end_time)
            : `${formatInstantTime(session.scheduled_at)}${session.effective_end_at ? ` – ${formatInstantTime(session.effective_end_at)}` : ''}`;
    }
    let confirmOpen = $state(false);
    let selectedSlot = $state(null);
    let reserving = $state(false);
    let switchMode = $state(false);
    let switchKey = $state(null);
    let tourOpen = $state(false);
    let wayfindingOpen = $state(false);
    let calendarView = $state('month');
    let reservationCalendarView = $state('strip');
    let selectedDate = $state(null);
    let visibleMonth = $state('');
    const currentSchedule = $derived(assignment?.session ?? null);
    const isTentative = $derived(
        assignment?.session?.status === 'draft',
    );
    const tentativeDays = $derived(slot_switch?.target_calendar_days ?? []);
    const activeCalendarDays = $derived(
        page_state === 'calendar_open' ? (calendar_days ?? []) : tentativeDays,
    );
    const wayfindingVenue = $derived(
        currentSchedule?.venue ??
            (currentSchedule
                ? {
                      id: currentSchedule.room_id ?? null,
                      code: currentSchedule.room_code ?? null,
                      name: currentSchedule.room,
                  }
                : null),
    );
    const allSlots = $derived(
        (calendar_days ?? []).flatMap((day) => day.slots ?? []),
    );
    const availableSlots = $derived(allSlots.filter((slot) => !slot.full));

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
            page_state !== 'calendar_open' &&
            daySlots.length === 1 &&
            assignment?.session
        ) {
            openSwitchConfirm(daySlots[0]);
        } else if (typeof document !== 'undefined') {
            const slotsId =
                page_state === 'calendar_open'
                    ? 'exam-reservation-slots-list'
                    : 'switch-slots-list';
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
                timeLabel: formatTimeRange(slot.start_time, slot.end_time),
                venue: slot.room ?? slot.venue ?? null,
                code: slot.code ?? null,
            })),
    );

    const scheduleContext = $derived.by(() => {
        const entityKey = String(attempt?.id ?? 'anonymous');
        const hasAnySelectable = availableSlots.length > 0;
        const isPub = Boolean(assignment?.session?.published);
        const safetyKey = `${page_state}:${application?.needs_correction ? 'correction' : 'ok'}:${isPub ? 'pub' : 'unpub'}:${hasAnySelectable ? 'avail' : 'full'}:${has_assigned_seat_instruction === true ? 'assigned-seat-instruction' : 'no-seat-instruction'}`;

        return {
            entityKey,
            safetyKey,
            freshness: 'current',
            pageState: page_state,
            hasAssignedSeatInstruction: has_assigned_seat_instruction === true,
            message: message || '',
            needsCorrection: Boolean(application?.needs_correction),
            canOpenCorrection: Boolean(
                application?.needs_correction &&
                application?.actions?.some((a) => a.key === 'fix_application'),
            ),
            slotCount: allSlots.length,
            availableSlotCount: availableSlots.length,
            reservationError: null,
            appointment: assignment?.session
                ? {
                      date: assignment.session.date ?? null,
                      startTime: assignment.session.start_time ?? null,
                      endTime: assignment.session.end_time ?? null,
                      room: assignment.session.room ?? null,
                      published: isPub,
                      source: assignment.source ?? 'unknown',
                  }
                : null,
            schedulingPolicy: null,
        };
    });

    const headline = $derived.by(() => {
        const map = {
            denied: 'Schedule unavailable',
            pending_acceptance: 'Waiting for acceptance',
            waiting_for_reservation_open: 'Scheduling not open yet',
            calendar_open: 'Choose your exam slot',
            assigned_tentative: 'Your exam appointment',
            assigned_published:
                assignment?.source === 'applicant_reserved'
                    ? 'Reserved by you'
                    : assignment?.source === 'staff_assigned'
                      ? 'Assigned by Guidance'
                      : 'Your exam appointment',
        };
        return map[page_state] ?? 'Exam Schedule';
    });

    function openConfirm(slot) {
        if (slot.full) return;
        selectedSlot = slot;
        switchMode = false;
        confirmOpen = true;
    }

    function openSwitchConfirm(slot) {
        if (slot.full || !assignment?.session) return;
        selectedSlot = slot;
        switchMode = true;
        switchKey = `switch-${attempt.id}-${slot.id}-${Date.now()}`;
        confirmOpen = true;
    }

    function confirmReserve() {
        if (!selectedSlot || !attempt) return;
        reserving = true;
        if (switchMode) {
            router.post(
                '/applicant/schedule/switch',
                {
                    exam_session_id: selectedSlot.id,
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
                        selectedSlot = null;
                        switchMode = false;
                        switchKey = null;
                    },
                },
            );
            return;
        }
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

    const slotGroups = $derived.by(() => {
        const day = calendar_days.find(
            (calendarDay) => calendarDay.date === selectedDate,
        );
        if (!day) return [];

        return [
            {
                date: day.date,
                label: String(dayNumber(day.date)),
                caption: `${weekday(day.date)} · ${monthShort(day.date)} · ${formatDate(day.date, 'long')}`,
                slots: (day.slots ?? []).map((slot) => ({
                    id: slot.id,
                    date: day.date,
                    timeLabel: formatTimeRange(slot.start_time, slot.end_time),
                    venue: slot.room ?? null,
                    code: slot.code,
                    remaining: slot.remaining,
                    capacity: slot.capacity,
                    full: slot.full,
                })),
            },
        ];
    });

    function appointmentCurrentLabel() {
        if (!assignment?.session) return '';
        const session = assignment.session;
        const date = session.date
            ? formatDate(session.date, 'long')
            : formatDate(session.scheduled_at, 'long');
        const time = session.start_time
            ? formatTimeRange(session.start_time, session.end_time)
            : `${formatInstantTime(session.scheduled_at)}${session.effective_end_at ? ` – ${formatInstantTime(session.effective_end_at)}` : ''}`;
        return `${date} · ${time} · ${session.room ?? 'Room TBD'}`;
    }

    function slotLabel(slot) {
        const codePrefix = slot.code ? `${slot.code} · ` : '';
        return `${codePrefix}${formatDate(slot.date, 'long')} · ${formatTimeRange(slot.start_time, slot.end_time)} · ${slot.room ?? slot.venue ?? 'Room TBD'}`;
    }

    const confirmRows = $derived(
        selectedSlot
            ? switchMode
                ? [
                      { label: 'Current', value: appointmentCurrentLabel() },
                      { label: 'New', value: slotLabel(selectedSlot) },
                  ]
                : [
                      { label: 'Session', value: selectedSlot.code },
                      {
                          label: 'Slots left',
                          value: String(selectedSlot.remaining),
                      },
                  ]
            : [],
    );

    const confirmDescription = $derived(
        switchMode
            ? 'Your current appointment stays until you confirm.'
            : selectedSlot
              ? `Book ${formatDate(selectedSlot.date, 'long')} · ${formatTimeRange(selectedSlot.start_time, selectedSlot.end_time)} at ${selectedSlot.room ?? 'the assigned room'}?`
              : 'Confirm your selected exam slot.',
    );
</script>

<svelte:head><title>Exam Schedule</title></svelte:head>

<ApplicantLayout title="Exam Schedule">
    <div class="mx-auto max-w-lg space-y-5">
        <div class="space-y-1">
            <div class="flex flex-wrap items-center gap-2">
                <h1 class="text-2xl font-semibold tracking-tight">
                    {headline}
                </h1>
                <TourTriggerGlyph
                    tourKey="applicant-exam-schedule"
                    label="Need help with your exam schedule? Ask Kuya Xiam!"
                    onclick={() => (tourOpen = true)}
                />
            </div>
            {#if attempt}
                <p class="text-muted-foreground font-mono text-sm">
                    Applicant No. {attempt.reference_code}
                </p>
            {/if}
        </div>

        <div class="sr-only">
            <E2PageState
                state={page_state === 'assigned_tentative'
                    ? 'tentative'
                    : page_state === 'assigned_published'
                      ? 'published'
                      : page_state}
            />
        </div>

        {#if page.props?.errors?.switch || page.props?.errors?.reserve}
            <Card.Root class="border-destructive/40 bg-destructive/10">
                <Card.Content class="text-destructive p-4 text-sm font-medium">
                    {page.props.errors.switch || page.props.errors.reserve}
                </Card.Content>
            </Card.Root>
        {/if}

        {#if application?.needs_correction}
            <Card.Root class="border-amber-500/40 bg-amber-500/10">
                <Card.Content class="space-y-3 p-4">
                    <p class="font-medium">Registrar needs a correction</p>
                    {#if application.correction_notes}
                        <p class="text-muted-foreground text-sm">
                            {application.correction_notes}
                        </p>
                    {/if}
                    {#if application.correction_href}
                        <Link href={application.correction_href}>
                            <Button class="min-h-11 w-full"
                                >Fix application</Button
                            >
                        </Link>
                    {/if}
                </Card.Content>
            </Card.Root>
        {/if}
        {#if isTentative && assignment?.session}
            <Card.Root
                class="overflow-hidden border-amber-500/40 shadow-sm"
                data-tour="exam-schedule-appointment"
            >
                <Card.Header class="border-b bg-amber-500/10 pb-3">
                    <div
                        class="flex flex-wrap items-center justify-between gap-2"
                    >
                        <Card.Title class="text-lg">Exam appointment</Card.Title
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
                        <div class="min-w-0 space-y-1">
                            <p class="font-medium break-words">
                                {scheduledDateRange(assignment.session)}
                            </p>
                            <p
                                class="text-muted-foreground text-sm break-words"
                            >
                                {scheduledTimeRange(assignment.session)}
                            </p>
                        </div>
                    </div>
                    <div class="flex min-w-0 items-start gap-3">
                        <MapPin class="text-primary mt-0.5 size-5 shrink-0" />
                        <p class="min-w-0 font-medium break-words">
                            {assignment.session.room ?? 'Room TBD'}
                        </p>
                    </div>
                    <p class="text-muted-foreground text-sm leading-relaxed">
                        Need a special schedule arrangement?
                        <Link
                            href="/applicant/admission-support?group=offices"
                            class="text-primary font-medium underline underline-offset-4"
                            >Message Guidance →</Link
                        >
                    </p>
                </Card.Content>
            </Card.Root>
            {#if (slot_switch?.target_calendar_days?.length ?? 0) > 0}
                <ApplicantScheduleCalendar
                    headingId="exam-available-dates"
                    days={tentativeDays}
                    {selectedDate}
                    {visibleMonth}
                    view={calendarView}
                    onViewChange={(view) => (calendarView = view)}
                    onSelectDate={selectDate}
                    onMonthChange={(month) => (visibleMonth = month)}
                >
                    {#if switchSlotCards.length > 0}
                        <ul id="switch-slots-list" class="space-y-2">
                            {#each switchSlotCards as slot (slot.id)}
                                <li
                                    class="hover:border-primary/50 hover:bg-muted/20 flex min-w-0 flex-wrap items-center justify-between gap-3 rounded-lg border p-3 transition"
                                >
                                    <button
                                        type="button"
                                        class="focus-visible:ring-ring min-w-0 flex-1 space-y-1 rounded-md text-left focus-visible:ring-2 focus-visible:outline-none"
                                        onclick={() => openSwitchConfirm(slot)}
                                    >
                                        <div class="flex items-center gap-2">
                                            <p class="font-medium">
                                                {slot.timeLabel}
                                            </p>
                                            {#if slot.code}
                                                <Badge
                                                    variant="outline"
                                                    class="text-xs"
                                                    >{slot.code}</Badge
                                                >
                                            {/if}
                                        </div>
                                        <p
                                            class="text-muted-foreground text-sm break-words"
                                        >
                                            {slot.venue ?? 'Room TBD'}
                                        </p>
                                        <p
                                            class="text-muted-foreground text-xs"
                                        >
                                            {slot.remaining ?? 'Available'} seats
                                            left
                                        </p>
                                    </button>
                                    <Button
                                        type="button"
                                        class="min-h-11 shrink-0"
                                        onclick={() => openSwitchConfirm(slot)}
                                    >
                                        Switch Slot
                                    </Button>
                                </li>
                            {/each}
                        </ul>
                    {/if}
                </ApplicantScheduleCalendar>
            {:else if slot_switch !== null}
                <p class="text-muted-foreground text-sm">
                    No alternative slots open for booking.
                </p>
            {/if}
        {:else if page_state === 'calendar_open'}
            <Card.Root
                class="border-primary/25 overflow-hidden shadow-sm"
                data-tour="exam-schedule-slots"
            >
                <Card.Header class="bg-primary/5 border-b pb-3">
                    <div>
                        <Card.Title class="text-lg">Available slots</Card.Title>
                        <Card.Description>
                            Tap a time to reserve your slot. Available slots
                            update as others book.
                        </Card.Description>
                    </div>
                </Card.Header>
                <Card.Content class="space-y-4 pt-4">
                    {#if calendar_days.length === 0}
                        <p class="text-muted-foreground text-sm">
                            No open sessions yet. Check back soon.
                        </p>
                    {:else}
                        <ApplicantScheduleCalendar
                            headingId="exam-reservation-dates"
                            days={calendar_days}
                            {selectedDate}
                            {visibleMonth}
                            view={reservationCalendarView}
                            onViewChange={(view) =>
                                (reservationCalendarView = view)}
                            onSelectDate={selectDate}
                            onMonthChange={(month) => (visibleMonth = month)}
                        >
                            {#if slotGroups.length === 0}
                                <p class="text-muted-foreground text-sm">
                                    No open sessions are currently available.
                                    Check back soon.
                                </p>
                            {:else}
                                <div id="exam-reservation-slots-list">
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
                        Confirming reserves your slot immediately. Campus emergencies
                        may still require rescheduling.
                    </p>
                </Card.Content>
            </Card.Root>
        {:else if page_state === 'assigned_published' && assignment?.session}
            <Card.Root
                class="border-primary/25 overflow-hidden shadow-sm"
                data-tour="exam-schedule-appointment"
            >
                <Card.Header class="bg-primary/5 border-b pb-3">
                    <Card.Title class="text-lg">
                        {#if assignment.source === 'applicant_reserved'}
                            Reserved by you
                        {:else if assignment.source === 'staff_assigned'}
                            Assigned by Guidance
                        {:else}
                            Exam appointment
                        {/if}
                    </Card.Title>
                    <Card.Description>
                        {actualStatusLabel(assignment.session.status)}
                    </Card.Description>
                </Card.Header>
                <Card.Content class="space-y-4 pt-4">
                    {#if assignment.session.status === 'in_progress' || assignment.session.status === 'completed'}
                        <dl class="space-y-2 text-sm">
                            <div>
                                <dt class="text-muted-foreground">
                                    Started at
                                </dt>
                                <dd class="font-medium">
                                    {formatActualTimestamp(
                                        assignment.session.started_at,
                                    )}
                                </dd>
                            </div>
                            <div>
                                <dt class="text-muted-foreground">
                                    {assignment.session.status === 'in_progress'
                                        ? 'Expected end'
                                        : 'Ended at'}
                                </dt>
                                <dd class="font-medium">
                                    {formatActualTimestamp(
                                        assignment.session.status ===
                                            'in_progress'
                                            ? assignment.session
                                                  .effective_end_at
                                            : assignment.session.closed_at,
                                    )}
                                </dd>
                            </div>
                            <div>
                                <dt class="text-muted-foreground">
                                    Original scheduled start
                                </dt>
                                <dd class="font-medium">
                                    {assignment.session.date
                                        ? formatDate(
                                              assignment.session.date,
                                              'long',
                                          )
                                        : formatDate(
                                              assignment.session.scheduled_at,
                                              'long',
                                          )} · {assignment.session.start_time
                                        ? formatTime(
                                              assignment.session.start_time,
                                          )
                                        : formatInstantTime(
                                              assignment.session.scheduled_at,
                                          )}
                                </dd>
                            </div>
                        </dl>
                        <p class="text-muted-foreground text-sm">
                            {assignment.session.code}
                        </p>
                    {:else}
                        <div class="flex items-start gap-3">
                            <CheckCircle2 class="text-primary mt-0.5 size-5" />
                            <div>
                                <p class="font-medium">
                                    {scheduledDateRange(assignment.session)} · {scheduledTimeRange(
                                        assignment.session,
                                    )}
                                </p>
                                <p class="text-muted-foreground text-sm">
                                    {assignment.session.code}
                                </p>
                            </div>
                        </div>
                    {/if}
                    <div class="flex items-start gap-3">
                        <MapPin class="text-primary mt-0.5 size-5" />
                        <p class="font-medium">
                            {assignment.session.room ?? 'Room TBD'}
                        </p>
                        {#if wayfindingVenue && (wayfindingVenue.latitude != null || wayfindingVenue.longitude != null || wayfindingVenue.name)}
                            <Button
                                variant="outline"
                                size="sm"
                                class="min-h-9 gap-1.5"
                                onclick={() => (wayfindingOpen = true)}
                                ><Compass class="size-4" /> Find Venue</Button
                            >
                        {/if}
                    </div>
                    <div class="flex flex-wrap items-center gap-2">
                        <Badge variant="outline">
                            {assignment.source === 'applicant_reserved'
                                ? 'Reserved by you'
                                : 'Assigned by Guidance'}
                        </Badge>
                    </div>
                    {#if has_assigned_seat_instruction === true}
                        <p class="text-muted-foreground text-sm">
                            Follow staff-provided assigned-seat instructions at
                            the venue.
                        </p>
                    {/if}
                    {#if assignment.source === 'applicant_reserved'}
                        <p class="text-muted-foreground text-xs">
                            Reserved by you directly through the portal.
                        </p>
                    {:else if assignment.source === 'staff_assigned'}
                        <p class="text-muted-foreground text-xs">
                            Assigned by Guidance staff.
                        </p>
                    {/if}
                    <Link
                        href="/applicant/exam-day"
                        class="text-primary inline-flex items-center gap-1 text-sm font-medium underline-offset-4 hover:underline"
                    >
                        View Exam Day details
                    </Link>
                </Card.Content>
            </Card.Root>
        {:else if page_state === 'waiting_for_reservation_open'}
            <Card.Root>
                <Card.Content class="space-y-3 p-6 text-center">
                    <CalendarDays
                        class="text-muted-foreground mx-auto size-8"
                    />
                    <p class="font-medium">
                        Reservation window closed or not opened
                    </p>
                    <p class="text-muted-foreground text-sm">
                        {message ||
                            'When Guidance opens a session for booking, you can pick a time here. Staff may also assign you directly.'}
                    </p>
                </Card.Content>
            </Card.Root>
        {:else if page_state === 'pending_acceptance'}
            <Card.Root>
                <Card.Content class="space-y-3 p-6 text-center">
                    <Clock3 class="text-muted-foreground mx-auto size-8" />
                    <p class="font-medium">Acceptance comes first</p>
                    <p class="text-muted-foreground text-sm">
                        {message ||
                            'Exam scheduling unlocks after the registrar accepts your application.'}
                    </p>
                </Card.Content>
            </Card.Root>
        {:else if page_state === 'denied'}
            <Card.Root>
                <Card.Content
                    class="text-muted-foreground p-6 text-center text-sm"
                >
                    {message || 'This account cannot access exam scheduling.'}
                </Card.Content>
            </Card.Root>
        {/if}
    </div>

    <ReservationConfirmSheet
        bind:open={confirmOpen}
        title={switchMode ? 'Switch exam slot?' : 'Confirm exam slot'}
        description={confirmDescription}
        rows={confirmRows}
        confirmLabel={switchMode ? 'Confirm switch' : 'Confirm reservation'}
        busyLabel={switchMode ? 'Switching…' : 'Booking…'}
        busy={reserving}
        onConfirm={confirmReserve}
        onClose={() => {
            confirmOpen = false;
            selectedSlot = null;
            switchMode = false;
            switchKey = null;
        }}
    />
    <XiamNpcDialog
        tourKey="applicant-exam-schedule"
        context={scheduleContext}
        bind:open={tourOpen}
    />
    {#if wayfindingVenue}
        <VenueCompassHud
            bind:open={wayfindingOpen}
            venue={wayfindingVenue}
            onClose={() => (wayfindingOpen = false)}
        />
    {/if}
</ApplicantLayout>
