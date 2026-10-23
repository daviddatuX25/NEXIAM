// @vitest-environment jsdom

import { cleanup, fireEvent, render, screen, within } from '@testing-library/svelte';
import { afterEach, expect, it, vi } from 'vitest';

vi.mock('@/Pages/Staff/StaffLayout.svelte', () => ({
    default: (anchor: unknown, { children }: { children?: (anchor: unknown) => void }) => children?.(anchor),
}));
vi.mock('@inertiajs/svelte', async (importOriginal) => ({
    ...await importOriginal<Record<string, unknown>>(),
    usePage: () => ({ props: { flash: {}, errors: {} } }),
}));

import AuditLogExplorer from '@/Pages/Staff/Logs/Index.svelte';

function fixture(ids = [11, 12], filters: Record<string, unknown> = {}, identity: Record<string, unknown> = {}) {
    return {
        as_of: '2026-10-19T08:00:00Z',
        logs: ids.map((id) => ({
            id,
            domain: 'security',
            event_type: `auth.event_${id}`,
            title: `Audit event ${id}`,
            summary: `Summary ${id}`,
            actor: 'Synthetic Audit Reviewer',
            actor_info: { kind: 'staff', name: 'Synthetic Audit Reviewer', office: 'admin', avatar: {} },
            created_at: '2026-01-01T00:00:00Z',
            created_at_human: 'Jan 1',
            payload: {},
            diff: [],
            event_hash: 'stored-event-hash',
            prev_hash: 'stored-prev-hash',
        })),
        filters: { category: '', event: '', date_from: '', date_to: '', search: '', sort: 'created_at', dir: 'desc', ...filters },
        categories: {},
        events: [],
        pagination: { current_page: 1, last_page: 1, per_page: 25, total: ids.length },
        ...identity,
    };
}

const presence = {
    filters: {},
    summary: { online_staff: 0, recent_staff: 0, active_sessions: 0 },
    scope: { label: 'Institution audit fixture' },
    sessions: [],
};

afterEach(cleanup);

it('clears page-local selection when equal-length results have different membership', async () => {
    const view = render(AuditLogExplorer, { audit: fixture(), presence, can_export_audit: true });
    const table = within(screen.getByRole('table'));
    await fireEvent.click(table.getByRole('checkbox', { name: 'Select all events on page' }));
    expect(table.getByRole('checkbox', { name: 'Select event Audit event 11' }).getAttribute('aria-checked')).toBe('true');
    expect(screen.getByRole('link', { name: 'Export Selected (CSV)' })).toBeTruthy();

    await view.rerender({ audit: fixture([21, 22]), presence, can_export_audit: true });
    expect(screen.queryByRole('link', { name: 'Export Selected (CSV)' })).toBeNull();
    expect(within(screen.getByRole('table')).getByRole('checkbox', { name: 'Select event Audit event 21' }).getAttribute('aria-checked')).toBe('false');
});

it('dismisses stale inspected data and selection after filter changes', async () => {
    const view = render(AuditLogExplorer, { audit: fixture(), presence, can_export_audit: true });
    const table = within(screen.getByRole('table'));
    await fireEvent.click(table.getByRole('checkbox', { name: 'Select event Audit event 11' }));
    await fireEvent.click(table.getAllByRole('button', { name: 'Inspect' })[0]);
    expect(screen.getByRole('dialog')).toBeTruthy();

    await view.rerender({ audit: fixture([11, 12], { search: 'changed' }), presence, can_export_audit: true });
    expect(screen.queryByRole('dialog')).toBeNull();
    expect(screen.queryByRole('link', { name: 'Export Selected (CSV)' })).toBeNull();
});

it('does not present stored hash values as a verified integrity result', async () => {
    render(AuditLogExplorer, { audit: fixture(), presence, can_export_audit: true });
    await fireEvent.click(within(screen.getByRole('table')).getAllByRole('button', { name: 'Inspect' })[0]);
    await fireEvent.click(within(screen.getByRole('dialog')).getByRole('button', { name: 'Cryptographic Chain' }));
    expect(screen.getByRole('status', { name: 'Unverified audit integrity' })).toBeTruthy();
    expect(within(screen.getByRole('dialog')).getByText('stored-event-hash')).toBeTruthy();
});

it('exports the applied result filters rather than an unsubmitted search draft', async () => {
    render(AuditLogExplorer, { audit: fixture([11, 12], { search: 'APPLIED' }), presence, can_export_audit: true });
    await fireEvent.click(within(screen.getByRole('table')).getByRole('checkbox', { name: 'Select event Audit event 11' }));
    await fireEvent.input(screen.getByRole('combobox', { name: 'Search audit events' }), { target: { value: 'UNSUBMITTED' } });
    const link = screen.getByRole('link', { name: 'Export Selected (CSV)' });
    const params = new URL(link.getAttribute('href')!, 'http://localhost').searchParams;
    expect(params.get('search')).toBe('APPLIED');
    expect(params.get('ids')).toBe('11');
});

it.each(['snapshot', 'page'])('clears selection and inspector when only the %s changes but preserves equivalent results', async (change) => {
    const view = render(AuditLogExplorer, { audit: fixture(), presence, can_export_audit: true });
    const table = within(screen.getByRole('table'));
    await fireEvent.click(table.getByRole('checkbox', { name: 'Select event Audit event 11' }));
    await fireEvent.click(table.getAllByRole('button', { name: 'Inspect' })[0]);
    await view.rerender({ audit: fixture(), presence, can_export_audit: true });
    expect(screen.getByRole('dialog')).toBeTruthy();
    expect(screen.getByRole('link', { name: 'Export Selected (CSV)' })).toBeTruthy();

    const identity = change === 'snapshot'
        ? { as_of: '2026-10-19T08:00:01Z' }
        : { pagination: { current_page: 2, last_page: 2, per_page: 25, total: 4 } };
    await view.rerender({ audit: fixture([11, 12], {}, identity), presence, can_export_audit: true });
    expect(screen.queryByRole('dialog')).toBeNull();
    expect(screen.queryByRole('link', { name: 'Export Selected (CSV)' })).toBeNull();
});
