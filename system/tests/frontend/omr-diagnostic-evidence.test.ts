// @vitest-environment jsdom

import { cleanup, fireEvent, render, waitFor } from '@testing-library/svelte';
import { afterEach, describe, expect, it, vi } from 'vitest';
import OmrDiagnosticEvidenceModal from '../../resources/js/Components/Omr/Diagnostics/OmrDiagnosticEvidenceModal.svelte';
import OmrDiagnosticEvidencePanel from '../../resources/js/Components/Omr/Diagnostics/OmrDiagnosticEvidencePanel.svelte';
import { buildBubbleLayerRecipe } from '../../resources/js/lib/omrBubbleEvidenceOverlay.js';
import {
    computeSummary,
    cropKey,
    detectedText,
    filterItems,
    gateEntries,
    hashesEntries,
    identityProblem,
    itemProblem,
    mappingUrl,
    meanText,
    normalizeEvidence,
    normalizeEvidenceFilter,
    normalizeEvidencePage,
    normalizeItem,
    returnLabel,
    reviewIsStale,
    reviewPayloadForItem,
    shiftForItem,
    shortHash,
    stateLabel,
    unsafeBlocks,
    type EvidenceView,
} from '../../resources/js/lib/omrDiagnosticEvidence.js';
import { presentOmrEvidenceReason } from '../../resources/js/Components/Omr/Diagnostics/omrEvidenceReasons';
const originalScrollIntoView = Object.getOwnPropertyDescriptor(
    Element.prototype,
    'scrollIntoView',
);

/** Recursive key-set comparison: identical projection schema ⇒ identical rendering. */
function schemaEqual(left: unknown, right: unknown, path = 'root'): void {
    if (Array.isArray(left) || Array.isArray(right)) {
        expect(Array.isArray(left), `${path}: array-ness`).toBe(
            Array.isArray(right),
        );
        if (
            Array.isArray(left) &&
            Array.isArray(right) &&
            left.length &&
            right.length
        ) {
            expect(typeof left[0], `${path}: element type`).toBe(
                typeof right[0],
            );
            if (typeof left[0] === 'object' && left[0] !== null) {
                schemaEqual(left[0], right[0], `${path}[0]`);
            }
        }
        return;
    }
    if (
        typeof left === 'object' &&
        left !== null &&
        typeof right === 'object' &&
        right !== null
    ) {
        const leftKeys = Object.keys(left).sort();
        const rightKeys = Object.keys(right).sort();
        expect(rightKeys, `${path}: keys`).toEqual(leftKeys);
        for (const key of leftKeys) {
            const leftValue = (left as Record<string, unknown>)[key];
            const rightValue = (right as Record<string, unknown>)[key];
            // Nullable slots: absent values (null) are allowed on either side.
            if (leftValue === null || rightValue === null) {
                expect(
                    rightValue === null || leftValue === null,
                    `${path}.${key}: nullable`,
                ).toBe(true);
                continue;
            }
            schemaEqual(leftValue, rightValue, `${path}.${key}`);
        }
        return;
    }
    expect(typeof right, `${path}: type`).toBe(typeof left);
}

function mcqItem(overrides: Record<string, unknown> = {}) {
    return {
        position: 1,
        area_code: 'LOGIC',
        question_no: 5,
        native_label: 'LOGIC_Q_005',
        page_no: 1,
        region: { x: 0.1, y: 0.2, w: 0.7, h: 0.08 },
        options: [
            {
                code: 'A',
                region: { x: 0.12, y: 0.2, w: 0.1, h: 0.08 },
                mean: 42,
                threshold_status: 'Unqualified',
            },
            {
                code: 'B',
                region: { x: 0.3, y: 0.2, w: 0.1, h: 0.08 },
                mean: 190,
                threshold_status: 'Unqualified',
            },
            {
                code: 'C',
                region: { x: 0.48, y: 0.2, w: 0.1, h: 0.08 },
                mean: 180,
                threshold_status: 'Unqualified',
            },
            {
                code: 'D',
                region: { x: 0.66, y: 0.2, w: 0.1, h: 0.08 },
                mean: 188,
                threshold_status: 'Unqualified',
            },
        ],
        detected: { state: 'marked', selected_option: 'B', confidence: 0.92 },
        expected: 'B',
        exact: true,
        ...overrides,
    };
}

const builderLegacyEvidence = {
    outcome: 'succeeded',
    aligned_url: '/assets/aligned.png',
    overlay_url: '/assets/overlay.png',
    aligned_image: {
        mime: 'image/png',
        width: 1240,
        height: 1754,
        hash: 'abc123def456',
    },
    overlay_image: {
        mime: 'image/png',
        width: 1240,
        height: 1754,
        hash: 'fed654cba321',
    },
    marker_matches: { tl: 0.91, tr: 0.87, bl: 0.83, br: 0.89 },
    field_block_shifts: [{ block: 'LOGIC', x: 1.2, y: -0.4 }],
    bubble_means: {
        LOGIC_Q_005: [{ label: 'LOGIC_Q_005', option: 'A', mean: 42 }],
    },
    item_evidence: [
        {
            label: 'LOGIC_Q_005',
            page: 1,
            item_rect: { x: 0.1, y: 0.2, w: 0.7, h: 0.08 },
            options: [
                {
                    option: 'A',
                    rect: { x: 0.12, y: 0.2, w: 0.1, h: 0.08 },
                    crop_png_base64: '',
                    bubble_mean: 42,
                    threshold_delta: 158.0,
                    threshold_status: 'Unqualified',
                },
                {
                    option: 'B',
                    rect: { x: 0.3, y: 0.2, w: 0.1, h: 0.08 },
                    crop_png_base64: '',
                    bubble_mean: 190,
                    threshold_delta: 10.0,
                    threshold_status: 'Unqualified',
                },
                {
                    option: 'C',
                    rect: { x: 0.48, y: 0.2, w: 0.1, h: 0.08 },
                    crop_png_base64: '',
                    bubble_mean: 180,
                    threshold_delta: 20.0,
                    threshold_status: 'Unqualified',
                },
                {
                    option: 'D',
                    rect: { x: 0.66, y: 0.2, w: 0.1, h: 0.08 },
                    crop_png_base64: '',
                    bubble_mean: 188,
                    threshold_delta: 12.0,
                    threshold_status: 'Unqualified',
                },
            ],
            field_block_shifts: [{ block: 'LOGIC', x: 1.2, y: -0.4 }],
            source_hash: 'src-hash',
            mapping_hash: 'map-hash',
            bundle_hash: 'bundle-hash',
        },
    ],
    gates: {
        marker_count: 'pass',
        registration: 'pass',
        dimensions: 'pass',
        shifts: 'pass',
        unsafe_blocks: [],
    },
    calibration_status: 'unqualified',
    timings: { preprocess_ms: 12.5, verification_ms: 84.2 },
};

const scannerCanonicalEvidence = {
    outcome: 'succeeded',
    code: null,
    detail: null,
    page_no: 1,
    target: {
        kind: 'compiled_version',
        id: 42,
        page_count: 1,
        qualification_capable: true,
        marker_profile: {
            code: 'omrchecker.sample',
            version: 1,
            qualified: true,
            asset_name: 'marker.png',
            asset_hash: 'm-hash',
        },
        hashes: {
            artifact: 'a-hash',
            native_bundle: 'n-hash',
            manifest: 'm-hash',
            config: 'c-hash',
            mapping: 'map-hash',
        },
        engine: {
            name: 'OMRChecker',
            commit: 'abc',
            patch_identity: 'nexiam-v1',
        },
    },
    hashes: {
        input: 'src-hash',
        aligned: 'abc123def456',
        overlay: 'fed654cba321',
        plan: 'plan-hash',
    },
    assets: {
        captured: {
            mime: 'image/jpeg',
            width: 3000,
            height: 4000,
            sha256: 'src-hash',
        },
        aligned: {
            mime: 'image/png',
            width: 1240,
            height: 1754,
            sha256: 'abc123def456',
        },
        overlay: {
            mime: 'image/png',
            width: 1240,
            height: 1754,
            sha256: 'fed654cba321',
        },
    },
    gates: {
        marker_count: 'pass',
        registration: 'pass',
        dimensions: 'pass',
        shifts: 'pass',
        unsafe_blocks: [],
    },
    marker_matches: { tl: 0.91, tr: 0.87, bl: 0.83, br: 0.89 },
    field_block_shifts: [{ block: 'LOGIC', x: 1.2, y: -0.4 }],
    items: [mcqItem()],
    identity: [
        {
            code: 'applicant_number',
            position: 1,
            page_no: 1,
            region: { x: 0.05, y: 0.05, w: 0.4, h: 0.06 },
            confidence: 0.9,
            detected_value: '7',
            expected_digit: '7',
            exact: true,
        },
    ],
    summary: {
        total: 1,
        expected: 1,
        exact: 1,
        ambiguous: 0,
        missing: 0,
        vector_match: true,
    },
    timings: {
        preprocess_ms: 12.5,
        layout_verification_ms: 84.2,
        detection_ms: 20.1,
    },
    issues: [],
    calibration_status: 'unqualified',
    plan_hash: 'plan-hash',
    plan_purpose: 'digital',
    provider_item_evidence: null,
    evidence_hash: 'ev-hash',
};

describe('normalizeEvidence', () => {
    it('projects MCQ4/MCQ5/int declared-option evidence with regions and means', () => {
        const view = normalizeEvidence(scannerCanonicalEvidence);
        expect(view).not.toBeNull();
        expect(view!.items).toHaveLength(1);
        const item = view!.items[0];
        expect(item.label).toBe('LOGIC_Q_005');
        expect(item.options.map((option) => option.code)).toEqual([
            'A',
            'B',
            'C',
            'D',
        ]);
        expect(item.options.map((option) => option.mean)).toEqual([
            42, 190, 180, 188,
        ]);
        expect(item.region).toEqual({ x: 0.1, y: 0.2, w: 0.7, h: 0.08 });
        expect(item.detected).toEqual({
            state: 'marked',
            selected_option: 'B',
            confidence: 0.92,
        });
        expect(item.expected).toBe('B');
        expect(item.exact).toBe(true);
    });

    it('preserves MCQ5 and int option cardinality (A–E, 0–9)', () => {
        const five = normalizeEvidence({
            outcome: 'succeeded',
            items: [
                mcqItem({
                    options: ['A', 'B', 'C', 'D', 'E'].map((code, index) => ({
                        code,
                        region: {
                            x: 0.1 + index * 0.15,
                            y: 0.2,
                            w: 0.1,
                            h: 0.08,
                        },
                        mean: null,
                    })),
                }),
            ],
        });
        expect(five!.items[0].options.map((option) => option.code)).toEqual([
            'A',
            'B',
            'C',
            'D',
            'E',
        ]);

        const digits = normalizeEvidence({
            outcome: 'succeeded',
            items: [
                mcqItem({
                    options: Array.from({ length: 10 }, (_, index) => ({
                        code: String(index),
                        region: {
                            x: 0.1 + index * 0.08,
                            y: 0.2,
                            w: 0.05,
                            h: 0.08,
                        },
                        mean: null,
                    })),
                }),
            ],
        });
        expect(digits!.items[0].options.map((option) => option.code)).toEqual([
            '0',
            '1',
            '2',
            '3',
            '4',
            '5',
            '6',
            '7',
            '8',
            '9',
        ]);
    });

    it('flags expected≠detected as an incorrect problem and not exact', () => {
        const view = normalizeEvidence({
            outcome: 'succeeded',
            items: [
                mcqItem({
                    expected: 'B',
                    exact: false,
                    detected: {
                        state: 'marked',
                        selected_option: 'A',
                        confidence: 0.8,
                    },
                }),
            ],
        });
        const item = view!.items[0];
        expect(item.exact).toBe(false);
        expect(itemProblem(item)).toBe('incorrect');
        expect(filterItems(view!.items, 'problems')).toHaveLength(1);
        expect(computeSummary(view!.items)).toEqual({
            total: 1,
            expected: 1,
            exact: 0,
            ambiguous: 0,
            missing: 1,
            vector_match: false,
        });
    });

    it('treats persisted match rows as exact and never flags equal expected and detected values', () => {
        const view = normalizeEvidence({
            outcome: 'succeeded',
            items: [
                {
                    ...mcqItem(),
                    exact: undefined,
                    state: 'match',
                    detected: 'A',
                    expected: 'A',
                },
            ],
        })!;
        expect(view.items[0].exact).toBe(true);
        expect(itemProblem(view.items[0])).toBeNull();
    });

    it('counts ambiguous and missing detections exactly like the backend summary', () => {
        const view = normalizeEvidence({
            outcome: 'succeeded',
            items: [
                mcqItem({
                    position: 1,
                    expected: 'B',
                    exact: true,
                    detected: {
                        state: 'marked',
                        selected_option: 'B',
                        confidence: 0.9,
                    },
                }),
                mcqItem({
                    position: 2,
                    expected: 'C',
                    exact: false,
                    detected: {
                        state: 'ambiguous',
                        selected_option: null,
                        confidence: null,
                    },
                }),
                mcqItem({
                    position: 3,
                    expected: 'D',
                    exact: null,
                    detected: {
                        state: 'blank',
                        selected_option: null,
                        confidence: null,
                    },
                }),
            ],
        });
        expect(view!.summary).toEqual({
            total: 3,
            expected: 3,
            exact: 1,
            ambiguous: 1,
            missing: 1,
            vector_match: false,
        });
        expect(itemProblem(view!.items[1])).toBe('ambiguous');
        expect(itemProblem(view!.items[2])).toBe('missing');
        expect(
            filterItems(view!.items, 'problems').map((item) => item.position),
        ).toEqual([2, 3]);
        expect(filterItems(view!.items, 'all')).toHaveLength(3);
    });

    it('keeps untested rows out of exactness and problem views even when raw recognition is ambiguous', () => {
        const view = normalizeEvidence({
            ...builderLegacyEvidence,
            item_evidence: [
                {
                    ...builderLegacyEvidence.item_evidence[0],
                    expected: null,
                    state: 'ambiguous',
                },
            ],
        });
        expect(view!.items).toHaveLength(1);
        const item = view!.items[0];
        expect(item.expected).toBeNull();
        expect(item.exact).toBeNull();
        expect(itemProblem(item)).toBeNull();
        expect(view!.summary).toEqual({
            total: 1,
            expected: 0,
            exact: 0,
            ambiguous: 0,
            missing: 0,
            vector_match: null,
        });
    });

    it('normalizes the grading scanner review shape (proposal + projected geometry)', () => {
        const view = normalizeEvidence({
            outcome: 'succeeded',
            page_no: 2,
            assets: { aligned: { url: '/sheets/9/image' } },
            hashes: { input: 'sheet-hash' },
            items: [
                {
                    position: 3,
                    area_code: 'NUMERICAL',
                    question_no: 12,
                    page_no: 2,
                    region: { x: 0.2, y: 0.3, w: 0.6, h: 0.07 },
                    options: [
                        {
                            code: 'A',
                            region: { x: 0.22, y: 0.3, w: 0.09, h: 0.07 },
                        },
                        {
                            code: 'B',
                            region: { x: 0.4, y: 0.3, w: 0.09, h: 0.07 },
                        },
                    ],
                    state: 'marked',
                    detected_answer: 'B',
                    correct_option_code: 'B',
                    confidence: 0.88,
                    score: 1,
                },
            ],
        });
        expect(view!.crop_image_url).toBe('/sheets/9/image');
        const item = view!.items[0];
        expect(item.label).toBe('NUMERICAL Q12');
        expect(item.expected).toBe('B');
        expect(item.exact).toBe(true);
        expect(item.score).toBe(1);
        expect(item.detected).toEqual({
            state: 'marked',
            selected_option: 'B',
            confidence: 0.88,
        });
        expect(view!.summary).toEqual({
            total: 1,
            expected: 1,
            exact: 1,
            ambiguous: 0,
            missing: 0,
            vector_match: true,
        });
    });

    it('renders builder Test capture and canonical evidence through the SAME projection schema', () => {
        const builder = normalizeEvidence(builderLegacyEvidence, {
            cropImageUrl: '/assets/aligned.png',
        });
        const scanner = normalizeEvidence(scannerCanonicalEvidence);
        expect(builder).not.toBeNull();
        expect(scanner).not.toBeNull();
        schemaEqual(builder, scanner);
    });

    it('projects identical items for builder and scanner shapes describing the same page', () => {
        const builder = normalizeEvidence(builderLegacyEvidence, {
            cropImageUrl: '/assets/aligned.png',
        });
        const scanner = normalizeEvidence(scannerCanonicalEvidence, {
            cropImageUrl: '/assets/aligned.png',
        });
        const shared = (view: EvidenceView) =>
            view.items.map((item) => ({
                label: item.label,
                page_no: item.page_no,
                region: item.region,
                options: item.options.map((option) => ({
                    code: option.code,
                    region: option.region,
                    mean: option.mean,
                    threshold_status: option.threshold_status,
                })),
            }));
        expect(shared(builder!)).toEqual(shared(scanner!));
        expect(builder!.crop_image_url).toBe(scanner!.crop_image_url);
    });

    it('exposes gates, marker matches, shifts, and hashes in display-ready order', () => {
        const view = normalizeEvidence(scannerCanonicalEvidence)!;
        expect(gateEntries(view.gates).map((gate) => gate.key)).toEqual([
            'marker_count',
            'registration',
            'dimensions',
            'shifts',
        ]);
        expect(gateEntries(view.gates).every((gate) => gate.pass)).toBe(true);
        expect(
            unsafeBlocks({ ...view.gates, unsafe_blocks: ['PART_2'] }),
        ).toEqual(['PART_2']);
        expect(Object.values(view.marker_matches ?? {})).toEqual([
            0.91, 0.87, 0.83, 0.89,
        ]);
        expect(shiftForItem(view.items[0], view.field_block_shifts)).toEqual({
            block: 'LOGIC',
            x: 1.2,
            y: -0.4,
        });
        expect(
            shiftForItem(
                { ...view.items[0], area_code: 'OTHER', native_label: null },
                view.field_block_shifts,
            ),
        ).toBeNull();
        expect(hashesEntries(view).map((entry) => entry.key)).toEqual([
            'input',
            'aligned',
            'overlay',
            'plan',
            'evidence',
        ]);
        expect(shortHash('0123456789abcdef')).toBe('0123456789');
        expect(shortHash(null)).toBe('—');
        expect(meanText(42)).toBe('42.00');
        expect(meanText(null)).toBe('—');
    });

    it('normalizes identity evidence with expected/detected digits and exactness', () => {
        const view = normalizeEvidence(scannerCanonicalEvidence)!;
        expect(view.identity).toHaveLength(1);
        const entry = view.identity[0];
        expect(entry.expected_digit).toBe('7');
        expect(entry.detected_value).toBe('7');
        expect(entry.exact).toBe(true);
        expect(identityProblem(entry)).toBe(false);
        expect(identityProblem({ ...entry, detected_value: null })).toBe(true);
        expect(identityProblem({ ...entry, exact: false })).toBe(true);
    });

    it('maps return URLs onto the first problem item label', () => {
        const view = normalizeEvidence({
            outcome: 'succeeded',
            items: [
                mcqItem({ position: 1, exact: true }),
                mcqItem({
                    position: 2,
                    expected: 'C',
                    exact: false,
                    detected: {
                        state: 'marked',
                        selected_option: 'A',
                        confidence: 0.7,
                    },
                }),
            ],
        })!;
        expect(returnLabel(view, 'problems')).toBe('LOGIC_Q_005');
        expect(mappingUrl('/setup/map', returnLabel(view, 'problems'))).toBe(
            '/setup/map?row=LOGIC_Q_005',
        );
        expect(mappingUrl('/setup/map?x=1', 'LOGIC_Q_005')).toBe(
            '/setup/map?x=1&row=LOGIC_Q_005',
        );
        expect(mappingUrl('/setup/map', null)).toBeNull();
    });

    it('provides stable crop keys per item and tolerant state/detected text', () => {
        const view = normalizeEvidence(scannerCanonicalEvidence)!;
        expect(cropKey(view, view.items[0])).toBe(
            'abc123def456:1:LOGIC_Q_005:1',
        );
        expect(cropKey(view, { ...view.items[0], position: 2 })).not.toBe(
            cropKey(view, view.items[0]),
        );
        expect(detectedText(view.items[0])).toBe('B');
        expect(stateLabel('marked')).toBe('Marked');
        expect(stateLabel(null)).toBe('Unavailable');
    });

    it('returns null for malformed evidence instead of throwing', () => {
        expect(normalizeEvidence(null)).toBeNull();
        expect(normalizeEvidence('nope')).toBeNull();
        expect(normalizeEvidence({})).not.toBeNull();
        expect(
            normalizeEvidence({ item_evidence: 'not-an-array' })!.items,
        ).toEqual([]);
    });
});

describe('OMR evidence normalization and review contract', () => {
    it('normalizes evidence filters with singular mismatch as canonical', () => {
        expect(normalizeEvidenceFilter('mismatch')).toBe('mismatch');
        expect(normalizeEvidenceFilter('mismatches')).toBe('mismatch');
        expect(normalizeEvidenceFilter('needs_review')).toBe('needs_review');
        expect(normalizeEvidenceFilter('reviewed')).toBe('reviewed');
        expect(normalizeEvidenceFilter('all')).toBe('all');
        expect(normalizeEvidenceFilter('unknown_filter')).toBe('all');
        expect(normalizeEvidenceFilter(null)).toBe('all');
        expect(normalizeEvidenceFilter(undefined)).toBe('all');
    });

    it('normalizes server evidence page metadata, reasons, and bounds', () => {
        const raw = {
            method: 'pre_shaded',
            capture_id: 101,
            area: 'LOGIC',
            total: 10,
            mismatch_total: 2,
            needs_review_total: 3,
            reviewed_total: 1,
            page: 2,
            per_page: 80, // should clamp to 50
            page_count: 4,
            filter: 'mismatches', // should normalize to 'mismatch'
            parent_version: 7,
            reviewable: true,
            presentation: 'blind',
            review_note_max: 9999, // should clamp to 2000
            review_reasons: [
                { code: 'poor_erasure', label: 'Poor erasure' },
                { code: 'faint_mark', label: 'Faint candidate mark' },
                { code: 'poor_erasure', label: 'Duplicate reason' },
                { code: '', label: 'Ignored' },
            ],
            groups: [
                {
                    key: 'LOGIC',
                    label: 'Logic',
                    total: 10,
                    mismatch_total: 2,
                    page_count: 1,
                },
            ],
            items: [{ id: 1, item_id: 'Q1' }],
            lazy_asset_urls: { aligned: '/aligned.png' },
        };
        const page = normalizeEvidencePage(raw);
        expect(page).not.toBeNull();
        expect(page!.filter).toBe('mismatch');
        expect(page!.per_page).toBe(50);
        expect(page!.review_note_max).toBe(2000);
        expect(page!.parent_version).toBe(7);
        expect(page!.blind_review).toBe(true);
        expect(page!.reviewable).toBe(true);
        expect(page!.review_reasons).toEqual([
            { code: 'poor_erasure', label: 'Poor erasure' },
            { code: 'faint_mark', label: 'Faint candidate mark' },
            { code: 'poor_erasure', label: 'Duplicate reason' },
        ]);
        expect(page!.groups[0].key).toBe('LOGIC');

        const nonReviewable = normalizeEvidencePage({
            ...raw,
            reviewable: false,
            blind_review: false,
            presentation: 'standard',
            review_note_max: 0, // should clamp to 1
        });
        expect(nonReviewable!.reviewable).toBe(false);
        expect(nonReviewable!.blind_review).toBe(false);
        expect(nonReviewable!.review_note_max).toBe(1);
        expect(
            normalizeEvidencePage({
                method: 'pre_shaded',
                capture_id: 101,
            })?.reviewable,
        ).toBe(false);

        expect(normalizeEvidencePage(null)).toBeNull();
        expect(normalizeEvidencePage({})).toBeNull();
        expect(
            normalizeEvidencePage({ method: 'pre_shaded', capture_id: 0 }),
        ).toBeNull();
    });

    it('normalizes candidate-complete item evidence isolating machine from expected answers', () => {
        const itemHash = 'd'.repeat(64);
        const item = normalizeItem({
            id: 99,
            item_id: 'LOGIC_Q_009',
            native_label: 'LOGIC_Q_009',
            position: 9,
            page_no: 1,
            options: [
                {
                    code: 'A',
                    region: { x: 0.1, y: 0.2, w: 0.1, h: 0.08 },
                    mean: 200,
                },
                {
                    code: 'B',
                    region: { x: 0.3, y: 0.2, w: 0.1, h: 0.08 },
                    mean: 195,
                },
            ],
            machine: {
                state: 'ambiguous',
                selected_option: null,
                confidence: 0.78,
                observed_options: ['A', 'B'],
                option_observations: [
                    { code: 'A', mean: 200, classification: 'marked' },
                    { code: 'B', mean: 195, classification: 'marked' },
                ],
                issue_codes: ['multiple_marked'],
                evidence_hash: itemHash,
            },
            expected: 'A',
            effective: {
                state: 'marked',
                selected_option: 'A',
                source: 'effective',
            },
            review: {
                revision: 1,
                decision: 'set_option',
                selected_option: 'A',
                reason_code: 'poor_erasure',
                note: 'Confirmed candidate A',
                reviewer: { id: 3, display_name: 'Evaluator Smith' },
                reviewed_at: '2026-09-05T12:00:00Z',
                evidence_hash: itemHash,
                stale: false,
            },
            parent_version: 12,
            item_revision: 1,
        });

        expect(item.id).toBe(99);
        expect(item.item_id).toBe('LOGIC_Q_009');
        expect(item.machine?.state).toBe('ambiguous');
        expect(item.machine?.selected_option).toBeNull();
        expect(item.machine?.observed_options).toEqual(['A', 'B']);
        expect(item.machine?.issue_codes).toEqual(['multiple_marked']);
        expect(item.machine?.evidence_hash).toBe(itemHash);
        expect(item.expected).toBe('A');
        expect(item.review?.decision).toBe('set_option');
        expect(item.review?.reviewer?.display_name).toBe('Evaluator Smith');
        expect(item.effective?.selected_option).toBe('A');
        expect(item.effective?.source).toBe('effective');
        expect(item.exact).toBe(false);
    });

    it('detects stale review revisions when machine evidence hash changes', () => {
        const itemHash = 'a'.repeat(64);
        const staleItem = normalizeItem({
            id: 1,
            machine: {
                state: 'marked',
                selected_option: 'B',
                evidence_hash: itemHash,
            },
            review: {
                revision: 1,
                decision: 'confirm_machine',
                selected_option: 'B',
                evidence_hash: 'b'.repeat(64), // different hash => stale
                stale: false,
            },
        });
        expect(reviewIsStale(staleItem)).toBe(true);

        const currentItem = normalizeItem({
            id: 2,
            machine: {
                state: 'marked',
                selected_option: 'B',
                evidence_hash: itemHash,
            },
            review: {
                revision: 1,
                decision: 'confirm_machine',
                selected_option: 'B',
                evidence_hash: itemHash, // matching hash
                stale: false,
            },
        });
        expect(reviewIsStale(currentItem)).toBe(false);

        const explicitlyStaleItem = normalizeItem({
            id: 3,
            machine: {
                state: 'marked',
                selected_option: 'B',
                evidence_hash: itemHash,
            },
            review: {
                revision: 1,
                decision: 'confirm_machine',
                selected_option: 'B',
                evidence_hash: itemHash,
                stale: true, // explicit flag
            },
        });
        expect(reviewIsStale(explicitlyStaleItem)).toBe(true);
    });

    it('builds exact review write payload bound to machine evidence and parent version', () => {
        const itemHash = 'f'.repeat(64);
        const item = normalizeItem({
            id: 42,
            parent_version: 11,
            item_revision: 2,
            machine: {
                state: 'ambiguous',
                selected_option: null,
                evidence_hash: itemHash,
            },
        });
        const draft = {
            decision: 'set_option' as const,
            selected_option: 'B',
            reason_code: 'faint_mark',
            note: 'Bubble B clearly intended',
        };
        const payload = reviewPayloadForItem(
            item,
            draft,
            'idemp-uuid-1234',
            11,
        );
        expect(payload).toEqual({
            decision: 'set_option',
            selected_option: 'B',
            reason_code: 'faint_mark',
            note: 'Bubble B clearly intended',
            expected_parent_version: 11,
            expected_item_revision: 2,
            reviewed_machine_evidence_hash: itemHash,
            idempotency_key: 'idemp-uuid-1234',
        });
    });
});

const componentEvidencePage = {
    method: 'pre_shaded',
    capture_id: 42,
    area: 'all',
    total: 50,
    mismatch_total: 1,
    page: 1,
    per_page: 25,
    page_count: 2,
    filter: 'all',
    evidence_version: 'v1',
    evidence_hash: 'e'.repeat(64),
    attempt_no: 2,
    capture_page_no: 1,
    groups: [
        {
            key: 'all',
            label: 'All areas',
            group_type: 'all',
            total: 50,
            mismatch_total: 1,
            page_count: 2,
        },
        {
            key: 'LOGIC',
            label: 'Logic',
            group_type: 'aptitude_area',
            area_key: 'LOGIC',
            area_position: 1,
            total: 25,
            mismatch_total: 1,
            page_count: 1,
        },
        {
            key: 'identity',
            label: 'Identity',
            group_type: 'identity',
            total: 1,
            mismatch_total: 0,
            page_count: 1,
        },
        {
            key: 'calibration',
            label: 'Calibration',
            group_type: 'calibration',
            total: 1,
            mismatch_total: 0,
            page_count: 1,
        },
    ],
    items: [
        {
            id: 1,
            item_id: 'LOGIC_Q_001',
            group_type: 'aptitude_area',
            area_key: 'LOGIC',
            area_position: 1,
            question_no: 1,
            options: [
                {
                    code: 'A',
                    region: { x: 0.1, y: 0.2, w: 0.1, h: 0.08 },
                    mean: 36,
                },
                {
                    code: 'B',
                    region: { x: 0.3, y: 0.2, w: 0.1, h: 0.08 },
                    mean: 218,
                },
                {
                    code: 'C',
                    region: { x: 0.5, y: 0.2, w: 0.1, h: 0.08 },
                    mean: 90,
                },
                {
                    code: 'D',
                    region: { x: 0.7, y: 0.2, w: 0.1, h: 0.08 },
                    mean: 72,
                },
            ],
            expected: 'B',
            detected: 'B',
            state: 'match',
            means: { A: 36, B: 218, C: 90, D: 72 },
            crop_rect: { x: 0.1, y: 0.1, w: 0.7, h: 0.08 },
        },
    ],
    lazy_asset_urls: {
        input: '/evidence/input',
        aligned: '/evidence/aligned',
        overlay: '/evidence/overlay',
    },
};

afterEach(() => {
    cleanup();
    if (originalScrollIntoView) {
        Object.defineProperty(
            Element.prototype,
            'scrollIntoView',
            originalScrollIntoView,
        );
    } else {
        delete Element.prototype.scrollIntoView;
    }
    vi.unstubAllGlobals();
});

describe('OmrDiagnosticEvidencePanel presentation contract', () => {
    it('presents unavailable evidence reasons as human guidance with inspectable codes', () => {
        const reasons = [
            ['missing-machine-evidence', 'Answer recognition is unavailable for this capture'],
            ['missing-asset', 'Captured sheet image is unavailable'],
            ['missing-evidence-hash', 'Scan evidence signature is unavailable'],
            ['missing-geometry-hash', 'Answer-sheet geometry signature is unavailable'],
            ['missing-crop-bounds', 'Item crop coordinates are unavailable'],
            ['missing-page-dimensions', 'Page dimensions are unavailable'],
        ] as const;

        for (const [code, message] of reasons) {
            expect(presentOmrEvidenceReason(code)).toEqual({
                message,
                technicalDetail: code,
            });
        }
        expect(presentOmrEvidenceReason('future-reason').message).toBe(
            'Evidence could not be verified',
        );
    });

    it('keeps evidence bounded and drives tabs, groups, filters, pagination, and row details through callbacks', async () => {
        const callbacks = {
            onTabChange: vi.fn(),
            onGroupChange: vi.fn(),
            onFilterChange: vi.fn(),
            onPageChange: vi.fn(),
            onRowOpen: vi.fn(),
        };
        const { container, getByRole } = render(OmrDiagnosticEvidencePanel, {
            page: componentEvidencePage,
            initialTab: 'markings',
            ...callbacks,
        });

        expect(container.querySelectorAll('img')).toHaveLength(2);
        expect(container.querySelector('img')?.getAttribute('src')).toBe(
            '/evidence/aligned',
        );
        expect(container.innerHTML).not.toContain('base64');
        expect(container.querySelectorAll('[role=tab]')).toHaveLength(3);
        expect(
            getByRole('navigation', { name: 'Evidence areas' }),
        ).not.toBeNull();
        expect(
            getByRole('navigation', { name: 'Evidence pagination' }),
        ).not.toBeNull();
        expect(
            getByRole('button', { name: 'Evidence page 1' }).getAttribute(
                'aria-current',
            ),
        ).toBe('page');
        expect(getByRole('button', { name: 'Evidence page 2' })).not.toBeNull();

        expect(
            getByRole('tab', { name: 'Markings evidence' }).getAttribute(
                'aria-selected',
            ),
        ).toBe('true');
        await fireEvent.click(
            getByRole('tab', { name: 'Raw evidence' }),
        );
        expect(callbacks.onTabChange).toHaveBeenCalledWith('raw');
        expect(container.querySelector('img')?.getAttribute('src')).toBe(
            '/evidence/input',
        );
        await fireEvent.click(getByRole('button', { name: /Logic: 25 items/ }));
        expect(callbacks.onGroupChange).toHaveBeenCalledWith('LOGIC');
        await fireEvent.click(getByRole('button', { name: /Mismatches/ }));
        expect(callbacks.onFilterChange).toHaveBeenCalledWith('mismatch');
        await fireEvent.click(
            getByRole('button', { name: 'Next evidence page' }),
        );
        expect(callbacks.onPageChange).toHaveBeenCalledWith(2);

        await fireEvent.click(getByRole('button', { name: /LOGIC_Q_001/ }));
        expect(callbacks.onRowOpen).toHaveBeenCalledWith(
            expect.objectContaining({ item_id: 'LOGIC_Q_001' }),
        );
        const crop = container.querySelector('[data-omr-crop-status]');
        expect(crop?.querySelector('img')?.getAttribute('src')).toBe(
            '/evidence/input',
        );
        expect(container.textContent).not.toContain(
            'Crop unavailable for this row.',
        );
    });

    it('uses one full-sheet map and derives selected item detail from its page geometry', async () => {
        const onViewChange = vi.fn();
        const mappedItem = {
            id: 99,
            item_id: 'LOGIC_Q_026',
            group_type: 'aptitude_area',
            area_key: 'LOGIC',
            area_position: 1,
            question_no: 26,
            page_no: 1,
            machine: {
                state: 'ambiguous',
                selected_option: null,
                observed_options: ['A', 'B'],
                option_observations: [],
                issue_codes: [],
                evidence_hash: 'b'.repeat(64),
            },
            expected: { option: 'B' },
            geometry: {
                item_rect: { x: 0.1, y: 0.2, w: 0.32, h: 0.06 },
                crop_rect: { x: 0.1, y: 0.2, w: 0.32, h: 0.06 },
                options: [
                    { code: 'A', region: { x: 0.11, y: 0.21, w: 0.04, h: 0.04 } },
                    { code: 'B', region: { x: 0.19, y: 0.21, w: 0.04, h: 0.04 } },
                    { code: 'C', region: { x: 0.27, y: 0.21, w: 0.04, h: 0.04 } },
                    { code: 'D', region: { x: 0.35, y: 0.21, w: 0.04, h: 0.04 } },
                ],
                geometry_manifest_hash: 'c'.repeat(64),
                evidence_version: 'v1',
                page_no: 1,
            },
            issues: [],
        };
        const page = {
            ...componentEvidencePage,
            sheet_map: {
                page_no: 1,
                width: 1200,
                height: 1600,
                sha256: 'a'.repeat(64),
                items: [mappedItem],
            },
        };
        const view = render(OmrDiagnosticEvidencePanel, {
            page,
            onViewChange,
        });

        const sheetImage = view.getByRole('img', {
            name: /Aligned answer sheet with interactive/,
        });
        expect(sheetImage).not.toBeNull();
        const workbenchGrid = sheetImage.closest('.grid');
        expect(workbenchGrid?.className).toContain(
            'lg:grid-cols-[minmax(0,7fr)_minmax(18rem,3fr)]',
        );
        expect(workbenchGrid?.className).not.toContain('grid-cols-1');
        expect(view.getByText('Actionable issues')).not.toBeNull();
        expect(view.getAllByText('Ambiguous').length).toBeGreaterThan(0);
        await fireEvent.click(
            view.getByRole('button', { name: 'Review item' }),
        );
        expect(onViewChange).toHaveBeenCalledWith({
            area: 'LOGIC',
            filter: 'all',
            page: 1,
        });
    });


    it('disables the mismatch filter when every tested mark matches', async () => {
        const onFilterChange = vi.fn();
        const page = {
            ...componentEvidencePage,
            mismatch_total: 0,
        };
        const { getByRole } = render(OmrDiagnosticEvidencePanel, {
            page,
            onFilterChange,
        });

        const mismatches = getByRole('button', { name: /Mismatches/ });
        expect((mismatches as HTMLButtonElement).disabled).toBe(true);
        await fireEvent.click(mismatches);
        expect(onFilterChange).not.toHaveBeenCalled();
    });

    it('returns to item rows after area, filter, and pagination navigation completes', async () => {
        const scrollIntoView = vi.fn();
        Object.defineProperty(Element.prototype, 'scrollIntoView', {
            configurable: true,
            value: scrollIntoView,
        });
        const callbacks = {
            onGroupChange: vi.fn(),
            onFilterChange: vi.fn(),
            onPageChange: vi.fn(),
        };
        const view = render(OmrDiagnosticEvidencePanel, {
            page: componentEvidencePage,
            ...callbacks,
        });

        await fireEvent.click(
            view.getByRole('button', { name: /LOGIC_Q_001/ }),
        );
        await fireEvent.click(
            view.getByRole('button', { name: /Logic: 25 items/ }),
        );
        expect(callbacks.onGroupChange).toHaveBeenCalledWith('LOGIC');
        await view.rerender({
            page: {
                ...componentEvidencePage,
                area: 'LOGIC',
                page_count: 1,
            },
            ...callbacks,
        });
        await waitFor(() => expect(scrollIntoView).toHaveBeenCalledTimes(1));
        expect(
            view
                .getByRole('button', { name: /LOGIC_Q_001/ })
                .getAttribute('aria-expanded'),
        ).toBe('false');

        await fireEvent.click(view.getByRole('button', { name: /Mismatches/ }));
        expect(callbacks.onFilterChange).toHaveBeenCalledWith('mismatch');
        await view.rerender({
            page: {
                ...componentEvidencePage,
                area: 'LOGIC',
                filter: 'mismatch',
                page_count: 2,
            },
            ...callbacks,
        });
        await waitFor(() => expect(scrollIntoView).toHaveBeenCalledTimes(2));

        await fireEvent.click(
            view.getByRole('button', { name: 'Next evidence page' }),
        );
        expect(callbacks.onPageChange).toHaveBeenCalledWith(2);
        await view.rerender({
            page: {
                ...componentEvidencePage,
                area: 'LOGIC',
                filter: 'mismatches',
                page: 2,
                page_count: 2,
            },
            ...callbacks,
        });
        await waitFor(() => expect(scrollIntoView).toHaveBeenCalledTimes(3));
    });

    it('renders loading, empty, error, retry, and keyboard-accessible states', async () => {
        const retry = vi.fn();
        const loading = render(OmrDiagnosticEvidencePanel, { loading: true });
        expect(loading.getByRole('status').textContent).toContain(
            'Evidence is loading',
        );
        cleanup();

        const empty = render(OmrDiagnosticEvidencePanel, { onRetry: retry });
        expect(empty.getByRole('status').textContent).toContain(
            'No evidence is available',
        );
        await fireEvent.click(empty.getByRole('button', { name: 'Retry' }));
        expect(retry).toHaveBeenCalledTimes(1);
        cleanup();

        const failed = render(OmrDiagnosticEvidencePanel, {
            error: { detail: 'Private evidence unavailable.' },
            onRetry: retry,
        });
        expect(failed.getByRole('alert').textContent).toContain(
            'Private evidence unavailable.',
        );
        await fireEvent.click(failed.getByRole('button', { name: 'Retry' }));
        expect(retry).toHaveBeenCalledTimes(2);
    });
});

const testReasons = [
    { code: 'poor_erasure', label: 'Poor erasure' },
    { code: 'faint_mark', label: 'Faint candidate mark' },
    { code: 'unclear_intent', label: 'Unclear intent' },
];

function buildReviewTestItem(overrides: Record<string, unknown> = {}) {
    return {
        id: 1,
        item_id: 'LOGIC_Q_001',
        group_type: 'aptitude_area',
        area_key: 'LOGIC',
        area_position: 1,
        question_no: 1,
        parent_version: 3,
        item_revision: 0,
        reviewable: true,
        options: [
            {
                code: 'A',
                region: { x: 0.1, y: 0.2, w: 0.1, h: 0.08 },
                mean: 36,
            },
            {
                code: 'B',
                region: { x: 0.3, y: 0.2, w: 0.1, h: 0.08 },
                mean: 218,
            },
            {
                code: 'C',
                region: { x: 0.5, y: 0.2, w: 0.1, h: 0.08 },
                mean: 90,
            },
            {
                code: 'D',
                region: { x: 0.7, y: 0.2, w: 0.1, h: 0.08 },
                mean: 72,
            },
        ],
        machine: {
            state: 'marked',
            selected_option: 'B',
            confidence: 0.94,
            observed_options: ['B'],
            option_observations: [
                { code: 'A', mean: 36, classification: 'blank' },
                { code: 'B', mean: 218, classification: 'marked' },
                { code: 'C', mean: 90, classification: 'blank' },
                { code: 'D', mean: 72, classification: 'blank' },
            ],
            issue_codes: [],
            evidence_hash: 'b'.repeat(64),
        },
        expected: 'B',
        detected: 'B',
        state: 'marked',
        provenance: {
            asset: {
                url: '/evidence/aligned',
                sha256: 'a'.repeat(64),
                width: 1200,
                height: 1600,
                page_no: 1,
            },
            page_asset: {
                url: '/evidence/aligned',
                sha256: 'a'.repeat(64),
                width: 1200,
                height: 1600,
                page_no: 1,
            },
            crop_source: null,
            geometry_manifest_hash: 'g'.repeat(64),
            evidence_version: 'v1',
            page_no: 1,
        },
        geometry: {
            item_rect: { x: 0.1, y: 0.2, w: 0.7, h: 0.08 },
            crop_rect: { x: 0.1, y: 0.2, w: 0.7, h: 0.08 },
            options: [
                { code: 'A', region: { x: 0.1, y: 0.2, w: 0.1, h: 0.08 } },
                { code: 'B', region: { x: 0.3, y: 0.2, w: 0.1, h: 0.08 } },
                { code: 'C', region: { x: 0.5, y: 0.2, w: 0.1, h: 0.08 } },
                { code: 'D', region: { x: 0.7, y: 0.2, w: 0.1, h: 0.08 } },
            ],
            aligned_asset: {
                url: '/evidence/aligned',
                sha256: 'a'.repeat(64),
                width: 1200,
                height: 1600,
                page_no: 1,
            },
            geometry_manifest_hash: 'g'.repeat(64),
            evidence_version: 'v1',
            page_no: 1,
        },
        crop_rect: { x: 0.1, y: 0.2, w: 0.7, h: 0.08 },
        ...overrides,
    };
}

function buildReviewTestPage(overrides: Record<string, unknown> = {}) {
    return {
        method: 'pre_shaded',
        capture_id: 42,
        area: 'all',
        total: 2,
        mismatch_total: 1,
        needs_review_total: 1,
        reviewed_total: 0,
        page: 1,
        per_page: 25,
        page_count: 2,
        filter: 'all',
        evidence_version: 'v1',
        evidence_hash: 'e'.repeat(64),
        attempt_no: 2,
        capture_page_no: 1,
        parent_version: 5,
        reviewable: true,
        blind_review: false,
        review_note_max: 500,
        review_reasons: testReasons,
        groups: [
            {
                key: 'all',
                label: 'All areas',
                group_type: 'all',
                total: 2,
                mismatch_total: 1,
                page_count: 2,
            },
            {
                key: 'LOGIC',
                label: 'Logic',
                group_type: 'aptitude_area',
                area_key: 'LOGIC',
                area_position: 1,
                total: 2,
                mismatch_total: 1,
                page_count: 2,
            },
        ],
        items: [
            buildReviewTestItem(),
            buildReviewTestItem({
                id: 2,
                item_id: 'LOGIC_Q_002',
                question_no: 2,
                options: [
                    {
                        code: 'A',
                        region: { x: 0.1, y: 0.3, w: 0.1, h: 0.08 },
                        mean: 190,
                    },
                    {
                        code: 'B',
                        region: { x: 0.3, y: 0.3, w: 0.1, h: 0.08 },
                        mean: 195,
                    },
                    {
                        code: 'C',
                        region: { x: 0.5, y: 0.3, w: 0.1, h: 0.08 },
                        mean: 50,
                    },
                    {
                        code: 'D',
                        region: { x: 0.7, y: 0.3, w: 0.1, h: 0.08 },
                        mean: 40,
                    },
                ],
                machine: {
                    state: 'ambiguous',
                    selected_option: null,
                    confidence: null,
                    observed_options: ['A', 'B'],
                    option_observations: [
                        { code: 'A', mean: 190, classification: 'marked' },
                        { code: 'B', mean: 195, classification: 'marked' },
                        { code: 'C', mean: 50, classification: 'blank' },
                        { code: 'D', mean: 40, classification: 'blank' },
                    ],
                    issue_codes: ['multiple_marked'],
                    evidence_hash: 'c'.repeat(64),
                },
                expected: 'A',
                detected: null,
                state: 'ambiguous',
                geometry: {
                    item_rect: { x: 0.1, y: 0.3, w: 0.7, h: 0.08 },
                    crop_rect: { x: 0.1, y: 0.3, w: 0.7, h: 0.08 },
                    options: [
                        {
                            code: 'A',
                            region: { x: 0.1, y: 0.3, w: 0.1, h: 0.08 },
                        },
                        {
                            code: 'B',
                            region: { x: 0.3, y: 0.3, w: 0.1, h: 0.08 },
                        },
                        {
                            code: 'C',
                            region: { x: 0.5, y: 0.3, w: 0.1, h: 0.08 },
                        },
                        {
                            code: 'D',
                            region: { x: 0.7, y: 0.3, w: 0.1, h: 0.08 },
                        },
                    ],
                    aligned_asset: {
                        url: '/evidence/aligned',
                        sha256: 'a'.repeat(64),
                        width: 1200,
                        height: 1600,
                        page_no: 1,
                    },
                    geometry_manifest_hash: 'g'.repeat(64),
                    evidence_version: 'v1',
                    page_no: 1,
                },
                crop_rect: { x: 0.1, y: 0.3, w: 0.7, h: 0.08 },
            }),
        ],
        lazy_asset_urls: {
            input: '/evidence/input',
            aligned: '/evidence/aligned',
            overlay: '/evidence/overlay',
        },
        ...overrides,
    };
}

function buildStencilPage(
    items: Record<string, unknown>[],
    overrides: Record<string, unknown> = {},
) {
    const stencilItems = items.map((item) => {
        const geometry =
            item.geometry &&
            typeof item.geometry === 'object' &&
            !Array.isArray(item.geometry)
                ? (item.geometry as Record<string, unknown>)
                : {};
        const provenance =
            item.provenance &&
            typeof item.provenance === 'object' &&
            !Array.isArray(item.provenance)
                ? (item.provenance as Record<string, unknown>)
                : {};
        return {
            ...item,
            geometry: {
                ...geometry,
                geometry_manifest_hash: 'c'.repeat(64),
            },
            provenance: {
                ...provenance,
                geometry_manifest_hash: 'c'.repeat(64),
            },
        };
    });
    const total = stencilItems.length;
    return buildReviewTestPage({
        total,
        mismatch_total: total,
        page: 1,
        page_count: 1,
        groups: [
            {
                key: 'all',
                label: 'All areas',
                group_type: 'all',
                total,
                mismatch_total: total,
                page_count: 1,
            },
            {
                key: 'LOGIC',
                label: 'Logic',
                group_type: 'aptitude_area',
                area_key: 'LOGIC',
                area_position: 1,
                total,
                mismatch_total: total,
                page_count: 1,
            },
        ],
        items: stencilItems,
        sheet_map: {
            page_no: 1,
            width: 1200,
            height: 1600,
            sha256: 'a'.repeat(64),
            items: stencilItems,
            document_fields: [],
        },
        ...overrides,
    });
}

describe('OMR stencil render contract', () => {
    it('renders opaque white rectangular stencil cards with only the expected hole on sheet and queue', () => {
        const item = buildReviewTestItem();
        const page = buildStencilPage([item]);
        const view = render(OmrDiagnosticEvidencePanel, {
            page,
            initialTab: 'stencil',
        });
        const sheetStencil = view.container.querySelector(
            '[data-omr-sheet-stencil]',
        );
        const queueStencil = view.container.querySelector(
            '[data-omr-bubble-stencil]',
        );
        expect(sheetStencil).not.toBeNull();
        expect(queueStencil).not.toBeNull();

        for (const root of [sheetStencil!, queueStencil!]) {
            const card = root.querySelector('[data-omr-stencil-card]');
            expect(card?.tagName.toLowerCase()).toBe('path');
            expect(card?.getAttribute('fill')).toBe('#ffffff');
            expect(card?.getAttribute('fill-opacity')).toBe('1');
            expect(card?.getAttribute('fill-rule')).toBe('evenodd');
            expect(card?.getAttribute('stroke')).toBe('none');
            expect(root.querySelectorAll('[data-omr-stencil-card]')).toHaveLength(
                1,
            );
            expect(root.querySelectorAll('.stencil-card-border')).toHaveLength(
                1,
            );
            expect(
                root.querySelectorAll(
                    '[data-omr-stencil-hole][data-option="B"]',
                ),
            ).toHaveLength(1);
            expect(
                root
                    .querySelector('[data-omr-stencil-hole]')
                    ?.getAttribute('fill'),
            ).toBe('none');
            expect(root.querySelector('.stencil-mask')).toBeNull();
            expect(
                root.querySelector(
                    '.machine-fill, .candidate-hatch, .geometry-ring, .expected-ring',
                ),
            ).toBeNull();
        }

        const sheetPath =
            sheetStencil?.querySelector('[data-omr-stencil-card]')?.getAttribute('d') ??
            '';
        const sheetMatch = sheetPath.match(
            /^M ([\d.e+-]+) ([\d.e+-]+) H ([\d.e+-]+) V ([\d.e+-]+) H ([\d.e+-]+) Z M ([\d.e+-]+) ([\d.e+-]+) a ([\d.e+-]+) ([\d.e+-]+)/,
        );
        expect(sheetMatch).not.toBeNull();
        const sheetNumbers = sheetMatch!.slice(1).map(Number);
        expect(sheetNumbers[0]).toBeCloseTo(109.2, 4);
        expect(sheetNumbers[1]).toBeCloseTo(308.48, 4);
        expect(sheetNumbers[2]).toBeCloseTo(970.8, 4);
        expect(sheetNumbers[3]).toBeCloseTo(459.52, 4);
        expect(sheetNumbers[4]).toBeCloseTo(109.2, 4);
        expect(sheetNumbers[5]).toBeCloseTo(349.2, 4);
        expect(sheetNumbers[6]).toBeCloseTo(384, 4);
        expect(sheetNumbers[7]).toBeCloseTo(70.8, 4);
        expect(sheetNumbers[8]).toBeCloseTo(75.52, 4);
        expect(sheetPath).toContain(' Z M ');
        expect(sheetPath).toContain(' a ');
        expect((sheetPath.match(/ Z M /g) ?? [])).toHaveLength(1);
        expect((sheetPath.match(/\ba\b/g) ?? [])).toHaveLength(2);

        const queuePath =
            queueStencil?.querySelector('[data-omr-stencil-card]')?.getAttribute('d') ??
            '';
        expect(queuePath).toMatch(/^M 0 0 H 1 V 1 H 0 Z M /);
        expect(queuePath).toContain(' a ');
        expect((queuePath.match(/ Z M /g) ?? [])).toHaveLength(1);
        expect((queuePath.match(/\ba\b/g) ?? [])).toHaveLength(2);
        const cropRecipe = buildBubbleLayerRecipe(page.items[0]);
        expect(cropRecipe.status).toBe('ready');
        const cropExpected = cropRecipe.visuals.find(
            (visual) => visual.code === 'B' && visual.expected_ring,
        );
        const cropHole = queueStencil?.querySelector(
            '[data-omr-stencil-hole]',
        );
        expect(cropExpected).toBeDefined();
        expect(Number(cropHole?.getAttribute('cx'))).toBeCloseTo(
            cropExpected!.geometry.x + cropExpected!.geometry.w / 2,
            6,
        );
        expect(Number(cropHole?.getAttribute('cy'))).toBeCloseTo(
            cropExpected!.geometry.y + cropExpected!.geometry.h / 2,
            6,
        );
        expect(Number(cropHole?.getAttribute('rx'))).toBeCloseTo(
            cropExpected!.geometry.w * 0.59,
            6,
        );
        expect(Number(cropHole?.getAttribute('ry'))).toBeCloseTo(
            cropExpected!.geometry.h * 0.59,
            6,
        );
    });

    it('keeps stencil geometry independent of detected marks', () => {
        const baseMachine = buildReviewTestItem().machine as Record<
            string,
            unknown
        >;
        const scenarios = [
            {
                name: 'definite match',
                machine: { ...baseMachine },
            },
            {
                name: 'wrong-option mark',
                machine: {
                    ...baseMachine,
                    selected_option: 'A',
                    observed_options: ['A'],
                    option_observations: [
                        { code: 'A', mean: 218, classification: 'marked' },
                    ],
                },
            },
            {
                name: 'blank',
                machine: {
                    ...baseMachine,
                    state: 'blank',
                    selected_option: null,
                    observed_options: [],
                    option_observations: [],
                },
            },
            {
                name: 'ambiguous A and B',
                machine: {
                    ...baseMachine,
                    state: 'ambiguous',
                    selected_option: null,
                    observed_options: ['A', 'B'],
                    issue_codes: ['multiple_marked'],
                },
            },
            {
                name: 'unreadable',
                machine: {
                    ...baseMachine,
                    state: 'unreadable',
                    selected_option: null,
                    observed_options: ['B'],
                    issue_codes: ['low_confidence'],
                },
            },
        ];
        let originalSheetPath: string | null = null;
        let originalQueuePath: string | null = null;
        let originalSheetImageUrl: string | null = null;
        let originalQueueImageUrl: string | null = null;
        for (const scenario of scenarios) {
            const item = buildReviewTestItem({
                machine: scenario.machine,
            });
            const snapshot = structuredClone(item);
            const view = render(OmrDiagnosticEvidencePanel, {
                page: buildStencilPage([item]),
                initialTab: 'stencil',
            });
            const sheetStencil = view.container.querySelector(
                '[data-omr-sheet-stencil]',
            );
            const queueStencil = view.container.querySelector(
                '[data-omr-bubble-stencil]',
            );
            const sheetPath =
                sheetStencil
                    ?.querySelector('[data-omr-stencil-card]')
                    ?.getAttribute('d') ?? '';
            const queuePath =
                queueStencil
                    ?.querySelector('[data-omr-stencil-card]')
                    ?.getAttribute('d') ?? '';
            const sheetImageUrl =
                view.container
                    .querySelector(
                        '[aria-label="Full-sheet evidence map"] image',
                    )
                    ?.getAttribute('href') ?? null;
            const queueImageUrl =
                view.container
                    .querySelector('[data-omr-crop-status] img')
                    ?.getAttribute('src') ?? null;
            if (originalSheetImageUrl === null)
                originalSheetImageUrl = sheetImageUrl;
            if (originalQueueImageUrl === null)
                originalQueueImageUrl = queueImageUrl;
            expect(sheetImageUrl).toBe(originalSheetImageUrl);
            expect(queueImageUrl).toBe(originalQueueImageUrl);
            if (originalSheetPath === null) originalSheetPath = sheetPath;
            if (originalQueuePath === null) originalQueuePath = queuePath;
            expect(sheetPath).toBe(originalSheetPath);
            expect(queuePath).toBe(originalQueuePath);
            expect(
                sheetStencil?.querySelector('[data-omr-stencil-hole]')?.getAttribute(
                    'data-option',
                ),
            ).toBe('B');
            expect(
                queueStencil?.querySelector('[data-omr-stencil-hole]')?.getAttribute(
                    'data-option',
                ),
            ).toBe('B');
            expect(
                sheetStencil?.querySelector(
                    '.machine-fill, .candidate-hatch, .geometry-ring, .expected-ring',
                ),
            ).toBeNull();
            expect(
                queueStencil?.querySelector(
                    '.machine-fill, .candidate-hatch, .geometry-ring, .expected-ring',
                ),
            ).toBeNull();
            expect(item.machine).toEqual(snapshot.machine);
            expect(item.expected).toBe(snapshot.expected);
            view.unmount();
        }
    });

    it('routes Raw, Stencil and Markings to both evidence surfaces', async () => {
        const onTabChange = vi.fn();
        const item = buildReviewTestItem();
        const view = render(OmrDiagnosticEvidencePanel, {
            page: buildStencilPage([item]),
            initialTab: 'markings',
            onTabChange,
        });
        const sheet = view.container.querySelector(
            '[aria-label="Full-sheet evidence map"] svg[role="img"]',
        )!;

        expect(
            view.getByRole('tab', { name: 'Markings evidence' }).getAttribute(
                'aria-selected',
            ),
        ).toBe('true');
        await fireEvent.click(
            view.getByRole('tab', { name: 'Stencil evidence' }),
        );
        expect(onTabChange).toHaveBeenCalledWith('stencil');
        expect(
            view.getByRole('tab', { name: 'Stencil evidence' }).getAttribute(
                'aria-selected',
            ),
        ).toBe('true');
        expect(
            view.container.querySelector('[data-omr-sheet-stencil]'),
        ).not.toBeNull();
        expect(
            view.container.querySelector('[data-omr-bubble-stencil]'),
        ).not.toBeNull();
        expect(sheet.querySelector('image')?.getAttribute('href')).toBe(
            '/evidence/aligned',
        );

        await fireEvent.click(view.getByRole('tab', { name: 'Raw evidence' }));
        expect(onTabChange).toHaveBeenCalledWith('raw');
        expect(sheet.querySelector('image')?.getAttribute('href')).toBe(
            '/evidence/input',
        );
        expect(
            view.container.querySelector('[data-omr-crop-status] img')?.getAttribute(
                'src',
            ),
        ).toBe('/evidence/input');
        expect(
            view.container.querySelector(
                '[data-omr-sheet-stencil], [data-omr-bubble-stencil], .machine-fill, .candidate-hatch, .geometry-ring, .expected-ring',
            ),
        ).toBeNull();

        await fireEvent.click(
            view.getByRole('tab', { name: 'Markings evidence' }),
        );
        expect(onTabChange).toHaveBeenCalledWith('markings');
        expect(sheet.querySelector('image')?.getAttribute('href')).toBe(
            '/evidence/aligned',
        );
        expect(view.container.querySelector('.machine-fill')).not.toBeNull();
        expect(
            view.container.querySelector(
                '[data-omr-sheet-stencil], [data-omr-bubble-stencil]',
            ),
        ).toBeNull();
    });

    it('does not expose hidden expected answers in blind stencil mode, then reveals only a saved queue item', async () => {
        const firstItem = buildReviewTestItem({ id: 11 });
        const secondItem = buildReviewTestItem({
            id: 12,
            item_id: 'LOGIC_Q_002',
            question_no: 2,
        });
        const onSaveDecision = vi.fn().mockResolvedValue({
            review: {
                revision: 1,
                decision: 'confirm_machine',
                selected_option: 'B',
                evidence_hash: 'b'.repeat(64),
                reviewer: { display_name: 'Staff' },
                reviewed_at: '2026-10-13T10:00:00Z',
                stale: false,
            },
        });
        const view = render(OmrDiagnosticEvidencePanel, {
            page: buildStencilPage([firstItem, secondItem], {
                blind_review: true,
            }),
            reviewable: true,
            onSaveDecision,
            initialTab: 'stencil',
        });

        expect(
            view.getByText('Stencil is unavailable during blind review.'),
        ).not.toBeNull();
        expect(
            view.container.querySelector(
                '[data-omr-sheet-stencil], [data-omr-bubble-stencil]',
            ),
        ).toBeNull();
        expect(view.getByRole('button', { name: /Edit.*LOGIC_Q_001/ })).not.toBeNull();
        await fireEvent.click(
            view.getByRole('button', { name: /Edit.*LOGIC_Q_001/ }),
        );
        await fireEvent.click(
            view.getByRole('button', { name: 'Use detected' }),
        );
        await fireEvent.click(view.getByRole('button', { name: 'Save' }));

        await waitFor(() => {
            expect(onSaveDecision).toHaveBeenCalledTimes(1);
            expect(
                view.container.querySelector(
                    '#omr-evidence-crop-LOGIC_Q_001 [data-omr-bubble-stencil]',
                ),
            ).not.toBeNull();
        });
        expect(
            view.container.querySelector(
                '#omr-evidence-crop-LOGIC_Q_002 [data-omr-bubble-stencil]',
            ),
        ).toBeNull();
        expect(
            view.container.querySelector('[data-omr-sheet-stencil]'),
        ).toBeNull();
    });

    it.each([
        [
            'unkeyed blank row',
            buildReviewTestItem({ expected: null }),
            false,
            true,
        ],
        [
            'blank row during blind review',
            buildReviewTestItem({ expected: null }),
            true,
            false,
        ],
        [
            'unknown expected code',
            buildReviewTestItem({ expected: 'Z' }),
            false,
            false,
        ],
        [
            'missing expected option region',
            buildReviewTestItem({
                options: buildReviewTestItem().options.map((option) =>
                    option.code === 'B' ? { ...option, region: null } : option,
                ),
                geometry: {
                    ...buildReviewTestItem().geometry,
                    options: buildReviewTestItem().geometry.options.map(
                        (option) =>
                            option.code === 'B'
                                ? { ...option, region: null }
                                : option,
                    ),
                },
            }),
            false,
            false,
        ],
    ])(
        'renders only a solid blank stencil or fails closed for %s',
        (_case, item, blindReview, expectBlankStencil) => {
            const view = render(OmrDiagnosticEvidencePanel, {
                page: buildStencilPage(
                    [item],
                    blindReview ? { blind_review: true } : {},
                ),
                initialTab: 'stencil',
            });
            if (expectBlankStencil) {
                const sheetStencil = view.container.querySelector(
                    '[data-omr-sheet-stencil]',
                );
                const sheetCard = sheetStencil?.querySelector(
                    '[data-omr-stencil-card]',
                );
                expect(sheetCard).not.toBeNull();
                expect(sheetCard?.getAttribute('d')).not.toContain(' a ');
                expect(
                    sheetStencil?.querySelector('[data-omr-stencil-hole]'),
                ).toBeNull();

                const cropStencil = view.container.querySelector(
                    '#omr-evidence-crop-LOGIC_Q_001 [data-omr-bubble-stencil]',
                );
                expect(cropStencil).not.toBeNull();
                expect(
                    cropStencil
                        ?.querySelector('[data-omr-stencil-card]')
                        ?.getAttribute('d'),
                ).toBe('M 0 0 H 1 V 1 H 0 Z');
                expect(
                    cropStencil?.querySelector('[data-omr-stencil-hole]'),
                ).toBeNull();
                expect(view.container.textContent).toContain('Leave blank');
            } else {
                expect(
                    view.container.querySelector(
                        '[data-omr-stencil-card], [data-omr-stencil-hole]',
                    ),
                ).toBeNull();
                expect(
                    view.container.querySelector(
                        '[data-omr-sheet-stencil]',
                    ),
                ).toBeNull();
                expect(
                    view.container.querySelector(
                        '[data-omr-bubble-stencil]',
                    ),
                ).toBeNull();
                if (blindReview) {
                    expect(view.container.textContent).toContain(
                        'Stencil is unavailable during blind review.',
                    );
                    expect(view.container.textContent).toContain(
                        'Leave blank',
                    );
                } else {
                    expect(view.container.textContent).toContain(
                        'Stencil unavailable; expected geometry is hidden or missing.',
                    );
                }
            }
            if (_case === 'missing expected option region') {
                expect(
                    view.container
                        .querySelector('[data-omr-crop-status]')
                        ?.getAttribute('data-omr-crop-status'),
                ).toBe('unavailable');
            }
            expect(
                view.container
                    .querySelector('[aria-label="Full-sheet evidence map"] image')
                    ?.getAttribute('href'),
            ).toBe('/evidence/aligned');
            expect(
                view.getByRole('button', { name: 'Inspect LOGIC_Q_001' }),
            ).not.toBeNull();
            expect(
                view.getAllByRole('img', { name: /stencil/i }).length,
            ).toBeGreaterThanOrEqual(1);
        },
    );

    it('keeps separate panel instances and their expected holes independent', async () => {
        const firstItem = buildReviewTestItem({ id: 31 });
        const secondItem = buildReviewTestItem({
            id: 32,
            expected: 'B',
        });
        const firstView = render(OmrDiagnosticEvidencePanel, {
            page: buildStencilPage([firstItem]),
            initialTab: 'stencil',
            idPrefix: 'first-stencil',
        });
        const secondView = render(OmrDiagnosticEvidencePanel, {
            page: buildStencilPage([secondItem]),
            initialTab: 'stencil',
            idPrefix: 'second-stencil',
        });
        const firstPath =
            firstView.container
                .querySelector('[data-omr-stencil-card]')
                ?.getAttribute('d') ?? '';

        expect(
            firstView.container.querySelectorAll('[data-omr-stencil-card]'),
        ).toHaveLength(2);
        expect(
            secondView.container.querySelectorAll('[data-omr-stencil-card]'),
        ).toHaveLength(2);
        expect(
            document.querySelectorAll('[data-omr-stencil-card]'),
        ).toHaveLength(4);
        expect(
            firstView.container.querySelectorAll('[data-omr-stencil-hole]'),
        ).toHaveLength(2);
        expect(
            secondView.container.querySelectorAll('[data-omr-stencil-hole]'),
        ).toHaveLength(2);
        expect(
            firstView.container
                .querySelector('[data-omr-stencil-hole]')
                ?.getAttribute('data-option'),
        ).toBe('B');
        expect(
            secondView.container
                .querySelector('[data-omr-stencil-hole]')
                ?.getAttribute('data-option'),
        ).toBe('B');

        const secondC = buildReviewTestItem({
            id: 32,
            expected: 'C',
        });
        await secondView.rerender({
            page: buildStencilPage([secondC]),
            initialTab: 'stencil',
            idPrefix: 'second-stencil',
        });
        expect(
            firstView.container
                .querySelector('[data-omr-stencil-card]')
                ?.getAttribute('d'),
        ).toBe(firstPath);
        expect(
            secondView.container
                .querySelector('[data-omr-stencil-hole]')
                ?.getAttribute('data-option'),
        ).toBe('C');
    });

    it('uses dedicated-crop transformed geometry and variable option tokens', () => {
        const options = [
            {
                code: 'A-B',
                region: { x: 0.2, y: 0.2, w: 0.1, h: 0.1 },
                mean: 210,
            },
            {
                code: 'A-C',
                region: { x: 0.2, y: 0.35, w: 0.1, h: 0.1 },
                mean: 50,
            },
        ];
        const item = buildReviewTestItem({
            id: 44,
            item_id: 'LOGIC_PAIR_001',
            options,
            expected: 'A-B',
            detail_url: '/evidence/dedicated-crop',
            crop_rect: { x: 0.1, y: 0.15, w: 0.5, h: 0.4 },
            machine: {
                ...buildReviewTestItem().machine,
                state: 'marked',
                selected_option: 'A-C',
                observed_options: ['A-C'],
                option_observations: [],
            },
            geometry: {
                ...buildReviewTestItem().geometry,
                item_rect: { x: 0.1, y: 0.15, w: 0.5, h: 0.4 },
                crop_rect: { x: 0.1, y: 0.15, w: 0.5, h: 0.4 },
                options,
                crop_source: {
                    kind: 'dedicated_crop',
                    source_rect: { x: 0.1, y: 0.15, w: 0.5, h: 0.4 },
                    transform: {
                        valid: true,
                        matrix: [1, 0, 0, 1, 0, 0],
                    },
                },
            },
            provenance: {
                ...buildReviewTestItem().provenance,
                crop_source: {
                    kind: 'dedicated_crop',
                    source_rect: { x: 0.1, y: 0.15, w: 0.5, h: 0.4 },
                    transform: {
                        valid: true,
                        matrix: [1, 0, 0, 1, 0, 0],
                    },
                },
            },
        });
        const view = render(OmrDiagnosticEvidencePanel, {
            page: buildStencilPage([item]),
            initialTab: 'stencil',
        });
        const crop = view.container.querySelector(
            '#omr-evidence-crop-LOGIC_PAIR_001',
        );
        const overlay = crop?.querySelector('[data-omr-bubble-stencil]');
        const hole = overlay?.querySelector('[data-omr-stencil-hole]');

        expect(crop?.querySelector('img')?.getAttribute('src')).toBe(
            '/evidence/dedicated-crop',
        );
        expect(hole?.getAttribute('data-option')).toBe('A-B');
        expect(Number(hole?.getAttribute('cx'))).toBeCloseTo(0.3, 5);
        expect(Number(hole?.getAttribute('cy'))).toBeCloseTo(0.25, 5);
        expect(Number(hole?.getAttribute('rx'))).toBeCloseTo(0.118, 5);
        expect(Number(hole?.getAttribute('ry'))).toBeCloseTo(0.1475, 5);
    });

    it('preserves sheet item click and keyboard navigation after stencil cards', async () => {
        const onViewChange = vi.fn();
        const item = buildReviewTestItem();
        const view = render(OmrDiagnosticEvidencePanel, {
            page: buildStencilPage([item]),
            initialTab: 'stencil',
            onViewChange,
        });
        const sheetItem = view.getByRole('button', {
            name: 'Inspect LOGIC_Q_001',
        });
        const group = view.container.querySelector(
            '[data-sheet-item="1"]',
        )!;
        await fireEvent.click(sheetItem);
        await waitFor(() =>
            expect(group.querySelector('.selected-item')).not.toBeNull(),
        );
        const card = group.querySelector('[data-omr-stencil-card]')!;
        const selectedOutline = group.querySelector('.selected-item')!;
        expect(
            card.compareDocumentPosition(selectedOutline) &
                Node.DOCUMENT_POSITION_FOLLOWING,
        ).not.toBe(0);

        await fireEvent.keyDown(sheetItem, { key: 'Enter' });
        await fireEvent.keyDown(sheetItem, { key: ' ' });
        expect(onViewChange).toHaveBeenCalledTimes(3);
        expect(onViewChange).toHaveBeenLastCalledWith({
            area: 'LOGIC',
            filter: 'all',
            page: 1,
        });
    });
});

describe('OmrDiagnosticEvidencePanel item review contract (OpenSpec 7.1-7.6)', () => {
    it('supports four decision controls, retains local drafts, and avoids premature reviewed claims', async () => {
        const onSaveDecision = vi.fn();
        const onDirtyChange = vi.fn();
        const page = buildReviewTestPage();
        const view = render(OmrDiagnosticEvidencePanel, {
            page,
            reviewable: true,
            reviewReasons: testReasons,
            onSaveDecision,
            onDirtyChange,
        });

        await fireEvent.click(
            view.getByRole('button', { name: /LOGIC_Q_001/ }),
        );
        expect(
            view.getByRole('region', { name: 'LOGIC_Q_001 details' }),
        ).not.toBeNull();

        const confirmBtn = view.getByRole('button', {
            name: 'Use detected answer',
        });
        const setOptionBtn = view.getByRole('button', { name: 'Choose answer' });
        const blankBtn = view.getByRole('button', { name: 'Mark blank' });
        const unresolvedBtn = view.getByRole('button', { name: 'Leave unresolved' });

        expect(confirmBtn).not.toBeNull();
        expect(setOptionBtn).not.toBeNull();
        expect(blankBtn).not.toBeNull();
        expect(unresolvedBtn).not.toBeNull();

        // 1. Confirm machine
        await fireEvent.click(confirmBtn);
        expect(confirmBtn.getAttribute('aria-pressed')).toBe('true');
        expect(onDirtyChange).toHaveBeenCalledWith(true);
        expect(view.getByText('Draft')).not.toBeNull();
        expect(view.container.querySelector('.review-badge')).toBeNull();

        // 2. Set option
        await fireEvent.click(setOptionBtn);
        expect(setOptionBtn.getAttribute('aria-pressed')).toBe('true');
        expect(
            view.getByRole('radiogroup', { name: 'Pinned options' }),
        ).not.toBeNull();
        const radioC = view.container.querySelector(
            'input[type="radio"][value="C"]',
        ) as HTMLInputElement;
        expect(radioC).not.toBeNull();
        await fireEvent.click(radioC);
        expect(radioC.checked).toBe(true);

        // 3. Blank
        await fireEvent.click(blankBtn);
        expect(blankBtn.getAttribute('aria-pressed')).toBe('true');
        expect(
            view.queryByRole('radiogroup', { name: 'Pinned options' }),
        ).toBeNull();

        // 4. Unresolved
        await fireEvent.click(unresolvedBtn);
        expect(unresolvedBtn.getAttribute('aria-pressed')).toBe('true');

        // Local draft is preserved; no server save triggered yet
        expect(onSaveDecision).not.toHaveBeenCalled();
        expect(view.container.querySelector('.review-badge')).toBeNull();
    });

    it('validates structured reason and note requirements before explicit save', async () => {
        const onSaveDecision = vi.fn();
        const page = buildReviewTestPage();
        const view = render(OmrDiagnosticEvidencePanel, {
            page,
            reviewable: true,
            reviewReasons: testReasons,
            onSaveDecision,
        });

        await fireEvent.click(
            view.getByRole('button', { name: /LOGIC_Q_001/ }),
        );
        await fireEvent.click(view.getByRole('button', { name: 'Unresolved' }));

        // Attempt save without required reason
        const saveBtn = view.getByRole('button', { name: 'Save' });
        await fireEvent.click(saveBtn);
        expect(onSaveDecision).not.toHaveBeenCalled();
        const alerts = view.getAllByRole('alert');
        expect(alerts.some((a) => a.textContent?.includes('Choose a structured reason before saving.'))).toBe(true);

        // Select structured reason
        const reasonSelect = view.container.querySelector(
            'select[id*="-reason-"]',
        ) as HTMLSelectElement;
        expect(reasonSelect).not.toBeNull();
        await fireEvent.change(reasonSelect, {
            target: { value: 'poor_erasure' },
        });

        // Attempt save without required note
        await fireEvent.click(saveBtn);
        expect(onSaveDecision).not.toHaveBeenCalled();
        expect(view.getByRole('alert').textContent).toContain(
            'Add a short note explaining this review.',
        );

        // Enter note within character limit
        const textarea = view.container.querySelector(
            'textarea[id*="-note-"]',
        ) as HTMLTextAreaElement;
        expect(textarea).not.toBeNull();
        await fireEvent.input(textarea, {
            target: { value: 'Candidate erased mark and left area unclear' },
        });

        // Save with valid payload
        onSaveDecision.mockResolvedValueOnce({
            review: {
                revision: 1,
                decision: 'unresolved',
                selected_option: null,
                reason_code: 'poor_erasure',
                note: 'Candidate erased mark and left area unclear',
                reviewer: { display_name: 'Lead Reviewer' },
                reviewed_at: '2026-09-05T10:00:00Z',
                evidence_hash: 'b'.repeat(64),
                stale: false,
            },
        });
        await fireEvent.click(saveBtn);
        expect(onSaveDecision).toHaveBeenCalledTimes(1);
        const payload = onSaveDecision.mock.calls[0][0];
        expect(payload.decision).toBe('unresolved');
        expect(payload.reason_code).toBe('poor_erasure');
        expect(payload.note).toBe('Candidate erased mark and left area unclear');
        expect(payload.reviewed_machine_evidence_hash).toBe('b'.repeat(64));
        expect(payload.expected_parent_version).toBe(3);
        expect(payload.expected_item_revision).toBe(0);
    });
    it('handles saving state, duplicate click suppression, and failure recovery', async () => {
        const resolvers = Promise.withResolvers<unknown>();
        const onSaveDecision = vi.fn().mockReturnValue(resolvers.promise);
        const page = buildReviewTestPage();
        const view = render(OmrDiagnosticEvidencePanel, {
            page,
            reviewable: true,
            reviewReasons: testReasons,
            onSaveDecision,
        });

        await fireEvent.click(
            view.getByRole('button', { name: /LOGIC_Q_001/ }),
        );
        await fireEvent.click(
            view.getByRole('button', { name: 'Use detected answer' }),
        );

        const saveBtn = view.getByRole('button', { name: 'Save' });
        await fireEvent.click(saveBtn);
        expect(onSaveDecision).toHaveBeenCalledTimes(1);

        // While saving, button displays Saving... and is disabled
        expect(saveBtn.textContent).toContain('Saving…');
        expect((saveBtn as HTMLButtonElement).disabled).toBe(true);

        // Rapid second click does not trigger another call (debounced duplicate suppression)
        await fireEvent.click(saveBtn);
        expect(onSaveDecision).toHaveBeenCalledTimes(1);

        resolvers.reject({
            status: 500,
            detail: 'Persistence transaction failed.',
        });
        await waitFor(() => {
            const alert = view.getByRole('alert');
            expect(alert.textContent).toContain(
                'Persistence transaction failed.',
            );
        });
        expect(view.container.querySelector('.review-badge')).toBeNull();

        // Save button is re-enabled for retry
        const retrySaveBtn = view.getByRole('button', { name: 'Save' });
        expect((retrySaveBtn as HTMLButtonElement).disabled).toBe(false);

        // Retry succeeds
        onSaveDecision.mockResolvedValueOnce({
            review: {
                revision: 1,
                decision: 'confirm_machine',
                selected_option: 'B',
                reviewer: { display_name: 'Lead Reviewer' },
                reviewed_at: '2026-09-05T10:00:00Z',
                evidence_hash: 'b'.repeat(64),
                stale: false,
            },
        });
        await fireEvent.click(retrySaveBtn);
        expect(onSaveDecision).toHaveBeenCalledTimes(2);
        await waitFor(() => {
            expect(view.container.querySelector('.review-badge')).not.toBeNull();
        });
    });

    it('discloses current and historical reviews and protects against stale review saves', async () => {
        const itemHash = 'b'.repeat(64);
        const onSaveDecision = vi.fn();
        const reviewedItem = buildReviewTestItem({
            review: {
                revision: 2,
                decision: 'set_option',
                selected_option: 'C',
                reason_code: 'poor_erasure',
                note: 'Confirmed choice C after erase',
                reviewer: { id: 8, display_name: 'Counselor Ramos' },
                reviewed_at: '2026-09-05 11:00:00',
                evidence_hash: itemHash,
                stale: false,
            },
            review_history: [
                {
                    revision: 1,
                    decision: 'confirm_machine',
                    selected_option: 'B',
                    reviewer: { id: 2, display_name: 'Clerk Santos' },
                    reviewed_at: '2026-09-05 09:00:00',
                    evidence_hash: itemHash,
                    stale: false,
                },
            ],
        });
        const page = buildReviewTestPage({
            items: [reviewedItem],
        });
        const view = render(OmrDiagnosticEvidencePanel, {
            page,
            reviewable: true,
            reviewReasons: testReasons,
            onSaveDecision,
        });

        // Reviewed badge is visible
        expect(view.container.querySelector('.review-badge')).not.toBeNull();
        await fireEvent.click(
            view.getByRole('button', { name: /LOGIC_Q_001/ }),
        );

        // Current review section discloses details
        const currentSection = view.getByLabelText('Current review');
        expect(currentSection.textContent).toContain('Counselor Ramos');
        expect(currentSection.textContent).toContain('revision 2');
        expect(currentSection.textContent).toContain('Choose answer');
        expect(currentSection.textContent).toContain('Reason poor_erasure');

        // Review history disclosure exists
        const historyDetails = view.container.querySelector(
            'details',
        ) as HTMLDetailsElement;
        expect(historyDetails).not.toBeNull();
        expect(historyDetails.textContent).toContain('Clerk Santos');

        // Stale review: machine evidence hash drifted
        const staleItem = buildReviewTestItem({
            id: 1,
            machine: {
                ...reviewedItem.machine,
                evidence_hash: 'new_machine_hash'.padEnd(64, '0'),
            },
            review: {
                ...reviewedItem.review,
                evidence_hash: 'old_machine_hash'.padEnd(64, '0'),
            },
        });
        await view.rerender({
            page: buildReviewTestPage({ items: [staleItem] }),
            reviewable: true,
            reviewReasons: testReasons,
            onSaveDecision,
        });

        expect(view.getByText('Stale history')).not.toBeNull();
        await fireEvent.click(
            view.getByRole('button', { name: 'Use detected answer' }),
        );
        await fireEvent.click(view.getByRole('button', { name: 'Save' }));
        expect(onSaveDecision).not.toHaveBeenCalled();
        expect(view.getByRole('alert').textContent).toContain(
            'This review is stale against a newer machine evidence version.',
        );
    });

    it('isolates expected truth in blind review mode until server confirms save', async () => {
        const onSaveDecision = vi.fn();
        const page = buildReviewTestPage({
            blind_review: true,
        });
        const view = render(OmrDiagnosticEvidencePanel, {
            page,
            reviewable: true,
            blind: true,
            reviewReasons: testReasons,
            onSaveDecision,
        });

        // Header renders blind badge and hidden comparison banner
        expect(view.getByText('Blind')).not.toBeNull();
        expect(view.container.textContent).toContain(
            'comparison hidden until a decision is saved',
        );

        await fireEvent.click(
            view.getByRole('button', { name: /LOGIC_Q_001/ }),
        );
        expect(view.container.textContent).toContain('Hidden until saved');

        await fireEvent.click(
            view.getByRole('button', { name: 'Use detected answer' }),
        );
        onSaveDecision.mockResolvedValueOnce({
            review: {
                revision: 1,
                decision: 'confirm_machine',
                selected_option: 'B',
                reviewer: { display_name: 'Lead Reviewer' },
                reviewed_at: '2026-09-05 10:00:00',
                evidence_hash: 'b'.repeat(64),
                stale: false,
            },
        });
        await fireEvent.click(view.getByRole('button', { name: 'Save' }));
        expect(onSaveDecision).toHaveBeenCalledTimes(1);

        await waitFor(() => {
            expect(view.container.querySelector('.review-badge')).not.toBeNull();
            expect(view.getByRole('button', { name: /LOGIC_Q_001/ }).textContent).not.toContain('Hidden until saved');
        });
    });

    it('supports save-and-next flow automatically navigating to next review candidate', async () => {
        const onSaveAndNext = vi.fn();
        const onRowOpen = vi.fn();
        const page = buildReviewTestPage();
        const view = render(OmrDiagnosticEvidencePanel, {
            page,
            reviewable: true,
            reviewReasons: testReasons,
            onSaveAndNext,
            onRowOpen,
        });

        await fireEvent.click(
            view.getByRole('button', { name: /LOGIC_Q_001/ }),
        );
        await fireEvent.click(
            view.getByRole('button', { name: 'Use detected answer' }),
        );

        const saveAndNextBtn = view.getByRole('button', {
            name: /Save & next/,
        });
        expect(saveAndNextBtn).not.toBeNull();

        onSaveAndNext.mockResolvedValueOnce({
            review: {
                revision: 1,
                decision: 'confirm_machine',
                selected_option: 'B',
                reviewer: { display_name: 'Staff' },
                reviewed_at: '2026-09-05',
                evidence_hash: 'b'.repeat(64),
                stale: false,
            },
        });

        await fireEvent.click(saveAndNextBtn);
        expect(onSaveAndNext).toHaveBeenCalledTimes(1);

        // Automatically opens next problem item (LOGIC_Q_002)
        await waitFor(() => {
            expect(onRowOpen).toHaveBeenCalledWith(
                expect.objectContaining({ item_id: 'LOGIC_Q_002' }),
            );
        });
    });

    it('protects unsaved dirty changes during area, filter, and pagination navigation', async () => {
        const callbacks = {
            onGroupChange: vi.fn(),
            onFilterChange: vi.fn(),
            onPageChange: vi.fn(),
            onDirtyChange: vi.fn(),
        };
        const page = buildReviewTestPage();
        const view = render(OmrDiagnosticEvidencePanel, {
            page,
            reviewable: true,
            reviewReasons: testReasons,
            onSaveDecision: vi.fn(),
            ...callbacks,
        });

        // Create dirty draft
        await fireEvent.click(
            view.getByRole('button', { name: /LOGIC_Q_001/ }),
        );
        await fireEvent.click(view.getByRole('button', { name: 'Mark blank' }));
        expect(callbacks.onDirtyChange).toHaveBeenCalledWith(true);

        // Attempt area navigation
        await fireEvent.click(
            view.getByRole('button', { name: /Logic: 2 items/ }),
        );
        expect(callbacks.onGroupChange).not.toHaveBeenCalled();

        // Accessible alertdialog prompt appears
        const alertDialog = view.getByRole('alertdialog');
        expect(alertDialog.textContent).toContain('Unsaved review changes');

        // Click Continue editing -> cancels navigation, retains draft
        await fireEvent.click(
            view.getByRole('button', { name: 'Continue editing' }),
        );
        expect(view.queryByRole('alertdialog')).toBeNull();
        expect(callbacks.onGroupChange).not.toHaveBeenCalled();

        // Attempt area navigation again and click Discard changes
        await fireEvent.click(
            view.getByRole('button', { name: /Logic: 2 items/ }),
        );
        expect(view.getByRole('alertdialog')).not.toBeNull();
        await fireEvent.click(
            view.getByRole('button', { name: 'Discard changes' }),
        );

        expect(callbacks.onGroupChange).toHaveBeenCalledWith('LOGIC');
        expect(callbacks.onDirtyChange).toHaveBeenCalledWith(false);
    });

    it('fails closed and blocks review save when asset or geometry provenance is unavailable', async () => {
        const onSaveDecision = vi.fn();
        const brokenItem = buildReviewTestItem({
            unavailable_reason: 'missing-geometry-hash',
            geometry: {
                ...buildReviewTestItem().geometry,
                geometry_manifest_hash: null,
            },
        });
        const page = buildReviewTestPage({ items: [brokenItem] });
        const view = render(OmrDiagnosticEvidencePanel, {
            page,
            reviewable: true,
            reviewReasons: testReasons,
            onSaveDecision,
        });

        // Unavailable badge is visible
        expect(view.getByText('Unavailable')).not.toBeNull();

        await fireEvent.click(
            view.getByRole('button', { name: /LOGIC_Q_001/ }),
        );
        const unavailableAlert = view.getByRole('alert');
        expect(unavailableAlert.textContent).toContain(
            'Answer-sheet geometry signature is unavailable',
        );
        expect(unavailableAlert.textContent).toContain('missing-geometry-hash');

        // Attempting to save fails closed
        await fireEvent.click(
            view.getByRole('button', { name: 'Use detected answer' }),
        );
        await fireEvent.click(view.getByRole('button', { name: 'Save' }));

        expect(onSaveDecision).not.toHaveBeenCalled();
        const alerts = view.getAllByRole('alert');
        expect(alerts.some((a) => a.textContent?.includes('Review save is blocked'))).toBe(true);
    });
    it('supports whole examinee ID correction, per-digit evidence, and image-only configured fields', async () => {
        const digitOptions = Array.from({ length: 10 }, (_, digit) => ({
            code: String(digit),
            region: {
                x: 0.7,
                y: 0.08 + digit * 0.02,
                w: 0.02,
                h: 0.015,
            },
        }));
        const identityItems = ['1', '2', '3'].map((digit, index) =>
            buildReviewTestItem({
                id: 101 + index,
                item_id: `APPLICANT_NUMBER_${index + 1}`,
                reviewable: true,
                group_type: 'identity',
                area_key: null,
                question_no: index + 1,
                expected: digit,
                detected: digit,
                state: 'match',
                options: digitOptions,
                machine: {
                    state: 'marked',
                    selected_option: digit,
                    confidence: 0.96,
                    observed_options: [digit],
                    option_observations: [],
                    issue_codes: [],
                    evidence_hash: String(index + 1).repeat(64),
                },
                geometry: {
                    item_rect: { x: 0.7 + index * 0.025, y: 0.08, w: 0.02, h: 0.195 },
                    crop_rect: { x: 0.7 + index * 0.025, y: 0.08, w: 0.02, h: 0.195 },
                    options: digitOptions.map((option) => ({
                        ...option,
                        region: { ...option.region, x: 0.7 + index * 0.025 },
                    })),
                    aligned_asset: {
                        url: '/evidence/aligned',
                        sha256: 'a'.repeat(64),
                        width: 1200,
                        height: 1600,
                        page_no: 1,
                    },
                    geometry_manifest_hash: 'g'.repeat(64),
                    evidence_version: 'v1',
                    page_no: 1,
                },
                crop_rect: { x: 0.7 + index * 0.025, y: 0.08, w: 0.02, h: 0.195 },
            }),
        );
        const onSaveDecision = vi.fn().mockResolvedValue({
            review: { revision: 1, decision: 'set_option' },
        });
        const view = render(OmrDiagnosticEvidencePanel, {
            page: buildReviewTestPage({
                area: 'identity',
                total: 3,
                mismatch_total: 0,
                page_count: 1,
                groups: [
                    { key: 'all', label: 'All areas', total: 3, mismatch_total: 0, page_count: 1 },
                    { key: 'identity', label: 'Identity', group_type: 'identity', total: 3, mismatch_total: 0, page_count: 1 },
                ],
                items: identityItems,
                sheet_map: {
                    page_no: 1,
                    width: 1200,
                    height: 1600,
                    sha256: 'a'.repeat(64),
                    items: identityItems,
                    document_fields: [
                        {
                            id: 'candidate-name',
                            type: 'candidate_name',
                            label: 'Full name',
                            region: { x: 0.08, y: 0.1, w: 0.4, h: 0.06 },
                            transcription: false,
                        },
                    ],
                },
            }),
            reviewable: true,
            reviewReasons: [
                ...testReasons,
                { code: 'incorrect_detection', label: 'Detected answer is incorrect' },
            ],
            onSaveDecision,
        });

        expect(view.getByText('Machine read').parentElement?.textContent).toContain('123');
        expect(view.getByText('Image evidence only. These fields are not transcribed.')).not.toBeNull();
        expect(view.queryByText('Calibration')).toBeNull();
        expect(view.getAllByRole('button', { name: /APPLICANT_NUMBER_1/ }).length).toBeGreaterThan(0);

        await fireEvent.input(view.getByLabelText('Correct examinee ID'), {
            target: { value: '124' },
        });
        await fireEvent.click(view.getByRole('button', { name: 'Save corrected ID' }));

        await waitFor(() => expect(onSaveDecision).toHaveBeenCalledTimes(1));
        expect(onSaveDecision.mock.calls[0][0]).toMatchObject({
            decision: 'set_option',
            selected_option: '4',
            reason_code: 'incorrect_detection',
        });
        expect(onSaveDecision.mock.calls[0][1].item_id).toBe('APPLICANT_NUMBER_3');
    });

    it('navigates the right-hand rail to the clicked preview item area and page', async () => {
        const onViewChange = vi.fn();
        const onSaveDecision = vi.fn();
        const mappedItem = buildReviewTestItem({
            id: 99,
            item_id: 'LOGIC_Q_026',
            area_key: 'LOGIC',
            question_no: 26,
        });
        const sheetItems = [
            ...Array.from({ length: 25 }, (_, index) =>
                buildReviewTestItem({
                    id: 100 + index,
                    item_id: `LOGIC_Q_${String(index + 1).padStart(3, '0')}`,
                    area_key: 'LOGIC',
                    question_no: index + 1,
                }),
            ),
            mappedItem,
        ];
        const page = buildReviewTestPage({
            total: 50,
            items: [buildReviewTestItem()],
            sheet_map: {
                page_no: 1,
                width: 1200,
                height: 1600,
                sha256: 'a'.repeat(64),
                items: sheetItems,
                document_fields: [],
            },
        });
        const props = {
            page,
            reviewable: true,
            reviewReasons: testReasons,
            onSaveDecision,
            onViewChange,
        };
        const view = render(OmrDiagnosticEvidencePanel, props);

        await fireEvent.click(
            view.getByRole('button', { name: 'Inspect LOGIC_Q_026' }),
        );
        expect(onViewChange).toHaveBeenCalledWith({
            area: 'LOGIC',
            filter: 'all',
            page: 2,
        });

        await view.rerender({
            ...props,
            page: { ...page, area: 'LOGIC', page: 2, items: [mappedItem] },
        });
        await waitFor(() =>
            expect(
                view.getByRole('region', { name: 'Edit LOGIC · Q26' }),
            ).not.toBeNull(),
        );

        await fireEvent.click(
            view.getByRole('button', { name: 'Inspect LOGIC_Q_026' }),
        );
        expect(onViewChange).toHaveBeenCalledTimes(2);
        expect(onViewChange).toHaveBeenLastCalledWith({
            area: 'LOGIC',
            filter: 'all',
            page: 2,
        });

        await fireEvent.click(
            view.getByRole('button', { name: 'Use detected' }),
        );
        await fireEvent.click(
            view.getByRole('button', { name: 'Inspect LOGIC_Q_026' }),
        );
        expect(view.getByRole('alertdialog').textContent).toContain(
            'Unsaved review changes',
        );
        await fireEvent.click(
            view.getByRole('button', { name: 'Continue editing' }),
        );
        expect(onViewChange).toHaveBeenCalledTimes(2);
    });

    it('keeps drafts after review conflicts and requires server and row eligibility', async () => {
        const item = buildReviewTestItem({
            parent_version: 12,
            item_revision: 7,
        });
        const page = buildReviewTestPage({ items: [item], parent_version: 12 });
        const onSaveDecision = vi.fn().mockRejectedValue({
            status: 409,
            detail: 'The evidence changed. Reload before saving.',
        });
        const view = render(OmrDiagnosticEvidencePanel, {
            page,
            reviewable: true,
            reviewReasons: testReasons,
            onSaveDecision,
        });
        await fireEvent.click(view.getByRole('button', { name: /LOGIC_Q_001/ }));
        const detectedButton = view.getByRole('button', {
            name: 'Use detected',
        });
        await fireEvent.click(detectedButton);
        await fireEvent.click(view.getByRole('button', { name: 'Save' }));
        await waitFor(() =>
            expect(view.getByRole('alert').textContent).toContain(
                'The evidence changed. Reload before saving.',
            ),
        );
        expect(detectedButton.getAttribute('aria-pressed')).toBe('true');
        expect(onSaveDecision.mock.calls[0][0]).toMatchObject({
            expected_parent_version: 12,
            expected_item_revision: 7,
        });

        await view.rerender({
            page: buildReviewTestPage({
                reviewable: false,
                items: [item],
                parent_version: 12,
            }),
            reviewable: true,
            reviewReasons: testReasons,
            onSaveDecision,
        });
        expect(
            view.queryByRole('button', { name: 'Edit LOGIC · Q1 LOGIC_Q_001' }),
        ).toBeNull();
        expect(
            view.queryByRole('button', { name: 'Use detected' }),
        ).toBeNull();
        await view.rerender({
            page: buildReviewTestPage({
                reviewable: true,
                items: [{ ...item, reviewable: false }],
                parent_version: 12,
            }),
            reviewable: true,
            reviewReasons: testReasons,
            onSaveDecision,
        });
        expect(
            view.queryByRole('button', { name: /Edit.*LOGIC_Q_001/ }),
        ).toBeNull();
        expect(
            view.queryByRole('button', { name: 'Use detected' }),
        ).toBeNull();
});

});


describe('OmrDiagnosticEvidenceModal presentation contract', () => {
    it('traps focus, supports Escape, restores focus, and exposes responsive dialog semantics', async () => {
        const offsetParentDescriptor = Object.getOwnPropertyDescriptor(
            HTMLElement.prototype,
            'offsetParent',
        );
        Object.defineProperty(HTMLElement.prototype, 'offsetParent', {
            configurable: true,
            get: () => document.body,
        });
        const opener = document.createElement('button');
        opener.type = 'button';
        opener.textContent = 'Open evidence';
        document.body.append(opener);
        opener.focus();
        const onClose = vi.fn();
        const { getByRole, getAllByRole } = render(OmrDiagnosticEvidenceModal, {
            open: true,
            page: componentEvidencePage,
            onClose,
            onPageChange: vi.fn(),
            title: 'Pre-shaded evidence',
            subtitle: 'Private evidence',
        });

        const dialog = getByRole('dialog');
        expect(dialog.getAttribute('aria-modal')).toBe('true');
        expect(dialog.parentElement?.parentElement).toBe(document.body);
        await waitFor(() =>
            expect(document.activeElement).toBe(
                getByRole('button', { name: 'Close evidence' }),
            ),
        );

        const first = getByRole('button', { name: 'Close evidence' });
        const last = getAllByRole('button').at(-1)!;
        last.focus();
        await fireEvent.keyDown(window, { key: 'Tab' });
        expect(document.activeElement).toBe(first);
        first.focus();
        await fireEvent.keyDown(window, { key: 'Tab', shiftKey: true });
        expect(document.activeElement).toBe(last);

        await fireEvent.keyDown(window, { key: 'Escape' });
        expect(onClose).toHaveBeenCalledTimes(1);
        await waitFor(() => expect(document.activeElement).toBe(opener));
        if (offsetParentDescriptor) {
            Object.defineProperty(
                HTMLElement.prototype,
                'offsetParent',
                offsetParentDescriptor,
            );
        } else {
            delete (HTMLElement.prototype as { offsetParent?: unknown })
                .offsetParent;
        }
        opener.remove();
    });

    it('protects against dirty close via onDirtyNavigation callback and window.confirm fallback', async () => {
        const onClose = vi.fn();
        const onDirtyNavigation = vi.fn().mockReturnValue(false);
        const page = buildReviewTestPage();
        const view = render(OmrDiagnosticEvidenceModal, {
            open: true,
            page,
            reviewable: true,
            reviewReasons: testReasons,
            onClose,
            onDirtyNavigation,
            onSaveDecision: vi.fn(),
        });

        await fireEvent.click(
            view.getByRole('button', { name: /LOGIC_Q_001/ }),
        );
        await fireEvent.click(
            view.getByRole('button', { name: 'Use detected answer' }),
        );

        await fireEvent.click(
            view.getByRole('button', { name: 'Close evidence' }),
        );
        expect(onDirtyNavigation).toHaveBeenCalledWith(
            expect.objectContaining({ kind: 'close' }),
        );
        expect(onClose).not.toHaveBeenCalled();

        onDirtyNavigation.mockReturnValue(true);
        await fireEvent.click(
            view.getByRole('button', { name: 'Close evidence' }),
        );
        expect(onClose).toHaveBeenCalledTimes(1);
    });

    it('falls back to window.confirm for dirty modal close when onDirtyNavigation is absent', async () => {
        const confirmSpy = vi.spyOn(window, 'confirm').mockReturnValue(false);
        const onClose = vi.fn();
        const page = buildReviewTestPage();
        const view = render(OmrDiagnosticEvidenceModal, {
            open: true,
            page,
            reviewable: true,
            reviewReasons: testReasons,
            onClose,
            onSaveDecision: vi.fn(),
        });

        await fireEvent.click(
            view.getByRole('button', { name: /LOGIC_Q_001/ }),
        );
        await fireEvent.click(view.getByRole('button', { name: 'Mark blank' }));

        await fireEvent.click(
            view.getByRole('button', { name: 'Close evidence' }),
        );
        expect(confirmSpy).toHaveBeenCalledWith(
            'Discard the unsaved item review decision?',
        );
        expect(onClose).not.toHaveBeenCalled();

        confirmSpy.mockReturnValue(true);
        await fireEvent.click(
            view.getByRole('button', { name: 'Close evidence' }),
        );
        expect(onClose).toHaveBeenCalledTimes(1);
        confirmSpy.mockRestore();
    });

    it('offers recapture without an action that promotes failed test evidence', async () => {
        const onCaptureDecision = vi.fn().mockResolvedValue(true);
        const view = render(OmrDiagnosticEvidenceModal, {
            open: true,
            page: componentEvidencePage,
            captureReviewRequired: true,
            onCaptureDecision,
        });

        expect(view.queryByRole('button', { name: /Done reviewing|Complete review/ })).toBeNull();
        expect(
            view.getByRole('button', { name: 'Request recapture' }),
        ).not.toBeNull();

        await fireEvent.input(view.getByLabelText(/Review note/), {
            target: { value: 'Marks and crops checked.' },
        });
        await fireEvent.click(view.getByRole('button', { name: 'Request recapture' }));

        expect(onCaptureDecision).toHaveBeenCalledWith(
            'fail',
            'Marks and crops checked.',
        );
    });
    it('offers completion and acceptance only when the server allows it', async () => {
        const onCaptureDecision = vi.fn().mockResolvedValue(true);
        const view = render(OmrDiagnosticEvidenceModal, {
            open: true,
            page: componentEvidencePage,
            captureReviewRequired: true,
            canAcceptReview: true,
            captureReviewBusy: false,
            onCaptureDecision,
        });

        expect(
            view.getByRole('button', { name: 'Complete review & accept' }),
        ).not.toBeNull();
        expect(view.getByRole('dialog').textContent).toContain(
            'All flagged items have been reviewed.',
        );
        await fireEvent.click(
            view.getByRole('button', { name: 'Complete review & accept' }),
        );
        expect(onCaptureDecision).toHaveBeenCalledWith('pass', '');

        await view.rerender({
            open: true,
            page: componentEvidencePage,
            captureReviewRequired: true,
            canAcceptReview: false,
            captureReviewBusy: false,
            onCaptureDecision,
        });
        expect(
            view.queryByRole('button', { name: 'Complete review & accept' }),
        ).toBeNull();
    });
});
