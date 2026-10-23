import { h as head, e as escape_html, B as Button, a as ensure_array_like, d as derived, n as newIdempotencyKey } from "../app.js";
import ApplicantLayout from "./ApplicantLayout-DKnOpLAK.js";
import { T as TourTriggerGlyph, X as XiamNpcDialog } from "./XiamNpcDialog-DoqCSHj9.js";
import E2PageState from "./E2PageState-yFavAmgl.js";
import { B as Badge } from "./badge-D9Vgs7xh.js";
import { C as Card, a as Card_content } from "./card-content-Brg2wRkV.js";
import { C as Card_description } from "./card-description-BGy_v5-d.js";
import { C as Card_header, a as Card_title } from "./card-title-BNY2ZPje.js";
import { D as Dialog, a as Dialog_content, b as Dialog_header, c as Dialog_title, d as Dialog_description } from "./dialog-description-D12L0QA9.js";
import { D as Dialog_footer } from "./dialog-footer-kNnwKT2h.js";
import { L as Label } from "./label-dGY6L9vm.js";
import { T as Textarea } from "./textarea-Bv3Na1wO.js";
import { u as useForm } from "./useForm.svelte-C0OkDFGu.js";
import { C as Clock_3 } from "./clock-3-CBFB3kOE.js";
import { C as Circle_check } from "./circle-check-C59ZS7Zv.js";
import { I as Info } from "./info-C3MCzlQW.js";
import "axios";
import "@inertiajs/core";
import "clsx";
import "laravel-precognition";
import "tailwind-merge";
import "tailwind-variants";
import "./ToastManager-C7oO_4Jn.js";
import "./Icon-Dal7PqKK.js";
import "./circle-x-BQrhluGu.js";
import "./triangle-alert-CUvuB-HL.js";
import "./x-CnxrMbMn.js";
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
import "./NotificationBell-Cgc59min.js";
import "./inbox-AmUVtgA2.js";
import "./mail-DBtkjaCt.js";
import "qrcode";
import "./Link-B7ks7rBK.js";
import "./qr-code-C5X8qcH6.js";
import "./megaphone-C8QUuT5s.js";
import "./chevron-right-BHu-PGOg.js";
import "./file-text-PXEtmXm9.js";
import "./printer-Bx0z1CRb.js";
import "./shield-DTtuUihb.js";
import "./circle-question-mark-DY-RU614.js";
import "./sparkles-DbQIaVG6.js";
import "./graduation-cap-Bc9amiZ0.js";
import "./user-check-Dl-wciTR.js";
import "./calendar-clock-D72LURiz.js";
/* empty css                                                        */
import "./chevron-left-DCSOZwZl.js";
import "./shield-alert-CXtDdte9.js";
import "es-toolkit";
import "./clone-BJz-lxsY.js";
import "es-toolkit/compat";
function ProgramChoice($$renderer, $$props) {
  $$renderer.component(($$renderer2) => {
    let { page_state = "ready", support = null } = $$props;
    const readiness = derived(() => support?.readiness ?? {});
    const pool = derived(() => support?.program_pool ?? {});
    const analysis = derived(() => support?.analysis ?? null);
    const selectedCode = derived(() => pool().selection_program ?? readiness().selection?.program_code ?? null);
    const selectionExists = derived(() => ["pending_acknowledgement", "active"].includes(readiness().selection?.status));
    const options = derived(() => (pool().options ?? []).filter((option) => option.program_code !== selectedCode()));
    const canStillChoose = derived(() => !readiness().enrolling && !readiness().enrolled);
    const acknowledgeUrl = typeof route === "function" ? route("applicant.program-choice.acknowledge") : "/applicant/program-choice/acknowledge";
    typeof route === "function" ? route("applicant.program-choice.select") : "/applicant/program-choice/select";
    typeof route === "function" ? route("applicant.program-choice.transfer") : "/applicant/program-choice/transfer";
    const cancelUrl = typeof route === "function" ? route("applicant.program-choice.cancel") : "/applicant/program-choice/cancel";
    const acknowledgeForm = useForm({ idempotency_key: "" });
    const selectForm = useForm({ program_code: "", idempotency_key: "" });
    const cancelForm = useForm({ reason: "", idempotency_key: "" });
    let confirmOpen = false;
    let pendingCode = null;
    let cancelOpen = false;
    let tourOpen = false;
    const choiceContext = derived(() => {
      const isEnrolled = Boolean(readiness().enrolled || readiness().enrolling);
      const isRel = Boolean(readiness().result_released);
      const isAdv = Boolean(readiness().advised);
      const isAck = Boolean(readiness().selection?.acknowledged);
      const code = selectedCode();
      const safetyKey = `${page_state}:${isRel ? "rel" : "unrel"}:${isAdv ? "adv" : "unadv"}:${code ?? "none"}:${isAck ? "ack" : "unack"}:${isEnrolled ? "locked" : "open"}`;
      return {
        entityKey: code ?? "program-choice",
        safetyKey,
        freshness: "current",
        pageState: page_state,
        isResultReleased: isRel,
        isAdvised: isAdv,
        selectedProgramCode: code,
        isAcknowledged: isAck,
        selectionSource: readiness().selection?.source ?? "none",
        isEnrollingOrEnrolled: isEnrolled,
        hasDefaultProgram: Boolean(pool().default),
        defaultProgramName: pool().default?.program_name ?? null,
        alternativeOptionsCount: options().length,
        canStillChoose: canStillChoose()
      };
    });
    const pendingProgram = derived(() => (pool().options ?? []).find((option) => option.program_code === pendingCode) ?? null);
    const confirmTitle = derived(() => selectionExists() ? "Transfer program choice" : "Choose program");
    const confirmDescription = derived(() => pendingProgram() ? selectionExists() ? `This replaces your current selection with ${pendingProgram().program_name} (${pendingProgram().program_code}).` : `Record ${pendingProgram().program_name} (${pendingProgram().program_code}) as your program choice.` : "Confirm your program choice.");
    function acceptDefault() {
      acknowledgeForm.idempotency_key = newIdempotencyKey();
      acknowledgeForm.post(acknowledgeUrl, { preserveScroll: true });
    }
    function confirmSelect() {
      return;
    }
    function confirmCancel() {
      cancelForm.idempotency_key = newIdempotencyKey();
      cancelForm.post(cancelUrl, {
        preserveScroll: true,
        onFinish: () => {
          cancelOpen = false;
          cancelForm.reason = "";
        }
      });
    }
    let $$settled = true;
    let $$inner_renderer;
    function $$render_inner($$renderer3) {
      head("ltkby7", $$renderer3, ($$renderer4) => {
        $$renderer4.title(($$renderer5) => {
          $$renderer5.push(`<title>Program choice</title>`);
        });
      });
      ApplicantLayout($$renderer3, {
        title: "Program choice",
        children: ($$renderer4) => {
          $$renderer4.push(`<div class="mx-auto max-w-2xl space-y-5">`);
          if (page_state !== "ready") {
            $$renderer4.push("<!--[0-->");
            $$renderer4.push(`<div class="sr-only">`);
            E2PageState($$renderer4, { state: page_state });
            $$renderer4.push(`<!----></div> `);
            if (Card) {
              $$renderer4.push("<!--[-->");
              Card($$renderer4, {
                children: ($$renderer5) => {
                  if (Card_content) {
                    $$renderer5.push("<!--[-->");
                    Card_content($$renderer5, {
                      class: "space-y-3 p-6 text-center",
                      children: ($$renderer6) => {
                        Clock_3($$renderer6, { class: "mx-auto size-8 text-muted-foreground" });
                        $$renderer6.push(`<!----> <p class="font-medium">Program choice unavailable</p> <p class="text-sm text-muted-foreground">${escape_html(page_state === "denied" ? "This account cannot access program choice." : "Program choice is not ready yet.")}</p>`);
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
            $$renderer4.push(`<div class="space-y-1"><div class="flex flex-wrap items-center gap-2"><h1 class="text-2xl font-semibold tracking-tight">Program choice</h1> `);
            TourTriggerGlyph($$renderer4, {
              tourKey: "applicant-program-choice",
              label: "Need help choosing a program? Ask Kuya Xiam!",
              onclick: () => tourOpen = true
            });
            $$renderer4.push(`<!----></div> <p class="text-sm text-muted-foreground">Pick or confirm your program after your result is released.</p></div> `);
            if (Card) {
              $$renderer4.push("<!--[-->");
              Card($$renderer4, {
                class: "overflow-hidden shadow-sm",
                "data-tour": "program-choice-status",
                children: ($$renderer5) => {
                  if (Card_header) {
                    $$renderer5.push("<!--[-->");
                    Card_header($$renderer5, {
                      class: "border-b bg-muted/20 pb-3",
                      children: ($$renderer6) => {
                        if (Card_title) {
                          $$renderer6.push("<!--[-->");
                          Card_title($$renderer6, {
                            class: "text-lg",
                            children: ($$renderer7) => {
                              $$renderer7.push(`<!---->Your admission status`);
                            },
                            $$slots: { default: true }
                          });
                          $$renderer6.push("<!--]-->");
                        } else {
                          $$renderer6.push("<!--[!-->");
                          $$renderer6.push("<!--]-->");
                        }
                        $$renderer6.push(` `);
                        if (Card_description) {
                          $$renderer6.push("<!--[-->");
                          Card_description($$renderer6, {
                            children: ($$renderer7) => {
                              $$renderer7.push(`<!---->Result, advice, selection, and enrollment facts`);
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
                  if (Card_content) {
                    $$renderer5.push("<!--[-->");
                    Card_content($$renderer5, {
                      class: "pt-5",
                      children: ($$renderer6) => {
                        $$renderer6.push(`<dl class="grid gap-4 sm:grid-cols-2"><div class="space-y-1"><dt class="text-xs font-medium uppercase tracking-wide text-muted-foreground">Result</dt> <dd>`);
                        Badge($$renderer6, {
                          variant: readiness().result_released ? "default" : "secondary",
                          children: ($$renderer7) => {
                            $$renderer7.push(`<!---->${escape_html(readiness().result_released ? "Released" : "Pending")}`);
                          },
                          $$slots: { default: true }
                        });
                        $$renderer6.push(`<!----></dd></div> <div class="space-y-1"><dt class="text-xs font-medium uppercase tracking-wide text-muted-foreground">Advised</dt> <dd>`);
                        Badge($$renderer6, {
                          variant: readiness().advised ? "default" : "secondary",
                          children: ($$renderer7) => {
                            $$renderer7.push(`<!---->${escape_html(readiness().advised ? "Advised" : "Not advised")}`);
                          },
                          $$slots: { default: true }
                        });
                        $$renderer6.push(`<!----></dd></div> <div class="space-y-1"><dt class="text-xs font-medium uppercase tracking-wide text-muted-foreground">Program selection</dt> <dd class="space-y-1">`);
                        if (selectedCode()) {
                          $$renderer6.push("<!--[0-->");
                          Badge($$renderer6, {
                            children: ($$renderer7) => {
                              $$renderer7.push(`<!---->${escape_html(selectedCode())}`);
                            },
                            $$slots: { default: true }
                          });
                          $$renderer6.push(`<!----> `);
                          if (readiness().selection?.acknowledged || readiness().selection?.source) {
                            $$renderer6.push("<!--[0-->");
                            $$renderer6.push(`<p class="text-xs text-muted-foreground">`);
                            if (readiness().selection?.acknowledged) {
                              $$renderer6.push("<!--[0-->");
                              $$renderer6.push(`Acknowledged by you`);
                            } else if (readiness().selection?.source === "guidance_default") {
                              $$renderer6.push("<!--[1-->");
                              $$renderer6.push(`Guidance default · projection active · not acknowledged`);
                            } else {
                              $$renderer6.push("<!--[-1-->");
                              $$renderer6.push(`Awaiting acknowledgement`);
                            }
                            $$renderer6.push(`<!--]--> `);
                            if (readiness().selection?.source === "student_self") {
                              $$renderer6.push("<!--[0-->");
                              $$renderer6.push(`· your choice`);
                            } else {
                              $$renderer6.push("<!--[-1-->");
                            }
                            $$renderer6.push(`<!--]--></p>`);
                          } else {
                            $$renderer6.push("<!--[-1-->");
                          }
                          $$renderer6.push(`<!--]-->`);
                        } else {
                          $$renderer6.push("<!--[-1-->");
                          Badge($$renderer6, {
                            variant: "secondary",
                            children: ($$renderer7) => {
                              $$renderer7.push(`<!---->None yet`);
                            },
                            $$slots: { default: true }
                          });
                        }
                        $$renderer6.push(`<!--]--></dd></div> <div class="space-y-1"><dt class="text-xs font-medium uppercase tracking-wide text-muted-foreground">Enrollment</dt> <dd>`);
                        Badge($$renderer6, {
                          variant: readiness().enrolled || readiness().enrolling ? "default" : "secondary",
                          children: ($$renderer7) => {
                            $$renderer7.push(`<!---->${escape_html(readiness().enrolled ? "Enrolled" : readiness().enrolling ? "Enrolling" : "Not started")}`);
                          },
                          $$slots: { default: true }
                        });
                        $$renderer6.push(`<!----></dd></div></dl>`);
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
            if (pool().default && (pool().selection_status === "pending_acknowledgement" || pool().selection_status === "active" && readiness().selection?.source === "guidance_default" && !readiness().selection?.acknowledged)) {
              $$renderer4.push("<!--[0-->");
              if (Card) {
                $$renderer4.push("<!--[-->");
                Card($$renderer4, {
                  class: "overflow-hidden border-emerald-600/30 shadow-sm",
                  "data-tour": "program-choice-default",
                  children: ($$renderer5) => {
                    if (Card_header) {
                      $$renderer5.push("<!--[-->");
                      Card_header($$renderer5, {
                        class: "border-b bg-emerald-600/5 pb-3",
                        children: ($$renderer6) => {
                          $$renderer6.push(`<div class="flex items-center justify-between gap-3"><div>`);
                          if (Card_title) {
                            $$renderer6.push("<!--[-->");
                            Card_title($$renderer6, {
                              class: "text-lg",
                              children: ($$renderer7) => {
                                $$renderer7.push(`<!---->Guidance default`);
                              },
                              $$slots: { default: true }
                            });
                            $$renderer6.push("<!--]-->");
                          } else {
                            $$renderer6.push("<!--[!-->");
                            $$renderer6.push("<!--]-->");
                          }
                          $$renderer6.push(` `);
                          if (Card_description) {
                            $$renderer6.push("<!--[-->");
                            Card_description($$renderer6, {
                              children: ($$renderer7) => {
                                if (!canStillChoose()) {
                                  $$renderer7.push("<!--[0-->");
                                  $$renderer7.push(`Your program choice and acknowledgement are locked once enrollment starts.`);
                                } else if (pool().selection_status === "active") {
                                  $$renderer7.push("<!--[1-->");
                                  $$renderer7.push(`Your department has cleared this program; confirm it is your choice.`);
                                } else {
                                  $$renderer7.push("<!--[-1-->");
                                  $$renderer7.push(`Your projection is already active — no action needed.`);
                                }
                                $$renderer7.push(`<!--]-->`);
                              },
                              $$slots: { default: true }
                            });
                            $$renderer6.push("<!--]-->");
                          } else {
                            $$renderer6.push("<!--[!-->");
                            $$renderer6.push("<!--]-->");
                          }
                          $$renderer6.push(`</div> `);
                          Badge($$renderer6, {
                            variant: "outline",
                            class: "shrink-0 border-emerald-600/40 text-emerald-700 dark:text-emerald-400",
                            children: ($$renderer7) => {
                              $$renderer7.push(`<!---->✦ Auto`);
                            },
                            $$slots: { default: true }
                          });
                          $$renderer6.push(`<!----></div>`);
                        },
                        $$slots: { default: true }
                      });
                      $$renderer5.push("<!--]-->");
                    } else {
                      $$renderer5.push("<!--[!-->");
                      $$renderer5.push("<!--]-->");
                    }
                    $$renderer5.push(` `);
                    if (Card_content) {
                      $$renderer5.push("<!--[-->");
                      Card_content($$renderer5, {
                        class: "space-y-3 pt-4",
                        children: ($$renderer6) => {
                          $$renderer6.push(`<div class="flex items-start gap-3">`);
                          Circle_check($$renderer6, {
                            class: "mt-0.5 size-5 shrink-0 text-emerald-600 dark:text-emerald-400"
                          });
                          $$renderer6.push(`<!----> <div class="space-y-0.5"><p class="font-medium">${escape_html(pool().default)}</p> <p class="text-sm text-muted-foreground">`);
                          if (!canStillChoose()) {
                            $$renderer6.push("<!--[0-->");
                            $$renderer6.push(`Enrollment has started, so no program choice or acknowledgement changes can be made here.
                    The Registrar’s paper confirmation determines your final program.`);
                          } else if (pool().selection_status === "active") {
                            $$renderer6.push("<!--[1-->");
                            $$renderer6.push(`Your department has cleared this program. Confirming marks it as your explicit current
                    selection.`);
                          } else {
                            $$renderer6.push("<!--[-1-->");
                            $$renderer6.push(`Set by Guidance after consultation. Quota is reserved and your projection is visible to
                    the admission team. Acknowledgement is optional — it marks this as your explicit current
                    selection.`);
                          }
                          $$renderer6.push(`<!--]--></p></div></div> `);
                          if (canStillChoose()) {
                            $$renderer6.push("<!--[0-->");
                            if (acknowledgeForm.errors.selection) {
                              $$renderer6.push("<!--[0-->");
                              $$renderer6.push(`<p class="text-sm text-destructive">${escape_html(acknowledgeForm.errors.selection)}</p>`);
                            } else {
                              $$renderer6.push("<!--[-1-->");
                            }
                            $$renderer6.push(`<!--]--> `);
                            Button($$renderer6, {
                              "data-tour": "program-choice-acknowledge",
                              variant: "outline",
                              class: "min-h-11",
                              disabled: acknowledgeForm.processing,
                              onclick: acceptDefault,
                              children: ($$renderer7) => {
                                $$renderer7.push(`<!---->${escape_html(acknowledgeForm.processing ? "Confirming…" : "This is my choice")}`);
                              },
                              $$slots: { default: true }
                            });
                            $$renderer6.push(`<!---->`);
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
            } else {
              $$renderer4.push("<!--[-1-->");
            }
            $$renderer4.push(`<!--]--> `);
            if (canStillChoose()) {
              $$renderer4.push("<!--[0-->");
              if (Card) {
                $$renderer4.push("<!--[-->");
                Card($$renderer4, {
                  class: "overflow-hidden shadow-sm",
                  "data-tour": "program-choice-options",
                  children: ($$renderer5) => {
                    if (Card_header) {
                      $$renderer5.push("<!--[-->");
                      Card_header($$renderer5, {
                        class: "border-b bg-muted/20 pb-3",
                        children: ($$renderer6) => {
                          if (Card_title) {
                            $$renderer6.push("<!--[-->");
                            Card_title($$renderer6, {
                              class: "text-lg",
                              children: ($$renderer7) => {
                                $$renderer7.push(`<!---->Choose another program`);
                              },
                              $$slots: { default: true }
                            });
                            $$renderer6.push("<!--]-->");
                          } else {
                            $$renderer6.push("<!--[!-->");
                            $$renderer6.push("<!--]-->");
                          }
                          $$renderer6.push(` `);
                          if (Card_description) {
                            $$renderer6.push("<!--[-->");
                            Card_description($$renderer6, {
                              children: ($$renderer7) => {
                                if (selectionExists()) {
                                  $$renderer7.push("<!--[0-->");
                                  $$renderer7.push(`You already have a selection — picking an option here transfers it.`);
                                } else {
                                  $$renderer7.push("<!--[-1-->");
                                  $$renderer7.push(`Pick any open option; your choice is recorded once confirmed.`);
                                }
                                $$renderer7.push(`<!--]-->`);
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
                    if (Card_content) {
                      $$renderer5.push("<!--[-->");
                      Card_content($$renderer5, {
                        class: "space-y-3 pt-4",
                        children: ($$renderer6) => {
                          if (options().length === 0) {
                            $$renderer6.push("<!--[0-->");
                            $$renderer6.push(`<p class="text-sm text-muted-foreground">No other programs are open for choice right now.</p>`);
                          } else {
                            $$renderer6.push("<!--[-1-->");
                            $$renderer6.push(`<ul class="space-y-2"><!--[-->`);
                            const each_array = ensure_array_like(options());
                            for (let $$index = 0, $$length = each_array.length; $$index < $$length; $$index++) {
                              let option = each_array[$$index];
                              $$renderer6.push(`<li><button type="button" class="flex w-full items-start gap-3 rounded-lg border border-border px-3 py-3 text-left text-sm transition hover:border-primary/50 hover:bg-muted/20"><span class="mt-0.5 flex size-4 shrink-0 items-center justify-center rounded-full border border-muted-foreground/50" aria-hidden="true"></span> <span class="min-w-0 flex-1 space-y-0.5"><span class="block font-medium">${escape_html(option.program_name)}</span> <span class="block font-mono text-xs text-muted-foreground">${escape_html(option.program_code)}</span> `);
                              if (option.basis) {
                                $$renderer6.push("<!--[0-->");
                                $$renderer6.push(`<span class="block text-xs text-muted-foreground">Basis: ${escape_html(option.basis)}</span>`);
                              } else {
                                $$renderer6.push("<!--[-1-->");
                              }
                              $$renderer6.push(`<!--]--></span></button></li>`);
                            }
                            $$renderer6.push(`<!--]--></ul>`);
                          }
                          $$renderer6.push(`<!--]--> `);
                          if (selectForm.errors.selection) {
                            $$renderer6.push("<!--[0-->");
                            $$renderer6.push(`<p class="text-sm text-destructive">${escape_html(selectForm.errors.selection)}</p>`);
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
              if (selectionExists()) {
                $$renderer4.push("<!--[0-->");
                if (Card) {
                  $$renderer4.push("<!--[-->");
                  Card($$renderer4, {
                    class: "border-destructive/30",
                    children: ($$renderer5) => {
                      if (Card_content) {
                        $$renderer5.push("<!--[-->");
                        Card_content($$renderer5, {
                          class: "flex flex-col gap-3 p-4 sm:flex-row sm:items-center sm:justify-between",
                          children: ($$renderer6) => {
                            $$renderer6.push(`<p class="text-sm text-muted-foreground">Changed your mind? Cancel your current selection before enrollment starts.</p> `);
                            Button($$renderer6, {
                              type: "button",
                              variant: "outline",
                              class: "min-h-11 border-destructive/50 text-destructive hover:bg-destructive/10",
                              onclick: () => cancelOpen = true,
                              children: ($$renderer7) => {
                                $$renderer7.push(`<!---->Cancel selection`);
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
              } else {
                $$renderer4.push("<!--[-1-->");
              }
              $$renderer4.push(`<!--]-->`);
            } else {
              $$renderer4.push("<!--[-1-->");
            }
            $$renderer4.push(`<!--]--> `);
            if (analysis()) {
              $$renderer4.push("<!--[0-->");
              if (Card) {
                $$renderer4.push("<!--[-->");
                Card($$renderer4, {
                  "data-tour": "program-choice-analysis",
                  children: ($$renderer5) => {
                    if (Card_header) {
                      $$renderer5.push("<!--[-->");
                      Card_header($$renderer5, {
                        class: "pb-3",
                        children: ($$renderer6) => {
                          if (Card_title) {
                            $$renderer6.push("<!--[-->");
                            Card_title($$renderer6, {
                              class: "text-lg",
                              children: ($$renderer7) => {
                                $$renderer7.push(`<!---->Analysis`);
                              },
                              $$slots: { default: true }
                            });
                            $$renderer6.push("<!--]-->");
                          } else {
                            $$renderer6.push("<!--[!-->");
                            $$renderer6.push("<!--]-->");
                          }
                          $$renderer6.push(` `);
                          if (Card_description) {
                            $$renderer6.push("<!--[-->");
                            Card_description($$renderer6, {
                              children: ($$renderer7) => {
                                $$renderer7.push(`<!---->Consultation evidence summary`);
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
                    if (Card_content) {
                      $$renderer5.push("<!--[-->");
                      Card_content($$renderer5, {
                        class: "space-y-2 pt-2",
                        children: ($$renderer6) => {
                          $$renderer6.push(`<p class="text-sm">${escape_html(analysis().message)}</p> `);
                          if (analysis().status === "result_not_ready") {
                            $$renderer6.push("<!--[0-->");
                            $$renderer6.push(`<p class="flex gap-2 text-xs text-muted-foreground">`);
                            Info($$renderer6, { class: "mt-0.5 size-3.5 shrink-0" });
                            $$renderer6.push(`<!----> Unlocks after grading is finalized and the result is released.</p>`);
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
            } else {
              $$renderer4.push("<!--[-1-->");
            }
            $$renderer4.push(`<!--]-->`);
          }
          $$renderer4.push(`<!--]--></div> `);
          if (Dialog) {
            $$renderer4.push("<!--[-->");
            Dialog($$renderer4, {
              get open() {
                return confirmOpen;
              },
              set open($$value) {
                confirmOpen = $$value;
                $$settled = false;
              },
              children: ($$renderer5) => {
                if (Dialog_content) {
                  $$renderer5.push("<!--[-->");
                  Dialog_content($$renderer5, {
                    class: "sm:max-w-md",
                    children: ($$renderer6) => {
                      if (Dialog_header) {
                        $$renderer6.push("<!--[-->");
                        Dialog_header($$renderer6, {
                          children: ($$renderer7) => {
                            if (Dialog_title) {
                              $$renderer7.push("<!--[-->");
                              Dialog_title($$renderer7, {
                                children: ($$renderer8) => {
                                  $$renderer8.push(`<!---->${escape_html(confirmTitle())}`);
                                },
                                $$slots: { default: true }
                              });
                              $$renderer7.push("<!--]-->");
                            } else {
                              $$renderer7.push("<!--[!-->");
                              $$renderer7.push("<!--]-->");
                            }
                            $$renderer7.push(` `);
                            if (Dialog_description) {
                              $$renderer7.push("<!--[-->");
                              Dialog_description($$renderer7, {
                                children: ($$renderer8) => {
                                  $$renderer8.push(`<!---->${escape_html(confirmDescription())}`);
                                },
                                $$slots: { default: true }
                              });
                              $$renderer7.push("<!--]-->");
                            } else {
                              $$renderer7.push("<!--[!-->");
                              $$renderer7.push("<!--]-->");
                            }
                          },
                          $$slots: { default: true }
                        });
                        $$renderer6.push("<!--]-->");
                      } else {
                        $$renderer6.push("<!--[!-->");
                        $$renderer6.push("<!--]-->");
                      }
                      $$renderer6.push(` `);
                      if (pendingProgram()) {
                        $$renderer6.push("<!--[0-->");
                        $$renderer6.push(`<dl class="space-y-1 text-sm"><div class="flex justify-between gap-4"><dt class="text-muted-foreground">Program</dt> <dd>${escape_html(pendingProgram().program_name)}</dd></div> <div class="flex justify-between gap-4"><dt class="text-muted-foreground">Code</dt> <dd class="font-mono">${escape_html(pendingProgram().program_code)}</dd></div> `);
                        if (pendingProgram().basis) {
                          $$renderer6.push("<!--[0-->");
                          $$renderer6.push(`<div class="flex justify-between gap-4"><dt class="text-muted-foreground">Basis</dt> <dd>${escape_html(pendingProgram().basis)}</dd></div>`);
                        } else {
                          $$renderer6.push("<!--[-1-->");
                        }
                        $$renderer6.push(`<!--]--></dl>`);
                      } else {
                        $$renderer6.push("<!--[-1-->");
                      }
                      $$renderer6.push(`<!--]--> `);
                      if (selectForm.errors.selection) {
                        $$renderer6.push("<!--[0-->");
                        $$renderer6.push(`<p class="text-sm text-destructive">${escape_html(selectForm.errors.selection)}</p>`);
                      } else {
                        $$renderer6.push("<!--[-1-->");
                      }
                      $$renderer6.push(`<!--]--> `);
                      if (Dialog_footer) {
                        $$renderer6.push("<!--[-->");
                        Dialog_footer($$renderer6, {
                          children: ($$renderer7) => {
                            Button($$renderer7, {
                              type: "button",
                              variant: "outline",
                              class: "min-h-11",
                              onclick: () => confirmOpen = false,
                              children: ($$renderer8) => {
                                $$renderer8.push(`<!---->Back`);
                              },
                              $$slots: { default: true }
                            });
                            $$renderer7.push(`<!----> `);
                            Button($$renderer7, {
                              type: "button",
                              class: "min-h-11",
                              disabled: selectForm.processing || !pendingCode,
                              onclick: confirmSelect,
                              children: ($$renderer8) => {
                                $$renderer8.push(`<!---->${escape_html(selectForm.processing ? "Confirming…" : selectionExists() ? "Transfer choice" : "Confirm choice")}`);
                              },
                              $$slots: { default: true }
                            });
                            $$renderer7.push(`<!---->`);
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
              },
              $$slots: { default: true }
            });
            $$renderer4.push("<!--]-->");
          } else {
            $$renderer4.push("<!--[!-->");
            $$renderer4.push("<!--]-->");
          }
          $$renderer4.push(` `);
          if (Dialog) {
            $$renderer4.push("<!--[-->");
            Dialog($$renderer4, {
              get open() {
                return cancelOpen;
              },
              set open($$value) {
                cancelOpen = $$value;
                $$settled = false;
              },
              children: ($$renderer5) => {
                if (Dialog_content) {
                  $$renderer5.push("<!--[-->");
                  Dialog_content($$renderer5, {
                    class: "sm:max-w-md",
                    children: ($$renderer6) => {
                      if (Dialog_header) {
                        $$renderer6.push("<!--[-->");
                        Dialog_header($$renderer6, {
                          children: ($$renderer7) => {
                            if (Dialog_title) {
                              $$renderer7.push("<!--[-->");
                              Dialog_title($$renderer7, {
                                children: ($$renderer8) => {
                                  $$renderer8.push(`<!---->Cancel program selection`);
                                },
                                $$slots: { default: true }
                              });
                              $$renderer7.push("<!--]-->");
                            } else {
                              $$renderer7.push("<!--[!-->");
                              $$renderer7.push("<!--]-->");
                            }
                            $$renderer7.push(` `);
                            if (Dialog_description) {
                              $$renderer7.push("<!--[-->");
                              Dialog_description($$renderer7, {
                                children: ($$renderer8) => {
                                  $$renderer8.push(`<!---->Tell us why so guidance can follow up. This cannot be undone.`);
                                },
                                $$slots: { default: true }
                              });
                              $$renderer7.push("<!--]-->");
                            } else {
                              $$renderer7.push("<!--[!-->");
                              $$renderer7.push("<!--]-->");
                            }
                          },
                          $$slots: { default: true }
                        });
                        $$renderer6.push("<!--]-->");
                      } else {
                        $$renderer6.push("<!--[!-->");
                        $$renderer6.push("<!--]-->");
                      }
                      $$renderer6.push(` <div class="space-y-2">`);
                      Label($$renderer6, {
                        for: "cancel-reason",
                        children: ($$renderer7) => {
                          $$renderer7.push(`<!---->Reason`);
                        },
                        $$slots: { default: true }
                      });
                      $$renderer6.push(`<!----> `);
                      Textarea($$renderer6, {
                        id: "cancel-reason",
                        placeholder: "Why are you cancelling your selection?",
                        maxlength: "500",
                        get value() {
                          return cancelForm.reason;
                        },
                        set value($$value) {
                          cancelForm.reason = $$value;
                          $$settled = false;
                        }
                      });
                      $$renderer6.push(`<!----></div> `);
                      if (cancelForm.errors.selection) {
                        $$renderer6.push("<!--[0-->");
                        $$renderer6.push(`<p class="text-sm text-destructive">${escape_html(cancelForm.errors.selection)}</p>`);
                      } else {
                        $$renderer6.push("<!--[-1-->");
                      }
                      $$renderer6.push(`<!--]--> `);
                      if (Dialog_footer) {
                        $$renderer6.push("<!--[-->");
                        Dialog_footer($$renderer6, {
                          children: ($$renderer7) => {
                            Button($$renderer7, {
                              type: "button",
                              variant: "outline",
                              class: "min-h-11",
                              onclick: () => cancelOpen = false,
                              children: ($$renderer8) => {
                                $$renderer8.push(`<!---->Back`);
                              },
                              $$slots: { default: true }
                            });
                            $$renderer7.push(`<!----> `);
                            Button($$renderer7, {
                              type: "button",
                              variant: "destructive",
                              class: "min-h-11",
                              disabled: cancelForm.processing || !cancelForm.reason.trim(),
                              onclick: confirmCancel,
                              children: ($$renderer8) => {
                                $$renderer8.push(`<!---->${escape_html(cancelForm.processing ? "Cancelling…" : "Cancel selection")}`);
                              },
                              $$slots: { default: true }
                            });
                            $$renderer7.push(`<!---->`);
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
              },
              $$slots: { default: true }
            });
            $$renderer4.push("<!--]-->");
          } else {
            $$renderer4.push("<!--[!-->");
            $$renderer4.push("<!--]-->");
          }
          $$renderer4.push(` `);
          XiamNpcDialog($$renderer4, {
            tourKey: "applicant-program-choice",
            context: choiceContext(),
            get open() {
              return tourOpen;
            },
            set open($$value) {
              tourOpen = $$value;
              $$settled = false;
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
  ProgramChoice as default
};
