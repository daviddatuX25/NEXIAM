const DATABASE_NAME = 'nexiam-exam-monitoring-outbox';
const DATABASE_VERSION = 1;
const STORE_NAME = 'attendance';

let databasePromise;

function openDatabase() {
    if (typeof indexedDB === 'undefined') {
        return Promise.reject(new Error('This browser does not provide durable offline storage.'));
    }

    if (!databasePromise) {
        const pending = Promise.withResolvers();
        databasePromise = pending.promise;
        const request = indexedDB.open(DATABASE_NAME, DATABASE_VERSION);
        request.onupgradeneeded = () => {
            const database = request.result;
            if (!database.objectStoreNames.contains(STORE_NAME)) {
                const store = database.createObjectStore(STORE_NAME, { keyPath: 'key' });
                store.createIndex('scope', ['actorId', 'sessionId'], { unique: false });
            }
        };
        request.onsuccess = () => pending.resolve(request.result);
        request.onerror = () => {
            databasePromise = null;
            pending.reject(request.error ?? new Error('Unable to open durable offline storage.'));
        };
        request.onblocked = () => {
            databasePromise = null;
            pending.reject(new Error('Offline storage is blocked by another browser tab.'));
        };
    }

    return databasePromise;
}

function scopedKey(actorId, sessionId, idempotencyKey) {
    return `${String(actorId)}:${String(sessionId)}:${idempotencyKey}`;
}

function transactionResult(transaction, request) {
    const result = Promise.withResolvers();
    transaction.oncomplete = () => result.resolve(request?.result);
    transaction.onerror = () => result.reject(transaction.error ?? new Error('Offline storage transaction failed.'));
    transaction.onabort = () => result.reject(transaction.error ?? new Error('Offline storage transaction was aborted.'));
    return result.promise;
}

export async function queueAttendanceCommand(command) {
    const database = await openDatabase();
    const transaction = database.transaction(STORE_NAME, 'readwrite');
    const store = transaction.objectStore(STORE_NAME);
    const record = {
        ...command,
        key: scopedKey(command.actorId, command.sessionId, command.idempotencyKey),
        state: 'pending',
        queuedAt: new Date().toISOString(),
        conflictMessage: null,
    };
    const request = store.put(record);
    await transactionResult(transaction, request);
    return record;
}

export async function listAttendanceCommands(actorId, sessionId) {
    const database = await openDatabase();
    const transaction = database.transaction(STORE_NAME, 'readonly');
    const request = transaction.objectStore(STORE_NAME).index('scope').getAll([String(actorId), String(sessionId)]);
    const rows = await transactionResult(transaction, request);
    return rows.sort((left, right) => left.queuedAt.localeCompare(right.queuedAt));
}

export async function updateAttendanceCommand(command, updates) {
    const database = await openDatabase();
    const transaction = database.transaction(STORE_NAME, 'readwrite');
    const store = transaction.objectStore(STORE_NAME);
    const request = store.get(scopedKey(command.actorId, command.sessionId, command.idempotencyKey));
    request.onsuccess = () => {
        if (request.result) {
            store.put({ ...request.result, ...updates });
        }
    };
    await transactionResult(transaction);
}

export async function removeAttendanceCommand(command) {
    const database = await openDatabase();
    const transaction = database.transaction(STORE_NAME, 'readwrite');
    transaction.objectStore(STORE_NAME).delete(scopedKey(command.actorId, command.sessionId, command.idempotencyKey));
    await transactionResult(transaction);
}

export function isOfflineStorageAvailable() {
    return typeof indexedDB !== 'undefined';
}
