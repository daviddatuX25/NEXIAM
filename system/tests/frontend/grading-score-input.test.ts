// @vitest-environment jsdom

import { cleanup, fireEvent, render, screen } from '@testing-library/svelte';
import { afterEach, expect, it, vi } from 'vitest';

const mocks = vi.hoisted(() => ({ put: vi.fn() }));
vi.mock('@inertiajs/svelte', async (importOriginal) => {
    const actual = await importOriginal<Record<string, unknown>>();
    return {
        ...actual,
        usePage: () => ({ props: { auth: { user: { id: 17 } }, errors: {} } }),
        router: { put: mocks.put, on: () => () => {} },
    };
});

import GradingScoreInput from '../../resources/js/Pages/Assessment/GradingScoreInput.svelte';

const domains = [
    ['COMPUTATION_RAW', 'Computation Raw Score', false],
    ['SPATIAL_RAW', 'Spatial Raw Score', false],
    ['VOCABULARY_RAW', 'Vocabulary Raw Score', false],
    ['WORD_COMP_RAW', 'Word Comparison Raw Score', false],
    ['GENERAL_ABILITY_RAW', 'General Ability Raw Score', true],
    ['MANUAL_DEXTERITY_RAW', 'Manual Dexterity Raw Score', true],
].map(([code, name, derived], index) => ({
    metric_id: index + 1,
    metric_code: code,
    name,
    is_derived: derived,
    is_required: true,
    required_output_scales: ['percentile_rank'],
    precision_scale: 0,
    max_raw_value: 100,
}));

function renderInput(overrides: Record<string, unknown> = {}) {
    return render(GradingScoreInput, { props: {
        can_write: true,
        has_scoring_profile: true,
        is_profile_bound: true,
        selected_test_kit_version_id: 3,
        grading_status: 'open',
        grading_version: 1,
        urls: { update: '/scoring/sessions/1/attempts/1/scores' },
        domains,
        ...overrides,
    } });
}

afterEach(() => {
    cleanup();
    mocks.put.mockReset();
});

it('accepts all six external finals including raw-derived composites without requiring raw inputs', async () => {
    const { container } = renderInput();
    await fireEvent.click(screen.getByRole('button', { name: 'Aptitude Final Scores' }));
    const outputs = container.querySelectorAll<HTMLInputElement>('input[id^="external-output-"]');
    expect(outputs).toHaveLength(6);
    for (const [index, input] of Array.from(outputs).entries()) {
        expect(input.disabled).toBe(false);
        await fireEvent.input(input, { target: { value: String(70 + index) } });
    }
    await fireEvent.click(screen.getByRole('button', { name: 'Save final scores' }));
    expect(mocks.put).toHaveBeenCalledOnce();
    expect(mocks.put.mock.calls[0][1]).toMatchObject({
        scoring_mode: 'manual_converted',
        scores: domains.map((metric, index) => ({
            metric_id: metric.metric_id,
            typed_outputs: { percentile_rank: String(70 + index) },
        })),
    });
});

it('keeps raw composites read-only while direct raw measurements remain editable', () => {
    const { container } = renderInput();
    for (const metric of domains) {
        const input = container.querySelector<HTMLInputElement>(`#score-${metric.metric_id}`);
        expect(input?.disabled).toBe(metric.is_derived);
    }
});

it('reopens final-only results with distinct sources and preserves a valid zero when saving', async () => {
    const savedDomains = domains.map((metric, index) => ({
        ...metric,
        is_derived: false,
        rule_kind: 'external_final',
        precision_scale: 2,
        typed_outputs: { percentile_rank: index === 0 ? '0.00' : '81.25' },
        external_provenance: `Provider report ${index + 1}`,
    }));
    renderInput({ is_final_only: true, domains: savedDomains });
    expect(screen.queryByRole('button', { name: 'Subtest Raw Scores' })).toBeNull();
    expect(screen.queryByText(/Norm package version/)).toBeNull();
    await fireEvent.click(screen.getByRole('button', { name: 'Save final scores' }));
    expect(mocks.put.mock.calls[0][1].scores).toEqual(savedDomains.map((metric) => ({
        metric_id: metric.metric_id,
        typed_outputs: metric.typed_outputs,
        external_provenance: metric.external_provenance,
    })));
});
