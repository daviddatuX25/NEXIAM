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
 * Idempotency key that works in ANY browser context and is valid for UUID
 * server columns (`omr_capture_sheets.client_capture_id` is a Postgres
 * `uuid`).
 * `crypto.randomUUID()` only exists in secure contexts (https / localhost)
 * and modern browsers — on LAN-IP or older browsers it is undefined and
 * crashes the page. Fall back to an RFC 4122 v4 UUID from
 * `crypto.getRandomValues` (or Math.random as a last resort).
 */
export function newIdempotencyKey() {
    if (typeof crypto !== 'undefined' && typeof crypto.randomUUID === 'function') {
        return crypto.randomUUID();
    }
    const bytes = typeof crypto !== 'undefined' && typeof crypto.getRandomValues === 'function'
        ? crypto.getRandomValues(new Uint8Array(16))
        : Array.from({ length: 16 }, () => Math.floor(Math.random() * 256));
    bytes[6] = (bytes[6] & 0x0f) | 0x40; // version 4
    bytes[8] = (bytes[8] & 0x3f) | 0x80; // variant 10
    const hex = Array.from(bytes, (byte) => byte.toString(16).padStart(2, '0')).join('');
    return `${hex.slice(0, 8)}-${hex.slice(8, 12)}-${hex.slice(12, 16)}-${hex.slice(16, 20)}-${hex.slice(20)}`;
}

/**
 * Returns the CSRF token from meta tags, cookies, or window properties.
 */
export function csrfToken() {
    if (typeof document === 'undefined') {
        return '';
    }
    const meta = document.querySelector('meta[name="csrf-token"]');
    if (meta && meta instanceof HTMLMetaElement && meta.content) {
        return meta.content;
    }
    const match = document.cookie.match(/(?:^|;\s*)XSRF-TOKEN=([^;]+)/);
    if (match && match[1]) {
        try {
            return decodeURIComponent(match[1]);
        } catch {
            return match[1];
        }
    }
    if (typeof window !== 'undefined' && window._token) {
        return window._token;
    }
    return '';
}
