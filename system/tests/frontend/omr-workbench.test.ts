// @vitest-environment jsdom

import { cleanup, fireEvent, render, waitFor } from '@testing-library/svelte';
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import OmrWorkbench from '../../resources/js/Components/Omr/Workbench/OmrWorkbench.svelte';
import OmrCompletionBar from '../../resources/js/Components/Omr/Workbench/OmrCompletionBar.svelte';
import OmrRecoveryCard from '../../resources/js/Components/Omr/Workbench/OmrRecoveryCard.svelte';
import OmrCameraDeck, { type OmrCameraDeckProps } from '../../resources/js/Components/Omr/Workbench/OmrCameraDeck.svelte';
import OmrPendingCaptureProgress from '../../resources/js/Components/Omr/Workbench/OmrPendingCaptureProgress.svelte';
import {
    buildDiagnosticReviewPayload,
    mapDiagnosticEvidencePage,
} from '../../resources/js/lib/omrDiagnosticReviewEvidence.js';
import {
    OMR_WORKBENCH_COMMANDS,
    acceptOmrWorkbenchProjection,
    isOmrWorkbenchProjection,
    type OmrExecutionKind,
    type OmrWorkbenchProjection,
    type OmrWorkbenchRecoveryAction,
    type OmrWorkbenchRecoveryItem,
    type OmrWorkbenchSheet,
} from '../../resources/js/lib/omrWorkbench.js';
import {
    freezeOmrCaptureTargetContext,
    type OmrCaptureTargetContext,
} from '../../resources/js/lib/omrCameraAcquisition';
import type {
    OmrCaptureAcceptance,
    OmrCaptureAction,
    OmrCaptureOperation,
    OmrCapturedFrame,
} from '../../resources/js/lib/omrCaptureProtocol';
import type {
    OmrCameraBootstrap,
    OmrCameraSourceConfig,
    OmrCameraSourceEvent,
    OmrLocalCameraSource,
} from '../../resources/js/lib/omrCameraSource';
import {
    createOmrWorkbenchClient,
    isOmrWorkbenchClientError,
    OmrWorkbenchConflictError,
    OmrWorkbenchDeniedError,
    OmrWorkbenchExpiredError,
    OmrWorkbenchInfrastructureError,
    OmrWorkbenchInvalidError,
    OmrWorkbenchStaleError,
} from '../../resources/js/lib/omrWorkbenchClient.js';
import {
    assertDiagnosticItemReviewAcknowledged,
    assertProductionItemReviewAcknowledged,
    buildOmrConsoleReviewPayload,
    normalizeAcknowledgedItemReview,
    productionItemReviewKey,
} from '../../resources/js/lib/omrConsoleReview';
import { captureAcceptanceFromProjection } from '../../resources/js/lib/omrCaptureWorkbenchAdapter';
const cameraTestMocks = vi.hoisted(() => ({
    createLocalSource: vi.fn(),
    createPairedSource: vi.fn(),
    fetchPairedFrame: vi.fn(),
    qrToCanvas: vi.fn(),
}));

vi.mock('../../resources/js/lib/omrCameraSource', async (importOriginal) => {
    const actual = (await importOriginal()) as Record<string, unknown>;

    return {
        ...actual,
        createLocalOmrCameraSource: cameraTestMocks.createLocalSource,
        createPairedPhoneCameraSource: cameraTestMocks.createPairedSource,
        fetchPairedFrame: cameraTestMocks.fetchPairedFrame,
    };
});

vi.mock('qrcode', () => ({
    default: { toCanvas: cameraTestMocks.qrToCanvas },
}));

type TestCameraSource = OmrLocalCameraSource & {
    readonly config: OmrCameraSourceConfig | null;
    readonly connected: boolean;
    readonly disconnectCount: number;
    readonly acquireCount: number;
    emit(event: OmrCameraSourceEvent): void;
    emitCommand(command: 'CAPTURE' | 'NEXT_PAGE' | 'NEXT_SET' | 'REDO_LAST'): void;
};

let activeTestCameraSource: TestCameraSource | null = null;
let captureReceiptSequence = 0;

function createTestCameraSource(
    kind: 'local' | 'paired_phone',
): TestCameraSource {
    let video: HTMLVideoElement | null = null;
    let config: OmrCameraSourceConfig | null = null;
    let listener: ((event: OmrCameraSourceEvent) => void) | null = null;
    let connected = false;
    let disconnectCount = 0;
    let acquireCount = 0;
    let commandSequence = 0;

    return {
        id: `${kind}:renderer-test`,
        kind,
        get video() {
            return video;
        },
        latestAnalysis: null,
        available: false,
        supportsQrCommands: true,
        get config() {
            return config;
        },
        get connected() {
            return connected;
        },
        get disconnectCount() {
            return disconnectCount;
        },
        get acquireCount() {
            return acquireCount;
        },
        async connect(onEvent, signal) {
            if (signal?.aborted) {
                throw new DOMException('Camera connection cancelled.', 'AbortError');
            }
            listener = onEvent;
            connected = true;
            onEvent({ type: 'status', status: 'connecting' });
            onEvent({ type: 'status', status: 'ready' });
        },
        attachPreview(element) {
            video = element;
        },
        async configure(next) {
            config = next;
            listener?.({ type: 'configured', config: next });
            listener?.({
                type: 'status',
                status: next.paused ? 'paused' : 'ready',
            });
        },
        async acquire(operation, signal) {
            if (!config || signal?.aborted) {
                throw new DOMException('Camera acquisition cancelled.', 'AbortError');
            }
            acquireCount += 1;
            if (kind === 'paired_phone') {
                return {
                    kind: 'staged',
                    frameId: 'staged-frame-renderer',
                    previewUrl: '/server/private-camera/staged-frame-renderer',
                    commandId: 'staged-command-renderer',
                    pairingId: 'pairing-renderer',
                    targetKey: operation.target.targetKey,
                    inputHash: 'b'.repeat(64),
                    width: 120,
                    height: 160,
                    orientation: 1,
                    sourceGeneration: operation.sourceGeneration,
                };
            }
            const file = new File(['captured-frame'], 'capture.jpg', {
                type: 'image/jpeg',
            });
            return {
                kind: 'raw',
                file,
                targetKey: operation.target.targetKey,
                inputHash: 'a'.repeat(64),
                width: 120,
                height: 160,
                orientation: 1,
                sourceGeneration: operation.sourceGeneration,
            };
        },
        disconnect() {
            disconnectCount += 1;
            connected = false;
            const previousListener = listener;
            listener = null;
            previousListener?.({ type: 'status', status: 'disconnected' });
            video = null;
        },
        emit(event) {
            listener?.(event);
        },
        emitCommand(command) {
            if (!config) return;
            listener?.({
                type: 'qr_intent',
                command,
                targetKey: config.target.targetKey,
                sourceGeneration: config.sourceGeneration,
                configRevision: config.configRevision,
                commandId: `renderer-command-${++commandSequence}`,
                sequence: commandSequence,
            });
        },
    };
}

function captureTarget(
    workbenchId: string,
    clientCaptureId: string,
    pageNo: number,
    captureRevision: number,
    instanceId: string | null = null,
    sheetId: string | null = null,
): OmrCaptureTargetContext {
    const target = freezeOmrCaptureTargetContext({
        workbenchId,
        clientCaptureId,
        pageNo,
        captureRevision,
        instanceId,
        sheetId,
    });
    if (!target) {
        throw new Error('The renderer fixture needs a valid capture target.');
    }
    return target;
}

function captureReceipt(
    operation: Pick<OmrCaptureOperation, 'operationId' | 'target'>,
    status: OmrCaptureAcceptance['status'] = 'accepted',
): OmrCaptureAcceptance {
    return status === 'accepted'
        ? {
              status,
              operationId: operation.operationId,
              targetKey: operation.target.targetKey,
              captureId: `capture-${++captureReceiptSequence}`,
              pageNo: operation.target.pageNo,
          }
        : {
              status,
              operationId: operation.operationId,
              targetKey: operation.target.targetKey,
              pageNo: operation.target.pageNo,
              message: 'The test authority rejected this capture.',
          };
}

function cameraDeckProps(
    onCapture: OmrCameraDeckProps['onCapture'],
    overrides: Partial<OmrCameraDeckProps> = {},
): OmrCameraDeckProps {
    return {
        onCapture,
        onReconcile: async (operation) =>
            captureReceipt(operation, 'rejected'),
        onCaptureAction: async (action) => ({
            supported: true,
            action,
            accepted: true,
        }),
        workbenchId: 'diagnostic:renderer',
        actorId: 12,
        clientCaptureId: 'renderer-page-1',
        currentPage: 1,
        pageCount: 2,
        captureRevision: 1,
        cameraEnabled: true,
        ...overrides,
    };
}

function cameraBootstrap(target: OmrCaptureTargetContext): OmrCameraBootstrap {
    return {
        pairingId: 'pairing-renderer',
        pairCode: '7K5M-2Q',
        sensorUrl: '/assessment/omr/camera?pairingId=pairing-renderer',
        protocolVersion: 'nexiam.omr.camera.v1',
        generation: 8,
        token: 'controller-token-must-not-render',
        target,
        endpoints: {
            join: '/server/camera/join',
            poll: '/server/camera/poll',
            signal: '/server/camera/signal',
            command: '/server/camera/command',
            ack: '/server/camera/ack',
            heartbeat: '/server/camera/heartbeat',
            frames: '/server/camera/frames',
        },
        iceServers: [],
        config: {
            target,
            sourceGeneration: 3,
            configRevision: 5,
            registrationMode: 'fiducial_anchored',
            pageCount: 2,
            mode: 'manual',
            batchTrigger: 'manual_qr',
            steadyDelayMs: 300,
            qrTriggerEnabled: false,
            paused: false,
        },
    };
}

beforeEach(() => {
    vi.stubGlobal('matchMedia', vi.fn((media: string) => Object.assign(new EventTarget(), {
        media,
        matches: false,
        onchange: null,
        addListener: () => {},
        removeListener: () => {},
    })));
    activeTestCameraSource = null;
    captureReceiptSequence = 0;
    cameraTestMocks.createLocalSource.mockReset();
    cameraTestMocks.createPairedSource.mockReset();
    cameraTestMocks.fetchPairedFrame.mockReset();
    cameraTestMocks.qrToCanvas.mockReset();
    cameraTestMocks.qrToCanvas.mockResolvedValue(undefined);
    cameraTestMocks.fetchPairedFrame.mockResolvedValue(
        new Blob(['private-original'], { type: 'image/jpeg' }),
    );
    cameraTestMocks.createLocalSource.mockImplementation(() => {
        activeTestCameraSource = createTestCameraSource('local');
        return activeTestCameraSource;
    });
    cameraTestMocks.createPairedSource.mockImplementation(() => {
        activeTestCameraSource = createTestCameraSource('paired_phone');
        return activeTestCameraSource;
    });
});

afterEach(() => {
    cleanup();
    vi.restoreAllMocks();
    vi.unstubAllGlobals();
    activeTestCameraSource = null;
});

describe('OMR camera assistance renderer', () => {
    it('retains the production controls and waits for an authorized next-page intent before the deferred file', async () => {
        let releaseFirstCapture: (() => void) | null = null;
        let advanceToNextPage: (() => Promise<void>) | null = null;
        const firstCapture = new Promise<void>((resolve) => {
            releaseFirstCapture = resolve;
        });
        const firstTarget = captureTarget(
            'workbench-7',
            'client-capture-1',
            1,
            8,
            'instance-2',
            'sheet-3',
        );
        const secondTarget = captureTarget(
            'workbench-7',
            'client-capture-2',
            2,
            9,
            'instance-2',
            'sheet-3',
        );
        const onCapture = vi.fn(
            async (
                _frame: OmrCapturedFrame,
                operation: OmrCaptureOperation,
            ) => {
                if (onCapture.mock.calls.length === 1) {
                    await firstCapture;
                    await advanceToNextPage?.();
                }
                return captureReceipt(operation);
            },
        );
        const props = cameraDeckProps(onCapture, {
            workbenchId: firstTarget.workbenchId,
            clientCaptureId: firstTarget.clientCaptureId,
            currentPage: firstTarget.pageNo,
            pageCount: 2,
            captureRevision: 8,
            instanceId: 'instance-2',
            sheetId: 'sheet-3',
            authorizedActions: {
                next_page: {
                    authorizationId: 'next-page-2',
                    targetKey: secondTarget.targetKey,
                    acceptedTargetKey: firstTarget.targetKey,
                },
            },
        });
        const view = render(OmrCameraDeck, props);
        const source = activeTestCameraSource;
        advanceToNextPage = async () => {
            await view.rerender({
                ...props,
                clientCaptureId: secondTarget.clientCaptureId,
                currentPage: secondTarget.pageNo,
                captureRevision: 9,
            });
        };

        expect(view.getByRole('button', { name: 'Manual' })).toBeTruthy();
        expect(view.getByRole('button', { name: 'Auto' })).toBeTruthy();
        expect(view.getByRole('button', { name: 'Batch' })).toBeTruthy();
        expect(view.queryByRole('button', { name: 'Manual / QR' })).toBeNull();
        await fireEvent.click(view.getByRole('button', { name: 'Auto' }));
        await fireEvent.click(view.getByRole('button', { name: 'Batch' }));
        expect(
            view.getByRole('button', { name: 'Batch' }).getAttribute('aria-pressed'),
        ).toBe('true');

        await fireEvent.click(view.getByRole('button', { name: 'File batch upload' }));
        const input = view.container.querySelector('input[type="file"]') as HTMLInputElement;
        const files = [
            new File(['one'], 'one.png', { type: 'image/png' }),
            new File(['two'], 'two.png', { type: 'image/png' }),
        ];
        fireEvent.change(input, { target: { files } });

        await waitFor(() => expect(onCapture).toHaveBeenCalledTimes(1));
        const firstFrame = onCapture.mock.calls[0]?.[0];
        const firstOperation = onCapture.mock.calls[0]?.[1];
        expect(firstFrame?.kind).toBe('raw');
        expect(firstFrame?.kind === 'raw' && firstFrame.file.name).toBe('one.png');
        expect(firstOperation?.target).toEqual(firstTarget);
        expect(onCapture.mock.calls[0]?.[2]).toEqual({
            policy: 'auto_document_quad',
            quad: null,
        });

        releaseFirstCapture?.();
        await waitFor(() =>
            expect(view.queryByRole('progressbar', { name: 'Files uploaded' })).toBeNull(),
        );
        expect(onCapture).toHaveBeenCalledTimes(1);

        source?.emitCommand('NEXT_PAGE');
        await waitFor(() => expect(onCapture).toHaveBeenCalledTimes(2));
        const secondFrame = onCapture.mock.calls[1]?.[0];
        expect(secondFrame?.kind === 'raw' ? secondFrame.file.name : null).toBe(
            'two.png',
        );
        expect(onCapture.mock.calls[1]?.[1].target).toEqual(secondTarget);
    });

    it('retries the identical file operation before resuming deferred files in order', async () => {
        let advanceToNextPage: (() => Promise<void>) | null = null;
        const firstTarget = captureTarget(
            'diagnostic:retry-run',
            'capture-page-1',
            1,
            7,
            'instance-retry',
            'sheet-retry',
        );
        const secondTarget = captureTarget(
            'diagnostic:retry-run',
            'capture-page-2',
            2,
            8,
            'instance-retry',
            'sheet-retry',
        );
        const onCapture = vi.fn(
            async (
                _frame: OmrCapturedFrame,
                operation: OmrCaptureOperation,
            ) => {
                if (onCapture.mock.calls.length === 1) {
                    return captureReceipt(operation, 'rejected');
                }
                
                return captureReceipt(operation);
            },
        );
        const props = cameraDeckProps(onCapture, {
            workbenchId: firstTarget.workbenchId,
            clientCaptureId: firstTarget.clientCaptureId,
            currentPage: firstTarget.pageNo,
            pageCount: 2,
            captureRevision: 7,
            instanceId: 'instance-retry',
            sheetId: 'sheet-retry',
            authorizedActions: {
                next_page: {
                    authorizationId: 'retry-next-page',
                    targetKey: secondTarget.targetKey,
                    acceptedTargetKey: firstTarget.targetKey,
                },
            },
        });
        const view = render(OmrCameraDeck, props);
        const source = activeTestCameraSource;
        advanceToNextPage = async () => {
            await view.rerender({
                ...props,
                clientCaptureId: secondTarget.clientCaptureId,
                currentPage: secondTarget.pageNo,
                captureRevision: 8,
            });
        };
        await fireEvent.click(view.getByRole('button', { name: 'File batch upload' }));
        const input = view.container.querySelector('input[type="file"]') as HTMLInputElement;
        const first = new File(['first'], 'first.png', { type: 'image/png' });
        const second = new File(['second'], 'second.png', { type: 'image/png' });
        fireEvent.change(input, { target: { files: [first, second] } });

        await waitFor(() => expect(onCapture).toHaveBeenCalledTimes(1));
        await view.findByRole('button', { name: 'Retry', exact: true });
        expect(onCapture.mock.calls[0]?.[1].target).toEqual(firstTarget);

        await fireEvent.click(view.getByRole('button', { name: 'Retry', exact: true }));
        await waitFor(() => expect(onCapture).toHaveBeenCalledTimes(2));
        expect(onCapture.mock.calls[1]?.[1].operationId).toBe(
            onCapture.mock.calls[0]?.[1].operationId,
        );
        const retriedFrame = onCapture.mock.calls[1]?.[0];
        expect(retriedFrame?.kind === 'raw' ? retriedFrame.file : null).toBe(first);
        expect(onCapture).toHaveBeenCalledTimes(2);
        await waitFor(() =>
            expect(
                view.queryByRole('button', { name: 'Retry', exact: true }),
            ).toBeNull(),
        );
        await advanceToNextPage?.();
        source?.emitCommand('NEXT_PAGE');
        await waitFor(() => expect(onCapture).toHaveBeenCalledTimes(3));
        expect(onCapture.mock.calls[2]?.[1].target).toEqual(secondTarget);
        const deferredFrame = onCapture.mock.calls[2]?.[0];
        expect(deferredFrame?.kind === 'raw' ? deferredFrame.file.name : null).toBe(
            'second.png',
        );
    });

    it('cleans the rejected file and confirmed deferred batch before taking a new photo', async () => {
        const confirm = vi.spyOn(window, 'confirm').mockReturnValue(true);
        const onCapture = vi.fn(async (_frame: OmrCapturedFrame, operation: OmrCaptureOperation) =>
            captureReceipt(operation, onCapture.mock.calls.length === 1 ? 'rejected' : 'accepted'),
        );
        const onResetPreflight = vi.fn();
        const view = render(OmrCameraDeck, cameraDeckProps(onCapture, { onResetPreflight }));
        await fireEvent.click(view.getByRole('button', { name: 'File batch upload' }));
        const input = view.container.querySelector('input[type="file"]') as HTMLInputElement;
        await fireEvent.change(input, { target: { files: [
            new File(['failed'], 'failed.png', { type: 'image/png' }),
            new File(['queued'], 'queued.png', { type: 'image/png' }),
        ] } });
        await view.findByRole('button', { name: 'Retake with new photo' });
        await view.findByRole('button', { name: 'Retry', exact: true });
        await waitFor(() => expect(view.getByRole('button', { name: 'Retry', exact: true }).hasAttribute('disabled')).toBe(false));
        await fireEvent.click(view.getByRole('button', { name: 'Retake with new photo' }));
        expect(confirm).toHaveBeenCalledWith('Discard the remaining queued files and retake this page?');
        await waitFor(() => expect(view.queryByRole('button', { name: 'Retry', exact: true })).toBeNull());
        expect(onResetPreflight).toHaveBeenCalledTimes(1);
        await fireEvent.change(input, { target: { files: [
            new File(['fresh'], 'fresh.png', { type: 'image/png' }),
        ] } });
        await waitFor(() => expect(onCapture).toHaveBeenCalledTimes(2));
        const frame = onCapture.mock.calls[1]?.[0];
        expect(frame?.kind === 'raw' ? frame.file.name : null).toBe('fresh.png');
        expect(onCapture.mock.calls[1]?.[1].operationId).not.toBe(onCapture.mock.calls[0]?.[1].operationId);
    });

    it('submits a raw fiducial-anchored upload without opening Crop Check', async () => {
        const onCapture = vi.fn(
            async (_frame: OmrCapturedFrame, operation: OmrCaptureOperation) =>
                captureReceipt(operation),
        );
        const view = render(
            OmrCameraDeck,
            cameraDeckProps(onCapture, {
                mode: 'manual',
                registrationMode: 'fiducial_anchored',
                cameraEnabled: false,
                workbenchId: 'production:anchored-upload',
                clientCaptureId: 'anchored-capture',
                currentPage: 1,
                captureRevision: 1,
            }),
        );
        await fireEvent.click(view.getByRole('button', { name: 'Upload image file' }));
        const input = view.container.querySelector('input[type="file"]') as HTMLInputElement;
        fireEvent.change(input, {
            target: {
                files: [
                    new File(['anchored'], 'anchored.png', {
                        type: 'image/png',
                    }),
                ],
            },
        });

        await waitFor(() => expect(onCapture).toHaveBeenCalledTimes(1));
        expect(view.queryByRole('dialog', { name: 'Crop Check' })).toBeNull();
        expect(onCapture.mock.calls[0]?.[0].kind).toBe('raw');
        expect(onCapture.mock.calls[0]?.[2].policy).toBe('auto_document_quad');
    });

    it('does not let manual shutter intent manufacture a physical sheet swap', async () => {
        const target = captureTarget('diagnostic:renderer', 'renderer-page-1', 1, 1);
        const prior = { operationId: 'accepted-operation', target };
        const onCapture = vi.fn(async (_frame: OmrCapturedFrame, operation: OmrCaptureOperation) => captureReceipt(operation));
        const view = render(OmrCameraDeck, cameraDeckProps(onCapture, {
            countdownSeconds: 0,
            initialAccepted: { operation: prior, receipt: captureReceipt(prior) },
        }));
        const shutter = view.getByRole('button', { name: 'Capture page 1 of 2' });
        await waitFor(() => expect(activeTestCameraSource?.connected).toBe(true));
        await fireEvent.click(shutter);
        expect(activeTestCameraSource?.acquireCount).toBe(0);
        expect(onCapture).not.toHaveBeenCalled();
        expect(shutter.hasAttribute('disabled')).toBe(true);
    });

    it('cancels the default three-second manual countdown without acquiring a frame', async () => {
        const onCapture = vi.fn(
            async (_frame: OmrCapturedFrame, operation: OmrCaptureOperation) =>
                captureReceipt(operation),
        );
        const view = render(
            OmrCameraDeck,
            cameraDeckProps(onCapture, {
                pageCount: 1,
                workbenchId: 'countdown-workbench',
                clientCaptureId: 'countdown-capture',
            }),
        );

        const source = activeTestCameraSource;
        await waitFor(() =>
            expect(
                view.getByRole('button', { name: 'Capture sheet' }).hasAttribute('disabled'),
            ).toBe(false),
        );
        await fireEvent.click(view.getByRole('button', { name: 'Capture sheet' }));
        expect(view.getByText('Capture in 3')).toBeTruthy();
        await fireEvent.click(view.getByRole('button', { name: 'Cancel countdown' }));
        expect(onCapture).not.toHaveBeenCalled();
        expect(source?.acquireCount).toBe(0);
    });
    it('waits for the QR card removal and a fresh quality observation before countdown', async () => {
        const onCapture = vi.fn(
            async (_frame: OmrCapturedFrame, operation: OmrCaptureOperation) =>
                captureReceipt(operation),
        );
        const props = cameraDeckProps(onCapture, {
            mode: 'batch',
            batchTrigger: 'manual_qr',
            registrationMode: 'document_bound',
            pageCount: 1,
            workbenchId: 'qr-workbench',
            clientCaptureId: 'qr-capture',
            settingsOpen: true,
        });
        const view = render(OmrCameraDeck, props);
        const source = activeTestCameraSource;

        await fireEvent.click(view.getByRole('button', { name: /QR command trigger/ }));
        await view.rerender({ ...props, settingsOpen: false });
        await waitFor(() =>
            expect(
                view.getByRole('button', { name: 'Capture sheet' }).hasAttribute('disabled'),
            ).toBe(false),
        );

        source?.emitCommand('CAPTURE');
        await view.findByRole('button', { name: 'Cancel command' });
        expect(view.queryByRole('button', { name: 'Cancel countdown' })).toBeNull();
        const config = source?.config;
        expect(config).not.toBeNull();
        if (!source || !config) {
            throw new Error('The renderer source must be configured before QR observations.');
        }
        source.emit({
            type: 'qr_card_removed',
            targetKey: config.target.targetKey,
            sourceGeneration: config.sourceGeneration,
            configRevision: config.configRevision,
            sequence: 2,
            observedAt: performance.now(),
        });
        expect(view.queryByRole('button', { name: 'Cancel countdown' })).toBeNull();

        source.emit({
            type: 'quality',
            ready: true,
            targetKey: config.target.targetKey,
            sourceGeneration: config.sourceGeneration,
            configRevision: config.configRevision,
            sequence: 3,
            lockProgress: 0.4,
            relativeAgeMs: 0,
            result: {
                type: 'analysis',
                generation: 1,
                frameId: 1,
                capturedAt: performance.now(),
                quad: [
                    { x: 0.1, y: 0.1 },
                    { x: 0.9, y: 0.1 },
                    { x: 0.9, y: 0.9 },
                    { x: 0.1, y: 0.9 },
                ],
                gates: {
                    in_bounds: true,
                    angle_ok: true,
                    steady_ok: true,
                    lighting_ok: true,
                },
            },
        });
        await view.findByRole('button', { name: 'Cancel countdown' });
        expect(onCapture).not.toHaveBeenCalled();
        await fireEvent.click(view.getByRole('button', { name: 'Cancel countdown' }));
        expect(source.acquireCount).toBe(0);
    });
    it('renders the real source quad in the current video coordinate frame', async () => {
        const onCapture = vi.fn(
            async (_frame: OmrCapturedFrame, operation: OmrCaptureOperation) =>
                captureReceipt(operation),
        );
        const view = render(
            OmrCameraDeck,
            cameraDeckProps(onCapture, {
                mode: 'manual',
                pageCount: 1,
                workbenchId: 'hud-workbench',
                clientCaptureId: 'hud-capture',
            }),
        );
        const video = view.getByLabelText('Live camera preview') as HTMLVideoElement;
        Object.defineProperties(video, { videoWidth: { value: 1280 }, videoHeight: { value: 720 } });
        await fireEvent(video, new Event('loadedmetadata'));
        const source = activeTestCameraSource;
        await waitFor(() => expect(source?.config).not.toBeNull());
        const config = source?.config;
        if (!source || !config) {
            throw new Error('The renderer source must be configured before quality observations.');
        }
        source.emit({
            type: 'quality',
            ready: true,
            targetKey: config.target.targetKey,
            sourceGeneration: config.sourceGeneration,
            configRevision: config.configRevision,
            sequence: 1,
            lockProgress: 0.42,
            relativeAgeMs: 0,
            result: {
                type: 'analysis',
                generation: 1,
                frameId: 1,
                capturedAt: performance.now(),
                quad: [
                    { x: 0.1, y: 0.1 },
                    { x: 0.9, y: 0.1 },
                    { x: 0.9, y: 0.9 },
                    { x: 0.1, y: 0.9 },
                ],
                gates: {
                    in_bounds: true,
                    angle_ok: true,
                    steady_ok: true,
                    lighting_ok: true,
                },
            },
        });

        await waitFor(() =>
            expect(view.container.querySelector('[data-omr-viewfinder-polygon] polygon')?.getAttribute('points')).toBe('128,72 1152,72 1152,648 128,648'),
        );
        expect(onCapture).not.toHaveBeenCalled();
    });

    it('keeps countdown and command-card printing in Settings without claiming a capture', async () => {
        const onCapture = vi.fn(
            async (_frame: OmrCapturedFrame, operation: OmrCaptureOperation) =>
                captureReceipt(operation),
        );
        const view = render(
            OmrCameraDeck,
            cameraDeckProps(onCapture, {
                cameraEnabled: false,
                settingsOpen: true,
                workbenchId: 'workbench-settings',
                clientCaptureId: 'capture-settings',
            }),
        );

        expect(view.getByRole('region', { name: 'Capture settings' })).toBeTruthy();
        expect(view.getByRole('group', { name: 'Batch capture trigger' })).toBeTruthy();
        expect(view.getByLabelText('Steady delay')).toBeTruthy();
        expect(view.getByLabelText('Manual and QR countdown')).toHaveProperty('value', '3');
        expect(view.getByText('Local camera')).toBeTruthy();
        expect(view.getByRole('button', { name: 'Voice cues' })).toBeTruthy();
        expect(view.getByRole('button', { name: /QR command trigger/ })).toBeTruthy();
        await fireEvent.click(view.getByRole('button', { name: 'Manual / QR' }));
        expect(
            view.getByRole('button', { name: 'Manual / QR' }).getAttribute('aria-pressed'),
        ).toBe('true');

        await fireEvent.click(view.getByRole('button', { name: 'Print command cards' }));
        expect(view.getByRole('dialog', { name: 'Print command cards' })).toBeTruthy();
        expect(onCapture).not.toHaveBeenCalled();
    });

    it('fetches a private staged original for Crop Check and submits the selected crop choice', async () => {
        vi.stubGlobal(
            'URL',
            class extends URL {
                static createObjectURL = vi.fn(() => 'blob:private-staged-original');
                static revokeObjectURL = vi.fn();
            },
        );
        const target = captureTarget(
            'paired-crop-workbench',
            'paired-crop-capture',
            1,
            1,
        );
        const bootstrap = cameraBootstrap(target);
        const create = vi.fn(async () => bootstrap);
        const rejoin = vi.fn(async () => bootstrap);
        const revoke = vi.fn(async () => {});
        const onCapture = vi.fn(
            async (
                _frame: OmrCapturedFrame,
                operation: OmrCaptureOperation,
            ) => captureReceipt(operation),
        );
        const props = cameraDeckProps(onCapture, {
            workbenchId: target.workbenchId,
            clientCaptureId: target.clientCaptureId,
            currentPage: 1,
            pageCount: 1,
            captureRevision: 1,
            mode: 'manual',
            registrationMode: 'document_bound',
            countdownSeconds: 0,
            cameraPairing: { create, rejoin, revoke },
            settingsOpen: true,
        });
        const view = render(OmrCameraDeck, props);

        await fireEvent.click(view.getByRole('button', { name: 'Connect phone' }));
        await waitFor(() => expect(view.getByText('7K5M-2Q')).toBeTruthy());
        await view.rerender({ ...props, settingsOpen: false });
        await waitFor(() =>
            expect(
                view.getByRole('button', { name: 'Capture sheet' }).hasAttribute('disabled'),
            ).toBe(false),
        );
        await fireEvent.click(view.getByRole('button', { name: 'Capture sheet' }));
        await view.findByRole('dialog', { name: 'Crop Check' });
        expect(onCapture).not.toHaveBeenCalled();
        await waitFor(() =>
            expect(cameraTestMocks.fetchPairedFrame).toHaveBeenCalledTimes(1),
        );
        const [previewUrl, auth] =
            cameraTestMocks.fetchPairedFrame.mock.calls[0] ?? [];
        expect(previewUrl).toBe('/server/private-camera/staged-frame-renderer');
        expect(auth).toMatchObject({
            role: 'controller',
            token: bootstrap.token,
            generation: bootstrap.generation,
        });

        await fireEvent.click(view.getByRole('button', { name: 'Keep Original' }));
        await waitFor(() => expect(onCapture).toHaveBeenCalledTimes(1));
        const [frame, operation, crop] = onCapture.mock.calls[0] ?? [];
        expect(frame?.kind).toBe('staged');
        expect(operation?.target).toEqual(target);
        expect(crop).toEqual({ policy: 'keep_original', quad: null });
    });
    it('connects a paired phone from the source settings without exposing its controller token', async () => {
        const target = captureTarget(
            'pairing-workbench',
            'pairing-capture',
            1,
            1,
        );
        const bootstrap = cameraBootstrap(target);
        const renewedBootstrap: OmrCameraBootstrap = {
            ...bootstrap,
            pairingId: 'pairing-renderer-renewed',
            pairCode: '3Q8Z-4M',
            sensorUrl: '/assessment/omr/camera?pairingId=pairing-renderer-renewed',
            generation: 9,
            token: 'renewed-controller-secret',
            config: {
                ...bootstrap.config,
                sourceGeneration: 4,
                configRevision: 6,
            },
        };
        const create = vi.fn(async () => bootstrap);
        const rejoin = vi.fn(async () => renewedBootstrap);
        const revoke = vi.fn(async () => {});
        const onCapture = vi.fn(
            async (_frame: OmrCapturedFrame, operation: OmrCaptureOperation) =>
                captureReceipt(operation),
        );
        const view = render(
            OmrCameraDeck,
            cameraDeckProps(onCapture, {
                workbenchId: target.workbenchId,
                clientCaptureId: target.clientCaptureId,
                currentPage: target.pageNo,
                captureRevision: 1,
                cameraPairing: { create, rejoin, revoke },
                settingsOpen: true,
            }),
        );

        await fireEvent.click(view.getByRole('button', { name: 'Connect phone' }));
        await waitFor(() =>
            expect(view.getByText('7K5M-2Q')).toBeTruthy(),
        );
        expect(create).toHaveBeenCalledTimes(1);
        expect(activeTestCameraSource?.kind).toBe('paired_phone');
        const link = view.getByRole('link', { name: 'Open phone camera link' });
        expect(link.getAttribute('href')).toContain('pairingId=pairing-renderer');
        expect(link.getAttribute('href')).not.toContain('controller-token');
        expect(view.container.textContent).not.toContain('controller-token-must-not-render');
        expect(activeTestCameraSource?.video).toBe(
            view.container.querySelector('video'),
        );

        await fireEvent.click(
            view.getByRole('button', { name: 'Replace / reconnect phone' }),
        );
        const confirmation = view.getByRole('group', {
            name: 'Confirm phone reconnection',
        });
        expect(confirmation).toBeTruthy();
        expect(rejoin).not.toHaveBeenCalled();
        await fireEvent.click(
            view.getByRole('button', { name: 'Confirm reconnect' }),
        );
        await waitFor(() => expect(rejoin).toHaveBeenCalledWith(bootstrap));
        await waitFor(() => expect(view.getByText('3Q8Z-4M')).toBeTruthy());
        expect(view.container.textContent).not.toContain('renewed-controller-secret');
        expect(
            view.getByRole('link', { name: 'Open phone camera link' }).getAttribute('href'),
        ).toContain('pairing-renderer-renewed');
        expect(activeTestCameraSource?.kind).toBe('paired_phone');

        await fireEvent.click(view.getByRole('button', { name: 'Disconnect phone' }));
        await waitFor(() => expect(revoke).toHaveBeenCalledWith(renewedBootstrap));
        expect(activeTestCameraSource?.kind).toBe('local');
        expect(view.queryByText('3Q8Z-4M')).toBeNull();
        expect(onCapture).not.toHaveBeenCalled();
    });

    it('keeps the camera deck mounted and connected through Review and Gallery navigation', async () => {
        const onCapture = vi.fn(
            async (_frame: OmrCapturedFrame, operation: OmrCaptureOperation) =>
                captureReceipt(operation),
        );
        const onOpenGallery = vi.fn();
        const props = {
            projection: projection('diagnostic'),
            initialStageView: 'live' as const,
            cameraDeckProps: cameraDeckProps(onCapture, { onOpenGallery }),
        };
        const view = render(OmrWorkbench, props);
        await waitFor(() =>
            expect(view.container.querySelector('[data-omr-camera-deck]')).not.toBeNull(),
        );
        const source = activeTestCameraSource;

        await view.rerender({ ...props, stageView: 'review' });
        await waitFor(() =>
            expect(view.container.querySelector('[data-omr-stage-view="review"]')).not.toBeNull(),
        );
        const deck = view.container.querySelector('[data-omr-camera-deck]');
        expect(deck).not.toBeNull();
        expect(deck?.closest('[aria-hidden="true"]')).not.toBeNull();
        expect(source?.disconnectCount).toBe(0);

        await view.rerender({ ...props, stageView: 'camera' });
        await waitFor(() =>
            expect(
                view.container.querySelector('[data-omr-camera-deck]')?.closest('[aria-hidden="true"]'),
            ).toBeNull(),
        );
        await fireEvent.click(view.getByRole('button', { name: 'Open scans gallery' }));
        expect(onOpenGallery).toHaveBeenCalledTimes(1);
        expect(source?.disconnectCount).toBe(0);
    });
    it('releases a cancelled file Crop Check waiter so another original can be selected', async () => {
        const view = render(OmrCameraDeck, {
            props: cameraDeckProps(vi.fn(async (_frame, operation) => captureReceipt(operation)), {
                mode: 'manual',
                registrationMode: 'document_bound',
                singleUpload: true,
                pageCount: 1,
                countdownSeconds: 0,
            }),
        });
        const input = view.container.querySelector('input[type="file"]') as HTMLInputElement;
        const select = (name: string) => fireEvent.change(input, {
            target: { files: [new File([name], name, { type: 'image/png' })] },
        });
        await select('first.png');
        await view.findByRole('dialog', { name: 'Crop Check' });
        await fireEvent.click(view.getByRole('button', { name: 'Retake / Cancel' }));
        await waitFor(() => expect(view.queryByRole('dialog', { name: 'Crop Check' })).toBeNull());
        await waitFor(() => expect(input.disabled).toBe(false));
        await select('second.png');
        expect(await view.findByRole('dialog', { name: 'Crop Check' })).toBeTruthy();
    });
    it('runs authorized Next page from the rendered settings recovery control without a QR event', async () => {
        const current = captureTarget('action-workbench', 'page-one', 1, 1);
        const next = captureTarget('action-workbench', 'page-two', 2, 2);
        const onAction = vi.fn(async (action: OmrCaptureAction) => ({
            supported: true as const,
            action,
            accepted: true,
        }));
        const onCapture = vi.fn(async (_frame: OmrCapturedFrame, operation: OmrCaptureOperation) => captureReceipt(operation));
        const props = cameraDeckProps(onCapture, {
            workbenchId: current.workbenchId,
            clientCaptureId: current.clientCaptureId,
            currentPage: 1,
            captureRevision: 1,
            pageCount: 2,
            authorizedActions: {
                next_page: {
                    authorizationId: 'authorized-next',
                    targetKey: next.targetKey,
                    acceptedTargetKey: current.targetKey,
                },
            },
            onCaptureAction: onAction,
        });
        const view = render(OmrCameraDeck, { props });
        const input = view.container.querySelector('input[type="file"]') as HTMLInputElement;
        await fireEvent.change(input, {
            target: { files: [new File(['page'], 'page.png', { type: 'image/png' })] },
        });
        await waitFor(() => expect(onCapture).toHaveBeenCalledTimes(1));
        await waitFor(() => expect(view.queryByRole('progressbar', { name: 'Files uploaded' })).toBeNull());
        await view.rerender({ ...props, settingsOpen: true });
        await waitFor(() => expect(view.getByRole('button', { name: 'Next page' }).hasAttribute('disabled')).toBe(false));
        await fireEvent.click(view.getByRole('button', { name: 'Next page' }));
        await waitFor(() => expect(onAction).toHaveBeenCalledWith('next_page', expect.anything()));
        await waitFor(() => expect(view.getByText(/Action accepted/)).toBeTruthy());
    });
});

function projection(
    executionKind: OmrExecutionKind = 'production',
    overrides: Partial<OmrWorkbenchProjection> = {},
): OmrWorkbenchProjection {
    const base: OmrWorkbenchProjection = {
        workbench_id:
            executionKind === 'production'
                ? 'production:7:9'
                : 'diagnostic:run-1',
        execution_kind: executionKind,
        revision: 1,
        phase: 'review',
        context: {
            execution_kind: executionKind,
            actor_id: 12,
            session_id: 7,
            run_id: 9,
            title:
                executionKind === 'production'
                    ? 'Production grading run'
                    : 'Diagnostic answer-sheet run',
            subtitle:
                executionKind === 'production'
                    ? 'Server-owned grading context'
                    : 'Setup-owned diagnostic context',
            progress: { completed: 1, total: 2, known: true },
        },
        sheets: [
            {
                id: 1,
                client_capture_id: 'capture-1',
                status: 'ready',
                version: 1,
                stage: 'review',
                pages: [{ page_no: 1, status: 'captured', captured: true }],
            },
        ],
        evidence: {
            references: [],
            items: [],
            captures: [],
            redacted: true,
        },
        recovery: { items: [], needs_attention: [], count: 0, outcome: null },
        completion: null,
        commands: [...OMR_WORKBENCH_COMMANDS],
    };

    return {
        ...base,
        ...overrides,
        context: { ...base.context, ...(overrides.context ?? {}) },
    };
}
type RecordedRequest = {
    url: string;
    init?: RequestInit;
};

function jsonRequestBody(request: RecordedRequest): Record<string, unknown> {
    return JSON.parse(String(request.init?.body)) as Record<string, unknown>;
}

describe('OMR item review acknowledgment', () => {
    const item = {
        id: 12,
        item_id: 'Q-1',
        capture_id: 41,
        machine: {
            state: 'marked',
            evidence_hash: 'a'.repeat(64),
            selected_option: 'A',
        },
        options: [{ code: 'A' }, { code: 'B' }],
        parent_version: 7,
        item_revision: 2,
    };

    it('resolves the production item key from its stable area and question identity', () => {
        expect(
            productionItemReviewKey({ area_code: 'vErBaL', question_no: 4 }),
        ).toBe('VERBAL_Q_4');
    });

    it('preserves the selected item option and refuses options outside its pinned set', () => {
        const payload = buildOmrConsoleReviewPayload(
            {
                decision: 'set_option',
                selected_option: 'B',
                reason_code: 'manual_correction',
                note: 'Selected answer corrected.',
                expected_parent_version: 7,
                expected_item_revision: 2,
            },
            item,
            'stable-key',
        );

        expect(payload.selected_option).toBe('B');
        expect(payload.decision).toBe('set_option');
        expect(() =>
            buildOmrConsoleReviewPayload(
                {
                    decision: 'set_option',
                    selected_option: 'C',
                    expected_parent_version: 7,
                    expected_item_revision: 2,
                },
                item,
                'stable-key',
            ),
        ).toThrow('pinned to this item');
    });

    it('rejects failed or stale production acknowledgments', () => {
        expect(() =>
            normalizeAcknowledgedItemReview({
                outcome: 'failed',
                code: 'omr_review_item_revision_stale',
            }),
        ).toThrow('did not acknowledge');
        const request = buildOmrConsoleReviewPayload(
            {
                decision: 'set_option',
                selected_option: 'B',
                reason_code: 'manual_correction',
                note: 'Selected answer corrected.',
                expected_parent_version: 7,
                expected_item_revision: 2,
            },
            item,
            'stable-key',
        );
        expect(() =>
            assertProductionItemReviewAcknowledged(
                {
                    outcome: 'succeeded',
                    receipt: {
                        parent_id: 41,
                        item_key: 'Q-1',
                        revision: 3,
                        decision: 'set_option',
                        selected_option: 'B',
                        evidence_hash: 'a'.repeat(64),
                        parent_version: 8,
                    },
                    review: {
                        revision: 3,
                        decision: 'set_option',
                        selected_option: 'B',
                        reason_code: 'manual_correction',
                        note: 'Selected answer corrected.',
                        evidence_hash: 'a'.repeat(64),
                    },
                },
                request,
                41,
                'Q-1',
            ),
        ).not.toThrow();
        expect(() =>
            assertProductionItemReviewAcknowledged(
                {
                    outcome: 'succeeded',
                    receipt: {
                        parent_id: 41,
                        item_key: 'Q-1',
                        revision: 2,
                        decision: 'set_option',
                        selected_option: 'B',
                        evidence_hash: 'a'.repeat(64),
                        parent_version: 8,
                    },
                    review: {
                        revision: 2,
                        decision: 'set_option',
                        selected_option: 'B',
                        evidence_hash: 'a'.repeat(64),
                    },
                },
                request,
                41,
                'Q-1',
            ),
        ).toThrow('stale');
    });

    it('requires the changed diagnostic item in a newer projection before acknowledging save', () => {
        const request = {
            decision: 'set_option' as const,
            selected_option: 'B',
            reason_code: null,
            note: null,
            expected_parent_version: 7,
            expected_item_revision: 2,
            reviewed_machine_evidence_hash: 'a'.repeat(64),
            idempotency_key: 'stable-key',
        };
        const previous = {
            workbench_id: 'diagnostic:run-1',
            execution_kind: 'diagnostic',
            revision: 9,
            context: { run_id: 9 },
        };
        const acknowledged = {
            workbench_id: 'diagnostic:run-1',
            execution_kind: 'diagnostic',
            revision: 10,
            context: { run_id: 9 },
            evidence: {
                items: [
                    {
                        id: 12,
                        capture_id: 41,
                        review: {
                            revision: 3,
                            decision: 'set_option',
                            selected_option: 'B',
                            reason_code: null,
                            note: null,
                            evidence_hash: 'a'.repeat(64),
                        },
                    },
                ],
            },
        };

        expect(() =>
            assertDiagnosticItemReviewAcknowledged(
                previous,
                acknowledged,
                41,
                12,
                request,
            ),
        ).not.toThrow();
        expect(() =>
            assertDiagnosticItemReviewAcknowledged(
                previous,
                {
                    ...acknowledged,
                    evidence: {
                        items: [
                            {
                                id: 13,
                                capture_id: 41,
                                review: {
                                    revision: 3,
                                    decision: 'set_option',
                                    selected_option: 'B',
                                    evidence_hash: 'a'.repeat(64),
                                },
                            },
                        ],
                    },
                },
                41,
                12,
                request,
            ),
        ).toThrow('did not acknowledge this item decision');
        expect(() =>
            assertDiagnosticItemReviewAcknowledged(
                previous,
                {
                    ...acknowledged,
                    recovery: {
                        outcome: { kind: 'stale', code: 'revision_changed' },
                    },
                },
                41,
                12,
                request,
            ),
        ).toThrow('was not acknowledged');
        expect(() =>
            assertDiagnosticItemReviewAcknowledged(
                { ...previous, revision: 10 },
                acknowledged,
                41,
                12,
                request,
            ),
        ).not.toThrow();
    });
});

describe('OMR workbench projection contract', () => {
    it('accepts a valid projection and rejects malformed identity or kind', () => {
        const valid = projection('production');

        expect(isOmrWorkbenchProjection(valid)).toBe(true);
        expect(
            isOmrWorkbenchProjection({
                ...valid,
                workbench_id: 'diagnostic:run-1',
            }),
        ).toBe(false);
        expect(
            isOmrWorkbenchProjection({
                ...valid,
                context: { ...valid.context, execution_kind: 'diagnostic' },
            }),
        ).toBe(false);
        expect(isOmrWorkbenchProjection({ ...valid, revision: 0 })).toBe(false);
    });

    it('accepts production operational sheet stages and structured recovery detail', () => {
        const observed = projection('production', {
            sheets: [
                {
                    ...projection('production').sheets[0],
                    status: 'needs_review',
                    stage: 'needs_review',
                    recovery: {
                        technical_detail: { exam_session_id: 4 },
                    },
                },
            ],
        });

        expect(isOmrWorkbenchProjection(observed)).toBe(true);
        expect(acceptOmrWorkbenchProjection(null, observed)).toBe(true);
        expect(observed.phase).toBe('review');
        expect(
            isOmrWorkbenchProjection({
                ...observed,
                phase: 'needs_review',
            }),
        ).toBe(false);
        expect(
            isOmrWorkbenchProjection({
                ...observed,
                sheets: [
                    {
                        ...observed.sheets[0],
                        stage: 42,
                    },
                ],
            }),
        ).toBe(false);
        expect(
            isOmrWorkbenchProjection({
                ...observed,
                sheets: [
                    {
                        ...observed.sheets[0],
                        recovery: {
                            technical_detail: { exam_session_id: undefined },
                        },
                    },
                ],
            }),
        ).toBe(false);
        expect(
            isOmrWorkbenchProjection({
                ...observed,
                sheets: [
                    {
                        ...observed.sheets[0],
                        recovery: {
                            technical_detail: {
                                exam_session_id: 'x'.repeat(16_385),
                            },
                        },
                    },
                ],
            }),
        ).toBe(false);
    });

    it('accepts only a newer revision for the same workbench identity and kind', () => {
        const current = projection('production', { revision: 4 });
        const newer = { ...current, revision: 5 };
        const sameRevision = { ...current, revision: 4 };
        const older = { ...current, revision: 3 };
        const differentIdentity = {
            ...current,
            workbench_id: 'production:7:10',
            revision: 5,
        };
        const differentKind = projection('diagnostic', { revision: 5 });

        expect(acceptOmrWorkbenchProjection(null, current)).toBe(true);
        expect(acceptOmrWorkbenchProjection(current, newer)).toBe(true);
        expect(acceptOmrWorkbenchProjection(current, sameRevision)).toBe(false);
        expect(acceptOmrWorkbenchProjection(current, older)).toBe(false);
        expect(acceptOmrWorkbenchProjection(current, differentIdentity)).toBe(
            false,
        );
        expect(acceptOmrWorkbenchProjection(current, differentKind)).toBe(
            false,
        );
        expect(
            acceptOmrWorkbenchProjection(current, {
                ...newer,
                revision: 0,
            }),
        ).toBe(false);
    });
});


describe('OMR durable capture feedback adapter', () => {
    const operation: OmrCaptureOperation = {
        operationId: 'operation-analysis-failed',
        idempotencyKey: 'idempotency-analysis-failed',
        target: captureTarget('production:workbench', 'capture-client', 1, 4),
        sourceId: 'local-camera',
        sourceGeneration: 1,
        configRevision: 1,
        trigger: 'shutter',
        mode: 'manual',
    };

    it('keeps accepted receipt distinct from durable analysis failure details', () => {
        const result = captureAcceptanceFromProjection({
            workbench_id: 'production:workbench',
            context: {
                capture_receipt: {
                    operation_id: operation.operationId,
                    target_key: operation.target.targetKey,
                    page_no: 1,
                    status: 'accepted',
                    capture_id: 41,
                },
            },
            sheets: [{
                id: 41,
                failure: {
                    code: 'analysis_failed',
                    message: 'The capture was received, but analysis did not complete.',
                    stage: 'processing',
                    recovery: 'Retry this capture.',
                },
            }],
        }, operation);

        expect(result.status).toBe('accepted');
        expect(result.message).toContain('analysis did not complete');
        expect(result.stage).toBe('processing');
        expect(result.recovery).toBe('Retry this capture.');
    });

    it('does not call a synthetic missing-receipt pending status processing', () => {
        expect(() => captureAcceptanceFromProjection({
            workbench_id: 'production:workbench',
            context: {
                capture_receipt: {
                    operation_id: operation.operationId,
                    target_key: operation.target.targetKey,
                    page_no: 1,
                    status: 'pending',
                    capture_id: null,
                },
            },
            sheets: [],
        }, operation)).toThrow('outcome is unconfirmed');
    });
});

describe('OMR workbench typed client contract', () => {
    it('sends exact production revision fields for each command', async () => {
        const requests: RecordedRequest[] = [];
        let responseRevision = 0;
        const fetchImpl = vi.fn(
            async (input: RequestInfo | URL, init?: RequestInit) => {
                requests.push({ url: String(input), init });
                responseRevision += 1;
                return new Response(
                    JSON.stringify(
                        projection('production', {
                            revision: responseRevision,
                        }),
                    ),
                    {
                        status: 200,
                        headers: { 'Content-Type': 'application/json' },
                    },
                );
            },
        );
        const client = createOmrWorkbenchClient({
            workbenchId: 'production:7:9',
            executionKind: 'production',
            urls: {
                show: '/grading/sessions/7/omr/batches/9/workbench',
                capture: '/grading/sessions/7/omr/batches/9/workbench/capture',
                retry: (sheetId) =>
                    `/grading/sessions/7/omr/batches/9/workbench/sheets/${sheetId}/retry`,
                review: (sheetId) =>
                    `/grading/sessions/7/omr/batches/9/workbench/sheets/${sheetId}/review`,
                resolveApplicant: (sheetId) =>
                    `/grading/sessions/7/omr/batches/9/workbench/sheets/${sheetId}/resolve-applicant`,
                resolveDuplicate: (sheetId) =>
                    `/grading/sessions/7/omr/batches/9/workbench/sheets/${sheetId}/resolve-duplicate`,
                complete:
                    '/grading/sessions/7/omr/batches/9/workbench/complete',
            },
            fetchImpl,
        });

        await client.captureStart(
            {
                kind: 'raw',
                file: new File(['scan'], 'scan.png', { type: 'image/png' }),
            },
            { client_capture_id: 'capture-1' },
            1,
        );
        await client.captureRetry(
            41,
            { sheet_id: 41, page_no: 1, reason: 'retry' },
            1,
        );
        await client.reviewSave(12, { sheet_id: 12, action: 'confirm' }, 2);
        await client.resolveApplicant(
            12,
            {
                sheet_id: 12,
                exam_assignment_id: 22,
                reason: 'Explicit roster correction',
            },
            3,
        );
        await client.resolveDuplicate(
            12,
            {
                survivor_sheet_id: 12,
                rejected_sheet_id: 41,
                survivor_version: 1,
                rejected_version: 2,
                reason: 'Retain the earlier verified capture',
            },
            4,
        );
        await client.productionComplete(5);

        expect(requests[0]?.url).toBe(
            '/grading/sessions/7/omr/batches/9/workbench/capture',
        );

        const captureBody = requests[0]?.init?.body;
        expect(captureBody).toBeInstanceOf(FormData);
        const captureForm = captureBody as FormData;
        expect(captureForm.get('client_capture_id')).toBe('capture-1');
        expect(captureForm.get('expected_revision')).toBe('1');
        expect(captureForm.get('expected_run_revision')).toBeNull();
        expect(captureForm.get('expected_parent_version')).toBeNull();
        expect(captureForm.get('expected_version')).toBeNull();

        expect(requests[1]?.url).toBe(
            '/grading/sessions/7/omr/batches/9/workbench/sheets/41/retry',
        );
        expect(jsonRequestBody(requests[1])).toEqual({
            sheet_id: 41,
            page_no: 1,
            reason: 'retry',
            expected_revision: 1,
        });

        expect(requests[2]?.url).toBe(
            '/grading/sessions/7/omr/batches/9/workbench/sheets/12/review',
        );
        expect(jsonRequestBody(requests[2])).toEqual({
            sheet_id: 12,
            action: 'confirm',
            expected_revision: 2,
        });

        expect(requests[3]?.url).toBe(
            '/grading/sessions/7/omr/batches/9/workbench/sheets/12/resolve-applicant',
        );
        expect(jsonRequestBody(requests[3])).toEqual({
            sheet_id: 12,
            exam_assignment_id: 22,
            reason: 'Explicit roster correction',
            expected_revision: 3,
        });

        expect(requests[4]?.url).toBe(
            '/grading/sessions/7/omr/batches/9/workbench/sheets/12/resolve-duplicate',
        );
        expect(jsonRequestBody(requests[4])).toEqual({
            survivor_sheet_id: 12,
            rejected_sheet_id: 41,
            survivor_version: 1,
            rejected_version: 2,
            reason: 'Retain the earlier verified capture',
            expected_revision: 4,
        });

        expect(requests[5]?.url).toBe(
            '/grading/sessions/7/omr/batches/9/workbench/complete',
        );
        const completionBody = jsonRequestBody(requests[5]);
        expect(Object.keys(completionBody).sort()).toEqual([
            'expected_revision',
            'idempotency_key',
        ]);
        expect(completionBody.expected_revision).toBe(5);
        expect(typeof completionBody.idempotency_key).toBe('string');
    });

    it('sends exact diagnostic revision fields and scoped resource identifiers', async () => {
        const requests: RecordedRequest[] = [];
        let responseRevision = 0;
        const fetchImpl = vi.fn(
            async (input: RequestInfo | URL, init?: RequestInit) => {
                requests.push({ url: String(input), init });
                responseRevision += 1;
                return new Response(
                    JSON.stringify(
                        projection('diagnostic', {
                            revision: responseRevision,
                        }),
                    ),
                    {
                        status: 200,
                        headers: { 'Content-Type': 'application/json' },
                    },
                );
            },
        );
        const client = createOmrWorkbenchClient({
            workbenchId: 'diagnostic:run-1',
            executionKind: 'diagnostic',
            urls: {
                show: '/setup/omr-test/show',
                capture: '/setup/omr-test/capture',
                retry: (captureId) =>
                    `/setup/omr-test/captures/${captureId}/retry`,
                review: (itemId) => `/setup/omr-test/items/${itemId}/review`,
                compare: '/setup/omr-test/compare',
                complete: '/setup/omr-test/complete',
            },
            fetchImpl,
        });

        await client.captureStart(
            {
                kind: 'raw',
                file: new File(['scan'], 'scan.png', { type: 'image/png' }),
            },
            { reason: 'capture' },
            1,
        );
        await client.captureRetry(41, { reason: 'retry' }, 1);
        await client.reviewSave(12, { decision: 'confirm' }, 2);
        await client.diagnosticCompare(3);
        await client.diagnosticComplete(4);

        const captureBody = requests[0]?.init?.body;
        expect(captureBody).toBeInstanceOf(FormData);
        const captureForm = captureBody as FormData;
        expect(captureForm.get('reason')).toBe('capture');
        expect(captureForm.get('expected_revision')).toBe('1');
        expect(captureForm.get('expected_run_revision')).toBeNull();
        expect(captureForm.get('expected_parent_version')).toBeNull();
        expect(captureForm.get('expected_version')).toBeNull();

        expect(jsonRequestBody(requests[1])).toEqual({
            reason: 'retry',
            expected_revision: 1,
            capture_id: 41,
        });
        expect(jsonRequestBody(requests[2])).toEqual({
            decision: 'confirm',
            expected_run_revision: 2,
            item_evidence_id: 12,
        });

        const comparisonBody = jsonRequestBody(requests[3]);
        expect(Object.keys(comparisonBody).sort()).toEqual([
            'expected_revision',
            'idempotency_key',
        ]);
        expect(comparisonBody.expected_revision).toBe(3);
        expect(typeof comparisonBody.idempotency_key).toBe('string');

        const diagnosticCompletionBody = jsonRequestBody(requests[4]);
        expect(Object.keys(diagnosticCompletionBody).sort()).toEqual([
            'expected_revision',
            'idempotency_key',
        ]);
        expect(diagnosticCompletionBody.expected_revision).toBe(4);
        expect(typeof diagnosticCompletionBody.idempotency_key).toBe('string');
    });

    it.each([
        {
            label: 'stale revisions',
            status: 409,
            body: { code: 'stale_revision', message: 'Refresh the run.' },
            errorType: OmrWorkbenchStaleError,
            kind: 'stale',
        },
        {
            label: 'denied capabilities',
            status: 403,
            body: {
                error: {
                    code: 'capability_denied',
                    message: 'Not allowed for this operator.',
                },
            },
            errorType: OmrWorkbenchDeniedError,
            kind: 'denied',
        },
        {
            label: 'expired continuations',
            status: 410,
            body: { code: 'continuation_expired' },
            errorType: OmrWorkbenchExpiredError,
            kind: 'expired',
        },
        {
            label: 'invalid requests',
            status: 422,
            body: {
                code: 'payload_invalid',
                message: 'The payload is invalid.',
            },
            errorType: OmrWorkbenchInvalidError,
            kind: 'invalid',
        },
        {
            label: 'concurrent conflicts',
            status: 409,
            body: { code: 'version_mismatch', message: 'Try again.' },
            errorType: OmrWorkbenchConflictError,
            kind: 'conflict',
        },
        {
            label: 'server-side analysis failures',
            status: 500,
            body: {
                code: 'analysis_failed',
                message: 'The capture could not be analyzed.',
                details: {
                    stage: 'processing',
                    recovery: 'Check the run status before retrying.',
                    support_code: 'OMR-42',
                    internal_path: '/private/provider/output',
                },
            },
            errorType: OmrWorkbenchInfrastructureError,
            kind: 'infrastructure',
        },
    ] as const)(
        '$label map to typed client errors',
        async ({ status, body, errorType, kind }) => {
            const client = createOmrWorkbenchClient({
                workbenchId: 'production:7:9',
                executionKind: 'production',
                urls: { show: '/grading/omr/show' },
                fetchImpl: async () =>
                    new Response(JSON.stringify(body), {
                        status,
                        headers: { 'Content-Type': 'application/json' },
                    }),
            });

            let failure: unknown = null;
            try {
                await client.load();
            } catch (error) {
                failure = error;
            }

            expect(failure).toBeInstanceOf(errorType);
            expect(isOmrWorkbenchClientError(failure)).toBe(true);
            if (isOmrWorkbenchClientError(failure)) {
                expect(failure.kind).toBe(kind);
                expect(failure.status).toBe(status);
                if (kind === 'infrastructure') {
                    expect(failure.message).toBe('The capture could not be analyzed.');
                    expect(failure.details).toEqual({
                        stage: 'processing',
                        recovery: 'Check the run status before retrying.',
                        support_code: 'OMR-42',
                    });
                }
            }
        },
    );
});

describe('shared OMR workbench presentation contract', () => {
    it('navigates compact Camera through Queue and Review without replacing the camera', async () => {
        vi.stubGlobal('matchMedia', vi.fn((media: string) => Object.assign(new EventTarget(), {
            media,
            matches: true,
            onchange: null,
            addListener: () => {},
            removeListener: () => {},
        })));
        const view = render(OmrWorkbench, {
            projection: projection('diagnostic'),
            cameraDeckProps: cameraDeckProps(async (_frame, operation) => captureReceipt(operation)),
        });
        const video = view.container.querySelector('video');
        expect(view.queryByRole('listbox', { name: 'OMR sheet queue' })).toBeNull();

        await fireEvent.click(view.getByRole('button', { name: 'Open scans gallery' }));
        expect(view.queryByRole('region', { name: 'OMR capture' })).toBeNull();
        expect(view.queryByRole('dialog')).toBeNull();
        const queue = view.getByRole('listbox', { name: 'OMR sheet queue' });
        await fireEvent.click(queue.querySelector('button')!);
        expect(view.queryByRole('listbox', { name: 'OMR sheet queue' })).toBeNull();
        expect(view.getByRole('button', { name: 'Back to Scans queue' })).toBeTruthy();

        await fireEvent.click(view.getByRole('button', { name: 'Back to Scans queue' }));
        expect(view.getByRole('listbox', { name: 'OMR sheet queue' })).toBeTruthy();
        await fireEvent.click(view.getByRole('button', { name: 'Back to Camera' }));
        expect(view.getByRole('region', { name: 'OMR capture' })).toBeTruthy();
        expect(view.queryByRole('listbox', { name: 'OMR sheet queue' })).toBeNull();
        expect(view.container.querySelector('video')).toBe(video);
    });

    it('keeps compact Camera active when its navigation guard refuses Queue', async () => {
        vi.stubGlobal('matchMedia', vi.fn((media: string) => Object.assign(new EventTarget(), {
            media,
            matches: true,
            onchange: null,
            addListener: () => {},
            removeListener: () => {},
        })));
        const view = render(OmrWorkbench, {
            projection: projection('diagnostic'),
            cameraDeckProps: cameraDeckProps(async (_frame, operation) => captureReceipt(operation)),
            onDirtyNavigation: () => false,
        });

        await fireEvent.click(view.getByRole('button', { name: 'Open scans gallery' }));
        expect(view.getByRole('region', { name: 'OMR capture' })).toBeTruthy();
        expect(view.queryByRole('listbox', { name: 'OMR sheet queue' })).toBeNull();
    });

    it('shows the diagnostic no-write banner and uses only projected completion commands', async () => {
        const onComplete = vi.fn().mockResolvedValue(undefined);
        const view = render(OmrWorkbench, {
            projection: projection('diagnostic', {
                commands: ['diagnostic_complete'],
            }),
            onComplete,
        });

        expect(
            view.getByText('Diagnostic projection · no production writes'),
        ).not.toBeNull();
        expect(
            view.getByText(
                /isolated from production captures, page claims, scores/,
            ),
        ).not.toBeNull();
        expect(
            view.getByRole('button', { name: 'Record diagnostic outcome' }),
        ).not.toBeNull();
        expect(
            view.queryByRole('button', {
                name: 'Confirm production completion',
            }),
        ).toBeNull();

        await fireEvent.click(
            view.getByRole('button', { name: 'Record diagnostic outcome' }),
        );
        expect(onComplete).toHaveBeenCalledWith('diagnostic_complete');
    });

    it('keeps completion unavailable until the matching production command is projected', async () => {
        const onComplete = vi.fn().mockResolvedValue(undefined);
        const view = render(OmrWorkbench, {
            projection: projection('production', { commands: [] }),
            onComplete,
        });

        expect(view.getByText('Review required')).not.toBeNull();
        expect(
            view.queryByRole('button', {
                name: 'Confirm production completion',
            }),
        ).toBeNull();

        await view.rerender({
            projection: projection('production', {
                revision: 2,
                commands: ['production_complete'],
            }),
            onComplete,
        });

        const completeButton = view.getByRole('button', {
            name: 'Confirm production completion',
        });
        expect(completeButton).not.toBeNull();
        await fireEvent.click(completeButton);
        expect(onComplete).toHaveBeenCalledWith('production_complete');
        expect(
            view.queryByText('Diagnostic projection · no production writes'),
        ).toBeNull();
    });

    it('keeps one evidence review surface while moving between embedded and focus presentations', async () => {
        const offsetParentDescriptor = Object.getOwnPropertyDescriptor(
            HTMLElement.prototype,
            'offsetParent',
        );
        Object.defineProperty(HTMLElement.prototype, 'offsetParent', {
            configurable: true,
            get: () => document.body,
        });

        try {
            Object.defineProperty(window, 'innerWidth', {
                configurable: true,
                writable: true,
                value: 1280,
            });
            const onRetry = vi.fn();
            const view = render(OmrWorkbench, {
                projection: projection('production', { commands: [] }),
                onRetry,
                initialStageView: 'review',
            });

            expect(
                view.getAllByRole('region', { name: 'OMR evidence review' }),
            ).toHaveLength(1);
            expect(
                view.queryByRole('dialog', {
                    name: 'Focused OMR evidence review',
                }),
            ).toBeNull();

            const opener = view.getByRole('button', {
                name: 'Focus evidence',
            });
            opener.focus();
            await fireEvent.click(opener);
            const dialog = view.getByRole('dialog', {
                name: 'Focused OMR evidence review',
            });
            expect(dialog.getAttribute('aria-modal')).toBe('true');
            expect(
                view.getAllByRole('dialog', {
                    name: 'Focused OMR evidence review',
                }),
            ).toHaveLength(1);
            expect(
                view.queryByRole('region', { name: 'OMR evidence review' }),
            ).toBeNull();
            await waitFor(() => expect(document.activeElement).toBe(dialog));

            const retry = dialog.querySelector<HTMLButtonElement>(
                'button:not([disabled])',
            );
            expect(retry).not.toBeNull();

            await fireEvent.keyDown(window, {
                key: 'Tab',
                shiftKey: true,
            });
            expect(document.activeElement).toBe(retry);
            await fireEvent.keyDown(window, { key: 'Tab' });
            expect(document.activeElement).toBe(retry);

            opener.focus();
            await fireEvent.keyDown(window, { key: 'Tab' });
            expect(document.activeElement).toBe(retry);

            retry?.focus();
            await fireEvent.keyDown(window, { key: 'Tab' });
            expect(document.activeElement).toBe(retry);
            await fireEvent.keyDown(window, {
                key: 'Tab',
                shiftKey: true,
            });
            expect(document.activeElement).toBe(retry);

            await fireEvent.keyDown(window, { key: 'Escape' });
            await waitFor(() => {
                expect(
                    view.queryByRole('dialog', {
                        name: 'Focused OMR evidence review',
                    }),
                ).toBeNull();
                expect(document.activeElement).toBe(opener);
            });
            expect(
                view.getAllByRole('region', { name: 'OMR evidence review' }),
            ).toHaveLength(1);
        } finally {
            if (offsetParentDescriptor) {
                Object.defineProperty(
                    HTMLElement.prototype,
                    'offsetParent',
                    offsetParentDescriptor,
                );
            } else {
                Reflect.deleteProperty(HTMLElement.prototype, 'offsetParent');
            }
        }
    });
});

const RECOVERY_CATALOG_ENTRIES = [
    {
        code: 'session_unresolved',
        title: 'Session not found',
        explanation:
            'No scannable submitted session matches this Applicant Number.',
        owner: 'Scoring staff',
        primary_action: 'retry_scan',
        secondary_action: null,
    },
    {
        code: 'session_ambiguous',
        title: 'Choose the session',
        explanation:
            'More than one eligible submitted session matches this Applicant Number.',
        owner: 'Scoring staff',
        primary_action: 'select_session',
        secondary_action: 'retry_scan',
    },
    {
        code: 'session_missing',
        title: 'Session unavailable',
        explanation: 'The scanner session is no longer available.',
        owner: 'Scoring staff',
        primary_action: 'return_to_grading',
        secondary_action: null,
    },
    {
        code: 'applicant_unresolved',
        title: 'Applicant Number not resolved',
        explanation:
            'Retry the scan or match an eligible assignment from this session.',
        owner: 'Scoring staff',
        primary_action: 'retry_scan',
        secondary_action: 'match_applicant',
    },
    {
        code: 'applicant_conflict',
        title: 'Applicant Number conflict',
        explanation:
            'More than one eligible assignment matches; staff must choose from the session roster.',
        owner: 'Scoring staff',
        primary_action: 'match_applicant',
        secondary_action: 'retry_scan',
    },
    {
        code: 'applicant_duplicate',
        title: 'Possible duplicate scan',
        explanation:
            'Another capture in this batch already uses this applicant.',
        owner: 'Scoring staff',
        primary_action: 'compare_duplicates',
        secondary_action: null,
    },
    {
        code: 'submission_missing',
        title: 'Submission not recorded',
        explanation:
            'Record the submission in Exam Monitoring, then return to this scan.',
        owner: 'Exam proctor',
        primary_action: 'open_monitoring',
        secondary_action: null,
    },
    {
        code: 'seat_missing',
        title: 'Frozen seat unavailable',
        explanation:
            'The submission does not have a valid frozen session-seat allocation.',
        owner: 'Exam setup staff',
        primary_action: 'open_exam_setup',
        secondary_action: null,
    },
    {
        code: 'seat_snapshot_missing',
        title: 'Frozen session snapshot unavailable',
        explanation:
            'The submitted seat is not part of this exam session snapshot.',
        owner: 'Exam setup staff',
        primary_action: 'open_exam_setup',
        secondary_action: null,
    },
    {
        code: 'key_missing',
        title: 'Answer key unavailable',
        explanation: 'The frozen booklet Set has no immutable answer key.',
        owner: 'Assessment setup staff',
        primary_action: 'open_sheet_builder',
        secondary_action: null,
    },
    {
        code: 'observed_set_mismatch',
        title: 'Observed Set conflict',
        explanation:
            'The visible Set differs from the submission-frozen seat; scoring remains blocked.',
        owner: 'Assessment setup staff',
        primary_action: 'review_material_assignment',
        secondary_action: null,
    },
    {
        code: 'qr_missing',
        title: 'QR not readable',
        explanation:
            'Rescan first; if the code is still unreadable, use the printed display code.',
        owner: 'Scoring staff',
        primary_action: 'retry_scan',
        secondary_action: 'enter_display_code',
    },
    {
        code: 'qr_reader_unavailable',
        title: 'QR reader unavailable',
        explanation:
            'The QR reader is unavailable. Retry this same capture or use the printed display code.',
        owner: 'Scoring staff',
        primary_action: 'retry_scan',
        secondary_action: 'enter_display_code',
    },
    {
        code: 'qr_reader_response_invalid',
        title: 'QR reader response invalid',
        explanation:
            'The QR reader returned an invalid response. Retry this same capture or use the printed display code.',
        owner: 'Scoring staff',
        primary_action: 'retry_scan',
        secondary_action: 'enter_display_code',
    },
    {
        code: 'multi_page_capture',
        title: 'Multi-page capture required',
        explanation:
            'Capture the complete page set from the selected Exam Monitoring session.',
        owner: 'Scoring staff',
        primary_action: 'retry_scan',
        secondary_action: null,
    },
    {
        code: 'analysis_failed',
        title: 'Scan analysis failed',
        explanation:
            'The capture was preserved. Retry the same image or send it for manual review.',
        owner: 'Scoring staff',
        primary_action: 'retry_scan',
        secondary_action: 'match_applicant',
    },
    {
        code: 'analysis_evidence_invalid',
        title: 'Scan evidence unavailable',
        explanation:
            'The capture was preserved but its machine evidence could not be stored safely.',
        owner: 'Scoring support',
        primary_action: 'retry_scan',
        secondary_action: null,
    },
    {
        code: 'qr_invalid',
        title: 'QR signature invalid',
        explanation:
            'This sheet cannot be bypassed with a display code; quarantine it and rescan.',
        owner: 'Scoring staff',
        primary_action: 'quarantine_scan',
        secondary_action: 'retry_scan',
    },
    {
        code: 'qr_instance_missing',
        title: 'Answer Sheet not registered',
        explanation: 'The signed Answer Sheet instance is not registered.',
        owner: 'Assessment setup staff',
        primary_action: 'quarantine_scan',
        secondary_action: null,
    },
    {
        code: 'qr_provenance_conflict',
        title: 'Answer Sheet provenance conflict',
        explanation:
            'The signed QR conflicts with the registered Answer Sheet instance.',
        owner: 'Scoring staff',
        primary_action: 'quarantine_scan',
        secondary_action: null,
    },
    {
        code: 'qr_scope_mismatch',
        title: 'Answer Sheet scope mismatch',
        explanation:
            'The issued Answer Sheet belongs to a different institution or academic cycle than the selected session.',
        owner: 'Assessment setup staff',
        primary_action: 'quarantine_scan',
        secondary_action: null,
    },
    {
        code: 'qr_duplicate_page',
        title: 'Answer Sheet page already captured',
        explanation: 'This issued page is already attached to another capture.',
        owner: 'Scoring staff',
        primary_action: 'compare_duplicates',
        secondary_action: null,
    },
    {
        code: 'qr_lifecycle_invalid',
        title: 'Answer Sheet no longer valid',
        explanation: 'This Answer Sheet was voided or superseded.',
        owner: 'Assessment setup staff',
        primary_action: 'quarantine_scan',
        secondary_action: null,
    },
    {
        code: 'qr_page_invalid',
        title: 'Answer Sheet page mismatch',
        explanation:
            'The signed page does not match the issued Answer Sheet page set.',
        owner: 'Scoring staff',
        primary_action: 'retry_scan',
        secondary_action: null,
    },
    {
        code: 'template_mismatch',
        title: 'Template mismatch',
        explanation:
            'The Answer Sheet template does not match the active batch.',
        owner: 'Assessment setup staff',
        primary_action: 'open_sheet_builder',
        secondary_action: null,
    },
] as const;

describe('OMR workbench recovery cards catalog contract', () => {
    it.each(RECOVERY_CATALOG_ENTRIES)(
        'renders recovery card for $code with title, explanation, owner, blocked badge, and actions',
        async ({
            code,
            title,
            explanation,
            owner,
            primary_action,
            secondary_action,
        }) => {
            const onAction = vi.fn().mockResolvedValue(undefined);
            const supportCode = `REC-${code.toUpperCase().replace(/[^A-Z0-9]/g, '-')}`;
            const recovery: OmrWorkbenchRecoveryItem = {
                code,
                title,
                explanation,
                blocked: true,
                owner,
                support_code: supportCode,
                primary_action: primary_action as OmrWorkbenchRecoveryAction,
                secondary_action: (secondary_action ?? undefined) as
                    OmrWorkbenchRecoveryAction | undefined,
                technical_detail: {
                    failure_code: code,
                    retry_eligible: primary_action === 'retry_scan',
                },
            };

            const view = render(OmrRecoveryCard, {
                recovery,
                onAction,
                idPrefix: `test-${code}`,
            });

            const article = view.getByRole('alert');
            expect(article).not.toBeNull();
            expect(article.getAttribute('data-omr-recovery-blocked')).toBe(
                'true',
            );

            const titleHeading = view.getByRole('heading', {
                level: 3,
                name: title,
            });
            expect(titleHeading).not.toBeNull();
            expect(view.getByText(explanation)).not.toBeNull();

            const badge = view.getByText('Scoring blocked');
            expect(badge).not.toBeNull();
            expect(badge.getAttribute('role')).toBe('status');
            expect(badge.getAttribute('aria-live')).toBe('polite');

            expect(view.getByText('Responsible owner')).not.toBeNull();
            expect(view.getByTitle(owner).textContent).toContain(owner);

            expect(view.getByText('Support code')).not.toBeNull();
            expect(view.getByTitle(supportCode).textContent).toContain(
                supportCode,
            );

            const primaryLabelPattern = new RegExp(
                primary_action.replace(/_/g, ' '),
                'i',
            );
            const primaryButton = view.getByRole('button', {
                name: primaryLabelPattern,
            });
            expect(primaryButton).not.toBeNull();
            await fireEvent.click(primaryButton);
            expect(onAction).toHaveBeenCalledWith(primary_action, recovery);

            if (secondary_action) {
                const secondaryLabelPattern = new RegExp(
                    secondary_action.replace(/_/g, ' '),
                    'i',
                );
                const secondaryButton = view.getByRole('button', {
                    name: secondaryLabelPattern,
                });
                expect(secondaryButton).not.toBeNull();
                await fireEvent.click(secondaryButton);
                expect(onAction).toHaveBeenCalledWith(
                    secondary_action,
                    recovery,
                );
            } else {
                const actionButtons = view.container.querySelectorAll(
                    '[data-omr-recovery-actions] button',
                );
                expect(actionButtons).toHaveLength(1);
            }

            const techDetails = view.container.querySelector(
                '[data-omr-recovery-technical]',
            );
            expect(techDetails).not.toBeNull();
            expect(techDetails?.textContent).toContain('Technical details');
            expect(techDetails?.textContent).toContain(code);
        },
    );

    it('copies support code to clipboard and indicates copied status', async () => {
        const writeText = vi.fn().mockResolvedValue(undefined);
        Object.defineProperty(navigator, 'clipboard', {
            configurable: true,
            writable: true,
            value: { writeText },
        });

        const recovery: OmrWorkbenchRecoveryItem = {
            code: 'submission_missing',
            title: 'Submission not recorded',
            explanation:
                'Record the submission in Exam Monitoring, then return to this scan.',
            blocked: true,
            owner: 'Exam proctor',
            support_code: 'ERR-REC-SUB-042',
            primary_action: 'open_monitoring',
        };

        const view = render(OmrRecoveryCard, {
            recovery,
            onAction: vi.fn(),
            idPrefix: 'test-clipboard',
        });

        const copyButton = view.getByRole('button', {
            name: 'Copy support code ERR-REC-SUB-042',
        });
        expect(copyButton).not.toBeNull();
        await fireEvent.click(copyButton);

        expect(writeText).toHaveBeenCalledWith('ERR-REC-SUB-042');
        await waitFor(() => {
            expect(copyButton.textContent).toContain('Copied');
        });
    });

    it('displays error feedback when recovery action invocation rejects', async () => {
        const onAction = vi
            .fn()
            .mockRejectedValue(new Error('Authority service timeout'));
        const recovery: OmrWorkbenchRecoveryItem = {
            code: 'analysis_failed',
            title: 'Scan analysis failed',
            explanation: 'The capture was preserved. Retry the same image.',
            blocked: true,
            owner: 'Scoring staff',
            support_code: 'ERR-REC-FAIL-1',
            primary_action: 'retry_scan',
        };

        const view = render(OmrRecoveryCard, {
            recovery,
            onAction,
            idPrefix: 'test-error',
        });

        const button = view.getByRole('button', { name: /retry scan/i });
        await fireEvent.click(button);

        await waitFor(() => {
            expect(view.getByText('Authority service timeout')).not.toBeNull();
        });
    });

    it('gates command-backed recovery actions against projected commands', async () => {
        const onRecoveryAction = vi.fn().mockResolvedValue(undefined);
        const recovery: OmrWorkbenchRecoveryItem = {
            code: 'applicant_duplicate',
            title: 'Possible duplicate scan',
            explanation:
                'Another capture in this batch already uses this applicant.',
            blocked: true,
            owner: 'Scoring staff',
            support_code: 'ERR-REC-DUP-01',
            primary_action: 'resolve_duplicate',
        };

        const view = render(OmrWorkbench, {
            projection: projection('production', {
                commands: ['capture_start', 'capture_retry'],
                recovery: {
                    items: [recovery],
                    needs_attention: [{ owner: 'Scoring staff', count: 1 }],
                    count: 1,
                    outcome: null,
                },
            }),
            onRecoveryAction,
        });

        const resolveBtn = view.getByRole('button', {
            name: /resolve duplicate/i,
        });
        expect(resolveBtn).not.toBeNull();
        await fireEvent.click(resolveBtn);
        expect(onRecoveryAction).not.toHaveBeenCalled();

        await view.rerender({
            projection: projection('production', {
                revision: 2,
                commands: [
                    'capture_start',
                    'capture_retry',
                    'resolve_duplicate',
                ],
                recovery: {
                    items: [recovery],
                    needs_attention: [{ owner: 'Scoring staff', count: 1 }],
                    count: 1,
                    outcome: null,
                },
            }),
            onRecoveryAction,
        });

        const enabledBtn = view.getByRole('button', {
            name: /resolve duplicate/i,
        });
        await fireEvent.click(enabledBtn);
        expect(onRecoveryAction).toHaveBeenCalledWith(
            'resolve_duplicate',
            expect.objectContaining({ code: 'applicant_duplicate' }),
        );
    });
});

describe('OMR destructive control availability', () => {
    it('does not advertise capture removal when the host supplies no authorized handler', async () => {
        const view = render(OmrWorkbench, {
            projection: projection('production', {
                sheets: [{ id: 42, client_capture_id: 'failed-page', status: 'failed', version: 1, stage: 'recovery', pages: [] }],
            }),
        });
        await fireEvent.click(view.getByRole('button', { name: /Batch Queue/ }));
        await view.findByRole('dialog', { name: /Batch Progress/ });
        expect(view.queryByRole('button', { name: 'Remove capture #42' })).toBeNull();
    });
    it('does not advertise retaking a historical row without a current server grant', async () => {
        const view = render(OmrWorkbench, {
            projection: projection('diagnostic', {
                sheets: [{ id: 42, client_capture_id: 'historical-page', status: 'failed', version: 1, stage: 'recovery', pages: [] }],
            }),
            onRetakeSheet: vi.fn(),
            initialStageView: 'review',
        });
        expect(view.queryByRole('button', { name: 'Retake page', exact: true })).toBeNull();
        await fireEvent.click(view.getByRole('tab', { name: 'Camera', exact: true }));
        await fireEvent.click(view.getByRole('button', { name: /Batch Queue/ }));
        await view.findByRole('dialog', { name: /Batch Progress/ });
        expect(view.queryByRole('button', { name: 'Retake page', exact: true })).toBeNull();
    });
});

describe('OMR diagnostic evidence reaches the shared rendered Review', () => {
    it('renders mapped evidence and lets zoom and Fit full sheet change the visible viewport', async () => {
        const alignedAssetId = 82;
        const capture = {
            id: 8,
            capture_id: 8,
            version: 1,
            status: 'needs_review',
            assets: {
                aligned: { id: alignedAssetId, hash: 'b'.repeat(64) },
            },
        };
        const captureEvidence = {
            page_no: 1,
            evidence_hash: 'c'.repeat(64),
            assets: {
                aligned: {
                    mime: 'image/png',
                    width: 2480,
                    height: 3508,
                    sha256: 'b'.repeat(64),
                },
            },
            items: [
                {
                    position: 1,
                    area_code: 'VOCABULARY',
                    question_no: 1,
                    native_label: 'VOCABULARY_Q_1',
                    page_no: 1,
                    region: {
                        x: 0.08548387096774193,
                        y: 0.3617445838084379,
                        w: 0.1717741935483871,
                        h: 0.009407069555302128,
                    },
                    options: [
                        { code: 'A-B', region: { x: 0.08548387096774193, y: 0.3617445838084379, w: 0.012903225806451613, h: 0.009122006841505131 }, mean: 216.5 },
                        { code: 'A-C', region: { x: 0.11733870967741936, y: 0.3617445838084379, w: 0.012903225806451613, h: 0.009122006841505131 }, mean: 230.5 },
                        { code: 'A-D', region: { x: 0.1495967741935484, y: 0.3620296465222349, w: 0.012903225806451613, h: 0.009122006841505131 }, mean: 227.7 },
                        { code: 'B-C', region: { x: 0.18185483870967742, y: 0.3620296465222349, w: 0.012903225806451613, h: 0.009122006841505131 }, mean: 224.6 },
                        { code: 'B-D', region: { x: 0.21330645161290324, y: 0.3620296465222349, w: 0.012903225806451613, h: 0.009122006841505131 }, mean: 225.1 },
                        { code: 'C-D', region: { x: 0.24435483870967742, y: 0.3620296465222349, w: 0.012903225806451613, h: 0.009122006841505131 }, mean: 221 },
                    ],
                    detected: {
                        state: 'unreadable',
                        selected_option: null,
                        confidence: null,
                        observed_options: [],
                        option_observations: [],
                    },
                },
            ],
        };
        const evidencePage = mapDiagnosticEvidencePage({
            capture,
            captureEvidence,
            items: captureEvidence.items,
            assetUrls: {
                aligned: `/setup/exam-materials/omr-test/runs/private-run/assets/${alignedAssetId}`,
            },
            reviewable: true,
        });
        const view = render(OmrWorkbench, {
            projection: projection('diagnostic', {
                sheets: [{
                    id: 8,
                    client_capture_id: 'capture-8',
                    status: 'needs_review',
                    version: 1,
                    stage: 'review',
                    pages: [{ page_no: 1, status: 'captured', captured: true }],
                }],
            }),
            evidencePage,
            initialStageView: 'review',
        });

        expect(view.queryByText(/mapped sheet geometry is missing/i)).toBeNull();
        expect(view.getByRole('img', { name: 'Machine-marked answer sheet' })).toBeTruthy();
        expect(view.container.querySelector('svg image')?.getAttribute('href')).toBe(
            `/setup/exam-materials/omr-test/runs/private-run/assets/${alignedAssetId}`,
        );
        expect(view.container.querySelector('[data-sheet-item="VOCABULARY_Q_1"]')).not.toBeNull();
        const sheet = view.getByRole('img', { name: 'Machine-marked answer sheet' });
        expect(sheet.getAttribute('viewBox')).toBe('0 0 2480 3508');
        await fireEvent.click(view.getByRole('button', { name: 'Zoom in', exact: true }));
        const zoomed = (sheet.getAttribute('viewBox') ?? '').split(' ').map(Number);
        expect(zoomed[0]).toBeGreaterThan(0);
        expect(zoomed[1]).toBeGreaterThan(0);
        expect(zoomed[2]).toBeLessThan(2480);
        expect(zoomed[3]).toBeLessThan(3508);
        await fireEvent.click(view.getByRole('button', { name: 'Fit full sheet', exact: true }));
        expect(sheet.getAttribute('viewBox')).toBe('0 0 2480 3508');
    });
});

describe('OMR workbench keyboard focus and dialog trap contract', () => {
    it('opens focus review on keyboard click, traps tab, and restores focus on Escape', async () => {
        const offsetParentDescriptor = Object.getOwnPropertyDescriptor(
            HTMLElement.prototype,
            'offsetParent',
        );
        Object.defineProperty(HTMLElement.prototype, 'offsetParent', {
            configurable: true,
            get: () => document.body,
        });

        try {
            Object.defineProperty(window, 'innerWidth', {
                configurable: true,
                writable: true,
                value: 1280,
            });

            const realisticEvidencePage = {
                method: 'pre_shaded',
                capture_id: 42,
                area: 'all',
                total: 50,
                mismatch_total: 1,
                page: 1,
                per_page: 25,
                page_count: 2,
                filter: 'all',
                evidence_version: 'v1',
                evidence_hash: 'e'.repeat(64),
                attempt_no: 2,
                capture_page_no: 1,
                groups: [
                    {
                        key: 'all',
                        label: 'All areas',
                        group_type: 'all',
                        total: 50,
                        mismatch_total: 1,
                        page_count: 2,
                    },
                ],
                items: [
                    {
                        item_key: 'PART_1_Q_001',
                        question_no: 1,
                        state: 'mismatch',
                        detected_value: 'B',
                        mapped_value: 'A',
                    },
                ],
            };

            const view = render(OmrWorkbench, {
                projection: projection('production', { commands: [] }),
                evidencePage: realisticEvidencePage,
                initialStageView: 'review',
            });

            const opener = view.getByRole('button', {
                name: 'Focus evidence',
            });
            opener.focus();
            expect(document.activeElement).toBe(opener);

            await fireEvent.click(opener);

            const dialog = view.getByRole('dialog', {
                name: 'Focused OMR evidence review',
            });
            expect(dialog).not.toBeNull();
            expect(dialog.getAttribute('aria-modal')).toBe('true');
            await waitFor(() => expect(document.activeElement).toBe(dialog));

            const interactive = dialog.querySelectorAll<HTMLElement>(
                'button:not([disabled]), [tabindex]:not([tabindex="-1"])',
            );
            expect(interactive.length).toBeGreaterThan(0);

            await fireEvent.keyDown(window, {
                key: 'Tab',
                shiftKey: true,
            });
            expect(dialog.contains(document.activeElement)).toBe(true);

            await fireEvent.keyDown(window, { key: 'Tab' });
            expect(dialog.contains(document.activeElement)).toBe(true);

            await fireEvent.keyDown(window, { key: 'Escape' });
            await waitFor(() => {
                expect(
                    view.queryByRole('dialog', {
                        name: 'Focused OMR evidence review',
                    }),
                ).toBeNull();
                expect(document.activeElement).toBe(opener);
            });
        } finally {
            if (offsetParentDescriptor) {
                Object.defineProperty(
                    HTMLElement.prototype,
                    'offsetParent',
                    offsetParentDescriptor,
                );
            } else {
                Reflect.deleteProperty(HTMLElement.prototype, 'offsetParent');
            }
        }
    });

    it('restores focus to opener when closed via close focus button', async () => {
        const offsetParentDescriptor = Object.getOwnPropertyDescriptor(
            HTMLElement.prototype,
            'offsetParent',
        );
        Object.defineProperty(HTMLElement.prototype, 'offsetParent', {
            configurable: true,
            get: () => document.body,
        });

        try {
            Object.defineProperty(window, 'innerWidth', {
                configurable: true,
                writable: true,
                value: 1280,
            });

            const view = render(OmrWorkbench, {
                projection: projection('production', { commands: [] }),
                initialStageView: 'review',
            });

            const opener = view.getByRole('button', {
                name: 'Focus evidence',
            });
            opener.focus();
            await fireEvent.click(opener);

            expect(
                view.getByRole('dialog', {
                    name: 'Focused OMR evidence review',
                }),
            ).not.toBeNull();

            const closeBtn = view.getByRole('button', {
                name: 'Close focus view',
            });
            expect(closeBtn).not.toBeNull();
            await fireEvent.click(closeBtn);

            await waitFor(() => {
                expect(
                    view.queryByRole('dialog', {
                        name: 'Focused OMR evidence review',
                    }),
                ).toBeNull();
                expect(document.activeElement).toBe(opener);
            });
        } finally {
            if (offsetParentDescriptor) {
                Object.defineProperty(
                    HTMLElement.prototype,
                    'offsetParent',
                    offsetParentDescriptor,
                );
            } else {
                Reflect.deleteProperty(HTMLElement.prototype, 'offsetParent');
            }
        }
    });

    it('opens review and selects the requested sheet', async () => {
        const onSelectSheet = vi.fn();
        const view = render(OmrWorkbench, {
            projection: projection('production', {
                sheets: [
                    {
                        id: 10,
                        client_capture_id: 'cap-10',
                        status: 'ready',
                        version: 1,
                        stage: 'review',
                        pages: [],
                    },
                    {
                        id: 20,
                        client_capture_id: 'cap-20',
                        status: 'needs_review',
                        version: 1,
                        stage: 'review',
                        pages: [],
                    },
                    {
                        id: 30,
                        client_capture_id: 'cap-30',
                        status: 'ready',
                        version: 1,
                        stage: 'review',
                        pages: [],
                    },
                ],
            }),
            onSelectSheet,
        });

        const queue = view.getByRole('listbox', {
            name: 'OMR sheet queue',
        });
        expect(queue).not.toBeNull();

        const options = view.getAllByRole('option');
        expect(options).toHaveLength(3);

        expect(options[0].getAttribute('aria-selected')).toBe('true');
        expect(options[1].getAttribute('aria-selected')).toBe('false');
        expect(options[2].getAttribute('aria-selected')).toBe('false');

        await fireEvent.click(options[1]);
        expect(onSelectSheet).toHaveBeenCalledWith(
            expect.objectContaining({ id: 20 }),
        );

        expect(view.getByRole('tab', { name: /#20/ })).not.toBeNull();
    });

    it('filters scans by status and opens the next needs-review sheet', async () => {
        const onSelectSheet = vi.fn();
        const view = render(OmrWorkbench, {
            projection: projection('production', {
                sheets: [
                    {
                        id: 10,
                        client_capture_id: 'cap-10',
                        status: 'ready',
                        version: 1,
                        stage: 'review',
                        pages: [],
                    },
                    {
                        id: 20,
                        client_capture_id: 'cap-20',
                        status: 'needs_review',
                        version: 1,
                        stage: 'review',
                        pages: [],
                    },
                    {
                        id: 30,
                        client_capture_id: 'cap-30',
                        status: 'reviewed',
                        version: 1,
                        stage: 'review',
                        pages: [],
                    },
                ],
            }),
            onSelectSheet,
        });

        const queue = view.getByRole('listbox', {
            name: 'OMR sheet queue',
        });
        expect(queue.querySelectorAll('[data-omr-sheet-option]')).toHaveLength(3);

        await fireEvent.click(
            view.getByRole('button', { name: /Needs Review/ }),
        );
        expect(queue.querySelectorAll('[data-omr-sheet-option]')).toHaveLength(1);
        expect(
            queue.querySelector('[data-omr-sheet-id="20"]'),
        ).not.toBeNull();

        await fireEvent.click(view.getByRole('button', { name: /Ready/ }));
        expect(queue.querySelectorAll('[data-omr-sheet-option]')).toHaveLength(2);

        await fireEvent.click(
            view.getByRole('button', { name: /Next needs review/ }),
        );
        expect(onSelectSheet).toHaveBeenCalledWith(
            expect.objectContaining({ id: 20 }),
        );
    });

    it('notifies the host and keeps sheet review counts consistent when next-review is already selected', async () => {
        const onSelectSheet = vi.fn();
        const sheets = Array.from({ length: 6 }, (_, index) => ({
            id: index + 1,
            client_capture_id: `cap-${index + 1}`,
            status: index === 0 ? 'needs_review' : 'ready',
            version: 1,
            stage: 'review',
            pages: [],
        }));
        const view = render(OmrWorkbench, {
            projection: projection('production', {
                sheets,
                recovery: {
                    items: [],
                    needs_attention: [{ owner: 'Scoring staff', count: 6 }],
                    count: 6,
                    outcome: null,
                },
            }),
            selectedSheetId: 1,
            onSelectSheet,
        });

        expect(view.getByText('6 Scanned · 1 Needs Check')).toBeTruthy();
        expect(
            view.getByRole('button', { name: /Needs Review 1/ }),
        ).toBeTruthy();

        await fireEvent.click(
            view.getByRole('button', { name: /Next needs review/ }),
        );

        expect(onSelectSheet).toHaveBeenCalledTimes(1);
        expect(onSelectSheet).toHaveBeenCalledWith(
            expect.objectContaining({ id: 1 }),
        );
        expect(
            view.container.querySelector('[data-omr-stage-view="review"]'),
        ).not.toBeNull();
    });
});

describe('OMR completion readiness presentation', () => {
    it('shows review required instead of ready when no completion command is enabled', () => {
        const view = render(OmrCompletionBar, {
            projection: projection('production', { commands: [] }),
        });

        expect(
            view.container.querySelector('[data-omr-completion-status]')
                ?.textContent?.trim(),
        ).toBe('Review required');
        expect(
            view.container.querySelector('[data-omr-completion-command]'),
        ).toBeNull();
    });

    it('allows projected completion commands despite historical review sheets', () => {
        const view = render(OmrCompletionBar, {
            projection: projection('production', {
                sheets: [
                    {
                        id: 1,
                        client_capture_id: 'capture-1',
                        status: 'needs_review',
                        version: 1,
                        stage: 'review',
                        pages: [],
                    },
                ],
            }),
            onComplete: vi.fn(),
        });

        expect(
            view.container.querySelector('[data-omr-completion-status]')
                ?.textContent?.trim(),
        ).toBe('Ready');
        expect(
            view.container.querySelector('[data-omr-completion-command]'),
        ).not.toBeNull();
    });
});

describe('OMR workbench side-by-side duplicate disposition contract', () => {
    it('invokes compare_duplicates recovery action from duplicate recovery card', async () => {
        const onRecoveryAction = vi.fn().mockResolvedValue(undefined);
        const duplicateItem: OmrWorkbenchRecoveryItem = {
            code: 'applicant_duplicate',
            title: 'Possible duplicate scan',
            explanation:
                'Another capture in this batch already uses this applicant.',
            blocked: true,
            owner: 'Scoring staff',
            support_code: 'REC-DUP-01',
            primary_action: 'compare_duplicates',
        };

        const view = render(OmrWorkbench, {
            projection: projection('production', {
                recovery: {
                    items: [duplicateItem],
                    needs_attention: [{ owner: 'Scoring staff', count: 1 }],
                    count: 1,
                    outcome: null,
                },
            }),
            onRecoveryAction,
        });

        const compareBtn = view.getByRole('button', {
            name: /compare duplicates/i,
        });
        expect(compareBtn).not.toBeNull();
        await fireEvent.click(compareBtn);
        expect(onRecoveryAction).toHaveBeenCalledWith(
            'compare_duplicates',
            duplicateItem,
        );
    });

    it('executes typed resolveDuplicate client command with survivor and rejected versions', async () => {
        const requests: RecordedRequest[] = [];
        const fetchImpl = vi.fn(
            async (input: RequestInfo | URL, init?: RequestInit) => {
                requests.push({ url: String(input), init });
                return new Response(
                    JSON.stringify(
                        projection('production', {
                            revision: 5,
                        }),
                    ),
                    {
                        status: 200,
                        headers: { 'Content-Type': 'application/json' },
                    },
                );
            },
        );

        const client = createOmrWorkbenchClient({
            workbenchId: 'production:7:9',
            executionKind: 'production',
            urls: {
                resolveDuplicate: (sheetId) =>
                    `/grading/sessions/7/omr/batches/9/workbench/sheets/${sheetId}/resolve-duplicate`,
            },
            fetchImpl,
        });

        const result = await client.resolveDuplicate(
            12,
            {
                survivor_sheet_id: 12,
                rejected_sheet_id: 41,
                survivor_version: 1,
                rejected_version: 2,
                reason: 'Physical booklet signature verified on primary sheet',
                idempotency_key: 'idemp-dup-123',
            },
            4,
        );

        expect(result.revision).toBe(5);
        expect(requests).toHaveLength(1);
        expect(requests[0].url).toBe(
            '/grading/sessions/7/omr/batches/9/workbench/sheets/12/resolve-duplicate',
        );
        const body = jsonRequestBody(requests[0]);
        expect(body).toEqual({
            survivor_sheet_id: 12,
            rejected_sheet_id: 41,
            survivor_version: 1,
            rejected_version: 2,
            reason: 'Physical booklet signature verified on primary sheet',
            expected_revision: 4,
            idempotency_key: 'idemp-dup-123',
        });
    });

    it('enforces production-only execution and command authorization for duplicate resolution', async () => {
        const diagnosticClient = createOmrWorkbenchClient({
            workbenchId: 'diagnostic:run-1',
            executionKind: 'diagnostic',
            urls: {
                resolveDuplicate: '/diagnostic/resolve-duplicate',
            },
            fetchImpl: vi.fn(),
        });

        await expect(
            diagnosticClient.resolveDuplicate(
                12,
                {
                    survivor_sheet_id: 12,
                    rejected_sheet_id: 41,
                    survivor_version: 1,
                    rejected_version: 2,
                    reason: 'Valid reason exceeding eight chars',
                },
                1,
            ),
        ).rejects.toThrow(
            /resolveDuplicate is unavailable for the diagnostic OMR workbench/,
        );

        const unauthorizedClient = createOmrWorkbenchClient({
            workbenchId: 'production:7:9',
            executionKind: 'production',
            urls: {
                show: '/grading/sessions/7/omr/batches/9/workbench',
                resolveDuplicate: (id) => `/resolve-duplicate/${id}`,
            },
            fetchImpl: vi.fn().mockResolvedValue(
                new Response(
                    JSON.stringify({
                        projection: projection('production', {
                            commands: ['capture_start'],
                        }),
                    }),
                    {
                        status: 200,
                        headers: { 'Content-Type': 'application/json' },
                    },
                ),
            ),
        });

        await unauthorizedClient.load();

        await expect(
            unauthorizedClient.resolveDuplicate(
                12,
                {
                    survivor_sheet_id: 12,
                    rejected_sheet_id: 41,
                    survivor_version: 1,
                    rejected_version: 2,
                    reason: 'Valid reason exceeding eight chars',
                },
                1,
            ),
        ).rejects.toThrow(
            /The requested OMR command is not available in the current projection/,
        );
    });
});

describe('OMR workbench Monitoring destination and return preservation contract', () => {
    it('provides authorized Exam Monitoring destination on submission_missing recovery card', async () => {
        const onRecoveryAction = vi.fn().mockResolvedValue(undefined);
        const submissionMissingItem: OmrWorkbenchRecoveryItem = {
            code: 'submission_missing',
            title: 'Submission not recorded',
            explanation:
                'Record the submission in Exam Monitoring, then return to this scan.',
            blocked: true,
            owner: 'Exam proctor',
            support_code: 'REC-SUB-001',
            primary_action: 'open_monitoring',
            destination_url: '/exams/sessions/7/monitoring',
        };

        const view = render(OmrWorkbench, {
            projection: projection('production', {
                recovery: {
                    items: [submissionMissingItem],
                    needs_attention: [{ owner: 'Exam proctor', count: 1 }],
                    count: 1,
                    outcome: null,
                },
            }),
            onRecoveryAction,
        });

        expect(view.getByText('Submission not recorded')).not.toBeNull();
        expect(view.getByTitle('Exam proctor').textContent).toContain(
            'Exam proctor',
        );

        const actionBtn = view.getByRole('button', {
            name: /open monitoring/i,
        });
        expect(actionBtn).not.toBeNull();
        await fireEvent.click(actionBtn);

        expect(onRecoveryAction).toHaveBeenCalledWith(
            'open_monitoring',
            expect.objectContaining({
                destination_url: '/exams/sessions/7/monitoring',
                code: 'submission_missing',
            }),
        );
    });

    it('preserves return_to parameter format across clean and query-bearing destination URLs', () => {
        function buildMonitoringUrl(
            destinationUrl: string,
            currentPath: string,
            sheetId: number,
        ): string {
            const returnTo = `${currentPath}?omr_sheet=${sheetId}`;
            const separator = destinationUrl.includes('?') ? '&' : '?';
            return `${destinationUrl}${separator}return_to=${encodeURIComponent(returnTo)}`;
        }

        const cleanDestination = '/exams/sessions/7/monitoring';
        const url1 = buildMonitoringUrl(
            cleanDestination,
            '/grading/sessions/7/omr',
            12,
        );
        expect(url1).toBe(
            '/exams/sessions/7/monitoring?return_to=%2Fgrading%2Fsessions%2F7%2Fomr%3Fomr_sheet%3D12',
        );

        const queryDestination = '/exams/sessions/7/monitoring?view=attendance';
        const url2 = buildMonitoringUrl(
            queryDestination,
            '/grading/sessions/7/omr',
            12,
        );
        expect(url2).toBe(
            '/exams/sessions/7/monitoring?view=attendance&return_to=%2Fgrading%2Fsessions%2F7%2Fomr%3Fomr_sheet%3D12',
        );
    });

    it('restores resumed sheet from query parameter and withholds completion until confirmed', async () => {
        const onComplete = vi.fn();
        const view = render(OmrWorkbench, {
            projection: projection('production', {
                sheets: [
                    {
                        id: 12,
                        client_capture_id: 'cap-12',
                        status: 'needs_attention',
                        version: 1,
                        stage: 'recovery',
                        pages: [],
                        recovery: {
                            code: 'submission_missing',
                            title: 'Submission not recorded',
                            explanation:
                                'Record the submission in Exam Monitoring, then return to this scan.',
                            blocked: true,
                            owner: 'Exam proctor',
                            support_code: 'REC-SUB-012',
                            primary_action: 'open_monitoring',
                        },
                    },
                ],
                commands: ['capture_start'],
            }),
            selectedSheetId: 12,
            onComplete,
        });

        expect(view.getByText('Capture cap-12')).not.toBeNull();
        expect(view.getAllByText('Needs attention').length).toBeGreaterThan(0);
        expect(view.getByText('Submission not recorded')).not.toBeNull();

        expect(
            view.queryByRole('button', {
                name: 'Confirm production completion',
            }),
        ).toBeNull();
        expect(onComplete).not.toHaveBeenCalled();
    });
});

describe('OMR workbench same-sheet selection restoration across projections', () => {
    it('preserves active sheet selection when newer projection revisions arrive', async () => {
        const onSelectSheet = vi.fn();
        const view = render(OmrWorkbench, {
            projection: projection('production', {
                revision: 1,
                sheets: [
                    {
                        id: 101,
                        client_capture_id: 'cap-101',
                        status: 'ready',
                        version: 1,
                        stage: 'review',
                        pages: [],
                    },
                    {
                        id: 102,
                        client_capture_id: 'cap-102',
                        status: 'needs_review',
                        version: 1,
                        stage: 'review',
                        pages: [],
                    },
                    {
                        id: 103,
                        client_capture_id: 'cap-103',
                        status: 'ready',
                        version: 1,
                        stage: 'review',
                        pages: [],
                    },
                ],
            }),
            onSelectSheet,
        });

        const options = view.getAllByRole('option');
        expect(options[0].getAttribute('aria-selected')).toBe('true');

        await fireEvent.click(options[1]);
        expect(view.getByRole('tab', { name: /#102/ }).getAttribute('aria-selected')).toBe('true');
        expect(onSelectSheet).toHaveBeenCalledWith(
            expect.objectContaining({ id: 102 }),
        );

        await view.rerender({
            projection: projection('production', {
                revision: 2,
                context: {
                    progress: { completed: 1, total: 3, known: true },
                },
                sheets: [
                    {
                        id: 101,
                        client_capture_id: 'cap-101',
                        status: 'completed',
                        version: 2,
                        stage: 'complete',
                        pages: [],
                    },
                    {
                        id: 102,
                        client_capture_id: 'cap-102',
                        status: 'needs_review',
                        version: 1,
                        stage: 'review',
                        pages: [],
                    },
                    {
                        id: 103,
                        client_capture_id: 'cap-103',
                        status: 'ready',
                        version: 1,
                        stage: 'review',
                        pages: [],
                    },
                ],
            }),
            onSelectSheet,
        });

        expect(
            view.getByRole('tab', { name: /#102/ }).getAttribute('aria-selected'),
        ).toBe('true');

        await view.rerender({
            projection: projection('production', {
                revision: 3,
                sheets: [
                    {
                        id: 101,
                        client_capture_id: 'cap-101',
                        status: 'completed',
                        version: 2,
                        stage: 'complete',
                        pages: [],
                    },
                    {
                        id: 102,
                        client_capture_id: 'cap-102',
                        status: 'needs_review',
                        version: 1,
                        stage: 'review',
                        pages: [],
                    },
                    {
                        id: 103,
                        client_capture_id: 'cap-103',
                        status: 'ready',
                        version: 1,
                        stage: 'review',
                        pages: [],
                    },
                    {
                        id: 104,
                        client_capture_id: 'cap-104',
                        status: 'ready',
                        version: 1,
                        stage: 'review',
                        pages: [],
                    },
                ],
            }),
            onSelectSheet,
        });

        expect(
            view.getByRole('tab', { name: /#102/ }).getAttribute('aria-selected'),
        ).toBe('true');
    });

    it('safely falls back to first sheet when previously selected sheet is removed', async () => {
        const view = render(OmrWorkbench, {
            projection: projection('production', {
                revision: 1,
                sheets: [
                    {
                        id: 201,
                        client_capture_id: 'cap-201',
                        status: 'ready',
                        version: 1,
                        stage: 'review',
                        pages: [],
                    },
                    {
                        id: 202,
                        client_capture_id: 'cap-202',
                        status: 'ready',
                        version: 1,
                        stage: 'review',
                        pages: [],
                    },
                ],
            }),
        });

        const options = view.getAllByRole('option');
        await fireEvent.click(options[1]);
        expect(view.getByRole('tab', { name: /#202/ }).getAttribute('aria-selected')).toBe('true');

        await view.rerender({
            projection: projection('production', {
                revision: 2,
                sheets: [
                    {
                        id: 201,
                        client_capture_id: 'cap-201',
                        status: 'ready',
                        version: 1,
                        stage: 'review',
                        pages: [],
                    },
                    {
                        id: 203,
                        client_capture_id: 'cap-203',
                        status: 'ready',
                        version: 1,
                        stage: 'review',
                        pages: [],
                    },
                ],
            }),
        });

        expect(
            view.getByRole('tab', { name: /#201/ }).getAttribute('aria-selected'),
        ).toBe('true');
    });

    it('respects incoming selectedSheetId prop overrides', async () => {
        const view = render(OmrWorkbench, {
            projection: projection('production', {
                sheets: [
                    {
                        id: 301,
                        client_capture_id: 'cap-301',
                        status: 'ready',
                        version: 1,
                        stage: 'review',
                        pages: [],
                    },
                    {
                        id: 302,
                        client_capture_id: 'cap-302',
                        status: 'ready',
                        version: 1,
                        stage: 'review',
                        pages: [],
                    },
                ],
            }),
            selectedSheetId: 301,
        });

        const options = view.getAllByRole('option');
        expect(options[0].getAttribute('aria-selected')).toBe('true');

        await view.rerender({
            projection: projection('production', {
                revision: 2,
                sheets: [
                    {
                        id: 301,
                        client_capture_id: 'cap-301',
                        status: 'ready',
                        version: 1,
                        stage: 'review',
                        pages: [],
                    },
                    {
                        id: 302,
                        client_capture_id: 'cap-302',
                        status: 'ready',
                        version: 1,
                        stage: 'review',
                        pages: [],
                    },
                ],
            }),
            selectedSheetId: 302,
        });

        const updatedOptions = view.getAllByRole('option');
        expect(updatedOptions[1].getAttribute('aria-selected')).toBe('true');
        expect(updatedOptions[0].getAttribute('aria-selected')).toBe('false');
    });
});

describe('OMR workbench absence of answer-key and Booklet Set leakage', () => {
    it('withholds answer keys, correct answers, and Booklet Sets from production DOM', async () => {
        const view = render(OmrWorkbench, {
            projection: projection('production', {
                context: {
                    template_version_id: 88,
                    booklet_version_id: 99,
                },
                evidence: {
                    references: [],
                    items: [],
                    captures: [],
                    redacted: true,
                    blind_review: true,
                },
            }),
        });

        const fullText = view.container.textContent ?? '';

        expect(fullText).not.toContain('answer_key');
        expect(fullText).not.toContain('correct_answer');
        expect(fullText).not.toContain('booklet_set_key');
        expect(fullText).not.toContain('template_version_id');
        expect(fullText).not.toContain('booklet_version_id');

        expect(fullText).not.toMatch(/Key:\s*[A-E]/i);
        expect(fullText).not.toMatch(/Correct:\s*[A-E]/i);
        expect(fullText).not.toMatch(/Booklet Set:\s*[A-E0-9]/i);
    });

    it('withholds answer keys and candidate Sets on key_missing and observed_set_mismatch cards', async () => {
        const keyMissingCard: OmrWorkbenchRecoveryItem = {
            code: 'key_missing',
            title: 'Answer key unavailable',
            explanation: 'The frozen booklet Set has no immutable answer key.',
            blocked: true,
            owner: 'Assessment setup staff',
            support_code: 'ERR-KEY-001',
            primary_action: 'open_sheet_builder',
        };

        const setMismatchCard: OmrWorkbenchRecoveryItem = {
            code: 'observed_set_mismatch',
            title: 'Observed Set conflict',
            explanation:
                'The visible Set differs from the submission-frozen seat; scoring remains blocked.',
            blocked: true,
            owner: 'Assessment setup staff',
            support_code: 'ERR-SET-002',
            primary_action: 'review_material_assignment',
        };

        const view1 = render(OmrRecoveryCard, {
            recovery: keyMissingCard,
            idPrefix: 'key-miss',
        });
        const text1 = view1.container.textContent ?? '';
        expect(text1).toContain('Answer key unavailable');
        expect(text1).not.toContain('answer_key');
        expect(text1).not.toMatch(/Key:\s*[A-E]/i);
        cleanup();

        const view2 = render(OmrRecoveryCard, {
            recovery: setMismatchCard,
            idPrefix: 'set-mis',
        });
        const text2 = view2.container.textContent ?? '';
        expect(text2).toContain('Observed Set conflict');
        expect(text2).not.toContain('booklet_set_key');
        expect(text2).not.toMatch(/Set:\s*[A-E0-9]/i);
    });

    it('renders only public session and run attributes in context summary', async () => {
        const view = render(OmrWorkbench, {
            projection: projection('production', {
                context: {
                    session_id: 7,
                    run_id: 9,
                    status: 'active',
                    title: 'Production grading run',
                    subtitle: 'Server-owned grading context',
                    template_version_id: 42,
                    booklet_version_id: 84,
                    academic_cycle_id: 10,
                },
            }),
        });

        const summary = view.container.querySelector(
            '[data-omr-context-summary]',
        );
        expect(summary).not.toBeNull();
        const summaryText = summary?.textContent ?? '';

        expect(summaryText).toContain('Session');
        expect(summaryText).toContain('7');
        expect(summaryText).toContain('Run');
        expect(summaryText).toContain('9');

        expect(summaryText).not.toContain('template_version_id');
        expect(summaryText).not.toContain('booklet_version_id');
        expect(summaryText).not.toContain('academic_cycle_id');
        expect(summaryText).not.toContain('42');
        expect(summaryText).not.toContain('84');
    });
});

describe('QR-disabled pending-page operator progression', () => {
    it('advances the visible pending target after a successful Page 1 capture', async () => {
        const view = render(OmrPendingCaptureProgress, {
            pending: {
                id: '00000000-0000-4000-8000-000000000003',
                revision: 1,
                status: 'pending',
                page_count: 2,
                active_page_no: 1,
                pages: [
                    { page_no: 1, active: true, capture_count: 0 },
                    { page_no: 2, active: false, capture_count: 0 },
                ],
            },
            production: true,
        });

        expect(view.getByText(/0 of 2 pages captured/)).toBeTruthy();
        expect(view.getByRole('status').textContent).toContain('Page 1 of 2');
        expect(view.getByText('Current')).toBeTruthy();
        expect(view.getByText('Waiting')).toBeTruthy();

        await view.rerender({
            pending: {
                id: '00000000-0000-4000-8000-000000000003',
                revision: 2,
                status: 'pending',
                page_count: 2,
                active_page_no: 2,
                pages: [
                    {
                        page_no: 1,
                        active: false,
                        capture_count: 1,
                        detected_applicant_number: 'APP-101',
                    },
                    { page_no: 2, active: true, capture_count: 0 },
                ],
            },
            production: true,
        });

        expect(view.getByText(/1 of 2 pages captured/)).toBeTruthy();
        expect(view.getByRole('status').textContent).toContain('Page 2 of 2');
        expect(view.getByText('Captured · APP-101')).toBeTruthy();
        expect(view.getByText('Current')).toBeTruthy();
        expect(
            view.queryByRole('button', { name: 'Continue to sheet review' }),
        ).toBeNull();
    });

    it('keeps missing-identity page evidence visible and blocks production adoption', () => {
        const onAdopt = vi.fn();
        const view = render(OmrPendingCaptureProgress, {
            pending: {
                id: '00000000-0000-4000-8000-000000000004',
                revision: 3,
                status: 'captured',
                page_count: 2,
                active_page_no: 2,
                pairing_code: 'pairing_missing',
                pages: [
                    {
                        page_no: 1,
                        capture_count: 1,
                        detected_applicant_number: 'APP-101',
                    },
                    {
                        page_no: 2,
                        capture_count: 1,
                        recognition_code: 'applicant_number_unreadable',
                    },
                ],
            },
            production: true,
            onAdopt,
        });

        const alert = view.getByRole('alert');
        expect(alert.textContent).toContain(
            'Applicant Number is missing on at least one page.',
        );
        expect(alert.textContent?.replace(/\s+/g, ' ')).toContain(
            'No applicant or score is attached.',
        );
        expect(view.getByText('Captured · APP-101')).toBeTruthy();
        expect(view.getByText('Needs review')).toBeTruthy();
        expect(
            view.queryByRole('button', { name: 'Continue to sheet review' }),
        ).toBeNull();
        expect(onAdopt).not.toHaveBeenCalled();
    });

    it('renders typed production adoption recovery for blocked QR-disabled stock', () => {
        const onAdopt = vi.fn();
        const view = render(OmrPendingCaptureProgress, {
            pending: {
                id: '00000000-0000-4000-8000-000000000005',
                revision: 7,
                status: 'ready',
                page_count: 2,
                active_page_no: 2,
                pairing_code: null,
                resolution_code: 'qr_disabled_stock_exhausted',
                recovery: {
                    title: 'QR-disabled stock exhausted',
                    explanation:
                        'Issue more stock before accepting another capture.',
                    owner: 'Assessment setup staff',
                },
                pages: [
                    {
                        page_no: 1,
                        capture_count: 1,
                        detected_applicant_number: 'APP-101',
                    },
                    {
                        page_no: 2,
                        capture_count: 1,
                        detected_applicant_number: 'APP-101',
                    },
                ],
            },
            production: true,
            onAdopt,
        });

        expect(view.getByRole('status').textContent).toContain(
            'Adoption blocked',
        );
        const recovery = view.getByRole('alert');
        expect(recovery.textContent).toContain('QR-disabled stock exhausted');
        expect(recovery.textContent).toContain(
            'Issue more stock before accepting another capture.',
        );
        expect(recovery.textContent).toContain('Owner: Assessment setup staff');
        expect(
            view.getByRole('button', { name: 'Retry adoption' }),
        ).toBeTruthy();
    });

    it('keeps paired diagnostic captures explicitly non-scoring', () => {
        const diagnosticAdopt = vi.fn();
        const diagnostic = render(OmrPendingCaptureProgress, {
            pending: {
                id: '00000000-0000-4000-8000-000000000006',
                revision: 5,
                status: 'ready',
                page_count: 2,
                active_page_no: 2,
                pairing_code: null,
                pages: [
                    {
                        page_no: 1,
                        capture_count: 1,
                        detected_applicant_number: 'APP-101',
                    },
                    {
                        page_no: 2,
                        capture_count: 1,
                        detected_applicant_number: 'APP-101',
                    },
                ],
            },
            onAdopt: diagnosticAdopt,
        });

        expect(diagnostic.getAllByRole('status')[0].textContent).toContain(
            'Pages paired · diagnostic only',
        );
        expect(
            diagnostic.getByText(
                /cannot create production paper claims or scores/,
            ),
        ).toBeTruthy();
        expect(
            diagnostic.queryByRole('button', {
                name: 'Continue to sheet review',
            }),
        ).toBeNull();
        expect(diagnosticAdopt).not.toHaveBeenCalled();
    });

    it('shows the conflicting page evidence and requires a reason before retake', async () => {
        const onRetake = vi.fn();
        const view = render(OmrPendingCaptureProgress, {
            pending: {
                id: '00000000-0000-4000-8000-000000000001',
                revision: 4,
                status: 'captured',
                page_count: 2,
                active_page_no: 2,
                pairing_code: 'pairing_conflict',
                pages: [
                    {
                        page_no: 1,
                        capture_count: 1,
                        detected_applicant_number: 'APP-101',
                    },
                    {
                        page_no: 2,
                        capture_count: 1,
                        detected_applicant_number: 'APP-202',
                    },
                ],
            },
            production: true,
            onRetake,
        });

        expect(view.getByRole('alert').textContent).toContain(
            'different Applicant Numbers',
        );
        expect(view.getAllByText(/APP-101/).length).toBeGreaterThan(0);
        expect(view.getByText(/APP-202/)).toBeTruthy();
        await fireEvent.click(
            view.getByRole('button', { name: 'Retake page 2' }),
        );
        const reason = view.getByLabelText('Reason for retaking Page 2');
        const confirm = view.getByRole('button', { name: 'Capture Page 2' });
        expect(confirm.hasAttribute('disabled')).toBe(true);
        await fireEvent.input(reason, { target: { value: '   ' } });
        expect(confirm.hasAttribute('disabled')).toBe(true);
        await fireEvent.click(confirm);
        expect(onRetake).not.toHaveBeenCalled();
        await fireEvent.input(reason, {
            target: { value: '  Page 2 belongs to another examinee  ' },
        });
        expect(confirm.hasAttribute('disabled')).toBe(false);
        await fireEvent.click(confirm);
        expect(onRetake).toHaveBeenCalledWith(
            2,
            'Page 2 belongs to another examinee',
        );
    });

    it('shows ready, busy, and adopted production states around sheet review', async () => {
        const pending = {
            id: '00000000-0000-4000-8000-000000000002',
            revision: 5,
            status: 'ready',
            page_count: 2,
            active_page_no: 2,
            pairing_code: null,
            pages: [
                {
                    page_no: 1,
                    capture_count: 1,
                    detected_applicant_number: 'APP-101',
                },
                {
                    page_no: 2,
                    capture_count: 1,
                    detected_applicant_number: 'APP-101',
                },
            ],
        };
        const onAdopt = vi.fn();
        const production = render(OmrPendingCaptureProgress, {
            pending,
            production: true,
            onAdopt,
        });

        expect(production.getByRole('status').textContent).toContain(
            'Ready to adopt',
        );
        const readyButton = production.getByRole('button', {
            name: 'Continue to sheet review',
        });
        expect(readyButton.hasAttribute('disabled')).toBe(false);

        await production.rerender({
            pending,
            production: true,
            busy: true,
            onAdopt,
        });
        const busyButton = production.getByRole('button', {
            name: 'Checking adoption…',
        });
        expect(busyButton.hasAttribute('disabled')).toBe(true);

        await production.rerender({
            pending,
            production: true,
            busy: false,
            onAdopt,
        });
        await fireEvent.click(
            production.getByRole('button', {
                name: 'Continue to sheet review',
            }),
        );
        expect(onAdopt).toHaveBeenCalledOnce();

        await production.rerender({
            pending: {
                ...pending,
                revision: 6,
                status: 'adopted',
                adopted_sheet_id: 84,
            },
            production: true,
            busy: false,
            onAdopt,
        });
        expect(production.getByRole('status').textContent).toContain(
            'Ready for sheet review',
        );
        expect(
            production.queryByRole('button', {
                name: 'Continue to sheet review',
            }),
        ).toBeNull();
    });

    it('keeps paired diagnostic completion explicitly non-scoring', () => {
        const pending = {
            id: '00000000-0000-4000-8000-000000000006',
            revision: 5,
            status: 'ready',
            page_count: 2,
            active_page_no: 2,
            pairing_code: null,
            pages: [
                {
                    page_no: 1,
                    capture_count: 1,
                    detected_applicant_number: 'APP-101',
                },
                {
                    page_no: 2,
                    capture_count: 1,
                    detected_applicant_number: 'APP-101',
                },
            ],
        };
        const diagnosticAdopt = vi.fn();
        const diagnostic = render(OmrPendingCaptureProgress, {
            pending,
            production: false,
            onAdopt: diagnosticAdopt,
        });

        expect(
            diagnostic.getByText('Pages paired · diagnostic only'),
        ).toBeTruthy();
        expect(
            diagnostic.getByText(
                /cannot create production paper claims or scores/,
            ),
        ).toBeTruthy();
        expect(
            diagnostic.queryByRole('button', {
                name: 'Continue to sheet review',
            }),
        ).toBeNull();
        expect(diagnosticAdopt).not.toHaveBeenCalled();
    });
});
