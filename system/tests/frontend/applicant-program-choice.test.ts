// @vitest-environment jsdom
import { cleanup, fireEvent, render, screen } from '@testing-library/svelte';
import { afterEach, describe, expect, it, vi } from 'vitest';

vi.mock('@/Pages/Applicant/ApplicantLayout.svelte', () => ({
  default: (anchor: unknown, { children }: { children?: (anchor: unknown) => void }) => children?.(anchor),
}));
vi.mock('@/Components/kit/Walkthrough/TourTriggerGlyph.svelte', () => ({ default: () => null }));
vi.mock('@/Components/kit/Walkthrough/XiamNpcDialog.svelte', () => ({ default: () => null }));
import ProgramChoice from '../../resources/js/Pages/Applicant/ProgramChoice.svelte';
afterEach(cleanup);

const primary = { program_code: 'BEEd', program_name: 'Bachelor of Elementary Education' };
const alternate = { program_code: 'BSIT', program_name: 'Bachelor of Science in Information Technology', basis: 'guidance_option' };
const base = {
  readiness: { accepted: true, result_released: true, advised: true, selection: { status: 'pending_acknowledgement', source: 'guidance_default', program_code: 'BEEd', acknowledged: false }, enrolling: false, enrolled: false },
  program_pool: { default: primary, selection_program: 'BEEd', selection_status: 'pending_acknowledgement', options: [] },
  actions: { state: 'pending_acknowledgement', can_acknowledge: true, can_choose: false, can_cancel: true },
  tabfm: { status: 'unavailable', matches: [] },
};

describe('applicant program plans', () => {
  it('allows optional acknowledgement without inventing alternative programs and exposes a separate reporting path', async () => {
    const { container } = render(ProgramChoice, { support: base });
    expect(screen.getByRole('button', { name: 'Still my plan' })).toBeTruthy();
    expect(screen.queryByRole('button', { name: /Bachelor of Science in Information Technology/ })).toBeNull();
    await fireEvent.click(container.querySelector('summary')!);
    expect(screen.getByRole('link', { name: 'I enrolled in another program' }).getAttribute('href')).toContain('draft_reason=program_enrolled_elsewhere');
  });

  it('shows only recorded alternatives and preserves cancellation as a separately permitted action', () => {
    render(ProgramChoice, { support: { ...base,
      program_pool: { ...base.program_pool, options: [alternate] },
      actions: { ...base.actions, can_choose: true },
    } });
    expect(screen.getByRole('button', { name: /Bachelor of Science in Information Technology/ })).toBeTruthy();
    expect(screen.getByRole('button', { name: 'Cancel selection' })).toBeTruthy();
    expect(screen.queryByRole('button', { name: /Bachelor of Elementary Education/ })).toBeNull();
  });

  it('keeps permitted transfer options after acknowledgement', () => {
    render(ProgramChoice, { support: { ...base,
      readiness: { ...base.readiness, selection: { ...base.readiness.selection, status: 'active', acknowledged: true } },
      program_pool: { ...base.program_pool, options: [alternate] },
      actions: { ...base.actions, state: 'confirmed', can_acknowledge: false, can_choose: true },
    } });
    expect(screen.queryByRole('button', { name: 'Still my plan' })).toBeNull();
    expect(screen.getByRole('button', { name: /Bachelor of Science in Information Technology/ })).toBeTruthy();
  });

  it('does not offer nonexistent alternatives after a sole recommendation is acknowledged', () => {
    render(ProgramChoice, { support: { ...base,
      readiness: { ...base.readiness, selection: { ...base.readiness.selection, status: 'active', acknowledged: true } },
      actions: { ...base.actions, state: 'confirmed', can_acknowledge: false },
    } });
    expect(screen.queryByRole('button', { name: 'Still my plan' })).toBeNull();
    expect(screen.queryByRole('button', { name: /Bachelor of Science in Information Technology/ })).toBeNull();
    expect(screen.getByRole('button', { name: 'Cancel selection' })).toBeTruthy();
  });

  it.each(['awaiting_recommendation', 'awaiting_release', 'awaiting_advice', 'locked'])('does not expose blocked mutations in %s', (state) => {
    render(ProgramChoice, { support: { ...base,
      actions: { state, can_acknowledge: false, can_choose: false, can_cancel: false },
    } });
    expect(screen.queryByRole('button', { name: 'Still my plan' })).toBeNull();
    expect(screen.queryByRole('button', { name: 'Cancel selection' })).toBeNull();
    expect(screen.queryByRole('button', { name: /Bachelor of Science in Information Technology/ })).toBeNull();
  });

  it('offers a correction report rather than a dead alternative link after confirmed clearance', async () => {
    const { container } = render(ProgramChoice, { support: { ...base,
      actions: { state: 'locked', can_acknowledge: false, can_choose: false, can_cancel: false, clearance_confirmed: true },
    } });
    await fireEvent.click(container.querySelector('summary')!);
    expect(screen.getByRole('link', { name: 'I chose another program' }).getAttribute('href')).toContain('draft_reason=program_changed');
    expect(screen.queryByRole('link', { name: 'Choose another available program' })).toBeNull();
    expect(screen.queryByRole('button', { name: 'Still my plan' })).toBeNull();
  });

  it('renders subtle planned capacity metrics for recorded selection and available options', () => {
    render(ProgramChoice, { support: { ...base,
      readiness: { ...base.readiness, selection: { ...base.readiness.selection, quota: { configured: true, capacity: 100, committed: 72, remaining: 28 } } },
      program_pool: { ...base.program_pool, options: [{ ...alternate, quota: { configured: true, capacity: 80, committed: 45, remaining: 35 } }] },
      actions: { ...base.actions, can_choose: true },
    } });
    expect(screen.getByText('Planned capacity: 72 / 100 reserved (28 remaining)')).toBeTruthy();
    expect(screen.getByText('Planned capacity: 45 / 80 reserved (35 remaining)')).toBeTruthy();
    expect(screen.getByText(/Capacity snapshot only/)).toBeTruthy();
  });
});
