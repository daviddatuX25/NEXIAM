export type OmrCameraSignal = {
    kind: 'offer' | 'answer' | 'ice';
    generation: number;
    payload: RTCSessionDescriptionInit | RTCIceCandidateInit;
};

export type OmrPeerRole = 'controller' | 'node';

export type OmrPeerTransport = {
    createPeerConnection(configuration?: RTCConfiguration): RTCPeerConnection;
    send(message: OmrCameraSignal, abortSignal?: AbortSignal): Promise<void>;
    onPreview?(stream: MediaStream): void;
    onState?(state: RTCPeerConnectionState): void;
    onError?(error: Error): void;
};

export type OmrNativePeer = {
    start(signal?: AbortSignal): Promise<void>;
    receive(message: OmrCameraSignal): Promise<void>;
    close(): void;
    readonly connection: RTCPeerConnection;
};

/** Native WebRTC offer/answer signaling with generation fencing and bounded pre-description ICE buffering. */
export function createOmrNativePeer(
    role: OmrPeerRole,
    generation: number,
    iceServers: RTCIceServer[],
    transport: OmrPeerTransport,
): OmrNativePeer {
    const peer = transport.createPeerConnection({ iceServers });
    let closed = false;
    let remoteDescriptionReady = false;
    let answerAccepted = false;
    let bufferedIce: RTCIceCandidateInit[] = [];
    let startPromise: Promise<void> | null = null;

    peer.onicecandidate = (event) => {
        if (closed || !event.candidate) return;
        void transport.send({ kind: 'ice', generation, payload: event.candidate.toJSON() }).catch(reportError);
    };
    peer.ontrack = (event) => {
        if (closed || event.track.kind !== 'video') return;
        const stream = event.streams[0] ?? new MediaStream([event.track]);
        transport.onPreview?.(stream);
    };
    peer.onconnectionstatechange = () => {
        if (!closed) transport.onState?.(peer.connectionState);
    };

    function reportError(error: unknown): void {
        if (!closed) transport.onError?.(error instanceof Error ? error : new Error('Camera signaling failed.'));
    }

    async function flushIce(): Promise<void> {
        const queued = bufferedIce;
        bufferedIce = [];
        for (const candidate of queued) {
            if (closed) return;
            await peer.addIceCandidate(candidate);
        }
    }

    async function start(signal?: AbortSignal): Promise<void> {
        if (closed || role !== 'controller') return;
        if (startPromise) return startPromise;
        startPromise = (async () => {
            if (signal?.aborted) throw signal.reason;
            const offer = await peer.createOffer();
            if (closed || signal?.aborted) throw signal?.reason ?? new DOMException('Peer closed.', 'AbortError');
            await peer.setLocalDescription(offer);
            if (closed || signal?.aborted) throw signal?.reason ?? new DOMException('Peer closed.', 'AbortError');
            await transport.send({ kind: 'offer', generation, payload: peer.localDescription ?? offer }, signal);
        })();
        return startPromise;
    }

    async function receive(message: OmrCameraSignal): Promise<void> {
        if (closed || message.generation !== generation) return;
        if (message.kind === 'ice') {
            if (!remoteDescriptionReady) {
                if (bufferedIce.length < 64) bufferedIce.push(message.payload as RTCIceCandidateInit);
                return;
            }
            await peer.addIceCandidate(message.payload as RTCIceCandidateInit);
            return;
        }
        if (role === 'node') {
            if (message.kind !== 'offer' || remoteDescriptionReady) return;
            await peer.setRemoteDescription(message.payload as RTCSessionDescriptionInit);
            if (closed) return;
            remoteDescriptionReady = true;
            await flushIce();
            if (closed) return;
            const answer = await peer.createAnswer();
            if (closed) return;
            await peer.setLocalDescription(answer);
            if (closed) return;
            await transport.send({ kind: 'answer', generation, payload: peer.localDescription ?? answer });
            return;
        }
        if (message.kind !== 'answer' || answerAccepted) return;
        await peer.setRemoteDescription(message.payload as RTCSessionDescriptionInit);
        if (closed) return;
        answerAccepted = true;
        remoteDescriptionReady = true;
        await flushIce();
    }

    function close(): void {
        if (closed) return;
        closed = true;
        bufferedIce = [];
        startPromise = null;
        peer.onicecandidate = null;
        peer.ontrack = null;
        peer.onconnectionstatechange = null;
        peer.close();
    }

    return { start, receive, close, connection: peer };
}

export function isFreshOmrPreviewFrame(lastDecodedAt: number, now: number, maxAgeMs = 2000): boolean {
    return Number.isFinite(lastDecodedAt) && now >= lastDecodedAt && now - lastDecodedAt <= maxAgeMs;
}
