import { n as newIdempotencyKey, o as onDestroy, h as head, B as Button, e as escape_html, d as derived, q as success, t as tick, c as attr_class, b as attr, a as ensure_array_like, a3 as csrfToken } from "../app.js";
import { S as StaffLayout } from "./StaffLayout-CR-MZopa.js";
import { P as PageHeaderBar } from "./PageHeaderBar-DJhT5vOR.js";
import { B as Badge } from "./badge-D9Vgs7xh.js";
import { P as PlaceholderPicker, R as ResultSheetStudio } from "./PlaceholderPicker-CH_aVvK3.js";
import { router } from "@inertiajs/core";
import "axios";
import "clsx";
import "laravel-precognition";
import "tailwind-merge";
import "tailwind-variants";
import "./ToastManager-C7oO_4Jn.js";
import "./Icon-Dal7PqKK.js";
import "./circle-x-BQrhluGu.js";
import "./triangle-alert-CUvuB-HL.js";
import "./info-C3MCzlQW.js";
import "./x-CnxrMbMn.js";
import "./NotificationBell-Cgc59min.js";
import "./PreferencesMenu-CCPeta-5.js";
import "./watch.svelte-BcPoqckX.js";
import "./is-C6cm64pF.js";
import "./create-id-CUJmDlea.js";
import "style-to-object";
import "./scroll-lock-B1ibcKMx.js";
import "./noop-BQS7OFs8.js";
import "tabbable";
import "@floating-ui/dom";
import "./hidden-input-GSZDfM3n.js";
import "./InstitutionalBackdrop-BjxeRqDu.js";
import "./zoom-out-DsJCISKt.js";
import "./rotate-ccw-D6Dm_efx.js";
import "./inbox-AmUVtgA2.js";
import "./mail-DBtkjaCt.js";
import "./AvatarBadge-DQ5Qru9A.js";
import "./input-BUZAZKIK.js";
import "./dialog-description-D12L0QA9.js";
import "./circle-alert-jWXJ7oC0.js";
import "./refresh-cw-CYdk8aE0.js";
import "./camera-C09Eyogi.js";
import "./settings-C5xOKpV3.js";
import "./arrow-right-CzynHiIo.js";
import "./circle-check-C59ZS7Zv.js";
import "./dialog-footer-kNnwKT2h.js";
import "./clock-B4fXoAi9.js";
import "./Link-B7ks7rBK.js";
import "./qr-code-C5X8qcH6.js";
import "./chevron-right-BHu-PGOg.js";
import "./chevron-down-CkQQZIhu.js";
import "./printer-Bx0z1CRb.js";
import "./book-open-B6eMguqY.js";
import "./megaphone-C8QUuT5s.js";
import "./file-check-BSLEMs1I.js";
import "./shield-DTtuUihb.js";
import "./clipboard-list-YgthURdB.js";
import "./users-DWlguTa_.js";
import "./user-round-Kft0Cy_Y.js";
import "./XiamNpcDialog-DoqCSHj9.js";
/* empty css                                                        */
import "./circle-question-mark-DY-RU614.js";
import "./sparkles-DbQIaVG6.js";
import "./chevron-left-DCSOZwZl.js";
import "./arrays-CkOR4vx_.js";
import "./html-BMEe54ZQ.js";
import "./text-align-justify-CFymVZbq.js";
import "./text-align-start-Cy9u9QqD.js";
import "./minus-Dqkhcxt7.js";
import "./plus-DjyPLC7A.js";
import "./maximize-2-u72Ahym6.js";
import "./layers-_Ie6y7G_.js";
import "./lock-DAA4kTLl.js";
import "./trash-2-CjFp3otc.js";
import "./lock-open-5Nny5RV8.js";
import "./copy-D9_YDhto.js";
import "./file-text-PXEtmXm9.js";
import "./sticky-note-CKw7DId8.js";
import "./pen-line-Bqgfmrgp.js";
import "./table-2-BLgeaPqt.js";
import "./calendar-clock-D72LURiz.js";
import "./clipboard-BXuTlHdz.js";
import "./check-DK45Y9SJ.js";
import "./clock-3-CBFB3kOE.js";
const clone = (value) => JSON.parse(JSON.stringify(value));
const emptyState = () => ({ source_kind: "builder", document_asset_id: null, builder_json: null, paper_size: "a4", orientation: "portrait" });
function createNoticeDocumentStudioAdapter(initial, options = {}) {
  const transport = options.fetch ?? globalThis.fetch.bind(globalThis);
  const makeKey = options.key ?? newIdempotencyKey;
  const endpoints = initial.endpoints;
  let state = clone(initial.workspace.draft?.state ?? emptyState());
  let savedState = clone(state);
  let draft = clone(initial.workspace.draft);
  let guards = { ...initial.workspace.guards };
  let renderSha = draft?.render_sha256 ?? initial.workspace.render_sha256;
  let generation = 0;
  let lifecycle = 0;
  let previewSequence = 0;
  let loadSequence = 0;
  let status = draft ? "saved" : "idle";
  let error = null;
  let previewResult = null;
  let unknown = null;
  let disposed = false;
  let timer;
  let writeTail = Promise.resolve();
  let saveJob = null;
  let requestedStage = draft?.stage ?? 2;
  const listeners = /* @__PURE__ */ new Set();
  const changed = () => JSON.stringify(state) !== JSON.stringify(savedState);
  const snapshot = () => ({ state: clone(state), draft: clone(draft), guards: { ...guards }, status, error, render_sha256: renderSha, preview: previewResult ? clone(previewResult) : null, unknown_operation: unknown?.kind ?? null });
  function notify() {
    if (!disposed) for (const listener of listeners) listener(snapshot());
  }
  function cancelTimer() {
    if (timer !== void 0) {
      if (typeof clearTimeout !== "undefined") {
        clearTimeout(timer);
      } else if (typeof window !== "undefined" && typeof window.clearTimeout === "function") {
        window.clearTimeout(timer);
      }
      timer = void 0;
    }
  }
  function invalidatePreview() {
    previewResult = null;
    previewSequence += 1;
  }
  function assertWritable() {
    if (disposed) throw new Error("The document workspace is closed.");
    if (unknown) throw new Error("Retry the pending document operation before starting another operation.");
    if (status === "conflict") throw new Error("Reload or reconcile the newer document draft before saving. Your local work is preserved.");
  }
  function edit(next) {
    const nextState = typeof next === "function" ? next(state) : next;
    if (disposed || status !== "idle" && JSON.stringify(nextState) === JSON.stringify(state)) return;
    state = clone(nextState);
    generation += 1;
    invalidatePreview();
    if (!unknown && status !== "conflict") {
      status = "dirty";
      error = null;
    }
    notify();
    cancelTimer();
    if (typeof window !== "undefined" && !unknown && status !== "conflict" && options.autosaveMs !== 0) {
      if (typeof setTimeout !== "undefined") {
        timer = setTimeout(() => {
          timer = void 0;
          void save().catch(() => {
          });
        }, options.autosaveMs ?? 700);
      } else if (typeof window.setTimeout === "function") {
        timer = window.setTimeout(() => {
          timer = void 0;
          void save().catch(() => {
          });
        }, options.autosaveMs ?? 700);
      }
    }
  }
  function exclusive(work) {
    const job = writeTail.then(work);
    writeTail = job.catch(() => {
    });
    return job;
  }
  function headers(json = true) {
    const token = options.csrf?.();
    return { Accept: "application/json", "X-Requested-With": "XMLHttpRequest", ...json ? { "Content-Type": "application/json" } : {}, ...token ? { "X-CSRF-TOKEN": token } : {} };
  }
  function diagnostic(result, fallback) {
    const details = Array.isArray(result.diagnostics) ? result.diagnostics : result.errors && typeof result.errors === "object" ? Object.values(result.errors).flat() : [];
    return [String(result.message ?? fallback), ...details.map((item) => typeof item === "string" ? item : JSON.stringify(item))].filter(Boolean).join(" ");
  }
  function adopt(result, op) {
    const returnedDraft = result.document_draft ?? result.draft;
    if ("document_draft" in result || "draft" in result) draft = returnedDraft ? clone(returnedDraft) : null;
    if (op.kind === "discard") draft = null;
    const returnedGuards = result.guards;
    guards = {
      base_version: typeof result.base_version === "number" ? result.base_version : returnedDraft?.base_version ?? guards.base_version,
      document_revision: typeof result.document_revision === "number" ? result.document_revision : guards.document_revision,
      lock_version: typeof result.lock_version === "number" || result.lock_version === null ? result.lock_version : returnedDraft?.lock_version ?? (draft ? guards.lock_version : null),
      ...returnedGuards
    };
    renderSha = typeof result.render_sha256 === "string" ? result.render_sha256 : returnedDraft?.render_sha256 ?? (draft ? renderSha : null);
    if (op.state) savedState = clone(op.state);
    if (op.kind === "upload" && op.body?.has("document")) {
      const source = result.source;
      savedState = clone(returnedDraft?.state ?? { ...op.state ?? savedState, source_kind: "docx", document_asset_id: source.asset_id, builder_json: null });
    }
    if (generation === op.generation && (op.state || op.body?.has("document"))) state = clone(savedState);
    if (op.kind === "discard" && generation === op.generation) state = savedState = emptyState();
    if (op.kind === "discard" || op.kind === "version") {
      lifecycle += 1;
      cancelTimer();
    }
    unknown = null;
    error = null;
    status = changed() ? "dirty" : "saved";
    invalidatePreview();
    notify();
  }
  async function send(op) {
    loadSequence += 1;
    status = "saving";
    error = null;
    notify();
    let ambiguous = true;
    try {
      if (op.body) for (const [key, value] of Object.entries(op.payload)) op.body.set(key, typeof value === "string" ? value : JSON.stringify(value));
      const response = await transport(op.url, { method: op.method, credentials: "same-origin", headers: headers(!op.body), body: op.body ?? JSON.stringify(op.payload) });
      ambiguous = response.status >= 500;
      const result = await response.json().catch(() => ({}));
      if (!response.ok) {
        if (!ambiguous) unknown = null;
        status = response.status === 409 ? "conflict" : ambiguous ? "unknown" : "error";
        error = diagnostic(result, `The document operation failed (${response.status}). Your local work is preserved.`);
        invalidatePreview();
        throw new Error(error);
      }
      const versionReceipt = result.document_version;
      const imageReceipt = result.asset;
      const sourceReceipt = result.source;
      const confirmed = op.kind === "draft" ? Boolean(result.document_draft ?? result.draft) : op.kind === "version" ? versionReceipt !== null && typeof versionReceipt === "object" && "id" in versionReceipt && typeof versionReceipt.id === "number" : op.kind === "discard" ? result.document_draft === null && typeof result.document_revision === "number" && "latest_ready_version" in result && typeof result.operation_id === "string" && typeof result.replayed === "boolean" : op.body?.has("image") ? imageReceipt !== null && typeof imageReceipt === "object" && "id" in imageReceipt && typeof imageReceipt.id === "string" && typeof result.preview_url === "string" && typeof result.operation_id === "string" : sourceReceipt !== null && typeof sourceReceipt === "object" && "asset_id" in sourceReceipt && typeof sourceReceipt.asset_id === "string" && typeof result.operation_id === "string";
      if (!confirmed) {
        ambiguous = true;
        throw new Error("The server did not confirm the operation.");
      }
      if (!disposed) adopt(result, op);
      return result;
    } catch (caught) {
      if (ambiguous) {
        unknown = op;
        status = "unknown";
        error = "The save outcome is unknown. Your document work is retained; retry the same operation.";
        invalidatePreview();
      }
      notify();
      throw caught;
    }
  }
  function operation(kind, url, method, payload, body) {
    const key = makeKey();
    return { kind, url, method, key, payload: { ...payload, idempotency_key: key }, body, generation, state: kind === "draft" || body?.has("document") ? clone(state) : void 0 };
  }
  function draftUrl(url) {
    return url.replace("{draft}", String(draft?.id));
  }
  function save(stage = state.source_kind === "builder" ? 2 : 1) {
    cancelTimer();
    requestedStage = stage;
    if (saveJob) return saveJob;
    const requestedLifecycle = lifecycle;
    const job = exclusive(async () => {
      let result = {};
      do {
        assertWritable();
        if (lifecycle !== requestedLifecycle) throw new Error("This document draft was discarded or versioned. The queued save was cancelled.");
        if (!changed() && draft) return { ...result, document_draft: clone(draft), render_sha256: renderSha };
        const url = draft ? endpoints.update : endpoints.create;
        if (!url) throw new Error("Document draft saving is unavailable.");
        result = await send(operation("draft", draftUrl(url), draft ? "PUT" : "POST", { state: clone(state), stage: requestedStage, ...guards }));
      } while (changed() && !disposed);
      return result;
    });
    saveJob = job;
    void job.finally(() => {
      if (saveJob === job) saveJob = null;
    }).catch(() => {
    });
    return job;
  }
  async function retry() {
    cancelTimer();
    return exclusive(async () => {
      if (!unknown) throw new Error("There is no unknown document operation to retry.");
      return send(unknown);
    });
  }
  async function load(preserveLocal = true) {
    assertWritableForLoad();
    if (!endpoints.load) throw new Error("Reloading this document is unavailable.");
    const requestedGeneration = generation;
    await writeTail;
    assertWritableForLoad();
    const ticket = ++loadSequence;
    const response = await transport(endpoints.load, { credentials: "same-origin", headers: headers(false) });
    const result = await response.json();
    if (!response.ok) throw new Error(diagnostic(result, "Could not reload the current document. Your local edits are retained."));
    if (disposed || ticket !== loadSequence || generation !== requestedGeneration) return result;
    const workspace = result.workspace ?? result;
    if (!workspace.guards || !("draft" in workspace)) throw new Error("The reload response did not include a guarded document workspace.");
    cancelTimer();
    draft = clone(workspace.draft);
    guards = { ...workspace.guards };
    savedState = clone(draft?.state ?? emptyState());
    if (!preserveLocal) {
      state = clone(savedState);
      generation += 1;
    }
    renderSha = draft?.render_sha256 ?? workspace.render_sha256;
    error = null;
    status = changed() ? "dirty" : draft ? "saved" : "idle";
    invalidatePreview();
    notify();
    return result;
  }
  function assertWritableForLoad() {
    if (unknown || disposed) throw new Error("Retry the unknown operation before reloading; your local work is preserved.");
  }
  async function upload(file) {
    assertWritable();
    if (!endpoints.upload) throw new Error("Word upload is unavailable.");
    if (!/\.docx$/i.test(file.name) || file.size > 10 * 1024 * 1024 || file.size === 0) throw new Error("Choose a nonempty DOCX file of at most 10 MiB.");
    await save(1);
    return exclusive(() => {
      assertWritable();
      const body = new FormData();
      body.set("document", file);
      return send(operation("upload", endpoints.upload, "POST", { ...guards, document_draft_id: draft?.id }, body));
    });
  }
  async function uploadImage(file) {
    assertWritable();
    if (!endpoints.image_upload) throw new Error("Verified document image upload is unavailable.");
    if (!["image/png", "image/jpeg"].includes(file.type) || !file.size || file.size > 2 * 1024 * 1024) throw new Error("Choose a PNG or JPEG image of at most 2 MiB.");
    await save();
    return exclusive(() => {
      assertWritable();
      const body = new FormData();
      body.set("image", file);
      return send(operation("upload", endpoints.image_upload, "POST", { ...guards, document_draft_id: draft?.id }, body));
    });
  }
  async function preview() {
    assertWritable();
    await save();
    assertWritable();
    if (!endpoints.preview || !renderSha) throw new Error("A current document render is not available yet. Save the draft and try again.");
    const digest = renderSha;
    const requestedGeneration = generation;
    const ticket = ++previewSequence;
    previewResult = null;
    notify();
    try {
      const response = await transport(endpoints.preview, { method: "POST", credentials: "same-origin", headers: headers(), body: JSON.stringify({ ...guards, render_sha256: digest }) });
      const result = await response.json().catch(() => ({}));
      if (disposed || ticket !== previewSequence || generation !== requestedGeneration || renderSha !== digest) throw new Error("The PDF check response is stale or incomplete. Check the current document again.");
      if (!response.ok) {
        status = response.status === 409 ? "conflict" : "error";
        throw new Error(diagnostic(result, `PDF check failed (${response.status}).`));
      }
      const pdf = result.pdf;
      let validBytes = false;
      try {
        validBytes = typeof pdf?.pdf_base64 === "string" && atob(pdf.pdf_base64).startsWith("%PDF-");
      } catch {
        validBytes = false;
      }
      if (result.render_sha256 !== digest || !validBytes || !pdf?.sha256 || typeof result.preview_receipt !== "string" || !result.preview_receipt) throw new Error("The PDF check response is stale or incomplete. Check the current document again.");
      previewResult = { render_sha256: digest, pdf, preview_receipt: result.preview_receipt, diagnostics: Array.isArray(result.diagnostics) ? result.diagnostics : [] };
      error = null;
      status = "saved";
      notify();
      return clone(previewResult);
    } catch (caught) {
      if (!disposed && ticket === previewSequence && generation === requestedGeneration) {
        error = caught instanceof Error ? caught.message : "PDF check failed. Retry the current document.";
        if (status !== "conflict") status = "error";
        notify();
      }
      throw caught;
    }
  }
  async function saveVersion() {
    cancelTimer();
    return exclusive(() => {
      assertWritable();
      if (!previewResult || changed() || previewResult.render_sha256 !== renderSha) throw new Error("Run a successful current PDF check before saving a document version.");
      if (!endpoints.version) throw new Error("Saving a document version is unavailable.");
      return send(operation("version", endpoints.version, "POST", { ...guards, render_sha256: renderSha, preview_receipt: previewResult.preview_receipt }));
    });
  }
  async function discard() {
    cancelTimer();
    return exclusive(() => {
      assertWritable();
      if (!draft || !endpoints.discard) throw new Error("There is no document draft to discard.");
      return send(operation("discard", draftUrl(endpoints.discard), "DELETE", { ...guards }));
    });
  }
  function subscribe(listener) {
    listeners.add(listener);
    listener(snapshot());
    return () => listeners.delete(listener);
  }
  function dispose() {
    disposed = true;
    cancelTimer();
    previewSequence += 1;
    loadSequence += 1;
    listeners.clear();
  }
  return { snapshot, edit, save, retry, load, upload, uploadImage, preview, saveVersion, discard, subscribe, dispose };
}
function PhysicalDocument($$renderer, $$props) {
  $$renderer.component(($$renderer2) => {
    let { $$slots, $$events, ...props } = $$props;
    let adapter = null;
    let snapshot = null;
    let state = null;
    let sourceChosen = false;
    let mobileTab = "write";
    let busy = false;
    let message = "";
    let errorMessage = "";
    let pickerMode = "copy";
    let pickerOpen = false;
    let placeholderTargetId = null;
    let pendingFile = null;
    let imageInput;
    let pdfUrl = "";
    let checkingPdf = false;
    let discardConfirm = false;
    let documentImages = [];
    let pendingUpload = null;
    let unsubscribe;
    let pendingNavigation = null;
    let leaveDialog = false;
    let leaving = false;
    const hasDraft = derived(() => Boolean(snapshot?.draft));
    const preview = derived(() => snapshot?.preview ?? null);
    const previewCurrent = derived(() => Boolean(preview() && snapshot?.render_sha256 && preview().render_sha256 === snapshot.render_sha256));
    const draftStatus = derived(() => snapshot?.status === "conflict" ? "Stale" : snapshot?.status === "unknown" ? "Unknown" : snapshot && ["dirty", "saving", "error"].includes(snapshot.status) ? "Unsaved changes" : "Saved");
    const builderDocument = derived(() => state?.source_kind === "builder" ? state.builder_json : null);
    const sourceName = derived(() => state?.source_kind === "docx" ? "Word document" : "Visual designer");
    const builderComponents = derived(() => Array.isArray(builderDocument()?.components) ? builderDocument().components : []);
    function imageAsset(component) {
      const imageProps = component.props ?? {};
      const assetId = typeof imageProps.asset_id === "string" ? imageProps.asset_id : "";
      return (props.workspace.verified_images ?? []).find((asset) => asset.id === assetId) ?? null;
    }
    const containsUnverifiedImage = derived(() => builderComponents().some((component) => {
      const imageProps = component.props;
      const assetId = imageProps && typeof imageProps === "object" && "asset_id" in imageProps ? imageProps.asset_id : null;
      return component.type === "image" && (!assetId || !imageAsset(component) && !documentImages.some((asset) => asset.asset_id === assetId));
    }));
    const headerCatalog = derived(() => props.builderCatalog.header ?? {});
    const fieldCatalog = derived(() => props.builderCatalog.field ?? {});
    const safeBuilderCatalog = derived(() => ({
      ...props.builderCatalog,
      header: {
        ...headerCatalog(),
        default_props: {
          ...headerCatalog().default_props ?? {},
          title: "{{institution_name}}",
          subtitle: props.family.name
        }
      },
      field: {
        ...fieldCatalog(),
        default_props: {
          ...fieldCatalog().default_props ?? {},
          label: props.family.domain.toLowerCase() === "results" ? "Applicant number" : "Application number",
          binding: props.family.domain.toLowerCase() === "results" ? "applicant_reference" : "application_no"
        }
      },
      image: props.builderCatalog.image ?? {
        label: "Image",
        description: "Place an uploaded, verified family image",
        category: "Media",
        default_w: 60,
        default_h: 40
      }
    }));
    const breadcrumbs = derived(() => [
      { label: "Setup", href: "/setup" },
      { label: "Email & Notifications", href: "/setup/notifications" },
      {
        label: `${props.family.domain} notices`,
        href: props.return_url
      },
      { label: props.family.name, href: props.return_url },
      { label: "PDF document" }
    ]);
    function clone2(value) {
      return JSON.parse(JSON.stringify(value));
    }
    function imagesIn() {
      return (props.workspace.verified_images ?? []).map((asset) => ({ asset_id: asset.id, src: asset.url, label: asset.filename }));
    }
    function resolveBuilderImages(value) {
      if (!value.builder_json || !Array.isArray(value.builder_json.components)) return value;
      return {
        ...value,
        builder_json: {
          ...value.builder_json,
          components: value.builder_json.components.map((component) => {
            if (component.type !== "image") return component;
            const imageProps = component.props ?? {};
            const verified = (props.workspace.verified_images ?? []).find((asset) => asset.id === imageProps.asset_id);
            return verified ? { ...component, props: { ...imageProps, src: verified.url } } : component;
          })
        }
      };
    }
    function startAdapter() {
      if (adapter) return;
      adapter = createNoticeDocumentStudioAdapter(props, { csrf: csrfToken });
      snapshot = adapter.snapshot();
      state = resolveBuilderImages(clone2(snapshot.state));
      documentImages = imagesIn();
      sourceChosen = Boolean(snapshot.draft?.state?.source_kind);
      unsubscribe = adapter.subscribe((next) => {
        snapshot = next;
        if (next.error) errorMessage = next.error;
      });
    }
    startAdapter();
    function setPdf(value) {
      pdfUrl = `data:application/pdf;base64,${value.pdf.pdf_base64}`;
    }
    function updateBuilder(next) {
      if (!adapter || busy || !sourceChosen) return;
      state = { ...state, builder_json: clone2(next) };
      adapter.edit((current) => ({ ...current, builder_json: clone2(next) }));
    }
    function openBuilderPlaceholder(selection) {
      placeholderTargetId = selection.targetId ?? null;
      pickerMode = "insert";
      pickerOpen = true;
    }
    function insertBuilderPlaceholder(selection) {
      if (!state?.builder_json || !placeholderTargetId) return;
      const components = Array.isArray(state.builder_json.components) ? state.builder_json.components : [];
      const next = {
        ...state.builder_json,
        components: components.map((component) => component.id === placeholderTargetId ? {
          ...component,
          props: { ...component.props ?? {}, binding: selection.key }
        } : component)
      };
      updateBuilder(next);
      pickerOpen = false;
      placeholderTargetId = null;
    }
    function updateSetting(key, value) {
      if (!adapter || !state) return;
      const changed = { ...state, [key]: value };
      if (changed.builder_json) {
        const page = changed.builder_json.page ?? {};
        changed.builder_json = { ...changed.builder_json, page: { ...page, [key]: value } };
      }
      state = changed;
      adapter.edit(changed);
    }
    async function saveDraft(stage = state?.source_kind === "docx" ? 1 : 2) {
      if (!adapter || busy || !sourceChosen) return;
      busy = true;
      errorMessage = "";
      message = "Saving document draft…";
      try {
        await adapter.save(stage);
        message = "Document draft saved.";
      } catch (error) {
        errorMessage = error instanceof Error ? error.message : "The document draft was not saved. Your work is retained.";
        message = "";
      } finally {
        busy = false;
      }
    }
    async function uploadDocumentImage(file, targetId = null) {
      if (!adapter || busy || !file) {
        if (!file) errorMessage = "Choose a PNG or JPEG image to upload.";
        return null;
      }
      if (snapshot?.unknown_operation) {
        errorMessage = "Retry the previous operation before selecting another file.";
        return null;
      }
      pendingUpload = { kind: "image", file, targetId };
      busy = true;
      errorMessage = "";
      message = "Uploading and validating private document image…";
      try {
        const result = await adapter.uploadImage(file);
        const entry = completeImageUpload(result, targetId);
        message = targetId ? "Verified image uploaded. It is applied only if the original image component still exists and is unlocked." : "Verified image added to this document family. Use the Designer palette to place it.";
        return entry;
      } catch (error) {
        errorMessage = error instanceof Error ? error.message : "Image upload failed. Existing document content is retained.";
        message = "";
        return null;
      } finally {
        if (!adapter.snapshot().unknown_operation) pendingUpload = null;
        busy = false;
      }
    }
    function completeImageUpload(result, targetId) {
      const entry = addDocumentImage(result);
      if (!adapter || !targetId || state?.source_kind !== "builder" || !state.builder_json || !Array.isArray(state.builder_json.components)) return entry;
      const components = state.builder_json.components;
      const target = components.find((component) => component.id === targetId);
      if (!target || target.type !== "image" || Boolean(target.locked)) return entry;
      const next = {
        ...state,
        builder_json: {
          ...state.builder_json,
          components: components.map((component) => component === target ? {
            ...component,
            props: {
              ...component.props ?? {},
              asset_id: entry.asset_id,
              src: entry.src
            }
          } : component)
        }
      };
      state = next;
      adapter.edit(next);
      return entry;
    }
    function addDocumentImage(result) {
      const asset = result.asset;
      const previewUrl = typeof result.preview_url === "string" ? result.preview_url : null;
      if (!asset?.id || !previewUrl) throw new Error("The upload receipt did not include a verified image asset and preview URL.");
      const entry = {
        asset_id: asset.id,
        src: previewUrl,
        label: `Image ${asset.id.slice(0, 8)}`
      };
      if (!documentImages.some((item) => item.asset_id === entry.asset_id)) documentImages = [...documentImages, entry];
      return entry;
    }
    async function retryUnknown() {
      if (!adapter || busy && !snapshot?.unknown_operation) return;
      busy = true;
      errorMessage = "";
      message = "Retrying the same document operation…";
      const operation = snapshot?.unknown_operation;
      try {
        const result = await adapter.retry();
        if (pendingUpload) {
          const intended = pendingUpload;
          const uploadResult = operation === "draft" ? intended.kind === "image" ? await adapter.uploadImage(intended.file) : await adapter.upload(intended.file) : result;
          if (intended.kind === "image") completeImageUpload(uploadResult, intended.targetId);
          else state = adapter.snapshot().state;
          pendingFile = null;
          pendingUpload = null;
        }
        message = "The original document operation was confirmed.";
        if (operation === "version") {
          message = "Document version ready. The message still uses its previous selection.";
          success(message);
          leaving = true;
          router.visit(props.return_url);
        }
      } catch (error) {
        errorMessage = error instanceof Error ? error.message : "The operation is still unconfirmed; your work is retained.";
        message = "";
      } finally {
        if (!adapter.snapshot().unknown_operation) pendingUpload = null;
        busy = false;
      }
    }
    async function checkPdf() {
      if (!adapter || busy || !sourceChosen || containsUnverifiedImage()) return;
      busy = true;
      checkingPdf = true;
      errorMessage = "";
      message = "Checking the current document and preparing its PDF…";
      try {
        const result = await adapter.preview();
        setPdf(result);
        mobileTab = "preview";
        message = "Current PDF check passed.";
      } catch (error) {
        errorMessage = error instanceof Error ? error.message : "PDF check failed. Correct the document findings and check again.";
        message = "";
      } finally {
        busy = false;
        checkingPdf = false;
      }
    }
    async function saveVersion() {
      if (!adapter || busy || !previewCurrent()) return;
      busy = true;
      errorMessage = "";
      message = "Saving immutable document version…";
      try {
        const result = await adapter.saveVersion();
        const version = result.document_version;
        message = `Document version ${version && typeof version === "object" && "version" in version ? version.version : ""} is ready. The message still uses its previous selection.`;
        success(message);
        await tick();
        leaving = true;
        router.visit(props.return_url);
      } catch (error) {
        errorMessage = error instanceof Error ? error.message : "Document version was not confirmed. The current PDF proof and draft are retained.";
        message = "";
      } finally {
        busy = false;
      }
    }
    async function discardDraft() {
      if (!adapter || busy || !snapshot?.draft) return;
      busy = true;
      errorMessage = "";
      message = "Discarding document draft…";
      try {
        await adapter.discard();
        message = "Document draft discarded.";
        router.visit(props.return_url);
      } catch (error) {
        errorMessage = error instanceof Error ? error.message : "The draft was not discarded. Your work is retained.";
        message = "";
      } finally {
        busy = false;
      }
    }
    function hasUnsavedWork() {
      return Boolean(snapshot && ["dirty", "saving", "unknown", "error", "conflict"].includes(snapshot.status)) || busy;
    }
    function offerNavigation(leave) {
      if (!hasUnsavedWork()) {
        leaving = true;
        leave();
        return;
      }
      pendingNavigation = leave;
      leaveDialog = true;
    }
    async function saveAndContinue() {
      if (!adapter || busy || !pendingNavigation || snapshot?.unknown_operation) return;
      busy = true;
      errorMessage = "";
      message = "Saving document draft before leaving…";
      try {
        await adapter.save(state?.source_kind === "docx" ? 1 : 2);
        if (adapter.snapshot().status !== "saved") throw new Error("The latest document edit is not confirmed yet.");
        const leave = pendingNavigation;
        pendingNavigation = null;
        leaveDialog = false;
        leaving = true;
        leave?.();
      } catch (error) {
        errorMessage = error instanceof Error ? error.message : "Document draft was not confirmed; your work is retained.";
      } finally {
        busy = false;
      }
    }
    function offerBack() {
      offerNavigation(() => router.visit(props.return_url));
    }
    async function reloadDocument(preserveLocal) {
      if (!adapter || busy) return;
      busy = true;
      errorMessage = "";
      try {
        await adapter.load(preserveLocal);
        state = resolveBuilderImages(adapter.snapshot().state);
        sourceChosen = Boolean(adapter.snapshot().draft || preserveLocal);
        message = preserveLocal ? "Current document guards loaded. Review your retained local changes, then save." : "Current server document loaded.";
      } catch (error) {
        errorMessage = error instanceof Error ? error.message : "Reload failed. Your local work is retained.";
      } finally {
        busy = false;
      }
    }
    onDestroy(() => {
      unsubscribe?.();
      adapter?.dispose();
    });
    head("aogzoo", $$renderer2, ($$renderer3) => {
      $$renderer3.title(($$renderer4) => {
        $$renderer4.push(`<title>${escape_html(props.family.name)} · PDF document</title>`);
      });
    });
    StaffLayout($$renderer2, {
      breadcrumbs: breadcrumbs(),
      children: ($$renderer3) => {
        $$renderer3.push(`<div class="flex min-h-[calc(100dvh-9rem)] min-w-0 flex-col gap-3 p-3 sm:p-5"><header class="sticky top-0 z-30 flex flex-wrap items-center justify-between gap-3 border-b bg-background py-3"><div class="flex min-w-0 items-center gap-2">`);
        Button($$renderer3, {
          type: "button",
          variant: "outline",
          onclick: offerBack,
          "aria-label": "Back to notice",
          children: ($$renderer4) => {
            $$renderer4.push(`<!---->Back`);
          },
          $$slots: { default: true }
        });
        $$renderer3.push(`<!----><div class="min-w-0">`);
        PageHeaderBar($$renderer3, {
          title: `Edit ${props.family.name} document`,
          description: "Create a separate PDF document. Saving it does not change the message or attach it automatically."
        });
        $$renderer3.push(`<!----></div></div> `);
        Badge($$renderer3, {
          variant: "outline",
          "aria-label": "Document draft status",
          children: ($$renderer4) => {
            $$renderer4.push(`<!---->${escape_html(draftStatus())}`);
          },
          $$slots: { default: true }
        });
        $$renderer3.push(`<!----> <div class="flex flex-wrap gap-2">`);
        Button($$renderer3, {
          type: "button",
          variant: "outline",
          onclick: () => void saveDraft(),
          disabled: busy || !sourceChosen,
          children: ($$renderer4) => {
            $$renderer4.push(`<!---->Save draft`);
          },
          $$slots: { default: true }
        });
        $$renderer3.push(`<!----> `);
        Button($$renderer3, {
          type: "button",
          variant: "outline",
          onclick: () => void checkPdf(),
          disabled: busy || !sourceChosen || containsUnverifiedImage(),
          children: ($$renderer4) => {
            $$renderer4.push(`<!---->Check PDF`);
          },
          $$slots: { default: true }
        });
        $$renderer3.push(`<!----> `);
        Button($$renderer3, {
          type: "button",
          onclick: () => void saveVersion(),
          disabled: busy || !previewCurrent() || containsUnverifiedImage(),
          children: ($$renderer4) => {
            $$renderer4.push(`<!---->Save document version`);
          },
          $$slots: { default: true }
        });
        $$renderer3.push(`<!----></div></header> <div class="flex min-h-11 items-center justify-between gap-2 rounded-lg border bg-card px-3 text-sm"><p aria-live="polite">${escape_html(message || (snapshot?.status === "unknown" ? "Document save outcome unknown; retry the same operation." : snapshot?.status === "conflict" ? "A newer document draft exists. Local work is preserved." : snapshot?.status === "dirty" ? "Unsaved document changes" : hasDraft() ? "Document draft saved" : "No document draft saved yet"))}</p>`);
        if (snapshot?.unknown_operation) {
          $$renderer3.push("<!--[0-->");
          Button($$renderer3, {
            type: "button",
            variant: "outline",
            onclick: () => void retryUnknown(),
            disabled: busy,
            children: ($$renderer4) => {
              $$renderer4.push(`<!---->Retry same operation`);
            },
            $$slots: { default: true }
          });
        } else {
          $$renderer3.push("<!--[-1-->");
        }
        $$renderer3.push(`<!--]--></div> `);
        if (errorMessage) {
          $$renderer3.push("<!--[0-->");
          $$renderer3.push(`<div tabindex="-1" role="alert" class="rounded-lg border border-destructive p-3 text-sm text-destructive">${escape_html(errorMessage)}</div>`);
        } else {
          $$renderer3.push("<!--[-1-->");
        }
        $$renderer3.push(`<!--]--> `);
        if (snapshot?.status === "conflict") {
          $$renderer3.push("<!--[0-->");
          $$renderer3.push(`<section role="alert" class="space-y-2 rounded-lg border p-3 text-sm"><p>Your local document is preserved. Load current guards to reconcile your work, or replace local edits with the server draft.</p><div class="flex flex-wrap gap-2">`);
          Button($$renderer3, {
            variant: "outline",
            onclick: () => void reloadDocument(true),
            disabled: busy,
            children: ($$renderer4) => {
              $$renderer4.push(`<!---->Reconcile local changes`);
            },
            $$slots: { default: true }
          });
          $$renderer3.push(`<!---->`);
          Button($$renderer3, {
            variant: "outline",
            onclick: () => void reloadDocument(false),
            disabled: busy,
            children: ($$renderer4) => {
              $$renderer4.push(`<!---->Reload server draft`);
            },
            $$slots: { default: true }
          });
          $$renderer3.push(`<!----></div></section>`);
        } else {
          $$renderer3.push("<!--[-1-->");
        }
        $$renderer3.push(`<!--]--> `);
        if (leaveDialog) {
          $$renderer3.push("<!--[0-->");
          $$renderer3.push(`<section role="alertdialog" aria-labelledby="leave-document-title" aria-describedby="leave-document-copy" class="space-y-3 rounded-lg border border-primary/40 bg-card p-4"><h2 id="leave-document-title" class="font-semibold">Save document work before leaving?</h2><p id="leave-document-copy" class="text-sm text-muted-foreground">Your document changes are not confirmed yet. Save this document draft before returning; the message and its selected version remain unchanged.</p><div class="flex flex-wrap gap-2">`);
          Button($$renderer3, {
            type: "button",
            onclick: () => void saveAndContinue(),
            disabled: busy || Boolean(snapshot?.unknown_operation),
            children: ($$renderer4) => {
              $$renderer4.push(`<!---->Save draft and continue`);
            },
            $$slots: { default: true }
          });
          $$renderer3.push(`<!---->`);
          Button($$renderer3, {
            type: "button",
            variant: "outline",
            onclick: () => {
              leaveDialog = false;
              pendingNavigation = null;
            },
            children: ($$renderer4) => {
              $$renderer4.push(`<!---->Stay here`);
            },
            $$slots: { default: true }
          });
          $$renderer3.push(`<!----></div></section>`);
        } else {
          $$renderer3.push("<!--[-1-->");
        }
        $$renderer3.push(`<!--]--> <div class="flex gap-2 min-[769px]:hidden" role="group" aria-label="Document workspace view">`);
        Button($$renderer3, {
          type: "button",
          variant: mobileTab === "write" ? "default" : "outline",
          "aria-pressed": mobileTab === "write",
          onclick: () => mobileTab = "write",
          children: ($$renderer4) => {
            $$renderer4.push(`<!---->Write`);
          },
          $$slots: { default: true }
        });
        $$renderer3.push(`<!---->`);
        Button($$renderer3, {
          type: "button",
          variant: mobileTab === "preview" ? "default" : "outline",
          "aria-pressed": mobileTab === "preview",
          onclick: () => mobileTab = "preview",
          children: ($$renderer4) => {
            $$renderer4.push(`<!---->Preview`);
          },
          $$slots: { default: true }
        });
        $$renderer3.push(`<!----></div> `);
        if (!sourceChosen) {
          $$renderer3.push("<!--[0-->");
          $$renderer3.push(`<section class="grid gap-3 rounded-xl border bg-card p-4 sm:grid-cols-2 sm:p-6" aria-labelledby="source-choice-heading"><div class="sm:col-span-2"><h2 id="source-choice-heading" class="text-lg font-semibold">Choose a document source</h2><p class="mt-1 text-sm text-muted-foreground">Choose before editing. An empty designer draft may be saved, but it cannot pass PDF Check or become a ready version until it has meaningful printable content and required fields.</p></div> <button type="button" aria-label="Start from a Word document" class="min-h-32 rounded-lg border p-4 text-left hover:bg-muted/40 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring"><span class="block font-semibold">Start from a Word document</span><span class="mt-2 block text-sm text-muted-foreground">Upload a safe DOCX, then check the actual server-rendered PDF.</span></button> <button type="button" aria-label="Design a document" class="min-h-32 rounded-lg border p-4 text-left hover:bg-muted/40 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring"><span class="block font-semibold">Design a document</span><span class="mt-2 block text-sm text-muted-foreground">Use the familiar physical document designer and its typed fields.</span></button></section>`);
        } else {
          $$renderer3.push("<!--[-1-->");
          $$renderer3.push(`<div class="grid min-h-0 min-w-0 flex-1 gap-3 min-[769px]:grid-cols-2"><section${attr_class(`${mobileTab === "write" ? "flex" : "hidden"} min-h-[50vh] min-w-0 flex-col overflow-hidden rounded-xl border bg-card min-[769px]:flex`)} aria-label="Write document"><div class="flex flex-wrap items-center justify-between gap-2 border-b p-3"><div><h2 class="font-semibold">${escape_html(sourceName())}</h2><p class="text-xs text-muted-foreground">Document work is independent from the message.</p></div><button type="button" class="min-h-10 rounded-md border px-3 text-sm"${attr("disabled", busy, true)}>Change source</button></div> `);
          {
            $$renderer3.push("<!--[-1-->");
          }
          $$renderer3.push(`<!--]--> <div class="flex flex-wrap gap-3 border-b p-3"><label class="text-xs">Paper size`);
          $$renderer3.select(
            {
              disabled: busy,
              class: "ml-2 min-h-10 rounded-md border bg-background px-2",
              value: state?.paper_size ?? "a4",
              onchange: (event) => updateSetting("paper_size", event.currentTarget.value)
            },
            ($$renderer4) => {
              $$renderer4.option({ value: "a4" }, ($$renderer5) => {
                $$renderer5.push(`A4`);
              });
              $$renderer4.option({ value: "letter" }, ($$renderer5) => {
                $$renderer5.push(`Letter`);
              });
              $$renderer4.option({ value: "legal" }, ($$renderer5) => {
                $$renderer5.push(`Legal`);
              });
            }
          );
          $$renderer3.push(`</label><label class="text-xs">Orientation`);
          $$renderer3.select(
            {
              disabled: busy,
              class: "ml-2 min-h-10 rounded-md border bg-background px-2",
              value: state?.orientation ?? "portrait",
              onchange: (event) => updateSetting("orientation", event.currentTarget.value)
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
          $$renderer3.push(`</label></div> `);
          if (state?.source_kind === "docx") {
            $$renderer3.push("<!--[0-->");
            $$renderer3.push(`<div class="space-y-4 p-4"><button type="button" class="min-h-11 rounded-md border px-3 text-sm">View permitted document fields</button> <label class="block space-y-2 text-sm font-medium">Upload or replace Word document (.docx)<input class="block min-h-11 w-full rounded-md border p-2 text-sm" type="file" accept=".docx,application/vnd.openxmlformats-officedocument.wordprocessingml.document"${attr("disabled", busy || Boolean(snapshot?.unknown_operation), true)}/><span class="block text-xs font-normal text-muted-foreground">DOCX only, up to 10 MiB. The original remains private and is not imported into the message.</span></label> `);
            {
              $$renderer3.push("<!--[-1-->");
            }
            $$renderer3.push(`<!--]--> `);
            if (state.document_asset_id) {
              $$renderer3.push("<!--[0-->");
              $$renderer3.push(`<p class="text-sm" role="status">A Word source is attached to this document draft.</p>`);
            } else {
              $$renderer3.push("<!--[-1-->");
              $$renderer3.push(`<p class="text-sm text-muted-foreground">No Word source uploaded yet.</p>`);
            }
            $$renderer3.push(`<!--]--></div>`);
          } else {
            $$renderer3.push("<!--[-1-->");
            $$renderer3.push(`<div class="flex min-h-0 flex-1 flex-col">`);
            if (props.endpoints.image_upload) {
              $$renderer3.push("<!--[0-->");
              $$renderer3.push(`<div class="flex flex-wrap items-center gap-2 border-b p-2">`);
              Button($$renderer3, {
                type: "button",
                variant: "outline",
                onclick: () => imageInput?.click(),
                disabled: busy,
                children: ($$renderer4) => {
                  $$renderer4.push(`<!---->Upload PNG/JPEG`);
                },
                $$slots: { default: true }
              });
              $$renderer3.push(`<!----><input class="sr-only" aria-label="Upload verified document image" type="file" accept="image/png,image/jpeg,.png,.jpg,.jpeg"${attr("disabled", busy || Boolean(snapshot?.unknown_operation), true)}/><span class="text-xs text-muted-foreground">PNG/JPEG up to 2 MiB.</span></div>`);
            } else {
              $$renderer3.push("<!--[-1-->");
            }
            $$renderer3.push(`<!--]--> `);
            if (containsUnverifiedImage()) {
              $$renderer3.push("<!--[0-->");
              $$renderer3.push(`<div role="alert" class="m-3 rounded-lg border border-destructive/50 p-3 text-sm">This saved design has an image without a verified same-family asset reference. Its source is preserved but cannot pass PDF Check or become ready. Replace it with an uploaded verified image or remove the image component.</div>`);
            } else {
              $$renderer3.push("<!--[-1-->");
            }
            $$renderer3.push(`<!--]--> <div class="flex min-h-[48vh] min-w-0 flex-1">`);
            ResultSheetStudio($$renderer3, {
              fullBleed: true,
              builderDocument: builderDocument(),
              builderDefaults: props.builderDefaults,
              builderCatalog: safeBuilderCatalog(),
              builderCapabilities: props.builderCapabilities,
              placeholderGroups: props.placeholderGroups,
              sampleContext: props.sampleContext,
              onChange: updateBuilder,
              onOpenPlaceholders: openBuilderPlaceholder,
              allowNoticeDocumentImages: true,
              noticeDocumentImageAssets: documentImages,
              onNoticeDocumentImageUpload: uploadDocumentImage
            });
            $$renderer3.push(`<!----></div></div>`);
          }
          $$renderer3.push(`<!--]--></section> <section${attr_class(`${mobileTab === "preview" ? "flex" : "hidden"} min-h-[50vh] min-w-0 flex-col overflow-hidden rounded-xl border bg-card min-[769px]:flex`)} aria-label="Actual PDF preview"><div class="flex flex-wrap items-center justify-between gap-2 border-b p-3"><div><h2 class="font-semibold">PDF preview</h2><p class="text-xs text-muted-foreground">Only server-returned PDF bytes can pass the current check.</p></div>`);
          Button($$renderer3, {
            type: "button",
            variant: "outline",
            onclick: () => void checkPdf(),
            disabled: busy || !sourceChosen || containsUnverifiedImage(),
            children: ($$renderer4) => {
              $$renderer4.push(`<!---->Check current document`);
            },
            $$slots: { default: true }
          });
          $$renderer3.push(`<!----></div> `);
          if (checkingPdf) {
            $$renderer3.push("<!--[0-->");
            $$renderer3.push(`<div role="status" class="min-h-[55vh] space-y-4 p-6"><p>Rendering and validating the current PDF…</p><div class="h-8 w-2/3 rounded bg-muted motion-safe:animate-pulse"></div><div class="h-80 rounded bg-muted motion-safe:animate-pulse"></div></div>`);
          } else if (pdfUrl && previewCurrent()) {
            $$renderer3.push("<!--[1-->");
            $$renderer3.push(`<iframe title="Checked PDF document preview"${attr("src", pdfUrl)} class="min-h-[55vh] w-full flex-1 bg-muted"></iframe>`);
          } else {
            $$renderer3.push("<!--[-1-->");
            $$renderer3.push(`<div class="flex min-h-[55vh] flex-1 items-center justify-center p-6 text-center"><div class="max-w-md space-y-2"><p class="font-medium">${escape_html(pdfUrl ? "This PDF check is no longer current." : "No current PDF check")}</p><p class="text-sm text-muted-foreground">Include meaningful content and the required ${escape_html(props.family.slug === "released-online" ? "applicant_reference" : "application_no")} field, then choose Check PDF. Correct any server findings and check again.</p></div></div>`);
          }
          $$renderer3.push(`<!--]--> `);
          if (previewCurrent()) {
            $$renderer3.push("<!--[0-->");
            $$renderer3.push(`<p class="break-all border-t p-3 text-xs text-muted-foreground" role="status">Current PDF check passed · SHA-256 ${escape_html(preview()?.pdf.sha256)}</p>`);
          } else {
            $$renderer3.push("<!--[-1-->");
          }
          $$renderer3.push(`<!--]--></section></div> `);
          if (snapshot?.preview?.diagnostics?.length) {
            $$renderer3.push("<!--[0-->");
            $$renderer3.push(`<section aria-label="Document corrections" class="rounded-lg border p-3"><h2 class="font-semibold">Corrections to make</h2><ul class="mt-2 list-disc space-y-1 pl-5 text-sm"><!--[-->`);
            const each_array = ensure_array_like(snapshot.preview.diagnostics);
            for (let $$index = 0, $$length = each_array.length; $$index < $$length; $$index++) {
              let diagnostic = each_array[$$index];
              $$renderer3.push(`<li>${escape_html(typeof diagnostic === "string" ? diagnostic : JSON.stringify(diagnostic))}</li>`);
            }
            $$renderer3.push(`<!--]--></ul></section>`);
          } else {
            $$renderer3.push("<!--[-1-->");
          }
          $$renderer3.push(`<!--]--> `);
          if (hasDraft()) {
            $$renderer3.push("<!--[0-->");
            $$renderer3.push(`<div class="flex flex-wrap justify-end">`);
            Button($$renderer3, {
              type: "button",
              variant: "outline",
              onclick: () => discardConfirm = true,
              disabled: busy,
              children: ($$renderer4) => {
                $$renderer4.push(`<!---->Discard document draft`);
              },
              $$slots: { default: true }
            });
            $$renderer3.push(`<!----></div>`);
          } else {
            $$renderer3.push("<!--[-1-->");
          }
          $$renderer3.push(`<!--]--> `);
          if (discardConfirm) {
            $$renderer3.push("<!--[0-->");
            $$renderer3.push(`<section role="alertdialog" aria-labelledby="discard-document-title" aria-describedby="discard-document-copy" class="space-y-3 rounded-lg border border-destructive/50 p-4"><h2 id="discard-document-title" class="font-semibold">Discard this document draft?</h2><p id="discard-document-copy" class="text-sm text-muted-foreground">Only this document draft will be discarded. The message draft and saved document versions remain unchanged.</p><div class="flex flex-wrap gap-2">`);
            Button($$renderer3, {
              type: "button",
              variant: "destructive",
              onclick: () => {
                discardConfirm = false;
                void discardDraft();
              },
              disabled: busy,
              children: ($$renderer4) => {
                $$renderer4.push(`<!---->Discard document draft`);
              },
              $$slots: { default: true }
            });
            $$renderer3.push(`<!---->`);
            Button($$renderer3, {
              type: "button",
              variant: "outline",
              onclick: () => discardConfirm = false,
              disabled: busy,
              children: ($$renderer4) => {
                $$renderer4.push(`<!---->Keep editing`);
              },
              $$slots: { default: true }
            });
            $$renderer3.push(`<!----></div></section>`);
          } else {
            $$renderer3.push("<!--[-1-->");
          }
          $$renderer3.push(`<!--]-->`);
        }
        $$renderer3.push(`<!--]--> `);
        PlaceholderPicker($$renderer3, {
          open: pickerOpen,
          mode: pickerMode,
          placeholderGroups: props.placeholderGroups,
          onclose: () => {
            pickerOpen = false;
            placeholderTargetId = null;
          },
          oncopy: (value) => {
            void navigator.clipboard?.writeText(String(value.macro));
            pickerOpen = false;
          },
          oninsert: insertBuilderPlaceholder
        });
        $$renderer3.push(`<!----></div>`);
      }
    });
  });
}
export {
  PhysicalDocument as default
};
