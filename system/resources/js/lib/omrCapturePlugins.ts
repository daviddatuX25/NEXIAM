import type { OmrCaptureCue } from './omrCaptureProtocol';
import { createOmrQrDecoder, type OmrQrDecoder, type OmrQrObservation } from './omrQrDecoder';
export type { OmrQrDecoder, OmrQrObservation };

const VOICE_CUES_STORAGE_KEY = 'nexiam.omr.voice-cues.v1';
const QR_COMMAND_PREFIX = 'NEXIAM:OMR:CMD:1:';
export const OMR_QR_COMMAND_PAYLOADS: Readonly<Record<OmrQrCommand, string>> = {
    CAPTURE: 'NEXIAM:OMR:CMD:1:CAPTURE',
    NEXT_PAGE: 'NEXIAM:OMR:CMD:1:NEXT_PAGE',
    NEXT_SET: 'NEXIAM:OMR:CMD:1:NEXT_SET',
    REDO_LAST: 'NEXIAM:OMR:CMD:1:REDO_LAST',
};
const QR_COMMANDS: Record<OmrQrCommand, true> = {
    CAPTURE: true,
    NEXT_PAGE: true,
    NEXT_SET: true,
    REDO_LAST: true,
};
const VOICE_PHRASES: Record<OmrCaptureCue['name'], string> = {
    ready: 'Ready to capture.',
    remove_command_card: 'Remove command card.',
    hold_steady: 'Hold steady.',
    countdown: '',
    acquiring: 'Capturing image.',
    upload_pending: 'Uploading image.',
    accepted: 'Page accepted.',
    processing: 'Capture is processing; acceptance is pending.',
    rejected: 'Capture was not accepted. Check the on-screen status for the reason.',
    checking_previous_capture: 'Checking previous capture.',
    disconnected: 'Camera disconnected.',
    session_expired: 'Staff session expired. Follow the on-screen recovery instructions.',
    capture_attention: 'Capture receipt is confirmed, but follow-up work is needed. Check the on-screen status for the reason.',
};

export type OmrQrCommand = 'CAPTURE' | 'NEXT_PAGE' | 'NEXT_SET' | 'REDO_LAST';

export interface OmrVoiceCues {
    enabled: boolean;
    setEnabled(enabled: boolean): void;
    emit(cue: OmrCaptureCue): void;
    cancel(operationId?: string | null): void;
    captured(pageNo: number, nextPageNo?: number, eventId?: string): void;
    sheetComplete(eventId?: string): void;
    flaggedItemQueued(eventId?: string): void;
    holdSteady(): void;
    dispose(): void;
}

export type OmrQrCardRemoval = Readonly<{ observedAt: number; sequence: number }>;

export interface OmrQrCommandDetector {
    supported: boolean;
    start(video: HTMLVideoElement, onCommand: (command: OmrQrCommand) => void, onCardRemoved?: (removal: OmrQrCardRemoval) => void): void;
    pause(): void;
    resume(): void;
    stop(): void;
    dispose(): void;
}

export type OmrQrCommandDetectorOptions = Readonly<{
    decoder?: OmrQrDecoder;
    now?: () => number;
    onError?: (error: unknown) => void;
}>;

export function parseOmrQrCommand(payload: string | null | undefined): OmrQrCommand | null {
    if (!payload || payload.length > 64 || !payload.startsWith(QR_COMMAND_PREFIX)) return null;
    const command = payload.slice(QR_COMMAND_PREFIX.length);
    return Object.hasOwn(QR_COMMANDS, command) ? command as OmrQrCommand : null;
}

function defaultVoicePreference(): boolean {
    try {
        return typeof window !== 'undefined' && window.localStorage.getItem(VOICE_CUES_STORAGE_KEY) === 'true';
    } catch {
        return false;
    }
}

export function createOmrVoiceCues(options: { enabled?: boolean; onVisualCue?: (cue: OmrCaptureCue) => void } = {}): OmrVoiceCues {
    let enabled = options.enabled ?? defaultVoicePreference();
    let currentOperationId: string | null = null;
    let activeSpeechCueId: string | null = null;
    let lastHoldSteadyAt = Number.NEGATIVE_INFINITY;
    let lastCountdownTick: number | null = null;
    let countdownClosed = false;
    const spokenEvents = new Set<string>();
    const obsoleteOperations = new Set<string>();

    function cancelSpeech(): void {
        try {
            if (typeof window !== 'undefined') window.speechSynthesis?.cancel();
        } catch {
            // Speech is optional; visual feedback remains authoritative.
        }
        activeSpeechCueId = null;
    }

    function speak(phrase: string, eventId?: string): void {
        if (!enabled || typeof window === 'undefined' || !phrase) return;
        if (eventId) {
            if (spokenEvents.has(eventId)) return;
            spokenEvents.add(eventId);
            if (spokenEvents.size > 128) {
                const oldest = spokenEvents.values().next().value;
                if (oldest !== undefined) spokenEvents.delete(oldest);
            }
        }
        try {
            const synthesis = window.speechSynthesis;
            const Utterance = window.SpeechSynthesisUtterance;
            if (!synthesis || typeof Utterance !== 'function') return;
            synthesis.cancel();
            const utterance = new Utterance(phrase);
            utterance.onend = () => {
                if (activeSpeechCueId === eventId) activeSpeechCueId = null;
            };
            utterance.onerror = () => {
                if (activeSpeechCueId === eventId) activeSpeechCueId = null;
            };
            activeSpeechCueId = eventId ?? null;
            synthesis.speak(utterance);
        } catch {
            // Synthesis failures never alter capture timing or visual state.
        }
    }

    return {
        get enabled() { return enabled; },
        setEnabled(next: boolean) {
            enabled = next;
            try {
                if (typeof window !== 'undefined') window.localStorage.setItem(VOICE_CUES_STORAGE_KEY, String(next));
            } catch {
                // Storage may be unavailable in private or restricted contexts.
            }
            if (!next) {
                countdownClosed = true;
                cancelSpeech();
            }
        },
        emit(cue: OmrCaptureCue) {
            options.onVisualCue?.(cue);
            if (cue.operationId && obsoleteOperations.has(cue.operationId)) return;
            if (cue.operationId !== currentOperationId) {
                cancelSpeech();
                if (currentOperationId) {
                    obsoleteOperations.add(currentOperationId);
                    if (obsoleteOperations.size > 128) {
                        const oldest = obsoleteOperations.values().next().value;
                        if (oldest !== undefined) obsoleteOperations.delete(oldest);
                    }
                }
                currentOperationId = cue.operationId;
                lastCountdownTick = null;
                countdownClosed = false;
            }
            if (cue.name === 'countdown') {
                if (countdownClosed || cue.tick === undefined || (lastCountdownTick !== null && cue.tick >= lastCountdownTick)) return;
                lastCountdownTick = cue.tick;
                speak(String(cue.tick), `${cue.operationId ?? cue.cueId}:countdown:${cue.tick}`);
                return;
            }
            if (cue.name === 'acquiring' || cue.name === 'upload_pending' || cue.name === 'accepted' || cue.name === 'processing' || cue.name === 'rejected' || cue.name === 'checking_previous_capture' || cue.name === 'disconnected' || cue.name === 'session_expired' || cue.name === 'capture_attention') {
                countdownClosed = true;
            }
            if (cue.name === 'hold_steady') {
                const at = typeof performance === 'undefined' ? Date.now() : performance.now();
                if (at - lastHoldSteadyAt < 5000) return;
                lastHoldSteadyAt = at;
            }
            if (countdownClosed) cancelSpeech();
            const eventId = cue.name === 'hold_steady'
                ? undefined
                : `${cue.operationId ?? cue.cueId}:${cue.name}`;
            speak(VOICE_PHRASES[cue.name], eventId);
        },
        cancel(operationId?: string | null) {
            if (operationId !== undefined && operationId !== currentOperationId) return;
            countdownClosed = true;
            if (currentOperationId) obsoleteOperations.add(currentOperationId);
            cancelSpeech();
        },
        captured(pageNo: number, nextPageNo?: number, eventId?: string) {
            speak(nextPageNo === undefined ? `Page ${pageNo} captured.` : `Page ${pageNo} captured. Place Page ${nextPageNo}.`, eventId);
        },
        sheetComplete(eventId?: string) { speak('Sheet complete.', eventId); },
        flaggedItemQueued(eventId?: string) { speak('Flagged item queued.', eventId); },
        holdSteady() {
            const at = typeof performance === 'undefined' ? Date.now() : performance.now();
            if (at - lastHoldSteadyAt < 5000) return;
            lastHoldSteadyAt = at;
            speak('Hold steady.');
        },
        dispose() {
            cancelSpeech();
            spokenEvents.clear();
            obsoleteOperations.clear();
            currentOperationId = null;
        },
    };
}

export function createOmrQrCommandDetector(options: OmrQrCommandDetectorOptions = {}): OmrQrCommandDetector {
    const decoder = options.decoder ?? createOmrQrDecoder();
    const now = options.now ?? (() => typeof performance === 'undefined' ? Date.now() : performance.now());
    let video: HTMLVideoElement | null = null;
    let onCommand: ((command: OmrQrCommand) => void) | null = null;
    let onCardRemoved: ((removal: OmrQrCardRemoval) => void) | null = null;
    let timer: ReturnType<typeof setInterval> | null = null;
    let generation = 0;
    let observationSequence = 0;
    let inFlight = false;
    let candidate: OmrQrCommand | null = null;
    let confirmations = 0;
    let latched = false;
    let absentDetections = 0;
    let absentSince: number | null = null;
    let latchedAt = Number.NEGATIVE_INFINITY;

    function clearObservation(): void {
        candidate = null;
        confirmations = 0;
        absentDetections = 0;
        absentSince = null;
    }

    function clearTimer(): void {
        if (timer !== null) {
            clearInterval(timer);
            timer = null;
        }
    }

    function poll(): void {
        if (timer === null && video && decoder.supported) {
            timer = setInterval(() => void detect(), 100);
        }
    }

    async function detect(): Promise<void> {
        if (!video || !onCommand || !decoder.supported || inFlight || (typeof document !== 'undefined' && document.hidden)) return;
        if (typeof HTMLMediaElement !== 'undefined' && video.readyState < HTMLMediaElement.HAVE_CURRENT_DATA) return;
        inFlight = true;
        const activeGeneration = generation;
        try {
            const observations = await decoder.detect(video);
            if (activeGeneration !== generation) return;
            const timestamp = now();
            observationSequence += 1;
            if (latched) {
                if (observations.length === 0) {
                    absentDetections += 1;
                    absentSince ??= timestamp;
                    if (absentDetections >= 3 && timestamp - absentSince >= 300 && timestamp - latchedAt >= 1500) {
                        latched = false;
                        clearObservation();
                        onCardRemoved?.({ observedAt: timestamp, sequence: observationSequence });
                    }
                } else {
                    absentDetections = 0;
                    absentSince = null;
                }
                return;
            }
            if (observations.length !== 1) {
                clearObservation();
                return;
            }
            const command = parseOmrQrCommand(observations[0].rawValue);
            if (!command) {
                clearObservation();
                return;
            }
            if (candidate === command) confirmations += 1;
            else {
                candidate = command;
                confirmations = 1;
            }
            if (confirmations >= 2) {
                latched = true;
                latchedAt = timestamp;
                absentDetections = 0;
                absentSince = null;
                onCommand(command);
            }
        } catch (error) {
            options.onError?.(error);
        } finally {
            inFlight = false;
        }
    }

    function pause(): void {
        generation += 1;
        clearTimer();
        clearObservation();
    }

    function stop(): void {
        pause();
        video = null;
        onCommand = null;
        onCardRemoved = null;
    }

    return {
        get supported() { return decoder.supported; },
        start(nextVideo, commandCallback, removedCallback) {
            pause();
            video = nextVideo;
            onCommand = commandCallback;
            onCardRemoved = removedCallback ?? null;
            poll();
        },
        pause,
        resume: poll,
        stop,
        dispose() {
            stop();
            decoder.dispose?.();
        },
    };
}
