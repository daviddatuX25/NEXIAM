import { n as newIdempotencyKey, o as onDestroy, h as head, b as attr, e as escape_html, c as attr_class, d as derived, p as info, s as stringify, a as ensure_array_like, f as attr_style, q as success, m as csrfHeaders, v as error } from "../app.js";
import { T as ToastManager } from "./ToastManager-C7oO_4Jn.js";
import { O as OmrRegionCrop } from "./OmrDiagnosticEvidencePanel-CvO7kqrK.js";
import { O as OmrWorkbench, i as isOmrWorkbenchProjection, c as createOmrViewfinderSession, a as OmrPendingCaptureProgress, b as OmrViewfinderHud, p as productionItemReviewKey, d as buildOmrConsoleReviewPayload, e as productionItemReviewUrl, f as assertProductionItemReviewAcknowledged, g as createOmrWorkbenchClient, h as acceptOmrWorkbenchProjection } from "./omrWorkbenchClient-DKqCgkj5.js";
import { A as Arrow_left } from "./arrow-left-DbWnQR3t.js";
import { L as Layout_grid } from "./layout-grid-CXJfSBqV.js";
import { M as Maximize_2, I as Image } from "./maximize-2-u72Ahym6.js";
import { S as Settings_2 } from "./settings-2-B2lTvmrM.js";
import { R as Refresh_cw } from "./refresh-cw-CYdk8aE0.js";
import { S as Scan_line } from "./scan-line-CiwhUtYK.js";
import { U as Upload } from "./upload-CwqN2Utd.js";
import { C as Camera } from "./camera-C09Eyogi.js";
import { F as File_image } from "./file-image-BGlN21-8.js";
import { C as Check } from "./check-DK45Y9SJ.js";
import { X } from "./x-CnxrMbMn.js";
import { T as Trash_2 } from "./trash-2-CjFp3otc.js";
import { router } from "@inertiajs/core";
import "axios";
import "clsx";
import "laravel-precognition";
import "tailwind-merge";
import "tailwind-variants";
import "./Icon-Dal7PqKK.js";
import "./circle-x-BQrhluGu.js";
import "./triangle-alert-CUvuB-HL.js";
import "./info-C3MCzlQW.js";
import "./zoom-out-DsJCISKt.js";
import "./loader-circle-_UEl10Lx.js";
import "./eye-off-Bx_raPQb.js";
import "./save-DAlMQk-Q.js";
import "./layers-_Ie6y7G_.js";
import "./chevron-down-CkQQZIhu.js";
import "./chevron-up-lhASJLRD.js";
import "./circle-alert-jWXJ7oC0.js";
import "./flask-conical-CPKNi8ld.js";
import "./circle-check-C59ZS7Zv.js";
import "./shield-check-CLSg-ako.js";
import "./arrow-right-CzynHiIo.js";
import "./clipboard-BXuTlHdz.js";
import "./dialog-description-D12L0QA9.js";
import "./noop-BQS7OFs8.js";
import "./create-id-CUJmDlea.js";
import "style-to-object";
import "./scroll-lock-B1ibcKMx.js";
import "./watch.svelte-BcPoqckX.js";
import "./is-C6cm64pF.js";
import "tabbable";
import "./dialog-footer-kNnwKT2h.js";
import "./Link-B7ks7rBK.js";
import "./circle-question-mark-DY-RU614.js";
import "./ellipsis-CPRDNN0k.js";
import "./list-DlzkoO4B.js";
function Capture($$renderer, $$props) {
  $$renderer.component(($$renderer2) => {
    let {
      page_state = "empty",
      camera_enabled = true,
      provider = { name: "nexiam.fake", version: "1", synthetic: true },
      material = null,
      applicants = [],
      session = {},
      template = { identity_region: null, items: [] },
      batch = null,
      run_status = null,
      reproduction_authorizations = [],
      review_urls = {},
      workbench_projection = null,
      qr_disabled_issuances = [],
      supplier_stock_allocations = [],
      roster = []
    } = $$props;
    function productionWorkbenchUrls(sessionId, batchId) {
      const prefix = `/grading/sessions/${encodeURIComponent(String(sessionId))}/omr/batches/${encodeURIComponent(String(batchId))}/workbench`;
      return {
        show: prefix,
        capture: `${prefix}/capture`,
        pendingCapture: `${prefix}/pending-capture`,
        adoptPendingCapture: `${prefix}/pending-capture/adopt`,
        confirmPendingIdentity: `${prefix}/pending-capture/identity`,
        retry: (sheetId) => `${prefix}/sheets/${encodeURIComponent(String(sheetId))}/retry`,
        review: (sheetId) => `${prefix}/sheets/${encodeURIComponent(String(sheetId))}/review`,
        resolveApplicant: (sheetId) => `${prefix}/sheets/${encodeURIComponent(String(sheetId))}/resolve-applicant`,
        resolveDuplicate: (sheetId) => `${prefix}/sheets/${encodeURIComponent(String(sheetId))}/resolve-duplicate`,
        complete: `${prefix}/complete`
      };
    }
    function matchingProductionProjection(value, sessionId, batchId) {
      if (sessionId < 1 || batchId < 1 || !isOmrWorkbenchProjection(value) || value.execution_kind !== "production" || value.workbench_id !== `production:${sessionId}:${batchId}`) {
        return null;
      }
      const context = value.context;
      if (context.execution_kind !== "production" || context.session_id !== sessionId || context.run_id !== batchId || context.session?.id !== sessionId || context.run?.id !== batchId) {
        return null;
      }
      return value;
    }
    let screen = "camera";
    let mode = "auto";
    let cameraState = camera_enabled ? "requesting" : "unavailable";
    let activeTab = "draft";
    let selectedSheetId = null;
    let activeReviewPage = 1;
    let desktopLayout = "split";
    let videoEl = null;
    let cameraStream = null;
    let cameraUnavailableReason = camera_enabled ? null : "device";
    let settingsOpen = false;
    let selectedDeviceId = "";
    let cameraDevices = [];
    let failedImageIds = /* @__PURE__ */ new Set();
    let currentPage = 1;
    let uploadManifest = [];
    let batchUploading = false;
    newIdempotencyKey();
    let confirmingBatch = false;
    let exceptionAssignmentId = "";
    let exceptionReason = "";
    let resolvingDuplicate = false;
    let duplicateReason = "";
    let manualQrState = "";
    let manualDisplayCode = "";
    let manualIdentityConfirmed = false;
    let reproductionDisplayCode = "";
    let reproductionPageNo = 1;
    let reproductionQuantity = 1;
    let reproductionReason = "";
    let reproductionExpiry = "";
    const itemReviewParentVersions = /* @__PURE__ */ new Map();
    let preflightState = "idle";
    let viewfinderResult = null;
    let viewfinderAvailable = true;
    let videoWidth = 0;
    let videoHeight = 0;
    const viewfinder = createOmrViewfinderSession({
      video: () => videoEl,
      onResult: (result) => {
        viewfinderResult = result;
        if (result && (!result.quad || !result.gates.in_bounds || !result.gates.angle_ok || !result.gates.lighting_ok)) ;
      },
      onUnavailable: () => {
        viewfinderAvailable = false;
      },
      onCapture: async ({ quad }) => {
        captureTargetKey();
        const succeeded = await captureFrame();
        return succeeded;
      }
    });
    let evidenceRequestGeneration = 0;
    let evidencePage = null;
    let evidenceArea = "all";
    let evidenceFilter = null;
    let evidencePageNo = 1;
    let evidencePerPage = 25;
    let evidenceReviewDirty = false;
    let mobileEvidenceExpanded = false;
    let evidenceReviewContext = null;
    let operationalStatus = run_status;
    let runConnection = {
      state: batch ? "connecting" : "live"
    };
    const initialProductionProjection = matchingProductionProjection(workbench_projection, Number(session?.id ?? 0), Number(batch?.id ?? 0));
    let productionProjection = initialProductionProjection;
    let productionProjectionLoading = false;
    let productionProjectionError = null;
    let productionWorkbenchClient = null;
    let productionCommandBusy = false;
    let pendingCaptureBusy = false;
    let pendingCaptureError = "";
    let selectedQrDisabledIssuanceId = "";
    newIdempotencyKey();
    let pendingRetake = null;
    const evidenceReviewable = derived(() => evidencePage?.reviewable === true && evidenceReviewContext?.sheetId != null);
    const pageCount = derived(() => Number(batch?.template?.page_count ?? batch?.expected_page_count ?? 1));
    const qrDisabledMode = derived(() => material?.qr_mode === "qr_disabled");
    const isUploadedStockMode = derived(() => material?.source_kind === "uploaded_original");
    const qrDisabledIssuances = derived(() => isUploadedStockMode() ? Array.isArray(supplier_stock_allocations) ? supplier_stock_allocations : [] : Array.isArray(qr_disabled_issuances) ? qr_disabled_issuances : []);
    const selectedQrDisabledIssuance = derived(() => qrDisabledIssuances().find((issuance) => String(issuance.id) === selectedQrDisabledIssuanceId) ?? null);
    const pendingCapture = derived(() => qrDisabledMode() ? productionProjection?.context?.pending_capture ?? null : null);
    const hasOpenQrDisabledCapture = derived(() => qrDisabledMode() && pendingCapture() !== null && pendingCapture().adopted_sheet_id == null);
    const pendingCapturePageNo = derived(() => pendingRetake?.pageNo ?? pendingCapture()?.active_page_no ?? null);
    const captureTargetKey = derived(() => qrDisabledMode() ? `${pendingCapture()?.id ?? ""}:${pendingCapture()?.revision ?? ""}:${pendingCapturePageNo() ?? ""}` : `${batch?.id ?? ""}:${currentPage}`);
    const pendingPageHasCapture = derived(() => pendingCapture()?.pages?.some((page) => Number(page.page_no) === Number(pendingCapturePageNo()) && Number(page.capture_count ?? 0) > 0) ?? false);
    const sheets = derived(() => batch?.sheets ?? []);
    const gallery = derived(sheets);
    const visibleScans = derived(sheets);
    const selectedSheet = derived(() => sheets().find((sheet) => sheet.id === selectedSheetId) ?? null);
    const duplicateSheet = derived(() => {
      const duplicateId = Number(selectedSheet()?.resolution?.failure?.context?.duplicate_sheet_id ?? 0);
      return duplicateId > 0 ? sheets().find((sheet) => Number(sheet.id) === duplicateId) ?? null : null;
    });
    const autoAcquisitionPaused = derived(() => mobileEvidenceExpanded);
    const reviewBusy = derived(() => ["pending_pages", "queued", "processing"].includes(selectedSheet()?.status));
    const reviewFailed = derived(() => selectedSheet()?.status === "failed_terminal");
    const reviewReady = derived(() => selectedSheet() != null && !reviewBusy() && !reviewFailed());
    function canonicalEvidenceFilter(value) {
      if (value === "mismatches") return "mismatch";
      return ["all", "mismatch", "needs_review", "reviewed"].includes(value) ? value : null;
    }
    function persistEvidenceUrlState() {
      if (typeof window === "undefined") return;
      const url = new URL(window.location.href);
      const values = {
        evidence_sheet: selectedSheet()?.id ?? evidenceReviewContext?.sheetId ?? null,
        evidence_method: evidenceReviewContext?.method ?? evidencePage?.method ?? "pre_shaded",
        evidence_area: evidenceArea !== "all" ? evidenceArea : null,
        evidence_filter: evidenceFilter,
        evidence_page: evidencePageNo > 1 ? evidencePageNo : null,
        evidence_per_page: evidencePerPage !== 25 ? evidencePerPage : null,
        evidence_tab: null
      };
      Object.entries(values).forEach(([key, value]) => {
        if (value == null || value === "") url.searchParams.delete(key);
        else url.searchParams.set(key, String(value));
      });
      window.history.replaceState(window.history.state, "", `${url.pathname}${url.search}${url.hash}`);
    }
    function productionClientFor(sessionId, batchId) {
      if (sessionId < 1 || batchId < 1) return null;
      const workbenchId = `production:${sessionId}:${batchId}`;
      if (productionWorkbenchClient?.workbenchId === workbenchId) {
        return productionWorkbenchClient;
      }
      productionWorkbenchClient = createOmrWorkbenchClient({
        workbenchId,
        executionKind: "production",
        urls: productionWorkbenchUrls(sessionId, batchId)
      });
      return productionWorkbenchClient;
    }
    function adoptProductionProjection(value) {
      const sessionId = Number(session?.id ?? 0);
      const batchId = Number(batch?.id ?? 0);
      const next = matchingProductionProjection(value, sessionId, batchId);
      if (next === null) {
        throw new Error("The production OMR workbench returned an invalid route projection.");
      }
      if (productionProjection !== null && !acceptOmrWorkbenchProjection(productionProjection, next)) {
        return productionProjection;
      }
      productionProjection = next;
      productionProjectionError = null;
      return next;
    }
    function adoptQrDisabledPendingProjection(value) {
      const sessionId = Number(session?.id ?? 0);
      const batchId = Number(batch?.id ?? 0);
      const candidate = value && typeof value === "object" && !Array.isArray(value) && value.projection && typeof value.projection === "object" ? value.projection : value;
      const next = matchingProductionProjection(candidate, sessionId, batchId);
      if (next === null) {
        throw new Error("The production OMR workbench returned an invalid route projection.");
      }
      const current = matchingProductionProjection(productionProjection, sessionId, batchId);
      const currentPending = current?.context?.pending_capture ?? null;
      const nextPending = next.context?.pending_capture ?? null;
      if (current) {
        if (next.revision < current.revision) return current;
        if (currentPending?.id && currentPending.id === nextPending?.id && Number(nextPending?.revision ?? 0) < Number(currentPending.revision ?? 0)) {
          return current;
        }
        if (next.revision === current.revision && (!currentPending && !nextPending || currentPending?.id && currentPending.id === nextPending?.id && Number(nextPending?.revision ?? 0) <= Number(currentPending.revision ?? 0) || currentPending?.id && !nextPending && !currentPending.adopted_sheet_id)) {
          return current;
        }
      }
      productionProjection = next;
      productionProjectionError = null;
      return next;
    }
    async function runProductionCommand(operation, options = {}) {
      const sessionId = Number(session?.id ?? 0);
      const batchId = Number(batch?.id ?? 0);
      const client = productionClientFor(sessionId, batchId);
      const current = matchingProductionProjection(productionProjection, sessionId, batchId);
      if (!client || !current || productionCommandBusy) {
        if (options.propagate === true) {
          throw new Error("The production OMR workbench is busy or unavailable.");
        }
        return null;
      }
      productionCommandBusy = true;
      productionProjectionError = null;
      try {
        return adoptProductionProjection(await operation(client, current));
      } catch (error2) {
        productionProjectionError = {
          detail: error2?.message ?? "The production OMR workbench command could not be completed."
        };
        if (options.propagate === true) throw error2;
        return null;
      } finally {
        productionCommandBusy = false;
      }
    }
    function pendingCaptureErrorMessage(error2, fallback) {
      const code = error2?.code ?? error2?.details?.code ?? null;
      const actionable = {
        omr_production_workbench_stale: "The production workbench changed while this page was open. Refresh the pending set before retrying.",
        omr_capture_set_run_stale: "The production workbench changed while this page was open. Refresh the pending set before retrying.",
        omr_capture_set_revision_stale: "Another page action changed this pending set. Refresh its current page and revision before retrying.",
        omr_capture_set_page_target_mismatch: "The server page target changed. Refresh the pending set and capture its current page.",
        qr_disabled_stock_exhausted: "This printed issuance has no unclaimed copies. Select another issuance or issue more printed stock.",
        qr_disabled_issuance_invalid: "This printed issuance is no longer available. Refresh the page and choose a current issuance."
      };
      if (typeof error2?.message === "string" && error2.message.trim() !== "") {
        return error2.message;
      }
      if (typeof error2?.detail === "string" && error2.detail.trim() !== "") {
        return error2.detail;
      }
      return actionable[code] ?? `${fallback}${code ? ` (${code})` : ""}`;
    }
    async function postPendingCaptureProjection(url, payload, fallback) {
      const response = await fetch(url, {
        method: "POST",
        credentials: "same-origin",
        headers: {
          ...csrfHeaders(),
          "X-Requested-With": "XMLHttpRequest",
          Accept: "application/json",
          "Content-Type": "application/json"
        },
        body: JSON.stringify(payload)
      });
      const body = await response.json().catch(() => ({}));
      if (!response.ok) {
        throw new Error(pendingCaptureErrorMessage(body, fallback));
      }
      return adoptQrDisabledPendingProjection(body);
    }
    function reloadQrDisabledBatch() {
      router.reload({
        only: [
          "batch",
          "run_status",
          "qr_disabled_issuances",
          "supplier_stock_allocations"
        ],
        preserveState: true,
        preserveScroll: true
      });
    }
    function selectQrDisabledIssuance(event) {
      const nextId = String(event.currentTarget.value ?? "");
      if (nextId === selectedQrDisabledIssuanceId) return;
      selectedQrDisabledIssuanceId = nextId;
      newIdempotencyKey();
      pendingCaptureError = "";
    }
    async function adoptQrDisabledPendingCapture() {
      const pending = pendingCapture();
      if (!pending?.id || pendingCaptureBusy || productionCommandBusy) {
        return;
      }
      pendingCaptureBusy = true;
      pendingCaptureError = "";
      try {
        await postPendingCaptureProjection(
          productionWorkbenchUrls(session.id, batch.id).adoptPendingCapture,
          {
            pending_capture_id: pending.id,
            pending_capture_revision: Number(pending.revision)
          },
          "The captured pages could not be adopted into sheet review."
        );
        pendingRetake = null;
        success("Pages adopted. The sheet is ready for review.");
        reloadQrDisabledBatch();
      } catch (error$1) {
        pendingCaptureError = pendingCaptureErrorMessage(error$1, "Adoption is still blocked. Review the recovery guidance and retry.");
        error(pendingCaptureError);
      } finally {
        pendingCaptureBusy = false;
      }
    }
    async function confirmPendingCaptureManualIdentity(selections) {
      const pending = pendingCapture();
      if (!pending?.id || !Array.isArray(selections) || pendingCaptureBusy || productionCommandBusy) {
        return;
      }
      pendingCaptureBusy = true;
      pendingCaptureError = "";
      try {
        await postPendingCaptureProjection(
          productionWorkbenchUrls(session.id, batch.id).confirmPendingIdentity,
          {
            pending_capture_id: pending.id,
            pending_capture_revision: Number(pending.revision),
            idempotency_key: newIdempotencyKey(),
            selections
          },
          "The write-in Applicant Number could not be confirmed."
        );
        success("Write-in Applicant Number confirmed.");
      } catch (error$1) {
        pendingCaptureError = pendingCaptureErrorMessage(error$1, "The write-in Applicant Number could not be confirmed.");
        error(pendingCaptureError);
      } finally {
        pendingCaptureBusy = false;
      }
    }
    function canLeavePendingCapture() {
      if (!hasOpenQrDisabledCapture() && !(qrDisabledMode() && pendingCaptureBusy)) return true;
      info(pendingCaptureBusy && qrDisabledMode() && !hasOpenQrDisabledCapture() ? "Wait for the pending capture action to finish before leaving this workbench." : "Finish and continue this pending capture set before leaving the production workbench.");
      return false;
    }
    function canLeaveWorkbench() {
      return canLeavePendingCapture() && canLeaveEvidenceReview();
    }
    function beginPendingPageRetake(pageNo, reason) {
      const page = pendingCapture()?.pages?.find((entry) => Number(entry.page_no) === Number(pageNo));
      if (!hasOpenQrDisabledCapture() || !page || Number(page.capture_count ?? 0) < 1 || !String(reason ?? "").trim()) {
        pendingCaptureError = "Choose a captured page and enter a reason before retaking it.";
        return;
      }
      pendingCaptureError = "";
      pendingRetake = { pageNo: Number(page.page_no), reason: String(reason).trim() };
      screen = "camera";
      triggerUpload();
    }
    async function fetchEvidencePage(context = {}) {
      const sheet = selectedSheet();
      if (!sheet?.id) {
        return null;
      }
      if (context.area !== void 0) evidenceArea = context.area;
      if (context.filter !== void 0) evidenceFilter = canonicalEvidenceFilter(context.filter);
      if (context.page !== void 0) evidencePageNo = Math.max(1, Number(context.page) || 1);
      if (context.perPage !== void 0) evidencePerPage = Math.min(50, Math.max(1, Number(context.perPage) || 25));
      const generation = ++evidenceRequestGeneration;
      const method = context.method ?? evidenceReviewContext?.method ?? evidencePage?.method ?? "pre_shaded";
      const params = new URLSearchParams({
        method,
        area: evidenceArea || "all",
        page: String(evidencePageNo),
        per_page: String(evidencePerPage)
      });
      if (context.captureId) params.set("capture_id", String(context.captureId));
      if (evidenceFilter) params.set("filter", evidenceFilter);
      persistEvidenceUrlState();
      try {
        const response = await fetch(`/grading/sessions/${session.id}/omr/sheets/${sheet.id}/evidence?${params.toString()}`, {
          headers: { Accept: "application/json" },
          credentials: "same-origin"
        });
        const payload = await response.json().catch(() => null);
        if (generation !== evidenceRequestGeneration) return null;
        if (!response.ok || !payload || typeof payload !== "object") throw new Error(payload?.detail ?? "The private evidence page is unavailable.");
        evidencePage = {
          ...payload,
          lazy_asset_urls: {
            ...payload.lazy_asset_urls ?? {},
            input: payload.lazy_asset_urls?.input ?? (hasImageEvidence(sheet) ? reviewImageUrl() : null),
            aligned: payload.lazy_asset_urls?.aligned ?? (hasImageEvidence(sheet) ? reviewImageUrl() : null)
          }
        };
        evidenceArea = payload.area ?? evidenceArea;
        evidenceFilter = canonicalEvidenceFilter(payload.filter ?? evidenceFilter);
        evidencePageNo = Number(payload.page ?? evidencePageNo);
        evidencePerPage = Number(payload.per_page ?? evidencePerPage);
        persistEvidenceUrlState();
        return evidencePage;
      } catch (error2) {
        if (generation === evidenceRequestGeneration) ({
          detail: error2?.message ?? "The private evidence page is unavailable."
        });
        return null;
      } finally {
      }
    }
    function canLeaveEvidenceReview() {
      if (!evidenceReviewDirty) return true;
      if (typeof window === "undefined") return false;
      const discard = window.confirm("This item review has unsaved changes. Discard them and continue?");
      if (discard) evidenceReviewDirty = false;
      return discard;
    }
    async function saveEvidenceDecision(payload, item) {
      if (!evidenceReviewable()) {
        throw new Error("This grading evidence is not reviewable.");
      }
      const sheetId = Number(item?.capture_id ?? item?.sheet_id ?? evidenceReviewContext?.sheetId ?? selectedSheet()?.id ?? 0);
      const sessionId = Number(session?.id ?? 0);
      const itemKey = productionItemReviewKey(item);
      const expectedParentVersion = itemReviewParentVersions.get(sheetId) ?? payload?.expected_parent_version ?? item?.parent_version ?? item?.version;
      const reviewPayload = buildOmrConsoleReviewPayload({ ...payload, expected_parent_version: expectedParentVersion }, item, payload?.idempotency_key ?? newIdempotencyKey());
      const response = await fetch(productionItemReviewUrl(sessionId, sheetId, itemKey), {
        method: "POST",
        headers: {
          ...csrfHeaders(),
          "Content-Type": "application/json",
          "X-Requested-With": "XMLHttpRequest",
          Accept: "application/json"
        },
        credentials: "same-origin",
        body: JSON.stringify(reviewPayload)
      });
      const body = await response.json().catch(() => ({}));
      if (!response.ok || body?.outcome !== "succeeded") {
        const code = String(body?.code ?? "");
        const detail = code ? ` (${code.replaceAll("_", " ")})` : "";
        throw new Error(`The item review was not saved${detail}. Your selection is still available to retry.`);
      }
      const review = assertProductionItemReviewAcknowledged(body, reviewPayload, sheetId, itemKey);
      itemReviewParentVersions.set(sheetId, review.parent_version);
      return { review, receipt: body.receipt };
    }
    function prepareEvidenceReview(sheet) {
      if (!sheet?.id || !canLeaveEvidenceReview()) return;
      const method = "pre_shaded";
      const sameSheet = Number(evidenceReviewContext?.sheetId) === Number(sheet.id);
      selectedSheetId = sheet.id;
      evidenceReviewContext = { sheetId: sheet.id, method };
      activeReviewPage = Number(activeReviewPage) || 1;
      if (sameSheet && evidencePage) {
        persistEvidenceUrlState();
        return;
      }
      evidenceArea = "all";
      evidenceFilter = null;
      evidencePageNo = 1;
      evidencePerPage = 25;
      evidencePage = null;
      persistEvidenceUrlState();
      queueMicrotask(() => fetchEvidencePage({ captureId: null, method: "pre_shaded" }));
    }
    function evidenceRetry() {
      fetchEvidencePage({});
    }
    function nextMissingPage(sheet) {
      const pages = sheet.pages ?? [];
      for (let no = 1; no <= Number(sheet.expected_page_count ?? 1); no += 1) {
        if (!pages.some((page) => Number(page.page_no) === no && page.captured)) return no;
      }
      return null;
    }
    function missingPageCount(sheet) {
      const capturedPages = new Set((sheet.pages ?? []).filter((page) => page.captured).map((page) => Number(page.page_no)));
      return Math.max(0, Number(sheet.expected_page_count ?? 1) - capturedPages.size);
    }
    const reviewPages = derived(() => (selectedSheet()?.pages?.length ? selectedSheet().pages : null) ?? null);
    const activeReviewPageData = derived(() => reviewPages()?.find((page) => Number(page.page_no) === activeReviewPage) ?? null);
    const reviewImageUrl = derived(() => activeReviewPageData()?.image_url ?? selectedSheet()?.image_url ?? null);
    function pageLabel(sheet, pageNo) {
      const page = (sheet.pages ?? []).find((candidate) => Number(candidate.page_no) === pageNo);
      return page?.captured ? "Captured" : "Missing";
    }
    const captureTemplate = derived(() => batch?.template ?? template ?? { identity_region: null, items: [] });
    function observedSetCode(sheet) {
      return sheet?.observed_set_code ?? sheet?.detected_set_code ?? null;
    }
    const identityExceptionOpen = derived(() => {
      const issues = selectedSheet()?.issues ?? [];
      const hasIdentityIssue = issues.some((issue) => issue.state === "open" && ["identity_unresolved", "identity_conflict"].includes(issue.issue_code));
      return Boolean(selectedSheet() && (selectedSheet().identity_exception_required === true || selectedSheet().identity_exception === true || selectedSheet().identity_resolution?.requires_exception === true || hasIdentityIssue));
    });
    function getExamineeInfo(sheet) {
      const applicantNumber = sheet?.detected_applicant_number ?? null;
      const observedSet = observedSetCode(sheet);
      const name = applicantNumber !== null ? "Applicant Number evidence" : sheet?.id ? `Scan #${sheet.id}` : "Unidentified scan";
      const code = applicantNumber !== null ? `Applicant No. ${applicantNumber}` : "Applicant Number not detected";
      const initials = applicantNumber !== null ? String(applicantNumber).slice(-2) : sheet?.id ? `#${String(sheet.id).padStart(2, "0").slice(-2)}` : "?";
      return { name, code, initials, observedSet };
    }
    function imageKey(sheet) {
      return String(sheet?.id ?? sheet?.thumbnail_url ?? "");
    }
    function imageIsPlaceholder(sheet) {
      const url = sheet?.thumbnail_url;
      return typeof url === "string" && (url.startsWith("data:image/svg") || /\.svg(?:[?#]|$)/i.test(url));
    }
    function hasImageEvidence(sheet) {
      return Boolean(sheet?.thumbnail_url && !failedImageIds.has(imageKey(sheet)) && !imageIsPlaceholder(sheet));
    }
    function evidenceLabel(sheet) {
      if (!sheet?.thumbnail_url) return "No image evidence";
      if (imageIsPlaceholder(sheet)) return "Preview placeholder";
      if (failedImageIds.has(imageKey(sheet))) return "Image unavailable";
      return "Captured image";
    }
    function isRegion(value) {
      return Boolean(value && typeof value === "object" && Number(value.w) > 0 && Number(value.h) > 0);
    }
    const pageIdentityRegions = derived(() => captureTemplate().identity_regions?.[String(activeReviewPage)] ?? null);
    const nameRegion = derived(() => isRegion(pageIdentityRegions()?.name) ? pageIdentityRegions().name : null);
    function confidenceLabel(value) {
      return value == null ? "Unqualified" : `${Math.round(value * 100)}%`;
    }
    function deviceLabel(device, index) {
      if (!device.facing) return device.label || `Camera ${index + 1}`;
      const cameraNumber = cameraDevices.slice(0, index + 1).filter((candidate) => candidate.facing === device.facing).length;
      return `${device.facing === "front" ? "Front" : "Back"} camera ${cameraNumber}`;
    }
    function stopCamera() {
      viewfinder.stop();
      cameraStream?.getTracks().forEach((track) => track.stop());
      cameraStream = null;
      cameraState = "idle";
    }
    function triggerUpload() {
      if (qrDisabledMode()) {
        if (!pendingCapture() || pendingCapture().adopted_sheet_id) {
          info("Select a printed issuance and start its pending capture before uploading an image.");
          return;
        }
        if (pendingCaptureBusy || productionCommandBusy) {
          info("Wait for the current pending capture action to finish.");
          return;
        }
        if (!pendingRetake && pendingPageHasCapture()) {
          info("The server has already captured this page. Use its reasoned Retake action to replace it.");
          return;
        }
      }
    }
    async function captureFrame(quad) {
      if (qrDisabledMode()) {
        if (!pendingCapture() || pendingCapture().adopted_sheet_id) {
          info("Select a printed issuance and start its pending capture before capturing an image.");
          return false;
        }
        if (pendingCaptureBusy || productionCommandBusy) return false;
        if (!pendingRetake && pendingPageHasCapture()) {
          info("The server has already captured this page. Use its reasoned Retake action to replace it.");
          return false;
        }
      }
      if (cameraState !== "ready" || !qrDisabledMode() && autoAcquisitionPaused() || !videoEl) {
        return false;
      }
    }
    function openReview(sheet) {
      if (!sheet?.id || !canLeavePendingCapture() || !canLeaveEvidenceReview()) return;
      selectedSheetId = sheet.id;
      activeReviewPage = 1;
      screen = "review";
      if (!["pending_pages", "queued", "processing", "failed_terminal"].includes(sheet.status)) {
        prepareEvidenceReview(sheet);
      }
    }
    function selectWorkbenchSheet(projectedSheet) {
      const sheet = sheets().find((candidate) => Number(candidate.id) === Number(projectedSheet?.id));
      if (sheet) openReview(sheet);
    }
    async function workbenchRetry(context = null) {
      if (!canLeavePendingCapture()) return;
      const processingRetry = context?.reason === "processing" || context?.sheet_id != null || context?.sheetId != null;
      if (!processingRetry) {
        evidenceRetry();
        return;
      }
      const sheetId = Number(context?.sheet_id ?? context?.sheetId ?? selectedSheetId ?? 0);
      const sheet = sheets().find((candidate) => Number(candidate.id) === sheetId);
      if (!sheetId || !sheet) return;
      const pageNo = Number(context?.page_no ?? nextMissingPage(sheet) ?? 1);
      const next = await runProductionCommand((client, current) => client.captureRetry(
        sheetId,
        {
          sheet_id: sheetId,
          page_no: pageNo,
          idempotency_key: newIdempotencyKey()
        },
        current.revision
      ));
      if (next) {
        selectedSheetId = sheetId;
        screen = "gallery";
        success(`Scan #${sheetId} re-queued for analysis.`);
      }
    }
    async function handleWorkbenchRecovery(action, recovery) {
      if (!canLeavePendingCapture()) return;
      const supportCode = typeof recovery?.support_code === "string" ? recovery.support_code : "";
      const supportMatch = /^OMR-(\d+)-/.exec(supportCode);
      const recoverySheetId = recovery?.capture_id ?? recovery?.sheet_id ?? supportMatch?.[1] ?? selectedSheetId;
      const sheet = sheets().find((candidate) => Number(candidate.id) === Number(recoverySheetId));
      if (sheet) {
        selectedSheetId = sheet.id;
        if (action === "retry_scan" || action === "capture_retry") {
          await workbenchRetry({ reason: "processing", sheet_id: sheet.id });
          return;
        }
        if (action === "match_applicant" || action === "resolve_applicant") {
          {
            openReview(sheet);
          }
          return;
        }
        if (action === "compare_duplicates" || action === "resolve_duplicate") {
          openReview(sheet);
          return;
        }
        if (action === "enter_display_code") {
          screen = "camera";
          settingsOpen = true;
          return;
        }
      }
      const destinationUrl = recovery?.destination_url ?? sheet?.recovery?.destination_url;
      if (destinationUrl) {
        openRecoveryDestination({ ...recovery, destination_url: destinationUrl });
      }
    }
    async function completeWorkbench(command) {
      if (command !== "production_complete") return;
      if (!canLeavePendingCapture() || !canLeaveEvidenceReview()) return;
      const next = await runProductionCommand((client, current) => client.productionComplete(current.revision));
      if (next) {
        screen = "gallery";
        success("OMR scores committed to the grading session!");
      }
    }
    function openRecoveryDestination(recovery) {
      if (!canLeavePendingCapture() || !recovery?.destination_url || typeof window === "undefined") return;
      if (recovery.primary_action === "open_monitoring" && selectedSheet()) {
        const returnTo = `${window.location.pathname}?omr_sheet=${selectedSheet().id}`;
        const separator = recovery.destination_url.includes("?") ? "&" : "?";
        window.location.assign(`${recovery.destination_url}${separator}return_to=${encodeURIComponent(returnTo)}`);
        return;
      }
      window.location.assign(recovery.destination_url);
    }
    function statusLabel(status) {
      return {
        received: "Draft",
        queued: "Queued",
        processing: "Processing",
        needs_review: "Needs review",
        reviewed: "Reviewed",
        committed: "Committed",
        failed_terminal: "Failed"
      }[status] ?? status;
    }
    function runConnectionLabel() {
      if (runConnection.state === "live") return "Live";
      if (runConnection.state === "connecting") return "Connecting";
      if (runConnection.state === "stale") return "Status stale";
      return "Reconnecting";
    }
    onDestroy(() => {
      stopCamera();
      viewfinder.stop();
    });
    head("1h9qjuf", $$renderer2, ($$renderer3) => {
      $$renderer3.title(($$renderer4) => {
        $$renderer4.push(`<title>OMR capture · ${escape_html(session.exam_code ?? "Session")}</title>`);
      });
    });
    $$renderer2.push(`<div class="omr-app svelte-1h9qjuf">`);
    ToastManager($$renderer2, {});
    $$renderer2.push(`<!----> <header class="omr-topbar svelte-1h9qjuf"><button class="top-action svelte-1h9qjuf" type="button" aria-label="Back to grading session"${attr("aria-disabled", hasOpenQrDisabledCapture())}${attr("title", hasOpenQrDisabledCapture() ? "Finish and continue the pending capture set before leaving." : "Return to the grading session")}>`);
    Arrow_left($$renderer2, { size: 18 });
    $$renderer2.push(`<!----> <span class="svelte-1h9qjuf">Session</span></button> <div class="session-title svelte-1h9qjuf"><span class="svelte-1h9qjuf">OMR capture</span> <small class="svelte-1h9qjuf">${escape_html(session.exam_code ?? "—")} · ${escape_html(session.room_name ?? "—")} `);
    if (provider.synthetic) {
      $$renderer2.push("<!--[0-->");
      $$renderer2.push(`· <span class="synthetic-tag svelte-1h9qjuf"${attr("title", `Synthetic provider: ${provider.name} v${provider.version}`)}>synthetic v${escape_html(provider.version)}</span>`);
    } else {
      $$renderer2.push("<!--[-1-->");
    }
    $$renderer2.push(`<!--]--> · Production</small></div> <div class="top-actions svelte-1h9qjuf"><span class="scan-count svelte-1h9qjuf">${escape_html(gallery().length)} scans</span> `);
    if (batch) {
      $$renderer2.push("<!--[0-->");
      $$renderer2.push(`<span${attr_class(`run-connection ${stringify(runConnection.state)}`, "svelte-1h9qjuf")} role="status" aria-live="polite"${attr("title", "Authoritative run status")}><span class="connection-dot svelte-1h9qjuf"></span> ${escape_html(runConnectionLabel())} `);
      if (operationalStatus?.progress?.known) {
        $$renderer2.push("<!--[0-->");
        $$renderer2.push(`· ${escape_html(operationalStatus.progress.completed)}/${escape_html(operationalStatus.progress.total)}`);
      } else {
        $$renderer2.push("<!--[-1-->");
      }
      $$renderer2.push(`<!--]--></span>`);
    } else {
      $$renderer2.push("<!--[-1-->");
    }
    $$renderer2.push(`<!--]--> <div class="desktop-view-toggle svelte-1h9qjuf"><button type="button"${attr_class("toggle-btn svelte-1h9qjuf", void 0, { "active": desktopLayout === "split" })} title="Combined split workspace">`);
    Layout_grid($$renderer2, { size: 14 });
    $$renderer2.push(`<!----> <span class="svelte-1h9qjuf">Split</span></button> <button type="button"${attr_class("toggle-btn svelte-1h9qjuf", void 0, { "active": desktopLayout === "focused" })} title="Focused single panel view">`);
    Maximize_2($$renderer2, { size: 14 });
    $$renderer2.push(`<!----> <span class="svelte-1h9qjuf">Focus</span></button></div> <div class="settings-wrap svelte-1h9qjuf"><button${attr_class("icon-action svelte-1h9qjuf", void 0, { "active": settingsOpen })} type="button" aria-label="Capture settings" aria-haspopup="menu"${attr("aria-expanded", settingsOpen)}>`);
    Settings_2($$renderer2, { size: 18 });
    $$renderer2.push(`<!----></button> `);
    if (settingsOpen) {
      $$renderer2.push("<!--[0-->");
      $$renderer2.push(`<button class="settings-backdrop svelte-1h9qjuf" type="button" aria-label="Close capture settings"></button> <div class="settings-menu svelte-1h9qjuf" role="menu" aria-label="Capture settings"><div class="settings-menu-heading svelte-1h9qjuf"><p class="settings-menu-title svelte-1h9qjuf">Camera source · ${escape_html(cameraDevices.length)}</p> <button class="settings-refresh svelte-1h9qjuf" type="button" aria-label="Refresh camera list">`);
      Refresh_cw($$renderer2, { size: 13 });
      $$renderer2.push(`<!----></button></div> `);
      if (cameraDevices.length > 0) {
        $$renderer2.push("<!--[0-->");
        $$renderer2.push(`<!--[-->`);
        const each_array = ensure_array_like(cameraDevices);
        for (let index = 0, $$length = each_array.length; index < $$length; index++) {
          let device = each_array[index];
          $$renderer2.push(`<button type="button" role="menuitemradio"${attr("aria-checked", selectedDeviceId === device.id)}${attr("disabled", !device.selectable, true)}${attr_class("svelte-1h9qjuf", void 0, { "selected": selectedDeviceId === device.id })}><span class="svelte-1h9qjuf"><strong class="svelte-1h9qjuf">${escape_html(deviceLabel(device, index))}</strong><small class="svelte-1h9qjuf">${escape_html(device.label || `Camera ${index + 1}`)}${escape_html(device.selectable ? "" : " · grant camera access to select")}</small></span> `);
          if (selectedDeviceId === device.id) {
            $$renderer2.push("<!--[0-->");
            Check($$renderer2, { size: 14 });
          } else {
            $$renderer2.push("<!--[-1-->");
          }
          $$renderer2.push(`<!--]--></button>`);
        }
        $$renderer2.push(`<!--]-->`);
      } else {
        $$renderer2.push("<!--[-1-->");
        $$renderer2.push(`<div class="menu-hint-box svelte-1h9qjuf">No browser camera devices were reported. Check
                                permission and connect a camera, then refresh.</div>`);
      }
      $$renderer2.push(`<!--]--> <p class="settings-menu-title svelte-1h9qjuf">External device hints</p> <div class="menu-hint-box svelte-1h9qjuf">Use any listed USB webcam, Bluetooth document
                            scanner, or rear multi-lens phone camera.</div> `);
      if (!qrDisabledMode()) {
        $$renderer2.push("<!--[0-->");
        $$renderer2.push(`<p class="settings-menu-title svelte-1h9qjuf">QR recovery</p> <div class="menu-hint-box svelte-1h9qjuf">Use only when the QR is absent or unreadable. A
                                present but invalid signed QR cannot be
                                overridden.</div> <label class="settings-field svelte-1h9qjuf"><span class="svelte-1h9qjuf">QR state</span> `);
        $$renderer2.select(
          { value: manualQrState, class: "" },
          ($$renderer3) => {
            $$renderer3.option(
              { value: "", class: "" },
              ($$renderer4) => {
                $$renderer4.push(`No manual recovery`);
              },
              "svelte-1h9qjuf"
            );
            $$renderer3.option(
              { value: "absent", class: "" },
              ($$renderer4) => {
                $$renderer4.push(`QR absent`);
              },
              "svelte-1h9qjuf"
            );
            $$renderer3.option(
              { value: "undecodable", class: "" },
              ($$renderer4) => {
                $$renderer4.push(`QR unreadable`);
              },
              "svelte-1h9qjuf"
            );
          },
          "svelte-1h9qjuf"
        );
        $$renderer2.push(`</label> <label class="settings-field svelte-1h9qjuf"><span class="svelte-1h9qjuf">Printed Display Code</span> <input${attr("value", manualDisplayCode)} maxlength="24" autocomplete="off" placeholder="Enter code below the QR" class="svelte-1h9qjuf"/></label> <label class="settings-confirmation svelte-1h9qjuf"><input type="checkbox"${attr("checked", manualIdentityConfirmed, true)} class="svelte-1h9qjuf"/> <span class="svelte-1h9qjuf">I confirm the printed Display Code</span></label> <p class="settings-menu-title svelte-1h9qjuf">Emergency copy authorization</p> <div class="menu-hint-box svelte-1h9qjuf">For a unique sheet already claimed once.
                                Authorization is limited to this exam session,
                                source page, quantity, and expiry; all
                                signature, template, applicant, session, and
                                one-score checks remain active.</div> <label class="settings-field svelte-1h9qjuf"><span class="svelte-1h9qjuf">Source Display Code</span> <input${attr("value", reproductionDisplayCode)} maxlength="40" autocomplete="off" placeholder="NI-…" class="svelte-1h9qjuf"/></label> <div class="settings-inline-fields svelte-1h9qjuf"><label class="settings-field svelte-1h9qjuf"><span class="svelte-1h9qjuf">Page</span> <input${attr("value", reproductionPageNo)} type="number" min="1" class="svelte-1h9qjuf"/></label> <label class="settings-field svelte-1h9qjuf"><span class="svelte-1h9qjuf">Copies</span> <input${attr("value", reproductionQuantity)} type="number" min="1" max="20" class="svelte-1h9qjuf"/></label></div> <label class="settings-field svelte-1h9qjuf"><span class="svelte-1h9qjuf">Expires at (optional)</span> <input${attr("value", reproductionExpiry)} type="datetime-local" class="svelte-1h9qjuf"/></label> <label class="settings-field svelte-1h9qjuf"><span class="svelte-1h9qjuf">Reason</span> <input${attr("value", reproductionReason)} minlength="8" maxlength="500" placeholder="Why an emergency copy is required" class="svelte-1h9qjuf"/></label> <button type="button"${attr("disabled", !reproductionDisplayCode, true)} class="svelte-1h9qjuf"><span class="svelte-1h9qjuf"><strong class="svelte-1h9qjuf">${escape_html("Authorize emergency copies")}</strong><small class="svelte-1h9qjuf">Audited and reconciled as captures
                                        arrive</small></span></button> `);
        if (reproduction_authorizations.length > 0) {
          $$renderer2.push("<!--[0-->");
          $$renderer2.push(`<p class="settings-menu-title svelte-1h9qjuf">Recent authorizations</p> <!--[-->`);
          const each_array_1 = ensure_array_like(reproduction_authorizations.slice(0, 3));
          for (let $$index_1 = 0, $$length = each_array_1.length; $$index_1 < $$length; $$index_1++) {
            let authorization = each_array_1[$$index_1];
            $$renderer2.push(`<div class="menu-hint-box svelte-1h9qjuf"><strong class="svelte-1h9qjuf">${escape_html(authorization.display_code)} · page ${escape_html(authorization.page_no)}</strong><br class="svelte-1h9qjuf"/> ${escape_html(authorization.used_claims)}/${escape_html(authorization.maximum_claims)}
                                        claimed · ${escape_html(authorization.status)}</div>`);
          }
          $$renderer2.push(`<!--]-->`);
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
    $$renderer2.push(`<!--]--></div></div></header> `);
    if (!batch) {
      $$renderer2.push("<!--[0-->");
      if (material) {
        $$renderer2.push("<!--[1-->");
        $$renderer2.push(`<section class="start-screen svelte-1h9qjuf" aria-label="Initializing OMR capture"><div class="start-card svelte-1h9qjuf">`);
        Refresh_cw($$renderer2, { size: 28, class: "spin text-primary" });
        $$renderer2.push(`<!----> <h1 class="svelte-1h9qjuf">Initializing scanner…</h1> <p class="svelte-1h9qjuf">Opening batch for <strong class="svelte-1h9qjuf">${escape_html(material.booklet_label)}</strong>.</p></div></section>`);
      } else {
        $$renderer2.push("<!--[-1-->");
        $$renderer2.push(`<section class="start-screen svelte-1h9qjuf" aria-label="No published OMR material"><div class="start-card svelte-1h9qjuf">`);
        Scan_line($$renderer2, { size: 28 });
        $$renderer2.push(`<!----> <div class="empty-gallery svelte-1h9qjuf"><strong class="svelte-1h9qjuf">No published OMR material</strong> <span class="svelte-1h9qjuf">Return to the Exam Session and publish a finalized
                            Booklet before capture begins.</span></div></div></section>`);
      }
      $$renderer2.push(`<!--]-->`);
    } else {
      $$renderer2.push("<!--[-1-->");
      $$renderer2.push(`<input class="file-input svelte-1h9qjuf" type="file"${attr("accept", qrDisabledMode() ? "image/jpeg,image/png,image/webp" : "image/jpeg,image/png,image/webp,application/pdf,image/tiff")}${attr("multiple", !qrDisabledMode(), true)}/> `);
      if (productionProjection) {
        $$renderer2.push("<!--[0-->");
        {
          let captureContent = function($$renderer3) {
            if (qrDisabledMode()) {
              $$renderer3.push("<!--[0-->");
              $$renderer3.push(`<section class="border-border bg-card mb-4 min-w-0 space-y-3 rounded-xl border p-4 shadow-xs sm:p-5 svelte-1h9qjuf" aria-label="QR-disabled printed issuance capture" data-omr-qr-disabled-pending-capture=""><div class="svelte-1h9qjuf"><p class="text-primary text-xs font-semibold tracking-[0.12em] uppercase svelte-1h9qjuf">Printed QR-disabled paper</p> <h2 class="mt-1 text-lg font-semibold tracking-tight svelte-1h9qjuf">Capture a printed issuance</h2> <p class="text-muted-foreground mt-1 max-w-3xl text-sm leading-relaxed svelte-1h9qjuf">Start the route-bound pending set before
                                    uploading. The server selects each page
                                    target; pages are paired before sheet review
                                    and no partial score is created.</p></div> `);
              if (pendingCapture()) {
                $$renderer3.push("<!--[0-->");
                OmrPendingCaptureProgress($$renderer3, {
                  pending: pendingCapture(),
                  production: true,
                  roster: roster.length ? roster : applicants,
                  busy: pendingCaptureBusy || productionCommandBusy,
                  onRetake: beginPendingPageRetake,
                  onAdopt: adoptQrDisabledPendingCapture,
                  onConfirmIdentity: confirmPendingCaptureManualIdentity
                });
                $$renderer3.push(`<!----> `);
                if (pendingCapture().adopted_sheet_id) {
                  $$renderer3.push("<!--[0-->");
                  $$renderer3.push(`<button type="button" class="bg-primary text-primary-foreground rounded-md px-3 py-2 text-sm font-semibold svelte-1h9qjuf">View adopted sheet in capture gallery</button> <div class="border-border bg-background/70 grid min-w-0 gap-3 rounded-lg border p-3 sm:grid-cols-[minmax(0,1fr)_auto] sm:items-end sm:p-4 svelte-1h9qjuf"><label class="min-w-0 text-sm font-medium svelte-1h9qjuf" for="qr-disabled-next-issuance">Next printed issuance `);
                  $$renderer3.select(
                    {
                      id: "qr-disabled-next-issuance",
                      class: "border-input bg-background text-foreground mt-1 w-full rounded-md border px-3 py-2",
                      value: selectedQrDisabledIssuanceId,
                      onchange: selectQrDisabledIssuance,
                      disabled: pendingCaptureBusy || productionCommandBusy || qrDisabledIssuances().length === 0
                    },
                    ($$renderer4) => {
                      $$renderer4.option(
                        { value: "", class: "" },
                        ($$renderer5) => {
                          $$renderer5.push(`Choose an authorized
                                                    printed issuance`);
                        },
                        "svelte-1h9qjuf"
                      );
                      $$renderer4.push(`<!--[-->`);
                      const each_array_2 = ensure_array_like(qrDisabledIssuances());
                      for (let $$index_2 = 0, $$length = each_array_2.length; $$index_2 < $$length; $$index_2++) {
                        let issuance = each_array_2[$$index_2];
                        $$renderer4.option(
                          {
                            value: String(issuance.id),
                            disabled: issuance.remaining_claims === 0,
                            class: ""
                          },
                          ($$renderer5) => {
                            $$renderer5.push(`${escape_html(issuance.issuance_code)} ·
                                                        ${escape_html(issuance.page_count)} pages
                                                        · ${escape_html(issuance.remaining_claims === null ? "reusable master" : `${issuance.remaining_claims} copies remaining`)}`);
                          },
                          "svelte-1h9qjuf"
                        );
                      }
                      $$renderer4.push(`<!--]-->`);
                    },
                    "svelte-1h9qjuf"
                  );
                  $$renderer3.push(`</label> <button type="button" class="bg-primary text-primary-foreground rounded-md px-4 py-2.5 text-sm font-semibold disabled:opacity-50 svelte-1h9qjuf"${attr("disabled", pendingCaptureBusy || productionCommandBusy || !selectedQrDisabledIssuance() || selectedQrDisabledIssuance().remaining_claims === 0, true)}>Start another pending capture</button></div>`);
                } else {
                  $$renderer3.push("<!--[-1-->");
                }
                $$renderer3.push(`<!--]-->`);
              } else {
                $$renderer3.push("<!--[-1-->");
                $$renderer3.push(`<div class="border-border bg-background/70 grid min-w-0 gap-3 rounded-lg border p-3 sm:grid-cols-[minmax(0,1fr)_auto] sm:items-end sm:p-4 svelte-1h9qjuf"><label class="min-w-0 text-sm font-medium svelte-1h9qjuf" for="qr-disabled-issuance">Printed issuance `);
                $$renderer3.select(
                  {
                    id: "qr-disabled-issuance",
                    class: "border-input bg-background text-foreground mt-1 w-full rounded-md border px-3 py-2",
                    value: selectedQrDisabledIssuanceId,
                    onchange: selectQrDisabledIssuance,
                    disabled: pendingCaptureBusy || productionCommandBusy || qrDisabledIssuances().length === 0
                  },
                  ($$renderer4) => {
                    $$renderer4.option(
                      { value: "", class: "" },
                      ($$renderer5) => {
                        $$renderer5.push(`Choose an authorized printed
                                                issuance`);
                      },
                      "svelte-1h9qjuf"
                    );
                    $$renderer4.push(`<!--[-->`);
                    const each_array_3 = ensure_array_like(qrDisabledIssuances());
                    for (let $$index_3 = 0, $$length = each_array_3.length; $$index_3 < $$length; $$index_3++) {
                      let issuance = each_array_3[$$index_3];
                      $$renderer4.option(
                        {
                          value: String(issuance.id),
                          disabled: issuance.remaining_claims === 0,
                          class: ""
                        },
                        ($$renderer5) => {
                          $$renderer5.push(`${escape_html(issuance.issuance_code)} · ${escape_html(issuance.page_count)}
                                                    pages · ${escape_html(issuance.remaining_claims === null ? "reusable master" : `${issuance.remaining_claims} copies remaining`)}`);
                        },
                        "svelte-1h9qjuf"
                      );
                    }
                    $$renderer4.push(`<!--]-->`);
                  },
                  "svelte-1h9qjuf"
                );
                $$renderer3.push(`</label> <button type="button" class="bg-primary text-primary-foreground rounded-md px-4 py-2.5 text-sm font-semibold disabled:opacity-50 svelte-1h9qjuf"${attr("disabled", pendingCaptureBusy || productionCommandBusy || !selectedQrDisabledIssuance() || selectedQrDisabledIssuance().remaining_claims === 0, true)}>${escape_html(pendingCaptureBusy ? "Starting…" : "Start pending Page 1")}</button> `);
                if (qrDisabledIssuances().length === 0) {
                  $$renderer3.push("<!--[0-->");
                  $$renderer3.push(`<p class="text-muted-foreground text-sm sm:col-span-2 svelte-1h9qjuf" role="status">No printed QR-disabled issuance is
                                            available for this session’s
                                            material. Issue and print copies
                                            before capture.</p>`);
                } else if (selectedQrDisabledIssuance()) {
                  $$renderer3.push("<!--[1-->");
                  $$renderer3.push(`<p class="text-muted-foreground text-xs sm:col-span-2 svelte-1h9qjuf">${escape_html(selectedQrDisabledIssuance().issuance_code)}
                                            · ${escape_html(selectedQrDisabledIssuance().page_count)}
                                            pages · ${escape_html(selectedQrDisabledIssuance().copy_policy.replaceAll("_", " "))} `);
                  if (selectedQrDisabledIssuance().remaining_claims !== null) {
                    $$renderer3.push("<!--[0-->");
                    $$renderer3.push(`· ${escape_html(selectedQrDisabledIssuance().remaining_claims)}
                                                of ${escape_html(selectedQrDisabledIssuance().intended_copy_count)}
                                                copies remain`);
                  } else {
                    $$renderer3.push("<!--[-1-->");
                  }
                  $$renderer3.push(`<!--]--></p>`);
                } else {
                  $$renderer3.push("<!--[-1-->");
                }
                $$renderer3.push(`<!--]--></div>`);
              }
              $$renderer3.push(`<!--]--> `);
              if (pendingCaptureError) {
                $$renderer3.push("<!--[0-->");
                $$renderer3.push(`<div class="flex flex-col gap-2 rounded-lg border border-rose-500/30 bg-rose-500/10 px-3 py-2.5 text-sm text-rose-900 sm:flex-row sm:items-center sm:justify-between dark:text-rose-200 svelte-1h9qjuf" role="alert"><p class="svelte-1h9qjuf">${escape_html(pendingCaptureError)}</p> <button type="button" class="shrink-0 rounded-md border border-current px-3 py-1.5 text-xs font-semibold svelte-1h9qjuf"${attr("disabled", productionProjectionLoading, true)}>Refresh pending status</button></div>`);
              } else {
                $$renderer3.push("<!--[-1-->");
              }
              $$renderer3.push(`<!--]--></section>`);
            } else {
              $$renderer3.push("<!--[-1-->");
            }
            $$renderer3.push(`<!--]--> <div${attr_class("omr-workspace svelte-1h9qjuf", void 0, {
              "split-mode": desktopLayout === "split",
              "camera-active": screen === "camera",
              "is-hidden": desktopLayout !== "split"
            })}><section${attr_class("capture-screen svelte-1h9qjuf", void 0, {
              "is-hidden": desktopLayout !== "split"
            })} aria-label="Camera capture"><div class="camera-stage svelte-1h9qjuf">`);
            if (cameraState === "ready") {
              $$renderer3.push("<!--[0-->");
              $$renderer3.push(`<div class="scan-hint svelte-1h9qjuf">`);
              Scan_line($$renderer3, { size: 14 });
              $$renderer3.push(`<!----> Align all four sheet
                                        corners inside the frame</div> `);
              if (cameraDevices.length > 1) {
                $$renderer3.push("<!--[0-->");
                $$renderer3.push(`<div class="camera-lens-strip svelte-1h9qjuf" role="toolbar" aria-label="Camera lenses"><!--[-->`);
                const each_array_4 = ensure_array_like(cameraDevices);
                for (let index = 0, $$length = each_array_4.length; index < $$length; index++) {
                  let device = each_array_4[index];
                  $$renderer3.push(`<button type="button"${attr_class("lens-pill-btn svelte-1h9qjuf", void 0, { "active": selectedDeviceId === device.id })}>${escape_html(deviceLabel(device, index))}</button>`);
                }
                $$renderer3.push(`<!--]--></div>`);
              } else {
                $$renderer3.push("<!--[-1-->");
              }
              $$renderer3.push(`<!--]--> <div class="frame frame-tl svelte-1h9qjuf"></div> <div class="frame frame-tr svelte-1h9qjuf"></div> <div class="frame frame-bl svelte-1h9qjuf"></div> <div class="frame frame-br svelte-1h9qjuf"></div> <video class="camera-feed svelte-1h9qjuf" autoplay="" playsinline="" muted="" aria-label="Live camera preview"></video> `);
              OmrViewfinderHud($$renderer3, {
                result: viewfinderResult,
                available: viewfinderAvailable,
                manual: mode === "manual",
                videoWidth,
                videoHeight
              });
              $$renderer3.push(`<!----> `);
              if (qrDisabledMode() && pendingCapture() && !pendingCapture().adopted_sheet_id) {
                $$renderer3.push("<!--[0-->");
                $$renderer3.push(`<div class="page-badge svelte-1h9qjuf" role="status">${escape_html(pendingRetake ? `Retake Page ${pendingCapturePageNo()}` : `Server target · Page ${pendingCapturePageNo()}`)}
                                            of ${escape_html(pendingCapture().page_count)}</div>`);
              } else if (!qrDisabledMode() && pageCount() > 1) {
                $$renderer3.push("<!--[1-->");
                $$renderer3.push(`<div class="page-badge svelte-1h9qjuf" role="status">Page ${escape_html(currentPage)} of ${escape_html(pageCount())}</div>`);
              } else {
                $$renderer3.push("<!--[-1-->");
              }
              $$renderer3.push(`<!--]--> `);
              if (qrDisabledMode() && pendingCapture()?.adopted_sheet_id) {
                $$renderer3.push("<!--[0-->");
                $$renderer3.push(`<p class="camera-caption svelte-1h9qjuf">Pending capture set adopted · view
                                            it in the gallery or choose the next
                                            printed issuance</p>`);
              } else if (qrDisabledMode() && pendingCapture()) {
                $$renderer3.push("<!--[1-->");
                $$renderer3.push(`<p class="camera-caption svelte-1h9qjuf">Camera stream active · the pending
                                            set selects the page and revision ·
                                            recognition stays Unqualified</p>`);
              } else if (qrDisabledMode()) {
                $$renderer3.push("<!--[2-->");
                $$renderer3.push(`<p class="camera-caption svelte-1h9qjuf">Start a pending capture before
                                            scanning · the server selects each
                                            page and revision</p>`);
              } else {
                $$renderer3.push("<!--[-1-->");
                $$renderer3.push(`<p class="camera-caption svelte-1h9qjuf">Camera stream active · preflight
                                            checks the frame before upload ·
                                            recognition stays Unqualified</p>`);
              }
              $$renderer3.push(`<!--]-->`);
            } else if (cameraState === "requesting") {
              $$renderer3.push("<!--[1-->");
              $$renderer3.push(`<div class="camera-message svelte-1h9qjuf">`);
              Refresh_cw($$renderer3, { class: "spin", size: 24 });
              $$renderer3.push(`<!----><strong class="svelte-1h9qjuf">Requesting camera access…</strong><small class="svelte-1h9qjuf">Allow camera access to capture in
                                            place.</small></div>`);
            } else if (cameraState === "uploading") {
              $$renderer3.push("<!--[2-->");
              $$renderer3.push(`<div class="camera-message svelte-1h9qjuf">`);
              Upload($$renderer3, { size: 28, class: "muted-stage-icon" });
              $$renderer3.push(`<!----><strong class="svelte-1h9qjuf">Uploading ${escape_html(qrDisabledMode() ? `Page ${pendingCapturePageNo()} of ${pendingCapture()?.page_count ?? pageCount()}` : `page ${currentPage} of ${pageCount()}`)}…</strong><small class="svelte-1h9qjuf">Keep this window open while the
                                            scan is analyzed.</small></div>`);
            } else {
              $$renderer3.push("<!--[-1-->");
              $$renderer3.push(`<div class="camera-message svelte-1h9qjuf">`);
              Camera($$renderer3, { size: 32, class: "muted-stage-icon" });
              $$renderer3.push(`<!----> <strong class="svelte-1h9qjuf">${escape_html(cameraUnavailableReason === "permission" ? "Camera permission unavailable" : "Camera device unavailable")}</strong> <small class="svelte-1h9qjuf">${escape_html(cameraUnavailableReason === "permission" ? "Allow camera access in the browser, or use file upload." : "Connect a camera or use file upload instead.")}</small> <button class="primary-button stage-upload-btn svelte-1h9qjuf" type="button">`);
              Upload($$renderer3, { size: 15 });
              $$renderer3.push(`<!----> Upload image file</button></div>`);
            }
            $$renderer3.push(`<!--]--> `);
            if (!qrDisabledMode()) {
              $$renderer3.push("<!--[0-->");
              {
                $$renderer3.push("<!--[-1-->");
              }
              $$renderer3.push(`<!--]-->`);
            } else {
              $$renderer3.push("<!--[-1-->");
            }
            $$renderer3.push(`<!--]--></div> <div class="capture-controls svelte-1h9qjuf"><button class="control-side svelte-1h9qjuf" type="button" aria-label="Open scans gallery">`);
            Image($$renderer3, { size: 23 });
            $$renderer3.push(`<!----><span class="svelte-1h9qjuf">Gallery</span></button> <button class="shutter svelte-1h9qjuf" type="button"${attr("aria-label", qrDisabledMode() ? pendingRetake ? `Retake page ${pendingCapturePageNo()}` : pendingCapture()?.adopted_sheet_id ? "Pending set adopted; start another capture" : pendingCapture() ? `Capture page ${pendingCapture().active_page_no} of ${pendingCapture().page_count}` : "Start a pending capture before capturing" : pageCount() > 1 ? `Capture page ${currentPage} of ${pageCount()}` : "Capture sheet")}${attr("disabled", cameraState !== "ready" || !qrDisabledMode() && (autoAcquisitionPaused() || preflightState === "rejected" || preflightState === "error") || qrDisabledMode() && (!pendingCapture() || pendingCapture().adopted_sheet_id || pendingCaptureBusy || productionCommandBusy || !pendingRetake && pendingPageHasCapture()), true)}><span class="svelte-1h9qjuf">`);
            Camera($$renderer3, { size: 25 });
            $$renderer3.push(`<!----></span></button> <div class="mode-toggle svelte-1h9qjuf" role="group" aria-label="Capture mode"><button type="button"${attr_class("svelte-1h9qjuf", void 0, { "active": mode === "manual" })}>Manual</button> <button type="button"${attr_class("svelte-1h9qjuf", void 0, { "active": mode === "auto" })}>Auto</button></div></div> <div class="camera-footer svelte-1h9qjuf">`);
            if (qrDisabledMode() && pendingCapture()?.adopted_sheet_id) {
              $$renderer3.push("<!--[0-->");
              $$renderer3.push(`<span class="page-step svelte-1h9qjuf" role="status">Pending set adopted · choose another
                                        issuance or review the sheet</span>`);
            } else if (qrDisabledMode() && pendingCapture()) {
              $$renderer3.push("<!--[1-->");
              $$renderer3.push(`<span class="page-step svelte-1h9qjuf" role="status">Server target · Page ${escape_html(pendingCapturePageNo())}
                                        of ${escape_html(pendingCapture().page_count)}</span>`);
            } else if (!qrDisabledMode() && pageCount() > 1) {
              $$renderer3.push("<!--[2-->");
              $$renderer3.push(`<span class="page-step svelte-1h9qjuf" role="status">Set page ${escape_html(currentPage)} of ${escape_html(pageCount())}</span>`);
            } else {
              $$renderer3.push("<!--[-1-->");
            }
            $$renderer3.push(`<!--]--> `);
            if (cameraState === "unavailable") {
              $$renderer3.push("<!--[0-->");
              $$renderer3.push(`<button type="button" class="text-button svelte-1h9qjuf">`);
              Refresh_cw($$renderer3, { size: 14 });
              $$renderer3.push(`<!----> Retry camera connection</button>`);
            } else {
              $$renderer3.push("<!--[-1-->");
              $$renderer3.push(`<button type="button" class="text-button svelte-1h9qjuf">`);
              File_image($$renderer3, { size: 14 });
              $$renderer3.push(`<!----> ${escape_html(qrDisabledMode() ? "Upload one page image" : "Upload file(s)")}</button>`);
            }
            $$renderer3.push(`<!--]--></div> `);
            if (!qrDisabledMode() && uploadManifest.length > 0) {
              $$renderer3.push("<!--[0-->");
              $$renderer3.push(`<section class="upload-manifest svelte-1h9qjuf" aria-label="Batch upload manifest"><div class="manifest-heading svelte-1h9qjuf"><div class="svelte-1h9qjuf"><strong class="svelte-1h9qjuf">Batch upload</strong> <span class="svelte-1h9qjuf">${escape_html(uploadManifest.length)} files · sequential
                                                backpressure</span></div> `);
              {
                $$renderer3.push("<!--[-1-->");
              }
              $$renderer3.push(`<!--]--></div> <div class="manifest-list svelte-1h9qjuf"><!--[-->`);
              const each_array_6 = ensure_array_like(uploadManifest);
              for (let $$index_6 = 0, $$length = each_array_6.length; $$index_6 < $$length; $$index_6++) {
                let entry = each_array_6[$$index_6];
                $$renderer3.push(`<div class="manifest-row svelte-1h9qjuf"><div class="manifest-copy svelte-1h9qjuf"><strong class="svelte-1h9qjuf">${escape_html(entry.name)}</strong> <span class="svelte-1h9qjuf">Page ${escape_html(entry.pageNo)} · ${escape_html(entry.state.replaceAll("_", " "))}</span> `);
                if (entry.error) {
                  $$renderer3.push("<!--[0-->");
                  $$renderer3.push(`<small class="svelte-1h9qjuf">${escape_html(entry.error)}</small>`);
                } else {
                  $$renderer3.push("<!--[-1-->");
                }
                $$renderer3.push(`<!--]--></div> <div class="manifest-progress svelte-1h9qjuf"><span${attr_style(`width: ${entry.progress}%`)} class="svelte-1h9qjuf"></span></div> `);
                if (entry.state === "failed") {
                  $$renderer3.push("<!--[0-->");
                  $$renderer3.push(`<button type="button" class="text-button svelte-1h9qjuf"${attr("disabled", batchUploading, true)}>Retry</button>`);
                } else {
                  $$renderer3.push("<!--[-1-->");
                }
                $$renderer3.push(`<!--]--></div>`);
              }
              $$renderer3.push(`<!--]--></div></section>`);
            } else {
              $$renderer3.push("<!--[-1-->");
            }
            $$renderer3.push(`<!--]--></section></div>`);
          }, selectionContent = function($$renderer3) {
            if (screen === "review") {
              $$renderer3.push("<!--[0-->");
              $$renderer3.push(`<section class="review-screen svelte-1h9qjuf" aria-label="Sheet review">`);
              if (reviewBusy()) {
                $$renderer3.push("<!--[0-->");
                $$renderer3.push(`<div class="review-busy svelte-1h9qjuf" role="status" aria-live="polite">`);
                Refresh_cw($$renderer3, { size: 16, class: "spin" });
                $$renderer3.push(`<!----> <div class="svelte-1h9qjuf"><strong class="svelte-1h9qjuf">Analysis in progress</strong> <span class="svelte-1h9qjuf">This scan is ${escape_html(statusLabel(selectedSheet()?.status))}. The review below refreshes
                                            automatically when analysis
                                            completes.</span></div></div>`);
              } else if (reviewFailed()) {
                $$renderer3.push("<!--[1-->");
                $$renderer3.push(`<div class="review-failed svelte-1h9qjuf" role="alert">`);
                X($$renderer3, { size: 16 });
                $$renderer3.push(`<!----> <div class="svelte-1h9qjuf"><strong class="svelte-1h9qjuf">Analysis failed</strong> <span class="svelte-1h9qjuf">No answer evidence is available for
                                            this scan. Retry the scan or replace
                                            its page.</span></div> <button type="button" class="table-edit svelte-1h9qjuf"${attr("disabled", confirmingBatch, true)}>`);
                Refresh_cw($$renderer3, { size: 13 });
                $$renderer3.push(`<!----> Retry scan</button></div>`);
              } else {
                $$renderer3.push("<!--[-1-->");
              }
              $$renderer3.push(`<!--]--> `);
              if (hasImageEvidence(selectedSheet()) && reviewReady() && nameRegion()) {
                $$renderer3.push("<!--[0-->");
                $$renderer3.push(`<div class="review-card name-card svelte-1h9qjuf"><div class="review-card-heading svelte-1h9qjuf"><h2 class="svelte-1h9qjuf">Handwritten name</h2></div> <div class="name-crop-wrap svelte-1h9qjuf"><!---->`);
                {
                  OmrRegionCrop($$renderer3, {
                    imageUrl: reviewImageUrl(),
                    region: nameRegion(),
                    alt: "Cropped handwritten name evidence",
                    width: 240
                  });
                }
                $$renderer3.push(`<!----> <p class="review-note svelte-1h9qjuf">Confirm the physical handwritten
                                            name against the session roster.</p></div></div>`);
              } else {
                $$renderer3.push("<!--[-1-->");
              }
              $$renderer3.push(`<!--]--> `);
              if (selectedSheet()?.recovery?.primary_action === "compare_duplicates" && duplicateSheet()) {
                $$renderer3.push("<!--[0-->");
                $$renderer3.push(`<div class="review-card duplicate-disposition-card svelte-1h9qjuf" aria-labelledby="duplicate-disposition-title"><div class="review-card-heading svelte-1h9qjuf"><div class="svelte-1h9qjuf"><h2 id="duplicate-disposition-title" class="svelte-1h9qjuf">Choose the scoring evidence</h2> <p class="svelte-1h9qjuf">Compare both preserved captures,
                                                then record which scan survives.</p></div></div> <div class="duplicate-compare svelte-1h9qjuf"><div class="svelte-1h9qjuf"><strong class="svelte-1h9qjuf">Earlier scan #${escape_html(duplicateSheet().id)}</strong> <span class="svelte-1h9qjuf">${escape_html(duplicateSheet().detected_applicant_number ?? "Applicant Number unresolved")}
                                                · ${escape_html(confidenceLabel(duplicateSheet().confidence))}</span></div> <div class="svelte-1h9qjuf"><strong class="svelte-1h9qjuf">Current scan #${escape_html(selectedSheet().id)}</strong> <span class="svelte-1h9qjuf">${escape_html(selectedSheet().detected_applicant_number ?? "Applicant Number unresolved")}
                                                · ${escape_html(confidenceLabel(selectedSheet().confidence))}</span></div></div> <label class="abstain-field svelte-1h9qjuf"><span class="svelte-1h9qjuf">Disposition reason</span> <textarea minlength="8" maxlength="500" placeholder="Record why this capture is the approved scoring evidence" class="svelte-1h9qjuf">`);
                const $$body = escape_html(duplicateReason);
                if ($$body) {
                  $$renderer3.push(`${$$body}`);
                }
                $$renderer3.push(`</textarea></label> <div class="recovery-actions svelte-1h9qjuf"><button class="primary-button svelte-1h9qjuf" type="button"${attr("disabled", resolvingDuplicate, true)}>Keep earlier scan</button> <button class="outline-button svelte-1h9qjuf" type="button"${attr("disabled", resolvingDuplicate, true)}>Keep current scan</button></div></div>`);
              } else {
                $$renderer3.push("<!--[-1-->");
              }
              $$renderer3.push(`<!--]--> `);
              if (identityExceptionOpen()) {
                $$renderer3.push("<!--[0-->");
                $$renderer3.push(`<div class="review-card svelte-1h9qjuf"><div class="review-card-heading svelte-1h9qjuf"><h2 class="svelte-1h9qjuf">Applicant exception</h2></div> <div class="identity-exception-form svelte-1h9qjuf"><label class="svelte-1h9qjuf"><span class="svelte-1h9qjuf">Session examinee assignment</span> `);
                $$renderer3.select(
                  { value: exceptionAssignmentId, class: "" },
                  ($$renderer4) => {
                    $$renderer4.option(
                      { value: null, class: "" },
                      ($$renderer5) => {
                        $$renderer5.push(`Select an eligible examinee`);
                      },
                      "svelte-1h9qjuf"
                    );
                    $$renderer4.push(`<!--[-->`);
                    const each_array_7 = ensure_array_like(eligibleAssignments);
                    for (let $$index_7 = 0, $$length = each_array_7.length; $$index_7 < $$length; $$index_7++) {
                      let assignment = each_array_7[$$index_7];
                      $$renderer4.option(
                        { value: assignment.id, class: "" },
                        ($$renderer5) => {
                          $$renderer5.push(`${escape_html(assignment.applicant_number ?? "No ID")} · ${escape_html(assignment.name ?? "Unassigned")}`);
                        },
                        "svelte-1h9qjuf"
                      );
                    }
                    $$renderer4.push(`<!--]-->`);
                  },
                  "svelte-1h9qjuf"
                );
                $$renderer3.push(`</label> <label class="svelte-1h9qjuf"><span class="svelte-1h9qjuf">Reason for assignment</span> <textarea minlength="8" maxlength="255" placeholder="Explain why this paper is being bound to this applicant" class="svelte-1h9qjuf">`);
                const $$body_1 = escape_html(exceptionReason);
                if ($$body_1) {
                  $$renderer3.push(`${$$body_1}`);
                }
                $$renderer3.push(`</textarea></label> <button class="primary-button svelte-1h9qjuf" type="button"${attr("disabled", !exceptionAssignmentId, true)}>${escape_html("Resolve applicant exception")}</button></div></div>`);
              } else {
                $$renderer3.push("<!--[-1-->");
              }
              $$renderer3.push(`<!--]--></section>`);
            } else {
              $$renderer3.push("<!--[-1-->");
            }
            $$renderer3.push(`<!--]-->`);
          }, queueContent = function($$renderer3) {
            if (screen === "gallery") {
              $$renderer3.push("<!--[0-->");
              $$renderer3.push(`<section class="gallery-screen svelte-1h9qjuf" aria-label="Scans gallery"><div class="section-heading svelte-1h9qjuf"><div class="svelte-1h9qjuf"><p class="eyebrow svelte-1h9qjuf">Scans</p> <h1 class="svelte-1h9qjuf">Captures gallery</h1></div> <div class="heading-actions svelte-1h9qjuf">`);
              if (batch?.status === "open" && gallery().length > 0) {
                $$renderer3.push("<!--[0-->");
                $$renderer3.push(`<button class="danger-button svelte-1h9qjuf" type="button"${attr("disabled", confirmingBatch, true)}>`);
                Trash_2($$renderer3, { size: 14 });
                $$renderer3.push(`<!----> Reject batch</button>`);
              } else {
                $$renderer3.push("<!--[-1-->");
              }
              $$renderer3.push(`<!--]--> <button class="icon-action focus-only svelte-1h9qjuf" type="button" aria-label="Close gallery">`);
              X($$renderer3, { size: 19 });
              $$renderer3.push(`<!----></button></div></div> <div class="tabs svelte-1h9qjuf" role="tablist"><button type="button" role="tab"${attr("aria-selected", activeTab === "draft")}${attr_class("svelte-1h9qjuf", void 0, { "active": activeTab === "draft" })}>Draft <b class="svelte-1h9qjuf">${escape_html(gallery().filter((sheet) => sheet.status !== "committed").length)}</b></button> <button type="button" role="tab"${attr("aria-selected", activeTab === "all")}${attr_class("svelte-1h9qjuf", void 0, { "active": activeTab === "all" })}>All <b class="svelte-1h9qjuf">${escape_html(gallery().length)}</b></button></div> `);
              if (operationalStatus?.needs_attention?.length > 0) {
                $$renderer3.push("<!--[0-->");
                $$renderer3.push(`<section class="attention-queue svelte-1h9qjuf" aria-label="Needs attention by responsible owner"><div class="attention-heading svelte-1h9qjuf"><div class="svelte-1h9qjuf"><strong class="svelte-1h9qjuf">Needs attention</strong> <span class="svelte-1h9qjuf">Repair one blocked sheet
                                                without stopping the rest of the
                                                run.</span></div> <b class="svelte-1h9qjuf">${escape_html(operationalStatus.counts.needs_review + operationalStatus.counts.failed)}</b></div> <div class="attention-groups svelte-1h9qjuf"><!--[-->`);
                const each_array_8 = ensure_array_like(operationalStatus.needs_attention);
                for (let $$index_8 = 0, $$length = each_array_8.length; $$index_8 < $$length; $$index_8++) {
                  let group = each_array_8[$$index_8];
                  $$renderer3.push(`<button type="button" class="svelte-1h9qjuf"><span class="svelte-1h9qjuf"><strong class="svelte-1h9qjuf">${escape_html(group.owner)}</strong><small class="svelte-1h9qjuf">${escape_html(group.count)} sheet${escape_html(group.count === 1 ? "" : "s")} blocked</small></span> <b class="svelte-1h9qjuf">${escape_html(group.count)}</b></button>`);
                }
                $$renderer3.push(`<!--]--></div></section>`);
              } else {
                $$renderer3.push("<!--[-1-->");
              }
              $$renderer3.push(`<!--]--> <div class="scan-grid svelte-1h9qjuf">`);
              const each_array_9 = ensure_array_like(visibleScans());
              if (each_array_9.length !== 0) {
                $$renderer3.push("<!--[-->");
                for (let $$index_11 = 0, $$length = each_array_9.length; $$index_11 < $$length; $$index_11++) {
                  let sheet = each_array_9[$$index_11];
                  const info2 = getExamineeInfo(sheet);
                  $$renderer3.push(`<div class="scan-card svelte-1h9qjuf" role="button" tabindex="0"><div class="scan-thumb svelte-1h9qjuf">`);
                  if (hasImageEvidence(sheet)) {
                    $$renderer3.push("<!--[0-->");
                    $$renderer3.push(`<img${attr("src", sheet.thumbnail_url)}${attr("alt", info2.name)} class="scan-img-thumb svelte-1h9qjuf" loading="lazy" decoding="async" onerror="this.__e=event"/>`);
                  } else {
                    $$renderer3.push("<!--[-1-->");
                    $$renderer3.push(`<div class="mini-paper svelte-1h9qjuf"><span class="svelte-1h9qjuf">${escape_html(evidenceLabel(sheet) === "Preview placeholder" ? "PREVIEW" : "NEXIAM")}</span> <i class="svelte-1h9qjuf"></i><i class="fill svelte-1h9qjuf"></i><i class="svelte-1h9qjuf"></i><i class="svelte-1h9qjuf"></i></div>`);
                  }
                  $$renderer3.push(`<!--]--> <div class="examinee-avatar svelte-1h9qjuf"${attr("title", info2.name)}>${escape_html(info2.initials)}</div> <span${attr_class(`status-dot ${stringify(sheet.status)}`, "svelte-1h9qjuf")}${attr("title", statusLabel(sheet.status))}></span></div> <div class="scan-meta svelte-1h9qjuf"><div class="meta-header svelte-1h9qjuf"><strong${attr("title", info2.name)} class="svelte-1h9qjuf">${escape_html(info2.name)}</strong></div> <span class="examinee-ref svelte-1h9qjuf">${escape_html(info2.code)}</span> `);
                  if (info2.observedSet) {
                    $$renderer3.push("<!--[0-->");
                    $$renderer3.push(`<span class="examinee-ref svelte-1h9qjuf">Observed Set ${escape_html(info2.observedSet)}
                                                    · evidence only</span>`);
                  } else {
                    $$renderer3.push("<!--[-1-->");
                  }
                  $$renderer3.push(`<!--]--> `);
                  if (Number(sheet.expected_page_count ?? 1) > 1) {
                    $$renderer3.push("<!--[0-->");
                    $$renderer3.push(`<div class="page-chips svelte-1h9qjuf" role="list" aria-label="Sheet pages"><!--[-->`);
                    const each_array_10 = ensure_array_like(Array(Number(sheet.expected_page_count)));
                    for (let index = 0, $$length2 = each_array_10.length; index < $$length2; index++) {
                      each_array_10[index];
                      const pageNo = index + 1;
                      const page = (sheet.pages ?? []).find((candidate) => Number(candidate.page_no) === pageNo);
                      $$renderer3.push(`<span${attr_class("page-chip svelte-1h9qjuf", void 0, { "captured": page?.captured, "missing": !page?.captured })} role="listitem"${attr("title", `Page ${pageNo}: ${pageLabel(sheet, pageNo)}`)}><span class="page-chip-dot svelte-1h9qjuf"></span> P${escape_html(pageNo)}</span>`);
                    }
                    $$renderer3.push(`<!--]--></div>`);
                  } else {
                    $$renderer3.push("<!--[-1-->");
                  }
                  $$renderer3.push(`<!--]--> <div class="meta-footer svelte-1h9qjuf"><small class="svelte-1h9qjuf">${escape_html(sheet.capture_mode === "auto" ? "Auto" : "Manual")} · ${escape_html(statusLabel(sheet.status))}</small> <small class="time-stamp svelte-1h9qjuf">${escape_html(sheet.created_at ? new Date(sheet.created_at).toLocaleTimeString([], { hour: "2-digit", minute: "2-digit" }) : "just now")}</small></div> `);
                  if (sheet.status === "pending_pages" && missingPageCount(sheet) > 0) {
                    $$renderer3.push("<!--[0-->");
                    $$renderer3.push(`<div class="card-actions svelte-1h9qjuf"><button class="card-action primary svelte-1h9qjuf" type="button">`);
                    Camera($$renderer3, { size: 12 });
                    $$renderer3.push(`<!----> Resume
                                                        set</button> <!--[-->`);
                    const each_array_11 = ensure_array_like((sheet.pages ?? []).filter((page) => page.captured));
                    for (let $$index_10 = 0, $$length2 = each_array_11.length; $$index_10 < $$length2; $$index_10++) {
                      let page = each_array_11[$$index_10];
                      $$renderer3.push(`<button class="card-action svelte-1h9qjuf" type="button"${attr("title", `Retake page ${page.page_no}`)}>`);
                      Refresh_cw($$renderer3, { size: 12 });
                      $$renderer3.push(`<!----> Retake P${escape_html(page.page_no)}</button>`);
                    }
                    $$renderer3.push(`<!--]--></div>`);
                  } else {
                    $$renderer3.push("<!--[-1-->");
                  }
                  $$renderer3.push(`<!--]--> `);
                  if (sheet.status === "failed_terminal") {
                    $$renderer3.push("<!--[0-->");
                    $$renderer3.push(`<div class="card-actions svelte-1h9qjuf"><button class="card-action primary svelte-1h9qjuf" type="button">`);
                    Refresh_cw($$renderer3, { size: 12 });
                    $$renderer3.push(`<!----> Retry
                                                        scan</button></div>`);
                  } else {
                    $$renderer3.push("<!--[-1-->");
                  }
                  $$renderer3.push(`<!--]--> <div class="audit-row svelte-1h9qjuf"><span${attr_class("evidence-badge svelte-1h9qjuf", void 0, { "available": hasImageEvidence(sheet) })}>`);
                  if (hasImageEvidence(sheet)) {
                    $$renderer3.push("<!--[0-->");
                    File_image($$renderer3, { size: 11 });
                  } else {
                    $$renderer3.push("<!--[-1-->");
                    Image($$renderer3, { size: 11 });
                  }
                  $$renderer3.push(`<!--]--> ${escape_html(evidenceLabel(sheet))}</span> `);
                  if (sheet.identity_confidence != null) {
                    $$renderer3.push("<!--[0-->");
                    $$renderer3.push(`<span${attr_class("audit-pill svelte-1h9qjuf", void 0, {
                      "high-conf": sheet.identity_confidence >= 0.8,
                      "low-conf": sheet.identity_confidence < 0.8
                    })}>${escape_html(Math.round(sheet.identity_confidence * 100))}% identity match</span>`);
                  } else {
                    $$renderer3.push("<!--[-1-->");
                    $$renderer3.push(`<span class="audit-pill neutral svelte-1h9qjuf">Identity not detected</span>`);
                  }
                  $$renderer3.push(`<!--]--></div></div></div>`);
                }
              } else {
                $$renderer3.push("<!--[!-->");
                $$renderer3.push(`<div class="empty-gallery svelte-1h9qjuf">`);
                Image($$renderer3, { size: 30 });
                $$renderer3.push(`<!----> <strong class="svelte-1h9qjuf">No scans yet</strong> <span class="svelte-1h9qjuf">Capture or upload an answer sheet
                                            to start the review flow.</span> <button class="outline-button svelte-1h9qjuf" type="button">`);
                Camera($$renderer3, { size: 16 });
                $$renderer3.push(`<!----> Capture a sheet</button></div>`);
              }
              $$renderer3.push(`<!--]--></div> <button class="primary-button gallery-capture focus-only svelte-1h9qjuf" type="button">`);
              Camera($$renderer3, { size: 17 });
              $$renderer3.push(`<!----> Capture another sheet</button></section>`);
            } else {
              $$renderer3.push("<!--[-1-->");
            }
            $$renderer3.push(`<!--]-->`);
          };
          OmrWorkbench($$renderer2, {
            projection: productionProjection,
            connectionState: runConnection.state,
            evidencePage,
            selectedSheetId,
            onSelectSheet: selectWorkbenchSheet,
            onRetry: workbenchRetry,
            onReviewAction: saveEvidenceDecision,
            onRecoveryAction: handleWorkbenchRecovery,
            onComplete: completeWorkbench,
            onDirtyChange: (dirty) => evidenceReviewDirty = dirty,
            onDirtyNavigation: canLeaveWorkbench,
            captureContent,
            selectionContent,
            queueContent
          });
        }
      } else {
        $$renderer2.push("<!--[-1-->");
        $$renderer2.push(`<section class="workbench-fallback svelte-1h9qjuf" role="status" aria-live="polite">`);
        {
          $$renderer2.push("<!--[-1-->");
          $$renderer2.push(`<strong class="svelte-1h9qjuf">Production OMR workbench unavailable</strong> <p class="svelte-1h9qjuf">${escape_html(productionProjectionError?.detail ?? "The production run could not be loaded.")}</p> <button type="button" class="outline-button svelte-1h9qjuf">`);
          Refresh_cw($$renderer2, { size: 14 });
          $$renderer2.push(`<!----> Retry workbench</button>`);
        }
        $$renderer2.push(`<!--]--></section>`);
      }
      $$renderer2.push(`<!--]-->`);
    }
    $$renderer2.push(`<!--]--></div>`);
  });
}
export {
  Capture as default
};
