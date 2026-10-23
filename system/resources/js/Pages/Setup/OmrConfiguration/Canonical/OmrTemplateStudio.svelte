<script>

    import { onMount, onDestroy } from 'svelte';
    import { router } from '@inertiajs/svelte';
    import { csrfHeaders, newIdempotencyKey, csrfToken } from '@/lib/utils';
    import { createStudioHistory } from '@/lib/studioHistory';
    import {
        emitNativeMapping,
        emitOverlayBoxes,
        contentBlockSizeMm,
        effectiveRecognitionGeometryMm,
        validateStudioLayout,
        resetPhotoCropCorners,
        rotatePhotoCropCornersClockwise,
        detectStaticPaperCornersFromPixels,
        nextItemNumberForArea,
        remapPageDeclarations,
        syncDeclaredInventoryFromSections,
        snapSectionsToPrintedCircles,
        MIN_BUBBLE_MM,
        MIN_UPLOADED_BUBBLE_MM,
        MIN_STUDIO_SCALE,
        MAX_STUDIO_SCALE,
    } from '@/lib/omrStudioGeometry';
    import {
        createPhysicalSheetInventory,
        physicalInventoryImpact,
        physicalInventoryImpactDigest,
    } from '@/lib/physicalSheetInventory';
    import {
        error as toastError,
        success as toastSuccess,
        warning as toastWarning,
        info as toastInfo,
    } from '@/lib/toast.js';
    import {
        renderFullSheetHtml,
        DEFAULT_TYPOGRAPHY,
        PRESET_150Q_COMPONENTS,
        componentToSectionData,
        createComponent,
        componentBlockSizeMm,
        canvasComponentBlockSizeMm,
        minimumCanvasPitchMm,
        normalizeQuestionGridRowPositions,
        COMPONENT_PATH_FILTERS,
        paletteForPath,
        overlayPaletteForPath,
        COMPONENT_SUITE_PROMPT,
    } from '@/lib/omrComponents';
    import {
        ArrowLeft,
        Monitor,
        Download,
        CheckCircle2,
        XCircle,
        Loader2,
        Sparkles,
        ChevronLeft,
        ChevronRight,
        ChevronUp,
        ChevronDown,
        Upload,
        MoreHorizontal,
        Maximize2,
        Lock,
        Unlock,
        Minus,
        Plus,
        Check,
        Copy,
        AlertTriangle,
        RotateCcw,
        Undo2,
        Redo2,
        FileText,
        Sliders,
        Type,
        AlignLeft,
        AlignCenter,
        AlignRight,
        AlignVerticalSpaceAround,
        ArrowDown,
        ArrowRight,
        ArrowUp,
        Trash2,
        GripVertical,
        MousePointer,
        Hand,
    } from 'lucide-svelte';
    import StudioCanvas from './StudioCanvas.svelte';
    import StudioOverlayPanel from './StudioOverlayPanel.svelte';
    import StudioBubblePanel from './StudioBubblePanel.svelte';
    import StudioExportVerifyPanel from './StudioExportVerifyPanel.svelte';
    import StudioTypographyControls from './StudioTypographyControls.svelte';
    import StudioQrReservationControls from './StudioQrReservationControls.svelte';
    import StudioComponentAppearanceControls from './StudioComponentAppearanceControls.svelte';
    import StudioFiducialInsetControls from './StudioFiducialInsetControls.svelte';

    let {
        draft = {},
        marker_profiles = [],
        aptitude_areas = [],
        test_instrument = null,
        urls = {},
        verification_run = null,
        physical_run_id = null,
        kit_context = null,
    } = $props();
    let activeVerificationRun = $state(verification_run);
    const isReadOnly = $derived(
        !draft.can_edit ||
            draft.stage === 'compiled' ||
            draft.compiled_version_id != null,
    );
    const exportUrl = $derived(
        urls?.export ??
            (draft?.id
                ? `/setup/exam-materials/answer-sheets/${draft.id}/export`
                : null),
    );
    const selectedMarkerProfile = $derived(
        marker_profiles.find(
            (profile) => Number(profile.id) === Number(draft.marker_profile_id),
        ) ?? null,
    );
    const usesFiducials = draft.registration_mode === 'fiducial_anchored';
    const qrEnabled = draft.qr_mode !== 'qr_disabled';
    let orderedPages = $state(
        Array.isArray(draft.ordered_pages) && draft.ordered_pages.length > 0
            ? draft.ordered_pages
            : Array.from(
                  { length: Number(draft.requested_page_count) || 1 },
                  (_, index) => ({
                      page_no: index + 1,
                      kind: 'blank',
                      confirmed: true,
                  }),
              ),
    );
    let activePageNo = $state(
        Math.min(
            Number(draft.requested_page_count) || 1,
            Number(draft.authoring?.studio?.active_page_no) || 1,
        ),
    );
    let pageMutationRunning = $state(false);
    let pendingUploadedPages = $state([]);
    let showUploadedPageConfirmation = $state(false);

    // 4-Step Streamlined Navigation
    let isSidebarCollapsed = $state(false);
    let creatingEditableCopy = $state(false);
    let unlockingDraft = $state(false);
    let currentStage = $state(1);
    const sidebarStage = $derived(currentStage);
    const stageTitles = [
        '1. Source Ingest',
        '2. Page Registration',
        '3. Recognition Mapping',
        '4. Verification & Test',
    ];
    function normalizeStudioStage(stage, mode) {
        const numericStage = Number(stage);
        if (
            !Number.isInteger(numericStage) ||
            numericStage < 1 ||
            numericStage > 4
        )
            return 1;
        if (mode === 'html' && numericStage === 2) return 1;
        if (mode !== 'html' && !modifyUploadedDocument && numericStage === 2)
            return 3;
        return numericStage;
    }
    function availableOverlayPalette(path) {
        return overlayPaletteForPath(path).filter(
            (item) =>
                (usesFiducials || item.kind !== 'markers') &&
                (qrEnabled || item.kind !== 'qr_code'),
        );
    }

    // Stage 1: Base Mode ('html' | 'upload'). Chosen at the entry gate before the canvas appears.
    let stage1Mode = $state(
        draft.source_kind === 'image' ||
            draft.source_kind === 'pdf' ||
            draft.source_kind === 'docx'
            ? 'upload'
            : 'html',
    );
    let baseType = $state(draft.source_kind ?? 'html');
    let hasChosenMode = $state(Boolean(draft.authoring?.studio?.stage1Mode));
    let componentPalettePath = $derived(
        stage1Mode === 'html' ? 'sheet_designer' : 'upload_document',
    );
    let componentPaletteFilter = $derived(
        COMPONENT_PATH_FILTERS[componentPalettePath],
    );
    let modifyUploadedDocument = $state(
        Boolean(draft.authoring?.studio?.modifyUploadedDocument ?? false),
    );
    let currentRasterUrl = $state(draft.raster_url ?? null);
    let sheetImageLoaded = $state(false);

    // Bounded best-effort background preloader for ordered pages
    $effect(() => {
        if (stage1Mode === 'html') return;
        const candidatePages = Array.isArray(orderedPages) ? orderedPages : [];
        const canonicalRasters = Array.isArray(draft.authoring?.canonical?.raster_assets)
            ? draft.authoring.canonical.raster_assets
            : [];
        for (const p of candidatePages) {
            const pageNo = Number(p.page_no);
            const canonicalAsset = canonicalRasters.find((r) => Number(r.page_no) === pageNo);
            let url = null;
            if (canonicalAsset?.asset_id) {
                url = `/setup/omr/templates/canonical/drafts/${draft.id}/assets/${canonicalAsset.asset_id}`;
            } else if (p.raster_url) {
                url = p.raster_url;
            } else if (p.asset_id) {
                url = `/setup/omr/templates/canonical/drafts/${draft.id}/assets/${p.asset_id}`;
            }
            if (url) {
                const img = new Image();
                img.src = url;
            }
        }
    });

    const activePageRasterUrl = $derived.by(() => {
        if (stage1Mode === 'html') return null;

        // 1. Composed raster assets for the active page
        const canonicalRasters = Array.isArray(draft.authoring?.canonical?.raster_assets)
            ? draft.authoring.canonical.raster_assets
            : [];
        const canonicalAsset = canonicalRasters.find(
            (r) => Number(r.page_no) === activePageNo,
        );
        if (canonicalAsset?.asset_id) {
            return `/setup/omr/templates/canonical/drafts/${draft.id}/assets/${canonicalAsset.asset_id}`;
        }

        // 2. Confirmed uploaded/intermediate page asset for active page
        const pageAsset = orderedPages.find(
            (p) => Number(p.page_no) === activePageNo,
        );
        if (pageAsset?.asset_id) {
            return `/setup/omr/templates/canonical/drafts/${draft.id}/assets/${pageAsset.asset_id}`;
        }

        // 3. Fall back to currentRasterUrl or draft.raster_url
        return currentRasterUrl || draft.raster_url || null;
    });
    let declaredInventory = $state(
        Array.isArray(draft.authoring?.studio?.declaredInventory)
            ? draft.authoring.studio.declaredInventory
            : Array.isArray(draft.authoring?.mapping?.input?.declared_inventory)
              ? draft.authoring.mapping.input.declared_inventory.map(
                    (entry, idx) => ({
                        id: entry.id ?? `inventory-${entry.page_no ?? 1}-${idx + 1}`,
                        page_no: Number(entry.page_no ?? 1),
                        area_code: entry.area_code ?? '',
                        question_count: entry.question_count ?? null,
                        option_labels: Array.isArray(entry.option_labels)
                            ? entry.option_labels
                            : [],
                        geometry_id: entry.geometry_id ?? null,
                    }),
                )
              : [],
    );
    let physicalInventory = $state(
        draft.authoring?.studio?.physicalInventory ??
            createPhysicalSheetInventory(
                draft.authoring?.studio?.sections ?? [],
                draft.authoring?.mapping?.input ?? {},
            ),
    );
    let physicalInventoryConfirmation = $state(null);
    let identityDeclaration = $state(
        draft.authoring?.studio?.identity ??
            draft.authoring?.mapping?.input?.identity ?? { method: null },
    );
    let documentFields = $state(
        draft.authoring?.studio?.document_fields ??
            draft.authoring?.mapping?.input?.document_fields ??
            draft.authoring?.canonical?.mapping?.document_fields ??
            [],
    );
    let activeDocumentFieldId = $state(null);

    // Stage 1: Sheet Designer Component State — starts BLANK; components come from the palette.
    let designerSubTab = $state('designer'); // 'designer' | 'raw_html' | 'print_preview'
    let components = $state([]);
    let selectedComponentId = $state(null);
    let selectedComponentIds = $state([]);
    let selectedCanvasIds = $state([]);
    const QR_CANVAS_ITEM_ID = 'qr';
    let componentCopySeq = 0;
    let layerDragId = $state(null);
    let layerDragOverId = $state(null);
    let layerDragPosition = $state(null);
    let showLayersPanel = $state(true);
    let contentMarginInput = $state(null);
    let markerInsetControlsElement = $state(null);

    let selectedComponent = $derived(
        components.find((c) => c.id === selectedComponentId) ?? null,
    );
    const DEFAULT_MARKER_INSETS_MM = {
        top: 12,
        right: 12,
        bottom: 14,
        left: 12,
    };

    function normalizeMarkerInsets(insets) {
        if (!insets || typeof insets !== 'object')
            return { ...DEFAULT_MARKER_INSETS_MM };
        return Object.fromEntries(
            Object.entries(DEFAULT_MARKER_INSETS_MM).map(([side, fallback]) => [
                side,
                Number.isFinite(Number(insets[side]))
                    ? Number(insets[side])
                    : fallback,
            ]),
        );
    }

    function htmlSheetSettings() {
        return {
            paperWidthMm: 210,
            paperHeightMm: 297,
            marginMm,
            markerPattern,
            markerInsetsMm,
        };
    }

    function htmlDecorations() {
        return {
            showFiducials: usesFiducials,
            qr: {
                visible: qrEnabled && showQrOverlay,
                xMm: qrX,
                yMm: qrY,
                sizeMm: Math.min(Number(qrW) || 26, Number(qrH) || 26),
                overlayTag: 'blank_instance_qr',
            },
        };
    }

    function renderCurrentHtml() {
        return renderFullSheetHtml(
            components,
            htmlSheetSettings(),
            htmlDecorations(),
            Number(draft.requested_page_count) || orderedPages.length || 1,
        );
    }
    const activePageHtml = $derived.by(() => {
        if (!htmlCode) return '';
        if ((Number(draft.requested_page_count) || orderedPages.length || 1) <= 1) {
            return htmlCode;
        }
        const pageComps = components.filter(
            (c) => (Number(c.page_no) || 1) === activePageNo,
        );
        return renderFullSheetHtml(
            pageComps.map((c) => ({ ...c, page_no: 1 })),
            htmlSheetSettings(),
            htmlDecorations(),
            1,
        );
    });
    function invalidateDerivedAuthoringState() {
        canonicalPreview = null;
        previewError = null;
        mappingDirty = true;
        if (stage1Mode === 'html') {
            draft.raster_url = null;
        }
        if (draft.stage === 'digital_verified') {
            draft.stage = 'draft';
        }
        draft.digital_verified = false;
        draft.digital_verification_current = false;
        draft.matching_verification = null;
        draft.verification_run = null;
        verification_run = null;
        activeVerificationRun = null;
        physical_run_id = null;
        if (draft.authoring) {
            draft.authoring.verification = null;
            if (stage1Mode === 'html' && draft.authoring.canonical) {
                draft.authoring.canonical = null;
            }
        }
    }

    function remapLocalPages(operation, pageNo, order) {
        const currentOrder = orderedPages.map((page) => Number(page.page_no));
        const nextOrder =
            operation === 'reorder'
                ? order
                : operation === 'remove'
                  ? currentOrder.filter((number) => number !== pageNo)
                  : currentOrder;
        const pageNumberMap = new Map(
            nextOrder.map((oldPageNo, index) => [oldPageNo, index + 1]),
        );
        const remappedDeclarations = remapPageDeclarations(
            declaredInventory,
            identityDeclaration,
            pageNumberMap,
        );
        declaredInventory = remappedDeclarations.inventory;
        identityDeclaration = remappedDeclarations.identity;
        const sourcePageNo = (pageNo) =>
            pageNo === undefined ? 1 : Number(pageNo);
        components = components
            .filter((component) =>
                pageNumberMap.has(sourcePageNo(component.page_no)),
            )
            .map((component) => ({
                ...component,
                page_no: pageNumberMap.get(
                    sourcePageNo(component.page_no),
                ),
            }));
        sections = sections
            .filter((section) =>
                pageNumberMap.has(sourcePageNo(section.page_no)),
            )
            .map((section) => ({
                ...section,
                page_no: pageNumberMap.get(sourcePageNo(section.page_no)),
            }));
    }

    async function updatePages(operation, pageNo = null, order = []) {
        if (isReadOnly || !urls.pages || pageMutationRunning) return false;
        pageMutationRunning = true;
        errorMessage = '';
        try {
            const response = await fetch(urls.pages, {
                method: 'PUT',
                headers: {
                    'Content-Type': 'application/json',
                    Accept: 'application/json',
                    ...csrfHeaders(),
                    'X-Requested-With': 'XMLHttpRequest',
                },
                body: JSON.stringify({
                    operation,
                    ...(pageNo === null ? {} : { page_no: pageNo }),
                    ...(order.length === 0 ? {} : { order }),
                    lock_version: currentLockVersion,
                }),
            });
            const body = await response.json().catch(() => ({}));
            if (!response.ok) {
                throw typedRequestError(
                    body,
                    'Page structure could not be updated.',
                );
            }
            remapLocalPages(operation, pageNo, order);
            orderedPages = body.pages;
            if (!draft.authoring) draft.authoring = {};
            draft.authoring.pages = body.pages;
            draft.requested_page_count = body.requested_page_count;
            draft.stage = body.stage;
            syncDraftLockVersion(body.lock_version);
            if (Array.isArray(body.pages)) {
                const nextUrls = {};
                body.pages.forEach((p) => {
                    const originalAssetId = Number(p.original_asset_id);
                    if (originalAssetId > 0) {
                        nextUrls[p.page_no] = `/setup/omr/templates/canonical/drafts/${draft.id}/assets/${originalAssetId}`;
                    }
                });
                draft.original_photo_urls = nextUrls;
            }
            activePageNo =
                operation === 'add'
                    ? body.requested_page_count
                    : Math.min(activePageNo, body.requested_page_count);
            htmlCode = renderCurrentHtml();
            invalidateDerivedAuthoringState();
            return true;
        } catch (error) {
            errorMessage = error?.message || 'Page structure could not be updated.';
            toastError(errorMessage);
            return false;
        } finally {
            pageMutationRunning = false;
        }
    }

    function removeActivePage() {
        if (
            orderedPages.length <= 1 ||
            !window.confirm(
                `Delete page ${activePageNo} and all components on it? This cannot be undone.`,
            )
        ) {
            return;
        }
        void updatePages('remove', activePageNo);
    }

    function clearActivePageGuides() {
        if (isReadOnly) return;
        const countOnPage = sections.filter(
            (s) => Number(s.page_no || 1) === Number(activePageNo),
        ).length;
        if (countOnPage === 0) {
            toastInfo(`Page ${activePageNo} has no recognition guides to clear.`);
            return;
        }
        if (
            !window.confirm(
                `Clear all ${countOnPage} recognition guide(s) on Page ${activePageNo}? Guides on other pages will remain untouched.`,
            )
        ) {
            return;
        }
        sections = sections.filter(
            (s) => Number(s.page_no || 1) !== Number(activePageNo),
        );
        selectedSectionIndices = [];
        activeSectionIndex = -1;
        declaredInventory = syncDeclaredInventoryFromSections(
            sections,
            declaredInventory,
        );
        invalidateCanonicalPreview();
        recordHistory(`Cleared recognition guides on Page ${activePageNo}`);
        triggerAutosave();
        toastSuccess(`Cleared recognition guides on Page ${activePageNo}.`);
    }


    function moveActivePage(direction) {
        if (isReadOnly) return;
        const index = orderedPages.findIndex(
            (page) => Number(page.page_no) === activePageNo,
        );
        const target = index + direction;
        if (index < 0 || target < 0 || target >= orderedPages.length) return;
        const order = orderedPages.map((page) => Number(page.page_no));
        [order[index], order[target]] = [order[target], order[index]];
        void updatePages('reorder', null, order).then((saved) => {
            if (saved) activePageNo = target + 1;
        });
    }

    function handleHtmlDecorationChanged() {
        if (components.length > 0) htmlCode = renderCurrentHtml();
        invalidateDerivedAuthoringState();
        recordHistory('Edit layout', { coalesceKey: 'layout' });
        triggerAutosave();
    }

    function componentLabel(c) {
        switch (c.type) {
            case 'header':
                return 'Document Header';
            case 'candidate_name':
                return 'Candidate Name';
            case 'birthdate':
                return 'Birthdate';
            case 'room_seat':
                return 'Room / Seat';
            case 'instructions':
                return 'Instructions';
            case 'roll':
                return 'Examinee ID Matrix';
            case 'mcq':
            case 'tf':
            case 'likert':
            case 'custom':
                return c.subjectName || c.name || 'Question Grid';
            case 'custom_text':
                return 'Custom Text';
            case 'custom_input':
                return 'Custom Input Box';
            case 'signature':
                return 'Signature Box';
            default:
                return c.type;
        }
    }
    function updateSelectedComponent(patch) {
        if (!selectedComponent) return;
        Object.assign(selectedComponent, patch);
        syncComponentsToSections();
    }

    function syncComponentsToSections(label = 'Edit component', options = {}) {
        normalizeComponentsForCanvas();
        htmlCode = components.length > 0 ? renderCurrentHtml() : '';
        if (!options.preserveSections) {
            sections = components
                .map((c) => componentToSectionData(c))
                .filter((s) => s !== null);
        }
        invalidateDerivedAuthoringState();
        if (options.recordHistory !== false) {
            const historyOptions = options.coalesceKey
                ? options
                : label === 'Edit component' && selectedComponentId
                  ? { coalesceKey: `component:${selectedComponentId}` }
                  : {};
            recordHistory(label, historyOptions);
        }
        if (options.autosave !== false) triggerAutosave();
    }

    const PAGE_W = 210;
    const PAGE_H = 297;
    function normalizeRecognitionSection(section) {
        if (
            !section ||
            !['roll', 'mcq', 'tf', 'likert', 'custom'].includes(section.type)
        ) {
            return section;
        }
        section.page_no = Math.min(
            Number(draft.requested_page_count) || orderedPages.length || 1,
            Math.max(1, Number(section.page_no) || 1),
        );
        const geometry = effectiveRecognitionGeometryMm({
            kind: section.type === 'roll' ? 'roll' : 'grid',
            bubbleDiameterMm: section.bubbleDiameterMm,
            bubblePitchMm: section.bubblePitchMm,
            rowPitchMm: section.rowPitchMm,
            minBubbleMm: stage1Mode !== 'html' ? 2.5 : undefined,
        });
        if (
            Number(section.bubbleDiameterMm) === geometry.bubbleDiameterMm &&
            Number(section.bubblePitchMm) === geometry.bubblePitchMm &&
            Number(section.rowPitchMm) === geometry.rowPitchMm
        ) {
            return section;
        }
        return {
            ...section,
            bubbleDiameterMm: geometry.bubbleDiameterMm,
            bubblePitchMm: geometry.bubblePitchMm,
            rowPitchMm: geometry.rowPitchMm,
        };
    }

    function normalizeSectionsForRecognition() {
        let changed = false;
        sections = sections.map((section) => {
            const normalized = normalizeRecognitionSection(section);
            changed ||= normalized !== section;
            return normalized;
        });
        return changed;
    }

    function normalizeComponentsForCanvas() {
        const printableWidth = PAGE_W - marginMm * 2;
        const printableHeight = PAGE_H - marginMm * 2;
        const clamp = (value, min, max, fallback) =>
            Math.min(
                max,
                Math.max(
                    min,
                    Number.isFinite(Number(value)) ? Number(value) : fallback,
                ),
            );

        for (const component of components) {
            component.page_no = Math.min(
                Number(draft.requested_page_count) || orderedPages.length || 1,
                Math.max(1, Number(component.page_no) || 1),
            );
            const isRoll = component.type === 'roll';
            const isGrid = ['mcq', 'tf', 'likert', 'custom'].includes(
                component.type,
            );
            if (isRoll || isGrid) {
                const geometry = effectiveRecognitionGeometryMm({
                    kind: isRoll ? 'roll' : 'grid',
                    bubbleDiameterMm: clamp(
                        component.bubbleDiameterMm,
                        4.2,
                        8,
                        4.5,
                    ),
                    bubblePitchMm: component.bubblePitchMm,
                    rowPitchMm: component.rowPitchMm,
                });
                const minimumPitch = minimumCanvasPitchMm(
                    geometry.bubbleDiameterMm,
                );
                const maximumPitch = isRoll ? 15 : 25;
                component.bubbleDiameterMm = geometry.bubbleDiameterMm;
                component.bubblePitchMm = clamp(
                    geometry.bubblePitchMm,
                    minimumPitch,
                    maximumPitch,
                    minimumPitch,
                );
                component.rowPitchMm = clamp(
                    geometry.rowPitchMm,
                    minimumPitch,
                    maximumPitch,
                    minimumPitch,
                );

                if (isRoll) {
                    component.digitsCount = clamp(
                        component.digitsCount,
                        2,
                        10,
                        7,
                    );
                } else {
                    component.labelFontSizePt = clamp(
                        component.labelFontSizePt,
                        5,
                        12,
                        6.5,
                    );
                    component.questionsCount = clamp(
                        component.questionsCount,
                        1,
                        50,
                        25,
                    );
                    while (
                        component.questionsCount > 1 &&
                        canvasComponentBlockSizeMm(component).h_mm >
                            printableHeight
                    ) {
                        component.questionsCount -= 1;
                    }
                }

                while (
                    component.bubblePitchMm > minimumPitch &&
                    canvasComponentBlockSizeMm(component).w_mm > printableWidth
                ) {
                    component.bubblePitchMm = Math.max(
                        minimumPitch,
                        Number(
                            (Number(component.bubblePitchMm) - 0.1).toFixed(2),
                        ),
                    );
                }
            }
            const size = canvasComponentBlockSizeMm(component);
            const maxX = Math.max(marginMm, PAGE_W - marginMm - size.w_mm);
            const maxY = Math.max(marginMm, PAGE_H - marginMm - size.h_mm);
            component.posX = clamp(component.posX, marginMm, maxX, marginMm);
            component.posY = clamp(component.posY, marginMm, maxY, marginMm);
        }
        normalizeQuestionGridRowPositions(components, marginMm, PAGE_W, 2);
    }
    const MIN_INSET = 6;

    function addComponent(kind, point = null) {
        const comp = createComponent(kind, { pageNo: activePageNo });
        if (kind === 'question_grid') {
            const existingGrids = components.filter(
                (c) =>
                    c.type === 'mcq' ||
                    c.type === 'tf' ||
                    c.type === 'likert' ||
                    c.type === 'custom',
            );
            if (existingGrids.length > 0) {
                const nextStartQ = existingGrids.reduce(
                    (max, g) =>
                        Math.max(
                            max,
                            (Number(g.startQ) || 1) +
                                (Number(g.questionsCount) || 25),
                        ),
                    1,
                );
                comp.startQ = nextStartQ;
                comp.name = `Part ${existingGrids.length + 1}: Subject`;
                comp.subjectName = `${existingGrids.length + 1}. Subject`;
            }
        }
        if (
            point &&
            Number.isFinite(point.posX) &&
            Number.isFinite(point.posY)
        ) {
            comp.posX = Math.max(
                MIN_INSET,
                Math.min(PAGE_W - MIN_INSET - Number(comp.w_mm), point.posX),
            );
            comp.posY = Math.max(
                MIN_INSET,
                Math.min(PAGE_H - MIN_INSET - Number(comp.h_mm), point.posY),
            );
        } else {
            if (kind === 'question_grid') {
                const pageGrids = components.filter(
                    (c) =>
                        (Number(c.page_no) || 1) === activePageNo &&
                        (c.type === 'mcq' ||
                            c.type === 'tf' ||
                            c.type === 'likert' ||
                            c.type === 'custom'),
                );
                comp.posX = Math.min(
                    PAGE_W - 15 - 28,
                    15 + pageGrids.length * 31,
                );
            } else {
                const sameKindCount = components.filter(
                    (c) =>
                        (Number(c.page_no) || 1) === activePageNo &&
                        c.type === comp.type,
                ).length;
                if (sameKindCount > 0) {
                    comp.posX = Math.min(
                        PAGE_W - 15 - Number(comp.w_mm || 40),
                        comp.posX + ((sameKindCount * 8) % 40),
                    );
                    comp.posY = Math.min(
                        PAGE_H - 15 - Number(comp.h_mm || 12),
                        comp.posY + ((sameKindCount * 8) % 40),
                    );
                }
            }
        }
        components = normalizeLayerOrder([...components, comp]);
        selectedCanvasIds = [comp.id];
        selectedComponentIds = [comp.id];
        selectedComponentId = comp.id;
        syncComponentsToSections(`Add ${componentLabel(comp)}`);
    }
    function addUploadTextOverlay(kind) {
        const labels = {
            candidate_name: 'CANDIDATE NAME',
            birthdate: 'BIRTHDATE (MM / DD / YYYY)',
            room_seat: 'ROOM / SEAT / GROUP NO.',
            custom_text: 'Additional Instruction / Note',
        };
        const nextId = customTextItems.length + 1;
        customTextItems = [
            ...customTextItems,
            {
                id: `custom_text_${nextId}`,
                name: `Text ${nextId}`,
                content: labels[kind] || labels.custom_text,
                fontSize: 10,
                fontFamily: 'Inter, sans-serif',
                color: '#0f172a',
                align: 'left',
                bold: false,
                italic: false,
                posX: 25,
                posY: Math.min(260, 230 + nextId * 10),
                w_mm: 80,
                h_mm: 15,
                isLocked: false,
            },
        ];
        recordHistory('Add overlay text');
        triggerAutosave();
    }

    function handlePaletteItem(item) {
        if (componentPalettePath === 'sheet_designer') {
            addComponent(item.kind);
            return;
        }

        if (item.kind === 'header') showHeaderOverlay = true;
        else if (item.kind === 'instructions') showInstructionsOverlay = true;
        else if (item.kind === 'signature') showSignatureOverlay = true;
        else {
            addUploadTextOverlay(item.kind);
            return;
        }
        invalidateDerivedAuthoringState();
        recordHistory(`Enable ${item.label}`);
        triggerAutosave();
    }

    function focusLayoutControl(element) {
        requestAnimationFrame(() => {
            element?.scrollIntoView?.({ behavior: 'smooth', block: 'nearest' });
            element?.focus?.();
        });
    }

    function handleOverlayPaletteItem(kind) {
        if (kind === 'markers' && !usesFiducials) return;
        if (kind === 'qr_code' && !qrEnabled) return;
        if (kind === 'margins') {
            if (componentPalettePath === 'sheet_designer')
                focusLayoutControl(contentMarginInput);
            else requestGoToStage(2);
            return;
        }
        if (kind === 'markers') {
            if (componentPalettePath === 'sheet_designer') {
                focusLayoutControl(
                    markerInsetControlsElement?.querySelector('input'),
                );
            } else {
                requestGoToStage(2);
            }
            return;
        }
        if (kind === 'qr_code') {
            showQrOverlay = true;
            selectCanvasItem(QR_CANVAS_ITEM_ID);
            handleHtmlDecorationChanged();
        }
    }

    function syncCanvasSelection(nextIds, primaryId = null) {
        selectedCanvasIds = [...new Set(nextIds)];
        selectedComponentIds = selectedCanvasIds.filter(
            (id) => id !== QR_CANVAS_ITEM_ID,
        );
        selectedComponentId = selectedComponentIds.includes(primaryId)
            ? primaryId
            : (selectedComponentIds.at(-1) ?? null);
        return selectedCanvasIds;
    }

    function selectCanvasItem(id, additive = false) {
        const currentSelection = selectedCanvasIds;
        const nextSelection = additive
            ? currentSelection.includes(id)
                ? currentSelection.filter((selectedId) => selectedId !== id)
                : [...currentSelection, id]
            : [id];
        const selection = syncCanvasSelection(nextSelection, id);
        if (id !== QR_CANVAS_ITEM_ID) {
            const idx = sections.findIndex((s) => s.id === id);
            if (idx >= 0) activeSectionIndex = idx;
        }
        return selection;
    }

    function selectComponent(id, additive = false) {
        return selectCanvasItem(id, additive);
    }

    function clearComponentSelection() {
        return syncCanvasSelection([]);
    }

    function handleCanvasComponentSelected(id, additive = false) {
        return selectCanvasItem(id, additive);
    }

    function handleCanvasItemSelected(id, additive = false) {
        return selectCanvasItem(id, additive);
    }

    function removeComponent(id) {
        components = components.filter((c) => c.id !== id);
        selectedCanvasIds = selectedCanvasIds.filter(
            (selectedId) => selectedId !== id,
        );
        selectedComponentIds = selectedCanvasIds.filter(
            (selectedId) => selectedId !== QR_CANVAS_ITEM_ID,
        );
        if (selectedComponentId === id)
            selectedComponentId = selectedComponentIds.at(-1) ?? null;
        syncComponentsToSections('Remove component');
    }
    function batchAlign(direction) {
        if (currentStage === 1) {
            const selected = components.filter((component) =>
                selectedComponentIds.includes(component.id),
            );
            if (selected.length < 2) return;
            const target =
                direction === 'left'
                    ? Math.min(...selected.map((item) => Number(item.posX) || 0))
                    : direction === 'right'
                      ? Math.max(
                            ...selected.map(
                                (item) =>
                                    (Number(item.posX) || 0) +
                                    canvasComponentBlockSizeMm(item).w_mm,
                            ),
                        )
                      : direction === 'top'
                        ? Math.min(...selected.map((item) => Number(item.posY) || 0))
                        : Math.max(
                              ...selected.map(
                                  (item) =>
                                      (Number(item.posY) || 0) +
                                      canvasComponentBlockSizeMm(item).h_mm,
                              ),
                          );
            for (const item of selected) {
                const size = canvasComponentBlockSizeMm(item);
                if (direction === 'left') item.posX = target;
                if (direction === 'right') item.posX = target - size.w_mm;
                if (direction === 'top') item.posY = target;
                if (direction === 'bottom') item.posY = target - size.h_mm;
            }
            syncComponentsToSections('Align components ' + direction);
            return;
        }
        if (currentStage !== 3 || selectedSectionIndices.length < 2) return;
        const selected = selectedSectionIndices
            .map((index) => ({ index, section: sections[index] }))
            .filter(({ section }) => section);
        const target =
            direction === 'left'
                ? Math.min(...selected.map(({ section }) => Number(section.posX) || 0))
                : direction === 'right'
                  ? Math.max(
                        ...selected.map(
                            ({ section }) =>
                                (Number(section.posX) || 0) +
                                (Number(section.w_mm) || 0),
                        ),
                    )
                  : direction === 'top'
                    ? Math.min(...selected.map(({ section }) => Number(section.posY) || 0))
                    : Math.max(
                          ...selected.map(
                              ({ section }) =>
                                  (Number(section.posY) || 0) +
                                  (Number(section.h_mm) || 0),
                          ),
                      );
        for (const { section } of selected) {
            const deltaX =
                direction === 'left'
                    ? target - (Number(section.posX) || 0)
                    : direction === 'right'
                      ? target -
                        ((Number(section.posX) || 0) +
                            (Number(section.w_mm) || 0))
                      : 0;
            const deltaY =
                direction === 'top'
                    ? target - (Number(section.posY) || 0)
                    : direction === 'bottom'
                      ? target -
                        ((Number(section.posY) || 0) +
                            (Number(section.h_mm) || 0))
                      : 0;
            section.posX = (Number(section.posX) || 0) + deltaX;
            section.posY = (Number(section.posY) || 0) + deltaY;
            if (Array.isArray(section.optionPointsMm)) {
                section.optionPointsMm = section.optionPointsMm.map((point) => ({
                    ...point,
                    ...(point.x_mm != null
                        ? { x_mm: Number(point.x_mm) + deltaX }
                        : { x: Number(point.x) + deltaX }),
                    ...(point.y_mm != null
                        ? { y_mm: Number(point.y_mm) + deltaY }
                        : { y: Number(point.y) + deltaY }),
                }));
            }
        }
        syncComponentsToSections('Align sections ' + direction, {
            preserveSections: true,
        });
    }

    function batchDistribute(axis) {
        const stageOne = currentStage === 1;
        const selected = stageOne
            ? components.filter((item) => selectedComponentIds.includes(item.id))
            : currentStage === 3
              ? selectedSectionIndices
                    .map((index) => ({ index, item: sections[index] }))
                    .filter(({ item }) => item)
                    .map(({ item }) => item)
              : [];
        if (selected.length < 3) return;
        const horizontal = axis === 'horizontal';
        const positionKey = horizontal ? 'posX' : 'posY';
        const sizeKey = horizontal ? 'w_mm' : 'h_mm';
        const ordered = [...selected].sort(
            (a, b) => Number(a[positionKey]) - Number(b[positionKey]),
        );
        const sizeOf = (item) =>
            stageOne
                ? canvasComponentBlockSizeMm(item)[sizeKey]
                : Number(item[sizeKey]) || 0;
        const firstPosition = Number(ordered[0][positionKey]) || 0;
        const lastEnd =
            (Number(ordered.at(-1)[positionKey]) || 0) +
            sizeOf(ordered.at(-1));
        const gap =
            (lastEnd -
                firstPosition -
                ordered.reduce((total, item) => total + sizeOf(item), 0)) /
            (ordered.length - 1);
        let nextPosition = firstPosition;
        for (const item of ordered) {
            const delta = nextPosition - (Number(item[positionKey]) || 0);
            item[positionKey] = nextPosition;
            if (!stageOne && Array.isArray(item.optionPointsMm)) {
                item.optionPointsMm = item.optionPointsMm.map((point) => ({
                    ...point,
                    ...(horizontal
                        ? point.x_mm != null
                            ? { x_mm: Number(point.x_mm) + delta }
                            : { x: Number(point.x) + delta }
                        : {}),
                    ...(!horizontal
                        ? point.y_mm != null
                            ? { y_mm: Number(point.y_mm) + delta }
                            : { y: Number(point.y) + delta }
                        : {}),
                }));
            }
            nextPosition += sizeOf(item) + gap;
        }
        if (stageOne) {
            syncComponentsToSections(
                `Distribute components ${axis}`,
            );
        } else {
            syncComponentsToSections(`Distribute sections ${axis}`, {
                preserveSections: true,
            });
        }
    }

    function batchToggleLock(lockState) {
        if (currentStage === 1) {
            const selected = components.filter((item) =>
                selectedComponentIds.includes(item.id),
            );
            if (selected.length < 2) return;
            const nextLocked =
                lockState ?? selected.some((item) => !item.isLocked);
            for (const item of selected) item.isLocked = nextLocked;
            syncComponentsToSections(
                nextLocked ? 'Lock selected components' : 'Unlock selected components',
            );
            return;
        }
        if (currentStage === 3 && selectedSectionIndices.length >= 2) {
            const selected = selectedSectionIndices
                .map((index) => sections[index])
                .filter(Boolean);
            const nextLocked =
                lockState ?? selected.some((item) => !item.isLocked);
            for (const item of selected) item.isLocked = nextLocked;
            syncComponentsToSections(
                nextLocked ? 'Lock selected sections' : 'Unlock selected sections',
                { preserveSections: true },
            );
        }
    }

    function batchDeleteSelected() {
        if (currentStage === 1) {
            removeSelectedComponents();
            return;
        }
        if (currentStage !== 3 || selectedSectionIndices.length < 2) return;
        const removed = new Set(selectedSectionIndices);
        sections = sections.filter((_, index) => !removed.has(index));
        selectedSectionIndices = [];
        activeSectionIndex = -1;
        syncComponentsToSections('Delete selected sections', {
            preserveSections: true,
        });
    }

    function removeSelectedComponents() {
        const ids =
            selectedCanvasIds.length > 0
                ? selectedCanvasIds
                : selectedComponentId
                  ? [selectedComponentId]
                  : [];
        if (ids.length === 0) return;
        const idSet = new Set(ids);
        components = components.filter((component) => !idSet.has(component.id));
        if (idSet.has(QR_CANVAS_ITEM_ID)) showQrOverlay = false;
        clearComponentSelection();
        syncComponentsToSections('Delete selected items');
    }

    function duplicateSelectedComponents(fallbackId = null, options = {}) {
        const currentSelection =
            selectedComponentIds.length > 0
                ? selectedComponentIds
                : selectedComponentId
                  ? [selectedComponentId]
                  : [];
        const ids =
            fallbackId && !currentSelection.includes(fallbackId)
                ? [fallbackId]
                : currentSelection;
        const sourceComponents = components.filter(
            (component) => ids.includes(component.id) && !component.isLocked,
        );
        if (sourceComponents.length === 0) return [];

        const usedIds = new Set(components.map((component) => component.id));
        const copies = sourceComponents.map((component) => {
            const copy = structuredClone($state.snapshot(component));
            let nextId = '';
            do {
                componentCopySeq += 1;
                nextId = `${component.id}_copy_${componentCopySeq}`;
            } while (usedIds.has(nextId));
            usedIds.add(nextId);
            copy.id = nextId;
            copy.posX = Math.min(
                PAGE_W - MIN_INSET - (Number(copy.w_mm) || 40),
                (Number(copy.posX) || 10) + 8,
            );
            copy.posY = Math.min(
                PAGE_H - MIN_INSET - (Number(copy.h_mm) || 12),
                (Number(copy.posY) || 10) + 8,
            );
            copy.isLocked = false;
            return copy;
        });

        components = normalizeLayerOrder([...components, ...copies]);
        selectedCanvasIds = copies.map((copy) => copy.id);
        selectedComponentIds = [...selectedCanvasIds];
        selectedComponentId = selectedComponentIds.at(-1) ?? null;
        syncComponentsToSections(
            `Duplicate ${copies.length} component${copies.length === 1 ? '' : 's'}`,
            options.deferHistory
                ? { recordHistory: false, autosave: false }
                : {},
        );
        return copies;
    }
    function normalizeLayerOrder(nextComponents) {
        return nextComponents.map((component, index) => ({
            ...component,
            zIndex: index + 1,
        }));
    }
    function normalizeLoadedComponent(component) {
        if (
            component.type === 'candidate_name' ||
            component.type === 'birthdate' ||
            component.type === 'room_seat'
        ) {
            return {
                ...component,
                inputStyle: component.inputStyle ?? 'box',
                inputKind:
                    component.inputKind ??
                    (component.type === 'birthdate' ? 'date' : 'text'),
                inputBackgroundColor:
                    component.inputBackgroundColor ?? '#ffffff',
                typography: component.typography ?? {
                    ...DEFAULT_TYPOGRAPHY,
                    fontSizePt: 6.5,
                    fontWeight: 800,
                    textColor: '#475569',
                },
            };
        }
        if (component.type === 'custom_input') {
            return {
                ...component,
                inputStyle: component.inputStyle ?? 'box',
                inputKind: component.inputKind ?? 'text',
                inputBackgroundColor:
                    component.inputBackgroundColor ?? '#ffffff',
                typography: component.typography ?? {
                    ...DEFAULT_TYPOGRAPHY,
                    fontSizePt: 7,
                    fontWeight: 700,
                    textColor: '#475569',
                },
            };
        }
        if (component.type === 'instructions') {
            return {
                ...component,
                label: component.label ?? 'INSTRUCTIONS',
            };
        }
        if (component.type === 'roll') {
            return {
                ...component,
                bubbleDiameterMm: component.bubbleDiameterMm ?? 4.5,
                typography: component.typography ?? {
                    ...DEFAULT_TYPOGRAPHY,
                    fontSizePt: 7.5,
                    fontWeight: 700,
                    textColor: '#475569',
                    textAlign: 'center',
                },
            };
        }
        return component;
    }

    function reorderComponentLayers(fromId, toId) {
        const fromIndex = components.findIndex(
            (component) => component.id === fromId,
        );
        const toIndex = components.findIndex(
            (component) => component.id === toId,
        );
        if (fromIndex < 0 || toIndex < 0 || fromIndex === toIndex) return;
        const next = [...components];
        const [moved] = next.splice(fromIndex, 1);
        next.splice(toIndex, 0, moved);
        components = normalizeLayerOrder(next);
        syncComponentsToSections('Reorder layers');
    }

    function moveComponentLayer(id, direction) {
        const index = components.findIndex((component) => component.id === id);
        const target = index + direction;
        if (index < 0 || target < 0 || target >= components.length) return;
        reorderComponentLayers(id, components[target].id);
    }
    function moveSelectedLayerToEdge(toFront) {
        const ids = new Set(
            selectedComponentIds.length > 0
                ? selectedComponentIds
                : selectedComponentId
                  ? [selectedComponentId]
                  : [],
        );
        if (ids.size === 0) return;
        const selected = components.filter((component) =>
            ids.has(component.id),
        );
        const remaining = components.filter(
            (component) => !ids.has(component.id),
        );
        components = normalizeLayerOrder(
            toFront ? [...remaining, ...selected] : [...selected, ...remaining],
        );
        syncComponentsToSections(toFront ? 'Bring to front' : 'Send to back');
    }

    function reorderLayerPosition(fromId, toId, position = 'above') {
        const fromIndex = components.findIndex(
            (component) => component.id === fromId,
        );
        const toIndex = components.findIndex(
            (component) => component.id === toId,
        );
        if (fromIndex < 0 || toIndex < 0 || fromIndex === toIndex) return;
        const next = [...components];
        const [moved] = next.splice(fromIndex, 1);
        const newTargetIndex = next.findIndex(
            (component) => component.id === toId,
        );
        const insertIndex =
            position === 'above' ? newTargetIndex : newTargetIndex + 1;
        next.splice(insertIndex, 0, moved);
        components = normalizeLayerOrder(next);
        syncComponentsToSections('Reorder layers');
    }

    function handleLayerDragStart(event, id) {
        layerDragId = id;
        layerDragOverId = null;
        layerDragPosition = null;
        try {
            event.dataTransfer?.setData('application/x-omr-layer', id);
            event.dataTransfer?.setData('text/plain', id);
            event.dataTransfer.effectAllowed = 'move';
        } catch {}
    }

    function handleLayerDragOver(event, id) {
        event.preventDefault();
        try {
            if (event.dataTransfer) event.dataTransfer.dropEffect = 'move';
        } catch {}
        if (layerDragId === id) return;
        const rect = event.currentTarget.getBoundingClientRect();
        const midY = rect.top + rect.height / 2;
        layerDragPosition = event.clientY < midY ? 'above' : 'below';
        layerDragOverId = id;
    }

    function handleLayerDragLeave(event, id) {
        if (
            layerDragOverId === id &&
            !event.currentTarget.contains(event.relatedTarget)
        ) {
            layerDragOverId = null;
            layerDragPosition = null;
        }
    }

    function handleLayerDragEnd() {
        layerDragId = null;
        layerDragOverId = null;
        layerDragPosition = null;
    }

    function handleLayerDrop(event, targetId) {
        event.preventDefault();
        const sourceId =
            layerDragId ||
            event.dataTransfer?.getData('application/x-omr-layer') ||
            event.dataTransfer?.getData('text/plain');
        if (sourceId && sourceId !== targetId) {
            reorderLayerPosition(
                sourceId,
                targetId,
                layerDragPosition || 'above',
            );
        }
        layerDragId = null;
        layerDragOverId = null;
        layerDragPosition = null;
    }

    function handleLayerKeyDown(event, id, index) {
        if (event.key === 'ArrowUp' && (event.altKey || event.metaKey)) {
            event.preventDefault();
            if (index > 0) {
                reorderLayerPosition(id, components[index - 1].id, 'above');
            }
        } else if (
            event.key === 'ArrowDown' &&
            (event.altKey || event.metaKey)
        ) {
            event.preventDefault();
            if (index < components.length - 1) {
                reorderLayerPosition(id, components[index + 1].id, 'below');
            }
        }
    }

    function handlePaletteDrop(e) {
        if (isReadOnly) return;
        const kind =
            e.dataTransfer?.getData('application/x-omr-component') ||
            window.__omrDragKind;
        if (!kind) return;
        // Convert the drop pointer position to sheet-local mm using the transformed sheet rect.
        const sheet = document.querySelector('[data-omr-sheet]');
        let point = null;
        if (sheet) {
            const rect = sheet.getBoundingClientRect();
            if (rect.width > 0 && rect.height > 0) {
                const px = ((e.clientX - rect.left) / rect.width) * 520;
                const py = ((e.clientY - rect.top) / rect.height) * 735;
                point = {
                    posX: Math.round(px / 2.476 / 2) * 2,
                    posY: Math.round(py / 2.476 / 2) * 2,
                };
            }
        }
        addComponent(kind, point);
    }

    function chooseMode(mode) {
        stage1Mode = mode;
        baseType = mode === 'html' ? 'html' : 'image';
        hasChosenMode = true;
        triggerAutosave();
        invalidateDerivedAuthoringState();
    }

    function applyPreset(presetName) {
        if (presetName === '150q') {
            components = JSON.parse(JSON.stringify(PRESET_150Q_COMPONENTS));
        } else if (presetName === '100q') {
            const copy = JSON.parse(JSON.stringify(PRESET_150Q_COMPONENTS));
            copy[0].title = 'University Standard Aptitude Exam';
            copy[0].subtitle = '100-Item Examination Answer Sheet • ISO A4';
            copy[0].formCode = 'FORM 100-B';
            components = copy.filter(
                (c) => !['Part_5', 'Part_6'].includes(c.id),
            );
        } else if (presetName === '50q') {
            const copy = JSON.parse(JSON.stringify(PRESET_150Q_COMPONENTS));
            copy[0].title = 'Short Evaluation Assessment';
            copy[0].subtitle = '50-Item Examination Answer Sheet • ISO A4';
            copy[0].formCode = 'FORM 50-C';
            components = copy.filter(
                (c) => !['Part_3', 'Part_4', 'Part_5', 'Part_6'].includes(c.id),
            );
        }
        components = normalizeLayerOrder(components);
        selectedComponentId = null;
        selectedComponentIds = [];
        selectedCanvasIds = [];
        syncComponentsToSections(`Apply ${presetName} preset`);
    }

    function scaffoldFromAptitudeAreas() {
        const canonicalAreaCodes = [
            'LOGIC',
            'VERBAL',
            'QUANT',
            'SPATIAL',
            'MEMORY',
            'LANGUAGE',
        ];
        const areas = canonicalAreaCodes
            .map((code) => aptitude_areas?.find((area) => area.code === code))
            .filter(Boolean)
            .slice(0, 6);
        if (areas.length === 0) return;

        const pageCount = Math.max(1, Number(draft.requested_page_count) || orderedPages.length || 1);
        const pageIndexMap = new Map();
        let startQ = 1;
        const newGrids = areas.map((area, idx) => {
            const qCount = 25;
            const targetPage = Math.min(
                pageCount,
                Math.floor((idx * pageCount) / areas.length) + 1,
            );
            const indexOnPage = pageIndexMap.get(targetPage) ?? 0;
            pageIndexMap.set(targetPage, indexOnPage + 1);
            const grid = {
                id: `Part_${idx + 1}`,
                name: `Part ${idx + 1}: ${area.name} (Q${startQ}–${startQ + qCount - 1})`,
                type: 'mcq',
                page_no: targetPage,
                subjectName: area.name,
                aptitudeAreaCode: area.code,
                questionsCount: qCount,
                cols: 1,
                optionsCount: 4,
                startQ,
                customValuesStr: 'A, B, C, D',
                bubbleDiameterMm: 4.5,
                bubblePitchMm: 6.0,
                rowPitchMm: 6.8,
                labelFontSizePt: 6.5,
                labelStyle: 'column_header',
                posX: 10 + indexOnPage * 31,
                posY: 84,
                w_mm: 28,
                h_mm: 175,
                isLocked: false,
            };
            startQ += qCount;
            return grid;
        });

        const otherComps = components.filter(
            (c) =>
                c.type !== 'mcq' &&
                c.type !== 'tf' &&
                c.type !== 'likert' &&
                c.type !== 'custom',
        );
        components = normalizeLayerOrder([...otherComps, ...newGrids]);
        syncComponentsToSections('Scaffold aptitude areas');
    }

    // HTML Code Editor State (blank until components are placed)
    let htmlCode = $state('');
    // Mode Switch Confirmation Modal State
    let showModeSwitchModal = $state(false);
    let pendingTargetMode = $state(null);

    function resetCanvasForModeSwitch() {
        draft.raster_url = null;
        components = [];
        sections = [];
        selectedComponentId = null;
        selectedComponentIds = [];
        selectedCanvasIds = [];
        activeSectionIndex = 0;
        currentStage = 1;
        scale = 0.78;
        panX = 0;
        panY = 0;
        uploadRunning = false;
        conversionStatusText = '';
        conversionError = '';
        if (uploadFileInput) uploadFileInput.value = '';
        resetHistory();
    }

    function requestSwitchMode(targetMode) {
        if (targetMode === stage1Mode) return;
        const hasModified =
            draft.raster_url || components.length > 0 || uploadRunning;
        if (hasModified) {
            pendingTargetMode = targetMode;
            showModeSwitchModal = true;
            return;
        }
        resetCanvasForModeSwitch();
        stage1Mode = targetMode;
        baseType = targetMode === 'html' ? 'html' : 'image';
        triggerAutosave();
    }

    function confirmSwitchMode() {
        if (!pendingTargetMode) return;
        const nextMode = pendingTargetMode;
        resetCanvasForModeSwitch();
        stage1Mode = nextMode;
        baseType = nextMode === 'html' ? 'html' : 'image';
        showModeSwitchModal = false;
        pendingTargetMode = null;
        triggerAutosave();
    }

    // Direct Upload & Ingest State
    let uploadFileInput = $state(null);
    let replacePageImageInput = $state(null);
    let pageActionsDetails = $state(null);
    let uploadRunning = $state(false);
    let conversionStatusText = $state('');
    let conversionError = $state('');
    let imageAlreadyCropped = $state(false);
    let photoCropFile = $state(null);
    let photoCropUrl = $state(null);
    let photoCropPreviewUrl = $state(null);
    let photoCropWidth = $state(0);
    let photoCropHeight = $state(0);
    let photoCropCorners = $state([]);
    let photoCropSuggestion = $state(false);
    let photoCropDragging = $state(-1);
    let photoCropRotation = $state(0);
    let photoCropSurface = $state(null);
    let photoCropKey = null;
    let photoCropIsCorrection = $state(false);
    let photoCropIsPageReplacement = $state(false);
    let photoCropTouchedCorners = $state([]);
    let photoCropTargetPageNo = $state(1);
    let pendingCropQueue = $state([]);
    let pendingCropQueueIndex = $state(0);
    let cropRecoveryError = $state('');
    let cropReviewRequired = $state(
        draft.authoring?.invalidations?.mapping_requires_review === true,
    );
    let cropReviewAcknowledged = $state(false);

    // Stage 3 Composition Loading
    let isComposing = $state(false);
    let smartSnapRunning = $state(false);
    let stageTransitionRunning = $state(false);
    let previewSurface = $state('canonical');
    let canonicalPreview = $state(draft.persisted_preview ?? null);
    let previewRunning = $state(false);
    let previewError = $state(null);
    let mappingDirty = $state(false);
    let showResetConfirmModal = $state(false);
    let pendingTargetStage = $state(null);
    let discardDraftModalOpen = $state(false);
    let discardDraftBusy = $state(false);
    let discardDraftError = $state('');

    function confirmDiscardDraft() {
        if (discardDraftBusy) return;
        discardDraftBusy = true;
        discardDraftError = '';
        const destroyUrl = urls?.destroy || `/setup/exam-materials/answer-sheets/${draft.id}`;
        router.delete(destroyUrl, {
            onSuccess: () => {
                discardDraftModalOpen = false;
            },
            onError: (errors) => {
                discardDraftError = typeof errors === 'string' ? errors : (errors?.message || 'Failed to discard draft. The draft may have active verification history.');
            },
            onFinish: () => {
                discardDraftBusy = false;
            },
        });
    }
    // Stage 2: Overlays & Insets
    let marginMm = $state(15);
    let markerInsetsMm = $state(
        normalizeMarkerInsets(draft.authoring?.marker?.insets_mm),
    );
    let persistedMarkerInsetsMm = $state(
        normalizeMarkerInsets(draft.authoring?.marker?.insets_mm),
    );
    let markerInsetsSaving = $state(false);
    let markerPattern = $state(draft.marker_profile_id ? 'target' : 'square');
    let showHeaderOverlay = $state(false);
    let headerTitle = $state(draft.name || 'EXAMINATION ANSWER SHEET');
    let headerSubtitle = $state('ANSWER SHEET');
    let headerFontSize = $state(14.0);
    let headerFontFamily = $state('Inter, sans-serif');
    let headerColor = $state('#0f172a');
    let headerAlign = $state('left');
    let headerX = $state(25);
    let headerY = $state(20);
    let headerW = $state(160);
    let headerH = $state(15);
    let headerLocked = $state(false);
    let showQrOverlay = $state(qrEnabled);
    let qrX = $state(162);
    let qrY = $state(18);
    let qrW = $state(26);
    let qrH = $state(26);
    let qrLocked = $state(false);

    let showInstructionsOverlay = $state(false);
    let instructionsContent = $state(
        'Use 2B pencil only. Shade bubbles completely. Erase cleanly to change.',
    );
    let instructionsFontSize = $state(9.0);
    let instructionsFontFamily = $state('Inter, sans-serif');
    let instructionsColor = $state('#334155');
    let instX = $state(25);
    let instY = $state(250);
    let instW = $state(90);
    let instH = $state(28);
    let instLocked = $state(false);

    let showSignatureOverlay = $state(false);
    let signatureContent = $state('EXAMINEE SIGNATURE');
    let signatureFontSize = $state(8.5);
    let signatureFontFamily = $state('Inter, sans-serif');
    let signatureColor = $state('#475569');
    let sigX = $state(125);
    let sigY = $state(250);
    let sigW = $state(60);
    let sigH = $state(28);
    let sigLocked = $state(false);

    let plottingConfig = $state({
        active: false,
        mode: 'consecutive',
        areaCode: aptitude_areas?.[0]?.code || 'GENERAL',
        itemNumber: 1,
        optionsCount: 4,
        optionLabels: ['A', 'B', 'C', 'D'],
        bubbleDiameterMm: 4.5,
        currentOptionIndex: 0,
        pointsMm: [],
        respotSectionIndex: null,
        respotOptionIndex: 0,
        stampSourceSection: null,
        sizingPreviewDiameterMm: null,
        questionSpanMode: true,
    });
    let showMetadataOverlay = $state(false);
    let metadataContent = $state('ROOM / SEAT NO.');
    let metadataFontSize = $state(9.0);
    let metaX = $state(25);
    let metaY = $state(45);
    let metaW = $state(50);
    let metaH = $state(15);

    let showRollOverlay = $state(false);
    let rollDigits = $state(6);
    let rollX = $state(25);
    let rollY = $state(45);

    let customTextItems = $state([]);

    // Stage 3: Multi-Section Geometry & Canvas State
    let interactionMode = $state('pan');
    let sections = $state([]);
    if (
        stage1Mode !== 'html' &&
        !Array.isArray(draft.authoring?.mapping?.field_blocks)
    ) {
        sections = [];
    }
    let activeSectionIndex = $state(0);
    let selectedSectionIndices = $state([]);
    let bubbleRadiusMm = $state(4.5);
    let smartSnapSearchMm = $state(Math.max(2, Math.min(12, Number(draft?.authoring?.canonical?.settings?.smart_snap_search_mm ?? draft?.authoring?.studio?.smartSnapSearchMm ?? 5.0) || 5.0)));
    let showSmartSnapSearchZone = $state(true);
    let previewingSearchZone = $state(false);
    /** @type {'selected' | 'all_unlocked'} */
    let smartSnapSearchZoneScope = $state('selected');
    let previousSearchZoneContext = `${activePageNo}:${currentStage}`;
    $effect(() => {
        const nextContext = `${activePageNo}:${currentStage}`;
        if (nextContext !== previousSearchZoneContext) {
            previousSearchZoneContext = nextContext;
            previewingSearchZone = false;
        }
    });

    // Canvas Pan & Zoom (Optimized Scale = 0.78 fits 100% of A4 sheet inside standard viewport without overflow)
    let scale = $state(0.78);
    let panX = $state(0);
    let panY = $state(0);

    // Persistence & Locking
    let currentLockVersion = $state(draft.lock_version ?? 0);
    function syncDraftLockVersion(version) {
        const next = Number(version);
        if (!Number.isInteger(next) || next < 0 || next < currentLockVersion)
            return;
        currentLockVersion = next;
        draft.lock_version = next;
    }
    let saveStatus = $state('saved'); // 'saved' | 'saving' | 'error'
    let errorMessage = $state('');
    let studioSaveIssues = $state([]);
    let autosaveTimer = null;
    function firstErrorText(value) {
        if (typeof value === 'string' && value.trim()) return value.trim();
        if (Array.isArray(value)) {
            return value.map(firstErrorText).find(Boolean) ?? '';
        }
        if (value && typeof value === 'object') {
            for (const key of ['detail', 'message', 'error', 'reason']) {
                const text = firstErrorText(value[key]);
                if (text) return text;
            }
        }
        return '';
    }

    function errorCodeFrom(payload) {
        if (!payload || typeof payload !== 'object') return '';
        const candidates = [
            payload.code,
            payload.payload?.code,
            payload.error?.code,
            payload.payload?.error?.code,
        ];
        return (
            candidates
                .find((value) => typeof value === 'string' && value.trim())
                ?.trim() ?? ''
        );
    }

    function formatPreviewError(
        payload,
        fallback = 'The answer-sheet print preview could not be generated.',
    ) {
        const code = errorCodeFrom(payload);
        const detail =
            firstErrorText(payload?.detail) ||
            firstErrorText(payload?.payload?.detail) ||
            firstErrorText(payload?.safe_issue?.detail) ||
            firstErrorText(payload?.message) ||
            firstErrorText(payload?.payload?.message) ||
            firstErrorText(payload?.error) ||
            firstErrorText(payload?.payload?.error);
        let resolvedDetail = detail;
        const isGeneric =
            !resolvedDetail ||
            resolvedDetail ===
                'The OMR worker rejected the layout verification.' ||
            resolvedDetail === 'The request was rejected.' ||
            resolvedDetail === 'Preview failed';

        if (isGeneric && code) {
            if (code === 'marker_profile_unqualified') {
                resolvedDetail =
                    'This marker profile must be qualified before compile.';
            } else if (code.includes('marker')) {
                resolvedDetail =
                    'Fiducial markers could not be detected or do not match the selected marker profile.';
            } else if (code.includes('dimension')) {
                resolvedDetail =
                    'Sheet dimensions or margins exceed printable page boundaries.';
            } else if (
                code.includes('bound') ||
                code.includes('shift') ||
                code.includes('overflow')
            ) {
                resolvedDetail =
                    'Mapped bubble fields extend beyond safe printable margins.';
            } else if (
                code.includes('unavailable') ||
                code.includes('timeout')
            ) {
                resolvedDetail =
                    'The OMR recognition engine is currently unavailable. Retry in a moment.';
            } else if (code.includes('invalid')) {
                resolvedDetail =
                    'The OMR recognition engine returned an unreadable response.';
            }
        }

        return { code, detail: resolvedDetail || fallback };
    }

    function typedRequestError(payload, fallback) {
        const parsed = formatPreviewError(payload, fallback);
        const error = new Error(parsed.detail);
        if (parsed.code) error.code = parsed.code;
        error.payload = payload;
        return error;
    }
    let studioSaveQueue = Promise.resolve(true);

    let totalQuestionsCount = $derived(
        sections.reduce((sum, s) => sum + (Number(s.questionsCount) || 0), 0),
    );

    function normalizeStudioIssues(
        payload,
        fallback = 'Studio save was rejected.',
    ) {
        if (Array.isArray(payload?.errors)) {
            const structured = payload.errors
                .map((error) => {
                    const message = firstErrorText(error);
                    return message
                        ? {
                              ...(error && typeof error === 'object'
                                  ? error
                                  : {}),
                              message,
                          }
                        : null;
                })
                .filter(Boolean);
            if (structured.length > 0) return structured;
        }
        if (payload?.errors && typeof payload.errors === 'object') {
            const messages = Object.values(payload.errors)
                .map(firstErrorText)
                .filter(Boolean);
            if (messages.length > 0)
                return messages.map((message) => ({ message }));
        }
        const detail =
            firstErrorText(payload?.detail) ||
            firstErrorText(payload?.message) ||
            fallback;
        if (detail.trim().startsWith('[')) {
            try {
                const decoded = JSON.parse(detail);
                if (Array.isArray(decoded))
                    return normalizeStudioIssues({ errors: decoded }, fallback);
            } catch {
                // Keep the safe server detail below.
            }
        }
        return [{ message: detail }];
    }

    function readEditorState() {
        return {
            components: $state.snapshot(components),
            sections: $state.snapshot(sections),
            physicalInventory: $state.snapshot(physicalInventory),
            documentFields: $state.snapshot(documentFields),
            smartSnapSearchMm,
            layout: {
                marginMm,
                markerPattern,
                showHeaderOverlay,
                headerTitle,
                headerSubtitle,
                headerFontSize,
                headerFontFamily,
                headerColor,
                headerAlign,
                headerX,
                headerY,
                headerW,
                headerH,
                headerLocked,
                showQrOverlay,
                qrX,
                qrY,
                qrW,
                qrH,
                qrLocked,
                showInstructionsOverlay,
                instructionsContent,
                instructionsFontSize,
                instructionsFontFamily,
                instructionsColor,
                instX,
                instY,
                instW,
                instH,
                instLocked,
                showSignatureOverlay,
                signatureContent,
                signatureFontSize,
                signatureFontFamily,
                signatureColor,
                sigX,
                sigY,
                sigW,
                sigH,
                sigLocked,
                metadataContent,
                metadataFontSize,
                showMetadataOverlay,
                showRollOverlay,
                rollDigits,
                customTextItems: $state.snapshot(customTextItems),
            },
        };
    }

    let historyRevision = $state(0);
    let historyNotice = $state('');
    const studioHistory = createStudioHistory(readEditorState(), 100);
    let historyStatus = $derived.by(() => {
        historyRevision;
        return studioHistory.status();
    });

    function recordHistory(label, options = {}) {
        if (studioHistory.record(readEditorState(), label, options)) {
            historyRevision += 1;
        }
    }

    function applyEditorState(nextState, notice = '') {
        components = nextState.components;
        sections = nextState.sections;
        physicalInventory = nextState.physicalInventory ?? physicalInventory;
        physicalInventoryConfirmation = null;
        documentFields = nextState.documentFields ?? [];
        smartSnapSearchMm = nextState.smartSnapSearchMm ?? 5.0;
        const layout = nextState.layout;
        marginMm = layout.marginMm;
        markerPattern = layout.markerPattern;
        showHeaderOverlay = layout.showHeaderOverlay;
        headerTitle = layout.headerTitle;
        headerSubtitle = layout.headerSubtitle;
        headerFontSize = layout.headerFontSize;
        headerFontFamily = layout.headerFontFamily;
        headerColor = layout.headerColor;
        headerAlign = layout.headerAlign;
        headerX = layout.headerX;
        headerY = layout.headerY;
        headerW = layout.headerW;
        headerH = layout.headerH;
        headerLocked = layout.headerLocked;
        showQrOverlay = layout.showQrOverlay;
        qrX = layout.qrX;
        qrY = layout.qrY;
        qrW = layout.qrW;
        qrH = layout.qrH;
        qrLocked = layout.qrLocked;
        showInstructionsOverlay = layout.showInstructionsOverlay;
        instructionsContent = layout.instructionsContent;
        instructionsFontSize = layout.instructionsFontSize;
        instructionsFontFamily = layout.instructionsFontFamily;
        instructionsColor = layout.instructionsColor;
        instX = layout.instX;
        instY = layout.instY;
        instW = layout.instW;
        instH = layout.instH;
        instLocked = layout.instLocked;
        showSignatureOverlay = layout.showSignatureOverlay;
        signatureContent = layout.signatureContent;
        signatureFontSize = layout.signatureFontSize;
        signatureFontFamily = layout.signatureFontFamily;
        signatureColor = layout.signatureColor;
        sigX = layout.sigX;
        sigY = layout.sigY;
        sigW = layout.sigW;
        sigH = layout.sigH;
        sigLocked = layout.sigLocked;
        metadataContent = layout.metadataContent;
        metadataFontSize = layout.metadataFontSize;
        showMetadataOverlay = layout.showMetadataOverlay;
        showRollOverlay = layout.showRollOverlay;
        rollDigits = layout.rollDigits;
        customTextItems = layout.customTextItems;
        htmlCode = components.length > 0 ? renderCurrentHtml() : '';
        selectedCanvasIds = selectedCanvasIds.filter(
            (id) =>
                (id === QR_CANVAS_ITEM_ID && showQrOverlay) ||
                components.some((component) => component.id === id),
        );
        selectedComponentIds = selectedCanvasIds.filter(
            (id) => id !== QR_CANVAS_ITEM_ID,
        );
        if (
            !components.some(
                (component) => component.id === selectedComponentId,
            )
        )
            selectedComponentId = selectedComponentIds.at(-1) ?? null;
        activeSectionIndex = Math.min(
            activeSectionIndex,
            Math.max(0, sections.length - 1),
        );
        if (stage1Mode !== 'html') {
            declaredInventory = syncDeclaredInventoryFromSections(
                sections,
                declaredInventory,
            );
        }
        plottingConfig.itemNumber = nextItemNumberForArea(
            sections,
            plottingConfig.areaCode,
        );
        invalidateCanonicalPreview();
        historyRevision += 1;
        historyNotice = notice;
        triggerAutosave();
    }

    function undoEditor() {
        if (isReadOnly) return;
        const state = studioHistory.undo();
        if (state) {
            applyEditorState(
                state,
                `Undid ${studioHistory.status().redoLabel ?? 'edit'}`,
            );
        }
    }

    function redoEditor() {
        if (isReadOnly) return;
        const state = studioHistory.redo();
        if (state) {
            applyEditorState(
                state,
                `Redid ${studioHistory.status().undoLabel ?? 'edit'}`,
            );
        }
    }

    function resetHistory() {
        studioHistory.reset(readEditorState());
        historyRevision += 1;
        historyNotice = '';
    }

    function invalidateCanonicalPreview() {
        if (currentStage >= 3) {
            mappingDirty = true;
            previewError = null;
        }
    }
    function syncUploadedDeclarationsFromSections() {
        if (stage1Mode === 'html') return;
        if (
            sections.some(
                (s) => s && s.type !== 'roll' && !s.identity_grid && s.area_code,
            )
        ) {
            declaredInventory = syncDeclaredInventoryFromSections(
                sections,
                declaredInventory,
            );
        }
        const identityGrid = sections.find(
            (s) => s && s.identity_grid && s.type === 'roll',
        );
        if (identityGrid) {
            const digitCount = Number(identityGrid.questionsCount);
            if (
                !identityDeclaration?.method ||
                (identityDeclaration.method === 'shaded_grid' &&
                    (identityDeclaration.digit_count == null ||
                        identityDeclaration.digit_count === ''))
            ) {
                identityDeclaration = {
                    ...identityDeclaration,
                    method: 'shaded_grid',
                    digit_count:
                        Number.isInteger(digitCount) && digitCount >= 1
                            ? digitCount
                            : 6,
                };
            }
        }
    }

    function handleGeometryChanged() {
        syncUploadedDeclarationsFromSections();
        invalidateCanonicalPreview();
        recordHistory('Move or resize overlay', { coalesceKey: 'geometry' });
        triggerAutosave();
    }

    function recordBubbleEdit(changeKind = 'mapping') {
        normalizeSectionsForRecognition();
        syncUploadedDeclarationsFromSections();
        if (changeKind !== 'lock') invalidateCanonicalPreview();
        recordHistory(
            changeKind === 'lock'
                ? 'Lock or unlock recognition guide'
                : changeKind === 'mapping'
                  ? 'Edit recognition mapping'
                  : changeKind,
            { coalesceKey: `section:${activeSectionIndex}:${changeKind}` },
        );
        triggerAutosave();
    }
    function updatePhysicalInventory(nextInventory, confirmation = null) {
        physicalInventory = nextInventory;
        physicalInventoryConfirmation = confirmation;
        recordHistory('Manage Printed sections');
        triggerAutosave();
    }
    async function handleUnlockDraft() {
        if (unlockingDraft) return;
        unlockingDraft = true;
        try {
            const response = await fetch(
                urls?.unlock_draft ||
                    `/setup/exam-materials/answer-sheets/${draft.id}/unlock`,
                {
                    method: 'POST',
                    headers: {
                        Accept: 'application/json',
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': csrfToken(),
                    },
                },
            );
            const payload = await response.json();
            if (!response.ok) {
                toastError(payload?.message || 'Could not unlock this draft.');
                return;
            }
            window.location.href =
                payload?.redirect_url ||
                `/setup/exam-materials/answer-sheets/${draft.id}/edit`;
        } catch {
            toastError('Could not unlock this draft.');
        } finally {
            unlockingDraft = false;
        }
    }
    async function handleCreateEditableCopy() {
        if (creatingEditableCopy) return;
        creatingEditableCopy = true;
        try {
            const response = await fetch(
                urls?.editable_copy ||
                    `/setup/exam-materials/answer-sheets/${draft.id}/editable-copy`,
                {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        Accept: 'application/json',
                        'X-CSRF-TOKEN': csrfToken(),
                    },
                },
            );
            const data = await response.json();
            if (!response.ok) {
                throw new Error(
                    data?.message || 'Could not create an editable copy.',
                );
            }
            if (data?.redirect_url) {
                window.location.href = data.redirect_url;
                return;
            }
            throw new Error('The editable copy was created without a redirect URL.');
        } catch (error) {
            toastError(error?.message || 'Could not create an editable copy.');
        } finally {
            creatingEditableCopy = false;
        }
    }

    async function handleSmartSnapBubbles() {
        if (smartSnapRunning || isReadOnly) return;
        smartSnapRunning = true;
        try {
            const targetPageNo = Number(activePageNo) || 1;
            const targetPaperW = Number(canonicalPreview?.artifact?.paper?.width_mm) || PAGE_W || 210;
            const targetPaperH = Number(canonicalPreview?.artifact?.paper?.height_mm) || PAGE_H || 297;
            const rasterUrl =
                canonicalPreview?.artifact?.pages?.find(
                    (candidate) => Number(candidate.page_no) === targetPageNo,
                )?.canonical_url ??
                orderedPages.find(
                    (candidate) => Number(candidate.page_no) === targetPageNo,
                )?.raster_url ??
                (targetPageNo === 1 ? urls?.raster_preview : null);
            if (!rasterUrl) {
                throw new Error('No raster image is available for the active page.');
            }
            const image = new Image();
            image.crossOrigin = 'anonymous';
            image.src = rasterUrl;
            await new Promise((resolve, reject) => {
                image.onload = resolve;
                image.onerror = () => reject(new Error('The active page image could not be loaded.'));
            });
            if ((Number(activePageNo) || 1) !== targetPageNo || isReadOnly) {
                return;
            }
            const canvas = document.createElement('canvas');
            canvas.width = image.naturalWidth;
            canvas.height = image.naturalHeight;
            const context = canvas.getContext('2d');
            if (!context) {
                throw new Error('The active page image could not be read.');
            }
            context.drawImage(image, 0, 0, image.naturalWidth, image.naturalHeight);
            const result = snapSectionsToPrintedCircles(
                context.getImageData(0, 0, image.naturalWidth, image.naturalHeight),
                sections,
                {
                    pageNo: targetPageNo,
                    paperWidthMm: targetPaperW,
                    paperHeightMm: targetPaperH,
                    minBubbleMm:
                        stage1Mode !== 'html'
                            ? MIN_UPLOADED_BUBBLE_MM
                            : MIN_BUBBLE_MM,
                    searchRadiusMm: Math.max(2, Math.min(12, Number(smartSnapSearchMm) || 5.0)),
                },
            );
            if (result.snappedBubblesCount > 0) {
                toastSuccess(
                    `Snapped ${result.snappedBubblesCount} bubbles across ${result.snappedQuestionsCount} questions on Page ${targetPageNo}.`,
                );
            } else {
                toastError(
                    'No distinct printed circles were found near the current guides on this page.',
                );
            }
            sections = result.sections;
            recordBubbleEdit('Smart snap bubbles');
        } catch (error) {
            toastError(error?.message || 'Smart snap could not read this page.');
        } finally {
            smartSnapRunning = false;
        }
    }
    function handleStudioKeydown(event) {
        if (event.repeat || event.isComposing) return;
        const tagName = event.target?.tagName?.toLowerCase?.() ?? '';
        if (
            ['input', 'textarea', 'select'].includes(tagName) ||
            event.target?.isContentEditable
        )
            return;
        if (isReadOnly) return;
        if (event.key === 'Escape' && currentStage === 3) {
            activeSectionIndex = -1;
            selectedSectionIndices = [];
            return;
        }
        const key = event.key.toLowerCase();
        const modifier = event.ctrlKey || event.metaKey;
        const isSheetDesigner =
            currentStage === 1 &&
            stage1Mode === 'html' &&
            designerSubTab === 'designer';
        if ((key === 'delete' || key === 'backspace') && isSheetDesigner) {
            event.preventDefault();
            removeSelectedComponents();
            return;
        }
        if (!modifier || event.altKey) return;
        if (key === 'd' && !event.shiftKey && isSheetDesigner) {
            event.preventDefault();
            duplicateSelectedComponents();
        } else if (key === 'z' && !event.shiftKey) {
            event.preventDefault();
            undoEditor();
        } else if (
            (key === 'z' && event.shiftKey) ||
            (key === 'y' && !event.shiftKey)
        ) {
            event.preventDefault();
            redoEditor();
        } else if (key === ']' && event.shiftKey && isSheetDesigner) {
            event.preventDefault();
            moveSelectedLayerToEdge(true);
        } else if (key === '[' && event.shiftKey && isSheetDesigner) {
            event.preventDefault();
            moveSelectedLayerToEdge(false);
        } else if (key === ']' && isSheetDesigner) {
            event.preventDefault();
            moveComponentLayer(selectedComponentId, 1);
        } else if (key === '[' && isSheetDesigner) {
            event.preventDefault();
            moveComponentLayer(selectedComponentId, -1);
        }
    }

    onMount(() => {
        const studio = draft.authoring?.studio;
        let persistedStage = 1;
        if (studio) {
            if (studio.stage1Mode) stage1Mode = studio.stage1Mode;
            if (studio.baseType) baseType = studio.baseType;
            if (typeof studio.current_stage === 'number') {
                persistedStage = normalizeStudioStage(
                    studio.current_stage,
                    stage1Mode,
                );
                currentStage = persistedStage;
            }

            if (
                Array.isArray(studio.components) &&
                studio.components.length > 0
            ) {
                components = normalizeLayerOrder(
                    JSON.parse(JSON.stringify(studio.components)).map(
                        normalizeLoadedComponent,
                    ),
                );
                htmlCode =
                    components.length > 0
                        ? renderFullSheetHtml(components)
                        : '';
            }
            if (Array.isArray(studio.sections) && studio.sections.length > 0) {
                sections = JSON.parse(JSON.stringify(studio.sections));
            }
            if (studio.physicalInventory && typeof studio.physicalInventory === 'object') {
                physicalInventory = JSON.parse(JSON.stringify(studio.physicalInventory));
            }
            if (Array.isArray(studio.declaredInventory)) {
                declaredInventory = JSON.parse(
                    JSON.stringify(studio.declaredInventory),
                );
            }
            if (stage1Mode !== 'html' && sections.length > 0) {
                declaredInventory = syncDeclaredInventoryFromSections(
                    sections,
                    declaredInventory,
                );
            }
            if (studio.identity && typeof studio.identity === 'object') {
                identityDeclaration = JSON.parse(
                    JSON.stringify(studio.identity),
                );
            }
            if (
                typeof studio.marginMm === 'number' &&
                Number.isFinite(studio.marginMm)
            ) {
                marginMm = Math.min(35, Math.max(6, studio.marginMm));
            }
            if (studio.markerPattern) markerPattern = studio.markerPattern;
            if (typeof studio.bubbleRadiusMm === 'number')
                bubbleRadiusMm = studio.bubbleRadiusMm;

            if (typeof studio.showHeaderOverlay === 'boolean')
                showHeaderOverlay = studio.showHeaderOverlay;
            if (studio.headerTitle) headerTitle = studio.headerTitle;
            if (studio.headerSubtitle) headerSubtitle = studio.headerSubtitle;
            if (typeof studio.headerFontSize === 'number')
                headerFontSize = studio.headerFontSize;
            if (studio.headerFontFamily)
                headerFontFamily = studio.headerFontFamily;
            if (studio.headerColor) headerColor = studio.headerColor;
            if (studio.headerAlign) headerAlign = studio.headerAlign;
            if (typeof studio.headerW === 'number') headerW = studio.headerW;
            if (typeof studio.headerH === 'number') headerH = studio.headerH;
            if (typeof studio.headerLocked === 'boolean')
                headerLocked = studio.headerLocked;

            if (typeof studio.showQrOverlay === 'boolean')
                showQrOverlay = studio.showQrOverlay;
            if (typeof studio.qrX === 'number') qrX = studio.qrX;
            if (typeof studio.qrY === 'number') qrY = studio.qrY;
            if (typeof studio.qrW === 'number') qrW = studio.qrW;
            if (typeof studio.qrH === 'number') qrH = studio.qrH;
            if (typeof studio.qrLocked === 'boolean')
                qrLocked = studio.qrLocked;

            if (typeof studio.showInstructionsOverlay === 'boolean')
                showInstructionsOverlay = studio.showInstructionsOverlay;
            if (studio.instructionsContent)
                instructionsContent = studio.instructionsContent;
            if (typeof studio.instructionsFontSize === 'number')
                instructionsFontSize = studio.instructionsFontSize;
            if (studio.instructionsFontFamily)
                instructionsFontFamily = studio.instructionsFontFamily;
            if (studio.instructionsColor)
                instructionsColor = studio.instructionsColor;
            if (typeof studio.instW === 'number') instW = studio.instW;
            if (typeof studio.instH === 'number') instH = studio.instH;
            if (typeof studio.instLocked === 'boolean')
                instLocked = studio.instLocked;

            if (typeof studio.showSignatureOverlay === 'boolean')
                showSignatureOverlay = studio.showSignatureOverlay;
            if (studio.signatureContent)
                signatureContent = studio.signatureContent;
            if (typeof studio.signatureFontSize === 'number')
                signatureFontSize = studio.signatureFontSize;
            if (studio.signatureFontFamily)
                signatureFontFamily = studio.signatureFontFamily;
            if (studio.signatureColor) signatureColor = studio.signatureColor;
            if (typeof studio.sigW === 'number') sigW = studio.sigW;
            if (typeof studio.sigH === 'number') sigH = studio.sigH;
            if (typeof studio.sigLocked === 'boolean')
                sigLocked = studio.sigLocked;

            if (typeof studio.showMetadataOverlay === 'boolean')
                showMetadataOverlay = studio.showMetadataOverlay;
            if (studio.metadataContent)
                metadataContent = studio.metadataContent;

            if (studio.htmlCode && components.length === 0)
                htmlCode = studio.htmlCode;
        }

        if (components.length > 0) {
            normalizeComponentsForCanvas();
            htmlCode = renderCurrentHtml();
        }
        const sectionsNormalized = normalizeSectionsForRecognition();
        if (persistedStage >= 3 && sectionsNormalized) {
            mappingDirty = true;
        }
        syncUploadedDeclarationsFromSections();

        if (draft.lock_version !== undefined) {
            syncDraftLockVersion(draft.lock_version);
        }
        if (usesFiducials && draft.marker_profile_id) {
            markerPattern = 'target';
        }
        resetHistory();
        if (usesFiducials && persistedStage >= 3 && !draft.marker_profile_id) {
            previewError = {
                code: 'marker_profile_required',
                detail: 'Select an OMRChecker fiducial profile before restoring Recognition Mapping.',
            };
        }
        window.addEventListener('keydown', handleStudioKeydown);
    });

    onDestroy(() => {
        if (autosaveTimer) clearTimeout(autosaveTimer);
        window.removeEventListener('keydown', handleStudioKeydown);
        clearPhotoCrop();
    });

    async function requestGoToStage(step) {
        const targetStage = normalizeStudioStage(step, stage1Mode);
        if (isReadOnly) {
            executeGoToStage(targetStage);
            return;
        }
        if (targetStage === 4 && cropReviewRequired) {
            historyNotice =
                'Review and realign every guide against the corrected crop before verification.';
            return;
        }
        if (stageTransitionRunning || previewRunning || isComposing) return;
        if (
            (targetStage === 1 || targetStage === 2) &&
            currentStage >= 3 &&
            draft.raster_url
        ) {
            pendingTargetStage = targetStage;
            showResetConfirmModal = true;
            return;
        }
        if (
            targetStage === 3 &&
            stage1Mode !== 'html' &&
            currentStage === 2 &&
            !canonicalPreview
        ) {
            const composed = await composeUploadedSource();
            if (!composed) return;
        }
        executeGoToStage(targetStage);
    }

    async function returnToKit() {
        if (!kit_context?.back_url) return;
        if (isReadOnly) {
            router.visit(kit_context.back_url);
            return;
        }
        if (stageTransitionRunning || previewRunning || isComposing) return;

        if (autosaveTimer) {
            clearTimeout(autosaveTimer);
            autosaveTimer = null;
        }

        if (await performSave()) {
            router.visit(kit_context.back_url);
        } else {
            historyNotice =
                errorMessage || 'Could not save before leaving the Answer Sheet Studio.';
        }
    }

    async function loadSampleUploadedDocument() {
        if (!urls.source_ingest) return;
        if (Number(draft.requested_page_count) > 1) {
            conversionError = `The standard sample sheet is a single-page document. This draft is configured for ${draft.requested_page_count} pages. Upload a ${draft.requested_page_count}-page PDF or Word document, or create a 1-page draft to use this sample.`;
            return;
        }
        uploadRunning = true;
        conversionStatusText = 'Importing standard 150-question test sheet…';
        conversionError = '';

        clearPhotoCrop();
        canonicalPreview = null;
        draft.raster_url = null;
        currentRasterUrl = null;
        if (draft.authoring?.canonical) {
            draft.authoring.canonical.raster_assets = [];
            draft.authoring.canonical.artifact_set = null;
        }
        try {
            const res = await fetch(
                '/samples/omr/standard-150q-answer-sheet.png',
            );
            const blob = await res.blob();
            const file = new File([blob], 'standard-150q-answer-sheet.png', {
                type: 'image/png',
            });

            baseType = 'image';
            if (autosaveTimer) {
                clearTimeout(autosaveTimer);
                autosaveTimer = null;
            }
            if (!(await performSave('image'))) {
                throw new Error(
                    errorMessage || 'Could not save the image source type.',
                );
            }

            const form = new FormData();
            form.append('source_kind', 'image');
            form.append('source', file);
            form.append('width_mm', '210');
            form.append('height_mm', '297');
            form.append('idempotency_key', newIdempotencyKey());
            const uploadRes = await fetch(urls.source_ingest, {
                method: 'POST',
                headers: { ...csrfHeaders(), Accept: 'application/json' },
                body: form,
            });
            if (!uploadRes.ok) {
                const data = await uploadRes.json().catch(() => ({}));
                throw typedRequestError(data, 'File upload rejected.');
            }

            conversionStatusText = 'Preparing print sheet…';
            await pollConversionRun();
        } catch (err) {
            uploadRunning = false;
            conversionError = err.message || 'Sample document import failed.';
        }
    }

    function confirmResetAndNavigate() {
        draft.raster_url = null;
        currentRasterUrl = null;
        canonicalPreview = null;
        previewError = null;
        if (draft.authoring?.canonical) {
            draft.authoring.canonical.raster_assets = [];
            draft.authoring.canonical.artifact_set = null;
        }
        showResetConfirmModal = false;
        if (pendingTargetStage) {
            executeGoToStage(pendingTargetStage);
            pendingTargetStage = null;
        }
    }

    function syncSectionsFromComponentsForBubbleStage() {
        normalizeComponentsForCanvas();
        const existingSections = new Map(
            sections.map((section) => [section.id, section]),
        );
        const nextSections = components
            .map((component) => componentToSectionData(component))
            .filter((section) => section !== null)
            .map((section) =>
                normalizeRecognitionSection(
                    existingSections.has(section.id)
                        ? {
                              ...section,
                              ...existingSections.get(section.id),
                              id: section.id,
                          }
                        : section,
                ),
            );

        sections = nextSections;
        activeSectionIndex = Math.min(
            activeSectionIndex,
            Math.max(0, nextSections.length - 1),
        );
    }

    function previewMarkerInsets(nextInsets) {
        markerInsetsMm = normalizeMarkerInsets(nextInsets);
        if (components.length > 0) htmlCode = renderCurrentHtml();
    }

    async function selectMarkerProfile(profileId, requestedInsets = null) {
        if (!profileId || !urls.mapping || markerInsetsSaving) return;
        const markerUrl = `${urls.mapping.replace(/\/mapping$/, '')}/marker-profile/${profileId}`;
        if (!usesFiducials) return;
        const nextInsets = normalizeMarkerInsets(
            requestedInsets ?? DEFAULT_MARKER_INSETS_MM,
        );
        const rollbackInsets = { ...persistedMarkerInsetsMm };
        markerInsetsSaving = true;
        try {
            const response = await fetch(markerUrl, {
                method: 'PUT',
                headers: {
                    'Content-Type': 'application/json',
                    Accept: 'application/json',
                    ...csrfHeaders(),
                    'X-Requested-With': 'XMLHttpRequest',
                },
                body: JSON.stringify({
                    marker_profile_id: profileId,
                    insets: nextInsets,
                    lock_version: currentLockVersion,
                    idempotency_key: newIdempotencyKey(),
                }),
            });
            const body = await response.json().catch(() => ({}));
            if (body?.lock_version != null)
                syncDraftLockVersion(body.lock_version);
            if (!response.ok)
                throw typedRequestError(
                    body,
                    'Fiducial settings could not be saved.',
                );
            draft.marker_profile_id = profileId;
            markerPattern = 'target';
            markerInsetsMm = { ...nextInsets };
            persistedMarkerInsetsMm = { ...nextInsets };
            draft.authoring ??= {};
            draft.authoring.marker = {
                ...(draft.authoring.marker ?? {}),
                profile_id: profileId,
                insets_mm: { ...nextInsets },
            };
            if (components.length > 0) htmlCode = renderCurrentHtml();
            canonicalPreview = null;
            previewError = null;
            mappingDirty = false;
        } catch (error) {
            markerInsetsMm = rollbackInsets;
            if (components.length > 0) htmlCode = renderCurrentHtml();
            previewError = formatPreviewError(
                error,
                error?.message || 'Fiducial settings could not be saved.',
            );
        } finally {
            markerInsetsSaving = false;
        }
    }

    function persistMarkerInsets(nextInsets) {
        return selectMarkerProfile(Number(draft.marker_profile_id), nextInsets);
    }

    async function runCanonicalPreview() {
        previewRunning = true;
        previewError = null;
        try {
            if (!urls.preview)
                throw new Error('The print preview route is unavailable.');
            if (usesFiducials && !draft.marker_profile_id)
                throw new Error(
                    'Select a marker profile before entering Recognition Mapping.',
                );
            const response = await fetch(urls.preview, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    Accept: 'application/json',
                    ...csrfHeaders(),
                    'X-Requested-With': 'XMLHttpRequest',
                },
                body: JSON.stringify({ lock_version: currentLockVersion }),
            });
            const body = await response.json().catch(() => ({}));
            if (body?.lock_version != null) {
                syncDraftLockVersion(body.lock_version);
            }
            if (!response.ok) {
                const parsed = formatPreviewError(
                    body,
                    'The answer-sheet print preview was rejected.',
                );
                canonicalPreview = null;
                previewError = parsed;
                historyNotice = parsed.detail;
                errorMessage = parsed.detail;
                saveStatus = 'error';
                return false;
            }
            canonicalPreview = body;
            mappingDirty = false;
            draft.target_current = true;
            return true;
        } catch (error) {
            canonicalPreview = null;
            const parsed = formatPreviewError(
                error,
                error?.message ||
                    'The answer-sheet print preview could not be generated.',
            );
            previewError = parsed;
            historyNotice = parsed.detail;
            errorMessage = parsed.detail;
            saveStatus = 'error';
            return false;
        } finally {
            previewRunning = false;
        }
    }

    async function openPrintPreview() {
        designerSubTab = 'print_preview';
        previewSurface = 'canonical';
        if (canonicalPreview) return;
        const saved = await performSave();
        if (saved) await runCanonicalPreview();
    }


    async function composeUploadedSource(options = {}) {
        if (stage1Mode === 'html') return true;
        if (!urls.compose) {
            previewError = 'The source composition route is unavailable.';
            return false;
        }
        if (!(await performSave())) return false;
        isComposing = true;
        try {
            const isFullBleed =
                !modifyUploadedDocument || Boolean(options?.fullBleed);
            const margins = isFullBleed
                ? { top: 0, right: 0, bottom: 0, left: 0 }
                : {
                      top: Number(marginMm) || 0,
                      right: Number(marginMm) || 0,
                      bottom: Number(marginMm) || 0,
                      left: Number(marginMm) || 0,
                  };
            const response = await fetch(urls.compose, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    Accept: 'application/json',
                    ...csrfHeaders(),
                    'X-Requested-With': 'XMLHttpRequest',
                },
                body: JSON.stringify({
                    paper_width_mm: 210,
                    paper_height_mm: 297,
                    content_margin_mm: margins,
                    printer_safe_margin_mm: {
                        top: 0,
                        right: 0,
                        bottom: 0,
                        left: 0,
                    },
                    ...(usesFiducials
                        ? {
                              marker_inset_mm: {
                                  top: 12,
                                  right: 12,
                                  bottom: 14,
                                  left: 12,
                              },
                          }
                        : {}),
                    alignment: 'center',
                    source_dpi: 300,
                    idempotency_key: newIdempotencyKey(),
                }),
            });
            const body = await response.json().catch(() => ({}));
            if (body?.lock_version != null)
                syncDraftLockVersion(body.lock_version);
            if (!response.ok) {
                throw typedRequestError(body, 'Source composition failed.');
            }
            if (!draft.authoring) draft.authoring = {};
            if (body.composition) {
                draft.authoring.canonical = body.composition;
            }
            if (body.raster_url) {
                draft.raster_url = body.raster_url;
                currentRasterUrl = body.raster_url;
            }
            canonicalPreview = null;
            previewError = null;
            if (body?.lock_version != null) {
                syncDraftLockVersion(body.lock_version);
            }
            draft.stage = body.stage ?? draft.stage;
            return true;
        } catch (error) {
            previewError = formatPreviewError(
                error,
                error?.message || 'Source composition failed.',
            );
            errorMessage = previewError.detail;
            saveStatus = 'error';
            return false;
        } finally {
            isComposing = false;
        }
    }

    async function executeGoToStage(step) {
        const targetStage = normalizeStudioStage(step, stage1Mode);
        if (isReadOnly) {
            currentStage = targetStage;
            return;
        }
        const previousStage = normalizeStudioStage(currentStage, stage1Mode);
        stageTransitionRunning = true;
        try {
            if (targetStage === 3 && stage1Mode === 'html') {
                syncSectionsFromComponentsForBubbleStage();
            }
            if (targetStage < 3) {
                canonicalPreview = null;
                previewError = null;
                mappingDirty = false;
            }
            if (autosaveTimer) {
                clearTimeout(autosaveTimer);
                autosaveTimer = null;
            }
            currentStage = targetStage;
            const saved = await performSave();
            const hasMappingIntent =
                stage1Mode === 'html' || sections.length > 0;
            const previewNeedsVerification =
                !canonicalPreview ||
                mappingDirty ||
                Boolean(
                    previewError &&
                    (typeof previewError === 'string'
                        ? previewError
                        : previewError.detail),
                );
            if (
                saved &&
                hasMappingIntent &&
                !cropReviewRequired &&
                ((targetStage === 3 && previousStage < 3 && previewNeedsVerification) ||
                    (targetStage === 4 &&
                        previousStage === 3 &&
                        previewNeedsVerification))
            ) {
                const previewed = await runCanonicalPreview();
                if (!previewed) {
                    currentStage = previousStage;
                    historyNotice =
                        typeof previewError === 'object' && previewError?.detail
                            ? previewError.detail
                            : previewError || '';
                    return;
                }
            }
            if (!saved) {
                currentStage = previousStage;
                historyNotice =
                    errorMessage ||
                    'Could not save this stage transition. Your layout remains open.';
            }
        } finally {
            stageTransitionRunning = false;
        }
    }
    function triggerAutosave() {
        if (isReadOnly || !draft.can_edit) return;
        saveStatus = 'saving';

        if (autosaveTimer) clearTimeout(autosaveTimer);
        autosaveTimer = setTimeout(async () => {
            autosaveTimer = null;
            await performSave();
        }, 600);
    }

    function buildBlockList() {
        normalizeComponentsForCanvas();
        normalizeSectionsForRecognition();
        const blocks = sections.map((s) => {
            if (s.type === 'roll') {
                const digits = Number(s.questionsCount) || 7;
                const geometry = effectiveRecognitionGeometryMm({
                    kind: 'roll',
                    bubbleDiameterMm: s.bubbleDiameterMm,
                    bubblePitchMm: s.bubblePitchMm,
                    rowPitchMm: s.rowPitchMm,
                    minBubbleMm: stage1Mode !== 'html' ? 2.5 : undefined,
                });
                return {
                    id: s.id,
                    type: 'roll',
                    page_no: Number(s.page_no) || 1,
                    x_mm: Number(s.posX) || 110,
                    y_mm: Number(s.posY) || 28,
                    w_mm: Number(
                        (
                            geometry.bubbleDiameterMm +
                            (digits - 1) * geometry.bubblePitchMm +
                            1.5
                        ).toFixed(1),
                    ),
                    h_mm: Number(
                        (
                            geometry.bubbleDiameterMm +
                            (10 - 1) * geometry.rowPitchMm +
                            1.5
                        ).toFixed(1),
                    ),
                    digits,
                    identity_grid: s.identity_grid === true,
                    rotation_deg: Number(s.rotationDeg) || 0,
                    roll_direction:
                        s.rollDirection === 'horizontal'
                            ? 'horizontal'
                            : 'vertical',
                    bubble_diameter_mm: geometry.bubbleDiameterMm,
                    bubble_pitch_mm: geometry.bubblePitchMm,
                    row_pitch_mm: geometry.rowPitchMm,
                };
            }

            const resolvedOptionLabels =
                typeof s.customValuesStr === 'string' &&
                s.customValuesStr.trim() !== ''
                    ? s.customValuesStr
                          .split(',')
                          .map((v) => v.trim())
                          .filter(Boolean)
                    : Array.isArray(s.optionPointsMm) &&
                        s.optionPointsMm.length >= 2
                      ? [...new Set(s.optionPointsMm
                            .map((pt) => String(pt?.label ?? '').trim())
                            .filter(Boolean))]
                      : Array.isArray(s.optionLabels) &&
                          s.optionLabels.length >= 2
                        ? s.optionLabels
                              .map((v) => String(v).trim())
                              .filter(Boolean)
                        : undefined;
            let opts =
                resolvedOptionLabels && resolvedOptionLabels.length >= 2
                    ? resolvedOptionLabels.length
                    : Number(s.optionsCount) || 4;
            if (!(resolvedOptionLabels && resolvedOptionLabels.length >= 2)) {
                if (s.type === 'tf') opts = 2;
                if (s.type === 'likert') opts = 5;
            }
            const geometry = effectiveRecognitionGeometryMm({
                kind: 'grid',
                bubbleDiameterMm: s.bubbleDiameterMm,
                bubblePitchMm: s.bubblePitchMm,
                rowPitchMm: s.rowPitchMm,
                minBubbleMm: stage1Mode !== 'html' ? 2.5 : undefined,
            });

            const qCount = Number(s.questionsCount) || 25;
            const hasExactOptionPoints =
                Array.isArray(s.optionPointsMm) &&
                s.optionPointsMm.length === qCount * opts;
            const pointBounds = hasExactOptionPoints
                ? {
                      minX: Math.min(...s.optionPointsMm.map((point) => Number(point.x_mm))),
                      maxX: Math.max(...s.optionPointsMm.map((point) => Number(point.x_mm))),
                      minY: Math.min(...s.optionPointsMm.map((point) => Number(point.y_mm))),
                      maxY: Math.max(...s.optionPointsMm.map((point) => Number(point.y_mm))),
                  }
                : null;
            const { w_mm, h_mm } = pointBounds
                ? {
                      w_mm: Number(
                          (
                              pointBounds.maxX -
                              pointBounds.minX +
                              geometry.bubbleDiameterMm
                          ).toFixed(2),
                      ),
                      h_mm: Number(
                          (
                              pointBounds.maxY -
                              pointBounds.minY +
                              geometry.bubbleDiameterMm
                          ).toFixed(2),
                      ),
                  }
                : qCount === 1
                  ? {
                        w_mm: Number(
                            (
                                geometry.bubbleDiameterMm +
                                Math.max(0, opts - 1) * geometry.bubblePitchMm
                            ).toFixed(2),
                        ),
                        h_mm: Number(geometry.bubbleDiameterMm.toFixed(2)),
                    }
                  : contentBlockSizeMm(
                        opts,
                        qCount,
                        geometry.bubblePitchMm,
                        geometry.rowPitchMm,
                        geometry.bubbleDiameterMm,
                        stage1Mode !== 'html' ? 2.5 : undefined,
                    );
            return {
                id: s.id,
                area_code: s.area_code || s.aptitudeAreaCode,
                subtest_code:
                    s.subtest_code ||
                    s.area_code ||
                    s.aptitudeAreaCode ||
                    undefined,
                test_instrument_subtest_id:
                    s.test_instrument_subtest_id != null
                        ? Number(s.test_instrument_subtest_id)
                        : undefined,
                type: s.type,
                page_no: Number(s.page_no) || 1,
                x_mm:
                    s.posX != null && Number.isFinite(Number(s.posX))
                        ? Number(s.posX)
                        : 15,
                y_mm:
                    s.posY != null && Number.isFinite(Number(s.posY))
                        ? Number(s.posY)
                        : 84,
                w_mm: pointBounds
                    ? w_mm
                    : qCount === 1
                      ? w_mm
                      : Math.max(w_mm, Number(s.w_mm) || 0),
                h_mm: pointBounds
                    ? h_mm
                    : qCount === 1
                      ? h_mm
                      : Math.max(h_mm, Number(s.h_mm) || 0),
                questions: qCount,
                cols: Number(s.cols) || 1,
                options: opts,
                start_q: Number(s.startQ) || 1,
                custom_values: resolvedOptionLabels,
                bubble_values: resolvedOptionLabels,
                optionLabels: resolvedOptionLabels,
                bubble_diameter_mm: geometry.bubbleDiameterMm,
                bubble_pitch_mm: geometry.bubblePitchMm,
                row_pitch_mm: geometry.rowPitchMm,
                option_points_mm: hasExactOptionPoints
                    ? s.optionPointsMm.map((point) => ({
                          label: point.label,
                          x_mm: Number(point.x_mm),
                          y_mm: Number(point.y_mm),
                      }))
                    : undefined,
                rotation_deg: Number(s.rotationDeg) || 0,
            };
        });

        if (showRollOverlay) {
            const digits = Math.max(2, Math.min(10, Number(rollDigits) || 6));
            const geometry = effectiveRecognitionGeometryMm({ kind: 'roll' });
            blocks.push({
                id: 'Student_ID',
                type: 'roll',
                page_no: activePageNo,
                x_mm: rollX,
                y_mm: rollY,
                w_mm: Number(
                    (
                        geometry.bubbleDiameterMm +
                        (digits - 1) * geometry.bubblePitchMm +
                        1.5
                    ).toFixed(1),
                ),
                h_mm: Number(
                    (
                        geometry.bubbleDiameterMm +
                        (10 - 1) * geometry.rowPitchMm +
                        1.5
                    ).toFixed(1),
                ),
                digits,
                bubble_diameter_mm: geometry.bubbleDiameterMm,
                bubble_pitch_mm: geometry.bubblePitchMm,
                row_pitch_mm: geometry.rowPitchMm,
            });
        }

        const overlayBlocks = [];
        if (qrEnabled && showQrOverlay) {
            overlayBlocks.push({
                id: 'Exam_QR',
                type: 'qr',
                page_no: activePageNo,
                tag: 'blank_instance_qr',
                scope: 'blank_instance',
                x_mm: qrX,
                y_mm: qrY,
                w_mm: qrW,
                h_mm: qrH,
            });
        }
        if (showHeaderOverlay) {
            overlayBlocks.push({
                id: 'Header_Text',
                type: 'header',
                page_no: activePageNo,
                tag: 'header_title',
                scope: 'template',
                x_mm: headerX,
                y_mm: headerY,
                w_mm: headerW,
                h_mm: headerH,
                font_size: headerFontSize,
                content: headerTitle,
            });
        }
        if (showSignatureOverlay) {
            overlayBlocks.push({
                id: 'Signature_Area',
                type: 'signature',
                page_no: activePageNo,
                tag: 'signature_box',
                scope: 'template',
                x_mm: sigX,
                y_mm: sigY,
                w_mm: sigW,
                h_mm: sigH,
                font_size: signatureFontSize,
                content: signatureContent,
            });
        }
        if (showInstructionsOverlay) {
            overlayBlocks.push({
                id: 'Instructions_Box',
                type: 'instructions',
                page_no: activePageNo,
                tag: 'instructions_box',
                scope: 'template',
                x_mm: instX,
                y_mm: instY,
                w_mm: instW,
                h_mm: instH,
                font_size: instructionsFontSize,
                content: instructionsContent,
            });
        }
        if (showMetadataOverlay) {
            overlayBlocks.push({
                id: 'Metadata_Box',
                type: 'metadata',
                page_no: activePageNo,
                tag: 'metadata_box',
                scope: 'template',
                x_mm: metaX,
                y_mm: metaY,
                w_mm: metaW,
                h_mm: metaH,
                font_size: metadataFontSize,
                content: metadataContent,
            });
        }

        return { blocks, overlayBlocks };
    }

    function performSave(sourceKindOverride = null) {
        if (isReadOnly) return false;
        const safeSourceKindOverride =
            typeof sourceKindOverride === 'string' ? sourceKindOverride : null;
        saveStatus = 'saving';
        const queuedSave = studioSaveQueue.then(
            () => saveStudioState(safeSourceKindOverride),
            () => saveStudioState(safeSourceKindOverride),
        );
        studioSaveQueue = queuedSave.catch(() => false);
        return queuedSave;
    }
    async function handleStudioExport(event) {
        if (isReadOnly || (saveStatus === 'saved' && !autosaveTimer)) return;
        event.preventDefault();
        if (autosaveTimer) {
            clearTimeout(autosaveTimer);
            autosaveTimer = null;
        }
        if (!(await performSave()) || !exportUrl) return;

        const exportLink = document.createElement('a');
        exportLink.href = exportUrl;
        exportLink.download = '';
        exportLink.style.display = 'none';
        document.body.appendChild(exportLink);
        exportLink.click();
        exportLink.remove();
    }

    async function saveStudioState(sourceKindOverride = null) {
        if (isReadOnly) return false;
        const saveUrl =
            urls.studio || `/setup/exam-materials/answer-sheets/${draft.id}`;
        const persistedSourceKind = sourceKindOverride ?? baseType;

        try {
            const { blocks, overlayBlocks } = buildBlockList();
            const clientValidation = validateStudioLayout(
                { width_mm: 210, height_mm: 297, margin_mm: marginMm },
                { top_mm: 12, right_mm: 12, bottom_mm: 14, left_mm: 12 },
                blocks,
            );
            if (!clientValidation.valid) {
                studioSaveIssues = clientValidation.warnings
                    .filter((warning) => warning.severity === 'error')
                    .map((warning) => ({
                        code: warning.code,
                        block: warning.block_id,
                        message: warning.message,
                    }));
                saveStatus = 'error';
                errorMessage =
                    studioSaveIssues[0]?.message ||
                    'Recognition guides overlap. Move the selected guide before saving.';
                toastError(errorMessage || 'Failed to save studio state.', {
                    duration: 0,
                    dedupeKey: 'studio-save-error',
                });
                return false;
            }
            const nativeMapping = emitNativeMapping(
                210,
                297,
                blocks,
                Number(draft.requested_page_count) || orderedPages.length || 1,
                stage1Mode !== 'html' ? { minBubbleMm: 2.5 } : undefined,
            );
            syncUploadedDeclarationsFromSections();
            if (stage1Mode !== 'html') {
                nativeMapping.declared_inventory = declaredInventory.map(
                    (entry) => ({
                        page_no: Number(entry.page_no),
                        area_code: String(entry.area_code ?? '').trim(),
                        question_count:
                            entry.question_count === '' ||
                            entry.question_count == null
                                ? null
                                : Number(entry.question_count),
                        option_labels: Array.isArray(entry.option_labels)
                            ? entry.option_labels
                            : [],
                    }),
                );
                nativeMapping.identity = identityDeclaration;
            }
            const overlayBoxes = emitOverlayBoxes(overlayBlocks, 210, 297);
            const studioPayload = {
                current_stage: currentStage,
                active_page_no: activePageNo,
                sections,
                components,
                declaredInventory,
                physicalInventory,
                physicalInventoryConfirmation,
                identity: identityDeclaration,
                document_fields: documentFields,
                modifyUploadedDocument,
                marginMm,
                ...(usesFiducials ? { markerPattern } : {}),
                bubbleRadiusMm,
                smartSnapSearchMm: Number(smartSnapSearchMm) || 5.0,
                showHeaderOverlay,
                headerTitle,
                headerSubtitle,
                headerFontSize,
                headerFontFamily,
                headerColor,
                headerAlign,
                headerX,
                headerY,
                headerW,
                headerH,
                headerLocked,
                ...(qrEnabled
                    ? { showQrOverlay, qrX, qrY, qrW, qrH, qrLocked }
                    : {}),
                showInstructionsOverlay,
                instructionsContent,
                instructionsFontSize,
                instructionsFontFamily,
                instructionsColor,
                instX,
                instY,
                instW,
                instH,
                instLocked,
                showSignatureOverlay,
                signatureContent,
                signatureFontSize,
                signatureFontFamily,
                signatureColor,
                sigX,
                sigY,
                sigW,
                sigH,
                sigLocked,
                metadataContent,
                metadataFontSize,
                showRollOverlay,
                rollDigits,
                customTextItems,
                htmlCode,
                stage1Mode,
                baseType: persistedSourceKind,
                ...(cropReviewRequired && cropReviewAcknowledged
                    ? { reviewed_crop_asset_id: Number(orderedPages[0]?.asset_id) }
                    : {}),
                palette_filter: componentPaletteFilter,
            };
            const res = await fetch(saveUrl, {
                method: 'PUT',
                headers: {
                    'Content-Type': 'application/json',
                    Accept: 'application/json',
                    ...csrfHeaders(),
                    'X-Requested-With': 'XMLHttpRequest',
                },
                body: JSON.stringify({
                    mapping: nativeMapping,
                    overlay: overlayBoxes,
                    studio: studioPayload,
                    source_kind: persistedSourceKind,
                    lock_version: currentLockVersion,
                    idempotency_key: newIdempotencyKey(),
                }),
            });
            if (res.ok) {
                const data = await res.json();
                if (data?.lock_version != null) {
                    syncDraftLockVersion(data.lock_version);
                }
                draft.source_kind = persistedSourceKind;
                const resolvedStage =
                    (typeof data.status === 'string' && data.status !== 'saved'
                        ? data.status
                        : data.stage) ??
                    data.status ??
                    draft.stage;
                draft.stage = resolvedStage;
                draft.mapping_current = Boolean(
                    data.mapping_current ??
                        (resolvedStage === 'mapping_complete' ||
                            resolvedStage === 'digital_verified' ||
                            resolvedStage === 'compiled'),
                );
                draft.target_current =
                    data.target_current ?? draft.target_current;
                studioSaveIssues =
                    Array.isArray(data.mapping?.errors) &&
                    data.mapping.errors.length > 0
                        ? data.mapping.errors
                        : [];
                saveStatus = 'saved';
                physicalInventoryConfirmation = null;
                errorMessage = '';
                if (cropReviewRequired && cropReviewAcknowledged) {
                    cropReviewRequired = false;
                    cropReviewAcknowledged = false;
                }
                return true;
            }

            const data = await res.json().catch(() => ({}));
            if (data?.lock_version != null) {
                syncDraftLockVersion(data.lock_version);
            }
            saveStatus = 'error';
            studioSaveIssues = normalizeStudioIssues(
                data,
                'Failed to save studio state.',
            );
            errorMessage =
                data?.detail ||
                data?.message ||
                'Failed to save studio state.';
            toastError(errorMessage || 'Failed to save studio state.', {
                duration: 0,
                dedupeKey: 'studio-save-error',
            });
            return false;
        } catch (err) {
            studioSaveIssues = [
                {
                    message:
                        err?.message ||
                        'Network error while saving studio state.',
                },
            ];
            saveStatus = 'error';
            errorMessage = studioSaveIssues[0].message;
            toastError(errorMessage || 'Failed to save studio state.', {
                duration: 0,
                dedupeKey: 'studio-save-error',
            });
            return false;
        }
    }

    function clearPhotoCrop() {
        if (photoCropUrl) URL.revokeObjectURL(photoCropUrl);
        photoCropUrl = null;
        photoCropFile = null;
        photoCropPreviewUrl = null;
        photoCropDragging = -1;
        photoCropRotation = 0;
        photoCropKey = null;
        photoCropIsCorrection = false;
        photoCropIsPageReplacement = false;
        photoCropTouchedCorners = [];
    }
    function cancelPhotoCrop() {
        clearPhotoCrop();
        pendingCropQueue = [];
        pendingCropQueueIndex = 0;
        photoCropTargetPageNo = 1;
    }

    function resetPhotoCropToSheet(inset = 0.02) {
        photoCropCorners = resetPhotoCropCorners(inset);
        photoCropSuggestion = false;
        photoCropTouchedCorners = [];
        photoCropKey = newIdempotencyKey();
    }

    async function rotatePhotoCropClockwise() {
        if (!photoCropFile || uploadRunning) return;
        photoCropRotation = (photoCropRotation + 90) % 360;
        photoCropCorners = rotatePhotoCropCornersClockwise(photoCropCorners);
        photoCropTouchedCorners = [];
        if (photoCropWidth > 0 && photoCropHeight > 0) {
            const prevW = photoCropWidth;
            photoCropWidth = photoCropHeight;
            photoCropHeight = prevW;
        }
        photoCropKey = newIdempotencyKey();

        try {
            if (typeof createImageBitmap === 'function') {
                const bitmap = await createImageBitmap(photoCropFile);
                const canvas = document.createElement('canvas');
                canvas.width = bitmap.height;
                canvas.height = bitmap.width;
                const ctx = canvas.getContext?.('2d');
                if (ctx) {
                    ctx.translate(canvas.width / 2, canvas.height / 2);
                    ctx.rotate(Math.PI / 2);
                    ctx.drawImage(bitmap, -bitmap.width / 2, -bitmap.height / 2);
                    bitmap.close?.();
                    const blob = await new Promise((resolve) =>
                        canvas.toBlob(
                            resolve,
                            photoCropFile.type || 'image/jpeg',
                            0.95,
                        ),
                    );
                    if (blob) {
                        const rotatedFile = new File(
                            [blob],
                            photoCropFile.name || 'rotated.jpg',
                            { type: blob.type || 'image/jpeg' },
                        );
                        if (photoCropUrl) URL.revokeObjectURL(photoCropUrl);
                        photoCropFile = rotatedFile;
                        if (pendingCropQueueIndex < pendingCropQueue.length) {
                            pendingCropQueue[pendingCropQueueIndex] = rotatedFile;
                        }
                        photoCropUrl = URL.createObjectURL(rotatedFile);
                        photoCropWidth = canvas.width;
                        photoCropHeight = canvas.height;
                    }
                } else {
                    bitmap.close?.();
                }
            }
        } catch {
            // Corner coordinates and dimensions are already rotated in state.
        }
    }

    async function suggestPhotoCorners(file) {
        const bitmap = await createImageBitmap(file, {
            imageOrientation: 'from-image',
        });
        photoCropWidth = bitmap.width;
        photoCropHeight = bitmap.height;

        try {
            const scale = Math.min(
                1,
                384 / Math.max(bitmap.width, bitmap.height, 1),
            );
            const sampleW = Math.max(32, Math.round(bitmap.width * scale));
            const sampleH = Math.max(32, Math.round(bitmap.height * scale));
            const canvas = document.createElement('canvas');
            canvas.width = sampleW;
            canvas.height = sampleH;
            const ctx = canvas.getContext?.('2d');
            if (ctx) {
                ctx.drawImage(bitmap, 0, 0, sampleW, sampleH);
                const imgData = ctx.getImageData(0, 0, sampleW, sampleH);
                const staticQuad = detectStaticPaperCornersFromPixels(
                    imgData.data,
                    sampleW,
                    sampleH,
                );
                if (staticQuad) {
                    bitmap.close?.();
                    return staticQuad;
                }
            }
        } catch {
            // Fall through to viewfinder worker suggestion
        }

        if (typeof Worker === 'undefined') {
            bitmap.close();
            return null;
        }
        const worker = new Worker(
            new URL('../../../../lib/omrViewfinderWorker.ts', import.meta.url),
            { type: 'module' },
        );
        return new Promise((resolve) => {
            const timeout = window.setTimeout(() => {
                worker.terminate();
                resolve(null);
            }, 3000);
            worker.onmessage = ({ data }) => {
                window.clearTimeout(timeout);
                worker.terminate();
                resolve(
                    data?.type === 'analysis' && data.gates?.in_bounds
                        ? data.quad
                        : null,
                );
            };
            worker.onerror = () => {
                window.clearTimeout(timeout);
                worker.terminate();
                resolve(null);
            };
            worker.postMessage(
                {
                    type: 'analyze',
                    generation: 1,
                    frameId: 1,
                    capturedAt: 1,
                    expectedAspectRatio: 210 / 297,
                    bitmap,
                },
                [bitmap],
            );
        });
    }

    async function preparePhotoCrop(file, manualOnly = false) {
        clearPhotoCrop();
        photoCropFile = file;
        photoCropUrl = URL.createObjectURL(file);
        photoCropCorners = [
            { x: 0.06, y: 0.06 },
            { x: 0.94, y: 0.06 },
            { x: 0.94, y: 0.94 },
            { x: 0.06, y: 0.94 },
        ];
        photoCropSuggestion = false;
        photoCropKey = newIdempotencyKey();
        conversionStatusText = manualOnly
            ? 'Position the paper corners manually…'
            : 'Finding the paper edges…';
        try {
            if (manualOnly) {
                const bitmap = await createImageBitmap(file, {
                    imageOrientation: 'from-image',
                });
                photoCropWidth = bitmap.width;
                photoCropHeight = bitmap.height;
                bitmap.close?.();
                return;
            }
            const suggestion = await suggestPhotoCorners(file);
            if (
                photoCropFile === file &&
                Array.isArray(suggestion) &&
                suggestion.length === 4 &&
                suggestion.every(
                    (point) =>
                        Number.isFinite(point.x) &&
                        Number.isFinite(point.y) &&
                        point.x > 0 &&
                        point.x < 1 &&
                        point.y > 0 &&
                        point.y < 1,
                )
            ) {
                photoCropCorners = suggestion;
                photoCropSuggestion = true;
            }
        } catch (error) {
            if (manualOnly) {
                clearPhotoCrop();
                throw error;
            }
            // The manual corner controls remain available when detection is unavailable.
        } finally {
            uploadRunning = false;
        }
    }

    async function openOriginalPhotoCrop() {
        const targetPage = Math.max(1, activePageNo || 1);
        photoCropTargetPageNo = targetPage;
        const currentPageEntry = (Array.isArray(draft.authoring?.pages) ? draft.authoring.pages : orderedPages)
            ?.find((p) => Number(p.page_no) === targetPage);
        const pageOriginalAssetId = Number(currentPageEntry?.original_asset_id);
        const originalUrl = pageOriginalAssetId > 0
            ? `/setup/omr/templates/canonical/drafts/${draft.id}/assets/${pageOriginalAssetId}`
            : (draft.original_photo_urls?.[targetPage] || draft.original_photo_url);
        if (!originalUrl || uploadRunning || stageTransitionRunning)
            return;
        cropRecoveryError = '';
        uploadRunning = true;
        try {
            const response = await fetch(originalUrl, {
                headers: { Accept: 'image/*' },
            });
            if (!response.ok) throw new Error('The original photo is unavailable.');
            const original = await response.blob();
            if (!original.type.startsWith('image/'))
                throw new Error('The original photo is not an image.');
            await preparePhotoCrop(
                new File([original], `original-answer-sheet-p${targetPage}.jpg`, {
                    type: original.type,
                }),
                true,
            );
            photoCropIsCorrection = true;
        } catch (error) {
            cropRecoveryError =
                error?.message || 'Could not open the original photo.';
        } finally {
            uploadRunning = false;
        }
    }

    async function confirmCropReview() {
        cropReviewAcknowledged = true;
        if (!(await performSave())) cropReviewAcknowledged = false;
    }

    function movePhotoCorner(index, clientX, clientY) {
        if (!photoCropSurface) return;
        const bounds = photoCropSurface.getBoundingClientRect();
        const width = bounds.width || photoCropSurface.clientWidth || 400;
        const height = bounds.height || photoCropSurface.clientHeight || 560;
        photoCropCorners = photoCropCorners.map((corner, cornerIndex) =>
            cornerIndex === index
                ? {
                      x: Number(
                          Math.max(
                              0.001,
                              Math.min(0.999, (clientX - bounds.left) / width),
                          ).toFixed(4),
                      ),
                      y: Number(
                          Math.max(
                              0.001,
                              Math.min(0.999, (clientY - bounds.top) / height),
                          ).toFixed(4),
                      ),
                  }
                : corner,
        );
        photoCropKey = newIdempotencyKey();
        if (!photoCropTouchedCorners.includes(index))
            photoCropTouchedCorners = [...photoCropTouchedCorners, index];
    }

    function nudgePhotoCorner(event, index) {
        const direction = {
            ArrowLeft: [-0.005, 0],
            ArrowRight: [0.005, 0],
            ArrowUp: [0, -0.005],
            ArrowDown: [0, 0.005],
        }[event.key];
        if (!direction) return;
        event.preventDefault();
        photoCropCorners = photoCropCorners.map((corner, cornerIndex) =>
            cornerIndex === index
                ? {
                      x: Math.max(0.001, Math.min(0.999, corner.x + direction[0])),
                      y: Math.max(0.001, Math.min(0.999, corner.y + direction[1])),
                  }
                : corner,
        );
        photoCropKey = newIdempotencyKey();
        if (!photoCropTouchedCorners.includes(index))
            photoCropTouchedCorners = [...photoCropTouchedCorners, index];
    }

    async function confirmPhotoCrop() {
        const rectifyUrl = urls.rectify ?? urls.source_rectify;
        if (!photoCropFile || !rectifyUrl || uploadRunning) return;
        if (photoCropIsCorrection && photoCropTouchedCorners.length !== 4) {
            conversionError = 'Position all four corner pins on the paper before replacing its crop.';
            return;
        }
        uploadRunning = true;
        conversionError = '';
        conversionStatusText = 'Straightening the photographed page…';
        try {
            if (photoCropIsCorrection) {
                if (autosaveTimer) {
                    clearTimeout(autosaveTimer);
                    autosaveTimer = null;
                }
                if (!(await performSave())) {
                    conversionError =
                        errorMessage ||
                        'Save your existing guides before correcting the crop.';
                    return;
                }
            }
            const form = new FormData();
            form.append('source', photoCropFile);
            photoCropCorners.forEach((point, index) => {
                form.append(
                    `points[${index}][0]`,
                    String(Math.round(point.x * (photoCropWidth - 1))),
                );
                form.append(
                    `points[${index}][1]`,
                    String(Math.round(point.y * (photoCropHeight - 1))),
                );
            });
            form.append('target_width', '2480');
            form.append('target_height', '3508');
            form.append('lock_version', String(currentLockVersion));
            form.append('idempotency_key', photoCropKey);
            form.append('page_no', String(photoCropTargetPageNo));
            const response = await fetch(rectifyUrl, {
                method: 'POST',
                headers: { ...csrfHeaders(), Accept: 'application/json' },
                body: form,
            });
            const body = await response.json().catch(() => ({}));
            if (!response.ok) {
                throw typedRequestError(body, 'Could not straighten the page.');
            }
            syncDraftLockVersion(body.lock_version);
            pendingUploadedPages = body.pages ?? [];
            orderedPages = body.pages ?? orderedPages;
            if (!draft.authoring) draft.authoring = {};
            draft.authoring.pages = body.pages ?? [];
            if (draft.authoring.canonical) {
                draft.authoring.canonical.raster_assets = [];
                draft.authoring.canonical.artifact_set = null;
            }
            if (body.preview_url) {
                draft.raster_url = body.preview_url;
                currentRasterUrl = body.preview_url;
            }
            canonicalPreview = null;
            previewError = null;
            mappingDirty = true;
            if (photoCropIsPageReplacement || sections.length > 0) {
                cropReviewRequired = true;
            }
            cropReviewAcknowledged = false;
            const requestedCount = Number(draft.requested_page_count) || 1;
            const nextQueueIdx = pendingCropQueueIndex + 1;
            const nextTargetPage = photoCropTargetPageNo + 1;

            if (nextQueueIdx < pendingCropQueue.length && nextTargetPage <= requestedCount) {
                pendingCropQueueIndex = nextQueueIdx;
                photoCropTargetPageNo = nextTargetPage;
                activePageNo = nextTargetPage;
                const nextFile = pendingCropQueue[nextQueueIdx];
                conversionStatusText = `Page ${photoCropTargetPageNo - 1} straightened! Finding edges for Page ${photoCropTargetPageNo}…`;
                await preparePhotoCrop(nextFile);
            } else {
                clearPhotoCrop();
                pendingCropQueue = [];
                pendingCropQueueIndex = 0;
                photoCropPreviewUrl = body.preview_url;
                showUploadedPageConfirmation = true;
            }
        } catch (error) {
            conversionError =
                error?.message ||
                'Could not straighten the page. Adjust the corners and retry.';
        } finally {
            uploadRunning = false;
        }
    }

    async function handleReplacePageImageFile(event) {
        const input = event.currentTarget;
        const file = input?.files?.[0] ?? null;
        if (input) input.value = '';

        const rectifyUrl = urls.rectify ?? urls.source_rectify;
        if (
            !file ||
            !rectifyUrl ||
            isReadOnly ||
            stage1Mode === 'html' ||
            uploadRunning
        ) {
            return;
        }

        if (autosaveTimer) {
            clearTimeout(autosaveTimer);
            autosaveTimer = null;
        }
        conversionError = '';
        conversionStatusText = `Preparing Page ${activePageNo} image…`;
        if (!(await performSave())) {
            conversionError =
                errorMessage || 'Could not save the current studio state.';
            return;
        }

        const targetPageNo = Math.max(1, Number(activePageNo) || 1);
        clearPhotoCrop();
        pendingCropQueue = [];
        pendingCropQueueIndex = 0;
        photoCropTargetPageNo = targetPageNo;
        uploadRunning = true;
        try {
            await preparePhotoCrop(file);
            if (!photoCropFile) {
                return;
            }
            photoCropTargetPageNo = targetPageNo;
            photoCropIsPageReplacement = true;
        } catch (error) {
            uploadRunning = false;
            clearPhotoCrop();
            conversionError =
                error?.message || 'Could not prepare the replacement image.';
        }
    }

    // File Ingestion Handler
    async function handleFileUpload(e) {
        const fileList = Array.from(e.target.files ?? []);
        if (fileList.length === 0 || !urls.source_ingest || uploadRunning) return;
        const file = fileList[0];
        clearPhotoCrop();
        canonicalPreview = null;
        draft.raster_url = null;
        currentRasterUrl = null;
        if (draft.authoring?.canonical) {
            draft.authoring.canonical.raster_assets = [];
            draft.authoring.canonical.artifact_set = null;
        }

        const isImageUpload = fileList.every(
            (f) =>
                f.type.startsWith('image/') ||
                Boolean(f.name.toLowerCase().match(/\.(png|jpg|jpeg|webp)$/i)),
        );
        const bypassCrop =
            imageAlreadyCropped && Number(draft.requested_page_count) === 1;

        if (isImageUpload && !bypassCrop) {
            uploadRunning = true;
            conversionError = '';
            conversionStatusText = 'Preparing paper photo crop…';
            baseType = 'image';
            if (autosaveTimer) {
                clearTimeout(autosaveTimer);
                autosaveTimer = null;
            }
            const saved = await performSave('image');
            if (!saved) {
                uploadRunning = false;
                conversionError =
                    errorMessage || 'Could not save the image source type.';
                return;
            }
            if (!(urls.rectify ?? urls.source_rectify)) {
                uploadRunning = false;
                conversionError =
                    'Photo cropping is unavailable. Try again later.';
                return;
            }
            pendingCropQueue = fileList;
            pendingCropQueueIndex = 0;
            const requestedCount = Number(draft.requested_page_count) || 1;
            const existingPages = Array.isArray(draft.authoring?.pages)
                ? draft.authoring.pages
                : [];
            const unrectifiedPage = Array.from(
                { length: requestedCount },
                (_, i) => i + 1,
            ).find(
                (p) =>
                    !existingPages.some(
                        (ep) => Number(ep.page_no) === p && ep.asset_id,
                    ),
            );
            photoCropTargetPageNo =
                fileList.length > 1 ? 1 : (unrectifiedPage ?? activePageNo ?? 1);

            await preparePhotoCrop(pendingCropQueue[0]);
            return;
        }
        uploadRunning = true;
        conversionStatusText = `Uploading ${file.name}…`;
        conversionError = '';

        const filename = file.name.toLowerCase();
        let detectedKind = 'image';
        if (filename.endsWith('.pdf')) detectedKind = 'pdf';
        else if (filename.endsWith('.docx')) detectedKind = 'docx';
        else if (filename.endsWith('.html') || filename.endsWith('.htm'))
            detectedKind = 'html';

        baseType = detectedKind;
        if (autosaveTimer) {
            clearTimeout(autosaveTimer);
            autosaveTimer = null;
        }
        const saved = await performSave(detectedKind);
        if (!saved) {
            uploadRunning = false;
            conversionError =
                errorMessage || 'Could not save the selected source type.';
            return;
        }

        const form = new FormData();
        form.append('source_kind', detectedKind);
        form.append('source', file);
        form.append('width_mm', '210');
        form.append('height_mm', '297');
        form.append('idempotency_key', newIdempotencyKey());

        try {
            const res = await fetch(urls.source_ingest, {
                method: 'POST',
                headers: { ...csrfHeaders(), Accept: 'application/json' },
                body: form,
            });

            if (!res.ok) {
                const data = await res.json().catch(() => ({}));
                throw typedRequestError(data, 'File upload rejected.');
            }

            conversionStatusText = `Converting ${file.name}…`;
            await pollConversionRun();
        } catch (err) {
            uploadRunning = false;
            conversionError = err.message || 'File conversion failed.';
        }
    }

    async function pollConversionRun() {
        if (!urls.source_run) {
            uploadRunning = false;
            return;
        }

        const pollInterval = setInterval(async () => {
            try {
                const res = await fetch(urls.source_run, {
                    headers: { Accept: 'application/json' },
                });
                if (res.ok) {
                    const body = await res.json();
                    const run = body.run;
                    if (
                        run?.status === 'completed' ||
                        run?.status === 'succeeded'
                    ) {
                        clearInterval(pollInterval);
                        conversionStatusText =
                            'Conversion completed. Review the detected pages.';
                        pendingUploadedPages = Array.isArray(body.pages)
                            ? body.pages
                            : [];
                        syncDraftLockVersion(body.lock_version);
                        uploadRunning = false;
                        showUploadedPageConfirmation = true;
                    } else if (run?.status === 'failed') {
                        clearInterval(pollInterval);
                        uploadRunning = false;
                        conversionError =
                            run?.error || 'Document conversion failed.';
                    } else if (run?.status === 'processing') {
                        conversionStatusText = 'Preparing print sheet…';
                    }
                }
            } catch {
                clearInterval(pollInterval);
                uploadRunning = false;
            }
        }, 1500);
    }

    async function confirmUploadedPages() {
        if (!urls.confirm_pages || pendingUploadedPages.length === 0) return;
        uploadRunning = true;
        conversionError = '';
        try {
            const response = await fetch(urls.confirm_pages, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    ...csrfHeaders(),
                    Accept: 'application/json',
                },
                body: JSON.stringify({
                    pages: pendingUploadedPages.map((page) => ({
                        page_no: Number(page.page_no),
                        decision: 'confirmed',
                    })),
                    lock_version: currentLockVersion,
                }),
            });
            const body = await response.json().catch(() => ({}));
            if (!response.ok) {
                throw typedRequestError(body, 'Page confirmation failed.');
            }
            syncDraftLockVersion(body.lock_version);
            orderedPages = body.pages;
            if (!draft.authoring) draft.authoring = {};
            draft.authoring.pages = body.pages;
            if (draft.authoring.canonical) {
                draft.authoring.canonical.raster_assets = [];
                draft.authoring.canonical.artifact_set = null;
            }
            canonicalPreview = null;
            showUploadedPageConfirmation = false;
            photoCropPreviewUrl = null;
            if (stage1Mode !== 'html' && !modifyUploadedDocument) {
                if (await composeUploadedSource({ fullBleed: true }))
                    await executeGoToStage(3);
            } else {
                executeGoToStage(2);
            }
        } catch (error) {
            conversionError =
                error?.message || 'The uploaded pages could not be confirmed.';
        } finally {
            uploadRunning = false;
        }
    }

    function zoomStep(delta) {
        scale = Math.min(Math.max(MIN_STUDIO_SCALE, scale + delta), MAX_STUDIO_SCALE);
    }

    function resetZoom() {
        scale = 0.78;
        panX = 0;
        panY = 0;
    }

    /** Svelte action: strips padding and overflow from the page-layout <main> and its slot wrapper
      so this component fills the remaining viewport edge-to-edge with zero scroll. */
    function bleedParent(node) {
        const slot = node.parentElement;
        const pageMain = slot?.closest('main');
        if (!pageMain) return;
        const savedMain = {
            padding: pageMain.style.padding,
            overflow: pageMain.style.overflow,
            position: pageMain.style.position,
        };
        const savedSlot = slot
            ? { overflow: slot.style.overflow, maxHeight: slot.style.maxHeight }
            : null;
        pageMain.style.padding = '0';
        pageMain.style.overflow = 'hidden';
        pageMain.style.position = 'relative';
        if (slot && slot !== pageMain) {
            slot.style.overflow = 'hidden';
            slot.style.maxHeight = '100%';
        }
        return {
            destroy() {
                pageMain.style.padding = savedMain.padding;
                pageMain.style.overflow = savedMain.overflow;
                pageMain.style.position = savedMain.position;
                if (slot && savedSlot) {
                    slot.style.overflow = savedSlot.overflow;
                    slot.style.maxHeight = savedSlot.maxHeight;
                }
            },
        };
    }

</script>

<!-- FULL-BLEED WORKSPACE: fills parent exactly. onMount strips the page layout <main> padding/overflow
     so this container occupies the entire remaining viewport without any scroll. -->
<div
    class="studio-shell bg-muted absolute inset-0 overflow-hidden select-none"
    use:bleedParent
>
    <!-- MOBILE NOTIFICATION: Desktop-only notice for narrow screens -->
    <div
        class="bg-background/95 fixed inset-0 z-[100] flex flex-col items-center justify-center p-6 text-center backdrop-blur-md lg:hidden"
    >
        <div
            class="border-border bg-card max-w-sm space-y-4 rounded-2xl border p-6 shadow-2xl"
        >
            <div
                class="mx-auto flex size-12 items-center justify-center rounded-2xl bg-amber-500/15 text-amber-600 dark:text-amber-400"
            >
                <Monitor class="size-6" />
            </div>
            <div class="space-y-1.5">
                <h3 class="text-foreground font-sans text-base font-bold">
                    Desktop Screen Required
                </h3>
                <p class="text-muted-foreground text-xs leading-relaxed">
                    Answer Sheet Studio requires a desktop or large display to
                    design optical bubble grids and fiducial markers. This
                    feature is not available on mobile screens.
                </p>
            </div>
            <div class="pt-2">
                {#if kit_context}
                    <button
                        type="button"
                        onclick={returnToKit}
                        class="btn-tactile-primary inline-flex w-full items-center justify-center gap-1.5 rounded-xl py-2.5 text-xs font-semibold shadow-xs"
                    >
                        <ArrowLeft class="size-3.5" />
                        <span>Return to {kit_context.code}</span>
                    </button>
                {:else}
                    <a
                        href="/setup/exam-materials/answer-sheets"
                        class="btn-tactile-primary inline-flex w-full items-center justify-center gap-1.5 rounded-xl py-2.5 text-xs font-semibold shadow-xs"
                    >
                        <ArrowLeft class="size-3.5" />
                        <span>Return to Answer Sheets</span>
                    </a>
                {/if}
            </div>
        </div>
    </div>
    <!-- ENTRY GATE: Choose how to start before any canvas or template UI appears -->
    {#if !hasChosenMode}
        <div
            class="bg-background absolute inset-0 z-[60] flex items-center justify-center p-6"
        >
            <div class="w-full max-w-2xl space-y-6">
                <div class="space-y-1.5 text-center">
                    <h2 class="text-foreground text-xl font-black">
                        Start a New Template
                    </h2>
                    <p class="text-muted-foreground text-sm">
                        Choose how you want to create this answer sheet. This
                        choice defines your workflow.
                    </p>
                </div>
                <div class="grid grid-cols-2 gap-4">
                    <button
                        type="button"
                        onclick={() => chooseMode('upload')}
                        class="group bg-card border-primary/60 space-y-3 rounded-2xl border-2 p-6 text-left shadow-sm transition hover:border-blue-500 hover:shadow-md"
                    >
                        <div class="flex items-center justify-between gap-2">
                            <div
                                class="flex size-11 items-center justify-center rounded-xl bg-blue-500/10 text-blue-600 dark:text-blue-400"
                            >
                                <Upload class="size-5" />
                            </div>
                            <span
                                class="rounded-full bg-blue-500/10 px-2.5 py-0.5 text-[10px] font-bold tracking-wide text-blue-700 uppercase dark:text-blue-300"
                            >
                                Default · Licensed Stock
                            </span>
                        </div>
                        <div>
                            <h3 class="text-foreground text-sm font-black">
                                Upload Existing Sheet
                            </h3>
                            <p
                                class="text-muted-foreground mt-1 text-xs leading-relaxed"
                            >
                                {Number(draft.requested_page_count) > 1
                                    ? `Register an existing ${draft.requested_page_count}-page supplier PDF or Word document as a private recognition template.`
                                    : 'Register a supplier-issued PDF, Word document, or photographed answer sheet as a private recognition template without reproducing it.'}
                            </p>
                        </div>
                        <span
                            class="text-[11px] font-bold text-blue-700 group-hover:underline"
                            >Upload a file →</span
                        >
                    </button>
                    <button
                        type="button"
                        onclick={() => chooseMode('html')}
                        class="group bg-card border-border space-y-3 rounded-2xl border-2 p-6 text-left shadow-sm transition hover:border-emerald-500 hover:shadow-md"
                    >
                        <div
                            class="flex size-11 items-center justify-center rounded-xl bg-emerald-500/10 text-emerald-600 dark:text-emerald-400"
                        >
                            <Sliders class="size-5" />
                        </div>
                        <div>
                            <h3 class="text-foreground text-sm font-black">
                                Sheet Designer
                            </h3>
                            <p
                                class="text-muted-foreground mt-1 text-xs leading-relaxed"
                            >
                                {Number(draft.requested_page_count) > 1
                                    ? `Build a ${draft.requested_page_count}-page institution-owned answer sheet with page navigation, candidate fields, ID matrix, and question grids.`
                                    : 'Build an institution-owned answer sheet from scratch with headers, candidate fields, ID matrix, question grids, and permitted printing.'}
                            </p>
                        </div>
                        <span
                            class="text-[11px] font-bold text-emerald-700 group-hover:underline"
                            >Start designing →</span
                        >
                    </button>
                </div>
            </div>
        </div>
    {/if}
    <div
        class="studio-workspace bg-muted absolute inset-0 overflow-hidden"
        ondragover={(e) => {
            e.preventDefault();
            e.dataTransfer.dropEffect = 'copy';
        }}
        ondrop={handlePaletteDrop}
    >
        <div
            class={`absolute top-20 ${isSidebarCollapsed ? 'left-[230px]' : 'left-4 lg:left-[398px]'} z-30 flex items-center gap-1.5 rounded-xl border border-slate-200 bg-white/95 p-1.5 shadow-md backdrop-blur`}
            aria-label="Answer sheet pages"
        >
            {#each orderedPages as page}
                {@const isCurrentActive = activePageNo === Number(page.page_no)}
                {@const isPageLoading = isCurrentActive && !sheetImageLoaded && stage1Mode !== 'html'}
                <button
                    type="button"
                    onclick={() => {
                        activePageNo = Number(page.page_no);
                        clearComponentSelection();
                    }}
                    class={`flex items-center gap-1.5 rounded-lg px-3 py-1.5 text-xs font-bold transition ${isCurrentActive ? 'bg-slate-900 text-white' : 'text-slate-600 hover:bg-slate-100'}`}
                    aria-pressed={isCurrentActive}
                    aria-busy={isPageLoading}
                    data-omr-page-btn={page.page_no}
                >
                    {#if isPageLoading}
                        <Loader2 class="size-3 animate-spin text-white/90 shrink-0" aria-hidden="true" />
                    {/if}
                    <span>Page {page.page_no}</span>
                    {#if isPageLoading}
                        <span class="sr-only">(Loading…)</span>
                    {/if}
                </button>
            {/each}
            <span class="mx-0.5 h-5 w-px bg-slate-200"></span>
            <button
                type="button"
                onclick={() => moveActivePage(-1)}
                disabled={isReadOnly || activePageNo === 1 || pageMutationRunning}
                class="rounded-md p-1 text-slate-500 hover:bg-slate-100 disabled:opacity-30"
                title="Move page left"
                aria-label="Move page left"
            >
                <ChevronLeft class="size-3.5" />
            </button>
            <button
                type="button"
                onclick={() => moveActivePage(1)}
                disabled={isReadOnly || activePageNo === orderedPages.length ||
                    pageMutationRunning}
                class="rounded-md p-1 text-slate-500 hover:bg-slate-100 disabled:opacity-30"
                title="Move page right"
                aria-label="Move page right"
            >
                <ChevronRight class="size-3.5" />
            </button>
            <button
                type="button"
                onclick={() => updatePages('add')}
                disabled={isReadOnly || orderedPages.length >= 4 || pageMutationRunning}
                class="rounded-md p-1 text-emerald-700 hover:bg-emerald-50 disabled:opacity-30"
                title="Add page"
                aria-label="Add page"
            >
                <Plus class="size-3.5" />
            </button>

            <details
                bind:this={pageActionsDetails}
                class="relative"
            >
                <summary
                    class="flex size-7 cursor-pointer list-none items-center justify-center rounded-md text-slate-600 transition hover:bg-slate-100 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-slate-500"
                    aria-label="Page actions"
                    title="Page actions"
                >
                    <MoreHorizontal class="size-4" aria-hidden="true" />
                    <span class="sr-only">Page actions</span>
                </summary>
                <div
                    id="omr-page-actions-menu"
                    role="group"
                    aria-label="Page actions"
                    class="absolute right-0 top-full z-50 mt-1 w-60 rounded-xl border border-slate-200 bg-white p-1.5 shadow-xl"
                >
                    {#if stage1Mode !== 'html' && !isReadOnly}
                        <button
                            type="button"
                            onclick={() => {
                                if (pageActionsDetails) pageActionsDetails.open = false;
                                replacePageImageInput?.click();
                            }}
                            class="flex w-full items-center gap-2 rounded-lg px-2.5 py-2 text-left text-xs font-semibold text-slate-700 transition hover:bg-slate-100 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-slate-500"
                        >
                            <Upload class="size-3.5 shrink-0" aria-hidden="true" />
                            <span>Replace page image…</span>
                        </button>
                    {/if}
                    <button
                        type="button"
                        onclick={() => {
                            if (pageActionsDetails) pageActionsDetails.open = false;
                            clearActivePageGuides();
                        }}
                        disabled={isReadOnly}
                        class="flex w-full items-center gap-2 rounded-lg px-2.5 py-2 text-left text-xs font-semibold text-amber-800 transition hover:bg-amber-50 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-amber-600 disabled:cursor-not-allowed disabled:opacity-50"
                    >
                        <Trash2 class="size-3.5 shrink-0" aria-hidden="true" />
                        <span>Clear recognition guides on Page {activePageNo}</span>
                    </button>
                </div>
            </details>
            {#if stage1Mode !== 'html' && !isReadOnly}
                <input
                    bind:this={replacePageImageInput}
                    type="file"
                    accept="image/png,image/jpeg,image/webp,application/pdf"
                    onchange={handleReplacePageImageFile}
                    class="hidden"
                />
            {/if}
            <button
                type="button"
                onclick={removeActivePage}
                disabled={isReadOnly || orderedPages.length <= 1 || pageMutationRunning}
                class="rounded-md p-1 text-rose-600 hover:bg-rose-50 disabled:opacity-30"
                title="Delete active page"
                aria-label="Delete active page"
            >
                <Trash2 class="size-3.5" />
            </button>
        </div>
        <StudioCanvas
            readOnly={isReadOnly}
            bind:scale
            bind:panX
            bind:panY
            bind:marginMm
            {markerInsetsMm}
            bind:markerPattern
            showFiducials={usesFiducials}
            {activePageNo}
            {smartSnapSearchMm}
            {showSmartSnapSearchZone}
            {previewingSearchZone}
            {smartSnapSearchZoneScope}
            bind:documentFields
            bind:activeDocumentFieldId
            {interactionMode}
            bind:sheetImageLoaded
            rasterUrl={activePageRasterUrl ?? (activePageNo === 1 ? draft.raster_url : null)}
            htmlContent={activePageHtml}
            {canonicalPreview}
            mappingPreviewStale={mappingDirty || Boolean(previewError)}
            isHtmlMode={stage1Mode === 'html'}
            isUploadedSource={stage1Mode !== 'html'}
            {previewSurface}
            showCanonicalPrintPreview={currentStage === 1 &&
                designerSubTab === 'print_preview'}
            isComposing={isComposing || previewRunning}
            {showHeaderOverlay}
            {headerTitle}
            {headerSubtitle}
            bind:headerFontSize
            bind:headerFontFamily
            bind:headerColor
            bind:headerAlign
            bind:headerX
            bind:headerY
            bind:headerW
            bind:headerH
            bind:headerLocked
            {showQrOverlay}
            bind:qrX
            bind:qrY
            bind:qrW
            bind:qrH
            bind:qrLocked
            {showInstructionsOverlay}
            {instructionsContent}
            bind:instructionsFontSize
            bind:instructionsFontFamily
            bind:instructionsColor
            bind:instX
            bind:instY
            bind:instW
            bind:instH
            bind:instLocked
            {showSignatureOverlay}
            {signatureContent}
            bind:signatureFontSize
            bind:signatureFontFamily
            bind:signatureColor
            bind:sigX
            bind:sigY
            bind:sigW
            bind:sigH
            bind:sigLocked
            bind:customTextItems
            bind:sections
            bind:activeSectionIndex
            bind:selectedSectionIndices
            bind:components
            bind:plottingConfig
            bind:identity={identityDeclaration}
            onIdentityChanged={(value) => {
                identityDeclaration = value;
                recordBubbleEdit();
            }}
            {currentStage}
            bind:activeComponentId={selectedComponentId}
            bind:activeComponentIds={selectedComponentIds}
            bind:activeItemIds={selectedCanvasIds}
            onComponentSelected={handleCanvasComponentSelected}
            onCanvasItemSelected={handleCanvasItemSelected}
            onCanvasSelectionClear={clearComponentSelection}
            onDuplicateComponents={duplicateSelectedComponents}
            onGeometryChanged={handleGeometryChanged}
            onDocumentFieldsChanged={() => recordBubbleEdit()}
            onComponentGeometryChanged={syncComponentsToSections}

        />
    </div>

    <!-- FLOATING TOP STEPPER BAR (Positioned over the canvas, right of Column 1) -->
    <div
        class={`studio-topbar absolute top-4 ${isSidebarCollapsed ? 'left-[230px]' : 'left-4 lg:left-[398px]'} pointer-events-none right-4 z-30 hidden items-center justify-between gap-3 transition-all duration-200 lg:flex`}
        data-sidebar-collapsed={isSidebarCollapsed}
    >
        <div
            data-studio-stage-navigation
            class="bg-card border-border/80 pointer-events-auto flex shrink-0 items-center space-x-1 rounded-2xl border p-1 shadow-md"
        >
            {#each (stage1Mode === 'html' || !modifyUploadedDocument) ? ['1. ' + (stage1Mode === 'html' ? 'Sheet Designer' : 'Source Ingest'), '2. Recognition Mapping', '3. Verification & Test'] : stageTitles as title, idx}
                {@const isSkippedStep =
                    stage1Mode === 'html' || !modifyUploadedDocument}
                {@const stageNum = isSkippedStep
                    ? idx === 0
                        ? 1
                        : idx + 2
                    : idx + 1}
                {@const displayStep = idx + 1}
                {@const isActive =
                    (isSkippedStep &&
                        ((idx === 0 && currentStage <= 2) ||
                            (idx === 1 && currentStage === 3) ||
                            (idx === 2 && currentStage === 4))) ||
                    (!isSkippedStep && currentStage === stageNum)}
                {@const isPast = isSkippedStep
                    ? (idx === 0 && currentStage >= 3) ||
                      (idx === 1 && currentStage === 4)
                    : currentStage > stageNum}
                <button
                    type="button"
                    onclick={() => requestGoToStage(stageNum)}
                    title={title.split('.')[1]?.trim() || title}
                    class={`flex items-center gap-1.5 rounded-xl px-2 py-1.5 text-xs leading-none font-bold transition xl:px-3 ${
                        isActive
                            ? 'bg-primary text-primary-foreground shadow-xs'
                            : isPast
                              ? 'text-foreground hover:bg-muted'
                              : 'text-muted-foreground hover:bg-muted/70 hover:text-foreground'
                    }`}
                >
                    <span
                        class={`flex size-4 shrink-0 items-center justify-center rounded-full text-[10px] leading-none font-extrabold ${
                            isActive
                                ? 'bg-primary-foreground text-primary'
                                : isPast
                                  ? 'bg-muted text-foreground'
                                  : 'bg-muted text-muted-foreground'
                        }`}
                    >
                        {displayStep}
                    </span>
                    <span class="hidden truncate xl:inline"
                        >{title.split('.')[1]?.trim() || title}</span
                    >
                </button>
            {/each}
        </div>

        <!-- Essential editing controls only -->
        <div
            data-studio-editing-controls
            class="bg-card border-border/80 pointer-events-auto flex shrink-0 items-center gap-1.5 rounded-2xl border p-1.5 shadow-md"
        >
            <div
                data-studio-history
                class="border-border flex items-center gap-0.5 border-r pr-2"
            >
                <button
                    type="button"
                    onclick={undoEditor}
                    disabled={isReadOnly || !historyStatus.canUndo}
                    class="text-muted-foreground hover:bg-muted hover:text-foreground rounded-lg p-1.5 transition disabled:cursor-not-allowed disabled:opacity-30"
                    title={historyStatus.undoLabel
                        ? `Undo: ${historyStatus.undoLabel}`
                        : 'Undo'}
                    aria-label={historyStatus.undoLabel
                        ? `Undo: ${historyStatus.undoLabel}`
                        : 'Undo'}
                >
                    <Undo2 class="size-3.5" />
                </button>
                <button
                    type="button"
                    onclick={redoEditor}
                    disabled={isReadOnly || !historyStatus.canRedo}
                    class="text-muted-foreground hover:bg-muted hover:text-foreground rounded-lg p-1.5 transition disabled:cursor-not-allowed disabled:opacity-30"
                    title={historyStatus.redoLabel
                        ? `Redo: ${historyStatus.redoLabel}`
                        : 'Redo'}
                    aria-label={historyStatus.redoLabel
                        ? `Redo: ${historyStatus.redoLabel}`
                        : 'Redo'}
                >
                    <Redo2 class="size-3.5" />
                </button>
            </div>

            <div
                data-studio-save-state
                class="flex items-center space-x-1.5 px-2 text-xs"
            >
                {#if saveStatus === 'saving'}
                    <Loader2 class="size-3.5 animate-spin text-amber-500" />
                    <span class="font-mono text-[11px] text-amber-600"
                        >Saving…</span
                    >
                {:else if saveStatus === 'saved'}
                    <CheckCircle2 class="size-3.5 text-emerald-600" />
                    <span class="font-mono text-[11px] text-emerald-600"
                        >Saved</span
                    >
                {:else if saveStatus === 'error'}
                    <XCircle class="size-3.5 text-rose-600" />
                    <button
                        type="button"
                        class="font-mono text-[11px] text-rose-600 underline underline-offset-2"
                        title={errorMessage || 'Save blocked. Check studio configuration.'}
                        onclick={() =>
                            toastError(
                                errorMessage ||
                                    'Save blocked. Check studio configuration.',
                                {
                                    duration: 0,
                                    dedupeKey: 'studio-save-error',
                                },
                            )}
                    >
                        Save blocked
                    </button>
                {/if}
                {#if historyNotice}
                    <span class="sr-only" aria-live="polite"
                        >{historyNotice}</span
                    >
                {/if}
            </div>

            {#if isReadOnly}
                <div class="flex items-center gap-1.5 rounded-xl border border-slate-200 bg-slate-100/90 px-3 py-1 font-mono text-xs font-semibold text-slate-700 dark:border-slate-800 dark:bg-slate-900/80 dark:text-slate-300">
                    <Lock class="size-3.5 text-slate-500" />
                    <span>Locked</span>
                </div>
            {:else}
                <button
                    type="button"
                    onclick={() => performSave()}
                    class="bg-primary hover:bg-primary/90 text-primary-foreground h-7 rounded-xl px-3.5 text-xs font-semibold shadow-xs transition"
                >
                    Save
                </button>
            {/if}
            <div class="flex items-center rounded-xl border border-border bg-muted/60 p-0.5">
                <button
                    type="button"
                    onclick={() => { interactionMode = 'pan'; }}
                    class={`flex h-6 items-center gap-1 rounded-lg px-2 text-xs font-semibold transition ${
                        interactionMode === 'pan'
                            ? 'bg-card text-foreground shadow-xs font-bold'
                            : 'text-muted-foreground hover:text-foreground'
                    }`}
                    title="Pan Mode (drag background to pan canvas)"
                    aria-pressed={interactionMode === 'pan'}
                >
                    <Hand class="size-3" />
                    <span>Pan</span>
                </button>
                <button
                    type="button"
                    onclick={() => { interactionMode = 'select'; }}
                    class={`flex h-6 items-center gap-1 rounded-lg px-2 text-xs font-semibold transition ${
                        interactionMode === 'select'
                            ? 'bg-emerald-600 text-white shadow-xs font-bold dark:bg-emerald-500'
                            : 'text-muted-foreground hover:text-foreground'
                    }`}
                    title="Select Mode (drag background to select multiple elements)"
                    aria-pressed={interactionMode === 'select'}
                >
                    <MousePointer class="size-3" />
                    <span>Select</span>
                </button>
            </div>
            {#if exportUrl}
                <a
                    href={exportUrl}
                    onclick={handleStudioExport}
                    download
                    data-testid="studio-export-bundle-btn"
                    class="border-border bg-card hover:bg-muted text-foreground inline-flex h-7 items-center gap-1.5 rounded-lg border px-2.5 text-xs font-semibold shadow-xs transition"
                    title="Export Answer Sheet .zip bundle"
                    aria-label={`Export ${draft.name || draft.code || 'answer sheet'} .zip bundle`}
                >
                    <Download class="size-3.5 shrink-0" aria-hidden="true" />
                    <span>Export</span>
                </a>
            {/if}
            {#if exportUrl || (isReadOnly && (draft.can_unlock_to_edit || draft.can_create_editable_copy || draft.stage === 'compiled' || draft.compiled_version_id != null)) || (draft.can_discard && urls?.destroy)}
                <details class="relative">
                    <summary
                        class="hover:bg-muted text-muted-foreground hover:text-foreground flex size-7 cursor-pointer list-none items-center justify-center rounded-lg border border-transparent transition focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-slate-500"
                        aria-label="More draft actions"
                        title="More draft actions"
                    >
                        <MoreHorizontal class="size-4" aria-hidden="true" />
                        <span class="sr-only">More draft actions</span>
                    </summary>
                    <div
                        role="group"
                        aria-label="Draft actions"
                        class="border-border bg-card absolute right-0 top-full z-50 mt-1.5 w-52 space-y-1 rounded-xl border p-1.5 shadow-xl"
                    >
                        {#if exportUrl}
                            <a
                                href={exportUrl}
                                onclick={handleStudioExport}
                                download
                                class="text-foreground hover:bg-muted flex w-full items-center gap-2 rounded-lg px-2.5 py-2 text-left text-xs font-semibold transition"
                            >
                                <Download class="size-3.5 shrink-0" aria-hidden="true" />
                                <span>Export Bundle (.zip)</span>
                            </a>
                        {/if}
                        {#if isReadOnly && draft.can_unlock_to_edit}
                            <button
                                type="button"
                                data-testid="topbar-unlock-draft-btn"
                                onclick={handleUnlockDraft}
                                disabled={unlockingDraft || creatingEditableCopy}
                                class="flex w-full items-center gap-2 rounded-lg px-2.5 py-2 text-left text-xs font-semibold text-indigo-700 transition hover:bg-indigo-50 disabled:cursor-wait disabled:opacity-60 dark:text-indigo-300 dark:hover:bg-indigo-950/60"
                            >
                                <Unlock class="size-3.5 shrink-0" aria-hidden="true" />
                                <span>{unlockingDraft ? 'Unlocking Draft…' : 'Unlock to Edit'}</span>
                            </button>
                        {/if}
                        {#if isReadOnly && (draft.can_create_editable_copy || draft.stage === 'compiled' || draft.compiled_version_id != null)}
                            <button
                                type="button"
                                onclick={handleCreateEditableCopy}
                                disabled={creatingEditableCopy || unlockingDraft}
                                class="text-foreground hover:bg-muted flex w-full items-center gap-2 rounded-lg px-2.5 py-2 text-left text-xs font-semibold transition disabled:cursor-wait disabled:opacity-60"
                            >
                                <Copy class="size-3.5 shrink-0" aria-hidden="true" />
                                <span>{creatingEditableCopy ? 'Creating Copy…' : 'Create Editable Copy'}</span>
                            </button>
                        {/if}
                        {#if draft.can_discard && urls?.destroy}
                            <button
                                type="button"
                                onclick={() => (discardDraftModalOpen = true)}
                                class="flex w-full items-center gap-2 rounded-lg px-2.5 py-2 text-left text-xs font-semibold text-rose-700 transition hover:bg-rose-50 dark:text-rose-300 dark:hover:bg-rose-950/50"
                                title="Discard answer sheet draft"
                                aria-label={`Discard ${draft.name || draft.code} draft`}
                            >
                                <Trash2 class="size-3.5 shrink-0" aria-hidden="true" />
                                <span>Discard Draft</span>
                            </button>
                        {/if}
                    </div>
                </details>
            {/if}
        </div>
    </div>

    <!-- FLOATING COMPONENT INSPECTOR (Top-Right of Canvas, Stage 1 Sheet Designer) -->
    {#if currentStage === 1 && stage1Mode === 'html' && designerSubTab === 'designer' && selectedComponent}
        <div
            inert={isReadOnly}
            class="studio-inspector bg-card text-foreground [&_input]:border-input [&_input]:bg-background [&_input]:text-foreground [&_select]:border-input [&_select]:bg-background [&_select]:text-foreground pointer-events-auto absolute top-16 right-4 z-40 max-h-[calc(100%-8rem)] w-[320px] space-y-3 overflow-y-auto rounded-2xl border border-emerald-500/30 p-3.5 text-xs shadow-2xl"
        >
            <!-- Header row -->
            <div
                class="border-border flex items-center justify-between border-b pb-2"
            >
                <div class="flex min-w-0 items-center gap-1.5">
                    <span class="size-2 shrink-0 rounded-full bg-emerald-500"
                    ></span>
                    <span class="text-foreground truncate text-xs font-black">
                        Inspector — {componentLabel(selectedComponent)}
                    </span>
                </div>
                <div class="flex shrink-0 items-center gap-1">
                    <button
                        type="button"
                        onclick={() => {
                            selectedComponent.isLocked =
                                !selectedComponent.isLocked;
                            syncComponentsToSections();
                        }}
                        class={`flex items-center gap-1 rounded p-1 text-xs font-semibold transition ${
                            selectedComponent.isLocked
                                ? 'bg-amber-500/10 text-amber-700 dark:text-amber-400'
                                : 'text-muted-foreground hover:text-foreground hover:bg-muted'
                        }`}
                        title={selectedComponent.isLocked
                            ? 'Locked (click to unlock movement)'
                            : 'Unlocked (click to lock)'}
                    >
                        {#if selectedComponent.isLocked}
                            <Lock class="size-3 text-amber-600" />
                        {:else}
                            <Unlock class="size-3 text-slate-400" />
                        {/if}
                    </button>
                    <button
                        type="button"
                        onclick={() => removeComponent(selectedComponent.id)}
                        class="rounded p-1 text-red-500 transition hover:bg-red-50 hover:text-red-700"
                        title="Remove component"
                    >
                        <Trash2 class="size-3" />
                    </button>
                </div>
            </div>

            <!-- Geometry Position & Size Row -->
            <div
                class="bg-muted/50 border-border space-y-1 rounded-xl border p-2"
            >
                <span
                    class="text-muted-foreground block text-[9.5px] font-bold tracking-wide uppercase"
                    >Geometry (mm)</span
                >
                <div class="grid grid-cols-4 gap-1.5">
                    <div>
                        <label class="text-muted-foreground block text-[9px]"
                            >X</label
                        >
                        <input
                            type="number"
                            bind:value={selectedComponent.posX}
                            oninput={syncComponentsToSections}
                            class="bg-background border-input w-full rounded border px-1.5 py-0.5 font-mono text-[11px]"
                        />
                    </div>
                    <div>
                        <label class="text-muted-foreground block text-[9px]"
                            >Y</label
                        >
                        <input
                            type="number"
                            bind:value={selectedComponent.posY}
                            oninput={syncComponentsToSections}
                            class="bg-background border-input w-full rounded border px-1.5 py-0.5 font-mono text-[11px]"
                        />
                    </div>
                    <div>
                        <label class="block text-[9px] text-slate-500">W</label>
                        {#if selectedComponent.type === 'roll' || selectedComponent.type === 'mcq' || selectedComponent.type === 'tf' || selectedComponent.type === 'likert' || selectedComponent.type === 'custom'}
                            <span
                                class="block w-full rounded border border-slate-200 bg-slate-100 px-1.5 py-0.5 font-mono text-[11px] text-slate-500"
                            >
                                {componentBlockSizeMm(selectedComponent).w_mm}
                            </span>
                        {:else}
                            <input
                                type="number"
                                bind:value={selectedComponent.w_mm}
                                oninput={syncComponentsToSections}
                                class="w-full rounded border border-slate-200 bg-white px-1.5 py-0.5 font-mono text-[11px]"
                            />
                        {/if}
                    </div>
                    <div>
                        <label class="block text-[9px] text-slate-500">H</label>
                        {#if selectedComponent.type === 'roll' || selectedComponent.type === 'mcq' || selectedComponent.type === 'tf' || selectedComponent.type === 'likert' || selectedComponent.type === 'custom'}
                            <span
                                class="block w-full rounded border border-slate-200 bg-slate-100 px-1.5 py-0.5 font-mono text-[11px] text-slate-500"
                            >
                                {componentBlockSizeMm(selectedComponent).h_mm}
                            </span>
                        {:else}
                            <input
                                type="number"
                                bind:value={selectedComponent.h_mm}
                                oninput={syncComponentsToSections}
                                class="w-full rounded border border-slate-200 bg-white px-1.5 py-0.5 font-mono text-[11px]"
                            />
                        {/if}
                    </div>
                </div>
            </div>

            <StudioComponentAppearanceControls
                backgroundColor={selectedComponent.backgroundColor ??
                    'transparent'}
                onChanged={(backgroundColor) =>
                    updateSelectedComponent({ backgroundColor })}
            />

            <!-- Type-specific fields -->
            {#if selectedComponent.type === 'header'}
                <div class="space-y-2">
                    <div>
                        <label
                            class="mb-0.5 block text-[10px] font-semibold text-slate-600"
                            >Title</label
                        >
                        <input
                            type="text"
                            bind:value={selectedComponent.title}
                            oninput={syncComponentsToSections}
                            placeholder="Institution / Exam Title"
                            class="w-full rounded-lg border border-slate-200 bg-white px-2 py-1 text-xs font-semibold"
                        />
                    </div>
                    <div class="grid grid-cols-2 gap-2">
                        <div>
                            <label
                                class="mb-0.5 block text-[10px] font-semibold text-slate-600"
                                >Subtitle</label
                            >
                            <input
                                type="text"
                                bind:value={selectedComponent.subtitle}
                                oninput={syncComponentsToSections}
                                placeholder="Subtitle"
                                class="w-full rounded-lg border border-slate-200 bg-white px-2 py-1 text-[11px]"
                            />
                        </div>
                        <div>
                            <label
                                class="mb-0.5 block text-[10px] font-semibold text-slate-600"
                                >Form Code</label
                            >
                            <input
                                type="text"
                                bind:value={selectedComponent.formCode}
                                oninput={syncComponentsToSections}
                                placeholder="Form Code"
                                class="w-full rounded-lg border border-slate-200 bg-white px-2 py-1 font-mono text-[11px]"
                            />
                        </div>
                    </div>
                    <StudioTypographyControls
                        bind:typography={selectedComponent.typography}
                        onChanged={syncComponentsToSections}
                    />
                </div>
            {:else if selectedComponent.type === 'candidate_name' || selectedComponent.type === 'birthdate' || selectedComponent.type === 'room_seat'}
                <div class="space-y-2">
                    <div>
                        <label
                            class="mb-0.5 block text-[10px] font-semibold text-slate-600"
                            >Field Label</label
                        >
                        <input
                            type="text"
                            bind:value={selectedComponent.label}
                            oninput={syncComponentsToSections}
                            placeholder="Label text"
                            class="w-full rounded-lg border border-slate-200 bg-white px-2 py-1 text-[11px]"
                        />
                    </div>
                    <div class="grid grid-cols-2 gap-2">
                        <div>
                            <label
                                class="mb-0.5 block text-[10px] font-semibold text-slate-600"
                                >Input Type</label
                            >
                            <select
                                value={selectedComponent.inputKind ??
                                    (selectedComponent.type === 'birthdate'
                                        ? 'date'
                                        : 'text')}
                                onchange={(e) =>
                                    updateSelectedComponent({
                                        inputKind: e.currentTarget.value,
                                    })}
                                class="w-full rounded-lg border border-slate-200 bg-white px-2 py-1 text-[11px]"
                            >
                                <option value="text">Text</option>
                                <option value="number">Number</option>
                                <option value="date">Date</option>
                                <option value="multiline">Multiline</option>
                            </select>
                        </div>
                        <div>
                            <label
                                class="mb-0.5 block text-[10px] font-semibold text-slate-600"
                                >Input Shape</label
                            >
                            <select
                                value={selectedComponent.inputStyle ?? 'box'}
                                onchange={(e) =>
                                    updateSelectedComponent({
                                        inputStyle: e.currentTarget.value,
                                    })}
                                class="w-full rounded-lg border border-slate-200 bg-white px-2 py-1 text-[11px]"
                            >
                                <option value="box">Box</option>
                                <option value="dotted_line">Dotted line</option>
                                <option value="underline">Underline</option>
                            </select>
                        </div>
                    </div>
                    <div class="flex items-center justify-between gap-2">
                        <label class="text-[10px] font-semibold text-slate-600"
                            >Input Fill</label
                        >
                        <input
                            type="color"
                            value={selectedComponent.inputBackgroundColor ??
                                '#ffffff'}
                            oninput={(e) =>
                                updateSelectedComponent({
                                    inputBackgroundColor: e.currentTarget.value,
                                })}
                            class="h-7 w-16 cursor-pointer rounded border border-slate-200 bg-white p-0.5"
                            aria-label="Input fill color"
                        />
                    </div>
                    <StudioTypographyControls
                        bind:typography={selectedComponent.typography}
                        onChanged={syncComponentsToSections}
                    />
                </div>
            {:else if selectedComponent.type === 'custom_input'}
                <div class="space-y-2">
                    <div>
                        <label
                            class="mb-0.5 block text-[10px] font-semibold text-slate-600"
                            >Input Label</label
                        >
                        <input
                            type="text"
                            bind:value={selectedComponent.label}
                            oninput={syncComponentsToSections}
                            placeholder="Custom response label"
                            class="w-full rounded-lg border border-slate-200 bg-white px-2 py-1 text-[11px]"
                        />
                    </div>
                    <div class="grid grid-cols-2 gap-2">
                        <div>
                            <label
                                class="mb-0.5 block text-[10px] font-semibold text-slate-600"
                                >Input Type</label
                            >
                            <select
                                bind:value={selectedComponent.inputKind}
                                onchange={syncComponentsToSections}
                                class="w-full rounded-lg border border-slate-200 bg-white px-2 py-1 text-[11px]"
                            >
                                <option value="text">Text</option>
                                <option value="number">Number</option>
                                <option value="date">Date</option>
                                <option value="multiline">Multiline</option>
                            </select>
                        </div>
                        <div>
                            <label
                                class="mb-0.5 block text-[10px] font-semibold text-slate-600"
                                >Input Shape</label
                            >
                            <select
                                bind:value={selectedComponent.inputStyle}
                                onchange={syncComponentsToSections}
                                class="w-full rounded-lg border border-slate-200 bg-white px-2 py-1 text-[11px]"
                            >
                                <option value="box">Box</option>
                                <option value="dotted_line">Dotted line</option>
                                <option value="underline">Underline</option>
                            </select>
                        </div>
                    </div>
                    <div class="flex items-center justify-between gap-2">
                        <label class="text-[10px] font-semibold text-slate-600"
                            >Input Fill</label
                        >
                        <input
                            type="color"
                            bind:value={selectedComponent.inputBackgroundColor}
                            oninput={syncComponentsToSections}
                            class="h-7 w-16 cursor-pointer rounded border border-slate-200 bg-white p-0.5"
                            aria-label="Input fill color"
                        />
                    </div>
                    <StudioTypographyControls
                        bind:typography={selectedComponent.typography}
                        onChanged={syncComponentsToSections}
                    />
                </div>
            {:else if selectedComponent.type === 'instructions'}
                <div class="space-y-2">
                    <div>
                        <label
                            class="mb-0.5 block text-[10px] font-semibold text-slate-600"
                            >Notice Label</label
                        >
                        <input
                            type="text"
                            bind:value={selectedComponent.label}
                            oninput={syncComponentsToSections}
                            placeholder="Instructions"
                            class="w-full rounded-lg border border-slate-200 bg-white px-2 py-1 text-[11px] font-semibold"
                        />
                    </div>
                    <div>
                        <label
                            class="mb-0.5 block text-[10px] font-semibold text-slate-600"
                            >Notice Text</label
                        >
                        <textarea
                            bind:value={selectedComponent.content}
                            oninput={syncComponentsToSections}
                            rows="3"
                            class="w-full rounded-lg border border-slate-200 bg-white px-2 py-1 text-[11px] leading-relaxed"
                            placeholder="Instructions text…"></textarea>
                    </div>
                    <div class="grid grid-cols-2 gap-2">
                        <div>
                            <label
                                class="mb-0.5 block text-[10px] font-semibold text-slate-600"
                                >Border Color</label
                            >
                            <input
                                type="color"
                                bind:value={selectedComponent.borderColor}
                                oninput={syncComponentsToSections}
                                class="h-7 w-full cursor-pointer rounded border border-slate-200 bg-white p-0.5"
                            />
                        </div>
                        <div>
                            <label
                                class="mb-0.5 block text-[10px] font-semibold text-slate-600"
                                >Background</label
                            >
                            <input
                                type="color"
                                bind:value={selectedComponent.backgroundColor}
                                oninput={syncComponentsToSections}
                                class="h-7 w-full cursor-pointer rounded border border-slate-200 bg-white p-0.5"
                            />
                        </div>
                    </div>
                    <StudioTypographyControls
                        bind:typography={selectedComponent.typography}
                        onChanged={syncComponentsToSections}
                    />
                </div>
            {:else if selectedComponent.type === 'roll'}
                <div class="space-y-2">
                    <div class="grid grid-cols-2 gap-2">
                        <div>
                            <label
                                class="mb-0.5 block text-[10px] font-semibold text-slate-600"
                                >Digit Columns</label
                            >
                            <input
                                type="number"
                                min="2"
                                max="10"
                                bind:value={selectedComponent.digitsCount}
                                oninput={syncComponentsToSections}
                                class="w-full rounded-lg border border-slate-200 bg-white px-2 py-1 text-xs font-semibold"
                            />
                        </div>
                        <div>
                            <label
                                class="mb-0.5 block text-[10px] font-semibold text-slate-600"
                                >Matrix Title</label
                            >
                            <input
                                type="text"
                                bind:value={selectedComponent.title}
                                oninput={syncComponentsToSections}
                                class="w-full rounded-lg border border-slate-200 bg-white px-2 py-1 text-xs"
                            />
                        </div>
                    </div>
                    <div class="grid grid-cols-3 gap-2 pt-1">
                        <div>
                            <label
                                class="mb-0.5 block text-[10px] font-semibold text-slate-600"
                                >Bubble Size (mm)</label
                            >
                            <input
                                type="number"
                                min="4.2"
                                max="8"
                                step="0.1"
                                bind:value={selectedComponent.bubbleDiameterMm}
                                oninput={syncComponentsToSections}
                                class="w-full rounded-lg border border-slate-200 bg-white px-2 py-1 text-xs"
                            />
                        </div>
                        <div>
                            <label
                                class="mb-0.5 block text-[10px] font-semibold text-slate-600"
                                >Bubble Pitch (mm)</label
                            >
                            <input
                                type="number"
                                min={minimumCanvasPitchMm(
                                    selectedComponent.bubbleDiameterMm,
                                )}
                                max="15"
                                step="0.1"
                                bind:value={selectedComponent.bubblePitchMm}
                                oninput={syncComponentsToSections}
                                class="w-full rounded-lg border border-slate-200 bg-white px-2 py-1 text-xs"
                            />
                        </div>
                        <div>
                            <label
                                class="mb-0.5 block text-[10px] font-semibold text-slate-600"
                                >Row Pitch (mm)</label
                            >
                            <input
                                type="number"
                                min={minimumCanvasPitchMm(
                                    selectedComponent.bubbleDiameterMm,
                                )}
                                max="15"
                                step="0.1"
                                bind:value={selectedComponent.rowPitchMm}
                                oninput={syncComponentsToSections}
                                class="w-full rounded-lg border border-slate-200 bg-white px-2 py-1 text-xs"
                            />
                        </div>
                    </div>
                </div>

                <StudioTypographyControls
                    bind:typography={selectedComponent.typography}
                    onChanged={syncComponentsToSections}
                />
            {:else if selectedComponent.type === 'mcq' || selectedComponent.type === 'tf' || selectedComponent.type === 'likert' || selectedComponent.type === 'custom'}
                <div class="space-y-2">
                    <div>
                        <label
                            class="mb-0.5 block text-[10px] font-semibold text-slate-600"
                            >Subject Section Name</label
                        >
                        <input
                            type="text"
                            bind:value={selectedComponent.subjectName}
                            oninput={syncComponentsToSections}
                            placeholder="Subject name"
                            class="w-full rounded-lg border border-slate-200 bg-white px-2 py-1 text-xs font-semibold"
                        />
                    </div>
                    <div class="grid grid-cols-2 gap-2">
                        <div>
                            <label
                                class="mb-0.5 block text-[10px] font-semibold text-slate-600"
                                >Bubble Size (mm)</label
                            >
                            <input
                                type="number"
                                min="4.2"
                                max="8"
                                step="0.1"
                                bind:value={selectedComponent.bubbleDiameterMm}
                                oninput={syncComponentsToSections}
                                class="w-full rounded-lg border border-slate-200 bg-white px-2 py-1 text-xs"
                            />
                        </div>
                        <div>
                            <label
                                class="mb-0.5 block text-[10px] font-semibold text-slate-600"
                                >Question Label Size (pt)</label
                            >
                            <input
                                type="number"
                                min="5"
                                max="12"
                                step="0.1"
                                bind:value={selectedComponent.labelFontSizePt}
                                oninput={syncComponentsToSections}
                                class="w-full rounded-lg border border-slate-200 bg-white px-2 py-1 text-xs"
                            />
                        </div>
                    </div>
                    <div class="grid grid-cols-2 gap-2">
                        <div>
                            <label
                                class="mb-0.5 block text-[10px] font-semibold text-slate-600"
                                >Questions Count</label
                            >
                            <input
                                type="number"
                                min="1"
                                max="50"
                                bind:value={selectedComponent.questionsCount}
                                oninput={syncComponentsToSections}
                                class="w-full rounded-lg border border-slate-200 bg-white px-2 py-1 text-xs"
                            />
                        </div>
                        <div>
                            <label
                                class="mb-0.5 block text-[10px] font-semibold text-slate-600"
                                >Bubble Pitch (mm)</label
                            >
                            <input
                                type="number"
                                min={minimumCanvasPitchMm(
                                    selectedComponent.bubbleDiameterMm,
                                )}
                                max="25"
                                step="0.1"
                                bind:value={selectedComponent.bubblePitchMm}
                                oninput={syncComponentsToSections}
                                class="w-full rounded-lg border border-slate-200 bg-white px-2 py-1 text-xs"
                            />
                        </div>
                        <div>
                            <label
                                class="mb-0.5 block text-[10px] font-semibold text-slate-600"
                                >Options Count</label
                            >
                            <input
                                type="number"
                                min="2"
                                max="5"
                                bind:value={selectedComponent.optionsCount}
                                oninput={syncComponentsToSections}
                                class="w-full rounded-lg border border-slate-200 bg-white px-2 py-1 text-xs"
                            />
                        </div>
                        <div>
                            <label
                                class="mb-0.5 block text-[10px] font-semibold text-slate-600"
                                >Start Q#</label
                            >
                            <input
                                type="number"
                                min="1"
                                bind:value={selectedComponent.startQ}
                                oninput={syncComponentsToSections}
                                class="w-full rounded-lg border border-slate-200 bg-white px-2 py-1 text-xs"
                            />
                        </div>
                        <div>
                            <label
                                class="mb-0.5 block text-[10px] font-semibold text-slate-600"
                                >Row Pitch (mm)</label
                            >
                            <input
                                type="number"
                                min={minimumCanvasPitchMm(
                                    selectedComponent.bubbleDiameterMm,
                                )}
                                max="25"
                                bind:value={selectedComponent.rowPitchMm}
                                oninput={syncComponentsToSections}
                                class="w-full rounded-lg border border-slate-200 bg-white px-2 py-1 text-xs"
                            />
                        </div>
                    </div>
                    {#if aptitude_areas && aptitude_areas.length > 0}
                        <div>
                            <label
                                class="mb-0.5 block text-[10px] font-semibold text-slate-600"
                                >Bind System Aptitude Area</label
                            >
                            <select
                                value={selectedComponent.aptitudeAreaCode ?? ''}
                                class="w-full rounded-lg border border-slate-200 bg-slate-50 px-2 py-1 text-[10.5px] text-slate-700"
                                onchange={(e) => {
                                    const area = aptitude_areas.find(
                                        (a) => a.code === e.currentTarget.value,
                                    );
                                    if (area) {
                                        updateSelectedComponent({
                                            aptitudeAreaCode: area.code,
                                            subjectName: area.name,
                                            questionsCount: 25,
                                        });
                                    }
                                }}
                            >
                                <option value=""
                                    >-- Choose Aptitude Area --</option
                                >
                                {#each aptitude_areas as area}
                                    <option value={area.code}
                                        >{area.name} (25 Items)</option
                                    >
                                {/each}
                            </select>
                        </div>
                    {/if}
                </div>
            {:else if selectedComponent.type === 'custom_text'}
                <div class="space-y-2">
                    <div>
                        <label
                            class="mb-0.5 block text-[10px] font-semibold text-slate-600"
                            >Text Content</label
                        >
                        <input
                            type="text"
                            bind:value={selectedComponent.text}
                            oninput={syncComponentsToSections}
                            placeholder="Custom text…"
                            class="w-full rounded-lg border border-slate-200 bg-white px-2 py-1 text-[11px]"
                        />
                    </div>
                    <StudioTypographyControls
                        bind:typography={selectedComponent.typography}
                        onChanged={syncComponentsToSections}
                    />
                </div>
            {:else if selectedComponent.type === 'signature'}
                <div class="space-y-2">
                    <div>
                        <label
                            class="mb-0.5 block text-[10px] font-semibold text-slate-600"
                            >Title Label</label
                        >
                        <input
                            type="text"
                            bind:value={selectedComponent.title}
                            oninput={syncComponentsToSections}
                            placeholder="Signature label"
                            class="w-full rounded-lg border border-slate-200 bg-white px-2 py-1 text-[11px]"
                        />
                    </div>
                    <div>
                        <label
                            class="mb-0.5 block text-[10px] font-semibold text-slate-600"
                            >Certification Text</label
                        >
                        <input
                            type="text"
                            bind:value={selectedComponent.certificationText}
                            oninput={syncComponentsToSections}
                            placeholder="Certification text"
                            class="w-full rounded-lg border border-slate-200 bg-white px-2 py-1 text-[11px]"
                        />
                    </div>
                    <StudioTypographyControls
                        bind:typography={selectedComponent.typography}
                        onChanged={syncComponentsToSections}
                    />
                </div>
            {/if}
        </div>
    {/if}

    <!-- FLOATING CANVAS CONTROLS (Bottom-left beside Column 1) -->
    <div
        class={`studio-canvas-controls absolute bottom-4 ${isSidebarCollapsed ? 'left-4' : 'left-4 sm:left-[398px]'} border-border bg-card pointer-events-auto z-30 hidden items-center gap-2 rounded-2xl border p-1.5 shadow-xl transition-all duration-200 sm:flex`}
        data-sidebar-collapsed={isSidebarCollapsed}
    >

        <div data-studio-canvas-divider class="bg-border h-4 w-px"></div>

        <!-- Zoom In & Out Buttons -->
        <div
            data-studio-canvas-zoom
            class="bg-muted border-border flex items-center gap-1 rounded-xl border p-0.5"
        >
            <button
                type="button"
                onclick={() => zoomStep(-0.15)}
                title="Zoom Out"
                class="text-muted-foreground hover:text-foreground hover:bg-card flex h-7 w-7 items-center justify-center rounded-lg transition"
            >
                <Minus class="size-3.5" />
            </button>
            <span
                class="text-foreground min-w-[42px] px-1.5 text-center font-mono text-xs font-bold"
            >
                {Math.round(scale * 100)}%
            </span>
            <button
                type="button"
                onclick={() => zoomStep(0.15)}
                title="Zoom In"
                class="text-muted-foreground hover:text-foreground hover:bg-card flex h-7 w-7 items-center justify-center rounded-lg transition"
            >
                <Plus class="size-3.5" />
            </button>
        </div>

        <!-- Fit Button -->
        <button
            type="button"
            onclick={resetZoom}
            title="Reset zoom and center the sheet"
            data-studio-canvas-fit
            class="text-foreground hover:bg-muted border-border flex h-8 items-center gap-1 rounded-xl border px-2.5 text-xs font-bold transition"
        >
            <Maximize2 class="size-3" />
            <span>Fit</span>
        </button>

        {#if !isReadOnly &&
            ((currentStage === 1 &&
                stage1Mode === 'html' &&
                designerSubTab === 'designer' &&
                selectedCanvasIds.length >= 2) ||
                (currentStage === 3 && selectedSectionIndices.length >= 2))}
            <div data-studio-canvas-divider class="bg-border h-4 w-px"></div>
            <div class="flex items-center gap-0.5 rounded-xl bg-muted/60 p-0.5">
                <span class="px-2 font-mono text-[11px] font-bold text-emerald-600 dark:text-emerald-400">
                    {currentStage === 1 ? selectedCanvasIds.length : selectedSectionIndices.length} selected
                </span>
                <button type="button" onclick={() => batchAlign('left')} class="hover:bg-card flex size-7 items-center justify-center rounded-lg transition" title="Align Left"><AlignLeft class="size-3.5" /></button>
                <button type="button" onclick={() => batchAlign('right')} class="hover:bg-card flex size-7 items-center justify-center rounded-lg transition" title="Align Right"><AlignRight class="size-3.5" /></button>
                <button type="button" onclick={() => batchAlign('top')} class="hover:bg-card flex size-7 items-center justify-center rounded-lg transition" title="Align Top"><AlignVerticalSpaceAround class="size-3.5 rotate-90" /></button>
                <button type="button" onclick={() => batchAlign('bottom')} class="hover:bg-card flex size-7 items-center justify-center rounded-lg transition" title="Align Bottom"><ArrowDown class="size-3.5" /></button>
                {#if (currentStage === 1 ? selectedComponentIds.length : selectedSectionIndices.length) >= 3}
                    <button type="button" onclick={() => batchDistribute('horizontal')} class="hover:bg-card flex size-7 items-center justify-center rounded-lg transition" title="Distribute Horizontally"><ArrowRight class="size-3.5" /></button>
                    <button type="button" onclick={() => batchDistribute('vertical')} class="hover:bg-card flex size-7 items-center justify-center rounded-lg transition" title="Distribute Vertically"><ArrowDown class="size-3.5" /></button>
                {/if}
                <button type="button" onclick={() => batchToggleLock()} class="hover:bg-card flex size-7 items-center justify-center rounded-lg transition" title="Lock All / Unlock All"><Lock class="size-3.5" /></button>
                <button type="button" onclick={batchDeleteSelected} class="hover:bg-destructive/10 text-rose-600 flex size-7 items-center justify-center rounded-lg transition" title="Delete Selected"><Trash2 class="size-3.5" /></button>
            </div>
        {/if}
    </div>

    {#if isSidebarCollapsed}
        <div class="pointer-events-auto absolute top-4 left-4 z-30 flex w-[210px] min-w-0 flex-col gap-2">
            <button
                type="button"
                onclick={() => {
                    isSidebarCollapsed = false;
                }}
                class="studio-sidebar-toggle border-border bg-card/95 text-foreground hover:bg-muted focus-visible:ring-ring flex items-center gap-2 rounded-2xl border px-3 py-2 text-xs font-bold shadow-xl backdrop-blur-xs transition focus-visible:ring-2 focus-visible:outline-none"
                title={`Open tool panel for ${draft.name || 'Answer sheet'}`}
                aria-label={`Open tool panel for ${draft.name || 'Answer sheet'}`}
            >
                <div
                    class="flex size-5 items-center justify-center rounded bg-emerald-600 text-[10px] font-bold text-white"
                >
                    N
                </div>
                <span
                    class="max-w-[140px] truncate sm:max-w-[180px]"
                    title={draft.name || 'Answer sheet'}
                    >{draft.name || 'Answer sheet'} ({totalQuestionsCount} questions)</span
                >
                <ChevronRight class="text-muted-foreground ml-0.5 size-3.5" />
            </button>
            {#if draft.read_only_reason}
                <div class="flex min-w-0 items-start gap-1.5 rounded-xl border border-amber-300/80 bg-amber-50/95 p-2 text-left text-[11px] font-medium leading-tight text-amber-950 shadow-md backdrop-blur-xs dark:border-amber-800/80 dark:bg-amber-950/70 dark:text-amber-200">
                    <Lock class="mt-0.5 size-3 shrink-0 text-amber-700 dark:text-amber-400" aria-hidden="true" />
                    <span class="min-w-0 whitespace-normal break-words">{draft.read_only_reason}</span>
                </div>
            {/if}
        </div>
    {/if}

    <!-- COLUMN 1: FLOATING LEFT TOOL CARD (Positioned strictly inside top-4 to bottom-4) -->
    <aside
        class={`studio-sidebar bg-card border-border text-foreground absolute top-4 bottom-4 left-4 z-20 flex max-h-[calc(100%-2rem)] w-[360px] max-w-[calc(100vw-2rem)] min-w-0 flex-col overflow-hidden rounded-2xl border shadow-2xl transition-all duration-200 sm:w-[370px] ${
            isSidebarCollapsed
                ? 'pointer-events-none -translate-x-4 opacity-0'
                : 'pointer-events-auto translate-x-0 opacity-100'
        } [&_input]:border-input [&_input]:bg-background [&_input]:text-foreground [&_select]:border-input [&_select]:bg-background [&_select]:text-foreground dark:[&_.bg-white]:bg-card dark:[&_.bg-slate-50]:bg-muted/50 dark:[&_.bg-slate-100]:bg-muted dark:[&_.text-slate-900]:text-foreground dark:[&_.text-slate-800]:text-foreground dark:[&_.text-slate-700]:text-foreground dark:[&_.text-slate-600]:text-muted-foreground dark:[&_.text-slate-500]:text-muted-foreground dark:[&_.border-slate-200]:border-border dark:[&_.border-slate-100]:border-border`}
        data-studio-stage={currentStage}
        data-sidebar-collapsed={isSidebarCollapsed}
    >
        <!-- Card Header -->
        <div
            class="border-border bg-card flex h-11 shrink-0 items-center justify-between border-b px-3.5"
        >
            <div class="flex min-w-0 items-center space-x-2">
                <div
                    class="flex size-5 shrink-0 items-center justify-center rounded bg-emerald-600 text-[10px] font-bold text-white"
                >
                    N
                </div>
                <span
                    class="text-foreground max-w-[170px] truncate text-xs font-bold"
                    title={draft.name || 'Answer sheet'}
                >
                    {draft.name || 'Answer sheet'}
                </span>
            </div>
            <div class="flex shrink-0 items-center gap-1.5">
                <div class="text-muted-foreground font-mono text-[10.5px]">
                    {totalQuestionsCount} Qs
                </div>
                <button
                    type="button"
                    onclick={() => {
                        isSidebarCollapsed = true;
                    }}
                    class="text-muted-foreground hover:text-foreground hover:bg-muted flex size-6 items-center justify-center rounded-lg transition"
                    title="Collapse tool card"
                    aria-label="Collapse tool card"
                >
                    <ChevronLeft class="size-4" />
                </button>
            </div>
        </div>
        {#if draft.read_only_reason}
            <div class="shrink-0 border-b border-border/60 bg-amber-500/5 px-3.5 py-2">
                <div class="flex min-w-0 items-start gap-2 rounded-lg border border-amber-300/80 bg-amber-50/90 px-2.5 py-1.5 text-left text-xs font-medium leading-4 text-amber-950 dark:border-amber-800/80 dark:bg-amber-950/40 dark:text-amber-200">
                    <Lock class="mt-0.5 size-3.5 shrink-0 text-amber-700 dark:text-amber-400" aria-hidden="true" />
                    <span class="min-w-0 whitespace-normal break-words">{draft.read_only_reason}</span>
                </div>
            </div>
        {/if}

        <!-- Scrollable Tool Content Body (Dedicated internal scroll) -->
        <div
            class="min-h-0 min-w-0 flex-1 space-y-4 overflow-x-hidden overflow-y-auto p-4"
        >
            {#key sidebarStage}
                {#if sidebarStage === 1}
                    <!-- STEP 1: SOURCE SUBSTRATE & INGESTION -->
                    <div class="space-y-4" inert={isReadOnly}>
                        <!-- Creation Mode Indicator (deliberate change only, not an easy switch) -->
                        <div
                            class="flex items-center justify-between rounded-xl border border-slate-200 bg-slate-50 p-2.5"
                        >
                            <div class="flex items-center gap-2">
                                <span
                                    class={`rounded-lg px-2 py-1 text-[10.5px] font-bold ${stage1Mode === 'html' ? 'bg-emerald-100 text-emerald-800' : 'bg-blue-100 text-blue-800'}`}
                                >
                                    {stage1Mode === 'html'
                                        ? 'Sheet Designer'
                                        : 'Substrate Ingest'}
                                </span>
                            </div>
                            <button
                                type="button"
                                onclick={() =>
                                    requestSwitchMode(
                                        stage1Mode === 'html'
                                            ? 'upload'
                                            : 'html',
                                    )}
                                class="text-[10.5px] font-semibold text-slate-600 underline underline-offset-2 hover:text-slate-900"
                            >
                                Change mode
                            </button>
                        </div>

                        {#if stage1Mode === 'html'}
                            <div
                                class="grid grid-cols-3 items-center rounded-lg border border-slate-200 bg-slate-100 p-0.5"
                            >
                                <button
                                    type="button"
                                    onclick={() => {
                                        designerSubTab = 'designer';
                                    }}
                                    class={`rounded-md py-1 text-[10.5px] font-semibold transition ${designerSubTab === 'designer' ? 'bg-white text-slate-900 shadow-xs' : 'text-slate-500 hover:text-slate-800'}`}
                                    >Visual Designer</button
                                >
                                <button
                                    type="button"
                                    onclick={() => {
                                        designerSubTab = 'raw_html';
                                    }}
                                    class={`rounded-md py-1 text-[10.5px] font-semibold transition ${designerSubTab === 'raw_html' ? 'bg-white text-slate-900 shadow-xs' : 'text-slate-500 hover:text-slate-800'}`}
                                    >Compiled HTML</button
                                >
                                <button
                                    type="button"
                                    onclick={openPrintPreview}
                                    class={`rounded-md py-1 text-[10.5px] font-semibold transition ${designerSubTab === 'print_preview' ? 'bg-white text-slate-900 shadow-xs' : 'text-slate-500 hover:text-slate-800'}`}
                                    >Print Preview</button
                                >
                            </div>

                            {#if designerSubTab === 'designer'}
                                <div class="space-y-3">
                                    <!-- 1. Draggable Component Palette -->
                                    <div>
                                        <div
                                            class="mb-1.5 flex items-center justify-between"
                                        >
                                            <span
                                                class="text-xs font-bold text-slate-900"
                                                >Component Palette</span
                                            >
                                            <span
                                                class="text-[9.5px] text-slate-400"
                                                >Drag onto canvas or click to
                                                add</span
                                            >
                                        </div>
                                        <div class="grid grid-cols-2 gap-1.5">
                                            {#each paletteForPath(componentPalettePath) as item}
                                                <button
                                                    type="button"
                                                    draggable={componentPaletteFilter.includeQuestionComponents ||
                                                        (item.kind !== 'roll' &&
                                                            item.kind !==
                                                                'question_grid')}
                                                    ondragstart={(e) => {
                                                        window.__omrDragKind =
                                                            item.kind;
                                                        try {
                                                            e.dataTransfer?.setData(
                                                                'application/x-omr-component',
                                                                item.kind,
                                                            );
                                                            e.dataTransfer.effectAllowed =
                                                                'copy';
                                                        } catch {}
                                                    }}
                                                    onclick={() =>
                                                        handlePaletteItem(item)}
                                                    class="cursor-grab rounded-lg border border-slate-200 bg-white p-2 text-left transition hover:border-emerald-400 hover:bg-emerald-50/40 active:cursor-grabbing"
                                                    title={item.description}
                                                >
                                                    <span
                                                        class="block text-[10.5px] leading-tight font-bold text-slate-800"
                                                        >{item.label}</span
                                                    >
                                                    <span
                                                        class="mt-0.5 block text-[9px] leading-snug text-slate-400"
                                                        >{item.description}</span
                                                    >
                                                </button>
                                            {/each}
                                        </div>
                                    </div>

                                    <!-- Shared overlay palette: filtered by authoring path -->
                                    <div
                                        class="border-t border-slate-200/80 pt-3"
                                    >
                                        <div
                                            class="mb-1.5 flex items-center justify-between"
                                        >
                                            <span
                                                class="text-xs font-bold text-slate-900"
                                                >Shared Overlay Palette</span
                                            >
                                            <span
                                                class="text-[9.5px] text-slate-400"
                                                >Path: {componentPalettePath ===
                                                'sheet_designer'
                                                    ? 'Sheet'
                                                    : 'Upload'}</span
                                            >
                                        </div>
                                        <div class="grid grid-cols-3 gap-1.5">
                                            {#each availableOverlayPalette(componentPalettePath) as item}
                                                <button
                                                    type="button"
                                                    onclick={() =>
                                                        handleOverlayPaletteItem(
                                                            item.kind,
                                                        )}
                                                    class="rounded-lg border border-slate-200 bg-white p-2 text-left transition hover:border-blue-400 hover:bg-blue-50/40"
                                                    title={item.description}
                                                >
                                                    <span
                                                        class="block text-[10px] leading-tight font-bold text-slate-800"
                                                        >{item.label}</span
                                                    >
                                                    <span
                                                        class="mt-0.5 block text-[8.5px] leading-snug text-slate-400"
                                                        >{item.description}</span
                                                    >
                                                </button>
                                            {/each}
                                        </div>
                                    </div>

                                    <!-- Shared layout controls: margins, markers, and QR -->
                                    <div
                                        class="space-y-3 border-t border-slate-200/80 pt-3"
                                    >
                                        <div
                                            class="flex items-center justify-between"
                                        >
                                            <span
                                                class="text-xs font-bold tracking-wider text-slate-800 uppercase"
                                                >Sheet Layout</span
                                            >
                                            <span
                                                class="text-[9.5px] text-slate-400"
                                                >Saved with this path</span
                                            >
                                        </div>
                                        <div
                                            class="space-y-1.5 rounded-xl border border-slate-200 bg-slate-50 p-2.5"
                                        >
                                            <div
                                                class="flex justify-between text-[11px] font-semibold text-slate-700"
                                            >
                                                <span>Content Margin</span>
                                                <span
                                                    class="rounded border bg-white px-1.5 py-0.5 font-mono text-[10px]"
                                                    >{marginMm} mm</span
                                                >
                                            </div>
                                            <input
                                                type="range"
                                                min="8"
                                                max="30"
                                                step="1"
                                                bind:value={marginMm}
                                                bind:this={contentMarginInput}
                                                oninput={handleHtmlDecorationChanged}
                                                class="w-full accent-slate-900"
                                            />
                                        </div>
                                        {#if usesFiducials}
                                            <label class="block space-y-1.5">
                                                <span
                                                    class="text-[11px] font-bold text-slate-700"
                                                    >OMRChecker fiducial profile</span
                                                >
                                                <select
                                                    value={draft.marker_profile_id ??
                                                        ''}
                                                    onchange={(event) =>
                                                        selectMarkerProfile(
                                                            Number(
                                                                event
                                                                    .currentTarget
                                                                    .value,
                                                            ),
                                                        )}
                                                    class="w-full rounded-lg border border-slate-200 bg-white px-2 py-1.5 text-xs text-slate-800"
                                                >
                                                    <option value="" disabled
                                                        >Select a marker profile</option
                                                    >
                                                    {#each marker_profiles as profile}
                                                        <option
                                                            value={profile.id}
                                                            >{profile.code} v{profile.version}
                                                            · {profile.status}</option
                                                        >
                                                    {/each}
                                                </select>
                                                <span
                                                    class="block text-[10px] leading-relaxed text-slate-500"
                                                >
                                                    The selected profile
                                                    controls the four
                                                    registration fiducials
                                                    independently from the
                                                    content margin.
                                                </span>
                                            </label>
                                            <div
                                                bind:this={
                                                    markerInsetControlsElement
                                                }
                                            >
                                                <StudioFiducialInsetControls
                                                    bind:markerInsetsMm
                                                    disabled={!draft.marker_profile_id ||
                                                        markerInsetsSaving}
                                                    onPreview={previewMarkerInsets}
                                                    onCommit={persistMarkerInsets}
                                                />
                                            </div>
                                        {/if}
                                        {#if qrEnabled}
                                            <div
                                                class="rounded-xl border border-slate-200 bg-slate-50 p-2.5"
                                            >
                                                <StudioQrReservationControls
                                                    bind:showQrOverlay
                                                    bind:qrX
                                                    bind:qrY
                                                    bind:qrW
                                                    bind:qrH
                                                    bind:qrLocked
                                                    onChanged={handleHtmlDecorationChanged}
                                                />
                                            </div>
                                        {/if}
                                    </div>

                                    <!-- 2. Quick Scaffolds -->
                                    <div
                                        class="space-y-1.5 rounded-xl border border-emerald-200/80 bg-gradient-to-r from-emerald-50 to-teal-50 p-2.5"
                                    >
                                        <div
                                            class="flex items-center justify-between text-xs font-bold text-emerald-950"
                                        >
                                            <span
                                                class="flex items-center gap-1"
                                            >
                                                <Sparkles
                                                    class="size-3 text-emerald-600"
                                                />
                                                <span>Quick Scaffolds</span>
                                            </span>
                                            <span
                                                class="font-mono text-[9.5px] text-emerald-700"
                                                >{totalQuestionsCount} Items</span
                                            >
                                        </div>
                                        <div
                                            class="grid grid-cols-3 gap-1 pt-0.5"
                                        >
                                            <button
                                                type="button"
                                                onclick={() =>
                                                    applyPreset('150q')}
                                                class="rounded-lg border border-emerald-200 bg-white px-1.5 py-1 text-center text-[10px] font-bold text-emerald-900 shadow-2xs transition hover:bg-emerald-100"
                                            >
                                                150-Q (6 Subj)
                                            </button>
                                            <button
                                                type="button"
                                                onclick={() =>
                                                    applyPreset('100q')}
                                                class="rounded-lg border border-emerald-200 bg-white px-1.5 py-1 text-center text-[10px] font-bold text-emerald-900 shadow-2xs transition hover:bg-emerald-100"
                                            >
                                                100-Q (4 Subj)
                                            </button>
                                            <button
                                                type="button"
                                                onclick={() =>
                                                    applyPreset('50q')}
                                                class="rounded-lg border border-emerald-200 bg-white px-1.5 py-1 text-center text-[10px] font-bold text-emerald-900 shadow-2xs transition hover:bg-emerald-100"
                                            >
                                                50-Q (2 Subj)
                                            </button>
                                        </div>
                                        {#if aptitude_areas && aptitude_areas.length > 0}
                                            <button
                                                type="button"
                                                onclick={scaffoldFromAptitudeAreas}
                                                class="flex w-full items-center justify-center gap-1 rounded-lg border border-emerald-200 bg-white px-2 py-1 text-[10.5px] font-semibold text-emerald-900 transition hover:bg-emerald-100"
                                            >
                                                <Sparkles
                                                    class="size-3 text-emerald-600"
                                                />
                                                <span
                                                    >Scaffold from System
                                                    Aptitude Areas ({aptitude_areas.length})</span
                                                >
                                            </button>
                                        {/if}
                                    </div>

                                    <!-- 3. Component Layers -->
                                    <div>
                                        <button
                                            type="button"
                                            onclick={() => {
                                                showLayersPanel =
                                                    !showLayersPanel;
                                            }}
                                            class="group mb-1.5 flex w-full items-center justify-between text-left"
                                            aria-expanded={showLayersPanel}
                                        >
                                            <div
                                                class="flex items-center gap-1.5"
                                            >
                                                <span
                                                    class="text-xs font-bold text-slate-900"
                                                >
                                                    Layers ({components.length +
                                                        (showQrOverlay
                                                            ? 1
                                                            : 0)})
                                                </span>
                                                {#if selectedCanvasIds.length > 1}
                                                    <span
                                                        class="py-0.2 rounded-md bg-emerald-100 px-1.5 font-mono text-[9.5px] font-semibold text-emerald-800"
                                                    >
                                                        {selectedCanvasIds.length}
                                                        selected
                                                    </span>
                                                {/if}
                                            </div>
                                            <span
                                                class="flex items-center gap-1 text-[9.5px] text-slate-400 transition-colors group-hover:text-slate-600"
                                            >
                                                <span>Back to front</span>
                                                <ChevronDown
                                                    class={`size-3 transition-transform duration-200 ${showLayersPanel ? 'rotate-180' : ''}`}
                                                />
                                            </span>
                                        </button>
                                        {#if showLayersPanel}
                                            {#if components.length === 0 && !showQrOverlay}
                                                <div
                                                    class="rounded-xl border-2 border-dashed border-slate-300 bg-slate-50/50 p-4 text-center text-[11px] leading-relaxed text-slate-400"
                                                >
                                                    No components yet.<br />
                                                    Drag items from the palette onto
                                                    the canvas — or click one to add
                                                    it.
                                                </div>
                                            {:else}
                                                <div
                                                    class="space-y-1"
                                                    role="list"
                                                    aria-label="Component layers"
                                                >
                                                    {#if showQrOverlay}
                                                        <div
                                                            role="listitem"
                                                            class={`group relative flex items-center gap-1.5 rounded-lg border p-1.5 text-[11px] transition-all ${
                                                                selectedCanvasIds.includes(
                                                                    QR_CANVAS_ITEM_ID,
                                                                )
                                                                    ? 'border-emerald-500 bg-emerald-50 shadow-2xs ring-1 ring-emerald-400'
                                                                    : 'border-slate-200 bg-white hover:border-slate-300'
                                                            }`}
                                                            title="Reserved instance QR overlay"
                                                        >
                                                            <div
                                                                class="shrink-0 p-0.5 text-slate-300"
                                                                title="Fixed base layer"
                                                            >
                                                                <GripVertical
                                                                    class="size-3.5 opacity-30"
                                                                />
                                                            </div>
                                                            <button
                                                                type="button"
                                                                onclick={(
                                                                    e,
                                                                ) => {
                                                                    e.stopPropagation();
                                                                    selectCanvasItem(
                                                                        QR_CANVAS_ITEM_ID,
                                                                        e.shiftKey,
                                                                    );
                                                                }}
                                                                class="flex min-w-0 flex-1 items-center gap-1.5 text-left focus:outline-hidden"
                                                                aria-label="Select QR Reservation layer"
                                                            >
                                                                <span
                                                                    class="flex size-4 shrink-0 items-center justify-center rounded bg-slate-100 font-mono text-[9px] font-medium text-slate-500"
                                                                    >1</span
                                                                >
                                                                <span
                                                                    class="truncate font-bold text-slate-800"
                                                                    >QR
                                                                    Reservation</span
                                                                >
                                                            </button>
                                                            <div
                                                                class={`flex shrink-0 items-center gap-0.5 transition-opacity duration-150 ${qrLocked ? 'opacity-100' : 'opacity-0 group-hover:opacity-100 focus-within:opacity-100'}`}
                                                            >
                                                                <button
                                                                    type="button"
                                                                    onclick={(
                                                                        e,
                                                                    ) => {
                                                                        e.stopPropagation();
                                                                        qrLocked =
                                                                            !qrLocked;
                                                                        handleHtmlDecorationChanged();
                                                                    }}
                                                                    class={`rounded p-1 transition ${qrLocked ? 'bg-amber-500/10 text-amber-700 dark:text-amber-400' : 'text-muted-foreground hover:text-foreground hover:bg-slate-100'}`}
                                                                    title={qrLocked
                                                                        ? 'Unlock position'
                                                                        : 'Lock position'}
                                                                    aria-label={qrLocked
                                                                        ? 'Unlock QR Reservation'
                                                                        : 'Lock QR Reservation'}
                                                                >
                                                                    {#if qrLocked}<Lock
                                                                            class="size-3"
                                                                        />{:else}<Unlock
                                                                            class="size-3"
                                                                        />{/if}
                                                                </button>
                                                                <button
                                                                    type="button"
                                                                    onclick={(
                                                                        e,
                                                                    ) => {
                                                                        e.stopPropagation();
                                                                        showQrOverlay = false;
                                                                        clearComponentSelection();
                                                                        handleHtmlDecorationChanged();
                                                                    }}
                                                                    class="text-muted-foreground hover:text-destructive hover:bg-destructive/10 rounded p-1 transition"
                                                                    title="Remove QR reservation"
                                                                    aria-label="Remove QR Reservation"
                                                                >
                                                                    <Trash2
                                                                        class="size-3"
                                                                    />
                                                                </button>
                                                            </div>
                                                        </div>
                                                    {/if}
                                                    {#each components as c, layerIndex (c.id)}
                                                        {@const layerNumber =
                                                            layerIndex +
                                                            (showQrOverlay
                                                                ? 2
                                                                : 1)}
                                                        {@const isDragging =
                                                            layerDragId ===
                                                            c.id}
                                                        {@const isDropTarget =
                                                            layerDragOverId ===
                                                                c.id &&
                                                            !isDragging}
                                                        {@const isSelected =
                                                            selectedCanvasIds.includes(
                                                                c.id,
                                                            )}
                                                        <div
                                                            role="listitem"
                                                            draggable="true"
                                                            ondragstart={(e) =>
                                                                handleLayerDragStart(
                                                                    e,
                                                                    c.id,
                                                                )}
                                                            ondragover={(e) =>
                                                                handleLayerDragOver(
                                                                    e,
                                                                    c.id,
                                                                )}
                                                            ondragleave={(e) =>
                                                                handleLayerDragLeave(
                                                                    e,
                                                                    c.id,
                                                                )}
                                                            ondragend={handleLayerDragEnd}
                                                            ondrop={(e) =>
                                                                handleLayerDrop(
                                                                    e,
                                                                    c.id,
                                                                )}
                                                            onkeydown={(e) =>
                                                                handleLayerKeyDown(
                                                                    e,
                                                                    c.id,
                                                                    layerIndex,
                                                                )}
                                                            class={`group relative flex items-center gap-1.5 rounded-lg border p-1.5 text-[11px] transition-all ${
                                                                isDragging
                                                                    ? 'scale-[0.98] border-dashed border-slate-300 bg-slate-50 opacity-40'
                                                                    : isSelected
                                                                      ? 'border-emerald-500 bg-emerald-50 shadow-2xs ring-1 ring-emerald-400'
                                                                      : 'border-slate-200 bg-white hover:border-slate-300'
                                                            }`}
                                                            title="Drag handle to reorder layer (or Alt+Arrow keys)"
                                                        >
                                                            <!-- Drop indicator bar -->
                                                            {#if isDropTarget && layerDragPosition === 'above'}
                                                                <div
                                                                    class="pointer-events-none absolute -top-1 right-1 left-1 z-20 h-0.5 animate-pulse rounded-full bg-emerald-500 shadow-xs"
                                                                ></div>
                                                            {/if}
                                                            {#if isDropTarget && layerDragPosition === 'below'}
                                                                <div
                                                                    class="pointer-events-none absolute right-1 -bottom-1 left-1 z-20 h-0.5 animate-pulse rounded-full bg-emerald-500 shadow-xs"
                                                                ></div>
                                                            {/if}

                                                            <!-- Drag Grip Handle -->
                                                            <div
                                                                class="shrink-0 cursor-grab p-0.5 text-slate-400 transition-colors group-hover:text-slate-700 active:cursor-grabbing"
                                                                title="Drag to reorder"
                                                            >
                                                                <GripVertical
                                                                    class="size-3.5"
                                                                />
                                                            </div>

                                                            <!-- Layer selection & title -->
                                                            <button
                                                                type="button"
                                                                onclick={(
                                                                    e,
                                                                ) => {
                                                                    e.stopPropagation();
                                                                    selectComponent(
                                                                        c.id,
                                                                        e.shiftKey,
                                                                    );
                                                                }}
                                                                class="flex min-w-0 flex-1 items-center gap-1.5 text-left focus:outline-hidden"
                                                                aria-label={`Select ${componentLabel(c)} layer (Layer ${layerNumber})`}
                                                            >
                                                                <span
                                                                    class="flex size-4 shrink-0 items-center justify-center rounded bg-slate-100 font-mono text-[9px] font-medium text-slate-500"
                                                                >
                                                                    {layerNumber}
                                                                </span>
                                                                <span
                                                                    class={`truncate ${isSelected ? 'font-bold text-emerald-950' : 'font-bold text-slate-800'}`}
                                                                >
                                                                    {componentLabel(
                                                                        c,
                                                                    )}
                                                                </span>
                                                            </button>

                                                            <!-- Restrained hover/focus actions (Lock & Delete) -->
                                                            <div
                                                                class={`flex shrink-0 items-center gap-0.5 transition-opacity duration-150 ${c.isLocked ? 'opacity-100' : 'opacity-0 group-hover:opacity-100 focus-within:opacity-100'}`}
                                                            >
                                                                <button
                                                                    type="button"
                                                                    onclick={(
                                                                        e,
                                                                    ) => {
                                                                        e.stopPropagation();
                                                                        c.isLocked =
                                                                            !c.isLocked;
                                                                        syncComponentsToSections(
                                                                            'Toggle component lock',
                                                                        );
                                                                    }}
                                                                    class={`rounded p-1 transition ${c.isLocked ? 'bg-amber-500/10 text-amber-700 dark:text-amber-400' : 'text-muted-foreground hover:text-foreground hover:bg-slate-100'}`}
                                                                    title={c.isLocked
                                                                        ? 'Unlock position'
                                                                        : 'Lock position'}
                                                                    aria-label={c.isLocked
                                                                        ? `Unlock ${componentLabel(c)}`
                                                                        : `Lock ${componentLabel(c)}`}
                                                                >
                                                                    {#if c.isLocked}<Lock
                                                                            class="size-3"
                                                                        />{:else}<Unlock
                                                                            class="size-3"
                                                                        />{/if}
                                                                </button>
                                                                <button
                                                                    type="button"
                                                                    onclick={(
                                                                        e,
                                                                    ) => {
                                                                        e.stopPropagation();
                                                                        removeComponent(
                                                                            c.id,
                                                                        );
                                                                    }}
                                                                    class="text-muted-foreground hover:text-destructive hover:bg-destructive/10 rounded p-1 transition"
                                                                    title="Remove component"
                                                                    aria-label={`Remove ${componentLabel(c)}`}
                                                                >
                                                                    <Trash2
                                                                        class="size-3"
                                                                    />
                                                                </button>
                                                            </div>
                                                        </div>
                                                    {/each}
                                                </div>
                                            {/if}
                                        {/if}
                                    </div>
                                </div>
                            {:else if designerSubTab === 'print_preview'}
                                <div class="space-y-3">
                                    <div
                                        class="rounded-xl border border-emerald-200 bg-emerald-50 px-3 py-2.5 text-[11px] leading-relaxed text-emerald-950"
                                    >
                                        {#if previewRunning}
                                            Preparing print sheet and verifying
                                            native OMR frame…
                                        {:else if previewError}
                                            Print preview failed: {typeof previewError ===
                                                'object' &&
                                            previewError !== null
                                                ? previewError.detail ||
                                                  previewError.message ||
                                                  previewError.code ||
                                                  ''
                                                : previewError}
                                        {:else if canonicalPreview}
                                            Print-ready server artifact is
                                            active. Reused by Recognition
                                            Mapping and compile.
                                        {:else}
                                            Save the current design to generate
                                            its printable artifact.
                                        {/if}
                                    </div>
                                    <button
                                        type="button"
                                        onclick={async () => {
                                            canonicalPreview = null;
                                            await openPrintPreview();
                                        }}
                                        disabled={previewRunning}
                                        class="w-full rounded-lg bg-slate-900 px-3 py-2 text-[11px] font-semibold text-white disabled:opacity-50"
                                    >
                                        Regenerate Print Preview
                                    </button>
                                </div>
                            {:else}
                                <div class="space-y-2.5">
                                    <div
                                        class="flex items-center justify-between"
                                    >
                                        <label
                                            class="text-xs font-bold text-slate-700"
                                            >Compiled HTML Markup</label
                                        >
                                        <button
                                            type="button"
                                            onclick={() =>
                                                navigator.clipboard?.writeText(
                                                    htmlCode || '',
                                                )}
                                            class="flex items-center gap-1 text-[11px] font-semibold text-emerald-600 hover:text-emerald-800"
                                        >
                                            <Check class="size-3" />
                                            <span>Copy</span>
                                        </button>
                                    </div>
                                    <textarea
                                        readonly
                                        bind:value={htmlCode}
                                        rows="9"
                                        class="w-full resize-y rounded-xl border border-slate-800 bg-slate-950 p-2.5 font-mono text-[10px] leading-relaxed text-emerald-400"
                                        placeholder="Compiled HTML appears here as you add components."
                                    ></textarea>

                                    <div
                                        class="flex items-center justify-between pt-1"
                                    >
                                        <label
                                            class="text-xs font-bold text-slate-700"
                                            >Suite Reference (HTML / CSS + AI
                                            Prompt)</label
                                        >
                                        <button
                                            type="button"
                                            onclick={() =>
                                                navigator.clipboard?.writeText(
                                                    COMPONENT_SUITE_PROMPT,
                                                )}
                                            class="flex items-center gap-1 text-[11px] font-semibold text-blue-600 hover:text-blue-800"
                                        >
                                            <Check class="size-3" />
                                            <span>Copy</span>
                                        </button>
                                    </div>
                                    <textarea
                                        readonly
                                        value={COMPONENT_SUITE_PROMPT}
                                        rows="7"
                                        class="w-full resize-y rounded-xl border border-slate-300 bg-slate-100 p-2.5 font-mono text-[9px] leading-relaxed text-slate-700"
                                        placeholder="Suite definition…"
                                    ></textarea>
                                    <p
                                        class="text-[10px] leading-relaxed text-slate-400"
                                    >
                                        Copy the suite reference to reproduce
                                        this component system in another
                                        interface or paste it into an AI
                                        assistant.
                                    </p>
                                </div>
                            {/if}
                        {:else}
                            <!-- Option 2: Upload Document File -->
                            <div class="space-y-3">
                                <!-- 1. Primary Upload Document Dropzone -->
                                <div
                                    class="border-border bg-muted/20 hover:bg-muted/40 hover:border-primary/50 rounded-2xl border-2 border-dashed p-4 text-center transition"
                                >
                                    <Upload
                                        class="text-muted-foreground mx-auto mb-1 size-5"
                                    />
                                    <p
                                        class="text-foreground font-sans text-xs font-bold"
                                    >
                                        {Number(draft.requested_page_count) > 1
                                            ? `Upload Answer Sheet (${draft.requested_page_count} Pages)`
                                            : 'Upload Answer Sheet'}
                                    </p>
                                    <p
                                        class="text-muted-foreground mt-0.5 font-mono text-[10px]"
                                    >
                                        PDF, Word (.docx), or {draft.requested_page_count}
                                        {Number(draft.requested_page_count) > 1
                                            ? ' Page Photos (PNG/JPG)'
                                            : ' Page Photo (PNG/JPG)'}
                                    </p>
                                    <p
                                        class="text-muted-foreground mt-2 rounded-lg bg-slate-100 dark:bg-slate-800/60 px-2.5 py-1.5 text-left text-[10.5px] leading-relaxed"
                                    >
                                        <span class="font-semibold text-foreground">Photo tip:</span>
                                        Place clean sheet flat on a <span class="font-semibold text-foreground">plain, dark matte background</span>
                                        with bright, even light so all 4 corners contrast sharply.
                                        {#if Number(draft.requested_page_count) > 1}
                                            You can select {draft.requested_page_count} photos at once to autocrop both pages sequentially.
                                        {/if}
                                    </p>
                                    {#if Number(draft.requested_page_count) === 1}
                                        <label
                                            class="text-foreground mx-auto mt-3 flex max-w-xs cursor-pointer items-start gap-2 text-left text-xs"
                                        >
                                            <input
                                                type="checkbox"
                                                bind:checked={imageAlreadyCropped}
                                                class="accent-primary mt-0.5 size-4 shrink-0"
                                            />
                                            <span>
                                                Image is already cropped and straight
                                                <span class="text-muted-foreground block text-[10px]"
                                                    >Otherwise, review paper corners before mapping.</span
                                                >
                                            </span>
                                        </label>
                                    {/if}
                                    <label
                                        class="text-foreground mx-auto mt-2 flex max-w-xs cursor-pointer items-start gap-2 text-left text-xs"
                                    >
                                        <input
                                            type="checkbox"
                                            bind:checked={modifyUploadedDocument}
                                            class="accent-primary mt-0.5 size-4 shrink-0"
                                        />
                                        <span>
                                            Modify document with headers, custom text, or margins
                                            <span class="text-muted-foreground block text-[10px]"
                                                >Default: Use as-is for supplier/licensed stock without modifying headers or margins.</span
                                            >
                                        </span>
                                    </label>
                                    <input
                                        type="file"
                                        accept=".pdf,.docx,.doc,.png,.jpg,.jpeg,.webp"
                                        multiple={Number(draft.requested_page_count) > 1}
                                        bind:this={uploadFileInput}
                                        onchange={handleFileUpload}
                                        class="hidden"
                                    />
                                    <button
                                        type="button"
                                        onclick={() => uploadFileInput?.click()}
                                        disabled={uploadRunning}
                                        class="border-border bg-card text-foreground hover:bg-muted mt-2.5 rounded-lg border px-4 py-1.5 text-xs font-semibold shadow-xs transition disabled:opacity-50"
                                    >
                                        Browse Computer
                                    </button>
                                </div>

                                <!-- 2. Compact Sample Sheet Helper -->
                                <div
                                    class="border-border bg-card/60 flex items-center justify-between rounded-xl border p-2.5 text-xs shadow-xs"
                                >
                                    <div class="flex items-center gap-1.5 min-w-0">
                                        <Sparkles class="size-3.5 shrink-0 text-emerald-500" />
                                        <span class="text-[11px] font-medium text-foreground truncate">Sample 150-Q sheet:</span>
                                    </div>
                                    <div class="flex items-center gap-1.5 shrink-0 text-[10.5px]">
                                        {#if Number(draft.requested_page_count) === 1}
                                            <button
                                                type="button"
                                                onclick={loadSampleUploadedDocument}
                                                disabled={uploadRunning}
                                                class="text-primary hover:underline font-semibold disabled:opacity-50"
                                            >
                                                Import
                                            </button>
                                        {:else}
                                            <span class="text-muted-foreground text-[10px] italic">
                                                1-page sample
                                            </span>
                                        {/if}
                                        <span class="text-muted-foreground">&bull;</span>
                                        <a
                                            href="/samples/omr/standard-150q-answer-sheet.pdf"
                                            download="standard-150q-answer-sheet.pdf"
                                            class="text-muted-foreground hover:text-foreground hover:underline"
                                        >
                                            PDF
                                        </a>
                                        <span class="text-muted-foreground">&bull;</span>
                                        <a
                                            href="/samples/omr/standard-150q-answer-sheet.png"
                                            download="standard-150q-answer-sheet.png"
                                            class="text-muted-foreground hover:text-foreground hover:underline"
                                        >
                                            PNG
                                        </a>
                                    </div>
                                </div>

                                <!-- 3. Optional Overlay Palette (Only if user opts in to modify document) -->
                                {#if modifyUploadedDocument}
                                    <div
                                        class="border-border bg-card space-y-2 rounded-xl border p-3 shadow-xs"
                                    >
                                        <div
                                            class="flex items-center justify-between"
                                        >
                                            <span
                                                class="text-foreground font-sans text-xs font-bold"
                                                >Overlay Palette</span
                                            >
                                            <span
                                                class="text-muted-foreground font-mono text-[9.5px]"
                                                >Questions are configured later</span
                                            >
                                        </div>
                                        <div class="grid grid-cols-2 gap-1.5">
                                            {#each paletteForPath(componentPalettePath) as item}
                                                <button
                                                    type="button"
                                                    onclick={() =>
                                                        handlePaletteItem(item)}
                                                    class="border-border bg-background hover:bg-muted/60 hover:border-primary/50 rounded-lg border p-2 text-left transition"
                                                    title={item.description}
                                                >
                                                    <span
                                                        class="text-foreground block text-[10px] leading-tight font-bold"
                                                        >{item.label}</span
                                                    >
                                                    <span
                                                        class="text-muted-foreground mt-0.5 block text-[8.5px] leading-snug"
                                                        >{item.description}</span
                                                    >
                                                </button>
                                            {/each}
                                        </div>
                                        <div class="grid grid-cols-3 gap-1.5">
                                            {#each availableOverlayPalette(componentPalettePath) as item}
                                                <button
                                                    type="button"
                                                    onclick={() =>
                                                        handleOverlayPaletteItem(
                                                            item.kind,
                                                        )}
                                                    class="border-border bg-background hover:bg-muted/60 hover:border-primary/50 rounded-lg border p-2 text-left transition"
                                                    title={item.description}
                                                >
                                                    <span
                                                        class="text-foreground block text-[9.5px] leading-tight font-bold"
                                                        >{item.label}</span
                                                    >
                                                </button>
                                            {/each}
                                        </div>
                                        <button
                                            type="button"
                                            onclick={() => requestGoToStage(2)}
                                            class="btn-tactile-primary w-full rounded-lg py-2 text-xs font-semibold shadow-xs transition"
                                        >
                                            Open Layout &amp; Overlays
                                        </button>
                                    </div>
                                {/if}
                                {#if uploadRunning}
                                    <div
                                        class="flex items-center gap-2 rounded-xl border border-blue-200 bg-blue-50 p-2.5 text-xs text-blue-800"
                                    >
                                        <Loader2
                                            class="size-3.5 shrink-0 animate-spin text-blue-600"
                                        />
                                        <span
                                            >{conversionStatusText ||
                                                'Converting file…'}</span
                                        >
                                    </div>
                                {/if}

                                {#if conversionError}
                                    <div
                                        class="rounded-xl border border-red-200 bg-red-50 p-2.5 text-xs text-red-800"
                                    >
                                        {conversionError}
                                    </div>
                                {/if}
                                {#if previewError}
                                    <div
                                        class="rounded-xl border border-rose-200 bg-rose-50 p-2.5 text-xs text-rose-800"
                                    >
                                        Recognition Mapping blocked: {typeof previewError ===
                                            'object' && previewError !== null
                                            ? previewError.detail ||
                                              previewError.message ||
                                              previewError.code ||
                                              ''
                                            : previewError}
                                    </div>
                                {/if}
                            </div>
                        {/if}
                    </div>
                {:else if sidebarStage === 2}
                    <!-- STEP 2: LAYOUT, MARGINS & OVERLAYS -->
                    <div inert={isReadOnly}>
                    <StudioOverlayPanel
                        showFiducialControls={usesFiducials}
                        showQrControls={qrEnabled}
                        bind:marginMm
                        bind:markerInsetsMm
                        markerInsetsDisabled={!draft.marker_profile_id ||
                            markerInsetsSaving}
                        onMarkerInsetsPreview={previewMarkerInsets}
                        onMarkerInsetsCommit={persistMarkerInsets}
                        bind:markerPattern
                        markerProfiles={marker_profiles}
                        selectedMarkerProfileId={draft.marker_profile_id}
                        onMarkerProfileChange={selectMarkerProfile}
                        bind:showHeaderOverlay
                        bind:headerTitle
                        bind:headerSubtitle
                        bind:headerFontSize
                        bind:headerFontFamily
                        bind:headerColor
                        bind:headerAlign
                        bind:headerLocked
                        bind:showQrOverlay
                        bind:qrX
                        bind:qrY
                        bind:qrW
                        bind:qrH
                        bind:qrLocked
                        bind:showInstructionsOverlay
                        bind:instructionsContent
                        bind:instructionsFontSize
                        bind:instructionsFontFamily
                        bind:instructionsColor
                        bind:instLocked
                        bind:showSignatureOverlay
                        bind:signatureContent
                        bind:signatureFontSize
                        bind:signatureFontFamily
                        bind:signatureColor
                        bind:sigLocked
                        onChanged={handleHtmlDecorationChanged}
                        onBack={() => requestGoToStage(1)}
                        onNext={() => requestGoToStage(3)}
                    />
                    </div>
                {:else if sidebarStage === 3}
                    <div data-omr-stage-panel="3">
                        {#if stage1Mode !== 'html' && draft.original_photo_url && draft.can_edit}
                            <div class="text-muted-foreground mb-3 flex items-start justify-between gap-3 text-xs">
                                <p class="min-w-0 leading-relaxed">
                                    Crop misaligned? Reopen the original to correct it. Guides stay editable, but must be realigned and reviewed before verification.
                                </p>
                                <button
                                    type="button"
                                    onclick={openOriginalPhotoCrop}
                                    disabled={isReadOnly || uploadRunning || stageTransitionRunning}
                                    class="text-primary focus-visible:ring-ring shrink-0 rounded-md px-2 py-1 font-semibold underline underline-offset-2 hover:text-primary/80 focus-visible:ring-2 focus-visible:outline-none disabled:cursor-not-allowed disabled:opacity-50"
                                >
                                    Correct crop from original
                                </button>
                            </div>
                            {#if cropRecoveryError}
                                <p role="alert" aria-live="assertive" class="mb-3 text-xs text-rose-700 dark:text-rose-300">
                                    {cropRecoveryError}
                                </p>
                            {/if}
                        {/if}
                        {#if cropReviewRequired}
                            <div
                                role="alert"
                                class="mb-3 rounded-xl border border-amber-300 bg-amber-50 p-3 text-xs text-amber-950 dark:border-amber-800 dark:bg-amber-950/30 dark:text-amber-100"
                            >
                                <p class="font-semibold">
                                    Crop changed. Existing guides are not yet verified
                                    against this image.
                                </p>
                                <p class="mt-1">
                                    Move each question and Applicant Number guide onto
                                    its printed bubbles. The old positions are not
                                    automatically transformed.
                                </p>
                                <button
                                    type="button"
                                    onclick={confirmCropReview}
                                    disabled={isReadOnly || saveStatus === 'saving' ||
                                        !orderedPages[0]?.confirmed}
                                    class="mt-2 rounded-lg border border-amber-600 px-3 py-1.5 font-semibold hover:bg-amber-100 disabled:opacity-50 dark:hover:bg-amber-900/50"
                                >
                                    I reviewed and aligned all guides
                                </button>
                            </div>
                        {/if}
                        <!-- STEP 3: QUESTION BUBBLES & PITCH GEOMETRY -->
                        <StudioBubblePanel
                            showRegistrationControls={usesFiducials}
                            bind:sections
                            bind:smartSnapSearchMm
                            bind:showSmartSnapSearchZone={showSmartSnapSearchZone}
                            bind:previewingSearchZone={previewingSearchZone}
                            bind:smartSnapSearchZoneScope={smartSnapSearchZoneScope}
                            bind:activeSectionIndex
                            bind:selectedSectionIndices
                            bind:plottingConfig
                            isUploadedSource={stage1Mode !== 'html'}
                            pageCount={Number(draft.requested_page_count) ||
                                orderedPages.length ||
                                1}
                            bind:declaredInventory
                            bind:physicalInventory
                            {activePageNo}
                            bind:documentFields
                            bind:activeDocumentFieldId
                            identity={identityDeclaration}
                            onIdentityChanged={(value) => {
                                identityDeclaration = value;
                            }}
                            aptitudeAreas={aptitude_areas}
                            testInstrument={test_instrument}
                            markerProfiles={marker_profiles}
                            selectedMarkerProfileId={draft.marker_profile_id}
                            onMarkerProfileChange={selectMarkerProfile}
                            {canonicalPreview}
                            {previewRunning}
                            {previewError}
                            transitionRunning={stageTransitionRunning}
                            {mappingDirty}
                            mappingErrors={studioSaveIssues}
                            bind:previewSurface
                            canUndo={historyStatus.canUndo}
                            canRedo={historyStatus.canRedo}
                            onUndo={undoEditor}
                            onRedo={redoEditor}
                            onBatchAlign={batchAlign}
                            onBatchDistribute={batchDistribute}
                            onBatchToggleLock={batchToggleLock}
                            onChanged={recordBubbleEdit}
                            onPhysicalInventoryChanged={updatePhysicalInventory}
                            onDocumentFieldsChanged={() => recordBubbleEdit()}
                            onSmartSnapBubbles={handleSmartSnapBubbles}
                            {smartSnapRunning}
                            readOnly={isReadOnly}
                            onCreateEditableCopy={handleCreateEditableCopy}
                            {creatingEditableCopy}
                            readOnlyReason={draft.read_only_reason}
                            canUnlockToEdit={Boolean(draft.can_unlock_to_edit)}
                            unlockBlockReason={draft.unlock_block_reason}
                            onUnlockDraft={handleUnlockDraft}
                            {unlockingDraft}
                            compiledVersionNumber={draft.compiled_version_number}
                            onBack={() =>
                                requestGoToStage(
                                    modifyUploadedDocument ? 2 : 1,
                                )}
                            onNext={() => requestGoToStage(4)}
                        />
                    </div>
                {:else if sidebarStage === 4}
                    <div
                        data-omr-stage-panel="4"
                        class="studio-verification-panel min-w-0"
                    >
                        <StudioExportVerifyPanel
                            bind:draft
                            {urls}
                            getCsrfToken={csrfToken}
                            verification_run={activeVerificationRun}
                            {physical_run_id}
                            marker_profile_status={selectedMarkerProfile?.status ??
                                null}
                            onDraftLockVersion={syncDraftLockVersion}
                            kit_context={kit_context}
                            onBackToKit={returnToKit}
                            onBack={() => requestGoToStage(3)}
                            lifecycleBusy={stageTransitionRunning}
                        />
                    </div>
                {/if}
            {/key}
        </div>

        <!-- Pinned Bottom Navigation Footer in Floating Card -->
        {#if currentStage === 1}
            <div
                class="border-border bg-card/90 dark:bg-card/95 shrink-0 border-t p-3 backdrop-blur-xs"
            >
                <button
                    type="button"
                    onclick={() =>
                        requestGoToStage(stage1Mode === 'html' ? 3 : 2)}
                    class="bg-primary text-primary-foreground hover:bg-primary/90 flex w-full items-center justify-center gap-1.5 rounded-xl py-2 text-xs font-semibold shadow-xs transition"
                >
                    <span
                        >{stage1Mode === 'html'
                            ? 'Continue to Recognition Mapping'
                            : 'Continue to Layout & Markers'}</span
                    >
                    <ChevronRight class="size-3.5" />
                </button>
            </div>
        {/if}
    </aside>

    {#if photoCropFile && photoCropUrl}
        <div
            class="fixed inset-0 z-[70] flex items-center justify-center bg-slate-900/70 p-4"
        >
            <div
                role="dialog"
                aria-modal="true"
                tabindex="-1"
                class="border-border bg-card flex max-h-[95vh] w-full max-w-2xl flex-col gap-3 rounded-2xl border p-4 shadow-2xl sm:p-6"
            >
                <div class="flex flex-wrap items-start justify-between gap-2">
                    <div>
                        <h3 class="text-foreground text-base font-bold">
                            {Number(draft.requested_page_count) > 1
                                ? `Check the paper edges · Page ${photoCropTargetPageNo} of ${draft.requested_page_count}`
                                : 'Check the paper edges'}
                        </h3>
                        <p class="text-muted-foreground mt-1 text-xs">
                            {photoCropSuggestion
                                ? 'Suggested corners are shown. Drag any corner pin to use the 2.5× precision magnifier.'
                                : 'Position all four corners on the exact paper vertices using the 2.5× magnifier.'}
                        </p>
                        {#if photoCropIsCorrection || photoCropIsPageReplacement}
                            <p class="mt-1 text-xs text-amber-700 dark:text-amber-300">
                                This image change updates the recognition reference.
                                Your saved guides remain editable but must be reviewed
                                against the new crop before verification.
                            </p>
                        {/if}
                        {#if photoCropIsCorrection}
                            <p class="mt-1 text-xs font-semibold text-amber-700 dark:text-amber-300">
                                Set all four corners manually ({photoCropTouchedCorners.length}/4 positioned).
                                Automatic suggestions are not used for correction.
                            </p>
                        {/if}
                    </div>
                    <div class="flex flex-wrap items-center gap-1.5">
                        <button
                            type="button"
                            data-omr-crop-reset="true"
                            disabled={uploadRunning}
                            onclick={() => resetPhotoCropToSheet(0.02)}
                            class="border-border bg-muted/60 hover:bg-muted text-foreground rounded-lg border px-2.5 py-1.5 text-xs font-semibold transition disabled:opacity-50"
                            title="Snap 4 corners to sheet bounds with a 2% margin"
                        >
                            Reset to Sheet
                        </button>
                        <button
                            type="button"
                            data-omr-crop-full="true"
                            disabled={uploadRunning}
                            onclick={() => resetPhotoCropToSheet(0.002)}
                            class="border-border bg-muted/60 hover:bg-muted text-foreground rounded-lg border px-2.5 py-1.5 text-xs font-semibold transition disabled:opacity-50"
                            title="Snap to exact full image bounds (0% inset)"
                        >
                            Full Image
                        </button>
                        <button
                            type="button"
                            data-omr-crop-rotate="true"
                            disabled={uploadRunning}
                            onclick={rotatePhotoCropClockwise}
                            class="border-border bg-muted/60 hover:bg-muted text-foreground rounded-lg border px-2.5 py-1.5 text-xs font-semibold transition disabled:opacity-50"
                            title="Rotate photo 90° clockwise"
                        >
                            Rotate 90°
                        </button>
                    </div>
                </div>
                <div
                    class="flex min-h-0 items-center justify-center overflow-hidden rounded-lg bg-slate-800 p-2"
                    onpointermove={(event) => {
                        if (photoCropDragging >= 0) {
                            movePhotoCorner(
                                photoCropDragging,
                                event.clientX,
                                event.clientY,
                            );
                        }
                    }}
                    onmousemove={(event) => {
                        if (photoCropDragging >= 0) {
                            movePhotoCorner(
                                photoCropDragging,
                                event.clientX,
                                event.clientY,
                            );
                        }
                    }}
                    onpointerup={() => (photoCropDragging = -1)}
                    onmouseup={() => (photoCropDragging = -1)}
                >
                    <div
                        class="relative mx-auto w-fit max-w-full select-none"
                        bind:this={photoCropSurface}
                        data-omr-crop-rotation={photoCropRotation}
                    >
                        <img
                            src={photoCropUrl}
                            alt="Uploaded answer sheet before cropping"
                            class="block max-h-[calc(95vh-13rem)] w-auto max-w-full"
                        />
                        <svg
                            viewBox="0 0 1000 1000"
                            preserveAspectRatio="none"
                            aria-hidden="true"
                            class="pointer-events-none absolute inset-0 size-full"
                        >
                            <polygon
                                points={photoCropCorners
                                    .map(
                                        (point) =>
                                            `${point.x * 1000},${point.y * 1000}`,
                                    )
                                    .join(' ')}
                                fill="rgba(16,185,129,0.12)"
                                stroke="#10b981"
                                stroke-width="4"
                                vector-effect="non-scaling-stroke"
                            />
                        </svg>
                        {#each photoCropCorners as corner, index}
                            <button
                                type="button"
                                data-omr-crop-pin={index}
                                aria-label={`${['Top left', 'Top right', 'Bottom right', 'Bottom left'][index]} paper corner; drag or use arrow keys`}
                                class={`absolute size-6 -translate-x-1/2 -translate-y-1/2 touch-none rounded-full border-2 border-white shadow-md transition-transform focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-emerald-400 ${
                                    photoCropDragging === index
                                        ? 'scale-125 bg-emerald-500 ring-4 ring-emerald-400/50'
                                        : 'bg-emerald-600 hover:scale-110'
                                }`}
                                style={`left: ${corner.x * 100}%; top: ${corner.y * 100}%`}
                                onpointerdown={(event) => {
                                    photoCropDragging = index;
                                    event.currentTarget.setPointerCapture?.(
                                        event.pointerId,
                                    );
                                }}
                                onmousedown={() => {
                                    photoCropDragging = index;
                                }}
                                onpointermove={(event) => {
                                    if (photoCropDragging === index)
                                        movePhotoCorner(
                                            index,
                                            event.clientX,
                                            event.clientY,
                                        );
                                }}
                                onpointerup={() => (photoCropDragging = -1)}
                                onmouseup={() => (photoCropDragging = -1)}
                                onpointercancel={() => (photoCropDragging = -1)}
                                onkeydown={(event) =>
                                    nudgePhotoCorner(event, index)}
                            ></button>
                        {/each}

                        {#if photoCropDragging >= 0 && photoCropCorners[photoCropDragging]}
                            {@const activeCorner =
                                photoCropCorners[photoCropDragging]}
                            {@const cornerLabel =
                                ['TL', 'TR', 'BR', 'BL'][photoCropDragging] ??
                                'Pin'}
                            {@const surfaceW =
                                photoCropSurface?.clientWidth || 360}
                            {@const surfaceH =
                                photoCropSurface?.clientHeight || 500}
                            {@const loupeVerticalOffset =
                                activeCorner.y < 0.25 ? '4rem' : '-4.5rem'}
                            <div
                                data-omr-crop-loupe="true"
                                data-omr-crop-loupe-corner={photoCropDragging}
                                class="pointer-events-none absolute z-30 size-28 -translate-x-1/2 -translate-y-1/2 overflow-hidden rounded-full border-2 border-emerald-400 bg-slate-950 shadow-2xl ring-2 ring-slate-900/80"
                                style={`left: ${activeCorner.x * 100}%; top: calc(${activeCorner.y * 100}% + ${loupeVerticalOffset});`}
                            >
                                <img
                                    src={photoCropUrl}
                                    alt="2.5x magnified corner loupe"
                                    class="pointer-events-none absolute max-w-none select-none"
                                    style={`width: ${surfaceW * 2.5}px; height: ${surfaceH * 2.5}px; left: ${56 - activeCorner.x * surfaceW * 2.5}px; top: ${56 - activeCorner.y * surfaceH * 2.5}px;`}
                                />
                                <div
                                    class="pointer-events-none absolute inset-0 flex items-center justify-center"
                                >
                                    <div
                                        class="h-full w-px bg-emerald-400/85"
                                    ></div>
                                    <div
                                        class="absolute h-px w-full bg-emerald-400/85"
                                    ></div>
                                    <div
                                        class="size-2.5 rounded-full border border-white bg-emerald-500/40"
                                    ></div>
                                </div>
                                <div
                                    class="pointer-events-none absolute bottom-1 left-1/2 -translate-x-1/2 rounded bg-slate-900/90 px-1.5 py-0.5 font-mono text-[9px] font-bold text-emerald-300"
                                >
                                    {cornerLabel} · 2.5×
                                </div>
                            </div>
                        {/if}
                    </div>
                </div>
                {#if conversionError}
                    <p role="alert" class="text-sm text-rose-700">{conversionError}</p>
                {/if}
                <div class="flex flex-wrap justify-end gap-2">
                    <button
                        type="button"
                        disabled={uploadRunning}
                        onclick={cancelPhotoCrop}
                        class="text-foreground rounded-lg px-4 py-2 text-sm font-medium hover:bg-muted disabled:opacity-50"
                    >
                        Cancel
                    </button>
                    <button
                        type="button"
                        disabled={uploadRunning ||
                            (photoCropIsCorrection &&
                                photoCropTouchedCorners.length !== 4)}
                        onclick={confirmPhotoCrop}
                        class="rounded-lg bg-emerald-700 px-4 py-2 text-sm font-semibold text-white hover:bg-emerald-800 disabled:opacity-50"
                    >
                        {uploadRunning
                            ? 'Straightening…'
                            : pendingCropQueue.length > 1 &&
                                pendingCropQueue.indexOf(photoCropFile) <
                                    pendingCropQueue.length - 1 &&
                                photoCropTargetPageNo <
                                    Number(draft.requested_page_count)
                              ? `Straighten & Continue to Page ${photoCropTargetPageNo + 1}`
                              : Number(draft.requested_page_count) > 1
                                ? `Straighten Page ${photoCropTargetPageNo}`
                                : 'Straighten page'}
                    </button>
                </div>
            </div>
        </div>
    {/if}

    {#if showUploadedPageConfirmation}
        <div
            class="fixed inset-0 z-[70] flex items-center justify-center bg-slate-900/60 p-4 backdrop-blur-xs"
        >
            <div
                class="border-border bg-card max-h-[95vh] w-full max-w-md space-y-4 overflow-auto rounded-2xl border p-6 shadow-2xl"
            >
                <div>
                    <h3 class="text-foreground text-sm font-bold">
                        Confirm uploaded pages
                    </h3>
                    <p class="text-muted-foreground mt-1 text-xs">
                        The converter detected {pendingUploadedPages.length}
                        {pendingUploadedPages.length === 1
                            ? ' page'
                            : ' pages'}. This draft expects {draft.requested_page_count}.
                    </p>
                </div>
                <div
                    class="grid grid-cols-4 gap-2"
                    aria-label="Detected uploaded pages"
                >
                    {#each pendingUploadedPages as page}
                        <div
                            class="border-border bg-muted/40 rounded-lg border px-2 py-3 text-center text-xs font-bold"
                        >
                            Page {page.page_no}
                        </div>
                    {/each}
                </div>
                {#if photoCropPreviewUrl}
                    <div>
                        <img
                            src={photoCropPreviewUrl}
                            alt="Server-straightened answer sheet ready for page confirmation"
                            class="mx-auto max-h-[45vh] rounded-md border border-border"
                        />
                        <p class="text-muted-foreground mt-2 text-xs">
                            Check that all question bubbles and the ID area are visible. Close and upload again if the crop cut anything off.
                        </p>
                    </div>
                {/if}
                {#if pendingUploadedPages.length < Number(draft.requested_page_count)}
                    <div
                        class="rounded-xl border border-amber-200 bg-amber-50 p-3 text-xs text-amber-900"
                    >
                        {pendingUploadedPages.length} of {draft.requested_page_count} pages straightened.
                        Close this review and select or photograph the next page.
                    </div>
                {:else if pendingUploadedPages.length > Number(draft.requested_page_count)}
                    <div
                        class="rounded-xl border border-amber-200 bg-amber-50 p-3 text-xs text-amber-900"
                    >
                        Page-count mismatch. Close this review, change the draft
                        page count with the page controls, then upload the
                        document again.
                    </div>
                {/if}
                {#if conversionError}
                    <div
                        class="rounded-xl border border-rose-200 bg-rose-50 p-3 text-xs text-rose-900"
                    >
                        {conversionError}
                    </div>
                {/if}
                <div class="flex justify-end gap-2">
                    <button
                        type="button"
                        onclick={() => {
                            showUploadedPageConfirmation = false;
                            photoCropPreviewUrl = null;
                        }}
                        class="text-muted-foreground hover:bg-muted rounded-xl px-3.5 py-2 text-xs font-semibold"
                    >
                        Close
                    </button>
                    <button
                        type="button"
                        onclick={confirmUploadedPages}
                        disabled={uploadRunning ||
                            pendingUploadedPages.length !==
                                Number(draft.requested_page_count)}
                        class="rounded-xl bg-emerald-600 px-4 py-2 text-xs font-semibold text-white disabled:opacity-40"
                    >
                        Confirm {pendingUploadedPages.length} pages
                    </button>
                </div>
            </div>
        </div>
    {/if}

    <!-- Re-edit & Raster Reset Confirmation Modal -->
    {#if showResetConfirmModal}
        <div
            class="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/60 p-4 backdrop-blur-xs"
        >
            <div
                class="border-border bg-card text-foreground w-full max-w-md space-y-4 rounded-2xl border p-6 shadow-2xl"
            >
                <div class="flex items-center space-x-3">
                    <div
                        class="flex size-10 shrink-0 items-center justify-center rounded-xl bg-amber-500/15 text-amber-600 dark:text-amber-400"
                    >
                        <AlertTriangle class="size-5" />
                    </div>
                    <div>
                        <h3 class="text-foreground text-sm font-bold">
                            Re-edit Design & Reset Raster?
                        </h3>
                        <p class="text-muted-foreground mt-0.5 text-xs">
                            Returning to Step {pendingTargetStage} resets the print
                            sheet.
                        </p>
                    </div>
                </div>

                <p
                    class="border-border bg-muted/40 text-muted-foreground rounded-xl border p-3 text-xs leading-relaxed"
                >
                    Step 3 relies on a settled, composed print raster for
                    optical bubble calibration. Going back to modify HTML source
                    or overlays will invalidate the current raster image so you
                    can re-render a fresh document.
                </p>
                <div class="flex items-center justify-end space-x-2 pt-2">
                    <button
                        type="button"
                        onclick={() => {
                            showResetConfirmModal = false;
                            pendingTargetStage = null;
                        }}
                        class="text-muted-foreground hover:bg-muted hover:text-foreground rounded-xl px-3.5 py-2 text-xs font-semibold transition"
                    >
                        Cancel & Stay on Step {currentStage}
                    </button>
                    <button
                        type="button"
                        onclick={confirmResetAndNavigate}
                        class="rounded-xl bg-amber-600 px-4 py-2 text-xs font-semibold text-white shadow-xs transition hover:bg-amber-700"
                    >
                        Yes, Reset Raster & Re-edit
                    </button>
                </div>
            </div>
        </div>
    {/if}

    <!-- Discard Answer Sheet Draft Modal Dialog -->
    {#if discardDraftModalOpen}
        <div
            class="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/60 p-4 backdrop-blur-xs"
            role="presentation"
            onclick={(e) => {
                if (e.target === e.currentTarget && !discardDraftBusy) discardDraftModalOpen = false;
            }}
            onkeydown={(e) => {
                if (e.key === 'Escape' && !discardDraftBusy) discardDraftModalOpen = false;
            }}
        >
            <div
                class="border-border bg-card text-foreground w-full max-w-md space-y-4 rounded-2xl border p-6 shadow-2xl"
                role="dialog"
                aria-modal="true"
                aria-labelledby="studio-discard-modal-title"
                tabindex="-1"
            >
                <div class="flex items-center space-x-3">
                    <div
                        class="flex size-10 shrink-0 items-center justify-center rounded-xl bg-rose-500/15 text-rose-600 dark:text-rose-400"
                    >
                        <Trash2 class="size-5" />
                    </div>
                    <div>
                        <h3 id="studio-discard-modal-title" class="text-foreground text-sm font-bold">
                            Discard Answer Sheet Draft?
                        </h3>
                        <p class="text-muted-foreground mt-0.5 text-xs">
                            {draft.code} · {draft.name}
                        </p>
                    </div>
                </div>

                {#if discardDraftError}
                    <div class="flex items-start gap-2.5 rounded-xl border border-rose-500/30 bg-rose-500/10 p-3 text-xs text-rose-700 dark:text-rose-300">
                        <AlertTriangle class="size-4 shrink-0 mt-0.5" />
                        <span class="leading-relaxed">{discardDraftError}</span>
                    </div>
                {/if}

                <div class="border-border bg-muted/40 text-muted-foreground space-y-2 rounded-xl border p-3 text-xs leading-relaxed">
                    <p>Permanently discard this draft and exit the studio?</p>
                    <ul class="list-inside list-disc space-y-1 text-slate-700 dark:text-slate-300">
                        <li>All recognition bubble mappings and declared items will be deleted.</li>
                        <li>Uploaded source paper rasters will be removed from draft storage.</li>
                        <li>This action cannot be undone.</li>
                    </ul>
                </div>

                <div class="flex items-center justify-end space-x-2 pt-2">
                    <button
                        type="button"
                        onclick={() => (discardDraftModalOpen = false)}
                        disabled={discardDraftBusy}
                        class="text-muted-foreground hover:bg-muted hover:text-foreground rounded-xl px-3.5 py-2 text-xs font-semibold transition"
                    >
                        Cancel & Keep Draft
                    </button>
                    <button
                        type="button"
                        onclick={confirmDiscardDraft}
                        disabled={discardDraftBusy}
                        class="flex items-center gap-1.5 rounded-xl bg-rose-600 px-4 py-2 text-xs font-semibold text-white shadow-xs transition hover:bg-rose-700 disabled:opacity-50"
                    >
                        {#if discardDraftBusy}
                            <Loader2 class="size-3.5 animate-spin" />
                            <span>Discarding…</span>
                        {:else}
                            <Trash2 class="size-3.5" />
                            <span>Yes, Discard Draft</span>
                        {/if}
                    </button>
                </div>
            </div>
        </div>
    {/if}

    <!-- Mode Switch Confirmation Modal -->
    {#if showModeSwitchModal}
        <div
            class="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/60 p-4 backdrop-blur-xs"
        >
            <div
                class="border-border bg-card text-foreground w-full max-w-md space-y-4 rounded-2xl border p-6 shadow-2xl"
            >
                <div class="flex items-center space-x-3">
                    <div
                        class="flex size-10 shrink-0 items-center justify-center rounded-xl bg-amber-500/15 text-amber-600 dark:text-amber-400"
                    >
                        <AlertTriangle class="size-5" />
                    </div>
                    <div>
                        <h3 class="text-foreground text-sm font-bold">
                            Change creation mode?
                        </h3>
                        <p class="text-muted-foreground mt-0.5 text-xs">
                            Switching to {pendingTargetMode === 'html'
                                ? 'Sheet Designer'
                                : 'Upload Document'} will clear the current sheet
                            layout.
                        </p>
                    </div>
                </div>

                <p
                    class="border-border bg-muted/40 text-muted-foreground rounded-xl border p-3 text-xs leading-relaxed"
                >
                    Sheet Designer builds the answer sheet from scratch with
                    draggable OMR components. Upload Document ingests an
                    existing PDF, Word file, or image for overlay work.
                </p>

                <div class="flex items-center justify-end space-x-2 pt-2">
                    <button
                        type="button"
                        onclick={() => {
                            showModeSwitchModal = false;
                            pendingTargetMode = null;
                        }}
                        class="text-muted-foreground hover:bg-muted hover:text-foreground rounded-xl px-3.5 py-2 text-xs font-semibold transition"
                    >
                        Cancel
                    </button>
                    <button
                        type="button"
                        onclick={confirmSwitchMode}
                        class="rounded-xl bg-amber-600 px-4 py-2 text-xs font-semibold text-white shadow-xs transition hover:bg-amber-700"
                    >
                        Yes, Switch Mode
                    </button>
                </div>
            </div>
        </div>
    {/if}
</div>

<style>

    .studio-shell,
    .studio-workspace,
    .studio-topbar,
    .studio-sidebar,
    .studio-inspector,
    .studio-canvas-controls,
    .studio-verification-panel {
        min-width: 0;
        min-height: 0;
    }

    .studio-topbar [data-studio-editing-controls] {
        min-width: 0;
    }

    @media (max-width: 1023px) {
        .studio-topbar {
            left: calc(370px + 1rem);
            right: 1rem;
            flex-direction: column;
            align-items: flex-end;
            justify-content: flex-start;
            gap: 0.5rem;
        }

        .studio-topbar[data-sidebar-collapsed='true'] {
            left: 1rem;
            right: 1rem;
            flex-direction: row;
            align-items: flex-start;
            justify-content: space-between;
            flex-wrap: wrap;
        }

        .studio-topbar [data-studio-stage-navigation],
        .studio-topbar [data-studio-editing-controls] {
            max-width: 100%;
        }

        .studio-topbar [data-studio-editing-controls] {
            flex-wrap: wrap;
            justify-content: flex-end;
            gap: 0.25rem;
        }

        .studio-topbar [data-studio-history] {
            display: none;
        }

        .studio-topbar [data-studio-save-state] {
            gap: 0.25rem;
            padding-inline: 0.25rem;
        }

        .studio-topbar [data-studio-save-state] span {
            white-space: nowrap;
        }

        .studio-sidebar[data-studio-stage='4'] {
            z-index: 40;
        }

        .studio-canvas-controls {
            left: calc(370px + 1rem);
            right: 1rem;
            bottom: 5.5rem;
            max-width: none;
            flex-wrap: wrap;
            justify-content: flex-end;
            gap: 0.25rem;
            padding: 0.375rem;
        }

        .studio-canvas-controls[data-sidebar-collapsed='true'] {
            left: 1rem;
            right: auto;
            max-width: calc(100vw - 2rem);
        }


        .studio-canvas-controls [data-studio-canvas-divider] {
            display: none;
        }

        .studio-canvas-controls [data-studio-canvas-zoom],
        .studio-canvas-controls [data-studio-canvas-fit] {
            flex: 0 0 auto;
        }
    }

    @media (max-width: 639px) {
        .studio-sidebar {
            top: 0.5rem;
            right: 0.5rem;
            bottom: 0.5rem;
            left: 0.5rem;
            width: auto;
            max-width: none;
        }

        .studio-topbar,
        .studio-canvas-controls {
            left: 0.5rem;
            right: 0.5rem;
        }

        .studio-topbar {
            flex-direction: row;
            align-items: flex-start;
        }

        .studio-canvas-controls {
            bottom: 5.5rem;
        }
    }

</style>
