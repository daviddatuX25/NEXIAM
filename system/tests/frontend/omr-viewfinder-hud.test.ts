// @vitest-environment jsdom

import { afterEach, describe, expect, it } from 'vitest';
import { cleanup, render, screen } from '@testing-library/svelte';
import OmrViewfinderHud from '../../resources/js/Components/Omr/Workbench/OmrViewfinderHud.svelte';
import type { ViewfinderResult } from '../../resources/js/lib/omrViewfinderWorker';

afterEach(cleanup);

const baseResult: ViewfinderResult = {
    type: 'analysis',
    generation: 1,
    frameId: 1,
    quad: [
        { x: 0.2, y: 0.15 },
        { x: 0.8, y: 0.15 },
        { x: 0.8, y: 0.85 },
        { x: 0.2, y: 0.85 },
    ],
    gates: {
        in_bounds: true,
        angle_ok: true,
        steady_ok: true,
        lighting_ok: true,
    },
    geometry: {
        observedAspectRatio: 210 / 297,
        expectedAspectRatio: 210 / 297,
        maxEdgeTiltRadians: 0,
    },
};

describe('OMR viewfinder HUD', () => {
    it('separates passing quality checks from a lifecycle capture hold', () => {
        render(OmrViewfinderHud, {
            props: {
                result: baseResult,
                captureBlockedReason: 'Reconcile the pending capture before retrying.',
                lockProgress: 1,
            },
        });

        const status = screen.getByRole('status').textContent ?? '';
        expect(status).toMatch(/capture is paused/i);
        expect(status).not.toContain('Auto dwell');
        expect(
            document.querySelector('[data-omr-viewfinder-hud]')?.getAttribute('data-capture-blocked'),
        ).toBe('true');
        expect(screen.getByText('Angle: Ready')).toBeTruthy();
    });

    it('renders evidence-based portrait advice for reciprocal sheet geometry', () => {
        const sideways: ViewfinderResult = {
            ...baseResult,
            gates: { ...baseResult.gates, angle_ok: false },
            geometry: {
                observedAspectRatio: 1.42,
                expectedAspectRatio: 210 / 297,
                maxEdgeTiltRadians: 0,
            },
        };

        render(OmrViewfinderHud, { props: { result: sideways } });

        expect(screen.getByText(/Rotate the sheet to portrait/)).toBeTruthy();
        expect(screen.getByText('Angle: Adjust')).toBeTruthy();
    });
});
