// @vitest-environment jsdom
import { cleanup, fireEvent, render, screen } from '@testing-library/svelte';
import { afterEach, describe, expect, it, vi } from 'vitest';

vi.mock('@/Pages/Applicant/ApplicantLayout.svelte', () => ({
  default: (anchor: unknown, { children }: { children?: (anchor: unknown) => void }) => children?.(anchor),
}));
import ReleaseDay from '../../resources/js/Pages/Applicant/ReleaseDay.svelte';
afterEach(() => cleanup());

function linkPath(name: string): string {
  return new URL(screen.getByRole('link', { name }).getAttribute('href')!, window.location.origin).pathname;
}

const baseProjection = {
  appointment: null,
  completion: { completed: false, completed_at: null },
  decision: { state: 'unrecorded', recorded_at: null },
  follow_ups: [],
  result: { available: false, review_available: false, href: null, unavailable_reason: 'Your result is not yet released.' },
  program_choice: { available: true, href: '/applicant/program-choice', unavailable_reason: null },
  online_encounter: null,
};

describe('Release Day page', () => {
  it('keeps held results reviewable without offering a download', () => {
    render(ReleaseDay, { props: { page_state: 'ready', release_day: {
      ...baseProjection,
      result: { available: false, review_available: true, href: '/applicant/results', unavailable_reason: 'Your result is not yet released.' },
    } } });
    expect(linkPath('Review My Results')).toBe('/applicant/results');
    expect(screen.queryByRole('link', { name: /download/i })).toBeNull();
    expect(linkPath('Review program plans')).toBe('/applicant/program-choice');
  });

  it('offers released-result and online-join links before a decision is complete', () => {
    render(ReleaseDay, { props: { page_state: 'ready', release_day: {
      ...baseProjection,
      result: { available: true, review_available: true, href: '/applicant/results', unavailable_reason: null },
      online_encounter: {
        status: 'invited', href: '/consultation/encounters/24', join_available: true, unavailable_reason: null,
        access_starts_at: '2026-10-14T09:00:00Z', access_ends_at: '2026-10-14T09:30:00Z',
      },
    } } });
    expect(linkPath('Review My Results')).toBe('/applicant/results');
    expect(linkPath('Join online encounter')).toBe('/consultation/encounters/24');
  });

  it('retains historical appointment details without making them the current task', async () => {
    const { container } = render(ReleaseDay, { props: { page_state: 'ready', release_day: {
      ...baseProjection,
      appointment: { status: 'consulted', scheduled_at: '2026-10-14T09:30:00Z', room: 'Registrar Office' },
      completion: { completed: true, completed_at: '2026-10-14T10:00:00Z' },
    } } });
    const history = container.querySelector('details');
    expect(history?.open).toBe(false);
    await fireEvent.click(container.querySelector('summary')!);
    expect(screen.getByText('Registrar Office')).toBeTruthy();
    expect(container.querySelector('time')?.getAttribute('datetime')).toBe('2026-10-14T10:00:00Z');
    expect(linkPath('View admission clearance')).toBe('/applicant/admission-clearance');
  });

  it('does not expose an online-join link when its current access is unavailable', () => {
    render(ReleaseDay, { props: { page_state: 'ready', release_day: {
      ...baseProjection,
      online_encounter: {
        status: 'in_progress', href: null, join_available: false,
        unavailable_reason: 'The online encounter window is not currently open.',
        access_starts_at: '2026-10-14T09:00:00Z', access_ends_at: '2026-10-14T09:30:00Z',
      },
    } } });
    expect(screen.queryByRole('link', { name: 'Join online encounter' })).toBeNull();
  });

  it('does not offer wayfinding for a review without a requested appearance', () => {
    render(ReleaseDay, { props: { page_state: 'ready', release_day: {
      ...baseProjection,
      follow_ups: [{ status: 'review_pending', program: 'BSIT', office: null, instruction_recorded: false }],
    } } });
    expect(screen.queryByRole('button', { name: 'Find department office' })).toBeNull();
  });

  it('offers only configured mapped offices among multiple required follow-ups', () => {
    render(ReleaseDay, { props: { page_state: 'ready', release_day: {
      ...baseProjection,
      follow_ups: [
        { status: 'review_pending', program: 'BSIT', office: null, instruction_recorded: false },
        { status: 'appearance_requested', program: 'BSEd', office: { id: 42, name: 'CTE Office', building: null, floor: null, coordinates_available: true }, instruction_recorded: false },
        { status: 'appearance_requested', program: 'BSBA', office: { id: 43, name: 'Unmapped Office', building: null, floor: null, coordinates_available: false }, instruction_recorded: false },
      ],
    } } });
    expect(screen.getByText('BSIT')).toBeTruthy();
    expect(screen.getByText('BSEd')).toBeTruthy();
    expect(screen.getByRole('button', { name: 'Find department office' })).toBeTruthy();
    expect(screen.getByText('Unmapped Office')).toBeTruthy();
  });
});
