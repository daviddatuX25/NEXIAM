import { describe, expect, it } from 'vitest';
import { createNoticeMessageComposer, type MessageComposerProps, type MessageState } from '../../resources/js/lib/noticeMessageComposer';
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

    it('uses the safe UUID fallback for the default notice composer key factory', async () => {
        const realCrypto = globalThis.crypto;
        const state: MessageState = {
            subject: 'Exam notice',
            body: 'Please review the schedule.',
            action_label: null,
            action_route: null,
            email_blueprint: null,
            document_revision_id: null,
        };
        const props: MessageComposerProps = {
            family: {
                id: 1,
                domain: 'Examination',
                slug: 'exam-notice',
                name: 'Exam notice',
                active_version: 1,
                family_revision: 1,
            },
            state,
            draft: null,
            base_version: 1,
            content_sha256: null,
            output_digests: { email_sha256: 'email', portal_sha256: 'portal' },
            tokens: [],
            assets: [],
            sample_context: {},
            endpoints: { create: '/drafts', return_url: '/notices' },
        };
        let idempotencyKey: unknown;
        const fetcher = async (_url: RequestInfo | URL, init?: RequestInit) => {
            idempotencyKey = JSON.parse(String(init?.body)).idempotency_key;
            const payload = JSON.parse(String(init?.body));
            return new Response(
                JSON.stringify({
                    operation_id: 'draft-op',
                    family_revision: 2,
                    content_sha256: 'saved-content',
                    draft: {
                        id: 1,
                        lock_version: 1,
                        state: payload.state,
                        updated_at: 'now',
                    },
                }),
                { status: 200 },
            );
        };

        try {
            Object.defineProperty(globalThis, 'crypto', {
                value: {
                    getRandomValues: (bytes) => bytes.map((_, index) => index),
                },
                configurable: true,
            });

            const composer = createNoticeMessageComposer(props, {
                fetch: fetcher as typeof fetch,
            });
            await composer.save();

            expect(idempotencyKey).toMatch(UUID_RE);
            composer.dispose();
        } finally {
            Object.defineProperty(globalThis, 'crypto', {
                value: realCrypto,
                configurable: true,
            });
        }
    });
    it('returns UUID-shaped keys when randomUUID is absent', () => {
        const realCrypto = globalThis.crypto;
        try {
            Object.defineProperty(globalThis, 'crypto', {
                value: {
                    getRandomValues: (bytes) => bytes.map((_, index) => index),
                },
                configurable: true,
            });

            expect(newIdempotencyKey()).toMatch(UUID_RE);
        } finally {
            Object.defineProperty(globalThis, 'crypto', {
                value: realCrypto,
                configurable: true,
            });
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
