// @vitest-environment jsdom

import { cleanup, fireEvent, render, screen } from '@testing-library/svelte';
import { afterEach, expect, it, vi } from 'vitest';

const { routerGet, routerVisit } = vi.hoisted(() => ({
    routerGet: vi.fn(),
    routerVisit: vi.fn(),
}));
vi.mock('@inertiajs/svelte', async (importOriginal) => ({
    ...await importOriginal<Record<string, unknown>>(),
    router: { get: (...args: unknown[]) => routerGet(...args), visit: (...args: unknown[]) => routerVisit(...args) },
}));
vi.mock('@/Pages/Staff/StaffLayout.svelte', () => ({
    default: (anchor: unknown, { children }: { children?: (anchor: unknown) => void }) => children?.(anchor),
}));
vi.mock('@/Components/kit/PageHeaderBar.svelte', () => ({ default: () => null }));
vi.mock('@/Components/kit/HeaderActions.svelte', () => ({ default: () => null }));
vi.mock('@/Components/kit/BulkActionBar.svelte', () => ({ default: () => null }));
vi.mock('@/Components/kit/ActionDropdown.svelte', () => ({ default: () => null }));
vi.mock('@/Components/kit/SearchSuggestionDropdown.svelte', () => ({ default: () => null }));
vi.mock('@/Components/kit/EntityRowActions.svelte', () => ({ default: () => null }));
vi.mock('@/Components/scheduling/CohortAutoDistributeModal.svelte', () => ({ default: () => null }));

import ConsultationSessions from '../../resources/js/Pages/Consultation/ConsultationSessions.svelte';
import ConsultationSessionCreate from '../../resources/js/Pages/Consultation/ConsultationSessionCreate.svelte';

afterEach(() => {
    cleanup();
    routerGet.mockReset();
    routerVisit.mockReset();
});

function renderSessions() {
    return render(ConsultationSessions, {
        props: {
            sessions: [
                { id: 1, code: 'Z-1', status: 'draft', scheduled_at: '2026-10-15T10:00:00+08:00', assigned_count: 8, booking_mode: 'staff_only', room: { name: 'Room 10', capacity: 20 } },
                { id: 2, code: 'A-2', status: 'published', scheduled_at: '2026-10-15T08:00:00+08:00', assigned_count: 2, booking_mode: 'open_for_reservation', room: { name: 'Room 2', capacity: 10 } },
                { id: 3, code: 'M-3', status: 'draft', scheduled_at: '2026-10-15T09:00:00+08:00', assigned_count: 13, booking_mode: 'staff_only', room: { name: 'Room 3', capacity: 15 } },
            ],
            filters: { search: 'candidate', status: 'draft', academic_cycle_id: 12, sort: 'scheduled_at', dir: 'desc' },
            statuses: [{ value: 'draft', label: 'Draft' }, { value: 'published', label: 'Published' }],
            cycles: [],
            booking_modes: [],
            sort_options: [
                { value: 'scheduled_at', label: 'Scheduled Time' },
                { value: 'code', label: 'Session Code' },
                { value: 'status', label: 'Status' },
                { value: 'created_at', label: 'Date Created' },
            ],
        },
    });
}

function tableCodes(): string[] {
    return Array.from(document.querySelectorAll('[data-tour="consultation-session-list"] tbody tr'))
        .map((row) => row.textContent ?? '')
        .filter((text) => /[A-Z]-\d/.test(text))
        .map((text) => text.match(/[A-Z]-\d/)?.[0] ?? '');
}

it('sorts Assigned numerically in both directions and preserves ID selection in table and cards', async () => {
    renderSessions();
    const selection = document.querySelector('table input[aria-label="Select session Z-1"]') as HTMLInputElement;
    await fireEvent.click(selection);
    expect(selection.checked).toBe(true);

    await fireEvent.click(screen.getByRole('button', { name: 'Sort by Room' }));
    expect(tableCodes()).toEqual(['A-2', 'M-3', 'Z-1']);
    await fireEvent.click(screen.getByRole('button', { name: 'Sort by Room' }));
    expect(tableCodes()).toEqual(['Z-1', 'M-3', 'A-2']);

    await fireEvent.click(screen.getByRole('button', { name: 'Sort by Assignment' }));
    expect(tableCodes()).toEqual(['A-2', 'Z-1', 'M-3']);
    await fireEvent.click(screen.getByRole('button', { name: 'Sort by Assignment' }));
    expect(tableCodes()).toEqual(['Z-1', 'M-3', 'A-2']);
    await fireEvent.click(screen.getByRole('button', { name: 'Sort by Assigned' }));
    expect(tableCodes()).toEqual(['A-2', 'Z-1', 'M-3']);
    expect(selection.checked).toBe(true);

    await fireEvent.click(screen.getByRole('button', { name: 'Sort by Assigned' }));
    expect(tableCodes()).toEqual(['M-3', 'Z-1', 'A-2']);

    await fireEvent.click(screen.getByRole('button', { name: 'Card view' }));
    const cards = document.querySelector('ul[data-tour="consultation-session-list"]');
    expect(cards).not.toBeNull();
    expect(Array.from(cards!.querySelectorAll('li')).map((card) => card.textContent?.match(/[A-Z]-\d/)?.[0])).toEqual(['M-3', 'Z-1', 'A-2']);
    expect((cards!.querySelector('input[aria-label="Select session Z-1"]') as HTMLInputElement).checked).toBe(true);
});

it('keeps supported server sorting on scheduled timestamps and retains active filters', async () => {
    renderSessions();
    await fireEvent.click(screen.getByRole('button', { name: 'Sort by When' }));

    expect(routerGet).toHaveBeenCalledWith('/consultation/sessions', {
        search: 'candidate',
        status: 'draft',
        academic_cycle_id: '12',
        sort: 'scheduled_at',
        dir: 'asc',
    }, { preserveState: true, preserveScroll: true });
});

it('renders the consistent standby card and centered Schedule Release Day CTA when no sessions are scheduled', () => {
    const view = render(ConsultationSessions, {
        props: {
            page_state: 'empty',
            sessions: [],
            filters: {},
            statuses: [],
            cycles: [
                {
                    id: 12,
                    label: '2025-2026 1st Semester',
                    is_active: true,
                },
            ],
            booking_modes: [],
            sort_options: [],
        },
    });

    expect(
        view.getByRole('heading', { name: 'No Sessions in Active Schedule' }),
    ).toBeTruthy();
    expect(view.container.textContent?.replace(/\s+/g, ' ')).toContain(
        'All consultation rooms are currently on standby for 2025-2026 1st Semester. Schedule a Release Day session under Release Day Scheduling to initiate live Release Day advising.',
    );
    const ctaLink = view
        .getByRole('button', { name: /Schedule Release Day/i })
        .closest('a');
    expect(ctaLink?.getAttribute('href')).toContain('/consultation/sessions/create');
});
it('shows both authorized booking modes on Release Day session creation without an unlock gesture', () => {
    render(ConsultationSessionCreate, {
        props: {
            cycles: [{ id: 12, label: '2026–2027', is_active: true }],
            rooms: [{ id: 5, label: 'Guidance Room', capacity: 20 }],
            default_cycle_id: 12,
            booking_modes: [
                { value: 'staff_only', label: 'Staff-only (office assigns)' },
                { value: 'open_for_reservation', label: 'Self-booking (students reserve)' },
            ],
        },
    });

    expect(screen.getByRole('radio', { name: /Staff-only/ })).toBeTruthy();
    expect(screen.getByRole('radio', { name: /Self-booking/ })).toBeTruthy();
});
