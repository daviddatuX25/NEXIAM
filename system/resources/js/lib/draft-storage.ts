export interface DraftEnvelope<T> {
    version: 1;
    formId: string;
    context: Record<string, unknown>;
    updatedAt: number;
    expiresAt: number;
    data: T;
}

const storageKey = (formId: string): string => `nexiam:draft:v1:${formId}`;

export function saveDraft<T>(
    formId: string,
    context: Record<string, unknown>,
    data: T,
    ttlMs = 15 * 60 * 1000,
): boolean {
    try {
        const updatedAt = Date.now();
        const envelope: DraftEnvelope<T> = {
            version: 1,
            formId,
            context,
            updatedAt,
            expiresAt: updatedAt + ttlMs,
            data,
        };

        window.sessionStorage.setItem(storageKey(formId), JSON.stringify(envelope));
        return true;
    } catch {
        return false;
    }
}

export function loadDraft<T>(
    formId: string,
    expectedContext?: Record<string, unknown>,
): T | null {
    let serialized: string | null;

    try {
        serialized = window.sessionStorage.getItem(storageKey(formId));
    } catch {
        return null;
    }

    if (serialized === null) {
        return null;
    }

    let envelope: DraftEnvelope<T>;
    try {
        envelope = JSON.parse(serialized) as DraftEnvelope<T>;
    } catch {
        return null;
    }

    if (
        envelope === null ||
        typeof envelope !== 'object' ||
        envelope.version !== 1 ||
        envelope.formId !== formId ||
        typeof envelope.expiresAt !== 'number' ||
        !('data' in envelope)
    ) {
        return null;
    }

    if (Date.now() > envelope.expiresAt) {
        clearDraft(formId);
        return null;
    }

    if (expectedContext !== undefined) {
        for (const [key, expectedValue] of Object.entries(expectedContext)) {
            if (envelope.context?.[key] !== expectedValue) {
                clearDraft(formId);
                return null;
            }
        }
    }

    return envelope.data;
}

export function clearDraft(formId: string): void {
    try {
        window.sessionStorage.removeItem(storageKey(formId));
    } catch {
        // Session storage can be disabled by the browser or host environment.
    }
}

export function hasDraft(
    formId: string,
    expectedContext?: Record<string, unknown>,
): boolean {
    return loadDraft(formId, expectedContext) !== null;
}
