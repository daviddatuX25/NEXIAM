// @vitest-environment jsdom

import { cleanup, fireEvent, render, screen } from '@testing-library/svelte';
import { afterEach, describe, expect, it, vi } from 'vitest';
import TabFmBenchmarkCalibrator from '../../resources/js/Components/admission-support/TabFmBenchmarkCalibrator.svelte';

afterEach(() => {
    cleanup();
});

describe('TabFM common range authoring UI', () => {
    it('does not show endpoint errors after locally applying reviewed range values', async () => {
        const onContentChange = vi.fn();
        const content = {
            tiers: {
                '1': { values: { biology_grade: 95 } },
                '2': { values: { biology_grade: 88 } },
                '3': { values: { biology_grade: 82 } },
                '4': { values: {} },
                '5': { values: {} },
            },
        };
        render(TabFmBenchmarkCalibrator, {
            props: {
                program: { code: 'GOV-DEMO-A' },
                cycle: { code: 'SYN-CYCLE-2026' },
                features: [{ key: 'biology_grade', label: 'Biology score', logical_type: 'numeric', declared_unit: 'points' }],
                inputSchema: { subjects: [], activities: [] },
                content,
                version: 1,
                canEdit: true,
                dirty: true,
                onContentChange,
                onSave: vi.fn(async () => true),
                onReset: vi.fn(),
            },
        });

        await fireEvent.input(screen.getByLabelText('Common range · From'), { target: { value: '60' } });
        await fireEvent.input(screen.getByLabelText('Common range · To'), { target: { value: '95' } });
        await fireEvent.click(screen.getByRole('button', { name: 'Prepare examples' }));
        expect(screen.getByText('Model cell: 88 → 77.5')).toBeTruthy();
        expect(screen.getByText('Review before applying · local only')).toBeTruthy();

        await fireEvent.click(screen.getByRole('button', { name: 'Apply reviewed values to this draft' }));

        expect(onContentChange).toHaveBeenCalledOnce();
        expect(onContentChange.mock.calls[0][0].tiers['2'].values.biology_grade).toBe(77.5);
        expect(screen.queryByText('Both endpoints must be valid before values can be prepared.')).toBeNull();
    });

    it('leaves dynamic fields, nonstandard option tokens, and tier 4/5 profiles untouched when editing common ranges in UI', async () => {
        const onContentChange = vi.fn();
        const content = {
            tiers: {
                '1': { values: { gwa: 94, shs_strand: 'STEM', custom_score: 91 } },
                '2': { values: { gwa: 87, shs_strand: 'TVL-MARITIME', custom_score: 84 } },
                '3': { values: { gwa: 81, shs_strand: 'ARTS-DESIGN', custom_score: 79 } },
                '4': { values: { gwa: 78, shs_strand: 'SPORTS', custom_score: 75, riasec_i: 1 } },
                '5': { values: { gwa: 70, shs_strand: 'ABM', custom_score: 65, riasec_i: 0 } },
            },
            authoring: {
                version: 1,
                ranges: { honors: {}, regular: {}, cutoff: {} },
                preferred_strands: ['TVL-MARITIME'],
                interests: ['riasec_i'],
            },
        };

        render(TabFmBenchmarkCalibrator, {
            props: {
                program: { code: 'GOV-DEMO-B' },
                cycle: { code: 'SYN-CYCLE-2026' },
                features: [
                    { key: 'gwa', label: 'GWA', logical_type: 'numeric', declared_unit: 'percent' },
                    { key: 'custom_score', label: 'Custom Score', logical_type: 'numeric', declared_unit: 'points' },
                    { key: 'shs_strand', label: 'SHS Strand', logical_type: 'categorical' },
                ],
                inputSchema: { subjects: [], activities: [] },
                content,
                version: 1,
                canEdit: true,
                dirty: true,
                onContentChange,
                onSave: vi.fn(async () => true),
                onReset: vi.fn(),
            },
        });

        const fromInputs = screen.getAllByLabelText('Common range · From');
        const toInputs = screen.getAllByLabelText('Common range · To');
        await fireEvent.input(fromInputs[0], { target: { value: '75' } });
        await fireEvent.input(toInputs[0], { target: { value: '90' } });

        const prepareButtons = screen.getAllByRole('button', { name: 'Prepare examples' });
        await fireEvent.click(prepareButtons[0]);

        await fireEvent.click(screen.getByRole('button', { name: 'Apply reviewed values to this draft' }));

        expect(onContentChange).toHaveBeenCalledOnce();
        const updated = onContentChange.mock.calls[0][0];

        expect(updated.tiers['1'].values.gwa).toBe(90);
        expect(updated.tiers['2'].values.gwa).toBe(82.5);
        expect(updated.tiers['3'].values.gwa).toBe(75);

        expect(updated.tiers['1'].values.shs_strand).toBe('STEM');
        expect(updated.tiers['1'].values.custom_score).toBe(91);
        expect(updated.tiers['2'].values.shs_strand).toBe('TVL-MARITIME');
        expect(updated.tiers['2'].values.custom_score).toBe(84);
        expect(updated.tiers['3'].values.shs_strand).toBe('ARTS-DESIGN');
        expect(updated.tiers['3'].values.custom_score).toBe(79);

        expect(updated.tiers['4'].values).toEqual(content.tiers['4'].values);
        expect(updated.tiers['5'].values).toEqual(content.tiers['5'].values);

        expect(updated.authoring?.preferred_strands).toEqual(['TVL-MARITIME']);
        expect(updated.authoring?.interests).toEqual(['riasec_i']);
    });
    it('preserves reviewed numeric ranges when the profile tool changes advisory strand preferences', async () => {
        const onContentChange = vi.fn();
        const original = {
            tiers: {
                '1': { values: { gwa: 94 } }, '2': { values: { gwa: 87 } },
                '3': { values: { gwa: 81 } }, '4': { values: { gwa: 78 } }, '5': { values: { gwa: 70 } },
            },
            authoring: { version: 1, ranges: { honors: {}, regular: {}, cutoff: {} }, preferred_strands: [], interests: [] },
        };
        const props = {
            program: { code: 'BSIT' }, cycle: { code: 'SYN-CYCLE-2026' },
            features: [{ key: 'gwa', label: 'GWA', logical_type: 'numeric', declared_unit: 'percent' }],
            inputSchema: { subjects: [], activities: [] }, content: original, view: 'core',
            version: 1, canEdit: true, dirty: true, onContentChange, onSave: vi.fn(), onReset: vi.fn(),
        };
        const { rerender } = render(TabFmBenchmarkCalibrator, { props });
        await fireEvent.input(screen.getByLabelText('Common range · From'), { target: { value: '75' } });
        await fireEvent.input(screen.getByLabelText('Common range · To'), { target: { value: '90' } });
        await fireEvent.click(screen.getByRole('button', { name: 'Prepare examples' }));
        await fireEvent.click(screen.getByRole('button', { name: 'Apply reviewed values to this draft' }));
        const ranged = onContentChange.mock.calls[0][0];
        await rerender({ ...props, content: ranged, view: 'profile' });
        await fireEvent.click(screen.getByRole('button', { name: 'STEM', exact: true }));
        const profiled = onContentChange.mock.calls[1][0];
        expect(profiled.authoring.preferred_strands).toEqual(['STEM']);
        expect(profiled.tiers['1'].values.gwa).toBe(90);
        expect(profiled.tiers['2'].values.gwa).toBe(82.5);
        expect(profiled.tiers['3'].values.gwa).toBe(75);
        expect(profiled.tiers['4'].values.gwa).toBe(78);
        expect(profiled.tiers['5'].values.gwa).toBe(70);
        expect(profiled.authoring.ranges).toEqual(ranged.authoring.ranges);
    });
});
