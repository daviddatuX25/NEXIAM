// Shared frontend projection for OMR diagnostic evidence (OpenSpec 35.33).
// One normalized shape for builder Test capture, canonical verification
// evidence, and grading scanner review, so the shared evidence panel renders
// identical markup from every source (builder/scanner equivalence, 35.37).
// Pure module: no DOM, no network, no domain mutation — the projection only
// describes read-only evidence.

import { isRecord, toRegion, type Region } from './omrRegionCrop.js';

type UnknownRecord = Record<string, unknown>;

function positiveNumberOr(value: unknown): number | null {
    const parsed = numberOr(value, null);
    return parsed !== null && parsed > 0 ? parsed : null;
}

function normalizeHash(value: unknown): string | null {
    const hash = stringOr(value, null)?.toLowerCase() ?? null;
    return hash !== null && /^[a-f0-9]{64}$/.test(hash) ? hash : null;
}

export interface NormalizedOption {
    code: string;
    region: Region | null;
    mean: number | null;
    threshold_delta: number | null;
    threshold_status: string | null;
    crop_png_base64: string | null;
}

export interface NormalizedDetected {
    state: string | null;
    selected_option: string | null;
    confidence: number | null;
    observed_options?: string[];
    option_observations?: {
        code: string;
        mean: number | null;
        classification: string | null;
    }[];
    issue_codes?: string[];
    evidence_hash?: string | null;
}

export type OmrEvidenceFilter =
    | 'all'
    | 'mismatch'
    | 'mismatches'
    | 'needs_review'
    | 'reviewed';

export type OmrReviewDecision =
    | 'confirm_machine'
    | 'set_option'
    | 'blank'
    | 'unresolved';

export interface OmrReviewReason {
    code: string;
    label: string;
}

/** Exact write contract accepted by the item review endpoint. */
export interface OmrReviewDecisionPayload {
    decision: OmrReviewDecision;
    selected_option: string | null;
    reason_code: string | null;
    note: string | null;
    expected_parent_version: number | string | null;
    expected_item_revision: number | null;
    reviewed_machine_evidence_hash: string;
    idempotency_key: string;
}

export interface NormalizedReviewer {
    id: string | number | null;
    display_name: string | null;
}

export interface NormalizedReview {
    revision: number | null;
    decision: OmrReviewDecision | string | null;
    selected_option: string | null;
    reason_code: string | null;
    note: string | null;
    reviewer: NormalizedReviewer | null;
    reviewed_at: string | null;
    evidence_hash: string | null;
    stale: boolean;
}

export interface NormalizedEffective {
    state: string | null;
    selected_option: string | null;
    source: string | null;
}

export interface NormalizedProvenance {
    asset: Record<string, unknown> | null;
    page_asset: Record<string, unknown> | null;
    crop_source: Record<string, unknown> | null;
    geometry_manifest_hash: string | null;
    evidence_version: string | null;
    page_no: number | null;
}

export interface NormalizedItem {
    id?: number | string | null;
    item_id?: string | null;
    group_type: string | null;
    position: number | null;
    area_code: string | null;
    question_no: number | null;
    native_label: string | null;
    page_no: number;
    region: Region | null;
    crop_rect: Region | null;
    options: NormalizedOption[];
    detected: NormalizedDetected | null;
    machine: NormalizedDetected | null;
    expected: string | null;
    expected_evidence: { option: string | null };
    review: NormalizedReview | null;
    review_history: NormalizedReview[];
    effective: NormalizedEffective | null;
    provenance: NormalizedProvenance;
    geometry: Record<string, unknown> | null;
    exact: boolean | null;
    confidence: number | null;
    score: number | null;
    issues: string[];
    parent_version: number | string | null;
    item_revision: number | null;
    reviewable: boolean;
    label: string;
}

export interface NormalizedIdentity {
    code: string | null;
    position: number;
    page_no: number;
    region: Region | null;
    confidence: number | null;
    detected_value: string | null;
    expected_digit: string | null;
    exact: boolean | null;
}

export interface EvidenceSummary {
    total: number;
    expected: number;
    exact: number;
    ambiguous: number;
    missing: number;
    vector_match: boolean | null;
}

export interface EvidenceView {
    outcome: string | null;
    code: string | null;
    detail: string | null;
    page_no: number;
    target: Record<string, unknown> | null;
    assets: {
        captured: Record<string, unknown> | null;
        aligned: Record<string, unknown> | null;
        overlay: Record<string, unknown> | null;
    };
    hashes: {
        input: string | null;
        aligned: string | null;
        overlay: string | null;
        plan: string | null;
        evidence: string | null;
    };
    gates: Record<string, unknown> | null;
    marker_matches: Record<string, number> | null;
    field_block_shifts: { block: string; x: number | null; y: number | null }[];
    items: NormalizedItem[];
    identity: NormalizedIdentity[];
    summary: EvidenceSummary;
    timings: {
        preprocess_ms: number | null;
        layout_verification_ms: number | null;
        detection_ms: number | null;
    };
    issues: unknown[];
    calibration_status: string | null;
    rectification?: Record<string, unknown> | null;
    plan_hash: string | null;
    plan_purpose: string | null;
    crop_image_url: string | null;
}
/** Immutable, server-paginated evidence projection (OmrEvidencePage). */
export interface EvidencePageGroup {
    key: string;
    label: string;
    group_type?: string | null;
    area_key?: string | null;
    area_position?: number | null;
    total: number;
    mismatch_total: number;
    page_count: number;
    url?: string | null;
}

export interface EvidenceSheetMap {
    page_no: number;
    width: number | null;
    height: number | null;
    sha256: string | null;
    identity_write_in?: UnknownRecord | null;
    items: Record<string, unknown>[];
    document_fields: Record<string, unknown>[];
}


export interface EvidencePage {
    method: 'pre_shaded' | 'hand_shaded' | string;
    capture_id: number;
    area: string;
    area_position?: number | null;
    total: number;
    mismatch_total: number;
    needs_review_total?: number | null;
    reviewed_total?: number | null;
    page: number;
    per_page: number;
    page_count: number;
    filter: OmrEvidenceFilter;
    evidence_version?: string | null;
    evidence_hash?: string | null;
    attempt_no?: number | null;
    capture_page_no?: number | null;
    parent_version?: number | null;
    reviewable: boolean;
    blind_review: boolean;
    lifecycle?: string | null;
    status?: string | null;
    review_note_max: number;
    review_reasons: OmrReviewReason[];
    groups: EvidencePageGroup[];
    items: Record<string, unknown>[];
    lazy_asset_urls: Record<string, string | null>;
    sheet_map: EvidenceSheetMap;
}

const STATE_LABELS: Record<string, string> = {
    marked: 'Marked',
    blank: 'Blank',
    ambiguous: 'Ambiguous',
    invalid: 'Invalid',
    unresolved: 'Unresolved',
};

const GATE_LABELS: Record<string, string> = {
    marker_count: 'Marker count',
    registration: 'Registration',
    dimensions: 'Dimensions',
    shifts: 'Field-block shifts',
};

export const GATE_ORDER = [
    'marker_count',
    'registration',
    'dimensions',
    'shifts',
];

export type ProblemKind = 'incorrect' | 'ambiguous' | 'missing';

/**
 * Canonical projection of raw evidence from any source:
 * - builder Test capture (`aligned_url` + `item_evidence` rows),
 * - canonical `OmrDiagnosticEvidence::normalize()` (`assets`/`items`/`identity`),
 * - grading scanner review (items with `detected`/`expected`/`exact`).
 * Never throws; malformed fields degrade to nulls so rendering stays honest.
 */
export function normalizeEvidence(
    raw: unknown,
    opts: { cropImageUrl?: string | null } = {},
): EvidenceView | null {
    if (!raw || typeof raw !== 'object') return null;
    const source = raw as UnknownRecord;
    const rawAssets = isRecord(source.assets)
        ? (source.assets as UnknownRecord)
        : {};

    const alignedAsset = normalizeAsset(
        isRecord(rawAssets.aligned)
            ? (rawAssets.aligned as UnknownRecord)
            : null,
    );
    const overlayAsset = normalizeAsset(
        isRecord(rawAssets.overlay)
            ? (rawAssets.overlay as UnknownRecord)
            : null,
    );
    const capturedAsset = normalizeAsset(
        isRecord(rawAssets.captured)
            ? (rawAssets.captured as UnknownRecord)
            : null,
    );

    const alignedImage = isRecord(source.aligned_image)
        ? (source.aligned_image as UnknownRecord)
        : null;
    const overlayImage = isRecord(source.overlay_image)
        ? (source.overlay_image as UnknownRecord)
        : null;

    const cropImageUrl =
        opts.cropImageUrl ??
        (isRecord(alignedAsset)
            ? ((alignedAsset.url as string | null) ?? null)
            : null);
    const alignedUrl =
        cropImageUrl ??
        (isRecord(alignedAsset)
            ? ((alignedAsset.url as string | null) ?? null)
            : null);

    const hashes = isRecord(source.hashes)
        ? (source.hashes as UnknownRecord)
        : {};
    const rawItems = Array.isArray(source.items)
        ? (source.items as UnknownRecord[])
        : Array.isArray(source.item_evidence)
          ? (source.item_evidence as UnknownRecord[])
          : [];
    const items = rawItems.map(normalizeItem);

    return {
        outcome: typeof source.outcome === 'string' ? source.outcome : null,
        code: typeof source.code === 'string' ? source.code : null,
        detail: typeof source.detail === 'string' ? source.detail : null,
        page_no: numberOr(source.page_no, 1) ?? 1,
        target: isRecord(source.target)
            ? (source.target as UnknownRecord)
            : null,
        assets: {
            captured:
                capturedAsset ??
                (typeof source.captured_url === 'string'
                    ? { url: source.captured_url }
                    : null),
            aligned:
                alignedAsset ??
                (alignedUrl
                    ? {
                          url: alignedUrl,
                          mime: alignedImage?.mime ?? null,
                          width: numberOr(alignedImage?.width, null),
                          height: numberOr(alignedImage?.height, null),
                          sha256:
                              typeof alignedImage?.hash === 'string'
                                  ? (alignedImage.hash as string)
                                  : null,
                      }
                    : null),
            overlay:
                overlayAsset ??
                (typeof source.overlay_url === 'string'
                    ? {
                          url: source.overlay_url,
                          mime: overlayImage?.mime ?? null,
                          width: numberOr(overlayImage?.width, null),
                          height: numberOr(overlayImage?.height, null),
                          sha256:
                              typeof overlayImage?.hash === 'string'
                                  ? (overlayImage.hash as string)
                                  : null,
                      }
                    : null),
        },
        hashes: {
            input: stringOr(hashes?.input ?? source.input_hash, null),
            aligned: stringOr(hashes?.aligned ?? alignedImage?.hash, null),
            overlay: stringOr(hashes?.overlay ?? overlayImage?.hash, null),
            plan: stringOr(hashes?.plan ?? source.plan_hash, null),
            evidence: stringOr(source.evidence_hash, null),
        },
        gates: isRecord(source.gates) ? (source.gates as UnknownRecord) : null,
        marker_matches: isRecord(source.marker_matches)
            ? Object.fromEntries(
                  Object.entries(source.marker_matches as UnknownRecord).map(
                      ([key, value]) => [
                          key,
                          typeof value === 'number' ? value : Number(value),
                      ],
                  ),
              )
            : null,
        field_block_shifts: normalizeShifts(source.field_block_shifts),
        items,
        identity: Array.isArray(source.identity)
            ? (source.identity as UnknownRecord[]).map(normalizeIdentity)
            : [],
        summary: computeSummary(items),
        timings: normalizeTimings(source.timings),
        issues: Array.isArray(source.issues)
            ? (source.issues as UnknownRecord[])
            : [],
        calibration_status: stringOr(source.calibration_status, null),
        rectification: isRecord(source.rectification)
            ? (source.rectification as UnknownRecord)
            : null,
        plan_hash: stringOr(source.plan_hash, null),
        plan_purpose: stringOr(source.plan_purpose, null),
        crop_image_url: cropImageUrl,
    };
}

/**
 * Normalize the bounded server page without expanding it into a legacy
 * full-capture payload. The returned item list is exactly the page received
 * from `OmrEvidenceQuery`; callers fetch another page explicitly.
 */
export function normalizeEvidencePage(raw: unknown): EvidencePage | null {
    if (!raw || typeof raw !== 'object') return null;
    const source = raw as UnknownRecord;
    const method = stringOr(source.method, '') ?? '';
    const captureId = numberOr(source.capture_id ?? source.capture, 0) ?? 0;
    if (!method || captureId < 1) return null;
    const rawGroups = Array.isArray(source.groups)
        ? source.groups
        : Array.isArray(source.group_navigation)
          ? source.group_navigation
          : [];
    const groups = rawGroups
        .filter((group): group is UnknownRecord => isRecord(group))
        .map((group) => ({
            key:
                stringOr(group.key ?? group.area_key ?? group.group_type, '') ??
                '',
            label:
                stringOr(
                    group.label ??
                        group.key ??
                        group.area_key ??
                        group.group_type,
                    'Evidence',
                ) ?? 'Evidence',
            group_type: stringOr(group.group_type, null),
            area_key: stringOr(group.area_key, null),
            area_position: numberOr(group.area_position, null),
            total: numberOr(group.total, 0) ?? 0,
            mismatch_total: numberOr(group.mismatch_total, 0) ?? 0,
            page_count: numberOr(group.page_count, 1) ?? 1,
            url: stringOr(group.url, null),
        }))
        .filter((group) => group.key !== '');
    const items = (
        Array.isArray(source.items)
            ? source.items
            : Array.isArray(source.rows)
              ? source.rows
              : []
    ).filter((item): item is UnknownRecord => isRecord(item));
    const rawSheetMap = isRecord(source.sheet_map)
        ? source.sheet_map
        : {};
    const sheetItems = Array.isArray(rawSheetMap.items)
        ? rawSheetMap.items.filter(
              (item): item is UnknownRecord => isRecord(item),
          )
        : [];
    const documentFields = Array.isArray(rawSheetMap.document_fields)
        ? rawSheetMap.document_fields.filter(
              (field): field is UnknownRecord => isRecord(field),
          )
        : [];
    const lazy = isRecord(source.lazy_asset_urls)
        ? source.lazy_asset_urls
        : isRecord(source.asset_urls)
          ? source.asset_urls
          : {};

    const rawFilter = stringOr(source.filter, 'all') ?? 'all';
    const filter: OmrEvidenceFilter = normalizeEvidenceFilter(rawFilter);
    const rawReasons: unknown[] = Array.isArray(
        source.review_reasons ?? source.reasons,
    )
        ? ((source.review_reasons ?? source.reasons) as unknown[])
        : [];
    const reviewReasons = rawReasons
        .filter((reason): reason is UnknownRecord => isRecord(reason))
        .map((reason) => ({
            code: stringOr(reason.code, '') ?? '',
            label:
                stringOr(reason.label ?? reason.name ?? reason.code, '') ?? '',
        }))
        .filter((reason) => reason.code !== '' && reason.label !== '');
    return {
        method,
        capture_id: captureId,
        area: stringOr(source.area, 'all') ?? 'all',
        area_position: numberOr(source.area_position, null),
        total: numberOr(source.total, items.length) ?? items.length,
        mismatch_total: numberOr(source.mismatch_total, 0) ?? 0,
        needs_review_total: numberOr(source.needs_review_total, null),
        reviewed_total: numberOr(source.reviewed_total, null),
        page: Math.max(1, numberOr(source.page, 1) ?? 1),
        per_page: Math.min(
            50,
            Math.max(1, numberOr(source.per_page, 25) ?? 25),
        ),
        page_count: Math.max(1, numberOr(source.page_count, 1) ?? 1),
        filter,
        evidence_version: stringOr(source.evidence_version, null),
        evidence_hash: stringOr(source.evidence_hash, null),
        attempt_no: numberOr(source.attempt_no, null),
        capture_page_no: numberOr(source.capture_page_no, null),
        parent_version: numberOr(
            source.parent_version ?? source.version,
            null,
        ),
        reviewable: source.reviewable === true,
        blind_review:
            source.blind_review === true ||
            source.presentation === 'blind' ||
            source.review_presentation === 'blind',
        lifecycle: stringOr(source.lifecycle ?? source.status, null),
        status: stringOr(source.status, null),
        review_note_max: Math.min(
            2000,
            Math.max(1, numberOr(source.review_note_max, 500) ?? 500),
        ),
        review_reasons: reviewReasons,
        groups,
        items,
        lazy_asset_urls: Object.fromEntries(
            Object.entries(lazy).map(([key, value]) => [
                key,
                typeof value === 'string' ? value : null,
            ]),
        ),
        sheet_map: {
            page_no: Math.max(
                1,
                numberOr(
                    rawSheetMap.page_no ?? source.capture_page_no,
                    1,
                ) ?? 1,
            ),
            width: positiveNumberOr(rawSheetMap.width),
            height: positiveNumberOr(rawSheetMap.height),
            sha256: normalizeHash(rawSheetMap.sha256),
            identity_write_in: isRecord(rawSheetMap.identity_write_in)
                ? (rawSheetMap.identity_write_in as UnknownRecord)
                : null,
            items: sheetItems,
            document_fields: documentFields,
        },
    };
}

export function normalizeItem(item: UnknownRecord): NormalizedItem {
    const machineSource = isRecord(item.machine)
        ? (item.machine as UnknownRecord)
        : isRecord(item.machine_evidence)
          ? (item.machine_evidence as UnknownRecord)
          : null;
    const machine = normalizeDetected(
        machineSource ? { ...item, ...machineSource, detected: machineSource } : item,
    );
    const detected = machine
        ? {
              state: machine.state,
              selected_option: machine.selected_option,
              confidence: machine.confidence,
          }
        : null;
    const expectedSource = isRecord(item.expected)
        ? (item.expected as UnknownRecord)
        : null;
    const expected = stringOr(
        expectedSource?.option ??
            expectedSource?.selected_option ??
            item.expected ??
            item.correct_option_code,
        null,
    );
    const exact = normalizeExact(item, detected, expected);
    const geometry = isRecord(item.geometry)
        ? (item.geometry as UnknownRecord)
        : null;
    const rawOptions = Array.isArray(item.options)
        ? (item.options as UnknownRecord[])
        : geometry && Array.isArray(geometry.options)
          ? (geometry.options as UnknownRecord[])
          : [];
    const means = isRecord(item.means) ? (item.means as UnknownRecord) : null;
    const observations = Array.isArray(machine?.option_observations)
        ? machine.option_observations
        : [];
    const options = rawOptions.map((option) => {
        const code = stringOr(option.code ?? option.option, '') ?? '';
        const observation = observations.find((entry) => entry.code === code);
        return {
            code,
            region: toRegion(option.region ?? option.rect),
            mean: floatOr(
                option.mean ??
                    option.bubble_mean ??
                    means?.[code] ??
                    observation?.mean,
                null,
            ),
            threshold_delta: floatOr(option.threshold_delta, null),
            threshold_status: stringOr(option.threshold_status, null),
            crop_png_base64: null,
        };
    });
    const nativeLabel = stringOr(
        item.native_label ?? item.label ?? item.item_id,
        null,
    );
    const areaCode = stringOr(item.area_code ?? item.area_key, null);
    const questionNo =
        item.question_no == null ? null : Number(item.question_no);
    const label =
        nativeLabel ??
        (areaCode ? `${areaCode} Q${questionNo ?? '?'}` : 'Item');
    const review = normalizeReview(item.review ?? item.current_review);
    const history = Array.isArray(item.review_history)
        ? item.review_history
              .filter((entry): entry is UnknownRecord => isRecord(entry))
              .map(normalizeReview)
              .filter((entry): entry is NormalizedReview => entry !== null)
        : [];
    const effective = normalizeEffective(item.effective);
    const asset = isRecord(item.asset)
        ? (item.asset as UnknownRecord)
        : isRecord(item.page_asset)
          ? (item.page_asset as UnknownRecord)
          : null;
    const pageAsset = isRecord(item.page_asset)
        ? (item.page_asset as UnknownRecord)
        : null;
    const cropSource = isRecord(item.crop_source)
        ? (item.crop_source as UnknownRecord)
        : geometry && isRecord(geometry.crop_source)
          ? (geometry.crop_source as UnknownRecord)
          : null;
    const provenance = {
        asset,
        page_asset: pageAsset,
        crop_source: cropSource,
        geometry_manifest_hash: stringOr(
            item.geometry_manifest_hash ?? geometry?.geometry_manifest_hash,
            null,
        ),
        evidence_version: stringOr(item.evidence_version, null),
        page_no: numberOr(item.page_no ?? item.page, 1),
    };
    const itemRevision = numberOr(
        item.item_revision ?? review?.revision,
        null,
    );
    return {
        id: typeof item.id === 'string' || typeof item.id === 'number' ? item.id : null,
        item_id: stringOr(item.item_id ?? item.label, null),
        group_type: stringOr(item.group_type ?? item.group, null),
        position: item.position == null ? null : Number(item.position),
        area_code: areaCode,
        question_no: Number.isFinite(questionNo as number) ? questionNo : null,
        native_label: nativeLabel,
        page_no: numberOr(item.page_no ?? item.page, 1) ?? 1,
        region: toRegion(
            item.region ?? item.item_rect ?? item.crop_rect ?? geometry?.item_rect,
        ),
        crop_rect: toRegion(item.crop_rect ?? geometry?.crop_rect),
        options,
        detected,
        machine,
        expected,
        expected_evidence: { option: expected },
        review,
        review_history: history,
        effective,
        provenance,
        geometry,
        exact,
        confidence: floatOr(machine?.confidence ?? item.confidence, null),
        score: item.score == null ? null : Number(item.score),
        issues: normalizeIssueCodes(
            item.issue_codes ?? item.issues ?? machine?.issue_codes,
        ),
        parent_version: numberOr(item.parent_version ?? item.version, null),
        item_revision: itemRevision,
        reviewable: item.reviewable === true,
        label,
    };
}

export function normalizeIdentity(entry: UnknownRecord): NormalizedIdentity {
    return {
        code: stringOr(entry.code, null),
        position: numberOr(entry.position, 1) ?? 1,
        page_no: numberOr(entry.page_no, 1) ?? 1,
        region: toRegion(entry.region),
        confidence: floatOr(entry.confidence, null),
        detected_value: stringOr(entry.detected_value, null),
        expected_digit: stringOr(entry.expected_digit, null),
        exact: entry.exact == null ? null : Boolean(entry.exact),
    };
}

/**
 * Backend-parity summary (mirrors `OmrDiagnosticEvidence::summarize`):
 * expected counts items with a declared expected value; exact counts exact
 * matches; ambiguous counts non-exact ambiguous detections; missing is the
 * remainder; vector_match is true only when every expected item is exact.
 */
export function computeSummary(items: NormalizedItem[]): EvidenceSummary {
    let expected = 0;
    let exact = 0;
    let ambiguous = 0;
    for (const item of items) {
        if (item.expected === null) continue;
        expected += 1;
        if (item.exact === true) {
            exact += 1;
            continue;
        }
        if (item.detected?.state === 'ambiguous') ambiguous += 1;
    }
    return {
        total: items.length,
        expected,
        exact,
        ambiguous,
        missing: Math.max(0, expected - exact - ambiguous),
        vector_match: expected > 0 ? exact === expected : null,
    };
}

/** Problems are meaningful only for rows exercised by the expected vector. */
export function itemProblem(item: NormalizedItem): ProblemKind | null {
    if (item.expected === null) return null;
    if (
        item.detected?.state === 'ambiguous' ||
        item.detected?.state === 'unreadable'
    )
        return 'ambiguous';
    if (item.detected == null) return 'missing';
    if (
        item.detected.selected_option == null &&
        item.detected.state !== 'marked' &&
        item.detected.state !== 'match'
    )
        return 'missing';
    if (item.exact === false) return 'incorrect';
    return null;
}

export function isProblemItem(item: NormalizedItem): boolean {
    return itemProblem(item) !== null;
}

export function identityProblem(entry: NormalizedIdentity): boolean {
    return (
        entry.exact === false ||
        (entry.expected_digit !== null && entry.detected_value === null)
    );
}

export function filterItems(
    items: NormalizedItem[],
    filter: 'problems' | 'all',
): NormalizedItem[] {
    return filter === 'problems' ? items.filter(isProblemItem) : items;
}
export interface OmrReviewDraft {
    decision: OmrReviewDecision;
    selected_option: string | null;
    reason_code: string | null;
    note: string | null;
}

export function normalizeEvidenceFilter(value: unknown): OmrEvidenceFilter {
    return value === 'mismatches' || value === 'mismatch'
        ? 'mismatch'
        : value === 'needs_review' || value === 'reviewed'
          ? value
          : 'all';
}

export function reviewPayloadForItem(
    item: NormalizedItem,
    draft: OmrReviewDraft,
    idempotencyKey: string,
    parentVersion: number | string | null = item.parent_version,
): OmrReviewDecisionPayload {
    return {
        decision: draft.decision,
        selected_option: draft.selected_option,
        reason_code: draft.reason_code,
        note: draft.note,
        expected_parent_version: parentVersion,
        expected_item_revision: item.item_revision,
        reviewed_machine_evidence_hash:
            item.machine?.evidence_hash ?? item.detected?.evidence_hash ?? '',
        idempotency_key: idempotencyKey,
    };
}

export function reviewIsStale(item: NormalizedItem): boolean {
    const reviewHash = item.review?.evidence_hash;
    const machineHash = item.machine?.evidence_hash;
    return Boolean(
        item.review?.stale ||
            (reviewHash && machineHash && reviewHash !== machineHash),
    );
}

/** Best-effort field-block shift match for one item (block id ≈ area/label). */
export function shiftForItem(
    item: NormalizedItem,
    shifts: { block: string; x: number | null; y: number | null }[],
): { block: string; x: number | null; y: number | null } | null {
    const needle = item.native_label ?? item.area_code;
    if (!needle) return null;
    for (const shift of shifts) {
        if (
            shift.block === needle ||
            (item.area_code != null && shift.block.includes(item.area_code))
        )
            return shift;
    }
    return null;
}

export function shortHash(value: unknown): string {
    return value ? String(value).slice(0, 10) : '—';
}

export function meanText(value: unknown): string {
    return value == null ? '—' : Number(value).toFixed(2);
}

export function stateLabel(state: string | null): string {
    return state ? (STATE_LABELS[state] ?? state) : 'Unavailable';
}

export function detectedText(item: NormalizedItem): string {
    if (item.detected?.selected_option) return item.detected.selected_option;
    return stateLabel(item.detected?.state ?? null);
}

export function gateLabel(key: string): string {
    return GATE_LABELS[key] ?? key;
}

export function gateTone(gate: unknown): 'pass' | 'fail' {
    return gate === 'pass' ? 'pass' : 'fail';
}

export function gateEntries(
    gates: UnknownRecord | null,
): { key: string; label: string; pass: boolean }[] {
    if (!gates) return [];
    return GATE_ORDER.filter(
        (key) => gates[key] !== undefined && gates[key] !== null,
    ).map((key) => ({
        key,
        label: gateLabel(key),
        pass: gateTone(gates[key]) === 'pass',
    }));
}

export function unsafeBlocks(gates: UnknownRecord | null): unknown[] {
    return Array.isArray(gates?.unsafe_blocks)
        ? (gates?.unsafe_blocks as unknown[])
        : [];
}

export function hashesEntries(
    view: EvidenceView,
): { key: string; label: string; value: string }[] {
    const entries: { key: string; label: string; value: string | null }[] = [
        { key: 'input', label: 'Input', value: view.hashes.input },
        { key: 'aligned', label: 'Aligned', value: view.hashes.aligned },
        { key: 'overlay', label: 'Overlay', value: view.hashes.overlay },
        { key: 'plan', label: 'Plan', value: view.hashes.plan },
        { key: 'evidence', label: 'Evidence', value: view.hashes.evidence },
    ];
    return entries.filter(
        (entry): entry is { key: string; label: string; value: string } =>
            entry.value != null,
    );
}

export function timingsText(timings: EvidenceView['timings']): string {
    const parts: string[] = [];
    if (timings.preprocess_ms != null)
        parts.push(`preprocess ${timings.preprocess_ms.toFixed(0)} ms`);
    if (timings.layout_verification_ms != null)
        parts.push(
            `verification ${timings.layout_verification_ms.toFixed(0)} ms`,
        );
    if (timings.detection_ms != null)
        parts.push(`detection ${timings.detection_ms.toFixed(0)} ms`);
    return parts.length ? `Timing — ${parts.join(' · ')}` : '';
}

/** Row label for the return-to-mapping link: first problem, else first item. */
export function returnLabel(
    view: EvidenceView,
    filter: 'problems' | 'all',
): string | null {
    const visible = filterItems(view.items, filter);
    const firstProblem = visible.find(isProblemItem);
    return firstProblem?.label ?? visible[0]?.label ?? null;
}

export function mappingUrl(
    returnUrl: string | null,
    label: string | null,
): string | null {
    if (!returnUrl || !label) return null;
    const separator = returnUrl.includes('?') ? '&' : '?';
    return `${returnUrl}${separator}row=${encodeURIComponent(label)}`;
}

export function cropKey(view: EvidenceView, item: NormalizedItem): string {
    const imageKey = view.hashes.aligned ?? view.crop_image_url ?? '';
    return `${imageKey}:${item.page_no}:${item.label}:${item.position ?? ''}`;
}

function normalizeDetected(item: UnknownRecord): NormalizedDetected | null {
    const nested = isRecord(item.detected)
        ? (item.detected as UnknownRecord)
        : null;
    const scalar = !nested ? stringOr(item.detected, null) : null;
    const state = stringOr(nested?.state ?? item.state, null);
    const observedSource =
        nested?.observed_options ?? item.observed_options;
    const observed: unknown[] = Array.isArray(observedSource)
        ? observedSource
        : [];
    const observationsSource =
        nested?.option_observations ?? item.option_observations;
    const observations: unknown[] = Array.isArray(observationsSource)
        ? observationsSource
        : [];
    if (nested || state || scalar || observed.length || observations.length) {
        return {
            state,
            selected_option: stringOr(
                nested?.selected_option ?? item.detected_answer ?? scalar,
                null,
            ),
            confidence: floatOr(nested?.confidence ?? item.confidence, null),
            observed_options: [...new Set(
                observed
                    .map((code) => String(code))
                    .filter((code) => code !== ''),
            )],
            option_observations: observations
                .filter(
                    (entry): entry is UnknownRecord => isRecord(entry),
                )
                .map((entry) => ({
                    code: stringOr(entry.code ?? entry.option, '') ?? '',
                    mean: floatOr(entry.mean ?? entry.bubble_mean, null),
                    classification: stringOr(entry.classification, null),
                }))
                .filter((entry) => entry.code !== ''),
            issue_codes: normalizeIssueCodes(
                nested?.issue_codes ?? item.issue_codes,
            ),
            evidence_hash: stringOr(
                nested?.evidence_hash ?? item.evidence_hash,
                null,
            ),
        };
    }
    return null;
}

function normalizeReview(value: unknown): NormalizedReview | null {
    if (!isRecord(value)) return null;
    const reviewer = isRecord(value.reviewer)
        ? {
              id:
                  typeof value.reviewer.id === 'string' ||
                  typeof value.reviewer.id === 'number'
                      ? value.reviewer.id
                      : null,
              display_name: stringOr(
                  value.reviewer.display_name ?? value.reviewer.name,
                  null,
              ),
          }
        : null;
    return {
        revision: numberOr(value.revision, null),
        decision: stringOr(value.decision, null),
        selected_option: stringOr(value.selected_option, null),
        reason_code: stringOr(value.reason_code, null),
        note: stringOr(value.note, null),
        reviewer,
        reviewed_at: stringOr(value.reviewed_at ?? value.created_at, null),
        evidence_hash: stringOr(
            value.evidence_hash ?? value.reviewed_machine_evidence_hash,
            null,
        ),
        stale: value.stale === true,
    };
}

function normalizeEffective(value: unknown): NormalizedEffective | null {
    if (!isRecord(value)) return null;
    return {
        state: stringOr(value.state, null),
        selected_option: stringOr(value.selected_option, null),
        source: stringOr(value.source, null),
    };
}

function normalizeIssueCodes(value: unknown): string[] {
    if (!Array.isArray(value)) return [];
    return [...new Set(value.map(String).filter((code) => code !== ''))];
}

function normalizeExact(
    item: UnknownRecord,
    detected: NormalizedDetected | null,
    expected: string | null,
): boolean | null {
    if (item.exact != null) return Boolean(item.exact);
    if (detected === null || expected === null) return null;
    if (
        detected.state === 'ambiguous' ||
        detected.state === 'unreadable' ||
        detected.state === 'blank'
    )
        return false;
    if (detected.state === 'match' || detected.state === 'marked')
        return detected.selected_option === expected;
    if (detected.state === 'mismatch') return false;
    return detected.selected_option === expected;
}

function normalizeShifts(
    value: unknown,
): { block: string; x: number | null; y: number | null }[] {
    if (!Array.isArray(value)) return [];
    return value
        .map((entry) => {
            if (!isRecord(entry)) return null;
            return {
                block: stringOr(entry.block, ''),
                x: floatOr(entry.x, null),
                y: floatOr(entry.y, null),
            };
        })
        .filter(
            (
                entry,
            ): entry is { block: string; x: number | null; y: number | null } =>
                entry !== null && entry.block !== '',
        );
}

function normalizeTimings(value: unknown): EvidenceView['timings'] {
    const timings = isRecord(value) ? (value as UnknownRecord) : {};
    return {
        preprocess_ms: floatOr(timings.preprocess_ms, null),
        layout_verification_ms: floatOr(
            timings.layout_verification_ms ?? timings.verification_ms,
            null,
        ),
        detection_ms: floatOr(timings.detection_ms, null),
    };
}

function normalizeAsset(asset: UnknownRecord | null): UnknownRecord | null {
    if (!isRecord(asset)) return null;
    const url = stringOr(asset.url, null);
    const sha256 = stringOr(asset.sha256, null);
    return {
        url,
        mime: stringOr(asset.mime, null),
        width: numberOr(asset.width, null),
        height: numberOr(asset.height, null),
        sha256,
    };
}

function stringOr(value: unknown, fallback: string | null): string | null {
    return typeof value === 'string' && value !== '' ? value : fallback;
}

function numberOr(value: unknown, fallback: number | null): number | null {
    const parsed =
        typeof value === 'number'
            ? value
            : value == null || value === ''
              ? NaN
              : Number(value);
    return Number.isFinite(parsed) ? parsed : fallback;
}

function floatOr(value: unknown, fallback: number | null): number | null {
    if (value == null) return fallback;
    const parsed = typeof value === 'number' ? value : Number(value);
    return Number.isFinite(parsed) ? parsed : fallback;
}
