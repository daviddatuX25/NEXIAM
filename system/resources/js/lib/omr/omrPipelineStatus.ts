/**
 * Authoritative pipeline phase codes and human-readable QA descriptions
 * for physical OMR verification capture analysis.
 */

export const OMR_PIPELINE_PHASES = {
    QUEUED: 'queued',
    PREFLIGHT: 'preflight',
    ORIENTATION: 'orientation',
    LAYOUT_VERIFICATION: 'layout_verification',
    RECOGNITION: 'recognition',
    EVIDENCE_PERSISTENCE: 'evidence_persistence',
    AWAITING_REVIEW: 'awaiting_review',
    PASSED: 'passed',
    FAILED: 'failed',
} as const;

export type OmrPipelinePhaseCode =
    (typeof OMR_PIPELINE_PHASES)[keyof typeof OMR_PIPELINE_PHASES];
type RegistrationMode = 'fiducial_anchored' | 'document_bound';

export const OMR_PHASE_LABELS: Record<OmrPipelinePhaseCode, string> = {
    queued: 'Upload received — waiting for worker…',
    preflight: 'Validating image format, resolution, and page limits…',
    orientation: 'Finding orientation and corner markers…',
    layout_verification: 'Verifying sheet layout and fiducial alignment…',
    recognition: 'Detecting bubble marks and comparing expected answers…',
    evidence_persistence: 'Saving aligned evidence and diagnostic overlays…',
    awaiting_review: 'Analysis complete — choose Pass or Retake',
    passed: 'Passed',
    failed: 'Needs retake — analysis stopped',
};

export const OMR_PHASE_ACTIVITY_DETAILS: Record<OmrPipelinePhaseCode, string> =
    {
        queued: 'Capture received and queued for asynchronous analysis.',
        preflight: 'Checking image format, resolution, and page limits.',
        orientation:
            'Searching the quarter-turn orientations for the signed sheet markers.',
        layout_verification:
            'Comparing corner markers and sheet geometry with the challenge.',
        recognition:
            'Reading bubble marks and comparing them with the expected answer vector.',
        evidence_persistence:
            'Persisting aligned evidence and diagnostics for this attempt.',
        awaiting_review:
            'Analysis finished; the captured evidence is ready for staff review.',
        passed: 'All verification gates passed for this method.',
        failed: 'Analysis stopped at the recorded failure phase.',
    };

export function getPipelineActivityDetail(
    code: string | null | undefined,
    registrationMode: RegistrationMode = 'fiducial_anchored',
    cropPolicy?: string | null,
): string {
    const normalized =
        typeof code === 'string' ? code.toLowerCase().trim() : '';
    if (
        registrationMode === 'document_bound' &&
        cropPolicy === 'keep_original' &&
        (normalized === OMR_PIPELINE_PHASES.ORIENTATION ||
            normalized === OMR_PIPELINE_PHASES.LAYOUT_VERIFICATION)
    ) {
        return 'Using the original full-page frame without autocrop; checking mapped layout and printed content.';
    }
    if (registrationMode === 'document_bound') {
        if (normalized === OMR_PIPELINE_PHASES.ORIENTATION)
            return 'Checking the captured page orientation.';
        if (normalized === OMR_PIPELINE_PHASES.LAYOUT_VERIFICATION)
            return 'Verifying the unique page boundary, rectification, and printed content.';
    }
    return (
        OMR_PHASE_ACTIVITY_DETAILS[normalized as OmrPipelinePhaseCode] ??
        'The verification worker is processing this capture; waiting for a recognized phase update.'
    );
}

export type OmrPipelineStepState =
    'complete' | 'current' | 'pending' | 'failed';

export type OmrPipelineStep = {
    code: OmrPipelinePhaseCode;
    label: string;
    state: OmrPipelineStepState;
};

const OMR_ANALYSIS_STEPS: ReadonlyArray<
    Pick<OmrPipelineStep, 'code' | 'label'>
> = [
    { code: OMR_PIPELINE_PHASES.QUEUED, label: 'Upload received' },
    { code: OMR_PIPELINE_PHASES.PREFLIGHT, label: 'Validating image' },
    {
        code: OMR_PIPELINE_PHASES.ORIENTATION,
        label: 'Finding orientation and corner markers',
    },
    {
        code: OMR_PIPELINE_PHASES.LAYOUT_VERIFICATION,
        label: 'Verifying sheet layout',
    },
    { code: OMR_PIPELINE_PHASES.RECOGNITION, label: 'Detecting bubble marks' },
    {
        code: OMR_PIPELINE_PHASES.RECOGNITION,
        label: 'Comparing expected answers',
    },
    {
        code: OMR_PIPELINE_PHASES.EVIDENCE_PERSISTENCE,
        label: 'Saving evidence',
    },
    { code: OMR_PIPELINE_PHASES.PASSED, label: 'Passed or Needs retake' },
];

/**
 * Project the server-owned phase onto a stable, numbered analysis sequence.
 * Terminal success completes the sequence. Terminal failure marks only the
 * server-reported failed phase; missing phase evidence remains pending.
 */
export function getPipelinePhaseSteps(
    code: string | null | undefined,
    failedPhaseCode?: string | null,
    registrationMode?: RegistrationMode,
    cropPolicy?: string | null,
): OmrPipelineStep[];
export function getPipelinePhaseSteps(
    code: string | null | undefined,
    registrationMode: RegistrationMode,
    cropPolicy?: string | null,
): OmrPipelineStep[];
export function getPipelinePhaseSteps(
    code: string | null | undefined,
    failedPhaseCodeOrRegistrationMode: string | null = null,
    registrationModeOrCropPolicy: RegistrationMode | string | null = 'fiducial_anchored',
    trailingCropPolicy?: string | null,
): OmrPipelineStep[] {
    const secondArgumentIsRegistrationMode =
        failedPhaseCodeOrRegistrationMode === 'fiducial_anchored' ||
        failedPhaseCodeOrRegistrationMode === 'document_bound';
    const thirdArgumentIsRegistrationMode =
        registrationModeOrCropPolicy === 'fiducial_anchored' ||
        registrationModeOrCropPolicy === 'document_bound';
    const failedPhaseCode = secondArgumentIsRegistrationMode
        ? null
        : failedPhaseCodeOrRegistrationMode;
    const registrationMode = secondArgumentIsRegistrationMode
        ? (failedPhaseCodeOrRegistrationMode as RegistrationMode)
        : thirdArgumentIsRegistrationMode
          ? (registrationModeOrCropPolicy as RegistrationMode)
          : 'fiducial_anchored';
    const cropPolicy = secondArgumentIsRegistrationMode
        ? thirdArgumentIsRegistrationMode
            ? trailingCropPolicy
            : registrationModeOrCropPolicy
        : trailingCropPolicy;
    const normalized =
        typeof code === 'string' ? code.toLowerCase().trim() : '';
    const normalizedFailedPhase =
        typeof failedPhaseCode === 'string'
            ? failedPhaseCode.toLowerCase().trim()
            : '';
    const terminalSuccess = normalized === OMR_PIPELINE_PHASES.PASSED;
    const terminalReview = normalized === OMR_PIPELINE_PHASES.AWAITING_REVIEW;
    const terminalFailure = normalized === OMR_PIPELINE_PHASES.FAILED;
    const activeCode = terminalFailure ? normalizedFailedPhase : normalized;
    const phaseIndexes = OMR_ANALYSIS_STEPS.reduce<number[]>(
        (indexes, step, index) => {
            if (step.code === activeCode) indexes.push(index);
            return indexes;
        },
        [],
    );
    const firstActiveIndex = phaseIndexes[0] ?? -1;
    const lastActiveIndex = phaseIndexes.at(-1) ?? -1;

    return OMR_ANALYSIS_STEPS.map((sourceStep, index) => {
        const step =
            registrationMode === 'document_bound' &&
            cropPolicy === 'keep_original' &&
            sourceStep.code === OMR_PIPELINE_PHASES.ORIENTATION
                ? {
                      ...sourceStep,
                      label: 'Preserving original full-page frame',
                  }
                : registrationMode === 'document_bound' &&
                    cropPolicy === 'keep_original' &&
                    sourceStep.code === OMR_PIPELINE_PHASES.LAYOUT_VERIFICATION
                  ? {
                        ...sourceStep,
                        label: 'Verifying original page layout',
                    }
                  : registrationMode === 'document_bound' &&
                      sourceStep.code === OMR_PIPELINE_PHASES.ORIENTATION
                    ? { ...sourceStep, label: 'Checking page orientation' }
                    : registrationMode === 'document_bound' &&
                        sourceStep.code === OMR_PIPELINE_PHASES.LAYOUT_VERIFICATION
                      ? { ...sourceStep, label: 'Verifying document boundary' }
                      : sourceStep;
        if (terminalSuccess) return { ...step, state: 'complete' };
        if (terminalReview) {
            return {
                ...step,
                state:
                    index === OMR_ANALYSIS_STEPS.length - 1
                        ? 'current'
                        : 'complete',
            };
        }
        if (terminalFailure) {
            if (phaseIndexes.length === 0) {
                return {
                    ...step,
                    state:
                        index === OMR_ANALYSIS_STEPS.length - 1
                            ? 'failed'
                            : 'pending',
                };
            }
            return {
                ...step,
                state:
                    index < firstActiveIndex
                        ? 'complete'
                        : index <= lastActiveIndex
                          ? 'failed'
                          : 'pending',
            };
        }
        if (phaseIndexes.length === 0) return { ...step, state: 'pending' };
        return {
            ...step,
            state:
                index < firstActiveIndex
                    ? 'complete'
                    : index <= lastActiveIndex
                      ? 'current'
                      : 'pending',
        };
    });
}

/**
 * Read the server-owned phase code from a phase detail projection.
 * Unknown codes remain available to the label resolver so newer workers
 * degrade to a neutral progress message instead of throwing.
 */
export function getPipelinePhaseCode(
    detail: { phase_code?: unknown; stage?: unknown } | null | undefined,
): string | null {
    if (!detail || typeof detail !== 'object') return null;
    const code = detail.phase_code ?? detail.stage;
    return typeof code === 'string' && code.trim()
        ? code.trim().toLowerCase()
        : null;
}

/**
 * Resolve a friendly human-readable label for any emitted worker phase,
 * with a graceful neutral fallback for unknown or future backend codes.
 */
export function getPipelinePhaseLabel(
    code: string | null | undefined,
    registrationMode: RegistrationMode = 'fiducial_anchored',
    cropPolicy?: string | null,
): string {
    if (!code || typeof code !== 'string') {
        return 'Analysis in progress…';
    }
    const normalized = code.toLowerCase().trim();
    if (
        registrationMode === 'document_bound' &&
        cropPolicy === 'keep_original' &&
        (normalized === OMR_PIPELINE_PHASES.ORIENTATION ||
            normalized === OMR_PIPELINE_PHASES.LAYOUT_VERIFICATION)
    ) {
        return 'Verifying original page layout and content…';
    }
    if (registrationMode === 'document_bound') {
        if (normalized === OMR_PIPELINE_PHASES.ORIENTATION)
            return 'Checking page orientation…';
        if (normalized === OMR_PIPELINE_PHASES.LAYOUT_VERIFICATION)
            return 'Verifying document boundary and content…';
    }
    return (
        OMR_PHASE_LABELS[normalized as OmrPipelinePhaseCode] ??
        'Analysis in progress…'
    );
}
