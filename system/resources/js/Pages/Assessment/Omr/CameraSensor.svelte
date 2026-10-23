<script lang="ts">
    import { onDestroy, tick } from 'svelte';
    import { Camera, CheckCircle2, RotateCcw } from 'lucide-svelte';
    import {
        createOmrPhoneSensorClient,
        joinPairedPhoneSession,
        type OmrPhoneJoinResult,
        type OmrPhoneSensorClient,
        type OmrSensorCommand,
    } from '@/lib/omrCameraSource';
    import { createOmrNativePeer, type OmrCameraSignal, type OmrNativePeer } from '@/lib/omrCameraMedia';
    import { createOmrViewfinderSession } from '@/lib/omrViewfinderSession';
    import { createOmrQrCommandDetector, type OmrQrCommand } from '@/lib/omrCapturePlugins';
    import type { ViewfinderResult } from '@/lib/omrViewfinderWorker';

    let {
        pairingId = null,
        pairCode = null,
        endpoints = {},
        staffGeneration = null,
    }: {
        pairingId?: string | null;
        pairCode?: string | null;
        endpoints?: Record<string, string>;
        staffGeneration?: string | null;
    } = $props();

    let joinCode = $state(pairCode ?? '');
    let sensorState = $state<'unpaired' | 'joining' | 'permission' | 'connecting' | 'ready' | 'paused' | 'error'>('unpaired');
    let message = $state('Enter the pairing code shown on the authorized desktop.');
    let sensorVideo = $state<HTMLVideoElement | null>(null);
    let cameraStream = $state.raw<MediaStream | null>(null);
    let joined: OmrPhoneJoinResult | null = null;
    let client: OmrPhoneSensorClient | null = null;
    let peer: OmrNativePeer | null = null;
    let pollTimer: ReturnType<typeof setTimeout> | null = null;
    let pollAbort: AbortController | null = null;
    let pollBusy = false;
    let afterSequence = 0;
    let afterSignalSequence = 0;
    let failures = 0;
    let sourceGeneration = 0;
    let configRevision = 0;
    let activeTargetKey: string | null = null;
    let observationSequence = 0;
    let observationInFlight = false;
    let queuedObservations: Array<{ payload: Record<string, unknown>; observedAt: number }> = [];
    let phoneMode: 'manual' | 'auto' | 'batch' = 'manual';
    let batchTrigger: 'auto_steady' | 'manual_qr' = 'auto_steady';
    let steadyDelayMs: 150 | 300 | 500 | 800 = 300;
    let lockProgress = 0;
    let viewfinderResult = $state<ViewfinderResult | null>(null);
    let lastFrameAt = 0;
    let disposed = false;
    let photoInFlight = false;
    let sensorPaused = true;
    let activeCapture: { command: OmrSensorCommand; commandId: string; file: File; frameId: string; width: number; height: number; inputHash: string; staged?: { frame_id: string; preview_url: string }; uploadExpiresAt?: string; expired?: boolean } | null = null;
    let captureCommandProcessed = false;
    const acceptedCommandIds = new Set<string>();
    let resumeNeedsAuthorization = false;
    let appliedConfiguration: { sourceGeneration: number; configRevision: number; targetKey: string } | null = null;
    let pendingTerminalRejection: { commandId: string; payload: Record<string, unknown>; reason: string } | null = null;
    const viewfinder = createOmrViewfinderSession({
        video: () => sensorVideo,
        onResult(result) {
            viewfinderResult = result;
            lastFrameAt = typeof result?.capturedAt === 'number' ? result.capturedAt : 0;
            void sendObservation(result);
        },
        onUnavailable() {
            message = 'Camera quality analysis is unavailable. Reconnect the camera and try again.';
        },
        onLockProgress(progress) {
            lockProgress = progress;
        },
        onCapture: async () => false,
    });
    const qrDetector = createOmrQrCommandDetector();

    function joinEndpoint(): string | null {
        const value = endpoints.join;
        return typeof value === 'string' && value.length > 0 ? value : null;
    }

    function stopPolling(): void {
        if (pollTimer !== null) clearTimeout(pollTimer);
        pollTimer = null;
        pollAbort?.abort();
        pollAbort = null;
    }

    function releaseCamera(): void {
        viewfinder.stop();
        qrDetector.stop();
        peer?.close();
        peer = null;
        for (const track of cameraStream?.getTracks() ?? []) track.stop();
        cameraStream = null;
        if (sensorVideo) sensorVideo.srcObject = null;
        stopPolling();
        client = null;
        joined = null;
        queuedObservations = [];
        sensorPaused = true;
        activeCapture = null;
        pendingTerminalRejection = null;
        appliedConfiguration = null;
    }

    async function enableAndPair(): Promise<void> {
        if (disposed || sensorState === 'joining' || sensorState === 'permission') return;
        const joinUrl = joinEndpoint();
        if (!joinUrl) {
            sensorState = 'error';
            message = 'The server did not provide a camera join endpoint. Return to the desktop and start a new pairing.';
            return;
        }
        const code = joinCode.trim().toUpperCase();
        if (!/^[A-Z0-9]{6}$/.test(code)) {
            sensorState = 'error';
            message = 'Enter the six-character code displayed on the desktop.';
            return;
        }
        if (!navigator.mediaDevices?.getUserMedia) {
            sensorState = 'error';
            message = 'Camera access requires a supported browser and secure connection.';
            return;
        }

        sensorState = 'permission';
        message = 'Allow camera access to connect this phone.';
        let permissionRequest: Promise<MediaStream>;
        try {
            permissionRequest = navigator.mediaDevices.getUserMedia({
                audio: false,
                video: { facingMode: { ideal: 'environment' }, width: { ideal: 1920 }, height: { ideal: 1080 } },
            });
        } catch (error) {
            sensorState = 'error';
            message = error instanceof Error ? error.message : 'Camera permission could not be requested.';
            return;
        }

        sensorState = 'joining';
        message = 'Joining the desktop camera session…';
        try {
            const stream = await permissionRequest;
            if (disposed) {
                for (const track of stream.getTracks()) track.stop();
                return;
            }
            cameraStream = stream;
            await tick();
            if (disposed) {
                for (const track of stream.getTracks()) track.stop();
                return;
            }
            const video = sensorVideo;
            if (!video) throw new Error('The phone camera preview did not mount after permission was granted.');
            video.srcObject = stream;
            await video.play();
            if (disposed || cameraStream !== stream) {
                for (const track of stream.getTracks()) track.stop();
                return;
            }
            const result = await joinPairedPhoneSession(joinUrl, code, { staffGeneration });
            if (disposed || cameraStream !== stream) return;
            joined = result;
            sourceGeneration = Number(result.config?.sourceGeneration ?? 0);
            configRevision = Number(result.config?.configRevision ?? 0);
            const initialTarget = result.config?.target as unknown as Record<string, unknown> | undefined;
            activeTargetKey = typeof initialTarget?.targetKey === 'string' ? initialTarget.targetKey : typeof initialTarget?.target_key === 'string' ? initialTarget.target_key : null;
            const initialConfig = result.config as Record<string, unknown> | undefined;
            phoneMode = String(initialConfig?.mode ?? 'manual') as typeof phoneMode;
            batchTrigger = String(initialConfig?.batchTrigger ?? initialConfig?.batch_trigger ?? 'auto_steady') as typeof batchTrigger;
            steadyDelayMs = Number(initialConfig?.steadyDelayMs ?? initialConfig?.steady_delay_ms ?? 300) as typeof steadyDelayMs;
            client = createOmrPhoneSensorClient(result, { staffGeneration });
            await startPeer(stream);
            viewfinder.start();
            viewfinder.setLockDurationMs(steadyDelayMs);
            viewfinder.setAutoEnabled(false);
            const qrEnabled = Boolean(initialConfig?.qrTriggerEnabled ?? initialConfig?.qr_trigger_enabled);
            if (qrEnabled && qrDetector.supported) qrDetector.start(video, reportQrIntent, () => sendObservation(viewfinderResult, true));
            sensorState = 'connecting';
            message = 'Camera enabled. Waiting for the desktop preview to receive fresh frames.';
            await poll();
        } catch (error) {
            if (disposed) return;
            releaseCamera();
            sensorState = 'error';
            message = error instanceof Error ? error.message : 'The camera could not be paired.';
        }
    }

    async function startPeer(stream: MediaStream): Promise<void> {
        if (!joined || !client) throw new Error('Pairing must complete before media connection.');
        peer = createOmrNativePeer('node', joined.controllerGeneration, joined.iceServers, {
            createPeerConnection(configuration) { return new RTCPeerConnection(configuration); },
            send: (signal, abortSignal) => client?.sendSignal(signal, abortSignal) ?? Promise.reject(new Error('Camera signaling is unavailable.')),
            onState(connectionState) {
                if (connectionState === 'connected') {
                    sensorState = 'connecting';
                    message = 'Secure media connected. Waiting for a decoded desktop preview frame.';
                } else if (connectionState === 'failed' || connectionState === 'disconnected' || connectionState === 'closed') {
                    sensorState = 'error';
                    message = 'The desktop media connection was lost. Reconnect the camera.';
                }
            },
            onError(error) {
                sensorState = 'error';
                message = error.message;
            },
        });
        for (const track of stream.getVideoTracks()) peer.connection.addTrack(track, stream);
    }

    function schedulePoll(delay = 350): void {
        if (disposed || document.visibilityState !== 'visible' || pollTimer !== null) return;
        pollTimer = setTimeout(() => {
            pollTimer = null;
            void poll();
        }, delay);
    }

    async function poll(): Promise<void> {
        if (!client || pollBusy || disposed || document.visibilityState !== 'visible') return;
        pollBusy = true;
        const controller = new AbortController();
        pollAbort = controller;
        try {
            const resumingAfterVisibility = resumeNeedsAuthorization;
            captureCommandProcessed = false;
            const data = await client.poll(afterSequence, afterSignalSequence, controller.signal);
            failures = 0;
            if (Number(data.generation) !== joined?.generation) throw new Error('This phone camera pairing has expired or been replaced. Rejoin from the desktop.');
            const controllerHeartbeatAgeMs = Number(data.controller_heartbeat_age_ms);
            if (data.controller_heartbeat_age_ms === null || data.controller_heartbeat_age_ms === undefined ||
                !Number.isFinite(controllerHeartbeatAgeMs) || controllerHeartbeatAgeMs < 0 || controllerHeartbeatAgeMs > 15000) {
                sensorState = 'paused';
                sensorPaused = true;
                message = 'Desktop control heartbeat expired. Waiting for the authorized desktop to reconnect.';
                viewfinder.setAutoEnabled(false);
                return;
            }
            if (Array.isArray(data.signals)) {
                for (const raw of data.signals) {
                    if (!isSignal(raw)) continue;
                    await peer?.receive(raw);
                    const signalSequence = Number((raw as Record<string, unknown>).sequence ?? afterSignalSequence);
                    afterSignalSequence = Math.max(afterSignalSequence, signalSequence);
                }
            }
            if (Array.isArray(data.commands)) {
                for (const raw of data.commands as Record<string, unknown>[]) {
                    const sequence = Number(raw.sequence);
                    if (!Number.isSafeInteger(sequence) || sequence <= afterSequence) continue;
                    const command = parseCommand(raw);
                    if (resumingAfterVisibility && command.kind === 'CAPTURE') {
                        if (activeCapture?.commandId !== command.commandId) {
                            const rejection = pendingTerminalRejection?.commandId === command.commandId
                                ? pendingTerminalRejection
                                : {
                                    commandId: command.commandId,
                                    reason: 'The phone was hidden before this capture command could acquire a still.',
                                    payload: {
                                        command_id: command.commandId,
                                        status: 'rejected',
                                        target_key: command.targetKey,
                                        source_generation: command.sourceGeneration,
                                        config_revision: command.configRevision,
                                        reason: 'The phone was hidden before this capture command could acquire a still.',
                                    },
                                };
                            pendingTerminalRejection = rejection;
                            await client.acknowledge(rejection.payload);
                            pendingTerminalRejection = null;
                            sensorState = 'error';
                            message = rejection.reason;
                        }
                    } else {
                        await processCommand(command);
                    }
                    afterSequence = sequence;
                }
            }
            if (resumingAfterVisibility) {
                const rawConfig = data.config && typeof data.config === 'object' ? data.config as Record<string, unknown> : null;
                const rawTarget = rawConfig?.target && typeof rawConfig.target === 'object' ? rawConfig.target as Record<string, unknown> : null;
                const targetKey = typeof rawTarget?.targetKey === 'string' ? rawTarget.targetKey : typeof rawTarget?.target_key === 'string' ? rawTarget.target_key : null;
                const serverSourceGeneration = Number(data.source_generation);
                const serverConfigRevision = Number(data.config_revision);
                const serverPaused = rawConfig?.paused === true;
                if (rawConfig && !serverPaused && appliedConfiguration &&
                    serverSourceGeneration === appliedConfiguration.sourceGeneration &&
                    serverConfigRevision === appliedConfiguration.configRevision &&
                    targetKey === appliedConfiguration.targetKey &&
                    Number(rawConfig.sourceGeneration ?? rawConfig.source_generation ?? serverSourceGeneration) === appliedConfiguration.sourceGeneration &&
                    Number(rawConfig.configRevision ?? rawConfig.config_revision ?? serverConfigRevision) === appliedConfiguration.configRevision) {
                    const settings = rawConfig;
                    sensorPaused = false;
                    resumeNeedsAuthorization = false;
                    viewfinderResult = null;
                    lastFrameAt = 0;
                    phoneMode = String(settings.mode ?? phoneMode) as typeof phoneMode;
                    batchTrigger = String(settings.batchTrigger ?? settings.batch_trigger ?? batchTrigger) as typeof batchTrigger;
                    steadyDelayMs = Number(settings.steadyDelayMs ?? settings.steady_delay_ms ?? steadyDelayMs) as typeof steadyDelayMs;
                    viewfinder.setLockDurationMs(steadyDelayMs);
                    viewfinder.start();
                    if (settings.qrTriggerEnabled === true || settings.qr_trigger_enabled === true) {
                        if (sensorVideo && qrDetector.supported) qrDetector.start(sensorVideo, reportQrIntent, () => sendObservation(viewfinderResult, true));
                    } else {
                        qrDetector.pause();
                    }
                    sensorState = 'connecting';
                    message = 'Authorized camera settings restored. Waiting for a fresh phone frame.';
                } else {
                    sensorPaused = true;
                    sensorState = 'paused';
                    message = 'Waiting for the authorized desktop to confirm the current camera settings.';
                }
            }
            if (!captureCommandProcessed && activeCapture && !activeCapture.staged && !activeCapture.expired) await stageRetainedCapture();
            if (sensorState === 'connecting' && !(activeCapture && !activeCapture.staged) && peer?.connection.connectionState === 'connected' && lastFrameAt > 0 && performance.now() - lastFrameAt < 250) {
                sensorState = 'ready';
                message = 'Camera connected. Keep this page open while the desktop scanner is active.';
            }
            enqueueObservation({ kind: 'heartbeat', ready: sensorState === 'ready', qr_supported: qrDetector.supported, source_generation: sourceGeneration, config_revision: configRevision, target_key: currentTargetKey(), observed_at_age_ms: lastFrameAt ? Math.max(0, Math.ceil(performance.now() - lastFrameAt)) : 0 });
        } catch (error) {
            if (!controller.signal.aborted && !disposed) {
                failures += 1;
                sensorState = 'error';
                message = error instanceof Error ? error.message : 'Connection lost. Retry the camera connection.';
            }
        } finally {
            if (pollAbort === controller) pollAbort = null;
            pollBusy = false;
            schedulePoll(Math.min(5000, 350 * 2 ** Math.min(failures, 4)));
        }
    }

    function isSignal(value: unknown): value is OmrCameraSignal & { sequence?: number } {
        if (!value || typeof value !== 'object') return false;
        const signal = value as Record<string, unknown>;
        return (signal.kind === 'offer' || signal.kind === 'answer' || signal.kind === 'ice') && Number.isSafeInteger(signal.generation) && Boolean(signal.payload && typeof signal.payload === 'object');
    }

    function parseCommand(value: Record<string, unknown>): OmrSensorCommand {
        if (typeof value.command_id !== 'string' || !Number.isSafeInteger(Number(value.sequence)) || typeof value.command !== 'string' || !['SET_CONFIG', 'CAPTURE', 'PAUSE', 'STOP'].includes(value.command)) throw new Error('The desktop sent an invalid camera command.');
        return {
            commandId: value.command_id,
            sequence: Number(value.sequence),
            kind: value.command as OmrSensorCommand['kind'],
            operationId: typeof value.operation_id === 'string' ? value.operation_id : undefined,
            targetKey: typeof value.target_key === 'string' ? value.target_key : typeof (value.target as Record<string, unknown> | undefined)?.target_key === 'string' ? String((value.target as Record<string, unknown>).target_key) : undefined,
            target: value.target as OmrSensorCommand['target'],
            sourceGeneration: Number(value.source_generation),
            configRevision: Number(value.config_revision),
            trigger: ['shutter', 'keyboard', 'steady', 'qr_capture'].includes(String(value.trigger)) ? value.trigger as OmrSensorCommand['trigger'] : undefined,
            mode: ['manual', 'auto', 'batch'].includes(String(value.mode)) ? value.mode as OmrSensorCommand['mode'] : undefined,
            config: value.config as OmrSensorCommand['config'],
            expiresAt: typeof value.expires_at === 'string' ? value.expires_at : undefined,
        };
    }

    function currentTargetKey(): string | null {
        return activeTargetKey;
    }

    function enqueueObservation(payload: Record<string, unknown>): void {
        if (!client || sensorPaused || document.visibilityState !== 'visible' || payload.target_key === null) return;
        const at = performance.now();
        const observedAt = at - Number(payload.observed_at_age_ms ?? 0);
        const replaceable = payload.kind === 'quality' || payload.kind === 'heartbeat';
        const last = queuedObservations.at(-1);
        if (replaceable && last?.payload.kind === payload.kind) {
            queuedObservations[queuedObservations.length - 1] = { payload, observedAt };
        } else {
            if (queuedObservations.length >= 16) {
                queuedObservations = [];
                sensorPaused = true;
                sensorState = 'error';
                message = 'The camera connection cannot keep up. Reconnect before sending another command card.';
                return;
            }
            queuedObservations.push({ payload, observedAt });
        }
        if (!observationInFlight) void flushObservations();
    }

    async function flushObservations(): Promise<void> {
        if (observationInFlight || !client) return;
        observationInFlight = true;
        try {
            while (client && queuedObservations.length > 0) {
                const observation = queuedObservations.shift()!;
                if (observation.payload.source_generation !== sourceGeneration ||
                    observation.payload.config_revision !== configRevision ||
                    observation.payload.target_key !== currentTargetKey()) continue;
                await client.heartbeat({
                    ...observation.payload,
                    observed_at_age_ms: Math.max(0, Math.ceil(performance.now() - observation.observedAt)),
                    sequence: ++observationSequence,
                });
            }
        } catch (error) {
            queuedObservations = [];
            sensorPaused = true;
            sensorState = 'error';
            message = error instanceof Error ? error.message : 'Camera observations could not reach the desktop.';
        } finally {
            observationInFlight = false;
            if (client && queuedObservations.length > 0) void flushObservations();
        }
    }

    function sendObservation(result: ViewfinderResult | null, cardRemoved = false): void {
        if (!client || !joined || !result && !cardRemoved) return;
        const gates = result?.gates;
        enqueueObservation({
            kind: cardRemoved ? 'qr_card_removed' : 'quality',
            ready: Boolean(gates?.in_bounds && gates.angle_ok && gates.steady_ok && gates.lighting_ok),
            frame_sequence: result?.frameId ?? null,
            source_generation: sourceGeneration,
            config_revision: configRevision,
            target_key: currentTargetKey(),
            lock_progress: lockProgress,
            observed_at_age_ms: typeof result?.capturedAt === 'number' ? Math.max(0, Math.ceil(performance.now() - result.capturedAt)) : 0,
            quality: gates ? { in_bounds: gates.in_bounds, angle_ok: gates.angle_ok, steady_ok: gates.steady_ok, lighting_ok: gates.lighting_ok } : null,
            result,
            qr_card_removed: cardRemoved,
        });
    }

    function reportQrIntent(command: OmrQrCommand): void {
        if (!client || !joined) return;
        enqueueObservation({
            kind: 'qr_intent',
            command,
            command_id: crypto.randomUUID(),
            source_generation: sourceGeneration,
            config_revision: configRevision,
            target_key: currentTargetKey(),
            observed_at_age_ms: Math.max(0, Math.ceil(performance.now() - lastFrameAt)),
        });
    }

    async function acquireOriginal(command: OmrSensorCommand): Promise<{ file: File; width: number; height: number; inputHash: string }> {
        if (!cameraStream || !sensorVideo) throw new Error('Camera is not enabled.');
        if (sensorPaused || document.visibilityState !== 'visible' ||
            command.sourceGeneration !== sourceGeneration || command.configRevision !== configRevision ||
            command.targetKey !== currentTargetKey()) throw new Error('The capture command belongs to a paused or old camera configuration.');
        if (command.expiresAt === undefined || !Number.isFinite(Date.parse(command.expiresAt)) || Date.now() >= Date.parse(command.expiresAt)) {
            throw new Error('The desktop capture command expired before this still was acquired.');
        }
        if (activeCapture?.commandId === command.commandId) return { file: activeCapture.file, width: activeCapture.width, height: activeCapture.height, inputHash: activeCapture.inputHash };
        if ((command.trigger === 'steady' || command.trigger === 'qr_capture') &&
            (!viewfinderResult || performance.now() - lastFrameAt > 250 ||
                !viewfinderResult.gates.in_bounds || !viewfinderResult.gates.angle_ok ||
                !viewfinderResult.gates.steady_ok || !viewfinderResult.gates.lighting_ok)) {
            throw new Error('The phone no longer has fresh, stable sheet framing.');
        }
        if (photoInFlight) throw new Error('A camera still is already being acquired.');
        photoInFlight = true;
        const generation = sourceGeneration;
        try {
            const track = cameraStream.getVideoTracks()[0];
            let original: Blob | null = null;
            const imageCaptureConstructor = (window as unknown as { ImageCapture?: new (track: MediaStreamTrack) => { takePhoto(): Promise<Blob> } }).ImageCapture;
            if (imageCaptureConstructor && track) {
                try {
                    const photo = await new imageCaptureConstructor(track).takePhoto();
                    const bitmap = await createImageBitmap(photo);
                    const canvas = document.createElement('canvas');
                    canvas.width = bitmap.width;
                    canvas.height = bitmap.height;
                    const context = canvas.getContext('2d');
                    if (!context) {
                        bitmap.close();
                        throw new Error('The original camera image could not be read.');
                    }
                    context.drawImage(bitmap, 0, 0);
                    bitmap.close();
                    original = await new Promise<Blob>((resolve, reject) => canvas.toBlob((blob) => blob ? resolve(blob) : reject(new Error('The high-resolution camera still could not be normalized.')), 'image/png'));
                } catch (error) {
                    if (error instanceof Error && error.message.includes('could not')) throw error;
                    original = null;
                }
            }
            let width = 0;
            let height = 0;
            if (original) {
                const bitmap = await createImageBitmap(original);
                width = bitmap.width;
                height = bitmap.height;
                bitmap.close();
            } else {
                const video = sensorVideo;
                if (video.readyState < HTMLMediaElement.HAVE_CURRENT_DATA || !video.videoWidth || !video.videoHeight) throw new Error('No fresh camera image is available.');
                width = video.videoWidth;
                height = video.videoHeight;
                const canvas = document.createElement('canvas');
                canvas.width = width;
                canvas.height = height;
                const context = canvas.getContext('2d');
                if (!context) throw new Error('The camera image could not be read.');
                context.drawImage(video, 0, 0, width, height);
                original = await new Promise<Blob>((resolve, reject) => canvas.toBlob((blob) => blob ? resolve(blob) : reject(new Error('The camera image could not be encoded.')), 'image/jpeg', 0.94));
            }
            if (generation !== sourceGeneration || sensorPaused || document.visibilityState !== 'visible') throw new Error('The camera was paused or its settings changed during still acquisition.');
            if (original.size > 20 * 1024 * 1024) throw new Error('The original still exceeds the server camera upload limit; no smaller preview was substituted.');
            const file = new File([original], `omr-phone-${command.commandId}.${original.type === 'image/png' ? 'png' : 'jpg'}`, { type: original.type, lastModified: Date.now() });
            const inputHash = await sha256(file);
            if (generation !== sourceGeneration || sensorPaused || document.visibilityState !== 'visible') throw new Error('The camera was paused or its settings changed during still acquisition.');
            activeCapture = { command, commandId: command.commandId, file, frameId: '', width, height, inputHash };
            return { file, width, height, inputHash };
        } finally {
            photoInFlight = false;
        }
    }

    async function processCommand(command: OmrSensorCommand): Promise<void> {
        if (!client || !joined) return;
        if (command.kind === 'PAUSE' || command.kind === 'STOP') {
            if (command.sourceGeneration < sourceGeneration) return;
            sourceGeneration = command.sourceGeneration;
            sensorPaused = true;
            configRevision = command.configRevision;
            viewfinder.setAutoEnabled(false);
            qrDetector.pause();
            sensorState = 'paused';
            message = command.kind === 'STOP' ? 'The desktop ended this camera session.' : 'Camera paused by the desktop.';
            await client.acknowledge({ command_id: command.commandId, status: 'applied', source_generation: sourceGeneration, config_revision: command.configRevision });
            return;
        }
        if (command.kind === 'SET_CONFIG') {
            if (command.sourceGeneration < sourceGeneration) return;
            sourceGeneration = command.sourceGeneration;
            configRevision = command.configRevision;
            activeTargetKey = command.targetKey ?? null;
            sensorPaused = true;
            queuedObservations = [];
            viewfinderResult = null;
            lastFrameAt = 0;
            viewfinder.start();
            const settings = command.config as Record<string, unknown> | undefined;
            phoneMode = String(settings?.mode ?? phoneMode) as typeof phoneMode;
            batchTrigger = String(settings?.batch_trigger ?? settings?.batchTrigger ?? batchTrigger) as typeof batchTrigger;
            steadyDelayMs = Number(settings?.steady_delay_ms ?? settings?.steadyDelayMs ?? steadyDelayMs) as typeof steadyDelayMs;
            viewfinder.setLockDurationMs(steadyDelayMs);
            viewfinder.setAutoEnabled(false);
            const qrEnabled = settings?.qrTriggerEnabled === true || settings?.qr_trigger_enabled === true;
            if (!qrEnabled) qrDetector.pause();
            else if (sensorVideo && qrDetector.supported) qrDetector.start(sensorVideo, reportQrIntent, () => sendObservation(viewfinderResult, true));
            message = 'Camera configuration received from the desktop.';
            await client.acknowledge({ command_id: command.commandId, status: 'applied', source_generation: sourceGeneration, config_revision: configRevision, target_key: activeTargetKey });
            appliedConfiguration = activeTargetKey ? { sourceGeneration, configRevision, targetKey: activeTargetKey } : null;
            sensorPaused = false;
            resumeNeedsAuthorization = false;
            return;
        }
        if (command.kind !== 'CAPTURE' || acceptedCommandIds.has(command.commandId) && activeCapture?.commandId !== command.commandId) return;
        if (command.sourceGeneration !== sourceGeneration || command.configRevision !== configRevision || command.targetKey !== currentTargetKey()) return;
        if (pendingTerminalRejection?.commandId === command.commandId) {
            await client.acknowledge(pendingTerminalRejection.payload);
            sensorState = 'error';
            message = pendingTerminalRejection.reason;
            pendingTerminalRejection = null;
            return;
        }
        captureCommandProcessed = true;
        let acquired: { file: File; width: number; height: number; inputHash: string };
        try {
            if (activeCapture?.commandId === command.commandId) {
                acquired = { file: activeCapture.file, width: activeCapture.width, height: activeCapture.height, inputHash: activeCapture.inputHash };
            } else {
                acquired = await acquireOriginal(command);
            }
        } catch (error) {
            const reason = error instanceof Error ? error.message : 'The phone could not acquire the still.';
            const payload = {
                command_id: command.commandId,
                status: 'rejected',
                target_key: command.targetKey,
                source_generation: command.sourceGeneration,
                config_revision: command.configRevision,
                reason,
            };
            pendingTerminalRejection = { commandId: command.commandId, payload, reason };
            await client.acknowledge(payload);
            sensorState = 'error';
            message = reason;
            pendingTerminalRejection = null;
            return;
        }
        await stageRetainedCapture(acquired);
    }

    async function stageRetainedCapture(acquired?: { file: File; width: number; height: number; inputHash: string }): Promise<void> {
        const capture = activeCapture;
        if (!capture || !client || capture.staged || capture.expired) return;
        if (capture.command.sourceGeneration !== sourceGeneration ||
            capture.command.configRevision !== configRevision ||
            capture.command.targetKey !== currentTargetKey()) {
            capture.expired = true;
            return;
        }
        if (capture.uploadExpiresAt && Date.now() >= Date.parse(capture.uploadExpiresAt)) {
            capture.expired = true;
            sensorState = 'error';
            message = 'The original still upload window expired before secure staging completed.';
            return;
        }
        try {
            const original = acquired ?? { file: capture.file, width: capture.width, height: capture.height, inputHash: capture.inputHash };
            const ack = await client.acknowledge({
                command_id: capture.commandId,
                status: 'acquired',
                input_hash: original.inputHash,
                mime: original.file.type,
                byte_count: original.file.size,
                width: original.width,
                height: original.height,
                orientation: 1,
                target_key: capture.command.targetKey,
                config_revision: capture.command.configRevision,
                source_generation: capture.command.sourceGeneration,
            });
            const frameId = typeof ack.frame_id === 'string' ? ack.frame_id : capture.frameId;
            if (!frameId) throw new Error('The server did not reserve the acquired still.');
            capture.frameId = frameId;
            capture.uploadExpiresAt = typeof ack.upload_expires_at === 'string' ? ack.upload_expires_at : capture.uploadExpiresAt;
            if (capture.uploadExpiresAt && Date.now() >= Date.parse(capture.uploadExpiresAt)) {
                capture.expired = true;
                sensorState = 'error';
                message = 'The original still upload window expired before secure staging completed.';
                return;
            }
            const staged = await client.stage(capture.file, { commandId: capture.commandId, frameId, targetKey: capture.command.targetKey ?? '', clientQuad: null });
            capture.staged = staged;
            if (typeof staged.upload_expires_at === 'string') capture.uploadExpiresAt = staged.upload_expires_at;
            acceptedCommandIds.add(capture.commandId);
            sensorState = 'ready';
            message = 'Original still securely staged. The desktop controls capture acceptance.';
        } catch (error) {
            sensorState = 'connecting';
            message = error instanceof Error ? `Still retained; retrying secure upload. ${error.message}` : 'Still retained; retrying secure upload.';
        }
    }

    async function sha256(file: Blob): Promise<string> {
        const bytes = new Uint8Array(await crypto.subtle.digest('SHA-256', await file.arrayBuffer()));
        return [...bytes].map((value) => value.toString(16).padStart(2, '0')).join('');
    }

    function visibilityChanged(): void {
        if (document.visibilityState !== 'visible') {
            sensorState = 'paused';
            sensorPaused = true;
            resumeNeedsAuthorization = true;
            queuedObservations = [];
            message = 'Camera preview paused while this phone tab is hidden. Return here to reconnect.';
            viewfinder.setAutoEnabled(false);
            qrDetector.pause();
            stopPolling();
            return;
        }
        if (client) {
            sensorState = 'connecting';
            message = 'Restoring camera connection…';
            schedulePoll(0);
        }
    }

    $effect(() => {
        if (sensorVideo && cameraStream && sensorVideo.srcObject !== cameraStream) {
            sensorVideo.srcObject = cameraStream;
            void sensorVideo.play().catch(() => {
                sensorState = 'error';
                message = 'Tap the video to allow camera playback.';
            });
        }
    });


    if (typeof document !== 'undefined') document.addEventListener('visibilitychange', visibilityChanged);

    onDestroy(() => {
        disposed = true;
        document.removeEventListener('visibilitychange', visibilityChanged);
        viewfinder.stop();
        qrDetector.dispose();
        releaseCamera();
    });
</script>

<svelte:head>
    <title>Paired OMR Camera · NEXIAM</title>
</svelte:head>

<main class="relative flex min-h-screen flex-col bg-slate-950 text-white" data-omr-remote-camera-node>
    <header class="border-b border-white/10 bg-slate-950 px-4 py-4">
        <div class="mx-auto flex w-full max-w-2xl items-center gap-3">
            <Camera class="size-6 shrink-0 text-emerald-300" />
            <div>
                <h1 class="text-base font-semibold">Paired OMR camera</h1>
                <p class="mt-1 text-xs text-white/65">Keep this page open. The authorized desktop controls capture.</p>
            </div>
        </div>
    </header>
    <section class="mx-auto flex w-full max-w-2xl flex-1 flex-col gap-4 px-4 py-6" aria-label="Phone camera status">
        {#if sensorState !== 'ready' && sensorState !== 'connecting' && sensorState !== 'paused' && sensorState !== 'error'}
            <form onsubmit={(event) => { event.preventDefault(); void enableAndPair(); }} class="rounded-xl border border-white/10 bg-white/5 p-4">
                <label class="block text-sm font-medium" for="omr-pair-code">Pair code</label>
                <input id="omr-pair-code" autocomplete="one-time-code" maxlength="6" bind:value={joinCode} class="mt-2 h-12 w-full rounded-lg border border-white/20 bg-slate-950 px-3 text-center text-xl uppercase tracking-[0.3em]" aria-describedby="omr-pair-help" />
                <p id="omr-pair-help" class="mt-2 text-xs text-white/60">Enter the one-use code shown on the same authorized desktop account.</p>
                <button type="submit" disabled={sensorState === 'joining' || !joinEndpoint()} class="mt-4 inline-flex min-h-11 w-full items-center justify-center gap-2 rounded-lg bg-emerald-700 px-4 text-sm font-semibold disabled:opacity-50">
                    <Camera class="size-4" /> Enable camera and pair
                </button>
            </form>
        {/if}

        <div class="relative flex min-h-[50vh] flex-1 items-center justify-center overflow-hidden rounded-xl border border-white/10 bg-black">
            {#if cameraStream}
                <!-- svelte-ignore a11y_media_has_caption -->
                <video bind:this={sensorVideo} autoplay playsinline muted class="absolute inset-0 size-full object-contain" aria-label="Phone camera preview"></video>
                {#if viewfinderResult}
                    <div class="absolute left-3 top-3 rounded-full bg-black/70 px-3 py-2 text-xs" role="status">
                        {viewfinderResult.gates.in_bounds && viewfinderResult.gates.angle_ok && viewfinderResult.gates.steady_ok && viewfinderResult.gates.lighting_ok ? 'Sheet framing ready' : 'Adjust sheet framing'}
                    </div>
                {/if}
            {:else}
                <Camera class="size-10 text-white/35" />
            {/if}
        </div>

        <div class="rounded-xl border border-white/10 bg-white/5 p-4" role="status" aria-live="polite">
            <div class="flex items-center gap-2 text-sm font-semibold">
                {#if sensorState === 'ready'}<CheckCircle2 class="size-4 text-emerald-300" />{/if}
                <span>{sensorState === 'ready' ? 'Connected' : sensorState === 'connecting' ? 'Connecting' : sensorState === 'paused' ? 'Paused' : sensorState === 'permission' ? 'Camera permission' : sensorState === 'joining' ? 'Joining session' : sensorState === 'error' ? 'Connection needs attention' : 'Not paired'}</span>
            </div>
            <p class="mt-2 text-sm text-white/70">{message}</p>
            {#if sensorState === 'error' || sensorState === 'paused'}
                <button type="button" class="mt-3 inline-flex min-h-10 items-center gap-2 rounded-lg bg-white/10 px-3 text-sm font-semibold" onclick={() => void enableAndPair()}>
                    <RotateCcw class="size-4" /> Reconnect camera
                </button>
            {/if}
        </div>
        {#if pairingId}
            <p class="text-center text-xs text-white/40">Session {pairingId.slice(0, 8)}</p>
        {/if}
    </section>
</main>
