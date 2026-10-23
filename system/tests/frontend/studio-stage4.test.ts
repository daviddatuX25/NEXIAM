// @vitest-environment jsdom

import { cleanup, fireEvent, render, waitFor } from '@testing-library/svelte';
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import OmrTemplateStudio from '../../resources/js/Pages/Setup/OmrConfiguration/Canonical/OmrTemplateStudio.svelte';
import StudioExportVerifyPanel from '../../resources/js/Pages/Setup/OmrConfiguration/Canonical/StudioExportVerifyPanel.svelte';

const draft = {
    id: 1,
    can_edit: false,
    lock_version: 0,
    marker_profile_id: 1,
    source_kind: 'image',
    stage: 'source_confirmed',
    authoring: {
        studio: {
            current_stage: 2,
            stage1Mode: 'upload',
            baseType: 'image',
            components: [],
            sections: [],
        },
    },
};

const markerProfiles = [
    { id: 1, code: 'target', version: 1, status: 'qualified' },
];
const verificationRun = { status: 'passed' };

function jsonResponse(payload: Record<string, unknown> = {}) {
    return { ok: true, json: async () => payload };
}

describe('Canonical Studio Stage 4 composition', () => {
    beforeEach(() => {
        vi.stubGlobal(
            'fetch',
            vi.fn(async () => jsonResponse({ lock_version: 0 })),
        );
    });

    afterEach(() => {
        cleanup();
        vi.unstubAllGlobals();
    });

    it('renders the three-step verification flow without serialized props', async () => {
        const { container, getByRole } = render(OmrTemplateStudio, {
            draft,
            marker_profiles: markerProfiles,
            aptitude_areas: [],
            urls: { studio: '/setup/omr/studio/1' },
            verification_run: verificationRun,
            physical_run_id: 7,
        });

        await fireEvent.click(
            getByRole('button', { name: /Verification & Test/ }),
        );

        await waitFor(() => {
            expect(
                container.querySelector('[data-omr-stage-panel="4"]'),
            ).not.toBeNull();
        });

        const stage4 = container.querySelector('[data-omr-stage-panel="4"]');
        const text = stage4?.textContent ?? '';
        expect(text).toContain('Digital engine check');
        expect(text).toContain('Physical tests');
        expect(text).toContain('Evidence review');
        expect(text).not.toContain('Qualification');
        expect(text).not.toContain('[object Object]');
        expect(stage4?.innerHTML ?? '').not.toContain('/>');
        expect(stage4?.innerHTML ?? '').not.toContain('marker_profile_status=');
    });
});

describe('StudioExportVerifyPanel status rail and focus panel', () => {
    afterEach(() => {
        cleanup();
        vi.unstubAllGlobals();
    });

    it('renders the ordered three-node status rail with semantic progress list', () => {
        const { container } = render(StudioExportVerifyPanel, {
            draft: { id: 1, stage: 'mapping_complete' },
            urls: { digital_verification: '/verify' },
        });

        const rail = container.querySelector(
            'ol[aria-label="Verification progress"]',
        );
        expect(rail).not.toBeNull();
        const items = rail?.querySelectorAll(':scope > li');
        expect(items?.length).toBe(3);

        const labels = Array.from(items ?? []).map((li) =>
            li.textContent?.trim(),
        );
        expect(labels[0]).toContain('Digital engine check');
        expect(labels[1]).toContain('Physical tests');
        expect(labels[2]).toContain('Evidence review');
        expect(labels.join(' ')).not.toContain('Qualification');
    });

    it('has completed, current, and upcoming visual semantics with aria-current="step"', () => {
        const { container } = render(StudioExportVerifyPanel, {
            draft: { id: 1, stage: 'digital_verified' },
            urls: {},
            verification_run: {
                run_id: 7,
                status: 'awaiting_print',
                mode: 'quick_rehearsal',
                available_actions: ['print_challenge'],
                challenge: {
                    challenge_id: 9,
                    challenge_code: 'CHAL-1',
                    mode: 'quick',
                    status: 'created',
                    print_url: '/print',
                    guide_url: '/guide',
                },
                evidence: [],
            },
        });

        const rail = container.querySelector(
            'ol[aria-label="Verification progress"]',
        );
        const items = rail?.querySelectorAll('li');
        expect(items).toBeDefined();
        if (!items) return;

        // Node 1 (Digital): completed (done)
        expect(items[0].getAttribute('aria-current')).toBeNull();
        expect(items[0].textContent).toContain('Done');
        // Node 2 (Physical challenge): current (active / awaiting_print)
        expect(items[1].getAttribute('aria-current')).toBe('step');
        expect(items[1].textContent).toContain('In progress');

        // Node 3, 4, 5: upcoming/blocked
        expect(items[2].getAttribute('aria-current')).toBeNull();
        expect(items[2].textContent).toContain('Blocked');
        expect(items[3].getAttribute('aria-current')).toBeNull();
        expect(items[3].textContent).toContain('Blocked');
        expect(items[4].getAttribute('aria-current')).toBeNull();
        expect(items[4].textContent).toContain('Blocked');
    });

    it('renders exactly ONE expanded focus panel for the derived current node', () => {
        const { container } = render(StudioExportVerifyPanel, {
            draft: { id: 1, stage: 'mapping_complete' },
            urls: { digital_verification: '/verify' },
        });

        const focusPanels = container.querySelectorAll(
            'section[aria-labelledby="current-step-heading"]',
        );
        expect(focusPanels.length).toBe(1);

        const heading = container.querySelector('#current-step-heading');
        expect(heading?.textContent?.trim()).toBe('Digital engine check');
    });

    it('failed digital run becomes current with retry action and no other step controls', () => {
        const { container, getByRole, queryByRole } = render(
            StudioExportVerifyPanel,
            {
                draft: { id: 1, stage: 'mapping_complete' },
                urls: { digital_verification: '/verify' },
                verification_run: {
                    run_id: 12,
                    status: 'failed',
                    mode: 'digital',
                    safe_issue: {
                        code: 'matrix_mismatch',
                        detail: 'Rotation variant proof failed recovery.',
                    },
                    evidence: [],
                },
            },
        );

        const currentLi = container.querySelector('li[aria-current="step"]');
        expect(currentLi?.textContent).toContain('Digital engine check');

        const focusPanel = container.querySelector(
            'section[aria-labelledby="current-step-heading"]',
        );
        expect(focusPanel?.textContent).toContain('Digital engine check');
        expect(focusPanel?.textContent).toContain(
            'Rotation variant proof failed recovery.',
        );

        const retryBtn = getByRole('button', {
            name: /Retry digital verification/,
        });
        expect(retryBtn).not.toBeNull();

        // Inactive step controls are NOT in the DOM
        expect(queryByRole('button', { name: /Create.*challenge/ })).toBeNull();
        expect(queryByRole('button', { name: /Mark printed/ })).toBeNull();
        expect(container.querySelector('#capture-page-no')).toBeNull();
        expect(
            queryByRole('button', { name: /Record measurements/ }),
        ).toBeNull();
    });

    it('quick awaiting_print focuses Physical challenge with printable links and disclaimer', () => {
        const { container, getByRole, queryByRole } = render(
            StudioExportVerifyPanel,
            {
                draft: { id: 1, stage: 'digital_verified' },
                urls: {},
                verification_run: {
                    run_id: 15,
                    status: 'awaiting_print',
                    mode: 'quick_rehearsal',
                    available_actions: ['print_challenge'],
                    challenge: {
                        challenge_id: 3,
                        challenge_code: 'CHAL-TEST-99',
                        mode: 'quick',
                        status: 'created',
                        print_url: '/runs/15/print',
                        guide_url: '/runs/15/guide',
                    },
                    evidence: [],
                },
            },
        );

        const currentLi = container.querySelector('li[aria-current="step"]');
        expect(currentLi?.textContent).toContain('Physical tests');

        const heading = container.querySelector('#current-step-heading');
        expect(heading?.textContent?.trim()).toBe('Physical tests');

        const sheetBtn = getByRole('button', {
            name: /Open print PDF|Printable challenge/i,
        });
        const guideBtn = getByRole('button', {
            name: /View guide|Separate shading guide/i,
        });
        const markPrintedBtn = getByRole('button', {
            name: /Mark printed & continue to capture/i,
        });
        expect(sheetBtn).not.toBeNull();
        expect(guideBtn).not.toBeNull();
        expect(markPrintedBtn).not.toBeNull();

        expect(container.textContent).toContain('Calibration-ID disclaimer');
        expect(container.querySelector('#capture-page-no')).toBeNull();
    });

    it('failed awaiting-print challenge shows Resume with existing sheet and guide, never Create', () => {
        const { container, getByRole, queryByRole } = render(
            StudioExportVerifyPanel,
            {
                draft: { id: 1, stage: 'digital_verified' },
                verification_run: {
                    run_id: 41,
                    status: 'failed',
                    mode: 'quick_rehearsal',
                    available_actions: ['retry'],
                    challenge: {
                        challenge_id: 4,
                        challenge_code: 'CHAL-FAILED-PRINT',
                        mode: 'quick',
                        status: 'created',
                        print_url: '/runs/41/sheet',
                        guide_url: '/runs/41/guide',
                    },
                    evidence: [],
                },
            },
        );

        expect(
            container.querySelector('li[aria-current="step"]')?.textContent,
        ).toContain('Physical tests');
        expect(
            container
                .querySelector('#current-step-heading')
                ?.textContent?.trim(),
        ).toBe('Physical tests');
        expect(
            getByRole('button', { name: /Resume challenge/ }),
        ).not.toBeNull();
        expect(
            getByRole('button', {
                name: /Open print PDF|Printable challenge/i,
            }),
        ).not.toBeNull();
        expect(
            getByRole('button', { name: /View guide|Separate shading guide/i }),
        ).not.toBeNull();
        expect(
            queryByRole('button', { name: /Create .*challenge/ }),
        ).toBeNull();
        expect(queryByRole('radio', { name: /Quick rehearsal/ })).toBeNull();
        expect(queryByRole('button', { name: /Mark printed/ })).toBeNull();
    });

    it('failed printed challenge shows Capture resume and keeps challenge links without Create controls', () => {
        const { container, getByRole, queryByRole } = render(
            StudioExportVerifyPanel,
            {
                draft: { id: 1, stage: 'compiled', compiled_version_id: 9 },
                marker_profile_status: 'qualified',
                verification_run: {
                    run_id: 42,
                    status: 'failed',
                    mode: 'full_qualification',
                    available_actions: ['retry'],
                    challenge: {
                        challenge_id: 5,
                        challenge_code: 'CHAL-FAILED-CAPTURE',
                        mode: 'full',
                        status: 'printed',
                        print_url: '/runs/42/sheet',
                        guide_url: '/runs/42/guide',
                    },
                    evidence: [],
                },
            },
        );

        expect(
            container.querySelector('li[aria-current="step"]')?.textContent,
        ).toContain('Physical tests');
        expect(
            container
                .querySelector('#current-step-heading')
                ?.textContent?.trim(),
        ).toBe('Physical tests');
        expect(getByRole('button', { name: /Resume capture/ })).not.toBeNull();
        expect(
            getByRole('button', {
                name: /Open print PDF|Printable challenge/i,
            }),
        ).not.toBeNull();
        expect(
            getByRole('button', { name: /View guide|Separate shading guide/i }),
        ).not.toBeNull();
        expect(
            queryByRole('button', { name: /Create .*challenge/ }),
        ).toBeNull();
        expect(queryByRole('radio', { name: /Quick rehearsal/ })).toBeNull();
        expect(queryByRole('button', { name: /Mark printed/ })).toBeNull();
    });

    it('retrying a failed print challenge preserves links and projects the 202 state before polling', async () => {
        const challengePayload = {
            run_id: 43,
            status: 'awaiting_print',
            attempt: 2,
            status_url: '/setup/omr/templates/verification-runs/43/status',
        };
        const { promise: statusPromise, resolve: resolveStatus } =
            Promise.withResolvers<{
                ok: boolean;
                json: () => Promise<unknown>;
            }>();
        const fetchMock = vi
            .fn()
            .mockResolvedValueOnce({
                ok: true,
                status: 202,
                json: async () => challengePayload,
            })
            .mockImplementation(() => statusPromise);
        vi.stubGlobal('fetch', fetchMock);
        const { container, getByRole, unmount } = render(
            StudioExportVerifyPanel,
            {
                draft: { id: 1, stage: 'digital_verified' },
                verification_run: {
                    run_id: 41,
                    status: 'failed',
                    mode: 'quick_rehearsal',
                    available_actions: ['retry'],
                    challenge: {
                        challenge_id: 4,
                        challenge_code: 'CHAL-FAILED-PRINT',
                        mode: 'quick',
                        status: 'created',
                        print_url: '/runs/41/sheet',
                        guide_url: '/runs/41/guide',
                    },
                    evidence: [],
                },
            },
        );

        await fireEvent.click(
            getByRole('button', { name: /Resume challenge/ }),
        );

        await waitFor(() => {
            expect(fetchMock).toHaveBeenCalledTimes(2);
            expect(container.textContent).toContain(
                'Existing challenge resumed',
            );
            expect(
                getByRole('button', {
                    name: /Open print PDF|Printable challenge/i,
                }),
            ).not.toBeNull();
            expect(
                getByRole('button', {
                    name: /View guide|Separate shading guide/i,
                }),
            ).not.toBeNull();
            expect(
                getByRole('button', {
                    name: /Mark printed & continue to capture/,
                }),
            ).not.toBeNull();
        });

        const [url, options] = fetchMock.mock.calls[0] as unknown as [
            string,
            RequestInit,
        ];
        expect(url).toBe('/setup/omr/templates/verification-runs/41/retry');
        expect(options.method).toBe('POST');
        expect(JSON.parse(options.body as string)).toEqual({});
        expect(
            container.querySelector('li[aria-current="step"]')?.textContent,
        ).toContain('Physical tests');

        resolveStatus({
            ok: true,
            json: async () => ({ ...challengePayload, status: 'passed' }),
        });
        await Promise.resolve();
        await Promise.resolve();
        unmount();
    });

    it('normalizes challenge_exists_resume_required into a resume instruction', async () => {
        const fetchMock = vi.fn(async () => ({
            ok: false,
            status: 422,
            json: async () => ({
                code: 'challenge_exists_resume_required',
                detail: 'An existing challenge is already persisted.',
            }),
        }));
        vi.stubGlobal('fetch', fetchMock);

        const { container, getByRole } = render(StudioExportVerifyPanel, {
            draft: { id: 1, stage: 'digital_verified' },
            verification_run: {
                run_id: 44,
                status: 'passed',
                mode: 'digital',
                evidence: [],
            },
        });

        await fireEvent.click(
            getByRole('button', { name: /Create quick rehearsal pack/ }),
        );

        await waitFor(() => {
            expect(container.textContent).toContain(
                'challenge_exists_resume_required',
            );
            expect(container.textContent).toContain('Resume challenge');
        });
    });

    it('awaiting_capture focuses Capture printed challenge with OmrCaptureInput', () => {
        const { container, getByLabelText, queryByRole } = render(
            StudioExportVerifyPanel,
            {
                draft: { id: 1, stage: 'digital_verified' },
                urls: {},
                verification_run: {
                    run_id: 18,
                    status: 'awaiting_capture',
                    mode: 'quick_rehearsal',
                    available_actions: ['submit_capture'],
                    challenge: {
                        challenge_id: 3,
                        challenge_code: 'CHAL-TEST-99',
                        mode: 'quick',
                        status: 'printed',
                        print_url: '/runs/18/print',
                        guide_url: '/runs/18/guide',
                    },
                    evidence: [],
                },
            },
        );

        const currentLi = container.querySelector('li[aria-current="step"]');
        expect(currentLi?.textContent).toContain('Physical tests');

        const heading = container.querySelector('#current-step-heading');
        expect(heading?.textContent?.trim()).toBe('Physical tests');

        const pageInput = getByLabelText(/Capture page/);
        expect(pageInput).not.toBeNull();

        expect(queryByRole('button', { name: /Mark printed/ })).toBeNull();
    });

    it('keeps needs-review capture decisions inside the evidence modal', () => {
        const evidenceItem = {
            capture_id: 1,
            attempt: 1,
            page_no: 1,
            variant: null,
            expected: 'pass',
            actual: 'fail',
            status: 'fail' as const,
            error_code: null,
            input_hash: 'a'.repeat(64),
            evidence_hash: 'b'.repeat(64),
            expected_vector_hash: null,
            detected_vector_hash: null,
            vector_match: false,
            captured_at: '2026-08-26T10:00:00+00:00',
            urls: { input: null, aligned: null, overlay: null },
            summary: {
                evidence: {
                    items: [
                        {
                            label: 'Q1',
                            value: 'A',
                            expected: 'A',
                            exact: true,
                            detected: { state: 'marked', selected_option: 'A' },
                        },
                        {
                            label: 'Q2',
                            value: 'B',
                            expected: 'C',
                            exact: false,
                            detected: {
                                state: 'ambiguous',
                                selected_option: 'B',
                            },
                        },
                    ],
                },
            },
        };

        const { container, getAllByRole, queryByRole } = render(
            StudioExportVerifyPanel,
            {
                draft: {
                    id: 1,
                    stage: 'compiled',
                    compiled_version_id: 5,
                    mapping_current: true,
                    digital_verification_current: true,
                },
                urls: {},
                verification_run: {
                    run_id: 22,
                    status: 'needs_review',
                    mode: 'full_qualification',
                    target: {
                        kind: 'compiled_version',
                        id: 5,
                        hashes: { artifact: 'target-artifact-hash' },
                    },
                    methods: [
                        {
                            target_kind: 'compiled_version',
                            target_id: 5,
                            target_hash: 'target-artifact-hash',
                            challenge_id: 10,
                            method: 'pre_shaded',
                            state: 'needs_review',
                            required_page_count: 1,
                            pages: [{ page_no: 1, selected_attempt_id: 1 }],
                            attempts: [evidenceItem],
                        },
                        {
                            method: 'hand_shaded',
                            target_kind: 'compiled_version',
                            target_id: 5,
                            target_hash: 'target-artifact-hash',
                            challenge_id: 11,
                            state: 'needs_review',
                            required_page_count: 1,
                            pages: [{ page_no: 1, selected_attempt_id: 2 }],
                            attempts: [
                                { ...evidenceItem, capture_id: 2, attempt: 2 },
                            ],
                        },
                    ],
                    evidence: [evidenceItem],
                },
            },
        );

        const currentLi = container.querySelector('li[aria-current="step"]');
        expect(currentLi?.textContent).toContain('Evidence review');

        const heading = container.querySelector('#current-step-heading');
        expect(heading?.textContent?.trim()).toBe('Evidence review');

        expect(container.textContent).toContain('Review required');
        expect(container.textContent).toContain('Not reviewed');
        expect(getAllByRole('button', { name: 'Review' })).toHaveLength(2);
        expect(
            queryByRole('button', { name: /Confirm exact recovery/ }),
        ).toBeNull();
        expect(queryByRole('button', { name: /Reject evidence/ })).toBeNull();
    });

    it('keeps passed full-run qualification controls out of the authoring flow', () => {
        const { container, queryByRole, queryByPlaceholderText } = render(
            StudioExportVerifyPanel,
            {
                draft: { id: 1, stage: 'compiled', compiled_version_id: 8 },
                urls: {},
                physical_run_id: 30,
                marker_profile_status: 'qualified',
                verification_run: {
                    run_id: 30,
                    status: 'passed',
                    mode: 'full_qualification',
                    target: { kind: 'compiled_version', id: 8, hashes: {} },
                    evidence: [],
                },
            },
        );

        expect(
            container.querySelectorAll(
                'ol[aria-label="Verification progress"] > li',
            ),
        ).toHaveLength(3);
        expect(container.textContent).not.toContain('Qualification');
        expect(queryByPlaceholderText('Printer identity')).toBeNull();
        expect(queryByPlaceholderText('Camera/scanner identity')).toBeNull();
        expect(
            queryByRole('button', {
                name: /Record measurements & derive qualification/,
            }),
        ).toBeNull();
    });

    it('keeps activation outside the authoring flow for qualified draft data', () => {
        const { container, queryByRole } = render(StudioExportVerifyPanel, {
            draft: {
                id: 1,
                stage: 'compiled',
                compiled_version_id: 8,
                physical_qualified: true,
            },
            urls: {},
            physical_run_id: 30,
            marker_profile_status: 'qualified',
            verification_run: {
                run_id: 30,
                status: 'passed',
                mode: 'full_qualification',
                target: { kind: 'compiled_version', id: 8, hashes: {} },
                evidence: [],
            },
        });

        expect(
            container.querySelectorAll(
                'ol[aria-label="Verification progress"] > li',
            ),
        ).toHaveLength(3);
        expect(container.textContent).not.toContain('Qualification');
        expect(
            queryByRole('button', { name: /Activate version 8/ }),
        ).toBeNull();
    });

    it('does not repeat instructions or controls for inactive steps on the rail', () => {
        const { container } = render(StudioExportVerifyPanel, {
            draft: { id: 1, stage: 'digital_verified' },
            urls: { digital_verification: '/verify' },
            verification_run: {
                run_id: 5,
                status: 'awaiting_print',
                mode: 'quick_rehearsal',
                available_actions: ['print_challenge'],
                challenge: {
                    challenge_id: 1,
                    challenge_code: 'CHAL-1',
                    mode: 'quick',
                    status: 'created',
                    print_url: '/print',
                    guide_url: '/guide',
                },
                evidence: [],
            },
        });

        const rail = container.querySelector(
            'ol[aria-label="Verification progress"]',
        );
        const railItems = rail?.querySelectorAll(':scope > li');
        expect(railItems?.length).toBe(3);

        // Rail items contain the row toggle button (aria-expanded) but no domain form controls or nested inputs
        railItems?.forEach((item) => {
            expect(item.querySelector('input')).toBeNull();
            expect(item.querySelector('select')).toBeNull();
            expect(item.querySelector('form')).toBeNull();
            expect(item.querySelector('button')).not.toBeNull();
            expect(
                item.querySelector('button')?.getAttribute('aria-expanded'),
            ).toBeDefined();
        });
    });

    it('allows clicking any step in the status rail to toggle its inline dropdown', async () => {
        const { container, getByRole } = render(StudioExportVerifyPanel, {
            draft: { id: 1, stage: 'digital_verified' },
            urls: { digital_verification: '/verify' },
            verification_run: {
                run_id: 5,
                status: 'awaiting_print',
                mode: 'quick_rehearsal',
                available_actions: ['print_challenge'],
                challenge: {
                    challenge_id: 1,
                    challenge_code: 'CHAL-1',
                    mode: 'quick',
                    status: 'created',
                    print_url: '/print',
                    guide_url: '/guide',
                },
                evidence: [],
            },
        });

        // By default, step 2 (Physical challenge) is active and its dropdown is open right below it
        const heading = container.querySelector('#current-step-heading');
        expect(heading?.textContent?.trim()).toBe('Physical tests');

        // Click Step 1 (Digital engine check) on the rail to open its dropdown directly below Step 1
        const digitalRowBtn = getByRole('button', {
            name: /Digital engine check/,
        });
        await fireEvent.click(digitalRowBtn);

        expect(
            container
                .querySelector('#current-step-heading')
                ?.textContent?.trim(),
        ).toBe('Digital engine check');

        // Toggle collapse all button on top of rail
        const collapseAllBtn = getByRole('button', { name: /Collapse all/ });
        await fireEvent.click(collapseAllBtn);
        expect(container.querySelector('#current-step-heading')).toBeNull();

        // Expand active again
        const expandActiveBtn = getByRole('button', { name: /Expand active/ });
        await fireEvent.click(expandActiveBtn);
        expect(
            container
                .querySelector('#current-step-heading')
                ?.textContent?.trim(),
        ).toBe('Physical tests');
    });

    it('shows animated progress bar and spinner during active digital verification', () => {
        const { container } = render(StudioExportVerifyPanel, {
            draft: { id: 1, stage: 'mapping_complete' },
            urls: { digital_verification: '/verify' },
            verification_run: {
                run_id: 8,
                status: 'variant_matrix',
                mode: 'digital',
                progress: { done: 6, total: 14, percent: 42.8 },
                phase_detail: {
                    stage: 'variant_matrix',
                    current_variant: 'blur',
                },
                evidence: [],
            },
        });

        expect(container.textContent).toContain(
            'Running digital verification…',
        );
        expect(container.textContent).toContain('6 / 14 variants');
        expect(container.textContent).toContain('Variant: blur');

        const progressBar = container.querySelector('[role="progressbar"]');
        expect(progressBar).not.toBeNull();
        expect(progressBar?.getAttribute('aria-valuenow')).toBe('6');
        expect(progressBar?.getAttribute('aria-valuemax')).toBe('14');
    });

    it('includes Return to Recognition Mapping button in sticky footer', async () => {
        const onBack = vi.fn();
        const { getByRole } = render(StudioExportVerifyPanel, {
            draft: { id: 1, stage: 'mapping_complete' },
            urls: { digital_verification: '/verify' },
            onBack,
        });

        const backBtn = getByRole('button', {
            name: /Return to Recognition Mapping/,
        });
        expect(backBtn).not.toBeNull();
        await fireEvent.click(backBtn);
        expect(onBack).toHaveBeenCalledOnce();
    });

    it('offers a non-mutating return to Exam materials when every checkpoint is done', () => {
        const { getByRole } = render(StudioExportVerifyPanel, {
            draft: {
                id: 1,
                stage: 'compiled',
                compiled_version_id: 8,
                physical_qualified: true,
                mapping_current: true,
                digital_verification_current: true,
            },
            urls: {},
            physical_run_id: 30,
            marker_profile_status: 'qualified',
            verification_run: {
                run_id: 30,
                status: 'passed',
                mode: 'full_qualification',
                target: {
                    kind: 'compiled_version',
                    id: 8,
                    hashes: { artifact: 'artifact-hash' },
                },
                methods: ['pre_shaded', 'hand_shaded'].map((method, index) => ({
                    method,
                    target_kind: 'compiled_version',
                    target_id: 8,
                    target_hash: 'artifact-hash',
                    challenge_id: index + 10,
                    state: 'passed',
                    pages: [
                        {
                            page_no: 1,
                            selected_attempt_id: index + 1,
                        },
                    ],
                    attempts: [
                        {
                            capture_id: index + 1,
                            page_no: 1,
                            state: 'passed',
                        },
                    ],
                })),
                evidence: [],
            },
        });

        const finishLink = getByRole('link', {
            name: /Finish & return to Exam materials/,
        });
        expect(new URL(finishLink.getAttribute('href') ?? '').pathname).toBe(
            '/setup/exam-materials',
        );
    });

    it('persisted passed summary + stage mapping_complete + qualified marker unlocks compile', () => {
        const { container, getByRole } = render(StudioExportVerifyPanel, {
            draft: { id: 1, stage: 'mapping_complete' },
            urls: { compile: '/setup/omr/drafts/1/compile' },
            marker_profile_status: 'qualified',
            verification_run: {
                run_id: 21,
                status: 'passed',
                mode: 'digital',
                result_summary: { matrix_hash: 'c9f952f5c3ed' },
                evidence: [],
            },
        });

        const items = container.querySelectorAll(
            'ol[aria-label="Verification progress"] > li',
        );
        expect(items[0].textContent).toContain('Done');
        expect(items[1].textContent).toContain('Ready');
        expect(items[1].getAttribute('aria-current')).toBe('step');

        const step2Btn = items[1].querySelector('button');
        expect((step2Btn as HTMLButtonElement).disabled).toBe(false);
        expect(step2Btn?.getAttribute('aria-expanded')).toBe('true');

        const compileBtn = getByRole('button', {
            name: /Compile immutable version/,
        });
        expect(compileBtn).not.toBeNull();
        expect((compileBtn as HTMLButtonElement).disabled).toBe(false);
    });

    it('digital passed + candidate marker profile unlocks Step 2 for quick rehearsal while gating compile and full challenge', async () => {
        const { container, getByRole } = render(StudioExportVerifyPanel, {
            draft: { id: 1, stage: 'mapping_complete' },
            urls: { compile: '/setup/omr/drafts/1/compile' },
            marker_profile_status: 'candidate',
            verification_run: {
                run_id: 21,
                status: 'passed',
                mode: 'digital',
                evidence: [],
            },
        });

        const items = container.querySelectorAll(
            'ol[aria-label="Verification progress"] > li',
        );
        expect(items[0].textContent).toContain('Done');
        expect(items[1].textContent).toContain('Ready');
        expect(items[1].getAttribute('aria-current')).toBe('step');

        const step2Btn = items[1].querySelector('button');
        expect((step2Btn as HTMLButtonElement).disabled).toBe(false);
        expect(step2Btn?.getAttribute('aria-expanded')).toBe('true');

        // Compile button is disabled with inline prerequisite visible
        const compileBtn = getByRole('button', {
            name: /Compile immutable version/,
        });
        expect(compileBtn).not.toBeNull();
        expect((compileBtn as HTMLButtonElement).disabled).toBe(true);
        expect(container.textContent).toContain(
            'Qualify the selected registration profile to compile or run a full challenge.',
        );

        // Quick rehearsal is enabled and checked
        const quickRadio = getByRole('radio', { name: /Quick rehearsal/ });
        expect((quickRadio as HTMLButtonElement).disabled).toBe(false);
        expect(quickRadio.getAttribute('aria-checked')).toBe('true');

        // Full challenge is disabled with single-column UI-kit styling
        const fullRadio = getByRole('radio', { name: /Full challenge/ });
        expect((fullRadio as HTMLButtonElement).disabled).toBe(true);
        expect(fullRadio.className).toContain('disabled:opacity-45');
        expect(fullRadio.className).toContain('disabled:cursor-not-allowed');
        expect(fullRadio.className).toContain('w-full text-left');
        // Create quick rehearsal pack button is enabled
        const createBtn = getByRole('button', {
            name: /Create quick rehearsal pack/,
        });
        expect(createBtn).not.toBeNull();
        expect((createBtn as HTMLButtonElement).disabled).toBe(false);

        // Collapsing and re-expanding Step 2 works (no workflow mutation)
        await fireEvent.click(step2Btn!);
        expect(step2Btn?.getAttribute('aria-expanded')).toBe('false');
        await fireEvent.click(step2Btn!);
        expect(step2Btn?.getAttribute('aria-expanded')).toBe('true');
    });

    it('posts the quick challenge payload and transitions Step 2 to awaiting_print with sheet and guide links', async () => {
        const challengePayload = {
            run_id: 42,
            status: 'awaiting_print',
            status_url: '/setup/omr/templates/verification-runs/42/status',
            challenge_id: 7,
            challenge_code: 'CHAL-QUICK-7',
            print_url: '/setup/omr/templates/verification-runs/42/print',
            guide_url: '/setup/omr/templates/verification-runs/42/guide',
        };
        const { promise: statusPromise, resolve: resolveStatus } =
            Promise.withResolvers<{
                ok: boolean;
                status: number;
                json: () => Promise<unknown>;
            }>();
        const fetchMock = vi
            .fn()
            .mockResolvedValueOnce({
                ok: true,
                status: 202,
                json: async () => challengePayload,
            })
            // Leave the status request unresolved so this assertion covers the immediate projection.
            .mockImplementation(() => statusPromise);
        vi.stubGlobal('fetch', fetchMock);

        const { container, getByRole, unmount } = render(
            StudioExportVerifyPanel,
            {
                draft: { id: 1, stage: 'mapping_complete' },
                marker_profile_status: 'candidate',
                verification_run: {
                    run_id: 21,
                    status: 'passed',
                    mode: 'digital',
                    evidence: [],
                },
            },
        );

        await fireEvent.click(
            getByRole('button', { name: /Create quick rehearsal pack/ }),
        );

        await waitFor(() => {
            const currentLi = container.querySelector(
                'li[aria-current="step"]',
            );
            expect(currentLi?.textContent).toContain('Physical tests');
            expect(currentLi?.textContent).toContain('In progress');
            expect(
                getByRole('button', {
                    name: /Open print PDF|Printable challenge/i,
                }),
            ).not.toBeNull();
            expect(
                getByRole('button', {
                    name: /View guide|Separate shading guide/i,
                }),
            ).not.toBeNull();
        });

        const [url, options] = fetchMock.mock.calls[0] as unknown as [
            string,
            RequestInit,
        ];
        expect(url).toBe('/setup/omr/templates/canonical/drafts/1/challenge');
        expect(options.method).toBe('POST');
        expect(JSON.parse(options.body as string)).toMatchObject({
            mode: 'quick',
            idempotency_key: expect.any(String),
        });

        resolveStatus({
            ok: true,
            status: 200,
            json: async () => ({ ...challengePayload, status: 'passed' }),
        });
        await Promise.resolve();
        await Promise.resolve();
        unmount();
    });

    it('shows typed challenge errors once and keeps Step 2 Ready after a 422', async () => {
        const detail =
            'A passed digital verification run matching this artifact is required.';
        const fetchMock = vi.fn(async () => ({
            ok: false,
            status: 422,
            json: async () => ({ code: 'digital_pass_required', detail }),
        }));
        vi.stubGlobal('fetch', fetchMock);

        const { container, getByRole } = render(StudioExportVerifyPanel, {
            draft: { id: 1, stage: 'mapping_complete' },
            marker_profile_status: 'candidate',
            verification_run: {
                run_id: 21,
                status: 'passed',
                mode: 'digital',
                evidence: [],
            },
        });

        await fireEvent.click(
            getByRole('button', { name: /Create quick rehearsal pack/ }),
        );

        await waitFor(() => {
            expect(container.textContent).toContain('digital_pass_required');
            expect(container.textContent).toContain(detail);
        });

        const text = container.textContent ?? '';
        expect(text.match(/digital_pass_required/g)).toHaveLength(1);
        expect(
            text.match(
                /A passed digital verification run matching this artifact is required\./g,
            ),
        ).toHaveLength(1);
        const currentLi = container.querySelector('li[aria-current="step"]');
        expect(currentLi?.textContent).toContain('Physical tests');
        expect(currentLi?.textContent).toContain('Ready');
        expect(
            getByRole('button', { name: /Create quick rehearsal pack/ }),
        ).not.toBeNull();
        expect(text).not.toContain('[object Object]');
        expect(text).not.toContain('readyState');
    });

    it('uses a readable fallback for Event-like challenge request failures', async () => {
        const fetchMock = vi.fn(async () => {
            throw { readyState: 4, status: 0 };
        });
        vi.stubGlobal('fetch', fetchMock);

        const { container, getByRole } = render(StudioExportVerifyPanel, {
            draft: { id: 1, stage: 'mapping_complete' },
            marker_profile_status: 'candidate',
            verification_run: {
                run_id: 21,
                status: 'passed',
                mode: 'digital',
                evidence: [],
            },
        });

        await fireEvent.click(
            getByRole('button', { name: /Create quick rehearsal pack/ }),
        );

        await waitFor(() => {
            expect(container.textContent).toContain('challenge_failed');
            expect(container.textContent).toContain(
                'The challenge pack could not be created.',
            );
        });

        const text = container.textContent ?? '';
        expect(text).not.toContain('[object Object]');
        expect(text).not.toContain('readyState');
    });

    it('qualified marker profile with compiled draft enables full challenge selection', () => {
        const { getByRole } = render(StudioExportVerifyPanel, {
            draft: { id: 1, stage: 'compiled', compiled_version_id: 10 },
            marker_profile_status: 'qualified',
            verification_run: {
                run_id: 25,
                status: 'passed',
                mode: 'digital',
                evidence: [],
            },
        });

        const fullRadio = getByRole('radio', { name: /Full challenge/ });
        expect((fullRadio as HTMLButtonElement).disabled).toBe(false);
    });
    it('stale boolean/no summary stays Digital ready and suppresses compile', () => {
        const { container, getByRole, queryByRole } = render(
            StudioExportVerifyPanel,
            {
                draft: {
                    id: 1,
                    stage: 'digital_verified',
                    digital_verified: true,
                },
                urls: { digital_verification: '/verify', compile: '/compile' },
                verification_run: null,
            },
        );

        const items = container.querySelectorAll(
            'ol[aria-label="Verification progress"] > li',
        );
        expect(items[0].textContent).toContain('Ready');
        expect(items[0].getAttribute('aria-current')).toBe('step');
        expect(items[1].textContent).toContain('Blocked');

        const verifyBtn = getByRole('button', {
            name: /Run digital verification/,
        });
        expect(verifyBtn).not.toBeNull();
        expect(
            queryByRole('button', { name: /Compile immutable version/ }),
        ).toBeNull();
    });

    it('failed summary blocks compile even with digital_verified stage and boolean', () => {
        const { container, getByRole, queryByRole } = render(
            StudioExportVerifyPanel,
            {
                draft: {
                    id: 1,
                    stage: 'digital_verified',
                    digital_verified: true,
                },
                urls: { digital_verification: '/verify', compile: '/compile' },
                verification_run: {
                    run_id: 22,
                    status: 'failed',
                    mode: 'digital',
                    safe_issue: {
                        code: 'nogo_matrix_fail',
                        detail: 'Rotation variant proof failed recovery',
                    },
                    evidence: [],
                },
            },
        );

        const items = container.querySelectorAll(
            'ol[aria-label="Verification progress"] > li',
        );
        expect(items[0].textContent).toContain('In progress');
        expect(items[0].textContent).toContain(
            'Rotation variant proof failed recovery',
        );
        expect(items[0].getAttribute('aria-current')).toBe('step');
        expect(items[1].textContent).toContain('Blocked');

        expect(
            getByRole('button', { name: /Retry digital verification/ }),
        ).not.toBeNull();
        expect(
            queryByRole('button', { name: /Compile immutable version/ }),
        ).toBeNull();
    });

    it('hydration spinner suppresses compile controls until full status is loaded', async () => {
        const summaryRun = {
            projection: 'summary',
            run_id: 33,
            mode: 'digital',
            status: 'passed',
            status_url: '/runs/33/status',
        };

        const { promise, resolve } = Promise.withResolvers<{
            ok: boolean;
            json: () => Promise<unknown>;
        }>();
        const fetchMock = vi.fn().mockReturnValueOnce(promise);
        vi.stubGlobal('fetch', fetchMock);

        const { container, queryByRole, getByRole } = render(
            StudioExportVerifyPanel,
            {
                draft: { id: 1, stage: 'mapping_complete' },
                urls: { compile: '/compile' },
                marker_profile_status: 'qualified',
                verification_run: summaryRun,
            },
        );

        expect(container.textContent).toContain(
            'Restoring verification status…',
        );
        expect(
            queryByRole('button', { name: /Compile immutable version/ }),
        ).toBeNull();

        resolve({
            ok: true,
            json: async () => ({
                run_id: 33,
                mode: 'digital',
                status: 'passed',
                attempt: 1,
            }),
        });

        await waitFor(() => {
            expect(container.textContent).not.toContain(
                'Restoring verification status…',
            );
        });

        const compileBtn = getByRole('button', {
            name: /Compile immutable version/,
        });
        expect(compileBtn).not.toBeNull();
    });

    it('on typed compile_gate_missing, refreshes summary and focuses Digital with recovery copy', async () => {
        const fetchMock = vi.fn().mockImplementation(async (url: string) => {
            if (url.includes('/compile')) {
                return {
                    ok: false,
                    status: 422,
                    json: async () => ({
                        code: 'compile_gate_missing',
                        detail: 'Digital verification is missing or stale for this artifact set.',
                    }),
                };
            }
            if (url.includes('/status') || url.includes('/verification-runs')) {
                return {
                    ok: true,
                    json: async () => ({
                        run_id: 25,
                        mode: 'digital',
                        status: 'failed',
                        safe_issue: {
                            code: 'compile_gate_missing',
                            detail: 'Digital verification required.',
                        },
                    }),
                };
            }
            return { ok: true, json: async () => ({}) };
        });
        vi.stubGlobal('fetch', fetchMock);

        const { container, getByRole } = render(StudioExportVerifyPanel, {
            draft: { id: 1, stage: 'mapping_complete' },
            urls: { compile: '/compile', digital_verification: '/verify' },
            marker_profile_status: 'qualified',
            verification_run: {
                run_id: 25,
                status: 'passed',
                mode: 'digital',
                evidence: [],
            },
        });

        const compileBtn = getByRole('button', {
            name: /Compile immutable version/,
        });
        await fireEvent.click(compileBtn);

        await waitFor(() => {
            expect(container.textContent).toContain('compile_gate_missing');
        });

        const currentHeading = container.querySelector('#current-step-heading');
        expect(currentHeading?.textContent?.trim()).toBe(
            'Digital engine check',
        );
        expect(container.textContent).toContain(
            'Digital verification is missing or stale',
        );
    });
});
