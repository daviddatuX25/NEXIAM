// @vitest-environment jsdom

import { cleanup, fireEvent, render, screen, waitFor } from '@testing-library/svelte';
import { afterEach, expect, it, vi } from 'vitest';

const requests = vi.hoisted(() => ({ post: vi.fn() }));
vi.mock('@inertiajs/svelte', async (importOriginal) => ({
    ...await importOriginal<Record<string, unknown>>(),
    usePage: () => ({ props: { errors: {}, flash: {} } }),
    router: { post: requests.post },
}));
vi.mock('@/Pages/Applicant/ApplicantLayout.svelte', () => ({
    default: (anchor: unknown, { children }: { children?: (anchor: unknown) => void }) => children?.(anchor),
}));

import ReleaseSchedule from '../../resources/js/Pages/Applicant/ReleaseSchedule.svelte';

afterEach(async () => {
    cleanup();
    await waitFor(() => expect(document.body.style.overflow).not.toBe('hidden'));
    requests.post.mockReset();
});

it('retains a tentative appointment but withholds switching until its individual scoring blocker is resolved', async () => {
    const props = {
        page_state: 'assigned_or_reserved',
        attempt: { id: 11, reference_code: 'SYN-HELD-11', advised: false },
        assignment: {
            id: 5, version: 1, source: 'applicant_reserved',
            scoring_blocker: 'The individual score needs correction; the existing appointment remains held.',
            session: { id: 7, published: false, scheduled_at: '2026-10-25T08:00:00Z', room: 'Synthetic appointment room' },
        },
        slot_switch: {
            target_calendar_days: [{
                date: '2026-10-26',
                slots: [{ id: 8, date: '2026-10-26', scheduled_at: '2026-10-26T08:00:00Z', start_time: '08:00', end_time: '09:30', code: 'SYN-ALTERNATIVE', room: 'Synthetic alternative room', remaining: 2, full: false }],
            }],
        },
    };
    const view = render(ReleaseSchedule, props);
    expect(screen.getByText('Tentative')).toBeTruthy();
    expect(screen.getByText('Synthetic appointment room')).toBeTruthy();
    expect(screen.getByText(props.assignment.scoring_blocker)).toBeTruthy();
    expect(screen.queryByRole('button', { name: 'Switch Slot', exact: true })).toBeNull();
    expect(requests.post).not.toHaveBeenCalled();

    await view.rerender({ ...props, assignment: { ...props.assignment, scoring_blocker: null } });
    await fireEvent.click(await screen.findByRole('button', { name: 'Switch Slot', exact: true }));
    expect(screen.getByRole('button', { name: 'Confirm switch', exact: true })).toBeTruthy();
    expect(requests.post).not.toHaveBeenCalled();
});
