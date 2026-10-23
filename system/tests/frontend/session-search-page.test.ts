// @vitest-environment jsdom

import {
    cleanup,
    fireEvent,
    render,
    waitFor,
    within,
} from '@testing-library/svelte';
import { tick } from 'svelte';
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';

const mocks = vi.hoisted(() => ({
    routerVisit: vi.fn(),
    routerGet: vi.fn(),
    routerPost: vi.fn(),
    routerOn: vi.fn(),
}));

vi.mock('@inertiajs/svelte', async (importOriginal) => {
    const actual = await importOriginal<Record<string, unknown>>();
    const actualRouter = (actual.router as Record<string, unknown>) ?? {};

    return {
        ...actual,
        usePage: () => ({
            url: '/exams/sessions',
            props: {
                auth: { user: { id: 17 } },
                practice_workspace: null,
            },
        }),
        router: {
            ...actualRouter,
            visit: (...args: unknown[]) => mocks.routerVisit(...args),
            get: (...args: unknown[]) => mocks.routerGet(...args),
            post: (...args: unknown[]) => mocks.routerPost(...args),
            on: (...args: unknown[]) => mocks.routerOn(...args),
        },
    };
});

import ExaminationIndex from '../../resources/js/Pages/Examination/Index.svelte';

type PageResponse = {
    url?: string;
    props: {
        filters?: Record<string, string | number | null | undefined>;
        selected_cycle_id?: number | null;
        suggestion_cycle_id?: number | null;
    };
};

type VisitOptions = {
    method?: string;
    data?: Record<string, unknown>;
    preserveState?: boolean;
    preserveScroll?: boolean;
    replace?: boolean;
    onCancelToken?: (token: { cancel: () => void }) => void;
    onSuccess?: (page: PageResponse) => void;
    onError?: (errors?: unknown) => void;
    onHttpException?: (response?: unknown) => false | void;
    onNetworkError?: (error?: unknown) => false | void;
    onCancel?: () => void;
    onFinish?: () => void;
};

type RecordedVisit = {
    url: string;
    options: VisitOptions;
    cancel: () => void;
};

const visits: RecordedVisit[] = [];
let navigationListener:
    ((event: { detail: { page: PageResponse } }) => void) | undefined;

const sessionSuggestion = {
    key: 'session:31',
    kind: 'session',
    assignment_id: null,
    attempt_id: null,
    reference_code: null,
    name: null,
    exam_session_id: 31,
    session_code: 'EXAM-S31',
    session_status: 'published',
    room_label: 'Science Hall',
    starts_at: '2026-10-08T09:00:00Z',
    ends_at: null,
    grading_session_id: null,
    matched_fields: ['room'],
    destination: {
        url: '/exams/sessions/monitoring/31',
        focus_kind: null,
        focus_id: null,
    },
};

const examineeSuggestion = {
    key: 'examinee:44',
    kind: 'examinee',
    assignment_id: 44,
    attempt_id: null,
    reference_code: '2026-0044',
    name: 'Avery Santos',
    exam_session_id: 31,
    session_code: 'EXAM-S31',
    session_status: 'published',
    room_label: 'Science Hall',
    starts_at: '2026-10-08T09:00:00Z',
    ends_at: null,
    grading_session_id: null,
    matched_fields: ['name'],
    destination: {
        url: '/exams/sessions/monitoring/31?focus_assignment_id=44',
        focus_kind: 'assignment',
        focus_id: 44,
    },
};

function pageProps(overrides: Record<string, unknown> = {}) {
    return {
        page_state: 'ready',
        sessions: [
            {
                id: 31,
                code: 'EXAM-S31',
                date: '2026-10-08',
                start_time: '09:00',
                end_time: '11:00',
                room: {
                    name: 'Science Hall',
                    code: 'SH',
                    building: 'North',
                    capacity: 24,
                },
                capacity: 24,
                assigned_count: 8,
                status: 'published',
                booking_mode: 'staff_only',
            },
        ],
        filters: { search: '', status: '', academic_cycle_id: 8 },
        selected_cycle_id: 8,
        statuses: [
            { value: 'draft', label: 'Draft' },
            { value: 'published', label: 'Published' },
            { value: 'completed', label: 'Completed' },
        ],
        cycles: [{ id: 8, label: '2026–2027' }],
        distribution: { active_cycle_id: 8 },
        suggestion_cycle_id: 8,
        ...overrides,
    };
}

function mockSuggestionFetch(items: unknown[]) {
    const fetchMock = vi.fn().mockResolvedValue({
        ok: true,
        json: async () => ({ items, matches_truncated: false }),
    });
    vi.stubGlobal('fetch', fetchMock);

    return fetchMock;
}

function inputFor(view: { container: HTMLElement }): HTMLInputElement {
    return within(view.container).getByRole('combobox', {
        name: 'Search',
    }) as HTMLInputElement;
}

async function startVisit(
    options: VisitOptions,
    filters = { search: 'accepted', status: 'draft', academic_cycle_id: 8 },
): Promise<void> {
    options.onSuccess?.({
        url: `/exams/sessions?search=${filters.search}`,
        props: { filters },
    });
    options.onFinish?.();
    await tick();
}

beforeEach(() => {
    visits.length = 0;
    navigationListener = undefined;
    mocks.routerVisit.mockReset();
    mocks.routerGet.mockReset();
    mocks.routerPost.mockReset();
    mocks.routerOn.mockReset();
    mocks.routerVisit.mockImplementation((rawUrl, rawOptions) => {
        const url = String(rawUrl);
        const options = rawOptions as VisitOptions;
        const cancel = vi.fn(() => {
            options.onCancel?.();
            options.onFinish?.();
        });
        options.onCancelToken?.({ cancel });
        visits.push({ url, options, cancel });
    });
    mocks.routerOn.mockImplementation((rawEvent, rawListener) => {
        if (rawEvent === 'navigate') {
            navigationListener = rawListener as (event: {
                detail: { page: PageResponse };
            }) => void;
        }

        return () => {
            navigationListener = undefined;
        };
    });
});

afterEach(() => {
    cleanup();
    vi.useRealTimers();
    vi.unstubAllGlobals();
    vi.restoreAllMocks();
});

describe('Examination scheduling search', () => {
    it('keeps typing as a draft and loads suggestions without a hub visit', async () => {
        const fetchMock = mockSuggestionFetch([sessionSuggestion]);
        const view = render(ExaminationIndex, { props: pageProps() });
        const input = inputFor(view);

        await fireEvent.input(input, { target: { value: 'EXAM' } });

        expect(input.value).toBe('EXAM');
        expect(mocks.routerVisit).not.toHaveBeenCalled();
        expect(mocks.routerGet).not.toHaveBeenCalled();
        expect(
            view.getByText(
                /current list still reflects the applied filters without a search/i,
            ),
        ).toBeTruthy();

        await waitFor(() => expect(fetchMock).toHaveBeenCalledTimes(1), {
            timeout: 1500,
        });
        expect(String(fetchMock.mock.calls[0]?.[0])).toContain(
            '/exams/search-suggestions',
        );
        expect(mocks.routerVisit).not.toHaveBeenCalled();
        expect(
            await view.findByRole('option', { name: /EXAM-S31/ }),
        ).toBeTruthy();
    });
    it('uses only the server-resolved default cycle when no cycle is selected', async () => {
        const fetchMock = mockSuggestionFetch([sessionSuggestion]);
        const view = render(ExaminationIndex, {
            props: pageProps({
                selected_cycle_id: null,
                suggestion_cycle_id: null,
                distribution: { active_cycle_id: 91 },
                filters: {
                    search: '',
                    status: 'draft',
                    academic_cycle_id: null,
                },
            }),
        });

        await fireEvent.input(inputFor(view), { target: { value: 'EXAM' } });
        expect(fetchMock).not.toHaveBeenCalled();
    });
    it('does not promote the fallback cycle during ordinary filters, but enables suggestions after selection', async () => {
        const fetchMock = mockSuggestionFetch([sessionSuggestion]);
        const view = render(ExaminationIndex, {
            props: pageProps({
                filters: {
                    search: '',
                    status: 'draft',
                    academic_cycle_id: 8,
                },
                cycles: [
                    { id: 8, label: '2026–2027' },
                    { id: 9, label: '2027–2028' },
                ],
                selected_cycle_id: null,
                suggestion_cycle_id: null,
                distribution: { active_cycle_id: 8 },
            }),
        });

        const statusTrigger = view.getByRole('button', { name: 'Status' });
        statusTrigger.focus();
        await fireEvent.keyDown(statusTrigger, { key: 'ArrowDown' });
        await waitFor(() =>
            expect(statusTrigger.getAttribute('aria-expanded')).toBe('true'),
        );
        const publishedOption = await view.findByRole('option', {
            name: 'Published',
        });
        await fireEvent.keyDown(statusTrigger, { key: 'ArrowDown' });
        expect(statusTrigger.getAttribute('aria-activedescendant')).toBe(
            publishedOption.id,
        );
        await fireEvent.keyDown(statusTrigger, { key: 'Enter' });

        expect(visits).toHaveLength(1);
        expect(visits[0].options.data?.academic_cycle_id).toBeUndefined();
        visits[0].options.onSuccess?.({
            url: '/exams/sessions?status=published',
            props: {
                filters: {
                    search: '',
                    status: 'published',
                    academic_cycle_id: 8,
                },
                selected_cycle_id: null,
                suggestion_cycle_id: null,
            },
        });
        visits[0].options.onFinish?.();
        await tick();

        const searchInput = inputFor(view);
        await fireEvent.input(searchInput, { target: { value: 'EXAM' } });
        await fireEvent.keyDown(searchInput, { key: 'Enter' });
        expect(visits).toHaveLength(2);
        expect(visits[1].options.data?.academic_cycle_id).toBeUndefined();
        visits[1].options.onSuccess?.({
            url: '/exams/sessions?search=EXAM&status=published',
            props: {
                filters: {
                    search: 'EXAM',
                    status: 'published',
                    academic_cycle_id: 8,
                },
                selected_cycle_id: null,
                suggestion_cycle_id: null,
            },
        });
        visits[1].options.onFinish?.();
        await tick();

        await fireEvent.input(searchInput, { target: { value: 'EXAM-S31' } });
        expect(fetchMock).not.toHaveBeenCalled();

        const cycleTrigger = view.getByRole('button', {
            name: 'Academic year',
        });
        cycleTrigger.focus();
        await fireEvent.keyDown(cycleTrigger, { key: 'ArrowDown' });
        await waitFor(() =>
            expect(cycleTrigger.getAttribute('aria-expanded')).toBe('true'),
        );
        const nextCycleOption = await view.findByRole('option', {
            name: '2027–2028',
        });
        await fireEvent.keyDown(cycleTrigger, { key: 'End' });
        expect(cycleTrigger.getAttribute('aria-activedescendant')).toBe(
            nextCycleOption.id,
        );
        await fireEvent.keyDown(cycleTrigger, { key: 'Enter' });
        expect(visits).toHaveLength(3);
        expect(visits[2].options.data?.academic_cycle_id).toBe('9');
        const acceptedCycleProps = pageProps({
            filters: {
                search: 'EXAM',
                status: 'published',
                academic_cycle_id: 9,
            },
            selected_cycle_id: 9,
            suggestion_cycle_id: 9,
        });
        await view.rerender(acceptedCycleProps);
        visits[2].options.onSuccess?.({
            url: '/exams/sessions?academic_cycle_id=9',
            props: acceptedCycleProps,
        });
        visits[2].options.onFinish?.();
        await tick();

        await fireEvent.input(inputFor(view), {
            target: { value: 'EXAM-S31' },
        });
        await waitFor(() => expect(fetchMock).toHaveBeenCalledTimes(1), {
            timeout: 1500,
        });
        const request = new URL(
            String(fetchMock.mock.calls[0]?.[0]),
            'http://localhost',
        );
        expect(request.searchParams.get('academic_cycle_id')).toBe('9');
    });
    it('fences old-cycle suggestions until the selected cycle is server-confirmed', async () => {
        const oldCycleSuggestion = {
            ...sessionSuggestion,
            key: 'session:old-cycle',
            session_code: 'OLD-CYCLE-SESSION',
            room_label: 'Old Cycle Hall',
        };
        const newCycleSuggestion = {
            ...sessionSuggestion,
            key: 'session:new-cycle',
            session_code: 'NEW-CYCLE-SESSION',
            room_label: 'New Cycle Hall',
        };
        type SuggestionResponse = {
            ok: boolean;
            json: () => Promise<{
                items: unknown[];
                matches_truncated: boolean;
            }>;
        };
        const {
            promise: oldSuggestionResponse,
            resolve: resolveOldSuggestion,
        } = Promise.withResolvers<SuggestionResponse>();
        const fetchMock = vi
            .fn()
            .mockImplementationOnce(() => oldSuggestionResponse)
            .mockResolvedValue({
                ok: true,
                json: async () => ({
                    items: [newCycleSuggestion],
                    matches_truncated: false,
                }),
            });
        vi.stubGlobal('fetch', fetchMock);
        const initialProps = pageProps({
            cycles: [
                { id: 8, label: '2026–2027' },
                { id: 9, label: '2027–2028' },
            ],
        });
        const view = render(ExaminationIndex, { props: initialProps });
        const input = inputFor(view);
        const selectCycle = async (label: string) => {
            const trigger = view.getByRole('button', {
                name: /Academic year/,
            });
            trigger.focus();
            await fireEvent.keyDown(trigger, { key: 'ArrowDown' });
            await waitFor(() =>
                expect(trigger.getAttribute('aria-expanded')).toBe('true'),
            );
            const option = await view.findByRole('option', { name: label });
            if (label === '2027–2028') {
                await fireEvent.keyDown(trigger, { key: 'End' });
            } else {
                await fireEvent.keyDown(trigger, { key: 'Home' });
                await fireEvent.keyDown(trigger, { key: 'ArrowDown' });
            }
            expect(trigger.getAttribute('aria-activedescendant')).toBe(
                option.id,
            );
            await fireEvent.keyDown(trigger, { key: 'Enter' });
        };
        await fireEvent.input(input, { target: { value: 'old-cycle query' } });
        await waitFor(() => expect(fetchMock).toHaveBeenCalledTimes(1), {
            timeout: 1500,
        });
        expect(fetchMock).toHaveBeenCalledTimes(1);

        const oldCycleRequest = new URL(
            String(fetchMock.mock.calls[0]?.[0]),
            'http://localhost',
        );
        expect(oldCycleRequest.searchParams.get('academic_cycle_id')).toBe('8');

        await selectCycle('2027–2028');
        expect(visits).toHaveLength(1);
        const pendingCycleVisit = visits[0];
        expect(pendingCycleVisit.options.data?.academic_cycle_id).toBe('9');

        resolveOldSuggestion({
            ok: true,
            json: async () => ({
                items: [oldCycleSuggestion],
                matches_truncated: false,
            }),
        });
        await oldSuggestionResponse;
        await tick();
        expect(
            view.queryByRole('option', { name: /OLD-CYCLE-SESSION/ }),
        ).toBeNull();

        await fireEvent.input(input, {
            target: { value: 'pending-cycle query' },
        });
        await waitFor(() => expect(fetchMock).toHaveBeenCalledTimes(1), {
            timeout: 1500,
        });
        expect(
            view.queryByRole('option', { name: /OLD-CYCLE-SESSION/ }),
        ).toBeNull();

        expect(
            pendingCycleVisit.options.onNetworkError?.(
                new TypeError('Failed to fetch'),
            ),
        ).toBe(false);
        pendingCycleVisit.options.onFinish?.();
        await tick();
        await fireEvent.input(input, {
            target: { value: 'failed-cycle query' },
        });
        await waitFor(() => expect(fetchMock).toHaveBeenCalledTimes(1), {
            timeout: 1500,
        });
        expect(fetchMock).toHaveBeenCalledTimes(1);

        await selectCycle('2026–2027');
        expect(visits).toHaveLength(2);
        const acceptedCycle8Props = {
            ...initialProps,
            filters: {
                search: '',
                status: 'draft',
                academic_cycle_id: 8,
            },
            selected_cycle_id: 8,
            suggestion_cycle_id: 8,
        };
        await view.rerender(acceptedCycle8Props);
        visits[1].options.onSuccess?.({
            url: '/exams/sessions?academic_cycle_id=8',
            props: acceptedCycle8Props,
        });
        visits[1].options.onFinish?.();
        await tick();

        await selectCycle('2027–2028');
        expect(visits).toHaveLength(3);
        const acceptedCycle9Props = {
            ...initialProps,
            filters: {
                search: '',
                status: 'draft',
                academic_cycle_id: 9,
            },
            selected_cycle_id: 9,
            suggestion_cycle_id: 9,
        };
        await view.rerender(acceptedCycle9Props);
        visits[2].options.onSuccess?.({
            url: '/exams/sessions?academic_cycle_id=9',
            props: acceptedCycle9Props,
        });
        visits[2].options.onFinish?.();
        await tick();

        await fireEvent.input(input, {
            target: { value: 'accepted-cycle query' },
        });
        await waitFor(() => expect(fetchMock).toHaveBeenCalledTimes(2), {
            timeout: 1500,
        });
        expect(fetchMock).toHaveBeenCalledTimes(2);
        const acceptedCycleRequest = new URL(
            String(fetchMock.mock.calls[1]?.[0]),
            'http://localhost',
        );
        expect(acceptedCycleRequest.searchParams.get('academic_cycle_id')).toBe(
            '9',
        );
        expect(
            await view.findByRole('option', { name: /NEW-CYCLE-SESSION/ }),
        ).toBeTruthy();
        expect(
            view.queryByRole('option', { name: /OLD-CYCLE-SESSION/ }),
        ).toBeNull();
    });

    it('retries ordinary search after a selected academic year request fails', async () => {
        mockSuggestionFetch([]);
        const view = render(ExaminationIndex, {
            props: pageProps({
                cycles: [
                    { id: 8, label: '2026–2027' },
                    { id: 9, label: '2027–2028' },
                ],
            }),
        });
        const trigger = view.getByRole('button', { name: 'Academic year' });
        trigger.focus();
        await fireEvent.keyDown(trigger, { key: 'ArrowDown' });
        await view.findByRole('option', { name: '2027–2028' });
        await fireEvent.keyDown(trigger, { key: 'End' });
        await fireEvent.keyDown(trigger, { key: 'Enter' });
        expect(visits[0].options.data?.academic_cycle_id).toBe('9');
        visits[0].options.onNetworkError?.(new TypeError('Failed to fetch'));
        visits[0].options.onFinish?.();
        await tick();
        const input = inputFor(view);
        await fireEvent.input(input, { target: { value: 'retry query' } });
        await fireEvent.keyDown(input, { key: 'Enter' });
        await waitFor(() => expect(visits).toHaveLength(2));
        expect(visits[1].options.data).toMatchObject({
            search: 'retry query',
            academic_cycle_id: '9',
        });
    });

    it('shows the effective default academic year without claiming all-year results', () => {
        mockSuggestionFetch([]);
        const view = render(ExaminationIndex, {
            props: pageProps({ selected_cycle_id: null }),
        });
        expect(view.getByRole('button', { name: 'Academic year' }).textContent)
            .toContain('2026–2027');
    });

    it('sends the server-resolved default cycle and scheduling status', async () => {
        const fetchMock = mockSuggestionFetch([sessionSuggestion]);
        const view = render(ExaminationIndex, {
            props: pageProps({
                suggestion_cycle_id: 19,
                distribution: { active_cycle_id: 91 },
                filters: {
                    search: '',
                    status: 'draft',
                    academic_cycle_id: null,
                },
            }),
        });

        await fireEvent.input(inputFor(view), { target: { value: 'EXAM' } });
        await waitFor(() => expect(fetchMock).toHaveBeenCalledTimes(1), {
            timeout: 1500,
        });
        const request = new URL(
            String(fetchMock.mock.calls[0]?.[0]),
            'http://localhost',
        );
        expect(request.searchParams.get('academic_cycle_id')).toBe('19');
        expect(request.searchParams.get('status')).toBe('draft');
        expect(request.searchParams.get('domain_context')).toBe('scheduling');
    });

    it('routes examinee activation once and filters the current hub for a session activation', async () => {
        const fetchMock = mockSuggestionFetch([
            sessionSuggestion,
            examineeSuggestion,
        ]);
        const view = render(ExaminationIndex, {
            props: pageProps({
                filters: {
                    search: '',
                    status: 'published',
                    academic_cycle_id: 8,
                },
            }),
        });
        const input = inputFor(view);

        await fireEvent.input(input, { target: { value: 'Science' } });
        await waitFor(() => expect(fetchMock).toHaveBeenCalledTimes(1), {
            timeout: 1500,
        });
        const suggestionRequest = new URL(
            String(fetchMock.mock.calls[0]?.[0]),
            'http://localhost',
        );
        expect(suggestionRequest.searchParams.get('domain_context')).toBe(
            'scheduling',
        );
        expect(suggestionRequest.searchParams.get('academic_cycle_id')).toBe(
            '8',
        );
        expect(suggestionRequest.searchParams.get('status')).toBe('published');
        const sessionOption = await view.findByRole('option', {
            name: /Science Hall \(EXAM-S31\)/,
        });
        await fireEvent.click(sessionOption);

        expect(visits).toHaveLength(1);
        expect(visits[0].url).toBe('/exams/sessions');
        expect(visits[0].options.method).toBe('get');
        expect(visits[0].options.data).toEqual({
            search: 'EXAM-S31',
            status: 'published',
            academic_cycle_id: '8',
        });
        expect(visits[0].options.preserveScroll).toBe(true);
        visits[0].options.onFinish?.();
        await tick();

        visits.length = 0;
        mocks.routerVisit.mockClear();
        const examineeFetch = mockSuggestionFetch([examineeSuggestion]);
        await fireEvent.input(input, { target: { value: 'Avery' } });
        await waitFor(() => expect(examineeFetch).toHaveBeenCalledTimes(1), {
            timeout: 1500,
        });
        await fireEvent.click(
            await view.findByRole('option', { name: /Avery Santos/ }),
        );

        expect(
            visits.filter((visit) => visit.url === '/exams/sessions'),
        ).toHaveLength(0);
        expect(mocks.routerVisit).toHaveBeenCalledTimes(1);
        expect(mocks.routerVisit).toHaveBeenCalledWith(
            examineeSuggestion.destination.url,
            { preserveState: false, preserveScroll: false },
        );
        expect(visits).toHaveLength(1);
    });

    it('keeps dirty search out of status requests and preserves it after the accepted response', async () => {
        mockSuggestionFetch([]);
        const view = render(ExaminationIndex, {
            props: pageProps({
                filters: {
                    search: 'applied',
                    status: 'published',
                    academic_cycle_id: 8,
                },
            }),
        });
        const input = inputFor(view);

        await fireEvent.input(input, { target: { value: 'unfinished draft' } });
        expect(mocks.routerVisit).not.toHaveBeenCalled();

        const statusTrigger = view.getByRole('button', { name: 'Status' });
        statusTrigger.focus();
        await fireEvent.keyDown(statusTrigger, { key: 'ArrowDown' });
        await waitFor(() =>
            expect(statusTrigger.getAttribute('aria-expanded')).toBe('true'),
        );
        await tick();
        const draftOption = await view.findByRole('option', { name: 'Draft' });
        await fireEvent.keyDown(statusTrigger, { key: 'ArrowUp' });
        expect(statusTrigger.getAttribute('aria-activedescendant')).toBe(
            draftOption.id,
        );
        await fireEvent.keyDown(statusTrigger, { key: 'Enter' });
        expect(statusTrigger.textContent).toContain('Draft');

        expect(visits).toHaveLength(1);
        expect(visits[0].options.data).toEqual({
            search: 'applied',
            status: 'draft',
            academic_cycle_id: '8',
        });

        await startVisit(visits[0].options, {
            search: 'applied',
            status: 'draft',
            academic_cycle_id: 8,
        });
        await waitFor(() => expect(input.value).toBe('unfinished draft'));
        expect(view.getByText(/Press Enter to filter/)).toBeTruthy();
        expect(
            view.getByText(/current list still reflects “applied”/i),
        ).toBeTruthy();
    });

    it('clears only the draft without a redundant request, and clears an applied search with other filters retained', async () => {
        const draftOnly = render(ExaminationIndex, { props: pageProps() });
        const draftInput = inputFor(draftOnly);
        await fireEvent.input(draftInput, { target: { value: 'draft-only' } });
        await fireEvent.click(
            draftOnly.getByRole('button', { name: 'Clear search' }),
        );

        expect(draftInput.value).toBe('');
        expect(document.activeElement).toBe(draftInput);
        expect(mocks.routerVisit).not.toHaveBeenCalled();
        draftOnly.unmount();

        const pending = render(ExaminationIndex, { props: pageProps() });
        const pendingInput = inputFor(pending);
        await fireEvent.input(pendingInput, {
            target: { value: 'pending search' },
        });
        await fireEvent.keyDown(pendingInput, { key: 'Enter' });
        expect(
            pending.getByRole('button', { name: 'Clear search' }),
        ).toBeTruthy();
        await fireEvent.click(
            pending.getByRole('button', { name: 'Clear search' }),
        );
        expect(visits).toHaveLength(2);
        expect(visits[0].cancel).toHaveBeenCalledTimes(1);
        expect(visits[1].options.data?.search).toBeUndefined();
        pending.unmount();
        visits.length = 0;
        mocks.routerVisit.mockClear();

        const applied = render(ExaminationIndex, {
            props: pageProps({
                filters: {
                    search: 'applied',
                    status: 'draft',
                    academic_cycle_id: 8,
                },
            }),
        });
        const appliedInput = inputFor(applied);
        await fireEvent.input(appliedInput, { target: { value: '' } });
        expect(
            applied.getByRole('button', { name: 'Clear search' }),
        ).toBeTruthy();
        await fireEvent.click(
            applied.getByRole('button', { name: 'Clear search' }),
        );

        expect(document.activeElement).toBe(appliedInput);
        expect(visits).toHaveLength(1);
        expect(visits[0].options.data).toEqual({
            search: undefined,
            status: 'draft',
            academic_cycle_id: '8',
        });
        await startVisit(visits[0].options, {
            search: '',
            status: 'draft',
            academic_cycle_id: 8,
        });
        await waitFor(() =>
            expect(
                applied.queryByRole('button', { name: 'Clear search' }),
            ).toBeNull(),
        );
    });

    it('keeps the applied query after failure and ignores superseded visits', async () => {
        mockSuggestionFetch([]);
        const view = render(ExaminationIndex, {
            props: pageProps({
                filters: {
                    search: 'applied',
                    status: '',
                    academic_cycle_id: 8,
                },
            }),
        });
        const input = inputFor(view);

        await fireEvent.input(input, { target: { value: 'first query' } });
        await fireEvent.keyDown(input, { key: 'Enter' });
        expect(visits).toHaveLength(1);
        const first = visits[0];
        first.options.onError?.({ search: 'Rejected' });
        first.options.onFinish?.();
        await tick();

        expect(input.value).toBe('first query');
        expect(view.getByRole('alert').textContent).toContain(
            'The filter was not applied',
        );
        expect(
            view.getByText(/current list still reflects “applied”/i),
        ).toBeTruthy();

        await fireEvent.input(input, { target: { value: 'superseded query' } });
        await fireEvent.keyDown(input, { key: 'Enter' });
        const superseded = visits[1];
        await fireEvent.input(input, { target: { value: 'current query' } });
        await fireEvent.keyDown(input, { key: 'Enter' });
        const current = visits[2];

        expect(visits).toHaveLength(3);
        expect(superseded.cancel).toHaveBeenCalledTimes(1);
        superseded.options.onSuccess?.({
            url: '/exams/sessions?search=superseded',
            props: {
                filters: {
                    search: 'superseded query',
                    status: '',
                    academic_cycle_id: 8,
                },
            },
        });
        await tick();
        expect(input.value).toBe('current query');

        current.options.onSuccess?.({
            url: '/exams/sessions?search=current',
            props: {
                filters: {
                    search: 'current query',
                    status: '',
                    academic_cycle_id: 8,
                },
            },
        });
        current.options.onFinish?.();
        await tick();
        await waitFor(() =>
            expect(
                view.getByText(/Showing sessions filtered by “current query”/),
            ).toBeTruthy(),
        );
    });

    it('keeps applied results after handled HTTP and network failures', async () => {
        mockSuggestionFetch([]);
        const view = render(ExaminationIndex, {
            props: pageProps({
                filters: {
                    search: 'applied',
                    status: '',
                    academic_cycle_id: 8,
                },
            }),
        });
        const input = inputFor(view);

        await fireEvent.input(input, { target: { value: 'http failure' } });
        await fireEvent.keyDown(input, { key: 'Enter' });
        const httpFailure = visits[0];
        expect(httpFailure.options.onHttpException?.({ status: 500 })).toBe(
            false,
        );
        await tick();
        expect(view.getByRole('alert').textContent).toContain(
            'The filter could not be applied',
        );
        expect(
            view.getByText(/current list still reflects “applied”/i),
        ).toBeTruthy();
        expect(view.container.textContent).toContain('EXAM-S31');
        httpFailure.options.onFinish?.();
        await tick();

        await fireEvent.input(input, { target: { value: 'network failure' } });
        await fireEvent.keyDown(input, { key: 'Enter' });
        const networkFailure = visits[1];
        expect(
            networkFailure.options.onNetworkError?.(
                new TypeError('Failed to fetch'),
            ),
        ).toBe(false);
        await tick();
        expect(view.getByRole('alert').textContent).toContain(
            'The filter could not be applied because the request failed',
        );
        expect(
            view.getByText(/current list still reflects “applied”/i),
        ).toBeTruthy();
        expect(view.container.textContent).toContain('EXAM-S31');
        networkFailure.options.onFinish?.();
        await tick();
    });

    it('keeps newer typing after success and restores draft, committed search, and filters from browser history', async () => {
        mockSuggestionFetch([]);
        const view = render(ExaminationIndex, {
            props: pageProps({
                filters: {
                    search: 'applied',
                    status: '',
                    academic_cycle_id: 8,
                },
            }),
        });
        const input = inputFor(view);

        await fireEvent.input(input, { target: { value: 'submitted query' } });
        await fireEvent.keyDown(input, { key: 'Enter' });
        const submitted = visits[0];
        await fireEvent.input(input, { target: { value: 'newer draft' } });
        submitted.options.onSuccess?.({
            url: '/exams/sessions?search=submitted',
            props: {
                filters: {
                    search: 'submitted query',
                    status: '',
                    academic_cycle_id: 8,
                },
            },
        });
        submitted.options.onFinish?.();
        await tick();

        expect(input.value).toBe('newer draft');
        expect(view.getByText(/Press Enter to filter/)).toBeTruthy();
        expect(
            view.getByText(/current list still reflects “submitted query”/i),
        ).toBeTruthy();

        await fireEvent(window, new PopStateEvent('popstate'));
        navigationListener?.({
            detail: {
                page: {
                    url: '/exams/sessions?search=restored&status=completed&academic_cycle_id=9',
                    props: {
                        filters: {
                            search: 'restored',
                            status: 'completed',
                            academic_cycle_id: 9,
                        },
                    },
                },
            },
        });
        await tick();

        await waitFor(() => expect(input.value).toBe('restored'));
        expect(
            view.getByText(/Showing sessions filtered by “restored”/),
        ).toBeTruthy();
        expect(
            view.getByRole('button', { name: 'Status' }).textContent,
        ).toContain('Completed');
    });

    it('uses the selected institution-owned cycle and current status for suggestions', async () => {
        const fetchMock = mockSuggestionFetch([]);
        const view = render(ExaminationIndex, {
            props: pageProps({
                filters: {
                    search: '',
                    status: 'completed',
                    academic_cycle_id: 7,
                },
                cycles: [
                    { id: 7, label: '2025–2026' },
                    { id: 8, label: '2026–2027' },
                ],
                distribution: { active_cycle_id: 8 },
                suggestion_cycle_id: 7,
            }),
        });

        await fireEvent.input(inputFor(view), {
            target: { value: 'older session' },
        });
        await waitFor(() => expect(fetchMock).toHaveBeenCalledTimes(1), {
            timeout: 1500,
        });
        const request = new URL(
            String(fetchMock.mock.calls[0]?.[0]),
            'http://localhost',
        );
        expect(request.searchParams.get('academic_cycle_id')).toBe('7');
        expect(request.searchParams.get('status')).toBe('completed');
        expect(request.searchParams.get('domain_context')).toBe('scheduling');
    });
    it('defaults to Needs scheduling, keeps All sessions available, and resets to the active queue', async () => {
        const view = render(ExaminationIndex, {
            props: pageProps({
                filters: { search: '', status: '', academic_cycle_id: 8 },
            }),
        });
        const statusTrigger = view.getByRole('button', { name: 'Status' });
        expect(statusTrigger.textContent).toContain('Needs scheduling');

        statusTrigger.focus();
        await fireEvent.keyDown(statusTrigger, { key: 'ArrowDown' });
        await waitFor(() =>
            expect(statusTrigger.getAttribute('aria-expanded')).toBe('true'),
        );
        const allSessionsOption = await view.findByRole('option', {
            name: 'All sessions',
        });
        await fireEvent.keyDown(statusTrigger, { key: 'ArrowDown' });
        expect(statusTrigger.getAttribute('aria-activedescendant')).toBe(
            allSessionsOption.id,
        );
        await fireEvent.keyDown(statusTrigger, { key: 'Enter' });
        await waitFor(() => expect(visits).toHaveLength(1));
        expect(visits[0].options.data?.status).toBe('all');

        await fireEvent.click(view.getByRole('button', { name: 'Reset' }));
        await waitFor(() => expect(visits).toHaveLength(2));
        expect(visits[1].options.data?.status).toBe('active');
    });

    it('sorts occupancy numerically in both directions without losing selected session identity', async () => {
        const base = pageProps().sessions[0];
        const view = render(ExaminationIndex, {
            props: pageProps({
                sessions: [
                    { ...base, id: 31, code: 'LOW-OCCUPANCY', capacity: 100, assigned_count: 2 },
                    { ...base, id: 32, code: 'HIGH-OCCUPANCY', capacity: 100, assigned_count: 10 },
                ],
            }),
        });
        const table = view.container.querySelector('table')!;
        const rows = () => within(table).getAllByRole('row').slice(1);
        const selected = within(rows()[0]).getByRole('checkbox');
        await fireEvent.click(selected);
        const header = within(table).getByRole('button', { name: 'Sort by capacity occupancy' });
        await fireEvent.click(header);
        expect(rows().map((row) => row.textContent?.includes('LOW-OCCUPANCY'))).toEqual([true, false]);
        expect(header.closest('th')?.getAttribute('aria-sort')).toBe('ascending');
        await fireEvent.click(header);
        expect(rows().map((row) => row.textContent?.includes('HIGH-OCCUPANCY'))).toEqual([true, false]);
        expect(header.closest('th')?.getAttribute('aria-sort')).toBe('descending');
        expect((within(rows()[1]).getByRole('checkbox') as HTMLInputElement).checked).toBe(true);
        expect(mocks.routerPost).not.toHaveBeenCalled();
    });

    it('renders the consistent standby card and Create Exam Session CTA when no sessions are scheduled', () => {
        const view = render(ExaminationIndex, {
            props: pageProps({
                page_state: 'empty',
                sessions: [],
                cycles: [
                    {
                        id: 8,
                        label: '2025-2026 1st Semester',
                        is_active: true,
                    },
                ],
                selected_cycle_id: 8,
                waitlist_counts: { ready: 4 },
            }),
        });

        expect(
            view.getByRole('heading', { name: 'No Sessions in Active Schedule' }),
        ).toBeTruthy();
        expect(view.container.textContent?.replace(/\s+/g, ' ')).toContain(
            'All examination rooms are currently on standby for 2025-2026 1st Semester. Create an exam session under Exam Scheduling to initiate live testing-day monitoring.',
        );
        const createLink = view
            .getByRole('button', { name: /Create Exam Session/i })
            .closest('a');
        expect(createLink?.getAttribute('href')).toContain('/exams/sessions/create');
        expect(
            view.queryByRole('button', { name: /View Waitlist/i }),
        ).toBeNull();
    });
});
