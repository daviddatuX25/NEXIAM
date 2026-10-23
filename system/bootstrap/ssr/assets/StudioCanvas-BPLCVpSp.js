import { c as attr_class, e as escape_html, f as attr_style, b as attr, a as ensure_array_like, l as bind_props, d as derived } from "../app.js";
import { i as isQuestionGridComponent, b as canvasComponentBlockSizeMm, g as gridOptionLabels, d as canvasRollRowGapPx, e as canvasRollBubbleGapPx, f as componentBackgroundColor, G as GRID_SECTION_HEADER_HEIGHT_MM, h as componentInputBackgroundColor, j as CANVAS_BUBBLE_DIAMETER_PX } from "./omrComponents-D81Zk9FJ.js";
import { e as effectiveRecognitionGeometryMm, r as recognitionOriginOffsetMm, f as bubbleLabelLayout, g as multiplyItemsDownward, h as mmToPx, p as pxToMm, i as verticalRollBubblePositionsPx, P as PX_PER_MM } from "./omrStudioGeometry-DyCkNaaw.js";
import { M as Minus } from "./minus-Dqkhcxt7.js";
import { P as Plus } from "./plus-DjyPLC7A.js";
import { L as Lock } from "./lock-DAA4kTLl.js";
import { L as Loader_circle } from "./loader-circle-_UEl10Lx.js";
import "axios";
import "@inertiajs/core";
import "clsx";
import "laravel-precognition";
import "tailwind-merge";
import "tailwind-variants";
import "./Icon-Dal7PqKK.js";
function StudioCanvas($$renderer, $$props) {
  $$renderer.component(($$renderer2) => {
    let {
      scale = 1,
      panX = 0,
      panY = 0,
      marginMm = 15,
      markerInsetsMm = { top: 12, right: 12, bottom: 14, left: 12 },
      markerPattern = "square",
      showFiducials = true,
      activePageNo = 1,
      canvasLocked = false,
      rasterUrl = null,
      showHeaderOverlay = true,
      headerTitle = "EXAMINATION ANSWER SHEET",
      headerSubtitle = "AUTOMATED OMR EVALUATION FORM",
      headerFontSize = 14,
      headerFontFamily = "Inter, sans-serif",
      headerColor = "#0f172a",
      headerAlign = "left",
      headerX = 25,
      headerY = 20,
      headerW = 160,
      headerH = 15,
      headerLocked = false,
      showQrOverlay = true,
      qrX = 150,
      qrY = 32,
      qrW = 32,
      qrH = 32,
      qrLocked = false,
      showInstructionsOverlay = true,
      instructionsContent = "Use 2B pencil only.",
      instructionsFontSize = 9,
      instructionsFontFamily = "Inter, sans-serif",
      instructionsColor = "#334155",
      instX = 25,
      instY = 250,
      instW = 90,
      instH = 28,
      instLocked = false,
      showSignatureOverlay = true,
      signatureContent = "EXAMINEE SIGNATURE",
      signatureFontSize = 8.5,
      signatureFontFamily = "Inter, sans-serif",
      signatureColor = "#475569",
      sigX = 125,
      sigY = 250,
      sigW = 60,
      sigH = 28,
      sigLocked = false,
      customTextItems = [],
      htmlContent = null,
      canonicalPreview = null,
      previewSurface = "canonical",
      showCanonicalPrintPreview = false,
      mappingPreviewStale = false,
      isHtmlMode = true,
      isUploadedSource = false,
      isComposing = false,
      sections = [],
      activeSectionIndex = 0,
      selectedSectionIndices = [],
      components = [],
      activeComponentId = null,
      activeComponentIds = [],
      activeItemIds = [],
      currentStage = 1,
      plottingConfig = {
        active: false,
        mode: "consecutive",
        areaCode: "GENERAL",
        itemNumber: 1,
        optionsCount: 4,
        optionLabels: ["A", "B", "C", "D"],
        bubbleDiameterMm: 4.5,
        currentOptionIndex: 0,
        pointsMm: [],
        respotSectionIndex: null,
        respotOptionIndex: 0,
        stampSourceSection: null
      },
      onGeometryChanged = null,
      onComponentGeometryChanged = null,
      onComponentSelected = null,
      onCanvasItemSelected = null,
      onCanvasSelectionClear = null,
      onDuplicateComponents = null,
      onUndo = null,
      onRedo = null,
      identity = null,
      onIdentityChanged = null,
      documentFields = [],
      activeDocumentFieldId = null,
      onDocumentFieldsChanged = null,
      readOnly = false,
      sheetImageLoaded = false
    } = $$props;
    const activeDocumentFields = derived(() => documentFields.filter((field) => Number(field.page_no ?? 1) === Number(activePageNo)));
    const pageComponents = derived(() => components.filter((component) => (Number(component.page_no) || 1) === activePageNo));
    const pageSections = derived(() => sections.filter((section) => (Number(section.page_no) || 1) === activePageNo));
    const activeSheetImageUrl = derived(() => {
      const previewPages = canonicalPreview?.artifact?.pages;
      if (Array.isArray(previewPages) && previewPages.length > 0) {
        const match = previewPages.find((p) => Number(p.page_no) === Number(activePageNo));
        if (match?.canonical_url) {
          return match.canonical_url;
        }
      }
      if (Number(activePageNo) === 1 && canonicalPreview?.artifact?.canonical_url) {
        return canonicalPreview.artifact.canonical_url;
      }
      if (!isHtmlMode) {
        return rasterUrl ?? null;
      }
      return null;
    });
    const hasWriteIn = derived(() => Boolean(identity?.has_write_in ?? identity?.strategy === "bubbles_and_write_in" ?? identity?.strategy === "write_in_only" ?? identity?.method === "write_in_manual" ?? (Array.isArray(identity?.write_in_boxes) && identity.write_in_boxes.length > 0)));
    const activeWriteInBox = derived(() => hasWriteIn() ? (identity?.write_in_boxes ?? []).find((b) => Number(b.page_no) === activePageNo) ?? {
      page_no: activePageNo,
      x_mm: 110,
      y_mm: 18,
      w_mm: 42,
      h_mm: 8.5
    } : null);
    const QR_CANVAS_ITEM_ID = "qr";
    let cursorPaperMm = null;
    let autoGridActive = false;
    let selectedOverlay = "header";
    let isComponentResizing = false;
    let isDuplicateDragging = false;
    let isDragging = derived(() => isComponentResizing);
    let safeHtmlContent = derived(() => {
      if (!htmlContent) return null;
      return htmlContent.replace(/<script\b[^<]*(?:(?!<\/script>)<[^<]*)*<\/script>/gi, "").replace(/\son\w+\s*=\s*(['"]).*?\1/gi, "");
    });
    function recognitionGuideSizeMm(section) {
      const geometry = effectiveRecognitionGeometryMm({
        kind: section.type === "roll" ? "roll" : "grid",
        bubbleDiameterMm: section.bubbleDiameterMm,
        bubblePitchMm: section.bubblePitchMm,
        rowPitchMm: section.rowPitchMm,
        minBubbleMm: isUploadedSource ? 2.5 : 4.2
      });
      if (section.type === "roll") {
        const isHorizontalRoll = section.rollDirection === "horizontal";
        const digits = Math.max(1, Number(section.questionsCount) || 7);
        const columns = isHorizontalRoll ? 10 : digits;
        const rows2 = isHorizontalRoll ? digits : 10;
        return {
          w_mm: geometry.bubbleDiameterMm + (columns - 1) * geometry.bubblePitchMm,
          h_mm: geometry.bubbleDiameterMm + (rows2 - 1) * geometry.rowPitchMm
        };
      }
      const options = getOptionLabels(section).length;
      const rows = Math.max(1, Number(section.questionsCount) || 1);
      return {
        w_mm: geometry.bubbleDiameterMm + (options - 1) * geometry.bubblePitchMm,
        h_mm: geometry.bubbleDiameterMm + (rows - 1) * geometry.rowPitchMm
      };
    }
    function recognitionGuideBounds(section, posX = Number(section.posX), posY = Number(section.posY)) {
      const bounds = recognitionGuideSizeMm(section);
      const offset = recognitionOriginOffsetMm(section);
      return {
        x: posX + offset.x,
        y: posY + offset.y,
        w: bounds.w_mm,
        h: bounds.h_mm
      };
    }
    function localRecognitionBubbles(section) {
      const origin = recognitionGuideBounds(section);
      const geometry = effectiveRecognitionGeometryMm({
        kind: section.type === "roll" ? "roll" : "grid",
        bubbleDiameterMm: section.bubbleDiameterMm,
        bubblePitchMm: section.bubblePitchMm,
        rowPitchMm: section.rowPitchMm,
        minBubbleMm: isUploadedSource ? 2.5 : 4.2
      });
      const bubbleSizePx = mmToPx(geometry.bubbleDiameterMm);
      const bubbleW = bubbleSizePx / PX_PER_MM;
      if (Array.isArray(section.optionPointsMm) && section.optionPointsMm.length > 0 && (Number(section.questionsCount) || 1) === 1) {
        return section.optionPointsMm.map((pt) => ({
          label: pt.label,
          x_mm: pxToMm(mmToPx(pt.x_mm) - Math.floor(bubbleSizePx / 2)),
          y_mm: pxToMm(mmToPx(pt.y_mm) - Math.floor(bubbleSizePx / 2)),
          w_mm: bubbleW,
          h_mm: bubbleW
        }));
      }
      const labels = getOptionLabels(section);
      const optionCount = labels.length;
      const isHorizontalRoll = section.type === "roll" && section.rollDirection === "horizontal";
      const rowCount = section.type === "roll" ? isHorizontalRoll ? Math.max(1, Number(section.questionsCount) || 7) : 10 : Math.max(1, Number(section.questionsCount) || 1);
      const columnCount = section.type === "roll" ? isHorizontalRoll ? 10 : Math.max(1, Number(section.questionsCount) || 7) : optionCount;
      const originXPx = mmToPx(origin.x);
      const originYPx = mmToPx(origin.y);
      const xPitchPx = mmToPx(geometry.bubblePitchMm);
      const yPitchPx = mmToPx(geometry.rowPitchMm);
      if (section.type === "roll" && section.identity_grid === true && section.rollDirection !== "horizontal" && Number(section.rotationDeg ?? 0) !== 0) {
        const positions = verticalRollBubblePositionsPx({
          originX: originXPx,
          originY: originYPx,
          digitCount: columnCount,
          digitPitchPx: xPitchPx,
          valuePitchPx: yPitchPx,
          bubbleWidthPx: bubbleSizePx,
          rotationDeg: Number(section.rotationDeg)
        });
        return positions.flatMap((digitPositions) => digitPositions.map(([x, y], value) => ({
          label: String(value),
          x_mm: pxToMm(x),
          y_mm: pxToMm(y),
          w_mm: bubbleW,
          h_mm: bubbleW
        })));
      }
      const bubbles = [];
      for (let row = 0; row < rowCount; row += 1) {
        for (let column = 0; column < columnCount; column += 1) {
          const bubbleLabel = section.type === "roll" ? isHorizontalRoll ? String(column) : String(row) : labels[column];
          bubbles.push({
            label: bubbleLabel,
            x_mm: (originXPx + column * xPitchPx) / PX_PER_MM,
            y_mm: (originYPx + row * yPitchPx) / PX_PER_MM,
            w_mm: bubbleW,
            h_mm: bubbleW
          });
        }
      }
      return bubbles;
    }
    function componentLabel(c) {
      if (!c) return "Component";
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
          return c.type || "Component";
      }
    }
    function getOptionLabels(s) {
      if (!s) return ["A", "B", "C", "D"];
      if (s.type === "roll") return ["0", "1", "2", "3", "4", "5", "6", "7", "8", "9"];
      if (s.type === "tf") return ["T", "F"];
      if (s.type === "likert") return ["1", "2", "3", "4", "5"];
      if (s.type === "custom") return (s.customValuesStr || "A, B").split(",").map((v) => v.trim()).filter(Boolean);
      if (Array.isArray(s.optionLabels) && s.optionLabels.length >= 2) {
        return s.optionLabels;
      }
      if (s.customValuesStr) {
        const parsed = s.customValuesStr.split(",").map((v) => v.trim()).filter(Boolean);
        if (parsed.length >= 2) return parsed;
      }
      const count = Number(s.optionsCount) || 4;
      const alphabet = ["A", "B", "C", "D", "E", "F", "G", "H", "I", "J"];
      return alphabet.slice(0, Math.max(2, Math.min(10, count)));
    }
    function markerClassName(pattern) {
      if (pattern === "target") return "marker-target";
      if (pattern === "aruco") return "marker-aruco";
      return "marker-box";
    }
    $$renderer2.push(`<div class="bg-muted relative flex h-full w-full flex-1 flex-col overflow-hidden select-none"><div${attr_class(
      `relative h-full w-full overflow-hidden ${plottingConfig?.active || autoGridActive ? "cursor-crosshair" : "cursor-grab active:cursor-grabbing"} canvas-grid`,
      "svelte-1pe0x5v"
    )} role="region" aria-label="OMR sheet design viewport" aria-keyshortcuts="Delete Backspace Control+D Meta+D Control+Z Control+Y Control+C Control+V">`);
    if (currentStage === 3 && plottingConfig?.active) {
      $$renderer2.push("<!--[0-->");
      $$renderer2.push(`<div data-omr-plotting-banner="true" class="pointer-events-auto absolute top-16 left-1/2 z-50 flex -translate-x-1/2 items-center gap-3 rounded-xl border border-emerald-400/70 bg-slate-900/95 px-3.5 py-2 text-xs font-semibold text-white shadow-2xl backdrop-blur-sm" role="status">`);
      if (plottingConfig.mode === "paste_stamp") {
        $$renderer2.push("<!--[0-->");
        $$renderer2.push(`<span class="rounded-md bg-amber-500 px-1.5 py-0.5 font-mono text-[10px] font-bold text-slate-950">STAMP Q${escape_html(plottingConfig.itemNumber)}</span> <span>Click on the sheet to paste <strong>${escape_html(plottingConfig.areaCode)} · Q${escape_html(plottingConfig.itemNumber)}</strong> (auto-increments on each click)</span>`);
      } else if (plottingConfig.mode === "respot_single") {
        $$renderer2.push("<!--[1-->");
        $$renderer2.push(`<span class="rounded-md bg-sky-500 px-1.5 py-0.5 font-mono text-[10px] font-bold text-slate-950">RE-SPOT</span> <span>Click on the sheet to re-position Option <strong>${escape_html(plottingConfig.optionLabels?.[plottingConfig.respotOptionIndex] ?? "A")}</strong></span>`);
      } else if (plottingConfig.targetType === "applicant_digit") {
        $$renderer2.push("<!--[2-->");
        $$renderer2.push(`<span class="flex size-5 items-center justify-center rounded-full bg-indigo-500 font-mono text-[11px] font-black text-white">#</span> <span>`);
        if (plottingConfig.digitSpanMode) {
          $$renderer2.push("<!--[0-->");
          if (!plottingConfig.pointsMm?.length) {
            $$renderer2.push("<!--[0-->");
            $$renderer2.push(`Step 1 of 2: Click center of <strong>Bubble 0</strong> (start of Digit 1)`);
          } else {
            $$renderer2.push("<!--[-1-->");
            $$renderer2.push(`Step 2 of 2: Click center of <strong>Bubble 9</strong> (end of Digit 1) <span class="ml-2 font-normal text-indigo-300 opacity-90">· Hold <kbd class="rounded bg-slate-800 px-1 py-0.5 font-mono text-[9px] font-bold text-white">Shift</kbd> to lock column</span>`);
          }
          $$renderer2.push(`<!--]-->`);
        } else {
          $$renderer2.push("<!--[-1-->");
          $$renderer2.push(`Plotting Digit 1: Click center of <strong>Bubble ${escape_html(plottingConfig.optionLabels?.[plottingConfig.currentOptionIndex] ?? "0")}</strong> (${escape_html((plottingConfig.currentOptionIndex ?? 0) + 1)} of 10) `);
          if ((plottingConfig.currentOptionIndex ?? 0) > 0) {
            $$renderer2.push("<!--[0-->");
            $$renderer2.push(`<span class="ml-2 font-normal text-indigo-300 opacity-90">· Hold <kbd class="rounded bg-slate-800 px-1 py-0.5 font-mono text-[9px] font-bold text-white">Shift</kbd> to lock column</span>`);
          } else {
            $$renderer2.push("<!--[-1-->");
          }
          $$renderer2.push(`<!--]-->`);
        }
        $$renderer2.push(`<!--]--></span>`);
      } else if (plottingConfig.questionSpanMode) {
        $$renderer2.push("<!--[3-->");
        const optList = plottingConfig.optionLabels ?? ["A", "B", "C", "D"];
        $$renderer2.push(`<span class="flex size-5 items-center justify-center rounded-full bg-emerald-500 font-mono text-[11px] font-black text-slate-950">${escape_html(plottingConfig.pointsMm?.length ? optList[optList.length - 1] : optList[0])}</span> <span>`);
        if (!plottingConfig.pointsMm?.length) {
          $$renderer2.push("<!--[0-->");
          $$renderer2.push(`Plotting <strong>${escape_html(plottingConfig.areaCode)} · Q${escape_html(plottingConfig.itemNumber)}</strong>: Step 1 of 2: Click center of Option <strong>${escape_html(optList[0])}</strong>`);
        } else {
          $$renderer2.push("<!--[-1-->");
          $$renderer2.push(`Plotting <strong>${escape_html(plottingConfig.areaCode)} · Q${escape_html(plottingConfig.itemNumber)}</strong>: Step 2 of 2: Click center of Option <strong>${escape_html(optList[optList.length - 1])}</strong> <span class="ml-2 font-normal text-emerald-300 opacity-90">· Hold <kbd class="rounded bg-slate-800 px-1 py-0.5 font-mono text-[9px] font-bold text-white">Shift</kbd> to snap horizontal</span>`);
        }
        $$renderer2.push(`<!--]--></span>`);
      } else {
        $$renderer2.push("<!--[-1-->");
        const activeOptLabel = plottingConfig.optionLabels?.[plottingConfig.currentOptionIndex] ?? "A";
        $$renderer2.push(`<span class="flex size-5 items-center justify-center rounded-full bg-emerald-500 font-mono text-[11px] font-black text-slate-950">${escape_html(activeOptLabel)}</span> <span>Plotting <strong>${escape_html(plottingConfig.areaCode)} · Q${escape_html(plottingConfig.itemNumber)}</strong>: Click center of Option <strong>${escape_html(activeOptLabel)}</strong> (${escape_html((plottingConfig.currentOptionIndex ?? 0) + 1)} of ${escape_html(plottingConfig.optionLabels?.length ?? 4)}) `);
        if ((plottingConfig.currentOptionIndex ?? 0) > 0) {
          $$renderer2.push("<!--[0-->");
          $$renderer2.push(`<span class="ml-2 font-normal text-emerald-300 opacity-90">· Hold <kbd class="rounded bg-slate-800 px-1 py-0.5 font-mono text-[9px] font-bold text-white">Shift</kbd> to snap horizontal</span>`);
        } else {
          $$renderer2.push("<!--[-1-->");
        }
        $$renderer2.push(`<!--]--></span>`);
      }
      $$renderer2.push(`<!--]--> <button type="button" data-omr-plotting-cancel="true" class="rounded-md bg-slate-800 px-2 py-1 text-[10px] font-bold text-slate-200 hover:bg-slate-700">Done (Esc)</button></div>`);
    } else {
      $$renderer2.push("<!--[-1-->");
    }
    $$renderer2.push(`<!--]--> <div class="pointer-events-none absolute inset-0 flex items-center justify-center overflow-hidden"><div data-omr-sheet="true"${attr_class(`pointer-events-auto relative isolate shrink-0 origin-center overflow-hidden rounded-xs bg-white shadow-2xl outline outline-slate-300 transition-transform ${plottingConfig?.active || autoGridActive ? "cursor-crosshair" : ""}`, "svelte-1pe0x5v")}${attr_style(`width: ${(canonicalPreview?.artifact?.paper?.width_mm ?? 210) * 2.476}px; height: ${(canonicalPreview?.artifact?.paper?.height_mm ?? 297) * 2.476}px; transform: translate(${panX}px, ${panY}px) scale(${scale});`)}>`);
    if (activeSheetImageUrl() && (currentStage >= 3 || showCanonicalPrintPreview || !isHtmlMode)) {
      $$renderer2.push("<!--[0-->");
      $$renderer2.push(`<img${attr("src", activeSheetImageUrl())}${attr("alt", canonicalPreview?.artifact ? "Server-rendered answer sheet" : "Uploaded source sheet for recognition mapping")} class="pointer-events-none absolute inset-0 z-0 h-full w-full object-contain" onload="this.__e=event" onerror="this.__e=event"/>`);
    } else if (currentStage >= 4 && !activeSheetImageUrl() && !isHtmlMode) {
      $$renderer2.push("<!--[1-->");
      $$renderer2.push(`<div class="pointer-events-none absolute inset-0 z-[60] flex items-center justify-center p-8" role="status"><div class="max-w-[78%] rounded-xl border border-amber-300 bg-white/95 px-4 py-3 text-center text-xs leading-relaxed font-semibold text-slate-800 shadow-xl ring-1 ring-amber-200/70 backdrop-blur-sm"><span class="block">Candidate preview unavailable for Page ${escape_html(activePageNo)}.</span> <span class="mt-1 block text-[11px] font-medium text-slate-600">Return to Recognition Mapping, then continue to Verification to prepare the candidate preview.</span></div></div>`);
    } else if (safeHtmlContent() && isHtmlMode) {
      $$renderer2.push("<!--[2-->");
      $$renderer2.push(`<div class="pointer-events-none absolute inset-0 z-0 h-full w-full overflow-hidden rounded-xs bg-white"><iframe${attr("srcdoc", safeHtmlContent())} title="HTML Source Preview" class="pointer-events-none overflow-hidden border-none bg-white" style="width: 793.7px; height: 1122.5px; transform: scale(0.65517); transform-origin: top left;" scrolling="no" onload="this.__e=event"></iframe></div>`);
    } else {
      $$renderer2.push("<!--[-1-->");
    }
    $$renderer2.push(`<!--]--> `);
    if (!sheetImageLoaded && true && activeSheetImageUrl() && !isHtmlMode) {
      $$renderer2.push("<!--[0-->");
      $$renderer2.push(`<div role="status" data-omr-canvas-loading="true" class="pointer-events-none absolute inset-0 z-50 flex flex-col items-center justify-center bg-white/60 backdrop-blur-xs transition-opacity duration-150"><div${attr_style(`transform: scale(${1 / scale}); transform-origin: center;`)} class="flex items-center gap-2 rounded-xl border border-slate-200 bg-white/95 px-3.5 py-2 shadow-lg ring-1 ring-slate-900/5"><span class="text-xs font-semibold text-slate-700">Loading Page ${escape_html(activePageNo)}…</span></div></div>`);
    } else {
      $$renderer2.push("<!--[-1-->");
    }
    $$renderer2.push(`<!--]--> `);
    {
      $$renderer2.push("<!--[-1-->");
    }
    $$renderer2.push(`<!--]--> `);
    if (currentStage >= 3 && previewSurface !== "canonical") {
      $$renderer2.push("<!--[0-->");
      const insets = canonicalPreview?.artifact?.insets_mm ?? { top: 12, right: 12, bottom: 14, left: 12 };
      const activeDiagnosticUrl = previewSurface === "overlay" ? canonicalPreview?.overlay_url : canonicalPreview?.aligned_url;
      if (activeDiagnosticUrl) {
        $$renderer2.push("<!--[0-->");
        $$renderer2.push(`<img${attr("src", activeDiagnosticUrl)}${attr("alt", previewSurface === "overlay" ? "OMRChecker diagnostic overlay" : "OMRChecker aligned frame")} class="pointer-events-none absolute z-10"${attr_style(`top:${Number(insets.top) * 2.476}px;left:${Number(insets.left) * 2.476}px;width:${(210 - Number(insets.left) - Number(insets.right)) * 2.476}px;height:${(297 - Number(insets.top) - Number(insets.bottom)) * 2.476}px;`)}/>`);
      } else {
        $$renderer2.push("<!--[-1-->");
      }
      $$renderer2.push(`<!--]-->`);
    } else {
      $$renderer2.push("<!--[-1-->");
    }
    $$renderer2.push(`<!--]--> `);
    if (showFiducials && (currentStage === 2 || isHtmlMode && currentStage === 1) && !showCanonicalPrintPreview) {
      $$renderer2.push("<!--[0-->");
      $$renderer2.push(`<div${attr_class(`pointer-events-none absolute z-40 ${markerClassName(markerPattern)}`, "svelte-1pe0x5v")}${attr_style(`top: ${markerInsetsMm.top * 2.476}px; left: ${markerInsetsMm.left * 2.476}px;`)}></div> <div${attr_class(`pointer-events-none absolute z-40 ${markerClassName(markerPattern)}`, "svelte-1pe0x5v")}${attr_style(`top: ${markerInsetsMm.top * 2.476}px; right: ${markerInsetsMm.right * 2.476}px;`)}></div> <div${attr_class(`pointer-events-none absolute z-40 ${markerClassName(markerPattern)}`, "svelte-1pe0x5v")}${attr_style(`bottom: ${markerInsetsMm.bottom * 2.476}px; left: ${markerInsetsMm.left * 2.476}px;`)}></div> <div${attr_class(`pointer-events-none absolute z-40 ${markerClassName(markerPattern)}`, "svelte-1pe0x5v")}${attr_style(`bottom: ${markerInsetsMm.bottom * 2.476}px; right: ${markerInsetsMm.right * 2.476}px;`)}></div>`);
    } else {
      $$renderer2.push("<!--[-1-->");
    }
    $$renderer2.push(`<!--]--> `);
    if (isHtmlMode && currentStage === 1 && !showCanonicalPrintPreview) {
      $$renderer2.push("<!--[0-->");
      $$renderer2.push(`<div class="pointer-events-none absolute z-0 border border-dashed border-emerald-300/70"${attr_style(`top: ${marginMm * 2.476}px; left: ${marginMm * 2.476}px; right: ${marginMm * 2.476}px; bottom: ${marginMm * 2.476}px;`)}${attr("aria-label", `Printable margin inset ${marginMm} millimeters`)}></div>`);
    } else {
      $$renderer2.push("<!--[-1-->");
    }
    $$renderer2.push(`<!--]--> `);
    if (currentStage === 2 || isHtmlMode && currentStage === 1) {
      $$renderer2.push("<!--[0-->");
      if (showHeaderOverlay && currentStage < 3 && !showCanonicalPrintPreview) {
        $$renderer2.push("<!--[0-->");
        $$renderer2.push(`<div${attr_class(
          `absolute flex items-center justify-between border-b-2 border-slate-900 pb-1.5 transition-all ${!headerLocked ? "z-30 rounded bg-emerald-500/10 p-1 ring-2 ring-emerald-500" : "z-10"}`,
          "svelte-1pe0x5v"
        )}${attr_style(`top: ${headerY * 2.476}px; left: ${headerX * 2.476}px; width: ${headerW * 2.476}px; text-align: ${headerAlign};`)} role="button" tabindex="0"><div class="w-full"${attr_style(`text-align: ${headerAlign};`)}><h3 class="leading-tight font-bold tracking-tight"${attr_style(`font-size: ${headerFontSize * 0.9}px; font-family: ${headerFontFamily}; color: ${headerColor}; text-align: ${headerAlign};`)}>${escape_html(headerTitle)}</h3> `);
        if (headerSubtitle) {
          $$renderer2.push("<!--[0-->");
          $$renderer2.push(`<p class="mt-0.5 font-mono text-[9px] text-slate-500"${attr_style(`text-align: ${headerAlign};`)}>${escape_html(headerSubtitle)}</p>`);
        } else {
          $$renderer2.push("<!--[-1-->");
        }
        $$renderer2.push(`<!--]--></div> `);
        if (!headerLocked) {
          $$renderer2.push("<!--[0-->");
          $$renderer2.push(`<div class="pointer-events-auto absolute -top-7 left-0 z-40 flex items-center space-x-1.5 rounded-lg bg-slate-900/90 px-2 py-0.5 text-[10px] text-white shadow-lg" role="toolbar" tabindex="0"><button type="button" class="p-0.5 hover:text-emerald-400" title="Decrease font size">`);
          Minus($$renderer2, { class: "size-2.5" });
          $$renderer2.push(`<!----></button> <span class="font-mono text-[9.5px]">${escape_html(headerFontSize)}pt</span> <button type="button" class="p-0.5 hover:text-emerald-400" title="Increase font size">`);
          Plus($$renderer2, { class: "size-2.5" });
          $$renderer2.push(`<!----></button></div> <div class="absolute -right-1 -bottom-1 h-3.5 w-3.5 cursor-se-resize rounded-full border-2 border-white bg-emerald-600 shadow-md" role="button" tabindex="0"></div>`);
        } else {
          $$renderer2.push("<!--[-1-->");
        }
        $$renderer2.push(`<!--]--></div>`);
      } else {
        $$renderer2.push("<!--[-1-->");
      }
      $$renderer2.push(`<!--]--> `);
      if (showQrOverlay) {
        $$renderer2.push("<!--[0-->");
        $$renderer2.push(`<div${attr_class(
          `absolute overflow-hidden border bg-white transition-all ${activeItemIds.includes(QR_CANVAS_ITEM_ID) ? "z-30 border-emerald-500 shadow-md ring-2 ring-emerald-400/80" : "z-10 border-slate-300 shadow-xs"}`,
          "svelte-1pe0x5v"
        )}${attr("data-omr-canvas-item-id", QR_CANVAS_ITEM_ID)}${attr_style(`top: ${qrY * 2.476}px; left: ${qrX * 2.476}px; width: ${qrW * 2.476}px; height: ${qrH * 2.476}px;`)} role="button" tabindex="0"><svg viewBox="0 0 100 100" class="block size-full text-slate-900" xmlns="http://www.w3.org/2000/svg"><rect x="5" y="5" width="28" height="28" fill="currentColor" rx="2"></rect><rect x="11" y="11" width="16" height="16" fill="white" rx="1"></rect><rect x="15" y="15" width="8" height="8" fill="currentColor" rx="1"></rect><rect x="67" y="5" width="28" height="28" fill="currentColor" rx="2"></rect><rect x="73" y="11" width="16" height="16" fill="white" rx="1"></rect><rect x="77" y="15" width="8" height="8" fill="currentColor" rx="1"></rect><rect x="5" y="67" width="28" height="28" fill="currentColor" rx="2"></rect><rect x="11" y="73" width="16" height="16" fill="white" rx="1"></rect><rect x="15" y="77" width="8" height="8" fill="currentColor" rx="1"></rect><rect x="38" y="8" width="6" height="6" fill="currentColor"></rect><rect x="48" y="14" width="6" height="6" fill="currentColor"></rect><rect x="56" y="8" width="6" height="6" fill="currentColor"></rect><rect x="38" y="24" width="6" height="6" fill="currentColor"></rect><rect x="48" y="24" width="6" height="6" fill="currentColor"></rect><rect x="8" y="38" width="6" height="6" fill="currentColor"></rect><rect x="20" y="46" width="6" height="6" fill="currentColor"></rect><rect x="38" y="38" width="6" height="6" fill="currentColor"></rect><rect x="48" y="46" width="6" height="6" fill="currentColor"></rect><rect x="56" y="38" width="6" height="6" fill="currentColor"></rect><rect x="68" y="46" width="6" height="6" fill="currentColor"></rect><rect x="78" y="38" width="6" height="6" fill="currentColor"></rect><rect x="88" y="46" width="6" height="6" fill="currentColor"></rect><rect x="38" y="58" width="6" height="6" fill="currentColor"></rect><rect x="56" y="68" width="6" height="6" fill="currentColor"></rect><rect x="78" y="68" width="6" height="6" fill="currentColor"></rect><rect x="88" y="78" width="6" height="6" fill="currentColor"></rect><rect x="38" y="78" width="6" height="6" fill="currentColor"></rect><rect x="48" y="88" width="6" height="6" fill="currentColor"></rect><rect x="68" y="88" width="6" height="6" fill="currentColor"></rect></svg> `);
        if (activeItemIds.includes(QR_CANVAS_ITEM_ID) && !qrLocked) {
          $$renderer2.push("<!--[0-->");
          $$renderer2.push(`<div class="absolute -right-1 -bottom-1 h-3.5 w-3.5 cursor-se-resize rounded-full border-2 border-white bg-emerald-600 shadow-md" role="button" tabindex="0"></div>`);
        } else {
          $$renderer2.push("<!--[-1-->");
        }
        $$renderer2.push(`<!--]--></div>`);
      } else {
        $$renderer2.push("<!--[-1-->");
      }
      $$renderer2.push(`<!--]--> `);
      if (showInstructionsOverlay && currentStage < 3 && !showCanonicalPrintPreview) {
        $$renderer2.push("<!--[0-->");
        $$renderer2.push(`<div${attr_class(
          `absolute rounded border p-2 text-[8px] leading-tight transition-all ${"z-10 border-slate-300 bg-slate-50/90 text-slate-700"}`,
          "svelte-1pe0x5v"
        )}${attr_style(`top: ${instY * 2.476}px; left: ${instX * 2.476}px; width: ${instW * 2.476}px; font-family: ${instructionsFontFamily}; color: ${instructionsColor}; font-size: ${instructionsFontSize * 0.9}px;`)} role="button" tabindex="0"><span class="mb-0.5 block font-bold text-slate-900 uppercase">Instructions:</span> ${escape_html(instructionsContent)} `);
        {
          $$renderer2.push("<!--[-1-->");
        }
        $$renderer2.push(`<!--]--></div>`);
      } else {
        $$renderer2.push("<!--[-1-->");
      }
      $$renderer2.push(`<!--]--> `);
      if (showSignatureOverlay && currentStage < 3 && !showCanonicalPrintPreview) {
        $$renderer2.push("<!--[0-->");
        $$renderer2.push(`<div${attr_class(
          `absolute flex flex-col justify-between rounded border-2 border-slate-400 bg-white p-2 transition-all ${"z-10"}`,
          "svelte-1pe0x5v"
        )}${attr_style(`top: ${sigY * 2.476}px; left: ${sigX * 2.476}px; width: ${sigW * 2.476}px; height: ${sigH * 2.476}px; font-family: ${signatureFontFamily};`)} role="button" tabindex="0"><span class="font-bold tracking-wider uppercase"${attr_style(`font-size: ${signatureFontSize * 0.9}px; color: ${signatureColor}; font-family: ${signatureFontFamily};`)}>${escape_html(signatureContent)}</span> <div class="mb-1 w-full border-b border-dashed border-slate-400"></div> `);
        {
          $$renderer2.push("<!--[-1-->");
        }
        $$renderer2.push(`<!--]--></div>`);
      } else {
        $$renderer2.push("<!--[-1-->");
      }
      $$renderer2.push(`<!--]--> `);
      if (currentStage < 3 && !showCanonicalPrintPreview) {
        $$renderer2.push("<!--[0-->");
        $$renderer2.push(`<!--[-->`);
        const each_array = ensure_array_like(customTextItems);
        for (let idx = 0, $$length = each_array.length; idx < $$length; idx++) {
          let t = each_array[idx];
          $$renderer2.push(`<div${attr_class(
            `absolute cursor-move rounded p-0.5 leading-tight font-medium transition-all ${selectedOverlay === idx && !t.isLocked ? "z-30 bg-emerald-50/50 ring-2 ring-emerald-500" : "z-10"}`,
            "svelte-1pe0x5v"
          )}${attr_style(`top: ${t.posY * 2.476}px; left: ${t.posX * 2.476}px; width: ${t.w_mm * 2.476}px; font-size: ${(t.fontSize ?? 10) * 0.9}px; font-family: ${t.fontFamily || "Inter, sans-serif"}; color: ${t.color || "#0f172a"}; text-align: ${t.align || "left"}; font-weight: ${t.bold ? "bold" : "normal"}; font-style: ${t.italic ? "italic" : "normal"};`)} role="button" tabindex="0">${escape_html(t.content)}</div>`);
        }
        $$renderer2.push(`<!--]-->`);
      } else {
        $$renderer2.push("<!--[-1-->");
      }
      $$renderer2.push(`<!--]-->`);
    } else {
      $$renderer2.push("<!--[-1-->");
    }
    $$renderer2.push(`<!--]--> `);
    if (isHtmlMode && currentStage < 3 && !showCanonicalPrintPreview) {
      $$renderer2.push("<!--[0-->");
      $$renderer2.push(`<!--[-->`);
      const each_array_1 = ensure_array_like(pageComponents());
      for (let compIndex = 0, $$length = each_array_1.length; compIndex < $$length; compIndex++) {
        let comp = each_array_1[compIndex];
        const isSelected = (activeComponentIds ?? []).includes(comp.id) || activeComponentId === comp.id;
        const isPrimarySelected = activeComponentId === comp.id;
        const isRoll = comp.type === "roll";
        const isGrid = isQuestionGridComponent(comp);
        const isText = !isRoll && !isGrid;
        const isInputField = comp.type === "candidate_name" || comp.type === "birthdate" || comp.type === "room_seat" || comp.type === "custom_input";
        const fieldInputClass = comp.inputKind === "multiline" ? "border border-slate-700 rounded-xs" : comp.inputStyle === "underline" ? "border-b border-slate-700" : comp.inputStyle === "dotted_line" ? "border-b border-dashed border-slate-400" : "border border-slate-700 rounded-xs";
        const { w_mm: bw, h_mm: bh } = canvasComponentBlockSizeMm(comp);
        const digitsCount = isRoll ? Number(comp.digitsCount) || 7 : 0;
        const gridLabels = isGrid ? gridOptionLabels(comp) : [];
        isGrid ? (Number(comp.startQ) || 1) + (Number(comp.questionsCount) || 1) - 1 : 0;
        const recognitionGeometry = effectiveRecognitionGeometryMm({
          kind: isRoll ? "roll" : "grid",
          bubbleDiameterMm: comp.bubbleDiameterMm,
          bubblePitchMm: comp.bubblePitchMm,
          rowPitchMm: comp.rowPitchMm
        });
        const bubblePitchMm = recognitionGeometry.bubblePitchMm;
        const bubbleDiameterPx = isRoll || isGrid ? recognitionGeometry.bubbleDiameterMm * 2.476 : CANVAS_BUBBLE_DIAMETER_PX;
        const rowPitchMm = recognitionGeometry.rowPitchMm;
        const rowGapPx = isRoll || isGrid ? canvasRollRowGapPx(rowPitchMm, bubbleDiameterPx) : 0;
        const bubbleGapPx = isRoll || isGrid ? canvasRollBubbleGapPx(bubblePitchMm, bubbleDiameterPx) : 0;
        const labelFontSizePx = isGrid ? Math.max(5, (Number(comp.labelFontSizePt) || 6.5) * 0.9) : null;
        const labelWidthPx = 3.5 * 2.476;
        const labelGapPx = 1.5 * 2.476;
        const bubbleStyle = `width: ${bubbleDiameterPx}px; height: ${bubbleDiameterPx}px;`;
        $$renderer2.push(`<div${attr_class(
          `absolute overflow-visible rounded-sm transition-all select-none ${isDragging() && isDuplicateDragging ? "z-40 scale-[1.005] bg-white/95 shadow-2xl ring-2 ring-sky-500" : isDragging() ? "z-40 scale-[1.005] bg-white/95 shadow-2xl ring-2 ring-emerald-500" : isSelected ? "z-30 bg-white shadow-sm ring-2 ring-emerald-500" : "z-10 bg-white hover:ring-1 hover:ring-slate-400/80"} ${comp.isLocked ? "cursor-default" : isDragging() ? "cursor-grabbing" : "cursor-grab"}`,
          "svelte-1pe0x5v"
        )}${attr("data-omr-component-id", comp.id)}${attr("data-omr-component-type", comp.type)}${attr_style(`top: ${comp.posY * 2.476}px; left: ${comp.posX * 2.476}px; width: ${bw * 2.476}px; height: ${bh * 2.476}px; z-index: ${isDragging() ? 40 : isSelected ? 30 : Number(comp.zIndex) || compIndex + 1}; background-color: ${componentBackgroundColor(comp)};`)} role="button" tabindex="0">`);
        if (isRoll) {
          $$renderer2.push("<!--[0-->");
          $$renderer2.push(`<div class="flex h-full w-full flex-col justify-start p-1"><div class="mb-1 text-center font-black tracking-wider text-slate-900"${attr_style(`font-size: ${(comp.typography?.fontSizePt ?? 7.5) * 0.9}px; font-family: ${comp.typography?.fontFamily ?? "Inter, sans-serif"}; color: ${comp.typography?.textColor ?? "#0f172a"}; text-align: ${comp.typography?.textAlign ?? "center"};`)}>${escape_html(comp.title || "EXAMINEE NUMBER")}</div> <div class="mb-1 flex justify-start"${attr_style(`gap: ${bubbleGapPx}px; padding-left: ${labelWidthPx + labelGapPx}px;`)}><!--[-->`);
          const each_array_2 = ensure_array_like(Array.from({ length: digitsCount }));
          for (let $$index_1 = 0, $$length2 = each_array_2.length; $$index_1 < $$length2; $$index_1++) {
            each_array_2[$$index_1];
            $$renderer2.push(`<div class="border border-slate-900 bg-white"${attr_style(`width: ${bubbleDiameterPx}px; height: ${bubbleDiameterPx * 1.15}px;`)} data-omr-write-in-box="true"></div>`);
          }
          $$renderer2.push(`<!--]--></div> <div class="flex flex-col"${attr_style(`gap: ${rowGapPx}px;`)} data-omr-designer-rows="true"><!--[-->`);
          const each_array_3 = ensure_array_like(Array.from({ length: 10 }));
          for (let digit = 0, $$length2 = each_array_3.length; digit < $$length2; digit++) {
            each_array_3[digit];
            $$renderer2.push(`<div class="flex items-center justify-start"${attr_style(`gap: ${labelGapPx}px; height: ${bubbleDiameterPx}px;`)} data-omr-designer-row="true"><span class="text-right font-mono font-bold"${attr_style(`width: ${labelWidthPx}px; flex: 0 0 ${labelWidthPx}px; font-size: ${(comp.typography?.fontSizePt ?? 6.5) * 0.8}px; font-family: ${comp.typography?.fontFamily ?? "monospace"}; color: ${comp.typography?.textColor ?? "#334155"};`)}${attr("data-omr-row-label", digit)}>${escape_html(digit)}</span> <div class="flex items-center"${attr_style(`gap: ${bubbleGapPx}px;`)}><!--[-->`);
            const each_array_4 = ensure_array_like(Array.from({ length: digitsCount }));
            for (let $$index_2 = 0, $$length3 = each_array_4.length; $$index_2 < $$length3; $$index_2++) {
              each_array_4[$$index_2];
              $$renderer2.push(`<div class="shrink-0 rounded-full border border-slate-800 bg-white"${attr_style(bubbleStyle)} data-omr-designer-bubble="true"></div>`);
            }
            $$renderer2.push(`<!--]--></div></div>`);
          }
          $$renderer2.push(`<!--]--></div></div>`);
        } else if (isGrid) {
          $$renderer2.push("<!--[1-->");
          $$renderer2.push(`<div class="pointer-events-none flex h-full w-full flex-col justify-start bg-white"${attr_style(`background-color: ${componentBackgroundColor(comp)};`)}><div class="truncate rounded-xs bg-slate-900 px-1 text-center font-black tracking-wide text-white uppercase"${attr_style(`height: ${GRID_SECTION_HEADER_HEIGHT_MM * 2.476}px; line-height: ${GRID_SECTION_HEADER_HEIGHT_MM * 2.476}px; font-size: ${Math.max(4.5, (Number(comp.labelFontSizePt) || 6.5) * 0.75)}px; margin-bottom: ${0.5 * 2.476}px;`)}>${escape_html(comp.subjectName || comp.name || "Subject")}</div> <div class="mb-0.5 flex items-center justify-start font-mono text-[6.5px] font-bold text-slate-900"><span aria-hidden="true"${attr_style(`width: ${labelWidthPx}px; flex: 0 0 ${labelWidthPx}px;`)}></span> <div class="flex items-center"${attr_style(`gap: ${bubbleGapPx}px;`)}><!--[-->`);
          const each_array_5 = ensure_array_like(gridLabels);
          for (let $$index_4 = 0, $$length2 = each_array_5.length; $$index_4 < $$length2; $$index_4++) {
            let opt = each_array_5[$$index_4];
            $$renderer2.push(`<span class="text-center"${attr_style(`font-size: ${labelFontSizePx}px; ${bubbleStyle}`)}>${escape_html(opt)}</span>`);
          }
          $$renderer2.push(`<!--]--></div></div> <div class="flex flex-col"${attr_style(`gap: ${rowGapPx}px;`)}><!--[-->`);
          const each_array_6 = ensure_array_like(Array.from({ length: Math.min(Number(comp.questionsCount) || 1, 50) }));
          for (let r = 0, $$length2 = each_array_6.length; r < $$length2; r++) {
            each_array_6[r];
            $$renderer2.push(`<div class="flex items-center justify-start"${attr_style(`gap: ${labelGapPx}px; height: ${bubbleDiameterPx}px;`)}><span class="shrink-0 text-right font-mono font-bold text-slate-600"${attr_style(`font-size: ${labelFontSizePx}px; width: ${labelWidthPx}px; flex: 0 0 ${labelWidthPx}px;`)}>${escape_html((Number(comp.startQ) || 1) + r)}</span> <div class="flex items-center"${attr_style(`gap: ${bubbleGapPx}px;`)}><!--[-->`);
            const each_array_7 = ensure_array_like(gridLabels);
            for (let $$index_5 = 0, $$length3 = each_array_7.length; $$index_5 < $$length3; $$index_5++) {
              each_array_7[$$index_5];
              $$renderer2.push(`<div class="flex shrink-0 items-center justify-center rounded-full border border-slate-800 bg-white text-[5.5px] font-bold text-slate-900 shadow-2xs"${attr_style(bubbleStyle)}></div>`);
            }
            $$renderer2.push(`<!--]--></div></div>`);
          }
          $$renderer2.push(`<!--]--></div></div>`);
        } else if (comp.type === "header") {
          $$renderer2.push("<!--[2-->");
          $$renderer2.push(`<div class="pointer-events-none flex h-full w-full flex-col justify-between border-b-2 border-slate-900 p-1.5"><div class="flex items-end justify-between gap-2"><div class="min-w-0"><h1 class="truncate leading-tight font-black"${attr_style(`font-size: ${(comp.typography?.fontSizePt ?? 13) * 0.85}px; font-family: ${comp.typography?.fontFamily ?? "Inter, sans-serif"}; font-weight: ${comp.typography?.fontWeight ?? 900}; color: ${comp.typography?.textColor ?? "#0f172a"}; text-align: ${comp.typography?.textAlign ?? "left"};`)}>${escape_html(comp.title)}</h1> `);
          if (comp.subtitle) {
            $$renderer2.push("<!--[0-->");
            $$renderer2.push(`<p class="mt-0.5 truncate font-semibold tracking-wider text-slate-500"${attr_style(`font-size: ${Math.max(5, (comp.typography?.fontSizePt ?? 13) * 0.55)}px; font-family: ${comp.typography?.fontFamily ?? "Inter, sans-serif"}; color: ${comp.typography?.textColor ?? "#64748b"}; text-align: ${comp.typography?.textAlign ?? "left"};`)}>${escape_html(comp.subtitle)}</p>`);
          } else {
            $$renderer2.push("<!--[-1-->");
          }
          $$renderer2.push(`<!--]--></div> <span class="shrink-0 rounded-xs border border-slate-900 px-1.5 py-0.5 font-mono text-[8px] font-extrabold text-slate-900">${escape_html(comp.formCode)}</span></div></div>`);
        } else if (isInputField) {
          $$renderer2.push("<!--[3-->");
          $$renderer2.push(`<div class="pointer-events-none flex h-full w-full flex-col gap-1 rounded-xs border border-slate-700/80 bg-slate-50/80 p-1.5"${attr_style(`background-color: ${componentBackgroundColor(comp)};`)}><span class="shrink-0 truncate font-extrabold tracking-tight"${attr_style(`font-size: ${(comp.typography?.fontSizePt ?? 6.5) * 0.9}px; font-family: ${comp.typography?.fontFamily ?? "Inter, sans-serif"}; color: ${comp.typography?.textColor ?? "#334155"};`)}>${escape_html(comp.label)}</span> <div${attr_class(`min-h-1 w-full flex-1 ${fieldInputClass}`, "svelte-1pe0x5v")}${attr_style(`background-color: ${componentInputBackgroundColor(comp)};`)}></div></div>`);
        } else if (comp.type === "instructions") {
          $$renderer2.push("<!--[4-->");
          $$renderer2.push(`<div class="pointer-events-none flex h-full w-full flex-col rounded-xs border p-2 leading-tight"${attr_style(`border-color: ${comp.borderColor || "#cbd5e1"}; background-color: ${comp.backgroundColor || "#ffffff"}; font-size: ${(comp.typography?.fontSizePt ?? 6.5) * 0.9}px; font-family: ${comp.typography?.fontFamily ?? "Inter, sans-serif"}; color: ${comp.typography?.textColor ?? "#334155"};`)}><span class="mb-0.5 block text-[7px] font-bold tracking-wide text-slate-900">${escape_html(comp.label || "INSTRUCTIONS")}:</span> <span class="line-clamp-3 leading-snug">${escape_html(comp.content)}</span></div>`);
        } else if (comp.type === "custom_text") {
          $$renderer2.push("<!--[5-->");
          $$renderer2.push(`<div class="pointer-events-none flex h-full w-full items-center p-1"${attr_style(`font-size: ${(comp.typography?.fontSizePt ?? 9) * 0.9}px; font-family: ${comp.typography?.fontFamily ?? "Inter, sans-serif"}; font-weight: ${comp.typography?.fontWeight ?? 600}; color: ${comp.typography?.textColor ?? "#0f172a"}; text-align: ${comp.typography?.textAlign ?? "left"};`)}><span class="truncate">${escape_html(comp.text)}</span></div>`);
        } else if (comp.type === "signature") {
          $$renderer2.push("<!--[6-->");
          $$renderer2.push(`<div class="pointer-events-none flex h-full w-full flex-col justify-between rounded-xs border border-slate-400 bg-white p-1.5"${attr_style(`background-color: ${componentBackgroundColor(comp)};`)}><span class="truncate font-bold tracking-wide"${attr_style(`font-size: ${(comp.typography?.fontSizePt ?? 7.5) * 0.9}px; font-family: ${comp.typography?.fontFamily ?? "Inter, sans-serif"}; color: ${comp.typography?.textColor ?? "#1e293b"};`)}>${escape_html(comp.title)}</span> <div class="my-0.5 w-full border-b border-slate-500"></div> <span class="truncate text-[6.5px] leading-tight text-slate-500">${escape_html(comp.certificationText)}</span></div>`);
        } else {
          $$renderer2.push("<!--[-1-->");
        }
        $$renderer2.push(`<!--]--> `);
        if (isPrimarySelected) {
          $$renderer2.push("<!--[0-->");
          $$renderer2.push(`<div class="pointer-events-none absolute -top-3.5 right-1 z-40 flex items-center gap-1 rounded bg-slate-900 px-1.5 py-px font-mono text-[7px] font-bold text-emerald-400 shadow-sm"><span>${escape_html(componentLabel(comp))}</span> `);
          if (comp.isLocked) {
            $$renderer2.push("<!--[0-->");
            Lock($$renderer2, { class: "size-2 text-amber-400" });
          } else {
            $$renderer2.push("<!--[-1-->");
          }
          $$renderer2.push(`<!--]--></div>`);
        } else {
          $$renderer2.push("<!--[-1-->");
        }
        $$renderer2.push(`<!--]--> `);
        if (comp.isLocked && !isSelected) {
          $$renderer2.push("<!--[0-->");
          $$renderer2.push(`<div class="pointer-events-none absolute -top-1 -left-1 z-20 flex size-3.5 items-center justify-center rounded-full border border-amber-300 bg-amber-100 text-amber-700">`);
          Lock($$renderer2, { class: "size-2" });
          $$renderer2.push(`<!----></div>`);
        } else {
          $$renderer2.push("<!--[-1-->");
        }
        $$renderer2.push(`<!--]--> `);
        if (isText && isPrimarySelected && !comp.isLocked) {
          $$renderer2.push("<!--[0-->");
          $$renderer2.push(`<div class="pointer-events-auto absolute -right-1.5 -bottom-1.5 z-40 size-3.5 cursor-se-resize rounded-full border-2 border-white bg-emerald-600 shadow-md" role="button" tabindex="0" title="Drag to resize width and height"></div>`);
        } else {
          $$renderer2.push("<!--[-1-->");
        }
        $$renderer2.push(`<!--]--></div>`);
      }
      $$renderer2.push(`<!--]-->`);
    } else {
      $$renderer2.push("<!--[-1-->");
    }
    $$renderer2.push(`<!--]--> `);
    if (currentStage >= 3 && previewSurface !== "overlay") {
      $$renderer2.push("<!--[0-->");
      $$renderer2.push(`<!--[-->`);
      const each_array_8 = ensure_array_like(pageSections());
      for (let i = 0, $$length = each_array_8.length; i < $$length; i++) {
        let sec = each_array_8[i];
        const globalIndex = sections.findIndex((s) => s.id === sec.id);
        const resolvedIdx = globalIndex >= 0 ? globalIndex : i;
        const isSelected = selectedSectionIndices.includes(resolvedIdx) || activeSectionIndex === resolvedIdx;
        const isSinglePlottedItem = Array.isArray(sec.optionPointsMm) && sec.optionPointsMm.length > 0 && (Number(sec.questionsCount) || 1) === 1;
        const visualBounds = recognitionGuideSizeMm(sec);
        const originOffset = recognitionOriginOffsetMm(sec);
        const bubbleGuides = localRecognitionBubbles(sec);
        const minBubbleX = isSinglePlottedItem && bubbleGuides.length > 0 ? Math.min(...bubbleGuides.map((b) => Number(b.x_mm))) : Number(sec.posX) + originOffset.x || 16;
        const minBubbleY = isSinglePlottedItem && bubbleGuides.length > 0 ? Math.min(...bubbleGuides.map((b) => Number(b.y_mm))) : Number(sec.posY) + originOffset.y || 56;
        const maxBubbleX = isSinglePlottedItem && bubbleGuides.length > 0 ? Math.max(...bubbleGuides.map((b) => Number(b.x_mm) + Number(b.w_mm))) : minBubbleX + visualBounds.w_mm;
        const maxBubbleY = isSinglePlottedItem && bubbleGuides.length > 0 ? Math.max(...bubbleGuides.map((b) => Number(b.y_mm) + Number(b.h_mm))) : minBubbleY + visualBounds.h_mm;
        const blockX = minBubbleX;
        const blockY = minBubbleY;
        const blockWidthMm = Math.max(4, maxBubbleX - minBubbleX);
        const blockHeightMm = Math.max(4, maxBubbleY - minBubbleY);
        $$renderer2.push(`<div${attr_class(
          `absolute overflow-visible rounded-lg outline outline-1 transition-all ${sec.isLocked ? "cursor-default" : isSinglePlottedItem ? "cursor-pointer" : "cursor-grab active:cursor-grabbing"} ${isSelected ? "z-30 bg-emerald-500/15 shadow-sm ring-2 ring-emerald-400/60 outline-emerald-600 backdrop-blur-[0.5px] outline-dashed" : "z-20 bg-emerald-500/5 outline-emerald-500/80 backdrop-blur-[0.5px] outline-dashed hover:outline-emerald-600"}`,
          "svelte-1pe0x5v"
        )}${attr_style(`top: ${blockY * 2.476}px; left: ${blockX * 2.476}px; width: ${blockWidthMm * 2.476}px; height: ${blockHeightMm * 2.476}px;`)}${attr("data-omr-section-id", sec.id)} role="button" tabindex="0">`);
        if ((Number(sec.questionsCount) || 1) === 1 && sec.type !== "roll") {
          $$renderer2.push("<!--[0-->");
          $$renderer2.push(`<div${attr_class(
            `pointer-events-none absolute top-1/2 -left-1 -translate-x-full -translate-y-1/2 rounded px-1 py-0.5 font-mono text-[8px] font-bold whitespace-nowrap shadow-xs ${isSelected ? "bg-emerald-700 text-white" : "bg-slate-900/85 text-white"}`,
            "svelte-1pe0x5v"
          )} data-omr-solid-item-badge="true">${escape_html(sec.area_code ? `${sec.area_code}·` : "")}Q${escape_html(sec.startQ ?? 1)}</div>`);
        } else {
          $$renderer2.push("<!--[-1-->");
        }
        $$renderer2.push(`<!--]--> <div class="pointer-events-none absolute inset-0"><!--[-->`);
        const each_array_9 = ensure_array_like(bubbleGuides);
        for (let $$index_8 = 0, $$length2 = each_array_9.length; $$index_8 < $$length2; $$index_8++) {
          let bubble = each_array_9[$$index_8];
          const layout = bubbleLabelLayout(bubble.label, Number(bubble.w_mm));
          $$renderer2.push(`<div class="absolute flex items-center justify-center rounded-full border border-emerald-600/90 bg-emerald-500/5 font-mono font-bold text-emerald-800/90 shadow-[0_0_0_1px_rgba(255,255,255,0.7)]" data-omr-recognition-bubble="true"${attr_style(`left: ${(Number(bubble.x_mm) - blockX) * 2.476}px; top: ${(Number(bubble.y_mm) - blockY) * 2.476}px; width: ${Number(bubble.w_mm) * 2.476}px; height: ${Number(bubble.h_mm) * 2.476}px; font-size: ${layout.fontSizePx}px;`)}>`);
          if (bubble.label && ((Number(sec.questionsCount) || 1) === 1 || sec.type === "roll")) {
            $$renderer2.push("<!--[0-->");
            if (layout.showInside) {
              $$renderer2.push("<!--[0-->");
              $$renderer2.push(`<span class="truncate leading-none">${escape_html(bubble.label)}</span>`);
            } else {
              $$renderer2.push("<!--[-1-->");
              $$renderer2.push(`<span class="pointer-events-none absolute -top-3.5 left-1/2 -translate-x-1/2 rounded border border-emerald-600/40 bg-white/95 px-1 py-0.2 font-mono text-[7px] font-bold text-emerald-900 shadow-xs whitespace-nowrap" data-omr-floating-label="true">${escape_html(bubble.label)}</span>`);
            }
            $$renderer2.push(`<!--]-->`);
          } else {
            $$renderer2.push("<!--[-1-->");
          }
          $$renderer2.push(`<!--]--></div>`);
        }
        $$renderer2.push(`<!--]--></div> `);
        if (isSelected && !sec.isLocked) {
          $$renderer2.push("<!--[0-->");
          $$renderer2.push(`<div class="pointer-events-auto absolute -top-6 left-0 z-40 flex items-center gap-1 select-none" role="toolbar" aria-label="Selected item actions" tabindex="-1">`);
          if ((Number(sec.questionsCount) || 1) > 1 && sec.type !== "roll") {
            $$renderer2.push("<!--[0-->");
            $$renderer2.push(`<button type="button" data-omr-auto-grid-trigger="true" title="Click the first bubble (top-left) and last bubble (bottom-right) to auto-solve origin and spacing"${attr_class(
              `flex items-center gap-1 rounded-full px-2 py-0.5 text-[9px] font-bold whitespace-nowrap shadow-md ring-2 ring-white transition ${"bg-slate-900 text-white hover:bg-emerald-700"}`,
              "svelte-1pe0x5v"
            )}><span>⌖</span> <span>${escape_html("2-Click Align")}</span></button>`);
          } else {
            $$renderer2.push("<!--[-1-->");
          }
          $$renderer2.push(`<!--]--> <button type="button" data-omr-delete-section="true" title="Delete this item (Del / Backspace)" class="flex items-center gap-0.5 rounded-full bg-rose-600 px-1.5 py-0.5 text-[9px] font-bold text-white shadow-md ring-2 ring-white transition hover:bg-rose-500"><span>✕</span></button></div> `);
          if (!isSinglePlottedItem) {
            $$renderer2.push("<!--[0-->");
            $$renderer2.push(`<div class="pointer-events-auto absolute -right-3.5 top-1/2 -translate-y-1/2 z-40 flex items-center justify-center cursor-ew-resize select-none" title="Drag horizontally to adjust option distance (bubble pitch)" data-omr-pitch-handle="bubblePitch" role="button" tabindex="0"><div class="flex items-center gap-1 rounded-full bg-emerald-700 hover:bg-emerald-600 px-2 py-0.5 text-[9px] font-bold text-white shadow-md ring-2 ring-white transition"><span>↔</span> <span class="font-mono">${escape_html(Number(sec.bubblePitchMm).toFixed(1))}mm</span></div></div> <div class="pointer-events-auto absolute -bottom-4 left-1/2 -translate-x-1/2 z-40 flex items-center justify-center cursor-ns-resize select-none" title="Drag vertically to adjust question row distance (row pitch)" data-omr-pitch-handle="rowPitch" role="button" tabindex="0"><div class="flex items-center gap-1 rounded-full bg-emerald-700 hover:bg-emerald-600 px-2 py-0.5 text-[9px] font-bold text-white shadow-md ring-2 ring-white transition"><span>↕</span> <span class="font-mono">${escape_html(Number(sec.rowPitchMm).toFixed(1))}mm</span></div></div>`);
          } else {
            $$renderer2.push("<!--[-1-->");
          }
          $$renderer2.push(`<!--]-->`);
        } else {
          $$renderer2.push("<!--[-1-->");
        }
        $$renderer2.push(`<!--]--></div>`);
      }
      $$renderer2.push(`<!--]--> `);
      if (currentStage >= 3 && previewSurface !== "overlay" && hasWriteIn() && activeWriteInBox()) {
        $$renderer2.push("<!--[0-->");
        const box = activeWriteInBox();
        $$renderer2.push(`<div${attr_class(
          `absolute z-30 flex flex-col justify-between rounded border-2 border-dashed transition-all cursor-move select-none ${"border-amber-600/80 bg-amber-500/10 hover:border-amber-500 hover:bg-amber-500/15"}`,
          "svelte-1pe0x5v"
        )}${attr_style(`top: ${box.y_mm * 2.476}px; left: ${box.x_mm * 2.476}px; width: ${box.w_mm * 2.476}px; height: ${box.h_mm * 2.476}px;`)} role="button" tabindex="0" title="Applicant Number (Handwritten Box) - Drag to align over handwritten digits" data-omr-write-in-box="true"><div class="pointer-events-none absolute -top-5 left-0 flex items-center gap-1 rounded bg-amber-600 px-1.5 py-0.5 text-[9px] font-bold text-white shadow-xs whitespace-nowrap"><span>Applicant Number (Handwritten Box)</span> <span class="font-mono text-[8px] opacity-80">${escape_html(Number(box.w_mm).toFixed(0))}×${escape_html(Number(box.h_mm).toFixed(0))}mm</span></div> `);
        {
          $$renderer2.push("<!--[-1-->");
        }
        $$renderer2.push(`<!--]--></div>`);
      } else {
        $$renderer2.push("<!--[-1-->");
      }
      $$renderer2.push(`<!--]--> `);
      if (currentStage >= 3 && previewSurface !== "overlay") {
        $$renderer2.push("<!--[0-->");
        $$renderer2.push(`<!--[-->`);
        const each_array_10 = ensure_array_like(activeDocumentFields());
        for (let $$index_11 = 0, $$length = each_array_10.length; $$index_11 < $$length; $$index_11++) {
          let field = each_array_10[$$index_11];
          const isActive = activeDocumentFieldId === field.id;
          $$renderer2.push(`<div${attr_class(
            `absolute z-30 cursor-move select-none rounded border-2 border-dashed ${isActive ? "border-violet-600 bg-violet-600/20 ring-2 ring-violet-400 shadow-md" : "border-violet-500 bg-violet-500/10"}`,
            "svelte-1pe0x5v"
          )}${attr_style(`top: ${Number(field.y_mm) * 2.476}px; left: ${Number(field.x_mm) * 2.476}px; width: ${Number(field.w_mm) * 2.476}px; height: ${Number(field.h_mm) * 2.476}px;`)} role="button" tabindex="0"${attr("aria-label", `Document field ${field.label || field.type}`)}${attr("aria-pressed", isActive)}${attr("data-omr-document-field", field.id)}><div class="pointer-events-none absolute -top-5 left-0 flex items-center gap-1 whitespace-nowrap rounded bg-violet-600 px-1.5 py-0.5 text-[9px] font-bold text-white shadow-xs"><span>${escape_html(field.label)}</span> <span class="font-mono text-[8px] opacity-90">${escape_html(Math.round(Number(field.w_mm) || 0))}×${escape_html(Math.round(Number(field.h_mm) || 0))}mm</span></div> `);
          if (isActive) {
            $$renderer2.push("<!--[0-->");
            $$renderer2.push(`<!--[-->`);
            const each_array_11 = ensure_array_like([
              {
                handle: "nw",
                position: "-left-1.5 -top-1.5 cursor-nwse-resize"
              },
              {
                handle: "ne",
                position: "-right-1.5 -top-1.5 cursor-nesw-resize"
              },
              {
                handle: "se",
                position: "-right-1.5 -bottom-1.5 cursor-nwse-resize"
              },
              {
                handle: "sw",
                position: "-left-1.5 -bottom-1.5 cursor-nesw-resize"
              }
            ]);
            for (let $$index_10 = 0, $$length2 = each_array_11.length; $$index_10 < $$length2; $$index_10++) {
              let corner = each_array_11[$$index_10];
              $$renderer2.push(`<div${attr_class(`absolute z-10 size-3.5 rounded-full border-2 border-white bg-violet-600 shadow-md ${corner.position}`, "svelte-1pe0x5v")} role="button" tabindex="0"${attr("aria-label", `Resize ${field.label || field.type} ${corner.handle} corner`)}${attr("data-omr-document-field-handle", corner.handle)}></div>`);
            }
            $$renderer2.push(`<!--]-->`);
          } else {
            $$renderer2.push("<!--[-1-->");
          }
          $$renderer2.push(`<!--]--></div>`);
        }
        $$renderer2.push(`<!--]-->`);
      } else {
        $$renderer2.push("<!--[-1-->");
      }
      $$renderer2.push(`<!--]--> `);
      if (plottingConfig?.active && plottingConfig.mode === "consecutive" && Array.isArray(plottingConfig.pointsMm)) {
        $$renderer2.push("<!--[0-->");
        const dPx = (Number(plottingConfig.bubbleDiameterMm) || 4.5) * 2.476;
        $$renderer2.push(`<!--[-->`);
        const each_array_12 = ensure_array_like(plottingConfig.pointsMm);
        for (let ptIdx = 0, $$length = each_array_12.length; ptIdx < $$length; ptIdx++) {
          let pt = each_array_12[ptIdx];
          if (pt && Number.isFinite(pt.x) && Number.isFinite(pt.y)) {
            $$renderer2.push("<!--[0-->");
            $$renderer2.push(`<div${attr("data-omr-plotting-point", ptIdx)} class="pointer-events-none absolute z-40 flex -translate-x-1/2 -translate-y-1/2 items-center justify-center rounded-full border-2 border-dashed border-amber-500 bg-amber-400/5 font-mono text-[8px] font-black text-amber-950 shadow-sm"${attr_style(`left: ${pt.x * 2.476}px; top: ${pt.y * 2.476}px; width: ${dPx}px; height: ${dPx}px;`)}><span>${escape_html(pt.label ?? plottingConfig.optionLabels?.[ptIdx] ?? "")}</span></div>`);
          } else {
            $$renderer2.push("<!--[-1-->");
          }
          $$renderer2.push(`<!--]-->`);
        }
        $$renderer2.push(`<!--]-->`);
      } else {
        $$renderer2.push("<!--[-1-->");
      }
      $$renderer2.push(`<!--]--> `);
      if (plottingConfig?.active && cursorPaperMm) ;
      else {
        $$renderer2.push("<!--[-1-->");
      }
      $$renderer2.push(`<!--]--> `);
      if (plottingConfig?.multiplyPreview?.active) {
        $$renderer2.push("<!--[0-->");
        const prevSource = plottingConfig.multiplyPreview.sourceSection ?? sections[activeSectionIndex];
        if (prevSource && prevSource.type !== "roll") {
          $$renderer2.push("<!--[0-->");
          const prevCount = Math.max(1, Math.min(100, Math.round(Number(plottingConfig.multiplyPreview.count) || 1)));
          const prevPitch = Number(plottingConfig.multiplyPreview.pitchMm) || 6.8;
          const previewItems = multiplyItemsDownward(prevSource, {
            count: prevCount,
            distancePerItemMm: prevPitch,
            areaCode: prevSource.area_code || plottingConfig.areaCode,
            pageNo: prevSource.page_no,
            direction: plottingConfig.multiplyPreview.direction,
            slantAngleDeg: Number(plottingConfig.multiplyPreview.slantAngleDeg) || 0
          });
          $$renderer2.push(`<div data-omr-multiply-preview-container="true" class="pointer-events-none absolute inset-0 z-40 select-none"><!--[-->`);
          const each_array_14 = ensure_array_like(previewItems);
          for (let $$index_15 = 0, $$length = each_array_14.length; $$index_15 < $$length; $$index_15++) {
            let pItem = each_array_14[$$index_15];
            const itemDPx = (Number(pItem.bubbleDiameterMm) || 4.5) * 2.476;
            $$renderer2.push(`<div data-omr-multiply-preview-badge="true" class="absolute -translate-y-1/2 flex items-center gap-1 rounded bg-emerald-700/80 px-1 py-0.5 font-mono text-[8px] font-bold text-white shadow-xs backdrop-blur-xs"${attr_style(`left: ${(Number(pItem.posX) - 2) * 2.476}px; top: ${(Number(pItem.posY) + 2) * 2.476}px;`)}><span>${escape_html(pItem.name)}</span></div> <!--[-->`);
            const each_array_15 = ensure_array_like(pItem.optionPointsMm);
            for (let optIdx = 0, $$length2 = each_array_15.length; optIdx < $$length2; optIdx++) {
              let optPt = each_array_15[optIdx];
              $$renderer2.push(`<div data-omr-multiply-preview-bubble="true" class="absolute flex -translate-x-1/2 -translate-y-1/2 items-center justify-center rounded-full border-2 border-dashed border-emerald-500 bg-emerald-500/5 font-mono text-[8px] font-black text-emerald-950 shadow-sm"${attr_style(`left: ${optPt.x_mm * 2.476}px; top: ${optPt.y_mm * 2.476}px; width: ${itemDPx}px; height: ${itemDPx}px;`)}><span>${escape_html(pItem.customValuesStr?.split(",")?.[optIdx]?.trim() ?? "")}</span></div>`);
            }
            $$renderer2.push(`<!--]-->`);
          }
          $$renderer2.push(`<!--]--></div>`);
        } else {
          $$renderer2.push("<!--[-1-->");
        }
        $$renderer2.push(`<!--]-->`);
      } else {
        $$renderer2.push("<!--[-1-->");
      }
      $$renderer2.push(`<!--]--> `);
      if (plottingConfig?.rollPreview?.active) {
        $$renderer2.push("<!--[0-->");
        const rollSec = sections.find((s) => s.identity_grid && Number(s.page_no) === activePageNo);
        if (rollSec) {
          $$renderer2.push("<!--[0-->");
          const prevDigits = Math.max(2, Math.min(10, Number(plottingConfig.rollPreview.digitCount) || 6));
          const isH = plottingConfig.rollPreview.direction === "horizontal" || rollSec.rollDirection === "horizontal";
          const prevCols = isH ? 10 : prevDigits;
          const prevRows = isH ? prevDigits : 10;
          const bubbleDPx = (Number(rollSec.bubbleDiameterMm) || 4.5) * 2.476;
          const xP = mmToPx(Number(plottingConfig.rollPreview.colPitchMm || rollSec.bubblePitchMm || 5.5));
          const yP = mmToPx(Number(plottingConfig.rollPreview.rowPitchMm || rollSec.rowPitchMm || 4.8));
          const oXPx = mmToPx(Number(rollSec.posX) + (isH ? 0 : 2.25));
          const oYPx = mmToPx(Number(rollSec.posY) + (isH ? 2.25 : 0));
          $$renderer2.push(`<div data-omr-roll-preview-container="true" class="pointer-events-none absolute inset-0 z-40 select-none"><!--[-->`);
          const each_array_16 = ensure_array_like(Array.from({ length: prevRows }));
          for (let r = 0, $$length = each_array_16.length; r < $$length; r++) {
            each_array_16[r];
            $$renderer2.push(`<!--[-->`);
            const each_array_17 = ensure_array_like(Array.from({ length: prevCols }));
            for (let c = 0, $$length2 = each_array_17.length; c < $$length2; c++) {
              each_array_17[c];
              $$renderer2.push(`<div data-omr-roll-preview-bubble="true" class="absolute flex -translate-x-1/2 -translate-y-1/2 items-center justify-center rounded-full border-2 border-dashed border-indigo-500 bg-indigo-400/25 font-mono text-[8px] font-black text-indigo-950 shadow-sm"${attr_style(`left: ${oXPx + c * xP}px; top: ${oYPx + r * yP}px; width: ${bubbleDPx}px; height: ${bubbleDPx}px;`)}><span>${escape_html(isH ? String(c) : String(r))}</span></div>`);
            }
            $$renderer2.push(`<!--]-->`);
          }
          $$renderer2.push(`<!--]--></div>`);
        } else {
          $$renderer2.push("<!--[-1-->");
        }
        $$renderer2.push(`<!--]-->`);
      } else {
        $$renderer2.push("<!--[-1-->");
      }
      $$renderer2.push(`<!--]--> `);
      if (plottingConfig?.active && plottingConfig.targetType === "applicant_digit" && plottingConfig.digitSpanMode && plottingConfig.pointsMm?.length === 1 && cursorPaperMm) ;
      else {
        $$renderer2.push("<!--[-1-->");
      }
      $$renderer2.push(`<!--]--> `);
      if (plottingConfig?.active && !plottingConfig.targetType && plottingConfig.questionSpanMode && plottingConfig.pointsMm?.length === 1 && cursorPaperMm) ;
      else {
        $$renderer2.push("<!--[-1-->");
      }
      $$renderer2.push(`<!--]--> `);
      {
        $$renderer2.push("<!--[-1-->");
      }
      $$renderer2.push(`<!--]-->`);
    } else {
      $$renderer2.push("<!--[-1-->");
    }
    $$renderer2.push(`<!--]--> `);
    if (plottingConfig?.sizingPreviewDiameterMm && Number(plottingConfig.sizingPreviewDiameterMm) > 0) {
      $$renderer2.push("<!--[0-->");
      const previewDiameterPx = Number(plottingConfig.sizingPreviewDiameterMm) * 2.476;
      const previewRadiusMm = (Number(plottingConfig.sizingPreviewDiameterMm) / 2).toFixed(2);
      $$renderer2.push(`<div data-omr-sizing-preview-gauge="true" class="pointer-events-none absolute left-1/2 top-1/2 -translate-x-1/2 -translate-y-1/2 z-[70] flex flex-col items-center justify-center"><div class="absolute h-px w-20 bg-emerald-500/60"></div> <div class="absolute w-px h-20 bg-emerald-500/60"></div> <div class="rounded-full border-2 border-emerald-500 bg-emerald-500/5 shadow-2xl ring-4 ring-emerald-400/25 backdrop-blur-xs transition-[width,height] duration-75"${attr_style(`width: ${previewDiameterPx}px; height: ${previewDiameterPx}px;`)}></div> <div${attr_style(`transform: scale(${1 / scale}); transform-origin: top center;`)} class="mt-3 flex items-center gap-1.5 rounded-full border border-emerald-400/80 bg-slate-950/90 px-2.5 py-1 text-[10px] font-mono font-bold text-white shadow-xl backdrop-blur-sm whitespace-nowrap"><span class="text-slate-500">·</span> <span class="text-slate-300">R ${escape_html(previewRadiusMm)} mm</span></div></div>`);
    } else {
      $$renderer2.push("<!--[-1-->");
    }
    $$renderer2.push(`<!--]--> `);
    if (isComposing) {
      $$renderer2.push("<!--[0-->");
      $$renderer2.push(`<div class="absolute inset-0 z-40 flex flex-col items-center justify-center rounded-xs bg-white/90 p-6 text-center backdrop-blur-xs"><div class="mb-3 flex size-10 animate-pulse items-center justify-center rounded-xl bg-slate-900 text-white shadow-md">`);
      Loader_circle($$renderer2, { class: "size-5 animate-spin" });
      $$renderer2.push(`<!----></div> <h4 class="text-xs font-bold text-slate-900">Preparing print sheet</h4> <p class="mt-1 max-w-[240px] text-[10px] text-slate-500">Generating vector fiducial markers and normalizing
                            print canvas layout…</p></div>`);
    } else {
      $$renderer2.push("<!--[-1-->");
    }
    $$renderer2.push(`<!--]--></div></div></div></div>`);
    bind_props($$props, {
      scale,
      panX,
      panY,
      headerFontSize,
      headerFontFamily,
      headerColor,
      headerAlign,
      headerX,
      headerY,
      headerW,
      headerH,
      qrX,
      qrY,
      qrW,
      qrH,
      instructionsFontSize,
      instructionsFontFamily,
      instructionsColor,
      instX,
      instY,
      instW,
      instH,
      signatureFontSize,
      signatureFontFamily,
      signatureColor,
      sigX,
      sigY,
      sigW,
      sigH,
      customTextItems,
      sections,
      activeSectionIndex,
      selectedSectionIndices,
      components,
      activeComponentId,
      activeComponentIds,
      activeItemIds,
      plottingConfig,
      identity,
      documentFields,
      activeDocumentFieldId,
      sheetImageLoaded
    });
  });
}
export {
  StudioCanvas as default
};
