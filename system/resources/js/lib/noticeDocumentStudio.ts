import { newIdempotencyKey } from '@/lib/utils';

export type DocumentState = {
    source_kind: 'docx' | 'builder';
    document_asset_id: string | null;
    builder_json: Record<string, unknown> | null;
    paper_size: string;
    orientation: string;
};
export type DocumentGuards = { base_version: number; document_revision: number; lock_version: number | null };
export type DocumentEndpoints = {
    load?: string; create?: string; update?: string; discard?: string; upload?: string;
    image_upload?: string; image_content?: string; preview?: string; version?: string;
    candidate_return_url?: string; return_url: string; pdf?: string; thumbnail?: string;
};
export type DocumentDraft = { id: number; stage: number; state: DocumentState; lock_version: number; base_version: number; render_sha256: string | null };
export type ReadyDocumentVersion = { id: number; version: number; definition_sha256: string; render_sha256?: string; pdf_url?: string; thumbnail_url?: string };
export type DocumentWorkspaceProps = {
    family: { id: string | number; domain: string; slug: string; name: string };
    workspace: {
        guards: DocumentGuards; draft: DocumentDraft | null; ready_versions: ReadyDocumentVersion[];
        latest_ready_version: ReadyDocumentVersion | null; render_sha256: string | null;
        verified_images?: { id: string; sha256: string; mime: string; filename: string; url: string }[];
    };
    surface: Record<string, unknown>; builderDefaults: Record<string, unknown>; builderCatalog: Record<string, unknown>;
    builderCapabilities: Record<string, unknown>; placeholderGroups: Record<string, unknown>; sampleContext: Record<string, unknown>;
    endpoints: DocumentEndpoints; return_url: string;
};
export type DocumentPreview = {
    render_sha256: string; pdf: { pdf_base64: string; sha256: string }; preview_receipt: string; diagnostics?: unknown[];
};
export type DocumentSnapshot = {
    state: DocumentState; draft: DocumentDraft | null; guards: DocumentGuards;
    status: 'idle' | 'saved' | 'dirty' | 'saving' | 'unknown' | 'conflict' | 'error';
    error: string | null; render_sha256: string | null; preview: DocumentPreview | null;
    unknown_operation: 'draft' | 'discard' | 'upload' | 'version' | null;
};
export type NoticeDocumentStudioAdapter = {
    snapshot(): DocumentSnapshot; edit(next: DocumentState | ((current: DocumentState) => DocumentState)): void; save(stage?: number): Promise<Record<string, unknown>>;
    retry(): Promise<Record<string, unknown>>; load(preserveLocal?: boolean): Promise<Record<string, unknown>>;
    upload(file: File): Promise<Record<string, unknown>>; uploadImage(file: File): Promise<Record<string, unknown>>;
    preview(): Promise<DocumentPreview>; saveVersion(): Promise<Record<string, unknown>>; discard(): Promise<Record<string, unknown>>;
    subscribe(listener: (snapshot: DocumentSnapshot) => void): () => void; dispose(): void;
};
type Operation = {
    url: string; method: string; key: string; payload: Record<string, unknown>;
    kind: NonNullable<DocumentSnapshot['unknown_operation']>; body?: FormData; state?: DocumentState; generation: number;
};
const clone = <T>(value: T): T => JSON.parse(JSON.stringify(value)) as T;
const emptyState = (): DocumentState => ({ source_kind: 'builder', document_asset_id: null, builder_json: null, paper_size: 'a4', orientation: 'portrait' });

/** One write queue and one coalesced save loop; ambiguous writes retain their exact payload and identity. */
export function createNoticeDocumentStudioAdapter(initial: DocumentWorkspaceProps, options: { fetch?: typeof fetch; csrf?: () => string; key?: () => string; autosaveMs?: number } = {}): NoticeDocumentStudioAdapter {
    const transport = options.fetch ?? globalThis.fetch.bind(globalThis);
    const makeKey = options.key ?? newIdempotencyKey;
    const endpoints = initial.endpoints;
    let state = clone(initial.workspace.draft?.state ?? emptyState());
    let savedState = clone(state);
    let draft = clone(initial.workspace.draft);
    let guards = { ...initial.workspace.guards };
    let renderSha = draft?.render_sha256 ?? initial.workspace.render_sha256;
    let generation = 0;
    let lifecycle = 0;
    let previewSequence = 0;
    let loadSequence = 0;
    let status: DocumentSnapshot['status'] = draft ? 'saved' : 'idle';
    let error: string | null = null;
    let previewResult: DocumentPreview | null = null;
    let unknown: Operation | null = null;
    let disposed = false;
    let timer: number | NodeJS.Timeout | undefined;
    let writeTail: Promise<unknown> = Promise.resolve();
    let saveJob: Promise<Record<string, unknown>> | null = null;
    let requestedStage = draft?.stage ?? 2;
    const listeners = new Set<(snapshot: DocumentSnapshot) => void>();
    const changed = () => JSON.stringify(state) !== JSON.stringify(savedState);
    const snapshot = (): DocumentSnapshot => ({ state: clone(state), draft: clone(draft), guards: { ...guards }, status, error, render_sha256: renderSha, preview: previewResult ? clone(previewResult) : null, unknown_operation: unknown?.kind ?? null });
    function notify() { if (!disposed) for (const listener of listeners) listener(snapshot()); }
    function cancelTimer() {
        if (timer !== undefined) {
            if (typeof clearTimeout !== 'undefined') {
                clearTimeout(timer);
            } else if (typeof window !== 'undefined' && typeof window.clearTimeout === 'function') {
                window.clearTimeout(timer as number);
            }
            timer = undefined;
        }
    }
    function invalidatePreview() { previewResult = null; previewSequence += 1; }
    function assertWritable() {
        if (disposed) throw new Error('The document workspace is closed.');
        if (unknown) throw new Error('Retry the pending document operation before starting another operation.');
        if (status === 'conflict') throw new Error('Reload or reconcile the newer document draft before saving. Your local work is preserved.');
    }
    function edit(next: DocumentState | ((current: DocumentState) => DocumentState)) {
        const nextState = typeof next === 'function' ? next(state) : next;
        if (disposed || (status !== 'idle' && JSON.stringify(nextState) === JSON.stringify(state))) return;
        state = clone(nextState); generation += 1; invalidatePreview();
        if (!unknown && status !== 'conflict') { status = 'dirty'; error = null; }
        notify(); cancelTimer();
        if (typeof window !== 'undefined' && !unknown && status !== 'conflict' && options.autosaveMs !== 0) {
            if (typeof setTimeout !== 'undefined') {
                timer = setTimeout(() => { timer = undefined; void save().catch(() => {}); }, options.autosaveMs ?? 700);
            } else if (typeof window.setTimeout === 'function') {
                timer = window.setTimeout(() => { timer = undefined; void save().catch(() => {}); }, options.autosaveMs ?? 700);
            }
        }
    }
    function exclusive<T>(work: () => Promise<T>): Promise<T> {
        const job = writeTail.then(work);
        writeTail = job.catch(() => {});
        return job;
    }
    function headers(json = true): Record<string, string> {
        const token = options.csrf?.();
        return { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest', ...(json ? { 'Content-Type': 'application/json' } : {}), ...(token ? { 'X-CSRF-TOKEN': token } : {}) };
    }
    function diagnostic(result: Record<string, unknown>, fallback: string) {
        const details = Array.isArray(result.diagnostics) ? result.diagnostics : result.errors && typeof result.errors === 'object' ? Object.values(result.errors).flat() : [];
        return [String(result.message ?? fallback), ...details.map((item) => typeof item === 'string' ? item : JSON.stringify(item))].filter(Boolean).join(' ');
    }
    function adopt(result: Record<string, unknown>, op: Operation) {
        const returnedDraft = (result.document_draft ?? result.draft) as DocumentDraft | undefined;
        if ('document_draft' in result || 'draft' in result) draft = returnedDraft ? clone(returnedDraft) : null;
        if (op.kind === 'discard') draft = null;
        const returnedGuards = result.guards as Partial<DocumentGuards> | undefined;
        guards = {
            base_version: typeof result.base_version === 'number' ? result.base_version : returnedDraft?.base_version ?? guards.base_version,
            document_revision: typeof result.document_revision === 'number' ? result.document_revision : guards.document_revision,
            lock_version: typeof result.lock_version === 'number' || result.lock_version === null ? result.lock_version : returnedDraft?.lock_version ?? (draft ? guards.lock_version : null),
            ...returnedGuards,
        };
        renderSha = typeof result.render_sha256 === 'string' ? result.render_sha256 : returnedDraft?.render_sha256 ?? (draft ? renderSha : null);
        if (op.state) savedState = clone(op.state);
        if (op.kind === 'upload' && op.body?.has('document')) {
            const source = result.source as { asset_id: string };
            savedState = clone(returnedDraft?.state ?? { ...(op.state ?? savedState), source_kind: 'docx', document_asset_id: source.asset_id, builder_json: null });
        }
        if (generation === op.generation && (op.state || op.body?.has('document'))) state = clone(savedState);
        if (op.kind === 'discard' && generation === op.generation) state = savedState = emptyState();
        if (op.kind === 'discard' || op.kind === 'version') { lifecycle += 1; cancelTimer(); }
        unknown = null; error = null; status = changed() ? 'dirty' : 'saved'; invalidatePreview(); notify();
    }
    async function send(op: Operation): Promise<Record<string, unknown>> {
        loadSequence += 1;
        status = 'saving'; error = null; notify();
        let ambiguous = true;
        try {
            if (op.body) for (const [key, value] of Object.entries(op.payload)) op.body.set(key, typeof value === 'string' ? value : JSON.stringify(value));
            const response = await transport(op.url, { method: op.method, credentials: 'same-origin', headers: headers(!op.body), body: op.body ?? JSON.stringify(op.payload) });
            ambiguous = response.status >= 500;
            const result = await response.json().catch(() => ({})) as Record<string, unknown>;
            if (!response.ok) {
                if (!ambiguous) unknown = null;
                status = response.status === 409 ? 'conflict' : ambiguous ? 'unknown' : 'error';
                error = diagnostic(result, `The document operation failed (${response.status}). Your local work is preserved.`);
                invalidatePreview(); throw new Error(error);
            }
            const versionReceipt = result.document_version;
            const imageReceipt = result.asset;
            const sourceReceipt = result.source;
            const confirmed = op.kind === 'draft' ? Boolean(result.document_draft ?? result.draft)
                : op.kind === 'version' ? versionReceipt !== null && typeof versionReceipt === 'object'
                    && 'id' in versionReceipt && typeof versionReceipt.id === 'number' && Number.isSafeInteger(versionReceipt.id) && versionReceipt.id > 0
                    && 'version' in versionReceipt && typeof versionReceipt.version === 'number' && Number.isSafeInteger(versionReceipt.version) && versionReceipt.version > 0
                    && 'definition_sha256' in versionReceipt && typeof versionReceipt.definition_sha256 === 'string' && versionReceipt.definition_sha256.length > 0
                    && 'render_sha256' in versionReceipt && typeof versionReceipt.render_sha256 === 'string' && versionReceipt.render_sha256.length > 0
                : op.kind === 'discard' ? result.document_draft === null && typeof result.document_revision === 'number' && 'latest_ready_version' in result && typeof result.operation_id === 'string' && typeof result.replayed === 'boolean'
                : op.body?.has('image') ? imageReceipt !== null && typeof imageReceipt === 'object' && 'id' in imageReceipt && typeof imageReceipt.id === 'string' && typeof result.preview_url === 'string' && typeof result.operation_id === 'string'
                : sourceReceipt !== null && typeof sourceReceipt === 'object' && 'asset_id' in sourceReceipt && typeof sourceReceipt.asset_id === 'string' && typeof result.operation_id === 'string';
            if (!confirmed) { ambiguous = true; throw new Error('The server did not confirm the operation.'); }
            if (!disposed) adopt(result, op);
            return result;
        } catch (caught) {
            if (ambiguous) { unknown = op; status = 'unknown'; error = 'The save outcome is unknown. Your document work is retained; retry the same operation.'; invalidatePreview(); }
            notify(); throw caught;
        }
    }
    function operation(kind: Operation['kind'], url: string, method: string, payload: Record<string, unknown>, body?: FormData): Operation {
        const key = makeKey();
        return { kind, url, method, key, payload: { ...payload, idempotency_key: key }, body, generation, state: kind === 'draft' || body?.has('document') ? clone(state) : undefined };
    }
    function draftUrl(url: string) { return url.replace('{draft}', String(draft?.id)); }
    function save(stage = state.source_kind === 'builder' ? 2 : 1): Promise<Record<string, unknown>> {
        cancelTimer(); requestedStage = stage;
        if (saveJob) return saveJob;
        const requestedLifecycle = lifecycle;
        const job = exclusive(async () => {
            let result: Record<string, unknown> = {};
            do {
                assertWritable();
                if (lifecycle !== requestedLifecycle) throw new Error('This document draft was discarded or versioned. The queued save was cancelled.');
                if (!changed() && draft) return { ...result, document_draft: clone(draft), render_sha256: renderSha };
                const url = draft ? endpoints.update : endpoints.create;
                if (!url) throw new Error('Document draft saving is unavailable.');
                result = await send(operation('draft', draftUrl(url), draft ? 'PUT' : 'POST', { state: clone(state), stage: requestedStage, ...guards }));
            } while (changed() && !disposed);
            return result;
        });
        saveJob = job;
        void job.finally(() => { if (saveJob === job) saveJob = null; }).catch(() => {});
        return job;
    }
    async function retry() {
        cancelTimer();
        return exclusive(async () => {
            if (!unknown) throw new Error('There is no unknown document operation to retry.');
            return send(unknown);
        });
    }
    async function load(preserveLocal = true) {
        assertWritableForLoad();
        if (!endpoints.load) throw new Error('Reloading this document is unavailable.');
        const requestedGeneration = generation;
        await writeTail;
        assertWritableForLoad();
        const ticket = ++loadSequence;
        const response = await transport(endpoints.load, { credentials: 'same-origin', headers: headers(false) });
        const result = await response.json() as Record<string, unknown>;
        if (!response.ok) throw new Error(diagnostic(result, 'Could not reload the current document. Your local edits are retained.'));
        if (disposed || ticket !== loadSequence || generation !== requestedGeneration) return result;
        const workspace = (result.workspace ?? result) as DocumentWorkspaceProps['workspace'];
        if (!workspace.guards || !('draft' in workspace)) throw new Error('The reload response did not include a guarded document workspace.');
        cancelTimer(); draft = clone(workspace.draft); guards = { ...workspace.guards }; savedState = clone(draft?.state ?? emptyState());
        if (!preserveLocal) { state = clone(savedState); generation += 1; }
        renderSha = draft?.render_sha256 ?? workspace.render_sha256; error = null; status = changed() ? 'dirty' : draft ? 'saved' : 'idle'; invalidatePreview(); notify();
        return result;
    }
    function assertWritableForLoad() { if (unknown || disposed) throw new Error('Retry the unknown operation before reloading; your local work is preserved.'); }
    async function upload(file: File) {
        assertWritable();
        if (!endpoints.upload) throw new Error('Word upload is unavailable.');
        if (!/\.docx$/i.test(file.name) || file.size > 10 * 1024 * 1024 || file.size === 0) throw new Error('Choose a nonempty DOCX file of at most 10 MiB.');
        await save(1);
        return exclusive(() => {
            assertWritable(); const body = new FormData(); body.set('document', file);
            return send(operation('upload', endpoints.upload!, 'POST', { ...guards, document_draft_id: draft?.id }, body));
        });
    }
    async function uploadImage(file: File) {
        assertWritable();
        if (!endpoints.image_upload) throw new Error('Verified document image upload is unavailable.');
        if (!['image/png', 'image/jpeg'].includes(file.type) || !file.size || file.size > 2 * 1024 * 1024) throw new Error('Choose a PNG or JPEG image of at most 2 MiB.');
        await save();
        return exclusive(() => {
            assertWritable(); const body = new FormData(); body.set('image', file);
            return send(operation('upload', endpoints.image_upload!, 'POST', { ...guards, document_draft_id: draft?.id }, body));
        });
    }
    async function preview(): Promise<DocumentPreview> {
        assertWritable(); await save(); assertWritable();
        if (!endpoints.preview || !renderSha) throw new Error('A current document render is not available yet. Save the draft and try again.');
        const digest = renderSha; const requestedGeneration = generation; const ticket = ++previewSequence;
        previewResult = null; notify();
        try {
            const response = await transport(endpoints.preview, { method: 'POST', credentials: 'same-origin', headers: headers(), body: JSON.stringify({ ...guards, render_sha256: digest }) });
            const result = await response.json().catch(() => ({})) as Record<string, unknown>;
            if (disposed || ticket !== previewSequence || generation !== requestedGeneration || renderSha !== digest) throw new Error('The PDF check response is stale or incomplete. Check the current document again.');
            if (!response.ok) { status = response.status === 409 ? 'conflict' : 'error'; throw new Error(diagnostic(result, `PDF check failed (${response.status}).`)); }
            const pdf = result.pdf as DocumentPreview['pdf'] | undefined;
            let validBytes = false;
            try { validBytes = typeof pdf?.pdf_base64 === 'string' && atob(pdf.pdf_base64).startsWith('%PDF-'); } catch { validBytes = false; }
            if (result.render_sha256 !== digest || !validBytes || !pdf?.sha256 || typeof result.preview_receipt !== 'string' || !result.preview_receipt) throw new Error('The PDF check response is stale or incomplete. Check the current document again.');
            previewResult = { render_sha256: digest, pdf: pdf!, preview_receipt: result.preview_receipt, diagnostics: Array.isArray(result.diagnostics) ? result.diagnostics : [] };
            error = null; status = 'saved'; notify(); return clone(previewResult);
        } catch (caught) {
            if (!disposed && ticket === previewSequence && generation === requestedGeneration) { error = caught instanceof Error ? caught.message : 'PDF check failed. Retry the current document.'; if (status !== 'conflict') status = 'error'; notify(); }
            throw caught;
        }
    }
    async function saveVersion() {
        cancelTimer();
        return exclusive(() => {
            assertWritable();
            if (!previewResult || changed() || previewResult.render_sha256 !== renderSha) throw new Error('Run a successful current PDF check before saving a document version.');
            if (!endpoints.version) throw new Error('Saving a document version is unavailable.');
            return send(operation('version', endpoints.version, 'POST', { ...guards, render_sha256: renderSha, preview_receipt: previewResult.preview_receipt }));
        });
    }
    async function discard() {
        cancelTimer();
        return exclusive(() => {
            assertWritable();
            if (!draft || !endpoints.discard) throw new Error('There is no document draft to discard.');
            return send(operation('discard', draftUrl(endpoints.discard), 'DELETE', { ...guards }));
        });
    }
    function subscribe(listener: (value: DocumentSnapshot) => void) { listeners.add(listener); listener(snapshot()); return () => listeners.delete(listener); }
    function dispose() { disposed = true; cancelTimer(); previewSequence += 1; loadSequence += 1; listeners.clear(); }
    return { snapshot, edit, save, retry, load, upload, uploadImage, preview, saveVersion, discard, subscribe, dispose };
}
