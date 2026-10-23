const STORAGE_KEY = 'nexiam.staff.last_activity_at';

interface StaffIdleMarker {
  generation: string;
  lastActivityAt: number;
  idleDurationMinutes?: number;
}

const VALID_IDLE_DURATIONS = [15, 30, 60] as const;

function isIdleDuration(value: unknown): value is (typeof VALID_IDLE_DURATIONS)[number] {
  return typeof value === 'number'
    && VALID_IDLE_DURATIONS.includes(value as (typeof VALID_IDLE_DURATIONS)[number]);
}

function isNonEmptyGeneration(generation: unknown): generation is string {
  return typeof generation === 'string' && generation.trim().length > 0;
}

function getSessionStorage(): Storage | null {
  try {
    if (typeof window === 'undefined') {
      return null;
    }

    return window.sessionStorage;
  } catch {
    return null;
  }
}

export function readStaffIdleMarker(generation: string, now = Date.now()): number | null {
  if (!isNonEmptyGeneration(generation) || !Number.isFinite(now)) {
    return null;
  }

  try {
    const raw = getSessionStorage()?.getItem(STORAGE_KEY);
    if (raw === null || raw === undefined) {
      return null;
    }

    const marker = JSON.parse(raw) as Partial<StaffIdleMarker> | null;
    if (
      marker === null
      || typeof marker !== 'object'
      || Array.isArray(marker)
      || !isNonEmptyGeneration(marker.generation)
      || marker.generation !== generation
      || typeof marker.lastActivityAt !== 'number'
      || !Number.isFinite(marker.lastActivityAt)
      || marker.lastActivityAt <= 0
      || marker.lastActivityAt > now
      || (marker.idleDurationMinutes !== undefined && !isIdleDuration(marker.idleDurationMinutes))
      || now - marker.lastActivityAt > (marker.idleDurationMinutes ?? 15) * 60 * 1000
    ) {
      return null;
    }

    return marker.lastActivityAt;
  } catch {
    return null;
  }
}

export function writeStaffIdleMarker(
  generation: string,
  timestamp: number,
  idleDurationMinutes: (typeof VALID_IDLE_DURATIONS)[number] = 15,
): void {
  if (!isNonEmptyGeneration(generation) || !Number.isFinite(timestamp) || !isIdleDuration(idleDurationMinutes)) {
    return;
  }

  try {
    const marker: StaffIdleMarker = { generation, lastActivityAt: timestamp };
    if (idleDurationMinutes !== 15) {
      marker.idleDurationMinutes = idleDurationMinutes;
    }
    getSessionStorage()?.setItem(STORAGE_KEY, JSON.stringify(marker));
  } catch {
    // Storage may be unavailable in privacy-restricted browser contexts.
  }
}

export function clearStaffIdleMarker(): void {
  try {
    getSessionStorage()?.removeItem(STORAGE_KEY);
  } catch {
    // Storage may be unavailable in privacy-restricted browser contexts.
  }
}
