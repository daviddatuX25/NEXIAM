import { g as sanitize_props, i as spread_props, j as slot, c as attr_class, A as clsx, e as escape_html, o as onDestroy, v as error, b as attr, a as ensure_array_like, B as Button, f as attr_style, d as derived, a3 as csrfToken, a4 as warning, u as usePage, t as tick, q as success, r as run } from "../app.js";
import { I as Icon } from "./Icon-Dal7PqKK.js";
import { I as Input } from "./input-BUZAZKIK.js";
import { L as Label } from "./label-dGY6L9vm.js";
import { P as PlaceholderPicker, R as ResultSheetStudio } from "./PlaceholderPicker-CH_aVvK3.js";
import { u as useForm } from "./useForm.svelte-C0OkDFGu.js";
import { C as Chevron_left } from "./chevron-left-DCSOZwZl.js";
import { C as Chevron_right } from "./chevron-right-BHu-PGOg.js";
import { C as Chevron_down } from "./chevron-down-CkQQZIhu.js";
import { S as Save } from "./save-DAlMQk-Q.js";
import { R as Rotate_ccw } from "./rotate-ccw-D6Dm_efx.js";
import { T as Trash_2 } from "./trash-2-CjFp3otc.js";
import { C as Circle_check } from "./circle-check-C59ZS7Zv.js";
import { S as Shield_alert } from "./shield-alert-CXtDdte9.js";
import { F as File_text } from "./file-text-PXEtmXm9.js";
import { R as Refresh_cw } from "./refresh-cw-CYdk8aE0.js";
import { C as Circle_alert } from "./circle-alert-jWXJ7oC0.js";
import { P as Play } from "./play-DnSz4pBE.js";
import { T as Triangle_alert } from "./triangle-alert-CUvuB-HL.js";
import { router } from "@inertiajs/core";
function File_output($$renderer, $$props) {
  const $$sanitized_props = sanitize_props($$props);
  const iconNode = [
    [
      "path",
      {
        "d": "M4.226 20.925A2 2 0 0 0 6 22h12a2 2 0 0 0 2-2V8a2.4 2.4 0 0 0-.706-1.706l-3.588-3.588A2.4 2.4 0 0 0 14 2H6a2 2 0 0 0-2 2v3.127"
      }
    ],
    ["path", { "d": "M14 2v5a1 1 0 0 0 1 1h5" }],
    ["path", { "d": "m5 11-3 3" }],
    ["path", { "d": "m5 17-3-3h10" }]
  ];
  Icon($$renderer, spread_props([
    { name: "file-output" },
    $$sanitized_props,
    {
      /**
       * @component @name FileOutput
       * @description Lucide SVG icon component, renders SVG Element with children.
       *
       * @preview ![img](data:image/svg+xml;base64,PHN2ZyAgeG1sbnM9Imh0dHA6Ly93d3cudzMub3JnLzIwMDAvc3ZnIgogIHdpZHRoPSIyNCIKICBoZWlnaHQ9IjI0IgogIHZpZXdCb3g9IjAgMCAyNCAyNCIKICBmaWxsPSJub25lIgogIHN0cm9rZT0iIzAwMCIgc3R5bGU9ImJhY2tncm91bmQtY29sb3I6ICNmZmY7IGJvcmRlci1yYWRpdXM6IDJweCIKICBzdHJva2Utd2lkdGg9IjIiCiAgc3Ryb2tlLWxpbmVjYXA9InJvdW5kIgogIHN0cm9rZS1saW5lam9pbj0icm91bmQiCj4KICA8cGF0aCBkPSJNNC4yMjYgMjAuOTI1QTIgMiAwIDAgMCA2IDIyaDEyYTIgMiAwIDAgMCAyLTJWOGEyLjQgMi40IDAgMCAwLS43MDYtMS43MDZsLTMuNTg4LTMuNTg4QTIuNCAyLjQgMCAwIDAgMTQgMkg2YTIgMiAwIDAgMC0yIDJ2My4xMjciIC8+CiAgPHBhdGggZD0iTTE0IDJ2NWExIDEgMCAwIDAgMSAxaDUiIC8+CiAgPHBhdGggZD0ibTUgMTEtMyAzIiAvPgogIDxwYXRoIGQ9Im01IDE3LTMtM2gxMCIgLz4KPC9zdmc+Cg==) - https://lucide.dev/icons/file-output
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
function Scan_search($$renderer, $$props) {
  const $$sanitized_props = sanitize_props($$props);
  const iconNode = [
    ["path", { "d": "M3 7V5a2 2 0 0 1 2-2h2" }],
    ["path", { "d": "M17 3h2a2 2 0 0 1 2 2v2" }],
    ["path", { "d": "M21 17v2a2 2 0 0 1-2 2h-2" }],
    ["path", { "d": "M7 21H5a2 2 0 0 1-2-2v-2" }],
    ["circle", { "cx": "12", "cy": "12", "r": "3" }],
    ["path", { "d": "m16 16-1.9-1.9" }]
  ];
  Icon($$renderer, spread_props([
    { name: "scan-search" },
    $$sanitized_props,
    {
      /**
       * @component @name ScanSearch
       * @description Lucide SVG icon component, renders SVG Element with children.
       *
       * @preview ![img](data:image/svg+xml;base64,PHN2ZyAgeG1sbnM9Imh0dHA6Ly93d3cudzMub3JnLzIwMDAvc3ZnIgogIHdpZHRoPSIyNCIKICBoZWlnaHQ9IjI0IgogIHZpZXdCb3g9IjAgMCAyNCAyNCIKICBmaWxsPSJub25lIgogIHN0cm9rZT0iIzAwMCIgc3R5bGU9ImJhY2tncm91bmQtY29sb3I6ICNmZmY7IGJvcmRlci1yYWRpdXM6IDJweCIKICBzdHJva2Utd2lkdGg9IjIiCiAgc3Ryb2tlLWxpbmVjYXA9InJvdW5kIgogIHN0cm9rZS1saW5lam9pbj0icm91bmQiCj4KICA8cGF0aCBkPSJNMyA3VjVhMiAyIDAgMCAxIDItMmgyIiAvPgogIDxwYXRoIGQ9Ik0xNyAzaDJhMiAyIDAgMCAxIDIgMnYyIiAvPgogIDxwYXRoIGQ9Ik0yMSAxN3YyYTIgMiAwIDAgMS0yIDJoLTIiIC8+CiAgPHBhdGggZD0iTTcgMjFINWEyIDIgMCAwIDEtMi0ydi0yIiAvPgogIDxjaXJjbGUgY3g9IjEyIiBjeT0iMTIiIHI9IjMiIC8+CiAgPHBhdGggZD0ibTE2IDE2LTEuOS0xLjkiIC8+Cjwvc3ZnPgo=) - https://lucide.dev/icons/scan-search
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
function Loading($$renderer, $$props) {
  let {
    kind = "render",
    variant = "surface",
    title = "Working…",
    detail = "Keep this window open while NEXIAM finishes.",
    class: className = ""
  } = $$props;
  const variantClasses = {
    inline: "inline-flex min-h-10 items-center gap-3 rounded-xl border border-border/80 bg-card px-3 py-2.5",
    surface: "relative flex min-h-[220px] w-full items-center justify-center rounded-2xl border border-border/80 bg-card/80 p-6",
    overlay: "absolute inset-0 z-10 flex items-center justify-center bg-card/85 p-6 backdrop-blur-sm"
  };
  $$renderer.push(`<div${attr_class(`${variantClasses[variant] ?? variantClasses.surface} ${className}`)} role="status" aria-live="polite" aria-busy="true"><div${attr_class(clsx(variant === "inline" ? "flex size-8 shrink-0 items-center justify-center rounded-lg bg-primary/10 text-primary" : "flex size-14 shrink-0 items-center justify-center rounded-2xl bg-primary/10 text-primary"))}><span class="relative flex items-center justify-center"><span class="absolute size-full animate-ping rounded-full bg-primary/20 motion-reduce:animate-none"></span> `);
  if (kind === "analysis") {
    $$renderer.push("<!--[0-->");
    Scan_search($$renderer, {
      class: `${variant === "inline" ? "size-4" : "size-7"} relative animate-pulse motion-reduce:animate-none`,
      "aria-hidden": "true"
    });
  } else {
    $$renderer.push("<!--[-1-->");
    File_output($$renderer, {
      class: `${variant === "inline" ? "size-4" : "size-7"} relative animate-pulse motion-reduce:animate-none`,
      "aria-hidden": "true"
    });
  }
  $$renderer.push(`<!--]--></span></div> <div${attr_class(clsx(variant === "inline" ? "min-w-0" : "mt-4 min-w-0 max-w-sm text-center"))}><p${attr_class(clsx(variant === "inline" ? "truncate text-xs font-bold text-foreground" : "text-sm font-black text-foreground"))}>${escape_html(title)}</p> <p${attr_class(clsx(variant === "inline" ? "mt-0.5 truncate text-[11px] text-muted-foreground" : "mt-1 text-xs leading-relaxed text-muted-foreground"))}>${escape_html(detail)}</p></div> `);
  if (variant !== "inline") {
    $$renderer.push("<!--[0-->");
    $$renderer.push(`<div class="absolute bottom-5 left-1/2 flex w-36 -translate-x-1/2 gap-1" aria-hidden="true"><span class="h-1 flex-1 animate-pulse rounded-full bg-primary/30 motion-reduce:animate-none"></span> <span class="h-1 flex-1 animate-pulse rounded-full bg-primary/60 [animation-delay:150ms] motion-reduce:animate-none"></span> <span class="h-1 flex-1 animate-pulse rounded-full bg-primary [animation-delay:300ms] motion-reduce:animate-none"></span></div>`);
  } else {
    $$renderer.push("<!--[-1-->");
  }
  $$renderer.push(`<!--]--></div>`);
}
function ResultSheetTemplateEditor($$renderer, $$props) {
  $$renderer.component(($$renderer2) => {
    let {
      mode = "create",
      template = {},
      draft = null,
      placeholderGroups = {},
      builderCatalog = {},
      builderDefaults = {},
      builderCompilerVersion = "result-sheet-builder-2",
      documentAnalysis = null,
      sampleContext = {},
      printPlaceholderGroups = null,
      printSampleContext = null,
      builderCapabilities = {},
      basePath = "/setup/result-templates",
      surface = null,
      studioTitle = null
    } = $$props;
    const resolvedStudioTitle = derived(() => studioTitle ?? (surface?.title ? `${surface.title} Studio` : basePath?.includes("/setup/report-templates") ? "Report Template Studio" : "Result Sheet Studio"));
    const page = usePage();
    const draftState = draft?.state ?? {};
    const token = derived(() => page.props.csrf_token || csrfToken());
    const form = useForm({
      name: draftState.name ?? template.name ?? "",
      mode: draftState.mode ?? template.mode ?? "html",
      source_kind: draftState.source_kind ?? template.source_kind ?? (mode === "create" ? "builder" : "legacy_html"),
      paper_size: draftState.paper_size ?? template.paper_size ?? "a4",
      orientation: draftState.orientation ?? template.orientation ?? "portrait",
      logical_unit: draftState.logical_unit ?? "full",
      content: draftState.content ?? template.content ?? "",
      document: null,
      builder_json: draftState.builder_json ?? template.builder_json ?? null,
      builder_schema_version: draftState.builder_schema_version ?? template.builder_schema_version ?? null,
      compiled_html: template.compiled_html ?? null,
      compiler_version: draftState.compiler_version ?? template.compiler_version ?? builderCompilerVersion,
      lock_version: template.lock_version ?? 1,
      draft_id: draft?.id ?? null,
      watermark_text: draftState.watermark_text ?? template.watermark_text ?? "",
      is_active: draftState.is_active ?? (mode === "create" ? false : template.is_active ?? false)
    });
    let analysis = draft && draftState.mode === "docx" ? null : documentAnalysis ?? null;
    let builderDocument = draftState.builder_json ? clone(draftState.builder_json) : template.builder_json ? clone(template.builder_json) : mode === "create" ? clone(builderDefaults) : null;
    let previewPdfUrl = "";
    let previewLoading = false;
    let previewError = null;
    let previewDiagnostics = [];
    let isPdfStale = Number(draft?.stage ?? 1) === 3;
    let pdfGeneratedAt = null;
    let previewSequence = 0;
    let previewController = null;
    let previewPollTimer = null;
    let previewPollController = null;
    let previewPollRunId = null;
    let previewJobIsPolling = false;
    const previewPollIntervalMs = 1200;
    const previewPollRequestTimeoutMs = 1e4;
    const previewPollTimeoutMs = 12e4;
    let previewTimer = null;
    let analysisLoading = false;
    const resumedWordDraft = Boolean(draft && draftState.mode === "docx");
    const rawInitialStage = draft?.stage ?? 1;
    const initialStageId = typeof rawInitialStage === "string" && rawInitialStage !== "" ? rawInitialStage : form.mode === "docx" ? Number(rawInitialStage) === 1 ? "copy" : "upload" : Number(rawInitialStage) === 2 ? "design" : Number(rawInitialStage) === 3 ? "verify" : "setup";
    let currentStageId = resumedWordDraft ? "upload" : initialStageId;
    let draftId = draft?.id ?? null;
    let draftLockVersion = draft?.lock_version ?? null;
    let draftSaveTimer = null;
    let draftSaveInFlight = null;
    let draftSaveStatus = draft ? "saved" : "idle";
    let draftSaveError = "";
    let lifecycleAction = "";
    let editorAlive = true;
    let draftRequestGeneration = 0;
    const initialEditorPath = typeof window === "undefined" ? "" : window.location.pathname;
    let mobileFocusMode = false;
    let studioViewport = {
      zoomMode: "fit",
      zoom: 0.72,
      panX: 0,
      panY: 0,
      effectiveZoom: 0.72,
      percentage: 72
    };
    let conflictDetected = false;
    let conflictMessage = "";
    let pickerOpen = false;
    let pickerMode = "copy";
    let bindingTargetId = null;
    let recentKeys = [];
    let verifySafeBottom = 16;
    const stages = derived(() => form.mode === "docx" ? [
      {
        id: "copy",
        number: 1,
        label: "Copy",
        description: "Placeholders"
      },
      {
        id: "upload",
        number: 2,
        label: "Upload",
        description: "Analyze document"
      },
      {
        id: "preview",
        number: 3,
        label: "Preview",
        description: "Verify and save"
      }
    ] : [
      {
        id: "setup",
        number: 1,
        label: "Setup",
        description: "Format and page"
      },
      {
        id: "design",
        number: 2,
        label: "Design",
        description: "Build and style"
      },
      {
        id: "verify",
        number: 3,
        label: "Verify",
        description: "PDF and save"
      }
    ]);
    const currentStageIndex = derived(() => Math.max(0, stages().findIndex((s) => s.id === currentStageId || s.number === currentStageId)));
    const currentStage = derived(() => stages()[currentStageIndex()]?.number ?? 1);
    const currentStageDescriptor = derived(() => stages()[currentStageIndex()] ?? stages()[0]);
    const isFirstStage = derived(() => currentStageIndex() === 0);
    const isLastStage = derived(() => currentStageIndex() === stages().length - 1);
    const previousStageDescriptor = derived(() => !isFirstStage() ? stages()[currentStageIndex() - 1] : null);
    const nextStageDescriptor = derived(() => !isLastStage() ? stages()[currentStageIndex() + 1] : null);
    const saveStateLabel = derived(() => form.processing ? "Saving template…" : draftSaveStatus === "pending" ? "Changes pending…" : draftSaveStatus === "saving" ? "Saving draft…" : draftSaveStatus === "error" ? "Draft save failed" : draftSaveStatus === "saved" ? "Draft saved" : mode === "create" ? "Draft" : "Saved");
    const usedKeys = derived(() => form.mode === "docx" ? analysis?.found ?? [] : builderDocument ? bindingsFrom(builderDocument) : bindingsFrom(form.content));
    const isLegacyHtml = derived(() => form.mode === "html" && !builderDocument && Boolean(form.content?.trim()));
    const hasValidName = derived(() => Boolean(form.name && form.name.trim().length > 0));
    const hasFatalHtmlIssues = derived(() => {
      if (form.mode !== "html") return false;
      if (previewDiagnostics.length > 0) return true;
      if (isLegacyHtml()) {
        return !form.content?.trim();
      }
      if (!builderDocument && !form.content?.trim()) return true;
      if (builderDocument && (!Array.isArray(builderDocument.components) || builderDocument.components.length === 0)) return true;
      const clientValidation = validateBuilderState(builderDocument, form.paper_size, form.orientation);
      if (!clientValidation.valid) return true;
      return false;
    });
    const hasFatalDocxIssues = derived(() => {
      if (form.mode !== "docx") return false;
      if (!analysis?.valid) return true;
      if (!(mode === "edit" && template.id)) return true;
      return false;
    });
    const pdfIsCurrentAndValid = derived(() => Boolean(previewPdfUrl) && !previewLoading && !previewError && !isPdfStale && previewDiagnostics.length === 0);
    const isConflictActive = derived(() => conflictDetected || Boolean(form.errors?.template_conflict || page.props.errors?.template_conflict));
    const effectiveConflictMessage = derived(() => conflictMessage || form.errors?.template_conflict || page.props.errors?.template_conflict || "This template was modified in another session. Please reload to review current changes and avoid overwriting newer data.");
    const canSave = derived(() => hasValidName() && pdfIsCurrentAndValid() && !hasFatalHtmlIssues() && !hasFatalDocxIssues() && !form.processing && !isConflictActive());
    onDestroy(() => {
      if (draftSaveStatus === "pending" && lifecycleAction !== "discard" && lifecycleAction !== "restart") {
        void persistDraft({ flush: true, keepalive: true, updateUi: false });
      }
      editorAlive = false;
      draftRequestGeneration++;
      previewSequence++;
      if (previewController) previewController.abort();
      stopPreviewPolling();
      clearScheduledPreview();
      clearScheduledDraftSave();
    });
    async function focusAlert() {
      await tick();
    }
    function humanizeError(code) {
      if (!code) return "Preview generation failed. Please check the template and try again.";
      switch (code) {
        case "builder_invalid":
          return "The template layout contains compilation issues. Review the diagnostics below and adjust the layout in Design.";
        case "template_document_required":
          return "A Word or ODT document is required before generating a preview.";
        case "template_mode_mismatch":
          return "The selected template mode does not match the document format.";
        case "template_conflict":
          return "This template was modified in another session. Please reload to review the latest changes.";
        case "template_document_missing":
          return "The document file is missing or unreadable on the server.";
        default:
          return String(code).replace(/_/g, " ");
      }
    }
    function clone(value) {
      return value ? JSON.parse(JSON.stringify(value)) : null;
    }
    function hasNetworkEvidence(caught) {
      return /(failed to fetch|network|connection|offline|timeout)/i.test(String(caught?.message ?? caught ?? ""));
    }
    function failureMessage(caught, fallback) {
      const message = caught?.message || (caught ? String(caught) : "");
      if (hasNetworkEvidence(caught)) {
        return `${fallback} Check your network connection and try again.`;
      }
      return message || fallback;
    }
    function draftStatePayload() {
      return {
        name: form.name ?? "",
        mode: form.mode ?? "html",
        source_kind: form.mode === "docx" ? "docx" : builderDocument ? "builder" : "legacy_html",
        paper_size: form.paper_size ?? "a4",
        orientation: form.orientation ?? "portrait",
        logical_unit: form.logical_unit ?? "full",
        content: form.content ?? "",
        builder_json: builderDocument ? clone(builderDocument) : null,
        builder_schema_version: form.builder_schema_version ?? null,
        compiler_version: form.compiler_version ?? null,
        watermark_text: form.watermark_text ?? "",
        is_active: Boolean(form.is_active)
      };
    }
    function scheduleDraftSave() {
      draftSaveStatus = "pending";
      if (draftSaveTimer) clearTimeout(draftSaveTimer);
      draftSaveTimer = setTimeout(
        () => {
          draftSaveTimer = null;
          void persistDraft();
        },
        300
      );
    }
    function clearScheduledDraftSave() {
      if (!draftSaveTimer) return;
      clearTimeout(draftSaveTimer);
      draftSaveTimer = null;
    }
    function attachDraftToUrl(id) {
      if (!editorAlive || typeof window === "undefined") return;
      const url = new URL(window.location.href);
      if (url.pathname !== initialEditorPath) return;
      url.searchParams.set("draft", String(id));
      window.history.replaceState(window.history.state, "", `${url.pathname}${url.search}${url.hash}`);
    }
    async function persistDraft({ flush = false, keepalive = false, updateUi = true } = {}) {
      if (flush) clearScheduledDraftSave();
      if (draftSaveInFlight) {
        await draftSaveInFlight;
        if (!editorAlive && !keepalive) return false;
        return persistDraft({ flush: true, keepalive, updateUi });
      }
      if (updateUi) {
        draftSaveStatus = "saving";
        draftSaveError = "";
      }
      const requestGeneration = draftRequestGeneration;
      const isNewDraft = !draftId;
      const endpoint = isNewDraft ? `${basePath}/drafts` : `${basePath}/drafts/${draftId}`;
      const body = {
        state: draftStatePayload(),
        stage: currentStageDescriptor().number,
        ...isNewDraft ? { source_template_id: mode === "edit" ? template.id : null } : { lock_version: draftLockVersion }
      };
      const request = (async () => {
        let draftConflict = false;
        try {
          const response = await fetch(endpoint, {
            method: isNewDraft ? "POST" : "PUT",
            headers: {
              Accept: "application/json",
              "Content-Type": "application/json",
              "X-Requested-With": "XMLHttpRequest",
              "X-CSRF-TOKEN": token()
            },
            credentials: "same-origin",
            body: JSON.stringify(body),
            keepalive
          });
          const result = await response.json().catch(() => ({}));
          if (!editorAlive || requestGeneration !== draftRequestGeneration) {
            return response.ok;
          }
          if (!response.ok) {
            if (response.status === 409 || result.error === "draft_conflict") {
              draftConflict = true;
              conflictDetected = true;
              conflictMessage = result.message || "This draft was modified in another session. Please reload to review the latest saved state.";
            }
            throw new Error(result.message || result.error || "Draft save failed.");
          }
          if (!result.draft?.id) {
            throw new Error("Draft save returned no draft identity.");
          }
          draftId = result.draft.id;
          draftLockVersion = result.draft.lock_version;
          form.draft_id = draftId;
          attachDraftToUrl(draftId);
          if (updateUi) draftSaveStatus = draftSaveTimer ? "pending" : "saved";
          return true;
        } catch (caught) {
          if (!editorAlive || requestGeneration !== draftRequestGeneration) return false;
          if (updateUi) {
            draftSaveStatus = "error";
            draftSaveError = failureMessage(caught, "Draft save failed. Your final template has not been changed.");
            if (!draftConflict) {
              error(draftSaveError);
            }
          }
          return false;
        }
      })();
      draftSaveInFlight = request;
      try {
        return await request;
      } finally {
        if (draftSaveInFlight === request) draftSaveInFlight = null;
      }
    }
    function bindingsFrom(value) {
      const found = /* @__PURE__ */ new Set();
      const walk = (item) => {
        if (Array.isArray(item)) return item.forEach(walk);
        if (item && typeof item === "object") return Object.values(item).forEach(walk);
        if (typeof item !== "string") return;
        for (const match of item.matchAll(/\{\{\s*([a-zA-Z0-9_]+)\s*\}\}/g)) found.add(match[1]);
      };
      walk(value);
      return [...found].sort();
    }
    function pageDimensions(paperSize, orientation) {
      const base = paperSize === "legal" ? { width_mm: 216, height_mm: 356 } : paperSize === "letter" ? { width_mm: 216, height_mm: 279 } : { width_mm: 210, height_mm: 297 };
      return orientation === "landscape" ? { width_mm: base.height_mm, height_mm: base.width_mm } : base;
    }
    function validateBuilderState(doc, paperSize, orientation) {
      if (!doc || !Array.isArray(doc.components) || doc.components.length === 0) {
        return {
          valid: false,
          message: "Add at least one printable component to the canvas in Design before proceeding to Verify."
        };
      }
      const page2 = doc.page || {};
      const margin = typeof page2.margin_mm === "number" ? page2.margin_mm : 12;
      const dims = pageDimensions(paperSize || page2.paper_size || "a4", orientation || page2.orientation || "portrait");
      const contentWidth = dims.width_mm - margin * 2;
      const contentHeight = dims.height_mm - margin * 2;
      for (const comp of doc.components) {
        if (comp.type === "field") {
          const binding = comp.props?.binding;
          if (!binding || !String(binding).trim()) {
            return {
              valid: false,
              message: `Field "${comp.props?.label || "Bound Field"}" is missing a placeholder binding. Select the field in Design and choose a placeholder.`
            };
          }
        }
        const x = Number(comp.x) || 0;
        const y = Number(comp.y) || 0;
        const w = Number(comp.w) || 0;
        const h = Number(comp.h) || 0;
        if (x < 0 || y < 0 || x + w > contentWidth + 0.1 || y + h > contentHeight + 0.1) {
          return {
            valid: false,
            message: `Component "${comp.props?.label || comp.type || "Item"}" extends beyond the printable page margins (${contentWidth} × ${contentHeight} mm). Adjust its position or dimensions in Design before verifying.`
          };
        }
      }
      return { valid: true, message: null };
    }
    function clearScheduledPreview() {
      if (!previewTimer) return;
      clearTimeout(previewTimer);
      previewTimer = null;
    }
    function invalidatePreview() {
      clearScheduledPreview();
      previewSequence++;
      if (previewController) {
        previewController.abort();
        previewController = null;
      }
      stopPreviewPolling();
      previewLoading = false;
      isPdfStale = true;
      previewPdfUrl = "";
      previewError = null;
      previewDiagnostics = [];
    }
    function schedulePreview() {
      clearScheduledPreview();
      if (form.mode !== "html") return;
      const hasHtmlSource = Boolean(builderDocument || form.content?.trim());
      if (!hasHtmlSource) {
        invalidatePreview();
        return;
      }
      previewTimer = setTimeout(
        () => {
          previewTimer = null;
          void runPreview();
        },
        400
      );
    }
    function handlePageSetting(key, value) {
      form[key] = value;
      if (!builderDocument) {
        invalidatePreview();
        schedulePreview();
        scheduleDraftSave();
        return;
      }
      const page2 = builderDocument.page ?? {};
      builderDocument = {
        ...builderDocument,
        page: {
          ...page2,
          paper_size: form.paper_size,
          orientation: form.orientation,
          ...pageDimensions(form.paper_size, form.orientation)
        }
      };
      handleStudioChange(builderDocument);
    }
    function handleStudioChange(next) {
      builderDocument = clone(next);
      if (next.page?.paper_size) form.paper_size = next.page.paper_size;
      if (next.page?.orientation) form.orientation = next.page.orientation;
      form.source_kind = "builder";
      form.builder_schema_version = next.schema_version ?? 1;
      form.compiler_version = builderCompilerVersion;
      invalidatePreview();
      schedulePreview();
      scheduleDraftSave();
    }
    function handleStudioSelectionChange(selection) {
      mobileFocusMode = Boolean(selection?.focused);
    }
    function handleStudioViewportChange(viewport) {
      if (!viewport) return;
      const nextViewport = {
        zoomMode: viewport.zoomMode,
        zoom: viewport.zoom,
        panX: viewport.panX,
        panY: viewport.panY,
        effectiveZoom: viewport.effectiveZoom,
        percentage: viewport.percentage
      };
      const isSameViewport = run(() => studioViewport.zoomMode === nextViewport.zoomMode && studioViewport.zoom === nextViewport.zoom && studioViewport.panX === nextViewport.panX && studioViewport.panY === nextViewport.panY && studioViewport.effectiveZoom === nextViewport.effectiveZoom && studioViewport.percentage === nextViewport.percentage);
      if (isSameViewport) {
        return;
      }
      studioViewport = nextViewport;
    }
    function convertLegacy() {
      const defaults = clone(builderDefaults) ?? {};
      builderDocument = {
        ...defaults,
        page: {
          ...defaults.page ?? {},
          paper_size: form.paper_size,
          orientation: form.orientation,
          ...pageDimensions(form.paper_size, form.orientation)
        }
      };
      form.source_kind = "builder";
      form.builder_json = clone(builderDocument);
      invalidatePreview();
      scheduleDraftSave();
      success("Converted to the structured " + (surface?.title ?? "template") + " builder. Review the layout before saving.");
    }
    function resolveStageDescriptor(targetStage) {
      if (!targetStage) return null;
      if (typeof targetStage === "string") {
        return stages().find((stage) => stage.id === targetStage) ?? null;
      }
      if (typeof targetStage === "number") {
        return stages().find((stage) => stage.number === targetStage) ?? null;
      }
      if (typeof targetStage === "object" && targetStage.id) {
        return stages().find((stage) => stage.id === targetStage.id) ?? null;
      }
      return null;
    }
    async function goToStage(targetStage) {
      const targetDescriptor = resolveStageDescriptor(targetStage);
      if (!targetDescriptor) return;
      const currentIndex = currentStageIndex();
      const targetIndex = stages().findIndex((s) => s.id === targetDescriptor.id);
      if (targetIndex < 0 || targetIndex === currentIndex) return;
      if (targetIndex > currentIndex + 1) {
        warning("Complete the current stage before skipping ahead.");
        return;
      }
      if (targetIndex > 0 && !form.name?.trim()) {
        warning("Enter a template name in Setup before proceeding.");
        return;
      }
      if (targetIndex === stages().length - 1) {
        if (form.mode === "docx") {
          if (!(mode === "edit" && template.id)) {
            warning("Upload a Word / ODT document before moving to Preview.");
            return;
          }
          if (!analysis?.valid) {
            warning("Resolve the missing, unknown, or malformed placeholders before moving to Preview.");
            return;
          }
        } else if (form.mode === "html") {
          if (isLegacyHtml()) {
            if (!form.content?.trim()) {
              warning("Template HTML content cannot be empty.");
              return;
            }
          } else if (form.source_kind === "builder" || builderDocument) {
            const validation = validateBuilderState(builderDocument, form.paper_size, form.orientation);
            if (!validation.valid) {
              warning(validation.message);
              return;
            }
          } else if (!form.content?.trim()) {
            warning("Template HTML content cannot be empty.");
            return;
          }
        }
      }
      const previousStageId = currentStageId;
      currentStageId = targetDescriptor.id;
      const persisted = await persistDraft({ flush: true });
      if (!persisted) {
        currentStageId = previousStageId;
        if (isConflictActive()) void focusAlert();
      } else if ((targetDescriptor.id === "verify" || targetDescriptor.id === "preview") && (!previewPdfUrl || isPdfStale) && !previewLoading) {
        void runPreview();
      }
    }
    function openPicker(request = {}) {
      pickerMode = request.mode ?? "copy";
      bindingTargetId = request.targetId ?? null;
      pickerOpen = true;
    }
    function closePicker() {
      pickerOpen = false;
      bindingTargetId = null;
    }
    function remember(key) {
      if (!recentKeys.includes(key)) recentKeys = [key, ...recentKeys].slice(0, 8);
    }
    async function copyPlaceholder(item) {
      try {
        if (navigator.clipboard && window.isSecureContext) {
          await navigator.clipboard.writeText(item.macro);
        } else {
          const area = document.createElement("textarea");
          area.value = item.macro;
          area.style.position = "fixed";
          area.style.opacity = "0";
          document.body.appendChild(area);
          area.select();
          document.execCommand("copy");
          area.remove();
        }
        remember(item.key);
        success(`Copied ${item.label}`);
      } catch {
        error("Failed to copy placeholder");
      }
    }
    function insertBinding(item) {
      if (!builderDocument || !bindingTargetId) {
        closePicker();
        return;
      }
      builderDocument = {
        ...builderDocument,
        components: builderDocument.components.map((component) => component.id === bindingTargetId ? {
          ...component,
          props: {
            ...component.props ?? {},
            binding: item.key,
            label: item.label
          }
        } : component)
      };
      remember(item.key);
      handleStudioChange(builderDocument);
      closePicker();
    }
    async function runPreview() {
      clearScheduledPreview();
      const runId = ++previewSequence;
      if (previewController) {
        previewController.abort();
        previewController = null;
      }
      stopPreviewPolling();
      previewError = null;
      previewDiagnostics = [];
      previewPdfUrl = "";
      previewLoading = true;
      if (form.mode === "docx" && !analysis?.valid) {
        previewLoading = false;
        isPdfStale = true;
        previewError = "Analyze a valid Word / ODT document before previewing.";
        return;
      }
      const fd = new FormData();
      fd.append("mode", form.mode);
      fd.append("paper_size", form.paper_size);
      fd.append("orientation", form.orientation);
      fd.append("logical_unit", "full");
      if (form.mode === "html") {
        if (builderDocument) {
          fd.append("builder_json", JSON.stringify(builderDocument));
        } else if (form.content?.trim()) {
          fd.append("content", form.content);
        } else {
          if (runId !== previewSequence) return;
          previewLoading = false;
          previewError = "No template content available to preview.";
          isPdfStale = true;
          return;
        }
      } else if (mode === "edit" && template.id) {
        fd.append("template_id", String(template.id));
      } else {
        if (runId !== previewSequence) return;
        previewLoading = false;
        previewError = "No document selected for preview.";
        isPdfStale = true;
        return;
      }
      const controller = new AbortController();
      previewController = controller;
      try {
        const res = await fetch(`${basePath}/preview`, {
          method: "POST",
          body: fd,
          signal: controller.signal,
          headers: {
            Accept: "application/json",
            "X-Requested-With": "XMLHttpRequest",
            "X-CSRF-TOKEN": token()
          },
          credentials: "same-origin"
        });
        const data = await res.json().catch(() => ({}));
        if (runId !== previewSequence) return;
        if (!res.ok) {
          if (data.diagnostics && Array.isArray(data.diagnostics) && data.diagnostics.length > 0) {
            previewDiagnostics = data.diagnostics;
            previewError = `Builder validation failed with ${data.diagnostics.length} diagnostic issue${data.diagnostics.length > 1 ? "s" : ""}. Correct the issues listed below.`;
          } else {
            previewDiagnostics = [];
            previewError = humanizeError(data.error);
          }
          isPdfStale = true;
          previewPdfUrl = "";
          if (previewDiagnostics.length > 0) void focusAlert();
          return;
        }
        if (data.pdf_base64) {
          try {
            const bin = atob(data.pdf_base64);
            const arr = new Uint8Array(bin.length);
            for (let i = 0; i < bin.length; i++) {
              arr[i] = bin.charCodeAt(i);
            }
            const blob = new Blob([arr], { type: "application/pdf" });
            previewPdfUrl = `${URL.createObjectURL(blob)}#toolbar=0`;
          } catch {
            previewPdfUrl = `data:application/pdf;base64,${data.pdf_base64}#toolbar=0`;
          }
          isPdfStale = false;
          previewDiagnostics = [];
          previewError = null;
          pdfGeneratedAt = /* @__PURE__ */ new Date();
        } else if (data.preview_job_id && data.preview_url) {
          pollPreview(data.preview_job_id, data.preview_url, runId);
          return;
        } else {
          previewError = "Preview generation returned no PDF or background job. Try again.";
          isPdfStale = true;
          previewPdfUrl = "";
        }
      } catch (err) {
        if (err?.name === "AbortError" || runId !== previewSequence) return;
        previewError = failureMessage(err, "Preview generation failed.");
        previewDiagnostics = [];
        isPdfStale = true;
        previewPdfUrl = "";
        if (hasNetworkEvidence(err)) error(previewError);
      } finally {
        if (runId === previewSequence) {
          previewController = null;
          if (!previewJobIsPolling) previewLoading = false;
        }
      }
    }
    function stopPreviewPolling(runId = null) {
      if (runId !== null && previewPollRunId !== runId) return;
      if (previewPollTimer) clearTimeout(previewPollTimer);
      previewPollTimer = null;
      previewPollController?.abort();
      previewPollController = null;
      previewPollRunId = null;
      previewJobIsPolling = false;
    }
    function failPreviewPolling(message, runId) {
      if (previewPollRunId !== runId) return;
      stopPreviewPolling(runId);
      previewError = message;
      previewLoading = false;
      isPdfStale = true;
      previewPdfUrl = "";
    }
    function pollPreview(jobId, downloadUrl, runId) {
      stopPreviewPolling();
      if (runId !== previewSequence) return;
      const deadline = Date.now() + previewPollTimeoutMs;
      previewPollRunId = runId;
      previewJobIsPolling = true;
      const poll = async () => {
        if (runId !== previewSequence || previewPollRunId !== runId) {
          stopPreviewPolling(runId);
          return;
        }
        if (Date.now() >= deadline) {
          failPreviewPolling("Preview generation timed out. Retry to start a new preview.", runId);
          return;
        }
        const controller = new AbortController();
        previewPollController = controller;
        const requestTimeout = setTimeout(() => controller.abort(), Math.min(previewPollRequestTimeoutMs, Math.max(1, deadline - Date.now())));
        try {
          const res = await fetch(`/printing/jobs/${jobId}`, {
            signal: controller.signal,
            headers: { Accept: "application/json" },
            credentials: "same-origin"
          });
          if (runId !== previewSequence || previewPollRunId !== runId) {
            stopPreviewPolling(runId);
            return;
          }
          if (!res.ok) {
            failPreviewPolling(`Preview status request failed (${res.status}). Retry to start a new preview.`, runId);
            return;
          }
          const job = await res.json().catch(() => null);
          if (runId !== previewSequence || previewPollRunId !== runId) {
            stopPreviewPolling(runId);
            return;
          }
          if (!job || typeof job !== "object" || !job.status) {
            failPreviewPolling("Preview status was not recognized. Retry to start a new preview.", runId);
            return;
          }
          if (job.status === "ready") {
            stopPreviewPolling(runId);
            previewPdfUrl = `${downloadUrl}#toolbar=0`;
            previewLoading = false;
            previewError = null;
            previewDiagnostics = [];
            isPdfStale = false;
            pdfGeneratedAt = /* @__PURE__ */ new Date();
            return;
          }
          if (job.status === "failed") {
            failPreviewPolling(job.safe_error || "Preview generation failed in worker.", runId);
            return;
          }
          if (job.status !== "queued" && job.status !== "rendering" && job.status !== "processing") {
            failPreviewPolling("Preview status was not recognized. Retry to start a new preview.", runId);
            return;
          }
          previewPollTimer = setTimeout(poll, previewPollIntervalMs);
        } catch (caught) {
          if (runId !== previewSequence || previewPollRunId !== runId) {
            stopPreviewPolling(runId);
            return;
          }
          const message = caught?.name === "AbortError" ? "Preview status request timed out. Retry to start a new preview." : failureMessage(caught, "Preview status unavailable.");
          failPreviewPolling(message, runId);
          if (hasNetworkEvidence(caught)) error(message);
        } finally {
          clearTimeout(requestTimeout);
          if (previewPollController === controller) {
            previewPollController = null;
          }
        }
      };
      previewPollTimer = setTimeout(poll, previewPollIntervalMs);
    }
    function handleReloadConflict() {
      router.reload({ preserveScroll: false });
    }
    let $$settled = true;
    let $$inner_renderer;
    function $$render_inner($$renderer3) {
      $$renderer3.push(`<div class="bg-studio-shell text-studio-shell-foreground relative flex min-h-0 min-w-0 flex-1 flex-col overflow-hidden select-none"${attr("aria-busy", form.processing)}><header data-result-sheet-document-bar=""${attr_class(clsx(currentStageDescriptor().id === "design" && form.mode === "html" ? "border-studio-border bg-studio-panel text-studio-panel-foreground hidden shrink-0 flex-col gap-3 border-b px-4 py-3 xl:flex xl:flex-row xl:items-center xl:justify-between xl:gap-5 xl:px-6" : "border-studio-border bg-studio-panel text-studio-panel-foreground flex shrink-0 flex-col gap-3 border-b px-4 py-3 xl:flex-row xl:items-center xl:justify-between xl:gap-5 xl:px-6"))}><div class="flex min-w-0 items-center gap-3"><div class="bg-studio-success text-studio-success-foreground flex size-9 shrink-0 items-center justify-center rounded-lg text-sm font-black" aria-hidden="true">N</div> <div class="min-w-0"><p class="text-studio-panel-muted-foreground text-[10px] font-bold tracking-[0.16em] uppercase">${escape_html(resolvedStudioTitle())}</p> <div class="flex min-w-0 flex-wrap items-center gap-x-2 gap-y-1"><h1 class="min-w-0 truncate text-sm font-bold">${escape_html(form.name?.trim() || (mode === "create" ? "Create template" : "Edit template"))}</h1> <span data-result-sheet-save-state=""${attr_class(`rounded-full border px-2 py-0.5 text-[10px] font-semibold ${draftSaveStatus === "pending" || draftSaveStatus === "error" ? "border-studio-warning/70 bg-studio-warning text-studio-warning-foreground" : "border-studio-success/70 bg-studio-success/30 text-studio-success-foreground"}`)} aria-live="polite">${escape_html(saveStateLabel())}</span></div></div></div> <nav data-result-sheet-workflow="" class="border-studio-border bg-studio-panel-muted hidden min-w-0 items-center gap-1 overflow-x-auto rounded-lg border p-1 xl:flex" aria-label="Template workflow"><!--[-->`);
      const each_array = ensure_array_like(stages());
      for (let $$index = 0, $$length = each_array.length; $$index < $$length; $$index++) {
        let stage = each_array[$$index];
        $$renderer3.push(`<button type="button"${attr("data-result-sheet-workflow-stage", stage.id)}${attr_class(`flex min-h-10 shrink-0 items-center gap-1.5 rounded-md px-3 text-left text-xs font-semibold transition-colors motion-reduce:transition-none ${stage.number === currentStage() ? "bg-studio-selection text-studio-selection-foreground shadow-sm" : "text-studio-panel-muted-foreground hover:bg-studio-panel hover:text-studio-panel-foreground"}`)}${attr("title", stage.description)}${attr("aria-current", stage.number === currentStage() ? "step" : void 0)}><span>${escape_html(stage.label)}</span> <span class="hidden text-[10px] font-normal opacity-70 sm:inline">${escape_html(stage.description)}</span></button>`);
      }
      $$renderer3.push(`<!--]--></nav> <div data-result-sheet-mobile-workflow="" class="border-studio-border bg-studio-panel-muted flex min-w-0 items-center justify-between gap-2 rounded-lg border p-1 xl:hidden"><div class="inline-flex min-h-10 items-center"><button type="button" class="text-studio-panel-muted-foreground hover:bg-studio-panel hover:text-studio-panel-foreground focus-visible:ring-studio-focus inline-flex min-h-10 min-w-10 items-center justify-center rounded-md focus-visible:ring-2 focus-visible:outline-none disabled:pointer-events-none disabled:opacity-30" aria-label="Previous workflow stage" title="Previous workflow stage"${attr("disabled", isFirstStage(), true)}>`);
      Chevron_left($$renderer3, { class: "size-4", "aria-hidden": "true" });
      $$renderer3.push(`<!----></button></div> <span class="text-studio-panel-foreground min-w-0 truncate text-center text-xs font-semibold">${escape_html(currentStageDescriptor()?.label)} · ${escape_html(currentStageIndex() + 1)} of ${escape_html(stages().length)}</span> <div class="inline-flex items-center gap-1.5">`);
      if (!isLastStage()) {
        $$renderer3.push("<!--[0-->");
        $$renderer3.push(`<button type="button" class="bg-studio-selection text-studio-selection-foreground hover:bg-studio-selection/80 focus-visible:ring-studio-focus inline-flex min-h-10 min-w-10 items-center justify-center gap-1 rounded-md px-2 text-[11px] font-semibold focus-visible:ring-2 focus-visible:outline-none"${attr("aria-label", `Continue to ${nextStageDescriptor()?.label}`)}${attr("title", `Continue to ${nextStageDescriptor()?.label}`)}><span>Next</span> `);
        Chevron_right($$renderer3, { class: "size-4", "aria-hidden": "true" });
        $$renderer3.push(`<!----></button>`);
      } else {
        $$renderer3.push("<!--[-1-->");
        Button($$renderer3, {
          form: "result-sheet-template-form",
          type: "submit",
          size: "sm",
          class: "min-h-10 gap-1",
          disabled: !canSave() || form.processing,
          children: ($$renderer4) => {
            $$renderer4.push(`<span>${escape_html(form.processing ? "Saving…" : "Save")}</span> `);
            Chevron_right($$renderer4, { class: "size-4", "aria-hidden": "true" });
            $$renderer4.push(`<!---->`);
          },
          $$slots: { default: true }
        });
      }
      $$renderer3.push(`<!--]--></div></div> <div class="hidden shrink-0 items-center justify-end gap-3 xl:flex">`);
      if (mode === "create") {
        $$renderer3.push("<!--[0-->");
        $$renderer3.push(`<details data-result-sheet-draft-actions="" class="relative shrink-0"><summary class="border-studio-border bg-studio-panel-muted text-studio-panel-foreground hover:bg-studio-panel focus-visible:ring-studio-focus inline-flex min-h-10 cursor-pointer list-none items-center gap-1.5 rounded-lg border px-3 text-xs font-semibold transition-colors focus-visible:ring-2 focus-visible:outline-none" title="Draft and exit options"><span>Exit</span> `);
        Chevron_down($$renderer3, {
          class: "text-studio-panel-muted-foreground size-3.5",
          "aria-hidden": "true"
        });
        $$renderer3.push(`<!----></summary> <div class="border-studio-border bg-studio-panel absolute right-0 z-40 mt-1.5 grid min-w-52 gap-1 rounded-xl border p-1.5 shadow-xl"><button type="button" class="hover:bg-studio-panel-muted text-studio-panel-foreground flex items-center gap-2 rounded-lg px-3 py-2 text-left text-xs font-semibold transition-colors disabled:opacity-50"${attr("disabled", Boolean(lifecycleAction), true)}>`);
        Save($$renderer3, {
          class: "text-studio-panel-muted-foreground size-3.5",
          "aria-hidden": "true"
        });
        $$renderer3.push(`<!----> <span>${escape_html("Continue later")}</span></button> <button type="button" class="hover:bg-studio-panel-muted text-studio-panel-foreground flex items-center gap-2 rounded-lg px-3 py-2 text-left text-xs font-semibold transition-colors disabled:opacity-50"${attr("disabled", Boolean(lifecycleAction), true)}>`);
        Rotate_ccw($$renderer3, {
          class: "text-studio-panel-muted-foreground size-3.5",
          "aria-hidden": "true"
        });
        $$renderer3.push(`<!----> <span>Start over</span></button> <div class="border-studio-border my-0.5 border-t"></div> <button type="button" class="text-destructive hover:bg-destructive/10 flex items-center gap-2 rounded-lg px-3 py-2 text-left text-xs font-semibold transition-colors disabled:opacity-50"${attr("disabled", Boolean(lifecycleAction), true)}>`);
        Trash_2($$renderer3, { class: "size-3.5", "aria-hidden": "true" });
        $$renderer3.push(`<!----> <span>Discard draft</span></button></div></details>`);
      } else {
        $$renderer3.push("<!--[-1-->");
        Button($$renderer3, {
          type: "button",
          variant: "outline",
          size: "sm",
          class: "border-studio-border text-studio-panel-muted-foreground hover:text-studio-panel-foreground min-h-10",
          onclick: () => router.visit(basePath),
          children: ($$renderer4) => {
            $$renderer4.push(`<!---->Exit`);
          },
          $$slots: { default: true }
        });
      }
      $$renderer3.push(`<!--]--> `);
      if (!isLastStage()) {
        $$renderer3.push("<!--[0-->");
        $$renderer3.push(`<div class="border-studio-border bg-studio-panel inline-flex min-h-10 items-stretch rounded-lg border p-0.5 shadow-sm"><button type="button" class="text-studio-panel-muted-foreground hover:bg-studio-panel-muted hover:text-studio-panel-foreground focus-visible:ring-studio-focus inline-flex items-center gap-1 rounded-md px-3 text-xs font-semibold transition-colors focus-visible:ring-2 focus-visible:outline-none disabled:pointer-events-none disabled:opacity-30" aria-label="Previous workflow stage" title="Previous workflow stage"${attr("disabled", isFirstStage(), true)}>`);
        Chevron_left($$renderer3, { class: "size-3.5", "aria-hidden": "true" });
        $$renderer3.push(`<!----> <span>Back</span></button> <div class="bg-studio-border my-1 w-px" aria-hidden="true"></div> <button type="button" class="bg-studio-selection text-studio-selection-foreground hover:bg-studio-selection/90 focus-visible:ring-studio-focus inline-flex items-center gap-1 rounded-md px-3.5 text-xs font-semibold transition-colors focus-visible:ring-2 focus-visible:outline-none"${attr("title", `Continue to ${nextStageDescriptor()?.label}`)}><span>Next</span> <span class="hidden xl:inline"> to ${escape_html(nextStageDescriptor()?.label)}</span> `);
        Chevron_right($$renderer3, { class: "size-3.5", "aria-hidden": "true" });
        $$renderer3.push(`<!----></button></div>`);
      } else {
        $$renderer3.push("<!--[-1-->");
        $$renderer3.push(`<div class="inline-flex items-center gap-2">`);
        Button($$renderer3, {
          type: "button",
          variant: "outline",
          size: "sm",
          class: "border-studio-border min-h-10 gap-1",
          "aria-label": "Previous workflow stage",
          title: "Previous workflow stage",
          onclick: () => goToStage(previousStageDescriptor()?.id),
          children: ($$renderer4) => {
            Chevron_left($$renderer4, { class: "size-3.5", "aria-hidden": "true" });
            $$renderer4.push(`<!----> <span>Back</span>`);
          },
          $$slots: { default: true }
        });
        $$renderer3.push(`<!----> `);
        Button($$renderer3, {
          form: "result-sheet-template-form",
          type: "submit",
          size: "sm",
          class: "bg-studio-selection text-studio-selection-foreground hover:bg-studio-selection/90 min-h-10 gap-1.5",
          disabled: !canSave() || form.processing,
          children: ($$renderer4) => {
            $$renderer4.push(`<span>${escape_html(form.processing ? "Saving…" : mode === "create" ? "Create template" : "Save changes")}</span> `);
            Circle_check($$renderer4, { class: "size-3.5", "aria-hidden": "true" });
            $$renderer4.push(`<!---->`);
          },
          $$slots: { default: true }
        });
        $$renderer3.push(`<!----></div>`);
      }
      $$renderer3.push(`<!--]--></div></header> `);
      if (currentStageDescriptor().id === "design" && form.mode === "html" && !mobileFocusMode) {
        $$renderer3.push("<!--[0-->");
        $$renderer3.push(`<div data-result-sheet-mobile-design-chrome="" class="border-studio-border bg-studio-panel text-studio-panel-foreground flex min-h-12 shrink-0 items-center gap-1.5 border-b px-2 py-1.5 xl:hidden"><button type="button" class="border-studio-border text-studio-panel-muted-foreground hover:bg-studio-panel-muted focus-visible:ring-studio-focus inline-flex min-h-11 min-w-11 shrink-0 items-center justify-center rounded-md border focus-visible:ring-2 focus-visible:outline-none disabled:pointer-events-none disabled:opacity-30" aria-label="Previous workflow stage" title="Previous workflow stage"${attr("disabled", isFirstStage(), true)}>`);
        Chevron_left($$renderer3, { class: "size-4", "aria-hidden": "true" });
        $$renderer3.push(`<!----></button> <span class="min-w-0 flex-1 truncate text-center text-xs font-semibold" data-result-sheet-mobile-design-stage="" aria-current="step">Design · ${escape_html(currentStageIndex() + 1)} of ${escape_html(stages().length)}</span> <span data-result-sheet-mobile-zoom="" class="min-w-12 shrink-0 text-center font-mono text-[11px] font-bold"${attr("aria-label", `Canvas zoom ${studioViewport.percentage ?? Math.round((studioViewport.effectiveZoom ?? studioViewport.zoom) * 100)} percent`)}>${escape_html(studioViewport.percentage ?? Math.round((studioViewport.effectiveZoom ?? studioViewport.zoom) * 100))}%</span> <button type="button" class="bg-studio-selection text-studio-selection-foreground hover:bg-studio-selection/80 focus-visible:ring-studio-focus inline-flex min-h-11 shrink-0 items-center justify-center gap-1 rounded-md px-2 text-[11px] font-semibold focus-visible:ring-2 focus-visible:outline-none" aria-label="Continue to Verify" title="Continue to Verify"><span>Verify</span> `);
        Chevron_right($$renderer3, { class: "size-4", "aria-hidden": "true" });
        $$renderer3.push(`<!----></button> `);
        if (mode === "create") {
          $$renderer3.push("<!--[0-->");
          $$renderer3.push(`<details data-result-sheet-mobile-draft-actions="" class="relative shrink-0"><summary class="border-studio-border text-studio-panel-foreground focus-visible:ring-studio-focus inline-flex min-h-11 cursor-pointer list-none items-center rounded-md border px-2 text-[11px] font-semibold focus-visible:ring-2 focus-visible:outline-none">Draft</summary> <div class="border-studio-border bg-studio-panel absolute right-0 z-40 mt-1 grid min-w-44 gap-1 rounded-lg border p-1.5 shadow-xl"><button type="button" class="hover:bg-studio-panel-muted rounded-md px-3 py-2 text-left text-xs font-semibold disabled:opacity-50"${attr("disabled", Boolean(lifecycleAction), true)}>Continue later</button> <button type="button" class="hover:bg-studio-panel-muted rounded-md px-3 py-2 text-left text-xs font-semibold disabled:opacity-50"${attr("disabled", Boolean(lifecycleAction), true)}>Start over</button> <button type="button" class="text-destructive hover:bg-destructive/10 rounded-md px-3 py-2 text-left text-xs font-semibold disabled:opacity-50"${attr("disabled", Boolean(lifecycleAction), true)}>Discard draft</button></div></details>`);
        } else {
          $$renderer3.push("<!--[-1-->");
        }
        $$renderer3.push(`<!--]--></div>`);
      } else {
        $$renderer3.push("<!--[-1-->");
      }
      $$renderer3.push(`<!--]--> `);
      if (isConflictActive()) {
        $$renderer3.push("<!--[0-->");
        $$renderer3.push(`<div tabindex="-1" role="alert" aria-live="assertive" class="border-destructive/40 bg-destructive/10 text-destructive focus:ring-studio-focus relative mx-3 mt-3 flex min-w-0 shrink-0 items-start justify-between gap-4 rounded-xl border p-4 text-xs focus:ring-2 focus:outline-none sm:mx-4 lg:mx-6"><div class="flex min-w-0 items-start gap-3">`);
        Shield_alert($$renderer3, { class: "text-destructive mt-0.5 size-5 shrink-0" });
        $$renderer3.push(`<!----> <div class="min-w-0 space-y-1"><h3 class="text-destructive font-bold">Template Version Conflict</h3> <p class="text-destructive/90 leading-relaxed">${escape_html(effectiveConflictMessage())}</p> <p class="text-destructive/80 text-[11px]">To prevent overwriting newer changes made elsewhere,
                        reload this template before making further edits.</p></div></div> <div class="flex shrink-0 items-center gap-2">`);
        Button($$renderer3, {
          type: "button",
          variant: "outline",
          size: "sm",
          class: "border-studio-border bg-studio-panel text-studio-panel-foreground hover:bg-studio-panel-muted h-8 gap-1.5 text-xs font-semibold",
          onclick: handleReloadConflict,
          children: ($$renderer4) => {
            Rotate_ccw($$renderer4, { class: "size-3.5" });
            $$renderer4.push(`<!----> Reload Template`);
          },
          $$slots: { default: true }
        });
        $$renderer3.push(`<!----></div></div>`);
      } else {
        $$renderer3.push("<!--[-1-->");
      }
      $$renderer3.push(`<!--]--> <form id="result-sheet-template-form" class="relative flex min-h-0 min-w-0 flex-1 flex-col overflow-hidden">`);
      if (currentStageDescriptor().id === "setup" || currentStageDescriptor().id === "copy") {
        $$renderer3.push("<!--[0-->");
        if (form.mode === "docx") {
          $$renderer3.push("<!--[0-->");
          $$renderer3.push(`<div data-result-sheet-stage="word-copy" data-result-sheet-word-copy="" class="grid min-h-0 flex-1 gap-3 overflow-y-auto p-3 lg:grid-cols-[minmax(20rem,24rem)_minmax(0,1fr)] lg:overflow-hidden lg:p-4"><aside data-result-sheet-stage-card="word-copy" class="border-studio-focus/70 bg-studio-panel flex min-h-0 min-w-0 flex-col gap-4 overflow-y-auto rounded-xl border p-4 shadow-sm"><div class="flex items-start gap-3"><div class="bg-studio-focus text-studio-focus-foreground flex size-8 shrink-0 items-center justify-center rounded-lg text-xs font-black">W</div> <div class="min-w-0"><p class="text-studio-panel-muted-foreground text-[10px] font-bold tracking-[0.16em] uppercase">Word / ODT workflow</p> <h2 class="text-studio-panel-foreground text-sm font-black">Copy placeholders</h2> <p class="text-studio-panel-muted-foreground mt-1 text-[11px] leading-relaxed">Copy the exact macros below into your Word
                                    or LibreOffice document. Keep the braces and
                                    spelling unchanged.</p></div></div> <div class="border-studio-focus/40 bg-studio-selection/30 text-studio-panel-foreground space-y-2 rounded-xl border p-3 text-[11px] leading-relaxed"><p class="font-bold">Required order</p> <ol class="list-decimal space-y-1 pl-4"><li>Copy every required field used by your 
                                    ${escape_html(surface?.title ? surface.title.toLowerCase() : "template")}
                                    .</li> <li>Save the external document as .docx or .odt.</li> <li>Continue to Upload, choose that file, and
                                    wait for analysis.</li></ol></div> `);
          if (analysis?.missing?.length) {
            $$renderer3.push("<!--[0-->");
            $$renderer3.push(`<div data-result-sheet-word-missing-callout="" role="alert" class="border-studio-warning bg-studio-warning text-studio-warning-foreground space-y-2 rounded-xl border p-3 text-[11px] leading-relaxed"><p class="font-black">Analysis found ${escape_html(analysis.missing.length)} required
                                    field${escape_html(analysis.missing.length === 1 ? "" : "s")} missing.</p> <p>These fields are highlighted in the picker.
                                    Copy them into the corrected document,
                                    upload it again, and analyze before
                                    continuing.</p> <button type="button" class="border-studio-warning-foreground/40 bg-studio-panel text-studio-panel-foreground hover:bg-studio-panel-muted focus-visible:ring-studio-focus w-full rounded-lg border px-2.5 py-2 text-[11px] font-bold focus-visible:ring-2 focus-visible:outline-none">Return to Upload &amp; Analyze</button></div>`);
          } else {
            $$renderer3.push("<!--[-1-->");
          }
          $$renderer3.push(`<!--]--> <div class="border-studio-border bg-studio-panel-muted text-studio-panel-muted-foreground rounded-xl border border-dashed p-3 text-[11px] leading-relaxed">The Copy stage is the source of truth for field
                            names. Do not type macros by hand when you can copy
                            them from the categorized list.</div></aside> <section data-result-sheet-stage-main="word-copy" class="border-studio-border bg-studio-canvas/40 text-studio-canvas-foreground min-h-0 min-w-0 overflow-hidden rounded-xl border">`);
          PlaceholderPicker($$renderer3, {
            open: true,
            embedded: true,
            placeholderGroups,
            usedKeys: usedKeys(),
            recentKeys,
            missingKeys: analysis?.missing ?? [],
            mode: "copy",
            onclose: closePicker,
            oncopy: copyPlaceholder,
            oninsert: insertBinding
          });
          $$renderer3.push(`<!----></section></div>`);
        } else {
          $$renderer3.push("<!--[-1-->");
          $$renderer3.push(`<div data-result-sheet-stage="setup" class="grid min-h-0 flex-1 gap-3 overflow-y-auto p-3 lg:grid-cols-[minmax(20rem,26rem)_minmax(0,1fr)] lg:overflow-hidden lg:p-4"><aside data-result-sheet-stage-card="setup" class="border-studio-border bg-studio-panel flex min-h-0 min-w-0 flex-col overflow-hidden rounded-xl border shadow-sm"><div class="border-studio-border bg-studio-panel/80 flex h-11 shrink-0 items-center justify-between border-b px-4"><div class="flex items-center gap-2"><div class="bg-studio-success text-studio-success-foreground flex size-5 items-center justify-center rounded text-[10px] font-bold">N</div> <span class="text-studio-panel-foreground truncate text-xs font-bold">Setup</span></div> <span class="text-studio-panel-muted-foreground font-mono text-[10px]">1 / 3</span></div> <div class="min-h-0 flex-1 space-y-4 overflow-y-auto p-4"><div><h2 class="text-studio-panel-foreground text-sm font-black">Set up the template</h2> <p class="text-studio-panel-muted-foreground mt-1 text-[11px] leading-relaxed">Choose the document format and page settings
                                    before opening the design workspace.</p></div> <div class="space-y-3"><div class="space-y-1.5">`);
          Label($$renderer3, {
            for: "name",
            children: ($$renderer4) => {
              $$renderer4.push(`<!---->Template Name`);
            },
            $$slots: { default: true }
          });
          $$renderer3.push(`<!----> `);
          Input($$renderer3, {
            id: "name",
            oninput: scheduleDraftSave,
            class: "min-h-10 font-medium",
            placeholder: "e.g. Official ICAT Results 2026",
            required: true,
            get value() {
              return form.name;
            },
            set value($$value) {
              form.name = $$value;
              $$settled = false;
            }
          });
          $$renderer3.push(`<!----></div> <div class="space-y-1.5">`);
          Label($$renderer3, {
            children: ($$renderer4) => {
              $$renderer4.push(`<!---->Template Format`);
            },
            $$slots: { default: true }
          });
          $$renderer3.push(`<!----> <div class="flex gap-2"><button type="button"${attr_class(clsx(form.mode === "html" ? "border-studio-focus bg-studio-selection text-studio-selection-foreground ring-studio-focus flex min-h-10 flex-1 items-center justify-center gap-2 rounded-lg border text-xs font-semibold ring-1" : "border-studio-border bg-studio-panel-muted text-studio-panel-muted-foreground hover:bg-studio-panel flex min-h-10 flex-1 items-center justify-center gap-2 rounded-lg border text-xs font-semibold"))}>`);
          File_text($$renderer3, { class: "size-4" });
          $$renderer3.push(`<!----> HTML</button> <button type="button"${attr_class(clsx(form.mode === "docx" ? "border-studio-focus bg-studio-selection text-studio-selection-foreground ring-studio-focus flex min-h-10 flex-1 items-center justify-center gap-2 rounded-lg border text-xs font-semibold ring-1" : "border-studio-border bg-studio-panel-muted text-studio-panel-muted-foreground hover:bg-studio-panel flex min-h-10 flex-1 items-center justify-center gap-2 rounded-lg border text-xs font-semibold"))}>`);
          File_text($$renderer3, { class: "size-4" });
          $$renderer3.push(`<!----> Word</button></div></div> <div class="space-y-1.5">`);
          Label($$renderer3, {
            for: "paper_size",
            children: ($$renderer4) => {
              $$renderer4.push(`<!---->Paper Size`);
            },
            $$slots: { default: true }
          });
          $$renderer3.push(`<!----> `);
          $$renderer3.select(
            {
              id: "paper_size",
              class: "border-studio-border bg-studio-panel text-studio-panel-foreground min-h-10 w-full rounded-md border px-3 text-sm",
              value: form.paper_size,
              onchange: (event) => handlePageSetting("paper_size", event.currentTarget.value)
            },
            ($$renderer4) => {
              $$renderer4.option({ value: "a4" }, ($$renderer5) => {
                $$renderer5.push(`A4 (210 × 297 mm)`);
              });
              $$renderer4.option({ value: "legal" }, ($$renderer5) => {
                $$renderer5.push(`Legal (216 × 356 mm)`);
              });
              $$renderer4.option({ value: "letter" }, ($$renderer5) => {
                $$renderer5.push(`Letter (216 × 279 mm)`);
              });
            }
          );
          $$renderer3.push(`</div> <div class="space-y-1.5">`);
          Label($$renderer3, {
            for: "orientation",
            children: ($$renderer4) => {
              $$renderer4.push(`<!---->Orientation`);
            },
            $$slots: { default: true }
          });
          $$renderer3.push(`<!----> `);
          $$renderer3.select(
            {
              id: "orientation",
              class: "border-studio-border bg-studio-panel text-studio-panel-foreground min-h-10 w-full rounded-md border px-3 text-sm",
              value: form.orientation,
              onchange: (event) => handlePageSetting("orientation", event.currentTarget.value)
            },
            ($$renderer4) => {
              $$renderer4.option({ value: "portrait" }, ($$renderer5) => {
                $$renderer5.push(`Portrait`);
              });
              $$renderer4.option({ value: "landscape" }, ($$renderer5) => {
                $$renderer5.push(`Landscape`);
              });
            }
          );
          $$renderer3.push(`</div> <div class="space-y-1.5">`);
          Label($$renderer3, {
            for: "watermark_text",
            children: ($$renderer4) => {
              $$renderer4.push(`<!---->Watermark`);
            },
            $$slots: { default: true }
          });
          $$renderer3.push(`<!----> `);
          Input($$renderer3, {
            id: "watermark_text",
            oninput: scheduleDraftSave,
            class: "min-h-10 font-mono text-xs",
            maxlength: "50",
            placeholder: "Optional",
            get value() {
              return form.watermark_text;
            },
            set value($$value) {
              form.watermark_text = $$value;
              $$settled = false;
            }
          });
          $$renderer3.push(`<!----></div></div> <div class="border-studio-border bg-studio-panel-muted text-studio-panel-muted-foreground rounded-xl border border-dashed p-3 text-[11px] leading-relaxed">Stage 1 defines the paper geometry. Stage 2 is
                                the editable visual canvas. Stage 3 verifies and
                                saves the production PDF.</div></div></aside> <section data-result-sheet-stage-main="setup" data-result-sheet-document-summary="" class="border-studio-border bg-studio-canvas/60 text-studio-canvas-foreground flex min-h-0 min-w-0 flex-col justify-center overflow-y-auto rounded-xl border p-5 lg:p-8"><div class="mx-auto w-full max-w-xl space-y-5"><div><p class="text-studio-panel-muted-foreground text-[10px] font-bold tracking-[0.16em] uppercase">Document summary</p> <h2 class="text-studio-panel-foreground mt-1 text-lg font-bold">Ready for the next stage</h2> <p class="text-studio-panel-muted-foreground mt-1 max-w-prose text-xs leading-relaxed">Confirm the document context here before
                                    opening the stage-specific workspace.</p></div> <dl class="border-studio-border bg-studio-panel grid gap-2 rounded-xl border p-4 text-xs sm:grid-cols-2"><div><dt class="text-studio-panel-muted-foreground">Template</dt> <dd class="mt-1 truncate font-semibold">${escape_html(form.name?.trim() || "Untitled draft")}</dd></div> <div><dt class="text-studio-panel-muted-foreground">Format</dt> <dd class="mt-1 font-semibold">${escape_html(form.mode === "html" ? "HTML Studio" : "Word / ODT")}</dd></div> <div><dt class="text-studio-panel-muted-foreground">Page</dt> <dd class="mt-1 font-semibold">${escape_html(form.paper_size)} · ${escape_html(form.orientation)}</dd></div> <div><dt class="text-studio-panel-muted-foreground">Placed components</dt> <dd class="mt-1 font-semibold">${escape_html(builderDocument?.components?.length ?? 0)}</dd></div></dl> `);
          if (form.mode === "docx") {
            $$renderer3.push("<!--[0-->");
            $$renderer3.push(`<p class="border-studio-focus/40 bg-studio-selection/30 text-studio-panel-foreground rounded-lg border p-3 text-[11px] leading-relaxed">Copy required fields from the Copy stage
                                    before uploading the completed Word or ODT
                                    document.</p>`);
          } else {
            $$renderer3.push("<!--[-1-->");
            $$renderer3.push(`<p class="border-studio-border bg-studio-panel-muted text-studio-panel-muted-foreground rounded-lg border border-dashed p-3 text-[11px] leading-relaxed">Design opens the canvas and its Add,
                                    Inspector, and Layers tools. Verify is
                                    reserved for production PDF review and save.</p>`);
          }
          $$renderer3.push(`<!--]--></div></section></div>`);
        }
        $$renderer3.push(`<!--]-->`);
      } else if (currentStageDescriptor().id === "design" || currentStageDescriptor().id === "upload") {
        $$renderer3.push("<!--[1-->");
        if (form.mode === "docx") {
          $$renderer3.push("<!--[0-->");
          $$renderer3.push(`<div data-result-sheet-stage="word-upload" class="grid min-h-0 flex-1 gap-3 overflow-y-auto p-3 lg:grid-cols-[minmax(20rem,26rem)_minmax(0,1fr)] lg:overflow-hidden lg:p-4"><aside data-result-sheet-stage-card="word-upload" class="border-studio-focus/70 bg-studio-panel flex min-h-0 min-w-0 flex-col overflow-hidden rounded-xl border shadow-sm"><div class="border-studio-focus/70 bg-studio-panel/80 flex h-11 shrink-0 items-center justify-between border-b px-4"><div class="flex items-center gap-2"><div class="bg-studio-focus text-studio-focus-foreground flex size-5 items-center justify-center rounded text-[10px] font-bold">W</div> <span class="text-studio-panel-foreground truncate text-xs font-bold">Upload &amp; Analyze</span></div> <span class="text-studio-panel-muted-foreground font-mono text-[10px]">2 / 3</span></div> <div class="min-h-0 flex-1 space-y-4 overflow-y-auto p-4"${attr("aria-busy", analysisLoading)}><div><h2 class="text-studio-panel-foreground text-sm font-black">Upload your completed document</h2> <p class="text-studio-panel-muted-foreground mt-1 text-[11px] leading-relaxed">This stage analyzes the document you
                                    authored externally. It is not an HTML
                                    canvas.</p></div> <p class="border-studio-warning/60 bg-studio-warning/20 text-studio-warning-foreground rounded-lg border p-3 text-[11px] leading-relaxed">Drafts save this stage and its metadata, not the
                                selected document. If you leave, select the Word
                                / ODT file again before analysis or preview.</p> <input type="file" accept=".docx,.odt"${attr("disabled", analysisLoading, true)} class="border-studio-border bg-studio-panel text-studio-panel-foreground file:bg-studio-panel-muted min-h-10 w-full rounded-md border text-xs file:mr-3 file:border-0 file:px-3 file:py-2.5 file:font-semibold"/> `);
          if (mode === "edit" && template.document_extension) {
            $$renderer3.push("<!--[0-->");
            $$renderer3.push(`<p class="text-studio-panel-muted-foreground text-[11px]">Current file: <span class="font-mono font-semibold uppercase">${escape_html(template.document_extension)}</span> (checksummed)</p>`);
          } else {
            $$renderer3.push("<!--[-1-->");
          }
          $$renderer3.push(`<!--]--> `);
          {
            $$renderer3.push("<!--[-1-->");
          }
          $$renderer3.push(`<!--]--> `);
          if (analysis) {
            $$renderer3.push("<!--[0-->");
            $$renderer3.push(`<div class="border-studio-border bg-studio-panel-muted space-y-2 rounded-xl border p-3 text-xs"><div class="flex justify-between"><span class="text-studio-panel-muted-foreground">Used</span><strong>${escape_html(analysis.found?.length ?? 0)}</strong></div> <div class="flex justify-between"><span class="text-studio-panel-muted-foreground">Missing required</span><strong${attr_class(clsx(analysis.missing?.length ? "text-destructive" : "text-studio-success-foreground"))}>${escape_html(analysis.missing?.length ?? 0)}</strong></div> <div class="flex justify-between"><span class="text-studio-panel-muted-foreground">Unknown / extra</span><strong${attr_class(clsx(analysis.extra?.length ? "text-destructive" : "text-studio-success-foreground"))}>${escape_html(analysis.extra?.length ?? 0)}</strong></div> <div data-result-sheet-word-unresolved-count="" class="flex justify-between"><span class="text-studio-panel-muted-foreground">Malformed / unresolved</span><strong${attr_class(clsx(analysis.unresolved?.length ? "text-destructive" : "text-studio-success-foreground"))}>${escape_html(analysis.unresolved?.length ?? 0)}</strong></div> `);
            if (analysis.missing?.length) {
              $$renderer3.push("<!--[0-->");
              $$renderer3.push(`<div data-result-sheet-word-missing-callout="" role="alert" class="border-studio-warning bg-studio-warning text-studio-warning-foreground space-y-2 rounded-lg border p-2.5 text-[11px]"><p class="font-bold">Missing required fields block
                                                Preview and Save.</p> <p>Return to Copy, copy the
                                                highlighted exact macros, then
                                                upload and analyze the corrected
                                                document again.</p> <button type="button" class="border-studio-warning-foreground/40 bg-studio-panel text-studio-panel-foreground hover:bg-studio-panel-muted w-full rounded-lg border px-2 py-1.5 text-[10px] font-semibold">Back to Copy missing fields</button></div>`);
            } else {
              $$renderer3.push("<!--[-1-->");
            }
            $$renderer3.push(`<!--]--></div>`);
          } else {
            $$renderer3.push("<!--[-1-->");
          }
          $$renderer3.push(`<!--]--></div></aside> <main data-result-sheet-stage-main="word-upload" class="border-studio-focus/70 bg-studio-canvas/30 text-studio-canvas-foreground flex min-h-0 min-w-0 items-center justify-center overflow-hidden rounded-xl border"${attr("aria-busy", analysisLoading)}>`);
          {
            $$renderer3.push("<!--[-1-->");
            $$renderer3.push(`<div class="border-studio-focus/70 bg-studio-panel/90 text-studio-panel-foreground max-w-sm rounded-2xl border p-6 text-center shadow-md">`);
            File_text($$renderer3, { class: "text-studio-focus mx-auto size-8" });
            $$renderer3.push(`<!----> <h2 class="text-studio-panel-foreground mt-3 text-sm font-bold">Analyze the uploaded file</h2> <p class="text-studio-panel-muted-foreground mt-1 text-xs leading-relaxed">After analysis, missing and unknown
                                    placeholders appear in the Upload card.</p></div>`);
          }
          $$renderer3.push(`<!--]--></main></div>`);
        } else {
          $$renderer3.push("<!--[-1-->");
          $$renderer3.push(`<div data-result-sheet-stage="design" class="flex min-h-0 min-w-0 flex-1 flex-col overflow-hidden">`);
          ResultSheetStudio($$renderer3, {
            fullBleed: true,
            builderDocument,
            builderDefaults,
            builderCatalog,
            builderCapabilities,
            sampleContext,
            placeholderGroups,
            legacyContent: form.content,
            onChange: handleStudioChange,
            onSelectionChange: handleStudioSelectionChange,
            onViewportChange: handleStudioViewportChange,
            onConvertLegacy: convertLegacy,
            onOpenPlaceholders: openPicker
          });
          $$renderer3.push(`<!----></div>`);
        }
        $$renderer3.push(`<!--]-->`);
      } else {
        $$renderer3.push("<!--[-1-->");
        $$renderer3.push(`<div data-result-sheet-stage="verify" class="flex min-h-0 flex-1 flex-col gap-3 overflow-y-auto p-3 pb-[calc(1rem+env(safe-area-inset-bottom))] lg:grid lg:grid-cols-[minmax(20rem,26rem)_minmax(0,1fr)] lg:grid-rows-[minmax(0,1fr)] lg:overflow-hidden lg:p-4"><aside data-result-sheet-stage-card="verify" class="border-studio-border bg-studio-panel flex min-h-0 min-w-0 flex-col overflow-hidden rounded-xl border shadow-sm"><div class="border-studio-border bg-studio-panel/80 flex h-11 shrink-0 items-center justify-between border-b px-4"><div class="flex items-center gap-2"><div class="bg-studio-success text-studio-success-foreground flex size-5 items-center justify-center rounded text-[10px] font-bold">N</div> <span class="text-studio-panel-foreground truncate text-xs font-bold">Verification</span></div> <span class="text-studio-panel-muted-foreground font-mono text-[10px]">3 / 3</span></div> <div class="min-h-0 flex-1 space-y-4 overflow-y-auto p-4"><div><h2 class="text-studio-panel-foreground text-sm font-black">Preview and verify</h2> <p class="text-studio-panel-muted-foreground mt-1 text-[11px] leading-relaxed">Review the production PDF before saving or
                                activating the template.</p></div> <div class="border-studio-border bg-studio-panel-muted space-y-2 rounded-xl border p-3 text-xs"><div class="flex justify-between"><span class="text-studio-panel-muted-foreground">Format</span><strong>${escape_html(form.mode === "html" ? isLegacyHtml() ? "HTML (Legacy)" : "HTML Studio" : "Word / ODT")}</strong></div> <div class="flex justify-between"><span class="text-studio-panel-muted-foreground">Page</span><strong>${escape_html(form.paper_size)} · ${escape_html(form.orientation)}</strong></div> <div class="flex justify-between"><span class="text-studio-panel-muted-foreground">Bindings</span><strong>${escape_html(usedKeys().length)}</strong></div> `);
        if (previewPdfUrl && !isPdfStale && previewDiagnostics.length === 0) {
          $$renderer3.push("<!--[0-->");
          $$renderer3.push(`<div class="text-studio-success-foreground flex items-center gap-1.5 pt-1 font-semibold">`);
          Circle_check($$renderer3, { class: "size-3.5" });
          $$renderer3.push(`<!----> PDF verified
                                    &amp; current</div>`);
        } else if (isPdfStale) {
          $$renderer3.push("<!--[1-->");
          $$renderer3.push(`<div class="text-studio-warning-foreground flex items-center gap-1.5 pt-1 font-semibold">`);
          Refresh_cw($$renderer3, { class: "size-3.5 animate-spin" });
          $$renderer3.push(`<!----> Template
                                    changed — refresh PDF</div>`);
        } else {
          $$renderer3.push("<!--[-1-->");
        }
        $$renderer3.push(`<!--]--> `);
        if (form.mode === "docx") {
          $$renderer3.push("<!--[0-->");
          $$renderer3.push(`<div class="border-studio-border space-y-1.5 border-t pt-2 text-[11px]"><div class="flex justify-between"><span class="text-studio-panel-muted-foreground">Source Checksum</span> <span class="max-w-[12rem] truncate font-mono text-[10px]"${attr("title", analysis?.source_checksum || "Pending")}>${escape_html(analysis?.source_checksum ? `${analysis.source_checksum.slice(0, 12)}…` : "Calculated on upload")}</span></div> <div class="flex justify-between"><span class="text-studio-panel-muted-foreground">Analyzer Status</span> <strong${attr_class(clsx(analysis?.valid ? "text-studio-success-foreground" : "text-destructive"))}>${escape_html(analysis?.valid ? "Valid (All required fields)" : analysis ? "Invalid (Issues detected)" : "Pending upload")}</strong></div> <div class="flex justify-between"><span class="text-studio-panel-muted-foreground">LibreOffice Conversion</span> <strong${attr_class(clsx(previewPdfUrl && !isPdfStale ? "text-studio-success-foreground" : previewError ? "text-destructive" : "text-studio-panel-muted-foreground"))}>${escape_html(previewPdfUrl && !isPdfStale ? "Verified (%PDF-1.7 ready)" : previewError ? "Conversion failed" : "Pending")}</strong></div></div>`);
        } else {
          $$renderer3.push("<!--[-1-->");
        }
        $$renderer3.push(`<!--]--></div> `);
        if (previewDiagnostics.length > 0) {
          $$renderer3.push("<!--[0-->");
          $$renderer3.push(`<div tabindex="-1" role="alert" aria-live="assertive" class="border-destructive/40 bg-destructive/10 text-destructive focus:ring-studio-focus space-y-2 rounded-xl border p-3 text-xs shadow-sm focus:ring-2 focus:outline-none"><div class="text-destructive flex items-center gap-1.5 font-bold">`);
          Circle_alert($$renderer3, { class: "text-destructive size-4" });
          $$renderer3.push(`<!----> <span>Compiler Diagnostics (${escape_html(previewDiagnostics.length)})</span></div> <p class="text-destructive/80 text-[11px]">The template contains layout or binding
                                    issues that prevent compilation:</p> <ul class="space-y-1.5 pt-1"><!--[-->`);
          const each_array_1 = ensure_array_like(previewDiagnostics);
          for (let $$index_1 = 0, $$length = each_array_1.length; $$index_1 < $$length; $$index_1++) {
            let diag = each_array_1[$$index_1];
            $$renderer3.push(`<li class="border-destructive/30 bg-studio-panel rounded-lg border p-2 text-[11px]"><div class="flex items-center justify-between gap-1"><span class="bg-destructive/10 text-destructive rounded px-1.5 py-0.5 font-mono text-[10px] font-bold uppercase">${escape_html(diag.code)}</span> `);
            if (diag.component_id) {
              $$renderer3.push("<!--[0-->");
              $$renderer3.push(`<span class="text-studio-panel-muted-foreground font-mono text-[10px]">id: ${escape_html(diag.component_id)}</span>`);
            } else {
              $$renderer3.push("<!--[-1-->");
            }
            $$renderer3.push(`<!--]--></div> <p class="text-studio-panel-foreground mt-1">${escape_html(diag.message)}</p></li>`);
          }
          $$renderer3.push(`<!--]--></ul></div>`);
        } else {
          $$renderer3.push("<!--[-1-->");
        }
        $$renderer3.push(`<!--]--> `);
        if (form.mode === "html") {
          $$renderer3.push("<!--[0-->");
          $$renderer3.push(`<p class="text-studio-panel-muted-foreground text-[11px] leading-relaxed">${escape_html(isLegacyHtml() ? "Editing metadata only. The legacy HTML source remains active until converted." : "The canvas is editable. Generated HTML is derived output.")}</p>`);
        } else {
          $$renderer3.push("<!--[-1-->");
          $$renderer3.push(`<p class="text-studio-panel-muted-foreground text-[11px] leading-relaxed">The source document remains private and
                                checksummed.</p>`);
        }
        $$renderer3.push(`<!--]--> <div class="border-studio-border border-t pt-3"><label${attr_class("flex cursor-pointer items-center gap-2 text-xs font-medium", void 0, { "opacity-60": !canSave() && !form.is_active })}><input type="checkbox"${attr("checked", form.is_active, true)}${attr("disabled", !canSave() && !form.is_active, true)} class="border-studio-border text-studio-success-foreground focus:ring-studio-focus size-4 rounded border"/> <span class="text-studio-panel-foreground font-semibold">Set as active template</span></label> `);
        if (!canSave() && !form.is_active) {
          $$renderer3.push("<!--[0-->");
          $$renderer3.push(`<p class="text-studio-panel-muted-foreground mt-1 text-[10px]">Verification and a valid current PDF preview
                                    are required before activating.</p>`);
        } else {
          $$renderer3.push("<!--[-1-->");
        }
        $$renderer3.push(`<!--]--></div></div></aside> <main data-result-sheet-stage-main="verify" class="border-studio-border bg-studio-panel flex h-[min(70dvh,52rem)] min-h-[28rem] min-w-0 flex-col overflow-hidden rounded-xl border lg:h-auto lg:min-h-0"${attr("aria-busy", previewLoading)}><div class="border-studio-border bg-studio-panel/90 flex h-11 shrink-0 items-center justify-between border-b px-4"><span class="text-studio-panel-foreground text-xs font-black tracking-wider uppercase">Production PDF</span> `);
        Button($$renderer3, {
          type: "button",
          variant: "outline",
          size: "sm",
          class: "h-7 gap-1 px-2 text-[11px]",
          onclick: () => void runPreview(),
          disabled: previewLoading,
          children: ($$renderer4) => {
            Play($$renderer4, { class: "size-3" });
            $$renderer4.push(`<!----> Refresh`);
          },
          $$slots: { default: true }
        });
        $$renderer3.push(`<!----></div> <div data-result-sheet-preview-content=""${attr("data-result-sheet-verify-safe-bottom", verifySafeBottom)} class="box-border flex min-h-0 min-w-0 flex-1 flex-col overflow-hidden"${attr_style(`padding-bottom:calc(${verifySafeBottom}px + env(safe-area-inset-bottom));`)}>`);
        if (previewError) {
          $$renderer3.push("<!--[0-->");
          $$renderer3.push(`<div class="border-destructive/30 bg-destructive/10 text-destructive m-4 min-h-0 flex-1 space-y-2 overflow-y-auto rounded-xl border p-4 text-xs" role="alert"><div class="flex items-center gap-2 font-bold">`);
          Triangle_alert($$renderer3, { class: "size-4" });
          $$renderer3.push(`<!----> <span>Preview Compilation Issue</span></div> <p class="leading-relaxed">${escape_html(previewError)}</p> `);
          Button($$renderer3, {
            type: "button",
            variant: "outline",
            size: "sm",
            class: "mt-2 h-8",
            onclick: () => void runPreview(),
            children: ($$renderer4) => {
              $$renderer4.push(`<!---->Retry preview`);
            },
            $$slots: { default: true }
          });
          $$renderer3.push(`<!----></div>`);
        } else if (previewLoading) {
          $$renderer3.push("<!--[1-->");
          Loading($$renderer3, {
            kind: "render",
            title: "Rendering production PDF…",
            detail: form.mode === "docx" ? "LibreOffice is composing the PDF from the original Word document and its styles." : "DomPDF is generating deterministic vector output from the builder layout.",
            class: "min-h-0 w-full flex-1 rounded-none border-0 bg-transparent"
          });
        } else if (previewPdfUrl) {
          $$renderer3.push("<!--[2-->");
          $$renderer3.push(`<iframe title="Template PDF preview" class="block h-full min-h-0 w-full min-w-0 flex-1 bg-white"${attr("src", previewPdfUrl)}></iframe>`);
        } else {
          $$renderer3.push("<!--[-1-->");
          $$renderer3.push(`<div class="text-studio-panel-muted-foreground flex min-h-0 flex-1 flex-col items-center justify-center p-6 text-center">`);
          Triangle_alert($$renderer3, { class: "mb-2 size-8 opacity-40" });
          $$renderer3.push(`<!----> <p class="text-xs">Continue from Design or click Refresh to
                                    generate a PDF preview.</p></div>`);
        }
        $$renderer3.push(`<!--]--></div></main></div>`);
      }
      $$renderer3.push(`<!--]--></form></div> `);
      PlaceholderPicker($$renderer3, {
        open: pickerOpen,
        placeholderGroups,
        usedKeys: usedKeys(),
        recentKeys,
        mode: pickerMode === "insert" ? "insert" : "copy",
        onclose: closePicker,
        oncopy: copyPlaceholder,
        oninsert: insertBinding
      });
      $$renderer3.push(`<!---->`);
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
  ResultSheetTemplateEditor as R
};
