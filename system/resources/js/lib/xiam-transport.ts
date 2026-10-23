/**
 * Client transport for OpenSpec 3 Xiam runs — never treats responses as authz.
 */
import { newIdempotencyKey } from '@/lib/utils.js';
import type { XiamModeView, XiamUsageView } from '@/types/xiam';

export type XiamTurnAccepted = {
    conversation_id: string;
    turn_id: string;
    run_id: string;
    context_epoch: string;
    cursor: number;
    usage?: XiamUsageView;
};

export type XiamTransportError = Error & {
    status?: number;
    code?: string;
    usage?: XiamUsageView;
};

function isUsageView(value: unknown): value is XiamUsageView {
    if (!value || typeof value !== 'object') return false;
    const usage = value as Partial<XiamUsageView>;
    return (
        ['available', 'near_limit', 'exhausted'].includes(
            String(usage.state),
        ) &&
        Number.isInteger(usage.used) &&
        Number(usage.used) >= 0 &&
        Number.isInteger(usage.limit) &&
        (Number(usage.limit) === -1 || Number(usage.limit) >= 0) &&
        (usage.remaining === null ||
            (Number.isInteger(usage.remaining) &&
                Number(usage.remaining) >= 0)) &&
        Number.isInteger(usage.credit_balance) &&
        Number(usage.credit_balance) >= 0 &&
        typeof usage.unit === 'string' &&
        typeof usage.period === 'string' &&
        (usage.resets_at === null || typeof usage.resets_at === 'string')
    );
}

function isModeView(value: unknown): value is XiamModeView {
    if (!value || typeof value !== 'object') return false;
    const view = value as Partial<XiamModeView>;
    return (
        (view.active_mode === 'inquiry' || view.active_mode === 'assistant') &&
        Array.isArray(view.interaction_modes) &&
        view.interaction_modes.every(
            (option) =>
                option &&
                ['inquiry', 'assistant'].includes(option.key) &&
                typeof option.label === 'string',
        ) &&
        (view.interaction_modes.length === 0
            ? view.active_mode === 'inquiry'
            : view.interaction_modes.some(
                  (option) => option.key === view.active_mode,
              )) &&
        (view.context_epoch === null ||
            typeof view.context_epoch === 'string') &&
        isUsageView(view.usage)
    );
}

async function responseBody(res: Response): Promise<Record<string, unknown>> {
    const body = await res.json().catch(() => ({}));
    return body && typeof body === 'object'
        ? (body as Record<string, unknown>)
        : {};
}

function transportError(
    res: Response,
    body: Record<string, unknown>,
    fallback: string,
): XiamTransportError {
    const code =
        typeof body.error === 'string'
            ? body.error
            : typeof body.code === 'string'
              ? body.code
              : fallback;
    const error = new Error(code) as XiamTransportError;
    error.status = res.status;
    error.code = code;
    if (isUsageView(body.usage)) error.usage = body.usage;
    return error;
}

export type XiamStreamPart =
    | { type: 'text-start'; id: string }
    | { type: 'text-delta'; id: string; delta: string }
    | { type: 'text-end'; id: string }
    | { type: 'data-xiam-avatar'; data: Record<string, unknown> }
    | { type: 'data-xiam-proposal'; data: Record<string, unknown> }
    | { type: 'data-xiam-steps'; data: Record<string, unknown> }
    | { type: 'data-xiam-diff'; data: Record<string, unknown> }
    | { type: 'data-xiam-mode-request'; data: Record<string, unknown> }
    | { type: 'data-xiam-citation'; data: Record<string, unknown> }
    | { type: 'done' };

export type XiamSnapshot = {
    conversation_id: string;
    context_epoch: string;
    cursor: number;
    usage?: XiamUsageView;
    run: { id: string; status: string } | null;
    messages: Array<{
        role: 'user' | 'assistant';
        content: string;
        citations?: Array<{
            version_id?: string;
            source_title?: string | null;
            excerpt?: string;
        }>;
        finish_reason?: string | null;
    }>;
    proposals?: Array<{ id: string; status: string }>;
    events: Array<{
        seq: number;
        type: string;
        payload: Record<string, unknown>;
    }>;
};

export async function fetchXiamMode(): Promise<XiamModeView> {
    const res = await fetch('/xiam/mode', {
        headers: {
            Accept: 'application/json',
            'X-Requested-With': 'XMLHttpRequest',
        },
        credentials: 'same-origin',
        cache: 'no-store',
    });
    const body = await responseBody(res);
    if (!res.ok) throw transportError(res, body, 'mode_view_failed');
    if (!isModeView(body)) throw new Error('mode_view_invalid');
    return body;
}

export async function setXiamMode(
    mode: XiamModeView['active_mode'],
    contextEpoch: string | null,
): Promise<XiamModeView> {
    const res = await fetch('/xiam/mode', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            Accept: 'application/json',
            ...xiamCsrfHeaders(),
            'X-Requested-With': 'XMLHttpRequest',
        },
        credentials: 'same-origin',
        body: JSON.stringify({ mode, context_epoch: contextEpoch }),
    });
    const body = await responseBody(res);
    if (!res.ok) throw transportError(res, body, 'mode_change_failed');
    if (!isModeView(body) || body.active_mode !== mode) {
        throw new Error('mode_ack_invalid');
    }
    return body;
}

export function xiamCsrfHeaders(): Record<string, string> {
    const headers: Record<string, string> = {};
    if (typeof document !== 'undefined') {
        const match = document.cookie.match(/(?:^|;\s*)XSRF-TOKEN=([^;]+)/);
        if (match && match[1]) {
            try {
                headers['X-XSRF-TOKEN'] = decodeURIComponent(match[1]);
            } catch {
                headers['X-XSRF-TOKEN'] = match[1];
            }
        }
        const metaEl = document.querySelector('meta[name="csrf-token"]');
        if (metaEl instanceof HTMLMetaElement && metaEl.content) {
            headers['X-CSRF-TOKEN'] = metaEl.content;
        }
    }
    return headers;
}

export async function submitXiamTurn(input: {
    message: string;
    pagePackKey?: string | null;
    pagePackVersion?: number | null;
    idempotencyKey?: string;
}): Promise<XiamTurnAccepted> {
    const res = await fetch('/xiam/turns', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            Accept: 'application/json',
            ...xiamCsrfHeaders(),
            'X-Requested-With': 'XMLHttpRequest',
        },
        credentials: 'same-origin',
        body: JSON.stringify({
            message: input.message,
            page_pack_key: input.pagePackKey ?? null,
            page_pack_version: input.pagePackVersion ?? null,
            idempotency_key: input.idempotencyKey ?? newIdempotencyKey(),
        }),
    });

    if (!res.ok) {
        const body = await responseBody(res);
        const fallback =
            res.status === 419
                ? 'csrf_expired'
                : res.status === 403
                  ? 'surface_denied'
                  : 'turn_failed';
        throw transportError(res, body, fallback);
    }

    const accepted = (await res.json()) as XiamTurnAccepted;
    if (accepted.usage && !isUsageView(accepted.usage)) {
        delete accepted.usage;
    }
    return accepted;
}


export async function fetchXiamSnapshot(
    conversationId: string,
    after = 0,
): Promise<XiamSnapshot> {
    const res = await fetch(
        `/xiam/conversations/${conversationId}/snapshot?after=${after}`,
        {
            headers: {
                Accept: 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
            },
            credentials: 'same-origin',
        },
    );

    if (!res.ok) {
        throw new Error('snapshot_denied');
    }

    const snapshot = (await res.json()) as XiamSnapshot;
    if (snapshot.usage && !isUsageView(snapshot.usage)) {
        delete snapshot.usage;
    }
    return snapshot;
}

export function parseXiamStreamChunk(input: string): {
    parts: XiamStreamPart[];
    remainder: string;
} {
    const frames = input.split(/\r?\n\r?\n/);
    const remainder = frames.pop() ?? '';
    const parts: XiamStreamPart[] = [];

    for (const frame of frames) {
        const data = frame
            .split(/\r?\n/)
            .filter((line) => line.startsWith('data:'))
            .map((line) => line.slice(5).trimStart())
            .join('\n')
            .trim();

        if (!data) {
            continue;
        }

        if (data === '[DONE]') {
            parts.push({ type: 'done' });
            continue;
        }

        try {
            const part = JSON.parse(data) as XiamStreamPart;
            if (part && typeof part.type === 'string') {
                parts.push(part);
            }
        } catch {
            // Ignore malformed frames; the snapshot remains the durable fallback.
        }
    }

    return { parts, remainder };
}

export async function streamXiamConversation(
    conversationId: string,
    after: number,
    onPart: (part: XiamStreamPart) => void,
    signal?: AbortSignal,
): Promise<void> {
    const res = await fetch(
        `/xiam/conversations/${conversationId}/stream?after=${after}`,
        {
            headers: {
                Accept: 'text/event-stream',
                'Cache-Control': 'no-cache',
                'X-Requested-With': 'XMLHttpRequest',
            },
            credentials: 'same-origin',
            signal,
        },
    );

    if (!res.ok) {
        throw new Error('stream_denied');
    }

    if (!res.body) {
        throw new Error('stream_unavailable');
    }

    const reader = res.body.getReader();
    const decoder = new TextDecoder();
    let buffer = '';

    try {
        while (true) {
            const next = await reader.read();
            buffer += decoder.decode(next.value ?? new Uint8Array(), {
                stream: !next.done,
            });

            const parsed = parseXiamStreamChunk(buffer);
            buffer = parsed.remainder;
            for (const part of parsed.parts) {
                onPart(part);
                if (part.type === 'done') {
                    return;
                }
            }

            if (next.done) {
                break;
            }
        }

        const final = parseXiamStreamChunk(`${buffer}\n\n`);
        for (const part of final.parts) {
            onPart(part);
        }
    } finally {
        reader.releaseLock();
    }
}

export async function clearXiamConversation(): Promise<{
    cleared: boolean;
    conversation_id: string | null;
    context_epoch?: string;
}> {
    const res = await fetch('/xiam/conversations/clear', {
        method: 'POST',
        headers: {
            Accept: 'application/json',
            'Content-Type': 'application/json',
            ...xiamCsrfHeaders(),
            'X-Requested-With': 'XMLHttpRequest',
        },
        credentials: 'same-origin',
        body: '{}',
    });
    if (!res.ok) {
        throw new Error('clear_denied');
    }
    return (await res.json()) as {
        cleared: boolean;
        conversation_id: string | null;
        context_epoch?: string;
    };
}

export async function confirmXiamProposal(
    proposalId: string,
    options: { phrase?: string; idempotencyKey?: string } = {},
): Promise<{
    ok: boolean;
    proposal: { id: string; status: string };
    result: Record<string, unknown>;
}> {
    const res = await fetch(`/xiam/proposals/${proposalId}/confirm`, {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            Accept: 'application/json',
            ...xiamCsrfHeaders(),
            'X-Requested-With': 'XMLHttpRequest',
        },
        credentials: 'same-origin',
        body: JSON.stringify({
            idempotency_key: options.idempotencyKey ?? newIdempotencyKey(),
            phrase: options.phrase ?? '',
        }),
    });
    if (!res.ok) {
        const body = await res.json().catch(() => ({}));
        throw new Error(
            typeof body.error === 'string' ? body.error : 'confirm_failed',
        );
    }
    return (await res.json()) as {
        ok: boolean;
        proposal: { id: string; status: string };
        result: Record<string, unknown>;
    };
}

export async function cancelXiamProposal(
    proposalId: string,
): Promise<{ ok: boolean; proposal: { id: string; status: string } }> {
    const res = await fetch(`/xiam/proposals/${proposalId}/cancel`, {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            Accept: 'application/json',
            ...xiamCsrfHeaders(),
            'X-Requested-With': 'XMLHttpRequest',
        },
        credentials: 'same-origin',
        body: '{}',
    });
    if (!res.ok) {
        const body = await res.json().catch(() => ({}));
        throw new Error(
            typeof body.error === 'string' ? body.error : 'cancel_failed',
        );
    }
    return (await res.json()) as {
        ok: boolean;
        proposal: { id: string; status: string };
    };
}
