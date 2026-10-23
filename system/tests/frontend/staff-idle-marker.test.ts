// @vitest-environment jsdom
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import {
  clearStaffIdleMarker,
  readStaffIdleMarker,
  writeStaffIdleMarker,
} from '../../resources/js/lib/staff-idle-marker';

const STORAGE_KEY = 'nexiam.staff.last_activity_at';

let sessionStorageDescriptor: PropertyDescriptor | undefined;

beforeEach(() => {
  sessionStorageDescriptor = Object.getOwnPropertyDescriptor(window, 'sessionStorage');
  window.sessionStorage.clear();
});

afterEach(() => {
  vi.restoreAllMocks();
  if (sessionStorageDescriptor) {
    Object.defineProperty(window, 'sessionStorage', sessionStorageDescriptor);
  } else {
    Reflect.deleteProperty(window, 'sessionStorage');
  }
});

describe('staff idle marker', () => {
  it('preserves the exact activity timestamp for the same generation', () => {
    const generation = 'staff-generation-a';
    const timestamp = 1_700_000_000_000;

    writeStaffIdleMarker(generation, timestamp);

    expect(window.sessionStorage.getItem(STORAGE_KEY)).toBe(JSON.stringify({
      generation,
      lastActivityAt: timestamp,
    }));
    expect(readStaffIdleMarker(generation, timestamp)).toBe(timestamp);
  });

  it('rejects a marker from a different generation', () => {
    writeStaffIdleMarker('staff-generation-a', 1_700_000_000_000);

    expect(readStaffIdleMarker('staff-generation-b', 1_700_000_000_001)).toBeNull();
  });

  it('rejects legacy, malformed, future, empty-generation, and invalid timestamp values', () => {
    const generation = 'staff-generation-a';

    window.sessionStorage.setItem(STORAGE_KEY, '1700000000000');
    expect(readStaffIdleMarker(generation, 1_700_000_000_001)).toBeNull();

    window.sessionStorage.setItem(STORAGE_KEY, '{not-json');
    expect(readStaffIdleMarker(generation, 1_700_000_000_001)).toBeNull();

    window.sessionStorage.setItem(STORAGE_KEY, JSON.stringify({
      generation,
      lastActivityAt: 1_700_000_000_002,
    }));
    expect(readStaffIdleMarker(generation, 1_700_000_000_001)).toBeNull();

    window.sessionStorage.setItem(STORAGE_KEY, JSON.stringify({
      generation: '',
      lastActivityAt: 1_700_000_000_000,
    }));
    expect(readStaffIdleMarker(generation, 1_700_000_000_001)).toBeNull();

    for (const lastActivityAt of [0, -1, Number.NaN, Number.POSITIVE_INFINITY]) {
      window.sessionStorage.setItem(STORAGE_KEY, JSON.stringify({ generation, lastActivityAt }));
      expect(readStaffIdleMarker(generation, 1_700_000_000_001)).toBeNull();
    }

    expect(readStaffIdleMarker('', 1_700_000_000_001)).toBeNull();
  });

  it('safely degrades when accessing the sessionStorage getter throws', () => {
    Object.defineProperty(window, 'sessionStorage', {
      configurable: true,
      get() {
        throw new Error('Storage access blocked');
      },
    });

    expect(readStaffIdleMarker('staff-generation-a')).toBeNull();
    expect(() => writeStaffIdleMarker('staff-generation-a', Date.now())).not.toThrow();
    expect(() => clearStaffIdleMarker()).not.toThrow();
  });

  it('safely degrades when storage reads, writes, or clears throw', () => {
    vi.spyOn(Storage.prototype, 'getItem').mockImplementation(() => {
      throw new Error('Storage read blocked');
    });
    vi.spyOn(Storage.prototype, 'setItem').mockImplementation(() => {
      throw new Error('Storage write blocked');
    });
    vi.spyOn(Storage.prototype, 'removeItem').mockImplementation(() => {
      throw new Error('Storage clear blocked');
    });

    expect(readStaffIdleMarker('staff-generation-a')).toBeNull();
    expect(() => writeStaffIdleMarker('staff-generation-a', Date.now())).not.toThrow();
    expect(() => clearStaffIdleMarker()).not.toThrow();
  });

  it('clears a same-tab marker so a later read starts a fresh interval', () => {
    const generation = 'staff-generation-a';
    const timestamp = 1_700_000_000_000;
    writeStaffIdleMarker(generation, timestamp);

    clearStaffIdleMarker();

    expect(readStaffIdleMarker(generation, timestamp)).toBeNull();
  });
});
