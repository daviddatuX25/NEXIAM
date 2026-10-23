// @vitest-environment jsdom
import {
    afterEach,
    beforeEach,
    describe,
    expect,
    it,
    vi,
    type Mock,
} from 'vitest';
import {
    canZoomIn,
    canZoomOut,
    getScale,
    initializeScale,
    resetScale,
    setScale,
    zoomIn,
    zoomOut,
} from '../../resources/js/lib/app-scale.svelte';
import { sound } from '../../resources/js/lib/sound.js';

describe('interface scale service (system/resources/js/lib/app-scale.svelte.ts)', () => {
    beforeEach(() => {
        localStorage.clear();
        document.documentElement.style.fontSize = '';
        resetScale();
    });

    afterEach(() => {
        localStorage.clear();
        document.documentElement.style.fontSize = '';
    });

    it('defaults to 100% scale (14px baseline)', () => {
        expect(getScale()).toBe(100);
        expect(canZoomOut()).toBe(false);
        expect(canZoomIn()).toBe(true);
    });

    it('sets scale to 115% and updates html font size and localStorage', () => {
        setScale(115);
        expect(getScale()).toBe(115);
        expect(document.documentElement.style.fontSize).toBe('16.1px');
        expect(localStorage.getItem('app_scale')).toBe('115');
        expect(canZoomOut()).toBe(true);
        expect(canZoomIn()).toBe(true);
    });

    it('sets scale to 125% and updates html font size', () => {
        setScale(125);
        expect(getScale()).toBe(125);
        expect(document.documentElement.style.fontSize).toBe('17.5px');
        expect(localStorage.getItem('app_scale')).toBe('125');
        expect(canZoomOut()).toBe(true);
        expect(canZoomIn()).toBe(false);
    });

    it('steps up with zoomIn() and steps down with zoomOut()', () => {
        expect(getScale()).toBe(100);
        zoomIn();
        expect(getScale()).toBe(115);
        zoomIn();
        expect(getScale()).toBe(125);
        zoomIn(); // At max, should remain 125
        expect(getScale()).toBe(125);

        zoomOut();
        expect(getScale()).toBe(115);
        zoomOut();
        expect(getScale()).toBe(100);
        zoomOut(); // At min, should remain 100
        expect(getScale()).toBe(100);
    });

    it('resets to 100% using resetScale()', () => {
        setScale(125);
        expect(getScale()).toBe(125);
        resetScale();
        expect(getScale()).toBe(100);
        expect(document.documentElement.style.fontSize).toBe('14px');
        expect(localStorage.getItem('app_scale')).toBe('100');
    });

    it('initializes from stored localStorage preference', () => {
        localStorage.setItem('app_scale', '115');
        initializeScale();
        expect(getScale()).toBe(115);
        expect(document.documentElement.style.fontSize).toBe('16.1px');
    });

    it('ignores invalid stored scales and defaults to 100', () => {
        localStorage.setItem('app_scale', '999');
        initializeScale();
        expect(getScale()).toBe(100);
        expect(document.documentElement.style.fontSize).toBe('14px');
    });

    it('temporarily uses homepage scale without replacing the saved staff scale', () => {
        setScale(125);
        const staffScale = getScale();
        setScale(100, false);
        expect(document.documentElement.style.fontSize).toBe('14px');
        expect(localStorage.getItem('app_scale')).toBe('125');
        setScale(staffScale, false);
        expect(getScale()).toBe(125);
        expect(document.documentElement.style.fontSize).toBe('17.5px');
        expect(localStorage.getItem('app_scale')).toBe('125');
    });
});

describe('audio service synthesis (system/resources/js/lib/sound.js)', () => {
    let contexts: Array<{
        state: string;
        currentTime: number;
        destination: object;
        createOscillator: Mock;
        createGain: Mock;
        resume: Mock;
    }>;

    beforeEach(() => {
        sound.ctx = null;
        sound.enabled = false;
        localStorage.removeItem('snd_sine_enabled');

        contexts = [];
        const mockContext = {
            state: 'running',
            currentTime: 0,
            destination: {},
            createOscillator: vi.fn().mockReturnValue({
                type: 'sine',
                frequency: {
                    setValueAtTime: vi.fn(),
                    exponentialRampToValueAtTime: vi.fn(),
                },
                connect: vi.fn(),
                start: vi.fn(),
                stop: vi.fn(),
            }),
            createGain: vi.fn().mockReturnValue({
                gain: {
                    setValueAtTime: vi.fn(),
                    linearRampToValueAtTime: vi.fn(),
                    exponentialRampToValueAtTime: vi.fn(),
                },
                connect: vi.fn(),
            }),
            resume: vi.fn().mockResolvedValue(undefined),
        };
        contexts.push(mockContext);
        sound.ctx = mockContext as typeof sound.ctx;
        sound.enable();
    });

    afterEach(() => {
        sound.ctx = null;
        sound.enabled = false;
        vi.restoreAllMocks();
    });

    it('toggles sound enabled state and preserves localStorage', () => {
        sound.enable();
        expect(sound.isEnabled()).toBe(true);

        const newState = sound.toggle();
        expect(newState).toBe(false);
        expect(sound.isEnabled()).toBe(false);

        sound.enable();
        expect(sound.isEnabled()).toBe(true);
    });

    it('schedules cues immediately in a running context', async () => {
        const ctx = contexts[0];
        const oscillatorBaseline = ctx.createOscillator.mock.calls.length;

        await expect(sound.warning()).resolves.toBe(true);
        await expect(sound.countdownUrgent(5)).resolves.toBe(true);
        sound.notification();
        await expect(sound.expired()).resolves.toBe(true);

        expect(ctx.createOscillator).toHaveBeenCalledTimes(
            oscillatorBaseline + 6,
        );
    });

    it('plays a notification cue after its suspended AudioContext resumes', async () => {
        const ctx = contexts[0];
        ctx.state = 'suspended';
        const oscillatorBaseline = ctx.createOscillator.mock.calls.length;
        let settleResume!: () => void;
        ctx.resume.mockImplementation(
            () =>
                new Promise<void>((resolve) => {
                    settleResume = () => {
                        ctx.state = 'running';
                        resolve();
                    };
                }),
        );

        const cue = sound.notification();
        expect(ctx.resume).toHaveBeenCalledOnce();
        settleResume();
        await expect(cue).resolves.toBe(true);
        expect(ctx.createOscillator).toHaveBeenCalledTimes(
            oscillatorBaseline + 1,
        );
    });

    it('waits for a suspended context and schedules only while its predicate remains current', async () => {
        const ctx = contexts[0];
        const oscillatorBaseline = ctx.createOscillator.mock.calls.length;
        ctx.state = 'suspended';
        let settleResume!: () => void;
        ctx.resume.mockImplementation(
            () =>
                new Promise<void>((resolve) => {
                    settleResume = resolve;
                }),
        );

        let isCurrent = true;
        const cue = sound.countdownUrgent(5, () => isCurrent);
        expect(ctx.resume).toHaveBeenCalledOnce();
        expect(ctx.createOscillator).toHaveBeenCalledTimes(oscillatorBaseline);
        ctx.state = 'running';
        settleResume();
        await expect(cue).resolves.toBe(true);
        const oscillatorBaselineAfterCue =
            ctx.createOscillator.mock.calls.length;
        expect(oscillatorBaselineAfterCue).toBe(oscillatorBaseline + 1);

        ctx.state = 'suspended';
        let settleStaleResume!: () => void;
        ctx.resume.mockImplementation(
            () =>
                new Promise<void>((resolve) => {
                    settleStaleResume = resolve;
                }),
        );
        isCurrent = true;
        const staleCue = sound.warning(() => isCurrent);
        isCurrent = false;
        ctx.state = 'running';
        settleStaleResume();
        await expect(staleCue).resolves.toBe(false);
        expect(ctx.createOscillator).toHaveBeenCalledTimes(
            oscillatorBaselineAfterCue,
        );
    });

    it('drops pending cues when muted or when the context instance changes', async () => {
        const ctx = contexts[0];
        ctx.state = 'suspended';
        let settleResume!: () => void;
        ctx.resume.mockImplementation(
            () =>
                new Promise<void>((resolve) => {
                    settleResume = resolve;
                }),
        );
        const mutedCue = sound.warning();
        const oscillatorBaselineBeforeMutedCue =
            ctx.createOscillator.mock.calls.length;
        sound.disable();
        ctx.state = 'running';
        settleResume();
        await expect(mutedCue).resolves.toBe(false);
        expect(ctx.createOscillator).toHaveBeenCalledTimes(
            oscillatorBaselineBeforeMutedCue,
        );

        sound.enable();
        ctx.state = 'suspended';
        let settleReplacementResume!: () => void;
        ctx.resume.mockImplementation(
            () =>
                new Promise<void>((resolve) => {
                    settleReplacementResume = resolve;
                }),
        );
        const replacedCue = sound.expired();
        const oscillatorBaselineBeforeReplacement =
            ctx.createOscillator.mock.calls.length;
        const replacementContext = { ...ctx };
        sound.ctx = replacementContext as typeof sound.ctx;
        ctx.state = 'running';
        settleReplacementResume();
        await expect(replacedCue).resolves.toBe(false);
        expect(ctx.createOscillator).toHaveBeenCalledTimes(
            oscillatorBaselineBeforeReplacement,
        );
    });

    it('returns false without scheduling for rejected resume or a closed context', async () => {
        const ctx = contexts[0];
        ctx.state = 'suspended';
        const oscillatorBaselineBeforeRejectedResume =
            ctx.createOscillator.mock.calls.length;
        ctx.resume.mockRejectedValueOnce(new Error('resume denied'));
        await expect(sound.warning()).resolves.toBe(false);
        expect(ctx.createOscillator).toHaveBeenCalledTimes(
            oscillatorBaselineBeforeRejectedResume,
        );

        ctx.state = 'closed';
        const oscillatorBaselineBeforeClosedContext =
            ctx.createOscillator.mock.calls.length;
        await expect(sound.countdownUrgent(1)).resolves.toBe(false);
        expect(ctx.createOscillator).toHaveBeenCalledTimes(
            oscillatorBaselineBeforeClosedContext,
        );
    });

    it('returns false when sound is muted', async () => {
        const ctx = contexts[0];
        sound.disable();
        const oscillatorBaseline = ctx.createOscillator.mock.calls.length;
        await expect(sound.warning()).resolves.toBe(false);
        await expect(sound.countdownUrgent(10)).resolves.toBe(false);
        await expect(sound.expired()).resolves.toBe(false);
        sound.notification();
        expect(ctx.createOscillator).toHaveBeenCalledTimes(oscillatorBaseline);
    });

    it('plays press, tick, and pop immediate cues when running', () => {
        const ctx = contexts[0];
        const baseline = ctx.createOscillator.mock.calls.length;
        sound.press();
        sound.tick();
        sound.pop();
        expect(ctx.createOscillator.mock.calls.length).toBeGreaterThan(baseline);
    });

    it('triggers sound on button and close button clicks while omitting text inputs', () => {
        const ctx = contexts[0];
        sound.lastInteractionSoundAt = 0;
        const baseline = ctx.createOscillator.mock.calls.length;

        // Click close button
        const closeBtn = document.createElement('button');
        closeBtn.setAttribute('aria-label', 'Close dialog');
        document.body.appendChild(closeBtn);
        closeBtn.click();
        expect(ctx.createOscillator.mock.calls.length).toBeGreaterThan(baseline);
        const afterClose = ctx.createOscillator.mock.calls.length;

        // Click text input - MUST omit sound
        const inputEl = document.createElement('input');
        inputEl.type = 'text';
        document.body.appendChild(inputEl);
        inputEl.click();
        expect(ctx.createOscillator.mock.calls.length).toBe(afterClose);

        // Clean up
        closeBtn.remove();
        inputEl.remove();
    });
});
