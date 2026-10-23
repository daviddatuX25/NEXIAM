// @vitest-environment jsdom
import { afterEach, beforeEach, expect, it, vi } from 'vitest';
import { createNoticeStudioHistoryGuard, initializeNoticeStudioHistoryGuard } from '../../resources/js/lib/noticeStudioHistoryGuard';
import type { NoticeHistoryGuard } from '../../resources/js/lib/noticeStudioHistoryGuard';
import { createNoticeMessageComposer, type MessageComposerProps, type MessageState } from '../../resources/js/lib/noticeMessageComposer';
const guards: NoticeHistoryGuard[] = [];
const composerState: MessageState = {
    subject: 'Notice subject',
    body: 'Portal caption',
    action_label: null,
    action_route: null,
    email_blueprint: { schema_version: 'notice-email-1', layout: 'flow', blocks: [{ type: 'paragraph', text: 'Notice body' }] },
    document_revision_id: null,
};
const composerProps: MessageComposerProps = {
    family: { id: 1, domain: 'Examination', slug: 'reminder', name: 'Exam reminder', active_version: 1, family_revision: 2 },
    state: composerState,
    draft: { id: 4, lock_version: 3, state: composerState, updated_at: 'Now' },
    base_version: 1,
    content_sha256: 'content-1',
    output_digests: { email_sha256: 'email-1', portal_sha256: 'portal-1' },
    tokens: [],
    assets: [],
    sample_context: {},
    endpoints: { load: '/load', create: '/drafts', update: '/drafts/4', discard: '/drafts/4', preview: '/preview', publish: '/publish', return_url: '/family' },
    content_schema_version: 'notice-content-3',
    email_renderer_version: 'notice-message-email-2',
    conversion_review: null,
};
const listeners: ((event: PopStateEvent) => void)[] = [];
beforeEach(() => initializeNoticeStudioHistoryGuard(window));
afterEach(() => {
    for (const guard of guards.splice(0)) guard.dispose();
    for (const listener of listeners.splice(0)) window.removeEventListener('popstate', listener);
    window.history.replaceState({}, '', '/'); vi.restoreAllMocks();
});

it.each([{ destination: 1, restore: 1, leave: -1, name: 'Back' }, { destination: 3, restore: -1, leave: 1, name: 'Forward' }])('retains the current editor before $name reaches Inertia and leaves only after confirmation', ({ destination, restore, leave }) => {
    window.history.replaceState({ editor: 'current' }, '', '/notice/edit');
    let index = 2;
    const decisions: Array<() => void> = [];
    const restoring: boolean[] = [];
    const go = vi.spyOn(window.history, 'go').mockImplementation(() => {});
    let downstreamNavigations = 0;
    const inertiaListener = () => { downstreamNavigations++; };
    listeners.push(inertiaListener); window.addEventListener('popstate', inertiaListener);
    const guard = createNoticeStudioHistoryGuard({ hasUnsavedWork: () => true, entryIndex: () => index, onRestoring: (value) => restoring.push(value), onNavigation: (callback) => decisions.push(callback) });
    guards.push(guard);
    index = destination;
    window.history.replaceState({ target: 'family' }, '', '/notice/family');
    window.dispatchEvent(new PopStateEvent('popstate', { state: { target: 'family' } }));
    expect(go).toHaveBeenCalledWith(restore); expect(downstreamNavigations).toBe(0); expect(decisions).toHaveLength(0);
    index = 2;
    window.history.replaceState({ editor: 'current' }, '', '/notice/edit');
    window.dispatchEvent(new PopStateEvent('popstate', { state: { editor: 'current' } }));
    expect(downstreamNavigations).toBe(0); expect(restoring).toEqual([true, false]); expect(decisions).toHaveLength(1);
    // Staying requires no history mutation and leaves the same editor and its timer alive.
    expect(go).toHaveBeenCalledTimes(1); expect(window.location.pathname).toBe('/notice/edit');
    const confirmed = decisions[0];
    if (!confirmed) throw new Error('No navigation decision offered');
    confirmed();
    expect(go).toHaveBeenLastCalledWith(leave); expect(go).toHaveBeenCalledTimes(2);
});
it('clean history traversals are not intercepted', () => {
    const go = vi.spyOn(window.history, 'go').mockImplementation(() => {});
    const prompt = vi.fn();
    const guard = createNoticeStudioHistoryGuard({ hasUnsavedWork: () => false, entryIndex: () => 2, onNavigation: prompt });
    guards.push(guard);
    const downstream = vi.fn(); listeners.push(downstream); window.addEventListener('popstate', downstream);
    window.dispatchEvent(new PopStateEvent('popstate', { state: { clean: true } }));
    expect(downstream).toHaveBeenCalledTimes(1); expect(prompt).not.toHaveBeenCalled(); expect(go).not.toHaveBeenCalled();
});
it('without Navigation API indexes, cancellation still restores the current workspace instead of disposing unsaved input', () => {
    window.history.replaceState({ editor: 'keep' }, '', '/notice/edit');
    const push = vi.spyOn(window.history, 'pushState');
    let continueNavigation: (() => void) | null = null;
    const guard = createNoticeStudioHistoryGuard({ hasUnsavedWork: () => true, entryIndex: () => null, onNavigation: (callback) => { continueNavigation = callback; } });
    guards.push(guard);
    const downstream = vi.fn(); listeners.push(downstream); window.addEventListener('popstate', downstream);
    window.history.replaceState({ target: 'list' }, '', '/notice/list');
    window.dispatchEvent(new PopStateEvent('popstate', { state: { target: 'list' } }));
    expect(push).toHaveBeenCalledWith({ editor: 'keep' }, '', expect.stringContaining('/notice/edit'));
    expect(window.location.pathname).toBe('/notice/edit'); expect(downstream).not.toHaveBeenCalled(); expect(continueNavigation).not.toBeNull();
    // Cancel: leave callback is deliberately not invoked; the workspace remains current.
    expect(window.history.state).toEqual({ editor: 'keep' });
});
it('preserves the 409 conflict after editing and keeps Back guarded until reconciliation', async () => {
    const fetch = vi.fn(async () => new Response(JSON.stringify({ message: 'Draft revision changed.' }), { status: 409 }));
    const composer = createNoticeMessageComposer(composerProps, { fetch: fetch as typeof globalThis.fetch, key: () => 'conflict-key' });
    composer.edit({ ...composerState, body: 'Local text after the conflict.' });
    await expect(composer.save()).rejects.toThrow('Draft revision changed.');
    const conflictMessage = composer.snapshot().error;

    composer.edit({ ...composer.snapshot().state, body: 'New local text, still conflicted.' });
    expect(composer.snapshot().status).toBe('conflict');
    expect(composer.snapshot().error).toBe(conflictMessage);
    expect(composer.hasUnsavedWork()).toBe(true);
    await expect(composer.save()).rejects.toThrow('Draft revision changed.');
    expect(fetch).toHaveBeenCalledTimes(1);

    window.history.replaceState({ editor: 'current' }, '', '/notice/edit');
    let index = 2;
    let offered: (() => void) | null = null;
    const go = vi.spyOn(window.history, 'go').mockImplementation(() => {});
    let downstreamNavigations = 0;
    const inertiaListener = () => { downstreamNavigations++; };
    listeners.push(inertiaListener); window.addEventListener('popstate', inertiaListener);
    const guard = createNoticeStudioHistoryGuard({
        hasUnsavedWork: () => composer.hasUnsavedWork(),
        entryIndex: () => index,
        onNavigation: (continueNavigation) => { offered = continueNavigation; },
    });
    guards.push(guard);
    index = 1;
    window.history.replaceState({ target: 'family' }, '', '/notice/family');
    window.dispatchEvent(new PopStateEvent('popstate', { state: { target: 'family' } }));
    expect(go).toHaveBeenCalledWith(1);
    expect(downstreamNavigations).toBe(0);
    index = 2;
    window.history.replaceState({ editor: 'current' }, '', '/notice/edit');
    window.dispatchEvent(new PopStateEvent('popstate', { state: { editor: 'current' } }));
    expect(offered).not.toBeNull();
    expect(downstreamNavigations).toBe(0);
    composer.dispose();
});

it('reconciles current guards without replacing local typing and requires confirmed server reload', async () => {
    const serverState: MessageState = { ...composerState, body: 'Current server caption.' };
    const fetch = vi.fn(async (_url: RequestInfo | URL) => {
        return new Response(JSON.stringify({
            family: { family_revision: 12 },
            state: serverState,
            draft: { id: 4, lock_version: 9, state: serverState, updated_at: 'Later' },
            base_version: 3,
            content_sha256: 'content-current',
            content_schema_version: 'notice-content-3',
            email_renderer_version: 'notice-message-email-2',
            output_digests: { email_sha256: 'email-current', portal_sha256: 'portal-current' },
            conversion_review: null,
        }), { status: 200 });
    });
    const composer = createNoticeMessageComposer(composerProps, { fetch: fetch as typeof globalThis.fetch });
    composer.edit({ ...composerState, body: 'Unsaved local caption.' });
    await composer.reconcilePreservingLocal();
    expect(composer.snapshot().state.body).toBe('Unsaved local caption.');
    expect(composer.snapshot().guards).toEqual({ base_version: 3, family_revision: 12, lock_version: 9 });
    expect(composer.snapshot().content_sha256).toBe('content-current');
    expect(composer.snapshot().content_schema_version).toBe('notice-content-3');
    expect(composer.snapshot().email_renderer_version).toBe('notice-message-email-2');
    expect(composer.snapshot().status).toBe('dirty');
    expect(fetch).toHaveBeenCalledTimes(1);

    await composer.reloadServerState({ confirmed: true });
    expect(composer.snapshot().state.body).toBe('Current server caption.');
    expect(composer.snapshot().status).toBe('saved');
    expect(fetch).toHaveBeenCalledTimes(2);
    composer.dispose();
});
