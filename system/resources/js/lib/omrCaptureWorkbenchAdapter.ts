import type { OmrCaptureAcceptance, OmrCaptureAction, OmrCaptureCrop, OmrCaptureOperation, OmrCapturedFrame } from './omrCaptureProtocol';
import type { OmrCameraBootstrap } from './omrCameraSource';
import type { OmrAcceptedCaptureReference, OmrLifecycleAuthorization } from './omrCaptureLifecycle';
import { OmrWorkbenchConflictError, type OmrCameraControllerAuthorization, type OmrWorkbenchCaptureInput, type OmrWorkbenchCommandPayload } from './omrWorkbenchClient';
import { freezeOmrCaptureTargetContext } from './omrCameraAcquisition';

function record(value: unknown): Record<string, unknown> {
    return value !== null && typeof value === 'object' && !Array.isArray(value) ? value as Record<string, unknown> : {};
}

export function captureInputFromFrame(frame: OmrCapturedFrame): OmrWorkbenchCaptureInput {
    return frame.kind === 'raw'
        ? { kind: 'raw', file: frame.file }
        : { kind: 'staged', stagedFrameId: frame.frameId, pairingId: frame.pairingId, commandId: frame.commandId };
}

export function captureOperationPayload(operation: OmrCaptureOperation): OmrWorkbenchCommandPayload {
    return {
        operation_id: operation.operationId,
        idempotency_key: operation.idempotencyKey,
        target_key: operation.target.targetKey,
        source_generation: operation.sourceGeneration,
        config_revision: operation.configRevision,
        client_capture_id: operation.target.clientCaptureId,
        page_no: operation.target.pageNo,
        target_revision: operation.target.captureRevision,
        ...(operation.target.instanceId !== null ? { instance_id: operation.target.instanceId } : {}),
        ...(operation.target.sheetId !== null ? { sheet_id: operation.target.sheetId } : {}),
    };
}

/** Only a matching persisted domain receipt may drive accepted/processing cues. */
export function captureAcceptanceFromProjection(value: unknown, operation: Pick<OmrCaptureOperation, 'operationId' | 'target'>): OmrCaptureAcceptance {
    const projection = record(value);
    const receipt = record(record(projection.context).capture_receipt);
    const status = receipt.status;
    const captureId = receipt.capture_id;
    const hasEvidence = (typeof captureId === 'number' && Number.isSafeInteger(captureId) && captureId > 0) ||
        (typeof captureId === 'string' && captureId.trim() !== '');
    if (projection.workbench_id !== operation.target.workbenchId) {
        throw new OmrWorkbenchConflictError(
            'Capture acknowledgment is unavailable or belongs to another workbench.',
            { code: 'capture_receipt_mismatch', details: { operationId: operation.operationId } },
        );
    }
    if (
        status === 'pending' &&
        receipt.operation_id === operation.operationId &&
        receipt.target_key === '' &&
        receipt.page_no === null &&
        captureId == null
    ) {
        return {
            operationId: operation.operationId,
            targetKey: operation.target.targetKey,
            status: 'pending',
            pageNo: operation.target.pageNo,
            message: 'No persisted receipt confirms this capture yet. Its request outcome remains unconfirmed.',
            stage: 'recovery',
            recovery: 'Keep this exact operation held and check again; do not submit another capture.',
            projection,
        };
    }
    if (
        receipt.operation_id !== operation.operationId ||
        receipt.target_key !== operation.target.targetKey ||
        receipt.page_no !== operation.target.pageNo ||
        (status !== 'accepted' && status !== 'pending' && status !== 'rejected') ||
        ((status === 'accepted' || status === 'pending') && !hasEvidence)
    ) {
        throw new OmrWorkbenchConflictError(
            status === 'pending' && !hasEvidence
                ? 'No capture receipt or processing reservation confirms this operation. Its outcome is unconfirmed; check this exact operation before retrying.'
                : 'Capture acknowledgment is unavailable or does not match this operation. Check its existing outcome before capturing again.',
            { code: status === 'pending' && !hasEvidence ? 'capture_receipt_missing' : 'capture_receipt_mismatch', details: { operationId: operation.operationId } },
        );
    }
    const recognitionCaptureId = projection.execution_kind === 'diagnostic'
        ? receipt.diagnostic_capture_id ?? captureId
        : captureId;
    const captureFailure = Array.isArray(projection.sheets)
        ? projection.sheets
            .map(record)
            .find((sheet) => String(sheet.id ?? sheet.capture_id ?? '') === String(recognitionCaptureId ?? ''))
        : null;
    const failure = record(captureFailure?.failure);
    const failureMessage = typeof failure.message === 'string' ? failure.message : null;
    const failureStage = typeof failure.stage === 'string' ? failure.stage : null;
    const failureRecovery = typeof failure.recovery === 'string' ? failure.recovery : null;
    return {
        operationId: operation.operationId,
        targetKey: operation.target.targetKey,
        status,
        pageNo: operation.target.pageNo,
        ...(hasEvidence ? { captureId: captureId as number | string } : {}),
        projection,
        ...(failureMessage ? { message: failureMessage } : typeof receipt.message === 'string' ? { message: receipt.message } : {}),
        ...(failureStage ? { stage: failureStage } : {}),
        ...(failureRecovery ? { recovery: failureRecovery } : {}),
    };
}

/** Restores only a real persisted accepted page; no source generation or idempotency key is fabricated. */
export function acceptedCaptureReferenceFromProjection(value: unknown): OmrAcceptedCaptureReference | null {
    const projection = record(value);
    const receipt = record(record(projection.context).capture_receipt);
    if (receipt.status !== 'accepted') return null;
    const rawTarget = record(receipt.target);
    const identifier = (entry: unknown): entry is string | number | null =>
        entry === null || typeof entry === 'string' || typeof entry === 'number' && Number.isFinite(entry);
    if (typeof receipt.operation_id !== 'string' || typeof rawTarget.workbenchId !== 'string' ||
        rawTarget.workbenchId !== projection.workbench_id ||
        (rawTarget.clientCaptureId !== null && typeof rawTarget.clientCaptureId !== 'string') ||
        typeof rawTarget.pageNo !== 'number' || !identifier(rawTarget.captureRevision) ||
        !identifier(rawTarget.instanceId) || !identifier(rawTarget.sheetId)) {
        throw new OmrWorkbenchConflictError('The persisted capture reference does not match this workbench.', { code: 'capture_receipt_mismatch' });
    }
    const target = freezeOmrCaptureTargetContext({
        workbenchId: rawTarget.workbenchId,
        clientCaptureId: rawTarget.clientCaptureId,
        pageNo: rawTarget.pageNo,
        captureRevision: rawTarget.captureRevision,
        instanceId: rawTarget.instanceId,
        sheetId: rawTarget.sheetId,
    });
    if (!target || target.targetKey !== rawTarget.targetKey) {
        throw new OmrWorkbenchConflictError('The persisted capture target is malformed.', { code: 'capture_receipt_mismatch' });
    }
    const operation = { operationId: receipt.operation_id, target };
    return { operation, receipt: captureAcceptanceFromProjection(projection, operation) };
}

export function cameraAuthorizationForFrame(
    frame: OmrCapturedFrame,
    pairing: OmrCameraBootstrap | null,
): OmrCameraControllerAuthorization | undefined {
    if (frame.kind === 'raw') return undefined;
    if (!pairing || pairing.pairingId !== frame.pairingId || !pairing.token) {
        throw new OmrWorkbenchConflictError(
            'This original belongs to a different or expired phone camera controller.',
            { code: 'camera_pairing_mismatch' },
        );
    }
    return { token: pairing.token, generation: pairing.generation };
}

export type OmrCaptureActionGrant = OmrLifecycleAuthorization & Readonly<{ url: string }>;

export function captureActionsFromProjection(
    value: unknown,
): Partial<Record<OmrCaptureAction, OmrCaptureActionGrant>> {
    const grants = record(record(record(value).context).capture_actions);
    const actions: Partial<Record<OmrCaptureAction, OmrCaptureActionGrant>> = {};
    for (const action of ['next_page', 'next_set', 'redo_last'] as const) {
        const grant = record(grants[action]);
        if (['authorizationId', 'targetKey', 'acceptedTargetKey', 'url'].every((key) => typeof grant[key] === 'string' && (grant[key] as string).trim() !== '')) {
            actions[action] = {
                authorizationId: grant.authorizationId as string,
                targetKey: grant.targetKey as string,
                acceptedTargetKey: grant.acceptedTargetKey as string,
                url: grant.url as string,
            };
        }
    }
    return actions;
}

export function captureFramePayload(
    frame: OmrCapturedFrame,
    operation: OmrCaptureOperation,
    crop: OmrCaptureCrop,
): OmrWorkbenchCommandPayload {
    const hasStillCoordinates = crop.quad && frame.width !== null && frame.height !== null &&
        frame.width > 0 && frame.height > 0 && frame.orientation === 1 &&
        /^[a-f0-9]{64}$/.test(frame.inputHash);
    return {
        ...captureOperationPayload(operation),
        crop_policy: crop.policy,
        ...(hasStillCoordinates ? {
            client_quad: {
                points: crop.quad!.map(({ x, y }) => [x, y]),
                width: frame.width,
                height: frame.height,
                orientation: 1,
                input_hash: frame.inputHash,
                page_no: operation.target.pageNo,
            },
        } : {}),
    };
}
