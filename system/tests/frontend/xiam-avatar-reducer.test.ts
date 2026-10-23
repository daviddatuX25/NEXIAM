import { describe, expect, it } from 'vitest';
import {
    completeXiamAvatar,
    INITIAL_XIAM_AVATAR_STATE,
    reduceXiamAvatar,
} from '../../resources/js/lib/xiam-avatar-reducer';

describe('Xiam avatar reducer', () => {
    it('drops lower-priority events and accepts higher-priority overrides', () => {
        const celebrating = reduceXiamAvatar(INITIAL_XIAM_AVATAR_STATE, {
            emotion: 'celebrating',
            generation: 4,
        });

        expect(
            reduceXiamAvatar(celebrating, { emotion: 'hi', generation: 5 }),
        ).toEqual(celebrating);

        expect(
            reduceXiamAvatar(celebrating, { emotion: 'shock', generation: 5 }),
        ).toMatchObject({
            emotion: 'shock',
            generation: 5,
            priority: 60,
        });
    });

    it('preserves its monotonic generation watermark when a burst completes', () => {
        const active = reduceXiamAvatar(INITIAL_XIAM_AVATAR_STATE, {
            emotion: 'celebrating',
            generation: 4,
        });
        const completed = completeXiamAvatar(active, active.generation);

        expect(completed).toMatchObject({
            emotion: 'idle',
            generation: 4,
            priority: 0,
        });
        expect(
            reduceXiamAvatar(completed, { emotion: 'hi' }).generation,
        ).toBe(5);
    });

    it('returns transient proposal or stream bursts like thumbs-up back to idle on completion', () => {
        const proposalReact = reduceXiamAvatar(INITIAL_XIAM_AVATAR_STATE, {
            emotion: 'thumbs-up',
            generation: 1,
            mood: 'thumbs-up',
        });
        const completed = completeXiamAvatar(proposalReact, proposalReact.generation);

        expect(completed).toMatchObject({
            emotion: 'idle',
            generation: 1,
            priority: 0,
            mood: 'idle',
        });
    });

    it('keeps a concerned posture after a burst expires during an incident', () => {
        const incident = reduceXiamAvatar(INITIAL_XIAM_AVATAR_STATE, {
            emotion: 'shock',
            generation: 2,
            mood: 'sad',
            incident: true,
        });
        const completed = completeXiamAvatar(incident, incident.generation);

        expect(completed).toMatchObject({
            emotion: 'sad',
            generation: 2,
            incidentActive: true,
            mood: 'sad',
        });
        expect(completed.emotion).not.toBe('idle');

        const resolved = reduceXiamAvatar(completed, {
            emotion: 'thinking',
            resolveIncident: true,
        });

        expect(resolved.incidentActive).toBe(false);
        expect(
            completeXiamAvatar(resolved, resolved.generation).emotion,
        ).toBe('sad');
    });

    it('tracks concurrent tools until all tools end, then returns to mood', () => {
        const firstTool = reduceXiamAvatar(INITIAL_XIAM_AVATAR_STATE, {
            emotion: 'thinking',
            toolStart: 'document-search',
            mood: 'talking-calm',
        });
        const secondTool = reduceXiamAvatar(firstTool, {
            emotion: 'thinking',
            toolStart: 'document-details',
        });
        const oneToolRemaining = reduceXiamAvatar(secondTool, {
            emotion: 'idle',
            toolEnd: 'document-search',
        });

        expect(oneToolRemaining).toMatchObject({
            emotion: 'searching-paper',
            activeTools: ['document-details'],
            mood: 'talking-calm',
        });

        const allToolsEnded = reduceXiamAvatar(oneToolRemaining, {
            emotion: 'idle',
            toolEnd: 'document-details',
        });

        expect(allToolsEnded).toMatchObject({
            emotion: 'talking-calm',
            mood: 'talking-calm',
        });
        expect(allToolsEnded).not.toHaveProperty('activeTools');
    });

    it('rejects invalid and untrusted emotion strings without changing state', () => {
        const state = reduceXiamAvatar(INITIAL_XIAM_AVATAR_STATE, {
            emotion: 'idle',
            generation: 2,
        });

        expect(
            reduceXiamAvatar(state, {
                emotion: 'constructor',
                generation: 3,
                toolStart: 'document-search',
            }),
        ).toBe(state);
        expect(
            reduceXiamAvatar(state, {
                emotion: '__proto__',
                incident: true,
            }),
        ).toBe(state);
    });
});
