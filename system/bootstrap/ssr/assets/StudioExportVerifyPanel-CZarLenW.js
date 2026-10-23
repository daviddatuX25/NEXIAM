import { o as onDestroy, l as bind_props, e as escape_html, b as attr, a as ensure_array_like, c as attr_class, f as attr_style, s as stringify, d as derived, n as newIdempotencyKey, m as csrfHeaders } from "../app.js";
import { e as SvelteURLSearchParams } from "./watch.svelte-BcPoqckX.js";
import StudioShadingGuideModal from "./StudioShadingGuideModal-BfmTaRFq.js";
import OmrPhysicalTestMethodCard from "./OmrPhysicalTestMethodCard-hibzJL3T.js";
import { a as OmrDiagnosticEvidencePanel, n as normalizeItem, c as computeSummary } from "./OmrDiagnosticEvidencePanel-CvO7kqrK.js";
import { X } from "./x-CnxrMbMn.js";
import { L as Loader_circle } from "./loader-circle-_UEl10Lx.js";
import { C as Chevron_up } from "./chevron-up-lhASJLRD.js";
import { C as Chevron_down } from "./chevron-down-CkQQZIhu.js";
import { C as Check } from "./check-DK45Y9SJ.js";
import { R as Rotate_ccw } from "./rotate-ccw-D6Dm_efx.js";
import { P as Printer } from "./printer-Bx0z1CRb.js";
import { S as Sparkles } from "./sparkles-DbQIaVG6.js";
import { L as Link } from "./Link-B7ks7rBK.js";
import { C as Chevron_left } from "./chevron-left-DCSOZwZl.js";
import "axios";
import "@inertiajs/core";
import "clsx";
import "laravel-precognition";
import "tailwind-merge";
import "tailwind-variants";
import "./dialog-description-D12L0QA9.js";
import "./noop-BQS7OFs8.js";
import "./create-id-CUJmDlea.js";
import "style-to-object";
import "./scroll-lock-B1ibcKMx.js";
import "./is-C6cm64pF.js";
import "tabbable";
import "./dialog-footer-kNnwKT2h.js";
import "./circle-x-BQrhluGu.js";
import "./Icon-Dal7PqKK.js";
import "./triangle-alert-CUvuB-HL.js";
import "./circle-check-C59ZS7Zv.js";
import "./qr-code-C5X8qcH6.js";
import "./scan-DTyWR4bt.js";
import "./shield-alert-CXtDdte9.js";
import "./arrow-right-CzynHiIo.js";
import "./chevron-right-BHu-PGOg.js";
import "./minus-Dqkhcxt7.js";
import "./plus-DjyPLC7A.js";
import "./file-image-BGlN21-8.js";
import "./arrow-left-DbWnQR3t.js";
import "./OmrCaptureInput-CmVb1QoH.js";
/* empty css                                                       */
import "./camera-C09Eyogi.js";
import "./upload-CwqN2Utd.js";
import "./refresh-cw-CYdk8aE0.js";
import "./info-C3MCzlQW.js";
import "./file-text-PXEtmXm9.js";
import "./book-open-B6eMguqY.js";
import "./eye-DyG-RiwW.js";
import "./zoom-out-DsJCISKt.js";
import "./maximize-2-u72Ahym6.js";
import "./eye-off-Bx_raPQb.js";
import "./save-DAlMQk-Q.js";
import "./scan-line-CiwhUtYK.js";
import "./layers-_Ie6y7G_.js";
import "./circle-alert-jWXJ7oC0.js";
function OmrDiagnosticEvidenceModal($$renderer, $$props) {
  $$renderer.component(($$renderer2) => {
    let {
      open = false,
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
      contextSummary = null,
      title = "Evidence",
      subtitle = "Read-only diagnostic evidence",
      onClose = null,
      onDirtyChange = null,
      onSaveDecision = null,
      onSaveAndNext = null,
      reviewable = false,
      blind = false,
      reviewReasons = [],
      onDirtyNavigation = null,
      captureReviewRequired = false,
      captureReviewBusy = false,
      onCaptureDecision = null
    } = $$props;
    let previousFocus = null;
    let previousOverflow = "";
    let dirty = false;
    let captureReviewNote = "";
    let reviewRailExpanded = false;
    function handleDirtyChange(value) {
      dirty = Boolean(value);
      onDirtyChange?.(dirty);
    }
    onDestroy(() => {
      if (typeof document !== "undefined") {
        document.body.style.overflow = previousOverflow;
        if (previousFocus instanceof HTMLElement && document.contains(previousFocus)) previousFocus.focus();
      }
    });
    let $$settled = true;
    let $$inner_renderer;
    function $$render_inner($$renderer3) {
      if (open) {
        $$renderer3.push("<!--[0-->");
        $$renderer3.push(`<div class="fixed inset-0 z-50 flex items-center justify-center bg-slate-950/65 p-0 backdrop-blur-sm sm:p-4 md:p-6" role="presentation"><div class="border-border bg-background text-foreground flex h-dvh max-h-dvh w-full min-w-0 flex-col overflow-hidden border shadow-2xl outline-none sm:h-auto sm:max-h-[min(94vh,1080px)] sm:w-[min(98vw,1600px)] sm:rounded-xl" role="dialog" aria-modal="true" aria-labelledby="omr-evidence-modal-title" aria-describedby="omr-evidence-modal-subtitle" tabindex="-1"><header class="border-border bg-card flex shrink-0 items-center justify-between gap-3 border-b px-3 py-2 sm:px-4"><div class="flex min-w-0 items-center gap-3"><div class="min-w-0"><h2 id="omr-evidence-modal-title" class="truncate text-sm font-bold">${escape_html(title)}</h2> <p id="omr-evidence-modal-subtitle" class="text-muted-foreground hidden truncate text-[11px] sm:block">${escape_html(subtitle)}</p></div></div> <button type="button" class="text-muted-foreground hover:bg-muted hover:text-foreground focus-visible:ring-ring inline-flex size-9 shrink-0 items-center justify-center rounded-md focus-visible:ring-2 focus-visible:outline-none"${attr("aria-label", labels.closeLabel ?? "Close evidence")}>`);
        X($$renderer3, { class: "size-4", "aria-hidden": "true" });
        $$renderer3.push(`<!----></button></header> <div class="min-h-0 flex-1 overflow-y-auto overscroll-contain p-2 sm:p-4">`);
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
          onSaveDecision,
          onSaveAndNext,
          onDirtyChange: handleDirtyChange,
          onDirtyNavigation,
          idPrefix: "omr-evidence-modal",
          get mobileRailExpanded() {
            return reviewRailExpanded;
          },
          set mobileRailExpanded($$value) {
            reviewRailExpanded = $$value;
            $$settled = false;
          }
        });
        $$renderer3.push(`<!----></div> `);
        if (captureReviewRequired && onCaptureDecision) {
          $$renderer3.push("<!--[0-->");
          $$renderer3.push(`<footer class="border-border bg-card shrink-0 border-t px-3 py-2 sm:px-4" aria-label="Complete test review"><div class="mx-auto flex max-w-4xl items-end gap-2"><label class="min-w-0 flex-1 text-[10px] font-semibold" for="omr-evidence-capture-review-note">Review note <span class="text-muted-foreground">(optional)</span> <input id="omr-evidence-capture-review-note"${attr("value", captureReviewNote)} maxlength="500" placeholder="Only what the next reviewer needs" class="border-input bg-background focus-visible:ring-ring mt-1 min-h-9 w-full rounded-md border px-2.5 text-xs font-normal focus-visible:ring-2 focus-visible:outline-none"/></label> <div class="flex shrink-0 flex-wrap gap-2"><button type="button" class="border-border bg-background hover:bg-muted focus-visible:ring-ring inline-flex min-h-9 flex-1 items-center justify-center rounded-md border px-3 text-xs font-semibold focus-visible:ring-2 focus-visible:outline-none disabled:opacity-40 sm:flex-none"${attr("disabled", captureReviewBusy, true)}>Request recapture</button> <button type="button" class="bg-primary text-primary-foreground hover:bg-primary/90 focus-visible:ring-ring inline-flex min-h-9 flex-1 items-center justify-center rounded-md px-3 text-xs font-semibold focus-visible:ring-2 focus-visible:outline-none disabled:opacity-40 sm:flex-none"${attr("disabled", captureReviewBusy, true)}>${escape_html(captureReviewBusy ? "Saving…" : "Done reviewing")}</button></div></div> `);
          {
            $$renderer3.push("<!--[-1-->");
          }
          $$renderer3.push(`<!--]--></footer>`);
        } else {
          $$renderer3.push("<!--[-1-->");
        }
        $$renderer3.push(`<!--]--></div></div>`);
      } else {
        $$renderer3.push("<!--[-1-->");
      }
      $$renderer3.push(`<!--]-->`);
    }
    do {
      $$settled = true;
      $$inner_renderer = $$renderer2.copy();
      $$render_inner($$inner_renderer);
    } while (!$$settled);
    $$renderer2.subsume($$inner_renderer);
    bind_props($$props, { open });
  });
}
const RUN_STATUS_QUEUED = "queued";
const RUN_STATUS_PREPARING_PROOF = "preparing_proof";
const RUN_STATUS_CLEAN_RECOVERY = "clean_recovery";
const RUN_STATUS_VARIANT_MATRIX = "variant_matrix";
const RUN_STATUS_AWAITING_PRINT = "awaiting_print";
const RUN_STATUS_AWAITING_CAPTURE = "awaiting_capture";
const RUN_STATUS_ANALYZING_CAPTURE = "analyzing_capture";
const RUN_STATUS_NEEDS_REVIEW = "needs_review";
const RUN_STATUS_PASSED = "passed";
const RUN_STATUS_FAILED = "failed";
const ACTIVE_RUN_STATUSES = [
  RUN_STATUS_QUEUED,
  RUN_STATUS_PREPARING_PROOF,
  RUN_STATUS_CLEAN_RECOVERY,
  RUN_STATUS_VARIANT_MATRIX,
  RUN_STATUS_AWAITING_PRINT,
  RUN_STATUS_AWAITING_CAPTURE,
  RUN_STATUS_ANALYZING_CAPTURE
];
const DIGITAL_PHASES = [
  RUN_STATUS_PREPARING_PROOF,
  RUN_STATUS_CLEAN_RECOVERY,
  RUN_STATUS_VARIANT_MATRIX
];
function isTerminalRunStatus(status) {
  return status === RUN_STATUS_PASSED || status === RUN_STATUS_FAILED;
}
function isActiveRunStatus(status) {
  return typeof status === "string" && ACTIVE_RUN_STATUSES.includes(status);
}
function challengeRecovery(run) {
  if (run?.challenge_recovery !== void 0) {
    return run.challenge_recovery;
  }
  if (isStaleChallenge(run?.challenge, run?.available_actions)) {
    return {
      kind: "repair_reference",
      action_url: run?.run_id ? `/setup/omr/templates/verification-runs/${run.run_id}/challenge/regenerate` : null,
      reason_code: "renderer_outdated",
      detail: "Update challenge sheet before printing or capturing."
    };
  }
  return null;
}
function isStaleChallenge(challenge, availableActions) {
  if (!challenge) return false;
  return challenge.reference_current === false && Boolean(
    availableActions && availableActions.includes("regenerate_challenge")
  );
}
function progressText(progress) {
  if (!progress || typeof progress.done !== "number" || typeof progress.total !== "number" || progress.total <= 0) {
    return "—";
  }
  return `${Math.max(0, Math.min(progress.done, progress.total))}/${progress.total}`;
}
function phaseText(run) {
  if (!run) return "—";
  const phase = String(run.phase_detail?.stage ?? run.phase ?? run.status);
  const variant = run.current_variant ?? null;
  return variant ? `${phase} · ${variant}` : phase;
}
function captureSourceForFile(file) {
  return typeof file?.name === "string" && file.name.startsWith("camera-snapshot-") ? "camera" : "upload";
}
function groupExpectedMarks(captures) {
  if (!Array.isArray(captures)) return [];
  const byPage = /* @__PURE__ */ new Map();
  for (const capture of captures) {
    byPage.set(capture.page_no, capture);
  }
  const groups = [];
  for (const page of [...byPage.keys()].sort((a, b) => a - b)) {
    const capture = byPage.get(page);
    const evidence = capture?.summary?.evidence;
    const items = Array.isArray(evidence?.items) ? evidence.items.map(normalizeItem) : [];
    const summary = computeSummary(items);
    groups.push({
      page_no: page,
      total: summary.total,
      expected: summary.expected,
      exact: summary.exact,
      ambiguous: summary.ambiguous,
      missing: summary.missing
    });
  }
  return groups;
}
const CHECKPOINT_DEFINITIONS = [
  {
    key: "digital",
    step: 1,
    label: "Digital engine check",
    purpose: "Runs the machine-filled proof through the real recognition engine and the no-go variant matrix to prove the mapped geometry recovers the expected answer vector exactly."
  },
  {
    key: "physical_challenge",
    step: 2,
    label: "Physical tests",
    purpose: "Tests each physical page using the required real paper, then captures or uploads the sheet for analysis."
  },
  {
    key: "evidence_review",
    step: 3,
    label: "Evidence review",
    purpose: "Inspects captured, aligned, and overlay evidence with exact/ambiguous/missing totals and problems-first item review before any pass is derived."
  },
  {
    key: "qualification",
    step: 4,
    label: "Qualification",
    purpose: "Derives physical qualification from a passed full compiled challenge plus staff printer/device/paper measurements — never from a manual checkbox."
  }
];
function registrationProfileReady(draft, markerProfileStatus) {
  return draft.registration_mode === "document_bound" ? draft.marker_profile_id == null : markerProfileStatus === "qualified";
}
function isCurrentTargetRun(run) {
  return Boolean(
    run && run.target_current !== false && run.target_stale !== true
  );
}
function digitalPassed(input) {
  const { run, draft } = input;
  if (run?.mode === "digital") {
    return run.status === "passed" || run.digital_verification_current === true;
  }
  return draft?.digital_verification_current === true || Boolean(draft?.digital_verified) || draft?.stage === "digital_verified" || draft?.stage === "compiled" || draft?.stage === "physical_qualified";
}
function mappingComplete(input) {
  const { draft } = input;
  if (draft?.stage === "mapping_complete" || draft?.stage === "digital_verified" || draft?.stage === "compiled" || draft?.stage === "physical_qualified" || Boolean(draft?.digital_verified) || Boolean(draft?.digital_verification_current)) {
    return true;
  }
  return draft?.mapping_current === true;
}
const REQUIRED_PHYSICAL_METHODS = ["pre_shaded", "hand_shaded"];
const PRINTED_METHOD_STATES = /* @__PURE__ */ new Set([
  "awaiting_capture",
  "analyzing",
  "needs_review",
  "passed",
  "failed"
]);
const PRINTED_CHALLENGE_STATES = /* @__PURE__ */ new Set([
  "printed",
  "awaiting_capture",
  "analyzing_capture",
  "needs_review",
  "passed",
  "failed"
]);
function currentPhysicalTestPackReady(run) {
  if (!isCurrentTargetRun(run) || run?.mode === "digital" || !Array.isArray(run?.methods) || !run?.target)
    return false;
  const targetKind = run.target.kind;
  const targetId = Number(run.target.id);
  const targetHash = String(run.target.hashes?.artifact ?? "");
  if (!targetKind || !Number.isInteger(targetId) || targetId < 1 || targetHash === "")
    return false;
  const byMethod = /* @__PURE__ */ new Map();
  for (const method of run.methods) {
    if (!method || !REQUIRED_PHYSICAL_METHODS.includes(method.method) || byMethod.has(method.method))
      return false;
    if (method.target_kind !== targetKind || Number(method.target_id) !== targetId || String(method.target_hash ?? "") !== targetHash)
      return false;
    if (!method.challenge_id || !PRINTED_METHOD_STATES.has(String(method.state ?? "")))
      return false;
    const challengeState = String(method.challenge?.status ?? "");
    if (challengeState !== "" && !PRINTED_CHALLENGE_STATES.has(challengeState))
      return false;
    byMethod.set(method.method, method);
  }
  return REQUIRED_PHYSICAL_METHODS.every((method) => byMethod.has(method));
}
function currentPhysicalCaptureEvidence(run) {
  if (!currentPhysicalTestPackReady(run)) return false;
  return (run?.methods ?? []).some(
    (method) => (method.attempts ?? []).some(
      (attempt) => Number(attempt.capture_id) > 0
    )
  );
}
function currentPhysicalAnalysisComplete(run) {
  if (!currentPhysicalTestPackReady(run)) return false;
  return (run?.methods ?? []).every((method) => {
    if (!["passed", "needs_review"].includes(String(method.state ?? "")))
      return false;
    const pages = (method.pages ?? []).filter(
      (page) => page.required !== false
    );
    return pages.length > 0 && pages.every(
      (page) => (method.attempts ?? []).some(
        (attempt) => Number(attempt.capture_id) > 0 && Number(attempt.page_no) === Number(page.page_no)
      )
    );
  });
}
function currentPhysicalSelectionComplete(run) {
  if (!currentPhysicalTestPackReady(run)) return false;
  return (run?.methods ?? []).every((method) => {
    const pages = (method.pages ?? []).filter(
      (page) => page.required !== false
    );
    return pages.length > 0 && pages.every(
      (page) => Number(
        page.selected_attempt_id ?? page.selected_attempt?.capture_id ?? 0
      ) > 0
    );
  });
}
function currentPhysicalMethodsPassed(run) {
  return currentPhysicalSelectionComplete(run) && (run?.methods ?? []).every((method) => method.state === "passed");
}
function physicalRunPassed(run) {
  return run !== null && isCurrentTargetRun(run) && run.mode !== "digital" && run.status === RUN_STATUS_PASSED;
}
function physicalChallengeResumeStage(challenge) {
  return challenge?.status === "created" ? "print" : "capture";
}
function hasResumablePhysicalChallenge(run) {
  return run !== null && isCurrentTargetRun(run) && run.mode !== "digital" && run.status === RUN_STATUS_FAILED && Boolean(run.challenge) && run.challenge?.status !== "superseded" && run.safe_issue?.code !== "target_changed_superseded";
}
function isRehearsalRun(run) {
  return Boolean(
    run && (run.mode === "quick_rehearsal" || run.target?.kind === "candidate")
  );
}
function safeIssueText(run) {
  if (!run?.safe_issue) return "";
  return run.safe_issue.detail || run.safe_issue.code || "";
}
function evidenceSummaryFor(run) {
  if (!run || !Array.isArray(run.evidence) || run.evidence.length === 0)
    return null;
  const groups = groupExpectedMarks(run.evidence);
  if (groups.length === 0) return null;
  const totals = groups.reduce(
    (acc, g) => ({
      total: acc.total + g.total,
      exact: acc.exact + g.exact,
      ambiguous: acc.ambiguous + g.ambiguous,
      missing: acc.missing + g.missing
    }),
    { total: 0, exact: 0, ambiguous: 0, missing: 0 }
  );
  const pages = groups.length === 1 ? `page ${groups[0].page_no}` : `${groups.length} pages`;
  return `${totals.total} expected marks on ${pages} — ${totals.exact} exact, ${totals.ambiguous} ambiguous, ${totals.missing} missing`;
}
function deriveCheckpointStates(input) {
  const run = isCurrentTargetRun(input.run) ? input.run : null;
  const draft = input.draft ?? {};
  const urls = input.urls ?? {};
  const physicalRunId = input.physicalRunId ?? null;
  const digital = digitalPassed(input);
  const physicalPackReady = currentPhysicalTestPackReady(run);
  const physical = physicalRunPassed(run) && physicalPackReady;
  const physicalCaptureEvidence = currentPhysicalCaptureEvidence(run);
  const physicalSelectionComplete = currentPhysicalSelectionComplete(run);
  currentPhysicalAnalysisComplete(run);
  const physicalQualificationReady = physicalRunPassed(run) && currentPhysicalMethodsPassed(run);
  const rehearsal = isRehearsalRun(run) && !physicalRunId;
  const challenge = run?.challenge ?? null;
  const compiled = Boolean(draft.compiled_version_id);
  const profileQualified = registrationProfileReady(
    draft,
    input.markerProfileStatus
  );
  const states = [];
  {
    const base = CHECKPOINT_DEFINITIONS[0];
    let state = {
      ...base,
      status: "pending",
      statusText: "Not started",
      prerequisites: ["Recognition Mapping saved and validated"],
      nextAction: null,
      actionAvailable: false,
      retryAvailable: false,
      evidenceSummary: null,
      rehearsalOnly: false,
      activationAvailable: false,
      activationReason: null
    };
    if (run?.status === RUN_STATUS_FAILED && run.mode === "digital") {
      state.status = "active";
      state.statusText = `Failed — ${safeIssueText(run) || run.status}`;
      state.nextAction = "Retry digital verification";
      state.actionAvailable = true;
      state.retryAvailable = true;
    } else if (!mappingComplete(input)) {
      state.status = "blocked";
      state.statusText = "Blocked";
      state.prerequisites = [
        "Complete Recognition Mapping (stage 3) before the engine check can run"
      ];
    } else if (digital) {
      state.status = "done";
      state.statusText = `Passed — attempt ${run?.attempt ?? 1}`;
      state.nextAction = null;
      state.evidenceSummary = run?.result_summary?.matrix_hash ? `Matrix ${String(run.result_summary.matrix_hash).slice(0, 10)}…` : "Proof and no-go matrix exact-recovery verified";
    } else if (run && run.mode === "digital" && run.status === RUN_STATUS_QUEUED) {
      state.status = "pending";
      state.statusText = "Queued — waiting for the verification worker";
      state.nextAction = "Waiting for the verification worker to claim the run";
      state.actionAvailable = false;
      state.evidenceSummary = null;
    } else if (run && isActiveRunStatus(run.status) && run.mode === "digital") {
      state.status = "active";
      state.statusText = phaseText(run);
      state.nextAction = null;
      state.evidenceSummary = progressText(run.progress) !== "—" ? `${progressText(run.progress)} variants completed` : null;
    } else {
      state.status = "pending";
      state.statusText = "Ready";
      state.nextAction = "Run digital verification";
      state.actionAvailable = Boolean(urls.digital_verification);
    }
    states.push(state);
  }
  {
    const base = CHECKPOINT_DEFINITIONS[1];
    let state = {
      ...base,
      status: "blocked",
      statusText: "Blocked",
      prerequisites: ["Digital engine check passed"],
      nextAction: null,
      actionAvailable: false,
      retryAvailable: false,
      evidenceSummary: null,
      rehearsalOnly: rehearsal || !compiled || !profileQualified,
      activationAvailable: false,
      activationReason: null
    };
    const recovery = challengeRecovery(run);
    const capturedPages = run && Array.isArray(run.evidence) ? new Set(run.evidence.map((capture) => capture.page_no)).size : 0;
    if (recovery?.kind === "repair_reference") {
      state.status = "active";
      state.statusText = `Challenge ${challenge?.challenge_code ?? "—"} reference outdated — update sheet required`;
      state.nextAction = "Update challenge sheet";
      state.actionAvailable = Boolean(recovery.action_url);
      state.evidenceSummary = "Challenge sheet update available";
    } else if (recovery?.kind === "restart_rehearsal") {
      state.status = "active";
      state.statusText = `Layout changed for challenge ${challenge?.challenge_code ?? "—"} — start new rehearsal`;
      state.nextAction = "Start new rehearsal";
      state.actionAvailable = Boolean(recovery.action_url);
      state.evidenceSummary = "Layout changed";
    } else if (physical || draft.physical_qualified) {
      state.status = "done";
      state.statusText = challenge ? `Physical tests ${challenge.challenge_code} completed` : "Physical tests completed";
      state.evidenceSummary = evidenceSummaryFor(run);
    } else if (run?.status === RUN_STATUS_NEEDS_REVIEW) {
      const methodSummary = (run.methods ?? []).map(
        (method) => `${method.method === "hand_shaded" ? "Hand-shaded" : "Pre-shaded"} ${method.state === "passed" ? "passed" : "needs review"}`
      ).join(" · ");
      state.status = "done";
      state.statusText = "All physical captures analyzed — evidence review ready";
      state.evidenceSummary = [methodSummary, evidenceSummaryFor(run)].filter(Boolean).join(" · ");
    } else if (hasResumablePhysicalChallenge(run)) {
      const resumeStage = physicalChallengeResumeStage(challenge);
      state.status = "active";
      const interruption = safeIssueText(run);
      state.statusText = resumeStage === "print" ? `Physical tests ${challenge?.challenge_code ?? "—"} interrupted${interruption ? ` — ${interruption}` : ""}` : `Physical capture ${challenge?.challenge_code ?? "—"} interrupted${interruption ? ` — ${interruption}` : " — resume upload"}`;
      state.nextAction = resumeStage === "print" ? "Resume the existing sheets at the print step" : "Resume capture of the printed physical test";
      state.actionAvailable = true;
      state.retryAvailable = true;
      state.evidenceSummary = evidenceSummaryFor(run) ?? (challenge?.print_url || challenge?.guide_url ? "Existing physical test assets preserved" : null);
    } else if (run && run.mode !== "digital" && run.status === RUN_STATUS_AWAITING_PRINT) {
      state.status = "active";
      state.statusText = `Physical tests ${challenge?.challenge_code ?? "—"} created — print the first ready method`;
      state.nextAction = "Complete pre-shaded first, then hand-shaded: print and capture each method in its card";
      state.actionAvailable = true;
      state.evidenceSummary = challenge?.print_url ? "Printable physical test sheet ready" : null;
    } else if (run && run.mode !== "digital" && run.status === RUN_STATUS_AWAITING_CAPTURE) {
      state.status = "active";
      state.statusText = `${capturedPages} page${capturedPages === 1 ? "" : "s"} captured so far`;
      state.nextAction = physicalPackReady ? `Capture page ${nextPageToCapture(run)} of the next unfinished method` : "Complete pre-shaded first, then hand-shaded";
      state.actionAvailable = true;
      state.evidenceSummary = evidenceSummaryFor(run);
    } else if (run && run.mode !== "digital" && run.status === RUN_STATUS_ANALYZING_CAPTURE) {
      state.status = "active";
      state.statusText = "Analyzing the submitted physical test…";
      state.evidenceSummary = evidenceSummaryFor(run);
    } else if (run && run.mode !== "digital" && run.status === RUN_STATUS_PASSED && physicalCaptureEvidence) {
      state.status = "done";
      state.statusText = "Physical tests completed";
      state.evidenceSummary = evidenceSummaryFor(run);
    } else if (!digital) {
      state.status = "blocked";
      state.statusText = "Blocked";
      state.prerequisites = ["Digital engine check passed first"];
    } else if (run && run.mode === "digital" && !isTerminalRunStatus(run.status)) {
      state.status = "blocked";
      state.statusText = "Blocked";
      state.prerequisites = [
        "Wait for the active verification run to settle before creating physical tests"
      ];
    } else {
      state.status = "pending";
      state.statusText = "Ready";
      state.nextAction = "Create the physical test sheets";
      state.actionAvailable = true;
      state.rehearsalOnly = rehearsal || !compiled || !profileQualified;
    }
    states.push(state);
  }
  {
    const base = CHECKPOINT_DEFINITIONS[2];
    let state = {
      ...base,
      status: "blocked",
      statusText: "Blocked",
      prerequisites: ["At least one captured page analyzed"],
      nextAction: null,
      actionAvailable: false,
      retryAvailable: false,
      evidenceSummary: null,
      rehearsalOnly: rehearsal,
      activationAvailable: false,
      activationReason: null
    };
    if (physical && physicalSelectionComplete) {
      state.status = "done";
      state.statusText = "Reviewed — exact expected-vector recovery confirmed";
      state.evidenceSummary = evidenceSummaryFor(run);
    } else if (run?.status === RUN_STATUS_NEEDS_REVIEW) {
      state.status = "active";
      state.statusText = "Capture needs review — ambiguous or missing marks detected";
      state.nextAction = "Review the evidence: confirm exact recovery or reject";
      state.actionAvailable = true;
      state.evidenceSummary = evidenceSummaryFor(run);
    } else if (run && run.mode !== "digital" && physicalCaptureEvidence) {
      state.status = "pending";
      state.statusText = "Waiting for current page selections";
      state.evidenceSummary = evidenceSummaryFor(run);
    } else {
      state.status = "blocked";
      state.statusText = "Blocked";
      state.prerequisites = ["Capture a printed challenge page first"];
    }
    states.push(state);
  }
  {
    const base = CHECKPOINT_DEFINITIONS[3];
    const reasons = [];
    let state = {
      ...base,
      status: "blocked",
      statusText: "Blocked",
      prerequisites: reasons,
      nextAction: null,
      actionAvailable: false,
      retryAvailable: false,
      evidenceSummary: null,
      rehearsalOnly: rehearsal,
      activationAvailable: false,
      activationReason: null
    };
    if (draft.physical_qualified) {
      state.status = "done";
      state.statusText = "Physical qualification derived from genuine full-challenge evidence";
      state.rehearsalOnly = false;
      state.activationAvailable = Boolean(draft.compiled_version_id);
      state.activationReason = null;
    } else if (physicalRunId && physicalQualificationReady) {
      state.status = "active";
      state.statusText = `Full challenge run #${physicalRunId} passed — evidence ready`;
      state.nextAction = "Record printer/device/paper measurements to derive qualification";
      state.actionAvailable = true;
      state.evidenceSummary = evidenceSummaryFor(run);
      state.prerequisites = ["Passed full compiled challenge"];
      if (draft.registration_mode !== "document_bound") {
        state.prerequisites.push("Qualified marker profile");
        if (!profileQualified) {
          state.actionAvailable = false;
          state.nextAction = null;
          state.prerequisites.push(
            "A qualified marker profile is required (profiles are qualified via the calibration artifact)"
          );
        }
      }
    } else if (rehearsal || run && run.mode !== "digital" && run.target?.kind === "candidate") {
      state.status = "blocked";
      state.statusText = "Rehearsal only";
      state.rehearsalOnly = true;
      state.prerequisites = [
        "Candidate/quick rehearsals never qualify — run a full challenge from the compiled immutable version"
      ];
    } else if (run?.mode === "full_qualification" && run.status === RUN_STATUS_FAILED) {
      state.status = "blocked";
      state.statusText = "Blocked";
      state.prerequisites = [
        "The full challenge did not pass — capture must recover the exact expected vector with all gates green"
      ];
    } else if (!compiled) {
      state.status = "blocked";
      state.statusText = "Blocked";
      state.prerequisites = [
        "Compile the digitally verified candidate, then run a full challenge with genuine capture evidence"
      ];
    } else {
      state.status = "blocked";
      state.statusText = "Blocked";
      state.prerequisites = [
        "A passed full compiled challenge with genuine evidence is required — activation stays unavailable until then"
      ];
    }
    if (state.status !== "done") {
      state.activationAvailable = false;
      state.activationReason = "Activation remains unavailable until physical qualification is derived from genuine evidence.";
    }
    states.push(state);
  }
  return states;
}
function deriveCurrentCheckpoint(states) {
  if (!Array.isArray(states) || states.length === 0) {
    return {
      ...CHECKPOINT_DEFINITIONS[0],
      status: "pending",
      statusText: "Ready",
      prerequisites: [],
      nextAction: null,
      actionAvailable: false,
      retryAvailable: false,
      evidenceSummary: null,
      rehearsalOnly: false,
      activationAvailable: false,
      activationReason: null
    };
  }
  const active = states.find((state) => state.status === "active");
  if (active) return active;
  const pending = states.find((state) => state.status === "pending");
  if (pending) return pending;
  const incomplete = states.find((state) => state.status !== "done");
  if (incomplete) return incomplete;
  return states[states.length - 1] ?? states[0];
}
function nextPageToCapture(run) {
  if (!run || !Array.isArray(run.evidence) || run.evidence.length === 0)
    return 1;
  const maxPage = Math.max(
    ...run.evidence.map((c) => Number(c.page_no) || 0)
  );
  return Math.min(maxPage + 1, 20);
}
function boundedInterval(intervalMs, maxIntervalMs) {
  const max = maxIntervalMs && maxIntervalMs > 0 ? maxIntervalMs : 8e3;
  const preferred = intervalMs && intervalMs > 0 ? intervalMs : 1500;
  return Math.max(500, Math.min(preferred, max));
}
function createVerificationPoller(options) {
  const intervalMs = boundedInterval(
    options.intervalMs,
    options.maxIntervalMs
  );
  const fetchImpl = options.fetchImpl ?? ((url, init) => fetch(url, init));
  let timer = null;
  let stopped = true;
  let inFlight = false;
  let requestGeneration = 0;
  async function tick() {
    if (stopped || inFlight || !options.statusUrl) return;
    inFlight = true;
    const generation = requestGeneration;
    try {
      const response = await fetchImpl(options.statusUrl, {
        headers: { Accept: "application/json" },
        credentials: "same-origin"
      });
      if (!response || typeof response.json !== "function") return;
      const payload = await response.json().catch(() => null);
      if (stopped || generation !== requestGeneration) return;
      if (!response.ok || !payload || typeof payload.status !== "string") {
        throw new Error("Verification status is unavailable.");
      }
      if (options.expectedRunId != null && Number(payload.run_id) !== Number(options.expectedRunId))
        return;
      if (options.expectedMode != null && payload.mode !== options.expectedMode)
        return;
      options.onStatus(payload);
      if (isTerminalRunStatus(payload.status)) {
        stop();
      }
    } catch (error) {
      if (!stopped && generation === requestGeneration) {
        options.onError?.(
          error instanceof Error ? error.message : "Verification status is unavailable."
        );
      }
    } finally {
      inFlight = false;
    }
  }
  function start() {
    if (!stopped || !options.statusUrl) return;
    stopped = false;
    void tick();
    timer = setInterval(() => void tick(), intervalMs);
  }
  function stop() {
    stopped = true;
    requestGeneration += 1;
    if (timer !== null) {
      clearInterval(timer);
      timer = null;
    }
  }
  return {
    start,
    stop,
    refresh: async () => {
      if (stopped) return;
      await tick();
    },
    isRunning: () => !stopped
  };
}
function StudioExportVerifyPanel($$renderer, $$props) {
  $$renderer.component(($$renderer2) => {
    let {
      draft = {},
      urls = {},
      getCsrfToken = () => "",
      lifecycleBusy = false,
      onBack = null,
      verification_run = null,
      physical_run_id = null,
      marker_profile_status = null,
      onDraftLockVersion = () => {
      }
    } = $$props;
    const STATUS_LABELS = {
      done: "Done",
      active: "In progress",
      blocked: "Blocked",
      pending: "Ready"
    };
    const isProfileQualified = derived(() => registrationProfileReady(draft, marker_profile_status));
    const isUploadedSource = derived(() => ["pdf", "image", "docx", "uploaded_original"].includes(draft?.source_kind));
    const baseRun = derived(() => verification_run ?? draft?.verification_run ?? null);
    let runOverride = null;
    const run = derived(() => runOverride ?? baseRun());
    const recovery = derived(() => challengeRecovery(run()));
    let poller = null;
    let isHydratingOverride = null;
    const isHydrating = derived(() => isHydratingOverride ?? Boolean(baseRun()?.status_url));
    let lastHydratedUrl = null;
    let requestGeneration = 0;
    let challengeMode = "quick";
    let capturePageNo = 1;
    let selectedCaptureId = null;
    let notice = "";
    let failure = null;
    let activateBusy = false;
    let busyFlags = {
      digital: false,
      compile: false,
      challenge: false,
      print: false,
      capture: false,
      review: false
    };
    let guideModalOpen = false;
    let guideMethod = null;
    const states = derived(() => deriveCheckpointStates({
      run: run(),
      draft,
      urls,
      physicalRunId: physical_run_id,
      markerProfileStatus: marker_profile_status
    }).filter((state) => state.key !== "qualification"));
    const isComplete = derived(() => states().length > 0 && states().every((state) => state.status === "done"));
    let expandedStepKeyOverride = null;
    const activeStep = derived(() => deriveCurrentCheckpoint(states()));
    const defaultExpandedKey = derived(() => activeStep() && (activeStep().status === "active" || activeStep().status === "pending" || states().every((s) => s.status === "done") && activeStep().status === "done") ? activeStep().key : null);
    const expandedStepKey = derived(() => {
      if (expandedStepKeyOverride === "none") return null;
      const candidate = expandedStepKeyOverride ?? defaultExpandedKey();
      if (!candidate) return null;
      const target = states().find((s) => s.key === candidate);
      if (!target || target.status === "blocked") return null;
      return candidate;
    });
    const isDigitalQueued = derived(() => Boolean(run()?.mode === "digital" && run()?.status === "queued"));
    const isDigitalRunning = derived(() => Boolean(run()?.mode === "digital" && DIGITAL_PHASES.includes(run()?.status)));
    function checkpointStatusLabel(state) {
      if (state.key === "digital" && state.status === "pending" && isDigitalQueued()) {
        return "Waiting";
      }
      return STATUS_LABELS[state.status] ?? state.status;
    }
    function mergeRunPayload(previous, payload, fallbackStatus = null) {
      const runChanged = Boolean(previous && payload?.run_id != null && previous.run_id != null && Number(payload.run_id) !== Number(previous.run_id));
      const replacingRun = previous == null || runChanged;
      const next = replacingRun ? { ...payload ?? {} } : { ...previous ?? {}, ...payload ?? {} };
      if (payload?.run_id == null && previous?.run_id != null) next.run_id = previous.run_id;
      if (payload?.status == null && fallbackStatus) next.status = fallbackStatus;
      if (payload?.attempt == null && previous?.attempt != null && !replacingRun) next.attempt = previous.attempt;
      if (payload?.status_url == null && previous?.status_url && !replacingRun) next.status_url = previous.status_url;
      if (payload?.challenge == null && previous?.challenge && !replacingRun) next.challenge = previous.challenge;
      else if (payload?.challenge != null && previous?.challenge != null && !replacingRun) {
        next.challenge = { ...previous.challenge, ...payload.challenge };
      }
      if (payload?.evidence == null && previous?.evidence && !replacingRun) next.evidence = previous.evidence;
      if (replacingRun) {
        if (payload?.challenge === void 0) next.challenge = null;
        if (payload?.evidence === void 0) next.evidence = [];
        if (payload?.challenge_recovery === void 0) next.challenge_recovery = null;
        next.target_current = payload?.target_current !== false;
        next.target_stale = payload?.target_stale === true;
        next.digital_verification_current = payload?.digital_verification_current === true;
      } else if (payload?.challenge_recovery !== void 0) {
        next.challenge_recovery = payload.challenge_recovery;
      } else if (previous?.challenge_recovery !== void 0) {
        next.challenge_recovery = previous.challenge_recovery;
      }
      return next;
    }
    function selectCaptureMethod(method) {
      if (!METHOD_KEYS.includes(method)) return;
      activeCaptureMethod = method;
      activeMethod = method;
      expandedStepKeyOverride = "physical_challenge";
    }
    const digitalPassedCheck = derived(() => states()[0]?.status === "done");
    const METHOD_KEYS = ["pre_shaded", "hand_shaded"];
    let activeMethod = "pre_shaded";
    let activeCaptureMethod = "pre_shaded";
    let evidenceModalOpen = false;
    let evidencePage = null;
    let evidenceLoading = false;
    let evidenceError = null;
    let evidenceArea = "all";
    let evidenceFilter = null;
    let evidencePageNo = 1;
    let evidencePerPage = 25;
    let evidenceTab = "aligned";
    let evidenceRequestGeneration = 0;
    const evidenceReviewReasons = [
      {
        code: "faint_or_partial_mark",
        label: "Faint shading missed by machine"
      },
      {
        code: "multiple_marks",
        label: "Multiple marks — one intended"
      },
      {
        code: "incorrect_detection",
        label: "Machine selected wrong option"
      },
      {
        code: "intentional_blank",
        label: "Confirmed intentionally blank"
      },
      { code: "insufficient_evidence", label: "Unable to determine" }
    ];
    function methodPageCount(methodData) {
      return Math.max(1, Number(methodData?.required_page_count) || Number(methodData?.pages?.length) || Number(methodData?.challenge?.sheet_pages?.length) || Number(draft?.authoring?.canonical?.settings?.page_count) || Number(draft?.authoring?.pages?.length) || 1);
    }
    const methodCards = derived(() => METHOD_KEYS.map((method) => {
      const persisted = Array.isArray(run()?.methods) ? run().methods.find((candidate) => candidate.method === method) : null;
      if (persisted) return persisted;
      const legacyChallenge = method === "pre_shaded" && run()?.challenge ? run().challenge : null;
      const legacyState = legacyChallenge ? legacyChallenge.status === "printed" || run()?.status === "awaiting_capture" ? "awaiting_capture" : run()?.status === "failed" ? "failed" : "challenge_ready" : "not_started";
      return {
        method,
        label: method === "hand_shaded" ? "Hand-shaded test" : "Pre-shaded test",
        state: legacyState,
        challenge_id: legacyChallenge?.challenge_id ?? null,
        challenge: legacyChallenge,
        print_url: legacyChallenge?.print_url ?? null,
        required_page_count: Number(legacyChallenge?.sheet_pages?.length) || 1,
        pages: [],
        attempts: []
      };
    }));
    const activeReviewMethodData = derived(() => methodCards().find((methodData) => methodData.method === activeMethod) ?? null);
    const activeEvidenceAttempt = derived(() => (activeReviewMethodData()?.attempts ?? []).find((attempt) => Number(attempt.capture_id) === Number(evidencePage?.capture_id ?? selectedCaptureId)) ?? null);
    const evidenceContextSummary = derived(() => ({
      simulated: true,
      answer_sheet_number: `SIM-${String(evidencePage?.capture_id ?? selectedCaptureId ?? "PREVIEW").padStart(4, "0")}`,
      applicant_name: "Maria Santos",
      exam_session: "Entrance Examination — Morning",
      schedule: "September 18, 2026 · 9:00 AM",
      room_seat: "Room 204 · B-12",
      score: { display: "86 / 100" }
    }));
    const activePhysicalMethod = derived(() => {
      const selected = methodCards().find((candidate) => candidate.method === activeCaptureMethod);
      if (selected && selected.state !== "passed") return selected.method;
      return methodCards().find((candidate) => candidate.state !== "passed")?.method ?? selected?.method ?? METHOD_KEYS[0];
    });
    const guideMethodData = derived(() => methodCards().find((candidate) => candidate.method === (guideMethod ?? activeMethod)) ?? null);
    const guideChallenge = derived(() => guideMethodData()?.challenge ?? (guideMethodData()?.challenge_id ? guideMethodData() : null) ?? run()?.challenge ?? null);
    const guideChallengeId = derived(() => guideChallenge()?.challenge_id ?? guideMethodData()?.challenge_id ?? null);
    const methodRunId = derived(() => run()?.run_id ?? null);
    const qualification = derived(() => run()?.physical_assurance ?? run()?.qualification ?? null);
    const legacyEvidence = derived(() => Array.isArray(run()?.evidence) ? run().evidence.find((entry) => entry?.summary?.evidence?.items?.length || entry?.capture_id) : null);
    function challengeModeForMethod() {
      if (run()?.mode === "full_qualification") {
        return "full";
      }
      if (run()?.mode === "quick_rehearsal") {
        return "quick";
      }
      return draft?.compiled_version_id && isProfileQualified() ? "full" : "quick";
    }
    function methodTargetPayload(methodData) {
      const challenge = methodData?.challenge ?? methodData ?? {};
      return {
        challenge_id: challenge.challenge_id ?? methodData?.challenge_id ?? null,
        target_hash: methodData?.target_hash ?? run()?.target?.hashes?.artifact ?? null,
        plan_hash: methodData?.plan_hash ?? challenge.plan_hash ?? run()?.plan?.plan_hash ?? null,
        expected_vector_hash: methodData?.expected_vector_hash ?? challenge.expected_vector_hash ?? run()?.plan?.expected_vector_hash ?? null
      };
    }
    function persistEvidenceUrlState() {
      if (typeof window === "undefined") return;
      const url = new URL(window.location.href);
      const values = {
        evidence_method: activeMethod,
        evidence_capture: evidencePage?.capture_id ?? null,
        evidence_area: evidenceArea !== "all" ? evidenceArea : null,
        evidence_filter: evidenceFilter !== "all" ? evidenceFilter : null,
        evidence_page: evidencePageNo > 1 ? evidencePageNo : null,
        evidence_per_page: evidencePerPage !== 25 ? evidencePerPage : null,
        evidence_tab: evidenceTab !== "aligned" ? evidenceTab : null
      };
      Object.entries(values).forEach(([key, value]) => {
        if (value == null || value === "") url.searchParams.delete(key);
        else url.searchParams.set(key, String(value));
      });
      window.history.replaceState(window.history.state, "", `${url.pathname}${url.search}${url.hash}`);
    }
    async function fetchEvidencePage(context = {}) {
      const method = context.method ?? activeMethod;
      const captureId = context.captureId ?? evidencePage?.capture_id ?? null;
      if (!methodRunId() || !captureId) {
        evidencePage = null;
        evidenceError = null;
        return;
      }
      activeMethod = method;
      if (context.area !== void 0) evidenceArea = context.area;
      if (context.filter !== void 0) evidenceFilter = context.filter;
      if (context.page !== void 0) evidencePageNo = Math.max(1, Number(context.page) || 1);
      if (context.perPage !== void 0) evidencePerPage = Math.min(50, Math.max(1, Number(context.perPage) || 25));
      const generation = ++evidenceRequestGeneration;
      evidenceLoading = true;
      evidenceError = null;
      const params = new SvelteURLSearchParams({
        method,
        capture_id: String(captureId),
        area: evidenceArea || "all",
        page: String(evidencePageNo),
        per_page: String(evidencePerPage)
      });
      if (evidenceFilter) params.set("filter", evidenceFilter);
      persistEvidenceUrlState();
      try {
        const response = await fetch(`/setup/omr/templates/verification-runs/${methodRunId()}/evidence?${params.toString()}`, {
          headers: { Accept: "application/json" },
          credentials: "same-origin"
        });
        const payload = await response.json().catch(() => null);
        if (generation !== evidenceRequestGeneration) return;
        if (!response.ok || !payload || typeof payload !== "object") {
          throw new Error(payload?.detail ?? "The private evidence page is unavailable.");
        }
        evidencePage = payload;
        evidenceArea = payload.area ?? evidenceArea;
        evidenceFilter = payload.filter ?? evidenceFilter;
        evidencePageNo = Number(payload.page ?? evidencePageNo);
        evidencePerPage = Number(payload.per_page ?? evidencePerPage);
        persistEvidenceUrlState();
        if (evidenceFilter === "mismatches" && Number(payload.mismatch_total) === 0 && Array.isArray(payload.items) && payload.items.length === 0) {
          await fetchEvidencePage({ filter: "all", page: 1 });
        }
      } catch (error) {
        if (generation === evidenceRequestGeneration) evidenceError = {
          detail: error?.message ?? "The private evidence page is unavailable."
        };
      } finally {
        if (generation === evidenceRequestGeneration) evidenceLoading = false;
      }
    }
    async function saveEvidenceDecision(payload, item) {
      const captureId = Number(evidencePage?.capture_id ?? 0);
      const itemId = Number(item?.id ?? item?.item_id ?? 0);
      if (!methodRunId() || captureId < 1 || itemId < 1) {
        throw new Error("The verification item review context is unavailable.");
      }
      const response = await postJson(`/setup/omr/templates/verification-runs/${methodRunId()}/captures/${captureId}/items/${itemId}/review`, payload);
      await fetchEvidencePage({
        method: activeMethod,
        captureId,
        area: evidenceArea,
        filter: evidenceFilter,
        page: evidencePageNo,
        perPage: evidencePerPage
      });
      return response;
    }
    function openMethodEvidence(context) {
      activeMethod = context.method;
      evidenceArea = "all";
      evidenceFilter = null;
      evidencePageNo = 1;
      evidencePage = null;
      evidenceModalOpen = true;
      persistEvidenceUrlState();
      fetchEvidencePage(context);
    }
    function closeEvidenceModal() {
      evidenceModalOpen = false;
      evidencePage = null;
      evidenceError = null;
      if (typeof window !== "undefined") {
        const url = new URL(window.location.href);
        [
          "evidence_method",
          "evidence_capture",
          "evidence_area",
          "evidence_filter",
          "evidence_page",
          "evidence_per_page",
          "evidence_tab"
        ].forEach((key) => url.searchParams.delete(key));
        window.history.replaceState(window.history.state, "", `${url.pathname}${url.search}${url.hash}`);
      }
    }
    function evidenceGroupChange(area) {
      fetchEvidencePage({ area, page: 1 });
    }
    function evidenceFilterChange(filter) {
      fetchEvidencePage({ filter, page: 1 });
    }
    function evidencePageChange(page) {
      fetchEvidencePage({ page });
    }
    function evidenceViewChange(view) {
      fetchEvidencePage(view);
    }
    function evidenceRetry() {
      fetchEvidencePage({});
    }
    function evidenceTabChange(tab) {
      evidenceTab = tab;
      persistEvidenceUrlState();
    }
    function attachPoller(statusUrl, expectedRunId = null, expectedMode = null) {
      const generation = ++requestGeneration;
      const runId = expectedRunId ?? runOverride?.run_id ?? run()?.run_id ?? null;
      if (poller) poller.stop();
      lastHydratedUrl = statusUrl;
      isHydratingOverride = false;
      poller = createVerificationPoller({
        statusUrl,
        expectedRunId: runId,
        expectedMode,
        onStatus: (status) => {
          if (!status || generation !== requestGeneration || !poller?.isRunning()) return;
          if (runId !== null && Number(status.run_id) !== Number(runId)) return;
          if (expectedMode !== null && status.mode !== expectedMode) return;
          runOverride = mergeRunPayload(runOverride ?? run(), status);
          if (failure?.code === "status_unavailable") failure = null;
          const targetCurrent = status.target_current !== false && status.target_stale !== true;
          if (targetCurrent && status.mode === "digital" && status.status === "passed") {
            if (draft.stage !== "compiled" && draft.compiled_version_id == null) {
              draft.stage = "digital_verified";
            }
            draft.digital_verified = true;
            draft.digital_verification_current = true;
            notice = "Digital engine check passed: machine-filled proof and no-go matrix exact-recovery verified.";
          } else if (targetCurrent && status.mode === "digital" && status.status === "failed") {
            draft.digital_verified = false;
            draft.digital_verification_current = false;
          }
        },
        onError: (message) => {
          if (generation !== requestGeneration || !poller?.isRunning()) return;
          failure = { code: "status_unavailable", detail: message };
        }
      });
      poller.start();
    }
    async function hydrateStatus(statusUrl) {
      if (!statusUrl) {
        isHydratingOverride = false;
        return;
      }
      const currentUrl = statusUrl;
      lastHydratedUrl = currentUrl;
      const generation = ++requestGeneration;
      if (poller) {
        poller.stop();
        poller = null;
      }
      const controller = new AbortController();
      const timeoutId = setTimeout(() => controller.abort(), 5e3);
      isHydratingOverride = true;
      try {
        const response = await fetch(statusUrl, {
          headers: { Accept: "application/json" },
          credentials: "same-origin",
          signal: controller.signal
        });
        if (!response || typeof response.json !== "function") return;
        const payload = await response.json().catch(() => null);
        if (generation !== requestGeneration || lastHydratedUrl !== currentUrl) return;
        if (!response.ok || !payload || typeof payload.status !== "string") {
          throw new Error("Verification status is unavailable.");
        }
        if (failure?.code === "status_unavailable") failure = null;
        runOverride = mergeRunPayload(runOverride ?? run(), payload);
        const targetCurrent = payload.target_current !== false && payload.target_stale !== true;
        if (targetCurrent && payload.mode === "digital" && payload.status === "passed") {
          if (draft.stage !== "compiled" && draft.compiled_version_id == null) {
            draft.stage = "digital_verified";
          }
          draft.digital_verified = true;
          draft.digital_verification_current = true;
          notice = "Digital engine check passed: machine-filled proof and no-go matrix exact-recovery verified.";
        } else if (targetCurrent && payload.mode === "digital" && payload.status === "failed") {
          draft.digital_verified = false;
          draft.digital_verification_current = false;
        }
        if (!isTerminalRunStatus(payload.status) && generation === requestGeneration) {
          attachPoller(statusUrl, Number(payload.run_id) || null, payload.mode ?? null);
        }
      } catch (error) {
        if (generation === requestGeneration && lastHydratedUrl === currentUrl) {
          failure = {
            code: "status_unavailable",
            detail: requestErrorDetail(error, "Verification status is unavailable.")
          };
        }
      } finally {
        clearTimeout(timeoutId);
        if (generation === requestGeneration && lastHydratedUrl === currentUrl) {
          isHydratingOverride = false;
        }
      }
    }
    onDestroy(() => {
      requestGeneration += 1;
      poller?.stop();
      poller = null;
      lastHydratedUrl = null;
    });
    function requestErrorText(value) {
      if (typeof value === "string" && value.trim()) return value.trim();
      if (Array.isArray(value)) return value.map(requestErrorText).find(Boolean) ?? "";
      if (value && typeof value === "object") {
        for (const key of ["detail", "message", "error", "reason"]) {
          const text = requestErrorText(value[key]);
          if (text) return text;
        }
      }
      return "";
    }
    function requestErrorCode(error, fallback) {
      const candidates = [
        error?.payload?.code,
        error?.code,
        error?.payload?.error?.code,
        error?.error?.code
      ];
      return candidates.find((candidate) => typeof candidate === "string" && candidate.trim())?.trim() ?? fallback;
    }
    function requestErrorDetail(error, fallback) {
      const detail = requestErrorText(error) || requestErrorText(error?.payload);
      return detail || fallback;
    }
    async function postJson(url, body) {
      const response = await fetch(url, {
        method: "POST",
        headers: {
          Accept: "application/json",
          "Content-Type": "application/json",
          ...csrfHeaders(getCsrfToken())
        },
        credentials: "same-origin",
        body: JSON.stringify(body)
      });
      const payload = await response.json().catch(() => ({}));
      if (!response.ok) {
        const errorPayload = payload && typeof payload === "object" ? payload : {};
        const err = new Error(requestErrorDetail(errorPayload, "The request was rejected."));
        const code = requestErrorCode(errorPayload, "");
        if (code) err.code = code;
        err.payload = errorPayload;
        throw err;
      }
      return payload;
    }
    async function createChallenge(method = "pre_shaded") {
      if (lifecycleBusy || busyFlags.challenge || !draft.id) return;
      activeMethod = method;
      activeCaptureMethod = method;
      challengeMode = challengeModeForMethod();
      const methodData = methodCards().find((candidate) => candidate.method === method);
      if (methodData?.challenge_id || methodData?.challenge?.challenge_id) {
        activeMethod = method;
        activeCaptureMethod = method;
        expandedStepKeyOverride = "physical_challenge";
        notice = "This test sheet already exists. Continue with its saved print and capture steps.";
        failure = null;
        return;
      }
      busyFlags = { ...busyFlags, challenge: true };
      failure = null;
      try {
        const payload = await postJson(`/setup/omr/templates/canonical/drafts/${draft.id}/challenge`, {
          mode: challengeMode,
          method,
          idempotency_key: newIdempotencyKey()
        });
        if (payload.run_id) {
          const fallbackStatus = payload.status ?? "awaiting_print";
          const responseMode = payload.mode ?? (challengeMode === "full" ? "full_qualification" : "quick_rehearsal");
          const responseChallenge = payload.challenge ?? {
            challenge_id: payload.challenge_id,
            challenge_code: payload.challenge_code,
            mode: challengeMode,
            status: "created",
            print_url: payload.print_url ?? null,
            print_mime: payload.print_mime ?? "application/pdf",
            pdf_ready: payload.pdf_ready ?? true,
            guide: payload.guide ?? null
          };
          runOverride = mergeRunPayload(
            run(),
            {
              ...payload,
              mode: responseMode,
              status: fallbackStatus,
              status_url: payload.status_url ?? null,
              available_actions: payload.available_actions ?? (fallbackStatus === "awaiting_print" ? ["print_challenge"] : ["submit_capture"]),
              challenge: responseChallenge
            },
            fallbackStatus
          );
        }
        if (payload.status_url) await hydrateStatus(payload.status_url);
        notice = isUploadedSource() ? "Physical test ready. Capture or upload a photo or scan of your physical test sheet." : "Test sheet created. Inspect the sheet and guide, then print at 100% / actual size.";
      } catch (error) {
        const code = requestErrorCode(error, "challenge_failed");
        failure = {
          code,
          detail: code === "challenge_exists_resume_required" ? "A saved physical test is ready to resume. Resume challenge instead of creating another sheet." : requestErrorDetail(error, "The challenge pack could not be created.")
        };
        notice = "";
      } finally {
        busyFlags = { ...busyFlags, challenge: false };
      }
    }
    async function markPrinted(context = {}) {
      if (busyFlags.print || !run()?.run_id) return;
      const method = context.method ?? activeMethod;
      activeMethod = method;
      activeCaptureMethod = method;
      busyFlags = { ...busyFlags, print: true };
      failure = null;
      try {
        const methodData = methodCards().find((candidate) => candidate.method === method);
        const binding = methodTargetPayload(methodData);
        const payload = await postJson(`/setup/omr/templates/verification-runs/${run().run_id}/print`, {
          method,
          ...binding,
          challenge_id: context.challengeId ?? binding.challenge_id ?? null,
          idempotency_key: newIdempotencyKey()
        });
        expandedStepKeyOverride = "capture";
        if (payload.status_url) await hydrateStatus(payload.status_url);
        notice = "Test sheet marked printed. Capture the page when ready.";
      } catch (error) {
        failure = {
          code: requestErrorCode(error, "print_failed"),
          detail: requestErrorDetail(error, "The test sheet could not be marked printed.")
        };
      } finally {
        busyFlags = { ...busyFlags, print: false };
      }
    }
    async function submitCapture(context) {
      const file = context?.file ?? context;
      const method = context?.method ?? activeMethod;
      const pageNo = Number(context?.pageNo ?? capturePageNo) || 1;
      if (busyFlags.capture || !file || !run()?.run_id) return;
      activeMethod = method;
      activeCaptureMethod = method;
      capturePageNo = pageNo;
      busyFlags = { ...busyFlags, capture: true };
      failure = null;
      notice = "";
      const form = new FormData();
      const methodData = methodCards().find((candidate) => candidate.method === method);
      const binding = methodTargetPayload(methodData);
      form.append("image", file);
      form.append("method", method);
      form.append("page_no", String(pageNo));
      form.append("capture_source", captureSourceForFile(file));
      form.append("idempotency_key", newIdempotencyKey());
      Object.entries(binding).forEach(([key, value]) => {
        if (value != null && value !== "") form.append(key, String(value));
      });
      if (method === "hand_shaded" && context?.medium) form.append("medium", context.medium);
      try {
        const response = await fetch(`/setup/omr/templates/verification-runs/${run().run_id}/capture`, {
          method: "POST",
          headers: {
            ...csrfHeaders(getCsrfToken()),
            "X-Requested-With": "XMLHttpRequest",
            Accept: "application/json"
          },
          credentials: "same-origin",
          body: form
        });
        const payload = await response.json().catch(() => ({}));
        if (!response.ok) {
          failure = {
            code: requestErrorCode(payload, "capture_rejected"),
            detail: requestErrorDetail(payload, "The capture was rejected by the engine worker.")
          };
          return;
        }
        if (payload.status_url) await hydrateStatus(payload.status_url);
        notice = "";
      } catch (error) {
        failure = {
          code: requestErrorCode(error, "network_error"),
          detail: requestErrorDetail(error, "The capture service is unreachable.")
        };
      } finally {
        busyFlags = { ...busyFlags, capture: false };
      }
    }
    async function retakeMethodPage(context = {}) {
      const method = context.method ?? activeMethod;
      const pageNo = Number(context.pageNo ?? capturePageNo) || 1;
      const methodData = methodCards().find((candidate) => candidate.method === method);
      const page = (methodData?.pages ?? []).find((candidate) => Number(candidate.page_no) === pageNo);
      if (busyFlags.capture || !run()?.run_id || !Number.isInteger(Number(page?.page_version))) return;
      activeMethod = method;
      activeCaptureMethod = method;
      capturePageNo = pageNo;
      busyFlags = { ...busyFlags, capture: true };
      failure = null;
      notice = "";
      try {
        const payload = await postJson(`/setup/omr/templates/verification-runs/${run().run_id}/methods/${method}/pages/${pageNo}/retake`, {
          page_version: Number(page.page_version),
          idempotency_key: newIdempotencyKey()
        });
        if (payload.status_url) await hydrateStatus(payload.status_url);
      } catch (error) {
        failure = {
          code: requestErrorCode(error, "retake_failed"),
          detail: requestErrorDetail(error, `Page ${pageNo} could not be prepared for another capture.`)
        };
      } finally {
        busyFlags = { ...busyFlags, capture: false };
      }
    }
    async function reviewCapture(decision, context = {}) {
      if (busyFlags.review || !run()?.run_id) return false;
      const method = context.method ?? activeMethod;
      activeMethod = method;
      activeCaptureMethod = method;
      busyFlags = { ...busyFlags, review: true };
      failure = null;
      try {
        const payload = await postJson(`/setup/omr/templates/verification-runs/${run().run_id}/review`, {
          decision,
          method,
          capture_id: context.captureId ?? selectedCaptureId ?? null,
          note: context.note || null,
          idempotency_key: newIdempotencyKey()
        });
        if (payload.status_url) await hydrateStatus(payload.status_url);
        notice = decision === "pass" ? "Test review completed." : "A new capture was requested; retained history is unchanged.";
        return true;
      } catch (error) {
        failure = {
          code: requestErrorCode(error, "review_failed"),
          detail: requestErrorDetail(error, "The test review could not be recorded.")
        };
        return false;
      } finally {
        busyFlags = { ...busyFlags, review: false };
      }
    }
    async function completeEvidenceModalReview(decision, note) {
      const captureId = evidencePage?.capture_id ?? selectedCaptureId;
      const alreadyPassed = activeEvidenceAttempt()?.attempt_state === "passed" || activeEvidenceAttempt()?.status === "pass";
      const saved = decision === "pass" && alreadyPassed ? await selectAttempt({
        method: activeMethod,
        attempt: activeEvidenceAttempt(),
        captureId,
        pageNo: activeEvidenceAttempt()?.page_no,
        reason: note
      }) : await reviewCapture(decision, { method: activeMethod, captureId, note });
      if (saved) closeEvidenceModal();
      return saved;
    }
    async function selectAttempt(context) {
      const method = context?.method ?? activeMethod;
      const attempt = context?.attempt;
      const captureId = context?.captureId ?? attempt?.capture_id;
      const reason = context?.reason?.trim() ?? "";
      if (!run()?.run_id || !captureId || busyFlags.review) return false;
      activeMethod = method;
      activeCaptureMethod = method;
      busyFlags = { ...busyFlags, review: true };
      failure = null;
      const methodData = methodCards().find((candidate) => candidate.method === method);
      const page = (methodData?.pages ?? []).find((candidate) => Number(candidate.page_no) === Number(context.pageNo ?? attempt?.page_no));
      const binding = methodTargetPayload(methodData);
      try {
        const payload = await postJson(`/setup/omr/templates/verification-runs/${run().run_id}/captures/${captureId}/select`, {
          method,
          page_no: Number(context.pageNo ?? attempt?.page_no ?? 1),
          reason,
          page_version: page?.page_version ?? null,
          ...binding,
          idempotency_key: newIdempotencyKey()
        });
        if (payload.status_url) await hydrateStatus(payload.status_url);
        notice = "Selected passing evidence for this method and page.";
        return true;
      } catch (error) {
        failure = {
          code: requestErrorCode(error, "selection_failed"),
          detail: requestErrorDetail(error, "The passing attempt could not be selected.")
        };
        return false;
      } finally {
        busyFlags = { ...busyFlags, review: false };
      }
    }
    function openPrint(url) {
      if (!url) return;
      window.open(url, "_blank", "noopener,noreferrer");
    }
    function openGuide(method) {
      guideMethod = METHOD_KEYS.includes(method) ? method : activeMethod;
      guideModalOpen = true;
    }
    let $$settled = true;
    let $$inner_renderer;
    function $$render_inner($$renderer3) {
      $$renderer3.push(`<div class="text-foreground [&amp;_input]:border-input [&amp;_input]:bg-background [&amp;_input]:text-foreground space-y-4"><div><h2 class="text-foreground text-sm font-bold">Verification &amp; Dry Run</h2> <p class="text-muted-foreground mt-1 text-xs leading-relaxed">Run no-write engine and physical diagnostics, then review the
            evidence. Reloading resumes the current setup run.</p></div> `);
      if (isHydrating()) {
        $$renderer3.push("<!--[0-->");
        $$renderer3.push(`<div class="flex items-center gap-2.5 rounded-xl border border-blue-200 bg-blue-50/70 p-3 text-xs text-blue-900 dark:border-blue-900/60 dark:bg-blue-950/30 dark:text-blue-200" role="status" aria-live="polite">`);
        Loader_circle($$renderer3, {
          class: "size-4 shrink-0 animate-spin text-blue-600 dark:text-blue-400"
        });
        $$renderer3.push(`<!----> <span class="font-semibold">Restoring verification status…</span></div>`);
      } else {
        $$renderer3.push("<!--[-1-->");
      }
      $$renderer3.push(`<!--]--> `);
      if (notice) {
        $$renderer3.push("<!--[0-->");
        $$renderer3.push(`<div class="rounded-xl border border-emerald-200 bg-emerald-50/70 p-3 text-xs text-emerald-900 dark:border-emerald-900/60 dark:bg-emerald-950/30 dark:text-emerald-200">${escape_html(notice)}</div>`);
      } else {
        $$renderer3.push("<!--[-1-->");
      }
      $$renderer3.push(`<!--]--> `);
      if (failure) {
        $$renderer3.push("<!--[0-->");
        $$renderer3.push(`<div class="rounded-xl border border-red-200 bg-red-50/70 p-3 text-xs text-red-900 dark:border-red-900/60 dark:bg-red-950/30 dark:text-red-200"><div class="font-semibold">${escape_html(failure.detail || "An error occurred.")}</div> `);
        if (failure.code && failure.code !== failure.detail) {
          $$renderer3.push("<!--[0-->");
          $$renderer3.push(`<details class="mt-1 text-[10px] opacity-75"><summary class="inline-block cursor-pointer font-mono hover:underline">Technical details</summary> <div class="mt-0.5 font-mono text-[10px]">${escape_html(failure.code)}</div></details>`);
        } else {
          $$renderer3.push("<!--[-1-->");
        }
        $$renderer3.push(`<!--]--></div>`);
      } else {
        $$renderer3.push("<!--[-1-->");
      }
      $$renderer3.push(`<!--]--> <div class="space-y-3"><div class="flex items-center justify-between pb-0.5"><div class="flex items-center gap-2"><span class="text-muted-foreground text-[11px] font-bold tracking-wider uppercase">Verification Checkpoints</span> <span class="text-muted-foreground text-[10px] font-medium">(${escape_html(states().filter((s) => s.status === "done").length)} of ${escape_html(states().length)}
                    complete)</span></div> <button type="button" class="text-muted-foreground hover:text-foreground border-border/70 hover:bg-muted/40 focus-visible:ring-ring inline-flex items-center gap-1 rounded-md border px-2 py-0.5 text-[10px] font-semibold transition focus-visible:ring-2 focus-visible:outline-none">`);
      if (expandedStepKey() !== null) {
        $$renderer3.push("<!--[0-->");
        Chevron_up($$renderer3, { class: "size-3" });
        $$renderer3.push(`<!----> <span>Collapse all</span>`);
      } else {
        $$renderer3.push("<!--[-1-->");
        Chevron_down($$renderer3, { class: "size-3" });
        $$renderer3.push(`<!----> <span>Expand active</span>`);
      }
      $$renderer3.push(`<!--]--></button></div> <ol aria-label="Verification progress" class="space-y-2.5"><!--[-->`);
      const each_array = ensure_array_like(states());
      for (let $$index_2 = 0, $$length = each_array.length; $$index_2 < $$length; $$index_2++) {
        let state = each_array[$$index_2];
        const isBlocked = state.status === "blocked";
        const isDone = state.status === "done";
        const isActive = state.status === "active";
        const isPending = state.status === "pending";
        const isActionActive = state.key === activeStep()?.key;
        const isExpanded = expandedStepKey() === state.key;
        const isDigitalActive = state.key === "digital" && isDigitalRunning();
        $$renderer3.push(`<li${attr("aria-current", isActionActive ? "step" : void 0)} class="relative"><section${attr("aria-labelledby", isExpanded ? "current-step-heading" : void 0)}${attr_class(`rounded-xl border transition-all ${isBlocked ? "border-border/60 bg-muted/20 opacity-85" : isExpanded ? isActive ? "bg-card border-blue-400/80 shadow-xs ring-1 ring-blue-300/40 dark:border-blue-800 dark:ring-blue-950/60" : isDone ? "bg-card border-emerald-400/70 shadow-xs dark:border-emerald-800/70" : "border-border bg-card shadow-xs" : "border-border/80 bg-card/60 hover:bg-card hover:border-border"}`)}><button type="button"${attr("disabled", isBlocked, true)}${attr("aria-expanded", isExpanded)}${attr_class(`flex w-full items-center justify-between gap-2.5 rounded-xl p-3 text-left transition ${isBlocked ? "text-muted-foreground cursor-not-allowed" : "hover:bg-muted/30 focus-visible:ring-ring cursor-pointer focus-visible:ring-2 focus-visible:outline-none"}`)}><div class="flex min-w-0 items-center gap-2.5"><div${attr_class(`flex size-6 shrink-0 items-center justify-center rounded-full text-[10px] font-bold transition-all ${isDone ? "bg-emerald-600 text-white shadow-xs dark:bg-emerald-500" : isActive ? "bg-slate-900 text-white shadow-xs ring-2 ring-blue-500/50 dark:bg-slate-100 dark:text-slate-900 dark:ring-blue-400/50" : isPending ? "border border-slate-300 bg-slate-100 text-slate-700 dark:border-slate-600 dark:bg-slate-800 dark:text-slate-200" : "border-border/60 bg-muted/40 text-muted-foreground/60 border"}`)}>`);
        if (isDone) {
          $$renderer3.push("<!--[0-->");
          Check($$renderer3, { class: "size-3.5 stroke-[2.5]", "aria-hidden": "true" });
        } else if (isActive && isDigitalActive) {
          $$renderer3.push("<!--[1-->");
          Loader_circle($$renderer3, {
            class: "size-3.5 animate-spin text-white dark:text-slate-900",
            "aria-hidden": "true"
          });
        } else {
          $$renderer3.push("<!--[-1-->");
          $$renderer3.push(`<span>${escape_html(state.step)}</span>`);
        }
        $$renderer3.push(`<!--]--></div> <span${attr("id", isExpanded ? "current-step-heading" : void 0)}${attr_class(`text-xs ${isActive ? "text-foreground font-bold" : isPending ? "text-foreground font-semibold" : isDone ? "text-foreground font-medium" : "text-muted-foreground/80 font-normal"} truncate`)}>${escape_html(state.label)}</span></div> <div class="flex shrink-0 items-center gap-2"><span${attr_class(`rounded-full px-2 py-0.5 text-[9px] font-bold tracking-wide uppercase ${isDone ? "bg-emerald-100 text-emerald-800 dark:bg-emerald-900/40 dark:text-emerald-300" : isActive ? "bg-blue-100 text-blue-800 dark:bg-blue-900/40 dark:text-blue-300" : isBlocked ? "bg-muted text-muted-foreground" : "bg-slate-100 text-slate-700 dark:bg-slate-800 dark:text-slate-300"}`)}>${escape_html(checkpointStatusLabel(state))}</span> `);
        if (!isBlocked) {
          $$renderer3.push("<!--[0-->");
          $$renderer3.push(`<span${attr_class(`text-muted-foreground/60 transition-transform duration-200 ${isExpanded ? "rotate-180" : ""}`)}>`);
          Chevron_down($$renderer3, { class: "size-3.5" });
          $$renderer3.push(`<!----></span>`);
        } else {
          $$renderer3.push("<!--[-1-->");
        }
        $$renderer3.push(`<!--]--></div></button> `);
        if (isBlocked && state.prerequisites.length > 0) {
          $$renderer3.push("<!--[0-->");
          $$renderer3.push(`<div class="text-muted-foreground -mt-0.5 flex items-start gap-1 px-3 pb-2.5 text-[11px] leading-relaxed"><span class="text-foreground shrink-0 font-semibold">Requires:</span> <span class="break-words">${escape_html(state.prerequisites.join(" · "))}</span></div>`);
        } else {
          $$renderer3.push("<!--[-1-->");
        }
        $$renderer3.push(`<!--]--> `);
        if (isExpanded) {
          $$renderer3.push("<!--[0-->");
          $$renderer3.push(`<div class="border-border/80 bg-muted/15 space-y-3 rounded-b-xl border-t p-3.5">`);
          if (state.key === "physical_challenge") {
            $$renderer3.push("<!--[0-->");
            $$renderer3.push(`<details class="border-border/70 bg-muted/20 rounded-lg border px-2.5 py-2"><summary class="text-foreground focus-visible:ring-ring flex cursor-pointer list-none items-center justify-between gap-2 text-[11px] font-semibold focus-visible:ring-2 focus-visible:outline-none"><span>What this step verifies</span> `);
            Chevron_down($$renderer3, { class: "size-3.5 shrink-0", "aria-hidden": "true" });
            $$renderer3.push(`<!----></summary> <p class="text-muted-foreground mt-2 text-[10px] leading-relaxed">${escape_html(state.purpose)}</p></details>`);
          } else {
            $$renderer3.push("<!--[-1-->");
            $$renderer3.push(`<p class="text-muted-foreground text-xs leading-relaxed">${escape_html(state.purpose)}</p>`);
          }
          $$renderer3.push(`<!--]--> `);
          if (state.key === "digital" && isDigitalRunning() && run()) {
            $$renderer3.push("<!--[0-->");
            $$renderer3.push(`<div class="space-y-2 rounded-xl border border-blue-200 bg-blue-50/70 p-3 dark:border-blue-900/60 dark:bg-blue-950/30" role="status" aria-live="polite"><div class="flex items-center justify-between text-xs font-semibold text-blue-900 dark:text-blue-200"><div class="flex items-center gap-2">`);
            Loader_circle($$renderer3, {
              class: "size-3.5 shrink-0 animate-spin text-blue-600 dark:text-blue-400"
            });
            $$renderer3.push(`<!----> <span>Running digital
                                                    verification…</span></div> <span class="font-mono text-[11px] text-blue-700 dark:text-blue-300">${escape_html(run()?.progress?.done ?? 0)} / ${escape_html(run()?.progress?.total || 14)} variants</span></div> <div class="h-1.5 w-full overflow-hidden rounded-full bg-blue-200/70 dark:bg-blue-900/50"><div class="h-1.5 rounded-full bg-blue-600 transition-all duration-300 ease-out dark:bg-blue-400"${attr_style(`width: ${stringify(Math.max(5, Math.min(100, (run()?.progress?.done ?? 0) / (run()?.progress?.total || 14) * 100)))}%`)} role="progressbar"${attr("aria-valuenow", run()?.progress?.done ?? 0)} aria-valuemin="0"${attr("aria-valuemax", run()?.progress?.total || 14)}></div></div> <div class="flex items-center justify-between text-[10px] text-blue-800/80 dark:text-blue-300/80"><span>Phase: <strong class="text-blue-950 dark:text-blue-100">${escape_html(phaseText(run()))}</strong></span> `);
            if (run()?.phase_detail?.current_variant) {
              $$renderer3.push("<!--[0-->");
              $$renderer3.push(`<span>Variant: <strong class="font-mono text-blue-950 dark:text-blue-100">${escape_html(run().phase_detail.current_variant)}</strong></span>`);
            } else {
              $$renderer3.push("<!--[-1-->");
            }
            $$renderer3.push(`<!--]--></div></div>`);
          } else {
            $$renderer3.push("<!--[-1-->");
          }
          $$renderer3.push(`<!--]--> `);
          if (state.key === "digital" && isDigitalQueued() && run()) {
            $$renderer3.push("<!--[0-->");
            $$renderer3.push(`<div class="rounded-lg border border-amber-200/80 bg-amber-50/60 px-2.5 py-1.5 text-[11px] text-amber-900 dark:border-amber-900/50 dark:bg-amber-950/25 dark:text-amber-200" role="status" aria-live="polite"><p><span class="font-semibold">Queued:</span> waiting for the dedicated verification
                                            worker to claim this run.</p> <p class="mt-1 text-[10px] opacity-80">${escape_html(run()?.progress?.done ?? 0)} / ${escape_html(run()?.progress?.total || 14)} variants
                                            planned</p></div>`);
          } else {
            $$renderer3.push("<!--[-1-->");
          }
          $$renderer3.push(`<!--]--> `);
          if (state.status === "active" && state.key === "digital" && run()?.safe_issue) {
            $$renderer3.push("<!--[0-->");
            $$renderer3.push(`<div class="rounded-lg border border-red-200 bg-red-50/60 p-2.5 text-[11px] text-red-800 dark:border-red-900/60 dark:bg-red-950/30 dark:text-red-300"><span class="font-semibold">Issue:</span> ${escape_html(run().safe_issue.detail || run().safe_issue.code || state.statusText)}</div>`);
          } else if (state.status === "active" && state.statusText && state.key !== "digital") {
            $$renderer3.push("<!--[1-->");
            $$renderer3.push(`<div class="text-muted-foreground bg-card border-border/70 rounded-lg border p-2.5 text-[11px]"><span class="text-foreground font-semibold">Status:</span> ${escape_html(state.statusText)}</div>`);
          } else if (state.evidenceSummary) {
            $$renderer3.push("<!--[2-->");
            $$renderer3.push(`<div class="text-muted-foreground bg-card border-border/70 rounded-lg border p-2.5 text-[11px]"><span class="text-foreground font-semibold">Evidence:</span> ${escape_html(state.evidenceSummary)}</div>`);
          } else {
            $$renderer3.push("<!--[-1-->");
          }
          $$renderer3.push(`<!--]--> `);
          if (state.key === "digital") {
            $$renderer3.push("<!--[0-->");
            if (state.status === "active" && state.retryAvailable) {
              $$renderer3.push("<!--[0-->");
              $$renderer3.push(`<div class="border-border space-y-2 border-t pt-3"><button type="button"${attr("disabled", busyFlags.digital || isHydrating(), true)} class="inline-flex w-full items-center justify-center gap-1.5 rounded-lg bg-slate-900 px-3 py-2 text-[11px] font-semibold text-white focus-visible:ring-2 focus-visible:outline-none disabled:opacity-40">`);
              if (busyFlags.digital || isHydrating()) {
                $$renderer3.push("<!--[0-->");
                Loader_circle($$renderer3, { class: "size-3.5 animate-spin" });
              } else {
                $$renderer3.push("<!--[-1-->");
                Rotate_ccw($$renderer3, { class: "size-3.5" });
              }
              $$renderer3.push(`<!--]--> Retry digital verification</button> <p class="text-muted-foreground text-[10px]">Retry runs the same pinned plan
                                                and expected vector — prior
                                                variant evidence is preserved.</p></div>`);
            } else if (state.status === "pending" && state.actionAvailable && !isHydrating()) {
              $$renderer3.push("<!--[1-->");
              $$renderer3.push(`<div class="border-border space-y-2 border-t pt-3"><button type="button"${attr("disabled", busyFlags.digital || lifecycleBusy, true)} class="inline-flex w-full items-center justify-center gap-1.5 rounded-lg bg-blue-700 px-3 py-2 text-[11px] font-semibold text-white hover:bg-blue-600 focus-visible:ring-2 focus-visible:outline-none disabled:opacity-40">`);
              if (busyFlags.digital) {
                $$renderer3.push("<!--[0-->");
                Loader_circle($$renderer3, { class: "size-3.5 animate-spin" });
              } else {
                $$renderer3.push("<!--[-1-->");
              }
              $$renderer3.push(`<!--]--> Run digital verification</button> <p class="text-muted-foreground text-[10px]">Runs the machine-filled proof
                                                through the recognition engine
                                                and no-go matrix.</p></div>`);
            } else {
              $$renderer3.push("<!--[-1-->");
            }
            $$renderer3.push(`<!--]--> `);
            if (draft.print_pdf_url && !isUploadedSource()) {
              $$renderer3.push("<!--[0-->");
              $$renderer3.push(`<div class="pt-1"><button type="button" class="border-border bg-card hover:bg-muted text-foreground inline-flex w-full items-center justify-center gap-1.5 rounded-lg border px-3 py-2 text-[11px] font-semibold focus-visible:ring-2 focus-visible:outline-none">`);
              Printer($$renderer3, { class: "size-3.5" });
              $$renderer3.push(`<!----> Open
                                                printable candidate PDF</button></div>`);
            } else {
              $$renderer3.push("<!--[-1-->");
            }
            $$renderer3.push(`<!--]-->`);
          } else {
            $$renderer3.push("<!--[-1-->");
          }
          $$renderer3.push(`<!--]--> `);
          if (state.key === "physical_challenge") {
            $$renderer3.push("<!--[0-->");
            if (digitalPassedCheck() && !run()?.challenge && !isHydrating() && !recovery()?.kind) {
              $$renderer3.push("<!--[0-->");
              $$renderer3.push(`<input class="sr-only w-full text-left disabled:cursor-not-allowed disabled:opacity-45" type="radio" name="physical-challenge-mode" value="quick" aria-label="Quick rehearsal"${attr("aria-checked", challengeMode === "quick")}${attr("checked", challengeMode === "quick", true)}${attr("disabled", false, true)}/> <input class="sr-only w-full text-left disabled:cursor-not-allowed disabled:opacity-45" type="radio" name="physical-challenge-mode" value="full" aria-label="Full challenge"${attr("aria-checked", challengeMode === "full")}${attr("checked", challengeMode === "full", true)}${attr("disabled", !draft.compiled_version_id || !isProfileQualified(), true)}/>`);
            } else {
              $$renderer3.push("<!--[-1-->");
            }
            $$renderer3.push(`<!--]--> <div class="border-border space-y-3 border-t pt-3">`);
            if (digitalPassedCheck() && !draft.compiled_version_id && !isHydrating() && !recovery()?.kind) {
              $$renderer3.push("<!--[0-->");
              $$renderer3.push(`<div class="border-border bg-card flex flex-wrap items-center justify-between gap-3 rounded-xl border p-3 text-xs shadow-xs"><div class="max-w-xl space-y-0.5"><p class="text-foreground font-semibold">Digital recognition check passed</p> <p class="text-muted-foreground text-[11px] leading-relaxed">Lock this template design to begin testing with real paper. It remains inactive until all paper test pages pass.</p></div> <button type="button"${attr("disabled", !isProfileQualified() || busyFlags.compile || lifecycleBusy, true)} class="focus-visible:ring-ring inline-flex items-center gap-1.5 rounded-lg bg-slate-900 px-3 py-2 text-xs font-semibold text-white shadow-xs transition hover:bg-slate-800 focus-visible:ring-2 focus-visible:outline-none disabled:opacity-40 dark:bg-slate-100 dark:text-slate-900 dark:hover:bg-white">`);
              if (busyFlags.compile) {
                $$renderer3.push("<!--[0-->");
                Loader_circle($$renderer3, { class: "size-3.5 animate-spin", "aria-hidden": "true" });
              } else {
                $$renderer3.push("<!--[-1-->");
              }
              $$renderer3.push(`<!--]--> Lock Version for Physical Testing</button></div>`);
            } else {
              $$renderer3.push("<!--[-1-->");
            }
            $$renderer3.push(`<!--]--> `);
            if (isUploadedSource()) {
              $$renderer3.push("<!--[0-->");
              $$renderer3.push(`<div class="border-border bg-card space-y-1.5 rounded-xl border p-3 text-xs" role="status"><p class="text-foreground font-semibold">Testing with Pre-Printed Answer Sheets</p> <p class="text-muted-foreground leading-relaxed text-[11px]">Because this is an uploaded pre-printed sheet, NEXIAM will not print new blank copies. Use your genuine supplier answer sheets (both blank sheets and hand-filled test sheets) to complete the test captures. Record the sheet sample and capture device used for each test.</p></div>`);
            } else {
              $$renderer3.push("<!--[-1-->");
              $$renderer3.push(`<details class="border-border/70 bg-muted/20 rounded-lg border px-2.5 py-2"><summary class="text-foreground focus-visible:ring-ring flex cursor-pointer list-none items-center justify-between gap-2 text-[10px] font-semibold focus-visible:ring-2 focus-visible:outline-none"><span>Physical test information</span> `);
              Chevron_down($$renderer3, { class: "size-3.5 shrink-0", "aria-hidden": "true" });
              $$renderer3.push(`<!----></summary> `);
              if (run()?.challenge) {
                $$renderer3.push("<!--[0-->");
                $$renderer3.push(`<p class="text-muted-foreground mt-2 text-[10px] leading-relaxed"><strong class="text-foreground">Calibration-ID
                                                        disclaimer:</strong> this signed calibration QR identifies
                                                    diagnostic evidence only; it never
                                                    qualifies a marker profile or
                                                    template.</p>`);
              } else {
                $$renderer3.push("<!--[-1-->");
              }
              $$renderer3.push(`<!--]--> <div class="border-border/60 mt-3 border-t pt-3 text-[11px] leading-relaxed" aria-label="Physical test order"><p class="font-semibold">How the two tests fit
                                                    together</p> <ol class="mt-2 list-decimal space-y-0.5 pl-4 text-blue-800 dark:text-blue-200"><li><strong>Pre-shaded:</strong> create, print, then capture
                                                        or upload the machine baseline.</li> <li><strong>Hand-shaded:</strong> create, print, shade using
                                                        the guide, then capture or
                                                        upload it.</li></ol> <p class="mt-1 text-[10px] text-blue-700 dark:text-blue-300">Each method card owns its
                                                    full flow. There is no
                                                    separate upload step.</p></div></details>`);
            }
            $$renderer3.push(`<!--]--> `);
            if (recovery()?.kind) {
              $$renderer3.push("<!--[0-->");
              $$renderer3.push(`<div class="rounded-lg border border-amber-300/70 bg-amber-50/70 p-2.5 text-[11px] text-amber-900 dark:border-amber-900/60 dark:bg-amber-950/25 dark:text-amber-200"><p class="font-semibold">${escape_html(recovery().kind === "repair_reference" ? "Update challenge sheet" : recovery().kind === "restart_rehearsal" ? "Layout changed" : "Evidence retained")}</p> <p>${escape_html(recovery().kind === "restart_rehearsal" ? "This test belongs to an older layout. Start one new rehearsal; digital verification runs first, then both physical methods unlock." : recovery().kind === "repair_reference" ? "Update the saved physical test sheet before printing or capturing." : recovery().detail)}</p> `);
              if (recovery().action_url) {
                $$renderer3.push("<!--[0-->");
                $$renderer3.push(`<button type="button" class="focus-visible:ring-ring mt-2 inline-flex items-center gap-1.5 rounded-md bg-slate-900 px-2.5 py-1.5 font-semibold text-white focus-visible:ring-2 focus-visible:outline-none"${attr("disabled", busyFlags.challenge || lifecycleBusy, true)}>${escape_html(recovery().kind === "repair_reference" ? "Update sheet" : "Start new rehearsal")}</button>`);
              } else {
                $$renderer3.push("<!--[-1-->");
              }
              $$renderer3.push(`<!--]--></div>`);
            } else {
              $$renderer3.push("<!--[-1-->");
            }
            $$renderer3.push(`<!--]--> `);
            if (!recovery()?.kind && hasResumablePhysicalChallenge(run())) {
              $$renderer3.push("<!--[0-->");
              $$renderer3.push(`<button type="button" class="focus-visible:ring-ring inline-flex w-full items-center justify-center gap-1.5 rounded-md bg-slate-900 px-3 py-2 text-[11px] font-semibold text-white focus-visible:ring-2 focus-visible:outline-none disabled:opacity-40 dark:bg-slate-100 dark:text-slate-900"${attr("disabled", busyFlags.challenge || lifecycleBusy, true)}>`);
              if (busyFlags.challenge) {
                $$renderer3.push("<!--[0-->");
                Loader_circle($$renderer3, { class: "size-3.5 animate-spin", "aria-hidden": "true" });
              } else {
                $$renderer3.push("<!--[-1-->");
                Rotate_ccw($$renderer3, { class: "size-3.5", "aria-hidden": "true" });
              }
              $$renderer3.push(`<!--]--> Resume challenge</button>`);
            } else {
              $$renderer3.push("<!--[-1-->");
            }
            $$renderer3.push(`<!--]--> `);
            if (qualification()?.status === "rehearsal_only" || qualification()?.blockers?.some((blocker) => blocker.code === "candidate_not_qualification_capable")) {
              $$renderer3.push("<!--[0-->");
              $$renderer3.push(`<div class="rounded-lg border border-amber-300/70 bg-amber-50/70 p-2.5 text-[11px] text-amber-900 dark:border-amber-900/60 dark:bg-amber-950/25 dark:text-amber-200" role="status"><strong>Rehearsal only</strong> —
                                                candidate physical evidence is retained
                                                for troubleshooting and cannot qualify
                                                or activate a template.</div>`);
            } else {
              $$renderer3.push("<!--[-1-->");
            }
            $$renderer3.push(`<!--]--> `);
            if (draft.registration_mode === "document_bound") {
              $$renderer3.push("<!--[0-->");
              $$renderer3.push(`<p role="status" class="text-muted-foreground text-xs">Physical captures are diagnostic
                                                evidence. Activation still
                                                requires genuine per-page
                                                printer and device
                                                qualification.</p>`);
            } else {
              $$renderer3.push("<!--[-1-->");
            }
            $$renderer3.push(`<!--]--> `);
            if (!recovery()?.kind) {
              $$renderer3.push("<!--[0-->");
              $$renderer3.push(`<div class="flex min-w-0 flex-col gap-3" aria-label="Physical tests"><!--[-->`);
              const each_array_1 = ensure_array_like(methodCards());
              for (let $$index = 0, $$length2 = each_array_1.length; $$index < $$length2; $$index++) {
                let methodData = each_array_1[$$index];
                OmrPhysicalTestMethodCard($$renderer3, {
                  registrationMode: draft.registration_mode,
                  isUploadedSource: isUploadedSource(),
                  method: methodData.method,
                  methodData,
                  requiredPageCount: methodPageCount(methodData),
                  busy: busyFlags.challenge || lifecycleBusy,
                  captureBusy: busyFlags.capture && activeMethod === methodData.method,
                  captureDisabled: isHydrating() || !methodRunId(),
                  captureEnabled: methodData.method === activePhysicalMethod(),
                  captureQueueReason: "Another physical method is active. Select this method to capture it.",
                  phaseDetail: run()?.phase_detail,
                  onCreate: createChallenge,
                  onOpenPrint: openPrint,
                  onOpenGuide: () => openGuide(methodData.method),
                  onMarkPrinted: markPrinted,
                  onSubmitCapture: submitCapture,
                  onOpenEvidence: openMethodEvidence,
                  onRetry: retakeMethodPage,
                  onSelectMethod: selectCaptureMethod,
                  onSelectAttempt: selectAttempt
                });
              }
              $$renderer3.push(`<!--]--></div>`);
            } else {
              $$renderer3.push("<!--[-1-->");
            }
            $$renderer3.push(`<!--]--></div>`);
          } else {
            $$renderer3.push("<!--[-1-->");
          }
          $$renderer3.push(`<!--]--> `);
          if (state.key === "evidence_review") {
            $$renderer3.push("<!--[0-->");
            $$renderer3.push(`<div class="border-border space-y-3 border-t pt-3"><p class="text-muted-foreground text-xs">Review each physical test in its
                                            workbench. These cards only show
                                            whether the review is complete.</p> <div class="flex min-w-0 flex-col gap-3" aria-label="Evidence by physical test">`);
            if (legacyEvidence() && !run()?.methods?.length) {
              $$renderer3.push("<!--[0-->");
              $$renderer3.push(`<div class="border-border/70 bg-card rounded-lg border p-3"><div class="flex flex-wrap items-center justify-between gap-3"><div><h4 class="text-xs font-bold">Pre-shaded test</h4> <p class="text-muted-foreground mt-0.5 text-[10px]">Review required</p></div> <div class="flex items-center gap-2"><span class="rounded-full bg-amber-100 px-2 py-0.5 text-[10px] font-semibold text-amber-900 dark:bg-amber-900/40 dark:text-amber-100">Not reviewed</span> <button type="button" class="bg-primary text-primary-foreground hover:bg-primary/90 focus-visible:ring-ring inline-flex min-h-8 items-center justify-center rounded-md px-3 text-[11px] font-semibold focus-visible:ring-2 focus-visible:outline-none">Review</button></div></div></div>`);
            } else {
              $$renderer3.push("<!--[-1-->");
              $$renderer3.push(`<!--[-->`);
              const each_array_2 = ensure_array_like(methodCards());
              for (let $$index_1 = 0, $$length2 = each_array_2.length; $$index_1 < $$length2; $$index_1++) {
                let methodData = each_array_2[$$index_1];
                const reviewAttempt = (methodData.attempts ?? []).find((attempt) => attempt.attempt_state === "passed" || attempt.status === "pass") ?? methodData.attempts?.[0] ?? null;
                $$renderer3.push(`<div class="border-border/70 bg-card rounded-lg border p-3"><div class="flex flex-wrap items-center justify-between gap-3"><div class="min-w-0"><h4 class="text-xs font-bold">${escape_html(methodData.method === "hand_shaded" ? "Hand-shaded test" : "Pre-shaded test")}</h4> <p class="text-muted-foreground mt-0.5 text-[10px]">${escape_html(methodData.state === "passed" ? "Review complete" : methodData.state === "needs_review" ? "Review required" : "Waiting for an analyzed capture")}</p></div> <div class="flex items-center gap-2"><span${attr_class(`rounded-full px-2 py-0.5 text-[10px] font-semibold ${methodData.state === "passed" ? "bg-emerald-100 text-emerald-800 dark:bg-emerald-900/40 dark:text-emerald-100" : methodData.state === "needs_review" ? "bg-amber-100 text-amber-900 dark:bg-amber-900/40 dark:text-amber-100" : "bg-muted text-muted-foreground"}`)}>${escape_html(methodData.state === "passed" ? "Reviewed" : methodData.state === "needs_review" ? "Not reviewed" : "Not ready")}</span> `);
                if (reviewAttempt) {
                  $$renderer3.push("<!--[0-->");
                  $$renderer3.push(`<button type="button" class="bg-primary text-primary-foreground hover:bg-primary/90 focus-visible:ring-ring inline-flex min-h-8 items-center justify-center rounded-md px-3 text-[11px] font-semibold focus-visible:ring-2 focus-visible:outline-none">Review</button>`);
                } else {
                  $$renderer3.push("<!--[-1-->");
                }
                $$renderer3.push(`<!--]--></div></div></div>`);
              }
              $$renderer3.push(`<!--]-->`);
            }
            $$renderer3.push(`<!--]--></div></div>`);
          } else {
            $$renderer3.push("<!--[-1-->");
          }
          $$renderer3.push(`<!--]--></div>`);
        } else {
          $$renderer3.push("<!--[-1-->");
        }
        $$renderer3.push(`<!--]--></section></li>`);
      }
      $$renderer3.push(`<!--]--></ol></div> <div class="bg-card/95 border-border sticky bottom-0 mt-3 flex flex-col gap-2 border-t pt-3 pb-1 shadow-xs backdrop-blur-xs">`);
      if (draft?.compiled_version_id) {
        $$renderer3.push("<!--[0-->");
        if (draft.compiled_version_status === "active") {
          $$renderer3.push("<!--[0-->");
          $$renderer3.push(`<div class="flex items-center justify-center gap-1.5 rounded-xl border border-emerald-500/30 bg-emerald-500/10 px-3 py-2 text-xs font-semibold text-emerald-700 dark:text-emerald-400">`);
          Check($$renderer3, { class: "size-3.5" });
          $$renderer3.push(`<!----> <span>Active Answer Sheet · Ready in Booklets</span></div>`);
        } else {
          $$renderer3.push("<!--[-1-->");
          $$renderer3.push(`<button type="button"${attr("disabled", activateBusy, true)} class="bg-emerald-600 text-white hover:bg-emerald-500 focus-visible:ring-ring flex w-full items-center justify-center gap-1.5 rounded-xl px-3 py-2 text-xs font-semibold shadow-xs transition focus-visible:ring-2 focus-visible:outline-none disabled:opacity-50">`);
          Sparkles($$renderer3, { class: "size-3.5" });
          $$renderer3.push(`<!----> <span>Activate for Exam Booklets</span></button>`);
        }
        $$renderer3.push(`<!--]-->`);
      } else {
        $$renderer3.push("<!--[-1-->");
      }
      $$renderer3.push(`<!--]--> `);
      if (isComplete()) {
        $$renderer3.push("<!--[0-->");
        Link($$renderer3, {
          href: "/setup/exam-materials",
          class: "bg-primary text-primary-foreground hover:bg-primary/90 focus-visible:ring-ring flex w-full items-center justify-center gap-1.5 rounded-xl px-3 py-2 text-xs font-semibold transition focus-visible:ring-2 focus-visible:outline-none",
          children: ($$renderer4) => {
            Check($$renderer4, { class: "size-3.5" });
            $$renderer4.push(`<!----> <span>Finish &amp; return to Exam materials</span>`);
          },
          $$slots: { default: true }
        });
      } else {
        $$renderer3.push("<!--[-1-->");
      }
      $$renderer3.push(`<!--]--> <button type="button" class="border-border hover:bg-muted text-foreground focus-visible:ring-ring flex w-full items-center justify-center gap-1.5 rounded-xl border px-3 py-2 text-xs font-semibold transition focus-visible:ring-2 focus-visible:outline-none">`);
      Chevron_left($$renderer3, { class: "size-3.5" });
      $$renderer3.push(`<!----> <span>Return to Recognition Mapping</span></button></div> `);
      StudioShadingGuideModal($$renderer3, {
        challenge: guideChallenge(),
        method: guideMethod ?? guideMethodData()?.method ?? activeMethod,
        challengeId: guideChallengeId(),
        onMarkPrinted: markPrinted,
        onOpenPrintPdf: openPrint,
        busy: busyFlags.print,
        get open() {
          return guideModalOpen;
        },
        set open($$value) {
          guideModalOpen = $$value;
          $$settled = false;
        }
      });
      $$renderer3.push(`<!----> `);
      OmrDiagnosticEvidenceModal($$renderer3, {
        page: evidencePage,
        loading: evidenceLoading,
        error: evidenceError,
        onRetry: evidenceRetry,
        onPageChange: evidencePageChange,
        onGroupChange: evidenceGroupChange,
        onFilterChange: evidenceFilterChange,
        onTabChange: evidenceTabChange,
        onViewChange: evidenceViewChange,
        initialTab: evidenceTab,
        onClose: closeEvidenceModal,
        reviewable: false,
        reviewReasons: evidenceReviewReasons,
        contextSummary: evidenceContextSummary(),
        onSaveDecision: saveEvidenceDecision,
        onSaveAndNext: saveEvidenceDecision,
        captureReviewRequired: activeReviewMethodData()?.state === "needs_review" || !run()?.methods?.length && run()?.status === "needs_review",
        captureReviewBusy: busyFlags.review,
        onCaptureDecision: completeEvidenceModalReview,
        title: `${activeMethod === "hand_shaded" ? "Hand-shaded test" : "Pre-shaded test"} evidence`,
        subtitle: "Review detected marks against the expected diagnostic result",
        get open() {
          return evidenceModalOpen;
        },
        set open($$value) {
          evidenceModalOpen = $$value;
          $$settled = false;
        }
      });
      $$renderer3.push(`<!----> `);
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
    bind_props($$props, { draft });
  });
}
export {
  StudioExportVerifyPanel as default
};
