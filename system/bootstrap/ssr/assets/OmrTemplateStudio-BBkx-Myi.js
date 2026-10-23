import { g as sanitize_props, i as spread_props, j as slot, o as onDestroy, e as escape_html, c as attr_class, a as ensure_array_like, b as attr, a3 as csrfToken, f as attr_style, d as derived, n as newIdempotencyKey, m as csrfHeaders, v as error } from "../app.js";
import { e as effectiveRecognitionGeometryMm, s as syncDeclaredInventoryFromSections, n as nextItemNumberForArea, v as validateStudioLayout, a as emitNativeMapping, b as emitOverlayBoxes, c as contentBlockSizeMm } from "./omrStudioGeometry-DyCkNaaw.js";
import { c as componentBlockSizeMm, m as minimumCanvasPitchMm, p as paletteForPath, a as componentToSectionData, r as renderFullSheetHtml, C as COMPONENT_PATH_FILTERS, o as overlayPaletteForPath, b as canvasComponentBlockSizeMm, n as normalizeQuestionGridRowPositions } from "./omrComponents-D81Zk9FJ.js";
import StudioCanvas from "./StudioCanvas-BPLCVpSp.js";
import StudioOverlayPanel from "./StudioOverlayPanel-DQo4NXMg.js";
import StudioBubblePanel from "./StudioBubblePanel-dUcvtVr8.js";
import StudioExportVerifyPanel from "./StudioExportVerifyPanel-CZarLenW.js";
import StudioTypographyControls from "./StudioTypographyControls-D2aqSkxQ.js";
import StudioQrReservationControls from "./StudioQrReservationControls-C1jYP4Jw.js";
import StudioComponentAppearanceControls from "./StudioComponentAppearanceControls-CMSXg1au.js";
import StudioFiducialInsetControls from "./StudioFiducialInsetControls-CdTiPYqb.js";
import { I as Icon } from "./Icon-Dal7PqKK.js";
import { A as Arrow_left } from "./arrow-left-DbWnQR3t.js";
import { U as Upload } from "./upload-CwqN2Utd.js";
import { S as Sliders_vertical } from "./sliders-vertical-DiYN4y-t.js";
import { L as Loader_circle } from "./loader-circle-_UEl10Lx.js";
import { C as Chevron_left } from "./chevron-left-DCSOZwZl.js";
import { C as Chevron_right } from "./chevron-right-BHu-PGOg.js";
import { P as Plus } from "./plus-DjyPLC7A.js";
import { T as Trash_2 } from "./trash-2-CjFp3otc.js";
import { U as Undo_2 } from "./undo-2-CzBOZ6l_.js";
import { C as Circle_check } from "./circle-check-C59ZS7Zv.js";
import { L as Lock } from "./lock-DAA4kTLl.js";
import { D as Download } from "./download-n32NlL8c.js";
import { L as Lock_open } from "./lock-open-5Nny5RV8.js";
import { M as Minus } from "./minus-Dqkhcxt7.js";
import { M as Maximize_2 } from "./maximize-2-u72Ahym6.js";
import { S as Sparkles } from "./sparkles-DbQIaVG6.js";
import { C as Chevron_down } from "./chevron-down-CkQQZIhu.js";
import { C as Circle_x } from "./circle-x-BQrhluGu.js";
import { T as Triangle_alert } from "./triangle-alert-CUvuB-HL.js";
import { s as snapshot } from "./clone-BJz-lxsY.js";
import "axios";
import "@inertiajs/core";
import "clsx";
import "laravel-precognition";
import "tailwind-merge";
import "tailwind-variants";
import "./StudioTip-evy-6JcG.js";
import "./info-C3MCzlQW.js";
import "./StudioFontPicker-5wWjSEPZ.js";
import "./text-align-start-Cy9u9QqD.js";
import "./chevron-up-lhASJLRD.js";
import "./scan-DTyWR4bt.js";
import "./watch.svelte-BcPoqckX.js";
import "./StudioShadingGuideModal-BfmTaRFq.js";
import "./dialog-description-D12L0QA9.js";
import "./noop-BQS7OFs8.js";
import "./create-id-CUJmDlea.js";
import "style-to-object";
import "./scroll-lock-B1ibcKMx.js";
import "./is-C6cm64pF.js";
import "tabbable";
import "./x-CnxrMbMn.js";
import "./dialog-footer-kNnwKT2h.js";
import "./check-DK45Y9SJ.js";
import "./qr-code-C5X8qcH6.js";
import "./printer-Bx0z1CRb.js";
import "./shield-alert-CXtDdte9.js";
import "./arrow-right-CzynHiIo.js";
import "./rotate-ccw-D6Dm_efx.js";
import "./file-image-BGlN21-8.js";
import "./OmrPhysicalTestMethodCard-hibzJL3T.js";
import "./OmrCaptureInput-CmVb1QoH.js";
/* empty css                                                       */
import "./camera-C09Eyogi.js";
import "./refresh-cw-CYdk8aE0.js";
import "./file-text-PXEtmXm9.js";
import "./book-open-B6eMguqY.js";
import "./eye-DyG-RiwW.js";
import "./OmrDiagnosticEvidencePanel-CvO7kqrK.js";
import "./zoom-out-DsJCISKt.js";
import "./eye-off-Bx_raPQb.js";
import "./save-DAlMQk-Q.js";
import "./scan-line-CiwhUtYK.js";
import "./layers-_Ie6y7G_.js";
import "./circle-alert-jWXJ7oC0.js";
import "./Link-B7ks7rBK.js";
import "./text-align-justify-CFymVZbq.js";
function Grip_vertical($$renderer, $$props) {
  const $$sanitized_props = sanitize_props($$props);
  const iconNode = [
    ["circle", { "cx": "9", "cy": "12", "r": "1" }],
    ["circle", { "cx": "9", "cy": "5", "r": "1" }],
    ["circle", { "cx": "9", "cy": "19", "r": "1" }],
    ["circle", { "cx": "15", "cy": "12", "r": "1" }],
    ["circle", { "cx": "15", "cy": "5", "r": "1" }],
    ["circle", { "cx": "15", "cy": "19", "r": "1" }]
  ];
  Icon($$renderer, spread_props([
    { name: "grip-vertical" },
    $$sanitized_props,
    {
      /**
       * @component @name GripVertical
       * @description Lucide SVG icon component, renders SVG Element with children.
       *
       * @preview ![img](data:image/svg+xml;base64,PHN2ZyAgeG1sbnM9Imh0dHA6Ly93d3cudzMub3JnLzIwMDAvc3ZnIgogIHdpZHRoPSIyNCIKICBoZWlnaHQ9IjI0IgogIHZpZXdCb3g9IjAgMCAyNCAyNCIKICBmaWxsPSJub25lIgogIHN0cm9rZT0iIzAwMCIgc3R5bGU9ImJhY2tncm91bmQtY29sb3I6ICNmZmY7IGJvcmRlci1yYWRpdXM6IDJweCIKICBzdHJva2Utd2lkdGg9IjIiCiAgc3Ryb2tlLWxpbmVjYXA9InJvdW5kIgogIHN0cm9rZS1saW5lam9pbj0icm91bmQiCj4KICA8Y2lyY2xlIGN4PSI5IiBjeT0iMTIiIHI9IjEiIC8+CiAgPGNpcmNsZSBjeD0iOSIgY3k9IjUiIHI9IjEiIC8+CiAgPGNpcmNsZSBjeD0iOSIgY3k9IjE5IiByPSIxIiAvPgogIDxjaXJjbGUgY3g9IjE1IiBjeT0iMTIiIHI9IjEiIC8+CiAgPGNpcmNsZSBjeD0iMTUiIGN5PSI1IiByPSIxIiAvPgogIDxjaXJjbGUgY3g9IjE1IiBjeT0iMTkiIHI9IjEiIC8+Cjwvc3ZnPgo=) - https://lucide.dev/icons/grip-vertical
       * @see https://lucide.dev/guide/packages/lucide-svelte - Documentation
       *
       * @param {Object} props - Lucide icons props and any valid SVG attribute
       * @returns {FunctionalComponent} Svelte component
       *
       */
      iconNode,
      children: ($$renderer2) => {
        $$renderer2.push(`<!--[-->`);
        slot($$renderer2, $$props, "default", {});
        $$renderer2.push(`<!--]-->`);
      },
      $$slots: { default: true }
    }
  ]));
}
function Monitor($$renderer, $$props) {
  const $$sanitized_props = sanitize_props($$props);
  const iconNode = [
    [
      "rect",
      { "width": "20", "height": "14", "x": "2", "y": "3", "rx": "2" }
    ],
    ["line", { "x1": "8", "x2": "16", "y1": "21", "y2": "21" }],
    ["line", { "x1": "12", "x2": "12", "y1": "17", "y2": "21" }]
  ];
  Icon($$renderer, spread_props([
    { name: "monitor" },
    $$sanitized_props,
    {
      /**
       * @component @name Monitor
       * @description Lucide SVG icon component, renders SVG Element with children.
       *
       * @preview ![img](data:image/svg+xml;base64,PHN2ZyAgeG1sbnM9Imh0dHA6Ly93d3cudzMub3JnLzIwMDAvc3ZnIgogIHdpZHRoPSIyNCIKICBoZWlnaHQ9IjI0IgogIHZpZXdCb3g9IjAgMCAyNCAyNCIKICBmaWxsPSJub25lIgogIHN0cm9rZT0iIzAwMCIgc3R5bGU9ImJhY2tncm91bmQtY29sb3I6ICNmZmY7IGJvcmRlci1yYWRpdXM6IDJweCIKICBzdHJva2Utd2lkdGg9IjIiCiAgc3Ryb2tlLWxpbmVjYXA9InJvdW5kIgogIHN0cm9rZS1saW5lam9pbj0icm91bmQiCj4KICA8cmVjdCB3aWR0aD0iMjAiIGhlaWdodD0iMTQiIHg9IjIiIHk9IjMiIHJ4PSIyIiAvPgogIDxsaW5lIHgxPSI4IiB4Mj0iMTYiIHkxPSIyMSIgeTI9IjIxIiAvPgogIDxsaW5lIHgxPSIxMiIgeDI9IjEyIiB5MT0iMTciIHkyPSIyMSIgLz4KPC9zdmc+Cg==) - https://lucide.dev/icons/monitor
       * @see https://lucide.dev/guide/packages/lucide-svelte - Documentation
       *
       * @param {Object} props - Lucide icons props and any valid SVG attribute
       * @returns {FunctionalComponent} Svelte component
       *
       */
      iconNode,
      children: ($$renderer2) => {
        $$renderer2.push(`<!--[-->`);
        slot($$renderer2, $$props, "default", {});
        $$renderer2.push(`<!--]-->`);
      },
      $$slots: { default: true }
    }
  ]));
}
function Redo_2($$renderer, $$props) {
  const $$sanitized_props = sanitize_props($$props);
  const iconNode = [
    ["path", { "d": "m15 14 5-5-5-5" }],
    [
      "path",
      {
        "d": "M20 9H9.5A5.5 5.5 0 0 0 4 14.5A5.5 5.5 0 0 0 9.5 20H13"
      }
    ]
  ];
  Icon($$renderer, spread_props([
    { name: "redo-2" },
    $$sanitized_props,
    {
      /**
       * @component @name Redo2
       * @description Lucide SVG icon component, renders SVG Element with children.
       *
       * @preview ![img](data:image/svg+xml;base64,PHN2ZyAgeG1sbnM9Imh0dHA6Ly93d3cudzMub3JnLzIwMDAvc3ZnIgogIHdpZHRoPSIyNCIKICBoZWlnaHQ9IjI0IgogIHZpZXdCb3g9IjAgMCAyNCAyNCIKICBmaWxsPSJub25lIgogIHN0cm9rZT0iIzAwMCIgc3R5bGU9ImJhY2tncm91bmQtY29sb3I6ICNmZmY7IGJvcmRlci1yYWRpdXM6IDJweCIKICBzdHJva2Utd2lkdGg9IjIiCiAgc3Ryb2tlLWxpbmVjYXA9InJvdW5kIgogIHN0cm9rZS1saW5lam9pbj0icm91bmQiCj4KICA8cGF0aCBkPSJtMTUgMTQgNS01LTUtNSIgLz4KICA8cGF0aCBkPSJNMjAgOUg5LjVBNS41IDUuNSAwIDAgMCA0IDE0LjVBNS41IDUuNSAwIDAgMCA5LjUgMjBIMTMiIC8+Cjwvc3ZnPgo=) - https://lucide.dev/icons/redo-2
       * @see https://lucide.dev/guide/packages/lucide-svelte - Documentation
       *
       * @param {Object} props - Lucide icons props and any valid SVG attribute
       * @returns {FunctionalComponent} Svelte component
       *
       */
      iconNode,
      children: ($$renderer2) => {
        $$renderer2.push(`<!--[-->`);
        slot($$renderer2, $$props, "default", {});
        $$renderer2.push(`<!--]-->`);
      },
      $$slots: { default: true }
    }
  ]));
}
function clone(value) {
  return structuredClone(value);
}
function equal(left, right) {
  return JSON.stringify(left) === JSON.stringify(right);
}
function createStudioHistory(initial, maxEntries = 100) {
  const limit = Math.max(1, Math.floor(maxEntries));
  let current = clone(initial);
  let past = [];
  let future = [];
  let lastCoalesceKey;
  let lastCoalesceAt = 0;
  function resetCoalescing() {
    lastCoalesceKey = void 0;
    lastCoalesceAt = 0;
  }
  return {
    present() {
      return clone(current);
    },
    record(next, label, options = {}) {
      const nextState = clone(next);
      if (equal(current, nextState)) return false;
      const now = Date.now();
      const windowMs = options.windowMs ?? 450;
      const canCoalesce = Boolean(
        options.coalesceKey && options.coalesceKey === lastCoalesceKey && now - lastCoalesceAt <= windowMs && past.length > 0
      );
      if (!canCoalesce) {
        past.push({
          state: clone(current),
          label,
          coalesceKey: options.coalesceKey
        });
        if (past.length > limit) past = past.slice(-limit);
      } else {
        past[past.length - 1].label = label;
      }
      current = nextState;
      future = [];
      lastCoalesceKey = options.coalesceKey;
      lastCoalesceAt = now;
      return true;
    },
    undo() {
      const entry = past.pop();
      if (!entry) return null;
      future.unshift({ state: clone(current), label: entry.label });
      current = clone(entry.state);
      resetCoalescing();
      return clone(current);
    },
    redo() {
      const entry = future.shift();
      if (!entry) return null;
      past.push({ state: clone(current), label: entry.label });
      if (past.length > limit) past = past.slice(-limit);
      current = clone(entry.state);
      resetCoalescing();
      return clone(current);
    },
    reset(state) {
      current = clone(state);
      past = [];
      future = [];
      resetCoalescing();
    },
    clear() {
      past = [];
      future = [];
      resetCoalescing();
    },
    status() {
      return {
        canUndo: past.length > 0,
        canRedo: future.length > 0,
        undoLabel: past.at(-1)?.label ?? null,
        redoLabel: future[0]?.label ?? null
      };
    }
  };
}
function OmrTemplateStudio($$renderer, $$props) {
  $$renderer.component(($$renderer2) => {
    let {
      draft = {},
      marker_profiles = [],
      aptitude_areas = [],
      test_instrument = null,
      urls = {},
      verification_run = null,
      physical_run_id = null,
      onMappingSaved = null
    } = $$props;
    let activeVerificationRun = verification_run;
    const isReadOnly = derived(() => !draft.can_edit || draft.stage === "compiled" || draft.compiled_version_id != null);
    const selectedMarkerProfile = derived(() => marker_profiles.find((profile) => Number(profile.id) === Number(draft.marker_profile_id)) ?? null);
    const usesFiducials = draft.registration_mode === "fiducial_anchored";
    const qrEnabled = draft.qr_mode !== "qr_disabled";
    let orderedPages = Array.isArray(draft.ordered_pages) && draft.ordered_pages.length > 0 ? draft.ordered_pages : Array.from({ length: Number(draft.requested_page_count) || 1 }, (_, index) => ({ page_no: index + 1, kind: "blank", confirmed: true }));
    let activePageNo = Math.min(Number(draft.requested_page_count) || 1, Number(draft.authoring?.studio?.active_page_no) || 1);
    let pageMutationRunning = false;
    let isSidebarCollapsed = false;
    let currentStage = 1;
    const sidebarStage = derived(() => currentStage);
    const stageTitles = [
      "1. Source Ingest",
      "2. Page Registration",
      "3. Recognition Mapping",
      "4. Verification & Test"
    ];
    function normalizeStudioStage(stage, mode) {
      const numericStage = Number(stage);
      if (!Number.isInteger(numericStage) || numericStage < 1 || numericStage > 4) return 1;
      if (mode === "html" && numericStage === 2) return 1;
      if (mode !== "html" && !modifyUploadedDocument && numericStage === 2) return 3;
      return numericStage;
    }
    function availableOverlayPalette(path) {
      return overlayPaletteForPath(path).filter((item) => (usesFiducials || item.kind !== "markers") && (qrEnabled || item.kind !== "qr_code"));
    }
    let stage1Mode = draft.source_kind === "image" || draft.source_kind === "pdf" || draft.source_kind === "docx" ? "upload" : "html";
    let baseType = draft.source_kind ?? "html";
    let hasChosenMode = Boolean(draft.authoring?.studio?.stage1Mode);
    let componentPalettePath = derived(() => stage1Mode === "html" ? "sheet_designer" : "upload_document");
    let componentPaletteFilter = derived(() => COMPONENT_PATH_FILTERS[componentPalettePath()]);
    let modifyUploadedDocument = Boolean(draft.authoring?.studio?.modifyUploadedDocument ?? false);
    let currentRasterUrl = draft.raster_url ?? null;
    let sheetImageLoaded = false;
    const activePageRasterUrl = derived(() => {
      if (stage1Mode === "html") return null;
      const canonicalRasters = Array.isArray(draft.authoring?.canonical?.raster_assets) ? draft.authoring.canonical.raster_assets : [];
      const canonicalAsset = canonicalRasters.find((r) => Number(r.page_no) === activePageNo);
      if (canonicalAsset?.asset_id) {
        return `/setup/omr/templates/canonical/drafts/${draft.id}/assets/${canonicalAsset.asset_id}`;
      }
      const pageAsset = orderedPages.find((p) => Number(p.page_no) === activePageNo);
      if (pageAsset?.asset_id) {
        return `/setup/omr/templates/canonical/drafts/${draft.id}/assets/${pageAsset.asset_id}`;
      }
      return currentRasterUrl || draft.raster_url || null;
    });
    let declaredInventory = Array.isArray(draft.authoring?.studio?.declaredInventory) ? draft.authoring.studio.declaredInventory : Array.isArray(draft.authoring?.mapping?.input?.declared_inventory) ? draft.authoring.mapping.input.declared_inventory.map((entry, idx) => ({
      id: entry.id ?? `inventory-${entry.page_no ?? 1}-${idx + 1}`,
      page_no: Number(entry.page_no ?? 1),
      area_code: entry.area_code ?? "",
      question_count: entry.question_count ?? null,
      option_labels: Array.isArray(entry.option_labels) ? entry.option_labels : [],
      geometry_id: entry.geometry_id ?? null
    })) : [];
    let identityDeclaration = draft.authoring?.studio?.identity ?? draft.authoring?.mapping?.input?.identity ?? { method: null };
    let documentFields = draft.authoring?.studio?.document_fields ?? draft.authoring?.mapping?.input?.document_fields ?? draft.authoring?.canonical?.mapping?.document_fields ?? [];
    let activeDocumentFieldId = null;
    let designerSubTab = "designer";
    let components = [];
    let selectedComponentId = null;
    let selectedComponentIds = [];
    let selectedCanvasIds = [];
    const QR_CANVAS_ITEM_ID = "qr";
    let componentCopySeq = 0;
    let layerDragId = null;
    let layerDragOverId = null;
    let showLayersPanel = true;
    let selectedComponent = derived(() => components.find((c) => c.id === selectedComponentId) ?? null);
    const DEFAULT_MARKER_INSETS_MM = { top: 12, right: 12, bottom: 14, left: 12 };
    function normalizeMarkerInsets(insets) {
      if (!insets || typeof insets !== "object") return { ...DEFAULT_MARKER_INSETS_MM };
      return Object.fromEntries(Object.entries(DEFAULT_MARKER_INSETS_MM).map(([side, fallback]) => [
        side,
        Number.isFinite(Number(insets[side])) ? Number(insets[side]) : fallback
      ]));
    }
    function htmlSheetSettings() {
      return {
        paperWidthMm: 210,
        paperHeightMm: 297,
        marginMm,
        markerPattern,
        markerInsetsMm
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
          overlayTag: "blank_instance_qr"
        }
      };
    }
    function renderCurrentHtml() {
      return renderFullSheetHtml(components, htmlSheetSettings(), htmlDecorations(), Number(draft.requested_page_count) || orderedPages.length || 1);
    }
    const activePageHtml = derived(() => {
      if (!htmlCode) return "";
      if ((Number(draft.requested_page_count) || orderedPages.length || 1) <= 1) {
        return htmlCode;
      }
      const pageComps = components.filter((c) => (Number(c.page_no) || 1) === activePageNo);
      return renderFullSheetHtml(pageComps.map((c) => ({ ...c, page_no: 1 })), htmlSheetSettings(), htmlDecorations(), 1);
    });
    function invalidateDerivedAuthoringState() {
      canonicalPreview = null;
      previewError = null;
      mappingDirty = true;
      if (stage1Mode === "html") {
        draft.raster_url = null;
      }
      if (draft.stage === "digital_verified") {
        draft.stage = "draft";
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
        if (stage1Mode === "html" && draft.authoring.canonical) {
          draft.authoring.canonical = null;
        }
      }
    }
    function handleHtmlDecorationChanged() {
      if (components.length > 0) htmlCode = renderCurrentHtml();
      invalidateDerivedAuthoringState();
      recordHistory("Edit layout", { coalesceKey: "layout" });
      triggerAutosave();
    }
    function componentLabel(c) {
      switch (c.type) {
        case "header":
          return "Document Header";
        case "candidate_name":
          return "Candidate Name";
        case "birthdate":
          return "Birthdate";
        case "room_seat":
          return "Room / Seat";
        case "instructions":
          return "Instructions";
        case "roll":
          return "Examinee ID Matrix";
        case "mcq":
        case "tf":
        case "likert":
        case "custom":
          return c.subjectName || c.name || "Question Grid";
        case "custom_text":
          return "Custom Text";
        case "custom_input":
          return "Custom Input Box";
        case "signature":
          return "Signature Box";
        default:
          return c.type;
      }
    }
    function updateSelectedComponent(patch) {
      if (!selectedComponent()) return;
      Object.assign(selectedComponent(), patch);
      syncComponentsToSections();
    }
    function syncComponentsToSections(label = "Edit component", options = {}) {
      normalizeComponentsForCanvas();
      htmlCode = components.length > 0 ? renderCurrentHtml() : "";
      sections = components.map((c) => componentToSectionData(c)).filter((s) => s !== null);
      invalidateDerivedAuthoringState();
      if (options.recordHistory !== false) {
        const historyOptions = options.coalesceKey ? options : label === "Edit component" && selectedComponentId ? { coalesceKey: `component:${selectedComponentId}` } : {};
        recordHistory(label, historyOptions);
      }
      if (options.autosave !== false) triggerAutosave();
    }
    const PAGE_W = 210;
    const PAGE_H = 297;
    function normalizeRecognitionSection(section) {
      if (!section || !["roll", "mcq", "tf", "likert", "custom"].includes(section.type)) {
        return section;
      }
      section.page_no = Math.min(Number(draft.requested_page_count) || orderedPages.length || 1, Math.max(1, Number(section.page_no) || 1));
      const geometry = effectiveRecognitionGeometryMm({
        kind: section.type === "roll" ? "roll" : "grid",
        bubbleDiameterMm: section.bubbleDiameterMm,
        bubblePitchMm: section.bubblePitchMm,
        rowPitchMm: section.rowPitchMm
      });
      if (Number(section.bubbleDiameterMm) === geometry.bubbleDiameterMm && Number(section.bubblePitchMm) === geometry.bubblePitchMm && Number(section.rowPitchMm) === geometry.rowPitchMm) {
        return section;
      }
      return {
        ...section,
        bubbleDiameterMm: geometry.bubbleDiameterMm,
        bubblePitchMm: geometry.bubblePitchMm,
        rowPitchMm: geometry.rowPitchMm
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
      const clamp = (value, min, max, fallback) => Math.min(max, Math.max(min, Number.isFinite(Number(value)) ? Number(value) : fallback));
      for (const component of components) {
        component.page_no = Math.min(Number(draft.requested_page_count) || orderedPages.length || 1, Math.max(1, Number(component.page_no) || 1));
        const isRoll = component.type === "roll";
        const isGrid = ["mcq", "tf", "likert", "custom"].includes(component.type);
        if (isRoll || isGrid) {
          const geometry = effectiveRecognitionGeometryMm({
            kind: isRoll ? "roll" : "grid",
            bubbleDiameterMm: clamp(component.bubbleDiameterMm, 4.2, 8, 4.5),
            bubblePitchMm: component.bubblePitchMm,
            rowPitchMm: component.rowPitchMm
          });
          const minimumPitch = minimumCanvasPitchMm(geometry.bubbleDiameterMm);
          const maximumPitch = isRoll ? 15 : 25;
          component.bubbleDiameterMm = geometry.bubbleDiameterMm;
          component.bubblePitchMm = clamp(geometry.bubblePitchMm, minimumPitch, maximumPitch, minimumPitch);
          component.rowPitchMm = clamp(geometry.rowPitchMm, minimumPitch, maximumPitch, minimumPitch);
          if (isRoll) {
            component.digitsCount = clamp(component.digitsCount, 2, 10, 7);
          } else {
            component.labelFontSizePt = clamp(component.labelFontSizePt, 5, 12, 6.5);
            component.questionsCount = clamp(component.questionsCount, 1, 50, 25);
            while (component.questionsCount > 1 && canvasComponentBlockSizeMm(component).h_mm > printableHeight) {
              component.questionsCount -= 1;
            }
          }
          while (component.bubblePitchMm > minimumPitch && canvasComponentBlockSizeMm(component).w_mm > printableWidth) {
            component.bubblePitchMm = Math.max(minimumPitch, Number((Number(component.bubblePitchMm) - 0.1).toFixed(2)));
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
    function syncCanvasSelection(nextIds, primaryId = null) {
      selectedCanvasIds = [...new Set(nextIds)];
      selectedComponentIds = selectedCanvasIds.filter((id) => id !== QR_CANVAS_ITEM_ID);
      selectedComponentId = selectedComponentIds.includes(primaryId) ? primaryId : selectedComponentIds.at(-1) ?? null;
      return selectedCanvasIds;
    }
    function selectCanvasItem(id, additive = false) {
      const currentSelection = selectedCanvasIds;
      const nextSelection = additive ? currentSelection.includes(id) ? currentSelection.filter((selectedId) => selectedId !== id) : [...currentSelection, id] : [id];
      const selection = syncCanvasSelection(nextSelection, id);
      if (id !== QR_CANVAS_ITEM_ID) {
        const idx = sections.findIndex((s) => s.id === id);
        if (idx >= 0) activeSectionIndex = idx;
      }
      return selection;
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
    function removeSelectedComponents() {
      const ids = selectedCanvasIds.length > 0 ? selectedCanvasIds : selectedComponentId ? [selectedComponentId] : [];
      if (ids.length === 0) return;
      const idSet = new Set(ids);
      components = components.filter((component) => !idSet.has(component.id));
      if (idSet.has(QR_CANVAS_ITEM_ID)) showQrOverlay = false;
      clearComponentSelection();
      syncComponentsToSections("Delete selected items");
    }
    function duplicateSelectedComponents(fallbackId = null, options = {}) {
      const currentSelection = selectedComponentIds.length > 0 ? selectedComponentIds : selectedComponentId ? [selectedComponentId] : [];
      const ids = fallbackId && !currentSelection.includes(fallbackId) ? [fallbackId] : currentSelection;
      const sourceComponents = components.filter((component) => ids.includes(component.id) && !component.isLocked);
      if (sourceComponents.length === 0) return [];
      const usedIds = new Set(components.map((component) => component.id));
      const copies = sourceComponents.map((component) => {
        const copy = structuredClone(snapshot(component));
        let nextId = "";
        do {
          componentCopySeq += 1;
          nextId = `${component.id}_copy_${componentCopySeq}`;
        } while (usedIds.has(nextId));
        usedIds.add(nextId);
        copy.id = nextId;
        copy.posX = Math.min(PAGE_W - MIN_INSET - (Number(copy.w_mm) || 40), (Number(copy.posX) || 10) + 8);
        copy.posY = Math.min(PAGE_H - MIN_INSET - (Number(copy.h_mm) || 12), (Number(copy.posY) || 10) + 8);
        copy.isLocked = false;
        return copy;
      });
      components = normalizeLayerOrder([...components, ...copies]);
      selectedCanvasIds = copies.map((copy) => copy.id);
      selectedComponentIds = [...selectedCanvasIds];
      selectedComponentId = selectedComponentIds.at(-1) ?? null;
      syncComponentsToSections(`Duplicate ${copies.length} component${copies.length === 1 ? "" : "s"}`, options.deferHistory ? { recordHistory: false, autosave: false } : {});
      return copies;
    }
    function normalizeLayerOrder(nextComponents) {
      return nextComponents.map((component, index) => ({ ...component, zIndex: index + 1 }));
    }
    function reorderComponentLayers(fromId, toId) {
      const fromIndex = components.findIndex((component) => component.id === fromId);
      const toIndex = components.findIndex((component) => component.id === toId);
      if (fromIndex < 0 || toIndex < 0 || fromIndex === toIndex) return;
      const next = [...components];
      const [moved] = next.splice(fromIndex, 1);
      next.splice(toIndex, 0, moved);
      components = normalizeLayerOrder(next);
      syncComponentsToSections("Reorder layers");
    }
    function moveComponentLayer(id, direction) {
      const index = components.findIndex((component) => component.id === id);
      const target = index + direction;
      if (index < 0 || target < 0 || target >= components.length) return;
      reorderComponentLayers(id, components[target].id);
    }
    function moveSelectedLayerToEdge(toFront) {
      const ids = new Set(selectedComponentIds.length > 0 ? selectedComponentIds : selectedComponentId ? [selectedComponentId] : []);
      if (ids.size === 0) return;
      const selected = components.filter((component) => ids.has(component.id));
      const remaining = components.filter((component) => !ids.has(component.id));
      components = normalizeLayerOrder(toFront ? [...remaining, ...selected] : [...selected, ...remaining]);
      syncComponentsToSections(toFront ? "Bring to front" : "Send to back");
    }
    let htmlCode = "";
    let uploadRunning = false;
    let imageAlreadyCropped = false;
    let photoCropFile = null;
    let photoCropUrl = null;
    let photoCropCorners = [];
    let photoCropDragging = -1;
    let photoCropRotation = 0;
    let photoCropIsCorrection = false;
    let photoCropTouchedCorners = [];
    let photoCropTargetPageNo = 1;
    let pendingCropQueue = [];
    let cropReviewRequired = draft.authoring?.invalidations?.mapping_requires_review === true;
    let cropReviewAcknowledged = false;
    let isComposing = false;
    let stageTransitionRunning = false;
    let previewSurface = "canonical";
    let canonicalPreview = draft.persisted_preview ?? null;
    let previewRunning = false;
    let previewError = null;
    let mappingDirty = false;
    let showResetConfirmModal = false;
    let pendingTargetStage = null;
    let marginMm = 15;
    let markerInsetsMm = normalizeMarkerInsets(draft.authoring?.marker?.insets_mm);
    let persistedMarkerInsetsMm = normalizeMarkerInsets(draft.authoring?.marker?.insets_mm);
    let markerInsetsSaving = false;
    let markerPattern = draft.marker_profile_id ? "target" : "square";
    let showHeaderOverlay = false;
    let headerTitle = draft.name || "EXAMINATION ANSWER SHEET";
    let headerSubtitle = "ANSWER SHEET";
    let headerFontSize = 14;
    let headerFontFamily = "Inter, sans-serif";
    let headerColor = "#0f172a";
    let headerAlign = "left";
    let headerX = 25;
    let headerY = 20;
    let headerW = 160;
    let headerH = 15;
    let headerLocked = false;
    let showQrOverlay = qrEnabled;
    let qrX = 162;
    let qrY = 18;
    let qrW = 26;
    let qrH = 26;
    let qrLocked = false;
    let showInstructionsOverlay = false;
    let instructionsContent = "Use 2B pencil only. Shade bubbles completely. Erase cleanly to change.";
    let instructionsFontSize = 9;
    let instructionsFontFamily = "Inter, sans-serif";
    let instructionsColor = "#334155";
    let instX = 25;
    let instY = 250;
    let instW = 90;
    let instH = 28;
    let instLocked = false;
    let showSignatureOverlay = false;
    let signatureContent = "EXAMINEE SIGNATURE";
    let signatureFontSize = 8.5;
    let signatureFontFamily = "Inter, sans-serif";
    let signatureColor = "#475569";
    let sigX = 125;
    let sigY = 250;
    let sigW = 60;
    let sigH = 28;
    let sigLocked = false;
    let plottingConfig = {
      active: false,
      mode: "consecutive",
      areaCode: aptitude_areas?.[0]?.code || "GENERAL",
      itemNumber: 1,
      optionsCount: 4,
      optionLabels: ["A", "B", "C", "D"],
      bubbleDiameterMm: 4.5,
      currentOptionIndex: 0,
      pointsMm: [],
      respotSectionIndex: null,
      respotOptionIndex: 0,
      stampSourceSection: null,
      sizingPreviewDiameterMm: null,
      questionSpanMode: true
    };
    let showMetadataOverlay = false;
    let metadataContent = "ROOM / SEAT NO.";
    let metadataFontSize = 9;
    let metaX = 25;
    let metaY = 45;
    let metaW = 50;
    let metaH = 15;
    let showRollOverlay = false;
    let rollDigits = 6;
    let rollX = 25;
    let rollY = 45;
    let customTextItems = [];
    let canvasLocked = false;
    let sections = [];
    if (stage1Mode !== "html" && !Array.isArray(draft.authoring?.mapping?.field_blocks)) {
      sections = [];
    }
    let activeSectionIndex = 0;
    let selectedSectionIndices = [];
    let bubbleRadiusMm = 4.5;
    let scale = 0.78;
    let panX = 0;
    let panY = 0;
    let currentLockVersion = draft.lock_version ?? 0;
    function syncDraftLockVersion(version) {
      const next = Number(version);
      if (!Number.isInteger(next) || next < 0 || next < currentLockVersion) return;
      currentLockVersion = next;
      draft.lock_version = next;
    }
    let saveStatus = "saved";
    let errorMessage = "";
    let studioSaveIssues = [];
    let autosaveTimer = null;
    function firstErrorText(value) {
      if (typeof value === "string" && value.trim()) return value.trim();
      if (Array.isArray(value)) {
        return value.map(firstErrorText).find(Boolean) ?? "";
      }
      if (value && typeof value === "object") {
        for (const key of ["detail", "message", "error", "reason"]) {
          const text = firstErrorText(value[key]);
          if (text) return text;
        }
      }
      return "";
    }
    function errorCodeFrom(payload) {
      if (!payload || typeof payload !== "object") return "";
      const candidates = [
        payload.code,
        payload.payload?.code,
        payload.error?.code,
        payload.payload?.error?.code
      ];
      return candidates.find((value) => typeof value === "string" && value.trim())?.trim() ?? "";
    }
    function formatPreviewError(payload, fallback = "The answer-sheet print preview could not be generated.") {
      const code = errorCodeFrom(payload);
      const detail = firstErrorText(payload?.detail) || firstErrorText(payload?.payload?.detail) || firstErrorText(payload?.safe_issue?.detail) || firstErrorText(payload?.message) || firstErrorText(payload?.payload?.message) || firstErrorText(payload?.error) || firstErrorText(payload?.payload?.error);
      let resolvedDetail = detail;
      const isGeneric = !resolvedDetail || resolvedDetail === "The OMR worker rejected the layout verification." || resolvedDetail === "The request was rejected." || resolvedDetail === "Preview failed";
      if (isGeneric && code) {
        if (code === "marker_profile_unqualified") {
          resolvedDetail = "This marker profile must be qualified before compile.";
        } else if (code.includes("marker")) {
          resolvedDetail = "Fiducial markers could not be detected or do not match the selected marker profile.";
        } else if (code.includes("dimension")) {
          resolvedDetail = "Sheet dimensions or margins exceed printable page boundaries.";
        } else if (code.includes("bound") || code.includes("shift") || code.includes("overflow")) {
          resolvedDetail = "Mapped bubble fields extend beyond safe printable margins.";
        } else if (code.includes("unavailable") || code.includes("timeout")) {
          resolvedDetail = "The OMR recognition engine is currently unavailable. Retry in a moment.";
        } else if (code.includes("invalid")) {
          resolvedDetail = "The OMR recognition engine returned an unreadable response.";
        }
      }
      return { code, detail: resolvedDetail || fallback };
    }
    function typedRequestError(payload, fallback) {
      const parsed = formatPreviewError(payload, fallback);
      const error2 = new Error(parsed.detail);
      if (parsed.code) error2.code = parsed.code;
      error2.payload = payload;
      return error2;
    }
    let studioSaveQueue = Promise.resolve(true);
    let totalQuestionsCount = derived(() => sections.reduce((sum, s) => sum + (Number(s.questionsCount) || 0), 0));
    function normalizeStudioIssues(payload, fallback = "Studio save was rejected.") {
      if (Array.isArray(payload?.errors)) {
        const structured = payload.errors.map((error2) => {
          const message = firstErrorText(error2);
          return message ? { ...error2 && typeof error2 === "object" ? error2 : {}, message } : null;
        }).filter(Boolean);
        if (structured.length > 0) return structured;
      }
      if (payload?.errors && typeof payload.errors === "object") {
        const messages = Object.values(payload.errors).map(firstErrorText).filter(Boolean);
        if (messages.length > 0) return messages.map((message) => ({ message }));
      }
      const detail = firstErrorText(payload?.detail) || firstErrorText(payload?.message) || fallback;
      if (detail.trim().startsWith("[")) {
        try {
          const decoded = JSON.parse(detail);
          if (Array.isArray(decoded)) return normalizeStudioIssues({ errors: decoded }, fallback);
        } catch {
        }
      }
      return [{ message: detail }];
    }
    function readEditorState() {
      return {
        components: snapshot(components),
        sections: snapshot(sections),
        documentFields: snapshot(documentFields),
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
          customTextItems: snapshot(customTextItems)
        }
      };
    }
    let historyNotice = "";
    const studioHistory = createStudioHistory(readEditorState(), 100);
    let historyStatus = derived(() => {
      return studioHistory.status();
    });
    function recordHistory(label, options = {}) {
      if (studioHistory.record(readEditorState(), label, options)) ;
    }
    function applyEditorState(nextState, notice = "") {
      components = nextState.components;
      sections = nextState.sections;
      documentFields = nextState.documentFields ?? [];
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
      htmlCode = components.length > 0 ? renderCurrentHtml() : "";
      selectedCanvasIds = selectedCanvasIds.filter((id) => id === QR_CANVAS_ITEM_ID && showQrOverlay || components.some((component) => component.id === id));
      selectedComponentIds = selectedCanvasIds.filter((id) => id !== QR_CANVAS_ITEM_ID);
      if (!components.some((component) => component.id === selectedComponentId)) selectedComponentId = selectedComponentIds.at(-1) ?? null;
      activeSectionIndex = Math.min(activeSectionIndex, Math.max(0, sections.length - 1));
      if (stage1Mode !== "html") {
        declaredInventory = syncDeclaredInventoryFromSections(sections, declaredInventory);
      }
      plottingConfig.itemNumber = nextItemNumberForArea(sections, plottingConfig.areaCode);
      invalidateCanonicalPreview();
      historyNotice = notice;
      triggerAutosave();
    }
    function undoEditor() {
      if (isReadOnly()) return;
      const state = studioHistory.undo();
      if (state) {
        applyEditorState(state, `Undid ${studioHistory.status().redoLabel ?? "edit"}`);
      }
    }
    function redoEditor() {
      if (isReadOnly()) return;
      const state = studioHistory.redo();
      if (state) {
        applyEditorState(state, `Redid ${studioHistory.status().undoLabel ?? "edit"}`);
      }
    }
    function invalidateCanonicalPreview() {
      if (currentStage >= 3) {
        mappingDirty = true;
        previewError = null;
      }
    }
    function syncUploadedDeclarationsFromSections() {
      if (stage1Mode === "html") return;
      if (sections.some((s) => s && s.type !== "roll" && !s.identity_grid && s.area_code)) {
        declaredInventory = syncDeclaredInventoryFromSections(sections, declaredInventory);
      }
      const identityGrid = sections.find((s) => s && s.identity_grid && s.type === "roll");
      if (identityGrid) {
        const digitCount = Number(identityGrid.questionsCount);
        if (!identityDeclaration?.method || identityDeclaration.method === "shaded_grid" && (identityDeclaration.digit_count == null || identityDeclaration.digit_count === "")) {
          identityDeclaration = {
            ...identityDeclaration,
            method: "shaded_grid",
            digit_count: Number.isInteger(digitCount) && digitCount >= 1 ? digitCount : 6
          };
        }
      }
    }
    function handleGeometryChanged() {
      syncUploadedDeclarationsFromSections();
      invalidateCanonicalPreview();
      recordHistory("Move or resize overlay", { coalesceKey: "geometry" });
      triggerAutosave();
    }
    function recordBubbleEdit(changeKind = "mapping") {
      normalizeSectionsForRecognition();
      syncUploadedDeclarationsFromSections();
      if (changeKind === "mapping") invalidateCanonicalPreview();
      recordHistory(
        changeKind === "lock" ? "Lock or unlock recognition guide" : "Edit recognition mapping",
        { coalesceKey: `section:${activeSectionIndex}:${changeKind}` }
      );
      triggerAutosave();
    }
    function handleStudioKeydown(event) {
      if (event.repeat || event.isComposing) return;
      const tagName = event.target?.tagName?.toLowerCase?.() ?? "";
      if (["input", "textarea", "select"].includes(tagName) || event.target?.isContentEditable) return;
      if (isReadOnly()) return;
      const key = event.key.toLowerCase();
      const modifier = event.ctrlKey || event.metaKey;
      const isSheetDesigner = currentStage === 1 && stage1Mode === "html" && designerSubTab === "designer";
      if ((key === "delete" || key === "backspace") && isSheetDesigner) {
        event.preventDefault();
        removeSelectedComponents();
        return;
      }
      if (!modifier || event.altKey) return;
      if (key === "d" && !event.shiftKey && isSheetDesigner) {
        event.preventDefault();
        duplicateSelectedComponents();
      } else if (key === "z" && !event.shiftKey) {
        event.preventDefault();
        undoEditor();
      } else if (key === "z" && event.shiftKey || key === "y" && !event.shiftKey) {
        event.preventDefault();
        redoEditor();
      } else if (key === "]" && event.shiftKey && isSheetDesigner) {
        event.preventDefault();
        moveSelectedLayerToEdge(true);
      } else if (key === "[" && event.shiftKey && isSheetDesigner) {
        event.preventDefault();
        moveSelectedLayerToEdge(false);
      } else if (key === "]" && isSheetDesigner) {
        event.preventDefault();
        moveComponentLayer(selectedComponentId, 1);
      } else if (key === "[" && isSheetDesigner) {
        event.preventDefault();
        moveComponentLayer(selectedComponentId, -1);
      }
    }
    onDestroy(() => {
      if (autosaveTimer) clearTimeout(autosaveTimer);
      window.removeEventListener("keydown", handleStudioKeydown);
      clearPhotoCrop();
    });
    async function requestGoToStage(step) {
      const targetStage = normalizeStudioStage(step, stage1Mode);
      if (isReadOnly()) {
        executeGoToStage(targetStage);
        return;
      }
      if (targetStage === 4 && cropReviewRequired) {
        historyNotice = "Review and realign every guide against the corrected crop before verification.";
        return;
      }
      if (stageTransitionRunning || previewRunning || isComposing) return;
      if ((targetStage === 1 || targetStage === 2) && currentStage >= 3 && draft.raster_url) {
        pendingTargetStage = targetStage;
        showResetConfirmModal = true;
        return;
      }
      if (targetStage === 3 && stage1Mode !== "html" && currentStage === 2 && !canonicalPreview) {
        const composed = await composeUploadedSource();
        if (!composed) return;
      }
      executeGoToStage(targetStage);
    }
    function syncSectionsFromComponentsForBubbleStage() {
      normalizeComponentsForCanvas();
      const existingSections = new Map(sections.map((section) => [section.id, section]));
      const nextSections = components.map((component) => componentToSectionData(component)).filter((section) => section !== null).map((section) => normalizeRecognitionSection(existingSections.has(section.id) ? {
        ...section,
        ...existingSections.get(section.id),
        id: section.id
      } : section));
      sections = nextSections;
      activeSectionIndex = Math.min(activeSectionIndex, Math.max(0, nextSections.length - 1));
    }
    function previewMarkerInsets(nextInsets) {
      markerInsetsMm = normalizeMarkerInsets(nextInsets);
      if (components.length > 0) htmlCode = renderCurrentHtml();
    }
    async function selectMarkerProfile(profileId, requestedInsets = null) {
      if (!profileId || !urls.mapping || markerInsetsSaving) return;
      const markerUrl = `${urls.mapping.replace(/\/mapping$/, "")}/marker-profile/${profileId}`;
      if (!usesFiducials) return;
      const nextInsets = normalizeMarkerInsets(requestedInsets ?? DEFAULT_MARKER_INSETS_MM);
      const rollbackInsets = { ...persistedMarkerInsetsMm };
      markerInsetsSaving = true;
      try {
        const response = await fetch(markerUrl, {
          method: "PUT",
          headers: {
            "Content-Type": "application/json",
            Accept: "application/json",
            ...csrfHeaders(),
            "X-Requested-With": "XMLHttpRequest"
          },
          body: JSON.stringify({
            marker_profile_id: profileId,
            insets: nextInsets,
            lock_version: currentLockVersion,
            idempotency_key: newIdempotencyKey()
          })
        });
        const body = await response.json().catch(() => ({}));
        if (body?.lock_version != null) syncDraftLockVersion(body.lock_version);
        if (!response.ok) throw typedRequestError(body, "Fiducial settings could not be saved.");
        draft.marker_profile_id = profileId;
        markerPattern = "target";
        markerInsetsMm = { ...nextInsets };
        persistedMarkerInsetsMm = { ...nextInsets };
        draft.authoring ??= {};
        draft.authoring.marker = {
          ...draft.authoring.marker ?? {},
          profile_id: profileId,
          insets_mm: { ...nextInsets }
        };
        if (components.length > 0) htmlCode = renderCurrentHtml();
        canonicalPreview = null;
        previewError = null;
        mappingDirty = false;
      } catch (error2) {
        markerInsetsMm = rollbackInsets;
        if (components.length > 0) htmlCode = renderCurrentHtml();
        previewError = formatPreviewError(error2, error2?.message || "Fiducial settings could not be saved.");
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
        if (!urls.preview) throw new Error("The print preview route is unavailable.");
        if (usesFiducials && !draft.marker_profile_id) throw new Error("Select a marker profile before entering Recognition Mapping.");
        const response = await fetch(urls.preview, {
          method: "POST",
          headers: {
            "Content-Type": "application/json",
            Accept: "application/json",
            ...csrfHeaders(),
            "X-Requested-With": "XMLHttpRequest"
          },
          body: JSON.stringify({ lock_version: currentLockVersion })
        });
        const body = await response.json().catch(() => ({}));
        if (body?.lock_version != null) {
          syncDraftLockVersion(body.lock_version);
        }
        if (!response.ok) {
          const parsed = formatPreviewError(body, "The answer-sheet print preview was rejected.");
          canonicalPreview = null;
          previewError = parsed;
          historyNotice = parsed.detail;
          errorMessage = parsed.detail;
          saveStatus = "error";
          return false;
        }
        canonicalPreview = body;
        mappingDirty = false;
        draft.target_current = true;
        return true;
      } catch (error2) {
        canonicalPreview = null;
        const parsed = formatPreviewError(error2, error2?.message || "The answer-sheet print preview could not be generated.");
        previewError = parsed;
        historyNotice = parsed.detail;
        errorMessage = parsed.detail;
        saveStatus = "error";
        return false;
      } finally {
        previewRunning = false;
      }
    }
    async function composeUploadedSource(options = {}) {
      if (stage1Mode === "html") return true;
      if (!urls.compose) {
        previewError = "The source composition route is unavailable.";
        return false;
      }
      if (!await performSave()) return false;
      isComposing = true;
      try {
        const isFullBleed = !modifyUploadedDocument || Boolean(options?.fullBleed);
        const margins = isFullBleed ? { top: 0, right: 0, bottom: 0, left: 0 } : {
          top: Number(marginMm) || 0,
          right: Number(marginMm) || 0,
          bottom: Number(marginMm) || 0,
          left: Number(marginMm) || 0
        };
        const response = await fetch(urls.compose, {
          method: "POST",
          headers: {
            "Content-Type": "application/json",
            Accept: "application/json",
            ...csrfHeaders(),
            "X-Requested-With": "XMLHttpRequest"
          },
          body: JSON.stringify({
            paper_width_mm: 210,
            paper_height_mm: 297,
            content_margin_mm: margins,
            printer_safe_margin_mm: { top: 0, right: 0, bottom: 0, left: 0 },
            ...usesFiducials ? {
              marker_inset_mm: { top: 12, right: 12, bottom: 14, left: 12 }
            } : {},
            alignment: "center",
            source_dpi: 300,
            idempotency_key: newIdempotencyKey()
          })
        });
        const body = await response.json().catch(() => ({}));
        if (body?.lock_version != null) syncDraftLockVersion(body.lock_version);
        if (!response.ok) {
          throw typedRequestError(body, "Source composition failed.");
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
      } catch (error2) {
        previewError = formatPreviewError(error2, error2?.message || "Source composition failed.");
        errorMessage = previewError.detail;
        saveStatus = "error";
        return false;
      } finally {
        isComposing = false;
      }
    }
    async function executeGoToStage(step) {
      const targetStage = normalizeStudioStage(step, stage1Mode);
      if (isReadOnly()) {
        currentStage = targetStage;
        return;
      }
      const previousStage = normalizeStudioStage(currentStage, stage1Mode);
      stageTransitionRunning = true;
      try {
        if (targetStage === 3 && stage1Mode === "html") {
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
        const hasMappingIntent = stage1Mode === "html" || sections.length > 0;
        const previewNeedsVerification = !canonicalPreview || mappingDirty || Boolean(previewError && (typeof previewError === "string" ? previewError : previewError.detail));
        if (saved && hasMappingIntent && !cropReviewRequired && (targetStage === 3 && previousStage < 3 && previewNeedsVerification || targetStage === 4 && previousStage === 3 && previewNeedsVerification)) {
          const previewed = await runCanonicalPreview();
          if (!previewed) {
            currentStage = previousStage;
            historyNotice = typeof previewError === "object" && previewError?.detail ? previewError.detail : previewError || "";
            return;
          }
        }
        if (!saved) {
          currentStage = previousStage;
          historyNotice = errorMessage || "Could not save this stage transition. Your layout remains open.";
        }
      } finally {
        stageTransitionRunning = false;
      }
    }
    function triggerAutosave() {
      if (isReadOnly() || !draft.can_edit) return;
      saveStatus = "saving";
      if (autosaveTimer) clearTimeout(autosaveTimer);
      autosaveTimer = setTimeout(
        async () => {
          await performSave();
        },
        600
      );
    }
    function buildBlockList() {
      normalizeComponentsForCanvas();
      normalizeSectionsForRecognition();
      const blocks = sections.map((s) => {
        if (s.type === "roll") {
          const digits = Number(s.questionsCount) || 7;
          const geometry2 = effectiveRecognitionGeometryMm({
            kind: "roll",
            bubbleDiameterMm: s.bubbleDiameterMm,
            bubblePitchMm: s.bubblePitchMm,
            rowPitchMm: s.rowPitchMm
          });
          return {
            id: s.id,
            type: "roll",
            page_no: Number(s.page_no) || 1,
            x_mm: Number(s.posX) || 110,
            y_mm: Number(s.posY) || 28,
            w_mm: Number((geometry2.bubbleDiameterMm + (digits - 1) * geometry2.bubblePitchMm + 1.5).toFixed(1)),
            h_mm: Number((geometry2.bubbleDiameterMm + (10 - 1) * geometry2.rowPitchMm + 1.5).toFixed(1)),
            digits,
            identity_grid: s.identity_grid === true,
            rotation_deg: Number(s.rotationDeg) || 0,
            roll_direction: s.rollDirection === "horizontal" ? "horizontal" : "vertical",
            bubble_diameter_mm: geometry2.bubbleDiameterMm,
            bubble_pitch_mm: geometry2.bubblePitchMm,
            row_pitch_mm: geometry2.rowPitchMm
          };
        }
        let opts = Number(s.optionsCount) || 4;
        if (s.type === "tf") opts = 2;
        if (s.type === "likert") opts = 5;
        if (s.type === "custom") opts = (s.customValuesStr || "").split(",").length;
        const geometry = effectiveRecognitionGeometryMm({
          kind: "grid",
          bubbleDiameterMm: s.bubbleDiameterMm,
          bubblePitchMm: s.bubblePitchMm,
          rowPitchMm: s.rowPitchMm
        });
        const qCount = Number(s.questionsCount) || 25;
        const { w_mm, h_mm } = qCount === 1 ? {
          w_mm: Number((geometry.bubbleDiameterMm + Math.max(0, opts - 1) * geometry.bubblePitchMm).toFixed(2)),
          h_mm: Number(geometry.bubbleDiameterMm.toFixed(2))
        } : contentBlockSizeMm(opts, qCount, geometry.bubblePitchMm, geometry.rowPitchMm, geometry.bubbleDiameterMm);
        return {
          id: s.id,
          area_code: s.area_code || s.aptitudeAreaCode,
          subtest_code: s.subtest_code ?? void 0,
          test_instrument_subtest_id: s.test_instrument_subtest_id != null ? Number(s.test_instrument_subtest_id) : void 0,
          type: s.type,
          page_no: Number(s.page_no) || 1,
          x_mm: s.posX != null && Number.isFinite(Number(s.posX)) ? Number(s.posX) : 15,
          y_mm: s.posY != null && Number.isFinite(Number(s.posY)) ? Number(s.posY) : 84,
          w_mm: qCount === 1 ? w_mm : Math.max(w_mm, Number(s.w_mm) || 0),
          h_mm: qCount === 1 ? h_mm : Math.max(h_mm, Number(s.h_mm) || 0),
          questions: qCount,
          cols: Number(s.cols) || 1,
          options: opts,
          start_q: Number(s.startQ) || 1,
          custom_values: s.type === "custom" ? (s.customValuesStr || "").split(",").map((v) => v.trim()) : void 0,
          bubble_diameter_mm: geometry.bubbleDiameterMm,
          bubble_pitch_mm: geometry.bubblePitchMm,
          row_pitch_mm: geometry.rowPitchMm,
          option_points_mm: qCount === 1 && Array.isArray(s.optionPointsMm) && s.optionPointsMm.length === opts ? s.optionPointsMm.map((point) => ({
            label: point.label,
            x_mm: Number(point.x_mm),
            y_mm: Number(point.y_mm)
          })) : void 0,
          rotation_deg: Number(s.rotationDeg) || 0
        };
      });
      if (showRollOverlay) {
        const digits = Math.max(2, Math.min(10, Number(rollDigits) || 6));
        const geometry = effectiveRecognitionGeometryMm({ kind: "roll" });
        blocks.push({
          id: "Student_ID",
          type: "roll",
          page_no: activePageNo,
          x_mm: rollX,
          y_mm: rollY,
          w_mm: Number((geometry.bubbleDiameterMm + (digits - 1) * geometry.bubblePitchMm + 1.5).toFixed(1)),
          h_mm: Number((geometry.bubbleDiameterMm + (10 - 1) * geometry.rowPitchMm + 1.5).toFixed(1)),
          digits,
          bubble_diameter_mm: geometry.bubbleDiameterMm,
          bubble_pitch_mm: geometry.bubblePitchMm,
          row_pitch_mm: geometry.rowPitchMm
        });
      }
      const overlayBlocks = [];
      if (qrEnabled && showQrOverlay) {
        overlayBlocks.push({
          id: "Exam_QR",
          type: "qr",
          page_no: activePageNo,
          tag: "blank_instance_qr",
          scope: "blank_instance",
          x_mm: qrX,
          y_mm: qrY,
          w_mm: qrW,
          h_mm: qrH
        });
      }
      if (showHeaderOverlay) {
        overlayBlocks.push({
          id: "Header_Text",
          type: "header",
          page_no: activePageNo,
          tag: "header_title",
          scope: "template",
          x_mm: headerX,
          y_mm: headerY,
          w_mm: headerW,
          h_mm: headerH,
          font_size: headerFontSize,
          content: headerTitle
        });
      }
      if (showSignatureOverlay) {
        overlayBlocks.push({
          id: "Signature_Area",
          type: "signature",
          page_no: activePageNo,
          tag: "signature_box",
          scope: "template",
          x_mm: sigX,
          y_mm: sigY,
          w_mm: sigW,
          h_mm: sigH,
          font_size: signatureFontSize,
          content: signatureContent
        });
      }
      if (showInstructionsOverlay) {
        overlayBlocks.push({
          id: "Instructions_Box",
          type: "instructions",
          page_no: activePageNo,
          tag: "instructions_box",
          scope: "template",
          x_mm: instX,
          y_mm: instY,
          w_mm: instW,
          h_mm: instH,
          font_size: instructionsFontSize,
          content: instructionsContent
        });
      }
      if (showMetadataOverlay) {
        overlayBlocks.push({
          id: "Metadata_Box",
          type: "metadata",
          page_no: activePageNo,
          tag: "metadata_box",
          scope: "template",
          x_mm: metaX,
          y_mm: metaY,
          w_mm: metaW,
          h_mm: metaH,
          font_size: metadataFontSize,
          content: metadataContent
        });
      }
      return { blocks, overlayBlocks };
    }
    function performSave(sourceKindOverride = null) {
      if (isReadOnly()) return false;
      const safeSourceKindOverride = typeof sourceKindOverride === "string" ? sourceKindOverride : null;
      saveStatus = "saving";
      const queuedSave = studioSaveQueue.then(() => saveStudioState(safeSourceKindOverride), () => saveStudioState(safeSourceKindOverride));
      studioSaveQueue = queuedSave.catch(() => false);
      return queuedSave;
    }
    async function saveStudioState(sourceKindOverride = null) {
      if (isReadOnly()) return false;
      const saveUrl = urls.studio || `/setup/exam-materials/answer-sheets/${draft.id}`;
      const persistedSourceKind = sourceKindOverride ?? baseType;
      try {
        const { blocks, overlayBlocks } = buildBlockList();
        const clientValidation = validateStudioLayout({ width_mm: 210, height_mm: 297, margin_mm: marginMm }, { top_mm: 12, right_mm: 12, bottom_mm: 14, left_mm: 12 }, blocks);
        if (!clientValidation.valid) {
          studioSaveIssues = clientValidation.warnings.filter((warning) => warning.severity === "error").map((warning) => ({
            code: warning.code,
            block: warning.block_id,
            message: warning.message
          }));
          saveStatus = "error";
          errorMessage = studioSaveIssues[0]?.message || "Recognition guides overlap. Move the selected guide before saving.";
          error(errorMessage || "Failed to save studio state.", { duration: 0, dedupeKey: "studio-save-error" });
          return false;
        }
        const nativeMapping = emitNativeMapping(210, 297, blocks, Number(draft.requested_page_count) || orderedPages.length || 1, stage1Mode !== "html" ? { minBubbleMm: 2.5 } : void 0);
        syncUploadedDeclarationsFromSections();
        if (stage1Mode !== "html") {
          nativeMapping.declared_inventory = declaredInventory.map((entry) => ({
            page_no: Number(entry.page_no),
            area_code: String(entry.area_code ?? "").trim(),
            question_count: entry.question_count === "" || entry.question_count == null ? null : Number(entry.question_count),
            option_labels: Array.isArray(entry.option_labels) ? entry.option_labels : []
          }));
          nativeMapping.identity = identityDeclaration;
        }
        const overlayBoxes = emitOverlayBoxes(overlayBlocks, 210, 297);
        const studioPayload = {
          current_stage: currentStage,
          active_page_no: activePageNo,
          sections,
          components,
          declaredInventory,
          identity: identityDeclaration,
          document_fields: documentFields,
          modifyUploadedDocument,
          marginMm,
          ...usesFiducials ? { markerPattern } : {},
          bubbleRadiusMm,
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
          ...qrEnabled ? { showQrOverlay, qrX, qrY, qrW, qrH, qrLocked } : {},
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
          ...cropReviewRequired && cropReviewAcknowledged ? { reviewed_crop_asset_id: Number(orderedPages[0]?.asset_id) } : {},
          palette_filter: componentPaletteFilter()
        };
        const res = await fetch(saveUrl, {
          method: "PUT",
          headers: {
            "Content-Type": "application/json",
            Accept: "application/json",
            ...csrfHeaders(),
            "X-Requested-With": "XMLHttpRequest"
          },
          body: JSON.stringify({
            mapping: nativeMapping,
            overlay: overlayBoxes,
            studio: studioPayload,
            source_kind: persistedSourceKind,
            lock_version: currentLockVersion,
            idempotency_key: newIdempotencyKey()
          })
        });
        if (res.ok) {
          const data2 = await res.json();
          if (data2?.lock_version != null) {
            syncDraftLockVersion(data2.lock_version);
          }
          draft.source_kind = persistedSourceKind;
          draft.stage = data2.stage ?? draft.stage;
          draft.mapping_current = data2.mapping_current ?? ["mapping_complete", "digital_verified", "compiled"].includes(draft.stage);
          draft.target_current = data2.target_current ?? draft.target_current;
          studioSaveIssues = [];
          saveStatus = "saved";
          errorMessage = "";
          if (cropReviewRequired && cropReviewAcknowledged) {
            cropReviewRequired = false;
            cropReviewAcknowledged = false;
          }
          if (onMappingSaved) onMappingSaved();
          return true;
        }
        const data = await res.json().catch(() => ({}));
        if (data?.lock_version != null) {
          syncDraftLockVersion(data.lock_version);
        }
        saveStatus = "error";
        studioSaveIssues = normalizeStudioIssues(data, "Failed to save studio state.");
        errorMessage = data?.detail || data?.message || studioSaveIssues[0]?.message || "Failed to save studio state.";
        error(errorMessage || "Failed to save studio state.", { duration: 0, dedupeKey: "studio-save-error" });
        return false;
      } catch (err) {
        studioSaveIssues = [
          {
            message: err?.message || "Network error while saving studio state."
          }
        ];
        saveStatus = "error";
        errorMessage = studioSaveIssues[0].message;
        error(errorMessage || "Failed to save studio state.", { duration: 0, dedupeKey: "studio-save-error" });
        return false;
      }
    }
    function clearPhotoCrop() {
      if (photoCropUrl) URL.revokeObjectURL(photoCropUrl);
      photoCropUrl = null;
      photoCropFile = null;
      photoCropDragging = -1;
      photoCropRotation = 0;
      photoCropIsCorrection = false;
      photoCropTouchedCorners = [];
    }
    let $$settled = true;
    let $$inner_renderer;
    function $$render_inner($$renderer3) {
      $$renderer3.push(`<div class="studio-shell bg-muted absolute inset-0 overflow-hidden select-none svelte-e90e77"><div class="bg-background/95 fixed inset-0 z-[100] flex flex-col items-center justify-center p-6 text-center backdrop-blur-md lg:hidden"><div class="border-border bg-card max-w-sm space-y-4 rounded-2xl border p-6 shadow-2xl"><div class="mx-auto flex size-12 items-center justify-center rounded-2xl bg-amber-500/15 text-amber-600 dark:text-amber-400">`);
      Monitor($$renderer3, { class: "size-6" });
      $$renderer3.push(`<!----></div> <div class="space-y-1.5"><h3 class="text-foreground font-sans text-base font-bold">Desktop Screen Required</h3> <p class="text-muted-foreground text-xs leading-relaxed">Answer Sheet Studio requires a desktop or large display to
                    design optical bubble grids and fiducial markers. This
                    feature is not available on mobile screens.</p></div> <div class="pt-2"><a href="/setup/exam-materials" class="btn-tactile-primary inline-flex w-full items-center justify-center gap-1.5 rounded-xl py-2.5 text-xs font-semibold shadow-xs">`);
      Arrow_left($$renderer3, { class: "size-3.5" });
      $$renderer3.push(`<!----> <span>Return to Exam Materials</span></a></div></div></div> `);
      if (!hasChosenMode) {
        $$renderer3.push("<!--[0-->");
        $$renderer3.push(`<div class="bg-background absolute inset-0 z-[60] flex items-center justify-center p-6"><div class="w-full max-w-2xl space-y-6"><div class="space-y-1.5 text-center"><h2 class="text-foreground text-xl font-black">Start a New Template</h2> <p class="text-muted-foreground text-sm">Choose how you want to create this answer sheet. This
                        choice defines your workflow.</p></div> <div class="grid grid-cols-2 gap-4"><button type="button" class="group bg-card border-primary/60 space-y-3 rounded-2xl border-2 p-6 text-left shadow-sm transition hover:border-blue-500 hover:shadow-md"><div class="flex items-center justify-between gap-2"><div class="flex size-11 items-center justify-center rounded-xl bg-blue-500/10 text-blue-600 dark:text-blue-400">`);
        Upload($$renderer3, { class: "size-5" });
        $$renderer3.push(`<!----></div> <span class="rounded-full bg-blue-500/10 px-2.5 py-0.5 text-[10px] font-bold tracking-wide text-blue-700 uppercase dark:text-blue-300">Default · Licensed Stock</span></div> <div><h3 class="text-foreground text-sm font-black">Upload Existing Sheet</h3> <p class="text-muted-foreground mt-1 text-xs leading-relaxed">${escape_html(Number(draft.requested_page_count) > 1 ? `Register an existing ${draft.requested_page_count}-page supplier PDF or Word document as a private recognition template.` : "Register a supplier-issued PDF, Word document, or photographed answer sheet as a private recognition template without reproducing it.")}</p></div> <span class="text-[11px] font-bold text-blue-700 group-hover:underline">Upload a file →</span></button> <button type="button" class="group bg-card border-border space-y-3 rounded-2xl border-2 p-6 text-left shadow-sm transition hover:border-emerald-500 hover:shadow-md"><div class="flex size-11 items-center justify-center rounded-xl bg-emerald-500/10 text-emerald-600 dark:text-emerald-400">`);
        Sliders_vertical($$renderer3, { class: "size-5" });
        $$renderer3.push(`<!----></div> <div><h3 class="text-foreground text-sm font-black">Sheet Designer</h3> <p class="text-muted-foreground mt-1 text-xs leading-relaxed">${escape_html(Number(draft.requested_page_count) > 1 ? `Build a ${draft.requested_page_count}-page institution-owned answer sheet with page navigation, candidate fields, ID matrix, and question grids.` : "Build an institution-owned answer sheet from scratch with headers, candidate fields, ID matrix, question grids, and permitted printing.")}</p></div> <span class="text-[11px] font-bold text-emerald-700 group-hover:underline">Start designing →</span></button></div></div></div>`);
      } else {
        $$renderer3.push("<!--[-1-->");
      }
      $$renderer3.push(`<!--]--> <div class="studio-workspace bg-muted absolute inset-0 overflow-hidden svelte-e90e77"><div${attr_class(`absolute top-20 ${"left-4 lg:left-[398px]"} z-30 flex items-center gap-1.5 rounded-xl border border-slate-200 bg-white/95 p-1.5 shadow-md backdrop-blur`, "svelte-e90e77")} aria-label="Answer sheet pages"><!--[-->`);
      const each_array = ensure_array_like(orderedPages);
      for (let $$index = 0, $$length = each_array.length; $$index < $$length; $$index++) {
        let page = each_array[$$index];
        const isCurrentActive = activePageNo === Number(page.page_no);
        const isPageLoading = isCurrentActive && !sheetImageLoaded && stage1Mode !== "html";
        $$renderer3.push(`<button type="button"${attr_class(
          `flex items-center gap-1.5 rounded-lg px-3 py-1.5 text-xs font-bold transition ${isCurrentActive ? "bg-slate-900 text-white" : "text-slate-600 hover:bg-slate-100"}`,
          "svelte-e90e77"
        )}${attr("aria-pressed", isCurrentActive)}${attr("aria-busy", isPageLoading)}${attr("data-omr-page-btn", page.page_no)}>`);
        if (isPageLoading) {
          $$renderer3.push("<!--[0-->");
          Loader_circle($$renderer3, {
            class: "size-3 animate-spin text-white/90 shrink-0",
            "aria-hidden": "true"
          });
        } else {
          $$renderer3.push("<!--[-1-->");
        }
        $$renderer3.push(`<!--]--> <span>Page ${escape_html(page.page_no)}</span> `);
        if (isPageLoading) {
          $$renderer3.push("<!--[0-->");
          $$renderer3.push(`<span class="sr-only">(Loading…)</span>`);
        } else {
          $$renderer3.push("<!--[-1-->");
        }
        $$renderer3.push(`<!--]--></button>`);
      }
      $$renderer3.push(`<!--]--> <span class="mx-0.5 h-5 w-px bg-slate-200"></span> <button type="button"${attr("disabled", isReadOnly() || activePageNo === 1 || pageMutationRunning, true)} class="rounded-md p-1 text-slate-500 hover:bg-slate-100 disabled:opacity-30" title="Move page left" aria-label="Move page left">`);
      Chevron_left($$renderer3, { class: "size-3.5" });
      $$renderer3.push(`<!----></button> <button type="button"${attr("disabled", isReadOnly() || activePageNo === orderedPages.length || pageMutationRunning, true)} class="rounded-md p-1 text-slate-500 hover:bg-slate-100 disabled:opacity-30" title="Move page right" aria-label="Move page right">`);
      Chevron_right($$renderer3, { class: "size-3.5" });
      $$renderer3.push(`<!----></button> <button type="button"${attr("disabled", isReadOnly() || orderedPages.length >= 4 || pageMutationRunning, true)} class="rounded-md p-1 text-emerald-700 hover:bg-emerald-50 disabled:opacity-30" title="Add page" aria-label="Add page">`);
      Plus($$renderer3, { class: "size-3.5" });
      $$renderer3.push(`<!----></button> <button type="button"${attr("disabled", isReadOnly() || orderedPages.length <= 1 || pageMutationRunning, true)} class="rounded-md p-1 text-rose-600 hover:bg-rose-50 disabled:opacity-30" title="Delete active page" aria-label="Delete active page">`);
      Trash_2($$renderer3, { class: "size-3.5" });
      $$renderer3.push(`<!----></button></div> `);
      StudioCanvas($$renderer3, {
        readOnly: isReadOnly(),
        markerInsetsMm,
        showFiducials: usesFiducials,
        activePageNo,
        rasterUrl: activePageRasterUrl() ?? (activePageNo === 1 ? draft.raster_url : null),
        htmlContent: activePageHtml(),
        canonicalPreview,
        mappingPreviewStale: mappingDirty || Boolean(previewError),
        isHtmlMode: stage1Mode === "html",
        isUploadedSource: stage1Mode !== "html",
        previewSurface,
        showCanonicalPrintPreview: currentStage === 1 && designerSubTab === "print_preview",
        isComposing: isComposing || previewRunning,
        showHeaderOverlay,
        headerTitle,
        headerSubtitle,
        showQrOverlay,
        showInstructionsOverlay,
        instructionsContent,
        showSignatureOverlay,
        signatureContent,
        onIdentityChanged: (value) => {
          identityDeclaration = value;
          recordBubbleEdit();
        },
        currentStage,
        onComponentSelected: handleCanvasComponentSelected,
        onCanvasItemSelected: handleCanvasItemSelected,
        onCanvasSelectionClear: clearComponentSelection,
        onDuplicateComponents: duplicateSelectedComponents,
        onGeometryChanged: handleGeometryChanged,
        onDocumentFieldsChanged: () => recordBubbleEdit(),
        onComponentGeometryChanged: syncComponentsToSections,
        get scale() {
          return scale;
        },
        set scale($$value) {
          scale = $$value;
          $$settled = false;
        },
        get panX() {
          return panX;
        },
        set panX($$value) {
          panX = $$value;
          $$settled = false;
        },
        get panY() {
          return panY;
        },
        set panY($$value) {
          panY = $$value;
          $$settled = false;
        },
        get marginMm() {
          return marginMm;
        },
        set marginMm($$value) {
          marginMm = $$value;
          $$settled = false;
        },
        get markerPattern() {
          return markerPattern;
        },
        set markerPattern($$value) {
          markerPattern = $$value;
          $$settled = false;
        },
        get documentFields() {
          return documentFields;
        },
        set documentFields($$value) {
          documentFields = $$value;
          $$settled = false;
        },
        get activeDocumentFieldId() {
          return activeDocumentFieldId;
        },
        set activeDocumentFieldId($$value) {
          activeDocumentFieldId = $$value;
          $$settled = false;
        },
        get canvasLocked() {
          return canvasLocked;
        },
        set canvasLocked($$value) {
          canvasLocked = $$value;
          $$settled = false;
        },
        get sheetImageLoaded() {
          return sheetImageLoaded;
        },
        set sheetImageLoaded($$value) {
          sheetImageLoaded = $$value;
          $$settled = false;
        },
        get headerFontSize() {
          return headerFontSize;
        },
        set headerFontSize($$value) {
          headerFontSize = $$value;
          $$settled = false;
        },
        get headerFontFamily() {
          return headerFontFamily;
        },
        set headerFontFamily($$value) {
          headerFontFamily = $$value;
          $$settled = false;
        },
        get headerColor() {
          return headerColor;
        },
        set headerColor($$value) {
          headerColor = $$value;
          $$settled = false;
        },
        get headerAlign() {
          return headerAlign;
        },
        set headerAlign($$value) {
          headerAlign = $$value;
          $$settled = false;
        },
        get headerX() {
          return headerX;
        },
        set headerX($$value) {
          headerX = $$value;
          $$settled = false;
        },
        get headerY() {
          return headerY;
        },
        set headerY($$value) {
          headerY = $$value;
          $$settled = false;
        },
        get headerW() {
          return headerW;
        },
        set headerW($$value) {
          headerW = $$value;
          $$settled = false;
        },
        get headerH() {
          return headerH;
        },
        set headerH($$value) {
          headerH = $$value;
          $$settled = false;
        },
        get headerLocked() {
          return headerLocked;
        },
        set headerLocked($$value) {
          headerLocked = $$value;
          $$settled = false;
        },
        get qrX() {
          return qrX;
        },
        set qrX($$value) {
          qrX = $$value;
          $$settled = false;
        },
        get qrY() {
          return qrY;
        },
        set qrY($$value) {
          qrY = $$value;
          $$settled = false;
        },
        get qrW() {
          return qrW;
        },
        set qrW($$value) {
          qrW = $$value;
          $$settled = false;
        },
        get qrH() {
          return qrH;
        },
        set qrH($$value) {
          qrH = $$value;
          $$settled = false;
        },
        get qrLocked() {
          return qrLocked;
        },
        set qrLocked($$value) {
          qrLocked = $$value;
          $$settled = false;
        },
        get instructionsFontSize() {
          return instructionsFontSize;
        },
        set instructionsFontSize($$value) {
          instructionsFontSize = $$value;
          $$settled = false;
        },
        get instructionsFontFamily() {
          return instructionsFontFamily;
        },
        set instructionsFontFamily($$value) {
          instructionsFontFamily = $$value;
          $$settled = false;
        },
        get instructionsColor() {
          return instructionsColor;
        },
        set instructionsColor($$value) {
          instructionsColor = $$value;
          $$settled = false;
        },
        get instX() {
          return instX;
        },
        set instX($$value) {
          instX = $$value;
          $$settled = false;
        },
        get instY() {
          return instY;
        },
        set instY($$value) {
          instY = $$value;
          $$settled = false;
        },
        get instW() {
          return instW;
        },
        set instW($$value) {
          instW = $$value;
          $$settled = false;
        },
        get instH() {
          return instH;
        },
        set instH($$value) {
          instH = $$value;
          $$settled = false;
        },
        get instLocked() {
          return instLocked;
        },
        set instLocked($$value) {
          instLocked = $$value;
          $$settled = false;
        },
        get signatureFontSize() {
          return signatureFontSize;
        },
        set signatureFontSize($$value) {
          signatureFontSize = $$value;
          $$settled = false;
        },
        get signatureFontFamily() {
          return signatureFontFamily;
        },
        set signatureFontFamily($$value) {
          signatureFontFamily = $$value;
          $$settled = false;
        },
        get signatureColor() {
          return signatureColor;
        },
        set signatureColor($$value) {
          signatureColor = $$value;
          $$settled = false;
        },
        get sigX() {
          return sigX;
        },
        set sigX($$value) {
          sigX = $$value;
          $$settled = false;
        },
        get sigY() {
          return sigY;
        },
        set sigY($$value) {
          sigY = $$value;
          $$settled = false;
        },
        get sigW() {
          return sigW;
        },
        set sigW($$value) {
          sigW = $$value;
          $$settled = false;
        },
        get sigH() {
          return sigH;
        },
        set sigH($$value) {
          sigH = $$value;
          $$settled = false;
        },
        get sigLocked() {
          return sigLocked;
        },
        set sigLocked($$value) {
          sigLocked = $$value;
          $$settled = false;
        },
        get customTextItems() {
          return customTextItems;
        },
        set customTextItems($$value) {
          customTextItems = $$value;
          $$settled = false;
        },
        get sections() {
          return sections;
        },
        set sections($$value) {
          sections = $$value;
          $$settled = false;
        },
        get activeSectionIndex() {
          return activeSectionIndex;
        },
        set activeSectionIndex($$value) {
          activeSectionIndex = $$value;
          $$settled = false;
        },
        get selectedSectionIndices() {
          return selectedSectionIndices;
        },
        set selectedSectionIndices($$value) {
          selectedSectionIndices = $$value;
          $$settled = false;
        },
        get components() {
          return components;
        },
        set components($$value) {
          components = $$value;
          $$settled = false;
        },
        get plottingConfig() {
          return plottingConfig;
        },
        set plottingConfig($$value) {
          plottingConfig = $$value;
          $$settled = false;
        },
        get identity() {
          return identityDeclaration;
        },
        set identity($$value) {
          identityDeclaration = $$value;
          $$settled = false;
        },
        get activeComponentId() {
          return selectedComponentId;
        },
        set activeComponentId($$value) {
          selectedComponentId = $$value;
          $$settled = false;
        },
        get activeComponentIds() {
          return selectedComponentIds;
        },
        set activeComponentIds($$value) {
          selectedComponentIds = $$value;
          $$settled = false;
        },
        get activeItemIds() {
          return selectedCanvasIds;
        },
        set activeItemIds($$value) {
          selectedCanvasIds = $$value;
          $$settled = false;
        }
      });
      $$renderer3.push(`<!----></div> <div${attr_class(`studio-topbar absolute top-4 ${"left-4 lg:left-[398px]"} pointer-events-none right-4 z-30 hidden items-center justify-between gap-3 transition-all duration-200 lg:flex`, "svelte-e90e77")}${attr("data-sidebar-collapsed", isSidebarCollapsed)}><div data-studio-stage-navigation="" class="bg-card border-border/80 pointer-events-auto flex shrink-0 items-center space-x-1 rounded-2xl border p-1 shadow-md svelte-e90e77"><!--[-->`);
      const each_array_1 = ensure_array_like(stage1Mode === "html" || !modifyUploadedDocument ? [
        "1. " + (stage1Mode === "html" ? "Sheet Designer" : "Source Ingest"),
        "2. Recognition Mapping",
        "3. Verification & Test"
      ] : stageTitles);
      for (let idx = 0, $$length = each_array_1.length; idx < $$length; idx++) {
        let title = each_array_1[idx];
        const isSkippedStep = stage1Mode === "html" || !modifyUploadedDocument;
        const stageNum = isSkippedStep ? idx === 0 ? 1 : idx + 2 : idx + 1;
        const displayStep = idx + 1;
        const isActive = isSkippedStep && (idx === 0 && currentStage <= 2 || idx === 1 && currentStage === 3 || idx === 2 && currentStage === 4) || !isSkippedStep && currentStage === stageNum;
        const isPast = isSkippedStep ? idx === 0 && currentStage >= 3 || idx === 1 && currentStage === 4 : currentStage > stageNum;
        $$renderer3.push(`<button type="button"${attr("title", title.split(".")[1]?.trim() || title)}${attr_class(
          `flex items-center gap-1.5 rounded-xl px-2 py-1.5 text-xs leading-none font-bold transition xl:px-3 ${isActive ? "bg-primary text-primary-foreground shadow-xs" : isPast ? "text-foreground hover:bg-muted" : "text-muted-foreground hover:bg-muted/70 hover:text-foreground"}`,
          "svelte-e90e77"
        )}><span${attr_class(
          `flex size-4 shrink-0 items-center justify-center rounded-full text-[10px] leading-none font-extrabold ${isActive ? "bg-primary-foreground text-primary" : isPast ? "bg-muted text-foreground" : "bg-muted text-muted-foreground"}`,
          "svelte-e90e77"
        )}>${escape_html(displayStep)}</span> <span class="hidden truncate xl:inline">${escape_html(title.split(".")[1]?.trim() || title)}</span></button>`);
      }
      $$renderer3.push(`<!--]--></div> <div data-studio-editing-controls="" class="bg-card border-border/80 pointer-events-auto flex shrink-0 items-center gap-1.5 rounded-2xl border p-1.5 shadow-md svelte-e90e77"><div data-studio-history="" class="border-border flex items-center gap-0.5 border-r pr-2 svelte-e90e77"><button type="button"${attr("disabled", isReadOnly() || !historyStatus().canUndo, true)} class="text-muted-foreground hover:bg-muted hover:text-foreground rounded-lg p-1.5 transition disabled:cursor-not-allowed disabled:opacity-30"${attr("title", historyStatus().undoLabel ? `Undo: ${historyStatus().undoLabel}` : "Undo")}${attr("aria-label", historyStatus().undoLabel ? `Undo: ${historyStatus().undoLabel}` : "Undo")}>`);
      Undo_2($$renderer3, { class: "size-3.5" });
      $$renderer3.push(`<!----></button> <button type="button"${attr("disabled", isReadOnly() || !historyStatus().canRedo, true)} class="text-muted-foreground hover:bg-muted hover:text-foreground rounded-lg p-1.5 transition disabled:cursor-not-allowed disabled:opacity-30"${attr("title", historyStatus().redoLabel ? `Redo: ${historyStatus().redoLabel}` : "Redo")}${attr("aria-label", historyStatus().redoLabel ? `Redo: ${historyStatus().redoLabel}` : "Redo")}>`);
      Redo_2($$renderer3, { class: "size-3.5" });
      $$renderer3.push(`<!----></button></div> <div data-studio-save-state="" class="flex items-center space-x-1.5 px-2 text-xs svelte-e90e77">`);
      if (saveStatus === "saving") {
        $$renderer3.push("<!--[0-->");
        Loader_circle($$renderer3, { class: "size-3.5 animate-spin text-amber-500" });
        $$renderer3.push(`<!----> <span class="font-mono text-[11px] text-amber-600 svelte-e90e77">Saving…</span>`);
      } else if (saveStatus === "saved") {
        $$renderer3.push("<!--[1-->");
        Circle_check($$renderer3, { class: "size-3.5 text-emerald-600" });
        $$renderer3.push(`<!----> <span class="font-mono text-[11px] text-emerald-600 svelte-e90e77">Saved</span>`);
      } else if (saveStatus === "error") {
        $$renderer3.push("<!--[2-->");
        Circle_x($$renderer3, { class: "size-3.5 text-rose-600" });
        $$renderer3.push(`<!----> <button type="button" class="font-mono text-[11px] text-rose-600 underline underline-offset-2"${attr("title", errorMessage || "Save blocked. Check studio configuration.")}>Save blocked</button>`);
      } else {
        $$renderer3.push("<!--[-1-->");
      }
      $$renderer3.push(`<!--]--> `);
      if (historyNotice) {
        $$renderer3.push("<!--[0-->");
        $$renderer3.push(`<span class="sr-only svelte-e90e77" aria-live="polite">${escape_html(historyNotice)}</span>`);
      } else {
        $$renderer3.push("<!--[-1-->");
      }
      $$renderer3.push(`<!--]--></div> `);
      if (isReadOnly()) {
        $$renderer3.push("<!--[0-->");
        $$renderer3.push(`<div class="flex items-center gap-1.5 rounded-xl border border-slate-200 bg-slate-100/90 px-3 py-1 font-mono text-xs font-semibold text-slate-700 dark:border-slate-800 dark:bg-slate-900/80 dark:text-slate-300">`);
        Lock($$renderer3, { class: "size-3.5 text-slate-500" });
        $$renderer3.push(`<!----> <span>Locked (Read-Only)</span></div>`);
      } else {
        $$renderer3.push("<!--[-1-->");
        $$renderer3.push(`<button type="button" class="bg-primary hover:bg-primary/90 text-primary-foreground h-7 rounded-xl px-3.5 text-xs font-semibold shadow-xs transition">Save</button>`);
      }
      $$renderer3.push(`<!--]--> <a${attr("href", `/setup/exam-materials/answer-sheets/${draft.id}/export`)} download="" class="border-border bg-card hover:bg-muted text-foreground flex h-7 items-center gap-1.5 rounded-xl border px-3 text-xs font-semibold shadow-xs transition" title="Export complete answer sheet bundle as portable .zip archive">`);
      Download($$renderer3, { class: "text-muted-foreground size-3" });
      $$renderer3.push(`<!----> <span>Export (.zip)</span></a> `);
      if (draft.can_discard && urls?.destroy) {
        $$renderer3.push("<!--[0-->");
        $$renderer3.push(`<button type="button" class="border-rose-500/30 bg-rose-500/10 text-rose-700 hover:bg-rose-500/20 dark:text-rose-300 dark:hover:bg-rose-500/30 flex h-7 items-center gap-1.5 rounded-xl border px-3 text-xs font-semibold shadow-xs transition" title="Discard answer sheet draft"${attr("aria-label", `Discard ${draft.name || draft.code} draft`)}>`);
        Trash_2($$renderer3, { class: "size-3" });
        $$renderer3.push(`<!----> <span>Discard</span></button>`);
      } else {
        $$renderer3.push("<!--[-1-->");
      }
      $$renderer3.push(`<!--]--></div></div> `);
      if (currentStage === 1 && stage1Mode === "html" && designerSubTab === "designer" && selectedComponent()) {
        $$renderer3.push("<!--[0-->");
        $$renderer3.push(`<div${attr("inert", isReadOnly(), true)} class="studio-inspector bg-card text-foreground [&amp;_input]:border-input [&amp;_input]:bg-background [&amp;_input]:text-foreground [&amp;_select]:border-input [&amp;_select]:bg-background [&amp;_select]:text-foreground pointer-events-auto absolute top-16 right-4 z-40 max-h-[calc(100%-8rem)] w-[320px] space-y-3 overflow-y-auto rounded-2xl border border-emerald-500/30 p-3.5 text-xs shadow-2xl svelte-e90e77"><div class="border-border flex items-center justify-between border-b pb-2"><div class="flex min-w-0 items-center gap-1.5"><span class="size-2 shrink-0 rounded-full bg-emerald-500"></span> <span class="text-foreground truncate text-xs font-black">Inspector — ${escape_html(componentLabel(selectedComponent()))}</span></div> <div class="flex shrink-0 items-center gap-1"><button type="button"${attr_class(
          `flex items-center gap-1 rounded p-1 text-xs font-semibold transition ${selectedComponent().isLocked ? "bg-amber-500/10 text-amber-700 dark:text-amber-400" : "text-muted-foreground hover:text-foreground hover:bg-muted"}`,
          "svelte-e90e77"
        )}${attr("title", selectedComponent().isLocked ? "Locked (click to unlock movement)" : "Unlocked (click to lock)")}>`);
        if (selectedComponent().isLocked) {
          $$renderer3.push("<!--[0-->");
          Lock($$renderer3, { class: "size-3 text-amber-600" });
        } else {
          $$renderer3.push("<!--[-1-->");
          Lock_open($$renderer3, { class: "size-3 text-slate-400" });
        }
        $$renderer3.push(`<!--]--></button> <button type="button" class="rounded p-1 text-red-500 transition hover:bg-red-50 hover:text-red-700" title="Remove component">`);
        Trash_2($$renderer3, { class: "size-3" });
        $$renderer3.push(`<!----></button></div></div> <div class="bg-muted/50 border-border space-y-1 rounded-xl border p-2"><span class="text-muted-foreground block text-[9.5px] font-bold tracking-wide uppercase">Geometry (mm)</span> <div class="grid grid-cols-4 gap-1.5"><div><label class="text-muted-foreground block text-[9px]">X</label> <input type="number"${attr("value", selectedComponent().posX)} class="bg-background border-input w-full rounded border px-1.5 py-0.5 font-mono text-[11px]"/></div> <div><label class="text-muted-foreground block text-[9px]">Y</label> <input type="number"${attr("value", selectedComponent().posY)} class="bg-background border-input w-full rounded border px-1.5 py-0.5 font-mono text-[11px]"/></div> <div><label class="block text-[9px] text-slate-500">W</label> `);
        if (selectedComponent().type === "roll" || selectedComponent().type === "mcq" || selectedComponent().type === "tf" || selectedComponent().type === "likert" || selectedComponent().type === "custom") {
          $$renderer3.push("<!--[0-->");
          $$renderer3.push(`<span class="block w-full rounded border border-slate-200 bg-slate-100 px-1.5 py-0.5 font-mono text-[11px] text-slate-500">${escape_html(componentBlockSizeMm(selectedComponent()).w_mm)}</span>`);
        } else {
          $$renderer3.push("<!--[-1-->");
          $$renderer3.push(`<input type="number"${attr("value", selectedComponent().w_mm)} class="w-full rounded border border-slate-200 bg-white px-1.5 py-0.5 font-mono text-[11px]"/>`);
        }
        $$renderer3.push(`<!--]--></div> <div><label class="block text-[9px] text-slate-500">H</label> `);
        if (selectedComponent().type === "roll" || selectedComponent().type === "mcq" || selectedComponent().type === "tf" || selectedComponent().type === "likert" || selectedComponent().type === "custom") {
          $$renderer3.push("<!--[0-->");
          $$renderer3.push(`<span class="block w-full rounded border border-slate-200 bg-slate-100 px-1.5 py-0.5 font-mono text-[11px] text-slate-500">${escape_html(componentBlockSizeMm(selectedComponent()).h_mm)}</span>`);
        } else {
          $$renderer3.push("<!--[-1-->");
          $$renderer3.push(`<input type="number"${attr("value", selectedComponent().h_mm)} class="w-full rounded border border-slate-200 bg-white px-1.5 py-0.5 font-mono text-[11px]"/>`);
        }
        $$renderer3.push(`<!--]--></div></div></div> `);
        StudioComponentAppearanceControls($$renderer3, {
          backgroundColor: selectedComponent().backgroundColor ?? "transparent",
          onChanged: (backgroundColor) => updateSelectedComponent({ backgroundColor })
        });
        $$renderer3.push(`<!----> `);
        if (selectedComponent().type === "header") {
          $$renderer3.push("<!--[0-->");
          $$renderer3.push(`<div class="space-y-2"><div><label class="mb-0.5 block text-[10px] font-semibold text-slate-600">Title</label> <input type="text"${attr("value", selectedComponent().title)} placeholder="Institution / Exam Title" class="w-full rounded-lg border border-slate-200 bg-white px-2 py-1 text-xs font-semibold"/></div> <div class="grid grid-cols-2 gap-2"><div><label class="mb-0.5 block text-[10px] font-semibold text-slate-600">Subtitle</label> <input type="text"${attr("value", selectedComponent().subtitle)} placeholder="Subtitle" class="w-full rounded-lg border border-slate-200 bg-white px-2 py-1 text-[11px]"/></div> <div><label class="mb-0.5 block text-[10px] font-semibold text-slate-600">Form Code</label> <input type="text"${attr("value", selectedComponent().formCode)} placeholder="Form Code" class="w-full rounded-lg border border-slate-200 bg-white px-2 py-1 font-mono text-[11px]"/></div></div> `);
          StudioTypographyControls($$renderer3, {
            onChanged: syncComponentsToSections,
            get typography() {
              return selectedComponent().typography;
            },
            set typography($$value) {
              selectedComponent().typography = $$value;
              $$settled = false;
            }
          });
          $$renderer3.push(`<!----></div>`);
        } else if (selectedComponent().type === "candidate_name" || selectedComponent().type === "birthdate" || selectedComponent().type === "room_seat") {
          $$renderer3.push("<!--[1-->");
          $$renderer3.push(`<div class="space-y-2"><div><label class="mb-0.5 block text-[10px] font-semibold text-slate-600">Field Label</label> <input type="text"${attr("value", selectedComponent().label)} placeholder="Label text" class="w-full rounded-lg border border-slate-200 bg-white px-2 py-1 text-[11px]"/></div> <div class="grid grid-cols-2 gap-2"><div><label class="mb-0.5 block text-[10px] font-semibold text-slate-600">Input Type</label> `);
          $$renderer3.select(
            {
              value: selectedComponent().inputKind ?? (selectedComponent().type === "birthdate" ? "date" : "text"),
              onchange: (e) => updateSelectedComponent({ inputKind: e.currentTarget.value }),
              class: "w-full rounded-lg border border-slate-200 bg-white px-2 py-1 text-[11px]"
            },
            ($$renderer4) => {
              $$renderer4.option({ value: "text" }, ($$renderer5) => {
                $$renderer5.push(`Text`);
              });
              $$renderer4.option({ value: "number" }, ($$renderer5) => {
                $$renderer5.push(`Number`);
              });
              $$renderer4.option({ value: "date" }, ($$renderer5) => {
                $$renderer5.push(`Date`);
              });
              $$renderer4.option({ value: "multiline" }, ($$renderer5) => {
                $$renderer5.push(`Multiline`);
              });
            }
          );
          $$renderer3.push(`</div> <div><label class="mb-0.5 block text-[10px] font-semibold text-slate-600">Input Shape</label> `);
          $$renderer3.select(
            {
              value: selectedComponent().inputStyle ?? "box",
              onchange: (e) => updateSelectedComponent({ inputStyle: e.currentTarget.value }),
              class: "w-full rounded-lg border border-slate-200 bg-white px-2 py-1 text-[11px]"
            },
            ($$renderer4) => {
              $$renderer4.option({ value: "box" }, ($$renderer5) => {
                $$renderer5.push(`Box`);
              });
              $$renderer4.option({ value: "dotted_line" }, ($$renderer5) => {
                $$renderer5.push(`Dotted line`);
              });
              $$renderer4.option({ value: "underline" }, ($$renderer5) => {
                $$renderer5.push(`Underline`);
              });
            }
          );
          $$renderer3.push(`</div></div> <div class="flex items-center justify-between gap-2"><label class="text-[10px] font-semibold text-slate-600">Input Fill</label> <input type="color"${attr("value", selectedComponent().inputBackgroundColor ?? "#ffffff")} class="h-7 w-16 cursor-pointer rounded border border-slate-200 bg-white p-0.5" aria-label="Input fill color"/></div> `);
          StudioTypographyControls($$renderer3, {
            onChanged: syncComponentsToSections,
            get typography() {
              return selectedComponent().typography;
            },
            set typography($$value) {
              selectedComponent().typography = $$value;
              $$settled = false;
            }
          });
          $$renderer3.push(`<!----></div>`);
        } else if (selectedComponent().type === "custom_input") {
          $$renderer3.push("<!--[2-->");
          $$renderer3.push(`<div class="space-y-2"><div><label class="mb-0.5 block text-[10px] font-semibold text-slate-600">Input Label</label> <input type="text"${attr("value", selectedComponent().label)} placeholder="Custom response label" class="w-full rounded-lg border border-slate-200 bg-white px-2 py-1 text-[11px]"/></div> <div class="grid grid-cols-2 gap-2"><div><label class="mb-0.5 block text-[10px] font-semibold text-slate-600">Input Type</label> `);
          $$renderer3.select(
            {
              value: selectedComponent().inputKind,
              onchange: syncComponentsToSections,
              class: "w-full rounded-lg border border-slate-200 bg-white px-2 py-1 text-[11px]"
            },
            ($$renderer4) => {
              $$renderer4.option({ value: "text" }, ($$renderer5) => {
                $$renderer5.push(`Text`);
              });
              $$renderer4.option({ value: "number" }, ($$renderer5) => {
                $$renderer5.push(`Number`);
              });
              $$renderer4.option({ value: "date" }, ($$renderer5) => {
                $$renderer5.push(`Date`);
              });
              $$renderer4.option({ value: "multiline" }, ($$renderer5) => {
                $$renderer5.push(`Multiline`);
              });
            }
          );
          $$renderer3.push(`</div> <div><label class="mb-0.5 block text-[10px] font-semibold text-slate-600">Input Shape</label> `);
          $$renderer3.select(
            {
              value: selectedComponent().inputStyle,
              onchange: syncComponentsToSections,
              class: "w-full rounded-lg border border-slate-200 bg-white px-2 py-1 text-[11px]"
            },
            ($$renderer4) => {
              $$renderer4.option({ value: "box" }, ($$renderer5) => {
                $$renderer5.push(`Box`);
              });
              $$renderer4.option({ value: "dotted_line" }, ($$renderer5) => {
                $$renderer5.push(`Dotted line`);
              });
              $$renderer4.option({ value: "underline" }, ($$renderer5) => {
                $$renderer5.push(`Underline`);
              });
            }
          );
          $$renderer3.push(`</div></div> <div class="flex items-center justify-between gap-2"><label class="text-[10px] font-semibold text-slate-600">Input Fill</label> <input type="color"${attr("value", selectedComponent().inputBackgroundColor)} class="h-7 w-16 cursor-pointer rounded border border-slate-200 bg-white p-0.5" aria-label="Input fill color"/></div> `);
          StudioTypographyControls($$renderer3, {
            onChanged: syncComponentsToSections,
            get typography() {
              return selectedComponent().typography;
            },
            set typography($$value) {
              selectedComponent().typography = $$value;
              $$settled = false;
            }
          });
          $$renderer3.push(`<!----></div>`);
        } else if (selectedComponent().type === "instructions") {
          $$renderer3.push("<!--[3-->");
          $$renderer3.push(`<div class="space-y-2"><div><label class="mb-0.5 block text-[10px] font-semibold text-slate-600">Notice Label</label> <input type="text"${attr("value", selectedComponent().label)} placeholder="Instructions" class="w-full rounded-lg border border-slate-200 bg-white px-2 py-1 text-[11px] font-semibold"/></div> <div><label class="mb-0.5 block text-[10px] font-semibold text-slate-600">Notice Text</label> <textarea rows="3" class="w-full rounded-lg border border-slate-200 bg-white px-2 py-1 text-[11px] leading-relaxed" placeholder="Instructions text…">`);
          const $$body = escape_html(selectedComponent().content);
          if ($$body) {
            $$renderer3.push(`${$$body}`);
          }
          $$renderer3.push(`</textarea></div> <div class="grid grid-cols-2 gap-2"><div><label class="mb-0.5 block text-[10px] font-semibold text-slate-600">Border Color</label> <input type="color"${attr("value", selectedComponent().borderColor)} class="h-7 w-full cursor-pointer rounded border border-slate-200 bg-white p-0.5"/></div> <div><label class="mb-0.5 block text-[10px] font-semibold text-slate-600">Background</label> <input type="color"${attr("value", selectedComponent().backgroundColor)} class="h-7 w-full cursor-pointer rounded border border-slate-200 bg-white p-0.5"/></div></div> `);
          StudioTypographyControls($$renderer3, {
            onChanged: syncComponentsToSections,
            get typography() {
              return selectedComponent().typography;
            },
            set typography($$value) {
              selectedComponent().typography = $$value;
              $$settled = false;
            }
          });
          $$renderer3.push(`<!----></div>`);
        } else if (selectedComponent().type === "roll") {
          $$renderer3.push("<!--[4-->");
          $$renderer3.push(`<div class="space-y-2"><div class="grid grid-cols-2 gap-2"><div><label class="mb-0.5 block text-[10px] font-semibold text-slate-600">Digit Columns</label> <input type="number" min="2" max="10"${attr("value", selectedComponent().digitsCount)} class="w-full rounded-lg border border-slate-200 bg-white px-2 py-1 text-xs font-semibold"/></div> <div><label class="mb-0.5 block text-[10px] font-semibold text-slate-600">Matrix Title</label> <input type="text"${attr("value", selectedComponent().title)} class="w-full rounded-lg border border-slate-200 bg-white px-2 py-1 text-xs"/></div></div> <div class="grid grid-cols-3 gap-2 pt-1"><div><label class="mb-0.5 block text-[10px] font-semibold text-slate-600">Bubble Size (mm)</label> <input type="number" min="4.2" max="8" step="0.1"${attr("value", selectedComponent().bubbleDiameterMm)} class="w-full rounded-lg border border-slate-200 bg-white px-2 py-1 text-xs"/></div> <div><label class="mb-0.5 block text-[10px] font-semibold text-slate-600">Bubble Pitch (mm)</label> <input type="number"${attr("min", minimumCanvasPitchMm(selectedComponent().bubbleDiameterMm))} max="15" step="0.1"${attr("value", selectedComponent().bubblePitchMm)} class="w-full rounded-lg border border-slate-200 bg-white px-2 py-1 text-xs"/></div> <div><label class="mb-0.5 block text-[10px] font-semibold text-slate-600">Row Pitch (mm)</label> <input type="number"${attr("min", minimumCanvasPitchMm(selectedComponent().bubbleDiameterMm))} max="15" step="0.1"${attr("value", selectedComponent().rowPitchMm)} class="w-full rounded-lg border border-slate-200 bg-white px-2 py-1 text-xs"/></div></div></div> `);
          StudioTypographyControls($$renderer3, {
            onChanged: syncComponentsToSections,
            get typography() {
              return selectedComponent().typography;
            },
            set typography($$value) {
              selectedComponent().typography = $$value;
              $$settled = false;
            }
          });
          $$renderer3.push(`<!---->`);
        } else if (selectedComponent().type === "mcq" || selectedComponent().type === "tf" || selectedComponent().type === "likert" || selectedComponent().type === "custom") {
          $$renderer3.push("<!--[5-->");
          $$renderer3.push(`<div class="space-y-2"><div><label class="mb-0.5 block text-[10px] font-semibold text-slate-600">Subject Section Name</label> <input type="text"${attr("value", selectedComponent().subjectName)} placeholder="Subject name" class="w-full rounded-lg border border-slate-200 bg-white px-2 py-1 text-xs font-semibold"/></div> <div class="grid grid-cols-2 gap-2"><div><label class="mb-0.5 block text-[10px] font-semibold text-slate-600">Bubble Size (mm)</label> <input type="number" min="4.2" max="8" step="0.1"${attr("value", selectedComponent().bubbleDiameterMm)} class="w-full rounded-lg border border-slate-200 bg-white px-2 py-1 text-xs"/></div> <div><label class="mb-0.5 block text-[10px] font-semibold text-slate-600">Question Label Size (pt)</label> <input type="number" min="5" max="12" step="0.1"${attr("value", selectedComponent().labelFontSizePt)} class="w-full rounded-lg border border-slate-200 bg-white px-2 py-1 text-xs"/></div></div> <div class="grid grid-cols-2 gap-2"><div><label class="mb-0.5 block text-[10px] font-semibold text-slate-600">Questions Count</label> <input type="number" min="1" max="50"${attr("value", selectedComponent().questionsCount)} class="w-full rounded-lg border border-slate-200 bg-white px-2 py-1 text-xs"/></div> <div><label class="mb-0.5 block text-[10px] font-semibold text-slate-600">Bubble Pitch (mm)</label> <input type="number"${attr("min", minimumCanvasPitchMm(selectedComponent().bubbleDiameterMm))} max="25" step="0.1"${attr("value", selectedComponent().bubblePitchMm)} class="w-full rounded-lg border border-slate-200 bg-white px-2 py-1 text-xs"/></div> <div><label class="mb-0.5 block text-[10px] font-semibold text-slate-600">Options Count</label> <input type="number" min="2" max="5"${attr("value", selectedComponent().optionsCount)} class="w-full rounded-lg border border-slate-200 bg-white px-2 py-1 text-xs"/></div> <div><label class="mb-0.5 block text-[10px] font-semibold text-slate-600">Start Q#</label> <input type="number" min="1"${attr("value", selectedComponent().startQ)} class="w-full rounded-lg border border-slate-200 bg-white px-2 py-1 text-xs"/></div> <div><label class="mb-0.5 block text-[10px] font-semibold text-slate-600">Row Pitch (mm)</label> <input type="number"${attr("min", minimumCanvasPitchMm(selectedComponent().bubbleDiameterMm))} max="25"${attr("value", selectedComponent().rowPitchMm)} class="w-full rounded-lg border border-slate-200 bg-white px-2 py-1 text-xs"/></div></div> `);
          if (aptitude_areas && aptitude_areas.length > 0) {
            $$renderer3.push("<!--[0-->");
            $$renderer3.push(`<div><label class="mb-0.5 block text-[10px] font-semibold text-slate-600">Bind System Aptitude Area</label> `);
            $$renderer3.select(
              {
                value: selectedComponent().aptitudeAreaCode ?? "",
                class: "w-full rounded-lg border border-slate-200 bg-slate-50 px-2 py-1 text-[10.5px] text-slate-700",
                onchange: (e) => {
                  const area = aptitude_areas.find((a) => a.code === e.currentTarget.value);
                  if (area) {
                    updateSelectedComponent({
                      aptitudeAreaCode: area.code,
                      subjectName: area.name,
                      questionsCount: 25
                    });
                  }
                }
              },
              ($$renderer4) => {
                $$renderer4.option({ value: "" }, ($$renderer5) => {
                  $$renderer5.push(`-- Choose Aptitude Area --`);
                });
                $$renderer4.push(`<!--[-->`);
                const each_array_2 = ensure_array_like(aptitude_areas);
                for (let $$index_2 = 0, $$length = each_array_2.length; $$index_2 < $$length; $$index_2++) {
                  let area = each_array_2[$$index_2];
                  $$renderer4.option({ value: area.code }, ($$renderer5) => {
                    $$renderer5.push(`${escape_html(area.name)} (25 Items)`);
                  });
                }
                $$renderer4.push(`<!--]-->`);
              }
            );
            $$renderer3.push(`</div>`);
          } else {
            $$renderer3.push("<!--[-1-->");
          }
          $$renderer3.push(`<!--]--></div>`);
        } else if (selectedComponent().type === "custom_text") {
          $$renderer3.push("<!--[6-->");
          $$renderer3.push(`<div class="space-y-2"><div><label class="mb-0.5 block text-[10px] font-semibold text-slate-600">Text Content</label> <input type="text"${attr("value", selectedComponent().text)} placeholder="Custom text…" class="w-full rounded-lg border border-slate-200 bg-white px-2 py-1 text-[11px]"/></div> `);
          StudioTypographyControls($$renderer3, {
            onChanged: syncComponentsToSections,
            get typography() {
              return selectedComponent().typography;
            },
            set typography($$value) {
              selectedComponent().typography = $$value;
              $$settled = false;
            }
          });
          $$renderer3.push(`<!----></div>`);
        } else if (selectedComponent().type === "signature") {
          $$renderer3.push("<!--[7-->");
          $$renderer3.push(`<div class="space-y-2"><div><label class="mb-0.5 block text-[10px] font-semibold text-slate-600">Title Label</label> <input type="text"${attr("value", selectedComponent().title)} placeholder="Signature label" class="w-full rounded-lg border border-slate-200 bg-white px-2 py-1 text-[11px]"/></div> <div><label class="mb-0.5 block text-[10px] font-semibold text-slate-600">Certification Text</label> <input type="text"${attr("value", selectedComponent().certificationText)} placeholder="Certification text" class="w-full rounded-lg border border-slate-200 bg-white px-2 py-1 text-[11px]"/></div> `);
          StudioTypographyControls($$renderer3, {
            onChanged: syncComponentsToSections,
            get typography() {
              return selectedComponent().typography;
            },
            set typography($$value) {
              selectedComponent().typography = $$value;
              $$settled = false;
            }
          });
          $$renderer3.push(`<!----></div>`);
        } else {
          $$renderer3.push("<!--[-1-->");
        }
        $$renderer3.push(`<!--]--></div>`);
      } else {
        $$renderer3.push("<!--[-1-->");
      }
      $$renderer3.push(`<!--]--> <div${attr_class(`studio-canvas-controls absolute bottom-4 ${"left-4 sm:left-[398px]"} border-border bg-card pointer-events-auto z-30 hidden items-center gap-2 rounded-2xl border p-1.5 shadow-xl transition-all duration-200 sm:flex`, "svelte-e90e77")}${attr("data-sidebar-collapsed", isSidebarCollapsed)}><button type="button" data-studio-canvas-lock=""${attr_class(
        `flex items-center gap-1.5 rounded-xl px-2.5 py-1.5 text-xs font-bold transition ${canvasLocked ? "border border-amber-500/40 bg-amber-500/10 text-amber-700 dark:text-amber-400" : "bg-muted hover:bg-muted/80 text-foreground border-border border"}`,
        "svelte-e90e77"
      )}${attr("title", canvasLocked ? "Canvas viewport is locked (Scroll wheel & panning disabled)" : "Canvas is interactive (Scroll wheel zooms, drag background to pan)")}>`);
      if (canvasLocked) {
        $$renderer3.push("<!--[0-->");
        Lock($$renderer3, { class: "size-3.5" });
        $$renderer3.push(`<!----> <span>Canvas Locked</span>`);
      } else {
        $$renderer3.push("<!--[-1-->");
        Lock_open($$renderer3, { class: "text-muted-foreground size-3.5" });
        $$renderer3.push(`<!----> <span>Canvas Active</span>`);
      }
      $$renderer3.push(`<!--]--></button> <div data-studio-canvas-divider="" class="bg-border h-4 w-px svelte-e90e77"></div> <div data-studio-canvas-zoom="" class="bg-muted border-border flex items-center gap-1 rounded-xl border p-0.5 svelte-e90e77"><button type="button" title="Zoom Out" class="text-muted-foreground hover:text-foreground hover:bg-card flex h-7 w-7 items-center justify-center rounded-lg transition">`);
      Minus($$renderer3, { class: "size-3.5" });
      $$renderer3.push(`<!----></button> <span class="text-foreground min-w-[42px] px-1.5 text-center font-mono text-xs font-bold">${escape_html(Math.round(scale * 100))}%</span> <button type="button" title="Zoom In" class="text-muted-foreground hover:text-foreground hover:bg-card flex h-7 w-7 items-center justify-center rounded-lg transition">`);
      Plus($$renderer3, { class: "size-3.5" });
      $$renderer3.push(`<!----></button></div> <button type="button" title="Reset zoom and center the sheet" data-studio-canvas-fit="" class="text-foreground hover:bg-muted border-border flex h-8 items-center gap-1 rounded-xl border px-2.5 text-xs font-bold transition svelte-e90e77">`);
      Maximize_2($$renderer3, { class: "size-3" });
      $$renderer3.push(`<!----> <span>Fit</span></button></div> `);
      {
        $$renderer3.push("<!--[-1-->");
      }
      $$renderer3.push(`<!--]--> <aside${attr_class(
        `studio-sidebar bg-card border-border text-foreground absolute top-4 bottom-4 left-4 z-20 flex max-h-[calc(100%-2rem)] w-[360px] max-w-[calc(100vw-2rem)] min-w-0 flex-col overflow-hidden rounded-2xl border shadow-2xl transition-all duration-200 sm:w-[370px] ${"pointer-events-auto translate-x-0 opacity-100"} [&_input]:border-input [&_input]:bg-background [&_input]:text-foreground [&_select]:border-input [&_select]:bg-background [&_select]:text-foreground dark:[&_.bg-white]:bg-card dark:[&_.bg-slate-50]:bg-muted/50 dark:[&_.bg-slate-100]:bg-muted dark:[&_.text-slate-900]:text-foreground dark:[&_.text-slate-800]:text-foreground dark:[&_.text-slate-700]:text-foreground dark:[&_.text-slate-600]:text-muted-foreground dark:[&_.text-slate-500]:text-muted-foreground dark:[&_.border-slate-200]:border-border dark:[&_.border-slate-100]:border-border`,
        "svelte-e90e77"
      )}${attr("data-studio-stage", currentStage)}${attr("data-sidebar-collapsed", isSidebarCollapsed)}><div class="border-border bg-card flex h-11 shrink-0 items-center justify-between border-b px-3.5"><div class="flex min-w-0 items-center space-x-2"><div class="flex size-5 shrink-0 items-center justify-center rounded bg-emerald-600 text-[10px] font-bold text-white">N</div> <span class="text-foreground max-w-[170px] truncate text-xs font-bold"${attr("title", draft.name || "Answer sheet")}>${escape_html(draft.name || "Answer sheet")}</span></div> <div class="flex shrink-0 items-center gap-1.5"><div class="text-muted-foreground font-mono text-[10.5px]">${escape_html(totalQuestionsCount())} Qs</div> <button type="button" class="text-muted-foreground hover:text-foreground hover:bg-muted flex size-6 items-center justify-center rounded-lg transition" title="Collapse tool card" aria-label="Collapse tool card">`);
      Chevron_left($$renderer3, { class: "size-4" });
      $$renderer3.push(`<!----></button></div></div> <div class="min-h-0 min-w-0 flex-1 space-y-4 overflow-x-hidden overflow-y-auto p-4"><!---->`);
      {
        if (sidebarStage() === 1) {
          $$renderer3.push("<!--[0-->");
          $$renderer3.push(`<div class="space-y-4"${attr("inert", isReadOnly(), true)}><div class="flex items-center justify-between rounded-xl border border-slate-200 bg-slate-50 p-2.5"><div class="flex items-center gap-2"><span${attr_class(
            `rounded-lg px-2 py-1 text-[10.5px] font-bold ${stage1Mode === "html" ? "bg-emerald-100 text-emerald-800" : "bg-blue-100 text-blue-800"}`,
            "svelte-e90e77"
          )}>${escape_html(stage1Mode === "html" ? "Sheet Designer" : "Substrate Ingest")}</span></div> <button type="button" class="text-[10.5px] font-semibold text-slate-600 underline underline-offset-2 hover:text-slate-900">Change mode</button></div> `);
          if (stage1Mode === "html") {
            $$renderer3.push("<!--[0-->");
            $$renderer3.push(`<div class="grid grid-cols-3 items-center rounded-lg border border-slate-200 bg-slate-100 p-0.5"><button type="button"${attr_class(
              `rounded-md py-1 text-[10.5px] font-semibold transition ${"bg-white text-slate-900 shadow-xs"}`,
              "svelte-e90e77"
            )}>Visual Designer</button> <button type="button"${attr_class(
              `rounded-md py-1 text-[10.5px] font-semibold transition ${"text-slate-500 hover:text-slate-800"}`,
              "svelte-e90e77"
            )}>Compiled HTML</button> <button type="button"${attr_class(
              `rounded-md py-1 text-[10.5px] font-semibold transition ${"text-slate-500 hover:text-slate-800"}`,
              "svelte-e90e77"
            )}>Print Preview</button></div> `);
            {
              $$renderer3.push("<!--[0-->");
              $$renderer3.push(`<div class="space-y-3"><div><div class="mb-1.5 flex items-center justify-between"><span class="text-xs font-bold text-slate-900">Component Palette</span> <span class="text-[9.5px] text-slate-400">Drag onto canvas or click to
                                                add</span></div> <div class="grid grid-cols-2 gap-1.5"><!--[-->`);
              const each_array_3 = ensure_array_like(paletteForPath(componentPalettePath()));
              for (let $$index_3 = 0, $$length = each_array_3.length; $$index_3 < $$length; $$index_3++) {
                let item = each_array_3[$$index_3];
                $$renderer3.push(`<button type="button"${attr("draggable", componentPaletteFilter().includeQuestionComponents || item.kind !== "roll" && item.kind !== "question_grid")} class="cursor-grab rounded-lg border border-slate-200 bg-white p-2 text-left transition hover:border-emerald-400 hover:bg-emerald-50/40 active:cursor-grabbing"${attr("title", item.description)}><span class="block text-[10.5px] leading-tight font-bold text-slate-800">${escape_html(item.label)}</span> <span class="mt-0.5 block text-[9px] leading-snug text-slate-400">${escape_html(item.description)}</span></button>`);
              }
              $$renderer3.push(`<!--]--></div></div> <div class="border-t border-slate-200/80 pt-3"><div class="mb-1.5 flex items-center justify-between"><span class="text-xs font-bold text-slate-900">Shared Overlay Palette</span> <span class="text-[9.5px] text-slate-400">Path: ${escape_html(componentPalettePath() === "sheet_designer" ? "Sheet" : "Upload")}</span></div> <div class="grid grid-cols-3 gap-1.5"><!--[-->`);
              const each_array_4 = ensure_array_like(availableOverlayPalette(componentPalettePath()));
              for (let $$index_4 = 0, $$length = each_array_4.length; $$index_4 < $$length; $$index_4++) {
                let item = each_array_4[$$index_4];
                $$renderer3.push(`<button type="button" class="rounded-lg border border-slate-200 bg-white p-2 text-left transition hover:border-blue-400 hover:bg-blue-50/40"${attr("title", item.description)}><span class="block text-[10px] leading-tight font-bold text-slate-800">${escape_html(item.label)}</span> <span class="mt-0.5 block text-[8.5px] leading-snug text-slate-400">${escape_html(item.description)}</span></button>`);
              }
              $$renderer3.push(`<!--]--></div></div> <div class="space-y-3 border-t border-slate-200/80 pt-3"><div class="flex items-center justify-between"><span class="text-xs font-bold tracking-wider text-slate-800 uppercase">Sheet Layout</span> <span class="text-[9.5px] text-slate-400">Saved with this path</span></div> <div class="space-y-1.5 rounded-xl border border-slate-200 bg-slate-50 p-2.5"><div class="flex justify-between text-[11px] font-semibold text-slate-700"><span>Content Margin</span> <span class="rounded border bg-white px-1.5 py-0.5 font-mono text-[10px]">${escape_html(marginMm)} mm</span></div> <input type="range" min="8" max="30" step="1"${attr("value", marginMm)} class="w-full accent-slate-900"/></div> `);
              if (usesFiducials) {
                $$renderer3.push("<!--[0-->");
                $$renderer3.push(`<label class="block space-y-1.5"><span class="text-[11px] font-bold text-slate-700">OMRChecker fiducial profile</span> `);
                $$renderer3.select(
                  {
                    value: draft.marker_profile_id ?? "",
                    onchange: (event) => selectMarkerProfile(Number(event.currentTarget.value)),
                    class: "w-full rounded-lg border border-slate-200 bg-white px-2 py-1.5 text-xs text-slate-800"
                  },
                  ($$renderer4) => {
                    $$renderer4.option({ value: "", disabled: true }, ($$renderer5) => {
                      $$renderer5.push(`Select a marker profile`);
                    });
                    $$renderer4.push(`<!--[-->`);
                    const each_array_5 = ensure_array_like(marker_profiles);
                    for (let $$index_5 = 0, $$length = each_array_5.length; $$index_5 < $$length; $$index_5++) {
                      let profile = each_array_5[$$index_5];
                      $$renderer4.option({ value: profile.id }, ($$renderer5) => {
                        $$renderer5.push(`${escape_html(profile.code)} v${escape_html(profile.version)}
                                                            · ${escape_html(profile.status)}`);
                      });
                    }
                    $$renderer4.push(`<!--]-->`);
                  }
                );
                $$renderer3.push(` <span class="block text-[10px] leading-relaxed text-slate-500">The selected profile
                                                    controls the four
                                                    registration fiducials
                                                    independently from the
                                                    content margin.</span></label> <div>`);
                StudioFiducialInsetControls($$renderer3, {
                  disabled: !draft.marker_profile_id || markerInsetsSaving,
                  onPreview: previewMarkerInsets,
                  onCommit: persistMarkerInsets,
                  get markerInsetsMm() {
                    return markerInsetsMm;
                  },
                  set markerInsetsMm($$value) {
                    markerInsetsMm = $$value;
                    $$settled = false;
                  }
                });
                $$renderer3.push(`<!----></div>`);
              } else {
                $$renderer3.push("<!--[-1-->");
              }
              $$renderer3.push(`<!--]--> `);
              if (qrEnabled) {
                $$renderer3.push("<!--[0-->");
                $$renderer3.push(`<div class="rounded-xl border border-slate-200 bg-slate-50 p-2.5">`);
                StudioQrReservationControls($$renderer3, {
                  onChanged: handleHtmlDecorationChanged,
                  get showQrOverlay() {
                    return showQrOverlay;
                  },
                  set showQrOverlay($$value) {
                    showQrOverlay = $$value;
                    $$settled = false;
                  },
                  get qrX() {
                    return qrX;
                  },
                  set qrX($$value) {
                    qrX = $$value;
                    $$settled = false;
                  },
                  get qrY() {
                    return qrY;
                  },
                  set qrY($$value) {
                    qrY = $$value;
                    $$settled = false;
                  },
                  get qrW() {
                    return qrW;
                  },
                  set qrW($$value) {
                    qrW = $$value;
                    $$settled = false;
                  },
                  get qrH() {
                    return qrH;
                  },
                  set qrH($$value) {
                    qrH = $$value;
                    $$settled = false;
                  },
                  get qrLocked() {
                    return qrLocked;
                  },
                  set qrLocked($$value) {
                    qrLocked = $$value;
                    $$settled = false;
                  }
                });
                $$renderer3.push(`<!----></div>`);
              } else {
                $$renderer3.push("<!--[-1-->");
              }
              $$renderer3.push(`<!--]--></div> <div class="space-y-1.5 rounded-xl border border-emerald-200/80 bg-gradient-to-r from-emerald-50 to-teal-50 p-2.5"><div class="flex items-center justify-between text-xs font-bold text-emerald-950"><span class="flex items-center gap-1">`);
              Sparkles($$renderer3, { class: "size-3 text-emerald-600" });
              $$renderer3.push(`<!----> <span>Quick Scaffolds</span></span> <span class="font-mono text-[9.5px] text-emerald-700">${escape_html(totalQuestionsCount())} Items</span></div> <div class="grid grid-cols-3 gap-1 pt-0.5"><button type="button" class="rounded-lg border border-emerald-200 bg-white px-1.5 py-1 text-center text-[10px] font-bold text-emerald-900 shadow-2xs transition hover:bg-emerald-100">150-Q (6 Subj)</button> <button type="button" class="rounded-lg border border-emerald-200 bg-white px-1.5 py-1 text-center text-[10px] font-bold text-emerald-900 shadow-2xs transition hover:bg-emerald-100">100-Q (4 Subj)</button> <button type="button" class="rounded-lg border border-emerald-200 bg-white px-1.5 py-1 text-center text-[10px] font-bold text-emerald-900 shadow-2xs transition hover:bg-emerald-100">50-Q (2 Subj)</button></div> `);
              if (aptitude_areas && aptitude_areas.length > 0) {
                $$renderer3.push("<!--[0-->");
                $$renderer3.push(`<button type="button" class="flex w-full items-center justify-center gap-1 rounded-lg border border-emerald-200 bg-white px-2 py-1 text-[10.5px] font-semibold text-emerald-900 transition hover:bg-emerald-100">`);
                Sparkles($$renderer3, { class: "size-3 text-emerald-600" });
                $$renderer3.push(`<!----> <span>Scaffold from System
                                                    Aptitude Areas (${escape_html(aptitude_areas.length)})</span></button>`);
              } else {
                $$renderer3.push("<!--[-1-->");
              }
              $$renderer3.push(`<!--]--></div> <div><button type="button" class="group mb-1.5 flex w-full items-center justify-between text-left"${attr("aria-expanded", showLayersPanel)}><div class="flex items-center gap-1.5"><span class="text-xs font-bold text-slate-900">Layers (${escape_html(components.length + (showQrOverlay ? 1 : 0))})</span> `);
              if (selectedCanvasIds.length > 1) {
                $$renderer3.push("<!--[0-->");
                $$renderer3.push(`<span class="py-0.2 rounded-md bg-emerald-100 px-1.5 font-mono text-[9.5px] font-semibold text-emerald-800">${escape_html(selectedCanvasIds.length)}
                                                        selected</span>`);
              } else {
                $$renderer3.push("<!--[-1-->");
              }
              $$renderer3.push(`<!--]--></div> <span class="flex items-center gap-1 text-[9.5px] text-slate-400 transition-colors group-hover:text-slate-600"><span>Back to front</span> `);
              Chevron_down($$renderer3, {
                class: `size-3 transition-transform duration-200 ${"rotate-180"}`
              });
              $$renderer3.push(`<!----></span></button> `);
              {
                $$renderer3.push("<!--[0-->");
                if (components.length === 0 && !showQrOverlay) {
                  $$renderer3.push("<!--[0-->");
                  $$renderer3.push(`<div class="rounded-xl border-2 border-dashed border-slate-300 bg-slate-50/50 p-4 text-center text-[11px] leading-relaxed text-slate-400">No components yet.<br/> Drag items from the palette onto
                                                    the canvas — or click one to add
                                                    it.</div>`);
                } else {
                  $$renderer3.push("<!--[-1-->");
                  $$renderer3.push(`<div class="space-y-1" role="list" aria-label="Component layers">`);
                  if (showQrOverlay) {
                    $$renderer3.push("<!--[0-->");
                    $$renderer3.push(`<div role="listitem"${attr_class(
                      `group relative flex items-center gap-1.5 rounded-lg border p-1.5 text-[11px] transition-all ${selectedCanvasIds.includes(QR_CANVAS_ITEM_ID) ? "border-emerald-500 bg-emerald-50 shadow-2xs ring-1 ring-emerald-400" : "border-slate-200 bg-white hover:border-slate-300"}`,
                      "svelte-e90e77"
                    )} title="Reserved instance QR overlay"><div class="shrink-0 p-0.5 text-slate-300" title="Fixed base layer">`);
                    Grip_vertical($$renderer3, { class: "size-3.5 opacity-30" });
                    $$renderer3.push(`<!----></div> <button type="button" class="flex min-w-0 flex-1 items-center gap-1.5 text-left focus:outline-hidden" aria-label="Select QR Reservation layer"><span class="flex size-4 shrink-0 items-center justify-center rounded bg-slate-100 font-mono text-[9px] font-medium text-slate-500">1</span> <span class="truncate font-bold text-slate-800">QR
                                                                    Reservation</span></button> <div${attr_class(
                      `flex shrink-0 items-center gap-0.5 transition-opacity duration-150 ${qrLocked ? "opacity-100" : "opacity-0 group-hover:opacity-100 focus-within:opacity-100"}`,
                      "svelte-e90e77"
                    )}><button type="button"${attr_class(
                      `rounded p-1 transition ${qrLocked ? "bg-amber-500/10 text-amber-700 dark:text-amber-400" : "text-muted-foreground hover:text-foreground hover:bg-slate-100"}`,
                      "svelte-e90e77"
                    )}${attr("title", qrLocked ? "Unlock position" : "Lock position")}${attr("aria-label", qrLocked ? "Unlock QR Reservation" : "Lock QR Reservation")}>`);
                    if (qrLocked) {
                      $$renderer3.push("<!--[0-->");
                      Lock($$renderer3, { class: "size-3" });
                    } else {
                      $$renderer3.push("<!--[-1-->");
                      Lock_open($$renderer3, { class: "size-3" });
                    }
                    $$renderer3.push(`<!--]--></button> <button type="button" class="text-muted-foreground hover:text-destructive hover:bg-destructive/10 rounded p-1 transition" title="Remove QR reservation" aria-label="Remove QR Reservation">`);
                    Trash_2($$renderer3, { class: "size-3" });
                    $$renderer3.push(`<!----></button></div></div>`);
                  } else {
                    $$renderer3.push("<!--[-1-->");
                  }
                  $$renderer3.push(`<!--]--> <!--[-->`);
                  const each_array_6 = ensure_array_like(components);
                  for (let layerIndex = 0, $$length = each_array_6.length; layerIndex < $$length; layerIndex++) {
                    let c = each_array_6[layerIndex];
                    const layerNumber = layerIndex + (showQrOverlay ? 2 : 1);
                    const isDragging = layerDragId === c.id;
                    layerDragOverId === c.id && !isDragging;
                    const isSelected = selectedCanvasIds.includes(c.id);
                    $$renderer3.push(`<div role="listitem" draggable="true"${attr_class(
                      `group relative flex items-center gap-1.5 rounded-lg border p-1.5 text-[11px] transition-all ${isDragging ? "scale-[0.98] border-dashed border-slate-300 bg-slate-50 opacity-40" : isSelected ? "border-emerald-500 bg-emerald-50 shadow-2xs ring-1 ring-emerald-400" : "border-slate-200 bg-white hover:border-slate-300"}`,
                      "svelte-e90e77"
                    )} title="Drag handle to reorder layer (or Alt+Arrow keys)">`);
                    {
                      $$renderer3.push("<!--[-1-->");
                    }
                    $$renderer3.push(`<!--]--> `);
                    {
                      $$renderer3.push("<!--[-1-->");
                    }
                    $$renderer3.push(`<!--]--> <div class="shrink-0 cursor-grab p-0.5 text-slate-400 transition-colors group-hover:text-slate-700 active:cursor-grabbing" title="Drag to reorder">`);
                    Grip_vertical($$renderer3, { class: "size-3.5" });
                    $$renderer3.push(`<!----></div> <button type="button" class="flex min-w-0 flex-1 items-center gap-1.5 text-left focus:outline-hidden"${attr("aria-label", `Select ${componentLabel(c)} layer (Layer ${layerNumber})`)}><span class="flex size-4 shrink-0 items-center justify-center rounded bg-slate-100 font-mono text-[9px] font-medium text-slate-500">${escape_html(layerNumber)}</span> <span${attr_class(
                      `truncate ${isSelected ? "font-bold text-emerald-950" : "font-bold text-slate-800"}`,
                      "svelte-e90e77"
                    )}>${escape_html(componentLabel(c))}</span></button> <div${attr_class(
                      `flex shrink-0 items-center gap-0.5 transition-opacity duration-150 ${c.isLocked ? "opacity-100" : "opacity-0 group-hover:opacity-100 focus-within:opacity-100"}`,
                      "svelte-e90e77"
                    )}><button type="button"${attr_class(
                      `rounded p-1 transition ${c.isLocked ? "bg-amber-500/10 text-amber-700 dark:text-amber-400" : "text-muted-foreground hover:text-foreground hover:bg-slate-100"}`,
                      "svelte-e90e77"
                    )}${attr("title", c.isLocked ? "Unlock position" : "Lock position")}${attr("aria-label", c.isLocked ? `Unlock ${componentLabel(c)}` : `Lock ${componentLabel(c)}`)}>`);
                    if (c.isLocked) {
                      $$renderer3.push("<!--[0-->");
                      Lock($$renderer3, { class: "size-3" });
                    } else {
                      $$renderer3.push("<!--[-1-->");
                      Lock_open($$renderer3, { class: "size-3" });
                    }
                    $$renderer3.push(`<!--]--></button> <button type="button" class="text-muted-foreground hover:text-destructive hover:bg-destructive/10 rounded p-1 transition" title="Remove component"${attr("aria-label", `Remove ${componentLabel(c)}`)}>`);
                    Trash_2($$renderer3, { class: "size-3" });
                    $$renderer3.push(`<!----></button></div></div>`);
                  }
                  $$renderer3.push(`<!--]--></div>`);
                }
                $$renderer3.push(`<!--]-->`);
              }
              $$renderer3.push(`<!--]--></div></div>`);
            }
            $$renderer3.push(`<!--]-->`);
          } else {
            $$renderer3.push("<!--[-1-->");
            $$renderer3.push(`<div class="space-y-3"><div class="border-border bg-muted/20 hover:bg-muted/40 hover:border-primary/50 rounded-2xl border-2 border-dashed p-4 text-center transition">`);
            Upload($$renderer3, { class: "text-muted-foreground mx-auto mb-1 size-5" });
            $$renderer3.push(`<!----> <p class="text-foreground font-sans text-xs font-bold">${escape_html(Number(draft.requested_page_count) > 1 ? `Upload Answer Sheet (${draft.requested_page_count} Pages)` : "Upload Answer Sheet")}</p> <p class="text-muted-foreground mt-0.5 font-mono text-[10px]">PDF, Word (.docx), or ${escape_html(draft.requested_page_count)}
                                        ${escape_html(Number(draft.requested_page_count) > 1 ? " Page Photos (PNG/JPG)" : " Page Photo (PNG/JPG)")}</p> <p class="text-muted-foreground mt-2 rounded-lg bg-slate-100 dark:bg-slate-800/60 px-2.5 py-1.5 text-left text-[10.5px] leading-relaxed"><span class="font-semibold text-foreground">Photo tip:</span> Place clean sheet flat on a <span class="font-semibold text-foreground">plain, dark matte background</span> with bright, even light so all 4 corners contrast sharply. `);
            if (Number(draft.requested_page_count) > 1) {
              $$renderer3.push("<!--[0-->");
              $$renderer3.push(`You can select ${escape_html(draft.requested_page_count)} photos at once to autocrop both pages sequentially.`);
            } else {
              $$renderer3.push("<!--[-1-->");
            }
            $$renderer3.push(`<!--]--></p> `);
            if (Number(draft.requested_page_count) === 1) {
              $$renderer3.push("<!--[0-->");
              $$renderer3.push(`<label class="text-foreground mx-auto mt-3 flex max-w-xs cursor-pointer items-start gap-2 text-left text-xs"><input type="checkbox"${attr("checked", imageAlreadyCropped, true)} class="accent-primary mt-0.5 size-4 shrink-0"/> <span>Image is already cropped and straight <span class="text-muted-foreground block text-[10px]">Otherwise, review paper corners before mapping.</span></span></label>`);
            } else {
              $$renderer3.push("<!--[-1-->");
            }
            $$renderer3.push(`<!--]--> <label class="text-foreground mx-auto mt-2 flex max-w-xs cursor-pointer items-start gap-2 text-left text-xs"><input type="checkbox"${attr("checked", modifyUploadedDocument, true)} class="accent-primary mt-0.5 size-4 shrink-0"/> <span>Modify document with headers, custom text, or margins <span class="text-muted-foreground block text-[10px]">Default: Use as-is for supplier/licensed stock without modifying headers or margins.</span></span></label> <input type="file" accept=".pdf,.docx,.doc,.png,.jpg,.jpeg,.webp"${attr("multiple", Number(draft.requested_page_count) > 1, true)} class="hidden"/> <button type="button"${attr("disabled", uploadRunning, true)} class="border-border bg-card text-foreground hover:bg-muted mt-2.5 rounded-lg border px-4 py-1.5 text-xs font-semibold shadow-xs transition disabled:opacity-50">Browse Computer</button></div> <div class="border-border bg-card/60 flex items-center justify-between rounded-xl border p-2.5 text-xs shadow-xs"><div class="flex items-center gap-1.5 min-w-0">`);
            Sparkles($$renderer3, { class: "size-3.5 shrink-0 text-emerald-500" });
            $$renderer3.push(`<!----> <span class="text-[11px] font-medium text-foreground truncate">Sample 150-Q sheet:</span></div> <div class="flex items-center gap-1.5 shrink-0 text-[10.5px]">`);
            if (Number(draft.requested_page_count) === 1) {
              $$renderer3.push("<!--[0-->");
              $$renderer3.push(`<button type="button"${attr("disabled", uploadRunning, true)} class="text-primary hover:underline font-semibold disabled:opacity-50">Import</button>`);
            } else {
              $$renderer3.push("<!--[-1-->");
              $$renderer3.push(`<span class="text-muted-foreground text-[10px] italic">1-page sample</span>`);
            }
            $$renderer3.push(`<!--]--> <span class="text-muted-foreground">•</span> <a href="/samples/omr/standard-150q-answer-sheet.pdf" download="standard-150q-answer-sheet.pdf" class="text-muted-foreground hover:text-foreground hover:underline">PDF</a> <span class="text-muted-foreground">•</span> <a href="/samples/omr/standard-150q-answer-sheet.png" download="standard-150q-answer-sheet.png" class="text-muted-foreground hover:text-foreground hover:underline">PNG</a></div></div> `);
            if (modifyUploadedDocument) {
              $$renderer3.push("<!--[0-->");
              $$renderer3.push(`<div class="border-border bg-card space-y-2 rounded-xl border p-3 shadow-xs"><div class="flex items-center justify-between"><span class="text-foreground font-sans text-xs font-bold">Overlay Palette</span> <span class="text-muted-foreground font-mono text-[9.5px]">Questions are configured later</span></div> <div class="grid grid-cols-2 gap-1.5"><!--[-->`);
              const each_array_7 = ensure_array_like(paletteForPath(componentPalettePath()));
              for (let $$index_7 = 0, $$length = each_array_7.length; $$index_7 < $$length; $$index_7++) {
                let item = each_array_7[$$index_7];
                $$renderer3.push(`<button type="button" class="border-border bg-background hover:bg-muted/60 hover:border-primary/50 rounded-lg border p-2 text-left transition"${attr("title", item.description)}><span class="text-foreground block text-[10px] leading-tight font-bold">${escape_html(item.label)}</span> <span class="text-muted-foreground mt-0.5 block text-[8.5px] leading-snug">${escape_html(item.description)}</span></button>`);
              }
              $$renderer3.push(`<!--]--></div> <div class="grid grid-cols-3 gap-1.5"><!--[-->`);
              const each_array_8 = ensure_array_like(availableOverlayPalette(componentPalettePath()));
              for (let $$index_8 = 0, $$length = each_array_8.length; $$index_8 < $$length; $$index_8++) {
                let item = each_array_8[$$index_8];
                $$renderer3.push(`<button type="button" class="border-border bg-background hover:bg-muted/60 hover:border-primary/50 rounded-lg border p-2 text-left transition"${attr("title", item.description)}><span class="text-foreground block text-[9.5px] leading-tight font-bold">${escape_html(item.label)}</span></button>`);
              }
              $$renderer3.push(`<!--]--></div> <button type="button" class="btn-tactile-primary w-full rounded-lg py-2 text-xs font-semibold shadow-xs transition">Open Layout &amp; Overlays</button></div>`);
            } else {
              $$renderer3.push("<!--[-1-->");
            }
            $$renderer3.push(`<!--]--> `);
            {
              $$renderer3.push("<!--[-1-->");
            }
            $$renderer3.push(`<!--]--> `);
            {
              $$renderer3.push("<!--[-1-->");
            }
            $$renderer3.push(`<!--]--> `);
            if (previewError) {
              $$renderer3.push("<!--[0-->");
              $$renderer3.push(`<div class="rounded-xl border border-rose-200 bg-rose-50 p-2.5 text-xs text-rose-800">Recognition Mapping blocked: ${escape_html(typeof previewError === "object" && previewError !== null ? previewError.detail || previewError.message || previewError.code || "" : previewError)}</div>`);
            } else {
              $$renderer3.push("<!--[-1-->");
            }
            $$renderer3.push(`<!--]--></div>`);
          }
          $$renderer3.push(`<!--]--></div>`);
        } else if (sidebarStage() === 2) {
          $$renderer3.push("<!--[1-->");
          $$renderer3.push(`<div${attr("inert", isReadOnly(), true)}>`);
          StudioOverlayPanel($$renderer3, {
            showFiducialControls: usesFiducials,
            showQrControls: qrEnabled,
            markerInsetsDisabled: !draft.marker_profile_id || markerInsetsSaving,
            onMarkerInsetsPreview: previewMarkerInsets,
            onMarkerInsetsCommit: persistMarkerInsets,
            markerProfiles: marker_profiles,
            selectedMarkerProfileId: draft.marker_profile_id,
            onMarkerProfileChange: selectMarkerProfile,
            onChanged: handleHtmlDecorationChanged,
            onBack: () => requestGoToStage(1),
            onNext: () => requestGoToStage(3),
            get marginMm() {
              return marginMm;
            },
            set marginMm($$value) {
              marginMm = $$value;
              $$settled = false;
            },
            get markerInsetsMm() {
              return markerInsetsMm;
            },
            set markerInsetsMm($$value) {
              markerInsetsMm = $$value;
              $$settled = false;
            },
            get markerPattern() {
              return markerPattern;
            },
            set markerPattern($$value) {
              markerPattern = $$value;
              $$settled = false;
            },
            get showHeaderOverlay() {
              return showHeaderOverlay;
            },
            set showHeaderOverlay($$value) {
              showHeaderOverlay = $$value;
              $$settled = false;
            },
            get headerTitle() {
              return headerTitle;
            },
            set headerTitle($$value) {
              headerTitle = $$value;
              $$settled = false;
            },
            get headerSubtitle() {
              return headerSubtitle;
            },
            set headerSubtitle($$value) {
              headerSubtitle = $$value;
              $$settled = false;
            },
            get headerFontSize() {
              return headerFontSize;
            },
            set headerFontSize($$value) {
              headerFontSize = $$value;
              $$settled = false;
            },
            get headerFontFamily() {
              return headerFontFamily;
            },
            set headerFontFamily($$value) {
              headerFontFamily = $$value;
              $$settled = false;
            },
            get headerColor() {
              return headerColor;
            },
            set headerColor($$value) {
              headerColor = $$value;
              $$settled = false;
            },
            get headerAlign() {
              return headerAlign;
            },
            set headerAlign($$value) {
              headerAlign = $$value;
              $$settled = false;
            },
            get headerLocked() {
              return headerLocked;
            },
            set headerLocked($$value) {
              headerLocked = $$value;
              $$settled = false;
            },
            get showQrOverlay() {
              return showQrOverlay;
            },
            set showQrOverlay($$value) {
              showQrOverlay = $$value;
              $$settled = false;
            },
            get qrX() {
              return qrX;
            },
            set qrX($$value) {
              qrX = $$value;
              $$settled = false;
            },
            get qrY() {
              return qrY;
            },
            set qrY($$value) {
              qrY = $$value;
              $$settled = false;
            },
            get qrW() {
              return qrW;
            },
            set qrW($$value) {
              qrW = $$value;
              $$settled = false;
            },
            get qrH() {
              return qrH;
            },
            set qrH($$value) {
              qrH = $$value;
              $$settled = false;
            },
            get qrLocked() {
              return qrLocked;
            },
            set qrLocked($$value) {
              qrLocked = $$value;
              $$settled = false;
            },
            get showInstructionsOverlay() {
              return showInstructionsOverlay;
            },
            set showInstructionsOverlay($$value) {
              showInstructionsOverlay = $$value;
              $$settled = false;
            },
            get instructionsContent() {
              return instructionsContent;
            },
            set instructionsContent($$value) {
              instructionsContent = $$value;
              $$settled = false;
            },
            get instructionsFontSize() {
              return instructionsFontSize;
            },
            set instructionsFontSize($$value) {
              instructionsFontSize = $$value;
              $$settled = false;
            },
            get instructionsFontFamily() {
              return instructionsFontFamily;
            },
            set instructionsFontFamily($$value) {
              instructionsFontFamily = $$value;
              $$settled = false;
            },
            get instructionsColor() {
              return instructionsColor;
            },
            set instructionsColor($$value) {
              instructionsColor = $$value;
              $$settled = false;
            },
            get instLocked() {
              return instLocked;
            },
            set instLocked($$value) {
              instLocked = $$value;
              $$settled = false;
            },
            get showSignatureOverlay() {
              return showSignatureOverlay;
            },
            set showSignatureOverlay($$value) {
              showSignatureOverlay = $$value;
              $$settled = false;
            },
            get signatureContent() {
              return signatureContent;
            },
            set signatureContent($$value) {
              signatureContent = $$value;
              $$settled = false;
            },
            get signatureFontSize() {
              return signatureFontSize;
            },
            set signatureFontSize($$value) {
              signatureFontSize = $$value;
              $$settled = false;
            },
            get signatureFontFamily() {
              return signatureFontFamily;
            },
            set signatureFontFamily($$value) {
              signatureFontFamily = $$value;
              $$settled = false;
            },
            get signatureColor() {
              return signatureColor;
            },
            set signatureColor($$value) {
              signatureColor = $$value;
              $$settled = false;
            },
            get sigLocked() {
              return sigLocked;
            },
            set sigLocked($$value) {
              sigLocked = $$value;
              $$settled = false;
            }
          });
          $$renderer3.push(`<!----></div>`);
        } else if (sidebarStage() === 3) {
          $$renderer3.push("<!--[2-->");
          $$renderer3.push(`<div data-omr-stage-panel="3">`);
          if (stage1Mode !== "html" && draft.original_photo_url && draft.can_edit) {
            $$renderer3.push("<!--[0-->");
            $$renderer3.push(`<div class="text-muted-foreground mb-3 flex items-start justify-between gap-3 text-xs"><p class="min-w-0 leading-relaxed">Crop misaligned? Reopen the original to correct it. Guides stay editable, but must be realigned and reviewed before verification.</p> <button type="button"${attr("disabled", isReadOnly() || uploadRunning || stageTransitionRunning, true)} class="text-primary focus-visible:ring-ring shrink-0 rounded-md px-2 py-1 font-semibold underline underline-offset-2 hover:text-primary/80 focus-visible:ring-2 focus-visible:outline-none disabled:cursor-not-allowed disabled:opacity-50">Correct crop from original</button></div> `);
            {
              $$renderer3.push("<!--[-1-->");
            }
            $$renderer3.push(`<!--]-->`);
          } else {
            $$renderer3.push("<!--[-1-->");
          }
          $$renderer3.push(`<!--]--> `);
          if (cropReviewRequired) {
            $$renderer3.push("<!--[0-->");
            $$renderer3.push(`<div role="alert" class="mb-3 rounded-xl border border-amber-300 bg-amber-50 p-3 text-xs text-amber-950 dark:border-amber-800 dark:bg-amber-950/30 dark:text-amber-100"><p class="font-semibold">Crop changed. Existing guides are not yet verified
                                    against this image.</p> <p class="mt-1">Move each question and Applicant Number guide onto
                                    its printed bubbles. The old positions are not
                                    automatically transformed.</p> <button type="button"${attr("disabled", isReadOnly() || saveStatus === "saving" || !orderedPages[0]?.confirmed, true)} class="mt-2 rounded-lg border border-amber-600 px-3 py-1.5 font-semibold hover:bg-amber-100 disabled:opacity-50 dark:hover:bg-amber-900/50">I reviewed and aligned all guides</button></div>`);
          } else {
            $$renderer3.push("<!--[-1-->");
          }
          $$renderer3.push(`<!--]--> `);
          StudioBubblePanel($$renderer3, {
            showRegistrationControls: usesFiducials,
            isUploadedSource: stage1Mode !== "html",
            pageCount: Number(draft.requested_page_count) || orderedPages.length || 1,
            activePageNo,
            identity: identityDeclaration,
            onIdentityChanged: (value) => {
              identityDeclaration = value;
            },
            aptitudeAreas: aptitude_areas,
            testInstrument: test_instrument,
            markerProfiles: marker_profiles,
            selectedMarkerProfileId: draft.marker_profile_id,
            onMarkerProfileChange: selectMarkerProfile,
            canonicalPreview,
            previewRunning,
            previewError,
            transitionRunning: stageTransitionRunning,
            mappingDirty,
            mappingErrors: studioSaveIssues,
            canUndo: historyStatus().canUndo,
            canRedo: historyStatus().canRedo,
            onUndo: undoEditor,
            onRedo: redoEditor,
            onChanged: recordBubbleEdit,
            onDocumentFieldsChanged: () => recordBubbleEdit(),
            readOnly: isReadOnly(),
            onBack: () => requestGoToStage(modifyUploadedDocument ? 2 : 1),
            onNext: () => requestGoToStage(4),
            get sections() {
              return sections;
            },
            set sections($$value) {
              sections = $$value;
              $$settled = false;
            },
            get activeSectionIndex() {
              return activeSectionIndex;
            },
            set activeSectionIndex($$value) {
              activeSectionIndex = $$value;
              $$settled = false;
            },
            get selectedSectionIndices() {
              return selectedSectionIndices;
            },
            set selectedSectionIndices($$value) {
              selectedSectionIndices = $$value;
              $$settled = false;
            },
            get plottingConfig() {
              return plottingConfig;
            },
            set plottingConfig($$value) {
              plottingConfig = $$value;
              $$settled = false;
            },
            get declaredInventory() {
              return declaredInventory;
            },
            set declaredInventory($$value) {
              declaredInventory = $$value;
              $$settled = false;
            },
            get documentFields() {
              return documentFields;
            },
            set documentFields($$value) {
              documentFields = $$value;
              $$settled = false;
            },
            get activeDocumentFieldId() {
              return activeDocumentFieldId;
            },
            set activeDocumentFieldId($$value) {
              activeDocumentFieldId = $$value;
              $$settled = false;
            },
            get previewSurface() {
              return previewSurface;
            },
            set previewSurface($$value) {
              previewSurface = $$value;
              $$settled = false;
            }
          });
          $$renderer3.push(`<!----></div>`);
        } else if (sidebarStage() === 4) {
          $$renderer3.push("<!--[3-->");
          $$renderer3.push(`<div data-omr-stage-panel="4" class="studio-verification-panel min-w-0 svelte-e90e77">`);
          StudioExportVerifyPanel($$renderer3, {
            urls,
            getCsrfToken: csrfToken,
            verification_run: activeVerificationRun,
            physical_run_id,
            marker_profile_status: selectedMarkerProfile()?.status ?? null,
            onDraftLockVersion: syncDraftLockVersion,
            onBack: () => requestGoToStage(3),
            lifecycleBusy: stageTransitionRunning,
            get draft() {
              return draft;
            },
            set draft($$value) {
              draft = $$value;
              $$settled = false;
            }
          });
          $$renderer3.push(`<!----></div>`);
        } else {
          $$renderer3.push("<!--[-1-->");
        }
        $$renderer3.push(`<!--]-->`);
      }
      $$renderer3.push(`<!----></div> `);
      if (currentStage === 1) {
        $$renderer3.push("<!--[0-->");
        $$renderer3.push(`<div class="border-border bg-card/90 dark:bg-card/95 shrink-0 border-t p-3 backdrop-blur-xs"><button type="button" class="bg-primary text-primary-foreground hover:bg-primary/90 flex w-full items-center justify-center gap-1.5 rounded-xl py-2 text-xs font-semibold shadow-xs transition"><span>${escape_html(stage1Mode === "html" ? "Continue to Recognition Mapping" : "Continue to Layout & Markers")}</span> `);
        Chevron_right($$renderer3, { class: "size-3.5" });
        $$renderer3.push(`<!----></button></div>`);
      } else {
        $$renderer3.push("<!--[-1-->");
      }
      $$renderer3.push(`<!--]--></aside> `);
      if (photoCropFile && photoCropUrl) {
        $$renderer3.push("<!--[0-->");
        $$renderer3.push(`<div class="fixed inset-0 z-[70] flex items-center justify-center bg-slate-900/70 p-4"><div role="dialog" aria-modal="true" tabindex="-1" class="border-border bg-card flex max-h-[95vh] w-full max-w-2xl flex-col gap-3 rounded-2xl border p-4 shadow-2xl sm:p-6"><div class="flex flex-wrap items-start justify-between gap-2"><div><h3 class="text-foreground text-base font-bold">${escape_html(Number(draft.requested_page_count) > 1 ? `Check the paper edges · Page ${photoCropTargetPageNo} of ${draft.requested_page_count}` : "Check the paper edges")}</h3> <p class="text-muted-foreground mt-1 text-xs">${escape_html("Position all four corners on the exact paper vertices using the 2.5× magnifier.")}</p> `);
        if (photoCropIsCorrection) {
          $$renderer3.push("<!--[0-->");
          $$renderer3.push(`<p class="mt-1 text-xs text-amber-700 dark:text-amber-300">Straightening changes the recognition image. Your
                                saved guides remain editable but cannot be treated
                                as aligned until you check them against the new crop.</p> <p class="mt-1 text-xs font-semibold text-amber-700 dark:text-amber-300">Set all four corners manually (${escape_html(photoCropTouchedCorners.length)}/4 positioned).
                                Automatic suggestions are not used for correction.</p>`);
        } else {
          $$renderer3.push("<!--[-1-->");
        }
        $$renderer3.push(`<!--]--></div> <div class="flex flex-wrap items-center gap-1.5"><button type="button" data-omr-crop-reset="true"${attr("disabled", uploadRunning, true)} class="border-border bg-muted/60 hover:bg-muted text-foreground rounded-lg border px-2.5 py-1.5 text-xs font-semibold transition disabled:opacity-50" title="Snap 4 corners to sheet bounds with a 2% margin">Reset to Sheet</button> <button type="button" data-omr-crop-full="true"${attr("disabled", uploadRunning, true)} class="border-border bg-muted/60 hover:bg-muted text-foreground rounded-lg border px-2.5 py-1.5 text-xs font-semibold transition disabled:opacity-50" title="Snap to exact full image bounds (0% inset)">Full Image</button> <button type="button" data-omr-crop-rotate="true"${attr("disabled", uploadRunning, true)} class="border-border bg-muted/60 hover:bg-muted text-foreground rounded-lg border px-2.5 py-1.5 text-xs font-semibold transition disabled:opacity-50" title="Rotate photo 90° clockwise">Rotate 90°</button></div></div> <div class="flex min-h-0 items-center justify-center overflow-hidden rounded-lg bg-slate-800 p-2"><div class="relative mx-auto w-fit max-w-full select-none"${attr("data-omr-crop-rotation", photoCropRotation)}><img${attr("src", photoCropUrl)} alt="Uploaded answer sheet before cropping" class="block max-h-[calc(95vh-13rem)] w-auto max-w-full"/> <svg viewBox="0 0 1000 1000" preserveAspectRatio="none" aria-hidden="true" class="pointer-events-none absolute inset-0 size-full"><polygon${attr("points", photoCropCorners.map((point) => `${point.x * 1e3},${point.y * 1e3}`).join(" "))} fill="rgba(16,185,129,0.12)" stroke="#10b981" stroke-width="4" vector-effect="non-scaling-stroke"></polygon></svg> <!--[-->`);
        const each_array_9 = ensure_array_like(photoCropCorners);
        for (let index = 0, $$length = each_array_9.length; index < $$length; index++) {
          let corner = each_array_9[index];
          $$renderer3.push(`<button type="button"${attr("data-omr-crop-pin", index)}${attr("aria-label", `${["Top left", "Top right", "Bottom right", "Bottom left"][index]} paper corner; drag or use arrow keys`)}${attr_class(
            `absolute size-6 -translate-x-1/2 -translate-y-1/2 touch-none rounded-full border-2 border-white shadow-md transition-transform focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-emerald-400 ${photoCropDragging === index ? "scale-125 bg-emerald-500 ring-4 ring-emerald-400/50" : "bg-emerald-600 hover:scale-110"}`,
            "svelte-e90e77"
          )}${attr_style(`left: ${corner.x * 100}%; top: ${corner.y * 100}%`)}></button>`);
        }
        $$renderer3.push(`<!--]--> `);
        if (photoCropDragging >= 0 && photoCropCorners[photoCropDragging]) {
          $$renderer3.push("<!--[0-->");
          const activeCorner = photoCropCorners[photoCropDragging];
          const cornerLabel = ["TL", "TR", "BR", "BL"][photoCropDragging] ?? "Pin";
          const surfaceW = 360;
          const surfaceH = 500;
          const loupeVerticalOffset = activeCorner.y < 0.25 ? "4rem" : "-4.5rem";
          $$renderer3.push(`<div data-omr-crop-loupe="true"${attr("data-omr-crop-loupe-corner", photoCropDragging)} class="pointer-events-none absolute z-30 size-28 -translate-x-1/2 -translate-y-1/2 overflow-hidden rounded-full border-2 border-emerald-400 bg-slate-950 shadow-2xl ring-2 ring-slate-900/80"${attr_style(`left: ${activeCorner.x * 100}%; top: calc(${activeCorner.y * 100}% + ${loupeVerticalOffset});`)}><img${attr("src", photoCropUrl)} alt="2.5x magnified corner loupe" class="pointer-events-none absolute max-w-none select-none"${attr_style(`width: ${surfaceW * 2.5}px; height: ${surfaceH * 2.5}px; left: ${56 - activeCorner.x * surfaceW * 2.5}px; top: ${56 - activeCorner.y * surfaceH * 2.5}px;`)}/> <div class="pointer-events-none absolute inset-0 flex items-center justify-center"><div class="h-full w-px bg-emerald-400/85"></div> <div class="absolute h-px w-full bg-emerald-400/85"></div> <div class="size-2.5 rounded-full border border-white bg-emerald-500/40"></div></div> <div class="pointer-events-none absolute bottom-1 left-1/2 -translate-x-1/2 rounded bg-slate-900/90 px-1.5 py-0.5 font-mono text-[9px] font-bold text-emerald-300">${escape_html(cornerLabel)} · 2.5×</div></div>`);
        } else {
          $$renderer3.push("<!--[-1-->");
        }
        $$renderer3.push(`<!--]--></div></div> `);
        {
          $$renderer3.push("<!--[-1-->");
        }
        $$renderer3.push(`<!--]--> <div class="flex flex-wrap justify-end gap-2"><button type="button"${attr("disabled", uploadRunning, true)} class="text-foreground rounded-lg px-4 py-2 text-sm font-medium hover:bg-muted disabled:opacity-50">Cancel</button> <button type="button"${attr("disabled", photoCropIsCorrection && photoCropTouchedCorners.length !== 4, true)} class="rounded-lg bg-emerald-700 px-4 py-2 text-sm font-semibold text-white hover:bg-emerald-800 disabled:opacity-50">${escape_html(pendingCropQueue.length > 1 && pendingCropQueue.indexOf(photoCropFile) < pendingCropQueue.length - 1 && photoCropTargetPageNo < Number(draft.requested_page_count) ? `Straighten & Continue to Page ${photoCropTargetPageNo + 1}` : Number(draft.requested_page_count) > 1 ? `Straighten Page ${photoCropTargetPageNo}` : "Straighten page")}</button></div></div></div>`);
      } else {
        $$renderer3.push("<!--[-1-->");
      }
      $$renderer3.push(`<!--]--> `);
      {
        $$renderer3.push("<!--[-1-->");
      }
      $$renderer3.push(`<!--]--> `);
      if (showResetConfirmModal) {
        $$renderer3.push("<!--[0-->");
        $$renderer3.push(`<div class="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/60 p-4 backdrop-blur-xs"><div class="border-border bg-card text-foreground w-full max-w-md space-y-4 rounded-2xl border p-6 shadow-2xl"><div class="flex items-center space-x-3"><div class="flex size-10 shrink-0 items-center justify-center rounded-xl bg-amber-500/15 text-amber-600 dark:text-amber-400">`);
        Triangle_alert($$renderer3, { class: "size-5" });
        $$renderer3.push(`<!----></div> <div><h3 class="text-foreground text-sm font-bold">Re-edit Design &amp; Reset Raster?</h3> <p class="text-muted-foreground mt-0.5 text-xs">Returning to Step ${escape_html(pendingTargetStage)} resets the print
                            sheet.</p></div></div> <p class="border-border bg-muted/40 text-muted-foreground rounded-xl border p-3 text-xs leading-relaxed">Step 3 relies on a settled, composed print raster for
                    optical bubble calibration. Going back to modify HTML source
                    or overlays will invalidate the current raster image so you
                    can re-render a fresh document.</p> <div class="flex items-center justify-end space-x-2 pt-2"><button type="button" class="text-muted-foreground hover:bg-muted hover:text-foreground rounded-xl px-3.5 py-2 text-xs font-semibold transition">Cancel &amp; Stay on Step ${escape_html(currentStage)}</button> <button type="button" class="rounded-xl bg-amber-600 px-4 py-2 text-xs font-semibold text-white shadow-xs transition hover:bg-amber-700">Yes, Reset Raster &amp; Re-edit</button></div></div></div>`);
      } else {
        $$renderer3.push("<!--[-1-->");
      }
      $$renderer3.push(`<!--]--> `);
      {
        $$renderer3.push("<!--[-1-->");
      }
      $$renderer3.push(`<!--]--> `);
      {
        $$renderer3.push("<!--[-1-->");
      }
      $$renderer3.push(`<!--]--></div>`);
    }
    do {
      $$settled = true;
      $$inner_renderer = $$renderer2.copy();
      $$render_inner($$inner_renderer);
    } while (!$$settled);
    $$renderer2.subsume($$inner_renderer);
  });
}
export {
  OmrTemplateStudio as default
};
