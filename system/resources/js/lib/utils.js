import { clsx } from 'clsx';
import { twMerge } from 'tailwind-merge';

/**
 * Combines class names with Tailwind CSS merge support
 * @param {...string} inputs - Class names to combine
 * @returns {string} Merged class names
 */
export function cn(...inputs) {
    return twMerge(clsx(inputs));
}

/**
 * Idempotency key that works in ANY browser context.
 * `crypto.randomUUID()` only exists in secure contexts (https / localhost)
 * and modern browsers — on LAN-IP or older browsers it is undefined and
 * crashes the page. Fall back to a timestamp+random key.
 */
export function newIdempotencyKey() {
    if (typeof crypto !== 'undefined' && typeof crypto.randomUUID === 'function') {
        return crypto.randomUUID();
    }
    return 'key-' + Date.now().toString(36) + '-' + Math.random().toString(36).slice(2, 12);
}
