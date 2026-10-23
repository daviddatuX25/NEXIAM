// @vitest-environment jsdom

import { cleanup, fireEvent, render, waitFor } from '@testing-library/svelte';
import { afterEach, describe, expect, it, vi } from 'vitest';
import OmrPhysicalTestMethodCard from '../../resources/js/Pages/Setup/OmrConfiguration/Canonical/OmrPhysicalTestMethodCard.svelte';
import StudioExportVerifyPanel from '../../resources/js/Pages/Setup/OmrConfiguration/Canonical/StudioExportVerifyPanel.svelte';
import StudioShadingGuideModal from '../../resources/js/Pages/Setup/OmrConfiguration/Canonical/StudioShadingGuideModal.svelte';

const selectedPass = {
    capture_id: 102,
    attempt_no: 2,
    page_no: 1,
    attempt_state: 'passed',
    status: 'pass',
    actual: 'pass',
    gates_pass: true,
    vector_match: true,
    failure_code: null,
};

const methodData = {
    method: 'pre_shaded',
    state: 'passed',
    challenge_id: 7,
    print_url: '/runs/7/pre-sheet',
    guide_url: '/runs/7/pre-guide',
    pages: [
        {
            page_no: 1,
            state: 'passed',
            selected_attempt_id: selectedPass.capture_id,
            selected_attempt: selectedPass,
        },
    ],
    attempts: [
        {
            capture_id: 101,
            attempt_no: 1,
            page_no: 1,
            attempt_state: 'failed',
            status: 'fail',
            error_code: 'marker_mismatch',
        },
        selectedPass,
        {
            capture_id: 103,
            attempt_no: 3,
            page_no: 1,
            attempt_state: 'passed',
            status: 'pass',
            actual: 'pass',
            gates_pass: true,
            vector_match: true,
            failure_code: null,
        },
    ],
};

afterEach(() => {
    cleanup();
    vi.unstubAllGlobals();
    window.history.replaceState({}, '', '/setup/omr');
});

describe('OMR physical method cards', () => {
    it('does not call an active capture failed before analysis completes', () => {
        const view = render(OmrPhysicalTestMethodCard, {
            method: 'pre_shaded',
            onOpenEvidence: vi.fn(),
            methodData: {
                state: 'failed',
                run_status: 'analyzing_capture',
                pages: [{ page_no: 2, state: 'analyzing' }],
                attempts: [{
                    capture_id: 116, page_no: 2, attempt_no: 3,
                    attempt_state: 'active', status: 'fail', actual: null,
                    failure_code: null, is_latest: true,
                }],
            },
        });
        expect(view.container.textContent).toContain('Analyzing');
        expect(view.container.textContent).not.toContain('Failed');
        expect(view.queryByRole('button', { name: 'Inspect evidence' })).toBeNull();
        expect(view.container.querySelector('[role="progressbar"]')).toBeNull();
    });
    it('does not invent applicant identity, session details, or a score for builder evidence', async () => {
        window.history.replaceState({}, '', '/setup/omr?evidence_method=pre_shaded&evidence_capture=113&evidence_filter=needs_review');
        const fetchMock = vi.fn().mockResolvedValue(new Response(JSON.stringify({
            method: 'pre_shaded', capture_id: 113, capture_page_no: 2,
            area: 'all', total: 0, page: 1, per_page: 25, page_count: 1,
            items: [], groups: [], reviewable: false,
        }), { status: 200, headers: { 'Content-Type': 'application/json' } }));
        vi.stubGlobal('fetch', fetchMock);
        const view = render(StudioExportVerifyPanel, {
            draft: { id: 7, stage: 'compiled' },
            verification_run: {
                run_id: 17, status: 'awaiting_capture',
                methods: [{ method: 'pre_shaded', state: 'failed', attempts: [] }],
            },
            urls: {},
        });
        await waitFor(() => expect(fetchMock).toHaveBeenCalled());
        expect(String(fetchMock.mock.calls[0][0])).toContain('filter=needs_review');
        expect(fetchMock.mock.calls[0][1]).toMatchObject({ cache: 'no-store' });
        await waitFor(() => expect(view.getByRole('dialog')).not.toBeNull());
        const dialog = view.getByRole('dialog');
        expect(dialog.textContent).not.toContain('Maria Santos');
        expect(dialog.textContent).not.toContain('86 / 100');
        expect(dialog.textContent).not.toContain('SIM-0113');
        expect(dialog.textContent).not.toContain('Room 204');
        expect(dialog.textContent).not.toContain('September 18, 2026');
    });
    it('distinguishes unresolved marks from a binding rejection on the selected page', async () => {
        const onOpenEvidence = vi.fn();
        const onRetry = vi.fn();
        const reviewAttempt = {
            capture_id: 113,
            page_no: 2,
            attempt_no: 1,
            attempt_state: 'failed',
            status: 'fail',
            actual: 'answer_vector_mismatch',
            failure_code: 'capture_needs_review',
            gates_pass: true,
            needs_review_count: 3,
        };
        const props = {
            method: 'pre_shaded',
            isUploadedSource: true,
            capturePage: 2,
            onOpenEvidence,
            onRetry,
            methodData: {
                state: 'needs_review',
                pages: [{ page_no: 2, state: 'needs_review' }],
                attempts: [reviewAttempt],
            },
        };
        const view = render(OmrPhysicalTestMethodCard, props);
        expect(view.getByRole('status').textContent).toContain('Review required');
        expect(
            view.getAllByRole('button', { name: 'Review evidence (3)' }),
        ).toHaveLength(1);
        expect(view.getAllByRole('button', { name: 'Retake page 2' })).toHaveLength(1);
        expect(view.queryByText(/Capture snapshot/)).toBeNull();
        await fireEvent.click(view.getByRole('button', { name: 'Review evidence (3)' }));
        await view.rerender({
            ...props,
            methodData: {
                state: 'failed',
                failure_reason: 'source_image_hash_mismatch',
                pages: [{ page_no: 1, state: 'needs_review' }, { page_no: 2, state: 'failed' }],
                attempts: [{
                    ...reviewAttempt,
                    actual: 'typed_rejection',
                    failure_code: 'source_image_hash_mismatch',
                    failure_reason: 'source_image_hash_mismatch',
                    gates_pass: false,
                    needs_review_count: null,
                }],
            },
        });
        expect(view.queryByRole('button', { name: /Review evidence/ })).toBeNull();
        expect(view.container.textContent).not.toContain('Review required');
        expect(view.container.textContent).toContain('source_image_hash_mismatch');
        expect(view.getByRole('button', { name: 'Inspect evidence' })).not.toBeNull();
        expect(view.getAllByRole('button', { name: 'Retake page 2' })).toHaveLength(1);
        await fireEvent.click(view.getByRole('button', { name: 'Retake page 2' }));
        expect(onRetry).toHaveBeenCalledWith({ method: 'pre_shaded', pageNo: 2 });
    });

    it('defaults to the pending review page after an earlier page passed', async () => {
        const onOpenEvidence = vi.fn();
        const onRetry = vi.fn();
        const onAcceptReview = vi.fn();
        const pageOnePass = {
            capture_id: 114,
            page_no: 1,
            attempt_no: 1,
            attempt_state: 'passed',
            status: 'pass',
            actual: 'pass',
            gates_pass: true,
            vector_match: true,
            failure_code: null,
        };
        const pageTwoReview = {
            capture_id: 115,
            page_no: 2,
            attempt_no: 2,
            attempt_state: 'failed',
            status: 'fail',
            actual: 'answer_vector_mismatch',
            failure_code: 'capture_needs_review',
            gates_pass: true,
            needs_review_count: 3,
        };
        const view = render(OmrPhysicalTestMethodCard, {
            method: 'pre_shaded',
            onOpenEvidence,
            onRetry,
            onAcceptReview,
            methodData: {
                state: 'needs_review',
                pages: [
                    {
                        page_no: 1,
                        state: 'passed',
                        selected_attempt_id: pageOnePass.capture_id,
                        selected_attempt: pageOnePass,
                    },
                    { page_no: 2, state: 'needs_review' },
                ],
                attempts: [pageOnePass, pageTwoReview],
            },
        });

        expect(view.getByRole('status').textContent).toContain('Review required');
        expect(
            view.queryByRole('button', { name: 'Accept reviewed result' }),
        ).toBeNull();
        expect(
            (view.getByRole('button', { name: 'Page 2: needs review' }) as HTMLButtonElement)
                .getAttribute('aria-pressed'),
        ).toBe('true');
        const reviewButton = view.getByRole('button', {
            name: 'Review evidence (3)',
        });
        await fireEvent.click(reviewButton);
        expect(onOpenEvidence).toHaveBeenCalledWith({
            method: 'pre_shaded',
            captureId: 115,
            pageNo: 2,
            filter: 'needs_review',
        });

        await view.rerender({
            onOpenEvidence,
            onRetry,
            onAcceptReview,
            methodData: {
                state: 'needs_review',
                pages: [
                    {
                        page_no: 1,
                        state: 'passed',
                        selected_attempt_id: pageOnePass.capture_id,
                        selected_attempt: pageOnePass,
                    },
                    { page_no: 2, state: 'needs_review' },
                ],
                attempts: [
                    pageOnePass,
                    {
                        ...pageTwoReview,
                        needs_review_count: 0,
                        machine_needs_review_count: 3,
                        can_accept_reviewed: true,
                    },
                ],
            },
        });
        expect(
            view.getByRole('button', { name: 'Review evidence (reviewed)' }),
        ).not.toBeNull();
        const acceptReviewedButton = view.getByRole('button', {
            name: 'Accept reviewed result',
        });
        await fireEvent.click(acceptReviewedButton);
        expect(onAcceptReview).toHaveBeenCalledWith({
            method: 'pre_shaded',
            captureId: 115,
            pageNo: 2,
        });
        await fireEvent.click(
            view.getByRole('button', { name: 'Review evidence (reviewed)' }),
        );
        expect(onOpenEvidence).toHaveBeenLastCalledWith({
            method: 'pre_shaded',
            captureId: 115,
            pageNo: 2,
            filter: 'reviewed',
        });
        await fireEvent.click(view.getByText(/Attempt history/));
        expect(view.container.textContent).toContain(
            'Ready to accept reviewed result',
        );
        await fireEvent.click(
            view.getByRole('button', { name: 'Select this attempt' }),
        );
        expect(onAcceptReview).toHaveBeenCalledTimes(2);
        const historyEvidenceButtons = view.getAllByRole('button', {
            name: 'Inspect evidence',
        });
        await fireEvent.click(historyEvidenceButtons[1]);
        expect(onOpenEvidence).toHaveBeenLastCalledWith({
            method: 'pre_shaded',
            captureId: 115,
            pageNo: 2,
            filter: 'reviewed',
        });
    });

    it('clears prior review actions after retake reopens the page', () => {
        const onRetry = vi.fn();
        const view = render(OmrPhysicalTestMethodCard, {
            method: 'pre_shaded',
            captureEnabled: true,
            isUploadedSource: true,
            capturePage: 2,
            onRetry,
            onOpenEvidence: vi.fn(),
            onSubmitCapture: vi.fn(),
            methodData: {
                state: 'awaiting_capture',
                pages: [{ page_no: 2, state: 'awaiting_capture' }],
                attempts: [{
                    capture_id: 113,
                    page_no: 2,
                    attempt_no: 1,
                    attempt_state: 'failed',
                    status: 'fail',
                    actual: 'answer_vector_mismatch',
                    failure_code: 'capture_needs_review',
                    gates_pass: true,
                    needs_review_count: 3,
                }],
            },
        });

        expect(view.queryByRole('button', { name: /Review evidence/ })).toBeNull();
        expect(view.queryByRole('button', { name: 'Retake page 2' })).toBeNull();
        expect(view.getByRole('button', { name: 'Choose Photo or Drag & Drop' })).not.toBeNull();
        expect(onRetry).not.toHaveBeenCalled();
    });

    it('does not offer review approval or selection for historical binding rejections', () => {
        const view = render(OmrPhysicalTestMethodCard, {
            method: 'pre_shaded',
            onOpenEvidence: vi.fn(),
            onSelectAttempt: vi.fn(),
            methodData: {
                state: 'needs_review',
                pages: [{ page_no: 1, state: 'needs_review' }],
                attempts: [{
                    capture_id: 113, page_no: 1, attempt_no: 1,
                    attempt_state: 'failed', status: 'fail',
                    actual: 'typed_rejection', failure_code: 'capture_needs_review',
                    failure_reason: 'binding_evidence_unrecorded',
                    gates_pass: null, needs_review_count: null,
                }],
            },
        });
        expect(view.queryByRole('button', { name: /Review evidence/ })).toBeNull();
        expect(view.queryByRole('button', { name: 'Accept result' })).toBeNull();
        expect(view.queryByRole('button', { name: 'Use this pass' })).toBeNull();
        expect(view.getByRole('button', { name: 'Inspect evidence' })).not.toBeNull();
    });

    it('does not present a manually promoted mismatch as an exact pass', () => {
        const view = render(OmrPhysicalTestMethodCard, {
            method: 'pre_shaded',
            onSelectAttempt: vi.fn(),
            methodData: {
                state: 'needs_review',
                pages: [{ page_no: 1, state: 'needs_review' }],
                attempts: [{
                    capture_id: 113, page_no: 1, attempt_no: 1,
                    attempt_state: 'passed', status: 'pass',
                    actual: 'answer_vector_mismatch', vector_match: false,
                    gates_pass: true, failure_code: 'capture_needs_review',
                }],
            },
        });
        expect(view.queryByRole('button', { name: 'Accept result' })).toBeNull();
    });
    it('offers document-bound physical methods after digital verification without a marker profile', () => {
        const { container } = render(StudioExportVerifyPanel, {
            draft: {
                id: 9,
                stage: 'digital_verified',
                registration_mode: 'document_bound',
                marker_profile_id: null,
                compiled_version_id: 10,
                digital_verification_current: true,
                mapping_current: true,
                authoring: { canonical: { settings: { page_count: 2 } } },
            },
            marker_profile_status: null,
            urls: {},
        });

        expect(container.querySelectorAll('[data-omr-method]')).toHaveLength(2);
        expect(
            container.querySelector('[data-omr-method="pre_shaded"]'),
        ).not.toBeNull();
        expect(
            container.querySelector('[data-omr-method="hand_shaded"]'),
        ).not.toBeNull();
        expect(container.textContent).toMatch(
            /Activation still[\s\S]*?device\s+qualification\./,
        );
        expect(container.textContent).not.toContain(
            'Document-bound physical capture is not available.',
        );
    });
    it('shows document boundary progress instead of fiducial markers during capture', () => {
        const { container } = render(OmrPhysicalTestMethodCard, {
            method: 'pre_shaded',
            registrationMode: 'document_bound',
            methodData: {
                method: 'pre_shaded',
                state: 'analyzing',
                phase_detail: { phase_code: 'layout_verification' },
                pages: [{ page_no: 1, state: 'analyzing' }],
                attempts: [],
            },
        });

        expect(container.textContent).toContain('unique page boundary');
        expect(container.textContent).not.toContain('corner markers');
    });
    it('uses original-page analysis language for keep-original captures', () => {
        const { container } = render(OmrPhysicalTestMethodCard, {
            method: 'pre_shaded',
            registrationMode: 'document_bound',
            methodData: {
                method: 'pre_shaded',
                state: 'analyzing',
                phase_detail: { phase_code: 'layout_verification' },
                pages: [{ page_no: 1, state: 'analyzing' }],
                attempts: [{
                    capture_id: 117,
                    page_no: 1,
                    attempt_state: 'failed',
                    status: 'fail',
                    crop_policy: 'keep_original',
                }],
            },
        });

        expect(container.textContent).toContain(
            'Verifying original page layout and content…',
        );
        expect(container.textContent).toContain(
            'Preserving original full-page frame',
        );
        expect(container.textContent).toContain(
            'Verifying original page layout',
        );
        expect(container.textContent).toContain(
            'Using the original full-page frame without autocrop; checking mapped layout and printed content.',
        );
        expect(container.textContent).not.toMatch(
            /document boundary|page boundary/i,
        );
    });

    it('renders the not-started physical method action after refresh', () => {
        const { getByRole } = render(OmrPhysicalTestMethodCard, {
            method: 'pre_shaded',
            methodData: {
                method: 'pre_shaded',
                state: 'not_started',
                pages: [],
                attempts: [],
            },
            onCreate: vi.fn(),
        });

        expect(
            getByRole('button', { name: 'Create pre-shaded test sheet' }),
        ).not.toBeNull();
    });

    it('renders exactly two ordered method cards with selected-pass summary and retained history', async () => {
        const run = {
            run_id: 7,
            status: 'awaiting_capture',
            phase: 'awaiting_capture',
            phase_detail: null,
            mode: 'quick_rehearsal',
            attempt: 1,
            progress: { done: 0, total: 1, percent: 0 },
            current_variant: null,
            safe_issue: null,
            result_summary: null,
            available_actions: ['submit_capture'],
            run_version: 1,
            target: { kind: 'candidate', id: 1, hashes: {} },
            timestamps: {
                started_at: null,
                finished_at: null,
                last_heartbeat_at: null,
            },
            evidence: [{ page_no: 1, status: 'pass', summary: null }],
            challenge: null,
            methods: [
                { ...methodData, method: 'pre_shaded' },
                {
                    ...methodData,
                    method: 'hand_shaded',
                    state: 'needs_review',
                    print_url: '/runs/7/hand-sheet',
                    guide_url: '/runs/7/hand-guide',
                },
            ],
        };
        const { container } = render(StudioExportVerifyPanel, {
            draft: {
                id: 1,
                stage: 'digital_verified',
                source_kind: 'html',
                authoring: { canonical: { settings: { page_count: 1 } } },
            },
            verification_run: run,
            marker_profile_status: 'candidate',
            urls: {},
        });

        const cards = container.querySelectorAll('[data-omr-method]');
        expect(cards).toHaveLength(2);
        expect(cards[0].getAttribute('data-omr-method')).toBe('pre_shaded');
        expect(cards[1].getAttribute('data-omr-method')).toBe('hand_shaded');
        expect(container.textContent).toContain('Pre-shaded test');
        expect(container.textContent).toContain('Hand-shaded test');
        expect(container.textContent).toContain('Page 1: selected');
        expect(container.textContent).toContain('Attempt history (3)');
        expect(container.textContent).not.toContain(
            'Passing attempt ready for acceptance',
        );
        const preShadedCard = cards[0];
        const history = Array.from(
            preShadedCard.querySelectorAll('details'),
        ).find(
            (details) =>
                details.querySelector('summary')?.textContent?.trim() ===
                'Attempt history (3)',
        );
        expect(history).toBeDefined();
        expect(history?.hasAttribute('open')).toBe(false);
        expect(history?.textContent).toContain('Attempt 3 · page 1');
        expect(
            Array.from(history?.querySelectorAll('button') ?? []).some(
                (button) => button.textContent?.includes('Use this pass'),
            ),
        ).toBe(true);
        expect(
            preShadedCard.querySelector('input[id^="selection-reason-"]'),
        ).toBeNull();
        expect(container.textContent).toContain('marker_mismatch');
        expect(container.textContent).not.toMatch(/Full qualification|Exclude/);
    });

    it('keeps method guidance behind named info controls', async () => {
        const run = {
            run_id: 7,
            status: 'awaiting_capture',
            phase: 'awaiting_capture',
            phase_detail: null,
            mode: 'quick_rehearsal',
            attempt: 1,
            progress: { done: 0, total: 2, percent: 0 },
            available_actions: ['submit_capture'],
            challenge_recovery: {
                kind: null,
                reason_code: 'evidence_immutable',
                detail: 'Evidence is retained.',
            },
            target_current: true,
            target_stale: false,
            methods: [
                {
                    ...methodData,
                    method: 'pre_shaded',
                    state: 'awaiting_capture',
                },
                {
                    ...methodData,
                    method: 'hand_shaded',
                    state: 'awaiting_capture',
                    challenge_id: 8,
                },
            ],
            challenge: {
                challenge_id: 7,
                challenge_code: 'CHAL-7',
                status: 'printed',
            },
        };
        const { container } = render(StudioExportVerifyPanel, {
            draft: {
                id: 1,
                stage: 'digital_verified',
                registration_mode: 'document_bound',
                marker_profile_id: null,
                digital_verification_current: true,
                mapping_current: true,
                source_kind: 'html',
                authoring: { canonical: { settings: { page_count: 1 } } },
            },
            verification_run: run,
            marker_profile_status: 'candidate',
            urls: {},
        });

        const summaries = Array.from(container.querySelectorAll('summary')).map(
            (summary) => summary.textContent?.trim(),
        );
        expect(summaries).toContain('Physical test information');
        expect(summaries).not.toContain('How the two tests fit together');
        expect(
            container.querySelectorAll('button[aria-label$="test info"]'),
        ).toHaveLength(2);
        expect(container.querySelectorAll('details[open]')).toHaveLength(0);
        expect(container.textContent).toMatch(/Calibration-ID\s+disclaimer:/);
        expect(container.textContent).not.toContain('Marking guide:');
        await fireEvent.click(
            container.querySelector(
                'button[aria-label="Hand-shaded test info"]',
            )!,
        );
        expect(container.textContent).toContain('Marking guide:');
    });

    it('keeps recovery and live pipeline controls outside the closed information disclosure', () => {
        const failedMethod = {
            ...methodData,
            method: 'pre_shaded',
            state: 'failed',
            run_status: 'awaiting_capture',
            safe_error: 'Retake page 1 after correcting the sheet alignment.',
            phase_detail: {
                phase_code: 'failed',
                failed_phase_code: 'layout_verification',
                page_no: 1,
            },
            pages: [{ page_no: 1, state: 'failed', page_version: 1 }],
        };
        const analyzingMethod = {
            ...methodData,
            method: 'hand_shaded',
            state: 'analyzing',
            run_status: 'analyzing_capture',
            phase_detail: { phase_code: 'orientation' },
            pages: [{ page_no: 1, state: 'analyzing', page_version: 1 }],
        };
        const { container } = render(StudioExportVerifyPanel, {
            draft: {
                id: 1,
                stage: 'digital_verified',
                source_kind: 'html',
                authoring: { canonical: { settings: { page_count: 1 } } },
            },
            verification_run: {
                run_id: 7,
                status: 'awaiting_capture',
                phase: 'awaiting_capture',
                mode: 'quick_rehearsal',
                progress: { done: 0, total: 2, percent: 0 },
                available_actions: ['submit_capture'],
                challenge_recovery: {
                    kind: null,
                    reason_code: 'evidence_immutable',
                    detail: 'Evidence is retained.',
                },
                target_current: true,
                target_stale: false,
                methods: [failedMethod, analyzingMethod],
                challenge: { challenge_id: 7, status: 'printed' },
            },
            marker_profile_status: 'candidate',
            urls: {},
        });

        const information = Array.from(
            container.querySelectorAll('details'),
        ).find(
            (details) =>
                details.querySelector('summary')?.textContent?.trim() ===
                'Physical test information',
        );
        expect(information).toBeDefined();
        expect(information?.hasAttribute('open')).toBe(false);
        expect(
            information?.querySelector('[aria-label="Physical tests"]'),
        ).toBeNull();
        expect(
            information?.querySelector(
                '[aria-label="Capture analysis progress"]',
            ),
        ).toBeNull();
        expect(
            information?.querySelector('button[aria-label="Retake page 1"]'),
        ).toBeNull();
        expect(
            container.querySelector('[aria-label="Physical tests"]'),
        ).not.toBeNull();
        expect(
            container.querySelector('[aria-label="Capture analysis progress"]'),
        ).not.toBeNull();
        expect(
            container.querySelector('button[aria-label="Retake page 1"]'),
        ).not.toBeNull();
        expect(container.textContent).toContain(
            'Searching the quarter-turn orientations for the signed sheet markers.',
        );
    });
    it('keeps preparation and capture methods together without overriding child state', () => {
        const run = {
            run_id: 49,
            status: 'awaiting_capture',
            phase: 'awaiting_capture',
            phase_detail: { stage: 'awaiting_capture' },
            mode: 'quick_rehearsal',
            attempt: 2,
            progress: { done: 0, total: 2, percent: 0 },
            current_variant: null,
            safe_issue: null,
            result_summary: null,
            available_actions: ['submit_capture'],
            run_version: 2,
            target_current: true,
            target_stale: false,
            digital_verification_current: true,
            target: { kind: 'candidate', id: 13, hashes: {} },
            timestamps: {
                started_at: null,
                finished_at: null,
                last_heartbeat_at: null,
            },
            evidence: [],
            challenge: {
                challenge_id: 42,
                challenge_code: 'CHAL-49',
                mode: 'quick',
                status: 'created',
            },
            methods: [
                {
                    method: 'pre_shaded',
                    state: 'analyzing',
                    challenge_id: 41,
                    challenge: { status: 'analyzing_capture' },
                    pages: [{ page_no: 1, state: 'analyzing' }],
                    attempts: [],
                },
                {
                    method: 'hand_shaded',
                    state: 'challenge_ready',
                    challenge_id: 42,
                    challenge: { status: 'created' },
                    pages: [{ page_no: 1, state: 'not_started' }],
                    attempts: [],
                },
            ],
        };
        const { container } = render(StudioExportVerifyPanel, {
            draft: {
                id: 13,
                stage: 'digital_verified',
                source_kind: 'html',
                authoring: { canonical: { settings: { page_count: 1 } } },
            },
            verification_run: run,
            marker_profile_status: 'candidate',
            urls: {},
        });

        const cards = container.querySelectorAll('[data-omr-method]');
        expect(cards).toHaveLength(2);
        expect(cards[0].getAttribute('data-omr-method')).toBe('pre_shaded');
        expect(cards[0].textContent).toContain('Analyzing captured page');
        expect(cards[0].querySelector('input[type=file]')).toBeNull();
        expect(cards[1].getAttribute('data-omr-method')).toBe('hand_shaded');
        expect(
            container.querySelectorAll('div[aria-label="Physical tests"]'),
        ).toHaveLength(1);
        expect(container.textContent).not.toContain(
            'Capture printed challenge',
        );
    });

    it('shows one selectable capture surface while preserving both independent methods', async () => {
        const method = (name: 'pre_shaded' | 'hand_shaded') => ({
            method: name,
            state: 'awaiting_capture',
            challenge_id: name === 'pre_shaded' ? 41 : 42,
            required_page_count: 1,
            pages: [{ page_no: 1, state: 'awaiting_capture', page_version: 1 }],
            attempts: [],
        });
        const { container, getByRole } = render(StudioExportVerifyPanel, {
            draft: {
                id: 13,
                stage: 'digital_verified',
                authoring: { canonical: { settings: { page_count: 1 } } },
            },
            verification_run: {
                run_id: 49,
                status: 'awaiting_capture',
                phase: 'awaiting_capture',
                phase_detail: null,
                mode: 'quick_rehearsal',
                progress: { done: 0, total: 2, percent: 0 },
                available_actions: ['submit_capture'],
                challenge_recovery: {
                    kind: null,
                    reason_code: 'evidence_immutable',
                },
                target_current: true,
                methods: [method('pre_shaded'), method('hand_shaded')],
            },
            marker_profile_status: 'candidate',
            urls: {},
        });

        const cards = container.querySelectorAll('[data-omr-method]');
        expect(cards).toHaveLength(2);
        expect(container.querySelectorAll('input[type=file]')).toHaveLength(1);
        expect(cards[0].querySelector('input[type=file]')).not.toBeNull();
        expect(cards[1].querySelector('input[type=file]')).toBeNull();

        await fireEvent.click(
            getByRole('button', { name: 'Use hand-shaded capture' }),
        );

        expect(container.querySelectorAll('input[type=file]')).toHaveLength(1);
        expect(cards[0].querySelector('input[type=file]')).toBeNull();
        expect(cards[1].querySelector('input[type=file]')).not.toBeNull();
    });

    it('captures a hand-shaded page without a manual marking-instrument choice', async () => {
        const submit = vi.fn();
        const file = new File(['capture'], 'portrait.png', {
            type: 'image/png',
        });
        const { container, getByRole, queryByRole } = render(OmrPhysicalTestMethodCard, {
            method: 'hand_shaded',
            methodData: {
                ...methodData,
                method: 'hand_shaded',
                state: 'awaiting_capture',
                pages: [
                    {
                        page_no: 1,
                        state: 'awaiting_capture',
                        selected_attempt_id: null,
                        selected_attempt: null,
                    },
                ],
                attempts: [],
            },
            onSubmitCapture: submit,
        });

        const input = container.querySelector('input[type=file]');
        expect(input).not.toBeNull();
        expect(input?.getAttribute('accept')).toContain('application/pdf');
        expect(
            (
                getByRole('button', {
                    name: /Choose Photo or Drag & Drop/,
                }) as HTMLButtonElement
            ).disabled,
        ).toBe(false);
        expect(
            queryByRole('group', { name: 'Marking instrument' }),
        ).toBeNull();
        expect(queryByRole('radio', { name: /HB \/ #2 pencil/ })).toBeNull();
        expect(queryByRole('radio', { name: /Dark black ballpoint pen/ })).toBeNull();
        await fireEvent.change(input!, { target: { files: [file] } });
        await waitFor(() =>
            expect(
                getByRole('button', { name: 'Capture page 1' }),
            ).not.toBeNull(),
        );
        await fireEvent.click(getByRole('button', { name: 'Capture page 1' }));
        expect(submit).toHaveBeenCalledWith(
            expect.objectContaining({
                method: 'hand_shaded',
                pageNo: 1,
                cropPolicy: 'auto_document_quad',
            }),
        );
        expect(submit.mock.calls[0][0]).not.toHaveProperty('medium');
    });
    it('targets the awaiting page, exposes review-page retake, and sends crop policy', async () => {
        const submit = vi.fn();
        const retry = vi.fn();
        const file = new File(['capture'], 'page-2.png', {
            type: 'image/png',
        });
        const { container, getByRole } = render(
            OmrPhysicalTestMethodCard,
            {
                method: 'pre_shaded',
                methodData: {
                    ...methodData,
                    state: 'awaiting_capture',
                    pages: [
                        {
                            page_no: 1,
                            state: 'needs_review',
                            page_version: 4,
                        },
                        {
                            page_no: 2,
                            state: 'awaiting_capture',
                            page_version: 2,
                        },
                    ],
                    attempts: [],
                },
                onSubmitCapture: submit,
                onRetry: retry,
            },
        );

        expect(getByRole('button', { name: /Page 1: Needs review/i })).toBeTruthy();
        expect(getByRole('button', { name: /Page 2: Awaiting capture/i })).toBeTruthy();
        await fireEvent.click(getByRole('button', { name: /Page 1: Needs review/i }));
        expect(
            (getByRole('button', { name: 'Retake Page 1' }) as HTMLButtonElement)
                .disabled,
        ).toBe(false);
        await fireEvent.click(getByRole('button', { name: 'Retake Page 1' }));
        expect(retry).toHaveBeenCalledWith({
            method: 'pre_shaded',
            pageNo: 1,
        });

        expect(container.querySelector('input[type=file]')).toBeNull();
        await fireEvent.click(getByRole('button', { name: /Page 2: Awaiting capture/i }));
        await fireEvent.click(
            getByRole('radio', { name: /Keep original/i }),
        );
        const input = container.querySelector('input[type=file]');
        await fireEvent.change(input!, { target: { files: [file] } });
        await waitFor(() => expect(getByRole('button', { name: 'Capture page 2' })).toBeTruthy());
        await fireEvent.click(getByRole('button', { name: 'Capture page 2' }));
        expect(submit).toHaveBeenCalledWith(
            expect.objectContaining({
                file,
                method: 'pre_shaded',
                pageNo: 2,
                cropPolicy: 'keep_original',
            }),
        );
    });


    it('chooses, cancels, and confirms one selected-pass replacement', async () => {
        const onSelectAttempt = vi.fn();
        const { container, getByRole, queryByRole } = render(
            OmrPhysicalTestMethodCard,
            {
                method: 'pre_shaded',
                methodData,
                onSelectAttempt,
            },
        );

        expect(
            container.querySelector('input[id^="selection-reason-"]'),
        ).toBeNull();
        expect(container.textContent).not.toContain(
            'Passing attempt ready for acceptance',
        );
        const history = Array.from(container.querySelectorAll('details')).find(
            (details) =>
                details.querySelector('summary')?.textContent?.trim() ===
                'Attempt history (3)',
        );
        expect(history?.hasAttribute('open')).toBe(false);
        await fireEvent.click(history?.querySelector('summary') as HTMLElement);

        await fireEvent.click(getByRole('button', { name: 'Use this pass' }));
        expect(
            container.querySelectorAll('input[id^="selection-reason-"]'),
        ).toHaveLength(1);
        const reason = getByRole('textbox', {
            name: 'Replacement reason',
        }) as HTMLInputElement;
        expect(reason.value).toBe('');
        expect(
            (
                getByRole('button', {
                    name: 'Confirm replacement',
                }) as HTMLButtonElement
            ).disabled,
        ).toBe(true);

        await fireEvent.input(reason, {
            target: {
                value: 'Portrait pass replaces the earlier rehearsal selection.',
            },
        });
        await fireEvent.click(getByRole('button', { name: 'Cancel' }));
        expect(
            queryByRole('textbox', { name: 'Replacement reason' }),
        ).toBeNull();
        expect(onSelectAttempt).not.toHaveBeenCalled();

        await fireEvent.click(getByRole('button', { name: 'Use this pass' }));
        const replacementReason = getByRole('textbox', {
            name: 'Replacement reason',
        }) as HTMLInputElement;
        expect(replacementReason.value).toBe('');
        await fireEvent.input(replacementReason, {
            target: {
                value: 'Portrait pass replaces the earlier rehearsal selection.',
            },
        });
        const confirm = getByRole('button', {
            name: 'Confirm replacement',
        }) as HTMLButtonElement;
        expect(confirm.disabled).toBe(false);
        await fireEvent.click(confirm);

        expect(onSelectAttempt).toHaveBeenCalledTimes(1);
        expect(onSelectAttempt).toHaveBeenCalledWith({
            attempt: expect.objectContaining({ capture_id: 103 }),
            method: 'pre_shaded',
            pageNo: 1,
            reason: 'Portrait pass replaces the earlier rehearsal selection.',
        });
        expect(
            queryByRole('textbox', { name: 'Replacement reason' }),
        ).toBeNull();
    });

    it('accepts the first passing attempt without a note and refreshes status', async () => {
        const run = {
            run_id: 77,
            status: 'awaiting_print',
            phase: 'awaiting_print',
            mode: 'quick_rehearsal',
            attempt: 1,
            progress: { done: 0, total: 2, percent: 0 },
            available_actions: [],
            target: { kind: 'candidate', id: 13, hashes: {} },
            methods: [
                {
                    method: 'pre_shaded',
                    state: 'needs_review',
                    challenge_id: 41,
                    target_hash: 'a'.repeat(64),
                    plan_hash: 'b'.repeat(64),
                    expected_vector_hash: 'c'.repeat(64),
                    pages: [
                        {
                            page_no: 1,
                            state: 'needs_review',
                            page_version: 6,
                            selected_attempt_id: null,
                            selected_attempt: null,
                        },
                    ],
                    attempts: [
                        {
                            capture_id: 76,
                            attempt_no: 2,
                            page_no: 1,
                            attempt_state: 'passed',
                            status: 'pass',
                            actual: 'pass',
                            gates_pass: true,
                            vector_match: true,
                            failure_code: null,
                        },
                    ],
                },
                {
                    method: 'hand_shaded',
                    state: 'not_started',
                    pages: [],
                    attempts: [],
                },
            ],
        };
        const refreshed = {
            ...run,
            methods: [
                {
                    ...run.methods[0],
                    state: 'passed',
                    pages: [
                        {
                            ...run.methods[0].pages[0],
                            state: 'passed',
                            page_version: 7,
                            selected_attempt_id: 76,
                            selected_attempt: run.methods[0].attempts[0],
                        },
                    ],
                },
                run.methods[1],
            ],
        };
        const fetchMock = vi
            .fn()
            .mockResolvedValueOnce(
                new Response(
                    JSON.stringify({
                        selected_attempt_id: 76,
                        status_url: '/setup/omr/templates/verification-runs/77',
                    }),
                    {
                        status: 200,
                        headers: { 'Content-Type': 'application/json' },
                    },
                ),
            )
            .mockResolvedValueOnce(
                new Response(JSON.stringify(refreshed), {
                    status: 200,
                    headers: { 'Content-Type': 'application/json' },
                }),
            );
        vi.stubGlobal('fetch', fetchMock);

        const { getByRole, getByText } = render(StudioExportVerifyPanel, {
            draft: {
                id: 13,
                stage: 'digital_verified',
                authoring: { canonical: { settings: { page_count: 1 } } },
            },
            verification_run: run,
            marker_profile_status: 'candidate',
            urls: {},
        });

        expect(getByRole('button', { name: 'Accept result' })).not.toBeNull();
        await fireEvent.click(getByRole('button', { name: 'Accept result' }));

        await waitFor(() => expect(fetchMock).toHaveBeenCalledTimes(2));
        expect(fetchMock.mock.calls[0][0]).toBe(
            '/setup/omr/templates/verification-runs/77/captures/76/select',
        );
        expect(JSON.parse(fetchMock.mock.calls[0][1].body)).toMatchObject({
            method: 'pre_shaded',
            page_no: 1,
            page_version: 6,
            reason: '',
        });
        expect(fetchMock.mock.calls[1][0]).toBe(
            '/setup/omr/templates/verification-runs/77',
        );
        await waitFor(() =>
            expect(
                getByText(
                    'Selected passing evidence for this method and page.',
                ),
            ).not.toBeNull(),
        );
    });
    it('keeps guide completion browser-local without marking the challenge printed', async () => {
        const onOpenChange = vi.fn();
        const onMarkPrinted = vi.fn();
        const { getByRole } = render(StudioShadingGuideModal, {
            open: true,
            onOpenChange,
            challenge: {
                mode: 'quick',
                guide: { mode: 'quick', sections: [] },
                sheet_pages: [],
            },
            onMarkPrinted,
        });

        await fireEvent.click(
            getByRole('button', { name: /Next: View Challenge Sheet/ }),
        );
        await fireEvent.click(
            getByRole('button', { name: /Done — return to test/ }),
        );
        expect(onMarkPrinted).not.toHaveBeenCalled();
        expect(onOpenChange).toHaveBeenCalledWith(false);
    });

    it('recovers from terminal worker timeout when method is still analyzing without rendering infinite spinner', async () => {
        const challengePayload = {
            run_id: 49,
            status: 'awaiting_print',
            mode: 'quick_rehearsal',
            attempt: 2,
            status_url: '/setup/omr/templates/verification-runs/49/status',
        };
        const fetchMock = vi.fn().mockResolvedValue({
            ok: true,
            status: 202,
            json: async () => challengePayload,
        });
        vi.stubGlobal('fetch', fetchMock);

        const run = {
            run_id: 49,
            status: 'failed',
            phase: 'failed',
            phase_detail: null,
            mode: 'quick_rehearsal',
            attempt: 1,
            progress: { done: 0, total: 1, percent: 0 },
            current_variant: null,
            safe_issue: {
                code: 'worker_timeout',
                detail: 'The verification worker stopped before finishing; completed evidence is preserved and the run can be retried.',
            },
            result_summary: null,
            available_actions: ['retry'],
            run_version: 1,
            target_current: true,
            target_stale: false,
            digital_verification_current: true,
            plan: {
                plan_hash: 'p',
                plan_version: 'v1',
                challenge_code: 'CHAL-49',
                expected_vector_hash: null,
            },
            target: {
                kind: 'candidate',
                id: 13,
                hashes: { artifact: 'hash-13' },
            },
            timestamps: {
                started_at: null,
                finished_at: '2026-09-04T14:57:13Z',
                last_heartbeat_at: '2026-09-04T12:29:39Z',
            },
            evidence: [],
            challenge: {
                challenge_id: 42,
                challenge_code: 'CHAL-49',
                mode: 'quick',
                status: 'created',
                print_url: '/runs/49/sheet',
                guide_url: '/runs/49/guide',
            },
            methods: [
                {
                    method: 'pre_shaded',
                    state: 'analyzing',
                    challenge_id: 41,
                    target_kind: 'candidate',
                    target_id: 13,
                    target_hash: 'hash-13',
                    challenge: { status: 'analyzing_capture' },
                    pages: [{ page_no: 1, state: 'analyzing' }],
                    attempts: [
                        {
                            capture_id: 381,
                            attempt_no: 1,
                            page_no: 1,
                            attempt_state: 'failed',
                            status: 'fail',
                            error_code: 'worker_timeout',
                        },
                    ],
                },
                {
                    method: 'hand_shaded',
                    state: 'awaiting_capture',
                    challenge_id: 42,
                    target_kind: 'candidate',
                    target_id: 13,
                    target_hash: 'hash-13',
                    challenge: { status: 'created' },
                    pages: [{ page_no: 1, state: 'awaiting_capture' }],
                    attempts: [],
                },
            ],
        };

        const { container, getByRole, queryByText } = render(
            StudioExportVerifyPanel,
            {
                draft: {
                    id: 13,
                    stage: 'digital_verified',
                    authoring: { canonical: { settings: { page_count: 1 } } },
                },
                verification_run: run,
                marker_profile_status: 'candidate',
                urls: {},
            },
        );

        // 1. Assert no spinner or "Polling in progress"
        expect(queryByText(/Polling in progress/)).toBeNull();
        expect(
            container.querySelector(
                '[data-omr-method="pre_shaded"] .animate-spin',
            ),
        ).toBeNull();

        // 2. Truthful timeout text is present on the method card and issue banner
        expect(container.textContent).toContain(
            'The verification worker stopped before finishing; completed evidence is preserved and the run can be retried.',
        );

        // 3. Available retry action that posts to /verification-runs/:id/retry
        const resumeBtn = getByRole('button', { name: /Resume challenge/ });

        await fireEvent.click(resumeBtn);

        expect(fetchMock).toHaveBeenCalled();
        const [url, options] = fetchMock.mock.calls[0] as unknown as [
            string,
            RequestInit,
        ];
        expect(url).toBe('/setup/omr/templates/verification-runs/49/retry');
        expect(options.method).toBe('POST');
    });

    it('renders a numbered vertical checklist at the server-owned analysis phase', async () => {
        const run = {
            run_id: 50,
            status: 'analyzing_capture',
            phase: 'analyzing_capture',
            phase_detail: {
                phase_code: 'layout_verification',
            },
            attempt: 1,
            progress: { done: 0, total: 1, percent: 0 },
            current_variant: null,
            safe_issue: null,
            result_summary: null,
            available_actions: [],
            run_version: 1,
            target: { kind: 'candidate', id: 13, hashes: {} },
            timestamps: {
                started_at: null,
                finished_at: null,
                last_heartbeat_at: null,
            },
            evidence: [],
            challenge: {
                challenge_id: 12,
                challenge_code: 'CHAL-50',
                mode: 'quick',
                status: 'printed',
                print_url: '/runs/50/sheet',
                guide_url: '/runs/50/guide',
            },
            methods: [
                {
                    method: 'pre_shaded',
                    state: 'analyzing',
                    challenge_id: 12,
                    pages: [{ page_no: 1, state: 'analyzing' }],
                    attempts: [],
                },
                {
                    method: 'hand_shaded',
                    state: 'awaiting_capture',
                    challenge_id: 12,
                    pages: [{ page_no: 1, state: 'awaiting_capture' }],
                    attempts: [],
                },
            ],
        };

        const { container } = render(StudioExportVerifyPanel, {
            draft: {
                id: 13,
                stage: 'digital_verified',
                authoring: { canonical: { settings: { page_count: 1 } } },
            },
            verification_run: run,
            marker_profile_status: 'candidate',
            urls: {},
        });

        const preShadedCard = container.querySelector(
            '[data-omr-method="pre_shaded"]',
        );
        const progress = preShadedCard?.querySelector(
            'ol[aria-label="Capture analysis progress"]',
        );
        const steps = progress?.querySelectorAll('li');
        expect(steps).toHaveLength(8);
        expect(Array.from(steps ?? []).map((step) => step.textContent)).toEqual(
            [
                expect.stringContaining('Upload received'),
                expect.stringContaining('Validating image'),
                expect.stringContaining(
                    'Finding orientation and corner markers',
                ),
                expect.stringContaining('Verifying sheet layout'),
                expect.stringContaining('Detecting bubble marks'),
                expect.stringContaining('Comparing expected answers'),
                expect.stringContaining('Saving evidence'),
                expect.stringContaining('Passed or Needs retake'),
            ],
        );
        expect(steps?.[3].getAttribute('aria-current')).toBe('step');
        expect(steps?.[3].textContent).toContain('Current');
        expect(progress?.querySelectorAll('.animate-spin')).toHaveLength(1);
    });

    it.each([
        ['orientation', 2, 'Finding orientation and corner markers'],
        ['evidence_persistence', 6, 'Saving evidence'],
    ] as const)(
        'keeps the checklist visible when %s fails',
        (failedPhase, failedIndex, label) => {
            const failedMethod = {
                ...methodData,
                state: 'failed',
                run_status: 'failed',
                safe_error: 'The capture could not be analyzed.',
                phase_detail: {
                    phase_code: 'failed',
                    failed_phase_code: failedPhase,
                },
            };
            const { container } = render(OmrPhysicalTestMethodCard, {
                method: 'pre_shaded',
                methodData: failedMethod,
                phaseDetail: {
                    phase_code: 'recognition',
                    method: 'hand_shaded',
                },
            });

            const progress = container.querySelector(
                'ol[aria-label="Capture analysis progress"]',
            );
            const steps = progress?.querySelectorAll('li');
            expect(steps).toHaveLength(8);
            expect(steps?.[failedIndex].textContent).toContain(label);
            expect(steps?.[failedIndex].textContent).toContain('Failed');
            expect(steps?.[failedIndex].getAttribute('aria-label')).toBe(
                `Step ${failedIndex + 1}, ${label}: failed`,
            );
            expect(container.textContent).toContain(
                'The capture could not be analyzed.',
            );
            expect(progress?.querySelectorAll('.animate-spin')).toHaveLength(0);
        },
    );

    it('stops at the persisted failed phase and keeps the retake action beside it', async () => {
        const onRetry = vi.fn();
        const failedMethod = {
            ...methodData,
            state: 'failed',
            run_status: 'awaiting_capture',
            safe_error:
                'No corner markers matched this answer sheet. Hold it upright and retake.',
            phase_detail: {
                phase_code: 'failed',
                failed_phase_code: 'layout_verification',
                page_no: 1,
                capture_id: 75,
            },
            pages: [{ page_no: 1, state: 'failed', page_version: 3 }],
            failure: {
                code: 'marker_missing',
                what_happened: 'Corner markers were not found.',
                observation:
                    'No corner markers matched this answer sheet. Hold it upright and retake.',
                recommended_fix:
                    'Retake the page with all four corners visible, flat, and unobstructed.',
                technical_details: {
                    phase_code: 'layout_verification',
                    capture_id: 75,
                    attempt_no: 3,
                    error_code: 'marker_missing',
                },
            },
        };
        const { container, getByRole } = render(OmrPhysicalTestMethodCard, {
            method: 'pre_shaded',
            methodData: failedMethod,
            onRetry,
        });

        const steps = container.querySelectorAll(
            'ol[aria-label="Capture analysis progress"] li',
        );
        expect(steps).toHaveLength(8);
        expect(steps[3].textContent).toContain('Verifying sheet layout');
        expect(steps[3].textContent).toContain('Failed');
        expect(steps[4].textContent).toContain('Waiting');
        expect(container.textContent).toContain(
            'No corner markers matched this answer sheet.',
        );
        expect(container.textContent).toContain('Why verification stopped');
        expect(container.textContent).toContain(
            'Corner markers were not found.',
        );
        expect(container.textContent).toContain('Engine observation');
        expect(container.textContent).toContain('Recommended fix');
        expect(container.textContent).toContain(
            'Retake the page with all four corners visible, flat, and unobstructed.',
        );
        expect(container.textContent).toContain('Technical details');

        await fireEvent.click(getByRole('button', { name: 'Retake page 1' }));
        expect(onRetry).toHaveBeenCalledWith({
            method: 'pre_shaded',
            pageNo: 1,
        });
    });
    it('does not render a run-wide failure checklist on a passed sibling method', () => {
        const { container, queryByText } = render(OmrPhysicalTestMethodCard, {
            method: 'hand_shaded',
            methodData: {
                ...methodData,
                method: 'hand_shaded',
                state: 'passed',
                run_status: 'failed',
                phase_detail: {
                    phase_code: 'failed',
                    failed_phase_code: 'layout_verification',
                },
                safe_error: 'The other method failed during analysis.',
            },
            phaseDetail: {
                phase_code: 'failed',
                failed_phase_code: 'layout_verification',
            },
        });

        expect(
            container.querySelector(
                'ol[aria-label="Capture analysis progress"]',
            ),
        ).toBeNull();
        expect(queryByText('Capture analysis stopped')).toBeNull();
        expect(queryByText('Analysis stopped')).toBeNull();
    });
    it('recovers a persisted zero-mismatch URL to the populated all-items view', async () => {
        const emptyMismatchPage = {
            method: 'hand_shaded',
            capture_id: 389,
            area: 'PART_1',
            total: 25,
            mismatch_total: 0,
            page: 1,
            per_page: 25,
            page_count: 1,
            filter: 'mismatches',
            groups: [],
            items: [],
            lazy_asset_urls: {},
        };
        const populatedPage = {
            ...emptyMismatchPage,
            filter: 'all',
            items: [
                {
                    id: 1,
                    item_id: 'PART_1_Q_001',
                    group_type: 'aptitude_area',
                    area_key: 'PART_1',
                    question_no: 1,
                    expected: 'A',
                    detected: 'A',
                    state: 'match',
                    options: [],
                },
            ],
        };
        const fetchMock = vi
            .fn()
            .mockResolvedValueOnce({
                ok: true,
                json: async () => emptyMismatchPage,
            })
            .mockResolvedValueOnce({
                ok: true,
                json: async () => populatedPage,
            });
        vi.stubGlobal('fetch', fetchMock);
        window.history.replaceState(
            {},
            '',
            '/setup/omr?evidence_method=hand_shaded&evidence_capture=389&evidence_area=PART_1&evidence_filter=mismatches',
        );

        const view = render(StudioExportVerifyPanel, {
            draft: { id: 13, stage: 'digital_verified' },
            verification_run: {
                run_id: 77,
                status: 'passed',
                methods: [],
            },
            urls: {},
        });

        await waitFor(() => expect(fetchMock).toHaveBeenCalledTimes(2));
        expect(String(fetchMock.mock.calls[0][0])).toContain('capture_id=389');
        expect(String(fetchMock.mock.calls[0][0])).toContain('filter=mismatch');
        expect(String(fetchMock.mock.calls[1][0])).toContain('filter=all');
        await waitFor(() =>
            expect(window.location.search).not.toContain('evidence_filter='),
        );
        expect(window.location.search).toContain('evidence_method=hand_shaded');
        expect(window.location.search).toContain('evidence_capture=389');
        expect(window.location.search).toContain('evidence_area=PART_1');
        expect(window.location.search).not.toContain('evidence_filter=');
    });
});
