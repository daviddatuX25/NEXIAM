import { b as attr, e as escape_html, c as attr_class, s as stringify, a as ensure_array_like, d as derived } from "../app.js";
import { O as OmrCaptureInput } from "./OmrCaptureInput-CmVb1QoH.js";
import { I as Info } from "./info-C3MCzlQW.js";
import { C as Check } from "./check-DK45Y9SJ.js";
import { L as Loader_circle } from "./loader-circle-_UEl10Lx.js";
import { F as File_text } from "./file-text-PXEtmXm9.js";
import { P as Printer } from "./printer-Bx0z1CRb.js";
import { B as Book_open } from "./book-open-B6eMguqY.js";
import { R as Refresh_cw } from "./refresh-cw-CYdk8aE0.js";
import { U as Upload } from "./upload-CwqN2Utd.js";
import { E as Eye } from "./eye-DyG-RiwW.js";
import "axios";
import "@inertiajs/core";
import "clsx";
import "laravel-precognition";
import "tailwind-merge";
import "tailwind-variants";
/* empty css                                                       */
import "./camera-C09Eyogi.js";
import "./Icon-Dal7PqKK.js";
const OMR_PIPELINE_PHASES = {
  QUEUED: "queued",
  PREFLIGHT: "preflight",
  ORIENTATION: "orientation",
  LAYOUT_VERIFICATION: "layout_verification",
  RECOGNITION: "recognition",
  EVIDENCE_PERSISTENCE: "evidence_persistence",
  AWAITING_REVIEW: "awaiting_review",
  PASSED: "passed",
  FAILED: "failed"
};
const OMR_PHASE_LABELS = {
  queued: "Upload received — waiting for worker…",
  preflight: "Validating image format, resolution, and page limits…",
  orientation: "Finding orientation and corner markers…",
  layout_verification: "Verifying sheet layout and fiducial alignment…",
  recognition: "Detecting bubble marks and comparing expected answers…",
  evidence_persistence: "Saving aligned evidence and diagnostic overlays…",
  awaiting_review: "Analysis complete — choose Pass or Retake",
  passed: "Passed",
  failed: "Needs retake — analysis stopped"
};
const OMR_PHASE_ACTIVITY_DETAILS = {
  queued: "Capture received and queued for asynchronous analysis.",
  preflight: "Checking image format, resolution, and page limits.",
  orientation: "Searching the quarter-turn orientations for the signed sheet markers.",
  layout_verification: "Comparing corner markers and sheet geometry with the challenge.",
  recognition: "Reading bubble marks and comparing them with the expected answer vector.",
  evidence_persistence: "Persisting aligned evidence and diagnostics for this attempt.",
  awaiting_review: "Analysis finished; the captured evidence is ready for staff review.",
  passed: "All verification gates passed for this method.",
  failed: "Analysis stopped at the recorded failure phase."
};
function getPipelineActivityDetail(code, registrationMode = "fiducial_anchored") {
  const normalized = typeof code === "string" ? code.toLowerCase().trim() : "";
  if (registrationMode === "document_bound") {
    if (normalized === OMR_PIPELINE_PHASES.ORIENTATION)
      return "Checking the captured page orientation.";
    if (normalized === OMR_PIPELINE_PHASES.LAYOUT_VERIFICATION)
      return "Verifying the unique page boundary, rectification, and printed content.";
  }
  return OMR_PHASE_ACTIVITY_DETAILS[normalized] ?? "The verification worker is processing this capture; waiting for a recognized phase update.";
}
const OMR_ANALYSIS_STEPS = [
  { code: OMR_PIPELINE_PHASES.QUEUED, label: "Upload received" },
  { code: OMR_PIPELINE_PHASES.PREFLIGHT, label: "Validating image" },
  {
    code: OMR_PIPELINE_PHASES.ORIENTATION,
    label: "Finding orientation and corner markers"
  },
  {
    code: OMR_PIPELINE_PHASES.LAYOUT_VERIFICATION,
    label: "Verifying sheet layout"
  },
  { code: OMR_PIPELINE_PHASES.RECOGNITION, label: "Detecting bubble marks" },
  {
    code: OMR_PIPELINE_PHASES.RECOGNITION,
    label: "Comparing expected answers"
  },
  {
    code: OMR_PIPELINE_PHASES.EVIDENCE_PERSISTENCE,
    label: "Saving evidence"
  },
  { code: OMR_PIPELINE_PHASES.PASSED, label: "Passed or Needs retake" }
];
function getPipelinePhaseSteps(code, failedPhaseCode = null, registrationMode = "fiducial_anchored") {
  const normalized = typeof code === "string" ? code.toLowerCase().trim() : "";
  const normalizedFailedPhase = typeof failedPhaseCode === "string" ? failedPhaseCode.toLowerCase().trim() : "";
  const terminalSuccess = normalized === OMR_PIPELINE_PHASES.PASSED;
  const terminalReview = normalized === OMR_PIPELINE_PHASES.AWAITING_REVIEW;
  const terminalFailure = normalized === OMR_PIPELINE_PHASES.FAILED;
  const activeCode = terminalFailure ? normalizedFailedPhase : normalized;
  const phaseIndexes = OMR_ANALYSIS_STEPS.reduce(
    (indexes, step, index) => {
      if (step.code === activeCode) indexes.push(index);
      return indexes;
    },
    []
  );
  const firstActiveIndex = phaseIndexes[0] ?? -1;
  const lastActiveIndex = phaseIndexes.at(-1) ?? -1;
  return OMR_ANALYSIS_STEPS.map((sourceStep, index) => {
    const step = registrationMode === "document_bound" && sourceStep.code === OMR_PIPELINE_PHASES.ORIENTATION ? { ...sourceStep, label: "Checking page orientation" } : registrationMode === "document_bound" && sourceStep.code === OMR_PIPELINE_PHASES.LAYOUT_VERIFICATION ? { ...sourceStep, label: "Verifying document boundary" } : sourceStep;
    if (terminalSuccess) return { ...step, state: "complete" };
    if (terminalReview) {
      return {
        ...step,
        state: index === OMR_ANALYSIS_STEPS.length - 1 ? "current" : "complete"
      };
    }
    if (terminalFailure) {
      if (phaseIndexes.length === 0) {
        return {
          ...step,
          state: index === OMR_ANALYSIS_STEPS.length - 1 ? "failed" : "pending"
        };
      }
      return {
        ...step,
        state: index < firstActiveIndex ? "complete" : index <= lastActiveIndex ? "failed" : "pending"
      };
    }
    if (phaseIndexes.length === 0) return { ...step, state: "pending" };
    return {
      ...step,
      state: index < firstActiveIndex ? "complete" : index <= lastActiveIndex ? "current" : "pending"
    };
  });
}
function getPipelinePhaseCode(detail) {
  if (!detail || typeof detail !== "object") return null;
  const code = detail.phase_code ?? detail.stage;
  return typeof code === "string" && code.trim() ? code.trim().toLowerCase() : null;
}
function getPipelinePhaseLabel(code, registrationMode = "fiducial_anchored") {
  if (!code || typeof code !== "string") {
    return "Analysis in progress…";
  }
  const normalized = code.toLowerCase().trim();
  if (registrationMode === "document_bound") {
    if (normalized === OMR_PIPELINE_PHASES.ORIENTATION)
      return "Checking page orientation…";
    if (normalized === OMR_PIPELINE_PHASES.LAYOUT_VERIFICATION)
      return "Verifying document boundary and content…";
  }
  return OMR_PHASE_LABELS[normalized] ?? "Analysis in progress…";
}
function OmrPhysicalTestMethodCard($$renderer, $$props) {
  $$renderer.component(($$renderer2) => {
    let {
      method = "pre_shaded",
      registrationMode = "fiducial_anchored",
      isUploadedSource = false,
      methodData = null,
      requiredPageCount = 1,
      busy = false,
      captureBusy = false,
      captureDisabled = false,
      captureEnabled = true,
      captureQueueReason = null,
      capturePage = 1,
      phaseDetail = null,
      onCreate = null,
      onOpenPrint = null,
      onOpenGuide = null,
      onMarkPrinted = null,
      onSubmitCapture = null,
      onOpenEvidence = null,
      onRetry = null,
      onResumeCapture = null,
      onSelectMethod = null,
      onSelectAttempt = null
    } = $$props;
    let selectedMedium = method === "hand_shaded" ? "hb_2_pencil" : "";
    let selectionReason = "";
    let replacementAttemptId = null;
    let selectedPage = Math.max(1, Number(capturePage) || 1);
    const isHand = derived(() => method === "hand_shaded");
    const isUploaded = derived(() => Boolean(isUploadedSource));
    const data = derived(() => methodData ?? {});
    const state = derived(() => data().state ?? "not_started");
    const challenge = derived(() => data().challenge ?? (data().challenge_id ? data() : null));
    const challengeId = derived(() => Number(challenge()?.challenge_id ?? data().challenge_id) > 0 ? Number(challenge()?.challenge_id ?? data().challenge_id) : null);
    const canResume = derived(() => Boolean(challengeId()));
    const canResumeCapture = derived(() => state() === "awaiting_capture" && captureEnabled && canResume() && typeof onResumeCapture === "function");
    const scopedRunPhaseDetail = derived(() => !phaseDetail?.method || phaseDetail.method === method ? phaseDetail : null);
    const activePhaseDetail = derived(() => state() === "failed" ? data().phase_detail ?? scopedRunPhaseDetail() : scopedRunPhaseDetail() ?? data().phase_detail ?? null);
    const activePhaseCode = derived(() => getPipelinePhaseCode(activePhaseDetail()));
    const activePhaseLabel = derived(() => getPipelinePhaseLabel(activePhaseCode(), registrationMode));
    const failedPhaseCode = derived(() => typeof activePhaseDetail()?.failed_phase_code === "string" ? activePhaseDetail().failed_phase_code : null);
    const isAnalysisFailure = derived(() => state() === "failed" || state() === "analyzing" && data().run_status === "failed");
    const analysisSteps = derived(() => getPipelinePhaseSteps(activePhaseCode(), failedPhaseCode(), registrationMode));
    const pages = derived(() => Array.isArray(data().pages) && data().pages.length > 0 ? data().pages : Array.from({ length: Math.max(1, Number(requiredPageCount) || 1) }, (_, index) => ({
      page_no: index + 1,
      state: "not_started",
      selected_attempt: null,
      selected_attempt_id: null
    })));
    const attempts = derived(() => Array.isArray(data().attempts) ? data().attempts : []);
    function isSupersededAttempt(attempt) {
      return attempt?.attempt_state === "superseded" || attempt?.superseded === true || Boolean(attempt?.superseded_at);
    }
    const retakePage = derived(() => {
      const actionablePage = pages().find((page) => ["failed", "needs_review"].includes(page.state));
      return Number(actionablePage?.page_no ?? selectedPage) || 1;
    });
    const unselectedPasses = derived(() => attempts().filter((attempt) => (attempt.attempt_state === "passed" || attempt.status === "pass") && !isSupersededAttempt(attempt) && !isAttemptSelected(attempt)));
    const firstAcceptancePasses = derived(() => unselectedPasses().filter((attempt) => !pageHasSelection(matchingPageForAttempt(attempt))));
    const printUrl = derived(() => data().print_url ?? data().challenge?.print_url ?? null);
    const hasGuide = derived(() => Boolean(onOpenGuide && (data().challenge_id ?? data().challenge?.challenge_id ?? printUrl())));
    const canCreate = derived(() => captureEnabled && !canResume() && (state() === "not_started" || state() === "failed"));
    const canCapture = derived(() => state() === "awaiting_capture" && captureEnabled);
    const isCaptureQueued = derived(() => state() === "awaiting_capture" && !captureEnabled);
    const canSelectMethod = derived(() => typeof onSelectMethod === "function" && !captureEnabled && state() !== "passed" && !["awaiting_capture", "analyzing", "needs_review", "failed"].includes(state()));
    const isAnalyzing = derived(() => state() === "analyzing" && data().run_status !== "failed");
    const currentActivityDetail = derived(() => isAnalyzing() ? getPipelineActivityDetail(activePhaseCode(), registrationMode) : null);
    const isRunWideFailure = derived(() => data().run_status === "failed");
    const failure = derived(() => data().failure && typeof data().failure === "object" ? data().failure : null);
    const failureWhatHappened = derived(() => typeof failure()?.what_happened === "string" ? failure().what_happened : isRunWideFailure() ? "The asynchronous verification run stopped before this method completed." : "Capture analysis stopped before the sheet could be verified.");
    const failureObservation = derived(() => typeof failure()?.observation === "string" ? failure().observation : typeof data().safe_error === "string" ? data().safe_error : "The verification service did not provide a structured diagnosis.");
    const failureRecommendedFix = derived(() => typeof failure()?.recommended_fix === "string" ? failure().recommended_fix : "Retake the page. Ask an administrator to inspect the technical details if the problem repeats.");
    const failureTechnical = derived(() => failure()?.technical_details && typeof failure().technical_details === "object" ? failure().technical_details : {});
    const failureTechnicalEntries = derived(() => (() => {
      const raw = Object.entries(failureTechnical()).filter(([, value]) => value !== null && value !== void 0 && value !== "");
      const seenCodes = /* @__PURE__ */ new Set();
      return raw.filter(([label, value]) => {
        const strVal = String(value);
        if (label === "error_code" || label === "failure_code") {
          if (seenCodes.has(strVal)) return false;
          seenCodes.add(strVal);
        }
        return true;
      });
    })());
    const isNeedsReview = derived(() => state() === "needs_review");
    const stateLabel = derived(() => ({
      not_started: "Ready",
      challenge_ready: "Sheet ready",
      awaiting_capture: "Awaiting capture",
      analyzing: "Analyzing",
      needs_review: "Review needed",
      passed: "Passed",
      failed: "Failed"
    })[state()] ?? state());
    const stateTone = derived(() => ({
      passed: "border-emerald-300 bg-emerald-50/70 text-emerald-900 dark:border-emerald-900/60 dark:bg-emerald-950/30 dark:text-emerald-200",
      failed: "border-rose-300 bg-rose-50/70 text-rose-900 dark:border-rose-900/60 dark:bg-rose-950/30 dark:text-rose-200",
      needs_review: "border-amber-300 bg-amber-50/70 text-amber-900 dark:border-amber-900/60 dark:bg-amber-950/30 dark:text-amber-200",
      analyzing: "border-blue-300 bg-blue-50/70 text-blue-900 dark:border-blue-900/60 dark:bg-blue-950/30 dark:text-blue-200",
      awaiting_capture: "border-sky-300 bg-sky-50/70 text-sky-900 dark:border-sky-900/60 dark:bg-sky-950/30 dark:text-sky-200",
      challenge_ready: "border-border bg-muted/40 text-foreground"
    })[state()] ?? "border-border bg-muted/20 text-muted-foreground");
    function pageSelectionId(page) {
      return page?.selected_attempt_id ?? page?.selected_attempt?.capture_id ?? null;
    }
    function pageHasSelection(page) {
      return pageSelectionId(page) != null;
    }
    function sameCapture(first, second) {
      return first != null && second != null && String(first) === String(second);
    }
    function matchingPageForAttempt(attempt) {
      return pages().find((page) => sameCapture(page.page_no, attempt?.page_no)) ?? null;
    }
    function isAttemptSelected(attempt) {
      return pages().some((page) => sameCapture(pageSelectionId(page), attempt?.capture_id));
    }
    function pageAttempt(page) {
      return page.selected_attempt ?? (pageHasSelection(page) ? { capture_id: page.selected_attempt_id } : null);
    }
    function attemptLabel(attempt) {
      return `Attempt ${attempt.attempt_no ?? attempt.attempt ?? "—"} · page ${attempt.page_no ?? "—"}`;
    }
    function submit(file) {
      if (!file || !onSubmitCapture || isHand() && !selectedMedium) return;
      onSubmitCapture({
        file,
        method,
        pageNo: selectedPage,
        medium: isHand() ? selectedMedium : null
      });
    }
    function isReplacementCandidate(attempt) {
      return pageHasSelection(matchingPageForAttempt(attempt)) && !isAttemptSelected(attempt) && !isSupersededAttempt(attempt) && (attempt?.attempt_state === "passed" || attempt?.status === "pass");
    }
    $$renderer2.push(`<article class="border-border/80 bg-card flex min-w-0 flex-col gap-3 rounded-xl border p-3.5 shadow-xs"${attr("data-omr-method", method)}><header class="flex min-w-0 items-start justify-between gap-2.5"><div class="min-w-0"><div class="flex flex-wrap items-center gap-1.5"><h3 class="text-foreground truncate text-xs font-bold">${escape_html(isUploaded() ? isHand() ? "2. Hand-marked test sheet" : "1. Blank baseline sheet" : isHand() ? "2. Hand-shaded test" : "1. Pre-shaded test")}</h3> <button type="button" class="text-muted-foreground hover:text-foreground focus-visible:ring-ring inline-flex items-center rounded p-0.5 transition focus-visible:ring-2 focus-visible:outline-none"${attr("aria-label", isHand() ? "Hand-shaded test info" : "Pre-shaded test info")}${attr("title", isHand() ? "Hand-shaded test info" : "Pre-shaded test info")}>`);
    Info($$renderer2, { class: "size-3.5", "aria-hidden": "true" });
    $$renderer2.push(`<!----></button> `);
    if (isHand()) {
      $$renderer2.push("<!--[0-->");
      $$renderer2.push(`<span class="rounded-full bg-sky-100 px-1.5 py-0.5 text-[9px] font-bold tracking-wide text-sky-800 uppercase dark:bg-sky-950/60 dark:text-sky-300">Human marks</span>`);
    } else {
      $$renderer2.push("<!--[-1-->");
      $$renderer2.push(`<span class="rounded-full bg-emerald-100 px-1.5 py-0.5 text-[9px] font-bold tracking-wide text-emerald-800 uppercase dark:bg-emerald-950/60 dark:text-emerald-300">${escape_html(isUploaded() ? "Clean paper" : "Machine baseline")}</span>`);
    }
    $$renderer2.push(`<!--]--></div> `);
    {
      $$renderer2.push("<!--[-1-->");
    }
    $$renderer2.push(`<!--]--></div> <span${attr_class(`shrink-0 rounded-full border px-2 py-0.5 text-[10px] font-semibold ${stringify(stateTone())}`)}${attr("aria-label", `Method status: ${stateLabel()}`)}>${escape_html(stateLabel())}</span></header> <div class="flex flex-wrap items-center gap-1.5"${attr("aria-label", `${isHand() ? "Hand-shaded" : "Pre-shaded"} test pages`)}><!--[-->`);
    const each_array = ensure_array_like(pages());
    for (let $$index = 0, $$length = each_array.length; $$index < $$length; $$index++) {
      let page = each_array[$$index];
      const selected = pageAttempt(page);
      $$renderer2.push(`<span${attr_class(`inline-flex items-center gap-1 rounded-full border px-2 py-1 text-[10px] ${selected ? "border-emerald-300 bg-emerald-50 text-emerald-800 dark:border-emerald-900/70 dark:bg-emerald-950/30 dark:text-emerald-200" : page.state === "passed" ? "border-amber-300 bg-amber-50 text-amber-800 dark:border-amber-900/60 dark:bg-amber-950/30 dark:text-amber-200" : "border-border bg-background text-muted-foreground"}`)}>`);
      if (selected) {
        $$renderer2.push("<!--[0-->");
        Check($$renderer2, { class: "size-3", "aria-hidden": "true" });
      } else {
        $$renderer2.push("<!--[-1-->");
      }
      $$renderer2.push(`<!--]--> Page ${escape_html(page.page_no)}: ${escape_html(selected ? "selected pass" : page.state ?? "not started")}</span>`);
    }
    $$renderer2.push(`<!--]--></div> <div class="flex flex-wrap items-center gap-2">`);
    if (canCreate() && onCreate) {
      $$renderer2.push("<!--[0-->");
      $$renderer2.push(`<button type="button"${attr("aria-label", isHand() ? "Create hand-shaded test sheet" : "Create pre-shaded test sheet")} class="focus-visible:ring-ring inline-flex items-center gap-1.5 rounded-md bg-slate-900 px-2.5 py-1.5 text-[11px] font-semibold text-white hover:bg-slate-800 focus-visible:ring-2 focus-visible:outline-none disabled:opacity-40 dark:bg-slate-100 dark:text-slate-900 dark:hover:bg-slate-200"${attr("disabled", busy, true)}>`);
      if (busy) {
        $$renderer2.push("<!--[0-->");
        Loader_circle($$renderer2, { class: "size-3.5 animate-spin", "aria-hidden": "true" });
      } else {
        $$renderer2.push("<!--[-1-->");
        File_text($$renderer2, { class: "size-3.5", "aria-hidden": "true" });
      }
      $$renderer2.push(`<!--]--> ${escape_html(isUploaded() ? "Start physical test" : "Create test sheet")}</button>`);
    } else {
      $$renderer2.push("<!--[-1-->");
    }
    $$renderer2.push(`<!--]--> `);
    if (printUrl() && !isUploaded()) {
      $$renderer2.push("<!--[0-->");
      $$renderer2.push(`<button type="button" aria-label="Open print PDF" class="border-border hover:bg-muted focus-visible:ring-ring inline-flex items-center gap-1.5 rounded-md border px-2.5 py-1.5 text-[11px] font-semibold focus-visible:ring-2 focus-visible:outline-none disabled:opacity-40"${attr("disabled", busy, true)}>`);
      Printer($$renderer2, { class: "size-3.5", "aria-hidden": "true" });
      $$renderer2.push(`<!----> Print challenge `);
      {
        $$renderer2.push("<!--[-1-->");
      }
      $$renderer2.push(`<!--]--></button>`);
    } else {
      $$renderer2.push("<!--[-1-->");
    }
    $$renderer2.push(`<!--]--> `);
    if (hasGuide()) {
      $$renderer2.push("<!--[0-->");
      $$renderer2.push(`<button type="button" aria-label="View shading guide" class="border-border hover:bg-muted focus-visible:ring-ring inline-flex items-center gap-1.5 rounded-md border px-2.5 py-1.5 text-[11px] font-semibold focus-visible:ring-2 focus-visible:outline-none disabled:opacity-40"${attr("disabled", busy, true)}>`);
      Book_open($$renderer2, { class: "size-3.5", "aria-hidden": "true" });
      $$renderer2.push(`<!----> ${escape_html(isHand() ? "Shading guide" : "View guide")} `);
      {
        $$renderer2.push("<!--[-1-->");
      }
      $$renderer2.push(`<!--]--></button>`);
    } else {
      $$renderer2.push("<!--[-1-->");
    }
    $$renderer2.push(`<!--]--> `);
    if (state() === "challenge_ready" && onMarkPrinted && !isUploaded()) {
      $$renderer2.push("<!--[0-->");
      $$renderer2.push(`<button type="button" aria-label="Mark printed &amp; continue to capture" class="focus-visible:ring-ring inline-flex items-center gap-1.5 rounded-md bg-slate-900 px-2.5 py-1.5 text-[11px] font-semibold text-white hover:bg-slate-800 focus-visible:ring-2 focus-visible:outline-none disabled:opacity-40 dark:bg-slate-100 dark:text-slate-900 dark:hover:bg-slate-200"${attr("disabled", busy, true)}>`);
      Check($$renderer2, { class: "size-3.5", "aria-hidden": "true" });
      $$renderer2.push(`<!---->Mark printed &amp; continue to capture</button>`);
    } else {
      $$renderer2.push("<!--[-1-->");
    }
    $$renderer2.push(`<!--]--> `);
    if (canSelectMethod()) {
      $$renderer2.push("<!--[0-->");
      $$renderer2.push(`<button type="button"${attr("aria-label", `Work on ${isHand() ? "hand-shaded" : "pre-shaded"} test`)} class="border-border hover:bg-muted focus-visible:ring-ring inline-flex items-center gap-1.5 rounded-md border px-2.5 py-1.5 text-[11px] font-semibold focus-visible:ring-2 focus-visible:outline-none"${attr("disabled", busy, true)}>Work on this method</button>`);
    } else {
      $$renderer2.push("<!--[-1-->");
    }
    $$renderer2.push(`<!--]--></div> `);
    if (isAnalyzing() || isAnalysisFailure()) {
      $$renderer2.push("<!--[0-->");
      $$renderer2.push(`<section${attr_class(`rounded-xl border p-3.5 ${isAnalysisFailure() ? "border-rose-200 bg-rose-50/70 text-rose-950 dark:border-rose-900/60 dark:bg-rose-950/25 dark:text-rose-100" : "border-blue-200 bg-blue-50/70 text-blue-950 dark:border-blue-900/60 dark:bg-blue-950/25 dark:text-blue-100"}`)} role="status" aria-live="polite" aria-atomic="false"><div class="flex flex-wrap items-start justify-between gap-2"><div class="min-w-0"><h4 class="text-xs font-semibold">${escape_html(isAnalysisFailure() ? "Capture analysis stopped" : "Analyzing captured page")}</h4> <p${attr_class(`mt-0.5 text-[10px] leading-relaxed ${isAnalysisFailure() ? "text-rose-800 dark:text-rose-300" : "text-blue-800 dark:text-blue-300"}`)}>${escape_html(activePhaseLabel())}</p></div></div> <ol class="mt-3" aria-label="Capture analysis progress"><!--[-->`);
      const each_array_1 = ensure_array_like(analysisSteps());
      for (let index = 0, $$length = each_array_1.length; index < $$length; index++) {
        let step = each_array_1[index];
        $$renderer2.push(`<li class="relative flex min-w-0 gap-3 pb-3 last:pb-0"${attr("aria-current", ["current", "failed"].includes(step.state) ? "step" : void 0)}${attr("aria-label", `Step ${stringify(index + 1)}, ${stringify(step.label)}: ${stringify(step.state)}`)}>`);
        if (index < analysisSteps().length - 1) {
          $$renderer2.push("<!--[0-->");
          $$renderer2.push(`<span${attr_class(`absolute start-[11.5px] top-6 bottom-0 w-px ${step.state === "complete" ? isAnalysisFailure() ? "bg-rose-500 dark:bg-rose-400" : "bg-blue-600 dark:bg-blue-400" : isAnalysisFailure() ? "bg-rose-200 dark:bg-rose-900" : "bg-blue-200 dark:bg-blue-800"}`)} aria-hidden="true"></span>`);
        } else {
          $$renderer2.push("<!--[-1-->");
        }
        $$renderer2.push(`<!--]--> <span${attr_class(`relative z-10 inline-flex size-6 shrink-0 items-center justify-center rounded-full border text-[10px] font-bold tabular-nums ${step.state === "complete" ? isAnalysisFailure() ? "border-rose-500 bg-rose-500 text-white dark:border-rose-400 dark:bg-rose-400 dark:text-rose-950" : "border-blue-600 bg-blue-600 text-white dark:border-blue-400 dark:bg-blue-400 dark:text-blue-950" : step.state === "failed" ? "border-rose-700 bg-white text-rose-800 shadow-sm dark:border-rose-300 dark:bg-rose-950 dark:text-rose-200" : step.state === "current" ? "border-blue-700 bg-white text-blue-800 shadow-sm dark:border-blue-300 dark:bg-blue-950 dark:text-blue-200" : isAnalysisFailure() ? "border-rose-300 bg-rose-100/80 text-rose-700 dark:border-rose-900 dark:bg-rose-950 dark:text-rose-400" : "border-blue-300 bg-blue-100/80 text-blue-700 dark:border-blue-800 dark:bg-blue-950 dark:text-blue-400"}`)} aria-hidden="true">`);
        if (step.state === "complete") {
          $$renderer2.push("<!--[0-->");
          Check($$renderer2, { class: "size-3.5" });
        } else {
          $$renderer2.push("<!--[-1-->");
          $$renderer2.push(`${escape_html(index + 1)}`);
        }
        $$renderer2.push(`<!--]--></span> <div class="flex min-w-0 flex-1 items-center justify-between gap-3 pt-0.5"><span${attr_class(`min-w-0 text-[11px] leading-5 font-medium ${step.state === "pending" ? "text-slate-600 dark:text-slate-400" : isAnalysisFailure() ? "text-rose-950 dark:text-rose-100" : "text-blue-950 dark:text-blue-100"}`)}>${escape_html(step.label)}</span> <span${attr_class(`inline-flex shrink-0 items-center gap-1.5 text-[9px] font-semibold tracking-wide uppercase ${step.state === "pending" ? "text-slate-500 dark:text-slate-400" : isAnalysisFailure() ? "text-rose-700 dark:text-rose-300" : "text-blue-700 dark:text-blue-300"}`)}>`);
        if (step.state === "current") {
          $$renderer2.push("<!--[0-->");
          Loader_circle($$renderer2, {
            class: "size-3 animate-spin motion-reduce:animate-none",
            "aria-hidden": "true"
          });
          $$renderer2.push(`<!----> Current`);
        } else if (step.state === "complete") {
          $$renderer2.push("<!--[1-->");
          $$renderer2.push(`Complete`);
        } else if (step.state === "failed") {
          $$renderer2.push("<!--[2-->");
          $$renderer2.push(`Failed`);
        } else {
          $$renderer2.push("<!--[-1-->");
          $$renderer2.push(`Waiting`);
        }
        $$renderer2.push(`<!--]--></span></div></li>`);
      }
      $$renderer2.push(`<!--]--></ol> `);
      if (isAnalyzing() && currentActivityDetail()) {
        $$renderer2.push("<!--[0-->");
        $$renderer2.push(`<p class="mt-3 rounded-md border border-blue-200/70 bg-blue-100/40 px-2.5 py-2 text-[10px] leading-relaxed text-blue-900 dark:border-blue-900/60 dark:bg-blue-950/30 dark:text-blue-200" aria-label="Current analysis activity"><span class="font-semibold">Current activity:</span> ${escape_html(currentActivityDetail())}</p>`);
      } else {
        $$renderer2.push("<!--[-1-->");
      }
      $$renderer2.push(`<!--]--></section>`);
    } else {
      $$renderer2.push("<!--[-1-->");
    }
    $$renderer2.push(`<!--]--> `);
    if (state() === "failed") {
      $$renderer2.push("<!--[0-->");
      $$renderer2.push(`<section class="rounded-xl border border-rose-300/80 bg-rose-50/70 p-3.5 text-rose-950 dark:border-rose-900/60 dark:bg-rose-950/25 dark:text-rose-100" role="alert" aria-label="Why verification stopped"><div class="flex flex-wrap items-start justify-between gap-2"><div class="min-w-0"><h4 class="text-xs font-semibold">Why verification stopped</h4> <p class="mt-1 text-[11px] font-medium">${escape_html(failureWhatHappened())}</p></div> `);
      if (onRetry && !isRunWideFailure()) {
        $$renderer2.push("<!--[0-->");
        $$renderer2.push(`<button type="button"${attr("aria-label", `Retake page ${retakePage()}`)} class="bg-background hover:bg-muted focus-visible:ring-ring inline-flex shrink-0 items-center gap-1 rounded-md border border-rose-300 px-2.5 py-1 text-[10px] font-semibold text-rose-900 focus-visible:ring-2 focus-visible:outline-none dark:text-rose-200"${attr("disabled", busy, true)}>`);
        Refresh_cw($$renderer2, { class: "size-3", "aria-hidden": "true" });
        $$renderer2.push(`<!----> Retake page ${escape_html(retakePage())}</button>`);
      } else {
        $$renderer2.push("<!--[-1-->");
      }
      $$renderer2.push(`<!--]--></div> <div class="mt-3 grid gap-2"><div><p class="text-[9px] font-bold tracking-wide text-rose-700 uppercase dark:text-rose-300">Engine observation</p> <p class="mt-0.5 text-[10px] leading-relaxed">${escape_html(failureObservation())}</p></div> <div><p class="text-[9px] font-bold tracking-wide text-rose-700 uppercase dark:text-rose-300">Recommended fix</p> <p class="mt-0.5 text-[10px] leading-relaxed">${escape_html(failureRecommendedFix())}</p></div></div> `);
      if (failureTechnicalEntries().length > 0) {
        $$renderer2.push("<!--[0-->");
        $$renderer2.push(`<details class="mt-3 border-t border-rose-200/80 pt-2 dark:border-rose-900/70"><summary class="focus-visible:ring-ring cursor-pointer text-[10px] font-semibold focus-visible:ring-2 focus-visible:outline-none">Technical details</summary> <dl class="mt-2 grid gap-1 text-[9px]"><!--[-->`);
        const each_array_2 = ensure_array_like(failureTechnicalEntries());
        for (let $$index_2 = 0, $$length = each_array_2.length; $$index_2 < $$length; $$index_2++) {
          let [label, value] = each_array_2[$$index_2];
          $$renderer2.push(`<div class="grid grid-cols-[minmax(0,1fr)_minmax(0,1.5fr)] gap-2"><dt class="font-semibold capitalize">${escape_html(label.replaceAll("_", " "))}</dt> <dd class="min-w-0 font-mono break-words">${escape_html(String(value))}</dd></div>`);
        }
        $$renderer2.push(`<!--]--></dl></details>`);
      } else {
        $$renderer2.push("<!--[-1-->");
      }
      $$renderer2.push(`<!--]--></section>`);
    } else {
      $$renderer2.push("<!--[-1-->");
    }
    $$renderer2.push(`<!--]--> `);
    if (isNeedsReview()) {
      $$renderer2.push("<!--[0-->");
      $$renderer2.push(`<div class="rounded-lg border border-amber-300/80 bg-amber-50/70 p-3 text-[11px] text-amber-900 dark:border-amber-900/60 dark:bg-amber-950/25 dark:text-amber-200"><div class="flex items-center justify-between gap-2"><strong class="text-xs">Evidence review ready</strong> `);
      if (onRetry) {
        $$renderer2.push("<!--[0-->");
        $$renderer2.push(`<button type="button" aria-label="Retake capture" class="bg-background text-foreground hover:bg-muted focus-visible:ring-ring inline-flex items-center gap-1 rounded-md border border-amber-300 px-2 py-1 text-[10px] font-semibold focus-visible:ring-2 focus-visible:outline-none"${attr("disabled", busy, true)}>`);
        Refresh_cw($$renderer2, { class: "size-3", "aria-hidden": "true" });
        $$renderer2.push(`<!---->Retake
                        capture</button>`);
      } else {
        $$renderer2.push("<!--[-1-->");
      }
      $$renderer2.push(`<!--]--></div> <p class="mt-1 text-[10px] leading-relaxed text-amber-800 dark:text-amber-300">Analysis completed. Inspect diagnostic overlays and accept the
                result or retake with improved lighting/alignment.</p></div>`);
    } else {
      $$renderer2.push("<!--[-1-->");
    }
    $$renderer2.push(`<!--]--> `);
    if (isCaptureQueued()) {
      $$renderer2.push("<!--[0-->");
      $$renderer2.push(`<div class="rounded-lg border border-sky-200/80 bg-sky-50/70 p-3 text-[11px] text-sky-950 dark:border-sky-900/60 dark:bg-sky-950/25 dark:text-sky-100" role="status" aria-live="polite"><strong class="text-xs">Capture queued</strong> <p class="mt-1 text-[10px] leading-relaxed text-sky-800 dark:text-sky-300">${escape_html(captureQueueReason ?? "Select this method when you are ready to capture its sheet.")}</p> `);
      if (onSelectMethod) {
        $$renderer2.push("<!--[0-->");
        $$renderer2.push(`<button type="button"${attr("aria-label", `Use ${isHand() ? "hand-shaded" : "pre-shaded"} capture`)} class="bg-background hover:bg-muted focus-visible:ring-ring mt-2 inline-flex items-center gap-1.5 rounded-md border border-sky-300 px-2.5 py-1 text-[10px] font-semibold text-sky-900 focus-visible:ring-2 focus-visible:outline-none dark:border-sky-800 dark:text-sky-200"${attr("disabled", busy, true)}>Use this capture</button>`);
      } else {
        $$renderer2.push("<!--[-1-->");
      }
      $$renderer2.push(`<!--]--></div>`);
    } else {
      $$renderer2.push("<!--[-1-->");
    }
    $$renderer2.push(`<!--]--> `);
    if (canCapture()) {
      $$renderer2.push("<!--[0-->");
      $$renderer2.push(`<div class="border-border/70 border-t pt-3"><div class="mb-2 flex flex-wrap items-center justify-between gap-2"><label class="flex items-center gap-1.5 text-[11px] font-semibold"${attr("for", `capture-page-${method}`)}>Capture page <input${attr("id", `capture-page-${method}`)} type="number" min="1"${attr("max", Math.max(1, requiredPageCount))}${attr("value", selectedPage)} class="border-input bg-background focus-visible:ring-ring w-16 rounded-md border px-2 py-1 text-xs focus-visible:ring-2 focus-visible:outline-none"/></label> <span class="text-muted-foreground text-[10px]">${escape_html(selectedPage)} of ${escape_html(requiredPageCount)}</span></div> `);
      if (canResumeCapture()) {
        $$renderer2.push("<!--[0-->");
        $$renderer2.push(`<div class="mb-2 flex flex-wrap items-center justify-between gap-2 rounded-lg border border-sky-200/80 bg-sky-50/60 p-2 text-[10px] text-sky-900 dark:border-sky-900/60 dark:bg-sky-950/25 dark:text-sky-200"><span><strong>Existing capture</strong> is ready. Resume restores
                        this surface without creating a new attempt.</span> <button type="button"${attr("aria-label", `Resume capture for ${isHand() ? "hand-shaded" : "pre-shaded"} test`)} class="bg-background hover:bg-muted focus-visible:ring-ring inline-flex items-center gap-1.5 rounded-md border border-sky-300 px-2.5 py-1 text-[10px] font-semibold text-sky-900 focus-visible:ring-2 focus-visible:outline-none dark:border-sky-800 dark:text-sky-200"${attr("disabled", busy, true)}>`);
        Upload($$renderer2, { class: "size-3", "aria-hidden": "true" });
        $$renderer2.push(`<!---->Resume
                        capture</button></div>`);
      } else {
        $$renderer2.push("<!--[-1-->");
      }
      $$renderer2.push(`<!--]--> `);
      if (isHand()) {
        $$renderer2.push("<!--[0-->");
        $$renderer2.push(`<fieldset class="border-border/70 mb-2 rounded-lg border p-2"><legend class="px-1 text-[10px] font-semibold">Marking instrument</legend> <div class="flex flex-wrap gap-3 text-[11px]"><label class="inline-flex cursor-pointer items-center gap-1.5"><input type="radio"${attr("name", `medium-${method}`)} value="hb_2_pencil"${attr("checked", selectedMedium === "hb_2_pencil", true)}/> HB / #2 pencil (default)</label> <label class="inline-flex cursor-pointer items-center gap-1.5"><input type="radio"${attr("name", `medium-${method}`)} value="dark_black_pen"${attr("checked", selectedMedium === "dark_black_pen", true)}/> Dark black ballpoint pen</label></div></fieldset>`);
      } else {
        $$renderer2.push("<!--[-1-->");
      }
      $$renderer2.push(`<!--]--> `);
      OmrCaptureInput($$renderer2, {
        submit,
        busy: captureBusy,
        disabled: captureDisabled || isHand() && !selectedMedium,
        disabledReason: isHand() && !selectedMedium ? "Choose the recorded shading medium before capturing." : "Capture opens once this test sheet is printed.",
        accept: ["image/jpeg", "image/png", "image/webp", "application/pdf"],
        labels: {
          runLabel: `Capture page ${selectedPage}`,
          busyLabel: "Analyzing capture…",
          captureLabel: "Capture snapshot"
        }
      });
      $$renderer2.push(`<!----></div>`);
    } else {
      $$renderer2.push("<!--[-1-->");
    }
    $$renderer2.push(`<!--]--> `);
    if (pages().length > 0) {
      $$renderer2.push("<!--[0-->");
      $$renderer2.push(`<div class="border-border/70 flex flex-col gap-2 border-t pt-2.5" aria-label="Selected attempts"><!--[-->`);
      const each_array_3 = ensure_array_like(pages());
      for (let $$index_3 = 0, $$length = each_array_3.length; $$index_3 < $$length; $$index_3++) {
        let page = each_array_3[$$index_3];
        const selected = pageAttempt(page);
        $$renderer2.push(`<div class="border-border/70 bg-muted/15 rounded-lg border p-2.5 text-[10px]"><div class="flex items-center justify-between gap-2"><strong>Page ${escape_html(page.page_no)}</strong> <span class="text-muted-foreground">${escape_html(selected ? "Selected pass" : "No selected pass")}</span></div> `);
        if (selected) {
          $$renderer2.push("<!--[0-->");
          $$renderer2.push(`<p class="text-muted-foreground mt-1">Attempt ${escape_html(selected.attempt_no ?? selected.attempt ?? "—")} is the selected passing evidence.</p>`);
        } else {
          $$renderer2.push("<!--[-1-->");
          $$renderer2.push(`<p class="text-muted-foreground mt-1">Complete and accept a passing attempt for this page.</p>`);
        }
        $$renderer2.push(`<!--]--> `);
        if (selected?.capture_id && onOpenEvidence) {
          $$renderer2.push("<!--[0-->");
          $$renderer2.push(`<button type="button" class="text-primary focus-visible:ring-ring mt-2 inline-flex items-center gap-1 text-[10px] font-semibold underline-offset-2 hover:underline focus-visible:ring-2 focus-visible:outline-none">`);
          Eye($$renderer2, { class: "size-3", "aria-hidden": "true" });
          $$renderer2.push(`<!---->Open
                            evidence</button>`);
        } else {
          $$renderer2.push("<!--[-1-->");
        }
        $$renderer2.push(`<!--]--></div>`);
      }
      $$renderer2.push(`<!--]--></div>`);
    } else {
      $$renderer2.push("<!--[-1-->");
    }
    $$renderer2.push(`<!--]--> `);
    if (firstAcceptancePasses().length > 0) {
      $$renderer2.push("<!--[0-->");
      $$renderer2.push(`<div class="rounded-lg border border-emerald-200 bg-emerald-50/70 p-2.5 text-[10px] text-emerald-900 dark:border-emerald-900/60 dark:bg-emerald-950/25 dark:text-emerald-200"><p class="text-xs font-semibold text-emerald-950 dark:text-emerald-100">Passing attempt ready for acceptance</p> <!--[-->`);
      const each_array_4 = ensure_array_like(firstAcceptancePasses());
      for (let $$index_4 = 0, $$length = each_array_4.length; $$index_4 < $$length; $$index_4++) {
        let attempt = each_array_4[$$index_4];
        $$renderer2.push(`<div class="bg-background/80 mt-2 flex flex-col gap-2 rounded-md border border-emerald-300/80 p-2"><div class="flex items-center justify-between gap-2"><span class="text-foreground font-semibold">${escape_html(attemptLabel(attempt))}</span> `);
        if (onOpenEvidence) {
          $$renderer2.push("<!--[0-->");
          $$renderer2.push(`<button type="button" class="text-primary focus-visible:ring-ring inline-flex items-center gap-1 text-[10px] font-semibold hover:underline focus-visible:ring-2 focus-visible:outline-none">`);
          Eye($$renderer2, { class: "size-3", "aria-hidden": "true" });
          $$renderer2.push(`<!---->Inspect
                                evidence</button>`);
        } else {
          $$renderer2.push("<!--[-1-->");
        }
        $$renderer2.push(`<!--]--></div> <div class="flex items-center justify-between gap-2"><span class="text-muted-foreground">All corner fiducials and alignment gates cleared
                            cleanly.</span> <button type="button" class="focus-visible:ring-ring inline-flex items-center gap-1 rounded-md bg-emerald-700 px-3 py-1.5 text-[11px] font-semibold text-white hover:bg-emerald-600 focus-visible:ring-2 focus-visible:outline-none disabled:opacity-40"${attr("disabled", busy, true)}>`);
        Check($$renderer2, { class: "size-3.5", "aria-hidden": "true" });
        $$renderer2.push(`<!---->Accept
                            result</button></div></div>`);
      }
      $$renderer2.push(`<!--]--></div>`);
    } else {
      $$renderer2.push("<!--[-1-->");
    }
    $$renderer2.push(`<!--]--> `);
    if (attempts().length > 0) {
      $$renderer2.push("<!--[0-->");
      $$renderer2.push(`<details class="border-border/70 border-t pt-2.5"><summary class="text-muted-foreground focus-visible:ring-ring cursor-pointer list-none text-[11px] font-semibold focus-visible:ring-2 focus-visible:outline-none">Attempt history (${escape_html(attempts().length)})</summary> <div class="mt-2 space-y-1.5"><!--[-->`);
      const each_array_5 = ensure_array_like(attempts());
      for (let $$index_5 = 0, $$length = each_array_5.length; $$index_5 < $$length; $$index_5++) {
        let attempt = each_array_5[$$index_5];
        const selected = isAttemptSelected(attempt);
        const canUseAsReplacement = isReplacementCandidate(attempt);
        const isReplacing = canUseAsReplacement && sameCapture(replacementAttemptId, attempt.capture_id);
        $$renderer2.push(`<div class="border-border/70 bg-muted/15 rounded-md border px-2.5 py-2 text-[10px]"><div class="flex flex-wrap items-center justify-between gap-2"><span><strong>${escape_html(attemptLabel(attempt))}</strong> · ${escape_html(attempt.attempt_state ?? attempt.status ?? "unknown")}</span> <span class="text-muted-foreground">${escape_html(selected ? "Selected pass" : isSupersededAttempt(attempt) ? "Superseded" : attempt.error_code ?? "Retained history")}</span></div> <div class="mt-1.5 flex flex-wrap items-center gap-2">`);
        if (onOpenEvidence && attempt.capture_id) {
          $$renderer2.push("<!--[0-->");
          $$renderer2.push(`<button type="button" class="text-primary focus-visible:ring-ring inline-flex items-center gap-1 text-[10px] font-semibold hover:underline focus-visible:ring-2 focus-visible:outline-none">`);
          Eye($$renderer2, { class: "size-3", "aria-hidden": "true" });
          $$renderer2.push(`<!---->Inspect evidence</button>`);
        } else {
          $$renderer2.push("<!--[-1-->");
        }
        $$renderer2.push(`<!--]--> `);
        if (canUseAsReplacement) {
          $$renderer2.push("<!--[0-->");
          $$renderer2.push(`<button type="button" class="focus-visible:ring-ring inline-flex items-center gap-1 rounded-md border border-emerald-300 px-2 py-1 text-[10px] font-semibold text-emerald-800 hover:bg-emerald-50 focus-visible:ring-2 focus-visible:outline-none dark:border-emerald-800 dark:text-emerald-200 dark:hover:bg-emerald-950/40">`);
          Check($$renderer2, { class: "size-3", "aria-hidden": "true" });
          $$renderer2.push(`<!---->Use this pass</button>`);
        } else {
          $$renderer2.push("<!--[-1-->");
        }
        $$renderer2.push(`<!--]--></div> `);
        if (isReplacing) {
          $$renderer2.push("<!--[0-->");
          $$renderer2.push(`<div class="border-border/70 mt-2 space-y-2 border-t pt-2"><label class="text-foreground block font-semibold"${attr("for", `selection-reason-${method}-${attempt.capture_id}`)}>Replacement reason</label> <input${attr("id", `selection-reason-${method}-${attempt.capture_id}`)}${attr("value", selectionReason)} maxlength="500" placeholder="Why should this pass replace the current selection?" class="border-input bg-background text-foreground focus-visible:ring-ring w-full rounded-md border px-2 py-1 text-xs focus-visible:ring-2 focus-visible:outline-none"/> <div class="flex flex-wrap items-center gap-2"><button type="button" class="border-border hover:bg-muted focus-visible:ring-ring inline-flex items-center gap-1 rounded-md border px-2.5 py-1 text-[10px] font-semibold focus-visible:ring-2 focus-visible:outline-none">Cancel</button> <button type="button" class="focus-visible:ring-ring inline-flex items-center gap-1 rounded-md bg-emerald-700 px-2.5 py-1 text-[10px] font-semibold text-white hover:bg-emerald-600 focus-visible:ring-2 focus-visible:outline-none disabled:opacity-40"${attr("disabled", !selectionReason.trim() || busy, true)}>`);
          Check($$renderer2, { class: "size-3", "aria-hidden": "true" });
          $$renderer2.push(`<!---->Confirm replacement</button></div></div>`);
        } else {
          $$renderer2.push("<!--[-1-->");
        }
        $$renderer2.push(`<!--]--></div>`);
      }
      $$renderer2.push(`<!--]--></div></details>`);
    } else {
      $$renderer2.push("<!--[-1-->");
    }
    $$renderer2.push(`<!--]--></article>`);
  });
}
export {
  OmrPhysicalTestMethodCard as default
};
