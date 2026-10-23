// @vitest-environment jsdom

import { cleanup, fireEvent, render, screen, waitFor, within } from '@testing-library/svelte';
import { afterEach, expect, it, vi } from 'vitest';

const actorContext = vi.hoisted(() => ({ capabilities: [] as string[] }));

vi.mock('@inertiajs/svelte', async (importOriginal) => ({
    ...await importOriginal<Record<string, unknown>>(),
    usePage: () => ({ props: { flash: {}, errors: {}, auth: { user: { capabilities: actorContext.capabilities } } } }),
}));
vi.mock('@/Pages/Staff/StaffLayout.svelte', () => ({
    default: (anchor: unknown, { children }: { children?: (anchor: unknown) => void }) => children?.(anchor),
}));
vi.mock('@/Components/kit/Walkthrough/TourTriggerGlyph.svelte', () => ({ default: () => null }));
vi.mock('@/Components/kit/Walkthrough/XiamNpcDialog.svelte', () => ({ default: () => null }));
vi.mock('@/Components/DocumentTemplate/PrintDocumentsSlot.svelte', () => ({ default: () => null }));

import Clearance from '../../resources/js/Pages/Registrar/EnrollmentEligibility.svelte';

afterEach(async () => {
    cleanup();
    await waitFor(() => expect(document.body.style.overflow).not.toBe('hidden'));
    actorContext.capabilities = [];
});

const rows = [
    { id: 1, name: 'Confirmed First', reference_number: 'SYN-001', status: 'accepted', clearance_status: 'confirmed', preferred_program_code: 'BSIT' },
    { id: 2, name: 'Ready Candidate', reference_number: 'SYN-002', status: 'accepted', clearance_status: 'ready', preferred_program_code: 'BSIT' },
    { id: 3, name: 'Confirmed Second', reference_number: 'SYN-003', status: 'accepted', clearance_status: 'confirmed', preferred_program_code: 'BSIT' },
];

it('selects mixed statuses while separating print eligibility and synchronizing table and cards', async () => {
    render(Clearance, { results: rows });
    expect(screen.queryByRole('region', { name: 'Bulk actions' })).toBeNull();
    const table = within(screen.getByRole('table'));
    const first = table.getByRole('checkbox', { name: 'Select applicant Confirmed First' });
    const ready = table.getByRole('checkbox', { name: 'Select applicant Ready Candidate' });
    expect(ready).toHaveProperty('disabled', false);
    await fireEvent.click(first);
    await fireEvent.click(ready);
    expect(screen.getByText('2 selected · 1 eligible for clearance printing · 1 ineligible')).toBeTruthy();
    const firstCard = [...document.querySelectorAll('article')].find((card) => card.textContent?.includes('Confirmed First'))!;
    expect(within(firstCard as HTMLElement).getByRole('checkbox')).toHaveProperty('checked', true);
    expect(screen.getByRole('checkbox', { name: 'Select all on page (3)' })).toHaveProperty('indeterminate', true);

    await fireEvent.click(screen.getByRole('checkbox', { name: 'Select all on page (3)' }));
    expect(screen.getByText('3 selected · 2 eligible for clearance printing · 1 ineligible')).toBeTruthy();
    expect(ready).toHaveProperty('checked', true);
    await fireEvent.click(screen.getByRole('button', { name: 'Select confirmed only' }));
    expect(screen.getByText('2 selected · 2 eligible for clearance printing · 0 ineligible')).toBeTruthy();
    expect(ready).toHaveProperty('checked', false);
    await fireEvent.click(screen.getByRole('button', { name: 'Clear', exact: true }));
    expect(first).toHaveProperty('checked', false);
    expect(screen.queryByRole('region', { name: 'Bulk actions' })).toBeNull();
});

it('allows an all-incomplete page to be selected but disables clearance printing', async () => {
    render(Clearance, { results: [rows[1]] });
    await fireEvent.click(screen.getByRole('checkbox', { name: 'Select all on page (1)' }));
    expect(screen.getByText('1 selected · 0 eligible for clearance printing · 1 ineligible')).toBeTruthy();
    expect(screen.getByRole('button', { name: 'Print confirmed clearances (0)' })).toHaveProperty('disabled', true);
    expect(screen.getByRole('button', { name: 'Select confirmed only' })).toHaveProperty('disabled', true);
    await fireEvent.click(screen.getByRole('button', { name: 'Deselect page' }));
    expect(screen.queryByRole('region', { name: 'Bulk actions' })).toBeNull();
});

it('prunes disappeared selection and recomputes eligibility when a selected record changes status', async () => {
    const view = render(Clearance, { results: rows });
    await fireEvent.click(screen.getByRole('checkbox', { name: 'Select all on page (3)' }));
    await view.rerender({ results: [{ ...rows[1], clearance_status: 'confirmed' }, { ...rows[2], clearance_status: 'revoked' }] });
    await waitFor(() => expect(screen.getByText('2 selected · 1 eligible for clearance printing · 1 ineligible')).toBeTruthy());
    const table = within(screen.getByRole('table'));
    expect(table.getByRole('checkbox', { name: 'Select applicant Ready Candidate' })).toHaveProperty('checked', true);
    expect(table.getByRole('checkbox', { name: 'Select applicant Confirmed Second' })).toHaveProperty('checked', true);
    expect(screen.getByRole('checkbox', { name: 'Select all on page (2)' })).toHaveProperty('checked', true);
});

it('does not treat retained handoff or legacy enrollment history as current clearance', () => {
    render(Clearance, {
        page_state: 'card',
        card: {
            id: 8,
            name: 'Reopened Candidate',
            reference_number: 'SYN-008',
            version: 4,
            eligible: false,
            facts: { accepted: true },
            clearance_readiness: { is_ready: false, blocking_reasons: ['Mandatory document verification is outstanding.'] },
            latest_clearance: { status: 'revoked' },
            historical_latest_sheet_handoff: { receipt_number: 'SHEET-HISTORICAL', issued_by_name: 'Synthetic Registrar', issued_at: '2026-10-01T09:00:00Z' },
            enrollment_stamps: [{ id: 6, version: 1, state: 'enrolled', created_at: '2026-09-01T09:00:00Z' }],
        },
    });
    expect(screen.getByRole('button', { name: 'Confirm Admission Clearance' })).toHaveProperty('disabled', true);
    expect(screen.queryByRole('button', { name: 'Record Physical Sheet Handoff' })).toBeNull();
    expect(screen.queryByRole('button', { name: 'Revoke / Reopen Clearance' })).toBeNull();
    expect(screen.getByText('SHEET-HISTORICAL')).toBeTruthy();
});

it('finishes confirmed clearance without sheet issue and retains reasoned reopening', async () => {
    actorContext.capabilities = ['registrar.admission.clearance.reopen'];
    render(Clearance, {
        page_state: 'card',
        card: {
            id: 9, name: 'Cleared Candidate', reference_number: 'SYN-009', version: 5,
            eligible: true, facts: { accepted: true },
            clearance_readiness: { is_ready: true, blocking_reasons: [] },
            latest_clearance: {
                status: 'confirmed', receipt_number: 'CLR-009', program_code: 'BPEd',
                confirmed_by_name: 'Registrar', confirmed_at: '2026-10-19T05:19:00Z',
            },
        },
    });
    expect(screen.getByText(/Admission processing in NEXIAM is complete/)).toBeTruthy();
    expect(screen.queryByRole('button', { name: 'Confirm Admission Clearance' })).toBeNull();
    expect(screen.queryByRole('button', { name: 'Record Physical Sheet Handoff' })).toBeNull();
    expect(screen.queryByText(/No physical enrollment sheet handoff/)).toBeNull();
    await fireEvent.click(screen.getByRole('button', { name: 'Revoke / Reopen Clearance' }));
    expect(screen.getByRole('dialog')).toBeTruthy();
    expect(screen.getByLabelText('Revocation Reason')).toBeTruthy();
});

it.each([
    { published: false, advised: false, status: 'Pending' },
    { published: true, advised: false, status: 'Pending' },
    { published: false, advised: true, status: 'Released' },
    { published: true, advised: true, status: 'Released' },
])('shows the Release Day decision as $status with publication=$published and advised=$advised', ({ published, advised, status }) => {
    render(Clearance, {
        page_state: 'card',
        card: {
            id: 28,
            name: 'Synthetic Release Candidate',
            reference_number: 'SYN-028',
            version: 1,
            eligible: false,
            facts: { accepted: true, grading_finalized: true, result_released: published, advised },
            clearance_readiness: { is_ready: false, blocking_reasons: [] },
        },
    });

    const milestones = document.querySelector('[data-tour="pre-clearance-milestones"]') as HTMLElement;
    const releaseRow = within(milestones).getByText('Result released').closest('li')!;
    expect(within(releaseRow).getByText(status)).toBeTruthy();
    expect(within(milestones).queryByText('Consultation advised')).toBeNull();
    expect(within(releaseRow).queryByText('Missing')).toBeNull();
});
