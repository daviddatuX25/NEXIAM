// @vitest-environment jsdom
import { afterEach, expect, it } from 'vitest';
import { flushSync, mount, tick, unmount } from 'svelte';
import { setPage } from '../../node_modules/@inertiajs/svelte/dist/page.svelte.js';
import ApplicationRoot from '../../resources/js/Components/ApplicationRoot.svelte';
import DemoGuide from '../../resources/js/Pages/Foundation/DemoGuide.svelte';

let component: Record<string, unknown>;
function visit(url: string, workspace: Record<string, unknown> | null) {
    setPage({ component: 'Foundation/DemoGuide', url, props: { auth: { user: { id: 1 } }, xiam: null, practice_workspace: workspace }, version: null, clearHistory: false, encryptHistory: false, rememberedState: {}, flash: {} });
}
afterEach(async () => { if (component) await unmount(component); document.body.innerHTML = ''; });

it('follows the server workspace and removes practice context on neutral navigation without replacing the application', async () => {
    visit('/applications?practice_set=set-1', { id: 'set-1', label: 'Mara practice', purpose: 'research', leave_url: '/demo?practice_set=leave' });
    component = mount(ApplicationRoot, { target: document.body, props: { App: DemoGuide, appProps: {} } });
    await tick();
    const heading = document.querySelector('h1');
    const banner = document.querySelector('aside[aria-label="Practice workspace context"]');
    expect(banner?.textContent).toContain('Mara practice');
    expect(banner?.textContent).toContain('research');
    visit('/demo/research', null);
    flushSync(); await tick();
    expect(document.querySelector('aside[aria-label="Practice workspace context"]')).toBeNull();
    expect(document.querySelector('h1')).toBe(heading);
    expect(document.querySelector('details[data-xiam-assist-root]')).toBeNull();
});

it('does not offer a false workspace escape for a practice applicant account', async () => {
    visit('/applicant/portal', { id: 'set-1', label: 'Student practice', purpose: 'research', leave_url: null, leave_note: 'Use normal portal sign out to leave this account.' });
    component = mount(ApplicationRoot, { target: document.body, props: { App: DemoGuide, appProps: {} } });
    await tick();
    const banner = document.querySelector('aside[aria-label="Practice workspace context"]');
    expect(banner?.textContent).toContain('Use normal portal sign out to leave this account.');
    expect(banner?.querySelector('a')).toBeNull();
});
