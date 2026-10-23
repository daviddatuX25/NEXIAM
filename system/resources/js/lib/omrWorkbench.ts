export const OMR_EXECUTION_KINDS = ['production', 'diagnostic'] as const;
export type OmrExecutionKind = (typeof OMR_EXECUTION_KINDS)[number];

export const OMR_WORKBENCH_PHASES = [
    'capture',
    'preflight',
    'context',
    'processing',
    'evidence',
    'review',
    'recovery',
    'completion',
    'complete',
    'terminal',
] as const;
export type OmrWorkbenchPhase = (typeof OMR_WORKBENCH_PHASES)[number];

export const OMR_WORKBENCH_COMMANDS = [
    'capture_start',
    'capture_retry',
    'review_save',
    'resolve_applicant',
    'resolve_display_code',
    'resolve_duplicate',
    'quarantine_capture',
    'select_session',
    'production_complete',
    'diagnostic_compare',
    'diagnostic_complete',
    'diagnostic_cancel',
] as const;
export type OmrWorkbenchCommand = (typeof OMR_WORKBENCH_COMMANDS)[number];

export const OMR_WORKBENCH_CAPABILITIES = [
    'capture',
    'review',
    'resolve_applicant',
    'resolve_display_code',
    'resolve_duplicate',
    'quarantine',
    'select_session',
    'production_complete',
    'diagnostic_compare',
    'diagnostic_complete',
] as const;
export type OmrWorkbenchCapability =
    (typeof OMR_WORKBENCH_CAPABILITIES)[number];

export type OmrWorkbenchPrimitive = string | number | boolean | null;
export type OmrWorkbenchValue =
    | OmrWorkbenchPrimitive
    | OmrWorkbenchValue[]
    | { [key: string]: OmrWorkbenchValue | undefined };

export interface OmrWorkbenchRecord {
    [key: string]: OmrWorkbenchValue | undefined;
}

export interface OmrWorkbenchProgress extends OmrWorkbenchRecord {
    completed: number;
    total: number;
    known: boolean;
}

export interface OmrWorkbenchCounts extends OmrWorkbenchRecord {
    [key: string]: number | undefined;
}

export interface OmrWorkbenchContext extends OmrWorkbenchRecord {
    execution_kind?: OmrExecutionKind;
    actor_id?: number;
    session_id?: number;
    run_id?: number;
    institution_id?: number;
    academic_cycle_id?: number;
    template_version_id?: number;
    booklet_version_id?: number;
    status?: string;
    raw_phase?: string;
    raw_status?: string;
    terminal?: boolean;
    stale_after_ms?: number;
    observed_at?: string | null;
    progress?: OmrWorkbenchProgress;
    counts?: OmrWorkbenchCounts;
    capabilities?: OmrWorkbenchCapability[];
    capture_target?: OmrWorkbenchCaptureTarget | null;
}

export interface OmrWorkbenchCaptureTarget extends OmrWorkbenchRecord {
    instance_id: string | null;
    sheet_id: number | null;
    client_capture_id: string;
    page_no: 1 | 2 | 3 | 4;
    target_revision: number;
    pending_capture_id: string | null;
    pending_capture_revision: number | null;
    page_count?: 1 | 2 | 3 | 4;
    replaces_capture_id?: number | null;
}

export interface OmrWorkbenchPage extends OmrWorkbenchRecord {
    page_no: number;
    status?: string;
    captured?: boolean;
}

export interface OmrWorkbenchAsset extends OmrWorkbenchRecord {
    id?: number | null;
    hash?: string | null;
    url?: string | null;
}

export interface OmrWorkbenchAssets extends OmrWorkbenchRecord {
    input?: OmrWorkbenchAsset | null;
    aligned?: OmrWorkbenchAsset | null;
    overlay?: OmrWorkbenchAsset | null;
}

export interface OmrWorkbenchFailure extends OmrWorkbenchRecord {
    code?: string | null;
    message?: string | null;
    detail?: string | null;
    stage?: string | null;
    recovery?: string | null;
    support_code?: string | null;
}

export type OmrWorkbenchRecoveryAction =
    | OmrWorkbenchCommand
    | 'retry_scan'
    | 'select_session'
    | 'return_to_grading'
    | 'match_applicant'
    | 'compare_duplicates'
    | 'open_monitoring'
    | 'open_exam_setup'
    | 'review_material_assignment'
    | 'enter_display_code'
    | 'quarantine_scan'
    | 'open_sheet_builder'
    | 'open_support';

const OMR_WORKBENCH_RECOVERY_ACTIONS = [
    ...OMR_WORKBENCH_COMMANDS,
    'retry_scan',
    'select_session',
    'return_to_grading',
    'match_applicant',
    'compare_duplicates',
    'open_monitoring',
    'open_exam_setup',
    'review_material_assignment',
    'enter_display_code',
    'quarantine_scan',
    'open_sheet_builder',
    'open_support',
] as const;

export type OmrWorkbenchRecoveryScope = 'page' | 'sheet' | 'batch';

export interface OmrWorkbenchRecoveryItem extends OmrWorkbenchRecord {
    owner?: string;
    title?: string;
    explanation?: string;
    message?: string;
    status?: string;
    stage?: string;
    recovery?: string;
    blocked?: boolean;
    scope?: OmrWorkbenchRecoveryScope;
    support_code?: string;
    code?: string;
    primary_action?: OmrWorkbenchRecoveryAction | null;
    secondary_action?: OmrWorkbenchRecoveryAction | null;
    destination_url?: string | null;
    technical_detail?: OmrWorkbenchValue | null;
}

export interface OmrWorkbenchAttentionItem extends OmrWorkbenchRecord {
    owner?: string;
    count?: number;
    sheet_ids?: number[];
}

export type OmrWorkbenchOutcomeKind =
    | 'success'
    | 'blocked'
    | 'stale'
    | 'invalid'
    | 'denied'
    | 'expired'
    | 'conflict';

export interface OmrWorkbenchOutcome extends OmrWorkbenchRecord {
    kind?: OmrWorkbenchOutcomeKind;
    code?: string;
    blocked?: boolean;
    stale?: boolean;
    invalid?: boolean;
    message?: string;
}

export interface OmrWorkbenchRecovery extends OmrWorkbenchRecord {
    items?: OmrWorkbenchRecoveryItem[];
    needs_attention?: OmrWorkbenchAttentionItem[];
    count?: number;
    outcome?: OmrWorkbenchOutcome | null;
}

export interface OmrWorkbenchEvidenceReference extends OmrWorkbenchRecord {
    sheet_id?: number;
    page_no?: number;
    status?: string;
    captured?: boolean;
}

export interface OmrWorkbenchEvidenceItem extends OmrWorkbenchRecord {
    id?: number;
    capture_id?: number;
    item_key?: string;
    page_no?: number;
    area_code?: string | null;
    question_no?: number | null;
    option_code?: string | null;
    evidence_hash?: string;
    evidence_version?: string;
}

export interface OmrWorkbenchEvidenceCapture extends OmrWorkbenchRecord {
    capture_id?: number;
    evidence?: OmrWorkbenchRecord | null;
    evidence_hash?: string | null;
}

export interface OmrWorkbenchEvidence extends OmrWorkbenchRecord {
    references?: OmrWorkbenchEvidenceReference[];
    items?: OmrWorkbenchEvidenceItem[];
    captures?: OmrWorkbenchEvidenceCapture[];
    comparison?: OmrWorkbenchRecord | null;
    count?: number;
    redacted?: boolean;
}

export interface OmrWorkbenchCompletion extends OmrWorkbenchRecord {
    kind?: 'production_receipt' | 'diagnostic_outcome' | string;
    label?: string;
    status?: string;
    official?: boolean;
    immutable?: boolean;
    run_id?: number;
    session_id?: number;
    capture_count?: number;
    completed_at?: string | null;
    outcome?: 'recorded' | 'blocked' | 'confirmed' | string;
}

export interface OmrWorkbenchSheet extends OmrWorkbenchRecord {
    id: number;
    client_capture_id?: string;
    status?: string;
    version?: number;
    stage?: string;
    resolution?: OmrWorkbenchRecord | null;
    recovery?: OmrWorkbenchRecoveryItem | null;
    pages?: OmrWorkbenchPage[];
    assets?: OmrWorkbenchAssets;
    evidence?: OmrWorkbenchRecord | null;
    evidence_hash?: string | null;
    failure?: OmrWorkbenchFailure | null;
    captured_at?: string | null;
    completed_at?: string | null;
}

export interface OmrWorkbenchProjection {
    workbench_id: string;
    execution_kind: OmrExecutionKind;
    revision: number;
    phase: OmrWorkbenchPhase;
    context: OmrWorkbenchContext;
    sheets: OmrWorkbenchSheet[];
    evidence: OmrWorkbenchEvidence;
    recovery: OmrWorkbenchRecovery;
    completion: OmrWorkbenchCompletion | null;
    commands: OmrWorkbenchCommand[];
}

export interface OmrWorkbenchError {
    kind: Exclude<OmrWorkbenchOutcomeKind, 'success'>;
    code: string;
    message?: string;
    status?: number;
    projection?: OmrWorkbenchProjection;
}

export interface OmrWorkbenchSuccessResult {
    ok: true;
    projection: OmrWorkbenchProjection;
}

export interface OmrWorkbenchFailureResult {
    ok: false;
    error: OmrWorkbenchError;
    projection?: OmrWorkbenchProjection;
}

export type OmrWorkbenchResult =
    OmrWorkbenchSuccessResult | OmrWorkbenchFailureResult;

const MAX_ID_LENGTH = 128;
const MAX_STRING_LENGTH = 16_384;
const MAX_ARRAY_ITEMS = 512;
const MAX_RECORD_KEYS = 256;
const MAX_VALUE_DEPTH = 10;
const WORKBENCH_ID_PATTERN = /^[A-Za-z0-9][A-Za-z0-9._:-]{0,127}$/;
const PRODUCTION_ID_PATTERN = /^production:[1-9][0-9]*:[1-9][0-9]*$/;
const DIAGNOSTIC_ID_PATTERN = /^diagnostic:[A-Za-z0-9][A-Za-z0-9._-]{0,127}$/;

function isRecord(value: unknown): value is Record<string, unknown> {
    if (value === null || typeof value !== 'object' || Array.isArray(value)) {
        return false;
    }

    try {
        const prototype = Object.getPrototypeOf(value);
        return prototype === Object.prototype || prototype === null;
    } catch {
        return false;
    }
}

function isSafeValue(value: unknown, depth = 0): value is OmrWorkbenchValue {
    if (depth > MAX_VALUE_DEPTH) {
        return false;
    }

    if (value === null || typeof value === 'boolean') {
        return true;
    }

    if (typeof value === 'string') {
        return value.length <= MAX_STRING_LENGTH;
    }

    if (typeof value === 'number') {
        return Number.isFinite(value);
    }

    if (Array.isArray(value)) {
        return (
            value.length <= MAX_ARRAY_ITEMS &&
            value.every((entry) => isSafeValue(entry, depth + 1))
        );
    }

    if (!isRecord(value)) {
        return false;
    }

    const keys = Object.keys(value);
    return (
        keys.length <= MAX_RECORD_KEYS &&
        keys.every(
            (key) =>
                key.length > 0 &&
                key.length <= MAX_ID_LENGTH &&
                isSafeValue(value[key], depth + 1),
        )
    );
}

function isPositiveInteger(value: unknown): value is number {
    return Number.isSafeInteger(value) && (value as number) > 0;
}

function isNonNegativeInteger(value: unknown): value is number {
    return Number.isSafeInteger(value) && (value as number) >= 0;
}

function isOneOf<T extends string>(
    values: readonly T[],
    value: unknown,
): value is T {
    return typeof value === 'string' && values.includes(value as T);
}

function isWorkbenchIdentity(
    value: unknown,
    executionKind: OmrExecutionKind,
): value is string {
    if (
        typeof value !== 'string' ||
        value.length === 0 ||
        value.length > MAX_ID_LENGTH ||
        !WORKBENCH_ID_PATTERN.test(value)
    ) {
        return false;
    }

    return executionKind === 'production'
        ? PRODUCTION_ID_PATTERN.test(value)
        : DIAGNOSTIC_ID_PATTERN.test(value);
}

function isProgress(value: unknown): value is OmrWorkbenchProgress {
    if (!isRecord(value)) {
        return false;
    }

    const completed = value.completed;
    const total = value.total;
    return (
        isNonNegativeInteger(completed) &&
        isNonNegativeInteger(total) &&
        completed <= total &&
        typeof value.known === 'boolean' &&
        isSafeValue(value)
    );
}

function isCounts(value: unknown): value is OmrWorkbenchCounts {
    if (!isRecord(value) || !isSafeValue(value)) {
        return false;
    }

    return Object.values(value).every(
        (count) => count === undefined || isNonNegativeInteger(count),
    );
}

function isPage(value: unknown): value is OmrWorkbenchPage {
    if (!isRecord(value) || !isPositiveInteger(value.page_no)) {
        return false;
    }

    if (value.status !== undefined && typeof value.status !== 'string') {
        return false;
    }
    if (value.captured !== undefined && typeof value.captured !== 'boolean') {
        return false;
    }

    return isSafeValue(value);
}

function isAsset(value: unknown): value is OmrWorkbenchAsset {
    if (!isRecord(value)) {
        return false;
    }

    if (
        value.id !== undefined &&
        value.id !== null &&
        !isPositiveInteger(value.id)
    ) {
        return false;
    }
    if (
        value.hash !== undefined &&
        value.hash !== null &&
        typeof value.hash !== 'string'
    ) {
        return false;
    }
    if (
        value.url !== undefined &&
        value.url !== null &&
        typeof value.url !== 'string'
    ) {
        return false;
    }

    return isSafeValue(value);
}

function isAssets(value: unknown): value is OmrWorkbenchAssets {
    if (!isRecord(value) || !isSafeValue(value)) {
        return false;
    }

    return ['input', 'aligned', 'overlay'].every((key) => {
        const asset = value[key];
        return asset === undefined || asset === null || isAsset(asset);
    });
}

function isRecoveryAction(value: unknown): value is OmrWorkbenchRecoveryAction {
    return isOneOf(OMR_WORKBENCH_RECOVERY_ACTIONS, value);
}

function isRecoveryItem(value: unknown): value is OmrWorkbenchRecoveryItem {
    if (!isRecord(value) || !isSafeValue(value)) {
        return false;
    }

    if (value.owner !== undefined && typeof value.owner !== 'string') {
        return false;
    }
    if (value.title !== undefined && typeof value.title !== 'string') {
        return false;
    }
    if (
        value.explanation !== undefined &&
        typeof value.explanation !== 'string'
    ) {
        return false;
    }
    if (value.status !== undefined && typeof value.status !== 'string') {
        return false;
    }
    if (value.blocked !== undefined && typeof value.blocked !== 'boolean') {
        return false;
    }
    if (
        value.scope !== undefined &&
        !isOneOf(['page', 'sheet', 'batch'] as const, value.scope)
    ) {
        return false;
    }
    if (
        value.support_code !== undefined &&
        typeof value.support_code !== 'string'
    ) {
        return false;
    }
    if (value.code !== undefined && typeof value.code !== 'string') {
        return false;
    }
    if (
        value.primary_action !== undefined &&
        value.primary_action !== null &&
        !isRecoveryAction(value.primary_action)
    ) {
        return false;
    }
    if (
        value.secondary_action !== undefined &&
        value.secondary_action !== null &&
        !isRecoveryAction(value.secondary_action)
    ) {
        return false;
    }
    if (
        value.destination_url !== undefined &&
        value.destination_url !== null &&
        typeof value.destination_url !== 'string'
    ) {
        return false;
    }
    if (
        value.technical_detail !== undefined &&
        value.technical_detail !== null &&
        !isSafeValue(value.technical_detail)
    ) {
        return false;
    }

    return true;
}

function isAttentionItem(value: unknown): value is OmrWorkbenchAttentionItem {
    if (!isRecord(value) || !isSafeValue(value)) {
        return false;
    }

    if (value.owner !== undefined && typeof value.owner !== 'string') {
        return false;
    }
    if (value.count !== undefined && !isNonNegativeInteger(value.count)) {
        return false;
    }
    if (
        value.sheet_ids !== undefined &&
        (!Array.isArray(value.sheet_ids) ||
            value.sheet_ids.length > MAX_ARRAY_ITEMS ||
            !value.sheet_ids.every(isPositiveInteger))
    ) {
        return false;
    }

    return true;
}

function isOutcome(value: unknown): value is OmrWorkbenchOutcome {
    if (!isRecord(value) || !isSafeValue(value)) {
        return false;
    }

    if (
        value.kind !== undefined &&
        !isOneOf(
            [
                'success',
                'blocked',
                'stale',
                'invalid',
                'denied',
                'expired',
                'conflict',
            ] as const,
            value.kind,
        )
    ) {
        return false;
    }
    if (value.code !== undefined && typeof value.code !== 'string') {
        return false;
    }
    for (const key of ['blocked', 'stale', 'invalid']) {
        if (value[key] !== undefined && typeof value[key] !== 'boolean') {
            return false;
        }
    }
    if (value.message !== undefined && typeof value.message !== 'string') {
        return false;
    }

    return true;
}

function isRecovery(value: unknown): value is OmrWorkbenchRecovery {
    if (!isRecord(value) || !isSafeValue(value)) {
        return false;
    }

    if (
        value.items !== undefined &&
        (!Array.isArray(value.items) ||
            value.items.length > MAX_ARRAY_ITEMS ||
            !value.items.every(isRecoveryItem))
    ) {
        return false;
    }
    if (
        value.needs_attention !== undefined &&
        (!Array.isArray(value.needs_attention) ||
            value.needs_attention.length > MAX_ARRAY_ITEMS ||
            !value.needs_attention.every(isAttentionItem))
    ) {
        return false;
    }
    if (value.count !== undefined && !isNonNegativeInteger(value.count)) {
        return false;
    }
    if (
        value.outcome !== undefined &&
        value.outcome !== null &&
        !isOutcome(value.outcome)
    ) {
        return false;
    }

    return true;
}

function isEvidenceReference(
    value: unknown,
): value is OmrWorkbenchEvidenceReference {
    if (!isRecord(value) || !isSafeValue(value)) {
        return false;
    }

    if (value.sheet_id !== undefined && !isPositiveInteger(value.sheet_id)) {
        return false;
    }
    if (value.page_no !== undefined && !isPositiveInteger(value.page_no)) {
        return false;
    }
    if (value.status !== undefined && typeof value.status !== 'string') {
        return false;
    }
    if (value.captured !== undefined && typeof value.captured !== 'boolean') {
        return false;
    }

    return true;
}

function isEvidenceItem(value: unknown): value is OmrWorkbenchEvidenceItem {
    if (!isRecord(value) || !isSafeValue(value)) {
        return false;
    }

    if (value.id !== undefined && !isPositiveInteger(value.id)) {
        return false;
    }
    if (
        value.capture_id !== undefined &&
        !isPositiveInteger(value.capture_id)
    ) {
        return false;
    }
    if (value.item_key !== undefined && typeof value.item_key !== 'string') {
        return false;
    }
    if (value.page_no !== undefined && !isPositiveInteger(value.page_no)) {
        return false;
    }
    if (
        value.area_code !== undefined &&
        value.area_code !== null &&
        typeof value.area_code !== 'string'
    ) {
        return false;
    }
    if (
        value.question_no !== undefined &&
        value.question_no !== null &&
        !isPositiveInteger(value.question_no)
    ) {
        return false;
    }
    if (
        value.option_code !== undefined &&
        value.option_code !== null &&
        typeof value.option_code !== 'string'
    ) {
        return false;
    }
    if (
        value.evidence_hash !== undefined &&
        typeof value.evidence_hash !== 'string'
    ) {
        return false;
    }
    if (
        value.evidence_version !== undefined &&
        typeof value.evidence_version !== 'string'
    ) {
        return false;
    }

    return true;
}

function isEvidenceCapture(
    value: unknown,
): value is OmrWorkbenchEvidenceCapture {
    if (!isRecord(value) || !isSafeValue(value)) {
        return false;
    }

    if (
        value.capture_id !== undefined &&
        !isPositiveInteger(value.capture_id)
    ) {
        return false;
    }
    if (
        value.evidence !== undefined &&
        value.evidence !== null &&
        (!isRecord(value.evidence) || !isSafeValue(value.evidence))
    ) {
        return false;
    }
    if (
        value.evidence_hash !== undefined &&
        value.evidence_hash !== null &&
        typeof value.evidence_hash !== 'string'
    ) {
        return false;
    }

    return true;
}

function isEvidence(value: unknown): value is OmrWorkbenchEvidence {
    if (!isRecord(value) || !isSafeValue(value)) {
        return false;
    }

    if (
        value.references !== undefined &&
        (!Array.isArray(value.references) ||
            value.references.length > MAX_ARRAY_ITEMS ||
            !value.references.every(isEvidenceReference))
    ) {
        return false;
    }
    if (
        value.items !== undefined &&
        (!Array.isArray(value.items) ||
            value.items.length > MAX_ARRAY_ITEMS ||
            !value.items.every(isEvidenceItem))
    ) {
        return false;
    }
    if (
        value.captures !== undefined &&
        (!Array.isArray(value.captures) ||
            value.captures.length > MAX_ARRAY_ITEMS ||
            !value.captures.every(isEvidenceCapture))
    ) {
        return false;
    }
    if (
        value.comparison !== undefined &&
        value.comparison !== null &&
        (!isRecord(value.comparison) || !isSafeValue(value.comparison))
    ) {
        return false;
    }
    if (value.count !== undefined && !isNonNegativeInteger(value.count)) {
        return false;
    }
    if (value.redacted !== undefined && typeof value.redacted !== 'boolean') {
        return false;
    }

    return true;
}

function isCompletion(value: unknown): value is OmrWorkbenchCompletion {
    if (!isRecord(value) || !isSafeValue(value)) {
        return false;
    }

    if (value.kind !== undefined && typeof value.kind !== 'string') {
        return false;
    }
    if (value.label !== undefined && typeof value.label !== 'string') {
        return false;
    }
    if (value.status !== undefined && typeof value.status !== 'string') {
        return false;
    }
    if (value.official !== undefined && typeof value.official !== 'boolean') {
        return false;
    }
    if (value.immutable !== undefined && typeof value.immutable !== 'boolean') {
        return false;
    }
    if (value.run_id !== undefined && !isPositiveInteger(value.run_id)) {
        return false;
    }
    if (
        value.session_id !== undefined &&
        !isPositiveInteger(value.session_id)
    ) {
        return false;
    }
    if (
        value.capture_count !== undefined &&
        !isNonNegativeInteger(value.capture_count)
    ) {
        return false;
    }
    if (
        value.completed_at !== undefined &&
        value.completed_at !== null &&
        typeof value.completed_at !== 'string'
    ) {
        return false;
    }
    if (value.outcome !== undefined && typeof value.outcome !== 'string') {
        return false;
    }

    return true;
}

function isFailure(value: unknown): value is OmrWorkbenchFailure {
    if (!isRecord(value) || !isSafeValue(value)) {
        return false;
    }

    if (
        value.code !== undefined &&
        value.code !== null &&
        typeof value.code !== 'string'
    ) {
        return false;
    }
    if (
        value.detail !== undefined &&
        value.detail !== null &&
        typeof value.detail !== 'string'
    ) {
        return false;
    }

    return true;
}

function isSheet(value: unknown): value is OmrWorkbenchSheet {
    if (
        !isRecord(value) ||
        !isPositiveInteger(value.id) ||
        !isSafeValue(value)
    ) {
        return false;
    }

    if (
        value.client_capture_id !== undefined &&
        typeof value.client_capture_id !== 'string'
    ) {
        return false;
    }
    if (value.status !== undefined && typeof value.status !== 'string') {
        return false;
    }
    if (value.version !== undefined && !isNonNegativeInteger(value.version)) {
        return false;
    }
    if (
        value.stage !== undefined &&
        (typeof value.stage !== 'string' ||
            value.stage.length > MAX_STRING_LENGTH)
    ) {
        return false;
    }
    if (
        value.resolution !== undefined &&
        value.resolution !== null &&
        (!isRecord(value.resolution) || !isSafeValue(value.resolution))
    ) {
        return false;
    }
    if (
        value.recovery !== undefined &&
        value.recovery !== null &&
        !isRecoveryItem(value.recovery)
    ) {
        return false;
    }
    if (
        value.pages !== undefined &&
        (!Array.isArray(value.pages) ||
            value.pages.length > MAX_ARRAY_ITEMS ||
            !value.pages.every(isPage))
    ) {
        return false;
    }
    if (value.assets !== undefined && !isAssets(value.assets)) {
        return false;
    }
    if (
        value.evidence !== undefined &&
        value.evidence !== null &&
        (!isRecord(value.evidence) || !isSafeValue(value.evidence))
    ) {
        return false;
    }
    if (
        value.evidence_hash !== undefined &&
        value.evidence_hash !== null &&
        typeof value.evidence_hash !== 'string'
    ) {
        return false;
    }
    if (
        value.failure !== undefined &&
        value.failure !== null &&
        !isFailure(value.failure)
    ) {
        return false;
    }
    if (
        value.captured_at !== undefined &&
        value.captured_at !== null &&
        typeof value.captured_at !== 'string'
    ) {
        return false;
    }
    if (
        value.completed_at !== undefined &&
        value.completed_at !== null &&
        typeof value.completed_at !== 'string'
    ) {
        return false;
    }

    return true;
}

function isCaptureTarget(value: unknown): value is OmrWorkbenchCaptureTarget {
    if (!isRecord(value)) {
        return false;
    }
    if (
        (value.instance_id !== null && typeof value.instance_id !== 'string') ||
        (value.sheet_id !== null &&
            (!Number.isSafeInteger(value.sheet_id) || Number(value.sheet_id) < 1)) ||
        typeof value.client_capture_id !== 'string' ||
        value.client_capture_id.length === 0 ||
        (value.page_no !== 1 && value.page_no !== 2 && value.page_no !== 3 && value.page_no !== 4) ||
        !isNonNegativeInteger(value.target_revision) ||
        (value.pending_capture_id !== null && typeof value.pending_capture_id !== 'string') ||
        (value.pending_capture_revision !== null &&
            !isNonNegativeInteger(value.pending_capture_revision)) ||
        (value.page_count !== undefined &&
            (!isPositiveInteger(value.page_count) || value.page_count > 4)) ||
        (value.replaces_capture_id !== undefined &&
            value.replaces_capture_id !== null &&
            !isPositiveInteger(value.replaces_capture_id))
    ) {
        return false;
    }
    return value.instance_id !== null || value.sheet_id !== null || value.client_capture_id.length > 0;
}

function isContext(
    value: unknown,
    executionKind: OmrExecutionKind,
): value is OmrWorkbenchContext {
    if (!isRecord(value) || !isSafeValue(value)) {
        return false;
    }

    if (
        value.execution_kind !== undefined &&
        value.execution_kind !== executionKind
    ) {
        return false;
    }
    if (
        value.capture_target !== undefined &&
        value.capture_target !== null &&
        !isCaptureTarget(value.capture_target)
    ) {
        return false;
    }
    for (const key of [
        'actor_id',
        'session_id',
        'run_id',
        'institution_id',
        'academic_cycle_id',
        'template_version_id',
        'booklet_version_id',
    ]) {
        if (value[key] !== undefined && !isPositiveInteger(value[key])) {
            return false;
        }
    }
    if (value.status !== undefined && typeof value.status !== 'string') {
        return false;
    }
    if (value.raw_phase !== undefined && typeof value.raw_phase !== 'string') {
        return false;
    }
    if (
        value.raw_status !== undefined &&
        typeof value.raw_status !== 'string'
    ) {
        return false;
    }
    if (value.terminal !== undefined && typeof value.terminal !== 'boolean') {
        return false;
    }
    if (
        value.stale_after_ms !== undefined &&
        !isNonNegativeInteger(value.stale_after_ms)
    ) {
        return false;
    }
    if (
        value.observed_at !== undefined &&
        value.observed_at !== null &&
        typeof value.observed_at !== 'string'
    ) {
        return false;
    }
    if (value.progress !== undefined && !isProgress(value.progress)) {
        return false;
    }
    if (value.counts !== undefined && !isCounts(value.counts)) {
        return false;
    }
    if (
        value.capabilities !== undefined &&
        (!Array.isArray(value.capabilities) ||
            value.capabilities.length > MAX_ARRAY_ITEMS ||
            !value.capabilities.every((capability) =>
                isOneOf(OMR_WORKBENCH_CAPABILITIES, capability),
            ))
    ) {
        return false;
    }

    return true;
}

function isCommandList(value: unknown): value is OmrWorkbenchCommand[] {
    return (
        Array.isArray(value) &&
        value.length <= MAX_ARRAY_ITEMS &&
        value.every((command) => isOneOf(OMR_WORKBENCH_COMMANDS, command))
    );
}

export function isOmrWorkbenchProjection(
    value: unknown,
): value is OmrWorkbenchProjection {
    if (!isRecord(value) || !isSafeValue(value)) {
        return false;
    }

    const executionKind = value.execution_kind;
    if (!isOneOf(OMR_EXECUTION_KINDS, executionKind)) {
        return false;
    }
    if (!isWorkbenchIdentity(value.workbench_id, executionKind)) {
        return false;
    }
    if (!isPositiveInteger(value.revision)) {
        return false;
    }
    if (!isOneOf(OMR_WORKBENCH_PHASES, value.phase)) {
        return false;
    }
    if (!isContext(value.context, executionKind)) {
        return false;
    }
    if (
        !Array.isArray(value.sheets) ||
        value.sheets.length > MAX_ARRAY_ITEMS ||
        !value.sheets.every(isSheet)
    ) {
        return false;
    }
    if (!isEvidence(value.evidence) || !isRecovery(value.recovery)) {
        return false;
    }
    if (value.completion !== null && !isCompletion(value.completion)) {
        return false;
    }

    return isCommandList(value.commands);
}

export function acceptOmrWorkbenchProjection(
    current: OmrWorkbenchProjection | null | undefined,
    next: unknown,
): next is OmrWorkbenchProjection {
    if (!isOmrWorkbenchProjection(next)) {
        return false;
    }

    if (current === null || current === undefined) {
        return true;
    }

    return (
        isOmrWorkbenchProjection(current) &&
        current.workbench_id === next.workbench_id &&
        current.execution_kind === next.execution_kind &&
        next.revision > current.revision
    );
}
