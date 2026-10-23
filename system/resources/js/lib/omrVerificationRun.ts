// Stage-3 Verification & Test Capture contract (OpenSpec 35.38–35.41, AD-6/AD-7).
// Pure module: checkpoint derivation, run polling, and gate labeling live here
// so the three-checkpoint surface is testable in Node without a browser.
// The durable run/evidence rows (not browser state) are the resume authority;
// this module only projects them and never fabricates progress.

import { computeSummary, normalizeItem } from './omrDiagnosticEvidence.js';

export const RUN_STATUS_QUEUED = 'queued';
export const RUN_STATUS_PREPARING_PROOF = 'preparing_proof';
export const RUN_STATUS_CLEAN_RECOVERY = 'clean_recovery';
export const RUN_STATUS_VARIANT_MATRIX = 'variant_matrix';
export const RUN_STATUS_AWAITING_PRINT = 'awaiting_print';
export const RUN_STATUS_AWAITING_CAPTURE = 'awaiting_capture';
export const RUN_STATUS_ANALYZING_CAPTURE = 'analyzing_capture';
export const RUN_STATUS_NEEDS_REVIEW = 'needs_review';
export const RUN_STATUS_PASSED = 'passed';
export const RUN_STATUS_FAILED = 'failed';

export type RunStatus =
    | typeof RUN_STATUS_QUEUED
    | typeof RUN_STATUS_PREPARING_PROOF
    | typeof RUN_STATUS_CLEAN_RECOVERY
    | typeof RUN_STATUS_VARIANT_MATRIX
    | typeof RUN_STATUS_AWAITING_PRINT
    | typeof RUN_STATUS_AWAITING_CAPTURE
    | typeof RUN_STATUS_ANALYZING_CAPTURE
    | typeof RUN_STATUS_NEEDS_REVIEW
    | typeof RUN_STATUS_PASSED
    | typeof RUN_STATUS_FAILED;

export type RunMode = 'digital' | 'quick_rehearsal' | 'full_qualification';
export type ChallengeMode = 'quick' | 'full';

/** Non-terminal statuses; polling continues until passed/failed. */
export const ACTIVE_RUN_STATUSES: RunStatus[] = [
    RUN_STATUS_QUEUED,
    RUN_STATUS_PREPARING_PROOF,
    RUN_STATUS_CLEAN_RECOVERY,
    RUN_STATUS_VARIANT_MATRIX,
    RUN_STATUS_AWAITING_PRINT,
    RUN_STATUS_AWAITING_CAPTURE,
    RUN_STATUS_ANALYZING_CAPTURE,
];

/** Digital phases that report real variant progress. */
export const DIGITAL_PHASES: RunStatus[] = [
    RUN_STATUS_PREPARING_PROOF,
    RUN_STATUS_CLEAN_RECOVERY,
    RUN_STATUS_VARIANT_MATRIX,
];

export function isTerminalRunStatus(status: unknown): status is RunStatus {
    return status === RUN_STATUS_PASSED || status === RUN_STATUS_FAILED;
}

export function isActiveRunStatus(status: unknown): status is RunStatus {
    return (
        typeof status === 'string' &&
        ACTIVE_RUN_STATUSES.includes(status as RunStatus)
    );
}

export interface RunProgress {
    done: number;
    total: number;
    percent: number;
}

export interface ChallengeGuideSection {
    page_no: number;
    area: string;
    options: string[];
    labels: Array<{ label: string; option: string }>;
}

export interface ChallengeGuidePayload {
    schema_version?: string;
    mode?: ChallengeMode | string;
    method?: 'pre_shaded' | 'hand_shaded' | string | null;
    challenge_code?: string;
    sections?: ChallengeGuideSection[];
    instructions?: string;
    calibration_disclaimer?: string;
    identity_columns?: Array<{ label: string; digit: number }>;
}

export interface ChallengeSheetPage {
    page_no: number;
    url: string;
    mime: string;
}

export interface RunChallenge {
    challenge_id: number;
    method?: 'pre_shaded' | 'hand_shaded' | string | null;
    challenge_code: string;
    mode: ChallengeMode;
    status: string;
    plan_hash: string | null;
    expected_vector_hash: string | null;
    created_at: string | null;
    print_url: string | null;
    guide_url: string | null;
    print_mime?: 'application/pdf' | 'image/png' | string | null;
    pdf_ready?: boolean;
    guide_pdf_url?: string | null;
    guide?: ChallengeGuidePayload | null;
    sheet_pages?: ChallengeSheetPage[];
    renderer_version?: string | null;
    reference_current?: boolean;
}

export interface ChallengeRecovery {
    kind: 'repair_reference' | 'restart_rehearsal' | null;
    action_url?: string | null;
    reason_code: string;
    detail: string;
}

export function challengeRecovery(
    run: RunStatusPayload | null | undefined,
): ChallengeRecovery | null {
    if (run?.challenge_recovery !== undefined) {
        return run.challenge_recovery;
    }
    if (isStaleChallenge(run?.challenge, run?.available_actions)) {
        return {
            kind: 'repair_reference',
            action_url: run?.run_id
                ? `/setup/omr/templates/verification-runs/${run.run_id}/challenge/regenerate`
                : null,
            reason_code: 'renderer_outdated',
            detail: 'Update challenge sheet before printing or capturing.',
        };
    }
    return null;
}

export function isChallengePdfReady(
    challenge: RunChallenge | null | undefined,
): boolean {
    if (!challenge) return false;
    if (challenge.pdf_ready === true) return true;
    if (challenge.print_mime === 'application/pdf') return true;
    if (challenge.print_mime === 'image/png') return false;
    if (challenge.pdf_ready === false) return false;
    return Boolean(challenge.print_url);
}

export function isLegacyPngChallenge(
    challenge: RunChallenge | null | undefined,
    availableActions?: string[],
): boolean {
    if (!challenge) return false;
    if (availableActions && availableActions.includes('prepare_print'))
        return true;
    if (challenge.print_mime === 'image/png') return true;
    if (challenge.pdf_ready === false) return true;
    return false;
}

export function isStaleChallenge(
    challenge: RunChallenge | null | undefined,
    availableActions?: string[],
): boolean {
    if (!challenge) return false;
    return (
        challenge.reference_current === false &&
        Boolean(
            availableActions &&
            availableActions.includes('regenerate_challenge'),
        )
    );
}
export function guideTotalExpectedMarks(
    guide: ChallengeGuidePayload | null | undefined,
): number {
    if (!guide?.sections || !Array.isArray(guide.sections)) return 0;
    return guide.sections.reduce(
        (acc, s) => acc + (Array.isArray(s.labels) ? s.labels.length : 0),
        0,
    );
}

export interface RunCapture {
    capture_id: number;
    attempt: number;
    page_no: number;
    variant: string | null;
    expected: string | null;
    actual: string | null;
    status: 'pass' | 'fail';
    error_code: string | null;
    input_hash: string | null;
    input_asset_id?: number | null;
    input_asset_sha256?: string | null;
    evidence_hash: string | null;
    expected_vector_hash: string | null;
    detected_vector_hash: string | null;
    vector_match: boolean | null;
    summary: Record<string, unknown> | null;
    captured_at: string | null;
    urls: {
        input: string | null;
        aligned: string | null;
        overlay: string | null;
    };
}

export interface RunMethodPayload {
    method: string;
    state?: string | null;
    failure_reason?: string | null;
    target_kind?: string | null;
    target_id?: number | null;
    target_hash?: string | null;
    challenge_id?: number | null;
    challenge?: { status?: string | null } | null;
    pages?: Array<{
        page_no?: number;
        page_version?: number;
        required?: boolean;
        state?: string | null;
        selected_attempt_id?: number | null;
        selected_attempt?: { capture_id?: number | null } | null;
    }>;
    attempts?: Array<{
        capture_id?: number | null;
        attempt_no?: number;
        page_no?: number;
        needs_review_count?: number | null;
        state?: string | null;
        gates_pass?: boolean | null;
        failure_reason?: string | null;
        attempt_state?: string | null;
        status?: string | null;
        actual?: string | null;
        vector_match?: boolean | null;
        failure_code?: string | null;
        error_code?: string | null;
        is_latest?: boolean;
        is_discarded?: boolean;
        can_discard?: boolean;
        lock_version?: number;
    }>;
    latest_attempt_id?: number | null;
    discarded_attempts?: Array<{
        capture_id?: number | null;
        attempt_no?: number;
        page_no?: number;
        attempt_state?: string | null;
        status?: string | null;
        actual?: string | null;
        state?: string | null;
        gates_pass?: boolean | null;
        failure_reason?: string | null;
        needs_review_count?: number | null;
        vector_match?: boolean | null;
        failure_code?: string | null;
        error_code?: string | null;
        is_latest?: boolean;
        is_discarded?: boolean;
        can_discard?: boolean;
        lock_version?: number;
    }>;
}

export interface RunStatusPayload {
    run_id: number;
    draft_lock_version?: number | null;
    status: RunStatus;
    phase: string;
    phase_detail: Record<string, unknown> | null;
    mode: RunMode;
    attempt: number;
    progress: RunProgress | null;
    current_variant: string | null;
    safe_issue: { code: string; detail: string } | null;
    result_summary: Record<string, unknown> | null;
    available_actions: string[];
    challenge_recovery?: ChallengeRecovery | null;
    run_version: number;
    /** Server authority: false/true only for an explicitly current target. */
    target_current?: boolean;
    target_stale?: boolean;
    digital_verification_current?: boolean;
    plan: {
        plan_version: string | null;
        challenge_code: string | null;
        expected_vector_hash: string | null;
    };
    target: {
        kind: 'candidate' | 'compiled_version';
        id: number;
        hashes: Record<string, string>;
    };
    timestamps: {
        started_at: string | null;
        finished_at: string | null;
        last_heartbeat_at: string | null;
    };
    evidence: RunCapture[];
    challenge: RunChallenge | null;
    methods?: RunMethodPayload[];
    physical_assurance?: Record<string, unknown> | null;
    qualification?: Record<string, unknown> | null;
}

/** Real completed/total only — the UI never computes its own percentage. */
export function progressText(progress: RunProgress | null | undefined): string {
    if (
        !progress ||
        typeof progress.done !== 'number' ||
        typeof progress.total !== 'number' ||
        progress.total <= 0
    ) {
        return '—';
    }
    return `${Math.max(0, Math.min(progress.done, progress.total))}/${progress.total}`;
}

export function phaseText(run: RunStatusPayload | null): string {
    if (!run) return '—';
    const phase = String(run.phase_detail?.stage ?? run.phase ?? run.status);
    const variant = run.current_variant ?? null;
    return variant ? `${phase} · ${variant}` : phase;
}

/** Camera snapshots are the only producer of the camera-snapshot-* filename. */
export function captureSourceForFile(
    file: { name?: string | null } | null,
): 'camera' | 'upload' {
    return typeof file?.name === 'string' &&
        file.name.startsWith('camera-snapshot-')
        ? 'camera'
        : 'upload';
}

/**
 * Merge the run's asset URLs into the normalized evidence payload (the
 * backend strips bytes and does not embed URLs in `evidence`), so the shared
 * evidence panel can render captured/aligned/overlay tabs from run rows.
 */
export function evidenceWithCaptureUrls(
    capture: RunCapture | null,
): Record<string, unknown> | null {
    if (!capture) return null;
    const evidence =
        (capture.summary?.evidence as
            Record<string, unknown> | null | undefined) ?? null;
    if (!evidence || typeof evidence !== 'object') return null;
    const assets =
        (evidence.assets as Record<string, unknown> | null | undefined) ?? {};
    const withUrl = (asset: unknown, url: string | null): unknown =>
        asset && typeof asset === 'object' && url
            ? { ...(asset as Record<string, unknown>), url }
            : (asset ?? (url ? { url } : null));
    return {
        ...(evidence as Record<string, unknown>),
        assets: {
            captured: withUrl(assets.captured, capture.urls.input),
            aligned: withUrl(assets.aligned, capture.urls.aligned),
            overlay: withUrl(assets.overlay, capture.urls.overlay),
        },
    };
}

export interface ExpectedMarksGroup {
    page_no: number;
    total: number;
    expected: number;
    exact: number;
    ambiguous: number;
    missing: number;
}

/** Grouped expected marks per captured page (35.40) from normalized evidence. */
export function groupExpectedMarks(
    captures: RunCapture[] | null | undefined,
): ExpectedMarksGroup[] {
    if (!Array.isArray(captures)) return [];
    const byPage = new Map<number, RunCapture>();
    for (const capture of captures) {
        byPage.set(capture.page_no, capture); // latest capture wins per page
    }
    const groups: ExpectedMarksGroup[] = [];
    for (const page of [...byPage.keys()].sort((a, b) => a - b)) {
        const capture = byPage.get(page);
        const evidence = capture?.summary?.evidence as
            Record<string, unknown> | null | undefined;
        const items = Array.isArray(evidence?.items)
            ? (evidence.items as Record<string, unknown>[]).map(normalizeItem)
            : [];
        const summary = computeSummary(items);
        groups.push({
            page_no: page,
            total: summary.total,
            expected: summary.expected,
            exact: summary.exact,
            ambiguous: summary.ambiguous,
            missing: summary.missing,
        });
    }
    return groups;
}

export const CHECKPOINT_KEYS = [
    'digital',
    'physical_challenge',
    'qualification',
] as const;
export type CheckpointKey = (typeof CHECKPOINT_KEYS)[number];

export type CheckpointStatus = 'done' | 'active' | 'blocked' | 'pending';

export interface CheckpointDefinition {
    key: CheckpointKey;
    step: number;
    label: string;
    purpose: string;
}

export const CHECKPOINT_DEFINITIONS: CheckpointDefinition[] = [
    {
        key: 'digital',
        step: 1,
        label: 'Digital engine check',
        purpose:
            'Runs the machine-filled proof through the real recognition engine and the no-go variant matrix to prove the mapped geometry recovers the expected answer vector exactly.',
    },
    {
        key: 'physical_challenge',
        step: 2,
        label: 'Physical tests',
        purpose:
            'Tests each physical page using the required real paper, then captures, inspects, reviews, and accepts each sheet.',
    },
    {
        key: 'qualification',
        step: 3,
        label: 'Qualification',
        purpose:
            'Derives physical qualification from a passed full compiled challenge plus staff printer/device/paper measurements — never from a manual checkbox.',
    },
];

export interface CheckpointState extends CheckpointDefinition {
    status: CheckpointStatus;
    statusText: string;
    prerequisites: string[];
    nextAction: string | null;
    actionAvailable: boolean;
    retryAvailable: boolean;
    evidenceSummary: string | null;
    /** 35.41 — candidate/quick runs are labelled "Rehearsal only". */
    rehearsalOnly: boolean;
    activationAvailable: boolean;
    activationReason: string | null;
}

export interface CheckpointDerivationInput {
    run: RunStatusPayload | null;
    draft: {
        stage?: string | null;
        compiled_version_id?: number | null;
        compiled_version_status?: string | null;
        physical_qualified?: boolean;
        print_pdf_url?: string | null;
        digital_verified?: boolean | null;
        mapping_current?: boolean;
        digital_verification_current?: boolean;
        registration_mode?: 'fiducial_anchored' | 'document_bound' | null;
        marker_profile_id?: number | null;
    };
    urls?: { digital_verification?: string | null; compile?: string | null };
    /** Latest PASSED full-qualification run id for the compiled version (server-projected). */
    physicalRunId?: number | null;
    markerProfileStatus?: string | null;
}
export function registrationProfileReady(
    draft: CheckpointDerivationInput['draft'],
    markerProfileStatus: string | null | undefined,
): boolean {
    return draft.registration_mode === 'document_bound'
        ? draft.marker_profile_id == null
        : markerProfileStatus === 'qualified';
}

export function isCurrentTargetRun(
    run: RunStatusPayload | null | undefined,
): boolean {
    return Boolean(
        run && run.target_current !== false && run.target_stale !== true,
    );
}

export function digitalPassed(input: CheckpointDerivationInput): boolean {
    const { run, draft } = input;
    if (run?.mode === 'digital') {
        return (
            run.status === 'passed' ||
            run.digital_verification_current === true
        );
    }
    return (
        draft?.digital_verification_current === true ||
        Boolean(draft?.compiled_version_id) ||
        draft?.stage === 'compiled' ||
        draft?.stage === 'physical_qualified'
    );
}

export function mappingComplete(input: CheckpointDerivationInput): boolean {
    const { draft } = input;
    if (
        draft?.stage === 'mapping_complete' ||
        draft?.stage === 'digital_verified' ||
        draft?.stage === 'compiled' ||
        draft?.stage === 'physical_qualified' ||
        Boolean(draft?.digital_verified) ||
        Boolean(draft?.digital_verification_current)
    ) {
        return true;
    }
    return draft?.mapping_current === true;
}

export function physicalRunActive(run: RunStatusPayload | null): boolean {
    if (!isCurrentTargetRun(run) || !run || run.mode === 'digital')
        return false;
    return (
        run.status === RUN_STATUS_AWAITING_PRINT ||
        run.status === RUN_STATUS_AWAITING_CAPTURE ||
        run.status === RUN_STATUS_ANALYZING_CAPTURE ||
        run.status === RUN_STATUS_NEEDS_REVIEW
    );
}

const REQUIRED_PHYSICAL_METHODS = ['pre_shaded', 'hand_shaded'];
const PRINTED_METHOD_STATES = new Set([
    'awaiting_capture',
    'analyzing',
    'needs_review',
    'passed',
    'failed',
]);
const PRINTED_CHALLENGE_STATES = new Set([
    'printed',
    'awaiting_capture',
    'analyzing_capture',
    'needs_review',
    'passed',
    'failed',
]);

/**
 * A physical checkpoint is current only when both server-owned method rows
 * carry the current target and have crossed challenge preparation/printing.
 * Run-level awaiting_capture alone is insufficient because old summaries can
 * outlive their method rows.
 */
export function currentPhysicalTestPackReady(
    run: RunStatusPayload | null,
): boolean {
    if (
        !isCurrentTargetRun(run) ||
        run?.mode === 'digital' ||
        !Array.isArray(run?.methods) ||
        !run?.target
    )
        return false;
    const targetKind = run.target.kind;
    const targetId = Number(run.target.id);
    const targetHash = String(run.target.hashes?.artifact ?? '');
    if (
        !targetKind ||
        !Number.isInteger(targetId) ||
        targetId < 1 ||
        targetHash === ''
    )
        return false;

    const byMethod = new Map<string, RunMethodPayload>();
    for (const method of run.methods) {
        if (
            !method ||
            !REQUIRED_PHYSICAL_METHODS.includes(method.method) ||
            byMethod.has(method.method)
        )
            return false;
        if (
            method.target_kind !== targetKind ||
            Number(method.target_id) !== targetId ||
            String(method.target_hash ?? '') !== targetHash
        )
            return false;
        if (
            !method.challenge_id ||
            !PRINTED_METHOD_STATES.has(String(method.state ?? ''))
        )
            return false;
        const challengeState = String(method.challenge?.status ?? '');
        if (
            challengeState !== '' &&
            !PRINTED_CHALLENGE_STATES.has(challengeState)
        )
            return false;
        byMethod.set(method.method, method);
    }

    return REQUIRED_PHYSICAL_METHODS.every((method) => byMethod.has(method));
}

/** At least one analyzed/captured page must belong to a current method row. */
export function currentPhysicalCaptureEvidence(
    run: RunStatusPayload | null,
): boolean {
    if (!currentPhysicalTestPackReady(run)) return false;

    return (run?.methods ?? []).some((method) =>
        (method.attempts ?? []).some(
            (attempt) => Number(attempt.capture_id) > 0,
        ),
    );
}


/** Every required current method page must have an explicit selected attempt. */
export function currentPhysicalSelectionComplete(
    run: RunStatusPayload | null,
): boolean {
    if (!currentPhysicalTestPackReady(run)) return false;

    return (run?.methods ?? []).every((method) => {
        const pages = (method.pages ?? []).filter(
            (page) => page.required !== false,
        );
        return (
            pages.length > 0 &&
            pages.every(
                (page) =>
                    Number(
                        page.selected_attempt_id ??
                            page.selected_attempt?.capture_id ??
                            0,
                    ) > 0,
            )
        );
    });
}

/** Qualification may use only current method rows that actually passed. */
export function currentPhysicalMethodsPassed(
    run: RunStatusPayload | null,
): boolean {
    return (
        currentPhysicalSelectionComplete(run) &&
        (run?.methods ?? []).every((method) => method.state === 'passed')
    );
}

export function physicalRunPassed(run: RunStatusPayload | null): boolean {
    return (
        run !== null &&
        isCurrentTargetRun(run) &&
        run.mode !== 'digital' &&
        run.status === RUN_STATUS_PASSED
    );
}

export type PhysicalChallengeResumeStage = 'print' | 'capture';

/**
 * A failed physical run resumes from its durable challenge, not from a new
 * challenge mode selection. A created pack still needs printing; every later
 * challenge state has already crossed the print boundary and resumes capture.
 */
export function physicalChallengeResumeStage(
    challenge: RunChallenge | null | undefined,
): PhysicalChallengeResumeStage {
    return challenge?.status === 'created' ? 'print' : 'capture';
}

export function hasResumablePhysicalChallenge(
    run: RunStatusPayload | null,
): boolean {
    return (
        run !== null &&
        isCurrentTargetRun(run) &&
        run.mode !== 'digital' &&
        run.status === RUN_STATUS_FAILED &&
        Boolean(run.challenge) &&
        run.challenge?.status !== 'superseded' &&
        run.safe_issue?.code !== 'target_changed_superseded'
    );
}

export function isRehearsalRun(run: RunStatusPayload | null): boolean {
    return Boolean(
        run &&
        (run.mode === 'quick_rehearsal' || run.target?.kind === 'candidate'),
    );
}

function safeIssueText(run: RunStatusPayload | null): string {
    if (!run?.safe_issue) return '';
    return run.safe_issue.detail || run.safe_issue.code || '';
}

function evidenceSummaryFor(run: RunStatusPayload | null): string | null {
    if (!run || !Array.isArray(run.evidence) || run.evidence.length === 0)
        return null;
    const groups = groupExpectedMarks(run.evidence);
    if (groups.length === 0) return null;
    const totals = groups.reduce(
        (acc, g) => ({
            total: acc.total + g.total,
            exact: acc.exact + g.exact,
            ambiguous: acc.ambiguous + g.ambiguous,
            missing: acc.missing + g.missing,
        }),
        { total: 0, exact: 0, ambiguous: 0, missing: 0 },
    );
    const pages =
        groups.length === 1
            ? `page ${groups[0].page_no}`
            : `${groups.length} pages`;
    return `${totals.total} expected marks on ${pages} — ${totals.exact} exact, ${totals.ambiguous} ambiguous, ${totals.missing} missing`;
}

/**
 * Derive the three visible checkpoints from the durable run/evidence rows
 * plus the draft projection. Status semantics:
 *  - done:    the checkpoint has persisted proof/evidence.
 *  - active:  this is where the staff must act now (in progress or actionable).
 *  - blocked: a prerequisite checkpoint has not been completed.
 *  - pending: prerequisites met, not yet started or waiting for a worker claim.
 */
export function deriveCheckpointStates(
    input: CheckpointDerivationInput,
): CheckpointState[] {
    // A status response for a historical target remains queryable, but it is
    // never allowed to drive the current checkpoint projection.
    const run = isCurrentTargetRun(input.run) ? input.run : null;
    const draft = input.draft ?? {};
    const urls = input.urls ?? {};
    const physicalRunId = input.physicalRunId ?? null;
    const digital = digitalPassed(input);
    const physicalPackReady = currentPhysicalTestPackReady(run);
    const physicalQualificationReady =
        physicalRunPassed(run) && currentPhysicalMethodsPassed(run);
    const rehearsal = isRehearsalRun(run) && !physicalRunId;
    const challenge = run?.challenge ?? null;
    const compiled = Boolean(draft.compiled_version_id);
    const profileQualified = registrationProfileReady(
        draft,
        input.markerProfileStatus,
    );

    const states: CheckpointState[] = [];

    // 1 — Digital engine check
    {
        const base = CHECKPOINT_DEFINITIONS[0];
        let state: CheckpointState = {
            ...base,
            status: 'pending',
            statusText: 'Not started',
            prerequisites: ['Recognition Mapping saved and validated'],
            nextAction: null,
            actionAvailable: false,
            retryAvailable: false,
            evidenceSummary: null,
            rehearsalOnly: false,
            activationAvailable: false,
            activationReason: null,
        };
        if (
            run?.status === RUN_STATUS_FAILED &&
            run.mode === 'digital'
        ) {
            state.status = 'active';
            state.statusText = `Failed — ${safeIssueText(run) || run.status}`;
            state.nextAction = 'Retry digital verification';
            state.actionAvailable = true;
            state.retryAvailable = true;
        } else if (!mappingComplete(input)) {
            state.status = 'blocked';
            state.statusText = 'Blocked';
            state.prerequisites = [
                'Complete Recognition Mapping (stage 3) before the engine check can run',
            ];
        } else if (digital) {
            state.status = 'done';
            state.statusText = `Passed — attempt ${run?.attempt ?? 1}`;
            state.nextAction = null;
            state.evidenceSummary = run?.result_summary?.matrix_hash
                ? `Matrix ${String(run.result_summary.matrix_hash).slice(0, 10)}…`
                : 'Proof and no-go matrix exact-recovery verified';
        } else if (
            run &&
            run.mode === 'digital' &&
            run.status === RUN_STATUS_QUEUED
        ) {
            state.status = 'pending';
            state.statusText = 'Queued — waiting for the verification worker';
            state.nextAction =
                'Waiting for the verification worker to claim the run';
            state.actionAvailable = false;
            state.evidenceSummary = null;
        } else if (
            run &&
            isActiveRunStatus(run.status) &&
            run.mode === 'digital'
        ) {
            state.status = 'active';
            state.statusText = phaseText(run);
            state.nextAction = null;
            state.evidenceSummary =
                progressText(run.progress) !== '—'
                    ? `${progressText(run.progress)} variants completed`
                    : null;
        } else {
            state.status = 'pending';
            state.statusText = 'Ready';
            state.nextAction = 'Run digital verification';
            state.actionAvailable = Boolean(urls.digital_verification);
        }
        states.push(state);
    }

    // 2 — Physical tests: create, print, capture, and analyze in one checkpoint
    {
        const base = CHECKPOINT_DEFINITIONS[1];
        let state: CheckpointState = {
            ...base,
            status: 'blocked',
            statusText: 'Blocked',
            prerequisites: ['Digital engine check passed'],
            nextAction: null,
            actionAvailable: false,
            retryAvailable: false,
            evidenceSummary: null,
            rehearsalOnly: rehearsal || !compiled || !profileQualified,
            activationAvailable: false,
            activationReason: null,
        };
        const recovery = challengeRecovery(run);
        const capturedPages =
            run && Array.isArray(run.evidence)
                ? new Set(run.evidence.map((capture) => capture.page_no)).size
                : 0;
        if (recovery?.kind === 'repair_reference') {
            state.status = 'active';
            state.statusText = `Challenge ${challenge?.challenge_code ?? '—'} reference outdated — update sheet required`;
            state.nextAction = 'Update challenge sheet';
            state.actionAvailable = Boolean(recovery.action_url);
            state.evidenceSummary = 'Challenge sheet update available';
        } else if (recovery?.kind === 'restart_rehearsal') {
            state.status = 'active';
            state.statusText = `Layout changed for challenge ${challenge?.challenge_code ?? '—'} — start new rehearsal`;
            state.nextAction = 'Start new rehearsal';
            state.actionAvailable = Boolean(recovery.action_url);
            state.evidenceSummary = 'Layout changed';
        } else if (
            physicalQualificationReady ||
            Boolean(draft.physical_qualified)
        ) {
            state.status = 'done';
            state.statusText = challenge
                ? `Physical tests ${challenge.challenge_code} completed`
                : 'Physical tests completed';
            state.evidenceSummary = evidenceSummaryFor(run);
        } else if (run?.status === RUN_STATUS_NEEDS_REVIEW) {
            state.status = 'active';
            state.statusText =
                'Capture needs review — inspect and accept the remaining physical test page';
            state.nextAction =
                'Review the flagged page in its physical test card and accept or retake';
            state.actionAvailable = true;
            state.evidenceSummary = evidenceSummaryFor(run);
        } else if (hasResumablePhysicalChallenge(run)) {
            const resumeStage = physicalChallengeResumeStage(challenge);
            state.status = 'active';
            const interruption = safeIssueText(run);
            state.statusText =
                resumeStage === 'print'
                    ? `Physical tests ${challenge?.challenge_code ?? '—'} interrupted${interruption ? ` — ${interruption}` : ''}`
                    : `Physical capture ${challenge?.challenge_code ?? '—'} interrupted${interruption ? ` — ${interruption}` : ' — resume upload'}`;
            state.nextAction =
                resumeStage === 'print'
                    ? 'Resume the existing sheets at the print step'
                    : 'Resume capture of the printed physical test';
            state.actionAvailable = true;
            state.retryAvailable = true;
            state.evidenceSummary =
                evidenceSummaryFor(run) ??
                (challenge?.print_url || challenge?.guide_url
                    ? 'Existing physical test assets preserved'
                    : null);
        } else if (
            run &&
            run.mode !== 'digital' &&
            run.status === RUN_STATUS_AWAITING_PRINT
        ) {
            state.status = 'active';
            state.statusText = `Physical tests ${challenge?.challenge_code ?? '—'} created — print the first ready method`;
            state.nextAction =
                'Complete pre-shaded first, then hand-shaded: print and capture each method in its card';
            state.actionAvailable = true;
            state.evidenceSummary = challenge?.print_url
                ? 'Printable physical test sheet ready'
                : null;
        } else if (
            run &&
            run.mode !== 'digital' &&
            run.status === RUN_STATUS_AWAITING_CAPTURE
        ) {
            state.status = 'active';
            state.statusText = `${capturedPages} page${capturedPages === 1 ? '' : 's'} captured so far`;
            state.nextAction = physicalPackReady
                ? `Capture page ${nextPageToCapture(run)} of the next unfinished method`
                : 'Complete pre-shaded first, then hand-shaded';
            state.actionAvailable = true;
            state.evidenceSummary = evidenceSummaryFor(run);
        } else if (
            run &&
            run.mode !== 'digital' &&
            run.status === RUN_STATUS_ANALYZING_CAPTURE
        ) {
            state.status = 'active';
            state.statusText = 'Analyzing the submitted physical test…';
            state.evidenceSummary = evidenceSummaryFor(run);
        } else if (!digital) {
            state.status = 'blocked';
            state.statusText = 'Blocked';
            state.prerequisites = ['Digital engine check passed first'];
        } else if (
            run &&
            run.mode === 'digital' &&
            !isTerminalRunStatus(run.status)
        ) {
            state.status = 'blocked';
            state.statusText = 'Blocked';
            state.prerequisites = [
                'Wait for the active verification run to settle before creating physical tests',
            ];
        } else {
            state.status = 'pending';
            state.statusText = 'Ready';
            state.nextAction = 'Create the physical test sheets';
            state.actionAvailable = true;
            state.rehearsalOnly = rehearsal || !compiled || !profileQualified;
        }
        states.push(state);
    }


    // 3 — Qualification
    {
        const base = CHECKPOINT_DEFINITIONS[2];
        const reasons: string[] = [];
        let state: CheckpointState = {
            ...base,
            status: 'blocked',
            statusText: 'Blocked',
            prerequisites: reasons,
            nextAction: null,
            actionAvailable: false,
            retryAvailable: false,
            evidenceSummary: null,
            rehearsalOnly: rehearsal,
            activationAvailable: false,
            activationReason: null,
        };
        if (draft.physical_qualified) {
            state.status = 'done';
            state.statusText =
                'Physical qualification derived from genuine full-challenge evidence';
            state.rehearsalOnly = false;
            state.activationAvailable = Boolean(draft.compiled_version_id);
            state.activationReason = null;
        } else if (physicalRunId && physicalQualificationReady) {
            state.status = 'active';
            state.statusText = `Full challenge run #${physicalRunId} passed — evidence ready`;
            state.nextAction =
                'Record printer/device/paper measurements to derive qualification';
            state.actionAvailable = true;
            state.evidenceSummary = evidenceSummaryFor(run);
            state.prerequisites = ['Passed full compiled challenge'];
            if (draft.registration_mode !== 'document_bound') {
                state.prerequisites.push('Qualified marker profile');
                if (!profileQualified) {
                    state.actionAvailable = false;
                    state.nextAction = null;
                    state.prerequisites.push(
                        'A qualified marker profile is required (profiles are qualified via the calibration artifact)',
                    );
                }
            }
        } else if (
            rehearsal ||
            (run && run.mode !== 'digital' && run.target?.kind === 'candidate')
        ) {
            state.status = 'blocked';
            state.statusText = 'Rehearsal only';
            state.rehearsalOnly = true;
            state.prerequisites = [
                'Candidate/quick rehearsals never qualify — run a full challenge from the compiled immutable version',
            ];
        } else if (
            run?.mode === 'full_qualification' &&
            run.status === RUN_STATUS_FAILED
        ) {
            state.status = 'blocked';
            state.statusText = 'Blocked';
            state.prerequisites = [
                'The full challenge did not pass — capture must recover the exact expected vector with all gates green',
            ];
        } else if (!compiled) {
            state.status = 'blocked';
            state.statusText = 'Blocked';
            state.prerequisites = [
                'Compile the digitally verified candidate, then run a full challenge with genuine capture evidence',
            ];
        } else {
            state.status = 'blocked';
            state.statusText = 'Blocked';
            state.prerequisites = [
                'A passed full compiled challenge with genuine evidence is required — activation stays unavailable until then',
            ];
        }
        if (state.status !== 'done') {
            state.activationAvailable = false;
            state.activationReason =
                'Activation remains unavailable until physical qualification is derived from genuine evidence.';
        }
        states.push(state);
    }

    return states;
}

/**
 * Authority priority:
 * 1. The first checkpoint with status === 'active' (in progress, actionable, or needing retry).
 * 2. The first checkpoint with status === 'pending' (ready to start).
 * 3. The first remaining blocked checkpoint once every earlier step is done.
 * 4. If all checkpoints are done, the final qualification checkpoint stays current.
 * 5. Fallback to the first checkpoint for an empty or malformed state set.
 */
export function deriveCurrentCheckpoint(
    states: CheckpointState[],
): CheckpointState {
    if (!Array.isArray(states) || states.length === 0) {
        return {
            ...CHECKPOINT_DEFINITIONS[0],
            status: 'pending',
            statusText: 'Ready',
            prerequisites: [],
            nextAction: null,
            actionAvailable: false,
            retryAvailable: false,
            evidenceSummary: null,
            rehearsalOnly: false,
            activationAvailable: false,
            activationReason: null,
        };
    }
    const active = states.find((state) => state.status === 'active');
    if (active) return active;
    const pending = states.find((state) => state.status === 'pending');
    if (pending) return pending;
    const incomplete = states.find((state) => state.status !== 'done');
    if (incomplete) return incomplete;
    return states[states.length - 1] ?? states[0];
}

/** Next page to capture: one past the highest captured page, capped at 20. */
export function nextPageToCapture(run: RunStatusPayload | null): number {
    if (!run || !Array.isArray(run.evidence) || run.evidence.length === 0)
        return 1;
    const maxPage = Math.max(
        ...run.evidence.map((c) => Number(c.page_no) || 0),
    );
    return Math.min(maxPage + 1, 20);
}

export interface VerificationPollerOptions {
    statusUrl: string | null;
    /** Ignore late/cache responses belonging to a different run. */
    expectedRunId?: number | null;
    expectedMode?: RunMode | null;
    intervalMs?: number;
    maxIntervalMs?: number;
    fetchImpl?: (
        url: string,
        init?: RequestInit,
    ) => Promise<{ ok: boolean; json(): Promise<unknown> }>;
    onStatus: (status: RunStatusPayload | null) => void;
    onError?: (message: string) => void;
}

export interface VerificationPoller {
    start(): void;
    stop(): void;
    refresh(): Promise<void>;
    isRunning(): boolean;
}

/** Bounded polling cadence: clamped to [500, maxIntervalMs] — never unbounded. */
export function boundedInterval(
    intervalMs: number | null | undefined,
    maxIntervalMs: number | null | undefined,
): number {
    const max = maxIntervalMs && maxIntervalMs > 0 ? maxIntervalMs : 8000;
    const preferred = intervalMs && intervalMs > 0 ? intervalMs : 1500;
    return Math.max(500, Math.min(preferred, max));
}

/**
 * Real polling for an active verification run (35.39): fixed bounded cadence,
 * immediate first fetch, terminal (passed/failed) stop, and explicit teardown
 * via stop() for onDestroy. The server response is the only progress source.
 */
export function createVerificationPoller(
    options: VerificationPollerOptions,
): VerificationPoller {
    const intervalMs = boundedInterval(
        options.intervalMs,
        options.maxIntervalMs,
    );
    const fetchImpl = options.fetchImpl ?? ((url, init) => fetch(url, init));
    let timer: ReturnType<typeof setInterval> | null = null;
    let stopped = true;
    let inFlight = false;
    let requestGeneration = 0;

    async function tick(): Promise<void> {
        if (stopped || inFlight || !options.statusUrl) return;
        inFlight = true;
        const generation = requestGeneration;
        try {
            const response = await fetchImpl(options.statusUrl, {
                headers: { Accept: 'application/json' },
                credentials: 'same-origin',
            });
            if (!response || typeof response.json !== 'function') return;
            const payload = (await response
                .json()
                .catch(() => null)) as RunStatusPayload | null;
            if (stopped || generation !== requestGeneration) return;
            if (
                !response.ok ||
                !payload ||
                typeof payload.status !== 'string'
            ) {
                throw new Error('Verification status is unavailable.');
            }
            if (
                options.expectedRunId != null &&
                Number(payload.run_id) !== Number(options.expectedRunId)
            )
                return;
            if (
                options.expectedMode != null &&
                payload.mode !== options.expectedMode
            )
                return;
            options.onStatus(payload);
            if (isTerminalRunStatus(payload.status)) {
                stop();
            }
        } catch (error) {
            if (!stopped && generation === requestGeneration) {
                options.onError?.(
                    error instanceof Error
                        ? error.message
                        : 'Verification status is unavailable.',
                );
            }
        } finally {
            inFlight = false;
        }
    }

    function start(): void {
        if (!stopped || !options.statusUrl) return;
        stopped = false;
        void tick();
        timer = setInterval(() => void tick(), intervalMs);
    }

    function stop(): void {
        stopped = true;
        requestGeneration += 1;
        if (timer !== null) {
            clearInterval(timer);
            timer = null;
        }
    }

    return {
        start,
        stop,
        refresh: async () => {
            if (stopped) return;
            await tick();
        },
        isRunning: () => !stopped,
    };
}
