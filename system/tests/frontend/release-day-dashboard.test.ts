// @vitest-environment jsdom

import {
    cleanup,
    fireEvent,
    render,
    screen,
    waitFor,
    within,
} from '@testing-library/svelte';
import { afterEach, expect, it, vi } from 'vitest';

vi.mock('@inertiajs/svelte', async (importOriginal) => ({
    ...(await importOriginal<Record<string, unknown>>()),
    usePage: () => ({ props: { flash: {}, errors: {} } }),
}));
vi.mock('@/Pages/Staff/StaffLayout.svelte', () => ({
    default: (
        anchor: unknown,
        { children }: { children?: (anchor: unknown) => void },
    ) => children?.(anchor),
}));
vi.mock('@/Components/kit/Walkthrough/TourTriggerGlyph.svelte', () => ({
    default: () => null,
}));
vi.mock('@/Components/kit/Walkthrough/XiamNpcDialog.svelte', () => ({
    default: () => null,
}));
vi.mock('@/Components/DocumentTemplate/PrintDocumentsSlot.svelte', () => ({
    default: (anchor: Element, props: Record<string, unknown>) => {
        const getter = props as { attemptIds?: number[]; slotKey?: string };
        const span = document.createElement('button');
        span.setAttribute('data-testid', `print-slot-${(getter.attemptIds ?? []).join(',') || getter.slotKey || 'header'}`);
        span.textContent = 'Print Result';
        anchor.parentNode?.insertBefore(span, anchor);
    },
}));

import Dashboard from '../../resources/js/Pages/Consultation/Dashboard.svelte';

afterEach(async () => {
    cleanup();
    await waitFor(() => expect(document.body.style.overflow).not.toBe('hidden'));
});

it('keeps applicant populations grouped, stateful, and metric-linked across four tabs', async () => {
    render(Dashboard, {
        dashboard: {
            stats: {
                today_scheduled: 0,
                pending_advise: 0,
                not_yet_scheduled: 3,
                pending_release: 3,
                released: 1,
                followups: 1,
                sessions_open: 0,
                sessions_closed: 0,
            },
            today_schedule: [],
            scheduled_applicants: [],
            unscheduled_applicants: [
                { attempt_id: 11, name: 'Zoe Candidate', reference_code: 'SYN-001', appointment_status: 'unscheduled', document_status: 'prepared', print_status: 'printed', advising_status: 'awaiting_advising' },
                { attempt_id: 12, name: 'Ana Candidate', reference_code: 'SYN-002', appointment_status: 'tentative', document_status: 'not_prepared', print_status: 'not_printed', advising_status: 'advised_awaiting_release' },
                { attempt_id: 13, name: 'Bea Candidate', reference_code: 'SYN-003', appointment_status: 'unscheduled', document_status: 'prepared', print_status: 'not_printed', advising_status: 'awaiting_advising' },
            ],
            released_applicants: [
                { attempt_id: 14, name: 'Carlo Released', reference_code: 'SYN-004', document_status: 'prepared', print_status: 'printed' },
            ],
            followup_applicants: [
                { attempt_id: 15, name: 'Dina Follow-up', reference_code: 'SYN-005', reason: 'program_follow_up', follow_up_reason: 'Second choice declined', document_status: 'prepared', print_status: 'printed' },
            ],
            not_yet_scheduled: [],
            re_advising: [],
            other_sessions: { total_count: 0, items: [] },
        },
    });

    await fireEvent.click(screen.getByRole('button', { name: 'Applicants' }));
    const search = screen.getByRole('textbox', { name: 'Search Release Day applicants' });
    const sidebar = screen.getByRole('region', { name: 'Release Day sidebar queues' });
    const listed = () => within(sidebar).getAllByRole('listitem').map((row) => row.textContent ?? '');
    await fireEvent.click(within(sidebar).getByRole('tab', { name: 'Unscheduled' }));
    expect(listed()[0]).toContain('Ana Candidate');

    await fireEvent.click(within(sidebar).getByRole('button', { name: 'Sort ascending' }));
    expect(listed()[0]).toContain('Zoe Candidate');
    await fireEvent.input(search, { target: { value: 'Ana' } });
    expect(listed()).toHaveLength(1);
    expect(listed()[0]).toContain('Ana Candidate');
    expect(within(sidebar).queryByText('Start Advising')).toBeNull();

    const unscheduledTab = within(sidebar).getByRole('tab', { name: 'Unscheduled' });
    await fireEvent.keyDown(unscheduledTab, { key: 'ArrowRight' });
    const releasedTab = within(sidebar).getByRole('tab', { name: 'Released' });
    expect(releasedTab).toBe(document.activeElement);
    expect(releasedTab.getAttribute('aria-selected')).toBe('true');
    await fireEvent.click(unscheduledTab);
    expect((search as HTMLInputElement).value).toBe('Ana');
    await fireEvent.input(search, { target: { value: '' } });
    await fireEvent.change(within(sidebar).getByRole('combobox', { name: 'Filter by print status' }), {
        target: { value: 'printed' },
    });
    expect(listed()).toHaveLength(1);
    expect(listed()[0]).toContain('Zoe Candidate');

    await fireEvent.click(screen.getByRole('button', { name: 'Hide sidebar' }));
    await fireEvent.click(screen.getByRole('button', { name: 'Released 1 applicants' }));
    expect(sidebar.hidden).toBe(false);
    expect(within(sidebar).getByRole('tab', { name: 'Released' }).getAttribute('aria-selected')).toBe('true');
    expect((screen.getByRole('textbox', { name: 'Search Release Day applicants' }) as HTMLInputElement).value).toBe('');
    expect(within(sidebar).getByText('Carlo Released')).toBeTruthy();
    await fireEvent.click(screen.getByRole('button', { name: 'Pending Release 3 applicants' }));
    expect(within(sidebar).getByRole('tab', { name: 'Unscheduled' }).getAttribute('aria-selected')).toBe('true');
    await fireEvent.click(within(sidebar).getByRole('tab', { name: 'Unscheduled' }));
    await fireEvent.input(search, { target: { value: 'Bea' } });
    await fireEvent.click(screen.getByRole('button', { name: 'Hide sidebar' }));
    await fireEvent.click(screen.getByRole('button', { name: 'Pending Release 3 applicants' }));
    expect(within(sidebar).getByRole('tab', { name: 'Unscheduled' }).getAttribute('aria-selected')).toBe('true');
    expect((screen.getByRole('textbox', { name: 'Search Release Day applicants' }) as HTMLInputElement).value).toBe('');
});

it('keeps session-grouped scheduled rosters and early-open actions alongside applicant tabs and print state', async () => {
    const view = render(Dashboard, {
        dashboard: {
            stats: {
                today_scheduled: 3,
                pending_advise: 2,
                pending_release: 5,
                released: 1,
                followups: 0,
                sessions_open: 1,
                sessions_closed: 0,
            },
            today_schedule: [
                {
                    id: 10,
                    code: 'CONS-TODAY-01',
                    scheduled_at: '2026-10-17T08:00:00+08:00',
                    time: '08:00',
                    time_range: '08:00 AM – 09:30 AM',
                    room: 'Room 101',
                    status: 'published',
                    assigned_count: 3,
                    applicants: [
                        { attempt_id: 101, reference_code: 'AY26-00101', name: 'Dela Cruz, Juan', outcome: 'pending', advised: false, status_label: 'Scheduled', release_day_readiness: 'ready' },
                        { attempt_id: 102, reference_code: 'AY26-00102', name: 'Reyes, Carlo', outcome: 'pending', advised: false, status_label: 'Scheduled', release_day_readiness: 'needs_print' },
                        { attempt_id: 301, reference_code: 'AY26-00301', name: 'Released Outside Queue', outcome: 'advised', advised: true, status_label: 'Advised', release_day_readiness: 'ready' },
                    ],
                },
            ],
            scheduled_applicants: [
                { attempt_id: 101, name: 'Dela Cruz, Juan', reference_code: 'AY26-00101', appointment_status: 'scheduled', document_status: 'prepared', print_status: 'printed', advising_status: 'awaiting_advising', url: '/consultation/applicants/101?session_id=10' },
                { attempt_id: 102, name: 'Reyes, Carlo', reference_code: 'AY26-00102', appointment_status: 'scheduled', document_status: 'prepared', print_status: 'not_printed', advising_status: 'awaiting_advising', url: '/consultation/applicants/102?session_id=10' },
                { attempt_id: 201, name: 'Gil, Enrique', reference_code: 'AY26-00201', appointment_status: 'scheduled', document_status: 'prepared', print_status: 'printed', advising_status: 'awaiting_advising', url: '/consultation/applicants/201?session_id=17' },
                { attempt_id: 202, name: 'Lim, Bea', reference_code: 'AY26-00202', appointment_status: 'scheduled', document_status: 'not_prepared', print_status: 'not_printed', advising_status: 'awaiting_advising', url: '/consultation/applicants/202?session_id=17' },
            ],
            unscheduled_applicants: [
                { attempt_id: 12, name: 'Unprinted Candidate', reference_code: 'SYN-012', appointment_status: 'tentative', document_status: 'not_prepared', print_status: 'not_printed', advising_status: 'awaiting_advising' },
            ],
            released_applicants: [
                { attempt_id: 301, name: 'Released Outside Queue', reference_code: 'AY26-00301', document_status: 'prepared', print_status: 'printed' },
            ],
            followup_applicants: [],
            re_advising: [],
            not_yet_scheduled: [],
            other_sessions: {
                total_count: 1,
                items: [
                    {
                        id: 17,
                        code: 'RD-FUTURE-17',
                        scheduled_at: '2026-10-20T09:00:00+08:00',
                        time_range: '09:00 AM – 10:30 AM',
                        room: 'Guidance Room',
                        status: 'published',
                        version: 1,
                        assigned_count: 2,
                        href: '/consultation/sessions/17',
                        applicants: [
                            { attempt_id: 201, reference_code: 'AY26-00201', name: 'Gil, Enrique', release_day_readiness: 'ready', url: '/consultation/applicants/201?session_id=17' },
                            { attempt_id: 202, reference_code: 'AY26-00202', name: 'Lim, Bea', release_day_readiness: 'needs_print', url: '/consultation/applicants/202?session_id=17' },
                        ],
                    },
                ],
            },
        },
    });
    await fireEvent.click(view.getByRole('button', { name: 'Applicants' }));

    const todayDetails = view.getByText('CONS-TODAY-01').closest('details')!;
    expect(within(todayDetails).getByText('Dela Cruz, Juan')).toBeTruthy();
    expect(within(todayDetails).getByText('Reyes, Carlo')).toBeTruthy();
    expect(within(view.getByRole('region', { name: 'Release Day sidebar queues' })).queryByText('Released Outside Queue')).toBeNull();
    expect(within(todayDetails).queryByRole('button', { name: /Open for Today/i })).toBeNull();
    const sessionDetails = view.getByText('RD-FUTURE-17').closest('details')!;
    expect(within(sessionDetails).getByText('Gil, Enrique')).toBeTruthy();
    expect(within(sessionDetails).getByText('Lim, Bea')).toBeTruthy();
    expect(within(sessionDetails).queryByTestId('print-slot-201')).toBeNull();
    expect(within(sessionDetails).getByTestId('print-slot-202')).toBeTruthy();
    await fireEvent.change(view.getByRole('combobox', { name: 'Filter by print status' }), {
        target: { value: 'not_printed' },
    });
    expect(view.getByText('CONS-TODAY-01')).toBeTruthy();
    expect(view.getByText('RD-FUTURE-17')).toBeTruthy();
    expect(view.queryByText('Gil, Enrique')).toBeNull();
    await fireEvent.change(view.getByRole('combobox', { name: 'Filter by print status' }), {
        target: { value: 'all' },
    });
    await fireEvent.click(view.getByRole('tab', { name: 'Unscheduled' }));
    expect(view.getByTestId('print-slot-12')).toBeTruthy();
    await fireEvent.click(view.getByRole('tab', { name: 'Scheduled' }));

    const scheduledSearch = view.getByRole('textbox', { name: 'Search Release Day applicants' });
    await fireEvent.input(scheduledSearch, { target: { value: 'Gil' } });
    expect(within(view.getByRole('region', { name: 'Release Day sidebar queues' })).queryByText('CONS-TODAY-01')).toBeNull();
    expect(within(view.getByRole('region', { name: 'Release Day sidebar queues' })).getByText('RD-FUTURE-17')).toBeTruthy();
    const filteredSessionDetails = within(view.getByRole('region', { name: 'Release Day sidebar queues' }))
        .getByText('RD-FUTURE-17').closest('details')!;
    expect(within(filteredSessionDetails).queryByText('Lim, Bea')).toBeNull();
    expect(within(filteredSessionDetails).getByText('Gil, Enrique')).toBeTruthy();

    await fireEvent.click(view.getByRole('button', { name: 'Hide sidebar' }));
    expect(view.queryByRole('textbox', { name: 'Search Release Day applicants' })).toBeNull();
    await fireEvent.click(view.getByRole('button', { name: 'Show sidebar' }));
    expect((view.getByRole('textbox', { name: 'Search Release Day applicants' }) as HTMLInputElement).value).toBe('Gil');

    await fireEvent.click(view.getByRole('button', { name: /Open for Today/i }));
    expect(view.getByText(/Open Session on Today's Release Day Board/i)).toBeTruthy();

    await fireEvent.click(view.getByRole('button', { name: 'Today' }));
    expect(view.getAllByText(/08:00 AM – 09:30 AM/).length).toBeGreaterThan(0);
    expect(view.queryByTestId('print-slot-101')).toBeNull();
    expect(view.getAllByTestId('print-slot-102').length).toBeGreaterThan(0);
});
