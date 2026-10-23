<script>
    import { Lock, Unlock, Loader2, Minus, Plus } from 'lucide-svelte';
    import {
        CANVAS_BUBBLE_DIAMETER_PX,
        CANVAS_RENDER_BUBBLE_PX,
        canvasRollBubbleGapPx,
        canvasRollRowGapPx,
        gridOptionLabels,
        isQuestionGridComponent,
        canvasComponentBlockSizeMm,
        componentBackgroundColor,
        componentInputBackgroundColor,
        GRID_SECTION_HEADER_HEIGHT_MM,
    } from '@/lib/omrComponents';
    import {
        buildSolidItemFromOptionPoints,
        cloneSolidItemAtAnchor,
        effectiveRecognitionGeometryMm,
        buildApplicantDigitRollFromPoints,
        interpolateOptionPointsFromSpan,
        interpolateTenDigitPointsFromSpan,
        mmToPx,
        pxToMm,
        multiplyItemsDownward,
        nextItemNumberForArea,
        normalizeOmrAreaCode,
        PX_PER_MM,
        recognitionOriginOffsetMm,
        verticalRollBubblePositionsPx,
        solveAutoGridFromTwoPoints,
        updateSolidItemOptionPoint,
        MIN_STUDIO_SCALE,
        MAX_STUDIO_SCALE,
        bubbleLabelLayout,
        buildSmartSnapSearchZoneOverlay,
        MIN_BUBBLE_MM,
        MIN_UPLOADED_BUBBLE_MM,
    } from '@/lib/omrStudioGeometry';
    let {
        scale = $bindable(1.0),
        panX = $bindable(0),
        panY = $bindable(0),
        marginMm = 15,
        markerInsetsMm = { top: 12, right: 12, bottom: 14, left: 12 },
        markerPattern = 'square',
        showFiducials = true,
        pageCount = 1,
        activePageNo = 1,
        interactionMode = 'pan',
        rasterUrl = null,
        showHeaderOverlay = true,
        headerTitle = 'EXAMINATION ANSWER SHEET',
        headerSubtitle = 'AUTOMATED OMR EVALUATION FORM',
        headerFontSize = $bindable(14.0),
        headerFontFamily = $bindable('Inter, sans-serif'),
        headerColor = $bindable('#0f172a'),
        headerAlign = $bindable('left'),
        headerX = $bindable(25),
        headerY = $bindable(20),
        headerW = $bindable(160),
        headerH = $bindable(15),
        headerLocked = false,
        showQrOverlay = true,
        qrX = $bindable(150),
        qrY = $bindable(32),
        qrW = $bindable(32),
        qrH = $bindable(32),
        qrLocked = false,
        showInstructionsOverlay = true,
        instructionsContent = 'Use 2B pencil only.',
        instructionsFontSize = $bindable(9.0),
        instructionsFontFamily = $bindable('Inter, sans-serif'),
        instructionsColor = $bindable('#334155'),
        instX = $bindable(25),
        instY = $bindable(250),
        instW = $bindable(90),
        instH = $bindable(28),
        instLocked = false,
        showSignatureOverlay = true,
        signatureContent = 'EXAMINEE SIGNATURE',
        signatureFontSize = $bindable(8.5),
        signatureFontFamily = $bindable('Inter, sans-serif'),
        signatureColor = $bindable('#475569'),
        sigX = $bindable(125),
        sigY = $bindable(250),
        sigW = $bindable(60),
        sigH = $bindable(28),
        sigLocked = false,
        customTextItems = $bindable([]),
        htmlContent = null,
        canonicalPreview = null,
        previewSurface = 'canonical',
        showCanonicalPrintPreview = false,
        mappingPreviewStale = false,
        isHtmlMode = true,
        isUploadedSource = false,
        isComposing = false,
        sections = $bindable([]),
        activeSectionIndex = $bindable(0),
        selectedSectionIndices = $bindable([]),
        components = $bindable([]),
        activeComponentId = $bindable(null),
        activeComponentIds = $bindable([]),
        activeItemId = $bindable(null),
        activeItemIds = $bindable([]),
        currentStage = 1,
        smartSnapSearchMm = 5,
        showSmartSnapSearchZone = true,
        previewingSearchZone = false,
        smartSnapSearchZoneScope = 'selected',
        plottingConfig = $bindable({
            active: false,
            mode: 'consecutive',
            areaCode: 'GENERAL',
            itemNumber: 1,
            optionsCount: 4,
            optionLabels: ['A', 'B', 'C', 'D'],
            bubbleDiameterMm: 4.5,
            currentOptionIndex: 0,
            pointsMm: [],
            respotSectionIndex: null,
            respotOptionIndex: 0,
            stampSourceSection: null,
        }),
        onGeometryChanged = null,
        onComponentGeometryChanged = null,
        onComponentSelected = null,
        onCanvasItemSelected = null,
        onCanvasSelectionClear = null,
        onDuplicateComponents = null,
        onUndo = null,
        onRedo = null,
        identity = $bindable(null),
        onIdentityChanged = null,
        documentFields = $bindable([]),
        activeDocumentFieldId = $bindable(null),
        onDocumentFieldsChanged = null,
        readOnly = false,
        sheetImageLoaded = $bindable(false),
    } = $props();
    const activeDocumentFields = $derived(
        documentFields.filter(
            (field) => Number(field.page_no ?? 1) === Number(activePageNo),
        ),
    );
    const pageComponents = $derived(
        components.filter(
            (component) => (Number(component.page_no) || 1) === activePageNo,
        ),
    );
    let inspectedQuestionIndex = $state(0);
    const paperWidthMm = $derived(Number(canonicalPreview?.artifact?.paper?.width_mm ?? 210));
    const paperHeightMm = $derived(Number(canonicalPreview?.artifact?.paper?.height_mm ?? 297));
    const smartSnapSearchZoneVisible = $derived(
        currentStage === 3 && previewSurface === 'canonical' &&
        sheetImageLoaded && !sheetImageError && Boolean(activeSheetImageUrl) &&
        (showSmartSnapSearchZone || previewingSearchZone)
    );
    const smartSnapSearchZone = $derived.by(() => smartSnapSearchZoneVisible
        ? buildSmartSnapSearchZoneOverlay(sections, {
            pageNo: activePageNo,
            paperWidthMm,
            paperHeightMm,
            searchRadiusMm: smartSnapSearchMm,
            minBubbleMm: isUploadedSource ? MIN_UPLOADED_BUBBLE_MM : MIN_BUBBLE_MM,
            activeSectionIndex,
            selectedSectionIndices,
            scope: smartSnapSearchZoneScope,
            inspectedQuestionIndex,
        })
        : null);
    const pageSections = $derived(
        sections.filter(
            (section) => (Number(section.page_no) || 1) === activePageNo,
        ),
    );

    let sheetImageError = $state(false);
    let activeRequestToken = 0;

    const activeSheetImageUrl = $derived.by(() => {
        // 1. If canonicalPreview has page-indexed pages, find activePageNo:
        const previewPages = canonicalPreview?.artifact?.pages;
        if (Array.isArray(previewPages) && previewPages.length > 0) {
            const match = previewPages.find(
                (p) => Number(p.page_no) === Number(activePageNo),
            );
            if (match?.canonical_url) {
                return match.canonical_url;
            }
        }

        // 2. If canonicalPreview has a legacy single canonical_url and activePageNo === 1:
        if (
            Number(activePageNo) === 1 &&
            canonicalPreview?.artifact?.canonical_url
        ) {
            return canonicalPreview.artifact.canonical_url;
        }

        // 3. Fallback to rasterUrl for the active page (only for non-HTML/uploaded mode)
        if (!isHtmlMode) {
            return rasterUrl ?? null;
        }

        return null;
    });

    let currentRenderedUrl = $state(null);
    let currentRenderedPageNo = $state(activePageNo);

    $effect(() => {
        const nextUrl = activeSheetImageUrl;
        const nextPage = activePageNo;
        if (
            nextUrl !== currentRenderedUrl ||
            nextPage !== currentRenderedPageNo
        ) {
            currentRenderedUrl = nextUrl;
            currentRenderedPageNo = nextPage;
            sheetImageLoaded = false;
            sheetImageError = false;
            const thisToken = ++activeRequestToken;

            if (nextUrl) {
                const img = new Image();
                img.onload = () => {
                    if (thisToken === activeRequestToken) {
                        sheetImageLoaded = true;
                        sheetImageError = false;
                    }
                };
                img.onerror = () => {
                    if (thisToken === activeRequestToken) {
                        sheetImageLoaded = false;
                        sheetImageError = true;
                    }
                };
                img.src = nextUrl;
                if (img.complete && img.naturalWidth > 0) {
                    sheetImageLoaded = true;
                    sheetImageError = false;
                }
            } else {
                sheetImageLoaded = true;
                sheetImageError = false;
            }
        }
    });
    const hasWriteIn = $derived(
        Boolean(
            identity?.has_write_in ??
            identity?.strategy === 'bubbles_and_write_in' ??
            identity?.strategy === 'write_in_only' ??
            identity?.method === 'write_in_manual' ??
            (Array.isArray(identity?.write_in_boxes) && identity.write_in_boxes.length > 0)
        )
    );

    const identityPages = $derived(
        Array.isArray(identity?.identity_pages) && identity.identity_pages.length > 0
            ? identity.identity_pages.map(Number)
            : Array.from({ length: pageCount || 1 }, (_, i) => i + 1)
    );

    const activeWriteInBox = $derived(
        hasWriteIn && identityPages.includes(activePageNo)
            ? ((identity?.write_in_boxes ?? []).find((b) => Number(b.page_no) === activePageNo) ?? null)
            : null
    );

    function removeActiveWriteInBox() {
        const boxes = (identity?.write_in_boxes ?? []).filter((b) => Number(b.page_no) !== activePageNo);
        identity = {
            ...identity,
            write_in_boxes: boxes,
        };
        selectedOverlay = null;
        if (onIdentityChanged) onIdentityChanged(identity);
        if (onGeometryChanged) onGeometryChanged();
    }

    function updateActiveWriteInBox(patch) {
        const boxes = [...(identity?.write_in_boxes ?? [])];
        const pageIdx = boxes.findIndex((b) => Number(b.page_no) === activePageNo);
        const existing = pageIdx >= 0 ? boxes[pageIdx] : { page_no: activePageNo, x_mm: 110, y_mm: 18, w_mm: 42, h_mm: 8.5 };
        const updated = { ...existing, ...patch, page_no: activePageNo };
        if (pageIdx >= 0) {
            boxes[pageIdx] = updated;
        } else {
            boxes.push(updated);
        }
        identity = {
            ...identity,
            write_in_boxes: boxes,
        };
        if (onIdentityChanged) {
            onIdentityChanged(identity);
        }
        if (onGeometryChanged) {
            onGeometryChanged();
        }
    }

    function handleWriteInMouseDown(e, box) {
        if (readOnly || currentStage !== 3) return;
        e.stopPropagation();
        selectedOverlay = 'applicant_write_in';
        selectedSectionIndices = [];
        activeSectionIndex = -1;
        isBlockDragging = true;
        dragBlockIndex = null;
        dragItemIds = [];
        dragStartX = e.clientX;
        dragStartY = e.clientY;
        blockStartGeometry = {
            x: Number(box.x_mm) || 110,
            y: Number(box.y_mm) || 18,
            w: Number(box.w_mm) || 42,
            h: Number(box.h_mm) || 8.5,
        };
    }

    function handleWriteInResizeMouseDown(e, box) {
        if (readOnly || currentStage !== 3) return;
        e.stopPropagation();
        selectedOverlay = 'applicant_write_in';
        isHandleDragging = true;
        dragBlockIndex = null;
        dragItemIds = [];
        dragStartX = e.clientX;
        dragStartY = e.clientY;
        blockStartGeometry = {
            x: Number(box.x_mm) || 110,
            y: Number(box.y_mm) || 18,
            w: Number(box.w_mm) || 42,
            h: Number(box.h_mm) || 8.5,
        };
    }
    let documentFieldDragId = $state(null);
    let documentFieldResizeHandle = $state(null);
    let documentFieldStartGeometry = null;
    let documentFieldStartPointer = null;

    function beginDocumentFieldPointerAction(e, field, resizeHandle = null) {
        if (readOnly || currentStage !== 3) return;
        e.stopPropagation();
        activeDocumentFieldId = field.id;
        selectedOverlay = null;
        selectedSectionIndices = [];
        activeSectionIndex = -1;
        documentFieldDragId = field.id;
        documentFieldResizeHandle = resizeHandle;
        documentFieldStartGeometry = {
            x: Number(field.x_mm) || 0,
            y: Number(field.y_mm) || 0,
            w: Number(field.w_mm) || 1,
            h: Number(field.h_mm) || 1,
        };
        documentFieldStartPointer = { x: e.clientX, y: e.clientY };
    }

    function updateDocumentFieldGeometry(fieldId, patch) {
        documentFields = documentFields.map((field) =>
            field.id === fieldId ? { ...field, ...patch } : field,
        );
        if (onDocumentFieldsChanged) {
            onDocumentFieldsChanged(documentFields);
        } else if (onGeometryChanged) {
            onGeometryChanged();
        }
    }
    const QR_CANVAS_ITEM_ID = 'qr';

    let isPanning = $state(false);
    let isSpaceHeld = $state(false);
    let isMarqueeSelecting = $state(false);
    let marqueeStartX = $state(0);
    let marqueeStartY = $state(0);
    let marqueeCurrentX = $state(0);
    let marqueeCurrentY = $state(0);
    let marqueeStartMm = $state({ x: 0, y: 0 });
    let marqueeStartingSelection = $state([]);
    let marqueeStartingSectionSelection = $state([]);
    let marqueeStyle = $derived.by(() => {
        const startX = marqueeStartMm.x;
        const startY = marqueeStartMm.y;
        const currentX =
            startX + (marqueeCurrentX - marqueeStartX) / (2.476 * scale);
        const currentY =
            startY + (marqueeCurrentY - marqueeStartY) / (2.476 * scale);
        return `left: ${Math.min(startX, currentX) * 2.476}px; top: ${Math.min(startY, currentY) * 2.476}px; width: ${Math.abs(currentX - startX) * 2.476}px; height: ${Math.abs(currentY - startY) * 2.476}px;`;
    });

    function handleCanvasKeyUp(e) {
        if (e.code === 'Space') isSpaceHeld = false;
    }

    function canvasItemIntersectsMarquee(item, bounds) {
        const left = Number(item.x) || 0;
        const top = Number(item.y) || 0;
        const right = left + Math.max(0, Number(item.width) || 0);
        const bottom = top + Math.max(0, Number(item.height) || 0);
        return (
            left <= bounds.maxX &&
            right >= bounds.minX &&
            top <= bounds.maxY &&
            bottom >= bounds.minY
        );
    }

    function selectMarqueeItems(e) {
        const endX =
            marqueeStartMm.x +
            (e.clientX - marqueeStartX) / (2.476 * scale);
        const endY =
            marqueeStartMm.y +
            (e.clientY - marqueeStartY) / (2.476 * scale);
        const bounds = {
            minX: Math.min(marqueeStartMm.x, endX),
            minY: Math.min(marqueeStartMm.y, endY),
            maxX: Math.max(marqueeStartMm.x, endX),
            maxY: Math.max(marqueeStartMm.y, endY),
        };
        if (currentStage === 3) {
            const intersecting = pageSections
                .map((section) => {
                    const guideSize = recognitionGuideSizeMm(section);
                    const originOffset = recognitionOriginOffsetMm(section);
                    const plottedBubbles =
                        Array.isArray(section.optionPointsMm) &&
                        section.optionPointsMm.length > 0 &&
                        (Number(section.questionsCount) || 1) === 1
                            ? localRecognitionBubbles(section)
                            : [];
                    const x =
                        plottedBubbles.length > 0
                            ? Math.min(...plottedBubbles.map((bubble) => bubble.x_mm))
                            : Number(section.posX) + originOffset.x;
                    const y =
                        plottedBubbles.length > 0
                            ? Math.min(...plottedBubbles.map((bubble) => bubble.y_mm))
                            : Number(section.posY) + originOffset.y;
                    const right =
                        plottedBubbles.length > 0
                            ? Math.max(
                                  ...plottedBubbles.map(
                                      (bubble) => bubble.x_mm + bubble.w_mm,
                                  ),
                              )
                            : x + guideSize.w_mm;
                    const bottom =
                        plottedBubbles.length > 0
                            ? Math.max(
                                  ...plottedBubbles.map(
                                      (bubble) => bubble.y_mm + bubble.h_mm,
                                  ),
                              )
                            : y + guideSize.h_mm;
                    return {
                        index: sections.findIndex(
                            (candidate) => candidate.id === section.id,
                        ),
                        x,
                        y,
                        width: right - x,
                        height: bottom - y,
                    };
                })
                .filter(
                    (section) =>
                        section.index >= 0 &&
                        canvasItemIntersectsMarquee(section, bounds),
                )
                .map((section) => section.index);
            const selection = new Set(marqueeStartingSectionSelection);
            if (e.ctrlKey || e.metaKey) {
                for (const index of intersecting) {
                    if (selection.has(index)) selection.delete(index);
                    else selection.add(index);
                }
            } else if (e.shiftKey) {
                for (const index of intersecting) selection.add(index);
            } else {
                selection.clear();
                for (const index of intersecting) selection.add(index);
            }
            selectedSectionIndices = [...selection].sort((a, b) => a - b);
            activeSectionIndex =
                selectedSectionIndices.at(-1) ?? -1;
            if (selectedSectionIndices.length > 0 || activeSectionIndex >= 0) {
                selectedOverlay = null;
            }
            return;
        }
        if (currentStage === 1 && isHtmlMode) {
            const intersecting = pageComponents
                .filter((component) => {
                    const size = canvasComponentBlockSizeMm(component);
                    return canvasItemIntersectsMarquee(
                        {
                            x: component.posX,
                            y: component.posY,
                            width: size.w_mm,
                            height: size.h_mm,
                        },
                        bounds,
                    );
                })
                .map((component) => component.id);
            const qrIsOnPage =
                showQrOverlay &&
                canvasItemIntersectsMarquee(
                    {
                        x: qrX,
                        y: qrY,
                        width: qrW,
                        height: qrH,
                    },
                    bounds,
                );
            if (qrIsOnPage) intersecting.push(QR_CANVAS_ITEM_ID);
            const selection = new Set(marqueeStartingSelection);
            if (e.ctrlKey || e.metaKey) {
                for (const id of intersecting) {
                    if (selection.has(id)) selection.delete(id);
                    else selection.add(id);
                }
            } else if (e.shiftKey) {
                for (const id of intersecting) selection.add(id);
            } else {
                selection.clear();
                for (const id of intersecting) selection.add(id);
            }
            activeItemIds = [...selection];
            activeComponentIds = activeItemIds.filter(
                (id) => id !== QR_CANVAS_ITEM_ID,
            );

            activeComponentId = activeComponentIds.at(-1) ?? null;
        }
    }
    let panStartX = 0;
    let panStartY = 0;

     // Dragging State (Stage 2/3)
     let isBlockDragging = $state(false);
     let isHandleDragging = $state(false);
    let isPitchDragging = $state(false);
    let pitchDragType = $state(null); // 'bubblePitch' | 'rowPitch'
    let pitchDragIndex = $state(null);
    let pitchDragStartValue = 0;
    let pitchDragStartPointer = 0;
    let sheetElement = $state(null);
    let cursorPaperMm = $state(null);
    $effect(() => {
        activeSectionIndex;
        activePageNo;
        inspectedQuestionIndex = 0;
    });
    let autoGridActive = $state(false);
    let autoGridFirstPointMm = $state(null);
    let autoGridError = $state('');
    let dragBlockIndex = $state(null);
    let selectedOverlay = $state('header');
    let dragStartX = 0;
    let dragStartY = 0;
    let blockStartGeometry = { x: 0, y: 0, w: 0, h: 0 };
 
     // Dragging & Resizing State (Stage 1 HTML Mode Universal Components)
     let isComponentDragging = $state(false);
     let isComponentResizing = $state(false);
     let dragCompId = $state(null);
     let dragComponentIds = $state([]);
     let dragItemIds = $state([]);
    let dragSectionIndices = $state([]);
    let sectionStartPositions = {};
    let sectionDragBounds = { minX: 0, minY: 0, maxX: 0, maxY: 0 };
     let componentStartPositions = {};
     let dragAxis = $state(null);
     let pendingShiftToggleId = $state(null);
     let dragMoved = $state(false);
     let isDuplicateDragging = $state(false);
     let isDragging = $derived(
         isBlockDragging ||
             isHandleDragging ||
            isPitchDragging ||
             isComponentDragging ||
            isComponentResizing,
    );
    let hasActiveSelection = $derived(
        currentStage === 1
            ? (activeItemIds?.length ?? 0) > 0
            : selectedSectionIndices.length > 0,
    );
    $effect(() => {
        if (
            currentStage === 3 &&
            (activeSectionIndex >= 0 || selectedSectionIndices.length > 0) &&
            selectedOverlay === 'applicant_write_in'
        ) {
            selectedOverlay = null;
        }
    });
    // Sanitize HTML preview content by stripping scripts and executable handlers
    let safeHtmlContent = $derived.by(() => {
        if (!htmlContent) return null;
        return htmlContent
            .replace(/<script\b[^<]*(?:(?!<\/script>)<[^<]*)*<\/script>/gi, '')
            .replace(/\son\w+\s*=\s*(['"]).*?\1/gi, '');
    });
    function setCanvasSelection(itemId, additive = false) {
        const currentSelection = Array.isArray(activeItemIds)
            ? activeItemIds
            : [];
        const fallbackSelection = additive
            ? currentSelection.includes(itemId)
                ? currentSelection.filter((id) => id !== itemId)
                : [...currentSelection, itemId]
            : [itemId];
        const callback =
            itemId === QR_CANVAS_ITEM_ID
                ? onCanvasItemSelected
                : onComponentSelected;
        const callbackSelection = callback ? callback(itemId, additive) : null;
        const nextSelection = Array.isArray(callbackSelection)
            ? callbackSelection
            : fallbackSelection;
        activeItemIds = nextSelection;
        activeComponentIds = nextSelection.filter(
            (id) => id !== QR_CANVAS_ITEM_ID,
        );
        activeComponentId = activeComponentIds.at(-1) ?? null;
        return nextSelection;
    }

    function captureCanvasItemPositions(ids) {
        return Object.fromEntries(
            ids
                .map((id) => {
                    if (id === QR_CANVAS_ITEM_ID) {
                        return [
                            id,
                            {
                                x: Number(qrX) || 10,
                                y: Number(qrY) || 10,
                                w: Number(qrW) || 26,
                                h: Number(qrH) || 26,
                            },
                        ];
                    }
                    const component = components.find(
                        (candidate) => candidate.id === id,
                    );
                    if (!component) return null;
                    return [
                        id,
                        {
                            x: Number(component.posX) || 10,
                            y: Number(component.posY) || 10,
                            w: Number(component.w_mm) || 40,
                            h: Number(component.h_mm) || 12,
                        },
                    ];
                })
                .filter(Boolean),
        );
    }

    function constrainedDragDelta(e) {
        let dXmm = (e.clientX - dragStartX) / (2.476 * scale);
        let dYmm = (e.clientY - dragStartY) / (2.476 * scale);
        if (Math.max(Math.abs(dXmm), Math.abs(dYmm)) >= 2) {
            dragMoved = true;
            pendingShiftToggleId = null;
        }
        if (!e.shiftKey) {
            dragAxis = null;
            return { dXmm, dYmm };
        }
        if (!dragAxis && Math.max(Math.abs(dXmm), Math.abs(dYmm)) >= 2) {
            dragAxis = Math.abs(dXmm) >= Math.abs(dYmm) ? 'x' : 'y';
        }
        if (dragAxis === 'x') dYmm = 0;
        if (dragAxis === 'y') dXmm = 0;
        return { dXmm, dYmm };
    }

    function moveCanvasItems(dXmm, dYmm) {
        const movableItems = dragItemIds
            .map((id) => {
                const start = componentStartPositions[id];
                if (!start) return null;
                if (id === QR_CANVAS_ITEM_ID) {
                    return qrLocked ? null : { id, start };
                }
                const component = components.find(
                    (candidate) => candidate.id === id,
                );
                return !component || component.isLocked
                    ? null
                    : { id, start, component };
            })
            .filter(Boolean);
        if (movableItems.length === 0) return;

        const minX = Math.min(...movableItems.map(({ start }) => start.x));
        const minY = Math.min(...movableItems.map(({ start }) => start.y));
        const maxX = Math.max(
            ...movableItems.map(({ start }) => start.x + start.w),
        );
        const maxY = Math.max(
            ...movableItems.map(({ start }) => start.y + start.h),
        );
        const clampedDX = Math.max(
            marginMm - minX,
            Math.min(210 - marginMm - maxX, dXmm),
        );
        const clampedDY = Math.max(
            marginMm - minY,
            Math.min(297 - marginMm - maxY, dYmm),
        );

        for (const { id, start, component } of movableItems) {
            const nextX = Math.round((start.x + clampedDX) * 10) / 10;
            const nextY = Math.round((start.y + clampedDY) * 10) / 10;
            if (id === QR_CANVAS_ITEM_ID) {
                qrX = nextX;
                qrY = nextY;
                continue;
            }
            component.posX = nextX;
            component.posY = nextY;
        }
    }

    function startDuplicateDrag(e, fallbackId = null) {
        if (!onDuplicateComponents) return [];
        const copies = onDuplicateComponents(
            fallbackId === QR_CANVAS_ITEM_ID ? null : fallbackId,
            { deferHistory: true },
        );
        if (!Array.isArray(copies) || copies.length === 0) return [];
        activeItemIds = copies.map((copy) => copy.id);
        activeComponentIds = [...activeItemIds];
        activeComponentId = activeComponentIds.at(-1) ?? null;
        dragItemIds = [...activeItemIds];
        dragComponentIds = [...activeComponentIds];
        componentStartPositions = Object.fromEntries(
            copies.map((copy) => [
                copy.id,
                {
                    x: Number(copy.posX) || 10,
                    y: Number(copy.posY) || 10,
                    w: Number(copy.w_mm) || 40,
                    h: Number(copy.h_mm) || 12,
                },
            ]),
        );
        dragAxis = null;
        pendingShiftToggleId = null;
        dragMoved = false;
        isDuplicateDragging = true;
        isComponentDragging = true;
        dragCompId = activeComponentId;
        dragStartX = e.clientX;
        dragStartY = e.clientY;
        const primary = copies.at(-1);
        blockStartGeometry = {
            x: Number(primary.posX) || 10,
            y: Number(primary.posY) || 10,
            w: Number(primary.w_mm) || 40,
            h: Number(primary.h_mm) || 12,
        };
        return copies;
    }

    function handleWheel(e) {
        e.preventDefault();
        const zoomFactor = e.deltaY < 0 ? 1.1 : 0.9;
        scale = Math.min(Math.max(MIN_STUDIO_SCALE, scale * zoomFactor), MAX_STUDIO_SCALE);
    }
    function resolveSheetClickMm(e) {
        const paperWidthMm = Number(
            canonicalPreview?.artifact?.paper?.width_mm ?? 210,
        );
        const paperHeightMm = Number(
            canonicalPreview?.artifact?.paper?.height_mm ?? 297,
        );
        const rect = sheetElement?.getBoundingClientRect?.();
        let clickXMm = 0;
        let clickYMm = 0;
        if (rect && rect.width > 0 && rect.height > 0) {
            clickXMm = ((e.clientX - rect.left) / rect.width) * paperWidthMm;
            clickYMm = ((e.clientY - rect.top) / rect.height) * paperHeightMm;
        } else {
            clickXMm = (e.clientX - panX) / (2.476 * scale);
            clickYMm = (e.clientY - panY) / (2.476 * scale);
        }
        return {
            x: Number(
                Math.max(0, Math.min(paperWidthMm, clickXMm)).toFixed(2),
            ),
            y: Number(
                Math.max(0, Math.min(paperHeightMm, clickYMm)).toFixed(2),
            ),
        };
    }

    function handlePlottingSheetClick(e) {
        if (!plottingConfig?.active || currentStage !== 3) return;
        e.stopPropagation();
        e.preventDefault();
        const pt = resolveSheetClickMm(e);
        const labels =
            Array.isArray(plottingConfig.optionLabels) &&
            plottingConfig.optionLabels.length >= 2
                ? plottingConfig.optionLabels
                : ['A', 'B', 'C', 'D'];

        if (plottingConfig.mode === 'respot_single') {
            const targetIdx =
                plottingConfig.respotSectionIndex ?? activeSectionIndex;
            const sec = sections[targetIdx];
            if (!sec || sec.isLocked) return;
            const updated = updateSolidItemOptionPoint(
                sec,
                Number(plottingConfig.respotOptionIndex) || 0,
                pt,
            );
            sections = sections.map((s, idx) =>
                idx === targetIdx ? updated : s,
            );
            plottingConfig = {
                ...plottingConfig,
                active: false,
                mode: 'consecutive',
            };
            if (onGeometryChanged) onGeometryChanged();
            return;
        }

        if (plottingConfig.mode === 'paste_stamp') {
            const source =
                plottingConfig.stampSourceSection ??
                sections[activeSectionIndex];
            if (!source) return;
            const targetSubtestCode =
                plottingConfig.subtest_code || source.subtest_code || undefined;
            const targetSubtestId =
                plottingConfig.test_instrument_subtest_id ??
                source.test_instrument_subtest_id;
            const targetArea = normalizeOmrAreaCode(
                targetSubtestCode || plottingConfig.areaCode || source.area_code,
            );
            const targetQ =
                Number(plottingConfig.itemNumber) > 0
                    ? Number(plottingConfig.itemNumber)
                    : nextItemNumberForArea(sections, targetArea, targetSubtestCode);
            const newId = `${targetArea}_P${activePageNo}_Q${targetQ}_${sections.length + 1}`;
            const cloned = cloneSolidItemAtAnchor(source, pt, {
                id: newId,
                pageNo: activePageNo,
                areaCode: targetArea,
                itemNumber: targetQ,
                subtest_code: targetSubtestCode,
                test_instrument_subtest_id:
                    targetSubtestId != null
                        ? Number(targetSubtestId)
                        : undefined,
            });
            sections = [...sections, cloned];
            activeSectionIndex = sections.length - 1;
            plottingConfig = {
                ...plottingConfig,
                itemNumber: nextItemNumberForArea(sections, targetArea, targetSubtestCode),
            };
            if (onGeometryChanged) onGeometryChanged();
            return;
        }

        if (plottingConfig.targetType === 'applicant_digit') {
            const digitLabels =
                Array.isArray(plottingConfig.optionLabels) &&
                plottingConfig.optionLabels.length === 10
                    ? plottingConfig.optionLabels
                    : ['0', '1', '2', '3', '4', '5', '6', '7', '8', '9'];

            // Mode A: 2-Click Span Shortcut (Click 0 then Click 9)
            if (plottingConfig.digitSpanMode) {
                if (!plottingConfig.pointsMm || plottingConfig.pointsMm.length === 0) {
                    plottingConfig = {
                        ...plottingConfig,
                        pointsMm: [{ label: '0', x: pt.x, y: pt.y }],
                        currentOptionIndex: 9,
                    };
                    return;
                }
                const p0 = plottingConfig.pointsMm[0];
                let p9X = pt.x;
                let p9Y = pt.y;
                if (e.shiftKey) {
                    if (Math.abs(p9Y - p0.y) >= Math.abs(p9X - p0.x)) {
                        p9X = p0.x;
                    } else {
                        p9Y = p0.y;
                    }
                }
                const interpolated = interpolateTenDigitPointsFromSpan(p0, { x: p9X, y: p9Y });
                const rollSection = buildApplicantDigitRollFromPoints({
                    pageNo: activePageNo,
                    digitCount: Number(plottingConfig.digitCount) || 6,
                    pointsMm: interpolated,
                    bubbleDiameterMm:
                        Number(plottingConfig.bubbleDiameterMm) ||
                        (isUploadedSource ? 3.0 : 4.5),
                    rollDirection:
                        plottingConfig.rollDirection ??
                        (Math.abs(p9Y - p0.y) >= Math.abs(p9X - p0.x)
                            ? 'vertical'
                            : 'horizontal'),
                    stepPitchMm: Number(plottingConfig.stepPitchMm) || 5.5,
                });

                sections = [
                    ...sections.filter(
                        (s) => !(s.identity_grid && Number(s.page_no) === activePageNo),
                    ),
                    rollSection,
                ];
                activeSectionIndex = sections.length - 1;
                selectedOverlay = null;
                plottingConfig = {
                    ...plottingConfig,
                    active: false,
                    pointsMm: [],
                    currentOptionIndex: 0,
                    targetType: undefined,
                    mode: 'consecutive',
                };
                if (onGeometryChanged) onGeometryChanged();
                return;
            }

            // Mode B: Consecutive 10 clicks (0 -> 1 -> 2 ... -> 9)
            const nextPoints = [...(plottingConfig.pointsMm ?? [])];
            const optIdx = Math.max(
                0,
                Math.min(
                    digitLabels.length - 1,
                    Number(plottingConfig.currentOptionIndex) || nextPoints.length,
                ),
            );
            let clickX = pt.x;
            let clickY = pt.y;
            if (e.shiftKey && optIdx > 0 && nextPoints[0]) {
                const first = nextPoints[0];
                if (Math.abs(pt.y - first.y) >= Math.abs(pt.x - first.x)) {
                    clickX = first.x;
                } else {
                    clickY = first.y;
                }
            }
            nextPoints[optIdx] = {
                label: digitLabels[optIdx],
                x: clickX,
                y: clickY,
            };
            const placedCount = nextPoints.filter((p) => p && Number.isFinite(p.x) && Number.isFinite(p.y)).length;
            if (placedCount < 10) {
                plottingConfig = {
                    ...plottingConfig,
                    pointsMm: nextPoints,
                    currentOptionIndex: optIdx + 1,
                };
                return;
            }

            // All 10 bubbles clicked -> Solidify into roll section!
            const rollSection = buildApplicantDigitRollFromPoints({
                pageNo: activePageNo,
                digitCount: Number(plottingConfig.digitCount) || 6,
                pointsMm: nextPoints,
                bubbleDiameterMm:
                    Number(plottingConfig.bubbleDiameterMm) ||
                    (isUploadedSource ? 3.0 : 4.5),
                rollDirection: plottingConfig.rollDirection,
                stepPitchMm: Number(plottingConfig.stepPitchMm) || 5.5,
            });

            sections = [
                ...sections.filter(
                    (s) => !(s.identity_grid && Number(s.page_no) === activePageNo),
                ),
                rollSection,
            ];
            activeSectionIndex = sections.length - 1;
            selectedOverlay = null;
            plottingConfig = {
                ...plottingConfig,
                active: false,
                pointsMm: [],
                currentOptionIndex: 0,
                targetType: undefined,
                mode: 'consecutive',
            };
            if (onGeometryChanged) onGeometryChanged();
            return;
        }

        // Mode A: 2-Click Span Shortcut for Question Items (Click Option A then Option D/E)
        if (plottingConfig.questionSpanMode) {
            if (!plottingConfig.pointsMm || plottingConfig.pointsMm.length === 0) {
                plottingConfig = {
                    ...plottingConfig,
                    pointsMm: [{ label: labels[0], x: pt.x, y: pt.y }],
                    currentOptionIndex: labels.length - 1,
                };
                return;
            }

            const p0 = plottingConfig.pointsMm[0];
            let lastY = pt.y;
            if (e.shiftKey && Number.isFinite(p0.y)) {
                lastY = Number(p0.y);
            }

            const interpolated = interpolateOptionPointsFromSpan(
                p0,
                { x: pt.x, y: lastY },
                labels,
            );

            const targetArea = normalizeOmrAreaCode(
                plottingConfig.subtest_code || plottingConfig.areaCode,
            );
            const targetQ =
                Number(plottingConfig.itemNumber) > 0
                    ? Number(plottingConfig.itemNumber)
                    : nextItemNumberForArea(sections, targetArea, plottingConfig.subtest_code);
            const newId = `${targetArea}_P${activePageNo}_Q${targetQ}_${sections.length + 1}`;
            const solidItem = buildSolidItemFromOptionPoints({
                id: newId,
                pageNo: activePageNo,
                areaCode: targetArea,
                itemNumber: targetQ,
                subtest_code: plottingConfig.subtest_code || undefined,
                test_instrument_subtest_id:
                    plottingConfig.test_instrument_subtest_id != null
                        ? Number(plottingConfig.test_instrument_subtest_id)
                        : undefined,
                optionLabels: labels,
                pointsMm: interpolated.map((p) => ({ x: p.x, y: p.y })),
                bubbleDiameterMm:
                    Number(plottingConfig.bubbleDiameterMm) ||
                    (isUploadedSource ? 3.0 : 4.5),
                minBubbleMm: isUploadedSource ? 2.5 : 4.2,
            });

            sections = [...sections, solidItem];
            activeSectionIndex = sections.length - 1;
            selectedSectionIndices = [activeSectionIndex];
            selectedOverlay = null;
            plottingConfig = {
                ...plottingConfig,
                pointsMm: [],
                currentOptionIndex: 0,
                itemNumber: nextItemNumberForArea(sections, targetArea, plottingConfig.subtest_code),
            };
            if (onGeometryChanged) onGeometryChanged();
            return;
        }

        // Mode B: consecutive option-by-option plotting (A -> B -> C -> D)
        const nextPoints = [...(plottingConfig.pointsMm ?? [])];
        const optIdx = Math.max(
            0,
            Math.min(
                labels.length - 1,
                Number(plottingConfig.currentOptionIndex) || nextPoints.length,
            ),
        );
        let clickY = pt.y;
        if (
            e.shiftKey &&
            optIdx > 0 &&
            nextPoints[0] &&
            Number.isFinite(nextPoints[0].y)
        ) {
            clickY = Number(nextPoints[0].y);
        }
        nextPoints[optIdx] = {
            label: labels[optIdx],
            x: pt.x,
            y: clickY,
        };
        const filledCount = nextPoints.filter(
            (p) => p && Number.isFinite(p.x) && Number.isFinite(p.y),
        ).length;
        if (filledCount < labels.length) {
            plottingConfig = {
                ...plottingConfig,
                pointsMm: nextPoints,
                currentOptionIndex: optIdx + 1,
            };
            return;
        }

        // All options clicked -> Solidify item component!
        const targetArea = normalizeOmrAreaCode(
            plottingConfig.subtest_code || plottingConfig.areaCode,
        );
        const targetQ =
            Number(plottingConfig.itemNumber) > 0
                ? Number(plottingConfig.itemNumber)
                : nextItemNumberForArea(sections, targetArea, plottingConfig.subtest_code);
        const newId = `${targetArea}_P${activePageNo}_Q${targetQ}_${sections.length + 1}`;
        const solidItem = buildSolidItemFromOptionPoints({
            id: newId,
            pageNo: activePageNo,
            areaCode: targetArea,
            itemNumber: targetQ,
            subtest_code: plottingConfig.subtest_code || undefined,
            test_instrument_subtest_id:
                plottingConfig.test_instrument_subtest_id != null
                    ? Number(plottingConfig.test_instrument_subtest_id)
                    : undefined,
            optionLabels: labels,
            pointsMm: nextPoints.map((p) => ({ x: p.x, y: p.y })),
            bubbleDiameterMm:
                Number(plottingConfig.bubbleDiameterMm) ||
                (isUploadedSource ? 3.0 : 4.5),
            minBubbleMm: isUploadedSource ? 2.5 : 4.2,
        });

        sections = [...sections, solidItem];
        activeSectionIndex = sections.length - 1;
        selectedOverlay = null;
        plottingConfig = {
            ...plottingConfig,
            pointsMm: [],
            currentOptionIndex: 0,
            itemNumber: nextItemNumberForArea(sections, targetArea, plottingConfig.subtest_code),
            active: false,
        };
        if (onGeometryChanged) onGeometryChanged();
    }

    function startPasteStampFromSection(sec) {
        if (!sec) return;
        const targetSubtestCode =
            plottingConfig.subtest_code || sec.subtest_code || undefined;
        const targetArea = normalizeOmrAreaCode(
            targetSubtestCode || plottingConfig.areaCode || sec.area_code,
        );
        plottingConfig = {
            ...plottingConfig,
            stampSourceSection: JSON.parse(JSON.stringify(sec)),
            areaCode: targetArea,
            subtest_code: targetSubtestCode,
            itemNumber: nextItemNumberForArea(sections, targetArea, targetSubtestCode),
            mode: 'paste_stamp',
            active: true,
        };
    }

    function deleteSectionAtIndex(idx) {
        if (idx == null || idx < 0 || idx >= sections.length) return;
        if (sections[idx]?.isLocked) return;
        sections = sections.filter((_, i) => i !== idx);
        activeSectionIndex = Math.max(0, Math.min(idx, sections.length - 1));
        if (plottingConfig) {
            plottingConfig = {
                ...plottingConfig,
                itemNumber: nextItemNumberForArea(
                    sections,
                    plottingConfig.areaCode,
                    plottingConfig.subtest_code,
                ),
            };
        }
        if (onGeometryChanged) onGeometryChanged();
    }

    function handleCanvasMouseDown(e) {
        if (e.target.closest('button, input, select, textarea')) return;
        if (!readOnly && currentStage === 3 && plottingConfig?.active && e.button === 0) {
            handlePlottingSheetClick(e);
            return;
        }
        if (!readOnly && autoGridActive && currentStage === 3) {
            handleAutoGridSheetClick(e);
            return;
        }
        const emptyBackground = !e.target?.closest?.(
            '[data-omr-component-id], [data-omr-canvas-item-id], [data-omr-section-id], [data-omr-pitch-handle], [data-omr-write-in-box], [data-omr-document-field], [data-omr-document-field-handle]',
        );
        if (emptyBackground && currentStage === 3) {
            marqueeStartingSectionSelection =
                selectedSectionIndices.length > 0
                    ? [...selectedSectionIndices]
                    : activeSectionIndex >= 0
                      ? [activeSectionIndex]
                      : [];
            selectedOverlay = null;
            if (!e.shiftKey && !e.ctrlKey && !e.metaKey) {
                selectedSectionIndices = [];
                activeSectionIndex = -1;
            }
        }
        if (
            !emptyBackground ||
            isBlockDragging ||
            isHandleDragging ||
            isPitchDragging ||
            isComponentDragging ||
            isComponentResizing ||
            documentFieldDragId !== null
        ) {
            return;
        }
        if (e.button === 1 || (e.button === 0 && (isSpaceHeld || interactionMode === 'pan'))) {
            e.preventDefault();
            isPanning = true;
            panStartX = e.clientX - panX;
            panStartY = e.clientY - panY;
            return;
        }
        if (e.button !== 0) return;
        isMarqueeSelecting = true;
        marqueeStartX = e.clientX;
        marqueeStartY = e.clientY;
        marqueeCurrentX = e.clientX;
        marqueeCurrentY = e.clientY;
        marqueeStartMm = resolveSheetClickMm(e);
        marqueeStartingSelection = [...(activeItemIds ?? [])];
        if (currentStage !== 3) {
            marqueeStartingSectionSelection =
                selectedSectionIndices.length > 0
                    ? [...selectedSectionIndices]
                    : activeSectionIndex >= 0
                      ? [activeSectionIndex]
                      : [];
        }
        if (currentStage === 1) {
            activeItemIds = [];
            activeComponentId = null;
            activeComponentIds = [];
            selectedOverlay = null;
        }
    }


    function handleCanvasMouseMove(e) {
        if (currentStage === 3) {
            cursorPaperMm = resolveSheetClickMm(e);
            if (
                e.shiftKey &&
                plottingConfig?.active &&
                Array.isArray(plottingConfig.pointsMm) &&
                plottingConfig.pointsMm[0] &&
                (Number(plottingConfig.currentOptionIndex) || 0) > 0
            ) {
                const p0 = plottingConfig.pointsMm[0];
                if (plottingConfig.targetType === 'applicant_digit') {
                    if (
                        Math.abs(cursorPaperMm.y - p0.y) >=
                        Math.abs(cursorPaperMm.x - p0.x)
                    ) {
                        cursorPaperMm = {
                            x: Number(p0.x),
                            y: cursorPaperMm.y,
                        };
                    } else {
                        cursorPaperMm = {
                            x: cursorPaperMm.x,
                            y: Number(p0.y),
                        };
                    }
                } else if (Number.isFinite(p0.y)) {
                    cursorPaperMm = {
                        x: cursorPaperMm.x,
                        y: Number(p0.y),
                    };
                }
            }
        }
        if (isMarqueeSelecting) {
            marqueeCurrentX = e.clientX;
            marqueeCurrentY = e.clientY;
            if (
                Math.hypot(
                    e.clientX - marqueeStartX,
                    e.clientY - marqueeStartY,
                ) >= 3
            ) {
                selectMarqueeItems(e);
            }
            return;
        }
        if (isPanning) {
            panX = e.clientX - panStartX;
            panY = e.clientY - panStartY;
            return;
        }
        if (isPitchDragging && pitchDragIndex !== null) {
            const sec = sections[pitchDragIndex];
            if (!sec) return;
            if (pitchDragType === 'bubblePitch') {
                const dXpx = e.clientX - pitchDragStartPointer;
                const dXmm = dXpx / (2.476 * scale);
                const count = sec.type === 'roll'
                    ? Math.max(1, Number(sec.questionsCount) || 7) - 1
                    : Math.max(1, getOptionLabels(sec).length - 1);
                const deltaPerPitch = count > 0 ? dXmm / count : dXmm;
                const nextPitch = Math.max(
                    3.0,
                    Math.min(
                        25.0,
                        Math.round((pitchDragStartValue + deltaPerPitch) * 100) /
                            100,
                    ),
                );
                if (sec.bubblePitchMm !== nextPitch) {
                    sec.bubblePitchMm = nextPitch;
                    if (onGeometryChanged) onGeometryChanged();
                }
            } else if (pitchDragType === 'rowPitch') {
                const dYpx = e.clientY - pitchDragStartPointer;
                const dYmm = dYpx / (2.476 * scale);
                const rows =
                    sec.type === 'roll'
                        ? 9
                        : Math.max(1, Number(sec.questionsCount) || 1) - 1;
                const deltaPerRow = rows > 0 ? dYmm / rows : dYmm;
                const nextPitch = Math.max(
                    3.0,
                    Math.min(
                        30.0,
                        Math.round((pitchDragStartValue + deltaPerRow) * 100) /
                            100,
                    ),
                );
                if (sec.rowPitchMm !== nextPitch) {
                    sec.rowPitchMm = nextPitch;
                    if (onGeometryChanged) onGeometryChanged();
                }
            }
            return;
        }

        if (documentFieldDragId !== null && documentFieldStartGeometry && documentFieldStartPointer) {
            const field = documentFields.find((item) => item.id === documentFieldDragId);
            if (!field) return;
            const dx = (e.clientX - documentFieldStartPointer.x) / (2.476 * scale);
            const dy = (e.clientY - documentFieldStartPointer.y) / (2.476 * scale);
            const start = documentFieldStartGeometry;
            const minW = 1;
            const minH = 1;
            const maxX = 210;
            const maxY = 297;
            let x = start.x;
            let y = start.y;
            let w = start.w;
            let h = start.h;

            if (!documentFieldResizeHandle) {
                x = Math.max(0, Math.min(maxX - w, start.x + dx));
                y = Math.max(0, Math.min(maxY - h, start.y + dy));
            } else {
                const handle = documentFieldResizeHandle;
                if (handle.includes('w')) {
                    x = Math.max(0, Math.min(start.x + start.w - minW, start.x + dx));
                    w = start.x + start.w - x;
                } else {
                    w = Math.max(minW, Math.min(maxX - start.x, start.w + dx));
                }
                if (handle.includes('n')) {
                    y = Math.max(0, Math.min(start.y + start.h - minH, start.y + dy));
                    h = start.y + start.h - y;
                } else {
                    h = Math.max(minH, Math.min(maxY - start.y, start.h + dy));
                }
            }
            updateDocumentFieldGeometry(documentFieldDragId, {
                x_mm: Math.round(x * 10) / 10,
                y_mm: Math.round(y * 10) / 10,
                w_mm: Math.round(w * 10) / 10,
                h_mm: Math.round(h * 10) / 10,
            });
            return;
        }

        const rawDXmm = (e.clientX - dragStartX) / (2.476 * scale);
        const rawDYmm = (e.clientY - dragStartY) / (2.476 * scale);
        const isDraggingCanvasItem =
            isBlockDragging ||
            isHandleDragging ||
            isComponentDragging ||
            isComponentResizing ||
            dragItemIds.length > 0;
        const { dXmm, dYmm } =
            isDraggingCanvasItem && !isComponentResizing
                ? constrainedDragDelta(e)
                : { dXmm: rawDXmm, dYmm: rawDYmm };

        if (
            isHtmlMode &&
            currentStage === 1 &&
            dragItemIds.length > 0 &&
            !isComponentResizing &&
            (isComponentDragging || isBlockDragging)
        ) {
            moveCanvasItems(dXmm, dYmm);
            return;
        }

        // STAGE 1 (HTML Mode): Text Component Resize
        if (
            isHtmlMode &&
            currentStage === 1 &&
            isComponentResizing &&
            dragCompId
        ) {
            const primary = components.find(
                (component) => component.id === dragCompId,
            );
            if (!primary || primary.isLocked) return;
            primary.w_mm = Math.max(
                20,
                Math.min(
                    210 - marginMm - primary.posX,
                    Math.round(blockStartGeometry.w + dXmm),
                ),
            );
            primary.h_mm = Math.max(
                8,
                Math.min(
                    297 - marginMm - primary.posY,
                    Math.round(blockStartGeometry.h + dYmm),
                ),
            );
            return;
        }

        if (currentStage === 2 || (isHtmlMode && currentStage === 1)) {
            if (isBlockDragging) {
                if (selectedOverlay === 'header' && !headerLocked) {
                    headerX = Math.max(
                        marginMm,
                        Math.min(
                            210 - marginMm - headerW,
                            Math.round(blockStartGeometry.x + dXmm),
                        ),
                    );
                    headerY = Math.max(
                        marginMm,
                        Math.min(
                            297 - marginMm - headerH,
                            Math.round(blockStartGeometry.y + dYmm),
                        ),
                    );
                } else if (selectedOverlay === 'qr' && !qrLocked) {
                    qrX = Math.max(
                        marginMm,
                        Math.min(
                            210 - marginMm - qrW,
                            Math.round(blockStartGeometry.x + dXmm),
                        ),
                    );
                    qrY = Math.max(
                        marginMm,
                        Math.min(
                            297 - marginMm - qrH,
                            Math.round(blockStartGeometry.y + dYmm),
                        ),
                    );
                } else if (selectedOverlay === 'signature' && !sigLocked) {
                    sigX = Math.max(
                        marginMm,
                        Math.min(
                            210 - marginMm - sigW,
                            Math.round(blockStartGeometry.x + dXmm),
                        ),
                    );
                    sigY = Math.max(
                        marginMm,
                        Math.min(
                            297 - marginMm - sigH,
                            Math.round(blockStartGeometry.y + dYmm),
                        ),
                    );
                } else if (selectedOverlay === 'instructions' && !instLocked) {
                    instX = Math.max(
                        marginMm,
                        Math.min(
                            210 - marginMm - instW,
                            Math.round(blockStartGeometry.x + dXmm),
                        ),
                    );
                    instY = Math.max(
                        marginMm,
                        Math.min(
                            297 - marginMm - instH,
                            Math.round(blockStartGeometry.y + dYmm),
                        ),
                    );
                } else if (
                    typeof selectedOverlay === 'number' &&
                    customTextItems[selectedOverlay] &&
                    !customTextItems[selectedOverlay].isLocked
                ) {
                    const item = customTextItems[selectedOverlay];
                    item.posX = Math.max(
                        marginMm,
                        Math.min(
                            210 - marginMm - item.w_mm,
                            Math.round(blockStartGeometry.x + dXmm),
                        ),
                    );
                    item.posY = Math.max(
                        marginMm,
                        Math.min(
                            297 - marginMm - item.h_mm,
                            Math.round(blockStartGeometry.y + dYmm),
                        ),
                    );
                }
            } else if (isHandleDragging) {
                if (selectedOverlay === 'header' && !headerLocked) {
                    headerW = Math.max(
                        40,
                        Math.min(
                            210 - headerX - marginMm,
                            Math.round(blockStartGeometry.w + rawDXmm),
                        ),
                    );
                    headerH = Math.max(
                        12,
                        Math.min(
                            297 - headerY - marginMm,
                            Math.round(blockStartGeometry.h + rawDYmm),
                        ),
                    );
                } else if (selectedOverlay === 'qr' && !qrLocked) {
                    const newSize = Math.max(
                        20,
                        Math.min(
                            50,
                            Math.round(blockStartGeometry.w + rawDXmm),
                        ),
                    );
                    qrW = newSize;
                    qrH = newSize;
                } else if (selectedOverlay === 'signature' && !sigLocked) {
                    sigW = Math.max(
                        30,
                        Math.min(
                            210 - sigX - marginMm,
                            Math.round(blockStartGeometry.w + rawDXmm),
                        ),
                    );
                    sigH = Math.max(
                        15,
                        Math.min(
                            297 - sigY - marginMm,
                            Math.round(blockStartGeometry.h + rawDYmm),
                        ),
                    );
                } else if (selectedOverlay === 'instructions' && !instLocked) {
                    instW = Math.max(
                        40,
                        Math.min(
                            210 - instX - marginMm,
                            Math.round(blockStartGeometry.w + rawDXmm),
                        ),
                    );
                    instH = Math.max(
                        15,
                        Math.min(
                            297 - instY - marginMm,
                            Math.round(blockStartGeometry.h + rawDYmm),
                        ),
                    );
                }
            }
            return;
        }

        // Stage 3: Handwritten Applicant Box drag & resize
        if (currentStage === 3 && selectedOverlay === 'applicant_write_in') {
            if (isBlockDragging) {
                if (!dragMoved && Math.hypot(dXmm, dYmm) < 0.15) return;
                dragMoved = true;
                const nextX = Math.max(
                    0,
                    Math.min(
                        210 - blockStartGeometry.w,
                        Math.round(blockStartGeometry.x + dXmm),
                    ),
                );
                const nextY = Math.max(
                    0,
                    Math.min(
                        297 - blockStartGeometry.h,
                        Math.round(blockStartGeometry.y + dYmm),
                    ),
                );
                updateActiveWriteInBox({ x_mm: nextX, y_mm: nextY });
                return;
            } else if (isHandleDragging) {
                const nextW = Math.max(
                    10,
                    Math.min(
                        210 - blockStartGeometry.x,
                        Math.round(blockStartGeometry.w + rawDXmm),
                    ),
                );
                const nextH = Math.max(
                    4,
                    Math.min(
                        297 - blockStartGeometry.y,
                        Math.round(blockStartGeometry.h + rawDYmm),
                    ),
                );
                updateActiveWriteInBox({ w_mm: nextW, h_mm: nextH });
                return;
            }
        }
        // Stage 3 moves recognition geometry over a fixed server-owned raster.
        if (
            currentStage === 3 &&
            isBlockDragging &&
            dragSectionIndices.length > 0
        ) {
            if (!dragMoved && Math.hypot(dXmm, dYmm) < 0.15) {
                return;
            }
            const paperW = Number(canonicalPreview?.artifact?.paper?.width_mm) || 210;
            const paperH = Number(canonicalPreview?.artifact?.paper?.height_mm) || 297;
            const { minX, minY, maxX, maxY } = sectionDragBounds;
            const minAllowedDX = -minX;
            const maxAllowedDX = Math.max(minAllowedDX, paperW - maxX);
            const minAllowedDY = -minY;
            const maxAllowedDY = Math.max(minAllowedDY, paperH - maxY);
            const clampedDX = Math.max(minAllowedDX, Math.min(maxAllowedDX, dXmm));
            const clampedDY = Math.max(minAllowedDY, Math.min(maxAllowedDY, dYmm));
            for (const index of dragSectionIndices) {
                const sec = sections[index];
                const start = sectionStartPositions[index];
                if (!sec || !start) continue;
                const nextX = start.posX + clampedDX;
                const nextY = start.posY + clampedDY;
                sec.posX = Number(nextX.toFixed(2));
                sec.posY = Number(nextY.toFixed(2));
                if (Array.isArray(start.optionPointsMm)) {
                    sec.optionPointsMm = start.optionPointsMm.map((startPt) => {
                        if (Array.isArray(startPt)) {
                            return [
                                Number((startPt[0] + clampedDX).toFixed(2)),
                                Number((startPt[1] + clampedDY).toFixed(2)),
                            ];
                        }
                        const ptX = Number(startPt.x_mm ?? startPt.x) || 0;
                        const ptY = Number(startPt.y_mm ?? startPt.y) || 0;
                        const nextPtX = Number((ptX + clampedDX).toFixed(2));
                        const nextPtY = Number((ptY + clampedDY).toFixed(2));
                        return {
                            ...startPt,
                            ...(startPt.x !== undefined ? { x: nextPtX } : {}),
                            ...(startPt.y !== undefined ? { y: nextPtY } : {}),
                            x_mm: nextPtX,
                            y_mm: nextPtY,
                        };
                    });
                }
            }
            dragMoved = true;
            return;
        }
    }

    function handleCanvasMouseUp() {
        if (isMarqueeSelecting) {
            const wasClick =
                Math.hypot(
                    marqueeCurrentX - marqueeStartX,
                    marqueeCurrentY - marqueeStartY,
                ) < 3;
            isMarqueeSelecting = false;
            if (wasClick) {
                if (currentStage === 3) {
                    selectedSectionIndices = [];
                    activeSectionIndex = -1;
                    selectedOverlay = null;
                } else {
                    activeItemIds = [];
                    activeComponentId = null;
                    activeComponentIds = [];
                    onCanvasSelectionClear?.();
                }
            }
            marqueeStartingSelection = [];
            marqueeStartingSectionSelection = [];
        }
        if (documentFieldDragId !== null) {
            documentFieldDragId = null;
            documentFieldResizeHandle = null;
            documentFieldStartGeometry = null;
            documentFieldStartPointer = null;
        }
        if (isPanning) isPanning = false;
        const hadBlockDrag = isBlockDragging || isHandleDragging;
        const hadComponentDrag = isComponentDragging || isComponentResizing;
        const shouldSyncStage1Items =
            isHtmlMode && currentStage === 1 && dragItemIds.length > 0;
        const pendingToggle = pendingShiftToggleId;
        const shouldToggleSelection = pendingToggle !== null && !dragMoved;
        if (isPitchDragging) {
            isPitchDragging = false;
            pitchDragType = null;
            pitchDragIndex = null;
            if (onGeometryChanged) onGeometryChanged();
        }
        if (hadBlockDrag) {
            isBlockDragging = false;
            isHandleDragging = false;
            dragBlockIndex = null;
            dragSectionIndices = [];
            sectionStartPositions = {};
            sectionDragBounds = { minX: 0, minY: 0, maxX: 0, maxY: 0 };
            dragItemIds = [];
            dragComponentIds = [];
            componentStartPositions = {};
            dragAxis = null;
            isDuplicateDragging = false;
            if (
                dragMoved &&
                shouldSyncStage1Items &&
                onComponentGeometryChanged
            )
                onComponentGeometryChanged();
            else if (dragMoved && onGeometryChanged) onGeometryChanged();
        }
        if (hadComponentDrag) {
            isComponentDragging = false;
            isComponentResizing = false;
            dragCompId = null;
            dragComponentIds = [];
            dragItemIds = [];
            componentStartPositions = {};
            dragAxis = null;
            isDuplicateDragging = false;
            if (onComponentGeometryChanged) onComponentGeometryChanged();
        }
        if (shouldToggleSelection) setCanvasSelection(pendingToggle, true);
        pendingShiftToggleId = null;
        dragMoved = false;
    }

    // Universal Component Drag & Resize Triggers (Stage 1 HTML Mode)
    function handleComponentMouseDown(e, compId) {
        if (readOnly || currentStage !== 1 || !isHtmlMode) return;
        e.stopPropagation();
        const source = components.find((component) => component.id === compId);
        if (!source) return;

        if (e.altKey && !source.isLocked) {
            const copies = startDuplicateDrag(e, compId);
            if (copies.length > 0) return;
        }

        const currentSelection = Array.isArray(activeItemIds)
            ? activeItemIds
            : [];
        const isModifier = e.shiftKey || e.ctrlKey || e.metaKey;
        const preserveSelection =
            currentSelection.includes(compId) &&
            (isModifier || currentSelection.length > 1);
        const nextSelection = preserveSelection
            ? currentSelection
            : setCanvasSelection(compId, isModifier);
        pendingShiftToggleId =
            isModifier && currentSelection.includes(compId) ? compId : null;
        dragMoved = false;
        if (!nextSelection.includes(compId) || source.isLocked) return;

        isDuplicateDragging = false;
        isComponentDragging = true;
        dragCompId = compId;
        dragItemIds = [...nextSelection];
        dragComponentIds = nextSelection.filter(
            (id) => id !== QR_CANVAS_ITEM_ID,
        );
        componentStartPositions = captureCanvasItemPositions(nextSelection);
        dragAxis = null;
        dragStartX = e.clientX;
        dragStartY = e.clientY;
        blockStartGeometry = {
            x: Number(source.posX) || 10,
            y: Number(source.posY) || 10,
            w: Number(source.w_mm) || 40,
            h: Number(source.h_mm) || 12,
        };
    }
    function handleComponentResizeMouseDown(e, compId) {
        if (readOnly || currentStage !== 1 || !isHtmlMode) return;
        e.stopPropagation();
        setCanvasSelection(compId);
        const comp = components.find((c) => c.id === compId);
        if (!comp || comp.isLocked) return;

        isDuplicateDragging = false;
        isComponentResizing = true;
        dragCompId = compId;
        dragItemIds = [compId];
        dragComponentIds = [compId];
        dragAxis = null;
        pendingShiftToggleId = null;
        dragMoved = false;
        dragStartX = e.clientX;
        dragStartY = e.clientY;
        blockStartGeometry = {
            x: Number(comp.posX) || 10,
            y: Number(comp.posY) || 10,
            w: Number(comp.w_mm) || 40,
            h: Number(comp.h_mm) || 12,
        };
    }

    // Overlay Drag & Resize Triggers (Stage 2)
    function handleOverlayMouseDown(e, target, geometry, isLocked = false) {
        if (readOnly || !(currentStage === 2 || (isHtmlMode && currentStage === 1))) return;
        e.stopPropagation();
        const itemId = target === 'qr' ? QR_CANVAS_ITEM_ID : null;
        const currentSelection = Array.isArray(activeItemIds)
            ? activeItemIds
            : [];
        const preserveSelection =
            itemId &&
            currentSelection.includes(itemId) &&
            (e.shiftKey || e.altKey || currentSelection.length > 1);
        const nextSelection = itemId
            ? preserveSelection
                ? currentSelection
                : setCanvasSelection(itemId, e.shiftKey)
            : null;
        selectedOverlay = target;

        pendingShiftToggleId =
            e.shiftKey && currentSelection.includes(itemId) ? itemId : null;
        dragMoved = false;
        if (itemId && e.altKey && !isLocked && currentStage === 1) {
            const copies = startDuplicateDrag(e, itemId);
            if (copies.length > 0) return;
        }
        if (itemId && (!nextSelection.includes(itemId) || isLocked)) return;

        isBlockDragging = true;
        dragItemIds = itemId
            ? isHtmlMode && currentStage === 1
                ? [...nextSelection]
                : [itemId]
            : [];
        dragComponentIds = dragItemIds.filter((id) => id !== QR_CANVAS_ITEM_ID);
        componentStartPositions = captureCanvasItemPositions(dragItemIds);
        dragAxis = null;
        dragStartX = e.clientX;
        dragStartY = e.clientY;
        blockStartGeometry = { ...geometry };
    }

    function handleOverlayResizeMouseDown(
        e,
        target,
        geometry,
        isLocked = false,
    ) {
        if (
            readOnly ||
            !(currentStage === 2 || (isHtmlMode && currentStage === 1)) ||
            isLocked
        )
            return;
        e.stopPropagation();
        if (target === 'qr') setCanvasSelection(QR_CANVAS_ITEM_ID);
        selectedOverlay = target;
        isHandleDragging = true;
        dragItemIds = [];
        dragAxis = null;
        dragStartX = e.clientX;
        dragStartY = e.clientY;
        blockStartGeometry = { ...geometry };
    }

    function handleBlockMouseDown(e, index) {
        if (readOnly || currentStage !== 3) return;
        if (plottingConfig?.active && e.button === 0) {
            handlePlottingSheetClick(e);
            return;
        }
        if (autoGridActive) {
            handleAutoGridSheetClick(e);
            return;
        }
        e.stopPropagation();
        selectedOverlay = null;
        const isModifier = e.shiftKey || e.ctrlKey || e.metaKey;
        if (isModifier) {
            if (selectedSectionIndices.includes(index)) {
                selectedSectionIndices = selectedSectionIndices.filter(
                    (i) => i !== index,
                );
                activeSectionIndex = selectedSectionIndices.at(-1) ?? -1;
                // Deselected: do not initiate drag on deselected item
                return;
            } else {
                selectedSectionIndices = [...selectedSectionIndices, index];
                activeSectionIndex = index;
            }
        } else if (selectedSectionIndices.includes(index)) {
            activeSectionIndex = index;
        } else {
            selectedSectionIndices = [index];
            activeSectionIndex = index;
        }
        const sec = sections[index];
        if (!sec || sec.isLocked) return;
        const targetIndices =
            selectedSectionIndices.length > 0 &&
            selectedSectionIndices.includes(index)
                ? selectedSectionIndices.filter((i) => !sections[i]?.isLocked)
                : sec.isLocked
                  ? []
                  : [index];
        if (targetIndices.length === 0) return;
        isBlockDragging = true;
        dragBlockIndex = index;
        dragSectionIndices = targetIndices;
        sectionStartPositions = Object.fromEntries(
            targetIndices.map((i) => {
                const section = sections[i];
                return [
                    i,
                    {
                        posX: Number(section.posX) || 25,
                        posY: Number(section.posY) || 72,
                        optionPointsMm: Array.isArray(section.optionPointsMm)
                            ? section.optionPointsMm.map((point) =>
                                  Array.isArray(point)
                                      ? [...point]
                                      : { ...point },
                              )
                            : null,
                    },
                ];
            }),
        );
        const bounds = targetIndices.map((i) => {
            const section = sections[i];
            const start = sectionStartPositions[i];
            const size =
                isUploadedSource ||
                (Array.isArray(section.optionPointsMm) &&
                    section.optionPointsMm.length > 0)
                    ? recognitionGuideSizeMm(section)
                    : {
                          w_mm: Number(section.w_mm) || 75,
                          h_mm: Number(section.h_mm) || 140,
                      };
            return {
                minX: start.posX,
                minY: start.posY,
                maxX: start.posX + size.w_mm,
                maxY: start.posY + size.h_mm,
            };
        });
        sectionDragBounds = {
            minX: Math.min(...bounds.map((bound) => bound.minX)),
            minY: Math.min(...bounds.map((bound) => bound.minY)),
            maxX: Math.max(...bounds.map((bound) => bound.maxX)),
            maxY: Math.max(...bounds.map((bound) => bound.maxY)),
        };
        dragItemIds = [];
        dragAxis = null;
        pendingShiftToggleId = null;
        dragMoved = false;
        dragStartX = e.clientX;
        dragStartY = e.clientY;
        blockStartGeometry = {
            x: Number(sec.posX) || 25,
            y: Number(sec.posY) || 72,
            w: Number(sec.w_mm) || 75,
            h: Number(sec.h_mm) || 140,
        };

    }
    function handlePitchHandleMouseDown(e, index, type) {
        if (readOnly || currentStage !== 3) return;
        e.stopPropagation();
        e.preventDefault();
        selectedOverlay = null;
        activeSectionIndex = index;
        const sec = sections[index];
        if (!sec || sec.isLocked) return;

        isPitchDragging = true;
        pitchDragType = type;
        pitchDragIndex = index;
        if (type === 'bubblePitch') {
            pitchDragStartValue = Number(sec.bubblePitchMm) || 5.0;
            pitchDragStartPointer = e.clientX;
        } else {
            pitchDragStartValue = Number(sec.rowPitchMm) || 6.5;
            pitchDragStartPointer = e.clientY;
        }
    }

    function handleAutoGridSheetClick(e) {
        if (!autoGridActive || currentStage !== 3) return;
        const sec = sections[activeSectionIndex];
        if (!sec || sec.isLocked) return;

        e.stopPropagation();
        e.preventDefault();

        const paperWidthMm = Number(
            canonicalPreview?.artifact?.paper?.width_mm ?? 210,
        );
        const paperHeightMm = Number(
            canonicalPreview?.artifact?.paper?.height_mm ?? 297,
        );
        const rect = sheetElement?.getBoundingClientRect?.();

        let clickXMm = 0;
        let clickYMm = 0;
        if (rect && rect.width > 0 && rect.height > 0) {
            clickXMm = ((e.clientX - rect.left) / rect.width) * paperWidthMm;
            clickYMm = ((e.clientY - rect.top) / rect.height) * paperHeightMm;
        } else {
            clickXMm = (e.clientX - panX) / (2.476 * scale);
            clickYMm = (e.clientY - panY) / (2.476 * scale);
        }

        clickXMm = Number(
            Math.max(0, Math.min(paperWidthMm, clickXMm)).toFixed(2),
        );
        clickYMm = Number(
            Math.max(0, Math.min(paperHeightMm, clickYMm)).toFixed(2),
        );

        if (!autoGridFirstPointMm) {
            autoGridFirstPointMm = { x: clickXMm, y: clickYMm };
            autoGridError = '';
            return;
        }

        const columns =
            sec.type === 'roll'
                ? Math.max(1, Number(sec.questionsCount) || 7)
                : Math.max(1, getOptionLabels(sec).length);
        const rows =
            sec.type === 'roll'
                ? 10
                : Math.max(1, Number(sec.questionsCount) || 1);

        const solved = solveAutoGridFromTwoPoints({
            kind: sec.type === 'roll' ? 'roll' : 'grid',
            firstPointMm: autoGridFirstPointMm,
            lastPointMm: { x: clickXMm, y: clickYMm },
            columns,
            rows,
            bubbleDiameterMm: sec.bubbleDiameterMm,
        });

        if (!solved.ok) {
            autoGridError =
                solved.error ||
                'Could not solve grid from those two points. Click top-left then bottom-right.';
            autoGridFirstPointMm = null;
            return;
        }

        sec.posX = solved.posX;
        sec.posY = solved.posY;
        sec.bubblePitchMm = solved.bubblePitchMm;
        sec.rowPitchMm = solved.rowPitchMm;
        if (solved.bubbleDiameterMm) {
            sec.bubbleDiameterMm = solved.bubbleDiameterMm;
        }
        autoGridActive = false;
        autoGridFirstPointMm = null;
        autoGridError = '';
        if (onGeometryChanged) onGeometryChanged();
    }

    function handleKeyDown(e) {
        const active = document.activeElement;
        const isSelectEscape =
            e.key === 'Escape' && active?.tagName === 'SELECT';
        if (
            active &&
            (active.tagName === 'INPUT' ||
                active.tagName === 'TEXTAREA' ||
                (active.tagName === 'SELECT' && !isSelectEscape) ||
                active.isContentEditable)
        ) {
            return;
        }
        if (e.code === 'Space') {
            if (
                active &&
                (active.tagName === 'BUTTON' ||
                    active.tagName === 'A' ||
                    active.getAttribute?.('role') === 'button')
            ) {
                return;
            }
            isSpaceHeld = true;
            e.preventDefault();
            return;
        }
        if (e.key === 'Escape' && e.repeat) {
            return;
        }
        if (readOnly) return;
        if (
            (e.key === 'Delete' || e.key === 'Backspace') &&
            selectedOverlay === 'applicant_write_in' &&
            activeSectionIndex < 0 &&
            selectedSectionIndices.length === 0
        ) {
            removeActiveWriteInBox();
            e.preventDefault();
            return;
        }


        const isMod = e.ctrlKey || e.metaKey;
        if (isMod && e.key.toLowerCase() === 'z') {
            e.preventDefault();
            if (e.shiftKey) {
                if (onRedo) onRedo();
            } else if (onUndo) {
                onUndo();
            }
            return;
        }
        if (isMod && e.key.toLowerCase() === 'y') {
            e.preventDefault();
            if (onRedo) onRedo();
            return;
        }

        if (e.key === 'Escape' || e.key === 'Enter') {
            if (isPitchDragging) {
                isPitchDragging = false;
                pitchDragType = null;
                pitchDragIndex = null;
                if (onGeometryChanged) onGeometryChanged();
                return;
            }
            if (isBlockDragging || isHandleDragging) {
                isBlockDragging = false;
                isHandleDragging = false;
                dragBlockIndex = null;
                dragSectionIndices = [];
                sectionStartPositions = {};
                sectionDragBounds = { minX: 0, minY: 0, maxX: 0, maxY: 0 };
                if (onGeometryChanged) onGeometryChanged();
                return;
            }
        }
        if (e.key === 'Escape' && currentStage === 3) {
            isBlockDragging = false;
            isPitchDragging = false;
            autoGridActive = false;
            selectedSectionIndices = [];
            activeSectionIndex = -1;
            selectedOverlay = null;
            if (plottingConfig) {
                plottingConfig = {
                    ...plottingConfig,
                    active: false,
                    pointsMm: [],
                    itemNumber: nextItemNumberForArea(
                        sections,
                        plottingConfig.areaCode,
                        plottingConfig.subtest_code,
                    ),
                };
            }
            e.preventDefault();
            return;
        }

        const arrowDirections = {
            ArrowLeft: [-1, 0],
            ArrowRight: [1, 0],
            ArrowUp: [0, -1],
            ArrowDown: [0, 1],
        };
        if (
            isHtmlMode &&
            currentStage === 1 &&
            arrowDirections[e.key] &&
            !e.altKey &&
            activeComponentIds.length > 0
        ) {
            const movableComponents = activeComponentIds
                .map((id) => components.find((component) => component.id === id))
                .filter((component) => component && !component.isLocked);
            if (movableComponents.length > 0) {
                e.preventDefault();
                const step = e.shiftKey ? 2.0 : 0.2;
                const [directionX, directionY] = arrowDirections[e.key];
                const deltaX = directionX * step;
                const deltaY = directionY * step;
                const bounds = movableComponents.map((component) => {
                    const start = captureCanvasItemPositions([component.id])[
                        component.id
                    ];
                    return {
                        minX: start.x,
                        minY: start.y,
                        maxX: start.x + start.w,
                        maxY: start.y + start.h,
                    };
                });
                const minX = Math.min(...bounds.map((bound) => bound.minX));
                const minY = Math.min(...bounds.map((bound) => bound.minY));
                const maxX = Math.max(...bounds.map((bound) => bound.maxX));
                const maxY = Math.max(...bounds.map((bound) => bound.maxY));
                const clampedDX = Math.max(
                    marginMm - minX,
                    Math.min(210 - marginMm - maxX, deltaX),
                );
                const clampedDY = Math.max(
                    marginMm - minY,
                    Math.min(297 - marginMm - maxY, deltaY),
                );
                for (const component of movableComponents) {
                    component.posX =
                        Math.round((Number(component.posX) + clampedDX) * 10) /
                        10;
                    component.posY =
                        Math.round((Number(component.posY) + clampedDY) * 10) /
                        10;
                }
                if (onComponentGeometryChanged) onComponentGeometryChanged();
            }
            return;
        }

        if (
            currentStage === 3 &&
            arrowDirections[e.key] &&
            !e.altKey &&
            (selectedSectionIndices.length > 0 || activeSectionIndex >= 0)
        ) {
            const indices =
                selectedSectionIndices.length > 0
                    ? selectedSectionIndices
                    : [activeSectionIndex];
            const movableSections = indices
                .map((index) => ({ index, section: sections[index] }))
                .filter(({ section }) => section && !section.isLocked);
            if (movableSections.length > 0) {
                e.preventDefault();
                const step = e.shiftKey ? 2.0 : 0.2;
                const [directionX, directionY] = arrowDirections[e.key];
                const deltaX = directionX * step;
                const deltaY = directionY * step;
                const bounds = movableSections.map(({ section }) => {
                    const startX = Number(section.posX) || 0;
                    const startY = Number(section.posY) || 0;
                    const size =
                        isUploadedSource ||
                        (Array.isArray(section.optionPointsMm) &&
                            section.optionPointsMm.length > 0)
                            ? recognitionGuideSizeMm(section)
                            : {
                                  w_mm: Number(section.w_mm) || 75,
                                  h_mm: Number(section.h_mm) || 140,
                              };
                    return {
                        minX: startX,
                        minY: startY,
                        maxX: startX + size.w_mm,
                        maxY: startY + size.h_mm,
                    };
                });
                const minX = Math.min(...bounds.map((bound) => bound.minX));
                const minY = Math.min(...bounds.map((bound) => bound.minY));
                const maxX = Math.max(...bounds.map((bound) => bound.maxX));
                const maxY = Math.max(...bounds.map((bound) => bound.maxY));
                const clampedDX = Math.max(-minX, Math.min(210 - maxX, deltaX));
                const clampedDY = Math.max(-minY, Math.min(297 - maxY, deltaY));
                for (const { section } of movableSections) {
                    section.posX =
                        Math.round(
                            ((Number(section.posX) || 0) + clampedDX) * 10,
                        ) / 10;
                    section.posY =
                        Math.round(
                            ((Number(section.posY) || 0) + clampedDY) * 10,
                        ) / 10;
                    if (Array.isArray(section.optionPointsMm)) {
                        section.optionPointsMm = section.optionPointsMm.map(
                            (point) => {
                                if (Array.isArray(point)) {
                                    return [
                                        Math.round((point[0] + clampedDX) * 100) /
                                            100,
                                        Math.round((point[1] + clampedDY) * 100) /
                                            100,
                                    ];
                                }
                                return {
                                    ...point,
                                    x_mm:
                                        Math.round(
                                            (point.x_mm + clampedDX) * 100,
                                        ) / 100,
                                    y_mm:
                                        Math.round(
                                            (point.y_mm + clampedDY) * 100,
                                        ) / 100,
                                };
                            },
                        );
                    }
                }
                if (onGeometryChanged) onGeometryChanged();
            }
            return;
        }

        if (
            currentStage === 3 &&
            activeSectionIndex !== null &&
            sections[activeSectionIndex]
        ) {
            const sec = sections[activeSectionIndex];

            if (isMod && e.key.toLowerCase() === 'c') {
                e.preventDefault();
                plottingConfig = {
                    ...plottingConfig,
                    stampSourceSection: JSON.parse(JSON.stringify(sec)),
                };
                return;
            }
            if (isMod && e.key.toLowerCase() === 'v') {
                e.preventDefault();
                startPasteStampFromSection(
                    plottingConfig.stampSourceSection ?? sec,
                );
                return;
            }
            if (
                (e.key === 'Delete' || e.key === 'Backspace') &&
                !sec.isLocked
            ) {
                e.preventDefault();
                if (selectedSectionIndices.length >= 2) {
                    const toDelete = new Set(selectedSectionIndices);
                    sections = sections.filter((_, idx) => !toDelete.has(idx));
                    selectedSectionIndices = [];
                    activeSectionIndex = Math.max(
                        0,
                        Math.min(activeSectionIndex, sections.length - 1),
                    );
                    if (onGeometryChanged) onGeometryChanged();
                } else {
                    deleteSectionAtIndex(activeSectionIndex);
                }
                return;
            }

            if (sec.isLocked) return;

            const isShift = e.shiftKey;
            const isAlt = e.altKey;
            const step = isShift ? 1.0 : 0.2;

            if (e.key === 'ArrowLeft') {
                e.preventDefault();
                if (isAlt) {
                    sec.bubblePitchMm = Math.max(
                        3.0,
                        Math.min(
                            25.0,
                            Math.round(
                                ((Number(sec.bubblePitchMm) || 5.0) - 0.1) *
                                    10,
                            ) / 10,
                        ),
                    );
                } else {
                    sec.posX = Math.max(
                        0,
                        Math.round(((Number(sec.posX) || 0) - step) * 10) / 10,
                    );
                    if (Array.isArray(sec.optionPointsMm)) {
                        sec.optionPointsMm = sec.optionPointsMm.map((pt) => ({
                            ...pt,
                            x_mm: Number((pt.x_mm - step).toFixed(2)),
                        }));
                    }
                }
                if (onGeometryChanged) onGeometryChanged();
            } else if (e.key === 'ArrowRight') {
                e.preventDefault();
                if (isAlt) {
                    sec.bubblePitchMm = Math.max(
                        3.0,
                        Math.min(
                            25.0,
                            Math.round(
                                ((Number(sec.bubblePitchMm) || 5.0) + 0.1) *
                                    10,
                            ) / 10,
                        ),
                    );
                } else {
                    sec.posX = Math.min(
                        210,
                        Math.round(((Number(sec.posX) || 0) + step) * 10) / 10,
                    );
                    if (Array.isArray(sec.optionPointsMm)) {
                        sec.optionPointsMm = sec.optionPointsMm.map((pt) => ({
                            ...pt,
                            x_mm: Number((pt.x_mm + step).toFixed(2)),
                        }));
                    }
                }
                if (onGeometryChanged) onGeometryChanged();
            } else if (e.key === 'ArrowUp') {
                e.preventDefault();
                if (isAlt) {
                    sec.rowPitchMm = Math.max(
                        3.0,
                        Math.min(
                            30.0,
                            Math.round(
                                ((Number(sec.rowPitchMm) || 6.5) - 0.1) * 10,
                            ) / 10,
                        ),
                    );
                } else {
                    sec.posY = Math.max(
                        0,
                        Math.round(((Number(sec.posY) || 0) - step) * 10) / 10,
                    );
                    if (Array.isArray(sec.optionPointsMm)) {
                        sec.optionPointsMm = sec.optionPointsMm.map((pt) => ({
                            ...pt,
                            y_mm: Number((pt.y_mm - step).toFixed(2)),
                        }));
                    }
                }
                if (onGeometryChanged) onGeometryChanged();
            } else if (e.key === 'ArrowDown') {
                e.preventDefault();
                if (isAlt) {
                    sec.rowPitchMm = Math.max(
                        3.0,
                        Math.min(
                            30.0,
                            Math.round(
                                ((Number(sec.rowPitchMm) || 6.5) + 0.1) * 10,
                            ) / 10,
                        ),
                    );
                } else {
                    sec.posY = Math.min(
                        297,
                        Math.round(((Number(sec.posY) || 0) + step) * 10) / 10,
                    );
                    if (Array.isArray(sec.optionPointsMm)) {
                        sec.optionPointsMm = sec.optionPointsMm.map((pt) => ({
                            ...pt,
                            y_mm: Number((pt.y_mm + step).toFixed(2)),
                        }));
                    }
                }
                if (onGeometryChanged) onGeometryChanged();
            }
        }
    }
    function recognitionGuideSizeMm(section) {
        const geometry = effectiveRecognitionGeometryMm({
            kind: section.type === 'roll' ? 'roll' : 'grid',
            bubbleDiameterMm: section.bubbleDiameterMm,
            bubblePitchMm: section.bubblePitchMm,
            rowPitchMm: section.rowPitchMm,
            minBubbleMm: isUploadedSource ? 2.5 : 4.2,
        });
        if (section.type === 'roll') {
            const isHorizontalRoll = section.rollDirection === 'horizontal';
            const digits = Math.max(1, Number(section.questionsCount) || 7);
            const columns = isHorizontalRoll ? 10 : digits;
            const rows = isHorizontalRoll ? digits : 10;
            return {
                w_mm:
                    geometry.bubbleDiameterMm +
                    (columns - 1) * geometry.bubblePitchMm,
                h_mm:
                    geometry.bubbleDiameterMm +
                    (rows - 1) * geometry.rowPitchMm,
            };
        }
        const options = getOptionLabels(section).length;
        const rows = Math.max(1, Number(section.questionsCount) || 1);
        return {
            w_mm:
                geometry.bubbleDiameterMm +
                (options - 1) * geometry.bubblePitchMm,
            h_mm: geometry.bubbleDiameterMm + (rows - 1) * geometry.rowPitchMm,
        };
    }

    function recognitionGuideBounds(
        section,
        posX = Number(section.posX),
        posY = Number(section.posY),
    ) {
        const labels = getOptionLabels(section);
        const questionCount = Math.max(1, Number(section.questionsCount) || 1);
        if (
            section.type !== 'roll' &&
            Array.isArray(section.optionPointsMm) &&
            section.optionPointsMm.length === questionCount * labels.length
        ) {
            const geometry = effectiveRecognitionGeometryMm({
                kind: 'grid',
                bubbleDiameterMm: section.bubbleDiameterMm,
                bubblePitchMm: section.bubblePitchMm,
                rowPitchMm: section.rowPitchMm,
                minBubbleMm: isUploadedSource ? 2.5 : 4.2,
            });
            const radiusMm = geometry.bubbleDiameterMm / 2;
            const deltaX = posX - (Number(section.posX) || 0);
            const deltaY = posY - (Number(section.posY) || 0);
            const xs = section.optionPointsMm.map((pt) => Number(pt.x_mm) + deltaX);
            const ys = section.optionPointsMm.map((pt) => Number(pt.y_mm) + deltaY);
            const minX = Math.min(...xs) - radiusMm;
            const minY = Math.min(...ys) - radiusMm;
            const maxX = Math.max(...xs) + radiusMm;
            const maxY = Math.max(...ys) + radiusMm;
            return {
                x: minX,
                y: minY,
                w: Math.max(geometry.bubbleDiameterMm, maxX - minX),
                h: Math.max(geometry.bubbleDiameterMm, maxY - minY),
            };
        }
        const bounds = recognitionGuideSizeMm(section);
        const offset = recognitionOriginOffsetMm(section);
        return {
            x: posX + offset.x,
            y: posY + offset.y,
            w: bounds.w_mm,
            h: bounds.h_mm,
        };
    }

    function localRecognitionBubbles(section) {
        const origin = recognitionGuideBounds(section);
        const geometry = effectiveRecognitionGeometryMm({
            kind: section.type === 'roll' ? 'roll' : 'grid',
            bubbleDiameterMm: section.bubbleDiameterMm,
            bubblePitchMm: section.bubblePitchMm,
            rowPitchMm: section.rowPitchMm,
            minBubbleMm: isUploadedSource ? 2.5 : 4.2,
        });
        const bubbleSizePx = mmToPx(geometry.bubbleDiameterMm);
        const bubbleW = bubbleSizePx / PX_PER_MM;
        const labels = getOptionLabels(section);
        const questionCount = Math.max(1, Number(section.questionsCount) || 1);
        if (
            Array.isArray(section.optionPointsMm) &&
            section.optionPointsMm.length === questionCount * labels.length
        ) {
            return section.optionPointsMm.map((pt) => ({
                label: pt.label,
                x_mm: pxToMm(
                    mmToPx(pt.x_mm) - Math.floor(bubbleSizePx / 2),
                ),
                y_mm: pxToMm(
                    mmToPx(pt.y_mm) - Math.floor(bubbleSizePx / 2),
                ),
                w_mm: bubbleW,
                h_mm: bubbleW,
            }));
        }
        const optionCount = labels.length;
        const isHorizontalRoll =
            section.type === 'roll' && section.rollDirection === 'horizontal';
        const rowCount =
            section.type === 'roll'
                ? (isHorizontalRoll
                      ? Math.max(1, Number(section.questionsCount) || 7)
                      : 10)
                : Math.max(1, Number(section.questionsCount) || 1);
        const columnCount =
            section.type === 'roll'
                ? (isHorizontalRoll
                      ? 10
                      : Math.max(1, Number(section.questionsCount) || 7))
                : optionCount;
        const originXPx = mmToPx(origin.x);
        const originYPx = mmToPx(origin.y);
        const xPitchPx = mmToPx(geometry.bubblePitchMm);
        const yPitchPx = mmToPx(geometry.rowPitchMm);
        if (
            section.type === 'roll' &&
            section.identity_grid === true &&
            section.rollDirection !== 'horizontal' &&
            Number(section.rotationDeg ?? 0) !== 0
        ) {
            const positions = verticalRollBubblePositionsPx({
                originX: originXPx,
                originY: originYPx,
                digitCount: columnCount,
                digitPitchPx: xPitchPx,
                valuePitchPx: yPitchPx,
                bubbleWidthPx: bubbleSizePx,
                rotationDeg: Number(section.rotationDeg),
            });

            return positions.flatMap((digitPositions) =>
                digitPositions.map(([x, y], value) => ({
                    label: String(value),
                    x_mm: pxToMm(x),
                    y_mm: pxToMm(y),
                    w_mm: bubbleW,
                    h_mm: bubbleW,
                })),
            );
        }
        const bubbles = [];
        for (let row = 0; row < rowCount; row += 1) {
            for (let column = 0; column < columnCount; column += 1) {
                const bubbleLabel =
                    section.type === 'roll'
                        ? (isHorizontalRoll ? String(column) : String(row))
                        : labels[column];
                bubbles.push({
                    label: bubbleLabel,
                    x_mm: (originXPx + column * xPitchPx) / PX_PER_MM,
                    y_mm: (originYPx + row * yPitchPx) / PX_PER_MM,
                    w_mm: bubbleW,
                    h_mm: bubbleW,
                });
            }
        }
        return bubbles;
    }

    function collidesWithAnotherGuide(index, posX, posY, minimumGapMm = 2) {
        const candidate = recognitionGuideBounds(sections[index], posX, posY);
        return sections.some((section, otherIndex) => {
            if (otherIndex === index) return false;
            const other = recognitionGuideBounds(section);
            return !(
                candidate.x + candidate.w + minimumGapMm <= other.x ||
                other.x + other.w + minimumGapMm <= candidate.x ||
                candidate.y + candidate.h + minimumGapMm <= other.y ||
                other.y + other.h + minimumGapMm <= candidate.y
            );
        });
    }

    function componentLabel(c) {
        if (!c) return 'Component';
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
                return c.type || 'Component';
        }
    }

    function getOptionLabels(s) {
        if (!s) return ['A', 'B', 'C', 'D'];
        if (s.type === 'roll')
            return ['0', '1', '2', '3', '4', '5', '6', '7', '8', '9'];
        if (s.type === 'tf') return ['T', 'F'];
        if (s.type === 'likert') return ['1', '2', '3', '4', '5'];
        if (s.type === 'custom')
            return (s.customValuesStr || 'A, B')
                .split(',')
                .map((v) => v.trim())
                .filter(Boolean);
        if (Array.isArray(s.optionLabels) && s.optionLabels.length >= 2) {
            return s.optionLabels;
        }
        if (s.customValuesStr) {
            const parsed = s.customValuesStr.split(',').map((v) => v.trim()).filter(Boolean);
            if (parsed.length >= 2) return parsed;
        }
        const count = Number(s.optionsCount) || 4;
        const alphabet = ['A', 'B', 'C', 'D', 'E', 'F', 'G', 'H', 'I', 'J'];
        return alphabet.slice(0, Math.max(2, Math.min(10, count)));
    }
    function markerClassName(pattern) {
        if (pattern === 'target') return 'marker-target';
        if (pattern === 'aruco') return 'marker-aruco';
        return 'marker-box';
    }
</script>

<svelte:window
    onmousemove={handleCanvasMouseMove}
    onmouseup={handleCanvasMouseUp}
    onkeydown={handleKeyDown}
    onkeyup={handleCanvasKeyUp}
    onblur={handleCanvasMouseUp}
/>

<div
    class="bg-muted relative flex h-full w-full flex-1 flex-col overflow-hidden select-none"
>
    <!-- Canvas Viewport with Absolute Centering (ZERO scrollHeight inflation) -->
    <div
        class={`relative h-full w-full overflow-hidden ${plottingConfig?.active || autoGridActive || interactionMode === 'select' ? 'cursor-crosshair' : isDuplicateDragging ? 'cursor-copy' : 'cursor-grab active:cursor-grabbing'} canvas-grid`}
        onwheel={handleWheel}
        onmousedown={handleCanvasMouseDown}
        role="region"
        aria-label="OMR sheet design viewport"
        aria-keyshortcuts="Delete Backspace Control+D Meta+D Control+Z Control+Y Control+C Control+V"
    >
        {#if currentStage === 3 && plottingConfig?.active}
            <div
                data-omr-plotting-banner="true"
                class="pointer-events-auto absolute top-16 left-1/2 z-50 flex -translate-x-1/2 items-center gap-3 rounded-xl border border-emerald-400/70 bg-slate-900/95 px-3.5 py-2 text-xs font-semibold text-white shadow-2xl backdrop-blur-sm"
                role="status"
            >
                {#if plottingConfig.mode === 'paste_stamp'}
                    <span class="rounded-md bg-amber-500 px-1.5 py-0.5 font-mono text-[10px] font-bold text-slate-950">
                        STAMP Q{plottingConfig.itemNumber}
                    </span>
                    <span>
                        Click on the sheet to paste <strong>{plottingConfig.areaCode} · Q{plottingConfig.itemNumber}</strong> (auto-increments on each click)
                    </span>
                {:else if plottingConfig.mode === 'respot_single'}
                    <span class="rounded-md bg-sky-500 px-1.5 py-0.5 font-mono text-[10px] font-bold text-slate-950">
                        RE-SPOT
                    </span>
                    <span>
                        Click on the sheet to re-position Option <strong>{plottingConfig.optionLabels?.[plottingConfig.respotOptionIndex] ?? 'A'}</strong>
                    </span>
                {:else if plottingConfig.targetType === 'applicant_digit'}
                    <span class="flex size-5 items-center justify-center rounded-full bg-indigo-500 font-mono text-[11px] font-black text-white">
                        #
                    </span>
                    <span>
                        {#if plottingConfig.digitSpanMode}
                            {#if !plottingConfig.pointsMm?.length}
                                Step 1 of 2: Click center of <strong>Bubble 0</strong> (start of Digit 1)
                            {:else}
                                Step 2 of 2: Click center of <strong>Bubble 9</strong> (end of Digit 1)
                                <span class="ml-2 font-normal text-indigo-300 opacity-90">· Hold <kbd class="rounded bg-slate-800 px-1 py-0.5 font-mono text-[9px] font-bold text-white">Shift</kbd> to lock column</span>
                            {/if}
                        {:else}
                            Plotting Digit 1: Click center of <strong>Bubble {plottingConfig.optionLabels?.[plottingConfig.currentOptionIndex] ?? '0'}</strong> ({(plottingConfig.currentOptionIndex ?? 0) + 1} of 10)
                            {#if (plottingConfig.currentOptionIndex ?? 0) > 0}
                                <span class="ml-2 font-normal text-indigo-300 opacity-90">· Hold <kbd class="rounded bg-slate-800 px-1 py-0.5 font-mono text-[9px] font-bold text-white">Shift</kbd> to lock column</span>
                            {/if}
                        {/if}
                    </span>
                {:else if plottingConfig.questionSpanMode}
                    {@const optList = plottingConfig.optionLabels ?? ['A', 'B', 'C', 'D']}
                    <span class="flex size-5 items-center justify-center rounded-full bg-emerald-500 font-mono text-[11px] font-black text-slate-950">
                        {plottingConfig.pointsMm?.length ? optList[optList.length - 1] : optList[0]}
                    </span>
                    <span>
                        {#if !plottingConfig.pointsMm?.length}
                            Plotting <strong>{plottingConfig.areaCode} · Q{plottingConfig.itemNumber}</strong>: Step 1 of 2: Click center of Option <strong>{optList[0]}</strong>
                        {:else}
                            Plotting <strong>{plottingConfig.areaCode} · Q{plottingConfig.itemNumber}</strong>: Step 2 of 2: Click center of Option <strong>{optList[optList.length - 1]}</strong>
                            <span class="ml-2 font-normal text-emerald-300 opacity-90">· Hold <kbd class="rounded bg-slate-800 px-1 py-0.5 font-mono text-[9px] font-bold text-white">Shift</kbd> to snap horizontal</span>
                        {/if}
                    </span>
                {:else}
                    {@const activeOptLabel =
                        plottingConfig.optionLabels?.[
                            plottingConfig.currentOptionIndex
                        ] ?? 'A'}
                    <span class="flex size-5 items-center justify-center rounded-full bg-emerald-500 font-mono text-[11px] font-black text-slate-950">
                        {activeOptLabel}
                    </span>
                    <span>
                        Plotting <strong>{plottingConfig.areaCode} · Q{plottingConfig.itemNumber}</strong>: Click center of Option <strong>{activeOptLabel}</strong> ({(plottingConfig.currentOptionIndex ?? 0) + 1} of {plottingConfig.optionLabels?.length ?? 4})
                        {#if (plottingConfig.currentOptionIndex ?? 0) > 0}
                            <span class="ml-2 font-normal text-emerald-300 opacity-90">· Hold <kbd class="rounded bg-slate-800 px-1 py-0.5 font-mono text-[9px] font-bold text-white">Shift</kbd> to snap horizontal</span>
                        {/if}
                    </span>
                {/if}
                <button
                    type="button"
                    data-omr-plotting-cancel="true"
                    onclick={(e) => {
                        e.stopPropagation();
                        plottingConfig = {
                            ...plottingConfig,
                            active: false,
                            pointsMm: [],
                            currentOptionIndex: 0,
                        };
                    }}
                    class="rounded-md bg-slate-800 px-2 py-1 text-[10px] font-bold text-slate-200 hover:bg-slate-700"
                >
                    Done (Esc)
                </button>
            </div>
        {:else if currentStage === 3 && (autoGridActive || autoGridError)}
            <div
                data-omr-auto-grid-banner="true"
                class="pointer-events-auto absolute top-16 left-1/2 z-50 flex -translate-x-1/2 items-center gap-3 rounded-xl border border-emerald-400/60 bg-slate-900/95 px-3.5 py-2 text-xs font-semibold text-white shadow-2xl backdrop-blur-sm"
                role="status"
            >
                <span class="flex size-5 items-center justify-center rounded-full bg-emerald-600 font-mono text-[10px] font-bold">
                    {autoGridFirstPointMm ? '2' : '1'}
                </span>
                <div class="flex flex-col">
                    <span>
                        {autoGridFirstPointMm
                            ? 'Step 2 of 2: Click the center of the last bubble (Bottom-Right)'
                            : 'Step 1 of 2: Click the center of the first bubble (Top-Left)'}
                    </span>
                    {#if autoGridError}
                        <span class="text-[10px] font-medium text-amber-300" data-omr-auto-grid-error="true">
                            {autoGridError}
                        </span>
                    {/if}
                </div>
                <button
                    type="button"
                    data-omr-auto-grid-cancel="true"
                    onclick={(e) => {
                        e.stopPropagation();
                        autoGridActive = false;
                        autoGridFirstPointMm = null;
                        autoGridError = '';
                    }}
                    class="rounded-md bg-slate-800 px-2 py-1 text-[10px] font-bold text-slate-200 hover:bg-slate-700"
                >
                    Cancel
                </button>
            </div>
        {/if}
        <!-- Centering Absolute Wrapper: Zero layout height inflation -->
        <div
            class="pointer-events-none absolute inset-0 flex items-center justify-center overflow-hidden"
        >
            <!-- Paper and guides share one millimetre scale; outlines consume no coordinate space. -->
            <div
                bind:this={sheetElement}
                data-omr-sheet="true"
                class={`pointer-events-auto relative isolate shrink-0 origin-center overflow-hidden rounded-xs bg-white shadow-2xl outline outline-slate-300 transition-transform ${plottingConfig?.active || autoGridActive ? 'cursor-crosshair' : ''}`}
                style={`width: ${(canonicalPreview?.artifact?.paper?.width_mm ?? 210) * 2.476}px; height: ${(canonicalPreview?.artifact?.paper?.height_mm ?? 297) * 2.476}px; transform: translate(${panX}px, ${panY}px) scale(${scale});`}
            >
                {#if isMarqueeSelecting}
                    <div
                        class="pointer-events-none absolute z-50 rounded border border-emerald-500 bg-emerald-500/15"
                        style={marqueeStyle}
                    ></div>
                {/if}
                {#if activeSheetImageUrl && (currentStage >= 3 || showCanonicalPrintPreview || !isHtmlMode)}
                    <img
                        src={activeSheetImageUrl}
                        alt={canonicalPreview?.artifact
                            ? 'Server-rendered answer sheet'
                            : 'Uploaded source sheet for recognition mapping'}
                        class="pointer-events-none absolute inset-0 z-0 h-full w-full object-contain"
                        onload={() => {
                            sheetImageLoaded = true;
                            sheetImageError = false;
                        }}
                        onerror={() => {
                            sheetImageLoaded = false;
                            sheetImageError = true;
                        }}
                    />
                {:else if currentStage >= 4 && !activeSheetImageUrl && !isHtmlMode}
                    <!-- In Stage 4 (Verification), candidate preview is strictly required before verification can run. -->
                    <div
                        class="pointer-events-none absolute inset-0 z-[60] flex items-center justify-center p-8"
                        role="status"
                    >
                        <div
                            class="max-w-[78%] rounded-xl border border-amber-300 bg-white/95 px-4 py-3 text-center text-xs leading-relaxed font-semibold text-slate-800 shadow-xl ring-1 ring-amber-200/70 backdrop-blur-sm"
                        >
                            <span class="block">Candidate preview unavailable for Page {activePageNo}.</span>
                            <span class="mt-1 block text-[11px] font-medium text-slate-600">
                                Return to Recognition Mapping, then continue to Verification to prepare the candidate preview.
                            </span>
                        </div>
                    </div>
                {:else if safeHtmlContent && isHtmlMode}
                    <div
                        class="pointer-events-none absolute inset-0 z-0 h-full w-full overflow-hidden rounded-xs bg-white"
                    >
                        <iframe
                            srcdoc={safeHtmlContent}
                            title="HTML Source Preview"
                            class="pointer-events-none overflow-hidden border-none bg-white"
                            style="width: 793.7px; height: 1122.5px; transform: scale(0.65517); transform-origin: top left;"
                            scrolling="no"
                            onload={() => {
                                sheetImageLoaded = true;
                            }}
                        ></iframe>
                    </div>
                {/if}

                {#if !sheetImageLoaded && !sheetImageError && activeSheetImageUrl && !isHtmlMode}
                    <div
                        role="status"
                        data-omr-canvas-loading="true"
                        class="pointer-events-none absolute inset-0 z-50 flex flex-col items-center justify-center bg-white/60 backdrop-blur-xs transition-opacity duration-150"
                    >
                        <div
                            style={`transform: scale(${1 / scale}); transform-origin: center;`}
                            class="flex items-center gap-2 rounded-xl border border-slate-200 bg-white/95 px-3.5 py-2 shadow-lg ring-1 ring-slate-900/5"
                        >
                            <span class="text-xs font-semibold text-slate-700">Loading Page {activePageNo}…</span>
                        </div>
                    </div>
                {/if}

                {#if sheetImageError && activeSheetImageUrl && !isHtmlMode}
                    <div
                        role="alert"
                        data-omr-canvas-error="true"
                        class="pointer-events-none absolute inset-0 z-50 flex flex-col items-center justify-center bg-white/80 p-6 text-center"
                    >
                        <div
                            style={`transform: scale(${1 / scale}); transform-origin: center;`}
                            class="pointer-events-auto max-w-xs rounded-xl border border-rose-200 bg-rose-50 p-4 text-xs text-rose-800 shadow-md"
                        >
                            <p class="font-bold">Could not load Page {activePageNo} image</p>
                            <p class="mt-1 text-[11px] text-rose-600">The raster asset could not be fetched.</p>
                            <button
                                type="button"
                                onclick={() => {
                                    sheetImageError = false;
                                    sheetImageLoaded = false;
                                    const current = currentRenderedUrl;
                                    currentRenderedUrl = null;
                                    setTimeout(() => {
                                        currentRenderedUrl = current;
                                    }, 50);
                                }}
                                class="mt-3 rounded-lg bg-rose-700 px-3 py-1.5 text-xs font-semibold text-white hover:bg-rose-600"
                            >
                                Retry
                            </button>
                        </div>
                    </div>
                {/if}

                <!-- Diagnostic overlays (aligned frame or detection overlay) in Stage 3 -->
                {#if currentStage >= 3 && previewSurface !== 'canonical'}
                    {@const insets = canonicalPreview?.artifact?.insets_mm ?? {
                        top: 12,
                        right: 12,
                        bottom: 14,
                        left: 12,
                    }}
                    {@const activeDiagnosticUrl =
                        previewSurface === 'overlay'
                            ? canonicalPreview?.overlay_url
                            : canonicalPreview?.aligned_url}
                    {#if activeDiagnosticUrl}
                        <img
                            src={activeDiagnosticUrl}
                            alt={previewSurface === 'overlay'
                                ? 'OMRChecker diagnostic overlay'
                                : 'OMRChecker aligned frame'}
                            class="pointer-events-none absolute z-10"
                            style={`top:${Number(insets.top) * 2.476}px;left:${Number(insets.left) * 2.476}px;width:${(210 - Number(insets.left) - Number(insets.right)) * 2.476}px;height:${(297 - Number(insets.top) - Number(insets.bottom)) * 2.476}px;`}
                        />
                    {/if}
                {/if}
                {#if showFiducials && (currentStage === 2 || (isHtmlMode && currentStage === 1)) && !showCanonicalPrintPreview}
                    <div
                        class={`pointer-events-none absolute z-40 ${markerClassName(markerPattern)}`}
                        style={`top: ${markerInsetsMm.top * 2.476}px; left: ${markerInsetsMm.left * 2.476}px;`}
                    ></div>
                    <div
                        class={`pointer-events-none absolute z-40 ${markerClassName(markerPattern)}`}
                        style={`top: ${markerInsetsMm.top * 2.476}px; right: ${markerInsetsMm.right * 2.476}px;`}
                    ></div>
                    <div
                        class={`pointer-events-none absolute z-40 ${markerClassName(markerPattern)}`}
                        style={`bottom: ${markerInsetsMm.bottom * 2.476}px; left: ${markerInsetsMm.left * 2.476}px;`}
                    ></div>
                    <div
                        class={`pointer-events-none absolute z-40 ${markerClassName(markerPattern)}`}
                        style={`bottom: ${markerInsetsMm.bottom * 2.476}px; right: ${markerInsetsMm.right * 2.476}px;`}
                    ></div>
                {/if}
                {#if isHtmlMode && currentStage === 1 && !showCanonicalPrintPreview}
                    <div
                        class="pointer-events-none absolute z-0 border border-dashed border-emerald-300/70"
                        style={`top: ${marginMm * 2.476}px; left: ${marginMm * 2.476}px; right: ${marginMm * 2.476}px; bottom: ${marginMm * 2.476}px;`}
                        aria-label={`Printable margin inset ${marginMm} millimeters`}
                    ></div>
                {/if}

                <!-- Shared overlay layer (Stage 2 and Sheet Designer Stage 1) -->
                {#if currentStage === 2 || (isHtmlMode && currentStage === 1)}
                    {#if showHeaderOverlay && currentStage < 3 && !showCanonicalPrintPreview}
                        <div
                            class={`absolute flex items-center justify-between border-b-2 border-slate-900 pb-1.5 transition-all ${
                                selectedOverlay === 'header' && !headerLocked
                                    ? 'z-30 rounded bg-emerald-500/10 p-1 ring-2 ring-emerald-500'
                                    : 'z-10'
                            }`}
                            style={`top: ${headerY * 2.476}px; left: ${headerX * 2.476}px; width: ${headerW * 2.476}px; text-align: ${headerAlign};`}
                            onmousedown={(e) =>
                                handleOverlayMouseDown(
                                    e,
                                    'header',
                                    {
                                        x: headerX,
                                        y: headerY,
                                        w: headerW,
                                        h: headerH,
                                    },
                                    headerLocked,
                                )}
                            role="button"
                            tabindex="0"
                        >
                            <div
                                class="w-full"
                                style={`text-align: ${headerAlign};`}
                            >
                                <h3
                                    class="leading-tight font-bold tracking-tight"
                                    style={`font-size: ${headerFontSize * 0.9}px; font-family: ${headerFontFamily}; color: ${headerColor}; text-align: ${headerAlign};`}
                                >
                                    {headerTitle}
                                </h3>
                                {#if headerSubtitle}
                                    <p
                                        class="mt-0.5 font-mono text-[9px] text-slate-500"
                                        style={`text-align: ${headerAlign};`}
                                    >
                                        {headerSubtitle}
                                    </p>
                                {/if}
                            </div>

                            <!-- On-Canvas Fast Style Pill for Header -->
                            {#if selectedOverlay === 'header' && !headerLocked}
                                <div
                                    class="pointer-events-auto absolute -top-7 left-0 z-40 flex items-center space-x-1.5 rounded-lg bg-slate-900/90 px-2 py-0.5 text-[10px] text-white shadow-lg"
                                    onmousedown={(e) => e.stopPropagation()}
                                    role="toolbar"
                                    tabindex="0"
                                >
                                    <button
                                        type="button"
                                        onclick={() => {
                                            headerFontSize = Math.max(
                                                9,
                                                headerFontSize - 1,
                                            );
                                            if (onGeometryChanged)
                                                onGeometryChanged();
                                        }}
                                        class="p-0.5 hover:text-emerald-400"
                                        title="Decrease font size"
                                    >
                                        <Minus class="size-2.5" />
                                    </button>
                                    <span class="font-mono text-[9.5px]"
                                        >{headerFontSize}pt</span
                                    >
                                    <button
                                        type="button"
                                        onclick={() => {
                                            headerFontSize = Math.min(
                                                28,
                                                headerFontSize + 1,
                                            );
                                            if (onGeometryChanged)
                                                onGeometryChanged();
                                        }}
                                        class="p-0.5 hover:text-emerald-400"
                                        title="Increase font size"
                                    >
                                        <Plus class="size-2.5" />
                                    </button>
                                </div>

                                <!-- Resize Handle -->
                                <div
                                    class="absolute -right-1 -bottom-1 h-3.5 w-3.5 cursor-se-resize rounded-full border-2 border-white bg-emerald-600 shadow-md"
                                    onmousedown={(e) =>
                                        handleOverlayResizeMouseDown(
                                            e,
                                            'header',
                                            {
                                                x: headerX,
                                                y: headerY,
                                                w: headerW,
                                                h: headerH,
                                            },
                                            headerLocked,
                                        )}
                                    role="button"
                                    tabindex="0"
                                ></div>
                            {/if}
                        </div>
                    {/if}

                    <!-- Simulated Cryptographic QR Overlay -->
                    {#if showQrOverlay}
                        <div
                            class={`absolute overflow-hidden border bg-white transition-all ${
                                activeItemIds.includes(QR_CANVAS_ITEM_ID)
                                    ? 'z-30 border-emerald-500 shadow-md ring-2 ring-emerald-400/80'
                                    : 'z-10 border-slate-300 shadow-xs'
                            }`}
                            data-omr-canvas-item-id={QR_CANVAS_ITEM_ID}
                            style={`top: ${qrY * 2.476}px; left: ${qrX * 2.476}px; width: ${qrW * 2.476}px; height: ${qrH * 2.476}px;`}
                            onmousedown={(e) =>
                                handleOverlayMouseDown(
                                    e,
                                    'qr',
                                    { x: qrX, y: qrY, w: qrW, h: qrH },
                                    qrLocked,
                                )}
                            role="button"
                            tabindex="0"
                        >
                            <!-- Simulated QR Matrix -->
                            <svg
                                viewBox="0 0 100 100"
                                class="block size-full text-slate-900"
                                xmlns="http://www.w3.org/2000/svg"
                            >
                                <rect
                                    x="5"
                                    y="5"
                                    width="28"
                                    height="28"
                                    fill="currentColor"
                                    rx="2"
                                />
                                <rect
                                    x="11"
                                    y="11"
                                    width="16"
                                    height="16"
                                    fill="white"
                                    rx="1"
                                />
                                <rect
                                    x="15"
                                    y="15"
                                    width="8"
                                    height="8"
                                    fill="currentColor"
                                    rx="1"
                                />
                                <rect
                                    x="67"
                                    y="5"
                                    width="28"
                                    height="28"
                                    fill="currentColor"
                                    rx="2"
                                />
                                <rect
                                    x="73"
                                    y="11"
                                    width="16"
                                    height="16"
                                    fill="white"
                                    rx="1"
                                />
                                <rect
                                    x="77"
                                    y="15"
                                    width="8"
                                    height="8"
                                    fill="currentColor"
                                    rx="1"
                                />
                                <rect
                                    x="5"
                                    y="67"
                                    width="28"
                                    height="28"
                                    fill="currentColor"
                                    rx="2"
                                />
                                <rect
                                    x="11"
                                    y="73"
                                    width="16"
                                    height="16"
                                    fill="white"
                                    rx="1"
                                />
                                <rect
                                    x="15"
                                    y="77"
                                    width="8"
                                    height="8"
                                    fill="currentColor"
                                    rx="1"
                                />
                                <rect
                                    x="38"
                                    y="8"
                                    width="6"
                                    height="6"
                                    fill="currentColor"
                                />
                                <rect
                                    x="48"
                                    y="14"
                                    width="6"
                                    height="6"
                                    fill="currentColor"
                                />
                                <rect
                                    x="56"
                                    y="8"
                                    width="6"
                                    height="6"
                                    fill="currentColor"
                                />
                                <rect
                                    x="38"
                                    y="24"
                                    width="6"
                                    height="6"
                                    fill="currentColor"
                                />
                                <rect
                                    x="48"
                                    y="24"
                                    width="6"
                                    height="6"
                                    fill="currentColor"
                                />
                                <rect
                                    x="8"
                                    y="38"
                                    width="6"
                                    height="6"
                                    fill="currentColor"
                                />
                                <rect
                                    x="20"
                                    y="46"
                                    width="6"
                                    height="6"
                                    fill="currentColor"
                                />
                                <rect
                                    x="38"
                                    y="38"
                                    width="6"
                                    height="6"
                                    fill="currentColor"
                                />
                                <rect
                                    x="48"
                                    y="46"
                                    width="6"
                                    height="6"
                                    fill="currentColor"
                                />
                                <rect
                                    x="56"
                                    y="38"
                                    width="6"
                                    height="6"
                                    fill="currentColor"
                                />
                                <rect
                                    x="68"
                                    y="46"
                                    width="6"
                                    height="6"
                                    fill="currentColor"
                                />
                                <rect
                                    x="78"
                                    y="38"
                                    width="6"
                                    height="6"
                                    fill="currentColor"
                                />
                                <rect
                                    x="88"
                                    y="46"
                                    width="6"
                                    height="6"
                                    fill="currentColor"
                                />
                                <rect
                                    x="38"
                                    y="58"
                                    width="6"
                                    height="6"
                                    fill="currentColor"
                                />
                                <rect
                                    x="56"
                                    y="68"
                                    width="6"
                                    height="6"
                                    fill="currentColor"
                                />
                                <rect
                                    x="78"
                                    y="68"
                                    width="6"
                                    height="6"
                                    fill="currentColor"
                                />
                                <rect
                                    x="88"
                                    y="78"
                                    width="6"
                                    height="6"
                                    fill="currentColor"
                                />
                                <rect
                                    x="38"
                                    y="78"
                                    width="6"
                                    height="6"
                                    fill="currentColor"
                                />
                                <rect
                                    x="48"
                                    y="88"
                                    width="6"
                                    height="6"
                                    fill="currentColor"
                                />
                                <rect
                                    x="68"
                                    y="88"
                                    width="6"
                                    height="6"
                                    fill="currentColor"
                                />
                            </svg>

                            {#if activeItemIds.includes(QR_CANVAS_ITEM_ID) && !qrLocked}
                                <div
                                    class="absolute -right-1 -bottom-1 h-3.5 w-3.5 cursor-se-resize rounded-full border-2 border-white bg-emerald-600 shadow-md"
                                    onmousedown={(e) =>
                                        handleOverlayResizeMouseDown(
                                            e,
                                            'qr',
                                            { x: qrX, y: qrY, w: qrW, h: qrH },
                                            qrLocked,
                                        )}
                                    role="button"
                                    tabindex="0"
                                ></div>
                            {/if}
                        </div>
                    {/if}

                    {#if showInstructionsOverlay && currentStage < 3 && !showCanonicalPrintPreview}
                        <div
                            class={`absolute rounded border p-2 text-[8px] leading-tight transition-all ${
                                selectedOverlay === 'instructions' &&
                                !instLocked
                                    ? 'z-30 border-emerald-500 bg-white shadow-md ring-2 ring-emerald-400/80'
                                    : 'z-10 border-slate-300 bg-slate-50/90 text-slate-700'
                            }`}
                            style={`top: ${instY * 2.476}px; left: ${instX * 2.476}px; width: ${instW * 2.476}px; font-family: ${instructionsFontFamily}; color: ${instructionsColor}; font-size: ${instructionsFontSize * 0.9}px;`}
                            onmousedown={(e) =>
                                handleOverlayMouseDown(
                                    e,
                                    'instructions',
                                    { x: instX, y: instY, w: instW, h: instH },
                                    instLocked,
                                )}
                            role="button"
                            tabindex="0"
                        >
                            <span
                                class="mb-0.5 block font-bold text-slate-900 uppercase"
                                >Instructions:</span
                            >
                            {instructionsContent}

                            {#if selectedOverlay === 'instructions' && !instLocked}
                                <div
                                    class="absolute -right-1 -bottom-1 h-3.5 w-3.5 cursor-se-resize rounded-full border-2 border-white bg-emerald-600 shadow-md"
                                    onmousedown={(e) =>
                                        handleOverlayResizeMouseDown(
                                            e,
                                            'instructions',
                                            {
                                                x: instX,
                                                y: instY,
                                                w: instW,
                                                h: instH,
                                            },
                                            instLocked,
                                        )}
                                    role="button"
                                    tabindex="0"
                                ></div>
                            {/if}
                        </div>
                    {/if}

                    {#if showSignatureOverlay && currentStage < 3 && !showCanonicalPrintPreview}
                        <div
                            class={`absolute flex flex-col justify-between rounded border-2 border-slate-400 bg-white p-2 transition-all ${
                                selectedOverlay === 'signature' && !sigLocked
                                    ? 'z-30 border-emerald-500 shadow-md ring-2 ring-emerald-500'
                                    : 'z-10'
                            }`}
                            style={`top: ${sigY * 2.476}px; left: ${sigX * 2.476}px; width: ${sigW * 2.476}px; height: ${sigH * 2.476}px; font-family: ${signatureFontFamily};`}
                            onmousedown={(e) =>
                                handleOverlayMouseDown(
                                    e,
                                    'signature',
                                    { x: sigX, y: sigY, w: sigW, h: sigH },
                                    sigLocked,
                                )}
                            role="button"
                            tabindex="0"
                        >
                            <span
                                class="font-bold tracking-wider uppercase"
                                style={`font-size: ${signatureFontSize * 0.9}px; color: ${signatureColor}; font-family: ${signatureFontFamily};`}
                            >
                                {signatureContent}
                            </span>
                            <div
                                class="mb-1 w-full border-b border-dashed border-slate-400"
                            ></div>

                            {#if selectedOverlay === 'signature' && !sigLocked}
                                <div
                                    class="absolute -right-1 -bottom-1 h-3.5 w-3.5 cursor-se-resize rounded-full border-2 border-white bg-emerald-600 shadow-md"
                                    onmousedown={(e) =>
                                        handleOverlayResizeMouseDown(
                                            e,
                                            'signature',
                                            {
                                                x: sigX,
                                                y: sigY,
                                                w: sigW,
                                                h: sigH,
                                            },
                                            sigLocked,
                                        )}
                                    role="button"
                                    tabindex="0"
                                ></div>
                            {/if}
                        </div>
                    {/if}

                    {#if currentStage < 3 && !showCanonicalPrintPreview}
                        {#each customTextItems as t, idx}
                            <div
                                class={`absolute cursor-move rounded p-0.5 leading-tight font-medium transition-all ${
                                    selectedOverlay === idx && !t.isLocked
                                        ? 'z-30 bg-emerald-50/50 ring-2 ring-emerald-500'
                                        : 'z-10'
                                }`}
                                style={`top: ${t.posY * 2.476}px; left: ${t.posX * 2.476}px; width: ${t.w_mm * 2.476}px; font-size: ${(t.fontSize ?? 10) * 0.9}px; font-family: ${t.fontFamily || 'Inter, sans-serif'}; color: ${t.color || '#0f172a'}; text-align: ${t.align || 'left'}; font-weight: ${t.bold ? 'bold' : 'normal'}; font-style: ${t.italic ? 'italic' : 'normal'};`}
                                onmousedown={(e) =>
                                    handleOverlayMouseDown(
                                        e,
                                        idx,
                                        {
                                            x: t.posX,
                                            y: t.posY,
                                            w: t.w_mm,
                                            h: t.h_mm,
                                        },
                                        t.isLocked,
                                    )}
                                role="button"
                                tabindex="0"
                            >
                                {t.content}
                            </div>
                        {/each}
                    {/if}
                {/if}

                {#if isHtmlMode && currentStage < 3 && !showCanonicalPrintPreview}
                    {#each pageComponents as comp, compIndex (comp.id)}
                        {@const isSelected =
                            (activeComponentIds ?? []).includes(comp.id) ||
                            activeComponentId === comp.id}
                        {@const isPrimarySelected =
                            activeComponentId === comp.id}
                        {@const isRoll = comp.type === 'roll'}
                        {@const isGrid = isQuestionGridComponent(comp)}
                        {@const isText = !isRoll && !isGrid}
                        {@const isInputField =
                            comp.type === 'candidate_name' ||
                            comp.type === 'birthdate' ||
                            comp.type === 'room_seat' ||
                            comp.type === 'custom_input'}
                        {@const fieldInputClass =
                            comp.inputKind === 'multiline'
                                ? 'border border-slate-700 rounded-xs'
                                : comp.inputStyle === 'underline'
                                  ? 'border-b border-slate-700'
                                  : comp.inputStyle === 'dotted_line'
                                    ? 'border-b border-dashed border-slate-400'
                                    : 'border border-slate-700 rounded-xs'}
                        {@const { w_mm: bw, h_mm: bh } =
                            canvasComponentBlockSizeMm(comp)}
                        {@const digitsCount = isRoll
                            ? Number(comp.digitsCount) || 7
                            : 0}
                        {@const gridLabels = isGrid
                            ? gridOptionLabels(comp)
                            : []}
                        {@const qEnd = isGrid
                            ? (Number(comp.startQ) || 1) +
                              (Number(comp.questionsCount) || 1) -
                              1
                            : 0}
                        {@const recognitionGeometry =
                            effectiveRecognitionGeometryMm({
                                kind: isRoll ? 'roll' : 'grid',
                                bubbleDiameterMm: comp.bubbleDiameterMm,
                                bubblePitchMm: comp.bubblePitchMm,
                                rowPitchMm: comp.rowPitchMm,
                            })}
                        {@const bubblePitchMm =
                            recognitionGeometry.bubblePitchMm}
                        {@const bubbleDiameterPx =
                            isRoll || isGrid
                                ? recognitionGeometry.bubbleDiameterMm * 2.476
                                : CANVAS_BUBBLE_DIAMETER_PX}
                        {@const rowPitchMm = recognitionGeometry.rowPitchMm}
                        {@const rowGapPx =
                            isRoll || isGrid
                                ? canvasRollRowGapPx(
                                      rowPitchMm,
                                      bubbleDiameterPx,
                                  )
                                : 0}
                        {@const bubbleGapPx =
                            isRoll || isGrid
                                ? canvasRollBubbleGapPx(
                                      bubblePitchMm,
                                      bubbleDiameterPx,
                                  )
                                : 0}
                        {@const labelFontSizePx = isGrid
                            ? Math.max(
                                  5,
                                  (Number(comp.labelFontSizePt) || 6.5) * 0.9,
                              )
                            : null}
                        {@const labelWidthPx = 3.5 * 2.476}
                        {@const labelGapPx = 1.5 * 2.476}
                        {@const bubbleStyle = `width: ${bubbleDiameterPx}px; height: ${bubbleDiameterPx}px;`}

                        <div
                            class={`absolute overflow-visible rounded-sm transition-all select-none ${
                                isDragging && isDuplicateDragging
                                    ? 'z-40 scale-[1.005] bg-white/95 shadow-2xl ring-2 ring-sky-500'
                                    : isDragging
                                      ? 'z-40 scale-[1.005] bg-white/95 shadow-2xl ring-2 ring-emerald-500'
                                      : isSelected
                                        ? 'z-30 bg-white shadow-sm ring-2 ring-emerald-500'
                                        : 'z-10 bg-white hover:ring-1 hover:ring-slate-400/80'
                            } ${comp.isLocked ? 'cursor-default' : isDuplicateDragging && isDragging ? 'cursor-copy' : isDragging ? 'cursor-grabbing' : 'cursor-grab'}`}
                            data-omr-component-id={comp.id}
                            data-omr-component-type={comp.type}
                            style={`top: ${comp.posY * 2.476}px; left: ${comp.posX * 2.476}px; width: ${bw * 2.476}px; height: ${bh * 2.476}px; z-index: ${isDragging ? 40 : isSelected ? 30 : Number(comp.zIndex) || compIndex + 1}; background-color: ${componentBackgroundColor(comp)};`}
                            onmousedown={(e) =>
                                handleComponentMouseDown(e, comp.id)}
                            role="button"
                            tabindex="0"
                        >
                            {#if isRoll}
                                <!-- Roll / Examinee ID 0-9 Bubble Matrix (print-matched spacing) -->
                                <div
                                    class="flex h-full w-full flex-col justify-start p-1"
                                >
                                    <div
                                        class="mb-1 text-center font-black tracking-wider text-slate-900"
                                        style={`font-size: ${(comp.typography?.fontSizePt ?? 7.5) * 0.9}px; font-family: ${comp.typography?.fontFamily ?? 'Inter, sans-serif'}; color: ${comp.typography?.textColor ?? '#0f172a'}; text-align: ${comp.typography?.textAlign ?? 'center'};`}
                                    >
                                        {comp.title || 'EXAMINEE NUMBER'}
                                    </div>
                                    <!-- Write-in boxes row -->
                                    <div
                                        class="mb-1 flex justify-start"
                                        style={`gap: ${bubbleGapPx}px; padding-left: ${labelWidthPx + labelGapPx}px;`}
                                    >
                                        {#each Array.from( { length: digitsCount } ) as _}
                                            <div
                                                class="border border-slate-900 bg-white"
                                                style={`width: ${bubbleDiameterPx}px; height: ${bubbleDiameterPx * 1.15}px;`}
                                                data-omr-write-in-box="true"
                                            ></div>
                                        {/each}
                                    </div>
                                    <!-- 0-9 Matrix rows: number and bubbles share one compact row rhythm -->
                                    <div
                                        class="flex flex-col"
                                        style={`gap: ${rowGapPx}px;`}
                                        data-omr-designer-rows="true"
                                    >
                                        {#each Array.from( { length: 10 } ) as _, digit}
                                            <div
                                                class="flex items-center justify-start"
                                                style={`gap: ${labelGapPx}px; height: ${bubbleDiameterPx}px;`}
                                                data-omr-designer-row="true"
                                            >
                                                <span
                                                    class="text-right font-mono font-bold"
                                                    style={`width: ${labelWidthPx}px; flex: 0 0 ${labelWidthPx}px; font-size: ${(comp.typography?.fontSizePt ?? 6.5) * 0.8}px; font-family: ${comp.typography?.fontFamily ?? 'monospace'}; color: ${comp.typography?.textColor ?? '#334155'};`}
                                                    data-omr-row-label={digit}
                                                >
                                                    {digit}
                                                </span>
                                                <div
                                                    class="flex items-center"
                                                    style={`gap: ${bubbleGapPx}px;`}
                                                >
                                                    {#each Array.from( { length: digitsCount } ) as _}
                                                        <div
                                                            class="shrink-0 rounded-full border border-slate-800 bg-white"
                                                            style={bubbleStyle}
                                                            data-omr-designer-bubble="true"
                                                        ></div>
                                                    {/each}
                                                </div>
                                            </div>
                                        {/each}
                                    </div>
                                </div>
                            {:else if isGrid}
                                <!-- Question Grid (shared physical header and row geometry) -->
                                <div
                                    class="pointer-events-none flex h-full w-full flex-col justify-start bg-white"
                                    style={`background-color: ${componentBackgroundColor(comp)};`}
                                >
                                    <div
                                        class="truncate rounded-xs bg-slate-900 px-1 text-center font-black tracking-wide text-white uppercase"
                                        style={`height: ${GRID_SECTION_HEADER_HEIGHT_MM * 2.476}px; line-height: ${GRID_SECTION_HEADER_HEIGHT_MM * 2.476}px; font-size: ${Math.max(4.5, (Number(comp.labelFontSizePt) || 6.5) * 0.75)}px; margin-bottom: ${0.5 * 2.476}px;`}
                                    >
                                        {comp.subjectName ||
                                            comp.name ||
                                            'Subject'}
                                    </div>
                                    <!-- Option labels align with the bubble centers below -->
                                    <div
                                        class="mb-0.5 flex items-center justify-start font-mono text-[6.5px] font-bold text-slate-900"
                                    >
                                        <span
                                            aria-hidden="true"
                                            style={`width: ${labelWidthPx}px; flex: 0 0 ${labelWidthPx}px;`}
                                        ></span>
                                        <div
                                            class="flex items-center"
                                            style={`gap: ${bubbleGapPx}px;`}
                                        >
                                            {#each gridLabels as opt}
                                                <span
                                                    class="text-center"
                                                    style={`font-size: ${labelFontSizePx}px; ${bubbleStyle}`}
                                                    >{opt}</span
                                                >
                                            {/each}
                                        </div>
                                    </div>
                                    <!-- Question Rows: number immediately precedes the bubble group -->
                                    <div
                                        class="flex flex-col"
                                        style={`gap: ${rowGapPx}px;`}
                                    >
                                        {#each Array.from( { length: Math.min(Number(comp.questionsCount) || 1, 50) } ) as _, r}
                                            <div
                                                class="flex items-center justify-start"
                                                style={`gap: ${labelGapPx}px; height: ${bubbleDiameterPx}px;`}
                                            >
                                                <span
                                                    class="shrink-0 text-right font-mono font-bold text-slate-600"
                                                    style={`font-size: ${labelFontSizePx}px; width: ${labelWidthPx}px; flex: 0 0 ${labelWidthPx}px;`}
                                                >
                                                    {(Number(comp.startQ) ||
                                                        1) + r}
                                                </span>
                                                <div
                                                    class="flex items-center"
                                                    style={`gap: ${bubbleGapPx}px;`}
                                                >
                                                    {#each gridLabels as _}
                                                        <div
                                                            class="flex shrink-0 items-center justify-center rounded-full border border-slate-800 bg-white text-[5.5px] font-bold text-slate-900 shadow-2xs"
                                                            style={bubbleStyle}
                                                        ></div>
                                                    {/each}
                                                </div>
                                            </div>
                                        {/each}
                                    </div>
                                </div>
                            {:else if comp.type === 'header'}
                                <!-- Document Header (Live print typography) -->
                                <div
                                    class="pointer-events-none flex h-full w-full flex-col justify-between border-b-2 border-slate-900 p-1.5"
                                >
                                    <div
                                        class="flex items-end justify-between gap-2"
                                    >
                                        <div class="min-w-0">
                                            <h1
                                                class="truncate leading-tight font-black"
                                                style={`font-size: ${(comp.typography?.fontSizePt ?? 13) * 0.85}px; font-family: ${comp.typography?.fontFamily ?? 'Inter, sans-serif'}; font-weight: ${comp.typography?.fontWeight ?? 900}; color: ${comp.typography?.textColor ?? '#0f172a'}; text-align: ${comp.typography?.textAlign ?? 'left'};`}
                                            >
                                                {comp.title}
                                            </h1>
                                            {#if comp.subtitle}
                                                <p
                                                    class="mt-0.5 truncate font-semibold tracking-wider text-slate-500"
                                                    style={`font-size: ${Math.max(5, (comp.typography?.fontSizePt ?? 13) * 0.55)}px; font-family: ${comp.typography?.fontFamily ?? 'Inter, sans-serif'}; color: ${comp.typography?.textColor ?? '#64748b'}; text-align: ${comp.typography?.textAlign ?? 'left'};`}
                                                >
                                                    {comp.subtitle}
                                                </p>
                                            {/if}
                                        </div>
                                        <span
                                            class="shrink-0 rounded-xs border border-slate-900 px-1.5 py-0.5 font-mono text-[8px] font-extrabold text-slate-900"
                                        >
                                            {comp.formCode}
                                        </span>
                                    </div>
                                </div>
                            {:else if isInputField}
                                <!-- Candidate/custom input fields keep the label and writing area separate. -->
                                <div
                                    class="pointer-events-none flex h-full w-full flex-col gap-1 rounded-xs border border-slate-700/80 bg-slate-50/80 p-1.5"
                                    style={`background-color: ${componentBackgroundColor(comp)};`}
                                >
                                    <span
                                        class="shrink-0 truncate font-extrabold tracking-tight"
                                        style={`font-size: ${(comp.typography?.fontSizePt ?? 6.5) * 0.9}px; font-family: ${comp.typography?.fontFamily ?? 'Inter, sans-serif'}; color: ${comp.typography?.textColor ?? '#334155'};`}
                                    >
                                        {comp.label}
                                    </span>
                                    <div
                                        class={`min-h-1 w-full flex-1 ${fieldInputClass}`}
                                        style={`background-color: ${componentInputBackgroundColor(comp)};`}
                                    ></div>
                                </div>
                            {:else if comp.type === 'instructions'}
                                <!-- Instructions Notice Box -->
                                <div
                                    class="pointer-events-none flex h-full w-full flex-col rounded-xs border p-2 leading-tight"
                                    style={`border-color: ${comp.borderColor || '#cbd5e1'}; background-color: ${comp.backgroundColor || '#ffffff'}; font-size: ${(comp.typography?.fontSizePt ?? 6.5) * 0.9}px; font-family: ${comp.typography?.fontFamily ?? 'Inter, sans-serif'}; color: ${comp.typography?.textColor ?? '#334155'};`}
                                >
                                    <span
                                        class="mb-0.5 block text-[7px] font-bold tracking-wide text-slate-900"
                                        >{comp.label || 'INSTRUCTIONS'}:</span
                                    >
                                    <span class="line-clamp-3 leading-snug"
                                        >{comp.content}</span
                                    >
                                </div>
                            {:else if comp.type === 'custom_text'}
                                <!-- Custom Text Block -->
                                <div
                                    class="pointer-events-none flex h-full w-full items-center p-1"
                                    style={`font-size: ${(comp.typography?.fontSizePt ?? 9) * 0.9}px; font-family: ${comp.typography?.fontFamily ?? 'Inter, sans-serif'}; font-weight: ${comp.typography?.fontWeight ?? 600}; color: ${comp.typography?.textColor ?? '#0f172a'}; text-align: ${comp.typography?.textAlign ?? 'left'};`}
                                >
                                    <span class="truncate">{comp.text}</span>
                                </div>
                            {:else if comp.type === 'signature'}
                                <!-- Signature Box -->
                                <div
                                    class="pointer-events-none flex h-full w-full flex-col justify-between rounded-xs border border-slate-400 bg-white p-1.5"
                                    style={`background-color: ${componentBackgroundColor(comp)};`}
                                >
                                    <span
                                        class="truncate font-bold tracking-wide"
                                        style={`font-size: ${(comp.typography?.fontSizePt ?? 7.5) * 0.9}px; font-family: ${comp.typography?.fontFamily ?? 'Inter, sans-serif'}; color: ${comp.typography?.textColor ?? '#1e293b'};`}
                                    >
                                        {comp.title}
                                    </span>
                                    <div
                                        class="my-0.5 w-full border-b border-slate-500"
                                    ></div>
                                    <span
                                        class="truncate text-[6.5px] leading-tight text-slate-500"
                                        >{comp.certificationText}</span
                                    >
                                </div>
                            {/if}

                            <!-- Selection Chip on top-right -->
                            {#if isPrimarySelected}
                                <div
                                    class="pointer-events-none absolute -top-3.5 right-1 z-40 flex items-center gap-1 rounded bg-slate-900 px-1.5 py-px font-mono text-[7px] font-bold text-emerald-400 shadow-sm"
                                >
                                    <span>{componentLabel(comp)}</span>
                                    {#if comp.isLocked}
                                        <Lock class="size-2 text-amber-400" />
                                    {/if}
                                </div>
                            {/if}

                            <!-- Lock indicator when unselected but locked -->
                            {#if comp.isLocked && !isSelected}
                                <div
                                    class="pointer-events-none absolute -top-1 -left-1 z-20 flex size-3.5 items-center justify-center rounded-full border border-amber-300 bg-amber-100 text-amber-700"
                                >
                                    <Lock class="size-2" />
                                </div>
                            {/if}

                            <!-- Resize Handle for Text Components -->
                            {#if isText && isPrimarySelected && !comp.isLocked}
                                <div
                                    class="pointer-events-auto absolute -right-1.5 -bottom-1.5 z-40 size-3.5 cursor-se-resize rounded-full border-2 border-white bg-emerald-600 shadow-md"
                                    onmousedown={(e) =>
                                        handleComponentResizeMouseDown(
                                            e,
                                            comp.id,
                                        )}
                                    role="button"
                                    tabindex="0"
                                    title="Drag to resize width and height"
                                ></div>
                            {/if}
                        </div>
                    {/each}
                {/if}

                {#if currentStage === 3 && previewSurface === 'canonical' && (showSmartSnapSearchZone || previewingSearchZone) && smartSnapSearchZone}
                    <svg
                        data-omr-smart-snap-search-zone="true"
                        data-testid="smart-snap-search-zone-overlay"
                        class="pointer-events-none absolute inset-0 z-[12] h-full w-full select-none overflow-hidden text-sky-600"
                        viewBox={`0 0 ${paperWidthMm} ${paperHeightMm}`}
                        preserveAspectRatio="xMidYMid meet"
                        aria-hidden="true"
                        focusable="false"
                    >
                        {#each smartSnapSearchZone.envelopes as envelope (envelope.key)}
                            <rect
                                data-omr-search-envelope={envelope.key}
                                data-omr-search-envelope-kind={envelope.kind}
                                x={envelope.cxMm - envelope.widthMm / 2}
                                y={envelope.cyMm - envelope.heightMm / 2}
                                width={envelope.widthMm}
                                height={envelope.heightMm}
                                rx={envelope.rxMm}
                                ry={envelope.rxMm}
                                transform={`rotate(${envelope.angleDeg} ${envelope.cxMm} ${envelope.cyMm})`}
                                fill="none"
                                stroke="currentColor"
                                stroke-width={0.35}
                                stroke-dasharray="1.4 1"
                                opacity={envelope.isDetailed ? 0.6 : 0.22}
                            />
                        {/each}
                        {#each smartSnapSearchZone.halos as halo (halo.key)}
                            <circle
                                data-omr-search-halo={halo.key}
                                cx={halo.xMm}
                                cy={halo.yMm}
                                r={halo.searchRadiusMm}
                                fill="none"
                                stroke="currentColor"
                                stroke-width={0.3}
                                stroke-dasharray="0.8 0.8"
                                opacity={0.7}
                            />
                        {/each}
                        {#if smartSnapSearchZone.leader}
                            {@const leader = smartSnapSearchZone.leader}
                            <g data-omr-search-measurement="true">
                                <line x1={leader.x1Mm} y1={leader.y1Mm} x2={leader.x2Mm} y2={leader.y2Mm} stroke="currentColor" stroke-width={0.3} />
                                <circle cx={leader.x1Mm} cy={leader.y1Mm} r={0.45} fill="currentColor" />
                                <line x1={leader.x2Mm} y1={leader.y2Mm - 0.7} x2={leader.x2Mm} y2={leader.y2Mm + 0.7} stroke="currentColor" stroke-width={0.3} />
                                <text
                                    x={leader.labelXMm}
                                    y={leader.labelYMm}
                                    text-anchor="middle"
                                    font-size={2.8}
                                    font-weight={600}
                                    fill="currentColor"
                                    stroke="white"
                                    stroke-width={0.8}
                                    paint-order="stroke"
                                    stroke-linejoin="round"
                                >{leader.text}</text>
                            </g>
                        {/if}
                    </svg>
                {/if}

                <!-- STAGE 3+ Multi-Section grids (for Section Bubbles stage) -->
                {#if currentStage >= 3 && previewSurface !== 'overlay'}
                    {#each pageSections as sec, i}
                        {@const globalIndex = sections.findIndex((s) => s.id === sec.id)}
                        {@const resolvedIdx = globalIndex >= 0 ? globalIndex : i}
                        {@const isSelected = selectedSectionIndices.includes(resolvedIdx) || activeSectionIndex === resolvedIdx}
                        {@const isSinglePlottedItem =
                            Array.isArray(sec.optionPointsMm) &&
                            sec.optionPointsMm.length > 0 &&
                            (Number(sec.questionsCount) || 1) === 1}
                        {@const visualBounds = recognitionGuideSizeMm(sec)}
                        {@const originOffset = recognitionOriginOffsetMm(sec)}
                        {@const bubbleGuides = localRecognitionBubbles(sec)}
                        {@const minBubbleX =
                            isSinglePlottedItem && bubbleGuides.length > 0
                                ? Math.min(...bubbleGuides.map((b) => Number(b.x_mm)))
                                : Number(sec.posX) + originOffset.x || 16}
                        {@const minBubbleY =
                            isSinglePlottedItem && bubbleGuides.length > 0
                                ? Math.min(...bubbleGuides.map((b) => Number(b.y_mm)))
                                : Number(sec.posY) + originOffset.y || 56}
                        {@const maxBubbleX =
                            isSinglePlottedItem && bubbleGuides.length > 0
                                ? Math.max(
                                      ...bubbleGuides.map(
                                          (b) => Number(b.x_mm) + Number(b.w_mm),
                                      ),
                                  )
                                : minBubbleX + visualBounds.w_mm}
                        {@const maxBubbleY =
                            isSinglePlottedItem && bubbleGuides.length > 0
                                ? Math.max(
                                      ...bubbleGuides.map(
                                          (b) => Number(b.y_mm) + Number(b.h_mm),
                                      ),
                                  )
                                : minBubbleY + visualBounds.h_mm}
                        {@const blockX = minBubbleX}
                        {@const blockY = minBubbleY}
                        {@const blockWidthMm = Math.max(4, maxBubbleX - minBubbleX)}
                        {@const blockHeightMm = Math.max(4, maxBubbleY - minBubbleY)}
                        <div
                            class={`group/section absolute overflow-visible rounded-lg outline outline-1 transition-all ${sec.isLocked ? 'cursor-default' : isSinglePlottedItem ? 'cursor-pointer' : 'cursor-grab active:cursor-grabbing'} ${
                                isSelected
                                    ? 'z-30 bg-emerald-500/15 shadow-sm ring-2 ring-emerald-400/60 outline-emerald-600 backdrop-blur-[0.5px] outline-dashed'
                                    : 'z-20 bg-emerald-500/5 outline-emerald-500/80 backdrop-blur-[0.5px] outline-dashed hover:outline-emerald-600'
                            }`}
                            style={`top: ${blockY * 2.476}px; left: ${blockX * 2.476}px; width: ${blockWidthMm * 2.476}px; height: ${blockHeightMm * 2.476}px;`}
                            data-omr-section-id={sec.id}
                            onpointermove={(event) => {
                                const points = sec.optionPointsMm;
                                if (!Array.isArray(points) || points.length === 0) return;
                                const optionCount = Math.max(1, Number(sec.optionsCount) || Number(sec.optionLabels?.length) || 4);
                                const rows = Array.isArray(points[0])
                                    ? points
                                    : Array.from({ length: Math.ceil(points.length / optionCount) }, (_, rowIndex) =>
                                        points.slice(rowIndex * optionCount, (rowIndex + 1) * optionCount),
                                    );
                                const bounds = sheetElement?.getBoundingClientRect?.();
                                if (!bounds || bounds.height <= 0) return;
                                const pointerY = ((event.clientY - bounds.top) / bounds.height) * paperHeightMm;
                                inspectedQuestionIndex = rows.reduce((closest, row, rowIndex) => {
                                    const y = Number(row[0]?.y_mm);
                                    const currentY = Number(rows[closest]?.[0]?.y_mm);
                                    return Math.abs(y - pointerY) < Math.abs(currentY - pointerY) ? rowIndex : closest;
                                }, 0);
                            }}
                            onfocusin={() => {
                                inspectedQuestionIndex = 0;
                            }}
                            onmousedown={(e) => handleBlockMouseDown(e, resolvedIdx)}
                            role="button"
                            tabindex="0"
                        >
                            <!-- Item Number & Area Pill Label on Left of Solidified Item -->
                            {#if (Number(sec.questionsCount) || 1) === 1 && sec.type !== 'roll'}
                                <div
                                    class={`pointer-events-none absolute top-1/2 -left-1 -translate-x-full -translate-y-1/2 rounded px-1 py-0.5 font-mono text-[8px] font-bold whitespace-nowrap shadow-xs transition-opacity duration-150 ${
                                        isSelected
                                            ? 'z-30 bg-emerald-700 text-white opacity-100'
                                            : hasActiveSelection
                                              ? 'bg-slate-900/85 text-white opacity-30 group-hover/section:opacity-100'
                                              : 'bg-slate-900/85 text-white opacity-100'
                                    }`}
                                    data-omr-solid-item-badge="true"
                                >
                                    {(sec.subtest_code || sec.area_code) ? `${sec.subtest_code || sec.area_code}·` : ''}Q{sec.startQ ?? 1}
                                </div>
                            {/if}

                            <div class="pointer-events-none absolute inset-0">
                                {#each bubbleGuides as bubble}
                                    {@const layout = bubbleLabelLayout(bubble.label, Number(bubble.w_mm))}
                                    <div
                                        class="absolute flex items-center justify-center rounded-full border border-emerald-600/90 bg-emerald-500/5 font-mono font-bold text-emerald-800/90 shadow-[0_0_0_1px_rgba(255,255,255,0.7)]"
                                        data-omr-recognition-bubble="true"
                                        style={`left: ${(Number(bubble.x_mm) - blockX) * 2.476}px; top: ${(Number(bubble.y_mm) - blockY) * 2.476}px; width: ${Number(bubble.w_mm) * 2.476}px; height: ${Number(bubble.h_mm) * 2.476}px; font-size: ${layout.fontSizePx}px;`}
                                    >
                                        {#if bubble.label && ((Number(sec.questionsCount) || 1) === 1 || sec.type === 'roll')}
                                            {#if layout.showInside}
                                                <span class="truncate leading-none">{bubble.label}</span>
                                            {:else}
                                                <span
                                                    class={`pointer-events-none absolute -top-3.5 left-1/2 -translate-x-1/2 rounded border border-emerald-600/40 bg-white/95 px-1 py-0.2 font-mono text-[7px] font-bold text-emerald-900 shadow-xs whitespace-nowrap transition-opacity duration-150 ${
                                                        hasActiveSelection && !isSelected
                                                            ? 'opacity-0 group-hover/section:opacity-100'
                                                            : 'opacity-100'
                                                    }`}
                                                    data-omr-floating-label="true"
                                                >
                                                    {bubble.label}
                                                </span>
                                            {/if}
                                        {/if}
                                    </div>
                                {/each}
                            </div>

                            {#if isSelected && !sec.isLocked}
                                <!-- Top Action Bar: Copy/Paste Next & Delete (plus 2-Click Align for multi-row blocks) -->
                                <div
                                    class="pointer-events-auto absolute -top-6 left-0 z-40 flex items-center gap-1 select-none"
                                    onmousedown={(e) => e.stopPropagation()}
                                    role="toolbar"
                                    aria-label="Selected item actions"
                                    tabindex="-1"
                                >
                                    {#if (Number(sec.questionsCount) || 1) > 1 && sec.type !== 'roll'}
                                        <button
                                            type="button"
                                            data-omr-auto-grid-trigger="true"
                                            onclick={(e) => {
                                                e.stopPropagation();
                                                autoGridActive = !autoGridActive;
                                                autoGridFirstPointMm = null;
                                                autoGridError = '';
                                            }}
                                            title="Click the first bubble (top-left) and last bubble (bottom-right) to auto-solve origin and spacing"
                                            class={`flex items-center gap-1 rounded-full px-2 py-0.5 text-[9px] font-bold whitespace-nowrap shadow-md ring-2 ring-white transition ${
                                                autoGridActive
                                                    ? 'bg-amber-600 text-white'
                                                    : 'bg-slate-900 text-white hover:bg-emerald-700'
                                            }`}
                                        >
                                            <span>⌖</span>
                                            <span>{autoGridActive ? 'Click 2 Corners…' : '2-Click Align'}</span>
                                        </button>
                                    {/if}
                                    <button
                                        type="button"
                                        data-omr-delete-section="true"
                                        onclick={(e) => {
                                            e.stopPropagation();
                                            deleteSectionAtIndex(resolvedIdx);
                                        }}
                                        title="Delete this item (Del / Backspace)"
                                        class="flex items-center gap-0.5 rounded-full bg-rose-600 px-1.5 py-0.5 text-[9px] font-bold text-white shadow-md ring-2 ring-white transition hover:bg-rose-500"
                                    >
                                        <span>✕</span>
                                    </button>
                                </div>

                                {#if !isSinglePlottedItem}
                                    <!-- Horizontal Option Pitch Handle (A <-> B <-> C <-> D) -->
                                    <div
                                        class="pointer-events-auto absolute -right-3.5 top-1/2 -translate-y-1/2 z-40 flex items-center justify-center cursor-ew-resize select-none"
                                        onmousedown={(e) => handlePitchHandleMouseDown(e, resolvedIdx, 'bubblePitch')}
                                        title="Drag horizontally to adjust option distance (bubble pitch)"
                                        data-omr-pitch-handle="bubblePitch"
                                        role="button"
                                        tabindex="0"
                                    >
                                        <div class="flex items-center gap-1 rounded-full bg-emerald-700 hover:bg-emerald-600 px-2 py-0.5 text-[9px] font-bold text-white shadow-md ring-2 ring-white transition">
                                            <span>↔</span>
                                            <span class="font-mono">{Number(sec.bubblePitchMm).toFixed(1)}mm</span>
                                        </div>
                                    </div>

                                    <!-- Vertical Row Pitch Handle (Q1 <-> Q2) -->
                                    <div
                                        class="pointer-events-auto absolute -bottom-4 left-1/2 -translate-x-1/2 z-40 flex items-center justify-center cursor-ns-resize select-none"
                                        onmousedown={(e) => handlePitchHandleMouseDown(e, resolvedIdx, 'rowPitch')}
                                        title="Drag vertically to adjust question row distance (row pitch)"
                                        data-omr-pitch-handle="rowPitch"
                                        role="button"
                                        tabindex="0"
                                    >
                                        <div class="flex items-center gap-1 rounded-full bg-emerald-700 hover:bg-emerald-600 px-2 py-0.5 text-[9px] font-bold text-white shadow-md ring-2 ring-white transition">
                                            <span>↕</span>
                                            <span class="font-mono">{Number(sec.rowPitchMm).toFixed(1)}mm</span>
                                        </div>
                                    </div>
                                {/if}
                            {/if}
                        </div>
                    {/each}
                {#if currentStage >= 3 && previewSurface !== 'overlay' && hasWriteIn && activeWriteInBox}
                    {@const box = activeWriteInBox}
                    {@const isSelected = selectedOverlay === 'applicant_write_in'}
                    <div
                        class={`absolute z-30 flex flex-col justify-between rounded border-2 border-dashed transition-all cursor-move select-none ${
                            isSelected
                                ? 'border-amber-500 bg-amber-500/15 shadow-md ring-2 ring-amber-400'
                                : 'border-amber-600/80 bg-amber-500/10 hover:border-amber-500 hover:bg-amber-500/15'
                        }`}
                        style={`top: ${box.y_mm * 2.476}px; left: ${box.x_mm * 2.476}px; width: ${box.w_mm * 2.476}px; height: ${box.h_mm * 2.476}px;`}
                        onmousedown={(e) => handleWriteInMouseDown(e, box)}
                        role="button"
                        tabindex="0"
                        title="Applicant Number (Handwritten Box) - Drag to align over handwritten digits"
                        data-omr-write-in-box="true"
                    >
                        <div class="absolute -top-5 left-0 flex items-center gap-1 rounded bg-amber-600 px-1.5 py-0.5 text-[9px] font-bold text-white shadow-xs whitespace-nowrap">
                            <span>Applicant Number (Handwritten Box)</span>
                            <span class="font-mono text-[8px] opacity-80">{Number(box.w_mm).toFixed(0)}×{Number(box.h_mm).toFixed(0)}mm</span>
                            <button
                                type="button"
                                onclick={(e) => { e.stopPropagation(); removeActiveWriteInBox(); }}
                                class="ml-1.5 rounded bg-black/40 hover:bg-rose-700 px-1 text-[8px] text-white pointer-events-auto transition"
                                title="Remove handwritten box from Page {activePageNo}"
                            >✕</button>
                        </div>

                        {#if isSelected}
                            <div
                                class="absolute -right-1.5 -bottom-1.5 h-3.5 w-3.5 cursor-se-resize rounded-full border-2 border-white bg-amber-600 shadow-md"
                                onmousedown={(e) => handleWriteInResizeMouseDown(e, box)}
                                role="button"
                                tabindex="0"
                                aria-label="Resize handwritten applicant box"
                            ></div>
                        {/if}
                    </div>
                {/if}
                {#if currentStage >= 3 && previewSurface !== 'overlay'}
                    {#each activeDocumentFields as field (field.id)}
                        {@const isActive = activeDocumentFieldId === field.id}
                        <div
                            class={`absolute z-30 cursor-move select-none rounded border-2 border-dashed ${
                                isActive
                                    ? 'border-violet-600 bg-violet-600/20 ring-2 ring-violet-400 shadow-md'
                                    : 'border-violet-500 bg-violet-500/10'
                            }`}
                            style={`top: ${Number(field.y_mm) * 2.476}px; left: ${Number(field.x_mm) * 2.476}px; width: ${Number(field.w_mm) * 2.476}px; height: ${Number(field.h_mm) * 2.476}px;`}
                            onmousedown={(event) => beginDocumentFieldPointerAction(event, field)}
                            role="button"
                            tabindex="0"
                            aria-label={`Document field ${field.label || field.type}`}
                            aria-pressed={isActive}
                            data-omr-document-field={field.id}
                        >
                            <div class="pointer-events-none absolute -top-5 left-0 flex items-center gap-1 whitespace-nowrap rounded bg-violet-600 px-1.5 py-0.5 text-[9px] font-bold text-white shadow-xs">
                                <span>{field.label}</span>
                                <span class="font-mono text-[8px] opacity-90">
                                    {Math.round(Number(field.w_mm) || 0)}×{Math.round(Number(field.h_mm) || 0)}mm
                                </span>
                            </div>
                            {#if isActive}
                                {#each [
                                    { handle: 'nw', position: '-left-1.5 -top-1.5 cursor-nwse-resize' },
                                    { handle: 'ne', position: '-right-1.5 -top-1.5 cursor-nesw-resize' },
                                    { handle: 'se', position: '-right-1.5 -bottom-1.5 cursor-nwse-resize' },
                                    { handle: 'sw', position: '-left-1.5 -bottom-1.5 cursor-nesw-resize' },
                                ] as corner}
                                    <div
                                        class={`absolute z-10 size-3.5 rounded-full border-2 border-white bg-violet-600 shadow-md ${corner.position}`}
                                        onmousedown={(event) => beginDocumentFieldPointerAction(event, field, corner.handle)}
                                        role="button"
                                        tabindex="0"
                                        aria-label={`Resize ${field.label || field.type} ${corner.handle} corner`}
                                        data-omr-document-field-handle={corner.handle}
                                    ></div>
                                {/each}
                            {/if}
                        </div>
                    {/each}
                {/if}

                    <!-- In-Progress Consecutive Option Points (Before Solidification) -->
                    {#if plottingConfig?.active && plottingConfig.mode === 'consecutive' && Array.isArray(plottingConfig.pointsMm)}
                        {@const dPx = (Number(plottingConfig.bubbleDiameterMm) || 4.5) * 2.476}
                        {#each plottingConfig.pointsMm as pt, ptIdx}
                            {#if pt && Number.isFinite(pt.x) && Number.isFinite(pt.y)}
                                <div
                                    data-omr-plotting-point={ptIdx}
                                    class="pointer-events-none absolute z-40 flex -translate-x-1/2 -translate-y-1/2 items-center justify-center rounded-full border-2 border-dashed border-amber-500 bg-amber-400/5 font-mono text-[8px] font-black text-amber-950 shadow-sm"
                                    style={`left: ${pt.x * 2.476}px; top: ${pt.y * 2.476}px; width: ${dPx}px; height: ${dPx}px;`}
                                >
                                    <span>{pt.label ?? plottingConfig.optionLabels?.[ptIdx] ?? ''}</span>
                                </div>
                            {/if}
                        {/each}
                    {/if}

                    <!-- Live Ghost Circle Follower Under Mouse Cursor -->
                    {#if plottingConfig?.active && cursorPaperMm}
                        {@const ghostDiameterPx =
                            (Number(plottingConfig.bubbleDiameterMm) || 4.5) *
                            2.476}
                        {#if plottingConfig.mode === 'paste_stamp'}
                            {@const stampSource =
                                plottingConfig.stampSourceSection ??
                                sections[activeSectionIndex]}
                            {@const stampPoints =
                                Array.isArray(stampSource?.optionPointsMm) &&
                                stampSource.optionPointsMm.length > 0
                                    ? stampSource.optionPointsMm
                                    : (plottingConfig.optionLabels ?? ['A', 'B', 'C', 'D']).map(
                                          (lbl, idx) => ({
                                              label: lbl,
                                              x_mm:
                                                  idx *
                                                  (Number(
                                                      stampSource?.bubblePitchMm,
                                                  ) || 6.0),
                                              y_mm: 0,
                                          }),
                                      )}
                            {@const base0 = stampPoints[0] ?? {
                                x_mm: 0,
                                y_mm: 0,
                            }}
                            <div
                                data-omr-ghost-follower="paste_stamp"
                                class="pointer-events-none absolute inset-0 z-50"
                            >
                                {#each stampPoints as spt}
                                    <div
                                        class="absolute flex -translate-x-1/2 -translate-y-1/2 items-center justify-center rounded-full border-2 border-amber-500 bg-amber-400/5 font-mono text-[8px] font-black text-amber-950 shadow-md"
                                        style={`left: ${(cursorPaperMm.x + (Number(spt.x_mm) - Number(base0.x_mm))) * 2.476}px; top: ${(cursorPaperMm.y + (Number(spt.y_mm) - Number(base0.y_mm))) * 2.476}px; width: ${ghostDiameterPx}px; height: ${ghostDiameterPx}px;`}
                                    >
                                    </div>
                                {/each}
                            </div>
                        {:else}
                            {@const activeLabel =
                                plottingConfig.mode === 'respot_single'
                                    ? (plottingConfig.optionLabels?.[
                                          plottingConfig.respotOptionIndex
                                      ] ?? 'A')
                                    : (plottingConfig.optionLabels?.[
                                          plottingConfig.currentOptionIndex
                                      ] ?? 'A')}
                            <div
                                data-omr-ghost-follower="single"
                                class="pointer-events-none absolute z-50 flex -translate-x-1/2 -translate-y-1/2 items-center justify-center rounded-full border-2 border-emerald-500 bg-emerald-500/5 font-mono text-[8px] font-black text-emerald-950 shadow-lg ring-2 ring-white/80"
                                style={`left: ${cursorPaperMm.x * 2.476}px; top: ${cursorPaperMm.y * 2.476}px; width: ${ghostDiameterPx}px; height: ${ghostDiameterPx}px;`}
                            >
                                <span>{activeLabel}</span>
                            </div>
                        {/if}
                    {/if}

                    <!-- Live Multiply Downward Ghost Preview -->
                    {#if plottingConfig?.multiplyPreview?.active}
                        {@const prevSource =
                            plottingConfig.multiplyPreview.sourceSection ??
                            sections[activeSectionIndex]}
                        {#if prevSource && prevSource.type !== 'roll'}
                            {@const prevCount = Math.max(
                                1,
                                Math.min(
                                    100,
                                    Math.round(
                                        Number(plottingConfig.multiplyPreview.count) || 1,
                                    ),
                                ),
                            )}
                            {@const prevPitch =
                                Number(plottingConfig.multiplyPreview.pitchMm) || 6.8}
                            {@const previewItems = multiplyItemsDownward(
                                prevSource,
                                {
                                    count: prevCount,
                                    distancePerItemMm: prevPitch,
                                    areaCode:
                                        plottingConfig.subtest_code ||
                                        prevSource.subtest_code ||
                                        prevSource.area_code ||
                                        plottingConfig.areaCode,
                                    pageNo: prevSource.page_no,
                                    direction: plottingConfig.multiplyPreview.direction,
                                    slantAngleDeg: Number(plottingConfig.multiplyPreview.slantAngleDeg) || 0,
                                },
                            )}
                            <div
                                data-omr-multiply-preview-container="true"
                                class="pointer-events-none absolute inset-0 z-40 select-none"
                            >
                                {#each previewItems as pItem}
                                    {@const itemDPx =
                                        (Number(pItem.bubbleDiameterMm) || 4.5) * 2.476}
                                    <!-- Item badge preview -->
                                    <div
                                        data-omr-multiply-preview-badge="true"
                                        class="absolute -translate-y-1/2 flex items-center gap-1 rounded bg-emerald-700/80 px-1 py-0.5 font-mono text-[8px] font-bold text-white shadow-xs backdrop-blur-xs"
                                        style={`left: ${(Number(pItem.posX) - 2) * 2.476}px; top: ${(Number(pItem.posY) + 2) * 2.476}px;`}
                                    >
                                        <span>{pItem.name}</span>
                                    </div>
                                    <!-- Bubble circles preview -->
                                    {#each pItem.optionPointsMm as optPt, optIdx}
                                        <div
                                            data-omr-multiply-preview-bubble="true"
                                            class="absolute flex -translate-x-1/2 -translate-y-1/2 items-center justify-center rounded-full border-2 border-dashed border-emerald-500 bg-emerald-500/5 font-mono text-[8px] font-black text-emerald-950 shadow-sm"
                                            style={`left: ${optPt.x_mm * 2.476}px; top: ${optPt.y_mm * 2.476}px; width: ${itemDPx}px; height: ${itemDPx}px;`}
                                        >
                                            <span>{pItem.customValuesStr?.split(',')?.[optIdx]?.trim() ?? ''}</span>
                                        </div>
                                    {/each}
                                {/each}
                            </div>
                        {/if}
                    {/if}

                    <!-- Live Applicant Roll Multi-Digit Preview -->
                    {#if plottingConfig?.rollPreview?.active}
                        {@const rollSec = sections.find((s) => s.identity_grid && Number(s.page_no) === activePageNo)}
                        {#if rollSec}
                            {@const prevDigits = Math.max(2, Math.min(10, Number(plottingConfig.rollPreview.digitCount) || 6))}
                            {@const isH = plottingConfig.rollPreview.direction === 'horizontal' || rollSec.rollDirection === 'horizontal'}
                            {@const prevCols = isH ? 10 : prevDigits}
                            {@const prevRows = isH ? prevDigits : 10}
                            {@const bubbleDPx = (Number(rollSec.bubbleDiameterMm) || 4.5) * 2.476}
                            {@const xP = mmToPx(Number(plottingConfig.rollPreview.colPitchMm || rollSec.bubblePitchMm || 5.5))}
                            {@const yP = mmToPx(Number(plottingConfig.rollPreview.rowPitchMm || rollSec.rowPitchMm || 4.8))}
                            {@const oXPx = mmToPx(Number(rollSec.posX) + (isH ? 0 : 2.25))}
                            {@const oYPx = mmToPx(Number(rollSec.posY) + (isH ? 2.25 : 0))}
                            <div
                                data-omr-roll-preview-container="true"
                                class="pointer-events-none absolute inset-0 z-40 select-none"
                            >
                                {#each Array.from({ length: prevRows }) as _, r}
                                    {#each Array.from({ length: prevCols }) as _, c}
                                        <div
                                            data-omr-roll-preview-bubble="true"
                                            class="absolute flex -translate-x-1/2 -translate-y-1/2 items-center justify-center rounded-full border-2 border-dashed border-indigo-500 bg-indigo-400/25 font-mono text-[8px] font-black text-indigo-950 shadow-sm"
                                            style={`left: ${oXPx + c * xP}px; top: ${oYPx + r * yP}px; width: ${bubbleDPx}px; height: ${bubbleDPx}px;`}
                                        >
                                            <span>{isH ? String(c) : String(r)}</span>
                                        </div>
                                    {/each}
                                {/each}
                            </div>
                        {/if}
                    {/if}

                    <!-- 2-Click Span Live Vector Preview -->
                    {#if plottingConfig?.active && plottingConfig.targetType === 'applicant_digit' && plottingConfig.digitSpanMode && plottingConfig.pointsMm?.length === 1 && cursorPaperMm}
                        {@const p0 = plottingConfig.pointsMm[0]}
                        {@const livePoints = interpolateTenDigitPointsFromSpan(p0, cursorPaperMm)}
                        {@const bDPx = (Number(plottingConfig.bubbleDiameterMm) || 4.5) * 2.476}
                        <div class="pointer-events-none absolute inset-0 z-50">
                            {#each livePoints as lpt}
                                <div
                                    data-omr-span-live-bubble="true"
                                    class="absolute flex -translate-x-1/2 -translate-y-1/2 items-center justify-center rounded-full border-2 border-dashed border-indigo-500 bg-indigo-400/30 font-mono text-[8px] font-black text-indigo-950 shadow-md"
                                    style={`left: ${lpt.x * 2.476}px; top: ${lpt.y * 2.476}px; width: ${bDPx}px; height: ${bDPx}px;`}
                                >
                                    <span>{lpt.label}</span>
                                </div>
                            {/each}
                        </div>
                    {/if}

                    <!-- 2-Click Question Span Live Vector Preview -->
                    {#if plottingConfig?.active && !plottingConfig.targetType && plottingConfig.questionSpanMode && plottingConfig.pointsMm?.length === 1 && cursorPaperMm}
                        {@const p0 = plottingConfig.pointsMm[0]}
                        {@const qLabels = Array.isArray(plottingConfig.optionLabels) && plottingConfig.optionLabels.length >= 2 ? plottingConfig.optionLabels : ['A', 'B', 'C', 'D']}
                        {@const livePoints = interpolateOptionPointsFromSpan(p0, cursorPaperMm, qLabels)}
                        {@const bDPx = (Number(plottingConfig.bubbleDiameterMm) || 4.5) * 2.476}
                        <div class="pointer-events-none absolute inset-0 z-50">
                            {#each livePoints as lpt}
                                {@const layout = bubbleLabelLayout(lpt.label, Number(plottingConfig.bubbleDiameterMm) || 4.5)}
                                <div
                                    data-omr-q-span-live-bubble="true"
                                    class="absolute flex -translate-x-1/2 -translate-y-1/2 items-center justify-center rounded-full border border-dashed border-emerald-500 bg-emerald-500/5 font-mono font-bold text-emerald-900 shadow-md"
                                >
                                    {#if layout.showInside}
                                        <span class="truncate leading-none">{lpt.label}</span>
                                    {:else}
                                        <span
                                            class="pointer-events-none absolute -top-3.5 left-1/2 -translate-x-1/2 rounded border border-emerald-600/40 bg-white/95 px-1 py-0.2 font-mono text-[7px] font-bold text-emerald-900 shadow-xs whitespace-nowrap"
                                            data-omr-floating-label="true"
                                        >
                                            {lpt.label}
                                        </span>
                                    {/if}
                                </div>
                            {/each}
                        </div>
                    {/if}

                    {#if autoGridActive && autoGridFirstPointMm}
                        <div
                            data-omr-auto-grid-point="1"
                            class="pointer-events-none absolute z-50 size-4 -translate-x-1/2 -translate-y-1/2 rounded-full border-2 border-white bg-amber-500 shadow-lg ring-2 ring-amber-400/70"
                            style={`left: ${autoGridFirstPointMm.x * 2.476}px; top: ${autoGridFirstPointMm.y * 2.476}px;`}
                        ></div>
                    {/if}
                {/if}
                <!-- Live Center Sizing Preview Gauge When Holding Radius Slider -->
                {#if plottingConfig?.sizingPreviewDiameterMm && Number(plottingConfig.sizingPreviewDiameterMm) > 0}
                    {@const previewDiameterPx = Number(plottingConfig.sizingPreviewDiameterMm) * 2.476}
                    {@const previewRadiusMm = (Number(plottingConfig.sizingPreviewDiameterMm) / 2).toFixed(2)}
                    <div
                        data-omr-sizing-preview-gauge="true"
                        class="pointer-events-none absolute left-1/2 top-1/2 -translate-x-1/2 -translate-y-1/2 z-[70] flex flex-col items-center justify-center"
                    >
                        <!-- Concentric crosshairs -->
                        <div class="absolute h-px w-20 bg-emerald-500/60"></div>
                        <div class="absolute w-px h-20 bg-emerald-500/60"></div>

                        <!-- Center sizing circle gauge -->
                        <div
                            class="rounded-full border-2 border-emerald-500 bg-emerald-500/5 shadow-2xl ring-4 ring-emerald-400/25 backdrop-blur-xs transition-[width,height] duration-75"
                            style={`width: ${previewDiameterPx}px; height: ${previewDiameterPx}px;`}
                        ></div>

                        <!-- Metric pill badge -->
                        <div
                            style={`transform: scale(${1 / scale}); transform-origin: top center;`}
                            class="mt-3 flex items-center gap-1.5 rounded-full border border-emerald-400/80 bg-slate-950/90 px-2.5 py-1 text-[10px] font-mono font-bold text-white shadow-xl backdrop-blur-sm whitespace-nowrap"
                        >
                            <span class="text-slate-500">·</span>
                            <span class="text-slate-300">R {previewRadiusMm} mm</span>
                        </div>
                    </div>
                {/if}

                <!-- Loading Overlay -->
                {#if isComposing}
                    <div
                        class="absolute inset-0 z-40 flex flex-col items-center justify-center rounded-xs bg-white/90 p-6 text-center backdrop-blur-xs"
                    >
                        <div
                            class="mb-3 flex size-10 animate-pulse items-center justify-center rounded-xl bg-slate-900 text-white shadow-md"
                        >
                            <Loader2 class="size-5 animate-spin" />
                        </div>
                        <h4 class="text-xs font-bold text-slate-900">
                            Preparing print sheet
                        </h4>
                        <p
                            class="mt-1 max-w-[240px] text-[10px] text-slate-500"
                        >
                            Generating vector fiducial markers and normalizing
                            print canvas layout…
                        </p>
                    </div>
                {/if}
            </div>
        </div>
    </div>
</div>

<style>
    .canvas-grid {
        background-color: var(--muted);
        background-image: radial-gradient(
            circle,
            color-mix(in srgb, var(--muted-foreground) 28%, transparent) 1px,
            transparent 1px
        );
        background-size: 20px 20px;
    }
    .marker-box {
        width: 24px;
        height: 24px;
        background-color: #0f172a;
        border: 2px solid #0f172a;
    }
    .marker-target {
        width: 24px;
        height: 24px;
        border-radius: 9999px;
        border: 3px solid #0f172a;
        position: absolute;
        background: #ffffff;
    }
    .marker-target::after {
        content: '';
        position: absolute;
        inset: 4px;
        background-color: #0f172a;
        border-radius: 9999px;
    }
    .marker-aruco {
        width: 24px;
        height: 24px;
        border: 2px solid #0f172a;
        background:
            linear-gradient(
                90deg,
                #0f172a 25%,
                transparent 25% 50%,
                #0f172a 50% 75%,
                transparent 75%
            ),
            linear-gradient(
                #0f172a 25%,
                transparent 25% 50%,
                #0f172a 50% 75%,
                transparent 75%
            );
        background-size: 8px 8px;
    }
</style>
