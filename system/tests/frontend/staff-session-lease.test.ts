// @vitest-environment jsdom
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import {
    StaffAccountChangedError,
    isLeaseExpired,
    parseLeaseProjection,
    readWorkstationTimeoutPreference,
    remainingIdleSeconds,
    requestSessionExtension,
    writeWorkstationTimeoutPreference,
    type StaffSessionLeaseProjection,
} from '../../resources/js/lib/staff-session-lease';

const preferenceKey = 'nexiam.workstation_timeout_preset';
const validProjection = {
    server_now: 1_800_000_000,
    idle_expires_at: 1_800_000_900,
    absolute_expires_at: 1_800_028_800,
    effective_idle_minutes: 15,
    status: 'active',
};

function createStorage(initialValue: string | null = null): Storage {
    let value = initialValue;

    return {
        getItem: vi.fn(() => value),
        setItem: vi.fn((_key: string, nextValue: string) => {
            value = nextValue;
        }),
        removeItem: vi.fn(() => {
            value = null;
        }),
        clear: vi.fn(() => {
            value = null;
        }),
        key: vi.fn(() => null),
        get length() {
            return value === null ? 0 : 1;
        },
    };
}

describe('staff-session-lease', () => {
    beforeEach(() => {
        vi.stubGlobal('localStorage', createStorage());
    });

    afterEach(() => {
        vi.unstubAllGlobals();
        vi.restoreAllMocks();
    });

    it('reads only allowlisted workstation timeout preferences and defaults to 15', () => {
        for (const preset of [15, 30, 60, 480] as const) {
            vi.stubGlobal('localStorage', createStorage(String(preset)));
            expect(readWorkstationTimeoutPreference()).toBe(preset);
        }

        for (const invalid of ['0', '20', '90', '15.5', 'invalid', '']) {
            vi.stubGlobal('localStorage', createStorage(invalid));
            expect(readWorkstationTimeoutPreference()).toBe(15);
        }
    });

    it('falls back to 15 when browser storage is blocked and ignores write failures', () => {
        vi.stubGlobal('localStorage', {
            getItem: vi.fn(() => {
                throw new Error('storage blocked');
            }),
            setItem: vi.fn(() => {
                throw new Error('storage blocked');
            }),
        });

        expect(readWorkstationTimeoutPreference()).toBe(15);
        expect(() => writeWorkstationTimeoutPreference(60)).not.toThrow();
    });

    it('writes the selected allowlisted preference to the expected local key', () => {
        const storage = createStorage();
        vi.stubGlobal('localStorage', storage);

        writeWorkstationTimeoutPreference(30);

        expect(storage.setItem).toHaveBeenCalledWith(preferenceKey, '30');
    });

    it('parses numeric seconds and ISO timestamps as millisecond timestamps', () => {
        const projection = parseLeaseProjection({
            ...validProjection,
            server_now: '2027-01-15T08:00:00.000Z',
            idle_expires_at: '2027-01-15T08:15:00.000Z',
            absolute_expires_at: '2027-01-15T16:00:00.000Z',
        });

        expect(projection).toEqual({
            server_now: Date.parse('2027-01-15T08:00:00.000Z'),
            idle_expires_at: Date.parse('2027-01-15T08:15:00.000Z'),
            absolute_expires_at: Date.parse('2027-01-15T16:00:00.000Z'),
            effective_idle_minutes: 15,
            status: 'active',
        });
        expect(parseLeaseProjection(validProjection)?.server_now).toBe(1_800_000_000_000);
    });

    it('rejects missing or malformed lease fields and timestamps', () => {
        expect(parseLeaseProjection(null)).toBeNull();
        expect(parseLeaseProjection({ ...validProjection, idle_expires_at: 'not-a-date' })).toBeNull();
        expect(parseLeaseProjection({ ...validProjection, server_now: Number.NaN })).toBeNull();
        expect(parseLeaseProjection({ ...validProjection, effective_idle_minutes: '15' })).toBeNull();
        expect(parseLeaseProjection({ ...validProjection, status: null })).toBeNull();
    });

    it('expires at idle or absolute deadline equality', () => {
        const lease = parseLeaseProjection(validProjection) as StaffSessionLeaseProjection;

        expect(isLeaseExpired(lease, lease.idle_expires_at - 1)).toBe(false);
        expect(isLeaseExpired(lease, lease.idle_expires_at)).toBe(true);
        expect(isLeaseExpired(lease, lease.absolute_expires_at)).toBe(true);
    });

    it('calculates floored remaining idle seconds without going below zero', () => {
        const lease = parseLeaseProjection(validProjection) as StaffSessionLeaseProjection;

        expect(remainingIdleSeconds(lease, lease.idle_expires_at - 2_999)).toBe(2);
        expect(remainingIdleSeconds(lease, lease.idle_expires_at)).toBe(0);
        expect(remainingIdleSeconds(lease, lease.idle_expires_at + 1_000)).toBe(0);
    });

    it('posts an explicit extension with same-origin credentials, CSRF, and parsed confirmation', async () => {
        const meta = document.createElement('meta');
        meta.name = 'csrf-token';
        meta.content = 'csrf-token-value';
        document.head.appendChild(meta);
        const fetchMock = vi.fn().mockResolvedValue({
            ok: true,
            status: 200,
            json: async () => validProjection,
        });
        vi.stubGlobal('fetch', fetchMock);
        const controller = new AbortController();

        const projection = await requestSessionExtension(30, controller.signal);

        expect(projection).toEqual({
            server_now: 1_800_000_000_000,
            idle_expires_at: 1_800_000_900_000,
            absolute_expires_at: 1_800_028_800_000,
            effective_idle_minutes: 15,
            status: 'active',
        });
        expect(fetchMock).toHaveBeenCalledWith('/staff/session-keepalive', expect.objectContaining({
            method: 'POST',
            credentials: 'same-origin',
            signal: controller.signal,
            headers: expect.objectContaining({
                Accept: 'application/json',
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': 'csrf-token-value',
            }),
            body: JSON.stringify({ extend_minutes: 30 }),
        }));
    });

    it('rejects unsuccessful responses and malformed confirmation projections', async () => {
        vi.stubGlobal('fetch', vi.fn().mockResolvedValue({ ok: false, status: 419 }));
        await expect(requestSessionExtension(15)).rejects.toThrow('status 419');

        vi.stubGlobal('fetch', vi.fn().mockResolvedValue({
            ok: true,
            status: 200,
            json: async () => ({ status: 'ok' }),
        }));
        await expect(requestSessionExtension(15)).rejects.toThrow('invalid lease projection');
        vi.stubGlobal('fetch', vi.fn().mockResolvedValue({
            ok: false,
            status: 409,
            clone: () => ({ json: async () => ({ error: 'staff_account_changed' }) }),
        }));
        await expect(requestSessionExtension(15)).rejects.toBeInstanceOf(StaffAccountChangedError);
    });
});
