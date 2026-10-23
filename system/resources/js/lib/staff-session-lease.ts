import { csrfHeaders } from './utils.js';
import { isStaffAccountChangedResponse } from './staff-account-generation';

export type StaffSessionLeaseProjection = {
    server_now: number;
    idle_expires_at: number;
    absolute_expires_at: number;
    effective_idle_minutes: number;
    status: string;
};

export type TimeoutPreset = 15 | 30 | 60 | 480;

const PREFERENCE_KEY = 'nexiam.workstation_timeout_preset';
export class StaffAccountChangedError extends Error {
    constructor() {
        super('The staff account changed during this request.');
        this.name = 'StaffAccountChangedError';
    }
}

const TIMEOUT_PRESETS: readonly TimeoutPreset[] = [15, 30, 60, 480];

function isTimeoutPreset(value: unknown): value is TimeoutPreset {
    return value === 15 || value === 30 || value === 60 || value === 480;
}

export function readWorkstationTimeoutPreference(): TimeoutPreset {
    try {
        const value = globalThis.localStorage?.getItem(PREFERENCE_KEY);
        const preset = typeof value === 'string' ? Number(value) : Number.NaN;

        return isTimeoutPreset(preset) ? preset : 15;
    } catch {
        return 15;
    }
}

export function writeWorkstationTimeoutPreference(preset: TimeoutPreset): void {
    if (!isTimeoutPreset(preset)) {
        return;
    }

    try {
        globalThis.localStorage?.setItem(PREFERENCE_KEY, String(preset));
        globalThis.window?.dispatchEvent?.(new CustomEvent('nexiam:workstation_timeout_changed', { detail: preset }));
    } catch {
        // Workstation preferences are best-effort when browser storage is unavailable.
    }
}

function parseTimestamp(value: unknown): number | null {
    if (typeof value === 'number' && Number.isFinite(value)) {
        const milliseconds = value * 1000;

        return Number.isFinite(milliseconds) ? milliseconds : null;
    }

    if (typeof value !== 'string' || value.trim() === '') {
        return null;
    }

    const milliseconds = Date.parse(value);

    return Number.isFinite(milliseconds) ? milliseconds : null;
}

export function parseLeaseProjection(raw: unknown): StaffSessionLeaseProjection | null {
    if (typeof raw !== 'object' || raw === null) {
        return null;
    }

    const value = raw as Record<string, unknown>;
    const serverNow = parseTimestamp(value.server_now);
    const idleExpiresAt = parseTimestamp(value.idle_expires_at);
    const absoluteExpiresAt = parseTimestamp(value.absolute_expires_at);
    const effectiveIdleMinutes = value.effective_idle_minutes;

    if (
        serverNow === null
        || idleExpiresAt === null
        || absoluteExpiresAt === null
        || typeof effectiveIdleMinutes !== 'number'
        || !Number.isFinite(effectiveIdleMinutes)
        || typeof value.status !== 'string'
    ) {
        return null;
    }

    return {
        server_now: serverNow,
        idle_expires_at: idleExpiresAt,
        absolute_expires_at: absoluteExpiresAt,
        effective_idle_minutes: effectiveIdleMinutes,
        status: value.status,
    };
}

export function isLeaseExpired(lease: StaffSessionLeaseProjection, now: number): boolean {
    return now >= lease.idle_expires_at || now >= lease.absolute_expires_at;
}

export function remainingIdleSeconds(lease: StaffSessionLeaseProjection, now: number): number {
    return Math.max(0, Math.floor((lease.idle_expires_at - now) / 1000));
}

export async function requestSessionExtension(
    minutes: TimeoutPreset,
    signal?: AbortSignal,
): Promise<StaffSessionLeaseProjection> {
    const response = await fetch('/staff/session-keepalive', {
        method: 'POST',
        credentials: 'same-origin',
        headers: {
            Accept: 'application/json',
            'Content-Type': 'application/json',
            'X-Requested-With': 'XMLHttpRequest',
            ...csrfHeaders(),
        },
        body: JSON.stringify({ extend_minutes: minutes }),
        signal,
    });

    if (!response.ok) {
        if (response.status === 409) {
            const payload: unknown = await response.clone().json().catch(() => null);
            if (isStaffAccountChangedResponse(response.status, payload)) {
                throw new StaffAccountChangedError();
            }
        }
        throw new Error(`Session extension failed with status ${response.status}.`);
    }

    const lease = parseLeaseProjection(await response.json());

    if (lease === null) {
        throw new Error('Session extension returned an invalid lease projection.');
    }

    return lease;
}
