// @vitest-environment jsdom
import { cleanup, fireEvent, render, waitFor } from '@testing-library/svelte';
import { afterEach, beforeEach, expect, it, vi } from 'vitest';
const visit = vi.hoisted(() => vi.fn());
vi.mock('@inertiajs/svelte', async (importOriginal) => {
    const actual = await importOriginal<Record<string, unknown>>();
    const actualRouter = (actual.router as Record<string, unknown>) ?? {};

    return {
        ...actual,
        usePage: () => ({
            props: {
                auth: {
                    user: { id: 1, name: 'Guidance Counselor', email: 'guidance.counselor@nexiam.test' },
                },
            },
        }),
        router: { ...actualRouter, visit, on: vi.fn(() => () => {}) },
    };
});

import PhysicalDocument from '../../resources/js/Pages/Setup/Notifications/Templates/PhysicalDocument.svelte';
import Studio from '../../resources/js/Pages/Setup/Notifications/Templates/Studio.svelte';
import { createNoticeDocumentStudioAdapter, type DocumentState, type DocumentWorkspaceProps } from '../../resources/js/lib/noticeDocumentStudio';
import ResultSheetStudio from '../../resources/js/Components/ResultSheet/ResultSheetStudio.svelte';
import ResultSheetComponentPreview from '../../resources/js/Components/ResultSheet/ResultSheetComponentPreview.svelte';
import { toasts } from '../../resources/js/lib/toast.js';

const originalAnimateDescriptor = Object.getOwnPropertyDescriptor(Element.prototype, 'animate');

const emptyState: DocumentState = { source_kind: 'builder', document_asset_id: null, builder_json: null, paper_size: 'a4', orientation: 'portrait' };
const props: DocumentWorkspaceProps = {
    family: { id: 4, domain: 'examination', slug: 'session-published', name: 'Examination permit' },
    workspace: { guards: { base_version: 2, document_revision: 3, lock_version: null }, draft: null, ready_versions: [], latest_ready_version: null, render_sha256: null, verified_images: [] },
    surface: {}, builderDefaults: {}, builderCatalog: { text: { label: 'Text', default_w: 100, default_h: 12 }, image: { label: 'Image' } }, builderCapabilities: {}, placeholderGroups: { applicant: [{ placeholder: 'application_no', description: 'Application number' }] }, sampleContext: {},
    endpoints: { create: '/document/drafts', update: '/document/drafts/1', discard: '/document/drafts/1', upload: '/document/upload', image_upload: '/document/images', image_content: '/document/images/{asset}', preview: '/document/preview', version: '/document/versions', candidate_return_url: '/setup/notifications/examination/templates/session-published/edit', return_url: '/family' }, return_url: '/family',
};
const response = (body: unknown, status = 200) => new Response(JSON.stringify(body), { status, headers: { 'Content-Type': 'application/json' } });
const readyDraft = (state: DocumentState, render_sha256 = 'render-current') => ({ id: 1, stage: 2, state, lock_version: 1, base_version: 2, render_sha256 });
const successfulPreview = (digest = 'render-current') => ({ render_sha256: digest, pdf: { pdf_base64: 'JVBERi0xLjQ=', sha256: 'pdf-sha256' }, preview_receipt: 'current-proof', diagnostics: [] });

function imageWorkspace(): DocumentWorkspaceProps {
    const state: DocumentState = { ...emptyState, builder_json: { components: [
        { id: 'image-a', type: 'image', x: 10, y: 10, w: 60, h: 40, locked: false, props: { alt: 'A remains blank' } },
        { id: 'image-b', type: 'image', x: 80, y: 10, w: 60, h: 40, locked: false, props: { alt: 'Existing B', asset_id: 'existing-image', src: '/document/images/existing-image' } },
    ] } };
    return { ...props, workspace: { ...props.workspace, guards: { base_version: 2, document_revision: 4, lock_version: 1 }, draft: readyDraft(state), render_sha256: 'render-current', verified_images: [{ id: 'existing-image', sha256: 'old-sha', mime: 'image/png', filename: 'old.png', url: '/document/images/existing-image' }] } };
}
const imageReceipt = () => ({ asset: { id: 'verified-image', filename: 'seal.png' }, preview_url: '/document/images/verified-image', document_revision: 5, lock_version: 2, operation_id: 'image-operation', replayed: false });
async function uploadSelectedImage(view: ReturnType<typeof render>) {
    await fireEvent.keyDown(view.getByRole('button', { name: 'Image component (2 of 2)' }), { key: 'Enter' });
    const input = view.getByLabelText('Upload image for selected document component') as HTMLInputElement;
    await waitFor(() => expect(input.disabled).toBe(false));
    await fireEvent.change(input, { target: { files: [new File(['image'], 'seal.png', { type: 'image/png' })] } });
}
async function saveImageDesign(view: ReturnType<typeof render>) {
    const save = view.getByRole('button', { name: 'Save draft', exact: true }) as HTMLButtonElement;
    await waitFor(() => expect(save.disabled).toBe(false));
    await fireEvent.click(save);
    await waitFor(() => expect(view.getByText('Document draft saved.')).toBeTruthy());
}
function imageComponents(state: DocumentState) {
    return state.builder_json?.components as { id: string; props: Record<string, unknown>; locked: boolean }[];
}
function saveImageResponse(init: RequestInit | undefined, saves: DocumentState[]) {
    const body = JSON.parse(String(init?.body));
    saves.push(body.state);
    return response({ document_draft: { ...readyDraft(body.state), lock_version: saves.length + 2 }, document_revision: saves.length + 5, render_sha256: 'render-current' });
}

beforeEach(() => {
    toasts.clear();
    Object.defineProperty(Element.prototype, 'animate', {
        configurable: true,
        value: vi.fn(() => ({ cancel: vi.fn(), currentTime: 0, onfinish: null })),
    });
});

afterEach(() => {
    cleanup();
    toasts.clear();
    visit.mockReset();
    vi.useRealTimers();
    vi.unstubAllGlobals();
    if (originalAnimateDescriptor) {
        Object.defineProperty(Element.prototype, 'animate', originalAnimateDescriptor);
    } else {
        Reflect.deleteProperty(Element.prototype, 'animate');
    }
    window.history.replaceState({}, '', '/');
});

it('presents Word or Designer before authoring and exposes the mobile Write/Preview switch', async () => {
    vi.stubGlobal('fetch', vi.fn(async (_url: RequestInfo | URL, init?: RequestInit) => {
        const payload = JSON.parse(String(init?.body ?? '{}'));
        return response({ document_draft: readyDraft(payload.state), document_revision: 4, base_version: 2, lock_version: 1, render_sha256: 'render-current' });
    }));
    const view = render(PhysicalDocument, { props });
    expect(view.getByRole('heading', { name: 'Choose a document source' })).toBeTruthy();
    expect((view.getByRole('button', { name: 'Save document version' }) as HTMLButtonElement).disabled).toBe(true);
    expect(view.getByRole('button', { name: 'Start from a Word document' })).toBeTruthy();
    expect(view.queryByTestId('result-sheet-studio')).toBeNull();
    await fireEvent.click(view.getByRole('button', { name: 'Design a document' }));
    expect(view.getByRole('button', { name: 'Write' })).toBeTruthy();
    await fireEvent.change(view.getByLabelText('Paper size'), { target: { value: 'legal' } });
    expect(view.getByRole('button', { name: 'Preview' })).toBeTruthy();
    expect(view.getByRole('region', { name: 'Write document' })).toBeTruthy();
    expect(view.getByRole('region', { name: 'Actual PDF preview' }).className).toMatch(/^hidden/);
    await fireEvent.click(view.getByRole('button', { name: 'Preview' }));
    await waitFor(() => expect(view.getByRole('region', { name: 'Actual PDF preview' }).className).not.toMatch(/^hidden/));
    await fireEvent.click(view.getByRole('button', { name: 'Write' }));
    expect(view.getByRole('heading', { name: 'Visual designer' })).toBeTruthy();
    expect((view.getByLabelText('Paper size') as HTMLSelectElement).value).toBe('legal');
});

it('does not treat an empty saved designer draft as a checked or ready document', async () => {
    const fetcher = vi.fn(async (url: RequestInfo | URL, init?: RequestInit) => {
        if (String(url) === '/document/drafts') {
            const body = JSON.parse(String(init?.body ?? '{}'));
            return response({ document_draft: readyDraft(body.state), document_revision: 4, base_version: 2, lock_version: 1, render_sha256: 'render-current' });
        }
        if (String(url) === '/document/preview') return response({ message: 'Add printable content and application number.' }, 422);
        throw new Error(`Unexpected request to ${String(url)}`);
    });
    const adapter = createNoticeDocumentStudioAdapter(props, { fetch: fetcher, key: () => 'stable-key' });
    adapter.edit(emptyState);
    await expect(adapter.preview()).rejects.toThrow('Add printable content');
    await expect(adapter.saveVersion()).rejects.toThrow('Run a successful current PDF check');
    expect(adapter.snapshot().preview).toBeNull();
    adapter.dispose();
});

it('saves a version only with the server-returned current PDF proof and invalidates it on edit', async () => {
    const calls: { url: string; body: Record<string, unknown> }[] = [];
    const fetcher = vi.fn(async (url: RequestInfo | URL, init?: RequestInit) => {
        const body = JSON.parse(String(init?.body ?? '{}')) as Record<string, unknown>;
        calls.push({ url: String(url), body });
        if (String(url) === '/document/drafts') return response({ document_draft: readyDraft(body.state as DocumentState), document_revision: 4, base_version: 2, lock_version: 1, render_sha256: 'render-current' });
        if (String(url) === '/document/preview') return response(successfulPreview());
        if (String(url) === '/document/versions') return response({ document_version: { id: 8, version: 1, definition_sha256: 'definition', render_sha256: 'render-current' }, document_revision: 5, document_draft: null, operation_id: 'version-op' });
        throw new Error(`Unexpected request to ${String(url)}`);
    });
    const adapter = createNoticeDocumentStudioAdapter(props, { fetch: fetcher, key: () => 'stable-key' });
    const meaningful = { ...emptyState, builder_json: { schema_version: '1.0.0', components: [{ id: 'body', type: 'text', x: 10, y: 10, w: 100, h: 12, props: { text: 'Printable permit' } }] } };
    adapter.edit(meaningful);
    const proof = await adapter.preview();
    expect(proof.pdf.pdf_base64).toBe('JVBERi0xLjQ=');
    await adapter.saveVersion();
    expect(calls.find((call) => call.url === '/document/versions')?.body.preview_receipt).toBe('current-proof');
    await expect(adapter.saveVersion()).rejects.toThrow('Run a successful current PDF check');
    adapter.dispose();
});
it('returns the immutable version from its save receipt as a pending direct-composer candidate', async () => {
    const state: DocumentState = { ...emptyState, builder_json: { components: [{ id: 'body', type: 'text', props: { text: 'Permit contents' } }] } };
    const initial = {
        ...props,
        workspace: {
            ...props.workspace,
            guards: { base_version: 4, document_revision: 9, lock_version: 2 },
            draft: readyDraft(state),
            render_sha256: 'render-current',
            ready_versions: [{ id: 207, version: 2, definition_sha256: 'older', render_sha256: 'older-render' }],
            latest_ready_version: { id: 207, version: 2, definition_sha256: 'older', render_sha256: 'older-render' },
        },
    };
    vi.stubGlobal('fetch', vi.fn(async (url: RequestInfo | URL) => {
        if (String(url) === '/document/preview') return response(successfulPreview());
        if (String(url) === '/document/versions') return response({
            document_version: { id: 208, version: 3, definition_sha256: 'exact-definition', render_sha256: 'render-current' },
            document_revision: 10,
            return_url: '/setup/notifications/examination/templates/session-published/edit',
            operation_id: 'version-op',
        });
        throw new Error(`Unexpected request to ${String(url)}`);
    }));
    const view = render(PhysicalDocument, { props: initial });
    await fireEvent.click(view.getByRole('button', { name: 'Check PDF' }));
    await waitFor(() => expect((view.getByRole('button', { name: 'Save document version' }) as HTMLButtonElement).disabled).toBe(false));
    await fireEvent.click(view.getByRole('button', { name: 'Save document version' }));
    await waitFor(() => expect(visit).toHaveBeenCalledTimes(1));
    const destination = new URL(String(visit.mock.calls[0][0]), window.location.origin);
    expect(destination.pathname).toBe('/setup/notifications/examination/templates/session-published/edit');
    expect(destination.searchParams.get('document_candidate_id')).toBe('208');
    expect(destination.searchParams.get('document_candidate_version')).toBe('3');
    expect(destination.searchParams.get('latest_ready_version')).toBeNull();
});
it('keeps a ready version receipt pending when an unknown retry resolves after newer local typing', async () => {
    const initialState: DocumentState = { ...emptyState, builder_json: { components: [{ id: 'body', type: 'text', props: { text: 'Permit contents' } }] } };
    const versionRequests: Record<string, unknown>[] = [];
    const savedStates: DocumentState[] = [];
    let versionAttempts = 0;
    const fetcher = vi.fn(async (url: RequestInfo | URL, init?: RequestInit) => {
        const body = JSON.parse(String(init?.body ?? '{}')) as Record<string, unknown>;
        if (String(url) === '/document/preview') return response(successfulPreview());
        if (String(url) === '/document/versions') {
            versionRequests.push(body);
            versionAttempts += 1;
            return response({
                document_version: versionAttempts === 1
                    ? { id: 208, version: 3, definition_sha256: 'partial' }
                    : { id: 208, version: 3, definition_sha256: 'exact-definition', render_sha256: 'render-current' },
                return_url: '/setup/notifications/examination/templates/session-published/edit',
                operation_id: 'version-op',
            });
        }
        if (String(url) === '/document/drafts/1') {
            savedStates.push(body.state as DocumentState);
            return response({ document_draft: readyDraft(body.state as DocumentState), document_revision: 11, base_version: 4, lock_version: 3, render_sha256: 'newer-render' });
        }
        throw new Error(`Unexpected request to ${String(url)}`);
    });
    const initial = {
        ...props,
        workspace: { ...props.workspace, guards: { base_version: 4, document_revision: 9, lock_version: 2 }, draft: readyDraft(initialState), render_sha256: 'render-current' },
    };
    vi.stubGlobal('fetch', fetcher);
    const view = render(PhysicalDocument, { props: initial });
    await fireEvent.click(view.getByRole('button', { name: 'Check PDF' }));
    await waitFor(() => expect((view.getByRole('button', { name: 'Save document version' }) as HTMLButtonElement).disabled).toBe(false));
    await fireEvent.click(view.getByRole('button', { name: 'Save document version' }));
    await waitFor(() => expect(view.getByRole('button', { name: 'Retry same operation' })).toBeTruthy());
    await fireEvent.change(view.getByLabelText('Paper size'), { target: { value: 'legal' } });
    await fireEvent.click(view.getByRole('button', { name: 'Retry same operation' }));
    await waitFor(() => expect(view.getByText(/Ready document version 3 is pending/)).toBeTruthy());
    expect(visit).not.toHaveBeenCalled();
    expect(versionRequests).toHaveLength(2);
    expect(versionRequests[1].idempotency_key).toBe(versionRequests[0].idempotency_key);
    expect((view.getByLabelText('Paper size') as HTMLSelectElement).value).toBe('legal');
    await fireEvent.click(view.getByRole('button', { name: 'Save newer edits and return' }));
    await waitFor(() => expect(visit).toHaveBeenCalledTimes(1));
    expect(savedStates).toHaveLength(1);
    expect(savedStates[0].paper_size).toBe('legal');
    const destination = new URL(String(visit.mock.calls[0][0]), window.location.origin);
    expect(destination.searchParams.get('document_candidate_id')).toBe('208');
    expect(destination.searchParams.get('document_candidate_version')).toBe('3');
});
it('cancels accessible source-change dialogs without mutation and returns focus to the trigger', async () => {
    const fetcher = vi.fn();
    vi.stubGlobal('fetch', fetcher);
    const initial = { ...props, workspace: { ...props.workspace, draft: readyDraft(emptyState), render_sha256: 'render-current' } };
    const view = render(PhysicalDocument, { props: initial });
    const trigger = view.getByRole('button', { name: 'Change source' });
    trigger.focus();
    expect(document.activeElement).toBe(trigger);
    await fireEvent.click(trigger);
    const dialog = view.getByRole('dialog', { name: 'Save and replace this document source?' });
    await waitFor(() => expect(dialog.contains(document.activeElement)).toBe(true));
    await fireEvent.keyDown(document, { key: 'Escape' });
    await waitFor(() => expect(view.queryByRole('dialog')).toBeNull());
    expect(document.activeElement).toBe(trigger);
    await fireEvent.click(trigger);
    const reopenedDialog = view.getByRole('dialog', { name: 'Save and replace this document source?' });
    await waitFor(() => expect(reopenedDialog.contains(document.activeElement)).toBe(true));
    await fireEvent.click(view.getByRole('button', { name: 'Keep current source' }));
    await waitFor(() => expect(view.queryByRole('dialog')).toBeNull());
    expect(document.activeElement).toBe(trigger);
    expect(fetcher).not.toHaveBeenCalled();
    expect((view.getByLabelText('Paper size') as HTMLSelectElement).value).toBe('a4');
});

it('requires explicit dialog confirmation before overwriting local work with a server reload', async () => {
    const serverState = { ...emptyState, paper_size: 'letter' };
    const requested: string[] = [];
    const fetcher = vi.fn(async (url: RequestInfo | URL, init?: RequestInit) => {
        requested.push(String(url));
        if (String(url) === '/document/drafts/1') return response({ message: 'A newer document draft exists.' }, 409);
        if (String(url) === '/document/load') return response({ workspace: { ...props.workspace, guards: { base_version: 2, document_revision: 9, lock_version: 6 }, draft: { ...readyDraft(serverState), lock_version: 6 }, render_sha256: 'server-render' } });
        throw new Error(`Unexpected request to ${String(url)}`);
    });
    vi.stubGlobal('fetch', fetcher);
    const initial = {
        ...props,
        endpoints: { ...props.endpoints, load: '/document/load' },
        workspace: { ...props.workspace, guards: { base_version: 2, document_revision: 4, lock_version: 1 }, draft: readyDraft(emptyState), render_sha256: 'render-current' },
    };
    const view = render(PhysicalDocument, { props: initial });
    await fireEvent.change(view.getByLabelText('Paper size'), { target: { value: 'legal' } });
    await fireEvent.click(view.getByRole('button', { name: 'Save draft' }));
    await waitFor(() => expect(view.getByRole('button', { name: 'Reload server draft' })).toBeTruthy());
    await fireEvent.click(view.getByRole('button', { name: 'Reload server draft' }));
    expect(view.getByRole('dialog', { name: 'Replace local work with the server draft?' })).toBeTruthy();
    expect(requested).not.toContain('/document/load');
    await fireEvent.click(view.getByRole('button', { name: 'Cancel' }));
    await waitFor(() => expect(view.queryByRole('dialog')).toBeNull());
    expect((view.getByLabelText('Paper size') as HTMLSelectElement).value).toBe('legal');
    expect(requested).not.toContain('/document/load');
    await fireEvent.click(view.getByRole('button', { name: 'Reload server draft' }));
    await fireEvent.click(view.getByRole('button', { name: 'Replace local work' }));
    await waitFor(() => expect(requested).toContain('/document/load'));
    await waitFor(() => expect((view.getByLabelText('Paper size') as HTMLSelectElement).value).toBe('letter'));
});
it('preserves a newer generation after exact version retry without resurrecting autosave', async () => {
    vi.useFakeTimers();
    const requests: string[] = [];
    let versionAttempts = 0;
    const fetcher = vi.fn(async (url: RequestInfo | URL, init?: RequestInit) => {
        requests.push(String(url));
        if (String(url) === '/document/drafts') {
            const body = JSON.parse(String(init?.body ?? '{}')) as { state: DocumentState };
            return response({ document_draft: readyDraft(body.state), document_revision: 4, base_version: 2, lock_version: 1, render_sha256: 'render-current' });
        }
        if (String(url) === '/document/preview') return response(successfulPreview());
        if (String(url) === '/document/versions') {
            versionAttempts += 1;
            return response({ document_version: versionAttempts === 1
                ? { id: 208, version: 3, definition_sha256: 'partial' }
                : { id: 208, version: 3, definition_sha256: 'exact-definition', render_sha256: 'render-current' } });
        }
        throw new Error(`Unexpected request to ${String(url)}`);
    });
    const adapter = createNoticeDocumentStudioAdapter(props, { fetch: fetcher, key: () => 'version-retry-key', autosaveMs: 700 });
    const original = { ...emptyState, builder_json: { components: [{ id: 'body', type: 'text', props: { text: 'Original' } }] } };
    const newer = { ...emptyState, builder_json: { components: [{ id: 'body', type: 'text', props: { text: 'Newer local edit' } }] } };
    adapter.edit(original);
    await adapter.preview();
    await expect(adapter.saveVersion()).rejects.toThrow('The server did not confirm the operation.');
    adapter.edit(newer);
    await adapter.retry();
    expect(adapter.snapshot().state).toEqual(newer);
    expect(adapter.snapshot().status).toBe('dirty');
    const requestCount = fetcher.mock.calls.length;
    await vi.advanceTimersByTimeAsync(700);
    expect(fetcher).toHaveBeenCalledTimes(requestCount);
    expect(requests.filter((url) => url === '/document/versions')).toHaveLength(2);
    adapter.dispose();
});
it('keeps a partial version receipt unknown across exact retry without auto-selecting it', async () => {
    const versionRequests: Record<string, unknown>[] = [];
    const fetcher = vi.fn(async (url: RequestInfo | URL, init?: RequestInit) => {
        if (String(url) === '/document/drafts') {
            const body = JSON.parse(String(init?.body ?? '{}')) as { state: DocumentState };
            return response({
                document_draft: readyDraft(body.state),
                document_revision: 4,
                base_version: 2,
                lock_version: 1,
                render_sha256: 'render-current',
            });
        }
        if (String(url) === '/document/preview') return response(successfulPreview());
        if (String(url) === '/document/versions') {
            versionRequests.push(JSON.parse(String(init?.body ?? '{}')) as Record<string, unknown>);
            return response({
                document_version: { id: 91, version: 4, definition_sha256: 'definition-only' },
                return_url: '/setup/notifications/examination/templates/session-published/edit',
                operation_id: 'incomplete-version-op',
            });
        }
        throw new Error(`Unexpected request to ${String(url)}`);
    });
    const adapter = createNoticeDocumentStudioAdapter(props, { fetch: fetcher, key: () => 'same-version-key' });
    adapter.edit({ ...emptyState, builder_json: { components: [{ id: 'body', type: 'text', props: { text: 'Printable content' } }] } });
    await adapter.preview();
    await expect(adapter.saveVersion()).rejects.toThrow('The server did not confirm the operation.');
    expect(adapter.snapshot().status).toBe('unknown');
    expect(adapter.snapshot().unknown_operation).toBe('version');
    await expect(adapter.retry()).rejects.toThrow('The server did not confirm the operation.');
    expect(versionRequests).toHaveLength(2);
    expect(versionRequests[1].idempotency_key).toBe(versionRequests[0].idempotency_key);
    expect(adapter.snapshot().status).toBe('unknown');
    expect(adapter.snapshot().unknown_operation).toBe('version');
    expect(visit).not.toHaveBeenCalled();
    adapter.dispose();
});



it('rejects stale server preview digests and replays an unknown save with its original key', async () => {
    let saves = 0;
    const requests: { url: string; body: Record<string, unknown> }[] = [];
    const fetcher = vi.fn(async (url: RequestInfo | URL, init?: RequestInit) => {
        const body = JSON.parse(String(init?.body ?? '{}')) as Record<string, unknown>;
        requests.push({ url: String(url), body });
        if (String(url) === '/document/drafts') {
            saves += 1;
            if (saves === 1) throw new TypeError('connection lost');
            return response({ document_draft: readyDraft(body.state as DocumentState), document_revision: 4, base_version: 2, lock_version: 1, render_sha256: 'render-current' });
        }
        if (String(url) === '/document/preview') return response(successfulPreview('stale-render'));
        throw new Error(`Unexpected request to ${String(url)}`);
    });
    const adapter = createNoticeDocumentStudioAdapter(props, { fetch: fetcher, key: () => `key-${requests.length + 1}` });
    const meaningful = { ...emptyState, builder_json: { components: [{ id: 'body', type: 'text', props: { text: 'Permit content' } }] } };
    adapter.edit(meaningful);
    await expect(adapter.save()).rejects.toThrow();
    expect(adapter.snapshot().unknown_operation).toBe('draft');
    const originalKey = requests[0].body.idempotency_key;
    await adapter.retry();
    expect(requests[1].body.idempotency_key).toBe(originalKey);
    await expect(adapter.preview()).rejects.toThrow('stale or incomplete');
    expect(adapter.snapshot().preview).toBeNull();
    await expect(adapter.saveVersion()).rejects.toThrow('Run a successful current PDF check');
    adapter.dispose();
});

it('uploads a same-family verified raster through the document-only route and adopts its returned guards', async () => {
    const state = { ...emptyState, builder_json: { components: [] } };
    const initial: DocumentWorkspaceProps = { ...props, workspace: { ...props.workspace, guards: { base_version: 2, document_revision: 4, lock_version: 1 }, draft: readyDraft(state), render_sha256: 'render-current' } };
    let request: RequestInit | undefined;
    const fetcher = vi.fn(async (_url: RequestInfo | URL, init?: RequestInit) => {
        request = init;
        return response({ asset: { id: 'private-asset-uuid', sha256: 'asset-hash', mime: 'image/png', filename: 'seal.png', width: 200, height: 200 }, preview_url: '/document/images/private-asset-uuid', document_revision: 5, lock_version: 2, base_version: 2, operation_id: 'image-upload-op', replayed: false });
    });
    const adapter = createNoticeDocumentStudioAdapter(initial, { fetch: fetcher, key: () => 'image-operation-key' });
    await adapter.uploadImage(new File(['png'], 'seal.png', { type: 'image/png' }));
    expect((request?.body as FormData).get('image')).toBeInstanceOf(File);
    expect((request?.body as FormData).get('document_draft_id')).toBe('1');
    expect((request?.body as FormData).get('idempotency_key')).toBe('image-operation-key');
    expect(adapter.snapshot().guards).toEqual({ base_version: 2, document_revision: 5, lock_version: 2 });
    adapter.dispose();
});
it('ignores a completed PDF check after a newer local document edit', async () => {
    const currentState: DocumentState = { ...emptyState, builder_json: { components: [{ id: 'body', type: 'text', props: { text: 'Current permit' } }] } };
    const initial: DocumentWorkspaceProps = { ...props, workspace: { ...props.workspace, guards: { base_version: 2, document_revision: 4, lock_version: 1 }, draft: readyDraft(currentState), render_sha256: 'render-current' } };
    let resolvePreview: ((value: Response) => void) | undefined;
    const fetcher = vi.fn(() => new Promise<Response>((resolve) => { resolvePreview = resolve; }));
    const adapter = createNoticeDocumentStudioAdapter(initial, { fetch: fetcher });
    const pendingPreview = adapter.preview();
    await waitFor(() => expect(resolvePreview).toBeTypeOf('function'));
    adapter.edit({ ...currentState, builder_json: { components: [{ id: 'body', type: 'text', props: { text: 'Newer permit text' } }] } });
    resolvePreview?.(response(successfulPreview()));
    await expect(pendingPreview).rejects.toThrow('stale or incomplete');
    expect(adapter.snapshot().preview).toBeNull();
    await expect(adapter.saveVersion()).rejects.toThrow('Run a successful current PDF check');
    adapter.dispose();
});

it('keeps message and document details out of a metadata-only family view', () => {
    const family = { id: 4, domain: 'Examination', slug: 'session-published', name: 'Examination permit', supports_pdf: true };
    const metadata = render(Studio, { props: {
        family, domain: 'examination', can_edit: false, read_only: false, back_url: '/family',
        authoring_mode: 'email_pdf',
        endpoints: { edit: '/private/message/edit', document_edit: '/private/document/edit', thumbnail: '/private/thumbnail', pdf: '/private/pdf' },
        document_summary: { has_ready_version: true, latest_ready_id: 99, latest_ready_version: 8, thumbnail_url: '/private/thumbnail', pdf_url: '/private/pdf' },
        message_composer: true,
        message_summary: { subject: 'PRIVATE SUBJECT', sample: 'PRIVATE MESSAGE' },
    } });

    expect(metadata.getByRole('heading', { name: 'Notice metadata' })).toBeTruthy();
    expect(metadata.queryByRole('heading', { name: 'PDF document' })).toBeNull();
    expect(metadata.queryByRole('heading', { name: 'Message' })).toBeNull();
    expect(metadata.container.innerHTML).not.toContain('/private/');
    expect(metadata.container.textContent).not.toContain('PRIVATE SUBJECT');
    expect(metadata.container.textContent).not.toContain('PRIVATE MESSAGE');
});

it('serializes and coalesces autosave without replacing newer local content with an earlier response', async () => {
    const requests: Record<string, unknown>[] = [];
    let finishFirst: ((value: Response) => void) | undefined;
    const fetcher = vi.fn(async (_url: RequestInfo | URL, init?: RequestInit) => {
        const body = JSON.parse(String(init?.body)) as Record<string, unknown>;
        requests.push(body);
        if (requests.length === 1) return new Promise<Response>((resolve) => { finishFirst = resolve; });
        return response({ document_draft: { ...readyDraft(body.state as DocumentState, 'new-render'), lock_version: 2 }, document_revision: 5, lock_version: 2, render_sha256: 'new-render' });
    });
    const adapter = createNoticeDocumentStudioAdapter(props, { fetch: fetcher, key: () => `save-${requests.length}`, autosaveMs: 0 });
    const older = { ...emptyState, paper_size: 'letter' };
    const newer = { ...emptyState, paper_size: 'legal', orientation: 'landscape' };
    adapter.edit(older);
    const saving = adapter.save();
    await waitFor(() => expect(finishFirst).toBeTypeOf('function'));
    adapter.edit(newer);
    const coalesced = adapter.save();
    expect(fetcher).toHaveBeenCalledTimes(1);
    finishFirst?.(response({ document_draft: readyDraft(older), document_revision: 4, lock_version: 1, render_sha256: 'older-render' }));
    await Promise.all([saving, coalesced]);
    expect(requests).toHaveLength(2);
    expect(requests[1].state).toEqual(newer);
    expect(requests[1].document_revision).toBe(4);
    expect(requests[1].lock_version).toBe(1);
    expect(adapter.snapshot().state).toEqual(newer);
    expect(adapter.snapshot().render_sha256).toBe('new-render');
    expect(adapter.snapshot().status).toBe('saved');
    adapter.dispose();
});

it('retains edits made after an unknown failure and replays the original payload and key before saving them', async () => {
    const requests: Record<string, unknown>[] = [];
    const fetcher = vi.fn(async (_url: RequestInfo | URL, init?: RequestInit) => {
        const body = JSON.parse(String(init?.body)) as Record<string, unknown>;
        requests.push(body);
        if (requests.length === 1) throw new TypeError('offline');
        return response({ document_draft: readyDraft(body.state as DocumentState), document_revision: requests.length + 2, lock_version: requests.length - 1, render_sha256: 'render-current' });
    });
    const adapter = createNoticeDocumentStudioAdapter(props, { fetch: fetcher, key: () => `key-${requests.length}`, autosaveMs: 0 });
    adapter.edit({ ...emptyState, paper_size: 'letter' });
    await expect(adapter.save()).rejects.toThrow('offline');
    const newer = { ...emptyState, paper_size: 'legal' };
    adapter.edit(newer);
    expect(adapter.snapshot().status).toBe('unknown');
    await adapter.retry();
    expect(requests[1]).toEqual(requests[0]);
    expect(adapter.snapshot().state).toEqual(newer);
    expect(adapter.snapshot().status).toBe('dirty');
    await adapter.save();
    expect(requests[2].state).toEqual(newer);
    expect(requests[2].idempotency_key).not.toBe(requests[0].idempotency_key);
    adapter.dispose();
});

it('preserves dirty work on conflict and explicitly reconciles current guards without losing local changes', async () => {
    const current = { ...emptyState, paper_size: 'letter' };
    const local = { ...emptyState, paper_size: 'legal' };
    let conflicted = false;
    const fetcher = vi.fn(async (url: RequestInfo | URL, init?: RequestInit) => {
        if (String(url) === '/document/load') return response({ workspace: { ...props.workspace, guards: { base_version: 2, document_revision: 9, lock_version: 6 }, draft: { ...readyDraft(current), lock_version: 6 }, render_sha256: 'remote-render' } });
        if (!conflicted) { conflicted = true; return response({ message: 'A newer draft exists.' }, 409); }
        const body = JSON.parse(String(init?.body)) as Record<string, unknown>;
        expect(body.document_revision).toBe(9);
        expect(body.lock_version).toBe(6);
        return response({ document_draft: readyDraft(body.state as DocumentState), document_revision: 10, lock_version: 7 });
    });
    const adapter = createNoticeDocumentStudioAdapter({ ...props, endpoints: { ...props.endpoints, load: '/document/load' } }, { fetch: fetcher, autosaveMs: 0 });
    adapter.edit(local);
    await expect(adapter.save()).rejects.toThrow('newer draft');
    expect(adapter.snapshot().status).toBe('conflict');
    expect(adapter.snapshot().state).toEqual(local);
    await expect(adapter.save()).rejects.toThrow('Reload or reconcile');
    await adapter.load(true);
    expect(adapter.snapshot().state).toEqual(local);
    expect(adapter.snapshot().guards.document_revision).toBe(9);
    await adapter.save();
    expect(adapter.snapshot().status).toBe('saved');
    adapter.dispose();
});

it('enables version save only after a valid PDF receipt and immediately disables it when paper changes', async () => {
    const current = { ...emptyState, builder_json: { components: [{ id: 'application', type: 'field', props: { binding: 'application_no', label: 'Application number' }, x: 10, y: 10, w: 100, h: 12 }] } };
    vi.stubGlobal('fetch', vi.fn(async (url: RequestInfo | URL) => {
        if (String(url) === '/document/preview') return response(successfulPreview());
        throw new Error('Unexpected document mutation');
    }));
    const view = render(PhysicalDocument, { props: { ...props, workspace: { ...props.workspace, guards: { base_version: 2, document_revision: 4, lock_version: 1 }, draft: readyDraft(current), render_sha256: 'render-current' } } });
    const saveButton = view.getByRole('button', { name: 'Save document version' }) as HTMLButtonElement;
    expect(saveButton.disabled).toBe(true);
    await fireEvent.click(view.getByRole('button', { name: 'Check PDF' }));
    await waitFor(() => expect(saveButton.disabled).toBe(false));
    expect(view.getByTitle('Checked PDF document preview').getAttribute('src')).toBe('data:application/pdf;base64,JVBERi0xLjQ=');
    await fireEvent.change(view.getByLabelText('Paper size'), { target: { value: 'legal' } });
    expect(saveButton.disabled).toBe(true);
    expect(view.queryByTitle('Checked PDF document preview')).toBeNull();
    expect(view.getByText('This PDF check is no longer current.')).toBeTruthy();
});


it('offers verified image insertion and never uses an unverified same-origin URL as image preview authority', async () => {
    const view = render(ResultSheetStudio, { props: { builderDocument: { components: [] }, builderCatalog: { image: { label: 'Image', description: 'Insert verified image', default_w: 60, default_h: 40 } }, allowNoticeDocumentImages: true, noticeDocumentImageAssets: [] } });
    expect(view.getByRole('button', { name: 'Image' })).toBeTruthy();
    view.unmount();
    const preview = render(ResultSheetComponentPreview, { props: { component: { type: 'image', props: { asset_id: 'not-verified', src: '/private/arbitrary' } }, noticeDocumentImagePreview: true, noticeDocumentImageAssets: [{ asset_id: 'verified', src: '/family/images/verified', label: 'Seal' }] } });
    expect(preview.queryByRole('img')).toBeNull();
    expect(preview.getByText('Verified image unavailable')).toBeTruthy();
    preview.unmount();
    const verified = render(ResultSheetComponentPreview, { props: { component: { type: 'image', props: { asset_id: 'verified', src: 'https://external.invalid/image', alt: 'Seal' } }, noticeDocumentImagePreview: true, noticeDocumentImageAssets: [{ asset_id: 'verified', src: '/family/images/verified', label: 'Seal' }] } });
    expect(verified.getByRole('img', { name: 'Seal' }).getAttribute('src')).toContain('/family/images/verified');
});

it('ignores an earlier PDF response without replacing a newer current preview receipt', async () => {
    const current = { ...emptyState, builder_json: { components: [{ id: 'field', type: 'field', props: { binding: 'application_no' } }] } };
    const initial = { ...props, workspace: { ...props.workspace, guards: { base_version: 2, document_revision: 4, lock_version: 1 }, draft: readyDraft(current), render_sha256: 'render-current' } };
    const completions: ((value: Response) => void)[] = [];
    const adapter = createNoticeDocumentStudioAdapter(initial, { fetch: vi.fn(() => new Promise<Response>((resolve) => completions.push(resolve))), autosaveMs: 0 });
    const first = adapter.preview();
    await waitFor(() => expect(completions).toHaveLength(1));
    const firstRejection = expect(first).rejects.toThrow('stale or incomplete');
    const second = adapter.preview();
    await waitFor(() => expect(completions).toHaveLength(2));
    completions[1](response({ ...successfulPreview(), preview_receipt: 'newer-current-proof' }));
    await second;
    completions[0](response({ ...successfulPreview(), preview_receipt: 'older-proof' }));
    await firstRejection;
    expect(adapter.snapshot().preview?.preview_receipt).toBe('newer-current-proof');
    expect(adapter.snapshot().status).toBe('saved');
    expect(adapter.snapshot().guards.document_revision).toBe(4);
    adapter.dispose();
});

it('cancels a queued autosave after confirmed discard instead of resurrecting the discarded draft', async () => {
    const initial = { ...props, workspace: { ...props.workspace, guards: { base_version: 2, document_revision: 4, lock_version: 1 }, draft: readyDraft(emptyState), render_sha256: 'render-current' } };
    let finishDiscard: ((value: Response) => void) | undefined;
    const fetcher = vi.fn(() => new Promise<Response>((resolve) => { finishDiscard = resolve; }));
    const adapter = createNoticeDocumentStudioAdapter(initial, { fetch: fetcher, autosaveMs: 0 });
    const discarding = adapter.discard();
    await waitFor(() => expect(finishDiscard).toBeTypeOf('function'));
    const lateSave = adapter.save();
    const rejectedSave = expect(lateSave).rejects.toThrow('queued save was cancelled');
    finishDiscard?.(response({ document_draft: null, document_revision: 5, latest_ready_version: null, operation_id: 'discard-operation', replayed: false }));
    await discarding;
    await rejectedSave;
    expect(fetcher).toHaveBeenCalledTimes(1);
    expect(adapter.snapshot().draft).toBeNull();
    adapter.dispose();
});

it('blocks invalid PDF bytes even when the server returns a receipt and render digest', async () => {
    const current = { ...emptyState, builder_json: { components: [] } };
    const initial = { ...props, workspace: { ...props.workspace, guards: { base_version: 2, document_revision: 4, lock_version: 1 }, draft: readyDraft(current), render_sha256: 'render-current' } };
    const adapter = createNoticeDocumentStudioAdapter(initial, { fetch: vi.fn(async () => response({ ...successfulPreview(), pdf: { pdf_base64: btoa('not a PDF'), sha256: 'invalid-output' } })), autosaveMs: 0 });
    await expect(adapter.preview()).rejects.toThrow('stale or incomplete');
    expect(adapter.snapshot().preview).toBeNull();
    await expect(adapter.saveVersion()).rejects.toThrow('Run a successful current PDF check');
    adapter.dispose();
});

it.each([409, 422])('reconciles an unknown save after an authoritative no-write %i without losing local work', async (httpStatus) => {
    const requests: Record<string, unknown>[] = [];
    const local = { ...emptyState, paper_size: 'legal' };
    const newer = { ...local, orientation: 'landscape' };
    const fetcher = vi.fn(async (url: RequestInfo | URL, init?: RequestInit) => {
        if (String(url) === '/document/load') return response({ workspace: { ...props.workspace, guards: { base_version: 2, document_revision: 9, lock_version: null } } });
        const body = JSON.parse(String(init?.body));
        requests.push(body);
        if (requests.length === 1) throw new TypeError('offline');
        if (requests.length === 2) return response({ message: 'No write occurred.' }, httpStatus);
        return response({ document_draft: readyDraft(body.state), document_revision: 10, render_sha256: 'render-current' });
    });
    let key = 0;
    const adapter = createNoticeDocumentStudioAdapter({ ...props, endpoints: { ...props.endpoints, load: '/document/load' } }, { fetch: fetcher, key: () => `operation-${++key}`, autosaveMs: 0 });
    adapter.edit(local);
    await expect(adapter.save()).rejects.toThrow();
    adapter.edit(newer);
    await expect(adapter.retry()).rejects.toThrow('No write occurred');
    expect(requests[1]).toEqual(requests[0]);
    expect(adapter.snapshot().unknown_operation).toBeNull();
    expect(adapter.snapshot().status).toBe(httpStatus === 409 ? 'conflict' : 'error');
    expect(adapter.snapshot().state).toEqual(newer);
    await adapter.load(true);
    await adapter.save();
    expect(requests[2].idempotency_key).not.toBe(requests[0].idempotency_key);
    expect(requests[2].document_revision).toBe(9);
    expect(adapter.snapshot().state).toEqual(newer);
    adapter.dispose();
});

it.each(['admissions', 'examination', 'results', 'Results'])('inserts only supported notice defaults for canonical %s DTOs', async (domain) => {
    const saves: DocumentState[] = [];
    vi.stubGlobal('fetch', vi.fn(async (_url: RequestInfo | URL, init?: RequestInit) => {
        const body = JSON.parse(String(init?.body));
        saves.push(body.state);
        return response({ document_draft: readyDraft(body.state), document_revision: saves.length + 3, render_sha256: 'render-current' });
    }));
    const view = render(PhysicalDocument, { props: { ...props, family: { ...props.family, domain }, builderCatalog: { header: { label: 'Header' }, field: { label: 'Field' } } } });
    await fireEvent.click(view.getByRole('button', { name: 'Design a document' }));
    await fireEvent.click(view.getByRole('button', { name: 'Header', exact: true }));
    await fireEvent.click(view.getByRole('button', { name: 'Field', exact: true }));
    await fireEvent.click(view.getByRole('button', { name: 'Save draft', exact: true }));
    await waitFor(() => expect(view.getByText('Document draft saved.')).toBeTruthy());
    const components = saves[saves.length - 1].builder_json?.components as { type: string; props: Record<string, unknown> }[];
    expect(components.find((item) => item.type === 'header')?.props.subtitle).toBe(props.family.name);
    expect(components.find((item) => item.type === 'field')?.props.binding).toBe(domain.toLowerCase() === 'results' ? 'applicant_reference' : 'application_no');
    expect(JSON.stringify(components)).not.toContain('institution_exam_name');
    if (domain.toLowerCase() !== 'results') expect(JSON.stringify(components)).not.toContain('applicant_reference');
});

it('defaults field binding to applicant_reference for lowercase results domain', async () => {
    const saves: DocumentState[] = [];
    vi.stubGlobal('fetch', vi.fn(async (_url: RequestInfo | URL, init?: RequestInit) => {
        const body = JSON.parse(String(init?.body));
        saves.push(body.state);
        return response({ document_draft: readyDraft(body.state), document_revision: 4, render_sha256: 'render-current' });
    }));
    const view = render(PhysicalDocument, { props: { ...props, family: { ...props.family, domain: 'results' }, builderCatalog: { field: { label: 'Field' } } } });
    await fireEvent.click(view.getByRole('button', { name: 'Design a document' }));
    await fireEvent.click(view.getByRole('button', { name: 'Field', exact: true }));
    await fireEvent.click(view.getByRole('button', { name: 'Save draft', exact: true }));
    await waitFor(() => expect(view.getByText('Document draft saved.')).toBeTruthy());
    const components = saves[saves.length - 1].builder_json?.components as { type: string; props: Record<string, unknown> }[];
    expect(components.find((item) => item.type === 'field')?.props.binding).toBe('applicant_reference');
    expect(components.find((item) => item.type === 'field')?.props.label).toBe('Applicant number');
});

it('saves an inserted blank image before uploading and attaching a verified same-family image', async () => {
    const saves: Record<string, unknown>[] = [];
    const calls: string[] = [];
    vi.stubGlobal('fetch', vi.fn(async (url: RequestInfo | URL, init?: RequestInit) => {
        calls.push(String(url));
        if (String(url) === '/document/images') {
            expect(init?.body).toBeInstanceOf(FormData);
            expect((init?.body as FormData).get('document_draft_id')).toBe('1');
            return response({ asset: { id: 'verified-image', filename: 'seal.png' }, preview_url: '/document/images/verified-image', operation_id: 'image-operation', replayed: false });
        }
        const body = JSON.parse(String(init?.body));
        saves.push(body);
        return response({ document_draft: { ...readyDraft(body.state), lock_version: saves.length }, document_revision: saves.length + 3, render_sha256: 'render-current' });
    }));
    const view = render(PhysicalDocument, { props });
    await fireEvent.click(view.getByRole('button', { name: 'Design a document' }));
    await fireEvent.click(view.getByRole('button', { name: 'Image', exact: true }));
    expect((view.getByRole('button', { name: 'Check PDF', exact: true }) as HTMLButtonElement).disabled).toBe(true);
    expect((view.getByRole('button', { name: 'Save document version' }) as HTMLButtonElement).disabled).toBe(true);
    await fireEvent.click(view.getByRole('button', { name: 'Save draft', exact: true }));
    await waitFor(() => expect(view.getByText('Document draft saved.')).toBeTruthy());
    expect((saves[0].state as DocumentState).builder_json?.components).toEqual(expect.arrayContaining([expect.objectContaining({ props: expect.not.objectContaining({ asset_id: expect.anything() }) })]));
    const imageFile = new File(['image'], 'seal.png', { type: 'image/png' });
    const fileInput = view.getByLabelText('Upload image for selected document component') as HTMLInputElement;
    await waitFor(() => expect(fileInput.disabled).toBe(false));
    Object.defineProperty(fileInput, 'files', { value: [imageFile], configurable: true });
    await fireEvent.change(fileInput, { target: { files: [imageFile] } });
    await waitFor(() => expect((view.getByRole('button', { name: 'Check PDF', exact: true }) as HTMLButtonElement).disabled).toBe(false));
    await fireEvent.click(view.getByRole('button', { name: 'Save draft', exact: true }));
    await waitFor(() => expect(saves.some((body) => JSON.stringify(body.state).includes('verified-image'))).toBe(true));
    expect(calls.indexOf('/document/images')).toBeGreaterThan(calls.indexOf('/document/drafts'));
    expect((view.getByRole('button', { name: 'Save document version' }) as HTMLButtonElement).disabled).toBe(true);
});

it('preserves default report/result bindings when notice catalog overrides are absent', async () => {
    const changes: Record<string, unknown>[] = [];
    const view = render(ResultSheetStudio, { props: { builderDocument: { components: [] }, builderCatalog: { header: { label: 'Header' }, field: { label: 'Field' } }, onChange: (next: Record<string, unknown>) => changes.push(next) } });
    await fireEvent.click(view.getByRole('button', { name: 'Header', exact: true }));
    await fireEvent.click(view.getByRole('button', { name: 'Field', exact: true }));
    const components = changes[changes.length - 1].components as { type: string; props: Record<string, unknown> }[];
    expect(components.find((item) => item.type === 'header')?.props.subtitle).toBe('{{institution_exam_name}} ({{institution_exam_acronym}})');
    expect(components.find((item) => item.type === 'field')?.props.binding).toBe('applicant_reference');
});

it('does not treat an incomplete successful discard response as authoritative confirmation', async () => {
    const initial = { ...props, workspace: { ...props.workspace, guards: { base_version: 2, document_revision: 4, lock_version: 1 }, draft: readyDraft(emptyState) } };
    const adapter = createNoticeDocumentStudioAdapter(initial, { fetch: vi.fn(async () => response({ document_draft: null })), autosaveMs: 0 });
    await expect(adapter.discard()).rejects.toThrow('did not confirm');
    expect(adapter.snapshot().unknown_operation).toBe('discard');
    expect(adapter.snapshot().draft?.id).toBe(1);
    adapter.dispose();
});

it('replaces only selected B without filling unrelated blank A', async () => {
    const initial = imageWorkspace();
    const saves: DocumentState[] = [];
    vi.stubGlobal('fetch', vi.fn(async (url: RequestInfo | URL, init?: RequestInit) => String(url) === '/document/images' ? response(imageReceipt()) : saveImageResponse(init, saves)));
    const view = render(PhysicalDocument, { props: initial });
    await uploadSelectedImage(view);
    await waitFor(() => expect((view.getByLabelText('Verified document image') as HTMLSelectElement).value).toBe('verified-image'));
    await saveImageDesign(view);
    const components = imageComponents(saves[saves.length - 1]);
    expect(components[0].id).toBe('image-a');
    expect(components[0].props.alt).toBe('A remains blank');
    expect(components[0].props.asset_id).toBeUndefined();
    expect(components[1].props).toMatchObject({ alt: 'Existing B', asset_id: 'verified-image', src: '/document/images/verified-image' });
});

it('registers family toolbar upload without mutating existing canvas components', async () => {
    const initial = imageWorkspace();
    const saves: DocumentState[] = [];
    vi.stubGlobal('fetch', vi.fn(async (url: RequestInfo | URL, init?: RequestInit) => String(url) === '/document/images' ? response(imageReceipt()) : saveImageResponse(init, saves)));
    const view = render(PhysicalDocument, { props: initial });
    const input = view.getByLabelText('Upload verified document image') as HTMLInputElement;
    await waitFor(() => expect(input.disabled).toBe(false));
    await fireEvent.change(input, { target: { files: [new File(['image'], 'seal.png', { type: 'image/png' })] } });
    await waitFor(() => expect(view.getByText('Verified image added to this document family. Use the Designer palette to place it.')).toBeTruthy());
    await fireEvent.keyDown(view.getByRole('button', { name: 'Image component (2 of 2)' }), { key: 'Enter' });
    expect(view.getByRole('option', { name: 'Image verified' })).toBeTruthy();
    expect((view.getByLabelText('Verified document image') as HTMLSelectElement).value).toBe('existing-image');
    await fireEvent.keyDown(view.getByRole('button', { name: 'Image component (1 of 2)' }), { key: 'Enter' });
    expect((view.getByLabelText('Verified document image') as HTMLSelectElement).value).toBe('');
    expect(view.getByRole('button', { name: 'Image component (1 of 2)' }).textContent).toContain('Verified image unavailable');
    await saveImageDesign(view);
    expect(saves).toEqual([]);
});

it('disposes cleanly during SSR when window is undefined without ReferenceError', () => {
    const originalWindow = globalThis.window;
    try {
        // @ts-expect-error simulating SSR environment
        delete globalThis.window;
        const adapter = createNoticeDocumentStudioAdapter(props, { autosaveMs: 100 });
        adapter.edit({ ...emptyState, paper_size: 'letter' });
        expect(() => adapter.dispose()).not.toThrow();
    } finally {
        globalThis.window = originalWindow;
    }
});

it('replays a lost image response with the same file and key and attaches only original B after selecting A', async () => {
    const initial = imageWorkspace();
    const uploads: FormData[] = [];
    const saves: DocumentState[] = [];
    vi.stubGlobal('fetch', vi.fn(async (url: RequestInfo | URL, init?: RequestInit) => {
        if (String(url) !== '/document/images') return saveImageResponse(init, saves);
        uploads.push(init?.body as FormData);
        if (uploads.length === 1) throw new TypeError('lost upload response');
        return response({ ...imageReceipt(), replayed: true });
    }));
    const view = render(PhysicalDocument, { props: initial });
    await uploadSelectedImage(view);
    await waitFor(() => expect(view.getByRole('button', { name: 'Retry same operation' })).toBeTruthy());
    await fireEvent.keyDown(view.getByRole('button', { name: 'Image component (1 of 2)' }), { key: 'Enter' });
    await fireEvent.click(view.getByRole('button', { name: 'Retry same operation' }));
    await waitFor(() => expect(view.queryByRole('button', { name: 'Retry same operation' })).toBeNull());
    expect(uploads).toHaveLength(2);
    expect(uploads[1].get('idempotency_key')).toBe(uploads[0].get('idempotency_key'));
    expect(uploads[1].get('image')).toBe(uploads[0].get('image'));
    expect((view.getByLabelText('Verified document image') as HTMLSelectElement).value).toBe('');
    await waitFor(() => {
        const option = view.getByRole('option', { name: 'Image verified' });
        expect(option).toBeTruthy();
        expect(option.textContent?.trim()).toBe('Image verified');
    });
    await saveImageDesign(view);
    const components = imageComponents(saves[saves.length - 1]);
    expect(components[0]).toEqual(imageComponents(initial.workspace.draft!.state)[0]);
    expect(components[1].props.asset_id).toBe('verified-image');
    await fireEvent.keyDown(view.getByRole('button', { name: 'Image component (2 of 2)' }), { key: 'Enter' });
    expect((view.getByLabelText('Verified document image') as HTMLSelectElement).value).toBe('verified-image');
});

it.each(['deleted', 'locked'])('registers a replayed asset but never resurrects or modifies the %s upload target', async (targetState) => {
    const initial = imageWorkspace();
    const saves: DocumentState[] = [];
    let uploads = 0;
    vi.stubGlobal('fetch', vi.fn(async (url: RequestInfo | URL, init?: RequestInit) => {
        if (String(url) !== '/document/images') return saveImageResponse(init, saves);
        if (++uploads === 1) throw new TypeError('lost response');
        return response({ ...imageReceipt(), replayed: true });
    }));
    const view = render(PhysicalDocument, { props: initial });
    await uploadSelectedImage(view);
    await waitFor(() => expect(view.getByRole('button', { name: 'Retry same operation' })).toBeTruthy());
    await fireEvent.keyDown(view.getByRole('button', { name: 'Image component (2 of 2)' }), { key: 'Enter' });
    await fireEvent.click(view.getByTitle(targetState === 'deleted' ? 'Delete component (Delete)' : 'Lock component'));
    if (targetState === 'deleted') {
        await waitFor(() => expect(view.queryByRole('button', { name: 'Image component (2 of 2)' })).toBeNull());
    } else {
        await waitFor(() => expect(view.getByTitle('Unlock component')).toBeTruthy());
    }
    await fireEvent.click(view.getByRole('button', { name: 'Retry same operation' }));
    await waitFor(() => expect(view.queryByRole('button', { name: 'Retry same operation' })).toBeNull());
    await saveImageDesign(view);
    const components = imageComponents(saves[saves.length - 1]);
    expect(components[0].id).toBe('image-a');
    expect(components[0].props.alt).toBe('A remains blank');
    expect(components[0].props.asset_id).toBeUndefined();
    const target = components.find((item) => item.id === 'image-b');
    if (targetState === 'deleted') expect(target).toBeUndefined();
    else expect(target).toMatchObject({ id: 'image-b', locked: true, props: expect.objectContaining({ asset_id: 'existing-image' }) });
    expect(JSON.stringify(components)).not.toContain('verified-image');
    await fireEvent.keyDown(view.getByRole('button', { name: `Image component (1 of ${components.length})` }), { key: 'Enter' });
    await waitFor(() => expect(view.getByRole('option', { name: 'Image verified' })).toBeTruthy());
});

it.each([false, true])('preserves content after a rejected image upload (response previously lost: %s)', async (lostResponse) => {
    const initial = imageWorkspace();
    const saves: DocumentState[] = [];
    let uploads = 0;
    vi.stubGlobal('fetch', vi.fn(async (url: RequestInfo | URL, init?: RequestInit) => {
        if (String(url) !== '/document/images') return saveImageResponse(init, saves);
        if (++uploads === 1 && lostResponse) throw new TypeError('lost response');
        return response({ message: 'Unsafe image; no write occurred.' }, 422);
    }));
    const view = render(PhysicalDocument, { props: initial });
    await uploadSelectedImage(view);
    if (lostResponse) {
        await waitFor(() => expect(view.getByRole('button', { name: 'Retry same operation' })).toBeTruthy());
        await fireEvent.click(view.getByRole('button', { name: 'Retry same operation' }));
    }
    await waitFor(() => {
        expect(view.getByText(/Unsafe image/)).toBeTruthy();
    });
    await waitFor(() => expect(view.queryByRole('button', { name: 'Retry same operation' })).toBeNull());
    expect((view.getByLabelText('Verified document image') as HTMLSelectElement).value).toBe('existing-image');
    expect(view.queryByRole('option', { name: 'Image verified' })).toBeNull();
    await saveImageDesign(view);
    expect(saves).toEqual([]);
    const input = view.getByLabelText('Upload image for selected document component') as HTMLInputElement;
    expect(input.disabled).toBe(false);
});

it('does not schedule autosave or touch browser timers during server initialization, editing and disposal', () => {
    vi.stubGlobal('window', undefined);
    const fetcher = vi.fn();
    const schedule = vi.spyOn(globalThis, 'setTimeout');
    const cancel = vi.spyOn(globalThis, 'clearTimeout');
    const adapter = createNoticeDocumentStudioAdapter(props, { fetch: fetcher });
    const listener = vi.fn();
    const unsubscribe = adapter.subscribe(listener);
    adapter.edit({ ...emptyState, paper_size: 'legal' });
    expect(adapter.snapshot().status).toBe('dirty');
    unsubscribe();
    expect(() => { adapter.dispose(); adapter.dispose(); }).not.toThrow();
    expect(schedule).not.toHaveBeenCalled();
    expect(cancel).not.toHaveBeenCalled();
    expect(fetcher).not.toHaveBeenCalled();
    schedule.mockRestore();
    cancel.mockRestore();
});

it('coalesces browser autosave and cancels pending saves and subscriptions on disposal', async () => {
    vi.useFakeTimers();
    const fetcher = vi.fn(async (_url: RequestInfo | URL, init?: RequestInit) => {
        const body = JSON.parse(String(init?.body));
        return response({ document_draft: readyDraft(body.state), document_revision: 4, render_sha256: 'render-current' });
    });
    const adapter = createNoticeDocumentStudioAdapter(props, { fetch: fetcher });
    const listener = vi.fn();
    adapter.subscribe(listener);
    adapter.edit({ ...emptyState, paper_size: 'letter' });
    adapter.edit({ ...emptyState, paper_size: 'legal' });
    await vi.advanceTimersByTimeAsync(700);
    expect(fetcher).toHaveBeenCalledTimes(1);
    expect(adapter.snapshot().status).toBe('saved');
    adapter.edit({ ...emptyState, paper_size: 'a4' });
    const notifications = listener.mock.calls.length;
    adapter.dispose();
    adapter.edit({ ...emptyState, orientation: 'landscape' });
    await vi.advanceTimersByTimeAsync(700);
    expect(fetcher).toHaveBeenCalledTimes(1);
    expect(listener).toHaveBeenCalledTimes(notifications);
});

it('server-renders and disposes the actual physical document page without a window or a save request', () => {
    const fetcher = vi.fn();
    const adapter = createNoticeDocumentStudioAdapter(props, { fetch: fetcher, autosaveMs: 100 });
    adapter.edit({ ...emptyState, paper_size: 'legal' });
    expect(() => adapter.dispose()).not.toThrow();
    expect(fetcher).not.toHaveBeenCalled();
});
