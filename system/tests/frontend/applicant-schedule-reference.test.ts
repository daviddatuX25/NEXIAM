// @vitest-environment jsdom

import { cleanup, render, screen } from '@testing-library/svelte';
import { afterEach, describe, expect, it, vi } from 'vitest';
import { router } from '@inertiajs/svelte';

vi.mock('@inertiajs/svelte', () => ({
    Link: (
        _anchor: unknown,
        { children }: { children?: (anchor: unknown) => void },
    ) => children?.(_anchor),
    router: { post: vi.fn(), reload: vi.fn() },
    usePage: () => ({ props: { errors: {} } }),
}));
vi.mock('@/Pages/Applicant/ApplicantLayout.svelte', () => ({
    default: (
        _anchor: unknown,
        { children }: { children?: (anchor: unknown) => void },
    ) => children?.(_anchor),
}));
vi.mock('@/Components/kit/Walkthrough/TourTriggerGlyph.svelte', () => ({
    default: () => null,
}));
vi.mock('@/Components/kit/Xiam/XiamNpcDialog.svelte', () => ({
    default: () => null,
}));
vi.mock('@/Components/applicant/ReservationSlotList.svelte', () => ({
    default: () => null,
}));
vi.mock('@/Components/applicant/ReservationConfirmSheet.svelte', () => ({
    default: () => null,
}));
vi.mock('@/Components/applicant/ApplicantScheduleCalendar.svelte', () => ({
    default: () => null,
}));
vi.mock('@/Components/Wayfinding/VenueCompassHud.svelte', () => ({
    default: () => null,
}));

import Schedule from '../../resources/js/Pages/Applicant/Schedule.svelte';

afterEach(() => cleanup());

describe('applicant Schedule reference', () => {
    it('labels the own application reference as Applicant No, not an NOA number', () => {
        render(Schedule, {
            props: {
                page_state: 'denied',
                attempt: { reference_code: 'APP-2026-000003' },
            },
        });

        expect(screen.getByText('Applicant No. APP-2026-000003')).toBeTruthy();
        expect(screen.queryByText(/NOA No\./i)).toBeNull();
    });

    it('shows actual early-start facts separately from the original scheduled date', () => {
        render(Schedule, {
            props: {
                page_state: 'assigned_published',
                attempt: { id: 1, reference_code: 'APP-2026-000003' },
                assignment: {
                    id: 2,
                    source: 'staff_assigned',
                    session: {
                        code: 'EXCEPTION-DEMO',
                        status: 'in_progress',
                        published: true,
                        date: '2026-10-20',
                        start_time: '16:00',
                        end_date: '2026-10-20',
                        end_time: '17:00',
                        scheduled_at: '2026-10-20T16:00:00+08:00',
                        effective_end_at: '2026-10-19T08:00:00+08:00',
                        started_at: '2026-10-19T07:30:00+08:00',
                        closed_at: null,
                        room: 'Room 1',
                    },
                },
            },
        });

        expect(screen.getByText('In progress')).toBeTruthy();
        expect(screen.getByTestId('page-state').textContent).toContain('published');
        expect(screen.queryByText('Tentative')).toBeNull();
        expect(screen.getByText('Started at')).toBeTruthy();
        expect(screen.getByText('Expected end')).toBeTruthy();
        expect(screen.getByText('Original scheduled start')).toBeTruthy();
        expect(screen.getByText(/October 20, 2026 · 4:00 PM/)).toBeTruthy();
        expect(screen.queryByText(/October 20, 2026 · 4:00 PM to/)).toBeNull();
    });
    it('keeps an unstarted tentative appointment in campus wall-clock time across dates', () => {
        render(Schedule, {
            props: {
                page_state: 'assigned_tentative',
                attempt: { id: 1 },
                assignment: {
                    id: 2,
                    session: {
                        code: 'CROSS-MIDNIGHT',
                        status: 'draft',
                        published: false,
                        date: '2026-10-20',
                        start_time: '23:00',
                        end_date: '2026-10-21',
                        end_time: '01:00',
                        scheduled_at: '2026-10-20T23:00:00+08:00',
                        effective_end_at: '2026-10-21T01:00:00+08:00',
                    },
                },
            },
        });

        expect(screen.getByText('Tentative')).toBeTruthy();
        expect(screen.getByTestId('page-state').textContent).toContain('tentative');
        expect(screen.getByTestId('page-state').textContent).not.toMatch(/published/i);
        expect(
            screen.getByText('October 20, 2026 – October 21, 2026'),
        ).toBeTruthy();
        expect(screen.getByText('11:00 PM – 1:00 AM')).toBeTruthy();
    });

    it('shows completed status and missing actual timestamps as not recorded', () => {
        render(Schedule, {
            props: {
                page_state: 'assigned_published',
                attempt: { id: 1 },
                assignment: {
                    id: 2,
                    session: {
                        code: 'COMPLETED-DEMO',
                        status: 'completed',
                        published: true,
                        date: '2026-10-20',
                        start_time: '08:00',
                        end_date: '2026-10-20',
                        end_time: '10:00',
                        scheduled_at: '2026-10-20T08:00:00+08:00',
                        effective_end_at: '2026-10-20T10:00:00+08:00',
                        started_at: null,
                        closed_at: null,
                    },
                },
            },
        });

        expect(screen.getByText('Completed')).toBeTruthy();
        expect(screen.getByText('Started at')).toBeTruthy();
        expect(screen.getByText('Ended at')).toBeTruthy();
        expect(screen.getAllByText('Not recorded')).toHaveLength(2);
    });

    it('pauses lifecycle refresh while hidden or offline and cleans up on unmount', async () => {
        vi.useFakeTimers();
        vi.mocked(router.reload).mockClear();
        const visibility = Object.getOwnPropertyDescriptor(
            document,
            'visibilityState',
        );
        Object.defineProperty(document, 'visibilityState', {
            configurable: true,
            value: 'hidden',
        });
        const online = Object.getOwnPropertyDescriptor(navigator, 'onLine');
        Object.defineProperty(navigator, 'onLine', {
            configurable: true,
            value: true,
        });
        const view = render(Schedule, {
            props: {
                page_state: 'assigned_published',
                assignment: { session: { status: 'published' } },
            },
        });

        await vi.advanceTimersByTimeAsync(30_000);
        expect(router.reload).not.toHaveBeenCalled();
        Object.defineProperty(navigator, 'onLine', {
            configurable: true,
            value: false,
        });
        Object.defineProperty(document, 'visibilityState', {
            configurable: true,
            value: 'visible',
        });
        document.dispatchEvent(new Event('visibilitychange'));
        expect(router.reload).not.toHaveBeenCalled();
        Object.defineProperty(navigator, 'onLine', {
            configurable: true,
            value: true,
        });
        window.dispatchEvent(new Event('online'));
        expect(router.reload).toHaveBeenCalledTimes(1);
        view.unmount();
        await vi.advanceTimersByTimeAsync(30_000);

        expect(router.reload).toHaveBeenCalledTimes(1);
        if (visibility)
            Object.defineProperty(document, 'visibilityState', visibility);
        if (online) Object.defineProperty(navigator, 'onLine', online);
        vi.useRealTimers();
    });
    it('begins refreshing when a reservation response supplies the first assignment', async () => {
        vi.useFakeTimers();
        vi.mocked(router.reload).mockClear();
        const view = render(Schedule, {
            props: { page_state: 'calendar_open', assignment: null },
        });
        await vi.advanceTimersByTimeAsync(15_000);
        expect(router.reload).not.toHaveBeenCalled();
        await view.rerender({
            page_state: 'assigned_published',
            assignment: { session: { status: 'published', published: true } },
        });
        await vi.advanceTimersByTimeAsync(15_000);
        expect(router.reload).toHaveBeenCalledTimes(1);
        view.unmount();
        vi.useRealTimers();
    });
});
