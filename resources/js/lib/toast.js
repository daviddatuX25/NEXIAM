// Native Svelte 5 Toast notification utility
// Uses a writable store + toast component pattern
// Sound: 'action' tier for success/error, 'background' tier for info/message

import { writable } from 'svelte/store';
import { playChime } from '@/lib/notification-sound.js';

// Toast store: array of { id, message, type, duration }
function createToastStore() {
    const { subscribe, update } = writable([]);

    return {
        subscribe,
        show(message, type = 'info', duration = 4000) {
            const id = Math.random().toString(36).slice(2) + Date.now().toString(36);
            update(toasts => [...toasts, { id, message, type, duration }]);

            if (duration > 0) {
                setTimeout(() => {
                    this.dismiss(id);
                }, duration);
            }

            return id;
        },
        dismiss(id) {
            update(toasts => toasts.filter(t => t.id !== id));
        },
        clear() {
            update(() => []);
        }
    };
}

export const toasts = createToastStore();

// Convenience functions: 'action' tier for direct user actions, 'background' for poll-based
export function success(message, options = {}) {
    playChime('action');
    return toasts.show(message, 'success', options.duration ?? 4000);
}

export function error(message, options = {}) {
    playChime('action');
    return toasts.show(message, 'error', options.duration ?? 4000);
}

export function info(message, options = {}) {
    playChime('background');
    return toasts.show(message, 'info', options.duration ?? 4000);
}

export function message(message, options = {}) {
    playChime('background');
    return toasts.show(message, 'info', options.duration ?? 4000);
}

export function silent(message, options = {}) {
    return toasts.show(message, 'info', 0); // 0 = no auto-dismiss, no sound
}

export const dismiss = (id) => toasts.dismiss(id);
