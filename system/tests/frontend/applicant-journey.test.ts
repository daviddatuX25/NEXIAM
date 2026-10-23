import { readFileSync } from 'node:fs';
import { describe, expect, it } from 'vitest';
import {
  buildJourneyPositions,
  JOURNEY_STATUS_APPEARANCE,
  resolveJourneyStatusAppearance,
} from '../../resources/js/lib/applicant-journey';

describe('applicant journey presentation', () => {
  it('defines appearance for all five projected statuses and safely resolves unknown values', () => {
    expect(Object.keys(JOURNEY_STATUS_APPEARANCE).sort()).toEqual(['action', 'auto', 'current', 'done', 'upcoming']);
    expect(resolveJourneyStatusAppearance('current').pulse).toBe(true);
    expect(resolveJourneyStatusAppearance('action').label).toBe('Action needed');
    expect(resolveJourneyStatusAppearance('unexpected')).toEqual(JOURNEY_STATUS_APPEARANCE.upcoming);
  });

  it('computes positions across phase boundaries and handles empty pipelines', () => {
    const positions = buildJourneyPositions([
      { steps: [{ id: 'application' }] },
      { steps: [{ id: 'exam' }, { id: 'release' }] },
    ]);

    expect(positions.totalSteps).toBe(3);
    expect(positions.firstStepId).toBe('application');
    expect(positions.lastStepId).toBe('release');
    expect(positions.positions.exam).toMatchObject({ index: 1, isFirst: false, isLast: false });
    expect(positions.positions.release).toMatchObject({ index: 2, isFirst: false, isLast: true });
    expect(positions.isLast('exam')).toBe(false);
    expect(positions.isLast('release')).toBe(true);
    expect(positions.isLast('unknown')).toBe(false);
    expect(buildJourneyPositions([])).toMatchObject({ totalSteps: 0, firstStepId: null, lastStepId: null });
  });

  it('retains the keyed timeline and accessible animated journey nodes', () => {
    const portal = readFileSync(new URL('../../resources/js/Pages/Applicant/Portal.svelte', import.meta.url), 'utf8');
    const node = readFileSync(new URL('../../resources/js/Components/kit/ApplicantJourneyNode.svelte', import.meta.url), 'utf8');

    expect(portal).toContain('{#each pipeline as phase (phase.id)}');
    expect(portal).toContain('{#each phase.steps as step (step.id)}');
    expect(portal).toContain('{phase.label}');
    expect(portal).not.toContain('Phase {phaseIndex + 1}');
    expect(portal).toContain('journey-flow');
    expect(portal).toContain('@keyframes flowDown');
    expect(portal).toContain('formatStageDate(step.at)');
    expect(portal).toContain('justify-between');
    expect(node).toContain('size-10');
    expect(node).toContain('size-5');
    expect(node).toContain('sr-only');
    expect(node).toContain('journey-node-pulse');
    expect(node).toContain('journey-node-breathe');
    expect(node).toContain('@keyframes pulseRing');
    expect(node).toContain('@keyframes breathePending');
    expect(node).toContain('@media (prefers-reduced-motion: reduce)');
  });
});
