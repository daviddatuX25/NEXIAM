// @vitest-environment jsdom

import { cleanup, fireEvent, render, waitFor } from '@testing-library/svelte';
import {
    afterEach,
    beforeEach,
    describe,
    expect,
    it,
    vi,
    type Mock,
} from 'vitest';
import OmrTemplateStudio from '../../resources/js/Pages/Setup/OmrConfiguration/Canonical/OmrTemplateStudio.svelte';
import StudioCanvas from '../../resources/js/Pages/Setup/OmrConfiguration/Canonical/StudioCanvas.svelte';
import StudioBubblePanel from '../../resources/js/Pages/Setup/OmrConfiguration/Canonical/StudioBubblePanel.svelte';
import StudioExportVerifyPanel from '../../resources/js/Pages/Setup/OmrConfiguration/Canonical/StudioExportVerifyPanel.svelte';
const markerProfiles = [
    { id: 1, code: 'target', version: 1, status: 'qualified' },
];

const samplePersistedPreview = {
    source: 'persisted',
    artifact: {
        canonical_url: '/storage/omr/canonical-preview-123.png',
        artifact_hash: 'hash-abc-789',
        paper: { width_mm: 210, height_mm: 297 },
        insets_mm: { top: 12, right: 12, bottom: 14, left: 12 },
    },
    aligned_url: null,
    overlay_url: null,
    mapping_guides: [],
    diagnostics_available: false,
};

describe('Canonical Studio refresh hydration and print readiness', () => {
    let fetchMock: Mock;

    beforeEach(() => {
        fetchMock = vi.fn(async () => ({
            ok: true,
            json: async () => ({ lock_version: 1 }),
        }));
        vi.stubGlobal('fetch', fetchMock);
    });

    afterEach(() => {
        cleanup();
        vi.unstubAllGlobals();
    });

    it('restores Stage 3 directly from persisted preview with zero network requests and mounts raster', async () => {
        const draft = {
            id: 1,
            can_edit: true,
            lock_version: 0,
            marker_profile_id: 1,
            source_kind: 'image',
            stage: 'source_confirmed',
            persisted_preview: samplePersistedPreview,
            authoring: {
                studio: {
                    current_stage: 3,
                    stage1Mode: 'upload',
                    baseType: 'image',
                    components: [],
                    sections: [
                        {
                            id: 'Section_A',
                            name: 'Section A',
                            type: 'mcq',
                            startQ: 1,
                            questionsCount: 20,
                            posX: 25,
                            posY: 72,
                            w_mm: 75,
                            h_mm: 140,
                            bubblePitchMm: 12.0,
                            rowPitchMm: 6.5,
                            isLocked: false,
                        },
                    ],
                },
            },
        };

        const { container, getByRole, getByAltText } = render(
            OmrTemplateStudio,
            {
                draft,
                marker_profiles: markerProfiles,
                aptitude_areas: [],
                urls: {
                    studio: '/setup/omr/studio/1',
                    preview: '/setup/omr/studio/1/preview',
                    mapping: '/setup/omr/studio/1/mapping',
                },
            },
        );

        // Zero mutation or preview requests on refresh/mount
        expect(fetchMock).not.toHaveBeenCalled();

        // Stage 3 panel is restored
        expect(
            container.querySelector('[data-omr-stage-panel="3"]'),
        ).not.toBeNull();

        // Raster canonical image is mounted directly in the canvas
        const rasterImg = getByAltText('Server-rendered answer sheet');
        expect(rasterImg).not.toBeNull();
        expect(rasterImg.getAttribute('src')).toBe(
            '/storage/omr/canonical-preview-123.png',
        );

        // Print Sheet is available immediately
        const printSheetBtn = getByRole('button', { name: 'Print Sheet' });
        expect(printSheetBtn).not.toBeNull();
        expect((printSheetBtn as HTMLButtonElement).disabled).toBe(false);

        // Aligned Check and Recognition Overlay are disabled when URLs are null
        const alignedBtn = getByRole('button', { name: 'Aligned Check' });
        expect((alignedBtn as HTMLButtonElement).disabled).toBe(true);

        const overlayBtn = getByRole('button', { name: 'Recognition Overlay' });
        expect((overlayBtn as HTMLButtonElement).disabled).toBe(true);

    });

    it('restores Stage 3 with no persisted preview and offers Continue to Verification', async () => {
        const draft = {
            id: 2,
            can_edit: true,
            lock_version: 0,
            marker_profile_id: 1,
            source_kind: 'image',
            stage: 'source_confirmed',
            raster_url: '/storage/omr/draft2-raster.png',
            persisted_preview: null,
            authoring: {
                pages: [{ page_no: 1, asset_id: 10 }],
                studio: {
                    current_stage: 3,
                    stage1Mode: 'upload',
                    baseType: 'image',
                    components: [],
                    sections: [
                        {
                            id: 'Section_A',
                            name: 'Section A',
                            type: 'mcq',
                            startQ: 1,
                            questionsCount: 20,
                            posX: 25,
                            posY: 72,
                            w_mm: 75,
                            h_mm: 140,
                            bubblePitchMm: 12.0,
                            rowPitchMm: 6.5,
                            isLocked: false,
                        },
                    ],
                },
            },
        };

        const { container, getByRole, queryByAltText } = render(
            OmrTemplateStudio,
            {
                draft,
                marker_profiles: markerProfiles,
                aptitude_areas: [],
                urls: {
                    studio: '/setup/omr/studio/2',
                    preview: '/setup/omr/studio/2/preview',
                    mapping: '/setup/omr/studio/2/mapping',
                },
            },
        );

        // Zero requests on initial mount
        expect(fetchMock).not.toHaveBeenCalled();

        // Stage 3 panel is restored
        expect(
            container.querySelector('[data-omr-stage-panel="3"]'),
        ).not.toBeNull();

        // Recognition Mapping displays the uploaded source raster with zero misleading preview error banners
        expect(
            queryByAltText('Uploaded source sheet for recognition mapping'),
        ).not.toBeNull();
        expect(queryByAltText('Server-rendered answer sheet')).toBeNull();
        expect(queryByAltText('Composed Page')).toBeNull();
        expect(
            container.querySelector('iframe[title="HTML Source Preview"]'),
        ).toBeNull();
        expect(container.textContent).not.toContain(
            'Persisted preview unavailable.',
        );
        expect(container.textContent).not.toContain(
            'Candidate preview unavailable.',
        );
        // The forward action owns preview validation and the stage transition.
        const continueBtn = getByRole('button', {
            name: 'Continue to Verification',
        });
        expect(continueBtn).not.toBeNull();
        expect((continueBtn as HTMLButtonElement).disabled).toBe(false);
        expect(container.textContent).not.toContain('Run recognition test');
        expect(container.textContent).not.toMatch(/300\s*-?\s*PPI/i);
    });

    it('shows Candidate preview unavailable fallback in Stage 4 if candidate preview is missing', async () => {
        const draft = {
            id: 2,
            can_edit: true,
            lock_version: 0,
            marker_profile_id: 1,
            source_kind: 'image',
            stage: 'page_prepared',
            raster_url: '/storage/omr/draft2-raster.png',
            persisted_preview: null,
            authoring: {
                pages: [{ page_no: 1, asset_id: 10 }],
                studio: {
                    current_stage: 4,
                    stage1Mode: 'upload',
                    baseType: 'image',
                    components: [],
                    sections: [
                        {
                            id: 'Section_A',
                            name: 'Section A',
                            type: 'mcq',
                            startQ: 1,
                            questionsCount: 20,
                            posX: 25,
                             posY: 72,
                             w_mm: 75,
                             h_mm: 140,
                             bubblePitchMm: 12.0,
                             rowPitchMm: 6.5,
                             isLocked: false,
                         },
                     ],
                 },
             },
         };

        const { container } = render(OmrTemplateStudio, {
            draft,
            marker_profiles: markerProfiles,
            aptitude_areas: [],
            urls: {
                studio: '/setup/omr/studio/2',
                preview: '/setup/omr/studio/2/preview',
                mapping: '/setup/omr/studio/2/mapping',
            },
        });

        expect(container.textContent).toContain('Candidate preview unavailable.');
        expect(container.textContent).toContain(
            'Return to Recognition Mapping, then continue to Verification to prepare the candidate preview.',
        );
    });

    it('saves and validates the candidate before advancing to Verification', async () => {
        const fullPreviewResponse = {
            source: 'generated',
            artifact: {
                canonical_url: '/storage/omr/fresh-canonical.png',
                artifact_hash: 'fresh-hash-456',
                paper: { width_mm: 210, height_mm: 297 },
                insets_mm: { top: 12, right: 12, bottom: 14, left: 12 },
            },
            aligned_url: '/storage/omr/fresh-aligned.png',
            overlay_url: '/storage/omr/fresh-overlay.png',
            mapping_guides: [],
            diagnostics_available: true,
            lock_version: 1,
        };

        fetchMock.mockImplementation(async (url) => {
            const urlStr = String(url);
            if (urlStr.includes('/preview')) {
                return { ok: true, json: async () => fullPreviewResponse };
            }
            return {
                ok: true,
                json: async () => ({ lock_version: 1, stage: 'page_prepared' }),
            };
        });

        const draft = {
            id: 3,
            can_edit: true,
            lock_version: 0,
            marker_profile_id: 1,
            source_kind: 'image',
            stage: 'source_confirmed',
            persisted_preview: null,
            authoring: {
                studio: {
                    current_stage: 3,
                    stage1Mode: 'upload',
                    baseType: 'image',
                    components: [],
                    sections: [
                        {
                            id: 'Section_A',
                            name: 'Section A',
                            type: 'mcq',
                            startQ: 1,
                            questionsCount: 20,
                            posX: 25,
                            posY: 72,
                            w_mm: 75,
                            h_mm: 140,
                            bubblePitchMm: 12.0,
                            rowPitchMm: 6.5,
                            isLocked: false,
                        },
                    ],
                },
            },
        };

        const { getByRole, container } = render(OmrTemplateStudio, {
            draft,
            marker_profiles: markerProfiles,
            aptitude_areas: [],
            urls: {
                studio: '/setup/omr/studio/3',
                preview: '/setup/omr/studio/3/preview',
                mapping: '/setup/omr/studio/3/mapping',
            },
        });

        // Mount remains read-only; the explicit forward action owns the work.
        expect(fetchMock).not.toHaveBeenCalled();
        const continueBtn = getByRole('button', {
            name: 'Continue to Verification',
        });
        await fireEvent.click(continueBtn);

        await waitFor(() => {
            expect(
                container.querySelector('[data-omr-stage-panel="4"]'),
            ).not.toBeNull();
        });

        await waitFor(() => {
            expect(
                fetchMock.mock.calls.some(([url]) =>
                    String(url).includes('/preview'),
                ),
            ).toBe(true);
        });

        const calls = fetchMock.mock.calls;
        const saveIndex = calls.findIndex(([url]) =>
            String(url).includes('/setup/omr/studio/3'),
        );
        const previewIndex = calls.findIndex(([url]) =>
            String(url).includes('/preview'),
        );
        expect(saveIndex).toBeGreaterThanOrEqual(0);
        expect(previewIndex).toBeGreaterThan(saveIndex);
        expect(calls[saveIndex]?.[1]?.method).toBe('PUT');
        expect(calls[previewIndex]?.[1]?.method).toBe('POST');
        expect(container.textContent).toContain('Digital engine check');
        expect(container.textContent).toContain('Physical tests');
        expect(
            calls.some(([url]) => String(url).includes('verification-runs')),
        ).toBe(false);
    });

    it('keeps the mapping stage and surfaces a 422 save conflict without previewing', async () => {
        fetchMock.mockImplementation(async (url) => {
            if (String(url).includes('/setup/omr/studio/5')) {
                return {
                    ok: false,
                    status: 422,
                    json: async () => ({
                        code: 'concurrency_conflict',
                        detail: 'Draft changed since it was loaded.',
                    }),
                };
            }

            return { ok: true, json: async () => ({ lock_version: 1 }) };
        });

        const draft = {
            id: 5,
            can_edit: true,
            lock_version: 0,
            marker_profile_id: 1,
            source_kind: 'image',
            stage: 'source_confirmed',
            persisted_preview: null,
            authoring: {
                studio: {
                    current_stage: 3,
                    stage1Mode: 'upload',
                    baseType: 'image',
                    components: [],
                    sections: [
                        {
                            id: 'Section_A',
                            name: 'Section A',
                            type: 'mcq',
                            startQ: 1,
                            questionsCount: 20,
                            posX: 25,
                            posY: 72,
                            w_mm: 75,
                            h_mm: 140,
                            bubblePitchMm: 12.0,
                            rowPitchMm: 6.5,
                            isLocked: false,
                        },
                    ],
                },
            },
        };

        const { container, getByRole } = render(OmrTemplateStudio, {
            draft,
            marker_profiles: markerProfiles,
            aptitude_areas: [],
            urls: {
                studio: '/setup/omr/studio/5',
                preview: '/setup/omr/studio/5/preview',
                mapping: '/setup/omr/studio/5/mapping',
            },
        });

        await fireEvent.click(
            getByRole('button', { name: 'Continue to Verification' }),
        );

        await waitFor(() => {
            expect(container.textContent).toContain(
                'Draft changed since it was loaded.',
            );
        });

        await waitFor(() => {
            expect(
                container.querySelector('[data-omr-stage-panel="3"]'),
            ).not.toBeNull();
        });
        expect(
            fetchMock.mock.calls.some(([url]) =>
                String(url).includes('/preview'),
            ),
        ).toBe(false);
        expect(container.textContent).toContain(
            'Resolve this issue before continuing.',
        );
    });


    it('keeps preview diagnostics distinct from the Verification action', async () => {
        let previewSurface = 'canonical';
        const onNextMock = vi.fn();

        const { getByRole, container, rerender } = render(StudioBubblePanel, {
            sections: [
                {
                    id: 'Section_A',
                    name: 'Section A',
                    type: 'mcq',
                    questionsCount: 20,
                    startQ: 1,
                    posX: 25,
                    posY: 72,
                    w_mm: 75,
                    h_mm: 140,
                    bubblePitchMm: 12.0,
                    rowPitchMm: 6.5,
                    isLocked: false,
                },
            ],
            activeSectionIndex: 0,
            markerProfiles,
            selectedMarkerProfileId: 1,
            canonicalPreview: samplePersistedPreview,
            previewSurface,
            onNext: onNextMock,
        });

        expect(
            (getByRole('button', { name: 'Print Sheet' }) as HTMLButtonElement)
                .disabled,
        ).toBe(false);
        expect(
            (
                getByRole('button', {
                    name: 'Aligned Check',
                }) as HTMLButtonElement
            ).disabled,
        ).toBe(true);
        expect(
            (
                getByRole('button', {
                    name: 'Recognition Overlay',
                }) as HTMLButtonElement
            ).disabled,
        ).toBe(true);

        expect(container.textContent).toContain(
            'Aligned and overlay diagnostics are unavailable for this preview.',
        );
        expect(container.textContent).not.toContain('verified');

        await fireEvent.click(
            getByRole('button', { name: 'Continue to Verification' }),
        );
        expect(onNextMock).toHaveBeenCalledTimes(1);

        const fullPreview = {
            ...samplePersistedPreview,
            aligned_url: '/storage/omr/aligned.png',
            overlay_url: '/storage/omr/overlay.png',
            diagnostics_available: true,
        };

        await rerender({
            canonicalPreview: fullPreview,
            previewSurface: 'canonical',
            sections: [
                {
                    id: 'Section_A',
                    name: 'Section A',
                    type: 'mcq',
                    questionsCount: 20,
                    startQ: 1,
                    posX: 25,
                    posY: 72,
                    w_mm: 75,
                    h_mm: 140,
                    bubblePitchMm: 12.0,
                    rowPitchMm: 6.5,
                    isLocked: false,
                },
            ],
            activeSectionIndex: 0,
            markerProfiles,
            selectedMarkerProfileId: 1,
            onNext: onNextMock,
        });

        expect(
            (getByRole('button', { name: 'Aligned Check' }) as HTMLButtonElement)
                .disabled,
        ).toBe(false);
        expect(
            (
                getByRole('button', {
                    name: 'Recognition Overlay',
                }) as HTMLButtonElement
            ).disabled,
        ).toBe(false);
        expect(container.textContent).not.toContain('Mapped geometry preview passed.');
    });

    it('restores Stage 4 directly without mount requests', async () => {
        const draft = {
            id: 4,
            can_edit: true,
            lock_version: 0,
            marker_profile_id: 1,
            source_kind: 'image',
            stage: 'digital_verified',
            digital_verified: true,
            digital_verification_current: true,
            target_current: true,
            persisted_preview: samplePersistedPreview,
            authoring: {
                studio: {
                    current_stage: 4,
                    stage1Mode: 'upload',
                    baseType: 'image',
                    components: [],
                    sections: [],
                },
            },
        };

        const { container, getByRole } = render(OmrTemplateStudio, {
            draft,
            marker_profiles: markerProfiles,
            aptitude_areas: [],
            urls: {
                studio: '/setup/omr/studio/4',
                preview: '/setup/omr/studio/4/preview',
            },
            verification_run: {
                run_id: 30,
                status: 'awaiting_print',
                mode: 'quick_rehearsal',
                target_current: true,
                available_actions: ['create_challenge'],
                methods: [
                    {
                        method: 'pre_shaded',
                        state: 'not_started',
                        pages: [],
                        attempts: [],
                    },
                ],
                evidence: [],
            },
        });

        // Zero requests on mount
        expect(fetchMock).not.toHaveBeenCalled();

        // Stage 4 panel is rendered directly
        expect(
            container.querySelector('[data-omr-stage-panel="4"]'),
        ).not.toBeNull();
        expect(
            getByRole('button', { name: 'Create pre-shaded test sheet' }),
        ).not.toBeNull();
    });

    it('StudioCanvas uses "Preparing print sheet" and contains no visible 300-PPI copy', () => {
        const { container } = render(StudioCanvas, {
            scale: 1,
            panX: 0,
            panY: 0,
            marginMm: 15,
            markerPattern: 'target',
            canvasLocked: false,
            rasterUrl: null,
            htmlContent: '',
            canonicalPreview: samplePersistedPreview,
            mappingPreviewStale: false,
            isHtmlMode: false,
            previewSurface: 'canonical',
            showCanonicalPrintPreview: false,
            isComposing: true,
            showHeaderOverlay: false,
            headerTitle: 'EXAM',
            headerSubtitle: 'FORM',
            headerFontSize: 14,
            headerFontFamily: 'Inter, sans-serif',
            headerColor: '#0f172a',
            headerAlign: 'left',
            headerX: 25,
            headerY: 20,
            headerW: 160,
            headerH: 15,
            headerLocked: false,
            showQrOverlay: false,
            qrX: 162,
            qrY: 18,
            qrW: 26,
            qrH: 26,
            qrLocked: false,
            showInstructionsOverlay: false,
            instructionsContent: '',
            instructionsFontSize: 8,
            instructionsFontFamily: 'Inter, sans-serif',
            instructionsColor: '#0f172a',
            instX: 25,
            instY: 38,
            instW: 160,
            instH: 14,
            instLocked: false,
            showSignatureOverlay: false,
            signatureContent: '',
            signatureFontSize: 8,
            signatureFontFamily: 'Inter, sans-serif',
            signatureColor: '#0f172a',
            sigX: 120,
            sigY: 255,
            sigW: 65,
            sigH: 22,
            sigLocked: false,
            showRollOverlay: false,
            rollX: 25,
            rollY: 55,
            rollDigits: 7,
            sections: [],
            activeSectionIndex: 0,
            components: [],
            selectedComponentId: null,
            selectedComponentIds: [],
            selectedCanvasIds: [],
            currentStage: 3,
        });

        expect(container.textContent).toContain('Preparing print sheet');
        expect(container.textContent).not.toMatch(/300\s*-?\s*PPI/i);
    });
});

describe('Stage 4 verification summary refresh hydration', () => {
    let fetchMock: Mock;

    beforeEach(() => {
        fetchMock = vi.fn();
        vi.stubGlobal('fetch', fetchMock);
    });

    afterEach(() => {
        cleanup();
        vi.unstubAllGlobals();
    });

    it('issues only GET status with zero POST/PUT requests on refresh', async () => {
        const summaryRun = {
            projection: 'summary',
            run_id: 10,
            mode: 'digital',
            status: 'passed',
            status_url: '/setup/omr/templates/verification-runs/10/status',
            attempt: 1,
        };

        fetchMock.mockResolvedValueOnce({
            ok: true,
            json: async () => ({
                run_id: 10,
                mode: 'digital',
                status: 'passed',
                attempt: 1,
                result_summary: { matrix_hash: 'abc123456789' },
            }),
        });

        const { container } = render(StudioExportVerifyPanel, {
            draft: { stage: 'mapping_complete' },
            verification_run: summaryRun,
            urls: { digital_verification: '/setup/omr/drafts/1/verify' },
        });

        // Hydration spinner is displayed on initial render
        expect(container.textContent).toContain(
            'Restoring verification status…',
        );

        // Wait for hydration to complete
        await waitFor(() => {
            expect(container.textContent).not.toContain(
                'Restoring verification status…',
            );
        });

        // Ensure only GET was performed, zero POST/PUT
        expect(fetchMock).toHaveBeenCalledTimes(1);
        const [calledUrl, calledInit] = fetchMock.mock.calls[0];
        expect(calledUrl).toBe(
            '/setup/omr/templates/verification-runs/10/status',
        );
        expect(
            calledInit?.method === undefined || calledInit?.method === 'GET',
        ).toBe(true);
        expect(calledInit?.body).toBeUndefined();
    });

    it('publishes the latest draft lock version from verification hydration', async () => {
        const onDraftLockVersion = vi.fn();
        fetchMock.mockResolvedValueOnce({
            ok: true,
            json: async () => ({
                run_id: 11,
                mode: 'digital',
                status: 'passed',
                draft_lock_version: 42,
            }),
        });

        render(StudioExportVerifyPanel, {
            draft: { stage: 'mapping_complete', lock_version: 40 },
            verification_run: {
                run_id: 11,
                mode: 'digital',
                status: 'passed',
                status_url: '/setup/omr/templates/verification-runs/11/status',
            },
            urls: {},
            onDraftLockVersion,
        });

        await waitFor(() => {
            expect(onDraftLockVersion).toHaveBeenCalledWith(42);
        });
    });

    it('terminal passed run fetches once and does not initiate repeated polling timer', async () => {
        const summaryRun = {
            projection: 'summary',
            run_id: 20,
            mode: 'digital',
            status: 'passed',
            status_url: '/setup/omr/templates/verification-runs/20/status',
        };

        fetchMock.mockResolvedValueOnce({
            ok: true,
            json: async () => ({
                run_id: 20,
                mode: 'digital',
                status: 'passed',
                attempt: 1,
            }),
        });

        const { container } = render(StudioExportVerifyPanel, {
            draft: { stage: 'digital_verified' },
            verification_run: summaryRun,
            urls: { digital_verification: '/setup/omr/drafts/1/verify' },
        });

        await waitFor(() => {
            expect(container.textContent).not.toContain(
                'Restoring verification status…',
            );
        });

        expect(fetchMock).toHaveBeenCalledTimes(1);

        // Digital checkpoint is done; Physical challenge checkpoint is active/focused
        expect(container.textContent).toContain('Digital engine check');
        expect(container.textContent).toContain('Physical tests');
        const activeStep = container.querySelector('[aria-current="step"]');
        expect(activeStep?.textContent).toContain('Physical tests');
    });

    it('active digital run summary displays progress immediately and continues bounded polling', async () => {
        const summaryRun = {
            projection: 'summary',
            run_id: 30,
            mode: 'digital',
            status: 'variant_matrix',
            progress: { done: 3, total: 14, percent: 21 },
            status_url: '/setup/omr/templates/verification-runs/30/status',
        };

        fetchMock.mockResolvedValue({
            ok: true,
            json: async () => ({
                run_id: 30,
                mode: 'digital',
                status: 'variant_matrix',
                progress: { done: 7, total: 14, percent: 50 },
                phase_detail: {
                    stage: 'variant_matrix',
                    current_variant: 'rotation_2deg',
                },
            }),
        });

        const { container } = render(StudioExportVerifyPanel, {
            draft: { stage: 'mapping_complete' },
            verification_run: summaryRun,
        });

        // Initial summary progress is visible immediately
        expect(container.textContent).toContain('3 / 14 variants');
        expect(container.textContent).toContain(
            'Restoring verification status…',
        );

        // After hydration resolves, updated progress is shown
        await waitFor(() => {
            expect(container.textContent).toContain('7 / 14 variants');
        });
    });

    it('stale or null summary leaves Step 1 ready with action button and zero network requests', async () => {
        const { container } = render(StudioExportVerifyPanel, {
            draft: { stage: 'mapping_complete' },
            verification_run: null,
            urls: { digital_verification: '/setup/omr/drafts/1/verify' },
        });

        // Zero requests on mount
        expect(fetchMock).not.toHaveBeenCalled();

        // No hydration banner
        expect(container.textContent).not.toContain(
            'Restoring verification status…',
        );

        // Step 1 is Ready and Run digital verification is available
        expect(container.textContent).toContain('Run digital verification');
    });

    it('restored failed run expands Digital with safe issue and Retry action', async () => {
        const summaryRun = {
            projection: 'summary',
            run_id: 40,
            mode: 'digital',
            status: 'failed',
            safe_issue: {
                code: 'nogo_matrix_fail',
                detail: 'No-go matrix check failed on marker perturbation',
            },
            status_url: '/setup/omr/templates/verification-runs/40/status',
        };

        fetchMock.mockResolvedValueOnce({
            ok: true,
            json: async () => ({
                run_id: 40,
                mode: 'digital',
                status: 'failed',
                attempt: 1,
                safe_issue: {
                    code: 'nogo_matrix_fail',
                    detail: 'No-go matrix check failed on marker perturbation',
                },
            }),
        });

        const { container } = render(StudioExportVerifyPanel, {
            draft: { stage: 'digital_verified' },
            verification_run: summaryRun,
        });

        // Active step is Digital engine check
        const activeStep = container.querySelector('[aria-current="step"]');
        expect(activeStep?.textContent).toContain('Digital engine check');

        // Safe issue detail is visible
        expect(container.textContent).toContain(
            'No-go matrix check failed on marker perturbation',
        );

        await waitFor(() => {
            expect(container.textContent).not.toContain(
                'Restoring verification status…',
            );
        });
        // Retry button is enabled after hydration
        expect(container.textContent).toContain('Retry digital verification');
        const retryBtn = Array.from(container.querySelectorAll('button')).find(
            (b) => b.textContent?.includes('Retry digital verification'),
        );
        expect(retryBtn).toBeDefined();
        expect((retryBtn as HTMLButtonElement).disabled).toBe(false);
    });

    it('quick rehearsal awaiting_print restores printable challenge sheet and shading guide links after hydration', async () => {
        const summaryRun = {
            projection: 'summary',
            run_id: 50,
            mode: 'quick_rehearsal',
            status: 'awaiting_print',
            status_url: '/setup/omr/templates/verification-runs/50/status',
        };

        fetchMock.mockResolvedValueOnce({
            ok: true,
            json: async () => ({
                run_id: 50,
                mode: 'quick_rehearsal',
                status: 'awaiting_print',
                challenge: {
                    challenge_code: 'QR-50',
                    print_url: '/storage/omr/challenge-50-sheet.pdf',
                    guide_url: '/storage/omr/challenge-50-guide.pdf',
                },
                available_actions: ['print_challenge'],
            }),
        });

        const { container } = render(StudioExportVerifyPanel, {
            draft: { stage: 'digital_verified' },
            verification_run: summaryRun,
        });

        await waitFor(() => {
            expect(container.textContent).not.toContain(
                'Restoring verification status…',
            );
        });

        // Challenge sheet and shading guide buttons are present and hydrated
        expect(container.textContent).toMatch(
            /Open print PDF|Print challenge/i,
        );
        expect(container.textContent).toMatch(/View guide|Shading guide/i);
        expect(container.textContent).toContain(
            'Mark printed & continue to capture',
        );
    });

    it('keeps human waiting challenge statuses stable across refresh hydration', async () => {
        const waitingRuns = [
            {
                status: 'awaiting_print',
                heading: 'Physical tests',
                run: {
                    run_id: 51,
                    mode: 'quick_rehearsal',
                    status: 'awaiting_print',
                    challenge: {
                        challenge_code: 'QR-51',
                        status: 'created',
                        print_url: '/storage/omr/challenge-51-sheet.pdf',
                        guide_url: '/storage/omr/challenge-51-guide.pdf',
                    },
                    available_actions: ['print_challenge'],
                },
            },
            {
                status: 'awaiting_capture',
                heading: 'Physical tests',
                run: {
                    run_id: 52,
                    mode: 'quick_rehearsal',
                    status: 'awaiting_capture',
                    challenge: {
                        challenge_code: 'QR-52',
                        status: 'printed',
                        print_url: '/storage/omr/challenge-52-sheet.pdf',
                        guide_url: '/storage/omr/challenge-52-guide.pdf',
                    },
                    available_actions: ['submit_capture'],
                    evidence: [],
                },
            },
            {
                status: 'needs_review',
                heading: 'Evidence review',
                run: {
                    run_id: 53,
                    mode: 'full_qualification',
                    status: 'needs_review',
                    challenge: {
                        challenge_code: 'FULL-53',
                        status: 'failed',
                        print_url: '/storage/omr/challenge-53-sheet.pdf',
                        guide_url: '/storage/omr/challenge-53-guide.pdf',
                    },
                    available_actions: ['review_capture'],
                    evidence: [],
                },
            },
        ];

        for (const waiting of waitingRuns) {
            fetchMock.mockImplementation(async () => ({
                ok: true,
                json: async () => waiting.run,
            }));
            const summaryRun = {
                projection: 'summary',
                ...waiting.run,
                status_url: `/setup/omr/templates/verification-runs/${waiting.run.run_id}/status`,
            };
            const view = render(StudioExportVerifyPanel, {
                draft: {
                    stage: 'digital_verified',
                    compiled_version_id:
                        waiting.status === 'needs_review' ? 3 : null,
                },
                verification_run: summaryRun,
                marker_profile_status: 'qualified',
            });

            await waitFor(() => {
                expect(view.container.textContent).not.toContain(
                    'Restoring verification status…',
                );
            });
            expect(
                view.container
                    .querySelector('#current-step-heading')
                    ?.textContent?.trim(),
            ).toBe(waiting.heading);
            expect(view.container.textContent).not.toContain('worker_timeout');
            view.unmount();
            fetchMock.mockReset();
        }
    });

    it('clears a transient status error after the next successful poll', async () => {
        const status = {
            run_id: 54,
            mode: 'quick_rehearsal',
            status: 'awaiting_print',
            challenge: {
                challenge_code: 'QR-54',
                status: 'created',
                print_url: '/storage/omr/challenge-54-sheet.pdf',
            },
            available_actions: ['print_challenge'],
        };
        let requestCount = 0;
        fetchMock.mockImplementation(async () => {
            requestCount += 1;
            if (requestCount === 2) {
                return {
                    ok: false,
                    json: async () => ({ detail: 'temporary outage' }),
                };
            }
            return { ok: true, json: async () => status };
        });

        const { container } = render(StudioExportVerifyPanel, {
            draft: { stage: 'digital_verified' },
            verification_run: {
                projection: 'summary',
                ...status,
                status_url: '/setup/omr/templates/verification-runs/54/status',
            },
        });

        await waitFor(() => {
            expect(container.textContent).toContain(
                'Verification status is unavailable.',
            );
        });
        await waitFor(
            () => {
                expect(container.textContent).not.toContain(
                    'Verification status is unavailable.',
                );
            },
            { timeout: 3000 },
        );
        expect(requestCount).toBeGreaterThanOrEqual(3);
    });

    it('full qualification needs_review restores Diagnostic Evidence panel and capture data', async () => {
        const summaryRun = {
            projection: 'summary',
            run_id: 60,
            mode: 'full_qualification',
            status: 'needs_review',
            status_url: '/setup/omr/templates/verification-runs/60/status',
        };

        fetchMock.mockResolvedValueOnce({
            ok: true,
            json: async () => ({
                run_id: 60,
                mode: 'full_qualification',
                status: 'needs_review',
                evidence: [
                    {
                        capture_id: 601,
                        page_no: 1,
                        status: 'fail',
                        error_code: 'ambiguous_bubbles',
                        summary: {
                            evidence: {
                                items: [
                                    {
                                        item_id: 'Q1',
                                        label: 'Question 1',
                                        problem_tags: ['ambiguous'],
                                        candidate_confidence: 0.65,
                                        fill_ratio: 0.45,
                                        status: 'ambiguous',
                                    },
                                ],
                                assets: {
                                    captured: { name: 'raw.jpg' },
                                    aligned: { name: 'aligned.jpg' },
                                    overlay: { name: 'overlay.jpg' },
                                },
                            },
                        },
                        urls: {
                            input: '/storage/omr/in601.jpg',
                            aligned: '/storage/omr/al601.jpg',
                            overlay: '/storage/omr/ov601.jpg',
                        },
                    },
                ],
            }),
        });

        const { container } = render(StudioExportVerifyPanel, {
            draft: { stage: 'digital_verified' },
            verification_run: summaryRun,
        });

        // Focuses Step 3 (Evidence review)
        const activeStep = container.querySelector('[aria-current="step"]');
        expect(activeStep?.textContent).toContain('Evidence review');

        await waitFor(() => {
            expect(container.textContent).not.toContain(
                'Restoring verification status…',
            );
        });

        // Restored capture exposes one modal review entry point, not report actions.
        expect(container.textContent).toContain('Review');
        expect(container.textContent).not.toContain('Confirm exact recovery');
        expect(container.textContent).not.toContain('Reject evidence');
    });

    it('passed full qualification restores the three-step authoring flow', async () => {
        const summaryRun = {
            projection: 'summary',
            run_id: 70,
            mode: 'full_qualification',
            status: 'passed',
            status_url: '/setup/omr/templates/verification-runs/70/status',
        };

        fetchMock.mockResolvedValueOnce({
            ok: true,
            json: async () => ({
                run_id: 70,
                mode: 'full_qualification',
                status: 'passed',
            }),
        });

        const { container } = render(StudioExportVerifyPanel, {
            draft: { stage: 'digital_verified', compiled_version_id: 10 },
            verification_run: summaryRun,
            physical_run_id: 70,
            marker_profile_status: 'qualified',
        });

        await waitFor(() => {
            expect(container.textContent).not.toContain(
                'Restoring verification status…',
            );
        });

        expect(
            container.querySelectorAll(
                'ol[aria-label="Verification progress"] > li',
            ),
        ).toHaveLength(3);
        expect(container.textContent).not.toContain('Qualification');
        expect(container.textContent).not.toContain(
            'Record measurements & derive qualification',
        );
    });

    it('poller stops automatically when active run transitions to terminal passed', async () => {
        const summaryRun = {
            projection: 'summary',
            run_id: 80,
            mode: 'digital',
            status: 'variant_matrix',
            progress: { done: 10, total: 14 },
            status_url: '/setup/omr/templates/verification-runs/80/status',
        };

        let pollCount = 0;
        fetchMock.mockImplementation(async () => {
            pollCount++;
            if (pollCount === 1) {
                return {
                    ok: true,
                    json: async () => ({
                        run_id: 80,
                        mode: 'digital',
                        status: 'variant_matrix',
                        progress: { done: 12, total: 14 },
                    }),
                };
            }
            return {
                ok: true,
                json: async () => ({
                    run_id: 80,
                    mode: 'digital',
                    status: 'passed',
                    attempt: 1,
                }),
            };
        });

        const { container } = render(StudioExportVerifyPanel, {
            draft: { stage: 'mapping_complete' },
            verification_run: summaryRun,
        });

        await waitFor(() => {
            expect(container.textContent).toContain(
                'Digital engine check passed: machine-filled proof',
            );
        });

        expect(pollCount).toBeGreaterThanOrEqual(2);
    });

    it('safely handles unmount during in-flight status hydration without errors', async () => {
        const summaryRun = {
            projection: 'summary',
            run_id: 90,
            mode: 'digital',
            status: 'variant_matrix',
            status_url: '/setup/omr/templates/verification-runs/90/status',
        };

        const { promise, resolve } = Promise.withResolvers<{
            ok: boolean;
            json: () => Promise<unknown>;
        }>();
        fetchMock.mockReturnValueOnce(promise);

        const { unmount } = render(StudioExportVerifyPanel, {
            draft: { stage: 'mapping_complete' },
            verification_run: summaryRun,
        });

        // Unmount while fetch is in-flight
        unmount();

        // Resolve the network call after unmount
        resolve({
            ok: true,
            json: async () => ({
                run_id: 90,
                mode: 'digital',
                status: 'passed',
            }),
        });

        await Promise.resolve();
    });

    it('hydrates the newly created physical run instead of retaining the base run projection', async () => {
        const baseRun = {
            projection: 'summary',
            run_id: 30,
            mode: 'digital',
            status: 'passed',
            status_url: '/setup/omr/templates/verification-runs/30/status',
            target_current: true,
            digital_verification_current: true,
        };
        const challenge = {
            challenge_id: 10,
            challenge_code: 'CHAL-31',
            mode: 'quick',
            status: 'created',
            print_url: '/runs/31/sheet.pdf',
            guide_url: '/runs/31/guide.pdf',
            print_mime: 'application/pdf',
            pdf_ready: true,
        };
        const created = {
            run_id: 31,
            status: 'awaiting_print',
            mode: 'quick_rehearsal',
            challenge_id: challenge.challenge_id,
            challenge_code: challenge.challenge_code,
            status_url: '/setup/omr/templates/verification-runs/31/status',
            available_actions: ['print_challenge'],
            print_url: challenge.print_url,
            guide_url: challenge.guide_url,
            challenge,
        };
        const fullProjection = {
            ...created,
            methods: [
                {
                    method: 'pre_shaded',
                    state: 'challenge_ready',
                    target_kind: 'candidate',
                    target_id: 1,
                    target_hash: 'a'.repeat(64),
                    plan_hash: 'b'.repeat(64),
                    expected_vector_hash: 'c'.repeat(64),
                    challenge_id: challenge.challenge_id,
                    challenge,
                    pages: [
                        {
                            page_no: 1,
                            state: 'challenge_ready',
                            selected_attempt_id: null,
                            selected_attempt: null,
                        },
                    ],
                    attempts: [],
                },
                {
                    method: 'hand_shaded',
                    state: 'challenge_ready',
                    target_kind: 'candidate',
                    target_id: 1,
                    target_hash: 'a'.repeat(64),
                    plan_hash: 'b'.repeat(64),
                    expected_vector_hash: 'c'.repeat(64),
                    challenge_id: 11,
                    challenge: {
                        ...challenge,
                        challenge_id: 11,
                        challenge_code: 'CHAL-31-HAND',
                    },
                    pages: [
                        {
                            page_no: 1,
                            state: 'challenge_ready',
                            selected_attempt_id: null,
                            selected_attempt: null,
                        },
                    ],
                    attempts: [],
                },
            ],
        };
        fetchMock.mockImplementation(async (url, init) => {
            const urlString = String(url);
            if (urlString.endsWith('/30/status')) {
                return { ok: true, json: async () => ({ ...baseRun }) };
            }
            if (init?.method === 'POST' && urlString.endsWith('/challenge')) {
                return { ok: true, json: async () => created };
            }
            if (init?.method === 'POST' && urlString.endsWith('/print')) {
                return {
                    ok: true,
                    json: async () => ({
                        status_url:
                            '/setup/omr/templates/verification-runs/31/status',
                    }),
                };
            }
            if (urlString.endsWith('/31/status')) {
                return { ok: true, json: async () => fullProjection };
            }
            return { ok: true, json: async () => ({}) };
        });

        const { container, getByRole, getAllByRole, unmount } = render(
            StudioExportVerifyPanel,
            {
                draft: {
                    id: 1,
                    stage: 'digital_verified',
                    mapping_current: true,
                    digital_verification_current: true,
                },
                verification_run: baseRun,
                marker_profile_status: 'qualified',
            },
        );

        await waitFor(() => {
            expect(container.textContent).not.toContain(
                'Restoring verification status…',
            );
        });
        await fireEvent.click(
            getByRole('button', { name: /Create pre-shaded test sheet/ }),
        );

        await waitFor(() => {
            expect(
                fetchMock.mock.calls.filter(([url]) =>
                    String(url).endsWith('/31/status'),
                ).length,
            ).toBeGreaterThanOrEqual(1);
            expect(container.textContent).toContain('CHAL-31');
            expect(
                container.querySelectorAll('[data-omr-method]'),
            ).toHaveLength(2);
            expect(container.textContent).not.toContain(
                'Restoring verification status…',
            );
        });

        await waitFor(() => {
            const printedButtons = getAllByRole('button', {
                name: /Mark printed & continue/,
            });
            expect(printedButtons).toHaveLength(2);
            expect(
                printedButtons.every(
                    (button) => !(button as HTMLButtonElement).disabled,
                ),
            ).toBe(true);
        });
        const preShadedPrintButton = getAllByRole('button', {
            name: /Mark printed & continue/,
        })[0];
        await fireEvent.click(preShadedPrintButton);
        await waitFor(() => {
            const printCall = fetchMock.mock.calls.find(
                ([url, init]) =>
                    String(url).endsWith('/31/print') &&
                    init?.method === 'POST',
            );
            expect(printCall).toBeDefined();
            expect(JSON.parse(String(printCall?.[1]?.body))).toMatchObject({
                method: 'pre_shaded',
                challenge_id: 10,
                target_hash: 'a'.repeat(64),
                plan_hash: 'b'.repeat(64),
                expected_vector_hash: 'c'.repeat(64),
            });
            expect(container.textContent).not.toContain(
                'Restoring verification status…',
            );
        });
        expect(
            fetchMock.mock.calls.filter(([url]) =>
                String(url).endsWith('/30/status'),
            ),
        ).toHaveLength(1);
        unmount();
    });
    it('re-renders cleanly across multiple reload cycles preserving pass/fail states', async () => {
        // 1. Initial reload with failed run
        fetchMock.mockResolvedValueOnce({
            ok: true,
            json: async () => ({
                run_id: 95,
                mode: 'digital',
                status: 'failed',
                attempt: 2,
                safe_issue: {
                    code: 'bad_skew',
                    detail: 'Skew threshold exceeded 3.5 deg',
                },
            }),
        });

        const firstRender = render(StudioExportVerifyPanel, {
            draft: { stage: 'mapping_complete' },
            verification_run: {
                projection: 'summary',
                run_id: 95,
                mode: 'digital',
                status: 'failed',
                safe_issue: {
                    code: 'bad_skew',
                    detail: 'Skew threshold exceeded 3.5 deg',
                },
                status_url: '/setup/omr/templates/verification-runs/95/status',
            },
        });

        expect(firstRender.container.textContent).toContain(
            'Skew threshold exceeded 3.5 deg',
        );
        await waitFor(() => {
            expect(firstRender.container.textContent).not.toContain(
                'Restoring verification status…',
            );
        });
        firstRender.unmount();

        // 2. Subsequent reload after retry succeeded
        fetchMock.mockResolvedValueOnce({
            ok: true,
            json: async () => ({
                run_id: 96,
                mode: 'digital',
                status: 'passed',
                attempt: 3,
            }),
        });

        const secondRender = render(StudioExportVerifyPanel, {
            draft: { stage: 'digital_verified' },
            verification_run: {
                projection: 'summary',
                run_id: 96,
                mode: 'digital',
                status: 'passed',
                status_url: '/setup/omr/templates/verification-runs/96/status',
            },
        });

        await waitFor(() => {
            expect(secondRender.container.textContent).not.toContain(
                'Restoring verification status…',
            );
        });
        expect(secondRender.container.textContent).toContain('Physical tests');
        secondRender.unmount();
    });
});
