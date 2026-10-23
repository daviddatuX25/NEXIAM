// @vitest-environment jsdom

import { cleanup, render, screen } from '@testing-library/svelte';
import { afterEach, describe, expect, it, vi } from 'vitest';

vi.mock('@/Pages/Applicant/ApplicantLayout.svelte', () => ({
    default: (
        _anchor: unknown,
        { children }: { children?: (anchor: unknown) => void },
    ) => children?.(_anchor),
}));

import ExamDay from '../../resources/js/Pages/Applicant/ExamDay.svelte';

afterEach(() => {
    cleanup();
    vi.unstubAllGlobals();
});

const helpHref =
    '/applicant/admission-support?group=offices&draft_reason=exam_time_extension';

function formattedDate(value: string): string {
    return new Intl.DateTimeFormat('en', {
        month: 'long',
        day: 'numeric',
        year: 'numeric',
        hour: 'numeric',
        minute: '2-digit',
    }).format(new Date(value));
}

function renderExamDay(
    status: string,
    overrides: Record<string, string | null> = {},
    serverNow = '2026-10-14T09:30:00.000Z',
) {
    const exam_day = {
        page_state: 'ready' as const,
        attendance: null,
        session: {
            code: 'EX-STATE',
            status,
            scheduled_starts_at: '2026-10-14T10:00:00.000Z',
            started_at: null,
            effective_end_at: '2026-10-14T11:00:00.000Z',
            closed_at: null,
            version: 1,
            ...overrides,
        },
        server_now: serverNow,
        help_href: helpHref,
    };

    vi.stubGlobal(
        'fetch',
        vi.fn(async () => ({
            ok: true,
            json: async () => ({ exam_day }),
        })),
    );

    render(ExamDay, { props: { exam_day } });
}

describe('applicant Exam Day lifecycle details', () => {
    it('shows actual start, effective end, and a readable live countdown while monitoring is active', async () => {
        const startedAt = '2026-10-14T10:15:00.000Z';
        const expectedEndAt = '2026-10-22T18:15:53.000Z';
        renderExamDay(
            'in_progress',
            {
                started_at: startedAt,
                effective_end_at: expectedEndAt,
            },
            '2026-10-14T10:15:00.000Z',
        );

        expect(screen.getByText('In progress')).toBeTruthy();
        expect(screen.getByText('Started at')).toBeTruthy();
        expect(screen.getByText(formattedDate(startedAt))).toBeTruthy();
        expect(screen.queryByText('Scheduled start')).toBeNull();
        expect(screen.getByText('Expected end')).toBeTruthy();
        expect(screen.getByText(formattedDate(expectedEndAt))).toBeTruthy();
        expect(await screen.findByText('Time remaining')).toBeTruthy();
        expect(await screen.findByText('8d 08:00:53')).toBeTruthy();
    });

    it('shows recorded actual start and close times without a timer after completion', () => {
        const startedAt = '2026-10-14T10:15:00.000Z';
        const closedAt = '2026-10-14T10:45:00.000Z';
        renderExamDay(
            'completed',
            { started_at: startedAt, closed_at: closedAt },
            '2026-10-14T11:00:00.000Z',
        );

        expect(screen.getByText('Completed')).toBeTruthy();
        expect(screen.getByText('Started at')).toBeTruthy();
        expect(screen.getByText(formattedDate(startedAt))).toBeTruthy();
        expect(screen.getByText('Ended at')).toBeTruthy();
        expect(screen.getByText(formattedDate(closedAt))).toBeTruthy();
        expect(screen.queryByText('Time remaining')).toBeNull();
        expect(screen.queryByText(/countdown/i)).toBeNull();
        expect(screen.queryByText('Scheduled start')).toBeNull();
    });
    it('does not infer completed start or close times from the schedule', () => {
        renderExamDay('completed', {}, '2026-10-14T11:00:00.000Z');

        expect(screen.getAllByText('Not recorded')).toHaveLength(2);
        expect(screen.queryByText('Scheduled start')).toBeNull();
        expect(screen.queryByText('Scheduled end')).toBeNull();
        expect(screen.queryByText('Time remaining')).toBeNull();
    });

    it('shows scheduled times and a pre-start countdown before the session starts', async () => {
        const scheduledStart = '2026-10-17T10:00:00.000Z';
        renderExamDay('published', {
            scheduled_starts_at: scheduledStart,
        }, '2026-10-14T10:00:00.000Z');

        expect(screen.getByText('Scheduled')).toBeTruthy();
        expect(screen.getByText('Scheduled start')).toBeTruthy();
        expect(screen.getByText('Scheduled end')).toBeTruthy();
        expect(screen.queryByText('Started at')).toBeNull();
        expect(await screen.findByText('Starts in')).toBeTruthy();
        expect(await screen.findByText('3d 00:00:00')).toBeTruthy();
    });

    it('formats scheduled start and end from wall-clock schedule fields without UTC offset shift', () => {
        renderExamDay('published', {
            scheduled_date: '2026-10-17',
            scheduled_start_time: '10:30',
            scheduled_end_time: '12:00',
            scheduled_starts_at: '2026-10-17T10:30:00+00:00',
            effective_end_at: '2026-10-17T12:00:00+00:00',
        });

        expect(screen.getByText(/October 17, 2026.*10:30 AM/)).toBeTruthy();
        expect(screen.getByText(/October 17, 2026.*12:00 PM/)).toBeTruthy();
        expect(screen.queryByText(/6:30 PM/)).toBeNull();
    });
});
