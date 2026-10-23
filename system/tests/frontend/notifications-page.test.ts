// @vitest-environment jsdom

import { cleanup, fireEvent, render, screen } from '@testing-library/svelte';
import { tick } from 'svelte';
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import NotificationsIndex from '../../resources/js/Pages/Notifications/Index.svelte';

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

const mockPageProps: {
    auth: {
        user: {
            id: number;
            name: string;
            is_applicant: boolean;
        };
    };
    staff_navigation: unknown[];
} = {
    auth: {
        user: {
            id: 1,
            name: 'Jane Applicant',
            is_applicant: true,
        },
    },
    staff_navigation: [],
};

vi.mock('@inertiajs/svelte', async (importOriginal) => {
    const actual = await importOriginal<Record<string, unknown>>();
    const actualRouter = (actual.router as Record<string, unknown>) ?? {};

    return {
        ...actual,
        usePage: () => ({ props: mockPageProps, url: '/notifications' }),
        router: {
            ...actualRouter,
            reload: (...args: unknown[]) => mockRouterReload(...args),
            post: (...args: unknown[]) => mockRouterPost(...args),
            visit: (...args: unknown[]) => mockRouterVisit(...args),
        },
    };
});

describe('Notifications Index Page', () => {
    beforeEach(() => {
        vi.clearAllMocks();
        mockPageProps.auth.user.is_applicant = true;
        vi.stubGlobal('ResizeObserver', class {
            observe() {}
            unobserve() {}
            disconnect() {}
        });
    });

    afterEach(() => {
        cleanup();
    });

    it('renders notifications with formatted timestamp and without in-app labels', async () => {
        const notifications = {
            unread_count: 1,
            queue_active: false,
            recent: [
                {
                    id: 'notif-1',
                    subject: 'Schedule Booked',
                    body: 'Your slot is confirmed.',
                    read: false,
                    created_at: '2026-08-22T10:03:00Z',
                    status: 'delivered',
                    delivered_at: '2026-08-22T10:03:00Z',
                    channel: 'in_app',
                    failed: false,
                    action_url: null,
                },
            ],
        };

        render(NotificationsIndex, { props: { notifications } });

        // Shows formatted date and time: Aug. 22 · 10:03 AM
        expect(screen.getByText(/Aug\.\s*22\s*·/i)).toBeTruthy();

        // In-app labels like "In-app available" or "In-app queued" must NOT be rendered
        expect(screen.queryByText(/in-app available/i)).toBeNull();
        expect(screen.queryByText(/in-app queued/i)).toBeNull();

        // Check header Refresh action
        const refreshBtn = screen.getByRole('button', { name: /refresh/i });
        expect(refreshBtn).toBeTruthy();
        await fireEvent.click(refreshBtn);
        await tick();

        expect(mockRouterReload).toHaveBeenCalledWith(
            expect.objectContaining({
                only: ['notifications'],
            }),
        );
    });

    it('shows subtle QA message when queue engine is working', async () => {
        const notifications = {
            unread_count: 0,
            queue_active: true,
            recent: [
                {
                    id: 'notif-2',
                    subject: 'Application Dispatched',
                    body: 'Verification notice processing.',
                    read: true,
                    created_at: '2026-08-22T10:05:00Z',
                    status: 'processing',
                    channel: 'in_app',
                    failed: false,
                    action_url: null,
                },
            ],
        };

        render(NotificationsIndex, { props: { notifications } });

        // QA message must be rendered
        expect(screen.getByText(/Queue worker active/i)).toBeTruthy();
        expect(screen.getByText(/Background delivery in progress/i)).toBeTruthy();
    });

    it('does not display email delivery status tag on applicant portal notifications', async () => {
        const notifications = {
            unread_count: 1,
            queue_active: false,
            recent: [
                {
                    id: 'notif-offer',
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

        render(NotificationsIndex, { props: { notifications } });

        expect(screen.getByText('Admission Offer')).toBeTruthy();
        expect(screen.getByText('Congratulations! You have been accepted for admission.')).toBeTruthy();

        // Must NOT render any email delivery status tag like "Email accepted by mail service"
        expect(screen.queryByText(/Email accepted by mail service/i)).toBeNull();
        expect(screen.queryByText(/Email/i)).toBeNull();
    });
});
