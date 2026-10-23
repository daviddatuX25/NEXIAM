// @vitest-environment jsdom
import { cleanup, fireEvent, render, screen, waitFor } from '@testing-library/svelte';
import { afterEach, describe, expect, it, vi } from 'vitest';

const harness = vi.hoisted(() => ({
    calls: [] as string[],
    poll: vi.fn(async (): Promise<Record<string, unknown>> => ({ generation: 2, controller_heartbeat_age_ms: 0, commands: [], signals: [] })),
    acknowledge: vi.fn(async (_payload: Record<string, unknown>) => ({})),
    heartbeat: vi.fn(async (_payload: Record<string, unknown>) => undefined),
    stage: vi.fn(async (_file: File, _payload: { commandId: string; frameId: string; targetKey: string; clientQuad?: unknown }) => ({ frame_id: 'frame-1', preview_url: '/frame' })),
    join: vi.fn(async () => ({
        pairingId: 'pair-1', nodeId: 'node-1', nodeToken: 'node-token', generation: 2,
        controllerGeneration: 1, protocolVersion: 'nexiam.omr.camera.v1', endpoints: {}, iceServers: [],
        config: { sourceGeneration: 3, configRevision: 4, target: { targetKey: 'target-1' }, mode: 'manual', qrTriggerEnabled: false },
    })),
}));

vi.mock('@/lib/omrCameraSource', () => ({
    joinPairedPhoneSession: harness.join,
    createOmrPhoneSensorClient: () => ({
        poll: harness.poll,
        sendSignal: vi.fn(async () => undefined),
        acknowledge: harness.acknowledge,
        stage: harness.stage,
        heartbeat: harness.heartbeat,
    }),
}));

vi.mock('@/lib/omrCameraMedia', () => ({
    createOmrNativePeer: () => ({
        connection: { connectionState: 'new', addTrack: vi.fn() },
        receive: vi.fn(async () => undefined),
        close: vi.fn(),
    }),
}));

vi.mock('@/lib/omrViewfinderSession', () => ({
    createOmrViewfinderSession: () => ({
        start: vi.fn(() => { harness.calls.push('analysis-start'); return true; }),
        stop: vi.fn(),
        setLockDurationMs: vi.fn(),
        setAutoEnabled: vi.fn(),
    }),
}));

vi.mock('@/lib/omrCapturePlugins', () => ({
    createOmrQrCommandDetector: () => ({ supported: true, start: vi.fn(), pause: vi.fn(), resume: vi.fn(), stop: vi.fn(), dispose: vi.fn() }),
}));

import CameraSensor from '../../resources/js/Pages/Assessment/Omr/CameraSensor.svelte';

afterEach(() => {
    cleanup();
    vi.restoreAllMocks();
    vi.unstubAllGlobals();
    vi.useRealTimers();
    harness.calls.length = 0;
    harness.poll.mockClear();
    harness.acknowledge.mockClear();
    harness.heartbeat.mockClear();
    harness.stage.mockClear();
    harness.join.mockClear();
});

describe('paired OMR phone camera startup', () => {
    it('reactively mounts and plays the preview before starting analysis', async () => {
        const track = { kind: 'video', stop: vi.fn() };
        const stream = { getTracks: () => [track], getVideoTracks: () => [track] } as unknown as MediaStream;
        Object.defineProperty(navigator, 'mediaDevices', {
            configurable: true,
            value: { getUserMedia: vi.fn(async () => stream) },
        });
        vi.spyOn(HTMLMediaElement.prototype, 'play').mockImplementation(async function () {
            harness.calls.push('video-play');
        });
        render(CameraSensor, { props: { pairCode: 'A1B2C3', endpoints: { join: '/camera/join' } } });

        await fireEvent.click(screen.getByRole('button', { name: /enable camera and pair/i }));
        await waitFor(() => expect(document.querySelector('video[aria-label="Phone camera preview"]')).toBeTruthy());
        await waitFor(() => expect(harness.calls).toContain('analysis-start'));
        expect(harness.calls.indexOf('video-play')).toBeGreaterThanOrEqual(0);
        expect(harness.calls.indexOf('video-play')).toBeLessThan(harness.calls.indexOf('analysis-start'));
        expect(harness.join).toHaveBeenCalledOnce();
        expect(harness.poll).toHaveBeenCalled();
    });
    it('retries acquired acknowledgements and staging with the same retained still and command', async () => {
        vi.useFakeTimers();
        const track = { kind: 'video', stop: vi.fn() };
        const stream = { getTracks: () => [track], getVideoTracks: () => [track] } as unknown as MediaStream;
        Object.defineProperty(navigator, 'mediaDevices', {
            configurable: true,
            value: { getUserMedia: vi.fn(async () => stream) },
        });
        vi.spyOn(HTMLMediaElement.prototype, 'play').mockResolvedValue(undefined);
        const configCommand = {
            command_id: 'config-command', sequence: 1, command: 'SET_CONFIG', source_generation: 3,
            config_revision: 4, target_key: 'target-1', config: { mode: 'manual', qr_trigger_enabled: false },
        };
        const captureCommand = {
            command_id: 'capture-command', sequence: 2, command: 'CAPTURE', source_generation: 3,
            config_revision: 4, target_key: 'target-1', trigger: 'shutter', mode: 'manual',
            expires_at: new Date(Date.now() + 60_000).toISOString(),
        };
        const pollResults = [
            { generation: 2, controller_heartbeat_age_ms: 0, commands: [configCommand], signals: [] },
            { generation: 2, controller_heartbeat_age_ms: 0, commands: [captureCommand], signals: [] },
            { generation: 2, controller_heartbeat_age_ms: 0, commands: [], signals: [] },
            { generation: 2, controller_heartbeat_age_ms: 0, commands: [], signals: [] },
        ];
        harness.poll.mockImplementation(async () => pollResults.shift() ?? { generation: 2, controller_heartbeat_age_ms: 0, commands: [], signals: [] });
        const acquisitionAcks: Record<string, unknown>[] = [];
        harness.acknowledge.mockImplementation(async (payload: Record<string, unknown>) => {
            if (payload.status !== 'acquired') return {};
            acquisitionAcks.push(payload);
            if (acquisitionAcks.length === 1) throw new Error('ACK response was lost.');
            return { frame_id: 'reserved-frame', upload_expires_at: new Date(Date.now() + 60_000).toISOString() };
        });
        const stageCalls: Array<{ file: File; commandId: string; frameId: string }> = [];
        harness.stage.mockImplementation(async (file: File, payload: { commandId: string; frameId: string }) => {
            stageCalls.push({ file, commandId: payload.commandId, frameId: payload.frameId });
            if (stageCalls.length === 1) throw new Error('Upload response was lost.');
            return { frame_id: payload.frameId, preview_url: '/frame', upload_expires_at: new Date(Date.now() + 60_000).toISOString() };
        });
        Object.defineProperty(window, 'ImageCapture', {
            configurable: true,
            value: class {
                constructor(_track: MediaStreamTrack) {}
                async takePhoto(): Promise<Blob> { return new Blob(['camera-still'], { type: 'image/jpeg' }); }
            },
        });
        vi.stubGlobal('createImageBitmap', vi.fn(async () => ({ width: 1280, height: 720, close: vi.fn() })));
        vi.spyOn(HTMLCanvasElement.prototype, 'getContext').mockReturnValue({ drawImage: vi.fn() } as unknown as CanvasRenderingContext2D);
        vi.spyOn(HTMLCanvasElement.prototype, 'toBlob').mockImplementation((callback) => callback(new Blob(['normalized-still'], { type: 'image/png' })));
        vi.spyOn(crypto.subtle, 'digest').mockResolvedValue(new ArrayBuffer(32));
        render(CameraSensor, { props: { pairCode: 'A1B2C3', endpoints: { join: '/camera/join' } } });

        await fireEvent.click(screen.getByRole('button', { name: /enable camera and pair/i }));
        await vi.waitFor(() => expect(harness.acknowledge).toHaveBeenCalledWith(expect.objectContaining({ command_id: 'config-command', status: 'applied' })));
        await vi.waitFor(() => expect(harness.heartbeat).toHaveBeenCalled());
        await Promise.resolve();
        await vi.advanceTimersByTimeAsync(350);
        await vi.waitFor(() => expect(harness.acknowledge).toHaveBeenCalledWith(expect.objectContaining({ command_id: 'capture-command', status: 'acquired' })));
        expect(acquisitionAcks).toHaveLength(1);
        await vi.advanceTimersByTimeAsync(350);
        await vi.waitFor(() => expect(stageCalls).toHaveLength(1));
        expect(acquisitionAcks).toHaveLength(2);
        await vi.advanceTimersByTimeAsync(350);
        await vi.waitFor(() => expect(stageCalls).toHaveLength(2));
        expect(acquisitionAcks).toHaveLength(3);
        expect(acquisitionAcks[0]).toEqual(acquisitionAcks[1]);
        expect(acquisitionAcks[1]).toEqual(acquisitionAcks[2]);
        expect(stageCalls[0]?.file).toBe(stageCalls[1]?.file);
        expect(stageCalls.map(({ commandId, frameId }) => ({ commandId, frameId }))).toEqual([
            { commandId: 'capture-command', frameId: 'reserved-frame' },
            { commandId: 'capture-command', frameId: 'reserved-frame' },
        ]);
    });
    it('restores only the previously applied current configuration after a brief hidden-tab pause', async () => {
        vi.useFakeTimers();
        const track = { kind: 'video', stop: vi.fn() };
        const stream = { getTracks: () => [track], getVideoTracks: () => [track] } as unknown as MediaStream;
        Object.defineProperty(navigator, 'mediaDevices', {
            configurable: true,
            value: { getUserMedia: vi.fn(async () => stream) },
        });
        vi.spyOn(HTMLMediaElement.prototype, 'play').mockResolvedValue(undefined);
        const setConfig = {
            command_id: 'config-command', sequence: 1, command: 'SET_CONFIG', source_generation: 3,
            config_revision: 4, target_key: 'target-1', config: { mode: 'manual', qr_trigger_enabled: false },
        };
        const bufferedCapture = {
            command_id: 'pre-hide-capture', sequence: 2, command: 'CAPTURE', source_generation: 3,
            config_revision: 4, target_key: 'target-1', trigger: 'shutter', mode: 'manual',
        };
        const currentConfig = {
            sourceGeneration: 3,
            configRevision: 4,
            target: { targetKey: 'target-1' },
            mode: 'manual',
            qrTriggerEnabled: false,
            paused: false,
        };
        const pollResults = [
            { generation: 2, controller_heartbeat_age_ms: 0, commands: [setConfig], signals: [] },
            { generation: 2, source_generation: 3, config_revision: 4, config: currentConfig, controller_heartbeat_age_ms: 0, commands: [bufferedCapture], signals: [] },
        ];
        harness.poll.mockImplementation(async () => pollResults.shift() ?? { generation: 2, controller_heartbeat_age_ms: 0, commands: [], signals: [] });
        render(CameraSensor, { props: { pairCode: 'A1B2C3', endpoints: { join: '/camera/join' } } });

        await fireEvent.click(screen.getByRole('button', { name: /enable camera and pair/i }));
        await vi.waitFor(() => expect(harness.acknowledge).toHaveBeenCalledWith(expect.objectContaining({ command_id: 'config-command', status: 'applied' })));
        Object.defineProperty(document, 'visibilityState', { configurable: true, value: 'hidden' });
        document.dispatchEvent(new Event('visibilitychange'));
        Object.defineProperty(document, 'visibilityState', { configurable: true, value: 'visible' });
        document.dispatchEvent(new Event('visibilitychange'));
        await vi.advanceTimersByTimeAsync(1);

        expect(screen.getByText('Authorized camera settings restored. Waiting for a fresh phone frame.')).toBeTruthy();
        expect(harness.acknowledge).not.toHaveBeenCalledWith(expect.objectContaining({ command_id: 'pre-hide-capture', status: 'acquired' }));
    });
});
