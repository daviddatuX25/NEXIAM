export type CaseConversationMessage = {
    id: string;
    sequence: string;
    sender: { display_name: string; is_self: boolean };
    body: string;
    created_at: string;
};

export type CaseConversationSnapshot = {
    thread: {
        id: string;
        subject: string;
        thread_type: 'internal_ph_guidance' | 'applicant_support' | 'applicant_office_inquiry';
        program_code: string;
        status: 'open' | 'read_only' | 'closed';
        can_read: boolean;
        can_send: boolean;
    };
    messages: CaseConversationMessage[];
    page: {
        oldest_sequence: string | null;
        newest_sequence: string | null;
        has_older: boolean;
        has_more_after: boolean;
    };
    latest_sequence: string;
    own_read_sequence: string;
    unread_count: number;
    observation_token: string;
    as_of: string;
};
export function caseConversationDraftKey(
    principalContext: string | number | null | undefined,
    attemptContext: string | number | null | undefined,
    threadId: string,
    domain: 'case' | 'office' = 'case',
): string {
    const principal =
        principalContext === null || principalContext === undefined
            ? `unknown-principal:${threadId}`
            : String(principalContext);
    const attempt =
        attemptContext === null || attemptContext === undefined
            ? `unknown-attempt:${threadId}`
            : String(attemptContext);

    return `${domain}\u0000${principal}\u0000${attempt}\u0000${threadId}`;
}

export function capturePendingCaseMessage(
    body: string,
    key: string,
): { body: string; idempotencyKey: string } | null {
    const capturedBody = body.trim();

    return capturedBody.length > 0
        ? { body: capturedBody, idempotencyKey: key }
        : null;
}

export function mergeCaseMessages(
    existing: CaseConversationMessage[],
    incoming: CaseConversationMessage[],
): CaseConversationMessage[] {
    const byId = new Map<string, CaseConversationMessage>();

    for (const message of existing) {
        byId.set(message.id, message);
    }

    for (const message of incoming) {
        if (!byId.has(message.id)) {
            byId.set(message.id, message);
        }
    }

    return Array.from(byId.values()).sort((left, right) => {
        const leftSequence = BigInt(left.sequence);
        const rightSequence = BigInt(right.sequence);

        return leftSequence < rightSequence
            ? -1
            : leftSequence > rightSequence
              ? 1
              : 0;
    });
}

export function nextCaseMessageCursor(
    previous: string,
    batch: CaseConversationMessage[],
): string {
    return batch.at(-1)?.sequence ?? previous;
}

export function caseConversationBackoffMs(
    failures: number,
    retryAfter?: number,
): number {
    const failureIndex = Number.isFinite(failures)
        ? Math.max(1, Math.floor(failures))
        : 1;
    const backoff = Math.min(
        60_000,
        10_000 * 2 ** Math.min(failureIndex - 1, 3),
    );
    const validRetryAfter =
        typeof retryAfter === 'number' &&
        Number.isFinite(retryAfter) &&
        retryAfter > 0
            ? retryAfter
            : 0;

    return Math.max(backoff, validRetryAfter);
}

export function caseConversationNeedsNavigationConfirmation(
    pathname: string,
    hasUnsavedState: boolean,
    accessAvailable: boolean,
): boolean {
    if (!hasUnsavedState || !accessAvailable) {
        return false;
    }

    return !/\/(logout|sign-out|login|authenticate)(\/|$)/i.test(pathname);
}
