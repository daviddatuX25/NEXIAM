// @vitest-environment jsdom

import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import { flushSync, mount, tick, unmount } from 'svelte';
import { get } from 'svelte/store';
import XiamAssistPanel from '../../resources/js/Components/kit/Xiam/XiamAssistPanel.svelte';
import { resetAvatarManifestCache } from '../../resources/js/Components/kit/Xiam/XiamAvatar.svelte';
import type { XiamModeView, XiamUsageView } from '../../resources/js/types/xiam';
import { toasts } from '../../resources/js/lib/toast.js';
import manifest from '../../public/assets/xiam/emotions/v2/manifest.json';

const transport = vi.hoisted(() => ({
    submitXiamTurn: vi.fn(), streamXiamConversation: vi.fn(), fetchXiamSnapshot: vi.fn(),
    fetchXiamMode: vi.fn(), setXiamMode: vi.fn(),
    confirmXiamProposal: vi.fn(), cancelXiamProposal: vi.fn(), clearXiamConversation: vi.fn(),
    startXiamSkill: vi.fn(), xiamCsrfHeaders: () => ({}),
}));
const sounds = vi.hoisted(() => ({ press: vi.fn(), tick: vi.fn(), success: vi.fn(), error: vi.fn(), pop: vi.fn() }));
vi.mock('../../resources/js/lib/xiam-transport', () => transport);
vi.mock('../../resources/js/lib/sound.js', () => ({ sound: sounds }));
const pageState = vi.hoisted(() => ({
    url: '/exams/sessions',
    props: { auth: { user: { id: 'actor-1' } } },
}));
vi.mock('@inertiajs/svelte', async (importOriginal) => {
    const actual = await importOriginal<Record<string, unknown>>();
    return {
        ...actual,
        usePage: () => pageState,
    };
});

const proposal = {
    proposal_id: 'proposal-1', capability_id: 'exams.session.create@1', status: 'pending',
    diff: [{ key: 'room', label: 'Room', before: 'None', after: 'Main Hall 101' }],
    preflight_checks: { checks: [{ key: 'room_suitable', status: 'pass', label: 'Room suitability' }] },
};
const snapshot = {
    conversation_id: 'conversation-1',
    context_epoch: 'epoch-1',
    cursor: 3, run: { id: 'run-1', status: 'succeeded' }, events: [], proposals: [],
    messages: [{ id: 'answer-1', run_id: 'run-1', role: 'assistant', content: 'Please review the proposed room.' }],
};
const usageView = (overrides: Partial<XiamUsageView> = {}): XiamUsageView => ({
    state: 'available',
    used: 0,
    limit: 15,
    remaining: 15,
    credit_balance: 0,
    unit: 'asks',
    period: 'day',
    resets_at: '2026-10-06T00:00:00+08:00',
    ...overrides,
});
const modeView = (
    active_mode: 'inquiry' | 'assistant' = 'inquiry',
    usage: XiamUsageView = usageView(),
): XiamModeView => ({
    active_mode,
    interaction_modes: [
        { key: 'inquiry', label: 'Inquiry' },
        { key: 'assistant', label: 'Assistant' },
    ],
    context_epoch: 'epoch-1',
    usage,
});

describe('Xiam panel lifecycle', () => {
    let container: HTMLDivElement;
    let component: Record<string, unknown>;
    let onPart: (part: Record<string, unknown>) => void;
    let endStream: () => void;

    beforeEach(async () => {
        pageState.url = '/exams/sessions';
        vi.useFakeTimers();
        vi.setSystemTime(new Date('2026-10-05T00:00:00+08:00'));
        vi.resetAllMocks();
        resetAvatarManifestCache();
        sessionStorage.clear();
        sessionStorage.setItem('nexiam.xiam.expanded', '1');
        toasts.clear();
        vi.stubGlobal('fetch', vi.fn().mockResolvedValue({ ok: true, json: async () => manifest }));
        vi.stubGlobal('requestAnimationFrame', (callback: FrameRequestCallback) => setTimeout(() => callback(0), 0));
        window.matchMedia = vi.fn().mockReturnValue({ matches: false, addEventListener: vi.fn(), removeEventListener: vi.fn() });
        transport.submitXiamTurn.mockResolvedValue({ conversation_id: 'conversation-1', run_id: 'run-1', cursor: 0 });
        transport.fetchXiamSnapshot.mockResolvedValue(snapshot);
        transport.fetchXiamMode.mockResolvedValue(modeView());
        transport.setXiamMode.mockImplementation(async (active_mode, context_epoch) => ({
            ...modeView(active_mode),
            context_epoch,
        }));
        transport.streamXiamConversation.mockImplementation((_conversation, _cursor, callback) => {
            onPart = callback;
            const stream = Promise.withResolvers<void>();
            endStream = stream.resolve;
            return stream.promise;
        });
        transport.clearXiamConversation.mockResolvedValue({ cleared: true, conversation_id: null });
        container = document.createElement('div');
        document.body.appendChild(container);
    });

    afterEach(async () => {
        if (component) await unmount(component);
        toasts.clear();
        container.remove();
        vi.unstubAllGlobals();
        vi.useRealTimers();
    });

    async function open(
        conversationId: string | null = null,
        sessionOverrides: Record<string, unknown> = {},
    ) {
        component = mount(XiamAssistPanel, {
            target: container,
            props: {
                session: {
                    contract_version: '2',
                    availability: 'assistant_available',
                    page_pack: { key: 'staff.exams.sessions.index@1', version: 1 },
                    context_epoch: 'epoch-1',
                    active_mode: 'inquiry',
                    interaction_modes: modeView().interaction_modes,
                    conversation: { id: conversationId, cursor: 0 },
                    usage: usageView(),
                    proposals: [],
                    ...sessionOverrides,
                },
            },
        });
        await tick();
        await vi.advanceTimersByTimeAsync(0);
    }

    async function send() {
        const textarea = container.querySelector('textarea')!;
        textarea.value = 'Help schedule an exam';
        textarea.dispatchEvent(new Event('input', { bubbles: true }));
        flushSync();
        container.querySelector<HTMLButtonElement>('[aria-label="Send message"]')!.click();
        await tick();
        await vi.advanceTimersByTimeAsync(0);
    }

    it('changes mode only after server acknowledgement and does not start a skill or turn', async () => {
        await open();
        const acknowledged = Promise.withResolvers<XiamModeView>();
        transport.setXiamMode.mockReturnValueOnce(acknowledged.promise);
        const assistant = Array.from(container.querySelectorAll('button')).find(
            (button) => button.textContent?.trim() === 'Assistant',
        ) as HTMLButtonElement;
        assistant.click();
        await tick();
        expect(Array.from(container.querySelectorAll('button')).filter((button) => /^(Inquiry|Assistant)$/.test(button.textContent?.trim() ?? '')).every((button) => (button as HTMLButtonElement).disabled)).toBe(true);
        expect(container.querySelector('textarea')?.hasAttribute('disabled')).toBe(true);
        expect(get(toasts)).toEqual([]);
        acknowledged.resolve(modeView('assistant'));
        await tick();
        await vi.advanceTimersByTimeAsync(0);
        expect(Array.from(container.querySelectorAll('button')).find((button) => button.textContent?.trim() === 'Assistant')?.getAttribute('aria-pressed')).toBe('true');
        expect(transport.submitXiamTurn).not.toHaveBeenCalled();
        expect(transport.startXiamSkill).not.toHaveBeenCalled();
        expect(container.querySelector('[role="status"]')?.textContent).toMatch(/\bAssistant\b/);
    });

    it('uses the clear acknowledgement epoch on the next mode switch', async () => {
        await open();
        transport.clearXiamConversation.mockResolvedValueOnce({
            cleared: true,
            conversation_id: null,
            context_epoch: 'epoch-after-clear',
        });
        container.querySelector<HTMLButtonElement>('[aria-label="Clear conversation"]')?.click();
        await tick();
        await vi.advanceTimersByTimeAsync(0);
        Array.from(container.querySelectorAll('button')).find(
            (button) => button.textContent?.trim() === 'Assistant',
        )?.click();
        await tick();
        await vi.advanceTimersByTimeAsync(0);
        expect(transport.setXiamMode).toHaveBeenLastCalledWith('assistant', 'epoch-after-clear');
    });

    it('keeps the acknowledged mode on denied switching and reports the failure', async () => {
        await open();
        transport.setXiamMode.mockRejectedValueOnce(new Error('mode_change_busy'));
        const assistant = Array.from(container.querySelectorAll('button')).find(
            (button) => button.textContent?.trim() === 'Assistant',
        ) as HTMLButtonElement;
        assistant.click();
        await tick();
        await vi.advanceTimersByTimeAsync(0);
        expect(Array.from(container.querySelectorAll('button')).find((button) => button.textContent?.trim() === 'Inquiry')?.getAttribute('aria-pressed')).toBe('true');
        expect(Array.from(container.querySelectorAll('button')).find((button) => button.textContent?.trim() === 'Assistant')?.getAttribute('aria-pressed')).toBe('false');
        expect(get(toasts)).toMatchObject([{ type: 'error' }]);
        expect(transport.submitXiamTurn).not.toHaveBeenCalled();
    });

    it('uses an authoritative null epoch instead of the stale page epoch when switching', async () => {
        transport.fetchXiamMode.mockResolvedValue({
            ...modeView(),
            context_epoch: null,
        });
        transport.setXiamMode.mockImplementation(async (active_mode, context_epoch) => {
            if (context_epoch !== null) throw new Error('context_epoch_conflict');
            return { ...modeView(active_mode), context_epoch: null };
        });

        await open(null, { context_epoch: 'stale-page-epoch' });
        Array.from(container.querySelectorAll('button')).find(
            (button) => button.textContent?.trim() === 'Assistant',
        )?.click();
        await tick();
        await vi.advanceTimersByTimeAsync(0);

        expect(Array.from(container.querySelectorAll('button')).find(
            (button) => button.textContent?.trim() === 'Assistant',
        )?.getAttribute('aria-pressed')).toBe('true');
        expect(container.querySelector('[role="alert"]')).toBeNull();
        expect(container.querySelector('[role="status"]')?.textContent).toMatch(/\bAssistant\b/);
    });

    it('keeps all mode and composer controls disabled when the server exposes no modes', async () => {
        transport.fetchXiamMode.mockResolvedValueOnce({
            ...modeView(),
            interaction_modes: [],
        });
        await open(null, { interaction_modes: [] });
        expect(Array.from(container.querySelectorAll('button')).filter((button) => /^(Inquiry|Assistant)$/.test(button.textContent?.trim() ?? '')).every((button) => (button as HTMLButtonElement).disabled)).toBe(true);
        expect(container.querySelector('textarea')?.hasAttribute('disabled')).toBe(true);
        expect(transport.submitXiamTurn).not.toHaveBeenCalled();
    });

    it('reconciles proposal status after a lost demotion response without rehydrating messages', async () => {
        transport.fetchXiamMode.mockResolvedValue(modeView('assistant'));
        await open(null, {
            active_mode: 'assistant',
            context_epoch: 'epoch-1',
            page_pack: { key: 'staff.workspace.home@1', version: 1 },
        });
        transport.fetchXiamSnapshot.mockResolvedValueOnce({
            ...snapshot,
            proposals: [],
        });
        await send();
        onPart({ type: 'data-xiam-proposal', data: proposal });
        endStream();
        await tick();
        await vi.advanceTimersByTimeAsync(0);
        const userMessageCount = container.textContent?.match(/Help schedule an exam/g)?.length ?? 0;
        transport.setXiamMode.mockRejectedValueOnce(new TypeError('Failed to fetch'));
        transport.fetchXiamMode.mockResolvedValueOnce({
            ...modeView('inquiry'),
            context_epoch: 'epoch-2',
        });
        transport.fetchXiamSnapshot.mockResolvedValueOnce({
            ...snapshot,
            context_epoch: 'epoch-2',
            proposals: [{ id: 'proposal-1', status: 'invalidated' }],
        });
        Array.from(container.querySelectorAll('button')).find(
            (button) => button.textContent?.trim() === 'Inquiry',
        )?.click();
        await tick();
        await vi.advanceTimersByTimeAsync(0);
        expect(container.textContent?.match(/Help schedule an exam/g)?.length ?? 0).toBe(userMessageCount);
        expect(container.querySelector('[role="status"]')?.textContent).toContain('Inquiry mode is active.');
        expect(Array.from(container.querySelectorAll('button')).some((button) => button.textContent?.includes('Confirm'))).toBe(false);
    });

    it('shows the beta notice only after an empty conversation loads successfully', async () => {
        const pending = Promise.withResolvers<Record<string, unknown>>();
        transport.fetchXiamSnapshot.mockReturnValueOnce(pending.promise);
        await open('conversation-empty');
        expect(container.textContent).not.toContain('Xiam is in beta');
        pending.resolve({
            cursor: 0,
            run: null,
            events: [],
            proposals: [],
            messages: [],
        });
        await tick();
        await vi.advanceTimersByTimeAsync(0);
        expect(container.textContent).toContain('Xiam is in beta');
        expect(container.textContent).toContain('avoid sharing sensitive information');
    });

    it('renders the applicant 15/15 exhausted allowance and keeps the composer disabled', async () => {
        const exhausted = usageView({
            state: 'exhausted',
            used: 15,
            limit: 15,
            remaining: 0,
            credit_balance: 0,
            resets_at: '2026-10-06T00:00:00+08:00',
        });
        transport.fetchXiamMode.mockResolvedValueOnce(modeView('inquiry', exhausted));
        await open(null, { availability: 'quota_exhausted', usage: exhausted });
        expect(container.textContent).toContain('15/15 asks');
        expect(container.textContent).toContain('0 left');
        expect(container.textContent).toContain('day');
        expect(container.textContent).not.toContain('0 credits');
        expect(container.querySelector('textarea')?.hasAttribute('disabled')).toBe(true);
        expect(container.textContent).not.toContain('Xiam is in beta');
    });

    it('refreshes usage across page navigation and advances the epoch for the next mode switch', async () => {
        await open();
        transport.submitXiamTurn.mockResolvedValueOnce({
            conversation_id: 'conversation-1',
            run_id: 'run-1',
            cursor: 0,
            context_epoch: 'epoch-accepted',
            usage: usageView({ used: 1, remaining: 14 }),
        });
        transport.fetchXiamSnapshot.mockResolvedValueOnce({
            ...snapshot,
            context_epoch: 'epoch-settled',
            usage: usageView({ used: 0, remaining: 15, credit_balance: 1 }),
        });
        await send();
        expect(container.textContent).toContain('1/15 asks');
        pageState.url = '/workspace';
        endStream();
        await tick();
        await vi.advanceTimersByTimeAsync(0);
        expect(container.textContent).toContain('0/15 asks');
        expect(container.textContent).toContain('15 left');
        expect(container.textContent).toContain('day · 1 credits');

        Array.from(container.querySelectorAll('button')).find(
            (button) => button.textContent?.trim() === 'Assistant',
        )?.click();
        await tick();
        await vi.advanceTimersByTimeAsync(0);
        expect(transport.setXiamMode).toHaveBeenLastCalledWith('assistant', 'epoch-settled');
        expect(container.textContent).toContain('Assistant mode is active.');
    });

    it('applies structured exhausted usage from a quota rejection', async () => {
        await open();
        transport.submitXiamTurn.mockRejectedValueOnce(
            Object.assign(new Error('quota_exhausted'), {
                usage: usageView({ state: 'exhausted', used: 15, limit: 15, remaining: 0 }),
            }),
        );
        await send();
        await tick();
        expect(container.textContent).toContain('15/15 asks');
        expect(container.querySelector('textarea')?.hasAttribute('disabled')).toBe(true);
    });

    it('ignores a legacy browser mode value in favor of the server session', async () => {
        sessionStorage.setItem('nexiam.xiam.interaction', 'assistant');
        await open();
        expect(Array.from(container.querySelectorAll('button')).find((button) => button.textContent?.trim() === 'Inquiry')?.getAttribute('aria-pressed')).toBe('true');
        expect(Array.from(container.querySelectorAll('button')).find((button) => button.textContent?.trim() === 'Assistant')?.getAttribute('aria-pressed')).toBe('false');
    });

    it('renders only safe server-issued navigation from snapshots and stream parts', async () => {
        transport.fetchXiamSnapshot.mockResolvedValueOnce({
            cursor: 1,
            messages: [{
                run_id: 'navigation-run',
                role: 'assistant',
                content: 'Try /admissions or [the registrar](/registrar/enrollment-eligibility).',
                navigation: [
                    { id: 'inquiries', label: 'My inquiries', href: '/applicant/inquiries', version: 1 },
                    { id: 'application', label: 'My application', href: '/applicant/portal', version: 1 },
                    { id: 'external', label: 'External', href: 'https://example.com', version: 1 },
                    { id: 'old-wire', label: 'Old route', href: '/admissions', version: 2 },
                    { id: 'staff-protocol', label: 'Staff', href: '//example.test/registrar', version: 1 },
                    { id: 'bad-id', label: 'Missing version', href: '/exams/sessions' },
                ],
            }],
            events: [{
                seq: 1,
                type: 'assistant.navigation',
                payload: {
                    navigation: [
                        { id: 'stale', label: 'Stale staff page', href: '/admissions', version: 1 },
                    ],
                },
            }],
            proposals: [],
        });
        await open('navigation-conversation');
        const destination = (pathname: string) => [...container.querySelectorAll<HTMLAnchorElement>('a[href]')]
            .find((anchor) => new URL(anchor.href).pathname === pathname);

        expect(destination('/applicant/inquiries')?.textContent).toContain('Visit My inquiries');
        expect(destination('/applicant/portal')?.textContent).toContain('Visit My application');
        expect(destination('/admissions')).toBeUndefined();
        expect(destination('/registrar/enrollment-eligibility')).toBeUndefined();
        expect([...container.querySelectorAll<HTMLAnchorElement>('a[href]')].every((anchor) => new URL(anchor.href).origin === window.location.origin)).toBe(true);
        expect(container.textContent).toContain('Try /admissions');

        await send();
        onPart({
            type: 'data-xiam-navigation',
            data: {
                navigation: [
                    { id: 'sessions', label: 'Exam sessions', href: '/exams/sessions', version: 1 },
                    { id: 'forged', label: 'Forged', href: 'javascript:alert(1)', version: 1 },
                ],
            },
        });
        flushSync();
        expect(destination('/exams/sessions')?.textContent).toContain('Visit Exam sessions');
        expect(container.querySelector('a[href^="javascript:"]')).toBeNull();
    });

    it('keeps one progress display and a working avatar after a mid-turn proposal and idle cue', async () => {
        transport.fetchXiamSnapshot.mockResolvedValueOnce({ cursor: 0, run: null, events: [], proposals: [], messages: [] });
        await open('previous-conversation');
        expect(container.textContent).not.toContain("Hi, I'm Xiam");
        await send();
        onPart({ type: 'data-xiam-proposal', data: proposal });
        onPart({ type: 'data-xiam-avatar', data: { event_type: 'assistant.mood_cue', emotion: 'idle' } });
        onPart({ type: 'data-xiam-steps', data: { event_type: 'tool.call', capability_id: 'exams.rooms.available@1' } });
        flushSync();
        await vi.advanceTimersByTimeAsync(7700);
        expect(container.querySelector('img')!.src).toMatch(/thinking-[12]\.png$/);
        expect(container.textContent).toContain('Thinking (7.7s)');
        expect(container.querySelectorAll('.animate-bounce')).toHaveLength(3);
        expect(container.textContent).toContain('Checking room availability');
        expect(container.textContent).not.toContain('EvidenceBundle');
        expect(transport.fetchXiamSnapshot).toHaveBeenCalledTimes(1);
        expect(container.textContent).toContain('Help schedule an exam');
        endStream();
        await tick();
        await vi.advanceTimersByTimeAsync(6000);
        expect(container.querySelector('img')!.src).toMatch(/idle-(open|blink|glance)\.png$/);
    });

    it('starts and loops the real thinking artwork on three consecutive turns without remounting', async () => {
        await open();
        const img = container.querySelector('img')!;

        for (let turn = 1; turn <= 3; turn += 1) {
            const runId = `consecutive-${turn}`;
            transport.submitXiamTurn.mockResolvedValueOnce({
                conversation_id: 'conversation-1',
                run_id: runId,
                cursor: turn * 3,
            });
            transport.fetchXiamSnapshot.mockResolvedValueOnce({
                ...snapshot,
                run: { id: runId, status: 'succeeded' },
                messages: [{ id: `answer-${turn}`, run_id: runId, role: 'assistant', content: `Answer for turn ${turn}.` }],
            });

            await send();
            expect(img.src, `turn ${turn} must enter thinking`).toContain('thinking-1.png');
            await vi.advanceTimersByTimeAsync(600);
            expect(img.src, `turn ${turn} must advance to its second art frame`).toContain('thinking-2.png');
            await vi.advanceTimersByTimeAsync(600);
            expect(img.src, `turn ${turn} must loop to its first art frame`).toContain('thinking-1.png');
            await vi.advanceTimersByTimeAsync(10800);
            expect(img.src, `turn ${turn} must remain working at twelve seconds`).toContain('thinking-1.png');

            onPart({ type: 'text-start', id: runId });
            onPart({ type: 'text-delta', id: runId, delta: `Answer for turn ${turn}.` });
            onPart({ type: 'text-end', id: runId });
            onPart({ type: 'data-xiam-avatar', data: { event_type: 'assistant.mood_cue', emotion: 'thumbs-up' } });
            endStream();
            await tick();
            await vi.advanceTimersByTimeAsync(500);
            expect(container.querySelector<HTMLButtonElement>('[aria-label="Send message"]')!.disabled).toBe(true);
            await vi.advanceTimersByTimeAsync(turn === 1 ? 2500 : 500);
        }
    });

    it('loads the thinking artwork before submitting, then keeps all three turns independent of frame HTTP requests', async () => {
        const decoded = [
            Promise.withResolvers<void>(),
            Promise.withResolvers<void>(),
        ];
        let decodeIndex = 0;
        const revokeObjectURL = vi.fn();
        class ArtworkURL extends URL {
            static createObjectURL = vi.fn()
                .mockReturnValueOnce('blob:thinking-1.png')
                .mockReturnValueOnce('blob:thinking-2.png');
            static revokeObjectURL = revokeObjectURL;
        }
        vi.stubGlobal('URL', ArtworkURL);
        vi.stubGlobal('Image', class {
            src = '';
            decode() {
                return decoded[decodeIndex++].promise;
            }
        });
        const fetchArtwork = vi.fn().mockImplementation((input: string) => {
            if (input.endsWith('manifest.json')) {
                return Promise.resolve(new Response(JSON.stringify(manifest)));
            }
            return Promise.resolve({
                ok: true,
                blob: async () => new Blob(['artwork'], { type: 'image/png' }),
            });
        });
        vi.stubGlobal('fetch', fetchArtwork);
        await open();
        await send();

        expect(transport.submitXiamTurn).not.toHaveBeenCalled();
        decoded[0].resolve();
        await tick();
        await vi.advanceTimersByTimeAsync(0);
        expect(transport.submitXiamTurn).not.toHaveBeenCalled();
        decoded[1].resolve();
        await tick();
        await vi.advanceTimersByTimeAsync(0);
        expect(transport.submitXiamTurn).toHaveBeenCalledTimes(1);

        fetchArtwork.mockRejectedValue(new Error('HTTP worker is occupied by the AI request'));
        const img = container.querySelector('img')!;
        for (let turn = 1; turn <= 3; turn += 1) {
            if (turn > 1) await send();
            expect(img.src).toBe('blob:thinking-1.png');
            await vi.advanceTimersByTimeAsync(600);
            expect(img.src).toBe('blob:thinking-2.png');
            await vi.advanceTimersByTimeAsync(600);
            expect(img.src).toBe('blob:thinking-1.png');
            onPart({ type: 'done' });
            endStream();
            await tick();
            await vi.advanceTimersByTimeAsync(4000);
        }
        expect(fetchArtwork.mock.calls.filter(([url]) => String(url).includes('/frames/'))).toHaveLength(2);
        resetAvatarManifestCache();
        expect(revokeObjectURL).toHaveBeenCalledWith('blob:thinking-1.png');
        expect(revokeObjectURL).toHaveBeenCalledWith('blob:thinking-2.png');
    });

    it('announces success only after committed confirmation and keeps failure visible without a success toast', async () => {
        await open();
        await send();
        onPart({ type: 'data-xiam-proposal', data: proposal });
        endStream();
        await tick();
        await vi.advanceTimersByTimeAsync(4000);
        transport.confirmXiamProposal.mockRejectedValueOnce(new Error('reauth_required'));
        const confirm = () => Array.from(container.querySelectorAll('button')).find(button => button.textContent?.includes('Confirm'))!;
        confirm().click();
        await tick();
        await vi.advanceTimersByTimeAsync(0);
        expect(sounds.success).not.toHaveBeenCalled();
        expect(get(toasts)).toEqual([]);
        expect(container.textContent).toContain('sign in again');

        await send();
        onPart({ type: 'data-xiam-proposal', data: proposal });
        endStream();
        await tick();
        await vi.advanceTimersByTimeAsync(4000);
        const pending = Promise.withResolvers<unknown>();
        transport.confirmXiamProposal.mockReturnValueOnce(pending.promise);
        confirm().click();
        await tick();
        expect(get(toasts)).toEqual([]);
        expect(sounds.success).not.toHaveBeenCalled();
        pending.resolve({ ok: true, proposal: { id: 'proposal-1', status: 'executed' }, result: { ok: true } });
        await tick();
        await vi.advanceTimersByTimeAsync(0);
        expect(get(toasts)).toMatchObject([{ type: 'success', message: 'Exam session created.' }]);
        expect(sounds.success).toHaveBeenCalledTimes(1);
        expect(container.textContent).not.toContain('Main Hall 101');
        expect(Array.from(container.querySelectorAll('button')).filter(button => button.textContent?.includes('Confirm'))).toHaveLength(0);
    });

    it('restores completed history without replaying a success toast', async () => {
        transport.fetchXiamSnapshot.mockResolvedValueOnce({
            ...snapshot,
            events: [{ seq: 1, type: 'skill.proposal', payload: proposal }, { seq: 2, type: 'skill.proposal_confirmed', payload: { proposal_id: 'proposal-1' } }],
            proposals: [{ id: 'proposal-1', status: 'executed' }],
        });
        await open('conversation-1');
        expect(sounds.success).not.toHaveBeenCalled();
        expect(get(toasts)).toEqual([]);
    });
});
