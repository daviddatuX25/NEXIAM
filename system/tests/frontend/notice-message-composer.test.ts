// @vitest-environment jsdom
if (typeof Element !== 'undefined' && !Element.prototype.animate) {
    Element.prototype.animate = () => ({ onfinish: null, cancel: () => {}, finish: () => {}, addEventListener: () => {}, removeEventListener: () => {} } as unknown as Animation);
}
import { cleanup, fireEvent, render, waitFor } from '@testing-library/svelte';
import { afterEach, expect, it, vi } from 'vitest';
import Message from '../../resources/js/Pages/Setup/Notifications/Templates/Message.svelte';
import { createNoticeMessageComposer, type MessageComposerProps, type MessageState } from '../../resources/js/lib/noticeMessageComposer';
import { blueprintToText, textToBlueprint } from '../../resources/js/lib/noticeMessageBody';

const state: MessageState = { subject: 'Exam reminder', body: 'Portal caption', action_label: 'View schedule', action_route: '/schedule', document_revision_id: null, email_blueprint: { schema_version: 'notice-email-1', layout: 'flow', blocks: [{ type: 'paragraph', runs: [{ text: 'Hello' }] }, { type: 'image', asset_id: 'asset-1', alt: 'Current campus', width: 600, height: 300 }] } };
const props: MessageComposerProps = { family: { id: 1, domain: 'Examination', slug: 'reminder', name: 'Examination reminder', active_version: 3, family_revision: 4 }, state, draft: { id: 8, lock_version: 2, state, updated_at: 'Today' }, base_version: 3, content_sha256: 'content-1', output_digests: { email_sha256: 'email-1', portal_sha256: 'portal-1' }, tokens: [{ token: 'applicant_name', label: 'Applicant name' }], assets: [{ asset_id: 'asset-1', sha256: 'abc', mime: 'image/png', width: 600, height: 300, preview_url: '/private/image' }], sample_context: { applicant_name: 'A. Sample' }, endpoints: { create: '/drafts', update: '/drafts/{draft}', preview: '/preview', publish: '/publish', test_mail: '/test-mail', image_upload: '/images', return_url: '/family' } };
const previewReceipt = (view: 'email' | 'portal', digest: string) => new Response(JSON.stringify({ view, output_sha256: digest, preview_receipt: `${view}-proof`, ...(view === 'email' ? { email: { production_html: '<p>Hello</p>', preview_html: '<p>Hello</p>', text: 'Hello', assets: [], projection_sha256: 'projection' } } : { portal: { title: 'Reminder', body: 'Caption' } }) }), { status: 200 });

async function waitForComposerReady(container: HTMLElement): Promise<void> {
    await waitFor(() => expect(container.querySelector('[data-composer-status]')?.textContent).toContain('Draft saved'));
}
async function setEditableText(element: HTMLElement, text: string): Promise<void> {
    element.textContent = text;
    await fireEvent.input(element);
}
function selectEditableText(element: HTMLElement, start: number, end: number): void {
    const node = element.firstChild;
    if (!node) return;
    const range = document.createRange();
    range.setStart(node, start);
    range.setEnd(node, end);
    const selection = window.getSelection();
    selection?.removeAllRanges();
    selection?.addRange(range);
}

afterEach(() => { cleanup(); vi.unstubAllGlobals(); window.history.replaceState({}, '', '/'); });

it('exposes the separate friendly composer, accessible previews and owned image alt/replace affordance', async () => {
    let resolveImageUpload: ((response: Response) => void) | undefined;
    let savedImageBlock: Record<string, unknown> | undefined;
    vi.stubGlobal('fetch', vi.fn(async (url: RequestInfo | URL, init?: RequestInit) => {
        if (String(url) === '/drafts/8') {
            const savedState = JSON.parse(String(init?.body ?? '{}')).state;
            savedImageBlock = savedState.email_blueprint.blocks[savedState.email_blueprint.blocks.length - 1];
            return new Response(JSON.stringify({ operation_id: 'save-image-alt', family_revision: 5, active_version: 3, content_sha256: 'content-alt', output_digests: { email_sha256: 'email-alt', portal_sha256: 'portal-1' }, draft: { id: 8, lock_version: 3, state: savedState, updated_at: 'Now' } }), { status: 200 });
        }
        return new Promise<Response>((resolve) => { resolveImageUpload = resolve; });
    }));
    const view = render(Message, { props });
    await waitForComposerReady(view.container);
    expect(view.getByRole('heading', { name: 'Message composer' })).toBeTruthy();
    expect(view.getByRole('textbox', { name: /Portal caption/ })).toBeTruthy();
    const emailPreviewButton = view.getByRole('button', { name: 'Email' });
    const portalPreviewButton = view.getByRole('button', { name: 'Portal' });
    expect(emailPreviewButton.getAttribute('aria-pressed')).toBe('true');
    expect(portalPreviewButton.getAttribute('aria-pressed')).toBe('false');
    expect(view.container.querySelector('[data-notice-message-action-footer]')?.classList.contains('md:left-56')).toBe(true);
    expect(view.getByRole('textbox', { name: 'Image alt text' })).toHaveProperty('value', 'Current campus');
    expect(view.getByRole('button', { name: 'Replace image' })).toBeTruthy();
    expect(view.container.querySelector('img[alt="Current campus"]')?.getAttribute('src')).toBe('/private/image');
    expect(view.container.textContent).not.toMatch(/Layers|Inspector|Word upload|PDF|thumbnail|Setup\/Design\/Verify/);
    await fireEvent.input(view.getByRole('textbox', { name: 'Image alt text' }), { target: { value: 'Accessible campus photo' } });
    expect(view.getByRole('textbox', { name: 'Image alt text' })).toHaveProperty('value', 'Accessible campus photo');
    vi.stubGlobal('crypto', { randomUUID: () => 'image-key' });
    const NativeURL = globalThis.URL;
    const TestURL = class extends NativeURL {};
    Object.assign(TestURL, { createObjectURL: () => 'blob:uploaded-image', revokeObjectURL: () => {} });
    vi.stubGlobal('URL', TestURL);
    await fireEvent.click(view.getByRole('button', { name: 'Replace image' }));
    const file = new File(['pixels'], 'campus.png', { type: 'image/png' });
    await fireEvent.change(view.getByLabelText('Upload a PNG or JPEG image'), { target: { files: [file] } });
    await view.findByText('Uploading image…');
    await waitFor(() => expect(resolveImageUpload).toBeDefined());
    resolveImageUpload?.(new Response(JSON.stringify({ operation_id: 'image-op', family_revision: 6, asset: { id: 'asset-2', sha256: 'def', mime: 'image/png', width: 1200, height: 800 } }), { status: 200 }));
    await waitFor(() => expect(view.queryByText('Uploading image…')).toBeNull());
    expect(view.queryByRole('alert')).toBeNull();
    expect(view.container.querySelector('img[alt=""]')?.getAttribute('src')).toBe('blob:uploaded-image');
    expect(view.container.querySelectorAll('img[alt=""]')).toHaveLength(1);
    await fireEvent.click(view.getByRole('button', { name: 'Save draft' }));
    await waitFor(() => expect(savedImageBlock).toBeDefined());
    expect(savedImageBlock).toMatchObject({ width: 600, height: 400 });

});
it('initializes from Inertia reactive state and edits nested table runs', async () => {
    const tableState: MessageState = {
        ...state,
        email_blueprint: { schema_version: 'notice-email-1', layout: 'flow', blocks: [
            { type: 'table', rows: [[{ runs: [{ text: 'Cell' }] }]] },
        ] },
    };
    const reactiveState = new Proxy(tableState, {});
    const reactiveDraft = new Proxy({ ...props.draft!, state: reactiveState }, {});
    const view = render(Message, { props: { ...props, state: reactiveState, draft: reactiveDraft } });
    await waitForComposerReady(view.container);

    expect(tableState.email_blueprint.blocks).toEqual([{ type: 'table', rows: [[{ runs: [{ text: 'Cell' }] }]] }]);
    expect(view.getByRole('textbox', { name: 'Email message body' })).toHaveProperty('value', '');
});

it('keeps Email current when only the Portal caption changes and discards a late preview after relevant edits', async () => {
    const fetcher = vi.fn(async (url: RequestInfo | URL) => {
        if (String(url) === '/preview') return previewReceipt('email', 'email-1');
        throw new Error('Unexpected request');
    });
    const adapter = createNoticeMessageComposer(props, { fetch: fetcher as typeof fetch });
    expect(await adapter.preview('email')).toMatchObject({ output_sha256: 'email-1' });
    adapter.edit({ ...state, body: 'Portal-only caption change' });
    expect(adapter.snapshot().previews.email?.output_sha256).toBe('email-1');
    adapter.dispose();

    let resolvePreview: ((response: Response) => void) | undefined;
    const delayed = vi.fn(() => new Promise<Response>((resolve) => { resolvePreview = resolve; }));
    const lateAdapter = createNoticeMessageComposer(props, { fetch: delayed as typeof fetch });
    const request = lateAdapter.preview('email');
    await waitFor(() => expect(delayed).toHaveBeenCalled());
    lateAdapter.edit({ ...state, subject: 'A different shared title' });
    resolvePreview?.(previewReceipt('email', 'email-1'));
    expect(await request).toBeNull();
    expect(lateAdapter.snapshot().previews.email).toBeUndefined();
    lateAdapter.dispose();
});

it('retains changed input and reuses the idempotency key after unknown save outcome', async () => {
    const saved = { operation_id: 'op-2', family_revision: 5, active_version: 3, content_sha256: 'content-2', output_digests: { email_sha256: 'email-2', portal_sha256: 'portal-2' }, draft: { id: 8, lock_version: 3, state, updated_at: 'Now' } };
    const fetcher = vi.fn().mockRejectedValueOnce(new TypeError('connection lost')).mockResolvedValueOnce(new Response(JSON.stringify(saved), { status: 200 }));
    const adapter = createNoticeMessageComposer(props, { fetch: fetcher as typeof fetch, key: () => 'stable-save-key' });
    adapter.edit({ ...state, subject: 'Edited reminder' });
    await expect(adapter.save()).rejects.toThrow();
    expect(adapter.snapshot().state.subject).toBe('Edited reminder');
    expect(adapter.snapshot().status).toBe('unknown');
    await adapter.retry();
    expect(fetcher.mock.calls[0][1]?.body).toBe(fetcher.mock.calls[1][1]?.body);
    expect(adapter.snapshot().state.subject).toBe('Edited reminder');
    expect(adapter.snapshot().status).toBe('saved');
    adapter.dispose();
});
it('adopts the concrete server update endpoint after creation and uses it for the next save', async () => {
    const requests: { url: string; body: Record<string, unknown> }[] = [];
    const fetcher = vi.fn(async (url: RequestInfo | URL, init?: RequestInit) => {
        const body = JSON.parse(String(init?.body ?? '{}')) as Record<string, unknown>;
        requests.push({ url: String(url), body });
        const nextLockVersion = requests.length + 1;
        return new Response(JSON.stringify({
            operation_id: `save-${requests.length}`, family_revision: 4 + requests.length,
            active_version: 3, content_sha256: `content-${requests.length}`,
            output_digests: { email_sha256: 'email-1', portal_sha256: 'portal-1' },
            draft: { id: 8, lock_version: nextLockVersion, state: body.state, updated_at: 'Now',
                endpoints: { update: '/opaque/drafts/8' } },
        }), { status: 200 });
    });
    const composer = createNoticeMessageComposer({ ...props, draft: null, endpoints: { ...props.endpoints, update: undefined } },
        { fetch: fetcher as typeof fetch, key: (() => { let value = 0; return () => `save-${++value}`; })() });
    await composer.save();
    composer.edit({ ...composer.snapshot().state, subject: 'Changed title' });
    await composer.save();
    expect(requests.map((request) => request.url)).toEqual(['/drafts', '/opaque/drafts/8']);
    expect(requests[1].body).toMatchObject({ lock_version: 2, family_revision: 5, state: { subject: 'Changed title' } });
    composer.dispose();
});

it('keeps input after validation and conflict failures without claiming it saved', async () => {
    const fetcher = vi.fn().mockResolvedValue(new Response(JSON.stringify({ message: 'Subject is required.' }), { status: 422 }));
    const adapter = createNoticeMessageComposer(props, { fetch: fetcher as typeof fetch, key: () => 'one-key' });
    adapter.edit({ ...state, subject: '' });
    await expect(adapter.save()).rejects.toThrow('Subject is required.');
    expect(adapter.snapshot().state.subject).toBe('');
    expect(adapter.snapshot().status).toBe('error');
    expect(adapter.snapshot().content_sha256).toBe('content-1');
    adapter.dispose();
});
it('retains local input and does not claim a saved receipt after a stale-draft conflict', async () => {
    const fetcher = vi.fn().mockResolvedValue(new Response(JSON.stringify({ message: 'Draft revision changed.' }), { status: 409 }));
    const adapter = createNoticeMessageComposer(props, { fetch: fetcher as typeof fetch, key: () => 'conflict-key' });
    adapter.edit({ ...state, body: 'Unsaved caption remains here' });
    await expect(adapter.save()).rejects.toThrow('Draft revision changed.');
    expect(adapter.snapshot().state.body).toBe('Unsaved caption remains here');
    expect(adapter.snapshot().status).toBe('conflict');
    expect(adapter.snapshot().content_sha256).toBe('content-1');
    adapter.dispose();
});
it('uses an inline link editor without a blocking prompt and persists its selected-run URL', async () => {
    const prompt = vi.spyOn(window, 'prompt');
    let savedState: MessageState | null = null;
    vi.stubGlobal('fetch', vi.fn(async (_url: RequestInfo | URL, init?: RequestInit) => {
        savedState = JSON.parse(String(init?.body ?? '{}')).state as MessageState;
        return new Response(JSON.stringify({ operation_id: 'link-save', family_revision: 5, active_version: 3, content_sha256: 'link-content', output_digests: { email_sha256: 'link-email', portal_sha256: 'link-portal' }, draft: { id: 8, lock_version: 3, state: savedState, updated_at: 'Now' } }), { status: 200 });
    }));
    const view = render(Message, { props });
    await waitForComposerReady(view.container);
    const body = view.getByRole('textbox', { name: 'Email message body' });
    await fireEvent.input(body, { target: { value: 'https://example.test/reminder' } });
    expect(body).toHaveProperty('value', 'https://example.test/reminder');
    expect(prompt).not.toHaveBeenCalled();
    await fireEvent.click(view.getByRole('button', { name: 'Save draft' }));
    await waitFor(() => expect(savedState).not.toBeNull());
    expect(savedState?.email_blueprint?.blocks[0].runs[0]).toMatchObject({ text: 'https://example.test/reminder' });
});

it('preserves typed token runs and existing formatting while editing and saving content', async () => {
    const richState: MessageState = { ...state, email_blueprint: { schema_version: 'notice-email-1', layout: 'flow', blocks: [{ type: 'paragraph', runs: [{ text: 'Hello', bold: true }, { token: 'applicant_name' }, { text: '!', italic: true }] }] } };
    let savedBody = '';
    vi.stubGlobal('fetch', vi.fn(async (_url: RequestInfo | URL, init?: RequestInit) => {
        savedBody = String(init?.body ?? '');
        const statePayload = JSON.parse(savedBody).state;
        return new Response(JSON.stringify({ operation_id: 'save-rich', family_revision: 5, active_version: 3, content_sha256: 'content-rich', output_digests: { email_sha256: 'email-rich', portal_sha256: 'portal-rich' }, draft: { id: 8, lock_version: 3, state: statePayload, updated_at: 'Now' } }), { status: 200 });
    }));
    const view = render(Message, { props: { ...props, state: richState, draft: { ...props.draft!, state: richState } } });
    await waitForComposerReady(view.container);
    await fireEvent.input(view.getByRole('textbox', { name: 'Email message body' }), { target: { value: 'Welcome' } });
    await fireEvent.click(view.getByRole('button', { name: 'Save draft' }));
    await waitFor(() => expect(savedBody).not.toBe(''));
    expect(JSON.parse(savedBody).state.email_blueprint.blocks[0].runs[0].text).toBe('Welcome');
});

it('removes a typed token from the message body without changing adjacent text', async () => {
    const richState: MessageState = { ...state, email_blueprint: { schema_version: 'notice-email-1', layout: 'flow', blocks: [{ type: 'paragraph', runs: [{ text: 'Before ' }, { token: 'applicant_name' }, { text: ' after' }] }] } };
    const view = render(Message, { props: { ...props, state: richState, draft: { ...props.draft!, state: richState } } });
    await waitForComposerReady(view.container);
    const body = view.getByRole('textbox', { name: 'Email message body' });
    expect(body).toHaveProperty('value', 'Before {{applicant_name}} after');
    await fireEvent.input(body, { target: { value: 'Before  after' } });
    expect(body).toHaveProperty('value', 'Before  after');
});

it('inserts a catalog token into the message body and transmits it when saving', async () => {
    let savedBody = '';
    vi.stubGlobal('fetch', vi.fn(async (_url: RequestInfo | URL, init?: RequestInit) => {
        savedBody = String(init?.body ?? '');
        const statePayload = JSON.parse(savedBody).state;
        return new Response(JSON.stringify({ operation_id: 'token-save', family_revision: 5, active_version: 3, content_sha256: 'token-content', output_digests: { email_sha256: 'token-email', portal_sha256: 'token-portal' }, draft: { id: 8, lock_version: 3, state: statePayload, updated_at: 'Now' } }), { status: 200 });
    }));
    const view = render(Message, { props });
    await waitForComposerReady(view.container);
    await fireEvent.click(view.getByRole('button', { name: /\+ Applicant name/i }));
    const body = view.getByRole('textbox', { name: 'Email message body' });
    await waitFor(() => expect(body).toHaveProperty('value', 'Hello {{applicant_name}}'));
    await fireEvent.click(view.getByRole('button', { name: 'Save draft' }));
    await waitFor(() => expect(savedBody).not.toBe(''));
    expect(savedBody).toContain('"token":"applicant_name"');
});

it('changes the real Email iframe CSS viewport at 390, 768 and 1024 pixels without page scaling', async () => {
    vi.stubGlobal('fetch', vi.fn(async (_url: RequestInfo | URL, init?: RequestInit) => {
        const request = JSON.parse(String(init?.body ?? '{}')) as { view: 'email' | 'portal' };
        return previewReceipt(request.view, request.view === 'email' ? 'email-1' : 'portal-1');
    }));
    const view = render(Message, { props });
    await waitForComposerReady(view.container);
    await fireEvent.click(view.getByRole('button', { name: 'Check preview' }));
    const frame = await view.findByTitle('Current production Email preview');
    for (const width of [768, 1024, 390]) {
        await fireEvent.click(view.getByRole('button', { name: `${width}px` }));
        expect(frame.getAttribute('width')).toBe(String(width));
        expect((frame as HTMLIFrameElement).style.width).toBe(`${width}px`);
    }
    expect(frame.parentElement?.className).toContain('overflow-x-auto');
});
it('opens a future-only publish confirmation without publishing and cancels safely', async () => {
    const requests: Array<{ url: string; method: string }> = [];
    vi.stubGlobal('fetch', vi.fn(async (url: RequestInfo | URL, init?: RequestInit) => {
        requests.push({ url: String(url), method: init?.method ?? 'GET' });
        if (String(url) === '/preview') {
            const body = JSON.parse(String(init?.body ?? '{}')) as { view: 'email' | 'portal' };
            return previewReceipt(body.view, body.view === 'email' ? 'email-1' : 'portal-1');
        }
        throw new Error(`Unexpected request to ${String(url)}`);
    }));
    const view = render(Message, { props });
    await waitForComposerReady(view.container);
    const check = view.getByRole('button', { name: 'Check preview' });
    await fireEvent.click(check);
    const review = await view.findByRole('button', { name: 'Review & publish' });
    await fireEvent.click(review);
    const dialog = await view.findByRole('dialog', { name: 'Review and publish message?' });
    expect(dialog.textContent).toContain('future occurrences only');
    expect(dialog.textContent).toContain('no attachment');
    expect(requests.filter((request) => request.method === 'POST').map((request) => request.url)).toEqual(['/preview', '/preview']);
    await fireEvent.click(view.getByRole('button', { name: 'Cancel' }));
    expect(view.queryByRole('dialog')).toBeNull();
    expect(document.activeElement).toBe(review);
});
it('offers only server-canonical action enablement and keeps section navigation local', async () => {
    const requests: string[] = [];
    vi.stubGlobal('fetch', vi.fn(async (url: RequestInfo | URL) => {
        requests.push(String(url));
        throw new Error('No request expected');
    }));
    const view = render(Message, { props: { ...props, family: { ...props.family, default_action_route: 'applicant.schedule' } } });
    await waitForComposerReady(view.container);
    const before = [...requests];
    expect(view.getByRole('checkbox', { name: /Include action button/ })).toBeTruthy();
    await fireEvent.click(view.getByRole('checkbox', { name: /Include action button/ }));
    expect(view.getByRole('textbox', { name: 'Shared action label' })).toBeTruthy();
    expect((view.getByLabelText('Shared action destination') as HTMLInputElement).disabled).toBe(true);
    expect(requests).toEqual(before);
});

it('sends a synthetic test only to the verified staff address and reports its real receipt', async () => {
    const requests: Array<{ url: string; body: Record<string, unknown> }> = [];
    vi.stubGlobal('fetch', vi.fn(async (url: RequestInfo | URL, init?: RequestInit) => {
        const path = String(url);
        const body = JSON.parse(String(init?.body ?? '{}')) as Record<string, unknown>;
        requests.push({ url: path, body });
        if (path === '/preview') {
            const view = body.view as 'email' | 'portal';
            return previewReceipt(view, view === 'email' ? 'email-1' : 'portal-1');
        }
        return new Response(JSON.stringify({ operation_id: 'mail-op', test_send_id: 'test-9', status: 'sent', recipient_masked: 's***@nexiam.test' }), { status: 200 });
    }));
    const view = render(Message, { props });
    await waitForComposerReady(view.container);
    await fireEvent.click(view.getByRole('button', { name: 'Check preview' }));
    const sendButton = view.getByRole('button', { name: 'Send synthetic test email to verified staff address' }) as HTMLButtonElement;
    await waitFor(() => expect(sendButton.disabled).toBe(false));
    await fireEvent.click(sendButton);
    expect(await view.findByText(/test-9: sent to s\*\*\*@nexiam\.test/)).toBeTruthy();
    const request = requests.find((item) => item.url === '/test-mail');
    expect(request?.body).toMatchObject({ email_sha256: 'email-1', preview_receipt: 'email-proof' });
    expect(request?.body).not.toHaveProperty('recipient');
    expect(view.queryByLabelText(/applicant email/i)).toBeNull();
});
it('keeps an exact returned document candidate pending until explicit attach and saves that exact pin', async () => {
    let savedMessage: MessageState | null = null;
    const candidate = { id: 30, version: 3, definition_sha256: 'candidate-definition', render_sha256: 'candidate-render', pdf_url: '/candidate.pdf', endpoints: { pdf: '/candidate.pdf', thumbnail: '/candidate.png' } };
    const pdfProps: MessageComposerProps & { pending_document_candidate: typeof candidate } = {
        ...props,
        family: { ...props.family, supports_pdf: true },
        supports_pdf: true,
        state: { ...state, document_revision_id: null },
        draft: { ...props.draft!, state: { ...state, document_revision_id: null } },
        output_digests: { ...props.output_digests, binding_sha256: 'binding-none' },
        document_versions: [{ id: 30, version: 3, definition_sha256: candidate.definition_sha256, render_sha256: candidate.render_sha256, pdf_url: candidate.pdf_url }],
        endpoints: { ...props.endpoints, update: '/drafts/8', attachment_check: '/attachment-check', document_edit: '/document/edit' },
        pending_document_candidate: candidate,
    };
    vi.stubGlobal('fetch', vi.fn(async (_url: RequestInfo | URL, init?: RequestInit) => {
        savedMessage = JSON.parse(String(init?.body ?? '{}')).state as MessageState;
        return new Response(JSON.stringify({
            operation_id: 'attach-candidate',
            family_revision: 5,
            active_version: 3,
            content_sha256: 'candidate-content',
            output_digests: { email_sha256: 'email-candidate', portal_sha256: 'portal-candidate', binding_sha256: 'binding-candidate' },
            draft: { id: 8, lock_version: 3, state: savedMessage, updated_at: 'Now' },
        }), { status: 200 });
    }));
    const view = render(Message, { props: pdfProps });
    await waitForComposerReady(view.container);
    expect(view.getByText(/Current saved attachment: none/)).toBeTruthy();
    expect(view.getByRole('link', { name: 'Preview exact candidate version 3' }).getAttribute('href')).toBe('/candidate.pdf');
    await fireEvent.click(view.getByRole('button', { name: 'Attach this version' }));
    await waitFor(() => expect(savedMessage?.document_revision_id).toBe(30));
    expect(view.queryByText(/Document version 3 is ready/)).toBeNull();
    expect(view.getByText(/Attachment: .*v3/)).toBeTruthy();
});

it('retains exact text after native Back when the author chooses Stay', async () => {
    window.history.replaceState({ editor: 'current' }, '', '/notice/edit');
    const view = render(Message, { props });
    await waitForComposerReady(view.container);
    const caption = view.getByRole('textbox', { name: /Portal caption/ });
    await fireEvent.input(caption, { target: { value: 'Keep these exact unsaved words' } });
    window.dispatchEvent(new PopStateEvent('popstate', { state: { target: 'family' } }));
    await view.findByRole('alertdialog');
    await fireEvent.click(view.getByRole('button', { name: 'Stay' }));
    await waitFor(() => expect(view.queryByRole('alertdialog')).toBeNull());
    expect(view.getByRole('textbox', { name: /Portal caption/ })).toHaveProperty('value', 'Keep these exact unsaved words');
    expect(window.location.pathname).toBe('/notice/edit');
});
it('offers Stay before desk navigation leaves a dirty composer', async () => {
    const view = render(Message, { props });
    await waitForComposerReady(view.container);
    const subject = view.getByRole('textbox', { name: 'Email subject and Portal title' });
    await fireEvent.input(subject, { target: { value: 'Internal navigation keeps this title' } });
    await fireEvent.click(view.getByRole('link', { name: 'Setup', exact: true }));
    expect(view.getByRole('alertdialog')).toBeTruthy();
    await fireEvent.click(view.getByRole('button', { name: 'Stay' }));
    expect(view.getByRole('textbox', { name: /Email subject/ })).toHaveProperty('value', 'Internal navigation keeps this title');
});
it('retries an unknown synthetic test with the same idempotency key and receipt contract', async () => {
    const testRequests: string[] = [];
    let attempts = 0;
    const fetcher = vi.fn(async (url: RequestInfo | URL, init?: RequestInit) => {
        if (String(url) === '/preview') return previewReceipt('email', 'email-1');
        testRequests.push(String(init?.body));
        attempts++;
        if (attempts === 1) throw new TypeError('connection lost');
        return new Response(JSON.stringify({ operation_id: 'mail-op-2', test_send_id: 'test-10', status: 'sent', recipient_masked: 's***@nexiam.test' }), { status: 200 });
    });
    const adapter = createNoticeMessageComposer(props, { fetch: fetcher as typeof fetch, key: () => 'stable-test-key' });
    await adapter.preview('email');
    await expect(adapter.sendTestMail()).rejects.toThrow();
    expect(adapter.snapshot().unknown_operation).toBe('test_mail');
    expect(await adapter.sendTestMail()).toMatchObject({ test_send_id: 'test-10', recipient_masked: 's***@nexiam.test' });
    expect(testRequests[0]).toBe(testRequests[1]);
    expect(JSON.parse(testRequests[1]).idempotency_key).toBe('stable-test-key');
    adapter.dispose();
});
it('reuses an unknown image upload operation and adopts only its new family guard', async () => {
    const formBodies: FormData[] = [];
    let attempts = 0;
    const fetcher = vi.fn(async (url: RequestInfo | URL, init?: RequestInit) => {
        if (String(url) !== '/images') throw new Error('Unexpected request');
        const body = init?.body as FormData;
        formBodies.push(body);
        attempts++;
        if (attempts === 1) throw new TypeError('connection lost');
        return new Response(JSON.stringify({ operation_id: 'image-op-2', family_revision: 6, asset: { id: 'asset-3', sha256: 'ghi', mime: 'image/jpeg', width: 100, height: 80 } }), { status: 200 });
    });
    const adapter = createNoticeMessageComposer(props, { fetch: fetcher as typeof fetch, key: () => 'stable-image-key' });
    const file = new File(['pixels'], 'reminder.jpg', { type: 'image/jpeg' });
    await expect(adapter.uploadImage(file)).rejects.toThrow();
    expect(adapter.snapshot().unknown_operation).toBe('image_upload');
    const digestsBefore = adapter.snapshot().output_digests;
    await adapter.uploadImage(file);
    expect(formBodies[0].get('idempotency_key')).toBe('stable-image-key');
    expect(formBodies[1].get('idempotency_key')).toBe('stable-image-key');
    expect(adapter.snapshot().guards.family_revision).toBe(6);
    expect(adapter.snapshot().output_digests).toEqual(digestsBefore);
    adapter.dispose();
});
it('round-trips blank lines and preserves semantic elements in their original flow positions', () => {
    const source = 'First paragraph\n\nSection heading\n\n\nLast paragraph';
    const explicitEmptyParagraphs = {
        blocks: [
            { type: 'paragraph', runs: [{ text: 'First' }] },
            { type: 'paragraph', runs: [{ text: '' }] },
            { type: 'paragraph', runs: [{ text: 'Last' }] },
        ],
    };
    expect(blueprintToText(explicitEmptyParagraphs)).toBe('First\n\n\n\nLast');
    expect(blueprintToText(textToBlueprint('First\n\n\n\nLast'))).toBe('First\n\n\n\nLast');
    const image = { type: 'image', asset_id: 'asset-1', alt: 'Campus' };
    const heading = { type: 'heading', runs: [{ text: 'Section heading' }] };
    const list = { type: 'list', items: [{ runs: [{ text: 'List item' }] }] };
    const table = { type: 'table', rows: [[{ runs: [{ text: 'Cell' }] }]] };
    const existing = {
        blocks: [
            { type: 'paragraph', runs: [{ text: 'Original first' }] },
            image,
            heading,
            list,
            table,
            { type: 'paragraph', runs: [{ text: 'Original last' }] },
        ],
    };

    const updated = textToBlueprint(source, existing);

    expect(blueprintToText(updated)).toBe(source);
    expect(updated.blocks.map((block) => block.type)).toEqual([
        'paragraph',
        'image',
        'heading',
        'list',
        'table',
        'paragraph',
    ]);
    expect(updated.blocks[1]).toEqual(image);
    expect(updated.blocks[3]).toEqual(list);
    expect(updated.blocks[4]).toEqual(table);
});
