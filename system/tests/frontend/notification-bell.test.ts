// @vitest-environment jsdom

import { cleanup, fireEvent, render, screen } from '@testing-library/svelte';
import { tick } from 'svelte';
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import NotificationBell from '../../resources/js/Components/kit/NotificationBell.svelte';
const mockRouterReload = vi.fn((options: Record<string, unknown> = {}) => {
    if (typeof options?.onSuccess === 'function') {
        options.onSuccess();
    }
    if (typeof options?.onFinish === 'function') {
        options.onFinish();
    }
});
const mockRouterPost = vi.fn();
const mockRouterVisit = vi.fn();
const mockPollCalls: Array<{ interval: number; requestOptions: unknown }> = [];
const mockUsePoll = vi.fn((interval: number, requestOptions: unknown = {}) => {
    mockPollCalls.push({ interval, requestOptions });
    return { start: vi.fn(), stop: vi.fn() };
});

const mockPageProps: {
    notifications: {
        unread_count: number;
        recent: Array<Record<string, unknown>>;
        queue_active?: boolean;
    } | null;
    auth?: {
        user?: {
            id?: number;
            name?: string;
            is_applicant?: boolean;
        };
    };
    url?: string;
} = {
    notifications: {
        unread_count: 2,
        queue_active: false,
        recent: [
            {
                id: 'notif-1',
                subject: 'Exam Slot Confirmed',
                body: 'Your exam schedule is locked for Session AM-01.',
                read: false,
                created_at: '2026-08-22T10:03:00Z',
                status: 'delivered',
                delivered_at: '2026-08-22T10:03:00Z',
                channel: 'in_app',
                failed: false,
                action_url: '/applicant/schedule',
            },
            {
                id: 'notif-2',
                subject: 'Application Under Review',
                body: 'The registrar is verifying your documents.',
                read: true,
                created_at: '2026-08-21T14:30:00Z',
                status: 'delivered',
                delivered_at: '2026-08-21T14:30:00Z',
                channel: 'in_app',
                failed: false,
                action_url: null,
            },
        ],
    },
};

vi.mock('@inertiajs/svelte', async (importOriginal) => {
    const actual = await importOriginal<Record<string, unknown>>();
    const actualRouter = (actual.router as Record<string, unknown>) ?? {};

    return {
        ...actual,
        usePage: () => ({ props: mockPageProps }),
        usePoll: (...args: [number, unknown?, unknown?]) =>
            mockUsePoll(args[0], args[1]),
        router: {
            ...actualRouter,
            reload: (...args: unknown[]) => mockRouterReload(...args),
            post: (...args: unknown[]) => mockRouterPost(...args),
            visit: (...args: unknown[]) => mockRouterVisit(...args),
        },
    };
});

describe('NotificationBell', () => {
    beforeEach(() => {
        vi.clearAllMocks();
        delete mockPageProps.auth;
        delete mockPageProps.url;
        mockPageProps.notifications = {
            unread_count: 2,
            queue_active: false,
            recent: [
                {
                    id: 'notif-1',
                    subject: 'Exam Slot Confirmed',
                    body: 'Your exam schedule is locked for Session AM-01.',
                    read: false,
                    created_at: '2026-08-22T10:03:00Z',
                    status: 'delivered',
                    delivered_at: '2026-08-22T10:03:00Z',
                    channel: 'in_app',
                    failed: false,
                    action_url: '/applicant/schedule',
                },
                {
                    id: 'notif-2',
                    subject: 'Application Under Review',
                    body: 'The registrar is verifying your documents.',
                    read: true,
                    created_at: '2026-08-21T14:30:00Z',
                    status: 'delivered',
                    delivered_at: '2026-08-21T14:30:00Z',
                    channel: 'in_app',
                    failed: false,
                    action_url: null,
                },
            ],
        };
    });

    afterEach(() => {
        cleanup();
    });

    it('displays unread badge and triggers reload on initial dropdown open', async () => {
        render(NotificationBell);

        const bellButton = screen.getByRole('button', { name: /notifications/i });
        expect(bellButton).toBeTruthy();
        expect(screen.getByText('2')).toBeTruthy();

        // Open the popover
        await fireEvent.click(bellButton);
        await tick();

        // Opening should trigger a reload for fresh data
        expect(mockRouterReload).toHaveBeenCalledWith(
            expect.objectContaining({
                only: ['notifications', 'navigation_badges'],
            }),
        );
    });

    it('formats date and time minimally and removes in-app labels', async () => {
        render(NotificationBell);

        const bellButton = screen.getByRole('button', { name: /notifications/i });
        await fireEvent.click(bellButton);
        await tick();

        // Check date formatting: Aug. 22 · ...
        const dateMatch = screen.getByText(/Aug\.\s*22\s*·/i);
        expect(dateMatch).toBeTruthy();

        // In-app labels like "In-app available" or "In-app queued" must NOT be rendered
        expect(screen.queryByText(/in-app available/i)).toBeNull();
        expect(screen.queryByText(/in-app queued/i)).toBeNull();
    });

    it('describes queued delivery states without claiming a worker is active and allows refresh', async () => {
        mockPageProps.notifications = {
            unread_count: 1,
            queue_active: true,
            recent: [
                {
                    id: 'notif-queued',
                    subject: 'Processing Application Notice',
                    body: 'Your application is entering the queue.',
                    read: false,
                    created_at: '2026-08-22T10:05:00Z',
                    status: 'processing',
                    channel: 'in_app',
                    failed: false,
                },
            ],
        };

        render(NotificationBell);

        const bellButton = screen.getByRole('button', { name: /notifications/i });
        await fireEvent.click(bellButton);
        await tick();

        // The projection exposes delivery states, not worker health.
        expect(screen.getByText(/Some notifications are queued, retrying, or processing/i)).toBeTruthy();
        expect(screen.queryByText(/Queue worker active/i)).toBeNull();
        expect(screen.queryByText(/Background delivery in progress/i)).toBeNull();

        // Click the refresh button in the QA banner
        const refreshBtn = screen.getByRole('button', { name: 'Refresh' });
        expect(refreshBtn).toBeTruthy();

        mockRouterReload.mockClear();
        await fireEvent.click(refreshBtn);
        await tick();

        expect(mockRouterReload).toHaveBeenCalledWith(
            expect.objectContaining({
                only: ['notifications', 'navigation_badges'],
            }),
        );
    });

    it('registers 8s live poll interval for responsive background arrival', () => {
        render(NotificationBell);
        expect(mockPollCalls.some((call) => call.interval === 8000)).toBe(true);
    });

    it('reloads notifications on window focus for immediate live arrival', async () => {
        render(NotificationBell);
        mockRouterReload.mockClear();

        // Dispatch focus event
        window.dispatchEvent(new Event('focus'));
        await tick();

        expect(mockRouterReload).toHaveBeenCalledWith(
            expect.objectContaining({
                only: ['notifications', 'navigation_badges'],
            }),
        );
    });

    it('triggers subtle pop pulse animation when unread count increments', async () => {
        const initial = {
            unread_count: 2,
            recent: [],
            queue_active: false,
        };
        const view = render(NotificationBell, { props: { notifications: initial } });

        // Initial render should not have animation
        const initialBadge = screen.getByText('2').closest('.bg-destructive');
        expect(initialBadge?.className).not.toContain('animate-badge-pop');

        // Increment unread count to 3
        await view.rerender({
            notifications: {
                unread_count: 3,
                recent: [],
                queue_active: false,
            },
        });
        await tick();

        const updatedBadge = screen.getByText('3').closest('.bg-destructive');
        expect(updatedBadge?.className).toContain('animate-badge-pop');
    });



    it('does not display email delivery tags in bell popover for applicants', async () => {
        mockPageProps.auth = {
            user: {
                id: 1,
                name: 'Jane Applicant',
                is_applicant: true,
            },
        };
        mockPageProps.notifications = {
            unread_count: 1,
            queue_active: false,
            recent: [
                {
                    id: 'notif-offer-bell',
                    subject: 'Admission Offer',
                    body: 'Congratulations! You have been accepted for admission.',
                    read: false,
                    created_at: '2026-08-22T10:03:00Z',
                    status: 'delivered',
                    delivered_at: '2026-08-22T10:03:00Z',
                    channel: 'in_app',
                    failed: false,
                    action_url: null,
                    email_delivery: {
                        status: 'delivered',
                        attempts: 1,
                        delivered_at: '2026-08-22T10:03:01Z',
                        failed: false,
                        outcome_unknown: false,
                    },
                },
            ],
        };

        render(NotificationBell);

        const bellButton = screen.getByRole('button', { name: /notifications/i });
        await fireEvent.click(bellButton);
        await tick();

        expect(screen.getByText('Admission Offer')).toBeTruthy();
        expect(screen.getByText('Congratulations! You have been accepted for admission.')).toBeTruthy();
        expect(screen.queryByText(/Email accepted by mail service/i)).toBeNull();
    });
});
