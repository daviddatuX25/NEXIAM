<script>
    /**
     * Academic Advising Operational Hub (Domain: Consultation & Academic Advising)
     *
     * Architectural Role:
     * Post-examination guidance and academic advisory workspace:
     * 1. Live Advisory Queue & Hourly Timeline: Tracks scheduled sessions, examinees awaiting advice,
     *    and completed advisory endorsements grouped by hour/batch or session.
     * 2. Direct Walk-In Ingestion: Immediate examinee lookup and QR scanner shortcut
     *    to launch the candidate's admission support advisory profile.
     * 3. Decision Handoff: Advisers record official recommendation decisions,
     *    advancing the examinee to the final Registrar Enrollment Eligibility review.
     * 4. Multi-Exam Batch Scale: High-density batch roster supporting 40+ applicants per hour
     *    with instant in-batch search, status filtering, and clean pagination.
     *
     * Persona & Guidance:
     * - Uses the 'staff-copilot' persona (Xiam) to guide advisers through
     *   daily session rosters, examinee lookups, and recommendation handoffs.
     */
    /* global route */
    import StaffLayout from '@/Pages/Staff/StaffLayout.svelte';
    import PageHeaderBar from '@/Components/kit/PageHeaderBar.svelte';
    import SortDropdown from '@/Components/kit/SortDropdown.svelte';
    import E2PageState from '@/Pages/Admission/E2PageState.svelte';
    import { Button } from '@/Components/ui/button';
    import { Badge } from '@/Components/ui/badge';
    import * as Card from '@/Components/ui/card';
    import * as Dialog from '@/Components/ui/dialog';
    import { Input } from '@/Components/ui/input';
    import { Link, router } from '@inertiajs/svelte';
    import {
        Calendar,
        CalendarCheck,
        QrCode,
        Search,
        UserCheck,
        Clock3,
        UserPlus,
        X,
        ArrowRight,
        Layers,
        Building2,
        CheckCircle2,
        ChevronLeft,
        ChevronRight,
        ChevronDown,
        Play,
        Activity,
        Sparkles,
        Settings2,
    } from 'lucide-svelte';
    import { openScanModal } from '@/lib/scan-modal';
    import ConsultationMobileDock from '@/Components/consultation/ConsultationMobileDock.svelte';
    import ApplicantLookupDialog from '@/Components/consultation/ApplicantLookupDialog.svelte';
    import PrintDocumentsSlot from '@/Components/DocumentTemplate/PrintDocumentsSlot.svelte';
    import { formatDateTime, formatTime } from '@/lib/date-utils.js';

    let { page_state = 'ready', dashboard = null, can_plan = false, settings = { policy_url: null, admission_support_url: null } } = $props();

    const sessionsUrl =
        typeof route === 'function' ? route('consultation.sessions.index') : '/consultation/sessions';

    // Walk-in / Direct Lookup Dialog State
    let applicantLookupOpen = $state(false);
    let lookupQuery = $state('');
    let lookupResults = $state([]);
    let lookupLoading = $state(false);
    let searchInputEl = $state(null);

    // Timeline & Hourly Batch View State
    let selectedHour = $state('all'); // 'all' or formatted hour (e.g. '08:00 AM')
    let batchSearchQuery = $state('');
    let batchStatusFilter = $state('all'); // 'all' | 'waiting' | 'in_progress' | 'advised' | 'referred'
    let batchPage = $state(1);
    const batchPageSize = 10;

    // Mobile destinations: Today's board or the applicant sidebar.
    let mobileViewTab = $state('today');

    let activeWorkspaceTab = $state('scheduled');
    let lastPendingWorkspaceTab = $state('scheduled');
    let sidebarHidden = $state(false);
    let workspaceStates = $state({
        scheduled: { search: '', page: 1, sort: 'time', dir: 'asc', printFilter: 'all' },
        unscheduled: { search: '', page: 1, sort: 'name', dir: 'asc', printFilter: 'all' },
        released: { search: '', page: 1, sort: 'name', dir: 'asc', printFilter: 'all' },
        followups: { search: '', page: 1, sort: 'name', dir: 'asc', printFilter: 'all' },
    });
    const workspacePageSize = 8;
    const applicantSortOptions = [
        { value: 'name', label: 'Name' },
        { value: 'reference', label: 'Applicant Reference' },
    ];
    const sessionSortOptions = [
        { value: 'time', label: 'Schedule Time' },
        { value: 'code', label: 'Session Code' },
        { value: 'assigned', label: 'Applicants per session' },
    ];
    const workspaceSortOptions = $derived(
        activeWorkspaceTab === 'scheduled' ? sessionSortOptions : applicantSortOptions,
    );
    const queueSortOptions = [
        { value: 'time', label: 'Schedule Time' },
        { value: 'name', label: 'Name' },
        { value: 'reference', label: 'Applicant Reference' },
        { value: 'program', label: 'Program' },
    ];


    let batchSortKey = $state('time');
    let batchSortDir = $state('asc');


    let openSessionDialogOpen = $state(false);
    let sessionToOpen = $state(null);
    let openSessionBusy = $state(false);

    function sortQueueApplicants(items, sortKey, sortDir = 'asc') {
        const dir = sortDir === 'desc' ? -1 : 1;
        return [...items].sort((a, b) => {
            let valA = '';
            let valB = '';
            if (sortKey === 'time') {
                valA = a.scheduled_at || a.time || a.created_at || '';
                valB = b.scheduled_at || b.time || b.created_at || '';
            } else if (sortKey === 'reference') {
                valA = a.reference_code || '';
                valB = b.reference_code || '';
            } else if (sortKey === 'program') {
                valA = a.program || a.preferred_program || a.program_code || a.room || '';
                valB = b.program || b.preferred_program || b.program_code || b.room || '';
            } else {
                valA = a.name || '';
                valB = b.name || '';
            }
            return dir * String(valA).localeCompare(String(valB), undefined, { numeric: true, sensitivity: 'base' });
        });
    }

    const activeWorkspacePreferences = $derived(workspaceStates[activeWorkspaceTab]);
    const activeWorkspaceRows = $derived.by(() => {
        if (activeWorkspaceTab === 'scheduled') return [];
        if (activeWorkspaceTab === 'released') return dashboard?.released_applicants ?? [];
        if (activeWorkspaceTab === 'followups') return dashboard?.followup_applicants ?? [];
        return dashboard?.unscheduled_applicants ?? [];
    });
    const activeWorkspacePage = $derived.by(() => {
        const preferences = activeWorkspacePreferences;
        const query = preferences.search.trim().toLocaleLowerCase();
        const filteredByPrint = activeWorkspaceRows.filter((row) => {
            if (preferences.printFilter === 'not_prepared') return row.document_status === 'not_prepared';
            if (preferences.printFilter === 'not_printed') return row.print_status === 'not_printed';
            if (preferences.printFilter === 'printed') return row.print_status === 'printed';
            return true;
        });
        const filtered = filteredByPrint.filter((row) =>
            !query || String(row.name ?? '').toLocaleLowerCase().includes(query) || String(row.reference_code ?? '').toLocaleLowerCase().includes(query)
        );
        const sorted = sortQueueApplicants(filtered, preferences.sort, preferences.dir);
        const pageCount = Math.max(1, Math.ceil(sorted.length / workspacePageSize));
        const currentPage = Math.min(preferences.page, pageCount);
        return { items: sorted.slice((currentPage - 1) * workspacePageSize, currentPage * workspacePageSize), total: sorted.length, pageCount, currentPage };
    });

    function openMetric(tab) {
        const targetTab = tab === 'pending' ? lastPendingWorkspaceTab : tab;
        workspaceStates[targetTab].search = '';
        workspaceStates[targetTab].page = 1;
        workspaceStates[targetTab].printFilter = 'all';
        activeWorkspaceTab = targetTab;
        sidebarHidden = false;
        mobileViewTab = 'applicants';
    }

    function selectWorkspaceTab(tab) {
        if (tab === 'scheduled' || tab === 'unscheduled') {
            lastPendingWorkspaceTab = tab;
        }
        activeWorkspaceTab = tab;
        mobileViewTab = 'applicants';
    }

    function handleWorkspaceTabKeydown(event) {
        const tabs = Array.from(event.currentTarget.querySelectorAll('[role="tab"]'));
        const current = tabs.indexOf(event.target);
        const nextIndex = event.key === 'ArrowRight'
            ? (current + 1) % tabs.length
            : event.key === 'ArrowLeft'
                ? (current - 1 + tabs.length) % tabs.length
                : event.key === 'Home'
                    ? 0
                    : event.key === 'End'
                        ? tabs.length - 1
                        : -1;
        if (nextIndex >= 0) {
            event.preventDefault();
            tabs[nextIndex]?.focus();
            tabs[nextIndex]?.click();
        }
    }

    const scheduledGroupsPage = $derived.by(() => {
        const preferences = workspaceStates.scheduled;
        const query = preferences.search.trim().toLocaleLowerCase();
        const pendingApplicantsById = new Map(
            (dashboard?.scheduled_applicants ?? []).map((applicant) => [applicant.attempt_id, applicant]),
        );
        const sessions = [
            ...(dashboard?.today_schedule ?? [])
                .filter((session) => ['published', 'in_progress'].includes(session.status))
                .map((session) => ({ ...session, is_future_published: false })),
            ...(dashboard?.other_sessions?.items ?? []).map((session) => ({ ...session, is_future_published: session.status === 'published' })),
        ];
        const groups = sessions
            .map((session) => {
                const sessionMatches = !query || [
                    session.code,
                    session.room,
                    formatDate(session.scheduled_at),
                ].some((value) => String(value ?? '').toLocaleLowerCase().includes(query));
                const visibleApplicants = (session.applicants ?? [])
                    .map((sessionApplicant) => {
                        const applicant = pendingApplicantsById.get(sessionApplicant.attempt_id);
                        if (!applicant) return null;
                        const printMatched = preferences.printFilter === 'not_prepared'
                            ? applicant.document_status === 'not_prepared'
                            : preferences.printFilter === 'not_printed'
                                ? applicant.print_status === 'not_printed'
                                : preferences.printFilter === 'printed'
                                    ? applicant.print_status === 'printed'
                                    : true;
                        const applicantMatches = !query
                            || String(applicant.name ?? '').toLocaleLowerCase().includes(query)
                            || String(applicant.reference_code ?? '').toLocaleLowerCase().includes(query);
                        if (!printMatched || (!sessionMatches && !applicantMatches)) return null;
                        return { ...applicant, url: sessionApplicant.url ?? applicant.url };
                    })
                    .filter(Boolean);
                if (visibleApplicants.length === 0) return null;
                const href = session.href ?? `/consultation/sessions/${session.id}`;
                return {
                    ...session,
                    href,
                    visibleApplicants,
                    assigned_count: visibleApplicants.length,
                };
            })
            .filter(Boolean);
        const direction = preferences.dir === 'desc' ? -1 : 1;
        const sorted = [...groups].sort((left, right) => {
            if (preferences.sort === 'assigned') {
                return direction * (Number(left.assigned_count ?? 0) - Number(right.assigned_count ?? 0));
            }
            if (preferences.sort === 'code') {
                return direction * String(left.code ?? '').localeCompare(String(right.code ?? ''), undefined, { numeric: true, sensitivity: 'base' });
            }
            const leftValue = preferences.sort === 'name'
                ? left.visibleApplicants[0]?.name ?? ''
                : preferences.sort === 'reference'
                    ? left.visibleApplicants[0]?.reference_code ?? ''
                    : preferences.sort === 'program'
                        ? left.visibleApplicants[0]?.program ?? left.room ?? ''
                        : left.scheduled_at ?? '';
            const rightValue = preferences.sort === 'name'
                ? right.visibleApplicants[0]?.name ?? ''
                : preferences.sort === 'reference'
                    ? right.visibleApplicants[0]?.reference_code ?? ''
                    : preferences.sort === 'program'
                        ? right.visibleApplicants[0]?.program ?? right.room ?? ''
                        : right.scheduled_at ?? '';
            return direction * String(leftValue).localeCompare(String(rightValue), undefined, { numeric: true, sensitivity: 'base' });
        });
        const pageCount = Math.max(1, Math.ceil(sorted.length / workspacePageSize));
        const currentPage = Math.min(preferences.page, pageCount);
        return {
            items: sorted.slice((currentPage - 1) * workspacePageSize, currentPage * workspacePageSize),
            currentPage,
            pageCount,
            total: sorted.length,
        };
    });

    function promptOpenSessionForToday(session) {
        sessionToOpen = session;
        openSessionDialogOpen = true;
    }

    function confirmOpenSessionForToday() {
        if (!sessionToOpen || openSessionBusy) return;
        openSessionBusy = true;
        router.post(
            `/consultation/sessions/${sessionToOpen.id}/open`,
            { expected_version: sessionToOpen.version ?? 1 },
            {
                preserveScroll: true,
                onSuccess: () => {
                    openSessionDialogOpen = false;
                    sessionToOpen = null;
                },
                onFinish: () => {
                    openSessionBusy = false;
                },
            },
        );
    }
    function consultationStatus(applicant) {
        const outcome = String(applicant?.outcome ?? '').toLowerCase();
        const label = String(applicant?.status_label ?? '').toLowerCase();
        if (outcome === 'advised' || label === 'advised' || applicant?.advised) return 'Advised';
        if (outcome === 'deferred' || label === 'deferred') return 'Deferred';
        if (outcome === 'referred' || outcome === 'referred to department' || label === 'referred to department') return 'Referred to Department';
        if (label === 'in progress' || outcome === 'in progress') return 'In Progress';
        return 'Scheduled';
    }

    function consultationStatusVariant(status) {
        if (status === 'Advised') return 'default';
        if (status === 'Referred to Department') return 'outline';
        return 'secondary';
    }

    function consultationStatusClass(status) {
        if (status === 'Advised') return 'shrink-0 border-emerald-600/30 bg-emerald-500/10 text-emerald-700 dark:text-emerald-400 font-semibold';
        if (status === 'Deferred') return 'shrink-0 border-amber-300 bg-amber-100 text-amber-950 dark:bg-amber-950/40 dark:text-amber-300 font-semibold';
        if (status === 'Referred to Department') return 'shrink-0 border-blue-300 bg-blue-50 text-blue-800 dark:bg-blue-950/40 dark:text-blue-300 font-semibold';
        if (status === 'In Progress') return 'shrink-0 border-purple-300 bg-purple-50 text-purple-800 dark:bg-purple-950/40 dark:text-purple-300 font-semibold';
        return 'shrink-0 border-border bg-muted/60 text-muted-foreground font-medium';
    }


    function getSessionRangeLabel(session) {
        if (session?.time_range) return session.time_range;
        let h = NaN;
        let m = 0;
        if (session?.time && session.time.includes(':')) {
            const [hStr, mStr = '00'] = session.time.split(':');
            h = parseInt(hStr, 10);
            m = parseInt(mStr, 10) || 0;
        } else if (session?.scheduled_at) {
            const d = new Date(session.scheduled_at);
            h = d.getHours();
            m = d.getMinutes();
        }
        if (!isNaN(h)) {
            const startMins = h * 60 + m;
            const endMins = startMins + 90;
            const fmt = (mins) => {
                const hh24 = Math.floor(mins / 60) % 24;
                const mm = mins % 60;
                const period = hh24 >= 12 ? 'PM' : 'AM';
                const hh12 = hh24 % 12 === 0 ? 12 : hh24 % 12;
                return `${String(hh12).padStart(2, '0')}:${String(mm).padStart(2, '0')} ${period}`;
            };
            return `${fmt(startMins)} – ${fmt(endMins)}`;
        }
        return 'Flexible / Unspecified';
    }

    // Range-Based Schedule & Timeline Aggregator
    const hourlySchedule = $derived.by(() => {
        const schedule = dashboard?.today_schedule ?? [];
        if (!schedule.length) return [];

        const buckets = new Map();

        for (const session of schedule) {
            const rangeLabel = getSessionRangeLabel(session);
            const hourKey = session.is_opened_early ? `${session.code} · ${rangeLabel}` : rangeLabel;
            let hourNum = 99;

            if (session.time && session.time.includes(':')) {
                const [hStr] = session.time.split(':');
                const h = parseInt(hStr, 10);
                if (!isNaN(h)) {
                    hourNum = h;
                }
            } else if (session.scheduled_at) {
                const date = new Date(session.scheduled_at);
                hourNum = date.getHours();
            }

            if (!buckets.has(hourKey)) {
                buckets.set(hourKey, {
                    hourKey,
                    rangeLabel,
                    hourNum,
                    isOpenedEarly: Boolean(session.is_opened_early),
                    sessions: [],
                    allApplicants: [],
                    totalApplicants: 0,
                    advisedCount: 0,
                    deferredCount: 0,
                    referredCount: 0,
                    inProgressCount: 0,
                    waitingCount: 0,
                    rooms: new Set(),
                });
            }

            const bucket = buckets.get(hourKey);
            bucket.sessions.push(session);
            if (session.room) bucket.rooms.add(session.room);

            const sessionApplicants = session.applicants ?? [];
            for (const app of sessionApplicants) {
                const item = {
                    ...app,
                    session_id: session.id,
                    session_code: session.code,
                    room: session.room,
                    session_time: session.time,
                    session_time_range: rangeLabel,
                    is_opened_early: Boolean(session.is_opened_early),
                };
                bucket.allApplicants.push(item);
                const st = consultationStatus(app);
                if (st === 'Advised') bucket.advisedCount++;
                else if (st === 'Deferred') bucket.deferredCount++;
                else if (st === 'Referred to Department') bucket.referredCount++;
                else if (st === 'In Progress') bucket.inProgressCount++;
                else bucket.waitingCount++;
            }
            bucket.totalApplicants = bucket.allApplicants.length;
        }

        const currentHourNum = new Date().getHours();
        const sorted = Array.from(buckets.values()).sort((a, b) => a.hourNum - b.hourNum);
        return sorted.map((b) => ({
            ...b,
            roomsList: Array.from(b.rooms).join(', ') || 'General Desk',
            completionRate: b.totalApplicants > 0 ? Math.round((b.advisedCount / b.totalApplicants) * 100) : 0,
            isCurrentHour: currentHourNum === b.hourNum,
        }));
    });
    const activeHourData = $derived.by(() => {
        if (!hourlySchedule.length) return null;
        if (selectedHour === 'all') {
            const allApps = hourlySchedule.flatMap((b) => b.allApplicants);
            const total = allApps.length;
            const advised = hourlySchedule.reduce((acc, b) => acc + b.advisedCount, 0);
            const inProg = hourlySchedule.reduce((acc, b) => acc + b.inProgressCount, 0);
            const waiting = hourlySchedule.reduce((acc, b) => acc + b.waitingCount, 0);
            const referred = hourlySchedule.reduce((acc, b) => acc + (b.deferredCount + b.referredCount), 0);
            const rooms = Array.from(new Set(hourlySchedule.flatMap((b) => Array.from(b.rooms)))).join(', ');
            return {
                title: "Today's Complete Schedule",
                subtitle: `${dashboard?.today_schedule?.length ?? 0} sessions across ${rooms || 'all Release Day rooms'}`,
                totalApplicants: total,
                advisedCount: advised,
                inProgressCount: inProg,
                waitingCount: waiting,
                referredCount: referred,
                completionRate: total > 0 ? Math.round((advised / total) * 100) : 0,
                allApplicants: allApps,
                sessions: dashboard?.today_schedule ?? [],
            };
        }

        const bucket = hourlySchedule.find((b) => b.hourKey === selectedHour);
        if (!bucket) return null;

        return {
            title: `${bucket.hourKey} Release Day batch`,
            subtitle: `${bucket.sessions.length} ${bucket.sessions.length === 1 ? 'session' : 'sessions'} in ${bucket.roomsList}`,
            totalApplicants: bucket.totalApplicants,
            advisedCount: bucket.advisedCount,
            inProgressCount: bucket.inProgressCount,
            waitingCount: bucket.waitingCount,
            referredCount: bucket.deferredCount + bucket.referredCount,
            completionRate: bucket.completionRate,
            allApplicants: bucket.allApplicants,
            sessions: bucket.sessions,
        };
    });

    const filteredBatchApplicants = $derived.by(() => {
        const pool = activeHourData?.allApplicants ?? [];
        const query = batchSearchQuery.trim().toLowerCase();
        const status = batchStatusFilter;

        const filtered = pool.filter((applicant) => {
            const st = consultationStatus(applicant);

            if (status === 'waiting' && st !== 'Scheduled') return false;
            if (status === 'in_progress' && st !== 'In Progress') return false;
            if (status === 'advised' && st !== 'Advised') return false;
            if (status === 'referred' && st !== 'Referred to Department' && st !== 'Deferred') return false;

            if (query) {
                const name = String(applicant.name ?? '').toLowerCase();
                const code = String(applicant.reference_code ?? '').toLowerCase();
                const room = String(applicant.room ?? '').toLowerCase();
                if (!name.includes(query) && !code.includes(query) && !room.includes(query)) {
                    return false;
                }
            }

            return true;
        });

        return sortQueueApplicants(filtered, batchSortKey, batchSortDir);
    });

    const batchPageCount = $derived(Math.max(1, Math.ceil(filteredBatchApplicants.length / batchPageSize)));
    const currentBatchPage = $derived(Math.min(batchPage, batchPageCount));
    const pagedBatchApplicants = $derived(
        filteredBatchApplicants.slice((currentBatchPage - 1) * batchPageSize, currentBatchPage * batchPageSize),
    );

    const consultationContext = $derived.by(() => {
        const pendingReleaseCount = dashboard?.stats?.pending_release ?? 0;
        const scheduledCount = dashboard?.scheduled_applicants?.length ?? 0;
        const unscheduledCount = dashboard?.unscheduled_applicants?.length ?? 0;
        const releasedCount = dashboard?.stats?.released ?? 0;
        const followUpsCount = dashboard?.stats?.followups ?? 0;
        const todayScheduledCount = dashboard?.stats?.today_scheduled ?? 0;
        const todaySessionsCount = dashboard?.today_schedule?.length ?? 0;
        const safetyKey = `${page_state}:${pendingReleaseCount}:${scheduledCount}:${unscheduledCount}:${releasedCount}:${followUpsCount}:${todaySessionsCount}`;
        return {
            entityKey: 'consultation-hub',
            safetyKey,
            freshness: 'current',
            pageState: page_state,
            pendingReleaseCount,
            scheduledCount,
            unscheduledCount,
            releasedCount,
            followUpsCount,
            todayScheduledCount,
            todaySessionsCount,
            canPlan: Boolean(can_plan),
        };
    });

    // Fast In-Page Search Debounce
    $effect(() => {
        const query = lookupQuery.trim();
        lookupResults = [];
        if (query.length < 2) {
            lookupResults = [];
            lookupLoading = false;
            return;
        }

        lookupLoading = true;
        const controller = new AbortController();
        const timeout = setTimeout(async () => {
            try {
                const response = await fetch(`/consultation/lookup?q=${encodeURIComponent(query)}`, {
                    headers: { Accept: 'application/json' },
                    signal: controller.signal,
                });
                if (!response.ok) throw new Error('Applicant lookup failed.');
                const data = await response.json();
                lookupResults = Array.isArray(data.results) ? data.results : [];
            } catch (error) {
                if (error.name !== 'AbortError') lookupResults = [];
            } finally {
                if (!controller.signal.aborted) lookupLoading = false;
            }
        }, 250);

        return () => {
            clearTimeout(timeout);
            controller.abort();
        };
    });


    function openApplicant(attemptId) {
        router.visit(`/consultation/applicants/${attemptId}`);
    }

    function startDirectConsultation() {
        if (!lookupLoading && lookupResults.length === 1 && lookupResults[0].can_consult) {
            router.visit(lookupResults[0].url);
        }
    }

    function formatDate(iso) {
        if (!iso) return null;
        return new Date(iso).toLocaleDateString(undefined, { month: 'short', day: 'numeric', year: 'numeric' });
    }
</script>

<svelte:head><title>Release Day · NEXIAM</title></svelte:head>

<StaffLayout breadcrumbs={[{ label: 'Release of Results' }, { label: 'Release Day' }]}>
    <div class="min-w-0 space-y-6 p-4 pb-[calc(7rem+env(safe-area-inset-bottom))] sm:p-6 sm:pb-[calc(7rem+env(safe-area-inset-bottom))] lg:pb-6">
        <PageHeaderBar
            title="Release Day"
            tourKey="consultation-hub"
            context={consultationContext}
        >
            {#snippet actions()}
                <div class="flex flex-wrap items-center gap-2">
                    <PrintDocumentsSlot slotKey="guidance_applicant_support" />
                    {#if page_state === 'ready' && dashboard}
                        <Button
                            variant="default"
                            class="min-h-10 gap-2 font-semibold shadow-sm"
                            data-tour="direct-consult-button"
                            onclick={() => (applicantLookupOpen = true)}
                        >
                            <UserPlus class="size-4" />
                            <span>Quick Lookup &amp; Scanner</span>
                        </Button>
                    {/if}
                    {#if can_plan}
                        <Link href={sessionsUrl}>
                            <Button variant="outline" class="min-h-10 gap-2 font-semibold">
                                <Calendar class="size-4" />
                                <span>Manage Release Days</span>
                            </Button>
                        </Link>
                    {/if}
                    {#if page_state === 'ready' && (settings?.policy_url || settings?.admission_support_url)}
                        <details class="relative">
                            <summary class="flex size-11 cursor-pointer list-none items-center justify-center rounded-md border bg-background text-foreground hover:bg-muted focus-visible:outline focus-visible:outline-2 focus-visible:outline-ring" aria-label="Release of Results settings" title="Release of Results settings">
                                <Settings2 class="size-4" aria-hidden="true" />
                            </summary>
                            <div class="absolute right-0 z-30 mt-2 w-64 rounded-lg border bg-background p-3 shadow-lg" aria-label="Release of Results settings destinations">
                                <p class="font-medium">Release of Results settings</p>
                                <p class="mt-1 text-sm text-muted-foreground">Choose a configuration area you can access. These are not global preferences.</p>
                                <div class="mt-3 grid gap-1">
                                    {#if settings.policy_url}
                                        <Link href={settings.policy_url} class="flex min-h-11 items-center rounded-md px-2 text-sm hover:bg-muted focus-visible:outline focus-visible:outline-2 focus-visible:outline-ring">Policy settings</Link>
                                    {/if}
                                    {#if settings.admission_support_url}
                                        <Link href={settings.admission_support_url} class="flex min-h-11 items-center rounded-md px-2 text-sm hover:bg-muted focus-visible:outline focus-visible:outline-2 focus-visible:outline-ring">Decision Support</Link>
                                    {/if}
                                </div>
                            </div>
                        </details>
                    {/if}
                </div>
            {/snippet}
        </PageHeaderBar>

        <E2PageState state={page_state} />

        {#if page_state === 'ready' && dashboard}
            <section class="space-y-4" data-tour="consultation-metrics-grid" aria-label="Release Day applicant summary">
                <div class="grid grid-cols-1 gap-2 sm:grid-cols-3">
                    <button type="button" aria-label={`Pending Release ${dashboard.stats.pending_release ?? 0} applicants`} class="min-h-20 rounded-lg border border-border bg-card px-4 py-3 text-left hover:bg-muted/40 focus-visible:outline focus-visible:outline-2 focus-visible:outline-ring" onclick={() => openMetric('pending')}>
                        <span class="text-xs font-semibold text-muted-foreground">Pending Release</span>
                        <span class="mt-1 block font-mono text-2xl font-bold tabular-nums">{dashboard.stats.pending_release ?? 0}</span>
                    </button>
                    <button type="button" aria-label={`Released ${dashboard.stats.released ?? 0} applicants`} class="min-h-20 rounded-lg border border-border bg-card px-4 py-3 text-left hover:bg-muted/40 focus-visible:outline focus-visible:outline-2 focus-visible:outline-ring" onclick={() => openMetric('released')}>
                        <span class="text-xs font-semibold text-muted-foreground">Released</span>
                        <span class="mt-1 block font-mono text-2xl font-bold tabular-nums">{dashboard.stats.released ?? 0}</span>
                    </button>
                    <button type="button" aria-label={`For Follow-up ${dashboard.stats.followups ?? 0} applicants`} class="min-h-20 rounded-lg border border-border bg-card px-4 py-3 text-left hover:bg-muted/40 focus-visible:outline focus-visible:outline-2 focus-visible:outline-ring" onclick={() => openMetric('followups')}>
                        <span class="text-xs font-semibold text-muted-foreground">For Follow-up</span>
                        <span class="mt-1 block font-mono text-2xl font-bold tabular-nums">{dashboard.stats.followups ?? 0}</span>
                    </button>
                </div>
                <div class="flex justify-end">
                    <Button type="button" variant="outline" size="sm" class="min-h-9" onclick={() => (sidebarHidden = !sidebarHidden)} aria-expanded={!sidebarHidden}>
                        {sidebarHidden ? 'Show sidebar' : 'Hide sidebar'}
                    </Button>
                </div>
            </section>

            <!-- Narrow-screen destination switch: Today or Applicants. -->
            <div class="flex gap-1 rounded-lg border bg-muted/40 p-1 lg:hidden" role="group" aria-label="Release Day workspace">
                <button
                    type="button"
                    class="min-h-10 flex-1 rounded-md px-3 text-center text-xs font-semibold {mobileViewTab === 'today' ? 'bg-background text-foreground shadow-xs' : 'text-muted-foreground hover:text-foreground'}"
                    aria-pressed={mobileViewTab === 'today'}
                    onclick={() => (mobileViewTab = 'today')}
                >
                    Today
                </button>
                <button
                    type="button"
                    class="min-h-10 flex-1 rounded-md px-3 text-center text-xs font-semibold {mobileViewTab === 'applicants' ? 'bg-background text-foreground shadow-xs' : 'text-muted-foreground hover:text-foreground'}"
                    aria-pressed={mobileViewTab === 'applicants'}
                    onclick={() => selectWorkspaceTab(activeWorkspaceTab)}
                >
                    Applicants
                </button>
            </div>

            <!-- MAIN OPERATIONAL WORKSTATION (Asymmetric 12-Column Grid on Desktop) -->
            <div class="grid grid-cols-1 gap-6 lg:grid-cols-12">
                <!-- ==================================================================== -->
                <!-- LEFT COLUMN: SCHEDULE TIMELINE & HIGH-DENSITY BATCH ROSTER (8 Cols) -->
                <!-- ==================================================================== -->
                <div class="space-y-6 {sidebarHidden ? 'lg:col-span-12' : 'lg:col-span-7'} {mobileViewTab !== 'today' ? 'hidden lg:block' : ''}">
                    <section class="overflow-hidden">
                        <div class="border-b border-border/40 pb-4">
                            <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                                <div>
                                    <Card.Title class="flex items-center gap-2 text-base font-bold text-foreground">
                                        <Clock3 class="size-4 text-primary" />
                                        <span>Today's Release Day Schedule &amp; Hourly Breakdown</span>
                                    </Card.Title>
                                    <Card.Description class="text-xs">
                                        {formatDate(new Date().toISOString())} — Filter by hour/batch or examine the combined room queues.
                                    </Card.Description>
                                </div>
                                {#if hourlySchedule.length}
                                    <Badge variant="outline" class="font-mono text-xs self-start sm:self-center">
                                        {hourlySchedule.length} Hourly {hourlySchedule.length === 1 ? 'Slot' : 'Slots'}
                                    </Badge>
                                {/if}
                            </div>

                            <!-- HOURLY TIMELINE SELECTOR (Horizontal Pill Rail) -->
                            {#if hourlySchedule.length}
                                <div class="mt-4 flex items-center gap-2 overflow-x-auto pb-1 pt-1 scrollbar-none" role="tablist" aria-label="Hourly schedule slots">
                                    <!-- ALL DAY PILL -->
                                    <button
                                        type="button"
                                        role="tab"
                                        aria-selected={selectedHour === 'all'}
                                        class="flex shrink-0 items-center gap-2 rounded-lg border px-3 py-1.5 text-xs font-medium transition-all {selectedHour === 'all' ? 'border-primary bg-primary text-primary-foreground shadow-xs font-semibold' : 'border-border/80 bg-background text-muted-foreground hover:bg-muted/60 hover:text-foreground'}"
                                        onclick={() => { selectedHour = 'all'; batchPage = 1; }}
                                    >
                                        <Layers class="size-3.5" />
                                        <span>All Day</span>
                                        <Badge variant={selectedHour === 'all' ? 'secondary' : 'outline'} class="ml-0.5 px-1.5 py-0 text-[10px] font-mono">
                                            {dashboard.stats.today_scheduled}
                                        </Badge>
                                    </button>

                                    <!-- INDIVIDUAL HOUR PILLS -->
                                    {#each hourlySchedule as hour (hour.hourKey)}
                                        <button
                                            type="button"
                                            role="tab"
                                            aria-selected={selectedHour === hour.hourKey}
                                            class="flex shrink-0 items-center gap-2 rounded-lg border px-3 py-1.5 text-xs font-medium transition-all {selectedHour === hour.hourKey ? 'border-primary bg-primary text-primary-foreground shadow-xs font-semibold' : 'border-border/80 bg-background text-muted-foreground hover:bg-muted/60 hover:text-foreground'}"
                                            onclick={() => { selectedHour = hour.hourKey; batchPage = 1; }}
                                        >
                                            {#if hour.isCurrentHour}
                                                <span class="relative flex h-2 w-2">
                                                    <span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-emerald-400 opacity-75"></span>
                                                    <span class="relative inline-flex rounded-full h-2 w-2 bg-emerald-500"></span>
                                                </span>
                                            {/if}
                                            <span>{hour.hourKey}</span>
                                            <span class="rounded-full px-1.5 py-0.2 text-[10px] font-mono font-bold {selectedHour === hour.hourKey ? 'bg-primary-foreground/20 text-primary-foreground' : 'bg-muted text-muted-foreground'}">
                                                {hour.totalApplicants}
                                            </span>
                                            {#if hour.completionRate === 100 && hour.totalApplicants > 0}
                                                <CheckCircle2 class="size-3 text-emerald-400" />
                                            {/if}
                                        </button>
                                    {/each}
                                </div>
                            {/if}
                        </div>

                        <div class="space-y-4 pt-4">
                            {#if activeHourData && activeHourData.totalApplicants > 0}
                                <!-- ACTIVE BATCH SUMMARY & PROGRESS HEADER -->
                                <div class="rounded-xl border border-border/60 bg-muted/20 p-4">
                                    <div class="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
                                        <div>
                                            <h4 class="text-sm font-bold text-foreground flex items-center gap-2">
                                                <span>{activeHourData.title}</span>
                                                {#if selectedHour !== 'all'}
                                                    <Badge variant="outline" class="font-normal text-[11px]">
                                                        {activeHourData.subtitle}
                                                    </Badge>
                                                {/if}
                                            </h4>
                                            <p class="text-xs text-muted-foreground mt-0.5">
                                                {activeHourData.advisedCount} of {activeHourData.totalApplicants} advised · {activeHourData.waitingCount} waiting · {activeHourData.inProgressCount} in progress
                                            </p>
                                        </div>
                                        <div class="flex items-center gap-3">
                                            <span class="font-mono text-xs font-bold text-foreground">
                                                {activeHourData.completionRate}% Done
                                            </span>
                                            <div class="w-24 sm:w-32 h-2 rounded-full bg-muted overflow-hidden flex">
                                                <div class="h-full bg-emerald-500 transition-all duration-300" style="width: {activeHourData.completionRate}%"></div>
                                                <div class="h-full bg-amber-400 transition-all duration-300" style="width: {activeHourData.totalApplicants > 0 ? (activeHourData.inProgressCount / activeHourData.totalApplicants) * 100 : 0}%"></div>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <!-- IN-BATCH SEARCH & FILTER TOOLBAR (Crucial for 40+ Examinee Batches) -->
                                <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                                    <!-- Search input -->
                                    <div class="relative flex-1">
                                        <Search class="pointer-events-none absolute left-3 top-1/2 size-3.5 -translate-y-1/2 text-muted-foreground" />
                                        <Input
                                            bind:value={batchSearchQuery}
                                            oninput={() => (batchPage = 1)}
                                            placeholder="Search examinee by name, reference code, or room..."
                                            class="h-9 pl-8 pr-8 text-xs"
                                            aria-label="Filter examinees in active batch"
                                        />
                                        {#if batchSearchQuery}
                                            <button
                                                type="button"
                                                class="absolute right-2.5 top-1/2 -translate-y-1/2 text-muted-foreground hover:text-foreground"
                                                onclick={() => { batchSearchQuery = ''; batchPage = 1; }}
                                                aria-label="Clear filter"
                                            >
                                                <X class="size-3.5" />
                                            </button>
                                        {/if}
                                    </div>

                                    <!-- Status Filter Pills -->
                                    <div class="flex flex-wrap items-center gap-2">
                                        <!-- Status Filter Pills -->
                                        <div class="flex items-center gap-1 overflow-x-auto pb-0.5 sm:shrink-0" role="group" aria-label="Filter by advising status">
                                            <button
                                                type="button"
                                                class="rounded-md px-2.5 py-1 text-[11px] font-semibold transition-colors {batchStatusFilter === 'all' ? 'bg-primary text-primary-foreground' : 'bg-muted/60 text-muted-foreground hover:bg-muted'}"
                                                onclick={() => { batchStatusFilter = 'all'; batchPage = 1; }}
                                            >
                                                All ({activeHourData.totalApplicants})
                                            </button>
                                            <button
                                                type="button"
                                                class="rounded-md px-2.5 py-1 text-[11px] font-semibold transition-colors {batchStatusFilter === 'waiting' ? 'bg-primary text-primary-foreground' : 'bg-muted/60 text-muted-foreground hover:bg-muted'}"
                                                onclick={() => { batchStatusFilter = 'waiting'; batchPage = 1; }}
                                            >
                                                Waiting ({activeHourData.waitingCount})
                                            </button>
                                            <button
                                                type="button"
                                                class="rounded-md px-2.5 py-1 text-[11px] font-semibold transition-colors {batchStatusFilter === 'in_progress' ? 'bg-primary text-primary-foreground' : 'bg-muted/60 text-muted-foreground hover:bg-muted'}"
                                                onclick={() => { batchStatusFilter = 'in_progress'; batchPage = 1; }}
                                            >
                                                In Progress ({activeHourData.inProgressCount})
                                            </button>
                                            <button
                                                type="button"
                                                class="rounded-md px-2.5 py-1 text-[11px] font-semibold transition-colors {batchStatusFilter === 'advised' ? 'bg-primary text-primary-foreground' : 'bg-muted/60 text-muted-foreground hover:bg-muted'}"
                                                onclick={() => { batchStatusFilter = 'advised'; batchPage = 1; }}
                                            >
                                                Advised ({activeHourData.advisedCount})
                                            </button>
                                        </div>

                                        <SortDropdown
                                            options={queueSortOptions}
                                            sort={batchSortKey}
                                            dir={batchSortDir}
                                            onchange={(e) => {
                                                batchSortKey = e.sort;
                                                batchSortDir = e.dir;
                                                batchPage = 1;
                                            }}
                                        />
                                    </div>
                                </div>

                                <!-- HIGH-DENSITY EXAMINEE ROSTER (Handling 40+ Examinees with Zero Lag) -->
                                {#if pagedBatchApplicants.length}
                                    <div class="grid grid-cols-1 gap-2.5 sm:grid-cols-2">
                                        {#each pagedBatchApplicants as applicant (applicant.attempt_id ?? applicant.reference_code)}
                                            {@const status = consultationStatus(applicant)}
                                            <div class="group flex flex-col justify-between rounded-lg border border-border/70 bg-card p-3 transition-all hover:border-primary/40 hover:shadow-xs">
                                                <div>
                                                    <div class="flex items-start justify-between gap-2">
                                                        <div class="min-w-0">
                                                            <div class="flex items-center gap-1.5">
                                                                <span class="font-mono text-xs font-bold text-foreground">
                                                                    {applicant.reference_code}
                                                                </span>
                                                                {#if applicant.room}
                                                                    <span class="text-[10px] text-muted-foreground font-medium">
                                                                        · {applicant.room}
                                                                    </span>
                                                                {/if}
                                                            </div>
                                                            <p class="truncate text-sm font-semibold text-foreground mt-0.5">
                                                                {applicant.name ?? 'Unregistered Examinee'}
                                                            </p>
                                                        </div>
                                                        <Badge variant={consultationStatusVariant(status)} class={consultationStatusClass(status)}>
                                                            {status}
                                                        </Badge>
                                                    </div>
                                                </div>

                                                <div class="mt-3 flex flex-wrap items-center justify-between gap-2 border-t border-border/40 pt-2">
                                                    <span class="text-[11px] font-medium text-muted-foreground">
                                                        {#if applicant.session_time_range}
                                                            {applicant.session_time_range}
                                                        {:else if applicant.session_time}
                                                            {formatTime(applicant.session_time)}
                                                        {/if}
                                                    </span>
                                                    <div class="flex flex-wrap items-center gap-2">
                                                        {#if applicant.release_day_readiness === 'needs_print'}
                                                            <Badge variant="outline" class="shrink-0 text-[10px]">Needs Result Sheet Print</Badge>
                                                            <PrintDocumentsSlot slotKey="guidance_applicant_support" attemptIds={[applicant.attempt_id ?? applicant.id]} autoStart={true} compact={true} buttonVariant="ghost" />
                                                        {:else}
                                                            <span class="inline-flex items-center gap-1 text-[11px] font-medium text-emerald-700 dark:text-emerald-400">
                                                                <CheckCircle2 class="size-3" />
                                                                <span>Printed</span>
                                                            </span>
                                                        {/if}
                                                        <button
                                                            type="button"
                                                            class="inline-flex items-center gap-1 text-xs font-semibold text-primary group-hover:underline focus-visible:outline-none"
                                                            onclick={() => openApplicant(applicant.attempt_id)}
                                                        >
                                                            <span>{status === 'Advised' ? 'View Decision' : 'Start Advising'}</span>
                                                            <ArrowRight class="size-3 transition-transform group-hover:translate-x-0.5" />
                                                        </button>
                                                    </div>
                                                </div>
                                            </div>
                                        {/each}
                                    </div>

                                    <!-- BATCH PAGINATION (Keeps 40+ Examinees Compact & Snappy) -->
                                    <div class="flex flex-col gap-2 pt-2 sm:flex-row sm:items-center sm:justify-between border-t border-border/40">
                                        <p class="text-xs text-muted-foreground" aria-live="polite">
                                            Showing {(currentBatchPage - 1) * batchPageSize + 1}–{Math.min(currentBatchPage * batchPageSize, filteredBatchApplicants.length)} of {filteredBatchApplicants.length} examinees
                                            {#if batchSearchQuery || batchStatusFilter !== 'all'}
                                                <span class="text-primary font-medium">(Filtered)</span>
                                            {/if}
                                        </p>
                                        {#if batchPageCount > 1}
                                            <div class="flex items-center gap-1 self-end sm:self-center">
                                                <Button
                                                    variant="outline"
                                                    size="sm"
                                                    class="h-7 px-2 text-xs"
                                                    disabled={currentBatchPage <= 1}
                                                    onclick={() => (batchPage = Math.max(1, currentBatchPage - 1))}
                                                >
                                                    <ChevronLeft class="size-3 mr-0.5" /> Previous
                                                </Button>
                                                <span class="px-2 text-xs text-muted-foreground">
                                                    Page {currentBatchPage} of {batchPageCount}
                                                </span>
                                                <Button
                                                    variant="outline"
                                                    size="sm"
                                                    class="h-7 px-2 text-xs"
                                                    disabled={currentBatchPage >= batchPageCount}
                                                    onclick={() => (batchPage = Math.min(batchPageCount, currentBatchPage + 1))}
                                                >
                                                    Next <ChevronRight class="size-3 ml-0.5" />
                                                </Button>
                                            </div>
                                        {/if}
                                    </div>
                                {:else}
                                    <div class="rounded-lg border border-dashed py-10 text-center text-sm text-muted-foreground">
                                        No examinees found in this batch matching the selected filter.
                                    </div>
                                {/if}
                            {:else}
                                <div class="rounded-xl border border-dashed py-12 text-center text-sm text-muted-foreground">
                                    <p class="font-medium text-foreground">No Release Day sessions scheduled for today.</p>
                                    <p class="mt-1 text-xs">Applicants can still be assisted through walk-ins or booked into a Release Day session.</p>
                                    {#if can_plan}
                                        <Link href={sessionsUrl} class="mt-4 inline-block">
                                            <Button size="sm" variant="outline" class="gap-1.5">
                                                <Calendar class="size-3.5" /> Manage Release Days
                                            </Button>
                                        </Link>
                                    {/if}
                                </div>
                            {/if}
                        </div>
                    </section>
                </div>

                <!-- ==================================================================== -->
                <!-- RIGHT COLUMN: FOUR-TAB RELEASE DAY APPLICANT WORKSPACE -->
                <!-- ==================================================================== -->
                <!-- svelte-ignore a11y_no_noninteractive_tabindex (Scrollable queues need a keyboard focus target.) -->
                <div
                    hidden={sidebarHidden}
                    class="min-w-0 max-h-[70dvh] space-y-4 overflow-y-auto overscroll-y-contain pr-2 pb-2 [scrollbar-gutter:stable] focus-visible:outline focus-visible:outline-2 focus-visible:outline-ring lg:sticky lg:top-0 lg:col-span-5 lg:max-h-[max(16rem,calc(100dvh-12rem))] lg:self-start {sidebarHidden ? 'hidden' : ''} {mobileViewTab === 'today' ? 'hidden lg:block' : ''}"
                    role="region"
                    aria-label="Release Day sidebar queues"
                    data-tour="release-day-applicants"
                    tabindex="0"
                >
                    <section class="overflow-hidden rounded-lg border border-border bg-card" aria-label="Release Day applicants">
                        <div class="space-y-3 border-b border-border p-3">
                            <div class="flex flex-wrap items-center gap-1" role="tablist" tabindex="-1" aria-label="Release Day applicant views" onkeydown={handleWorkspaceTabKeydown}>
                                {#each [{ key: 'scheduled', label: 'Scheduled' }, { key: 'unscheduled', label: 'Unscheduled' }, { key: 'released', label: 'Released' }, { key: 'followups', label: 'Follow-ups' }] as tab (tab.key)}
                                    <button type="button" role="tab" data-release-tab={tab.key} aria-selected={activeWorkspaceTab === tab.key} aria-controls="release-day-applicant-panel" tabindex={activeWorkspaceTab === tab.key ? 0 : -1} class="min-h-9 rounded-md px-3 text-xs font-semibold {activeWorkspaceTab === tab.key ? 'bg-primary text-primary-foreground' : 'text-muted-foreground hover:bg-muted'}" onclick={() => selectWorkspaceTab(tab.key)}>
                                        {tab.label}
                                    </button>
                                {/each}
                            </div>
                            <div class="flex flex-wrap items-center gap-2">
                                <Input value={workspaceStates[activeWorkspaceTab].search} oninput={(event) => { workspaceStates[activeWorkspaceTab].search = event.currentTarget.value; workspaceStates[activeWorkspaceTab].page = 1; }} placeholder="Search applicants" aria-label="Search Release Day applicants" class="h-9 min-w-0 flex-1" />
                                <SortDropdown options={workspaceSortOptions} sort={workspaceStates[activeWorkspaceTab].sort} dir={workspaceStates[activeWorkspaceTab].dir} onchange={(event) => { workspaceStates[activeWorkspaceTab].sort = event.sort; workspaceStates[activeWorkspaceTab].dir = event.dir; workspaceStates[activeWorkspaceTab].page = 1; }} />
                                <select aria-label="Filter by print status" value={workspaceStates[activeWorkspaceTab].printFilter} class="h-9 min-w-0 rounded-md border border-input bg-background px-2 text-xs" onchange={(event) => { workspaceStates[activeWorkspaceTab].printFilter = event.currentTarget.value; workspaceStates[activeWorkspaceTab].page = 1; }}>
                                    <option value="all">All print states</option>
                                    <option value="not_prepared">Not prepared</option>
                                    <option value="not_printed">Not printed</option>
                                    <option value="printed">Printed</option>
                                </select>
                            </div>
                        </div>
                        <div id="release-day-applicant-panel">
                            {#if activeWorkspaceTab === 'scheduled'}
                                {#if scheduledGroupsPage.items.length}
                                    <ul class="divide-y divide-border/60">
                                        {#each scheduledGroupsPage.items as session, index (session.id)}
                                            <li class="p-2">
                                                <details class="group overflow-hidden rounded-lg border border-border/60 bg-background" open={index === 0 || Boolean(workspaceStates.scheduled.search.trim())}>
                                                    <summary class="flex cursor-pointer list-none items-start justify-between gap-2 p-3 hover:bg-muted/30">
                                                        <div class="min-w-0 flex-1">
                                                            <div class="flex flex-wrap items-center gap-1.5">
                                                                <span class="truncate text-xs font-bold text-foreground">{session.code}</span>
                                                                <Badge variant="outline" class="text-[10px]">{session.status === 'in_progress' ? 'In progress' : 'Published'}</Badge>
                                                            </div>
                                                            <p class="mt-1 text-[11px] text-muted-foreground">{formatDate(session.scheduled_at)} · {session.time_range ?? formatDateTime(session.scheduled_at)}</p>
                                                            <p class="mt-0.5 text-[11px] text-muted-foreground">{session.room ?? 'Room not set'} · {session.visibleApplicants.length} {session.visibleApplicants.length === 1 ? 'applicant' : 'applicants'}</p>
                                                        </div>
                                                        <ChevronDown class="mt-0.5 size-4 shrink-0 text-muted-foreground transition-transform group-open:rotate-180" />
                                                    </summary>
                                                    <div class="space-y-2.5 border-t border-border/50 bg-muted/10 p-2.5">
                                                        <div class="flex flex-wrap items-center justify-between gap-2">
                                                            {#if session.is_future_published}
                                                                <Button size="sm" variant="default" class="h-8 gap-1.5 px-2.5 text-[11px] font-semibold" onclick={() => promptOpenSessionForToday(session)}>
                                                                    <Play class="size-3" />
                                                                    Open for Today
                                                                </Button>
                                                            {/if}
                                                            <Link href={session.href}>
                                                                <Button size="sm" variant="outline" class="h-8 px-2.5 text-[11px]">View roster</Button>
                                                            </Link>
                                                        </div>
                                                        <ul class="divide-y divide-border/40 overflow-hidden rounded-lg border border-border/50 bg-card">
                                                            {#each session.visibleApplicants as applicant (applicant.attempt_id)}
                                                                <li class="flex flex-wrap items-center justify-between gap-2 p-2.5">
                                                                    <div class="min-w-0">
                                                                        <p class="break-words text-xs font-semibold text-foreground">{applicant.name ?? 'Applicant'}</p>
                                                                        <p class="font-mono text-[10px] text-muted-foreground">{applicant.reference_code}</p>
                                                                    </div>
                                                                    <div class="flex flex-wrap items-center gap-2">
                                                                        <Badge variant="outline" class="text-[10px]">{applicant.document_status === 'prepared' ? 'Prepared' : 'Not prepared'}</Badge>
                                                                        <Badge variant="outline" class="text-[10px]">{applicant.print_status === 'printed' ? 'Printed' : 'Not printed'}</Badge>
                                                                        {#if applicant.advising_status === 'advised_awaiting_release'}
                                                                            <Badge variant="secondary" class="text-[10px]">Advised · awaiting release</Badge>
                                                                        {/if}
                                                                        {#if applicant.print_status !== 'printed'}
                                                                            <PrintDocumentsSlot slotKey="guidance_applicant_support" attemptIds={[applicant.attempt_id]} autoStart={true} compact={true} buttonVariant="ghost" />
                                                                        {/if}
                                                                        <button type="button" class="inline-flex min-h-9 items-center gap-1 text-xs font-semibold text-primary hover:underline" onclick={() => router.visit(applicant.url ?? `/consultation/applicants/${applicant.attempt_id}?session_id=${session.id}`)}>
                                                                            {applicant.advising_status === 'advised_awaiting_release' ? 'View advising' : 'Start Advising'}
                                                                            <ArrowRight class="size-3" />
                                                                        </button>
                                                                    </div>
                                                                </li>
                                                            {/each}
                                                        </ul>
                                                    </div>
                                                </details>
                                            </li>
                                        {/each}
                                    </ul>
                                    {#if scheduledGroupsPage.pageCount > 1}
                                        <div class="flex items-center justify-between border-t border-border px-3 py-2 text-xs">
                                            <span>Page {scheduledGroupsPage.currentPage} of {scheduledGroupsPage.pageCount}</span>
                                            <div class="flex gap-2">
                                                <Button size="sm" variant="outline" disabled={scheduledGroupsPage.currentPage <= 1} onclick={() => (workspaceStates.scheduled.page = Math.max(1, workspaceStates.scheduled.page - 1))}>Previous</Button>
                                                <Button size="sm" variant="outline" disabled={scheduledGroupsPage.currentPage >= scheduledGroupsPage.pageCount} onclick={() => (workspaceStates.scheduled.page = Math.min(scheduledGroupsPage.pageCount, workspaceStates.scheduled.page + 1))}>Next</Button>
                                            </div>
                                        </div>
                                    {/if}
                                {:else}
                                    <p class="px-4 py-8 text-center text-sm text-muted-foreground">{workspaceStates.scheduled.search.trim() || workspaceStates.scheduled.printFilter !== 'all' ? 'No scheduled groups match these filters.' : 'No published or in-progress scheduled applicants in this cycle.'}</p>
                                {/if}
                            {:else}
                                {#if activeWorkspacePage.items.length}
                                    <ul class="divide-y divide-border/60">
                                        {#each activeWorkspacePage.items as applicant (applicant.attempt_id)}
                                            <li class="flex flex-wrap items-center justify-between gap-2 px-3 py-2.5">
                                                <div class="min-w-0">
                                                    <p class="break-words text-sm font-semibold">{applicant.name ?? 'Applicant'}</p>
                                                    <p class="font-mono text-xs text-muted-foreground">{applicant.reference_code}</p>
                                                    {#if activeWorkspaceTab === 'followups' && applicant.scoring_blocker}
                                                        <p class="mt-1 text-xs text-muted-foreground">{applicant.scoring_blocker}</p>
                                                    {/if}
                                                </div>
                                                <div class="flex flex-wrap items-center gap-2">
                                                    {#if activeWorkspaceTab === 'unscheduled'}
                                                        <Badge variant="outline">{applicant.appointment_status === 'tentative' ? 'Tentative' : 'Unscheduled'}</Badge>
                                                    {/if}
                                                    {#if applicant.document_status}
                                                        <Badge variant="outline">{applicant.document_status === 'prepared' ? 'Prepared' : 'Not prepared'}</Badge>
                                                    {/if}
                                                    {#if applicant.print_status}
                                                        <Badge variant="outline">{applicant.print_status === 'printed' ? 'Printed' : 'Not printed'}</Badge>
                                                    {/if}
                                                    {#if activeWorkspaceTab === 'followups' && applicant.reason}
                                                        <Badge variant="outline">{applicant.reason === 'deferred' ? 'Deferred' : applicant.reason === 'program_follow_up' ? `Program follow-up${applicant.follow_up_reason ? ` · ${applicant.follow_up_reason}` : ''}` : 'Department declined'}</Badge>
                                                    {/if}
                                                    {#if activeWorkspaceTab === 'unscheduled' && applicant.document_status === 'not_prepared'}
                                                        <PrintDocumentsSlot slotKey="guidance_applicant_support" attemptIds={[applicant.attempt_id]} autoStart={true} compact={true} buttonVariant="ghost" />
                                                    {/if}
                                                    <Link href={applicant.url ?? `/consultation/applicants/${applicant.attempt_id}`}>
                                                        <Button size="sm" variant="outline" class="min-h-9">{activeWorkspaceTab === 'unscheduled' ? (applicant.advising_status === 'advised_awaiting_release' ? 'View advising' : 'Start Advising') : 'View record'}</Button>
                                                    </Link>
                                                </div>
                                            </li>
                                        {/each}
                                    </ul>
                                {:else}
                                    <p class="px-4 py-8 text-center text-sm text-muted-foreground">{workspaceStates[activeWorkspaceTab].search.trim() ? 'No applicants match this search.' : 'No applicants in this view.'}</p>
                                {/if}
                                {#if activeWorkspacePage.pageCount > 1}
                                    <div class="flex items-center justify-between border-t border-border px-3 py-2 text-xs">
                                        <span>Page {activeWorkspacePage.currentPage} of {activeWorkspacePage.pageCount}</span>
                                        <div class="flex gap-2">
                                            <Button size="sm" variant="outline" disabled={activeWorkspacePage.currentPage <= 1} onclick={() => (workspaceStates[activeWorkspaceTab].page = Math.max(1, workspaceStates[activeWorkspaceTab].page - 1))}>Previous</Button>
                                            <Button size="sm" variant="outline" disabled={activeWorkspacePage.currentPage >= activeWorkspacePage.pageCount} onclick={() => (workspaceStates[activeWorkspaceTab].page = Math.min(activeWorkspacePage.pageCount, activeWorkspacePage.page + 1))}>Next</Button>
                                        </div>
                                    </div>
                                {/if}
                            {/if}
                        </div>
                    </section>

                </div>
            </div>
        {/if}


        <Dialog.Root bind:open={openSessionDialogOpen}>
            <Dialog.Content class="sm:max-w-md">
                <Dialog.Header>
                    <Dialog.Title class="flex items-center gap-2">
                        <CalendarCheck class="size-4 text-primary" />
                        <span>Open Session on Today's Release Day Board?</span>
                    </Dialog.Title>
                    <Dialog.Description>
                        Opening this session early marks it In Progress and moves its full roster onto Today's Active Schedule board while keeping its original scheduled date for audit accuracy.
                    </Dialog.Description>
                </Dialog.Header>
                {#if sessionToOpen}
                    <div class="rounded-lg border border-border/60 bg-muted/30 p-3 text-xs space-y-1">
                        <p class="font-semibold text-foreground">{sessionToOpen.code}</p>
                        <p class="text-muted-foreground">
                            {formatDate(sessionToOpen.scheduled_at)} · {sessionToOpen.time_range ?? formatDateTime(sessionToOpen.scheduled_at)}
                        </p>
                        <p class="text-muted-foreground">
                            {sessionToOpen.room ?? 'Room not set'} · {sessionToOpen.assigned_count} {sessionToOpen.assigned_count === 1 ? 'applicant' : 'applicants'}
                        </p>
                    </div>
                {/if}
                <Dialog.Footer class="gap-2 sm:justify-end">
                    <Button
                        type="button"
                        variant="outline"
                        disabled={openSessionBusy}
                        onclick={() => {
                            openSessionDialogOpen = false;
                            sessionToOpen = null;
                        }}
                    >
                        Cancel
                    </Button>
                    <Button
                        type="button"
                        disabled={openSessionBusy}
                        onclick={confirmOpenSessionForToday}
                        class="gap-1.5"
                    >
                        <Play class="size-3.5" />
                        <span>{openSessionBusy ? 'Opening...' : 'Confirm Open for Today'}</span>
                    </Button>
                </Dialog.Footer>
            </Dialog.Content>
        </Dialog.Root>
        {#if page_state === 'ready' && dashboard}
            <ApplicantLookupDialog
                open={applicantLookupOpen}
                onOpenChange={(open) => (applicantLookupOpen = open)}
            />
            <ConsultationMobileDock
                mode="index"
                onSearch={() => (applicantLookupOpen = true)}
            />
        {/if}
    </div>
</StaffLayout>