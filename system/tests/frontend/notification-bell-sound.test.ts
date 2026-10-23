// @vitest-environment jsdom

import { cleanup, fireEvent, render, screen } from '@testing-library/svelte';
import { tick } from 'svelte';
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';

const soundMocks = vi.hoisted(() => ({ notification: vi.fn() }));
const inertiaMocks = vi.hoisted(() => ({
    page: { props: { auth: { user: { is_applicant: true } } }, url: '/applicant/portal' },
    reload: vi.fn((options: Record<string, unknown> = {}) => {
        if (typeof options.onSuccess === 'function') {
            (options.onSuccess as () => void)();
        }
        if (typeof options.onFinish === 'function') {
            (options.onFinish as () => void)();
        }
    }),
}));

vi.mock('../../resources/js/lib/sound.js', () => ({ sound: soundMocks }));
vi.mock('@inertiajs/svelte', async (importOriginal) => {
    const actual = await importOriginal<Record<string, unknown>>();
    const actualRouter = (actual.router as Record<string, unknown>) ?? {};

    return {
        ...actual,
        usePage: () => inertiaMocks.page,
        usePoll: () => ({ start: vi.fn(), stop: vi.fn() }),
        router: { ...actualRouter, reload: inertiaMocks.reload },
    };
});

import NotificationBell from '../../resources/js/Components/kit/NotificationBell.svelte';

let originalVisibilityStateDescriptor: PropertyDescriptor | undefined;

function makeNotification(id: string, read = false): Record<string, unknown> {
    return {
        id,
        subject: 'Test notification',
        body: 'Test notification body',
        read,
        created_at: '2026-10-16T10:00:00Z',
        status: 'delivered',
        channel: 'in_app',
        failed: false,
        action_url: null,
    };
}

beforeEach(() => {
    originalVisibilityStateDescriptor = Object.getOwnPropertyDescriptor(document, 'visibilityState');
    Object.defineProperty(document, 'visibilityState', { configurable: true, value: 'visible' });
    vi.clearAllMocks();
});

afterEach(() => {
    cleanup();
    if (originalVisibilityStateDescriptor) {
        Object.defineProperty(document, 'visibilityState', originalVisibilityStateDescriptor);
    } else {
        Reflect.deleteProperty(document, 'visibilityState');
    }
    vi.restoreAllMocks();
});

describe('NotificationBell arrival sound', () => {
    it('plays one cue for a batch of new unread IDs after a silent baseline, without relying on count growth', async () => {
        const initial = {
            unread_count: 2,
            recent: [makeNotification('old-1'), makeNotification('old-2')],
            queue_active: false,
        };
        const view = render(NotificationBell, { props: { notifications: initial } });

        expect(soundMocks.notification).not.toHaveBeenCalled();

        const arrived = {
            unread_count: 2,
            recent: [
                makeNotification('new-1'),
                makeNotification('new-2'),
                makeNotification('old-1', true),
                makeNotification('old-2', true),
            ],
            queue_active: false,
        };
        await view.rerender({ notifications: arrived });
        await tick();
        expect(soundMocks.notification).toHaveBeenCalledOnce();

        await view.rerender({ notifications: arrived });
        await tick();
        expect(soundMocks.notification).toHaveBeenCalledOnce();

        await view.rerender({
            notifications: {
                ...arrived,
                unread_count: 1,
                recent: [
                    makeNotification('new-1', true),
                    makeNotification('new-2'),
                    makeNotification('old-1', true),
                    makeNotification('old-2', true),
                ],
            },
        });
        await tick();
        expect(soundMocks.notification).toHaveBeenCalledOnce();
    });

    it('does not replay an arrival observed while the popover is open', async () => {
        const view = render(NotificationBell, {
            props: {
                notifications: {
                    unread_count: 1,
                    recent: [makeNotification('existing')],
                    queue_active: false,
                },
            },
        });

        await fireEvent.click(screen.getByRole('button', { name: /^Notifications/i }));
        await tick();
        await view.rerender({
            notifications: {
                unread_count: 2,
                recent: [makeNotification('new'), makeNotification('existing')],
                queue_active: false,
            },
        });
        await tick();

        expect(soundMocks.notification).not.toHaveBeenCalled();

        await fireEvent.click(screen.getByRole('button', { name: /^Notifications/i }));
        await tick();
        expect(soundMocks.notification).not.toHaveBeenCalled();
    });

    it('stays silent for hidden-tab arrivals and enforces the five-second cooldown', async () => {
        const now = vi.spyOn(Date, 'now').mockReturnValue(10_000);
        const view = render(NotificationBell, {
            props: {
                notifications: { unread_count: 0, recent: [], queue_active: false },
            },
        });

        Object.defineProperty(document, 'visibilityState', { configurable: true, value: 'hidden' });
        await view.rerender({
            notifications: {
                unread_count: 1,
                recent: [makeNotification('hidden')],
                queue_active: false,
            },
        });
        await tick();
        expect(soundMocks.notification).not.toHaveBeenCalled();

        Object.defineProperty(document, 'visibilityState', { configurable: true, value: 'visible' });
        now.mockReturnValue(11_000);
        await view.rerender({
            notifications: {
                unread_count: 2,
                recent: [makeNotification('hidden'), makeNotification('visible-1')],
                queue_active: false,
            },
        });
        await tick();
        expect(soundMocks.notification).toHaveBeenCalledOnce();

        now.mockReturnValue(15_999);
        await view.rerender({
            notifications: {
                unread_count: 3,
                recent: [
                    makeNotification('hidden'),
                    makeNotification('visible-1'),
                    makeNotification('cooldown'),
                ],
                queue_active: false,
            },
        });
        await tick();
        expect(soundMocks.notification).toHaveBeenCalledOnce();

        now.mockReturnValue(16_000);
        await view.rerender({
            notifications: {
                unread_count: 4,
                recent: [
                    makeNotification('hidden'),
                    makeNotification('visible-1'),
                    makeNotification('cooldown'),
                    makeNotification('after-cooldown'),
                ],
                queue_active: false,
            },
        });
        await tick();
        expect(soundMocks.notification).toHaveBeenCalledTimes(2);
    });
});
