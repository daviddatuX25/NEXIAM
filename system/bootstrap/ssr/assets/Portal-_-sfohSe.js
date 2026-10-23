import { g as sanitize_props, i as spread_props, j as slot, h as head, e as escape_html, B as Button, b as attr, a as ensure_array_like, c as attr_class, s as stringify, f as attr_style, d as derived } from "../app.js";
import ApplicantLayout from "./ApplicantLayout-DKnOpLAK.js";
import { T as TourTriggerGlyph, X as XiamNpcDialog } from "./XiamNpcDialog-DoqCSHj9.js";
import { B as Badge } from "./badge-D9Vgs7xh.js";
import { C as Card, a as Card_content } from "./card-content-Brg2wRkV.js";
import { C as Card_description } from "./card-description-BGy_v5-d.js";
import "clsx";
import { C as Card_header, a as Card_title } from "./card-title-BNY2ZPje.js";
import { L as Link } from "./Link-B7ks7rBK.js";
import { V as Video } from "./video-D4mycEAq.js";
import { U as User_round } from "./user-round-Kft0Cy_Y.js";
import { T as Triangle_alert } from "./triangle-alert-CUvuB-HL.js";
import { S as Shield_check } from "./shield-check-CLSg-ako.js";
import { C as Circle_check } from "./circle-check-C59ZS7Zv.js";
import { F as File_check } from "./file-check-BSLEMs1I.js";
import { S as Sparkles } from "./sparkles-DbQIaVG6.js";
import { C as Circle } from "./circle-DadbbVvn.js";
import { I as Icon } from "./Icon-Dal7PqKK.js";
import { Q as Qr_code } from "./qr-code-C5X8qcH6.js";
import { C as Clipboard_list } from "./clipboard-list-YgthURdB.js";
import "axios";
import "@inertiajs/core";
import "laravel-precognition";
import "tailwind-merge";
import "tailwind-variants";
import "./ToastManager-C7oO_4Jn.js";
import "./circle-x-BQrhluGu.js";
import "./info-C3MCzlQW.js";
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
import "./dialog-description-D12L0QA9.js";
import "./dialog-footer-kNnwKT2h.js";
import "qrcode";
import "./megaphone-C8QUuT5s.js";
import "./chevron-right-BHu-PGOg.js";
import "./file-text-PXEtmXm9.js";
import "./printer-Bx0z1CRb.js";
import "./shield-DTtuUihb.js";
import "./circle-question-mark-DY-RU614.js";
import "./graduation-cap-Bc9amiZ0.js";
import "./user-check-Dl-wciTR.js";
import "./calendar-clock-D72LURiz.js";
/* empty css                                                        */
import "./chevron-left-DCSOZwZl.js";
function Bell_ring($$renderer, $$props) {
  const $$sanitized_props = sanitize_props($$props);
  const iconNode = [
    ["path", { "d": "M10.268 21a2 2 0 0 0 3.464 0" }],
    ["path", { "d": "M22 8c0-2.3-.8-4.3-2-6" }],
    [
      "path",
      {
        "d": "M3.262 15.326A1 1 0 0 0 4 17h16a1 1 0 0 0 .74-1.673C19.41 13.956 18 12.499 18 8A6 6 0 0 0 6 8c0 4.499-1.411 5.956-2.738 7.326"
      }
    ],
    ["path", { "d": "M4 2C2.8 3.7 2 5.7 2 8" }]
  ];
  Icon($$renderer, spread_props([
    { name: "bell-ring" },
    $$sanitized_props,
    {
      /**
       * @component @name BellRing
       * @description Lucide SVG icon component, renders SVG Element with children.
       *
       * @preview ![img](data:image/svg+xml;base64,PHN2ZyAgeG1sbnM9Imh0dHA6Ly93d3cudzMub3JnLzIwMDAvc3ZnIgogIHdpZHRoPSIyNCIKICBoZWlnaHQ9IjI0IgogIHZpZXdCb3g9IjAgMCAyNCAyNCIKICBmaWxsPSJub25lIgogIHN0cm9rZT0iIzAwMCIgc3R5bGU9ImJhY2tncm91bmQtY29sb3I6ICNmZmY7IGJvcmRlci1yYWRpdXM6IDJweCIKICBzdHJva2Utd2lkdGg9IjIiCiAgc3Ryb2tlLWxpbmVjYXA9InJvdW5kIgogIHN0cm9rZS1saW5lam9pbj0icm91bmQiCj4KICA8cGF0aCBkPSJNMTAuMjY4IDIxYTIgMiAwIDAgMCAzLjQ2NCAwIiAvPgogIDxwYXRoIGQ9Ik0yMiA4YzAtMi4zLS44LTQuMy0yLTYiIC8+CiAgPHBhdGggZD0iTTMuMjYyIDE1LjMyNkExIDEgMCAwIDAgNCAxN2gxNmExIDEgMCAwIDAgLjc0LTEuNjczQzE5LjQxIDEzLjk1NiAxOCAxMi40OTkgMTggOEE2IDYgMCAwIDAgNiA4YzAgNC40OTktMS40MTEgNS45NTYtMi43MzggNy4zMjYiIC8+CiAgPHBhdGggZD0iTTQgMkMyLjggMy43IDIgNS43IDIgOCIgLz4KPC9zdmc+Cg==) - https://lucide.dev/icons/bell-ring
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
function Life_buoy($$renderer, $$props) {
  const $$sanitized_props = sanitize_props($$props);
  const iconNode = [
    ["circle", { "cx": "12", "cy": "12", "r": "10" }],
    ["path", { "d": "m4.93 4.93 4.24 4.24" }],
    ["path", { "d": "m14.83 9.17 4.24-4.24" }],
    ["path", { "d": "m14.83 14.83 4.24 4.24" }],
    ["path", { "d": "m9.17 14.83-4.24 4.24" }],
    ["circle", { "cx": "12", "cy": "12", "r": "4" }]
  ];
  Icon($$renderer, spread_props([
    { name: "life-buoy" },
    $$sanitized_props,
    {
      /**
       * @component @name LifeBuoy
       * @description Lucide SVG icon component, renders SVG Element with children.
       *
       * @preview ![img](data:image/svg+xml;base64,PHN2ZyAgeG1sbnM9Imh0dHA6Ly93d3cudzMub3JnLzIwMDAvc3ZnIgogIHdpZHRoPSIyNCIKICBoZWlnaHQ9IjI0IgogIHZpZXdCb3g9IjAgMCAyNCAyNCIKICBmaWxsPSJub25lIgogIHN0cm9rZT0iIzAwMCIgc3R5bGU9ImJhY2tncm91bmQtY29sb3I6ICNmZmY7IGJvcmRlci1yYWRpdXM6IDJweCIKICBzdHJva2Utd2lkdGg9IjIiCiAgc3Ryb2tlLWxpbmVjYXA9InJvdW5kIgogIHN0cm9rZS1saW5lam9pbj0icm91bmQiCj4KICA8Y2lyY2xlIGN4PSIxMiIgY3k9IjEyIiByPSIxMCIgLz4KICA8cGF0aCBkPSJtNC45MyA0LjkzIDQuMjQgNC4yNCIgLz4KICA8cGF0aCBkPSJtMTQuODMgOS4xNyA0LjI0LTQuMjQiIC8+CiAgPHBhdGggZD0ibTE0LjgzIDE0LjgzIDQuMjQgNC4yNCIgLz4KICA8cGF0aCBkPSJtOS4xNyAxNC44My00LjI0IDQuMjQiIC8+CiAgPGNpcmNsZSBjeD0iMTIiIGN5PSIxMiIgcj0iNCIgLz4KPC9zdmc+Cg==) - https://lucide.dev/icons/life-buoy
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
function Portal($$renderer, $$props) {
  $$renderer.component(($$renderer2) => {
    let {
      page_state = "ready",
      identity = null,
      stages = [],
      admission_clearance = null,
      notices = {},
      quick_links = [],
      active_online_encounter = null,
      applicable_online_exception = null,
      appointment_active = false
    } = $$props;
    let tourOpen = false;
    const currentStage = derived(() => (stages ?? []).find((s) => s.status === "current" || s.status === "action") ?? null);
    const actionStage = derived(() => (stages ?? []).find((s) => s.status === "action") ?? null);
    const completedCount = derived(() => (stages ?? []).filter((s) => s.status === "done").length);
    const hasQr = derived(() => (quick_links ?? []).some((l) => l.key === "qr"));
    const portalContext = derived(() => {
      const actNeeded = Boolean(actionStage());
      const curTitle = currentStage()?.title ?? null;
      const safetyKey = `${page_state}:${curTitle ?? "none"}:${actNeeded ? "action" : "ok"}:${completedCount()}:${hasQr() ? "qr" : "noqr"}`;
      return {
        entityKey: identity?.name ?? "applicant-portal",
        safetyKey,
        freshness: "current",
        pageState: page_state,
        applicantName: identity?.name ?? "Applicant",
        totalStagesCount: stages?.length ?? 0,
        currentStageTitle: curTitle,
        hasActionRequired: actNeeded,
        actionStageTitle: actionStage()?.title ?? null,
        hasQrLink: hasQr(),
        completedStagesCount: completedCount()
      };
    });
    const badgeFor = (status) => {
      switch (status) {
        case "done":
          return { label: "Done", variant: "outline" };
        case "current":
          return { label: "Now", variant: "default" };
        case "action":
          return { label: "Needs action", variant: "destructive" };
        case "auto":
          return { label: "Auto", variant: "secondary" };
        default:
          return { label: "Next", variant: "secondary" };
      }
    };
    const quickLinkIcon = (key) => {
      if (key === "notifications") return Bell_ring;
      if (key === "qr") return Qr_code;
      if (key === "admission_support") return Clipboard_list;
      return Life_buoy;
    };
    let $$settled = true;
    let $$inner_renderer;
    function $$render_inner($$renderer3) {
      head("glxyhe", $$renderer3, ($$renderer4) => {
        $$renderer4.title(($$renderer5) => {
          $$renderer5.push(`<title>Your status</title>`);
        });
      });
      ApplicantLayout($$renderer3, {
        title: "Your status",
        children: ($$renderer4) => {
          $$renderer4.push(`<div class="space-y-6"><div class="space-y-1"><div class="flex items-center gap-2"><h1 class="text-2xl font-semibold tracking-tight">Your status</h1> `);
          TourTriggerGlyph($$renderer4, {
            tourKey: "applicant-portal",
            label: "Need guidance? Kuya Xiam is here to help!",
            onclick: () => tourOpen = true
          });
          $$renderer4.push(`<!----></div> <p class="text-sm text-muted-foreground">Your application journey at a glance. Use Sign out when you finish on a shared device.</p></div> `);
          if (active_online_encounter?.id) {
            $$renderer4.push("<!--[0-->");
            const onlineEncounter = active_online_encounter;
            if (Card) {
              $$renderer4.push("<!--[-->");
              Card($$renderer4, {
                class: "border-primary/30 bg-primary/5",
                children: ($$renderer5) => {
                  if (Card_content) {
                    $$renderer5.push("<!--[-->");
                    Card_content($$renderer5, {
                      class: "flex flex-wrap items-center justify-between gap-4 p-5",
                      children: ($$renderer6) => {
                        $$renderer6.push(`<div class="space-y-1"><h2 class="font-semibold">Online consultation</h2> <p class="text-sm text-muted-foreground">${escape_html(onlineEncounter.join_available ? "Your online consultation session is ready." : onlineEncounter.unavailable_reason?.startsWith("Online consultation is unavailable:") ? "Online consultation is temporarily unavailable" : onlineEncounter.unavailable_reason ?? "Online consultation is currently unavailable")}</p></div> `);
                        if (onlineEncounter.join_available && onlineEncounter.url) {
                          $$renderer6.push("<!--[0-->");
                          Link($$renderer6, {
                            href: onlineEncounter.url,
                            children: ($$renderer7) => {
                              Button($$renderer7, {
                                class: "min-h-10 gap-2",
                                children: ($$renderer8) => {
                                  Video($$renderer8, { class: "size-4" });
                                  $$renderer8.push(`<!----> Join Consultation Call`);
                                },
                                $$slots: { default: true }
                              });
                            },
                            $$slots: { default: true }
                          });
                        } else {
                          $$renderer6.push("<!--[-1-->");
                          $$renderer6.push(`<span role="note" class="text-xs text-muted-foreground"${attr("title", onlineEncounter.unavailable_reason ?? void 0)}>Currently unavailable</span>`);
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
          if (page_state === "empty") {
            $$renderer4.push("<!--[0-->");
            if (Card) {
              $$renderer4.push("<!--[-->");
              Card($$renderer4, {
                children: ($$renderer5) => {
                  if (Card_content) {
                    $$renderer5.push("<!--[-->");
                    Card_content($$renderer5, {
                      class: "space-y-3 p-6 text-center",
                      children: ($$renderer6) => {
                        User_round($$renderer6, { class: "mx-auto size-8 text-muted-foreground" });
                        $$renderer6.push(`<!----> <p class="font-medium">No application found for this account</p> <p class="text-sm text-muted-foreground">If you applied, contact the Registrar. Your access key was shown after submitting your
            application and may also be in your email.</p>`);
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
            if (identity) {
              $$renderer4.push("<!--[0-->");
              if (Card) {
                $$renderer4.push("<!--[-->");
                Card($$renderer4, {
                  class: "overflow-hidden border-primary/20 bg-primary/5",
                  children: ($$renderer5) => {
                    if (Card_content) {
                      $$renderer5.push("<!--[-->");
                      Card_content($$renderer5, {
                        class: "flex flex-col gap-3 p-5 sm:flex-row sm:items-center sm:justify-between",
                        children: ($$renderer6) => {
                          $$renderer6.push(`<div class="flex items-center gap-3"><span class="flex size-11 shrink-0 items-center justify-center rounded-full bg-primary/15 text-primary" aria-hidden="true">`);
                          User_round($$renderer6, { class: "size-5" });
                          $$renderer6.push(`<!----></span> <div class="min-w-0"><p class="truncate font-semibold">${escape_html(identity.name)}</p> <p class="font-mono text-sm text-muted-foreground">Applicant No. ${escape_html(identity.applicant_no)}</p></div></div> `);
                          if (identity.program_hint) {
                            $$renderer6.push("<!--[0-->");
                            Badge($$renderer6, {
                              variant: "outline",
                              class: "w-fit shrink-0 border-primary/30 text-primary",
                              children: ($$renderer7) => {
                                $$renderer7.push(`<!---->${escape_html(identity.program_hint)}`);
                              },
                              $$slots: { default: true }
                            });
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
            if (notices.needs_correction) {
              $$renderer4.push("<!--[0-->");
              if (Card) {
                $$renderer4.push("<!--[-->");
                Card($$renderer4, {
                  class: "border-amber-500/40 bg-amber-500/10",
                  children: ($$renderer5) => {
                    if (Card_content) {
                      $$renderer5.push("<!--[-->");
                      Card_content($$renderer5, {
                        class: "space-y-3 p-4",
                        children: ($$renderer6) => {
                          $$renderer6.push(`<p class="flex items-center gap-2 font-medium text-amber-800 dark:text-amber-300">`);
                          Triangle_alert($$renderer6, { class: "size-4", "aria-hidden": "true" });
                          $$renderer6.push(`<!----> Registrar needs a correction</p> `);
                          if (notices.correction_notes) {
                            $$renderer6.push("<!--[0-->");
                            $$renderer6.push(`<p class="text-sm text-muted-foreground">${escape_html(notices.correction_notes)}</p>`);
                          } else {
                            $$renderer6.push("<!--[-1-->");
                          }
                          $$renderer6.push(`<!--]--> `);
                          if (notices.correction_href) {
                            $$renderer6.push("<!--[0-->");
                            Link($$renderer6, {
                              href: notices.correction_href,
                              children: ($$renderer7) => {
                                Button($$renderer7, {
                                  class: "min-h-11 w-full",
                                  children: ($$renderer8) => {
                                    $$renderer8.push(`<!---->Fix application`);
                                  },
                                  $$slots: { default: true }
                                });
                              },
                              $$slots: { default: true }
                            });
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
            if (notices.results_blocked) {
              $$renderer4.push("<!--[0-->");
              if (Card) {
                $$renderer4.push("<!--[-->");
                Card($$renderer4, {
                  class: "border-primary/30 bg-primary/5",
                  children: ($$renderer5) => {
                    if (Card_content) {
                      $$renderer5.push("<!--[-->");
                      Card_content($$renderer5, {
                        class: "space-y-3 p-4",
                        children: ($$renderer6) => {
                          $$renderer6.push(`<p class="flex items-center gap-2 font-medium">`);
                          Shield_check($$renderer6, { class: "size-4 text-primary", "aria-hidden": "true" });
                          $$renderer6.push(`<!----> Results ready — claim them at your consultation</p> <p class="text-sm text-muted-foreground">Your results are released for face-to-face consultation. Book a session with Guidance to
              view your scores.</p>`);
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
            if (admission_clearance?.is_confirmed) {
              $$renderer4.push("<!--[0-->");
              if (Card) {
                $$renderer4.push("<!--[-->");
                Card($$renderer4, {
                  class: "border-emerald-500/40 bg-emerald-500/5",
                  children: ($$renderer5) => {
                    if (Card_content) {
                      $$renderer5.push("<!--[-->");
                      Card_content($$renderer5, {
                        class: "space-y-3 p-5",
                        children: ($$renderer6) => {
                          $$renderer6.push(`<div class="flex flex-wrap items-center justify-between gap-2"><div class="flex items-center gap-2">`);
                          Circle_check($$renderer6, {
                            class: "size-5 text-emerald-600 dark:text-emerald-400",
                            "aria-hidden": "true"
                          });
                          $$renderer6.push(`<!----> <h2 class="font-semibold text-foreground">Admission Clearance Confirmed</h2></div> <div class="flex flex-wrap items-center gap-1.5">`);
                          Badge($$renderer6, {
                            variant: "outline",
                            class: "border-emerald-500/40 bg-emerald-500/10 text-emerald-700 dark:text-emerald-400",
                            children: ($$renderer7) => {
                              $$renderer7.push(`<!---->Clearance Confirmed`);
                            },
                            $$slots: { default: true }
                          });
                          $$renderer6.push(`<!----> `);
                          if (admission_clearance.receipt_number) {
                            $$renderer6.push("<!--[0-->");
                            $$renderer6.push(`<span class="font-mono text-xs text-muted-foreground">Ref: ${escape_html(admission_clearance.receipt_number)}</span>`);
                          } else {
                            $$renderer6.push("<!--[-1-->");
                          }
                          $$renderer6.push(`<!--]--></div></div> <p class="text-sm leading-relaxed text-muted-foreground">Admission clearance confirmed. External university matriculation and course registration are conducted directly by the university.</p> <div class="flex flex-wrap items-center gap-2 pt-1 border-t border-border/40"><span class="text-xs font-medium text-foreground">Enrollment sheet:</span> `);
                          if (admission_clearance.sheet_issued) {
                            $$renderer6.push("<!--[0-->");
                            Badge($$renderer6, {
                              variant: "default",
                              class: "gap-1 bg-emerald-600 text-white",
                              children: ($$renderer7) => {
                                File_check($$renderer7, { class: "size-3" });
                                $$renderer7.push(`<!----> Sheet Issued`);
                                if (admission_clearance.sheet_receipt_number) {
                                  $$renderer7.push("<!--[0-->");
                                  $$renderer7.push(`(${escape_html(admission_clearance.sheet_receipt_number)})`);
                                } else {
                                  $$renderer7.push("<!--[-1-->");
                                }
                                $$renderer7.push(`<!--]-->`);
                              },
                              $$slots: { default: true }
                            });
                          } else {
                            $$renderer6.push("<!--[-1-->");
                            Badge($$renderer6, {
                              variant: "secondary",
                              children: ($$renderer7) => {
                                $$renderer7.push(`<!---->Ready for sheet handoff at Registrar desk`);
                              },
                              $$slots: { default: true }
                            });
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
            if (notices.dismissed || notices.archived) {
              $$renderer4.push("<!--[0-->");
              if (Card) {
                $$renderer4.push("<!--[-->");
                Card($$renderer4, {
                  class: "border-destructive/30",
                  children: ($$renderer5) => {
                    if (Card_content) {
                      $$renderer5.push("<!--[-->");
                      Card_content($$renderer5, {
                        class: "space-y-3 p-6 text-center",
                        children: ($$renderer6) => {
                          Triangle_alert($$renderer6, {
                            class: "mx-auto size-8 text-destructive",
                            "aria-hidden": "true"
                          });
                          $$renderer6.push(`<!----> <p class="font-medium">${escape_html(notices.dismissed ? "Application dismissed" : "Application archived")}</p> `);
                          if (notices.dismissal_reason) {
                            $$renderer6.push("<!--[0-->");
                            $$renderer6.push(`<p class="text-sm text-muted-foreground">${escape_html(notices.dismissal_reason)}</p>`);
                          } else {
                            $$renderer6.push("<!--[-1-->");
                          }
                          $$renderer6.push(`<!--]--> <p class="text-sm text-muted-foreground">${escape_html(notices.dismissed ? "This application is closed. Contact the Registrar if you believe this is a mistake." : "This record is no longer active.")}</p>`);
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
            } else if (stages.length > 0) {
              $$renderer4.push("<!--[1-->");
              if (Card) {
                $$renderer4.push("<!--[-->");
                Card($$renderer4, {
                  class: "overflow-hidden shadow-sm",
                  "data-tour": "applicant-stages-list",
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
                                $$renderer7.push(`<!---->Your journey`);
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
                                $$renderer7.push(`<!---->Application → Examination → Scoring → Advising → Admission Clearance`);
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
                          $$renderer6.push(`<ol class="relative m-0 list-none space-y-0 p-0" aria-label="Application journey"><!--[-->`);
                          const each_array = ensure_array_like(stages);
                          for (let i = 0, $$length = each_array.length; i < $$length; i++) {
                            let stage = each_array[i];
                            const isLast = i === stages.length - 1;
                            const badge = badgeFor(stage.status);
                            $$renderer6.push(`<li class="relative flex gap-3"><div class="flex shrink-0 flex-col items-center"><div class="relative z-10">`);
                            if (stage.status === "current") {
                              $$renderer6.push("<!--[0-->");
                              $$renderer6.push(`<div class="absolute -inset-1.5 animate-ping rounded-full bg-primary/20 opacity-75" style="animation-duration: 3s;" aria-hidden="true"></div>`);
                            } else {
                              $$renderer6.push("<!--[-1-->");
                            }
                            $$renderer6.push(`<!--]--> <div${attr_class(`relative flex size-8 items-center justify-center rounded-full border-2 transition-colors ${stage.status === "done" ? "border-primary bg-primary text-primary-foreground" : stage.status === "current" ? "border-primary bg-background text-primary ring-4 ring-primary/10" : stage.status === "action" ? "border-amber-500 bg-amber-500/10 text-amber-600 dark:text-amber-400" : stage.status === "auto" ? "border-emerald-600/50 bg-emerald-600/10 text-emerald-700 dark:text-emerald-400" : "border-muted-foreground/30 bg-background text-muted-foreground"}`)}>`);
                            if (stage.status === "done") {
                              $$renderer6.push("<!--[0-->");
                              Circle_check($$renderer6, { class: "size-4", "aria-hidden": "true" });
                            } else if (stage.status === "action") {
                              $$renderer6.push("<!--[1-->");
                              Triangle_alert($$renderer6, { class: "size-4", "aria-hidden": "true" });
                            } else if (stage.status === "auto") {
                              $$renderer6.push("<!--[2-->");
                              Sparkles($$renderer6, { class: "size-4", "aria-hidden": "true" });
                            } else if (stage.status === "current") {
                              $$renderer6.push("<!--[3-->");
                              Circle($$renderer6, { class: "size-4 fill-primary/20", "aria-hidden": "true" });
                            } else {
                              $$renderer6.push("<!--[-1-->");
                              Circle($$renderer6, { class: "size-4", "aria-hidden": "true" });
                            }
                            $$renderer6.push(`<!--]--></div></div> `);
                            if (!isLast) {
                              $$renderer6.push("<!--[0-->");
                              $$renderer6.push(`<div${attr_class(`mt-1 min-h-7 w-0.5 flex-1 rounded-full ${stage.status === "done" ? "bg-primary" : stage.status === "current" ? "bg-primary/40" : "bg-border"}`)} aria-hidden="true"></div>`);
                            } else {
                              $$renderer6.push("<!--[-1-->");
                            }
                            $$renderer6.push(`<!--]--></div> <div class="min-w-0 flex-1 pb-5 pt-0.5 last:pb-0"><div class="flex flex-wrap items-center gap-2"><span${attr_class(`text-sm font-medium ${stage.status === "upcoming" ? "text-muted-foreground" : "text-foreground"}`)}>${escape_html(stage.label)}</span> `);
                            Badge($$renderer6, {
                              variant: badge.variant,
                              class: "shrink-0 text-[10px] uppercase tracking-wide",
                              children: ($$renderer7) => {
                                $$renderer7.push(`<!---->${escape_html(badge.label)}`);
                              },
                              $$slots: { default: true }
                            });
                            $$renderer6.push(`<!----> `);
                            if (stage.status === "auto") {
                              $$renderer6.push("<!--[0-->");
                              $$renderer6.push(`<span class="text-xs text-emerald-700 dark:text-emerald-400" title="Set by Guidance — no action needed">✦ projection active</span>`);
                            } else {
                              $$renderer6.push("<!--[-1-->");
                            }
                            $$renderer6.push(`<!--]--> `);
                            if (stage.at) {
                              $$renderer6.push("<!--[0-->");
                              $$renderer6.push(`<span class="text-xs text-muted-foreground">${escape_html(stage.at)}</span>`);
                            } else {
                              $$renderer6.push("<!--[-1-->");
                            }
                            $$renderer6.push(`<!--]--></div> `);
                            if (stage.summary) {
                              $$renderer6.push("<!--[0-->");
                              $$renderer6.push(`<p class="mt-1 text-sm leading-relaxed text-muted-foreground">${escape_html(stage.summary)}</p>`);
                            } else {
                              $$renderer6.push("<!--[-1-->");
                            }
                            $$renderer6.push(`<!--]--> `);
                            if (stage.release && stage.release.mode === "online" && stage.profile?.series?.length) {
                              $$renderer6.push("<!--[0-->");
                              $$renderer6.push(`<div class="mt-3 rounded-lg border border-border bg-muted/20 p-4"><p class="text-xs font-semibold uppercase tracking-wide text-muted-foreground">${escape_html(stage.profile.title ?? "Released score profile")}</p> <ul class="mt-3 space-y-3"><!--[-->`);
                              const each_array_1 = ensure_array_like(stage.profile.series);
                              for (let $$index = 0, $$length2 = each_array_1.length; $$index < $$length2; $$index++) {
                                let item = each_array_1[$$index];
                                const scoreNum = Number(item.score);
                                const barWidth = Number.isFinite(scoreNum) ? Math.min(Math.max(scoreNum, 0), 100) : null;
                                $$renderer6.push(`<li><div class="flex items-baseline justify-between gap-3"><span class="text-sm font-medium">${escape_html(item.area_name)}</span> <span class="font-mono text-sm tabular-nums">${escape_html(item.score)}</span></div> `);
                                if (barWidth !== null) {
                                  $$renderer6.push("<!--[0-->");
                                  $$renderer6.push(`<div class="mt-1 h-1.5 overflow-hidden rounded-full bg-border" role="img"${attr("aria-label", `${stringify(item.area_name)}: ${stringify(item.score)} converted score`)}><div class="h-full rounded-full bg-primary"${attr_style(`width: ${stringify(barWidth)}%`)}></div></div>`);
                                } else {
                                  $$renderer6.push("<!--[-1-->");
                                  $$renderer6.push(`<p class="mt-1 text-xs text-muted-foreground">Descriptor value — no numeric bar.</p>`);
                                }
                                $$renderer6.push(`<!--]--></li>`);
                              }
                              $$renderer6.push(`<!--]--></ul> <dl class="mt-3 flex flex-wrap gap-x-4 gap-y-1 text-xs text-muted-foreground">`);
                              if (stage.release.released_at) {
                                $$renderer6.push("<!--[0-->");
                                $$renderer6.push(`<div class="flex gap-1"><dt class="font-medium">Released</dt> <dd>${escape_html(new Date(stage.release.released_at).toLocaleDateString())}</dd></div>`);
                              } else {
                                $$renderer6.push("<!--[-1-->");
                              }
                              $$renderer6.push(`<!--]--> `);
                              if (stage.release.version) {
                                $$renderer6.push("<!--[0-->");
                                $$renderer6.push(`<div class="flex gap-1"><dt class="font-medium">Version</dt> <dd>${escape_html(stage.release.version)}</dd></div>`);
                              } else {
                                $$renderer6.push("<!--[-1-->");
                              }
                              $$renderer6.push(`<!--]--></dl> <p class="mt-2 text-xs text-muted-foreground">Unit: converted score · source: released result document. Scores are
                          informational; the official result sheet is released by the Registrar.</p></div>`);
                            } else {
                              $$renderer6.push("<!--[-1-->");
                            }
                            $$renderer6.push(`<!--]--> `);
                            if (stage.actions?.length) {
                              $$renderer6.push("<!--[0-->");
                              $$renderer6.push(`<div class="mt-2.5 flex flex-wrap gap-2"><!--[-->`);
                              const each_array_2 = ensure_array_like(stage.actions);
                              for (let $$index_1 = 0, $$length2 = each_array_2.length; $$index_1 < $$length2; $$index_1++) {
                                let action = each_array_2[$$index_1];
                                if (action.primary) {
                                  $$renderer6.push("<!--[0-->");
                                  Link($$renderer6, {
                                    href: action.href,
                                    children: ($$renderer7) => {
                                      Button($$renderer7, {
                                        class: "min-h-10",
                                        children: ($$renderer8) => {
                                          $$renderer8.push(`<!---->${escape_html(action.label)}`);
                                        },
                                        $$slots: { default: true }
                                      });
                                    },
                                    $$slots: { default: true }
                                  });
                                } else {
                                  $$renderer6.push("<!--[-1-->");
                                  Link($$renderer6, {
                                    href: action.href,
                                    children: ($$renderer7) => {
                                      Button($$renderer7, {
                                        variant: "outline",
                                        class: "min-h-10",
                                        children: ($$renderer8) => {
                                          $$renderer8.push(`<!---->${escape_html(action.label)}`);
                                        },
                                        $$slots: { default: true }
                                      });
                                    },
                                    $$slots: { default: true }
                                  });
                                }
                                $$renderer6.push(`<!--]-->`);
                              }
                              $$renderer6.push(`<!--]--></div>`);
                            } else {
                              $$renderer6.push("<!--[-1-->");
                            }
                            $$renderer6.push(`<!--]--></div></li>`);
                          }
                          $$renderer6.push(`<!--]--></ol>`);
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
            if (quick_links.length > 0) {
              $$renderer4.push("<!--[0-->");
              $$renderer4.push(`<div data-tour="quick-link-qr"><p class="mb-2 text-xs font-semibold uppercase tracking-wider text-muted-foreground">Quick links</p> <div class="flex flex-wrap gap-2"><!--[-->`);
              const each_array_3 = ensure_array_like(quick_links);
              for (let $$index_3 = 0, $$length = each_array_3.length; $$index_3 < $$length; $$index_3++) {
                let link = each_array_3[$$index_3];
                const Icon2 = quickLinkIcon(link.key);
                Link($$renderer4, {
                  href: link.href,
                  children: ($$renderer5) => {
                    Button($$renderer5, {
                      variant: "outline",
                      class: "min-h-11 gap-2",
                      children: ($$renderer6) => {
                        if (Icon2) {
                          $$renderer6.push("<!--[-->");
                          Icon2($$renderer6, { class: "size-4", "aria-hidden": "true" });
                          $$renderer6.push("<!--]-->");
                        } else {
                          $$renderer6.push("<!--[!-->");
                          $$renderer6.push("<!--]-->");
                        }
                        $$renderer6.push(` ${escape_html(link.label)}`);
                      },
                      $$slots: { default: true }
                    });
                  },
                  $$slots: { default: true }
                });
              }
              $$renderer4.push(`<!--]--></div></div>`);
            } else {
              $$renderer4.push("<!--[-1-->");
            }
            $$renderer4.push(`<!--]-->`);
          }
          $$renderer4.push(`<!--]--></div> `);
          XiamNpcDialog($$renderer4, {
            tourKey: "applicant-portal",
            context: portalContext(),
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
  Portal as default
};
