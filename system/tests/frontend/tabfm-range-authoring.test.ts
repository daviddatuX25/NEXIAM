import { applyCommonRange, authoredRangeError, benchmarkStatusLabel, cloneTabFmContent, hasIncompleteAuthoredRange, proposeCommonRange, setAuthoredRange, setPreference, type TabFmContent } from '../../resources/js/lib/tabFmRangeAuthoring';
import { describe, expect, it } from 'vitest';
function content(): TabFmContent {
    return {
        tiers: {
            '1': { values: { gwa: 93, shs_strand: 'STEM' } },
            '2': { values: { gwa: 86, shs_strand: 'TVL-ICT' } },
            '3': { values: { gwa: 81, shs_strand: 'GAS' } },
            '4': { values: { gwa: 77, shs_strand: 'HUMSS', riasec_i: 1 } },
            '5': { values: { gwa: 72, shs_strand: 'ABM', riasec_i: 0 } },
        },
    };
}

describe('TabFM range authoring state', () => {
    it('prepares a reviewed local proposal without mutating content, then records exact before/after provenance on apply', () => {
        const initial = content();
        const proposal = proposeCommonRange(initial, 'gwa', [80, 92]);
        expect(proposeCommonRange(initial, 'gwa', [80, 92], { min_value: 85, max_value: 95 })).toBeNull();

        expect(proposal?.rows).toEqual([
            { band: 'honors', level: '1', before: 93, after: 92 },
            { band: 'regular', level: '2', before: 86, after: 86 },
            { band: 'cutoff', level: '3', before: 81, after: 80 },
        ]);
        expect(initial.tiers['1'].values.gwa).toBe(93);
        expect(initial.authoring).toBeUndefined();
        expect(proposeCommonRange(initial, 'gwa', [null, 92])).toBeNull();

        const applied = applyCommonRange(initial, proposal!);
        expect(applied.authoring?.version).toBe(2);
        expect(applied.authoring?.preparation?.gwa).toEqual({
            version: 1,
            common_range: [80, 92],
            before: { honors: null, regular: null, cutoff: null },
            applied_values: { honors: 92, regular: 86, cutoff: 80 },
            reviewed: true,
        });
        expect(applied.tiers['4'].values).toEqual(initial.tiers['4'].values);
        expect(applied.tiers['5'].values).toEqual(initial.tiers['5'].values);
        expect(cloneTabFmContent(applied)?.authoring?.preparation).toEqual(applied.authoring?.preparation);
    });
    it('starts range authoring without fabricating endpoints and retains point values', () => {
        const initial = content();
        const changed = setAuthoredRange(initial, 'honors', 'gwa', 0, '90');

        expect(changed.authoring?.ranges.honors.gwa).toEqual([90, null]);
        expect(changed.tiers['1'].values.gwa).toBeNull();
        expect(changed.tiers['2'].values.gwa).toBe(86);
        expect(changed.tiers['3'].values.gwa).toBe(81);
        expect(hasIncompleteAuthoredRange(changed)).toBe(true);
        expect(hasIncompleteAuthoredRange(setAuthoredRange(changed, 'honors', 'gwa', 0, ''))).toBe(false);
        expect(initial.tiers['1'].values.gwa).toBe(93);
    });

    it('maps complete bands only to their approved examples and rounds midpoint ties away from zero', () => {
        let next = setAuthoredRange(content(), 'honors', 'gwa', 0, '90');
        next = setAuthoredRange(next, 'honors', 'gwa', 1, '98');
        next = setAuthoredRange(next, 'regular', 'gwa', 0, '-1.01');
        next = setAuthoredRange(next, 'regular', 'gwa', 1, '-1.00');
        next = setAuthoredRange(next, 'cutoff', 'gwa', 0, '75');
        next = setAuthoredRange(next, 'cutoff', 'gwa', 1, '82');

        expect(next.tiers['1'].values.gwa).toBe(98);
        expect(next.tiers['2'].values.gwa).toBe(-1.01);
        expect(next.tiers['3'].values.gwa).toBe(75);
        expect(next.tiers['4'].values).toEqual(content().tiers['4'].values);
        expect(next.tiers['5'].values).toEqual(content().tiers['5'].values);
        const reopened = cloneTabFmContent(next);
        expect(reopened?.authoring?.ranges).toEqual(next.authoring?.ranges);
        expect(reopened?.tiers).toEqual(next.tiers);
    });
    it('normalizes accepted numeric-string ranges when reopening saved authoring', () => {
        const reopened = cloneTabFmContent({
            tiers: { '1': { values: {} }, '2': { values: {} }, '3': { values: {} }, '4': { values: {} }, '5': { values: {} } },
            authoring: {
                version: 1,
                ranges: { honors: { gwa: ['90', '96'] }, regular: { gwa: ['85.5', '92.5'] }, cutoff: { gwa: [null, null] } },
                preferred_strands: null,
                interests: null,
            },
        });

        expect(reopened?.authoring?.ranges.honors.gwa).toEqual([90, 96]);
        expect(reopened?.authoring?.ranges.regular.gwa).toEqual([85.5, 92.5]);
        expect(reopened?.authoring?.ranges.cutoff.gwa).toEqual([null, null]);
        expect(reopened?.authoring?.ranges.cutoff).not.toHaveProperty('shs_math');
        const recalculated = setAuthoredRange(reopened!, 'regular', 'gwa', 0, '86');
        expect(recalculated.tiers['2'].values.gwa).toBe(89.25);
        expect(authoredRangeError(reopened?.authoring?.ranges.regular.gwa)).toBeNull();
    });


    it('retains authored ranges and distinguishes an unset preference from explicit empty selections', () => {
        let next = setPreference(content(), 'preferred_strands', 'STEM', true);
        next = setPreference(next, 'preferred_strands', 'STEM', false);
        next = setPreference(next, 'interests', 'riasec_i', true);
        const reopened = cloneTabFmContent(next);

        expect(reopened?.authoring?.preferred_strands).toEqual([]);
        expect(reopened?.authoring?.interests).toEqual(['riasec_i']);
        expect(reopened?.tiers['4'].values.riasec_i).toBe(1);
        expect(reopened?.tiers['5'].values.riasec_i).toBe(0);
        expect(content().authoring).toBeUndefined();
    });

    it('flags invalid range ordering, bounds, and precision for field feedback', () => {
        expect(authoredRangeError([90, 80])).toBe('From must not be greater than To.');
        expect(authoredRangeError([-10000, 10000])).toBe('From must be between -9999.99 and 9999.99.');
        expect(authoredRangeError([1.234, 2])).toBe('From can have up to 2 decimal places.');
        expect(authoredRangeError([null, null])).toBeNull();
    });

    it('ensures changing common ranges edits only the three named anchors and leaves all dynamic fields, custom option tokens, and activity metadata unchanged', () => {
        const initial: TabFmContent = {
            tiers: {
                '1': { values: { gwa: 95, shs_strand: 'STEM', custom_math: 94, custom_cert: 'ADVANCED' } },
                '2': { values: { gwa: 88, shs_strand: 'TVL-MARITIME', custom_math: 85, custom_cert: 'INTERMEDIATE' } },
                '3': { values: { gwa: 80, shs_strand: 'ARTS-DESIGN', custom_math: 78, custom_cert: 'BASIC' } },
                '4': { values: { gwa: 76, shs_strand: 'SPORTS', custom_math: 72, custom_cert: 'ENTRY', riasec_i: 1 } },
                '5': { values: { gwa: 65, shs_strand: 'ABM', custom_math: 60, custom_cert: 'NONE', riasec_i: 0 } },
            },
            authoring: {
                version: 1,
                ranges: { honors: {}, regular: {}, cutoff: {} },
                preferred_strands: ['TVL-MARITIME', 'ARTS-DESIGN'],
                interests: ['riasec_i'],
                interest_activities: ['act_custom_maritime'],
                profile_activity_mapping: { '4': { reviewed: true, activity_ids: ['act_custom_maritime'] } },
            },
        };

        const proposal = proposeCommonRange(initial, 'gwa', [75, 92]);
        expect(proposal).not.toBeNull();
        expect(proposal?.rows).toEqual([
            { band: 'honors', level: '1', before: 95, after: 92 },
            { band: 'regular', level: '2', before: 88, after: 83.5 },
            { band: 'cutoff', level: '3', before: 80, after: 75 },
        ]);

        const applied = applyCommonRange(initial, proposal!);

        // Tiers 1-3: only gwa updated, all other dynamic fields and tokens preserved
        expect(applied.tiers['1'].values.gwa).toBe(92);
        expect(applied.tiers['1'].values.shs_strand).toBe('STEM');
        expect(applied.tiers['1'].values.custom_math).toBe(94);
        expect(applied.tiers['1'].values.custom_cert).toBe('ADVANCED');

        expect(applied.tiers['2'].values.gwa).toBe(83.5);
        expect(applied.tiers['2'].values.shs_strand).toBe('TVL-MARITIME');
        expect(applied.tiers['2'].values.custom_math).toBe(85);
        expect(applied.tiers['2'].values.custom_cert).toBe('INTERMEDIATE');

        expect(applied.tiers['3'].values.gwa).toBe(75);
        expect(applied.tiers['3'].values.shs_strand).toBe('ARTS-DESIGN');
        expect(applied.tiers['3'].values.custom_math).toBe(78);
        expect(applied.tiers['3'].values.custom_cert).toBe('BASIC');

        // Tiers 4 and 5: completely untouched (deep equality)
        expect(applied.tiers['4'].values).toEqual(initial.tiers['4'].values);
        expect(applied.tiers['5'].values).toEqual(initial.tiers['5'].values);

        // Metadata preserved
        expect(applied.authoring?.preferred_strands).toEqual(['TVL-MARITIME', 'ARTS-DESIGN']);
        expect(applied.authoring?.interests).toEqual(['riasec_i']);
        expect(applied.authoring?.interest_activities).toEqual(['act_custom_maritime']);
        expect(applied.authoring?.profile_activity_mapping).toEqual(initial.authoring?.profile_activity_mapping);

        // Reopening preserves deep state
        const reopened = cloneTabFmContent(applied);
        expect(reopened?.tiers['4'].values).toEqual(initial.tiers['4'].values);
        expect(reopened?.tiers['5'].values).toEqual(initial.tiers['5'].values);
        expect(reopened?.authoring?.preferred_strands).toEqual(['TVL-MARITIME', 'ARTS-DESIGN']);
    });
});
