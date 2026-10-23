import { g as sanitize_props, i as spread_props, j as slot, o as onDestroy, c as attr_class, s as stringify, e as escape_html, B as Button, t as tick, b as attr, a as ensure_array_like, m as csrfHeaders, k as sound, d as derived, w as writable, h as head, x as store_get, y as unsubscribe_stores, u as usePage } from "../app.js";
import { T as ToastManager } from "./ToastManager-C7oO_4Jn.js";
import { N as NotificationBell } from "./NotificationBell-Cgc59min.js";
import { c as closeStaffIdleAlert, s as showStaffIdleAlert, M as Menu, P as PreferencesMenu } from "./PreferencesMenu-CCPeta-5.js";
import { A as AvatarBadge } from "./AvatarBadge-DQ5Qru9A.js";
import { B as Badge } from "./badge-D9Vgs7xh.js";
import "clsx";
import { I as Input } from "./input-BUZAZKIK.js";
import { D as Dialog, a as Dialog_content, c as Dialog_title, d as Dialog_description, e as Dialog_portal, f as Dialog_overlay, b as Dialog_header } from "./dialog-description-D12L0QA9.js";
import { C as Circle_alert } from "./circle-alert-jWXJ7oC0.js";
import { R as Refresh_cw } from "./refresh-cw-CYdk8aE0.js";
import { C as Camera } from "./camera-C09Eyogi.js";
import { S as Search, E as Ellipsis_vertical, F as File_spreadsheet, a as Settings, A as Activity, L as Layout_dashboard } from "./settings-C5xOKpV3.js";
import { A as Arrow_right } from "./arrow-right-CzynHiIo.js";
import { C as Circle_check } from "./circle-check-C59ZS7Zv.js";
import { router } from "@inertiajs/core";
import { D as Dialog_footer } from "./dialog-footer-kNnwKT2h.js";
import { C as Clock } from "./clock-B4fXoAi9.js";
import { I as InstitutionalBackdrop } from "./InstitutionalBackdrop-BjxeRqDu.js";
import { L as Link } from "./Link-B7ks7rBK.js";
import { Q as Qr_code } from "./qr-code-C5X8qcH6.js";
import { C as Chevron_right } from "./chevron-right-BHu-PGOg.js";
import { C as Chevron_down } from "./chevron-down-CkQQZIhu.js";
import { P as Printer } from "./printer-Bx0z1CRb.js";
import { B as Book_open } from "./book-open-B6eMguqY.js";
import { I as Icon } from "./Icon-Dal7PqKK.js";
import { M as Megaphone, L as Log_out } from "./megaphone-C8QUuT5s.js";
import { F as File_check } from "./file-check-BSLEMs1I.js";
import { S as Shield } from "./shield-DTtuUihb.js";
import { C as Clipboard_list } from "./clipboard-list-YgthURdB.js";
import { U as Users } from "./users-DWlguTa_.js";
import { U as User_round } from "./user-round-Kft0Cy_Y.js";
function Calendar($$renderer, $$props) {
  const $$sanitized_props = sanitize_props($$props);
  const iconNode = [
    ["path", { "d": "M8 2v4" }],
    ["path", { "d": "M16 2v4" }],
    [
      "rect",
      { "width": "18", "height": "18", "x": "3", "y": "4", "rx": "2" }
    ],
    ["path", { "d": "M3 10h18" }]
  ];
  Icon($$renderer, spread_props([
    { name: "calendar" },
    $$sanitized_props,
    {
      /**
       * @component @name Calendar
       * @description Lucide SVG icon component, renders SVG Element with children.
       *
       * @preview ![img](data:image/svg+xml;base64,PHN2ZyAgeG1sbnM9Imh0dHA6Ly93d3cudzMub3JnLzIwMDAvc3ZnIgogIHdpZHRoPSIyNCIKICBoZWlnaHQ9IjI0IgogIHZpZXdCb3g9IjAgMCAyNCAyNCIKICBmaWxsPSJub25lIgogIHN0cm9rZT0iIzAwMCIgc3R5bGU9ImJhY2tncm91bmQtY29sb3I6ICNmZmY7IGJvcmRlci1yYWRpdXM6IDJweCIKICBzdHJva2Utd2lkdGg9IjIiCiAgc3Ryb2tlLWxpbmVjYXA9InJvdW5kIgogIHN0cm9rZS1saW5lam9pbj0icm91bmQiCj4KICA8cGF0aCBkPSJNOCAydjQiIC8+CiAgPHBhdGggZD0iTTE2IDJ2NCIgLz4KICA8cmVjdCB3aWR0aD0iMTgiIGhlaWdodD0iMTgiIHg9IjMiIHk9IjQiIHJ4PSIyIiAvPgogIDxwYXRoIGQ9Ik0zIDEwaDE4IiAvPgo8L3N2Zz4K) - https://lucide.dev/icons/calendar
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
function Messages_square($$renderer, $$props) {
  const $$sanitized_props = sanitize_props($$props);
  const iconNode = [
    [
      "path",
      {
        "d": "M16 10a2 2 0 0 1-2 2H6.828a2 2 0 0 0-1.414.586l-2.202 2.202A.71.71 0 0 1 2 14.286V4a2 2 0 0 1 2-2h10a2 2 0 0 1 2 2z"
      }
    ],
    [
      "path",
      {
        "d": "M20 9a2 2 0 0 1 2 2v10.286a.71.71 0 0 1-1.212.502l-2.202-2.202A2 2 0 0 0 17.172 19H10a2 2 0 0 1-2-2v-1"
      }
    ]
  ];
  Icon($$renderer, spread_props([
    { name: "messages-square" },
    $$sanitized_props,
    {
      /**
       * @component @name MessagesSquare
       * @description Lucide SVG icon component, renders SVG Element with children.
       *
       * @preview ![img](data:image/svg+xml;base64,PHN2ZyAgeG1sbnM9Imh0dHA6Ly93d3cudzMub3JnLzIwMDAvc3ZnIgogIHdpZHRoPSIyNCIKICBoZWlnaHQ9IjI0IgogIHZpZXdCb3g9IjAgMCAyNCAyNCIKICBmaWxsPSJub25lIgogIHN0cm9rZT0iIzAwMCIgc3R5bGU9ImJhY2tncm91bmQtY29sb3I6ICNmZmY7IGJvcmRlci1yYWRpdXM6IDJweCIKICBzdHJva2Utd2lkdGg9IjIiCiAgc3Ryb2tlLWxpbmVjYXA9InJvdW5kIgogIHN0cm9rZS1saW5lam9pbj0icm91bmQiCj4KICA8cGF0aCBkPSJNMTYgMTBhMiAyIDAgMCAxLTIgMkg2LjgyOGEyIDIgMCAwIDAtMS40MTQuNTg2bC0yLjIwMiAyLjIwMkEuNzEuNzEgMCAwIDEgMiAxNC4yODZWNGEyIDIgMCAwIDEgMi0yaDEwYTIgMiAwIDAgMSAyIDJ6IiAvPgogIDxwYXRoIGQ9Ik0yMCA5YTIgMiAwIDAgMSAyIDJ2MTAuMjg2YS43MS43MSAwIDAgMS0xLjIxMi41MDJsLTIuMjAyLTIuMjAyQTIgMiAwIDAgMCAxNy4xNzIgMTlIMTBhMiAyIDAgMCAxLTItMnYtMSIgLz4KPC9zdmc+Cg==) - https://lucide.dev/icons/messages-square
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
function Send($$renderer, $$props) {
  const $$sanitized_props = sanitize_props($$props);
  const iconNode = [
    [
      "path",
      {
        "d": "M14.536 21.686a.5.5 0 0 0 .937-.024l6.5-19a.496.496 0 0 0-.635-.635l-19 6.5a.5.5 0 0 0-.024.937l7.93 3.18a2 2 0 0 1 1.112 1.11z"
      }
    ],
    ["path", { "d": "m21.854 2.147-10.94 10.939" }]
  ];
  Icon($$renderer, spread_props([
    { name: "send" },
    $$sanitized_props,
    {
      /**
       * @component @name Send
       * @description Lucide SVG icon component, renders SVG Element with children.
       *
       * @preview ![img](data:image/svg+xml;base64,PHN2ZyAgeG1sbnM9Imh0dHA6Ly93d3cudzMub3JnLzIwMDAvc3ZnIgogIHdpZHRoPSIyNCIKICBoZWlnaHQ9IjI0IgogIHZpZXdCb3g9IjAgMCAyNCAyNCIKICBmaWxsPSJub25lIgogIHN0cm9rZT0iIzAwMCIgc3R5bGU9ImJhY2tncm91bmQtY29sb3I6ICNmZmY7IGJvcmRlci1yYWRpdXM6IDJweCIKICBzdHJva2Utd2lkdGg9IjIiCiAgc3Ryb2tlLWxpbmVjYXA9InJvdW5kIgogIHN0cm9rZS1saW5lam9pbj0icm91bmQiCj4KICA8cGF0aCBkPSJNMTQuNTM2IDIxLjY4NmEuNS41IDAgMCAwIC45MzctLjAyNGw2LjUtMTlhLjQ5Ni40OTYgMCAwIDAtLjYzNS0uNjM1bC0xOSA2LjVhLjUuNSAwIDAgMC0uMDI0LjkzN2w3LjkzIDMuMThhMiAyIDAgMCAxIDEuMTEyIDEuMTF6IiAvPgogIDxwYXRoIGQ9Im0yMS44NTQgMi4xNDctMTAuOTQgMTAuOTM5IiAvPgo8L3N2Zz4K) - https://lucide.dev/icons/send
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
function isStaffNavActive(item, items, rawUrl) {
  const url = normalizePath(rawUrl);
  let bestHref = null;
  let bestScore = -1;
  for (const candidate of items) {
    const score = scoreNavItem(candidate, url);
    if (score > bestScore) {
      bestScore = score;
      bestHref = candidate.href;
    }
  }
  return bestScore >= 0 && bestHref === item.href;
}
function scoreNavItem(item, url) {
  const href = normalizePath(item.href ?? "");
  const mode = item.match === "exact" ? "exact" : "prefix";
  if (mode === "exact") {
    return url === href ? 1e6 : -1;
  }
  if (url === href) {
    return 1e5 + href.length;
  }
  if (href !== "/" && url.startsWith(`${href}/`)) {
    return href.length;
  }
  return -1;
}
function normalizePath(path) {
  const cleaned = (path || "/").split("?")[0].split("#")[0];
  if (cleaned.length > 1 && cleaned.endsWith("/")) {
    return cleaned.slice(0, -1);
  }
  return cleaned || "/";
}
function QrScanner($$renderer, $$props) {
  $$renderer.component(($$renderer2) => {
    let {
      active = true,
      paused = false,
      facingMode = "environment",
      showTargetBox = true,
      onScan = () => {
      },
      onError = () => {
      },
      class: className = ""
    } = $$props;
    let videoEl = null;
    let cameraStatus = "idle";
    let errorMessage = "";
    let sessionToken = 0;
    const trackedMediaTracks = /* @__PURE__ */ new Set();
    let scanLoopRafId = null;
    let detectorInstance = null;
    function stopStreamTracks(stream) {
      if (!stream) return;
      try {
        stream.getTracks().forEach((track) => {
          try {
            track.stop();
            track.enabled = false;
          } catch (_) {
          }
        });
      } catch (_) {
      }
    }
    function terminateCamera() {
      sessionToken++;
      if (scanLoopRafId !== null) {
        cancelAnimationFrame(scanLoopRafId);
        scanLoopRafId = null;
      }
      for (const track of trackedMediaTracks) {
        try {
          track.stop();
          track.enabled = false;
        } catch (_) {
        }
      }
      trackedMediaTracks.clear();
      detectorInstance = null;
      if (cameraStatus !== "error" && cameraStatus !== "unsupported") {
        cameraStatus = "idle";
      }
    }
    async function acquireCamera() {
      if (!active) return;
      if (typeof document !== "undefined" && document.hidden) return;
      if (typeof navigator === "undefined" || !navigator.mediaDevices?.getUserMedia) {
        cameraStatus = "unsupported";
        errorMessage = "Camera capture is not supported in this browser environment.";
        onError(errorMessage);
        return;
      }
      terminateCamera();
      const currentToken = ++sessionToken;
      cameraStatus = "initializing";
      errorMessage = "";
      try {
        let stream = null;
        try {
          stream = await navigator.mediaDevices.getUserMedia({
            video: {
              facingMode: { ideal: facingMode },
              width: { ideal: 1280 },
              height: { ideal: 720 }
            },
            audio: false
          });
        } catch (strictErr) {
          if (currentToken !== sessionToken || !active) {
            stopStreamTracks(stream);
            return;
          }
          stream = await navigator.mediaDevices.getUserMedia({ video: true, audio: false });
        }
        if (currentToken !== sessionToken || !active) {
          stopStreamTracks(stream);
          return;
        }
        stream.getTracks().forEach((track) => {
          trackedMediaTracks.add(track);
          track.onended = () => {
            trackedMediaTracks.delete(track);
          };
        });
        await tick();
        if (currentToken !== sessionToken || !active || !videoEl) {
          stopStreamTracks(stream);
          return;
        }
        videoEl.srcObject = stream;
        await videoEl.play().catch(() => {
        });
        if (currentToken !== sessionToken || !active) {
          stopStreamTracks(stream);
          if (videoEl) ;
          return;
        }
        cameraStatus = "running";
        startBarcodeScanning(currentToken);
      } catch (err) {
        if (currentToken !== sessionToken) return;
        terminateCamera();
        cameraStatus = "error";
        if (err.name === "NotAllowedError" || err.name === "PermissionDeniedError") {
          errorMessage = "Camera access was denied. Please allow camera access in browser permissions.";
        } else if (err.name === "NotFoundError" || err.name === "DevicesNotFoundError") {
          errorMessage = "No camera device was detected on your hardware.";
        } else if (err.name === "NotReadableError" || err.name === "TrackStartError") {
          errorMessage = "The camera is currently locked or in use by another application.";
        } else {
          errorMessage = err.message || "Unable to start camera video stream.";
        }
        onError(errorMessage);
      }
    }
    async function startBarcodeScanning(expectedToken) {
      if (typeof window === "undefined") return;
      if (!("BarcodeDetector" in window)) {
        cameraStatus = "unsupported";
        errorMessage = "Automatic QR code scanning is not supported on this browser. Please use manual code entry.";
        onError(errorMessage);
        terminateCamera();
        return;
      }
      try {
        detectorInstance = new window.BarcodeDetector({ formats: ["qr_code"] });
      } catch (_) {
        cameraStatus = "unsupported";
        errorMessage = "QR barcode detector is unavailable on this device. Please use manual code entry.";
        onError(errorMessage);
        terminateCamera();
        return;
      }
      const runScanLoop = async () => {
        if (expectedToken !== sessionToken || !active || cameraStatus !== "running") {
          return;
        }
        if (expectedToken === sessionToken && active) {
          scanLoopRafId = requestAnimationFrame(runScanLoop);
        }
      };
      scanLoopRafId = requestAnimationFrame(runScanLoop);
    }
    onDestroy(() => {
      terminateCamera();
    });
    $$renderer2.push(`<div${attr_class(`relative overflow-hidden rounded-xl border border-border/80 bg-black aspect-video max-h-64 flex items-center justify-center select-none ${stringify(className)}`)}><video autoplay="" playsinline="" muted=""${attr_class(`size-full object-cover ${cameraStatus === "running" ? "opacity-100" : "opacity-0"}`)}></video> `);
    if (cameraStatus === "error" || cameraStatus === "unsupported") {
      $$renderer2.push("<!--[0-->");
      $$renderer2.push(`<div class="absolute inset-0 bg-background/95 p-4 text-center text-xs text-muted-foreground space-y-2 flex flex-col items-center justify-center z-20">`);
      Circle_alert($$renderer2, { class: "size-6 text-amber-500 mx-auto" });
      $$renderer2.push(`<!----> <p class="font-medium text-foreground max-w-xs">${escape_html(errorMessage)}</p> `);
      if (cameraStatus === "error") {
        $$renderer2.push("<!--[0-->");
        Button($$renderer2, {
          size: "sm",
          variant: "outline",
          class: "h-7 text-xs gap-1",
          onclick: acquireCamera,
          children: ($$renderer3) => {
            Refresh_cw($$renderer3, { class: "size-3" });
            $$renderer3.push(`<!----> Retry Camera`);
          },
          $$slots: { default: true }
        });
      } else {
        $$renderer2.push("<!--[-1-->");
      }
      $$renderer2.push(`<!--]--></div>`);
    } else if (cameraStatus === "initializing") {
      $$renderer2.push("<!--[1-->");
      $$renderer2.push(`<div class="absolute inset-0 bg-black/90 flex flex-col items-center justify-center gap-2 p-4 text-xs text-muted-foreground z-20">`);
      Refresh_cw($$renderer2, { class: "size-5 animate-spin text-primary" });
      $$renderer2.push(`<!----> <span class="text-white/80">Connecting to camera hardware…</span></div>`);
    } else {
      $$renderer2.push("<!--[-1-->");
    }
    $$renderer2.push(`<!--]--> `);
    if (showTargetBox && (cameraStatus === "running" || cameraStatus === "initializing")) {
      $$renderer2.push("<!--[0-->");
      $$renderer2.push(`<div class="pointer-events-none absolute inset-0 flex flex-col items-center justify-center z-10"><div${attr_class(`relative size-36 sm:size-44 rounded-xl border-2 transition-colors duration-300 ${"border-primary/90 border-dashed bg-primary/5 shadow-2xs"}`)}><div${attr_class(`absolute -top-1 -left-1 size-3.5 border-t-2 border-l-2 ${"border-primary"}`)}></div> <div${attr_class(`absolute -top-1 -right-1 size-3.5 border-t-2 border-r-2 ${"border-primary"}`)}></div> <div${attr_class(`absolute -bottom-1 -left-1 size-3.5 border-b-2 border-l-2 ${"border-primary"}`)}></div> <div${attr_class(`absolute -bottom-1 -right-1 size-3.5 border-b-2 border-r-2 ${"border-primary"}`)}></div> `);
      {
        $$renderer2.push("<!--[-1-->");
      }
      $$renderer2.push(`<!--]--></div> <p class="mt-2 text-[11px] font-medium text-white/80 drop-shadow-md">${escape_html("Align QR code within reticle")}</p></div>`);
    } else {
      $$renderer2.push("<!--[-1-->");
    }
    $$renderer2.push(`<!--]--></div>`);
  });
}
function ApplicantScanModal($$renderer, $$props) {
  $$renderer.component(($$renderer2) => {
    let {
      open = false,
      onOpenChange = () => {
      },
      preselect = null
      // { key, label, urlFor(attemptId) } — page-specific purpose
    } = $$props;
    let stage = "idle";
    let message = "";
    let scannedAttempt = null;
    let destinations = [];
    let inputMode = "camera";
    let code = "";
    const resolveUrl = typeof route === "function" ? route("staff.scan.resolve") : "/staff/scan/resolve";
    function reset() {
      stage = "idle";
      message = "";
      scannedAttempt = null;
      destinations = [];
      code = "";
      inputMode = "camera";
    }
    function onOpenChangeInternal(value) {
      if (value) reset();
      onOpenChange(value);
    }
    async function resolveCode() {
      const trimmed = code.trim();
      if (!trimmed) return;
      stage = "resolving";
      try {
        const { data } = await window.axios.post(resolveUrl, { code: trimmed });
        scannedAttempt = data.scanned_attempt ?? null;
        destinations = data.destinations ?? [];
        if (data.scan_result?.state === "resolved" && scannedAttempt) {
          if (preselect?.urlFor) {
            router.visit(preselect.urlFor(scannedAttempt.id));
            onOpenChange(false);
            return;
          }
          stage = "resolved";
          return;
        }
        stage = data.scan_result?.state === "no_destination" ? "no_destination" : "invalid";
        message = data.scan_result?.message ?? "Code could not be resolved.";
      } catch (error) {
        if (error?.response?.status !== 419) {
          stage = "invalid";
          message = error?.response?.data?.scan_result?.message ?? "Code could not be resolved.";
        }
      } finally {
        code = "";
      }
    }
    let $$settled = true;
    let $$inner_renderer;
    function $$render_inner($$renderer3) {
      Dialog($$renderer3, {
        open,
        onOpenChange: onOpenChangeInternal,
        children: ($$renderer4) => {
          Dialog_content($$renderer4, {
            class: "flex max-h-[92dvh] flex-col p-0 overflow-hidden sm:max-w-lg border border-border/80 shadow-2xl rounded-2xl",
            children: ($$renderer5) => {
              $$renderer5.push(`<div class="border-b border-border/60 bg-muted/20 px-5 pt-5 pb-3"><div class="pr-8">`);
              Dialog_title($$renderer5, {
                class: "text-base font-bold tracking-tight text-foreground flex items-center gap-2",
                children: ($$renderer6) => {
                  Camera($$renderer6, { class: "size-4 text-primary shrink-0" });
                  $$renderer6.push(`<!----> <span>${escape_html(preselect?.label ?? "Scan Applicant QR")}</span>`);
                },
                $$slots: { default: true }
              });
              $$renderer5.push(`<!----> `);
              Dialog_description($$renderer5, {
                class: "text-xs text-muted-foreground mt-0.5",
                children: ($$renderer6) => {
                  $$renderer6.push(`<!---->${escape_html(preselect ? `Scan an applicant's QR — this will ${preselect.label.toLowerCase()}.` : "Scan an applicant QR to choose an authorized action. Scanning never mutates.")}`);
                },
                $$slots: { default: true }
              });
              $$renderer5.push(`<!----></div> `);
              if (stage !== "resolved") {
                $$renderer5.push("<!--[0-->");
                $$renderer5.push(`<div class="mt-3.5 grid grid-cols-2 gap-1 rounded-xl bg-muted/70 p-1 border border-border/50 text-xs font-semibold select-none" role="tablist" aria-label="Scan mode"><button type="button" role="tab"${attr("aria-selected", inputMode === "camera")}${attr_class(`flex items-center justify-center gap-2 py-1.5 rounded-lg transition-all duration-200 ${inputMode === "camera" ? "bg-background text-foreground shadow-xs font-bold" : "text-muted-foreground hover:text-foreground"}`)}>`);
                Camera($$renderer5, { class: "size-3.5" });
                $$renderer5.push(`<!----> <span>Camera Scanner</span></button> <button type="button" role="tab"${attr("aria-selected", inputMode === "manual")}${attr_class(`flex items-center justify-center gap-2 py-1.5 rounded-lg transition-all duration-200 ${inputMode === "manual" ? "bg-background text-foreground shadow-xs font-bold" : "text-muted-foreground hover:text-foreground"}`)}>`);
                Search($$renderer5, { class: "size-3.5" });
                $$renderer5.push(`<!----> <span>Manual Code</span></button></div>`);
              } else {
                $$renderer5.push("<!--[-1-->");
              }
              $$renderer5.push(`<!--]--></div> <div class="relative flex-1 overflow-y-auto px-5 py-4 min-h-[380px] max-h-[520px]">`);
              if (stage === "resolved") {
                $$renderer5.push("<!--[0-->");
                $$renderer5.push(`<div class="w-full max-w-[440px] mx-auto space-y-4 py-2"><div class="flex items-center justify-between rounded-xl border border-border/60 bg-muted/30 p-3"><div class="space-y-0.5"><p class="text-xs font-bold uppercase tracking-wider text-muted-foreground">Applicant Resolved</p> <p class="text-sm font-mono font-bold text-foreground">${escape_html(scannedAttempt?.reference_code)}</p></div> `);
                Badge($$renderer5, {
                  variant: "outline",
                  class: "border-emerald-500/40 text-emerald-600 bg-emerald-500/10 font-bold text-xs gap-1",
                  children: ($$renderer6) => {
                    Circle_check($$renderer6, { class: "size-3" });
                    $$renderer6.push(`<!----> Resolved`);
                  },
                  $$slots: { default: true }
                });
                $$renderer5.push(`<!----></div> <div class="space-y-2"><p class="text-xs font-semibold text-muted-foreground uppercase tracking-wider">Choose an authorized action:</p> `);
                if (destinations.length > 0) {
                  $$renderer5.push("<!--[0-->");
                  $$renderer5.push(`<div class="space-y-2"><!--[-->`);
                  const each_array = ensure_array_like(destinations);
                  for (let $$index = 0, $$length = each_array.length; $$index < $$length; $$index++) {
                    let d = each_array[$$index];
                    $$renderer5.push(`<button type="button" class="flex w-full items-center justify-between gap-3 rounded-xl border border-border/70 p-3.5 text-left text-sm font-semibold transition-all hover:bg-muted/60 hover:border-primary/50 shadow-2xs"><span>${escape_html(d.label)}</span> `);
                    Arrow_right($$renderer5, { class: "size-4 text-muted-foreground shrink-0" });
                    $$renderer5.push(`<!----></button>`);
                  }
                  $$renderer5.push(`<!--]--></div>`);
                } else {
                  $$renderer5.push("<!--[-1-->");
                  $$renderer5.push(`<div class="rounded-xl border border-dashed border-border p-4 text-center text-xs text-muted-foreground">No action destinations are available for your current duty or scope.</div>`);
                }
                $$renderer5.push(`<!--]--></div> <div class="pt-3 border-t border-border/40 flex items-center justify-between gap-2">`);
                Button($$renderer5, {
                  type: "button",
                  variant: "outline",
                  size: "sm",
                  class: "text-xs font-semibold gap-1.5",
                  onclick: reset,
                  children: ($$renderer6) => {
                    Refresh_cw($$renderer6, { class: "size-3" });
                    $$renderer6.push(`<!----> Scan another applicant`);
                  },
                  $$slots: { default: true }
                });
                $$renderer5.push(`<!----> `);
                Button($$renderer5, {
                  type: "button",
                  variant: "ghost",
                  size: "sm",
                  class: "text-xs font-medium",
                  onclick: () => onOpenChange(false),
                  children: ($$renderer6) => {
                    $$renderer6.push(`<!---->Done`);
                  },
                  $$slots: { default: true }
                });
                $$renderer5.push(`<!----></div></div>`);
              } else {
                $$renderer5.push("<!--[-1-->");
                if (inputMode === "camera") {
                  $$renderer5.push("<!--[0-->");
                  $$renderer5.push(`<div class="flex flex-col items-center space-y-3.5 w-full"><div class="relative w-full max-w-[440px] mx-auto">`);
                  QrScanner($$renderer5, {
                    active: open && inputMode === "camera" && stage !== "resolving" && stage !== "resolved",
                    paused: stage === "resolving",
                    onScan: (scannedCode) => {
                      code = scannedCode;
                      resolveCode();
                    },
                    class: "w-full shadow-inner"
                  });
                  $$renderer5.push(`<!----> `);
                  if (stage === "resolving") {
                    $$renderer5.push("<!--[0-->");
                    $$renderer5.push(`<div class="absolute inset-0 rounded-xl bg-background/85 backdrop-blur-xs flex flex-col items-center justify-center gap-2 z-30">`);
                    Refresh_cw($$renderer5, { class: "size-6 animate-spin text-primary" });
                    $$renderer5.push(`<!----> <span class="text-xs font-semibold text-foreground">Resolving applicant QR…</span></div>`);
                  } else {
                    $$renderer5.push("<!--[-1-->");
                  }
                  $$renderer5.push(`<!--]--></div> `);
                  if (stage === "invalid" || stage === "no_destination") {
                    $$renderer5.push("<!--[0-->");
                    $$renderer5.push(`<div class="w-full max-w-[440px] mx-auto rounded-lg border border-destructive/40 bg-destructive/10 p-2.5 text-xs text-destructive flex items-start gap-2">`);
                    Circle_alert($$renderer5, { class: "size-4 shrink-0 mt-0.5" });
                    $$renderer5.push(`<!----> <span class="font-medium">${escape_html(message || "Applicant code could not be resolved.")}</span></div>`);
                  } else {
                    $$renderer5.push("<!--[-1-->");
                  }
                  $$renderer5.push(`<!--]--> <div class="w-full max-w-[440px] mx-auto rounded-xl border border-border/60 bg-muted/20 p-3 space-y-1.5"><div class="text-[11px] font-bold uppercase tracking-wider text-muted-foreground text-center sm:text-left">Can't scan QR? Enter Applicant Number</div> <div class="flex gap-2">`);
                  Input($$renderer5, {
                    placeholder: "e.g. APP-2026-00005",
                    class: "h-9 text-xs font-mono uppercase",
                    disabled: stage === "resolving",
                    onkeydown: (e) => {
                      if (e.key === "Enter") resolveCode();
                    },
                    get value() {
                      return code;
                    },
                    set value($$value) {
                      code = $$value;
                      $$settled = false;
                    }
                  });
                  $$renderer5.push(`<!----> `);
                  Button($$renderer5, {
                    size: "sm",
                    class: "h-9 px-3 text-xs font-semibold shrink-0 gap-1",
                    disabled: !code.trim() || stage === "resolving",
                    onclick: resolveCode,
                    children: ($$renderer6) => {
                      if (stage === "resolving") {
                        $$renderer6.push("<!--[0-->");
                        Refresh_cw($$renderer6, { class: "size-3 animate-spin" });
                      } else {
                        $$renderer6.push("<!--[-1-->");
                        $$renderer6.push(`Resolve `);
                        Arrow_right($$renderer6, { class: "size-3" });
                        $$renderer6.push(`<!---->`);
                      }
                      $$renderer6.push(`<!--]-->`);
                    },
                    $$slots: { default: true }
                  });
                  $$renderer5.push(`<!----></div></div></div>`);
                } else {
                  $$renderer5.push("<!--[-1-->");
                  $$renderer5.push(`<div class="w-full max-w-[440px] mx-auto space-y-4 py-2"><div class="space-y-1.5"><label for="manual-locator-entry" class="block text-xs font-semibold text-foreground">Applicant Locator or Reference Code</label> `);
                  Input($$renderer5, {
                    id: "manual-locator-entry",
                    placeholder: "e.g. APP-2026-00005",
                    class: "h-10 font-mono text-sm uppercase",
                    disabled: stage === "resolving",
                    onkeydown: (e) => {
                      if (e.key === "Enter") resolveCode();
                    },
                    get value() {
                      return code;
                    },
                    set value($$value) {
                      code = $$value;
                      $$settled = false;
                    }
                  });
                  $$renderer5.push(`<!----> <p class="text-[11px] text-muted-foreground">Type the full applicant reference number or locator code printed on their pass.</p></div> `);
                  if (stage === "invalid" || stage === "no_destination") {
                    $$renderer5.push("<!--[0-->");
                    $$renderer5.push(`<div class="rounded-lg border border-destructive/40 bg-destructive/10 p-2.5 text-xs text-destructive flex items-start gap-2">`);
                    Circle_alert($$renderer5, { class: "size-4 shrink-0 mt-0.5" });
                    $$renderer5.push(`<!----> <span class="font-medium">${escape_html(message || "Applicant code could not be resolved.")}</span></div>`);
                  } else {
                    $$renderer5.push("<!--[-1-->");
                  }
                  $$renderer5.push(`<!--]--> `);
                  Button($$renderer5, {
                    type: "button",
                    onclick: resolveCode,
                    disabled: !code.trim() || stage === "resolving",
                    class: "w-full h-10 font-semibold gap-2",
                    children: ($$renderer6) => {
                      if (stage === "resolving") {
                        $$renderer6.push("<!--[0-->");
                        Refresh_cw($$renderer6, { class: "size-4 animate-spin" });
                        $$renderer6.push(`<!----> <span>Resolving applicant…</span>`);
                      } else {
                        $$renderer6.push("<!--[-1-->");
                        Search($$renderer6, { class: "size-4" });
                        $$renderer6.push(`<!----> <span>Resolve Applicant</span>`);
                      }
                      $$renderer6.push(`<!--]-->`);
                    },
                    $$slots: { default: true }
                  });
                  $$renderer5.push(`<!----></div>`);
                }
                $$renderer5.push(`<!--]-->`);
              }
              $$renderer5.push(`<!--]--></div>`);
            },
            $$slots: { default: true }
          });
        },
        $$slots: { default: true }
      });
    }
    do {
      $$settled = true;
      $$inner_renderer = $$renderer2.copy();
      $$render_inner($$inner_renderer);
    } while (!$$settled);
    $$renderer2.subsume($$inner_renderer);
  });
}
function SessionExpiredCard($$renderer, $$props) {
  $$renderer.component(($$renderer2) => {
    $$renderer2.push(`<div class="fixed inset-0 z-50 flex items-center justify-center bg-background/95 backdrop-blur-md p-4"><div role="alertdialog" aria-modal="true" aria-labelledby="session-expired-title" aria-describedby="session-expired-desc" class="w-full max-w-md rounded-2xl border border-border/60 bg-card p-6 text-center shadow-2xl"><div class="mb-5 flex size-12 items-center justify-center rounded-full bg-amber-500/10 text-amber-500 mx-auto">`);
    Clock($$renderer2, { class: "size-6", "aria-hidden": "true" });
    $$renderer2.push(`<!----></div> <h1 id="session-expired-title" class="text-xl font-bold tracking-tight text-foreground">Session expired</h1> <p id="session-expired-desc" class="mt-2 text-sm text-muted-foreground">Your NEXIAM staff session ended after 15 minutes without activity. For security and data privacy, sign in again to continue.</p> <p class="mt-4 rounded-lg border border-border/60 bg-muted/40 p-3 text-xs text-muted-foreground">Changes not submitted before the session ended may not have been saved.</p> <button type="button" class="mt-6 inline-flex h-11 w-full items-center justify-center rounded-lg bg-primary px-4 text-sm font-semibold text-primary-foreground shadow-xs transition-colors hover:bg-primary/90 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2">Sign in again</button></div></div>`);
  });
}
const STORAGE_KEY = "nexiam.staff.last_activity_at";
function getSessionStorage() {
  try {
    if (typeof window === "undefined") {
      return null;
    }
    return window.sessionStorage;
  } catch {
    return null;
  }
}
function clearStaffIdleMarker() {
  try {
    getSessionStorage()?.removeItem(STORAGE_KEY);
  } catch {
  }
}
function IdleTimeoutGuard($$renderer, $$props) {
  $$renderer.component(($$renderer2) => {
    const warningAfter = 13 * 60 * 1e3;
    const logoutAfter = 15 * 60 * 1e3;
    const activityEvents = [
      "mousemove",
      "keydown",
      "click",
      "scroll",
      "wheel",
      "touchstart"
    ];
    let state = "active";
    let remainingSeconds = 120;
    let keepAliveBusy = false;
    let keepAliveError = "";
    let lastActivityAt = 0;
    let warningTimer;
    let logoutTimer;
    let countdownTimer;
    let keepaliveTimeoutTimer;
    let keepaliveController;
    let activeGeneration = null;
    let warningCycle = 0;
    let activeWarningEpisodeId = null;
    let destroyed = false;
    const countdown = derived(() => `${String(Math.floor(remainingSeconds / 60)).padStart(2, "0")}:${String(remainingSeconds % 60).padStart(2, "0")}`);
    function clearTimers() {
      clearTimeout(warningTimer);
      clearTimeout(logoutTimer);
      clearInterval(countdownTimer);
      warningTimer = void 0;
      logoutTimer = void 0;
      countdownTimer = void 0;
    }
    function clearBrowserAlert() {
      try {
        closeStaffIdleAlert();
      } catch {
      }
    }
    function showBrowserAlert(generation) {
      const warningEpisodeId = activeWarningEpisodeId;
      if (!warningEpisodeId) {
        return;
      }
      try {
        showStaffIdleAlert(generation, warningEpisodeId);
      } catch {
      }
    }
    function playCue(cue) {
      try {
        void cue().catch(() => false);
      } catch {
      }
    }
    function abortKeepalive() {
      clearTimeout(keepaliveTimeoutTimer);
      keepaliveTimeoutTimer = void 0;
      const controller = keepaliveController;
      keepaliveController = void 0;
      controller?.abort();
      keepAliveBusy = false;
    }
    function armDeadlineTimers(generation) {
      clearTimeout(warningTimer);
      clearTimeout(logoutTimer);
      warningTimer = void 0;
      logoutTimer = void 0;
      if (state === "active") {
        warningTimer = setTimeout(
          () => {
            if (state === "expired" || state === "logged_out") {
              return;
            }
            reconcileDeadline(generation);
            if (state === "active") {
              armDeadlineTimers(generation);
            }
          },
          Math.max(0, lastActivityAt + warningAfter - Date.now())
        );
      }
      logoutTimer = setTimeout(
        () => {
          if (state === "expired" || state === "logged_out") {
            return;
          }
          reconcileDeadline(generation);
          if (state !== "expired" && state !== "logged_out") {
            armDeadlineTimers(generation);
          }
        },
        Math.max(0, lastActivityAt + logoutAfter - Date.now())
      );
    }
    function manualLogOut() {
      clearTimers();
      abortKeepalive();
      clearBrowserAlert();
      clearStaffIdleMarker();
      state = "logged_out";
      router.post("/logout", {}, {
        onFinish: () => {
          window.location.replace("/login");
        },
        onError: () => {
          window.location.replace("/login");
        }
      });
    }
    function attemptBackendLogout() {
      try {
        fetch("/logout", {
          method: "POST",
          credentials: "same-origin",
          headers: { Accept: "application/json", ...csrfHeaders() }
        }).catch(() => {
        });
      } catch {
      }
    }
    function expireSession(_reason, generation = activeGeneration) {
      if (!generation || generation !== activeGeneration || state === "expired" || state === "logged_out") {
        return;
      }
      clearTimers();
      state = "expired";
      abortKeepalive();
      clearBrowserAlert();
      clearStaffIdleMarker();
      playCue(() => sound.expired(() => !destroyed && activeGeneration === generation && state === "expired"));
      attemptBackendLogout();
    }
    function updateCountdown() {
      {
        return;
      }
    }
    function enterWarning() {
      if (state !== "active" || !activeGeneration) {
        return;
      }
      const generation = activeGeneration;
      const warningCycleAtEntry = ++warningCycle;
      activeWarningEpisodeId = `${generation}:${lastActivityAt + logoutAfter}`;
      state = "warning";
      keepAliveError = "";
      playCue(() => sound.warning(() => !destroyed && activeGeneration === generation && state === "warning" && warningCycle === warningCycleAtEntry && Date.now() < lastActivityAt + logoutAfter));
      if (document.visibilityState === "hidden") {
        showBrowserAlert(generation);
      }
      armDeadlineTimers(generation);
      if (state === "warning") {
        countdownTimer = setInterval(updateCountdown, 250);
      }
    }
    function reconcileDeadline(generation = activeGeneration) {
      if (!generation || generation !== activeGeneration || state === "expired" || state === "logged_out") {
        return;
      }
      const elapsed = Date.now() - lastActivityAt;
      if (elapsed >= logoutAfter) {
        expireSession("inactivity_deadline", generation);
        return;
      }
      if (elapsed >= warningAfter) {
        if (state === "active") {
          enterWarning();
        }
      }
    }
    function handleActivity() {
      {
        return;
      }
    }
    function reconcilePageResume() {
      reconcileDeadline(void 0);
      if (document.visibilityState === "visible") {
        clearBrowserAlert();
      }
    }
    async function stayLoggedIn() {
      {
        return;
      }
    }
    onDestroy(() => {
      destroyed = true;
      clearTimers();
      abortKeepalive();
      for (const eventName of activityEvents) {
        window.removeEventListener(eventName, handleActivity, { capture: true });
      }
      document.removeEventListener("visibilitychange", reconcilePageResume);
      window.removeEventListener("pageshow", reconcilePageResume);
      window.removeEventListener("focus", reconcilePageResume);
    });
    if (state === "expired") {
      $$renderer2.push("<!--[0-->");
      SessionExpiredCard($$renderer2);
    } else if (state === "warning") {
      $$renderer2.push("<!--[1-->");
      if (Dialog) {
        $$renderer2.push("<!--[-->");
        Dialog($$renderer2, {
          open: true,
          onOpenChange: () => {
          },
          children: ($$renderer3) => {
            if (Dialog_portal) {
              $$renderer3.push("<!--[-->");
              Dialog_portal($$renderer3, {
                children: ($$renderer4) => {
                  if (Dialog_overlay) {
                    $$renderer4.push("<!--[-->");
                    Dialog_overlay($$renderer4, {});
                    $$renderer4.push("<!--]-->");
                  } else {
                    $$renderer4.push("<!--[!-->");
                    $$renderer4.push("<!--]-->");
                  }
                  $$renderer4.push(` `);
                  if (Dialog_content) {
                    $$renderer4.push("<!--[-->");
                    Dialog_content($$renderer4, {
                      role: "alertdialog",
                      "aria-live": "polite",
                      "aria-modal": "true",
                      "aria-labelledby": "session-inactivity-title",
                      "aria-describedby": "session-inactivity-description",
                      showCloseButton: false,
                      class: "sm:max-w-md",
                      children: ($$renderer5) => {
                        if (Dialog_header) {
                          $$renderer5.push("<!--[-->");
                          Dialog_header($$renderer5, {
                            children: ($$renderer6) => {
                              if (Dialog_title) {
                                $$renderer6.push("<!--[-->");
                                Dialog_title($$renderer6, {
                                  id: "session-inactivity-title",
                                  children: ($$renderer7) => {
                                    $$renderer7.push(`<!---->Session Inactivity Warning`);
                                  },
                                  $$slots: { default: true }
                                });
                                $$renderer6.push("<!--]-->");
                              } else {
                                $$renderer6.push("<!--[!-->");
                                $$renderer6.push("<!--]-->");
                              }
                              $$renderer6.push(` `);
                              if (Dialog_description) {
                                $$renderer6.push("<!--[-->");
                                Dialog_description($$renderer6, {
                                  id: "session-inactivity-description",
                                  children: ($$renderer7) => {
                                    $$renderer7.push(`<!---->For campus security and data privacy, this workstation will automatically log out in ${escape_html(countdown())} due to inactivity.`);
                                  },
                                  $$slots: { default: true }
                                });
                                $$renderer6.push("<!--]-->");
                              } else {
                                $$renderer6.push("<!--[!-->");
                                $$renderer6.push("<!--]-->");
                              }
                            },
                            $$slots: { default: true }
                          });
                          $$renderer5.push("<!--]-->");
                        } else {
                          $$renderer5.push("<!--[!-->");
                          $$renderer5.push("<!--]-->");
                        }
                        $$renderer5.push(` `);
                        {
                          $$renderer5.push("<!--[0-->");
                          $$renderer5.push(`<p class="text-center font-mono text-2xl font-semibold tabular-nums" aria-live="polite">${escape_html(countdown())}</p>`);
                        }
                        $$renderer5.push(`<!--]--> `);
                        if (keepAliveError) {
                          $$renderer5.push("<!--[0-->");
                          $$renderer5.push(`<p class="text-sm text-destructive" role="alert">${escape_html(keepAliveError)}</p>`);
                        } else {
                          $$renderer5.push("<!--[-1-->");
                        }
                        $$renderer5.push(`<!--]--> `);
                        if (Dialog_footer) {
                          $$renderer5.push("<!--[-->");
                          Dialog_footer($$renderer5, {
                            children: ($$renderer6) => {
                              Button($$renderer6, {
                                type: "button",
                                variant: "outline",
                                onclick: manualLogOut,
                                children: ($$renderer7) => {
                                  $$renderer7.push(`<!---->Log Out Now`);
                                },
                                $$slots: { default: true }
                              });
                              $$renderer6.push(`<!----> `);
                              Button($$renderer6, {
                                type: "button",
                                onclick: stayLoggedIn,
                                disabled: keepAliveBusy,
                                children: ($$renderer7) => {
                                  $$renderer7.push(`<!---->${escape_html(keepAliveBusy ? "Checking..." : "Stay Logged In")}`);
                                },
                                $$slots: { default: true }
                              });
                              $$renderer6.push(`<!---->`);
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
    } else {
      $$renderer2.push("<!--[-1-->");
    }
    $$renderer2.push(`<!--]-->`);
  });
}
const scanModal = writable({ open: false, preselect: null });
function openScanModal(preselect = null) {
  scanModal.set({ open: true, preselect });
}
function closeScanModal() {
  scanModal.set({ open: false, preselect: null });
}
function StaffLayout($$renderer, $$props) {
  $$renderer.component(($$renderer2) => {
    var $$store_subs;
    let { children, breadcrumbs = [] } = $$props;
    const page = usePage();
    const user = derived(() => page.props.auth?.user ?? null);
    const officeTitle = derived(() => {
      const office = String(user()?.office ?? "").trim();
      if (!office) return "Staff Office";
      const labels = {
        guidance: "Guidance Office",
        guidance_counselor: "Guidance Office",
        registrar: "Registrar Office",
        registrar_admin: "Registrar Office",
        admissions: "Admissions Office",
        assessment: "Assessment Office",
        examination: "Examination Office",
        clinic: "Campus Clinic"
      };
      return labels[office.toLowerCase()] ?? office.replaceAll("_", " ").replace(/\b\w/g, (character) => character.toUpperCase());
    });
    const headTitle = derived(() => breadcrumbs.length > 0 ? `${breadcrumbs[breadcrumbs.length - 1].label} - NEXIAM` : "NEXIAM");
    let sidebarOpen = false;
    let breadcrumbOpen = false;
    let userDropdownOpen = false;
    const iconMap = {
      LayoutDashboard: Layout_dashboard,
      Users,
      ClipboardList: Clipboard_list,
      Calendar,
      Activity,
      Shield,
      FileCheck: File_check,
      MessagesSquare: Messages_square,
      Megaphone,
      Send,
      BookOpen: Book_open,
      Settings,
      Printer,
      FileSpreadsheet: File_spreadsheet
    };
    const navSections = derived(() => (page.props.staff_navigation ?? [
      {
        label: null,
        items: [
          {
            href: "/staff",
            label: "Home",
            icon: "LayoutDashboard",
            match: "exact"
          }
        ]
      }
    ]).map((section) => ({
      ...section,
      items: (section.items ?? []).map((item) => ({ ...item, icon: iconMap[item.icon] ?? Layout_dashboard }))
    })));
    const navItems = derived(() => navSections().flatMap((section) => section.items));
    function closeDropdowns() {
      userDropdownOpen = false;
      sidebarOpen = false;
    }
    function isNavActive(item) {
      return isStaffNavActive(item, navItems(), page.url || "");
    }
    head("9jcrxs", $$renderer2, ($$renderer3) => {
      $$renderer3.title(($$renderer4) => {
        $$renderer4.push(`<title>${escape_html(headTitle())}</title>`);
      });
    });
    $$renderer2.push(`<div class="h-dvh w-full max-w-[100vw] flex overflow-hidden relative"><div class="pointer-events-none fixed inset-0 -z-10 bg-[radial-gradient(circle_at_82%_15%,oklch(0.82_0.12_145_/_0.22),transparent_35%),radial-gradient(circle_at_12%_85%,oklch(0.9_0.08_85_/_0.25),transparent_35%)] dark:bg-[radial-gradient(circle_at_82%_15%,oklch(0.38_0.1_145_/_0.18),transparent_35%),radial-gradient(circle_at_12%_85%,oklch(0.35_0.07_85_/_0.15),transparent_35%)]"></div> `);
    InstitutionalBackdrop($$renderer2, { fixed: true });
    $$renderer2.push(`<!----> `);
    if (sidebarOpen) {
      $$renderer2.push("<!--[0-->");
      $$renderer2.push(`<button type="button" class="fixed inset-0 z-40 bg-black/50 lg:hidden print:hidden" aria-label="Close sidebar"></button>`);
    } else {
      $$renderer2.push("<!--[-1-->");
    }
    $$renderer2.push(`<!--]--> <aside${attr_class(`fixed inset-y-0 left-0 z-50 w-56 flex-shrink-0 glass-panel border-r flex flex-col transform transition-transform duration-200 ease-in-out -translate-x-full md:translate-x-0 ${sidebarOpen ? "translate-x-0" : ""} print:hidden`)}><div class="flex flex-col h-full overflow-hidden"><div class="h-14 flex items-center px-4 border-b border-border/50 shrink-0">`);
    Link($$renderer2, {
      href: "/staff",
      class: "flex items-center gap-3",
      onclick: closeDropdowns,
      children: ($$renderer3) => {
        $$renderer3.push(`<span class="flex size-8 items-center justify-center rounded-lg bg-primary text-primary-foreground font-bold text-sm shrink-0" aria-hidden="true">N</span> <span class="font-semibold text-base tracking-tight text-foreground">NEXIAM</span>`);
      },
      $$slots: { default: true }
    });
    $$renderer2.push(`<!----> `);
    Button($$renderer2, {
      variant: "ghost",
      size: "icon",
      class: "md:hidden ml-auto",
      onclick: () => sidebarOpen = !sidebarOpen,
      "aria-label": "Close menu",
      children: ($$renderer3) => {
        Menu($$renderer3, { class: "h-5 w-5" });
      },
      $$slots: { default: true }
    });
    $$renderer2.push(`<!----></div> <nav class="flex-1 overflow-y-auto scrollbar-hide p-2 space-y-1 svelte-9jcrxs"><!--[-->`);
    const each_array = ensure_array_like(navSections());
    for (let $$index_1 = 0, $$length = each_array.length; $$index_1 < $$length; $$index_1++) {
      let section = each_array[$$index_1];
      $$renderer2.push(`<div class="space-y-0.5">`);
      if (section.label) {
        $$renderer2.push("<!--[0-->");
        $$renderer2.push(`<p class="px-3 pt-2 pb-0.5 text-xs font-semibold uppercase tracking-wider text-muted-foreground/70">${escape_html(section.label)}</p>`);
      } else {
        $$renderer2.push("<!--[-1-->");
      }
      $$renderer2.push(`<!--]--> <!--[-->`);
      const each_array_1 = ensure_array_like(section.items);
      for (let $$index = 0, $$length2 = each_array_1.length; $$index < $$length2; $$index++) {
        let item = each_array_1[$$index];
        Link($$renderer2, {
          href: item.href,
          class: `nav-item flex items-center gap-2.5 px-3 py-2 rounded-md text-sm font-medium transition-colors duration-150 ${isNavActive(item) ? "bg-primary text-primary-foreground shadow-sm" : "text-foreground/80 hover:text-foreground hover:bg-accent"}`,
          onclick: closeDropdowns,
          children: ($$renderer3) => {
            if (item.icon) {
              $$renderer3.push("<!--[-->");
              item.icon($$renderer3, { class: "w-4 h-4 shrink-0" });
              $$renderer3.push("<!--]-->");
            } else {
              $$renderer3.push("<!--[!-->");
              $$renderer3.push("<!--]-->");
            }
            $$renderer3.push(` ${escape_html(item.label)}`);
          },
          $$slots: { default: true }
        });
      }
      $$renderer2.push(`<!--]--></div>`);
    }
    $$renderer2.push(`<!--]--></nav></div> <div class="p-3 pb-0 shrink-0">`);
    Button($$renderer2, {
      variant: "default",
      size: "lg",
      class: "w-full min-h-[44px] gap-2 font-semibold shadow-sm",
      onclick: () => openScanModal(),
      children: ($$renderer3) => {
        Qr_code($$renderer3, { class: "size-4" });
        $$renderer3.push(`<!----> Scan Applicant QR`);
      },
      $$slots: { default: true }
    });
    $$renderer2.push(`<!----></div> <div class="border-t border-border/50 p-3 shrink-0"><div class="relative flex items-center gap-2">`);
    Link($$renderer2, {
      href: "/staff/profile",
      class: "flex min-h-[48px] min-w-0 flex-1 items-center gap-3 rounded-xl px-2 py-2 text-left transition-colors hover:bg-muted/70 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring",
      "aria-label": `Open profile for ${stringify(user()?.name ?? "staff member")}`,
      onclick: closeDropdowns,
      children: ($$renderer3) => {
        $$renderer3.push(`<span class="relative shrink-0">`);
        AvatarBadge($$renderer3, { avatar: user()?.avatar, name: user()?.name ?? "", size: "md" });
        $$renderer3.push(`<!----> <span class="absolute bottom-0 right-0 size-3 rounded-full border-2 border-card bg-emerald-500" aria-hidden="true" title="Session active"></span></span> <span class="min-w-0 flex-1"><span class="block truncate text-sm font-semibold text-foreground">${escape_html(user()?.name ?? "User")}</span> <span class="block truncate text-xs text-muted-foreground">${escape_html(officeTitle())}</span></span>`);
      },
      $$slots: { default: true }
    });
    $$renderer2.push(`<!----> <button type="button" class="flex size-[48px] shrink-0 items-center justify-center rounded-lg text-muted-foreground transition-colors hover:bg-muted hover:text-foreground focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring" aria-label="Staff account menu"${attr("aria-expanded", userDropdownOpen)} aria-controls="staff-account-menu">`);
    Ellipsis_vertical($$renderer2, { class: "size-5", "aria-hidden": "true" });
    $$renderer2.push(`<!----></button> `);
    if (userDropdownOpen) {
      $$renderer2.push("<!--[0-->");
      $$renderer2.push(`<div id="staff-account-menu" class="absolute bottom-full right-0 z-50 mb-2 w-64 max-w-full rounded-xl border border-border bg-card-solid p-2 shadow-xl" role="group" aria-label="Staff account links"><div class="border-b border-border/70 px-3 py-2.5"><p class="truncate text-sm font-semibold text-foreground">${escape_html(user()?.name ?? "User")}</p> <p class="truncate text-xs text-muted-foreground">${escape_html(user()?.email ?? "")}</p> <p class="mt-1 truncate text-xs font-medium text-primary">${escape_html(officeTitle())}</p></div> `);
      Link($$renderer2, {
        href: "/staff/profile",
        class: "flex min-h-[44px] items-center gap-3 rounded-lg px-3 text-sm text-foreground transition-colors hover:bg-accent",
        onclick: closeDropdowns,
        children: ($$renderer3) => {
          User_round($$renderer3, { class: "size-4 text-muted-foreground", "aria-hidden": "true" });
          $$renderer3.push(`<!----> My Profile &amp; Directory`);
        },
        $$slots: { default: true }
      });
      $$renderer2.push(`<!----> `);
      Link($$renderer2, {
        href: "/staff/profile#security",
        class: "flex min-h-[44px] items-center gap-3 rounded-lg px-3 text-sm text-foreground transition-colors hover:bg-accent",
        onclick: closeDropdowns,
        children: ($$renderer3) => {
          Shield($$renderer3, { class: "size-4 text-muted-foreground", "aria-hidden": "true" });
          $$renderer3.push(`<!----> Security &amp; Credentials`);
        },
        $$slots: { default: true }
      });
      $$renderer2.push(`<!----> <button type="button" class="flex min-h-[44px] w-full items-center gap-3 rounded-lg px-3 text-left text-sm text-destructive transition-colors hover:bg-destructive/10">`);
      Log_out($$renderer2, { class: "size-4", "aria-hidden": "true" });
      $$renderer2.push(`<!----> Sign out</button></div>`);
    } else {
      $$renderer2.push("<!--[-1-->");
    }
    $$renderer2.push(`<!--]--></div></div></aside> <div class="flex min-w-0 flex-1 flex-col md:pl-56 relative print:pl-0"><header class="shrink-0 z-20 h-20 glass-panel border-b border-l-0 flex items-center justify-between gap-2 px-4 lg:px-8 relative print:hidden"><div class="flex min-w-0 flex-1 items-center gap-2 sm:gap-4">`);
    Button($$renderer2, {
      variant: "ghost",
      size: "icon",
      class: "md:hidden shrink-0",
      onclick: () => sidebarOpen = true,
      "aria-label": "Open menu",
      children: ($$renderer3) => {
        Menu($$renderer3, { class: "h-5 w-5" });
      },
      $$slots: { default: true }
    });
    $$renderer2.push(`<!----> `);
    if (breadcrumbs.length === 0) {
      $$renderer2.push("<!--[0-->");
      $$renderer2.push(`<span class="text-sm font-semibold tracking-tight text-foreground">NEXIAM</span>`);
    } else {
      $$renderer2.push("<!--[-1-->");
      $$renderer2.push(`<nav class="hidden min-w-0 flex-1 items-center gap-1.5 overflow-x-auto whitespace-nowrap text-xs scrollbar-hide lg:flex svelte-9jcrxs" aria-label="Breadcrumb"><!--[-->`);
      const each_array_2 = ensure_array_like(breadcrumbs);
      for (let i = 0, $$length = each_array_2.length; i < $$length; i++) {
        let crumb = each_array_2[i];
        if (i > 0) {
          $$renderer2.push("<!--[0-->");
          Chevron_right($$renderer2, {
            class: "h-4 w-4 text-muted-foreground/50 shrink-0",
            "aria-hidden": "true"
          });
        } else {
          $$renderer2.push("<!--[-1-->");
        }
        $$renderer2.push(`<!--]--> `);
        if (crumb.href && i < breadcrumbs.length - 1) {
          $$renderer2.push("<!--[0-->");
          Link($$renderer2, {
            href: crumb.href,
            class: "text-muted-foreground hover:text-foreground transition-colors font-medium shrink-0",
            children: ($$renderer3) => {
              $$renderer3.push(`<!---->${escape_html(crumb.label)}`);
            },
            $$slots: { default: true }
          });
        } else {
          $$renderer2.push("<!--[-1-->");
          $$renderer2.push(`<span class="font-semibold text-foreground truncate max-w-[180px] sm:max-w-none shrink-0"${attr("aria-current", i === breadcrumbs.length - 1 ? "page" : void 0)}>${escape_html(crumb.label)}</span>`);
        }
        $$renderer2.push(`<!--]-->`);
      }
      $$renderer2.push(`<!--]--></nav> <div class="flex min-w-0 flex-1 items-center gap-2 lg:hidden"><div class="min-w-0 flex-1"><button type="button" class="flex min-h-11 min-w-0 max-w-full items-center gap-2 text-sm"${attr("aria-expanded", breadcrumbOpen)} aria-label="Breadcrumb navigation"><span class="rounded bg-muted px-2 py-0.5 text-xs font-semibold text-muted-foreground">Path</span> `);
      Chevron_right($$renderer2, {
        class: "h-4 w-4 text-muted-foreground/50 shrink-0",
        "aria-hidden": "true"
      });
      $$renderer2.push(`<!----> <span class="min-w-0 truncate font-semibold text-foreground">${escape_html(breadcrumbs[breadcrumbs.length - 1].label)}</span> `);
      Chevron_down($$renderer2, {
        class: `h-4 w-4 text-muted-foreground ml-1 shrink-0 transition-transform ${""}`,
        "aria-hidden": "true"
      });
      $$renderer2.push(`<!----></button> `);
      {
        $$renderer2.push("<!--[-1-->");
      }
      $$renderer2.push(`<!--]--></div></div>`);
    }
    $$renderer2.push(`<!--]--></div> <div class="flex shrink-0 items-center gap-2 sm:gap-4">`);
    NotificationBell($$renderer2);
    $$renderer2.push(`<!----> `);
    PreferencesMenu($$renderer2);
    $$renderer2.push(`<!----> `);
    Link($$renderer2, {
      href: "/staff/profile",
      class: "flex size-[48px] items-center justify-center rounded-full border border-border bg-card transition-colors hover:border-primary/50 hover:bg-accent focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring md:hidden",
      "aria-label": "Open my staff profile",
      children: ($$renderer3) => {
        AvatarBadge($$renderer3, { avatar: user()?.avatar, name: user()?.name ?? "", size: "sm" });
      },
      $$slots: { default: true }
    });
    $$renderer2.push(`<!----> `);
    ToastManager($$renderer2, { placement: "below-anchor" });
    $$renderer2.push(`<!----></div></header> <main class="flex-1 min-w-0 overflow-x-hidden overflow-y-auto p-4 lg:p-8 scroll-smooth" id="main-content"><div class="min-w-0 w-full max-w-full">`);
    children?.($$renderer2);
    $$renderer2.push(`<!----></div></main></div></div> `);
    ApplicantScanModal($$renderer2, {
      open: store_get($$store_subs ??= {}, "$scanModal", scanModal).open,
      preselect: store_get($$store_subs ??= {}, "$scanModal", scanModal).preselect,
      onOpenChange: () => closeScanModal()
    });
    $$renderer2.push(`<!----> `);
    IdleTimeoutGuard($$renderer2);
    $$renderer2.push(`<!---->`);
    if ($$store_subs) unsubscribe_stores($$store_subs);
  });
}
const StaffLayout$1 = /* @__PURE__ */ Object.freeze(/* @__PURE__ */ Object.defineProperty({
  __proto__: null,
  default: StaffLayout
}, Symbol.toStringTag, { value: "Module" }));
export {
  Calendar as C,
  Messages_square as M,
  QrScanner as Q,
  StaffLayout as S,
  Send as a,
  StaffLayout$1 as b
};
