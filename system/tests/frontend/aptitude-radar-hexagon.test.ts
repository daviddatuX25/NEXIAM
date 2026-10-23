// @vitest-environment jsdom

import { cleanup, render, screen, within } from '@testing-library/svelte';
import { afterEach, describe, expect, it } from 'vitest';
import AptitudeRadarHexagon from '../../resources/js/Components/admission-support/AptitudeRadarHexagon.svelte';

const axes = [
    { key: 'logic', label: 'Logic', source_key: 'LOGIC' },
    { key: 'verbal', label: 'Verbal', source_key: 'PROFILE_VERBAL' },
    { key: 'numerical', label: 'Numerical', source_key: 'PROFILE_QUANT' },
    { key: 'spatial', label: 'Spatial', source_key: 'SPATIAL' },
    { key: 'memory', label: 'Memory', source_key: 'MEMORY' },
    { key: 'language', label: 'Language', source_key: 'LANGUAGE' },
];

function values(overrides: Record<string, number | null> = {}) {
    return { logic: 0, verbal: 20, numerical: 40, spatial: 60, memory: 80, language: 100, ...overrides };
}

function contract(overrides: Record<string, unknown> = {}) {
    return {
        version: 1,
        scale: { kind: 'percentile', min: 0, max: 100 },
        axes,
        student: { values: values() },
        profiles: {
            cutoff: { tier: 3, values: values({ logic: 10 }) },
            regular: { tier: 2, values: values({ logic: 20 }) },
            honors: { tier: 1, values: values({ logic: 30 }) },
        },
        ...overrides,
    };
}

afterEach(() => cleanup());

describe('AptitudeRadarHexagon', () => {
    it('preserves zero and null values without drawing incomplete polygons', () => {
        const radar = contract({
            student: { values: values({ logic: 0, verbal: null }) },
            profiles: {
                cutoff: { tier: 3, values: values({ logic: null, verbal: null }) },
                regular: { tier: 2, values: values({ logic: 20 }) },
                honors: { tier: 1, values: values({ logic: 30 }) },
            },
        });
        const { container } = render(AptitudeRadarHexagon, { props: { radarComparison: radar } });

        expect(screen.getByRole('region', { name: 'Test subject comparison' })).toBeTruthy();
        expect(screen.getByText('Partial profiles show recorded points only; incomplete profiles are not connected or filled.')).toBeTruthy();
        expect(within(screen.getByRole('row', { name: /Logic/ })).getAllByRole('cell').map((cell) => cell.textContent)).toEqual(['0', '—', '20', '30']);
        expect(container.querySelectorAll('polygon[stroke]').length).toBe(2);
        expect(container.querySelector('circle[cy="155"][cx="180"]')).toBeTruthy();
    });

    it.each([
        ['incompatible scale', contract({ scale: { kind: 'score', min: 0, max: 100 } })],
        ['mismatched source metadata', contract({ axes: axes.map((axis, index) => index === 0 ? { ...axis, source_key: 'GENERAL_ABILITY' } : axis) })],
        ['mismatched subject label', contract({ axes: axes.map((axis, index) => index === 4 ? { ...axis, label: 'Perceptual' } : axis) })],
        ['mismatched anchor tier', contract({ profiles: { ...contract().profiles, regular: { tier: 3, values: values() } } })],
        ['out-of-range series value', contract({ student: { values: values({ logic: 101 }) } })],
    ])('reports %s as unavailable without a chart', (_name, radarComparison) => {
        const { container } = render(AptitudeRadarHexagon, { props: { radarComparison } });

        expect(screen.getByRole('status', { name: 'Test subject comparison unavailable' })).toBeTruthy();
        expect(container.querySelector('svg')).toBeNull();
        expect(screen.queryByRole('table', { name: 'Test subject percentile values' })).toBeNull();
    });

    it('does not substitute a missing anchor from another tier', () => {
        const radarComparison = contract({
            profiles: {
                cutoff: { tier: 3, values: values() },
                regular: { tier: 2, values: values() },
                honors: undefined,
            },
        });

        render(AptitudeRadarHexagon, { props: { radarComparison } });

        expect(screen.getByText('Test subject comparison unavailable')).toBeTruthy();
        expect(screen.queryByRole('table', { name: 'Test subject percentile values' })).toBeNull();
    });
});
