// @vitest-environment jsdom
import { afterEach, expect, it, vi } from 'vitest';
import { flushSync, mount, tick, unmount } from 'svelte';
import ApplicationRoot from '../../resources/js/Components/ApplicationRoot.svelte';
import ThinkingCard from '../../resources/js/Components/kit/Xiam/XiamThinkingCard.svelte';
import manifest from '../../public/assets/xiam/emotions/v2/manifest.json';

import { setPage } from '../../node_modules/@inertiajs/svelte/dist/page.svelte.js';
function visit(url: string, props: Record<string, unknown>) {
    setPage({ component: 'Fixture', url, props, version: null, clearHistory: false, encryptHistory: false, rememberedState: {}, flash: {} });
}
const fetchSnapshot = vi.hoisted(() => vi.fn());
vi.mock('../../resources/js/lib/xiam-transport', () => ({ fetchXiamSnapshot: fetchSnapshot, xiamCsrfHeaders: () => ({}), cancelXiamProposal: vi.fn(), clearXiamConversation: vi.fn(), confirmXiamProposal: vi.fn(), startXiamSkill: vi.fn(), streamXiamConversation: vi.fn(), submitXiamTurn: vi.fn() }));
vi.mock('../../resources/js/lib/sound.js', () => ({ sound: { pop: vi.fn(), tick: vi.fn() } }));
let component: Record<string, unknown>;
afterEach(async () => { if (component) await unmount(component); document.body.innerHTML = ''; vi.unstubAllGlobals(); });
it('keeps hydrated cards and expanded details when the page changes', async () => {
    vi.stubGlobal('fetch', vi.fn().mockResolvedValue({ ok: true, json: async () => manifest }));
    vi.stubGlobal('requestAnimationFrame', (callback: FrameRequestCallback) => setTimeout(() => callback(0), 0));
    window.matchMedia = vi.fn().mockReturnValue({ matches: true, addEventListener: vi.fn(), removeEventListener: vi.fn() });
    sessionStorage.setItem('nexiam.xiam.expanded', '1');
    const session = { contract_version: '2', availability: 'assistant_available', conversation: { id: 'nav-conversation', cursor: 0 }, page_pack: { key: 'staff.exams.sessions.index@1' }, proposals: [] };
    visit('/exams/sessions', { auth: { user: { id: 1 } }, xiam: session });
    fetchSnapshot.mockResolvedValue({ cursor: 2, messages: [{ run_id: 'nav-run', role: 'assistant', content: 'Keep this answer visible' }], events: [{ seq: 1, type: 'tool.result', payload: { ok: true, label: 'Checked availability' } }], proposals: [] });
    component = mount(ApplicationRoot, { target: document.body, props: { App: ThinkingCard, appProps: {} } });
    await tick(); await tick(); await tick();
    const answer = Array.from(document.querySelectorAll('p')).find(node => node.textContent?.includes('Keep this answer visible'));
    expect(answer).toBeDefined();
    const root = document.querySelector('details[data-xiam-assist-root]');
    const launcher = document.querySelector('summary');
    expect(launcher?.textContent?.trim()).toBe('');
    expect(launcher?.getAttribute('aria-label')).toBe('Open Xiam workspace');
    visit('/consultation/sessions', { auth: { user: { id: 1 } }, xiam: { ...session, page_pack: { key: 'staff.consultation.sessions.index@1' } } });
    flushSync(); await tick();
    expect(document.querySelector('details[data-xiam-assist-root]')).toBe(root);
    expect(answer?.isConnected).toBe(true);
    expect(fetchSnapshot).toHaveBeenCalledTimes(1);
    visit('/login', { auth: { user: null }, xiam: null });
    flushSync(); await tick();
    expect(answer?.isConnected).toBe(false);
    expect(document.querySelector('summary')).toBeNull();
});
