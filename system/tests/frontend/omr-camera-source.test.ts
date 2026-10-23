// @vitest-environment jsdom
import { afterEach, describe, expect, it, vi } from 'vitest';
import { OMR_CAMERA_PROTOCOL_VERSION, type OmrCameraSourceConfig, type OmrCameraSourceEvent, type OmrCaptureOperation } from '../../resources/js/lib/omrCaptureProtocol';
import { createOmrNativePeer, type OmrCameraSignal } from '../../resources/js/lib/omrCameraMedia';
import { createLocalOmrCameraSource, createOmrPhoneSensorClient, createPairedPhoneCameraSource, joinPairedPhoneSession, type OmrCameraEndpoints } from '../../resources/js/lib/omrCameraSource';

type PeerHarness = RTCPeerConnection & {
    calls: string[];
    remote: RTCSessionDescriptionInit | null;
    local: RTCSessionDescriptionInit | null;
    addedIce: RTCIceCandidateInit[];
    emitTrack(trackKind: string): void;
};

function peerHarness(): PeerHarness {
    const peer = {
        connectionState: 'new',
        remoteDescription: null,
        localDescription: null,
        onicecandidate: null,
        ontrack: null,
        onconnectionstatechange: null,
        calls: [] as string[],
        remote: null as RTCSessionDescriptionInit | null,
        local: null as RTCSessionDescriptionInit | null,
        addedIce: [] as RTCIceCandidateInit[],
        async createOffer() { this.calls.push('createOffer'); return { type: 'offer', sdp: 'offer' } as RTCSessionDescriptionInit; },
        async createAnswer() { this.calls.push('createAnswer'); return { type: 'answer', sdp: 'answer' } as RTCSessionDescriptionInit; },
        async setLocalDescription(description: RTCSessionDescriptionInit) { this.calls.push('setLocal'); this.local = description; this.localDescription = description; },
        async setRemoteDescription(description: RTCSessionDescriptionInit) { this.calls.push('setRemote'); this.remote = description; this.remoteDescription = description; },
        async addIceCandidate(candidate: RTCIceCandidateInit) { this.calls.push('addIce'); this.addedIce.push(candidate); },
        addTransceiver() { this.calls.push('transceiver'); return {} as RTCRtpTransceiver; },
        close() { this.calls.push('close'); this.connectionState = 'closed'; },
        emitTrack(trackKind: string) {
            const track = { kind: trackKind } as MediaStreamTrack;
            this.ontrack?.({ track, streams: [{} as MediaStream] } as unknown as RTCTrackEvent);
        },
    };
    return peer as unknown as PeerHarness;
}

const candidate: RTCIceCandidateInit = { candidate: 'candidate:phone', sdpMid: '0', sdpMLineIndex: 0 };
const offer: OmrCameraSignal = { kind: 'offer', generation: 4, payload: { type: 'offer', sdp: 'offer' } };
const answer: OmrCameraSignal = { kind: 'answer', generation: 4, payload: { type: 'answer', sdp: 'answer' } };
const ice: OmrCameraSignal = { kind: 'ice', generation: 4, payload: candidate };

const projectedEndpoints: OmrCameraEndpoints = {
    join: '/server-projected/join',
    poll: '/server-projected/poll',
    signal: '/server-projected/signal',
    command: '/server-projected/command',
    ack: '/server-projected/ack',
    heartbeat: '/server-projected/heartbeat',
    frames: '/server-projected/frames',
};

afterEach(() => {
    vi.restoreAllMocks();
    vi.clearAllTimers();
    vi.useRealTimers();
});

describe('native paired camera media and HTTP source behavior', () => {
    it('is desktop offerer, buffers phone ICE until the matching answer, and ignores stale generations', async () => {
        const pc = peerHarness();
        const send = vi.fn(async () => undefined);
        const source = createOmrNativePeer('controller', 4, [], {
            createPeerConnection: () => pc,
            send,
        });

        await source.start();
        expect(pc.calls).toEqual(['createOffer', 'setLocal']);
        expect(send).toHaveBeenCalledWith(expect.objectContaining({ kind: 'offer', generation: 4 }), undefined);
        await source.receive({ ...ice, generation: 3 });
        await source.receive(ice);
        expect(pc.addedIce).toHaveLength(0);
        await source.receive(answer);
        expect(pc.remote?.type).toBe('answer');
        expect(pc.addedIce).toEqual([candidate]);
        expect(pc.calls.filter((call) => call === 'addIce')).toHaveLength(1);
        source.close();
    });

    it('is phone answerer, buffers pre-offer candidates, and ignores stale or duplicate offers', async () => {
        const pc = peerHarness();
        const sent: OmrCameraSignal[] = [];
        const source = createOmrNativePeer('node', 4, [], {
            createPeerConnection: () => pc,
            async send(message) { sent.push(message); },
        });

        await source.receive({ ...ice, generation: 3 });
        await source.receive(ice);
        await source.receive({ ...offer, generation: 3 });
        expect(pc.remote).toBeNull();
        await source.receive(offer);
        await source.receive(offer);
        expect(pc.remote?.type).toBe('offer');
        expect(pc.addedIce).toEqual([candidate]);
        expect(sent).toEqual([{ kind: 'answer', generation: 4, payload: pc.local }]);
        expect(pc.calls.filter((call) => call === 'createAnswer')).toHaveLength(1);
        source.close();
    });

    it('publishes only video tracks as the preview and tears down the peer once', async () => {
        const pc = peerHarness();
        const onPreview = vi.fn();
        const source = createOmrNativePeer('controller', 4, [], {
            createPeerConnection: () => pc,
            send: async () => undefined,
            onPreview,
        });
        pc.emitTrack('audio');
        expect(onPreview).not.toHaveBeenCalled();
        pc.emitTrack('video');
        expect(onPreview).toHaveBeenCalledTimes(1);
        source.close();
        source.close();
        expect(pc.calls.filter((call) => call === 'close')).toHaveLength(1);
    });

    it('joins only through projected URLs and binds scoped node credentials and staff generation to subsequent requests', async () => {
        const response = {
            ok: true,
            status: 200,
            json: async () => ({ pairing_id: 'pair-1', node_id: 'node-1', node_token: 'opaque-node-token', generation: 9, controller_generation: 4, protocol_version: OMR_CAMERA_PROTOCOL_VERSION, endpoints: projectedEndpoints, ice_servers: [] }),
        } as Response;
        const fetcher = vi.fn(async () => response);
        const joined = await joinPairedPhoneSession(projectedEndpoints.join, 'A1B2C3', { fetcher, staffGeneration: 'staff-session-7' });

        expect(joined).toMatchObject({ pairingId: 'pair-1', nodeId: 'node-1', nodeToken: 'opaque-node-token', generation: 9 });
        expect(fetcher).toHaveBeenCalledWith(projectedEndpoints.join, expect.objectContaining({ method: 'POST', credentials: 'same-origin', redirect: 'error' }));
        const request = fetcher.mock.calls[0]?.[1] as RequestInit;
        const headers = new Headers(request.headers);
        expect(headers.get('X-Staff-Generation')).toBe('staff-session-7');
        expect(headers.get('Content-Type')).toBe('application/json');
        expect(JSON.parse(String(request.body))).toMatchObject({ pair_code: 'A1B2C3', protocol_version: OMR_CAMERA_PROTOCOL_VERSION });
        const sensorClient = createOmrPhoneSensorClient(joined, { fetcher, staffGeneration: 'staff-session-7' });
        await sensorClient.poll(0, 0);
        const scopedRequest = fetcher.mock.calls[1]?.[1] as RequestInit;
        const scopedHeaders = new Headers(scopedRequest.headers);
        expect(scopedHeaders.get('X-Omr-Camera-Role')).toBe('node');
        expect(scopedHeaders.get('X-Omr-Camera-Token')).toBe('opaque-node-token');
        expect(scopedHeaders.get('X-Omr-Camera-Generation')).toBe('9');
        expect(scopedHeaders.get('X-Staff-Generation')).toBe('staff-session-7');
        await sensorClient.sendSignal(ice);
        const signalingRequest = fetcher.mock.calls[2]?.[1] as RequestInit;
        expect(JSON.parse(String(signalingRequest.body)).generation).toBe(4);
        expect(new Headers(signalingRequest.headers).get('X-Omr-Camera-Generation')).toBe('9');
        await expect(sensorClient.sendSignal({ ...ice, generation: 9 })).rejects.toThrow(/controller generation/);
    });

    it('requires a post-configuration decoded preview and recovers only after new frame progress', async () => {
        vi.useFakeTimers();
        const target = {
            workbenchId: 'production:7:9', clientCaptureId: 'group-1', pageNo: 1,
            captureRevision: 4, instanceId: null, sheetId: null, targetKey: 'frozen-target',
        };
        const config: OmrCameraSourceConfig = {
            target, sourceGeneration: 3, configRevision: 4, registrationMode: 'document_bound',
            pageCount: 2, mode: 'manual', batchTrigger: 'manual_qr', steadyDelayMs: 300,
            qrTriggerEnabled: false, paused: false,
        };
        let configurationCommand: Record<string, unknown> | null = null;
        let captureCommand: Record<string, unknown> | null = null;
        const issuedCommands: string[] = [];
        const fetcher = vi.fn(async (input: RequestInfo | URL, init?: RequestInit) => {
            const path = new URL(String(input), window.location.origin).pathname;
            if (path === projectedEndpoints.command) {
                const command = JSON.parse(String(init?.body)) as Record<string, unknown>;
                issuedCommands.push(String(command.command));
                if (command.command === 'CAPTURE') captureCommand = command;
                else configurationCommand = command;
                return Response.json({ config: { ...config, sourceGeneration: 8, configRevision: 9 }, source_generation: 8, config_revision: 9 });
            }
            if (path === projectedEndpoints.poll) {
                return Response.json({
                    heartbeat_age_ms: 0, observations: [], signals: [], commands: [],
                    last_ack: captureCommand ? {
                        command_id: captureCommand.command_id, status: 'rejected', target_key: 'frozen-target',
                        source_generation: 8, config_revision: 9, reason: 'Phone rejected this capture.',
                    } : configurationCommand ? { command_id: configurationCommand.command_id, status: 'applied' } : null,
                });
            }
            return Response.json({});
        });
        const peer = peerHarness();
        Object.defineProperty(peer, 'connectionState', { value: 'connected', writable: true });
        const events: OmrCameraSourceEvent[] = [];
        const source = createPairedPhoneCameraSource({
            pairingId: 'pair-1', token: 'controller-token', generation: 1,
            protocolVersion: OMR_CAMERA_PROTOCOL_VERSION, endpoints: projectedEndpoints, iceServers: [],
        }, { fetcher, createPeerConnection: () => peer, now: () => Date.now() });
        const video = document.createElement('video');
        let frameCallback: VideoFrameRequestCallback | null = null;
        Object.defineProperty(video, 'requestVideoFrameCallback', {
            value: (callback: VideoFrameRequestCallback) => { frameCallback = callback; return 1; },
        });
        Object.defineProperty(video, 'cancelVideoFrameCallback', { value: vi.fn() });
        vi.spyOn(video, 'play').mockResolvedValue(undefined);
        source.attachPreview(video);
        await source.connect((event) => events.push(event));
        try {
            const configuring = source.configure(config);
            await vi.advanceTimersByTimeAsync(1);
            await configuring;
            const operation: OmrCaptureOperation = {
                target, sourceId: source.id, sourceGeneration: 8, configRevision: 9,
                operationId: 'operation-1', idempotencyKey: 'intent-1', mode: 'manual', trigger: 'shutter',
            };
            expect(events.some((event) => event.type === 'status' && event.status === 'ready')).toBe(false);
            await expect(source.acquire(operation)).rejects.toThrow(/fresh/);
            const stream = { getTracks: () => [{ stop: vi.fn() }] } as unknown as MediaStream;
            peer.ontrack?.({ track: { kind: 'video' }, streams: [stream] } as unknown as RTCTrackEvent);
            await Promise.resolve();
            video.currentTime = 1;
            if (!frameCallback) throw new Error('Preview progress was not observed.');
            (frameCallback as VideoFrameRequestCallback)(0, {} as VideoFrameCallbackMetadata);
            expect(events.filter((event) => event.type === 'status').at(-1)).toMatchObject({ status: 'ready' });
            await expect(source.acquire({ ...operation, configRevision: 8 })).rejects.toThrow(/fresh/);
            await vi.advanceTimersByTimeAsync(2200);
            await expect(source.acquire(operation)).rejects.toThrow(/fresh/);
            video.currentTime = 2;
            (frameCallback as VideoFrameRequestCallback)(0, {} as VideoFrameCallbackMetadata);
            expect(events.filter((event) => event.type === 'status').at(-1)).toMatchObject({ status: 'ready' });
            const pendingCapture = source.acquire(operation);
            const captureRejection = expect(pendingCapture).rejects.toThrow('Phone rejected this capture.');
            await vi.advanceTimersByTimeAsync(350);
            await captureRejection;
            expect(issuedCommands).toEqual(['SET_CONFIG', 'CAPTURE']);
        } finally {
            source.disconnect();
        }
    });
    it('reports local readiness again only after resumed configuration produces a fresh analysis frame', async () => {
        vi.useFakeTimers();
        const target = {
            workbenchId: 'production:7:9', clientCaptureId: 'group-1', pageNo: 1,
            captureRevision: 4, instanceId: null, sheetId: null, targetKey: 'local-target',
        };
        const config: OmrCameraSourceConfig = {
            target, sourceGeneration: 3, configRevision: 4, registrationMode: 'document_bound',
            pageCount: 2, mode: 'manual', batchTrigger: 'manual_qr', steadyDelayMs: 300,
            qrTriggerEnabled: false, paused: false,
        };
        const stream = { getTracks: () => [{ stop: vi.fn() }] } as unknown as MediaStream;
        const mediaDevices = { getUserMedia: vi.fn(async () => stream) };
        const worker = {
            onmessage: null as ((event: MessageEvent) => void) | null,
            onerror: null as (() => void) | null,
            postMessage(request: Record<string, unknown>) {
                const result = {
                    generation: request.generation,
                    frameId: request.frameId,
                    capturedAt: performance.now(),
                    quad: { points: [] },
                    gates: { in_bounds: true, angle_ok: true, steady_ok: true, lighting_ok: true },
                };
                queueMicrotask(() => this.onmessage?.({ data: result } as MessageEvent));
            },
            terminate: vi.fn(),
        };
        vi.stubGlobal('createImageBitmap', vi.fn(async () => ({ close: vi.fn() })));
        const video = document.createElement('video');
        Object.defineProperties(video, {
            readyState: { value: HTMLMediaElement.HAVE_CURRENT_DATA },
            videoWidth: { value: 1280 },
            videoHeight: { value: 720 },
        });
        vi.spyOn(video, 'play').mockResolvedValue(undefined);
        const events: OmrCameraSourceEvent[] = [];
        const source = createLocalOmrCameraSource({ mediaDevices, workerFactory: () => worker as unknown as Worker });
        source.attachPreview(video);
        await source.connect((event) => events.push(event));
        await vi.advanceTimersByTimeAsync(1);
        await source.configure(config);
        await vi.advanceTimersByTimeAsync(100);
        expect(events.some((event) => event.type === 'status' && event.status === 'ready')).toBe(true);
        await source.configure({ ...config, paused: true });
        expect(events.filter((event) => event.type === 'status').at(-1)).toMatchObject({ status: 'paused' });
        await source.configure(config);
        expect(events.filter((event) => event.type === 'status').at(-1)).toMatchObject({ status: 'connecting' });
        await vi.advanceTimersByTimeAsync(150);
        expect(events.filter((event) => event.type === 'status').at(-1)).toMatchObject({ status: 'ready' });
        source.disconnect();
    });
    it('delivers QR intent and removal observed after the normal 350ms poll interval', async () => {
        vi.useFakeTimers();
        const target = {
            workbenchId: 'production:7:9', clientCaptureId: 'group-1', pageNo: 1,
            captureRevision: 4, instanceId: null, sheetId: null, targetKey: 'frozen-target',
        };
        const config: OmrCameraSourceConfig = {
            target, sourceGeneration: 3, configRevision: 4, registrationMode: 'document_bound',
            pageCount: 2, mode: 'manual', batchTrigger: 'manual_qr', steadyDelayMs: 300,
            qrTriggerEnabled: true, paused: false,
        };
        let configurationCommand: Record<string, unknown> | null = null;
        let observations: Record<string, unknown>[] = [];
        const fetcher = vi.fn(async (input: RequestInfo | URL, init?: RequestInit) => {
            const path = new URL(String(input), window.location.origin).pathname;
            if (path === projectedEndpoints.command) {
                configurationCommand = JSON.parse(String(init?.body)) as Record<string, unknown>;
                return Response.json({ config: { ...config, sourceGeneration: 8, configRevision: 9 }, source_generation: 8, config_revision: 9 });
            }
            if (path === projectedEndpoints.poll) {
                return Response.json({
                    heartbeat_age_ms: 0, observations, signals: [], commands: [], staged_frame: null,
                    last_ack: configurationCommand ? { command_id: configurationCommand.command_id, status: 'applied' } : null,
                });
            }
            return Response.json({});
        });
        const peer = peerHarness();
        const events: OmrCameraSourceEvent[] = [];
        const source = createPairedPhoneCameraSource({
            pairingId: 'pair-qr', token: 'controller-token', generation: 1,
            protocolVersion: OMR_CAMERA_PROTOCOL_VERSION, endpoints: projectedEndpoints, iceServers: [],
        }, { fetcher, createPeerConnection: () => peer, now: () => Date.now() });
        source.attachPreview(null);
        await source.connect((event) => events.push(event));
        try {
            const configuring = source.configure(config);
            await vi.advanceTimersByTimeAsync(1);
            await configuring;
            observations = [
                { sequence: 1, age_ms: 350, kind: 'qr_intent', command: 'NEXT_PAGE', command_id: 'qr-page', target_key: 'frozen-target', source_generation: 8, config_revision: 9 },
                { sequence: 2, age_ms: 350, kind: 'qr_card_removed', target_key: 'frozen-target', source_generation: 8, config_revision: 9 },
                { sequence: 3, age_ms: 350, kind: 'qr_intent', command: 'NEXT_SET', command_id: 'stale-generation', target_key: 'frozen-target', source_generation: 7, config_revision: 9 },
            ];
            await vi.advanceTimersByTimeAsync(350);
            expect(events.filter((event) => event.type === 'qr_intent')).toEqual([
                expect.objectContaining({ command: 'NEXT_PAGE', commandId: 'qr-page', targetKey: 'frozen-target', sourceGeneration: 8, configRevision: 9 }),
            ]);
            expect(events.filter((event) => event.type === 'qr_card_removed')).toHaveLength(1);
        } finally {
            source.disconnect();
        }
    });
});
