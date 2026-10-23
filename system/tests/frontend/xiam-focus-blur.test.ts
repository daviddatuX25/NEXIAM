// @vitest-environment jsdom

import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import { flushSync, mount, tick, unmount } from 'svelte';
import XiamAssistPanel from '../../resources/js/Components/kit/Xiam/XiamAssistPanel.svelte';
import { resetAvatarManifestCache } from '../../resources/js/Components/kit/Xiam/XiamAvatar.svelte';
import manifest from '../../public/assets/xiam/emotions/v2/manifest.json';

const transport = vi.hoisted(() => ({
    submitXiamTurn: vi.fn(),
    streamXiamConversation: vi.fn(),
    fetchXiamSnapshot: vi.fn(),
    fetchXiamMode: vi.fn(),
    setXiamMode: vi.fn(),
    confirmXiamProposal: vi.fn(),
    cancelXiamProposal: vi.fn(),
    clearXiamConversation: vi.fn(),
    startXiamSkill: vi.fn(),
    xiamCsrfHeaders: () => ({}),
}));

const sounds = vi.hoisted(() => ({
    press: vi.fn(),
    tick: vi.fn(),
    success: vi.fn(),
    error: vi.fn(),
    pop: vi.fn(),
}));

vi.mock('../../resources/js/lib/xiam-transport', () => transport);
vi.mock('../../resources/js/lib/sound.js', () => ({ sound: sounds }));

const pageState = vi.hoisted(() => ({
    url: '/exams/sessions',
    props: { auth: { user: { id: 'actor-1' } }, flash: {} },
}));

vi.mock('@inertiajs/svelte', async (importOriginal) => {
    const actual = (await importOriginal()) || {};
    return {
        ...actual,
        usePage: () => pageState,
    };
});

describe('Xiam composer focus preservation while writing', () => {
    let container: HTMLDivElement;
    let component: Record<string, unknown>;

    beforeEach(async () => {
        vi.resetAllMocks();
        resetAvatarManifestCache();
        sessionStorage.clear();
        sessionStorage.setItem('nexiam.xiam.expanded', '1');
        vi.stubGlobal('fetch', vi.fn().mockResolvedValue({ ok: true, json: async () => manifest }));
        window.matchMedia = vi.fn().mockReturnValue({ matches: false, addEventListener: vi.fn(), removeEventListener: vi.fn() });
        transport.fetchXiamSnapshot.mockResolvedValue({
            conversation_id: 'conversation-1',
            context_epoch: 'epoch-1',
            cursor: 0,
            messages: [],
        });
        container = document.createElement('div');
        document.body.appendChild(container);
    });

    afterEach(async () => {
        if (component) await unmount(component);
        container.remove();
        vi.unstubAllGlobals();
    });

    it('does not disable composer or drop textarea focus during background mode/preference refresh', async () => {
        const { promise: modePromise, resolve: resolveMode } = Promise.withResolvers();
        transport.fetchXiamMode.mockImplementation(() => modePromise);

        component = mount(XiamAssistPanel, {
            target: container,
            props: {
                session: {
                    contract_version: '2',
                    availability: 'assistant_available',
                    page_pack: { key: 'staff.exams.sessions.index@1', version: 1 },
                    context_epoch: 'epoch-1',
                    interaction_modes: [
                        { key: 'inquiry', label: 'Inquiry' },
                        { key: 'assistant', label: 'Assistant' },
                    ],
                    active_mode: 'inquiry',
                    usage: {
                        state: 'available',
                        used: 0,
                        limit: 15,
                        remaining: 15,
                        credit_balance: 0,
                        unit: 'asks',
                        period: 'day',
                    },
                },
            },
        });

        flushSync();
        await tick();

        const textarea = container.querySelector('textarea') as HTMLTextAreaElement;
        expect(textarea).not.toBeNull();
        expect(textarea.disabled).toBe(false);
        textarea.focus();
        expect(document.activeElement).toBe(textarea);

        // While fetchXiamMode is in-flight in background, textarea MUST NOT be disabled
        // If it is disabled, the browser immediately blurs the textarea and drops focus/closes keyboard!
        expect(textarea.disabled).toBe(false);
        expect(document.activeElement).toBe(textarea);

        // Resolve fetchXiamMode
        resolveMode({
            active_mode: 'inquiry',
            interaction_modes: [
                { key: 'inquiry', label: 'Inquiry' },
                { key: 'assistant', label: 'Assistant' },
            ],
            context_epoch: 'epoch-1',
            usage: {
                state: 'available',
                used: 0,
                limit: 15,
                remaining: 15,
                credit_balance: 0,
                unit: 'asks',
                period: 'day',
            },
        });
        await tick();

        expect(textarea.disabled).toBe(false);
        expect(document.activeElement).toBe(textarea);
    });

    it('does not drop focus when a background mode refresh occurs while typing', async () => {
        const initialMode = {
            active_mode: 'inquiry' as const,
            interaction_modes: [
                { key: 'inquiry', label: 'Inquiry' },
                { key: 'assistant', label: 'Assistant' },
            ],
            context_epoch: 'epoch-1',
            usage: {
                state: 'available' as const,
                used: 0,
                limit: 15,
                remaining: 15,
                credit_balance: 0,
                unit: 'asks',
                period: 'day',
            },
        };
        transport.fetchXiamMode.mockResolvedValue(initialMode);

        component = mount(XiamAssistPanel, {
            target: container,
            props: {
                session: {
                    contract_version: '2',
                    availability: 'assistant_available',
                    page_pack: { key: 'staff.exams.sessions.index@1', version: 1 },
                    context_epoch: 'epoch-1',
                    interaction_modes: [
                        { key: 'inquiry', label: 'Inquiry' },
                        { key: 'assistant', label: 'Assistant' },
                    ],
                    active_mode: 'inquiry',
                    usage: {
                        state: 'available',
                        used: 0,
                        limit: 15,
                        remaining: 15,
                        credit_balance: 0,
                        unit: 'asks',
                        period: 'day',
                    },
                },
            },
        });

        flushSync();
        await tick();
        await vi.waitFor(() => {
            const el = container.querySelector('textarea') as HTMLTextAreaElement;
            expect(el?.disabled).toBe(false);
        });

        const textarea = container.querySelector('textarea') as HTMLTextAreaElement;
        expect(textarea).not.toBeNull();
        textarea.focus();
        expect(document.activeElement).toBe(textarea);

        // Now user is typing, and a background pulse or refresh runs
        const { promise: backgroundModePromise, resolve: resolveBackgroundMode } = Promise.withResolvers();
        transport.fetchXiamMode.mockImplementation(() => backgroundModePromise);

        // Simulate session prop update (e.g. from server page props or new context epoch)
        component.session = {
            ...component.session as Record<string, unknown>,
            context_epoch: 'epoch-2',
        };
        flushSync();
        await tick();

        // The textarea must remain enabled and focused while writing!
        expect(textarea.disabled).toBe(false);
        expect(document.activeElement).toBe(textarea);

        resolveBackgroundMode(initialMode);
        await tick();
        expect(document.activeElement).toBe(textarea);
    });
});
