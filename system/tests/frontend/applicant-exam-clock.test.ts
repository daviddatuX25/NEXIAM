import { describe, expect, it } from 'vitest';
import { ExamDayClock, formatCountdown } from '../../resources/js/lib/applicant-exam-clock';

describe('ExamDayClock', () => {
    it('anchors end-state calculation to monotonic elapsed time, not a changed wall clock', () => {
        const clock = new ExamDayClock();
        expect(
            clock.synchronize(
                '2026-10-14T10:00:00.000Z',
                '2026-10-14T11:00:00.000Z',
                5_000,
            ),
        ).toBe(true);

        expect(
            clock.state(
                {
                    status: 'in_progress',
                    effective_end_at: '2026-10-14T11:00:00.000Z',
                },
                15_000,
            ),
        ).toBe('live');
    });

    it('charges request transit time against the server snapshot instead of re-aging it at arrival', () => {
        const clock = new ExamDayClock();
        const requestStartedAt = 10_000;
        clock.synchronize(
            '2026-10-14T10:00:00.000Z',
            '2026-10-14T10:01:00.000Z',
            requestStartedAt,
        );
        const responseArrivedAt = 12_000;

        expect(
            clock.state(
                {
                    status: 'in_progress',
                    effective_end_at: '2026-10-14T10:01:00.000Z',
                },
                responseArrivedAt,
            ),
        ).toBe('live');
    });

    it('marks a delayed response uncertain instead of restarting its freshness window on arrival', () => {
        const clock = new ExamDayClock();
        const requestStartedAt = 0;
        clock.synchronize(
            '2026-10-14T10:00:00.000Z',
            '2026-10-14T11:00:00.000Z',
            requestStartedAt,
        );

        expect(
            clock.state(
                {
                    status: 'in_progress',
                    effective_end_at: '2026-10-14T11:00:00.000Z',
                },
                30_001,
            ),
        ).toBe('uncertain');
    });

    it('stops reporting a synchronized session state when the sample is stale or the connection is uncertain', () => {
        const clock = new ExamDayClock();
        clock.synchronize(
            '2026-10-14T10:00:00.000Z',
            '2026-10-14T11:00:00.000Z',
            0,
        );

        expect(
            clock.state(
                {
                    status: 'in_progress',
                    effective_end_at: '2026-10-14T11:00:00.000Z',
                },
                30_001,
            ),
        ).toBe('uncertain');
        clock.markUncertain();
        expect(
            clock.state(
                {
                    status: 'in_progress',
                    effective_end_at: '2026-10-14T11:00:00.000Z',
                },
                1_000,
            ),
        ).toBe('uncertain');
    });

    it('marks a passed expected end as ended without changing session state', () => {
        const clock = new ExamDayClock();
        clock.synchronize(
            '2026-10-14T10:00:00.000Z',
            '2026-10-14T10:00:01.000Z',
            1_000,
        );
        const session = {
            status: 'in_progress',
            effective_end_at: '2026-10-14T10:00:01.000Z',
        };

        expect(clock.state(session, 4_000)).toBe('ended');
        expect(session.status).toBe('in_progress');
    });

    it('accepts an authoritative extension on the next synchronized snapshot', () => {
        const clock = new ExamDayClock();
        clock.synchronize(
            '2026-10-14T10:00:00.000Z',
            '2026-10-14T10:05:00.000Z',
            0,
        );
        clock.synchronize(
            '2026-10-14T10:04:00.000Z',
            '2026-10-14T10:20:00.000Z',
            60_000,
        );

        expect(
            clock.state(
                {
                    status: 'in_progress',
                    effective_end_at: '2026-10-14T10:20:00.000Z',
                },
                60_000,
            ),
        ).toBe('live');
    });

    it('does not mark completed sessions live or sessions without deadlines certain', () => {
        const clock = new ExamDayClock();
        clock.synchronize(
            '2026-10-14T10:00:00.000Z',
            '2026-10-14T11:00:00.000Z',
            0,
        );

        expect(
            clock.state(
                {
                    status: 'completed',
                    effective_end_at: '2026-10-14T11:00:00.000Z',
                },
                1_000,
            ),
        ).toBe('unsynchronized');
        clock.synchronize('2026-10-14T10:00:00.000Z', null, 2_000);
        expect(
            clock.state(
                { status: 'in_progress', effective_end_at: null },
                2_000,
            ),
        ).toBe('uncertain');
    });
    it('keeps a long open session in progress until its authoritative end', () => {
        const clock = new ExamDayClock();
        const deadline = '2026-10-22T18:15:53.000Z';
        const session = { status: 'in_progress', effective_end_at: deadline };

        expect(clock.synchronize('2026-10-14T10:15:00.000Z', deadline, 0)).toBe(
            true,
        );
        expect(clock.state(session, 15_000)).toBe('live');
        expect(session.status).toBe('in_progress');
    });

    it('formats remaining time including days when the deadline is days away', () => {
        expect(formatCountdown(86_400 * 3 + 3600 * 4 + 60 * 15 + 32)).toBe('3d 04:15:32');
        expect(formatCountdown(86_400)).toBe('1d 00:00:00');
        expect(formatCountdown(3600 * 5 + 60 * 20 + 10)).toBe('05:20:10');
        expect(formatCountdown(45)).toBe('00:00:45');
        expect(formatCountdown(0)).toBe('00:00:00');
    });

    it('reports pre_start state for published sessions counting down to scheduled start', () => {
        const clock = new ExamDayClock();
        const scheduledStart = '2026-10-25T14:00:00.000Z';
        const serverNow = '2026-10-22T10:00:00.000Z';
        expect(clock.synchronize(serverNow, scheduledStart, 0)).toBe(true);

        const session = {
            status: 'published',
            scheduled_starts_at: scheduledStart,
        };
        expect(clock.state(session, 1_000)).toBe('pre_start');
        const remaining = clock.remainingSeconds(1_000);
        expect(remaining).not.toBeNull();
        expect(formatCountdown(remaining!)).toContain('3d');
    });

    it('reports start_time_reached for published sessions when scheduled start has arrived', () => {
        const clock = new ExamDayClock();
        const scheduledStart = '2026-10-22T10:00:00.000Z';
        const serverNow = '2026-10-22T10:00:05.000Z';
        expect(clock.synchronize(serverNow, scheduledStart, 0)).toBe(true);

        const session = {
            status: 'published',
            scheduled_starts_at: scheduledStart,
        };
        expect(clock.state(session, 0)).toBe('start_time_reached');
    });
});
