import { describe, expect, it } from 'vitest';
import { resolveTour } from '@/lib/walkthrough/resolver';
import { buildGradingDashboardTour } from '@/lib/walkthrough/tours/assessment-grading-dashboard';

describe('scoring dashboard walkthrough', () => {
    it('routes authorized staff through the visible import action', () => {
        const tour = resolveTour(buildGradingDashboardTour, { canImportScores: true } as never);
        const importStep = tour.steps.find((step) => step.id === 'import-scores');
        const sessionStep = tour.steps.find((step) => step.id === 'session-list');

        expect(tour.steps.map((step) => step.target).filter(Boolean)).toContainEqual({
            dataTour: 'grading-sessions-list',
        });
        expect(importStep?.target).toEqual({ dataTour: 'grading-import-scores' });
        expect(sessionStep?.advance).toEqual({
            kind: 'next',
            destination: { kind: 'step', stepId: 'import-scores' },
        });
    });

    it('skips import guidance for staff without score-import authority', () => {
        const tour = resolveTour(buildGradingDashboardTour, { canImportScores: false } as never);
        const sessionStep = tour.steps.find((step) => step.id === 'session-list');

        expect(tour.steps.some((step) => step.id === 'import-scores')).toBe(false);
        expect(sessionStep?.advance).toEqual({
            kind: 'next',
            destination: { kind: 'step', stepId: 'guidance' },
        });
    });
});
