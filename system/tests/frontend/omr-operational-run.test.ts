import { describe, expect, it, vi } from 'vitest';
import {
    createOmrOperationalPoller,
    isOmrRunStatusSnapshot,
    operationalPollInterval,
    type OmrRunConnection,
    type OmrRunStatusSnapshot,
} from '../../resources/js/lib/omrOperationalRun.js';

function snapshot(
    overrides: Partial<OmrRunStatusSnapshot> = {},
): OmrRunStatusSnapshot {
    return {
        session_id: 4,
        run_id: 9,
        mode: 'production',
        revision: 12,
        observed_at: '2026-10-01T10:00:00Z',
        stage: 'recognizing',
        raw_status: 'open',
        terminal: false,
        stale_after_ms: 12_000,
        progress: { completed: 1, total: 2, known: true },
        counts: { total: 1, processing: 1 },
        actions: { capture: true, confirm: false, reject: true },
        sheets: [
            {
                id: 21,
                status: 'processing',
                version: 1,
                stage: 'recognizing',
                pages: [
                    { page_no: 1, status: 'succeeded', captured: true },
                    { page_no: 2, status: 'processing', captured: true },
                ],
            },
        ],
        needs_attention: [],
        ...overrides,
    };
}

describe('OMR operational run status', () => {
    it('validates the authoritative context contract', () => {
        expect(isOmrRunStatusSnapshot(snapshot())).toBe(true);
        expect(isOmrRunStatusSnapshot({ ...snapshot(), revision: '12' })).toBe(
            false,
        );
        expect(isOmrRunStatusSnapshot({ ...snapshot(), mode: 'manual' })).toBe(
            false,
        );
    });

    it('clamps the polling cadence', () => {
        expect(operationalPollInterval(100, 8_000)).toBe(500);
        expect(operationalPollInterval(20_000, 8_000)).toBe(8_000);
        expect(operationalPollInterval(undefined, undefined)).toBe(1_500);
    });

    it('accepts only the expected run and a newer revision', async () => {
        const statuses: OmrRunStatusSnapshot[] = [];
        const payloads = [
            snapshot({ run_id: 8, revision: 13 }),
            snapshot({ revision: 11 }),
            snapshot({ revision: 13, stage: 'ready' }),
        ];
        const fetchImpl = vi.fn(async () => ({
            ok: true,
            json: async () => payloads.shift(),
        }));
        const poller = createOmrOperationalPoller({
            statusUrl: '/status',
            expectedSessionId: 4,
            expectedRunId: 9,
            expectedMode: 'production',
            initialRevision: 12,
            fetchImpl,
            onStatus: (status) => statuses.push(status),
        });

        poller.start();
        await vi.waitFor(() => expect(fetchImpl).toHaveBeenCalledTimes(1));
        await poller.refresh();
        await poller.refresh();
        await poller.refresh();
        poller.stop();

        expect(statuses).toHaveLength(1);
        expect(statuses[0]?.revision).toBe(13);
        expect(statuses[0]?.stage).toBe('ready');
    });

    it('reports retry state without overlapping requests', async () => {
        const connections: OmrRunConnection[] = [];
        const pendingResolvers: Array<
            (value: { ok: boolean; json(): Promise<unknown> }) => void
        > = [];
        const fetchImpl = vi.fn(
            () =>
                new Promise<{ ok: boolean; json(): Promise<unknown> }>(
                    (resolve) => {
                        pendingResolvers.push(resolve);
                    },
                ),
        );
        const poller = createOmrOperationalPoller({
            statusUrl: '/status',
            expectedSessionId: 4,
            expectedRunId: 9,
            expectedMode: 'production',
            fetchImpl,
            onStatus: vi.fn(),
            onConnection: (connection) => connections.push(connection),
        });

        poller.start();
        await vi.waitFor(() => expect(fetchImpl).toHaveBeenCalledTimes(1));
        const activeRequest = poller.refresh();
        expect(fetchImpl).toHaveBeenCalledTimes(1);
        pendingResolvers[0]?.({
            ok: false,
            json: async () => null,
        });
        await activeRequest;
        await vi.waitFor(() =>
            expect(connections.at(-1)?.state).toBe('retrying'),
        );
        poller.stop();
    });

    it('surfaces denied capability as a typed reconnect error', async () => {
        const connections: OmrRunConnection[] = [];
        const poller = createOmrOperationalPoller({
            statusUrl: '/status',
            expectedSessionId: 4,
            expectedRunId: 9,
            expectedMode: 'production',
            fetchImpl: async () => ({
                ok: false,
                status: 403,
                json: async () => null,
            }),
            onStatus: vi.fn(),
            onConnection: (connection) => connections.push(connection),
        });

        poller.start();
        await vi.waitFor(() =>
            expect(connections.at(-1)?.message).toContain('denied'),
        );
        poller.stop();
    });

    it('surfaces an expired run without retrying stale context', async () => {
        const connections: OmrRunConnection[] = [];
        const fetchImpl = vi.fn(async () => ({
            ok: false,
            status: 404,
            json: async () => null,
        }));
        const poller = createOmrOperationalPoller({
            statusUrl: '/status',
            expectedSessionId: 4,
            expectedRunId: 9,
            expectedMode: 'production',
            fetchImpl,
            onStatus: vi.fn(),
            onConnection: (connection) => connections.push(connection),
        });

        poller.start();
        await vi.waitFor(() =>
            expect(connections.at(-1)?.message).toContain('no longer available'),
        );
        expect(fetchImpl).toHaveBeenCalledTimes(1);
        poller.stop();
    });
});
