import { o as onDestroy, a as ensure_array_like, c as attr_class, e as escape_html, b as attr, l as bind_props, d as derived, h as head, B as Button, m as csrfHeaders, n as newIdempotencyKey } from "../app.js";
import { c as createOmrViewfinderSession, j as createOmrClientQuadHint, b as OmrViewfinderHud, g as createOmrWorkbenchClient, O as OmrWorkbench, a as OmrPendingCaptureProgress, k as assertDiagnosticItemReviewAcknowledged } from "./omrWorkbenchClient-DKqCgkj5.js";
import { R as Refresh_cw } from "./refresh-cw-CYdk8aE0.js";
import { C as Camera } from "./camera-C09Eyogi.js";
import { U as Upload } from "./upload-CwqN2Utd.js";
import { I as Image } from "./maximize-2-u72Ahym6.js";
import { S as Scan_line } from "./scan-line-CiwhUtYK.js";
/* empty css                                                       */
import { D as Dialog, a as Dialog_content, c as Dialog_title, d as Dialog_description } from "./dialog-description-D12L0QA9.js";
import "clsx";
import "axios";
import "@inertiajs/core";
import "laravel-precognition";
import "tailwind-merge";
import "tailwind-variants";
import "./OmrDiagnosticEvidencePanel-CvO7kqrK.js";
import "./Icon-Dal7PqKK.js";
import "./zoom-out-DsJCISKt.js";
import "./loader-circle-_UEl10Lx.js";
import "./circle-x-BQrhluGu.js";
import "./eye-off-Bx_raPQb.js";
import "./save-DAlMQk-Q.js";
import "./layers-_Ie6y7G_.js";
import "./chevron-down-CkQQZIhu.js";
import "./chevron-up-lhASJLRD.js";
import "./x-CnxrMbMn.js";
import "./check-DK45Y9SJ.js";
import "./circle-alert-jWXJ7oC0.js";
import "./arrow-left-DbWnQR3t.js";
import "./flask-conical-CPKNi8ld.js";
import "./circle-check-C59ZS7Zv.js";
import "./shield-check-CLSg-ako.js";
import "./arrow-right-CzynHiIo.js";
import "./clipboard-BXuTlHdz.js";
import "./dialog-footer-kNnwKT2h.js";
import "./Link-B7ks7rBK.js";
import "./circle-question-mark-DY-RU614.js";
import "./settings-2-B2lTvmrM.js";
import "./ellipsis-CPRDNN0k.js";
import "./list-DlzkoO4B.js";
import "./layout-grid-CXJfSBqV.js";
import "./file-image-BGlN21-8.js";
import "./noop-BQS7OFs8.js";
import "./create-id-CUJmDlea.js";
import "style-to-object";
import "./scroll-lock-B1ibcKMx.js";
import "./watch.svelte-BcPoqckX.js";
import "./is-C6cm64pF.js";
import "tabbable";
function OmrCameraDeck($$renderer, $$props) {
  $$renderer.component(($$renderer2) => {
    let {
      onCapture = null,
      busy = false,
      disabled = false,
      disabledReason = "Capture is currently unavailable.",
      pageCount = 1,
      currentPage = 1,
      registrationMode = "fiducial_anchored",
      captureRevision = 0,
      mode = "auto",
      onOpenGallery = null,
      galleryCount = 0,
      allowUpload = true,
      singleUpload = false,
      preflightState = "idle",
      preflight = null
    } = $$props;
    let videoEl = null;
    let canvasEl = null;
    let cameraState = "idle";
    let cameraDevices = [];
    let selectedDeviceId = "";
    let modeMenuOpen = false;
    let viewfinderResult = null;
    let viewfinderAvailable = true;
    let videoWidth = 0;
    let videoHeight = 0;
    const captureTargetKey = derived(() => `${currentPage}:${captureRevision}`);
    const viewfinder = createOmrViewfinderSession({
      video: () => videoEl,
      onResult: (result) => {
        viewfinderResult = result;
        if (result && (!result.quad || !result.gates.in_bounds || !result.gates.angle_ok || !result.gates.lighting_ok)) ;
      },
      onUnavailable: () => {
        viewfinderAvailable = false;
      },
      onCapture: snapSnapshot
    });
    function shortDeviceLabel(device, index) {
      const label = device.label.toLowerCase();
      if (label.includes("ultra") || label.includes("0.5x")) return "0.5x Wide";
      if (label.includes("tele") || label.includes("3x") || label.includes("2x")) return "3x Tele";
      if (label.includes("wide") || label.includes("1x") || label.includes("main")) return "1x Main";
      if (device.facing === "front") return "Selfie";
      if (label.includes("usb") || label.includes("uvc") || label.includes("scanner")) return "USB Cam";
      return `Cam ${index + 1}`;
    }
    function stopCamera() {
      viewfinder.stop();
      cameraState = "idle";
    }
    async function snapSnapshot({ quad }) {
      if (cameraState !== "ready" || busy || disabled || !videoEl || !onCapture) {
        return false;
      }
      if (!canvasEl) {
        canvasEl = document.createElement("canvas");
      }
      const width = videoEl.videoWidth;
      const height = videoEl.videoHeight;
      if (!width || !height) {
        return false;
      }
      canvasEl.width = width;
      canvasEl.height = height;
      const context = canvasEl.getContext("2d");
      if (!context) {
        return false;
      }
      context.drawImage(videoEl, 0, 0, width, height);
      const blob = await new Promise((resolve) => canvasEl?.toBlob(resolve, "image/jpeg", 0.92));
      if (!blob) {
        return false;
      }
      const file = new File([blob], `omr-capture-${Date.now()}.jpg`, { type: "image/jpeg" });
      const hint = registrationMode === "document_bound" ? await createOmrClientQuadHint(file, quad, width, height, currentPage) : null;
      const accepted = Boolean(await onCapture(file, hint));
      if (accepted) {
        captureTargetKey();
      }
      return accepted;
    }
    onDestroy(() => {
      stopCamera();
      viewfinder.stop();
    });
    $$renderer2.push(`<div class="omr-camera-deck border-border flex min-h-0 flex-1 flex-col overflow-hidden rounded-xl border bg-[#0d100f] text-[#f5f6f4]" data-omr-camera-deck=""><div class="camera-lens-strip flex shrink-0 items-center justify-between gap-1.5 border-b border-white/10 bg-black/40 px-3 py-2 text-xs"><div class="lens-pills flex items-center gap-1.5 overflow-x-auto">`);
    if (cameraDevices.length > 1) {
      $$renderer2.push("<!--[0-->");
      $$renderer2.push(`<!--[-->`);
      const each_array = ensure_array_like(cameraDevices);
      for (let index = 0, $$length = each_array.length; index < $$length; index++) {
        let device = each_array[index];
        $$renderer2.push(`<button type="button"${attr_class(`rounded-md border px-2.5 py-1 font-mono text-[11px] font-semibold transition-colors ${selectedDeviceId === device.id ? "border-primary bg-primary text-white shadow-xs" : "border-white/15 bg-white/5 text-white/70 hover:bg-white/10 hover:text-white"}`)}>${escape_html(shortDeviceLabel(device, index))}</button>`);
      }
      $$renderer2.push(`<!--]-->`);
    } else {
      $$renderer2.push("<!--[-1-->");
      $$renderer2.push(`<span class="font-mono text-[11px] text-white/60">Standard Camera (1x)</span>`);
    }
    $$renderer2.push(`<!--]--></div> `);
    if (cameraDevices.length > 1) {
      $$renderer2.push("<!--[0-->");
      $$renderer2.push(`<button type="button" class="flex shrink-0 items-center gap-1 rounded-md border border-white/15 bg-white/5 px-2 py-1 font-mono text-[11px] text-white hover:bg-white/15" title="Quick flip camera">`);
      Refresh_cw($$renderer2, { class: "size-3" });
      $$renderer2.push(`<!----> <span>Flip</span></button>`);
    } else {
      $$renderer2.push("<!--[-1-->");
    }
    $$renderer2.push(`<!--]--></div> <div class="viewfinder-stage relative flex min-h-64 flex-1 items-center justify-center overflow-hidden bg-radial from-[#171b19] to-[#0a0d0c] p-4"><div class="corner-bracket b-tl absolute top-4 left-4 size-6 border-t-2 border-l-2 border-emerald-400" aria-hidden="true"></div> <div class="corner-bracket b-tr absolute top-4 right-4 size-6 border-t-2 border-r-2 border-emerald-400" aria-hidden="true"></div> <div class="corner-bracket b-bl absolute bottom-4 left-4 size-6 border-b-2 border-l-2 border-emerald-400" aria-hidden="true"></div> <div class="corner-bracket b-br absolute right-4 bottom-4 size-6 border-r-2 border-b-2 border-emerald-400" aria-hidden="true"></div> `);
    if (cameraState === "ready") {
      $$renderer2.push("<!--[0-->");
      $$renderer2.push(`<div class="scan-guide absolute top-4 z-10 flex items-center gap-1.5 rounded-full border border-white/15 bg-black/60 px-3 py-1 font-mono text-[10px] tracking-wider text-emerald-300 uppercase">`);
      Scan_line($$renderer2, { class: "size-3" });
      $$renderer2.push(`<!----> <span>Align answer sheet within frame</span></div> <video autoplay="" playsinline="" muted="" class="absolute inset-0 size-full object-cover" aria-label="Live camera preview"></video> `);
      OmrViewfinderHud($$renderer2, {
        result: viewfinderResult,
        available: viewfinderAvailable,
        manual: mode === "manual",
        videoWidth,
        videoHeight
      });
      $$renderer2.push(`<!---->`);
    } else if (cameraState === "requesting") {
      $$renderer2.push("<!--[1-->");
      $$renderer2.push(`<div class="flex flex-col items-center gap-2 text-center text-white/80">`);
      Refresh_cw($$renderer2, { class: "text-primary size-6 animate-spin" });
      $$renderer2.push(`<!----> <span class="text-sm font-semibold">Opening camera feed…</span></div>`);
    } else {
      $$renderer2.push("<!--[-1-->");
      $$renderer2.push(`<div class="flex max-w-xs flex-col items-center gap-3 p-4 text-center text-white/80"><div class="rounded-full bg-white/5 p-3 text-white/60">`);
      Camera($$renderer2, { class: "size-8" });
      $$renderer2.push(`<!----></div> <div><strong class="block text-sm font-semibold text-white">Camera feed inactive</strong> <p class="mt-1 text-xs text-white/60">${escape_html(disabledReason || "Connect a webcam or use file upload.")}</p></div> <div class="flex flex-wrap items-center justify-center gap-2"><button type="button" class="inline-flex items-center gap-1.5 rounded-md border border-white/20 bg-white/10 px-3 py-1.5 text-xs font-semibold text-white hover:bg-white/20">`);
      Refresh_cw($$renderer2, { class: "size-3.5" });
      $$renderer2.push(`<!----> Retry camera</button> `);
      if (allowUpload) {
        $$renderer2.push("<!--[0-->");
        $$renderer2.push(`<button type="button" class="bg-primary hover:bg-primary/90 inline-flex items-center gap-1.5 rounded-md px-3 py-1.5 text-xs font-semibold text-white">`);
        Upload($$renderer2, { class: "size-3.5" });
        $$renderer2.push(`<!----> Upload image file</button>`);
      } else {
        $$renderer2.push("<!--[-1-->");
      }
      $$renderer2.push(`<!--]--></div></div>`);
    }
    $$renderer2.push(`<!--]--> `);
    if (pageCount > 1) {
      $$renderer2.push("<!--[0-->");
      $$renderer2.push(`<div class="absolute bottom-4 left-4 z-10 rounded-full border border-white/20 bg-black/60 px-3 py-1 font-mono text-[11px] font-bold text-white">Page ${escape_html(currentPage)} of ${escape_html(pageCount)}</div>`);
    } else {
      $$renderer2.push("<!--[-1-->");
    }
    $$renderer2.push(`<!--]--></div> <div class="camera-bottom-deck flex shrink-0 items-center justify-between gap-3 border-t border-white/10 bg-[#141817] px-4 py-3"><div>`);
    if (onOpenGallery) {
      $$renderer2.push("<!--[0-->");
      $$renderer2.push(`<button type="button" class="group flex flex-col items-center gap-0.5 rounded-lg border-0 bg-transparent px-2.5 py-1 text-white/70 hover:text-white lg:hidden" aria-label="Open scans gallery"><div class="relative flex items-center justify-center">`);
      Image($$renderer2, { class: "size-5 transition-transform group-hover:scale-105" });
      $$renderer2.push(`<!----> `);
      if (galleryCount > 0) {
        $$renderer2.push("<!--[0-->");
        $$renderer2.push(`<span class="bg-primary py-0.2 absolute -top-1.5 -right-2 rounded-full px-1.5 font-mono text-[9px] font-bold text-white">${escape_html(galleryCount)}</span>`);
      } else {
        $$renderer2.push("<!--[-1-->");
      }
      $$renderer2.push(`<!--]--></div> <span class="font-mono text-[9px] tracking-wider uppercase">Scans</span></button>`);
    } else if (allowUpload) {
      $$renderer2.push("<!--[1-->");
      $$renderer2.push(`<button type="button" class="flex items-center gap-1.5 rounded-md border border-white/15 bg-white/5 px-2.5 py-1.5 text-xs font-semibold text-white/80 hover:bg-white/10 hover:text-white"${attr("disabled", disabled || busy, true)}>`);
      Upload($$renderer2, { class: "size-3.5" });
      $$renderer2.push(`<!----> <span>Upload</span></button>`);
    } else {
      $$renderer2.push("<!--[-1-->");
    }
    $$renderer2.push(`<!--]--></div> <div class="flex flex-col items-center"><button type="button" class="group relative flex size-14 items-center justify-center rounded-full border-4 border-white/80 bg-transparent p-1 transition-transform active:scale-90 disabled:opacity-40"${attr("aria-label", pageCount > 1 ? `Capture page ${currentPage} of ${pageCount}` : "Capture sheet")}${attr("disabled", cameraState !== "ready" || busy || disabled, true)}><div${attr_class(`size-full rounded-full transition-colors ${mode === "batch" ? "bg-primary" : "bg-white group-hover:bg-white/90"}`)}></div></button></div> <div class="relative"><button type="button" class="flex items-center gap-1.5 rounded-full border border-white/15 bg-white/5 px-3 py-1.5 font-mono text-xs font-semibold text-white hover:bg-white/10" aria-haspopup="menu"${attr("aria-expanded", modeMenuOpen)}><span${attr_class(`size-2 rounded-full ${mode === "auto" ? "bg-emerald-400" : mode === "manual" ? "bg-amber-400" : "bg-blue-400"}`)}></span> <span class="capitalize">${escape_html(mode)}</span></button> `);
    {
      $$renderer2.push("<!--[-1-->");
    }
    $$renderer2.push(`<!--]--></div></div> `);
    if (allowUpload) {
      $$renderer2.push("<!--[0-->");
      $$renderer2.push(`<input type="file" accept="image/jpeg,image/png,image/webp,image/tiff"${attr("multiple", !singleUpload, true)} class="hidden"/>`);
    } else {
      $$renderer2.push("<!--[-1-->");
    }
    $$renderer2.push(`<!--]--></div>`);
    bind_props($$props, { mode });
  });
}
function OmrSettingsModal($$renderer, $$props) {
  $$renderer.component(($$renderer2) => {
    let {
      open,
      busy = false,
      answer_sheets = [],
      booklets = [],
      onClose
    } = $$props;
    let versionId = null;
    let bookletId = null;
    let setCode = null;
    const sameId = (left, right) => left !== null && right !== null;
    let selectedVersion = derived(() => answer_sheets.find((version) => sameId(version.id, versionId)) ?? null);
    let compatibleBooklets = derived(() => booklets.filter((booklet) => booklet.template_version_id == null || sameId(booklet.template_version_id, versionId)));
    let selectedBooklet = derived(() => compatibleBooklets().find((booklet) => sameId(booklet.id, bookletId)) ?? null);
    let sets = derived(() => (selectedBooklet()?.sets ?? []).map((entry) => {
      if (typeof entry === "string") return entry;
      return entry.code ?? entry.set_code ?? entry.label ?? entry.name ?? "";
    }).filter((code) => typeof code === "string" && code.trim() !== ""));
    let canApply = derived(() => !busy && versionId !== null);
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
              class: "bg-card text-foreground max-h-[calc(100dvh-2rem)] w-full max-w-lg gap-5 overflow-y-auto p-6 sm:max-w-lg",
              children: ($$renderer4) => {
                $$renderer4.push(`<header><div class="flex items-center justify-between gap-4">`);
                if (Dialog_title) {
                  $$renderer4.push("<!--[-->");
                  Dialog_title($$renderer4, {
                    id: "omr-settings-title",
                    class: "text-foreground text-base font-semibold",
                    children: ($$renderer5) => {
                      $$renderer5.push(`<!---->Exam material settings`);
                    },
                    $$slots: { default: true }
                  });
                  $$renderer4.push("<!--]-->");
                } else {
                  $$renderer4.push("<!--[!-->");
                  $$renderer4.push("<!--]-->");
                }
                $$renderer4.push(` <button type="button" class="text-muted-foreground hover:bg-muted hover:text-foreground focus-visible:outline-primary rounded-md px-2 py-1 text-lg leading-none focus-visible:outline focus-visible:outline-2" aria-label="Close settings">×</button></div> `);
                if (Dialog_description) {
                  $$renderer4.push("<!--[-->");
                  Dialog_description($$renderer4, {
                    class: "text-muted-foreground mt-1 text-xs",
                    children: ($$renderer5) => {
                      $$renderer5.push(`<!---->Select the Answer Sheet, Booklet, and Set for a new diagnostic
                run. The current run stays active until the replacement is
                accepted.`);
                    },
                    $$slots: { default: true }
                  });
                  $$renderer4.push("<!--]-->");
                } else {
                  $$renderer4.push("<!--[!-->");
                  $$renderer4.push("<!--]-->");
                }
                $$renderer4.push(`</header> <div class="max-h-[60vh] space-y-5 overflow-y-auto"><fieldset class="space-y-2"${attr("disabled", busy, true)}><legend class="text-foreground text-xs font-semibold">Answer Sheet</legend> `);
                if (answer_sheets.length === 0) {
                  $$renderer4.push("<!--[0-->");
                  $$renderer4.push(`<p class="border-border text-muted-foreground rounded-lg border p-3 text-sm">No answer sheet versions are available.</p>`);
                } else {
                  $$renderer4.push("<!--[-1-->");
                  $$renderer4.push(`<div class="space-y-2"><!--[-->`);
                  const each_array = ensure_array_like(answer_sheets);
                  for (let $$index = 0, $$length = each_array.length; $$index < $$length; $$index++) {
                    let version = each_array[$$index];
                    $$renderer4.push(`<label${attr_class("border-border hover:bg-muted/40 block cursor-pointer rounded-xl border p-3 transition-colors", void 0, { "border-primary": sameId(version.id, versionId) })}><input class="sr-only" type="radio" name="omr-answer-sheet"${attr("value", version.id)}${attr("checked", sameId(version.id, versionId), true)}/> <div class="flex flex-wrap items-center justify-between gap-2"><span class="text-foreground text-sm font-semibold">${escape_html(version.display_name ?? version.label ?? `Version ${version.id}`)}</span> <span class="flex flex-wrap gap-1.5"><span class="bg-muted text-muted-foreground rounded px-2 py-0.5 text-[10px] font-semibold">${escape_html(version.registration_mode === "document_bound" ? "Document Boundary" : version.registration_mode === "fiducial_anchored" ? "Corner Markers" : version.registration_mode ?? "Registration unspecified")}</span> <span class="bg-muted text-muted-foreground rounded px-2 py-0.5 text-[10px] font-semibold">${escape_html(version.qr_mode ?? "QR unspecified")}</span></span></div></label>`);
                  }
                  $$renderer4.push(`<!--]--></div>`);
                }
                $$renderer4.push(`<!--]--></fieldset> <fieldset class="space-y-2"${attr("disabled", busy || !selectedVersion(), true)}><legend class="text-foreground text-xs font-semibold">Booklet</legend> `);
                if (compatibleBooklets().length === 0) {
                  $$renderer4.push("<!--[0-->");
                  $$renderer4.push(`<p class="border-border text-muted-foreground rounded-lg border p-3 text-sm">No compatible booklets are available for this answer
                        sheet.</p>`);
                } else {
                  $$renderer4.push("<!--[-1-->");
                  $$renderer4.push(`<div class="space-y-2"><!--[-->`);
                  const each_array_1 = ensure_array_like(compatibleBooklets());
                  for (let $$index_1 = 0, $$length = each_array_1.length; $$index_1 < $$length; $$index_1++) {
                    let booklet = each_array_1[$$index_1];
                    $$renderer4.push(`<label${attr_class("border-border text-foreground hover:bg-muted/40 block cursor-pointer rounded-xl border p-3 text-sm font-semibold", void 0, { "border-primary": sameId(booklet.id, bookletId) })}><input class="sr-only" type="radio" name="omr-booklet"${attr("value", booklet.id)}${attr("checked", sameId(booklet.id, bookletId), true)}/> ${escape_html(booklet.name ?? `Booklet ${booklet.id}`)}</label>`);
                  }
                  $$renderer4.push(`<!--]--></div>`);
                }
                $$renderer4.push(`<!--]--></fieldset> <fieldset class="space-y-2"${attr("disabled", busy || !selectedBooklet(), true)}><legend class="text-foreground text-xs font-semibold">Booklet Set</legend> `);
                if (sets().length === 0) {
                  $$renderer4.push("<!--[0-->");
                  $$renderer4.push(`<p class="border-border text-muted-foreground rounded-lg border p-3 text-sm">Select a booklet with available sets.</p>`);
                } else {
                  $$renderer4.push("<!--[-1-->");
                  $$renderer4.push(`<div class="flex flex-wrap gap-2"><!--[-->`);
                  const each_array_2 = ensure_array_like(sets());
                  for (let $$index_2 = 0, $$length = each_array_2.length; $$index_2 < $$length; $$index_2++) {
                    let code = each_array_2[$$index_2];
                    $$renderer4.push(`<button type="button"${attr_class("focus-visible:outline-primary rounded-full border px-3 py-1.5 text-sm font-semibold transition-colors focus-visible:outline focus-visible:outline-2", void 0, {
                      "border-primary": setCode === code,
                      "bg-primary": setCode === code,
                      "text-primary-foreground": setCode === code,
                      "border-border": setCode !== code,
                      "text-foreground": setCode !== code
                    })}${attr("aria-pressed", setCode === code)}>Set ${escape_html(code)}</button>`);
                  }
                  $$renderer4.push(`<!--]--></div>`);
                }
                $$renderer4.push(`<!--]--></fieldset></div> <footer class="border-border flex flex-col-reverse justify-end gap-2 border-t pt-4 sm:flex-row"><button type="button" class="border-border text-foreground hover:bg-muted rounded-lg border px-4 py-2 text-sm font-semibold">Cancel</button> <button type="button" class="bg-primary text-primary-foreground rounded-lg px-4 py-2 text-sm font-semibold disabled:cursor-not-allowed disabled:opacity-50"${attr("disabled", !canApply(), true)}>Apply &amp; Re-calibrate</button></footer>`);
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
function diagnosticRecord(value) {
  return value !== null && typeof value === "object" && !Array.isArray(value) ? value : {};
}
function diagnosticArray(value) {
  return Array.isArray(value) ? value : [];
}
function diagnosticText(value, fallback = null) {
  return typeof value === "string" && value.trim() !== "" ? value.trim() : fallback;
}
function diagnosticCode(value) {
  if (typeof value !== "string" && typeof value !== "number") {
    return null;
  }
  const code = String(value).trim();
  return code === "" ? null : code;
}
function diagnosticNumber(value, fallback = null) {
  if (typeof value === "number" && Number.isFinite(value)) {
    return value;
  }
  if (typeof value === "string" && value.trim() !== "") {
    const parsed = Number(value);
    return Number.isFinite(parsed) ? parsed : fallback;
  }
  return fallback;
}
function diagnosticInteger(value, fallback = null) {
  const parsed = diagnosticNumber(value, null);
  return parsed !== null && Number.isSafeInteger(parsed) && parsed >= 0 ? parsed : fallback;
}
function diagnosticPositiveInteger(value, fallback = null) {
  const parsed = diagnosticInteger(value, null);
  return parsed !== null && parsed > 0 ? parsed : fallback;
}
function diagnosticFirstArray(...values) {
  const first = values.find((value) => Array.isArray(value) && value.length > 0);
  if (first) return first;
  return values.find((value) => Array.isArray(value)) ?? [];
}
function diagnosticUniqueCodes(value) {
  const seen = /* @__PURE__ */ new Set();
  const codes = [];
  for (const entry of diagnosticArray(value)) {
    const code = diagnosticCode(diagnosticRecord(entry).code ?? diagnosticRecord(entry).option ?? entry);
    if (code === null || seen.has(code)) continue;
    seen.add(code);
    codes.push(code);
  }
  return codes;
}
function diagnosticState(value) {
  const state = diagnosticText(value)?.toLowerCase() ?? null;
  return {
    match: "marked",
    selected: "marked",
    exact: "marked",
    multiple: "ambiguous",
    multi_mark: "ambiguous"
  }[state] ?? (["marked", "blank", "ambiguous", "unreadable", "unresolved"].includes(state) ? state : null);
}
function diagnosticObservation(value) {
  const raw = diagnosticRecord(value);
  const code = diagnosticCode(raw.code ?? raw.option);
  return code === null ? null : {
    code,
    mean: diagnosticNumber(raw.mean ?? raw.bubble_mean, null),
    classification: diagnosticText(raw.classification ?? raw.threshold_status, null)
  };
}
function diagnosticObservations(row, proposal, nested, detected) {
  const source = diagnosticFirstArray(diagnosticRecord(row.machine).option_observations, nested.option_observations, detected.option_observations, proposal.option_observations, row.option_observations, row.option_evidence);
  return diagnosticArray(source).map(diagnosticObservation).filter(Boolean);
}
function diagnosticOptions(row, proposal, nested, detected, observations) {
  const geometry = diagnosticRecord(row.geometry ?? proposal.geometry);
  const observationByCode = new Map(observations.map((observation) => [observation.code, observation]));
  const sources = [
    row.options,
    diagnosticRecord(row.machine).options,
    nested.options,
    detected.options,
    proposal.options,
    geometry.options,
    row.option_codes,
    proposal.option_codes,
    observations,
    diagnosticUniqueCodes(diagnosticFirstArray(diagnosticRecord(row.machine).observed_options, nested.observed_options, detected.observed_options, proposal.observed_options, row.observed_options))
  ];
  const seen = /* @__PURE__ */ new Set();
  const options = [];
  for (const source of sources) {
    for (const value of diagnosticArray(source)) {
      const raw = diagnosticRecord(value);
      const code = diagnosticCode(raw.code ?? raw.option ?? raw.value ?? value);
      if (code === null || seen.has(code)) continue;
      const observation = observationByCode.get(code);
      seen.add(code);
      options.push({
        code,
        region: raw.region ?? raw.rect ?? raw.option_rect ?? raw.geometry ?? null,
        mean: diagnosticNumber(raw.mean ?? raw.bubble_mean ?? observation?.mean, null),
        threshold_delta: diagnosticNumber(raw.threshold_delta, null),
        threshold_status: diagnosticText(raw.threshold_status ?? raw.classification, observation?.classification ?? null),
        crop_png_base64: null
      });
    }
  }
  return options;
}
function diagnosticAsset(source, fallback, url, kind) {
  const primary = diagnosticRecord(source);
  const secondary = diagnosticRecord(fallback);
  const primaryMetadata = diagnosticRecord(primary.metadata);
  const secondaryMetadata = diagnosticRecord(secondary.metadata);
  const sha256 = diagnosticText(primary.sha256 ?? primary.hash ?? primary.asset_hash ?? secondary.sha256 ?? secondary.hash ?? secondary.asset_hash, null);
  const assetUrl = diagnosticText(url ?? primary.url ?? primary.href ?? secondary.url ?? secondary.href, null);
  const asset = {
    id: diagnosticPositiveInteger(primary.id ?? secondary.id, null),
    kind: diagnosticText(primary.kind ?? primary.type ?? secondary.kind ?? secondary.type, kind),
    page_no: diagnosticNumber(primary.page_no ?? primary.page ?? secondary.page_no ?? secondary.page, null),
    mime: diagnosticText(primary.mime ?? secondary.mime, null),
    width: diagnosticPositiveInteger(primary.width ?? primaryMetadata.width ?? secondary.width ?? secondaryMetadata.width, null),
    height: diagnosticPositiveInteger(primary.height ?? primaryMetadata.height ?? secondary.height ?? secondaryMetadata.height, null),
    sha256,
    url: assetUrl
  };
  return Object.values(asset).some((value) => value !== null && value !== void 0) ? asset : null;
}
function diagnosticReview(value, machineHash) {
  const raw = diagnosticRecord(value);
  const decision = diagnosticText(raw.decision, null);
  if (decision === null) return null;
  const reviewHash = diagnosticText(raw.evidence_hash ?? raw.reviewed_evidence_hash, null);
  const reviewer = diagnosticRecord(raw.reviewer);
  const reviewerId = reviewer.id ?? raw.reviewer_id ?? raw.actor_id ?? null;
  const reviewerName = diagnosticText(reviewer.display_name ?? reviewer.name ?? raw.reviewer_name ?? raw.actor_name, null);
  return {
    id: diagnosticPositiveInteger(raw.id, null),
    diagnostic_item_evidence_id: diagnosticPositiveInteger(raw.diagnostic_item_evidence_id ?? raw.item_evidence_id, null),
    revision: diagnosticInteger(raw.revision, null),
    parent_revision: diagnosticInteger(raw.parent_revision, null),
    decision,
    selected_option: diagnosticCode(raw.selected_option ?? raw.final_option),
    reason_code: diagnosticText(raw.reason_code ?? raw.reason, null),
    note: diagnosticText(raw.note, null),
    reviewer: reviewerId !== null || reviewerName !== null ? { id: reviewerId, display_name: reviewerName } : null,
    reviewed_at: diagnosticText(raw.reviewed_at ?? raw.created_at, null),
    evidence_hash: reviewHash,
    stale: raw.stale === true || raw.review_stale === true || reviewHash !== null && machineHash !== null && reviewHash.toLowerCase() !== machineHash.toLowerCase()
  };
}
function diagnosticReviewRows(row, machineHash) {
  const raw = diagnosticRecord(row);
  const values = [
    raw.latest_review,
    raw.current_review,
    raw.review,
    ...diagnosticArray(raw.reviews).toReversed()
  ].filter(Boolean);
  const seen = /* @__PURE__ */ new Set();
  const reviews = [];
  for (const value of values) {
    const review = diagnosticReview(value, machineHash);
    if (!review) continue;
    const key = review.revision ?? review.id ?? reviews.length;
    if (seen.has(key)) continue;
    seen.add(key);
    reviews.push(review);
  }
  return reviews.toSorted((left, right) => (right.revision ?? 0) - (left.revision ?? 0));
}
function mapDiagnosticEvidenceItem(item, capture = {}, context = {}) {
  const row = diagnosticRecord(item);
  const captureRecord = diagnosticRecord(capture);
  const evidence = diagnosticRecord(context.evidence);
  const proposal = diagnosticRecord(row.machine_proposal);
  const nested = diagnosticRecord(proposal.detected);
  const detected = diagnosticRecord(row.detected);
  const machineSource = diagnosticRecord(row.machine);
  const selectedCandidate = machineSource.selected_option ?? nested.selected_option ?? detected.selected_option ?? proposal.selected_option ?? row.option_code;
  const selectedCode = diagnosticCode(selectedCandidate);
  const state = diagnosticState(machineSource.state ?? nested.state ?? detected.state ?? proposal.state ?? proposal.status ?? row.state) ?? (selectedCode !== null ? "marked" : "unresolved");
  const selectedOption = state === "marked" ? selectedCode : null;
  const observations = diagnosticObservations(row, proposal, nested, detected);
  const observedOptions = diagnosticUniqueCodes(diagnosticFirstArray(machineSource.observed_options, nested.observed_options, detected.observed_options, proposal.observed_options, row.observed_options));
  if (observedOptions.length === 0 && selectedOption !== null) {
    observedOptions.push(selectedOption);
  }
  const confidence = diagnosticNumber(machineSource.confidence ?? nested.confidence ?? detected.confidence ?? row.machine_confidence ?? proposal.confidence ?? row.confidence, null);
  const evidenceHash = diagnosticText(row.evidence_hash ?? machineSource.evidence_hash ?? nested.evidence_hash ?? detected.evidence_hash ?? proposal.evidence_hash, null);
  const issueCodes = diagnosticUniqueCodes(diagnosticFirstArray(machineSource.issue_codes, nested.issue_codes, detected.issue_codes, proposal.issue_codes, row.issue_codes));
  const options = diagnosticOptions(row, proposal, nested, detected, observations);
  const evidenceAssets = diagnosticRecord(evidence.assets);
  const alignedEvidence = diagnosticRecord(context.alignedEvidenceAsset ?? evidenceAssets.aligned);
  const alignedCapture = diagnosticRecord(context.alignedCaptureAsset ?? diagnosticRecord(captureRecord.assets).aligned);
  const pageAsset = diagnosticAsset(row.page_asset ?? alignedEvidence, alignedCapture, diagnosticRecord(context.assetUrls).aligned, "aligned_page");
  const cropAsset = diagnosticAsset(row.asset ?? row.crop_asset, null, diagnosticRecord(context.assetUrls).crop, "dedicated_crop");
  const geometrySource = diagnosticRecord(row.geometry ?? proposal.geometry);
  const itemRect = geometrySource.item_rect ?? geometrySource.itemRect ?? row.region ?? row.item_rect ?? proposal.region ?? null;
  const cropRect = geometrySource.crop_rect ?? geometrySource.cropRect ?? row.crop_rect ?? itemRect;
  const evidenceVersion = diagnosticText(row.evidence_version ?? proposal.evidence_version ?? context.evidenceVersion ?? diagnosticRecord(evidence).evidence_version, "v1") ?? "v1";
  const pageNo = diagnosticPositiveInteger(row.page_no ?? row.page ?? proposal.page_no ?? context.pageNo ?? diagnosticRecord(evidence).page_no, 1) ?? 1;
  const geometry = {
    item_rect: itemRect,
    crop_rect: cropRect,
    options,
    aligned_asset: pageAsset,
    crop_asset: cropAsset,
    crop_source: row.crop_source ?? geometrySource.crop_source ?? null,
    geometry_manifest_hash: diagnosticText(row.geometry_manifest_hash ?? geometrySource.geometry_manifest_hash ?? geometrySource.geometry_hash ?? context.geometryManifestHash, null),
    evidence_version: evidenceVersion,
    page_no: pageNo
  };
  const reviews = diagnosticReviewRows(row, evidenceHash);
  const currentReview = reviews[0] ?? null;
  const parentVersion = diagnosticInteger(row.parent_version ?? row.version ?? captureRecord.version ?? context.parentVersion, 0);
  const itemRevision = diagnosticInteger(row.item_revision ?? currentReview?.revision, 0);
  const captureId = diagnosticPositiveInteger(row.capture_id ?? captureRecord.id ?? context.captureId, null);
  const itemId = diagnosticCode(row.item_id ?? row.item_key ?? row.native_label ?? proposal.item_id ?? proposal.native_label ?? row.label);
  const machine = {
    state,
    selected_option: selectedOption,
    confidence,
    observed_options: observedOptions,
    option_observations: observations,
    issue_codes: issueCodes,
    evidence_hash: evidenceHash
  };
  return {
    id: diagnosticPositiveInteger(row.id, null),
    capture_id: captureId,
    item_id: itemId,
    native_label: diagnosticText(row.native_label ?? row.item_key ?? itemId, itemId),
    label: diagnosticText(row.label ?? row.item_key ?? itemId, itemId),
    position: diagnosticInteger(row.position, null),
    area_code: diagnosticText(row.area_code ?? row.area_key ?? proposal.area_code, null),
    question_no: row.question_no === null || row.question_no === void 0 ? null : diagnosticInteger(row.question_no, null),
    page_no: pageNo,
    region: itemRect,
    crop_rect: cropRect,
    options,
    machine,
    detected: { ...machine },
    machine_proposal: { ...machine, options },
    state,
    selected_option: selectedOption,
    confidence,
    issue_codes: issueCodes,
    option_evidence: observations,
    evidence_hash: evidenceHash,
    evidence_version: evidenceVersion,
    parent_version: parentVersion,
    version: parentVersion,
    item_revision: itemRevision,
    review: currentReview,
    current_review: currentReview,
    review_history: reviews,
    reviewable: context.reviewable !== false,
    asset: cropAsset,
    page_asset: pageAsset,
    crop_source: geometry.crop_source,
    geometry
  };
}
function mapDiagnosticEvidencePage(input = {}) {
  const capture = diagnosticRecord(input.capture);
  const evidence = diagnosticRecord(input.captureEvidence);
  const rawItems = diagnosticArray(input.items);
  const assetUrls = diagnosticRecord(input.assetUrls);
  const captureId = diagnosticPositiveInteger(capture.id ?? input.captureId, null);
  if (captureId === null) return null;
  const evidenceAssets = diagnosticRecord(evidence.assets);
  const alignedEvidenceAsset = diagnosticRecord(evidenceAssets.aligned ?? evidence.aligned_image);
  const alignedCaptureAsset = diagnosticRecord(diagnosticRecord(capture.assets).aligned);
  const firstRawItem = diagnosticRecord(rawItems[0]);
  const pageNo = diagnosticPositiveInteger(evidence.page_no ?? firstRawItem.page_no ?? firstRawItem.page, 1) ?? 1;
  const mappedItems = rawItems.map((item) => mapDiagnosticEvidenceItem(item, capture, {
    evidence,
    captureId,
    pageNo,
    parentVersion: capture.version ?? input.parentVersion,
    evidenceVersion: evidence.evidence_version,
    assetUrls,
    alignedEvidenceAsset,
    alignedCaptureAsset,
    geometryManifestHash: evidence.geometry_manifest_hash ?? diagnosticRecord(evidence.geometry).geometry_manifest_hash,
    reviewable: input.reviewable !== false
  }));
  const firstItem = mappedItems[0] ?? null;
  const evidenceVersion = diagnosticText(evidence.evidence_version ?? firstItem?.evidence_version, "v1") ?? "v1";
  const captureEvidenceHash = diagnosticText(capture.evidence_hash ?? evidence.evidence_hash, null);
  const pageAsset = diagnosticAsset(alignedEvidenceAsset, alignedCaptureAsset, assetUrls.aligned, "aligned_page");
  const summary = diagnosticRecord(evidence.summary);
  const needsReviewTotal = mappedItems.filter((item) => {
    const machine = diagnosticRecord(item.machine);
    return machine.state !== "marked" || machine.observed_options.length !== 1 || machine.issue_codes.length > 0;
  }).length;
  const reviewedTotal = mappedItems.filter((item) => item.review !== null).length;
  const rawGroups = diagnosticArray(evidence.groups ?? evidence.group_navigation).map((group) => diagnosticRecord(group)).map((group) => ({
    key: diagnosticText(group.key ?? group.area_key ?? group.group_type, null),
    label: diagnosticText(group.label ?? group.key ?? group.area_key ?? group.group_type, "Evidence") ?? "Evidence",
    group_type: diagnosticText(group.group_type, null),
    area_key: diagnosticText(group.area_key, null),
    area_position: diagnosticNumber(group.area_position, null),
    total: diagnosticInteger(group.total, 0) ?? 0,
    mismatch_total: diagnosticInteger(group.mismatch_total, 0) ?? 0,
    page_count: diagnosticPositiveInteger(group.page_count, 1) ?? 1
  })).filter((group) => group.key !== null);
  const groups = rawGroups.length > 0 ? rawGroups : [
    {
      key: "all",
      label: "All areas",
      group_type: "all",
      area_key: null,
      area_position: null,
      total: mappedItems.length,
      mismatch_total: needsReviewTotal,
      page_count: 1
    }
  ];
  const width = diagnosticPositiveInteger(pageAsset?.width ?? alignedEvidenceAsset.width ?? diagnosticRecord(evidence.aligned_image).width, null);
  const height = diagnosticPositiveInteger(pageAsset?.height ?? alignedEvidenceAsset.height ?? diagnosticRecord(evidence.aligned_image).height, null);
  return {
    method: diagnosticText(evidence.method, "pre_shaded") ?? "pre_shaded",
    capture_id: captureId,
    area: "all",
    total: mappedItems.length,
    mismatch_total: diagnosticInteger(evidence.mismatch_total ?? summary.mismatch ?? summary.ambiguous, needsReviewTotal) ?? needsReviewTotal,
    needs_review_total: diagnosticInteger(evidence.needs_review_total, needsReviewTotal) ?? needsReviewTotal,
    reviewed_total: diagnosticInteger(evidence.reviewed_total, reviewedTotal) ?? reviewedTotal,
    page: 1,
    per_page: 50,
    page_count: 1,
    filter: "all",
    evidence_version: evidenceVersion,
    evidence_hash: captureEvidenceHash,
    capture_page_no: pageNo,
    parent_version: diagnosticInteger(capture.version, 0),
    reviewable: input.reviewable !== false,
    blind_review: true,
    presentation: "blind",
    lifecycle: diagnosticText(capture.status, null),
    status: diagnosticText(capture.status, null),
    review_note_max: diagnosticInteger(evidence.review_note_max, 500) ?? 500,
    review_reasons: diagnosticArray(evidence.review_reasons ?? evidence.reasons).map((reason) => {
      const value = diagnosticRecord(reason);
      return {
        code: diagnosticText(value.code, null),
        label: diagnosticText(value.label ?? value.name ?? value.code, null) ?? ""
      };
    }).filter((reason) => reason.code !== null && reason.label !== ""),
    groups,
    items: mappedItems,
    lazy_asset_urls: {
      input: diagnosticText(assetUrls.input, null),
      aligned: diagnosticText(assetUrls.aligned, null),
      overlay: diagnosticText(assetUrls.overlay, null)
    },
    sheet_map: {
      page_no: pageNo,
      width,
      height,
      sha256: pageAsset?.sha256 ?? null,
      items: mappedItems,
      document_fields: []
    }
  };
}
function diagnosticEvidenceVersion(value, fallback = 1) {
  const parsed = diagnosticInteger(value, null);
  if (parsed !== null && parsed > 0) return parsed;
  const text = diagnosticText(value, null);
  const match = text?.match(/(\d+)/);
  return match ? Number(match[1]) : fallback;
}
function buildDiagnosticReviewPayload(payload = {}, row = {}, capture = {}, fallbackIdempotencyKey = null) {
  const payloadRecord = diagnosticRecord(payload);
  const rowRecord = diagnosticRecord(row);
  const captureRecord = diagnosticRecord(capture);
  const decision = diagnosticText(payloadRecord.decision, null);
  const rowMachine = diagnosticRecord(rowRecord.machine);
  const rowDetected = diagnosticRecord(rowRecord.detected);
  const rowProposal = diagnosticRecord(rowRecord.machine_proposal);
  const selectableOption = diagnosticCode(payloadRecord.selected_option ?? payloadRecord.selectable_option ?? payloadRecord.final_option ?? (decision === "confirm_machine" ? rowMachine.selected_option ?? rowDetected.selected_option ?? rowProposal.selected_option ?? rowRecord.option_code : null));
  const finalOption = decision === "confirm_machine" || decision === "set_option" ? selectableOption : null;
  const evidenceHash = diagnosticText(payloadRecord.reviewed_machine_evidence_hash ?? rowMachine.evidence_hash ?? rowDetected.evidence_hash ?? rowRecord.evidence_hash ?? rowProposal.evidence_hash, null);
  const expectedCaptureVersion = diagnosticInteger(captureRecord.version ?? rowRecord.parent_version ?? rowRecord.version, 0);
  const expectedItemRevision = diagnosticInteger(payloadRecord.expected_item_revision ?? rowRecord.item_revision ?? diagnosticRecord(rowRecord.review).revision, 0);
  const expectedEvidenceVersion = diagnosticEvidenceVersion(payloadRecord.expected_evidence_version ?? rowRecord.evidence_version, 1);
  const idempotencyKey = diagnosticText(payloadRecord.idempotency_key ?? fallbackIdempotencyKey, null);
  return {
    decision,
    final_option: finalOption,
    reason: payloadRecord.reason_code ?? payloadRecord.reason ?? null,
    note: payloadRecord.note ?? null,
    expected_capture_version: expectedCaptureVersion,
    expected_item_revision: expectedItemRevision,
    expected_evidence_version: expectedEvidenceVersion,
    reviewed_evidence_hash: evidenceHash,
    idempotency_key: idempotencyKey
  };
}
function OmrTest($$renderer, $$props) {
  $$renderer.component(($$renderer2) => {
    let {
      page_state = "needs_answer_sheet",
      answer_sheets = [],
      booklets = []
    } = $$props;
    let selectedVersionId = answer_sheets[0]?.id ?? null;
    const selectedAnswerSheet = derived(() => answer_sheets.find((sheet) => Number(sheet.id) === Number(selectedVersionId)) ?? null);
    const selectedAnswerSheetQrDisabled = derived(() => selectedAnswerSheet()?.qr_mode === "qr_disabled");
    const compatibleBooklets = derived(() => booklets.filter((booklet) => Number(booklet.template_version_id) === Number(selectedVersionId)));
    let selectedBookletId = booklets[0]?.id ?? null;
    let selectedSetCode = booklets[0]?.sets?.[0]?.code ?? null;
    const selectedBooklet = derived(() => compatibleBooklets().find((booklet) => Number(booklet.id) === Number(selectedBookletId)) ?? compatibleBooklets()[0] ?? null);
    let settingsModalOpen = false;
    let projection = null;
    let workbenchClient = null;
    let runPublicId = null;
    let selectedSheetId = null;
    let starting = false;
    let commandBusy = false;
    let refreshing = false;
    let runError = null;
    let reviewDirty = false;
    let pendingRetake = null;
    const runBase = derived(() => runPublicId ? `/setup/exam-materials/omr-test/runs/${encodeURIComponent(runPublicId)}` : null);
    const sheets = derived(() => (projection?.sheets ?? []).map((sheet) => ({
      ...sheet,
      thumbnail_url: diagnosticAssetUrl(asRecord(sheet.assets).input) ?? diagnosticText(asRecord(sheet.lazy_asset_urls).input, null)
    })));
    const selectedSheet = derived(() => sheets().find((sheet) => Number(sheet.id) === Number(selectedSheetId)) ?? sheets()[sheets().length - 1] ?? null);
    const pendingCapture = derived(() => {
      const candidate = asRecord(asRecord(projection).context).pending_capture;
      return selectedAnswerSheetQrDisabled() && candidate !== null && typeof candidate === "object" && !Array.isArray(candidate) ? candidate : null;
    });
    const pendingCaptureExpired = derived(() => pendingCapture()?.status === "expired" || String(pendingCapture()?.resolution_code ?? "").toLowerCase().includes("expired"));
    const connectionState = derived(() => starting || commandBusy || refreshing ? "connecting" : runError ? "error" : "live");
    const evidencePage = derived(() => {
      const sheet = selectedSheet();
      if (!sheet || !projection) return null;
      const captureId = positiveInteger(sheet.id);
      if (captureId === null) return null;
      const evidence = asRecord(projection.evidence);
      const captures = Array.isArray(evidence.captures) ? evidence.captures : [];
      const capture = asRecord(captures.find((candidate) => Number(asRecord(candidate).capture_id) === captureId));
      const captureEvidence = asRecord(capture.evidence ?? sheet.evidence);
      const projectedItems = Array.isArray(evidence.items) ? evidence.items.filter((item) => Number(asRecord(item).capture_id) === captureId) : [];
      const capturedItems = projectedItems.length > 0 ? projectedItems : Array.isArray(captureEvidence.items) ? captureEvidence.items : [];
      const assetUrls = {
        input: diagnosticAssetUrl(sheet.assets?.input),
        aligned: diagnosticAssetUrl(sheet.assets?.aligned),
        overlay: diagnosticAssetUrl(sheet.assets?.overlay)
      };
      return mapDiagnosticEvidencePage({
        capture: { ...sheet, ...capture, id: captureId },
        captureEvidence,
        items: capturedItems,
        assetUrls,
        reviewable: capturedItems.length > 0 && projection.commands.includes("review_save")
      });
    });
    function asRecord(value) {
      return value !== null && typeof value === "object" && !Array.isArray(value) ? value : {};
    }
    function positiveInteger(value) {
      const number = Number(value);
      return Number.isSafeInteger(number) && number > 0 ? number : null;
    }
    function diagnosticAssetUrl(asset) {
      const assetId = positiveInteger(asRecord(asset).id);
      return assetId !== null && runBase() ? `${runBase()}/assets/${assetId}` : null;
    }
    function diagnosticRunUrls(publicId) {
      const base = `/setup/exam-materials/omr-test/runs/${encodeURIComponent(publicId)}`;
      return {
        show: base,
        capture: `${base}/capture`,
        retry: (captureId) => `${base}/captures/${encodeURIComponent(String(captureId))}/retry`,
        review: (itemId) => `${base}/evidence/${encodeURIComponent(String(itemId))}/review`,
        compare: `${base}/compare`,
        pendingCapture: `${base}/pending-capture`,
        pendingCaptureIdentity: `${base}/pending-capture/identity`,
        complete: `${base}/complete`
      };
    }
    function projectionFromResponse(value) {
      const record = asRecord(value);
      const nested = record.projection;
      return nested !== null && typeof nested === "object" && !Array.isArray(nested) ? nested : value;
    }
    function publicIdFromProjection(value) {
      const record = asRecord(value);
      const context = asRecord(record.context);
      const candidate = context.public_id ?? record.public_id ?? record.run_public_id;
      return typeof candidate === "string" && candidate.trim() !== "" ? candidate.trim() : null;
    }
    function requestError(body, fallback) {
      const record = asRecord(body);
      const nested = asRecord(record.error);
      const message = typeof record.message === "string" ? record.message : typeof record.detail === "string" ? record.detail : typeof record.error === "string" ? record.error : typeof nested.message === "string" ? nested.message : fallback;
      const error = new Error(message);
      const code = typeof record.code === "string" ? record.code : typeof nested.code === "string" ? nested.code : null;
      if (code) error.code = code;
      return error;
    }
    function errorMessage(error, fallback) {
      if (typeof error === "string" && error.trim() !== "") {
        return error;
      }
      const record = asRecord(error);
      const nested = asRecord(record.error);
      const message = record.message ?? record.detail ?? nested.message ?? (record.code ? `Request failed (${record.code}).` : null);
      return typeof message === "string" && message.trim() !== "" ? message : fallback;
    }
    async function startRun(settings = null) {
      const target = settings ?? {
        versionId: selectedVersionId,
        bookletId: selectedBookletId,
        setCode: selectedSetCode
      };
      if (starting || commandBusy || refreshing || !target.versionId || !target.bookletId || !target.setCode) {
        return null;
      }
      starting = true;
      runError = null;
      try {
        const response = await fetch("/setup/exam-materials/omr-test/runs", {
          method: "POST",
          headers: {
            ...csrfHeaders(),
            "Content-Type": "application/json",
            "X-Requested-With": "XMLHttpRequest",
            Accept: "application/json"
          },
          credentials: "same-origin",
          body: JSON.stringify({
            template_version_id: Number(target.versionId),
            booklet_version_id: Number(target.bookletId),
            declared_set_code: String(target.setCode)
          })
        });
        const body = await response.json().catch(() => ({}));
        if (!response.ok) {
          throw requestError(body, "The diagnostic run could not be started.");
        }
        const startProjection = projectionFromResponse(body);
        assertDiagnosticCommandProjection(startProjection);
        const publicId = publicIdFromProjection(startProjection);
        if (!publicId) {
          throw new Error("The diagnostic run did not return a public identity.");
        }
        const client = createOmrWorkbenchClient({
          workbenchId: `diagnostic:${publicId}`,
          executionKind: "diagnostic",
          urls: diagnosticRunUrls(publicId)
        });
        const acknowledgedProjection = await client.load();
        assertDiagnosticCommandProjection(acknowledgedProjection);
        if (publicIdFromProjection(acknowledgedProjection) !== publicId) {
          throw new Error("The diagnostic run returned a mismatched run identity.");
        }
        selectedVersionId = target.versionId;
        selectedBookletId = target.bookletId;
        selectedSetCode = target.setCode;
        runPublicId = publicId;
        workbenchClient = client;
        projection = acknowledgedProjection;
        selectedSheetId = acknowledgedProjection.sheets?.[acknowledgedProjection.sheets.length - 1]?.id ?? null;
        reviewDirty = false;
        pendingRetake = null;
        settingsModalOpen = false;
        return acknowledgedProjection;
      } catch (error) {
        runError = error;
        return null;
      } finally {
        starting = false;
      }
    }
    async function restartRun() {
      if (starting || commandBusy || refreshing || reviewDirty) return null;
      return startRun();
    }
    function assertDiagnosticCommandProjection(value) {
      const outcome = asRecord(asRecord(value).recovery).outcome;
      const status = asRecord(outcome);
      const kind = diagnosticText(status.kind, null);
      if (kind !== null && kind !== "success" || status.blocked === true || status.stale === true || status.invalid === true) {
        const code = diagnosticText(status.code, kind ?? "failed");
        throw new Error(`The diagnostic command was not acknowledged (${code}).`);
      }
    }
    async function refreshProjection() {
      if (!workbenchClient || refreshing) return null;
      refreshing = true;
      try {
        const next = await workbenchClient.load();
        projection = next;
        runError = null;
        return next;
      } catch (error) {
        runError = error;
        return null;
      } finally {
        refreshing = false;
      }
    }
    async function confirmPendingIdentity(selections) {
      const publicId = runPublicId;
      const capture = pendingCapture();
      if (!publicId || !capture?.id || commandBusy) return null;
      commandBusy = true;
      runError = null;
      try {
        const response = await fetch(diagnosticRunUrls(publicId).pendingCaptureIdentity, {
          method: "POST",
          headers: {
            ...csrfHeaders(),
            "Content-Type": "application/json",
            "X-Requested-With": "XMLHttpRequest",
            Accept: "application/json"
          },
          credentials: "same-origin",
          body: JSON.stringify({
            pending_capture_id: capture.id,
            pending_capture_revision: capture.revision,
            idempotency_key: newIdempotencyKey(),
            selections
          })
        });
        const body = await response.json().catch(() => ({}));
        if (!response.ok) {
          throw requestError(body, "The write-in Applicant Number could not be confirmed.");
        }
        projection = projectionFromResponse(body);
        return projection;
      } catch (error) {
        runError = error;
        return null;
      } finally {
        commandBusy = false;
      }
    }
    async function startPendingCapture() {
      const client = workbenchClient;
      const publicId = runPublicId;
      if (!client || !publicId || !selectedAnswerSheetQrDisabled() || pendingCapture() || starting || commandBusy || refreshing) {
        return null;
      }
      commandBusy = true;
      runError = null;
      try {
        const response = await fetch(diagnosticRunUrls(publicId).pendingCapture, {
          method: "POST",
          headers: {
            ...csrfHeaders(),
            "Content-Type": "application/json",
            "X-Requested-With": "XMLHttpRequest",
            Accept: "application/json"
          },
          credentials: "same-origin",
          body: JSON.stringify({ idempotency_key: newIdempotencyKey() })
        });
        const body = await response.json().catch(() => ({}));
        if (!response.ok) {
          throw requestError(body, "The diagnostic page-capture set could not be started.");
        }
        const next = await client.load();
        projection = next;
        return next;
      } catch (error) {
        runError = error;
        return null;
      } finally {
        commandBusy = false;
      }
    }
    function requestPendingRetake(pageNo, reason) {
      const targetPage = positiveInteger(pageNo);
      if (!pendingCapture() || targetPage === null) return;
      pendingRetake = { pageNo: targetPage, reason: String(reason ?? "").trim() };
      runError = null;
    }
    async function runCommand(operation, options = {}) {
      const client = workbenchClient;
      const current = projection;
      const propagate = options.propagate === true;
      if (!client || !current || commandBusy) {
        if (propagate) {
          throw new Error("The diagnostic run is busy or unavailable.");
        }
        return null;
      }
      commandBusy = true;
      runError = null;
      try {
        const next = await operation(client, current);
        assertDiagnosticCommandProjection(next);
        if (typeof options.validate === "function") {
          options.validate(current, next);
        }
        projection = next;
        if (options.selectLatest === true) {
          selectedSheetId = next.sheets?.[next.sheets.length - 1]?.id ?? null;
        }
        return next;
      } catch (error) {
        runError = error;
        if (propagate) throw error;
        return null;
      } finally {
        commandBusy = false;
      }
    }
    async function captureImage(file, hint = null) {
      if (!file) return;
      if (selectedAnswerSheetQrDisabled()) {
        const retake = pendingRetake;
        const next = await runCommand(
          (client, current) => {
            const pending = asRecord(asRecord(current.context).pending_capture);
            const pendingCaptureId = diagnosticText(pending.id, null);
            const pendingRevision = positiveInteger(pending.revision);
            const pageNo = retake?.pageNo ?? positiveInteger(pending.active_page_no);
            if (pendingCaptureId === null || pendingRevision === null || pageNo === null) {
              throw new Error("The diagnostic page target is unavailable. Reload the projection before capturing.");
            }
            if (retake && !retake.reason) {
              throw new Error("A reason is required to replace a diagnostic page.");
            }
            return client.captureStart(
              file,
              {
                client_capture_id: newIdempotencyKey(),
                idempotency_key: newIdempotencyKey(),
                page_no: pageNo,
                pending_capture_id: pendingCaptureId,
                pending_capture_revision: pendingRevision,
                ...hint ? { client_quad: hint } : {},
                ...retake ? { replacement_reason: retake.reason } : {}
              },
              current.revision
            );
          },
          { selectLatest: true }
        );
        if (next) pendingRetake = null;
        return next;
      }
      return runCommand(
        (client, current) => client.captureStart(
          file,
          {
            client_capture_id: newIdempotencyKey(),
            idempotency_key: newIdempotencyKey(),
            page_no: 1,
            ...hint ? { client_quad: hint } : {}
          },
          current.revision
        ),
        { selectLatest: true }
      );
    }
    async function retryCapture(context = {}) {
      const record = asRecord(context);
      const captureId = positiveInteger(record.capture_id ?? record.sheet_id ?? selectedSheetId);
      if (captureId === null) return null;
      return runCommand((client, current) => client.captureRetry(captureId, { idempotency_key: newIdempotencyKey() }, current.revision), { selectLatest: true });
    }
    async function saveReview(payload, row) {
      const payloadRecord = asRecord(payload);
      const rowRecord = asRecord(row);
      const itemId = positiveInteger(rowRecord.id);
      const captureId = positiveInteger(rowRecord.capture_id);
      const capture = sheets().find((sheet) => Number(sheet.id) === Number(captureId));
      if (itemId === null || captureId === null || !capture) {
        throw new Error("The diagnostic evidence row is no longer available.");
      }
      const reviewPayload = buildDiagnosticReviewPayload(payload, row, capture, String(payloadRecord.idempotency_key ?? "").trim() === "" ? newIdempotencyKey() : null);
      const expectedReview = {
        decision: reviewPayload.decision,
        selected_option: reviewPayload.final_option,
        reason_code: reviewPayload.reason,
        note: reviewPayload.note,
        expected_parent_version: reviewPayload.expected_capture_version,
        expected_item_revision: reviewPayload.expected_item_revision,
        reviewed_machine_evidence_hash: reviewPayload.reviewed_evidence_hash,
        idempotency_key: reviewPayload.idempotency_key
      };
      const next = await runCommand((client, current) => client.reviewSave(itemId, reviewPayload, current.revision), {
        propagate: true,
        validate: (current, refreshed) => assertDiagnosticItemReviewAcknowledged(current, refreshed, captureId, itemId, expectedReview)
      });
      const acknowledgedItem = asRecord(next?.evidence).items;
      const acknowledged = Array.isArray(acknowledgedItem) ? acknowledgedItem.map(asRecord).find((entry) => Number(entry.id) === itemId && Number(entry.capture_id) === captureId) : null;
      if (!acknowledged?.review) {
        throw new Error("The diagnostic item review acknowledgment is missing.");
      }
      return { review: acknowledged.review, projection: next };
    }
    async function handleRecoveryAction(action, recovery) {
      if (action === "capture_retry" || action === "retry_scan") {
        return retryCapture(recovery);
      }
      if (action === "review_save") {
        const captureId = positiveInteger(asRecord(recovery).capture_id);
        if (captureId !== null) selectedSheetId = captureId;
        return refreshProjection();
      }
      return null;
    }
    async function handleComplete(command) {
      if (command === "diagnostic_compare") {
        return runCommand((client, current) => client.diagnosticCompare(current.revision));
      }
      if (command === "diagnostic_complete") {
        return runCommand((client, current) => client.diagnosticComplete(current.revision));
      }
      return null;
    }
    function canLeaveReview() {
      if (!reviewDirty || typeof window === "undefined") return true;
      const discard = window.confirm("This item review has unsaved changes. Discard them and continue?");
      if (discard) reviewDirty = false;
      return discard;
    }
    function captureDisabled() {
      if (commandBusy || !projection?.commands?.includes("capture_start")) {
        return true;
      }
      if (!selectedAnswerSheetQrDisabled()) return false;
      if (!pendingCapture() || pendingCaptureExpired()) return true;
      return pendingCapture().status !== "pending" && pendingRetake === null;
    }
    function captureDisabledReason() {
      if (commandBusy) return "A diagnostic command is already in progress.";
      if (!projection?.commands?.includes("capture_start")) {
        return "Capture is unavailable in the current diagnostic state.";
      }
      if (selectedAnswerSheetQrDisabled() && !pendingCapture()) {
        return "Start the diagnostic page-capture set before scanning.";
      }
      if (selectedAnswerSheetQrDisabled() && pendingCaptureExpired()) {
        return "This diagnostic page-capture set expired. Reload it or start a new diagnostic run.";
      }
      if (selectedAnswerSheetQrDisabled() && pendingCapture()?.status !== "pending" && pendingRetake === null) {
        return "The diagnostic set is complete or blocked. Retake a captured page to continue.";
      }
      return "";
    }
    head("w51xo5", $$renderer2, ($$renderer3) => {
      $$renderer3.title(($$renderer4) => {
        $$renderer4.push(`<title>OMR Test · Exam materials</title>`);
      });
    });
    $$renderer2.push(`<div class="bg-background text-foreground flex h-dvh w-full flex-col overflow-hidden" data-omr-test-host=""><div class="flex min-h-0 flex-1 flex-col overflow-hidden">`);
    if (runError) {
      $$renderer2.push("<!--[0-->");
      $$renderer2.push(`<section class="flex flex-wrap items-center justify-between gap-3 rounded-xl border border-amber-500/30 bg-amber-500/10 p-3.5 text-sm text-amber-200" role="alert" data-omr-diagnostic-error=""><p>${escape_html(errorMessage(runError, "The diagnostic run could not be refreshed."))}</p> <div class="flex flex-wrap gap-2">`);
      if (workbenchClient) {
        $$renderer2.push("<!--[0-->");
        Button($$renderer2, {
          size: "sm",
          variant: "outline",
          onclick: refreshProjection,
          disabled: starting || commandBusy || refreshing,
          children: ($$renderer3) => {
            $$renderer3.push(`<!---->Reload diagnostic projection`);
          },
          $$slots: { default: true }
        });
      } else {
        $$renderer2.push("<!--[-1-->");
      }
      $$renderer2.push(`<!--]--> `);
      if (projection) {
        $$renderer2.push("<!--[0-->");
        Button($$renderer2, {
          size: "sm",
          variant: "outline",
          onclick: restartRun,
          disabled: starting || commandBusy || refreshing,
          children: ($$renderer3) => {
            $$renderer3.push(`<!---->Restart run`);
          },
          $$slots: { default: true }
        });
      } else {
        $$renderer2.push("<!--[-1-->");
      }
      $$renderer2.push(`<!--]--></div></section>`);
    } else {
      $$renderer2.push("<!--[-1-->");
    }
    $$renderer2.push(`<!--]--> `);
    if (projection) {
      $$renderer2.push("<!--[0-->");
      {
        let captureContent = function($$renderer3) {
          if (selectedAnswerSheetQrDisabled()) {
            $$renderer3.push("<!--[0-->");
            if (pendingCapture()) {
              $$renderer3.push("<!--[0-->");
              $$renderer3.push(`<div class="space-y-3">`);
              OmrPendingCaptureProgress($$renderer3, {
                pending: pendingCapture(),
                production: false,
                busy: commandBusy || refreshing,
                onConfirmIdentity: confirmPendingIdentity,
                onRetake: requestPendingRetake
              });
              $$renderer3.push(`<!----> `);
              if (pendingCaptureExpired()) {
                $$renderer3.push("<!--[0-->");
                $$renderer3.push(`<section class="flex flex-wrap items-center justify-between gap-3 rounded-xl border border-amber-500/30 bg-amber-500/10 p-3.5 text-sm text-amber-900 dark:text-amber-200" role="alert"><p>This diagnostic page-capture set has
                                            expired. Reload its projection or
                                            restart the run to begin a fresh
                                            set.</p> <div class="flex flex-wrap gap-2">`);
                Button($$renderer3, {
                  size: "sm",
                  variant: "outline",
                  onclick: refreshProjection,
                  disabled: starting || commandBusy || refreshing,
                  children: ($$renderer4) => {
                    $$renderer4.push(`<!---->Reload diagnostic projection`);
                  },
                  $$slots: { default: true }
                });
                $$renderer3.push(`<!----> `);
                Button($$renderer3, {
                  size: "sm",
                  variant: "outline",
                  onclick: restartRun,
                  disabled: starting || commandBusy || refreshing,
                  children: ($$renderer4) => {
                    $$renderer4.push(`<!---->Restart run`);
                  },
                  $$slots: { default: true }
                });
                $$renderer3.push(`<!----></div></section>`);
              } else if (pendingCapture().status === "ready") {
                $$renderer3.push("<!--[1-->");
                $$renderer3.push(`<p class="rounded-lg border border-emerald-500/30 bg-emerald-500/10 px-3 py-2 text-sm text-emerald-900 dark:text-emerald-200" role="status">Diagnostic set ready. This is not a
                                        scored production sheet; no production
                                        claim or score was created.</p>`);
              } else {
                $$renderer3.push("<!--[-1-->");
              }
              $$renderer3.push(`<!--]--> `);
              OmrCameraDeck($$renderer3, {
                onCapture: async (file, hint) => Boolean(await captureImage(file, hint)),
                busy: commandBusy,
                disabled: captureDisabled(),
                disabledReason: captureDisabledReason(),
                registrationMode: selectedAnswerSheet()?.registration_mode ?? "fiducial_anchored",
                captureRevision: positiveInteger(pendingCapture().revision) ?? 0,
                pageCount: positiveInteger(pendingCapture().page_count) ?? 1,
                currentPage: pendingRetake?.pageNo ?? positiveInteger(pendingCapture().active_page_no) ?? 1,
                allowUpload: true,
                singleUpload: true,
                galleryCount: sheets().length
              });
              $$renderer3.push(`<!----></div>`);
            } else {
              $$renderer3.push("<!--[-1-->");
              $$renderer3.push(`<section class="border-border bg-card rounded-xl border p-4 sm:p-5" data-omr-pending-capture-start=""><h3 class="text-sm font-semibold">Start QR-disabled diagnostic capture</h3> <p class="text-muted-foreground mt-1 text-sm">Start a server-owned page set before
                                    scanning. Each page remains isolated to this
                                    OMR Test run and cannot make a production
                                    claim or score.</p> `);
              Button($$renderer3, {
                class: "mt-3",
                size: "sm",
                onclick: startPendingCapture,
                disabled: starting || commandBusy || refreshing,
                children: ($$renderer4) => {
                  $$renderer4.push(`<!---->${escape_html(commandBusy ? "Starting page set…" : "Start diagnostic page set")}`);
                },
                $$slots: { default: true }
              });
              $$renderer3.push(`<!----></section> `);
              OmrCameraDeck($$renderer3, {
                onCapture: async (file, hint) => Boolean(await captureImage(file, hint)),
                busy: commandBusy,
                disabled: true,
                disabledReason: "Start the diagnostic page set before scanning.",
                registrationMode: selectedAnswerSheet()?.registration_mode ?? "fiducial_anchored",
                allowUpload: true,
                galleryCount: sheets().length
              });
              $$renderer3.push(`<!---->`);
            }
            $$renderer3.push(`<!--]-->`);
          } else {
            $$renderer3.push("<!--[-1-->");
            OmrCameraDeck($$renderer3, {
              onCapture: async (file, hint) => Boolean(await captureImage(file, hint)),
              busy: commandBusy,
              disabled: captureDisabled(),
              disabledReason: captureDisabledReason(),
              registrationMode: selectedAnswerSheet()?.registration_mode ?? "fiducial_anchored",
              allowUpload: true,
              galleryCount: sheets().length
            });
          }
          $$renderer3.push(`<!--]-->`);
        };
        OmrWorkbench($$renderer2, {
          projection,
          connectionState: connectionState(),
          onSettings: () => settingsModalOpen = true,
          onRestart: restartRun,
          evidencePage: evidencePage(),
          selectedSheetId,
          onSelectSheet: (sheet) => selectedSheetId = sheet.id,
          onRetry: retryCapture,
          onReviewAction: saveReview,
          onRecoveryAction: handleRecoveryAction,
          onComplete: handleComplete,
          onDirtyChange: (dirty) => reviewDirty = dirty,
          onDirtyNavigation: canLeaveReview,
          captureContent
        });
      }
    } else {
      $$renderer2.push("<!--[-1-->");
      $$renderer2.push(`<div class="flex min-h-[60vh] flex-col items-center justify-center gap-4 text-center"><div class="size-8 animate-spin rounded-full border-2 border-cyan-400/20 border-t-cyan-400" aria-hidden="true"></div> <div class="space-y-1"><p class="font-mono text-sm text-slate-200">Initializing diagnostic calibration workspace…</p> `);
      if (page_state === "needs_answer_sheet") {
        $$renderer2.push("<!--[0-->");
        $$renderer2.push(`<p class="text-sm text-slate-400">Activate an Answer Sheet before starting a
                            diagnostic run.</p>`);
      } else if (page_state === "needs_booklet") {
        $$renderer2.push("<!--[1-->");
        $$renderer2.push(`<p class="text-sm text-slate-400">Finalize a compatible Booklet and Set before
                            starting a diagnostic run.</p>`);
      } else {
        $$renderer2.push("<!--[-1-->");
        $$renderer2.push(`<p class="text-sm text-slate-400">Choose the active Answer Sheet, Booklet, and Set for
                            this isolated calibration.</p>`);
      }
      $$renderer2.push(`<!--]--></div> `);
      Button($$renderer2, {
        size: "sm",
        onclick: startRun,
        disabled: starting || !selectedVersionId || !selectedBooklet()?.id || !selectedSetCode,
        children: ($$renderer3) => {
          $$renderer3.push(`<!---->${escape_html(starting ? "Starting diagnostic…" : "Start diagnostic run")}`);
        },
        $$slots: { default: true }
      });
      $$renderer2.push(`<!----></div>`);
    }
    $$renderer2.push(`<!--]--></div> `);
    OmrSettingsModal($$renderer2, {
      open: settingsModalOpen,
      busy: starting || commandBusy || refreshing,
      answer_sheets,
      booklets,
      onClose: () => settingsModalOpen = false
    });
    $$renderer2.push(`<!----></div>`);
  });
}
export {
  buildDiagnosticReviewPayload,
  OmrTest as default,
  mapDiagnosticEvidenceItem,
  mapDiagnosticEvidencePage
};
