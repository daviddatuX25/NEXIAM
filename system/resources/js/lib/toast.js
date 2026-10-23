// Native Svelte 5 toast notification utility with SND01 Web Audio feedback.
// Uses a writable store + <ToastManager> component pattern.

import { writable } from 'svelte/store';
import { sound } from './sound.js';

const DEFAULT_DURATIONS = {
    success: 6000,
    info: 6000,
    warning: 10000,
    error: 12000,
};
const MAX_VISIBLE_TOASTS = 4;
const timers = new Map();

function createToastId() {
    return Math.random().toString(36).slice(2) + Date.now().toString(36);
}

function clearTimer(id) {
    const timer = timers.get(id);
    if (timer === undefined) {
        return;
    }

    clearTimeout(timer);
    timers.delete(id);
}

function scheduleDismiss(id, duration, dismiss) {
    clearTimer(id);

    if (duration <= 0) {
        return;
    }

    timers.set(
        id,
        setTimeout(() => {
            timers.delete(id);
            dismiss(id);
        }, duration),
    );
}

function normalizeOptions(options) {
    return options && typeof options === 'object' ? options : {};
}

// Toast store: array of { id, message, type, duration, dedupeKey? }
function createToastStore() {
    const { subscribe, update } = writable([]);

    function dismissToast(id) {
        clearTimer(id);
        update((toasts) => toasts.filter((toast) => toast.id !== id));
    }

    function clearToasts() {
        update((toasts) => {
            toasts.forEach((toast) => clearTimer(toast.id));
            return [];
        });
    }

    return {
        subscribe,
        show(message, type = 'info', duration, options = {}) {
            const normalizedOptions = normalizeOptions(options);
            const resolvedDuration =
                duration ?? DEFAULT_DURATIONS[type] ?? DEFAULT_DURATIONS.info;
            const hasDedupeKey =
                normalizedOptions.dedupeKey !== undefined &&
                normalizedOptions.dedupeKey !== null;
            const dedupeKey = normalizedOptions.dedupeKey;
            const candidate = {
                id: createToastId(),
                message,
                type,
                duration: resolvedDuration,
            };

            if (hasDedupeKey) {
                candidate.dedupeKey = dedupeKey;
            }

            let resultId = candidate.id;
            let shouldSchedule = true;
            let shouldPlaySound = false;

            update((currentToasts) => {
                const existingIndex = currentToasts.findIndex((toast) =>
                    hasDedupeKey
                        ? toast.dedupeKey === dedupeKey
                        : toast.type === type && toast.message === message,
                );

                if (existingIndex !== -1) {
                    const existingToast = currentToasts[existingIndex];
                    resultId = existingToast.id;

                    // Identical un-keyed notifications are already represented.
                    // Keep their original timer rather than extending the
                    // notification every time a poll or event repeats.
                    if (!hasDedupeKey) {
                        shouldSchedule = false;
                        return currentToasts;
                    }

                    // A keyed notification is an upsert: preserve its id so a
                    // caller can still dismiss the logical notification after
                    // its message or severity changes.
                    shouldPlaySound =
                        existingToast.type !== type || existingToast.message !== message;
                    clearTimer(existingToast.id);
                    const replacement = [...currentToasts];
                    replacement[existingIndex] = {
                        ...candidate,
                        id: existingToast.id,
                    };
                    return replacement;
                }

                shouldPlaySound = true;
                const nextToasts = [...currentToasts, candidate];
                const overflow = nextToasts.length - MAX_VISIBLE_TOASTS;

                if (overflow <= 0) {
                    return nextToasts;
                }

                nextToasts.slice(0, overflow).forEach((toast) => clearTimer(toast.id));
                return nextToasts.slice(overflow);
            });

            if (shouldPlaySound) {
                if (type === 'success') {
                    sound.success?.();
                } else if (type === 'error') {
                    sound.error?.();
                } else {
                    sound.press?.();
                }
            }

            if (shouldSchedule) {
                scheduleDismiss(resultId, resolvedDuration, dismissToast);
            }

            return resultId;
        },
        dismiss: dismissToast,
        clear: clearToasts,
    };
}


export const toasts = createToastStore();

export function success(message, options = {}) {
    const normalizedOptions = normalizeOptions(options);
    return toasts.show(message, 'success', normalizedOptions.duration, normalizedOptions);
}

export function error(message, options = {}) {
    const normalizedOptions = normalizeOptions(options);
    return toasts.show(message, 'error', normalizedOptions.duration, normalizedOptions);
}

export function info(message, options = {}) {
    const normalizedOptions = normalizeOptions(options);
    return toasts.show(message, 'info', normalizedOptions.duration, normalizedOptions);
}

export function warning(message, options = {}) {
    const normalizedOptions = normalizeOptions(options);
    return toasts.show(message, 'warning', normalizedOptions.duration, normalizedOptions);
}

export function silent(message, options = {}) {
    const normalizedOptions = normalizeOptions(options);
    return toasts.show(message, 'info', normalizedOptions.duration ?? 0, normalizedOptions);
}

export const dismiss = (id) => toasts.dismiss(id);

export const toastSuccess = success;
export const toastError = error;
