// @vitest-environment jsdom
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import {
    clearDraft,
    hasDraft,
    loadDraft,
    saveDraft,
} from '../../resources/js/lib/draft-storage';

const sessionStorageDescriptor = Object.getOwnPropertyDescriptor(window, 'sessionStorage');

beforeEach(() => {
    window.sessionStorage.clear();
    vi.useFakeTimers();
    vi.setSystemTime(new Date('2026-10-14T12:00:00.000Z'));
});

afterEach(() => {
    vi.useRealTimers();
    vi.restoreAllMocks();
    if (sessionStorageDescriptor) {
        Object.defineProperty(window, 'sessionStorage', sessionStorageDescriptor);
    }
});

describe('draft storage', () => {
    it('saves and loads a draft with its context and expiry metadata', () => {
        const data = { name: 'Ada', cycle_id: 12 };

        expect(saveDraft('applicant', { cycleId: 12 }, data, 60_000)).toBe(true);
        expect(JSON.parse(window.sessionStorage.getItem('nexiam:draft:v1:applicant') ?? 'null')).toEqual({
            version: 1,
            formId: 'applicant',
            context: { cycleId: 12 },
            updatedAt: Date.now(),
            expiresAt: Date.now() + 60_000,
            data,
        });
        expect(loadDraft<typeof data>('applicant', { cycleId: 12 })).toEqual(data);
        expect(hasDraft('applicant', { cycleId: 12 })).toBe(true);
    });

    it('returns null and removes drafts after expiry', () => {
        saveDraft('expired', {}, { name: 'Ada' }, 1_000);
        vi.advanceTimersByTime(1_001);

        expect(loadDraft('expired')).toBeNull();
        expect(window.sessionStorage.getItem('nexiam:draft:v1:expired')).toBeNull();
    });

    it('rejects and removes a draft when an expected context value changes', () => {
        saveDraft('contextual', { cycleId: 12, windowId: 4 }, { name: 'Ada' });

        expect(loadDraft('contextual', { cycleId: 13 })).toBeNull();
        expect(window.sessionStorage.getItem('nexiam:draft:v1:contextual')).toBeNull();
        expect(hasDraft('contextual', { windowId: 5 })).toBe(false);
    });

    it('clears a stored draft', () => {
        saveDraft('clearable', {}, { name: 'Ada' });

        clearDraft('clearable');

        expect(loadDraft('clearable')).toBeNull();
        expect(hasDraft('clearable')).toBe(false);
    });

    it('returns null for malformed stored JSON', () => {
        window.sessionStorage.setItem('nexiam:draft:v1:malformed', '{');

        expect(loadDraft('malformed')).toBeNull();
    });

    it('handles quota errors and unavailable session storage without throwing', () => {
        const quotaError = new DOMException('Storage full', 'QuotaExceededError');
        vi.spyOn(Storage.prototype, 'setItem').mockImplementation(() => {
            throw quotaError;
        });
        expect(saveDraft('quota', {}, { name: 'Ada' })).toBe(false);

        Object.defineProperty(window, 'sessionStorage', {
            configurable: true,
            get() {
                throw new DOMException('Storage is disabled', 'SecurityError');
            },
        });

        expect(saveDraft('unavailable', {}, { name: 'Ada' })).toBe(false);
        expect(loadDraft('unavailable')).toBeNull();
        expect(() => clearDraft('unavailable')).not.toThrow();
    });
});
