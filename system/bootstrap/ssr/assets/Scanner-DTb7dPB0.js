import { o as onDestroy, h as head, B as Button, e as escape_html, b as attr, a as ensure_array_like, d as derived } from "../app.js";
import { S as StaffLayout } from "./StaffLayout-CR-MZopa.js";
import { P as PageHeaderBar } from "./PageHeaderBar-DJhT5vOR.js";
import { I as Input } from "./input-BUZAZKIK.js";
import { L as Label } from "./label-dGY6L9vm.js";
import { S as Scan_line } from "./scan-line-CiwhUtYK.js";
import { C as Camera } from "./camera-C09Eyogi.js";
import { U as Upload } from "./upload-CwqN2Utd.js";
import { F as File_image } from "./file-image-BGlN21-8.js";
import { C as Circle_check } from "./circle-check-C59ZS7Zv.js";
import { S as Shield_check } from "./shield-check-CLSg-ako.js";
import { L as Link } from "./Link-B7ks7rBK.js";
import { A as Arrow_left } from "./arrow-left-DbWnQR3t.js";
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
import "./badge-D9Vgs7xh.js";
import "./dialog-description-D12L0QA9.js";
import "./circle-alert-jWXJ7oC0.js";
import "./refresh-cw-CYdk8aE0.js";
import "./settings-C5xOKpV3.js";
import "./arrow-right-CzynHiIo.js";
import "./dialog-footer-kNnwKT2h.js";
import "./clock-B4fXoAi9.js";
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
function Scanner($$renderer, $$props) {
  $$renderer.component(($$renderer2) => {
    let {
      applicant_number = "",
      outcome = null,
      candidates = [],
      recovery = null,
      pending_capture = null,
      capture_url = "/grading/omr/captures"
    } = $$props;
    let cameraStream = null;
    let cameraState = "starting";
    let uploading = false;
    let retrying = false;
    let adopting = false;
    let selectingSession = false;
    let applicantNumber = "";
    let displayCode = "";
    let manualConfirmed = false;
    const identityRecoveryCodes = [
      "applicant_unresolved",
      "identity_unresolved",
      "identity_conflict",
      "unreadable"
    ];
    const pendingActions = derived(() => pending_capture?.actions ?? {});
    const pendingCandidates = derived(() => pending_capture?.candidates?.length ? pending_capture.candidates : candidates);
    const resolvedSession = derived(() => pending_capture?.status === "ready");
    const manualIdentityAllowed = derived(() => pending_capture !== null && identityRecoveryCodes.includes(pending_capture.resolution_code));
    const displayCodeAllowed = derived(() => pending_capture !== null && ["absent", "undecodable"].includes(pending_capture.qr_state) && recovery?.secondary_action === "enter_display_code");
    const scannerContext = derived(() => {
      const entityKey = String(pending_capture?.id ?? "station");
      const isBlk = recovery !== null;
      const safetyKey = `${pending_capture ? pending_capture.status : "idle"}:${cameraState}:${resolvedSession() ? "res" : "unres"}:${isBlk ? "blk" : "ok"}:${manualIdentityAllowed() ? "manual" : "auto"}:${displayCodeAllowed() ? "disp" : "norm"}`;
      return {
        entityKey,
        safetyKey,
        freshness: "current",
        hasPendingCapture: pending_capture !== null,
        captureStatus: pending_capture?.status ?? null,
        cameraState,
        isResolvedSession: resolvedSession(),
        manualIdentityAllowed: manualIdentityAllowed(),
        displayCodeAllowed: displayCodeAllowed(),
        isBlocked: isBlk,
        recoveryCode: recovery?.code ?? null,
        recoveryOwner: recovery?.owner ?? null,
        candidateCount: pendingCandidates().length,
        sessionCode: pending_capture?.session?.exam_code ?? null,
        roomName: pending_capture?.session?.room_name ?? null
      };
    });
    onDestroy(() => {
      stopCamera();
    });
    function stopCamera() {
      cameraStream?.getTracks?.().forEach((track) => track.stop());
      cameraStream = null;
    }
    function triggerUpload() {
    }
    function captureFrame() {
      return;
    }
    function retryCapture() {
      if (!pendingActions().retry || retrying || pending_capture === null) return;
      retrying = true;
      router.post(
        pendingActions().retry,
        {
          expected_version: pending_capture.version,
          idempotency_key: crypto.randomUUID()
        },
        { onFinish: () => retrying = false }
      );
    }
    function adoptCapture() {
      if (!pendingActions().adopt || adopting || pending_capture === null) return;
      adopting = true;
      router.post(
        pendingActions().adopt,
        {
          expected_version: pending_capture.version,
          idempotency_key: crypto.randomUUID()
        },
        { onFinish: () => adopting = false }
      );
    }
    function openRecoveryDestination() {
      if (recovery?.destination_url) window.location.assign(recovery.destination_url);
    }
    let $$settled = true;
    let $$inner_renderer;
    function $$render_inner($$renderer3) {
      head("bbrd3n", $$renderer3, ($$renderer4) => {
        $$renderer4.title(($$renderer5) => {
          $$renderer5.push(`<title>Answer Sheet Scanner · NEXIAM</title>`);
        });
      });
      StaffLayout($$renderer3, {
        breadcrumbs: [
          { label: "Scoring", href: "/grading" },
          { label: "Answer Sheet Scanner" }
        ],
        children: ($$renderer4) => {
          $$renderer4.push(`<div class="mx-auto min-w-0 max-w-4xl space-y-6 p-4 sm:p-6">`);
          {
            let actions = function($$renderer5) {
              Link($$renderer5, {
                href: "/grading",
                children: ($$renderer6) => {
                  Button($$renderer6, {
                    variant: "outline",
                    class: "min-h-10 gap-2",
                    children: ($$renderer7) => {
                      Arrow_left($$renderer7, { class: "size-4" });
                      $$renderer7.push(`<!----> Back to scoring`);
                    },
                    $$slots: { default: true }
                  });
                },
                $$slots: { default: true }
              });
            };
            PageHeaderBar($$renderer4, {
              title: "Answer Sheet Scanner",
              description: "Capture physical answer sheets to verify QR provenance, read applicant identifiers, and match submitted exam sessions.",
              tourKey: "assessment-omr-camera-scanner",
              context: scannerContext(),
              actions
            });
          }
          $$renderer4.push(`<!----> `);
          if (pending_capture === null) {
            $$renderer4.push("<!--[0-->");
            $$renderer4.push(`<section class="rounded-xl border border-border bg-card p-5 shadow-sm sm:p-6" data-tour="omr-scanner-capture"><div class="flex items-start gap-3"><div class="rounded-lg bg-primary/10 p-2 text-primary">`);
            Scan_line($$renderer4, { class: "size-5" });
            $$renderer4.push(`<!----></div> <div><h2 class="font-semibold tracking-tight">Capture the paper</h2> <p class="mt-1 text-sm text-muted-foreground">Use the camera or upload one clear image. Do not type an Applicant Number until the scan reports an unreadable or unresolved identity.</p></div></div> <div class="mt-5 grid gap-4 lg:grid-cols-[1.4fr_1fr]"><div class="overflow-hidden rounded-xl border border-border bg-muted/40"><video class="aspect-[4/3] h-full min-h-56 w-full object-cover" playsinline="" muted=""></video> `);
            {
              $$renderer4.push("<!--[0-->");
              $$renderer4.push(`<div class="flex items-center gap-2 p-3 text-sm text-muted-foreground">`);
              {
                $$renderer4.push("<!--[-1-->");
                Camera($$renderer4, { class: "size-4" });
                $$renderer4.push(`<!----> Requesting camera access…`);
              }
              $$renderer4.push(`<!--]--></div>`);
            }
            $$renderer4.push(`<!--]--></div> <div class="flex flex-col justify-center gap-3">`);
            Button($$renderer4, {
              class: "min-h-11 gap-2",
              onclick: captureFrame,
              disabled: cameraState !== "ready",
              children: ($$renderer5) => {
                Camera($$renderer5, { class: "size-4" });
                $$renderer5.push(`<!----> ${escape_html("Capture frame")}`);
              },
              $$slots: { default: true }
            });
            $$renderer4.push(`<!----> `);
            Button($$renderer4, {
              variant: "outline",
              class: "min-h-11 gap-2",
              onclick: triggerUpload,
              disabled: uploading,
              children: ($$renderer5) => {
                Upload($$renderer5, { class: "size-4" });
                $$renderer5.push(`<!----> Upload image`);
              },
              $$slots: { default: true }
            });
            $$renderer4.push(`<!----> `);
            {
              $$renderer4.push("<!--[-1-->");
            }
            $$renderer4.push(`<!--]--> <input class="hidden" type="file" accept="image/jpeg,image/png,image/webp" capture="environment"/> <p class="text-xs leading-5 text-muted-foreground">The original image is preserved server-side. QR/template verification and all identity/session matching stay server-authoritative.</p></div></div></section>`);
          } else {
            $$renderer4.push("<!--[-1-->");
            $$renderer4.push(`<section class="rounded-xl border border-border bg-card p-5 shadow-sm sm:p-6" data-tour="omr-scanner-capture-status"><div class="flex flex-wrap items-start justify-between gap-3"><div class="flex items-start gap-3"><div class="rounded-lg bg-primary/10 p-2 text-primary">`);
            File_image($$renderer4, { class: "size-5" });
            $$renderer4.push(`<!----></div> <div><h2 class="font-semibold tracking-tight">Preserved capture #${escape_html(pending_capture.id)}</h2> <p class="mt-1 text-sm text-muted-foreground">The same immutable image remains attached while identity or session recovery is completed.</p></div></div> <span class="rounded-full border border-border px-2.5 py-1 text-xs font-semibold uppercase tracking-wide">${escape_html(pending_capture.status)}</span></div> `);
            if (pending_capture.status === "analyzing") {
              $$renderer4.push("<!--[0-->");
              $$renderer4.push(`<div class="mt-5 flex items-center gap-2 rounded-lg border border-primary/30 bg-primary/5 p-4 text-sm">`);
              Scan_line($$renderer4, { class: "size-4 animate-pulse" });
              $$renderer4.push(`<!----> Verifying QR, template, and sheet marks…</div>`);
            } else if (resolvedSession()) {
              $$renderer4.push("<!--[1-->");
              $$renderer4.push(`<div class="mt-5 rounded-lg border border-emerald-500/40 bg-emerald-500/5 p-4"><div class="flex items-start gap-2">`);
              Circle_check($$renderer4, { class: "mt-0.5 size-5 text-emerald-600" });
              $$renderer4.push(`<!----><div><h3 class="font-semibold">Session resolved</h3><p class="mt-1 text-sm text-muted-foreground">${escape_html(pending_capture.session?.exam_code ?? "Verified session")} · ${escape_html(pending_capture.session?.room_name ?? "Room unavailable")}</p></div></div> <p class="mt-3 text-sm text-muted-foreground">The capture is ready to enter the existing review workbench. No score is written until staff review and batch confirmation.</p> `);
              Button($$renderer4, {
                class: "mt-4 min-h-10 gap-2",
                onclick: adoptCapture,
                disabled: adopting,
                children: ($$renderer5) => {
                  Shield_check($$renderer5, { class: "size-4" });
                  $$renderer5.push(`<!----> ${escape_html(adopting ? "Adopting…" : "Open review workbench")}`);
                },
                $$slots: { default: true }
              });
              $$renderer4.push(`<!----></div>`);
            } else {
              $$renderer4.push("<!--[-1-->");
            }
            $$renderer4.push(`<!--]--></section>`);
          }
          $$renderer4.push(`<!--]--> `);
          if (recovery) {
            $$renderer4.push("<!--[0-->");
            $$renderer4.push(`<section class="rounded-xl border border-amber-500/40 bg-amber-500/5 p-5 sm:p-6" role="alert"><div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between"><div><h2 class="font-semibold text-amber-800 dark:text-amber-300">${escape_html(recovery.title)}</h2><p class="mt-1 text-sm text-muted-foreground">${escape_html(recovery.explanation)}</p></div> <span class="w-fit rounded-full border border-amber-500/40 px-2.5 py-1 text-xs font-semibold text-amber-800 dark:text-amber-300">Scoring blocked</span></div> <dl class="mt-4 grid gap-3 text-sm sm:grid-cols-2"><div><dt class="text-muted-foreground">Responsible owner</dt><dd class="font-medium">${escape_html(recovery.owner)}</dd></div><div><dt class="text-muted-foreground">Support code</dt><dd class="font-mono font-medium">${escape_html(recovery.code)}</dd></div></dl> `);
            if (recovery.primary_action === "open_monitoring" && recovery.destination_url) {
              $$renderer4.push("<!--[0-->");
              Button($$renderer4, {
                class: "mt-4 min-h-10",
                onclick: openRecoveryDestination,
                children: ($$renderer5) => {
                  $$renderer5.push(`<!---->Open Exam Monitoring`);
                },
                $$slots: { default: true }
              });
            } else if (recovery.primary_action === "retry_scan" && pending_capture) {
              $$renderer4.push("<!--[1-->");
              Button($$renderer4, {
                class: "mt-4 min-h-10 gap-2",
                onclick: retryCapture,
                disabled: retrying,
                children: ($$renderer5) => {
                  Scan_line($$renderer5, { class: "size-4" });
                  $$renderer5.push(`<!----> ${escape_html(retrying ? "Retrying…" : "Retry this preserved capture")}`);
                },
                $$slots: { default: true }
              });
            } else {
              $$renderer4.push("<!--[-1-->");
            }
            $$renderer4.push(`<!--]--></section>`);
          } else {
            $$renderer4.push("<!--[-1-->");
          }
          $$renderer4.push(`<!--]--> `);
          if (manualIdentityAllowed()) {
            $$renderer4.push("<!--[0-->");
            $$renderer4.push(`<section class="rounded-xl border border-border bg-card p-5 sm:p-6" data-tour="omr-scanner-applicant-number"><h2 class="font-semibold tracking-tight">Applicant Number needs staff confirmation</h2> <p class="mt-1 text-sm text-muted-foreground">The machine identity was absent, unreadable, or unresolved. Enter only the printed Applicant Number; names, birthdate, room, time, handwriting, and observed Set are not used to infer a session.</p> <form class="mt-4 flex flex-col gap-3 sm:flex-row sm:items-end"><div class="min-w-0 flex-1 space-y-2">`);
            Label($$renderer4, {
              for: "manual-applicant-number",
              children: ($$renderer5) => {
                $$renderer5.push(`<!---->Applicant Number`);
              },
              $$slots: { default: true }
            });
            $$renderer4.push(`<!---->`);
            Input($$renderer4, {
              id: "manual-applicant-number",
              autocomplete: "off",
              maxlength: "64",
              pattern: "[A-Za-z0-9._-]+",
              required: true,
              get value() {
                return applicantNumber;
              },
              set value($$value) {
                applicantNumber = $$value;
                $$settled = false;
              }
            });
            $$renderer4.push(`<!----></div> `);
            Button($$renderer4, {
              type: "submit",
              class: "min-h-10 gap-2",
              disabled: !applicantNumber.trim(),
              children: ($$renderer5) => {
                Shield_check($$renderer5, { class: "size-4" });
                $$renderer5.push(`<!----> ${escape_html("Match eligible records")}`);
              },
              $$slots: { default: true }
            });
            $$renderer4.push(`<!----></form></section>`);
          } else {
            $$renderer4.push("<!--[-1-->");
          }
          $$renderer4.push(`<!--]--> `);
          if (displayCodeAllowed()) {
            $$renderer4.push("<!--[0-->");
            $$renderer4.push(`<section class="rounded-xl border border-border bg-card p-5 sm:p-6" data-tour="omr-scanner-display-code"><h2 class="font-semibold tracking-tight">QR recovery: use the printed Display Code</h2> <p class="mt-1 text-sm text-muted-foreground">This option is available only when the QR is absent or unreadable. A present-but-invalid QR can never be bypassed.</p> <form class="mt-4 space-y-3"><div class="space-y-2">`);
            Label($$renderer4, {
              for: "display-code",
              children: ($$renderer5) => {
                $$renderer5.push(`<!---->Display Code`);
              },
              $$slots: { default: true }
            });
            $$renderer4.push(`<!---->`);
            Input($$renderer4, {
              id: "display-code",
              autocomplete: "off",
              maxlength: "64",
              required: true,
              get value() {
                return displayCode;
              },
              set value($$value) {
                displayCode = $$value;
                $$settled = false;
              }
            });
            $$renderer4.push(`<!----></div> <label class="flex items-start gap-2 text-sm"><input type="checkbox"${attr("checked", manualConfirmed, true)} class="mt-1"/> <span>I verified this printed Display Code against the preserved paper.</span></label> `);
            Button($$renderer4, {
              type: "submit",
              class: "min-h-10",
              disabled: !displayCode.trim() || !manualConfirmed,
              children: ($$renderer5) => {
                $$renderer5.push(`<!---->${escape_html("Record Display Code and retry")}`);
              },
              $$slots: { default: true }
            });
            $$renderer4.push(`<!----></form></section>`);
          } else {
            $$renderer4.push("<!--[-1-->");
          }
          $$renderer4.push(`<!--]--> `);
          if (pending_capture && pendingCandidates().length > 1) {
            $$renderer4.push("<!--[0-->");
            $$renderer4.push(`<section class="rounded-xl border border-border bg-card p-5 sm:p-6"><h2 class="font-semibold tracking-tight">Choose the verified session</h2> <p class="mt-1 text-sm text-muted-foreground">Multiple eligible submitted sessions match. Choose explicitly; no other paper detail breaks the tie.</p> <div class="mt-4 grid gap-3 sm:grid-cols-2"><!--[-->`);
            const each_array = ensure_array_like(pendingCandidates());
            for (let $$index = 0, $$length = each_array.length; $$index < $$length; $$index++) {
              let candidate = each_array[$$index];
              $$renderer4.push(`<button type="button" class="rounded-lg border border-border p-4 text-left transition-colors hover:border-primary/50 hover:bg-primary/5"${attr("disabled", selectingSession, true)}><strong class="block">${escape_html(candidate.exam_code)}</strong><span class="mt-1 block text-sm text-muted-foreground">${escape_html(candidate.room_name)}</span><span class="mt-3 block text-sm font-semibold text-primary">${escape_html("Use this session")}</span></button>`);
            }
            $$renderer4.push(`<!--]--></div></section>`);
          } else if (!pending_capture && outcome === "selection_required" && candidates.length > 1) {
            $$renderer4.push("<!--[1-->");
            $$renderer4.push(`<section class="rounded-xl border border-border bg-card p-5 sm:p-6"><h2 class="font-semibold tracking-tight">Choose the verified session</h2><p class="mt-1 text-sm text-muted-foreground">Multiple eligible submitted sessions match. Choose explicitly; no other paper detail breaks the tie.</p><div class="mt-4 grid gap-3 sm:grid-cols-2"><!--[-->`);
            const each_array_1 = ensure_array_like(candidates);
            for (let $$index_1 = 0, $$length = each_array_1.length; $$index_1 < $$length; $$index_1++) {
              let candidate = each_array_1[$$index_1];
              $$renderer4.push(`<button type="button" class="rounded-lg border border-border p-4 text-left"><strong class="block">${escape_html(candidate.exam_code)}</strong><span class="mt-1 block text-sm text-muted-foreground">${escape_html(candidate.room_name)}</span><span class="mt-3 block text-sm font-semibold text-primary">Open session workbench</span></button>`);
            }
            $$renderer4.push(`<!--]--></div></section>`);
          } else {
            $$renderer4.push("<!--[-1-->");
          }
          $$renderer4.push(`<!--]--></div>`);
        }
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
export {
  Scanner as default
};
