// @vitest-environment jsdom

import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import { flushSync, mount, tick, unmount, type ComponentProps } from 'svelte';
import { fromStore, writable } from 'svelte/store';
import XiamAvatar, { resetAvatarManifestCache } from '../../resources/js/Components/kit/Xiam/XiamAvatar.svelte';
import manifest from '../../public/assets/xiam/emotions/v2/manifest.json';
import type { XiamAvatarEvent } from '../../resources/js/lib/xiam-avatar-reducer';

describe('Xiam avatar playback', () => {
    let container: HTMLDivElement;
    let components: Record<string, unknown>[];

    beforeEach(() => {
        resetAvatarManifestCache();
        vi.useFakeTimers();
        components = [];
        container = document.createElement('div');
        document.body.appendChild(container);
        vi.stubGlobal('fetch', vi.fn().mockResolvedValue({ ok: true, json: async () => manifest }));
        window.matchMedia = vi.fn().mockReturnValue({
            matches: false,
            addEventListener: vi.fn(),
            removeEventListener: vi.fn(),
        });
    });

    afterEach(async () => {
        for (const component of components) await unmount(component);
        container.remove();
        vi.unstubAllGlobals();
        vi.restoreAllMocks();
        vi.useRealTimers();
    });

    async function render(props: ComponentProps<typeof XiamAvatar>) {
        const component = mount(XiamAvatar, { target: container, props });
        components.push(component);
        await tick();
        await vi.advanceTimersByTimeAsync(0);
        await tick();
        return container.querySelector('img')!;
    }

    it('holds walkthrough poses instead of looping them', async () => {
        const img = await render({ heldEmotion: 'hi' });
        expect(img.src).toContain('hi-1.png');
        await vi.advanceTimersByTimeAsync(180);
        flushSync();
        expect(img.src).toContain('hi-2.png');
        await vi.advanceTimersByTimeAsync(8000);
        expect(img.src).toContain('hi-2.png');
    });

    it('replays the entrance on an actual playback-key change without remounting', async () => {
        const store = writable('step-1');
        const key = fromStore(store);
        const img = await render({ heldEmotion: 'hi', get playbackKey() { return key.current; } });
        await vi.advanceTimersByTimeAsync(180);
        expect(img.src).toContain('hi-2.png');
        store.set('step-2');
        flushSync();
        expect(img.src).toContain('hi-1.png');
        await vi.advanceTimersByTimeAsync(180);
        expect(img.src).toContain('hi-2.png');
    });

    it('loops working frames past 7.7s despite proposal and idle mood events', async () => {
        const store = writable<XiamAvatarEvent>({ emotion: 'thinking', lifecycle: true });
        const event = fromStore(store);
        const img = await render({ busy: true, get event() { return event.current; } });
        await vi.advanceTimersByTimeAsync(600);
        expect(img.src).toContain('thinking-2.png');
        store.set({ emotion: 'thumbs-up', lifecycle: true, mood: 'idle' });
        flushSync();
        await vi.advanceTimersByTimeAsync(7700);
        expect(img.src).toMatch(/thinking-[12]\.png$/);
        store.set({ emotion: 'idle', mood: 'idle' });
        flushSync();
        await vi.advanceTimersByTimeAsync(600);
        expect(img.src).toContain('thinking-2.png');
    });

    it('cancels an old burst timer when a new turn starts, then gives the final reaction its full grace', async () => {
        const store = writable({ busy: false, event: { emotion: 'thumbs-up', lifecycle: true, mood: 'idle' } as XiamAvatarEvent });
        const props = fromStore(store);
        const img = await render({ get busy() { return props.current.busy; }, get event() { return props.current.event; } });
        await vi.advanceTimersByTimeAsync(2000);
        store.set({ busy: true, event: { emotion: 'thinking', lifecycle: true } });
        flushSync();
        await vi.advanceTimersByTimeAsync(7700);
        expect(img.src).toMatch(/thinking-[12]\.png$/);
        store.set({ busy: false, event: { emotion: 'thumbs-up', lifecycle: true, mood: 'idle' } });
        flushSync();
        await vi.advanceTimersByTimeAsync(2799);
        expect(img.src).toContain('thumbs-up-2.png');
        await vi.advanceTimersByTimeAsync(1);
        expect(img.src).toContain('idle-open.png');
        await vi.advanceTimersByTimeAsync(2400);
        expect(img.src).toContain('idle-blink.png');
    });

    it('preserves a working frame without animation under reduced motion', async () => {
        window.matchMedia = vi.fn().mockReturnValue({ matches: true, addEventListener: vi.fn(), removeEventListener: vi.fn() });
        const img = await render({ busy: true, event: { emotion: 'idle' } });
        expect(img.src).toContain('thinking-2.png');
        await vi.advanceTimersByTimeAsync(8000);
        expect(img.src).toContain('thinking-2.png');
    });
});
