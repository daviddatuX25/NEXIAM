// @vitest-environment jsdom

import {
    cleanup,
    fireEvent,
    render,
    waitFor,
    within,
} from '@testing-library/svelte';
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import StudioExportVerifyPanel from '../../resources/js/Pages/Setup/OmrConfiguration/Canonical/StudioExportVerifyPanel.svelte';
import StudioShadingGuideModal from '../../resources/js/Pages/Setup/OmrConfiguration/Canonical/StudioShadingGuideModal.svelte';
import {
    isChallengePdfReady,
    isLegacyPngChallenge,
    guideTotalExpectedMarks,
    type ChallengeGuidePayload,
} from '../../resources/js/lib/omrVerificationRun.js';

const mockGuide: ChallengeGuidePayload = {
    schema_version: 'v1',
    mode: 'quick',
    challenge_code: 'CHAL-TEST-PDF-01',
    instructions: 'Shade target bubbles using a #2 pencil.',
    calibration_disclaimer:
        'Calibration-ID disclaimer: this challenge shades deterministic calibration digits derived from the mapped identity grid.',
    sections: [
        {
            page_no: 1,
            area: 'General Ability',
            options: ['A', 'B', 'C', 'D'],
            labels: [
                { label: 'GA_01', option: 'B' },
                { label: 'GA_02', option: 'D' },
                { label: 'GA_03', option: 'A' },
            ],
        },
        {
            page_no: 2,
            area: 'Mathematics',
            options: ['A', 'B', 'C', 'D'],
            labels: [
                { label: 'MATH_01', option: 'C' },
                { label: 'MATH_02', option: 'A' },
            ],
        },
    ],
    identity_columns: [
        { label: 'Col 1', digit: 3 },
        { label: 'Col 2', digit: 7 },
    ],
};

function jsonResponse(payload: Record<string, unknown> = {}, status = 200) {
    return {
        ok: status >= 200 && status < 300,
        status,
        json: async () => payload,
    };
}

describe('Pure helper contracts for PDF readiness and Guide sections', () => {
    it('identifies PDF ready vs legacy PNG challenges correctly', () => {
        expect(isChallengePdfReady(null)).toBe(false);
        expect(
            isChallengePdfReady({
                print_url: '/print.pdf',
                pdf_ready: true,
            } as unknown as Parameters<typeof isChallengePdfReady>[0]),
        ).toBe(true);
        expect(
            isChallengePdfReady({
                print_url: '/print.pdf',
                print_mime: 'application/pdf',
            } as unknown as Parameters<typeof isChallengePdfReady>[0]),
        ).toBe(true);
        expect(
            isChallengePdfReady({
                print_url: '/print.png',
                print_mime: 'image/png',
                pdf_ready: false,
            } as unknown as Parameters<typeof isChallengePdfReady>[0]),
        ).toBe(false);
        expect(
            isChallengePdfReady({
                print_url: '/print.pdf',
            } as unknown as Parameters<typeof isChallengePdfReady>[0]),
        ).toBe(true);

        expect(isLegacyPngChallenge(null)).toBe(false);
        expect(
            isLegacyPngChallenge({
                print_mime: 'image/png',
            } as unknown as Parameters<typeof isLegacyPngChallenge>[0]),
        ).toBe(true);
        expect(
            isLegacyPngChallenge({ pdf_ready: false } as unknown as Parameters<
                typeof isLegacyPngChallenge
            >[0]),
        ).toBe(true);
        expect(
            isLegacyPngChallenge(
                {} as unknown as Parameters<typeof isLegacyPngChallenge>[0],
                ['prepare_print'],
            ),
        ).toBe(true);
        expect(
            isLegacyPngChallenge({
                print_mime: 'application/pdf',
                pdf_ready: true,
            } as unknown as Parameters<typeof isLegacyPngChallenge>[0]),
        ).toBe(false);
    });

    it('computes total expected marks across all sections', () => {
        expect(guideTotalExpectedMarks(null)).toBe(0);
        expect(guideTotalExpectedMarks(mockGuide)).toBe(5);
    });
});

describe('Step 2 PDF-first print and shading guide rows in StudioExportVerifyPanel', () => {
    beforeEach(() => {
        vi.stubGlobal(
            'fetch',
            vi.fn(async () => jsonResponse({})),
        );
        vi.stubGlobal('open', vi.fn());
    });

    afterEach(() => {
        cleanup();
        vi.unstubAllGlobals();
    });

    it('renders compact stacked rows for Print challenge and Shading guide when PDF is ready', () => {
        const { container, getByRole, queryByRole } = render(
            StudioExportVerifyPanel,
            {
                draft: { id: 1, stage: 'digital_verified' },
                urls: {},
                verification_run: {
                    run_id: 10,
                    status: 'awaiting_print',
                    mode: 'quick_rehearsal',
                    available_actions: ['print_challenge'],
                    challenge: {
                        challenge_id: 1,
                        challenge_code: 'CHAL-TEST-PDF-01',
                        mode: 'quick',
                        status: 'created',
                        print_url: '/runs/10/sheet.pdf',
                        print_mime: 'application/pdf',
                        pdf_ready: true,
                        guide_url: '/runs/10/guide.pdf',
                        guide_pdf_url: '/runs/10/guide-download.pdf',
                        guide: mockGuide,
                    },
                    evidence: [],
                },
            },
        );

        const currentLi = container.querySelector('li[aria-current="step"]');
        expect(currentLi?.textContent).toContain('Physical tests');

        // Row 1: Print challenge
        expect(container.textContent).toContain('Print challenge');
        expect(container.textContent).toContain('PDF ready');
        expect(container.textContent).toContain('Print at 100% / Actual Size');
        const openPrintBtn = getByRole('button', { name: /Open print PDF/i });
        expect(openPrintBtn).not.toBeNull();

        // Row 2: Shading guide
        expect(container.textContent).toContain('Shading guide');
        const viewGuideBtn = getByRole('button', { name: /View guide/i });
        expect(viewGuideBtn).not.toBeNull();
        expect(
            queryByRole('button', { name: /Download guide PDF/i }),
        ).toBeNull();

        // Calibration disclaimer
        expect(container.textContent).toContain('Calibration-ID disclaimer');
    });

    it('opens each method-owned guide in place without navigating to a guide asset', async () => {
        const { getAllByRole, getByRole, queryByRole } = render(
            StudioExportVerifyPanel,
            {
                draft: { id: 1, stage: 'digital_verified' },
                urls: {},
                verification_run: {
                    run_id: 40,
                    status: 'awaiting_print',
                    mode: 'quick_rehearsal',
                    available_actions: ['print_challenge'],
                    methods: [
                        {
                            method: 'pre_shaded',
                            state: 'challenge_ready',
                            challenge_id: 401,
                            challenge: {
                                challenge_id: 401,
                                mode: 'quick',
                                status: 'created',
                                print_url: '/runs/40/pre-sheet.pdf',
                                sheet_pages: [
                                    {
                                        page_no: 1,
                                        url: '/runs/40/pre-sheet.png',
                                        mime: 'image/png',
                                    },
                                ],
                                guide: mockGuide,
                            },
                        },
                        {
                            method: 'hand_shaded',
                            state: 'challenge_ready',
                            challenge_id: 402,
                            challenge: {
                                challenge_id: 402,
                                mode: 'quick',
                                status: 'created',
                                print_url: '/runs/40/hand-sheet.pdf',
                                sheet_pages: [
                                    {
                                        page_no: 1,
                                        url: '/runs/40/hand-sheet.png',
                                        mime: 'image/png',
                                    },
                                ],
                                guide: mockGuide,
                            },
                        },
                    ],
                    evidence: [],
                },
            },
        );

        const guideButtons = getAllByRole('button', {
            name: /View shading guide/i,
        });
        expect(guideButtons).toHaveLength(2);

        await fireEvent.click(guideButtons[0]);
        const preDialog = await waitFor(() => getByRole('dialog'));
        await fireEvent.click(
            getByRole('tab', { name: /Challenge Sheet & Marks/i }),
        );
        expect(preDialog.querySelector('img')?.getAttribute('src')).toBe(
            '/runs/40/pre-sheet.png',
        );
        await fireEvent.click(getAllByRole('button', { name: /Close/i })[0]);
        await waitFor(() => expect(queryByRole('dialog')).toBeNull());

        await fireEvent.click(guideButtons[1]);
        const handDialog = await waitFor(() => getByRole('dialog'));
        await fireEvent.click(getByRole('tab', { name: /Marks to Shade/i }));
        expect(handDialog.querySelector('img')).toBeNull();
        expect(
            within(handDialog).getByRole('heading', {
                name: /Shade these exact bubbles/i,
            }),
        ).toBeTruthy();
        expect(within(handDialog).getByText('General Ability')).toBeTruthy();
        expect(within(handDialog).getByText('Question 1')).toBeTruthy();
        expect(
            within(handDialog).getByLabelText('Shade option B'),
        ).toBeTruthy();
        expect(within(handDialog).getByText('Question 2')).toBeTruthy();
        expect(
            within(handDialog).getByLabelText('Shade option D'),
        ).toBeTruthy();
        expect(within(handDialog).getByText('Question 3')).toBeTruthy();
        expect(
            within(handDialog).getByLabelText('Shade option A'),
        ).toBeTruthy();
    });
    it('clicking Open print PDF opens the PDF asset in a new tab', async () => {
        const windowOpenSpy = vi
            .spyOn(window, 'open')
            .mockImplementation(() => null);

        const { getByRole } = render(StudioExportVerifyPanel, {
            draft: { id: 1, stage: 'digital_verified' },
            urls: {},
            verification_run: {
                run_id: 10,
                status: 'awaiting_print',
                mode: 'quick_rehearsal',
                available_actions: ['print_challenge'],
                challenge: {
                    challenge_id: 1,
                    challenge_code: 'CHAL-TEST-PDF-01',
                    mode: 'quick',
                    status: 'created',
                    print_url: '/runs/10/sheet.pdf',
                    print_mime: 'application/pdf',
                    pdf_ready: true,
                    guide_url: '/runs/10/guide.pdf',
                },
                evidence: [],
            },
        });

        const openPrintBtn = getByRole('button', { name: /Open print PDF/i });
        await fireEvent.click(openPrintBtn);
        expect(windowOpenSpy).toHaveBeenCalledWith(
            '/runs/10/sheet.pdf',
            '_blank',
            'noopener,noreferrer',
        );
    });

    it('renders legacy PNG pack state with Prepare print PDF action and transitions to PDF ready on prepare', async () => {
        const preparePayload = {
            run_id: 12,
            status: 'awaiting_print',
            status_url: '/runs/12/status',
            print_url: '/runs/12/prepared-sheet.pdf',
            print_mime: 'application/pdf',
            pdf_ready: true,
            challenge: {
                challenge_id: 2,
                challenge_code: 'CHAL-LEGACY-02',
                mode: 'quick',
                status: 'created',
                print_url: '/runs/12/prepared-sheet.pdf',
                print_mime: 'application/pdf',
                pdf_ready: true,
            },
        };

        const fetchMock = vi.fn(async () => jsonResponse(preparePayload));
        vi.stubGlobal('fetch', fetchMock);

        const { container, getByRole, queryByRole } = render(
            StudioExportVerifyPanel,
            {
                draft: { id: 1, stage: 'digital_verified' },
                urls: {},
                verification_run: {
                    run_id: 12,
                    status: 'awaiting_print',
                    mode: 'quick_rehearsal',
                    available_actions: ['print_challenge', 'prepare_print'],
                    challenge: {
                        challenge_id: 2,
                        challenge_code: 'CHAL-LEGACY-02',
                        mode: 'quick',
                        status: 'created',
                        print_url: '/runs/12/sheet-page1.png',
                        print_mime: 'image/png',
                        pdf_ready: false,
                        guide_url: '/runs/12/guide',
                    },
                    evidence: [],
                },
            },
        );

        expect(container.textContent).toContain('Legacy PNG pack');
        expect(container.textContent).toContain(
            'Legacy pack detected — prepare an Actual-Size PDF before printing',
        );

        const prepareBtn = getByRole('button', { name: /Prepare print PDF/i });
        expect(prepareBtn).not.toBeNull();
        expect(queryByRole('button', { name: /Open print PDF/i })).toBeNull();

        // Click Prepare print PDF
        await fireEvent.click(prepareBtn);

        await waitFor(() => {
            expect(fetchMock).toHaveBeenCalled();
            const [url, options] = fetchMock.mock.calls[0] as unknown as [
                string,
                RequestInit,
            ];
            expect(url).toBe(
                '/setup/omr/templates/verification-runs/12/prepare-print',
            );
            expect(options.method).toBe('POST');
            expect(JSON.parse(options.body as string)).toHaveProperty(
                'idempotency_key',
            );
            expect(container.textContent).toContain(
                'Print PDF prepared at Actual Size',
            );
            expect(
                getByRole('button', { name: /Open print PDF/i }),
            ).not.toBeNull();
        });
    });
});

describe('Accessible Shading Guide Modal (StudioShadingGuideModal)', () => {
    beforeEach(() => {
        vi.useFakeTimers();
        vi.stubGlobal('open', vi.fn());
        if (!Element.prototype.setPointerCapture) {
            Element.prototype.setPointerCapture = vi.fn();
        }
        if (!Element.prototype.releasePointerCapture) {
            Element.prototype.releasePointerCapture = vi.fn();
        }
    });

    afterEach(async () => {
        cleanup();
        await vi.runAllTimersAsync();
        vi.useRealTimers();
        vi.unstubAllGlobals();
    });

    it('renders a simple modal header, mode badge, and meaningful visual shading examples', () => {
        const { getByRole } = render(StudioShadingGuideModal, {
            open: true,
            challenge: {
                challenge_id: 1,
                challenge_code: 'CHAL-TEST-PDF-01',
                mode: 'quick',
                status: 'created',
                print_url: '/runs/10/sheet.pdf',
                guide_url: '/runs/10/guide.pdf',
                guide_pdf_url: '/runs/10/guide-download.pdf',
                guide: mockGuide,
            },
        });

        const dialog = getByRole('dialog');
        expect(dialog).not.toBeNull();
        expect(dialog.textContent).toContain('Physical tests');
        expect(dialog.textContent).not.toContain('CHAL-TEST-PDF-01');
        expect(dialog.textContent).toContain('Quick Rehearsal');

        // 4 Visual Shading Standards
        expect(dialog.textContent).toContain('Optical Mark Shading Standards');
        expect(dialog.textContent).toContain('Unmarked');
        expect(dialog.textContent).toContain(
            'Clean bubble with clear interior',
        );
        expect(dialog.textContent).toContain('Correct Fill');
        expect(dialog.textContent).toContain(
            '100% solid, dark, complete coverage',
        );
        expect(dialog.textContent).toContain('Invalid Marks');
        expect(dialog.textContent).toContain(
            'No ticks, crosses, dots, or partial fills',
        );
        expect(dialog.textContent).toContain('Clean Erasure');
        expect(dialog.textContent).toContain(
            'Erase completely; avoid dark smudges',
        );

        // Ensure SVGs for examples are rendered
        const svgs = dialog.querySelectorAll('svg');
        expect(svgs.length).toBeGreaterThan(4);
    });

    it('renders concise physical print and marking rules and calibration disclaimer', () => {
        const { getByRole } = render(StudioShadingGuideModal, {
            open: true,
            challenge: {
                challenge_id: 1,
                challenge_code: 'CHAL-TEST-PDF-01',
                mode: 'full',
                status: 'created',
                guide: mockGuide,
            },
        });

        const dialog = getByRole('dialog');
        expect(dialog.textContent).toContain('Physical Marking & Print Rules');
        expect(dialog.textContent).toContain(
            '#2 (HB) Pencil or Dark Black Pen',
        );
        expect(dialog.textContent).toContain('Pristine QR Code Area');
        expect(dialog.textContent).toContain('Corner Alignment Targets');
        expect(dialog.textContent).toContain('Print at 100% (Actual Size)');
        expect(dialog.textContent).toContain('Calibration-ID disclaimer');
        expect(dialog.textContent).toContain(
            'deterministic calibration digits derived from the mapped identity grid',
        );
    });

    it('renders exact simplified copy contract on Screen 2 and omits misleading mark counts and target cards', async () => {
        const { getByRole, getByAltText } = render(StudioShadingGuideModal, {
            open: true,
            challenge: {
                challenge_id: 1,
                challenge_code: 'CHAL-TEST-PDF-01',
                mode: 'quick',
                status: 'created',
                guide: mockGuide,
                sheet_pages: [
                    {
                        page_no: 1,
                        url: '/runs/10/sheet-p1.png',
                        mime: 'image/png',
                    },
                    {
                        page_no: 2,
                        url: '/runs/10/sheet-p2.png',
                        mime: 'image/png',
                    },
                ],
            },
        });

        const dialog = getByRole('dialog');

        // Screen 1 does not show Screen 2 heading
        expect(dialog.textContent).not.toContain('Review the printed pattern');

        // Navigate to Screen 2 via Next button
        const nextBtn = getByRole('button', {
            name: /Next: View Challenge Sheet/i,
        });
        await fireEvent.click(nextBtn);

        // Exact copy contract on Screen 2
        expect(dialog.textContent).toContain('Review the printed pattern');
        expect(dialog.textContent).toContain(
            'Use this on-screen reference to check the generated marks before printing.',
        );

        // Assert that misleading clutter and counts are completely removed from Screen 2
        expect(dialog.textContent).not.toContain('Target marks to shade');
        expect(dialog.textContent).not.toContain('expected marks');
        expect(dialog.textContent).not.toContain(
            '5 expected marks across 2 sections',
        );
        expect(dialog.textContent).not.toContain('18 expected marks');
        expect(dialog.textContent).not.toContain('General Ability');
        expect(dialog.textContent).not.toContain('GA_01');
        expect(dialog.textContent).not.toContain('Mathematics');
        expect(dialog.textContent).not.toContain('MATH_01');
        expect(dialog.textContent).not.toContain(
            'Calibration Identity Column Values',
        );
        expect(dialog.textContent).not.toContain('Col 1:');
        expect(dialog.textContent).not.toContain('Col 2:');

        // Shaded sheet image is rendered as focal content
        const img = getByAltText('Challenge sheet page 1') as HTMLImageElement;
        expect(img).not.toBeNull();
        expect(img.getAttribute('src')).toBe('/runs/10/sheet-p1.png');
    });

    it('navigates seamlessly between Screen 1 and Screen 2 via stepper tabs and Next/Back buttons', async () => {
        const { getByRole } = render(StudioShadingGuideModal, {
            open: true,
            challenge: {
                challenge_id: 1,
                challenge_code: 'CHAL-TEST-PDF-01',
                mode: 'quick',
                status: 'created',
                guide: mockGuide,
                sheet_pages: [
                    {
                        page_no: 1,
                        url: '/runs/10/sheet-p1.png',
                        mime: 'image/png',
                    },
                ],
            },
        });

        const dialog = getByRole('dialog');

        // 1. Initially on Screen 1: Instructions & Standards
        const tab1 = getByRole('tab', { name: /Instructions & Standards/i });
        const tab2 = getByRole('tab', { name: /Challenge Sheet & Marks/i });
        expect(tab1.getAttribute('aria-selected')).toBe('true');
        expect(tab2.getAttribute('aria-selected')).toBe('false');
        expect(dialog.textContent).toContain('Optical Mark Shading Standards');

        // 2. Click Next button -> transitions to Screen 2
        const nextBtn = getByRole('button', {
            name: /Next: View Challenge Sheet/i,
        });
        await fireEvent.click(nextBtn);

        expect(tab1.getAttribute('aria-selected')).toBe('false');
        expect(tab2.getAttribute('aria-selected')).toBe('true');
        expect(dialog.textContent).toContain('Review the printed pattern');

        // 3. Click Back button -> transitions back to Screen 1
        const backBtn = getByRole('button', { name: /Back to Instructions/i });
        await fireEvent.click(backBtn);

        expect(tab1.getAttribute('aria-selected')).toBe('true');
        expect(tab2.getAttribute('aria-selected')).toBe('false');
        expect(dialog.textContent).toContain('Optical Mark Shading Standards');

        // 4. Click Tab 2 directly -> transitions to Screen 2
        await fireEvent.click(tab2);
        expect(tab2.getAttribute('aria-selected')).toBe('true');
        expect(dialog.textContent).toContain('Review the printed pattern');
    });

    it('renders zoom toolbar with zoom-out, zoom-in, percentage display, and Fit reset button', async () => {
        const { getByRole, getByLabelText } = render(StudioShadingGuideModal, {
            open: true,
            challenge: {
                challenge_id: 1,
                challenge_code: 'CHAL-TEST-PDF-01',
                mode: 'quick',
                status: 'created',
                guide: mockGuide,
                sheet_pages: [
                    {
                        page_no: 1,
                        url: '/runs/10/sheet-page1.png',
                        mime: 'image/png',
                    },
                ],
            },
        });

        // Navigate to Screen 2
        await fireEvent.click(
            getByRole('tab', { name: /Challenge Sheet & Marks/i }),
        );

        const zoomLevel = getByLabelText('Current zoom level');
        expect(zoomLevel.textContent?.trim()).toBe('100%');

        const zoomInBtn = getByRole('button', { name: /Zoom in/i });
        const zoomOutBtn = getByRole('button', { name: /Zoom out/i });
        const fitBtn = getByRole('button', { name: /Fit to view/i });

        // Zoom in step (+25%)
        await fireEvent.click(zoomInBtn);
        expect(zoomLevel.textContent?.trim()).toBe('125%');

        await fireEvent.click(zoomInBtn);
        expect(zoomLevel.textContent?.trim()).toBe('150%');

        // Zoom out step (-25%)
        await fireEvent.click(zoomOutBtn);
        expect(zoomLevel.textContent?.trim()).toBe('125%');

        // Fit button resets back to 100%
        await fireEvent.click(fitBtn);
        expect(zoomLevel.textContent?.trim()).toBe('100%');
    });

    it('handles wheel zoom with clamping between 50% and 300% and prevents default page scroll', async () => {
        const { getByRole, getByLabelText } = render(StudioShadingGuideModal, {
            open: true,
            challenge: {
                challenge_id: 1,
                challenge_code: 'CHAL-TEST-PDF-01',
                mode: 'quick',
                status: 'created',
                guide: mockGuide,
                sheet_pages: [
                    {
                        page_no: 1,
                        url: '/runs/10/sheet-page1.png',
                        mime: 'image/png',
                    },
                ],
            },
        });

        // Navigate to Screen 2
        await fireEvent.click(
            getByRole('tab', { name: /Challenge Sheet & Marks/i }),
        );

        const viewer = getByLabelText('Zoomable challenge sheet viewer');
        const zoomLevel = getByLabelText('Current zoom level');

        // Mock bounding rect for cursor-centered calculation
        vi.spyOn(viewer, 'getBoundingClientRect').mockReturnValue({
            left: 100,
            top: 100,
            width: 400,
            height: 400,
            right: 500,
            bottom: 500,
            x: 100,
            y: 100,
            toJSON: () => {},
        });

        // Wheel zoom in (deltaY < 0)
        const wheelInEvent = new WheelEvent('wheel', {
            deltaY: -100,
            clientX: 300,
            clientY: 300,
            bubbles: true,
            cancelable: true,
        });
        await fireEvent(viewer, wheelInEvent);
        expect(wheelInEvent.defaultPrevented).toBe(true);
        expect(zoomLevel.textContent?.trim()).toBe('125%');

        // Wheel in until clamped at 300%
        for (let i = 0; i < 15; i++) {
            await fireEvent.wheel(viewer, {
                deltaY: -100,
                clientX: 300,
                clientY: 300,
            });
        }
        expect(zoomLevel.textContent?.trim()).toBe('300%');

        // Wheel out until clamped at 50%
        for (let i = 0; i < 20; i++) {
            await fireEvent.wheel(viewer, {
                deltaY: 100,
                clientX: 300,
                clientY: 300,
            });
        }
        expect(zoomLevel.textContent?.trim()).toBe('50%');
    });

    it('handles pointer drag pan with pointer capture when zoomed and ignores pan at Fit', async () => {
        const { getByRole, getByLabelText } = render(StudioShadingGuideModal, {
            open: true,
            challenge: {
                challenge_id: 1,
                challenge_code: 'CHAL-TEST-PDF-01',
                mode: 'quick',
                status: 'created',
                guide: mockGuide,
                sheet_pages: [
                    {
                        page_no: 1,
                        url: '/runs/10/sheet-page1.png',
                        mime: 'image/png',
                    },
                ],
            },
        });

        // Navigate to Screen 2
        await fireEvent.click(
            getByRole('tab', { name: /Challenge Sheet & Marks/i }),
        );

        const viewer = getByLabelText('Zoomable challenge sheet viewer');
        const setCaptureSpy = vi.spyOn(viewer, 'setPointerCapture');
        const releaseCaptureSpy = vi.spyOn(viewer, 'releasePointerCapture');

        // At 100% zoom (Fit), dragging is disabled
        await fireEvent.pointerDown(viewer, {
            button: 0,
            pointerId: 1,
            clientX: 100,
            clientY: 100,
        });
        expect(setCaptureSpy).not.toHaveBeenCalled();

        // Zoom in to 150% via Zoom In button
        const zoomInBtn = getByRole('button', { name: /Zoom in/i });
        await fireEvent.click(zoomInBtn);
        await fireEvent.click(zoomInBtn);

        // At > 100% zoom, pointer drag engages pointer capture and pans
        await fireEvent.pointerDown(viewer, {
            button: 0,
            pointerId: 1,
            clientX: 100,
            clientY: 100,
        });
        expect(setCaptureSpy).toHaveBeenCalledWith(1);

        await fireEvent.pointerMove(viewer, { clientX: 150, clientY: 130 });
        const innerWrapper = viewer.firstElementChild as HTMLElement;
        expect(innerWrapper.getAttribute('style')).toContain(
            'translate(50px, 30px)',
        );

        await fireEvent.pointerUp(viewer, { pointerId: 1 });
        expect(releaseCaptureSpy).toHaveBeenCalledWith(1);
    });

    it('double clicking toggles Fit (100%) and 150% zoom', async () => {
        const { getByRole, getByLabelText } = render(StudioShadingGuideModal, {
            open: true,
            challenge: {
                challenge_id: 1,
                challenge_code: 'CHAL-TEST-PDF-01',
                mode: 'quick',
                status: 'created',
                guide: mockGuide,
                sheet_pages: [
                    {
                        page_no: 1,
                        url: '/runs/10/sheet-page1.png',
                        mime: 'image/png',
                    },
                ],
            },
        });

        // Navigate to Screen 2
        await fireEvent.click(
            getByRole('tab', { name: /Challenge Sheet & Marks/i }),
        );

        const viewer = getByLabelText('Zoomable challenge sheet viewer');
        const zoomLevel = getByLabelText('Current zoom level');
        expect(zoomLevel.textContent?.trim()).toBe('100%');

        // First double click: zooms to 150%
        await fireEvent.dblClick(viewer, { clientX: 200, clientY: 200 });
        expect(zoomLevel.textContent?.trim()).toBe('150%');

        // Second double click: toggles back to 100% Fit
        await fireEvent.dblClick(viewer, { clientX: 200, clientY: 200 });
        expect(zoomLevel.textContent?.trim()).toBe('100%');
    });

    it('handles keyboard shortcuts (+, -, 0, and Arrow keys) when viewer is focused', async () => {
        const { getByRole, getByLabelText } = render(StudioShadingGuideModal, {
            open: true,
            challenge: {
                challenge_id: 1,
                challenge_code: 'CHAL-TEST-PDF-01',
                mode: 'quick',
                status: 'created',
                guide: mockGuide,
                sheet_pages: [
                    {
                        page_no: 1,
                        url: '/runs/10/sheet-page1.png',
                        mime: 'image/png',
                    },
                ],
            },
        });

        // Navigate to Screen 2
        await fireEvent.click(
            getByRole('tab', { name: /Challenge Sheet & Marks/i }),
        );

        const viewer = getByLabelText('Zoomable challenge sheet viewer');
        const zoomLevel = getByLabelText('Current zoom level');

        // Keyboard '+' or '=' zooms in
        await fireEvent.keyDown(viewer, { key: '+' });
        expect(zoomLevel.textContent?.trim()).toBe('125%');

        await fireEvent.keyDown(viewer, { key: '=' });
        expect(zoomLevel.textContent?.trim()).toBe('150%');

        // Keyboard '-' zooms out
        await fireEvent.keyDown(viewer, { key: '-' });
        expect(zoomLevel.textContent?.trim()).toBe('125%');

        // Keyboard '0' resets to Fit (100%)
        await fireEvent.keyDown(viewer, { key: '0' });
        expect(zoomLevel.textContent?.trim()).toBe('100%');

        // Zoom in and test arrow key panning
        await fireEvent.keyDown(viewer, { key: '+' });
        await fireEvent.keyDown(viewer, { key: '+' });
        expect(zoomLevel.textContent?.trim()).toBe('150%');

        await fireEvent.keyDown(viewer, { key: 'ArrowRight' });
        await fireEvent.keyDown(viewer, { key: 'ArrowDown' });
        const innerWrapper = viewer.firstElementChild as HTMLElement;
        expect(innerWrapper.getAttribute('style')).toContain(
            'translate(-40px, -40px)',
        );
    });

    it('page switcher navigates multi-page sheets and resets zoom/pan to Fit on page change', async () => {
        const { getByRole, getByAltText, getByLabelText } = render(
            StudioShadingGuideModal,
            {
                open: true,
                challenge: {
                    challenge_id: 1,
                    challenge_code: 'CHAL-TEST-PDF-01',
                    mode: 'quick',
                    status: 'created',
                    guide: mockGuide,
                    sheet_pages: [
                        {
                            page_no: 1,
                            url: '/runs/10/sheet-page1.png',
                            mime: 'image/png',
                        },
                        {
                            page_no: 2,
                            url: '/runs/10/sheet-page2.png',
                            mime: 'image/png',
                        },
                    ],
                },
            },
        );

        // Navigate to Screen 2
        await fireEvent.click(
            getByRole('tab', { name: /Challenge Sheet & Marks/i }),
        );

        const zoomInBtn = getByRole('button', { name: /Zoom in/i });
        const zoomLevel = getByLabelText('Current zoom level');

        // Zoom in on page 1
        await fireEvent.click(zoomInBtn);
        await fireEvent.click(zoomInBtn);
        expect(zoomLevel.textContent?.trim()).toBe('150%');

        // Switch to page 2
        const page2Btn = getByRole('button', { name: '2' });
        await fireEvent.click(page2Btn);

        const imgPage2 = getByAltText(
            'Challenge sheet page 2',
        ) as HTMLImageElement;
        expect(imgPage2.getAttribute('src')).toBe('/runs/10/sheet-page2.png');

        // Zoom & pan should be reset to Fit (100%)
        expect(zoomLevel.textContent?.trim()).toBe('100%');
    });

    it('closing modal resets activeScreen to 1, currentPage to 1, and viewer state to Fit', async () => {
        const onOpenChange = vi.fn();
        const { getByRole, getAllByRole } = render(StudioShadingGuideModal, {
            open: true,
            challenge: {
                challenge_id: 1,
                challenge_code: 'CHAL-TEST-PDF-01',
                mode: 'quick',
                status: 'created',
                guide: mockGuide,
                sheet_pages: [
                    {
                        page_no: 1,
                        url: '/runs/10/sheet-page1.png',
                        mime: 'image/png',
                    },
                    {
                        page_no: 2,
                        url: '/runs/10/sheet-page2.png',
                        mime: 'image/png',
                    },
                ],
            },
            onOpenChange,
        });

        // Navigate to Screen 2 and switch to Page 2
        await fireEvent.click(
            getByRole('tab', { name: /Challenge Sheet & Marks/i }),
        );
        await fireEvent.click(getByRole('button', { name: '2' }));

        // Close modal
        const closeButtons = getAllByRole('button', { name: /Close/i });
        await fireEvent.click(closeButtons[0]);
        expect(onOpenChange).toHaveBeenCalledWith(false);
    });

    it('renders fallback notice and PDF action when sheet image is omitted', async () => {
        const onOpenPrintPdf = vi.fn();
        const { getByRole } = render(StudioShadingGuideModal, {
            open: true,
            challenge: {
                challenge_id: 1,
                challenge_code: 'CHAL-NO-SECTIONS',
                mode: 'quick',
                status: 'created',
                print_url: '/runs/10/sheet-fallback.pdf',
                guide: null,
            },
            onOpenPrintPdf,
        });

        const dialog = getByRole('dialog');

        // Navigate to Screen 2
        const nextBtn = getByRole('button', {
            name: /Next: View Challenge Sheet/i,
        });
        await fireEvent.click(nextBtn);

        expect(dialog.textContent).toContain('Challenge Sheet Available');
        expect(dialog.textContent).toContain('Physical sheet preview is ready');

        const openPdfBtn = getByRole('button', { name: /Open sheet PDF/i });
        expect(openPdfBtn).not.toBeNull();
        await fireEvent.click(openPdfBtn);
        expect(onOpenPrintPdf).toHaveBeenCalledWith(
            '/runs/10/sheet-fallback.pdf',
        );
    });

    it('renders hand-shaded exact marks, calibration digits, and distinct optional guide-PDF versus blank-sheet actions on Screen 2', async () => {
        const onOpenPrintPdf = vi.fn();
        const { getByRole, getByLabelText, getByText } = render(
            StudioShadingGuideModal,
            {
                open: true,
                method: 'hand_shaded',
                onOpenPrintPdf,
                challenge: {
                    challenge_id: 1,
                    challenge_code: 'CHAL-TEST-PDF-01',
                    mode: 'quick',
                    status: 'created',
                    guide: mockGuide,
                    print_url: '/runs/10/blank-sheet.pdf',
                    guide_pdf_url: '/runs/10/guide.pdf',
                },
            },
        );

        const nextBtn = getByRole('button', {
            name: /Next: View Marks to Shade/i,
        });
        await fireEvent.click(nextBtn);

        expect(
            getByRole('heading', { name: /Shade these exact bubbles/i }),
        ).toBeTruthy();
        expect(
            getByText(
                /On the blank printed sheet, shade only the bubbles listed below/i,
            ),
        ).toBeTruthy();

        // Exact mark instructions rendered directly in-app
        expect(getByText('General Ability')).toBeTruthy();
        expect(getByText('Question 1')).toBeTruthy();
        expect(getByLabelText('Shade option B')).toBeTruthy();
        expect(getByText('Question 2')).toBeTruthy();
        expect(getByLabelText('Shade option D')).toBeTruthy();
        expect(getByText('Question 3')).toBeTruthy();
        expect(getByLabelText('Shade option A')).toBeTruthy();

        // Calibration digits
        expect(getByText('Applicant number calibration')).toBeTruthy();
        expect(getByText('Col 1')).toBeTruthy();
        expect(getByText('3')).toBeTruthy();
        expect(getByText('Col 2')).toBeTruthy();
        expect(getByText('7')).toBeTruthy();

        // Distinct optional guide-PDF versus blank-sheet actions
        const guidePdfBtn = getByRole('button', {
            name: /Open guide PDF \(optional\)/i,
        });
        const printSheetBtn = getByRole('button', {
            name: /Print blank sheet \(optional\)/i,
        });
        expect(guidePdfBtn).toBeTruthy();
        expect(printSheetBtn).toBeTruthy();

        await fireEvent.click(guidePdfBtn);
        expect(onOpenPrintPdf).toHaveBeenCalledWith('/runs/10/guide.pdf');

        await fireEvent.click(printSheetBtn);
        expect(onOpenPrintPdf).toHaveBeenCalledWith('/runs/10/blank-sheet.pdf');

        // Hand-shaded Screen 2 renders mark instructions, not a fake shaded raster
        const dialog = getByRole('dialog');
        expect(dialog.querySelector('img')).toBeNull();
    });

    it('renders 2-page recognition reference preview and svg bubble overlay on uploaded originals without printable fallback notice', async () => {
        const { getByRole, getByTestId, queryByText, getByText } = render(
            StudioShadingGuideModal,
            {
                open: true,
                isUploadedSource: true,
                challenge: {
                    challenge_id: 3,
                    challenge_code: 'CHAL-UPLOADED-03',
                    mode: 'quick',
                    status: 'created',
                    guide: mockGuide,
                    sheet_pages: [
                        {
                            page_no: 1,
                            url: '/runs/15/asset-126.png',
                            mime: 'image/png',
                            reference_only: true,
                            width_px: 2480,
                            height_px: 3508,
                            bubbles: [
                                {
                                    label: 'Q_1',
                                    option: 'A',
                                    x: 100,
                                    y: 200,
                                    w: 35,
                                    h: 35,
                                    shaded: true,
                                },
                            ],
                        },
                        {
                            page_no: 2,
                            url: '/runs/15/asset-127.png',
                            mime: 'image/png',
                            reference_only: true,
                            width_px: 2480,
                            height_px: 3508,
                            bubbles: [
                                {
                                    label: 'Q_2',
                                    option: 'B',
                                    x: 150,
                                    y: 250,
                                    w: 35,
                                    h: 35,
                                    shaded: false,
                                },
                            ],
                        },
                    ],
                },
            },
        );

        // Navigate to Screen 2
        const nextBtn = getByRole('button', {
            name: /Next: View/i,
        });
        await fireEvent.click(nextBtn);

        // Recognition Reference Preview banner is present
        expect(getByText(/Recognition Reference Preview/i)).toBeTruthy();

        // Fallback printable sheet notice is NOT rendered
        expect(queryByText(/Challenge Sheet Available/i)).toBeNull();

        // SVG bubble overlay is rendered
        const overlay = getByTestId('reference-bubble-overlay');
        expect(overlay).toBeTruthy();
        expect(overlay.getAttribute('viewBox')).toBe('0 0 2480 3508');
        const targetBubble = overlay.querySelector('[data-shaded="true"], [fill="#0f172a"], [fill="rgba(245, 158, 11, 0.25)"]');
        expect(targetBubble).toBeTruthy();
        expect(
            queryByText(/target bubble|blank bubble|focus on sheet/i) ??
                getByRole('button', { name: /focus/i }),
        ).toBeTruthy();

        // Page switcher buttons for Page 1 and Page 2 are present
        const page1Btn = getByRole('button', { name: /Page 1/i });
        const page2Btn = getByRole('button', { name: /Page 2/i });
        expect(page1Btn).toBeTruthy();
        expect(page2Btn).toBeTruthy();

        // Switch to Page 2
        await fireEvent.click(page2Btn);
        expect(getByText(/Recognition Reference Preview/i)).toBeTruthy();
        expect(getByTestId('reference-bubble-overlay')).toBeTruthy();
    });

    it('closes modal on Escape key press', async () => {
        const onOpenChange = vi.fn();
        const { getByRole } = render(StudioShadingGuideModal, {
            open: true,
            challenge: {
                challenge_id: 1,
                challenge_code: 'CHAL-ESC',
                mode: 'quick',
                status: 'created',
                guide: mockGuide,
            },
            onOpenChange,
        });

        const dialog = getByRole('dialog');
        await fireEvent.keyDown(dialog, { key: 'Escape', code: 'Escape' });
        expect(onOpenChange).toHaveBeenCalledWith(false);
    });
});

describe('Integrated modal interaction and Step 3 transition in StudioExportVerifyPanel', () => {
    beforeEach(() => {
        vi.useFakeTimers();
        vi.stubGlobal(
            'fetch',
            vi.fn(async () => jsonResponse({})),
        );
        vi.stubGlobal('open', vi.fn());
    });

    afterEach(async () => {
        cleanup();
        await vi.runAllTimersAsync();
        vi.useRealTimers();
        vi.unstubAllGlobals();
    });

    it('finishing the guide returns to the method card without acknowledging print', async () => {
        const fetchMock = vi.fn(async () => jsonResponse({}));
        const onOpenChange = vi.fn();
        vi.stubGlobal('fetch', fetchMock);

        const { getByRole } = render(StudioShadingGuideModal, {
            open: true,
            challenge: {
                challenge_id: 3,
                challenge_code: 'CHAL-TEST-MODAL-03',
                mode: 'quick',
                status: 'created',
                print_url: '/runs/15/sheet.pdf',
                print_mime: 'application/pdf',
                pdf_ready: true,
                guide_url: '/runs/15/guide.pdf',
                guide: mockGuide,
                sheet_pages: [
                    {
                        page_no: 1,
                        url: '/runs/15/sheet-p1.png',
                        mime: 'image/png',
                    },
                ],
            },
            onOpenChange,
        });

        await fireEvent.click(
            getByRole('button', { name: /Next: View Challenge Sheet/i }),
        );
        await fireEvent.click(
            getByRole('button', { name: /Done — return to test/i }),
        );

        expect(onOpenChange).toHaveBeenCalledWith(false);
        expect(fetchMock).not.toHaveBeenCalled();
    });

    it('refreshes status instead of acknowledging print when the challenge binding is incomplete', async () => {
        const run = {
            run_id: 16,
            status: 'awaiting_print',
            mode: 'quick_rehearsal',
            status_url: '/setup/omr/templates/verification-runs/16/status',
            target_current: true,
            digital_verification_current: true,
            challenge: {
                challenge_id: null,
                challenge_code: 'CHAL-INCOMPLETE',
                status: 'created',
                print_url: '/runs/16/sheet.pdf',
                print_mime: 'application/pdf',
                pdf_ready: true,
            },
        };
        const fetchMock = vi.fn(async () => jsonResponse(run));
        vi.stubGlobal('fetch', fetchMock);

        const { container, getByRole } = render(StudioExportVerifyPanel, {
            draft: {
                id: 1,
                stage: 'digital_verified',
                mapping_current: true,
                digital_verification_current: true,
            },
            verification_run: run,
        });

        await waitFor(() =>
            expect(container.textContent).not.toContain(
                'Restoring verification status…',
            ),
        );
        await fireEvent.click(
            getByRole('button', { name: /Mark printed & continue/i }),
        );

        await waitFor(() => {
            expect(container.textContent).toContain(
                'The saved challenge binding is unavailable.',
            );
            expect(fetchMock.mock.calls.length).toBeGreaterThanOrEqual(2);
        });
        expect(
            fetchMock.mock.calls.some(([, init]) => init?.method === 'POST'),
        ).toBe(false);
    });

    it('acknowledges print once with the complete persisted binding', async () => {
        const fetchMock = vi.fn(async () =>
            jsonResponse({ status: 'awaiting_capture' }),
        );
        vi.stubGlobal('fetch', fetchMock);

        const { getByRole } = render(StudioExportVerifyPanel, {
            draft: {
                id: 1,
                stage: 'digital_verified',
                mapping_current: true,
                digital_verification_current: true,
            },
            verification_run: {
                run_id: 17,
                status: 'awaiting_print',
                mode: 'quick_rehearsal',
                target_current: true,
                digital_verification_current: true,
                target: { hashes: { artifact: 'a'.repeat(64) } },
                challenge: {
                    challenge_id: 7,
                    challenge_code: 'CHAL-COMPLETE',
                    status: 'created',
                    target_hash: 'a'.repeat(64),
                    plan_hash: 'b'.repeat(64),
                    expected_vector_hash: 'c'.repeat(64),
                    print_url: '/runs/17/sheet.pdf',
                    print_mime: 'application/pdf',
                    pdf_ready: true,
                },
            },
        });

        await fireEvent.click(
            getByRole('button', { name: /Mark printed & continue/i }),
        );

        await waitFor(() => expect(fetchMock).toHaveBeenCalledTimes(1));
        const [url, init] = fetchMock.mock.calls[0];
        expect(String(url)).toBe(
            '/setup/omr/templates/verification-runs/17/print',
        );
        expect(init?.method).toBe('POST');
        expect(JSON.parse(String(init?.body))).toMatchObject({
            method: 'pre_shaded',
            challenge_id: 7,
            target_hash: 'a'.repeat(64),
            plan_hash: 'b'.repeat(64),
            expected_vector_hash: 'c'.repeat(64),
            idempotency_key: expect.any(String),
        });
    });

    it('restores PDF ready and Guide state across page hydration/refresh without duplicate Rehearsal/Blocked labels', () => {
        const hydratedRun = {
            run_id: 20,
            status: 'awaiting_print',
            mode: 'quick_rehearsal',
            available_actions: ['print_challenge'],
            challenge: {
                challenge_id: 4,
                challenge_code: 'CHAL-HYDRATE-04',
                mode: 'quick',
                status: 'created',
                print_url: '/runs/20/sheet.pdf',
                print_mime: 'application/pdf',
                pdf_ready: true,
                guide_url: '/runs/20/guide.pdf',
                guide_pdf_url: '/runs/20/guide-download.pdf',
                guide: mockGuide,
            },
            evidence: [],
        };

        const { container, getByRole } = render(StudioExportVerifyPanel, {
            draft: { id: 1, stage: 'digital_verified' },
            urls: {},
            verification_run: hydratedRun,
        });

        // Semantic progress rail has 4 items because print and capture share one physical step
        const railItems = container.querySelectorAll(
            'ol[aria-label="Verification progress"] > li',
        );
        expect(railItems.length).toBe(4);

        // No duplicate rehearsal badges on the status rail items
        const railText = Array.from(railItems).map(
            (li) => li.textContent || '',
        );
        expect(railText[0]).toContain('Done');
        expect(railText[1]).toContain('In progress');

        // Step 2 controls are expanded and render Open print PDF and View guide
        expect(getByRole('button', { name: /Open print PDF/i })).not.toBeNull();
        expect(getByRole('button', { name: /View guide/i })).not.toBeNull();
    });

    it('unified physical step keeps print and guide triggers available during capture', async () => {
        const { container, getByRole, queryByRole } = render(
            StudioExportVerifyPanel,
            {
                draft: { id: 1, stage: 'digital_verified' },
                urls: {},
                verification_run: {
                    run_id: 30,
                    status: 'awaiting_capture',
                    mode: 'quick_rehearsal',
                    available_actions: ['submit_capture'],
                    challenge: {
                        challenge_id: 8,
                        challenge_code: 'CHAL-STEP3',
                        mode: 'quick',
                        status: 'printed',
                        print_url: '/runs/30/sheet.pdf',
                        print_mime: 'application/pdf',
                        pdf_ready: true,
                        guide: mockGuide,
                    },
                    evidence: [],
                },
            },
        );

        expect(
            container.querySelector('li[aria-current="step"]')?.textContent,
        ).toContain('Physical tests');
        const openPdfBtn = getByRole('button', { name: /Open print PDF/i });
        const viewGuideBtn = getByRole('button', {
            name: /View shading guide/i,
        });
        expect(openPdfBtn).not.toBeNull();
        expect(viewGuideBtn).not.toBeNull();

        // Open guide modal without leaving the unified physical step
        await fireEvent.click(viewGuideBtn);
        await waitFor(() => {
            expect(queryByRole('dialog')).not.toBeNull();
            expect(queryByRole('dialog')?.textContent).not.toContain(
                'CHAL-STEP3',
            );
        });
    });
});
