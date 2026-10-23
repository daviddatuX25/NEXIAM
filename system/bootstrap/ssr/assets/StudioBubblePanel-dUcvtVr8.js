import { e as escape_html, b as attr, a as ensure_array_like, c as attr_class, l as bind_props, d as derived } from "../app.js";
import StudioTip from "./StudioTip-evy-6JcG.js";
import { n as nextItemNumberForArea, m as minimumRecognitionPitchMm, s as syncDeclaredInventoryFromSections, M as MIN_UPLOADED_BUBBLE_MM, j as MIN_BUBBLE_MM, k as assessUploadedMappingReadiness, l as detectAdjacentVerticalStepMm, o as normalizeOmrAreaCode } from "./omrStudioGeometry-DyCkNaaw.js";
import { C as Chevron_up } from "./chevron-up-lhASJLRD.js";
import { C as Circle_x } from "./circle-x-BQrhluGu.js";
import { P as Plus } from "./plus-DjyPLC7A.js";
import { T as Trash_2 } from "./trash-2-CjFp3otc.js";
import { S as Scan } from "./scan-DTyWR4bt.js";
import { L as Lock } from "./lock-DAA4kTLl.js";
import { L as Lock_open } from "./lock-open-5Nny5RV8.js";
import { C as Chevron_left } from "./chevron-left-DCSOZwZl.js";
import { C as Chevron_right } from "./chevron-right-BHu-PGOg.js";
import "axios";
import "@inertiajs/core";
import "clsx";
import "laravel-precognition";
import "tailwind-merge";
import "tailwind-variants";
import "./info-C3MCzlQW.js";
import "./Icon-Dal7PqKK.js";
function StudioBubblePanel($$renderer, $$props) {
  $$renderer.component(($$renderer2) => {
    let {
      sections = [],
      activeSectionIndex = 0,
      selectedSectionIndices = [],
      isUploadedSource = false,
      pageCount = 1,
      activePageNo = 1,
      documentFields = [],
      activeDocumentFieldId = null,
      onDocumentFieldsChanged = null,
      declaredInventory = [],
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
      previewSurface = "canonical",
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
      canUndo = false,
      canRedo = false,
      onUndo = null,
      onRedo = null,
      onChanged = null,
      onBack = null,
      onNext = null,
      readOnly = false
    } = $$props;
    const subtests = derived(() => testInstrument?.subtests ?? []);
    const OPTION_PRESETS = [
      { id: "mcq4", label: "A–D (4)", tokens: ["A", "B", "C", "D"] },
      {
        id: "mcq5",
        label: "A–E (5)",
        tokens: ["A", "B", "C", "D", "E"]
      },
      {
        id: "pair6",
        label: "Paired (6)",
        tokens: ["A-B", "A-C", "A-D", "B-C", "B-D", "C-D"]
      },
      { id: "tf", label: "T/F (2)", tokens: ["TRUE", "FALSE"] },
      {
        id: "likert5",
        label: "1–5 (5)",
        tokens: ["1", "2", "3", "4", "5"]
      }
    ];
    let customOptionTokens = "";
    let showCustomOptionTokens = false;
    let selectedPresetId = derived(() => {
      const tokens = plottingConfig.optionLabels ?? ["A", "B", "C", "D"];
      const joined = tokens.join(",");
      if (joined === "A,B,C,D") return "mcq4";
      if (joined === "A,B,C,D,E") return "mcq5";
      if (joined === "A-B,A-C,A-D,B-C,B-D,C-D") return "pair6";
      if (joined === "TRUE,FALSE") return "tf";
      if (joined === "1,2,3,4,5") return "likert5";
      return "custom";
    });
    function handlePresetSelect(presetId) {
      if (presetId === "custom") {
        showCustomOptionTokens = true;
        customOptionTokens = (plottingConfig.optionLabels ?? []).join(", ");
      } else {
        const found = OPTION_PRESETS.find((p) => p.id === presetId);
        if (found) {
          showCustomOptionTokens = false;
          applyOptionTokens(found.tokens);
        }
      }
    }
    function applyOptionTokens(tokens) {
      const labels = (Array.isArray(tokens) ? tokens : []).map((token) => String(token).trim()).filter(Boolean).slice(0, 10);
      if (labels.length < 2) return;
      plottingConfig.optionLabels = labels;
      plottingConfig.optionsCount = labels.length;
      plottingConfig.currentOptionIndex = 0;
      customOptionTokens = labels.join(", ");
      if (activeSection() && activeSection().type !== "roll") {
        activeSection().optionLabels = labels;
        activeSection().optionsCount = labels.length;
        activeSection().customValuesStr = labels.join(", ");
        sections = [...sections];
        if (isUploadedSource) {
          declaredInventory = syncDeclaredInventoryFromSections(sections, declaredInventory);
        }
        onChanged?.();
      }
    }
    let minBubbleDiameter = derived(() => isUploadedSource ? MIN_UPLOADED_BUBBLE_MM : MIN_BUBBLE_MM);
    let activeDocumentFields = derived(() => documentFields.filter((field) => Number(field.page_no ?? 1) === Number(activePageNo)));
    let selectedProfile = derived(() => markerProfiles.find((profile) => Number(profile.id) === Number(selectedMarkerProfileId)) ?? null);
    let activeSection = derived(() => sections[activeSectionIndex]);
    new Set(sections.map((section) => section.id));
    let uploadMappingReadiness = derived(() => assessUploadedMappingReadiness({
      pageCount: Number(pageCount) || 1,
      inventory: declaredInventory,
      identity,
      sections
    }));
    let multiplyRowCount = 9;
    let multiplyRowPitchMm = 6.8;
    let multiplyPreviewEnabled = false;
    let questionSpanMode = true;
    let bulkTargetArea = "GENERAL";
    let digitSpanMode = true;
    let digitMultiplyCount = 6;
    let digitMultiplyPitchMm = 5.5;
    let digitPreviewEnabled = false;
    let measuredVerticalStep = derived(() => {
      if (!activeSection() || activeSection().type === "roll") return null;
      const area = activeSection().area_code || plottingConfig.areaCode;
      return detectAdjacentVerticalStepMm(sections, area, Number(activeSection().page_no) || 1);
    });
    let closedAptitudeGroups = /* @__PURE__ */ new Set();
    const questionSections = derived(() => sections.map((sec, idx) => ({ ...sec, originalIndex: idx })).filter((sec) => sec.type !== "roll" && Number(sec.page_no || 1) === Number(activePageNo)));
    const sectionsByAptitude = derived(() => {
      const map = /* @__PURE__ */ new Map();
      for (const sec of questionSections()) {
        const code = normalizeOmrAreaCode(sec.area_code || sec.aptitudeAreaCode || "GENERAL");
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
          totalQuestions: items.reduce((acc, it) => acc + (Number(it.questionsCount) || 1), 0)
        };
      });
    });
    function subtestCodeFor(section) {
      const subtest = subtests().find((candidate) => candidate.code === section?.subtest_code || Number(candidate.id) === Number(section?.test_instrument_subtest_id));
      return subtest?.code ?? "";
    }
    function handleSubtestChange(rawSubtestCode) {
      const code = rawSubtestCode?.target?.value ?? rawSubtestCode?.currentTarget?.value ?? rawSubtestCode;
      const subtest = subtests().find((candidate) => candidate.code === code);
      if (!subtest) {
        return;
      }
      plottingConfig.subtest_code = subtest.code;
      plottingConfig.test_instrument_subtest_id = Number(subtest.id);
      if (sections[activeSectionIndex] && sections[activeSectionIndex].type !== "roll") {
        sections = sections.map((section, index) => index === activeSectionIndex ? {
          ...section,
          subtest_code: subtest.code,
          test_instrument_subtest_id: Number(subtest.id)
        } : section);
        if (isUploadedSource) {
          declaredInventory = syncDeclaredInventoryFromSections(sections, declaredInventory);
        }
        if (onChanged) {
          onChanged();
        }
      }
    }
    function handleAreaChange(rawArea) {
      const val = rawArea?.target?.value ?? rawArea?.currentTarget?.value ?? rawArea;
      const nextArea = normalizeOmrAreaCode(val);
      plottingConfig.areaCode = nextArea;
      if (sections[activeSectionIndex] && sections[activeSectionIndex].type !== "roll") {
        const sec = sections[activeSectionIndex];
        sec.area_code = nextArea;
        sec.aptitudeAreaCode = nextArea;
        sec.name = `${nextArea} · Q${sec.startQ ?? 1}`;
        sections = [...sections];
        if (isUploadedSource) {
          declaredInventory = syncDeclaredInventoryFromSections(sections, declaredInventory);
        }
        if (onChanged) onChanged();
      } else {
        plottingConfig.itemNumber = nextItemNumberForArea(sections, nextArea);
      }
    }
    function handleInput() {
      if (onChanged) onChanged("mapping");
    }
    const hasBubbles = derived(() => Boolean(identity?.has_bubbles || identity?.strategy === "bubbles_and_write_in" || identity?.strategy === "bubbles_only" || identity?.method === "shaded_grid" || sections.some((s) => s.identity_grid) || !identity?.has_write_in && identity?.method !== "write_in_manual"));
    const hasWriteIn = derived(() => Boolean(identity?.has_write_in || identity?.strategy === "bubbles_and_write_in" || identity?.strategy === "write_in_only" || identity?.method === "write_in_manual" || Array.isArray(identity?.write_in_boxes) && identity.write_in_boxes.length > 0 || Array.isArray(identity?.regions) && identity.regions.length > 0));
    function errorText(value) {
      if (typeof value === "string" && value.trim()) return value.trim();
      if (Array.isArray(value)) return value.map(errorText).find(Boolean) ?? "";
      if (value && typeof value === "object") {
        for (const key of ["detail", "message", "error", "reason"]) {
          const text = errorText(value[key]);
          if (text) return text;
        }
        return typeof value.code === "string" && value.code.trim() ? value.code.trim() : "";
      }
      return "";
    }
    $$renderer2.push(`<div class="text-foreground [&amp;_input]:border-input [&amp;_input]:bg-background [&amp;_input]:text-foreground [&amp;_select]:border-input [&amp;_select]:bg-background [&amp;_select]:text-foreground space-y-3"><div class="space-y-1"><div class="flex items-center justify-between"><h2 class="text-foreground text-sm font-bold tracking-tight">${escape_html(isUploadedSource ? "Uploaded-source Recognition Mapping" : "Recognition Mapping")}</h2> <span class="bg-muted/80 text-muted-foreground rounded-full px-2 py-0.5 text-[10px] font-medium">${escape_html(sections.length)} block${escape_html(sections.length === 1 ? "" : "s")}</span></div> <p class="text-muted-foreground text-[11px] leading-snug">${escape_html(isUploadedSource ? "Declare question areas and position bubbles over your sheet." : "Position recognition guides over the fixed printable sheet.")}</p></div> <section${attr("inert", readOnly, true)} class="border-border bg-card space-y-3 rounded-xl border p-3 shadow-xs" aria-labelledby="question-bubbles-heading" data-omr-item-plotter-card="true"><div class="border-border/70 flex items-center justify-between gap-1.5 border-b pb-2"><div class="flex items-center gap-2 min-w-0"><span class="flex size-5 items-center justify-center rounded-md bg-emerald-600/15 font-mono text-[10px] font-black text-emerald-700 dark:text-emerald-300 shrink-0">1</span> <div class="min-w-0"><span id="question-bubbles-heading" data-omr-question-bubbles-heading="true" class="text-foreground text-xs font-bold truncate block">Question Answer Bubbles</span> <span class="text-[9.5px] text-muted-foreground block truncate">Scored choices &amp; option guides</span></div> `);
    StudioTip($$renderer2, {
      text: "Plot question recognition bubbles (e.g. A, B, C, D) over your answer sheet."
    });
    $$renderer2.push(`<!----> <span data-omr-plotter-info-icon="true" class="sr-only">info</span> `);
    if (plottingConfig.active && !plottingConfig.targetType) {
      $$renderer2.push("<!--[0-->");
      $$renderer2.push(`<span class="rounded-full bg-emerald-500/15 px-2 py-0.5 font-mono text-[9px] font-bold text-emerald-700 dark:text-emerald-300 animate-pulse shrink-0 ml-1">Plotting</span>`);
    } else {
      $$renderer2.push("<!--[-1-->");
    }
    $$renderer2.push(`<!--]--></div> <div class="flex items-center gap-1 shrink-0">`);
    if (activeSection() && activeSection().type !== "roll") {
      $$renderer2.push("<!--[0-->");
      $$renderer2.push(`<button type="button"${attr("disabled", readOnly, true)} data-omr-panel-copy-paste="true" title="Copy selected item and click on canvas to paste next item (Ctrl+C / Ctrl+V)" class="rounded-md bg-emerald-700 px-2 py-1 text-[10px] font-semibold text-white transition hover:bg-emerald-600">⧉ Stamp Q${escape_html(nextItemNumberForArea(sections, plottingConfig.areaCode || activeSection().area_code))}</button> <button type="button" data-omr-panel-delete="true"${attr("disabled", readOnly || activeSection().isLocked, true)} title="Delete selected item (Del)" class="rounded-md bg-rose-600/15 px-2 py-1 text-[10px] font-semibold text-rose-700 transition hover:bg-rose-600/25 disabled:opacity-35 dark:text-rose-300">✕ Del</button>`);
    } else {
      $$renderer2.push("<!--[-1-->");
    }
    $$renderer2.push(`<!--]--> <button type="button" data-omr-question-bubbles-collapse="true"${attr("aria-expanded", true)} class="text-muted-foreground hover:text-foreground rounded p-1 transition"${attr("title", "Collapse Question Answer Bubbles")}>`);
    {
      $$renderer2.push("<!--[-1-->");
      Chevron_up($$renderer2, { class: "size-4" });
    }
    $$renderer2.push(`<!--]--></button></div></div> `);
    {
      $$renderer2.push("<!--[0-->");
      $$renderer2.push(`<div class="grid grid-cols-2 gap-2"><label class="space-y-1"><div class="flex items-center gap-1"><span class="text-muted-foreground block text-[10px] font-semibold">${escape_html(subtests().length > 0 ? "Test Section / Subtest" : "Aptitude Area")}</span> `);
      StudioTip($$renderer2, {
        text: subtests().length > 0 ? "Instrument-defined test section for plotted question items." : "Classification area for question items (e.g. LOGIC, VERBAL)."
      });
      $$renderer2.push(`<!----></div> `);
      if (subtests().length > 0) {
        $$renderer2.push("<!--[0-->");
        $$renderer2.select(
          {
            "data-omr-plotter-subtest-select": "true",
            value: subtestCodeFor(activeSection()) || plottingConfig.subtest_code || subtests()[0]?.code || "",
            onchange: handleSubtestChange,
            class: "w-full rounded-md border px-2 py-1.5 text-xs font-semibold"
          },
          ($$renderer3) => {
            $$renderer3.push(`<!--[-->`);
            const each_array = ensure_array_like(subtests());
            for (let $$index = 0, $$length = each_array.length; $$index < $$length; $$index++) {
              let subtest = each_array[$$index];
              $$renderer3.option({ value: subtest.code }, ($$renderer4) => {
                $$renderer4.push(`${escape_html(subtest.name)} (${escape_html(subtest.code)})`);
              });
            }
            $$renderer3.push(`<!--]-->`);
          }
        );
      } else if (aptitudeAreas.length > 0) {
        $$renderer2.push("<!--[1-->");
        $$renderer2.select(
          {
            "data-omr-plotter-area-select": "true",
            value: activeSection() && activeSection().type !== "roll" ? activeSection().area_code || activeSection().aptitudeAreaCode || plottingConfig.areaCode : plottingConfig.areaCode,
            onchange: (e) => handleAreaChange(e),
            class: "w-full rounded-md border px-2 py-1.5 text-xs font-semibold"
          },
          ($$renderer3) => {
            $$renderer3.push(`<!--[-->`);
            const each_array_1 = ensure_array_like(aptitudeAreas);
            for (let $$index_1 = 0, $$length = each_array_1.length; $$index_1 < $$length; $$index_1++) {
              let area = each_array_1[$$index_1];
              $$renderer3.option({ value: area.code }, ($$renderer4) => {
                $$renderer4.push(`${escape_html(area.name)} (${escape_html(area.code)})`);
              });
            }
            $$renderer3.push(`<!--]-->`);
          }
        );
      } else {
        $$renderer2.push("<!--[-1-->");
        $$renderer2.push(`<input type="text" data-omr-plotter-area-input="true"${attr("value", activeSection() && activeSection().type !== "roll" ? activeSection().area_code || activeSection().aptitudeAreaCode || plottingConfig.areaCode : plottingConfig.areaCode)} placeholder="GENERAL" class="w-full rounded-md border px-2 py-1.5 text-xs font-semibold uppercase"/>`);
      }
      $$renderer2.push(`<!--]--></label> <label class="space-y-1"><div class="flex items-center gap-1"><span class="text-muted-foreground block text-[10px] font-semibold">Item # (Auto +1)</span> `);
      StudioTip($$renderer2, {
        text: "Question number. Auto-increments as questions are plotted or pasted."
      });
      $$renderer2.push(`<!----></div> <input type="number" min="1" max="500" data-omr-plotter-item-number="true"${attr("value", activeSection() && activeSection().type !== "roll" ? activeSection().startQ ?? plottingConfig.itemNumber : plottingConfig.itemNumber)} class="w-full rounded-md border px-2 py-1.5 font-mono text-xs font-bold"/></label></div> <div class="space-y-2 rounded-lg border border-border/70 bg-muted/20 p-2.5"><div class="flex items-center justify-between gap-2"><div class="flex items-center gap-1.5"><span class="text-muted-foreground text-[10.5px] font-semibold">Options</span> `);
      StudioTip($$renderer2, { text: "Number of choices per question (2 to 10 choices)." });
      $$renderer2.push(`<!----> <div class="flex items-center gap-0.5 ml-1"><button type="button"${attr("disabled", (plottingConfig.optionsCount ?? 4) <= 2, true)} class="flex size-6 items-center justify-center rounded border border-border bg-muted/40 font-mono text-xs font-bold hover:bg-muted disabled:opacity-30">-</button> <input type="number" min="2" max="10" data-omr-plotter-options-count-input="true"${attr("value", plottingConfig.optionsCount ?? 4)} class="h-6 w-10 rounded border border-border bg-background text-center font-mono text-xs font-bold"/> <button type="button"${attr("disabled", (plottingConfig.optionsCount ?? 4) >= 10, true)} class="flex size-6 items-center justify-center rounded border border-border bg-muted/40 font-mono text-xs font-bold hover:bg-muted disabled:opacity-30">+</button></div></div> <div class="flex items-center gap-1.5 select-none"><span class="text-[9.5px] font-semibold text-muted-foreground">2-Click Span</span> `);
      StudioTip($$renderer2, {
        text: "Click Option A then the last option to quickly space all intermediate bubbles."
      });
      $$renderer2.push(`<!----> <button type="button" role="switch"${attr("aria-checked", questionSpanMode)} aria-label="Toggle 2-Click Span shortcut" data-omr-question-span-switch="true"${attr_class(`relative inline-flex h-4 w-7 shrink-0 cursor-pointer rounded-full border-2 border-transparent transition-colors duration-200 ease-in-out focus:outline-none focus-visible:ring-2 focus-visible:ring-emerald-500 ${"bg-emerald-600"}`)}><span aria-hidden="true"${attr_class(`pointer-events-none inline-block size-3 transform rounded-full bg-white shadow-sm ring-0 transition duration-200 ease-in-out ${"translate-x-3"}`)}></span></button></div></div> <div class="space-y-1.5 pt-1.5 border-t border-border/50"><div class="flex items-center justify-between gap-1 text-[10px]"><span class="text-muted-foreground font-semibold">Option Format Preset</span> `);
      if (selectedPresetId() === "custom" || showCustomOptionTokens) {
        $$renderer2.push("<!--[0-->");
        $$renderer2.push(`<button type="button" class="text-emerald-700 dark:text-emerald-400 font-semibold underline underline-offset-2 hover:opacity-80 text-[10px]">${escape_html(showCustomOptionTokens ? "Hide Custom Input" : "Edit Tokens")}</button>`);
      } else {
        $$renderer2.push("<!--[-1-->");
      }
      $$renderer2.push(`<!--]--></div> `);
      $$renderer2.select(
        {
          "data-omr-option-preset-select": "true",
          class: "w-full rounded-md border border-border bg-background px-2 py-1.5 text-xs font-semibold",
          value: selectedPresetId(),
          onchange: (e) => handlePresetSelect(e.currentTarget.value)
        },
        ($$renderer3) => {
          $$renderer3.push(`<!--[-->`);
          const each_array_2 = ensure_array_like(OPTION_PRESETS);
          for (let $$index_2 = 0, $$length = each_array_2.length; $$index_2 < $$length; $$index_2++) {
            let preset = each_array_2[$$index_2];
            $$renderer3.option({ value: preset.id }, ($$renderer4) => {
              $$renderer4.push(`${escape_html(preset.label)} — ${escape_html(preset.tokens.join(", "))}`);
            });
          }
          $$renderer3.push(`<!--]-->`);
          $$renderer3.option({ value: "custom" }, ($$renderer4) => {
            $$renderer4.push(`Custom tokens (User-defined)...`);
          });
        }
      );
      $$renderer2.push(` `);
      if (showCustomOptionTokens) {
        $$renderer2.push("<!--[0-->");
        $$renderer2.push(`<div class="flex gap-1.5 pt-1"><input type="text" aria-label="Custom option tokens" data-omr-custom-option-tokens="true"${attr("value", customOptionTokens)} placeholder="e.g. A-B, A-C, A-D, B-C, B-D, C-D" class="min-w-0 flex-1 rounded-md border border-border bg-background px-2 py-1 text-xs font-mono"/> <button type="button" class="rounded-md bg-emerald-700 text-white px-2.5 py-1 text-xs font-semibold shadow-xs hover:bg-emerald-600 transition">Apply</button></div>`);
      } else {
        $$renderer2.push("<!--[-1-->");
      }
      $$renderer2.push(`<!--]--></div></div> <div class="flex items-center gap-1.5"><!--[-->`);
      const each_array_3 = ensure_array_like(plottingConfig.optionLabels ?? ["A", "B", "C", "D"]);
      for (let optIdx = 0, $$length = each_array_3.length; optIdx < $$length; optIdx++) {
        let optLabel = each_array_3[optIdx];
        const isPointPlaced = Boolean(plottingConfig.pointsMm?.[optIdx]);
        const isCurrentLetter = plottingConfig.active && plottingConfig.mode === "consecutive" && plottingConfig.currentOptionIndex === optIdx;
        $$renderer2.push(`<button type="button"${attr("data-omr-plotter-letter", optLabel)}${attr("title", plottingConfig.active ? `Click on canvas to place Option ${optLabel}` : `Click to re-spot Option ${optLabel} on canvas`)}${attr_class(`flex flex-1 items-center justify-center gap-1 rounded-lg border py-1.5 font-mono text-xs font-black transition ${isCurrentLetter ? "border-emerald-500 bg-emerald-600 text-white shadow-sm ring-2 ring-emerald-400/50" : isPointPlaced ? "border-emerald-500/50 bg-emerald-500/15 text-emerald-800 dark:text-emerald-200" : "border-border bg-muted/30 text-foreground hover:bg-muted"}`)}><span>${escape_html(optLabel)}</span> `);
        if (isPointPlaced) {
          $$renderer2.push("<!--[0-->");
          $$renderer2.push(`<span class="text-[9px]">✓</span>`);
        } else {
          $$renderer2.push("<!--[-1-->");
        }
        $$renderer2.push(`<!--]--></button>`);
      }
      $$renderer2.push(`<!--]--></div> <div class="space-y-1"><div class="flex items-center justify-between text-[10px]"><div class="flex items-center gap-1"><span class="text-muted-foreground font-semibold">Circle Size (Radius ${escape_html((Number(plottingConfig.bubbleDiameterMm || 4.5) / 2).toFixed(2))} mm):</span> `);
      StudioTip($$renderer2, {
        text: "Diameter of optical bubble targets. Standard OMR is 4.5 mm Ø."
      });
      $$renderer2.push(`<!----></div> <span class="font-mono font-bold text-emerald-700 dark:text-emerald-300" data-omr-plotter-diameter-readout="true">${escape_html(Number(plottingConfig.bubbleDiameterMm || 4.5).toFixed(1))} mm Ø</span></div> <input type="range"${attr("min", minBubbleDiameter())} max="8.0" step="0.1" data-omr-plotter-radius-slider="true"${attr("value", plottingConfig.bubbleDiameterMm ?? 4.5)} class="accent-primary bg-muted h-1.5 w-full cursor-pointer rounded-lg"/></div> <button type="button" data-omr-start-plot="true"${attr("disabled", readOnly, true)}${attr_class(`flex w-full items-center justify-center gap-2 rounded-xl px-3 py-2 text-xs font-bold shadow-xs transition ${plottingConfig.active && !plottingConfig.targetType ? "bg-amber-600 text-white hover:bg-amber-500" : "bg-emerald-700 text-white hover:bg-emerald-600"}`)}>`);
      if (plottingConfig.active && !plottingConfig.targetType) {
        $$renderer2.push("<!--[0-->");
        Circle_x($$renderer2, { class: "size-3.5" });
        $$renderer2.push(`<!----> <span>Cancel Plotting (${escape_html((plottingConfig.optionLabels ?? ["A"])[plottingConfig.currentOptionIndex ?? 0])} · Esc)</span>`);
      } else {
        $$renderer2.push("<!--[-1-->");
        Plus($$renderer2, { class: "size-3.5" });
        $$renderer2.push(`<!----> <span>Plot Question ${escape_html(subtests().length > 0 ? subtestCodeFor(activeSection()) || plottingConfig.subtest_code || subtests()[0]?.code || "SUBTEST" : plottingConfig.areaCode || "GENERAL")} · Q${escape_html(plottingConfig.itemNumber || 1)}</span>`);
      }
      $$renderer2.push(`<!--]--></button>`);
    }
    $$renderer2.push(`<!--]--> `);
    if (activeSection() && activeSection().type !== "roll" && (Number(activeSection().questionsCount) || 1) === 1) {
      $$renderer2.push("<!--[0-->");
      $$renderer2.push(`<div class="rounded-xl border border-emerald-500/30 bg-emerald-500/5 p-2.5 space-y-2 dark:border-emerald-500/20 dark:bg-emerald-950/20" data-omr-multiply-downward-card="true"><div class="flex items-center justify-between text-[11px]"><span class="font-bold text-foreground flex items-center gap-1"><span>↓</span> Multiply Rows</span> <div class="flex items-center gap-1 bg-muted/60 p-0.5 rounded-lg border border-border/70 text-[9.5px]"><button type="button" data-omr-q-mult-dir="vertical"${attr_class(`px-2 py-0.5 rounded font-bold transition flex items-center gap-1 ${"bg-emerald-700 text-white shadow-xs"}`)}>`);
      {
        $$renderer2.push("<!--[0-->");
        $$renderer2.push(`<span>✓</span>`);
      }
      $$renderer2.push(`<!--]--> <span>Vertical (Down)</span></button> <button type="button" data-omr-q-mult-dir="horizontal"${attr_class(`px-2 py-0.5 rounded font-bold transition flex items-center gap-1 ${"text-muted-foreground hover:text-foreground"}`)}>`);
      {
        $$renderer2.push("<!--[-1-->");
      }
      $$renderer2.push(`<!--]--> <span>Horizontal (Across)</span></button></div></div> <div class="flex items-center justify-between text-[10px]">`);
      if (measuredVerticalStep() != null) {
        $$renderer2.push("<!--[0-->");
        $$renderer2.push(`<span class="font-mono text-[9px] text-emerald-600 dark:text-emerald-400 font-semibold">Step: ${escape_html(measuredVerticalStep().toFixed(2))}mm</span>`);
      } else {
        $$renderer2.push("<!--[-1-->");
      }
      $$renderer2.push(`<!--]--> <div class="flex items-center gap-1.5 select-none"><span class="text-[9.5px] font-semibold text-muted-foreground">Preview</span> <button type="button" role="switch"${attr("aria-checked", multiplyPreviewEnabled)} data-omr-multiply-preview-toggle="true"${attr_class(`relative inline-flex h-4 w-7 shrink-0 cursor-pointer rounded-full border-2 border-transparent transition-colors duration-200 ease-in-out focus:outline-none ${"bg-muted-foreground/30"}`)}><span aria-hidden="true"${attr_class(`pointer-events-none inline-block size-3 transform rounded-full bg-white shadow-sm ring-0 transition duration-200 ease-in-out ${"translate-x-0"}`)}></span></button></div></div> <div class="grid grid-cols-2 gap-2 text-[10px]"><label class="space-y-0.5"><span class="text-muted-foreground block font-semibold">Rows to add</span> <input type="number" min="1" max="100"${attr("value", multiplyRowCount)} data-omr-multiply-count="true" class="w-full rounded-md border bg-background px-2 py-1 font-mono text-xs font-bold"/></label> <label class="space-y-0.5"><span class="text-muted-foreground block font-semibold">Row pitch (mm)</span> <input type="number" min="2.0" max="50.0" step="0.05"${attr("value", multiplyRowPitchMm)} data-omr-multiply-pitch="true" class="w-full rounded-md border bg-background px-2 py-1 font-mono text-xs font-bold"/></label></div> <div class="border-t border-border/60 pt-1.5 space-y-1.5"><button type="button" data-omr-slant-toggle="true" class="flex items-center gap-1 text-[9.5px] font-semibold text-muted-foreground hover:text-foreground"><span>${escape_html("▸")}</span> <span>Advanced: Paper Incline / Slant Angle</span> `);
      {
        $$renderer2.push("<!--[-1-->");
      }
      $$renderer2.push(`<!--]--></button> `);
      {
        $$renderer2.push("<!--[-1-->");
      }
      $$renderer2.push(`<!--]--></div> <button type="button" data-omr-multiply-execute="true" class="flex w-full items-center justify-center gap-1.5 rounded-lg bg-emerald-700 px-2.5 py-1.5 text-xs font-bold text-white shadow-xs transition hover:bg-emerald-600"><span>↓ Generate ${escape_html(multiplyRowCount)} Items Downward (+${escape_html((Number(multiplyRowCount) * Number(multiplyRowPitchMm)).toFixed(1))}mm)</span></button></div>`);
    } else {
      $$renderer2.push("<!--[-1-->");
    }
    $$renderer2.push(`<!--]--> `);
    if (sections.some((s) => s.type !== "roll")) {
      $$renderer2.push("<!--[0-->");
      $$renderer2.push(`<div class="border-border/70 space-y-2 border-t pt-2.5" data-omr-solid-items-list="true"><div class="flex items-center justify-between text-[10px]"><span class="text-muted-foreground font-bold uppercase tracking-wider">Plotted Items (${escape_html(sections.filter((s) => s.type !== "roll").length)})</span> <span class="text-muted-foreground">Click to select (Shift for range)</span></div> `);
      if (selectedSectionIndices.length >= 2) {
        $$renderer2.push("<!--[0-->");
        $$renderer2.push(`<div class="rounded-xl border border-indigo-500/30 bg-indigo-50/70 p-2.5 space-y-2 dark:border-indigo-500/20 dark:bg-indigo-950/30" data-omr-bulk-actions-toolbar="true"><div class="flex items-center justify-between text-[11px]"><span class="font-bold text-indigo-950 dark:text-indigo-200">${escape_html(selectedSectionIndices.length)} items selected</span> <button type="button" data-omr-bulk-clear="true" class="text-[10px] font-semibold text-muted-foreground hover:text-foreground underline">Clear</button></div> <div class="flex items-center gap-1.5">`);
        if (aptitudeAreas.length > 0) {
          $$renderer2.push("<!--[0-->");
          $$renderer2.select(
            {
              value: bulkTargetArea,
              "data-omr-bulk-area-select": "true",
              class: "w-full rounded-md border bg-background px-2 py-1 text-xs font-semibold"
            },
            ($$renderer3) => {
              $$renderer3.push(`<!--[-->`);
              const each_array_4 = ensure_array_like(aptitudeAreas);
              for (let $$index_4 = 0, $$length = each_array_4.length; $$index_4 < $$length; $$index_4++) {
                let area = each_array_4[$$index_4];
                $$renderer3.option({ value: area.code }, ($$renderer4) => {
                  $$renderer4.push(`${escape_html(area.name)} (${escape_html(area.code)})`);
                });
              }
              $$renderer3.push(`<!--]-->`);
            }
          );
        } else {
          $$renderer2.push("<!--[-1-->");
          $$renderer2.push(`<input type="text"${attr("value", bulkTargetArea)} placeholder="AREA" data-omr-bulk-area-input="true" class="w-full rounded-md border bg-background px-2 py-1 text-xs font-semibold uppercase"/>`);
        }
        $$renderer2.push(`<!--]--> <button type="button" data-omr-bulk-reassign-btn="true" class="shrink-0 rounded-md bg-indigo-700 hover:bg-indigo-600 px-2.5 py-1 text-xs font-bold text-white shadow-xs transition">Reassign</button> <button type="button" data-omr-bulk-delete-btn="true" class="shrink-0 rounded-md bg-rose-600/15 hover:bg-rose-600/25 px-2 py-1 text-xs font-semibold text-rose-700 dark:text-rose-300 transition" title="Delete all selected items">✕ Del (${escape_html(selectedSectionIndices.length)})</button></div> <div class="flex items-center justify-between border-t border-indigo-200/50 pt-1.5 text-[9.5px] dark:border-indigo-800/50"><span class="text-indigo-900 dark:text-indigo-200 font-semibold">Rotate Group:</span> <div class="flex items-center gap-1"><button type="button" data-omr-rotate-group="-0.5" class="rounded border px-1.5 py-0.5 font-mono hover:bg-indigo-100 dark:hover:bg-indigo-900/50">⟲ -0.5°</button> <button type="button" data-omr-rotate-group="-0.1" class="rounded border px-1.5 py-0.5 font-mono hover:bg-indigo-100 dark:hover:bg-indigo-900/50">-0.1°</button> <button type="button" data-omr-rotate-group="+0.1" class="rounded border px-1.5 py-0.5 font-mono hover:bg-indigo-100 dark:hover:bg-indigo-900/50">+0.1°</button> <button type="button" data-omr-rotate-group="+0.5" class="rounded border px-1.5 py-0.5 font-mono hover:bg-indigo-100 dark:hover:bg-indigo-900/50">+0.5° ⟳</button></div></div></div>`);
      } else {
        $$renderer2.push("<!--[-1-->");
      }
      $$renderer2.push(`<!--]--> <div class="space-y-1.5 max-h-56 overflow-y-auto pr-0.5"><!--[-->`);
      const each_array_5 = ensure_array_like(sectionsByAptitude());
      for (let $$index_6 = 0, $$length = each_array_5.length; $$index_6 < $$length; $$index_6++) {
        let group = each_array_5[$$index_6];
        const itemIndices = group.items.map((it) => it.originalIndex);
        const allInGroupSelected = itemIndices.length > 0 && itemIndices.every((idx) => selectedSectionIndices.includes(idx));
        $$renderer2.push(`<div class="border border-border/70 rounded-lg overflow-hidden bg-card/60 shadow-2xs"${attr("data-omr-aptitude-group", group.code)}><div class="w-full flex items-center justify-between px-2.5 py-1.5 bg-muted/40 hover:bg-muted/70 text-xs font-bold transition select-none text-left"><button type="button"${attr("aria-expanded", !closedAptitudeGroups.has(group.code))}${attr("aria-controls", `omr-aptitude-panel-${group.code}`)} class="flex items-center gap-1.5 min-w-0 flex-1 text-left"><span class="text-[10px] text-muted-foreground">${escape_html(!closedAptitudeGroups.has(group.code) ? "▾" : "▸")}</span> <span class="truncate">${escape_html(group.name || group.code)} (${escape_html(group.code)})</span></button> <div class="flex items-center gap-2 shrink-0"><button type="button" class="text-[10px] text-primary hover:underline font-medium" title="Toggle multi-select for all items in this area">${escape_html(allInGroupSelected ? "Deselect area" : "Select all")}</button> <span class="rounded-full bg-emerald-500/15 text-emerald-800 dark:text-emerald-300 px-2 py-0.5 text-[9px] font-mono font-semibold shrink-0">${escape_html(group.items.length)} ${escape_html(group.items.length === 1 ? "item" : "items")}</span></div></div> `);
        if (!closedAptitudeGroups.has(group.code)) {
          $$renderer2.push("<!--[0-->");
          $$renderer2.push(`<div${attr("id", `omr-aptitude-panel-${group.code}`)} class="p-2 flex flex-wrap gap-1.5 max-h-36 overflow-y-auto"><!--[-->`);
          const each_array_6 = ensure_array_like(group.items);
          for (let $$index_5 = 0, $$length2 = each_array_6.length; $$index_5 < $$length2; $$index_5++) {
            let item = each_array_6[$$index_5];
            const secIdx = item.originalIndex;
            const isSelected = selectedSectionIndices.includes(secIdx) || activeSectionIndex === secIdx;
            $$renderer2.push(`<button type="button"${attr("data-omr-solid-item-chip", item.id)}${attr("data-omr-selected", isSelected ? "true" : "false")}${attr_class(`flex items-center gap-1 rounded-lg border px-2 py-1 font-mono text-[10px] font-bold transition ${isSelected ? "border-indigo-600 bg-indigo-600 text-white shadow-xs" : "border-border bg-muted/40 text-foreground hover:bg-muted"}`)}><span>${escape_html(item.area_code || group.code)} · Q${escape_html(item.startQ ?? 1)}</span> `);
            if ((Number(item.questionsCount) || 1) > 1) {
              $$renderer2.push("<!--[0-->");
              $$renderer2.push(`<span class="opacity-75">–${escape_html(Number(item.startQ) + Number(item.questionsCount) - 1)}</span>`);
            } else {
              $$renderer2.push("<!--[-1-->");
            }
            $$renderer2.push(`<!--]--></button>`);
          }
          $$renderer2.push(`<!--]--></div>`);
        } else {
          $$renderer2.push("<!--[-1-->");
        }
        $$renderer2.push(`<!--]--></div>`);
      }
      $$renderer2.push(`<!--]--></div> `);
      if (activeSection() && activeSection().type !== "roll") {
        $$renderer2.push("<!--[0-->");
        $$renderer2.push(`<div class="border-border/70 bg-muted/20 grid grid-cols-2 gap-2 rounded-lg border p-2"><label class="space-y-0.5"><span class="text-muted-foreground block text-[9.5px] font-semibold">Selected Item Aptitude</span> <input type="text" data-omr-active-item-area="true"${attr("value", activeSection().area_code ?? "GENERAL")} class="w-full rounded border px-2 py-1 text-xs font-semibold uppercase"/></label> <label class="space-y-0.5"><span class="text-muted-foreground block text-[9.5px] font-semibold">Selected Item #</span> <input type="number" min="1" max="500" data-omr-active-item-number="true"${attr("value", activeSection().startQ)} class="w-full rounded border px-2 py-1 font-mono text-xs font-bold"/></label></div> `);
        if ((Number(activeSection().questionsCount) || 1) === 1) {
          $$renderer2.push("<!--[0-->");
          $$renderer2.push(`<div class="flex items-center justify-between px-1 text-[9.5px] text-muted-foreground pt-1 border-t border-border/50"><span>Tilt Item (${escape_html(activeSection().rotationDeg ? `${activeSection().rotationDeg > 0 ? "+" : ""}${activeSection().rotationDeg}°` : "0°")}):</span> <div class="flex items-center gap-1"><button type="button" data-omr-tilt-item="-0.5" class="rounded border px-1.5 py-0.5 font-mono hover:bg-muted">-0.5°</button> <button type="button" data-omr-tilt-item="-0.1" class="rounded border px-1.5 py-0.5 font-mono hover:bg-muted">-0.1°</button> <button type="button" data-omr-tilt-item="+0.1" class="rounded border px-1.5 py-0.5 font-mono hover:bg-muted">+0.1°</button> <button type="button" data-omr-tilt-item="+0.5" class="rounded border px-1.5 py-0.5 font-mono hover:bg-muted">+0.5°</button></div></div>`);
        } else {
          $$renderer2.push("<!--[-1-->");
        }
        $$renderer2.push(`<!--]-->`);
      } else {
        $$renderer2.push("<!--[-1-->");
      }
      $$renderer2.push(`<!--]--></div>`);
    } else {
      $$renderer2.push("<!--[-1-->");
    }
    $$renderer2.push(`<!--]--></section> `);
    if (isUploadedSource) {
      $$renderer2.push("<!--[0-->");
      $$renderer2.push(`<section${attr("inert", readOnly, true)} class="border-border bg-card space-y-3 rounded-xl border p-3" aria-labelledby="uploaded-identity-heading"><div class="border-border/70 flex items-center justify-between gap-1.5 border-b pb-2"><div class="flex items-center gap-2 min-w-0"><span class="flex size-5 items-center justify-center rounded-md bg-blue-600/15 font-mono text-[10px] font-black text-blue-700 dark:text-blue-300 shrink-0">2</span> <div class="min-w-0"><h3 id="uploaded-identity-heading" class="text-foreground text-xs font-bold truncate block">Applicant Number</h3> <span class="text-[9.5px] text-muted-foreground block truncate">Examinee identity method &amp; digit grid</span></div></div></div> <div class="grid grid-cols-2 gap-2"><button type="button"${attr_class(`flex flex-col items-center justify-center rounded-lg border p-2 text-center text-xs transition ${hasBubbles() ? "border-primary bg-primary/10 text-primary font-semibold" : "border-border bg-muted/20 text-muted-foreground hover:bg-muted/40"}`)}><span class="font-semibold">Shaded Grid</span> <span class="text-[9px] opacity-80">${escape_html(hasBubbles() ? "Active · 0–9 bubbles" : "Click to enable")}</span></button> <button type="button"${attr_class(`flex flex-col items-center justify-center rounded-lg border p-2 text-center text-xs transition ${hasWriteIn() ? "border-primary bg-primary/10 text-primary font-semibold" : "border-border bg-muted/20 text-muted-foreground hover:bg-muted/40"}`)}><span class="font-semibold">Write-in Box</span> <span class="text-[9px] opacity-80">${escape_html(hasWriteIn() ? "Active · Manual review" : "Click to enable")}</span></button></div> `);
      if (hasBubbles()) {
        $$renderer2.push("<!--[0-->");
        $$renderer2.push(`<label class="block space-y-1"><span class="text-muted-foreground block text-[10px]">Exact digit count on every page</span> <input type="number" min="2" max="10" step="1"${attr("value", identity.digit_count ?? "")} aria-label="Applicant Number grid digit count" class="w-full rounded-md border px-2 py-1.5 text-xs"/></label> <!--[-->`);
        const each_array_7 = ensure_array_like(Array.from({ length: Number(pageCount) || 1 }, (_, index) => index + 1));
        for (let $$index_8 = 0, $$length = each_array_7.length; $$index_8 < $$length; $$index_8++) {
          let pageNo = each_array_7[$$index_8];
          const pageGrids = sections.filter((section) => section.identity_grid && Number(section.page_no) === pageNo);
          $$renderer2.push(`<div class="space-y-2 border-t pt-2"><div class="flex items-center justify-between gap-2"><span class="text-foreground text-[10px] font-bold">Page ${escape_html(pageNo)}</span> `);
          if (pageGrids.length > 0) {
            $$renderer2.push("<!--[0-->");
            $$renderer2.push(`<button type="button" data-omr-focus-applicant-grid="true" class="text-primary text-[10px] font-semibold hover:underline">Focus on canvas</button>`);
          } else {
            $$renderer2.push("<!--[-1-->");
          }
          $$renderer2.push(`<!--]--></div> `);
          if (pageGrids[0] && pageGrids[0].rollDirection !== "horizontal") {
            $$renderer2.push("<!--[0-->");
            const tilt = Number(pageGrids[0].rotationDeg) || 0;
            $$renderer2.push(`<div class="flex items-center justify-between gap-2 rounded-lg border border-border/70 bg-muted/20 px-2 py-1.5 text-[10px]"${attr("data-omr-applicant-tilt", pageNo)}><span class="font-semibold">Grid tilt · ${escape_html(tilt > 0 ? "+" : "")}${escape_html(tilt.toFixed(1))}°</span> <div class="flex items-center gap-1"><button type="button"${attr("aria-label", `Tilt Applicant Number grid on Page ${pageNo} by -0.5 degrees`)} data-omr-applicant-tilt="-0.5"${attr("disabled", tilt - 0.5 < -5, true)} class="rounded border px-1.5 py-0.5 font-mono disabled:opacity-40">-0.5°</button> <button type="button"${attr("aria-label", `Tilt Applicant Number grid on Page ${pageNo} by -0.1 degrees`)} data-omr-applicant-tilt="-0.1"${attr("disabled", tilt - 0.1 < -5, true)} class="rounded border px-1.5 py-0.5 font-mono disabled:opacity-40">-0.1°</button> <button type="button"${attr("aria-label", `Tilt Applicant Number grid on Page ${pageNo} by +0.1 degrees`)} data-omr-applicant-tilt="+0.1"${attr("disabled", tilt + 0.1 > 5, true)} class="rounded border px-1.5 py-0.5 font-mono disabled:opacity-40">+0.1°</button> <button type="button"${attr("aria-label", `Tilt Applicant Number grid on Page ${pageNo} by +0.5 degrees`)} data-omr-applicant-tilt="+0.5"${attr("disabled", tilt + 0.5 > 5, true)} class="rounded border px-1.5 py-0.5 font-mono disabled:opacity-40">+0.5°</button></div></div>`);
          } else {
            $$renderer2.push("<!--[-1-->");
          }
          $$renderer2.push(`<!--]--> <div class="space-y-2 rounded-xl border border-indigo-500/30 bg-indigo-500/5 p-2.5 dark:border-indigo-500/20 dark:bg-indigo-950/20" data-omr-applicant-plotter-card="true"><div class="flex items-center justify-between text-[11px]"><span class="font-bold text-foreground">Plot Digit 1 (0..9)</span> <label class="flex items-center gap-1.5 cursor-pointer select-none text-[10px] text-muted-foreground font-semibold"><input type="checkbox"${attr("checked", digitSpanMode, true)} data-omr-digit-span-toggle="true" class="size-3 rounded border-border text-indigo-600 focus:ring-indigo-500"/> <span>2-Click Span (0→9)</span></label></div> <div class="flex items-center gap-1"><!--[-->`);
          const each_array_8 = ensure_array_like(["0", "1", "2", "3", "4", "5", "6", "7", "8", "9"]);
          for (let dIdx = 0, $$length2 = each_array_8.length; dIdx < $$length2; dIdx++) {
            let dVal = each_array_8[dIdx];
            const isPlaced = plottingConfig.active && plottingConfig.targetType === "applicant_digit" && Boolean(plottingConfig.pointsMm?.[dIdx]);
            const isCurrent = plottingConfig.active && plottingConfig.targetType === "applicant_digit" && (plottingConfig.currentOptionIndex ?? 0) === dIdx;
            $$renderer2.push(`<button type="button"${attr("data-omr-digit-pill", dVal)}${attr_class(`flex-1 py-1 text-center font-mono text-[10px] font-bold rounded transition border ${isCurrent ? "border-indigo-600 bg-indigo-600 text-white shadow-xs ring-2 ring-indigo-400/50" : isPlaced ? "border-indigo-500/40 bg-indigo-500/15 text-indigo-900 dark:text-indigo-200" : "border-border bg-muted/40 text-foreground hover:bg-muted"}`)}>${escape_html(dVal)}</button>`);
          }
          $$renderer2.push(`<!--]--></div> <button type="button" data-omr-start-digit-plot="true"${attr("disabled", readOnly, true)}${attr_class(`flex w-full items-center justify-center gap-2 rounded-lg px-3 py-1.5 text-xs font-bold shadow-xs transition ${plottingConfig.active && plottingConfig.targetType === "applicant_digit" ? "bg-amber-600 text-white hover:bg-amber-500" : "bg-indigo-700 text-white hover:bg-indigo-600"}`)}>`);
          if (plottingConfig.active && plottingConfig.targetType === "applicant_digit") {
            $$renderer2.push("<!--[0-->");
            $$renderer2.push(`<span>Stop Digit Plotting (Esc)</span>`);
          } else {
            $$renderer2.push("<!--[-1-->");
            $$renderer2.push(`<span>Plot Digit 1 on Canvas ${escape_html("(Click 0 then 9)")}</span>`);
          }
          $$renderer2.push(`<!--]--></button></div> <div class="space-y-2 rounded-xl border border-indigo-500/30 bg-indigo-50/50 p-2.5 dark:border-indigo-500/20 dark:bg-indigo-950/20" data-omr-multiply-digits-card="true"><div class="flex items-center justify-between text-[11px]"><span class="font-bold text-foreground">Multiply Digits</span> <div class="flex items-center gap-1 bg-muted/60 p-0.5 rounded-lg border border-border/70 text-[9.5px]"><button type="button" data-omr-digit-mult-dir="horizontal"${attr_class(`px-2 py-0.5 rounded font-bold transition flex items-center gap-1 ${"bg-indigo-600 text-white shadow-xs"}`)}>`);
          {
            $$renderer2.push("<!--[0-->");
            $$renderer2.push(`<span>✓</span>`);
          }
          $$renderer2.push(`<!--]--> <span>Horizontal (Across)</span></button> <button type="button" data-omr-digit-mult-dir="vertical"${attr_class(`px-2 py-0.5 rounded font-bold transition flex items-center gap-1 ${"text-muted-foreground hover:text-foreground"}`)}>`);
          {
            $$renderer2.push("<!--[-1-->");
          }
          $$renderer2.push(`<!--]--> <span>Vertical (Down)</span></button></div></div> <div class="grid grid-cols-2 gap-2 text-[10px]"><label class="space-y-0.5"><span class="text-muted-foreground block font-semibold">Total Digits</span> <input type="number" min="2" max="10"${attr("value", identity.digit_count ?? digitMultiplyCount)} data-omr-digit-mult-count="true" class="w-full rounded-md border bg-background px-2 py-1 font-mono text-xs font-bold"/></label> <label class="space-y-0.5"><span class="text-muted-foreground block font-semibold">${escape_html("Col Pitch X (mm)")}</span> <input type="number" min="3.0" max="20.0" step="0.05"${attr("value", digitMultiplyPitchMm)} data-omr-digit-mult-pitch="true" class="w-full rounded-md border bg-background px-2 py-1 font-mono text-xs font-bold"/></label></div> <div class="flex items-center justify-between pt-0.5"><div class="flex items-center gap-1.5 select-none"><span class="text-[9.5px] font-semibold text-muted-foreground">Live Preview</span> <button type="button" role="switch"${attr("aria-checked", digitPreviewEnabled)} data-omr-digit-preview-toggle="true"${attr_class(`relative inline-flex h-4 w-7 shrink-0 cursor-pointer rounded-full border-2 border-transparent transition-colors duration-200 ease-in-out focus:outline-none ${"bg-muted-foreground/30"}`)}><span aria-hidden="true"${attr_class(`pointer-events-none inline-block size-3 transform rounded-full bg-white shadow-sm ring-0 transition duration-200 ease-in-out ${"translate-x-0"}`)}></span></button></div> <button type="button" data-omr-generate-digits-btn="true" class="rounded-lg bg-indigo-700 hover:bg-indigo-600 px-3 py-1.5 text-xs font-bold text-white shadow-xs transition">↓ Generate 6 Digits</button></div></div> `);
          if (pageGrids.length === 0) {
            $$renderer2.push("<!--[0-->");
            $$renderer2.push(`<button type="button" data-omr-add-applicant-grid="true" class="text-primary text-[10px] font-semibold hover:underline block pt-1">or create default 6-digit box</button>`);
          } else {
            $$renderer2.push("<!--[-1-->");
          }
          $$renderer2.push(`<!--]--> `);
          if (pageGrids.length > 0) {
            $$renderer2.push("<!--[0-->");
            const grid = pageGrids[0];
            $$renderer2.push(`<div class="bg-muted/20 border-border/80 space-y-2 rounded-lg border p-2.5 text-[11px]" data-omr-applicant-grid-controls="true"><div class="space-y-1"><span class="text-muted-foreground block text-[9.5px] font-semibold">Digit Orientation</span> <div class="grid grid-cols-2 gap-1.5"><button type="button" data-omr-roll-dir="vertical"${attr_class(`rounded-md px-2 py-1 text-xs font-bold transition ${(grid.rollDirection ?? "vertical") === "vertical" ? "bg-primary text-primary-foreground shadow-xs" : "bg-muted/60 text-muted-foreground hover:bg-muted hover:text-foreground"}`)}>Vertical (Columns)</button> <button type="button" data-omr-roll-dir="horizontal"${attr_class(`rounded-md px-2 py-1 text-xs font-bold transition ${grid.rollDirection === "horizontal" ? "bg-primary text-primary-foreground shadow-xs" : "bg-muted/60 text-muted-foreground hover:bg-muted hover:text-foreground"}`)}>Horizontal (Rows)</button></div></div> <div class="grid grid-cols-2 gap-2"><label class="space-y-0.5"><span class="text-muted-foreground block text-[9.5px]">${escape_html((grid.rollDirection ?? "vertical") === "vertical" ? "Digit Col Spacing (mm)" : "Value Bubble Spacing (mm)")}</span> <input type="number" min="3.0" max="20.0" step="0.05"${attr("value", grid.bubblePitchMm)} data-omr-roll-col-pitch="true" class="w-full rounded border px-2 py-1 font-mono text-xs font-bold"/></label> <label class="space-y-0.5"><span class="text-muted-foreground block text-[9.5px]">${escape_html((grid.rollDirection ?? "vertical") === "vertical" ? "Value Bubble Spacing (mm)" : "Digit Row Spacing (mm)")}</span> <input type="number" min="3.0" max="20.0" step="0.05"${attr("value", grid.rowPitchMm)} data-omr-roll-row-pitch="true" class="w-full rounded border px-2 py-1 font-mono text-xs font-bold"/></label></div> <div class="grid grid-cols-2 gap-2"><label class="space-y-0.5"><span class="text-muted-foreground block text-[9.5px]">Position X (mm)</span> <input type="number" min="0" max="250" step="0.5"${attr("value", grid.posX)} data-omr-roll-pos-x="true" class="w-full rounded border px-2 py-1 font-mono text-xs"/></label> <label class="space-y-0.5"><span class="text-muted-foreground block text-[9.5px]">Position Y (mm)</span> <input type="number" min="0" max="350" step="0.5"${attr("value", grid.posY)} data-omr-roll-pos-y="true" class="w-full rounded border px-2 py-1 font-mono text-xs"/></label></div> <div class="space-y-1"><div class="flex items-center justify-between text-[10px]"><span class="text-muted-foreground">Bubble Size:</span> <span class="font-mono font-bold text-emerald-600 dark:text-emerald-400">${escape_html(Number(grid.bubbleDiameterMm || 4.5).toFixed(1))} mm Ø</span></div> <input type="range"${attr("min", minBubbleDiameter())} max="8.0" step="0.1"${attr("value", grid.bubbleDiameterMm)} class="accent-primary bg-muted h-1.5 w-full cursor-pointer rounded-lg"/></div></div>`);
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
      if (hasWriteIn()) {
        $$renderer2.push("<!--[0-->");
        $$renderer2.push(`<!--[-->`);
        const each_array_9 = ensure_array_like(Array.from({ length: Number(pageCount) || 1 }, (_, index) => index + 1));
        for (let $$index_9 = 0, $$length = each_array_9.length; $$index_9 < $$length; $$index_9++) {
          let pageNo = each_array_9[$$index_9];
          const box = (identity?.write_in_boxes ?? []).find((b) => Number(b.page_no) === pageNo) ?? { x_mm: 110, y_mm: 18, w_mm: 42, h_mm: 8.5 };
          $$renderer2.push(`<div class="border-border/70 space-y-2 border-t pt-2"><div class="flex items-center justify-between gap-2"><span class="text-foreground text-[10px] font-semibold">Page ${escape_html(pageNo)} Handwritten Box</span> <span class="text-[9px] text-muted-foreground font-mono">${escape_html(box.w_mm ?? 42)} × ${escape_html(box.h_mm ?? 8.5)} mm</span></div> <p class="text-muted-foreground text-[9px] leading-relaxed">Drag or resize the amber box on the canvas directly over the handwritten boxes on the sheet.</p> <div class="grid grid-cols-2 gap-2 bg-muted/30 rounded-lg p-2"><label class="space-y-1"><span class="text-muted-foreground block text-[9px]">X (mm)</span> <input type="number" min="0" step="0.5"${attr("value", box.x_mm ?? 110)} class="w-full rounded-md border px-2 py-1 text-xs"/></label> <label class="space-y-1"><span class="text-muted-foreground block text-[9px]">Y (mm)</span> <input type="number" min="0" step="0.5"${attr("value", box.y_mm ?? 18)} class="w-full rounded-md border px-2 py-1 text-xs"/></label> <label class="space-y-1"><span class="text-muted-foreground block text-[9px]">Width (mm)</span> <input type="number" min="5" step="0.5"${attr("value", box.w_mm ?? 42)} class="w-full rounded-md border px-2 py-1 text-xs"/></label> <label class="space-y-1"><span class="text-muted-foreground block text-[9px]">Height (mm)</span> <input type="number" min="4" step="0.5"${attr("value", box.h_mm ?? 8.5)} class="w-full rounded-md border px-2 py-1 text-xs"/></label></div></div>`);
        }
        $$renderer2.push(`<!--]-->`);
      } else {
        $$renderer2.push("<!--[-1-->");
      }
      $$renderer2.push(`<!--]--></section> <section${attr("inert", readOnly, true)} class="border-border bg-card space-y-3 rounded-xl border p-3" aria-labelledby="document-fields-heading" data-omr-document-fields-panel="true"><div class="border-border/70 flex items-center justify-between gap-1.5 border-b pb-2"><div class="flex items-center gap-2 min-w-0"><span class="flex size-5 items-center justify-center rounded-md bg-violet-600/15 font-mono text-[10px] font-black text-violet-700 dark:text-violet-300 shrink-0">3</span> <div class="min-w-0"><h3 id="document-fields-heading" class="text-foreground text-xs font-bold truncate block">Document Fields</h3> <span class="text-[9.5px] text-muted-foreground block truncate">Printed name, birthdate &amp; signature boxes</span></div></div></div> <div class="flex flex-wrap gap-1.5"><!--[-->`);
      const each_array_10 = ensure_array_like([
        {
          action: "+ Name",
          type: "candidate_name",
          label: "Applicant Name",
          w_mm: 75,
          h_mm: 10,
          x_mm: 20,
          y_mm: 35
        },
        {
          action: "+ Birthdate",
          type: "birthdate",
          label: "Birthdate",
          w_mm: 45,
          h_mm: 10,
          x_mm: 20,
          y_mm: 50
        },
        {
          action: "+ Room/Seat",
          type: "room_seat",
          label: "Room / Seat",
          w_mm: 40,
          h_mm: 10,
          x_mm: 20,
          y_mm: 65
        },
        {
          action: "+ Signature",
          type: "signature",
          label: "Signature",
          w_mm: 60,
          h_mm: 15,
          x_mm: 120,
          y_mm: 250
        },
        {
          action: "+ Custom",
          type: "custom_input",
          label: "Custom Field",
          w_mm: 50,
          h_mm: 10,
          x_mm: 20,
          y_mm: 80
        }
      ]);
      for (let $$index_10 = 0, $$length = each_array_10.length; $$index_10 < $$length; $$index_10++) {
        let preset = each_array_10[$$index_10];
        $$renderer2.push(`<button type="button"${attr("disabled", readOnly, true)} class="rounded-md border border-violet-300 bg-violet-50 px-2 py-1 text-[10px] font-semibold text-violet-800 hover:bg-violet-100">${escape_html(preset.action)}</button>`);
      }
      $$renderer2.push(`<!--]--></div> `);
      if (activeDocumentFields().length === 0) {
        $$renderer2.push("<!--[0-->");
        $$renderer2.push(`<p class="text-muted-foreground text-[10px]">No document fields on Page ${escape_html(activePageNo)}.</p>`);
      } else {
        $$renderer2.push("<!--[-1-->");
        $$renderer2.push(`<div class="space-y-2"><!--[-->`);
        const each_array_11 = ensure_array_like(activeDocumentFields());
        for (let $$index_12 = 0, $$length = each_array_11.length; $$index_12 < $$length; $$index_12++) {
          let field = each_array_11[$$index_12];
          $$renderer2.push(`<div${attr_class(`space-y-2 rounded-lg border p-2 ${activeDocumentFieldId === field.id ? "border-violet-500 bg-violet-500/5" : "border-border"}`)}${attr("data-omr-document-field-row", field.id)}><div class="flex items-center gap-2"><button type="button" class="min-w-0 flex-1 text-left"${attr("aria-pressed", activeDocumentFieldId === field.id)}><span class="inline-flex rounded bg-violet-100 px-1.5 py-0.5 text-[9px] font-bold text-violet-800">${escape_html(field.type)}</span></button> <button type="button"${attr("disabled", readOnly, true)} class="rounded p-1 text-rose-600 hover:bg-rose-50"${attr("aria-label", `Delete ${field.label || "document field"}`)}>`);
          Trash_2($$renderer2, { class: "size-3.5" });
          $$renderer2.push(`<!----></button></div> <label class="block space-y-1"><span class="text-muted-foreground text-[9px] font-semibold">Label</span> <input type="text"${attr("readonly", readOnly, true)}${attr("value", field.label ?? "")}${attr("aria-label", `${field.type} field label`)} class="w-full rounded border px-2 py-1 text-[10px]"/></label> <div class="grid grid-cols-4 gap-1.5"><!--[-->`);
          const each_array_12 = ensure_array_like(["x_mm", "y_mm", "w_mm", "h_mm"]);
          for (let $$index_11 = 0, $$length2 = each_array_12.length; $$index_11 < $$length2; $$index_11++) {
            let key = each_array_12[$$index_11];
            $$renderer2.push(`<label class="min-w-0 space-y-1"><span class="text-muted-foreground text-[9px] font-semibold">${escape_html(key)}</span> <input type="number"${attr("readonly", readOnly, true)} min="0" step="0.1"${attr("value", field[key] ?? 0)}${attr("aria-label", `${field.label || field.type} ${key}`)} class="w-full rounded border px-1.5 py-1 font-mono text-[10px]"/></label>`);
          }
          $$renderer2.push(`<!--]--></div></div>`);
        }
        $$renderer2.push(`<!--]--></div>`);
      }
      $$renderer2.push(`<!--]--></section> <div${attr_class(`rounded-lg border p-2.5 text-[10px] ${uploadMappingReadiness().ready ? "border-emerald-500/25 bg-emerald-500/10 text-emerald-900 dark:text-emerald-200" : "border-amber-500/25 bg-amber-500/10 text-amber-900 dark:text-amber-200"}`)}${attr("role", uploadMappingReadiness().ready ? "status" : "alert")}${attr("aria-live", uploadMappingReadiness().ready ? "polite" : "assertive")}><p class="font-semibold">${escape_html(uploadMappingReadiness().ready ? "Declared mapping is complete; this is not qualification." : `Mapping incomplete · ${uploadMappingReadiness().issues.length} blocking detail${uploadMappingReadiness().issues.length === 1 ? "" : "s"}`)}</p> `);
      if (!uploadMappingReadiness().ready) {
        $$renderer2.push("<!--[0-->");
        $$renderer2.push(`<ul class="mt-1 list-disc space-y-0.5 pl-4"><!--[-->`);
        const each_array_13 = ensure_array_like(uploadMappingReadiness().issues);
        for (let $$index_13 = 0, $$length = each_array_13.length; $$index_13 < $$length; $$index_13++) {
          let issue = each_array_13[$$index_13];
          $$renderer2.push(`<li>${escape_html(issue)}</li>`);
        }
        $$renderer2.push(`<!--]--></ul>`);
      } else {
        $$renderer2.push("<!--[-1-->");
      }
      $$renderer2.push(`<!--]--></div>`);
    } else {
      $$renderer2.push("<!--[-1-->");
      $$renderer2.push(`<div class="space-y-1.5"${attr("inert", readOnly, true)}><span class="text-muted-foreground text-[10px] font-bold tracking-wider uppercase">Add recognition block</span> <div class="grid grid-cols-2 gap-2"><button type="button" class="border-border bg-card/90 focus-visible:ring-ring flex items-center gap-2.5 rounded-xl border p-2 text-left transition hover:border-emerald-500/60 hover:bg-emerald-500/5 focus-visible:ring-2 focus-visible:outline-none"><div class="bg-muted text-foreground flex size-7 shrink-0 items-center justify-center rounded-lg">`);
      Plus($$renderer2, { class: "size-3.5" });
      $$renderer2.push(`<!----></div> <div class="min-w-0"><span class="text-foreground block text-[11px] leading-tight font-bold">Single question</span> <span class="text-muted-foreground block truncate text-[9.5px]">1 item callout</span></div></button> <button type="button" class="border-border bg-card/90 focus-visible:ring-ring flex items-center gap-2.5 rounded-xl border p-2 text-left transition hover:border-emerald-500/60 hover:bg-emerald-500/5 focus-visible:ring-2 focus-visible:outline-none"><div class="bg-primary text-primary-foreground flex size-7 shrink-0 items-center justify-center rounded-lg">`);
      Scan($$renderer2, { class: "size-3.5" });
      $$renderer2.push(`<!----></div> <div class="min-w-0"><span class="text-foreground block text-[11px] leading-tight font-bold">Question range</span> <span class="text-muted-foreground block truncate text-[9.5px]">Consecutive grid</span></div></button></div></div>`);
    }
    $$renderer2.push(`<!--]--> `);
    if (previewRunning) {
      $$renderer2.push("<!--[0-->");
      $$renderer2.push(`<div role="status" aria-live="polite" class="flex items-center gap-2 rounded-lg border border-blue-500/25 bg-blue-500/10 px-2.5 py-2 text-xs text-blue-900 dark:text-blue-200"><div class="size-2 animate-pulse rounded-full bg-blue-500"></div> <span>Verifying native OMR geometry…</span></div>`);
    } else if (previewError) {
      $$renderer2.push("<!--[1-->");
      const errorDetail = errorText(previewError) || "The answer-sheet print preview could not be generated.";
      const errorCode = typeof previewError === "object" && typeof previewError?.code === "string" ? previewError.code : "";
      $$renderer2.push(`<div role="alert" aria-live="assertive" class="space-y-1 rounded-lg border border-rose-500/25 bg-rose-500/10 p-2.5 text-xs text-rose-900 dark:text-rose-200"><div class="flex items-center gap-1.5 font-semibold">`);
      Circle_x($$renderer2, { class: "size-3.5 shrink-0 text-rose-600 dark:text-rose-400" });
      $$renderer2.push(`<!----> <span>Recognition mapping needs attention: ${escape_html(errorDetail)}</span></div> `);
      if (errorCode && errorCode !== errorDetail) {
        $$renderer2.push("<!--[0-->");
        $$renderer2.push(`<details class="mt-1 text-[10px] text-rose-700/90 dark:text-rose-400/90"><summary class="inline-block cursor-pointer font-mono hover:underline">Technical details</summary> <div class="mt-0.5 font-mono text-[10px]">${escape_html(errorCode)}</div></details>`);
      } else {
        $$renderer2.push("<!--[-1-->");
      }
      $$renderer2.push(`<!--]--></div>`);
    } else if (mappingErrors.length > 0) {
      $$renderer2.push("<!--[2-->");
      $$renderer2.push(`<div class="space-y-1.5 rounded-lg border border-rose-500/25 bg-rose-500/10 p-2.5 text-[11px] text-rose-900 dark:text-rose-200" role="alert" aria-live="assertive"><div class="flex items-center gap-1.5 font-bold">`);
      Circle_x($$renderer2, { class: "size-3.5 shrink-0 text-rose-600 dark:text-rose-400" });
      $$renderer2.push(`<!----> <span>Recognition mapping needs attention</span></div> <ul class="space-y-0.5 pl-4"><!--[-->`);
      const each_array_14 = ensure_array_like(mappingErrors);
      for (let $$index_14 = 0, $$length = each_array_14.length; $$index_14 < $$length; $$index_14++) {
        let issue = each_array_14[$$index_14];
        $$renderer2.push(`<li class="list-disc">${escape_html(issue.block && !String(issue.message).includes(issue.block) ? `${issue.block}: ` : "")}${escape_html(issue.message)}</li>`);
      }
      $$renderer2.push(`<!--]--></ul> <p class="text-[10px] opacity-80">Resolve this issue before continuing.</p></div>`);
    } else {
      $$renderer2.push("<!--[-1-->");
    }
    $$renderer2.push(`<!--]--> `);
    if (canonicalPreview) {
      $$renderer2.push("<!--[0-->");
      $$renderer2.push(`<div class="space-y-1"><div class="border-border/80 bg-muted/60 grid grid-cols-3 rounded-xl border p-1" aria-label="Preview surface"><!--[-->`);
      const each_array_15 = ensure_array_like([
        [
          "canonical",
          "Print Sheet",
          Boolean(canonicalPreview.artifact?.canonical_url)
        ],
        [
          "aligned",
          "Aligned Check",
          Boolean(canonicalPreview.aligned_url)
        ],
        [
          "overlay",
          "Recognition Overlay",
          Boolean(canonicalPreview.overlay_url)
        ]
      ]);
      for (let $$index_15 = 0, $$length = each_array_15.length; $$index_15 < $$length; $$index_15++) {
        let [surfaceKey, surfaceLabel, isAvailable] = each_array_15[$$index_15];
        $$renderer2.push(`<button type="button"${attr("disabled", !isAvailable, true)}${attr_class(`rounded-lg px-2 py-1.5 text-[10px] font-semibold transition ${!isAvailable ? "text-muted-foreground cursor-not-allowed opacity-40" : previewSurface === surfaceKey ? "bg-card text-foreground shadow-xs" : "text-muted-foreground hover:text-foreground"}`)}>${escape_html(surfaceLabel)}</button>`);
      }
      $$renderer2.push(`<!--]--></div> `);
      if (!canonicalPreview.aligned_url || !canonicalPreview.overlay_url) {
        $$renderer2.push("<!--[0-->");
        $$renderer2.push(`<p class="text-muted-foreground px-0.5 text-[10px]">Aligned and overlay diagnostics are unavailable for this preview.</p>`);
      } else {
        $$renderer2.push("<!--[-1-->");
      }
      $$renderer2.push(`<!--]--></div>`);
    } else {
      $$renderer2.push("<!--[-1-->");
    }
    $$renderer2.push(`<!--]--> `);
    if (showRegistrationControls) {
      $$renderer2.push("<!--[0-->");
      $$renderer2.push(`<details class="group border-border bg-card rounded-xl border transition-all"><summary class="hover:bg-muted/40 flex cursor-pointer list-none items-center justify-between gap-3 rounded-xl px-3 py-2.5 transition"><div class="min-w-0"><span class="text-foreground block text-[11px] font-bold">Page registration</span> <span class="text-muted-foreground block truncate text-[10px]">${escape_html(selectedProfile() ? `${selectedProfile().code} v${selectedProfile().version}` : "Select the marker detector")}</span></div> <span${attr_class(`shrink-0 rounded-full px-2 py-0.5 text-[9px] font-semibold ${selectedProfile()?.status === "qualified" ? "bg-emerald-500/10 text-emerald-700 dark:text-emerald-400" : "bg-amber-500/10 text-amber-700 dark:text-amber-400"}`)}>${escape_html(selectedProfile()?.status === "qualified" ? "Qualified" : "Preview only")}</span></summary> <div class="border-border space-y-2 border-t px-3 py-2.5"><p class="text-muted-foreground text-[10px] leading-relaxed">Locates corner markers and deskews paper during recognition.</p> `);
      if (markerProfiles.length > 1) {
        $$renderer2.push("<!--[0-->");
        $$renderer2.select(
          {
            disabled: readOnly,
            value: selectedMarkerProfileId ?? "",
            onchange: (event) => onMarkerProfileChange?.(Number(event.currentTarget.value)),
            class: "border-input bg-background text-foreground h-8 w-full rounded-md border px-2 text-xs"
          },
          ($$renderer3) => {
            $$renderer3.option({ value: "", disabled: true }, ($$renderer4) => {
              $$renderer4.push(`Select registration profile`);
            });
            $$renderer3.push(`<!--[-->`);
            const each_array_16 = ensure_array_like(markerProfiles);
            for (let $$index_16 = 0, $$length = each_array_16.length; $$index_16 < $$length; $$index_16++) {
              let profile = each_array_16[$$index_16];
              $$renderer3.option({ value: profile.id }, ($$renderer4) => {
                $$renderer4.push(`${escape_html(profile.code)} v${escape_html(profile.version)} · ${escape_html(profile.status)}`);
              });
            }
            $$renderer3.push(`<!--]-->`);
          }
        );
      } else {
        $$renderer2.push("<!--[-1-->");
      }
      $$renderer2.push(`<!--]--> `);
      if (selectedProfile()?.status !== "qualified") {
        $$renderer2.push("<!--[0-->");
        $$renderer2.push(`<p class="text-[10px] text-amber-700 dark:text-amber-400">Preview only — production compile requires qualified
                        calibration.</p>`);
      } else {
        $$renderer2.push("<!--[-1-->");
      }
      $$renderer2.push(`<!--]--></div></details>`);
    } else {
      $$renderer2.push("<!--[-1-->");
    }
    $$renderer2.push(`<!--]--> <div class="bg-muted/40 text-muted-foreground border-border/50 flex items-center gap-2 rounded-lg border px-2.5 py-1.5">`);
    Scan($$renderer2, {
      class: "size-3.5 shrink-0 text-emerald-600 dark:text-emerald-400"
    });
    $$renderer2.push(`<!----> <span class="text-foreground text-[11px] font-medium">Recognition guides</span> `);
    StudioTip($$renderer2, {
      position: "top",
      text: "Green rings show exact read coordinates over printed bubbles; they do not alter printed sheets."
    });
    $$renderer2.push(`<!----></div> `);
    if (!isUploadedSource) {
      $$renderer2.push("<!--[0-->");
      $$renderer2.push(`<div class="space-y-1"${attr("inert", readOnly, true)}><div class="text-muted-foreground flex items-center justify-between px-0.5 text-[10px] font-bold tracking-wider uppercase"><span>Sections</span> <span class="text-muted-foreground text-[9.5px] font-normal normal-case">Select to position &amp; tune</span></div> <div class="no-scrollbar flex items-center gap-1.5 overflow-x-auto pt-0.5 pb-1" role="tablist"><!--[-->`);
      const each_array_17 = ensure_array_like(sections);
      for (let i = 0, $$length = each_array_17.length; i < $$length; i++) {
        let sec = each_array_17[i];
        $$renderer2.push(`<button type="button" role="tab"${attr("aria-selected", activeSectionIndex === i)}${attr_class(`flex shrink-0 items-center gap-1.5 rounded-lg px-2.5 py-1 text-xs font-semibold transition ${activeSectionIndex === i ? "bg-primary text-primary-foreground shadow-xs" : "bg-muted/80 text-muted-foreground hover:bg-muted hover:text-foreground"}`)}>`);
        if (sec.type === "roll") {
          $$renderer2.push("<!--[0-->");
          $$renderer2.push(`<span>${escape_html(sec.id)} (${escape_html(Number(sec.questionsCount) || 7)} Digits)</span>`);
        } else {
          $$renderer2.push("<!--[-1-->");
          $$renderer2.push(`<span>${escape_html(sec.id)} (Q${escape_html(sec.startQ)}–${escape_html(Number(sec.startQ) + Number(sec.questionsCount) - 1)})</span>`);
        }
        $$renderer2.push(`<!--]--> `);
        if (sec.isLocked) {
          $$renderer2.push("<!--[0-->");
          Lock($$renderer2, { class: "size-2.5 text-amber-400" });
        } else {
          $$renderer2.push("<!--[-1-->");
        }
        $$renderer2.push(`<!--]--></button>`);
      }
      $$renderer2.push(`<!--]--></div></div> `);
      if (activeSection()) {
        $$renderer2.push("<!--[0-->");
        $$renderer2.push(`<div class="bg-card border-border space-y-3 rounded-xl border p-3.5 text-xs shadow-xs"${attr("inert", readOnly, true)}><div class="border-border/70 flex items-center justify-between border-b pb-2"><span class="text-foreground text-sm font-bold tracking-tight">${escape_html(activeSection().name)}</span> <div class="flex items-center gap-1.5"><button type="button"${attr_class(`flex items-center gap-1 rounded-md border px-2 py-1 text-[10px] font-semibold transition ${activeSection().isLocked ? "border-amber-500/40 bg-amber-500/10 text-amber-700 dark:text-amber-400" : "bg-background text-foreground border-border hover:bg-muted"}`)}${attr("title", activeSection().isLocked ? "Unlock recognition guide for positioning" : "Lock recognition guide in place")}>`);
        if (activeSection().isLocked) {
          $$renderer2.push("<!--[0-->");
          Lock($$renderer2, { class: "size-2.5 text-amber-700 dark:text-amber-400" });
          $$renderer2.push(`<!----> <span>Locked</span>`);
        } else {
          $$renderer2.push("<!--[-1-->");
          Lock_open($$renderer2, { class: "text-muted-foreground size-2.5" });
          $$renderer2.push(`<!----> <span>Unlocked</span>`);
        }
        $$renderer2.push(`<!--]--></button> `);
        if (sections.length > 1) {
          $$renderer2.push("<!--[0-->");
          $$renderer2.push(`<button type="button" class="text-muted-foreground rounded-md p-1 transition hover:bg-rose-500/10 hover:text-rose-600" title="Delete section">`);
          Trash_2($$renderer2, { class: "size-3.5" });
          $$renderer2.push(`<!----></button>`);
        } else {
          $$renderer2.push("<!--[-1-->");
        }
        $$renderer2.push(`<!--]--></div></div> <div class="grid grid-cols-2 gap-2.5"><label class="block space-y-1"><span class="text-muted-foreground block text-[11px] font-medium">${escape_html(activeSection().type === "roll" ? "Digits Count" : "Question Count")}</span> <input type="number" min="1" max="100"${attr("value", activeSection().questionsCount)} class="bg-background border-input text-foreground focus:ring-ring w-full rounded-lg border px-2.5 py-1.5 text-xs font-semibold focus:ring-2 focus:outline-none"/></label> <label class="block space-y-1"><span class="text-muted-foreground block text-[11px] font-medium">${escape_html(activeSection().type === "roll" ? "Column Prefix" : "Start Question #")}</span> `);
        if (activeSection().type === "roll") {
          $$renderer2.push("<!--[0-->");
          $$renderer2.push(`<input type="text"${attr("value", activeSection().id)} class="bg-background border-input text-foreground focus:ring-ring w-full rounded-lg border px-2.5 py-1.5 text-xs font-semibold focus:ring-2 focus:outline-none"/>`);
        } else {
          $$renderer2.push("<!--[-1-->");
          $$renderer2.push(`<input type="number" min="1" max="500"${attr("value", activeSection().startQ)} class="bg-background border-input text-foreground focus:ring-ring w-full rounded-lg border px-2.5 py-1.5 text-xs font-semibold focus:ring-2 focus:outline-none"/>`);
        }
        $$renderer2.push(`<!--]--></label></div> `);
        if (activeSection().type !== "roll" && subtests().length > 0) {
          $$renderer2.push("<!--[0-->");
          $$renderer2.push(`<label class="block space-y-1"><span class="text-muted-foreground block text-[11px] font-medium">Test Section / Subtest</span> `);
          $$renderer2.select(
            {
              "data-omr-section-subtest-select": "true",
              "aria-label": "Test Section / Subtest",
              value: subtestCodeFor(activeSection()),
              onchange: handleSubtestChange,
              class: "bg-background border-input text-foreground focus:ring-ring w-full rounded-lg border px-2.5 py-1.5 text-xs focus:ring-2 focus:outline-none"
            },
            ($$renderer3) => {
              $$renderer3.option({ value: "" }, ($$renderer4) => {
                $$renderer4.push(`Select subtest before compile`);
              });
              $$renderer3.push(`<!--[-->`);
              const each_array_18 = ensure_array_like(subtests());
              for (let $$index_18 = 0, $$length = each_array_18.length; $$index_18 < $$length; $$index_18++) {
                let subtest = each_array_18[$$index_18];
                $$renderer3.option({ value: subtest.code }, ($$renderer4) => {
                  $$renderer4.push(`${escape_html(subtest.name)} (${escape_html(subtest.code)})`);
                });
              }
              $$renderer3.push(`<!--]-->`);
            }
          );
          $$renderer2.push(`</label>`);
        } else if (activeSection().type !== "roll" && aptitudeAreas.length > 0) {
          $$renderer2.push("<!--[1-->");
          $$renderer2.push(`<label class="block space-y-1"><span class="text-muted-foreground block text-[11px] font-medium">System aptitude area</span> `);
          $$renderer2.select(
            {
              "aria-label": "System aptitude area",
              value: activeSection().aptitudeAreaCode ?? "",
              onchange: (event) => handleAreaChange(event),
              class: "bg-background border-input text-foreground focus:ring-ring w-full rounded-lg border px-2.5 py-1.5 text-xs focus:ring-2 focus:outline-none"
            },
            ($$renderer3) => {
              $$renderer3.option({ value: "" }, ($$renderer4) => {
                $$renderer4.push(`Select area before compile`);
              });
              $$renderer3.push(`<!--[-->`);
              const each_array_19 = ensure_array_like(aptitudeAreas);
              for (let $$index_19 = 0, $$length = each_array_19.length; $$index_19 < $$length; $$index_19++) {
                let area = each_array_19[$$index_19];
                $$renderer3.option({ value: area.code }, ($$renderer4) => {
                  $$renderer4.push(`${escape_html(area.name)}`);
                });
              }
              $$renderer3.push(`<!--]-->`);
            }
          );
          $$renderer2.push(`</label>`);
        } else {
          $$renderer2.push("<!--[-1-->");
        }
        $$renderer2.push(`<!--]--> <label class="block space-y-1"><span class="text-muted-foreground block text-[11px] font-medium">Option Format</span> `);
        $$renderer2.select(
          {
            value: activeSection().type,
            onchange: handleInput,
            class: "bg-background border-input text-foreground focus:ring-ring w-full rounded-lg border px-2.5 py-1.5 text-xs focus:ring-2 focus:outline-none"
          },
          ($$renderer3) => {
            $$renderer3.option({ value: "mcq" }, ($$renderer4) => {
              $$renderer4.push(`Multiple Choice (4 Options: A, B, C, D)`);
            });
            $$renderer3.option({ value: "mcq", selected: activeSection().optionsCount === 5 }, ($$renderer4) => {
              $$renderer4.push(`Multiple Choice (5 Options: A, B, C, D, E)`);
            });
            $$renderer3.option({ value: "tf" }, ($$renderer4) => {
              $$renderer4.push(`True / False (2 Options: T, F)`);
            });
            $$renderer3.option({ value: "likert" }, ($$renderer4) => {
              $$renderer4.push(`Likert Scale (5 Options: 1, 2, 3, 4, 5)`);
            });
            $$renderer3.option({ value: "roll" }, ($$renderer4) => {
              $$renderer4.push(`Examinee / Student ID Matrix (0–9 Digits)`);
            });
            $$renderer3.option({ value: "custom" }, ($$renderer4) => {
              $$renderer4.push(`Custom Values`);
            });
          }
        );
        $$renderer2.push(`</label> <div class="border-border/70 space-y-2.5 border-t pt-2.5"><div class="space-y-1"><div class="flex items-center justify-between text-[11px]"><span class="text-muted-foreground flex items-center gap-1 font-medium">Bubble Spacing (X) `);
        StudioTip($$renderer2, {
          text: "Horizontal center-to-center distance between option bubbles (A ↔ B ↔ C ↔ D)."
        });
        $$renderer2.push(`<!----></span> <span class="bg-muted/80 border-border text-foreground rounded border px-1.5 py-0.5 font-mono text-[10px] font-semibold">${escape_html(activeSection().bubblePitchMm)} mm</span></div> <input type="range"${attr("min", minimumRecognitionPitchMm(activeSection().bubbleDiameterMm, minBubbleDiameter()))} step="0.1"${attr("value", activeSection().bubblePitchMm)} class="accent-primary bg-muted h-1.5 w-full cursor-pointer rounded-lg"/></div> `);
        if ((Number(activeSection().questionsCount) || 1) > 1) {
          $$renderer2.push("<!--[0-->");
          $$renderer2.push(`<div class="space-y-1"><div class="flex items-center justify-between text-[11px]"><span class="text-muted-foreground flex items-center gap-1 font-medium">Row Spacing (Y) `);
          StudioTip($$renderer2, {
            text: "Vertical center-to-center distance between question rows (Q1 ↕ Q2)."
          });
          $$renderer2.push(`<!----></span> <span class="bg-muted/80 border-border text-foreground rounded border px-1.5 py-0.5 font-mono text-[10px] font-semibold">${escape_html(activeSection().rowPitchMm)} mm</span></div> <input type="range"${attr("min", minimumRecognitionPitchMm(activeSection().bubbleDiameterMm, minBubbleDiameter()))} step="0.2"${attr("value", activeSection().rowPitchMm)} class="accent-primary bg-muted h-1.5 w-full cursor-pointer rounded-lg"/></div>`);
        } else {
          $$renderer2.push("<!--[-1-->");
        }
        $$renderer2.push(`<!--]--></div> <div class="border-border/70 grid grid-cols-2 gap-2.5 border-t pt-2.5"><label class="block space-y-1"><span class="text-muted-foreground block text-[11px] font-medium">Origin X (mm)</span> <input type="number" min="10" max="180"${attr("value", activeSection().posX)} class="bg-background border-input text-foreground focus:ring-ring w-full rounded-lg border px-2.5 py-1.5 text-xs focus:ring-2 focus:outline-none"/></label> <label class="block space-y-1"><span class="text-muted-foreground block text-[11px] font-medium">Origin Y (mm)</span> <input type="number" min="20" max="260"${attr("value", activeSection().posY)} class="bg-background border-input text-foreground focus:ring-ring w-full rounded-lg border px-2.5 py-1.5 text-xs focus:ring-2 focus:outline-none"/></label></div></div>`);
      } else {
        $$renderer2.push("<!--[-1-->");
      }
      $$renderer2.push(`<!--]-->`);
    } else {
      $$renderer2.push("<!--[-1-->");
    }
    $$renderer2.push(`<!--]--> <div class="border-border flex items-center justify-between gap-2.5 border-t pt-3"><button type="button" class="border-border hover:bg-muted text-foreground focus-visible:ring-ring flex items-center gap-1.5 rounded-xl border px-3 py-2 text-xs font-semibold transition focus-visible:ring-2 focus-visible:outline-none">`);
    Chevron_left($$renderer2, { class: "size-3.5" });
    $$renderer2.push(`<!----> <span>Back</span></button> <button type="button"${attr("disabled", previewRunning || transitionRunning, true)} class="bg-primary hover:bg-primary/90 text-primary-foreground focus-visible:ring-ring flex items-center gap-1.5 rounded-xl px-4 py-2 text-xs font-semibold shadow-xs transition focus-visible:ring-2 focus-visible:outline-none disabled:opacity-50">`);
    if (previewRunning) {
      $$renderer2.push("<!--[0-->");
      $$renderer2.push(`<span>Checking mapped layout…</span>`);
    } else if (transitionRunning) {
      $$renderer2.push("<!--[1-->");
      $$renderer2.push(`<span>Preparing verification…</span>`);
    } else {
      $$renderer2.push("<!--[-1-->");
      $$renderer2.push(`<span>Continue to Verification</span> `);
      Chevron_right($$renderer2, { class: "size-3.5" });
      $$renderer2.push(`<!---->`);
    }
    $$renderer2.push(`<!--]--></button></div></div>`);
    bind_props($$props, {
      sections,
      activeSectionIndex,
      selectedSectionIndices,
      documentFields,
      activeDocumentFieldId,
      declaredInventory,
      previewSurface,
      plottingConfig
    });
  });
}
export {
  StudioBubblePanel as default
};
