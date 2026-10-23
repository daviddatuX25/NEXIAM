import { describe, expect, it } from 'vitest';
import {
    getPipelineActivityDetail,
    getPipelinePhaseLabel,
    getPipelinePhaseSteps,
} from '../../resources/js/lib/omr/omrPipelineStatus';

describe('OMR pipeline status projection', () => {
    it('marks prior, current, and future stages from the server phase', () => {
        const steps = getPipelinePhaseSteps('layout_verification');

        expect(steps.map((step) => step.state)).toEqual([
            'complete',
            'complete',
            'complete',
            'current',
            'pending',
            'pending',
            'pending',
            'pending',
        ]);
    });

    it('completes every stage only for successful terminal phases', () => {
        expect(
            getPipelinePhaseSteps('passed').every(
                (step) => step.state === 'complete',
            ),
        ).toBe(true);
        expect(getPipelinePhaseSteps('awaiting_review').at(-1)?.state).toBe(
            'current',
        );
        expect(
            getPipelinePhaseSteps('failed', 'orientation').map(
                (step) => step.state,
            ),
        ).toEqual([
            'complete',
            'complete',
            'failed',
            'pending',
            'pending',
            'pending',
            'pending',
            'pending',
        ]);
        const unlocatedFailure = getPipelinePhaseSteps('failed');
        expect(unlocatedFailure.at(-1)?.state).toBe('failed');
        expect(
            unlocatedFailure
                .slice(0, -1)
                .every((step) => step.state === 'pending'),
        ).toBe(true);
    });

    it('degrades unknown worker phases without claiming unreported progress', () => {
        expect(getPipelinePhaseLabel('new_worker_phase')).toBe(
            'Analysis in progress…',
        );
        expect(
            getPipelinePhaseSteps('new_worker_phase').every(
                (step) => step.state === 'pending',
            ),
        ).toBe(true);
    });
    it('describes document-bound page registration without marker claims', () => {
        expect(
            getPipelinePhaseLabel('layout_verification', 'document_bound'),
        ).toContain('document boundary');
        expect(
            getPipelineActivityDetail('layout_verification', 'document_bound'),
        ).toContain('unique page boundary');
        expect(
            getPipelinePhaseSteps(
                'layout_verification',
                null,
                'document_bound',
            )[3].label,
        ).toBe('Verifying document boundary');
        expect(getPipelineActivityDetail('layout_verification')).toContain(
            'corner markers',
        );
    });
    it('describes keep-original capture without boundary-finding or autocrop copy', () => {
        const steps = getPipelinePhaseSteps(
            'orientation',
            'document_bound',
            'keep_original',
        );
        expect(steps[2].label).toBe('Preserving original full-page frame');
        expect(steps[3].label).toBe('Verifying original page layout');
        expect(
            getPipelinePhaseLabel(
                'layout_verification',
                'document_bound',
                'keep_original',
            ),
        ).toBe('Verifying original page layout and content…');
        expect(
            getPipelineActivityDetail(
                'orientation',
                'document_bound',
                'keep_original',
            ),
        ).toBe(
            'Using the original full-page frame without autocrop; checking mapped layout and printed content.',
        );
        expect(
            [
                steps[2].label,
                steps[3].label,
                getPipelinePhaseLabel(
                    'layout_verification',
                    'document_bound',
                    'keep_original',
                ),
                getPipelineActivityDetail(
                    'orientation',
                    'document_bound',
                    'keep_original',
                ),
            ].join(' '),
        ).not.toMatch(/document boundary|page boundary/i);
        expect(
            getPipelinePhaseSteps(
                'layout_verification',
                'document_bound',
                'auto_document_quad',
            )[3].label,
        ).toBe('Verifying document boundary');
        expect(
            getPipelinePhaseLabel(
                'layout_verification',
                'document_bound',
                'auto_document_quad',
            ),
        ).toBe('Verifying document boundary and content…');
    });
});
