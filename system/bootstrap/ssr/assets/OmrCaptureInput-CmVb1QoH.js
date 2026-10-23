import { o as onDestroy, b as attr, c as attr_class, e as escape_html, d as derived } from "../app.js";
/* empty css                                                       */
import { C as Camera } from "./camera-C09Eyogi.js";
import { U as Upload } from "./upload-CwqN2Utd.js";
import { L as Loader_circle } from "./loader-circle-_UEl10Lx.js";
import { R as Refresh_cw } from "./refresh-cw-CYdk8aE0.js";
const DEFAULT_ACCEPT = ["image/jpeg", "image/png", "image/webp"];
const DEFAULT_MAX_BYTES = 25 * 1024 * 1024;
function supportsGetUserMedia(mediaDevices) {
  if (!mediaDevices || typeof mediaDevices !== "object") return false;
  return "getUserMedia" in mediaDevices && typeof mediaDevices.getUserMedia === "function";
}
function initialCaptureMode(options) {
  return options.cameraEnabled && options.supports ? "camera" : "upload";
}
function preflightTone(preflight) {
  if (!preflight || typeof preflight !== "object") return null;
  if ("mode" in preflight && preflight.mode === "scan") return "scan";
  if ("outcome" in preflight && preflight.outcome === "validated") {
    return "submit_allowed" in preflight && preflight.submit_allowed === true ? "validated" : "rejected";
  }
  if ("outcome" in preflight && typeof preflight.outcome === "string") return "rejected";
  return null;
}
function OmrCaptureInput($$renderer, $$props) {
  $$renderer.component(($$renderer2) => {
    let {
      submit = null,
      // (file: File) => void | Promise<void> — the ONLY outward action
      busy = false,
      disabled = false,
      disabledReason = "Capture is unavailable until the prerequisite step is complete.",
      accept = DEFAULT_ACCEPT,
      maxBytes = DEFAULT_MAX_BYTES,
      defaultMode = "upload",
      cameraEnabled = true,
      autoSubmit = false,
      preflight = null,
      preflightError = "",
      labels = {}
    } = $$props;
    let mode = initialCaptureMode({
      cameraEnabled,
      supports: supportsGetUserMedia(navigator?.mediaDevices)
    }) === "camera" ? defaultMode : "upload";
    let stream = null;
    let cameraState = "idle";
    const preflightToneValue = derived(() => preflightTone(preflight));
    function stopCamera() {
      if (stream) {
        stream.getTracks().forEach((track) => track.stop());
      }
      stream = null;
      cameraState = "idle";
    }
    onDestroy(() => {
      stopCamera();
    });
    $$renderer2.push(`<div class="omr-ci svelte-1sbw36t"><input class="omr-ci-hidden-input svelte-1sbw36t" type="file"${attr("accept", accept.join(","))}/> <div class="omr-ci-modes svelte-1sbw36t" role="group" aria-label="Capture method"><button type="button"${attr("disabled", disabled || busy, true)}${attr_class("svelte-1sbw36t", void 0, { "active": mode === "camera" })}>`);
    Camera($$renderer2, { class: "omr-ci-icon", size: 14 });
    $$renderer2.push(`<!----> ${escape_html(labels.cameraLabel ?? "Device Camera")}</button> <button type="button"${attr("disabled", disabled || busy, true)}${attr_class("svelte-1sbw36t", void 0, { "active": mode === "upload" })}>`);
    Upload($$renderer2, { class: "omr-ci-icon", size: 14 });
    $$renderer2.push(`<!----> ${escape_html(labels.chooseLabel ?? "Choose Photo or Drag & Drop")}</button></div> `);
    if (disabled) {
      $$renderer2.push("<!--[0-->");
      $$renderer2.push(`<p class="omr-ci-disabled svelte-1sbw36t" role="note">${escape_html(labels.disabledHint ?? disabledReason)}</p>`);
    } else {
      $$renderer2.push("<!--[-1-->");
    }
    $$renderer2.push(`<!--]--> `);
    if (mode === "camera") {
      $$renderer2.push("<!--[0-->");
      $$renderer2.push(`<div class="omr-ci-camera svelte-1sbw36t"><div class="omr-ci-camera-head svelte-1sbw36t"><span class="omr-ci-camera-title svelte-1sbw36t">Live Viewfinder</span> <button type="button"${attr("disabled", disabled || busy || cameraState !== "ready", true)} class="omr-ci-small-btn svelte-1sbw36t">${escape_html(labels.switchCameraLabel ?? "Switch Camera")}</button></div> <div class="omr-ci-viewfinder svelte-1sbw36t"><video autoplay="" playsinline="" muted="" class="svelte-1sbw36t"></video> <canvas class="omr-ci-canvas svelte-1sbw36t"></canvas> <div class="omr-ci-target svelte-1sbw36t" aria-hidden="true"><div class="omr-ci-target-ring svelte-1sbw36t"></div></div></div> `);
      if (cameraState === "unavailable") {
        $$renderer2.push("<!--[0-->");
        $$renderer2.push(`<div class="omr-ci-camera-error svelte-1sbw36t" role="alert"><p class="omr-ci-camera-error-title svelte-1sbw36t">${escape_html("Camera could not be opened")}</p> <p class="omr-ci-camera-error-detail svelte-1sbw36t">${escape_html("Camera access is unavailable.")}</p> <div class="omr-ci-camera-error-actions svelte-1sbw36t"><button type="button" class="omr-ci-small-btn svelte-1sbw36t"${attr("disabled", busy, true)}>`);
        Refresh_cw($$renderer2, { class: "omr-ci-icon", size: 12 });
        $$renderer2.push(`<!----> ${escape_html(labels.retryLabel ?? "Retry camera")}</button> <button type="button" class="omr-ci-small-btn svelte-1sbw36t"${attr("disabled", busy, true)}>`);
        Upload($$renderer2, { class: "omr-ci-icon", size: 12 });
        $$renderer2.push(`<!----> ${escape_html(labels.uploadFallbackLabel ?? "Use file upload instead")}</button></div></div>`);
      } else {
        $$renderer2.push("<!--[-1-->");
        $$renderer2.push(`<button type="button" class="omr-ci-primary svelte-1sbw36t"${attr("disabled", busy || disabled || cameraState !== "ready", true)}>`);
        if (busy) {
          $$renderer2.push("<!--[0-->");
          Loader_circle($$renderer2, { class: "omr-ci-spin", size: 14 });
          $$renderer2.push(`<!----> ${escape_html(labels.busyLabel ?? "Running…")}`);
        } else {
          $$renderer2.push("<!--[-1-->");
          Camera($$renderer2, { class: "omr-ci-icon", size: 14 });
          $$renderer2.push(`<!----> ${escape_html(labels.captureLabel ?? "Capture Snapshot")}`);
        }
        $$renderer2.push(`<!--]--></button>`);
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
    {
      $$renderer2.push("<!--[-1-->");
    }
    $$renderer2.push(`<!--]--> `);
    if (preflightToneValue() === "validated") {
      $$renderer2.push("<!--[0-->");
      $$renderer2.push(`<p class="omr-ci-preflight ok svelte-1sbw36t" role="status">Preflight validated — ready to upload.</p>`);
    } else if (preflightToneValue() === "rejected") {
      $$renderer2.push("<!--[1-->");
      $$renderer2.push(`<p class="omr-ci-preflight fail svelte-1sbw36t" role="alert">Preflight rejected — retake or retry before uploading.</p>`);
    } else if (preflightToneValue() === "scan") {
      $$renderer2.push("<!--[2-->");
      $$renderer2.push(`<p class="omr-ci-preflight neutral svelte-1sbw36t" role="status">Scan profile — preflight not applicable; the normal upload path applies.</p>`);
    } else {
      $$renderer2.push("<!--[-1-->");
    }
    $$renderer2.push(`<!--]--> `);
    if (preflightError) {
      $$renderer2.push("<!--[0-->");
      $$renderer2.push(`<p class="omr-ci-preflight fail svelte-1sbw36t" role="alert">${escape_html(preflightError)}</p>`);
    } else {
      $$renderer2.push("<!--[-1-->");
    }
    $$renderer2.push(`<!--]--></div>`);
  });
}
export {
  OmrCaptureInput as O
};
