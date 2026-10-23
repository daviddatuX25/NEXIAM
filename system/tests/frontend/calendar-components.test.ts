// @vitest-environment jsdom

import {
    cleanup,
    fireEvent,
    render,
    screen,
    within,
} from '@testing-library/svelte';
import { afterEach, describe, expect, it, vi } from 'vitest';

const { routerPostMock } = vi.hoisted(() => ({
    routerPostMock: vi.fn(),
}));

vi.mock('@inertiajs/svelte', () => ({
    Link: (
        _anchor: unknown,
        { children }: { children?: (anchor: unknown) => void },
    ) => children?.(_anchor),
    router: {
        post: routerPostMock,
    },
    usePage: () => ({
        props: {
            errors: {},
        },
    }),
}));
vi.mock('@/Pages/Applicant/ApplicantLayout.svelte', () => ({
    default: (
        _anchor: unknown,
        { children }: { children?: (anchor: unknown) => void },
    ) => children?.(_anchor),
}));
vi.mock('@/Components/kit/Walkthrough/TourTriggerGlyph.svelte', () => ({
    default: () => null,
}));
vi.mock('@/Components/kit/Walkthrough/XiamNpcDialog.svelte', () => ({
    default: () => null,
}));
vi.mock('@/Components/Wayfinding/VenueCompassHud.svelte', () => ({
    default: () => null,
}));

import CalendarViewToggle from '../../resources/js/Components/applicant/CalendarViewToggle.svelte';
import DateStrip from '../../resources/js/Components/applicant/DateStrip.svelte';
import MonthGrid from '../../resources/js/Components/applicant/MonthGrid.svelte';
import Schedule from '../../resources/js/Pages/Applicant/Schedule.svelte';
import ReleaseSchedule from '../../resources/js/Pages/Applicant/ReleaseSchedule.svelte';
afterEach(() => {
    cleanup();
});

describe('CalendarViewToggle', () => {
    it('renders both view buttons and reports the selected mode', async () => {
        const onChange = vi.fn();
        render(CalendarViewToggle, { props: { value: 'month', onChange } });

        expect(screen.getByRole('button', { name: 'Month' })).toBeTruthy();
        expect(screen.getByRole('button', { name: 'Strip' })).toBeTruthy();

        await fireEvent.click(screen.getByRole('button', { name: 'Strip' }));
        expect(onChange).toHaveBeenCalledWith('strip');
        await fireEvent.click(screen.getByRole('button', { name: 'Month' }));
        expect(onChange).toHaveBeenLastCalledWith('month');
    });
});

describe('MonthGrid', () => {
    it('shows the visible month, weekday headers, and selected date', () => {
        render(MonthGrid, {
            props: {
                days: [
                    {
                        date: '2026-11-12',
                        slots: [{ full: false, remaining: 1 }],
                    },
                ],
                selectedDate: '2026-11-12',
                visibleMonth: '2026-11',
            },
        });

        expect(
            screen.getByRole('heading', { name: 'November 2026' }),
        ).toBeTruthy();
        const grid = screen.getByLabelText('Available dates');
        expect(
            within(grid)
                .getAllByText(/^(Mo|Tu|We|Th|Fr|Sa|Su)$/)
                .map((weekday) => weekday.textContent),
        ).toEqual(['Mo', 'Tu', 'We', 'Th', 'Fr', 'Sa', 'Su']);
        expect(within(grid).getAllByRole('button').length).toBe(30);
        expect(
            within(grid)
                .getByRole('button', { name: /November 12, 2026/ })
                .getAttribute('aria-pressed'),
        ).toBe('true');
    });
});

describe('DateStrip', () => {
    it('renders date pills and marks the selected date', () => {
        render(DateStrip, {
            props: {
                days: [
                    {
                        date: '2026-11-12',
                        slots: [{ full: false, remaining: 1 }],
                    },
                    {
                        date: '2026-11-13',
                        slots: [{ full: false, remaining: 1 }],
                    },
                ],
                selectedDate: '2026-11-12',
            },
        });

        const strip = screen.getByLabelText('Available dates');
        const pills = within(strip).getAllByRole('button');
        expect(pills).toHaveLength(2);
        expect(pills[0].textContent).toContain('12');
        expect(pills[1].textContent).toContain('13');
        expect(pills[0].getAttribute('aria-pressed')).toBe('true');
        expect(pills[1].getAttribute('aria-pressed')).toBe('false');
    });
});

describe('Schedule exam slot switching', () => {
    it('reopens the switch confirmation after dismissing with Close (X) and shows session codes on cards', async () => {
        render(Schedule, {
            props: {
                page_state: 'assigned_published',
                attempt: {
                    id: 11,
                    reference_code: 'APP-2026-00002',
                    status: 'accepted',
                    academic_cycle_id: 1,
                },
                assignment: {
                    id: 2,
                    version: 1,
                    source: 'staff_assigned',
                    source_label: 'Assigned by Guidance',
                    exam_completed: false,
                    attendance_outcome: null,
                    session: {
                        code: 'Batch-01',
                        status: 'draft',
                        date: '2026-10-17',
                        start_time: '08:00',
                        end_time: '09:30',
                        scheduled_at: '2026-10-17T08:00:00+00:00',
                        effective_end_at: '2026-10-17T09:30:00+00:00',
                        room: 'ITBR-104 — ITBR 104',
                        published: false,
                    },
                },
                slot_switch: {
                    target_calendar_days: [
                        {
                            date: '2026-10-17',
                            slots: [
                                {
                                    id: 3,
                                    code: 'Batch-03',
                                    date: '2026-10-17',
                                    starts_at: '2026-10-17T10:30:00+00:00',
                                    start_time: '10:30',
                                    end_time: '12:00',
                                    room: 'ITBR 105',
                                    capacity: 40,
                                    remaining: 40,
                                    full: false,
                                },
                                {
                                    id: 4,
                                    code: 'Batch-04',
                                    date: '2026-10-17',
                                    starts_at: '2026-10-17T10:30:00+00:00',
                                    start_time: '10:30',
                                    end_time: '12:00',
                                    room: 'ITBR 104',
                                    capacity: 30,
                                    remaining: 30,
                                    full: false,
                                },
                            ],
                        },
                    ],
                },
            },
        });

        const switchButtons = screen.getAllByRole('button', {
            name: 'Switch Slot',
        });
        expect(switchButtons).toHaveLength(2);

        await fireEvent.click(switchButtons[0]);
        expect(screen.getByText('Switch exam slot?')).toBeTruthy();

        const closeBtn = screen.getByRole('button', { name: 'Close' });
        await fireEvent.click(closeBtn);

        await fireEvent.click(switchButtons[1]);
        expect(screen.getByText('Switch exam slot?')).toBeTruthy();
        expect(screen.getAllByText(/Batch-04/).length).toBeGreaterThan(0);
    });

    it('opens the switch confirmation when clicking an available calendar date that has one slot', async () => {
        render(Schedule, {
            props: {
                page_state: 'assigned_published',
                attempt: {
                    id: 11,
                    reference_code: 'APP-2026-00002',
                    status: 'accepted',
                    academic_cycle_id: 1,
                },
                assignment: {
                    id: 2,
                    version: 1,
                    source: 'staff_assigned',
                    source_label: 'Assigned by Guidance',
                    exam_completed: false,
                    attendance_outcome: null,
                    session: {
                        code: 'Batch-01',
                        status: 'draft',
                        date: '2026-10-17',
                        start_time: '08:00',
                        end_time: '09:30',
                        scheduled_at: '2026-10-17T08:00:00+00:00',
                        effective_end_at: '2026-10-17T09:30:00+00:00',
                        room: 'ITBR-104 — ITBR 104',
                        published: false,
                    },
                },
                slot_switch: {
                    target_calendar_days: [
                        {
                            date: '2026-10-17',
                            slots: [
                                {
                                    id: 3,
                                    code: 'Batch-03',
                                    date: '2026-10-17',
                                    starts_at: '2026-10-17T10:30:00+00:00',
                                    start_time: '10:30',
                                    end_time: '12:00',
                                    room: 'ITBR 105',
                                    capacity: 40,
                                    remaining: 40,
                                    full: false,
                                },
                            ],
                        },
                    ],
                },
            },
        });

        const dayButton = screen.getByRole('button', {
            name: /October 17, 2026, 1 available slot/i,
        });
        await fireEvent.click(dayButton);

        expect(screen.getByText('Switch exam slot?')).toBeTruthy();
    });
});
describe('Schedule exam reservation', () => {
    it('offers month and strip date selection while retaining explicit slot confirmation', async () => {
        render(Schedule, {
            props: {
                page_state: 'calendar_open',
                attempt: {
                    id: 17,
                    reference_code: 'APP-2026-00003',
                    status: 'accepted',
                    academic_cycle_id: 1,
                },
                calendar_days: [
                    {
                        date: '2026-11-12',
                        slots: [
                            {
                                id: 71,
                                code: 'Internal-Exam-Code-71',
                                start_time: '09:00',
                                end_time: '10:00',
                                room: 'Room A',
                                remaining: 5,
                                capacity: 5,
                                full: false,
                            },
                        ],
                    },
                    {
                        date: '2026-11-13',
                        slots: [
                            {
                                id: 72,
                                code: 'Internal-Exam-Code-72',
                                start_time: '10:00',
                                end_time: '11:00',
                                room: 'Room B',
                                remaining: 4,
                                capacity: 5,
                                full: false,
                            },
                        ],
                    },
                ],
            },
        });

        expect(screen.getByRole('button', { name: 'Month' })).toBeTruthy();
        expect(screen.getByRole('button', { name: 'Strip' })).toBeTruthy();
        expect(
            screen
                .getByRole('button', { name: 'Strip' })
                .getAttribute('aria-pressed'),
        ).toBe('true');
        await fireEvent.click(screen.getByRole('button', { name: 'Month' }));
        expect(
            screen.getByRole('button', {
                name: /November 12, 2026, 1 available slot/i,
            }),
        ).toBeTruthy();

        await fireEvent.click(
            screen.getByRole('button', {
                name: /November 13, 2026, 1 available slot/i,
            }),
        );
        expect(screen.getByText(/Room B/)).toBeTruthy();
        expect(screen.queryByText(/Room A/)).toBeNull();

        await fireEvent.click(screen.getByRole('button', { name: 'Strip' }));
        await fireEvent.click(
            screen.getByRole('button', {
                name: /November 12, 2026, 1 available slot/i,
            }),
        );
        expect(screen.getByText(/Room A/)).toBeTruthy();
        expect(screen.queryByText(/Room B/)).toBeNull();

        await fireEvent.click(screen.getByRole('button', { name: /Room A/ }));
        expect(screen.getByText('Confirm exam slot')).toBeTruthy();
    });
});
describe('Release Day appointment reservation', () => {
    it('uses the shared month and strip calendar to filter the selected date', async () => {
        render(ReleaseSchedule, {
            props: {
                page_state: 'booking_open',
                attempt: {
                    id: 31,
                    reference_code: 'APP-2026-00031',
                    academic_cycle_id: 1,
                },
                calendar_days: [
                    {
                        date: '2026-11-12',
                        slots: [
                            {
                                id: 81,
                                code: 'Internal-Release-Code-81',
                                date: '2026-11-12',
                                scheduled_at: '2026-11-12T09:00:00+00:00',
                                start_time: '09:00',
                                time_label: '09:00 AM',
                                room: 'Guidance Office A',
                                remaining: 8,
                                capacity: 10,
                                full: false,
                            },
                        ],
                    },
                    {
                        date: '2026-11-13',
                        slots: [
                            {
                                id: 82,
                                code: 'Internal-Release-Code-82',
                                date: '2026-11-13',
                                scheduled_at: '2026-11-13T10:00:00+00:00',
                                start_time: '10:00',
                                time_label: '10:00 AM',
                                room: 'Guidance Office B',
                                remaining: 7,
                                capacity: 10,
                                full: false,
                            },
                        ],
                    },
                ],
            },
        });

        expect(screen.getByRole('button', { name: 'Month' })).toBeTruthy();
        expect(screen.getByRole('button', { name: 'Strip' })).toBeTruthy();
        expect(
            screen
                .getByRole('button', { name: 'Strip' })
                .getAttribute('aria-pressed'),
        ).toBe('true');
        await fireEvent.click(screen.getByRole('button', { name: 'Month' }));
        await fireEvent.click(
            screen.getByRole('button', {
                name: /November 13, 2026, 1 available slot/i,
            }),
        );

        expect(screen.getByText(/Guidance Office B/)).toBeTruthy();
        expect(screen.queryByText(/Guidance Office A/)).toBeNull();
    });
});

describe('Release Day appointment switching', () => {
    const assignment = {
        id: 91,
        version: 3,
        session: {
            status: 'draft',
            published: false,
            scheduled_at: '2026-11-12T09:00:00+08:00',
            room: 'Current Guidance Office',
        },
    };
    const slotSwitch = {
        target_calendar_days: [{
            date: '2026-11-13',
            slots: [{
                id: 82,
                scheduled_at: '2026-11-13T10:00:00+08:00',
                start_time: '10:00',
                room: 'New Guidance Office',
                remaining: 7,
                full: false,
            }],
        }],
    };

    it('preserves the tentative appointment until an explicitly confirmed switch and can reopen after dismissal', async () => {
        routerPostMock.mockClear();
        render(ReleaseSchedule, {
            props: {
                page_state: 'assigned_or_reserved',
                attempt: { id: 31 },
                assignment,
                slot_switch: slotSwitch,
            },
        });
        expect(screen.getByText('Tentative')).toBeTruthy();
        expect(screen.getByText('Current Guidance Office')).toBeTruthy();
        const day = screen.getByRole('button', {
            name: /November 13, 2026, 1 available slot/i,
        });
        await fireEvent.click(day);
        expect(routerPostMock).not.toHaveBeenCalled();
        const dialog = screen.getByRole('dialog');
        expect(within(dialog).getByText(/Current Guidance Office/)).toBeTruthy();
        expect(within(dialog).getByText(/New Guidance Office/)).toBeTruthy();
        await fireEvent.click(within(dialog).getByRole('button', { name: 'Back' }));
        await fireEvent.click(day);
        await fireEvent.click(screen.getByRole('button', { name: 'Confirm switch' }));
        expect(routerPostMock).toHaveBeenCalledWith(
            '/applicant/release-schedule/switch',
            expect.objectContaining({
                consultation_session_id: 82,
                admission_attempt_id: 31,
                expected_assignment_id: 91,
                expected_assignment_version: 3,
                idempotency_key: expect.any(String),
            }),
            expect.any(Object),
        );
    });

    it('keeps a draft appointment visible without alternatives and hides switching after publication', async () => {
        const view = render(ReleaseSchedule, {
            props: {
                page_state: 'assigned_or_reserved',
                attempt: { id: 31 },
                assignment,
                slot_switch: { target_calendar_days: [] },
            },
        });
        expect(screen.getByText('Tentative')).toBeTruthy();
        expect(screen.getByText('Current Guidance Office')).toBeTruthy();
        expect(screen.queryByRole('button', { name: 'Switch Slot' })).toBeNull();
        await view.rerender({
            assignment: {
                ...assignment,
                session: { ...assignment.session, status: 'published', published: true },
            },
            slot_switch: null,
        });
        expect(screen.queryByText('Tentative')).toBeNull();
        expect(screen.getByText('Official schedule published')).toBeTruthy();
        expect(screen.queryByRole('button', { name: 'Switch Slot' })).toBeNull();
    });

    it('shows the selected reservation venue rather than an unspecified assigned room', async () => {
        render(ReleaseSchedule, {
            props: {
                page_state: 'booking_open',
                attempt: { id: 31 },
                calendar_days: slotSwitch.target_calendar_days,
            },
        });
        await fireEvent.click(screen.getByRole('button', { name: /New Guidance Office/ }));
        const dialog = screen.getByRole('dialog');
        expect(within(dialog).getByText(/Reserve .* at New Guidance Office\?/)).toBeTruthy();
        expect(within(dialog).queryByText(/the assigned room/)).toBeNull();
    });
});
