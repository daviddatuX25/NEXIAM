import { afterEach, describe, expect, it, vi } from 'vitest';
import {
    createOmrQrCommandDetector,
    createOmrVoiceCues,
    OMR_QR_COMMAND_PAYLOADS,
    parseOmrQrCommand,
    type OmrQrCommand,
    type OmrQrDecoder,
    type OmrQrObservation,
} from '../../resources/js/lib/omrCapturePlugins';

const commandValue = (command: OmrQrCommand): OmrQrObservation => ({ rawValue: OMR_QR_COMMAND_PAYLOADS[command] });
const video = { readyState: 4 } as HTMLVideoElement;

function decoderWith(results: Array<readonly OmrQrObservation[] | Error>): OmrQrDecoder {
    return {
        supported: true,
        async detect() {
            const result = results.shift() ?? [];
            if (result instanceof Error) throw result;
            return result;
        },
    };
}

async function observe(now: { value: number }): Promise<void> {
    now.value += 100;
    await vi.advanceTimersByTimeAsync(100);
}

afterEach(() => {
    vi.useRealTimers();
    vi.unstubAllGlobals();
});

describe('OMR command-card plugins', () => {
    it('prints and parses only the four exact versioned semantic commands', () => {
        expect(OMR_QR_COMMAND_PAYLOADS).toEqual({
            CAPTURE: 'NEXIAM:OMR:CMD:1:CAPTURE',
            NEXT_PAGE: 'NEXIAM:OMR:CMD:1:NEXT_PAGE',
            NEXT_SET: 'NEXIAM:OMR:CMD:1:NEXT_SET',
            REDO_LAST: 'NEXIAM:OMR:CMD:1:REDO_LAST',
        });
        for (const command of Object.keys(OMR_QR_COMMAND_PAYLOADS) as OmrQrCommand[]) {
            expect(parseOmrQrCommand(OMR_QR_COMMAND_PAYLOADS[command])).toBe(command);
        }
        for (const invalid of [
            'CAPTURE',
            'NEXIAM:OMR:CMD:CAPTURE',
            'NEXIAM:OMR:CMD:2:CAPTURE',
            'NEXIAM:OMR:CMD:1:CAPTURE/https://example.test',
            'NEXIAM:OMR:CMD:1:APPLICANT:99117',
            'https://example.test',
        ]) {
            expect(parseOmrQrCommand(invalid)).toBeNull();
        }
    });

    it('requires two fresh observations and rejects even two identical spatial cards as ambiguous', async () => {
        vi.useFakeTimers();
        const now = { value: 0 };
        const onCommand = vi.fn();
        const detector = createOmrQrCommandDetector({
            decoder: decoderWith([
                [commandValue('CAPTURE')],
                [commandValue('CAPTURE'), commandValue('CAPTURE')],
                [commandValue('CAPTURE')],
                [commandValue('CAPTURE')],
                [commandValue('CAPTURE')],
            ]),
            now: () => now.value,
        });
        detector.start(video, onCommand);

        await observe(now);
        await observe(now);
        expect(onCommand).not.toHaveBeenCalled();
        await observe(now);
        expect(onCommand).not.toHaveBeenCalled();
        await observe(now);
        expect(onCommand).toHaveBeenCalledExactlyOnceWith('CAPTURE');
        await observe(now);
        expect(onCommand).toHaveBeenCalledTimes(1);
        detector.dispose();
    });

    it('keeps at most one decoder promise in flight across polling intervals', async () => {
        vi.useFakeTimers();
        let resolveDetection: ((observations: readonly OmrQrObservation[]) => void) | undefined;
        const detect = vi.fn(() => new Promise<readonly OmrQrObservation[]>((resolve) => { resolveDetection = resolve; }));
        const detector = createOmrQrCommandDetector({
            decoder: { supported: true, detect },
            now: () => 0,
        });
        detector.start(video, vi.fn());
        await vi.advanceTimersByTimeAsync(500);
        resolveDetection?.([commandValue('CAPTURE')]);
        await Promise.resolve();
        await Promise.resolve();
        await vi.advanceTimersByTimeAsync(100);
        expect(detect).toHaveBeenCalledTimes(2);
        resolveDetection?.([]);
        await Promise.resolve();
        await Promise.resolve();
        detector.dispose();
    });


    it('counts only successful absent observations and holds until removal and cooldown', async () => {
        vi.useFakeTimers();
        const now = { value: 0 };
        const onCommand = vi.fn();
        const onRemoved = vi.fn();
        const decoder = decoderWith([
            [commandValue('NEXT_PAGE')],
            [commandValue('NEXT_PAGE')],
            new Error('decoder failure'),
            [], [], [], [],
            ...Array.from({ length: 12 }, () => []),
        ]);
        const detector = createOmrQrCommandDetector({ decoder, now: () => now.value });
        detector.start(video, onCommand, onRemoved);

        await observe(now);
        await observe(now);
        expect(onCommand).toHaveBeenCalledTimes(1);
        await observe(now);
        expect(onRemoved).not.toHaveBeenCalled();
        await observe(now);
        await observe(now);
        await observe(now);
        expect(onRemoved).not.toHaveBeenCalled();
        await observe(now);
        expect(onRemoved).not.toHaveBeenCalled();
        for (let observation = 0; observation < 10; observation += 1) await observe(now);
        expect(onRemoved).toHaveBeenCalledOnce();
        expect(onRemoved).toHaveBeenCalledWith({ observedAt: 1700, sequence: 16 });
        detector.dispose();
    });

    it('preserves a consumed card latch across pauses so a held card cannot retrigger', async () => {
        vi.useFakeTimers();
        const now = { value: 0 };
        const onCommand = vi.fn();
        const detector = createOmrQrCommandDetector({
            decoder: decoderWith(Array.from({ length: 8 }, () => [commandValue('CAPTURE')])),
            now: () => now.value,
        });
        detector.start(video, onCommand);
        await observe(now);
        await observe(now);
        expect(onCommand).toHaveBeenCalledTimes(1);
        detector.pause();
        detector.start(video, onCommand);
        for (let observation = 0; observation < 5; observation += 1) await observe(now);
        expect(onCommand).toHaveBeenCalledTimes(1);
        detector.dispose();
    });

    it('keeps visual cues available when speech synthesis throws', () => {
        const visualCue = vi.fn();
        const speechSynthesis = { cancel: vi.fn(), speak: vi.fn(() => { throw new Error('speech blocked'); }) };
        vi.stubGlobal('window', {
            localStorage: { getItem: () => null, setItem: vi.fn() },
            speechSynthesis,
            SpeechSynthesisUtterance: class { constructor(public text: string) {} },
        });
        const cues = createOmrVoiceCues({ enabled: true, onVisualCue: visualCue });
        const cue = { cueId: 'accepted-op-1', operationId: 'op-1', name: 'accepted' as const };

        expect(() => cues.emit(cue)).not.toThrow();
        expect(visualCue).toHaveBeenCalledWith(cue);
        expect(speechSynthesis.speak).toHaveBeenCalledTimes(1);
        cues.dispose();
    });
    it('uses neutral rejection voice instead of blaming the image', () => {
        const spoken: string[] = [];
        const speechSynthesis = { cancel: vi.fn(), speak: vi.fn((utterance: { text: string }) => { spoken.push(utterance.text); }) };
        vi.stubGlobal('window', {
            localStorage: { getItem: () => null, setItem: vi.fn() },
            speechSynthesis,
            SpeechSynthesisUtterance: class { constructor(public text: string) {} },
        });
        const cues = createOmrVoiceCues({ enabled: true });
        cues.emit({ cueId: 'rejected-op', operationId: 'op-rejected', name: 'rejected' });
        cues.emit({ cueId: 'session-op', operationId: 'op-session', name: 'session_expired' });
        cues.emit({ cueId: 'attention-op', operationId: 'op-attention', name: 'capture_attention' });

        expect(spoken[0]).toMatch(/not accepted|rejected/i);
        expect(spoken[1]).toMatch(/session|sign in/i);
        expect(spoken[2]).toMatch(/follow.up|attention|review/i);
        expect(spoken.join(' ')).not.toMatch(/review (the )?image/i);
        cues.dispose();
    });

    it('skips late countdown speech after operation replacement', () => {
        const spoken: string[] = [];
        const speechSynthesis = { cancel: vi.fn(), speak: vi.fn((utterance: { text: string }) => { spoken.push(utterance.text); }) };
        vi.stubGlobal('window', {
            localStorage: { getItem: () => null, setItem: vi.fn() },
            speechSynthesis,
            SpeechSynthesisUtterance: class { constructor(public text: string) {} },
        });
        const cues = createOmrVoiceCues({ enabled: true });
        cues.emit({ cueId: 'op-1-countdown-3', operationId: 'op-1', name: 'countdown', tick: 3 });
        cues.emit({ cueId: 'op-2-ready', operationId: 'op-2', name: 'ready' });
        cues.emit({ cueId: 'op-1-countdown-2-late', operationId: 'op-1', name: 'countdown', tick: 2 });

        expect(speechSynthesis.speak).toHaveBeenCalledTimes(2);
        expect(spoken).toEqual(['3', 'Ready to capture.']);
        cues.dispose();
    });
});
