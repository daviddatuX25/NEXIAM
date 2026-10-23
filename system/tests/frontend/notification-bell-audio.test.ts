// @vitest-environment jsdom
import { cleanup, fireEvent, render, screen, waitFor } from '@testing-library/svelte';
import { tick } from 'svelte';
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import { sound } from '../../resources/js/lib/sound.js';

const inertiaMocks = vi.hoisted(() => ({
    page: { props: { auth: { user: { is_applicant: false } } }, url: '/staff' },
    reload: vi.fn(),
}));

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
        subject: 'Exam Session Started',
        body: 'The exam session is in progress.',
        read,
        created_at: '2026-10-16T10:00:00Z',
        status: 'delivered',
        channel: 'in_app',
        failed: false,
        action_url: '/exams/monitoring',
    };
}

function createSuspendedAudioContext() {
    let resumeContext!: () => void;
    const context = {
        state: 'suspended',
        currentTime: 0,
        destination: {},
        createOscillator: vi.fn().mockReturnValue({
            type: 'sine',
            frequency: {
                setValueAtTime: vi.fn(),
                exponentialRampToValueAtTime: vi.fn(),
            },
            connect: vi.fn(),
            start: vi.fn(),
            stop: vi.fn(),
        }),
        createGain: vi.fn().mockReturnValue({
            gain: {
                setValueAtTime: vi.fn(),
                linearRampToValueAtTime: vi.fn(),
                exponentialRampToValueAtTime: vi.fn(),
            },
            connect: vi.fn(),
        }),
        resume: vi.fn(
            () =>
                new Promise<void>((resolve) => {
                    resumeContext = () => {
                        context.state = 'running';
                        resolve();
                    };
                }),
        ),
    };

    return { context, resumeContext: () => resumeContext() };
}

describe('NotificationBell sound integration', () => {
    beforeEach(() => {
        originalVisibilityStateDescriptor = Object.getOwnPropertyDescriptor(
            document,
            'visibilityState',
        );
        Object.defineProperty(document, 'visibilityState', {
            configurable: true,
            value: 'visible',
        });
        sound.enabled = true;
        localStorage.setItem('snd_sine_enabled', 'true');
    });

    afterEach(() => {
        cleanup();
        sound.ctx = null;
        sound.enabled = false;
        localStorage.removeItem('snd_sine_enabled');
        if (originalVisibilityStateDescriptor) {
            Object.defineProperty(
                document,
                'visibilityState',
                originalVisibilityStateDescriptor,
            );
        } else {
            Reflect.deleteProperty(document, 'visibilityState');
        }
        vi.restoreAllMocks();
    });

    it('schedules a new-unread cue after a suspended context resumes', async () => {
        const { context, resumeContext } = createSuspendedAudioContext();
        sound.ctx = context as unknown as typeof sound.ctx;
        const view = render(NotificationBell, {
            props: {
                notifications: {
                    unread_count: 1,
                    recent: [makeNotification('existing')],
                    queue_active: false,
                },
            },
        });

        expect(context.createOscillator).not.toHaveBeenCalled();

        await view.rerender({
            notifications: {
                unread_count: 2,
                recent: [makeNotification('new'), makeNotification('existing')],
                queue_active: false,
            },
        });
        await tick();
        expect(context.resume).toHaveBeenCalledOnce();
        expect(context.createOscillator).not.toHaveBeenCalled();

        resumeContext();
        await waitFor(() =>
            expect(context.createOscillator).toHaveBeenCalledOnce(),
        );
        expect(context.createGain).toHaveBeenCalledOnce();
    });

    it('does not play after the tab becomes hidden while audio is resuming', async () => {
        const { context, resumeContext } = createSuspendedAudioContext();
        sound.ctx = context as unknown as typeof sound.ctx;
        const view = render(NotificationBell, {
            props: {
                notifications: {
                    unread_count: 1,
                    recent: [makeNotification('existing')],
                    queue_active: false,
                },
            },
        });

        await view.rerender({
            notifications: {
                unread_count: 2,
                recent: [makeNotification('new'), makeNotification('existing')],
                queue_active: false,
            },
        });
        await tick();
        Object.defineProperty(document, 'visibilityState', {
            configurable: true,
            value: 'hidden',
        });

        resumeContext();
        await Promise.resolve();
        await tick();

        expect(context.createOscillator).not.toHaveBeenCalled();
    });

    it('does not play after the bell opens while audio is resuming', async () => {
        const { context, resumeContext } = createSuspendedAudioContext();
        sound.ctx = context as unknown as typeof sound.ctx;
        const view = render(NotificationBell, {
            props: {
                notifications: {
                    unread_count: 1,
                    recent: [makeNotification('existing')],
                    queue_active: false,
                },
            },
        });

        await view.rerender({
            notifications: {
                unread_count: 2,
                recent: [makeNotification('new'), makeNotification('existing')],
                queue_active: false,
            },
        });
        await tick();
        await fireEvent.click(screen.getByRole('button', { name: /^Notifications/i }));
        resumeContext();
        await Promise.resolve();
        await tick();

        expect(context.createOscillator).not.toHaveBeenCalled();
    });

    it('does not play after the unread arrival is marked read while audio is resuming', async () => {
        const { context, resumeContext } = createSuspendedAudioContext();
        sound.ctx = context as unknown as typeof sound.ctx;
        const view = render(NotificationBell, {
            props: {
                notifications: {
                    unread_count: 1,
                    recent: [makeNotification('existing')],
                    queue_active: false,
                },
            },
        });

        await view.rerender({
            notifications: {
                unread_count: 2,
                recent: [makeNotification('new'), makeNotification('existing')],
                queue_active: false,
            },
        });
        await tick();
        await view.rerender({
            notifications: {
                unread_count: 1,
                recent: [makeNotification('new', true), makeNotification('existing')],
                queue_active: false,
            },
        });
        await tick();

        resumeContext();
        await Promise.resolve();
        await tick();

        expect(context.createOscillator).not.toHaveBeenCalled();
    });
});
