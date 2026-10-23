export type WalkthroughDisposition = 'in_progress' | 'completed' | 'skipped';

export interface WalkthroughScope {
    accountId: string | null;
    institutionId: string | null;
    workspaceId: string | null;
    surface: 'staff' | 'applicant' | 'anonymous';
    key: string;
}

export interface WalkthroughProgressRecord {
    pageKey: string;
    revision: number;
    cursor: string | null;
    disposition: WalkthroughDisposition;
    updatedAt: number;
}

export interface WalkthroughProgressStorage {
    getItem(key: string): string | null;
    setItem(key: string, value: string): void;
}

interface StoredRecord extends WalkthroughProgressRecord {
    scopeKey: string;
}

interface CacheDocument {
    version: 2;
    records: StoredRecord[];
}

const STORAGE_KEY = 'nexiam:walkthrough:v2:cache';
const MAX_RECORDS = 256;
const MAX_SCOPES = 24;
const TERMINAL_RANK: Record<WalkthroughDisposition, number> = {
    in_progress: 0,
    skipped: 1,
    completed: 2,
};
const memoryRecords = new Map<string, StoredRecord>();
const ANONYMOUS_SCOPE: WalkthroughScope = {
    accountId: null,
    institutionId: null,
    workspaceId: null,
    surface: 'anonymous',
    key: 'anonymous:browser',
};

function defaultStorage(): WalkthroughProgressStorage | null {
    try {
        return typeof localStorage === 'undefined' ? null : localStorage;
    } catch {
        return null;
    }
}

function recordKey(scopeKey: string, pageKey: string, revision: number): string {
    return `${scopeKey}:${pageKey}:r${revision}`;
}

function parseCacheDocument(encoded: string): CacheDocument | null {
    let parsed: unknown;
    try {
        parsed = JSON.parse(encoded);
    } catch {
        return null;
    }
    if (typeof parsed !== 'object' || parsed === null) {
        return null;
    }
    const document = parsed as { version?: unknown; records?: unknown };
    if (document.version !== 2 || !Array.isArray(document.records)) {
        return null;
    }

    const records: StoredRecord[] = [];
    for (const value of document.records) {
        if (typeof value !== 'object' || value === null) {
            continue;
        }
        const candidate = value as {
            scopeKey?: unknown;
            pageKey?: unknown;
            revision?: unknown;
            cursor?: unknown;
            disposition?: unknown;
            updatedAt?: unknown;
        };
        if (
            typeof candidate.scopeKey === 'string'
            && typeof candidate.pageKey === 'string'
            && typeof candidate.revision === 'number'
            && Number.isInteger(candidate.revision)
            && candidate.revision > 0
            && (candidate.cursor === null || typeof candidate.cursor === 'string')
            && (candidate.disposition === 'in_progress'
                || candidate.disposition === 'completed'
                || candidate.disposition === 'skipped')
            && typeof candidate.updatedAt === 'number'
            && Number.isFinite(candidate.updatedAt)
        ) {
            records.push({
                scopeKey: candidate.scopeKey,
                pageKey: candidate.pageKey,
                revision: candidate.revision,
                cursor: candidate.cursor,
                disposition: candidate.disposition,
                updatedAt: candidate.updatedAt,
            });
        }
    }
    return { version: 2, records };
}

function readCache(storage: WalkthroughProgressStorage | null): StoredRecord[] {
    if (!storage) {
        return [...memoryRecords.values()];
    }
    try {
        const encoded = storage.getItem(STORAGE_KEY);
        if (encoded) {
            const parsed = parseCacheDocument(encoded);
            if (parsed) {
                return parsed.records;
            }
        }
    } catch {
        // A broken or unavailable cache must not affect the page or tour playback.
    }
    return [...memoryRecords.values()];
}

function mergeRecord(
    existing: StoredRecord | undefined,
    incoming: StoredRecord,
): StoredRecord {
    if (!existing) {
        return incoming;
    }
    const disposition = TERMINAL_RANK[incoming.disposition] > TERMINAL_RANK[existing.disposition]
        ? incoming.disposition
        : existing.disposition;
    const terminal = disposition !== 'in_progress';
    return {
        ...existing,
        cursor: terminal
            ? (disposition === incoming.disposition ? incoming.cursor : existing.cursor)
            : incoming.cursor,
        disposition,
        updatedAt: Math.max(existing.updatedAt, incoming.updatedAt),
    };
}

function boundedRecords(records: StoredRecord[]): StoredRecord[] {
    const byKey = new Map<string, StoredRecord>();
    for (const record of records) {
        const key = recordKey(record.scopeKey, record.pageKey, record.revision);
        byKey.set(key, mergeRecord(byKey.get(key), record));
    }
    const sorted = [...byKey.values()].sort((left, right) => right.updatedAt - left.updatedAt);
    const allowedScopes = new Set<string>();
    const result: StoredRecord[] = [];
    for (const record of sorted) {
        allowedScopes.add(record.scopeKey);
        if (allowedScopes.size > MAX_SCOPES || result.length >= MAX_RECORDS) {
            continue;
        }
        result.push(record);
    }
    return result;
}

export function readWalkthroughProgress(
    scope: WalkthroughScope,
    pageKey: string,
    revision: number,
    storage: WalkthroughProgressStorage | null = defaultStorage(),
): WalkthroughProgressRecord | null {
    const key = recordKey(scope.key, pageKey, revision);
    const record = readCache(storage).find((candidate) => (
        candidate.scopeKey === scope.key
        && candidate.pageKey === pageKey
        && candidate.revision === revision
    )) ?? memoryRecords.get(key);
    if (!record) {
        return null;
    }
    return {
        pageKey: record.pageKey,
        revision: record.revision,
        cursor: record.cursor,
        disposition: record.disposition,
        updatedAt: record.updatedAt,
    };
}

export function saveWalkthroughProgress(
    scope: WalkthroughScope,
    record: WalkthroughProgressRecord,
    storage: WalkthroughProgressStorage | null = defaultStorage(),
): WalkthroughProgressRecord {
    const incoming: StoredRecord = { ...record, scopeKey: scope.key };
    const key = recordKey(scope.key, record.pageKey, record.revision);
    const cached = readCache(storage);
    const existing = cached.find((candidate) => (
        candidate.scopeKey === scope.key
        && candidate.pageKey === record.pageKey
        && candidate.revision === record.revision
    )) ?? memoryRecords.get(key);
    const merged = mergeRecord(existing, incoming);
    memoryRecords.set(key, merged);

    if (storage) {
        try {
            const bounded = boundedRecords([...cached, merged]);
            storage.setItem(STORAGE_KEY, JSON.stringify({ version: 2, records: bounded }));
            memoryRecords.clear();
            for (const stored of bounded) {
                memoryRecords.set(
                    recordKey(stored.scopeKey, stored.pageKey, stored.revision),
                    stored,
                );
            }
        } catch {
            // In-memory state suppresses repeat launches for this active browser session.
        }
    }

    return {
        pageKey: merged.pageKey,
        revision: merged.revision,
        cursor: merged.cursor,
        disposition: merged.disposition,
        updatedAt: merged.updatedAt,
    };
}

export function isWalkthroughExplored(
    scope: WalkthroughScope,
    pageKey: string,
    revision: number,
    serverRecords: readonly WalkthroughProgressRecord[] = [],
): boolean {
    const localRecord = readWalkthroughProgress(scope, pageKey, revision);
    const serverRecord = serverRecords.find((record) => (
        record.pageKey === pageKey && record.revision === revision
    ));
    const record = mergeRecord(
        localRecord ? { ...localRecord, scopeKey: scope.key } : undefined,
        serverRecord ? { ...serverRecord, scopeKey: scope.key } : {
            scopeKey: scope.key,
            pageKey,
            revision,
            cursor: null,
            disposition: 'in_progress',
            updatedAt: 0,
        },
    );
    return record.disposition !== 'in_progress';
}

export function anonymousWalkthroughScope(): WalkthroughScope {
    return ANONYMOUS_SCOPE;
}
