import { describe, expect, it } from 'vitest';
import {
    XIAM_CONTRACT_VERSION,
    isXiamSessionView,
    xiamShouldMount,
    resolveXiamSession,
    type XiamSessionView,
} from '../../resources/js/types/xiam';

function sample(
    availability: XiamSessionView['availability'],
): XiamSessionView {
    return {
        contract_version: XIAM_CONTRACT_VERSION,
        surface: { key: 'staff', version: 1 },
        context_epoch: 'epoch-1',
        page_pack: { key: 'staff.workspace.home@1', version: 1 },
        publication_version: null,
        availability,
        active_mode: 'inquiry',
        interaction_modes: [
            { key: 'inquiry', label: 'Inquiry' },
            { key: 'assistant', label: 'Assistant' },
        ],
        disclosure: {
            key: 'xiam.foundation.disclosure',
            version: 1,
            text: 'Xiam is an assistant.',
        },
        conversation: { id: null, run_id: null, cursor: 0 },
        usage: {
            state: 'available',
            used: 0,
            limit: 50,
            remaining: 50,
            credit_balance: 0,
            unit: 'asks',
            period: 'day',
            resets_at: null,
        },
        proposals: [],
        manual_fallbacks: [],
    };
}

describe('XiamSessionView mount matrix', () => {
    it('recognizes contract version 2 sessions', () => {
        expect(XIAM_CONTRACT_VERSION).toBe('2');
        expect(isXiamSessionView(sample('degraded'))).toBe(true);
        expect(isXiamSessionView({ enabled: true })).toBe(false);
    });

    it('resolves nested transitional share.session', () => {
        const session = sample('inquiry_only');
        expect(resolveXiamSession({ enabled: true, session })).toEqual(session);
    });

    it('mounts degraded and inquiry_only and hides hidden', () => {
        expect(xiamShouldMount(sample('degraded'))).toBe(true);
        expect(xiamShouldMount(sample('inquiry_only'))).toBe(true);
        expect(xiamShouldMount(sample('quota_exhausted'))).toBe(true);
        expect(xiamShouldMount(sample('suspended'))).toBe(true);
        expect(xiamShouldMount(sample('hidden'))).toBe(false);
        expect(xiamShouldMount(null)).toBe(false);
    });
    it('preserves unlimited and zero allowance states without a client-side limit default', () => {
        const unlimited = sample('assistant_available');
        unlimited.usage = {
            ...unlimited.usage!,
            state: 'available',
            used: 7,
            limit: -1,
            remaining: null,
            credit_balance: 3,
            period: 'lifetime',
            resets_at: null,
        };
        const zeroAllowance = sample('quota_exhausted');
        zeroAllowance.usage = {
            ...zeroAllowance.usage!,
            state: 'exhausted',
            used: 0,
            limit: 0,
            remaining: 0,
            credit_balance: 0,
            period: 'day',
        };

        expect(resolveXiamSession({ session: unlimited })?.usage).toMatchObject({
            limit: -1,
            remaining: null,
            credit_balance: 3,
            period: 'lifetime',
        });
        expect(resolveXiamSession({ session: zeroAllowance })?.usage).toMatchObject({
            state: 'exhausted',
            limit: 0,
            remaining: 0,
        });
    });
});
