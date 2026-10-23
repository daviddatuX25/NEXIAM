// @vitest-environment jsdom

import {
    cleanup,
    fireEvent,
    render,
    screen,
    waitFor,
} from '@testing-library/svelte';
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';

const mocks = vi.hoisted(() => ({
    get: vi.fn(),
    visit: vi.fn(),
    post: vi.fn(),
    listeners: new Map<string, (event: unknown) => void>(),
}));

vi.mock('@inertiajs/svelte', async (importOriginal) => {
    const actual = await importOriginal<Record<string, unknown>>();
    const actualRouter = (actual.router as Record<string, unknown>) ?? {};

    return {
        ...actual,
        usePage: () => ({
            url: '/grading',
            props: {
                auth: { user: { id: 17 } },
                practice_workspace: null,
                errors: {},
            },
        }),
        router: {
            ...actualRouter,
            get: (...args: unknown[]) => mocks.get(...args),
            visit: (...args: unknown[]) => mocks.visit(...args),
            post: (...args: unknown[]) => mocks.post(...args),
            on: (eventName: string, handler: (event: unknown) => void) => {
                mocks.listeners.set(eventName, handler);
                return () => {
                    if (mocks.listeners.get(eventName) === handler) {
                        mocks.listeners.delete(eventName);
                    }
                };
            },
        },
    };
});

import Grading from '../../resources/js/Pages/Assessment/Grading.svelte';

type VisitOptions = {
    onCancelToken?: (token: { cancel: () => void }) => void;
    onSuccess?: (page: { props: Record<string, unknown> }) => void;
    onError?: () => void;
    onCancel?: () => void;
    onFinish?: () => void;
};

function renderGrading(overrides: Record<string, unknown> = {}) {
    return render(Grading, {
        props: {
            page_state: 'ready',
            aptitude_areas_count: 1,
            active_academic_cycle: { id: 8, label: '2026–2027' },
            filters: { search: '' },
            sessions: [
                {
                    id: 5,
                    exam_session_id: 15,
                    exam_code: 'REAL-EXAM-CODE',
                    room_name: 'North Hall',
                    starts_at: '2026-10-08 09:00',
                    status: 'open',
                    applicants_total: 2,
                    applicants_scored: 0,
                    applicants_absent: 0,
                    progress_percent: 0,
                },
            ],
            ...overrides,
        },
    });
}

function expectResponsiveSessionIdentity(): void {
    expect(screen.getAllByText('REAL-EXAM-CODE')).toHaveLength(2);
    expect(screen.getByText('North Hall')).toBeTruthy();
    expect(screen.getByText('Room: North Hall')).toBeTruthy();
}

function searchInput(): HTMLInputElement {
    return screen.getByRole('combobox', {
        name: 'Search grading sessions and examinees',
    }) as HTMLInputElement;
}

function latestVisit(): {
    url: string;
    data: Record<string, unknown>;
    options: VisitOptions;
} {
    const [url, data, options] = mocks.get.mock.calls.at(-1) as [
        string,
        Record<string, unknown>,
        VisitOptions,
    ];
    return { url, data, options };
}

beforeEach(() => {
    mocks.get.mockReset();
    mocks.visit.mockReset();
    mocks.post.mockReset();
    vi.stubGlobal(
        'fetch',
        vi.fn().mockResolvedValue({
            ok: true,
            json: async () => ({ items: [], matches_truncated: false }),
        }),
    );
    mocks.listeners.clear();
});

afterEach(() => {
    cleanup();
    mocks.listeners.clear();
    vi.unstubAllGlobals();
});

describe('grading hub search', () => {
    it('keeps real server rows while typing and submits exactly once on delegated Enter', async () => {
        renderGrading();
        const input = searchInput();

        await fireEvent.input(input, { target: { value: 'North Hall' } });

        expect(mocks.get).not.toHaveBeenCalled();
        expectResponsiveSessionIdentity();

        await fireEvent.keyDown(input, { key: 'Enter', isComposing: true });
        expect(mocks.get).not.toHaveBeenCalled();

        await fireEvent.keyDown(input, { key: 'Enter' });

        expect(mocks.get).toHaveBeenCalledTimes(1);
        const visit = latestVisit();
        expect(visit.data).toMatchObject({ search: 'North Hall' });
        expectResponsiveSessionIdentity();
    });

    it('adopts the accepted server filter without overwriting newer draft text', async () => {
        renderGrading();
        const input = searchInput();
        await fireEvent.input(input, { target: { value: 'North' } });
        await fireEvent.keyDown(input, { key: 'Enter' });
        const visit = latestVisit();

        await fireEvent.input(input, { target: { value: 'newer draft' } });
        visit.options.onSuccess?.({
            props: { filters: { search: 'North Hall' } },
        });
        visit.options.onFinish?.();

        expect(input.value).toBe('newer draft');
        expectResponsiveSessionIdentity();
    });

    it('ignores callbacks from a superseded filter request', async () => {
        renderGrading();
        const input = searchInput();
        await fireEvent.input(input, { target: { value: 'older' } });
        await fireEvent.keyDown(input, { key: 'Enter' });
        const olderVisit = latestVisit();

        await fireEvent.input(input, { target: { value: 'newer' } });
        await fireEvent.keyDown(input, { key: 'Enter' });
        const newerVisit = latestVisit();
        expect(mocks.get).toHaveBeenCalledTimes(2);

        newerVisit.options.onSuccess?.({
            props: { filters: { search: 'newer' } },
        });
        newerVisit.options.onFinish?.();
        olderVisit.options.onSuccess?.({
            props: { filters: { search: 'older' } },
        });
        olderVisit.options.onFinish?.();

        expect(input.value).toBe('newer');
        expectResponsiveSessionIdentity();
    });

    it('retains rows and draft on failure, and clear explicitly submits search removal', async () => {
        renderGrading({ filters: { search: 'already applied' } });
        const input = searchInput();
        expect(input.value).toBe('already applied');

        await fireEvent.input(input, { target: { value: 'failed draft' } });
        await fireEvent.keyDown(input, { key: 'Enter' });
        const failedVisit = latestVisit();
        failedVisit.options.onError?.();
        failedVisit.options.onFinish?.();

        expect(input.value).toBe('failed draft');
        expectResponsiveSessionIdentity();
        await waitFor(() =>
            expect(screen.getByRole('alert').textContent).toContain(
                'could not be applied',
            ),
        );

        await fireEvent.click(
            screen.getByRole('button', { name: 'Clear search' }),
        );
        expect(mocks.get).toHaveBeenCalledTimes(2);
        expect(latestVisit().data).toMatchObject({ search: undefined });
    });
    it('restores the committed search after browser history navigation', async () => {
        renderGrading({ filters: { search: 'current search' } });
        const input = searchInput();
        expect(mocks.listeners.has('navigate')).toBe(true);

        await fireEvent.input(input, { target: { value: 'unsaved draft' } });
        window.dispatchEvent(new PopStateEvent('popstate'));
        mocks.listeners.get('navigate')?.({
            detail: {
                page: {
                    props: {
                        filters: {
                            search: 'previous search',
                            status: 'finalized',
                        },
                    },
                },
            },
        });

        await waitFor(() => expect(input.value).toBe('previous search'));
        expect(
            (document.querySelector('#grading-status-filter') as HTMLSelectElement).value,
        ).toBe('finalized');
        expectResponsiveSessionIdentity();

        cleanup();
        expect(mocks.listeners.has('navigate')).toBe(false);
    });
    it('navigates once for an examinee and server-filters session activation', async () => {
        const examinee = {
            key: 'examinee:22',
            kind: 'examinee',
            assignment_id: null,
            attempt_id: 22,
            reference_code: 'REF-22',
            name: 'Celia Applicant',
            exam_session_id: 22,
            session_code: 'GRADING-22',
            room_label: 'North Hall',
            session_status: 'completed',
            starts_at: '2026-10-08T09:00:00Z',
            ends_at: null,
            grading_session_id: 7,
            matched_fields: ['name'],
            destination: {
                url: '/grading/sessions/7?focus_attempt_id=22',
                focus_kind: 'attempt',
                focus_id: 22,
            },
        };
        const session = {
            key: 'session:22',
            kind: 'session',
            assignment_id: null,
            attempt_id: null,
            reference_code: null,
            name: null,
            exam_session_id: 22,
            session_code: 'GRADING-22',
            room_label: 'North Hall',
            session_status: 'completed',
            starts_at: '2026-10-08T09:00:00Z',
            ends_at: null,
            grading_session_id: 7,
            matched_fields: ['session_code'],
            destination: {
                url: '/grading/sessions/7',
                focus_kind: null,
                focus_id: null,
            },
        };
        const fetchMock = vi
            .fn()
            .mockResolvedValueOnce({
                ok: true,
                json: async () => ({
                    items: [examinee],
                    matches_truncated: false,
                }),
            })
            .mockResolvedValueOnce({
                ok: true,
                json: async () => ({
                    items: [session],
                    matches_truncated: false,
                }),
            });
        vi.stubGlobal('fetch', fetchMock);

        renderGrading();
        let input = searchInput();
        await fireEvent.focus(input);
        await fireEvent.input(input, { target: { value: 'Celia' } });
        await waitFor(() => expect(fetchMock).toHaveBeenCalledTimes(1));
        const suggestionRequest = new URL(
            String(fetchMock.mock.calls[0]?.[0]),
            window.location.origin,
        );
        expect(suggestionRequest.searchParams.get('status')).toBe('active');
        await waitFor(() =>
            expect(
                screen.getByRole('option', { name: /Celia Applicant/ }),
            ).toBeTruthy(),
        );
        await fireEvent.keyDown(input, { key: 'ArrowDown' });
        await fireEvent.keyDown(input, { key: 'Enter' });

        expect(mocks.visit).toHaveBeenCalledTimes(1);
        expect(mocks.visit).toHaveBeenCalledWith(examinee.destination.url);
        expect(mocks.get).not.toHaveBeenCalled();

        cleanup();
        renderGrading();
        input = searchInput();
        await fireEvent.focus(input);
        await fireEvent.input(input, { target: { value: 'North Hall' } });
        await waitFor(() => expect(fetchMock).toHaveBeenCalledTimes(2));
        await waitFor(() =>
            expect(
                screen.getByRole('option', { name: /GRADING-22/ }),
            ).toBeTruthy(),
        );
        await fireEvent.keyDown(input, { key: 'ArrowDown' });
        await fireEvent.keyDown(input, { key: 'Enter' });

        expect(mocks.get).toHaveBeenCalledTimes(1);
        expect(latestVisit().data).toMatchObject({ search: 'GRADING-22' });
        expect(mocks.visit).toHaveBeenCalledTimes(1);
    });
    
    it('shows one authorized Test Setup destination at the canonical Test Kits list', () => {
        renderGrading({ can_manage_catalog: true });
        const link = screen.getByRole('link', { name: 'Test Setup' });
        expect(link.getAttribute('href')).toBe('/setup/exam-materials/test-kits');
        expect(screen.queryByText(/Norm Conversion Tables|Aptitude Areas|Test Instruments/)).toBeNull();
    
        cleanup();
        renderGrading({ can_manage_catalog: false });
        expect(screen.queryByRole('link', { name: 'Test Setup' })).toBeNull();
    });
    it('defaults to Needs scoring, exposes all scoring states, and clears back to the active queue', async () => {
        const view = renderGrading();
        const status = view.container.querySelector(
            '#grading-status-filter',
        ) as HTMLSelectElement;

        expect(status.value).toBe('active');
        expect(Array.from(status.options).map((option) => option.textContent)).toEqual([
            'Needs scoring',
            'All sessions',
            'In progress',
            'In review',
            'Finalized',
            'Repair required',
        ]);

        await fireEvent.change(status, { target: { value: 'finalized' } });
        expect(latestVisit().data.status).toBe('finalized');
        await fireEvent.click(screen.getAllByRole('button', { name: 'Clear filters' })[0]);
        expect(status.value).toBe('active');
        expect(latestVisit().data.status).toBe('active');
    });
});
