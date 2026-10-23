import { newIdempotencyKey } from './utils.js';
export type MessageState = {
    subject: string | null;
    body: string | null;
    action_label: string | null;
    action_route: string | null;
    email_blueprint: { schema_version: 'notice-email-1'; layout: 'flow'; blocks: Record<string, unknown>[] } | null;
    document_revision_id: number | null;
};
export type MessageGuards = { base_version: number; family_revision: number; lock_version: number | null };
export type MessageOutputDigests = { email_sha256: string; portal_sha256: string; binding_sha256?: string };
export type ReadyNoticeDocumentVersion = {
    id: number;
    version: number;
    definition_sha256: string;
    render_sha256: string;
    pdf_url: string;
};
export type MessageEndpointMap = {
    load?: string;
    create?: string;
    update?: string;
    discard?: string;
    preview?: string;
    publish?: string;
    test_mail?: string;
    image_upload?: string;
    attachment_check?: string;
    document_edit?: string;
    return_url: string;
};
export type MessageOutput = { output_sha256: string; preview_receipt: unknown; diagnostics?: unknown[]; email?: { production_html: string; preview_html: string; text: string; assets: unknown[]; projection_sha256: string }; portal?: { title?: string; body?: string; action?: { label: string; route: string } | null } };
export type AttachmentCheckReceipt = {
    binding_sha256: string;
    attachment_receipt: unknown;
    document: { id: number; version: number; definition_sha256: string } | null;
};
export type NoticeConversionReview = {
    source_schema_version: string;
    source_renderer_version: string;
    status: 'ready' | 'blocked';
    email_subject: string | null;
    email_body: string | null;
    in_app_title: string | null;
    in_app_body: string | null;
    blockers: { code: string; path: string; message: string }[];
};
export type MessageComposerProps = {
    family: {
        id: string | number;
        domain: string;
        slug: string;
        name: string;
        active_version: number;
        family_revision: number;
        supports_pdf?: boolean;
        description?: string | null;
        channels?: string[];
        event_key?: string;
        default_action_route?: string;
        default_action_label?: string;
    };
    state: MessageState;
    draft: null | { id: string | number; lock_version: number; state: MessageState; updated_at: string; endpoints?: { update?: string; discard?: string } };
    base_version: number;
    content_sha256: string | null;
    output_digests: MessageOutputDigests;
    tokens: { token: string; label: string }[];
    assets: { asset_id: string; sha256: string; mime: string; width: number; height: number; preview_url: string }[];
    sample_context: Record<string, unknown>;
    endpoints: MessageEndpointMap;
    document_versions?: ReadyNoticeDocumentVersion[];
    versions?: { version: number; created_at: string | null; inspect_url: string; copy_state_url: string }[];
    copy_source_version?: number | null;
    supports_pdf?: boolean;
    read_only?: boolean;
    content_schema_version?: string | null;
    email_renderer_version?: string | null;
    conversion_review?: NoticeConversionReview | null;
};
export type ComposerStatus = 'idle' | 'loading' | 'saved' | 'dirty' | 'saving' | 'error' | 'unknown' | 'conflict';
export type ComposerSnapshot = {
    state: MessageState;
    draft: MessageComposerProps['draft'];
    guards: MessageGuards;
    status: ComposerStatus;
    error: string | null;
    content_sha256: string | null;
    output_digests: MessageOutputDigests;
    previews: Partial<Record<'email' | 'portal', MessageOutput>>;
    attachment_receipt: unknown;
    attachment_check: AttachmentCheckReceipt | null;
    content_schema_version?: string | null;
    email_renderer_version?: string | null;
    conversion_review?: NoticeConversionReview | null;
    unknown: boolean;
    unknown_operation: 'draft' | 'publish' | 'test_mail' | 'image_upload' | 'discard' | null;
};
export type TestMailReceipt = { test_send_id: string; status: string; recipient_masked: string; operation_id: string; replayed?: boolean };
export type MessageSaveHandoff = { typing_epoch: number; guards: MessageGuards };
export type NoticeMessageComposer = {
    snapshot(): ComposerSnapshot;
    edit(next: MessageState): void;
    save(): Promise<Record<string, unknown>>;
    saveForDocumentHandoff(): Promise<MessageSaveHandoff>;
    isSaveHandoffCurrent(handoff: MessageSaveHandoff): boolean;
    reconcilePreservingLocal(): Promise<void>;
    reloadServerState(confirmation: { confirmed: true }): Promise<void>;
    hasUnsavedWork(): boolean;
    retry(): Promise<Record<string, unknown>>;
    preview(view: 'email' | 'portal'): Promise<MessageOutput | null>;
    publish(): Promise<Record<string, unknown>>;
    checkAttachment(): Promise<AttachmentCheckReceipt | null>;
    checkAll(): Promise<{ email: MessageOutput | null; portal: MessageOutput | null; attachment: AttachmentCheckReceipt | null }>;
    discard(): Promise<Record<string, unknown>>;
    sendTestMail(): Promise<TestMailReceipt>;
    uploadImage(file: File): Promise<Record<string, unknown>>;
    subscribe(listener: (snapshot: ComposerSnapshot) => void): () => void;
    dispose(): void;
};
type Fetcher = typeof fetch;
type Operation = { url: string; method: string; payload: Record<string, unknown>; key: string; receipt: 'draft' | 'publish' | 'test_mail' | 'image_upload' | 'discard'; committedState?: MessageState; body?: FormData };
const clone = <T>(value: T): T => JSON.parse(JSON.stringify(value));
const documentVersionId = (state: MessageState): number | null => state.document_revision_id ?? null;
const documentSelectionKey = (versionId: number | null): string => versionId === null ? 'none' : String(versionId);
const views = ['email', 'portal'] as const;
function digestInput(data: MessageState, view: 'email' | 'portal'): string {
    if (view === 'email') {
        return JSON.stringify({
            subject: data.subject ?? '',
            email_blueprint: data.email_blueprint ?? null,
            action_label: data.action_label ?? null,
            action_route: data.action_route ?? null,
        });
    }
    return JSON.stringify({
        subject: data.subject ?? '',
        body: data.body ?? '',
        action_label: data.action_label ?? null,
        action_route: data.action_route ?? null,
    });
}
function sameMessageState(left: MessageState, right: MessageState): boolean {
    return digestInput(left, 'email') === digestInput(right, 'email')
        && digestInput(left, 'portal') === digestInput(right, 'portal')
        && documentVersionId(left) === documentVersionId(right);
}
type CurrentServerMessage = {
    state: MessageState;
    draft: MessageComposerProps['draft'];
    guards: MessageGuards;
    content_sha256: string;
    output_digests: MessageOutputDigests;
    content_schema_version?: string | null;
    email_renderer_version?: string | null;
    conversion_review?: NoticeConversionReview | null;
};
/** Message-only transport: serializes writes and preserves the exact idempotent operation after an ambiguous outcome. */
export function createNoticeMessageComposer(initial: MessageComposerProps, options: { fetch?: Fetcher; csrf?: () => string; key?: () => string } = {}): NoticeMessageComposer {
    const transport = options.fetch ?? globalThis.fetch.bind(globalThis);
    const makeKey = options.key ?? newIdempotencyKey;
    const state = clone(initial.draft?.state ?? initial.state);
    state.document_revision_id = documentVersionId(state);
    let savedState = clone(state);
    let draft = clone(initial.draft);
    let attachmentCheckReceipt: AttachmentCheckReceipt | null = null;
    let guards: MessageGuards = { base_version: initial.base_version, family_revision: initial.family.family_revision, lock_version: initial.draft?.lock_version ?? null };
    let status: ComposerStatus = initial.draft ? 'saved' : 'idle';
    let error: string | null = null;
    let conversionReview = initial.conversion_review;
    let contentSchemaVersion = initial.content_schema_version;
    let emailRendererVersion = initial.email_renderer_version;
    let contentSha = initial.content_sha256;
    let outputDigests: MessageOutputDigests = { ...initial.output_digests };
    let bindingDigestSelectionKey = typeof outputDigests.binding_sha256 === 'string'
        ? documentSelectionKey(documentVersionId(state))
        : null;
    let previews: Partial<Record<'email' | 'portal', MessageOutput>> = {};
    let unknown: Operation | null = null;
    let operationInFlight = false;
    let saveInFlight: Promise<Record<string, unknown>> | null = null;
    let typingEpoch = 0;
    let checkInteractionEpoch = 0;
    let attachmentRevision = 0;
    let attachmentCheckRequest = 0;
    const documentVersions = initial.document_versions ?? [];
    const hasAttachmentWorkflow = initial.family.supports_pdf === true && Boolean(initial.endpoints.attachment_check && initial.endpoints.document_edit && Array.isArray(initial.document_versions));
    const viewRevisions: Record<'email' | 'portal', number> = { email: 0, portal: 0 };
    const listeners = new Set<(snapshot: ComposerSnapshot) => void>();
    function assertMessageWritable(): void {
        if (conversionReview?.status !== 'blocked') return;
        const onlyTitleDiscrepancy = conversionReview.blockers.length === 1
            && conversionReview.blockers[0].code === 'differing_channel_titles';
        const explicitSubjectAndBody = typeof state.subject === 'string' && typeof state.body === 'string';
        if (!onlyTitleDiscrepancy || !explicitSubjectAndBody) {
            throw new Error('This historical message needs an explicit, lossless source correction before it can be saved or checked.');
        }
    }
    function edit(next: MessageState) {
        const previousRevision = documentVersionId(state);
        const nextState = clone(next);
        nextState.document_revision_id = documentVersionId(nextState);
        const nextRevision = documentVersionId(nextState);
        Object.assign(state, nextState);
        typingEpoch++;
        checkInteractionEpoch++;
        if (unknown) {
            status = 'unknown';
        } else if (status !== 'conflict') {
            status = 'dirty';
            error = null;
        }
        for (const view of views) {
            if (digestInput(state, view) !== digestInput(savedState, view)) {
                viewRevisions[view]++;
                delete previews[view];
            }
        }
        if (previousRevision !== nextRevision) {
            attachmentRevision++;
            bindingDigestSelectionKey = null;
            attachmentCheckReceipt = null;
        }
        notify();
    }
    function hasCurrentAttachmentCheck(): boolean {
        if (!hasAttachmentWorkflow || !attachmentCheckReceipt || bindingDigestSelectionKey !== documentSelectionKey(documentVersionId(state))
            || attachmentCheckReceipt.binding_sha256 !== outputDigests.binding_sha256) return false;
        return documentVersionId(state) === null
            ? attachmentCheckReceipt.document === null
            : String(attachmentCheckReceipt.document?.id) === String(documentVersionId(state));
    }
    function snapshot(): ComposerSnapshot {
        return {
            state: clone(state),
            draft: clone(draft),
            guards: { ...guards },
            status,
            error,
            content_sha256: contentSha,
            output_digests: { ...outputDigests },
            previews: { ...previews },
            attachment_receipt: hasCurrentAttachmentCheck() ? attachmentCheckReceipt?.attachment_receipt ?? null : null,
            attachment_check: hasCurrentAttachmentCheck() && attachmentCheckReceipt ? { ...attachmentCheckReceipt } : null,
            ...(contentSchemaVersion !== undefined ? { content_schema_version: contentSchemaVersion } : {}),
            ...(emailRendererVersion !== undefined ? { email_renderer_version: emailRendererVersion } : {}),
            ...(conversionReview !== undefined ? { conversion_review: clone(conversionReview) } : {}),
            unknown: unknown !== null,
            unknown_operation: unknown?.receipt ?? null,
        };
    }
    function notify() { for (const listener of listeners) listener(snapshot()); }
    const endpoint = (key: keyof MessageEndpointMap) => {
        if (key === 'update' && typeof draft?.endpoints?.update === 'string') {
            return draft.endpoints.update;
        }
        if (key === 'discard' && typeof draft?.endpoints?.discard === 'string') {
            return draft.endpoints.discard;
        }
        const url = initial.endpoints[key];
        if (!url) throw new Error(`The ${key} endpoint is unavailable.`);
        if ((key === 'update' || key === 'discard') && draft && url.includes('{draft}')) {
            return url.replace('{draft}', encodeURIComponent(String(draft.id)));
        }
        return url;
    };
    async function send(op: Operation, requireDraft = true): Promise<Record<string, unknown>> {
        assertMessageWritable();
        const headers: Record<string, string> = { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' };
        if (!op.body) headers['Content-Type'] = 'application/json';
        if (options.csrf) headers['X-CSRF-TOKEN'] = options.csrf();
        let response: Response;
        try {
            response = await transport(op.url, {
                method: op.method,
                credentials: 'same-origin',
                headers,
                body: op.body ?? JSON.stringify({ ...op.payload, idempotency_key: op.key }),
            });
        } catch (caught) { unknown = op; status = 'unknown'; error = `Outcome unknown. Your changes are retained. Retry the same operation to reconcile it. ${String(caught)}`; notify(); throw caught; }
        let result: Record<string, unknown>;
        try { result = await response.json() as Record<string, unknown>; } catch { result = {}; }
        if (!response.ok) {
            if (op.receipt === 'test_mail' && (result.error === 'test_send_ambiguous' || result.status === 'unknown' || result.status === 'claimed')) {
                unknown = op; status = 'unknown'; error = 'Test-email outcome is unknown. Retry the same operation to reconcile it; no automatic resend was started.'; notify(); throw new Error(error);
            }
            if (response.status === 409) {
                unknown = null;
                status = 'conflict';
                error = typeof result.message === 'string' ? result.message : 'This draft changed elsewhere. Your input is retained; reconcile current guards before saving.';
            } else {
                status = 'error';
                error = typeof result.message === 'string' ? result.message : `Request failed (${response.status}). Your input is retained.`;
            }
            notify(); throw Object.assign(new Error(error), { status: response.status, result });
        }
        if (typeof result.operation_id !== 'string'
            || (op.receipt === 'draft' && (typeof result.family_revision !== 'number' || typeof result.content_sha256 !== 'string' || !result.draft || typeof result.draft !== 'object'))
            || (op.receipt === 'test_mail' && ['test_send_id', 'status', 'recipient_masked'].some((field) => typeof result[field] !== 'string'))
            || (op.receipt === 'image_upload' && (
                typeof result.family_revision !== 'number'
                || typeof result.asset !== 'object'
                || result.asset === null
                || !['sha256', 'mime'].every((field) => typeof (result.asset as Record<string, unknown>)[field] === 'string')
                || !['width', 'height'].every((field) => typeof (result.asset as Record<string, unknown>)[field] === 'number')
                || !['string', 'number'].includes(typeof (result.asset as Record<string, unknown>).id)
            ))) {
            unknown = op; status = 'unknown'; error = 'The server returned no complete operation receipt. Retry the same operation to reconcile it.'; notify(); throw new Error(error);
        }
        unknown = null;
        return result;
    }
    function adoptOutputDigests(result: Record<string, unknown>, committedState: MessageState) {
        if (!result.output_digests || typeof result.output_digests !== 'object') return;
        const incoming = result.output_digests as Record<string, unknown>;
        const previousEmail = outputDigests.email_sha256;
        const previousPortal = outputDigests.portal_sha256;
        const previousBinding = outputDigests.binding_sha256;
        const emailChanged = typeof incoming.email_sha256 === 'string' && incoming.email_sha256 !== previousEmail;
        const portalChanged = typeof incoming.portal_sha256 === 'string' && incoming.portal_sha256 !== previousPortal;
        outputDigests = {
            ...outputDigests,
            ...(typeof incoming.email_sha256 === 'string' ? { email_sha256: incoming.email_sha256 } : {}),
            ...(typeof incoming.portal_sha256 === 'string' ? { portal_sha256: incoming.portal_sha256 } : {}),
            ...(typeof incoming.binding_sha256 === 'string' ? { binding_sha256: incoming.binding_sha256 } : {}),
        };
        if (emailChanged) {
            viewRevisions.email++;
            delete previews.email;
        }
        if (portalChanged) {
            viewRevisions.portal++;
            delete previews.portal;
        }
        if (typeof incoming.binding_sha256 === 'string') {
            bindingDigestSelectionKey = documentSelectionKey(documentVersionId(committedState));
            if (previousBinding !== incoming.binding_sha256) {
                attachmentRevision++;
                attachmentCheckReceipt = null;
            }
        }
    }
    function adoptDraft(result: Record<string, unknown>, committedState: MessageState) {
        const receiptDraft = result.draft as { id: string | number; lock_version: number; state: MessageState; updated_at?: string };
        draft = { ...receiptDraft, updated_at: receiptDraft.updated_at ?? '' };
        guards = { base_version: typeof result.active_version === 'number' ? result.active_version : guards.base_version, family_revision: result.family_revision as number, lock_version: receiptDraft.lock_version };
        contentSha = result.content_sha256 as string;
        if (typeof result.content_schema_version === 'string' || result.content_schema_version === null) {
            contentSchemaVersion = result.content_schema_version;
        }
        if (typeof result.email_renderer_version === 'string' || result.email_renderer_version === null) {
            emailRendererVersion = result.email_renderer_version;
        }
        if (result.content_schema_version === 'notice-content-3') {
            conversionReview = null;
        }
        adoptOutputDigests(result, committedState);
        savedState = clone(committedState);
    }
    function forgetDraft() {
        checkInteractionEpoch++;
        attachmentCheckRequest++;
        draft = null;
        guards = { ...guards, lock_version: null };
        bindingDigestSelectionKey = null;
        attachmentCheckReceipt = null;
        previews = {};
        status = 'idle';
        error = null;
    }

    async function performSaveUntilCurrent(): Promise<Record<string, unknown>> {
        let latestResult: Record<string, unknown> | null = null;
        if (unknown) {
            if (unknown.receipt !== 'draft') throw new Error('Reconcile the outstanding operation before saving a draft.');
            latestResult = await retry();
            if (status === 'conflict') throw new Error(error ?? 'Resolve the draft conflict before saving.');
            if (status === 'saved' && sameMessageState(state, savedState)) return latestResult;
        }
        while (true) {
            if (unknown) {
                if (unknown.receipt !== 'draft') throw new Error('Reconcile the outstanding operation before saving a draft.');
                latestResult = await retry();
                if (status === 'conflict') throw new Error(error ?? 'Resolve the draft conflict before saving.');
                if (status === 'saved' && sameMessageState(state, savedState)) return latestResult;
            }
            if (operationInFlight) throw new Error('A message operation is already in progress.');
            if (status === 'conflict') throw new Error(error ?? 'Resolve the draft conflict before saving.');
            const savedTypingEpoch = typingEpoch;
            const savedPayload = clone(state);
            const op: Operation = {
                url: endpoint(draft ? 'update' : 'create'),
                method: draft ? 'PUT' : 'POST',
                payload: {
                    state: savedPayload,
                    ...guards,
                    ...(!draft && typeof initial.copy_source_version === 'number' ? { source_version: initial.copy_source_version } : {}),
                },
                key: makeKey(),
                receipt: 'draft',
                committedState: savedPayload,
            };
            operationInFlight = true; status = 'saving'; error = null; notify();
            try {
                latestResult = await send(op);
                adoptDraft(latestResult, savedPayload);
                if (typingEpoch === savedTypingEpoch && sameMessageState(state, savedPayload)) {
                    status = 'saved';
                    notify();
                    return latestResult;
                }
                status = 'dirty';
                notify();
            } catch (caught) {
                if (!unknown && status !== 'conflict') status = 'error';
                notify();
                throw caught;
            } finally {
                operationInFlight = false;
            }
        }
    }
    function save(): Promise<Record<string, unknown>> {
        try {
            assertMessageWritable();
        } catch (caught) {
            return Promise.reject(caught);
        }
        if (saveInFlight) return saveInFlight;
        const pending = performSaveUntilCurrent();
        saveInFlight = pending;
        void pending.finally(() => {
            if (saveInFlight === pending) saveInFlight = null;
        }).catch(() => {});
        return pending;
    }
    async function saveForDocumentHandoff(): Promise<MessageSaveHandoff> {
        assertMessageWritable();
        if (unknown && unknown.receipt !== 'draft') {
            throw new Error('Reconcile the outstanding operation before opening the document editor.');
        }
        if (status === 'conflict') {
            throw new Error(error ?? 'Reconcile the draft conflict before opening the document editor.');
        }
        if (status === 'error') {
            throw new Error(error ?? 'Resolve the failed message save before opening the document editor.');
        }
        if (!draft || status !== 'saved' || !sameMessageState(state, savedState)) {
            await save();
        }
        if (unknown || status !== 'saved' || operationInFlight || !sameMessageState(state, savedState)) {
            throw new Error('The latest message typing is not acknowledged. The document editor was not opened.');
        }
        return { typing_epoch: typingEpoch, guards: { ...guards } };
    }
    function isSaveHandoffCurrent(handoff: MessageSaveHandoff): boolean {
        return handoff.typing_epoch === typingEpoch
            && handoff.guards.base_version === guards.base_version
            && handoff.guards.family_revision === guards.family_revision
            && handoff.guards.lock_version === guards.lock_version
            && status === 'saved'
            && unknown === null
            && !operationInFlight
            && sameMessageState(state, savedState);
    }
    async function fetchCurrentServerMessage(): Promise<CurrentServerMessage> {
        const response = await transport(endpoint('load'), {
            method: 'GET',
            credentials: 'same-origin',
            headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest', ...(options.csrf ? { 'X-CSRF-TOKEN': options.csrf() } : {}) },
        });
        let result: Record<string, unknown>;
        try {
            result = await response.json() as Record<string, unknown>;
        } catch {
            result = {};
        }
        if (!response.ok) {
            throw Object.assign(new Error(typeof result.message === 'string' ? result.message : `Could not load current message guards (${response.status}).`), { status: response.status, result });
        }
        const family = result.family as Record<string, unknown> | undefined;
        const output = result.output_digests as Record<string, unknown> | undefined;
        const candidateDraft = result.draft;
        const candidateState = result.state;
        const contentSchemaVersion = result.content_schema_version;
        const emailRendererVersion = result.email_renderer_version;
        if ((contentSchemaVersion !== undefined && contentSchemaVersion !== null && typeof contentSchemaVersion !== 'string')
            || (emailRendererVersion !== undefined && emailRendererVersion !== null && typeof emailRendererVersion !== 'string')) {
            throw new Error('The server returned invalid message version metadata. Your local input was retained.');
        }
        const rawReview = result.conversion_review;
        if (rawReview !== undefined && rawReview !== null) {
            if (typeof rawReview !== 'object' || Array.isArray(rawReview)) {
                throw new Error('The server returned invalid conversion-review status. Your local input was retained.');
            }
            const review = rawReview as Record<string, unknown>;
            const blockers = review.blockers;
            const validHistoricalFields = ['email_subject', 'email_body', 'in_app_title', 'in_app_body']
                .every((field) => review[field] === null || typeof review[field] === 'string');
            const validBlockers = Array.isArray(blockers) && blockers.every((blocker) => blocker !== null
                && typeof blocker === 'object'
                && ['code', 'path', 'message'].every((field) => typeof (blocker as Record<string, unknown>)[field] === 'string'));
            if (typeof review.source_schema_version !== 'string'
                || typeof review.source_renderer_version !== 'string'
                || !['ready', 'blocked'].includes(String(review.status))
                || !validHistoricalFields || !validBlockers) {
                throw new Error('The server returned incomplete conversion-review status. Your local input was retained.');
            }
        }
        const serverReview = rawReview as NoticeConversionReview | null | undefined;
        if (serverReview?.status === 'blocked') {
            conversionReview = serverReview;
            notify();
        }
        if (!family || typeof family.family_revision !== 'number'
            || typeof result.base_version !== 'number'
            || typeof result.content_sha256 !== 'string'
            || !output || typeof output.email_sha256 !== 'string' || typeof output.portal_sha256 !== 'string'
            || !candidateState || typeof candidateState !== 'object'
            || !Object.prototype.hasOwnProperty.call(result, 'draft')
            || (candidateDraft !== null && (
                typeof candidateDraft !== 'object'
                || typeof (candidateDraft as Record<string, unknown>).lock_version !== 'number'
                || !['string', 'number'].includes(typeof (candidateDraft as Record<string, unknown>).id)
            ))
            || (output.binding_sha256 !== undefined && typeof output.binding_sha256 !== 'string')) {
            throw new Error('The server returned incomplete current message guards. Your local input was retained.');
        }
        const serverState = clone(candidateState as MessageState);
        serverState.document_revision_id = documentVersionId(serverState);
        return {
            state: serverState,
            draft: candidateDraft === null ? null : clone(candidateDraft) as MessageComposerProps['draft'],
            guards: {
                base_version: result.base_version,
                family_revision: family.family_revision,
                lock_version: candidateDraft === null ? null : (candidateDraft as Record<string, unknown>).lock_version as number,
            },
            content_sha256: result.content_sha256,
            output_digests: {
                email_sha256: output.email_sha256,
                portal_sha256: output.portal_sha256,
                ...(typeof output.binding_sha256 === 'string' ? { binding_sha256: output.binding_sha256 } : {}),
            },
            ...(contentSchemaVersion !== undefined ? { content_schema_version: contentSchemaVersion as string | null } : {}),
            ...(emailRendererVersion !== undefined ? { email_renderer_version: emailRendererVersion as string | null } : {}),
            conversion_review: serverReview,
        };
    }
    function adoptCurrentServerMessage(current: CurrentServerMessage, reload: boolean): void {
        checkInteractionEpoch++;
        typingEpoch++;
        for (const view of views) {
            viewRevisions[view]++;
        }
        attachmentRevision++;
        attachmentCheckRequest++;
        previews = {};
        attachmentCheckReceipt = null;
        draft = clone(current.draft);
        guards = { ...current.guards };
        contentSha = current.content_sha256;
        if (current.content_schema_version !== undefined) {
            contentSchemaVersion = current.content_schema_version;
        }
        if (current.email_renderer_version !== undefined) {
            emailRendererVersion = current.email_renderer_version;
        }
        if (current.conversion_review !== undefined) {
            conversionReview = current.conversion_review;
        }
        outputDigests = { ...current.output_digests };
        bindingDigestSelectionKey = typeof outputDigests.binding_sha256 === 'string'
            ? documentSelectionKey(documentVersionId(current.state))
            : null;
        savedState = clone(current.state);
        if (reload) {
            Object.assign(state, clone(current.state));
        }
        status = sameMessageState(state, savedState) ? 'saved' : 'dirty';
        error = null;
        notify();
    }
    async function reconcilePreservingLocal(): Promise<void> {
        if (unknown) throw new Error('Retry the exact outstanding operation before reconciling current guards.');
        if (operationInFlight || saveInFlight) throw new Error('Wait for the current message operation before reconciling guards.');
        const current = await fetchCurrentServerMessage();
        adoptCurrentServerMessage(current, false);
    }
    async function reloadServerState(confirmation: { confirmed: true }): Promise<void> {
        if (confirmation.confirmed !== true) throw new Error('Explicit confirmation is required before reloading local message state.');
        if (unknown) throw new Error('Retry the exact outstanding operation before reloading server state.');
        if (operationInFlight || saveInFlight) throw new Error('Wait for the current message operation before reloading server state.');
        const current = await fetchCurrentServerMessage();
        adoptCurrentServerMessage(current, true);
    }
    function hasUnsavedWork(): boolean {
        return status === 'dirty' || status === 'saving' || status === 'error' || status === 'unknown' || status === 'conflict';
    }
    async function retry(): Promise<Record<string, unknown>> {
        assertMessageWritable();
        if (!unknown) return save();
        if (operationInFlight) throw new Error('A message operation is already in progress.');
        operationInFlight = true; const op = unknown; status = 'saving'; error = null; notify();
        try {
            const result = await send(op, op.receipt === 'draft');
            if (op.receipt === 'image_upload') {
                if (!result.asset || typeof result.family_revision !== 'number') {
                    unknown = op; status = 'unknown'; error = 'The image receipt is incomplete. Retry the same upload operation to reconcile it.'; notify(); throw new Error(error);
                }
                guards = { ...guards, family_revision: result.family_revision };
                status = digestInput(state, 'email') === digestInput(savedState, 'email')
                    && digestInput(state, 'portal') === digestInput(savedState, 'portal')
                    && documentVersionId(state) === documentVersionId(savedState) ? 'saved' : 'dirty';
            } else if (op.receipt === 'test_mail') {
                if (['test_send_id', 'status', 'recipient_masked', 'operation_id'].some((field) => typeof result[field] !== 'string')) {
                    unknown = op; status = 'unknown'; error = 'The test-email receipt is incomplete. Retry the same operation to reconcile it.'; notify(); throw new Error(error);
                }
                status = 'saved';
            } else if (op.receipt === 'discard') {
                forgetDraft();
            } else if (op.receipt === 'publish') {
                if (typeof result.active_version !== 'number' || typeof result.content_sha256 !== 'string') {
                    unknown = op; status = 'unknown'; error = 'The publication receipt is incomplete. Retry the same operation to reconcile it.'; notify(); throw new Error(error);
                }
                guards = { base_version: result.active_version, family_revision: typeof result.family_revision === 'number' ? result.family_revision : guards.family_revision, lock_version: null };
                contentSha = result.content_sha256;
                draft = null;
                const committedState = op.committedState ?? state;
                savedState = clone(committedState);
                status = digestInput(state, 'email') === digestInput(committedState, 'email')
                    && digestInput(state, 'portal') === digestInput(committedState, 'portal')
                    && documentVersionId(state) === documentVersionId(committedState) ? 'saved' : 'dirty';
            } else {
                const committedState = op.committedState ?? (op.payload.state as MessageState);
                adoptDraft(result, committedState);
                status = digestInput(state, 'email') === digestInput(committedState, 'email')
                    && digestInput(state, 'portal') === digestInput(committedState, 'portal')
                    && documentVersionId(state) === documentVersionId(committedState) ? 'saved' : 'dirty';
            }
            notify(); return result;
        } catch (caught) { if (!unknown && status !== 'conflict') status = 'error'; throw caught; }
        finally { operationInFlight = false; }
    }
    async function discard(): Promise<Record<string, unknown>> {
        assertMessageWritable();
        if (unknown) {
            if (unknown.receipt !== 'discard') {
                throw new Error('Reconcile the outstanding operation before discarding this draft.');
            }
            return retry();
        }
        if (!draft) {
            throw new Error('There is no draft to discard.');
        }
        if (operationInFlight) {
            throw new Error('A message operation is already in progress.');
        }
        if (status === 'conflict') {
            throw new Error(error ?? 'Resolve the draft conflict before discarding.');
        }
        operationInFlight = true;
        status = 'saving';
        error = null;
        notify();
        const op: Operation = {
            url: endpoint('discard'),
            method: 'DELETE',
            payload: { ...guards },
            key: makeKey(),
            receipt: 'discard',
        };
        try {
            const result = await send(op, false);
            forgetDraft();
            notify();
            return result;
        } catch (caught) {
            if (!unknown && status !== 'conflict') {
                status = 'error';
            }
            notify();
            throw caught;
        } finally {
            operationInFlight = false;
        }
    }
    async function saveIfDirty() {
        assertMessageWritable();
        if (unknown && unknown.receipt !== 'draft') {
            throw new Error('Reconcile the outstanding operation before requesting another message operation.');
        }
        if (status === 'conflict') {
            throw new Error(error ?? 'Reconcile the draft conflict before requesting another message operation.');
        }
        if (unknown || status === 'dirty' || status === 'error' || !draft) return save();
        return null;
    }
    async function requestPreview(view: 'email' | 'portal', interactionEpoch: number): Promise<MessageOutput | null> {
        assertMessageWritable();
        await saveIfDirty();
        if (interactionEpoch !== checkInteractionEpoch) return null;
        if (!contentSha) throw new Error('Save the draft before requesting a current preview.');
        const requestRevision = viewRevisions[view];
        const requestDigest = outputDigests[`${view}_sha256`];
        let response: Response;
        let result: Record<string, unknown>;
        try {
            response = await transport(endpoint('preview'), {
                method: 'POST',
                credentials: 'same-origin',
                headers: { Accept: 'application/json', 'Content-Type': 'application/json', 'X-Requested-With': 'XMLHttpRequest', ...(options.csrf ? { 'X-CSRF-TOKEN': options.csrf() } : {}) },
                body: JSON.stringify({ view, ...guards, output_sha256: requestDigest }),
            });
            result = await response.json() as Record<string, unknown>;
        } catch (caught) {
            if (interactionEpoch !== checkInteractionEpoch) return null;
            throw caught;
        }
        if (interactionEpoch !== checkInteractionEpoch || viewRevisions[view] !== requestRevision
            || outputDigests[`${view}_sha256`] !== requestDigest) return null;
        if (!response.ok) throw new Error(typeof result.message === 'string' ? result.message : `Preview failed (${response.status}).`);
        if (result.view !== view || result.output_sha256 !== requestDigest || !result.preview_receipt) return null;
        if ((view === 'email' && (!result.email || typeof result.email !== 'object')) || (view === 'portal' && (!result.portal || typeof result.portal !== 'object'))) {
            throw new Error('The server returned an incomplete preview projection.');
        }
        previews[view] = result as unknown as MessageOutput;
        notify();
        return previews[view] ?? null;
    }
    function preview(view: 'email' | 'portal'): Promise<MessageOutput | null> {
        const interactionEpoch = ++checkInteractionEpoch;
        return requestPreview(view, interactionEpoch);
    }
    async function requestAttachmentCheck(interactionEpoch: number): Promise<AttachmentCheckReceipt | null> {
        assertMessageWritable();
        await saveIfDirty();
        if (interactionEpoch !== checkInteractionEpoch) return null;
        if (!hasAttachmentWorkflow) throw new Error('Attachment checks are unavailable for this message family.');
        if (operationInFlight) throw new Error('A message operation is already in progress.');
        const selectedRevision = documentVersionId(state);
        if (selectedRevision !== null && !documentVersions.some((version) => String(version.id) === String(selectedRevision))) {
            throw new Error('Choose a ready document version from this family before checking the attachment.');
        }
        const selectionKey = documentSelectionKey(selectedRevision);
        const bindingSha = outputDigests.binding_sha256;
        if (bindingDigestSelectionKey !== selectionKey || typeof bindingSha !== 'string' || !bindingSha) {
            throw new Error('The server has not confirmed the current document selection. Save the message draft and try again.');
        }
        const requestRevision = attachmentRevision;
        const requestId = ++attachmentCheckRequest;
        let response: Response;
        let result: AttachmentCheckReceipt | Record<string, unknown>;
        try {
            response = await transport(endpoint('attachment_check'), {
                method: 'POST',
                credentials: 'same-origin',
                headers: { Accept: 'application/json', 'Content-Type': 'application/json', 'X-Requested-With': 'XMLHttpRequest', ...(options.csrf ? { 'X-CSRF-TOKEN': options.csrf() } : {}) },
                body: JSON.stringify({ ...guards, document_revision_id: selectedRevision, binding_sha256: bindingSha }),
            });
            try {
                result = await response.json() as AttachmentCheckReceipt;
            } catch {
                result = {};
            }
        } catch (caught) {
            if (interactionEpoch !== checkInteractionEpoch) return null;
            throw caught;
        }
        if (interactionEpoch !== checkInteractionEpoch || requestRevision !== attachmentRevision || requestId !== attachmentCheckRequest
            || documentVersionId(state) !== selectedRevision || bindingDigestSelectionKey !== selectionKey
            || outputDigests.binding_sha256 !== bindingSha) return null;
        if (!response.ok) throw new Error(typeof result.message === 'string' ? result.message : `Attachment check failed (${response.status}).`);
        const receipt = result as AttachmentCheckReceipt;
        if (typeof receipt.binding_sha256 !== 'string' || receipt.binding_sha256 !== bindingSha
            || receipt.attachment_receipt === null || receipt.attachment_receipt === undefined
            || !Object.prototype.hasOwnProperty.call(receipt, 'document')
            || (selectedRevision === null ? receipt.document !== null : (
                !receipt.document || String(receipt.document.id) !== String(selectedRevision)
                || typeof receipt.document.version !== 'number'
                || typeof receipt.document.definition_sha256 !== 'string'
            ))) {
            throw new Error('The server returned an incomplete attachment check. No current proof was recorded.');
        }
        attachmentCheckReceipt = receipt;
        notify();
        return receipt;
    }
    function checkAttachment(): Promise<AttachmentCheckReceipt | null> {
        const interactionEpoch = ++checkInteractionEpoch;
        return requestAttachmentCheck(interactionEpoch);
    }
    async function checkAll(): Promise<{ email: MessageOutput | null; portal: MessageOutput | null; attachment: AttachmentCheckReceipt | null }> {
        assertMessageWritable();
        const interactionEpoch = ++checkInteractionEpoch;
        await saveIfDirty();
        if (interactionEpoch !== checkInteractionEpoch) return { email: null, portal: null, attachment: null };
        const [email, portal, attachment] = await Promise.all([
            requestPreview('email', interactionEpoch),
            requestPreview('portal', interactionEpoch),
            hasAttachmentWorkflow ? requestAttachmentCheck(interactionEpoch) : Promise.resolve(null),
        ]);
        if (interactionEpoch !== checkInteractionEpoch) return { email: null, portal: null, attachment: null };
        return { email, portal, attachment };
    }
    function requireCurrentAttachmentCheck() {
        if (hasAttachmentWorkflow && !hasCurrentAttachmentCheck()) {
            throw new Error('Check the current document selection, including permitted none, before publication.');
        }
    }
    async function publish(): Promise<Record<string, unknown>> {
        assertMessageWritable();
        if (unknown) {
            if (unknown.receipt !== 'publish') throw new Error('Reconcile the outstanding operation before publishing.');
            return retry();
        }
        await saveIfDirty();
        if (operationInFlight) throw new Error('A message operation is already in progress.');
        const email = previews.email; const portal = previews.portal;
        if (!email || !portal || email.output_sha256 !== outputDigests.email_sha256 || portal.output_sha256 !== outputDigests.portal_sha256) throw new Error('Refresh both current Email and Portal previews before publication review.');
        requireCurrentAttachmentCheck();
        const previewReceipts: Record<string, unknown> = { email: email.preview_receipt, portal: portal.preview_receipt };
        if (hasAttachmentWorkflow && attachmentCheckReceipt) {
            previewReceipts.attachment = attachmentCheckReceipt.attachment_receipt;
        }
        checkInteractionEpoch++;
        operationInFlight = true;
        const publishedState = clone(state);
        const key = makeKey();
        const payload: Record<string, unknown> = {
            ...guards,
            content_sha256: contentSha,
            idempotency_key: key,
            preview_receipts: previewReceipts,
        };
        if (hasAttachmentWorkflow) {
            payload.document_revision_id = documentVersionId(state);
        }
        const op: Operation = { url: endpoint('publish'), method: 'POST', payload, key, receipt: 'publish', committedState: publishedState };
        try {
            const result = await send(op, false);
            if (typeof result.active_version !== 'number' || typeof result.content_sha256 !== 'string') {
                unknown = op; status = 'unknown'; error = 'The publication receipt is incomplete. Retry the same operation to reconcile it.'; notify(); throw new Error(error);
            }
            guards = { base_version: result.active_version, family_revision: typeof result.family_revision === 'number' ? result.family_revision : guards.family_revision, lock_version: null };
            contentSha = result.content_sha256;
            draft = null;
            savedState = publishedState;
            status = digestInput(state, 'email') === digestInput(publishedState, 'email')
                && digestInput(state, 'portal') === digestInput(publishedState, 'portal')
                && documentVersionId(state) === documentVersionId(publishedState) ? 'saved' : 'dirty';
            notify(); return result;
        } catch (caught) { if (!unknown && status !== 'conflict') status = 'error'; throw caught; }
        finally { operationInFlight = false; }
    }
    async function sendTestMail(): Promise<TestMailReceipt> {
        if (unknown) {
            if (unknown.receipt !== 'test_mail') throw new Error('Reconcile the outstanding operation before sending a test email.');
            return await retry() as TestMailReceipt;
        }
        await saveIfDirty();
        if (operationInFlight) throw new Error('A message operation is already in progress.');
        const email = previews.email;
        if (!email || email.output_sha256 !== outputDigests.email_sha256) throw new Error('Refresh the current Email preview before sending a synthetic test.');
        operationInFlight = true;
        const op: Operation = { url: endpoint('test_mail'), method: 'POST', payload: { ...guards, email_sha256: email.output_sha256, preview_receipt: email.preview_receipt }, key: makeKey(), receipt: 'test_mail' };
        try {
            return await send(op, false) as unknown as TestMailReceipt;
        } finally {
            operationInFlight = false;
        }
    }
    async function uploadImage(file: File): Promise<Record<string, unknown>> {
        if (unknown) {
            if (unknown.receipt !== 'image_upload') throw new Error('Reconcile the outstanding operation before uploading an image.');
            return retry();
        }
        await saveIfDirty();
        if (!draft) throw new Error('Save the message draft before uploading an image.');
        if (operationInFlight) throw new Error('A message operation is already in progress.');
        operationInFlight = true; status = 'saving'; error = null; notify();
        const body = new FormData();
        body.append('image', file);
        body.append('draft_id', String(draft.id));
        body.append('base_version', String(guards.base_version));
        body.append('lock_version', String(guards.lock_version ?? ''));
        const key = makeKey();
        body.append('idempotency_key', key);
        const op: Operation = { url: endpoint('image_upload'), method: 'POST', payload: {}, key, receipt: 'image_upload', body };
        try {
            const result = await send(op, false);
            guards = { ...guards, family_revision: result.family_revision as number };
            status = digestInput(state, 'email') === digestInput(savedState, 'email') && digestInput(state, 'portal') === digestInput(savedState, 'portal') ? 'saved' : 'dirty';
            notify(); return result;
        } catch (caught) { if (!unknown && status !== 'conflict') status = 'error'; notify(); throw caught; }
        finally { operationInFlight = false; }
    }
    return {
        snapshot, edit, save, saveForDocumentHandoff, isSaveHandoffCurrent, reconcilePreservingLocal, reloadServerState, hasUnsavedWork,
        retry, preview, checkAll, discard, publish, checkAttachment, sendTestMail, uploadImage,
        subscribe(listener: (snapshot: ComposerSnapshot) => void) {
            listeners.add(listener);
            return () => { listeners.delete(listener); };
        },
        dispose() {
            checkInteractionEpoch++;
            attachmentCheckRequest++;
            listeners.clear();
        },
    };
}
