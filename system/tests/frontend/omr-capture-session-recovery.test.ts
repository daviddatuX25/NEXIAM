import { describe, expect, it } from 'vitest';
import { freezeOmrCaptureTargetContext } from '../../resources/js/lib/omrCameraAcquisition';
import type { OmrCaptureOperation } from '../../resources/js/lib/omrCaptureProtocol';
import {
    clearOmrCaptureSessionRecovery,
    readOmrCaptureSessionRecovery,
    saveOmrCaptureSessionRecovery,
    type OmrCaptureSessionRecoveryStorage,
} from '../../resources/js/lib/omrCaptureSessionRecovery';

class MemoryStorage implements OmrCaptureSessionRecoveryStorage {
    private readonly values = new Map<string, string>();

    getItem(key: string): string | null {
        return this.values.get(key) ?? null;
    }

    setItem(key: string, value: string): void {
        this.values.set(key, value);
    }

    removeItem(key: string): void {
        this.values.delete(key);
    }

    serializedValues(): string[] {
        return [...this.values.values()];
    }
}

const target = freezeOmrCaptureTargetContext({
    workbenchId: 'production:12:8',
    clientCaptureId: 'capture-group-3',
    pageNo: 2,
    captureRevision: 4,
    instanceId: 6,
    sheetId: 9,
})!;

const operation: OmrCaptureOperation = {
    operationId: '2a2f9171-9ba3-4c24-a438-f8bd5d1d31b1',
    idempotencyKey: 'f86a5fd3-6d9d-465c-8fb0-5f01c1e4c963',
    target,
    sourceId: 'local:camera',
    sourceGeneration: 3,
    configRevision: 4,
    trigger: 'shutter',
    mode: 'manual',
};
const reference = {
    operationId: operation.operationId,
    idempotencyKey: operation.idempotencyKey,
    target,
};

describe('OMR capture session recovery metadata', () => {
    it('stores only the actor/workbench-bound operation reference and restores the exact identity', () => {
        const storage = new MemoryStorage();
        expect(saveOmrCaptureSessionRecovery(71, target.workbenchId, operation, storage)).toBe(true);
        const values = storage.serializedValues();
        expect(values).toHaveLength(1);
        expect(values[0]).not.toContain('image');
        expect(values[0]).not.toContain('credential');
        expect(values[0]).not.toContain('token');
        expect(readOmrCaptureSessionRecovery(71, target.workbenchId, storage)).toEqual({
            kind: 'ready',
            operation: reference,
        });
    });

    it('does not expose one actor or workbench recovery to another', () => {
        const storage = new MemoryStorage();
        saveOmrCaptureSessionRecovery(71, target.workbenchId, operation, storage);
        expect(readOmrCaptureSessionRecovery(72, target.workbenchId, storage)).toEqual({ kind: 'none' });
        expect(readOmrCaptureSessionRecovery(71, 'diagnostic:other-run', storage)).toEqual({ kind: 'none' });
    });

    it('fails closed on corrupted or extended records and does not clear a different operation', () => {
        const storage = new MemoryStorage();
        saveOmrCaptureSessionRecovery(71, target.workbenchId, operation, storage);
        const key = `nexiam.omr.capture.recovery.v1:71:${encodeURIComponent(target.workbenchId)}`;
        const value = JSON.parse(storage.getItem(key)!) as Record<string, unknown>;
        value.privateImageBytes = 'must-not-be-consumed';
        storage.setItem(key, JSON.stringify(value));
        expect(readOmrCaptureSessionRecovery(71, target.workbenchId, storage).kind).toBe('corrupt');
        expect(clearOmrCaptureSessionRecovery(71, target.workbenchId, operation, storage)).toBe(false);
    });

    it('clears only the exact resolved operation and reports storage unavailability', () => {
        const storage = new MemoryStorage();
        saveOmrCaptureSessionRecovery(71, target.workbenchId, operation, storage);
        expect(saveOmrCaptureSessionRecovery(71, target.workbenchId, reference, storage)).toBe(true);
        expect(saveOmrCaptureSessionRecovery(71, target.workbenchId, {
            ...reference,
            operationId: 'another-operation',
        }, storage)).toBe(false);
        expect(readOmrCaptureSessionRecovery(71, target.workbenchId, storage)).toEqual({
            kind: 'ready',
            operation: reference,
        });
        expect(clearOmrCaptureSessionRecovery(71, target.workbenchId, {
            operationId: operation.operationId,
            idempotencyKey: 'different-key',
            target,
        }, storage)).toBe(false);
        expect(readOmrCaptureSessionRecovery(71, target.workbenchId, storage).kind).toBe('ready');
        expect(clearOmrCaptureSessionRecovery(71, target.workbenchId, operation, storage)).toBe(true);
        expect(readOmrCaptureSessionRecovery(71, target.workbenchId, storage).kind).toBe('none');
        expect(readOmrCaptureSessionRecovery(71, target.workbenchId, null).kind).toBe('unavailable');
    });
});
