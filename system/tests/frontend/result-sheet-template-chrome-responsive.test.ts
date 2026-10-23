import { describe, expect, it, vi } from 'vitest';
import {
    createStudioStageHost,
    normalizeStudioStageRegistration,
    validateStudioStageRegistration,
} from '../../resources/js/lib/studioContracts.js';


const stageCallback = vi.fn();
const validRegistration = {
    currentId: 'design-stage',
    descriptors: [
        {
            id: 'setup-stage',
            label: 'Setup',
            description: 'Format and page',
            activate: stageCallback,
        },
        {
            id: 'design-stage',
            label: 'Design',
            description: 'Build and style',
            activate: stageCallback,
        },
        {
            id: 'verify-stage',
            label: 'Verify',
            description: 'PDF and save',
            activate: stageCallback,
        },
    ],
};

describe('Studio stage registration contract', () => {

    it('rejects malformed opaque stage registrations', () => {
        expect(validateStudioStageRegistration(validRegistration)).toBe(true);
        expect(
            normalizeStudioStageRegistration(
                validRegistration,
            )?.descriptors.map(({ id }) => id),
        ).toEqual(['setup-stage', 'design-stage', 'verify-stage']);
        expect(
            normalizeStudioStageRegistration({
                ...validRegistration,
                currentId: 'missing',
            }),
        ).toBeNull();
        expect(
            normalizeStudioStageRegistration({
                ...validRegistration,
                descriptors: [
                    validRegistration.descriptors[0],
                    { ...validRegistration.descriptors[1], id: 'setup-stage' },
                ],
            }),
        ).toBeNull();
        expect(
            normalizeStudioStageRegistration({
                ...validRegistration,
                descriptors: [
                    { ...validRegistration.descriptors[0], number: 1 },
                ],
            }),
        ).toBeNull();
        expect(
            normalizeStudioStageRegistration({
                ...validRegistration,
                descriptors: [{ id: 'setup' }],
            }),
        ).toBeNull();
    });

    it('preserves opaque callback ownership and stale host cleanup', () => {
        const host = createStudioStageHost();
        const firstToken = host.register(validRegistration);
        const secondToken = host.register({
            ...validRegistration,
            currentId: 'verify-stage',
        });
        expect(firstToken).not.toBeNull();
        expect(secondToken).not.toBeNull();
        expect(
            host.update(firstToken, {
                ...validRegistration,
                currentId: 'setup-stage',
            }),
        ).toBe(false);
        expect(host.unregister(firstToken)).toBe(false);
        expect(host.get()?.currentId).toBe('verify-stage');
        host.get()?.descriptors[0]?.activate();
        expect(stageCallback).toHaveBeenCalled();
        expect(host.unregister(secondToken)).toBe(true);
        expect(host.get()).toBeNull();
    });
});
