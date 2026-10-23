// @vitest-environment jsdom

import { cleanup, fireEvent, render } from '@testing-library/svelte';
import { tick } from 'svelte';
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import Monitoring from '@/Pages/Examination/Monitoring.svelte';
import { isStaffNavActive } from '@/Pages/Staff/staff-nav.js';
const mockOfflineStore = vi.hoisted(() => ({
    records: [] as Array<Record<string, unknown>>,
    available: true,
    failQueue: false,
}));

vi.mock('@/lib/exam-monitoring-offline.js', () => ({
    isOfflineStorageAvailable: () => mockOfflineStore.available,
    listAttendanceCommands: vi.fn(async (actorId: string | number, sessionId: string | number) =>
        mockOfflineStore.records.filter((entry) => entry.actorId === String(actorId) && entry.sessionId === String(sessionId)),
    ),
    queueAttendanceCommand: vi.fn(async (command: Record<string, unknown>) => {
        if (mockOfflineStore.failQueue) throw new Error('QuotaExceededError: offline storage is full.');
        const record = {
            ...command,
            key: `${command.actorId}:${command.sessionId}:${command.idempotencyKey}`,
            state: 'pending',
            queuedAt: new Date().toISOString(),
            conflictMessage: null,
        };
        mockOfflineStore.records.push(record);
        return record;
    }),
    updateAttendanceCommand: vi.fn(async (command: Record<string, unknown>, updates: Record<string, unknown>) => {
        const record = mockOfflineStore.records.find((entry) => entry.key === command.key);
        if (record) Object.assign(record, updates);
    }),
    removeAttendanceCommand: vi.fn(async (command: Record<string, unknown>) => {
        mockOfflineStore.records = mockOfflineStore.records.filter((entry) => entry.key !== command.key);
    }),
}));

const mockRouterPost = vi.fn();
const mockRouterVisit = vi.fn();
const mockRouterReload = vi.fn();
const mockRouterOn = vi.fn(() => () => {});

vi.mock('@inertiajs/svelte', async (importOriginal) => {
    const actual = await importOriginal<Record<string, unknown>>();
    const actualRouter = (actual.router as Record<string, unknown>) ?? {};

    return {
        ...actual,
        usePage: () => ({
            props: {
                auth: { user: { id: 1, name: 'Counselor', office: 'guidance' } },
                staff_navigation: [],
                notifications: { unread_count: 0, recent: [] },
                xiam: { enabled: false },
            },
        }),
        router: {
            ...actualRouter,
            on: (...args: unknown[]) => mockRouterOn(...args),
            post: (...args: unknown[]) => mockRouterPost(...args),
            visit: (...args: unknown[]) => mockRouterVisit(...args),
            reload: (...args: unknown[]) => mockRouterReload(...args),
        },
    };
});

describe('Examination/Monitoring conduct page', () => {
    beforeEach(() => {
        vi.clearAllMocks();
        mockOfflineStore.records = [];
        mockOfflineStore.available = true;
        mockOfflineStore.failQueue = false;
    });

    afterEach(() => {
        cleanup();
        vi.unstubAllGlobals();
        Object.defineProperty(window.navigator, 'onLine', { configurable: true, value: true });
    });

    const mockSession = {
        id: 28,
        code: 'EX-2026-028',
        version: 1,
        status: 'in_progress',
        started_at: '2026-10-20T02:20:00.000000Z',
        date: '2026-10-20',
        start_time: '10:20',
        end_time: '11:50',
        room: { id: 1, name: 'Main Hall 101', code: 'RM-101', capacity: 40 },
        academic_cycle: { label: '2026-2027 1st Semester' },
    };
    it.each(['published', 'completed', 'cancelled'])('hides attendance controls for a %s session despite can_record', (status) => {
        const { container, queryByText } = render(Monitoring, {
            props: {
                session: { ...mockSession, status },
                roster: mockRoster,
                can_record: true,
                can_manage: true,
            },
        });

        expect(queryByText('Start Session')).toBeNull();
        expect(queryByText('Scan Examinee QR (Door)')).toBeNull();
        const buttons = Array.from(container.querySelectorAll('[id^="table-monitoring-assignment-"] button, [id^="card-monitoring-assignment-"] button')).map((button) => button.textContent ?? '');
        expect(buttons.some((text) => /Present|Absent|Void \/ Walkout/.test(text))).toBe(false);
        expect(container.querySelector('[aria-label="Attendance filters"]')).toBeTruthy();
        if (status === 'completed') {
            expect(queryByText('Proceed to Scoring')).toBeTruthy();
        } else {
            expect(queryByText('Proceed to Scoring')).toBeNull();
        }
    });

    it('shows the authorized Start control for a published session without attendance controls', () => {
        const { container, queryByText } = render(Monitoring, {
            props: {
                session: { ...mockSession, status: 'published' },
                roster: mockRoster,
                can_record: false,
                can_start: true,
            },
        });

        expect(queryByText('Start Session')).toBeTruthy();
        expect(queryByText('Scan Examinee QR (Door)')).toBeNull();
        const buttons = Array.from(container.querySelectorAll('[id^="table-monitoring-assignment-"] button, [id^="card-monitoring-assignment-"] button')).map((button) => button.textContent ?? '');
        expect(buttons.some((text) => /Present|Absent|Void \/ Walkout/.test(text))).toBe(false);
    });

    const mockRoster = [
        {
            id: 101,
            reference_number: 'APP-2026-00001',
            name: 'Juan Dela Cruz',
            sex: 'male',
            attendance: null,
            submitted: false,
            seat_label: 'A-01',
        },
        {
            id: 102,
            reference_number: 'APP-2026-00002',
            name: 'Maria Santos',
            sex: 'female',
            attendance: 'present',
            submitted: true,
            seat_label: 'A-02',
        },
        {
            id: 103,
            reference_number: 'APP-2026-00003',
            name: 'Alex Reyes',
            sex: null,
            attendance: 'absent',
            submitted: false,
            seat_label: 'A-03',
        },
    ];

    it('renders unboxed SwitchableListView with both table and card views', () => {
        const { container } = render(Monitoring, {
            props: {
                session: mockSession,
                roster: mockRoster,
                can_record: true,
                can_manage: true,
            },
        });

        // Table row and card representations rendered for candidates
        expect(container.querySelector('#table-monitoring-assignment-101')).toBeTruthy();
        expect(container.querySelector('#card-monitoring-assignment-101')).toBeTruthy();
        expect(container.querySelector('#table-monitoring-assignment-102')).toBeTruthy();
        expect(container.querySelector('#card-monitoring-assignment-102')).toBeTruthy();
    });

    it('shows attendance without exposing an existing submission status', () => {
        const { container } = render(Monitoring, {
            props: {
                session: mockSession,
                roster: mockRoster,
                can_record: true,
                can_manage: true,
            },
        });

        const presentRow = container.querySelector('#table-monitoring-assignment-102');
        const absentRow = container.querySelector('#table-monitoring-assignment-103');

        expect(presentRow?.textContent).toContain('Present');
        expect(absentRow?.textContent).toContain('Absent');
        expect(container.textContent).not.toMatch(/submission|will submit on close/i);
    });

    it('displays gender avatars with appropriate titles for male and female examinees', () => {
        const { container } = render(Monitoring, {
            props: {
                session: mockSession,
                roster: mockRoster,
                can_record: true,
            },
        });

        const maleTitles = container.querySelectorAll('[title="Male"]');
        expect(maleTitles.length).toBeGreaterThan(0);

        const femaleTitles = container.querySelectorAll('[title="Female"]');
        expect(femaleTitles.length).toBeGreaterThan(0);
    });

    it('filters roster client-side by search query', async () => {
        const { container } = render(Monitoring, {
            props: {
                session: mockSession,
                roster: mockRoster,
                can_record: true,
            },
        });

        const searchInput = container.querySelector('input[aria-label="Search examinees"]') as HTMLInputElement;
        expect(searchInput).toBeTruthy();

        await fireEvent.input(searchInput, { target: { value: 'Santos' } });
        await tick();

        // Maria Santos should be visible, Juan Dela Cruz should not
        expect(container.querySelector('#table-monitoring-assignment-102')).toBeTruthy();
        expect(container.querySelector('#table-monitoring-assignment-101')).toBeNull();
    });

    it('filters roster client-side by attendance status pill', async () => {
        const { container, getByText } = render(Monitoring, {
            props: {
                session: mockSession,
                roster: mockRoster,
                can_record: true,
            },
        });

        const unrecordedButton = getByText('Unrecorded');
        await fireEvent.click(unrecordedButton);
        await tick();

        // Only Juan Dela Cruz (attendance: null) should be shown
        expect(container.querySelector('#table-monitoring-assignment-101')).toBeTruthy();
        expect(container.querySelector('#table-monitoring-assignment-102')).toBeNull();
        expect(container.querySelector('#table-monitoring-assignment-103')).toBeNull();
    });

    it('presents Present and Absent as primary row actions', async () => {
        const { container } = render(Monitoring, {
            props: {
                session: mockSession,
                roster: mockRoster,
                can_record: true,
            },
        });

        const row = container.querySelector('#table-monitoring-assignment-101');
        expect(row?.textContent).toContain('Present');
        expect(row?.textContent).toContain('Absent');
        // Primary row buttons should not include inline Void button
        expect(row?.querySelectorAll('button').length).toBe(3); // Present, Absent, ActionDropdown trigger
    });

    it('writes attendance to durable storage before an online replay attempt', async () => {
        const { container } = render(Monitoring, {
            props: {
                session: mockSession,
                roster: mockRoster,
                can_record: true,
                offline_actor_id: 1,
            },
        });

        const row = container.querySelector('#table-monitoring-assignment-101');
        const presentBtn = Array.from(row?.querySelectorAll('button') ?? []).find((button) => button.textContent?.includes('Present'));
        await fireEvent.click(presentBtn!);
        await vi.waitFor(() => expect(mockOfflineStore.records).toHaveLength(1));

        expect(mockOfflineStore.records[0]).toMatchObject({
            actorId: '1',
            sessionId: '28',
            assignmentId: 101,
            outcome: 'present',
            expectedStartedAt: mockSession.started_at,
            state: 'pending',
        });
        expect(mockRouterPost).not.toHaveBeenCalled();
        expect(container.textContent).toContain('1 attendance pending sync');
    });

    it('opens Start Session duration confirmation dialog and posts duration_minutes', async () => {
        const { getByText, getByLabelText } = render(Monitoring, {
            props: {
                session: {
                    ...mockSession,
                    status: 'published',
                    scheduled_duration_minutes: 90,
                },
                roster: mockRoster,
                can_record: false,
                can_start: true,
            },
        });

        await fireEvent.click(getByText('Start Session'));
        await tick();

        const durationInput = getByLabelText('Session duration (minutes)') as HTMLInputElement;
        expect(durationInput.value).toBe('90');
        expect(mockRouterPost).not.toHaveBeenCalled();

        await fireEvent.click(getByText('Confirm & Start Session'));
        expect(mockRouterPost).toHaveBeenCalledWith(
            '/exams/sessions/28/start',
            { expected_version: 1, duration_minutes: 90 },
            expect.any(Object),
        );
    });

    it('shows full date and time for Started and Expected end when session is in progress', () => {
        const { container } = render(Monitoring, {
            props: {
                session: {
                    ...mockSession,
                    status: 'in_progress',
                    started_at: '2026-10-16T13:10:00+00:00',
                    effective_end_at: '2026-10-16T14:40:00+00:00',
                },
                roster: mockRoster,
                can_record: true,
            },
        });

        expect(container.textContent).toContain('Started Oct 16, 2026');
        expect(container.textContent).toContain('Expected end Oct 16, 2026');
    });

    it('shows the Overtime Grace Warning Banner with remaining grace countdown and extends from current time', async () => {
        const fiveMinsAgo = new Date(Date.now() - 5 * 60 * 1000).toISOString();
        const { getByText, getAllByText } = render(Monitoring, {
            props: {
                session: {
                    ...mockSession,
                    status: 'in_progress',
                    started_at: new Date(Date.now() - 95 * 60 * 1000).toISOString(),
                    effective_end_at: fiveMinsAgo,
                },
                roster: mockRoster,
                can_record: true,
                thresholds: { auto_close_grace_minutes: 15 },
            },
        });

        expect(getByText(/Session Overtime — Auto-Close Grace Window Active/i)).toBeTruthy();
        expect(getByText(/Auto-closing in/i)).toBeTruthy();

        const beforeClick = Date.now();
        await fireEvent.click(getAllByText('Extend +15m')[0]);
        expect(mockRouterPost).toHaveBeenCalledWith(
            '/exams/sessions/28/extend',
            expect.objectContaining({ expected_version: 1 }),
            expect.any(Object),
        );
        const payload = mockRouterPost.mock.calls[0][1] as { effective_end_at: string };
        expect(new Date(payload.effective_end_at).getTime()).toBeGreaterThanOrEqual(beforeClick + 14 * 60 * 1000);
    });

    it('automatically triggers router.reload when the overtime grace period has lapsed', async () => {
        const twentyMinsAgo = new Date(Date.now() - 20 * 60 * 1000).toISOString();
        render(Monitoring, {
            props: {
                session: {
                    ...mockSession,
                    status: 'in_progress',
                    started_at: new Date(Date.now() - 110 * 60 * 1000).toISOString(),
                    effective_end_at: twentyMinsAgo,
                },
                roster: mockRoster,
                can_record: true,
                thresholds: { auto_close_grace_minutes: 15 },
            },
        });

        await tick();
        expect(mockRouterReload).toHaveBeenCalled();
    });

    it('allows reverting an accidentally started session when zero attendance is recorded and hides it once attendance exists', async () => {
        const unrecordedRoster = mockRoster.map((row) => ({ ...row, attendance: null, submitted: false }));
        const { getByText, queryByText } = render(Monitoring, {
            props: {
                session: {
                    ...mockSession,
                    status: 'in_progress',
                },
                roster: unrecordedRoster,
                can_record: true,
                can_manage: true,
            },
        });

        const revertBtn = getByText('Revert to Published');
        expect(revertBtn).toBeTruthy();

        await fireEvent.click(revertBtn);
        await tick();
        await fireEvent.click(getByText('Confirm & Revert Start'));

        expect(mockRouterPost).toHaveBeenCalledWith(
            '/exams/sessions/28/revert-start',
            { expected_version: 1 },
            expect.any(Object),
        );

        cleanup();
        const recordedView = render(Monitoring, {
            props: {
                session: {
                    ...mockSession,
                    status: 'in_progress',
                },
                roster: mockRoster,
                can_record: true,
                can_manage: true,
            },
        });
        expect(recordedView.queryByText('Revert to Published')).toBeNull();
    });

    it('blocks a start revert when submission evidence exists without attendance', () => {
        const submissionWithoutAttendance = mockRoster.map(
            (row, index) => ({
                ...row,
                attendance: null,
                submitted: index === 0,
            }),
        );
        const { queryByText } = render(Monitoring, {
            props: {
                session: {
                    ...mockSession,
                    status: 'in_progress',
                },
                roster: submissionWithoutAttendance,
                can_record: true,
                can_manage: true,
            },
        });

        expect(queryByText('Revert to Published')).toBeNull();
    });

    it('highlights Exam Monitoring instead of Exam Scheduling in sidebar when visiting /exams/sessions/{id}/monitoring', () => {
        const items = [
            { key: 'exam_scheduling', href: '/exams/sessions', match: 'prefix' as const },
            { key: 'exam_monitoring', href: '/exams/monitoring', match: 'prefix' as const },
        ];

        expect(isStaffNavActive(items[1], items, '/exams/sessions/4/monitoring')).toBe(true);
        expect(isStaffNavActive(items[0], items, '/exams/sessions/4/monitoring')).toBe(false);
        expect(isStaffNavActive(items[0], items, '/exams/sessions/4')).toBe(true);
        expect(isStaffNavActive(items[1], items, '/exams/sessions/4')).toBe(false);
    });

    it('opens inbound Accommodate Walk-in / Transfer modal from header, lists eligible candidates from other sessions, and submits accommodation', async () => {
        const fetchMock = vi.fn().mockResolvedValue({
            ok: true,
            headers: new Headers({ 'content-type': 'application/json' }),
            json: async () => ({ status: 'ok', assignment_id: 501 }),
        });
        vi.stubGlobal('fetch', fetchMock);

        const candidates = [
            {
                attempt_id: 11,
                reference_code: 'APP-2026-00002',
                name: 'Ghanny Javillo',
                sex: 'male',
                current_session_id: 1,
                current_session_code: 'Batch-01',
                current_session_status: 'completed',
                current_session_room: 'ITBR 104',
                attendance_outcome: 'absent',
                source_kind: 'absent',
                source_label: 'Missed: Batch-01 (Absent)',
                default_reason_category: 'tardiness_consideration',
            },
            {
                attempt_id: 16,
                reference_code: 'APP-2026-00004',
                name: 'Xilbur Hawkins',
                sex: 'male',
                current_session_id: 5,
                current_session_code: 'Batch-05',
                current_session_status: 'published',
                current_session_room: 'ITBR 105',
                attendance_outcome: null,
                source_kind: 'scheduled_other',
                source_label: 'Scheduled: Batch-05',
                default_reason_category: 'travel_distance',
            },
        ];

        const { getByText, getByLabelText } = render(Monitoring, {
            props: {
                session: { ...mockSession, status: 'published' },
                roster: mockRoster,
                can_manage: true,
                can_record: false,
                accommodate_candidates: candidates,
                transfer_target_sessions: [],
                session_remaining_capacity: 29,
            },
        });

        await fireEvent.click(getByText(/Accommodate Walk-in \/ Transfer/i));
        await tick();

        expect(getByText('Ghanny Javillo')).toBeTruthy();
        expect(getByText('Xilbur Hawkins')).toBeTruthy();

        await fireEvent.click(getByText('Ghanny Javillo'));
        await tick();

        const notesInput = getByLabelText('Reason notes') as HTMLTextAreaElement;
        await fireEvent.input(notesInput, { target: { value: 'Delayed by morning transit; approved for afternoon room' } });
        await tick();

        await fireEvent.click(getByText('Accommodate into this Room'));
        await tick();

        expect(fetchMock).toHaveBeenCalledWith(
            '/exams/sessions/28/monitoring/accommodate',
            expect.objectContaining({
                method: 'POST',
                body: JSON.stringify({
                    reference_code: 'APP-2026-00002',
                    reason_category: 'tardiness_consideration',
                    reason_notes: 'Delayed by morning transit; approved for afternoon room',
                }),
            }),
        );
        expect(mockRouterReload).toHaveBeenCalled();
        vi.unstubAllGlobals();
    });

    it('supports outbound Transfer to Other Session mode to move an unrecorded examinee to another open session', async () => {
        const fetchMock = vi.fn().mockResolvedValue({
            ok: true,
            headers: new Headers({ 'content-type': 'application/json' }),
            json: async () => ({ status: 'ok', assignment_id: 602 }),
        });
        vi.stubGlobal('fetch', fetchMock);

        const targetSessions = [
            {
                id: 5,
                code: 'Batch-05',
                status: 'published',
                date: '2026-10-19',
                start_time: '08:00',
                end_time: '09:30',
                room_name: 'ITBR 105',
                assigned_count: 1,
                capacity_limit: 40,
                remaining_capacity: 39,
            },
        ];

        const { getByText, getByLabelText, container } = render(Monitoring, {
            props: {
                session: { ...mockSession, status: 'published' },
                roster: [mockRoster[0]],
                can_manage: true,
                can_record: false,
                accommodate_candidates: [],
                transfer_target_sessions: targetSessions,
                session_remaining_capacity: 29,
            },
        });

        await fireEvent.click(container.querySelector('#table-monitoring-assignment-101 button[aria-haspopup="menu"]')!);
        await tick();
        await fireEvent.click(getByText('Transfer to Another Session'));
        await tick();

        expect(getByText('Batch-05')).toBeTruthy();
        const notesInput = getByLabelText('Reason notes') as HTMLTextAreaElement;
        await fireEvent.input(notesInput, { target: { value: 'Requested move to Batch-05 sitting' } });
        await tick();

        await fireEvent.click(getByText('Transfer to Batch-05'));
        await tick();

        expect(fetchMock).toHaveBeenCalledWith(
            '/exams/sessions/5/monitoring/accommodate',
            expect.objectContaining({
                method: 'POST',
                body: JSON.stringify({
                    reference_code: 'APP-2026-00001',
                    reason_category: 'travel_distance',
                    reason_notes: 'Requested move to Batch-05 sitting',
                }),
            }),
        );
        expect(mockRouterReload).toHaveBeenCalled();
        vi.unstubAllGlobals();
    });

    it('sorts roster by column header and supports bulk selecting examinees to transfer out via BulkActionBar', async () => {
        const fetchMock = vi.fn().mockResolvedValue({
            ok: true,
            headers: new Headers({ 'content-type': 'application/json' }),
            json: async () => ({ status: 'ok', count: 2 }),
        });
        vi.stubGlobal('fetch', fetchMock);

        const targetSessions = [
            {
                id: 5,
                code: 'Batch-05',
                status: 'published',
                date: '2026-10-19',
                start_time: '08:00',
                end_time: '09:30',
                room_name: 'ITBR 105',
                assigned_count: 1,
                capacity_limit: 40,
                remaining_capacity: 39,
            },
        ];

        const { container, getByText, getByLabelText } = render(Monitoring, {
            props: {
                session: { ...mockSession, status: 'published' },
                roster: mockRoster,
                can_manage: true,
                can_record: false,
                accommodate_candidates: [],
                transfer_target_sessions: targetSessions,
                session_remaining_capacity: 29,
            },
        });

        await fireEvent.click(getByLabelText('Sort by Name'));
        await tick();
        const sortedRows = Array.from(container.querySelectorAll('[id^="table-monitoring-assignment-"]'));
        expect(sortedRows[0]?.id).toBe('table-monitoring-assignment-103');

        await fireEvent.click(getByLabelText('Select all visible examinees'));
        await tick();

        const bulkTransferBtn = getByText('Transfer Selected (2)');
        expect(bulkTransferBtn).toBeTruthy();

        await fireEvent.click(bulkTransferBtn);
        await tick();

        const notesInput = getByLabelText('Reason notes') as HTMLTextAreaElement;
        await fireEvent.input(notesInput, { target: { value: 'Bulk transfer to Batch-05 sitting' } });
        await tick();

        await fireEvent.click(getByText('Transfer (2) to Batch-05'));
        await tick();

        expect(fetchMock).toHaveBeenCalledWith(
            '/exams/sessions/5/monitoring/accommodate',
            expect.objectContaining({
                method: 'POST',
                body: JSON.stringify({
                    reference_codes: ['APP-2026-00001', 'APP-2026-00003'],
                    reason_category: 'travel_distance',
                    reason_notes: 'Bulk transfer to Batch-05 sitting',
                }),
            }),
        );
        vi.unstubAllGlobals();
    });
    it('persists attendance while offline and replays the actor-scoped command after reconnect', async () => {
        Object.defineProperty(window.navigator, 'onLine', { configurable: true, value: false });
        const { container } = render(Monitoring, {
            props: {
                session: mockSession,
                roster: mockRoster.map((row) => ({
                    ...row,
                    attendance_id: null,
                    attendance_recorded_at: null,
                })),
                can_record: true,
                offline_actor_id: 77,
            },
        });
        await tick();
        const row = container.querySelector('#table-monitoring-assignment-101');
        const presentButton = Array.from(row?.querySelectorAll('button') ?? [])
            .find((button) => button.textContent?.includes('Present'));
        await fireEvent.click(presentButton!);
        await tick();

        expect(mockOfflineStore.records).toHaveLength(1);
        expect(mockOfflineStore.records[0]).toMatchObject({
            actorId: '77',
            sessionId: '28',
            assignmentId: 101,
            outcome: 'present',
            expectedOutcome: null,
            expectedAttendanceId: null,
            expectedRecordedAt: null,
            expectedStartedAt: mockSession.started_at,
            state: 'pending',
        });
        expect(container.querySelector('[role="status"]')?.textContent).toContain('1 attendance pending sync');
        expect(mockRouterPost).not.toHaveBeenCalled();

        const fetchMock = vi.fn(async (_url: string, init: RequestInit) => ({
            ok: true,
            status: 200,
            json: async () => ({ status: 'applied', attendance: { id: 501, outcome: 'present' } }),
            body: init.body,
        }));
        vi.stubGlobal('fetch', fetchMock);
        Object.defineProperty(window.navigator, 'onLine', { configurable: true, value: true });
        window.dispatchEvent(new Event('online'));
        await vi.waitFor(() => expect(mockOfflineStore.records).toHaveLength(0));

        expect(fetchMock).toHaveBeenCalledTimes(1);
        const replay = JSON.parse(String(fetchMock.mock.calls[0][1]?.body));
        expect(replay).toMatchObject({
            assignment_id: 101,
            expected_actor_id: '77',
            idempotency_key: expect.any(String),
            outcome: 'present',
            expected_outcome: null,
            expected_attendance_id: null,
            expected_recorded_at: null,
            expected_started_at: mockSession.started_at,
        });
        expect(mockOfflineStore.records).toHaveLength(0);
        expect(mockRouterReload).toHaveBeenCalledWith({
            only: ['roster', 'desks', 'session'],
            preserveScroll: true,
            preserveState: true,
        });
    });

    it('retains a semantic attendance conflict and blocks another offline mutation for that assignment', async () => {
        Object.defineProperty(window.navigator, 'onLine', { configurable: true, value: false });
        const { container } = render(Monitoring, {
            props: {
                session: mockSession,
                roster: mockRoster.map((row) => ({
                    ...row,
                    attendance_id: null,
                    attendance_recorded_at: null,
                })),
                can_record: true,
                offline_actor_id: 77,
            },
        });
        await tick();
        const row = container.querySelector('#table-monitoring-assignment-101');
        const presentButton = Array.from(row?.querySelectorAll('button') ?? [])
            .find((button) => button.textContent?.includes('Present'));
        await fireEvent.click(presentButton!);
        await tick();
        const fetchMock = vi.fn(async () => ({
            ok: false,
            status: 409,
            json: async () => ({ status: 'conflict', message: 'Attendance changed online.' }),
        }));
        vi.stubGlobal('fetch', fetchMock);
        Object.defineProperty(window.navigator, 'onLine', { configurable: true, value: true });
        window.dispatchEvent(new Event('online'));
        await vi.waitFor(() => expect(mockOfflineStore.records[0]?.state).toBe('conflict'));

        expect(mockOfflineStore.records[0]).toMatchObject({
            state: 'conflict',
            conflictMessage: 'Attendance changed online.',
        });
        expect(container.textContent).toContain('Conflict — review required');
        expect(container.textContent).toContain('1 attendance conflict');
        expect(row?.querySelector('button:disabled')).toBeTruthy();
        expect(fetchMock).toHaveBeenCalledTimes(1);
    });
    it('blocks server-only actions offline while leaving durable attendance enabled', () => {
        Object.defineProperty(window.navigator, 'onLine', { configurable: true, value: false });
        const { getByRole } = render(Monitoring, {
            props: {
                session: mockSession,
                roster: mockRoster,
                can_record: true,
                can_manage: true,
            },
        });

        expect((getByRole('button', { name: 'Scan Examinee QR (Door)' }) as HTMLButtonElement).disabled).toBe(true);
        expect((getByRole('button', { name: 'Accommodate Walk-in / Transfer' }) as HTMLButtonElement).disabled).toBe(true);
        expect((getByRole('button', { name: 'Close Room' }) as HTMLButtonElement).disabled).toBe(true);
        const present = document.querySelector('#table-monitoring-assignment-101 button') as HTMLButtonElement;
        expect(present.disabled).toBe(false);
    });

    it('does not claim offline durability when browser storage is unavailable', async () => {
        mockOfflineStore.available = false;
        Object.defineProperty(window.navigator, 'onLine', { configurable: true, value: false });
        const { container } = render(Monitoring, {
            props: {
                session: mockSession,
                roster: mockRoster,
                can_record: true,
                offline_actor_id: 77,
            },
        });
        const present = container.querySelector('#table-monitoring-assignment-101 button');

        expect(container.textContent).toContain('Durable offline attendance unavailable');
        expect((present as HTMLButtonElement).disabled).toBe(true);
        await fireEvent.click(present!);
        expect(mockOfflineStore.records).toHaveLength(0);
        expect(mockRouterPost).not.toHaveBeenCalled();
    });
    it('reports an IndexedDB write failure instead of claiming attendance was saved', async () => {
        mockOfflineStore.failQueue = true;
        Object.defineProperty(window.navigator, 'onLine', { configurable: true, value: false });
        const { container } = render(Monitoring, {
            props: {
                session: mockSession,
                roster: mockRoster,
                can_record: true,
                offline_actor_id: 77,
            },
        });
        const present = container.querySelector('#table-monitoring-assignment-101 button');
        await fireEvent.click(present!);

        await vi.waitFor(() => expect(container.textContent).toContain('QuotaExceededError'));
        expect(mockOfflineStore.records).toHaveLength(0);
        expect(container.textContent).not.toContain('1 attendance pending sync');
    });

    it('retains pending attendance and explains when replay authorization expires', async () => {
        Object.defineProperty(window.navigator, 'onLine', { configurable: true, value: false });
        const { container } = render(Monitoring, {
            props: {
                session: mockSession,
                roster: mockRoster,
                can_record: true,
                offline_actor_id: 77,
            },
        });
        const present = container.querySelector('#table-monitoring-assignment-101 button');
        await fireEvent.click(present!);
        await vi.waitFor(() => expect(mockOfflineStore.records).toHaveLength(1));
        const fetchMock = vi.fn(async () => ({
            ok: false,
            status: 403,
            json: async () => ({ message: 'Forbidden.' }),
        }));
        vi.stubGlobal('fetch', fetchMock);
        Object.defineProperty(window.navigator, 'onLine', { configurable: true, value: true });
        window.dispatchEvent(new Event('online'));

        await vi.waitFor(() => expect(container.textContent).toContain('Pending attendance is preserved.'));
        expect(mockOfflineStore.records).toHaveLength(1);
        expect(mockOfflineStore.records[0].state).toBe('pending');
        expect(container.textContent).toContain('1 attendance pending sync');
        expect(fetchMock).toHaveBeenCalledTimes(1);
    });
});
