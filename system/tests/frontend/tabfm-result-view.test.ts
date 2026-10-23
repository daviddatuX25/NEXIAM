// @vitest-environment jsdom

import { cleanup, fireEvent, render, screen, within } from '@testing-library/svelte';
import { afterEach, describe, expect, it, vi } from 'vitest';
import TabFmConsultationCard from '../../resources/js/Components/admission-support/TabFmConsultationCard.svelte';
import TabFmResultView from '../../resources/js/Components/admission-support/TabFmResultView.svelte';

const formMock = vi.hoisted(() => ({ clearErrors: vi.fn() }));

vi.mock('@inertiajs/svelte', () => ({
    useForm(initial: Record<string, unknown>) {
        return { ...initial, errors: {}, processing: false, clearErrors: formMock.clearErrors };
    },
}));

const radar = {
    general_ability: 81,
    verbal: 72,
    numerical: 88,
    spatial: 67,
    perceptual: 76,
    manual_dexterity: 59,
};
const radarAxisMetadata = [
    { key: 'logic', label: 'Logic', source_key: 'LOGIC' },
    { key: 'verbal', label: 'Verbal', source_key: 'PROFILE_VERBAL' },
    { key: 'numerical', label: 'Numerical', source_key: 'PROFILE_QUANT' },
    { key: 'spatial', label: 'Spatial', source_key: 'SPATIAL' },
    { key: 'memory', label: 'Memory', source_key: 'MEMORY' },
    { key: 'language', label: 'Language', source_key: 'LANGUAGE' },
];

function radarComparison(overrides: Record<string, unknown> = {}) {
    return {
        version: 1,
        scale: { kind: 'percentile', min: 0, max: 100 },
        axes: radarAxisMetadata,
        student: { values: { logic: 81, verbal: 72, numerical: 88, spatial: 67, memory: 76, language: 59 } },
        profiles: {
            cutoff: { tier: 3, values: { logic: 70, verbal: 65, numerical: 70, spatial: 60, memory: 66, language: 62 } },
            regular: { tier: 2, values: { logic: 80, verbal: 75, numerical: 80, spatial: 70, memory: 76, language: 72 } },
            honors: { tier: 1, values: { logic: 90, verbal: 85, numerical: 90, spatial: 80, memory: 86, language: 82 } },
        },
        ...overrides,
    };
}
const neuralInference = {
    inference_backend: 'tabpfn_pytorch',
    fallback_reason: null,
    engine_version: '9.1.0',
    modality_coverage: {
        inference_provenance: {
            package: 'tabpfn',
            package_version: '9.1.0',
            checkpoint_identity: 'Prior-Labs/tabpfn_3/tabpfn-v3-classifier-v3_20260417_binary.ckpt',
            checkpoint_sha256: '1f9949fd52e20b6ed8ece20b3b37242d2abbbf337d14b461a4244f9318031a88',
        },
    },
};

function recommendation(overrides: Record<string, unknown> = {}) {
    return {
        rank: 1,
        program_code: 'BSIT',
        program_name: 'Bachelor of Science in Information Technology',
        compatibility_index: 84,
        band: 'high_match',
        passed_cutoff: true,
        drivers: ['GWA benchmark alignment'],
        radar_comparison: radarComparison(),
        ...overrides,
    };
}

afterEach(() => {
    cleanup();
    vi.clearAllMocks();
});

describe('TabFmResultView', () => {
    it('renders the mocked neural response contract, cutoff states, selection, and returned rows', async () => {
        const recommendations = [
            recommendation(),
            recommendation({ rank: 2, program_code: 'BSN', band: 'moderate_match', compatibility_index: 68 }),
            recommendation({ rank: 3, program_code: 'BSED', band: 'below_threshold', compatibility_index: 43, passed_cutoff: false }),
            recommendation({ rank: 4, program_code: 'BSHM', passed_cutoff: null }),
            recommendation({ rank: 5, program_code: 'BSIT-2', band: 'unexpected_band', compatibility_index: null }),
        ];

        render(TabFmResultView, {
            props: {
                sourceKind: 'active',
                model: {
                    status: 'evaluated',
                    ...neuralInference,
                    source_version: 'v7',
                    benchmark_name: 'Mocked neural response-contract fixture',
                    recommendations,
                    applicant_radar: radar,
                    message: 'Advisory result only.',
                },
            },
        });

        expect(screen.getByText('Active benchmark · v7')).toBeTruthy();
        expect(screen.getByText('Backend · TabPFN transformer')).toBeTruthy();
        expect(screen.getByText('CMI · 0–100 index')).toBeTruthy();
        expect(screen.getByText('CMI is an index score, not an admission probability.')).toBeTruthy();
        const passing = screen.getByRole('button', { name: /Select BSIT for comparison/ });
        const belowCutoff = screen.getByRole('button', { name: /Select BSED for comparison/ });
        const unknownCutoff = screen.getByRole('button', { name: /Select BSHM for comparison/ });
        const passingRow = passing.closest('li');
        const belowCutoffRow = belowCutoff.closest('li');
        const unknownCutoffRow = unknownCutoff.closest('li');
        expect(passingRow?.textContent).toContain('High match');
        expect(passingRow?.textContent).toContain('Met recorded cutoff');
        expect(belowCutoffRow?.textContent).toContain('Below threshold');
        expect(belowCutoffRow?.textContent).toContain('Below recorded cutoff');
        expect(unknownCutoffRow?.textContent).toContain('Cutoff unavailable');
        expect(passing.getAttribute('aria-pressed')).toBe('true');
        expect(belowCutoff.getAttribute('aria-pressed')).toBe('false');
        await fireEvent.click(belowCutoff);
        expect(belowCutoff.getAttribute('aria-pressed')).toBe('true');
        expect(passing.getAttribute('aria-pressed')).toBe('false');
        expect(screen.getByRole('button', { name: /Select BSN for comparison/ }).textContent).toContain('Moderate match');
        expect(passingRow?.textContent).not.toContain('GWA benchmark alignment');
        const leadingMatch = screen.getByRole('button', { name: /Select BSIT for comparison/ });
        expect(leadingMatch.textContent).toContain('84% Match');
        expect(leadingMatch.textContent).not.toContain('84 / 100 CMI');
        expect(screen.queryByText('BSIT-2')).toBeNull();
        expect(screen.queryByRole('button', { name: /Record/ })).toBeNull();
        expect(screen.queryByRole('button', { name: /Add BSIT to the advised pool/ })).toBeNull();

        await fireEvent.click(screen.getByRole('button', { name: /Show 1 more ranked programmes/ }));
        const additional = screen.getByRole('button', { name: /Select BSIT-2 for comparison/ });
        expect(additional).toBeTruthy();
        await fireEvent.click(additional);
        expect(screen.getByText('Band unavailable')).toBeTruthy();
        expect(additional.closest('li')?.textContent).not.toContain('GWA benchmark alignment');

    });

    it('hands an explicitly selected recommendation to the optional advised-pool callback', async () => {
        const onAddToAdvisedPool = vi.fn();
        const recommendedProgram = recommendation();

        render(TabFmConsultationCard, {
            props: {
                attemptId: 91,
                model: {
                    status: 'evaluated',
                    ...neuralInference,
                    source_version: 'v7',
                    recommendations: [recommendedProgram],
                    applicant_evidence: { gwa: 89.4 },
                },
                onAddToAdvisedPool,
            },
        });

        await fireEvent.click(screen.getByRole('button', { name: /Add BSIT to the advised pool/ }));
        expect(onAddToAdvisedPool).toHaveBeenCalledWith(recommendedProgram);
    });

    it('keeps stale current evidence separate from a disclosed recorded analysis', async () => {
        const onAddToAdvisedPool = vi.fn();
        const evaluatedAt = '2026-10-07T14:35:00Z';

        render(TabFmConsultationCard, {
            props: {
                attemptId: 91,
                onAddToAdvisedPool,
                model: {
                    status: 'unavailable',
                    evaluated: false,
                    message: 'The active benchmark is not usable for evaluation.',
                    recommendations: [],
                    applicant_radar: null,
                    applicant_evidence: { gwa: 89.4 },
                    source_kind: 'recorded',
                    current_applicable: false,
                    recorded_result: {
                        payload: {
                            status: 'evaluated',
                            evaluated: true,
                            benchmark_name: 'Stored benchmark',
                            benchmark_version: 'v7',
                            recommendations: [recommendation()],
                            applicant_radar: radar,
                        },
                        evaluated_at: evaluatedAt,
                        recorded_at: evaluatedAt,
                        assessment_context: { benchmark_activation_id: 42 },
                        inference_backend: 'in_process_heuristics',
                        engine_version: 'tabfm-1',
                        benchmark_version: 'v7',
                        snapshot_version: 7,
                    },
                },
            },
        });

        expect(screen.getByText('89.4')).toBeTruthy();
        expect(screen.getByText('TabFM evaluation unavailable')).toBeTruthy();

        const liveResult = screen.getByText('TabFM evaluation unavailable').closest('[data-slot="card"]');
        const disclosure = screen.getByText(`Recorded analysis · ${evaluatedAt}`).closest('details');
        expect(disclosure?.open).toBe(false);
        expect(liveResult?.textContent).not.toContain('84');
        expect(liveResult?.querySelector('[aria-label="Ranked programme matches"]')).toBeNull();
        expect(liveResult?.querySelector('[aria-label="Aptitude comparison"]')).toBeNull();
        expect(disclosure?.querySelector('[aria-label="Ranked programme matches"]')).toBeNull();
        expect(disclosure?.querySelector('[aria-label="Aptitude comparison"]')).toBeNull();
        expect(disclosure?.textContent).toContain('Historical result · not current advice');
        expect(disclosure?.textContent).not.toContain('84');
        expect(disclosure?.textContent).not.toContain('100 CMI');


        await fireEvent.click(screen.getByText(`Recorded analysis · ${evaluatedAt}`));
        expect(disclosure?.open).toBe(true);

        const history = within(disclosure as HTMLElement);
        expect(history.getByText('Historical result · not current advice')).toBeTruthy();
        expect(history.getByText('Recorded analysis · v7')).toBeTruthy();
        expect(history.queryByRole('button', { name: /Select BSIT for comparison/ })).toBeNull();
        expect(history.queryByRole('button', { name: /Add BSIT to the advised pool/ })).toBeNull();
        expect(onAddToAdvisedPool).not.toHaveBeenCalled();

        await fireEvent.click(history.getByText('Technical details'));
        expect(history.getByText(evaluatedAt)).toBeTruthy();
        expect(history.getByText('{"benchmark_activation_id":42}')).toBeTruthy();
    });

    it('honors a backend-recorded source kind and allows handoff only when currently applicable', async () => {
        const onAddToAdvisedPool = vi.fn();

        render(TabFmResultView, {
            props: {
                sourceKind: 'active',
                onAddToAdvisedPool,
                model: {
                    status: 'evaluated',
                    ...neuralInference,
                    source_kind: 'recorded',
                    current_applicable: true,
                    source_version: 4,
                    recommendations: [recommendation()],
                },
            },
        });

        expect(screen.getByText('Recorded analysis · 4')).toBeTruthy();
        expect(screen.queryByText('Active benchmark · 4')).toBeNull();
        expect(screen.getByText('Backend · tabpfn_pytorch')).toBeTruthy();
        await fireEvent.click(screen.getByRole('button', { name: /Add BSIT to the advised pool/ }));
        expect(onAddToAdvisedPool).toHaveBeenCalledWith(expect.objectContaining({ program_code: 'BSIT' }));
    });

    it('does not render score, rank, radar, or advice for a recorded result without current applicability', () => {
        render(TabFmResultView, {
            props: {
                sourceKind: 'recorded',
                onAddToAdvisedPool: vi.fn(),
                model: {
                    status: 'evaluated',
                    source_kind: 'recorded',
                    recommendations: [recommendation()],
                },
            },
        });

        expect(screen.getByText('Recorded analysis · version unavailable')).toBeTruthy();
        expect(screen.getByText('Historical result · not current advice')).toBeTruthy();
        expect(screen.queryByRole('list', { name: 'Ranked programme matches' })).toBeNull();
        expect(screen.queryByRole('button', { name: /Select BSIT for comparison/ })).toBeNull();
        expect(screen.queryByRole('region', { name: 'Aptitude comparison' })).toBeNull();
        expect(screen.queryByText('84')).toBeNull();
        expect(screen.queryByText('/ 100 CMI')).toBeNull();
        expect(screen.queryByRole('button', { name: /Add BSIT to the advised pool/ })).toBeNull();
    });
    it('does not treat a malformed recorded payload as a successful analysis', async () => {
        const evaluatedAt = '2026-10-07T14:35:00Z';
        render(TabFmConsultationCard, {
            props: {
                attemptId: 91,
                model: {
                    status: 'unavailable',
                    recommendations: [],
                    applicant_evidence: { gwa: 89.4 },
                    recorded_result: {
                        payload: {
                            status: 'evaluated',
                            recommendations: 'malformed',
                        },
                        evaluated_at: evaluatedAt,
                        recorded_at: evaluatedAt,
                        benchmark_version: 'v7',
                        inference_backend: 'tabpfn_pytorch',
                    },
                },
            },
        });

        const disclosure = screen.getByText(`Recorded analysis · ${evaluatedAt}`).closest('details');
        expect(disclosure).toBeTruthy();
        await fireEvent.click(screen.getByText(`Recorded analysis · ${evaluatedAt}`));
        const history = within(disclosure as HTMLElement);
        expect(history.getByText('TabFM evaluation unavailable')).toBeTruthy();
        expect(history.queryByText('Recorded result')).toBeNull();
        expect(history.queryByRole('list', { name: 'Ranked programme matches' })).toBeNull();
    });

    it('keeps an applicable outer recorded model actionable', async () => {
        const onAddToAdvisedPool = vi.fn();
        const selectedRecommendation = recommendation();

        render(TabFmConsultationCard, {
            props: {
                attemptId: 91,
                onAddToAdvisedPool,
                model: {
                    status: 'evaluated',
                    ...neuralInference,
                    source_kind: 'recorded',
                    current_applicable: true,
                    recommendations: [selectedRecommendation],
                    applicant_evidence: { gwa: 89.4 },
                },
            },
        });

        await fireEvent.click(screen.getByRole('button', { name: /Add BSIT to the advised pool/ }));
        expect(onAddToAdvisedPool).toHaveBeenCalledWith(selectedRecommendation);
    });

    it('does not render an advised-pool button when the consultation wrapper has no callback', () => {
        render(TabFmConsultationCard, {
            props: {
                attemptId: 91,
                model: {
                    status: 'evaluated',
                    ...neuralInference,
                    recommendations: [recommendation()],
                },
            },
        });

        expect(screen.queryByRole('button', { name: /Add .* advised pool/ })).toBeNull();
    });
    it('rejects evaluated fallback data before showing scores or advice', () => {
        const onAddToAdvisedPool = vi.fn();
        render(TabFmConsultationCard, {
            props: {
                attemptId: 91,
                onAddToAdvisedPool,
                model: {
                    status: 'evaluated',
                    evaluated: true,
                    inference_backend: 'in_process_heuristics',
                    fallback_reason: 'tabpfn_unavailable',
                    recommendations: [recommendation()],
                    applicant_evidence: { gwa: 89.4 },
                },
            },
        });

        expect(screen.getByText('TabFM evaluation unavailable')).toBeTruthy();
        expect(screen.getByText('This result does not include valid neural inference provenance and cannot be shown.')).toBeTruthy();
        expect(screen.queryByRole('list', { name: 'Ranked programme matches' })).toBeNull();
        expect(screen.queryByText('/ 100 CMI')).toBeNull();
        expect(screen.queryByRole('button', { name: /Add BSIT to the advised pool/ })).toBeNull();
        expect(onAddToAdvisedPool).not.toHaveBeenCalled();
    });
    it('rejects neural-shaped results when provenance version and engine version disagree', () => {
        render(TabFmResultView, {
            props: {
                model: {
                    status: 'evaluated',
                    ...neuralInference,
                    engine_version: '9.1.1',
                    recommendations: [recommendation()],
                },
            },
        });

        expect(screen.getByText('TabFM evaluation unavailable')).toBeTruthy();
        expect(screen.queryByRole('list', { name: 'Ranked programme matches' })).toBeNull();
        expect(screen.queryByText('/ 100 CMI')).toBeNull();
        expect(screen.queryByRole('button', { name: /Select BSIT for comparison/ })).toBeNull();
    });

    it('renders the four stored subject series and switches all anchors with the selected programme', async () => {
        const secondProgrammeRadar = radarComparison({
            student: { values: { logic: 5, verbal: 0, numerical: 95, spatial: 50, memory: 75, language: 25 } },
            profiles: {
                cutoff: { tier: 3, values: { logic: 11, verbal: 12, numerical: 13, spatial: 14, memory: 15, language: 16 } },
                regular: { tier: 2, values: { logic: 21, verbal: 22, numerical: 23, spatial: 24, memory: 25, language: 26 } },
                honors: { tier: 1, values: { logic: 31, verbal: 32, numerical: 33, spatial: 34, memory: 35, language: 36 } },
            },
        });
        const { container } = render(TabFmResultView, {
            props: {
                sourceKind: 'draft',
                model: {
                    status: 'evaluated',
                    ...neuralInference,
                    source_version: 'draft-3',
                    recommendations: [
                        recommendation(),
                        recommendation({ program_code: 'BSCS', program_name: 'Computer Science', radar_comparison: secondProgrammeRadar }),
                    ],
                    applicant_radar: radar,
                },
            },
        });

        expect(screen.getByRole('region', { name: 'Test subject comparison' })).toBeTruthy();
        expect(screen.getByRole('table', { name: 'Test subject percentile values' })).toBeTruthy();
        expect(screen.getByRole('list', { name: 'Radar series legend' }).textContent).toContain('Student');
        expect(screen.getByRole('list', { name: 'Radar series legend' }).textContent).toContain('Cutoff');
        expect(screen.getByRole('list', { name: 'Radar series legend' }).textContent).toContain('Regular');
        expect(screen.getByRole('list', { name: 'Radar series legend' }).textContent).toContain('Honors');
        const desktopViewSelector = screen.getByRole('group', { name: 'Radar chart view selector' });
        expect(desktopViewSelector.querySelector('[aria-current="true"]')?.textContent).toContain('Cognitive Aptitude');
        expect(desktopViewSelector.textContent).toContain('Curriculum Fit');
        expect(desktopViewSelector.textContent).not.toContain('View 2');

        await fireEvent.click(screen.getAllByRole('button', { name: /Compare on Radar/ })[0]);
        const viewSelectors = screen.getAllByRole('group', { name: 'Radar chart view selector' });
        expect(viewSelectors).toHaveLength(2);
        expect(viewSelectors.every((selector) => {
            const text = selector.textContent ?? '';
            return text.includes('Cognitive Aptitude') && text.includes('Curriculum Fit') && !text.includes('View 2');
        })).toBe(true);

        expect(within(screen.getAllByRole('row', { name: /Memory/ })[0]).getAllByRole('cell').map((cell) => cell.textContent)).toEqual(['76', '66', '76', '86']);
        expect(within(screen.getAllByRole('row', { name: /Language/ })[0]).getAllByRole('cell').map((cell) => cell.textContent)).toEqual(['59', '62', '72', '82']);
        expect(screen.queryByText('Perceptual')).toBeNull();
        expect(screen.queryByText('Manual dexterity')).toBeNull();
        expect(screen.queryByText('PROFILE_QUANT')).toBeNull();
        expect(screen.getByText('Saved draft · draft-3')).toBeTruthy();
        expect(screen.queryByRole('button', { name: /Add .* advised pool/ })).toBeNull();

        await fireEvent.click(screen.getByRole('button', { name: 'Select BSCS for comparison' }));

        expect(within(screen.getAllByRole('row', { name: /Logic/ })[0]).getAllByRole('cell').map((cell) => cell.textContent)).toEqual(['5', '11', '21', '31']);
        expect(within(screen.getAllByRole('row', { name: /Verbal/ })[0]).getAllByRole('cell').map((cell) => cell.textContent)).toEqual(['0', '12', '22', '32']);
        expect(container.querySelectorAll('polygon[stroke]').length).toBe(4);
    });

    it('keeps valid CMI usable while a legacy-only radar payload is reported unavailable', () => {
        render(TabFmResultView, {
            props: {
                model: {
                    status: 'evaluated',
                    ...neuralInference,
                    recommendations: [recommendation({ radar_comparison: undefined, benchmark_radar: { ...radar } })],
                    applicant_radar: radar,
                    applicant_radar_sources: { general_ability: { source_key: 'GENERAL_ABILITY' } },
                },
            },
        });

        expect(screen.getByText('84% Match')).toBeTruthy();
        expect(screen.getByText('Test subject comparison unavailable')).toBeTruthy();
        expect(screen.queryByRole('table', { name: 'Test subject percentile values' })).toBeNull();
        expect(screen.queryByText('Perceptual')).toBeNull();
        expect(screen.queryByText('GENERAL_ABILITY')).toBeNull();
    });

    it('keeps unavailable sources free of scores, rankings, radar, and advice callbacks', () => {
        const onAddToAdvisedPool = vi.fn();
        render(TabFmResultView, {
            props: {
                sourceKind: 'active',
                onAddToAdvisedPool,
                model: {
                    status: 'unavailable',
                    evaluated: false,
                    source_version: null,
                    message: 'Required benchmark cells are still missing.',
                    recommendations: [recommendation()],
                    applicant_radar: radar,
                },
            },
        });

        expect(screen.getByText('TabFM evaluation unavailable')).toBeTruthy();
        expect(screen.getByText('Active benchmark · version unavailable')).toBeTruthy();
        expect(screen.queryByRole('list', { name: 'Ranked programme matches' })).toBeNull();
        expect(screen.queryByRole('region', { name: 'Aptitude comparison' })).toBeNull();
        expect(screen.queryByText('Below threshold')).toBeNull();
        expect(screen.queryByText('/ 100 CMI')).toBeNull();
        expect(screen.queryByRole('button', { name: /Add .* advised pool/ })).toBeNull();
        expect(onAddToAdvisedPool).not.toHaveBeenCalled();
    });
    it('shows the server-authored historical recovery hold in the shared result view without presenting it as evaluated', () => {
        render(TabFmResultView, {
            props: {
                sourceKind: 'active',
                model: {
                    status: 'standby',
                    message: 'The current historical benchmark requires explicit recovery and review before preview.',
                    recommendations: [],
                    evaluation_context: {
                        source: 'active',
                        benchmark_activation_id: null,
                        benchmark_set_id: null,
                    },
                },
            },
        });

        expect(screen.getByText('TabFM result not available')).toBeTruthy();
        expect(screen.getByText('Active benchmark · version unavailable')).toBeTruthy();
        expect(screen.queryByText('Advisory result')).toBeNull();
        expect(screen.queryByText('/ 100 CMI')).toBeNull();
    });

    it('suppresses drivers, rank badges, CMI scale, and substitute scores when neural provenance is missing or invalid on an evaluated result', () => {
        render(TabFmResultView, {
            props: {
                model: {
                    status: 'evaluated',
                    inference_backend: 'tabpfn_pytorch',
                    fallback_reason: null,
                    engine_version: '9.1.0',
                    modality_coverage: {},
                    recommendations: [recommendation({ drivers: ['Diagnostic driver should not render'] })],
                },
            },
        });

        expect(screen.getByText('TabFM evaluation unavailable')).toBeTruthy();
        expect(screen.queryByText('Diagnostic driver should not render')).toBeNull();
        expect(screen.queryByText(/GWA benchmark alignment/)).toBeNull();
        expect(screen.queryByRole('list', { name: 'Ranked programme matches' })).toBeNull();
        expect(screen.queryByText('/ 100 CMI')).toBeNull();
        expect(screen.queryByText('#1')).toBeNull();
        expect(screen.queryByRole('button', { name: /Select BSIT/ })).toBeNull();
        expect(screen.queryByRole('button', { name: /Add .* advised pool/ })).toBeNull();
    });

    it('suppresses driver text, rank badges, and scores when status is unavailable even if recommendation objects are injected', () => {
        render(TabFmResultView, {
            props: {
                sourceKind: 'active',
                model: {
                    status: 'unavailable',
                    message: 'Model is unavailable.',
                    recommendations: [recommendation({ drivers: ['Injected driver'] })],
                },
            },
        });

        expect(screen.getByText('TabFM evaluation unavailable')).toBeTruthy();
        expect(screen.queryByText('Injected driver')).toBeNull();
        expect(screen.queryByText('#1')).toBeNull();
        expect(screen.queryByText(/CMI/)).toBeNull();
        expect(screen.queryByRole('list', { name: 'Ranked programme matches' })).toBeNull();
    });

    it('switches between Cognitive Aptitude and CHED Curriculum Alignment when toggling the view selector', async () => {
        render(TabFmResultView, {
            props: {
                model: {
                    status: 'evaluated',
                    ...neuralInference,
                    recommendations: [recommendation()],
                    applicant_evidence: {
                        strand: 'STEM',
                    },
                },
                vocationalProfile: {
                    status: 'recorded',
                    is_reviewed: true,
                    program_targets: {
                        BSIT: {
                            code: 'IR',
                            name: 'Investigative · Realistic',
                            cmo_reference: 'CHED CMO No. 25, s. 2015',
                            cgp_strand_alignment: 'STEM / TVL-ICT',
                            priority_strands: ['STEM', 'TVL-ICT'],
                        },
                    },
                },
            },
        });

        expect(screen.getByRole('region', { name: 'Test subject comparison' })).toBeTruthy();
        expect(screen.queryByRole('region', { name: 'CHED Curriculum & Strand Alignment' })).toBeNull();

        const curriculumButtons = screen.getAllByRole('button', { name: /Curriculum Fit/ });
        await fireEvent.click(curriculumButtons[0]);

        expect(screen.getByRole('region', { name: 'CHED Curriculum & Strand Alignment' })).toBeTruthy();
        expect(screen.getByText('Directly Aligned')).toBeTruthy();
        expect(screen.getByText('CHED CMO No. 25, s. 2015')).toBeTruthy();
        expect(screen.getByText('TVL-ICT')).toBeTruthy();
        expect(screen.queryByRole('region', { name: 'Test subject comparison' })).toBeNull();

        const cognitiveButtons = screen.getAllByRole('button', { name: /Cognitive Aptitude/ });
        await fireEvent.click(cognitiveButtons[0]);

        expect(screen.getByRole('region', { name: 'Test subject comparison' })).toBeTruthy();
        expect(screen.queryByRole('region', { name: 'CHED Curriculum & Strand Alignment' })).toBeNull();
    });

    it('evaluates transferee prior tertiary coursework without false SHS cross-strand bridging warnings and anchors fallback to applicant preferred program', () => {
        render(TabFmResultView, {
            props: {
                availablePrograms: [
                    { code: 'BPA', name: 'Bachelor of Public Administration' },
                    { code: 'BSIT', name: 'Bachelor of Science in Information Technology' },
                ],
                model: {
                    status: 'unavailable',
                    message: 'TabFM neural inference is unavailable.',
                    recommendations: [],
                    applicant_evidence: {
                        applicant_type: 'transferee',
                        strand: 'Bachelor of Early Childhood Education',
                        preferred_program_code: 'BSIT',
                    },
                },
                vocationalProfile: {
                    status: 'unrecorded',
                    is_reviewed: false,
                    applicant_type: 'transferee',
                    applicant_strand: 'Bachelor of Early Childhood Education',
                    preferred_program_code: 'BSIT',
                    program_targets: {
                        BPA: {
                            code: 'ES',
                            name: 'Enterprising · Social',
                            cmo_reference: 'CHED CMO No. 06, s. 2010',
                            priority_strands: ['HUMSS', 'ABM'],
                        },
                        BSIT: {
                            code: 'IR',
                            name: 'Investigative · Realistic',
                            cmo_reference: 'CHED CMO No. 25, s. 2015',
                            priority_strands: ['STEM', 'TVL-ICT'],
                        },
                    },
                },
            },
        });

        const alignmentRegion = screen.getByRole('region', { name: 'CHED Curriculum & Strand Alignment' });
        expect(alignmentRegion.textContent).toContain('Curriculum Fit: BSIT');
        expect(alignmentRegion.textContent).not.toContain('BPA');
        expect(alignmentRegion.textContent).toContain('Transferee Credit');
        expect(alignmentRegion.textContent).toContain('Prior Degree');
        expect(alignmentRegion.textContent).toContain('Bachelor of Early Childhood Education');
        expect(alignmentRegion.textContent).toContain('TOR Credit Equivalency');
        expect(alignmentRegion.textContent).not.toContain('Applicant graduated from a non-aligned strand');
        const cmoLink = within(alignmentRegion).getByRole('link', { name: /CHED CMO No\. 25, s\. 2015/i });
        expect(cmoLink.getAttribute('target')).toBe('_blank');
    });

    it('opens the per-program Notes & Threads modal from a ranked recommendation card and switches between Advising Notes and Threads', async () => {
        const onOpenThread = vi.fn();
        render(TabFmResultView, {
            props: {
                model: {
                    status: 'evaluated',
                    evaluated: true,
                    ...neuralInference,
                    evaluation_context: { source: 'live' },
                    recommendations: [
                        {
                            rank: 1,
                            program_code: 'BA-Psych',
                            program_name: 'Bachelor of Arts in Psychology',
                            compatibility_index: 5,
                            band: 'below_threshold',
                            passed_cutoff: false,
                        },
                    ],
                },
                publishedAdvisingNotes: [
                    {
                        revision_id: 101,
                        program_code: 'BA-Psych',
                        version: 2,
                        content: 'Emphasize research statistics readiness during psychology advising.',
                        author_name: 'Dr. Santos',
                    },
                ],
                threads: [
                    {
                        id: 'thread-1',
                        program_code: 'BA-Psych',
                        subject: 'BA-Psych Portfolio Clarification',
                        thread_type: 'internal_ph_guidance',
                        status: 'open',
                        unread_count: 1,
                    },
                ],
                onOpenThread,
            },
        });

        const notesBtn = screen.getByRole('button', { name: /View advising notes and threads for BA-Psych/i });
        await fireEvent.click(notesBtn);

        expect(screen.getByText('Emphasize research statistics readiness during psychology advising.')).toBeTruthy();
        await fireEvent.click(screen.getByRole('tab', { name: /Threads \(1\)/i }));
        expect(screen.getByText('BA-Psych Portfolio Clarification')).toBeTruthy();
        await fireEvent.click(screen.getByRole('button', { name: /Open Thread/i }));
        expect(onOpenThread).toHaveBeenCalledOnce();
    });
    it('renders only the selected program in read-only scoped mode with inline mobile radar and explanation', () => {
        render(TabFmConsultationCard, {
            props: {
                scopedProgramCode: 'BSIT',
                model: {
                    slot: 'tabfm_pairwise',
                    status: 'evaluated',
                    evaluated: true,
                    source_kind: 'active',
                    current_applicable: true,
                    source_version: 'v4',
                    evaluated_at: '2026-10-20T10:00:00Z',
                    ...neuralInference,
                    recommendations: [
                        recommendation(),
                        recommendation({ program_code: 'BA-Psych', program_name: 'Psychology', drivers: ['Other program evidence'] }),
                    ],
                },
            },
        });

        expect(screen.getByText('TABFM — BSIT')).toBeTruthy();
        expect(screen.getByText(/Evaluated 2026-10-20T10:00:00Z/)).toBeTruthy();
        expect(screen.getByRole('region', { name: 'Test subject comparison' })).toBeTruthy();
        expect(screen.getByText('GWA benchmark alignment')).toBeTruthy();
        expect(screen.queryByText('Psychology')).toBeNull();
        expect(screen.queryByText('Other program evidence')).toBeNull();
        expect(screen.queryByRole('button')).toBeNull();
        expect(screen.queryByText(/Ranked programme matches|Compare on Radar|Add .*Advised Pool|Notes & Threads|Record Notes|Technical details/)).toBeNull();
    });

    it('keeps the selected program section visible when there is no program result', () => {
        render(TabFmResultView, {
            props: {
                scopedProgramCode: 'BSIT',
                model: {
                    status: 'evaluated',
                    evaluated: true,
                    ...neuralInference,
                    recommendations: [],
                },
            },
        });

        expect(screen.getByText('TABFM — BSIT')).toBeTruthy();
        expect(screen.getByRole('status').textContent).toContain('No program result');
    });

    it('labels recorded noncurrent analysis as history without exposing ranked results or controls', () => {
        render(TabFmResultView, {
            props: {
                scopedProgramCode: 'BSIT',
                sourceKind: 'recorded',
                model: {
                    status: 'evaluated',
                    evaluated: true,
                    source_kind: 'recorded',
                    current_applicable: false,
                    ...neuralInference,
                    recommendations: [],
                },
            },
        });

        expect(screen.getByText('TABFM — BSIT')).toBeTruthy();
        expect(screen.getByRole('status').textContent).toContain('Historical result');
        expect(screen.queryByRole('button')).toBeNull();
    });

    it('shows a safe not-evaluated state in scoped mode', () => {
        render(TabFmResultView, {
            props: {
                scopedProgramCode: 'BSIT',
                model: { status: 'no_analysis', evaluated: false, recommendations: [], message: 'No analysis has been produced.' },
            },
        });

        expect(screen.getByText('TABFM — BSIT')).toBeTruthy();
        expect(screen.getByRole('status').textContent).toContain('TABFM not evaluated');
        expect(screen.queryByRole('button')).toBeNull();
    });
    it('rebinds the result to the new selected program when scoped props change', async () => {
        const view = render(TabFmResultView, {
            props: {
                scopedProgramCode: 'BSIT',
                model: {
                    status: 'evaluated',
                    evaluated: true,
                    ...neuralInference,
                    recommendations: [recommendation(), recommendation({ program_code: 'BSBA', program_name: 'Business Administration' })],
                },
            },
        });

        expect(screen.getByText('TABFM — BSIT')).toBeTruthy();
        await view.rerender({
            scopedProgramCode: 'BSBA',
            model: {
                status: 'evaluated',
                evaluated: true,
                ...neuralInference,
                recommendations: [recommendation(), recommendation({ program_code: 'BSBA', program_name: 'Business Administration' })],
            },
        });

        expect(screen.getByText('TABFM — BSBA')).toBeTruthy();
        expect(screen.queryByText('TABFM — BSIT')).toBeNull();
    });
    it('shows the safe unavailable state without exposing provider diagnostics', () => {
        render(TabFmResultView, {
            props: {
                scopedProgramCode: 'BSIT',
                model: {
                    status: 'unavailable',
                    evaluated: false,
                    message: 'Analysis could not be prepared.',
                    diagnostic: 'private provider exception',
                },
            },
        });

        expect(screen.getByText('TABFM — BSIT')).toBeTruthy();
        expect(screen.getByRole('status').textContent).toContain('TABFM unavailable');
        expect(screen.getByRole('status').textContent).toContain('Analysis could not be prepared.');
        expect(screen.queryByText('private provider exception')).toBeNull();
    });
    it('renders fractional CMI match scores with two-decimal precision', () => {
        render(TabFmResultView, {
            props: {
                sourceKind: 'active',
                model: {
                    status: 'evaluated',
                    ...neuralInference,
                    source_version: 'v7',
                    benchmark_name: 'Two-decimal precision fixture',
                    recommendations: [
                        recommendation({ program_code: 'BSIT', compatibility_index: 88.42 }),
                        recommendation({ rank: 2, program_code: 'BSCS', compatibility_index: 84.15 }),
                    ],
                    applicant_radar: radar,
                    message: 'Advisory result only.',
                },
            },
        });

        expect(screen.getByText('88.42% Match')).toBeTruthy();
        expect(screen.getByText('84.15% Match')).toBeTruthy();
    });
});
