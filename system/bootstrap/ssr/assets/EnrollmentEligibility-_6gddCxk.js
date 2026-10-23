import { g as sanitize_props, i as spread_props, j as slot, e as escape_html, B as Button, a as ensure_array_like, c as attr_class, d as derived, n as newIdempotencyKey, h as head, A as clsx } from "../app.js";
import { S as StaffLayout, a as Send } from "./StaffLayout-CR-MZopa.js";
import { P as PageHeaderBar } from "./PageHeaderBar-DJhT5vOR.js";
import { B as Badge } from "./badge-D9Vgs7xh.js";
import { C as Card, a as Card_content } from "./card-content-Brg2wRkV.js";
import { C as Card_description } from "./card-description-BGy_v5-d.js";
import { C as Card_header, a as Card_title } from "./card-title-BNY2ZPje.js";
import { I as Input } from "./input-BUZAZKIK.js";
import { F as File_text } from "./file-text-PXEtmXm9.js";
import { R as Refresh_cw } from "./refresh-cw-CYdk8aE0.js";
import { I as Icon } from "./Icon-Dal7PqKK.js";
import { C as Circle_check } from "./circle-check-C59ZS7Zv.js";
import { C as Circle_alert } from "./circle-alert-jWXJ7oC0.js";
import { C as Circle_x } from "./circle-x-BQrhluGu.js";
import { C as Circle_question_mark } from "./circle-question-mark-DY-RU614.js";
import { router } from "@inertiajs/core";
import { D as Dialog, a as Dialog_content, b as Dialog_header, c as Dialog_title, d as Dialog_description } from "./dialog-description-D12L0QA9.js";
import { D as Dialog_footer } from "./dialog-footer-kNnwKT2h.js";
import { T as Textarea } from "./textarea-Bv3Na1wO.js";
import { S as Search } from "./settings-C5xOKpV3.js";
import { L as Link } from "./Link-B7ks7rBK.js";
import { R as Rotate_ccw } from "./rotate-ccw-D6Dm_efx.js";
import { S as Shield_alert } from "./shield-alert-CXtDdte9.js";
import { H as History } from "./history-D15LFKXr.js";
import "axios";
import "clsx";
import "laravel-precognition";
import "tailwind-merge";
import "tailwind-variants";
import "./ToastManager-C7oO_4Jn.js";
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
import "./inbox-AmUVtgA2.js";
import "./mail-DBtkjaCt.js";
import "./AvatarBadge-DQ5Qru9A.js";
import "./camera-C09Eyogi.js";
import "./arrow-right-CzynHiIo.js";
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
import "./sparkles-DbQIaVG6.js";
import "./chevron-left-DCSOZwZl.js";
function File_check_corner($$renderer, $$props) {
  const $$sanitized_props = sanitize_props($$props);
  const iconNode = [
    [
      "path",
      {
        "d": "M10.5 22H6a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h8a2.4 2.4 0 0 1 1.706.706l3.588 3.588A2.4 2.4 0 0 1 20 8v6"
      }
    ],
    ["path", { "d": "M14 2v5a1 1 0 0 0 1 1h5" }],
    ["path", { "d": "m14 20 2 2 4-4" }]
  ];
  Icon($$renderer, spread_props([
    { name: "file-check-corner" },
    $$sanitized_props,
    {
      /**
       * @component @name FileCheckCorner
       * @description Lucide SVG icon component, renders SVG Element with children.
       *
       * @preview ![img](data:image/svg+xml;base64,PHN2ZyAgeG1sbnM9Imh0dHA6Ly93d3cudzMub3JnLzIwMDAvc3ZnIgogIHdpZHRoPSIyNCIKICBoZWlnaHQ9IjI0IgogIHZpZXdCb3g9IjAgMCAyNCAyNCIKICBmaWxsPSJub25lIgogIHN0cm9rZT0iIzAwMCIgc3R5bGU9ImJhY2tncm91bmQtY29sb3I6ICNmZmY7IGJvcmRlci1yYWRpdXM6IDJweCIKICBzdHJva2Utd2lkdGg9IjIiCiAgc3Ryb2tlLWxpbmVjYXA9InJvdW5kIgogIHN0cm9rZS1saW5lam9pbj0icm91bmQiCj4KICA8cGF0aCBkPSJNMTAuNSAyMkg2YTIgMiAwIDAgMS0yLTJWNGEyIDIgMCAwIDEgMi0yaDhhMi40IDIuNCAwIDAgMSAxLjcwNi43MDZsMy41ODggMy41ODhBMi40IDIuNCAwIDAgMSAyMCA4djYiIC8+CiAgPHBhdGggZD0iTTE0IDJ2NWExIDEgMCAwIDAgMSAxaDUiIC8+CiAgPHBhdGggZD0ibTE0IDIwIDIgMiA0LTQiIC8+Cjwvc3ZnPgo=) - https://lucide.dev/icons/file-check-corner
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
function Message_square($$renderer, $$props) {
  const $$sanitized_props = sanitize_props($$props);
  const iconNode = [
    [
      "path",
      {
        "d": "M22 17a2 2 0 0 1-2 2H6.828a2 2 0 0 0-1.414.586l-2.202 2.202A.71.71 0 0 1 2 21.286V5a2 2 0 0 1 2-2h16a2 2 0 0 1 2 2z"
      }
    ]
  ];
  Icon($$renderer, spread_props([
    { name: "message-square" },
    $$sanitized_props,
    {
      /**
       * @component @name MessageSquare
       * @description Lucide SVG icon component, renders SVG Element with children.
       *
       * @preview ![img](data:image/svg+xml;base64,PHN2ZyAgeG1sbnM9Imh0dHA6Ly93d3cudzMub3JnLzIwMDAvc3ZnIgogIHdpZHRoPSIyNCIKICBoZWlnaHQ9IjI0IgogIHZpZXdCb3g9IjAgMCAyNCAyNCIKICBmaWxsPSJub25lIgogIHN0cm9rZT0iIzAwMCIgc3R5bGU9ImJhY2tncm91bmQtY29sb3I6ICNmZmY7IGJvcmRlci1yYWRpdXM6IDJweCIKICBzdHJva2Utd2lkdGg9IjIiCiAgc3Ryb2tlLWxpbmVjYXA9InJvdW5kIgogIHN0cm9rZS1saW5lam9pbj0icm91bmQiCj4KICA8cGF0aCBkPSJNMjIgMTdhMiAyIDAgMCAxLTIgMkg2LjgyOGEyIDIgMCAwIDAtMS40MTQuNTg2bC0yLjIwMiAyLjIwMkEuNzEuNzEgMCAwIDEgMiAyMS4yODZWNWEyIDIgMCAwIDEgMi0yaDE2YTIgMiAwIDAgMSAyIDJ6IiAvPgo8L3N2Zz4K) - https://lucide.dev/icons/message-square
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
function DocumentRequirementsPanel($$renderer, $$props) {
  $$renderer.component(($$renderer2) => {
    let { attemptId, checklist = null, isClearanceConfirmed = false } = $$props;
    let itemRemarks = {};
    let activeItemRemarksId = null;
    let isSubmitting = false;
    const items = derived(() => checklist?.items ?? []);
    const setInfo = derived(() => checklist?.set ?? null);
    const allMandatoryVerified = derived(() => checklist?.all_mandatory_verified ?? false);
    const pendingCount = derived(() => checklist?.mandatory_pending_count ?? 0);
    function getStatusBadge(status) {
      switch (status) {
        case "verified":
          return {
            label: "Verified",
            variant: "default",
            class: "bg-emerald-600 hover:bg-emerald-700 text-white border-transparent",
            icon: Circle_check
          };
        case "deficient":
          return {
            label: "Deficient",
            variant: "outline",
            class: "bg-amber-50 text-amber-700 border-amber-300 dark:bg-amber-950/30 dark:text-amber-400 dark:border-amber-700",
            icon: Circle_alert
          };
        case "missing":
          return {
            label: "Missing",
            variant: "destructive",
            class: "bg-rose-600 hover:bg-rose-700 text-white border-transparent",
            icon: Circle_x
          };
        case "not_checked":
        default:
          return {
            label: "Not checked",
            variant: "secondary",
            class: "bg-slate-100 text-slate-700 border-slate-300 dark:bg-slate-800 dark:text-slate-300 dark:border-slate-700",
            icon: Circle_question_mark
          };
      }
    }
    function initializeRequirements() {
      isSubmitting = true;
      router.post(`/registrar/enrollment-eligibility/${attemptId}/requirements/initialize`, {}, {
        preserveScroll: true,
        onFinish: () => {
          isSubmitting = false;
        }
      });
    }
    function verifyItem(itemId, status) {
      if (isClearanceConfirmed) return;
      isSubmitting = true;
      const remarks = itemRemarks[itemId] || null;
      router.post(
        `/registrar/enrollment-eligibility/${attemptId}/documents/verify`,
        {
          requirement_item_id: itemId,
          status,
          remarks,
          idempotency_key: newIdempotencyKey()
        },
        {
          preserveScroll: true,
          onSuccess: () => {
            activeItemRemarksId = null;
          },
          onFinish: () => {
            isSubmitting = false;
          }
        }
      );
    }
    function toggleRemarksInput(itemId) {
      if (activeItemRemarksId === itemId) {
        activeItemRemarksId = null;
      } else {
        activeItemRemarksId = itemId;
      }
    }
    let $$settled = true;
    let $$inner_renderer;
    function $$render_inner($$renderer3) {
      if (Card) {
        $$renderer3.push("<!--[-->");
        Card($$renderer3, {
          class: "overflow-hidden border-border/80 shadow-sm",
          children: ($$renderer4) => {
            if (Card_header) {
              $$renderer4.push("<!--[-->");
              Card_header($$renderer4, {
                class: "border-b bg-muted/20 pb-3",
                children: ($$renderer5) => {
                  $$renderer5.push(`<div class="flex flex-wrap items-center justify-between gap-2"><div class="flex items-center gap-2">`);
                  File_text($$renderer5, { class: "size-4 text-primary" });
                  $$renderer5.push(`<!----> `);
                  if (Card_title) {
                    $$renderer5.push("<!--[-->");
                    Card_title($$renderer5, {
                      class: "text-base font-semibold",
                      children: ($$renderer6) => {
                        $$renderer6.push(`<!---->Intake Document Requirements`);
                      },
                      $$slots: { default: true }
                    });
                    $$renderer5.push("<!--]-->");
                  } else {
                    $$renderer5.push("<!--[!-->");
                    $$renderer5.push("<!--]-->");
                  }
                  $$renderer5.push(`</div> `);
                  if (setInfo()) {
                    $$renderer5.push("<!--[0-->");
                    Badge($$renderer5, {
                      variant: allMandatoryVerified() ? "default" : "secondary",
                      class: allMandatoryVerified() ? "bg-emerald-600 text-white" : "",
                      children: ($$renderer6) => {
                        $$renderer6.push(`<!---->${escape_html(allMandatoryVerified() ? "All mandatory verified" : `${pendingCount()} mandatory pending`)}`);
                      },
                      $$slots: { default: true }
                    });
                  } else {
                    $$renderer5.push("<!--[-1-->");
                  }
                  $$renderer5.push(`<!--]--></div> `);
                  if (setInfo()) {
                    $$renderer5.push("<!--[0-->");
                    if (Card_description) {
                      $$renderer5.push("<!--[-->");
                      Card_description($$renderer5, {
                        class: "text-xs",
                        children: ($$renderer6) => {
                          $$renderer6.push(`<!---->Track: <span class="font-medium text-foreground capitalize">${escape_html(setInfo().applicant_track)}</span> · Catalog v${escape_html(setInfo().catalog_version)}`);
                        },
                        $$slots: { default: true }
                      });
                      $$renderer5.push("<!--]-->");
                    } else {
                      $$renderer5.push("<!--[!-->");
                      $$renderer5.push("<!--]-->");
                    }
                  } else {
                    $$renderer5.push("<!--[-1-->");
                  }
                  $$renderer5.push(`<!--]-->`);
                },
                $$slots: { default: true }
              });
              $$renderer4.push("<!--]-->");
            } else {
              $$renderer4.push("<!--[!-->");
              $$renderer4.push("<!--]-->");
            }
            $$renderer4.push(` `);
            if (Card_content) {
              $$renderer4.push("<!--[-->");
              Card_content($$renderer4, {
                class: "space-y-4 pt-4",
                children: ($$renderer5) => {
                  if (!setInfo()) {
                    $$renderer5.push("<!--[0-->");
                    $$renderer5.push(`<div class="rounded-md border border-dashed border-border/70 p-6 text-center">`);
                    File_text($$renderer5, { class: "mx-auto size-8 text-muted-foreground/60 mb-2" });
                    $$renderer5.push(`<!----> <p class="text-sm font-medium">No requirement checklist snapshot</p> <p class="text-xs text-muted-foreground mt-1 mb-4">Initialize the applicable track requirements for this attempt to begin document verification.</p> `);
                    Button($$renderer5, {
                      type: "button",
                      size: "sm",
                      disabled: isSubmitting || isClearanceConfirmed,
                      onclick: initializeRequirements,
                      children: ($$renderer6) => {
                        Refresh_cw($$renderer6, { class: "size-3.5 mr-1.5" });
                        $$renderer6.push(`<!----> Initialize Requirements`);
                      },
                      $$slots: { default: true }
                    });
                    $$renderer5.push(`<!----></div>`);
                  } else if (items().length === 0) {
                    $$renderer5.push("<!--[1-->");
                    $$renderer5.push(`<p class="text-sm text-muted-foreground py-2 text-center">No requirement items configured for track ${escape_html(setInfo().applicant_track)}.</p>`);
                  } else {
                    $$renderer5.push("<!--[-1-->");
                    $$renderer5.push(`<ul class="divide-y divide-border/60"><!--[-->`);
                    const each_array = ensure_array_like(items());
                    for (let $$index = 0, $$length = each_array.length; $$index < $$length; $$index++) {
                      let item = each_array[$$index];
                      const statusBadge = getStatusBadge(item.latest_status);
                      $$renderer5.push(`<li class="py-3 first:pt-0 last:pb-0 space-y-2.5"><div class="flex flex-col sm:flex-row sm:items-center justify-between gap-2"><div class="min-w-0 flex-1"><div class="flex items-center gap-2"><span class="font-medium text-sm text-foreground">${escape_html(item.label)}</span> `);
                      if (item.is_mandatory) {
                        $$renderer5.push("<!--[0-->");
                        Badge($$renderer5, {
                          variant: "outline",
                          class: "text-[10px] px-1.5 py-0 border-amber-400/80 text-amber-700 dark:text-amber-400",
                          children: ($$renderer6) => {
                            $$renderer6.push(`<!---->Mandatory`);
                          },
                          $$slots: { default: true }
                        });
                      } else {
                        $$renderer5.push("<!--[-1-->");
                        Badge($$renderer5, {
                          variant: "outline",
                          class: "text-[10px] px-1.5 py-0 text-muted-foreground",
                          children: ($$renderer6) => {
                            $$renderer6.push(`<!---->Optional`);
                          },
                          $$slots: { default: true }
                        });
                      }
                      $$renderer5.push(`<!--]--></div> `);
                      if (item.code) {
                        $$renderer5.push("<!--[0-->");
                        $$renderer5.push(`<p class="font-mono text-xs text-muted-foreground mt-0.5">${escape_html(item.code)}</p>`);
                      } else {
                        $$renderer5.push("<!--[-1-->");
                      }
                      $$renderer5.push(`<!--]--></div> <div class="flex items-center gap-2 shrink-0"><span${attr_class(`inline-flex items-center gap-1 text-xs px-2 py-0.5 rounded-full font-medium border ${statusBadge.class}`)}>`);
                      if (statusBadge.icon) {
                        $$renderer5.push("<!--[-->");
                        statusBadge.icon($$renderer5, { class: "size-3" });
                        $$renderer5.push("<!--]-->");
                      } else {
                        $$renderer5.push("<!--[!-->");
                        $$renderer5.push("<!--]-->");
                      }
                      $$renderer5.push(` ${escape_html(statusBadge.label)}</span> `);
                      if (!isClearanceConfirmed) {
                        $$renderer5.push("<!--[0-->");
                        Button($$renderer5, {
                          type: "button",
                          variant: "ghost",
                          size: "sm",
                          class: "h-7 px-2 text-xs",
                          onclick: () => toggleRemarksInput(item.id),
                          title: "Add / edit remarks",
                          children: ($$renderer6) => {
                            Message_square($$renderer6, { class: "size-3 mr-1" });
                            $$renderer6.push(`<!----> Remarks`);
                          },
                          $$slots: { default: true }
                        });
                      } else {
                        $$renderer5.push("<!--[-1-->");
                      }
                      $$renderer5.push(`<!--]--></div></div> `);
                      if (item.remarks) {
                        $$renderer5.push("<!--[0-->");
                        $$renderer5.push(`<div class="text-xs bg-muted/40 rounded p-2 text-muted-foreground border-l-2 border-primary/50"><span class="font-medium text-foreground/80">Verifier remarks:</span> ${escape_html(item.remarks)}</div>`);
                      } else {
                        $$renderer5.push("<!--[-1-->");
                      }
                      $$renderer5.push(`<!--]--> `);
                      if (!isClearanceConfirmed) {
                        $$renderer5.push("<!--[0-->");
                        $$renderer5.push(`<div class="flex flex-wrap items-center gap-1.5 pt-1">`);
                        Button($$renderer5, {
                          type: "button",
                          size: "sm",
                          variant: "outline",
                          class: "h-7 px-2.5 text-xs text-emerald-700 hover:text-emerald-800 hover:bg-emerald-50 dark:hover:bg-emerald-950/30 border-emerald-300",
                          disabled: isSubmitting || item.latest_status === "verified",
                          onclick: () => verifyItem(item.id, "verified"),
                          children: ($$renderer6) => {
                            Circle_check($$renderer6, { class: "size-3 mr-1 text-emerald-600" });
                            $$renderer6.push(`<!----> Verify`);
                          },
                          $$slots: { default: true }
                        });
                        $$renderer5.push(`<!----> `);
                        Button($$renderer5, {
                          type: "button",
                          size: "sm",
                          variant: "outline",
                          class: "h-7 px-2.5 text-xs text-amber-700 hover:text-amber-800 hover:bg-amber-50 dark:hover:bg-amber-950/30 border-amber-300",
                          disabled: isSubmitting || item.latest_status === "deficient",
                          onclick: () => verifyItem(item.id, "deficient"),
                          children: ($$renderer6) => {
                            Circle_alert($$renderer6, { class: "size-3 mr-1 text-amber-600" });
                            $$renderer6.push(`<!----> Deficient`);
                          },
                          $$slots: { default: true }
                        });
                        $$renderer5.push(`<!----> `);
                        Button($$renderer5, {
                          type: "button",
                          size: "sm",
                          variant: "outline",
                          class: "h-7 px-2.5 text-xs text-rose-700 hover:text-rose-800 hover:bg-rose-50 dark:hover:bg-rose-950/30 border-rose-300",
                          disabled: isSubmitting || item.latest_status === "missing",
                          onclick: () => verifyItem(item.id, "missing"),
                          children: ($$renderer6) => {
                            Circle_x($$renderer6, { class: "size-3 mr-1 text-rose-600" });
                            $$renderer6.push(`<!----> Missing`);
                          },
                          $$slots: { default: true }
                        });
                        $$renderer5.push(`<!----></div> `);
                        if (activeItemRemarksId === item.id) {
                          $$renderer5.push("<!--[0-->");
                          $$renderer5.push(`<div class="pt-1.5 flex gap-2">`);
                          Input($$renderer5, {
                            placeholder: "Enter observation / deficiency remarks...",
                            class: "h-8 text-xs",
                            get value() {
                              return itemRemarks[item.id];
                            },
                            set value($$value) {
                              itemRemarks[item.id] = $$value;
                              $$settled = false;
                            }
                          });
                          $$renderer5.push(`<!----> `);
                          Button($$renderer5, {
                            type: "button",
                            variant: "ghost",
                            size: "sm",
                            class: "h-8 px-2 text-xs",
                            onclick: () => toggleRemarksInput(item.id),
                            children: ($$renderer6) => {
                              $$renderer6.push(`<!---->Done`);
                            },
                            $$slots: { default: true }
                          });
                          $$renderer5.push(`<!----></div>`);
                        } else {
                          $$renderer5.push("<!--[-1-->");
                        }
                        $$renderer5.push(`<!--]-->`);
                      } else {
                        $$renderer5.push("<!--[-1-->");
                      }
                      $$renderer5.push(`<!--]--></li>`);
                    }
                    $$renderer5.push(`<!--]--></ul>`);
                  }
                  $$renderer5.push(`<!--]--> `);
                  if (isClearanceConfirmed) {
                    $$renderer5.push("<!--[0-->");
                    $$renderer5.push(`<div class="rounded-md bg-muted/30 p-2.5 text-xs text-muted-foreground border border-border/50 text-center">Document verification is locked while admission clearance is confirmed.</div>`);
                  } else {
                    $$renderer5.push("<!--[-1-->");
                  }
                  $$renderer5.push(`<!--]-->`);
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
    }
    do {
      $$settled = true;
      $$inner_renderer = $$renderer2.copy();
      $$render_inner($$inner_renderer);
    } while (!$$settled);
    $$renderer2.subsume($$inner_renderer);
  });
}
function EnrollmentEligibility($$renderer, $$props) {
  $$renderer.component(($$renderer2) => {
    let { page_state = "search", query = "", results = [], card = null } = $$props;
    let searchTerm = query;
    let isSubmitting = false;
    let isRevokeDialogOpen = false;
    let revokeReason = "";
    let isHandoffDialogOpen = false;
    let handoffPaperReference = "";
    function runSearch() {
      router.get("/registrar/enrollment-eligibility", { q: searchTerm }, { preserveState: true, replace: true });
    }
    const factLabels = {
      accepted: "Application accepted",
      grading_finalized: "Exam completed and graded",
      result_released: "Result released",
      advised: "Consultation advised"
    };
    const milestones = [
      { key: "accepted", label: "Application accepted" },
      { key: "grading_finalized", label: "Exam completed and graded" },
      { key: "result_released", label: "Result released" },
      { key: "advised", label: "Consultation advised" }
    ];
    function formatDate(iso) {
      if (!iso) return null;
      return new Date(iso).toLocaleDateString(void 0, {
        month: "short",
        day: "numeric",
        year: "numeric",
        hour: "2-digit",
        minute: "2-digit"
      });
    }
    const missingFactLabels = derived(() => (card?.missing ?? []).map((k) => factLabels[k] ?? k));
    const clearanceReadiness = derived(() => card?.clearance_readiness ?? null);
    const isClearanceReady = derived(() => clearanceReadiness()?.is_ready ?? false);
    const latestClearance = derived(() => card?.latest_clearance ?? null);
    const latestHandoff = derived(() => card?.latest_sheet_handoff ?? null);
    const isClearanceConfirmed = derived(() => latestClearance()?.status === "confirmed");
    const eligibilityContext = derived(() => {
      const isCard = page_state === "card" && Boolean(card);
      const safetyKey = `${page_state}:${query ? "q" : "noq"}:${results?.length ?? 0}:${isCard ? card.eligible ? "elig" : "inelig" : "search"}`;
      return {
        entityKey: isCard ? String(card.id ?? card.reference_number ?? "card") : "search",
        safetyKey,
        freshness: "current",
        pageState: page_state,
        hasQuery: Boolean(query && query.trim().length > 0),
        resultsCount: results?.length ?? 0,
        hasCard: isCard,
        candidateName: card?.name ?? null,
        isEligible: Boolean(card?.eligible),
        missingFacts: missingFactLabels()
      };
    });
    function confirmClearance() {
      if (!isClearanceReady() || isClearanceConfirmed() || isSubmitting) return;
      isSubmitting = true;
      router.post(`/registrar/enrollment-eligibility/${card.id}/clearance/confirm`, { idempotency_key: newIdempotencyKey() }, {
        preserveScroll: true,
        onFinish: () => {
          isSubmitting = false;
        }
      });
    }
    function submitRevocation() {
      if (!revokeReason.trim() || isSubmitting) return;
      isSubmitting = true;
      router.post(`/registrar/enrollment-eligibility/${card.id}/clearance/revoke`, { reason: revokeReason.trim() }, {
        preserveScroll: true,
        onSuccess: () => {
          isRevokeDialogOpen = false;
          revokeReason = "";
        },
        onFinish: () => {
          isSubmitting = false;
        }
      });
    }
    function submitSheetHandoff() {
      if (isSubmitting) return;
      isSubmitting = true;
      router.post(
        `/registrar/enrollment-eligibility/${card.id}/sheet/issue`,
        {
          paper_reference: handoffPaperReference.trim() || null,
          idempotency_key: newIdempotencyKey()
        },
        {
          preserveScroll: true,
          onSuccess: () => {
            isHandoffDialogOpen = false;
            handoffPaperReference = "";
          },
          onFinish: () => {
            isSubmitting = false;
          }
        }
      );
    }
    let $$settled = true;
    let $$inner_renderer;
    function $$render_inner($$renderer3) {
      head("n82o90", $$renderer3, ($$renderer4) => {
        $$renderer4.title(($$renderer5) => {
          $$renderer5.push(`<title>Enrollment Eligibility · NEXIAM</title>`);
        });
      });
      StaffLayout($$renderer3, {
        breadcrumbs: [{ label: "Enrollment Eligibility" }],
        children: ($$renderer4) => {
          $$renderer4.push(`<div class="min-w-0 space-y-6 p-4 sm:p-6">`);
          PageHeaderBar($$renderer4, {
            title: "Enrollment Eligibility & Clearance",
            description: "Verify candidate eligibility, intake document requirements, and issue official admission clearance.",
            tourKey: "enrollment-eligibility",
            context: eligibilityContext()
          });
          $$renderer4.push(`<!----> `);
          if (page_state === "search") {
            $$renderer4.push("<!--[0-->");
            if (Card) {
              $$renderer4.push("<!--[-->");
              Card($$renderer4, {
                "data-tour": "eligibility-table",
                children: ($$renderer5) => {
                  if (Card_content) {
                    $$renderer5.push("<!--[-->");
                    Card_content($$renderer5, {
                      class: "space-y-4 p-4",
                      children: ($$renderer6) => {
                        $$renderer6.push(`<div class="flex flex-col gap-3 sm:flex-row"><div class="flex-1">`);
                        Input($$renderer6, {
                          placeholder: "Name, reference number, or email",
                          onkeydown: (event) => {
                            if (event.key === "Enter") runSearch();
                          },
                          get value() {
                            return searchTerm;
                          },
                          set value($$value) {
                            searchTerm = $$value;
                            $$settled = false;
                          }
                        });
                        $$renderer6.push(`<!----></div> `);
                        Button($$renderer6, {
                          type: "button",
                          class: "min-h-10",
                          onclick: runSearch,
                          children: ($$renderer7) => {
                            Search($$renderer7, { class: "size-4" });
                            $$renderer7.push(`<!----> Search`);
                          },
                          $$slots: { default: true }
                        });
                        $$renderer6.push(`<!----></div> `);
                        if (results.length === 0) {
                          $$renderer6.push("<!--[0-->");
                          $$renderer6.push(`<p class="text-sm text-muted-foreground">${escape_html(query ? "No applicants match that search." : "Search by name, reference number, or email.")}</p>`);
                        } else {
                          $$renderer6.push("<!--[-1-->");
                          $$renderer6.push(`<ul class="divide-y"><!--[-->`);
                          const each_array = ensure_array_like(results);
                          for (let $$index = 0, $$length = each_array.length; $$index < $$length; $$index++) {
                            let row = each_array[$$index];
                            $$renderer6.push(`<li class="flex items-center justify-between gap-3 py-3"><div class="min-w-0"><p class="font-medium">${escape_html(row.name)}</p> <p class="font-mono text-xs text-muted-foreground">${escape_html(row.reference_number)}</p></div> `);
                            Link($$renderer6, {
                              href: `/registrar/enrollment-eligibility/${row.id}`,
                              children: ($$renderer7) => {
                                Button($$renderer7, {
                                  variant: "outline",
                                  class: "min-h-10",
                                  children: ($$renderer8) => {
                                    $$renderer8.push(`<!---->View status`);
                                  },
                                  $$slots: { default: true }
                                });
                              },
                              $$slots: { default: true }
                            });
                            $$renderer6.push(`<!----></li>`);
                          }
                          $$renderer6.push(`<!--]--></ul>`);
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
          } else if (page_state === "card" && card) {
            $$renderer4.push("<!--[1-->");
            $$renderer4.push(`<div class="space-y-6"><div class="flex items-center justify-between">`);
            Link($$renderer4, {
              href: "/registrar/enrollment-eligibility",
              children: ($$renderer5) => {
                Button($$renderer5, {
                  variant: "ghost",
                  class: "-ml-3 min-h-9",
                  children: ($$renderer6) => {
                    $$renderer6.push(`<!---->← Back to search`);
                  },
                  $$slots: { default: true }
                });
              },
              $$slots: { default: true }
            });
            $$renderer4.push(`<!----> <div class="flex items-center gap-2">`);
            if (isClearanceConfirmed()) {
              $$renderer4.push("<!--[0-->");
              Badge($$renderer4, {
                class: "bg-emerald-600 hover:bg-emerald-700 text-white font-medium px-2.5 py-1 text-xs",
                children: ($$renderer5) => {
                  $$renderer5.push(`<!---->Admission Clearance Confirmed · ${escape_html(latestClearance()?.receipt_number)}`);
                },
                $$slots: { default: true }
              });
            } else if (isClearanceReady()) {
              $$renderer4.push("<!--[1-->");
              Badge($$renderer4, {
                class: "bg-blue-600 hover:bg-blue-700 text-white font-medium px-2.5 py-1 text-xs",
                children: ($$renderer5) => {
                  $$renderer5.push(`<!---->Ready for Admission Clearance`);
                },
                $$slots: { default: true }
              });
            } else {
              $$renderer4.push("<!--[-1-->");
              Badge($$renderer4, {
                variant: "secondary",
                class: "text-xs",
                children: ($$renderer5) => {
                  $$renderer5.push(`<!---->Prerequisites Incomplete`);
                },
                $$slots: { default: true }
              });
            }
            $$renderer4.push(`<!--]--></div></div> `);
            if (Card) {
              $$renderer4.push("<!--[-->");
              Card($$renderer4, {
                class: "border-primary/20 bg-muted/10 shadow-sm",
                children: ($$renderer5) => {
                  if (Card_content) {
                    $$renderer5.push("<!--[-->");
                    Card_content($$renderer5, {
                      class: "p-4 sm:p-5 flex flex-col md:flex-row md:items-center justify-between gap-4",
                      children: ($$renderer6) => {
                        $$renderer6.push(`<div class="space-y-1"><div class="flex items-center gap-2">`);
                        File_check_corner($$renderer6, { class: "size-5 text-primary" });
                        $$renderer6.push(`<!----> <h3 class="text-base font-semibold">Admission Clearance Workflow</h3></div> <p class="text-xs text-muted-foreground">`);
                        if (isClearanceConfirmed()) {
                          $$renderer6.push("<!--[0-->");
                          $$renderer6.push(`Cleared by ${escape_html(latestClearance()?.confirmed_by_name ?? "Registrar")} on ${escape_html(formatDate(latestClearance()?.confirmed_at))}. Program: <span class="font-medium text-foreground">${escape_html(latestClearance()?.program_code)}</span>.`);
                        } else if (isClearanceReady()) {
                          $$renderer6.push("<!--[1-->");
                          $$renderer6.push(`All admission milestones and mandatory intake documents are satisfied. Candidate is eligible for clearance confirmation.`);
                        } else {
                          $$renderer6.push("<!--[-1-->");
                          $$renderer6.push(`Clearance blocked: review missing prerequisites or unverified mandatory documents below.`);
                        }
                        $$renderer6.push(`<!--]--></p></div> <div class="flex flex-wrap items-center gap-2">`);
                        if (!isClearanceConfirmed()) {
                          $$renderer6.push("<!--[0-->");
                          Button($$renderer6, {
                            type: "button",
                            "data-tour": "admission-clearance-action",
                            class: "bg-emerald-600 hover:bg-emerald-700 text-white",
                            disabled: !isClearanceReady() || isSubmitting,
                            onclick: confirmClearance,
                            children: ($$renderer7) => {
                              Circle_check($$renderer7, { class: "size-4 mr-1.5" });
                              $$renderer7.push(`<!----> Confirm Admission Clearance`);
                            },
                            $$slots: { default: true }
                          });
                        } else {
                          $$renderer6.push("<!--[-1-->");
                          Button($$renderer6, {
                            type: "button",
                            variant: "outline",
                            class: "border-amber-300 text-amber-700 hover:bg-amber-50 dark:hover:bg-amber-950/30",
                            disabled: isSubmitting,
                            onclick: () => isRevokeDialogOpen = true,
                            children: ($$renderer7) => {
                              Rotate_ccw($$renderer7, { class: "size-4 mr-1.5" });
                              $$renderer7.push(`<!----> Revoke / Reopen Clearance`);
                            },
                            $$slots: { default: true }
                          });
                          $$renderer6.push(`<!----> `);
                          Button($$renderer6, {
                            type: "button",
                            "data-tour": "sheet-handoff-action",
                            class: "bg-primary text-primary-foreground",
                            disabled: isSubmitting,
                            onclick: () => isHandoffDialogOpen = true,
                            children: ($$renderer7) => {
                              Send($$renderer7, { class: "size-4 mr-1.5" });
                              $$renderer7.push(`<!----> Record Physical Sheet Handoff`);
                            },
                            $$slots: { default: true }
                          });
                          $$renderer6.push(`<!---->`);
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
            $$renderer4.push(` `);
            if (latestHandoff()) {
              $$renderer4.push("<!--[0-->");
              $$renderer4.push(`<div class="rounded-lg border border-emerald-300/80 bg-emerald-50/70 p-4 dark:border-emerald-800 dark:bg-emerald-950/20 text-emerald-900 dark:text-emerald-300 flex items-start gap-3">`);
              Circle_check($$renderer4, { class: "size-5 text-emerald-600 shrink-0 mt-0.5" });
              $$renderer4.push(`<!----> <div class="text-sm space-y-1"><p class="font-medium">Enrollment Sheet Handoff Recorded</p> <p class="text-xs text-emerald-800/80 dark:text-emerald-300/80">Receipt <span class="font-mono font-medium">${escape_html(latestHandoff().receipt_number)}</span> · Issued by ${escape_html(latestHandoff().issued_by_name)} on ${escape_html(formatDate(latestHandoff().issued_at))} `);
              if (latestHandoff().paper_reference) {
                $$renderer4.push("<!--[0-->");
                $$renderer4.push(`· Paper Ref: <span class="font-mono font-medium">${escape_html(latestHandoff().paper_reference)}</span>`);
              } else {
                $$renderer4.push("<!--[-1-->");
              }
              $$renderer4.push(`<!--]--></p></div></div>`);
            } else {
              $$renderer4.push("<!--[-1-->");
            }
            $$renderer4.push(`<!--]--> <div class="grid grid-cols-1 lg:grid-cols-12 gap-6"><div class="lg:col-span-6 space-y-6">`);
            if (Card) {
              $$renderer4.push("<!--[-->");
              Card($$renderer4, {
                class: card.eligible ? "overflow-hidden border-primary/30 shadow-sm" : "overflow-hidden shadow-sm",
                children: ($$renderer5) => {
                  if (Card_header) {
                    $$renderer5.push("<!--[-->");
                    Card_header($$renderer5, {
                      class: "border-b bg-muted/20 pb-3",
                      children: ($$renderer6) => {
                        $$renderer6.push(`<div class="flex flex-wrap items-center justify-between gap-2"><div>`);
                        if (Card_title) {
                          $$renderer6.push("<!--[-->");
                          Card_title($$renderer6, {
                            class: "text-lg",
                            children: ($$renderer7) => {
                              $$renderer7.push(`<!---->${escape_html(card.name)}`);
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
                            class: "font-mono text-xs",
                            children: ($$renderer7) => {
                              $$renderer7.push(`<!---->${escape_html(card.reference_number)}`);
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
                          variant: card.eligible ? "default" : "secondary",
                          children: ($$renderer7) => {
                            $$renderer7.push(`<!---->${escape_html(card.eligible ? "Milestones complete" : "Milestones pending")}`);
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
                      class: "space-y-4 pt-5",
                      children: ($$renderer6) => {
                        $$renderer6.push(`<div class="space-y-2" data-tour="pre-clearance-milestones"><p class="text-xs font-semibold uppercase tracking-wider text-muted-foreground">Pre-Clearance Milestones</p> <ul class="space-y-2.5"><!--[-->`);
                        const each_array_1 = ensure_array_like(milestones);
                        for (let $$index_1 = 0, $$length = each_array_1.length; $$index_1 < $$length; $$index_1++) {
                          let { key, label } = each_array_1[$$index_1];
                          $$renderer6.push(`<li class="flex items-center justify-between gap-3 text-sm"><span class="text-muted-foreground">${escape_html(label)}</span> `);
                          if (card.facts?.[key]) {
                            $$renderer6.push("<!--[0-->");
                            $$renderer6.push(`<span class="inline-flex items-center gap-1.5 font-medium">`);
                            Circle_check($$renderer6, { class: "size-4 text-emerald-600" });
                            $$renderer6.push(`<!----> ${escape_html(key === "result_released" && card.released_at ? formatDate(card.released_at) : "Done")}</span>`);
                          } else {
                            $$renderer6.push("<!--[-1-->");
                            $$renderer6.push(`<span class="inline-flex items-center gap-1.5 font-medium text-rose-600">`);
                            Circle_x($$renderer6, { class: "size-4" });
                            $$renderer6.push(`<!----> Missing</span>`);
                          }
                          $$renderer6.push(`<!--]--></li>`);
                        }
                        $$renderer6.push(`<!--]--></ul></div> <div class="border-t pt-4 space-y-2"><p class="text-xs font-semibold uppercase tracking-wider text-muted-foreground">Program &amp; Advising Choice</p> `);
                        if (card.selection) {
                          $$renderer6.push("<!--[0-->");
                          $$renderer6.push(`<div class="rounded-md bg-muted/40 p-3 text-xs space-y-1"><div class="flex justify-between"><span class="text-muted-foreground">Selected Program:</span> <span class="font-bold text-foreground">${escape_html(card.selection.program_code)}</span></div> <div class="flex justify-between"><span class="text-muted-foreground">Status / Source:</span> <span class="capitalize">${escape_html(card.selection.status)} (${escape_html(card.selection.source)})</span></div> <div class="flex justify-between"><span class="text-muted-foreground">Applicant Acknowledged:</span> <span${attr_class(clsx(card.selection.acknowledged ? "text-emerald-600 font-medium" : "text-amber-600"))}>${escape_html(card.selection.acknowledged ? "Yes" : "Pending")}</span></div></div>`);
                        } else {
                          $$renderer6.push("<!--[-1-->");
                          $$renderer6.push(`<p class="text-xs text-muted-foreground italic">No program selection recorded.</p>`);
                        }
                        $$renderer6.push(`<!--]--> `);
                        if (card.follow_ups && card.follow_ups.length > 0) {
                          $$renderer6.push("<!--[0-->");
                          $$renderer6.push(`<div class="rounded-md border border-amber-200 bg-amber-50/50 p-2.5 dark:border-amber-900/50 dark:bg-amber-950/20 text-xs"><p class="font-medium text-amber-800 dark:text-amber-400">Targeted Follow-ups Pending:</p> <ul class="list-disc list-inside mt-1 space-y-0.5 text-amber-700 dark:text-amber-300"><!--[-->`);
                          const each_array_2 = ensure_array_like(card.follow_ups);
                          for (let $$index_2 = 0, $$length = each_array_2.length; $$index_2 < $$length; $$index_2++) {
                            let fu = each_array_2[$$index_2];
                            $$renderer6.push(`<li>${escape_html(fu.program_code)}: ${escape_html(fu.status)}</li>`);
                          }
                          $$renderer6.push(`<!--]--></ul></div>`);
                        } else {
                          $$renderer6.push("<!--[-1-->");
                        }
                        $$renderer6.push(`<!--]--></div> `);
                        if (clearanceReadiness() && !clearanceReadiness().is_ready && clearanceReadiness().blocking_reasons?.length > 0) {
                          $$renderer6.push("<!--[0-->");
                          $$renderer6.push(`<div class="rounded-md border border-rose-200 bg-rose-50/60 p-3 text-xs dark:border-rose-900/50 dark:bg-rose-950/20"><p class="font-medium text-rose-800 dark:text-rose-400 flex items-center gap-1.5 mb-1.5">`);
                          Shield_alert($$renderer6, { class: "size-3.5 text-rose-600" });
                          $$renderer6.push(`<!----> Prerequisites Blocking Clearance</p> <ul class="list-disc list-inside space-y-1 text-rose-700 dark:text-rose-300"><!--[-->`);
                          const each_array_3 = ensure_array_like(clearanceReadiness().blocking_reasons);
                          for (let $$index_3 = 0, $$length = each_array_3.length; $$index_3 < $$length; $$index_3++) {
                            let reason = each_array_3[$$index_3];
                            $$renderer6.push(`<li>${escape_html(reason)}</li>`);
                          }
                          $$renderer6.push(`<!--]--></ul></div>`);
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
            if (Card) {
              $$renderer4.push("<!--[-->");
              Card($$renderer4, {
                class: "overflow-hidden shadow-sm border-border/60",
                children: ($$renderer5) => {
                  if (Card_header) {
                    $$renderer5.push("<!--[-->");
                    Card_header($$renderer5, {
                      class: "py-3 px-4 bg-muted/10 border-b",
                      children: ($$renderer6) => {
                        $$renderer6.push(`<div class="flex items-center gap-2">`);
                        History($$renderer6, { class: "size-4 text-muted-foreground" });
                        $$renderer6.push(`<!----> <span class="text-xs font-semibold text-muted-foreground uppercase tracking-wider">Legacy Enrollment Stamps</span></div>`);
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
                      class: "p-4 space-y-3 text-xs",
                      children: ($$renderer6) => {
                        if (card.enrollment_stamps.length > 0) {
                          $$renderer6.push("<!--[0-->");
                          $$renderer6.push(`<!--[-->`);
                          const each_array_4 = ensure_array_like(card.enrollment_stamps);
                          for (let $$index_4 = 0, $$length = each_array_4.length; $$index_4 < $$length; $$index_4++) {
                            let stamp = each_array_4[$$index_4];
                            $$renderer6.push(`<div class="rounded-md border bg-background p-3 space-y-2"><div class="flex flex-wrap items-center justify-between gap-x-3 gap-y-1"><p class="font-medium text-foreground capitalize">Version ${escape_html(stamp.version)}: ${escape_html(stamp.state.replaceAll("_", " "))}</p> <span class="text-muted-foreground">${escape_html(formatDate(stamp.created_at) ?? "Date unavailable")}</span></div> <div class="grid grid-cols-2 gap-x-4 gap-y-1 text-muted-foreground"><div>Provisional: <span class="font-medium text-foreground">${escape_html(stamp.provisional_program ?? "None")}</span></div> <div>Final Program: <span class="font-medium text-foreground">${escape_html(stamp.final_program ?? "None")}</span></div> <div>Paper Reference: <span class="font-medium text-foreground">${escape_html(stamp.paper_reference ?? "None")}</span></div></div> `);
                            if (stamp.reason) {
                              $$renderer6.push("<!--[0-->");
                              $$renderer6.push(`<p class="text-muted-foreground">Reason: <span class="text-foreground">${escape_html(stamp.reason)}</span></p>`);
                            } else {
                              $$renderer6.push("<!--[-1-->");
                            }
                            $$renderer6.push(`<!--]--></div>`);
                          }
                          $$renderer6.push(`<!--]-->`);
                        } else {
                          $$renderer6.push("<!--[-1-->");
                          $$renderer6.push(`<p class="text-muted-foreground italic">No historical legacy stamps recorded for this attempt.</p>`);
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
            $$renderer4.push(`</div> <div class="lg:col-span-6 space-y-6" data-tour="document-requirements-panel">`);
            DocumentRequirementsPanel($$renderer4, {
              attemptId: card.id,
              checklist: card.checklist,
              isClearanceConfirmed: isClearanceConfirmed()
            });
            $$renderer4.push(`<!----></div></div> <p class="text-xs text-muted-foreground">Status facts only. Assessment scores and counselor notes are not shown outside Guidance. Every lookup is recorded.</p></div>`);
          } else {
            $$renderer4.push("<!--[-1-->");
          }
          $$renderer4.push(`<!--]--></div>`);
        }
      });
      $$renderer3.push(`<!----> `);
      if (Dialog) {
        $$renderer3.push("<!--[-->");
        Dialog($$renderer3, {
          get open() {
            return isRevokeDialogOpen;
          },
          set open($$value) {
            isRevokeDialogOpen = $$value;
            $$settled = false;
          },
          children: ($$renderer4) => {
            if (Dialog_content) {
              $$renderer4.push("<!--[-->");
              Dialog_content($$renderer4, {
                class: "sm:max-w-md",
                children: ($$renderer5) => {
                  if (Dialog_header) {
                    $$renderer5.push("<!--[-->");
                    Dialog_header($$renderer5, {
                      children: ($$renderer6) => {
                        if (Dialog_title) {
                          $$renderer6.push("<!--[-->");
                          Dialog_title($$renderer6, {
                            children: ($$renderer7) => {
                              $$renderer7.push(`<!---->Revoke / Reopen Admission Clearance`);
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
                            children: ($$renderer7) => {
                              $$renderer7.push(`<!---->Provide a mandatory audit reason for revoking confirmed clearance. This will reopen prerequisite edits and document verification.`);
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
                  $$renderer5.push(` <div class="space-y-4 py-2"><div class="space-y-2"><label for="revoke-reason" class="text-sm font-medium">Revocation Reason</label> `);
                  Textarea($$renderer5, {
                    id: "revoke-reason",
                    placeholder: "State reason (e.g. deficient document found, applicant program adjustment authorized)...",
                    rows: 3,
                    get value() {
                      return revokeReason;
                    },
                    set value($$value) {
                      revokeReason = $$value;
                      $$settled = false;
                    }
                  });
                  $$renderer5.push(`<!----></div></div> `);
                  if (Dialog_footer) {
                    $$renderer5.push("<!--[-->");
                    Dialog_footer($$renderer5, {
                      class: "gap-2 sm:gap-0",
                      children: ($$renderer6) => {
                        Button($$renderer6, {
                          type: "button",
                          variant: "outline",
                          onclick: () => {
                            isRevokeDialogOpen = false;
                            revokeReason = "";
                          },
                          children: ($$renderer7) => {
                            $$renderer7.push(`<!---->Cancel`);
                          },
                          $$slots: { default: true }
                        });
                        $$renderer6.push(`<!----> `);
                        Button($$renderer6, {
                          type: "button",
                          variant: "destructive",
                          disabled: !revokeReason.trim() || isSubmitting,
                          onclick: submitRevocation,
                          children: ($$renderer7) => {
                            $$renderer7.push(`<!---->Confirm Revocation`);
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
      $$renderer3.push(` `);
      if (Dialog) {
        $$renderer3.push("<!--[-->");
        Dialog($$renderer3, {
          get open() {
            return isHandoffDialogOpen;
          },
          set open($$value) {
            isHandoffDialogOpen = $$value;
            $$settled = false;
          },
          children: ($$renderer4) => {
            if (Dialog_content) {
              $$renderer4.push("<!--[-->");
              Dialog_content($$renderer4, {
                class: "sm:max-w-md",
                children: ($$renderer5) => {
                  if (Dialog_header) {
                    $$renderer5.push("<!--[-->");
                    Dialog_header($$renderer5, {
                      children: ($$renderer6) => {
                        if (Dialog_title) {
                          $$renderer6.push("<!--[-->");
                          Dialog_title($$renderer6, {
                            children: ($$renderer7) => {
                              $$renderer7.push(`<!---->Record Physical Enrollment Sheet Handoff`);
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
                            children: ($$renderer7) => {
                              $$renderer7.push(`<!---->Confirm that the official printed enrollment sheet has been physically handed to the applicant at the Registrar desk.`);
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
                  $$renderer5.push(` <div class="space-y-4 py-2"><div class="space-y-2"><label for="paper-ref" class="text-sm font-medium">Paper Reference / Serial (Optional)</label> `);
                  Input($$renderer5, {
                    id: "paper-ref",
                    placeholder: "e.g. ESH-2026-00123",
                    get value() {
                      return handoffPaperReference;
                    },
                    set value($$value) {
                      handoffPaperReference = $$value;
                      $$settled = false;
                    }
                  });
                  $$renderer5.push(`<!----></div></div> `);
                  if (Dialog_footer) {
                    $$renderer5.push("<!--[-->");
                    Dialog_footer($$renderer5, {
                      class: "gap-2 sm:gap-0",
                      children: ($$renderer6) => {
                        Button($$renderer6, {
                          type: "button",
                          variant: "outline",
                          onclick: () => {
                            isHandoffDialogOpen = false;
                            handoffPaperReference = "";
                          },
                          children: ($$renderer7) => {
                            $$renderer7.push(`<!---->Cancel`);
                          },
                          $$slots: { default: true }
                        });
                        $$renderer6.push(`<!----> `);
                        Button($$renderer6, {
                          type: "button",
                          class: "bg-primary text-primary-foreground",
                          disabled: isSubmitting,
                          onclick: submitSheetHandoff,
                          children: ($$renderer7) => {
                            $$renderer7.push(`<!---->Record Handoff`);
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
  EnrollmentEligibility as default
};
