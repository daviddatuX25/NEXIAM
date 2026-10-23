// @vitest-environment jsdom

import { cleanup, fireEvent, render, screen } from '@testing-library/svelte';
import { afterEach, expect, it, vi } from 'vitest';

vi.mock('@/Pages/Staff/StaffLayout.svelte', () => ({
    default: (anchor: unknown, { children }: { children?: (anchor: unknown) => void }) => children?.(anchor),
}));
vi.mock('@/Pages/Setup/SeatingArrangements/SeatingArrangementPreview.svelte', () => ({ default: () => null }));

import Create from '../../resources/js/Pages/Examination/Create.svelte';
import Edit from '../../resources/js/Pages/Examination/Edit.svelte';

afterEach(cleanup);

const kit = {
    id: 7, label: 'Synthetic Kit v1', status: 'candidate',
    booklet_label: 'Intrinsic Booklet v2', answer_sheet_label: 'Independent Sheet v3', profile_label: 'Synthetic Test Setup',
};
const session = {
    id: 2, version: 4, academic_cycle_id: 1, room_id: 1, code: 'SYN-DRAFT',
    date: '2026-11-15', start_time: '08:00', end_time: '09:30',
    seating_mode: 'simple_capacity', seating_arrangement_id: null, test_kit_version_id: null,
};
const room = { id: 1, name: 'Synthetic room', capacity: 20 };

it('keeps kit choice independent from simple and advanced seating', async () => {
    render(Create, { test_kits: [kit], enable_seating_arrangements: true, rooms: [room] });
    const select = screen.getByLabelText('Test Kit');
    expect(select).toHaveProperty('value', '');
    expect(select).toHaveProperty('required', false);
    await fireEvent.change(select, { target: { value: '7' } });
    expect(screen.getByText('Intrinsic Booklet v2')).toBeTruthy();
    expect(screen.getByText('Independent Sheet v3')).toBeTruthy();
    expect(screen.queryByLabelText('Seating arrangement')).toBeNull();
    await fireEvent.click(screen.getByRole('switch', { name: 'Assign physical seats' }));
    expect(screen.getByLabelText('Seating arrangement')).toBeTruthy();
    expect(select).toHaveProperty('value', '7');
    await fireEvent.click(screen.getByRole('switch', { name: 'Assign physical seats' }));
    expect(screen.queryByLabelText('Seating arrangement')).toBeNull();
    expect(select).toHaveProperty('value', '7');
});

it('does not silently attach the only available kit to an existing unbound draft', () => {
    render(Edit, { session, test_kits: [kit], rooms: [room] });
    expect(screen.getByLabelText('Test Kit')).toHaveProperty('value', '');
    expect(screen.queryByLabelText('Finalized Booklet')).toBeNull();
    expect(screen.queryByText('Intrinsic Booklet v2')).toBeNull();
});

it('retains an existing unavailable kit selection instead of silently dropping it', () => {
    render(Edit, { session: { ...session, test_kit_version_id: 9 }, test_kits: [kit], rooms: [room] });
    expect(screen.getByLabelText('Test Kit')).toHaveProperty('value', '9');
    expect(screen.getByRole('option', { name: 'Current Test Kit · unavailable for new selection' })).toHaveProperty('selected', true);
});

it('offers an unattached draft instead of an obsolete Booklet selector when no kit exists', () => {
    render(Create, { test_kits: [] });
    expect(screen.getByLabelText('Test Kit')).toHaveProperty('value', '');
    expect(screen.queryByLabelText('Finalized Booklet')).toBeNull();
    expect(new URL((screen.getByRole('link', { name: 'Open Test Kits' }) as HTMLAnchorElement).href).pathname).toBe('/setup/exam-materials/test-kits');
});

it('discloses contextual InfoPopover guidance on seating and scheduling fields while keeping Test Kit clean', async () => {
    render(Create, { test_kits: [kit], enable_seating_arrangements: true, rooms: [room] });
    expect(screen.getByText(/New sessions save as Drafts until published/i)).toBeTruthy();
    expect(screen.getByText(/Unique session identifier within the selected academic year/i)).toBeTruthy();

    const infoButtons = screen.getAllByRole('button', { name: 'More information' });
    expect(infoButtons.length).toBeGreaterThanOrEqual(3);

    await fireEvent.click(infoButtons[0]);
    expect(screen.getByRole('tooltip')).toBeTruthy();
    expect(screen.getByText('Simple capacity vs. Physical seats')).toBeTruthy();

    await fireEvent.keyDown(window, { key: 'Escape' });
    expect(screen.queryByRole('tooltip')).toBeNull();
});
