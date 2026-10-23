<script>
    import { untrack } from 'svelte';
    import StudioTip from './StudioTip.svelte';
    import { Badge } from '@/Components/ui/badge';
    import {
        assessUploadedMappingReadiness,
        detectAdjacentVerticalStepMm,
        minimumRecognitionPitchMm,
        MIN_BUBBLE_MM,
        MIN_UPLOADED_BUBBLE_MM,
        multiplyItemsDownward,
        nextItemNumberForArea,
        normalizeOmrAreaCode,
        resetSectionOptionPointsToUniformGrid,
        rescaleSectionOptionPointsPitch,
        resizeSolidItemBubbleDiameter,
        rotateGroupOfItems,
        setSectionAbsoluteTiltDeg,
        syncDeclaredInventoryFromSections,
    } from '@/lib/omrStudioGeometry';
    import {
        physicalInventoryImpact,
        physicalInventoryImpactDigest,
    } from '@/lib/physicalSheetInventory';
    import {
        Plus,
        Trash2,
        Lock,
        Unlock,
        ChevronLeft,
        ChevronRight,
        ChevronDown,
        ChevronUp,
        Scan,
        XCircle,
        Info,
        AlignLeft,
        AlignRight,
        AlignVerticalSpaceAround,
        ArrowDown,
        ArrowRight,
    } from 'lucide-svelte';
    let {
        sections = $bindable([]),
        activeSectionIndex = $bindable(0),
        selectedSectionIndices = $bindable([]),
        isUploadedSource = false,
        pageCount = 1,
        activePageNo = 1,
        documentFields = $bindable([]),
        activeDocumentFieldId = $bindable(null),
        onDocumentFieldsChanged = null,
        declaredInventory = $bindable([]),
        physicalInventory = $bindable({ revision: 0, sections: [] }),
        onPhysicalInventoryChanged = null,
        identity = { method: null },
        onIdentityChanged = null,
        markerProfiles = [],
        aptitudeAreas = [],
        testInstrument = null,
        selectedMarkerProfileId = null,
        onMarkerProfileChange = null,
        showRegistrationControls = true,
        previewRunning = false,
        transitionRunning = false,
        previewError = null,
        mappingErrors = [],
        mappingDirty = false,
        canonicalPreview = null,
        previewSurface = $bindable('canonical'),
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
        canUndo = false,
        canRedo = false,
        onUndo = null,
        onRedo = null,
        onBatchAlign = null,
        onBatchDistribute = null,
        onBatchToggleLock = null,
        onChanged = null,
        onBack = null,
        onNext = null,
        onSmartSnapBubbles = null,
        smartSnapSearchMm = $bindable(5.0),
        showSmartSnapSearchZone = $bindable(true),
        previewingSearchZone = $bindable(false),
        smartSnapSearchZoneScope = $bindable('selected'),
        smartSnapRunning = false,
        onCreateEditableCopy = null,
        creatingEditableCopy = false,
        readOnlyReason = null,
        readOnly = false,
        canUnlockToEdit = false,
        unlockBlockReason = null,
        onUnlockDraft = null,
        unlockingDraft = false,
        compiledVersionNumber = null,
    } = $props();
    const activePageQuestionSections = $derived(
        sections.filter(
            (section) =>
                section &&
                section.type !== 'roll' &&
                !section.identity_grid &&
                (Number(section.page_no) || 1) === Number(activePageNo),
        ),
    );
    const activePageUnlockedSections = $derived(
        activePageQuestionSections.filter(
            (section) => !section.locked && !section.isLocked,
        ),
    );
    const hasSelectedPageQuestion = $derived(
        activePageQuestionSections.includes(sections[activeSectionIndex]) ||
            selectedSectionIndices.some((index) =>
                activePageQuestionSections.includes(sections[index]),
            ),
    );
    let snapOptionsOpen = $state(false);
    let allowanceHovered = $state(false);
    let allowanceFocused = $state(false);
    function syncSearchZonePreview() {
        previewingSearchZone = Boolean(
            snapOptionsOpen || allowanceHovered || allowanceFocused,
        );
    }
    $effect(() => {
        const shouldPreview = Boolean(
            snapOptionsOpen || allowanceHovered || allowanceFocused,
        );
        if (previewingSearchZone !== shouldPreview) {
            previewingSearchZone = shouldPreview;
        }
    });
    $effect(() => () => {
        previewingSearchZone = false;
    });
    const subtests = $derived(testInstrument?.subtests ?? []);
    let selectedPrintedSectionUid = $state(null);
    let showPrintedSectionsManager = $state(false);
    let managerInventory = $state(null);
    let pendingPhysicalChange = $state(null);
    const printedSections = $derived(physicalInventory?.sections ?? []);
    const activePrintedSection = $derived(
        printedSections.find((section) => section.printed_section_uid === selectedPrintedSectionUid) ??
            printedSections[0] ??
            null,
    );
    const activePrintedGuideCount = $derived(
        activePrintedSection?.items?.filter((item) =>
            sections.some((guide) => String(guide.id) === String(item.guide_id)),
        ).length ?? 0,
    );
    const OPTION_PRESETS = [
        { id: 'mcq4', label: 'A–D (4)', tokens: ['A', 'B', 'C', 'D'] },
        { id: 'mcq5', label: 'A–E (5)', tokens: ['A', 'B', 'C', 'D', 'E'] },
        { id: 'pair6', label: 'Paired (6)', tokens: ['A-B', 'A-C', 'A-D', 'B-C', 'B-D', 'C-D'] },
        { id: 'tf', label: 'T/F (2)', tokens: ['TRUE', 'FALSE'] },
        { id: 'likert5', label: '1–5 (5)', tokens: ['1', '2', '3', '4', '5'] },
    ];

    function optionTokensForSection(section) {
        const optionLabels = Array.isArray(section?.optionLabels)
            ? section.optionLabels
                  .map((label) => String(label ?? '').trim())
                  .filter(Boolean)
            : [];
        if (optionLabels.length > 0) {
            return optionLabels;
        }

        const customTokens =
            typeof section?.customValuesStr === 'string'
                ? section.customValuesStr
                      .split(',')
                      .map((token) => token.trim())
                      .filter(Boolean)
                : [];
        if (customTokens.length > 0) {
            return customTokens;
        }

        return Array.isArray(section?.optionPointsMm)
            ? section.optionPointsMm
                  .map((point) => String(point?.label ?? '').trim())
                  .filter(Boolean)
            : [];
    }

    let customOptionTokens = $state('');
    let showCustomOptionTokens = $state(false);

    let selectedPresetId = $derived.by(() => {
        const tokens = plottingConfig.optionLabels ?? ['A', 'B', 'C', 'D'];
        const joined = tokens.join(',');
        if (joined === 'A,B,C,D') return 'mcq4';
        if (joined === 'A,B,C,D,E') return 'mcq5';
        if (joined === 'A-B,A-C,A-D,B-C,B-D,C-D') return 'pair6';
        if (joined === 'TRUE,FALSE') return 'tf';
        if (joined === '1,2,3,4,5') return 'likert5';
        return 'custom';
    });

    function handlePresetSelect(presetId) {
        if (presetId === 'custom') {
            showCustomOptionTokens = true;
            customOptionTokens = (plottingConfig.optionLabels ?? []).join(', ');
        } else {
            const found = OPTION_PRESETS.find((p) => p.id === presetId);
            if (found) {
                showCustomOptionTokens = false;
                applyOptionTokens(found.tokens);
            }
        }
    }
    function applyOptionTokens(tokens) {
        const labels = (Array.isArray(tokens) ? tokens : [])
            .map((token) => String(token).trim())
            .filter(Boolean)
            .slice(0, 10);
        if (labels.length < 2) return;
        plottingConfig.optionLabels = labels;
        plottingConfig.optionsCount = labels.length;
        plottingConfig.currentOptionIndex = 0;
        customOptionTokens = labels.join(', ');
        if (activeSection && activeSection.type !== 'roll') {
            activeSection.optionLabels = labels;
            activeSection.optionsCount = labels.length;
            activeSection.customValuesStr = labels.join(', ');
            sections = [...sections];
            if (isUploadedSource) {
                declaredInventory = syncDeclaredInventoryFromSections(sections, declaredInventory);
            }
            onChanged?.();
        }
    }

    function handleCustomTokensInput(val) {
        customOptionTokens = val;
        const tokens = val.split(',').map((token) => token.trim());
        if (
            tokens.length >= 2 &&
            tokens.length <= 10 &&
            tokens.every((token) => token.length > 0 && token.length <= 32) &&
            new Set(tokens).size === tokens.length
        ) {
            applyOptionTokens(tokens);
        }
    }

    function applyCustomOptionTokens() {
        const tokens = customOptionTokens.split(',').map((token) => token.trim());
        if (
            tokens.length < 2 ||
            tokens.length > 10 ||
            tokens.some((token) => token.length === 0 || token.length > 32) ||
            new Set(tokens).size !== tokens.length
        ) return;
        applyOptionTokens(tokens);
    }
    let minBubbleDiameter = $derived(
        isUploadedSource ? MIN_UPLOADED_BUBBLE_MM : MIN_BUBBLE_MM,
    );
    let defaultBubbleDiameterMm = $derived(isUploadedSource ? 3.0 : 4.5);
    let activeDocumentFields = $derived(
        documentFields.filter(
            (field) => Number(field.page_no ?? 1) === Number(activePageNo),
        ),
    );
    function notifyDocumentFieldsChanged() {
        if (onDocumentFieldsChanged) onDocumentFieldsChanged(documentFields);
        else if (onChanged) onChanged();
    }
    function addDocumentField(preset) {
        const id = typeof crypto !== 'undefined' && crypto.randomUUID
            ? crypto.randomUUID()
            : `document-field-${Date.now()}-${Math.random().toString(36).slice(2)}`;
        const field = {
            id,
            ...preset,
            page_no: Number(activePageNo) || 1,
        };
        documentFields = [...documentFields, field];
        activeDocumentFieldId = id;
        notifyDocumentFieldsChanged();
    }
    function updateDocumentField(fieldId, key, value) {
        documentFields = documentFields.map((field) => {
            if (field.id !== fieldId) return field;
            if (!['x_mm', 'y_mm', 'w_mm', 'h_mm'].includes(key)) {
                return { ...field, [key]: value };
            }

            const nextValue = Number.isFinite(Number(value)) ? Number(value) : 0;
            const minimum = key === 'x_mm' || key === 'y_mm' ? 0 : 1;
            const maxValue = Math.max(
                minimum,
                key === 'x_mm'
                    ? 210 - Number(field.w_mm || 1)
                    : key === 'y_mm'
                      ? 297 - Number(field.h_mm || 1)
                      : key === 'w_mm'
                        ? 210 - Number(field.x_mm || 0)
                        : 297 - Number(field.y_mm || 0),
            );
            return {
                ...field,
                [key]: Math.max(minimum, Math.min(maxValue, nextValue)),
            };
        });
        notifyDocumentFieldsChanged();
    }
    function removeDocumentField(fieldId) {
        documentFields = documentFields.filter((field) => field.id !== fieldId);
        if (activeDocumentFieldId === fieldId) activeDocumentFieldId = null;
        notifyDocumentFieldsChanged();
    }
    let selectedProfile = $derived(
        markerProfiles.find(
            (profile) => Number(profile.id) === Number(selectedMarkerProfileId),
        ) ?? null,
    );

    let activeSection = $derived(sections[activeSectionIndex]);
    const activeSectionOptionTokens = $derived(
        activeSection ? optionTokensForSection(activeSection) : [],
    );
    const activeSectionQuestionCount = $derived(
        Math.max(1, Number(activeSection?.questionsCount) || 1),
    );
    const hasCustomOptionPoints = $derived(
        Boolean(
            activeSection &&
                Array.isArray(activeSection.optionPointsMm) &&
                activeSection.optionPointsMm.length ===
                    (Number(activeSection.questionsCount) || 1) *
                        activeSectionOptionTokens.length,
        ),
    );
    const activeSubtest = $derived(
        subtests.find(
            (s) =>
                s.code ===
                    (activeSection?.subtest_code ||
                        plottingConfig.subtest_code) ||
                Number(s.id) ===
                    Number(
                        activeSection?.test_instrument_subtest_id ||
                            plottingConfig.test_instrument_subtest_id,
                    ),
        ) ?? subtests[0],
    );
    const subtestPlottedCount = $derived(
        sections
            .filter(
                (s) =>
                    s.type !== 'roll' &&
                    (s.subtest_code === activeSubtest?.code ||
                        s.area_code === activeSubtest?.code),
            )
            .reduce((acc, s) => acc + (Number(s.questionsCount) || 1), 0),
    );
    const subtestCapacity = $derived(
        Number(activeSubtest?.items_count ?? activeSubtest?.items?.length ?? 0),
    );
    const isDuplicateNumber = $derived(
        Boolean(
            plottingConfig.itemNumber &&
                sections.some(
                    (s) =>
                        s !== activeSection &&
                        s.id !== activeSection?.id &&
                        s.type !== 'roll' &&
                        (activeSubtest
                            ? s.subtest_code === activeSubtest.code ||
                              s.area_code === activeSubtest.code
                            : s.area_code === plottingConfig.areaCode) &&
                        (Number(s.startQ) === Number(plottingConfig.itemNumber) ||
                            (Number(plottingConfig.itemNumber) >= Number(s.startQ || 1) &&
                                Number(plottingConfig.itemNumber) <
                                    Number(s.startQ || 1) +
                                        Math.max(1, Number(s.questionsCount) || 1))),
                ),
        ),
    );
    function nextPlottingItemNumber(
        areaCode = plottingConfig.areaCode || activeSubtest?.code,
        subtestCode = activeSubtest?.code,
    ) {
        return nextItemNumberForArea(
            sections,
            areaCode,
            subtestCode,
        );
    }

    let knownSectionIds = new Set(sections.map((section) => section.id));
    let lastHydratedSectionId = null;
    let lastHydratedIndex = -1;
    $effect(() => {
        const addedQuestionSectionIds = new Set(
            sections
                .filter(
                    (section) =>
                        !knownSectionIds.has(section.id) &&
                        section.type !== 'roll',
                )
                .map((section) => section.id),
        );
        knownSectionIds = new Set(
            sections.map((section) => section.id),
        );
        if (addedQuestionSectionIds.size === 0) {
            return;
        }
        plottingConfig.itemNumber = nextPlottingItemNumber();

        const selectedSubtest = subtests.find(
            (subtest) =>
                subtest.code === plottingConfig.subtest_code &&
                Number(subtest.id) ===
                    Number(plottingConfig.test_instrument_subtest_id),
        );
        if (!selectedSubtest) {
            return;
        }

        sections = sections.map((section) =>
            addedQuestionSectionIds.has(section.id) && !section.subtest_code
                ? {
                      ...section,
                      subtest_code: selectedSubtest.code,
                      test_instrument_subtest_id: Number(selectedSubtest.id),
                  }
                : section,
        );
        if (onChanged) {
            onChanged();
        }
    });
    let uploadMappingReadiness = $derived(
        assessUploadedMappingReadiness({
            pageCount: Number(pageCount) || 1,
            inventory: declaredInventory,
            identity,
            sections,
        }),
    );
    let uploadedEntrySequence = 0;

    $effect(() => {
        if (previewSurface === 'aligned' && !canonicalPreview?.aligned_url) {
            previewSurface = 'canonical';
        } else if (
            previewSurface === 'overlay' &&
            !canonicalPreview?.overlay_url
        ) {
            previewSurface = 'canonical';
        }
    });

    let multiplyRowCount = $state(9);
    let multiplyRowPitchMm = $state(6.8);
    let multiplyPreviewEnabled = $state(false);
    let questionMultiplyDirection = $state('vertical');
    let questionSpanMode = $state(true);
    let bulkTargetArea = $state('GENERAL');
    let propagationSlantDeg = $state(0);
    let showAdvancedTilt = $state(false);
    let digitSpanMode = $state(true);
    let digitMultiplyDirection = $state('horizontal');
    let digitMultiplyCount = $state(6);
    let digitMultiplyPitchMm = $state(5.5);
    let digitPreviewEnabled = $state(false);
    let measuredVerticalStep = $derived.by(() => {
        if (!activeSection || activeSection.type === 'roll') return null;
        const area = activeSection.area_code || plottingConfig.areaCode;
        return detectAdjacentVerticalStepMm(
            sections,
            area,
            Number(activeSection.page_no) || 1,
        );
    });

    $effect(() => {
        if (measuredVerticalStep != null && measuredVerticalStep > 1.0) {
            multiplyRowPitchMm = measuredVerticalStep;
        }
    });

    $effect(() => {
        if (
            activeSection &&
            activeSection.type !== 'roll' &&
            (Number(activeSection.questionsCount) || 1) === 1 &&
            multiplyPreviewEnabled
        ) {
            plottingConfig.multiplyPreview = {
                active: true,
                count: multiplyRowCount,
                pitchMm: multiplyRowPitchMm,
                direction: questionMultiplyDirection,
                slantAngleDeg: propagationSlantDeg,
                sourceSection: activeSection,
            };
        } else {
            if (plottingConfig.multiplyPreview?.active) {
                plottingConfig.multiplyPreview = {
                    active: false,
                    count: 0,
                    pitchMm: 0,
                    sourceSection: null,
                };
            }
        }
    });
    let previousPageNo = $state(activePageNo);
    $effect(() => {
        if (activePageNo !== previousPageNo) {
            previousPageNo = activePageNo;
            const pageFirstIdx = sections.findIndex(
                (s) => Number(s.page_no || 1) === Number(activePageNo),
            );
            activeSectionIndex = pageFirstIdx;
            selectedSectionIndices = pageFirstIdx >= 0 ? [pageFirstIdx] : [];

            if (activeDocumentFieldId) {
                const currentField = documentFields.find(
                    (f) => f.id === activeDocumentFieldId,
                );
                if (
                    currentField &&
                    Number(currentField.page_no || 1) !== Number(activePageNo)
                ) {
                    activeDocumentFieldId = null;
                }
            }
        }
    });

    let initializedUploadedBubbleDiameter = false;
    $effect(() => {
        const currentIndex = activeSectionIndex;
        const currentSection = activeSection;
        const currentSectionId = currentSection?.id ?? null;

        if (currentIndex < 0 || !currentSection) {
            const hadHydratedSection = lastHydratedIndex >= 0;
            lastHydratedSectionId = null;
            lastHydratedIndex = -1;
            untrack(() => {
                const needsUploadedDefaultDiameter =
                    isUploadedSource &&
                    !initializedUploadedBubbleDiameter &&
                    (plottingConfig.bubbleDiameterMm == null ||
                        Number(plottingConfig.bubbleDiameterMm) === 4.5 ||
                        Number(plottingConfig.bubbleDiameterMm) === 4.2);
                if (needsUploadedDefaultDiameter) {
                    initializedUploadedBubbleDiameter = true;
                }
                if (needsUploadedDefaultDiameter) {
                    plottingConfig.bubbleDiameterMm = 3.0;
                }
                if (hadHydratedSection && !plottingConfig.active) {
                    plottingConfig.itemNumber = nextPlottingItemNumber(
                        plottingConfig.areaCode || activeSubtest?.code,
                        activeSubtest?.code,
                    );
                }
            });
            return;
        }

        if (
            currentIndex === lastHydratedIndex &&
            currentSectionId === lastHydratedSectionId
        ) {
            const nextDiameter = Number(
                currentSection.bubbleDiameterMm || defaultBubbleDiameterMm,
            );
            if (Number(plottingConfig.bubbleDiameterMm) !== nextDiameter) {
                untrack(() => {
                    plottingConfig = {
                        ...plottingConfig,
                        bubbleDiameterMm: nextDiameter,
                    };
                });
            }
            return;
        }

        lastHydratedIndex = currentIndex;
        lastHydratedSectionId = currentSectionId;

        untrack(() => {
            const tokens = optionTokensForSection(currentSection);
            plottingConfig = {
                ...plottingConfig,
                bubbleDiameterMm: Number(
                    currentSection.bubbleDiameterMm || defaultBubbleDiameterMm,
                ),
                areaCode:
                    currentSection.area_code ||
                    currentSection.aptitudeAreaCode ||
                    plottingConfig.areaCode,
                subtest_code:
                    currentSection.subtest_code ?? plottingConfig.subtest_code,
                test_instrument_subtest_id:
                    currentSection.test_instrument_subtest_id != null
                        ? Number(currentSection.test_instrument_subtest_id)
                        : plottingConfig.test_instrument_subtest_id,
                ...(tokens.length > 0
                    ? {
                          optionLabels: tokens,
                          optionsCount: tokens.length,
                      }
                    : {}),
                ...(!plottingConfig.active
                    ? { itemNumber: Number(currentSection.startQ || 1) }
                    : {}),
            };

            if (tokens.length > 0) {
                customOptionTokens = tokens.join(', ');
                showCustomOptionTokens = !OPTION_PRESETS.some(
                    (preset) =>
                        preset.tokens.length === tokens.length &&
                        preset.tokens.every(
                            (token, index) => token === tokens[index],
                        ),
                );
            }
        });
    });
    let questionBubblesCollapsed = $state(false);
    let applicantNumberCollapsed = $state(false);
    let documentFieldsCollapsed = $state(false);
    let closedAptitudeGroups = $state(new Set());
    let cachedQuestionOptionsCount = 4;
    let cachedQuestionOptionLabels = ['A', 'B', 'C', 'D'];

    const questionSections = $derived(
        sections
            .map((sec, idx) => ({ ...sec, originalIndex: idx }))
            .filter(
                (sec) =>
                    sec.type !== 'roll' &&
                    Number(sec.page_no || 1) === Number(activePageNo),
            ),
    );
    const sectionsByAptitude = $derived.by(() => {
        const map = new Map();
        for (const sec of questionSections) {
            const code = normalizeOmrAreaCode(
                sec.area_code || sec.aptitudeAreaCode || 'GENERAL',
            );
            if (!map.has(code)) {
                map.set(code, []);
            }
            map.get(code).push(sec);
        }
        return Array.from(map.entries()).map(([code, items]) => {
            const areaDef = aptitudeAreas.find((a) => a.code === code);
            return {
                code,
                name: areaDef?.name || code,
                items,
                totalQuestions: items.reduce(
                    (acc, it) => acc + (Number(it.questionsCount) || 1),
                    0,
                ),
            };
        });
    });

    function toggleAptitudeGroup(code) {
        if (closedAptitudeGroups.has(code)) {
            closedAptitudeGroups.delete(code);
        } else {
            closedAptitudeGroups.add(code);
        }
        closedAptitudeGroups = new Set(closedAptitudeGroups);
    }
    function executeMultiplyDownward() {
        if (!activeSection || activeSection.type === 'roll') return;
        const count = Math.max(
            1,
            Math.min(100, Math.round(Number(multiplyRowCount) || 1)),
        );
        const pitch = Number(multiplyRowPitchMm) || 6.8;

        const newItems = multiplyItemsDownward(
            activeSection,
            {
                count,
                distancePerItemMm: pitch,
                areaCode: activeSection.area_code || plottingConfig.areaCode,
                pageNo: activeSection.page_no,
                direction: questionMultiplyDirection,
                slantAngleDeg: propagationSlantDeg,
            },
            sections,
        );

        sections = [...sections, ...newItems];
        activeSectionIndex = sections.length - 1;
        plottingConfig = {
            ...plottingConfig,
            itemNumber: nextItemNumberForArea(
                sections,
                activeSection.area_code || plottingConfig.areaCode,
            ),
            multiplyPreview: {
                active: false,
                count: 0,
                pitchMm: 0,
                sourceSection: null,
            },
        };
        declaredInventory = syncDeclaredInventoryFromSections(
            sections,
            declaredInventory,
        );
        if (onChanged) onChanged();
    }

    function startDigitPlotting(startDigit = 0) {
        if (!plottingConfig.targetType) {
            cachedQuestionOptionsCount = Number(plottingConfig.optionsCount) || 4;
            cachedQuestionOptionLabels = Array.isArray(plottingConfig.optionLabels)
                ? [...plottingConfig.optionLabels]
                : ['A', 'B', 'C', 'D'];
        }
        plottingConfig.targetType = 'applicant_digit';
        plottingConfig.mode = 'applicant_digit';
        plottingConfig.digitSpanMode = digitSpanMode;
        plottingConfig.optionsCount = 10;
        plottingConfig.optionLabels = ['0', '1', '2', '3', '4', '5', '6', '7', '8', '9'];
        plottingConfig.currentOptionIndex = startDigit;
        plottingConfig.pointsMm = [];
        plottingConfig.digitCount = digitMultiplyCount;
        plottingConfig.stepPitchMm = digitMultiplyPitchMm;
        plottingConfig.rollDirection = digitMultiplyDirection === 'horizontal' ? 'vertical' : 'horizontal';
        plottingConfig.active = true;
    }

    function toggleDigitPlotting() {
        if (plottingConfig.active && plottingConfig.targetType === 'applicant_digit') {
            plottingConfig.active = false;
            plottingConfig.pointsMm = [];
            plottingConfig.currentOptionIndex = 0;
            plottingConfig.targetType = undefined;
            plottingConfig.mode = 'consecutive';
            plottingConfig.optionsCount = cachedQuestionOptionsCount;
            plottingConfig.optionLabels = cachedQuestionOptionLabels;
        } else {
            startDigitPlotting(0);
        }
    }

    function updateRollPreview() {
        if (!digitPreviewEnabled) {
            if (plottingConfig.rollPreview?.active) {
                plottingConfig = {
                    ...plottingConfig,
                    rollPreview: { active: false },
                };
            }
            return;
        }
        const rollSec = sections.find(
            (s) => s.identity_grid && Number(s.page_no) === (activeSection?.page_no || 1),
        );
        plottingConfig = {
            ...plottingConfig,
            rollPreview: {
                active: true,
                digitCount: digitMultiplyCount,
                direction: digitMultiplyDirection,
                colPitchMm: digitMultiplyDirection === 'horizontal' ? digitMultiplyPitchMm : (rollSec?.bubblePitchMm || 5.5),
                rowPitchMm: digitMultiplyDirection === 'vertical' ? digitMultiplyPitchMm : (rollSec?.rowPitchMm || 4.8),
            },
        };
    }

    function executeGenerateDigits() {
        const pageNo = activeSection?.page_no || 1;
        let rollIndex = sections.findIndex((s) => s.identity_grid && Number(s.page_no) === pageNo);
        if (rollIndex < 0) {
            addApplicantGrid(pageNo);
            rollIndex = sections.findIndex((s) => s.identity_grid && Number(s.page_no) === pageNo);
        }
        if (rollIndex < 0) return;

        const rollSec = { ...sections[rollIndex] };
        rollSec.questionsCount = Math.max(2, Math.min(10, Number(digitMultiplyCount) || 6));
        updateIdentityDeclaration({
            ...identity,
            digit_count: rollSec.questionsCount,
        });

        if (digitMultiplyDirection === 'horizontal') {
            rollSec.rollDirection = 'vertical';
            rollSec.bubblePitchMm = Number(digitMultiplyPitchMm) || 5.5;
        } else {
            rollSec.rollDirection = 'horizontal';
            rollSec.rowPitchMm = Number(digitMultiplyPitchMm) || 4.8;
        }

        const cols = rollSec.rollDirection === 'horizontal' ? 10 : rollSec.questionsCount;
        const rows = rollSec.rollDirection === 'horizontal' ? rollSec.questionsCount : 10;
        rollSec.w_mm = Number((Number(rollSec.bubbleDiameterMm || 4.5) + (cols - 1) * Number(rollSec.bubblePitchMm)).toFixed(2));
        rollSec.h_mm = Number((Number(rollSec.bubbleDiameterMm || 4.5) + (rows - 1) * Number(rollSec.rowPitchMm)).toFixed(2));

        sections = sections.map((s, idx) => idx === rollIndex ? rollSec : s);
        activeSectionIndex = rollIndex;
        plottingConfig = {
            ...plottingConfig,
            rollPreview: { active: false },
        };
        handleInput();
        if (onChanged) onChanged();
    }

    function handleChipClick(e, secIdx) {
        const sec = sections[secIdx];
        if (!sec) return;

        const isModifier = e.shiftKey || e.ctrlKey || e.metaKey;
        if (isModifier) {
            if (selectedSectionIndices.includes(secIdx)) {
                selectedSectionIndices = selectedSectionIndices.filter(
                    (i) => i !== secIdx,
                );
                if (activeSectionIndex === secIdx) {
                    activeSectionIndex = selectedSectionIndices.at(-1) ?? -1;
                }
            } else {
                selectedSectionIndices = [...selectedSectionIndices, secIdx];
                activeSectionIndex = secIdx;
            }
        } else {
            selectedSectionIndices = [secIdx];
            activeSectionIndex = secIdx;
            if (sec.bubbleDiameterMm) {
                plottingConfig.bubbleDiameterMm = Number(sec.bubbleDiameterMm);
            }
            if (sec.area_code) {
                plottingConfig.areaCode = sec.area_code;
            }
        }
    }

    function clearSectionMultiSelect() {
        selectedSectionIndices = activeSectionIndex >= 0 ? [activeSectionIndex] : [];
    }

    function selectAllInArea(groupItems) {
        const itemIndices = groupItems.map((item) => item.originalIndex);
        const allInAreaSelected =
            itemIndices.length > 0 &&
            itemIndices.every((idx) => selectedSectionIndices.includes(idx));
        if (allInAreaSelected) {
            selectedSectionIndices = selectedSectionIndices.filter(
                (idx) => !itemIndices.includes(idx),
            );
        } else {
            selectedSectionIndices = [
                ...new Set([...selectedSectionIndices, ...itemIndices]),
            ];
        }
        if (selectedSectionIndices.length > 0) {
            activeSectionIndex =
                selectedSectionIndices[selectedSectionIndices.length - 1];
        } else {
            activeSectionIndex = -1;
        }
    }

    function executeBulkReassignArea() {
        const targetArea = normalizeOmrAreaCode(bulkTargetArea || plottingConfig.areaCode || 'GENERAL');
        if (!targetArea || selectedSectionIndices.length === 0) return;
        const targetSubtest = subtests.find(
            (subtest) => subtest.code === targetArea,
        );

        sections = sections.map((sec, idx) => {
            if (selectedSectionIndices.includes(idx) && sec.type !== 'roll') {
                return {
                    ...sec,
                    area_code: targetArea,
                    ...(targetSubtest
                        ? {
                              subtest_code: targetSubtest.code,
                              test_instrument_subtest_id: Number(targetSubtest.id),
                          }
                        : {}),
                    name: `${targetArea} · Q${sec.startQ ?? 1}`,
                };
            }
            return sec;
        });

        plottingConfig.areaCode = targetArea;
        declaredInventory = syncDeclaredInventoryFromSections(sections, declaredInventory);
        if (onChanged) onChanged();
    }

    function executeBulkDelete() {
        if (selectedSectionIndices.length === 0) return;
        const toDelete = new Set(selectedSectionIndices);
        sections = sections.filter((_, idx) => !toDelete.has(idx));
        selectedSectionIndices = [];
        activeSectionIndex = -1;
        declaredInventory = syncDeclaredInventoryFromSections(sections, declaredInventory);
        plottingConfig.itemNumber = nextPlottingItemNumber();
        if (onChanged) onChanged();
    }

    function executeRotateGroup(angleDeg) {
        if (selectedSectionIndices.length === 0 || angleDeg === 0) return;
        sections = rotateGroupOfItems(sections, selectedSectionIndices, angleDeg);
        if (onChanged) onChanged();
    }

    function executeRotateActiveItem(angleDeg) {
        if (
            !activeSection ||
            activeSection.type === 'roll' ||
            (Number(activeSection.questionsCount) || 1) !== 1 ||
            angleDeg === 0
        ) {
            return;
        }
        sections = sections.map((sec, idx) =>
            idx === activeSectionIndex ? rotateSolidItem(sec, angleDeg) : sec,
        );
        if (onChanged) onChanged();
    }
    function applyActiveSectionTilt(targetDeg) {
        if (!activeSection || activeSection.type === 'roll') return;
        sections[activeSectionIndex] = setSectionAbsoluteTiltDeg(
            activeSection,
            targetDeg,
        );
        onChanged?.();
    }
    function subtestCodeFor(section) {
        const subtest = subtests.find(
            (candidate) =>
                candidate.code === section?.subtest_code ||
                Number(candidate.id) ===
                    Number(section?.test_instrument_subtest_id),
        );

        return subtest?.code ?? '';
    }

    function handleSubtestChange(rawSubtestCode) {
        const code =
            rawSubtestCode?.target?.value ??
            rawSubtestCode?.currentTarget?.value ??
            rawSubtestCode;
        const subtest = subtests.find(
            (candidate) => candidate.code === code,
        );
        if (!subtest) {
            return;
        }

        plottingConfig.subtest_code = subtest.code;
        plottingConfig.test_instrument_subtest_id = Number(subtest.id);
        plottingConfig.areaCode = subtest.code;
        plottingConfig.itemNumber =
            activeSectionIndex === -1
                ? nextPlottingItemNumber(subtest.code, subtest.code)
                : nextItemNumberForArea(
                      sections,
                      subtest.code,
                      subtest.code,
                  );

        if (activeSectionIndex >= 0) {
            const targetIndices =
                selectedSectionIndices.length > 0
                    ? selectedSectionIndices
                    : [activeSectionIndex];
            sections = sections.map((section, index) => {
                if (
                    targetIndices.includes(index) &&
                    section.type !== 'roll' &&
                    !section.isLocked
                ) {
                    return {
                        ...section,
                        subtest_code: subtest.code,
                        test_instrument_subtest_id: Number(subtest.id),
                        area_code: subtest.code,
                        name: `${subtest.code} · Q${section.startQ ?? 1}`,
                    };
                }
                return section;
            });
        }
        if (isUploadedSource) {
            declaredInventory = syncDeclaredInventoryFromSections(
                sections,
                declaredInventory,
            );
        }
        if (onChanged) {
            onChanged();
        }
    }

    function handleAreaChange(rawArea) {
        const val = rawArea?.target?.value ?? rawArea?.currentTarget?.value ?? rawArea;
        const nextArea = normalizeOmrAreaCode(val);
        plottingConfig.areaCode = nextArea;
        if (
            activeSectionIndex >= 0 &&
            sections[activeSectionIndex] &&
            sections[activeSectionIndex].type !== 'roll'
        ) {
            const sec = sections[activeSectionIndex];
            sec.area_code = nextArea;
            sec.aptitudeAreaCode = nextArea;
            sec.name = `${nextArea} · Q${sec.startQ ?? 1}`;
            sections = [...sections];
            if (isUploadedSource) {
                declaredInventory = syncDeclaredInventoryFromSections(
                    sections,
                    declaredInventory,
                );
            }
            if (onChanged) onChanged();
        } else if (activeSectionIndex === -1) {
            plottingConfig.itemNumber = nextPlottingItemNumber(
                nextArea,
                activeSubtest?.code,
            );
        }
    }

    function handleItemNumberChange(rawNum) {
        const val = rawNum?.target?.value ?? rawNum?.currentTarget?.value ?? rawNum;
        const nextNum = Number(val) || 1;
        plottingConfig.itemNumber = nextNum;
        if (
            activeSectionIndex >= 0 &&
            sections[activeSectionIndex] &&
            sections[activeSectionIndex].type !== 'roll'
        ) {
            const sec = sections[activeSectionIndex];
            sec.startQ = nextNum;
            const code = normalizeOmrAreaCode(sec.subtest_code || sec.area_code || sec.aptitudeAreaCode || plottingConfig.subtest_code || plottingConfig.areaCode);
            sec.name = `${code} · Q${nextNum}`;
            sections = [...sections];
            if (isUploadedSource) {
                declaredInventory = syncDeclaredInventoryFromSections(
                    sections,
                    declaredInventory,
                );
            }
            if (onChanged) onChanged();
        }
    }


    function startConsecutiveItemPlotting() {
        if (plottingConfig.active && plottingConfig.targetType === 'applicant_digit') {
            plottingConfig.pointsMm = [];
        }
        const area = normalizeOmrAreaCode(
            plottingConfig.areaCode ||
                aptitudeAreas?.[0]?.code ||
                'GENERAL',
        );
        const selectedSubtest =
            subtests.find(
                (subtest) =>
                    subtest.code ===
                        (activeSection?.subtest_code ||
                            plottingConfig.subtest_code) ||
                    Number(subtest.id) ===
                        Number(
                            activeSection?.test_instrument_subtest_id ??
                                plottingConfig.test_instrument_subtest_id,
                        ),
            ) ?? subtests[0];
        if (selectedSubtest) {
            plottingConfig.subtest_code = selectedSubtest.code;
            plottingConfig.test_instrument_subtest_id = Number(
                selectedSubtest.id,
            );
        }
        const existingLabels = Array.isArray(plottingConfig.optionLabels)
            ? plottingConfig.optionLabels.filter((label) => String(label).trim())
            : [];
        const labels = existingLabels.length >= 2
            ? existingLabels.slice(0, 10)
            : ['A', 'B', 'C', 'D'];
        const optCount = labels.length;
        plottingConfig.areaCode = area;
        plottingConfig.optionsCount = optCount;
        plottingConfig.optionLabels = labels;
        plottingConfig.itemNumber =
            Number(plottingConfig.itemNumber) > 0
                ? Number(plottingConfig.itemNumber)
                : nextItemNumberForArea(sections, area);
        plottingConfig.currentOptionIndex = 0;
        plottingConfig.pointsMm = [];
        plottingConfig.targetType = undefined;
        plottingConfig.questionSpanMode = questionSpanMode;
        plottingConfig.mode = 'consecutive';
        plottingConfig.active = true;
    }

    function setPlottingOptionCount(count) {
        const optCount = Math.max(2, Math.min(10, Number(count) || 4));
        const alphabet = ['A', 'B', 'C', 'D', 'E', 'F', 'G', 'H', 'I', 'J'];
        const existingLabels = Array.isArray(plottingConfig.optionLabels)
            ? [...plottingConfig.optionLabels]
            : [];
        const currentIsAlphabetPrefix = existingLabels.length > 0 &&
            existingLabels.every((label, index) => label === alphabet[index]);
        let labels;
        if (currentIsAlphabetPrefix) {
            labels = alphabet.slice(0, optCount);
        } else {
            labels = existingLabels.slice(0, optCount);
            while (labels.length < optCount) {
                const next = alphabet.find((label) => !labels.includes(label));
                labels.push(next ?? String(labels.length + 1));
            }
        }
        plottingConfig.optionsCount = optCount;
        plottingConfig.optionLabels = labels;
        plottingConfig.currentOptionIndex = Math.min(
            plottingConfig.currentOptionIndex ?? 0,
            labels.length - 1,
        );

        if (activeSection && activeSection.type !== 'roll') {
            const existingCount = Number(activeSection.optionsCount) || (activeSection.optionLabels?.length ?? 4);
            activeSection.optionsCount = optCount;
            activeSection.optionLabels = labels;
            activeSection.customValuesStr = labels.join(', ');
            if (Array.isArray(activeSection.optionPointsMm) && activeSection.optionPointsMm.length > 0) {
                if (optCount > existingCount) {
                    const pts = [...activeSection.optionPointsMm];
                    const pLast = pts[pts.length - 1];
                    const pLastX = Number(pLast.x_mm ?? pLast.x);
                    const pLastY = Number(pLast.y_mm ?? pLast.y);
                    const pPrev = pts[pts.length - 2];
                    const pPrevX = pPrev ? Number(pPrev.x_mm ?? pPrev.x) : pLastX - 5.5;
                    const pPrevY = pPrev ? Number(pPrev.y_mm ?? pPrev.y) : pLastY;
                    const dx = pLastX - pPrevX;
                    const dy = pLastY - pPrevY;
                    for (let i = pts.length; i < optCount; i++) {
                        const step = i - pts.length + 1;
                        const nx = Number((pLastX + dx * step).toFixed(2));
                        const ny = Number((pLastY + dy * step).toFixed(2));
                        pts.push({
                            label: labels[i],
                            x: nx,
                            y: ny,
                            x_mm: nx,
                            y_mm: ny,
                        });
                    }
                    activeSection.optionPointsMm = pts;
                } else if (optCount < existingCount) {
                    activeSection.optionPointsMm = activeSection.optionPointsMm.slice(0, optCount);
                }
            }
            sections = [...sections];
            if (onChanged) onChanged();
        }
    }
    function handleSliderPointerDown(val) {
        const nextDiameter = Number(
            Math.max(
                minBubbleDiameter,
                Math.min(8.5, Number(val) || defaultBubbleDiameterMm),
            ).toFixed(2),
        );
        plottingConfig = {
            ...plottingConfig,
            sizingPreviewDiameterMm: nextDiameter,
        };
        if (typeof window !== 'undefined') {
            window.addEventListener('pointerup', handleSliderPointerUp, { once: true });
            window.addEventListener('mouseup', handleSliderPointerUp, { once: true });
        }
    }

    function handleSliderPointerUp() {
        plottingConfig = {
            ...plottingConfig,
            sizingPreviewDiameterMm: null,
        };
    }

    function handleRadiusSliderInput(rawDiameter) {
        const nextDiameter = Number(
            Math.max(
                minBubbleDiameter,
                Math.min(8.5, Number(rawDiameter) || defaultBubbleDiameterMm),
            ).toFixed(2),
        );
        plottingConfig = {
            ...plottingConfig,
            bubbleDiameterMm: nextDiameter,
            ...(plottingConfig.sizingPreviewDiameterMm != null
                ? { sizingPreviewDiameterMm: nextDiameter }
                : {}),
        };

        const targetIndices = selectedSectionIndices.length > 0
            ? selectedSectionIndices
            : (activeSectionIndex >= 0 ? [activeSectionIndex] : []);

        if (targetIndices.length > 0) {
            let modified = false;
            sections = sections.map((s, idx) => {
                if (targetIndices.includes(idx) && s.type !== 'roll' && !s.isLocked) {
                    modified = true;
                    const tokens = optionTokensForSection(s);
                    return resizeSolidItemBubbleDiameter(
                        tokens.length > 0
                            ? { ...s, customValuesStr: tokens.join(', ') }
                            : s,
                        nextDiameter,
                        minBubbleDiameter,
                    );
                }
                return s;
            });
            if (modified) {
                if (isUploadedSource) {
                    declaredInventory = syncDeclaredInventoryFromSections(
                        sections,
                        declaredInventory,
                    );
                }
                if (onChanged) onChanged();
            }
        }
    }

    function startRespotOptionOnActiveItem(optIdx) {
        if (!activeSection || activeSection.isLocked) return;
        const labels = (activeSection.customValuesStr || 'A, B, C, D')
            .split(',')
            .map((s) => s.trim())
            .filter(Boolean);
        plottingConfig.optionLabels = labels;
        plottingConfig.respotSectionIndex = activeSectionIndex;
        plottingConfig.respotOptionIndex = optIdx;
        plottingConfig.mode = 'respot_single';
        plottingConfig.active = true;
    }

    function startCopyPasteActiveItem() {
        if (!activeSection) return;
        const targetArea = normalizeOmrAreaCode(
            plottingConfig.areaCode || activeSection.area_code,
        );
        plottingConfig.stampSourceSection = JSON.parse(
            JSON.stringify(activeSection),
        );
        plottingConfig.areaCode = targetArea;
        plottingConfig.itemNumber = nextPlottingItemNumber(
            targetArea,
            activeSubtest?.code,
        );
        plottingConfig.mode = 'paste_stamp';
        plottingConfig.active = true;
    }

    function deleteActiveItemFromPanel() {
        if (!activeSection || activeSection.isLocked) return;
        const idx = activeSectionIndex;
        sections = sections.filter((_, i) => i !== idx);
        activeSectionIndex = Math.max(0, Math.min(idx, sections.length - 1));
        plottingConfig.itemNumber = nextPlottingItemNumber();
        if (isUploadedSource) {
            declaredInventory = syncDeclaredInventoryFromSections(
                sections,
                declaredInventory,
            );
        }
        if (onChanged) onChanged();
    }
    function addSection() {
        const nextNum = sections.length + 1;
        const lastSec = sections[sections.length - 1];
        const nextStartQ = lastSec
            ? Number(lastSec.startQ) + Number(lastSec.questionsCount)
            : 1;

        const newSec = {
            id: `Section_${String.fromCharCode(64 + nextNum)}`,
            name: `Section ${String.fromCharCode(64 + nextNum)}`,
            type: 'mcq',
            posX: Math.min(125, 25 + (nextNum - 1) * 20),
            posY: 72,
            w_mm: 75,
            h_mm: 140,
            questionsCount: 20,
            cols: 1,
            optionsCount: 4,
            startQ: nextStartQ,
            customValuesStr: 'A, B, C, D',
            bubblePitchMm: 12.0,
            rowPitchMm: 6.5,
            isLocked: false,
        };

        sections = [...sections, newSec];
        activeSectionIndex = sections.length - 1;
        if (onChanged) onChanged();
    }

    function addSingleQuestionBlock() {
        const nextNum = sections.length + 1;
        const lastSec = sections[sections.length - 1];
        const nextStartQ = lastSec
            ? Number(lastSec.startQ) + Number(lastSec.questionsCount)
            : 1;

        const newSec = {
            id: `Question_${nextStartQ}`,
            name: `Question ${nextStartQ} (Callout / Single Q)`,
            type: 'mcq',
            posX: 25,
            posY: Math.min(220, 72 + sections.length * 15),
            w_mm: 75,
            h_mm: 18,
            questionsCount: 1,
            cols: 1,
            optionsCount: 4,
            startQ: nextStartQ,
            customValuesStr: 'A, B, C, D',
            bubblePitchMm: 12.0,
            rowPitchMm: 7.0,
            isLocked: false,
        };

        sections = [...sections, newSec];
        activeSectionIndex = sections.length - 1;
        if (onChanged) onChanged();
    }

    function removeSection(idx) {
        if (sections.length <= 1 && !isUploadedSource) return;
        sections = sections.filter((_, i) => i !== idx);
        activeSectionIndex = Math.max(0, idx - 1);
        plottingConfig.itemNumber = nextPlottingItemNumber();
        if (onChanged) onChanged();
    }

    function handleInput() {
        if (onChanged) onChanged('mapping');
    }
    function updateCustomOptionPoint(pointIndex, coordinate, rawValue, rowEnd) {
        if (!activeSection || activeSection.isLocked || activeSection.locked) return;

        const points = activeSection.optionPointsMm.map((point) => ({ ...point }));
        const currentPoint = points[pointIndex];
        if (!currentPoint) return;

        const nextValue = Number(rawValue);
        if (!Number.isFinite(nextValue)) return;

        if (coordinate === 'deltaX') {
            const oldDeltaX =
                Number(points[pointIndex + 1].x_mm) - Number(currentPoint.x_mm);
            const shiftX = nextValue - oldDeltaX;
            for (let index = pointIndex + 1; index < rowEnd; index += 1) {
                points[index].x_mm = Number(
                    (Number(points[index].x_mm) + shiftX).toFixed(2),
                );
            }
        } else {
            currentPoint[coordinate] = Number(nextValue.toFixed(2));
        }

        const diameter = Number(activeSection.bubbleDiameterMm) || defaultBubbleDiameterMm;
        const xValues = points.map((point) => Number(point.x_mm));
        const yValues = points.map((point) => Number(point.y_mm));
        const rowSize = optionTokensForSection(activeSection).length;
        let pitchTotal = 0;
        let pitchCount = 0;
        for (let rowStart = 0; rowStart < points.length; rowStart += rowSize) {
            const rowStop = Math.min(rowStart + rowSize, points.length);
            for (let index = rowStart; index < rowStop - 1; index += 1) {
                pitchTotal += Math.hypot(
                    Number(points[index + 1].x_mm) - Number(points[index].x_mm),
                    Number(points[index + 1].y_mm) - Number(points[index].y_mm),
                );
                pitchCount += 1;
            }
        }

        const minX = Math.min(...xValues);
        const maxX = Math.max(...xValues);
        const minY = Math.min(...yValues);
        const maxY = Math.max(...yValues);
        const updatedSection = {
            ...activeSection,
            optionPointsMm: points,
            bubblePitchMm:
                pitchCount > 0
                    ? Number((pitchTotal / pitchCount).toFixed(2))
                    : activeSection.bubblePitchMm,
            posX: Number((minX - diameter / 2).toFixed(2)),
            posY: Number((minY - diameter / 2).toFixed(2)),
            w_mm: Number((maxX - minX + diameter).toFixed(2)),
            h_mm: Number((maxY - minY + diameter).toFixed(2)),
        };
        sections = sections.map((section, index) =>
            index === activeSectionIndex ? updatedSection : section,
        );
        onChanged?.();
    }

    function handleBubblePitchInput(event) {
        if (!activeSection) return;

        const newPitch = Number(event.currentTarget.value);
        if (!Number.isFinite(newPitch)) return;

        const updatedSection = {
            ...activeSection,
            bubblePitchMm: newPitch,
        };
        sections = sections.map((section, index) =>
            index === activeSectionIndex
                ? Array.isArray(activeSection.optionPointsMm) &&
                    activeSection.optionPointsMm.length >= 2
                    ? rescaleSectionOptionPointsPitch(updatedSection, newPitch)
                    : updatedSection
                : section,
        );
        handleInput();
    }

    function resetActiveSectionToUniformGrid() {
        if (!activeSection || readOnly || activeSection.isLocked || activeSection.locked) return;

        sections = sections.map((section, index) =>
            index === activeSectionIndex
                ? resetSectionOptionPointsToUniformGrid(activeSection)
                : section,
        );
        onChanged?.();
    }

    function startRespotSingleOption(sectionIndex, optionIndex) {
        if (sectionIndex !== activeSectionIndex || !activeSection) return;
        startRespotOptionOnActiveItem(optionIndex);
    }


    function handleApplicantGridRadiusInput(val) {
        handleInput();
        if (plottingConfig.sizingPreviewDiameterMm != null) {
            plottingConfig = {
                ...plottingConfig,
                sizingPreviewDiameterMm: Number(val),
            };
        }
    }

    function updateIdentityDeclaration(nextIdentity) {
        identity = nextIdentity;
        if (onIdentityChanged) onIdentityChanged(nextIdentity);
    }
    function addDeclaredInventory(pageNo) {
        const id = `inventory-${pageNo}-${++uploadedEntrySequence}`;
        declaredInventory = [
            ...declaredInventory,
            {
                id,
                page_no: Number(pageNo),
                area_code: '',
                question_count: null,
                option_labels: [],
                geometry_id: null,
            },
        ];
        handleInput();
    }

    function setInventoryValue(id, field, value) {
        const entry = declaredInventory.find((item) => item.id === id);
        if (!entry) return;
        const nextEntry = { ...entry, [field]: value };
        declaredInventory = declaredInventory.map((item) =>
            item.id === id ? nextEntry : item,
        );
        const geometry = sections.find(
            (section) => section.id === nextEntry.geometry_id,
        );
        if (geometry) {
            geometry.aptitudeAreaCode = nextEntry.area_code;
            geometry.questionsCount = nextEntry.question_count ?? '';
            geometry.customValuesStr = nextEntry.option_labels.join(', ');
            geometry.optionsCount = nextEntry.option_labels.length || null;
        }
        handleInput();
    }

    function removeDeclaredInventory(id) {
        const entry = declaredInventory.find((item) => item.id === id);
        const geometry = sections.find(
            (section) => section.id === entry?.geometry_id,
        );
        if (geometry) geometry.inventory_id = null;
        declaredInventory = declaredInventory.filter((item) => item.id !== id);
        handleInput();
    }

    function linkInventoryGeometry(entry, geometryId) {
        for (const section of sections) {
            if (section.inventory_id === entry.id) section.inventory_id = null;
        }
        const geometry = sections.find((section) => section.id === geometryId);
        const nextGeometryId = geometry ? geometry.id : null;
        if (geometry && geometry.type !== 'roll') {
            geometry.inventory_id = entry.id;
            geometry.type = 'custom';
            geometry.aptitudeAreaCode = entry.area_code;
            geometry.questionsCount = entry.question_count ?? '';
            geometry.customValuesStr = entry.option_labels.join(', ');
            geometry.optionsCount = entry.option_labels.length || null;
        }
        declaredInventory = declaredInventory.map((item) =>
            item.id === entry.id
                ? { ...item, geometry_id: nextGeometryId }
                : item,
        );
        handleInput();
    }

    function addUploadedGeometry(entry) {
        const id = `Uploaded_Area_P${entry.page_no}_${++uploadedEntrySequence}`;
        const geometry = {
            id,
            name: `${entry.area_code || 'Question area'} · Page ${entry.page_no}`,
            type: 'custom',
            page_no: Number(entry.page_no),
            inventory_id: entry.id,
            aptitudeAreaCode: entry.area_code,
            questionsCount: entry.question_count ?? '',
            optionsCount: entry.option_labels.length || 4,
            startQ: 1,
            customValuesStr: entry.option_labels.join(', ') || 'A, B, C, D',
            posX: 25,
            posY: 80,
            w_mm: 75,
            h_mm: 140,
            bubbleDiameterMm: defaultBubbleDiameterMm,
            bubblePitchMm: 6.0,
            rowPitchMm: 6.8,
            cols: 1,
            isLocked: false,
        };
        sections = [...sections, geometry];
        activeSectionIndex = sections.length - 1;
        declaredInventory = declaredInventory.map((item) =>
            item.id === entry.id
                ? { ...item, geometry_id: id }
                : item,
        );
        handleInput();
    }

    const hasBubbles = $derived(
        Boolean(
            identity?.has_bubbles ||
            identity?.strategy === 'bubbles_and_write_in' ||
            identity?.strategy === 'bubbles_only' ||
            identity?.method === 'shaded_grid' ||
            sections.some((s) => s.identity_grid) ||
            (!identity?.has_write_in && identity?.method !== 'write_in_manual'),
        ),
    );

    const hasWriteIn = $derived(
        Boolean(
            identity?.has_write_in ||
            identity?.strategy === 'bubbles_and_write_in' ||
            identity?.strategy === 'write_in_only' ||
            identity?.method === 'write_in_manual' ||
            (Array.isArray(identity?.write_in_boxes) && identity.write_in_boxes.length > 0) ||
            (Array.isArray(identity?.regions) && identity.regions.length > 0),
        ),
    );
    const identityPages = $derived.by(() => {
        const rawIdentityPages =
            Array.isArray(identity?.identity_pages) &&
            identity.identity_pages.length > 0
                ? identity.identity_pages
                      .map((page) => Number(page))
                      .filter(
                          (page) =>
                              Number.isInteger(page) &&
                              page >= 1 &&
                              page <= (Number(pageCount) || 1),
                      )
                : [];
        return rawIdentityPages.length > 0
            ? Array.from(new Set(rawIdentityPages))
            : Array.from(
                  { length: Number(pageCount) || 1 },
                  (_, index) => index + 1,
              );
    });

    function setIdentityPages(pageNumbers) {
        const nextPages = [...pageNumbers];
        const nextBoxes = (identity?.write_in_boxes ?? []).filter((b) =>
            nextPages.includes(Number(b.page_no)),
        );
        updateIdentityDeclaration({
            ...identity,
            identity_pages: nextPages,
            write_in_boxes: nextBoxes,
        });
        handleInput();
    }

    function toggleBubbles(enabled) {
        const nextHasBubbles = Boolean(enabled);
        const nextHasWriteIn = hasWriteIn;
        if (!nextHasBubbles && !nextHasWriteIn) {
            return;
        }
        if (!nextHasBubbles) {
            sections = sections.filter((s) => !s.identity_grid && s.type !== 'roll');
        } else {
            for (let p = 1; p <= (Number(pageCount) || 1); p++) {
                const hasExisting = sections.some((s) => s.identity_grid && Number(s.page_no) === p);
                if (!hasExisting) {
                    addApplicantGrid(p);
                }
            }
            const activeGridIndex = sections.findIndex(
                (s) => s.identity_grid && Number(s.page_no) === Number(activePageNo || 1),
            );
            if (activeGridIndex >= 0) {
                activeSectionIndex = activeGridIndex;
            }
        }
        syncIdentityStrategy(nextHasBubbles, nextHasWriteIn);
    }

    function toggleWriteIn(enabled) {
        const nextHasWriteIn = Boolean(enabled);
        const nextHasBubbles = hasBubbles;
        if (!nextHasBubbles && !nextHasWriteIn) {
            return;
        }
        if (nextHasWriteIn) {
            const currentBoxes = [...(identity?.write_in_boxes ?? [])];
            for (let p = 1; p <= (Number(pageCount) || 1); p++) {
                if (!currentBoxes.some((b) => Number(b.page_no) === p)) {
                    currentBoxes.push({
                        page_no: p,
                        x_mm: 110.0,
                        y_mm: 18.0,
                        w_mm: 42.0,
                        h_mm: 8.5,
                    });
                }
            }
            identity.write_in_boxes = currentBoxes;
        }
        syncIdentityStrategy(nextHasBubbles, nextHasWriteIn);
    }

    function syncIdentityStrategy(nextBubbles, nextWriteIn) {
        let strategy = 'bubbles_only';
        if (nextBubbles && nextWriteIn) {
            strategy = 'bubbles_and_write_in';
        } else if (nextWriteIn) {
            strategy = 'write_in_only';
        } else if (nextBubbles) {
            strategy = 'bubbles_only';
        }
        const existingGrid = sections.find((section) => section.identity_grid);
        const inferredDigits = Number(existingGrid?.questionsCount);
        const digitCount = Number.isInteger(inferredDigits) && inferredDigits >= 1
            ? inferredDigits
            : (identity?.digit_count ?? 6);

        const nextIdentity = {
            ...identity,
            method: strategy === 'write_in_only' ? 'write_in_manual' : 'shaded_grid',
            strategy,
            has_bubbles: nextBubbles,
            has_write_in: nextWriteIn,
            digit_count: digitCount,
            write_in_boxes: identity?.write_in_boxes ?? [],
        };
        updateIdentityDeclaration(nextIdentity);
        handleInput();
    }

    function updateWriteInBoxMm(pageNo, field, val) {
        const numVal = Number(val);
        const boxes = [...(identity?.write_in_boxes ?? [])];
        const idx = boxes.findIndex((b) => Number(b.page_no) === Number(pageNo));
        const current = idx >= 0 ? boxes[idx] : { page_no: Number(pageNo), x_mm: 110, y_mm: 18, w_mm: 42, h_mm: 8.5 };
        const updated = { ...current, [field]: isNaN(numVal) ? 0 : numVal, page_no: Number(pageNo) };
        if (idx >= 0) {
            boxes[idx] = updated;
        } else {
            boxes.push(updated);
        }
        updateIdentityDeclaration({
            ...identity,
            write_in_boxes: boxes,
        });
        handleInput();
    }
    function removeWriteInBox(pageNo) {
        const nextBoxes = (identity?.write_in_boxes ?? []).filter(
            (box) => Number(box.page_no) !== Number(pageNo),
        );
        updateIdentityDeclaration({
            ...identity,
            write_in_boxes: nextBoxes,
        });
        handleInput();
    }

    function addWriteInBox(pageNo) {
        const nextBoxes = [
            ...(identity?.write_in_boxes ?? []),
            { page_no: Number(pageNo), x_mm: 110, y_mm: 18, w_mm: 42, h_mm: 8.5 },
        ];
        updateIdentityDeclaration({
            ...identity,
            write_in_boxes: nextBoxes,
        });
        handleInput();
    }


    function selectIdentityMethod(method) {
        if (method === 'shaded_grid') {
            const existingGrid = sections.find((section) => section.identity_grid);
            const inferredDigits = Number(existingGrid?.questionsCount);
            const digit_count = Number.isInteger(inferredDigits) && inferredDigits >= 1
                ? inferredDigits
                : (identity?.digit_count ?? null);
            if (identity?.method === null || identity?.method === undefined) {
                updateIdentityDeclaration({
                    method: 'shaded_grid',
                    digit_count,
                });
            } else {
                syncIdentityStrategy(true, hasWriteIn);
            }
        } else {
            if (identity?.method === null || identity?.method === undefined) {
                sections = sections.filter((section) => !section.identity_grid);
                updateIdentityDeclaration({ method, regions: [] });
            } else {
                syncIdentityStrategy(hasBubbles, true);
            }
        }
        handleInput();
    }

    function applyApplicantDigitCount(rawCount) {
        const val = rawCount?.target?.value ?? rawCount?.currentTarget?.value ?? rawCount;
        if (val === '' || val == null) return;
        const digitCount = Math.max(2, Math.min(10, Number(val) || 6));
        identity.digit_count = digitCount;
        updateIdentityDeclaration({ ...identity, digit_count: digitCount });
        let modified = false;
        sections = sections.map((sec) => {
            if (!sec.identity_grid && sec.type !== 'roll') return sec;
            modified = true;
            const rollDirection = sec.rollDirection ?? 'vertical';
            const isHorizontal = rollDirection === 'horizontal';
            const cols = isHorizontal ? 10 : digitCount;
            const rows = isHorizontal ? digitCount : 10;
            const bubblePitch = Number(sec.bubblePitchMm) || 5.5;
            const rowPitch = Number(sec.rowPitchMm) || 4.8;
            const bubbleDiameter = Number(sec.bubbleDiameterMm) || 4.5;
            const w_mm = Number((bubbleDiameter + (cols - 1) * bubblePitch).toFixed(2));
            const h_mm = Number((bubbleDiameter + (rows - 1) * rowPitch).toFixed(2));

            sec.questionsCount = digitCount;
            sec.w_mm = w_mm;
            sec.h_mm = h_mm;

            return {
                ...sec,
                questionsCount: digitCount,
                w_mm,
                h_mm,
            };
        });

        if (modified) {
            handleInput();
            if (onChanged) onChanged();
        }

        if (digitPreviewEnabled) {
            updateRollPreview();
        }
    }

    function applyApplicantDigitPitch(rawPitch) {
        const pitch = Number(rawPitch);
        if (!pitch || pitch <= 0) return;
        digitMultiplyPitchMm = pitch;

        let modified = false;
        sections = sections.map((sec) => {
            if (!sec.identity_grid && sec.type !== 'roll') return sec;
            modified = true;
            const rollDirection = sec.rollDirection ?? 'vertical';
            const isHorizontal = rollDirection === 'horizontal';
            const cols = isHorizontal ? 10 : (Number(sec.questionsCount) || 6);
            const rows = isHorizontal ? (Number(sec.questionsCount) || 6) : 10;
            if (isHorizontal) {
                sec.rowPitchMm = pitch;
            } else {
                sec.bubblePitchMm = pitch;
            }
            const bubblePitch = Number(sec.bubblePitchMm) || 5.5;
            const rowPitch = Number(sec.rowPitchMm) || 4.8;
            const bubbleDiameter = Number(sec.bubbleDiameterMm) || 4.5;
            const w_mm = Number((bubbleDiameter + (cols - 1) * bubblePitch).toFixed(2));
            const h_mm = Number((bubbleDiameter + (rows - 1) * rowPitch).toFixed(2));

            sec.w_mm = w_mm;
            sec.h_mm = h_mm;

            return {
                ...sec,
                w_mm,
                h_mm,
                bubblePitchMm: sec.bubblePitchMm,
                rowPitchMm: sec.rowPitchMm,
            };
        });

        if (modified) {
            handleInput();
            if (onChanged) onChanged();
        }

        if (digitPreviewEnabled) {
            updateRollPreview();
        }
    }

    function setIdentityDigitCount(value) {
        applyApplicantDigitCount(value);
    }
    function adjustApplicantGridTilt(pageNo, deltaDeg) {
        const gridIndex = sections.findIndex(
            (section) =>
                section.identity_grid === true &&
                Number(section.page_no) === Number(pageNo) &&
                section.rollDirection !== 'horizontal',
        );
        if (gridIndex < 0) return;

        const currentAngle = Number(sections[gridIndex].rotationDeg) || 0;
        const nextAngle = Number((currentAngle + deltaDeg).toFixed(1));
        if (Math.abs(nextAngle) > 5) return;

        sections = sections.map((section, index) =>
            index === gridIndex
                ? { ...section, rotationDeg: nextAngle }
                : section,
        );
        handleInput();
    }

    function addApplicantGrid(pageNo) {
        const id = `Applicant_Number_Page_${pageNo}`;
        if (sections.some((section) => section.id === id)) return;
        sections = [
            ...sections,
            {
                id,
                name: `Applicant Number · Page ${pageNo}`,
                type: 'roll',
                page_no: Number(pageNo),
                identity_grid: true,
                questionsCount: identity.digit_count ?? 6,
                startQ: 1,
                posX: 110,
                posY: 28,
                w_mm: 45,
                h_mm: 60,
                bubbleDiameterMm: 4.5,
                bubblePitchMm: 5.5,
                rowPitchMm: 4.8,
                isLocked: false,
            },
        ];
        activeSectionIndex = sections.length - 1;
        handleInput();
    }

    function addWriteInRegion(pageNo) {
        updateIdentityDeclaration({
            ...identity,
            regions: [
                ...(identity.regions ?? []),
                { page_no: Number(pageNo), x: null, y: null, w: null, h: null },
            ],
        });
        handleInput();
    }

    function updateWriteInRegion(index, field, value) {
        updateIdentityDeclaration({
            ...identity,
            regions: (identity.regions ?? []).map((region, regionIndex) =>
                regionIndex === index
                    ? { ...region, [field]: value }
                    : region,
            ),
        });
        handleInput();
    }

    function removeWriteInRegion(index) {
        updateIdentityDeclaration({
            ...identity,
            regions: (identity.regions ?? []).filter(
                (_, regionIndex) => regionIndex !== index,
            ),
        });
        handleInput();
    }

    function sectionName(section, index) {
        if (section.identity_grid) {
            return `Applicant Number · Page ${section.page_no}`;
        }
        if (section.inventory_id) {
            return `${section.aptitudeAreaCode || 'Question area'} · Page ${section.page_no}`;
        }
        return section.name || section.id || `Recognition guide ${index + 1}`;
    }

    function optionLabelsValue(entry) {
        return Array.isArray(entry.option_labels)
            ? entry.option_labels.join(', ')
            : '';
    }

    function parseOptionLabels(value) {
        const text = String(value ?? '');
        return text.trim() === ''
            ? []
            : text.split(',').map((label) => label.trim());
    }

    function regionIndex(region) {
        return identity.regions?.indexOf(region) ?? -1;
    }

    function handleLockToggle() {
        activeSection.isLocked = !activeSection.isLocked;
        if (onChanged) onChanged('lock');
    }

    function errorText(value) {
        if (typeof value === 'string' && value.trim()) return value.trim();
        if (Array.isArray(value))
            return value.map(errorText).find(Boolean) ?? '';
        if (value && typeof value === 'object') {
            for (const key of ['detail', 'message', 'error', 'reason']) {
                const text = errorText(value[key]);
                if (text) return text;
            }
            return typeof value.code === 'string' && value.code.trim()
                ? value.code.trim()
                : '';
        }
        return '';
    }
    function beginPrintedSectionsManager() {
        managerInventory = JSON.parse(JSON.stringify(physicalInventory ?? { revision: 0, sections: [] }));
        pendingPhysicalChange = null;
        showPrintedSectionsManager = true;
    }

    function addPrintedSection() {
        const pageNo = Number(activePageNo) || 1;
        managerInventory.sections.push({
            printed_section_uid: crypto.randomUUID(),
            name: 'New printed section',
            page_no: pageNo,
            numbering: { start: 1, step: 1, prefix: '', suffix: '' },
            expected_item_count: 1,
            display_order: managerInventory.sections.length + 1,
            items: [{
                physical_item_uid: crypto.randomUUID(),
                native_label: null,
                page_no: pageNo,
                printed_number: 1,
                option_tokens: [],
                guide_id: null,
            }],
        });
        managerInventory = { ...managerInventory, sections: [...managerInventory.sections] };
    }

    function resizePrintedSection(section, count) {
        const nextCount = Math.max(1, Math.min(1000, Number(count) || 1));
        const items = [...(section.items ?? [])];
        if (nextCount < items.length) {
            items.splice(nextCount);
        } else {
            while (items.length < nextCount) {
                const index = items.length;
                const numbering = section.numbering ?? { start: 1, step: 1, prefix: '', suffix: '' };
                items.push({
                    physical_item_uid: crypto.randomUUID(),
                    native_label: null,
                    page_no: Number(section.page_no),
                    printed_number: Number(numbering.start) + index * Number(numbering.step || 1),
                    option_tokens: [],
                    guide_id: null,
                });
            }
        }
        return { ...section, expected_item_count: nextCount, items };
    }

    async function commitPhysicalInventory(nextInventory) {
        const previous = physicalInventory ?? { revision: 0, sections: [] };
        const impact = physicalInventoryImpact(previous, nextInventory);
        if (impact.length > 0) {
            pendingPhysicalChange = {
                nextInventory,
                impact,
                digest: await physicalInventoryImpactDigest(Number(previous.revision) || 0, impact),
                revision: Number(previous.revision) || 0,
            };
            return;
        }
        nextInventory.revision = (Number(previous.revision) || 0) + 1;
        physicalInventory = nextInventory;
        onPhysicalInventoryChanged?.(nextInventory, null);
        pendingPhysicalChange = null;
        showPrintedSectionsManager = false;
    }

    function applyPrintedSectionsManager() {
        const next = JSON.parse(JSON.stringify(managerInventory));
        next.sections = next.sections.map((section, index) => {
            const numbering = section.numbering ?? { start: 1, step: 1, prefix: '', suffix: '' };
            const pageNo = Number(section.page_no);
            const resized = resizePrintedSection(section, section.expected_item_count);
            return {
                ...resized,
                page_no: pageNo,
                display_order: index + 1,
                numbering: {
                    start: Number(numbering.start),
                    step: Number(numbering.step),
                    prefix: String(numbering.prefix ?? ''),
                    suffix: String(numbering.suffix ?? ''),
                },
                items: resized.items.map((item, itemIndex) => ({
                    ...item,
                    page_no: pageNo,
                    printed_number: Number(numbering.start) + itemIndex * Number(numbering.step),
                })),
            };
        });
        void commitPhysicalInventory(next);
    }

    function confirmPhysicalInventoryChange() {
        if (!pendingPhysicalChange) return;
        const { nextInventory, revision, digest } = pendingPhysicalChange;
        nextInventory.revision = revision + 1;
        physicalInventory = nextInventory;
        onPhysicalInventoryChanged?.(nextInventory, { revision, digest });
        pendingPhysicalChange = null;
        showPrintedSectionsManager = false;
    }

    function focusPrintedSection(sectionUid) {
        selectedPrintedSectionUid = sectionUid;
        const selected = printedSections.find((section) => section.printed_section_uid === sectionUid);
        if (!selected) return;
        const guideIds = new Set((selected.items ?? []).map((item) => String(item.guide_id ?? '')).filter(Boolean));
        const indices = sections.reduce((matches, guide, index) => {
            if (guideIds.has(String(guide.id))) matches.push(index);
            return matches;
        }, []);
        if (indices.length > 0) {
            activeSectionIndex = indices[0];
        }
    }
</script>

<div
    class="text-foreground [&_input]:border-input [&_input]:bg-background [&_input]:text-foreground [&_select]:border-input [&_select]:bg-background [&_select]:text-foreground space-y-3"
>
    <div class="space-y-1">
        <div class="flex items-center justify-between">
            <h2 class="text-foreground text-sm font-bold tracking-tight">
                {isUploadedSource
                    ? 'Uploaded-source Recognition Mapping'
                    : 'Recognition Mapping'}
            </h2>
            <span
                class="bg-muted/80 text-muted-foreground rounded-full px-2 py-0.5 text-[10px] font-medium"
            >
                {sections.length} block{sections.length === 1 ? '' : 's'}
            </span>
        </div>
        <p class="text-muted-foreground text-[11px] leading-snug">
            {isUploadedSource
                ? 'Declare question areas and position bubbles over your sheet.'
                : 'Position recognition guides over the fixed printable sheet.'}
        </p>
    </div>
    <section class="border-border bg-card space-y-2 rounded-xl border p-3" data-testid="printed-section-controls">
        <div class="flex flex-wrap items-end gap-2">
            <label class="min-w-0 flex-1 space-y-1 text-[10px] font-semibold">
                <span>Printed section</span>
                <select
                    class="w-full rounded-lg border bg-background px-2 py-1.5 text-xs"
                    value={activePrintedSection?.printed_section_uid ?? ''}
                    onchange={(event) => focusPrintedSection(event.currentTarget.value)}
                >
                    {#if printedSections.length === 0}<option value="">No sections yet</option>{/if}
                    {#each printedSections as section (section.printed_section_uid)}
                        <option value={section.printed_section_uid}>{section.name} · Page {section.page_no}</option>
                    {/each}
                </select>
            </label>
            {#if !readOnly}
                <button type="button" class="rounded-lg border px-3 py-1.5 text-xs font-semibold hover:bg-muted" onclick={beginPrintedSectionsManager}>
                    Manage sections
                </button>
            {/if}
        </div>
        {#if activePrintedSection}
            <p class="text-muted-foreground text-[10px]">
                {activePrintedGuideCount}/{activePrintedSection.expected_item_count} recognition guides placed
                <span aria-label="expected physical item count">· {activePrintedSection.expected_item_count} expected items</span>
            </p>
        {/if}
    </section>
    {#if showPrintedSectionsManager && managerInventory}
        <div class="fixed inset-0 z-[90] flex items-end justify-center bg-black/40 p-2 sm:items-center sm:p-4" role="presentation">
            <section
                role="dialog"
                aria-modal="true"
                aria-labelledby="printed-sections-title"
                class="border-border bg-background max-h-[92dvh] w-full max-w-3xl overflow-y-auto rounded-2xl border p-4 shadow-xl"
            >
                <header class="mb-3 flex items-start justify-between gap-3">
                    <div>
                        <h3 id="printed-sections-title" class="text-sm font-bold">Manage Printed sections</h3>
                        <p class="text-muted-foreground mt-1 text-[11px]">Expected items are separate from guides already placed. Added locations stay unplaced.</p>
                    </div>
                    <button type="button" aria-label="Close Printed sections" class="rounded-lg border px-2 py-1 text-xs" onclick={() => { showPrintedSectionsManager = false; pendingPhysicalChange = null; }}>Close</button>
                </header>
                {#if pendingPhysicalChange}
                    <div role="alert" class="mb-3 rounded-xl border border-amber-400 bg-amber-50 p-3 text-xs text-amber-950 dark:bg-amber-950/30 dark:text-amber-100">
                        <p class="font-semibold">Confirm the exact affected physical locations</p>
                        <ul class="mt-2 max-h-40 space-y-1 overflow-y-auto">
                            {#each pendingPhysicalChange.impact as item (item.physical_item_uid)}
                                <li>{item.native_label ?? `Printed item ${item.physical_item_uid}`} · Page {item.page_no} · {item.guide_id ? 'recognition guide linked' : 'unplaced location'}</li>
                            {/each}
                        </ul>
                        <div class="mt-3 flex justify-end gap-2">
                            <button type="button" class="rounded-lg border px-3 py-1.5" onclick={() => { pendingPhysicalChange = null; }}>Review changes</button>
                            <button type="button" class="rounded-lg bg-amber-700 px-3 py-1.5 font-semibold text-white" onclick={confirmPhysicalInventoryChange}>Confirm changes</button>
                        </div>
                    </div>
                {:else}
                    <div class="space-y-3">
                        {#each managerInventory.sections as section, index (section.printed_section_uid)}
                            <fieldset class="border-border space-y-3 rounded-xl border p-3">
                                <legend class="px-1 text-xs font-semibold">{section.name || `Section ${index + 1}`}</legend>
                                <div class="grid grid-cols-1 gap-2 sm:grid-cols-2 lg:grid-cols-4">
                                    <label class="space-y-1 text-[10px] font-medium">Name
                                        <input class="w-full rounded-lg border px-2 py-1.5 text-xs" maxlength="100" bind:value={section.name} />
                                    </label>
                                    <label class="space-y-1 text-[10px] font-medium">Page
                                        <select class="w-full rounded-lg border px-2 py-1.5 text-xs" bind:value={section.page_no}>
                                            {#each Array.from({ length: pageCount }, (_, pageIndex) => pageIndex + 1) as page}
                                                <option value={page}>Page {page}</option>
                                            {/each}
                                        </select>
                                    </label>
                                    <label class="space-y-1 text-[10px] font-medium">Expected items
                                        <input class="w-full rounded-lg border px-2 py-1.5 text-xs" type="number" min="1" max="1000" bind:value={section.expected_item_count} />
                                    </label>
                                    <label class="space-y-1 text-[10px] font-medium">Printed numbering · start / step
                                        <div class="flex gap-1">
                                            <input aria-label="Numbering start" class="min-w-0 w-1/2 rounded-lg border px-2 py-1.5 text-xs" type="number" min="1" bind:value={section.numbering.start} />
                                            <input aria-label="Numbering step" class="min-w-0 w-1/2 rounded-lg border px-2 py-1.5 text-xs" type="number" min="1" bind:value={section.numbering.step} />
                                        </div>
                                    </label>
                                </div>
                                <div class="space-y-2">
                                    {#each section.items ?? [] as item, itemIndex (item.physical_item_uid)}
                                        <div class="grid grid-cols-1 gap-2 rounded-lg bg-muted/40 p-2 sm:grid-cols-2 lg:grid-cols-4">
                                            <label class="space-y-1 text-[10px]">Printed number
                                                <input class="w-full rounded-lg border px-2 py-1.5 text-xs" type="number" min="1" bind:value={item.printed_number} />
                                            </label>
                                            <label class="space-y-1 text-[10px]">Native label
                                                <input class="w-full rounded-lg border px-2 py-1.5 text-xs" maxlength="100" bind:value={item.native_label} />
                                            </label>
                                            <label class="space-y-1 text-[10px] sm:col-span-2">Literal response tokens
                                                <input
                                                    class="w-full rounded-lg border px-2 py-1.5 text-xs"
                                                    value={(item.option_tokens ?? []).join(', ')}
                                                    oninput={(event) => { item.option_tokens = event.currentTarget.value.split(',').map((token) => token.trim()).filter(Boolean); }}
                                                />
                                            </label>
                                            <p class="text-muted-foreground text-[10px] sm:col-span-2 lg:col-span-4">
                                                {item.guide_id ? 'Recognition guide already linked' : 'No recognition guide placed'}
                                            </p>
                                        </div>
                                    {/each}
                                </div>
                                <div class="flex justify-end">
                                    <button type="button" class="rounded-lg border border-rose-300 px-3 py-1.5 text-xs text-rose-700" onclick={() => { managerInventory.sections.splice(index, 1); managerInventory = { ...managerInventory, sections: [...managerInventory.sections] }; }}>
                                        Delete section
                                    </button>
                                </div>
                            </fieldset>
                        {/each}
                    </div>
                    <div class="mt-3 flex flex-wrap items-center justify-between gap-2">
                        <button type="button" class="rounded-lg border px-3 py-1.5 text-xs font-semibold" onclick={addPrintedSection}>Add section</button>
                        <div class="flex gap-2">
                            <button type="button" class="rounded-lg border px-3 py-1.5 text-xs" onclick={() => { showPrintedSectionsManager = false; }}>Cancel</button>
                            <button type="button" class="rounded-lg bg-primary px-3 py-1.5 text-xs font-semibold text-primary-foreground" onclick={applyPrintedSectionsManager}>Save sections</button>
                        </div>
                    </div>
                {/if}
            </section>
        </div>
    {/if}
    <section class="border-border bg-card space-y-2 rounded-xl border border-indigo-500/30 p-3">
        <div class="flex flex-wrap items-center justify-between gap-1.5">
            <h3 class="text-foreground text-xs font-bold">Smart Snap Bubbles</h3>
            <span class="text-muted-foreground text-[10px]">
                {activePageUnlockedSections.length} unlocked row{activePageUnlockedSections.length === 1 ? '' : 's'} on Page {activePageNo}
            </span>
        </div>
        {#if !readOnly}
            <button
                type="button"
                data-testid="smart-snap-bubbles-btn"
                title="Snap bubble guides on this page to the closest printed circle outlines (ignores option text above bubbles)"
                disabled={smartSnapRunning || activePageUnlockedSections.length === 0}
                onclick={() => onSmartSnapBubbles?.()}
                class="flex w-full items-center justify-center gap-2 rounded-lg bg-indigo-700 px-3 py-2 text-xs font-bold text-white shadow-sm transition hover:bg-indigo-600 disabled:cursor-not-allowed disabled:opacity-50"
            >
                <Scan class="size-3.5 shrink-0" />
                <span>{smartSnapRunning ? 'Snapping Bubbles to Printed Circles…' : `Smart Snap Bubbles (Page ${activePageNo})`}</span>
            </button>
        {:else}
            <button
                type="button"
                data-testid="smart-snap-bubbles-btn"
                disabled
                class="flex w-full items-center justify-center gap-2 rounded-lg bg-indigo-700 px-3 py-2 text-xs font-bold text-white opacity-50"
            >
                <Scan class="size-3.5 shrink-0" />
                <span>Smart Snap Bubbles (Locked — Compiled Version)</span>
            </button>
        {/if}
        <div class="flex flex-wrap items-center justify-between gap-1.5">
            <span class="rounded-full bg-indigo-500/10 px-2 py-0.5 text-[10px] font-semibold text-indigo-700 dark:text-indigo-300">
                {Number(smartSnapSearchMm ?? 5)} mm • Halo {showSmartSnapSearchZone ? 'On' : 'Off'}
            </span>
            {#if !readOnly && activePageUnlockedSections.length === 0}
                <p class="text-[10px] text-muted-foreground">Plot at least one unlocked question row on this page to use Smart Snap.</p>
            {/if}
        </div>
        <details
            bind:open={snapOptionsOpen}
            class="group rounded-lg border border-border/70"
            onmouseenter={() => {
                allowanceHovered = true;
                syncSearchZonePreview();
            }}
            onmouseleave={() => {
                allowanceHovered = false;
                syncSearchZonePreview();
            }}
            onfocusin={() => {
                allowanceFocused = true;
                syncSearchZonePreview();
            }}
            onfocusout={(event) => {
                allowanceFocused = event.currentTarget.contains(event.relatedTarget);
                syncSearchZonePreview();
            }}
        >
            <summary class="flex cursor-pointer list-none flex-wrap items-center justify-between gap-2 rounded-lg px-2.5 py-2 text-[11px] font-semibold focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-cyan-500">
                <span class="flex items-center gap-1.5">
                    <span class="text-muted-foreground transition-transform group-open:rotate-90" aria-hidden="true">›</span>
                    Snap &amp; Halo Options ({Number(smartSnapSearchMm ?? 5).toFixed(1)} mm)
                </span>
            </summary>
            <div class="space-y-2 border-t border-border/60 px-2.5 py-2.5">
                {#if !readOnly}
                    <div class="flex flex-wrap items-end gap-2">
                        <label class="flex min-w-0 flex-1 items-center gap-1.5 text-[10px] font-semibold">
                            <span>Search allowance (mm)</span>
                            <StudioTip text="Maximum whole-question search distance when finding printed circles" />
                        </label>
                        <div class="flex flex-wrap gap-1">
                            {#each [3, 5, 8] as preset}
                                <button
                                    type="button"
                                    class="rounded border px-2 py-1 text-[10px] font-semibold"
                                    class:bg-indigo-600={Number(smartSnapSearchMm) === preset}
                                    class:text-white={Number(smartSnapSearchMm) === preset}
                                    onclick={() => {
                                        smartSnapSearchMm = preset;
                                        onChanged?.();
                                    }}
                                >{preset} mm</button>
                            {/each}
                        </div>
                        <input
                            aria-label="Search allowance in millimeters"
                            type="number"
                            min="2"
                            max="12"
                            step="0.5"
                            bind:value={smartSnapSearchMm}
                            onchange={() => {
                                smartSnapSearchMm = Math.max(
                                    2,
                                    Math.min(12, Number(smartSnapSearchMm) || 5),
                                );
                                onChanged?.();
                            }}
                            class="w-16 rounded border px-1.5 py-1 text-xs"
                        />
                    </div>
                {/if}
                <div class="flex flex-wrap items-center justify-between gap-1.5">
                    <span class="flex items-center gap-1 text-[10px] font-semibold">
                        Canvas Halo
                        <StudioTip text="Dashed cyan rings show the ± search reach around eligible question bubbles. Halo Off hides the persistent layer; opening or focusing these options previews it temporarily." />
                    </span>
                    <div class="flex flex-wrap items-center gap-1">
                        <button
                            type="button"
                            data-testid="toggle-smart-snap-search-zone"
                            aria-pressed={showSmartSnapSearchZone}
                            onclick={() => showSmartSnapSearchZone = !showSmartSnapSearchZone}
                            class="rounded border px-2 py-1 text-[10px] font-semibold"
                            class:bg-cyan-700={showSmartSnapSearchZone}
                            class:text-white={showSmartSnapSearchZone}
                        >Canvas Halo: {showSmartSnapSearchZone ? 'On' : 'Off'}</button>
                        <button
                            type="button"
                            data-testid="smart-snap-zone-scope-selected"
                            aria-pressed={smartSnapSearchZoneScope === 'selected'}
                            onclick={() => smartSnapSearchZoneScope = 'selected'}
                            class="rounded border px-2 py-1 text-[10px] font-semibold"
                            class:bg-cyan-700={smartSnapSearchZoneScope === 'selected'}
                            class:text-white={smartSnapSearchZoneScope === 'selected'}
                        >Selected Only</button>
                        <button
                            type="button"
                            data-testid="smart-snap-zone-scope-all-unlocked"
                            aria-pressed={smartSnapSearchZoneScope === 'all_unlocked'}
                            onclick={() => smartSnapSearchZoneScope = 'all_unlocked'}
                            class="rounded border px-2 py-1 text-[10px] font-semibold"
                            class:bg-cyan-700={smartSnapSearchZoneScope === 'all_unlocked'}
                            class:text-white={smartSnapSearchZoneScope === 'all_unlocked'}
                        >All Unlocked</button>
                    </div>
                </div>
                <p class="text-muted-foreground text-[10px] leading-snug">
                    {#if Number(activeSection?.page_no || 1) === Number(activePageNo) &&
                        Boolean(activeSection?.locked || activeSection?.isLocked)}
                        Locked rows are not searched by Smart Snap.
                    {:else if smartSnapSearchZoneScope === 'selected' && !hasSelectedPageQuestion}
                        Select a question on the canvas to preview its ±{smartSnapSearchMm} mm search zone.
                    {:else}
                        Preview scope only; snapping processes all unlocked rows on this page.
                    {/if}
                </p>
            </div>
        </details>
        {#if readOnly}
            {#if canUnlockToEdit && onUnlockDraft}
                <div class="space-y-2 rounded-lg border border-amber-500/40 bg-amber-500/10 p-2.5 text-[11px] text-amber-950 dark:text-amber-100">
                    <p class="leading-snug font-medium">
                        This draft was compiled into Version #{compiledVersionNumber || ''} (not yet used by any Test Kit or Exam Session). You can unlock this draft directly to run Smart Snap Bubbles and edit guides, or create a separate copy.
                    </p>
                    <div class="flex flex-wrap gap-2">
                        <button type="button" data-testid="unlock-draft-btn" disabled={unlockingDraft || creatingEditableCopy} onclick={() => onUnlockDraft?.()} class="flex flex-1 items-center justify-center gap-1.5 rounded-lg bg-indigo-700 px-3 py-2 text-xs font-bold text-white shadow-sm transition hover:bg-indigo-600 disabled:cursor-wait disabled:opacity-60">
                            <span>{unlockingDraft ? 'Unlocking Draft…' : '🔓 Unlock Draft & Enable Smart Snap'}</span>
                        </button>
                        <button type="button" data-testid="create-editable-copy-btn" disabled={creatingEditableCopy || unlockingDraft} onclick={() => onCreateEditableCopy?.()} class="flex flex-1 items-center justify-center gap-1.5 rounded-lg border border-indigo-700 px-3 py-2 text-xs font-bold text-indigo-800 shadow-sm transition hover:bg-indigo-50 disabled:cursor-wait disabled:opacity-60 dark:text-indigo-200 dark:hover:bg-indigo-950">
                            <span>{creatingEditableCopy ? 'Creating Copy…' : '📋 Create Separate Copy'}</span>
                        </button>
                    </div>
                </div>
            {:else}
                <div class="space-y-2 rounded-lg border border-amber-500/40 bg-amber-500/10 p-2.5 text-[11px] text-amber-950 dark:text-amber-100">
                    <p class="leading-snug font-medium">
                        {unlockBlockReason || readOnlyReason || 'Compiled template version is in use'}
                    </p>
                    {#if onCreateEditableCopy}
                        <button type="button" data-testid="create-editable-copy-btn" disabled={creatingEditableCopy || unlockingDraft} onclick={() => onCreateEditableCopy?.()} class="flex w-full items-center justify-center gap-1.5 rounded-lg bg-indigo-700 px-3 py-2 text-xs font-bold text-white shadow-sm transition hover:bg-indigo-600 disabled:cursor-wait disabled:opacity-60">
                            <span>{creatingEditableCopy ? 'Creating Editable Copy…' : '📋 Create Editable Copy & Unlock Smart Snap'}</span>
                        </button>
                    {/if}
                </div>
            {/if}
        {/if}
    </section>
    <section
        inert={readOnly}
        class="border-border bg-card space-y-3 rounded-xl border p-3 shadow-xs"
        aria-labelledby="question-bubbles-heading"
        data-omr-item-plotter-card="true"
    >
        <!-- Header Toolbar: Title, Badge, Tools, Collapse Toggle -->
        <div class="border-border/70 flex items-center justify-between gap-1.5 border-b pb-2">
            <div class="flex items-center gap-2 min-w-0">
                <span class="flex size-5 items-center justify-center rounded-md bg-emerald-600/15 font-mono text-[10px] font-black text-emerald-700 dark:text-emerald-300 shrink-0">
                    1
                </span>
                <div class="min-w-0">
                    <span
                        id="question-bubbles-heading"
                        data-omr-question-bubbles-heading="true"
                        class="text-foreground text-xs font-bold truncate block"
                    >
                        Question Answer Bubbles
                    </span>
                    <span class="text-[9.5px] text-muted-foreground block truncate">Scored choices & option guides</span>
                </div>
                <StudioTip text="Plot question recognition bubbles (e.g. A, B, C, D) over your answer sheet." />
                <span data-omr-plotter-info-icon="true" class="sr-only">info</span>
                {#if activeSectionIndex === -1}
                    <span class="shrink-0 rounded-full bg-emerald-500/15 px-2 py-0.5 font-mono text-[9px] font-bold text-emerald-700 dark:text-emerald-300 ml-1">
                        Ready to Plot
                    </span>
                {:else if activeSectionIndex >= 0 && activeSection && activeSection.type !== 'roll'}
                    <span class="shrink-0 rounded-full bg-amber-500/15 px-2 py-0.5 font-mono text-[9px] font-bold text-amber-700 dark:text-amber-300 ml-1">
                        Editing Q{activeSection.startQ ?? 1}
                    </span>
                {/if}
                {#if plottingConfig.active && !plottingConfig.targetType}
                    <span class="rounded-full bg-emerald-500/15 px-2 py-0.5 font-mono text-[9px] font-bold text-emerald-700 dark:text-emerald-300 animate-pulse shrink-0 ml-1">
                        Plotting
                    </span>
                {/if}
            </div>
            <div class="flex items-center gap-1 shrink-0">
                
                {#if activeSection && activeSection.type !== 'roll'}
                    <button
                        type="button"
                        disabled={readOnly}
                        data-omr-panel-copy-paste="true"
                        onclick={startCopyPasteActiveItem}
                        title="Copy selected item and click on canvas to paste next item (Ctrl+C / Ctrl+V)"
                        class="rounded-md bg-emerald-700 px-2 py-1 text-[10px] font-semibold text-white transition hover:bg-emerald-600"
                    >
                        ⧉ Stamp Q{nextItemNumberForArea(sections, plottingConfig.areaCode || activeSection.area_code)}
                    </button>
                    <button
                        type="button"
                        data-omr-panel-delete="true"
                        disabled={readOnly || activeSection.isLocked}
                        onclick={deleteActiveItemFromPanel}
                        title="Delete selected item (Del)"
                        class="rounded-md bg-rose-600/15 px-2 py-1 text-[10px] font-semibold text-rose-700 transition hover:bg-rose-600/25 disabled:opacity-35 dark:text-rose-300"
                    >
                        ✕ Del
                    </button>
                {/if}
                {#if activeSectionIndex >= 0}
                    <button
                        type="button"
                        data-omr-panel-deselect="true"
                        onclick={() => {
                            activeSectionIndex = -1;
                            selectedSectionIndices = [];
                            plottingConfig.itemNumber = nextPlottingItemNumber(
                                plottingConfig.areaCode || activeSubtest?.code,
                                activeSubtest?.code,
                            );
                            if (onChanged) onChanged();
                        }}
                        title="Deselect the current question and prepare to plot a new one (Esc)"
                        class="rounded-md border border-amber-500/40 bg-amber-500/15 px-2.5 py-1 text-[10px] font-bold text-amber-800 transition hover:bg-amber-500/25 dark:text-amber-200"
                    >
                        ✕ Deselect (Esc)
                    </button>
                {/if}
                <button
                    type="button"
                    data-omr-question-bubbles-collapse="true"
                    aria-expanded={!questionBubblesCollapsed}
                    onclick={() => {
                        questionBubblesCollapsed = !questionBubblesCollapsed;
                        if (questionBubblesCollapsed && plottingConfig.active && !plottingConfig.targetType) {
                            plottingConfig.active = false;
                            plottingConfig.pointsMm = [];
                        }
                    }}
                    class="text-muted-foreground hover:text-foreground rounded p-1 transition"
                    title={questionBubblesCollapsed ? "Expand Question Answer Bubbles" : "Collapse Question Answer Bubbles"}
                >
                    {#if questionBubblesCollapsed}
                        <ChevronDown class="size-4" />
                    {:else}
                        <ChevronUp class="size-4" />
                    {/if}
                </button>
            </div>
        </div>

        {#if !questionBubblesCollapsed}
            {#if subtestCapacity > 0}
                <div class="flex items-center justify-between rounded-lg border border-border/60 bg-muted/30 px-2.5 py-1.5">
                    <span class="text-[11px] font-semibold text-foreground truncate max-w-[150px]">{activeSubtest?.name || activeSubtest?.code}</span>
                    {#if subtestPlottedCount < subtestCapacity}
                        <Badge variant="outline" class="border-emerald-500/40 bg-emerald-500/10 text-emerald-800 dark:text-emerald-300 font-bold text-[10px] py-0 px-1.5">{subtestPlottedCount} / {subtestCapacity} Plotted</Badge>
                    {:else}
                        <Badge variant="secondary" class="bg-blue-500/10 text-blue-800 dark:text-blue-300 font-bold text-[10px] py-0 px-1.5">{subtestCapacity} / {subtestCapacity} Complete ✓</Badge>
                    {/if}
                </div>
            {/if}
            <!-- Test Section / Subtest or legacy Aptitude Area -->
            <div class="grid grid-cols-2 gap-2">
                <label class="space-y-1">
                    <div class="flex items-center gap-1">
                        <span class="text-muted-foreground block text-[10px] font-semibold">
                            {subtests.length > 0
                                ? 'Test Section / Subtest'
                                : 'Aptitude Area'}
                        </span>
                        <StudioTip
                            text={subtests.length > 0
                                ? 'Instrument-defined test section for plotted question items.'
                                : 'Classification area for question items (e.g. LOGIC, VERBAL).'}
                        />
                    </div>
                    {#if subtests.length > 0}
                        <select
                            data-omr-plotter-subtest-select="true"
                            value={subtestCodeFor(activeSection) ||
                                plottingConfig.subtest_code ||
                                subtests[0]?.code ||
                                ''}
                            onchange={handleSubtestChange}
                            class="w-full rounded-md border px-2 py-1.5 text-xs font-semibold"
                        >
                            {#each subtests as subtest}
                                <option value={subtest.code}
                                    >{subtest.name} ({subtest.code})</option
                                >
                            {/each}
                        </select>
                    {:else if aptitudeAreas.length > 0}
                        <select
                            data-omr-plotter-area-select="true"
                            value={activeSection && activeSection.type !== 'roll' ? (activeSection.area_code || activeSection.aptitudeAreaCode || plottingConfig.areaCode) : plottingConfig.areaCode}
                            onchange={(e) => handleAreaChange(e)}
                            class="w-full rounded-md border px-2 py-1.5 text-xs font-semibold"
                        >
                            {#each aptitudeAreas as area}
                                <option value={area.code}>{area.name} ({area.code})</option>
                            {/each}
                        </select>
                    {:else}
                        <input
                            type="text"
                            data-omr-plotter-area-input="true"
                            value={activeSection && activeSection.type !== 'roll' ? (activeSection.area_code || activeSection.aptitudeAreaCode || plottingConfig.areaCode) : plottingConfig.areaCode}
                            placeholder="GENERAL"
                            oninput={(e) => handleAreaChange(e.currentTarget.value)}
                            class="w-full rounded-md border px-2 py-1.5 text-xs font-semibold uppercase"
                        />
                    {/if}
                </label>
                <label class="space-y-1">
                    <div class="flex items-center gap-1">
                        <span class="text-muted-foreground block text-[10px] font-semibold">
                            Item # (Auto +1)
                        </span>
                        <StudioTip text="Question number. Auto-increments as questions are plotted or pasted." />
                    </div>
                    <div class="flex items-center gap-1.5 flex-wrap">
                        <input
                            type="number"
                            min="1"
                            max="500"
                            data-omr-plotter-item-number="true"
                            value={activeSectionIndex === -1
                                ? plottingConfig.itemNumber
                                : plottingConfig.active
                                  ? plottingConfig.itemNumber
                                  : (activeSection && activeSection.type !== 'roll'
                                        ? (activeSection.startQ ?? plottingConfig.itemNumber)
                                        : plottingConfig.itemNumber)}
                            oninput={(e) => handleItemNumberChange(e)}
                            class={`${isDuplicateNumber ? 'w-20' : 'w-full'} rounded-md border px-2 py-1.5 font-mono text-xs font-bold`}
                        />
                        {#if isDuplicateNumber}
                            <span class="text-[10px] text-amber-600 dark:text-amber-400 font-semibold flex items-center gap-1">⚠️ Q{plottingConfig.itemNumber} already plotted</span>
                            <button
                                type="button"
                                onclick={() => {
                                    plottingConfig.itemNumber = nextPlottingItemNumber(
                                        plottingConfig.areaCode,
                                        activeSubtest?.code,
                                    );
                                }}
                                class="text-[10px] text-primary underline"
                            >Use next</button>
                        {/if}
                    </div>
                </label>
            </div>

            <!-- Option Count & 2-Click Span Switcher -->
            <div class="space-y-2 rounded-lg border border-border/70 bg-muted/20 p-2.5">
                <div class="flex items-center justify-between gap-2">
                    <div class="flex items-center gap-1.5">
                        <span class="text-muted-foreground text-[10.5px] font-semibold">Options</span>
                        <StudioTip text="Number of choices per question (2 to 10 choices)." />
                        <div class="flex items-center gap-0.5 ml-1">
                            <button
                                type="button"
                                disabled={(plottingConfig.optionsCount ?? 4) <= 2}
                                onclick={() => setPlottingOptionCount((plottingConfig.optionsCount ?? 4) - 1)}
                                class="flex size-6 items-center justify-center rounded border border-border bg-muted/40 font-mono text-xs font-bold hover:bg-muted disabled:opacity-30"
                            >-</button>
                            <input
                                type="number"
                                min="2"
                                max="10"
                                data-omr-plotter-options-count-input="true"
                                value={plottingConfig.optionsCount ?? 4}
                                oninput={(e) => setPlottingOptionCount(e.currentTarget.value)}
                                class="h-6 w-10 rounded border border-border bg-background text-center font-mono text-xs font-bold"
                            />
                            <button
                                type="button"
                                disabled={(plottingConfig.optionsCount ?? 4) >= 10}
                                onclick={() => setPlottingOptionCount((plottingConfig.optionsCount ?? 4) + 1)}
                                class="flex size-6 items-center justify-center rounded border border-border bg-muted/40 font-mono text-xs font-bold hover:bg-muted disabled:opacity-30"
                            >+</button>
                        </div>
                    </div>
                    <div class="flex items-center gap-1.5 select-none">
                        <span class="text-[9.5px] font-semibold text-muted-foreground">2-Click Span</span>
                        <StudioTip text="Click Option A then the last option to quickly space all intermediate bubbles." />
                        <button
                            type="button"
                            role="switch"
                            aria-checked={questionSpanMode}
                            aria-label="Toggle 2-Click Span shortcut"
                            data-omr-question-span-switch="true"
                            onclick={() => {
                                questionSpanMode = !questionSpanMode;
                                plottingConfig.questionSpanMode = questionSpanMode;
                            }}
                            class={`relative inline-flex h-4 w-7 shrink-0 cursor-pointer rounded-full border-2 border-transparent transition-colors duration-200 ease-in-out focus:outline-none focus-visible:ring-2 focus-visible:ring-emerald-500 ${
                                questionSpanMode ? 'bg-emerald-600' : 'bg-muted-foreground/30'
                            }`}
                        >
                            <span
                                aria-hidden="true"
                                class={`pointer-events-none inline-block size-3 transform rounded-full bg-white shadow-sm ring-0 transition duration-200 ease-in-out ${
                                    questionSpanMode ? 'translate-x-3' : 'translate-x-0'
                                }`}
                            ></span>
                        </button>
                    </div>
                </div>

                <!-- Option Format Preset Dropdown -->
                <div class="space-y-1.5 pt-1.5 border-t border-border/50">
                    <div class="flex items-center justify-between gap-1 text-[10px]">
                        <span class="text-muted-foreground font-semibold">Option Format Preset</span>
                        {#if selectedPresetId === 'custom' || showCustomOptionTokens}
                            <button
                                type="button"
                                onclick={() => (showCustomOptionTokens = !showCustomOptionTokens)}
                                class="text-emerald-700 dark:text-emerald-400 font-semibold underline underline-offset-2 hover:opacity-80 text-[10px]"
                            >
                                {showCustomOptionTokens ? 'Hide Custom Input' : 'Edit Tokens'}
                            </button>
                        {/if}
                    </div>
                    <select
                        data-omr-option-preset-select="true"
                        class="w-full rounded-md border border-border bg-background px-2 py-1.5 text-xs font-semibold"
                        value={selectedPresetId}
                        onchange={(e) => handlePresetSelect(e.currentTarget.value)}
                    >
                        {#each OPTION_PRESETS as preset}
                            <option value={preset.id}>{preset.label} — {preset.tokens.join(', ')}</option>
                        {/each}
                        <option value="custom">Custom tokens (User-defined)...</option>
                    </select>

                    {#if showCustomOptionTokens}
                        <div class="flex gap-1.5 pt-1">
                            <input
                                type="text"
                                aria-label="Custom option tokens"
                                data-omr-custom-option-tokens="true"
                                bind:value={customOptionTokens}
                                oninput={(e) => handleCustomTokensInput(e.currentTarget.value)}
                                placeholder="e.g. A-B, A-C, A-D, B-C, B-D, C-D"
                                class="min-w-0 flex-1 rounded-md border border-border bg-background px-2 py-1 text-xs font-mono"
                            />
                            <button
                                type="button"
                                onclick={applyCustomOptionTokens}
                                class="rounded-md bg-emerald-700 text-white px-2.5 py-1 text-xs font-semibold shadow-xs hover:bg-emerald-600 transition"
                            >Apply</button>
                        </div>
                    {/if}
                </div>
            </div>

                <!-- Option Pills A, B, C, D... -->
                <div class="flex items-center gap-1.5">
                    {#each plottingConfig.optionLabels ?? ['A', 'B', 'C', 'D'] as optLabel, optIdx}
                        {@const isPointPlaced = Boolean(plottingConfig.pointsMm?.[optIdx])}
                        {@const isCurrentLetter =
                            plottingConfig.active &&
                            plottingConfig.mode === 'consecutive' &&
                            plottingConfig.currentOptionIndex === optIdx}
                        <button
                            type="button"
                            data-omr-plotter-letter={optLabel}
                            onclick={() => {
                                if (plottingConfig.active && plottingConfig.mode === 'consecutive') {
                                    plottingConfig.currentOptionIndex = optIdx;
                                } else if (activeSection && activeSection.type !== 'roll') {
                                    startRespotOptionOnActiveItem(optIdx);
                                } else {
                                    startConsecutiveItemPlotting();
                                    plottingConfig.currentOptionIndex = optIdx;
                                }
                            }}
                            title={plottingConfig.active
                                ? `Click on canvas to place Option ${optLabel}`
                                : `Click to re-spot Option ${optLabel} on canvas`}
                            class={`flex flex-1 items-center justify-center gap-1 rounded-lg border py-1.5 font-mono text-xs font-black transition ${
                                isCurrentLetter
                                    ? 'border-emerald-500 bg-emerald-600 text-white shadow-sm ring-2 ring-emerald-400/50'
                                    : isPointPlaced
                                      ? 'border-emerald-500/50 bg-emerald-500/15 text-emerald-800 dark:text-emerald-200'
                                      : 'border-border bg-muted/30 text-foreground hover:bg-muted'
                            }`}
                        >
                            <span>{optLabel}</span>
                            {#if isPointPlaced}
                                <span class="text-[9px]">✓</span>
                            {/if}
                        </button>
                    {/each}
                </div>

            <!-- Circle Diameter / Radius Slider -->
            <div class="space-y-1">
                <div class="flex items-center justify-between text-[10px]">
                    <div class="flex items-center gap-1">
                        <span class="text-muted-foreground font-semibold">
                            Bubble Size (Radius {(Number(plottingConfig.bubbleDiameterMm || defaultBubbleDiameterMm) / 2).toFixed(2)} mm):
                        </span>
                        <StudioTip text="Diameter of optical bubble targets. Standard OMR is 4.5 mm Ø." />
                    </div>
                    <span class="font-mono font-bold text-emerald-700 dark:text-emerald-300" data-omr-plotter-diameter-readout="true">
                        {Number(plottingConfig.bubbleDiameterMm || defaultBubbleDiameterMm).toFixed(1)} mm Ø
                    </span>
                </div>
                <input
                    type="range"
                    min={minBubbleDiameter}
                    max="8.0"
                    step="0.1"
                    data-omr-plotter-radius-slider="true"
                    value={plottingConfig.bubbleDiameterMm ?? defaultBubbleDiameterMm}
                    onmousedown={(e) => handleSliderPointerDown(e.currentTarget.value)}
                    oninput={(e) => handleRadiusSliderInput(e.currentTarget.value)}
                    class="accent-primary bg-muted h-1.5 w-full cursor-pointer rounded-lg"
                />
            </div>

            <!-- Primary Plot Trigger Button -->
            <button
                type="button"
                data-omr-start-plot="true"
                disabled={readOnly}
                onclick={() => {
                    if (plottingConfig.active && !plottingConfig.targetType) {
                        plottingConfig.active = false;
                        plottingConfig.pointsMm = [];
                        plottingConfig.currentOptionIndex = 0;
                    } else {
                        startConsecutiveItemPlotting();
                    }
                }}
                class={`flex w-full items-center justify-center gap-2 rounded-xl px-3 py-2 text-xs font-bold shadow-xs transition ${
                    plottingConfig.active && !plottingConfig.targetType
                        ? 'bg-amber-600 text-white hover:bg-amber-500'
                        : 'bg-emerald-700 text-white hover:bg-emerald-600'
                }`}
            >
                {#if plottingConfig.active && !plottingConfig.targetType}
                    <XCircle class="size-3.5" />
                    <span>
                        Cancel Plotting ({(plottingConfig.optionLabels ?? ['A'])[plottingConfig.currentOptionIndex ?? 0]} · Esc)
                    </span>
                {:else}
                    <Plus class="size-3.5" />
                    <span>
                        Plot Question {subtests.length > 0
                            ? (subtestCodeFor(activeSection) || plottingConfig.subtest_code || subtests[0]?.code || 'SUBTEST')
                            : (plottingConfig.areaCode || 'GENERAL')} · Q{plottingConfig.itemNumber || 1}
                    </span>
                {/if}
            </button>
        {/if}
        {#if activeSection && activeSection.type !== 'roll' && (Number(activeSection.questionsCount) || 1) === 1}
            <!-- Multiply Downward Generator -->
            <details
                bind:open={showAdvancedTilt}
                class="group space-y-2 rounded-xl border border-emerald-500/30 bg-emerald-500/5 p-2.5 dark:border-emerald-500/20 dark:bg-emerald-950/20"
                data-omr-multiply-downward-card="true"
            >
                <summary class="flex cursor-pointer list-none flex-wrap items-center justify-between gap-2 text-[11px] font-bold focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-emerald-500">
                    <span class="flex items-center gap-1.5">
                        <span class="text-muted-foreground transition-transform group-open:rotate-90" aria-hidden="true">›</span>
                        Multiply Rows / Auto-Propagate
                    </span>
                    <span class="flex flex-wrap items-center gap-1.5 text-[9px] font-semibold text-muted-foreground">
                        <span>{multiplyRowCount} items • {questionMultiplyDirection === 'horizontal' ? 'Right' : 'Down'} • {Number(multiplyRowPitchMm).toFixed(1)} mm</span>
                        {#if multiplyPreviewEnabled}
                            <span class="rounded-full bg-emerald-600/15 px-1.5 py-0.5 text-emerald-700 dark:text-emerald-300">Preview On</span>
                        {/if}
                        {#if propagationSlantDeg !== 0}
                            <span class="rounded-full bg-indigo-600/15 px-1.5 py-0.5 font-mono text-indigo-700 dark:text-indigo-300">{propagationSlantDeg > 0 ? '+' : ''}{Number(propagationSlantDeg).toFixed(1)}°</span>
                        {/if}
                    </span>
                </summary>
                <div class="flex flex-wrap items-center justify-end gap-1 bg-muted/60 p-0.5 rounded-lg border border-border/70 text-[9.5px]">
                    <button
                        type="button"
                        data-omr-q-mult-dir="vertical"
                        onclick={() => {
                            questionMultiplyDirection = 'vertical';
                        }}
                        class={`px-2 py-0.5 rounded font-bold transition flex items-center gap-1 ${
                            questionMultiplyDirection === 'vertical'
                                ? 'bg-emerald-700 text-white shadow-xs'
                                : 'text-muted-foreground hover:text-foreground'
                        }`}
                    >
                        {#if questionMultiplyDirection === 'vertical'}<span>✓</span>{/if}
                        <span>Vertical (Down)</span>
                    </button>
                    <button
                        type="button"
                        data-omr-q-mult-dir="horizontal"
                        onclick={() => {
                            questionMultiplyDirection = 'horizontal';
                        }}
                        class={`px-2 py-0.5 rounded font-bold transition flex items-center gap-1 ${
                            questionMultiplyDirection === 'horizontal'
                                ? 'bg-emerald-700 text-white shadow-xs'
                                : 'text-muted-foreground hover:text-foreground'
                        }`}
                    >
                        {#if questionMultiplyDirection === 'horizontal'}<span>✓</span>{/if}
                        <span>Horizontal (Across)</span>
                    </button>
                </div>
                <div class="flex items-center justify-between text-[10px]">
                    {#if measuredVerticalStep != null}
                        <span class="font-mono text-[9px] text-emerald-600 dark:text-emerald-400 font-semibold">
                            Step: {measuredVerticalStep.toFixed(2)}mm
                        </span>
                    {/if}
                    <div class="flex items-center gap-1.5 select-none">
                        <span class="text-[9.5px] font-semibold text-muted-foreground">Preview</span>
                        <button
                                type="button"
                                role="switch"
                                aria-checked={multiplyPreviewEnabled}
                                data-omr-multiply-preview-toggle="true"
                                onclick={() => {
                                    multiplyPreviewEnabled = !multiplyPreviewEnabled;
                                }}
                                class={`relative inline-flex h-4 w-7 shrink-0 cursor-pointer rounded-full border-2 border-transparent transition-colors duration-200 ease-in-out focus:outline-none ${
                                    multiplyPreviewEnabled
                                        ? 'bg-emerald-600'
                                        : 'bg-muted-foreground/30'
                                }`}
                            >
                                <span
                                    aria-hidden="true"
                                    class={`pointer-events-none inline-block size-3 transform rounded-full bg-white shadow-sm ring-0 transition duration-200 ease-in-out ${
                                        multiplyPreviewEnabled
                                            ? 'translate-x-3'
                                            : 'translate-x-0'
                                    }`}
                                ></span>
                            </button>
                        </div>
                    </div>
                <div class="grid grid-cols-2 gap-2 text-[10px]">
                    <label class="space-y-0.5">
                        <span class="text-muted-foreground block font-semibold">Rows to add</span>
                        <input
                            type="number"
                            min="1"
                            max="100"
                            bind:value={multiplyRowCount}
                            data-omr-multiply-count="true"
                            class="w-full rounded-md border bg-background px-2 py-1 font-mono text-xs font-bold"
                        />
                    </label>
                    <label class="space-y-0.5">
                        <span class="text-muted-foreground block font-semibold">Row pitch (mm)</span>
                        <input
                            type="number"
                            min="2.0"
                            max="50.0"
                            step="0.05"
                            bind:value={multiplyRowPitchMm}
                            data-omr-multiply-pitch="true"
                            class="w-full rounded-md border bg-background px-2 py-1 font-mono text-xs font-bold"
                        />
                    </label>
                </div>
                <div class="space-y-1.5 border-t border-border/60 pt-2">
                    <div class="flex flex-wrap items-center justify-between gap-2 text-[10px]">
                        <span class="font-semibold text-muted-foreground">Propagation incline / slant</span>
                        <div class="flex items-center gap-1 font-mono font-bold">
                            <span>{propagationSlantDeg > 0 ? `+${propagationSlantDeg.toFixed(1)}` : propagationSlantDeg.toFixed(1)}°</span>
                            {#if propagationSlantDeg !== 0}
                                <button
                                    type="button"
                                    onclick={() => {
                                        propagationSlantDeg = 0;
                                    }}
                                    class="text-[9px] text-muted-foreground hover:underline ml-1"
                                >Reset</button>
                            {/if}
                        </div>
                    </div>
                    <input
                        type="range"
                        min="-10.0"
                        max="10.0"
                        step="0.1"
                        bind:value={propagationSlantDeg}
                        data-omr-slant-slider="true"
                        data-omr-slant-toggle="true"
                        class="accent-indigo-600 bg-muted h-1.5 w-full cursor-pointer rounded-lg"
                    />
                    <div class="flex flex-wrap items-center justify-between gap-1 text-[9px]">
                        <button type="button" onclick={() => { propagationSlantDeg = Number((propagationSlantDeg - 0.5).toFixed(1)); }} class="rounded border px-1 py-0.5 hover:bg-muted">-0.5°</button>
                        <button type="button" onclick={() => { propagationSlantDeg = Number((propagationSlantDeg - 0.1).toFixed(1)); }} class="rounded border px-1 py-0.5 hover:bg-muted">-0.1°</button>
                        <span class="text-muted-foreground">Natural Tilt</span>
                        <button type="button" onclick={() => { propagationSlantDeg = Number((propagationSlantDeg + 0.1).toFixed(1)); }} class="rounded border px-1 py-0.5 hover:bg-muted">+0.1°</button>
                        <button type="button" onclick={() => { propagationSlantDeg = Number((propagationSlantDeg + 0.5).toFixed(1)); }} class="rounded border px-1 py-0.5 hover:bg-muted">+0.5°</button>
                    </div>
                </div>
                <button
                    type="button"
                    data-omr-multiply-execute="true"
                    onclick={executeMultiplyDownward}
                    class="flex w-full items-center justify-center gap-1.5 rounded-lg bg-emerald-700 px-2.5 py-1.5 text-xs font-bold text-white shadow-xs transition hover:bg-emerald-600"
                >
                    <span>↓ Generate {multiplyRowCount} Items Downward (+{(Number(multiplyRowCount || 1) * Number(multiplyRowPitchMm || 6.8)).toFixed(1)}mm)</span>
                </button>
            </details>
        {/if}

        {#if sections.some((s) => s.type !== 'roll')}
            <div class="border-border/70 space-y-2 border-t pt-2.5" data-omr-solid-items-list="true">
                <div class="flex items-center justify-between text-[10px]">
                    <span class="text-muted-foreground font-bold uppercase tracking-wider">
                        Plotted Items ({sections.filter((s) => s.type !== 'roll').length})
                    </span>
                    <span class="text-muted-foreground">Click to select (Shift for range)</span>
                </div>

                <!-- Bulk Actions Toolbar (Visible when 2+ items selected) -->
                {#if selectedSectionIndices.length >= 2}
                    <div
                        inert={readOnly}
                        class="rounded-xl border border-indigo-500/30 bg-indigo-50/70 p-2.5 space-y-2 dark:border-indigo-500/20 dark:bg-indigo-950/30"
                        data-omr-bulk-actions-toolbar="true"
                    >
                        <div class="flex items-center justify-between text-[11px]">
                            <span class="font-bold text-indigo-950 dark:text-indigo-200">
                                {selectedSectionIndices.length} items selected
                            </span>
                            <button
                                type="button"
                                onclick={clearSectionMultiSelect}
                                data-omr-bulk-clear="true"
                                class="text-[10px] font-semibold text-muted-foreground underline hover:text-foreground"
                            >
                                Clear
                            </button>
                        </div>
                        <div class="flex flex-wrap items-center gap-1">
                            <button type="button" onclick={() => onBatchAlign?.('left')} class="rounded-md border border-indigo-300 px-2 py-1 text-xs hover:bg-indigo-100 dark:border-indigo-800 dark:hover:bg-indigo-900/50" title="Align Left" aria-label="Align Left"><AlignLeft class="size-4" /></button>
                            <button type="button" onclick={() => onBatchAlign?.('right')} class="rounded-md border border-indigo-300 px-2 py-1 text-xs hover:bg-indigo-100 dark:border-indigo-800 dark:hover:bg-indigo-900/50" title="Align Right" aria-label="Align Right"><AlignRight class="size-4" /></button>
                            <button type="button" onclick={() => onBatchAlign?.('top')} class="rounded-md border border-indigo-300 px-2 py-1 text-xs hover:bg-indigo-100 dark:border-indigo-800 dark:hover:bg-indigo-900/50" title="Align Top" aria-label="Align Top"><AlignVerticalSpaceAround class="size-4 rotate-90" /></button>
                            <button type="button" onclick={() => onBatchAlign?.('bottom')} class="rounded-md border border-indigo-300 px-2 py-1 text-xs hover:bg-indigo-100 dark:border-indigo-800 dark:hover:bg-indigo-900/50" title="Align Bottom" aria-label="Align Bottom"><ArrowDown class="size-4" /></button>
                            {#if selectedSectionIndices.length >= 3}
                                <button type="button" onclick={() => onBatchDistribute?.('horizontal')} class="rounded-md border border-indigo-300 px-2 py-1 text-xs hover:bg-indigo-100 dark:border-indigo-800 dark:hover:bg-indigo-900/50" title="Distribute Horizontally" aria-label="Distribute Horizontally"><ArrowRight class="size-4" /></button>
                                <button type="button" onclick={() => onBatchDistribute?.('vertical')} class="rounded-md border border-indigo-300 px-2 py-1 text-xs hover:bg-indigo-100 dark:border-indigo-800 dark:hover:bg-indigo-900/50" title="Distribute Vertically" aria-label="Distribute Vertically"><ArrowDown class="size-4" /></button>
                            {/if}
                            <button type="button" onclick={() => onBatchToggleLock?.()} class="rounded-md border border-indigo-300 px-2 py-1 text-xs hover:bg-indigo-100 dark:border-indigo-800 dark:hover:bg-indigo-900/50" title="Lock All / Unlock All" aria-label="Lock All or Unlock All"><Lock class="size-4" /></button>
                        </div>
                        <div class="flex items-center gap-1.5">
                            {#if aptitudeAreas.length > 0}
                                <select
                                    bind:value={bulkTargetArea}
                                    data-omr-bulk-area-select="true"
                                    class="w-full rounded-md border bg-background px-2 py-1 text-xs font-semibold"
                                >
                                    {#each aptitudeAreas as area}
                                        <option value={area.code}>{area.name} ({area.code})</option>
                                    {/each}
                                </select>
                            {:else}
                                <input
                                    type="text"
                                    bind:value={bulkTargetArea}
                                    placeholder="AREA"
                                    data-omr-bulk-area-input="true"
                                    class="w-full rounded-md border bg-background px-2 py-1 text-xs font-semibold uppercase"
                                />
                            {/if}
                            <button
                                type="button"
                                onclick={executeBulkReassignArea}
                                data-omr-bulk-reassign-btn="true"
                                class="shrink-0 rounded-md bg-indigo-700 hover:bg-indigo-600 px-2.5 py-1 text-xs font-bold text-white shadow-xs transition"
                            >
                                Reassign
                            </button>
                            <button
                                type="button"
                                onclick={executeBulkDelete}
                                data-omr-bulk-delete-btn="true"
                                class="shrink-0 rounded-md bg-rose-600/15 hover:bg-rose-600/25 px-2 py-1 text-xs font-semibold text-rose-700 dark:text-rose-300 transition"
                                title="Delete all selected items"
                            >
                                ✕ Del ({selectedSectionIndices.length})
                            </button>
                        </div>
                        <!-- Group Rotation Controls -->
                        <div class="flex items-center justify-between border-t border-indigo-200/50 pt-1.5 text-[9.5px] dark:border-indigo-800/50">
                            <span class="text-indigo-900 dark:text-indigo-200 font-semibold">Rotate Group:</span>
                            <div class="flex items-center gap-1">
                                <button type="button" data-omr-rotate-group="-0.5" onclick={() => executeRotateGroup(-0.5)} class="rounded border px-1.5 py-0.5 font-mono hover:bg-indigo-100 dark:hover:bg-indigo-900/50">⟲ -0.5°</button>
                                <button type="button" data-omr-rotate-group="-0.1" onclick={() => executeRotateGroup(-0.1)} class="rounded border px-1.5 py-0.5 font-mono hover:bg-indigo-100 dark:hover:bg-indigo-900/50">-0.1°</button>
                                <button type="button" data-omr-rotate-group="+0.1" onclick={() => executeRotateGroup(0.1)} class="rounded border px-1.5 py-0.5 font-mono hover:bg-indigo-100 dark:hover:bg-indigo-900/50">+0.1°</button>
                                <button type="button" data-omr-rotate-group="+0.5" onclick={() => executeRotateGroup(0.5)} class="rounded border px-1.5 py-0.5 font-mono hover:bg-indigo-100 dark:hover:bg-indigo-900/50">+0.5° ⟳</button>
                            </div>
                        </div>
                    </div>
                {/if}

                <!-- Plotted Items Grouped by Aptitude Area Accordions -->
                <div class="space-y-1.5 max-h-56 overflow-y-auto pr-0.5">
                    {#each sectionsByAptitude as group (group.code)}
                        {@const itemIndices = group.items.map((it) => it.originalIndex)}
                        {@const allInGroupSelected = itemIndices.length > 0 && itemIndices.every((idx) => selectedSectionIndices.includes(idx))}
                        <div
                            class="border border-border/70 rounded-lg overflow-hidden bg-card/60 shadow-2xs"
                            data-omr-aptitude-group={group.code}
                        >
                            <!-- Accordion Header -->
                            <div class="w-full flex items-center justify-between px-2.5 py-1.5 bg-muted/40 hover:bg-muted/70 text-xs font-bold transition select-none text-left">
                                <button
                                    type="button"
                                    aria-expanded={!closedAptitudeGroups.has(group.code)}
                                    aria-controls={`omr-aptitude-panel-${group.code}`}
                                    onclick={() => toggleAptitudeGroup(group.code)}
                                    class="flex items-center gap-1.5 min-w-0 flex-1 text-left"
                                >
                                    <span class="text-[10px] text-muted-foreground">{!closedAptitudeGroups.has(group.code) ? '▾' : '▸'}</span>
                                    <span class="truncate">{group.name || group.code} ({group.code})</span>
                                </button>
                                <div class="flex items-center gap-2 shrink-0">
                                    <button
                                        type="button"
                                        onclick={() => selectAllInArea(group.items)}
                                        class="text-[10px] text-primary hover:underline font-medium"
                                        title="Toggle multi-select for all items in this area"
                                    >
                                        {allInGroupSelected ? 'Deselect area' : 'Select all'}
                                    </button>
                                    <span class="rounded-full bg-emerald-500/15 text-emerald-800 dark:text-emerald-300 px-2 py-0.5 text-[9px] font-mono font-semibold shrink-0">
                                        {group.items.length} {group.items.length === 1 ? 'item' : 'items'}
                                    </span>
                                </div>
                            </div>
                            {#if !closedAptitudeGroups.has(group.code)}
                                <div id={`omr-aptitude-panel-${group.code}`} class="p-2 flex flex-wrap gap-1.5 max-h-36 overflow-y-auto">
                                    {#each group.items as item (item.id)}
                                        {@const secIdx = item.originalIndex}
                                        {@const isSelected = selectedSectionIndices.includes(secIdx) || activeSectionIndex === secIdx}
                                        <button
                                            type="button"
                                            data-omr-solid-item-chip={item.id}
                                            data-omr-selected={isSelected ? "true" : "false"}
                                            onclick={(e) => handleChipClick(e, secIdx)}
                                            class={`flex items-center gap-1 rounded-lg border px-2 py-1 font-mono text-[10px] font-bold transition ${
                                                isSelected
                                                    ? 'border-indigo-600 bg-indigo-600 text-white shadow-xs'
                                                    : 'border-border bg-muted/40 text-foreground hover:bg-muted'
                                            }`}
                                        >
                                            <span>{item.area_code || group.code} · Q{item.startQ ?? 1}</span>
                                            {#if (Number(item.questionsCount) || 1) > 1}
                                                <span class="opacity-75">–{Number(item.startQ) + Number(item.questionsCount) - 1}</span>
                                            {/if}
                                        </button>
                                    {/each}
                                </div>
                            {/if}
                        </div>
                    {/each}
                </div>

                {#if activeSection && activeSection.type !== 'roll'}
                    <div class="space-y-2 rounded-lg border border-border/70 bg-muted/20 p-2.5">
                        <div class="flex flex-wrap items-center justify-between gap-2">
                            <div>
                                <h3 class="text-[11px] font-bold">
                                    Selected {activeSectionQuestionCount > 1 ? 'Block' : 'Question'}
                                    {activeSection.startQ ?? 1}{#if activeSectionQuestionCount > 1}–{Number(activeSection.startQ ?? 1) + activeSectionQuestionCount - 1}{/if}
                                </h3>
                                <p class="text-[9px] text-muted-foreground">
                                    {activeSection.area_code || activeSection.aptitudeAreaCode || 'GENERAL'} · {activeSectionOptionTokens.length} options · {subtestCodeFor(activeSection) || 'No subtest'}
                                </p>
                            </div>
                            {#if activeSection.locked || activeSection.isLocked}
                                <span class="rounded-full bg-amber-500/15 px-2 py-0.5 text-[9px] font-semibold text-amber-800 dark:text-amber-300">Locked</span>
                            {/if}
                        </div>
                        <div class="grid grid-cols-2 gap-2">
                            <label class="space-y-0.5">
                                <span class="text-muted-foreground block text-[9.5px] font-semibold">Area</span>
                                <input
                                    type="text"
                                    data-omr-active-item-area="true"
                                    value={activeSection.area_code ?? 'GENERAL'}
                                    oninput={(e) => handleAreaChange(e)}
                                    class="w-full rounded border px-2 py-1 text-xs font-semibold uppercase"
                                />
                            </label>
                            <label class="space-y-0.5">
                                <span class="text-muted-foreground block text-[9.5px] font-semibold">Item #</span>
                                <input
                                    type="number"
                                    min="1"
                                    max="500"
                                    data-omr-active-item-number="true"
                                    value={activeSection.startQ}
                                    oninput={(e) => handleItemNumberChange(e)}
                                    class="w-full rounded border px-2 py-1 font-mono text-xs font-bold"
                                />
                            </label>
                        </div>
                        {#if isUploadedSource}
                            <div class="grid grid-cols-1 gap-2 border-t border-border/50 pt-2 sm:grid-cols-2" data-omr-uploaded-item-pitch="true">
                                <label class="space-y-0.5">
                                    <span class="text-muted-foreground block text-[9.5px] font-semibold">Bubble Spacing (X)</span>
                                    <span class="font-mono text-[9px] text-muted-foreground">
                                        {Math.max(minimumRecognitionPitchMm(activeSection.bubbleDiameterMm, minBubbleDiameter), Number(activeSection.bubblePitchMm) || 6.0).toFixed(2)} mm
                                    </span>
                                    <input
                                        type="range"
                                        min={minimumRecognitionPitchMm(activeSection.bubbleDiameterMm, minBubbleDiameter)}
                                        step="0.1"
                                        value={Math.max(minimumRecognitionPitchMm(activeSection.bubbleDiameterMm, minBubbleDiameter), Number(activeSection.bubblePitchMm) || 6.0)}
                                        data-omr-uploaded-item-bubble-pitch="true"
                                        oninput={(e) => {
                                            activeSection.bubblePitchMm = Number(e.currentTarget.value);
                                            handleInput();
                                        }}
                                        class="accent-primary bg-muted h-1.5 w-full cursor-pointer rounded-lg"
                                    />
                                </label>
                                {#if activeSectionQuestionCount > 1}
                                    <label class="space-y-0.5">
                                        <span class="text-muted-foreground block text-[9.5px] font-semibold">Row Spacing (Y)</span>
                                        <span class="font-mono text-[9px] text-muted-foreground">
                                            {Math.max(minimumRecognitionPitchMm(activeSection.bubbleDiameterMm, minBubbleDiameter), Number(activeSection.rowPitchMm) || 6.8).toFixed(2)} mm
                                        </span>
                                        <input
                                            type="range"
                                            min={minimumRecognitionPitchMm(activeSection.bubbleDiameterMm, minBubbleDiameter)}
                                            step="0.2"
                                            value={Math.max(minimumRecognitionPitchMm(activeSection.bubbleDiameterMm, minBubbleDiameter), Number(activeSection.rowPitchMm) || 6.8)}
                                            data-omr-uploaded-item-row-pitch="true"
                                            oninput={(e) => {
                                                activeSection.rowPitchMm = Number(e.currentTarget.value);
                                                handleInput();
                                            }}
                                            class="accent-primary bg-muted h-1.5 w-full cursor-pointer rounded-lg"
                                        />
                                    </label>
                                {/if}
                            </div>
                        {/if}
                        <details
                            class="group border-t border-border/60 pt-2"
                            data-testid="advanced-per-bubble-details"
                        >
                            <summary class="flex cursor-pointer list-none flex-wrap items-center justify-between gap-2 text-[10px] font-semibold focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary">
                                <span class="flex items-center gap-1.5">
                                    <span class="text-muted-foreground transition-transform group-open:rotate-90" aria-hidden="true">›</span>
                                    Tilt &amp; Fine Alignment
                                </span>
                                <span class="flex flex-wrap items-center gap-1.5">
                                    {#if Number(activeSection.rotationDeg ?? 0) !== 0}
                                        <span class="rounded-full bg-indigo-500/10 px-1.5 py-0.5 font-mono text-[9px] text-indigo-700 dark:text-indigo-300">
                                            {Number(activeSection.rotationDeg) > 0 ? '+' : ''}{Number(activeSection.rotationDeg).toFixed(1)}°
                                        </span>
                                    {/if}
                                    {#if hasCustomOptionPoints}
                                        <span class="rounded-full bg-amber-500/10 px-1.5 py-0.5 text-[9px] text-amber-800 dark:text-amber-300">Custom per-bubble</span>
                                    {/if}
                                </span>
                            </summary>
                            <div class="space-y-3 pt-2">
                                <div class="space-y-1.5">
                                    <div class="flex flex-wrap items-center justify-between gap-2 text-[10px]">
                                        <span class="font-semibold">
                                            {activeSectionQuestionCount > 1 ? 'Selected Block Tilt / Angle (°)' : `Tilt Item (${((Number(activeSection.rotationDeg) || 0) >= 0 ? '+' : '') + (Number(activeSection.rotationDeg) || 0).toFixed(1)}°):`}
                                        </span>
                                        <span class="font-mono font-bold">{((Number(activeSection.rotationDeg) || 0) >= 0 ? '+' : '') + (Number(activeSection.rotationDeg) || 0).toFixed(1)}°</span>
                                    </div>
                                    <input
                                        type="range"
                                        min="-5"
                                        max="5"
                                        step="0.1"
                                        data-testid="question-tilt-slider"
                                        value={Math.max(-5, Math.min(5, Number(activeSection.rotationDeg) || 0))}
                                        disabled={readOnly || activeSection.isLocked || activeSection.locked}
                                        oninput={(e) => applyActiveSectionTilt(Number(e.currentTarget.value))}
                                        class="accent-primary bg-muted h-1.5 w-full cursor-pointer rounded-lg disabled:cursor-not-allowed"
                                    />
                                    <div class="flex flex-wrap gap-1.5">
                                        {#each [-0.5, -0.1, 0, 0.1, 0.5] as step}
                                            <button
                                                type="button"
                                                disabled={readOnly || activeSection.isLocked || activeSection.locked}
                                                onclick={() => applyActiveSectionTilt(step === 0 ? 0 : (Number(activeSection.rotationDeg) || 0) + step)}
                                                class="rounded border px-2 py-1 font-mono text-[10px] font-semibold hover:bg-muted disabled:cursor-not-allowed disabled:opacity-50"
                                            >{step === 0 ? '0° Reset' : `${step > 0 ? '+' : ''}${step}°`}</button>
                                        {/each}
                                    </div>
                                </div>
                                {#if hasCustomOptionPoints}
                                    <button
                                        type="button"
                                        data-testid="reset-uniform-grid-btn"
                                        disabled={readOnly || activeSection.isLocked || activeSection.locked}
                                        onclick={resetActiveSectionToUniformGrid}
                                        class="rounded-md border border-border bg-background px-2.5 py-1 text-[10px] font-semibold hover:bg-muted disabled:cursor-not-allowed disabled:opacity-50"
                                    >Reset to Uniform Grid</button>
                                {/if}
                                {#if hasCustomOptionPoints}
                                    <div class="space-y-1.5 border-t border-border/60 pt-2">
                                        <div class="flex flex-wrap items-center justify-between gap-2">
                                            <h4 class="text-[10px] font-semibold">Per-Bubble Detailing</h4>
                                            <span class="text-[9px] text-muted-foreground">Custom option positions</span>
                                        </div>
                                        <p class="text-[9px] text-muted-foreground">Reset to Uniform Grid restores evenly spaced option positions.</p>
                                        <div class="space-y-2">
                                            {#each Array.from({ length: activeSectionQuestionCount }, (_, qIdx) => qIdx) as qIdx (qIdx)}
                                                <div class="space-y-1.5">
                                                    {#if activeSectionQuestionCount > 1}
                                                        <p class="text-[9px] font-semibold text-muted-foreground">Question {qIdx + 1}</p>
                                                    {/if}
                                                    <div class="space-y-1.5">
                                                        {#each activeSectionOptionTokens as token, optIdx (token + '-' + optIdx)}
                                                            {@const pointIndex = qIdx * activeSectionOptionTokens.length + optIdx}
                                                            {@const point = activeSection.optionPointsMm[pointIndex]}
                                                            <div class="grid grid-cols-2 items-end gap-1.5 rounded-md border border-border/50 bg-background/50 p-1.5 sm:grid-cols-[auto_repeat(3,minmax(0,1fr))_auto]">
                                                                <Badge variant="outline" class="mb-1 text-[9px]">{point.label || token}</Badge>
                                                                <label class="space-y-0.5">
                                                                    <span class="block text-[9px] text-muted-foreground">X (mm)</span>
                                                                    <input
                                                                        type="number"
                                                                        step="0.1"
                                                                        value={Number(point.x_mm).toFixed(2)}
                                                                        disabled={readOnly || activeSection.isLocked || activeSection.locked}
                                                                        oninput={(event) => updateCustomOptionPoint(pointIndex, 'x_mm', event.currentTarget.value, (qIdx + 1) * activeSectionOptionTokens.length)}
                                                                        class="w-full min-w-0 rounded border border-input bg-background px-1.5 py-1 font-mono text-[10px] disabled:opacity-50"
                                                                    />
                                                                </label>
                                                                <label class="space-y-0.5">
                                                                    <span class="block text-[9px] text-muted-foreground">Y (mm)</span>
                                                                    <input
                                                                        type="number"
                                                                        step="0.1"
                                                                        value={Number(point.y_mm).toFixed(2)}
                                                                        disabled={readOnly || activeSection.isLocked || activeSection.locked}
                                                                        oninput={(event) => updateCustomOptionPoint(pointIndex, 'y_mm', event.currentTarget.value, (qIdx + 1) * activeSectionOptionTokens.length)}
                                                                        class="w-full min-w-0 rounded border border-input bg-background px-1.5 py-1 font-mono text-[10px] disabled:opacity-50"
                                                                    />
                                                                </label>
                                                                {#if optIdx < activeSectionOptionTokens.length - 1}
                                                                    <label class="space-y-0.5">
                                                                        <span class="block text-[9px] text-muted-foreground">ΔX to next (mm)</span>
                                                                        <input
                                                                            type="number"
                                                                            step="0.1"
                                                                            value={(Number(activeSection.optionPointsMm[pointIndex + 1].x_mm) - Number(point.x_mm)).toFixed(2)}
                                                                            disabled={readOnly || activeSection.isLocked || activeSection.locked}
                                                                            oninput={(event) => updateCustomOptionPoint(pointIndex, 'deltaX', event.currentTarget.value, (qIdx + 1) * activeSectionOptionTokens.length)}
                                                                            class="w-full min-w-0 rounded border border-input bg-background px-1.5 py-1 font-mono text-[10px] disabled:opacity-50"
                                                                        />
                                                                    </label>
                                                                {/if}
                                                                {#if activeSectionQuestionCount === 1}
                                                                    <button
                                                                        type="button"
                                                                        disabled={readOnly || activeSection.isLocked || activeSection.locked}
                                                                        onclick={() => startRespotSingleOption(activeSectionIndex, optIdx)}
                                                                        class="rounded border border-sky-500/40 px-1.5 py-1 text-[9px] font-semibold text-sky-700 hover:bg-sky-500/10 disabled:opacity-50 dark:text-sky-300"
                                                                    >Re-spot</button>
                                                                {/if}
                                                            </div>
                                                        {/each}
                                                    </div>
                                                </div>
                                            {/each}
                                        </div>
                                    </div>
                                {/if}
                                {#if !isUploadedSource}
                                    <div class="grid grid-cols-2 gap-2 border-t border-border/60 pt-2">
                                        <label class="block space-y-1">
                                            <span class="text-muted-foreground block text-[10px] font-medium">Origin X (mm)</span>
                                            <input type="number" min="10" max="180" bind:value={activeSection.posX} oninput={handleInput} class="bg-background border-input text-foreground focus:ring-ring w-full rounded-lg border px-2.5 py-1.5 text-xs focus:ring-2 focus:outline-none" />
                                        </label>
                                        <label class="block space-y-1">
                                            <span class="text-muted-foreground block text-[10px] font-medium">Origin Y (mm)</span>
                                            <input type="number" min="20" max="260" bind:value={activeSection.posY} oninput={handleInput} class="bg-background border-input text-foreground focus:ring-ring w-full rounded-lg border px-2.5 py-1.5 text-xs focus:ring-2 focus:outline-none" />
                                        </label>
                                    </div>
                                {/if}
                            </div>
                        </details>
                    </div>
                {/if}
            </div>
        {/if}
    </section>
    {#if isUploadedSource}
        <section
            inert={readOnly}
            class="border-border bg-card space-y-3 rounded-xl border p-3"
            aria-labelledby="uploaded-identity-heading"
        >
            <div class="border-border/70 flex items-center justify-between gap-1.5 border-b pb-2">
                <div class="flex items-center gap-2 min-w-0">
                    <span class="flex size-5 items-center justify-center rounded-md bg-blue-600/15 font-mono text-[10px] font-black text-blue-700 dark:text-blue-300 shrink-0">
                        2
                    </span>
                    <div class="min-w-0">
                        <h3
                            id="uploaded-identity-heading"
                            class="text-foreground text-xs font-bold truncate block"
                        >
                            Applicant Number
                        </h3>
                        <span class="text-[9.5px] text-muted-foreground block truncate">Examinee identity method & digit grid</span>
                    </div>
                </div>
                <button
                    type="button"
                    onclick={() => { applicantNumberCollapsed = !applicantNumberCollapsed; }}
                    class="text-muted-foreground hover:text-foreground rounded p-1 transition shrink-0"
                    title={applicantNumberCollapsed ? "Expand Applicant Number section" : "Collapse Applicant Number section"}
                >
                    {#if applicantNumberCollapsed}
                        <ChevronDown class="size-4" />
                    {:else}
                        <ChevronUp class="size-4" />
                    {/if}
                </button>
            </div>
            {#if !applicantNumberCollapsed}
                <div class="grid grid-cols-2 gap-2">
                <button
                    type="button"
                    onclick={() => (identity?.method === null ? selectIdentityMethod('shaded_grid') : toggleBubbles(!hasBubbles))}
                    class={`flex flex-col items-center justify-center rounded-lg border p-2 text-center text-xs transition ${
                        hasBubbles
                            ? 'border-primary bg-primary/10 text-primary font-semibold'
                            : 'border-border bg-muted/20 text-muted-foreground hover:bg-muted/40'
                    }`}
                >
                    <span class="font-semibold">Shaded Grid</span>
                    <span class="text-[9px] opacity-80">{hasBubbles ? 'Active · 0–9 bubbles' : 'Click to enable'}</span>
                </button>
                <button
                    type="button"
                    onclick={() => (identity?.method === null ? selectIdentityMethod('write_in_manual') : toggleWriteIn(!hasWriteIn))}
                    class={`flex flex-col items-center justify-center rounded-lg border p-2 text-center text-xs transition ${
                        hasWriteIn
                            ? 'border-primary bg-primary/10 text-primary font-semibold'
                            : 'border-border bg-muted/20 text-muted-foreground hover:bg-muted/40'
                    }`}
                >
                    <span class="font-semibold">Write-in Box</span>
                    <span class="text-[9px] opacity-80">{hasWriteIn ? 'Active · Manual review' : 'Click to enable'}</span>
                </button>
            </div>

            {#if Number(pageCount) > 1}
                <div class="space-y-1 rounded-lg border border-border/70 bg-muted/20 p-2">
                    <span class="text-[10px] font-semibold text-foreground">Identity Present On:</span>
                    <div class="grid grid-cols-2 gap-1">
                        <button
                            type="button"
                            aria-pressed={identityPages.length === 1 && identityPages[0] === 1}
                            onclick={() => setIdentityPages([1])}
                            class={`rounded-md border px-2 py-1.5 text-[10px] font-semibold ${identityPages.length === 1 && identityPages[0] === 1 ? 'border-primary bg-primary/10 text-primary' : 'border-border text-muted-foreground hover:bg-muted'}`}
                        >Page 1 only</button>
                        <button
                            type="button"
                            aria-pressed={identityPages.length === Number(pageCount)}
                            onclick={() => setIdentityPages(Array.from({ length: Number(pageCount) }, (_, index) => index + 1))}
                            class={`rounded-md border px-2 py-1.5 text-[10px] font-semibold ${identityPages.length === Number(pageCount) ? 'border-primary bg-primary/10 text-primary' : 'border-border text-muted-foreground hover:bg-muted'}`}
                        >All {pageCount} pages</button>
                    </div>
                </div>
            {/if}
            {#if hasBubbles}
                <label class="block space-y-1">
                    <span class="text-muted-foreground block text-[10px]">Exact digit count on every page</span>
                    <input
                        type="number"
                        min="2"
                        max="10"
                        step="1"
                        value={identity.digit_count ?? ''}
                        aria-label="Applicant Number grid digit count"
                        oninput={(event) => applyApplicantDigitCount(event)}
                        class="w-full rounded-md border px-2 py-1.5 text-xs"
                    />
                </label>
                {#each Array.from({ length: Number(pageCount) || 1 }, (_, index) => index + 1) as pageNo}
                    {@const pageGrids = sections.filter((section) => section.identity_grid && Number(section.page_no) === pageNo)}
                    <div class="space-y-2 border-t pt-2">
                        {#if identityPages.includes(pageNo)}
                        <div class="flex items-center justify-between gap-2">
                            <span class="text-foreground text-[10px] font-bold">Page {pageNo}</span>
                            {#if pageGrids.length > 0}
                                <button
                                    type="button"
                                    data-omr-focus-applicant-grid="true"
                                    onclick={() => {
                                        activeSectionIndex = sections.indexOf(pageGrids[0]);
                                    }}
                                    class="text-primary text-[10px] font-semibold hover:underline"
                                >
                                    Focus on canvas
                                </button>
                            {/if}
                        </div>

                        {#if pageGrids[0] && pageGrids[0].rollDirection !== 'horizontal'}
                            {@const tilt = Number(pageGrids[0].rotationDeg) || 0}
                            <div class="flex items-center justify-between gap-2 rounded-lg border border-border/70 bg-muted/20 px-2 py-1.5 text-[10px]" data-omr-applicant-tilt={pageNo}>
                                <span class="font-semibold">Grid tilt · {tilt > 0 ? '+' : ''}{tilt.toFixed(1)}°</span>
                                <div class="flex items-center gap-1">
                                    <button type="button" aria-label={`Tilt Applicant Number grid on Page ${pageNo} by -0.5 degrees`} data-omr-applicant-tilt="-0.5" disabled={tilt - 0.5 < -5} onclick={() => adjustApplicantGridTilt(pageNo, -0.5)} class="rounded border px-1.5 py-0.5 font-mono disabled:opacity-40">-0.5°</button>
                                    <button type="button" aria-label={`Tilt Applicant Number grid on Page ${pageNo} by -0.1 degrees`} data-omr-applicant-tilt="-0.1" disabled={tilt - 0.1 < -5} onclick={() => adjustApplicantGridTilt(pageNo, -0.1)} class="rounded border px-1.5 py-0.5 font-mono disabled:opacity-40">-0.1°</button>
                                    <button type="button" aria-label={`Tilt Applicant Number grid on Page ${pageNo} by +0.1 degrees`} data-omr-applicant-tilt="+0.1" disabled={tilt + 0.1 > 5} onclick={() => adjustApplicantGridTilt(pageNo, 0.1)} class="rounded border px-1.5 py-0.5 font-mono disabled:opacity-40">+0.1°</button>
                                    <button type="button" aria-label={`Tilt Applicant Number grid on Page ${pageNo} by +0.5 degrees`} data-omr-applicant-tilt="+0.5" disabled={tilt + 0.5 > 5} onclick={() => adjustApplicantGridTilt(pageNo, 0.5)} class="rounded border px-1.5 py-0.5 font-mono disabled:opacity-40">+0.5°</button>
                                </div>
                            </div>
                        {/if}

                        <!-- Tactile Digit Plotter Card -->
                        <div class="space-y-2 rounded-xl border border-indigo-500/30 bg-indigo-500/5 p-2.5 dark:border-indigo-500/20 dark:bg-indigo-950/20" data-omr-applicant-plotter-card="true">
                            <div class="flex items-center justify-between text-[11px]">
                                <span class="font-bold text-foreground">
                                    Plot Digit 1 (0..9)
                                </span>
                                <label class="flex items-center gap-1.5 cursor-pointer select-none text-[10px] text-muted-foreground font-semibold">
                                    <input
                                        type="checkbox"
                                        bind:checked={digitSpanMode}
                                        data-omr-digit-span-toggle="true"
                                        class="size-3 rounded border-border text-indigo-600 focus:ring-indigo-500"
                                    />
                                    <span>2-Click Span (0→9)</span>
                                </label>
                            </div>

                            <!-- Digit Pills 0..9 -->
                            <div class="flex items-center gap-1">
                                {#each ['0', '1', '2', '3', '4', '5', '6', '7', '8', '9'] as dVal, dIdx}
                                    {@const isPlaced = plottingConfig.active && plottingConfig.targetType === 'applicant_digit' && Boolean(plottingConfig.pointsMm?.[dIdx])}
                                    {@const isCurrent = plottingConfig.active && plottingConfig.targetType === 'applicant_digit' && (plottingConfig.currentOptionIndex ?? 0) === dIdx}
                                    <button
                                        type="button"
                                        data-omr-digit-pill={dVal}
                                        onclick={() => {
                                            if (plottingConfig.active && plottingConfig.targetType === 'applicant_digit') {
                                                plottingConfig.currentOptionIndex = dIdx;
                                            } else {
                                                startDigitPlotting(dIdx);
                                            }
                                        }}
                                        class={`flex-1 py-1 text-center font-mono text-[10px] font-bold rounded transition border ${
                                            isCurrent
                                                ? 'border-indigo-600 bg-indigo-600 text-white shadow-xs ring-2 ring-indigo-400/50'
                                                : isPlaced
                                                  ? 'border-indigo-500/40 bg-indigo-500/15 text-indigo-900 dark:text-indigo-200'
                                                  : 'border-border bg-muted/40 text-foreground hover:bg-muted'
                                        }`}
                                    >
                                        {dVal}
                                    </button>
                                {/each}
                            </div>

                            <button
                                type="button"
                                data-omr-start-digit-plot="true"
                                disabled={readOnly}
                                onclick={toggleDigitPlotting}
                                class={`flex w-full items-center justify-center gap-2 rounded-lg px-3 py-1.5 text-xs font-bold shadow-xs transition ${
                                    plottingConfig.active && plottingConfig.targetType === 'applicant_digit'
                                        ? 'bg-amber-600 text-white hover:bg-amber-500'
                                        : 'bg-indigo-700 text-white hover:bg-indigo-600'
                                }`}
                            >
                                {#if plottingConfig.active && plottingConfig.targetType === 'applicant_digit'}
                                    <span>Stop Digit Plotting (Esc)</span>
                                {:else}
                                    <span>Plot Digit 1 on Canvas {digitSpanMode ? '(Click 0 then 9)' : '(Click 0..9)'}</span>
                                {/if}
                            </button>
                        </div>

                        <!-- Multiply Digits Card (with Horizontal vs Vertical Direction Tick) -->
                        <div class="space-y-2 rounded-xl border border-indigo-500/30 bg-indigo-50/50 p-2.5 dark:border-indigo-500/20 dark:bg-indigo-950/20" data-omr-multiply-digits-card="true">
                            <div class="flex items-center justify-between text-[11px]">
                                <span class="font-bold text-foreground">
                                    Multiply Digits
                                </span>
                                <!-- Direction Tick Toggle (Horizontal vs Vertical) -->
                                <div class="flex items-center gap-1 bg-muted/60 p-0.5 rounded-lg border border-border/70 text-[9.5px]">
                                    <button
                                        type="button"
                                        data-omr-digit-mult-dir="horizontal"
                                        onclick={() => {
                                            digitMultiplyDirection = 'horizontal';
                                            updateRollPreview();
                                        }}
                                        class={`px-2 py-0.5 rounded font-bold transition flex items-center gap-1 ${
                                            digitMultiplyDirection === 'horizontal'
                                                ? 'bg-indigo-600 text-white shadow-xs'
                                                : 'text-muted-foreground hover:text-foreground'
                                        }`}
                                    >
                                        {#if digitMultiplyDirection === 'horizontal'}<span>✓</span>{/if}
                                        <span>Horizontal (Across)</span>
                                    </button>
                                    <button
                                        type="button"
                                        data-omr-digit-mult-dir="vertical"
                                        onclick={() => {
                                            digitMultiplyDirection = 'vertical';
                                            updateRollPreview();
                                        }}
                                        class={`px-2 py-0.5 rounded font-bold transition flex items-center gap-1 ${
                                            digitMultiplyDirection === 'vertical'
                                                ? 'bg-indigo-600 text-white shadow-xs'
                                                : 'text-muted-foreground hover:text-foreground'
                                        }`}
                                    >
                                        {#if digitMultiplyDirection === 'vertical'}<span>✓</span>{/if}
                                        <span>Vertical (Down)</span>
                                    </button>
                                </div>
                            </div>

                            <div class="grid grid-cols-2 gap-2 text-[10px]">
                                <label class="space-y-0.5">
                                    <span class="text-muted-foreground block font-semibold">Total Digits</span>
                                    <input
                                        type="number"
                                        min="2"
                                        max="10"
                                        value={identity.digit_count ?? digitMultiplyCount}
                                        oninput={(e) => applyApplicantDigitCount(e)}
                                        data-omr-digit-mult-count="true"
                                        class="w-full rounded-md border bg-background px-2 py-1 font-mono text-xs font-bold"
                                    />
                                </label>
                                <label class="space-y-0.5">
                                    <span class="text-muted-foreground block font-semibold">
                                        {digitMultiplyDirection === 'horizontal' ? 'Col Pitch X (mm)' : 'Row Pitch Y (mm)'}
                                    </span>
                                    <input
                                        type="number"
                                        min="3.0"
                                        max="20.0"
                                        step="0.05"
                                        bind:value={digitMultiplyPitchMm}
                                        oninput={(e) => applyApplicantDigitPitch(e.currentTarget.value)}
                                        data-omr-digit-mult-pitch="true"
                                        class="w-full rounded-md border bg-background px-2 py-1 font-mono text-xs font-bold"
                                    />
                                </label>
                            </div>

                            <div class="flex items-center justify-between pt-0.5">
                                <!-- Live Preview Switch Toggle -->
                                <div class="flex items-center gap-1.5 select-none">
                                    <span class="text-[9.5px] font-semibold text-muted-foreground">Live Preview</span>
                                    <button
                                        type="button"
                                        role="switch"
                                        aria-checked={digitPreviewEnabled}
                                        data-omr-digit-preview-toggle="true"
                                        onclick={() => {
                                            digitPreviewEnabled = !digitPreviewEnabled;
                                            updateRollPreview();
                                        }}
                                        class={`relative inline-flex h-4 w-7 shrink-0 cursor-pointer rounded-full border-2 border-transparent transition-colors duration-200 ease-in-out focus:outline-none ${
                                            digitPreviewEnabled ? 'bg-indigo-600' : 'bg-muted-foreground/30'
                                        }`}
                                    >
                                        <span
                                            aria-hidden="true"
                                            class={`pointer-events-none inline-block size-3 transform rounded-full bg-white shadow-sm ring-0 transition duration-200 ease-in-out ${
                                                digitPreviewEnabled ? 'translate-x-3' : 'translate-x-0'
                                            }`}
                                        ></span>
                                    </button>
                                </div>

                                <button
                                    type="button"
                                    data-omr-generate-digits-btn="true"
                                    onclick={executeGenerateDigits}
                                    class="rounded-lg bg-indigo-700 hover:bg-indigo-600 px-3 py-1.5 text-xs font-bold text-white shadow-xs transition"
                                >
                                    ↓ Generate {digitMultiplyCount} Digits
                                </button>
                            </div>
                        </div>

                        {#if pageGrids.length === 0}
                            <button
                                type="button"
                                data-omr-add-applicant-grid="true"
                                onclick={() => addApplicantGrid(pageNo)}
                                class="text-primary text-[10px] font-semibold hover:underline block pt-1"
                            >
                                or create default 6-digit box
                            </button>
                        {/if}
                        {#if pageGrids.length > 0}
                            {@const grid = pageGrids[0]}
                            <div class="bg-muted/20 border-border/80 space-y-2 rounded-lg border p-2.5 text-[11px]" data-omr-applicant-grid-controls="true">
                                <!-- Orientation Selector -->
                                <div class="space-y-1">
                                    <span class="text-muted-foreground block text-[9.5px] font-semibold">Digit Orientation</span>
                                    <div class="grid grid-cols-2 gap-1.5">
                                        <button
                                            type="button"
                                            data-omr-roll-dir="vertical"
                                            onclick={() => {
                                                grid.rollDirection = 'vertical';
                                                handleInput();
                                            }}
                                            class={`rounded-md px-2 py-1 text-xs font-bold transition ${
                                                (grid.rollDirection ?? 'vertical') === 'vertical'
                                                    ? 'bg-primary text-primary-foreground shadow-xs'
                                                    : 'bg-muted/60 text-muted-foreground hover:bg-muted hover:text-foreground'
                                            }`}
                                        >
                                            Vertical (Columns)
                                        </button>
                                        <button
                                            type="button"
                                            data-omr-roll-dir="horizontal"
                                            onclick={() => {
                                                grid.rollDirection = 'horizontal';
                                                handleInput();
                                            }}
                                            class={`rounded-md px-2 py-1 text-xs font-bold transition ${
                                                grid.rollDirection === 'horizontal'
                                                    ? 'bg-primary text-primary-foreground shadow-xs'
                                                    : 'bg-muted/60 text-muted-foreground hover:bg-muted hover:text-foreground'
                                            }`}
                                        >
                                            Horizontal (Rows)
                                        </button>
                                    </div>
                                </div>

                                <!-- Digit Pitch & Value Pitch -->
                                <div class="grid grid-cols-2 gap-2">
                                    <label class="space-y-0.5">
                                        <span class="text-muted-foreground block text-[9.5px]">
                                            {(grid.rollDirection ?? 'vertical') === 'vertical' ? 'Digit Col Spacing (mm)' : 'Value Bubble Spacing (mm)'}
                                        </span>
                                        <input
                                            type="number"
                                            min="3.0"
                                            max="20.0"
                                            step="0.05"
                                            bind:value={grid.bubblePitchMm}
                                            data-omr-roll-col-pitch="true"
                                            oninput={handleInput}
                                            class="w-full rounded border px-2 py-1 font-mono text-xs font-bold"
                                        />
                                    </label>
                                    <label class="space-y-0.5">
                                        <span class="text-muted-foreground block text-[9.5px]">
                                            {(grid.rollDirection ?? 'vertical') === 'vertical' ? 'Value Bubble Spacing (mm)' : 'Digit Row Spacing (mm)'}
                                        </span>
                                        <input
                                            type="number"
                                            min="3.0"
                                            max="20.0"
                                            step="0.05"
                                            bind:value={grid.rowPitchMm}
                                            data-omr-roll-row-pitch="true"
                                            oninput={handleInput}
                                            class="w-full rounded border px-2 py-1 font-mono text-xs font-bold"
                                        />
                                    </label>
                                </div>

                                <!-- Position X and Y -->
                                <div class="grid grid-cols-2 gap-2">
                                    <label class="space-y-0.5">
                                        <span class="text-muted-foreground block text-[9.5px]">Position X (mm)</span>
                                        <input
                                            type="number"
                                            min="0"
                                            max="250"
                                            step="0.5"
                                            bind:value={grid.posX}
                                            data-omr-roll-pos-x="true"
                                            oninput={handleInput}
                                            class="w-full rounded border px-2 py-1 font-mono text-xs"
                                        />
                                    </label>
                                    <label class="space-y-0.5">
                                        <span class="text-muted-foreground block text-[9.5px]">Position Y (mm)</span>
                                        <input
                                            type="number"
                                            min="0"
                                            max="350"
                                            step="0.5"
                                            bind:value={grid.posY}
                                            data-omr-roll-pos-y="true"
                                            oninput={handleInput}
                                            class="w-full rounded border px-2 py-1 font-mono text-xs"
                                        />
                                    </label>
                                </div>

                                <!-- Bubble Diameter Slider -->
                                <div class="space-y-1">
                                    <div class="flex items-center justify-between text-[10px]">
                                        <span class="text-muted-foreground">Bubble Size:</span>
                                        <span class="font-mono font-bold text-emerald-600 dark:text-emerald-400">
                                            {Number(grid.bubbleDiameterMm || 4.5).toFixed(1)} mm Ø
                                        </span>
                                    </div>
                                    <input
                                        type="range"
                                        min={minBubbleDiameter}
                                        max="8.0"
                                        step="0.1"
                                        bind:value={grid.bubbleDiameterMm}
                                        onpointerdown={(e) => handleSliderPointerDown(e.currentTarget.value)}
                                        onmousedown={(e) => handleSliderPointerDown(e.currentTarget.value)}
                                        oninput={(e) => handleApplicantGridRadiusInput(e.currentTarget.value)}
                                        class="accent-primary bg-muted h-1.5 w-full cursor-pointer rounded-lg"
                                    />
                                </div>
                            </div>
                        {/if}
                        {:else}
                            <p class="rounded-lg border border-border/60 bg-muted/20 px-2.5 py-2 text-[10px] leading-relaxed text-muted-foreground">
                                No Applicant Number expected on Page {pageNo} (bound to Page 1 via packet confirmation)
                            </p>
                        {/if}
                    </div>
                {/each}
            {/if}

            {#if hasWriteIn}
                {#each Array.from({ length: Number(pageCount) || 1 }, (_, index) => index + 1) as pageNo}
                    {@const box = (identity?.write_in_boxes ?? []).find((b) => Number(b.page_no) === pageNo) ?? null}
                    <div class="border-border/70 space-y-2 border-t pt-2">
                        {#if identityPages.includes(pageNo) && box}
                        <div class="flex items-center justify-between gap-2">
                            <span class="text-foreground text-[10px] font-semibold">Page {pageNo} Handwritten Box</span>
                            <span class="text-[9px] text-muted-foreground font-mono">
                                {box.w_mm ?? 42} × {box.h_mm ?? 8.5} mm
                            </span>
                            <button type="button" onclick={() => removeWriteInBox(pageNo)} class="rounded-md border border-rose-300 px-2 py-1 text-[9px] font-semibold text-rose-700 hover:bg-rose-50">✕ Remove</button>
                        </div>
                        <p class="text-muted-foreground text-[9px] leading-relaxed">
                            Drag or resize the amber box on the canvas directly over the handwritten boxes on the sheet.
                        </p>
                        <div class="grid grid-cols-2 gap-2 bg-muted/30 rounded-lg p-2">
                            <label class="space-y-1">
                                <span class="text-muted-foreground block text-[9px]">X (mm)</span>
                                <input
                                    type="number"
                                    min="0"
                                    step="0.5"
                                    value={box.x_mm ?? 110}
                                    oninput={(e) => updateWriteInBoxMm(pageNo, 'x_mm', e.currentTarget.value)}
                                    class="w-full rounded-md border px-2 py-1 text-xs"
                                />
                            </label>
                            <label class="space-y-1">
                                <span class="text-muted-foreground block text-[9px]">Y (mm)</span>
                                <input
                                    type="number"
                                    min="0"
                                    step="0.5"
                                    value={box.y_mm ?? 18}
                                    oninput={(e) => updateWriteInBoxMm(pageNo, 'y_mm', e.currentTarget.value)}
                                    class="w-full rounded-md border px-2 py-1 text-xs"
                                />
                            </label>
                            <label class="space-y-1">
                                <span class="text-muted-foreground block text-[9px]">Width (mm)</span>
                                <input
                                    type="number"
                                    min="5"
                                    step="0.5"
                                    value={box.w_mm ?? 42}
                                    oninput={(e) => updateWriteInBoxMm(pageNo, 'w_mm', e.currentTarget.value)}
                                    class="w-full rounded-md border px-2 py-1 text-xs"
                                />
                            </label>
                            <label class="space-y-1">
                                <span class="text-muted-foreground block text-[9px]">Height (mm)</span>
                                <input
                                    type="number"
                                    min="4"
                                    step="0.5"
                                    value={box.h_mm ?? 8.5}
                                    oninput={(e) => updateWriteInBoxMm(pageNo, 'h_mm', e.currentTarget.value)}
                                    class="w-full rounded-md border px-2 py-1 text-xs"
                                />
                            </label>
                        </div>
                        {:else if identityPages.includes(pageNo)}
                            <button type="button" onclick={() => addWriteInBox(pageNo)} class="rounded-md border border-dashed px-2.5 py-2 text-[10px] font-semibold text-primary hover:bg-primary/5">[ + Add Write-in Box ]</button>
                        {:else}
                            <p class="rounded-lg border border-border/60 bg-muted/20 px-2.5 py-2 text-[10px] leading-relaxed text-muted-foreground">
                                No Applicant Number expected on Page {pageNo} (bound to Page 1 via packet confirmation)
                            </p>
                        {/if}
                    </div>
                {/each}
            {/if}
            {/if}
        </section>
        <section
            inert={readOnly}
            class="border-border bg-card space-y-3 rounded-xl border p-3"
            aria-labelledby="document-fields-heading"
            data-omr-document-fields-panel="true"
        >
            <div class="border-border/70 flex items-center justify-between gap-1.5 border-b pb-2">
                <div class="flex items-center gap-2 min-w-0">
                    <span class="flex size-5 items-center justify-center rounded-md bg-violet-600/15 font-mono text-[10px] font-black text-violet-700 dark:text-violet-300 shrink-0">
                        3
                    </span>
                    <div class="min-w-0">
                        <h3 id="document-fields-heading" class="text-foreground text-xs font-bold truncate block">
                            Document Fields
                        </h3>
                        <span class="text-[9.5px] text-muted-foreground block truncate">Printed name, birthdate & signature boxes</span>
                    </div>
                </div>
                <button
                    type="button"
                    onclick={() => { documentFieldsCollapsed = !documentFieldsCollapsed; }}
                    class="text-muted-foreground hover:text-foreground rounded p-1 transition shrink-0"
                    title={documentFieldsCollapsed ? "Expand Document Fields" : "Collapse Document Fields"}
                >
                    {#if documentFieldsCollapsed}
                        <ChevronDown class="size-4" />
                    {:else}
                        <ChevronUp class="size-4" />
                    {/if}
                </button>
            </div>
            {#if !documentFieldsCollapsed}
                <div class="flex flex-wrap gap-1.5">
                {#each [
                    { action: '+ Name', type: 'candidate_name', label: 'Applicant Name', w_mm: 75, h_mm: 10, x_mm: 20, y_mm: 35 },
                    { action: '+ Birthdate', type: 'birthdate', label: 'Birthdate', w_mm: 45, h_mm: 10, x_mm: 20, y_mm: 50 },
                    { action: '+ Room/Seat', type: 'room_seat', label: 'Room / Seat', w_mm: 40, h_mm: 10, x_mm: 20, y_mm: 65 },
                    { action: '+ Signature', type: 'signature', label: 'Signature', w_mm: 60, h_mm: 15, x_mm: 120, y_mm: 250 },
                    { action: '+ Custom', type: 'custom_input', label: 'Custom Field', w_mm: 50, h_mm: 10, x_mm: 20, y_mm: 80 },
                ] as preset}
                    <button
                        type="button"
                        disabled={readOnly}
                        class="rounded-md border border-violet-300 bg-violet-50 px-2 py-1 text-[10px] font-semibold text-violet-800 hover:bg-violet-100"
                        onclick={() => addDocumentField(preset)}
                    >
                        {preset.action}
                    </button>
                {/each}
            </div>
            {#if activeDocumentFields.length === 0}
                <p class="text-muted-foreground text-[10px]">
                    No document fields on Page {activePageNo}.
                </p>
            {:else}
                <div class="space-y-2">
                    {#each activeDocumentFields as field (field.id)}
                        <div
                            class={`space-y-2 rounded-lg border p-2 ${activeDocumentFieldId === field.id ? 'border-violet-500 bg-violet-500/5' : 'border-border'}`}
                            data-omr-document-field-row={field.id}
                        >
                            <div class="flex items-center gap-2">
                                <button
                                    type="button"
                                    class="min-w-0 flex-1 text-left"
                                    aria-pressed={activeDocumentFieldId === field.id}
                                    onclick={() => (activeDocumentFieldId = field.id)}
                                >
                                    <span class="inline-flex rounded bg-violet-100 px-1.5 py-0.5 text-[9px] font-bold text-violet-800">
                                        {field.type}
                                    </span>
                                </button>
                                <button
                                    type="button"
                                    disabled={readOnly}
                                    class="rounded p-1 text-rose-600 hover:bg-rose-50"
                                    aria-label={`Delete ${field.label || 'document field'}`}
                                    onclick={() => removeDocumentField(field.id)}
                                >
                                    <Trash2 class="size-3.5" />
                                </button>
                            </div>
                            <label class="block space-y-1">
                                <span class="text-muted-foreground text-[9px] font-semibold">Label</span>
                                <input
                                    type="text"
                                    readonly={readOnly}
                                    value={field.label ?? ''}
                                    aria-label={`${field.type} field label`}
                                    oninput={(event) => updateDocumentField(field.id, 'label', event.currentTarget.value)}
                                    class="w-full rounded border px-2 py-1 text-[10px]"
                                />
                            </label>
                            <div class="grid grid-cols-4 gap-1.5">
                                {#each ['x_mm', 'y_mm', 'w_mm', 'h_mm'] as key}
                                    <label class="min-w-0 space-y-1">
                                        <span class="text-muted-foreground text-[9px] font-semibold">{key}</span>
                                        <input
                                            type="number"
                                            readonly={readOnly}
                                            min="0"
                                            step="0.1"
                                            value={field[key] ?? 0}
                                            aria-label={`${field.label || field.type} ${key}`}
                                            oninput={(event) => updateDocumentField(field.id, key, Number(event.currentTarget.value))}
                                            class="w-full rounded border px-1.5 py-1 font-mono text-[10px]"
                                        />
                                    </label>
                                {/each}
                            </div>
                        </div>
                    {/each}
                </div>
            {/if}
            {/if}
        </section>

        <div
            class={`rounded-lg border p-2.5 text-[10px] ${uploadMappingReadiness.ready ? 'border-emerald-500/25 bg-emerald-500/10 text-emerald-900 dark:text-emerald-200' : 'border-amber-500/25 bg-amber-500/10 text-amber-900 dark:text-amber-200'}`}
            role={uploadMappingReadiness.ready ? 'status' : 'alert'}
            aria-live={uploadMappingReadiness.ready ? 'polite' : 'assertive'}
        >
            <p class="font-semibold">
                {uploadMappingReadiness.ready
                    ? 'Declared mapping is complete; this is not qualification.'
                    : `Mapping incomplete · ${uploadMappingReadiness.issues.length} blocking detail${uploadMappingReadiness.issues.length === 1 ? '' : 's'}`}
            </p>
            {#if !uploadMappingReadiness.ready}
                <ul class="mt-1 list-disc space-y-0.5 pl-4">
                    {#each uploadMappingReadiness.issues as issue}
                        <li>{issue}</li>
                    {/each}
                </ul>
            {/if}
        </div>
    {:else}
        <div class="space-y-1.5" inert={readOnly}>
            <span
                class="text-muted-foreground text-[10px] font-bold tracking-wider uppercase"
                >Add recognition block</span
            >
            <div class="grid grid-cols-2 gap-2">
                <button
                    type="button"
                    onclick={addSingleQuestionBlock}
                    class="border-border bg-card/90 focus-visible:ring-ring flex items-center gap-2.5 rounded-xl border p-2 text-left transition hover:border-emerald-500/60 hover:bg-emerald-500/5 focus-visible:ring-2 focus-visible:outline-none"
                >
                    <div
                        class="bg-muted text-foreground flex size-7 shrink-0 items-center justify-center rounded-lg"
                    >
                        <Plus class="size-3.5" />
                    </div>
                    <div class="min-w-0">
                        <span
                            class="text-foreground block text-[11px] leading-tight font-bold"
                            >Single question</span
                        >
                        <span
                            class="text-muted-foreground block truncate text-[9.5px]"
                            >1 item callout</span
                        >
                    </div>
                </button>
                <button
                    type="button"
                    onclick={addSection}
                    class="border-border bg-card/90 focus-visible:ring-ring flex items-center gap-2.5 rounded-xl border p-2 text-left transition hover:border-emerald-500/60 hover:bg-emerald-500/5 focus-visible:ring-2 focus-visible:outline-none"
                >
                    <div
                        class="bg-primary text-primary-foreground flex size-7 shrink-0 items-center justify-center rounded-lg"
                    >
                        <Scan class="size-3.5" />
                    </div>
                    <div class="min-w-0">
                        <span
                            class="text-foreground block text-[11px] leading-tight font-bold"
                            >Question range</span
                        >
                        <span
                            class="text-muted-foreground block truncate text-[9.5px]"
                            >Consecutive grid</span
                        >
                    </div>
                </button>
            </div>
        </div>
    {/if}

    {#if previewRunning}
        <div
            role="status"
            aria-live="polite"
            class="flex items-center gap-2 rounded-lg border border-blue-500/25 bg-blue-500/10 px-2.5 py-2 text-xs text-blue-900 dark:text-blue-200"
        >
            <div class="size-2 animate-pulse rounded-full bg-blue-500"></div>
            <span>Verifying native OMR geometry…</span>
        </div>
    {:else if previewError}
        {@const errorDetail =
            errorText(previewError) ||
            'The answer-sheet print preview could not be generated.'}
        {@const errorCode =
            typeof previewError === 'object' &&
            typeof previewError?.code === 'string'
                ? previewError.code
                : ''}
        <div
            role="alert"
            aria-live="assertive"
            class="space-y-1 rounded-lg border border-rose-500/25 bg-rose-500/10 p-2.5 text-xs text-rose-900 dark:text-rose-200"
        >
            <div class="flex items-center gap-1.5 font-semibold">
                <XCircle
                    class="size-3.5 shrink-0 text-rose-600 dark:text-rose-400"
                />
                <span>Recognition mapping needs attention: {errorDetail}</span>
            </div>
            {#if errorCode && errorCode !== errorDetail}
                <details
                    class="mt-1 text-[10px] text-rose-700/90 dark:text-rose-400/90"
                >
                    <summary
                        class="inline-block cursor-pointer font-mono hover:underline"
                        >Technical details</summary
                    >
                    <div class="mt-0.5 font-mono text-[10px]">{errorCode}</div>
                </details>
            {/if}
        </div>
    {:else if mappingErrors.length > 0}
        <div
            class="space-y-1.5 rounded-lg border border-rose-500/25 bg-rose-500/10 p-2.5 text-[11px] text-rose-900 dark:text-rose-200"
            role="alert"
            aria-live="assertive"
        >
            <div class="flex items-center gap-1.5 font-bold">
                <XCircle
                    class="size-3.5 shrink-0 text-rose-600 dark:text-rose-400"
                />
                <span>Recognition mapping needs attention</span>
            </div>
            <ul class="space-y-0.5 pl-4">
                {#each mappingErrors as issue}
                    <li class="list-disc">
                        {issue.block &&
                        !String(issue.message).includes(issue.block)
                            ? `${issue.block}: `
                            : ''}{issue.message}
                    </li>
                {/each}
            </ul>
            <p class="text-[10px] opacity-80">
                Resolve this issue before continuing.
            </p>
        </div>

    {/if}

    {#if canonicalPreview}
        <div class="space-y-1">
            <div
                class="border-border/80 bg-muted/60 grid grid-cols-3 rounded-xl border p-1"
                aria-label="Preview surface"
            >
                {#each [['canonical', 'Print Sheet', Boolean(canonicalPreview.artifact?.canonical_url)], ['aligned', 'Aligned Check', Boolean(canonicalPreview.aligned_url)], ['overlay', 'Recognition Overlay', Boolean(canonicalPreview.overlay_url)]] as [surfaceKey, surfaceLabel, isAvailable]}
                    <button
                        type="button"
                        onclick={() => {
                            if (isAvailable) previewSurface = surfaceKey;
                        }}
                        disabled={!isAvailable}
                        class={`rounded-lg px-2 py-1.5 text-[10px] font-semibold transition ${
                            !isAvailable
                                ? 'text-muted-foreground cursor-not-allowed opacity-40'
                                : previewSurface === surfaceKey
                                  ? 'bg-card text-foreground shadow-xs'
                                  : 'text-muted-foreground hover:text-foreground'
                        }`}>{surfaceLabel}</button
                    >
                {/each}
            </div>
            {#if !canonicalPreview.aligned_url || !canonicalPreview.overlay_url}
                <p class="text-muted-foreground px-0.5 text-[10px]">
                    Aligned and overlay diagnostics are unavailable for this preview.
                </p>
            {/if}
        </div>
    {/if}

    {#if showRegistrationControls}
        <details
            class="group border-border bg-card rounded-xl border transition-all"
        >
            <summary
                class="hover:bg-muted/40 flex cursor-pointer list-none items-center justify-between gap-3 rounded-xl px-3 py-2.5 transition"
            >
                <div class="min-w-0">
                    <span class="text-foreground block text-[11px] font-bold"
                        >Page registration</span
                    >
                    <span
                        class="text-muted-foreground block truncate text-[10px]"
                    >
                        {selectedProfile
                            ? `${selectedProfile.code} v${selectedProfile.version}`
                            : 'Select the marker detector'}
                    </span>
                </div>
                <span
                    class={`shrink-0 rounded-full px-2 py-0.5 text-[9px] font-semibold ${
                        selectedProfile?.status === 'qualified'
                            ? 'bg-emerald-500/10 text-emerald-700 dark:text-emerald-400'
                            : 'bg-amber-500/10 text-amber-700 dark:text-amber-400'
                    }`}
                >
                    {selectedProfile?.status === 'qualified'
                        ? 'Qualified'
                        : 'Preview only'}
                </span>
            </summary>
            <div class="border-border space-y-2 border-t px-3 py-2.5">
                <p class="text-muted-foreground text-[10px] leading-relaxed">
                    Locates corner markers and deskews paper during recognition.
                </p>
                {#if markerProfiles.length > 1}
                    <select
                        disabled={readOnly}
                        value={selectedMarkerProfileId ?? ''}
                        onchange={(event) =>
                            onMarkerProfileChange?.(
                                Number(event.currentTarget.value),
                            )}
                        class="border-input bg-background text-foreground h-8 w-full rounded-md border px-2 text-xs"
                    >
                        <option value="" disabled
                            >Select registration profile</option
                        >
                        {#each markerProfiles as profile}
                            <option value={profile.id}
                                >{profile.code} v{profile.version} · {profile.status}</option
                            >
                        {/each}
                    </select>
                {/if}
                {#if selectedProfile?.status !== 'qualified'}
                    <p class="text-[10px] text-amber-700 dark:text-amber-400">
                        Preview only — production compile requires qualified
                        calibration.
                    </p>
                {/if}
            </div>
        </details>
    {/if}

    <!-- Recognition-guide explanation -->
    <div
        class="bg-muted/40 text-muted-foreground border-border/50 flex items-center gap-2 rounded-lg border px-2.5 py-1.5"
    >
        <Scan
            class="size-3.5 shrink-0 text-emerald-600 dark:text-emerald-400"
        />
        <span class="text-foreground text-[11px] font-medium"
            >Recognition guides</span
        >
        <StudioTip
            position="top"
            text="Green rings show exact read coordinates over printed bubbles; they do not alter printed sheets."
        />
    </div>

    {#if !isUploadedSource}
    <!-- Section Select Tabs -->
    <div class="space-y-1" inert={readOnly}>
        <div
            class="text-muted-foreground flex items-center justify-between px-0.5 text-[10px] font-bold tracking-wider uppercase"
        >
            <span>Sections</span>
            <span
                class="text-muted-foreground text-[9.5px] font-normal normal-case"
                >Select to position & tune</span
            >
        </div>
        <div
            class="no-scrollbar flex items-center gap-1.5 overflow-x-auto pt-0.5 pb-1"
            role="tablist"
        >
            {#each sections as sec, i}
                <button
                    type="button"
                    role="tab"
                    aria-selected={activeSectionIndex === i}
                    onclick={() => {
                        activeSectionIndex = i;
                        if (selectedSectionIndices.length === 0) {
                            selectedSectionIndices = [activeSectionIndex];
                        }
                    }}
                    class={`flex shrink-0 items-center gap-1.5 rounded-lg px-2.5 py-1 text-xs font-semibold transition ${
                        activeSectionIndex === i
                            ? 'bg-primary text-primary-foreground shadow-xs'
                            : 'bg-muted/80 text-muted-foreground hover:bg-muted hover:text-foreground'
                    }`}
                >
                    {#if sec.type === 'roll'}
                        <span
                            >{sec.id} ({Number(sec.questionsCount) || 7} Digits)</span
                        >
                    {:else}
                        <span
                            >{sec.id} (Q{sec.startQ}&ndash;{Number(sec.startQ) +
                                Number(sec.questionsCount) -
                                1})</span
                        >
                    {/if}
                    {#if sec.isLocked}
                        <Lock class="size-2.5 text-amber-400" />
                    {/if}
                </button>
            {/each}
        </div>
    </div>

    <!-- Active Section Detail Editor -->
    {#if activeSection}
        <div
            class="bg-card border-border space-y-3 rounded-xl border p-3.5 text-xs shadow-xs"
            inert={readOnly}
        >
            <div
                class="border-border/70 flex items-center justify-between border-b pb-2"
            >
                <span class="text-foreground text-sm font-bold tracking-tight"
                    >{activeSection.name}</span
                >
                <div class="flex items-center gap-1.5">
                    <!-- Per-Section Lock Toggle -->
                    <button
                        type="button"
                        onclick={handleLockToggle}
                        class={`flex items-center gap-1 rounded-md border px-2 py-1 text-[10px] font-semibold transition ${
                            activeSection.isLocked
                                ? 'border-amber-500/40 bg-amber-500/10 text-amber-700 dark:text-amber-400'
                                : 'bg-background text-foreground border-border hover:bg-muted'
                        }`}
                        title={activeSection.isLocked
                            ? 'Unlock recognition guide for positioning'
                            : 'Lock recognition guide in place'}
                    >
                        {#if activeSection.isLocked}
                            <Lock
                                class="size-2.5 text-amber-700 dark:text-amber-400"
                            />
                            <span>Locked</span>
                        {:else}
                            <Unlock class="text-muted-foreground size-2.5" />
                            <span>Unlocked</span>
                        {/if}
                    </button>

                    {#if sections.length > 1}
                        <button
                            type="button"
                            onclick={() => removeSection(activeSectionIndex)}
                            class="text-muted-foreground rounded-md p-1 transition hover:bg-rose-500/10 hover:text-rose-600"
                            title="Delete section"
                        >
                            <Trash2 class="size-3.5" />
                        </button>
                    {/if}
                </div>
            </div>

            <!-- Question Count / Digits Count & Start Number -->
            <div class="grid grid-cols-2 gap-2.5">
                <label class="block space-y-1">
                    <span
                        class="text-muted-foreground block text-[11px] font-medium"
                    >
                        {activeSection.type === 'roll'
                            ? 'Digits Count'
                            : 'Question Count'}
                    </span>
                    <input
                        type="number"
                        min="1"
                        max="100"
                        bind:value={activeSection.questionsCount}
                        oninput={handleInput}
                        class="bg-background border-input text-foreground focus:ring-ring w-full rounded-lg border px-2.5 py-1.5 text-xs font-semibold focus:ring-2 focus:outline-none"
                    />
                </label>
                <label class="block space-y-1">
                    <span
                        class="text-muted-foreground block text-[11px] font-medium"
                    >
                        {activeSection.type === 'roll'
                            ? 'Column Prefix'
                            : 'Start Question #'}
                    </span>
                    {#if activeSection.type === 'roll'}
                        <input
                            type="text"
                            bind:value={activeSection.id}
                            oninput={handleInput}
                            class="bg-background border-input text-foreground focus:ring-ring w-full rounded-lg border px-2.5 py-1.5 text-xs font-semibold focus:ring-2 focus:outline-none"
                        />
                    {:else}
                        <input
                            type="number"
                            min="1"
                            max="500"
                            value={activeSection.startQ}
                            oninput={(e) => handleItemNumberChange(e)}
                            class="bg-background border-input text-foreground focus:ring-ring w-full rounded-lg border px-2.5 py-1.5 text-xs font-semibold focus:ring-2 focus:outline-none"
                        />
                    {/if}
                </label>
            </div>

            {#if activeSection.type !== 'roll' && subtests.length > 0}
                <label class="block space-y-1">
                    <span
                        class="text-muted-foreground block text-[11px] font-medium"
                    >
                        Test Section / Subtest
                    </span>
                    <select
                        data-omr-section-subtest-select="true"
                        aria-label="Test Section / Subtest"
                        value={subtestCodeFor(activeSection)}
                        onchange={handleSubtestChange}
                        class="bg-background border-input text-foreground focus:ring-ring w-full rounded-lg border px-2.5 py-1.5 text-xs focus:ring-2 focus:outline-none"
                    >
                        <option value="">Select subtest before compile</option>
                        {#each subtests as subtest}
                            <option value={subtest.code}
                                >{subtest.name} ({subtest.code})</option
                            >
                        {/each}
                    </select>
                </label>
            {:else if activeSection.type !== 'roll' && aptitudeAreas.length > 0}
                <label class="block space-y-1">
                    <span
                        class="text-muted-foreground block text-[11px] font-medium"
                    >
                        System aptitude area
                    </span>
                    <select
                        aria-label="System aptitude area"
                        value={activeSection.aptitudeAreaCode ?? ''}
                        onchange={(event) => handleAreaChange(event)}
                        class="bg-background border-input text-foreground focus:ring-ring w-full rounded-lg border px-2.5 py-1.5 text-xs focus:ring-2 focus:outline-none"
                    >
                        <option value="">Select area before compile</option>
                        {#each aptitudeAreas as area}
                            <option value={area.code}>{area.name}</option>
                        {/each}
                    </select>
                </label>
            {/if}
            <!-- Option Type -->
            <label class="block space-y-1">
                <span
                    class="text-muted-foreground block text-[11px] font-medium"
                    >Option Format</span
                >
                <select
                    bind:value={activeSection.type}
                    onchange={handleInput}
                    class="bg-background border-input text-foreground focus:ring-ring w-full rounded-lg border px-2.5 py-1.5 text-xs focus:ring-2 focus:outline-none"
                >
                    <option value="mcq"
                        >Multiple Choice (4 Options: A, B, C, D)</option
                    >
                    <option
                        value="mcq"
                        selected={activeSection.optionsCount === 5}
                        >Multiple Choice (5 Options: A, B, C, D, E)</option
                    >
                    <option value="tf">True / False (2 Options: T, F)</option>
                    <option value="likert"
                        >Likert Scale (5 Options: 1, 2, 3, 4, 5)</option
                    >
                    <option value="roll"
                        >Examinee / Student ID Matrix (0–9 Digits)</option
                    >
                    <option value="custom">Custom Values</option>
                </select>
            </label>

            <!-- Pitch Spacing Sliders -->
            <div class="border-border/70 space-y-2.5 border-t pt-2.5">
                <div class="space-y-1">
                    <div class="flex items-center justify-between text-[11px]">
                        <span
                            class="text-muted-foreground flex items-center gap-1 font-medium"
                        >
                            Bubble Spacing (X)
                            <StudioTip
                                text="Horizontal center-to-center distance between option bubbles (A ↔ B ↔ C ↔ D)."
                            />
                        </span>
                        <span
                            class="bg-muted/80 border-border text-foreground rounded border px-1.5 py-0.5 font-mono text-[10px] font-semibold"
                            >{activeSection.bubblePitchMm} mm</span
                        >
                    </div>
                    <input
                        type="range"
                        min={minimumRecognitionPitchMm(
                            activeSection.bubbleDiameterMm,
                            minBubbleDiameter,
                        )}
                        step="0.1"
                        value={activeSection.bubblePitchMm}
                        oninput={handleBubblePitchInput}
                        class="accent-primary bg-muted h-1.5 w-full cursor-pointer rounded-lg"
                    />
                </div>

                <!-- Row Spacing Slider (Only shown if questionsCount > 1) -->
                {#if (Number(activeSection.questionsCount) || 1) > 1}
                    <div class="space-y-1">
                        <div
                            class="flex items-center justify-between text-[11px]"
                        >
                            <span
                                class="text-muted-foreground flex items-center gap-1 font-medium"
                            >
                                Row Spacing (Y)
                                <StudioTip
                                    text="Vertical center-to-center distance between question rows (Q1 ↕ Q2)."
                                />
                            </span>
                            <span
                                class="bg-muted/80 border-border text-foreground rounded border px-1.5 py-0.5 font-mono text-[10px] font-semibold"
                                >{activeSection.rowPitchMm} mm</span
                            >
                        </div>
                        <input
                            type="range"
                            min={minimumRecognitionPitchMm(
                                activeSection.bubbleDiameterMm,
                                minBubbleDiameter,
                            )}
                            step="0.2"
                            bind:value={activeSection.rowPitchMm}
                            oninput={handleInput}
                            class="accent-primary bg-muted h-1.5 w-full cursor-pointer rounded-lg"
                        />
                    </div>
                {/if}
            </div>
        </div>
    {/if}
    {/if}

    <!-- Bottom Navigation Buttons -->
    <div
        class="border-border flex items-center justify-between gap-2.5 border-t pt-3"
    >
        <button
            type="button"
            onclick={onBack}
            class="border-border hover:bg-muted text-foreground focus-visible:ring-ring flex items-center gap-1.5 rounded-xl border px-3 py-2 text-xs font-semibold transition focus-visible:ring-2 focus-visible:outline-none"
        >
            <ChevronLeft class="size-3.5" />
            <span>Back</span>
        </button>
        <button
            type="button"
            onclick={onNext}
            disabled={previewRunning || transitionRunning}
            class="bg-primary hover:bg-primary/90 text-primary-foreground focus-visible:ring-ring flex items-center gap-1.5 rounded-xl px-4 py-2 text-xs font-semibold shadow-xs transition focus-visible:ring-2 focus-visible:outline-none disabled:opacity-50"
        >
            {#if previewRunning}
                <span>Checking mapped layout…</span>
            {:else if transitionRunning}
                <span>Preparing verification…</span>
            {:else}
                <span>Continue to Verification</span>
                <ChevronRight class="size-3.5" />
            {/if}
        </button>
    </div>
</div>
