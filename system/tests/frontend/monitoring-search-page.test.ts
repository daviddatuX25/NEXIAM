// @vitest-environment jsdom

import { cleanup, fireEvent, render, waitFor, within } from '@testing-library/svelte';
import { tick } from 'svelte';
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';

const mockRouterGet = vi.fn();
const mockRouterVisit = vi.fn();
const mockRouterListeners = new Map<string, (event: unknown) => void>();
const mockRouterOn = vi.fn(
    (eventName: string, handler: (event: unknown) => void) => {
        mockRouterListeners.set(eventName, handler);
        return () => {
            if (mockRouterListeners.get(eventName) === handler) {
                mockRouterListeners.delete(eventName);
            }
        };
    },
);
const mockPollStart = vi.fn();
const mockPollStop = vi.fn();
const mockPollCalls: Array<{ interval: number; requestOptions: unknown }> = [];
const mockUsePoll = vi.fn((interval: number, requestOptions: unknown = {}) => {
    mockPollCalls.push({ interval, requestOptions });
    return { start: mockPollStart, stop: mockPollStop };
});
const mockPage = {
    props: {
        auth: {
            user: {
                id: 41,
                name: 'Monitoring Staff',
                office: 'guidance',
                roles: [],
                capabilities: [],
            },
        },
        practice_workspace: { id: 73 },
        staff_navigation: [],
        notifications: { unread_count: 0, recent: [] },
        xiam: { enabled: false },
    },
    url: '/exams/monitoring',
};

vi.mock('@inertiajs/svelte', async (importOriginal) => {
    const actual = await importOriginal<Record<string, unknown>>();
    const actualRouter = (actual.router as Record<string, unknown>) ?? {};

    return {
        ...actual,
        usePage: () => mockPage,
        usePoll: (...args: [number, unknown?, unknown?]) =>
            mockUsePoll(args[0], args[1]),
        router: {
            ...actualRouter,
            on: (...args: unknown[]) => mockRouterOn(...args),
            get: (...args: unknown[]) => mockRouterGet(...args),
            visit: (...args: unknown[]) => mockRouterVisit(...args),
        },
    };
});

import MonitoringIndex from '../../resources/js/Pages/Examination/MonitoringIndex.svelte';

type FilterVisitOptions = {
    onSuccess: (page: { props: Record<string, unknown> }) => void;
    onError: (errors?: Record<string, unknown>) => void;
    onCancel: () => void;
    onFinish: (visit?: unknown) => void;
};

type Suggestion =
    | {
          key: string;
          kind: 'session';
          session_code: string;
          room_label: string;
          session_status: string;
          starts_at?: string;
          destination: { url: string; focus_kind: null; focus_id: null };
      }
    | {
          key: string;
          kind: 'examinee';
          session_code: string;
          room_label: string;
          session_status: string;
          starts_at?: string;
          name: string;
          reference_code: string;
          destination: {
              url: string;
              focus_kind: 'assignment';
              focus_id: number;
          };
      };

function monitoringProps(search = 'APPLIED-42') {
    const session = {
        id: 14,
        code: 'SESSION-14',
        status: 'published',
        date: '2026-10-08',
        start_time: null,
        end_time: null,
        assigned_count: 3,
        room: { id: 22, code: 'R-22', name: 'Room 22', capacity: 30 },
        proctors: [],
    };

    return {
        page_state: 'ready',
        sessions: [session],
        active_sessions: [session],
        history_sessions: {
            data: [],
            total: 0,
            current_page: 1,
            last_page: 1,
            from: 0,
            to: 0,
        },
        filters: { search, room_id: null, date: '', tab: 'active' },
        rooms: [{ id: 22, code: 'R-22', name: 'Room 22' }],
        can_manage: true,
        telemetry: null,
        active_academic_cycle: { id: 9, code: '2026-2027', name: '2026–2027' },
        thresholds: {},
    };
}

function renderMonitoring(search?: string) {
    return render(MonitoringIndex, { props: monitoringProps(search) });
}

function pollRequestOptions(): Record<string, unknown> {
    const call = mockPollCalls.find(({ interval }) => interval === 15000);
    if (!call) {
        throw new Error('Monitoring polling was not initialized.');
    }

    return typeof call.requestOptions === 'function'
        ? (call.requestOptions as () => Record<string, unknown>)()
        : (call.requestOptions as Record<string, unknown>);
}

beforeEach(() => {
    mockRouterGet.mockReset();
    mockRouterVisit.mockReset();
    mockRouterOn
        .mockReset()
        .mockImplementation(
            (eventName: string, handler: (event: unknown) => void) => {
                mockRouterListeners.set(eventName, handler);
                return () => {
                    if (mockRouterListeners.get(eventName) === handler) {
                        mockRouterListeners.delete(eventName);
                    }
                };
            },
        );
    mockRouterListeners.clear();
    mockPollStart.mockReset();
    mockPollStop.mockReset();
    mockPage.url = '/exams/monitoring';
    window.history.replaceState(window.history.state, '', '/exams/monitoring');

    mockUsePoll.mockClear();
    mockPollCalls.length = 0;
});

afterEach(() => {
    cleanup();
    vi.unstubAllGlobals();
    vi.useRealTimers();
});

describe('monitoring search draft and accepted filter', () => {
    it('keeps rows static while typing, cancels only its owned poll, and uses suggestions without a hub GET', async () => {
        const fetchMock = vi.fn(
            async () =>
                ({
                    ok: true,
                    status: 200,
                    json: async () => ({ items: [], matches_truncated: false }),
                }) as Response,
        );
        vi.stubGlobal('fetch', fetchMock);
        const view = renderMonitoring('APPLIED-42');
        const input = view.getByRole('combobox', {
            name: 'Search',
        }) as HTMLInputElement;
        const cancel = vi.fn();
        const pollOptions = pollRequestOptions();
        (pollOptions.onCancelToken as (token: { cancel: () => void }) => void)({
            cancel,
        });

        await fireEvent.focus(input);
        await fireEvent.input(input, { target: { value: 'new draft' } });
        await waitFor(() => expect(fetchMock).toHaveBeenCalledOnce());

        expect(mockRouterGet).not.toHaveBeenCalled();
        expect(cancel).toHaveBeenCalledOnce();
        expect(mockPollStop).toHaveBeenCalled();
        expect(input.value).toBe('new draft');
        expect(view.getAllByText('SESSION-14').length).toBeGreaterThan(0);
        expect(view.getByText(/Currently showing “APPLIED-42”/)).toBeTruthy();
        expect(view.getByRole('button', { name: 'Reset' })).toBeTruthy();
    });

    it('fences queued poll rows at application time but accepts security transitions', async () => {
        const props = monitoringProps('APPLIED-42');
        const view = render(MonitoringIndex, { props });
        const input = view.getByRole('combobox', {
            name: 'Search',
        });
        const pollOptions = pollRequestOptions();
        const cancel = vi.fn();
        (pollOptions.onCancelToken as (token: { cancel: () => void }) => void)({
            cancel,
        });

        await fireEvent.focus(input);
        expect(cancel).toHaveBeenCalledOnce();

        const queuedRows = {
            component: 'Examination/MonitoringIndex',
            props: {
                page_state: 'ready',
                sessions: [],
                active_sessions: [],
                history_sessions: {
                    data: [],
                    total: 0,
                    current_page: 1,
                    last_page: 1,
                    from: 0,
                    to: 0,
                },
            },
        };
        (pollOptions.onBeforeUpdate as (page: typeof queuedRows) => void)(
            queuedRows,
        );

        expect(queuedRows.props.sessions).toBe(props.sessions);
        expect(queuedRows.props.active_sessions).toBe(props.active_sessions);
        expect(queuedRows.props.history_sessions).toBe(props.history_sessions);
        expect(view.getAllByText('SESSION-14').length).toBeGreaterThan(0);

        const deniedPage = {
            component: 'Examination/MonitoringIndex',
            props: {
                page_state: 'denied',
                sessions: [],
                active_sessions: [],
                history_sessions: [],
            },
        };
        (pollOptions.onBeforeUpdate as (page: typeof deniedPage) => void)(
            deniedPage,
        );
        expect(deniedPage.props).toMatchObject({
            page_state: 'denied',
            sessions: [],
            active_sessions: [],
        });

        const sessionInvalidPage = {
            component: 'Auth/Login',
            props: {
                page_state: 'ready',
                errors: { email: 'Your session has expired.' },
            },
        };
        (
            pollOptions.onBeforeUpdate as (
                page: typeof sessionInvalidPage,
            ) => void
        )(sessionInvalidPage);
        expect(sessionInvalidPage).toMatchObject({
            component: 'Auth/Login',
            props: { errors: { email: 'Your session has expired.' } },
        });
    });

    it('preserves the accepted rows and URL when a queued poll is applied', async () => {
        const props = monitoringProps('APPLIED-42');
        const view = render(MonitoringIndex, { props });
        const input = view.getByRole('combobox', {
            name: 'Search',
        }) as HTMLInputElement;
        const pollOptions = pollRequestOptions();
        (pollOptions.onCancelToken as (token: { cancel: () => void }) => void)({
            cancel: vi.fn(),
        });

        mockPage.url = '/exams/monitoring?search=APPLIED-42&tab=active';
        window.history.replaceState(
            window.history.state,
            '',
            '/exams/monitoring?search=APPLIED-42&tab=active',
        );
        await fireEvent.focus(input);
        await fireEvent.input(input, {
            target: { value: 'COMMITTED-77' },
        });
        await fireEvent.keyDown(input, { key: 'Enter' });

        const acceptedSession = { ...props.sessions[0], code: 'SESSION-27' };
        const acceptedProps = {
            ...props,
            sessions: [acceptedSession],
            active_sessions: [acceptedSession],
            history_sessions: {
                ...props.history_sessions,
                data: [acceptedSession],
                total: 1,
            },
            filters: { ...props.filters, search: 'COMMITTED-77' },
        };
        const acceptedUrl = '/exams/monitoring?search=COMMITTED-77&tab=active';
        mockPage.url = acceptedUrl;
        window.history.replaceState(window.history.state, '', acceptedUrl);
        await view.rerender(acceptedProps);

        const filterCallbacks = mockRouterGet.mock
            .calls[0][2] as FilterVisitOptions;
        filterCallbacks.onSuccess({ props: acceptedProps });
        filterCallbacks.onFinish();
        await tick();

        expect(view.getAllByText('SESSION-27').length).toBeGreaterThan(0);

        const queuedPage = {
            component: 'Examination/MonitoringIndex',
            url: '/exams/monitoring?search=APPLIED-42&tab=active',
            props: {
                page_state: 'ready',
                sessions: [],
                active_sessions: [],
                history_sessions: [],
            },
        };
        (pollOptions.onBeforeUpdate as (page: typeof queuedPage) => void)(
            queuedPage,
        );

        expect(queuedPage.props.sessions).toBe(acceptedProps.sessions);
        expect(queuedPage.props.active_sessions).toBe(
            acceptedProps.active_sessions,
        );
        expect(queuedPage.props.history_sessions).toBe(
            acceptedProps.history_sessions,
        );

        // Inertia applies the response URL to browser history before polling again.
        mockPage.url = queuedPage.url;
        window.history.replaceState(window.history.state, '', queuedPage.url);
        const nextPollUrls: string[] = [];
        mockPollStart.mockImplementationOnce(() => {
            nextPollUrls.push(window.location.href);
        });
        await fireEvent.blur(input);
        expect(nextPollUrls).toEqual([
            new URL(acceptedUrl, window.location.origin).href,
        ]);
        expect(window.location.pathname + window.location.search).toBe(
            acceptedUrl,
        );
        expect(view.getAllByText('SESSION-27').length).toBeGreaterThan(0);
    });

    it('keeps a dirty draft out of room, date, and tab changes and resets the history paginator', async () => {
        const suggestionRequests: URL[] = [];
        vi.stubGlobal(
            'fetch',
            vi.fn(async (input: RequestInfo | URL) => {
                suggestionRequests.push(
                    new URL(String(input), window.location.origin),
                );
                return {
                    ok: true,
                    status: 200,
                    json: async () => ({ items: [], matches_truncated: false }),
                } as Response;
            }),
        );
        const view = renderMonitoring('APPLIED-42');
        const input = view.getByRole('combobox', {
            name: 'Search',
        }) as HTMLInputElement;
        await fireEvent.focus(input);
        await fireEvent.input(input, { target: { value: 'unfinished' } });
        await fireEvent.click(
            view.getByRole('button', { name: /Conduct History/ }),
        );
        expect(mockRouterGet).not.toHaveBeenCalled();

        const roomSelect = view.container.querySelector(
            'select',
        ) as HTMLSelectElement;
        await fireEvent.change(roomSelect, { target: { value: '22' } });
        expect(mockRouterGet).toHaveBeenCalledOnce();
        const roomVisit = mockRouterGet.mock.calls[0];
        const roomData = roomVisit[1] as Record<string, unknown>;
        const roomCallbacks = roomVisit[2] as FilterVisitOptions;
        expect(roomData).toMatchObject({
            search: 'APPLIED-42',
            room_id: '22',
            tab: 'history',
            history_page: 1,
        });
        expect(roomData.search).not.toBe('unfinished');

        roomCallbacks.onSuccess({
            props: {
                page_state: 'ready',
                filters: {
                    search: 'APPLIED-42',
                    room_id: 22,
                    date: '',
                    tab: 'history',
                },
            },
        });
        roomCallbacks.onFinish();
        await tick();
        expect(input.value).toBe('unfinished');
        expect(view.getByText(/Currently showing “APPLIED-42”/)).toBeTruthy();
        expect(roomSelect.value).toBe('22');

        const dateInput = view.container.querySelector(
            'input[type=\"date\"]',
        ) as HTMLInputElement;
        await fireEvent.change(dateInput, { target: { value: '2026-10-02' } });
        expect(mockRouterGet).toHaveBeenCalledTimes(2);
        const dateVisit = mockRouterGet.mock.calls[1];
        const dateData = dateVisit[1] as Record<string, unknown>;
        expect(dateData).toMatchObject({
            search: 'APPLIED-42',
            room_id: '22',
            date: '2026-10-02',
            tab: 'history',
            history_page: 1,
        });
        expect(dateData.search).not.toBe('unfinished');
        const dateCallbacks = dateVisit[2] as FilterVisitOptions;
        dateCallbacks.onSuccess({
            props: {
                page_state: 'ready',
                filters: {
                    search: 'APPLIED-42',
                    room_id: 22,
                    date: '2026-10-02',
                    tab: 'history',
                },
            },
        });
        dateCallbacks.onFinish();
        await tick();
        await fireEvent.blur(input);
        await fireEvent.focus(input);
        await waitFor(() =>
            expect(
                suggestionRequests.some(
                    (url) =>
                        url.searchParams.get('q') === 'unfinished' &&
                        url.searchParams.get('tab') === 'history' &&
                        url.searchParams.get('date') === '2026-10-02' &&
                        url.searchParams.get('room_id') === '22',
                ),
            ).toBe(true),
        );
    });

    it('commits free text immediately and keeps a newer draft after the accepted response', async () => {
        const view = renderMonitoring('APPLIED-42');
        const input = view.getByRole('combobox', {
            name: 'Search',
        }) as HTMLInputElement;

        await fireEvent.focus(input);
        await fireEvent.input(input, { target: { value: 'x' } });
        await fireEvent.keyDown(input, { key: 'Enter' });

        expect(mockRouterGet).toHaveBeenCalledOnce();
        const filterVisit = mockRouterGet.mock.calls[0];
        const filterData = filterVisit[1] as Record<string, unknown>;
        const filterCallbacks = filterVisit[2] as FilterVisitOptions;
        expect(filterData).toMatchObject({ search: 'x', history_page: 1 });
        expect(mockRouterVisit).not.toHaveBeenCalled();

        await fireEvent.input(input, { target: { value: 'newer draft' } });
        filterCallbacks.onSuccess({
            props: {
                page_state: 'ready',
                filters: {
                    search: 'x',
                    room_id: null,
                    date: '',
                    tab: 'active',
                },
            },
        });
        filterCallbacks.onFinish();
        await tick();

        expect(input.value).toBe('newer draft');
        expect(view.getByText(/Currently showing “x”/)).toBeTruthy();
    });

    it('clears search only, keeps other filters, and leaves accepted state unchanged after cancellation', async () => {
        const baseProps = monitoringProps('APPLIED-42');
        const props = {
            ...baseProps,
            filters: { ...baseProps.filters, room_id: 22, date: '2026-10-03' },
        };
        const view = render(MonitoringIndex, { props });
        const input = view.getByRole('combobox', {
            name: 'Search',
        }) as HTMLInputElement;

        await fireEvent.click(
            view.getByRole('button', { name: 'Clear search' }),
        );
        expect(input.value).toBe('');
        expect(document.activeElement).toBe(input);
        expect(mockRouterGet).toHaveBeenCalledOnce();
        const clearVisit = mockRouterGet.mock.calls[0];
        const clearData = clearVisit[1] as Record<string, unknown>;
        const clearCallbacks = clearVisit[2] as FilterVisitOptions;
        expect(clearData).toMatchObject({
            room_id: '22',
            date: '2026-10-03',
            history_page: 1,
        });
        expect(clearData.search).toBeUndefined();

        clearCallbacks.onCancel();
        clearCallbacks.onFinish();
        await tick();
        expect(view.getByRole('button', { name: 'Clear search' })).toBeTruthy();
        expect(view.getByText(/Currently showing “APPLIED-42”/)).toBeTruthy();

        await fireEvent.click(view.getByRole('button', { name: 'Reset' }));
        expect(mockRouterGet).toHaveBeenCalledTimes(2);
        const resetData = mockRouterGet.mock.calls[1][1] as Record<
            string,
            unknown
        >;
        expect(resetData).toMatchObject({ tab: 'active', history_page: 1 });
        expect(resetData.search).toBeUndefined();
        expect(resetData.room_id).toBeUndefined();
        expect(resetData.date).toBeUndefined();
    });

    it('restores the input draft and accepted filters on browser history navigation', async () => {
        const view = renderMonitoring('APPLIED-42');
        const input = view.getByRole('combobox', {
            name: 'Search',
        }) as HTMLInputElement;
        await fireEvent.input(input, { target: { value: 'unsubmitted text' } });

        window.dispatchEvent(new PopStateEvent('popstate'));
        const navigateListener = mockRouterListeners.get('navigate');
        expect(navigateListener).toBeDefined();
        navigateListener?.({
            detail: {
                page: {
                    component: 'Examination/MonitoringIndex',
                    props: {
                        filters: {
                            search: 'RESTORED-9',
                            room_id: 22,
                            date: '2026-10-01',
                            tab: 'history',
                        },
                    },
                },
            },
        });
        await tick();
        expect(input.value).toBe('RESTORED-9');
        expect(view.getByText('Filtered: “RESTORED-9”')).toBeTruthy();
        expect(
            (
                view.container.querySelector(
                    'input[type=\"date\"]',
                ) as HTMLInputElement
            ).value,
        ).toBe('2026-10-01');
    });

    it('filters the current hub for a session suggestion and navigates only for an examinee suggestion', async () => {
        const sessionSuggestion: Suggestion = {
            key: 'session:14',
            kind: 'session',
            session_code: 'SESSION-204',
            room_label: 'Room 22',
            session_status: 'published',
            starts_at: '2026-10-08T09:00:00Z',
            destination: {
                url: '/exams/sessions/14/monitoring',
                focus_kind: null,
                focus_id: null,
            },
        };
        const fetchMock = vi.fn(
            async () =>
                ({
                    ok: true,
                    status: 200,
                    json: async () => ({
                        items: [sessionSuggestion],
                        matches_truncated: false,
                    }),
                }) as Response,
        );
        vi.stubGlobal('fetch', fetchMock);
        const sessionView = renderMonitoring('');
        const sessionInput = sessionView.getByRole('combobox', {
            name: 'Search',
        }) as HTMLInputElement;
        await fireEvent.focus(sessionInput);
        await fireEvent.input(sessionInput, { target: { value: 'SESSION' } });
        const sessionOption = await waitFor(() =>
            sessionView.getByRole('option', { name: /SESSION-204/ }),
        );
        await fireEvent.click(sessionOption);

        expect(mockRouterGet).toHaveBeenCalledOnce();
        const sessionData = mockRouterGet.mock.calls[0][1] as Record<
            string,
            unknown
        >;
        expect(sessionData).toMatchObject({
            search: 'SESSION-204',
            history_page: 1,
        });
        expect(mockRouterVisit).not.toHaveBeenCalled();
        cleanup();
        mockRouterGet.mockReset();
        mockRouterVisit.mockReset();
        mockPollCalls.length = 0;
        mockUsePoll.mockClear();

        const examineeSuggestion: Suggestion = {
            key: 'examinee:14:93',
            kind: 'examinee',
            session_code: 'SESSION-205',
            room_label: 'Room 22',
            session_status: 'in_progress',
            starts_at: '2026-10-08T09:00:00Z',
            name: 'Example Examinee',
            reference_code: 'REF-93',
            destination: {
                url: '/exams/sessions/14/monitoring?focus_assignment_id=93',
                focus_kind: 'assignment',
                focus_id: 93,
            },
        };
        vi.stubGlobal(
            'fetch',
            vi.fn(
                async () =>
                    ({
                        ok: true,
                        status: 200,
                        json: async () => ({
                            items: [examineeSuggestion],
                            matches_truncated: false,
                        }),
                    }) as Response,
            ),
        );
        const examineeView = renderMonitoring('');
        const examineeInput = examineeView.getByRole('combobox', {
            name: 'Search',
        }) as HTMLInputElement;
        await fireEvent.focus(examineeInput);
        await fireEvent.input(examineeInput, { target: { value: 'Example' } });
        const examineeOption = await waitFor(() =>
            examineeView.getByRole('option', { name: /Example Examinee/ }),
        );
        await fireEvent.click(examineeOption);

        expect(mockRouterVisit).toHaveBeenCalledOnce();
        expect(mockRouterVisit).toHaveBeenCalledWith(
            '/exams/sessions/14/monitoring?focus_assignment_id=93',
            { preserveState: false, preserveScroll: false },
        );
        expect(mockRouterGet).not.toHaveBeenCalled();
    });
    it('labels Search and stays on Active Conduct when only history is available', async () => {
        const baseProps = monitoringProps('');
        const historicalSession = {
            ...baseProps.sessions[0],
            id: 19,
            code: 'HISTORY-19',
            status: 'completed',
        };
        const props = {
            ...baseProps,
            sessions: [],
            active_sessions: [],
            history_sessions: {
                ...baseProps.history_sessions,
                data: [historicalSession],
                total: 1,
            },
            filters: { search: '', room_id: null, date: '', tab: '' },
        };
        const view = render(MonitoringIndex, { props });
        const search = view.getByRole('combobox', { name: 'Search' });
        expect(view.getByLabelText('Search', { selector: 'input' })).toBe(search);

        expect(view.queryByText('HISTORY-19')).toBeNull();
        const historyTab = view.getByRole('button', { name: /^Conduct History/ });

        await fireEvent.click(historyTab);
        expect(view.getAllByText('HISTORY-19').length).toBeGreaterThan(0);
    });

    it('shows attendance summaries without submission statuses on the monitoring hub', async () => {
        const baseProps = monitoringProps('');
        const activeSession = {
            ...baseProps.sessions[0],
            present_count: 1,
            absent_count: 0,
            assigned_count: 1,
        };
        const historicalSession = {
            ...activeSession,
            id: 19,
            code: 'HISTORY-19',
            status: 'completed',
            submitted_count: 1,
            submission_status: 'complete',
            turnout_percent: 100,
        };
        const view = render(MonitoringIndex, {
            props: {
                ...baseProps,
                sessions: [activeSession],
                active_sessions: [activeSession],
                history_sessions: {
                    ...baseProps.history_sessions,
                    data: [historicalSession],
                    total: 1,
                },
                telemetry: {
                    is_live: false,
                    active_rooms: 0,
                    total_rooms: 1,
                    active_sessions: 0,
                    completed_sessions: 1,
                    total_scheduled: 1,
                    conduct_scheduled: 1,
                    present_count: 1,
                    absent_count: 0,
                    submitted_count: 1,
                    void_count: 0,
                },
                filters: { ...baseProps.filters, tab: 'active' },
            },
        });

        expect(view.container.textContent).toContain('Present');
        expect(view.container.textContent).toContain('Absent');
        expect(view.container.textContent).not.toMatch(/submission/i);

        await fireEvent.click(
            view.getByRole('button', { name: /^Conduct History/ }),
        );

        expect(view.container.textContent).toContain('HISTORY-19');
        expect(view.container.textContent).not.toMatch(/submission/i);
    });

    it.each([
        ['active', 'assigned count', 'assigned_count'],
        ['history', 'turnout count', 'present_count'],
    ])('sorts %s %s as numbers in both directions without a filter request', async (tab, label, field) => {
        const props = monitoringProps('');
        const base = props.sessions[0];
        const sessions = [
            { ...base, id: 14, code: 'LOW-COUNT', status: tab === 'history' ? 'completed' : 'published', [field]: 2 },
            { ...base, id: 15, code: 'HIGH-COUNT', status: tab === 'history' ? 'completed' : 'published', [field]: 10 },
        ];
        const view = render(MonitoringIndex, {
            props: {
                ...props,
                sessions,
                active_sessions: sessions,
                history_sessions: { ...props.history_sessions, data: sessions, total: 2 },
                filters: { ...props.filters, tab },
            },
        });
        const table = view.container.querySelector('table')!;
        const header = within(table).getByRole('button', { name: `Sort by ${label.toLowerCase()}` });
        await fireEvent.click(header);
        let rows = within(table).getAllByRole('row').slice(1);
        expect(rows[0].textContent).toContain('LOW-COUNT');
        expect(header.closest('th')?.getAttribute('aria-sort')).toBe('ascending');
        await fireEvent.click(header);
        rows = within(table).getAllByRole('row').slice(1);
        expect(rows[0].textContent).toContain('HIGH-COUNT');
        expect(header.closest('th')?.getAttribute('aria-sort')).toBe('descending');
        expect(mockRouterGet).not.toHaveBeenCalled();
    });
    it('marks the hub data stale and suppresses live indicators when the browser goes offline', async () => {
        const props = monitoringProps('');
        const activeSession = {
            ...props.sessions[0],
            status: 'in_progress',
            present_count: 2,
            absent_count: 0,
            turnout_percent: 67,
        };
        const view = render(MonitoringIndex, {
            props: {
                ...props,
                sessions: [activeSession],
                active_sessions: [activeSession],
                telemetry: {
                    is_live: true,
                    active_rooms: 1,
                    total_rooms: 1,
                    total_scheduled: 3,
                    conduct_scheduled: 3,
                    present_count: 2,
                    absent_count: 0,
                },
            },
        });

        window.dispatchEvent(new Event('offline'));
        await tick();

        expect(view.container.textContent).toContain('OFFLINE · DATA STALE');
        expect(view.container.textContent).toContain('No network connection — monitoring data may be stale');
        expect(view.container.textContent).toContain('STALE — last seen live');
        expect(view.container.querySelector('[class*="motion-safe:animate-pulse"]')).toBeNull();
        expect(mockPollStop).toHaveBeenCalled();
    });
});
