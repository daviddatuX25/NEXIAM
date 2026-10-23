// @vitest-environment jsdom
import { describe, expect, it } from 'vitest';
import {
    freezeOmrCaptureTargetContext,
    type OmrCaptureTargetIdentity,
} from '../../resources/js/lib/omrCameraAcquisition';
import {
    createOmrCaptureLifecycle,
    type OmrCaptureAdapter,
    type OmrCaptureClock,
    type OmrCaptureLifecycleContext,
} from '../../resources/js/lib/omrCaptureLifecycle';
import type {
    OmrCameraSource,
    OmrCameraSourceConfig,
    OmrCameraSourceEvent,
    OmrCaptureAcceptance,
    OmrCaptureOperation,
    OmrCapturedFrame,
    OmrCaptureOperationReference,
} from '../../resources/js/lib/omrCaptureProtocol';
import { OmrWorkbenchInvalidError } from '../../resources/js/lib/omrWorkbenchClient';
import type { ViewfinderResult } from '../../resources/js/lib/omrViewfinderWorker';

class TestClock implements OmrCaptureClock {
    private time = 0;
    private nextId = 0;
    private readonly timers = new Map<number, { at: number; callback: () => void }>();

    now(): number {
        return this.time;
    }

    setTimeout(callback: () => void, delayMs: number): ReturnType<typeof globalThis.setTimeout> {
        const id = ++this.nextId;
        this.timers.set(id, { at: this.time + delayMs, callback });
        return id as unknown as ReturnType<typeof globalThis.setTimeout>;
    }

    clearTimeout(handle: ReturnType<typeof globalThis.setTimeout>): void {
        this.timers.delete(Number(handle));
    }

    advance(milliseconds: number): void {
        const end = this.time + milliseconds;
        while (true) {
            const pending = [...this.timers.entries()].sort((a, b) => a[1].at - b[1].at)[0];
            if (!pending || pending[1].at > end) break;
            this.timers.delete(pending[0]);
            this.time = pending[1].at;
            pending[1].callback();
        }
        this.time = end;
    }
}

class TestSource implements OmrCameraSource {
    connectCalls = 0;
    readonly id = 'test-source';
    constructor(readonly kind: 'local' | 'paired_phone' = 'local') {}
    listener: ((event: OmrCameraSourceEvent) => void) | null = null;
    acquisitions: OmrCaptureOperation[] = [];
    nextFrame: OmrCapturedFrame | null = null;

    async connect(listener: (event: OmrCameraSourceEvent) => void): Promise<void> {
        this.connectCalls += 1;
        this.listener = listener;
        listener({ type: 'status', status: 'ready' });
    }

    attachPreview(): void {}
    async configure(): Promise<void> {}

    async acquire(operation: OmrCaptureOperation): Promise<OmrCapturedFrame> {
        this.acquisitions.push(operation);
        if (!this.nextFrame) throw new Error('Test frame was not configured.');
        return this.nextFrame;
    }

    disconnect(): void {}

    emit(event: OmrCameraSourceEvent): void {
        this.listener?.(event);
    }
}

const baseTarget: OmrCaptureTargetIdentity = {
    workbenchId: 'workbench-1',
    clientCaptureId: 'capture-1',
    pageNo: 1,
    captureRevision: 7,
    instanceId: null,
    sheetId: 'sheet-1',
};

function target(overrides: Partial<OmrCaptureTargetIdentity> = {}) {
    const result = freezeOmrCaptureTargetContext({ ...baseTarget, ...overrides });
    if (!result) throw new Error('Expected a valid test target.');
    return result;
}

function context(overrides: Partial<OmrCaptureLifecycleContext> = {}): OmrCaptureLifecycleContext {
    return {
        workbenchId: baseTarget.workbenchId,
        target: target(),
        sourceGeneration: 3,
        configRevision: 4,
        registrationMode: 'document_bound',
        pageCount: 2,
        cameraEnabled: true,
        visible: true,
        paused: false,
        ready: true,
        physicalSwapObserved: false,
        cropReviewRequired: false,
        ...overrides,
    };
}

const gatesResult: ViewfinderResult = {
    type: 'analysis',
    generation: 3,
    frameId: 9,
    quad: [{ x: 0.1, y: 0.1 }, { x: 0.9, y: 0.1 }, { x: 0.9, y: 0.9 }, { x: 0.1, y: 0.9 }],
    gates: { in_bounds: true, angle_ok: true, steady_ok: true, lighting_ok: true },
};

function rawFrame(targetKey = target().targetKey, sourceGeneration = 3): OmrCapturedFrame {
    return {
        kind: 'raw',
        file: new File(['original sheet bytes'], 'sheet.jpg', { type: 'image/jpeg' }),
        targetKey,
        inputHash: 'sha256:original',
        width: 1600,
        height: 1200,
        orientation: 1,
        sourceGeneration,
    };
}

function stagedFrame(targetKey = target().targetKey, sourceGeneration = 3): OmrCapturedFrame {
    return {
        kind: 'staged',
        frameId: 'frame-stage-1',
        previewUrl: '/private/preview/frame-stage-1',
        commandId: 'command-1',
        pairingId: 'pair-1',
        targetKey,
        inputHash: 'sha256:phone-original',
        width: 4032,
        height: 3024,
        orientation: 6,
        sourceGeneration,
    };
}

function receipt(operation: OmrCaptureOperationReference, status: OmrCaptureAcceptance['status'] = 'accepted'): OmrCaptureAcceptance {
    return {
        status,
        operationId: operation.operationId,
        targetKey: operation.target.targetKey,
        ...(status === 'accepted' ? { captureId: 918, projection: { pageNo: operation.target.pageNo } } : {}),
        pageNo: operation.target.pageNo,
    };
}

function makeAdapter(overrides: Partial<OmrCaptureAdapter> = {}): OmrCaptureAdapter & { submitted: Array<{ frame: OmrCapturedFrame; operation: OmrCaptureOperation }> } {
    const submitted: Array<{ frame: OmrCapturedFrame; operation: OmrCaptureOperation }> = [];
    const adapter = {
        submitted,
        async submit(frame: OmrCapturedFrame, operation: OmrCaptureOperation) {
            submitted.push({ frame, operation });
            return receipt(operation);
        },
        async reconcile(operation: OmrCaptureOperationReference) {
            return receipt(operation);
        },
        ...overrides,
    };
    return adapter;
}

async function flushPromises(): Promise<void> {
    await Promise.resolve();
    await Promise.resolve();
    await Promise.resolve();
}


describe('shared OMR capture lifecycle', () => {
    it('counts down to the exact deadline and latches one acquisition', async () => {
        const clock = new TestClock();
        const source = new TestSource();
        source.nextFrame = rawFrame();
        const adapter = makeAdapter();
        const lifecycle = createOmrCaptureLifecycle({ source, adapter, context: context(), clock, uuid: (() => { let id = 0; return () => `id-${++id}`; })() });
        await flushPromises();

        expect(lifecycle.trigger('shutter')).toBe(true);
        expect(lifecycle.getState().phase).toBe('countdown');
        clock.advance(2999);
        expect(source.acquisitions).toHaveLength(0);
        clock.advance(1);
        await flushPromises();
        expect(source.acquisitions).toHaveLength(1);
        expect(adapter.submitted).toHaveLength(1);
        expect(lifecycle.getState().phase).toBe('accepted');
        expect(lifecycle.getState().lastAccepted?.receipt.captureId).toBe(918);
        lifecycle.dispose();
    });

    it('cancels countdown on config or target invalidation without a delayed shutter', async () => {
        const clock = new TestClock();
        const source = new TestSource();
        source.nextFrame = rawFrame();
        const lifecycle = createOmrCaptureLifecycle({ source, adapter: makeAdapter(), context: context(), clock });
        await flushPromises();
        expect(lifecycle.trigger('keyboard')).toBe(true);
        lifecycle.updateContext(context({ configRevision: 5 }));
        clock.advance(4000);
        await flushPromises();
        expect(source.acquisitions).toHaveLength(0);
        expect(lifecycle.getState().operation).toBeNull();
        lifecycle.dispose();
    });
    it('keeps local configuration acknowledgment current while awaiting a fresh camera frame', async () => {
        class ConnectingLocalSource extends TestSource {
            override async configure(_config: OmrCameraSourceConfig): Promise<void> {
                this.emit({ type: 'status', status: 'connecting' });
            }
        }
        const source = new ConnectingLocalSource();
        source.nextFrame = rawFrame();
        const lifecycle = createOmrCaptureLifecycle({
            source,
            adapter: makeAdapter(),
            context: context(),
            settings: { countdownSeconds: 0 },
        });
        await flushPromises();
        expect(lifecycle.getState().phase).toBe('unavailable');
        expect(lifecycle.trigger('shutter')).toBe(false);
        source.emit({ type: 'status', status: 'ready' });
        expect(lifecycle.trigger('shutter')).toBe(true);
        await flushPromises();
        expect(lifecycle.getState().phase).toBe('accepted');
        lifecycle.dispose();
    });
    it('clears waiting for fresh camera frame message when status ready arrives even if context is not ready', async () => {
        class ConnectingLocalSource extends TestSource {
            override async configure(_config: OmrCameraSourceConfig): Promise<void> {
                this.emit({ type: 'status', status: 'connecting', message: 'Waiting for a fresh camera frame.' });
            }
        }
        const source = new ConnectingLocalSource();
        const lifecycle = createOmrCaptureLifecycle({
            source,
            adapter: makeAdapter(),
            context: context({ ready: false }),
            settings: { countdownSeconds: 0 },
        });
        await flushPromises();
        expect(lifecycle.getState().phase).toBe('unavailable');
        expect(lifecycle.getState().message).toBe('Waiting for a fresh camera frame.');
        source.emit({ type: 'status', status: 'ready' });
        expect(lifecycle.getState().phase).toBe('unavailable');
        expect(lifecycle.getState().message).toBeNull();
        lifecycle.dispose();
    });

    it('does not cancel manual shutter countdown when quality observation expires', async () => {
        const clock = new TestClock();
        const source = new TestSource();
        source.nextFrame = rawFrame();
        const adapter = makeAdapter();
        const lifecycle = createOmrCaptureLifecycle({
            source,
            adapter,
            context: context(),
            clock,
            settings: { mode: 'manual', countdownSeconds: 3 },
            uuid: (() => { let id = 0; return () => `id-${++id}`; })(),
        });
        await flushPromises();
        source.emit({
            type: 'quality',
            ready: true,
            targetKey: target().targetKey,
            sourceGeneration: 3,
            configRevision: 4,
            sequence: 1,
            lockProgress: 1,
            result: gatesResult,
        });
        expect(lifecycle.getState().qualityFresh).toBe(true);
        expect(lifecycle.trigger('shutter')).toBe(true);
        expect(lifecycle.getState().phase).toBe('countdown');
        clock.advance(1000);
        expect(lifecycle.getState().phase).toBe('countdown');
        expect(lifecycle.getState().qualityFresh).toBe(false);
        clock.advance(2000);
        await flushPromises();
        expect(lifecycle.getState().phase).toBe('accepted');
        expect(source.acquisitions).toHaveLength(1);
        lifecycle.dispose();
    });
    it('waits for the paired server configuration acknowledgment before acquisition', async () => {
        const source = new TestSource('paired_phone');
        source.nextFrame = rawFrame(target().targetKey, 8);
        const lifecycle = createOmrCaptureLifecycle({
            source,
            adapter: makeAdapter(),
            context: context(),
            settings: { countdownSeconds: 0 },
        });
        await flushPromises();
        expect(lifecycle.trigger('shutter')).toBe(false);
        expect(source.acquisitions).toHaveLength(0);

        const acknowledgedConfig: OmrCameraSourceConfig = {
            target: target(),
            sourceGeneration: 8,
            configRevision: 9,
            registrationMode: 'document_bound',
            pageCount: 2,
            mode: 'manual',
            batchTrigger: 'manual_qr',
            steadyDelayMs: 300,
            qrTriggerEnabled: false,
            paused: false,
        };
        source.emit({ type: 'configured', config: acknowledgedConfig });
        expect(lifecycle.getState().context.sourceGeneration).toBe(8);
        expect(lifecycle.getState().context.configRevision).toBe(9);
        expect(lifecycle.trigger('shutter')).toBe(true);
        await flushPromises();
        expect(source.acquisitions).toHaveLength(1);
        expect(lifecycle.getState().phase).toBe('accepted');
        lifecycle.dispose();
    });

    it('does not arm an acknowledged phone configuration before its first decoded preview frame', async () => {
        const source = new TestSource('paired_phone');
        source.connect = async (listener) => {
            source.listener = listener;
            listener({ type: 'status', status: 'connecting' });
        };
        source.nextFrame = rawFrame();
        const lifecycle = createOmrCaptureLifecycle({
            source, adapter: makeAdapter(), context: context(), settings: { countdownSeconds: 0 },
        });
        await flushPromises();
        const config: OmrCameraSourceConfig = {
            target: target(), sourceGeneration: 3, configRevision: 4,
            registrationMode: 'document_bound', pageCount: 2, mode: 'manual',
            batchTrigger: 'manual_qr', steadyDelayMs: 300, qrTriggerEnabled: false, paused: false,
        };
        source.emit({ type: 'configured', config });
        expect(lifecycle.trigger('shutter')).toBe(false);
        expect(source.acquisitions).toEqual([]);
        source.emit({ type: 'status', status: 'ready' });
        expect(lifecycle.trigger('shutter')).toBe(true);
        await flushPromises();
        expect(lifecycle.getState().phase).toBe('accepted');
        lifecycle.dispose();
    });

    it('requires every real quality gate for steady capture and keeps Auto dwell fast', async () => {
        const clock = new TestClock();
        const source = new TestSource();
        source.nextFrame = rawFrame();
        const lifecycle = createOmrCaptureLifecycle({
            source,
            adapter: makeAdapter(),
            context: context(),
            settings: { mode: 'auto', countdownSeconds: 3, applyCountdownToAuto: false },
            clock,
        });
        await flushPromises();
        const badResult: ViewfinderResult = {
            ...gatesResult,
            gates: { ...gatesResult.gates, lighting_ok: false },
        };
        source.emit({ type: 'quality', ready: true, targetKey: target().targetKey, sourceGeneration: 3, configRevision: 4, sequence: 1, lockProgress: 1, result: badResult });
        expect(lifecycle.trigger('steady')).toBe(false);
        expect(source.acquisitions).toHaveLength(0);

        source.emit({ type: 'quality', ready: true, targetKey: target().targetKey, sourceGeneration: 3, configRevision: 4, sequence: 2, lockProgress: 1, result: gatesResult });
        clock.advance(100);
        source.emit({ type: 'quality', ready: true, targetKey: target().targetKey, sourceGeneration: 3, configRevision: 4, sequence: 3, lockProgress: 0, result: gatesResult });
        clock.advance(100);
        source.emit({ type: 'quality', ready: true, targetKey: target().targetKey, sourceGeneration: 3, configRevision: 4, sequence: 4, lockProgress: 0, result: gatesResult });
        clock.advance(99);
        expect(source.acquisitions).toHaveLength(0);
        clock.advance(1);
        expect(lifecycle.getState().phase).toBe('acquiring');
        await flushPromises();
        expect(source.acquisitions).toHaveLength(1);
        expect(lifecycle.getState().phase).toBe('accepted');
        lifecycle.dispose();
    });

    it('pauses and cancels countdown when a modal takes camera ownership', async () => {
        const clock = new TestClock();
        const source = new TestSource();
        source.nextFrame = rawFrame();
        const lifecycle = createOmrCaptureLifecycle({ source, adapter: makeAdapter(), context: context(), clock });
        await flushPromises();
        expect(lifecycle.trigger('keyboard')).toBe(true);
        lifecycle.pause('Review opened.');
        clock.advance(4000);
        await flushPromises();
        expect(source.acquisitions).toHaveLength(0);
        expect(lifecycle.getState().phase).toBe('unavailable');
        lifecycle.resume();
        expect(lifecycle.getState().phase).toBe('armed');
        expect(lifecycle.getState().operation).toBeNull();
        lifecycle.dispose();
    });


    it('holds QR capture until real card removal and a newer all-gates frame', async () => {
        const clock = new TestClock();
        const source = new TestSource();
        source.nextFrame = rawFrame();
        const lifecycle = createOmrCaptureLifecycle({ source, adapter: makeAdapter(), context: context(), settings: { qrTriggerEnabled: true }, clock });
        await flushPromises();
        source.emit({ type: 'quality', ready: true, targetKey: target().targetKey, sourceGeneration: 3, configRevision: 4, sequence: 10, lockProgress: 1, result: gatesResult });
        expect(lifecycle.trigger('qr_capture', 11)).toBe(true);
        expect(lifecycle.getState().phase).toBe('awaiting_card_removal');
        clock.advance(4000);
        expect(source.acquisitions).toHaveLength(0);

        source.emit({ type: 'qr_card_removed', targetKey: target().targetKey, sourceGeneration: 3, configRevision: 4, sequence: 12, observedAt: 4000 });
        source.emit({ type: 'quality', ready: true, targetKey: target().targetKey, sourceGeneration: 3, configRevision: 4, sequence: 13, lockProgress: 1, result: gatesResult });
        expect(lifecycle.getState().phase).toBe('countdown');
        for (let sequence = 14; sequence <= 20; sequence += 1) {
            clock.advance(400);
            source.emit({ type: 'quality', ready: true, targetKey: target().targetKey, sourceGeneration: 3, configRevision: 4, sequence, lockProgress: 1, result: gatesResult });
        }
        clock.advance(199);
        expect(source.acquisitions).toHaveLength(0);
        clock.advance(1);
        await flushPromises();
        expect(source.acquisitions).toHaveLength(1);
        lifecycle.dispose();
    });

    it('requires document Crop Check and confirms the same immutable frame', async () => {
        const source = new TestSource();
        const original = rawFrame();
        source.nextFrame = original;
        const adapter = makeAdapter();
        const lifecycle = createOmrCaptureLifecycle({ source, adapter, context: context({ cropReviewRequired: true }), settings: { countdownSeconds: 0 } });
        await flushPromises();
        expect(lifecycle.trigger('shutter')).toBe(true);
        await flushPromises();
        expect(lifecycle.getState().phase).toBe('frame_awaiting_review');
        expect(adapter.submitted).toHaveLength(0);
        await lifecycle.confirmCrop({ policy: 'keep_original', quad: null });
        expect(adapter.submitted[0]?.frame).toBe(original);
        expect(adapter.submitted[0]?.operation.idempotencyKey).toBe(lifecycle.getState().operation?.idempotencyKey);
        expect(lifecycle.getState().phase).toBe('accepted');
        lifecycle.dispose();
    });

    it('supports original raw and staged inputs while preserving their distinct payloads', async () => {
        const source = new TestSource();
        const staged = stagedFrame();
        const lifecycle = createOmrCaptureLifecycle({ source, adapter: makeAdapter(), context: context() });
        await flushPromises();
        expect(lifecycle.acceptFrame(staged)).toBe(true);
        await flushPromises();
        expect(lifecycle.getState().frame?.kind).toBe('staged');
        expect(lifecycle.getState().frame).toMatchObject({ frameId: 'frame-stage-1', inputHash: 'sha256:phone-original' });
        lifecycle.dispose();

        const rawSource = new TestSource();
        const rawAdapter = makeAdapter();
        const rawLifecycle = createOmrCaptureLifecycle({ source: rawSource, adapter: rawAdapter, context: context(), settings: { countdownSeconds: 0 } });
        await flushPromises();
        const raw = rawFrame();
        expect(rawLifecycle.acceptFrame(raw)).toBe(true);
        await flushPromises();
        expect(rawAdapter.submitted[0]?.frame).toBe(raw);
        expect(rawLifecycle.getState().frame?.kind).toBe('raw');
        rawLifecycle.dispose();
    });
    it('accepts an original PDF without requesting camera permission or inventing dimensions', async () => {
        const source = new TestSource();
        const adapter = makeAdapter();
        const lifecycle = createOmrCaptureLifecycle({
            source,
            adapter,
            context: context({ cameraEnabled: false, cropReviewRequired: true }),
        });
        const original: OmrCapturedFrame = {
            kind: 'raw',
            file: new File(['original PDF bytes'], 'answer-sheet.pdf', { type: 'application/pdf' }),
            targetKey: target().targetKey,
            inputHash: 'sha256:original-pdf',
            width: null,
            height: null,
            orientation: null,
            sourceGeneration: 3,
        };

        expect(source.connectCalls).toBe(0);
        expect(lifecycle.acceptFrame(original)).toBe(true);
        expect(source.connectCalls).toBe(0);
        expect(lifecycle.getState().phase).toBe('frame_awaiting_review');
        expect(lifecycle.getState().operation?.sourceId).toBe('file-upload');
        expect(lifecycle.getState().frame?.width).toBeNull();
        await lifecycle.confirmCrop({ policy: 'auto_document_quad', quad: null });
        expect(adapter.submitted[0]?.frame).toBe(original);
        expect(lifecycle.getState().phase).toBe('accepted');
        lifecycle.dispose();
    });


    it('reconciles an unknown submit with the same operation and blocks fresh capture', async () => {
        const source = new TestSource();
        const submitted: OmrCaptureOperation[] = [];
        let reconciliation: OmrCaptureAcceptance | null = null;
        const adapter = makeAdapter({
            async submit(_frame, operation) {
                submitted.push(operation);
                throw Object.assign(new Error('The OMR request could not be completed.'), {
                    status: 500,
                    code: 'omr_diagnostic_failed',
                    details: { stage: 'recovery', recovery: 'Check the run status before retrying.' },
                });
            },
            async reconcile(operation) {
                if (reconciliation) return reconciliation;
                throw new Error('outcome still unavailable');
            },
        });
        const lifecycle = createOmrCaptureLifecycle({ source, adapter, context: context(), settings: { countdownSeconds: 0 } });
        await flushPromises();
        expect(lifecycle.acceptFrame(rawFrame())).toBe(true);
        await flushPromises();
        expect(lifecycle.getState().phase).toBe('reconciling');
        expect(lifecycle.getState().message).toContain('The OMR request could not be completed.');
        expect(lifecycle.getState().message).toContain('Stage: recovery.');
        expect(lifecycle.getState().phase).not.toBe('pending');
        expect(lifecycle.trigger('shutter')).toBe(false);
        expect(lifecycle.getState().phase).toBe('reconciling');
        const originalOperation = lifecycle.getState().operation;
        lifecycle.pause('Queue opened.');
        expect(lifecycle.getState().phase).toBe('reconciling');
        lifecycle.updateSettings({ mode: 'auto' });
        expect(lifecycle.getState().phase).toBe('reconciling');
        lifecycle.resume();
        expect(lifecycle.getState().phase).toBe('reconciling');
        expect(lifecycle.getState().operation?.operationId).toBe(originalOperation?.operationId);
        reconciliation = originalOperation ? receipt(originalOperation) : null;
        await lifecycle.retry();
        expect(lifecycle.getState().phase).toBe('accepted');
        expect(lifecycle.getState().operation?.operationId).toBe(originalOperation?.operationId);
        expect(submitted).toHaveLength(1);
        lifecycle.dispose();
    });
    it('reconciles the owned in-page operation after camera, source, and target context change', async () => {
        const source = new TestSource();
        const submitted: OmrCaptureOperation[] = [];
        const reconciled: OmrCaptureOperationReference[] = [];
        const adapter = makeAdapter({
            async submit(_frame, operation) {
                submitted.push(operation);
                throw new Error('response was lost');
            },
            async reconcile(operation) {
                reconciled.push(operation);
                if (reconciled.length === 1) throw new Error('outcome lookup is temporarily unavailable');
                return receipt(operation);
            },
        });
        const lifecycle = createOmrCaptureLifecycle({
            source,
            adapter,
            context: context(),
            settings: { countdownSeconds: 0 },
        });
        await flushPromises();
        expect(lifecycle.acceptFrame(rawFrame())).toBe(true);
        await flushPromises();
        const operation = lifecycle.getState().operation;
        if (operation === null) throw new Error('Expected the exact submitted operation to remain held.');
        expect(lifecycle.getState().phase).toBe('reconciling');
        expect(reconciled).toHaveLength(1);

        lifecycle.updateContext(context({
            target: target({ pageNo: 2, clientCaptureId: 'progressed-target', captureRevision: 8 }),
            sourceGeneration: 9,
            configRevision: 10,
            cameraEnabled: false,
            ready: false,
            paused: true,
        }));
        await lifecycle.retry();

        expect(reconciled).toEqual([operation, operation]);
        expect(submitted).toEqual([operation]);
        expect(lifecycle.getState().phase).toBe('accepted');
        expect(lifecycle.getState().lastAccepted?.operation.operationId).toBe(operation.operationId);
        expect(source.acquisitions).toEqual([]);
        lifecycle.dispose();
    });
    it.each([401, 419])('exposes definite submit session rejection for sign-in/reload/retake without resending stale request (%i)', async (status) => {
        const source = new TestSource();
        const submissions: Array<{ frame: OmrCapturedFrame; operation: OmrCaptureOperation }> = [];
        const reconciliations: OmrCaptureOperationReference[] = [];
        const adapter = makeAdapter({
            async submit(frame, operation) {
                submissions.push({ frame, operation });
                throw Object.assign(new Error('session expired'), { status });
            },
            async reconcile(operation) {
                reconciliations.push(operation);
                return receipt(operation);
            },
        });
        const lifecycle = createOmrCaptureLifecycle({ source, adapter, context: context(), settings: { countdownSeconds: 0 } });
        await flushPromises();
        expect(lifecycle.acceptFrame(rawFrame())).toBe(true);
        await flushPromises();
        const original = lifecycle.getState().operation;
        expect(lifecycle.getState().phase).toBe('rejected');
        expect(lifecycle.getState().sessionRecovery).toBe('submit_rejected');
        expect(lifecycle.getState().retryAvailable).toBe(false);
        expect(lifecycle.getState().message).toContain('reopen this scanner');
        expect(reconciliations).toHaveLength(0);
        expect(lifecycle.trigger('shutter')).toBe(false);
        expect(submissions).toHaveLength(1);
        expect(lifecycle.getState().operation).toBe(original);
        lifecycle.dispose();
    });
    it('holds definite submit rejection through pause, resume, reconfiguration and ready status', async () => {
        const source = new TestSource();
        const submissions: OmrCaptureOperation[] = [];
        const adapter = makeAdapter({
            async submit(_frame, operation) {
                submissions.push(operation);
                throw Object.assign(new Error('session expired'), { status: 419 });
            },
        });
        const lifecycle = createOmrCaptureLifecycle({ source, adapter, context: context(), settings: { countdownSeconds: 0 } });
        await flushPromises();
        const frame = rawFrame();
        expect(lifecycle.acceptFrame(frame)).toBe(true);
        await flushPromises();
        const before = lifecycle.getState();
        const operation = before.operation;
        const message = before.message;
        expect(before.sessionRecovery).toBe('submit_rejected');
        lifecycle.pause('Queue opened.');
        lifecycle.resume();
        lifecycle.updateSettings({ mode: 'auto' });
        await flushPromises();
        source.emit({ type: 'status', status: 'ready' });
        const after = lifecycle.getState();
        expect(after.phase).toBe('rejected');
        expect(after.sessionRecovery).toBe('submit_rejected');
        expect(after.operation).toBe(operation);
        expect(after.frame).toBe(frame);
        expect(after.message).toBe(message);
        expect(lifecycle.acceptFrame(rawFrame())).toBe(false);
        expect(lifecycle.trigger('shutter')).toBe(false);
        await lifecycle.retry();
        expect(submissions).toHaveLength(1);
        expect(source.acquisitions).toHaveLength(0);
        lifecycle.dispose();
    });

    it('keeps a committed-but-unacknowledged capture unresolved across expired reconciliation and resumes its same operation', async () => {
        const source = new TestSource();
        const submissions: OmrCaptureOperation[] = [];
        const reconciliations: OmrCaptureOperationReference[] = [];
        let authExpired = true;
        const adapter = makeAdapter({
            async submit(_frame, operation) {
                submissions.push(operation);
                throw new Error('response lost after commit');
            },
            async reconcile(operation) {
                reconciliations.push(operation);
                if (authExpired) throw Object.assign(new Error('session expired'), { status: 419 });
                return receipt(operation);
            },
        });
        const lifecycle = createOmrCaptureLifecycle({ source, adapter, context: context(), settings: { countdownSeconds: 0 } });
        await flushPromises();
        expect(lifecycle.acceptFrame(rawFrame())).toBe(true);
        await flushPromises();
        const original = lifecycle.getState().operation;
        expect(lifecycle.getState().phase).toBe('reconciling');
        expect(lifecycle.getState().sessionRecovery).toBe('reconcile_required');
        expect(lifecycle.getState().message).toContain('session expired');
        expect(lifecycle.getState().frame).not.toBeNull();
        authExpired = false;
        await lifecycle.retry();
        expect(lifecycle.getState().phase).toBe('accepted');
        expect(submissions).toHaveLength(1);
        expect(reconciliations).toHaveLength(2);
        expect(reconciliations[0]).toBe(original);
        expect(reconciliations[1]).toBe(original);
        lifecycle.dispose();
    });

    it('does not reconcile an unresolved operation under a changed workbench target', async () => {
        const source = new TestSource();
        const reconciliations: OmrCaptureOperationReference[] = [];
        const adapter = makeAdapter({
            async submit() {
                throw new Error('response lost');
            },
            async reconcile(operation) {
                reconciliations.push(operation);
                throw Object.assign(new Error('Session expired while checking the capture.'), { status: 419 });
            },
        });
        const lifecycle = createOmrCaptureLifecycle({ source, adapter, context: context(), settings: { countdownSeconds: 0 } });
        await flushPromises();
        expect(lifecycle.acceptFrame(rawFrame())).toBe(true);
        await flushPromises();
        lifecycle.updateContext(context({ target: freezeOmrCaptureTargetContext({
            workbenchId: 'workbench-2',
            clientCaptureId: 'capture-2',
            pageNo: 1,
            captureRevision: 1,
            instanceId: 22,
            sheetId: 22,
        }) }));
        await lifecycle.retry();
        expect(reconciliations).toHaveLength(1);
        expect(lifecycle.getState().phase).toBe('reconciling');
        expect(lifecycle.getState().message).toContain('not owned by the current workbench context');
        lifecycle.dispose();
    });
    it('restores an actor-bound operation reference as GET-only pending recovery', async () => {
        const source = new TestSource();
        const operation: OmrCaptureOperationReference = {
            operationId: 'saved-operation',
            idempotencyKey: 'saved-idempotency',
            target: target(),
        };
        const reconciled: OmrCaptureOperationReference[] = [];
        const submitted: OmrCaptureOperation[] = [];
        let pending = true;
        const adapter = makeAdapter({
            async submit(_frame, submittedOperation) {
                submitted.push(submittedOperation);
                return receipt(submittedOperation);
            },
            async reconcile(savedOperation) {
                reconciled.push(savedOperation);
                return pending ? receipt(savedOperation, 'pending') : receipt(savedOperation, 'accepted');
            },
        });
        const lifecycle = createOmrCaptureLifecycle({
            source,
            adapter,
            context: context(),
            initialRecovery: operation,
        });
        expect(lifecycle.getState().phase).toBe('reconciling');
        expect(lifecycle.getState().operation).toBeNull();
        expect(lifecycle.getState().recoveryOperation).toBe(operation);
        expect(lifecycle.getState().frame).toBeNull();
        expect(lifecycle.trigger('shutter')).toBe(false);
        expect(lifecycle.acceptFrame(rawFrame())).toBe(false);
        await lifecycle.retry();
        expect(lifecycle.getState().phase).toBe('pending');
        expect(lifecycle.getState().sessionRecovery).toBe('reconcile_required');
        expect(lifecycle.getState().operation).toBeNull();
        expect(lifecycle.getState().recoveryOperation).toBe(operation);
        expect(lifecycle.getState().frame).toBeNull();
        pending = false;
        await lifecycle.retry();
        expect(lifecycle.getState().phase).toBe('accepted');
        expect(reconciled).toEqual([operation, operation]);
        expect(submitted).toHaveLength(0);
        expect(source.acquisitions).toHaveLength(0);
        lifecycle.dispose();
    });
    it('reconciles an exact unresolved operation while the camera is unavailable', async () => {
        const source = new TestSource();
        const operation: OmrCaptureOperationReference = {
            operationId: 'saved-operation-camera-off',
            idempotencyKey: 'saved-idempotency-camera-off',
            target: target(),
        };
        const reconciled: OmrCaptureOperationReference[] = [];
        const lifecycle = createOmrCaptureLifecycle({
            source,
            adapter: makeAdapter({
                async reconcile(savedOperation) {
                    reconciled.push(savedOperation);
                    return {
                        ...receipt(savedOperation, 'pending'),
                        message: 'No persisted receipt confirms this capture yet. Its request outcome remains unconfirmed.',
                        stage: 'recovery',
                        recovery: 'Keep this exact operation held and check again; do not submit another capture.',
                    };
                },
            }),
            context: context({ cameraEnabled: false, ready: false }),
            initialRecovery: operation,
        });

        await lifecycle.retry();

        expect(reconciled).toEqual([operation]);
        expect(lifecycle.getState().phase).toBe('pending');
        expect(lifecycle.getState().stage).toBe('recovery');
        expect(lifecycle.getState().message).toContain('request outcome remains unconfirmed');
        expect(lifecycle.getState().message).not.toContain('processing');
        expect(lifecycle.getState().recovery).toContain('Keep this exact operation held');
        expect(lifecycle.getState().recoveryOperation).toBe(operation);
        lifecycle.dismissRecovery();
        expect(lifecycle.getState().recoveryOperation).toBe(operation);
        expect(source.acquisitions).toHaveLength(0);
        lifecycle.dispose();
    });
    it('keeps restored recovery held with the actual server reconciliation failure', async () => {
        const source = new TestSource();
        const operation: OmrCaptureOperationReference = {
            operationId: 'saved-operation-error',
            idempotencyKey: 'saved-idempotency-error',
            target: target(),
        };
        const reconciled: OmrCaptureOperationReference[] = [];
        const submitted: OmrCaptureOperation[] = [];
        const adapter = makeAdapter({
            async submit(_frame, submittedOperation) {
                submitted.push(submittedOperation);
                return receipt(submittedOperation);
            },
            async reconcile(savedOperation) {
                reconciled.push(savedOperation);
                throw new Error('The existing receipt could not be verified for this target.');
            },
        });
        const lifecycle = createOmrCaptureLifecycle({
            source,
            adapter,
            context: context(),
            initialRecovery: operation,
        });
        await lifecycle.retry();
        const state = lifecycle.getState();
        expect(reconciled).toEqual([operation]);
        expect(submitted).toHaveLength(0);
        expect(state.phase).toBe('reconciling');
        expect(state.sessionRecovery).toBe('reconcile_required');
        expect(state.recoveryOperation).toBe(operation);
        expect(state.message).toContain('The existing receipt could not be verified');
        expect(state.message).not.toContain('Checking the previous capture outcome');
        lifecycle.dispose();
    });
    it('reconciles a cold restored reference after the accepted workbench has no capture target', async () => {
        const source = new TestSource();
        const operation: OmrCaptureOperationReference = {
            operationId: 'cold-saved-operation',
            idempotencyKey: 'cold-saved-idempotency',
            target: target(),
        };
        const reconciled: OmrCaptureOperationReference[] = [];
        const submitted: OmrCaptureOperation[] = [];
        const adapter = makeAdapter({
            async submit(_frame, submittedOperation) {
                submitted.push(submittedOperation);
                return receipt(submittedOperation);
            },
            async reconcile(savedOperation) {
                reconciled.push(savedOperation);
                return receipt(savedOperation, 'accepted');
            },
        });
        const lifecycle = createOmrCaptureLifecycle({
            source,
            adapter,
            context: context({ target: null, ready: false, cameraEnabled: false }),
            initialRecovery: operation,
        });
        await lifecycle.retry();
        const state = lifecycle.getState();
        expect(reconciled).toEqual([operation]);
        expect(submitted).toHaveLength(0);
        expect(state.phase).toBe('accepted');
        expect(state.recoveryOperation).toBeNull();
        expect(state.sessionRecovery).toBeNull();
        expect(state.lastAccepted?.operation.operationId).toBe(operation.operationId);
        lifecycle.dispose();
    });

    it('allows explicit retake after a known rejection without clearing an unknown outcome', async () => {
        const source = new TestSource();
        const lifecycle = createOmrCaptureLifecycle({
            source,
            adapter: makeAdapter({ async submit(_frame, operation) { return receipt(operation, 'rejected'); } }),
            context: context(),
            settings: { countdownSeconds: 0 },
        });
        await flushPromises();
        lifecycle.acceptFrame(rawFrame());
        await flushPromises();
        const rejectedOperation = lifecycle.getState().operation?.operationId;
        lifecycle.cancel();
        expect(lifecycle.getState().operation).toBeNull();
        expect(lifecycle.acceptFrame(rawFrame())).toBe(true);
        expect(lifecycle.getState().operation?.operationId).not.toBe(rejectedOperation);
        lifecycle.dispose();

        const unknown = createOmrCaptureLifecycle({
            source: new TestSource(),
            adapter: makeAdapter({
                async submit() { throw new Error('Response lost.'); },
                async reconcile() { throw new Error('Outcome unconfirmed.'); },
            }),
            context: context(),
            settings: { countdownSeconds: 0 },
        });
        await flushPromises();
        unknown.acceptFrame(rawFrame());
        await flushPromises();
        const unresolvedOperation = unknown.getState().operation?.operationId;
        unknown.cancel();
        expect(unknown.getState().operation?.operationId).toBe(unresolvedOperation);
        expect(unknown.getState().phase).toBe('reconciling');
        expect(unknown.acceptFrame(rawFrame())).toBe(false);
        unknown.dispose();
    });

    it('retries a known rejection with the original frame and crop policy', async () => {
        const source = new TestSource();
        const original = rawFrame();
        let rejectFirst = true;
        const submitted: Array<{ frame: OmrCapturedFrame; operation: OmrCaptureOperation }> = [];
        const adapter = makeAdapter({
            async submit(frame, operation) {
                submitted.push({ frame, operation });
                if (rejectFirst) {
                    rejectFirst = false;
                    return { ...receipt(operation, 'rejected'), message: 'The capture could not be analyzed.', stage: 'processing', recovery: 'Check run status before retrying.' };
                }
                return receipt(operation);
            },
        });
        const lifecycle = createOmrCaptureLifecycle({ source, adapter, context: context(), settings: { countdownSeconds: 0 } });
        await flushPromises();
        expect(lifecycle.acceptFrame(original)).toBe(true);
        await flushPromises();
        expect(lifecycle.getState().phase).toBe('rejected');
        expect(lifecycle.getState().message).toContain('The capture could not be analyzed.');
        expect(lifecycle.getState().message).toContain('Stage: processing.');
        expect(lifecycle.getState().message).toContain('Check run status before retrying.');
        const operationId = lifecycle.getState().operation?.operationId;
        await lifecycle.retry();
        expect(submitted).toHaveLength(2);
        expect(submitted[0]?.frame).toBe(original);
        expect(submitted[1]?.frame).toBe(original);
        expect(submitted[1]?.operation.operationId).toBe(operationId);
        expect(lifecycle.getState().phase).toBe('accepted');
        lifecycle.dispose();
    });
    it('preserves a known rejected upload through pause and settings for exact-original retry', async () => {
        const source = new TestSource();
        const original = rawFrame();
        const submitted: Array<{ frame: OmrCapturedFrame; operation: OmrCaptureOperation }> = [];
        const adapter = makeAdapter({
            async submit(frame, operation) {
                submitted.push({ frame, operation });
                return submitted.length === 1
                    ? { ...receipt(operation, 'rejected'), message: 'Known validation rejection.' }
                    : receipt(operation);
            },
        });
        const lifecycle = createOmrCaptureLifecycle({ source, adapter, context: context(), settings: { countdownSeconds: 0 } });
        await flushPromises();
        expect(lifecycle.acceptFrame(original)).toBe(true);
        await flushPromises();
        const operationId = lifecycle.getState().operation?.operationId;
        lifecycle.pause('Settings opened.');
        lifecycle.updateSettings({ mode: 'manual' });
        lifecycle.resume();
        expect(lifecycle.getState().phase).toBe('rejected');
        expect(lifecycle.getState().frame).toBe(original);
        await lifecycle.retry();
        expect(submitted).toHaveLength(2);
        expect(submitted[1]?.frame).toBe(original);
        expect(submitted[1]?.operation.operationId).toBe(operationId);
        lifecycle.dispose();
    });

    it('validates exact receipts and keeps physical swap separate from target progression', async () => {
        const source = new TestSource();
        const adapter = makeAdapter({
            async submit(_frame, operation) {
                return { ...receipt(operation), targetKey: 'wrong-target' };
            },
            async reconcile(operation) {
                return receipt(operation);
            },
        });
        const lifecycle = createOmrCaptureLifecycle({ source, adapter, context: context(), settings: { countdownSeconds: 0 } });
        await flushPromises();
        expect(lifecycle.acceptFrame(rawFrame())).toBe(true);
        await flushPromises();
        expect(lifecycle.getState().phase).toBe('accepted');
        expect(lifecycle.trigger('shutter')).toBe(false);
        expect(lifecycle.acceptFrame(rawFrame())).toBe(false);
        expect(lifecycle.getState().awaitingSheetSwap).toBe(true);
        lifecycle.updateContext(context({ target: target({ pageNo: 2, clientCaptureId: 'capture-2', captureRevision: 8 }) }));
        expect(lifecycle.getState().lastAccepted?.receipt.pageNo).toBe(1);
        expect(lifecycle.getState().phase).toBe('held');
        expect(lifecycle.trigger('shutter')).toBe(false);
        lifecycle.updateContext(context({ target: target({ pageNo: 2, clientCaptureId: 'capture-2', captureRevision: 8 }), physicalSwapObserved: true }));
        expect(lifecycle.getState().phase).toBe('armed');
        expect(lifecycle.getState().awaitingSheetSwap).toBe(false);
        lifecycle.dispose();
    });
    it('releases the physical-swap hold only for a newly authorized exact replacement target, once', async () => {
        const source = new TestSource();
        const lifecycle = createOmrCaptureLifecycle({
            source,
            adapter: makeAdapter(),
            context: context(),
            settings: { countdownSeconds: 0 },
        });
        await flushPromises();
        expect(lifecycle.acceptFrame(rawFrame())).toBe(true);
        await flushPromises();
        const sourceCaptureId = lifecycle.getState().lastAccepted?.receipt.captureId;
        expect(sourceCaptureId).toBe(918);

        const retakeTarget = target({ clientCaptureId: 'retake-page-1', captureRevision: 8 });
        lifecycle.updateContext(context({
            target: retakeTarget,
            authorizedReplacementOfCaptureId: String(sourceCaptureId),
        }));
        expect(lifecycle.getState().awaitingSheetSwap).toBe(false);
        expect(lifecycle.acceptFrame(rawFrame(retakeTarget.targetKey))).toBe(true);
        await flushPromises();
        expect(lifecycle.getState().awaitingSheetSwap).toBe(true);

        lifecycle.updateContext(context({
            target: target({ pageNo: 2, clientCaptureId: 'page-2', captureRevision: 9 }),
            authorizedReplacementOfCaptureId: String(sourceCaptureId),
        }));
        expect(lifecycle.getState().awaitingSheetSwap).toBe(true);
        expect(lifecycle.getState().phase).toBe('held');
        lifecycle.dispose();
    });

    it('returns an explicit unsupported action instead of a successful no-op', async () => {
        const source = new TestSource();
        const lifecycle = createOmrCaptureLifecycle({ source, adapter: makeAdapter(), context: context(), settings: { countdownSeconds: 0 } });
        await flushPromises();
        const result = await lifecycle.requestAction('next_page');
        expect(result).toEqual({ supported: false, action: 'next_page', reason: 'unauthorized' });
        lifecycle.dispose();
    });

    it('keeps final-page QR commands available without authorizing another capture of that page', async () => {
        const source = new TestSource();
        const actions: string[] = [];
        const adapter = makeAdapter({
            async requestAction(action) {
                actions.push(action);
                return { supported: true, action, accepted: true };
            },
        });
        const lifecycle = createOmrCaptureLifecycle({ source, adapter, context: context({ pageCount: 1 }), settings: { countdownSeconds: 0 } });
        await flushPromises();
        expect(lifecycle.acceptFrame(rawFrame())).toBe(true);
        await flushPromises();
        const next = target({ clientCaptureId: 'capture-2', captureRevision: 8, sheetId: null });
        lifecycle.updateContext(context({
            pageCount: 1, target: null, ready: false,
            authorizedActions: { next_set: { authorizationId: 'next-set-1', targetKey: next.targetKey, acceptedTargetKey: target().targetKey } },
        }));
        source.emit({
            type: 'qr_intent', command: 'NEXT_SET', commandId: 'card-presentation-1',
            sequence: 5, targetKey: target().targetKey, sourceGeneration: 3, configRevision: 4,
        });
        await flushPromises();
        expect(actions).toEqual(['next_set']);
        expect(lifecycle.trigger('shutter')).toBe(false);
        lifecycle.updateContext(context({ pageCount: 1, target: next }));
        await flushPromises();
        source.nextFrame = rawFrame(next.targetKey);
        expect(lifecycle.trigger('shutter')).toBe(true);
        await flushPromises();
        expect(lifecycle.getState().lastAccepted?.receipt.targetKey).toBe(next.targetKey);
        lifecycle.dispose();
    });

    it('restores a persisted accepted reference without inventing a new operation or clearing the placement barrier', async () => {
        const originalSource = new TestSource();
        const original = createOmrCaptureLifecycle({ source: originalSource, adapter: makeAdapter(), context: context(), settings: { countdownSeconds: 0 } });
        await flushPromises();
        original.acceptFrame(rawFrame());
        await flushPromises();
        const persisted = original.getState().lastAccepted;
        original.dispose();
        const source = new TestSource();
        const reloaded = createOmrCaptureLifecycle({
            source, adapter: makeAdapter(), context: context({ target: target({ pageNo: 2 }) }),
            settings: { countdownSeconds: 0 }, initialAccepted: persisted,
        });
        await flushPromises();
        expect(reloaded.getState().lastAccepted?.receipt.captureId).toBe(918);
        expect(reloaded.getState().awaitingSheetSwap).toBe(true);
        expect(reloaded.trigger('shutter')).toBe(false);
        expect(source.acquisitions).toEqual([]);
        reloaded.dispose();
    });

    it('breaks Auto dwell on a real observation gap instead of treating freshness as continuous steadiness', async () => {
        const source = new TestSource();
        source.nextFrame = rawFrame();
        const clock = new TestClock();
        const lifecycle = createOmrCaptureLifecycle({
            source, adapter: makeAdapter(), context: context(), clock,
            settings: { mode: 'auto', steadyDelayMs: 800, applyCountdownToAuto: false },
        });
        await flushPromises();
        let sequence = 0;
        const observe = () => source.emit({ type: 'quality', ready: true, targetKey: target().targetKey, sourceGeneration: 3, configRevision: 4, sequence: ++sequence, lockProgress: 0, result: gatesResult, relativeAgeMs: 0 });
        observe();
        clock.advance(300);
        observe();
        for (let time = 400; time <= 800; time += 100) { clock.advance(100); observe(); }
        await flushPromises();
        expect(source.acquisitions).toEqual([]);
        clock.advance(100); observe();
        clock.advance(100); observe();
        clock.advance(100);
        await flushPromises();
        expect(lifecycle.getState().phase).toBe('accepted');
        lifecycle.dispose();
    });

    it('keeps the unresolved request outcome visible when a delayed media connection fails', async () => {
        const source = new TestSource();
        const connection = Promise.withResolvers<void>();
        source.connect = (listener) => {
            source.listener = listener;
            return connection.promise;
        };
        const lifecycle = createOmrCaptureLifecycle({
            source,
            context: context(),
            adapter: makeAdapter({
                async submit() { throw new TypeError('Capture response lost'); },
                async reconcile(operation) {
                    return { ...receipt(operation, 'pending'), message: 'The original request outcome remains unconfirmed.', stage: 'recovery' };
                },
            }),
        });
        expect(lifecycle.acceptFrame(rawFrame())).toBe(true);
        await flushPromises();
        expect(lifecycle.getState().phase).toBe('pending');
        const operationId = lifecycle.getState().operation?.operationId;
        connection.reject(new Error('Camera permission denied.'));
        await flushPromises();
        expect(lifecycle.getState().phase).toBe('pending');
        expect(lifecycle.getState().operation?.operationId).toBe(operationId);
        expect(lifecycle.getState().message).toBe('The original request outcome remains unconfirmed.');
        expect(lifecycle.getState().stage).toBe('recovery');
        lifecycle.dispose();
    });

    it('keeps an uploaded original in Crop Check when an unrelated camera connection fails or becomes ready', async () => {
        const source = new TestSource();
        let rejectConnection: ((error: Error) => void) | undefined;
        source.connect = (listener) => {
            source.listener = listener;
            return new Promise<void>((_resolve, reject) => { rejectConnection = reject; });
        };
        const adapter = makeAdapter();
        const lifecycle = createOmrCaptureLifecycle({
            source, adapter, context: context({ cropReviewRequired: true }),
        });
        expect(lifecycle.acceptFrame(rawFrame())).toBe(true);
        source.emit({ type: 'status', status: 'error', message: 'Camera permission denied.' });
        expect(lifecycle.getState().phase).toBe('frame_awaiting_review');
        if (!rejectConnection) throw new Error('The camera request was not started.');
        rejectConnection(new Error('Camera permission denied.'));
        await flushPromises();
        expect(lifecycle.getState().phase).toBe('frame_awaiting_review');
        source.emit({ type: 'status', status: 'ready' });
        expect(lifecycle.getState().phase).toBe('frame_awaiting_review');
        await lifecycle.confirmCrop({ policy: 'keep_original', quad: null });
        expect(lifecycle.getState().phase).toBe('accepted');
        expect(source.acquisitions).toEqual([]);
        lifecycle.dispose();
    });

    it('accepts an explicit raw-file selection for a new server target without rearming the camera', async () => {
        const source = new TestSource();
        const previousTarget = target();
        const nextTarget = target({ clientCaptureId: 'next-set', captureRevision: 1, sheetId: null });
        const lifecycle = createOmrCaptureLifecycle({
            source,
            adapter: makeAdapter(),
            context: context({ target: nextTarget, cameraEnabled: false, cropReviewRequired: true }),
            initialAccepted: {
                operation: { operationId: 'accepted-before-reload', target: previousTarget },
                receipt: {
                    status: 'accepted', operationId: 'accepted-before-reload', targetKey: previousTarget.targetKey,
                    pageNo: 1, captureId: 918,
                },
            },
        });
        expect(lifecycle.getState().awaitingSheetSwap).toBe(true);
        expect(lifecycle.trigger('shutter')).toBe(false);
        expect(lifecycle.acceptFrame(rawFrame(previousTarget.targetKey))).toBe(false);
        expect(lifecycle.acceptFrame(stagedFrame(nextTarget.targetKey))).toBe(false);
        expect(lifecycle.acceptFrame(rawFrame(nextTarget.targetKey))).toBe(true);
        expect(lifecycle.getState().phase).toBe('frame_awaiting_review');
        expect(lifecycle.getState().awaitingSheetSwap).toBe(true);
        await lifecycle.confirmCrop({ policy: 'keep_original', quad: null });
        expect(lifecycle.getState().lastAccepted?.receipt.targetKey).toBe(nextTarget.targetKey);
        expect(lifecycle.getState().awaitingSheetSwap).toBe(true);
        expect(lifecycle.acceptFrame(rawFrame(nextTarget.targetKey))).toBe(false);
        expect(source.acquisitions).toEqual([]);
        lifecycle.dispose();
    });
    it('treats an explicit FormRequest validation response as rejected without receipt reconciliation', async () => {
        const source = new TestSource();
        let reconciliations = 0;
        const lifecycle = createOmrCaptureLifecycle({
            source,
            adapter: makeAdapter({
                async submit() {
                    throw new OmrWorkbenchInvalidError('The instance id field must be a string.', {
                        code: 'form_request_validation_failed',
                        status: 422,
                    });
                },
                async reconcile(operation) {
                    reconciliations += 1;
                    return receipt(operation);
                },
            }),
            context: context(),
            settings: { countdownSeconds: 0 },
        });
        await flushPromises();

        expect(lifecycle.acceptFrame(rawFrame())).toBe(true);
        await flushPromises();

        expect(lifecycle.getState().phase).toBe('armed');
        expect(lifecycle.getState().operation).toBeNull();
        expect(lifecycle.getState().message).toContain('No capture was stored.');
        expect(reconciliations).toBe(0);
        lifecycle.dispose();
    });
});
