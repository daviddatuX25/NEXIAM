import { describe, expect, it } from 'vitest';
import { newIdempotencyKey } from '../../resources/js/lib/utils.js';

const UUID_RE = /^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/;

describe('newIdempotencyKey', () => {
    it('returns a unique RFC 4122 v4 UUID in a non-secure-context fallback', () => {
        const realCrypto = globalThis.crypto;
        try {
            // Simulate a LAN-IP / older-browser context: no randomUUID.
            Object.defineProperty(globalThis, 'crypto', {
                value: { getRandomValues: (bytes) => bytes.map(() => Math.floor(Math.random() * 256)) },
                configurable: true,
            });

            const keys = Array.from({ length: 50 }, () => newIdempotencyKey());
            expect(keys.every((key) => UUID_RE.test(key))).toBe(true);
            expect(new Set(keys).size).toBe(50);
        } finally {
            Object.defineProperty(globalThis, 'crypto', { value: realCrypto, configurable: true });
        }
    });

    it('uses crypto.randomUUID when available (secure context)', () => {
        const realCrypto = globalThis.crypto;
        try {
            Object.defineProperty(globalThis, 'crypto', {
                value: { randomUUID: () => '11111111-1111-4111-8111-111111111111', getRandomValues: () => [] },
                configurable: true,
            });
            expect(newIdempotencyKey()).toBe('11111111-1111-4111-8111-111111111111');
        } finally {
            Object.defineProperty(globalThis, 'crypto', { value: realCrypto, configurable: true });
        }
    });

    it('falls back to Math.random when no crypto APIs exist at all', () => {
        const realCrypto = globalThis.crypto;
        try {
            Object.defineProperty(globalThis, 'crypto', { value: undefined, configurable: true });
            const keys = Array.from({ length: 20 }, () => newIdempotencyKey());
            expect(keys.every((key) => UUID_RE.test(key))).toBe(true);
            expect(new Set(keys).size).toBe(20);
        } finally {
            Object.defineProperty(globalThis, 'crypto', { value: realCrypto, configurable: true });
        }
    });
});
