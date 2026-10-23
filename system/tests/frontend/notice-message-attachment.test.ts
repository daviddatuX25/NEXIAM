// @vitest-environment jsdom
if (typeof Element !== 'undefined' && !Element.prototype.animate) {
    Element.prototype.animate = () => ({ onfinish: null, cancel: () => {}, finish: () => {}, addEventListener: () => {}, removeEventListener: () => {} } as unknown as Animation);
}
import { cleanup, fireEvent, render, waitFor } from '@testing-library/svelte';
import { afterEach, expect, it, vi } from 'vitest';
import Message from '../../resources/js/Pages/Setup/Notifications/Templates/Message.svelte';
import { createNoticeMessageComposer, type MessageComposerProps, type MessageState } from '../../resources/js/lib/noticeMessageComposer';

const messageState: MessageState = {
    subject: 'Permit ready',
    body: 'Your permit is ready.',
    action_label: 'View permit',
    action_route: '/portal/permit',
    document_revision_id: 2,
    email_blueprint: { schema_version: 'notice-email-1', layout: 'flow', blocks: [{ type: 'paragraph', runs: [{ text: 'Your permit is ready.' }] }] },
};
const readyDocuments = [
    { id: 2, version: 1, definition_sha256: 'definition-2', render_sha256: 'render-2', pdf_url: '/documents/2.pdf' },
    { id: 3, version: 2, definition_sha256: 'definition-3', render_sha256: 'render-3', pdf_url: '/documents/3.pdf' },
];
function props(pdfEligible = true): MessageComposerProps {
    return {
        family: { id: 4, domain: 'Examination', slug: pdfEligible ? 'session-published' : 'reminder', name: pdfEligible ? 'Examination permit' : 'Examination reminder', active_version: 7, family_revision: 8, supports_pdf: pdfEligible },
        state: { ...messageState, document_revision_id: pdfEligible ? 2 : null },
        draft: { id: 8, lock_version: 3, state: { ...messageState, document_revision_id: pdfEligible ? 2 : null }, updated_at: 'Today' },
        base_version: 7,
        content_sha256: 'content-7',
        output_digests: { email_sha256: 'email-7', portal_sha256: 'portal-7', binding_sha256: 'binding-2' },
        tokens: [], assets: [], sample_context: {},
        endpoints: {
            load: '/load',
            create: '/drafts',
            update: '/drafts/8',
            discard: '/drafts/8',
            preview: '/preview',
            publish: '/publish',
            test_mail: '/test-mail',
            image_upload: '/images',
            attachment_check: pdfEligible ? '/attachment-check' : undefined,
            document_edit: pdfEligible ? '/document/edit' : undefined,
            return_url: '/family',
        },
        conversion_review: null,
        document_versions: pdfEligible ? readyDocuments : undefined,
    };
}
function fetcherFor(apiProps: MessageComposerProps) {
    return vi.fn(async (url: RequestInfo | URL, init?: RequestInit) => {
        const target = String(url);
        if (target === '/drafts/8') {
            const body = JSON.parse(String(init?.body ?? '{}'));
            const selected = body.state.document_revision_id;
            return new Response(JSON.stringify({ operation_id: 'save-op', family_revision: 9, active_version: 7, content_sha256: 'content-8', output_digests: { email_sha256: apiProps.output_digests.email_sha256, portal_sha256: apiProps.output_digests.portal_sha256, binding_sha256: `binding-${selected ?? 'none'}` }, draft: { id: 8, lock_version: 4, state: body.state, updated_at: 'Now' } }), { status: 200 });
        }
        if (target === '/preview') {
            const body = JSON.parse(String(init?.body ?? '{}'));
            const view = body.view as 'email' | 'portal';
            const digest = apiProps.output_digests[`${view}_sha256`];
            return new Response(JSON.stringify({
                view, output_sha256: digest, preview_receipt: `${view}-receipt`,
                ...(view === 'email' ? { email: { production_html: '<p>Permit</p>', preview_html: '<p>Permit</p>', text: 'Permit', assets: [], projection_sha256: 'email-projection' } } : { portal: { title: 'Permit ready', body: 'Your permit is ready.' } }),
            }), { status: 200 });
        }
        if (target === '/attachment-check') {
            const body = JSON.parse(String(init?.body ?? '{}'));
            const selected = body.document_revision_id;
            return new Response(JSON.stringify({ binding_sha256: body.binding_sha256, attachment_receipt: `attachment-${selected ?? 'none'}`, document: selected === null ? null : { id: selected, version: selected === 2 ? 1 : 2, definition_sha256: `definition-${selected}` } }), { status: 200 });
        }
        if (target === '/publish') return new Response(JSON.stringify({ operation_id: 'publish-op', active_version: 8, family_revision: 10, content_sha256: 'content-published' }), { status: 200 });
        throw new Error(`Unexpected request: ${target}`);
    });
}
function deferredResponse() {
    let resolve = (_response: Response) => {};
    const promise = new Promise<Response>((resolveResponse) => { resolve = resolveResponse; });
    return { promise, resolve };
}
function previewResponse(view: 'email' | 'portal', digest: string, receipt: string): Response {
    return new Response(JSON.stringify({
        view, output_sha256: digest, preview_receipt: receipt,
        ...(view === 'email'
            ? { email: { production_html: '<p>Permit</p>', preview_html: '<p>Permit</p>', text: 'Permit', assets: [], projection_sha256: `projection-${receipt}` } }
            : { portal: { title: 'Permit ready', body: 'Your permit is ready.' } }),
    }), { status: 200 });
}

afterEach(() => { cleanup(); vi.unstubAllGlobals(); });

it('renders attachment selection only for PDF-eligible message families', () => {
    const eligible = render(Message, { props: props(true) });
    expect(eligible.getByRole('heading', { name: 'Attachment' })).toBeTruthy();
    expect(eligible.getByRole('combobox', { name: 'Attachment document version' })).toBeTruthy();
    expect(eligible.getByText('Attachment: Examination permit v1')).toBeTruthy();
    eligible.unmount();
    const reminder = render(Message, { props: props(false) });
    expect(reminder.queryByRole('heading', { name: 'Attachment' })).toBeNull();
    expect(reminder.queryByRole('combobox', { name: 'Attachment document version' })).toBeNull();
});
it('renders historical message versions as read-only with every authoring and attachment action disabled', () => {
    const authored = props(true);
    const state: MessageState = { ...authored.state, email_blueprint: { ...authored.state.email_blueprint!, blocks: [
        ...authored.state.email_blueprint!.blocks,
        { type: 'image', asset_id: 'history-image', alt: 'History seal' },
    ] } };
    const historyProps = { ...authored, state, read_only: true, draft: null, endpoints: { ...authored.endpoints, test_mail: '/test-mail' }, assets: [
        { asset_id: 'history-image', sha256: 'history-sha', mime: 'image/png', width: 100, height: 80, preview_url: '/history-image' },
    ] };
    const view = render(Message, { props: historyProps });

    expect(view.getByText('Historical message version — read-only inspection.')).toBeTruthy();
    for (const label of [
        'Email subject and Portal title',
        'Shared action label',
        'Shared action destination',
        'Image alt text',
        'Attachment document version',
    ]) {
        expect((view.getByLabelText(label) as HTMLInputElement).disabled).toBe(true);
    }
    expect((view.getByLabelText(/Portal caption/) as HTMLTextAreaElement).disabled).toBe(true);
    for (const name of ['Preview current attachment', 'Edit document',
        'Send synthetic test email to verified staff address']) {
        expect((view.getByRole('button', { name }) as HTMLButtonElement).disabled).toBe(true);
    }
    expect(view.queryByRole('button', { name: 'Review & publish' })).toBeNull();
});

it('checks a selected ready document and invalidates only attachment proof when selection changes', async () => {
    const apiProps = props(true);
    const fetch = fetcherFor(apiProps);
    const composer = createNoticeMessageComposer(apiProps, { fetch: fetch as typeof globalThis.fetch, key: () => 'operation-key' });
    await composer.preview('email');
    await composer.preview('portal');
    const firstCheck = await composer.checkAttachment();
    expect(firstCheck?.document?.id).toBe(2);
    expect(composer.snapshot().attachment_receipt).toBe('attachment-2');

    composer.edit({ ...composer.snapshot().state, document_revision_id: 3 });
    expect(composer.snapshot().attachment_receipt).toBeNull();
    expect(composer.snapshot().previews.email?.preview_receipt).toBe('email-receipt');
    expect(composer.snapshot().previews.portal?.preview_receipt).toBe('portal-receipt');
    const secondCheck = await composer.checkAttachment();
    expect(secondCheck?.document?.id).toBe(3);
    expect(composer.snapshot().attachment_receipt).toBe('attachment-3');
    expect(JSON.parse(String(fetch.mock.calls.filter(([url]) => String(url) === '/attachment-check').at(-1)?.[1]?.body)).document_revision_id).toBe(3);
    composer.dispose();
});
it('selects a ready version in the composer, saves it, and checks the attachment', async () => {
    const apiProps = props(true);
    const fetch = fetcherFor(apiProps);
    vi.stubGlobal('fetch', fetch);
    const view = render(Message, { props: apiProps });
    await waitFor(() => expect(view.container.textContent).toContain('Draft saved'));
    const selector = view.getByRole('combobox', { name: 'Attachment document version' });
    await fireEvent.change(selector, { target: { value: '3' } });
    expect(selector).toHaveProperty('value', '3');
    await fireEvent.click(view.getByRole('button', { name: 'Attach this exact version' }));
    await waitFor(() => expect(view.container.textContent).toContain('Draft saved.'));
    await fireEvent.click(view.getByRole('button', { name: 'Check preview' }));
    await waitFor(() => expect(view.getByText(/Exact attachment selection proof is current|checks are current/)).toBeTruthy());
    const attachmentRequest = fetch.mock.calls.find(([url]) => String(url) === '/attachment-check');
    expect(JSON.parse(String(attachmentRequest?.[1]?.body)).document_revision_id).toBe(3);
});

it('editing the Email body invalidates Email proof without invalidating attachment proof', async () => {
    const apiProps = props(true);
    const composer = createNoticeMessageComposer(apiProps, { fetch: fetcherFor(apiProps) as typeof globalThis.fetch });
    await composer.preview('email');
    await composer.preview('portal');
    await composer.checkAttachment();
    composer.edit({ ...composer.snapshot().state, email_blueprint: { ...messageState.email_blueprint!, blocks: [{ type: 'paragraph', runs: [{ text: 'Updated body' }] }] } });
    expect(composer.snapshot().previews.email).toBeUndefined();
    expect(composer.snapshot().previews.portal?.preview_receipt).toBe('portal-receipt');
    expect(composer.snapshot().attachment_receipt).toBe('attachment-2');
    composer.dispose();
});

it('publishes the selected document version with Email, Portal, and attachment receipts', async () => {
    const apiProps = props(true);
    const fetch = fetcherFor(apiProps);
    const composer = createNoticeMessageComposer(apiProps, { fetch: fetch as typeof globalThis.fetch, key: () => 'publish-key' });
    await composer.preview('email');
    await composer.preview('portal');
    await composer.checkAttachment();
    await composer.publish();
    const publishRequest = fetch.mock.calls.find(([url]) => String(url) === '/publish');
    const payload = JSON.parse(String(publishRequest?.[1]?.body));
    expect(payload.document_revision_id).toBe(2);
    expect(payload.preview_receipts).toEqual({ email: 'email-receipt', portal: 'portal-receipt', attachment: 'attachment-2' });
    composer.dispose();
});
it('checks and publishes the explicit none binding for an eligible family', async () => {
    const eligible = props(true);
    const noneState = { ...eligible.state, document_revision_id: null };
    const noneProps: MessageComposerProps = {
        ...eligible,
        state: noneState,
        draft: { ...eligible.draft!, state: noneState },
        output_digests: { ...eligible.output_digests, binding_sha256: 'binding-none' },
    };
    const fetch = fetcherFor(noneProps);
    const composer = createNoticeMessageComposer(noneProps, { fetch: fetch as typeof globalThis.fetch, key: () => 'none-binding-key' });

    const checked = await composer.checkAll();
    expect(checked.attachment?.document).toBeNull();
    expect(composer.snapshot().attachment_receipt).toBe('attachment-none');
    const checkRequest = fetch.mock.calls.find(([url]) => String(url) === '/attachment-check');
    expect(JSON.parse(String(checkRequest?.[1]?.body)).document_revision_id).toBeNull();

    await composer.publish();
    const publishRequest = fetch.mock.calls.find(([url]) => String(url) === '/publish');
    const publishPayload = JSON.parse(String(publishRequest?.[1]?.body));
    expect(publishPayload.document_revision_id).toBeNull();
    expect(publishPayload.preview_receipts.attachment).toBe('attachment-none');
    composer.dispose();
});

it('ignores delayed Email, Portal, and attachment results from an older Check interaction', async () => {
    const oldEmail = deferredResponse();
    const oldPortal = deferredResponse();
    const oldAttachment = deferredResponse();
    const counts = { email: 0, portal: 0, attachment: 0 };
    const fetch = vi.fn(async (url: RequestInfo | URL, init?: RequestInit) => {
        const target = String(url);
        if (target === '/drafts/8') {
            const body = JSON.parse(String(init?.body ?? '{}'));
            const selected = body.state.document_revision_id;
            return new Response(JSON.stringify({
                operation_id: 'latest-save', family_revision: 9, active_version: 7, content_sha256: 'content-latest',
                output_digests: { email_sha256: 'email-7', portal_sha256: 'portal-7', binding_sha256: `binding-${selected ?? 'none'}` },
                draft: { id: 8, lock_version: 4, state: body.state, updated_at: 'Now' },
            }), { status: 200 });
        }
        if (target === '/preview') {
            const body = JSON.parse(String(init?.body ?? '{}'));
            const view = body.view as 'email' | 'portal';
            counts[view]++;
            if (counts[view] === 1) return view === 'email' ? oldEmail.promise : oldPortal.promise;
            return previewResponse(view, body.output_sha256, `current-${view}`);
        }
        if (target === '/attachment-check') {
            const body = JSON.parse(String(init?.body ?? '{}'));
            counts.attachment++;
            if (counts.attachment === 1) return oldAttachment.promise;
            return new Response(JSON.stringify({
                binding_sha256: body.binding_sha256,
                attachment_receipt: 'attachment-3',
                document: { id: 3, version: 2, definition_sha256: 'definition-3' },
            }), { status: 200 });
        }
        throw new Error(`Unexpected request: ${target}`);
    });
    const composer = createNoticeMessageComposer(props(true), { fetch: fetch as typeof globalThis.fetch });

    const staleCheck = composer.checkAll();
    await waitFor(() => {
        expect(counts.email).toBe(1);
        expect(counts.portal).toBe(1);
        expect(counts.attachment).toBe(1);
    });
    composer.edit({ ...composer.snapshot().state, document_revision_id: 3 });
    const currentCheck = await composer.checkAll();
    expect(currentCheck.email?.preview_receipt).toBe('current-email');
    expect(currentCheck.portal?.preview_receipt).toBe('current-portal');
    expect(currentCheck.attachment?.document?.id).toBe(3);

    oldEmail.resolve(previewResponse('email', 'email-7', 'old-email'));
    oldPortal.resolve(previewResponse('portal', 'portal-7', 'old-portal'));
    oldAttachment.resolve(new Response(JSON.stringify({
        binding_sha256: 'binding-2',
        attachment_receipt: 'attachment-2',
        document: { id: 2, version: 1, definition_sha256: 'definition-2' },
    }), { status: 200 }));

    await expect(staleCheck).resolves.toEqual({ email: null, portal: null, attachment: null });
    expect(composer.snapshot().previews.email?.preview_receipt).toBe('current-email');
    expect(composer.snapshot().previews.portal?.preview_receipt).toBe('current-portal');
    expect(composer.snapshot().attachment_check?.document?.id).toBe(3);
    expect(composer.snapshot().attachment_receipt).toBe('attachment-3');
    composer.dispose();
});

it('coalesces document handoff saves until the latest typing epoch is acknowledged', async () => {
    const firstSave = deferredResponse();
    const latestSave = deferredResponse();
    const payloads: Record<string, unknown>[] = [];
    const fetch = vi.fn(async (_url: RequestInfo | URL, init?: RequestInit) => {
        const body = JSON.parse(String(init?.body ?? '{}')) as Record<string, unknown>;
        payloads.push(body);
        return payloads.length === 1 ? firstSave.promise : latestSave.promise;
    });
    let key = 0;
    const composer = createNoticeMessageComposer(props(true), {
        fetch: fetch as typeof globalThis.fetch,
        key: () => `handoff-${++key}`,
    });
    composer.edit({ ...composer.snapshot().state, body: 'First typing' });
    const handoffPromise = composer.saveForDocumentHandoff();
    await waitFor(() => expect(payloads).toHaveLength(1));
    composer.edit({ ...composer.snapshot().state, body: 'Latest typing' });
    const coalescedSave = composer.save();

    const saveReceipt = (body: Record<string, unknown>, revision: number) => new Response(JSON.stringify({
        operation_id: `save-${revision}`,
        family_revision: revision,
        active_version: 7,
        content_sha256: `content-${revision}`,
        output_digests: { email_sha256: 'email-7', portal_sha256: 'portal-7', binding_sha256: 'binding-2' },
        draft: { id: 8, lock_version: revision - 5, state: body.state, updated_at: 'Now' },
    }), { status: 200 });
    firstSave.resolve(saveReceipt(payloads[0]!, 9));
    await waitFor(() => expect(payloads).toHaveLength(2));
    latestSave.resolve(saveReceipt(payloads[1]!, 10));

    const handoff = await handoffPromise;
    await coalescedSave;
    expect(payloads.map((payload) => (payload.state as MessageState).body)).toEqual(['First typing', 'Latest typing']);
    expect(payloads.map((payload) => payload.idempotency_key)).toEqual(['handoff-1', 'handoff-2']);
    expect(handoff.guards).toEqual({ base_version: 7, family_revision: 10, lock_version: 5 });
    expect(composer.isSaveHandoffCurrent(handoff)).toBe(true);
    composer.edit({ ...composer.snapshot().state, body: 'Typed after acknowledgement' });
    expect(composer.isSaveHandoffCurrent(handoff)).toBe(false);
    composer.dispose();
});
it('retries an unknown handoff save with the same mutation identity before saving newer typing', async () => {
    const payloads: Record<string, unknown>[] = [];
    const urls: string[] = [];
    let attempt = 0;
    const fetch = vi.fn(async (url: RequestInfo | URL, init?: RequestInit) => {
        const payload = JSON.parse(String(init?.body ?? '{}')) as Record<string, unknown>;
        payloads.push(payload);
        urls.push(String(url));
        attempt++;
        if (attempt === 1) throw new TypeError('connection lost');
        return new Response(JSON.stringify({
            operation_id: `save-${attempt}`,
            family_revision: attempt + 7,
            active_version: 7,
            content_sha256: `content-${attempt}`,
            output_digests: { email_sha256: 'email-7', portal_sha256: 'portal-7', binding_sha256: 'binding-2' },
            draft: { id: 8, lock_version: attempt + 2, state: payload.state, updated_at: 'Now' },
        }), { status: 200 });
    });
    let key = 0;
    const copyProps: MessageComposerProps = { ...props(true), draft: null, copy_source_version: 4 };
    const composer = createNoticeMessageComposer(copyProps, {
        fetch: fetch as typeof globalThis.fetch,
        key: () => `handoff-retry-${++key}`,
    });
    composer.edit({ ...composer.snapshot().state, body: 'Submitted typing' });
    await expect(composer.save()).rejects.toThrow('connection lost');
    expect(composer.snapshot().unknown_operation).toBe('draft');
    composer.edit({ ...composer.snapshot().state, body: 'Latest typing' });

    const handoff = await composer.saveForDocumentHandoff();
    expect(payloads).toHaveLength(3);
    expect(payloads[0]).toEqual(payloads[1]);
    expect(urls).toEqual(['/drafts', '/drafts', '/drafts/8']);
    expect(payloads.map((payload) => payload.idempotency_key)).toEqual([
        'handoff-retry-1', 'handoff-retry-1', 'handoff-retry-2',
    ]);
    expect(payloads.map((payload) => payload.source_version)).toEqual([4, 4, undefined]);
    expect(payloads.map((payload) => (payload.state as MessageState).body)).toEqual([
        'Submitted typing', 'Submitted typing', 'Latest typing',
    ]);
    expect(handoff.guards).toEqual({ base_version: 7, family_revision: 10, lock_version: 5 });
    expect(composer.isSaveHandoffCurrent(handoff)).toBe(true);
    composer.dispose();
});
it('adopts the persisted renderer pin and invalidates only the digest that changed', async () => {
    const readyReview = {
        source_schema_version: 'notice-content-1',
        source_renderer_version: 'notice-email-1',
        status: 'ready' as const,
        email_subject: 'Permit title',
        email_body: 'Permit body',
        in_app_title: 'Permit title',
        in_app_body: 'Permit body',
        blockers: [],
    };
    const initial: MessageComposerProps = {
        ...props(false),
        content_schema_version: 'notice-content-1',
        email_renderer_version: 'notice-email-1',
        conversion_review: readyReview,
    };
    const fetch = vi.fn(async (url: RequestInfo | URL, init?: RequestInit) => {
        if (String(url) === '/preview') {
            const body = JSON.parse(String(init?.body ?? '{}'));
            const view = body.view as 'email' | 'portal';
            return previewResponse(view, body.output_sha256, `old-${view}`);
        }
        const body = JSON.parse(String(init?.body ?? '{}'));
        return new Response(JSON.stringify({
            operation_id: 'rebased-save',
            family_revision: 9,
            active_version: 7,
            content_sha256: 'content-3',
            content_schema_version: 'notice-content-3',
            email_renderer_version: 'notice-message-email-2',
            output_digests: { email_sha256: 'email-new', portal_sha256: 'portal-7' },
            draft: { id: 8, lock_version: 4, state: body.state, updated_at: 'Now' },
        }), { status: 200 });
    });
    const composer = createNoticeMessageComposer(initial, { fetch: fetch as typeof globalThis.fetch });
    await composer.preview('email');
    await composer.preview('portal');
    await composer.save();

    expect(composer.snapshot().content_schema_version).toBe('notice-content-3');
    expect(composer.snapshot().email_renderer_version).toBe('notice-message-email-2');
    expect(composer.snapshot().conversion_review).toBeNull();
    expect(composer.snapshot().previews.email).toBeUndefined();
    expect(composer.snapshot().previews.portal?.preview_receipt).toBe('old-portal');
    composer.dispose();
});

it('preserves prior conversion review and independent proofs when a save receipt omits version and digest echoes', async () => {
    const readyReview = {
        source_schema_version: 'notice-content-1',
        source_renderer_version: 'notice-email-1',
        status: 'ready' as const,
        email_subject: 'Permit title',
        email_body: 'Permit body',
        in_app_title: 'Permit title',
        in_app_body: 'Permit body',
        blockers: [],
    };
    const initial: MessageComposerProps = {
        ...props(false),
        content_schema_version: 'notice-content-1',
        email_renderer_version: 'notice-email-1',
        conversion_review: readyReview,
    };
    const fetch = vi.fn(async (url: RequestInfo | URL, init?: RequestInit) => {
        if (String(url) === '/preview') {
            const body = JSON.parse(String(init?.body ?? '{}'));
            const view = body.view as 'email' | 'portal';
            return previewResponse(view, body.output_sha256, `current-${view}`);
        }
        const body = JSON.parse(String(init?.body ?? '{}'));
        return new Response(JSON.stringify({
            operation_id: 'minimal-save',
            family_revision: 9,
            active_version: 7,
            content_sha256: 'content-7',
            draft: { id: 8, lock_version: 4, state: body.state, updated_at: 'Now' },
        }), { status: 200 });
    });
    const composer = createNoticeMessageComposer(initial, { fetch: fetch as typeof globalThis.fetch });
    await composer.preview('email');
    await composer.preview('portal');
    await composer.save();

    expect(composer.snapshot().content_schema_version).toBe('notice-content-1');
    expect(composer.snapshot().email_renderer_version).toBe('notice-email-1');
    expect(composer.snapshot().conversion_review).toEqual(readyReview);
    expect(composer.snapshot().previews.email?.preview_receipt).toBe('current-email');
    expect(composer.snapshot().previews.portal?.preview_receipt).toBe('current-portal');
    composer.dispose();
});


it('does not save, check, publish, or hand off a blocked historical projection', async () => {
    const blockedProps: MessageComposerProps = {
        ...props(true),
        content_schema_version: 'notice-content-1',
        email_renderer_version: 'notice-email-1',
        conversion_review: {
            source_schema_version: 'notice-content-1',
            source_renderer_version: 'notice-email-1',
            status: 'blocked',
            email_subject: 'Permit title',
            email_body: 'Permit body',
            in_app_title: 'Different title',
            in_app_body: 'Permit body',
            blockers: [{ code: 'channel_title_mismatch', path: 'in_app_title', message: 'The historical titles differ.' }],
        },
    };
    const fetch = vi.fn();
    const composer = createNoticeMessageComposer(blockedProps, { fetch: fetch as typeof globalThis.fetch });
    composer.edit({ ...composer.snapshot().state, body: 'Retained local correction draft' });

    await expect(composer.save()).rejects.toThrow(/historical message needs/i);
    await expect(composer.checkAll()).rejects.toThrow(/historical message needs/i);
    await expect(composer.saveForDocumentHandoff()).rejects.toThrow(/historical message needs/i);
    await expect(composer.publish()).rejects.toThrow(/historical message needs/i);
    expect(fetch).not.toHaveBeenCalled();
    expect(composer.snapshot().state.body).toBe('Retained local correction draft');
    expect(composer.snapshot().conversion_review?.status).toBe('blocked');
    expect(composer.snapshot().email_renderer_version).toBe('notice-email-1');
    expect(composer.snapshot().previews).toEqual({});
    composer.dispose();
});
