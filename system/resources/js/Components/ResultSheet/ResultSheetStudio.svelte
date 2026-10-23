<script>
    import { tick, untrack } from 'svelte';
    import {
        Copy,
        Lock,
        Unlock,
        Trash2,
        CopyPlus,
        Plus,
        Minus,
        Maximize2,
        FileText,
        AlertTriangle,
        Table2,
        UserRound,
        CalendarClock,
        Brackets,
        BadgePercent,
        Sparkles,
        PenLine,
        StickyNote,
        AlignLeft,
        Image as ImageIcon,
        ChevronLeft,
        SlidersHorizontal,
        Search,
        Layers as LayersIcon,
        HelpCircle,
        MousePointer2,
        Hand,
        AlignHorizontalJustifyStart,
        AlignHorizontalJustifyCenter,
        AlignHorizontalJustifyEnd,
        AlignVerticalJustifyStart,
        AlignVerticalJustifyCenter,
        AlignVerticalJustifyEnd,
        AlignHorizontalDistributeCenter,
        LayoutList,
        Grid3X3,
        Type,
        Minus as MinusIcon,
        QrCode,
        UserCheck,
        Calendar,
    } from 'lucide-svelte';
    import {
        normalizeComponentProps,
        componentStyle,
        textPartOptions,
    } from '@/lib/resultSheetStyles';
    import {
        createStudioSelection,
        createStudioHistory,
        lockAxis,
        getBoundingBox,
        alignRects,
        distributeRects,
        calculateSnapGuides,
        normalizeRect,
        bringToFront,
        sendToBack,
        moveUp,
        moveDown,
        toggleLocked,
        filterUnlocked,
        isInputFocused,
        isMod,
        calculateCursorZoom,
        clampZoom,
        calculateWheelZoomFactor,
        fitViewport,
        fitViewportForReadableWidth,
    } from '@/lib/studio';
    import NumericControl from '@/Components/studio/NumericControl.svelte';
    import ResultSheetComponentPreview from './ResultSheetComponentPreview.svelte';
    import ResultSheetTypographyControls from './ResultSheetTypographyControls.svelte';
    import ResultSheetAppearanceControls from './ResultSheetAppearanceControls.svelte';
    import ResultSheetLayersPanel from './ResultSheetLayersPanel.svelte';
    import ResultSheetShortcutHelp from './ResultSheetShortcutHelp.svelte';

    let {
        builderDocument = null,
        builderDefaults = {},
        builderCatalog = {},
        placeholderGroups = {},
        legacyContent = '',
        components: legacyComponents = null,
        activeComponentId = null,
        activeComponentIds = [],
        onChange = () => {},
        onConvertLegacy = () => {},
        onOpenPlaceholders = () => {},
        onBinding = () => {},
        onSelectionChange = () => {},
        onViewportChange = () => {},
        fullBleed = false,
        adapterActions = null,
        builderCapabilities = {},
        sampleContext = {},
        readOnly = false,
        allowNoticeDocumentImages = false,
        noticeDocumentImageAssets = [],
        onNoticeDocumentImageUpload = null,
    } = $props();

    let noticeImageUploading = $state(false);
    let noticeImageError = $state('');
    async function uploadInspectorImage(event) {
        const target = (event.currentTarget ?? event.target);
        const file = target?.files?.[0];
        if (!file || !primaryComponent || !onNoticeDocumentImageUpload || readOnly || primaryIsLocked) return;
        const targetId = primaryComponent.id;
        noticeImageUploading = true;
        noticeImageError = '';
        try {
            const asset = await onNoticeDocumentImageUpload(file, targetId);
            if (asset) {
                const comp = components.find((component) => component.id === targetId);
                if (comp && !comp.locked) {
                    updateComponent(targetId, { props: { ...comp.props, asset_id: asset.asset_id, src: asset.src } }, 'Upload verified document image');
                }
            } else {
                noticeImageError = 'Upload was not confirmed. Retry the document operation or choose an existing verified image.';
            }
        } catch (error) {
            noticeImageError = error instanceof Error ? error.message : 'Image upload failed. The previous image is retained.';
        } finally {
            noticeImageUploading = false;
            if (target && 'value' in target) {
                try { target.value = ''; } catch {}
            }
        }
    }

    const legacyMode = legacyComponents !== null;

    function normalizePage(page = {}) {
        const paperSize = ['a4', 'legal', 'letter'].includes(page.paper_size)
            ? page.paper_size
            : 'a4';
        const orientation = ['portrait', 'landscape'].includes(page.orientation)
            ? page.orientation
            : 'portrait';
        const base =
            paperSize === 'legal'
                ? { width_mm: 216, height_mm: 356 }
                : paperSize === 'letter'
                  ? { width_mm: 216, height_mm: 279 }
                  : { width_mm: 210, height_mm: 297 };
        const dimensions =
            orientation === 'landscape'
                ? { width_mm: base.height_mm, height_mm: base.width_mm }
                : base;
        return {
            paper_size: paperSize,
            orientation,
            ...dimensions,
            margin_mm: Math.min(30, Math.max(5, Number(page.margin_mm ?? 12))),
        };
    }

    function clone(value) {
        return value ? JSON.parse(JSON.stringify(value)) : {};
    }

    let localDocument = $state(null);

    function normalizeDocument(value) {
        if (Array.isArray(value)) {
            // Defensive fallback against array commits: preserve current document context
            const fallback =
                [localDocument, builderDocument, builderDefaults].find(
                    (candidate) =>
                        candidate &&
                        typeof candidate === 'object' &&
                        !Array.isArray(candidate),
                ) ?? {};
            const base = clone(fallback);
            base.components = value.map((component) => ({
                ...component,
                props: normalizeComponentProps(
                    component.type,
                    component.props ?? {},
                ),
            }));
            return base;
        }
        const document = clone(value);
        document.components = Array.isArray(document?.components)
            ? document.components.map((component) => ({
                  ...component,
                  props: normalizeComponentProps(
                      component.type,
                      component.props ?? {},
                  ),
              }))
            : [];
        return document;
    }
    // Canonical Document State
    // Capture whether the component mounted before the builder document existed.
    // A later conversion must replace the defaults that initialized local state.
    let initiallyMountedWithoutBuilderDocument = false;
    localDocument = normalizeDocument(
        builderDocument ??
            (legacyMode ? { components: legacyComponents } : builderDefaults),
    );
    let lastEmittedDocument = '';
    let lastObservedIncomingDocument = '';

    // Modular Studio History (100 steps max, labeled transactions with coalescing)
    let historyEngine = createStudioHistory(
        normalizeDocument(localDocument),
        100,
    );
    let historyStatus = $state(historyEngine.status());

    // Modular Studio Selection
    const selectionEngine = createStudioSelection();
    let selectedIds = $state(
        new Set(
            Array.isArray(activeComponentIds) && activeComponentIds.length > 0
                ? activeComponentIds
                : activeComponentId
                  ? [activeComponentId]
                  : [],
        ),
    );
    let primaryId = $state(
        activeComponentId ?? activeComponentIds?.at?.(-1) ?? null,
    );
    let initialSelectionInitialized = false;

    function componentNode(id) {
        if (!id || !pageNode?.querySelectorAll) return null;
        return (
            [
                ...pageNode.querySelectorAll('[data-result-sheet-component]'),
            ].find(
                (candidate) =>
                    candidate.getAttribute('data-result-sheet-component') ===
                    String(id),
            ) ?? null
        );
    }

    function selectionFocusTarget(preferred = null) {
        const preferredComponent = preferred?.closest?.(
            '[data-result-sheet-component]',
        );
        if (preferredComponent) return preferredComponent;
        if (preferred === canvasNode) return canvasNode;

        const selectedNode = componentNode(
            primaryId ?? selectedIds.values().next().value,
        );
        return selectedNode ?? canvasNode;
    }

    function rememberContextualSelectionFocus(preferred = null) {
        if (!fullBleed) return;

        const selectedId =
            primaryId ?? selectedIds.values().next().value ?? null;
        const target = selectionFocusTarget(preferred);
        contextualFocusReturnNode = target;
        contextualFocusReturnId =
            target?.getAttribute?.('data-result-sheet-component') ?? selectedId;
    }

    function syncSelectionState(
        source = 'canvas',
        preferredFocusTarget = null,
    ) {
        selectedIds = selectionEngine.getSelectedIds();
        primaryId = selectionEngine.getPrimaryId();

        if (selectedIds.size > 0) {
            if (source !== 'layers') inspectorOpenerNode = null;
            const hasExplicitPanelOpener =
                inspectorOpenerNode &&
                inspectorOpenerNode.isConnected !== false;
            if (source !== 'layers' || !hasExplicitPanelOpener) {
                rememberContextualSelectionFocus(preferredFocusTarget);
            }
            if (
                fullBleed &&
                belowDesktop &&
                source === 'canvas' &&
                !mobileFocusMode
            ) {
                mobileFocusViewportSnapshot = { zoomMode, zoom, panX, panY };
                mobileInspectorSnap = 'half';
                mobileFocusMode = true;
                void focusSelectedComponent();
                void tick().then(() => inspectorPanelNode?.focus());
            }
            inspectorContextOpen = true;
            sidebarView = source === 'layers' ? 'layers' : 'inspector';
            activeMobilePanel = source === 'layers' ? 'layers' : 'inspector';
            if (belowDesktop) paletteState = 'closed';
            emitSelectionState();
            return;
        }

        if (mobileFocusMode) {
            exitMobileFocus();
            return;
        }

        if (
            belowDesktop &&
            activeMobilePanel === 'layers' &&
            components.length > 0
        ) {
            inspectorContextOpen = true;
            sidebarView = 'layers';
            emitSelectionState();
            return;
        }

        inspectorContextOpen = false;
        if (belowDesktop && activeMobilePanel !== 'add')
            activeMobilePanel = null;
        contextualFocusReturnNode = null;
        contextualFocusReturnId = null;
        inspectorOpenerNode = null;
        emitSelectionState();
    }
    // UI / Viewport & Tool State
    let toolbarNode = $state(null);
    let sidebarView = $state('inspector'); // 'inspector' | 'layers'
    let canvasTool = $state('select'); // 'select' | 'pan'
    let isSpacePressed = $state(false);
    let isCanvasHovered = $state(false);
    let zoom = $state(0.72);
    let zoomMode = $state('fit');
    let panX = $state(0);
    let panY = $state(0);
    let lastEmittedViewportSignature = null;
    let panState = $state(null);
    let canvasWidth = $state(0);
    let canvasHeight = $state(0);
    let canvasNode = $state(null);
    let pageFrameNode = $state(null);
    let pageNode = $state(null);
    let mobileSurfaceNode = $state(null);
    let mobileSurfaceHeight = $state(0);
    let fitInvalidation = $state(0);
    let wheelFrameModel = null;
    let wheelSequence = 0;
    let selectedTextPart = $state('body');
    let recentKeys = $state([]);
    let activeMobilePanel = $state(null); // null | 'add' | 'inspector' | 'layers'
    let paletteQuery = $state('');
    let paletteState = $state(
        fullBleed && typeof window !== 'undefined' && window.innerWidth < 1024
            ? 'closed'
            : 'expanded',
    ); // mobile uses the command dock; desktop starts with labeled components
    let shortcutHelpOpen = $state(false);
    let liveAnnouncement = $state('');
    let inspectorContextOpen = $state(
        typeof window === 'undefined' || window.innerWidth >= 1280,
    );
    let mobileFocusMode = $state(false);
    let mobileInspectorSnap = $state('half');
    let mobileFocusViewportSnapshot = $state(null);
    let preserveViewportOnNextLayout = false;
    let viewportWidth = $state(
        typeof window === 'undefined' ? 1024 : window.innerWidth,
    );
    let canvasSafeTop = $state(0);
    let canvasSafeBottom = $state(0);
    let canvasSafeRight = $state(0);
    let lastCanvasAssistantMeasurementKey = '';
    let palettePanelNode = $state(null);
    let inspectorPanelNode = $state(null);
    let inspectorOpenerNode = $state(null);
    let mobilePanelOpenerNode = $state(null);
    let contextualFocusReturnNode = $state(null);
    let contextualFocusReturnId = $state(null);
    const paletteRailOpen = $derived(paletteState === 'rail');
    const paletteExpanded = $derived(paletteState === 'expanded');
    const belowDesktop = $derived(viewportWidth < 1280);
    const mobileSurfaceOpen = $derived(
        fullBleed && belowDesktop && activeMobilePanel !== null,
    );
    const mobileSurfaceInset = $derived(
        mobileSurfaceOpen ? mobileSurfaceHeight + 12 : 0,
    );
    const canvasSafeLeft = $derived(0);
    const inspectorPanelOpen = $derived(
        activeMobilePanel === 'inspector' || activeMobilePanel === 'layers',
    );

    function rememberOpener(event) {
        const opener = event?.currentTarget;
        return opener && typeof opener.focus === 'function' ? opener : null;
    }

    async function focusPanel() {
        await tick();
        const node = inspectorPanelNode;
        const target = node?.querySelector?.('[data-result-sheet-panel-close]');
        const focusTarget = target ?? node;
        if (focusTarget && typeof focusTarget.focus === 'function') {
            focusTarget.focus();
        }
    }

    function rememberInspectorOpener(event) {
        inspectorOpenerNode = rememberOpener(event);
    }

    async function restorePanelFocus(
        target = null,
        fallback = null,
        selectionId = null,
    ) {
        await tick();

        const selectedTarget = selectionId ? componentNode(selectionId) : null;
        const candidate = selectedTarget ?? target;
        const focusTarget =
            candidate &&
            candidate.isConnected !== false &&
            typeof candidate.focus === 'function'
                ? candidate
                : fallback &&
                    fallback.isConnected !== false &&
                    typeof fallback.focus === 'function'
                  ? fallback
                  : canvasNode &&
                      canvasNode.isConnected !== false &&
                      typeof canvasNode.focus === 'function'
                    ? canvasNode
                    : null;
        if (focusTarget) {
            focusTarget.focus();
        }
    }
    function selectionLabel() {
        if (selectedCount > 1) return `${selectedCount} components`;
        return primaryComponent ? componentLabel(primaryComponent.type) : '';
    }

    function emitSelectionState() {
        onSelectionChange({
            selectedIds: Array.from(selectedIds),
            primaryId,
            label: selectionLabel(),
            focused: mobileFocusMode,
        });
    }

    function exitMobileFocus() {
        if (!mobileFocusMode && !mobileFocusViewportSnapshot) return;

        const restoreTarget = contextualFocusReturnNode;
        const restoreSelectionId = contextualFocusReturnId;
        const snapshot = mobileFocusViewportSnapshot;
        mobileFocusMode = false;
        mobileInspectorSnap = 'half';
        activeMobilePanel = null;
        inspectorContextOpen = false;
        inspectorOpenerNode = null;
        mobilePanelOpenerNode = null;
        contextualFocusReturnNode = null;
        contextualFocusReturnId = null;

        if (snapshot) {
            preserveViewportOnNextLayout = true;
            fitRequestSequence += 1;
            wheelSequence += 1;
            wheelFrameModel = null;
            zoomMode = snapshot.zoomMode;
            zoom = snapshot.zoom;
            panX = snapshot.panX;
            panY = snapshot.panY;
        }
        mobileFocusViewportSnapshot = null;
        void restorePanelFocus(restoreTarget, canvasNode, restoreSelectionId);
        emitSelectionState();
        announce('Component focus closed');
    }

    function setMobileInspectorSnap(nextSnap) {
        if (
            !['peek', 'half', 'full'].includes(nextSnap) ||
            nextSnap === mobileInspectorSnap
        )
            return;
        mobileInspectorSnap = nextSnap;
        void tick().then(() => focusSelectedComponent());
    }

    async function focusSelectedComponent() {
        await tick();
        if (!mobileFocusMode || !belowDesktop || !primaryComponent) return;

        const metrics = canvasContentMetrics();
        const availableWidth = Math.max(
            1,
            Number(metrics?.width ?? canvasWidth) -
                canvasSafeLeft -
                canvasSafeRight,
        );
        const availableHeight = Math.max(
            1,
            Number(metrics?.height ?? canvasHeight) -
                canvasSafeTop -
                canvasReservedBottom,
        );
        if (!availableWidth || !availableHeight) return;

        const componentWidth = Math.max(
            1,
            Number(primaryComponent.w) * MM_TO_PX,
        );
        const componentHeight = Math.max(
            1,
            Number(primaryComponent.h) * MM_TO_PX,
        );
        const nextZoom = fitViewport({
            viewportWidth: availableWidth,
            viewportHeight: availableHeight,
            pageWidthPx: componentWidth,
            pageHeightPx: componentHeight,
            minZoom: MIN_ZOOM,
            maxZoom: MAX_ZOOM,
            gutter: 48,
        });
        const frameWidth = pagePixelWidth * nextZoom;
        const frameLeft =
            (metrics?.left ?? 0) +
            canvasSafeLeft +
            Math.max(0, (availableWidth - frameWidth) / 2);
        const frameTop = (metrics?.top ?? 0) + canvasSafeTop;
        const componentCenterX =
            (Number(page.margin_mm) +
                Number(primaryComponent.x) +
                Number(primaryComponent.w) / 2) *
            MM_TO_PX *
            nextZoom;
        const componentCenterY =
            (Number(page.margin_mm) +
                Number(primaryComponent.y) +
                Number(primaryComponent.h) / 2) *
            MM_TO_PX *
            nextZoom;
        const viewportCenterX =
            (metrics?.left ?? 0) + canvasSafeLeft + availableWidth / 2;
        const viewportCenterY =
            (metrics?.top ?? 0) + canvasSafeTop + availableHeight / 2;

        zoomMode = 'manual';
        zoom = nextZoom;
        panX = viewportCenterX - (frameLeft + componentCenterX);
        panY = viewportCenterY - (frameTop + componentCenterY);
        fitInvalidation += 1;
    }

    function togglePalette() {
        if (belowDesktop) {
            toggleMobilePanel('add');
            return;
        }

        paletteState = paletteState === 'expanded' ? 'rail' : 'expanded';
        announce(
            paletteState === 'expanded'
                ? 'Add components expanded'
                : 'Add components collapsed to rail',
        );
    }

    function closeMobileAddPanel() {
        if (activeMobilePanel !== 'add') return;
        const restoreTarget = mobilePanelOpenerNode;
        paletteState = 'closed';
        activeMobilePanel = null;
        mobilePanelOpenerNode = null;
        inspectorContextOpen = false;
        void restorePanelFocus(restoreTarget, canvasNode);
        announce('Add components closed');
    }

    function toggleMobilePanel(panel, event = null) {
        if (!belowDesktop) {
            if (panel === 'add') {
                togglePalette();
            } else {
                toggleInspectorView(panel, event);
            }
            return;
        }

        if (panel === 'inspector' && selectedCount === 0) return;
        if (panel === 'layers' && components.length === 0) return;

        if (activeMobilePanel === panel) {
            if (panel === 'add') {
                closeMobileAddPanel();
            } else {
                closeInspectorPanel();
            }
            return;
        }

        if (panel === 'add') {
            mobilePanelOpenerNode = rememberOpener(event);
            inspectorOpenerNode = null;
            inspectorContextOpen = false;
            paletteState = 'expanded';
            activeMobilePanel = 'add';
            announce('Add components opened');
            return;
        }

        paletteState = 'closed';
        mobilePanelOpenerNode = null;
        rememberInspectorOpener(event);
        activeMobilePanel = panel;
        sidebarView = panel;
        inspectorContextOpen = true;
        void focusPanel();
    }

    function closeInspectorPanel() {
        if (mobileFocusMode) {
            exitMobileFocus();
            return;
        }
        if (!inspectorPanelOpen && !inspectorContextOpen) return;

        const hasExplicitPanelOpener =
            inspectorOpenerNode && inspectorOpenerNode.isConnected !== false;
        const restoreTarget = hasExplicitPanelOpener
            ? inspectorOpenerNode
            : contextualFocusReturnNode;
        const restoreFallback = hasExplicitPanelOpener ? null : canvasNode;
        const restoreSelectionId = hasExplicitPanelOpener
            ? null
            : contextualFocusReturnId;

        activeMobilePanel = null;
        inspectorContextOpen = false;
        inspectorOpenerNode = null;
        mobilePanelOpenerNode = null;
        contextualFocusReturnNode = null;
        contextualFocusReturnId = null;
        void restorePanelFocus(
            restoreTarget,
            restoreFallback,
            restoreSelectionId,
        );
    }

    function toggleInspectorView(view, event) {
        if (belowDesktop) {
            toggleMobilePanel(view, event);
            return;
        }

        if (view === 'inspector' && selectedCount === 0) return;
        if (view === 'layers' && components.length === 0) return;

        const panel = view === 'layers' ? 'layers' : 'inspector';
        if (sidebarView === panel && inspectorContextOpen) {
            closeInspectorPanel();
            return;
        }

        rememberInspectorOpener(event);
        sidebarView = panel;
        inspectorContextOpen = true;
        void focusPanel();
    }

    function handleViewportResize() {
        viewportWidth =
            typeof window === 'undefined' ? viewportWidth : window.innerWidth;
    }

    $effect(() => {
        if (typeof window === 'undefined') return;
        window.addEventListener('resize', handleViewportResize);
        return () => window.removeEventListener('resize', handleViewportResize);
    });

    $effect(() => {
        if (belowDesktop && paletteState === 'rail') {
            paletteState = 'closed';
        } else if (!belowDesktop && paletteState === 'closed') {
            paletteState = 'rail';
        }
    });

    $effect(() => {
        const node = mobileSurfaceNode;
        if (typeof window === 'undefined' || !mobileSurfaceOpen || !node) {
            mobileSurfaceHeight = 0;
            return;
        }

        const measure = () => {
            mobileSurfaceHeight = Math.ceil(
                node.getBoundingClientRect?.().height ?? node.offsetHeight ?? 0,
            );
        };
        measure();
        const observer =
            typeof ResizeObserver === 'function'
                ? new ResizeObserver(measure)
                : null;
        observer?.observe(node);
        window.addEventListener('resize', measure);

        return () => {
            observer?.disconnect();
            window.removeEventListener('resize', measure);
        };
    });
    // Interactive Gesture States
    let dragState = $state(null);
    let resizeState = $state(null);
    let marqueeState = $state(null);
    let paletteDragState = $state(null);
    let suppressPaletteClick = $state(false);
    let activeSnapGuides = $state([]);

    const MM_TO_PX = 96 / 25.4;
    const MIN_ZOOM = 0.25;
    const MAX_ZOOM = 3.0;

    const page = $derived(
        normalizePage(localDocument?.page ?? builderDefaults?.page ?? {}),
    );
    const components = $derived(
        Array.isArray(localDocument?.components)
            ? localDocument.components
            : [],
    );
    const selectedCount = $derived(selectedIds.size);

    $effect(() => {
        if (initialSelectionInitialized || components.length === 0) return;

        initialSelectionInitialized = true;
        if (primaryId) return;

        selectionEngine.select(components[0].id);
        syncSelectionState();
    });
    const studioCatalog = $derived(builderCatalog);
    const paletteEntries = $derived(
        Object.entries(studioCatalog).filter(([type, item]) => {
            const query = paletteQuery.trim().toLowerCase();
            if (!query) return true;
            return `${type} ${item?.label ?? ''} ${item?.description ?? ''}`.toLowerCase().includes(query);
        }),
    );
    const paletteGroups = $derived.by(() => {
        const groups = new Map();
        for (const entry of paletteEntries) {
            const category = entry[1]?.category ?? 'Components';
            if (!groups.has(category)) groups.set(category, []);
            groups.get(category).push(entry);
        }
        return [...groups.entries()];
    });
    const paletteTypeCount = $derived(Object.keys(builderCatalog).length);
    const paletteResultCount = $derived(paletteEntries.length);
    const paletteCountLabel = $derived(paletteQuery.trim() ? `${paletteResultCount} of ${paletteTypeCount} types` : `${paletteTypeCount} available types`);
    const inspectorPanelVisible = $derived(
        inspectorContextOpen &&
            (belowDesktop
                ? (activeMobilePanel === 'inspector' && selectedCount > 0) ||
                  (activeMobilePanel === 'layers' && components.length > 0)
                : sidebarView === 'layers'
                  ? components.length > 0
                  : selectedCount > 0),
    );
    // Keep this list ahead of primaryComponent so initial renders always have a
    // defined selection fallback, including an empty builder document.
    const selectedList = $derived(
        components.filter((component) => selectedIds.has(component.id)),
    );
    const primaryComponent = $derived(
        (primaryId ? components.find((c) => c.id === primaryId) : null) ??
            selectedList[0] ??
            null,
    );
    const primaryIsLocked = $derived(Boolean(primaryComponent?.locked));

    const textParts = $derived(
        textPartOptions(primaryComponent?.type ?? 'text'),
    );
    const activeTextPart = $derived(
        textParts.some((part) => part.value === selectedTextPart)
            ? selectedTextPart
            : (textParts[0]?.value ?? 'body'),
    );
    const activeTextStyle = $derived(
        primaryComponent?.props?.text_styles?.[activeTextPart] ??
            primaryComponent?.props?.typography ??
            {},
    );
    const usedKeys = $derived(findBindings(localDocument));
    const diagnostics = $derived(diagnosticList(localDocument));
    const pagePixelWidth = $derived(Number(page.width_mm) * MM_TO_PX);
    const pagePixelHeight = $derived(Number(page.height_mm) * MM_TO_PX);
    const canvasFitWidth = $derived(
        Math.max(0, canvasWidth - canvasSafeLeft - canvasSafeRight),
    );
    const canvasReservedBottom = $derived(
        canvasSafeBottom + mobileSurfaceInset,
    );
    const canvasFitHeight = $derived(
        Math.max(0, canvasHeight - canvasReservedBottom - canvasSafeTop),
    );

    const fitZoom = $derived(
        viewportWidth >= 1024
            ? fitViewportForReadableWidth({
                  viewportWidth: canvasFitWidth,
                  viewportHeight: canvasFitHeight,
                  pageWidthPx: pagePixelWidth,
                  pageHeightPx: pagePixelHeight,
                  minZoom: MIN_ZOOM,
                  maxZoom: MAX_ZOOM,
              })
            : canvasWidth > 0 && canvasHeight > 0
              ? fitViewport({
                    viewportWidth: canvasFitWidth,
                    viewportHeight: canvasFitHeight,
                    pageWidthPx: pagePixelWidth,
                    pageHeightPx: pagePixelHeight,
                    minZoom: MIN_ZOOM,
                    maxZoom: MAX_ZOOM,
                })
              : zoom,
    );
    const effectiveZoom = $derived(zoomMode === 'fit' ? fitZoom : zoom);
    const pageFrameWidth = $derived(pagePixelWidth * effectiveZoom);
    const pageFrameHeight = $derived(pagePixelHeight * effectiveZoom);
    const pageStyle = $derived(
        `width:${page.width_mm}mm;height:${page.height_mm}mm;transform:translate(${panX}px,${panY}px) scale(${effectiveZoom});transform-origin:top left;`,
    );
    const pageFrameStyle = $derived(
        `width:${pageFrameWidth}px;height:${pageFrameHeight}px;margin-left:max(0px, calc((100% - ${canvasSafeLeft}px - ${canvasSafeRight}px - ${pageFrameWidth}px) / 2));`,
    );

    const printableWidth = $derived(
        Math.max(0, Number(page.width_mm) - Number(page.margin_mm) * 2),
    );
    const printableHeight = $derived(
        Math.max(0, Number(page.height_mm) - Number(page.margin_mm) * 2),
    );

    function announce(message) {
        liveAnnouncement = message;
    }
    function resetCanvasSafeInsets() {
        lastCanvasAssistantMeasurementKey = '';
        if (untrack(() => canvasSafeBottom) !== 0) canvasSafeBottom = 0;
        if (untrack(() => canvasSafeRight) !== 0) canvasSafeRight = 0;
    }

    function measureCanvasToolbarInset(metrics = canvasContentMetrics()) {
        if (
            !belowDesktop ||
            mobileFocusMode ||
            !toolbarNode ||
            !canvasNode ||
            !metrics?.width ||
            !metrics?.height
        ) {
            if (untrack(() => canvasSafeTop) !== 0) canvasSafeTop = 0;
            return;
        }

        const toolbarRect = toolbarNode.getBoundingClientRect?.();
        const toolbarHeight = Math.max(
            44,
            Number(toolbarRect?.height) ||
                Number(toolbarNode.offsetHeight) ||
                52,
        );
        const nextTop = Math.min(
            Math.max(0, metrics.height - 32),
            Math.ceil(toolbarHeight + 12),
        );
        if (untrack(() => canvasSafeTop) !== nextTop) canvasSafeTop = nextTop;
    }

    function canvasContentMetrics() {
        if (!canvasNode) return null;

        const rect = canvasNode.getBoundingClientRect?.();
        if (!rect) return null;

        const computed =
            typeof window !== 'undefined' &&
            typeof window.getComputedStyle === 'function'
                ? window.getComputedStyle(canvasNode)
                : null;
        const readPx = (value) => {
            const parsed = Number.parseFloat(value ?? '0');
            return Number.isFinite(parsed) ? parsed : 0;
        };
        const paddingLeft = readPx(computed?.paddingLeft);
        const paddingRight = readPx(computed?.paddingRight);
        const paddingTop = readPx(computed?.paddingTop);
        const paddingBottom = readPx(computed?.paddingBottom);
        const borderLeft =
            Number(canvasNode.clientLeft) || readPx(computed?.borderLeftWidth);
        const borderTop =
            Number(canvasNode.clientTop) || readPx(computed?.borderTopWidth);
        const hasClientWidth = Number(canvasNode.clientWidth) > 0;
        const hasClientHeight = Number(canvasNode.clientHeight) > 0;
        const outerWidth = hasClientWidth
            ? Number(canvasNode.clientWidth)
            : Number(rect.width) || 0;
        const outerHeight = hasClientHeight
            ? Number(canvasNode.clientHeight)
            : Number(rect.height) || 0;
        const borderRight = hasClientWidth
            ? Math.max(0, (Number(rect.width) || 0) - outerWidth - borderLeft)
            : readPx(computed?.borderRightWidth);
        const borderBottom = hasClientHeight
            ? Math.max(0, (Number(rect.height) || 0) - outerHeight - borderTop)
            : readPx(computed?.borderBottomWidth);

        return {
            left:
                (Number(rect.left) || 0) +
                borderLeft +
                paddingLeft -
                (Number(canvasNode.scrollLeft) || 0),
            top:
                (Number(rect.top) || 0) +
                borderTop +
                paddingTop -
                (Number(canvasNode.scrollTop) || 0),
            width: Math.max(
                0,
                outerWidth -
                    borderLeft -
                    borderRight -
                    paddingLeft -
                    paddingRight,
            ),
            height: Math.max(
                0,
                outerHeight -
                    borderTop -
                    borderBottom -
                    paddingTop -
                    paddingBottom,
            ),
        };
    }

    function renderedFrameRect(scale) {
        const expectedWidth = Math.max(0, pagePixelWidth * scale);
        const expectedHeight = Math.max(0, pagePixelHeight * scale);
        const frameRect = pageFrameNode?.getBoundingClientRect?.();
        if (
            frameRect &&
            (Number(frameRect.width) > 0 || Number(frameRect.height) > 0)
        ) {
            return {
                left: Number(frameRect.left) || 0,
                top: Number(frameRect.top) || 0,
                width: Number(frameRect.width) || expectedWidth,
                height: Number(frameRect.height) || expectedHeight,
            };
        }

        // The transformed page has the same visual dimensions as the frame. Remove
        // its current translation when the wrapper itself has no measurable rect.
        const pageRect = pageNode?.getBoundingClientRect?.();
        if (
            pageRect &&
            (Number(pageRect.width) > 0 || Number(pageRect.height) > 0)
        ) {
            return {
                left: (Number(pageRect.left) || 0) - panX,
                top: (Number(pageRect.top) || 0) - panY,
                width: Number(pageRect.width) || expectedWidth,
                height: Number(pageRect.height) || expectedHeight,
            };
        }

        const metrics = canvasContentMetrics();
        return {
            left: metrics?.left ?? 0,
            top: metrics?.top ?? 0,
            width: expectedWidth,
            height: expectedHeight,
        };
    }

    function resolveWheelFrame(scale) {
        if (
            wheelFrameModel &&
            Math.abs(wheelFrameModel.scale - scale) < 0.0001 &&
            Math.abs(wheelFrameModel.pageWidth - pagePixelWidth) < 0.0001 &&
            Math.abs(wheelFrameModel.pageHeight - pagePixelHeight) < 0.0001
        ) {
            return wheelFrameModel;
        }

        const actual = renderedFrameRect(scale);
        wheelFrameModel = {
            ...actual,
            scale,
            pageWidth: pagePixelWidth,
            pageHeight: pagePixelHeight,
        };
        return wheelFrameModel;
    }

    function estimateFrameAtScale(frameRect, scale) {
        const width = Math.max(0, pagePixelWidth * scale);
        const height = Math.max(0, pagePixelHeight * scale);
        const metrics = canvasContentMetrics();
        let left = frameRect.left + (frameRect.width - width) / 2;
        let top = frameRect.top;

        if (metrics && metrics.width > 0) {
            left =
                width <= metrics.width
                    ? metrics.left + (metrics.width - width) / 2
                    : metrics.left;
            top = metrics.top;
        }

        return { left, top, width, height };
    }

    async function correctWheelAnchor(
        sequence,
        clientX,
        clientY,
        pagePoint,
        scale,
    ) {
        await tick();
        if (
            sequence !== wheelSequence ||
            Math.abs(effectiveZoom - scale) > 0.0001
        )
            return;

        const rect = pageNode?.getBoundingClientRect?.();
        if (!rect || !Number(rect.width) || !Number(rect.height)) return;

        const renderedX = Number(rect.left) + pagePoint.x * scale;
        const renderedY = Number(rect.top) + pagePoint.y * scale;
        const correctionX = clientX - renderedX;
        const correctionY = clientY - renderedY;
        if (!Number.isFinite(correctionX) || !Number.isFinite(correctionY))
            return;
        if (Math.abs(correctionX) > 0.01) panX += correctionX;
        if (Math.abs(correctionY) > 0.01) panY += correctionY;
    }

    function setManualZoom(nextScale) {
        fitRequestSequence += 1;
        wheelSequence += 1;
        wheelFrameModel = null;
        zoomMode = 'manual';
        zoom = clampZoom(nextScale, MIN_ZOOM, MAX_ZOOM);
    }

    function handleFit() {
        fitRequestSequence += 1;
        wheelSequence += 1;
        fitInvalidation += 1;
        wheelFrameModel = null;
        zoomMode = 'fit';
        panX = 0;
        panY = 0;

        const metrics = canvasContentMetrics();
        if (metrics) {
            canvasWidth = metrics.width;
            canvasHeight = metrics.height;
        }
    }

    let fitRequestSequence = 0;
    function requestFitAfterLayout() {
        // A manual zoom owns the viewport until the user explicitly selects Fit.
        // Layout measurement callbacks may still finish after the zoom action, so
        // check the mode both before scheduling and after the tick.
        if (untrack(() => zoomMode) !== 'fit') return;

        const requestSequence = ++fitRequestSequence;
        void tick().then(() => {
            if (requestSequence !== fitRequestSequence) return;
            if (untrack(() => zoomMode) !== 'fit') return;

            const metrics = canvasContentMetrics();
            if (!metrics?.width || !metrics?.height) return;

            handleFit();
        });
    }
    function measureCanvasAssistantInset(metrics = canvasContentMetrics()) {
        if (!canvasNode || !pageNode || typeof document === 'undefined') {
            resetCanvasSafeInsets();
            return;
        }

        const assistants = [
            ...document.querySelectorAll(
                '.xiam-fab, .xiam-dock, [data-xiam-assist-root]',
            ),
        ];
        if (!assistants.length || !metrics?.width || !metrics?.height) {
            resetCanvasSafeInsets();
            return;
        }

        const pageRect = pageNode.getBoundingClientRect();
        if (!pageRect.width || !pageRect.height) {
            resetCanvasSafeInsets();
            return;
        }

        const assistantRects = assistants.map((assistant) =>
            assistant.getBoundingClientRect(),
        );
        const measurementKey = [
            metrics.left,
            metrics.top,
            metrics.width,
            metrics.height,
            pagePixelWidth,
            pagePixelHeight,
            zoomMode,
            zoom,
            panX,
            panY,
            assistantRects
                .map((rect) =>
                    [
                        rect.left,
                        rect.top,
                        rect.right,
                        rect.bottom,
                        rect.width,
                        rect.height,
                    ].join(','),
                )
                .join('|'),
        ].join(';');
        if (measurementKey === lastCanvasAssistantMeasurementKey) return;
        lastCanvasAssistantMeasurementKey = measurementKey;

        const insetGap = 12;
        let nextBottom = 0;
        let nextRight = 0;
        for (const assistantRect of assistantRects) {
            if (!assistantRect.width || !assistantRect.height) continue;

            const horizontalOverlap =
                Math.min(pageRect.right, assistantRect.right) -
                Math.max(pageRect.left, assistantRect.left);
            const verticalOverlap =
                Math.min(pageRect.bottom, assistantRect.bottom) -
                Math.max(pageRect.top, assistantRect.top);

            if (horizontalOverlap <= 0 || verticalOverlap <= 0) continue;

            nextBottom = Math.max(
                nextBottom,
                Math.min(
                    Math.max(0, metrics.height - 32),
                    Math.ceil(verticalOverlap + insetGap),
                ),
            );
            nextRight = Math.max(
                nextRight,
                Math.min(
                    Math.max(0, metrics.width - 32),
                    Math.ceil(horizontalOverlap + insetGap),
                ),
            );
        }

        if (untrack(() => canvasSafeBottom) !== nextBottom)
            canvasSafeBottom = nextBottom;
        if (untrack(() => canvasSafeRight) !== nextRight)
            canvasSafeRight = nextRight;
    }

    $effect(() => {
        toolbarNode;
        if (!canvasNode) return;
        const measure = () => {
            const metrics = canvasContentMetrics();
            canvasWidth = metrics?.width ?? 0;
            canvasHeight = metrics?.height ?? 0;
            measureCanvasToolbarInset(metrics);
            measureCanvasAssistantInset(metrics);
        };
        measure();
        const observer = new ResizeObserver(measure);
        observer.observe(canvasNode);
        window.addEventListener('resize', measure);

        const assistantSelector = '.xiam-fab, .xiam-dock';
        const assistantClassPattern = /(?:^|\s)xiam-(?:fab|dock)(?:\s|$)/;
        const isAssistant = (node) =>
            node instanceof Element && node.matches(assistantSelector);
        const isAssistantOrDescendant = (node) =>
            node instanceof Element &&
            (isAssistant(node) || Boolean(node.closest(assistantSelector)));
        const nodeContainsAssistant = (node) =>
            node instanceof Element &&
            (isAssistant(node) ||
                Boolean(node.querySelector?.(assistantSelector)));
        const mutationTouchesAssistant = (mutation) => {
            if (mutation.type === 'childList') {
                return (
                    isAssistantOrDescendant(mutation.target) ||
                    [...mutation.addedNodes, ...mutation.removedNodes].some(
                        nodeContainsAssistant,
                    )
                );
            }
            if (mutation.type !== 'attributes') return false;
            if (mutation.attributeName === 'class') {
                return (
                    isAssistantOrDescendant(mutation.target) ||
                    assistantClassPattern.test(mutation.oldValue ?? '')
                );
            }
            return isAssistantOrDescendant(mutation.target);
        };
        const mutationObserver =
            typeof MutationObserver === 'function'
                ? new MutationObserver((mutations) => {
                      if (!mutations.some(mutationTouchesAssistant)) return;
                      lastCanvasAssistantMeasurementKey = '';
                      measure();
                  })
                : null;
        const observationRoot = document.body ?? document.documentElement;
        if (mutationObserver && observationRoot) {
            mutationObserver.observe(observationRoot, {
                subtree: true,
                childList: true,
                attributes: true,
                attributeFilter: ['class', 'style', 'hidden'],
                attributeOldValue: true,
            });
        }

        return () => {
            observer.disconnect();
            mutationObserver?.disconnect();
            window.removeEventListener('resize', measure);
        };
    });

    $effect(() => {
        page.paper_size;
        page.orientation;
        pagePixelWidth;
        pagePixelHeight;
        canvasWidth;
        canvasHeight;
        wheelSequence += 1;
        wheelFrameModel = null;
        if (untrack(() => zoomMode) === 'fit') {
            panX = 0;
            panY = 0;
        }
    });

    $effect(() => {
        canvasNode;
        pageNode;
        canvasWidth;
        canvasHeight;
        pagePixelWidth;
        pagePixelHeight;
        zoomMode;
        zoom;
        belowDesktop;
        mobileFocusMode;
        measureCanvasToolbarInset();
        measureCanvasAssistantInset();
    });

    $effect(() => {
        canvasWidth;
        canvasHeight;
        canvasSafeTop;
        mobileSurfaceOpen;
        mobileSurfaceHeight;
        if (!canvasWidth || !canvasHeight) return;
        if (preserveViewportOnNextLayout) {
            preserveViewportOnNextLayout = false;
            fitRequestSequence += 1;
            return;
        }

        // Layout changes must use the existing fit controller after the browser has
        // committed the new canvas/panel geometry, rather than duplicating viewport math.
        requestFitAfterLayout();
    });

    $effect(() => {
        const viewportSignature = `${zoomMode}|${zoom}|${panX}|${panY}|${effectiveZoom}|${Math.round(effectiveZoom * 100)}`;
        if (viewportSignature === lastEmittedViewportSignature) return;

        lastEmittedViewportSignature = viewportSignature;
        onViewportChange({
            zoomMode,
            zoom,
            panX,
            panY,
            effectiveZoom,
            percentage: Math.round(effectiveZoom * 100),
        });
    });

    function adoptIncomingDocument(incoming) {
        localDocument = normalizeDocument(incoming);
        historyEngine.reset(clone(localDocument));
        historyStatus = historyEngine.status();

        // Clean up selection if items removed
        const validIds = new Set(localDocument.components.map((c) => c.id));
        for (const id of selectedIds) {
            if (!validIds.has(id)) {
                selectionEngine.remove(id);
            }
        }
        syncSelectionState();
    }

    $effect(() => {
        const incoming = builderDocument;
        if (!incoming) {
            if (!lastObservedIncomingDocument)
                initiallyMountedWithoutBuilderDocument = true;
            return;
        }
        const incomingJson = JSON.stringify(incoming);
        if (!lastObservedIncomingDocument) {
            lastObservedIncomingDocument = incomingJson;
            const localJson = JSON.stringify(localDocument);
            if (initiallyMountedWithoutBuilderDocument || (incomingJson !== localJson && incomingJson !== lastEmittedDocument)) {
                adoptIncomingDocument(incoming);
            }
            return;
        }
        if (
            incomingJson === lastObservedIncomingDocument ||
            incomingJson === lastEmittedDocument ||
            incomingJson === JSON.stringify(localDocument)
        )
            return;
        lastObservedIncomingDocument = incomingJson;
        adoptIncomingDocument(incoming);
    });

    function emit() {
        const snapshot = clone(localDocument);
        lastEmittedDocument = JSON.stringify(snapshot);
        onChange(snapshot);
    }

    function commit(next, label = 'Update document') {
        if (readOnly) return;
        const snapshot = normalizeDocument(next);
        localDocument = snapshot;
        historyEngine.record(snapshot, label);
        historyStatus = historyEngine.status();
        emit();
    }

    function undo() {
        if (readOnly) return;
        const previous = historyEngine.undo();
        if (previous) {
            localDocument = clone(previous);
            historyStatus = historyEngine.status();
            emit();
            announce('Undo');
        }
    }

    function redo() {
        if (readOnly) return;
        const next = historyEngine.redo();
        if (next) {
            localDocument = clone(next);
            historyStatus = historyEngine.status();
            emit();
            announce('Redo');
        }
    }

    function updatePage(key, value) {
        commit(
            {
                ...localDocument,
                page: { ...(localDocument.page ?? {}), [key]: value },
            },
            `Update page ${key}`,
        );
    }

    function updatePageCoalesced(key, value, label = 'Update page') {
        const snapshot = normalizeDocument({
            ...localDocument,
            page: { ...(localDocument.page ?? {}), [key]: value },
        });
        localDocument = snapshot;
        historyEngine.record(snapshot, label, {
            coalesceKey: `page-${key}`,
            windowMs: 500,
        });
        historyStatus = historyEngine.status();
        emit();
    }

    function updateComponent(id, patch, label = 'Update component') {
        commit(
            {
                ...localDocument,
                components: localDocument.components.map((component) => {
                    if (component.id !== id) return component;
                    const next = { ...component, ...patch };
                    if (patch.props)
                        next.props = normalizeComponentProps(next.type, patch.props);
                    return next;
                }),
            },
            label,
        );
    }

    function updateComponentCoalesced(
        id,
        patch,
        propName,
        label = 'Update component',
    ) {
        const nextComponents = localDocument.components.map((component) => {
            if (component.id !== id) return component;
            const next = { ...component, ...patch };
            if (patch.props)
                next.props = normalizeComponentProps(next.type, patch.props);
            return next;
        });
        const snapshot = normalizeDocument({
            ...localDocument,
            components: nextComponents,
        });
        localDocument = snapshot;
        historyEngine.record(snapshot, label, {
            coalesceKey: `${id}-${propName}`,
            windowMs: 500,
        });
        historyStatus = historyEngine.status();
        emit();
    }

    function tableColumnsForInspector(component) {
        const configured = Array.isArray(component.props?.columns)
            ? component.props.columns.filter((column) => column && typeof column === 'object')
            : [];
        const catalog = Array.isArray(builderCatalog?.data_table?.default_props?.columns)
            ? builderCatalog.data_table.default_props.columns
            : [];
        const columns = [...configured];
        const knownBindings = new Set(
            configured.map((column) => String(column.binding ?? column.key ?? '')).filter(Boolean),
        );

        for (const column of catalog) {
            if (!column || typeof column !== 'object') continue;
            const binding = String(column.binding ?? column.key ?? '');
            if (!binding || knownBindings.has(binding)) continue;
            knownBindings.add(binding);
            columns.push({ ...column, checked: false });
        }

        return columns.map((column) => ({
            ...column,
            label: String(column.label ?? column.binding ?? column.key ?? ''),
            checked: column.checked !== false,
        }));
    }

    function kpiMetricsForInspector(component) {
        const metrics = Array.isArray(component.props?.metrics)
            ? component.props.metrics
            : Array.isArray(component.props?.items)
              ? component.props.items
              : [];

        return metrics.map((metric) => {
            const normalized = typeof metric === 'string' ? { binding: metric } : metric ?? {};
            return {
                ...normalized,
                binding: String(normalized.binding ?? ''),
                label: String(normalized.label ?? normalized.binding ?? ''),
                checked: normalized.checked !== false,
            };
        });
    }

    function moveInspectorItem(items, index, direction) {
        const nextIndex = index + direction;
        if (nextIndex < 0 || nextIndex >= items.length) return items;
        const next = [...items];
        [next[index], next[nextIndex]] = [next[nextIndex], next[index]];
        return next;
    }

    function clampComponentPosition(x, y, width, height) {
        const safeWidth = Math.max(0, Number(width) || 0);
        const safeHeight = Math.max(0, Number(height) || 0);
        return {
            x: Math.min(
                Math.max(0, Number(x) || 0),
                Math.max(0, printableWidth - safeWidth),
            ),
            y: Math.min(
                Math.max(0, Number(y) || 0),
                Math.max(0, printableHeight - safeHeight),
            ),
        };
    }

    function toIdentifiedRect(component) {
        return {
            id: component.id,
            x: Number(component.x),
            y: Number(component.y),
            width: Number(component.w),
            height: Number(component.h),
        };
    }

    function allocateComponentId(type, existingComponents = []) {
        const prefix = `${type}-`;
        let maxOrdinal = 0;
        for (const comp of existingComponents) {
            if (typeof comp.id === 'string' && comp.id.startsWith(prefix)) {
                const remainder = comp.id.slice(prefix.length);
                const parsed = parseInt(remainder, 10);
                if (
                    !isNaN(parsed) &&
                    String(parsed) === remainder &&
                    parsed > maxOrdinal
                ) {
                    maxOrdinal = parsed;
                }
            }
        }
        return `${type}-${maxOrdinal + 1}`;
    }
    function addComponent(type, point = null) {
        const existingComponents = Array.isArray(localDocument.components)
            ? localDocument.components
            : [];
        const count = existingComponents.filter(
            (component) => component.type === type,
        ).length;
        const catalogWidth = Number(builderCatalog?.[type]?.default_w);
        const catalogHeight = Number(builderCatalog?.[type]?.default_h);
        const width =
            Number.isFinite(catalogWidth) && catalogWidth > 0
                ? catalogWidth
                : Math.max(
                      30,
                      Number(page.width_mm) - Number(page.margin_mm) * 2 - 16,
                  );
        const height =
            Number.isFinite(catalogHeight) && catalogHeight > 0
                ? catalogHeight
                : type === 'score_table'
                  ? 60
                  : type === 'header'
                    ? 24
                    : 12;
        const defaultPoint = {
            x: point?.x ?? 8,
            y:
                point?.y ??
                Math.min(
                    Number(page.height_mm) - 30,
                    12 + ((existingComponents.length * 8) % 230),
                ),
        };
        const position = clampComponentPosition(
            defaultPoint.x,
            defaultPoint.y,
            width,
            height,
        );
        const component = {
            id: allocateComponentId(type, existingComponents),
            type,
            ...position,
            w: width,
            h: height,
            locked: false,
            props: normalizeComponentProps(type, defaultProps(type)),
        };
        commit(
            {
                ...localDocument,
                components: [...existingComponents, component],
            },
            `Add ${componentLabel(type)}`,
        );
        selectionEngine.select(component.id);
        syncSelectionState('palette');
        announce(`Added ${componentLabel(type)} component`);
    }

    function defaultProps(type) {
        const catalogProps = builderCatalog?.[type]?.default_props;
        const baseProps =
            catalogProps &&
            typeof catalogProps === 'object' &&
            !Array.isArray(catalogProps)
                ? { ...catalogProps }
                : {};
        const typeDefaults =
            {
                header: {
                    title: '{{institution_name}}',
                    subtitle:
                        '{{institution_exam_name}} ({{institution_exam_acronym}})',
                    align: 'center',
                },
                field: {
                    label: 'Applicant number',
                    binding: 'applicant_reference',
                },
                score_table: { title: 'Record of Scores' },
                kpi_grid: {
                    metrics: [
                        {
                            label: 'Total Examinees',
                            binding: 'total_examinees',
                        },
                        { label: 'Total Passed', binding: 'total_passed' },
                        { label: 'Passing Rate', binding: 'passing_rate' },
                    ],
                },
                data_table: {
                    title: 'Passers',
                    repeat_key: 'passers',
                    columns: [
                        { label: 'Row #', binding: 'row_no' },
                        { label: 'Reference', binding: 'reference_code' },
                        { label: 'Applicant Name', binding: 'applicant_name' },
                        { label: 'Program', binding: 'program_code' },
                        { label: 'Score', binding: 'composite_score' },
                        { label: 'Remarks', binding: 'remarks' },
                    ],
                },
                signature: { label: 'Counselor', binding: 'counselor_name' },
                text: { text: 'Add text', align: 'left' },
                note: { text: 'Add note' },
                footer: { text: 'NEXIAM result sheet', align: 'center' },
                image: allowNoticeDocumentImages && noticeDocumentImageAssets[0]
                    ? { alt: '', asset_id: noticeDocumentImageAssets[0].asset_id, src: noticeDocumentImageAssets[0].src, fit: 'contain' }
                    : { alt: 'Template image' },
                dynamic_image: {
                    binding: 'applicant_photo_2x2',
                    alt: 'Candidate Photo',
                    fit: 'cover',
                    fallback: 'affix_box',
                },
                dynamic_qr: {
                    binding: 'verification_qr',
                    alt: 'Verification QR',
                    error_correction: 'M',
                },
            }[type] ?? {};

        const mergedProps = { ...typeDefaults, ...baseProps };
        if (type === 'image' && allowNoticeDocumentImages) {
            const selectedId = typeof mergedProps.asset_id === 'string' ? mergedProps.asset_id : '';
            const verifiedAsset = noticeDocumentImageAssets.find((asset) => asset.asset_id === selectedId) ?? noticeDocumentImageAssets[0];
            if (verifiedAsset) mergedProps.asset_id = verifiedAsset.asset_id;
            else delete mergedProps.asset_id;
            mergedProps.src = verifiedAsset?.src ?? '';
            mergedProps.alt = typeof mergedProps.alt === 'string' ? mergedProps.alt : '';
            mergedProps.fit = ['contain', 'cover', 'fill'].includes(String(mergedProps.fit)) ? mergedProps.fit : 'contain';
        }
        if (
            type === 'data_table' &&
            (!Array.isArray(mergedProps.columns) || !mergedProps.columns.length)
        ) {
            mergedProps.columns = typeDefaults.columns;
        }

        return mergedProps;
    }

    function removeSelected() {
        if (selectedIds.size === 0) return;
        const unlockedSelectedIds = filterUnlocked(
            components,
            Array.from(selectedIds),
        );
        if (unlockedSelectedIds.length === 0) {
            announce('Cannot delete locked components');
            return;
        }
        const toDeleteSet = new Set(unlockedSelectedIds);
        const remaining = components.filter((c) => !toDeleteSet.has(c.id));
        commit(
            { ...localDocument, components: remaining },
            `Delete ${unlockedSelectedIds.length} component(s)`,
        );
        for (const id of unlockedSelectedIds) {
            selectionEngine.remove(id);
        }
        syncSelectionState();
        announce(`Deleted ${unlockedSelectedIds.length} component(s)`);
    }

    function duplicateSelected() {
        if (selectedIds.size === 0) return;
        const unlockedSelected = selectedList.filter((c) => !c.locked);
        if (unlockedSelected.length === 0) return;

        const currentPool = [...components];
        const duplicates = unlockedSelected.map((item) => {
            const pos = clampComponentPosition(
                Number(item.x) + 4,
                Number(item.y) + 4,
                item.w,
                item.h,
            );
            const nextId = allocateComponentId(item.type, currentPool);
            const cloneItem = {
                ...clone(item),
                id: nextId,
                ...pos,
                locked: false,
            };
            currentPool.push(cloneItem);
            return cloneItem;
        });

        const newComponents = [...components, ...duplicates];
        commit(
            { ...localDocument, components: newComponents },
            `Duplicate ${duplicates.length} component(s)`,
        );

        const newIds = duplicates.map((d) => d.id);
        selectionEngine.setSelection(newIds, newIds[newIds.length - 1]);
        syncSelectionState();
        announce(`Duplicated ${duplicates.length} component(s)`);
    }

    function toggleSelectedLock() {
        if (selectedIds.size === 0) return;
        const ids = Array.from(selectedIds);
        const updated = toggleLocked(components, ids);
        commit(
            { ...localDocument, components: updated },
            `Toggle lock on ${ids.length} component(s)`,
        );
        announce(`Lock toggled for ${ids.length} component(s)`);
    }

    function alignSelected(target) {
        const unlocked = selectedList.filter((c) => !c.locked);
        if (unlocked.length < 2) return;

        const identifiedRects = unlocked.map(toIdentifiedRect);
        const aligned = alignRects(identifiedRects, target);
        const alignedMap = new Map(aligned.map((r) => [r.id, r]));

        const updated = components.map((comp) => {
            const match = alignedMap.get(comp.id);
            if (!match) return comp;
            const clamped = clampComponentPosition(
                match.x,
                match.y,
                comp.w,
                comp.h,
            );
            return { ...comp, ...clamped };
        });

        commit({ ...localDocument, components: updated }, `Align ${target}`);
        announce(`Aligned ${unlocked.length} components to ${target}`);
    }

    function distributeSelected(axis) {
        const unlocked = selectedList.filter((c) => !c.locked);
        if (unlocked.length < 3) return;

        const identifiedRects = unlocked.map(toIdentifiedRect);
        const distributed = distributeRects(identifiedRects, axis);
        const distributedMap = new Map(distributed.map((r) => [r.id, r]));

        const updated = components.map((comp) => {
            const match = distributedMap.get(comp.id);
            if (!match) return comp;
            const clamped = clampComponentPosition(
                match.x,
                match.y,
                comp.w,
                comp.h,
            );
            return { ...comp, ...clamped };
        });

        commit({ ...localDocument, components: updated }, `Distribute ${axis}`);
        announce(`Distributed ${unlocked.length} components ${axis}`);
    }

    function selectAllComponents() {
        const allIds = components.map((c) => c.id);
        selectionEngine.selectAll(allIds);
        syncSelectionState();
        announce(`Selected all ${allIds.length} components`);
    }

    function deselectAll() {
        selectionEngine.clear();
        syncSelectionState();
    }

    function fitSelectedHeight() {
        if (!primaryComponent || !pageNode || primaryComponent.locked) return;
        const node = [
            ...document.querySelectorAll('[data-result-sheet-component]'),
        ].find(
            (candidate) =>
                candidate.getAttribute('data-result-sheet-component') ===
                primaryComponent.id,
        );
        if (!node) return;
        const previousMinHeight = node.style.minHeight;
        const previousHeight = node.style.height;
        node.style.minHeight = '0px';
        node.style.height = 'auto';
        const contentHeight = node.getBoundingClientRect().height;
        const pageHeight = pageNode.getBoundingClientRect().height;
        node.style.minHeight = previousMinHeight;
        node.style.height = previousHeight;
        if (!contentHeight || !pageHeight) return;
        const fittedHeight = Math.max(
            8,
            Number(
                ((contentHeight / pageHeight) * Number(page.height_mm)).toFixed(
                    1,
                ),
            ),
        );
        updateComponent(
            primaryComponent.id,
            { h: fittedHeight },
            'Fit height to content',
        );
        announce(`Fitted height to ${fittedHeight} mm`);
    }

    function updateSelectedProps(patch, label = 'Update properties') {
        if (!primaryComponent || primaryComponent.locked) return;
        updateComponent(
            primaryComponent.id,
            { props: { ...primaryComponent.props, ...patch } },
            label,
        );
    }

    function updateSelectedTypography(typography) {
        if (!primaryComponent || primaryComponent.locked) return;
        updateSelectedProps(
            {
                typography,
                text_styles: {
                    ...(primaryComponent.props?.text_styles ?? {}),
                    [activeTextPart]: typography,
                },
            },
            'Update typography',
        );
    }

    function updateSelectedAppearance(appearance) {
        if (!primaryComponent || primaryComponent.locked) return;
        updateSelectedProps({ appearance }, 'Update appearance');
    }

    function updateSelectedSpacing(spacing) {
        if (!primaryComponent || primaryComponent.locked) return;
        updateSelectedProps({ spacing }, 'Update spacing');
    }


    function componentLabel(type) {
        return studioCatalog[type]?.label ?? String(type).replace(/_/g, ' ');
    }

    function componentIcon(type) {
        return (
            {
                image: ImageIcon,
                header: FileText,
                metadata_grid: LayoutList,
                summary_metrics: BadgePercent,
                kpi_grid: Grid3X3,
                paragraph: AlignLeft,
                text: Type,
                data_table: Table2,
                signature: PenLine,
                footer: StickyNote,
                spacer: Maximize2,
                divider: MinusIcon,
                applicant_info: UserCheck,
                exam_details: Calendar,
                score_table: CalendarClock,
                notice_box: AlertTriangle,
                qr_block: QrCode,
                identity: UserRound,
                field: Brackets,
                overall: Sparkles,
                recommendation: StickyNote,
                note: StickyNote,
            }[type] ?? FileText
        );
    }

    function findBindings(value) {
        const found = new Set();
        const walk = (item) => {
            if (Array.isArray(item)) return item.forEach(walk);
            if (item && typeof item === 'object')
                return Object.values(item).forEach(walk);
            if (typeof item !== 'string') return;
            for (const match of item.matchAll(/\{\{\s*([a-zA-Z0-9_]+)\s*\}\}/g))
                found.add(match[1]);
        };
        walk(value);
        return [...found].sort();
    }

    function diagnosticList(document) {
        const pageData = document?.page ?? {};
        const width =
            Number(pageData.width_mm ?? 210) -
            Number(pageData.margin_mm ?? 12) * 2;
        const height =
            Number(pageData.height_mm ?? 297) -
            Number(pageData.margin_mm ?? 12) * 2;
        const documentComponents = Array.isArray(document?.components)
            ? document.components
            : [];
        const sampleRows = Array.isArray(sampleContext?.scores_rows)
            ? Math.max(3, sampleContext.scores_rows.length)
            : 3;

        return documentComponents.flatMap((component) => {
            const errors = [];
            const capabilities = builderCapabilities?.[component.type] ?? {};
            if (
                Number(component.x) < 0 ||
                Number(component.y) < 0 ||
                Number(component.x) + Number(component.w) > width ||
                Number(component.y) + Number(component.h) > height
            ) {
                errors.push(
                    `${componentLabel(component.type)} exceeds the printable area`,
                );
            }
            if (component.type === 'field' && !component.props?.binding)
                errors.push('Field needs a binding');
            if (component.type === 'score_table' && capabilities.dynamic_rows) {
                const fixedHeight = Number(
                    capabilities.dynamic_fixed_height_mm ?? 12,
                );
                const rowHeight = Number(
                    capabilities.dynamic_row_height_mm ?? 6,
                );
                const requiredHeight = fixedHeight + sampleRows * rowHeight;
                if (Number(component.h) + 0.01 < requiredHeight) {
                    errors.push(
                        `Score table needs at least ${requiredHeight}mm for ${sampleRows} sample rows`,
                    );
                }
            }
            return errors;
        });
    }

    function canvasPoint(clientX, clientY) {
        const node =
            pageNode ?? document.querySelector('[data-result-sheet-page]');
        const bounds = node?.getBoundingClientRect();
        if (!bounds?.width || !bounds?.height) return null;
        return {
            x:
                ((clientX - bounds.left) / bounds.width) *
                    Number(page.width_mm) -
                Number(page.margin_mm),
            y:
                ((clientY - bounds.top) / bounds.height) *
                    Number(page.height_mm) -
                Number(page.margin_mm),
        };
    }

    // ==========================================
    // Canvas Pan & Marquee Interactions
    // ==========================================

    async function handleCanvasWheel(event) {
        // The canvas owns wheel events, including events originating in nested
        // components. Always suppress document scrolling before any gesture guard.
        event.preventDefault();
        event.stopPropagation();
        if (dragState || resizeState || paletteDragState) return;

        const isPinch = event.ctrlKey || event.metaKey;
        const viewportSize =
            Number(canvasNode?.clientHeight) ||
            canvasHeight ||
            Number(globalThis.innerHeight) ||
            800;
        const zoomFactor = calculateWheelZoomFactor(
            event.deltaY,
            isPinch ? 0.05 : 0.1,
            event.deltaMode ?? 0,
            viewportSize,
        );
        const scaleOld = effectiveZoom;
        const scaleNew = clampZoom(scaleOld * zoomFactor, MIN_ZOOM, MAX_ZOOM);
        const sequence = (wheelSequence += 1);

        if (scaleNew === scaleOld) return;

        const frameRect = resolveWheelFrame(scaleOld);
        const nextFrameRect = estimateFrameAtScale(frameRect, scaleNew);
        const pageRect = pageNode?.getBoundingClientRect?.();
        const pageOriginX =
            pageRect && Number(pageRect.width) > 0
                ? Number(pageRect.left)
                : frameRect.left + panX;
        const pageOriginY =
            pageRect && Number(pageRect.height) > 0
                ? Number(pageRect.top)
                : frameRect.top + panY;
        const pagePoint = {
            x: (event.clientX - pageOriginX) / scaleOld,
            y: (event.clientY - pageOriginY) / scaleOld,
        };

        const result = calculateCursorZoom({
            cursorX: event.clientX,
            cursorY: event.clientY,
            panX,
            panY,
            scaleOld,
            scaleNew,
            frameX: frameRect.left,
            frameY: frameRect.top,
            nextFrameX: nextFrameRect.left,
            nextFrameY: nextFrameRect.top,
        });
        fitRequestSequence += 1;
        zoomMode = 'manual';
        zoom = result.scale;
        panX = result.panX;
        panY = result.panY;
        wheelFrameModel = {
            ...nextFrameRect,
            scale: scaleNew,
            pageWidth: pagePixelWidth,
            pageHeight: pagePixelHeight,
        };

        // The prediction handles the frame's auto-centering synchronously. Once
        // Svelte has committed the new layout, correct only the latest wheel event
        // against the actual transformed page rect to remove sub-pixel drift.
        void correctWheelAnchor(
            sequence,
            event.clientX,
            event.clientY,
            pagePoint,
            scaleNew,
        );
    }

    function beginCanvasPanOrMarquee(event) {
        // Middle click or Pan tool or space/alt modifier pans viewport
        const shouldPan =
            canvasTool === 'pan' ||
            isSpacePressed ||
            event.altKey ||
            event.button === 1 ||
            (event.buttons & 4) === 4;

        if (dragState || resizeState || paletteDragState) return;
        if (
            event.target?.closest?.(
                'button, input, select, textarea, [data-result-sheet-component]',
            )
        )
            return;

        event.preventDefault();
        event.currentTarget?.setPointerCapture?.(event.pointerId);

        if (shouldPan) {
            panState = {
                pointerId: event.pointerId,
                startX: event.clientX,
                startY: event.clientY,
                originX: panX,
                originY: panY,
            };
            return;
        }

        // Otherwise, primary button on empty canvas triggers marquee selection
        if (event.button === 0) {
            const point = canvasPoint(event.clientX, event.clientY);
            if (!point) return;

            const isAdditive = event.shiftKey;

            marqueeState = {
                pointerId: event.pointerId,
                startClientX: event.clientX,
                startClientY: event.clientY,
                startPoint: point,
                currentPoint: point,
                additive: isAdditive,
                initialSelection: new Set(selectedIds),
                initialPrimaryId: primaryId,
                previewIds: new Set(selectedIds),
                previewPrimaryId: primaryId,
            };
        }
    }

    function moveCanvasPanOrMarquee(event) {
        if (panState && event.pointerId === panState.pointerId) {
            event.preventDefault();
            panX = panState.originX + event.clientX - panState.startX;
            panY = panState.originY + event.clientY - panState.startY;
            return;
        }

        if (marqueeState && event.pointerId === marqueeState.pointerId) {
            event.preventDefault();
            const point = canvasPoint(event.clientX, event.clientY);
            if (!point) return;

            // Keep the in-progress hit list local to the marquee. Publishing selection
            // here opens the contextual Inspector before the rectangle is complete.
            const rect = normalizeRect({
                x: Math.min(marqueeState.startPoint.x, point.x),
                y: Math.min(marqueeState.startPoint.y, point.y),
                width: Math.abs(point.x - marqueeState.startPoint.x),
                height: Math.abs(point.y - marqueeState.startPoint.y),
            });

            let previewIds = marqueeState.additive
                ? new Set(marqueeState.initialSelection)
                : new Set();
            let previewPrimaryId = marqueeState.additive
                ? marqueeState.initialPrimaryId
                : null;

            if (rect.width > 1 || rect.height > 1) {
                const identifiedRects = components.map(toIdentifiedRect);
                const matchingIds = selectionEngine.queryMarquee(
                    identifiedRects,
                    rect,
                    'intersect',
                );

                if (marqueeState.additive) {
                    const next = new Set(marqueeState.initialSelection);
                    for (const id of matchingIds) next.add(id);
                    previewIds = next;
                    previewPrimaryId =
                        matchingIds[matchingIds.length - 1] ??
                        marqueeState.initialPrimaryId;
                } else {
                    previewIds = new Set(matchingIds);
                    previewPrimaryId =
                        matchingIds[matchingIds.length - 1] ?? null;
                }
            }

            marqueeState = {
                ...marqueeState,
                currentPoint: point,
                previewIds,
                previewPrimaryId,
            };
        }
    }

    function endCanvasPanOrMarquee(event) {
        if (panState && event.pointerId === panState.pointerId) {
            panState = null;
            return;
        }

        if (marqueeState && event.pointerId === marqueeState.pointerId) {
            const distance = Math.hypot(
                event.clientX - marqueeState.startClientX,
                event.clientY - marqueeState.startClientY,
            );

            if (distance < 4 && !marqueeState.additive) {
                // Blank click without drag clears selection
                selectionEngine.clear();
                syncSelectionState('canvas', canvasNode);
            } else if (distance >= 4) {
                // Finalize intersecting selection
                const finalRect = normalizeRect({
                    x: Math.min(
                        marqueeState.startPoint.x,
                        marqueeState.currentPoint.x,
                    ),
                    y: Math.min(
                        marqueeState.startPoint.y,
                        marqueeState.currentPoint.y,
                    ),
                    width: Math.abs(
                        marqueeState.currentPoint.x - marqueeState.startPoint.x,
                    ),
                    height: Math.abs(
                        marqueeState.currentPoint.y - marqueeState.startPoint.y,
                    ),
                });
                const identifiedRects = components.map(toIdentifiedRect);
                const matchingIds = selectionEngine.queryMarquee(
                    identifiedRects,
                    finalRect,
                    'intersect',
                );

                if (marqueeState.additive) {
                    const next = new Set(marqueeState.initialSelection);
                    for (const id of matchingIds) next.add(id);
                    selectionEngine.setSelection(
                        Array.from(next),
                        matchingIds[matchingIds.length - 1] ?? primaryId,
                    );
                } else {
                    selectionEngine.setSelection(
                        matchingIds,
                        matchingIds[matchingIds.length - 1] ?? null,
                    );
                }
                syncSelectionState('canvas', canvasNode);
                announce(
                    `Selected ${selectedIds.size} component(s) with marquee`,
                );
            }

            marqueeState = null;
        }
    }

    // ==========================================
    // Palette Drag & Drop
    // ==========================================

    function handleDragOver(event) {
        event.preventDefault();
        if (event.dataTransfer) event.dataTransfer.dropEffect = 'copy';
    }

    function handlePaletteDragStart(event, type) {
        try {
            event.dataTransfer?.setData(
                'application/x-result-sheet-component',
                type,
            );
            if (event.dataTransfer) event.dataTransfer.effectAllowed = 'copy';
        } catch {}
    }

    function handleDrop(event) {
        event.preventDefault();
        const type = event.dataTransfer?.getData('application/x-result-sheet-component');
        if (!type) return;
        const point = canvasPoint(event.clientX, event.clientY);
        if (point) addComponent(type, point);
    }

    function beginPaletteDrag(event, type) {
        if (readOnly) return;
        if (event.pointerType !== 'touch' || event.button !== 0) return;
        event.stopPropagation();
        event.currentTarget?.setPointerCapture?.(event.pointerId);
        paletteDragState = {
            type,
            pointerId: event.pointerId,
            startClientX: event.clientX,
            startClientY: event.clientY,
            moved: false,
        };
    }

    function handlePaletteClick(event, type) {
        if (readOnly) return;
        if (suppressPaletteClick) {
            event.preventDefault();
            suppressPaletteClick = false;
            return;
        }
        addComponent(type);
    }

    // ==========================================
    // Component Drag & Multi-Drag Movement
    // ==========================================

    function handleComponentKeydown(event, component) {
        if (
            event.key !== 'Enter' &&
            event.key !== ' ' &&
            event.code !== 'Space'
        )
            return;

        event.preventDefault();
        event.stopPropagation();

        const additive = event.shiftKey || event.ctrlKey || event.metaKey;
        if (additive) selectionEngine.toggle(component.id);
        else selectionEngine.select(component.id);
        syncSelectionState('canvas', event.currentTarget);
        announce(
            `${componentLabel(component.type)} component ${additive ? 'toggled' : 'selected'}`,
        );
    }

    function beginComponentDrag(event, component) {
        if (readOnly) return;
        if (event.button !== 0) return;

        // Space-drag or Pan tool over component pans canvas instead of moving component
        if (canvasTool === 'pan' || isSpacePressed) {
            event.preventDefault();
            event.stopPropagation();
            panState = {
                pointerId: event.pointerId,
                startX: event.clientX,
                startY: event.clientY,
                originX: panX,
                originY: panY,
            };
            event.currentTarget?.setPointerCapture?.(event.pointerId);
            return;
        }
        const startSelectedIds = Array.from(selectionEngine.getSelectedIds());
        const startPrimaryId = selectionEngine.getPrimaryId();
        const isAdditive = event.shiftKey;
        const isAltDuplicate = event.altKey;

        if (isAdditive && !selectedIds.has(component.id)) {
            selectionEngine.add(component.id);
            syncSelectionState('canvas', event.currentTarget);
        } else if (isAdditive && selectedIds.has(component.id)) {
            selectionEngine.setPrimaryId(component.id);
            syncSelectionState('canvas', event.currentTarget);
        }
        if (!selectedIds.has(component.id)) {
            selectionEngine.select(component.id);
            syncSelectionState('canvas', event.currentTarget);
        } else {
            selectionEngine.setPrimaryId(component.id);
            syncSelectionState('canvas', event.currentTarget);
        }

        if (component.locked) {
            return;
        }

        const point = canvasPoint(event.clientX, event.clientY);
        if (!point) return;

        event.preventDefault();
        event.stopPropagation();
        event.currentTarget?.setPointerCapture?.(event.pointerId);

        // Capture PRE-DUPLICATION document snapshot BEFORE any mutations:
        const preGestureDocument = clone(localDocument);

        // Start History gesture transaction BEFORE duplication so initialState is the pre-duplicate document:
        historyEngine.beginGesture(
            isAltDuplicate ? 'Duplicate and move' : 'Move selection',
        );

        let activeComponents = components;
        if (isAltDuplicate) {
            const unlockedSelected = selectedList.filter((c) => !c.locked);
            const currentPool = [...components];
            const duplicates = unlockedSelected.map((item) => {
                const nextId = allocateComponentId(item.type, currentPool);
                const cloneItem = {
                    ...clone(item),
                    id: nextId,
                    locked: false,
                };
                currentPool.push(cloneItem);
                return cloneItem;
            });
            activeComponents = [...components, ...duplicates];
            localDocument = { ...localDocument, components: activeComponents };
            const dupIds = duplicates.map((d) => d.id);
            selectionEngine.setSelection(dupIds, dupIds[dupIds.length - 1]);
            syncSelectionState('canvas', event.currentTarget);
            announce(`Duplicated ${duplicates.length} component(s) on drag`);
        }

        // Build initial position map for all selected unlocked items
        const selectedUnlocked = activeComponents.filter(
            (c) => selectedIds.has(c.id) && !c.locked,
        );
        const startPositions = new Map(
            selectedUnlocked.map((c) => [
                c.id,
                {
                    x: Number(c.x),
                    y: Number(c.y),
                    w: Number(c.w),
                    h: Number(c.h),
                },
            ]),
        );

        const otherRects = activeComponents
            .filter((c) => !selectedIds.has(c.id))
            .map((c) => ({
                x: Number(c.x),
                y: Number(c.y),
                width: Number(c.w),
                height: Number(c.h),
            }));

        dragState = {
            pointerId: event.pointerId,
            startClientX: event.clientX,
            startClientY: event.clientY,
            startPoint: point,
            startPositions,
            otherRects,
            isAltDuplicate,
            primaryId: component.id,
            startDocument: preGestureDocument,
            startSelectedIds,
            startPrimaryId,
            moved: false,
        };
    }

    // ==========================================
    // Component Corner Resize
    // ==========================================

    function beginComponentResize(event, component, direction = 'se') {
        if (readOnly) return;
        if (event.button !== 0 || component.locked) return;
        const point = canvasPoint(event.clientX, event.clientY);
        if (!point) return;

        event.preventDefault();
        event.stopPropagation();

        selectionEngine.select(component.id);
        syncSelectionState('canvas', event.currentTarget);
        event.currentTarget?.setPointerCapture?.(event.pointerId);

        historyEngine.beginGesture(`Resize ${componentLabel(component.type)}`);

        resizeState = {
            id: component.id,
            pointerId: event.pointerId,
            direction,
            startPoint: point,
            startDocument: clone(localDocument),
            startWidth: Number(component.w),
            startHeight: Number(component.h),
            startX: Number(component.x),
            startY: Number(component.y),
            moved: false,
        };
    }

    // ==========================================
    // Pointer Move Handler (Drag, Resize, Snap)
    // ==========================================

    function handlePointerMove(event) {
        // 1. Resize in progress
        if (resizeState && event.pointerId === resizeState.pointerId) {
            const point = canvasPoint(event.clientX, event.clientY);
            if (!point) return;
            event.preventDefault();

            const component = localDocument.components.find(
                (c) => c.id === resizeState.id,
            );
            if (!component || component.locked) return;

            const dx = point.x - resizeState.startPoint.x;
            const dy = point.y - resizeState.startPoint.y;
            let x = resizeState.startX;
            let y = resizeState.startY;
            let width = resizeState.startWidth;
            let height = resizeState.startHeight;
            const capabilities = builderCapabilities?.[component.type] ?? {};
            const resizeAxes = capabilities.resize_axes ?? 'both';
            const minW = Number(capabilities.min_dimensions?.w ?? 20);
            const minH = Number(capabilities.min_dimensions?.h ?? 8);
            const maxW = Math.max(
                minW,
                Number(capabilities.max_dimensions?.w ?? printableWidth),
            );
            const maxH = Math.max(
                minH,
                Number(capabilities.max_dimensions?.h ?? printableHeight),
            );
            const canH = resizeAxes === 'both' || resizeAxes === 'horizontal';
            const canV = resizeAxes === 'both' || resizeAxes === 'vertical';

            if (canH && resizeState.direction.includes('e')) {
                width = Math.min(
                    maxW,
                    printableWidth - x,
                    Math.max(minW, resizeState.startWidth + dx),
                );
            }
            if (canV && resizeState.direction.includes('s')) {
                height = Math.min(
                    maxH,
                    printableHeight - y,
                    Math.max(minH, resizeState.startHeight + dy),
                );
            }
            if (canH && resizeState.direction.includes('w')) {
                const minX = Math.max(
                    0,
                    resizeState.startX + resizeState.startWidth - maxW,
                );
                const maxX = Math.min(
                    resizeState.startX + resizeState.startWidth - minW,
                    printableWidth - minW,
                );
                const nextX = Math.min(
                    maxX,
                    Math.max(minX, resizeState.startX + dx),
                );
                x = nextX;
                width = resizeState.startWidth + resizeState.startX - nextX;
            }
            if (canV && resizeState.direction.includes('n')) {
                const minY = Math.max(
                    0,
                    resizeState.startY + resizeState.startHeight - maxH,
                );
                const maxY = Math.min(
                    resizeState.startY + resizeState.startHeight - minH,
                    printableHeight - minH,
                );
                const nextY = Math.min(
                    maxY,
                    Math.max(minY, resizeState.startY + dy),
                );
                y = nextY;
                height = resizeState.startHeight + resizeState.startY - nextY;
            }

            const aspectRatio =
                capabilities.aspect_ratio === 'square'
                    ? 1
                    : Number(capabilities.aspect_ratio);
            if (Number.isFinite(aspectRatio) && aspectRatio > 0) {
                if (
                    canH &&
                    (resizeState.direction.includes('e') ||
                        resizeState.direction.includes('w'))
                ) {
                    height = Math.min(
                        maxH,
                        printableHeight - y,
                        Math.max(minH, width / aspectRatio),
                    );
                } else if (
                    canV &&
                    (resizeState.direction.includes('s') ||
                        resizeState.direction.includes('n'))
                ) {
                    width = Math.min(
                        maxW,
                        printableWidth - x,
                        Math.max(minW, height * aspectRatio),
                    );
                }
            }
            resizeState.moved = true;
            const nextDoc = {
                ...localDocument,
                components: localDocument.components.map((c) =>
                    c.id === resizeState.id
                        ? { ...c, x, y, w: width, h: height }
                        : c,
                ),
            };
            localDocument = nextDoc;
            historyEngine.updateGesture(nextDoc);
            return;
        }

        // 2. Palette touch drag in progress
        if (
            paletteDragState &&
            event.pointerId === paletteDragState.pointerId
        ) {
            const distance = Math.hypot(
                event.clientX - paletteDragState.startClientX,
                event.clientY - paletteDragState.startClientY,
            );
            if (!paletteDragState.moved && distance < 8) return;
            event.preventDefault();
            paletteDragState.moved = true;
            return;
        }

        // 3. Selection Group Drag with Snapping & Shift-axis lock
        if (dragState && event.pointerId === dragState.pointerId) {
            const distance = Math.hypot(
                event.clientX - dragState.startClientX,
                event.clientY - dragState.startClientY,
            );
            if (!dragState.moved && distance < 3) return;

            const point = canvasPoint(event.clientX, event.clientY);
            if (!point) return;
            event.preventDefault();

            let deltaX = point.x - dragState.startPoint.x;
            let deltaY = point.y - dragState.startPoint.y;

            // Shift Axis Lock: constrain to horizontal or vertical dominant axis
            if (event.shiftKey) {
                const locked = lockAxis(deltaX, deltaY);
                deltaX = locked.dx;
                deltaY = locked.dy;
            }

            // Compute aggregate bounding box for selected items to clamp movement
            const selectedBounds = Array.from(
                dragState.startPositions.values(),
            );
            const collectiveBox = getBoundingBox(
                selectedBounds.map((b) => ({
                    x: b.x,
                    y: b.y,
                    width: b.w,
                    height: b.h,
                })),
            );

            let clampedDeltaX = deltaX;
            let clampedDeltaY = deltaY;

            if (collectiveBox) {
                // Clamp collectiveBox so it stays entirely within printable area [0, 0, printableWidth, printableHeight]
                const minDx = -collectiveBox.x;
                const maxDx =
                    printableWidth - (collectiveBox.x + collectiveBox.width);
                const minDy = -collectiveBox.y;
                const maxDy =
                    printableHeight - (collectiveBox.y + collectiveBox.height);

                clampedDeltaX = Math.min(Math.max(deltaX, minDx), maxDx);
                clampedDeltaY = Math.min(Math.max(deltaY, minDy), maxDy);
            }

            // Smart Snap Guides for Primary Dragged Item
            const primaryInitial = dragState.startPositions.get(
                dragState.primaryId,
            );
            if (
                primaryInitial &&
                dragState.otherRects.length > 0 &&
                !event.shiftKey
            ) {
                const candidateRect = {
                    x: primaryInitial.x + clampedDeltaX,
                    y: primaryInitial.y + clampedDeltaY,
                    width: primaryInitial.w,
                    height: primaryInitial.h,
                };
                const snap = calculateSnapGuides(
                    candidateRect,
                    dragState.otherRects,
                    2,
                );
                if (snap.guides.some((g) => g.axis === 'x')) {
                    clampedDeltaX = snap.snappedRect.x - primaryInitial.x;
                }
                if (snap.guides.some((g) => g.axis === 'y')) {
                    clampedDeltaY = snap.snappedRect.y - primaryInitial.y;
                }
                activeSnapGuides = snap.guides;
            } else {
                activeSnapGuides = [];
            }

            dragState.moved = true;

            const nextDoc = {
                ...localDocument,
                components: localDocument.components.map((c) => {
                    const initPos = dragState.startPositions.get(c.id);
                    if (!initPos) return c;
                    return {
                        ...c,
                        x: Number((initPos.x + clampedDeltaX).toFixed(2)),
                        y: Number((initPos.y + clampedDeltaY).toFixed(2)),
                    };
                }),
            };
            localDocument = nextDoc;
            historyEngine.updateGesture(nextDoc);
        }
    }

    // ==========================================
    // Pointer Up Handler
    // ==========================================

    function handlePointerUp(event) {
        activeSnapGuides = [];

        if (resizeState && event.pointerId === resizeState.pointerId) {
            const completedResize = resizeState;
            resizeState = null;
            if (completedResize.moved) {
                historyEngine.commitGesture();
                historyStatus = historyEngine.status();
                emit();
            } else {
                historyEngine.cancelGesture();
            }
            return;
        }

        if (
            paletteDragState &&
            event.pointerId === paletteDragState.pointerId
        ) {
            const completedPaletteDrag = paletteDragState;
            paletteDragState = null;
            if (completedPaletteDrag.moved) {
                const point = canvasPoint(event.clientX, event.clientY);
                if (point) addComponent(completedPaletteDrag.type, point);
                suppressPaletteClick = true;
                setTimeout(() => (suppressPaletteClick = false), 0);
            }
            return;
        }

        if (dragState && event.pointerId === dragState.pointerId) {
            const completedDrag = dragState;
            dragState = null;
            if (completedDrag.moved) {
                historyEngine.commitGesture();
                historyStatus = historyEngine.status();
                emit();
            } else {
                const restored = historyEngine.cancelGesture();
                if (restored) localDocument = clone(restored);
                else localDocument = clone(completedDrag.startDocument);
                if (completedDrag.isAltDuplicate) {
                    selectionEngine.setSelection(
                        completedDrag.startSelectedIds,
                        completedDrag.startPrimaryId,
                    );
                    syncSelectionState('canvas');
                }
            }
        }
    }

    // ==========================================
    // Pointer Cancel / Gesture Escape Revert
    // ==========================================

    function cancelActiveGesture() {
        activeSnapGuides = [];
        if (paletteDragState) paletteDragState = null;
        if (panState) panState = null;
        if (resizeState) {
            const restored = historyEngine.cancelGesture();
            if (restored) localDocument = clone(restored);
            else localDocument = clone(resizeState.startDocument);
            resizeState = null;
            emit();
            return;
        }
        if (dragState) {
            const cancelledDrag = dragState;
            const restored = historyEngine.cancelGesture();
            if (restored) localDocument = clone(restored);
            else localDocument = clone(cancelledDrag.startDocument);
            dragState = null;
            if (cancelledDrag.isAltDuplicate) {
                selectionEngine.setSelection(
                    cancelledDrag.startSelectedIds,
                    cancelledDrag.startPrimaryId,
                );
                syncSelectionState('canvas');
            }
            emit();
            return;
        }
        if (marqueeState) {
            // Keep cancellation side-effect free for contextual UI: restore the
            // published selection without running the opener-aware sync path.
            const cancelledMarquee = marqueeState;
            selectionEngine.setSelection(
                Array.from(cancelledMarquee.initialSelection),
                cancelledMarquee.initialPrimaryId,
            );
            selectedIds = selectionEngine.getSelectedIds();
            primaryId = selectionEngine.getPrimaryId();
            marqueeState = null;
        }
    }

    // ==========================================
    // Focus-Owned Keyboard Commands & Shortcuts
    // ==========================================

    function isEventInsideEditor(event) {
        const target = event.target;
        if (
            target instanceof Element &&
            target.closest('[data-editor-surface]')
        ) {
            return true;
        }
        const active =
            typeof document !== 'undefined' ? document.activeElement : null;
        if (
            active instanceof Element &&
            active.closest('[data-editor-surface]')
        ) {
            return true;
        }
        return false;
    }

    function handleKeydown(event) {
        if (readOnly) return;
        // Keyboard shortcuts normally require editor focus; Space also works while the pointer is over the canvas.
        const insideEditor = isEventInsideEditor(event);
        const allowsCanvasSpace =
            isCanvasHovered && event.code === 'Space' && !isInputFocused(event);
        const hasOpenPanel =
            fullBleed &&
            (inspectorPanelVisible ||
                activeMobilePanel === 'add' ||
                paletteExpanded);
        const allowsEscapeCleanup =
            event.key === 'Escape' &&
            (hasOpenPanel ||
                isSpacePressed ||
                dragState ||
                resizeState ||
                marqueeState ||
                paletteDragState);
        if (!insideEditor && !allowsCanvasSpace && !allowsEscapeCleanup) return;

        // Ignore repeat events for discrete/branching keyboard commands
        const isLayerShortcut =
            ['[', ']', '{', '}'].includes(event.key) ||
            ['BracketLeft', 'BracketRight'].includes(event.code);
        if (
            event.repeat &&
            ([
                'KeyD',
                'KeyZ',
                'KeyY',
                'KeyA',
                'Delete',
                'Backspace',
                'Escape',
            ].includes(event.code) ||
                isLayerShortcut)
        ) {
            return;
        }
        if (event.code === 'Space' && !isInputFocused(event)) {
            isSpacePressed = true;
            if (
                allowsCanvasSpace ||
                event.target === canvasNode ||
                event.target?.closest?.('[data-result-sheet-page]')
            ) {
                event.preventDefault();
            }
        }

        if (event.key === 'Escape' && hasOpenPanel) {
            event.preventDefault();
            isSpacePressed = false;
            const activePanel = document.activeElement?.closest?.(
                '[data-result-sheet-panel]',
            );
            const activePanelName = activePanel?.getAttribute?.(
                'data-result-sheet-panel',
            );
            if (
                activePanelName === 'palette' ||
                activeMobilePanel === 'add' ||
                paletteExpanded
            ) {
                if (belowDesktop) {
                    closeMobileAddPanel();
                } else {
                    paletteState = 'rail';
                    announce('Add components collapsed to rail');
                }
                return;
            }
            closeInspectorPanel();
            announce(
                `${sidebarView === 'layers' ? 'Layers' : 'Inspector'} panel closed`,
            );
            return;
        }

        // Strict Input & IME Isolation: NEVER intercept typing inside inputs, textareas, selects, or contenteditables
        if (isInputFocused(event) || event.isComposing) return;

        const layerDirection =
            event.key === ']' || event.key === '}'
                ? 'forward'
                : event.key === '[' || event.key === '{'
                  ? 'backward'
                  : null;

        // Escape: Cancel in-progress gesture, or clear selection
        if (event.key === 'Escape') {
            isSpacePressed = false;
            if (dragState || resizeState || marqueeState || paletteDragState) {
                event.preventDefault();
                cancelActiveGesture();
                announce('Gesture cancelled');
                return;
            }
            if (selectedIds.size > 0) {
                event.preventDefault();
                deselectAll();
                announce('Selection cleared');
                return;
            }
            return;
        }

        if (dragState || resizeState || paletteDragState) return;

        const mod = isMod(event);

        // Tool hotkeys (V = Select, H = Pan / Hand)
        if (!mod && (event.key === 'v' || event.key === 'V')) {
            canvasTool = 'select';
            announce('Select tool active');
            return;
        }
        if (!mod && (event.key === 'h' || event.key === 'H')) {
            canvasTool = 'pan';
            announce('Pan tool active');
            return;
        }

        // Help Dialog: ? or F1
        if (event.key === '?' || event.key === 'F1') {
            event.preventDefault();
            shortcutHelpOpen = true;
            return;
        }

        // Undo: Mod + Z (without Shift)
        if (
            mod &&
            (event.key === 'z' || event.key === 'Z') &&
            !event.shiftKey
        ) {
            event.preventDefault();
            undo();
            return;
        }

        // Redo: Mod + Shift + Z or Mod + Y
        if (
            (mod &&
                event.shiftKey &&
                (event.key === 'z' || event.key === 'Z')) ||
            (mod && event.key === 'y')
        ) {
            event.preventDefault();
            redo();
            return;
        }

        // Select All: Mod + A
        if (mod && (event.key === 'a' || event.key === 'A')) {
            event.preventDefault();
            selectAllComponents();
            return;
        }

        // Duplicate: Mod + D
        if (mod && (event.key === 'd' || event.key === 'D')) {
            event.preventDefault();
            duplicateSelected();
            return;
        }

        // Lock Toggle: Mod + L
        if (mod && (event.key === 'l' || event.key === 'L')) {
            event.preventDefault();
            toggleSelectedLock();
            return;
        }

        // Layer Stacking Commands (excluding locked items)
        if (mod && selectedIds.size > 0) {
            const unlockedIds = filterUnlocked(
                components,
                Array.from(selectedIds),
            );
            if (unlockedIds.length > 0) {
                if (layerDirection === 'forward') {
                    event.preventDefault();
                    if (event.shiftKey) {
                        const updated = bringToFront(components, unlockedIds);
                        commit(
                            { ...localDocument, components: updated },
                            'Bring layers to front',
                        );
                        announce('Brought to front');
                    } else {
                        const updated = moveUp(components, unlockedIds);
                        commit(
                            { ...localDocument, components: updated },
                            'Move layers forward',
                        );
                        announce('Moved forward');
                    }
                    return;
                }
                if (layerDirection === 'backward') {
                    event.preventDefault();
                    if (event.shiftKey) {
                        const updated = sendToBack(components, unlockedIds);
                        commit(
                            { ...localDocument, components: updated },
                            'Send layers to back',
                        );
                        announce('Sent to back');
                    } else {
                        const updated = moveDown(components, unlockedIds);
                        commit(
                            { ...localDocument, components: updated },
                            'Move layers backward',
                        );
                        announce('Moved backward');
                    }
                    return;
                }
            }
        }

        // Delete: Delete or Backspace
        if (event.key === 'Delete' || event.key === 'Backspace') {
            if (selectedIds.size > 0) {
                event.preventDefault();
                removeSelected();
                return;
            }
        }

        // Alt + Arrow: Keyboard Resize 1mm (normal) or 5mm (with Shift)
        if (
            event.altKey &&
            ['ArrowLeft', 'ArrowRight', 'ArrowUp', 'ArrowDown'].includes(
                event.key,
            ) &&
            selectedIds.size > 0
        ) {
            const unlocked = selectedList.filter((c) => !c.locked);
            if (unlocked.length === 0) return;

            event.preventDefault();
            const resizeStep = event.shiftKey ? 5 : 1;

            const updated = components.map((comp) => {
                if (!selectedIds.has(comp.id) || comp.locked) return comp;
                let w = Number(comp.w) || 20;
                let h = Number(comp.h) || 8;
                if (event.key === 'ArrowRight')
                    w = Math.min(
                        printableWidth - Number(comp.x),
                        w + resizeStep,
                    );
                if (event.key === 'ArrowLeft') w = Math.max(20, w - resizeStep);
                if (event.key === 'ArrowDown')
                    h = Math.min(
                        printableHeight - Number(comp.y),
                        h + resizeStep,
                    );
                if (event.key === 'ArrowUp') h = Math.max(8, h - resizeStep);
                return { ...comp, w, h };
            });

            commit(
                { ...localDocument, components: updated },
                `Resize ${unlocked.length} component(s) by ${resizeStep}mm`,
            );
            announce(
                `Resized ${unlocked.length} component(s) by ${resizeStep}mm`,
            );
            return;
        }
        // Arrow Nudge: 1mm (normal) or 5mm (with Shift)
        const nudgeStep = event.shiftKey ? 5 : 1;
        const delta = {
            ArrowLeft: { x: -nudgeStep, y: 0 },
            ArrowRight: { x: nudgeStep, y: 0 },
            ArrowUp: { x: 0, y: -nudgeStep },
            ArrowDown: { x: 0, y: nudgeStep },
        }[event.key];

        if (delta && selectedIds.size > 0) {
            const unlocked = selectedList.filter((c) => !c.locked);
            if (unlocked.length === 0) return;

            event.preventDefault();

            const updated = components.map((comp) => {
                if (!selectedIds.has(comp.id) || comp.locked) return comp;
                const clamped = clampComponentPosition(
                    Number(comp.x) + delta.x,
                    Number(comp.y) + delta.y,
                    comp.w,
                    comp.h,
                );
                return { ...comp, ...clamped };
            });

            commit(
                { ...localDocument, components: updated },
                `Nudge ${unlocked.length} component(s) by ${nudgeStep}mm`,
            );
            announce(
                `Nudged ${unlocked.length} component(s) by ${nudgeStep}mm`,
            );
        }
    }

    function handleKeyup(event) {
        if (event.code === 'Space') {
            isSpacePressed = false;
        }
    }
    function handleVisibilityChange() {
        isSpacePressed = false;
        cancelActiveGesture();
    }

    function handleInsert({ key, macro, label }) {
        if (!primaryComponent || primaryComponent.type !== 'field') {
            addComponent('field');
            setTimeout(() => {
                const newest = localDocument.components.at(-1);
                if (newest)
                    updateComponent(
                        newest.id,
                        { props: { label, binding: key } },
                        'Insert field binding',
                    );
            }, 0);
        } else {
            updateComponent(
                primaryComponent.id,
                { props: { ...primaryComponent.props, binding: key, label } },
                'Insert field binding',
            );
        }
        if (!recentKeys.includes(key))
            recentKeys = [key, ...recentKeys].slice(0, 8);
        onBinding({ key, macro, label, targetId: primaryId });
    }
</script>

<svelte:document onvisibilitychange={handleVisibilityChange} />
<svelte:window
    onkeydown={handleKeydown}
    onkeyup={handleKeyup}
    onblur={() => {
        isSpacePressed = false;
        cancelActiveGesture();
    }}
    onpointermove={(event) => {
        moveCanvasPanOrMarquee(event);
        handlePointerMove(event);
    }}
    onpointerup={(event) => {
        endCanvasPanOrMarquee(event);
        handlePointerUp(event);
    }}
/>

<!-- Live Screen Reader Announcement Area -->
<div class="sr-only" aria-live="polite" aria-atomic="true">
    {liveAnnouncement}
</div>

<!-- Shortcut Help Modal -->
<ResultSheetShortcutHelp
    open={shortcutHelpOpen}
    onClose={() => (shortcutHelpOpen = false)}
/>

<div
    data-result-sheet-studio
    data-editor-surface
    data-result-sheet-mobile-focus={mobileFocusMode ? 'true' : 'false'}
    data-result-sheet-zoom-mode={zoomMode}
    data-result-sheet-zoom={zoom}
    data-result-sheet-pan-x={panX}
    data-result-sheet-pan-y={panY}
    class={fullBleed
        ? 'bg-studio-shell text-studio-shell-foreground relative flex min-h-0 min-w-0 flex-1 basis-0 flex-col overflow-hidden select-none'
        : 'border-studio-border bg-studio-shell text-studio-shell-foreground min-w-0 space-y-3 rounded-xl border p-3 shadow-inner'}
>
    {#if !builderDocument && !mobileFocusMode}
        <div
            class="border-studio-warning bg-studio-warning text-studio-warning-foreground flex items-start gap-3 rounded-lg border p-3 text-xs"
        >
            <AlertTriangle class="mt-0.5 size-4 shrink-0" />
            <div class="flex-1">
                <strong>Legacy HTML template.</strong> Convert it into the visual
                builder to start editing components; the original HTML remains untouched
                until you save the conversion.
            </div>
            <button
                type="button"
                class="bg-studio-warning-foreground text-studio-warning hover:bg-studio-warning-foreground/90 rounded-md px-2.5 py-1.5 font-semibold transition-colors motion-reduce:transition-none"
                onclick={onConvertLegacy}
            >
                Convert
            </button>
        </div>
    {/if}

    <!-- Generic Studio chrome: canvas tools, history/viewport actions,
       and adapter-supplied actions. -->
    {#if !fullBleed || !belowDesktop || !mobileFocusMode}
        <div
            data-result-sheet-toolbar
            data-result-sheet-floating-toolbar={fullBleed && belowDesktop
                ? 'true'
                : undefined}
            data-result-sheet-toolbar-layout={fullBleed
                ? 'generic-responsive'
                : 'compact-wrap'}
            bind:this={toolbarNode}
            class={fullBleed
                ? belowDesktop
                    ? 'border-studio-border/90 bg-studio-toolbar absolute inset-x-3 top-3 z-20 flex w-auto max-w-full min-w-0 shrink-0 flex-nowrap items-center gap-1.5 overflow-x-auto overflow-y-visible overscroll-contain rounded-xl border p-1.5 max-sm:gap-1'
                    : 'border-studio-border/90 bg-studio-toolbar relative mx-3 flex w-auto max-w-full min-w-0 shrink-0 flex-wrap items-center gap-1.5 overflow-x-hidden overflow-y-visible overscroll-contain rounded-xl border p-1.5 max-sm:gap-1 lg:mx-4 lg:grid lg:grid-cols-[minmax(0,1fr)_auto_minmax(0,1fr)] lg:flex-nowrap'
                : 'border-studio-border bg-studio-toolbar flex flex-wrap items-center justify-between gap-2 rounded-lg border p-1.5 shadow-sm'}
        >
            <div
                data-result-sheet-toolbar-left
                role="group"
                aria-label="Studio navigation and tools"
                class={fullBleed && belowDesktop
                    ? 'order-none flex min-w-0 shrink-0 flex-nowrap items-center gap-1'
                    : 'order-2 flex min-w-0 flex-1 flex-wrap items-center gap-1 max-sm:gap-0.5 lg:order-none lg:col-start-1 lg:flex-nowrap'}
            >
                <!-- Tool Mode Switcher (Select vs Pan) -->
                <div
                    role="group"
                    aria-label="Canvas tools"
                    class="border-studio-border bg-studio-panel-muted flex items-center rounded-md border p-0.5"
                >
                    <button
                        type="button"
                        class={`rounded text-xs transition ${
                            fullBleed && belowDesktop
                                ? 'min-h-11 min-w-11 p-2'
                                : 'p-1.5'
                        } ${
                            canvasTool === 'select' && !isSpacePressed
                                ? 'bg-studio-selection text-studio-selection-foreground font-bold shadow-sm'
                                : 'text-studio-panel-muted-foreground hover:text-studio-panel-foreground'
                        }`}
                        title="Select & Marquee tool (V)"
                        aria-label="Select tool"
                        aria-pressed={canvasTool === 'select' &&
                            !isSpacePressed}
                        onclick={() => (canvasTool = 'select')}
                    >
                        <MousePointer2 class="size-3.5" />
                    </button>
                    <button
                        type="button"
                        class={`rounded text-xs transition ${
                            fullBleed && belowDesktop
                                ? 'min-h-11 min-w-11 p-2'
                                : 'p-1.5'
                        } ${
                            canvasTool === 'pan' || isSpacePressed
                                ? 'bg-studio-selection text-studio-selection-foreground font-bold shadow-sm'
                                : 'text-studio-panel-muted-foreground hover:text-studio-panel-foreground'
                        }`}
                        title="Pan / Hand tool (H or Space+Drag)"
                        aria-label="Pan tool"
                        aria-pressed={canvasTool === 'pan' || isSpacePressed}
                        onclick={() => (canvasTool = 'pan')}
                    >
                        <Hand class="size-3.5" />
                    </button>
                </div>
            </div>
            <div
                data-result-sheet-toolbar-right
                data-result-sheet-toolbar-actions
                role="group"
                aria-label="History and canvas actions"
                class={fullBleed && belowDesktop
                    ? 'order-none flex min-w-0 shrink-0 flex-nowrap items-center justify-end gap-1'
                    : 'order-3 flex min-w-0 flex-1 flex-wrap items-center justify-end gap-1 max-sm:gap-0.5 lg:order-none lg:col-start-3 lg:flex-nowrap'}
            >
                <button
                    type="button"
                    class={`text-studio-toolbar-foreground/80 hover:bg-studio-panel-muted rounded-md px-2 py-1 text-[11px] font-semibold transition-colors disabled:pointer-events-none disabled:opacity-30 motion-reduce:transition-none ${
                        fullBleed && belowDesktop ? 'min-h-11 min-w-11' : ''
                    }`}
                    disabled={!historyStatus.canUndo}
                    title="Undo (Ctrl+Z)"
                    aria-label="Undo"
                    onclick={undo}
                >
                    Undo
                </button>
                <button
                    type="button"
                    class={`text-studio-toolbar-foreground/80 hover:bg-studio-panel-muted rounded-md px-2 py-1 text-[11px] font-semibold transition-colors disabled:pointer-events-none disabled:opacity-30 motion-reduce:transition-none ${
                        fullBleed && belowDesktop ? 'min-h-11 min-w-11' : ''
                    }`}
                    disabled={!historyStatus.canRedo}
                    title="Redo (Ctrl+Shift+Z)"
                    aria-label="Redo"
                    onclick={redo}
                >
                    Redo
                </button>
                <button
                    type="button"
                    class={`text-studio-toolbar-foreground/80 hover:bg-studio-panel-muted rounded-md transition-colors motion-reduce:transition-none ${
                        fullBleed && belowDesktop
                            ? 'min-h-11 min-w-11 p-2'
                            : 'p-1.5'
                    }`}
                    title="Zoom out"
                    aria-label="Zoom out"
                    onclick={() => setManualZoom(effectiveZoom - 0.08)}
                >
                    <Minus class="size-3.5" />
                </button>
                <span
                    class="min-w-12 text-center font-mono text-[11px] font-bold"
                >
                    {Math.round(effectiveZoom * 100)}%
                </span>
                <button
                    type="button"
                    class={`text-studio-toolbar-foreground/80 hover:bg-studio-panel-muted rounded-md transition-colors motion-reduce:transition-none ${
                        fullBleed && belowDesktop
                            ? 'min-h-11 min-w-11 p-2'
                            : 'p-1.5'
                    }`}
                    title="Zoom in"
                    aria-label="Zoom in"
                    onclick={() => setManualZoom(effectiveZoom + 0.08)}
                >
                    <Plus class="size-3.5" />
                </button>
                <button
                    type="button"
                    class={`text-studio-toolbar-foreground/80 hover:bg-studio-panel-muted rounded-md transition-colors motion-reduce:transition-none ${
                        fullBleed && belowDesktop
                            ? 'min-h-11 min-w-11 p-2'
                            : 'p-1.5'
                    }`}
                    title="Fit page"
                    aria-label="Fit page"
                    onclick={handleFit}
                >
                    <Maximize2 class="size-3.5" />
                </button>
                <button
                    type="button"
                    class={`text-studio-toolbar-foreground/80 hover:bg-studio-panel-muted ml-0.5 rounded-md transition-colors motion-reduce:transition-none ${
                        fullBleed && belowDesktop
                            ? 'min-h-11 min-w-11 p-2'
                            : 'p-1.5'
                    }`}
                    title="Keyboard shortcuts (? / F1)"
                    aria-label="Keyboard shortcuts"
                    onclick={() => (shortcutHelpOpen = true)}
                >
                    <HelpCircle class="size-3.5" />
                </button>
                {#if adapterActions}
                    <div
                        data-result-sheet-adapter-actions
                        class="relative shrink-0"
                    >
                        {@render adapterActions()}
                    </div>
                {/if}
            </div>
        </div>
    {/if}

    <div
        data-result-sheet-workspace
        id="result-sheet-studio-view"
        class={fullBleed
            ? belowDesktop
                ? 'relative flex min-h-0 min-w-0 flex-1 basis-0 overflow-hidden px-3 pb-3'
                : inspectorPanelVisible
                  ? paletteExpanded
                      ? 'relative grid min-h-0 min-w-0 flex-1 basis-0 grid-cols-[260px_minmax(0,1fr)_330px] grid-rows-[minmax(0,1fr)] items-stretch gap-3 overflow-hidden px-4 pb-4'
                      : 'relative grid min-h-0 min-w-0 flex-1 basis-0 grid-cols-[88px_minmax(0,1fr)_330px] grid-rows-[minmax(0,1fr)] items-stretch gap-3 overflow-hidden px-4 pb-4'
                  : paletteExpanded
                    ? 'relative grid min-h-0 min-w-0 flex-1 basis-0 grid-cols-[260px_minmax(0,1fr)] grid-rows-[minmax(0,1fr)] items-stretch gap-3 overflow-hidden px-4 pb-4'
                    : 'relative grid min-h-0 min-w-0 flex-1 basis-0 grid-cols-[88px_minmax(0,1fr)] grid-rows-[minmax(0,1fr)] items-stretch gap-3 overflow-hidden px-4 pb-4'
            : 'grid min-w-0 gap-3 lg:grid-cols-[190px_minmax(0,1fr)_280px]'}
    >
        <div
            bind:this={mobileSurfaceNode}
            data-result-sheet-mobile-surface
            data-result-sheet-mobile-surface-panel={activeMobilePanel ??
                'closed'}
            data-result-sheet-mobile-surface-state={mobileSurfaceOpen
                ? 'open'
                : 'closed'}
            data-result-sheet-inspector-snap={mobileFocusMode
                ? mobileInspectorSnap
                : undefined}
            class={fullBleed && belowDesktop && mobileSurfaceOpen
                ? mobileFocusMode
                    ? `absolute inset-x-0 bottom-0 z-40 flex ${
                          mobileInspectorSnap === 'peek'
                              ? 'h-[min(24dvh,13rem)]'
                              : mobileInspectorSnap === 'full'
                                ? 'h-[calc(100dvh-env(safe-area-inset-bottom))]'
                                : 'h-[min(52dvh,32rem)]'
                      } border-studio-border bg-studio-panel min-h-0 flex-col overflow-hidden rounded-t-xl border border-b-0 pb-[env(safe-area-inset-bottom)] shadow-2xl`
                    : 'border-studio-border bg-studio-panel absolute inset-x-3 bottom-3 z-30 flex max-h-[min(62dvh,34rem)] min-h-0 flex-col overflow-hidden rounded-xl border shadow-2xl'
                : fullBleed && belowDesktop
                  ? 'hidden'
                  : 'contents'}
        >
            <aside
                bind:this={palettePanelNode}
                id="result-sheet-component-palette"
                role="complementary"
                aria-label={paletteExpanded
                    ? 'Add components'
                    : 'Add component'}
                data-result-sheet-panel="palette"
                data-result-sheet-panel-state={paletteState}
                data-result-sheet-panel-position={belowDesktop
                    ? 'bottom-surface'
                    : paletteRailOpen
                      ? 'left-rail'
                      : 'left-shelf'}
                data-result-sheet-panel-surface={belowDesktop
                    ? 'bottom-surface'
                    : paletteState}
                data-result-sheet-palette-width={paletteExpanded ? '260' : '88'}
                class={`border-studio-border bg-studio-panel min-h-0 min-w-0 rounded-lg border ${
                    fullBleed
                        ? belowDesktop
                            ? activeMobilePanel === 'add' && paletteExpanded
                                ? 'flex max-h-full min-h-0 w-full flex-col overflow-hidden'
                                : 'hidden'
                            : 'order-first flex h-full w-full flex-col overflow-hidden'
                        : paletteExpanded
                          ? 'order-2 flex w-[260px] max-w-[260px] flex-col overflow-hidden'
                          : 'order-2 flex w-[88px] max-w-[88px] flex-col overflow-hidden'
                }`}
            >
                <div
                    data-result-sheet-palette-header
                    class={`flex min-h-11 shrink-0 items-center ${paletteRailOpen ? 'justify-center' : 'justify-between'} border-studio-border gap-2 border-b p-2.5`}
                >
                    {#if paletteExpanded}
                        <div class="min-w-0">
                            <h3
                                id="result-sheet-component-palette-title"
                                class="truncate text-xs font-bold"
                            >
                                Add components
                            </h3>
                            <p
                                class="text-studio-panel-muted-foreground mt-0.5 text-[10px]"
                            >
                                {paletteCountLabel}
                            </p>
                        </div>
                    {:else}
                        <span class="sr-only">Add components</span>
                    {/if}
                    {#if paletteRailOpen}
                        <button
                            type="button"
                            data-result-sheet-add-component
                            class="border-studio-border bg-studio-panel-muted text-studio-panel-foreground hover:bg-studio-selection/50 focus-visible:ring-studio-focus inline-flex min-h-11 min-w-11 shrink-0 items-center justify-center gap-1.5 rounded-md border px-2 text-[10px] font-semibold focus-visible:ring-2 focus-visible:outline-none"
                            aria-controls="result-sheet-component-palette"
                            aria-expanded="false"
                            aria-label="Add component"
                            title="Add component"
                            onclick={togglePalette}
                        >
                            <Plus class="size-4" aria-hidden="true" />
                            <span class="sr-only">Add component</span>
                        </button>
                    {:else}
                        <button
                            type="button"
                            data-result-sheet-palette-collapse
                            class="border-studio-border text-studio-panel-muted-foreground hover:bg-studio-panel-muted hover:text-studio-panel-foreground focus-visible:ring-studio-focus inline-flex min-h-11 min-w-11 shrink-0 items-center justify-center rounded-md border focus-visible:ring-2 focus-visible:outline-none"
                            aria-controls="result-sheet-component-palette"
                            aria-expanded="true"
                            aria-label={belowDesktop
                                ? 'Close Add components'
                                : 'Collapse Add components to rail'}
                            title={belowDesktop
                                ? 'Close Add components'
                                : 'Collapse Add components to rail'}
                            onclick={togglePalette}
                        >
                            <ChevronLeft class="size-4" aria-hidden="true" />
                        </button>
                    {/if}
                </div>

                {#if paletteExpanded}
                    <label class="relative block shrink-0 p-2.5">
                        <span class="sr-only">Search component types</span>
                        <Search
                            class="text-studio-panel-muted-foreground pointer-events-none absolute top-1/2 left-5 size-3.5 -translate-y-1/2"
                            aria-hidden="true"
                        />
                        <input
                            bind:value={paletteQuery}
                            type="search"
                            class="border-studio-border bg-studio-panel-muted text-studio-panel-foreground focus-visible:ring-studio-focus min-h-11 w-full rounded-md border pr-2 pl-8 text-xs outline-none focus-visible:ring-2"
                            placeholder="Search component types"
                            aria-label="Search component types"
                        />
                    </label>
                {/if}

                <div
                    class={`min-h-0 flex-1 overflow-y-auto p-2 ${paletteExpanded ? 'space-y-3' : 'flex flex-col items-center gap-1.5'}`}
                >
                    {#if paletteExpanded}
                        {#each paletteGroups as [category, entries] (category)}
                            <section
                                class="space-y-1.5"
                                aria-labelledby={`result-sheet-palette-category-${category}`}
                            >
                                <h4
                                    id={`result-sheet-palette-category-${category}`}
                                    class="text-studio-panel-muted-foreground px-1 text-[10px] font-bold tracking-wider uppercase"
                                >
                                    {category}
                                </h4>
                                {#each entries as [type, item] (type)}
                                    {@const Icon = componentIcon(type)}
                                    <button
                                        type="button"
                                        data-result-sheet-palette={type}
                                        data-result-sheet-palette-state={paletteState}
                                        draggable="true"
                                        onpointerdown={(event) =>
                                            beginPaletteDrag(event, type)}
                                        onpointermove={handlePointerMove}
                                        onpointerup={handlePointerUp}
                                        onpointercancel={cancelActiveGesture}
                                        ondragstart={(event) =>
                                            handlePaletteDragStart(event, type)}
                                        onclick={(event) =>
                                            handlePaletteClick(event, type)}
                                        class="group border-studio-border bg-studio-panel hover:border-studio-focus hover:bg-studio-selection/40 focus-visible:ring-studio-focus flex min-h-11 w-full touch-none items-start gap-2 rounded-md border p-2 text-left transition-colors focus-visible:ring-2 focus-visible:outline-none motion-reduce:transition-none"
                                        aria-label={item.label}
                                    >
                                        <Icon
                                            class="text-studio-focus mt-0.5 size-4 shrink-0"
                                            aria-hidden="true"
                                        />
                                        <span class="min-w-0 block text-[11px] leading-tight font-semibold">{item.label}</span>
                                    </button>
                                {/each}
                            </section>
                        {:else}
                            <p
                                class="text-studio-panel-muted-foreground p-3 text-center text-[11px]"
                            >
                                No component types match this search.
                            </p>
                        {/each}
                    {:else}
                        {#each paletteEntries as [type, item] (type)}
                            {@const Icon = componentIcon(type)}
                            <button
                                type="button"
                                data-result-sheet-palette={type}
                                data-result-sheet-palette-state={paletteState}
                                draggable="true"
                                onpointerdown={(event) =>
                                    beginPaletteDrag(event, type)}
                                onpointermove={handlePointerMove}
                                onpointerup={handlePointerUp}
                                onpointercancel={cancelActiveGesture}
                                ondragstart={(event) =>
                                    handlePaletteDragStart(event, type)}
                                onclick={(event) =>
                                    handlePaletteClick(event, type)}
                                class="group border-studio-border bg-studio-panel hover:border-studio-focus hover:bg-studio-selection/40 focus-visible:ring-studio-focus flex min-h-11 min-w-11 touch-none items-center justify-center rounded-md border p-2 transition-colors focus-visible:ring-2 focus-visible:outline-none motion-reduce:transition-none"
                                aria-label={item.label}
                                title={item.label}
                            >
                                <Icon
                                    class="text-studio-focus size-4"
                                    aria-hidden="true"
                                />
                            </button>
                        {/each}
                    {/if}
                </div>
            </aside>
            <aside
                bind:this={inspectorPanelNode}
                id="result-sheet-inspector"
                tabindex="-1"
                role="complementary"
                data-result-sheet-panel="inspector"
                data-result-sheet-panel-state={inspectorPanelVisible
                    ? 'expanded'
                    : 'closed'}
                data-result-sheet-panel-position={belowDesktop
                    ? mobileFocusMode
                        ? 'bottom-sheet'
                        : 'bottom-surface'
                    : 'desktop-rail'}
                data-result-sheet-panel-surface={belowDesktop
                    ? mobileFocusMode
                        ? 'bottom-sheet'
                        : 'bottom-surface'
                    : 'rail'}
                data-result-sheet-inspector-snap={mobileFocusMode
                    ? mobileInspectorSnap
                    : undefined}
                class={`border-studio-border bg-studio-panel flex min-h-0 min-w-0 flex-col space-y-3 rounded-lg border p-3 ${
                    fullBleed
                        ? belowDesktop
                            ? activeMobilePanel === 'inspector' ||
                              activeMobilePanel === 'layers'
                                ? mobileFocusMode
                                    ? 'flex h-full min-h-0 w-full overflow-y-auto overscroll-contain'
                                    : 'flex max-h-full min-h-0 w-full overflow-y-auto overscroll-contain'
                                : 'hidden'
                            : 'order-3 h-full w-[330px] overflow-y-auto'
                        : 'order-3 lg:static lg:flex lg:flex-col'
                } ${inspectorPanelVisible ? 'flex' : 'hidden'}`}
            >
                <div
                    class="border-studio-border flex shrink-0 items-center justify-between border-b pb-2"
                >
                    {#if belowDesktop && mobileFocusMode && sidebarView === 'inspector'}
                        <div
                            data-result-sheet-inspector-snap-controls
                            role="group"
                            aria-label="Inspector sheet size"
                            class="flex shrink-0 items-center gap-1"
                        >
                            {#each ['peek', 'half', 'full'] as snap}
                                <button
                                    type="button"
                                    data-result-sheet-inspector-snap={snap}
                                    class={`focus-visible:ring-studio-focus min-h-11 min-w-11 rounded-md border px-2 text-[10px] font-semibold transition-colors focus-visible:ring-2 focus-visible:outline-none ${
                                        mobileInspectorSnap === snap
                                            ? 'border-studio-focus bg-studio-selection text-studio-selection-foreground'
                                            : 'border-studio-border text-studio-panel-muted-foreground hover:bg-studio-panel-muted'
                                    }`}
                                    aria-label={`${snap[0].toUpperCase()}${snap.slice(1)} inspector sheet`}
                                    aria-pressed={mobileInspectorSnap === snap}
                                    onclick={() => setMobileInspectorSnap(snap)}
                                >
                                    {snap[0].toUpperCase() + snap.slice(1)}
                                </button>
                            {/each}
                        </div>
                    {/if}
                    <div
                        role="tablist"
                        aria-label="Studio panels"
                        class="flex items-center gap-1"
                    >
                        <span id="result-sheet-inspector-title" class="sr-only">
                            Result sheet {sidebarView === 'layers'
                                ? 'layers'
                                : 'inspector'} panel
                        </span>
                        <button
                            type="button"
                            role="tab"
                            class={`focus-visible:ring-studio-focus rounded px-2 py-1 text-xs font-bold transition focus-visible:ring-2 focus-visible:outline-none ${
                                sidebarView === 'inspector'
                                    ? 'bg-studio-selection text-studio-selection-foreground'
                                    : 'text-studio-panel-muted-foreground hover:bg-studio-panel-muted'
                            }`}
                            aria-selected={sidebarView === 'inspector'}
                            aria-pressed={sidebarView === 'inspector'}
                            aria-controls="result-sheet-inspector"
                            onclick={() => {
                                sidebarView = 'inspector';
                                activeMobilePanel = 'inspector';
                            }}
                        >
                            Inspector
                        </button>
                        <button
                            type="button"
                            role="tab"
                            class={`focus-visible:ring-studio-focus flex items-center gap-1 rounded px-2 py-1 text-xs font-bold transition focus-visible:ring-2 focus-visible:outline-none ${
                                sidebarView === 'layers'
                                    ? 'bg-studio-selection text-studio-selection-foreground'
                                    : 'text-studio-panel-muted-foreground hover:bg-studio-panel-muted'
                            }`}
                            aria-selected={sidebarView === 'layers'}
                            aria-pressed={sidebarView === 'layers'}
                            aria-controls="result-sheet-inspector"
                            onclick={() => {
                                sidebarView = 'layers';
                                activeMobilePanel = 'layers';
                            }}
                        >
                            <LayersIcon class="size-3" />
                            <span>Layers</span>
                            <span
                                class="text-studio-panel-muted-foreground font-mono text-[10px]"
                                >({components.length} placed)</span
                            >
                        </button>
                    </div>
                    <button
                        type="button"
                        data-result-sheet-panel-close="inspector"
                        class="border-studio-border text-studio-panel-muted-foreground hover:bg-studio-panel-muted focus-visible:ring-studio-focus rounded border px-2 py-1 text-[10px] font-semibold focus-visible:ring-2 focus-visible:outline-none"
                        aria-label={`Close ${sidebarView === 'layers' ? 'layers' : 'inspector'} panel`}
                        onclick={closeInspectorPanel}
                    >
                        Close
                    </button>
                </div>
                {#if sidebarView === 'layers'}
                    <!-- Layers Panel View -->
                    <ResultSheetLayersPanel
                        components={layerComponents}
                        {selectedIds}
                        {primaryId}
                        {studioCatalog}
                        onSelect={(id, selection = {}) => {
                            const additive =
                                typeof selection === 'boolean'
                                    ? selection
                                    : selection.additive;
                            if (additive) selectionEngine.toggle(id);
                            else selectionEngine.select(id);
                            syncSelectionState('layers');
                        }}
                        onCommit={(updated, label) =>
                            commit(
                                { ...localDocument, components: updated },
                                label,
                            )}
                        onDelete={removeSelected}
                        onAnnounce={announce}
                    />
                {:else}
                    <!-- Inspector View -->
                    {#if selectedCount > 1}
                        <!-- Multi-selection Summary & Batch Toolbars -->
                        <div class="space-y-3">
                            <div
                                class="bg-studio-success text-studio-success-foreground border-studio-success flex items-center justify-between rounded-lg border p-2.5"
                            >
                                <div class="flex items-center gap-2">
                                    <span
                                        class="bg-studio-success-foreground size-2 rounded-full"
                                    ></span>
                                    <span class="text-xs font-bold"
                                        >{selectedCount} components selected</span
                                    >
                                </div>
                                <div class="flex items-center gap-1">
                                    <button
                                        type="button"
                                        class="text-studio-success-foreground hover:bg-studio-panel rounded p-1"
                                        title="Duplicate selected (Ctrl+D)"
                                        onclick={duplicateSelected}
                                    >
                                        <CopyPlus class="size-3.5" />
                                    </button>
                                    <button
                                        type="button"
                                        class="text-studio-success-foreground hover:bg-studio-panel rounded p-1"
                                        title="Toggle lock (Ctrl+L)"
                                        onclick={toggleSelectedLock}
                                    >
                                        <Lock class="size-3.5" />
                                    </button>
                                    <button
                                        type="button"
                                        class="text-destructive hover:bg-destructive/10 rounded p-1"
                                        title="Delete selected (Delete)"
                                        onclick={removeSelected}
                                    >
                                        <Trash2 class="size-3.5" />
                                    </button>
                                </div>
                            </div>

                            <!-- Alignment Toolbar -->
                            <div
                                class="border-studio-border space-y-1.5 border-t pt-2"
                            >
                                <span
                                    class="text-studio-panel-muted-foreground block text-[10px] font-bold tracking-wider uppercase"
                                >
                                    Align edges
                                </span>
                                <div
                                    class="border-studio-border bg-studio-panel-muted flex items-center justify-between gap-1 rounded-lg border p-1"
                                >
                                    <button
                                        type="button"
                                        class="text-studio-panel-muted-foreground hover:bg-studio-panel flex-1 rounded p-1 hover:shadow-sm"
                                        title="Align left"
                                        aria-label="Align left"
                                        onclick={() => alignSelected('left')}
                                    >
                                        <AlignHorizontalJustifyStart
                                            class="mx-auto size-3.5"
                                        />
                                    </button>
                                    <button
                                        type="button"
                                        class="text-studio-panel-muted-foreground hover:bg-studio-panel flex-1 rounded p-1 hover:shadow-sm"
                                        title="Align horizontal center"
                                        aria-label="Align horizontal center"
                                        onclick={() => alignSelected('center')}
                                    >
                                        <AlignHorizontalJustifyCenter
                                            class="mx-auto size-3.5"
                                        />
                                    </button>
                                    <button
                                        type="button"
                                        class="text-studio-panel-muted-foreground hover:bg-studio-panel flex-1 rounded p-1 hover:shadow-sm"
                                        title="Align right"
                                        aria-label="Align right"
                                        onclick={() => alignSelected('right')}
                                    >
                                        <AlignHorizontalJustifyEnd
                                            class="mx-auto size-3.5"
                                        />
                                    </button>
                                    <div
                                        class="bg-studio-border h-4 w-px"
                                    ></div>
                                    <button
                                        type="button"
                                        class="text-studio-panel-muted-foreground hover:bg-studio-panel flex-1 rounded p-1 hover:shadow-sm"
                                        title="Align top"
                                        aria-label="Align top"
                                        onclick={() => alignSelected('top')}
                                    >
                                        <AlignVerticalJustifyStart
                                            class="mx-auto size-3.5"
                                        />
                                    </button>
                                    <button
                                        type="button"
                                        class="text-studio-panel-muted-foreground hover:bg-studio-panel flex-1 rounded p-1 hover:shadow-sm"
                                        title="Align vertical middle"
                                        aria-label="Align vertical middle"
                                        onclick={() => alignSelected('middle')}
                                    >
                                        <AlignVerticalJustifyCenter
                                            class="mx-auto size-3.5"
                                        />
                                    </button>
                                    <button
                                        type="button"
                                        class="text-studio-panel-muted-foreground hover:bg-studio-panel flex-1 rounded p-1 hover:shadow-sm"
                                        title="Align bottom"
                                        aria-label="Align bottom"
                                        onclick={() => alignSelected('bottom')}
                                    >
                                        <AlignVerticalJustifyEnd
                                            class="mx-auto size-3.5"
                                        />
                                    </button>
                                </div>
                            </div>

                            <!-- Distribution Toolbar (3+ selected) -->
                            {#if selectedCount >= 3}
                                <div
                                    class="border-studio-border space-y-1.5 border-t pt-2"
                                >
                                    <span
                                        class="text-studio-panel-muted-foreground block text-[10px] font-bold tracking-wider uppercase"
                                    >
                                        Distribute spacing
                                    </span>
                                    <div
                                        class="border-studio-border bg-studio-panel-muted flex items-center gap-1 rounded-lg border p-1"
                                    >
                                        <button
                                            type="button"
                                            class="text-studio-panel-foreground hover:bg-studio-panel flex flex-1 items-center justify-center gap-1.5 rounded py-1 text-[10px] font-semibold hover:shadow-sm"
                                            onclick={() =>
                                                distributeSelected(
                                                    'horizontal',
                                                )}
                                        >
                                            <AlignHorizontalDistributeCenter
                                                class="size-3.5"
                                            />
                                            <span>Horizontal</span>
                                        </button>
                                        <button
                                            type="button"
                                            class="text-studio-panel-foreground hover:bg-studio-panel flex flex-1 items-center justify-center gap-1.5 rounded py-1 text-[10px] font-semibold hover:shadow-sm"
                                            onclick={() =>
                                                distributeSelected('vertical')}
                                        >
                                            <AlignVerticalDistributeCenter
                                                class="size-3.5"
                                            />
                                            <span>Vertical</span>
                                        </button>
                                    </div>
                                </div>
                            {/if}
                        </div>
                    {/if}

                    <!-- Primary Selected Component Inspector -->
                    {#if primaryComponent}
                        <div class="space-y-3">
                            <div
                                class="border-studio-border flex items-center justify-between gap-2 border-b pb-2"
                            >
                                <div class="flex min-w-0 items-center gap-1.5">
                                    <span
                                        class="bg-studio-success size-2 rounded-full"
                                    ></span>
                                    <h3 class="truncate text-xs font-bold">
                                        {componentLabel(primaryComponent.type)}
                                    </h3>
                                    {#if primaryComponent.locked}
                                        <span
                                            class="bg-studio-warning text-studio-warning-foreground rounded px-1.5 py-0.5 text-[9px] font-semibold"
                                        >
                                            Locked
                                        </span>
                                    {/if}
                                </div>
                                <div class="flex items-center gap-0.5">
                                    <button
                                        type="button"
                                        class="text-studio-panel-foreground/80 hover:bg-studio-panel-muted rounded p-1"
                                        title="Duplicate (Ctrl+D)"
                                        onclick={duplicateSelected}
                                    >
                                        <CopyPlus class="size-3.5" />
                                    </button>
                                    <button
                                        type="button"
                                        class="text-studio-panel-foreground/80 hover:bg-studio-panel-muted rounded p-1"
                                        title={primaryComponent.locked
                                            ? 'Unlock component'
                                            : 'Lock component'}
                                        onclick={toggleSelectedLock}
                                    >
                                        {#if primaryComponent.locked}
                                            <Lock
                                                class="text-studio-warning-foreground size-3.5"
                                            />
                                        {:else}
                                            <Unlock class="size-3.5" />
                                        {/if}
                                    </button>
                                    <button
                                        type="button"
                                        class="text-studio-panel-foreground hover:bg-destructive/10 hover:text-destructive rounded p-1"
                                        title="Delete component (Delete)"
                                        onclick={removeSelected}
                                    >
                                        <Trash2 class="size-3.5" />
                                    </button>
                                </div>
                            </div>
                            <!-- Millimetre Geometry Controls using NumericControl with stable coalesced updates -->
                            <div class="grid grid-cols-2 gap-2">
                                <NumericControl
                                    label="X position"
                                    min={0}
                                    max={Math.max(
                                        0,
                                        printableWidth -
                                            Number(primaryComponent.w),
                                    )}
                                    step={0.5}
                                    unit="mm"
                                    disabled={primaryIsLocked}
                                    value={Number(primaryComponent.x)}
                                    oninput={(val) =>
                                        updateComponentCoalesced(
                                            primaryComponent.id,
                                            { x: val },
                                            'x',
                                            'Move X',
                                        )}
                                    oncommit={(val) =>
                                        updateComponentCoalesced(
                                            primaryComponent.id,
                                            { x: val },
                                            'x',
                                            'Move X',
                                        )}
                                />
                                <NumericControl
                                    label="Y position"
                                    min={0}
                                    max={Math.max(
                                        0,
                                        printableHeight -
                                            Number(primaryComponent.h),
                                    )}
                                    step={0.5}
                                    unit="mm"
                                    disabled={primaryIsLocked}
                                    value={Number(primaryComponent.y)}
                                    oninput={(val) =>
                                        updateComponentCoalesced(
                                            primaryComponent.id,
                                            { y: val },
                                            'y',
                                            'Move Y',
                                        )}
                                    oncommit={(val) =>
                                        updateComponentCoalesced(
                                            primaryComponent.id,
                                            { y: val },
                                            'y',
                                            'Move Y',
                                        )}
                                />
                                <NumericControl
                                    label="Width"
                                    min={20}
                                    max={printableWidth}
                                    step={0.5}
                                    unit="mm"
                                    disabled={primaryIsLocked}
                                    value={Number(primaryComponent.w)}
                                    oninput={(val) =>
                                        updateComponentCoalesced(
                                            primaryComponent.id,
                                            { w: val },
                                            'w',
                                            'Resize width',
                                        )}
                                    oncommit={(val) =>
                                        updateComponentCoalesced(
                                            primaryComponent.id,
                                            { w: val },
                                            'w',
                                            'Resize width',
                                        )}
                                />
                                <NumericControl
                                    label="Height"
                                    min={8}
                                    max={printableHeight}
                                    step={0.5}
                                    unit="mm"
                                    disabled={primaryIsLocked}
                                    value={Number(primaryComponent.h)}
                                    oninput={(val) =>
                                        updateComponentCoalesced(
                                            primaryComponent.id,
                                            { h: val },
                                            'h',
                                            'Resize height',
                                        )}
                                    oncommit={(val) =>
                                        updateComponentCoalesced(
                                            primaryComponent.id,
                                            { h: val },
                                            'h',
                                            'Resize height',
                                        )}
                                />
                            </div>

                            <button
                                type="button"
                                disabled={primaryIsLocked}
                                class="border-studio-success bg-studio-success/30 text-studio-success-foreground hover:bg-studio-success/50 w-full rounded border px-2 py-1.5 text-[10px] font-semibold transition-colors disabled:pointer-events-none disabled:opacity-40 motion-reduce:transition-none"
                                onclick={fitSelectedHeight}
                            >
                                Fit height to content
                            </button>

                            {#if primaryComponent.type === 'data_table' || primaryComponent.type === 'kpi_grid'}
                                <section class="border-studio-border bg-studio-panel-muted/40 space-y-3 rounded-lg border p-3">
                                    <div>
                                        <h3 class="text-studio-panel-foreground text-[11px] font-bold">
                                            {primaryComponent.type === 'data_table' ? 'Data & Columns' : 'Summary Metrics'}
                                        </h3>
                                        <p class="text-studio-panel-muted-foreground mt-0.5 text-[10px]">
                                            Configure visible values and their order.
                                        </p>
                                    </div>

                                    {#if primaryComponent.type === 'data_table'}
                                        <label class="text-studio-panel-muted-foreground block text-[10px]">
                                            Repeat Dataset Key
                                            <input
                                                disabled={readOnly || primaryIsLocked}
                                                class="border-studio-border bg-studio-panel text-studio-panel-foreground disabled:bg-studio-panel-muted mt-1 w-full rounded border px-2 py-1.5 text-xs disabled:opacity-70"
                                                value={primaryComponent.props?.repeat_key ?? 'rows'}
                                                oninput={(event) => updateComponentCoalesced(
                                                    primaryComponent.id,
                                                    { props: { ...primaryComponent.props, repeat_key: event.currentTarget.value } },
                                                    'props.repeat_key',
                                                    'Update repeat dataset key',
                                                )}
                                            />
                                        </label>
                                        <div class="space-y-2">
                                            <p class="text-studio-panel-muted-foreground text-[10px] font-semibold">Table Columns &amp; Order</p>
                                            {#each tableColumnsForInspector(primaryComponent) as column, index (`${column.binding ?? column.key}`)}
                                                {@const columnKey = column.binding ?? column.key}
                                                <div class="border-studio-border bg-studio-panel grid grid-cols-[auto_minmax(0,1fr)_auto] items-center gap-2 rounded-md border p-2">
                                                    <input
                                                        aria-label={`Show ${column.label || columnKey} column`}
                                                        type="checkbox"
                                                        disabled={readOnly || primaryIsLocked}
                                                        checked={column.checked}
                                                        onchange={(event) => {
                                                            const nextColumns = tableColumnsForInspector(primaryComponent).map((item) =>
                                                                (item.binding ?? item.key) === columnKey ? { ...item, checked: event.currentTarget.checked } : item,
                                                            );
                                                            updateComponentCoalesced(
                                                                primaryComponent.id,
                                                                { props: { ...primaryComponent.props, columns: nextColumns } },
                                                                'props.columns',
                                                                'Update table columns',
                                                            );
                                                        }}
                                                    />
                                                    <input
                                                        aria-label={`Header label for ${columnKey}`}
                                                        disabled={readOnly || primaryIsLocked}
                                                        class="border-studio-border bg-studio-panel text-studio-panel-foreground min-w-0 rounded border px-1.5 py-1 text-[10px] disabled:opacity-70"
                                                        value={column.label}
                                                        oninput={(event) => {
                                                            const nextColumns = tableColumnsForInspector(primaryComponent).map((item) =>
                                                                (item.binding ?? item.key) === columnKey ? { ...item, label: event.currentTarget.value } : item,
                                                            );
                                                            updateComponentCoalesced(
                                                                primaryComponent.id,
                                                                { props: { ...primaryComponent.props, columns: nextColumns } },
                                                                'props.columns',
                                                                'Update table columns',
                                                            );
                                                        }}
                                                    />
                                                    <div class="flex gap-1">
                                                        <button
                                                            type="button"
                                                            aria-label={`Move ${column.label || columnKey} up`}
                                                            title="Move Up"
                                                            disabled={readOnly || primaryIsLocked || index === 0}
                                                            class="border-studio-border bg-studio-panel text-studio-panel-foreground hover:bg-studio-panel-muted rounded border px-1.5 py-1 text-[10px] disabled:opacity-40"
                                                            onclick={() => updateComponentCoalesced(
                                                                primaryComponent.id,
                                                                { props: { ...primaryComponent.props, columns: moveInspectorItem(tableColumnsForInspector(primaryComponent), index, -1) } },
                                                                'props.columns',
                                                                'Update table columns',
                                                            )}
                                                        >↑</button>
                                                        <button
                                                            type="button"
                                                            aria-label={`Move ${column.label || columnKey} down`}
                                                            title="Move Down"
                                                            disabled={readOnly || primaryIsLocked || index === tableColumnsForInspector(primaryComponent).length - 1}
                                                            class="border-studio-border bg-studio-panel text-studio-panel-foreground hover:bg-studio-panel-muted rounded border px-1.5 py-1 text-[10px] disabled:opacity-40"
                                                            onclick={() => updateComponentCoalesced(
                                                                primaryComponent.id,
                                                                { props: { ...primaryComponent.props, columns: moveInspectorItem(tableColumnsForInspector(primaryComponent), index, 1) } },
                                                                'props.columns',
                                                                'Update table columns',
                                                            )}
                                                        >↓</button>
                                                    </div>
                                                </div>
                                            {:else}
                                                <p class="text-studio-panel-muted-foreground rounded-md border border-dashed p-2 text-[10px]">No table columns are available.</p>
                                            {/each}
                                        </div>
                                    {:else}
                                        <div class="space-y-2">
                                            <p class="text-studio-panel-muted-foreground text-[10px] font-semibold">Summary Metrics &amp; Order</p>
                                            {#each kpiMetricsForInspector(primaryComponent) as metric, index (metric.binding)}
                                                <div class="border-studio-border bg-studio-panel grid grid-cols-[auto_minmax(0,1fr)_auto] items-center gap-2 rounded-md border p-2">
                                                    <input
                                                        aria-label={`Show ${metric.label || metric.binding} metric`}
                                                        type="checkbox"
                                                        disabled={readOnly || primaryIsLocked}
                                                        checked={metric.checked}
                                                        onchange={(event) => {
                                                            const nextMetrics = kpiMetricsForInspector(primaryComponent).map((item) =>
                                                                item.binding === metric.binding ? { ...item, checked: event.currentTarget.checked } : item,
                                                            );
                                                            updateComponentCoalesced(
                                                                primaryComponent.id,
                                                                { props: { ...primaryComponent.props, metrics: nextMetrics } },
                                                                'props.metrics',
                                                                'Update KPI metrics',
                                                            );
                                                        }}
                                                    />
                                                    <input
                                                        aria-label={`Label for ${metric.binding} metric`}
                                                        disabled={readOnly || primaryIsLocked}
                                                        class="border-studio-border bg-studio-panel text-studio-panel-foreground min-w-0 rounded border px-1.5 py-1 text-[10px] disabled:opacity-70"
                                                        value={metric.label}
                                                        oninput={(event) => {
                                                            const nextMetrics = kpiMetricsForInspector(primaryComponent).map((item) =>
                                                                item.binding === metric.binding ? { ...item, label: event.currentTarget.value } : item,
                                                            );
                                                            updateComponentCoalesced(
                                                                primaryComponent.id,
                                                                { props: { ...primaryComponent.props, metrics: nextMetrics } },
                                                                'props.metrics',
                                                                'Update KPI metrics',
                                                            );
                                                        }}
                                                    />
                                                    <div class="flex gap-1">
                                                        <button
                                                            type="button"
                                                            aria-label={`Move ${metric.label || metric.binding} up`}
                                                            title="Move Up"
                                                            disabled={readOnly || primaryIsLocked || index === 0}
                                                            class="border-studio-border bg-studio-panel text-studio-panel-foreground hover:bg-studio-panel-muted rounded border px-1.5 py-1 text-[10px] disabled:opacity-40"
                                                            onclick={() => updateComponentCoalesced(
                                                                primaryComponent.id,
                                                                { props: { ...primaryComponent.props, metrics: moveInspectorItem(kpiMetricsForInspector(primaryComponent), index, -1) } },
                                                                'props.metrics',
                                                                'Update KPI metrics',
                                                            )}
                                                        >↑</button>
                                                        <button
                                                            type="button"
                                                            aria-label={`Move ${metric.label || metric.binding} down`}
                                                            title="Move Down"
                                                            disabled={readOnly || primaryIsLocked || index === kpiMetricsForInspector(primaryComponent).length - 1}
                                                            class="border-studio-border bg-studio-panel text-studio-panel-foreground hover:bg-studio-panel-muted rounded border px-1.5 py-1 text-[10px] disabled:opacity-40"
                                                            onclick={() => updateComponentCoalesced(
                                                                primaryComponent.id,
                                                                { props: { ...primaryComponent.props, metrics: moveInspectorItem(kpiMetricsForInspector(primaryComponent), index, 1) } },
                                                                'props.metrics',
                                                                'Update KPI metrics',
                                                            )}
                                                        >↓</button>
                                                    </div>
                                                </div>
                                            {:else}
                                                <p class="text-studio-panel-muted-foreground rounded-md border border-dashed p-2 text-[10px]">No summary metrics are available.</p>
                                            {/each}
                                        </div>
                                    {/if}
                                </section>
                            {/if}

                            {#if primaryComponent.type === 'field' || primaryComponent.type === 'signature'}
                                <button
                                    type="button"
                                    disabled={primaryIsLocked}
                                    class="border-studio-success bg-studio-success/30 text-studio-success-foreground hover:bg-studio-success/50 flex w-full items-center justify-between rounded-md border px-2.5 py-2 text-left text-xs font-semibold transition-colors disabled:pointer-events-none disabled:opacity-40 motion-reduce:transition-none"
                                    onclick={() =>
                                        onOpenPlaceholders({
                                            mode: 'insert',
                                            targetId: primaryComponent.id,
                                        })}
                                >
                                    <span>
                                        {primaryComponent.props?.binding
                                            ? `{{${primaryComponent.props.binding}}}`
                                            : 'Choose binding'}
                                    </span>
                                    <Copy class="size-3.5" />
                                </button>
                            {/if}

                            {#if primaryComponent.type === 'image' && allowNoticeDocumentImages}
                                <label class="block text-[10px]">
                                    Verified document image
                                    <select
                                        disabled={readOnly || primaryIsLocked || noticeImageUploading}
                                        class="border-studio-border bg-studio-panel text-studio-panel-foreground mt-1 w-full rounded border px-1.5 py-1 text-xs disabled:opacity-70"
                                        value={primaryComponent.props?.asset_id ?? ''}
                                        onchange={(event) => {
                                            const asset = noticeDocumentImageAssets.find((item) => item.asset_id === event.currentTarget.value);
                                            if (asset) updateSelectedProps({ asset_id: asset.asset_id, src: asset.src }, 'Choose verified document image');
                                        }}
                                    >
                                        {#if !noticeDocumentImageAssets.some((item) => item.asset_id === primaryComponent.props?.asset_id)}<option value="">Choose a verified image</option>{/if}
                                        {#each noticeDocumentImageAssets as asset (asset.asset_id)}<option value={asset.asset_id}>{asset.label ?? `Image ${asset.asset_id.slice(0, 8)}`}</option>{/each}
                                    </select>
                                </label>
                                <label class="block text-[10px]">Alternative text<input class="border-studio-border bg-studio-panel text-studio-panel-foreground mt-1 w-full rounded border px-1.5 py-1 text-xs" disabled={primaryIsLocked} value={primaryComponent.props?.alt ?? ''} oninput={(event) => updateSelectedProps({ alt: event.currentTarget.value }, 'Update image alternative text')} /></label>
                                {#if onNoticeDocumentImageUpload}
                                    <label class="block text-[10px]">Upload verified PNG/JPEG (up to 2 MiB)
                                        <input aria-label="Upload image for selected document component" type="file" accept="image/png,image/jpeg,.png,.jpg,.jpeg" disabled={readOnly || primaryIsLocked || noticeImageUploading} onchange={uploadInspectorImage} class="mt-1 block w-full min-w-0 rounded border p-1 text-xs" />
                                    </label>
                                {/if}
                                {#if noticeImageUploading}<p role="status" class="text-xs">Validating image…</p>{/if}
                                {#if noticeImageError}<p role="alert" class="text-xs">{noticeImageError}</p>{/if}
                                {#if !noticeDocumentImageAssets.some((asset) => asset.asset_id === primaryComponent.props?.asset_id)}<p role="alert" class="text-xs">This image is unverified. Choose or upload a same-family verified image, or remove it before checking the PDF.</p>{/if}
                            {/if}

                            {#if ['dynamic_image', 'dynamic_qr'].includes(primaryComponent.type)}
                                <label class="block text-[10px]">
                                    Data binding
                                    <input
                                        disabled={primaryIsLocked}
                                        class="border-studio-border bg-studio-panel text-studio-panel-foreground disabled:bg-studio-panel-muted mt-1 w-full rounded border px-1.5 py-1 text-xs disabled:opacity-70"
                                        value={primaryComponent.props
                                            ?.binding ?? ''}
                                        oninput={(event) =>
                                            updateSelectedProps(
                                                {
                                                    binding:
                                                        event.currentTarget
                                                            .value,
                                                },
                                                'Update data binding',
                                            )}
                                    />
                                </label>
                                {#if primaryComponent.type === 'dynamic_image'}
                                    <label class="block text-[10px]">
                                        Fit
                                        <select
                                            disabled={primaryIsLocked}
                                            class="border-studio-border bg-studio-panel text-studio-panel-foreground disabled:bg-studio-panel-muted mt-1 w-full rounded border px-1.5 py-1 text-xs disabled:opacity-70"
                                            value={primaryComponent.props
                                                ?.fit ?? 'cover'}
                                            onchange={(event) =>
                                                updateSelectedProps(
                                                    {
                                                        fit: event.currentTarget
                                                            .value,
                                                    },
                                                    'Update image fit',
                                                )}
                                        >
                                            <option value="cover">Cover</option>
                                            <option value="contain"
                                                >Contain</option
                                            >
                                            <option value="fill">Fill</option>
                                        </select>
                                    </label>
                                    <label class="block text-[10px]">
                                        Fallback
                                        <select
                                            disabled={primaryIsLocked}
                                            class="border-studio-border bg-studio-panel text-studio-panel-foreground disabled:bg-studio-panel-muted mt-1 w-full rounded border px-1.5 py-1 text-xs disabled:opacity-70"
                                            value={primaryComponent.props
                                                ?.fallback ?? 'affix_box'}
                                            onchange={(event) =>
                                                updateSelectedProps(
                                                    {
                                                        fallback:
                                                            event.currentTarget
                                                                .value,
                                                    },
                                                    'Update image fallback',
                                                )}
                                        >
                                            <option value="affix_box"
                                                >Affix box</option
                                            >
                                        </select>
                                    </label>
                                {/if}
                            {/if}

                            {#if ['text', 'paragraph', 'note', 'footer'].includes(primaryComponent.type)}
                                <textarea
                                    disabled={primaryIsLocked}
                                    class="border-studio-border bg-studio-panel text-studio-panel-foreground disabled:bg-studio-panel-muted min-h-20 w-full rounded border p-2 text-xs disabled:opacity-70"
                                    value={primaryComponent.props?.text ?? ''}
                                    oninput={(event) =>
                                        updateComponent(
                                            primaryComponent.id,
                                            {
                                                props: {
                                                    ...primaryComponent.props,
                                                    text: event.currentTarget
                                                        .value,
                                                },
                                            },
                                            'Update text',
                                        )}></textarea>
                            {/if}

                            {#if primaryComponent.type === 'header'}
                                <label class="block text-[10px]">
                                    Title
                                    <input
                                        disabled={primaryIsLocked}
                                        class="border-studio-border bg-studio-panel text-studio-panel-foreground disabled:bg-studio-panel-muted mt-1 w-full rounded border px-1.5 py-1 text-xs disabled:opacity-70"
                                        value={primaryComponent.props?.title ??
                                            ''}
                                        oninput={(event) =>
                                            updateComponent(
                                                primaryComponent.id,
                                                {
                                                    props: {
                                                        ...primaryComponent.props,
                                                        title: event
                                                            .currentTarget
                                                            .value,
                                                    },
                                                },
                                                'Update header title',
                                            )}
                                    />
                                </label>
                                <label class="block text-[10px]">
                                    Subtitle
                                    <input
                                        disabled={primaryIsLocked}
                                        class="border-studio-border bg-studio-panel text-studio-panel-foreground disabled:bg-studio-panel-muted mt-1 w-full rounded border px-1.5 py-1 text-xs disabled:opacity-70"
                                        value={primaryComponent.props
                                            ?.subtitle ?? ''}
                                        oninput={(event) =>
                                            updateComponent(
                                                primaryComponent.id,
                                                {
                                                    props: {
                                                        ...primaryComponent.props,
                                                        subtitle:
                                                            event.currentTarget
                                                                .value,
                                                    },
                                                },
                                                'Update header subtitle',
                                            )}
                                    />
                                </label>
                            {/if}

                            {#if textParts.length > 1}
                                <label
                                    class="text-studio-panel-muted-foreground block text-[10px] font-semibold"
                                >
                                    Text part
                                    <select
                                        class="border-studio-border bg-studio-panel text-studio-panel-foreground mt-1 w-full rounded border px-2 py-1 text-xs"
                                        value={activeTextPart}
                                        onchange={(event) =>
                                            (selectedTextPart =
                                                event.currentTarget.value)}
                                    >
                                        {#each textParts as part}
                                            <option value={part.value}
                                                >{part.label}</option
                                            >
                                        {/each}
                                    </select>
                                </label>
                            {/if}

                            <!-- Typography & Appearance Controls -->
                            <ResultSheetTypographyControls
                                typography={activeTextStyle}
                                onChanged={updateSelectedTypography}
                            />
                            <ResultSheetAppearanceControls
                                appearance={primaryComponent.props
                                    ?.appearance ?? {}}
                                spacing={primaryComponent.props?.spacing ?? {}}
                                onAppearanceChanged={updateSelectedAppearance}
                                onSpacingChanged={updateSelectedSpacing}
                            />
                        </div>
                    {:else}
                        <div
                            class="border-studio-border text-studio-panel-muted-foreground rounded-lg border border-dashed p-5 text-center text-xs leading-relaxed"
                        >
                            Select one or more components to inspect geometry,
                            alignment, bindings, and appearance.
                        </div>
                    {/if}
                {/if}

                <!-- Page Configuration Section with NumericControl -->
                <div class="border-studio-border border-t pt-3">
                    <NumericControl
                        label="Page margin"
                        min={5}
                        max={30}
                        step={1}
                        unit="mm"
                        value={page.margin_mm}
                        oninput={(val) =>
                            updatePageCoalesced(
                                'margin_mm',
                                val,
                                'Update margin',
                            )}
                        oncommit={(val) =>
                            updatePageCoalesced(
                                'margin_mm',
                                val,
                                'Update margin',
                            )}
                    />
                    <div
                        class="text-studio-panel-muted-foreground mt-1 flex justify-between text-[10px]"
                    >
                        <span>Page</span>
                        <span class="font-mono"
                            >{page.paper_size} · {page.orientation} · {page.margin_mm}mm</span
                        >
                    </div>
                </div>

                {#if diagnostics.length}
                    <div
                        class="border-studio-warning bg-studio-warning text-studio-warning-foreground rounded-md border p-2 text-[10px] leading-relaxed"
                    >
                        <strong
                            >{diagnostics.length} verification warning{diagnostics.length ===
                            1
                                ? ''
                                : 's'}</strong
                        >
                        <ul class="mt-1 list-disc pl-4">
                            {#each diagnostics.slice(0, 4) as diagnostic}
                                <li>{diagnostic}</li>
                            {/each}
                        </ul>
                    </div>
                {/if}

                <button
                    type="button"
                    class="border-studio-success bg-studio-success/30 text-studio-success-foreground hover:bg-studio-success/50 w-full rounded-md border px-3 py-2 text-xs font-semibold transition-colors motion-reduce:transition-none"
                    onclick={() =>
                        onOpenPlaceholders({
                            mode: 'insert',
                            targetId: primaryId,
                        })}
                >
                    Open field picker
                </button>
            </aside>
        </div>
        <!-- Center Interactive Canvas -->
        <div
            bind:this={canvasNode}
            role="region"
            aria-label="Visual document canvas"
            tabindex="-1"
            data-result-sheet-safe-top={canvasSafeTop}
            data-result-sheet-safe-bottom={canvasReservedBottom}
            data-result-sheet-safe-left={canvasSafeLeft}
            data-result-sheet-safe-right={canvasSafeRight}
            data-result-sheet-fit-invalidation={fitInvalidation}
            class={`border-studio-border bg-studio-canvas text-studio-canvas-foreground order-1 min-h-0 min-w-0 flex-1 overscroll-contain rounded-lg border p-3 sm:p-5 ${
                canvasTool === 'pan' || isSpacePressed
                    ? 'cursor-grab active:cursor-grabbing'
                    : 'cursor-crosshair'
            } ${
                fullBleed
                    ? 'relative order-first min-h-0 w-full min-w-0 flex-1 basis-0 overflow-auto rounded-2xl lg:order-none lg:w-auto'
                    : 'h-[min(65vh,640px)] min-h-[380px] overflow-auto sm:h-[min(76vh,760px)] sm:min-h-[520px]'
            }`}
            onpointerenter={() => (isCanvasHovered = true)}
            onpointerleave={() => {
                isCanvasHovered = false;
            }}
            onwheel={handleCanvasWheel}
            onpointerdown={beginCanvasPanOrMarquee}
            onpointermove={(event) => {
                moveCanvasPanOrMarquee(event);
                handlePointerMove(event);
            }}
            onpointerup={(event) => {
                endCanvasPanOrMarquee(event);
                handlePointerUp(event);
            }}
            onpointercancel={cancelActiveGesture}
            ondragover={handleDragOver}
            ondrop={handleDrop}
        >
            <div data-result-sheet-page-track class="flex min-w-0 items-start">
                <div
                    aria-hidden="true"
                    data-result-sheet-safe-top-inset
                    class="pointer-events-none shrink-0"
                    style={`height:${canvasSafeTop}px;`}
                ></div>
                <div
                    aria-hidden="true"
                    data-result-sheet-safe-left-inset
                    class="pointer-events-none shrink-0"
                    style={`width:${canvasSafeLeft}px;`}
                ></div>
                <div
                    bind:this={pageFrameNode}
                    data-result-sheet-page-frame
                    class="relative shrink-0"
                    style={pageFrameStyle}
                >
                    <div
                        class="bg-studio-sheet origin-top-left shadow-xl"
                        style={pageStyle}
                    >
                        <div
                            bind:this={pageNode}
                            data-result-sheet-page
                            class="relative h-full"
                            style={`width:${page.width_mm}mm;height:${page.height_mm}mm;padding:${page.margin_mm}mm;`}
                        >
                            <!-- Render Components in Stacking Order (Array Order Authority) -->
                            {#each components as component, index (component.id)}
                                {@const isComponentSelected = selectedIds.has(
                                    component.id,
                                )}
                                {@const isPrimary = primaryId === component.id}
                                <button
                                    type="button"
                                    data-result-sheet-component={component.id}
                                    data-component-id={legacyMode
                                        ? component.id
                                        : undefined}
                                    aria-pressed={isComponentSelected}
                                    aria-label={`${componentLabel(component.type)} component (${index + 1} of ${components.length})`}
                                    class={`border-studio-border focus-visible:ring-studio-focus absolute overflow-hidden rounded border text-left leading-tight transition-colors select-none focus-visible:ring-2 focus-visible:outline-none motion-reduce:transition-none ${
                                        isComponentSelected
                                            ? legacyMode
                                                ? 'border-studio-focus shadow-md ring-2 ring-studio-focus'
                                                : 'ring-studio-focus border-studio-focus shadow-md ring-2'
                                            : 'hover:border-studio-border'
                                    } ${isPrimary ? 'ring-offset-studio-sheet ring-offset-1' : ''} ${
                                        component.locked
                                            ? 'cursor-not-allowed border-dashed'
                                            : 'cursor-move touch-none'
                                    }`}
                                    style={componentStyle(
                                        component,
                                        undefined,
                                        page.margin_mm,
                                    )}
                                    onpointerdown={(event) =>
                                        beginComponentDrag(event, component)}
                                    onkeydown={(event) =>
                                        handleComponentKeydown(
                                            event,
                                            component,
                                        )}
                                >
                                    <ResultSheetComponentPreview
                                        {component}
                                        {sampleContext}
                                        noticeDocumentImagePreview={allowNoticeDocumentImages}
                                        noticeDocumentImageAssets={noticeDocumentImageAssets}
                                        componentTemplate={localDocument
                                            .component_templates?.[
                                            component.type
                                        ]?.html ?? ''}
                                    />

                                    <!-- Resize Corner Handles (only on primary unlocked selected item) -->
                                    {#if isComponentSelected && !component.locked && (selectedCount === 1 || isPrimary)}
                                        <span
                                            class="bg-studio-focus ring-studio-sheet absolute top-0 left-0 z-30 size-3 cursor-nwse-resize rounded-br shadow ring-2"
                                            title="Resize component (NW)"
                                            aria-hidden="true"
                                            onpointerdown={(event) =>
                                                beginComponentResize(
                                                    event,
                                                    component,
                                                    'nw',
                                                )}
                                        ></span>
                                        <span
                                            class="bg-studio-focus ring-studio-sheet absolute top-0 right-0 z-30 size-3 cursor-nesw-resize rounded-bl shadow ring-2"
                                            title="Resize component (NE)"
                                            aria-hidden="true"
                                            onpointerdown={(event) =>
                                                beginComponentResize(
                                                    event,
                                                    component,
                                                    'ne',
                                                )}
                                        ></span>
                                        <span
                                            class="bg-studio-focus ring-studio-sheet absolute bottom-0 left-0 z-30 size-3 cursor-nesw-resize rounded-tr shadow ring-2"
                                            title="Resize component (SW)"
                                            aria-hidden="true"
                                            onpointerdown={(event) =>
                                                beginComponentResize(
                                                    event,
                                                    component,
                                                    'sw',
                                                )}
                                        ></span>
                                        <span
                                            class="bg-studio-focus ring-studio-sheet absolute right-0 bottom-0 z-30 size-3 cursor-nwse-resize rounded-tl shadow ring-2"
                                            title="Resize component (SE)"
                                            aria-hidden="true"
                                            onpointerdown={(event) =>
                                                beginComponentResize(
                                                    event,
                                                    component,
                                                    'se',
                                                )}
                                        ></span>
                                    {/if}

                                    <!-- Lock badge indicator -->
                                    {#if component.locked}
                                        <div
                                            class="bg-studio-shell/80 text-studio-shell-foreground absolute top-1 right-1 z-30 rounded p-0.5 shadow-sm"
                                            title="Locked"
                                        >
                                            <Lock class="size-2.5" />
                                        </div>
                                    {/if}
                                </button>
                            {/each}

                            <!-- Marquee Selection Box Overlay -->
                            {#if marqueeState}
                                {@const mRect = normalizeRect({
                                    x: Math.min(
                                        marqueeState.startPoint.x,
                                        marqueeState.currentPoint.x,
                                    ),
                                    y: Math.min(
                                        marqueeState.startPoint.y,
                                        marqueeState.currentPoint.y,
                                    ),
                                    width: Math.abs(
                                        marqueeState.currentPoint.x -
                                            marqueeState.startPoint.x,
                                    ),
                                    height: Math.abs(
                                        marqueeState.currentPoint.y -
                                            marqueeState.startPoint.y,
                                    ),
                                })}
                                {#if mRect.width > 0.5 || mRect.height > 0.5}
                                    <div
                                        data-result-sheet-marquee
                                        class="border-studio-focus bg-studio-selection/40 pointer-events-none absolute z-50 rounded border-2 border-dashed shadow-sm"
                                        style={`left:${mRect.x + Number(page.margin_mm)}mm;top:${mRect.y + Number(page.margin_mm)}mm;width:${mRect.width}mm;height:${mRect.height}mm;`}
                                    ></div>
                                {/if}
                            {/if}

                            <!-- Visible Snap Guides -->
                            {#each activeSnapGuides as guide}
                                {#if guide.axis === 'x'}
                                    <div
                                        class="border-studio-focus pointer-events-none absolute top-0 bottom-0 z-40 border-l-2 border-dashed"
                                        style={`left:${guide.position + Number(page.margin_mm)}mm;`}
                                    ></div>
                                {:else}
                                    <div
                                        class="border-studio-focus pointer-events-none absolute right-0 left-0 z-40 border-t-2 border-dashed"
                                        style={`top:${guide.position + Number(page.margin_mm)}mm;`}
                                    ></div>
                                {/if}
                            {/each}

                            {#if components.length === 0}
                                <div
                                    class="text-studio-canvas-foreground/70 flex h-full min-h-[260px] items-center justify-center text-center text-xs"
                                >
                                    Drop a component here<br />or choose one
                                    from the palette.
                                </div>
                            {/if}
                        </div>
                    </div>
                </div>
                <div
                    aria-hidden="true"
                    data-result-sheet-safe-right-inset
                    class="pointer-events-none shrink-0"
                    style={`width:${canvasSafeRight}px;`}
                ></div>
            </div>
            <div
                aria-hidden="true"
                data-result-sheet-safe-inset
                class="pointer-events-none shrink-0"
                style={`height:${canvasReservedBottom}px;`}
            ></div>
        </div>
    </div>
    {#if fullBleed && belowDesktop && !mobileFocusMode}
        <nav
            data-result-sheet-command-dock
            class="border-studio-border bg-studio-panel grid w-full shrink-0 grid-cols-3 border-t p-1.5 pb-[calc(0.375rem+env(safe-area-inset-bottom))]"
            aria-label="Studio commands"
        >
            <button
                type="button"
                data-result-sheet-command="add"
                class="text-studio-panel-foreground hover:bg-studio-panel-muted focus-visible:ring-studio-focus flex min-h-11 flex-col items-center justify-center gap-0.5 rounded-md px-2 py-1 text-[10px] font-semibold transition-colors focus-visible:ring-2 focus-visible:outline-none"
                aria-controls="result-sheet-component-palette"
                aria-expanded={activeMobilePanel === 'add'}
                aria-pressed={activeMobilePanel === 'add'}
                onclick={(event) => toggleMobilePanel('add', event)}
            >
                <Plus class="size-4" aria-hidden="true" />
                <span>Add</span>
            </button>
            <button
                type="button"
                data-result-sheet-command="layers"
                class="text-studio-panel-foreground hover:bg-studio-panel-muted focus-visible:ring-studio-focus flex min-h-11 flex-col items-center justify-center gap-0.5 rounded-md px-2 py-1 text-[10px] font-semibold transition-colors focus-visible:ring-2 focus-visible:outline-none disabled:pointer-events-none disabled:opacity-40"
                aria-controls="result-sheet-inspector"
                aria-expanded={activeMobilePanel === 'layers'}
                aria-pressed={activeMobilePanel === 'layers'}
                disabled={components.length === 0}
                onclick={(event) => toggleMobilePanel('layers', event)}
            >
                <LayersIcon class="size-4" aria-hidden="true" />
                <span>Layers</span>
            </button>
            <button
                type="button"
                data-result-sheet-command="inspector"
                class="text-studio-panel-foreground hover:bg-studio-panel-muted focus-visible:ring-studio-focus flex min-h-11 flex-col items-center justify-center gap-0.5 rounded-md px-2 py-1 text-[10px] font-semibold transition-colors focus-visible:ring-2 focus-visible:outline-none disabled:pointer-events-none disabled:opacity-40"
                aria-controls="result-sheet-inspector"
                aria-expanded={activeMobilePanel === 'inspector'}
                aria-pressed={activeMobilePanel === 'inspector'}
                disabled={selectedCount === 0}
                onclick={(event) => toggleMobilePanel('inspector', event)}
            >
                <SlidersHorizontal class="size-4" aria-hidden="true" />
                <span>Inspector</span>
            </button>
        </nav>
    {/if}
</div>
