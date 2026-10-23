import type { ViewfinderQuad } from './omrViewfinderWorker';

export type OmrCameraMode = 'auto' | 'manual' | 'batch';
export type OmrBatchTrigger = 'auto_steady' | 'manual_qr';
export type OmrCropPolicy = 'auto_document_quad' | 'keep_original';

export type OmrCaptureTargetIdentity = Readonly<{
    workbenchId: string;
    clientCaptureId: string | null;
    pageNo: number;
    captureRevision: number | string | null;
    instanceId: string | number | null;
    sheetId: string | number | null;
}>;

export type OmrCaptureTargetContext = Readonly<
    OmrCaptureTargetIdentity & { targetKey: string }
>;

export type OmrCaptureAttempt = Readonly<{
    target: OmrCaptureTargetContext;
    sourceGeneration: number;
    requiresSheetSwap: boolean;
}>;

export type OmrFailedUpload = Readonly<{
    id: number;
    file: File;
    target: OmrCaptureTargetContext;
    cropPolicy: OmrCropPolicy;
    quad: ViewfinderQuad | null;
    requiresSheetSwap: boolean;
}>;

export type OmrCaptureAvailability = Readonly<{
    cameraEnabled: boolean;
    visible: boolean;
    disabled: boolean;
    busy: boolean;
    paused: boolean;
    settingsOpen: boolean;
}>;
export function shouldRunOmrCameraSource(
    cameraEnabled: boolean,
    visible: boolean,
    paused: boolean,
): boolean {
    return cameraEnabled && visible && !paused;
}


export function buildOmrCaptureTargetKey(
    identity: OmrCaptureTargetIdentity,
): string {
    return JSON.stringify([
        identity.workbenchId,
        identity.clientCaptureId ?? null,
        identity.pageNo,
        identity.captureRevision ?? null,
        identity.instanceId ?? null,
        identity.sheetId ?? null,
    ]);
}

export function freezeOmrCaptureTargetContext(
    identity: OmrCaptureTargetIdentity,
): OmrCaptureTargetContext | null {
    if (
        typeof identity.workbenchId !== 'string' ||
        identity.workbenchId.trim() === '' ||
        !Number.isSafeInteger(identity.pageNo) ||
        identity.pageNo < 1
    ) {
        return null;
    }

    return Object.freeze({
        ...identity,
        targetKey: buildOmrCaptureTargetKey(identity),
    });
}

export function isOmrCaptureAttemptCurrent(
    attempt: OmrCaptureAttempt,
    current: OmrCaptureTargetContext | null,
    currentSourceGeneration: number,
): boolean {
    return (
        current !== null &&
        attempt.target.targetKey === current.targetKey &&
        attempt.sourceGeneration === currentSourceGeneration
    );
}

export function isOmrCaptureAvailable(
    availability: OmrCaptureAvailability,
    target: OmrCaptureTargetContext | null,
): boolean {
    return Boolean(
        target &&
            availability.cameraEnabled &&
            availability.visible &&
            !availability.disabled &&
            !availability.busy &&
            !availability.paused &&
            !availability.settingsOpen,
    );
}

export function isOmrFileUploadAvailable(
    availability: OmrCaptureAvailability,
    target: OmrCaptureTargetContext | null,
): boolean {
    return Boolean(
        target &&
            availability.visible &&
            !availability.disabled &&
            !availability.busy &&
            !availability.paused &&
            !availability.settingsOpen,
    );
}

export function usesAutomaticOmrCapture(
    mode: OmrCameraMode,
    batchTrigger: OmrBatchTrigger,
): boolean {
    return mode === 'auto' || (mode === 'batch' && batchTrigger === 'auto_steady');
}

export function canCaptureOmrTarget(
    lastCapturedTarget: string | null,
    target: OmrCaptureTargetContext | null,
    awaitingSheetSwap: boolean,
): boolean {
    return Boolean(
        target &&
            !awaitingSheetSwap &&
            lastCapturedTarget !== target.targetKey,
    );
}

export function canRetryOmrFailedUpload(
    failed: OmrFailedUpload,
    current: OmrCaptureTargetContext | null,
    available: boolean,
): boolean {
    return Boolean(
        available &&
            current &&
            failed.target.targetKey === current.targetKey,
    );
}
