import { e as escape_html, c as attr_class, b as attr, a as ensure_array_like, d as derived, o as onDestroy, l as bind_props, A as clsx, n as newIdempotencyKey, m as csrfHeaders } from "../app.js";
import { O as OmrRegionCrop, a as OmrDiagnosticEvidencePanel, n as normalizeItem } from "./OmrDiagnosticEvidencePanel-CvO7kqrK.js";
import { A as Arrow_left } from "./arrow-left-DbWnQR3t.js";
import { C as Camera } from "./camera-C09Eyogi.js";
import { F as Flask_conical } from "./flask-conical-CPKNi8ld.js";
import { C as Circle_check } from "./circle-check-C59ZS7Zv.js";
import { S as Shield_check } from "./shield-check-CLSg-ako.js";
import { A as Arrow_right } from "./arrow-right-CzynHiIo.js";
import { C as Clipboard } from "./clipboard-BXuTlHdz.js";
import { L as Loader_circle } from "./loader-circle-_UEl10Lx.js";
import { D as Dialog, a as Dialog_content, b as Dialog_header, c as Dialog_title, d as Dialog_description } from "./dialog-description-D12L0QA9.js";
import { D as Dialog_footer } from "./dialog-footer-kNnwKT2h.js";
import "clsx";
import { L as Link } from "./Link-B7ks7rBK.js";
import { C as Circle_question_mark } from "./circle-question-mark-DY-RU614.js";
import { S as Settings_2 } from "./settings-2-B2lTvmrM.js";
import { E as Ellipsis } from "./ellipsis-CPRDNN0k.js";
import { M as Maximize_2 } from "./maximize-2-u72Ahym6.js";
import { L as List } from "./list-DlzkoO4B.js";
import { L as Layout_grid } from "./layout-grid-CXJfSBqV.js";
import { F as File_image } from "./file-image-BGlN21-8.js";
import { C as Circle_alert } from "./circle-alert-jWXJ7oC0.js";
import { C as Check } from "./check-DK45Y9SJ.js";
function OmrCaptureStage($$renderer, $$props) {
  $$renderer.component(($$renderer2) => {
    let {
      captureContent,
      activeStageView = "live",
      evidencePage = null,
      activeSheet = null,
      sheets = [],
      activeSheetId = null
    } = $$props;
    let canvasMode = "markings";
    let activeAptitudeArea = "all";
    const evidenceRecord = derived(() => evidencePage !== null && typeof evidencePage === "object" ? evidencePage : {});
    const evidenceGroups = derived(() => Array.isArray(evidenceRecord().groups) ? evidenceRecord().groups : []);
    const aptitudeAreas = derived(() => evidenceGroups().filter((group) => group.group_type === "aptitude_area" || group.area_key !== null && group.area_key !== void 0));
    const evidenceItems = derived(() => Array.isArray(evidenceRecord().items) ? evidenceRecord().items : []);
    const visibleEvidenceItems = derived(
      () => evidenceItems()
    );
    const focusItem = derived(() => visibleEvidenceItems()[0] ?? evidenceItems()[0]);
    const assetUrls = derived(() => evidenceRecord().lazy_asset_urls && typeof evidenceRecord().lazy_asset_urls === "object" ? evidenceRecord().lazy_asset_urls : {});
    const evidenceImageUrl = derived(
      () => assetUrls().overlay ?? assetUrls().aligned ?? assetUrls().input ?? null
    );
    function sheetLabel(sheet) {
      return String(sheet.detected_applicant_number ?? sheet.client_capture_id ?? `Sheet #${sheet.id}`);
    }
    $$renderer2.push(`<section class="h-full overflow-hidden flex flex-col justify-between bg-slate-950 relative select-none rounded-xl border border-border/60" data-omr-stage="capture" aria-label="OMR capture">`);
    if (activeStageView === "review") {
      $$renderer2.push("<!--[0-->");
      $$renderer2.push(`<header class="h-12 shrink-0 px-4 border-b border-slate-800 bg-slate-900/60 flex items-center justify-between font-mono text-xs z-10"><div class="flex min-w-0 items-center gap-2"><button type="button" class="inline-flex items-center gap-1.5 text-xs text-slate-300 hover:text-white px-2.5 py-1 rounded-lg border border-slate-800 bg-slate-950 hover:bg-slate-900 transition-colors">`);
      Arrow_left($$renderer2, { class: "size-3.5 text-emerald-400", "aria-hidden": "true" });
      $$renderer2.push(`<!----> <span>Back to Camera</span></button> <span class="text-slate-700">|</span> <span class="font-bold text-slate-100 font-sans text-xs truncate">${escape_html(activeSheet ? activeSheet.detected_applicant_number ?? activeSheet.client_capture_id ?? `Sheet #${activeSheet.id}` : "Sheet Inspection")}</span></div> <div class="flex items-center gap-1 bg-slate-950 p-1 rounded-lg border border-slate-800 text-[11px] font-mono"><button type="button"${attr_class(`px-2 py-0.5 rounded font-bold ${"text-slate-400 hover:text-slate-200"}`)}${attr("aria-pressed", canvasMode === "raw")}>Raw</button> <button type="button"${attr_class(`px-2 py-0.5 rounded font-bold ${"text-slate-400 hover:text-slate-200"}`)}${attr("aria-pressed", canvasMode === "stencil")}>Stencil</button> <button type="button"${attr_class(`px-2 py-0.5 rounded font-bold ${"bg-slate-800 text-foreground"}`)}${attr("aria-pressed", canvasMode === "markings")}>Markings</button></div></header> `);
      if (aptitudeAreas().length > 0) {
        $$renderer2.push("<!--[0-->");
        $$renderer2.push(`<nav class="shrink-0 flex items-center gap-1 overflow-x-auto border-b border-slate-800 bg-slate-900/30 px-3 py-1.5 font-mono text-[11px]" aria-label="Aptitude areas"><button type="button"${attr_class(`shrink-0 rounded-md px-2.5 py-1 ${"bg-slate-800 text-slate-100"}`)}${attr("aria-pressed", activeAptitudeArea === "all")}>All areas</button> <!--[-->`);
        const each_array = ensure_array_like(aptitudeAreas());
        for (let $$index = 0, $$length = each_array.length; $$index < $$length; $$index++) {
          let area = each_array[$$index];
          const areaKey = area.area_key ?? area.key;
          $$renderer2.push(`<button type="button"${attr_class(`shrink-0 rounded-md px-2.5 py-1 ${activeAptitudeArea === areaKey ? "bg-emerald-500/20 text-emerald-300" : "text-slate-400 hover:text-slate-200"}`)}${attr("aria-pressed", activeAptitudeArea === areaKey)}>${escape_html(area.label ?? areaKey)}</button>`);
        }
        $$renderer2.push(`<!--]--></nav>`);
      } else {
        $$renderer2.push("<!--[-1-->");
      }
      $$renderer2.push(`<!--]--> <div class="flex-1 min-h-0 relative flex items-center justify-center p-3 overflow-hidden bg-slate-950">`);
      if (evidenceImageUrl()) {
        $$renderer2.push("<!--[0-->");
        $$renderer2.push(`<div class="grid h-full min-h-0 w-full grid-cols-1 gap-3 overflow-hidden md:grid-cols-[minmax(0,1.2fr)_minmax(0,0.8fr)]"><div class="flex min-h-0 items-center justify-center overflow-hidden rounded-lg border border-slate-800 bg-slate-900/60 p-2"><img${attr("src", evidenceImageUrl())} alt="Captured answer sheet evidence" class="max-h-full max-w-full object-contain"/></div> `);
        if (focusItem()) {
          $$renderer2.push("<!--[0-->");
          $$renderer2.push(`<div class="flex min-h-0 flex-col justify-center overflow-hidden rounded-lg border border-amber-400/40 bg-slate-900/60 p-3"><div class="mb-2 flex items-center justify-between gap-2 font-mono text-[10px] text-amber-300"><span class="truncate">${escape_html(focusItem().area_key ?? "Sheet")} · ${escape_html(focusItem().label ?? `Question ${focusItem().question_no ?? ""}`)}</span> <span class="shrink-0 rounded bg-amber-400/10 px-1.5 py-0.5">FOCUSED CROP</span></div> <div class="min-h-0 overflow-hidden">`);
          OmrRegionCrop($$renderer2, {
            item: focusItem(),
            imageUrl: assetUrls().aligned ?? evidenceImageUrl(),
            region: focusItem().crop_rect ?? focusItem().region,
            options: focusItem().options ?? [],
            mode: canvasMode,
            alt: `Focused evidence for ${focusItem().label ?? focusItem().question_no ?? "selected item"}`,
            title: "Focused answer area",
            width: 360,
            id: "workbench-document-viewer-crop"
          });
          $$renderer2.push(`<!----></div></div>`);
        } else {
          $$renderer2.push("<!--[-1-->");
        }
        $$renderer2.push(`<!--]--></div>`);
      } else {
        $$renderer2.push("<!--[-1-->");
        $$renderer2.push(`<div class="text-center font-mono text-xs text-slate-500">Evidence image is unavailable for this sheet.</div>`);
      }
      $$renderer2.push(`<!--]--></div> <footer class="p-2.5 border-t border-slate-800 bg-slate-900/60 font-mono text-xs z-10 shrink-0"><div class="mb-2 flex items-center justify-between text-slate-400"><span>Batch Sheets:</span> <span class="text-[10px]">${escape_html(sheets.length)} sheets</span></div> <div class="flex items-center gap-2 overflow-x-auto pb-0.5"><!--[-->`);
      const each_array_1 = ensure_array_like(sheets);
      for (let $$index_1 = 0, $$length = each_array_1.length; $$index_1 < $$length; $$index_1++) {
        let sheet = each_array_1[$$index_1];
        $$renderer2.push(`<button type="button"${attr_class(`inline-flex shrink-0 items-center gap-1.5 rounded-lg border px-2.5 py-1.5 font-mono text-[11px] transition-colors ${sheet.id === activeSheetId ? "border-amber-400 bg-amber-400/10 text-amber-300" : "border-slate-800 bg-slate-950 text-slate-400 hover:text-slate-200"}`)}${attr("aria-pressed", sheet.id === activeSheetId)}><span>#${escape_html(sheet.id)} ${escape_html(sheetLabel(sheet))}</span> `);
        if (sheet.id === activeSheetId) {
          $$renderer2.push("<!--[0-->");
          $$renderer2.push(`<span class="size-1.5 rounded-full bg-amber-400" aria-label="Selected"></span>`);
        } else if (sheet.reviewed_at || sheet.status === "reviewed") {
          $$renderer2.push("<!--[1-->");
          $$renderer2.push(`<span class="text-emerald-400" aria-label="Reviewed">✓</span>`);
        } else {
          $$renderer2.push("<!--[-1-->");
        }
        $$renderer2.push(`<!--]--></button>`);
      }
      $$renderer2.push(`<!--]--></div></footer>`);
    } else {
      $$renderer2.push("<!--[-1-->");
      $$renderer2.push(`<div class="flex-1 min-h-0 relative flex flex-col overflow-hidden">`);
      if (activeStageView === "gallery") {
        $$renderer2.push("<!--[0-->");
        $$renderer2.push(`<div class="flex h-full min-w-0 w-full flex-col gap-3 overflow-hidden p-3 font-mono text-xs"><div class="flex items-center justify-between gap-3 border-b border-border/60 pb-2"><span class="font-sans font-bold text-sm text-foreground">Scans Gallery</span> <button type="button" class="border-border bg-card text-foreground hover:bg-muted inline-flex shrink-0 items-center gap-1.5 rounded-lg border px-3 py-1.5 font-bold">`);
        Camera($$renderer2, { class: "size-3.5", "aria-hidden": "true" });
        $$renderer2.push(`<!----> Return to Camera</button></div> <div class="grid min-h-0 flex-1 grid-cols-2 gap-3 overflow-y-auto p-1 sm:grid-cols-4"><!--[-->`);
        const each_array_2 = ensure_array_like(sheets);
        for (let $$index_2 = 0, $$length = each_array_2.length; $$index_2 < $$length; $$index_2++) {
          let sheet = each_array_2[$$index_2];
          const thumbUrl = sheet.lazy_asset_urls?.input ?? sheet.asset_urls?.thumbnail ?? sheet.asset_urls?.input ?? null;
          $$renderer2.push(`<button type="button"${attr_class("group min-w-0 rounded-lg p-1 text-left transition hover:bg-muted/50 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-primary border border-border/60 bg-card", void 0, {
            "ring-2": sheet.id === activeSheetId,
            "ring-primary": sheet.id === activeSheetId
          })}${attr("aria-label", `Open sheet ${sheetLabel(sheet)}`)}><div class="aspect-[3/4] rounded-lg border border-border/40 bg-slate-900/60 overflow-hidden relative flex items-center justify-center">`);
          if (thumbUrl) {
            $$renderer2.push("<!--[0-->");
            $$renderer2.push(`<img${attr("src", thumbUrl)}${attr("alt", `Scan #${sheet.id}`)} class="w-full h-full object-cover" loading="lazy"/>`);
          } else {
            $$renderer2.push("<!--[-1-->");
            $$renderer2.push(`<div class="p-2 text-center text-[10px] text-muted-foreground font-mono">Scan #${escape_html(sheet.id)}</div>`);
          }
          $$renderer2.push(`<!--]--> <div class="absolute top-1 right-1"><span${attr_class(`px-1.5 py-0.5 rounded text-[9px] font-bold ${sheet.recovery ? "bg-amber-500/20 text-amber-400 border border-amber-500/40" : "bg-emerald-500/20 text-emerald-400 border border-emerald-500/40"}`)}>${escape_html(sheet.recovery ? "Needs Check" : "Clean")}</span></div></div> <div class="px-1 pt-2"><div class="text-foreground truncate font-sans text-xs font-bold">${escape_html(sheetLabel(sheet))}</div> <div${attr_class("text-[11px]", void 0, {
            "text-amber-500": sheet.recovery,
            "font-semibold": sheet.recovery,
            "text-muted-foreground": !sheet.recovery
          })}>${escape_html(sheet.recovery ? "Needs Check" : "Clean")}</div></div></button>`);
        }
        $$renderer2.push(`<!--]--></div> <div class="border-border/70 text-muted-foreground flex items-center justify-between border-t pt-3 text-xs"><span>Showing ${escape_html(sheets.length)} sheet${escape_html(sheets.length === 1 ? "" : "s")}</span> <button type="button" class="text-primary hover:text-primary/80 font-sans font-bold">Ingest New Sheet</button></div></div>`);
      } else if (captureContent) {
        $$renderer2.push("<!--[1-->");
        captureContent($$renderer2);
        $$renderer2.push(`<!---->`);
      } else {
        $$renderer2.push("<!--[-1-->");
      }
      $$renderer2.push(`<!--]--></div>`);
    }
    $$renderer2.push(`<!--]--></section>`);
  });
}
function OmrCompletionBar($$renderer, $$props) {
  $$renderer.component(($$renderer2) => {
    let { projection, onComplete = void 0 } = $$props;
    let submitting = false;
    function hasCommand(command2) {
      return projection.commands.includes(command2);
    }
    function text(value, fallback) {
      return typeof value === "string" && value.trim() !== "" ? value.trim() : fallback;
    }
    function number(value) {
      if (typeof value !== "number" || !Number.isFinite(value)) {
        return null;
      }
      return Math.max(0, Math.floor(value));
    }
    function completionCommand() {
      if (projection.execution_kind === "production") {
        return hasCommand("production_complete") ? "production_complete" : null;
      }
      if (hasCommand("diagnostic_complete")) {
        return "diagnostic_complete";
      }
      if (hasCommand("diagnostic_compare")) {
        return "diagnostic_compare";
      }
      return null;
    }
    function commandLabel(command2) {
      if (command2 === "production_complete") {
        return "Confirm production completion";
      }
      if (command2 === "diagnostic_compare") {
        return "Compare diagnostic outcome";
      }
      if (command2 === "diagnostic_complete") {
        return "Record diagnostic outcome";
      }
      return "Waiting for review";
    }
    function completionTitle(value, diagnostic2) {
      if (diagnostic2) {
        return text(value?.label, "Projected diagnostic outcome");
      }
      return text(value?.label, "Production completion");
    }
    function completionMessage(value, diagnostic2) {
      if (diagnostic2) {
        if (value?.outcome === "blocked") {
          return "The diagnostic outcome is blocked and remains isolated from production records.";
        }
        return "This projected outcome is for diagnostic evidence only; it does not write production data or satisfy physical qualification.";
      }
      if (value !== null) {
        return "The production run has an immutable completion result from the server.";
      }
      return "Review the queued sheets and resolve every blocking issue before confirming this production run.";
    }
    const diagnostic = derived(() => projection.execution_kind === "diagnostic");
    const completion = derived(() => projection.completion);
    const command = derived(completionCommand);
    const completedCaptureCount = derived(() => number(completion()?.capture_count));
    const completionStatus = derived(() => completion() === null ? "ready" : text(completion().status, completion().outcome ?? "recorded"));
    const official = derived(() => !diagnostic() && completion() !== null && completion().official === true);
    const title = derived(() => completionTitle(completion(), diagnostic()));
    const message = derived(() => completionMessage(completion(), diagnostic()));
    $$renderer2.push(`<section class="border-border bg-card min-w-0 overflow-hidden rounded-xl border shadow-xs" data-omr-completion-bar=""${attr("data-omr-completion-kind", diagnostic() ? "diagnostic" : "production")} aria-labelledby="omr-completion-title"><div class="flex flex-col gap-4 px-4 py-4 sm:flex-row sm:items-center sm:justify-between sm:px-5"><div class="flex min-w-0 items-start gap-3"><div${attr_class(`mt-0.5 flex size-9 shrink-0 items-center justify-center rounded-full ${diagnostic() ? "bg-amber-500/10 text-amber-700 dark:text-amber-300" : "bg-emerald-500/10 text-emerald-700 dark:text-emerald-300"}`)} aria-hidden="true">`);
    if (diagnostic()) {
      $$renderer2.push("<!--[0-->");
      Flask_conical($$renderer2, { class: "size-4" });
    } else if (completion() !== null) {
      $$renderer2.push("<!--[1-->");
      Circle_check($$renderer2, { class: "size-4" });
    } else {
      $$renderer2.push("<!--[-1-->");
      Shield_check($$renderer2, { class: "size-4" });
    }
    $$renderer2.push(`<!--]--></div> <div class="min-w-0"><div class="flex flex-wrap items-center gap-2"><h2 id="omr-completion-title" class="text-base font-semibold tracking-tight">${escape_html(title())}</h2> <span${attr_class(`inline-flex min-h-6 items-center rounded-full border px-2 py-0.5 text-[11px] font-semibold ${diagnostic() ? "border-amber-500/40 bg-amber-500/10 text-amber-800 dark:text-amber-200" : "border-emerald-500/40 bg-emerald-500/10 text-emerald-800 dark:text-emerald-200"}`)}${attr("data-omr-completion-status", completionStatus())}>`);
    if (diagnostic()) {
      $$renderer2.push("<!--[0-->");
      $$renderer2.push(`Projected / unofficial`);
    } else if (official()) {
      $$renderer2.push("<!--[1-->");
      $$renderer2.push(`Official`);
    } else if (completion() !== null) {
      $$renderer2.push("<!--[2-->");
      $$renderer2.push(`Recorded`);
    } else {
      $$renderer2.push("<!--[-1-->");
      $$renderer2.push(`Ready`);
    }
    $$renderer2.push(`<!--]--></span></div> <p class="text-muted-foreground mt-1 max-w-2xl text-sm leading-relaxed" data-omr-completion-message="">${escape_html(message())}</p> `);
    if (completedCaptureCount() !== null) {
      $$renderer2.push("<!--[0-->");
      $$renderer2.push(`<p class="text-muted-foreground mt-2 text-xs tabular-nums" data-omr-completion-captures="">${escape_html(completedCaptureCount())} capture${escape_html(completedCaptureCount() === 1 ? "" : "s")} included</p>`);
    } else {
      $$renderer2.push("<!--[-1-->");
    }
    $$renderer2.push(`<!--]--></div></div> `);
    if (command() && onComplete && completion() === null) {
      $$renderer2.push("<!--[0-->");
      $$renderer2.push(`<button type="button" class="bg-primary text-primary-foreground hover:bg-primary/90 focus-visible:ring-ring inline-flex min-h-10 shrink-0 items-center justify-center gap-2 rounded-md px-3.5 py-2 text-sm font-semibold focus-visible:ring-2 focus-visible:outline-none disabled:pointer-events-none disabled:opacity-50"${attr("data-omr-completion-command", command())}${attr("disabled", submitting, true)}>`);
      {
        $$renderer2.push("<!--[-1-->");
        $$renderer2.push(`${escape_html(commandLabel(command()))} `);
        Arrow_right($$renderer2, { class: "size-4", "aria-hidden": "true" });
        $$renderer2.push(`<!---->`);
      }
      $$renderer2.push(`<!--]--></button>`);
    } else if (completion() !== null) {
      $$renderer2.push("<!--[1-->");
      $$renderer2.push(`<span class="text-muted-foreground inline-flex min-h-10 shrink-0 items-center gap-2 text-xs font-medium" data-omr-completion-immutable="">`);
      Circle_check($$renderer2, {
        class: "size-4 text-emerald-600 dark:text-emerald-300",
        "aria-hidden": "true"
      });
      $$renderer2.push(`<!----> Immutable result</span>`);
    } else {
      $$renderer2.push("<!--[-1-->");
      $$renderer2.push(`<span class="text-muted-foreground inline-flex min-h-10 shrink-0 items-center gap-2 text-xs font-medium" data-omr-completion-waiting="">`);
      Shield_check($$renderer2, { class: "size-4", "aria-hidden": "true" });
      $$renderer2.push(`<!----> Review required</span>`);
    }
    $$renderer2.push(`<!--]--></div></section>`);
  });
}
function OmrRecoveryCard($$renderer, $$props) {
  $$renderer.component(($$renderer2) => {
    let {
      recovery,
      onAction = null,
      busy = false,
      idPrefix = "omr-recovery",
      className = ""
    } = $$props;
    let pendingAction = null;
    function asRecord(value) {
      if (value === null || typeof value !== "object" || Array.isArray(value)) {
        return {};
      }
      return value;
    }
    function textValue(value) {
      if (typeof value !== "string") return null;
      const valueText = value.trim();
      return valueText === "" ? null : valueText;
    }
    function fallbackText(value, fallback) {
      return textValue(value) ?? fallback;
    }
    function titleCase(value) {
      return value.replace(/[_-]+/g, " ").replace(/\b\w/g, (character) => character.toUpperCase());
    }
    function actionLabel(action, explicit) {
      const serverLabel = textValue(explicit);
      if (serverLabel !== null) return serverLabel;
      if (action === null) return "";
      return titleCase(action);
    }
    function technicalText(value) {
      if (typeof value === "string") return textValue(value);
      if (value === null || value === void 0 || typeof value === "number" || typeof value === "boolean") {
        return value === null || value === void 0 ? null : String(value);
      }
      try {
        return JSON.stringify(value, null, 2);
      } catch {
        return null;
      }
    }
    function safeId(value) {
      const raw = textValue(value) ?? "card";
      return raw.replace(/[^A-Za-z0-9_-]+/g, "-").replace(/^-+|-+$/g, "") || "card";
    }
    function actionFrom(value) {
      return textValue(value);
    }
    const source = derived(() => asRecord(recovery));
    const title = derived(() => fallbackText(source().title, "Review required"));
    const explanation = derived(() => fallbackText(source().explanation, "This scan needs staff review before scoring can continue."));
    const blocked = derived(() => source().blocked === true || source().blocking === true);
    const owner = derived(() => fallbackText(source().owner, "Responsible staff"));
    const supportCode = derived(() => textValue(source().support_code) ?? textValue(source().code));
    const technicalDetails = derived(() => technicalText(source().technical_detail));
    const primaryAction = derived(() => actionFrom(source().primary_action));
    const secondaryAction = derived(() => actionFrom(source().secondary_action));
    const primaryLabel = derived(() => actionLabel(primaryAction(), source().primary_label ?? source().primary_text));
    const secondaryLabel = derived(() => actionLabel(secondaryAction(), source().secondary_label ?? source().secondary_text));
    const cardId = derived(() => `${safeId(idPrefix)}-${safeId(source().capture_id ?? supportCode() ?? source().code)}`);
    const isBusy = derived(() => busy || pendingAction !== null);
    $$renderer2.push(`<article${attr_class(`omr-recovery-card border-border bg-card min-w-0 rounded-xl border p-4 shadow-xs sm:p-5 ${className}`)} data-omr-recovery-card=""${attr("data-omr-recovery-blocked", blocked())}${attr("aria-labelledby", `${cardId()}-title`)}${attr("aria-describedby", `${cardId()}-explanation`)}${attr("role", blocked() ? "alert" : "region")}><div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between"><div class="min-w-0"><h3${attr("id", `${cardId()}-title`)} class="text-base font-semibold tracking-tight">${escape_html(title())}</h3> <p${attr("id", `${cardId()}-explanation`)} class="text-muted-foreground mt-1 text-sm leading-relaxed">${escape_html(explanation())}</p></div> <span${attr_class(`inline-flex min-h-7 w-fit shrink-0 items-center rounded-full border px-2.5 py-1 text-xs font-semibold ${blocked() ? "border-amber-500/40 bg-amber-500/10 text-amber-800 dark:text-amber-200" : "border-emerald-500/40 bg-emerald-500/10 text-emerald-800 dark:text-emerald-200"}`)} role="status" aria-live="polite">`);
    if (blocked()) {
      $$renderer2.push("<!--[0-->");
      $$renderer2.push(`Scoring blocked`);
    } else {
      $$renderer2.push("<!--[-1-->");
      $$renderer2.push(`Ready`);
    }
    $$renderer2.push(`<!--]--></span></div> <dl class="border-border/70 mt-4 grid gap-3 border-t pt-3 text-sm sm:grid-cols-2"><div class="min-w-0"><dt class="text-muted-foreground text-xs">Responsible owner</dt> <dd class="mt-0.5 truncate font-medium"${attr("title", owner())}>${escape_html(owner())}</dd></div> <div class="min-w-0"><dt class="text-muted-foreground text-xs">Support code</dt> <dd class="mt-0.5 flex min-w-0 items-center gap-2">`);
    if (supportCode()) {
      $$renderer2.push("<!--[0-->");
      $$renderer2.push(`<code class="min-w-0 flex-1 truncate font-mono text-xs font-semibold select-all"${attr("title", supportCode())}>${escape_html(supportCode())}</code> <button type="button" class="border-border bg-background hover:bg-muted focus-visible:ring-ring inline-flex min-h-9 shrink-0 items-center gap-1.5 rounded-md border px-2.5 text-xs font-semibold focus-visible:ring-2 focus-visible:outline-none"${attr("aria-label", `Copy support code ${supportCode()}`)}>`);
      {
        $$renderer2.push("<!--[-1-->");
        Clipboard($$renderer2, { class: "size-3.5", "aria-hidden": "true" });
        $$renderer2.push(`<!----> <span>Copy</span>`);
      }
      $$renderer2.push(`<!--]--></button>`);
    } else {
      $$renderer2.push("<!--[-1-->");
      $$renderer2.push(`<span class="text-muted-foreground">Unavailable</span>`);
    }
    $$renderer2.push(`<!--]--></dd></div></dl> `);
    if (primaryAction() || secondaryAction()) {
      $$renderer2.push("<!--[0-->");
      $$renderer2.push(`<div class="mt-4 flex flex-wrap gap-2" data-omr-recovery-actions="">`);
      if (primaryAction()) {
        $$renderer2.push("<!--[0-->");
        $$renderer2.push(`<button type="button" class="bg-primary text-primary-foreground hover:bg-primary/90 focus-visible:ring-ring inline-flex min-h-10 items-center justify-center gap-2 rounded-md px-3 py-2 text-sm font-semibold focus-visible:ring-2 focus-visible:outline-none disabled:cursor-not-allowed disabled:opacity-50"${attr("disabled", isBusy() || !onAction, true)}${attr("aria-busy", pendingAction === primaryAction())}>`);
        if (pendingAction === primaryAction()) {
          $$renderer2.push("<!--[0-->");
          Loader_circle($$renderer2, { class: "size-4 animate-spin", "aria-hidden": "true" });
        } else {
          $$renderer2.push("<!--[-1-->");
        }
        $$renderer2.push(`<!--]--> <span>${escape_html(primaryLabel())}</span></button>`);
      } else {
        $$renderer2.push("<!--[-1-->");
      }
      $$renderer2.push(`<!--]--> `);
      if (secondaryAction()) {
        $$renderer2.push("<!--[0-->");
        $$renderer2.push(`<button type="button" class="border-border bg-background hover:bg-muted focus-visible:ring-ring inline-flex min-h-10 items-center justify-center gap-2 rounded-md border px-3 py-2 text-sm font-semibold focus-visible:ring-2 focus-visible:outline-none disabled:cursor-not-allowed disabled:opacity-50"${attr("disabled", isBusy() || !onAction, true)}${attr("aria-busy", pendingAction === secondaryAction())}>`);
        if (pendingAction === secondaryAction()) {
          $$renderer2.push("<!--[0-->");
          Loader_circle($$renderer2, { class: "size-4 animate-spin", "aria-hidden": "true" });
        } else {
          $$renderer2.push("<!--[-1-->");
        }
        $$renderer2.push(`<!--]--> <span>${escape_html(secondaryLabel())}</span></button>`);
      } else {
        $$renderer2.push("<!--[-1-->");
      }
      $$renderer2.push(`<!--]--></div>`);
    } else {
      $$renderer2.push("<!--[-1-->");
    }
    $$renderer2.push(`<!--]--> `);
    {
      $$renderer2.push("<!--[-1-->");
    }
    $$renderer2.push(`<!--]--> `);
    if (technicalDetails()) {
      $$renderer2.push("<!--[0-->");
      $$renderer2.push(`<details class="border-border/70 mt-4 border-t pt-3" data-omr-recovery-technical=""><summary class="text-muted-foreground hover:text-foreground focus-visible:ring-ring inline-flex min-h-9 cursor-pointer items-center rounded-md text-xs font-semibold underline-offset-2 hover:underline focus-visible:ring-2 focus-visible:outline-none">Technical details</summary> <pre class="border-border/70 bg-muted/30 mt-2 max-h-48 overflow-auto rounded-md border p-3 font-mono text-[11px] leading-relaxed break-words whitespace-pre-wrap">${escape_html(technicalDetails())}</pre></details>`);
    } else {
      $$renderer2.push("<!--[-1-->");
    }
    $$renderer2.push(`<!--]--></article>`);
  });
}
function OmrEvidenceDock($$renderer, $$props) {
  $$renderer.component(($$renderer2) => {
    let {
      page = null,
      labels = {},
      returnUrl = null,
      loading = false,
      error = null,
      onRetry = null,
      onPageChange = null,
      onPerPageChange = null,
      onGroupChange = null,
      onFilterChange = null,
      onRowOpen = null,
      onTabChange = null,
      onViewChange = null,
      initialTab = "aligned",
      mobileRailExpanded = false,
      contextSummary = null,
      idPrefix = "omr-evidence",
      reviewable = false,
      blind = false,
      reviewReasons = [],
      onDirtyChange = null,
      onDirtyNavigation = null,
      onSaveDecision = null,
      onSaveAndNext = null,
      onClose = null,
      placement = "embedded"
    } = $$props;
    let previousFocus = null;
    const dockClasses = derived(() => {
      if (placement === "focus") {
        return "fixed inset-0 z-50 flex min-h-0 items-stretch justify-center overflow-y-auto bg-slate-950/65 p-0 backdrop-blur-sm sm:items-center sm:p-4 md:p-6";
      }
      if (placement === "mobile") {
        return "sticky bottom-0 z-30 max-h-[52dvh] min-h-0 overflow-y-auto overscroll-contain rounded-t-xl border border-b-0 border-border bg-background p-2 shadow-2xl sm:p-3 lg:static lg:max-h-none lg:overflow-visible lg:rounded-xl lg:border-b lg:shadow-sm";
      }
      return "min-w-0";
    });
    const panelClasses = derived(() => {
      if (placement === "focus") {
        return "min-h-0 w-full max-w-[1600px] rounded-none border border-border bg-background p-2 shadow-2xl sm:max-h-[min(94dvh,1080px)] sm:overflow-y-auto sm:rounded-xl sm:p-4";
      }
      if (placement === "mobile") {
        return "min-w-0";
      }
      return "min-w-0";
    });
    const isDialog = derived(() => placement === "focus");
    function restoreFocus() {
      const opener = previousFocus;
      previousFocus = null;
      if (typeof document === "undefined" || typeof HTMLElement === "undefined") {
        return;
      }
      if (opener instanceof HTMLElement && document.contains(opener)) {
        opener.focus();
      }
    }
    onDestroy(restoreFocus);
    let $$settled = true;
    let $$inner_renderer;
    function $$render_inner($$renderer3) {
      $$renderer3.push(`<div${attr_class(`omr-evidence-dock ${dockClasses()}`, "svelte-1avcotu")} data-omr-evidence-dock=""${attr("data-omr-evidence-placement", placement)}${attr("role", isDialog() ? "dialog" : "region")}${attr("aria-modal", isDialog() ? "true" : void 0)}${attr("aria-label", isDialog() ? "Focused OMR evidence review" : "OMR evidence review")} tabindex="-1"><div${attr_class(clsx(panelClasses()), "svelte-1avcotu")} data-omr-evidence-dock-panel="">`);
      OmrDiagnosticEvidencePanel($$renderer3, {
        page,
        labels,
        returnUrl,
        loading,
        error,
        onRetry,
        onPageChange,
        onPerPageChange,
        onGroupChange,
        onFilterChange,
        onRowOpen,
        onTabChange,
        onViewChange,
        initialTab,
        contextSummary,
        reviewable,
        blind,
        reviewReasons,
        onDirtyChange,
        onDirtyNavigation,
        onSaveDecision,
        onSaveAndNext,
        idPrefix,
        get mobileRailExpanded() {
          return mobileRailExpanded;
        },
        set mobileRailExpanded($$value) {
          mobileRailExpanded = $$value;
          $$settled = false;
        }
      });
      $$renderer3.push(`<!----></div></div>`);
    }
    do {
      $$settled = true;
      $$inner_renderer = $$renderer2.copy();
      $$render_inner($$inner_renderer);
    } while (!$$settled);
    $$renderer2.subsume($$inner_renderer);
    bind_props($$props, { mobileRailExpanded });
  });
}
function OmrReviewStage($$renderer, $$props) {
  $$renderer.component(($$renderer2) => {
    let {
      projection,
      evidencePage = void 0,
      placement = "embedded",
      labels = {},
      returnUrl = null,
      loading = false,
      error = null,
      onRetry = null,
      onPageChange = null,
      onPerPageChange = null,
      onGroupChange = null,
      onFilterChange = null,
      onRowOpen = null,
      onTabChange = null,
      onViewChange = null,
      initialTab = "aligned",
      mobileRailExpanded = false,
      contextSummary = null,
      idPrefix = "omr-review",
      reviewable = false,
      blind = false,
      reviewReasons = [],
      onDirtyChange = null,
      onDirtyNavigation = null,
      onSaveDecision = null,
      onSaveAndNext = null,
      onRecoveryAction = null,
      recoveryBusy = false,
      sheets = [],
      activeSheetId = null,
      onSelectSheet = null
    } = $$props;
    function projectedRecoveryItems(value) {
      if (!Array.isArray(value)) return [];
      return value.filter((item) => item !== null && typeof item === "object" && !Array.isArray(item));
    }
    const recoveryItems = derived(() => {
      const items = projectedRecoveryItems(projection?.recovery?.items);
      if (items.length > 0) return items;
      return (projection?.sheets ?? []).map((sheet) => sheet.recovery).filter((item) => item !== null && item !== void 0 && typeof item === "object" && !Array.isArray(item));
    });
    const attentionItems = derived(() => {
      const items = projection?.recovery?.needs_attention;
      return Array.isArray(items) ? items : [];
    });
    const resolvedEvidencePage = derived(() => evidencePage ?? projection?.evidence);
    const evidencePlacement = derived(() => placement);
    const reviewLayoutClasses = derived(() => placement === "mobile" ? "flex min-w-0 flex-col gap-3" : "grid min-w-0 gap-4 lg:grid-cols-[minmax(0,42fr)_minmax(0,58fr)] lg:items-start");
    const recoveryColumnClasses = derived(() => placement === "mobile" ? "order-1 min-w-0" : "order-1 min-w-0");
    const evidenceColumnClasses = derived(() => placement === "mobile" ? "order-2 min-w-0" : "order-2 min-w-0");
    let $$settled = true;
    let $$inner_renderer;
    function $$render_inner($$renderer3) {
      $$renderer3.push(`<section class="border-border bg-card min-w-0 overflow-hidden rounded-xl border shadow-xs" data-omr-review-stage="" aria-labelledby="omr-review-stage-title"><header class="border-border/70 flex flex-col gap-2 border-b px-4 py-4 sm:px-5"><div class="text-primary text-xs font-semibold tracking-[0.12em] uppercase">OMR workbench</div> <h2 id="omr-review-stage-title" class="text-lg font-semibold tracking-tight">Evidence &amp; review</h2> <p class="text-muted-foreground max-w-3xl text-sm leading-relaxed">Review the server-projected evidence and resolve any blocked sheets
            before completion.</p></header> <div class="min-w-0 space-y-4 p-3 sm:p-4"><div${attr_class(clsx(reviewLayoutClasses()))} data-omr-review-layout=""><aside${attr_class(clsx(recoveryColumnClasses()))} data-omr-review-recovery="" aria-label="OMR recovery">`);
      if (attentionItems().length > 0) {
        $$renderer3.push("<!--[0-->");
        $$renderer3.push(`<div class="border-border/70 bg-muted/20 text-muted-foreground mb-3 rounded-lg border px-3 py-2.5 text-xs leading-relaxed" data-omr-needs-attention="" role="status" aria-live="polite"><p class="text-foreground font-semibold">Needs attention</p> <ul class="mt-1 space-y-1"><!--[-->`);
        const each_array = ensure_array_like(attentionItems());
        for (let index = 0, $$length = each_array.length; index < $$length; index++) {
          let attention = each_array[index];
          $$renderer3.push(`<li>${escape_html(attention.owner ?? "Assigned owner")} · ${escape_html(attention.count ?? 0)} item${escape_html(attention.count === 1 ? "" : "s")}</li>`);
        }
        $$renderer3.push(`<!--]--></ul></div>`);
      } else {
        $$renderer3.push("<!--[-1-->");
      }
      $$renderer3.push(`<!--]--> `);
      if (recoveryItems().length > 0) {
        $$renderer3.push("<!--[0-->");
        $$renderer3.push(`<div class="space-y-3" data-omr-recovery-list=""><!--[-->`);
        const each_array_1 = ensure_array_like(recoveryItems());
        for (let index = 0, $$length = each_array_1.length; index < $$length; index++) {
          let recovery = each_array_1[index];
          OmrRecoveryCard($$renderer3, {
            recovery,
            onAction: onRecoveryAction,
            busy: recoveryBusy,
            idPrefix: `${idPrefix}-recovery-${index}`
          });
        }
        $$renderer3.push(`<!--]--></div>`);
      } else if (attentionItems().length === 0) {
        $$renderer3.push("<!--[1-->");
        $$renderer3.push(`<div class="border-border/70 bg-muted/20 text-muted-foreground rounded-lg border border-dashed px-3 py-4 text-sm" data-omr-recovery-empty="">No recovery actions are currently projected.</div>`);
      } else {
        $$renderer3.push("<!--[-1-->");
      }
      $$renderer3.push(`<!--]--></aside> <div${attr_class(clsx(evidenceColumnClasses()))} data-omr-review-evidence="">`);
      OmrEvidenceDock($$renderer3, {
        page: resolvedEvidencePage(),
        labels,
        returnUrl,
        loading,
        error,
        onRetry,
        onPageChange,
        onPerPageChange,
        onGroupChange,
        onFilterChange,
        onRowOpen,
        onTabChange,
        onViewChange,
        initialTab,
        contextSummary,
        idPrefix: `${idPrefix}-evidence`,
        reviewable,
        blind,
        reviewReasons,
        onDirtyChange,
        onDirtyNavigation,
        onSaveDecision,
        onSaveAndNext,
        placement: evidencePlacement(),
        get mobileRailExpanded() {
          return mobileRailExpanded;
        },
        set mobileRailExpanded($$value) {
          mobileRailExpanded = $$value;
          $$settled = false;
        }
      });
      $$renderer3.push(`<!----></div></div> `);
      if (sheets && sheets.length > 0) {
        $$renderer3.push("<!--[0-->");
        $$renderer3.push(`<div class="border-border/70 border-t pt-3 font-mono text-xs" data-omr-review-reel=""><div class="text-muted-foreground mb-2 flex items-center justify-between text-xs"><span class="text-[10px] font-semibold tracking-wider uppercase">Batch Sheets:</span> <span class="text-[11px]">Click thumbnail to jump between sheets</span></div> <div class="flex items-center gap-2 overflow-x-auto pb-1.5" role="tablist" aria-label="Batch sheets reel"><!--[-->`);
        const each_array_2 = ensure_array_like(sheets);
        for (let $$index_2 = 0, $$length = each_array_2.length; $$index_2 < $$length; $$index_2++) {
          let sheet = each_array_2[$$index_2];
          $$renderer3.push(`<button type="button" role="tab"${attr("aria-selected", activeSheetId === sheet.id)}${attr_class(`flex shrink-0 items-center gap-2 rounded-lg border px-3 py-1.5 font-mono text-xs font-bold transition-all ${activeSheetId === sheet.id ? "border-primary bg-primary/10 text-primary ring-primary/40 ring-1" : "border-border/80 bg-background hover:bg-muted text-muted-foreground"}`)}${attr("data-omr-reel-sheet", sheet.id)}><span>#${escape_html(sheet.id)}
                                ${escape_html(sheet.detected_applicant_number ?? sheet.client_capture_id ?? "")}</span> `);
          if (sheet.recovery) {
            $$renderer3.push("<!--[0-->");
            $$renderer3.push(`<span class="size-2 rounded-full bg-amber-500" title="Needs Check"></span>`);
          } else {
            $$renderer3.push("<!--[-1-->");
            $$renderer3.push(`<span class="text-emerald-500 text-[10px] font-bold" title="Clean">✓</span>`);
          }
          $$renderer3.push(`<!--]--></button>`);
        }
        $$renderer3.push(`<!--]--></div></div>`);
      } else {
        $$renderer3.push("<!--[-1-->");
      }
      $$renderer3.push(`<!--]--></div></section>`);
    }
    do {
      $$settled = true;
      $$inner_renderer = $$renderer2.copy();
      $$render_inner($$inner_renderer);
    } while (!$$settled);
    $$renderer2.subsume($$inner_renderer);
    bind_props($$props, { mobileRailExpanded });
  });
}
function OmrShortcutsModal($$renderer, $$props) {
  $$renderer.component(($$renderer2) => {
    let { open = false, onClose } = $$props;
    const shortcuts = [
      {
        key: "Space",
        action: "Capture the current page",
        context: "Camera ready"
      },
      { key: "U", action: "Upload an image", context: "Camera view" },
      {
        key: "?",
        action: "Open keyboard shortcuts",
        context: "Console"
      },
      {
        key: "Esc",
        action: "Close a dialog or return to camera",
        context: "Review navigation preserves unsaved changes"
      }
    ];
    if (Dialog) {
      $$renderer2.push("<!--[-->");
      Dialog($$renderer2, {
        open,
        onOpenChange: (value) => {
          if (!value) onClose();
        },
        children: ($$renderer3) => {
          if (Dialog_content) {
            $$renderer3.push("<!--[-->");
            Dialog_content($$renderer3, {
              portalProps: {},
              showCloseButton: false,
              class: "bg-card text-foreground max-h-[calc(100dvh-2rem)] overflow-y-auto sm:max-w-lg",
              children: ($$renderer4) => {
                if (Dialog_header) {
                  $$renderer4.push("<!--[-->");
                  Dialog_header($$renderer4, {
                    class: "pr-8",
                    children: ($$renderer5) => {
                      if (Dialog_title) {
                        $$renderer5.push("<!--[-->");
                        Dialog_title($$renderer5, {
                          class: "text-base font-semibold",
                          children: ($$renderer6) => {
                            $$renderer6.push(`<!---->Keyboard shortcuts`);
                          },
                          $$slots: { default: true }
                        });
                        $$renderer5.push("<!--]-->");
                      } else {
                        $$renderer5.push("<!--[!-->");
                        $$renderer5.push("<!--]-->");
                      }
                      $$renderer5.push(` `);
                      if (Dialog_description) {
                        $$renderer5.push("<!--[-->");
                        Dialog_description($$renderer5, {
                          class: "text-muted-foreground text-sm",
                          children: ($$renderer6) => {
                            $$renderer6.push(`<!---->Shortcuts pause while editing a field or using a dialog.`);
                          },
                          $$slots: { default: true }
                        });
                        $$renderer5.push("<!--]-->");
                      } else {
                        $$renderer5.push("<!--[!-->");
                        $$renderer5.push("<!--]-->");
                      }
                    },
                    $$slots: { default: true }
                  });
                  $$renderer4.push("<!--]-->");
                } else {
                  $$renderer4.push("<!--[!-->");
                  $$renderer4.push("<!--]-->");
                }
                $$renderer4.push(` <dl class="divide-border divide-y"><!--[-->`);
                const each_array = ensure_array_like(shortcuts);
                for (let $$index = 0, $$length = each_array.length; $$index < $$length; $$index++) {
                  let shortcut = each_array[$$index];
                  $$renderer4.push(`<div class="flex items-start justify-between gap-4 py-3"><div class="min-w-0"><dt class="text-sm font-medium">${escape_html(shortcut.action)}</dt> <dd class="text-muted-foreground mt-1 text-xs">${escape_html(shortcut.context)}</dd></div> <kbd class="border-border bg-muted shrink-0 rounded border px-2 py-1 text-xs">${escape_html(shortcut.key)}</kbd></div>`);
                }
                $$renderer4.push(`<!--]--></dl> `);
                if (Dialog_footer) {
                  $$renderer4.push("<!--[-->");
                  Dialog_footer($$renderer4, {
                    class: "flex justify-end",
                    children: ($$renderer5) => {
                      $$renderer5.push(`<button type="button" class="bg-primary text-primary-foreground focus-visible:outline-ring rounded-md px-4 py-2 text-sm font-medium focus-visible:outline-2">Close</button>`);
                    },
                    $$slots: { default: true }
                  });
                  $$renderer4.push("<!--]-->");
                } else {
                  $$renderer4.push("<!--[!-->");
                  $$renderer4.push("<!--]-->");
                }
              },
              $$slots: { default: true }
            });
            $$renderer3.push("<!--]-->");
          } else {
            $$renderer3.push("<!--[!-->");
            $$renderer3.push("<!--]-->");
          }
        },
        $$slots: { default: true }
      });
      $$renderer2.push("<!--]-->");
    } else {
      $$renderer2.push("<!--[!-->");
      $$renderer2.push("<!--]-->");
    }
  });
}
function OmrWorkbenchHeader($$renderer, $$props) {
  $$renderer.component(($$renderer2) => {
    let {
      projection,
      sheetsCount = 0
    } = $$props;
    let menuOpen = false;
    function asRecord(value) {
      return value !== null && typeof value === "object" ? value : {};
    }
    function textValue(value) {
      if (typeof value !== "string" && typeof value !== "number") {
        return null;
      }
      const text = String(value).trim();
      return text === "" ? null : text;
    }
    const context = derived(() => asRecord(projection.context));
    const contextTitle = derived(() => textValue(context().title) ?? textValue(context().name) ?? textValue(context().label) ?? (projection.execution_kind === "diagnostic" ? "OMR Test Calibration" : "OMR scoring workbench"));
    const isDiagnostic = derived(() => projection.execution_kind === "diagnostic");
    const backHref = derived(() => isDiagnostic() ? textValue(context().materials_url) ?? textValue(context().back_url) ?? "/setup/exam-materials" : textValue(context().session_url) ?? textValue(context().back_url) ?? (textValue(context().session_id) ? `/grading/sessions/${encodeURIComponent(textValue(context().session_id) ?? "")}` : "/grading"));
    const completeCommand = derived(() => projection.commands.find((command) => command === "production_complete" || command === "diagnostic_compare" || command === "diagnostic_complete") ?? null);
    const backLabel = derived(() => isDiagnostic() ? "Materials" : "Session");
    $$renderer2.push(`<header class="flex h-12 shrink-0 items-center justify-between gap-4 border-b border-border/60 bg-card px-4 font-mono text-xs text-foreground" data-omr-workbench-header=""><div class="flex min-w-0 items-center gap-3">`);
    Link($$renderer2, {
      href: backHref(),
      class: "shrink-0 text-muted-foreground transition-colors hover:text-foreground inline-flex items-center gap-1",
      "aria-label": `Back to ${backLabel()}`,
      children: ($$renderer3) => {
        $$renderer3.push(`<span aria-hidden="true">←</span> ${escape_html(backLabel())}`);
      },
      $$slots: { default: true }
    });
    $$renderer2.push(`<!----> <span class="text-border" aria-hidden="true">/</span> <h1 class="min-w-0 truncate font-semibold tracking-tight text-foreground font-sans" data-omr-context-title="">${escape_html(contextTitle())}</h1> `);
    if (isDiagnostic()) {
      $$renderer2.push("<!--[0-->");
      $$renderer2.push(`<span class="px-2 py-0.5 rounded-full text-[10px] font-bold bg-violet-500/10 text-violet-600 dark:text-violet-400 border border-violet-500/20 shrink-0">Diagnostic</span>`);
    } else {
      $$renderer2.push("<!--[-1-->");
    }
    $$renderer2.push(`<!--]--></div> `);
    if (isDiagnostic()) {
      $$renderer2.push("<!--[0-->");
      $$renderer2.push(`<span class="sr-only" data-omr-diagnostic-banner=""><span>Diagnostic projection · no production writes</span> <span>This Setup run is isolated from production captures, page claims, scores, releases, activation, assignment and submission state, and qualification.</span></span>`);
    } else {
      $$renderer2.push("<!--[-1-->");
    }
    $$renderer2.push(`<!--]--> <div class="flex shrink-0 items-center gap-3"><button type="button" class="inline-flex items-center gap-1.5 rounded-md border border-border/60 bg-muted/30 px-2.5 py-1.5 text-foreground transition-colors hover:bg-muted" title="Keyboard shortcuts (?)">`);
    Circle_question_mark($$renderer2, { class: "size-3.5", "aria-hidden": "true" });
    $$renderer2.push(`<!----> <span class="hidden sm:inline">Shortcuts</span></button> <button type="button" class="inline-flex items-center gap-1.5 rounded-md border border-border/60 bg-muted/30 px-2.5 py-1.5 text-foreground transition-colors hover:bg-muted">`);
    Settings_2($$renderer2, { class: "size-3.5", "aria-hidden": "true" });
    $$renderer2.push(`<!----> <span>Settings</span></button> <span class="whitespace-nowrap text-slate-400" role="status" aria-live="polite"${attr("aria-label", `${sheetsCount} scanned`)} data-omr-workbench-status="">${escape_html(sheetsCount)} Scanned</span> <div class="relative"><button type="button" class="inline-flex size-8 items-center justify-center rounded-md text-muted-foreground transition-colors hover:bg-muted hover:text-foreground border border-border/60" aria-label="More workbench actions" aria-haspopup="menu"${attr("aria-expanded", menuOpen)} aria-controls="omr-workbench-menu">`);
    Ellipsis($$renderer2, { class: "size-4", "aria-hidden": "true" });
    $$renderer2.push(`<!----></button> `);
    {
      $$renderer2.push("<!--[-1-->");
    }
    $$renderer2.push(`<!--]--></div> `);
    if (completeCommand()) {
      $$renderer2.push("<!--[0-->");
      $$renderer2.push(`<button type="button" class="rounded-md border border-emerald-800/70 px-2.5 py-1.5 text-emerald-300 transition-colors hover:bg-emerald-950/60">Complete</button>`);
    } else {
      $$renderer2.push("<!--[-1-->");
    }
    $$renderer2.push(`<!--]--></div> <span class="sr-only" data-omr-diagnostic-banner="">`);
    if (isDiagnostic()) {
      $$renderer2.push("<!--[0-->");
      $$renderer2.push(`no production writes`);
    } else {
      $$renderer2.push("<!--[-1-->");
    }
    $$renderer2.push(`<!--]--></span></header>`);
  });
}
function record(value) {
  return value !== null && typeof value === "object" && !Array.isArray(value) ? value : {};
}
function requiredInteger(value, label) {
  const parsed = Number(value);
  if (!Number.isSafeInteger(parsed) || parsed < 0) {
    throw new Error(`The ${label} is unavailable or invalid.`);
  }
  return parsed;
}
function buildOmrConsoleReviewPayload(payload, item, idempotencyKey) {
  const draft = record(payload);
  const source = record(item);
  const decision = String(draft.decision ?? "");
  if (!["confirm_machine", "set_option", "blank", "unresolved"].includes(
    decision
  )) {
    throw new Error("Choose a supported item review decision.");
  }
  const option = draft.selected_option ?? draft.selectable_option ?? draft.final_option ?? (decision === "confirm_machine" ? record(source.machine).selected_option ?? record(source.detected).selected_option ?? source.option_code ?? null : null);
  const selectedOption = option === null || option === "" ? null : String(option).trim();
  if ((decision === "confirm_machine" || decision === "set_option") && !selectedOption) {
    throw new Error(
      "Select a pinned answer option before saving this item."
    );
  }
  const offeredOptions = Array.isArray(source.options) ? source.options.map((entry) => {
    const optionRecord = record(entry);
    return String(
      typeof entry === "string" || typeof entry === "number" ? entry : optionRecord.code ?? optionRecord.option_code ?? ""
    ).trim();
  }).filter(Boolean) : [];
  if ((decision === "confirm_machine" || decision === "set_option") && (offeredOptions.length === 0 || !offeredOptions.includes(selectedOption))) {
    throw new Error("Select an answer option pinned to this item.");
  }
  if ((decision === "blank" || decision === "unresolved") && selectedOption !== null) {
    throw new Error("This item decision cannot include a selected option.");
  }
  const machine = record(source.machine);
  const machineOption = machine.selected_option == null ? null : String(machine.selected_option);
  const machineMarked = machine.state === "marked" && machineOption !== null;
  const reasonCode = draft.reason_code == null ? null : String(draft.reason_code).trim() || null;
  const note = draft.note == null ? null : String(draft.note).trim() || null;
  if ((decision === "set_option" || decision === "unresolved" || decision === "blank" && machineMarked) && reasonCode === null || decision === "set_option" && machineMarked && selectedOption !== machineOption && note === null) {
    throw new Error(
      decision === "set_option" && selectedOption !== machineOption ? "Choose a review reason and add a note for a changed answer." : "Choose a review reason before saving this item."
    );
  }
  const evidenceHash = String(
    draft.reviewed_machine_evidence_hash ?? draft.reviewed_evidence_hash ?? record(source.machine).evidence_hash ?? record(source.detected).evidence_hash ?? source.evidence_hash ?? ""
  ).trim().toLowerCase();
  if (!/^[a-f0-9]{64}$/.test(evidenceHash)) {
    throw new Error("The reviewed machine evidence hash is unavailable.");
  }
  const key = String(draft.idempotency_key ?? idempotencyKey ?? "").trim();
  if (!key)
    throw new Error("The item review idempotency key is unavailable.");
  const parentVersion = draft.expected_parent_version ?? source.parent_version ?? source.version;
  if (parentVersion === null || parentVersion === void 0 || String(parentVersion).trim() === "") {
    throw new Error("The item review parent version is unavailable.");
  }
  const itemRevision = requiredInteger(
    draft.expected_item_revision ?? source.item_revision ?? record(source.review).revision ?? 0,
    "item review revision"
  );
  return {
    decision,
    selected_option: selectedOption,
    reason_code: reasonCode,
    note,
    expected_parent_version: parentVersion,
    expected_item_revision: itemRevision,
    reviewed_machine_evidence_hash: evidenceHash,
    idempotency_key: key
  };
}
function normalizeAcknowledgedItemReview(value) {
  const root = record(value);
  const review = record(root.review);
  if (root.outcome !== "succeeded" || Object.keys(review).length === 0) {
    throw new Error("The server did not acknowledge the item review.");
  }
  const revision = requiredInteger(
    review.revision,
    "acknowledged item review revision"
  );
  const decision = String(review.decision ?? "");
  if (!["confirm_machine", "set_option", "blank", "unresolved"].includes(
    decision
  )) {
    throw new Error(
      "The server returned an unsupported item review decision."
    );
  }
  const evidenceHash = String(review.evidence_hash ?? "").toLowerCase();
  if (!/^[a-f0-9]{64}$/.test(evidenceHash)) {
    throw new Error(
      "The acknowledged item review has no valid evidence hash."
    );
  }
  const reviewer = review.reviewer == null ? null : record(review.reviewer);
  return {
    revision,
    decision,
    selected_option: review.selected_option == null ? null : String(review.selected_option),
    reason_code: review.reason_code == null ? null : String(review.reason_code),
    note: review.note == null ? null : String(review.note),
    reviewer: reviewer === null ? null : {
      id: reviewer.id ?? null,
      display_name: reviewer.display_name == null ? null : String(reviewer.display_name)
    },
    reviewed_at: review.reviewed_at == null ? null : String(review.reviewed_at),
    evidence_hash: evidenceHash,
    stale: false
  };
}
function assertProductionItemReviewAcknowledged(value, payload, parentId, itemKey) {
  const root = record(value);
  const receipt = record(root.receipt);
  const review = normalizeAcknowledgedItemReview(value);
  if (Number(receipt.parent_id) !== parentId || String(receipt.item_key ?? "") !== itemKey || String(receipt.evidence_hash ?? "").toLowerCase() !== payload.reviewed_machine_evidence_hash || receipt.decision !== payload.decision || (receipt.selected_option ?? null) !== payload.selected_option || Number(receipt.revision) !== review.revision || !Number.isSafeInteger(Number(receipt.parent_version)) || Number(receipt.parent_version) < Number(payload.expected_parent_version) || review.decision !== payload.decision || review.selected_option !== payload.selected_option || review.reason_code !== payload.reason_code || (review.note ?? null) !== payload.note || review.evidence_hash !== payload.reviewed_machine_evidence_hash || review.revision <= payload.expected_item_revision) {
    throw new Error(
      "The server acknowledgment is stale or does not match this item decision."
    );
  }
  return {
    ...review,
    parent_version: Number(receipt.parent_version)
  };
}
function assertDiagnosticItemReviewAcknowledged(previous, next, captureId, itemId, payload) {
  const before = record(previous);
  const after = record(next);
  if (after.execution_kind !== "diagnostic" || before.execution_kind !== "diagnostic" || typeof before.workbench_id !== "string" || after.workbench_id !== before.workbench_id || !before.workbench_id.startsWith("diagnostic:") || record(after.context).run_id !== record(before.context).run_id) {
    throw new Error(
      "The diagnostic review acknowledgment belongs to another run."
    );
  }
  const outcome = record(record(after.recovery).outcome);
  if (Object.keys(outcome).length > 0 && (outcome.kind !== "success" || outcome.blocked === true || outcome.stale === true || outcome.invalid === true)) {
    throw new Error(
      `The diagnostic review was not acknowledged (${String(outcome.code ?? outcome.kind ?? "failed")}).`
    );
  }
  const evidenceItems = record(after.evidence).items;
  const rows = Array.isArray(evidenceItems) ? evidenceItems : [];
  const updated = rows.map(record).find(
    (row) => Number(row.id) === itemId && Number(row.capture_id) === captureId
  );
  const review = record(updated?.review);
  if (!updated || Number(review.revision) <= payload.expected_item_revision || review.decision !== payload.decision || (review.selected_option ?? null) !== payload.selected_option || String(review.evidence_hash ?? "").toLowerCase() !== payload.reviewed_machine_evidence_hash || review.reason_code !== payload.reason_code || (review.note ?? null) !== payload.note) {
    throw new Error(
      "The diagnostic projection did not acknowledge this item decision."
    );
  }
  return review;
}
function productionItemReviewUrl(sessionId, sheetId, itemId) {
  if (![sessionId, sheetId].every(
    (value) => Number.isSafeInteger(value) && value > 0
  ) || !String(itemId).trim()) {
    throw new Error("The production item review context is unavailable.");
  }
  return `/grading/sessions/${encodeURIComponent(String(sessionId))}/omr/sheets/${encodeURIComponent(String(sheetId))}/items/${encodeURIComponent(String(itemId))}/review`;
}
function productionItemReviewKey(item) {
  const source = record(item);
  const areaCode = String(source.area_code ?? "").trim();
  const questionNo = Number(source.question_no);
  if (areaCode !== "" && Number.isSafeInteger(questionNo) && questionNo > 0) {
    return `${areaCode.toUpperCase()}_Q_${questionNo}`;
  }
  const key = String(
    source.item_key ?? source.item_id ?? source.native_label ?? source.label ?? ""
  ).trim();
  if (!key) throw new Error("The production item key is unavailable.");
  return key;
}
const OMR_EXECUTION_KINDS = ["production", "diagnostic"];
const OMR_WORKBENCH_PHASES = [
  "capture",
  "preflight",
  "context",
  "processing",
  "evidence",
  "review",
  "recovery",
  "completion",
  "complete",
  "terminal"
];
const OMR_WORKBENCH_COMMANDS = [
  "capture_start",
  "capture_retry",
  "review_save",
  "resolve_applicant",
  "resolve_display_code",
  "resolve_duplicate",
  "quarantine_capture",
  "select_session",
  "production_complete",
  "diagnostic_compare",
  "diagnostic_complete"
];
const OMR_WORKBENCH_CAPABILITIES = [
  "capture",
  "review",
  "resolve_applicant",
  "resolve_display_code",
  "resolve_duplicate",
  "quarantine",
  "select_session",
  "production_complete",
  "diagnostic_compare",
  "diagnostic_complete"
];
const OMR_WORKBENCH_RECOVERY_ACTIONS = [
  ...OMR_WORKBENCH_COMMANDS,
  "retry_scan",
  "select_session",
  "return_to_grading",
  "match_applicant",
  "compare_duplicates",
  "open_monitoring",
  "open_exam_setup",
  "review_material_assignment",
  "enter_display_code",
  "quarantine_scan",
  "open_sheet_builder",
  "open_support"
];
const MAX_ID_LENGTH = 128;
const MAX_STRING_LENGTH = 16384;
const MAX_ARRAY_ITEMS = 512;
const MAX_RECORD_KEYS = 256;
const MAX_VALUE_DEPTH = 8;
const WORKBENCH_ID_PATTERN = /^[A-Za-z0-9][A-Za-z0-9._:-]{0,127}$/;
const PRODUCTION_ID_PATTERN = /^production:[1-9][0-9]*:[1-9][0-9]*$/;
const DIAGNOSTIC_ID_PATTERN = /^diagnostic:[A-Za-z0-9][A-Za-z0-9._-]{0,127}$/;
function isRecord$1(value) {
  if (value === null || typeof value !== "object" || Array.isArray(value)) {
    return false;
  }
  try {
    const prototype = Object.getPrototypeOf(value);
    return prototype === Object.prototype || prototype === null;
  } catch {
    return false;
  }
}
function isSafeValue(value, depth = 0) {
  if (depth > MAX_VALUE_DEPTH) {
    return false;
  }
  if (value === null || typeof value === "boolean") {
    return true;
  }
  if (typeof value === "string") {
    return value.length <= MAX_STRING_LENGTH;
  }
  if (typeof value === "number") {
    return Number.isFinite(value);
  }
  if (Array.isArray(value)) {
    return value.length <= MAX_ARRAY_ITEMS && value.every((entry) => isSafeValue(entry, depth + 1));
  }
  if (!isRecord$1(value)) {
    return false;
  }
  const keys = Object.keys(value);
  return keys.length <= MAX_RECORD_KEYS && keys.every(
    (key) => key.length > 0 && key.length <= MAX_ID_LENGTH && isSafeValue(value[key], depth + 1)
  );
}
function isPositiveInteger(value) {
  return Number.isSafeInteger(value) && value > 0;
}
function isNonNegativeInteger(value) {
  return Number.isSafeInteger(value) && value >= 0;
}
function isOneOf(values, value) {
  return typeof value === "string" && values.includes(value);
}
function isWorkbenchIdentity(value, executionKind) {
  if (typeof value !== "string" || value.length === 0 || value.length > MAX_ID_LENGTH || !WORKBENCH_ID_PATTERN.test(value)) {
    return false;
  }
  return executionKind === "production" ? PRODUCTION_ID_PATTERN.test(value) : DIAGNOSTIC_ID_PATTERN.test(value);
}
function isProgress(value) {
  if (!isRecord$1(value)) {
    return false;
  }
  const completed = value.completed;
  const total = value.total;
  return isNonNegativeInteger(completed) && isNonNegativeInteger(total) && completed <= total && typeof value.known === "boolean" && isSafeValue(value);
}
function isCounts(value) {
  if (!isRecord$1(value) || !isSafeValue(value)) {
    return false;
  }
  return Object.values(value).every(
    (count) => count === void 0 || isNonNegativeInteger(count)
  );
}
function isPage(value) {
  if (!isRecord$1(value) || !isPositiveInteger(value.page_no)) {
    return false;
  }
  if (value.status !== void 0 && typeof value.status !== "string") {
    return false;
  }
  if (value.captured !== void 0 && typeof value.captured !== "boolean") {
    return false;
  }
  return isSafeValue(value);
}
function isAsset(value) {
  if (!isRecord$1(value)) {
    return false;
  }
  if (value.id !== void 0 && value.id !== null && !isPositiveInteger(value.id)) {
    return false;
  }
  if (value.hash !== void 0 && value.hash !== null && typeof value.hash !== "string") {
    return false;
  }
  if (value.url !== void 0 && value.url !== null && typeof value.url !== "string") {
    return false;
  }
  return isSafeValue(value);
}
function isAssets(value) {
  if (!isRecord$1(value) || !isSafeValue(value)) {
    return false;
  }
  return ["input", "aligned", "overlay"].every((key) => {
    const asset = value[key];
    return asset === void 0 || asset === null || isAsset(asset);
  });
}
function isRecoveryAction(value) {
  return isOneOf(OMR_WORKBENCH_RECOVERY_ACTIONS, value);
}
function isRecoveryItem(value) {
  if (!isRecord$1(value) || !isSafeValue(value)) {
    return false;
  }
  if (value.owner !== void 0 && typeof value.owner !== "string") {
    return false;
  }
  if (value.title !== void 0 && typeof value.title !== "string") {
    return false;
  }
  if (value.explanation !== void 0 && typeof value.explanation !== "string") {
    return false;
  }
  if (value.status !== void 0 && typeof value.status !== "string") {
    return false;
  }
  if (value.blocked !== void 0 && typeof value.blocked !== "boolean") {
    return false;
  }
  if (value.scope !== void 0 && !isOneOf(["page", "sheet", "batch"], value.scope)) {
    return false;
  }
  if (value.support_code !== void 0 && typeof value.support_code !== "string") {
    return false;
  }
  if (value.code !== void 0 && typeof value.code !== "string") {
    return false;
  }
  if (value.primary_action !== void 0 && value.primary_action !== null && !isRecoveryAction(value.primary_action)) {
    return false;
  }
  if (value.secondary_action !== void 0 && value.secondary_action !== null && !isRecoveryAction(value.secondary_action)) {
    return false;
  }
  if (value.destination_url !== void 0 && value.destination_url !== null && typeof value.destination_url !== "string") {
    return false;
  }
  if (value.technical_detail !== void 0 && value.technical_detail !== null && !isSafeValue(value.technical_detail)) {
    return false;
  }
  return true;
}
function isAttentionItem(value) {
  if (!isRecord$1(value) || !isSafeValue(value)) {
    return false;
  }
  if (value.owner !== void 0 && typeof value.owner !== "string") {
    return false;
  }
  if (value.count !== void 0 && !isNonNegativeInteger(value.count)) {
    return false;
  }
  if (value.sheet_ids !== void 0 && (!Array.isArray(value.sheet_ids) || value.sheet_ids.length > MAX_ARRAY_ITEMS || !value.sheet_ids.every(isPositiveInteger))) {
    return false;
  }
  return true;
}
function isOutcome(value) {
  if (!isRecord$1(value) || !isSafeValue(value)) {
    return false;
  }
  if (value.kind !== void 0 && !isOneOf(
    [
      "success",
      "blocked",
      "stale",
      "invalid",
      "denied",
      "expired",
      "conflict"
    ],
    value.kind
  )) {
    return false;
  }
  if (value.code !== void 0 && typeof value.code !== "string") {
    return false;
  }
  for (const key of ["blocked", "stale", "invalid"]) {
    if (value[key] !== void 0 && typeof value[key] !== "boolean") {
      return false;
    }
  }
  if (value.message !== void 0 && typeof value.message !== "string") {
    return false;
  }
  return true;
}
function isRecovery(value) {
  if (!isRecord$1(value) || !isSafeValue(value)) {
    return false;
  }
  if (value.items !== void 0 && (!Array.isArray(value.items) || value.items.length > MAX_ARRAY_ITEMS || !value.items.every(isRecoveryItem))) {
    return false;
  }
  if (value.needs_attention !== void 0 && (!Array.isArray(value.needs_attention) || value.needs_attention.length > MAX_ARRAY_ITEMS || !value.needs_attention.every(isAttentionItem))) {
    return false;
  }
  if (value.count !== void 0 && !isNonNegativeInteger(value.count)) {
    return false;
  }
  if (value.outcome !== void 0 && value.outcome !== null && !isOutcome(value.outcome)) {
    return false;
  }
  return true;
}
function isEvidenceReference(value) {
  if (!isRecord$1(value) || !isSafeValue(value)) {
    return false;
  }
  if (value.sheet_id !== void 0 && !isPositiveInteger(value.sheet_id)) {
    return false;
  }
  if (value.page_no !== void 0 && !isPositiveInteger(value.page_no)) {
    return false;
  }
  if (value.status !== void 0 && typeof value.status !== "string") {
    return false;
  }
  if (value.captured !== void 0 && typeof value.captured !== "boolean") {
    return false;
  }
  return true;
}
function isEvidenceItem(value) {
  if (!isRecord$1(value) || !isSafeValue(value)) {
    return false;
  }
  if (value.id !== void 0 && !isPositiveInteger(value.id)) {
    return false;
  }
  if (value.capture_id !== void 0 && !isPositiveInteger(value.capture_id)) {
    return false;
  }
  if (value.item_key !== void 0 && typeof value.item_key !== "string") {
    return false;
  }
  if (value.page_no !== void 0 && !isPositiveInteger(value.page_no)) {
    return false;
  }
  if (value.area_code !== void 0 && value.area_code !== null && typeof value.area_code !== "string") {
    return false;
  }
  if (value.question_no !== void 0 && value.question_no !== null && !isPositiveInteger(value.question_no)) {
    return false;
  }
  if (value.option_code !== void 0 && value.option_code !== null && typeof value.option_code !== "string") {
    return false;
  }
  if (value.evidence_hash !== void 0 && typeof value.evidence_hash !== "string") {
    return false;
  }
  if (value.evidence_version !== void 0 && typeof value.evidence_version !== "string") {
    return false;
  }
  return true;
}
function isEvidenceCapture(value) {
  if (!isRecord$1(value) || !isSafeValue(value)) {
    return false;
  }
  if (value.capture_id !== void 0 && !isPositiveInteger(value.capture_id)) {
    return false;
  }
  if (value.evidence !== void 0 && value.evidence !== null && (!isRecord$1(value.evidence) || !isSafeValue(value.evidence))) {
    return false;
  }
  if (value.evidence_hash !== void 0 && value.evidence_hash !== null && typeof value.evidence_hash !== "string") {
    return false;
  }
  return true;
}
function isEvidence(value) {
  if (!isRecord$1(value) || !isSafeValue(value)) {
    return false;
  }
  if (value.references !== void 0 && (!Array.isArray(value.references) || value.references.length > MAX_ARRAY_ITEMS || !value.references.every(isEvidenceReference))) {
    return false;
  }
  if (value.items !== void 0 && (!Array.isArray(value.items) || value.items.length > MAX_ARRAY_ITEMS || !value.items.every(isEvidenceItem))) {
    return false;
  }
  if (value.captures !== void 0 && (!Array.isArray(value.captures) || value.captures.length > MAX_ARRAY_ITEMS || !value.captures.every(isEvidenceCapture))) {
    return false;
  }
  if (value.comparison !== void 0 && value.comparison !== null && (!isRecord$1(value.comparison) || !isSafeValue(value.comparison))) {
    return false;
  }
  if (value.count !== void 0 && !isNonNegativeInteger(value.count)) {
    return false;
  }
  if (value.redacted !== void 0 && typeof value.redacted !== "boolean") {
    return false;
  }
  return true;
}
function isCompletion(value) {
  if (!isRecord$1(value) || !isSafeValue(value)) {
    return false;
  }
  if (value.kind !== void 0 && typeof value.kind !== "string") {
    return false;
  }
  if (value.label !== void 0 && typeof value.label !== "string") {
    return false;
  }
  if (value.status !== void 0 && typeof value.status !== "string") {
    return false;
  }
  if (value.official !== void 0 && typeof value.official !== "boolean") {
    return false;
  }
  if (value.immutable !== void 0 && typeof value.immutable !== "boolean") {
    return false;
  }
  if (value.run_id !== void 0 && !isPositiveInteger(value.run_id)) {
    return false;
  }
  if (value.session_id !== void 0 && !isPositiveInteger(value.session_id)) {
    return false;
  }
  if (value.capture_count !== void 0 && !isNonNegativeInteger(value.capture_count)) {
    return false;
  }
  if (value.completed_at !== void 0 && value.completed_at !== null && typeof value.completed_at !== "string") {
    return false;
  }
  if (value.outcome !== void 0 && typeof value.outcome !== "string") {
    return false;
  }
  return true;
}
function isFailure(value) {
  if (!isRecord$1(value) || !isSafeValue(value)) {
    return false;
  }
  if (value.code !== void 0 && value.code !== null && typeof value.code !== "string") {
    return false;
  }
  if (value.detail !== void 0 && value.detail !== null && typeof value.detail !== "string") {
    return false;
  }
  return true;
}
function isSheet(value) {
  if (!isRecord$1(value) || !isPositiveInteger(value.id) || !isSafeValue(value)) {
    return false;
  }
  if (value.client_capture_id !== void 0 && typeof value.client_capture_id !== "string") {
    return false;
  }
  if (value.status !== void 0 && typeof value.status !== "string") {
    return false;
  }
  if (value.version !== void 0 && !isNonNegativeInteger(value.version)) {
    return false;
  }
  if (value.stage !== void 0 && (typeof value.stage !== "string" || value.stage.length > MAX_STRING_LENGTH)) {
    return false;
  }
  if (value.resolution !== void 0 && value.resolution !== null && (!isRecord$1(value.resolution) || !isSafeValue(value.resolution))) {
    return false;
  }
  if (value.recovery !== void 0 && value.recovery !== null && !isRecoveryItem(value.recovery)) {
    return false;
  }
  if (value.pages !== void 0 && (!Array.isArray(value.pages) || value.pages.length > MAX_ARRAY_ITEMS || !value.pages.every(isPage))) {
    return false;
  }
  if (value.assets !== void 0 && !isAssets(value.assets)) {
    return false;
  }
  if (value.evidence !== void 0 && value.evidence !== null && (!isRecord$1(value.evidence) || !isSafeValue(value.evidence))) {
    return false;
  }
  if (value.evidence_hash !== void 0 && value.evidence_hash !== null && typeof value.evidence_hash !== "string") {
    return false;
  }
  if (value.failure !== void 0 && value.failure !== null && !isFailure(value.failure)) {
    return false;
  }
  if (value.captured_at !== void 0 && value.captured_at !== null && typeof value.captured_at !== "string") {
    return false;
  }
  if (value.completed_at !== void 0 && value.completed_at !== null && typeof value.completed_at !== "string") {
    return false;
  }
  return true;
}
function isContext(value, executionKind) {
  if (!isRecord$1(value) || !isSafeValue(value)) {
    return false;
  }
  if (value.execution_kind !== void 0 && value.execution_kind !== executionKind) {
    return false;
  }
  for (const key of [
    "actor_id",
    "session_id",
    "run_id",
    "institution_id",
    "academic_cycle_id",
    "template_version_id",
    "booklet_version_id"
  ]) {
    if (value[key] !== void 0 && !isPositiveInteger(value[key])) {
      return false;
    }
  }
  if (value.status !== void 0 && typeof value.status !== "string") {
    return false;
  }
  if (value.raw_phase !== void 0 && typeof value.raw_phase !== "string") {
    return false;
  }
  if (value.raw_status !== void 0 && typeof value.raw_status !== "string") {
    return false;
  }
  if (value.terminal !== void 0 && typeof value.terminal !== "boolean") {
    return false;
  }
  if (value.stale_after_ms !== void 0 && !isNonNegativeInteger(value.stale_after_ms)) {
    return false;
  }
  if (value.observed_at !== void 0 && value.observed_at !== null && typeof value.observed_at !== "string") {
    return false;
  }
  if (value.progress !== void 0 && !isProgress(value.progress)) {
    return false;
  }
  if (value.counts !== void 0 && !isCounts(value.counts)) {
    return false;
  }
  if (value.capabilities !== void 0 && (!Array.isArray(value.capabilities) || value.capabilities.length > MAX_ARRAY_ITEMS || !value.capabilities.every(
    (capability) => isOneOf(OMR_WORKBENCH_CAPABILITIES, capability)
  ))) {
    return false;
  }
  return true;
}
function isCommandList(value) {
  return Array.isArray(value) && value.length <= MAX_ARRAY_ITEMS && value.every((command) => isOneOf(OMR_WORKBENCH_COMMANDS, command));
}
function isOmrWorkbenchProjection(value) {
  if (!isRecord$1(value) || !isSafeValue(value)) {
    return false;
  }
  const executionKind = value.execution_kind;
  if (!isOneOf(OMR_EXECUTION_KINDS, executionKind)) {
    return false;
  }
  if (!isWorkbenchIdentity(value.workbench_id, executionKind)) {
    return false;
  }
  if (!isPositiveInteger(value.revision)) {
    return false;
  }
  if (!isOneOf(OMR_WORKBENCH_PHASES, value.phase)) {
    return false;
  }
  if (!isContext(value.context, executionKind)) {
    return false;
  }
  if (!Array.isArray(value.sheets) || value.sheets.length > MAX_ARRAY_ITEMS || !value.sheets.every(isSheet)) {
    return false;
  }
  if (!isEvidence(value.evidence) || !isRecovery(value.recovery)) {
    return false;
  }
  if (value.completion !== null && !isCompletion(value.completion)) {
    return false;
  }
  return isCommandList(value.commands);
}
function acceptOmrWorkbenchProjection(current, next) {
  if (!isOmrWorkbenchProjection(next)) {
    return false;
  }
  if (current === null || current === void 0) {
    return true;
  }
  return isOmrWorkbenchProjection(current) && current.workbench_id === next.workbench_id && current.execution_kind === next.execution_kind && next.revision > current.revision;
}
function OmrWorkbench($$renderer, $$props) {
  $$renderer.component(($$renderer2) => {
    let {
      projection,
      connectionState = void 0,
      captureContent = void 0,
      queueContent = void 0,
      selectionContent = void 0,
      evidencePage = null,
      selectedSheetId = null,
      onSelectSheet = void 0,
      onPageChange = void 0,
      onGroupChange = void 0,
      onFilterChange = void 0,
      onViewChange = void 0,
      onRetry = void 0,
      onReviewAction = void 0,
      onRecoveryAction = void 0,
      onComplete = void 0,
      onDirtyChange = void 0,
      onDirtyNavigation = void 0,
      onSettings = void 0,
      onRestart = void 0,
      initialStageView = "live"
    } = $$props;
    let activeSheetId = null;
    let activeStageView = initialStageView ?? "live";
    let mobileRailExpanded = false;
    let evidenceFocusOpen = false;
    let recoveryBusy = false;
    let queueViewMode = "list";
    let reviewFilter = "flagged";
    let quickMarks = {};
    let quickReasons = {};
    let quickNotes = {};
    let shortcutsModalOpen = false;
    function reviewItemKey(item, _index) {
      return String(item.id ?? item.item_id ?? "");
    }
    function reviewItemOptions(item) {
      return normalizeItem(item).options.map((option) => option.code);
    }
    function setQuickReason(key, value) {
      quickReasons = { ...quickReasons, [key]: value };
    }
    function reviewItemFlagged(item) {
      const issues = Array.isArray(item.issues) ? item.issues : [];
      const issueCodes = Array.isArray(item.issue_codes) ? item.issue_codes : [];
      const detected = asRecord(item.detected);
      const machine = asRecord(item.machine);
      const machineCodes = Array.isArray(machine.issue_codes) ? machine.issue_codes : [];
      const state = String(detected.state ?? machine.state ?? item.state ?? "").toLowerCase();
      return issues.length > 0 || issueCodes.length > 0 || machineCodes.length > 0 || [
        "ambiguous",
        "unreadable",
        "unresolved",
        "multi_mark",
        "multiple"
      ].includes(state) || item.exact === false || Boolean(item.recovery);
    }
    function reviewConfidence(item) {
      const confidence = item.confidence;
      if (typeof confidence !== "number" || !Number.isFinite(confidence)) {
        return null;
      }
      const percentage = confidence >= 0 && confidence <= 1 ? confidence * 100 : confidence;
      return `${Math.round(percentage)}%`;
    }
    function reviewItemArea(item) {
      const area = textValue(item.area_code) ?? textValue(item.area) ?? textValue(item.group_type);
      return area === null ? "Aptitude" : titleCase(area);
    }
    let isSavingReview = false;
    let reviewSaveError = null;
    function sheetThumbnailUrl(sheet) {
      const s = sheet;
      return s.lazy_asset_urls?.input ?? s.asset_urls?.thumbnail ?? s.asset_urls?.input ?? null;
    }
    function asRecord(value) {
      if (value === null || typeof value !== "object" || Array.isArray(value)) {
        return {};
      }
      return value;
    }
    function textValue(value) {
      if (typeof value !== "string") return null;
      const text = value.trim();
      return text === "" ? null : text;
    }
    function displayValue(value) {
      if (typeof value === "string") return textValue(value);
      if (typeof value === "number" && Number.isFinite(value)) {
        return String(value);
      }
      if (typeof value === "boolean") return value ? "Yes" : "No";
      return null;
    }
    function nonNegative(value) {
      if (typeof value !== "number" || !Number.isFinite(value)) return null;
      return Math.max(0, Math.floor(value));
    }
    function titleCase(value) {
      return value.replace(/[_-]+/g, " ").replace(/\b\w/g, (character) => character.toUpperCase());
    }
    function phaseLabel(value) {
      const phase = textValue(value);
      if (phase === null) return "Awaiting status";
      return {
        capture: "Capture",
        preflight: "Preflight",
        context: "Context",
        processing: "Processing",
        evidence: "Evidence",
        review: "Review",
        recovery: "Needs attention",
        completion: "Completion",
        complete: "Complete",
        terminal: "Terminal"
      }[phase] ?? titleCase(phase);
    }
    const contextDetails = derived(() => {
      const details = [];
      const sessionId = displayValue(context().session_id);
      const runId = displayValue(context().run_id);
      const status = displayValue(context().status) ?? displayValue(context().raw_status);
      const stage = displayValue(context().raw_phase) ?? phaseLabel(projection.phase);
      if (sessionId !== null && projection.execution_kind === "production") {
        details.push({ label: "Session", value: sessionId });
      }
      if (runId !== null) details.push({ label: "Run", value: runId });
      if (status !== null) details.push({ label: "Status", value: titleCase(status) });
      if (stage !== null) details.push({ label: "Stage", value: phaseLabel(stage) });
      return details;
    });
    function sheetLabel(sheet) {
      const captureId = textValue(sheet.client_capture_id);
      return captureId !== null ? `Capture ${captureId}` : `Sheet ${sheet.id}`;
    }
    function sheetStatus(sheet) {
      if (sheet.recovery !== null && sheet.recovery !== void 0) {
        return "Needs attention";
      }
      const status = textValue(sheet.status);
      return status !== null ? titleCase(status) : "Processed";
    }
    function projectedCommand(command) {
      return projection.commands.includes(command);
    }
    function candidateLabel(sheet) {
      const details = asRecord(sheet);
      return textValue(details.candidate_name) ?? textValue(details.applicant_name) ?? textValue(details.detected_applicant_number) ?? textValue(sheet.client_capture_id) ?? `Scan #${sheet.id}`;
    }
    function canInvokeRecoveryAction(action) {
      if (!OMR_WORKBENCH_COMMANDS.includes(action)) {
        return true;
      }
      return projection.commands.includes(action);
    }
    const sheets = derived(() => projection.sheets ?? []);
    const evidenceItems = derived(() => {
      const items = asRecord(evidencePage).items;
      return Array.isArray(items) ? items : [];
    });
    const visibleReviewItems = derived(() => evidenceItems().filter(reviewItemFlagged));
    const activeSheet = derived(() => sheets().find((sheet) => sheet.id === activeSheetId) ?? null);
    const context = derived(() => asRecord(projection.context));
    const progress = derived(() => asRecord(context().progress));
    const progressCompleted = derived(() => nonNegative(progress().completed) ?? 0);
    const progressTotal = derived(() => nonNegative(progress().total));
    const progressKnown = derived(() => progress().known === true && progressTotal() !== null && progressTotal() > 0);
    const recoveryItems = derived(() => projection.recovery.items ?? []);
    const allRecoveryItems = derived(() => {
      const items = [...recoveryItems()];
      if (activeSheet()?.recovery) {
        const sheetRec = activeSheet().recovery;
        const alreadyExists = items.some((item) => item.code === sheetRec.code && Number(item.capture_id ?? 0) === Number(activeSheet()?.id ?? 0));
        if (!alreadyExists) {
          items.unshift({
            ...sheetRec,
            capture_id: sheetRec.capture_id ?? activeSheet().id
          });
        }
      }
      return items;
    });
    const attentionItems = derived(() => {
      const list = [...projection.recovery.needs_attention ?? []];
      if (list.length === 0 && allRecoveryItems().length > 0) {
        const owners = /* @__PURE__ */ new Map();
        for (const item of allRecoveryItems()) {
          const owner = textValue(item.owner) ?? "Operator";
          owners.set(owner, (owners.get(owner) ?? 0) + 1);
        }
        return Array.from(owners.entries()).map(([owner, count]) => ({ owner, count }));
      }
      return list;
    });
    const attentionCount = derived(() => {
      if (allRecoveryItems().length > 0) return allRecoveryItems().length;
      return attentionItems().reduce((total, item) => total + (nonNegative(item.count) ?? 0), 0);
    });
    const contextSummary = derived(() => {
      const summary = {};
      const session = displayValue(context().session_id);
      const status = displayValue(context().status) ?? displayValue(context().raw_status);
      if (session !== null) summary.exam_session = session;
      if (status !== null) summary.status = status;
      return summary;
    });
    const evidenceRecord = derived(() => asRecord(projection.evidence));
    const evidenceBlind = derived(() => asRecord(evidencePage).blind_review === true || evidenceRecord().blind_review === true);
    const evidenceReviewReasons = derived(() => {
      const reasons = asRecord(evidencePage).review_reasons;
      if (Array.isArray(reasons)) return reasons;
      return [];
    });
    const evidencePlacement = derived(() => "mobile");
    const reviewable = derived(() => projectedCommand("review_save") && onReviewAction !== void 0);
    const recoveryHandler = derived(() => onRecoveryAction === void 0 ? null : handleRecoveryAction);
    function selectSheet(sheet) {
      if (sheet.id === activeSheetId) return;
      if (Object.keys(quickMarks).length > 0 && onDirtyNavigation) {
        const allowed = onDirtyNavigation({ target: "sheet", sheet_id: sheet.id });
        if (allowed === false) return;
      }
      quickMarks = {};
      quickReasons = {};
      quickNotes = {};
      onDirtyChange?.(false);
      reviewSaveError = null;
      activeSheetId = sheet.id;
      mobileRailExpanded = true;
      void onSelectSheet?.(sheet);
    }
    async function handleRecoveryAction(action, recovery) {
      if (!onRecoveryAction || !canInvokeRecoveryAction(action) || recoveryBusy) {
        return;
      }
      recoveryBusy = true;
      try {
        await onRecoveryAction(action, recovery);
      } finally {
        recoveryBusy = false;
      }
    }
    async function handleComplete(command) {
      if (!onComplete || !projection.commands.includes(command)) return;
      await onComplete(command);
    }
    let $$settled = true;
    let $$inner_renderer;
    function $$render_inner($$renderer3) {
      $$renderer3.push(`<div class="omr-workbench bg-background text-foreground flex h-dvh w-full flex-col overflow-hidden" data-omr-workbench=""${attr("data-omr-workbench-id", projection.workbench_id)}${attr("data-omr-execution-kind", projection.execution_kind)}${attr("data-omr-evidence-placement", evidencePlacement())}>`);
      OmrWorkbenchHeader($$renderer3, {
        projection,
        sheetsCount: sheets().length
      });
      $$renderer3.push(`<!----> <div class="grid min-h-0 flex-1 grid-cols-1 items-stretch gap-4 overflow-hidden p-3 sm:p-4 lg:grid-cols-12 svelte-1nbp2q5" data-omr-workbench-flow=""><div class="flex h-full min-h-0 flex-col overflow-hidden lg:col-span-7 xl:col-span-8 svelte-1nbp2q5">`);
      OmrCaptureStage($$renderer3, {
        captureContent,
        activeStageView,
        sheets: sheets(),
        activeSheetId,
        evidencePage,
        activeSheet: activeSheet()
      });
      $$renderer3.push(`<!----></div> <div${attr_class(`h-full ${activeStageView === "review" ? "overflow-hidden" : "overflow-hidden"} border-border/60 bg-card/40 flex min-h-0 flex-col rounded-xl border-l lg:col-span-5 xl:col-span-4`, "svelte-1nbp2q5")}${attr("data-omr-stage", activeStageView === "review" ? "review" : "queue")}${attr("data-omr-stage-view", activeStageView === "review" ? "review" : "queue")}>`);
      if (activeStageView === "review") {
        $$renderer3.push("<!--[0-->");
        $$renderer3.push(`<header class="border-border/60 flex shrink-0 items-center justify-between border-b px-4 py-3 font-mono text-xs"><h2 class="text-foreground font-bold">Item Review <span class="text-muted-foreground">· Sheet #${escape_html(activeSheet()?.id ?? "")}</span></h2> <div class="border-border/60 bg-muted/40 flex items-center gap-1 rounded-lg border p-1" role="group" aria-label="Review item filter"><button type="button"${attr_class(`rounded-md px-2 py-1 text-[10px] font-semibold transition-colors ${"bg-card text-foreground font-bold shadow-xs"}`)}${attr("aria-pressed", reviewFilter === "flagged")}>Flagged</button> <button type="button"${attr_class(`rounded-md px-2 py-1 text-[10px] font-semibold transition-colors ${"text-muted-foreground hover:text-foreground"}`)}${attr("aria-pressed", reviewFilter === "all")}>All</button></div></header> `);
        if (reviewSaveError) {
          $$renderer3.push("<!--[0-->");
          $$renderer3.push(`<div class="bg-destructive/10 border-destructive/20 text-destructive flex shrink-0 items-center gap-2 border-b p-3 text-xs">`);
          Circle_alert($$renderer3, { class: "size-4 shrink-0" });
          $$renderer3.push(`<!----> <span>${escape_html(reviewSaveError)}</span></div>`);
        } else {
          $$renderer3.push("<!--[-1-->");
        }
        $$renderer3.push(`<!--]--> <div class="min-h-0 flex-1 space-y-3 overflow-y-auto p-3" data-omr-review-items="">`);
        if (visibleReviewItems().length > 0) {
          $$renderer3.push("<!--[0-->");
          $$renderer3.push(`<!--[-->`);
          const each_array = ensure_array_like(visibleReviewItems());
          for (let index = 0, $$length = each_array.length; index < $$length; index++) {
            let item = each_array[index];
            const flagged = reviewItemFlagged(item);
            const itemKey = reviewItemKey(item);
            const rawOptions = reviewItemOptions(item);
            $$renderer3.push(`<article class="border-border/60 bg-card rounded-xl border p-3 shadow-xs"${attr("data-omr-review-item", itemKey)}><div class="flex items-start justify-between gap-3"><h3 class="text-foreground min-w-0 text-sm font-semibold">Q${escape_html(item.question_no ?? item.label ?? index + 1)} · ${escape_html(reviewItemArea(item))}</h3> <span${attr_class(`shrink-0 rounded border px-2 py-0.5 font-mono text-[9px] font-bold tracking-wide ${flagged ? "border-amber-500/30 bg-amber-500/10 text-amber-600 dark:text-amber-400" : "border-emerald-500/20 bg-emerald-500/10 text-emerald-600 dark:text-emerald-400"}`)}>`);
            if (flagged) {
              $$renderer3.push("<!--[0-->");
              $$renderer3.push(`FLAGGED`);
            } else {
              $$renderer3.push("<!--[-1-->");
              $$renderer3.push(`CLEAN`);
              if (reviewConfidence(item)) {
                $$renderer3.push("<!--[0-->");
                $$renderer3.push(`· ${escape_html(reviewConfidence(item))}`);
              } else {
                $$renderer3.push("<!--[-1-->");
              }
              $$renderer3.push(`<!--]-->`);
            }
            $$renderer3.push(`<!--]--></span></div> <fieldset class="flex flex-wrap items-center gap-2 pt-2 text-sm"${attr("disabled", !reviewable() || item.reviewable === false, true)}><legend class="sr-only">Decision for ${escape_html(item.label ?? item.question_no ?? itemKey)}</legend> <!--[-->`);
            const each_array_1 = ensure_array_like(rawOptions);
            for (let $$index = 0, $$length2 = each_array_1.length; $$index < $$length2; $$index++) {
              let optCode = each_array_1[$$index];
              $$renderer3.push(`<button type="button"${attr_class(`flex size-7 items-center justify-center rounded-lg border font-bold transition-colors ${quickMarks[itemKey] === optCode ? "border-primary bg-primary text-primary-foreground shadow-xs" : "border-border/60 bg-muted/40 hover:bg-muted text-foreground"}`)}${attr("aria-pressed", quickMarks[itemKey] === optCode)}${attr("aria-label", `Mark question ${item.question_no ?? index + 1} as ${optCode}`)}>${escape_html(optCode)}</button>`);
            }
            $$renderer3.push(`<!--]--> <button type="button"${attr_class(`rounded-md border px-3 py-2 text-xs font-medium transition-colors disabled:opacity-50 ${quickMarks[itemKey] === "unresolved" ? "border-primary bg-primary text-primary-foreground" : "border-border bg-muted/40 text-foreground hover:bg-muted"}`)}${attr("aria-pressed", quickMarks[itemKey] === "unresolved")}>Unresolved</button></fieldset> `);
            if (quickMarks[itemKey]) {
              $$renderer3.push("<!--[0-->");
              $$renderer3.push(`<div class="mt-3 grid gap-3 text-sm"><label class="grid gap-1.5"><span class="text-xs font-medium">Review reason</span> `);
              $$renderer3.select(
                {
                  class: "border-border bg-background min-w-0 rounded-md border px-3 py-2",
                  value: quickReasons[itemKey] ?? "",
                  disabled: isSavingReview,
                  onchange: (event) => setQuickReason(itemKey, event.currentTarget.value)
                },
                ($$renderer4) => {
                  $$renderer4.option({ value: "" }, ($$renderer5) => {
                    $$renderer5.push(`Select a reason`);
                  });
                  $$renderer4.push(`<!--[-->`);
                  const each_array_2 = ensure_array_like(evidenceReviewReasons());
                  for (let $$index_1 = 0, $$length2 = each_array_2.length; $$index_1 < $$length2; $$index_1++) {
                    let reason = each_array_2[$$index_1];
                    $$renderer4.option({ value: reason.code }, ($$renderer5) => {
                      $$renderer5.push(`${escape_html(reason.label)}`);
                    });
                  }
                  $$renderer4.push(`<!--]-->`);
                }
              );
              $$renderer3.push(`</label> <label class="grid gap-1.5"><span class="text-xs font-medium">Review note</span> <textarea class="border-border bg-background min-w-0 resize-y rounded-md border px-3 py-2" rows="2"${attr("maxlength", Number(asRecord(evidencePage).review_note_max) || 500)}${attr("disabled", isSavingReview, true)}>`);
              const $$body = escape_html(quickNotes[itemKey] ?? "");
              if ($$body) {
                $$renderer3.push(`${$$body}`);
              }
              $$renderer3.push(`</textarea></label></div>`);
            } else {
              $$renderer3.push("<!--[-1-->");
            }
            $$renderer3.push(`<!--]--></article>`);
          }
          $$renderer3.push(`<!--]-->`);
        } else {
          $$renderer3.push("<!--[-1-->");
          $$renderer3.push(`<p class="border-border/60 text-muted-foreground rounded-lg border border-dashed px-3 py-8 text-center text-sm">${escape_html(evidenceItems().length === 0 ? "No item evidence is available for this sheet." : "No flagged items are available.")}</p>`);
        }
        $$renderer3.push(`<!--]--></div> <div class="border-border/60 bg-card flex shrink-0 items-center justify-between gap-2 border-t p-3 font-mono text-xs"><button type="button" class="text-muted-foreground hover:text-foreground px-2 py-1 transition-colors"${attr("disabled", isSavingReview, true)}>Cancel</button> <div class="flex items-center gap-2"><button type="button" class="border-border/60 bg-muted/40 hover:bg-muted text-foreground rounded-lg border px-3 py-1.5 text-xs font-semibold transition-colors"${attr("disabled", isSavingReview, true)}>Save</button> <button type="button" class="bg-primary text-primary-foreground hover:bg-primary/90 flex items-center gap-1.5 rounded-lg px-3.5 py-1.5 text-xs font-bold shadow-xs transition-colors"${attr("disabled", isSavingReview, true)}>`);
        {
          $$renderer3.push("<!--[-1-->");
          $$renderer3.push(`<span>Save &amp; Next Flagged →</span>`);
        }
        $$renderer3.push(`<!--]--></button></div></div> <div class="border-border/60 bg-muted/20 flex shrink-0 flex-wrap items-center justify-between gap-2 border-t px-4 py-2 svelte-1nbp2q5" data-omr-evidence-controls=""><p class="text-muted-foreground font-mono text-xs">Evidence review</p> <button type="button" class="border-border/60 bg-card hover:bg-muted focus-visible:ring-ring text-foreground inline-flex min-h-8 items-center gap-1.5 rounded-md border px-2.5 py-1 text-xs font-semibold transition-colors focus-visible:ring-2 focus-visible:outline-none svelte-1nbp2q5" data-omr-evidence-focus-toggle=""${attr("aria-pressed", evidenceFocusOpen)}>`);
        {
          $$renderer3.push("<!--[-1-->");
          Maximize_2($$renderer3, { class: "size-3.5", "aria-hidden": "true" });
          $$renderer3.push(`<!----> Focus evidence`);
        }
        $$renderer3.push(`<!--]--></button></div> <div class="border-border/60 space-y-3 overflow-y-auto border-t p-3">`);
        OmrReviewStage($$renderer3, {
          projection,
          evidencePage,
          placement: evidencePlacement(),
          returnUrl: null,
          loading: false,
          error: null,
          onRetry: onRetry ?? null,
          onPageChange: onPageChange ?? null,
          onPerPageChange: null,
          onGroupChange: onGroupChange ?? null,
          onFilterChange: onFilterChange ?? null,
          onRowOpen: null,
          onTabChange: null,
          onViewChange: onViewChange ?? null,
          initialTab: "aligned",
          contextSummary: contextSummary(),
          idPrefix: "omr-workbench",
          reviewable: reviewable(),
          blind: evidenceBlind(),
          reviewReasons: evidenceReviewReasons(),
          onDirtyChange: onDirtyChange ?? null,
          onDirtyNavigation: onDirtyNavigation ?? null,
          onSaveDecision: onReviewAction ?? null,
          onSaveAndNext: onReviewAction ?? null,
          onRecoveryAction: recoveryHandler(),
          recoveryBusy,
          sheets: sheets(),
          activeSheetId,
          onSelectSheet: (sheet) => selectSheet(sheet),
          get mobileRailExpanded() {
            return mobileRailExpanded;
          },
          set mobileRailExpanded($$value) {
            mobileRailExpanded = $$value;
            $$settled = false;
          }
        });
        $$renderer3.push(`<!----> `);
        OmrCompletionBar($$renderer3, {
          projection,
          onComplete: onComplete ? handleComplete : void 0
        });
        $$renderer3.push(`<!----></div>`);
      } else {
        $$renderer3.push("<!--[-1-->");
        $$renderer3.push(`<section class="flex min-w-0 flex-1 flex-col overflow-hidden" data-omr-stage="queue" data-omr-queue-selection="" aria-labelledby="omr-queue-selection-title"><header class="border-border/60 bg-card/50 shrink-0 border-b px-4 py-3.5 sm:px-5"><div class="grid min-w-0 grid-cols-[minmax(0,1fr)_auto] items-center gap-x-3 gap-y-2 sm:grid-cols-[minmax(0,1fr)_auto_auto]"><h2 id="omr-queue-selection-title" class="text-foreground min-w-0 font-mono text-xs font-bold tracking-[0.14em] uppercase">SCANS QUEUE</h2> <p class="text-muted-foreground col-span-2 row-start-2 text-center font-mono text-[11px] sm:col-span-1 sm:row-start-auto">${escape_html(sheets().length)} Scanned · ${escape_html(attentionCount())} Needs Check</p> <div class="border-border/60 bg-muted/40 text-muted-foreground inline-flex rounded-lg border p-0.5" role="group" aria-label="Queue presentation"><button type="button"${attr_class(`inline-flex items-center gap-1.5 rounded-md px-2 py-1 text-xs font-medium transition-colors ${"bg-card text-foreground shadow-xs"}`)}${attr("aria-pressed", queueViewMode === "list")}>`);
        List($$renderer3, { class: "size-3.5", "aria-hidden": "true" });
        $$renderer3.push(`<!----> <span>List</span></button> <button type="button"${attr_class(`inline-flex items-center gap-1.5 rounded-md px-2 py-1 text-xs font-medium transition-colors ${"hover:text-foreground"}`)}${attr("aria-pressed", queueViewMode === "grid")}>`);
        Layout_grid($$renderer3, { class: "size-3.5", "aria-hidden": "true" });
        $$renderer3.push(`<!----> <span>Grid</span></button></div></div> `);
        if (contextDetails().length > 0) {
          $$renderer3.push("<!--[0-->");
          $$renderer3.push(`<div class="border-border/40 mt-2.5 border-t pt-2.5" data-omr-context-summary=""><dl class="grid min-w-0 grid-cols-2 gap-2 font-mono text-xs sm:grid-cols-4"><!--[-->`);
          const each_array_3 = ensure_array_like(contextDetails());
          for (let $$index_3 = 0, $$length = each_array_3.length; $$index_3 < $$length; $$index_3++) {
            let detail = each_array_3[$$index_3];
            $$renderer3.push(`<div class="min-w-0"><dt class="text-muted-foreground text-[10px] uppercase">${escape_html(detail.label)}</dt> <dd class="text-foreground mt-0.5 truncate font-medium"${attr("title", detail.value)}>${escape_html(detail.value)}</dd></div>`);
          }
          $$renderer3.push(`<!--]--></dl></div>`);
        } else {
          $$renderer3.push("<!--[-1-->");
        }
        $$renderer3.push(`<!--]--></header> <div class="min-w-0 flex-1 space-y-3 overflow-y-auto p-3 sm:p-4">`);
        if (queueContent) {
          $$renderer3.push("<!--[0-->");
          $$renderer3.push(`<div data-omr-queue-slot="">`);
          queueContent($$renderer3);
          $$renderer3.push(`<!----></div>`);
        } else {
          $$renderer3.push("<!--[-1-->");
        }
        $$renderer3.push(`<!--]--> `);
        if (selectionContent) {
          $$renderer3.push("<!--[0-->");
          $$renderer3.push(`<div data-omr-selection-slot="">`);
          selectionContent($$renderer3);
          $$renderer3.push(`<!----></div>`);
        } else {
          $$renderer3.push("<!--[-1-->");
        }
        $$renderer3.push(`<!--]--> `);
        if (attentionItems().length > 0) {
          $$renderer3.push("<!--[0-->");
          $$renderer3.push(`<div class="border-border/70 bg-muted/20 text-muted-foreground rounded-lg border px-3 py-2.5 text-xs leading-relaxed" data-omr-needs-attention="" role="status" aria-live="polite"><p class="text-foreground font-semibold">Needs attention</p> <ul class="mt-1 space-y-1"><!--[-->`);
          const each_array_4 = ensure_array_like(attentionItems());
          for (let index = 0, $$length = each_array_4.length; index < $$length; index++) {
            let attention = each_array_4[index];
            $$renderer3.push(`<li>${escape_html(attention.owner ?? "Assigned owner")} · ${escape_html(attention.count ?? 0)} item${escape_html(attention.count === 1 ? "" : "s")}</li>`);
          }
          $$renderer3.push(`<!--]--></ul></div>`);
        } else {
          $$renderer3.push("<!--[-1-->");
        }
        $$renderer3.push(`<!--]--> `);
        if (allRecoveryItems().length > 0) {
          $$renderer3.push("<!--[0-->");
          $$renderer3.push(`<div class="space-y-3" data-omr-review-recovery=""><!--[-->`);
          const each_array_5 = ensure_array_like(allRecoveryItems());
          for (let index = 0, $$length = each_array_5.length; index < $$length; index++) {
            let recovery = each_array_5[index];
            OmrRecoveryCard($$renderer3, { recovery, onAction: recoveryHandler(), busy: recoveryBusy });
          }
          $$renderer3.push(`<!--]--></div>`);
        } else {
          $$renderer3.push("<!--[-1-->");
        }
        $$renderer3.push(`<!--]--> `);
        if (onComplete && (sheets().length > 0 || projection.execution_kind === "production" || projection.commands.some((c) => c.includes("complete") || c.includes("compare")))) {
          $$renderer3.push("<!--[0-->");
          OmrCompletionBar($$renderer3, { projection, onComplete: handleComplete });
        } else {
          $$renderer3.push("<!--[-1-->");
        }
        $$renderer3.push(`<!--]--> `);
        if (sheets().length > 0) {
          $$renderer3.push("<!--[0-->");
          $$renderer3.push(`<div${attr_class(clsx("flex min-w-0 flex-col gap-2"))} role="listbox" aria-label="OMR sheet queue" data-omr-sheet-queue=""><!--[-->`);
          const each_array_6 = ensure_array_like(sheets());
          for (let $$index_6 = 0, $$length = each_array_6.length; $$index_6 < $$length; $$index_6++) {
            let sheet = each_array_6[$$index_6];
            $$renderer3.push(`<button type="button" role="option"${attr("aria-selected", activeSheetId === sheet.id)}${attr("aria-label", `Review ${candidateLabel(sheet)}, sheet ${sheet.id}`)}${attr_class(`group border-border/60 bg-card hover:border-primary/40 hover:bg-muted/30 focus-visible:outline-primary border text-left transition-colors focus-visible:outline-2 focus-visible:outline-offset-2 ${"flex min-w-0 items-center gap-3 rounded-xl p-3"} ${activeSheetId === sheet.id ? "border-primary ring-primary/40 ring-1" : ""}`)} data-omr-sheet-option=""${attr("data-omr-sheet-id", sheet.id)}><div${attr_class(`border-border/60 bg-muted/30 relative shrink-0 overflow-hidden rounded-lg border ${"aspect-[3/4] w-14"}`)} aria-hidden="true">`);
            if (sheetThumbnailUrl(sheet)) {
              $$renderer3.push("<!--[0-->");
              $$renderer3.push(`<img${attr("src", sheetThumbnailUrl(sheet))} alt="" class="h-full w-full object-cover" loading="lazy"/>`);
            } else {
              $$renderer3.push("<!--[-1-->");
              $$renderer3.push(`<div class="text-muted-foreground flex h-full w-full flex-col items-center justify-center p-1">`);
              File_image($$renderer3, { class: "size-5 opacity-40" });
              $$renderer3.push(`<!----> <span class="mt-1 font-mono text-[8px] opacity-70">#${escape_html(sheet.id)}</span></div>`);
            }
            $$renderer3.push(`<!--]--> <div class="absolute top-1 right-1"><span${attr_class(`block size-2 shrink-0 rounded-full ${sheet.recovery ? "ring-background bg-amber-500 ring-2" : "ring-background bg-emerald-500 ring-2"}`)}></span></div></div> <div class="min-w-0 flex-1"><div${attr_class(`text-foreground group-hover:text-primary truncate font-sans font-bold ${"text-sm"}`)}${attr("title", sheetLabel(sheet))}>${escape_html(sheetLabel(sheet))}</div> `);
            if (candidateLabel(sheet) !== sheetLabel(sheet)) {
              $$renderer3.push("<!--[0-->");
              $$renderer3.push(`<div class="text-muted-foreground mt-0.5 truncate font-mono text-[11px]">${escape_html(candidateLabel(sheet))} · Sheet
                                                    #${escape_html(sheet.id)}</div>`);
            } else {
              $$renderer3.push("<!--[-1-->");
              $$renderer3.push(`<div class="text-muted-foreground mt-0.5 truncate font-mono text-[11px]">Sheet #${escape_html(sheet.id)}</div>`);
            }
            $$renderer3.push(`<!--]--> <span${attr_class(`mt-2 inline-flex rounded border px-2 py-0.5 font-mono text-[9px] font-bold tracking-wide uppercase ${sheet.recovery ? "border-amber-500/30 bg-amber-500/10 text-amber-600 dark:text-amber-400" : "border-emerald-500/20 bg-emerald-500/10 text-emerald-600 dark:text-emerald-400"}`)}>${escape_html(sheetStatus(sheet))}</span> <div class="mt-2 flex items-center justify-between gap-2"><span class="text-muted-foreground text-[10px]">${escape_html(sheet.recovery ? "Action required" : "Processed")}</span> <span class="text-primary shrink-0 cursor-pointer text-[11px] font-bold transition-transform group-hover:translate-x-0.5" role="button" tabindex="0">Review Sheet →</span></div></div></button>`);
          }
          $$renderer3.push(`<!--]--></div>`);
        } else {
          $$renderer3.push("<!--[-1-->");
          $$renderer3.push(`<div class="border-border/60 bg-muted/20 text-muted-foreground rounded-lg border border-dashed px-3 py-8 text-center text-sm" data-omr-sheet-queue-empty="">No sheets are in this run yet. Use the capture
                                controls to add one.</div>`);
        }
        $$renderer3.push(`<!--]--> <div class="border-border/60 bg-muted/20 text-muted-foreground flex flex-col gap-2 rounded-lg border px-3 py-2.5 text-xs leading-relaxed" data-omr-queue-progress="" role="status" aria-live="polite"><span>`);
        if (progressKnown()) {
          $$renderer3.push("<!--[0-->");
          $$renderer3.push(`${escape_html(progressCompleted())} of ${escape_html(progressTotal())} units processed`);
        } else if (progressCompleted() > 0) {
          $$renderer3.push("<!--[1-->");
          $$renderer3.push(`${escape_html(progressCompleted())} unit${escape_html(progressCompleted() === 1 ? "" : "s")} processed · total not yet known`);
        } else {
          $$renderer3.push("<!--[-1-->");
          $$renderer3.push(`Waiting for the server to report processed
                                    units`);
        }
        $$renderer3.push(`<!--]--></span> `);
        if (activeSheet()) {
          $$renderer3.push("<!--[0-->");
          $$renderer3.push(`<span class="text-foreground truncate font-medium">Selected: ${escape_html(sheetLabel(activeSheet()))}</span>`);
        } else {
          $$renderer3.push("<!--[-1-->");
        }
        $$renderer3.push(`<!--]--></div></div></section>`);
      }
      $$renderer3.push(`<!--]--></div></div> `);
      {
        $$renderer3.push("<!--[-1-->");
      }
      $$renderer3.push(`<!--]--> `);
      OmrShortcutsModal($$renderer3, {
        open: shortcutsModalOpen,
        onClose: () => shortcutsModalOpen = false
      });
      $$renderer3.push(`<!----></div>`);
    }
    do {
      $$settled = true;
      $$inner_renderer = $$renderer2.copy();
      $$render_inner($$inner_renderer);
    } while (!$$settled);
    $$renderer2.subsume($$inner_renderer);
  });
}
function OmrViewfinderHud($$renderer, $$props) {
  $$renderer.component(($$renderer2) => {
    let {
      result = null,
      available = true,
      manual = false,
      videoWidth = 0,
      videoHeight = 0
    } = $$props;
    const indicators = [
      { key: "in_bounds", label: "In frame" },
      { key: "angle_ok", label: "Angle" },
      { key: "steady_ok", label: "Steady" },
      { key: "lighting_ok", label: "Lighting" }
    ];
    const locked = derived(() => result !== null && Object.values(result.gates).every(Boolean));
    const status = derived(() => !available ? "Live guidance unavailable. Use the manual shutter." : manual ? "Manual shutter ready. Check the sheet before capturing." : !result?.quad ? "Looking for the answer sheet. Manual capture is available." : locked() ? "Sheet aligned. Hold steady for capture." : "Adjust the sheet until all four checks pass.");
    const polygon = derived(() => result?.quad?.map(({ x, y }) => `${x * videoWidth},${y * videoHeight}`).join(" ") ?? "");
    if (result?.quad && videoWidth > 0 && videoHeight > 0) {
      $$renderer2.push("<!--[0-->");
      $$renderer2.push(`<svg class="pointer-events-none absolute inset-0 z-10 size-full"${attr("viewBox", `0 0 ${videoWidth} ${videoHeight}`)} preserveAspectRatio="xMidYMid slice" aria-hidden="true" data-omr-viewfinder-polygon=""><polygon${attr("points", polygon())}${attr("fill", locked() ? "rgba(16, 185, 129, .12)" : "rgba(245, 158, 11, .11)")}${attr("stroke", locked() ? "#34d399" : "#fbbf24")}${attr("stroke-width", Math.max(2, Math.min(videoWidth, videoHeight) * 5e-3))} stroke-linejoin="round"></polygon></svg>`);
    } else {
      $$renderer2.push("<!--[-1-->");
    }
    $$renderer2.push(`<!--]--> <div class="pointer-events-none absolute right-3 bottom-3 z-20 w-[min(17rem,calc(100%-1.5rem))] rounded-xl border border-white/15 bg-[#101715]/90 p-2.5 text-white shadow-lg shadow-black/30" data-omr-viewfinder-hud=""><p class="text-xs leading-snug font-semibold" role="status" aria-live="polite">${escape_html(status())}</p> <div class="mt-2 grid grid-cols-2 gap-1.5" aria-label="Camera quality checks"><!--[-->`);
    const each_array = ensure_array_like(indicators);
    for (let $$index = 0, $$length = each_array.length; $$index < $$length; $$index++) {
      let indicator = each_array[$$index];
      const passed = result?.gates[indicator.key] === true;
      $$renderer2.push(`<span${attr_class(`rounded-md px-2 py-1 text-[11px] font-medium ${passed ? "bg-emerald-500/20 text-emerald-200" : "bg-amber-500/15 text-amber-100"}`)}${attr("data-omr-gate", indicator.key)}${attr("data-passed", passed)}>${escape_html(indicator.label)}: ${escape_html(passed ? "Ready" : "Adjust")}</span>`);
    }
    $$renderer2.push(`<!--]--></div></div>`);
  });
}
const FRAME_INTERVAL_MS = 100;
const LOCK_DURATION_MS = 300;
const ANALYSIS_EDGE = 320;
function createOmrViewfinderSession(options) {
  const createWorker = options.workerFactory ?? (() => new Worker(new URL("./omrViewfinderWorker.ts", import.meta.url), {
    type: "module"
  }));
  const now = options.now ?? (() => performance.now());
  let worker = null;
  let timer = null;
  let generation = 0;
  let frameId = 0;
  let inFlight = false;
  let autoEnabled = false;
  let lockedSince = null;
  let captureLatched = false;
  let capturePending = false;
  let latest = null;
  function clearLock() {
    lockedSince = null;
    captureLatched = false;
  }
  function stop() {
    generation++;
    if (timer !== null) {
      clearInterval(timer);
      timer = null;
    }
    worker?.terminate();
    worker = null;
    inFlight = false;
    frameId = 0;
    latest = null;
    clearLock();
    options.onResult(null);
  }
  async function capture(source) {
    if (capturePending || source === "auto" && captureLatched) {
      return false;
    }
    captureLatched = true;
    capturePending = true;
    const captureGeneration = generation;
    const gates = latest?.gates;
    const quad = latest?.quad && gates?.in_bounds && gates.angle_ok && gates.lighting_ok ? latest.quad : null;
    let succeeded = false;
    try {
      succeeded = await options.onCapture({ source, quad });
      return succeeded;
    } catch {
      return false;
    } finally {
      capturePending = false;
      if (!succeeded && captureGeneration === generation) {
        clearLock();
      }
    }
  }
  async function analyze() {
    const video = options.video();
    if (!worker || inFlight || capturePending || !video || video.readyState < HTMLMediaElement.HAVE_CURRENT_DATA || !video.videoWidth || !video.videoHeight) {
      return;
    }
    inFlight = true;
    const activeGeneration = generation;
    const capturedAt = now();
    const scale = Math.min(
      1,
      ANALYSIS_EDGE / Math.max(video.videoWidth, video.videoHeight)
    );
    try {
      const bitmap = await createImageBitmap(video, {
        resizeWidth: Math.round(video.videoWidth * scale),
        resizeHeight: Math.round(video.videoHeight * scale),
        resizeQuality: "medium"
      });
      if (!worker || activeGeneration !== generation) {
        bitmap.close();
        return;
      }
      const request = {
        type: "analyze",
        bitmap,
        capturedAt,
        generation,
        frameId: ++frameId,
        expectedAspectRatio: options.expectedAspectRatio ?? 210 / 297
      };
      try {
        worker.postMessage(request, [bitmap]);
      } catch (error) {
        bitmap.close();
        throw error;
      }
    } catch {
      if (activeGeneration === generation) {
        stop();
        options.onUnavailable?.();
      }
    }
  }
  function start() {
    stop();
    try {
      worker = createWorker();
    } catch {
      options.onUnavailable?.();
      return false;
    }
    const activeGeneration = generation;
    worker.onmessage = (event) => {
      if (activeGeneration !== generation || event.data.generation !== generation || event.data.frameId !== frameId) {
        return;
      }
      inFlight = false;
      latest = event.data;
      options.onResult(latest);
      const gates = latest.gates;
      const locked = latest.quad !== null && gates.in_bounds && gates.angle_ok && gates.steady_ok && gates.lighting_ok;
      if (!locked) {
        if (!capturePending) {
          clearLock();
        }
        return;
      }
      if (!autoEnabled || capturePending || captureLatched) {
        return;
      }
      const observedAt = now();
      if (lockedSince === null) {
        lockedSince = observedAt;
      } else if (observedAt - lockedSince >= LOCK_DURATION_MS) {
        void capture("auto");
      }
    };
    worker.onerror = () => {
      if (activeGeneration === generation) {
        stop();
        options.onUnavailable?.();
      }
    };
    timer = window.setInterval(() => void analyze(), FRAME_INTERVAL_MS);
    void analyze();
    return true;
  }
  return {
    start,
    stop,
    analyze,
    captureManual: () => capture("manual"),
    setAutoEnabled(enabled) {
      if (autoEnabled !== enabled) {
        autoEnabled = enabled;
        lockedSince = null;
        if (!capturePending) {
          captureLatched = false;
        }
      }
    },
    get generation() {
      return generation;
    }
  };
}
async function createOmrClientQuadHint(file, quad, width, height, pageNo) {
  if (!quad || !Number.isInteger(width) || !Number.isInteger(height) || width < 1 || height < 1 || !Number.isInteger(pageNo) || pageNo < 1 || pageNo > 4 || !globalThis.crypto?.subtle) {
    return null;
  }
  try {
    const digest = await crypto.subtle.digest(
      "SHA-256",
      await file.arrayBuffer()
    );
    const inputHash = Array.from(
      new Uint8Array(digest),
      (byte) => byte.toString(16).padStart(2, "0")
    ).join("");
    return {
      points: quad.map(({ x, y }) => [x, y]),
      width,
      height,
      orientation: 1,
      input_hash: inputHash,
      page_no: pageNo
    };
  } catch {
    return null;
  }
}
function appendOmrClientQuadHint(form, hint) {
  hint.points.forEach(([x, y], index) => {
    form.append(`client_quad[points][${index}][0]`, String(x));
    form.append(`client_quad[points][${index}][1]`, String(y));
  });
  form.append("client_quad[width]", String(hint.width));
  form.append("client_quad[height]", String(hint.height));
  form.append("client_quad[orientation]", String(hint.orientation));
  form.append("client_quad[input_hash]", hint.input_hash);
  form.append("client_quad[page_no]", String(hint.page_no));
}
function OmrPendingCaptureProgress($$renderer, $$props) {
  $$renderer.component(($$renderer2) => {
    let {
      pending,
      production = false,
      busy = false,
      roster = [],
      onRetake,
      onAdopt,
      onConfirmIdentity
    } = $$props;
    let manualSelections = {};
    const pages = derived(() => Array.isArray(pending.pages) ? pending.pages : []);
    const captured = derived(() => pages().filter((page) => (page.capture_count ?? 0) > 0).length);
    const observed = derived(() => pages().find((page) => page.detected_applicant_number)?.detected_applicant_number ?? null);
    const paired = derived(() => pending.status === "ready" && !pending.pairing_code);
    const needsManualIdentity = derived(() => pending.identity_mode === "write_in_manual" && pending.status === "captured" && (pending.pairing_code === "identity_review_required" || pending.pairing_code === "pairing_conflict"));
    const needsPairing = derived(() => pending.pairing_code === "pairing_missing" || pending.pairing_code === "pairing_conflict");
    const canSubmitManualIdentity = derived(() => needsManualIdentity() && pages().length > 0 && pages().every((page) => Boolean((manualSelections[page.page_no] ?? page.manual_applicant_number ?? "").trim())));
    function pageState(page) {
      if (page.recognition_code || page.capture_count && !page.detected_applicant_number) return "Needs review";
      if (page.capture_count) return "Captured";
      return page.active ? "Current" : "Waiting";
    }
    $$renderer2.push(`<section class="border-border bg-card min-w-0 rounded-xl border p-4 sm:p-5" aria-label="Current examinee page progress" data-omr-pending-pages=""><div class="flex flex-wrap items-start justify-between gap-3"><div><h3 class="text-sm font-semibold">Current examinee · ${escape_html(captured())} of ${escape_html(pending.page_count)} pages captured</h3> <p class="text-muted-foreground mt-1 text-xs">`);
    if (observed()) {
      $$renderer2.push("<!--[0-->");
      $$renderer2.push(`Observed Applicant Number: <strong class="text-foreground">${escape_html(observed())}</strong>`);
    } else {
      $$renderer2.push("<!--[-1-->");
      $$renderer2.push(`Applicant Number not yet confirmed.`);
    }
    $$renderer2.push(`<!--]--> Each page must show the same number before review.</p></div> <span class="border-border bg-muted/40 rounded-full border px-2.5 py-1 text-xs font-medium" role="status" aria-live="polite">`);
    if (pending.adopted_sheet_id) {
      $$renderer2.push("<!--[0-->");
      $$renderer2.push(`Ready for sheet review`);
    } else if (pending.resolution_code) {
      $$renderer2.push("<!--[1-->");
      $$renderer2.push(`Adoption blocked`);
    } else if (pending.pairing_code === "identity_review_required") {
      $$renderer2.push("<!--[2-->");
      $$renderer2.push(`Write-in confirmation required`);
    } else if (needsPairing()) {
      $$renderer2.push("<!--[3-->");
      $$renderer2.push(`Pairing needs attention`);
    } else if (paired()) {
      $$renderer2.push("<!--[4-->");
      $$renderer2.push(`${escape_html(production ? "Ready to adopt" : "Pages paired · diagnostic only")}`);
    } else {
      $$renderer2.push("<!--[-1-->");
      $$renderer2.push(`Page ${escape_html(pending.active_page_no)} of ${escape_html(pending.page_count)}`);
    }
    $$renderer2.push(`<!--]--></span></div> <ol class="mt-4 flex flex-wrap gap-2" aria-label="Page capture status"><!--[-->`);
    const each_array = ensure_array_like(pages());
    for (let $$index = 0, $$length = each_array.length; $$index < $$length; $$index++) {
      let page = each_array[$$index];
      $$renderer2.push(`<li${attr_class(`border-border bg-background flex min-w-28 items-center gap-2 rounded-lg border px-3 py-2 text-xs ${page.active && pending.status === "pending" ? "border-primary" : ""}`)}>`);
      if (page.capture_count && !page.recognition_code) {
        $$renderer2.push("<!--[0-->");
        Check($$renderer2, {
          class: "size-4 text-emerald-600 dark:text-emerald-400",
          "aria-hidden": "true"
        });
      } else {
        $$renderer2.push("<!--[-1-->");
        $$renderer2.push(`<span class="text-muted-foreground font-semibold">${escape_html(page.page_no)}</span>`);
      }
      $$renderer2.push(`<!--]--> <span><strong>Page ${escape_html(page.page_no)}</strong><span class="text-muted-foreground block">${escape_html(pageState(page))}${escape_html(page.detected_applicant_number ? ` · ${page.detected_applicant_number}` : "")}</span></span> `);
      if (page.capture_count && !pending.adopted_sheet_id && onRetake) {
        $$renderer2.push("<!--[0-->");
        $$renderer2.push(`<button type="button" class="text-primary focus-visible:outline-primary ml-auto rounded px-1 py-1 font-medium underline-offset-2 hover:underline focus-visible:outline-2 focus-visible:outline-offset-2"${attr("disabled", busy, true)}${attr("aria-label", `Retake page ${page.page_no}`)}>Retake</button>`);
      } else {
        $$renderer2.push("<!--[-1-->");
      }
      $$renderer2.push(`<!--]--></li>`);
    }
    $$renderer2.push(`<!--]--></ol> `);
    {
      $$renderer2.push("<!--[-1-->");
    }
    $$renderer2.push(`<!--]--> `);
    if (needsPairing()) {
      $$renderer2.push("<!--[0-->");
      $$renderer2.push(`<p class="mt-3 rounded-lg border border-amber-500/30 bg-amber-500/10 px-3 py-2 text-sm text-amber-900 dark:text-amber-200" role="alert">${escape_html(pending.pairing_code === "pairing_missing" ? "Applicant Number is missing on at least one page." : "The pages show different Applicant Numbers.")}
            Compare the per-page results above, then retake the affected page. No
            applicant or score is attached.</p>`);
    } else {
      $$renderer2.push("<!--[-1-->");
    }
    $$renderer2.push(`<!--]--> `);
    if (needsManualIdentity() && onConfirmIdentity) {
      $$renderer2.push("<!--[0-->");
      $$renderer2.push(`<form class="border-border bg-muted/30 mt-3 space-y-3 rounded-lg border p-3" data-omr-manual-identity-form=""><p class="text-xs font-medium">Confirm the write-in Applicant Number on every page before adoption:</p> <div class="grid gap-2 sm:grid-cols-2"><!--[-->`);
      const each_array_1 = ensure_array_like(pages());
      for (let $$index_2 = 0, $$length = each_array_1.length; $$index_2 < $$length; $$index_2++) {
        let page = each_array_1[$$index_2];
        $$renderer2.push(`<label class="text-xs font-medium"${attr("for", `manual-id-page-${page.page_no}`)}>Page ${escape_html(page.page_no)} examinee `);
        if (page.write_in_region) {
          $$renderer2.push("<!--[0-->");
          $$renderer2.push(`<span class="text-muted-foreground font-normal">(region ${escape_html(page.write_in_region.x)},${escape_html(page.write_in_region.y)} · ${escape_html(page.write_in_region.w)}×${escape_html(page.write_in_region.h)}px)</span>`);
        } else {
          $$renderer2.push("<!--[-1-->");
        }
        $$renderer2.push(`<!--]--> `);
        if (roster.length > 0) {
          $$renderer2.push("<!--[0-->");
          $$renderer2.select(
            {
              id: `manual-id-page-${page.page_no}`,
              class: "border-input bg-background text-foreground mt-1 w-full rounded-md border px-2.5 py-1.5 text-sm",
              value: manualSelections[page.page_no] ?? page.manual_applicant_number ?? "",
              disabled: busy,
              onchange: (event) => {
                manualSelections = {
                  ...manualSelections,
                  [page.page_no]: event.currentTarget.value
                };
              }
            },
            ($$renderer3) => {
              $$renderer3.option({ value: "" }, ($$renderer4) => {
                $$renderer4.push(`Select eligible examinee`);
              });
              $$renderer3.push(`<!--[-->`);
              const each_array_2 = ensure_array_like(roster);
              for (let $$index_1 = 0, $$length2 = each_array_2.length; $$index_1 < $$length2; $$index_1++) {
                let candidate = each_array_2[$$index_1];
                if (candidate.applicant_number) {
                  $$renderer3.push("<!--[0-->");
                  $$renderer3.option({ value: candidate.applicant_number }, ($$renderer4) => {
                    $$renderer4.push(`${escape_html(candidate.applicant_number)} · ${escape_html(candidate.name ?? candidate.reference_code ?? "Examinee")}`);
                  });
                } else {
                  $$renderer3.push("<!--[-1-->");
                }
                $$renderer3.push(`<!--]-->`);
              }
              $$renderer3.push(`<!--]-->`);
            }
          );
        } else {
          $$renderer2.push("<!--[-1-->");
          $$renderer2.push(`<input${attr("id", `manual-id-page-${page.page_no}`)} class="border-input bg-background text-foreground mt-1 w-full rounded-md border px-2.5 py-1.5 text-sm"${attr("value", manualSelections[page.page_no] ?? page.manual_applicant_number ?? "")}${attr("disabled", busy, true)} placeholder="Enter Applicant Number"/>`);
        }
        $$renderer2.push(`<!--]--></label>`);
      }
      $$renderer2.push(`<!--]--></div> <button type="submit" class="bg-primary text-primary-foreground rounded-md px-3 py-2 text-xs font-semibold disabled:opacity-50"${attr("disabled", busy || !canSubmitManualIdentity(), true)}>Confirm write-in Applicant Number</button></form>`);
    } else {
      $$renderer2.push("<!--[-1-->");
    }
    $$renderer2.push(`<!--]--> `);
    if (pending.recovery) {
      $$renderer2.push("<!--[0-->");
      $$renderer2.push(`<div class="mt-3 rounded-lg border border-amber-500/30 bg-amber-500/10 px-3 py-2 text-sm text-amber-900 dark:text-amber-200" role="alert"><strong>${escape_html(pending.recovery.title ?? "Adoption needs attention")}</strong> ${escape_html(pending.recovery.explanation ?? "")} `);
      if (pending.recovery.owner) {
        $$renderer2.push("<!--[0-->");
        $$renderer2.push(`<span class="mt-1 block text-xs">Owner: ${escape_html(pending.recovery.owner)}</span>`);
      } else {
        $$renderer2.push("<!--[-1-->");
      }
      $$renderer2.push(`<!--]--></div>`);
    } else {
      $$renderer2.push("<!--[-1-->");
    }
    $$renderer2.push(`<!--]--> `);
    if (paired() && production && onAdopt && !pending.adopted_sheet_id) {
      $$renderer2.push("<!--[0-->");
      $$renderer2.push(`<button type="button" class="bg-primary text-primary-foreground mt-3 rounded-md px-3 py-2 text-sm font-semibold disabled:opacity-50"${attr("disabled", busy, true)}>${escape_html(busy ? "Checking adoption…" : pending.resolution_code ? "Retry adoption" : "Continue to sheet review")}</button>`);
    } else {
      $$renderer2.push("<!--[-1-->");
    }
    $$renderer2.push(`<!--]--> `);
    if (paired() && !production) {
      $$renderer2.push("<!--[0-->");
      $$renderer2.push(`<p class="text-muted-foreground mt-3 text-xs" role="status">This is an isolated OMR Test capture. It cannot create production
            paper claims or scores.</p>`);
    } else {
      $$renderer2.push("<!--[-1-->");
    }
    $$renderer2.push(`<!--]--></section>`);
  });
}
class OmrWorkbenchClientError extends Error {
  kind;
  code;
  status;
  details;
  constructor(kind, message, init = {}) {
    super(
      message,
      init.cause === void 0 ? void 0 : { cause: init.cause }
    );
    this.name = "OmrWorkbenchClientError";
    this.kind = kind;
    this.code = init.code ?? kind;
    this.status = init.status ?? 0;
    this.details = init.details ?? null;
  }
}
class OmrWorkbenchStaleError extends OmrWorkbenchClientError {
  constructor(message = "The OMR workbench is stale.", init = {}) {
    super("stale", message, init);
    this.name = "OmrWorkbenchStaleError";
  }
}
class OmrWorkbenchDeniedError extends OmrWorkbenchClientError {
  constructor(message = "The OMR workbench command was denied.", init = {}) {
    super("denied", message, init);
    this.name = "OmrWorkbenchDeniedError";
  }
}
class OmrWorkbenchInvalidError extends OmrWorkbenchClientError {
  constructor(message = "The OMR workbench request was invalid.", init = {}) {
    super("invalid", message, init);
    this.name = "OmrWorkbenchInvalidError";
  }
}
class OmrWorkbenchExpiredError extends OmrWorkbenchClientError {
  constructor(message = "The OMR workbench continuation has expired.", init = {}) {
    super("expired", message, init);
    this.name = "OmrWorkbenchExpiredError";
  }
}
class OmrWorkbenchConflictError extends OmrWorkbenchClientError {
  constructor(message = "The OMR workbench has a concurrent conflict.", init = {}) {
    super("conflict", message, init);
    this.name = "OmrWorkbenchConflictError";
  }
}
const COMMAND_CAPTURE_START = "capture_start";
const COMMAND_CAPTURE_RETRY = "capture_retry";
const COMMAND_REVIEW_SAVE = "review_save";
const COMMAND_RESOLVE_APPLICANT = "resolve_applicant";
const COMMAND_RESOLVE_DUPLICATE = "resolve_duplicate";
const COMMAND_DIAGNOSTIC_COMPARE = "diagnostic_compare";
const COMMAND_DIAGNOSTIC_COMPLETE = "diagnostic_complete";
const COMMAND_PRODUCTION_COMPLETE = "production_complete";
const RESERVED_CONTEXT_FIELDS = /* @__PURE__ */ new Set([
  "workbench_id",
  "execution_kind",
  "kind",
  "mode",
  "run_id",
  "batch_id",
  "actor_id",
  "session_id",
  "grading_session_id",
  "route_session_id",
  "route_context",
  "context"
]);
function createOmrWorkbenchClient(config) {
  const workbenchId = assertWorkbenchId(config.workbenchId);
  const executionKind = assertExecutionKind(config.executionKind);
  const urls = freezeUrls(config.urls);
  const fetchImpl = config.fetchImpl ?? ((input, init) => fetch(input, init));
  const credentials = config.credentials ?? "same-origin";
  let latestProjection = null;
  const rememberProjection = (value) => {
    const projection = unwrapProjection(value);
    if (!isOmrWorkbenchProjection(projection)) {
      throw new OmrWorkbenchInvalidError(
        "The OMR workbench returned a malformed projection.",
        { code: "projection_invalid", details: value }
      );
    }
    if (projection.workbench_id !== workbenchId || projection.execution_kind !== executionKind) {
      throw new OmrWorkbenchInvalidError(
        "The OMR workbench projection does not match this route context.",
        {
          code: "projection_identity_mismatch",
          details: projection
        }
      );
    }
    if (latestProjection !== null && projection.revision < latestProjection.revision) {
      throw new OmrWorkbenchStaleError(
        "The OMR workbench returned an older revision.",
        {
          code: "projection_revision_stale",
          details: projection
        }
      );
    }
    latestProjection = projection;
    return projection;
  };
  const assertCommand = (command) => {
    if (latestProjection === null) {
      return;
    }
    if (!latestProjection.commands.includes(command)) {
      throw new OmrWorkbenchDeniedError(
        "The requested OMR command is not available in the current projection.",
        { code: "command_not_projected", details: { command } }
      );
    }
  };
  const assertRevision = (revision) => {
    if (!Number.isInteger(revision) || revision < 1) {
      throw new OmrWorkbenchInvalidError(
        "The expected OMR workbench revision must be a positive integer.",
        { code: "revision_invalid" }
      );
    }
    if (latestProjection !== null && revision !== latestProjection.revision) {
      throw new OmrWorkbenchStaleError(
        "The expected OMR workbench revision is no longer current.",
        {
          code: "revision_stale",
          details: {
            expected: revision,
            current: latestProjection.revision
          }
        }
      );
    }
    return revision;
  };
  const load = async () => {
    const showUrl = exactUrl(urls.show, "show");
    return requestProjection(showUrl, {
      method: "GET",
      fetchImpl,
      credentials,
      headers: config.headers,
      csrfToken: config.csrfToken,
      rememberProjection
    });
  };
  const captureStart = async (file, payload, revision) => {
    assertCommand(COMMAND_CAPTURE_START);
    const expectedRevision = assertRevision(revision);
    const captureUrl = exactUrl(urls.capture, "capture");
    const capturePayload = withExpectedRevisionField(
      payload,
      expectedRevision,
      "expected_revision"
    );
    const body = toCaptureFormData(file, capturePayload);
    return requestProjection(captureUrl, {
      method: "POST",
      body,
      fetchImpl,
      credentials,
      headers: config.headers,
      csrfToken: config.csrfToken,
      rememberProjection
    });
  };
  const captureRetry = async (captureId, payload, revision) => {
    assertCommand(COMMAND_CAPTURE_RETRY);
    const expectedRevision = assertRevision(revision);
    const retryUrl = exactUrlForResource(urls.retry, "retry", captureId);
    const retryPayload = withExpectedRevisionField(
      payload,
      expectedRevision,
      "expected_revision"
    );
    const body = toJsonBody(
      executionKind === "diagnostic" ? withResourceId(retryPayload, "capture_id", captureId) : withResourceId(retryPayload, "sheet_id", captureId)
    );
    return requestProjection(retryUrl, {
      method: "POST",
      body,
      fetchImpl,
      credentials,
      headers: config.headers,
      csrfToken: config.csrfToken,
      rememberProjection
    });
  };
  const reviewSave = async (itemId, payload, revision) => {
    assertCommand(COMMAND_REVIEW_SAVE);
    const expectedRevision = assertRevision(revision);
    const reviewPayload = withExpectedRevisionField(
      payload,
      expectedRevision,
      executionKind === "diagnostic" ? "expected_run_revision" : "expected_revision"
    );
    const reviewBody = executionKind === "diagnostic" ? withResourceId(reviewPayload, "item_evidence_id", itemId) : withResourceId(reviewPayload, "sheet_id", itemId);
    const reviewUrl = exactUrlForResource(urls.review, "review", itemId);
    const body = toJsonBody(reviewBody);
    return requestProjection(reviewUrl, {
      method: "POST",
      body,
      fetchImpl,
      credentials,
      headers: config.headers,
      csrfToken: config.csrfToken,
      rememberProjection
    });
  };
  const resolveApplicant = async (sheetId, payload, revision) => {
    assertKind(executionKind, "production", "resolveApplicant");
    assertCommand(COMMAND_RESOLVE_APPLICANT);
    const expectedRevision = assertRevision(revision);
    const resolvePayload = withResourceId(
      withExpectedRevisionField(
        payload,
        expectedRevision,
        "expected_revision"
      ),
      "sheet_id",
      sheetId
    );
    const resolveUrl = exactUrlForResource(
      urls.resolveApplicant,
      "resolveApplicant",
      sheetId
    );
    return requestProjection(resolveUrl, {
      method: "POST",
      body: toJsonBody(resolvePayload),
      fetchImpl,
      credentials,
      headers: config.headers,
      csrfToken: config.csrfToken,
      rememberProjection
    });
  };
  const resolveDuplicate = async (survivorSheetId, payload, revision) => {
    assertKind(executionKind, "production", "resolveDuplicate");
    assertCommand(COMMAND_RESOLVE_DUPLICATE);
    const expectedRevision = assertRevision(revision);
    const resolvePayload = withResourceId(
      withExpectedRevisionField(
        payload,
        expectedRevision,
        "expected_revision"
      ),
      "survivor_sheet_id",
      survivorSheetId
    );
    const resolveUrl = exactUrlForResource(
      urls.resolveDuplicate,
      "resolveDuplicate",
      survivorSheetId
    );
    return requestProjection(resolveUrl, {
      method: "POST",
      body: toJsonBody(resolvePayload),
      fetchImpl,
      credentials,
      headers: config.headers,
      csrfToken: config.csrfToken,
      rememberProjection
    });
  };
  const diagnosticCompare = async (revision) => {
    assertKind(executionKind, "diagnostic", "diagnosticCompare");
    assertCommand(COMMAND_DIAGNOSTIC_COMPARE);
    const expectedRevision = assertRevision(revision);
    const body = toJsonBody({
      expected_revision: expectedRevision,
      idempotency_key: newIdempotencyKey()
    });
    const compareUrl = exactUrl(urls.compare, "compare");
    return requestProjection(compareUrl, {
      method: "POST",
      body,
      fetchImpl,
      credentials,
      headers: config.headers,
      csrfToken: config.csrfToken,
      rememberProjection
    });
  };
  const diagnosticComplete = async (revision) => {
    assertKind(executionKind, "diagnostic", "diagnosticComplete");
    assertCommand(COMMAND_DIAGNOSTIC_COMPLETE);
    const expectedRevision = assertRevision(revision);
    const body = toJsonBody({
      expected_revision: expectedRevision,
      idempotency_key: newIdempotencyKey()
    });
    const completeUrl = exactUrl(urls.complete, "complete");
    return requestProjection(completeUrl, {
      method: "POST",
      body,
      fetchImpl,
      credentials,
      headers: config.headers,
      csrfToken: config.csrfToken,
      rememberProjection
    });
  };
  const productionComplete = async (revision) => {
    assertKind(executionKind, "production", "productionComplete");
    assertCommand(COMMAND_PRODUCTION_COMPLETE);
    const expectedRevision = assertRevision(revision);
    const body = toJsonBody({
      expected_revision: expectedRevision,
      idempotency_key: newIdempotencyKey()
    });
    const completeUrl = exactUrl(urls.complete, "complete");
    return requestProjection(completeUrl, {
      method: "POST",
      body,
      fetchImpl,
      credentials,
      headers: config.headers,
      csrfToken: config.csrfToken,
      rememberProjection
    });
  };
  return Object.freeze({
    workbenchId,
    executionKind,
    load,
    captureStart,
    captureRetry,
    reviewSave,
    resolveApplicant,
    resolveDuplicate,
    diagnosticCompare,
    diagnosticComplete,
    productionComplete
  });
}
async function requestProjection(url, options) {
  const headers = new Headers(options.headers);
  const csrf = csrfHeaders(options.csrfToken ?? "");
  for (const [name, value] of Object.entries(csrf)) {
    headers.set(name, value);
  }
  headers.set("Accept", "application/json");
  headers.set("X-Requested-With", "XMLHttpRequest");
  if (options.body instanceof FormData) {
    headers.delete("Content-Type");
  } else if (options.body !== void 0) {
    headers.set("Content-Type", "application/json");
  }
  let response;
  try {
    response = await options.fetchImpl(url, {
      method: options.method,
      headers,
      credentials: options.credentials,
      body: options.body
    });
  } catch (cause) {
    throw new OmrWorkbenchInvalidError(
      "The OMR workbench request could not be sent.",
      { code: "transport_unavailable", cause }
    );
  }
  let body;
  try {
    body = await readResponseBody(response);
  } catch (error) {
    if (!response.ok) {
      throw errorFromResponse(response.status, null);
    }
    throw error;
  }
  if (!response.ok) {
    throw errorFromResponse(response.status, body);
  }
  return options.rememberProjection(body);
}
async function readResponseBody(response) {
  let text;
  try {
    text = await response.text();
  } catch (cause) {
    throw new OmrWorkbenchInvalidError(
      "The OMR workbench response could not be read.",
      { code: "response_unreadable", status: response.status, cause }
    );
  }
  if (text.trim() === "") {
    return null;
  }
  try {
    return JSON.parse(text);
  } catch (cause) {
    throw new OmrWorkbenchInvalidError(
      "The OMR workbench returned invalid JSON.",
      { code: "response_json_invalid", status: response.status, cause }
    );
  }
}
function errorFromResponse(status, body) {
  const code = responseCode(body);
  const message = responseMessage(body, code);
  const kind = classifyError(status, code);
  const init = { code, status, details: body };
  switch (kind) {
    case "stale":
      return new OmrWorkbenchStaleError(message, init);
    case "denied":
      return new OmrWorkbenchDeniedError(message, init);
    case "expired":
      return new OmrWorkbenchExpiredError(message, init);
    case "conflict":
      return new OmrWorkbenchConflictError(message, init);
    case "invalid":
    default:
      return new OmrWorkbenchInvalidError(message, init);
  }
}
function classifyError(status, code) {
  const normalized = code.toLowerCase();
  if (status === 410 || status === 419 || /(expired|expiration|continuation_timeout|timed_out|token_expired)/u.test(
    normalized
  )) {
    return "expired";
  }
  if (status === 401 || status === 403 || /(denied|forbidden|unauthori[sz]ed|capability|not_allowed|not_projected)/u.test(
    normalized
  )) {
    return "denied";
  }
  if (/(stale|revision|outdated|superseded|context_changed)/u.test(normalized)) {
    return "stale";
  }
  if (status === 409 || /(conflict|concurren|duplicate|already_processed|version_mismatch)/u.test(
    normalized
  )) {
    return "conflict";
  }
  return "invalid";
}
function responseCode(body) {
  if (!isRecord(body)) {
    return "workbench_request_failed";
  }
  const directCode = body.code;
  if (typeof directCode === "string" && directCode.trim() !== "") {
    return directCode.trim();
  }
  const error = body.error;
  if (typeof error === "string" && error.trim() !== "") {
    return error.trim();
  }
  if (isRecord(error) && typeof error.code === "string" && error.code.trim() !== "") {
    return error.code.trim();
  }
  return "workbench_request_failed";
}
function responseMessage(body, code) {
  if (isRecord(body)) {
    if (typeof body.message === "string" && body.message.trim() !== "") {
      return body.message;
    }
    if (typeof body.error === "string" && body.error.trim() !== "") {
      return body.error;
    }
    if (isRecord(body.error) && typeof body.error.message === "string") {
      return body.error.message;
    }
  }
  return `OMR workbench request failed (${code}).`;
}
function unwrapProjection(value) {
  if (!isRecord(value)) {
    return value;
  }
  const projection = value.projection;
  return isRecord(projection) ? projection : value;
}
function assertWorkbenchId(value) {
  if (typeof value !== "string" || value.trim() === "") {
    throw new OmrWorkbenchInvalidError(
      "The OMR workbench identity must be a non-empty string.",
      { code: "workbench_id_invalid" }
    );
  }
  return value;
}
function assertExecutionKind(value) {
  if (value !== "production" && value !== "diagnostic") {
    throw new OmrWorkbenchInvalidError(
      "The OMR execution kind is unsupported.",
      { code: "execution_kind_invalid" }
    );
  }
  return value;
}
function assertKind(actual, expected, method) {
  if (actual !== expected) {
    throw new OmrWorkbenchDeniedError(
      `${method} is unavailable for the ${actual} OMR workbench.`,
      { code: "execution_kind_mismatch" }
    );
  }
}
function exactUrl(value, method) {
  if (typeof value !== "string" || value.trim() === "") {
    throw new OmrWorkbenchInvalidError(
      `No URL is configured for ${method}.`,
      { code: "url_not_configured" }
    );
  }
  return value;
}
function exactUrlForResource(value, method, resourceId) {
  if (typeof value === "function") {
    const resolved = value(resourceId);
    return exactUrl(resolved, method);
  }
  return exactUrl(value, method);
}
function freezeUrls(value) {
  if (value === null || typeof value !== "object") {
    throw new OmrWorkbenchInvalidError(
      "The OMR workbench URL configuration is invalid.",
      { code: "urls_invalid" }
    );
  }
  return Object.freeze({ ...value });
}
function withExpectedRevisionField(payload, revision, acceptedField) {
  const normalized = normalizePayload(payload);
  assertNoRevisionAliases(normalized, acceptedField);
  assertPayloadRevision(normalized, acceptedField, revision);
  normalized[acceptedField] = revision;
  return normalized;
}
function assertNoRevisionAliases(payload, acceptedField) {
  const revisionFields = [
    "expected_revision",
    "expected_run_revision",
    "expected_version",
    "expected_parent_version"
  ];
  for (const field of revisionFields) {
    if (field !== acceptedField && Object.prototype.hasOwnProperty.call(payload, field)) {
      throw new OmrWorkbenchInvalidError(
        `The OMR command cannot send ${field} for this endpoint.`,
        { code: "revision_field_invalid", details: { field } }
      );
    }
  }
}
function withResourceId(payload, field, resourceId) {
  const provided = payload[field];
  if (provided !== void 0 && String(provided) !== String(resourceId)) {
    throw new OmrWorkbenchInvalidError(
      `The OMR command resource ${field} does not match the route.`,
      { code: "resource_id_mismatch", details: { field } }
    );
  }
  return { ...payload, [field]: resourceId };
}
function normalizePayload(payload) {
  if (!isRecord(payload) || Array.isArray(payload)) {
    throw new OmrWorkbenchInvalidError(
      "The OMR command payload must be a plain object.",
      { code: "payload_invalid" }
    );
  }
  const normalized = { ...payload };
  for (const key of Object.keys(normalized)) {
    if (RESERVED_CONTEXT_FIELDS.has(key)) {
      throw new OmrWorkbenchInvalidError(
        `The OMR command payload cannot set ${key}.`,
        { code: "payload_context_forbidden", details: { key } }
      );
    }
  }
  return normalized;
}
function assertPayloadRevision(payload, field, revision) {
  const provided = payload[field];
  if (provided !== void 0 && provided !== revision) {
    throw new OmrWorkbenchStaleError(
      "The command payload revision does not match the expected revision.",
      {
        code: "payload_revision_stale",
        details: { field, provided, expected: revision }
      }
    );
  }
}
function toCaptureFormData(file, payload) {
  if (!(typeof Blob !== "undefined" && file instanceof Blob)) {
    throw new OmrWorkbenchInvalidError(
      "The OMR capture must be a Blob or File.",
      { code: "capture_file_invalid" }
    );
  }
  if (Object.prototype.hasOwnProperty.call(payload, "image")) {
    throw new OmrWorkbenchInvalidError(
      "The OMR capture image is supplied by the file argument.",
      { code: "capture_image_duplicate" }
    );
  }
  const form = new FormData();
  form.append("image", file);
  for (const [key, value] of Object.entries(payload)) {
    if (value === void 0) {
      continue;
    }
    if (value === null) {
      form.append(key, "");
      continue;
    }
    if (typeof Blob !== "undefined" && value instanceof Blob) {
      form.append(key, value);
      continue;
    }
    if (key === "client_quad" && typeof value === "object") {
      appendOmrClientQuadHint(form, value);
      continue;
    }
    if (typeof value === "object") {
      try {
        form.append(key, JSON.stringify(value));
      } catch (cause) {
        throw new OmrWorkbenchInvalidError(
          `The OMR command field ${key} cannot be serialized.`,
          { code: "payload_serialization_failed", cause }
        );
      }
      continue;
    }
    form.append(key, String(value));
  }
  return form;
}
function toJsonBody(payload) {
  try {
    return JSON.stringify(payload);
  } catch (cause) {
    throw new OmrWorkbenchInvalidError(
      "The OMR command payload cannot be serialized as JSON.",
      { code: "payload_serialization_failed", cause }
    );
  }
}
function isRecord(value) {
  return value !== null && typeof value === "object" && !Array.isArray(value);
}
export {
  OmrWorkbench as O,
  OmrPendingCaptureProgress as a,
  OmrViewfinderHud as b,
  createOmrViewfinderSession as c,
  buildOmrConsoleReviewPayload as d,
  productionItemReviewUrl as e,
  assertProductionItemReviewAcknowledged as f,
  createOmrWorkbenchClient as g,
  acceptOmrWorkbenchProjection as h,
  isOmrWorkbenchProjection as i,
  createOmrClientQuadHint as j,
  assertDiagnosticItemReviewAcknowledged as k,
  productionItemReviewKey as p
};
