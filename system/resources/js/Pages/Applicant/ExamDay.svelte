<script lang="ts">
    import { onMount } from 'svelte';
    import ApplicantLayout from '@/Pages/Applicant/ApplicantLayout.svelte';
    import * as Card from '@/Components/ui/card';
    import { Link } from '@inertiajs/svelte';
    import {
        ExamDayClock,
        formatCountdown,
        type ExamDayClockState,
        type ExamDaySession,
    } from '@/lib/applicant-exam-clock';

    type Attendance = {
        outcome: 'present' | 'absent' | 'void' | null;
        recorded_at: string | null;
        recorder: string | null;
    };
    type ExamDay = {
        page_state: 'ready' | 'empty' | 'denied';
        attendance: Attendance | null;
        session:
            | (ExamDaySession & {
                  code: string;
                  scheduled_starts_at: string | null;
                  scheduled_date?: string | null;
                  scheduled_start_time?: string | null;
                  scheduled_end_time?: string | null;
                  started_at: string | null;
                  closed_at: string | null;
                  version: number;
              })
            | null;
        server_now: string;
        help_href: string;
    };
    type SessionTimeFact = {
        label: string;
        value: string;
    };

    let { exam_day } = $props<{ exam_day: ExamDay }>();
    let snapshot = $state(exam_day);
    let clockState = $state<ExamDayClockState>('unsynchronized');
    let countdown = $state('');
    let online = $state(typeof navigator === 'undefined' || navigator.onLine);
    const clock = new ExamDayClock();

    function formatDate(value: string | null): string {
        if (!value) return 'Not scheduled';
        const date = new Date(value);
        if (Number.isNaN(date.getTime())) return 'Not available';
        return new Intl.DateTimeFormat('en', {
            month: 'long',
            day: 'numeric',
            year: 'numeric',
            hour: 'numeric',
            minute: '2-digit',
        }).format(date);
    }
    function formatScheduledDate(
        dateValue: string | null | undefined,
        timeValue: string | null | undefined,
        fallbackIso: string | null,
    ): string {
        if (dateValue && timeValue) {
            const dateMatch = dateValue.match(/^(\d{4})-(\d{2})-(\d{2})$/);
            const timeMatch = timeValue.match(/^(\d{1,2}):(\d{2})/);
            if (dateMatch && timeMatch) {
                const localDate = new Date(
                    Number(dateMatch[1]),
                    Number(dateMatch[2]) - 1,
                    Number(dateMatch[3]),
                    Number(timeMatch[1]),
                    Number(timeMatch[2]),
                );
                if (!Number.isNaN(localDate.getTime())) {
                    return new Intl.DateTimeFormat('en', {
                        month: 'long',
                        day: 'numeric',
                        year: 'numeric',
                        hour: 'numeric',
                        minute: '2-digit',
                    }).format(localDate);
                }
            }
        }
        return formatDate(fallbackIso);
    }
    function sessionStatusLabel(status: string): string {
        const labels: Record<string, string> = {
            draft: 'Schedule pending',
            published: 'Scheduled',
            in_progress: 'In progress',
            completed: 'Completed',
            cancelled: 'Cancelled',
        };

        return (
            labels[status] ??
            status
                .replaceAll('_', ' ')
                .replace(/^./, (character) => character.toUpperCase())
        );
    }

    const timeFacts: SessionTimeFact[] = $derived.by(() => {
        const session = snapshot.session;
        if (!session) return [];

        if (session.status === 'in_progress') {
            return [
                {
                    label: 'Started at',
                    value: session.started_at
                        ? formatDate(session.started_at)
                        : 'Not recorded',
                },
                {
                    label: 'Expected end',
                    value: session.effective_end_at
                        ? formatDate(session.effective_end_at)
                        : 'Not recorded',
                },
            ];
        }

        if (session.status === 'completed') {
            return [
                {
                    label: 'Started at',
                    value: session.started_at
                        ? formatDate(session.started_at)
                        : 'Not recorded',
                },
                {
                    label: 'Ended at',
                    value: session.closed_at
                        ? formatDate(session.closed_at)
                        : 'Not recorded',
                },
            ];
        }

        return [
            {
                label: 'Scheduled start',
                value: formatScheduledDate(
                    session.scheduled_date,
                    session.scheduled_start_time,
                    session.scheduled_starts_at,
                ),
            },
            {
                label: 'Scheduled end',
                value: formatScheduledDate(
                    session.scheduled_date,
                    session.scheduled_end_time,
                    session.effective_end_at,
                ),
            },
        ];
    });

    function renderClock(): void {
        if (!online) {
            clockState = 'uncertain';
            countdown = '';
            return;
        }

        const monotonicNow = performance.now();
        clockState = clock.state(snapshot.session, monotonicNow);
        const seconds = clock.remainingSeconds(monotonicNow);
        countdown = seconds !== null ? formatCountdown(seconds) : '';
    }

    onMount(() => {
        let active = true;
        let requestSequence = 0;
        let inFlight: AbortController | null = null;
        const refresh = async () => {
            if (!active || !navigator.onLine || inFlight !== null) {
                online = navigator.onLine;
                if (!online) {
                    clock.markUncertain();
                    renderClock();
                }
                return;
            }

            const sequence = ++requestSequence;
            const requestStartedAt = performance.now();
            const controller = new AbortController();
            inFlight = controller;
            try {
                const response = await fetch(window.location.pathname, {
                    method: 'GET',
                    credentials: 'same-origin',
                    headers: {
                        Accept: 'application/json',
                        'X-Applicant-Snapshot': 'true',
                    },
                    cache: 'no-store',
                    signal: controller.signal,
                });
                if (!response.ok) throw new Error('Snapshot refresh failed');
                const payload = (await response.json()) as {
                    exam_day: ExamDay;
                };
                if (!active || sequence !== requestSequence) return;
                if (!navigator.onLine) {
                    online = false;
                    clock.markUncertain();
                    renderClock();
                    return;
                }
                snapshot = payload.exam_day;
                online = true;
                if (snapshot.session?.status === 'in_progress') {
                    clock.synchronize(
                        snapshot.server_now,
                        snapshot.session.effective_end_at,
                        requestStartedAt,
                    );
                } else if (
                    snapshot.session?.scheduled_starts_at &&
                    (snapshot.session.status === 'published' || snapshot.session.status === 'draft')
                ) {
                    clock.synchronize(
                        snapshot.server_now,
                        snapshot.session.scheduled_starts_at,
                        requestStartedAt,
                    );
                } else {
                    clock.markUncertain();
                }
                renderClock();
            } catch {
                if (
                    !controller.signal.aborted &&
                    sequence === requestSequence
                ) {
                    clock.markUncertain();
                    renderClock();
                }
            } finally {
                if (inFlight === controller) {
                    inFlight = null;
                }
            }
        };

        clock.markUncertain();
        const pollId = window.setInterval(refresh, 15_000);
        const clockIntervalId = window.setInterval(renderClock, 1_000);
        void refresh();
        const onFocus = () => {
            void refresh();
        };
        const onOnline = () => {
            online = true;
            void refresh();
        };
        const onOffline = () => {
            online = false;
            clock.markUncertain();
            renderClock();
        };
        window.addEventListener('focus', onFocus);
        window.addEventListener('online', onOnline);
        window.addEventListener('offline', onOffline);

        return () => {
            active = false;
            requestSequence++;
            inFlight?.abort();
            window.clearInterval(pollId);
            window.clearInterval(clockIntervalId);
            window.removeEventListener('focus', onFocus);
            window.removeEventListener('online', onOnline);
            window.removeEventListener('offline', onOffline);
        };
    });
</script>

<ApplicantLayout title="Exam Day" breadcrumbs={[{ label: 'Exam Day' }]}>
    <div class="mx-auto max-w-2xl space-y-5">
        {#if snapshot.page_state === 'denied'}
            <Card.Root class="">
                <Card.Content class="space-y-2 p-6">
                    <Card.Title class="text-xl">Exam Day unavailable</Card.Title
                    >
                    <Card.Description class=""
                        >This account is not linked to an applicant record.</Card.Description
                    >
                </Card.Content>
            </Card.Root>
        {:else if snapshot.page_state === 'empty'}
            <Card.Root class="">
                <Card.Content class="space-y-2 p-6">
                    <Card.Title class="text-xl"
                        >No exam session assigned</Card.Title
                    >
                    <Card.Description class=""
                        >Session information will appear here when it is
                        available for your application.</Card.Description
                    >
                </Card.Content>
            </Card.Root>
        {:else}
            <Card.Root class="overflow-hidden shadow-sm">
                <Card.Header class="border-b px-5 py-5 sm:px-6">
                    <Card.Title class="text-xl">Your Exam Day</Card.Title>
                    <Card.Description class=""
                        >Session and attendance facts for your application.</Card.Description
                    >
                </Card.Header>
                <Card.Content class="space-y-5 p-5 sm:p-6">
                    <section
                        aria-labelledby="exam-session-heading"
                        class="space-y-3"
                    >
                        <h2 id="exam-session-heading" class="font-semibold">
                            Exam session
                        </h2>
                        {#if snapshot.session}
                            <dl class="grid gap-3 sm:grid-cols-2">
                                <div class="rounded-md border p-3">
                                    <dt class="text-muted-foreground text-xs">
                                        Session
                                    </dt>
                                    <dd class="mt-1 font-medium">
                                        {snapshot.session.code}
                                    </dd>
                                </div>
                                <div class="rounded-md border p-3">
                                    <dt class="text-muted-foreground text-xs">
                                        Status
                                    </dt>
                                    <dd class="mt-1 font-medium">
                                        {sessionStatusLabel(
                                            snapshot.session.status,
                                        )}
                                    </dd>
                                </div>
                                {#each timeFacts as fact (fact.label)}
                                    <div class="rounded-md border p-3">
                                        <dt
                                            class="text-muted-foreground text-xs"
                                        >
                                            {fact.label}
                                        </dt>
                                        <dd class="mt-1 font-medium">
                                            {fact.value}
                                        </dd>
                                    </div>
                                {/each}
                            </dl>
                            {#if snapshot.session.status === 'published' || snapshot.session.status === 'draft'}
                                {#if clockState === 'pre_start'}
                                    <div class="border-primary/30 bg-primary/5 rounded-md border p-4 text-center">
                                        <span class="text-muted-foreground block text-xs uppercase tracking-wider font-semibold">Starts in</span>
                                        <span class="mt-1 block font-mono text-3xl font-semibold tabular-nums text-foreground">{countdown}</span>
                                        <p class="text-muted-foreground mt-1 text-xs">Scheduled start: {formatScheduledDate(snapshot.session.scheduled_date, snapshot.session.scheduled_start_time, snapshot.session.scheduled_starts_at)}</p>
                                    </div>
                                {:else if clockState === 'start_time_reached'}
                                    <div class="rounded-md border border-amber-500/30 bg-amber-500/5 p-4 text-center">
                                        <span class="text-amber-800 dark:text-amber-200 font-medium text-sm">Scheduled start time reached</span>
                                        <p class="text-muted-foreground mt-0.5 text-xs">Awaiting room start by staff. Refresh shortly to see active testing.</p>
                                    </div>
                                {/if}
                            {:else if snapshot.session.status === 'in_progress'}
                                {#if clockState === 'live'}
                                    <div class="border-primary/30 bg-primary/5 rounded-md border p-4 text-center">
                                        <span class="text-muted-foreground block text-xs uppercase tracking-wider font-semibold">Time remaining</span>
                                        <span class="mt-1 block font-mono text-3xl font-semibold tabular-nums text-foreground">{countdown}</span>
                                    </div>
                                {:else if clockState === 'ended'}
                                    <p
                                        class="text-muted-foreground rounded-md border p-4 text-sm"
                                    >
                                        The expected end time has passed, but
                                        the session is still marked in progress.
                                        Staff will update its status.
                                    </p>
                                {:else}
                                    <p
                                        role="status"
                                        class="rounded-md border border-amber-500/30 bg-amber-500/5 p-4 text-sm"
                                    >
                                        Session timing is uncertain. Reconnect
                                        or refresh to update the expected end
                                        time.
                                    </p>
                                {/if}
                            {:else if snapshot.session.status === 'completed'}
                                <p class="text-muted-foreground text-sm">
                                    The exam session is complete.
                                </p>
                            {:else if snapshot.session.status === 'cancelled'}
                                <p class="text-muted-foreground text-sm">
                                    This exam session was cancelled.
                                </p>
                            {/if}
                        {:else}
                            <p
                                class="text-muted-foreground rounded-md border p-4 text-sm"
                            >
                                No session details are available yet.
                            </p>
                        {/if}
                    </section>

                    <section
                        aria-labelledby="attendance-heading"
                        class="space-y-3 border-t pt-5"
                    >
                        <h2 id="attendance-heading" class="font-semibold">
                            Attendance
                        </h2>
                        {#if snapshot.attendance}
                            <div class="rounded-md border p-4">
                                <p class="font-medium capitalize">
                                    {snapshot.attendance.outcome ?? 'Recorded'}
                                </p>
                                <p class="text-muted-foreground mt-1 text-sm">
                                    Recorded {formatDate(
                                        snapshot.attendance.recorded_at,
                                    )}
                                </p>
                                {#if snapshot.attendance.recorder}
                                    <p
                                        class="text-muted-foreground mt-1 text-sm"
                                    >
                                        Recorded by {snapshot.attendance
                                            .recorder}
                                    </p>
                                {/if}
                            </div>
                        {:else}
                            <p
                                class="text-muted-foreground rounded-md border p-4 text-sm"
                            >
                                Attendance has not been recorded.
                            </p>
                        {/if}
                    </section>

                    <section class="space-y-3 border-t pt-5">
                        <h2 class="font-semibold">
                            Need help during the exam?
                        </h2>
                        <p class="text-muted-foreground text-sm">
                            For an urgent issue, speak directly with your
                            proctor.
                        </p>
                        <Link
                            href={snapshot.help_href}
                            class="hover:bg-accent inline-flex min-h-11 items-center justify-center rounded-md border px-4 py-2 text-sm font-medium"
                            >Message staff about exam timing</Link
                        >
                    </section>
                    {#if !online}
                        <p
                            role="status"
                            class="text-sm text-amber-700 dark:text-amber-300"
                        >
                            Offline — session details may be out of date.
                        </p>
                    {/if}
                </Card.Content>
            </Card.Root>
        {/if}
    </div>
</ApplicantLayout>
