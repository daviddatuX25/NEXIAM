// @vitest-environment jsdom
import { describe, expect, it } from 'vitest';
import {
    buildOmrCaptureTargetKey,
    canCaptureOmrTarget,
    canRetryOmrFailedUpload,
    freezeOmrCaptureTargetContext,
    isOmrCaptureAttemptCurrent,
    isOmrCaptureAvailable,
    isOmrFileUploadAvailable,
    usesAutomaticOmrCapture,
    type OmrCaptureAvailability,
    type OmrCaptureTargetIdentity,
    shouldRunOmrCameraSource,
} from '../../resources/js/lib/omrCameraAcquisition';

function target(
    overrides: Partial<OmrCaptureTargetIdentity> = {},
) {
    const identity: OmrCaptureTargetIdentity = {
        workbenchId: 'production:7:9',
        clientCaptureId: 'capture-1',
        pageNo: 1,
        captureRevision: 4,
        instanceId: 'instance-2',
        sheetId: 'sheet-3',
        ...overrides,
    };
    return freezeOmrCaptureTargetContext(identity);
}

const available: OmrCaptureAvailability = {
    cameraEnabled: true,
    visible: true,
    disabled: false,
    busy: false,
    paused: false,
    settingsOpen: false,
};

describe('shared OMR camera acquisition contract', () => {
    it('keys every frozen target dimension and rejects stale source generations', () => {
        const identity: OmrCaptureTargetIdentity = {
            workbenchId: 'production:7:9',
            clientCaptureId: 'capture-1',
            pageNo: 1,
            captureRevision: 4,
            instanceId: 'instance-2',
            sheetId: 'sheet-3',
        };
        const capturedTarget = freezeOmrCaptureTargetContext(identity);
        expect(capturedTarget).not.toBeNull();
        expect(capturedTarget).toMatchObject({
            ...identity,
            targetKey: JSON.stringify([
                identity.workbenchId,
                identity.clientCaptureId,
                identity.pageNo,
                identity.captureRevision,
                identity.instanceId,
                identity.sheetId,
            ]),
        });
        expect(Object.isFrozen(capturedTarget)).toBe(true);

        const changedTargets: OmrCaptureTargetIdentity[] = [
            { ...identity, workbenchId: 'production:8:9' },
            { ...identity, clientCaptureId: 'capture-2' },
            { ...identity, pageNo: 2 },
            { ...identity, captureRevision: 5 },
            { ...identity, instanceId: 'instance-3' },
            { ...identity, sheetId: 'sheet-4' },
        ];
        for (const changedTarget of changedTargets) {
            expect(buildOmrCaptureTargetKey(changedTarget)).not.toBe(
                capturedTarget?.targetKey,
            );
        }

        if (!capturedTarget) {
            throw new Error('A valid target must freeze successfully.');
        }
        const attempt = Object.freeze({
            target: capturedTarget,
            sourceGeneration: 9,
            requiresSheetSwap: true,
        });
        expect(isOmrCaptureAttemptCurrent(attempt, target(), 9)).toBe(true);
        expect(isOmrCaptureAttemptCurrent(attempt, target({ pageNo: 2 }), 9)).toBe(false);
        expect(isOmrCaptureAttemptCurrent(attempt, capturedTarget, 10)).toBe(false);
        expect(isOmrCaptureAttemptCurrent(attempt, null, 9)).toBe(false);
    });

    it('blocks unavailable, paused, hidden, disabled, busy, settings-open, and missing-target acquisition', () => {
        const frozenTarget = target();
        expect(isOmrCaptureAvailable(available, frozenTarget)).toBe(true);
        expect(isOmrCaptureAvailable(available, null)).toBe(false);
        expect(
            isOmrFileUploadAvailable(
                { ...available, cameraEnabled: false },
                frozenTarget,
            ),
        ).toBe(true);

        const blockedFlags: Array<keyof OmrCaptureAvailability> = [
            'cameraEnabled',
            'visible',
            'disabled',
            'busy',
            'paused',
            'settingsOpen',
        ];
        for (const flag of blockedFlags) {
            const value =
                flag === 'disabled' ||
                flag === 'busy' ||
                flag === 'paused' ||
                flag === 'settingsOpen';
            expect(
                isOmrCaptureAvailable(
                    { ...available, [flag]: value },
                    frozenTarget,
                ),
            ).toBe(false);
        }
        expect(target({ workbenchId: ' ' })).toBeNull();
        expect(target({ pageNo: 0 })).toBeNull();
    });

    it('keeps the camera source alive through command-busy and capture-disabled states', () => {
        const frozenTarget = target();
        expect(
            isOmrCaptureAvailable(
                { ...available, busy: true },
                frozenTarget,
            ),
        ).toBe(false);
        expect(
            isOmrCaptureAvailable(
                { ...available, disabled: true },
                frozenTarget,
            ),
        ).toBe(false);
        expect(shouldRunOmrCameraSource(true, true, false)).toBe(true);
        expect(shouldRunOmrCameraSource(false, true, false)).toBe(false);
        expect(shouldRunOmrCameraSource(true, false, false)).toBe(false);
        expect(shouldRunOmrCameraSource(true, true, true)).toBe(false);
    });

    it('dispatches batch subtypes without turning manual QR into auto-steady capture', () => {
        expect(usesAutomaticOmrCapture('manual', 'auto_steady')).toBe(false);
        expect(usesAutomaticOmrCapture('auto', 'manual_qr')).toBe(true);
        expect(usesAutomaticOmrCapture('batch', 'auto_steady')).toBe(true);
        expect(usesAutomaticOmrCapture('batch', 'manual_qr')).toBe(false);
    });

    it('holds camera capture until a physical sheet swap, even after target progression', () => {
        const acceptedTarget = target();
        const nextTarget = target({ pageNo: 2, captureRevision: 5 });
        if (!acceptedTarget || !nextTarget) {
            throw new Error('Test targets must freeze successfully.');
        }

        expect(
            canCaptureOmrTarget(
                acceptedTarget.targetKey,
                nextTarget,
                true,
            ),
        ).toBe(false);
        expect(
            canCaptureOmrTarget(
                acceptedTarget.targetKey,
                nextTarget,
                false,
            ),
        ).toBe(true);
        expect(
            canCaptureOmrTarget(
                acceptedTarget.targetKey,
                acceptedTarget,
                false,
            ),
        ).toBe(false);
        expect(
            canCaptureOmrTarget(
                null,
                acceptedTarget,
                false,
            ),
        ).toBe(true);
    });

    it('keeps failed uploads retryable only against their original target and crop policy', () => {
        const originalTarget = target();
        const nextTarget = target({
            clientCaptureId: 'capture-2',
            pageNo: 2,
            captureRevision: 5,
        });
        if (!originalTarget || !nextTarget) {
            throw new Error('Test targets must freeze successfully.');
        }
        const failed: OmrFailedUpload = {
            id: 1,
            file: new File(['scan'], 'page-1.png', { type: 'image/png' }),
            target: originalTarget,
            cropPolicy: 'keep_original',
            quad: null,
            requiresSheetSwap: true,
        };

        expect(canRetryOmrFailedUpload(failed, originalTarget, true)).toBe(true);
        expect(canRetryOmrFailedUpload(failed, nextTarget, true)).toBe(false);
        expect(canRetryOmrFailedUpload(failed, originalTarget, false)).toBe(false);
        expect(failed.cropPolicy).toBe('keep_original');
        expect(failed.target).toBe(originalTarget);
        expect(failed.requiresSheetSwap).toBe(true);
    });
});
