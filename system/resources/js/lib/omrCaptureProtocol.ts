import type { ViewfinderQuad, ViewfinderResult } from './omrViewfinderWorker';
import type {
    OmrBatchTrigger,
    OmrCameraMode,
    OmrCaptureTargetContext,
    OmrCropPolicy,
} from './omrCameraAcquisition';

export const OMR_CAMERA_PROTOCOL_VERSION = 'nexiam.omr.camera.v1' as const;

export type OmrCaptureMode = OmrCameraMode;
export type OmrCaptureBatchTrigger = OmrBatchTrigger;
export type OmrCaptureTrigger = 'shutter' | 'keyboard' | 'steady' | 'qr_capture';
export type OmrCaptureAction = 'next_page' | 'next_set' | 'redo_last';
export type OmrCaptureStatus = 'accepted' | 'pending' | 'rejected';

export type OmrCaptureOperation = Readonly<{
    operationId: string;
    idempotencyKey: string;
    target: OmrCaptureTargetContext;
    sourceId: string;
    sourceGeneration: number;
    configRevision: number;
    trigger: OmrCaptureTrigger;
    mode: OmrCaptureMode;
}>;
export type OmrCaptureOperationReference = Pick<
    OmrCaptureOperation,
    'operationId' | 'idempotencyKey' | 'target'
>;

export type OmrCapturedFrameMetadata = Readonly<{
    targetKey: string;
    inputHash: string;
    width: number | null;
    height: number | null;
    orientation: number | null;
    sourceGeneration: number;
    clientQuad?: ViewfinderQuad;
}>;

export type OmrCapturedRawFrame = OmrCapturedFrameMetadata & Readonly<{ kind: 'raw'; file: File }>;
export type OmrCapturedStagedFrame = Omit<OmrCapturedFrameMetadata, 'width' | 'height' | 'orientation'> & Readonly<{
    kind: 'staged';
    frameId: string;
    previewUrl: string;
    commandId: string;
    pairingId: string;
    width: number;
    height: number;
    orientation: number;
}>;
export type OmrCapturedFrame = OmrCapturedRawFrame | OmrCapturedStagedFrame;

export type OmrCaptureCrop = Readonly<{ policy: OmrCropPolicy; quad: ViewfinderQuad | null }>;
export type OmrWorkbenchProjection = Readonly<Record<string, unknown>>;
export type OmrCaptureAcceptance = Readonly<{
    status: OmrCaptureStatus;
    operationId: string;
    targetKey: string;
    captureId?: number | string;
    pageNo: number;
    projection?: OmrWorkbenchProjection;
    message?: string;
    stage?: string;
    recovery?: string;
}>;

export type OmrCameraSourceConfig = Readonly<{
    target: OmrCaptureTargetContext;
    sourceGeneration: number;
    configRevision: number;
    registrationMode: 'fiducial_anchored' | 'document_bound';
    pageCount: number;
    mode: OmrCaptureMode;
    batchTrigger: OmrCaptureBatchTrigger;
    steadyDelayMs: 150 | 300 | 500 | 800;
    qrTriggerEnabled: boolean;
    paused: boolean;
}>;

export type OmrCameraSourceEvent =
    | Readonly<{ type: 'status'; status: 'connecting' | 'ready' | 'paused' | 'disconnected' | 'error'; message?: string }>
    | Readonly<{ type: 'configured'; config: OmrCameraSourceConfig }>
    | Readonly<{
        type: 'quality'; ready: boolean; targetKey: string; sourceGeneration: number; configRevision: number;
        sequence: number; lockProgress: number; relativeAgeMs?: number; result: ViewfinderResult | null;
    }>
    | Readonly<{
        type: 'qr_intent'; command: 'CAPTURE' | 'NEXT_PAGE' | 'NEXT_SET' | 'REDO_LAST';
        targetKey: string; sourceGeneration: number; configRevision: number; commandId: string; sequence: number;
    }>
    | Readonly<{
        type: 'qr_card_removed'; targetKey: string; sourceGeneration: number; configRevision: number; sequence: number; observedAt: number;
    }>
    | Readonly<{
        type: 'capture_request'; trigger: OmrCaptureTrigger; targetKey: string; sourceGeneration: number; configRevision: number; commandId?: string;
    }>;

export interface OmrCameraSource {
    readonly id: string;
    readonly kind: 'local' | 'paired_phone';
    connect(listener: (event: OmrCameraSourceEvent) => void, signal?: AbortSignal): Promise<void>;
    attachPreview(video: HTMLVideoElement | null): void;
    configure(config: OmrCameraSourceConfig): Promise<void>;
    acquire(operation: OmrCaptureOperation, signal?: AbortSignal): Promise<OmrCapturedFrame>;
    disconnect(): void;
}

export type OmrCaptureCueName =
    | 'ready' | 'remove_command_card' | 'hold_steady' | 'countdown' | 'acquiring'
    | 'upload_pending' | 'accepted' | 'processing' | 'rejected' | 'checking_previous_capture' | 'disconnected' | 'session_expired' | 'capture_attention';
export type OmrCaptureCue = Readonly<{
    cueId: string;
    operationId: string | null;
    name: OmrCaptureCueName;
    tick?: number;
}>;

export type OmrCaptureLifecyclePhase =
    | 'unavailable' | 'armed' | 'awaiting_card_removal' | 'countdown' | 'acquiring'
    | 'frame_awaiting_review' | 'submitting' | 'reconciling' | 'accepted' | 'pending'
    | 'rejected' | 'held' | 'disposed';

export type OmrCaptureActionResult =
    | Readonly<{ supported: true; action: OmrCaptureAction; accepted: boolean; message?: string }>
    | Readonly<{ supported: false; action: OmrCaptureAction; reason: 'unsupported' | 'unauthorized' | 'busy' | 'no_accepted_capture' | 'stale_context' }>
    | Readonly<{ supported: 'unknown'; action: OmrCaptureAction; reason: 'outcome_unknown' }>;
