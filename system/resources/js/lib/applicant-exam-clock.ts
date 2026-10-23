export type ExamDaySession = {
    status: string;
    scheduled_starts_at?: string | null;
    effective_end_at?: string | null;
};

export type ExamDayClockState =
    | 'unsynchronized'
    | 'live'
    | 'pre_start'
    | 'uncertain'
    | 'ended'
    | 'start_time_reached';

const MAX_SAMPLE_AGE_MS = 30_000;

export function formatCountdown(totalSeconds: number): string {
    const safeSeconds = Math.max(0, Math.floor(totalSeconds));
    const days = Math.floor(safeSeconds / 86_400);
    const hours = Math.floor((safeSeconds % 86_400) / 3_600);
    const minutes = Math.floor((safeSeconds % 3_600) / 60);
    const seconds = safeSeconds % 60;
    const paddedHours = String(hours).padStart(2, '0');
    const paddedMinutes = String(minutes).padStart(2, '0');
    const paddedSeconds = String(seconds).padStart(2, '0');
    const clock = `${paddedHours}:${paddedMinutes}:${paddedSeconds}`;

    return days > 0 ? `${days}d ${clock}` : clock;
}

export class ExamDayClock {
    private serverNowMs: number | null = null;
    private sampledAtMonotonicMs: number | null = null;
    private deadlineMs: number | null = null;
    private uncertain = true;

    synchronize(
        serverNow: string,
        deadline: string | null,
        monotonicNow: number,
    ): boolean {
        const serverNowMs = Date.parse(serverNow);
        const deadlineMs = deadline === null ? null : Date.parse(deadline);

        if (
            !Number.isFinite(serverNowMs) ||
            (deadline !== null && !Number.isFinite(deadlineMs))
        ) {
            this.uncertain = true;
            return false;
        }

        this.serverNowMs = serverNowMs;
        this.sampledAtMonotonicMs = monotonicNow;
        this.deadlineMs = deadlineMs;
        this.uncertain = false;
        return true;
    }

    markUncertain(): void {
        this.uncertain = true;
    }

    state(
        session: ExamDaySession | null,
        monotonicNow: number,
    ): ExamDayClockState {
        if (session?.status !== 'in_progress' && session?.status !== 'published' && session?.status !== 'draft') {
            return 'unsynchronized';
        }

        if (
            this.uncertain ||
            this.serverNowMs === null ||
            this.sampledAtMonotonicMs === null ||
            this.deadlineMs === null
        ) {
            return 'uncertain';
        }

        if (monotonicNow - this.sampledAtMonotonicMs > MAX_SAMPLE_AGE_MS) {
            return 'uncertain';
        }

        const remaining = this.remainingMilliseconds(monotonicNow);
        if (session.status === 'in_progress') {
            return remaining <= 0 ? 'ended' : 'live';
        }
        return remaining <= 0 ? 'start_time_reached' : 'pre_start';
    }

    remainingSeconds(monotonicNow: number): number | null {
        if (
            this.serverNowMs === null ||
            this.sampledAtMonotonicMs === null ||
            this.deadlineMs === null
        ) {
            return null;
        }

        const elapsed = Math.max(0, monotonicNow - this.sampledAtMonotonicMs);
        const remaining = Math.max(
            0,
            this.deadlineMs - (this.serverNowMs + elapsed),
        );
        return Math.ceil(remaining / 1000);
    }

    private remainingMilliseconds(monotonicNow: number): number {
        const elapsed = Math.max(
            0,
            monotonicNow - (this.sampledAtMonotonicMs ?? monotonicNow),
        );
        return (this.deadlineMs ?? 0) - ((this.serverNowMs ?? 0) + elapsed);
    }
}
