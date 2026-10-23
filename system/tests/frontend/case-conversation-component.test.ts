// @vitest-environment jsdom

import { cleanup, fireEvent, render, waitFor } from '@testing-library/svelte';
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import CaseConversation from '../../resources/js/Components/Admission/CaseConversation.svelte';
import type {
    CaseConversationMessage,
    CaseConversationSnapshot,
} from '../../resources/js/lib/case-conversation';

type SnapshotOverrides = Omit<
    Partial<CaseConversationSnapshot>,
    'thread' | 'page'
> & {
    thread?: Partial<CaseConversationSnapshot['thread']>;
    page?: Partial<CaseConversationSnapshot['page']>;
};

function createSnapshot(
    overrides: SnapshotOverrides = {},
): CaseConversationSnapshot {
    const threadOverrides = overrides.thread ?? {};
    const pageOverrides = overrides.page ?? {};

    return {
        thread: {
            id: 'thread-101',
            subject: 'Consultation Assistance Request',
            thread_type: 'applicant_support',
            program_code: 'BSCS',
            status: 'open',
            can_read: true,
            can_send: true,
            ...threadOverrides,
        },
        messages: overrides.messages ?? [
            {
                id: 'msg-1',
                sequence: '100',
                sender: { display_name: 'Dr. Santos', is_self: false },
                body: 'Please review your preferred consultation schedule.',
                created_at: '2026-10-05T08:30:00Z',
            },
        ],
        page: {
            oldest_sequence: '100',
            newest_sequence: '100',
            has_older: false,
            has_more_after: false,
            ...pageOverrides,
        },
        latest_sequence: overrides.latest_sequence ?? '100',
        own_read_sequence: overrides.own_read_sequence ?? '100',
        unread_count: overrides.unread_count ?? 0,
        observation_token: overrides.observation_token ?? 'token-100',
        as_of: overrides.as_of ?? '2026-10-05T08:30:00Z',
    };
}

describe('CaseConversation component', () => {
    let originalLocation: Location;

    beforeEach(() => {
        vi.useFakeTimers();
        originalLocation = window.location;
        const windowTarget = window as unknown as Record<string, unknown>;
        delete windowTarget.location;
        windowTarget.location = new URL(
            'https://nexiam.test/consultation/applicants/1',
        ) as unknown as Location;
        document.body.innerHTML = '';
        Object.defineProperty(document, 'visibilityState', {
            value: 'visible',
            configurable: true,
        });
        Object.defineProperty(document, 'hidden', {
            value: false,
            configurable: true,
        });
    });

    afterEach(() => {
        cleanup();
        vi.unstubAllGlobals();
        vi.restoreAllMocks();
        vi.useRealTimers();
        window.location = originalLocation;
    });

    it('retains captured body and exact idempotency UUID across lost response, retrying with the same identity', async () => {
        type SendPayload = { body: string; idempotency_key: string };
        const fetchRequests: { url: string; body: SendPayload }[] = [];
        let failFirstSend = true;

        const mockFetch = vi.fn(
            async (input: RequestInfo | URL, init?: RequestInit) => {
                const urlString = input.toString();
                if (urlString.includes('/threads/thread-101/messages')) {
                    const parsedBody = JSON.parse(String(init?.body ?? '{}'));
                    fetchRequests.push({ url: urlString, body: parsedBody });

                    if (failFirstSend) {
                        failFirstSend = false;
                        throw new TypeError('Network connection lost');
                    }

                    const responseMessage: CaseConversationMessage = {
                        id: 'msg-client-1',
                        sequence: '101',
                        sender: { display_name: 'Applicant', is_self: true },
                        body: parsedBody.body,
                        created_at: '2026-10-05T08:35:00Z',
                    };

                    return {
                        ok: true,
                        status: 200,
                        headers: new Headers(),
                        json: async () => ({
                            message: responseMessage,
                            replayed: false,
                            as_of: '2026-10-05T08:35:00Z',
                        }),
                    } as unknown as Response;
                }

                if (urlString.includes('/case-conversations/thread-101')) {
                    return {
                        ok: true,
                        status: 200,
                        headers: new Headers(),
                        json: async () =>
                            createSnapshot({
                                latest_sequence: '101',
                                messages: [
                                    {
                                        id: 'msg-1',
                                        sequence: '100',
                                        sender: {
                                            display_name: 'Dr. Santos',
                                            is_self: false,
                                        },
                                        body: 'Please review your preferred consultation schedule.',
                                        created_at: '2026-10-05T08:30:00Z',
                                    },
                                ],
                            }),
                    } as unknown as Response;
                }

                return {
                    ok: true,
                    status: 200,
                    headers: new Headers(),
                    json: async () => ({}),
                } as unknown as Response;
            },
        );

        vi.stubGlobal('fetch', mockFetch);

        const view = render(CaseConversation, {
            initial: createSnapshot(),
            snapshotUrl: 'https://nexiam.test/case-conversations/thread-101',
            sendUrl: 'https://nexiam.test/threads/thread-101/messages',
            readUrl: 'https://nexiam.test/threads/thread-101/read',
        });

        const textarea = view.container.querySelector('textarea');
        expect(textarea).toBeInstanceOf(HTMLTextAreaElement);
        if (!(textarea instanceof HTMLTextAreaElement)) {
            throw new Error('Expected textarea element');
        }
        await fireEvent.input(textarea, {
            target: { value: 'Available this Thursday morning.' },
        });

        const sendButton = view.getByRole('button', { name: 'Send message' });
        await fireEvent.click(sendButton);

        // Network error was thrown: send must enter uncertain state
        await waitFor(() => {
            expect(
                view.getByText(/The message could not be confirmed/),
            ).not.toBeNull();
        });

        expect(fetchRequests).toHaveLength(1);
        const originalPayload = fetchRequests[0].body;
        expect(originalPayload.body).toBe('Available this Thursday morning.');
        expect(typeof originalPayload.idempotency_key).toBe('string');

        // No optimistic fake bubble has been marked as sent
        expect(
            view.queryByText('Available this Thursday morning. · You'),
        ).toBeNull();

        // Retry same message button is present
        const retryButton = view.getByRole('button', {
            name: 'Retry same message',
        });
        expect(retryButton).not.toBeNull();

        await fireEvent.click(retryButton);

        await waitFor(() => {
            expect(fetchRequests).toHaveLength(2);
        });

        // Exact same UUID and body were replayed on retry
        expect(fetchRequests[1].body.idempotency_key).toBe(
            originalPayload.idempotency_key,
        );
        expect(fetchRequests[1].body.body).toBe(
            'Available this Thursday morning.',
        );

        // On success, message is integrated into list and composer draft is cleared
        await waitFor(() => {
            expect(view.container.textContent).toContain(
                'Available this Thursday morning.',
            );
            expect(textarea.value).toBe('');
        });
    });

    it('deduplicates identical message IDs and preserves sequence order without duplicate bubbles', async () => {
        const initial = createSnapshot({
            messages: [
                {
                    id: 'msg-1',
                    sequence: '10',
                    sender: { display_name: 'Counselor', is_self: false },
                    body: 'Message number ten',
                    created_at: '2026-10-05T08:00:00Z',
                },
            ],
            page: {
                oldest_sequence: '10',
                newest_sequence: '10',
                has_older: false,
                has_more_after: false,
            },
        });

        const mockFetch = vi.fn(async (input: RequestInfo | URL) => {
            const urlString = input.toString();
            if (urlString.includes('after_sequence')) {
                return {
                    ok: true,
                    status: 200,
                    headers: new Headers(),
                    json: async () => ({
                        ...initial,
                        messages: [
                            // Duplicate of msg-1 with same id
                            {
                                id: 'msg-1',
                                sequence: '10',
                                sender: {
                                    display_name: 'Counselor',
                                    is_self: false,
                                },
                                body: 'Message number ten',
                                created_at: '2026-10-05T08:00:00Z',
                            },
                            {
                                id: 'msg-2',
                                sequence: '11',
                                sender: {
                                    display_name: 'Applicant',
                                    is_self: true,
                                },
                                body: 'Message number eleven',
                                created_at: '2026-10-05T08:05:00Z',
                            },
                        ],
                        page: {
                            oldest_sequence: '10',
                            newest_sequence: '11',
                            has_older: false,
                            has_more_after: false,
                        },
                    }),
                } as unknown as Response;
            }

            return {
                ok: true,
                status: 200,
                headers: new Headers(),
                json: async () => ({}),
            } as unknown as Response;
        });

        vi.stubGlobal('fetch', mockFetch);

        const view = render(CaseConversation, {
            initial,
            snapshotUrl: 'https://nexiam.test/case-conversations/thread-101',
            sendUrl: 'https://nexiam.test/threads/thread-101/messages',
            readUrl: 'https://nexiam.test/threads/thread-101/read',
        });

        // Trigger catch-up / poll
        const refreshButton = view.getByRole('button', { name: 'Refresh' });
        await fireEvent.click(refreshButton);

        await waitFor(() => {
            const messageElements =
                view.container.querySelectorAll('[data-message-id]');
            expect(messageElements).toHaveLength(2);
        });

        const msg1Elements = view.container.querySelectorAll(
            '[data-message-id="msg-1"]',
        );
        expect(msg1Elements).toHaveLength(1);
    });

    it('renders server-controlled closed thread with read-only explanation and no composer form', async () => {
        const closedSnapshot = createSnapshot({
            thread: {
                id: 'thread-closed',
                subject: 'Completed Case Review',
                thread_type: 'internal_ph_guidance',
                program_code: 'BSN',
                status: 'closed',
                can_read: true,
                can_send: false,
            },
        });

        const view = render(CaseConversation, {
            initial: closedSnapshot,
            snapshotUrl: 'https://nexiam.test/case-conversations/thread-closed',
            sendUrl: 'https://nexiam.test/threads/thread-closed/messages',
            readUrl: 'https://nexiam.test/threads/thread-closed/read',
        });

        expect(view.getByText(/This conversation is closed/)).not.toBeNull();
        expect(view.container.querySelector('textarea')).toBeNull();
        expect(view.queryByRole('button', { name: 'Send message' })).toBeNull();
        // Existing history remains readable
        expect(
            view.getByText(
                'Please review your preferred consultation schedule.',
            ),
        ).not.toBeNull();
    });

    it('retries the captured message with its original key after the thread closes', async () => {
        const sends: { body: string; idempotency_key: string }[] = [];
        const mockFetch = vi.fn(
            async (input: RequestInfo | URL, init?: RequestInit) => {
                if (input.toString().includes('/threads/thread-closed/messages')) {
                    const payload = JSON.parse(String(init?.body ?? '{}')) as {
                        body: string;
                        idempotency_key: string;
                    };
                    sends.push(payload);
                    if (sends.length === 1) {
                        throw new TypeError('Commit response was lost.');
                    }
                    return {
                        ok: true,
                        status: 200,
                        headers: new Headers(),
                        json: async () => ({
                            message: {
                                id: 'closed-replayed-message',
                                sequence: '101',
                                sender: {
                                    display_name: 'Applicant',
                                    is_self: true,
                                },
                                body: payload.body,
                                created_at: '2026-10-08T09:00:00Z',
                            },
                            replayed: true,
                        }),
                    } as unknown as Response;
                }
                return {
                    ok: true,
                    status: 200,
                    headers: new Headers(),
                    json: async () => ({}),
                } as unknown as Response;
            },
        );
        vi.stubGlobal('fetch', mockFetch);

        const initial = createSnapshot({
            thread: {
                id: 'thread-closed',
                status: 'open',
                can_read: true,
                can_send: true,
            },
        });
        const props = {
            initial,
            snapshotUrl:
                'https://nexiam.test/case-conversations/thread-closed',
            sendUrl: 'https://nexiam.test/threads/thread-closed/messages',
            readUrl: 'https://nexiam.test/threads/thread-closed/read',
            principalContext: 'opaque-principal',
            attemptContext: '["case",1,2,"2026","BSCS"]',
        };
        const view = render(CaseConversation, props);
        const textarea = view.container.querySelector('textarea');
        if (!(textarea instanceof HTMLTextAreaElement)) {
            throw new Error('Expected textarea element');
        }
        await fireEvent.input(textarea, {
            target: { value: 'Please confirm my submitted record.' },
        });
        await fireEvent.click(view.getByRole('button', { name: 'Send message' }));
        await waitFor(() => {
            expect(
                view.getByRole('button', { name: 'Retry same message' }),
            ).not.toBeNull();
        });
        const captured = sends[0];

        await view.rerender({
            ...props,
            initial: createSnapshot({
                thread: {
                    id: 'thread-closed',
                    status: 'closed',
                    can_read: true,
                    can_send: false,
                },
            }),
        });

        expect(view.getByText('closed')).not.toBeNull();
        expect(view.queryByRole('button', { name: 'Send message' })).toBeNull();
        expect(view.container.querySelector('textarea')).toBeNull();
        const retry = view.getByRole('button', {
            name: 'Retry same message',
        }) as HTMLButtonElement;
        expect(retry.disabled).toBe(false);
        await fireEvent.click(retry);

        await waitFor(() => {
            expect(sends).toHaveLength(2);
            expect(view.getByText('Message saved.')).not.toBeNull();
        });
        expect(sends[1]).toEqual(captured);
        expect(
            view.getByText('Please confirm my submitted record.'),
        ).not.toBeNull();
    });

    it('allows multiline entry with Enter newline and sends only on Ctrl+Enter or Cmd+Enter', async () => {
        let sentBody = '';
        const mockFetch = vi.fn(
            async (input: RequestInfo | URL, init?: RequestInit) => {
                if (input.toString().includes('/threads/thread-101/messages')) {
                    const parsed = JSON.parse(String(init?.body ?? '{}'));
                    sentBody = parsed.body;
                    return {
                        ok: true,
                        status: 200,
                        headers: new Headers(),
                        json: async () => ({
                            message: {
                                id: 'msg-multiline',
                                sequence: '105',
                                sender: {
                                    display_name: 'Applicant',
                                    is_self: true,
                                },
                                body: parsed.body,
                                created_at: '2026-10-05T08:40:00Z',
                            },
                        }),
                    } as unknown as Response;
                }

                return {
                    ok: true,
                    status: 200,
                    headers: new Headers(),
                    json: async () => createSnapshot(),
                } as unknown as Response;
            },
        );

        vi.stubGlobal('fetch', mockFetch);

        const view = render(CaseConversation, {
            initial: createSnapshot(),
            snapshotUrl: 'https://nexiam.test/case-conversations/thread-101',
            sendUrl: 'https://nexiam.test/threads/thread-101/messages',
            readUrl: 'https://nexiam.test/threads/thread-101/read',
        });

        const textarea = view.container.querySelector('textarea');
        expect(textarea).toBeInstanceOf(HTMLTextAreaElement);
        if (!(textarea instanceof HTMLTextAreaElement)) {
            throw new Error('Expected textarea element');
        }
        await fireEvent.input(textarea, {
            target: { value: 'First line\nSecond line' },
        });

        // Regular Enter keydown should NOT trigger submission
        await fireEvent.keyDown(textarea, {
            key: 'Enter',
            code: 'Enter',
            ctrlKey: false,
            metaKey: false,
        });
        expect(sentBody).toBe('');

        // Ctrl+Enter triggers submission
        await fireEvent.keyDown(textarea, {
            key: 'Enter',
            code: 'Enter',
            ctrlKey: true,
        });
        await waitFor(() => {
            expect(sentBody).toBe('First line\nSecond line');
        });
    });

    it('pauses periodic polling when tab is hidden and runs catch-up when visibility or focus returns', async () => {
        let pollCount = 0;
        const mockFetch = vi.fn(async (input: RequestInfo | URL) => {
            if (input.toString().includes('after_sequence')) {
                pollCount += 1;
                return {
                    ok: true,
                    status: 200,
                    headers: new Headers(),
                    json: async () => createSnapshot({ messages: [] }),
                } as unknown as Response;
            }
            return {
                ok: true,
                status: 200,
                headers: new Headers(),
                json: async () => ({}),
            } as unknown as Response;
        });

        vi.stubGlobal('fetch', mockFetch);

        render(CaseConversation, {
            initial: createSnapshot(),
            snapshotUrl: 'https://nexiam.test/case-conversations/thread-101',
            sendUrl: 'https://nexiam.test/threads/thread-101/messages',
            readUrl: 'https://nexiam.test/threads/thread-101/read',
        });

        // 5s periodic timer elapses
        await vi.advanceTimersByTimeAsync(5_100);
        expect(pollCount).toBe(1);

        // Hide document
        Object.defineProperty(document, 'visibilityState', {
            value: 'hidden',
            configurable: true,
        });
        Object.defineProperty(document, 'hidden', {
            value: true,
            configurable: true,
        });
        document.dispatchEvent(new Event('visibilitychange'));

        // Advance 20 seconds while hidden: no polls should fire
        await vi.advanceTimersByTimeAsync(20_000);
        expect(pollCount).toBe(1);

        // Restore visibility: immediate catch-up occurs
        Object.defineProperty(document, 'visibilityState', {
            value: 'visible',
            configurable: true,
        });
        Object.defineProperty(document, 'hidden', {
            value: false,
            configurable: true,
        });
        document.dispatchEvent(new Event('visibilitychange'));

        await waitFor(() => {
            expect(pollCount).toBe(2);
        });
    });

    it('isolates slow stale case responses when switching conversation contexts', async () => {
        const { promise: thread1Promise, resolve: resolveThread1 } =
            Promise.withResolvers<void>();

        const mockFetch = vi.fn(async (input: RequestInfo | URL) => {
            const urlString = input.toString();
            if (urlString.includes('thread-101')) {
                await thread1Promise;
                return {
                    ok: true,
                    status: 200,
                    headers: new Headers(),
                    json: async () => ({
                        ...createSnapshot(),
                        messages: [
                            {
                                id: 'leaked-msg',
                                sequence: '999',
                                sender: {
                                    display_name: 'Wrong Case',
                                    is_self: false,
                                },
                                body: 'This should not appear in thread 202',
                                created_at: '2026-10-05T09:00:00Z',
                            },
                        ],
                    }),
                } as unknown as Response;
            }

            return {
                ok: true,
                status: 200,
                headers: new Headers(),
                json: async () => ({}),
            } as unknown as Response;
        });

        vi.stubGlobal('fetch', mockFetch);

        const thread1Snapshot = createSnapshot();
        const view = render(CaseConversation, {
            initial: thread1Snapshot,
            snapshotUrl: 'https://nexiam.test/case-conversations/thread-101',
            sendUrl: 'https://nexiam.test/threads/thread-101/messages',
            readUrl: 'https://nexiam.test/threads/thread-101/read',
        });

        // Trigger manual catch-up for thread 1 which will hang on thread1Promise
        const refresh = view.getByRole('button', { name: 'Refresh' });
        await fireEvent.click(refresh);

        // Switch to thread 202
        const thread2Snapshot = createSnapshot({
            thread: {
                id: 'thread-202',
                subject: 'Nursing Program Consultation',
                thread_type: 'applicant_support',
                program_code: 'BSN',
                status: 'open',
                can_read: true,
                can_send: true,
            },
            messages: [
                {
                    id: 'msg-clean-202',
                    sequence: '50',
                    sender: { display_name: 'Dean Flores', is_self: false },
                    body: 'Thread 202 initial message',
                    created_at: '2026-10-05T09:00:00Z',
                },
            ],
        });

        await view.rerender({
            initial: thread2Snapshot,
            snapshotUrl: 'https://nexiam.test/case-conversations/thread-202',
            sendUrl: 'https://nexiam.test/threads/thread-202/messages',
            readUrl: 'https://nexiam.test/threads/thread-202/read',
        });

        // Thread 1 finally finishes resolving late
        resolveThread1();
        await vi.advanceTimersByTimeAsync(0);

        // Thread 1 data must NOT bleed into Thread 2
        expect(
            view.queryByText('This should not appear in thread 202'),
        ).toBeNull();
        expect(view.getByText('Thread 202 initial message')).not.toBeNull();
    });

    it('clears protected state and stops operations when session expires (401/419)', async () => {
        const mockFetch = vi.fn(async (input: RequestInfo | URL) => {
            if (input.toString().includes('after_sequence')) {
                return {
                    ok: false,
                    status: 419,
                    headers: new Headers(),
                    json: async () => ({ message: 'CSRF token mismatch' }),
                } as unknown as Response;
            }
            return {
                ok: true,
                status: 200,
                headers: new Headers(),
                json: async () => ({}),
            } as unknown as Response;
        });

        vi.stubGlobal('fetch', mockFetch);

        const view = render(CaseConversation, {
            initial: createSnapshot(),
            snapshotUrl: 'https://nexiam.test/case-conversations/thread-101',
            sendUrl: 'https://nexiam.test/threads/thread-101/messages',
            readUrl: 'https://nexiam.test/threads/thread-101/read',
        });

        const refresh = view.getByRole('button', { name: 'Refresh' });
        await fireEvent.click(refresh);

        await waitFor(() => {
            expect(view.getByText(/Your session has expired/)).not.toBeNull();
        });

        // Protected messages and composer are wiped
        expect(
            view.queryByText(
                'Please review your preferred consultation schedule.',
            ),
        ).toBeNull();
        expect(view.container.querySelector('textarea')).toBeNull();
    });
    it('retains pending drafts across same-context refreshes and thread switches', async () => {
        const sendRequests: { body: string; idempotency_key: string }[] = [];
        const mockFetch = vi.fn(
            async (input: RequestInfo | URL, init?: RequestInit) => {
                const url = input.toString();
                if (url.includes('/threads/thread-101/messages')) {
                    const body = JSON.parse(String(init?.body ?? '{}')) as {
                        body: string;
                        idempotency_key: string;
                    };
                    sendRequests.push(body);

                    if (sendRequests.length === 1) {
                        throw new TypeError('Connection lost');
                    }

                    return {
                        ok: true,
                        status: 200,
                        headers: new Headers(),
                        json: async () => ({
                            message: {
                                id: 'msg-retried',
                                sequence: '101',
                                sender: {
                                    display_name: 'Applicant',
                                    is_self: true,
                                },
                                body: body.body,
                                created_at: '2026-10-05T09:00:00Z',
                            },
                        }),
                    } as unknown as Response;
                }

                return {
                    ok: true,
                    status: 200,
                    headers: new Headers(),
                    json: async () => createSnapshot({ messages: [] }),
                } as unknown as Response;
            },
        );
        vi.stubGlobal('fetch', mockFetch);

        const threadOne = createSnapshot();
        const threadOneProps = {
            initial: threadOne,
            snapshotUrl: 'https://nexiam.test/case-conversations/thread-101',
            sendUrl: 'https://nexiam.test/threads/thread-101/messages',
            readUrl: 'https://nexiam.test/threads/thread-101/read',
            principalContext: 'principal-1',
            attemptContext: 'attempt-1',
        };
        const view = render(CaseConversation, threadOneProps);
        const textarea = view.container.querySelector('textarea');
        if (!(textarea instanceof HTMLTextAreaElement)) {
            throw new Error('Expected textarea element');
        }

        await fireEvent.input(textarea, {
            target: { value: 'A reply that must remain pending.' },
        });
        await fireEvent.click(
            view.getByRole('button', { name: 'Send message' }),
        );
        await waitFor(() => {
            expect(
                view.getByRole('button', { name: 'Retry same message' }),
            ).not.toBeNull();
        });

        await view.rerender({
            ...threadOneProps,
            initial: createSnapshot({
                as_of: '2026-10-05T09:10:00Z',
            }),
        });
        expect(textarea.value).toBe('A reply that must remain pending.');
        expect(
            view.getByRole('button', { name: 'Retry same message' }),
        ).not.toBeNull();

        const threadTwo = createSnapshot({
            thread: {
                id: 'thread-202',
                subject: 'Another case thread',
                thread_type: 'applicant_support',
                program_code: 'BSN',
                status: 'open',
                can_read: true,
                can_send: true,
            },
            messages: [],
            page: {
                oldest_sequence: null,
                newest_sequence: null,
            },
        });
        await view.rerender({
            initial: threadTwo,
            snapshotUrl: 'https://nexiam.test/case-conversations/thread-202',
            sendUrl: 'https://nexiam.test/threads/thread-202/messages',
            readUrl: 'https://nexiam.test/threads/thread-202/read',
            principalContext: 'principal-1',
            attemptContext: 'attempt-1',
        });
        expect(
            view.queryByRole('button', { name: 'Retry same message' }),
        ).toBeNull();
        expect(textarea.value).toBe('');

        await view.rerender(threadOneProps);
        expect(textarea.value).toBe('A reply that must remain pending.');
        expect(
            view.getByRole('button', { name: 'Retry same message' }),
        ).not.toBeNull();

        await fireEvent.click(
            view.getByRole('button', { name: 'Retry same message' }),
        );
        await waitFor(() => {
            expect(sendRequests).toHaveLength(2);
        });
        expect(sendRequests[1]).toEqual(sendRequests[0]);
    });

    it('clears drafts on principal change and rejects a late prior-principal receipt', async () => {
        const { promise: sendPromise, resolve: resolveSend } =
            Promise.withResolvers<Response>();
        const mockFetch = vi.fn(async (input: RequestInfo | URL) => {
            if (input.toString().includes('/threads/thread-101/messages')) {
                return sendPromise;
            }
            return {
                ok: true,
                status: 200,
                headers: new Headers(),
                json: async () => createSnapshot({ messages: [] }),
            } as unknown as Response;
        });
        vi.stubGlobal('fetch', mockFetch);

        const initial = createSnapshot();
        const props = {
            initial,
            snapshotUrl: 'https://nexiam.test/case-conversations/thread-101',
            sendUrl: 'https://nexiam.test/threads/thread-101/messages',
            readUrl: 'https://nexiam.test/threads/thread-101/read',
            principalContext: 'principal-1',
            attemptContext: 'attempt-1',
        };
        const view = render(CaseConversation, props);
        const textarea = view.container.querySelector('textarea');
        if (!(textarea instanceof HTMLTextAreaElement)) {
            throw new Error('Expected textarea element');
        }
        await fireEvent.input(textarea, {
            target: { value: 'Pending under the old principal.' },
        });
        await fireEvent.click(
            view.getByRole('button', { name: 'Send message' }),
        );

        await view.rerender({
            ...props,
            initial: createSnapshot({
                messages: [
                    {
                        id: 'new-principal-message',
                        sequence: '200',
                        sender: {
                            display_name: 'Current participant',
                            is_self: false,
                        },
                        body: 'Current principal history.',
                        created_at: '2026-10-05T09:10:00Z',
                    },
                ],
                page: {
                    oldest_sequence: '200',
                    newest_sequence: '200',
                },
            }),
            principalContext: 'principal-2',
        });

        expect(textarea.value).toBe('');
        expect(view.queryByText(/Pending under the old principal/)).toBeNull();

        resolveSend({
            ok: true,
            status: 200,
            headers: new Headers(),
            json: async () => ({
                message: {
                    id: 'late-old-principal-receipt',
                    sequence: '201',
                    sender: { display_name: 'Old principal', is_self: true },
                    body: 'Late old-principal message.',
                    created_at: '2026-10-05T09:20:00Z',
                },
            }),
        } as unknown as Response);
        await vi.advanceTimersByTimeAsync(0);

        expect(view.getByText('Current principal history.')).not.toBeNull();
        expect(view.queryByText('Late old-principal message.')).toBeNull();
        expect(view.queryByText('Message saved.')).toBeNull();
    });

    it('exposes keyboard scrolling through a named focusable region around the semantic list', () => {
        const view = render(CaseConversation, {
            initial: createSnapshot(),
            snapshotUrl: 'https://nexiam.test/case-conversations/thread-101',
            sendUrl: 'https://nexiam.test/threads/thread-101/messages',
            readUrl: 'https://nexiam.test/threads/thread-101/read',
        });
        const region = view.getByRole('region', {
            name: 'Conversation messages',
        });

        expect(region.getAttribute('tabindex')).toBe('0');
        expect(region.querySelector('ol')).not.toBeNull();
        expect(region.querySelector('ol')?.getAttribute('aria-live')).toBe(
            'off',
        );
    });

    it('does not let manual or focus refresh bypass a Retry-After deadline over sixty seconds', async () => {
        let pollCount = 0;
        const mockFetch = vi.fn(async (input: RequestInfo | URL) => {
            if (input.toString().includes('after_sequence')) {
                pollCount += 1;
                return {
                    ok: pollCount > 1,
                    status: pollCount > 1 ? 200 : 429,
                    headers: new Headers(
                        pollCount > 1 ? {} : { 'Retry-After': '120' },
                    ),
                    json: async () => createSnapshot({ messages: [] }),
                } as unknown as Response;
            }
            return {
                ok: true,
                status: 200,
                headers: new Headers(),
                json: async () => ({}),
            } as unknown as Response;
        });
        vi.stubGlobal('fetch', mockFetch);

        const view = render(CaseConversation, {
            initial: createSnapshot(),
            snapshotUrl: 'https://nexiam.test/case-conversations/thread-101',
            sendUrl: 'https://nexiam.test/threads/thread-101/messages',
            readUrl: 'https://nexiam.test/threads/thread-101/read',
        });
        await fireEvent.click(view.getByRole('button', { name: 'Refresh' }));
        await waitFor(() => expect(pollCount).toBe(1));
        await waitFor(() => {
            expect(view.container.textContent).toContain(
                'Conversation may be out of date',
            );
        });
        await fireEvent.click(view.getByRole('button', { name: 'Refresh' }));
        window.dispatchEvent(new Event('focus'));
        expect(pollCount).toBe(1);

        await vi.advanceTimersByTimeAsync(119_999);
        expect(pollCount).toBe(1);
        await vi.advanceTimersByTimeAsync(1);
        await waitFor(() => expect(pollCount).toBe(2));
    });

    it('acknowledges only its captured visible boundary and aborts on hidden tabs', async () => {
        vi.spyOn(document, 'hasFocus').mockReturnValue(true);
        const { promise: readPromise, resolve: resolveRead } =
            Promise.withResolvers<Response>();
        let readRequest:
            | {
                  signal: AbortSignal | null;
                  body: { observed_sequence: string };
              }
            | undefined;
        const initial = createSnapshot({
            messages: [
                {
                    id: 'msg-10',
                    sequence: '10',
                    sender: { display_name: 'Staff', is_self: false },
                    body: 'Observed message ten.',
                    created_at: '2026-10-05T08:30:00Z',
                },
            ],
            page: {
                oldest_sequence: '10',
                newest_sequence: '10',
            },
            own_read_sequence: '0',
            unread_count: 1,
            observation_token: 'token-10',
            latest_sequence: '10',
        });
        const mockFetch = vi.fn(
            async (input: RequestInfo | URL, init?: RequestInit) => {
                const url = input.toString();
                if (url.includes('/threads/thread-101/read')) {
                    readRequest = {
                        signal: init?.signal as AbortSignal | null,
                        body: JSON.parse(String(init?.body ?? '{}')) as {
                            observed_sequence: string;
                        },
                    };
                    return readPromise;
                }
                if (url.includes('after_sequence')) {
                    return {
                        ok: true,
                        status: 200,
                        headers: new Headers(),
                        json: async () =>
                            createSnapshot({
                                messages: [
                                    {
                                        id: 'msg-11',
                                        sequence: '11',
                                        sender: {
                                            display_name: 'Applicant',
                                            is_self: false,
                                        },
                                        body: 'Newly arrived message eleven.',
                                        created_at: '2026-10-05T08:31:00Z',
                                    },
                                ],
                                page: {
                                    oldest_sequence: '11',
                                    newest_sequence: '11',
                                },
                                latest_sequence: '11',
                                observation_token: 'token-11',
                            }),
                    } as unknown as Response;
                }
                return {
                    ok: true,
                    status: 200,
                    headers: new Headers(),
                    json: async () => ({}),
                } as unknown as Response;
            },
        );
        vi.stubGlobal('fetch', mockFetch);

        const view = render(CaseConversation, {
            initial,
            snapshotUrl: 'https://nexiam.test/case-conversations/thread-101',
            sendUrl: 'https://nexiam.test/threads/thread-101/messages',
            readUrl: 'https://nexiam.test/threads/thread-101/read',
        });
        const region = view.getByRole('region', {
            name: 'Conversation messages',
        });
        const newestMessage = region.querySelector(
            '[data-message-id="msg-10"]',
        );
        if (!(newestMessage instanceof HTMLElement)) {
            throw new Error('Expected the newest message in the scroll region');
        }
        vi.spyOn(region, 'getBoundingClientRect').mockReturnValue({
            x: 0,
            y: 0,
            top: 0,
            right: 400,
            bottom: 200,
            left: 0,
            width: 400,
            height: 200,
            toJSON: () => ({}),
        } as DOMRect);
        vi.spyOn(newestMessage, 'getBoundingClientRect').mockReturnValue({
            x: 0,
            y: 160,
            top: 160,
            right: 400,
            bottom: 190,
            left: 0,
            width: 400,
            height: 30,
            toJSON: () => ({}),
        } as DOMRect);
        Object.defineProperty(region, 'scrollHeight', {
            value: 200,
            configurable: true,
        });
        Object.defineProperty(region, 'clientHeight', {
            value: 200,
            configurable: true,
        });
        await fireEvent.scroll(region);
        expect(readRequest).toBeDefined();
        expect(readRequest?.body.observed_sequence).toBe('10');

        await fireEvent.click(view.getByRole('button', { name: 'Refresh' }));
        await waitFor(() => {
            expect(
                view.getByText('Newly arrived message eleven.'),
            ).not.toBeNull();
        });
        expect(readRequest?.body.observed_sequence).toBe('10');

        Object.defineProperty(document, 'visibilityState', {
            value: 'hidden',
            configurable: true,
        });
        Object.defineProperty(document, 'hidden', {
            value: true,
            configurable: true,
        });
        document.dispatchEvent(new Event('visibilitychange'));
        expect(readRequest?.signal?.aborted).toBe(true);

        resolveRead({
            ok: true,
            status: 200,
            headers: new Headers(),
            json: async () => ({
                own_read_sequence: '10',
                unread_count: 1,
            }),
        } as unknown as Response);
        await vi.advanceTimersByTimeAsync(0);
        expect(readRequest?.body.observed_sequence).toBe('10');
    });
    it('acknowledges a newly displayed boundary after a successful prior ack', async () => {
        vi.spyOn(document, 'hasFocus').mockReturnValue(true);
        const readBodies: {
            observed_sequence: string;
            observation_token: string;
        }[] = [];
        const mockFetch = vi.fn(
            async (input: RequestInfo | URL, init?: RequestInit) => {
                const url = new URL(input.toString());
                if (url.pathname.endsWith('/read')) {
                    readBodies.push(
                        JSON.parse(String(init?.body ?? '{}')) as {
                            observed_sequence: string;
                            observation_token: string;
                        },
                    );
                    return {
                        ok: true,
                        status: 200,
                        headers: new Headers(),
                        json: async () => ({
                            own_read_sequence:
                                readBodies.at(-1)?.observed_sequence,
                            unread_count: 0,
                        }),
                    } as unknown as Response;
                }
                if (url.searchParams.has('after_sequence')) {
                    return {
                        ok: true,
                        status: 200,
                        headers: new Headers(),
                        json: async () =>
                            createSnapshot({
                                messages: [
                                    {
                                        id: 'msg-101',
                                        sequence: '101',
                                        sender: {
                                            display_name: 'Applicant',
                                            is_self: false,
                                        },
                                        body: 'The next displayed boundary.',
                                        created_at: '2026-10-05T08:31:00Z',
                                    },
                                ],
                                page: {
                                    oldest_sequence: '101',
                                    newest_sequence: '101',
                                },
                                latest_sequence: '101',
                                own_read_sequence: '100',
                                unread_count: 1,
                                observation_token: 'token-101',
                            }),
                    } as unknown as Response;
                }
                return {
                    ok: true,
                    status: 200,
                    headers: new Headers(),
                    json: async () => ({}),
                } as unknown as Response;
            },
        );
        vi.stubGlobal('fetch', mockFetch);

        const view = render(CaseConversation, {
            initial: createSnapshot({
                own_read_sequence: '99',
                unread_count: 1,
            }),
            snapshotUrl: 'https://nexiam.test/case-conversations/thread-101',
            sendUrl: 'https://nexiam.test/threads/thread-101/messages',
            readUrl: 'https://nexiam.test/threads/thread-101/read',
        });
        const region = view.getByRole('region', {
            name: 'Conversation messages',
        });
        vi.spyOn(region, 'getBoundingClientRect').mockReturnValue({
            x: 0,
            y: 0,
            top: 0,
            right: 400,
            bottom: 200,
            left: 0,
            width: 400,
            height: 200,
            toJSON: () => ({}),
        } as DOMRect);
        Object.defineProperty(region, 'scrollHeight', {
            value: 200,
            configurable: true,
        });
        Object.defineProperty(region, 'clientHeight', {
            value: 200,
            configurable: true,
        });
        const firstMessage = region.querySelector('[data-message-id="msg-1"]');
        if (!(firstMessage instanceof HTMLElement)) {
            throw new Error('Expected the initial boundary message');
        }
        vi.spyOn(firstMessage, 'getBoundingClientRect').mockReturnValue({
            x: 0,
            y: 160,
            top: 160,
            right: 400,
            bottom: 190,
            left: 0,
            width: 400,
            height: 30,
            toJSON: () => ({}),
        } as DOMRect);

        await fireEvent.scroll(region);
        await waitFor(() => expect(readBodies).toHaveLength(1));
        expect(readBodies[0]).toEqual({
            observed_sequence: '100',
            observation_token: 'token-100',
        });

        await fireEvent.click(view.getByRole('button', { name: 'Refresh' }));
        await waitFor(() =>
            expect(
                view.getByText('The next displayed boundary.'),
            ).not.toBeNull(),
        );
        await waitFor(() => expect(readBodies).toHaveLength(2));
        expect(readBodies[1]).toEqual({
            observed_sequence: '101',
            observation_token: 'token-101',
        });
    });

    it('starts a fresh boundary ack after an earlier ack fails', async () => {
        const hasFocus = vi.spyOn(document, 'hasFocus').mockReturnValue(false);
        const { promise: firstReadPromise, resolve: resolveFirstRead } =
            Promise.withResolvers<Response>();
        const readSequences: string[] = [];
        const mockFetch = vi.fn(
            async (input: RequestInfo | URL, init?: RequestInit) => {
                if (input.toString().includes('/read')) {
                    const body = JSON.parse(String(init?.body ?? '{}')) as {
                        observed_sequence: string;
                    };
                    readSequences.push(body.observed_sequence);
                    if (readSequences.length === 1) {
                        return firstReadPromise;
                    }
                    return {
                        ok: true,
                        status: 200,
                        headers: new Headers(),
                        json: async () => ({
                            own_read_sequence: '100',
                            unread_count: 0,
                        }),
                    } as unknown as Response;
                }
                return {
                    ok: true,
                    status: 200,
                    headers: new Headers(),
                    json: async () => ({}),
                } as unknown as Response;
            },
        );
        vi.stubGlobal('fetch', mockFetch);
        const view = render(CaseConversation, {
            initial: createSnapshot({
                own_read_sequence: '99',
                unread_count: 1,
            }),
            snapshotUrl: 'https://nexiam.test/case-conversations/thread-101',
            sendUrl: 'https://nexiam.test/threads/thread-101/messages',
            readUrl: 'https://nexiam.test/threads/thread-101/read',
        });
        const region = view.getByRole('region', {
            name: 'Conversation messages',
        });
        vi.spyOn(region, 'getBoundingClientRect').mockReturnValue({
            x: 0,
            y: 0,
            top: 0,
            right: 400,
            bottom: 200,
            left: 0,
            width: 400,
            height: 200,
            toJSON: () => ({}),
        } as DOMRect);
        Object.defineProperty(region, 'scrollHeight', {
            value: 200,
            configurable: true,
        });
        Object.defineProperty(region, 'clientHeight', {
            value: 200,
            configurable: true,
        });
        const newestMessage = region.querySelector('[data-message-id="msg-1"]');
        if (!(newestMessage instanceof HTMLElement)) {
            throw new Error('Expected the initial boundary message');
        }
        vi.spyOn(newestMessage, 'getBoundingClientRect').mockReturnValue({
            x: 0,
            y: 160,
            top: 160,
            right: 400,
            bottom: 190,
            left: 0,
            width: 400,
            height: 30,
            toJSON: () => ({}),
        } as DOMRect);
        hasFocus.mockReturnValue(true);

        await fireEvent.scroll(region);
        await waitFor(() => expect(readSequences).toHaveLength(1));
        resolveFirstRead({
            ok: false,
            status: 503,
            headers: new Headers(),
            json: async () => ({ code: 'temporarily_unavailable' }),
        } as unknown as Response);
        await vi.advanceTimersByTimeAsync(0);
        await fireEvent.scroll(region);
        await waitFor(() => expect(readSequences).toHaveLength(2));
        expect(readSequences).toEqual(['100', '100']);
    });

    it('renews an expired observation token from an authorized displayed snapshot before retrying its ack', async () => {
        vi.spyOn(document, 'hasFocus').mockReturnValue(true);
        const readBodies: {
            observed_sequence: string;
            observation_token: string;
        }[] = [];
        const deltaUrls: string[] = [];
        const snapshotUrls: string[] = [];
        const mockFetch = vi.fn(
            async (input: RequestInfo | URL, init?: RequestInit) => {
                const url = new URL(input.toString());
                if (url.pathname.endsWith('/read')) {
                    readBodies.push(
                        JSON.parse(String(init?.body ?? '{}')) as {
                            observed_sequence: string;
                            observation_token: string;
                        },
                    );
                    if (readBodies.length === 1) {
                        return {
                            ok: false,
                            status: 422,
                            headers: new Headers(),
                            json: async () => ({ code: 'validation_failed' }),
                        } as unknown as Response;
                    }
                    return {
                        ok: true,
                        status: 200,
                        headers: new Headers(),
                        json: async () => ({
                            own_read_sequence: '100',
                            unread_count: 0,
                        }),
                    } as unknown as Response;
                }
                if (url.searchParams.has('after_sequence')) {
                    deltaUrls.push(url.toString());
                    return {
                        ok: true,
                        status: 200,
                        headers: new Headers(),
                        json: async () =>
                            createSnapshot({
                                messages: [],
                                page: {
                                    oldest_sequence: null,
                                    newest_sequence: null,
                                    has_more_after: false,
                                },
                                latest_sequence: '100',
                                observation_token: 'empty-delta-token-0',
                                own_read_sequence: '99',
                                unread_count: 1,
                            }),
                    } as unknown as Response;
                }
                snapshotUrls.push(url.toString());
                return {
                    ok: true,
                    status: 200,
                    headers: new Headers(),
                    json: async () =>
                        createSnapshot({
                            own_read_sequence: '99',
                            unread_count: 1,
                            observation_token: 'renewed-token-100',
                        }),
                } as unknown as Response;
            },
        );
        vi.stubGlobal('fetch', mockFetch);
        const view = render(CaseConversation, {
            initial: createSnapshot({
                own_read_sequence: '99',
                unread_count: 1,
                observation_token: 'expired-token-100',
            }),
            snapshotUrl: 'https://nexiam.test/case-conversations/thread-101',
            sendUrl: 'https://nexiam.test/threads/thread-101/messages',
            readUrl: 'https://nexiam.test/threads/thread-101/read',
        });
        const region = view.getByRole('region', {
            name: 'Conversation messages',
        });
        vi.spyOn(region, 'getBoundingClientRect').mockReturnValue({
            x: 0,
            y: 0,
            top: 0,
            right: 400,
            bottom: 200,
            left: 0,
            width: 400,
            height: 200,
            toJSON: () => ({}),
        } as DOMRect);
        Object.defineProperty(region, 'scrollHeight', {
            value: 200,
            configurable: true,
        });
        Object.defineProperty(region, 'clientHeight', {
            value: 200,
            configurable: true,
        });
        const newestMessage = region.querySelector('[data-message-id="msg-1"]');
        if (!(newestMessage instanceof HTMLElement)) {
            throw new Error('Expected the displayed boundary message');
        }
        vi.spyOn(newestMessage, 'getBoundingClientRect').mockReturnValue({
            x: 0,
            y: 160,
            top: 160,
            right: 400,
            bottom: 190,
            left: 0,
            width: 400,
            height: 30,
            toJSON: () => ({}),
        } as DOMRect);

        await fireEvent.click(view.getByRole('button', { name: 'Refresh' }));
        await waitFor(() => expect(deltaUrls).toHaveLength(1));
        await fireEvent.scroll(region);
        await waitFor(() => expect(readBodies).toHaveLength(2));
        expect(readBodies).toEqual([
            {
                observed_sequence: '100',
                observation_token: 'expired-token-100',
            },
            {
                observed_sequence: '100',
                observation_token: 'renewed-token-100',
            },
        ]);
        expect(snapshotUrls).toHaveLength(1);
        const renewalUrl = new URL(snapshotUrls[0]);
        expect(renewalUrl.pathname).toBe('/case-conversations/thread-101');
        expect(renewalUrl.searchParams.has('after_sequence')).toBe(false);
        expect(renewalUrl.searchParams.has('before_sequence')).toBe(false);
    });

    it('keeps older pages reachable beyond the rendered cap and restores the latest window on jump', async () => {
        const beforeCursors: string[] = [];
        const olderWindows: Record<string, [number, number]> = {
            '501': [451, 500],
            '451': [401, 450],
            '951': [901, 950],
        };
        const latestSnapshotUrls: string[] = [];
        const makeMessage = (sequence: number): CaseConversationMessage => ({
            id: `msg-${sequence}`,
            sequence: String(sequence),
            sender: { display_name: 'Staff', is_self: false },
            body: `History message ${sequence}.`,
            created_at: '2026-10-05T08:30:00Z',
        });
        const mockFetch = vi.fn(async (input: RequestInfo | URL) => {
            const url = new URL(input.toString());
            if (url.searchParams.has('before_sequence')) {
                const cursor = url.searchParams.get('before_sequence') ?? '';
                beforeCursors.push(cursor);
                const [firstSequence, lastSequence] = olderWindows[cursor] ?? [
                    351, 400,
                ];
                return {
                    ok: true,
                    status: 200,
                    headers: new Headers(),
                    json: async () =>
                        createSnapshot({
                            messages: Array.from(
                                { length: lastSequence - firstSequence + 1 },
                                (_, index) =>
                                    makeMessage(firstSequence + index),
                            ),
                            page: {
                                oldest_sequence: String(firstSequence),
                                newest_sequence: String(lastSequence),
                                has_older: true,
                            },
                            latest_sequence: '1000',
                            own_read_sequence: '500',
                            unread_count: 500,
                            observation_token: `token-${lastSequence}`,
                        }),
                } as unknown as Response;
            }
            latestSnapshotUrls.push(url.toString());
            return {
                ok: true,
                status: 200,
                headers: new Headers(),
                json: async () =>
                    createSnapshot({
                        messages: Array.from({ length: 50 }, (_, index) =>
                            makeMessage(951 + index),
                        ),
                        page: {
                            oldest_sequence: '951',
                            newest_sequence: '1000',
                            has_older: true,
                        },
                        latest_sequence: '1000',
                        own_read_sequence: '500',
                        unread_count: 500,
                        observation_token: 'latest-token-1000',
                    }),
            } as unknown as Response;
        });
        vi.stubGlobal('fetch', mockFetch);
        const initial = createSnapshot({
            messages: Array.from({ length: 500 }, (_, index) =>
                makeMessage(501 + index),
            ),
            page: {
                oldest_sequence: '501',
                newest_sequence: '1000',
                has_older: true,
            },
            latest_sequence: '1000',
            own_read_sequence: '500',
            unread_count: 500,
        });
        const view = render(CaseConversation, {
            initial,
            snapshotUrl: 'https://nexiam.test/case-conversations/thread-101',
            sendUrl: 'https://nexiam.test/threads/thread-101/messages',
            readUrl: 'https://nexiam.test/threads/thread-101/read',
        });
        const region = view.getByRole('region', {
            name: 'Conversation messages',
        });
        vi.spyOn(region, 'getBoundingClientRect').mockReturnValue({
            x: 0,
            y: 0,
            top: 0,
            right: 400,
            bottom: 200,
            left: 0,
            width: 400,
            height: 200,
            toJSON: () => ({}),
        } as DOMRect);
        Object.defineProperty(region, 'scrollHeight', {
            value: 10_000,
            configurable: true,
        });
        Object.defineProperty(region, 'clientHeight', {
            value: 200,
            configurable: true,
        });
        Object.defineProperty(region, 'scrollTop', {
            value: 500,
            writable: true,
            configurable: true,
        });

        await fireEvent.click(
            view.getByRole('button', { name: 'Load older messages' }),
        );
        await vi.waitFor(() =>
            expect(view.getByText('History message 451.')).not.toBeNull(),
        );
        expect(beforeCursors).toEqual(['501']);
        await vi.advanceTimersByTimeAsync(0);
        await vi.waitFor(() => {
            const olderButton = view.getByRole('button', {
                name: 'Load older messages',
            }) as HTMLButtonElement;
            expect(olderButton.disabled).toBe(false);
        });
        expect(beforeCursors).toEqual(['501']);

        await fireEvent.click(
            view.getByRole('button', { name: 'Load older messages' }),
        );
        expect(beforeCursors).toEqual(['501', '451']);
        await vi.waitFor(() =>
            expect(view.getByText('History message 401.')).not.toBeNull(),
        );
        await vi.waitFor(() => {
            const latestButton = view.getByRole('button', {
                name: /Return to latest messages|Jump to latest/,
            }) as HTMLButtonElement;
            expect(latestButton.disabled).toBe(false);
        });
        await fireEvent.click(
            view.getByRole('button', {
                name: /Return to latest messages|Jump to latest/,
            }),
        );
        expect(latestSnapshotUrls).toHaveLength(1);
        await vi.waitFor(() =>
            expect(view.getByText('History message 1000.')).not.toBeNull(),
        );
        expect(latestSnapshotUrls).toHaveLength(1);
        const latestUrl = new URL(latestSnapshotUrls[0]);
        expect(latestUrl.searchParams.has('after_sequence')).toBe(false);
        expect(latestUrl.searchParams.has('before_sequence')).toBe(false);
        expect(view.getByText('500 unread messages')).not.toBeNull();
        await vi.waitFor(() => {
            const olderButton = view.getByRole('button', {
                name: 'Load older messages',
            }) as HTMLButtonElement;
            expect(olderButton.disabled).toBe(false);
        });
        await fireEvent.click(
            view.getByRole('button', { name: 'Load older messages' }),
        );
        expect(beforeCursors).toEqual(['501', '451', '951']);
        await vi.waitFor(() =>
            expect(view.getByText('History message 901.')).not.toBeNull(),
        );
    });
    it('warns before unload only when an unsent draft would be lost', async () => {
        const view = render(CaseConversation, {
            initial: createSnapshot(),
            snapshotUrl: 'https://nexiam.test/case-conversations/thread-101',
            sendUrl: 'https://nexiam.test/threads/thread-101/messages',
            readUrl: 'https://nexiam.test/threads/thread-101/read',
            principalContext: 'opaque-principal',
            attemptContext: '["case",1,2,"2026","BSCS"]',
        });
        const first = new Event('beforeunload', { cancelable: true });
        window.dispatchEvent(first);
        expect(first.defaultPrevented).toBe(false);

        const textarea = view.container.querySelector('textarea');
        if (!(textarea instanceof HTMLTextAreaElement)) {
            throw new Error('Expected textarea element');
        }
        await fireEvent.input(textarea, {
            target: { value: 'Unsaved protected draft.' },
        });
        const dirty = new Event('beforeunload', { cancelable: true });
        window.dispatchEvent(dirty);
        expect(dirty.defaultPrevented).toBe(true);
    });
});
