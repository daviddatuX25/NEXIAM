import { freezeOmrCaptureTargetContext } from './omrCameraAcquisition';
import type { OmrCaptureOperationReference } from './omrCaptureProtocol';

export type OmrCaptureSessionRecoveryRead =
    | Readonly<{ kind: 'none' }>
    | Readonly<{ kind: 'ready'; operation: OmrCaptureOperationReference }>
    | Readonly<{ kind: 'corrupt' | 'unavailable'; message: string }>;
export interface OmrCaptureSessionRecoveryStorage {
    getItem(key: string): string | null;
    setItem(key: string, value: string): void;
    removeItem(key: string): void;
}

type SerializedRecovery = Readonly<{
    version: 1;
    actorId: string;
    workbenchId: string;
    operation: OmrCaptureOperationReference;
}>;

function scopedKey(actorId: string | number | null, workbenchId: string): string | null {
    const actor = actorId === null ? '' : String(actorId);
    if (actor.trim() === '' || workbenchId.trim() === '') return null;
    return `nexiam.omr.capture.recovery.v1:${encodeURIComponent(actor)}:${encodeURIComponent(workbenchId)}`;
}

function browserStorage(): OmrCaptureSessionRecoveryStorage | null {
    try {
        return typeof window === 'undefined' ? null : window.sessionStorage;
    } catch {
        return null;
    }
}

function validOperationReference(value: unknown, workbenchId: string): value is OmrCaptureOperationReference {
    if (value === null || typeof value !== 'object' || Array.isArray(value)) return false;
    const raw = value as Record<string, unknown>;
    if (Object.keys(raw).sort().join(',') !== 'idempotencyKey,operationId,target') return false;
    const target = raw.target;
    if (target === null || typeof target !== 'object' || Array.isArray(target)) return false;
    const rawTarget = target as Record<string, unknown>;
    if (Object.keys(rawTarget).sort().join(',') !== 'captureRevision,clientCaptureId,instanceId,pageNo,sheetId,targetKey,workbenchId') return false;
    if (
        typeof raw.operationId !== 'string' || raw.operationId.trim() === '' ||
        typeof raw.idempotencyKey !== 'string' || raw.idempotencyKey.trim() === '' || raw.idempotencyKey.length > 255 ||
        rawTarget.workbenchId !== workbenchId ||
        !(rawTarget.clientCaptureId === null || typeof rawTarget.clientCaptureId === 'string') ||
        !Number.isSafeInteger(rawTarget.pageNo) ||
        !(rawTarget.captureRevision === null || typeof rawTarget.captureRevision === 'string' || Number.isSafeInteger(rawTarget.captureRevision)) ||
        !(rawTarget.instanceId === null || typeof rawTarget.instanceId === 'string' || Number.isSafeInteger(rawTarget.instanceId)) ||
        !(rawTarget.sheetId === null || typeof rawTarget.sheetId === 'string' || Number.isSafeInteger(rawTarget.sheetId)) ||
        typeof rawTarget.targetKey !== 'string'
    ) return false;

    const frozen = freezeOmrCaptureTargetContext({
        workbenchId,
        clientCaptureId: rawTarget.clientCaptureId as string | null,
        pageNo: rawTarget.pageNo as number,
        captureRevision: rawTarget.captureRevision as string | number | null,
        instanceId: rawTarget.instanceId as string | number | null,
        sheetId: rawTarget.sheetId as string | number | null,
    });
    return frozen !== null && frozen.targetKey === rawTarget.targetKey;
}

function operationReference(operation: OmrCaptureOperationReference): OmrCaptureOperationReference {
    return {
        operationId: operation.operationId,
        idempotencyKey: operation.idempotencyKey,
        target: {
            workbenchId: operation.target.workbenchId,
            targetKey: operation.target.targetKey,
            clientCaptureId: operation.target.clientCaptureId,
            pageNo: operation.target.pageNo,
            captureRevision: operation.target.captureRevision,
            instanceId: operation.target.instanceId,
            sheetId: operation.target.sheetId,
        },
    };
}

export function saveOmrCaptureSessionRecovery(
    actorId: string | number | null,
    workbenchId: string,
    operation: OmrCaptureOperationReference,
    storage: OmrCaptureSessionRecoveryStorage | null = browserStorage(),
): boolean {
    const key = scopedKey(actorId, workbenchId);
    if (!key || !storage || operation.target.workbenchId !== workbenchId) return false;
    const current = readOmrCaptureSessionRecovery(actorId, workbenchId, storage);
    if (current.kind !== 'none' && (
        current.kind !== 'ready' ||
        current.operation.operationId !== operation.operationId ||
        current.operation.idempotencyKey !== operation.idempotencyKey ||
        current.operation.target.targetKey !== operation.target.targetKey
    )) return false;
    try {
        const actor = String(actorId);
        const value: SerializedRecovery = {
            version: 1,
            actorId: actor,
            workbenchId,
            operation: operationReference(operation),
        };
        storage.setItem(key, JSON.stringify(value));
        return true;
    } catch {
        return false;
    }
}

export function readOmrCaptureSessionRecovery(
    actorId: string | number | null,
    workbenchId: string,
    storage: OmrCaptureSessionRecoveryStorage | null = browserStorage(),
): OmrCaptureSessionRecoveryRead {
    const key = scopedKey(actorId, workbenchId);
    if (!key || !storage) {
        return { kind: 'unavailable', message: 'Session storage is unavailable. No capture will be submitted; enable site storage and reopen the scanner.' };
    }
    let serialized: string | null;
    try {
        serialized = storage.getItem(key);
    } catch {
        return { kind: 'unavailable', message: 'Session storage could not be read. No capture will be submitted; enable site storage and reopen the scanner.' };
    }
    if (serialized === null) return { kind: 'none' };
    try {
        const value = JSON.parse(serialized) as Record<string, unknown>;
        if (
            Object.keys(value).sort().join(',') !== 'actorId,operation,version,workbenchId' ||
            value.version !== 1 || value.actorId !== String(actorId) || value.workbenchId !== workbenchId ||
            !validOperationReference(value.operation, workbenchId)
        ) {
            return { kind: 'corrupt', message: 'Saved capture recovery details are invalid. Do not capture again; reload the scanner or contact support.' };
        }
        return { kind: 'ready', operation: operationReference(value.operation) };
    } catch {
        return { kind: 'corrupt', message: 'Saved capture recovery details could not be read. Do not capture again; reload the scanner or contact support.' };
    }
}

export function clearOmrCaptureSessionRecovery(
    actorId: string | number | null,
    workbenchId: string,
    operation: OmrCaptureOperationReference,
    storage: OmrCaptureSessionRecoveryStorage | null = browserStorage(),
): boolean {
    const key = scopedKey(actorId, workbenchId);
    if (!key || !storage) return false;
    const current = readOmrCaptureSessionRecovery(actorId, workbenchId, storage);
    if (current.kind === 'none') return true;
    if (current.kind !== 'ready' || current.operation.operationId !== operation.operationId || current.operation.idempotencyKey !== operation.idempotencyKey) return false;
    try {
        storage.removeItem(key);
        return true;
    } catch {
        return false;
    }
}

export function purgeOmrCaptureSessionRecovery(
    actorId: string | number | null,
    workbenchId: string,
    storage: OmrCaptureSessionRecoveryStorage | null = browserStorage(),
): void {
    const key = scopedKey(actorId, workbenchId);
    if (!key || !storage) return;
    try {
        storage.removeItem(key);
    } catch {
        // ignore storage errors
    }
}

export class OmrCaptureSessionRecoveryStorageError extends Error {
    readonly code = 'omr_capture_session_recovery_storage_unavailable';

    constructor() {
        super('Session storage is unavailable. No capture was submitted. Enable site storage and reload the scanner before retrying.');
        this.name = 'OmrCaptureSessionRecoveryStorageError';
    }
}
