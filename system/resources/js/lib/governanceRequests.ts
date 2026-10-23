import { csrfHeaders } from './utils.js';

export function governanceRoute(name: string, parameters: Record<string, string | number> | number = {}): string {
    const p = typeof parameters === 'number' ? { thread: parameters } : parameters;
    const part = (key: string): string => {
        const value = p[key as keyof typeof p];
        if (value === undefined || value === null) throw new Error(`Missing route parameter: ${key}`);
        return encodeURIComponent(String(value));
    };
    const base = '/setup/admission-support';
    if (name === 'setup.decision-support.index') return '/setup/decision-support';
    if (name === 'setup.quotas' || name === 'setup.quotas.store') return '/setup/quotas';
    if (name === 'threads.messages.store') return `/threads/${part('thread')}/messages`;
    if (name === 'program-head.threads.open') return `/staff/programs/${part('program')}/applicants/${part('attempt')}/threads`;
    if (name === 'case-conversations.applicant.open') return `/staff/programs/${part('program')}/applicants/${part('attempt')}/support-conversations`;
    if (name === 'setup.admission-support.advising-note.use') return `${base}/applicants/${part('attempt')}/advising-note/use`;
    if (name.startsWith('setup.quotas.registrar-grants.')) {
        return `/setup/quotas/programs/${part('program')}/cycles/${part('cycle')}/registrar-grants${name.endsWith('.revoke') ? '/revoke' : ''}`;
    }
    const action = name.replace('setup.admission-support.', '');
    if (['benchmarks.import', 'benchmarks.publish', 'benchmarks.activate'].includes(action)) {
        return `${base}/cycles/${part('cycle')}/benchmarks/${action.split('.')[1]}`;
    }
    const paths: Record<string, string> = {
        'governance.show': 'governance',
        'benchmarks.draft.save': 'benchmarks/draft',
        'benchmarks.draft.start': 'benchmarks/draft/start',
        'benchmarks.draft.cutover': 'benchmarks/draft/cutover',
        'benchmarks.draft.recover': 'benchmarks/draft/recover',
        'benchmarks.draft.restore': 'benchmarks/draft/restore',
        'benchmarks.draft.rebase': 'benchmarks/draft/rebase',
        'benchmarks.history': 'benchmarks/history',
        'benchmarks.comparison': 'benchmarks/comparison',
        'sandbox.governance-preview': 'sandbox/preview',
        'advising-note.save': 'advising-note/draft',
        'advising-note.restore': 'advising-note/restore',
        'advising-note.publish': 'advising-note/publish',
        'advising-note.retract': 'advising-note/retract',
        'advising-note.copy': 'advising-note/copy',
        'discussions.open': 'discussions',
        'discussions.history': 'discussions/history',
        'discussions.send': 'discussions/messages',
        'discussions.read': 'discussions/read',
        'discussions.state': 'discussions/state',
    };
    if (!paths[action]) throw new Error(`Unknown governance route: ${name}`);
    return `${base}/programs/${part('program')}/cycles/${part('cycle')}/${paths[action]}`;
}

export class GovernanceRequestError extends Error {
    constructor(message: string, public status: number, public errors: Record<string, string[]> = {}, public outcomeUnknown = false) {
        super(message);
        this.name = 'GovernanceRequestError';
    }
}

export async function governanceRequest<T>(url: string, payload?: Record<string, unknown>, signal?: AbortSignal): Promise<T> {
    const response = await fetch(url, {
        method: payload === undefined ? 'GET' : 'POST',
        credentials: 'same-origin',
        headers: {
            Accept: 'application/json',
            'X-Requested-With': 'XMLHttpRequest',
            ...(payload === undefined ? {} : { 'Content-Type': 'application/json' }),
            ...csrfHeaders(),
        },
        ...(payload === undefined ? {} : { body: JSON.stringify(payload) }),
        signal,
    });
    const data = await response.json().catch(() => null);
    if (!response.ok) {
        throw new GovernanceRequestError(data?.message ?? (response.status === 419 ? 'Your session expired. Sign in again before retrying.' : 'The request could not be completed. Retry without changing your input.'), response.status, data?.errors ?? {});
    }
    if (data === null) throw new GovernanceRequestError('The server returned an unreadable response. Retry the same request.', response.status, {}, true);
    return data as T;
}

export function governanceError(error: unknown): string {
    if (error instanceof GovernanceRequestError) {
        const details = Object.values(error.errors).flat().join(' ');
        return `${error.status === 409 ? 'Conflict: ' : error.status === 403 ? 'Access is no longer available: ' : ''}${error.message}${details ? ` ${details}` : ''}`;
    }
    return error instanceof Error ? error.message : 'The request failed. Your input has been kept.';
}
export function isUncertainCommandOutcome(failure: unknown): boolean {
    if (!(failure instanceof GovernanceRequestError)) return true;
    return failure.outcomeUnknown || failure.status === 408 || failure.status === 425 || failure.status === 429 || failure.status >= 500;
}

export function reusePendingGovernancePayload<T extends Record<string, unknown>>(
    action: string,
    payload: T,
    pending: { action: string; payload: T } | null | undefined,
): T {
    return pending?.action === action ? pending.payload : payload;
}

export type GovernanceRequestToken = { scope: string; sequence: number };

export function beginGovernanceRequest(scope: string, previousSequence: number): GovernanceRequestToken {
    return { scope, sequence: previousSequence + 1 };
}

export function isCurrentGovernanceRequest(token: GovernanceRequestToken, scope: string, sequence: number): boolean {
    return token.scope === scope && token.sequence === sequence;
}

export function applyGovernanceNoteDetail(
    state: Record<string, any>,
    data: Record<string, any>,
): Record<string, any> {
    const detail = data.note ?? null;
    const latestDraft = detail?.draft ?? null;
    const hasDirtyText = state.text !== (state.draft?.content ?? '');

    return {
        ...state,
        selectedNote: detail,
        draft: hasDirtyText ? state.draft : latestDraft,
        latestDraft,
        published: detail?.published ?? null,
        history: detail?.history ?? { items: [], pagination: null },
        publicationHistory: detail?.publication_history ?? { items: [], pagination: null },
        text: hasDirtyText ? state.text : latestDraft?.content ?? '',
        selectedNoteId: latestDraft?.note_id ?? detail?.note_id ?? state.selectedNoteId,
    };
}

export function reconcileGovernanceNoteEditor(
    state: Record<string, any>,
): { draft: Record<string, any> | null; text: string } {
    const draft = state.latestDraft ?? null;
    return { draft, text: draft?.content ?? '' };
}

export function reconcileStudioGovernance(
    state: Record<string, any>,
    incoming: Record<string, any>,
): Record<string, any> {
    const preserveDraft = state.dirty || state.busy || state.pendingCommand !== null;
    const serverContent = incoming.draft?.content ?? incoming.active_content ?? incoming.recovery_content ?? null;

    return {
        activeSet: incoming.active_set ?? null,
        draft: preserveDraft ? state.draft : incoming.draft ?? null,
        content: preserveDraft ? state.content : serverContent,
        savedContent: preserveDraft ? state.savedContent : serverContent,
        recovery: incoming.recovery ?? null,
        recoveryContent: incoming.recovery_content ?? null,
        noteBoard: incoming.notes ?? state.noteBoard,
        benchmarkHistory: Array.isArray(incoming.benchmark_revision_history)
            ? { items: incoming.benchmark_revision_history, pagination: null }
            : state.benchmarkHistory,
    };
}

export type PreviewResponseIdentity = {
    requestId: string;
    source: 'active' | 'draft';
    programId: string | number;
    cycleId: string | number;
    activationId?: string | number | null;
    setId?: string | number | null;
    revisionId?: string | number | null;
    draftId?: string | number | null;
    baseSetId?: string | number | null;
};

export type PreviewResponseKind = 'evaluated' | 'unavailable' | 'standby' | 'mismatch';

function sameIdentityId(actual: unknown, expected: unknown): boolean {
    if (actual === null || actual === undefined || expected === null || expected === undefined) {
        return (actual === null || actual === undefined) && (expected === null || expected === undefined);
    }
    return String(actual) === String(expected);
}

export function classifyPreviewResponse(
    evaluation: Record<string, any> | null | undefined,
    expected: PreviewResponseIdentity,
): PreviewResponseKind {
    const context = evaluation?.evaluation_context;
    const scopeMatches = context?.source === expected.source
        && sameIdentityId(context?.request_id, expected.requestId)
        && sameIdentityId(context?.program_id, expected.programId)
        && sameIdentityId(context?.academic_cycle_id, expected.cycleId);
    if (!scopeMatches) return 'mismatch';

    const sourceMatches = expected.source === 'active'
        ? expected.activationId != null
            && expected.setId != null
            && sameIdentityId(context?.benchmark_activation_id, expected.activationId)
            && sameIdentityId(context?.benchmark_set_id, expected.setId)
        : expected.revisionId != null
            && expected.draftId != null
            && expected.baseSetId != null
            && sameIdentityId(context?.benchmark_revision_id, expected.revisionId)
            && sameIdentityId(context?.benchmark_draft_id, expected.draftId)
            && sameIdentityId(context?.benchmark_set_id, expected.baseSetId);
    const isEvaluated = evaluation?.status === 'evaluated' && evaluation?.evaluated !== false;
    if (isEvaluated) return sourceMatches ? 'evaluated' : 'mismatch';

    const activeStandby = expected.source === 'active'
        && evaluation?.status === 'standby'
        && evaluation?.evaluated !== true
        && context?.benchmark_activation_id == null
        && context?.benchmark_set_id == null
        && typeof evaluation?.message === 'string'
        && evaluation.message.trim() !== '';
    if (activeStandby) return 'standby';
    return sourceMatches ? 'unavailable' : 'mismatch';
}

export function focusGovernanceTarget(id: string): void {
    const target = document.getElementById(id);
    if (!target) return;
    const details = target.closest('details');
    if (details) details.open = true;
    const focusTarget = (): void => {
        target.scrollIntoView?.({ block: 'nearest' });
        if (target instanceof HTMLElement) target.focus({ preventScroll: true });
    };
    if (typeof requestAnimationFrame === 'function') requestAnimationFrame(focusTarget);
    else focusTarget();
}


export type PreviewIdentity = { requestId: string; fingerprint: string };

export type PreviewState<T> = { status: 'idle' | 'stale' | 'pending' | 'ready' | 'unavailable' | 'error'; current: PreviewIdentity | null; result: T | null; error: string };

export function invalidatePreview<T>(state: PreviewState<T>): PreviewState<T> {
    return { ...state, current: null, status: state.result === null ? 'idle' : 'stale', error: '' };
}

export function beginPreview<T>(state: PreviewState<T>, identity: PreviewIdentity): PreviewState<T> {
    return { ...state, current: identity, status: 'pending', error: '' };
}

export function receivePreview<T>(state: PreviewState<T>, identity: PreviewIdentity, currentFingerprint: string, responseRequestId: string | null, result: T, available: boolean): PreviewState<T> {
    if (state.current?.requestId !== identity.requestId || state.current.fingerprint !== identity.fingerprint || currentFingerprint !== identity.fingerprint || responseRequestId !== identity.requestId) return state;
    return { ...state, status: available ? 'ready' : 'unavailable', result, error: '' };
}
