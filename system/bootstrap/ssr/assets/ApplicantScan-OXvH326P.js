import { o as onDestroy, e as escape_html, b as attr, c as attr_class, B as Button, h as head, a as ensure_array_like } from "../app.js";
import { Q as QrScanner, S as StaffLayout } from "./StaffLayout-CR-MZopa.js";
import E2PageState from "./E2PageState-yFavAmgl.js";
import { P as PageHeaderBar } from "./PageHeaderBar-DJhT5vOR.js";
import { B as Badge } from "./badge-D9Vgs7xh.js";
import { C as Card, a as Card_content } from "./card-content-Brg2wRkV.js";
import "clsx";
import { I as Input } from "./input-BUZAZKIK.js";
import { D as Dialog, a as Dialog_content, c as Dialog_title, d as Dialog_description, b as Dialog_header } from "./dialog-description-D12L0QA9.js";
import { D as Dialog_footer } from "./dialog-footer-kNnwKT2h.js";
import { C as Camera } from "./camera-C09Eyogi.js";
import { S as Search } from "./settings-C5xOKpV3.js";
import { A as Arrow_right } from "./arrow-right-CzynHiIo.js";
import { R as Refresh_cw } from "./refresh-cw-CYdk8aE0.js";
import { C as Circle_check } from "./circle-check-C59ZS7Zv.js";
import { C as Circle_alert } from "./circle-alert-jWXJ7oC0.js";
import { router } from "@inertiajs/core";
import { u as useForm } from "./useForm.svelte-C0OkDFGu.js";
import { Q as Qr_code } from "./qr-code-C5X8qcH6.js";
import { L as Link } from "./Link-B7ks7rBK.js";
import { S as Shield_check } from "./shield-check-CLSg-ako.js";
import "axios";
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
import "./clock-B4fXoAi9.js";
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
import "./card-description-BGy_v5-d.js";
import "./card-title-BNY2ZPje.js";
import "./shield-alert-CXtDdte9.js";
import "./XiamNpcDialog-DoqCSHj9.js";
/* empty css                                                        */
import "./circle-question-mark-DY-RU614.js";
import "./sparkles-DbQIaVG6.js";
import "./chevron-left-DCSOZwZl.js";
import "es-toolkit";
import "./clone-BJz-lxsY.js";
import "es-toolkit/compat";
function UniversalQrModal($$renderer, $$props) {
  $$renderer.component(($$renderer2) => {
    let {
      open = false,
      onOpenChange = () => {
      },
      title = "Scan QR Code",
      description = "Point camera at QR code or enter code manually.",
      mode = "redirect",
      resolveUrl = "/staff/scan/resolve",
      continuous = false,
      onScan = () => {
      },
      onResolved = () => {
      },
      manualPlaceholder = "e.g. APP-2026-00012 or locator code"
    } = $$props;
    let inputMode = "camera";
    let manualCode = "";
    let isProcessing = false;
    let feedbackStatus = null;
    let scannerPaused = false;
    let modalSession = 0;
    let redirectTimer = null;
    let feedbackTimer = null;
    function clearTimers() {
      if (redirectTimer !== null) {
        clearTimeout(redirectTimer);
        redirectTimer = null;
      }
      if (feedbackTimer !== null) {
        clearTimeout(feedbackTimer);
        feedbackTimer = null;
      }
    }
    onDestroy(() => {
      modalSession++;
      clearTimers();
    });
    async function handleCode(rawCode) {
      const code = rawCode.trim();
      if (!code || isProcessing) return;
      const currentSession = modalSession;
      isProcessing = true;
      scannerPaused = true;
      feedbackStatus = null;
      clearTimers();
      const context = {
        close: () => onOpenChange(false),
        reset: () => {
          if (currentSession !== modalSession) return;
          isProcessing = false;
          scannerPaused = false;
          feedbackStatus = null;
          manualCode = "";
        }
      };
      if (mode === "callback") {
        try {
          await onScan(code, context);
          if (!open || currentSession !== modalSession) return;
          feedbackStatus = { type: "success", message: "Code processed successfully." };
          if (!continuous) {
            redirectTimer = setTimeout(
              () => {
                if (open && currentSession === modalSession) {
                  onOpenChange(false);
                }
              },
              500
            );
          } else {
            feedbackTimer = setTimeout(
              () => {
                if (open && currentSession === modalSession) {
                  feedbackStatus = null;
                  scannerPaused = false;
                  isProcessing = false;
                  manualCode = "";
                }
              },
              1200
            );
          }
        } catch (err) {
          if (!open || currentSession !== modalSession) return;
          feedbackStatus = {
            type: "error",
            message: err.message || "Failed to process QR code."
          };
          scannerPaused = false;
          isProcessing = false;
        }
        return;
      }
      try {
        const { data } = await window.axios.post(resolveUrl, { code });
        if (!open || currentSession !== modalSession) return;
        if (data.scan_result?.state === "resolved") {
          feedbackStatus = { type: "success", message: "Applicant verified." };
          onResolved(data);
          const redirectTarget = data.scanned_attempt?.id ? `/consultation/applicants/${data.scanned_attempt.id}` : data.destinations?.[0]?.url ?? "/consultation";
          if (!continuous) {
            redirectTimer = setTimeout(
              () => {
                if (open && currentSession === modalSession) {
                  onOpenChange(false);
                  router.visit(redirectTarget);
                }
              },
              350
            );
          } else {
            feedbackTimer = setTimeout(
              () => {
                if (open && currentSession === modalSession) {
                  feedbackStatus = null;
                  scannerPaused = false;
                  isProcessing = false;
                  manualCode = "";
                }
              },
              1500
            );
          }
          return;
        }
        feedbackStatus = {
          type: "error",
          message: data.scan_result?.message ?? "Locator code could not be resolved."
        };
      } catch (err) {
        if (!open || currentSession !== modalSession) return;
        feedbackStatus = {
          type: "error",
          message: err.response?.data?.message ?? err.message ?? "Failed to verify QR code."
        };
      } finally {
        if (!open || currentSession !== modalSession) return;
        if (feedbackStatus?.type === "error") {
          feedbackTimer = setTimeout(
            () => {
              if (open && currentSession === modalSession) {
                scannerPaused = false;
                isProcessing = false;
              }
            },
            2e3
          );
        }
      }
    }
    let $$settled = true;
    let $$inner_renderer;
    function $$render_inner($$renderer3) {
      Dialog($$renderer3, {
        open,
        onOpenChange,
        children: ($$renderer4) => {
          Dialog_content($$renderer4, {
            class: "flex max-h-[92dvh] flex-col p-0 overflow-hidden sm:max-w-lg border border-border/80 shadow-2xl rounded-2xl",
            children: ($$renderer5) => {
              $$renderer5.push(`<div class="border-b border-border/60 bg-muted/20 px-5 pt-5 pb-3"><div class="pr-8">`);
              Dialog_title($$renderer5, {
                class: "text-base font-bold tracking-tight text-foreground flex items-center gap-2",
                children: ($$renderer6) => {
                  Camera($$renderer6, { class: "size-4 text-primary shrink-0" });
                  $$renderer6.push(`<!----> <span>${escape_html(title)}</span>`);
                },
                $$slots: { default: true }
              });
              $$renderer5.push(`<!----> `);
              Dialog_description($$renderer5, {
                class: "text-xs text-muted-foreground mt-0.5",
                children: ($$renderer6) => {
                  $$renderer6.push(`<!---->${escape_html(description)}`);
                },
                $$slots: { default: true }
              });
              $$renderer5.push(`<!----></div> <div class="mt-3.5 grid grid-cols-2 gap-1 rounded-xl bg-muted/70 p-1 border border-border/50 text-xs font-semibold select-none" role="tablist" aria-label="Input mode"><button type="button" role="tab"${attr("aria-selected", inputMode === "camera")}${attr_class(`flex items-center justify-center gap-2 py-1.5 rounded-lg transition-all duration-200 ${"bg-background text-foreground shadow-xs font-bold"}`)}>`);
              Camera($$renderer5, { class: "size-3.5" });
              $$renderer5.push(`<!----> <span>Camera Scanner</span></button> <button type="button" role="tab"${attr("aria-selected", inputMode === "manual")}${attr_class(`flex items-center justify-center gap-2 py-1.5 rounded-lg transition-all duration-200 ${"text-muted-foreground hover:text-foreground"}`)}>`);
              Search($$renderer5, { class: "size-3.5" });
              $$renderer5.push(`<!----> <span>Manual Code</span></button></div></div> <div class="relative flex-1 overflow-y-auto px-5 py-4 min-h-[380px] max-h-[520px]">`);
              {
                $$renderer5.push("<!--[0-->");
                $$renderer5.push(`<div class="flex flex-col items-center space-y-3.5 w-full"><div class="relative w-full max-w-[440px] mx-auto">`);
                QrScanner($$renderer5, {
                  active: open && inputMode === "camera",
                  paused: scannerPaused,
                  onScan: handleCode,
                  class: "w-full shadow-inner"
                });
                $$renderer5.push(`<!----> `);
                if (isProcessing) {
                  $$renderer5.push("<!--[0-->");
                  $$renderer5.push(`<div class="absolute inset-0 rounded-xl bg-background/85 backdrop-blur-xs flex flex-col items-center justify-center gap-2 z-30">`);
                  Refresh_cw($$renderer5, { class: "size-6 animate-spin text-primary" });
                  $$renderer5.push(`<!----> <span class="text-xs font-semibold text-foreground">Verifying code…</span></div>`);
                } else {
                  $$renderer5.push("<!--[-1-->");
                }
                $$renderer5.push(`<!--]--></div> `);
                if (feedbackStatus) {
                  $$renderer5.push("<!--[0-->");
                  $$renderer5.push(`<div${attr_class(`w-full max-w-[440px] mx-auto rounded-lg border px-3 py-2 text-xs flex items-center gap-2 ${feedbackStatus.type === "success" ? "border-emerald-500/30 bg-emerald-500/10 text-emerald-700 dark:text-emerald-400" : "border-destructive/30 bg-destructive/10 text-destructive"}`)}>`);
                  if (feedbackStatus.type === "success") {
                    $$renderer5.push("<!--[0-->");
                    Circle_check($$renderer5, { class: "size-4 shrink-0 text-emerald-600" });
                  } else {
                    $$renderer5.push("<!--[-1-->");
                    Circle_alert($$renderer5, { class: "size-4 shrink-0 text-destructive" });
                  }
                  $$renderer5.push(`<!--]--> <span class="font-medium flex-1">${escape_html(feedbackStatus.message)}</span></div>`);
                } else {
                  $$renderer5.push("<!--[-1-->");
                }
                $$renderer5.push(`<!--]--> <div class="w-full max-w-[440px] mx-auto rounded-xl border border-border/60 bg-muted/20 p-3 space-y-1.5"><div class="text-[11px] font-bold uppercase tracking-wider text-muted-foreground text-center sm:text-left">Can't scan QR? Enter Locator Code</div> <div class="flex gap-2">`);
                Input($$renderer5, {
                  placeholder: manualPlaceholder,
                  class: "h-9 text-xs font-mono uppercase",
                  disabled: isProcessing,
                  onkeydown: (e) => {
                    if (e.key === "Enter") handleCode(manualCode);
                  },
                  get value() {
                    return manualCode;
                  },
                  set value($$value) {
                    manualCode = $$value;
                    $$settled = false;
                  }
                });
                $$renderer5.push(`<!----> `);
                Button($$renderer5, {
                  size: "sm",
                  class: "h-9 px-3 text-xs font-semibold shrink-0 gap-1",
                  disabled: !manualCode.trim() || isProcessing,
                  onclick: () => handleCode(manualCode),
                  children: ($$renderer6) => {
                    if (isProcessing) {
                      $$renderer6.push("<!--[0-->");
                      Refresh_cw($$renderer6, { class: "size-3 animate-spin" });
                    } else {
                      $$renderer6.push("<!--[-1-->");
                      $$renderer6.push(`Verify `);
                      Arrow_right($$renderer6, { class: "size-3" });
                      $$renderer6.push(`<!---->`);
                    }
                    $$renderer6.push(`<!--]-->`);
                  },
                  $$slots: { default: true }
                });
                $$renderer5.push(`<!----></div></div></div>`);
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
function ApplicantScan($$renderer, $$props) {
  $$renderer.component(($$renderer2) => {
    let {
      page_state = "ready",
      scan_result = null,
      scanned_attempt = null,
      destinations = []
    } = $$props;
    let modalOpen = false;
    let qrScannerOpen = false;
    const resolveUrl = typeof route === "function" ? route("staff.scan.resolve") : "/staff/scan/resolve";
    const form = useForm({ code: "" });
    function resolveCode() {
      form.post(resolveUrl, { onSuccess: () => form.reset("code") });
    }
    function startCamera() {
      qrScannerOpen = true;
    }
    const resultVariant = {
      invalid: "danger",
      no_destination: "warning",
      resolved: "muted"
    };
    let $$settled = true;
    let $$inner_renderer;
    function $$render_inner($$renderer3) {
      head("z5wtcr", $$renderer3, ($$renderer4) => {
        $$renderer4.title(($$renderer5) => {
          $$renderer5.push(`<title>Scan Applicant QR</title>`);
        });
      });
      StaffLayout($$renderer3, {
        breadcrumbs: [{ label: "Scan Applicant QR" }],
        children: ($$renderer4) => {
          $$renderer4.push(`<div class="min-w-0 space-y-6 p-4 sm:p-6">`);
          if (page_state !== "ready") {
            $$renderer4.push("<!--[0-->");
            E2PageState($$renderer4, { state: page_state });
          } else {
            $$renderer4.push("<!--[-1-->");
            PageHeaderBar($$renderer4, {
              title: "Scan Applicant QR",
              description: "Scan an applicant or examinee QR code to quickly look up their profile, exam schedule, and admission status."
            });
            $$renderer4.push(`<!----> `);
            if (Card) {
              $$renderer4.push("<!--[-->");
              Card($$renderer4, {
                children: ($$renderer5) => {
                  if (Card_content) {
                    $$renderer5.push("<!--[-->");
                    Card_content($$renderer5, {
                      class: "space-y-4 p-4",
                      children: ($$renderer6) => {
                        $$renderer6.push(`<div class="flex flex-col gap-3 sm:flex-row"><div class="flex-1">`);
                        Input($$renderer6, {
                          placeholder: "Applicant locator code",
                          onkeydown: (event) => {
                            if (event.key === "Enter") resolveCode();
                          },
                          get value() {
                            return form.code;
                          },
                          set value($$value) {
                            form.code = $$value;
                            $$settled = false;
                          }
                        });
                        $$renderer6.push(`<!----></div> `);
                        Button($$renderer6, {
                          type: "button",
                          class: "min-h-10",
                          onclick: resolveCode,
                          disabled: form.processing || !form.code.trim(),
                          children: ($$renderer7) => {
                            Qr_code($$renderer7, { class: "size-4" });
                            $$renderer7.push(`<!----> Resolve`);
                          },
                          $$slots: { default: true }
                        });
                        $$renderer6.push(`<!----> `);
                        Button($$renderer6, {
                          type: "button",
                          variant: "outline",
                          class: "min-h-10",
                          onclick: startCamera,
                          children: ($$renderer7) => {
                            Camera($$renderer7, { class: "size-4" });
                            $$renderer7.push(`<!----> Start camera`);
                          },
                          $$slots: { default: true }
                        });
                        $$renderer6.push(`<!----></div> <p class="text-xs text-muted-foreground">Camera capture is available on device browsers.</p> `);
                        if (form.errors.code) {
                          $$renderer6.push("<!--[0-->");
                          $$renderer6.push(`<p class="text-sm text-destructive">${escape_html(form.errors.code)}</p>`);
                        } else {
                          $$renderer6.push("<!--[-1-->");
                        }
                        $$renderer6.push(`<!--]-->`);
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
            $$renderer4.push(` `);
            if (scan_result) {
              $$renderer4.push("<!--[0-->");
              if (Card) {
                $$renderer4.push("<!--[-->");
                Card($$renderer4, {
                  children: ($$renderer5) => {
                    if (Card_content) {
                      $$renderer5.push("<!--[-->");
                      Card_content($$renderer5, {
                        class: "space-y-2 p-4",
                        children: ($$renderer6) => {
                          $$renderer6.push(`<div class="flex items-center gap-2">`);
                          Badge($$renderer6, {
                            variant: resultVariant[scan_result.state] ?? "muted",
                            children: ($$renderer7) => {
                              $$renderer7.push(`<!---->${escape_html(scan_result.state)}`);
                            },
                            $$slots: { default: true }
                          });
                          $$renderer6.push(`<!----> `);
                          if (scan_result.message) {
                            $$renderer6.push("<!--[0-->");
                            $$renderer6.push(`<p class="text-sm text-muted-foreground">${escape_html(scan_result.message)}</p>`);
                          } else {
                            $$renderer6.push("<!--[-1-->");
                          }
                          $$renderer6.push(`<!--]--></div>`);
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
            } else {
              $$renderer4.push("<!--[-1-->");
            }
            $$renderer4.push(`<!--]--> `);
            Dialog($$renderer4, {
              open: modalOpen,
              onOpenChange: (v) => modalOpen = v,
              children: ($$renderer5) => {
                Dialog_content($$renderer5, {
                  class: "sm:max-w-md",
                  children: ($$renderer6) => {
                    Dialog_header($$renderer6, {
                      children: ($$renderer7) => {
                        Dialog_title($$renderer7, {
                          children: ($$renderer8) => {
                            $$renderer8.push(`<!---->What would you like to do?`);
                          },
                          $$slots: { default: true }
                        });
                        $$renderer7.push(`<!----> `);
                        Dialog_description($$renderer7, {
                          children: ($$renderer8) => {
                            $$renderer8.push(`<!---->${escape_html(scanned_attempt?.reference_code)} · resolved. Choose an action — nothing runs automatically.`);
                          },
                          $$slots: { default: true }
                        });
                        $$renderer7.push(`<!---->`);
                      },
                      $$slots: { default: true }
                    });
                    $$renderer6.push(`<!----> <div class="space-y-2"><!--[-->`);
                    const each_array = ensure_array_like(destinations);
                    for (let $$index = 0, $$length = each_array.length; $$index < $$length; $$index++) {
                      let d = each_array[$$index];
                      Link($$renderer6, {
                        href: d.url,
                        class: "flex items-center justify-between gap-3 rounded-md border p-3 text-sm font-medium hover:bg-muted/60",
                        children: ($$renderer7) => {
                          $$renderer7.push(`<span>${escape_html(d.label)}</span> `);
                          Arrow_right($$renderer7, { class: "size-4 text-muted-foreground" });
                          $$renderer7.push(`<!---->`);
                        },
                        $$slots: { default: true }
                      });
                    }
                    $$renderer6.push(`<!--]--></div> `);
                    Dialog_footer($$renderer6, {
                      children: ($$renderer7) => {
                        Button($$renderer7, {
                          variant: "outline",
                          onclick: () => modalOpen = false,
                          children: ($$renderer8) => {
                            $$renderer8.push(`<!---->Cancel`);
                          },
                          $$slots: { default: true }
                        });
                      },
                      $$slots: { default: true }
                    });
                    $$renderer6.push(`<!---->`);
                  },
                  $$slots: { default: true }
                });
              },
              $$slots: { default: true }
            });
            $$renderer4.push(`<!----> `);
            if (Card) {
              $$renderer4.push("<!--[-->");
              Card($$renderer4, {
                children: ($$renderer5) => {
                  if (Card_content) {
                    $$renderer5.push("<!--[-->");
                    Card_content($$renderer5, {
                      class: "space-y-3 p-4",
                      children: ($$renderer6) => {
                        $$renderer6.push(`<div class="flex items-center gap-2">`);
                        Shield_check($$renderer6, { class: "size-4 text-muted-foreground" });
                        $$renderer6.push(`<!----> <p class="font-medium">What a scan reveals</p></div> <p class="text-sm text-muted-foreground">The payload contains no name, scores, action, or destination. Resolving a
            code verifies identity only — copying it grants nothing.</p>`);
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
          }
          $$renderer4.push(`<!--]--></div> `);
          UniversalQrModal($$renderer4, {
            open: qrScannerOpen,
            onOpenChange: (v) => qrScannerOpen = v,
            mode: "callback",
            title: "Scan Applicant Pass QR",
            description: "Align the applicant's digital QR or printed pass within the camera viewfinder.",
            onScan: (scannedCode, { close }) => {
              close();
              form.code = scannedCode;
              resolveCode();
            }
          });
          $$renderer4.push(`<!---->`);
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
  ApplicantScan as default
};
