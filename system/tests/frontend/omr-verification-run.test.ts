import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import {
    boundedInterval,
    captureSourceForFile,
    challengeRecovery,
    createVerificationPoller,
    currentPhysicalTestPackReady,
    currentPhysicalCaptureEvidence,
    currentPhysicalSelectionComplete,
    deriveCheckpointStates,
    deriveCurrentCheckpoint,
    digitalPassed,
    evidenceWithCaptureUrls,
    groupExpectedMarks,
    isTerminalRunStatus,
    mappingComplete,
    nextPageToCapture,
    progressText,
    registrationProfileReady,
    RUN_STATUS_AWAITING_CAPTURE,
    RUN_STATUS_AWAITING_PRINT,
    RUN_STATUS_FAILED,
    RUN_STATUS_NEEDS_REVIEW,
    RUN_STATUS_PASSED,
    RUN_STATUS_VARIANT_MATRIX,
    type RunCapture,
    type RunStatusPayload,
} from '../../resources/js/lib/omrVerificationRun.js';
// OpenSpec 35.38–35.41: three Studio-visible checkpoints, real polling, gate labels.
// Pure-module contract tests in the same style as the existing frontend suite.

function baseRun(overrides: Partial<RunStatusPayload> = {}): RunStatusPayload {
    const mode = overrides.mode ?? 'digital';
    const status = overrides.status ?? RUN_STATUS_VARIANT_MATRIX;
    return {
        run_id: 7,
        status,
        phase: RUN_STATUS_VARIANT_MATRIX,
        phase_detail: null,
        mode,
        attempt: 1,
        progress: { done: 0, total: 1, percent: 0 },
        current_variant: null,
        safe_issue: null,
        result_summary: null,
        available_actions: [],
        run_version: 1,
        target_current: true,
        target_stale: false,
        digital_verification_current:
            mode === 'digital' && status === RUN_STATUS_PASSED,
        plan: {
            plan_hash: 'h',
            plan_version: 'v1',
            challenge_code: null,
            expected_vector_hash: null,
        },
        target: { kind: 'candidate', id: 2, hashes: {} },
        timestamps: {
            started_at: null,
            finished_at: null,
            last_heartbeat_at: null,
        },
        evidence: [],
        challenge: null,
        ...overrides,
    };
}

function currentPhysicalRun(
    run: RunStatusPayload,
    options: {
        methodState?: string;
        challengeStatus?: string;
        attempted?: boolean;
        selected?: boolean;
    } = {},
): RunStatusPayload {
    const methodState =
        options.methodState ??
        (run.status === RUN_STATUS_PASSED ? 'passed' : 'awaiting_capture');
    const challengeStatus =
        options.challengeStatus ??
        (run.status === RUN_STATUS_AWAITING_PRINT ? 'created' : 'printed');
    const challengeId = run.challenge?.challenge_id ?? 9;
    const targetHash = 'artifact-current';
    const attempted = options.attempted ?? methodState !== 'challenge_ready';
    const selected = options.selected ?? methodState === 'passed';
    const target = {
        ...run.target,
        hashes: { ...run.target.hashes, artifact: targetHash },
    };
    const methods = ['pre_shaded', 'hand_shaded'].map((method) => ({
        method,
        state: methodState,
        target_kind: target.kind,
        target_id: target.id,
        target_hash: targetHash,
        challenge_id: challengeId,
        challenge: { status: challengeStatus },
        pages: [
            {
                page_no: 1,
                required: true,
                state: methodState,
                selected_attempt_id: selected ? challengeId : null,
                selected_attempt: selected ? { capture_id: challengeId } : null,
            },
        ],
        attempts: attempted
            ? [
                  {
                      capture_id: challengeId,
                      page_no: 1,
                      attempt_state: selected ? 'passed' : 'needs_review',
                      status: selected ? 'pass' : 'fail',
                  },
              ]
            : [],
    }));

    return { ...run, target, methods };
}

function baseDraft(overrides: Record<string, unknown> = {}) {
    const stage =
        typeof overrides.stage === 'string'
            ? overrides.stage
            : 'mapping_complete';
    const hasExplicitDigitalFlag =
        Object.prototype.hasOwnProperty.call(overrides, 'digital_verified') ||
        Object.prototype.hasOwnProperty.call(
            overrides,
            'digital_verification_current',
        );
    return {
        stage,
        compiled_version_id: null,
        compiled_version_status: null,
        physical_qualified: false,
        print_pdf_url: null,
        mapping_current: [
            'mapping_complete',
            'digital_verified',
            'compiled',
        ].includes(stage),
        digital_verified: stage === 'digital_verified' || stage === 'compiled',
        digital_verification_current:
            !hasExplicitDigitalFlag &&
            (stage === 'digital_verified' || stage === 'compiled'),
        ...overrides,
    };
}

const CAPTURE_EVIDENCE: RunCapture = {
    capture_id: 1,
    attempt: 1,
    page_no: 1,
    variant: null,
    expected: 'pass',
    actual: 'pass',
    status: 'pass' as const,
    error_code: null,
    input_hash: 'a'.repeat(64),
    evidence_hash: 'b'.repeat(64),
    expected_vector_hash: null,
    detected_vector_hash: null,
    vector_match: true,
    captured_at: '2026-08-26T10:00:00+00:00',
    urls: {
        input: '/runs/7/assets/11',
        aligned: '/runs/7/assets/12',
        overlay: '/runs/7/assets/13',
    },
    summary: {
        gates: {
            marker_count: 'pass',
            registration: 'pass',
            dimensions: 'pass',
        },
        evidence: {
            outcome: 'succeeded',
            page_no: 1,
            items: [
                {
                    label: 'LOGIC_Q_001',
                    page_no: 1,
                    expected: 'B',
                    detected: { state: 'marked', selected_option: 'B' },
                    exact: true,
                    options: [],
                },
                {
                    label: 'LOGIC_Q_002',
                    page_no: 1,
                    expected: 'C',
                    detected: { state: 'ambiguous' },
                    exact: false,
                    options: [],
                },
            ],
            identity: [],
            assets: { captured: null, aligned: null, overlay: null },
        },
    },
};

describe('three checkpoints by phase (35.38)', () => {
    it('keeps print and capture in one physical checkpoint', () => {
        const states = deriveCheckpointStates({
            run: null,
            draft: baseDraft(),
            urls: {},
        });
        expect(states.map((s) => s.key)).toEqual([
            'digital',
            'physical_challenge',
            'qualification',
        ]);
        expect(states.map((s) => s.step)).toEqual([1, 2, 3]);
        expect(states.every((s) => s.purpose.length > 10)).toBe(true);
    });

    it('before any run: digital is ready, everything else blocked on prerequisites', () => {
        const states = deriveCheckpointStates({
            run: null,
            draft: baseDraft(),
            urls: { digital_verification: '/verify' },
        });
        const [digital, physical, qualification] = states;
        expect(digital.status).toBe('pending');
        expect(digital.nextAction).toBe('Run digital verification');
        expect(digital.actionAvailable).toBe(true);
        expect(physical.status).toBe('blocked');
        expect(physical.prerequisites.join(' ')).toContain(
            'Digital engine check',
        );
        expect(qualification.status).toBe('blocked');
        expect(qualification.prerequisites.join(' ')).toContain(
            'Compile the digitally verified candidate',
        );
    });

    it('blocks digital when Recognition Mapping is not complete', () => {
        const states = deriveCheckpointStates({
            run: null,
            draft: baseDraft({ stage: 'page_prepared' }),
            urls: {},
        });
        expect(states[0].status).toBe('blocked');
        expect(states[0].prerequisites.join(' ')).toContain(
            'Complete Recognition Mapping',
        );
    });

    it('ignores a stale physical run and requires an explicit current digital authority', () => {
        const staleQuick = baseRun({
            status: RUN_STATUS_PASSED,
            mode: 'quick_rehearsal',
            target_current: false,
            target_stale: true,
            target: {
                kind: 'candidate',
                id: 2,
                hashes: { artifact: 'old', mapping: 'old' },
            },
        });
        const draft = baseDraft({
            stage: 'mapping_complete',
            digital_verified: false,
        });
        const input = { run: staleQuick, draft, urls: {} };
        const states = deriveCheckpointStates(input);

        expect(digitalPassed(input)).toBe(false);
        expect(mappingComplete(input)).toBe(true);
        expect(states[0].status).toBe('pending');
        expect(states[1].status).toBe('blocked');
        expect(states[0].status).not.toBe('done');
        expect(states[1].status).not.toBe('done');

        const currentDigital = baseRun({
            status: RUN_STATUS_PASSED,
            mode: 'digital',
            target_current: true,
            target_stale: false,
        });
        expect(
            digitalPassed({
                run: currentDigital,
                draft: baseDraft({ digital_verified: false }),
                urls: {},
            }),
        ).toBe(true);
    });

    it('shows real phase, variant, and completed/total while the digital matrix runs', () => {
        const run = baseRun({
            phase_detail: {
                stage: 'variant_matrix',
                current_variant: 'rotation',
            },
            progress: { done: 5, total: 12, percent: 41.7 },
            current_variant: 'rotation',
        });
        const [digital] = deriveCheckpointStates({
            run,
            draft: baseDraft(),
            urls: {},
        });
        expect(digital.status).toBe('active');
        expect(digital.statusText).toContain('rotation');
        // Real counts only — the derivation never synthesizes its own percentage.
        expect(digital.evidenceSummary).toContain('5/12');
        expect(digital.evidenceSummary).not.toContain('%');
        expect(progressText(run.progress)).toBe('5/12');
    });

    it('exposes retry when the digital run failed and keeps later checkpoints blocked even if draft stage is digital_verified', () => {
        const run = baseRun({
            status: RUN_STATUS_FAILED,
            mode: 'digital',
            safe_issue: {
                code: 'worker_timeout',
                detail: 'The worker stopped responding.',
            },
        });
        const [digital, physical] = deriveCheckpointStates({
            run,
            draft: baseDraft({
                stage: 'digital_verified',
                digital_verification_current: false,
            }),
            urls: { digital_verification: '/verify' },
        });
        expect(digital.status).toBe('active');
        expect(digital.retryAvailable).toBe(true);
        expect(digital.nextAction).toBe('Retry digital verification');
        expect(digital.statusText).toContain('worker');
        expect(physical.status).toBe('blocked');
        expect(physical.prerequisites.join(' ')).toContain(
            'Digital engine check',
        );
    });

    it('marks digital done after a passed run and unlocks the physical tests', () => {
        const run = baseRun({ status: RUN_STATUS_PASSED, mode: 'digital' });
        const [digital, physical] = deriveCheckpointStates({
            run,
            draft: baseDraft({ stage: 'digital_verified' }),
            urls: {},
            markerProfileStatus: 'qualified',
        });
        expect(digital.status).toBe('done');
        expect(physical.status).toBe('pending');
        expect(physical.label).toBe('Physical tests');
        expect(physical.nextAction).toBe('Create the physical test sheets');
        // Candidate drafts can only rehearse quickly.
        expect(physical.rehearsalOnly).toBe(true);
    });

    it('does not complete physical tests from run-level awaiting_capture without methods', () => {
        const run = baseRun({
            status: RUN_STATUS_AWAITING_CAPTURE,
            mode: 'quick_rehearsal',
            challenge: {
                challenge_id: 9,
                challenge_code: 'STALE-PACK',
                mode: 'quick',
                status: 'printed',
                plan_hash: 'p',
                expected_vector_hash: 'v',
                created_at: null,
                print_url: '/print',
                guide_url: '/guide',
            },
        });
        const [, physical] = deriveCheckpointStates({
            run,
            draft: baseDraft({ stage: 'digital_verified' }),
            urls: {},
        });
        expect(physical.status).toBe('active');
        expect(physical.status).not.toBe('done');
    });

    it('keeps physical tests active after both packs reach capture', () => {
        const run = currentPhysicalRun(
            baseRun({
                status: RUN_STATUS_AWAITING_CAPTURE,
                mode: 'quick_rehearsal',
                available_actions: ['submit_capture'],
                challenge: {
                    challenge_id: 9,
                    challenge_code: 'PACK-READY',
                    mode: 'quick',
                    status: 'printed',
                    plan_hash: 'p',
                    expected_vector_hash: 'v',
                    created_at: null,
                    print_url: '/print',
                    guide_url: '/guide',
                },
            }),
            {
                methodState: 'awaiting_capture',
                challengeStatus: 'printed',
                attempted: false,
            },
        );
        const [, physical] = deriveCheckpointStates({
            run,
            draft: baseDraft({ stage: 'digital_verified' }),
            urls: {},
        });
        expect(currentPhysicalTestPackReady(run)).toBe(true);
        expect(currentPhysicalCaptureEvidence(run)).toBe(false);
        expect(currentPhysicalSelectionComplete(run)).toBe(false);
        expect(physical.status).toBe('active');
        expect(physical.nextAction).toContain('Capture page');
        expect(run.methods?.every((method) => method.state !== 'passed')).toBe(
            true,
        );
    });

    it('awaiting_print makes the physical challenge the active checkpoint with print action', () => {
        const run = currentPhysicalRun(
            baseRun({
                status: RUN_STATUS_AWAITING_PRINT,
                mode: 'quick_rehearsal',
                available_actions: ['print_challenge'],
                challenge: {
                    challenge_id: 9,
                    challenge_code: 'CHAL-ABC123',
                    mode: 'quick',
                    status: 'created',
                    plan_hash: 'p',
                    expected_vector_hash: 'v',
                    created_at: null,
                    print_url: '/runs/7/assets/21',
                    guide_url: '/runs/7/assets/22',
                },
            }),
            {
                methodState: 'challenge_ready',
                challengeStatus: 'created',
                attempted: false,
            },
        );
        const [, physical] = deriveCheckpointStates({
            run,
            draft: baseDraft({ stage: 'digital_verified' }),
            urls: {},
        });
        expect(physical.status).toBe('active');
        expect(physical.nextAction).toContain('Complete pre-shaded first');
        expect(physical.actionAvailable).toBe(true);
        expect(physical.evidenceSummary).toContain('Printable');
    });

    it('failed quick run with a created challenge resumes the physical print checkpoint', () => {
        const run = currentPhysicalRun(
            baseRun({
                status: RUN_STATUS_FAILED,
                mode: 'quick_rehearsal',
                available_actions: ['retry'],
                challenge: {
                    challenge_id: 9,
                    challenge_code: 'CHAL-FAILED-PRINT',
                    mode: 'quick',
                    status: 'created',
                    plan_hash: 'p',
                    expected_vector_hash: 'v',
                    created_at: null,
                    print_url: '/runs/7/assets/21',
                    guide_url: '/runs/7/assets/22',
                },
            }),
            {
                methodState: 'challenge_ready',
                challengeStatus: 'created',
                attempted: false,
            },
        );
        const [, physical] = deriveCheckpointStates({
            run,
            draft: baseDraft({ stage: 'digital_verified' }),
            urls: {},
        });

        expect(physical.status).toBe('active');
        expect(physical.nextAction).toContain('Resume the existing sheets');
        expect(physical.nextAction).not.toContain('Create');
        expect(physical.retryAvailable).toBe(true);
    });

    it('failed printed challenge resumes capture inside the physical checkpoint', () => {
        const run = currentPhysicalRun(
            baseRun({
                status: RUN_STATUS_FAILED,
                mode: 'full_qualification',
                available_actions: ['retry'],
                target: { kind: 'compiled_version', id: 3, hashes: {} },
                challenge: {
                    challenge_id: 9,
                    challenge_code: 'CHAL-FAILED-CAPTURE',
                    mode: 'full',
                    status: 'printed',
                    plan_hash: 'p',
                    expected_vector_hash: 'v',
                    created_at: null,
                    print_url: '/runs/7/assets/21',
                    guide_url: '/runs/7/assets/22',
                },
            }),
            {
                methodState: 'awaiting_capture',
                challengeStatus: 'printed',
                attempted: false,
            },
        );
        const states = deriveCheckpointStates({
            run,
            draft: baseDraft({ stage: 'compiled', compiled_version_id: 3 }),
            urls: {},
        });

        expect(states[1].status).toBe('active');
        expect(states[1].nextAction).toContain('Resume capture');
        expect(states[1].retryAvailable).toBe(true);
        expect(deriveCurrentCheckpoint(states).key).toBe('physical_challenge');
    });

    it('awaiting_capture stays in physical tests with the next page', () => {
        const run = baseRun({
            status: RUN_STATUS_AWAITING_CAPTURE,
            mode: 'quick_rehearsal',
            available_actions: ['submit_capture'],
            challenge: {
                challenge_id: 9,
                challenge_code: 'CHAL-ABC123',
                mode: 'quick',
                status: 'printed',
                plan_hash: 'p',
                expected_vector_hash: 'v',
                created_at: null,
                print_url: '/runs/7/assets/21',
                guide_url: '/runs/7/assets/22',
            },
        });
        const [, physical, qualification] = deriveCheckpointStates({
            run,
            draft: baseDraft({ stage: 'digital_verified' }),
            urls: {},
        });
        expect(physical.status).toBe('active');
        expect(physical.nextAction).toContain('Complete pre-shaded first');
        expect(physical.actionAvailable).toBe(true);
        expect(qualification.status).toBe('blocked');
    });

    it('keeps physical tests active while any required page still needs review', () => {
        const run = currentPhysicalRun(
            baseRun({
                status: RUN_STATUS_NEEDS_REVIEW,
                mode: 'quick_rehearsal',
                available_actions: ['review_capture'],
                evidence: [CAPTURE_EVIDENCE],
            }),
            {
                methodState: 'needs_review',
                challengeStatus: 'needs_review',
                attempted: true,
            },
        );
        run.methods![1] = {
            ...run.methods![1],
            state: 'awaiting_capture',
            attempts: [],
        };

        const [, physical] = deriveCheckpointStates({
            run,
            draft: baseDraft({ stage: 'digital_verified' }),
            urls: {},
        });
        expect(physical.status).toBe('active');
        expect(physical.statusText).toBe(
            'Capture needs review — inspect and accept the remaining physical test page',
        );
        expect(physical.nextAction).toBe(
            'Review the flagged page in its physical test card and accept or retake',
        );
        expect(physical.actionAvailable).toBe(true);
    });

    it('keeps needs_review in Physical tests and groups expected marks per page', () => {
        const run = currentPhysicalRun(
            baseRun({
                status: RUN_STATUS_NEEDS_REVIEW,
                mode: 'full_qualification',
                available_actions: ['review_capture'],
                target: { kind: 'compiled_version', id: 3, hashes: {} },
                evidence: [CAPTURE_EVIDENCE],
            }),
            {
                methodState: 'needs_review',
                challengeStatus: 'needs_review',
                attempted: true,
                selected: false,
            },
        );
        const [, physical] = deriveCheckpointStates({
            run,
            draft: baseDraft({ stage: 'compiled', compiled_version_id: 3 }),
            urls: {},
        });
        expect(physical.status).toBe('active');
        expect(physical.statusText).toBe(
            'Capture needs review — inspect and accept the remaining physical test page',
        );
        expect(physical.nextAction).toBe(
            'Review the flagged page in its physical test card and accept or retake',
        );
        expect(physical.actionAvailable).toBe(true);
        expect(physical.evidenceSummary).toContain('2 expected marks on page 1');
        expect(physical.evidenceSummary).toContain('1 exact');
        expect(physical.evidenceSummary).toContain('1 ambiguous');
        const groups = groupExpectedMarks(run.evidence);
        expect(groups).toEqual([
            {
                page_no: 1,
                total: 2,
                expected: 2,
                exact: 1,
                ambiguous: 1,
                missing: 0,
            },
        ]);
    });

    it('a passed full challenge activates the qualification checkpoint with measurements', () => {
        const run = currentPhysicalRun(
            baseRun({
                status: RUN_STATUS_PASSED,
                mode: 'full_qualification',
                target: { kind: 'compiled_version', id: 3, hashes: {} },
                evidence: [CAPTURE_EVIDENCE],
            }),
            {
                methodState: 'passed',
                challengeStatus: 'passed',
                attempted: true,
                selected: true,
            },
        );
        const states = deriveCheckpointStates({
            run,
            draft: baseDraft({ stage: 'compiled', compiled_version_id: 3 }),
            urls: {},
            physicalRunId: 7,
            markerProfileStatus: 'qualified',
        });
        const qualification = states[2];
        expect(states[1].status).toBe('done');
        expect(qualification.status).toBe('active');
        expect(qualification.nextAction).toContain(
            'Record printer/device/paper measurements',
        );
        expect(qualification.actionAvailable).toBe(true);
        expect(qualification.rehearsalOnly).toBe(false);
    });

    it('physical_qualified marks qualification done and enables activation', () => {
        const run = currentPhysicalRun(
            baseRun({
                status: RUN_STATUS_PASSED,
                mode: 'full_qualification',
                target: { kind: 'compiled_version', id: 3, hashes: {} },
            }),
            {
                methodState: 'passed',
                challengeStatus: 'passed',
                attempted: true,
                selected: true,
            },
        );
        const states = deriveCheckpointStates({
            run,
            draft: baseDraft({
                stage: 'compiled',
                compiled_version_id: 3,
                physical_qualified: true,
            }),
            urls: {},
            physicalRunId: 7,
        });
        const qualification = states[2];
        expect(qualification.status).toBe('done');
        expect(qualification.activationAvailable).toBe(true);
        expect(qualification.activationReason).toBeNull();
    });
});

describe('current checkpoint focus selection', () => {
    it('selects digital as current when mapping is ready but run has not started', () => {
        const states = deriveCheckpointStates({
            run: null,
            draft: baseDraft(),
            urls: { digital_verification: '/verify' },
        });
        const current = deriveCurrentCheckpoint(states);
        expect(current.key).toBe('digital');
        expect(current.step).toBe(1);
        expect(current.status).toBe('pending');
    });

    it('selects digital as current with retry available when digital verification fails', () => {
        const run = baseRun({
            status: RUN_STATUS_FAILED,
            mode: 'digital',
            safe_issue: {
                code: 'matrix_fail',
                detail: 'Proof matrix mismatch',
            },
        });
        const states = deriveCheckpointStates({
            run,
            draft: baseDraft(),
            urls: { digital_verification: '/verify' },
        });
        const current = deriveCurrentCheckpoint(states);
        expect(current.key).toBe('digital');
        expect(current.status).toBe('active');
        expect(current.retryAvailable).toBe(true);
    });

    it('selects digital as current while the digital matrix run is in progress', () => {
        const run = baseRun({
            status: RUN_STATUS_VARIANT_MATRIX,
            mode: 'digital',
            progress: { done: 3, total: 10, percent: 30 },
        });
        const states = deriveCheckpointStates({
            run,
            draft: baseDraft(),
            urls: {},
        });
        const current = deriveCurrentCheckpoint(states);
        expect(current.key).toBe('digital');
        expect(current.status).toBe('active');
    });

    it('selects physical challenge as current after digital passes', () => {
        const run = baseRun({ status: RUN_STATUS_PASSED, mode: 'digital' });
        const states = deriveCheckpointStates({
            run,
            draft: baseDraft({ stage: 'digital_verified' }),
            urls: {},
            markerProfileStatus: 'qualified',
        });
        const current = deriveCurrentCheckpoint(states);
        expect(current.key).toBe('physical_challenge');
        expect(current.step).toBe(2);
        expect(current.status).toBe('pending');
    });

    it('selects physical challenge as current when awaiting print', () => {
        const run = baseRun({
            status: RUN_STATUS_AWAITING_PRINT,
            mode: 'quick_rehearsal',
            available_actions: ['print_challenge'],
            challenge: {
                challenge_id: 9,
                challenge_code: 'CHAL-1',
                mode: 'quick',
                status: 'created',
                plan_hash: 'p',
                expected_vector_hash: 'v',
                created_at: null,
                print_url: '/print',
                guide_url: '/guide',
            },
        });
        const states = deriveCheckpointStates({
            run,
            draft: baseDraft({ stage: 'digital_verified' }),
            urls: {},
        });
        const current = deriveCurrentCheckpoint(states);
        expect(current.key).toBe('physical_challenge');
        expect(current.status).toBe('active');
        expect(current.actionAvailable).toBe(true);
    });

    it('selects physical tests as current when awaiting capture', () => {
        const run = currentPhysicalRun(
            baseRun({
                status: RUN_STATUS_AWAITING_CAPTURE,
                mode: 'quick_rehearsal',
                available_actions: ['submit_capture'],
                challenge: {
                    challenge_id: 9,
                    challenge_code: 'CHAL-1',
                    mode: 'quick',
                    status: 'printed',
                    plan_hash: 'p',
                    expected_vector_hash: 'v',
                    created_at: null,
                    print_url: '/print',
                    guide_url: '/guide',
                },
            }),
            {
                methodState: 'awaiting_capture',
                challengeStatus: 'printed',
                attempted: false,
            },
        );
        const current = deriveCurrentCheckpoint(
            deriveCheckpointStates({
                run,
                draft: baseDraft({ stage: 'digital_verified' }),
                urls: {},
            }),
        );
        expect(current.key).toBe('physical_challenge');
        expect(current.step).toBe(2);
        expect(current.status).toBe('active');
    });

    it('selects Physical tests as current when needs_review', () => {
        const run = currentPhysicalRun(
            baseRun({
                status: RUN_STATUS_NEEDS_REVIEW,
                mode: 'full_qualification',
                available_actions: ['review_capture'],
                target: { kind: 'compiled_version', id: 3, hashes: {} },
                evidence: [CAPTURE_EVIDENCE],
            }),
            {
                methodState: 'needs_review',
                challengeStatus: 'needs_review',
                attempted: true,
                selected: false,
            },
        );
        const states = deriveCheckpointStates({
            run,
            draft: baseDraft({ stage: 'compiled', compiled_version_id: 3 }),
            urls: {},
        });
        const current = deriveCurrentCheckpoint(states);
        expect(current.key).toBe('physical_challenge');
        expect(current.step).toBe(2);
        expect(current.status).toBe('active');
    });

    it('selects qualification as current when full challenge passes and evidence is ready', () => {
        const run = currentPhysicalRun(
            baseRun({
                status: RUN_STATUS_PASSED,
                mode: 'full_qualification',
                target: { kind: 'compiled_version', id: 3, hashes: {} },
                evidence: [CAPTURE_EVIDENCE],
            }),
            {
                methodState: 'passed',
                challengeStatus: 'passed',
                attempted: true,
                selected: true,
            },
        );
        const states = deriveCheckpointStates({
            run,
            draft: baseDraft({ stage: 'compiled', compiled_version_id: 3 }),
            urls: {},
            physicalRunId: 7,
            markerProfileStatus: 'qualified',
        });
        const current = deriveCurrentCheckpoint(states);
        expect(current.key).toBe('qualification');
        expect(current.step).toBe(3);
        expect(current.status).toBe('active');
    });

    it('selects qualification as final current step when physical_qualified is true and all steps done', () => {
        const run = currentPhysicalRun(
            baseRun({
                status: RUN_STATUS_PASSED,
                mode: 'full_qualification',
                target: { kind: 'compiled_version', id: 3, hashes: {} },
                evidence: [CAPTURE_EVIDENCE],
            }),
            {
                methodState: 'passed',
                challengeStatus: 'passed',
                attempted: true,
                selected: true,
            },
        );
        const states = deriveCheckpointStates({
            run,
            draft: baseDraft({
                stage: 'compiled',
                compiled_version_id: 3,
                physical_qualified: true,
            }),
            urls: {},
            physicalRunId: 7,
            markerProfileStatus: 'qualified',
        });
        const current = deriveCurrentCheckpoint(states);
        expect(current.key).toBe('qualification');
        expect(current.step).toBe(3);
        expect(current.status).toBe('done');
    });

    it('selects first blocked step (digital) when mapping is incomplete', () => {
        const states = deriveCheckpointStates({
            run: null,
            draft: baseDraft({ stage: 'page_prepared' }),
            urls: {},
        });
        const current = deriveCurrentCheckpoint(states);
        expect(current.key).toBe('digital');
        expect(current.status).toBe('blocked');
    });
});

describe('gate labels and honesty (35.41)', () => {
    it('labels candidate rehearsals "Rehearsal only" and never offers qualification', () => {
        const run = currentPhysicalRun(
            baseRun({
                status: RUN_STATUS_PASSED,
                mode: 'quick_rehearsal',
                target: { kind: 'candidate', id: 2, hashes: {} },
                challenge: {
                    challenge_id: 9,
                    challenge_code: 'CHAL-ABC123',
                    mode: 'quick',
                    status: 'passed',
                    plan_hash: 'p',
                    expected_vector_hash: 'v',
                    created_at: null,
                    print_url: null,
                    guide_url: null,
                },
            }),
            {
                methodState: 'passed',
                challengeStatus: 'passed',
                attempted: true,
                selected: true,
            },
        );
        const states = deriveCheckpointStates({
            run,
            draft: baseDraft({ stage: 'digital_verified' }),
            urls: {},
        });
        const qualification = states[2];
        expect(qualification.rehearsalOnly).toBe(true);
        expect(qualification.status).toBe('blocked');
        expect(qualification.statusText).toBe('Rehearsal only');
        expect(qualification.prerequisites.join(' ')).toContain(
            'never qualify',
        );
        expect(qualification.actionAvailable).toBe(false);
        expect(qualification.activationAvailable).toBe(false);
        expect(qualification.activationReason).toContain(
            'Activation remains unavailable',
        );
        // The rehearsal banner is driven by at least one rehearsalOnly checkpoint.
        expect(states.some((s) => s.rehearsalOnly)).toBe(true);
        expect(deriveCurrentCheckpoint(states).key).toBe('qualification');
    });

    it('a failed full challenge blocks qualification on genuine evidence', () => {
        const run = baseRun({
            status: RUN_STATUS_FAILED,
            mode: 'full_qualification',
            target: { kind: 'compiled_version', id: 3, hashes: {} },
            safe_issue: {
                code: 'answer_vector_mismatch',
                detail: 'Detected vector differs from the plan.',
            },
        });
        const qualification = deriveCheckpointStates({
            run,
            draft: baseDraft({ stage: 'compiled', compiled_version_id: 3 }),
            urls: {},
        })[2];
        expect(qualification.status).toBe('blocked');
        expect(qualification.prerequisites.join(' ')).toContain(
            'exact expected vector',
        );
        expect(qualification.activationAvailable).toBe(false);
    });

    it('keeps activation unavailable before qualification even when compiled', () => {
        const run = baseRun({ status: RUN_STATUS_PASSED, mode: 'digital' });
        const states = deriveCheckpointStates({
            run,
            draft: baseDraft({ stage: 'compiled', compiled_version_id: 3 }),
            urls: {},
        });
        expect(states[2].activationAvailable).toBe(false);
        expect(states[2].activationReason).toContain(
            'Activation remains unavailable',
        );
    });

    it('requires a qualified marker profile before qualification can be derived', () => {
        const run = currentPhysicalRun(
            baseRun({
                status: RUN_STATUS_PASSED,
                mode: 'full_qualification',
                target: { kind: 'compiled_version', id: 3, hashes: {} },
                evidence: [CAPTURE_EVIDENCE],
            }),
            {
                methodState: 'passed',
                challengeStatus: 'passed',
                attempted: true,
                selected: true,
            },
        );
        const qualification = deriveCheckpointStates({
            run,
            draft: baseDraft({ stage: 'compiled', compiled_version_id: 3 }),
            urls: {},
            physicalRunId: 7,
            markerProfileStatus: 'candidate',
        })[2];
        expect(qualification.status).toBe('active');
        expect(qualification.actionAvailable).toBe(false);
        expect(qualification.prerequisites.join(' ')).toContain(
            'qualified marker profile',
        );
    });
});

describe('real polling for active runs (35.39)', () => {
    beforeEach(() => {
        vi.useFakeTimers();
    });
    afterEach(() => {
        vi.useRealTimers();
    });

    function stubFetch(sequence: RunStatusPayload[]) {
        let calls = 0;
        const fetchImpl = vi.fn(async () => {
            const payload = sequence[Math.min(calls, sequence.length - 1)];
            calls += 1;
            return { ok: true, json: async () => payload };
        });
        return { fetchImpl, calls: () => calls };
    }

    it('polls at the bounded interval and stops on a terminal status', async () => {
        const onStatus = vi.fn();
        const { fetchImpl, calls } = stubFetch([
            baseRun({
                status: RUN_STATUS_VARIANT_MATRIX,
                progress: { done: 1, total: 6, percent: 16.7 },
            }),
            baseRun({ status: RUN_STATUS_PASSED, mode: 'digital' }),
        ]);
        const poller = createVerificationPoller({
            statusUrl: '/runs/7/status',
            intervalMs: 500,
            fetchImpl,
            onStatus,
        });
        poller.start();
        await vi.advanceTimersByTimeAsync(0);
        expect(onStatus).toHaveBeenCalledTimes(1);
        expect(onStatus.mock.calls[0][0].progress.done).toBe(1);
        await vi.advanceTimersByTimeAsync(500);
        expect(onStatus).toHaveBeenCalledTimes(2);
        expect(onStatus.mock.calls[1][0].status).toBe(RUN_STATUS_PASSED);
        // Terminal status stops the timer: no further fetches after more time passes.
        await vi.advanceTimersByTimeAsync(5000);
        expect(calls()).toBe(2);
    });

    it('teardown via stop() prevents any further polling', async () => {
        const onStatus = vi.fn();
        const { fetchImpl, calls } = stubFetch([
            baseRun({ status: RUN_STATUS_VARIANT_MATRIX }),
        ]);
        const poller = createVerificationPoller({
            statusUrl: '/runs/7/status',
            intervalMs: 500,
            fetchImpl,
            onStatus,
        });
        poller.start();
        await vi.advanceTimersByTimeAsync(500);
        poller.stop();
        expect(poller.isRunning()).toBe(false);
        await vi.advanceTimersByTimeAsync(5000);
        expect(calls()).toBe(2);
    });

    it('ignores a late status from a stale run or wrong mode', async () => {
        const onStatus = vi.fn();
        const onError = vi.fn();
        const { fetchImpl } = stubFetch([
            baseRun({
                run_id: 18,
                mode: 'quick_rehearsal',
                status: RUN_STATUS_AWAITING_CAPTURE,
            }),
        ]);
        const poller = createVerificationPoller({
            statusUrl: '/runs/7/status',
            expectedRunId: 7,
            expectedMode: 'digital',
            intervalMs: 500,
            fetchImpl,
            onStatus,
            onError,
        });
        poller.start();
        await vi.advanceTimersByTimeAsync(0);
        expect(onStatus).not.toHaveBeenCalled();
        expect(onError).not.toHaveBeenCalled();
        poller.stop();
    });

    it('restores and polls the active run from the server on mount (non-terminal only)', async () => {
        // Reload/re-login resume: the projection hands back the active run's status URL.
        const onStatus = vi.fn();
        const { fetchImpl, calls } = stubFetch([
            baseRun({
                status: RUN_STATUS_AWAITING_CAPTURE,
                mode: 'quick_rehearsal',
            }),
        ]);
        const poller = createVerificationPoller({
            statusUrl: '/runs/7/status',
            intervalMs: 1000,
            fetchImpl,
            onStatus,
        });
        poller.start();
        await vi.advanceTimersByTimeAsync(0);
        expect(onStatus.mock.calls[0][0].status).toBe(
            RUN_STATUS_AWAITING_CAPTURE,
        );
        // A terminal run is not polled at all by the caller.
        expect(isTerminalRunStatus(RUN_STATUS_PASSED)).toBe(true);
        expect(isTerminalRunStatus(RUN_STATUS_NEEDS_REVIEW)).toBe(false);
        poller.stop();
        await vi.advanceTimersByTimeAsync(3000);
        expect(calls()).toBe(1);
    });

    it('does not start without a status URL and reports status errors via onError', async () => {
        const onStatus = vi.fn();
        const onError = vi.fn();
        const poller = createVerificationPoller({
            statusUrl: null,
            fetchImpl: vi.fn(),
            onStatus,
            onError,
        });
        poller.start();
        expect(poller.isRunning()).toBe(false);

        const failing = vi.fn(async () => ({
            ok: false,
            json: async () => ({ detail: 'gone' }),
        }));
        const failingPoller = createVerificationPoller({
            statusUrl: '/runs/7/status',
            intervalMs: 500,
            fetchImpl: failing,
            onStatus,
            onError,
        });
        failingPoller.start();
        await vi.advanceTimersByTimeAsync(0);
        expect(onError).toHaveBeenCalledWith(
            expect.stringContaining('unavailable'),
        );
        failingPoller.stop();
    });

    it('clamps the polling interval into a bounded range', () => {
        expect(boundedInterval(null, null)).toBe(1500);
        expect(boundedInterval(100, null)).toBe(500);
        expect(boundedInterval(30000, 8000)).toBe(8000);
        expect(boundedInterval(2000, 3000)).toBe(2000);
    });

    it('shows real completed/total only and never fabricates a percentage', () => {
        expect(progressText(null)).toBe('—');
        expect(progressText({ done: 0, total: 0, percent: 0 })).toBe('—');
        expect(progressText({ done: 3, total: 10, percent: 30 })).toBe('3/10');
        expect(progressText({ done: 20, total: 10, percent: 200 })).toBe(
            '10/10',
        );
    });
});

describe('capture submission helpers', () => {
    it('classifies camera snapshots by the camera filename contract', () => {
        expect(
            captureSourceForFile({ name: 'camera-snapshot-1724688000000.jpg' }),
        ).toBe('camera');
        expect(captureSourceForFile({ name: 'IMG_1234.jpg' })).toBe('upload');
        expect(captureSourceForFile(null)).toBe('upload');
    });

    it('merges run asset URLs into normalized evidence for the shared panel', () => {
        const merged = evidenceWithCaptureUrls(CAPTURE_EVIDENCE)!;
        const assets = merged.assets as {
            captured: { url: string };
            aligned: { url: string };
            overlay: { url: string };
        };
        expect(assets.captured.url).toBe('/runs/7/assets/11');
        expect(assets.aligned.url).toBe('/runs/7/assets/12');
        expect(assets.overlay.url).toBe('/runs/7/assets/13');
        expect(merged.items).toHaveLength(2);
        expect(evidenceWithCaptureUrls(null)).toBeNull();
    });

    it('computes the next page to capture from the evidence rows', () => {
        expect(nextPageToCapture(null)).toBe(1);
        expect(nextPageToCapture(baseRun())).toBe(1);
        expect(
            nextPageToCapture(baseRun({ evidence: [CAPTURE_EVIDENCE] })),
        ).toBe(2);
        expect(
            nextPageToCapture(
                baseRun({ evidence: [{ ...CAPTURE_EVIDENCE, page_no: 20 }] }),
            ),
        ).toBe(20);
    });
});

describe('digitalPassed and draft digital_verified authority', () => {
    it('returns true when a passed digital run is present, even with stage mapping_complete', () => {
        const run = baseRun({ status: RUN_STATUS_PASSED, mode: 'digital' });
        expect(
            digitalPassed({
                run,
                draft: baseDraft({ stage: 'mapping_complete' }),
                urls: {},
            }),
        ).toBe(true);
    });

    it('returns false for stale boolean / null run on candidate draft', () => {
        expect(
            digitalPassed({
                run: null,
                draft: baseDraft({
                    stage: 'digital_verified',
                    digital_verified: true,
                }),
                urls: {},
            }),
        ).toBe(false);
    });

    it('returns false when latest digital run failed, overriding boolean and stage', () => {
        const run = baseRun({ status: RUN_STATUS_FAILED, mode: 'digital' });
        expect(
            digitalPassed({
                run,
                draft: baseDraft({
                    stage: 'digital_verified',
                    digital_verified: true,
                }),
                urls: {},
            }),
        ).toBe(false);
    });

    it('returns true for compiled versions', () => {
        expect(
            digitalPassed({
                run: null,
                draft: baseDraft({
                    stage: 'compiled',
                    compiled_version_id: 12,
                }),
                urls: {},
            }),
        ).toBe(true);
    });

    it('persisted passed run summary unlocks physical challenge checkpoint when stage is mapping_complete and marker profile is qualified', () => {
        const run = baseRun({
            status: RUN_STATUS_PASSED,
            mode: 'digital',
            result_summary: { matrix_hash: 'c9f952f5c3ed' },
        });
        const states = deriveCheckpointStates({
            run,
            draft: baseDraft({ stage: 'mapping_complete' }),
            urls: {},
            markerProfileStatus: 'qualified',
        });
        expect(states[0].status).toBe('done');
        expect(states[0].evidenceSummary).toContain('Matrix c9f952f5c3…');
        expect(states[1].status).toBe('pending');
        expect(states[1].actionAvailable).toBe(true);
    });

    it('candidate marker profile leaves physical challenge checkpoint ready for quick rehearsal after digital pass', () => {
        const run = baseRun({ status: RUN_STATUS_PASSED, mode: 'digital' });
        const states = deriveCheckpointStates({
            run,
            draft: baseDraft({ stage: 'mapping_complete' }),
            urls: {},
            markerProfileStatus: 'candidate',
        });
        expect(states[0].status).toBe('done');
        expect(states[1].status).toBe('pending');
        expect(states[1].statusText).toBe('Ready');
        expect(states[1].nextAction).toBe('Create the physical test sheets');
        expect(states[1].actionAvailable).toBe(true);
        expect(states[1].rehearsalOnly).toBe(true);
    });
    it('document-bound physical tests use the compiled sheet without a marker profile', () => {
        const draft = baseDraft({
            stage: 'digital_verified',
            registration_mode: 'document_bound',
        });
        expect(registrationProfileReady(draft, null)).toBe(true);
        expect(
            registrationProfileReady({ ...draft, marker_profile_id: 4 }, null),
        ).toBe(false);
        const beforeCompile = deriveCheckpointStates({
            run: null,
            draft,
            urls: {},
            markerProfileStatus: null,
        });
        expect(beforeCompile[1].status).toBe('pending');
        const states = deriveCheckpointStates({
            run: null,
            draft: { ...draft, compiled_version_id: 3 },
            urls: {},
            markerProfileStatus: null,
        });
        expect(states[1].status).toBe('pending');
        expect(states[1].actionAvailable).toBe(true);
        expect(states[1].rehearsalOnly).toBe(false);
    });

    it('stale boolean without matching summary leaves digital ready and physical challenge blocked', () => {
        const states = deriveCheckpointStates({
            run: null,
            draft: baseDraft({
                stage: 'digital_verified',
                digital_verified: true,
            }),
            urls: { digital_verification: '/verify' },
            markerProfileStatus: 'qualified',
        });
        expect(states[0].status).toBe('pending');
        expect(states[0].statusText).toBe('Ready');
        expect(states[0].actionAvailable).toBe(true);
        expect(states[1].status).toBe('blocked');
    });
});
describe('challengeRecovery and recovery checkpoint states', () => {
    it('returns challenge_recovery when explicitly provided on payload', () => {
        const run = baseRun({
            challenge_recovery: {
                kind: 'restart_rehearsal',
                action_url: '/runs/10/challenge/restart',
                reason_code: 'target_changed',
                detail: 'Target changed.',
            },
        });
        expect(challengeRecovery(run)).toEqual({
            kind: 'restart_rehearsal',
            action_url: '/runs/10/challenge/restart',
            reason_code: 'target_changed',
            detail: 'Target changed.',
        });
    });

    it('projects repair_reference recovery to the unified physical step', () => {
        const run = baseRun({
            status: RUN_STATUS_AWAITING_PRINT,
            mode: 'quick_rehearsal',
            challenge: {
                challenge_id: 10,
                challenge_code: 'CHAL-REP-01',
                mode: 'quick',
                status: 'created',
                plan_hash: 'abc',
                expected_vector_hash: 'def',
                created_at: null,
                print_url: '/runs/10/sheet.pdf',
                guide_url: '/runs/10/guide.pdf',
            },
            challenge_recovery: {
                kind: 'repair_reference',
                action_url: '/runs/10/challenge/regenerate',
                reason_code: 'renderer_outdated',
                detail: 'Renderer outdated.',
            },
        });

        const states = deriveCheckpointStates({
            run,
            draft: baseDraft({ stage: 'digital_verified' }),
            urls: {},
            markerProfileStatus: 'qualified',
        });

        expect(states[1].status).toBe('active');
        expect(states[1].nextAction).toBe('Update challenge sheet');
        expect(states[1].actionAvailable).toBe(true);

        expect(states[2].status).toBe('blocked');
        expect(states[2].prerequisites.join(' ')).toContain('never qualify');
        expect(states[2].actionAvailable).toBe(false);
    });

    it('projects restart_rehearsal recovery to the unified physical step', () => {
        const run = baseRun({
            status: RUN_STATUS_AWAITING_PRINT,
            mode: 'quick_rehearsal',
            challenge: {
                challenge_id: 11,
                challenge_code: 'CHAL-RES-01',
                mode: 'quick',
                status: 'created',
                plan_hash: 'abc',
                expected_vector_hash: 'def',
                created_at: null,
                print_url: '/runs/11/sheet.pdf',
                guide_url: '/runs/11/guide.pdf',
            },
            challenge_recovery: {
                kind: 'restart_rehearsal',
                action_url: '/runs/11/challenge/restart',
                reason_code: 'target_changed',
                detail: 'Target changed.',
            },
        });

        const states = deriveCheckpointStates({
            run,
            draft: baseDraft({ stage: 'digital_verified' }),
            urls: {},
            markerProfileStatus: 'qualified',
        });

        expect(states[1].status).toBe('active');
        expect(states[1].nextAction).toBe('Start new rehearsal');
        expect(states[1].actionAvailable).toBe(true);

        expect(states[2].status).toBe('blocked');
        expect(states[2].prerequisites.join(' ')).toContain('never qualify');
        expect(states[2].actionAvailable).toBe(false);
    });

    it('handles null recovery (evidence_immutable) without creating false recovery action', () => {
        const run = currentPhysicalRun(
            baseRun({
                status: RUN_STATUS_AWAITING_CAPTURE,
                mode: 'quick_rehearsal',
                challenge: {
                    challenge_id: 12,
                    challenge_code: 'CHAL-IMM-01',
                    mode: 'quick',
                    status: 'printed',
                    plan_hash: 'abc',
                    expected_vector_hash: 'def',
                    created_at: null,
                    print_url: '/runs/12/sheet.pdf',
                    guide_url: '/runs/12/guide.pdf',
                },
                challenge_recovery: {
                    kind: null,
                    action_url: null,
                    reason_code: 'evidence_immutable',
                    detail: 'Evidence exists.',
                },
                evidence: [CAPTURE_EVIDENCE],
            }),
            {
                methodState: 'awaiting_capture',
                challengeStatus: 'printed',
                attempted: true,
                selected: false,
            },
        );

        const recovery = challengeRecovery(run);
        expect(recovery?.kind).toBeNull();

        const states = deriveCheckpointStates({
            run,
            draft: baseDraft({ stage: 'digital_verified' }),
            urls: {},
            markerProfileStatus: 'qualified',
        });

        expect(states[1].status).toBe('active');
        expect(states[1].nextAction).toContain('Capture page');
        expect(states[2].status).toBe('blocked');
    });
});

describe('verification poller lifecycle races', () => {
    it('does not publish a late response after stop', async () => {
        let resolveResponse:
            | ((response: {
                  ok: boolean;
                  json: () => Promise<unknown>;
              }) => void)
            | null = null;
        const response = new Promise<{
            ok: boolean;
            json: () => Promise<unknown>;
        }>((resolve) => {
            resolveResponse = resolve;
        });
        const onStatus = vi.fn();
        const onError = vi.fn();
        const poller = createVerificationPoller({
            statusUrl: '/runs/7/status',
            intervalMs: 500,
            fetchImpl: vi.fn(() => response),
            onStatus,
            onError,
        });

        poller.start();
        expect(poller.isRunning()).toBe(true);
        poller.stop();
        resolveResponse?.({
            ok: false,
            json: async () => ({ status: RUN_STATUS_FAILED }),
        });
        await response;
        await Promise.resolve();

        expect(onStatus).not.toHaveBeenCalled();
        expect(onError).not.toHaveBeenCalled();
        expect(poller.isRunning()).toBe(false);
    });
});
