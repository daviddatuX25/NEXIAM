export type OmrExecutionMode = 'production' | 'dry_run';

export type OmrOperationalStage =
    | 'captured'
    | 'queued'
    | 'preparing'
    | 'recognizing'
    | 'resolving'
    | 'needs_review'
    | 'ready'
    | 'confirmed'
    | string;

export interface OmrRunStatusSheet {
    id: number;
    status: string;
    version: number;
    stage: OmrOperationalStage;
    resolution?: Record<string, unknown> | null;
    recovery?: Record<string, unknown> | null;
    pages: Array<{ page_no: number; status: string; captured: boolean }>;
}

export interface OmrRunStatusSnapshot {
    session_id: number;
    run_id: number;
    mode: OmrExecutionMode;
    revision: number;
    observed_at: string;
    stage: OmrOperationalStage;
    raw_status: string;
    terminal: boolean;
    stale_after_ms: number;
    progress: { completed: number; total: number; known: boolean };
    counts: Record<string, number>;
    actions: { capture: boolean; confirm: boolean; reject: boolean };
    sheets: OmrRunStatusSheet[];
    needs_attention: Array<{
        owner: string;
        count: number;
        sheet_ids: number[];
    }>;
}

export type OmrRunConnectionState =
    | 'connecting'
    | 'live'
    | 'retrying'
    | 'stale';

export interface OmrRunConnection {
    state: OmrRunConnectionState;
    failures: number;
    lastSuccessAt: number | null;
    nextDelayMs: number;
    message: string | null;
}

export interface OmrOperationalPollerOptions {
    statusUrl: string | null;
    expectedSessionId: number;
    expectedRunId: number;
    expectedMode: OmrExecutionMode;
    initialRevision?: number;
    intervalMs?: number;
    maxIntervalMs?: number;
    staleAfterMs?: number;
    fetchImpl?: (
        url: string,
        init?: RequestInit,
    ) => Promise<{
        ok: boolean;
        status?: number;
        json(): Promise<unknown>;
    }>;
    onStatus: (status: OmrRunStatusSnapshot) => void;
    onConnection?: (connection: OmrRunConnection) => void;
}

export interface OmrOperationalPoller {
    start(): void;
    stop(): void;
    refresh(): Promise<void>;
    isRunning(): boolean;
}

export function operationalPollInterval(
    intervalMs: number | null | undefined,
    maxIntervalMs: number | null | undefined,
): number {
    const max = maxIntervalMs && maxIntervalMs > 0 ? maxIntervalMs : 12_000;
    const preferred = intervalMs && intervalMs > 0 ? intervalMs : 1_500;
    return Math.max(500, Math.min(preferred, max));
}

export function isOmrRunStatusSnapshot(
    value: unknown,
): value is OmrRunStatusSnapshot {
    if (!value || typeof value !== 'object') return false;
    const payload = value as Partial<OmrRunStatusSnapshot>;

    return (
        Number.isInteger(payload.session_id) &&
        Number.isInteger(payload.run_id) &&
        (payload.mode === 'production' || payload.mode === 'dry_run') &&
        Number.isFinite(payload.revision) &&
        typeof payload.stage === 'string' &&
        typeof payload.raw_status === 'string' &&
        typeof payload.terminal === 'boolean' &&
        Array.isArray(payload.sheets) &&
        Array.isArray(payload.needs_attention)
    );
}

/**
 * Authoritative run polling with one in-flight request, immutable context guards,
 * monotonic revision acceptance, bounded exponential retry, and stale detection.
 */
export function createOmrOperationalPoller(
    options: OmrOperationalPollerOptions,
): OmrOperationalPoller {
    const baseInterval = operationalPollInterval(
        options.intervalMs,
        options.maxIntervalMs,
    );
    const maxInterval = Math.max(
        baseInterval,
        options.maxIntervalMs ?? 12_000,
    );
    const fetchImpl = options.fetchImpl ?? ((url, init) => fetch(url, init));
    let timer: ReturnType<typeof setTimeout> | null = null;
    let stopped = true;
    let currentRequest: Promise<void> | null = null;
    let generation = 0;
    let failures = 0;
    let latestRevision = options.initialRevision ?? -1;
    let lastSuccessAt: number | null = null;

    function emitConnection(
        state: OmrRunConnectionState,
        nextDelayMs: number,
        message: string | null = null,
    ): void {
        options.onConnection?.({
            state,
            failures,
            lastSuccessAt,
            nextDelayMs,
            message,
        });
    }

    function schedule(delayMs: number): void {
        if (stopped) return;
        clearTimeout(timer ?? undefined);
        timer = setTimeout(() => void tick(), delayMs);
    }

    function retryDelay(): number {
        return Math.min(baseInterval * 2 ** Math.min(failures, 4), maxInterval);
    }

    async function requestStatus(): Promise<void> {
        const statusUrl = options.statusUrl;
        if (!statusUrl) return;
        const requestGeneration = generation;

        try {
            const response = await fetchImpl(statusUrl, {
                headers: { Accept: 'application/json' },
                credentials: 'same-origin',
            });
            const raw = await response.json().catch(() => null);
            if (!response.ok || !isOmrRunStatusSnapshot(raw)) {
                const message =
                    response.ok
                        ? 'OMR run status is unavailable.'
                        : response.status === 403
                          ? 'OMR status access was denied. Your capability may have changed.'
                          : response.status === 404
                            ? 'This OMR run is no longer available. Return to the session and reopen it.'
                            : 'OMR run status is unavailable.';
                throw new Error(message);
            }
            if (
                raw.session_id !== options.expectedSessionId ||
                raw.run_id !== options.expectedRunId ||
                raw.mode !== options.expectedMode
            ) {
                schedule(baseInterval);
                return;
            }

            failures = 0;
            lastSuccessAt = Date.now();
            emitConnection('live', baseInterval);

            if (raw.revision <= latestRevision) {
                schedule(baseInterval);
                return;
            }

            latestRevision = raw.revision;
            options.onStatus(raw);
            if (raw.terminal) {
                stop();
                return;
            }

            schedule(baseInterval);
        } catch (error) {
            if (stopped || requestGeneration !== generation) return;
            failures += 1;
            const delay = retryDelay();
            const staleAfter = options.staleAfterMs ?? 12_000;
            const stale =
                lastSuccessAt !== null && Date.now() - lastSuccessAt >= staleAfter;
            emitConnection(
                stale ? 'stale' : 'retrying',
                delay,
                error instanceof Error
                    ? error.message
                    : 'OMR run status is unavailable.',
            );
            schedule(delay);
        }
    }

    function tick(): Promise<void> {
        if (stopped || !options.statusUrl) return Promise.resolve();
        if (currentRequest !== null) return currentRequest;

        currentRequest = requestStatus().finally(() => {
            currentRequest = null;
        });

        return currentRequest;
    }

    function start(): void {
        if (!stopped || !options.statusUrl) return;
        stopped = false;
        emitConnection('connecting', 0);
        void tick();
    }

    function stop(): void {
        stopped = true;
        generation += 1;
        clearTimeout(timer ?? undefined);
        timer = null;
    }

    return {
        start,
        stop,
        refresh: tick,
        isRunning: () => !stopped,
    };
}
