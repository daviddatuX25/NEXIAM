<script lang="ts">
    import { onMount, tick, untrack } from 'svelte';
    import { router } from '@inertiajs/svelte';
    import { Lock, RefreshCw, UserCheck } from 'lucide-svelte';
    import { Button } from '@/Components/ui/button';
    import { Textarea } from '@/Components/ui/textarea';
    import { csrfHeaders, newIdempotencyKey } from '@/lib/utils.js';
    import {
        capturePendingCaseMessage,
        caseConversationBackoffMs,
        caseConversationDraftKey,
        caseConversationNeedsNavigationConfirmation,
        mergeCaseMessages,
        nextCaseMessageCursor,
        type CaseConversationMessage,
        type CaseConversationSnapshot,
    } from '@/lib/case-conversation';

    type AccessState = 'available' | 'session_expired' | 'access_lost';
    type SendState =
        | 'idle'
        | 'sending'
        | 'uncertain'
        | 'validation_error'
        | 'conflict'
        | 'error';
    type SnapshotMode = 'delta' | 'older';
    type RequestKind = 'snapshot' | 'send' | 'read';

    type RequestFailureKind = 'network' | 'timeout' | 'aborted';

    class CaseConversationRequestFailure extends Error {
        kind: RequestFailureKind;

        constructor(kind: RequestFailureKind) {
            super(kind);
            this.name = 'CaseConversationRequestFailure';
            this.kind = kind;
        }
    }

    type JsonResponse = {
        response: Response;
        payload: unknown;
    };

    let {
        initial,
        snapshotUrl,
        sendUrl,
        readUrl,
        principalContext = null,
        attemptContext = null,
    } = $props<{
        initial: CaseConversationSnapshot;
        snapshotUrl: string;
        sendUrl: string;
        readUrl: string;
        principalContext?: string | number | null;
        attemptContext?: string | number | null;
    }>();

    const capturedInitial = untrack(() => initial);

    const POLL_INTERVAL_MS = 5_000;
    const REQUEST_TIMEOUT_MS = 10_000;
    const MAX_TIMER_DELAY_MS = 2_147_000_000;
    const MAX_RENDERED_MESSAGES = 500;
    const BOTTOM_TOLERANCE_PX = 32;

    let conversation = $state<CaseConversationSnapshot | null>(capturedInitial);
    let messages = $state<CaseConversationMessage[]>(capturedInitial.messages);
    let draft = $state('');
    let pending = $state<{ body: string; idempotencyKey: string } | null>(null);
    let sendState = $state<SendState>('idle');
    let accessState = $state<AccessState>('available');
    let feedback = $state('');
    let stale = $state(false);
    let staleCopy = $state('');
    let olderLoading = $state(false);
    let readBusy = $state(false);
    let readRetryRequested = false;
    let sendBusy = $state(false);
    let atBottom = $state(true);
    let newMessageCount = $state(0);
    let abandonRequested = $state(false);
    let retryAvailableAt = $state(0);
    let pollRetryAvailableAt = $state(0);
    let currentObservationToken = $state(capturedInitial.observation_token);
    let observationSequence = $state<string | null>(
        capturedInitial.page.newest_sequence,
    );
    let latestWindowEvicted = $state(false);
    let olderHistoryAvailable = $state(capturedInitial.page.has_older);

    let messageListElement: HTMLElement | null = $state(null);
    let pollTimer: number | null = null;
    let retryTimer: number | null = null;
    let snapshotController: AbortController | null = null;
    let activeSnapshotMode: SnapshotMode | null = null;
    let catchupRequested = false;
    let snapshotBusy = $state(false);
    let sendController: AbortController | null = null;
    let readController: AbortController | null = null;
    let pollFailures = 0;
    let sendFailures = 0;
    let pollCursor = capturedInitial.page.newest_sequence ?? '0';
    let mounted = false;
    let generation = 0;
    let readOperationSequence = 0;
    let activeReadOperation = 0;
    let lastRejectedObservationToken = '';

    type ThreadDraftState = {
        draft: string;
        pending: { body: string; idempotencyKey: string } | null;
        sendState: SendState;
        feedback: string;
        abandonRequested: boolean;
        retryAvailableAt: number;
    };

    const threadDrafts = new Map<string, ThreadDraftState>();

    function effectivePrincipal(
        value: string | number | null | undefined,
        threadId: string,
    ): string {
        return value === null || value === undefined
            ? `unknown-principal:${threadId}`
            : String(value);
    }

    function effectiveAttempt(
        value: string | number | null | undefined,
        threadId: string,
    ): string {
        return value === null || value === undefined
            ? `unknown-attempt:${threadId}`
            : String(value);
    }

    function scopeFor(
        snapshot: CaseConversationSnapshot,
        principal: string,
        attempt: string,
        readUrlValue: string,
        sendUrlValue: string,
        snapshotUrlValue: string,
    ): string {
        return `case\u0000${principal}\u0000${attempt}\u0000${snapshot.thread.id}\u0000${snapshotUrlValue}\u0000${sendUrlValue}\u0000${readUrlValue}`;
    }

    let activePrincipal = effectivePrincipal(
        untrack(() => principalContext),
        capturedInitial.thread.id,
    );
    let activeAttempt = effectiveAttempt(
        untrack(() => attemptContext),
        capturedInitial.thread.id,
    );
    let activeScope = scopeFor(
        capturedInitial,
        activePrincipal,
        activeAttempt,
        untrack(() => readUrl),
        untrack(() => sendUrl),
        untrack(() => snapshotUrl),
    );
    let activeDraftKey = caseConversationDraftKey(
        activePrincipal,
        activeAttempt,
        capturedInitial.thread.id,
        'case',
    );
    let seenInitial = capturedInitial;

    let canRead = $derived(
        accessState === 'available' && conversation?.thread.can_read === true,
    );
    let canSend = $derived(
        canRead &&
            conversation?.thread.status === 'open' &&
            conversation?.thread.can_send === true,
    );

    $effect(() => {
        const nextInitial = initial;
        const nextPrincipal = effectivePrincipal(
            principalContext,
            nextInitial.thread.id,
        );
        const nextAttempt = effectiveAttempt(
            attemptContext,
            nextInitial.thread.id,
        );
        const nextScope = scopeFor(
            nextInitial,
            nextPrincipal,
            nextAttempt,
            readUrl,
            sendUrl,
            snapshotUrl,
        );

        untrack(() => {
            if (nextScope !== activeScope) {
                if (nextPrincipal !== activePrincipal) {
                    threadDrafts.clear();
                } else if (nextAttempt !== activeAttempt) {
                    threadDrafts.clear();
                } else {
                    threadDrafts.set(activeDraftKey, {
                        draft,
                        pending,
                        sendState: sendBusy ? 'uncertain' : sendState,
                        feedback,
                        abandonRequested,
                        retryAvailableAt,
                    });
                }

                activeScope = nextScope;
                activePrincipal = nextPrincipal;
                activeAttempt = nextAttempt;
                activeDraftKey = caseConversationDraftKey(
                    nextPrincipal,
                    nextAttempt,
                    nextInitial.thread.id,
                    'case',
                );
                seenInitial = nextInitial;
                resetForContext(nextInitial, threadDrafts.get(activeDraftKey));
            } else if (nextInitial !== seenInitial) {
                seenInitial = nextInitial;
                refreshInitialSnapshot(nextInitial);
            }
        });
    });

    function isCurrent(requestGeneration: number): boolean {
        return (
            mounted &&
            generation === requestGeneration &&
            accessState === 'available'
        );
    }

    function clearTimer(timer: number | null): void {
        if (timer !== null) {
            window.clearTimeout(timer);
        }
    }

    function scheduleLongTimer(
        delayMs: number,
        callback: () => void,
        setHandle: (handle: number | null) => void,
    ): void {
        let remaining = Math.max(0, delayMs);

        const wait = (): void => {
            if (!mounted) {
                setHandle(null);
                return;
            }

            if (remaining <= 0) {
                setHandle(null);
                callback();
                return;
            }

            const delay = Math.min(remaining, MAX_TIMER_DELAY_MS);
            remaining -= delay;
            setHandle(window.setTimeout(wait, delay));
        };

        wait();
    }

    function clearScheduledWork(): void {
        clearTimer(pollTimer);
        clearTimer(retryTimer);
        pollTimer = null;
        retryTimer = null;
        retryAvailableAt = 0;
        pollRetryAvailableAt = 0;
    }

    function abortRequests(): void {
        snapshotController?.abort();
        sendController?.abort();
        readController?.abort();
        snapshotController = null;
        sendController = null;
        readController = null;
    }

    function cancelReadOperation(): void {
        readController?.abort();
        readOperationSequence += 1;
        activeReadOperation = readOperationSequence;
        readBusy = false;
        readRetryRequested = false;
    }

    function resetForContext(
        nextInitial: CaseConversationSnapshot,
        savedDraft?: ThreadDraftState,
    ): void {
        const savedRetryAvailableAt = savedDraft?.retryAvailableAt ?? 0;
        generation += 1;
        clearScheduledWork();
        abortRequests();
        snapshotBusy = false;
        activeSnapshotMode = null;
        catchupRequested = false;
        sendBusy = false;
        readBusy = false;
        olderLoading = false;
        conversation = nextInitial;
        messages = nextInitial.messages;
        draft = savedDraft?.draft ?? '';
        pending = savedDraft?.pending ?? null;
        sendState = savedDraft?.sendState ?? 'idle';
        accessState = 'available';
        feedback = savedDraft?.feedback ?? '';
        stale = false;
        staleCopy = '';
        atBottom = true;
        newMessageCount = 0;
        abandonRequested = savedDraft?.abandonRequested ?? false;
        latestWindowEvicted = false;
        olderHistoryAvailable = nextInitial.page.has_older;
        readRetryRequested = false;
        pollFailures = 0;
        readOperationSequence += 1;
        activeReadOperation = readOperationSequence;
        lastRejectedObservationToken = '';
        currentObservationToken = nextInitial.observation_token;
        observationSequence = nextInitial.page.newest_sequence;

        if (savedRetryAvailableAt > Date.now()) {
            setRetryWait(savedRetryAvailableAt - Date.now());
        }

        if (mounted) {
            if (nextInitial.thread.can_read) {
                schedulePoll(POLL_INTERVAL_MS);
            } else {
                loseAccess();
            }
        }
    }

    function refreshInitialSnapshot(
        nextInitial: CaseConversationSnapshot,
    ): void {
        if (!nextInitial.thread.can_read) {
            loseAccess();
            return;
        }

        const previous = conversation;
        const merged = mergeCaseMessages(messages, nextInitial.messages);
        const newest = merged.at(-1)?.sequence ?? null;
        olderHistoryAvailable =
            olderHistoryAvailable || nextInitial.page.has_older;
        conversation = {
            ...nextInitial,
            messages: merged,
            page: {
                ...nextInitial.page,
                oldest_sequence: merged[0]?.sequence ?? null,
                newest_sequence: newest,
                has_older: olderHistoryAvailable,
                has_more_after:
                    previous?.page.has_more_after ??
                    nextInitial.page.has_more_after,
            },
        };
        messages = merged;
        if (
            nextInitial.page.newest_sequence &&
            (observationSequence === null ||
                BigInt(nextInitial.page.newest_sequence) >=
                    BigInt(observationSequence)) &&
            newest === nextInitial.page.newest_sequence
        ) {
            currentObservationToken = nextInitial.observation_token;
            observationSequence = nextInitial.page.newest_sequence;
        }
        if (newest && BigInt(newest) > BigInt(pollCursor)) {
            pollCursor = newest;
        }
    }

    function sameOriginUrl(rawUrl: string): URL {
        const url = new URL(rawUrl, window.location.href);
        if (url.origin !== window.location.origin) {
            throw new Error('Conversation requests must stay on this origin.');
        }
        return url;
    }

    function snapshotRequestUrl(
        cursorName: 'after_sequence' | 'before_sequence',
        cursor: string,
    ): string {
        const url = sameOriginUrl(snapshotUrl);
        url.searchParams.set(cursorName, cursor);
        return url.toString();
    }

    async function requestJson(
        url: string,
        init: RequestInit,
        kind: RequestKind,
        requestGeneration: number,
    ): Promise<JsonResponse> {
        const target = sameOriginUrl(url);
        const controller = new AbortController();
        let timedOut = false;

        if (kind === 'snapshot') {
            snapshotController = controller;
        } else if (kind === 'send') {
            sendController = controller;
        } else {
            readController = controller;
        }

        const timeout = window.setTimeout(() => {
            timedOut = true;
            controller.abort();
        }, REQUEST_TIMEOUT_MS);

        try {
            const response = await fetch(target.toString(), {
                ...init,
                credentials: 'same-origin',
                mode: 'same-origin',
                cache: 'no-store',
                signal: controller.signal,
            });
            const payload = await response.json().catch(() => null);
            return { response, payload };
        } catch {
            if (timedOut) {
                throw new CaseConversationRequestFailure('timeout');
            }
            if (controller.signal.aborted || generation !== requestGeneration) {
                throw new CaseConversationRequestFailure('aborted');
            }
            throw new CaseConversationRequestFailure('network');
        } finally {
            window.clearTimeout(timeout);
            if (kind === 'snapshot' && snapshotController === controller) {
                snapshotController = null;
            } else if (kind === 'send' && sendController === controller) {
                sendController = null;
            } else if (kind === 'read' && readController === controller) {
                readController = null;
            }
        }
    }

    function requestHeaders(json = false): HeadersInit {
        const headers = new Headers({
            Accept: 'application/json',
            'X-Requested-With': 'XMLHttpRequest',
            ...(json ? { 'Content-Type': 'application/json' } : {}),
        });

        for (const [name, value] of Object.entries(csrfHeaders())) {
            if (typeof value === 'string') {
                headers.set(name, value);
            }
        }

        return headers;
    }

    function retryAfterMilliseconds(headers: Headers): number | null {
        const value = headers.get('Retry-After')?.trim();
        if (!value) {
            return null;
        }

        if (/^\d+$/.test(value)) {
            const milliseconds = Number(value) * 1_000;
            return Number.isFinite(milliseconds) ? milliseconds : null;
        }

        const timestamp = Date.parse(value);
        if (!Number.isFinite(timestamp)) {
            return null;
        }

        return Math.max(0, timestamp - Date.now());
    }

    function setRetryWait(delayMs: number): void {
        clearTimer(retryTimer);
        retryTimer = null;
        retryAvailableAt = Date.now() + delayMs;

        if (delayMs <= 0) {
            retryAvailableAt = 0;
            return;
        }

        scheduleLongTimer(
            delayMs,
            () => {
                retryAvailableAt = 0;
                retryTimer = null;
            },
            (handle) => {
                retryTimer = handle;
            },
        );
    }

    function schedulePoll(delayMs: number): void {
        clearTimer(pollTimer);
        pollTimer = null;

        if (!mounted || !canRead || !isVisible()) {
            return;
        }

        scheduleLongTimer(
            delayMs,
            () => {
                pollTimer = null;
                void requestCatchup();
            },
            (handle) => {
                pollTimer = handle;
            },
        );
    }

    function isVisible(): boolean {
        return (
            typeof document !== 'undefined' &&
            document.visibilityState !== 'hidden' &&
            !document.hidden
        );
    }

    function hasForegroundFocus(): boolean {
        return (
            isVisible() &&
            (typeof document.hasFocus !== 'function' || document.hasFocus())
        );
    }

    function handleSnapshotAccessFailure(status: number): boolean {
        if (status === 401 || status === 419) {
            loseSession();
            return true;
        }
        if (status === 403 || status === 404) {
            loseAccess();
            return true;
        }
        return false;
    }

    function loseSession(): void {
        generation += 1;
        clearScheduledWork();
        abortRequests();
        clearProtectedState();
        accessState = 'session_expired';
        feedback =
            'Your session has expired. Sign in again to continue. Unsaved protected conversation state has been cleared.';
    }

    function loseAccess(): void {
        generation += 1;
        clearScheduledWork();
        abortRequests();
        clearProtectedState();
        accessState = 'access_lost';
        feedback = 'This conversation is no longer available.';
    }

    function clearProtectedState(): void {
        threadDrafts.clear();
        snapshotBusy = false;
        activeSnapshotMode = null;
        catchupRequested = false;
        sendBusy = false;
        readBusy = false;
        olderLoading = false;
        conversation = null;
        messages = [];
        draft = '';
        pending = null;
        sendState = 'idle';
        stale = false;
        staleCopy = '';
        currentObservationToken = '';
        observationSequence = null;
        pollCursor = '0';
        newMessageCount = 0;
        readRetryRequested = false;
        olderHistoryAvailable = false;
        lastRejectedObservationToken = '';
        latestWindowEvicted = false;
        abandonRequested = false;
        retryAvailableAt = 0;
        pollRetryAvailableAt = 0;
    }

    function isSnapshot(value: unknown): value is CaseConversationSnapshot {
        if (!value || typeof value !== 'object') {
            return false;
        }
        const candidate = value as Partial<CaseConversationSnapshot>;
        return Boolean(
            candidate.thread &&
            Array.isArray(candidate.messages) &&
            candidate.page,
        );
    }

    function isMessage(value: unknown): value is CaseConversationMessage {
        if (!value || typeof value !== 'object') {
            return false;
        }
        const candidate = value as Partial<CaseConversationMessage>;
        return (
            typeof candidate.id === 'string' &&
            typeof candidate.sequence === 'string' &&
            typeof candidate.body === 'string' &&
            typeof candidate.created_at === 'string' &&
            Boolean(
                candidate.sender &&
                typeof candidate.sender.display_name === 'string' &&
                typeof candidate.sender.is_self === 'boolean',
            )
        );
    }

    type HttpFailureWithRetry = { retryAfter: number | null };

    function isHttpFailureWithRetry(
        value: unknown,
    ): value is HttpFailureWithRetry {
        return (
            typeof value === 'object' && value !== null && 'retryAfter' in value
        );
    }

    type ReadReceipt = {
        own_read_sequence: string;
        unread_count: number;
        as_of?: string;
    };

    function isReadReceipt(value: unknown): value is ReadReceipt {
        if (!value || typeof value !== 'object') {
            return false;
        }
        return (
            'own_read_sequence' in value &&
            typeof value.own_read_sequence === 'string' &&
            'unread_count' in value &&
            typeof value.unread_count === 'number'
        );
    }

    type SendReceipt = {
        message: CaseConversationMessage;
        replayed?: boolean;
        as_of?: string;
    };

    function isSendReceipt(value: unknown): value is SendReceipt {
        if (!value || typeof value !== 'object' || !('message' in value)) {
            return false;
        }
        return isMessage(value.message);
    }
    function mergeIncoming(
        incoming: CaseConversationMessage[],
        mode: SnapshotMode,
    ): number {
        const unseenCount = incoming.reduce(
            (count, message) =>
                messages.some((existing) => existing.id === message.id)
                    ? count
                    : count + 1,
            0,
        );
        const merged = mergeCaseMessages(messages, incoming);
        const prunedCount = Math.max(0, merged.length - MAX_RENDERED_MESSAGES);
        if (mode === 'older') {
            messages =
                prunedCount > 0
                    ? merged.slice(0, MAX_RENDERED_MESSAGES)
                    : merged;
        } else {
            messages = prunedCount > 0 ? merged.slice(prunedCount) : merged;
        }
        if (prunedCount > 0 && conversation) {
            if (mode === 'older') {
                latestWindowEvicted = true;
            }
            conversation = {
                ...conversation,
                page: { ...conversation.page, has_older: true },
            };
        }
        if (mode === 'older' && atBottom) {
            atBottom = false;
        }
        return unseenCount;
    }

    function applySnapshot(
        snapshot: CaseConversationSnapshot,
        mode: SnapshotMode,
    ): number {
        if (!snapshot.thread.can_read) {
            loseAccess();
            return 0;
        }

        const previous = conversation;
        const anchorSequence = snapshot.page.newest_sequence;
        const unseenCount = mergeIncoming(snapshot.messages, mode);
        const newestSequence =
            messages.length > 0 ? messages[messages.length - 1].sequence : null;
        const oldestSequence =
            messages.length > 0 ? messages[0].sequence : null;
        if (mode === 'older') {
            olderHistoryAvailable = snapshot.page.has_older;
        } else if (snapshot.page.has_older) {
            olderHistoryAvailable = true;
        }
        const hasOlder = olderHistoryAvailable;
        const hasMoreAfter =
            mode === 'older'
                ? latestWindowEvicted ||
                  (previous?.page.has_more_after ??
                      snapshot.page.has_more_after)
                : snapshot.page.has_more_after ||
                  (previous?.page.has_more_after ?? false);

        conversation = {
            ...snapshot,
            messages,
            page: {
                oldest_sequence: oldestSequence,
                newest_sequence: newestSequence,
                has_older: hasOlder,
                has_more_after: hasMoreAfter,
            },
        };

        if (mode === 'delta' && snapshot.messages.length > 0) {
            currentObservationToken = snapshot.observation_token;
            observationSequence = anchorSequence;
        }

        if (mode === 'delta' && unseenCount > 0 && !atBottom) {
            newMessageCount += unseenCount;
        }

        return unseenCount;
    }

    async function requestCatchup(): Promise<void> {
        if (!mounted || !canRead || !isVisible()) {
            return;
        }

        if (Date.now() < pollRetryAvailableAt) {
            schedulePoll(pollRetryAvailableAt - Date.now());
            return;
        }

        clearTimer(pollTimer);
        pollTimer = null;
        if (snapshotBusy) {
            catchupRequested = true;
            return;
        }

        await loadDelta();
    }

    async function loadDelta(): Promise<void> {
        if (!mounted || !canRead || !isVisible() || snapshotBusy) {
            return;
        }

        const requestGeneration = generation;
        const wasAtBottom = atBottom;
        snapshotBusy = true;
        activeSnapshotMode = 'delta';
        let nextDelay = POLL_INTERVAL_MS;
        let succeeded = false;

        try {
            let hasMore = true;
            while (hasMore && isCurrent(requestGeneration) && isVisible()) {
                const previousCursor = pollCursor;
                const url = snapshotRequestUrl(
                    'after_sequence',
                    previousCursor,
                );
                const { response, payload } = await requestJson(
                    url,
                    {
                        method: 'GET',
                        headers: requestHeaders(),
                    },
                    'snapshot',
                    requestGeneration,
                );

                if (!isCurrent(requestGeneration)) {
                    return;
                }

                if (!response.ok) {
                    if (handleSnapshotAccessFailure(response.status)) {
                        return;
                    }
                    const retryAfter = retryAfterMilliseconds(response.headers);
                    throw { kind: 'http', retryAfter };
                }

                if (!isSnapshot(payload)) {
                    throw { kind: 'invalid_response', retryAfter: null };
                }

                const unseenCount = applySnapshot(payload, 'delta');
                if (!isCurrent(requestGeneration)) {
                    return;
                }

                const nextCursor = nextCaseMessageCursor(
                    previousCursor,
                    payload.messages,
                );
                const advanced = nextCursor !== previousCursor;
                pollCursor = nextCursor;
                hasMore = payload.page.has_more_after && advanced;
                succeeded = true;

                if (wasAtBottom && unseenCount > 0) {
                    await tick();
                    if (isCurrent(requestGeneration) && atBottom) {
                        scrollToBottom();
                    }
                }
            }

            if (succeeded && isCurrent(requestGeneration)) {
                stale = false;
                staleCopy = '';
                pollFailures = 0;
                pollRetryAvailableAt = 0;
                if (
                    !feedback ||
                    feedback ===
                        'Conversation may be out of date. Showing the messages already loaded.'
                ) {
                    feedback = '';
                }
                await maybeMarkObservedRead();
            }
        } catch (failure) {
            if (!isCurrent(requestGeneration)) {
                return;
            }
            if (
                failure instanceof CaseConversationRequestFailure &&
                failure.kind === 'aborted' &&
                !isVisible()
            ) {
                return;
            }

            const retryAfter = isHttpFailureWithRetry(failure)
                ? failure.retryAfter
                : null;
            pollFailures += 1;
            nextDelay = caseConversationBackoffMs(
                pollFailures,
                retryAfter ?? undefined,
            );
            pollRetryAvailableAt = Date.now() + nextDelay;
            stale = true;
            staleCopy =
                'Conversation may be out of date. Showing the messages already loaded.';
            feedback = staleCopy;
        } finally {
            if (generation === requestGeneration) {
                snapshotBusy = false;
                activeSnapshotMode = null;
                snapshotController = null;

                if (mounted && canRead && isVisible()) {
                    if (catchupRequested) {
                        catchupRequested = false;
                        schedulePoll(0);
                    } else {
                        schedulePoll(nextDelay);
                    }
                }
            }
        }
    }

    type ScrollAnchor = { id: string; top: number } | null;

    function currentScrollAnchor(): ScrollAnchor {
        if (!messageListElement) {
            return null;
        }
        const listTop = messageListElement.getBoundingClientRect().top;
        for (const item of messageListElement.querySelectorAll<HTMLElement>(
            '[data-message-id]',
        )) {
            const rect = item.getBoundingClientRect();
            if (rect.bottom > listTop) {
                return { id: item.dataset.messageId ?? '', top: rect.top };
            }
        }
        return null;
    }

    function restoreScrollAnchor(anchor: ScrollAnchor): void {
        if (!anchor || !messageListElement) {
            return;
        }
        for (const item of messageListElement.querySelectorAll<HTMLElement>(
            '[data-message-id]',
        )) {
            if (item.dataset.messageId === anchor.id) {
                messageListElement.scrollTop +=
                    item.getBoundingClientRect().top - anchor.top;
                break;
            }
        }
        updateBottomState();
    }

    async function loadOlder(): Promise<void> {
        if (
            !mounted ||
            !canRead ||
            !conversation ||
            !olderHistoryAvailable ||
            snapshotBusy
        ) {
            return;
        }
        const cursor =
            conversation.page.oldest_sequence ?? messages[0]?.sequence;
        if (!cursor) {
            return;
        }

        const requestGeneration = generation;
        const anchor = currentScrollAnchor();
        snapshotBusy = true;
        activeSnapshotMode = 'older';
        olderLoading = true;
        feedback = '';

        try {
            const url = snapshotRequestUrl('before_sequence', cursor);
            const { response, payload } = await requestJson(
                url,
                {
                    method: 'GET',
                    headers: requestHeaders(),
                },
                'snapshot',
                requestGeneration,
            );

            if (!isCurrent(requestGeneration)) {
                return;
            }
            if (!response.ok) {
                if (handleSnapshotAccessFailure(response.status)) {
                    return;
                }
                throw new Error('History request failed.');
            }
            if (!isSnapshot(payload)) {
                throw new Error('History response could not be read.');
            }

            applySnapshot(payload, 'older');
            if (!isCurrent(requestGeneration)) {
                return;
            }
            stale = false;
            staleCopy = '';
            await tick();
            if (isCurrent(requestGeneration)) {
                restoreScrollAnchor(anchor);
            }
        } catch (failure) {
            if (!isCurrent(requestGeneration)) {
                return;
            }
            if (
                failure instanceof CaseConversationRequestFailure &&
                failure.kind === 'aborted' &&
                !isVisible()
            ) {
                return;
            }
            stale = true;
            staleCopy =
                'Older messages could not be loaded. Your current view is still available.';
            feedback = staleCopy;
        } finally {
            if (generation === requestGeneration) {
                snapshotBusy = false;
                activeSnapshotMode = null;
                snapshotController = null;
                olderLoading = false;
                if (catchupRequested && isVisible()) {
                    catchupRequested = false;
                    schedulePoll(0);
                } else {
                    schedulePoll(POLL_INTERVAL_MS);
                }
            }
        }
    }

    function isNewestBoundaryVisible(): boolean {
        if (!messageListElement || !observationSequence || !messages.length) {
            return false;
        }
        const newest = messages[messages.length - 1];
        if (newest.sequence !== observationSequence) {
            return false;
        }
        const listRect = messageListElement.getBoundingClientRect();
        for (const item of messageListElement.querySelectorAll<HTMLElement>(
            '[data-message-id]',
        )) {
            if (item.dataset.messageId === newest.id) {
                const messageRect = item.getBoundingClientRect();
                return (
                    messageRect.top >= listRect.top &&
                    messageRect.bottom <= listRect.bottom
                );
            }
        }
        return false;
    }

    async function maybeMarkObservedRead(
        allowTokenRefresh = true,
    ): Promise<void> {
        if (
            !mounted ||
            !canRead ||
            !conversation ||
            !hasForegroundFocus() ||
            !atBottom ||
            !currentObservationToken ||
            !observationSequence ||
            !isNewestBoundaryVisible()
        ) {
            return;
        }

        if (
            BigInt(observationSequence) <=
            BigInt(conversation.own_read_sequence)
        ) {
            return;
        }

        if (readBusy) {
            readRetryRequested = true;
            return;
        }

        readRetryRequested = false;
        const observedSequence = observationSequence;
        const observationToken = currentObservationToken;
        const requestGeneration = generation;
        const readOperation = ++readOperationSequence;
        activeReadOperation = readOperation;
        readBusy = true;
        try {
            const { response, payload } = await requestJson(
                readUrl,
                {
                    method: 'POST',
                    headers: requestHeaders(true),
                    body: JSON.stringify({
                        observed_sequence: observedSequence,
                        observation_token: observationToken,
                    }),
                },
                'read',
                requestGeneration,
            );

            if (!isCurrent(requestGeneration) || !hasForegroundFocus()) {
                return;
            }
            if (!response.ok) {
                if (handleSnapshotAccessFailure(response.status)) {
                    return;
                }
                if (response.status === 422) {
                    const canRefresh =
                        allowTokenRefresh &&
                        observationToken !== lastRejectedObservationToken;
                    lastRejectedObservationToken = observationToken;
                    if (canRefresh) {
                        await refreshObservedBoundary(
                            requestGeneration,
                            observationToken,
                        );
                    } else {
                        feedback =
                            'The displayed read receipt expired. Messages remain available; a fresh observation is needed before they can be marked read.';
                    }
                }
                return;
            }

            if (!isReadReceipt(payload) || !conversation) {
                return;
            }
            conversation = {
                ...conversation,
                own_read_sequence: payload.own_read_sequence,
                unread_count: payload.unread_count,
                as_of: payload.as_of ?? conversation.as_of,
            };
        } catch {
            // A read watermark is monotonic and can safely be retried on a later foreground observation.
        } finally {
            if (
                generation === requestGeneration &&
                activeReadOperation === readOperation
            ) {
                readBusy = false;
                if (readController === null) {
                    activeReadOperation = 0;
                }
                const shouldRetry =
                    readRetryRequested &&
                    hasForegroundFocus() &&
                    isCurrent(requestGeneration);
                readRetryRequested = false;
                if (shouldRetry) {
                    void maybeMarkObservedRead();
                }
            }
        }
    }

    async function refreshObservedBoundary(
        requestGeneration: number,
        expiredToken: string,
    ): Promise<void> {
        if (
            !isCurrent(requestGeneration) ||
            !hasForegroundFocus() ||
            !atBottom ||
            !conversation
        ) {
            return;
        }

        const threadId = conversation.thread.id;
        try {
            const { response, payload } = await requestJson(
                snapshotUrl,
                {
                    method: 'GET',
                    headers: requestHeaders(),
                },
                'snapshot',
                requestGeneration,
            );
            if (
                !isCurrent(requestGeneration) ||
                !hasForegroundFocus() ||
                !atBottom ||
                !conversation
            ) {
                return;
            }
            if (!response.ok) {
                if (!handleSnapshotAccessFailure(response.status)) {
                    feedback =
                        'A fresh read receipt could not be loaded. The conversation remains available; retry when ready.';
                }
                return;
            }
            if (isSnapshot(payload) && !payload.thread.can_read) {
                loseAccess();
                return;
            }
            if (
                !isSnapshot(payload) ||
                payload.thread.id !== threadId ||
                payload.messages.length === 0 ||
                !payload.observation_token ||
                payload.page.newest_sequence === null ||
                payload.page.newest_sequence === '0' ||
                !payload.messages.some(
                    (message) =>
                        message.sequence === payload.page.newest_sequence,
                )
            ) {
                feedback =
                    'A fresh read receipt was not available for the displayed messages. The conversation remains available.';
                return;
            }

            applySnapshot(payload, 'delta');
            await tick();
            if (
                !isCurrent(requestGeneration) ||
                !hasForegroundFocus() ||
                !atBottom ||
                observationSequence === null ||
                observationSequence !== payload.page.newest_sequence ||
                currentObservationToken === expiredToken
            ) {
                return;
            }
            scrollToBottom();
            if (!isNewestBoundaryVisible()) {
                return;
            }
            readBusy = false;
            activeReadOperation = 0;
            await maybeMarkObservedRead(false);
        } catch {
            if (isCurrent(requestGeneration)) {
                feedback =
                    'A fresh read receipt could not be loaded. The conversation remains available; retry when ready.';
            }
        }
    }

    function updateBottomState(): void {
        if (!messageListElement) {
            return;
        }
        const remaining =
            messageListElement.scrollHeight -
            messageListElement.scrollTop -
            messageListElement.clientHeight;
        atBottom = remaining <= BOTTOM_TOLERANCE_PX;
        if (atBottom) {
            newMessageCount = 0;
            void maybeMarkObservedRead();
        }
    }

    function scrollToBottom(): void {
        if (!messageListElement) {
            return;
        }
        messageListElement.scrollTop = messageListElement.scrollHeight;
        updateBottomState();
    }

    async function jumpToLatest(): Promise<void> {
        if (latestWindowEvicted && !(await loadLatestWindow())) {
            return;
        }
        if (conversation?.page.has_more_after) {
            await requestCatchup();
        }
        await tick();
        scrollToBottom();
        newMessageCount = 0;
        await maybeMarkObservedRead();
    }

    async function loadLatestWindow(): Promise<boolean> {
        if (
            !mounted ||
            !canRead ||
            !conversation ||
            snapshotBusy ||
            !isVisible()
        ) {
            return false;
        }

        const requestGeneration = generation;
        const threadId = conversation.thread.id;
        snapshotBusy = true;
        activeSnapshotMode = 'delta';
        try {
            const { response, payload } = await requestJson(
                snapshotUrl,
                {
                    method: 'GET',
                    headers: requestHeaders(),
                },
                'snapshot',
                requestGeneration,
            );
            if (!isCurrent(requestGeneration)) {
                return false;
            }
            if (!response.ok) {
                if (!handleSnapshotAccessFailure(response.status)) {
                    feedback =
                        'The latest messages could not be loaded. Your current view is still available.';
                }
                return false;
            }
            if (
                !isSnapshot(payload) ||
                payload.thread.id !== threadId ||
                !payload.thread.can_read
            ) {
                if (isSnapshot(payload) && !payload.thread.can_read) {
                    loseAccess();
                } else {
                    feedback =
                        'The latest messages could not be confirmed. Your current view is still available.';
                }
                return false;
            }
            const firstMessage = payload.messages[0];
            const lastMessage = payload.messages.at(-1);
            if (
                !firstMessage ||
                !lastMessage ||
                payload.messages.length > MAX_RENDERED_MESSAGES ||
                !payload.observation_token ||
                payload.page.oldest_sequence !== firstMessage.sequence ||
                payload.page.newest_sequence !== lastMessage.sequence
            ) {
                feedback =
                    'The latest messages could not be confirmed. Your current view is still available.';
                return false;
            }

            messages = payload.messages;
            conversation = {
                ...payload,
                messages,
                page: {
                    oldest_sequence: firstMessage.sequence,
                    newest_sequence: lastMessage.sequence,
                    has_older: payload.page.has_older,
                    has_more_after: payload.page.has_more_after,
                },
            };
            olderHistoryAvailable = payload.page.has_older;
            currentObservationToken = payload.observation_token;
            observationSequence = lastMessage.sequence;
            latestWindowEvicted = false;
            stale = false;
            staleCopy = '';
            return true;
        } catch {
            if (isCurrent(requestGeneration)) {
                feedback =
                    'The latest messages could not be loaded. Your current view is still available.';
            }
            return false;
        } finally {
            if (generation === requestGeneration) {
                snapshotBusy = false;
                activeSnapshotMode = null;
            }
        }
    }

    function parseResponseStatus(
        status: number,
        retryAfter: number | null,
    ): void {
        if (status === 401 || status === 419) {
            loseSession();
            return;
        }
        if (status === 403 || status === 404) {
            loseAccess();
            return;
        }
        if (status === 422) {
            sendState = 'validation_error';
            feedback =
                'This message was not accepted. Keep it unchanged to retry, or explicitly abandon it before editing.';
            setRetryWait(0);
            return;
        }
        if (status === 409) {
            sendState = 'conflict';
            feedback =
                'The send identity conflicts with an existing operation. The message may already exist; retry the same captured message or explicitly abandon it.';
            setRetryWait(0);
            return;
        }

        sendFailures += 1;
        sendState = 'uncertain';
        if (status === 429) {
            feedback =
                'The service is receiving too many requests. This message is still pending; retry will be available after the server’s requested wait.';
            setRetryWait(
                caseConversationBackoffMs(
                    sendFailures,
                    retryAfter ?? undefined,
                ),
            );
        } else {
            feedback =
                'The message could not be confirmed. It may already exist; retry sends the same captured message safely.';
            setRetryWait(0);
        }
    }

    async function submitMessage(): Promise<void> {
        if (!canSend || sendBusy) {
            return;
        }
        if (!pending) {
            const captured = capturePendingCaseMessage(
                draft,
                newIdempotencyKey(),
            );
            if (!captured) {
                feedback = 'Enter a message before sending.';
                return;
            }
            pending = captured;
        }
        if (Date.now() < retryAvailableAt) {
            feedback =
                'The server asked us to wait before retrying this message.';
            return;
        }
        await sendPendingMessage();
    }
    async function sendPendingMessage(): Promise<void> {
        const captured = pending;
        if (
            !captured ||
            !canRead ||
            sendBusy ||
            Date.now() < retryAvailableAt
        ) {
            return;
        }
        const requestGeneration = generation;
        const wasAtBottom = atBottom;
        sendBusy = true;
        sendState = 'sending';
        abandonRequested = false;
        feedback = 'Sending the captured message…';

        try {
            const { response, payload } = await requestJson(
                sendUrl,
                {
                    method: 'POST',
                    headers: requestHeaders(true),
                    body: JSON.stringify({
                        body: captured.body,
                        idempotency_key: captured.idempotencyKey,
                    }),
                },
                'send',
                requestGeneration,
            );

            if (!isCurrent(requestGeneration)) {
                return;
            }
            if (!response.ok) {
                parseResponseStatus(
                    response.status,
                    retryAfterMilliseconds(response.headers),
                );
                return;
            }
            if (!isSendReceipt(payload)) {
                throw new Error(
                    'The send response did not include a message receipt.',
                );
            }

            const receiptMessage = payload.message;
            const unseenCount = mergeIncoming([receiptMessage], 'delta');
            const latestSequence =
                BigInt(receiptMessage.sequence) >
                BigInt(conversation?.latest_sequence ?? '0')
                    ? receiptMessage.sequence
                    : (conversation?.latest_sequence ??
                      receiptMessage.sequence);
            if (conversation) {
                conversation = {
                    ...conversation,
                    messages,
                    latest_sequence: latestSequence,
                    page: {
                        ...conversation.page,
                        oldest_sequence: messages[0]?.sequence ?? null,
                        newest_sequence:
                            messages[messages.length - 1]?.sequence ?? null,
                    },
                };
            }

            if (pending?.idempotencyKey === captured.idempotencyKey) {
                pending = null;
                draft = '';
                sendState = 'idle';
                feedback = 'Message saved.';
                abandonRequested = false;
                sendFailures = 0;
                setRetryWait(0);
            }

            if (unseenCount > 0 && !wasAtBottom) {
                newMessageCount += unseenCount;
            }
            await tick();
            if (isCurrent(requestGeneration) && wasAtBottom && atBottom) {
                scrollToBottom();
            }
            if (isCurrent(requestGeneration)) {
                await requestCatchup();
            }
        } catch (failure) {
            if (!isCurrent(requestGeneration)) {
                return;
            }
            if (
                failure instanceof CaseConversationRequestFailure &&
                failure.kind === 'aborted'
            ) {
                return;
            }
            sendFailures += 1;
            sendState = 'uncertain';
            feedback =
                'The message could not be confirmed. It may already exist; retry keeps the same message text and identity.';
            setRetryWait(0);
        } finally {
            if (generation === requestGeneration) {
                sendBusy = false;
                sendController = null;
            }
        }
    }

    function handleComposerKeydown(event: KeyboardEvent): void {
        if (event.key === 'Enter' && (event.metaKey || event.ctrlKey)) {
            event.preventDefault();
            void submitMessage();
        }
    }

    function requestAbandon(): void {
        if (!pending || sendBusy) {
            return;
        }
        if (!abandonRequested) {
            abandonRequested = true;
            feedback =
                'The server may already have saved this message. Abandoning stops retries but cannot undo a committed message.';
            return;
        }

        pending = null;
        sendState = 'idle';
        abandonRequested = false;
        feedback =
            'The captured send was abandoned. Review the draft before sending it with a new identity.';
        setRetryWait(0);
    }

    function retryPending(): void {
        if (!pending || sendBusy || Date.now() < retryAvailableAt) {
            return;
        }
        void sendPendingMessage();
    }

    function messageTime(createdAt: string): string {
        const date = new Date(createdAt);
        return Number.isNaN(date.getTime()) ? createdAt : date.toLocaleString();
    }

    function onVisibilityChange(): void {
        if (!isVisible()) {
            clearTimer(pollTimer);
            pollTimer = null;
            cancelReadOperation();
            if (activeSnapshotMode === 'delta') {
                snapshotController?.abort();
            }
            return;
        }
        void requestCatchup();
    }

    function onForegroundReconnect(): void {
        void requestCatchup();
    }

    function onWindowBlur(): void {
        if (!hasForegroundFocus()) {
            cancelReadOperation();
        }
    }

    onMount(() => {
        mounted = true;
        const removeNavigationGuard = router.on('before', (event) => {
            const visitUrl = new URL(
                event.detail.visit.url,
                window.location.href,
            );
            if (
                !caseConversationNeedsNavigationConfirmation(
                    visitUrl.pathname,
                    Boolean(draft.trim()) || pending !== null,
                    accessState === 'available',
                )
            ) {
                return;
            }
            return window.confirm(
                'A draft or unconfirmed message will be lost if you leave this conversation. Leave anyway?',
            );
        });
        const onBeforeUnload = (event: BeforeUnloadEvent): void => {
            if (
                accessState !== 'available' ||
                (!draft.trim() && pending === null)
            ) {
                return;
            }
            event.preventDefault();
            event.returnValue = '';
        };
        window.addEventListener('beforeunload', onBeforeUnload);
        document.addEventListener('visibilitychange', onVisibilityChange);
        window.addEventListener('focus', onForegroundReconnect);
        window.addEventListener('blur', onWindowBlur);
        window.addEventListener('online', onForegroundReconnect);
        if (!conversation?.thread.can_read) {
            loseAccess();
        } else {
            schedulePoll(POLL_INTERVAL_MS);
            void tick().then(() => {
                if (!mounted || !canRead) {
                    return;
                }
                scrollToBottom();
                void maybeMarkObservedRead();
            });
        }

        return () => {
            mounted = false;
            generation += 1;
            clearScheduledWork();
            abortRequests();
            removeNavigationGuard();
            window.removeEventListener('beforeunload', onBeforeUnload);
            document.removeEventListener(
                'visibilitychange',
                onVisibilityChange,
            );
            window.removeEventListener('focus', onForegroundReconnect);
            window.removeEventListener('blur', onWindowBlur);
            window.removeEventListener('online', onForegroundReconnect);
            snapshotBusy = false;
            sendBusy = false;
            readBusy = false;
            pending = null;
            draft = '';
            messages = [];
            conversation = null;
        };
    });
</script>

<section
    class="border-border/70 bg-card/80 min-w-0 space-y-4 rounded-xl border p-3 shadow-sm sm:p-5"
    aria-label="Case conversation"
>
    {#if accessState === 'session_expired'}
        <div
            role="alert"
            class="border-destructive/30 bg-destructive/5 text-foreground rounded-lg border p-4 text-sm"
        >
            Your session has expired. Sign in again to continue. Protected
            conversation state has been cleared.
        </div>
    {:else if accessState === 'access_lost' || !conversation?.thread.can_read}
        <div
            role="status"
            class="border-border bg-muted/40 text-muted-foreground rounded-lg border p-4 text-sm"
        >
            This conversation is no longer available.
        </div>
    {:else if conversation}
        <header
            class="border-border/70 flex min-w-0 flex-col gap-3 border-b pb-4 sm:flex-row sm:items-start sm:justify-between"
        >
            <div class="min-w-0 space-y-1">
                <h2
                    class="text-base font-semibold tracking-tight break-words sm:text-lg"
                >
                    {conversation.thread.subject}
                </h2>
                <div class="flex flex-wrap items-center gap-1.5">
                    {#if conversation.thread.thread_type === 'internal_ph_guidance'}
                        <span
                            class="inline-flex items-center gap-1 rounded-full border border-amber-500/25 bg-amber-500/10 px-2 py-0.5 text-xs font-medium text-amber-800 dark:text-amber-300"
                        >
                            <Lock class="size-3" />
                            Staff discussion · Guidance and academic staff
                        </span>
                    {:else if conversation.thread.thread_type === 'applicant_office_inquiry'}
                        <span
                            class="text-primary border-primary/20 bg-primary/5 inline-flex items-center gap-1 rounded-full border px-2 py-0.5 text-xs font-medium"
                        >
                            <Lock class="size-3" />
                            {conversation.thread.office_label ?? 'Office inquiry'}
                        </span>
                    {:else}
                        <span
                            class="inline-flex items-center gap-1 rounded-full border border-emerald-500/25 bg-emerald-500/10 px-2 py-0.5 text-xs font-medium text-emerald-800 dark:text-emerald-300"
                        >
                            <UserCheck class="size-3" />
                            With applicant · Applicant-visible
                        </span>
                    {/if}
                    {#if conversation.thread.program_code}
                        <span
                            class="border-border bg-muted/40 text-muted-foreground inline-flex items-center rounded-full border px-2 py-0.5 text-xs font-medium"
                        >
                            Program {conversation.thread.program_code}
                        </span>
                    {/if}
                </div>
            </div>
            <div class="flex shrink-0 items-center gap-2 self-start">
                <span
                    class="border-border bg-background text-muted-foreground inline-flex min-h-8 items-center rounded-full border px-3 text-xs font-medium capitalize"
                >
                    {conversation.thread.status.replace('_', ' ')}
                </span>
                <Button
                    variant="outline"
                    size="sm"
                    class="gap-1.5 h-8 px-2.5 text-xs"
                    disabled={snapshotBusy || !canRead || !isVisible()}
                    onclick={() => void requestCatchup()}
                >
                    <RefreshCw class="size-3.5" />
                    <span>Refresh</span>
                </Button>
            </div>
        </header>

        {#if stale || conversation.unread_count > 0}
            <div class="text-muted-foreground flex flex-wrap items-center gap-2 text-xs">
                {#if stale}
                    <span role="status"
                        >{staleCopy ||
                            'Conversation may be out of date. Showing the messages already loaded.'}</span
                    >
                {:else}
                    <span>
                        {conversation.unread_count} unread {conversation.unread_count ===
                        1
                            ? 'message'
                            : 'messages'}
                    </span>
                {/if}
            </div>
        {/if}

        {#if olderHistoryAvailable}
            <Button
                variant="outline"
                class="min-h-11 w-full sm:w-auto"
                disabled={snapshotBusy || olderLoading || !canRead}
                onclick={() => void loadOlder()}
            >
                {olderLoading
                    ? 'Loading older messages…'
                    : 'Load older messages'}
            </Button>
        {/if}

        <div
            bind:this={messageListElement}
            class="border-border/70 bg-background/70 focus-visible:ring-ring max-h-[min(55vh,32rem)] min-h-40 overflow-y-auto overscroll-contain rounded-lg border p-3 focus-visible:ring-2 focus-visible:outline-none sm:p-4"
            tabindex="0"
            role="region"
            aria-label="Conversation messages"
            onscroll={updateBottomState}
        >
            <ol class="space-y-3" aria-live="off">
                {#each messages as message (message.id)}
                    <li
                        data-message-id={message.id}
                        class={`flex ${message.sender.is_self ? 'justify-end' : 'justify-start'}`}
                    >
                        <article
                            class={`max-w-[92%] min-w-0 rounded-lg border px-3 py-2 sm:max-w-[80%] ${message.sender.is_self ? 'border-primary/25 bg-primary/10' : 'border-border bg-muted/40'}`}
                        >
                            <div
                                class="text-muted-foreground mb-1 flex flex-wrap items-baseline justify-between gap-x-3 gap-y-1 text-xs"
                            >
                                <span class="text-foreground font-medium">
                                    {message.sender.display_name}{message.sender
                                        .is_self
                                        ? ' · You'
                                        : ''}
                                </span>
                                <time datetime={message.created_at}
                                    >{messageTime(message.created_at)}</time
                                >
                            </div>
                            <p
                                class="text-sm [overflow-wrap:anywhere] break-words whitespace-pre-wrap"
                            >
                                {message.body}
                            </p>
                        </article>
                    </li>
                {:else}
                    <li
                        class="border-border text-muted-foreground rounded-md border border-dashed px-3 py-6 text-center text-sm"
                    >
                        {conversation.thread.thread_type ===
                        'internal_ph_guidance'
                            ? 'No messages in this internal case thread yet.'
                            : 'No messages in this support conversation yet.'}
                    </li>
                {/each}
            </ol>
        </div>

        {#if newMessageCount > 0 || latestWindowEvicted}
            <div class="flex justify-center">
                <Button
                    variant="outline"
                    class="min-h-11 w-full sm:w-auto"
                    disabled={snapshotBusy}
                    onclick={() => void jumpToLatest()}
                >
                    {#if latestWindowEvicted && newMessageCount === 0}
                        Return to latest messages
                    {:else}
                        {newMessageCount} new {newMessageCount === 1
                            ? 'message'
                            : 'messages'} · Jump to latest
                    {/if}
                </Button>
            </div>
        {/if}

        {#if canSend || pending}
            <form
                class="border-border/70 space-y-3 border-t pt-4"
                onsubmit={(event) => {
                    event.preventDefault();
                    void submitMessage();
                }}
            >
                {#if canSend}
                    <div class="space-y-1">
                        <label
                            for="case-conversation-reply"
                            class="text-sm font-medium">{conversation.thread.thread_type === 'internal_ph_guidance' ? 'Write to staff only' : conversation.thread.thread_type === 'applicant_support' ? 'Write to applicant' : 'Your message'}</label
                        >
                        <Textarea
                            id="case-conversation-reply"
                            bind:value={draft}
                            rows={4}
                            maxlength={4000}
                            disabled={pending !== null || sendBusy}
                            aria-describedby="case-conversation-composer-help"
                            placeholder={conversation.thread.thread_type === 'internal_ph_guidance' ? 'Write to staff only… (Ctrl+Enter to send)' : conversation.thread.thread_type === 'applicant_support' ? 'Write to applicant… (Ctrl+Enter to send)' : 'Write a message… (Ctrl+Enter to send)'}
                            class="min-h-24 resize-y"
                            onkeydown={handleComposerKeydown}
                        />
                        <p
                            id="case-conversation-composer-help"
                            class="sr-only"
                        >
                            Press Enter for a new line. Press Ctrl+Enter or
                            Command+Enter to send.
                        </p>
                    </div>
                {/if}

                {#if pending}
                    <div
                        class="rounded-lg border border-amber-500/30 bg-amber-500/5 p-3 text-sm"
                        aria-live="polite"
                    >
                        {#if sendState === 'sending'}
                            This captured message is being sent. Its text and
                            retry identity are locked until the server confirms
                            it.
                        {:else if sendState === 'validation_error'}
                            The server rejected this captured message. Its text
                            and identity are retained; abandon it explicitly
                            before editing.
                        {:else if sendState === 'conflict'}
                            This send identity is conflicted. The message may
                            already exist; retry only this exact text or
                            explicitly abandon it.
                        {:else if sendState === 'uncertain'}
                            The result is uncertain. The message may already
                            exist; retry uses the same text and identity.
                        {:else}
                            This message is still pending and cannot be changed.
                        {/if}
                    </div>
                    <div class="flex flex-col gap-2 sm:flex-row sm:flex-wrap">
                        <Button
                            type="button"
                            class="min-h-11 w-full sm:w-auto"
                            disabled={sendBusy ||
                                retryAvailableAt > Date.now() ||
                                !canRead}
                            onclick={retryPending}
                        >
                            {sendState === 'sending'
                                ? 'Sending…'
                                : sendState === 'validation_error'
                                  ? 'Retry exact message'
                                  : 'Retry same message'}
                        </Button>
                        {#if !sendBusy}
                            <Button
                                type="button"
                                variant="outline"
                                class="min-h-11 w-full sm:w-auto"
                                disabled={false}
                                onclick={requestAbandon}
                            >
                                {abandonRequested
                                    ? 'Confirm abandon'
                                    : 'Abandon pending message'}
                            </Button>
                        {/if}
                    </div>
                    {#if abandonRequested}
                        <p role="alert" class="text-destructive text-sm">
                            The server may already have saved this message.
                            Confirming only stops retries; it cannot undo a
                            committed message.
                        </p>
                    {/if}
                {:else}
                    <div class="flex">
                        <Button
                            type="submit"
                            class="min-h-11 w-full sm:w-auto"
                            disabled={!draft.trim() || sendBusy || !canSend}
                        >
                            {sendBusy ? 'Sending…' : 'Send message'}
                        </Button>
                    </div>
                {/if}
            </form>
        {:else}
            <div
                class="border-border bg-muted/30 text-muted-foreground rounded-lg border p-3 text-sm"
            >
                {conversation.thread.status === 'closed'
                    ? 'This conversation is closed. Its history remains readable, but new messages are unavailable.'
                    : conversation.thread.status === 'read_only'
                      ? 'This conversation is read-only. New messages are unavailable.'
                      : 'Replies are unavailable for this conversation.'}
            </div>
        {/if}

        {#if feedback}
            <p
                class="text-muted-foreground text-sm"
                role={stale ||
                sendState === 'validation_error' ||
                sendState === 'conflict'
                    ? 'status'
                    : 'status'}
                aria-live="polite"
            >
                {feedback}
            </p>
        {/if}
    {/if}
</section>
