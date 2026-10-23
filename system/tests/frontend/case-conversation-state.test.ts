import { describe, expect, it } from 'vitest';
import {
    capturePendingCaseMessage,
    caseConversationBackoffMs,
    caseConversationDraftKey,
    caseConversationNeedsNavigationConfirmation,
    mergeCaseMessages,
    nextCaseMessageCursor,
    type CaseConversationMessage,
} from '../../resources/js/lib/case-conversation';

function message(id: string, sequence: string): CaseConversationMessage {
    return {
        id,
        sequence,
        sender: { display_name: `Sender ${id}`, is_self: false },
        body: `Message ${id}`,
        created_at: '2026-10-05T00:00:00Z',
    };
}

describe('case conversation pending message identity', () => {
    it('captures trimmed content once and preserves that body/key pair for uncertain replay', () => {
        const pending = capturePendingCaseMessage(
            '  Keep this body  \n',
            ' key-exactly-as-issued ',
        );

        expect(pending).toEqual({
            body: 'Keep this body',
            idempotencyKey: ' key-exactly-as-issued ',
        });
        expect(pending).not.toBeNull();
        const uncertainRetry = pending;
        expect(uncertainRetry).toBe(pending);
        expect(uncertainRetry?.idempotencyKey).toBe(' key-exactly-as-issued ');
        expect(uncertainRetry?.body).toBe('Keep this body');
    });

    it.each(['', ' \n\t '])('rejects blank message bodies (%j)', (body) => {
        expect(capturePendingCaseMessage(body, 'key')).toBeNull();
    });
});

describe('case and office conversation draft namespaces', () => {
    it('separates exact attempt draft identities by domain', () => {
        expect(
            caseConversationDraftKey('principal', 'attempt', 'thread', 'case'),
        ).not.toBe(
            caseConversationDraftKey(
                'principal',
                'attempt',
                'thread',
                'office',
            ),
        );
    });
});

describe('case conversation navigation protection', () => {
    it('guards dirty ordinary visits but exempts logout, authentication, and access loss', () => {
        expect(
            caseConversationNeedsNavigationConfirmation(
                '/consultation/applicants/2',
                true,
                true,
            ),
        ).toBe(true);
        expect(
            caseConversationNeedsNavigationConfirmation('/staff/logout', true, true),
        ).toBe(false);
        expect(
            caseConversationNeedsNavigationConfirmation('/staff/login', true, true),
        ).toBe(false);
        expect(
            caseConversationNeedsNavigationConfirmation(
                '/consultation/applicants/2',
                true,
                false,
            ),
        ).toBe(false);
        expect(
            caseConversationNeedsNavigationConfirmation(
                '/consultation/applicants/2',
                false,
                true,
            ),
        ).toBe(false);
    });
});

describe('case conversation ordered message merge', () => {
    it('deduplicates stable message IDs and orders decimal sequences beyond safe integers', () => {
        const existing = [
            message('large', '9007199254740993'),
            message('first', '9007199254740992'),
        ];
        const duplicateWithDifferentPayload = message(
            'first',
            '9007199254740994',
        );
        const incoming = [
            message('last', '9007199254740995'),
            duplicateWithDifferentPayload,
        ];

        expect(mergeCaseMessages(existing, incoming)).toEqual([
            message('first', '9007199254740992'),
            message('large', '9007199254740993'),
            message('last', '9007199254740995'),
        ]);
        expect(mergeCaseMessages(existing, incoming)).toHaveLength(3);
    });
});

describe('case conversation cursor progression', () => {
    it('advances to the last returned row and preserves its cursor for an empty batch', () => {
        const batch = [message('one', '12'), message('two', '13')];

        expect(nextCaseMessageCursor('11', batch)).toBe('13');
        expect(nextCaseMessageCursor('11', [])).toBe('11');
    });

    it('uses the returned sequence rather than a newer latest hint when a batch overflows', () => {
        const returnedBatch = [message('one', '25'), message('two', '26')];
        const latestSequenceHint = '99';

        expect(nextCaseMessageCursor('24', returnedBatch)).toBe('26');
        expect(nextCaseMessageCursor('24', returnedBatch)).not.toBe(
            latestSequenceHint,
        );
    });
});

describe('case conversation polling backoff', () => {
    it('uses the bounded 10/20/40/60-second failure schedule', () => {
        expect(
            [0, 1, 2, 3, 4, 5].map((failures) =>
                caseConversationBackoffMs(failures),
            ),
        ).toEqual([10_000, 10_000, 20_000, 40_000, 60_000, 60_000]);
    });

    it('honors a valid Retry-After deadline as a lower bound without capping it at sixty seconds', () => {
        expect(caseConversationBackoffMs(1, 15_000)).toBe(15_000);
        expect(caseConversationBackoffMs(1, 5_000)).toBe(10_000);
        expect(caseConversationBackoffMs(1, 120_000)).toBe(120_000);
        expect(caseConversationBackoffMs(1, -1)).toBe(10_000);
        expect(caseConversationBackoffMs(1, Number.POSITIVE_INFINITY)).toBe(
            10_000,
        );
    });
});
