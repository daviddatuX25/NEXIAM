import { g as sanitize_props, i as spread_props, j as slot, h as head, e as escape_html, a as ensure_array_like, B as Button, c as attr_class, d as derived, n as newIdempotencyKey } from "../app.js";
import { S as StaffLayout } from "./StaffLayout-CR-MZopa.js";
import { P as PageHeaderBar } from "./PageHeaderBar-DJhT5vOR.js";
import E2PageState from "./E2PageState-yFavAmgl.js";
import { K as KpiCard } from "./KpiCard-uSVmkmNZ.js";
import { B as Badge } from "./badge-D9Vgs7xh.js";
import { C as Card, a as Card_content } from "./card-content-Brg2wRkV.js";
import { C as Card_description } from "./card-description-BGy_v5-d.js";
import { C as Card_header, a as Card_title } from "./card-title-BNY2ZPje.js";
import { D as Dialog, a as Dialog_content, b as Dialog_header, c as Dialog_title, d as Dialog_description } from "./dialog-description-D12L0QA9.js";
import { D as Dialog_footer } from "./dialog-footer-kNnwKT2h.js";
import { T as Table, a as Table_header, b as Table_row, c as Table_head, d as Table_body, e as Table_cell } from "./table-row-_ffWKrps.js";
import { T as Tabs, a as Tabs_list, b as Tabs_trigger, c as Tabs_content } from "./tabs-trigger-DxnTaeSp.js";
import { L as Label } from "./label-dGY6L9vm.js";
import { T as Textarea } from "./textarea-Bv3Na1wO.js";
import { u as useForm } from "./useForm.svelte-C0OkDFGu.js";
import { U as User_check } from "./user-check-Dl-wciTR.js";
import { C as Clipboard_list } from "./clipboard-list-YgthURdB.js";
import { L as Link } from "./Link-B7ks7rBK.js";
import { I as Icon } from "./Icon-Dal7PqKK.js";
import { C as Circle_check } from "./circle-check-C59ZS7Zv.js";
import "axios";
import "@inertiajs/core";
import "clsx";
import "laravel-precognition";
import "tailwind-merge";
import "tailwind-variants";
import "./ToastManager-C7oO_4Jn.js";
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
import "./circle-alert-jWXJ7oC0.js";
import "./refresh-cw-CYdk8aE0.js";
import "./camera-C09Eyogi.js";
import "./settings-C5xOKpV3.js";
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
import "./users-DWlguTa_.js";
import "./user-round-Kft0Cy_Y.js";
import "./XiamNpcDialog-DoqCSHj9.js";
/* empty css                                                        */
import "./circle-question-mark-DY-RU614.js";
import "./sparkles-DbQIaVG6.js";
import "./chevron-left-DCSOZwZl.js";
import "./shield-alert-CXtDdte9.js";
import "./minus-Dqkhcxt7.js";
import "./roving-focus-group-BJDH9kUk.js";
import "es-toolkit";
import "./clone-BJz-lxsY.js";
import "es-toolkit/compat";
function Message_square_warning($$renderer, $$props) {
  const $$sanitized_props = sanitize_props($$props);
  const iconNode = [
    [
      "path",
      {
        "d": "M22 17a2 2 0 0 1-2 2H6.828a2 2 0 0 0-1.414.586l-2.202 2.202A.71.71 0 0 1 2 21.286V5a2 2 0 0 1 2-2h16a2 2 0 0 1 2 2z"
      }
    ],
    ["path", { "d": "M12 15h.01" }],
    ["path", { "d": "M12 7v4" }]
  ];
  Icon($$renderer, spread_props([
    { name: "message-square-warning" },
    $$sanitized_props,
    {
      /**
       * @component @name MessageSquareWarning
       * @description Lucide SVG icon component, renders SVG Element with children.
       *
       * @preview ![img](data:image/svg+xml;base64,PHN2ZyAgeG1sbnM9Imh0dHA6Ly93d3cudzMub3JnLzIwMDAvc3ZnIgogIHdpZHRoPSIyNCIKICBoZWlnaHQ9IjI0IgogIHZpZXdCb3g9IjAgMCAyNCAyNCIKICBmaWxsPSJub25lIgogIHN0cm9rZT0iIzAwMCIgc3R5bGU9ImJhY2tncm91bmQtY29sb3I6ICNmZmY7IGJvcmRlci1yYWRpdXM6IDJweCIKICBzdHJva2Utd2lkdGg9IjIiCiAgc3Ryb2tlLWxpbmVjYXA9InJvdW5kIgogIHN0cm9rZS1saW5lam9pbj0icm91bmQiCj4KICA8cGF0aCBkPSJNMjIgMTdhMiAyIDAgMCAxLTIgMkg2LjgyOGEyIDIgMCAwIDAtMS40MTQuNTg2bC0yLjIwMiAyLjIwMkEuNzEuNzEgMCAwIDEgMiAyMS4yODZWNWEyIDIgMCAwIDEgMi0yaDE2YTIgMiAwIDAgMSAyIDJ6IiAvPgogIDxwYXRoIGQ9Ik0xMiAxNWguMDEiIC8+CiAgPHBhdGggZD0iTTEyIDd2NCIgLz4KPC9zdmc+Cg==) - https://lucide.dev/icons/message-square-warning
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
function ProgramShow($$renderer, $$props) {
  $$renderer.component(($$renderer2) => {
    let {
      page_state = "denied",
      dashboard = null,
      lists = null,
      setup_links = {}
    } = $$props;
    const programCode = derived(() => dashboard?.program?.code ?? "");
    const cycle = derived(() => dashboard?.cycle ?? lists?.cycle ?? null);
    const metrics = derived(() => dashboard?.metrics ?? null);
    const quota = derived(() => metrics()?.quota ?? null);
    const breadcrumbs = derived(() => {
      const crumbs = [
        { label: "My programs", href: "/staff/programs" },
        { label: dashboard?.program?.code ?? "Program" }
      ];
      if (dashboard?.program?.name) {
        crumbs[1] = { label: dashboard.program.name };
      }
      return crumbs;
    });
    const resolveForm = useForm({ outcome: "", reason: "", idempotency_key: "" });
    let resolveDialogOpen = false;
    let activeFollowUp = null;
    const resolveOutcomes = [
      {
        value: "cleared_by_ph",
        label: "Cleared by program head",
        description: "Marks the applicant advised and ready for Registrar Enrollment."
      },
      {
        value: "declined_by_ph",
        label: "Declined by program head",
        description: "Returns the applicant to Guidance for alternative program placement."
      },
      {
        value: "appearance_requested",
        label: "Appearance requested",
        description: "Ask the applicant to visit the program office."
      }
    ];
    function openResolve(row) {
      activeFollowUp = row;
      resolveForm.clearErrors();
      resolveForm.outcome = "cleared_by_ph";
      resolveForm.reason = "";
      resolveForm.idempotency_key = newIdempotencyKey();
      resolveDialogOpen = true;
    }
    function submitResolve() {
      if (!activeFollowUp) return;
      const url = typeof route === "function" ? route("program-head.follow-ups.resolve", { program: programCode(), followUp: activeFollowUp.id }) : `/staff/programs/${programCode()}/follow-ups/${activeFollowUp.id}/resolve`;
      resolveForm.post(url, {
        preserveScroll: true,
        onSuccess: () => {
          resolveDialogOpen = false;
          activeFollowUp = null;
        }
      });
    }
    const resolveReasonRequired = derived(() => resolveForm.outcome !== "appearance_requested" && !resolveForm.reason.trim());
    let endorseDialogOpen = false;
    let endorseNotes = "";
    const endorseForm = useForm({});
    const stageLabels = {
      potential: "Potential",
      upcoming: "Upcoming",
      selected: "Selected",
      selection_pending_ack: "Selection pending ack",
      advised: "Advised",
      result_released: "Result released",
      accepted: "Accepted",
      enrolling: "Enrolling",
      enrolled: "Enrolled"
    };
    function stageLabel(stage) {
      return stageLabels[stage] ?? stage ?? "—";
    }
    function stageVariant(stage) {
      if (stage === "enrolled" || stage === "enrolling" || stage === "selected" || stage === "upcoming") return "default";
      if (stage === "selection_pending_ack" || stage === "result_released") return "outline";
      return "secondary";
    }
    const followUpStatusLabels = {
      open: "Open",
      appearance_requested: "Appearance requested",
      cleared_by_ph: "Cleared by program head",
      declined_by_ph: "Declined by program head"
    };
    function followUpStatusLabel(status) {
      return followUpStatusLabels[status] ?? status ?? "—";
    }
    function followUpStatusVariant(status) {
      if (status === "open") return "default";
      if (status === "appearance_requested") return "outline";
      if (status === "declined_by_ph") return "destructive";
      return "secondary";
    }
    function applicantUrl(attemptId) {
      return typeof route === "function" ? route("program-head.applicants.show", { program: programCode(), attempt: attemptId }) : `/staff/programs/${programCode()}/applicants/${attemptId}`;
    }
    function formatDateTime(iso) {
      if (!iso) return "—";
      const d = new Date(iso);
      if (isNaN(d.getTime())) return iso;
      return d.toLocaleString(void 0, {
        year: "numeric",
        month: "short",
        day: "numeric",
        hour: "numeric",
        minute: "2-digit"
      });
    }
    function formatScore(value) {
      if (value === null || value === void 0 || value === "") return "—";
      const numeric = Number(value);
      return Number.isNaN(numeric) ? String(value) : numeric.toLocaleString(void 0, { maximumFractionDigits: 1 });
    }
    function scoreEntries(row) {
      const series = row.score_profile?.series ?? row.score_summary?.series ?? row.exam_scores ?? row.subtests ?? row.scores ?? [];
      if (Array.isArray(series)) return series;
      if (series && typeof series === "object") {
        return Object.entries(series).map(([area, score]) => ({
          area_code: area,
          area_name: area,
          score: typeof score === "object" ? score.score : score,
          cutoff: typeof score === "object" ? score.cutoff : null
        }));
      }
      return [];
    }
    function scoreCutoff(row, score) {
      return score.cutoff ?? score.program_cutoff ?? row.program_cutoffs?.[score.area_code] ?? row.cutoffs?.[score.area_code] ?? null;
    }
    const metricCards = derived(() => {
      if (!metrics()) return [];
      return [
        {
          label: "Preference candidates",
          value: metrics().preference_candidates ?? 0
        },
        {
          label: "Accepted candidates",
          value: metrics().accepted_candidates ?? 0
        },
        {
          label: "Exam completed",
          value: metrics().exam_completed_candidates ?? 0
        },
        {
          label: "Result released",
          value: metrics().result_released_candidates ?? 0
        },
        { label: "Advised", value: metrics().advised_candidates ?? 0 },
        {
          label: "Guidance pool",
          value: metrics().guidance_pool_candidates ?? 0
        },
        {
          label: "Active reservations",
          value: metrics().active_reservations ?? 0
        },
        {
          label: "Open follow-ups",
          value: metrics().open_follow_ups ?? 0
        },
        { label: "Enrolling", value: metrics().enrolling ?? 0 },
        { label: "Enrolled", value: metrics().enrolled ?? 0 }
      ];
    });
    let activeTab = "potential";
    const tabCount = (key) => lists ? lists[key]?.length ?? 0 : 0;
    let $$settled = true;
    let $$inner_renderer;
    function $$render_inner($$renderer3) {
      head("15hyevl", $$renderer3, ($$renderer4) => {
        $$renderer4.title(($$renderer5) => {
          $$renderer5.push(`<title>Program workspace</title>`);
        });
      });
      StaffLayout($$renderer3, {
        breadcrumbs: breadcrumbs(),
        children: ($$renderer4) => {
          $$renderer4.push(`<div class="min-w-0 space-y-6 p-4 sm:p-6">`);
          if (page_state !== "ready") {
            $$renderer4.push("<!--[0-->");
            E2PageState($$renderer4, { state: page_state });
          } else if (dashboard && lists) {
            $$renderer4.push("<!--[1-->");
            {
              let actions = function($$renderer5) {
                if (setup_links.studio) {
                  $$renderer5.push("<!--[0-->");
                  Link($$renderer5, {
                    href: setup_links.studio,
                    class: "inline-flex min-h-9 items-center rounded-md border border-input px-3 text-sm font-medium hover:bg-accent focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring",
                    children: ($$renderer6) => {
                      $$renderer6.push(`<!---->Benchmark Studio`);
                    },
                    $$slots: { default: true }
                  });
                } else {
                  $$renderer5.push("<!--[-1-->");
                }
                $$renderer5.push(`<!--]--> `);
                if (setup_links.quotas) {
                  $$renderer5.push("<!--[0-->");
                  Link($$renderer5, {
                    href: setup_links.quotas,
                    class: "inline-flex min-h-9 items-center rounded-md border border-input px-3 text-sm font-medium hover:bg-accent focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring",
                    children: ($$renderer6) => {
                      $$renderer6.push(`<!---->Quotas`);
                    },
                    $$slots: { default: true }
                  });
                } else {
                  $$renderer5.push("<!--[-1-->");
                }
                $$renderer5.push(`<!--]-->`);
              };
              PageHeaderBar($$renderer4, {
                title: dashboard.program.name,
                description: `Program ${dashboard.program.code}${cycle() ? ` · cycle ${cycle()}` : ""} — pipeline and follow-up workspace.`,
                actions
              });
            }
            $$renderer4.push(`<!----> `);
            if (resolveForm.errors?.follow_up || resolveForm.errors?.outcome || resolveForm.errors?.reason) {
              $$renderer4.push("<!--[0-->");
              $$renderer4.push(`<div class="border-destructive/40 bg-destructive/10 text-destructive rounded-md border px-4 py-3 text-sm">${escape_html(resolveForm.errors.follow_up ?? resolveForm.errors.outcome ?? resolveForm.errors.reason)}</div>`);
            } else {
              $$renderer4.push("<!--[-1-->");
            }
            $$renderer4.push(`<!--]--> <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-5"><!--[-->`);
            const each_array = ensure_array_like(metricCards());
            for (let $$index = 0, $$length = each_array.length; $$index < $$length; $$index++) {
              let card = each_array[$$index];
              KpiCard($$renderer4, { label: card.label, value: card.value });
            }
            $$renderer4.push(`<!--]--></div> `);
            if (quota()) {
              $$renderer4.push("<!--[0-->");
              if (Card) {
                $$renderer4.push("<!--[-->");
                Card($$renderer4, {
                  children: ($$renderer5) => {
                    if (Card_header) {
                      $$renderer5.push("<!--[-->");
                      Card_header($$renderer5, {
                        class: "pb-3",
                        children: ($$renderer6) => {
                          $$renderer6.push(`<div class="flex flex-wrap items-center justify-between gap-2"><div>`);
                          if (Card_title) {
                            $$renderer6.push("<!--[-->");
                            Card_title($$renderer6, {
                              class: "text-lg",
                              children: ($$renderer7) => {
                                $$renderer7.push(`<!---->Quota`);
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
                                $$renderer7.push(`<!---->${escape_html(cycle() ? `Cycle ${cycle()}` : "No active cycle")}`);
                              },
                              $$slots: { default: true }
                            });
                            $$renderer6.push("<!--]-->");
                          } else {
                            $$renderer6.push("<!--[!-->");
                            $$renderer6.push("<!--]-->");
                          }
                          $$renderer6.push(`</div> `);
                          if (quota().over_capacity > 0) {
                            $$renderer6.push("<!--[0-->");
                            Badge($$renderer6, {
                              variant: "destructive",
                              children: ($$renderer7) => {
                                $$renderer7.push(`<!---->Over capacity by ${escape_html(quota().over_capacity)}`);
                              },
                              $$slots: { default: true }
                            });
                          } else if (!quota().configured) {
                            $$renderer6.push("<!--[1-->");
                            Badge($$renderer6, {
                              variant: "secondary",
                              children: ($$renderer7) => {
                                $$renderer7.push(`<!---->Not configured`);
                              },
                              $$slots: { default: true }
                            });
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
                    $$renderer5.push(` `);
                    if (Card_content) {
                      $$renderer5.push("<!--[-->");
                      Card_content($$renderer5, {
                        class: "space-y-2 pt-4 text-sm",
                        children: ($$renderer6) => {
                          $$renderer6.push(`<div class="flex items-center justify-between gap-3"><span class="text-muted-foreground">Configured</span> `);
                          if (quota().configured) {
                            $$renderer6.push("<!--[0-->");
                            $$renderer6.push(`<span class="inline-flex items-center gap-1.5 font-medium text-foreground">`);
                            User_check($$renderer6, { class: "size-4 text-primary" });
                            $$renderer6.push(`<!----> Yes</span>`);
                          } else {
                            $$renderer6.push("<!--[-1-->");
                            $$renderer6.push(`<span class="inline-flex items-center gap-1.5 font-medium text-muted-foreground">`);
                            Clipboard_list($$renderer6, { class: "size-4" });
                            $$renderer6.push(`<!----> No</span>`);
                          }
                          $$renderer6.push(`<!--]--></div> <div class="flex items-center justify-between gap-3"><span class="text-muted-foreground">Capacity</span> <span class="tabular-nums font-semibold text-foreground">${escape_html(quota().capacity ?? "—")}</span></div> <div class="flex items-center justify-between gap-3"><span class="text-muted-foreground">Committed</span> <span class="tabular-nums font-semibold text-foreground">${escape_html(quota().committed ?? 0)}</span></div> <div class="flex items-center justify-between gap-3"><span class="text-muted-foreground">Remaining</span> <span class="tabular-nums font-semibold text-foreground">${escape_html(quota().remaining ?? 0)}</span></div>`);
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
            if (Tabs) {
              $$renderer4.push("<!--[-->");
              Tabs($$renderer4, {
                get value() {
                  return activeTab;
                },
                set value($$value) {
                  activeTab = $$value;
                  $$settled = false;
                },
                children: ($$renderer5) => {
                  if (Tabs_list) {
                    $$renderer5.push("<!--[-->");
                    Tabs_list($$renderer5, {
                      variant: "line",
                      children: ($$renderer6) => {
                        if (Tabs_trigger) {
                          $$renderer6.push("<!--[-->");
                          Tabs_trigger($$renderer6, {
                            value: "potential",
                            children: ($$renderer7) => {
                              $$renderer7.push(`<!---->Potential (${escape_html(tabCount("potential"))})`);
                            },
                            $$slots: { default: true }
                          });
                          $$renderer6.push("<!--]-->");
                        } else {
                          $$renderer6.push("<!--[!-->");
                          $$renderer6.push("<!--]-->");
                        }
                        $$renderer6.push(` `);
                        if (Tabs_trigger) {
                          $$renderer6.push("<!--[-->");
                          Tabs_trigger($$renderer6, {
                            value: "upcoming",
                            children: ($$renderer7) => {
                              $$renderer7.push(`<!---->Upcoming (${escape_html(tabCount("upcoming"))})`);
                            },
                            $$slots: { default: true }
                          });
                          $$renderer6.push("<!--]-->");
                        } else {
                          $$renderer6.push("<!--[!-->");
                          $$renderer6.push("<!--]-->");
                        }
                        $$renderer6.push(` `);
                        if (Tabs_trigger) {
                          $$renderer6.push("<!--[-->");
                          Tabs_trigger($$renderer6, {
                            value: "follow_ups",
                            children: ($$renderer7) => {
                              $$renderer7.push(`<!---->Follow-up (${escape_html(tabCount("follow_ups"))})`);
                            },
                            $$slots: { default: true }
                          });
                          $$renderer6.push("<!--]-->");
                        } else {
                          $$renderer6.push("<!--[!-->");
                          $$renderer6.push("<!--]-->");
                        }
                        $$renderer6.push(` `);
                        if (Tabs_trigger) {
                          $$renderer6.push("<!--[-->");
                          Tabs_trigger($$renderer6, {
                            value: "enrolling",
                            children: ($$renderer7) => {
                              $$renderer7.push(`<!---->Enrolling (${escape_html(tabCount("enrolling"))})`);
                            },
                            $$slots: { default: true }
                          });
                          $$renderer6.push("<!--]-->");
                        } else {
                          $$renderer6.push("<!--[!-->");
                          $$renderer6.push("<!--]-->");
                        }
                        $$renderer6.push(` `);
                        if (Tabs_trigger) {
                          $$renderer6.push("<!--[-->");
                          Tabs_trigger($$renderer6, {
                            value: "enrolled",
                            children: ($$renderer7) => {
                              $$renderer7.push(`<!---->Enrolled (${escape_html(tabCount("enrolled"))})`);
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
                  if (Tabs_content) {
                    $$renderer5.push("<!--[-->");
                    Tabs_content($$renderer5, {
                      value: "potential",
                      class: "pt-4",
                      children: ($$renderer6) => {
                        if (lists.potential.length === 0) {
                          $$renderer6.push("<!--[0-->");
                          $$renderer6.push(`<p class="text-muted-foreground py-10 text-center">No potential candidates for this program.</p>`);
                        } else {
                          $$renderer6.push("<!--[-1-->");
                          if (Table) {
                            $$renderer6.push("<!--[-->");
                            Table($$renderer6, {
                              class: "w-full min-w-[720px] text-sm",
                              children: ($$renderer7) => {
                                if (Table_header) {
                                  $$renderer7.push("<!--[-->");
                                  Table_header($$renderer7, {
                                    children: ($$renderer8) => {
                                      if (Table_row) {
                                        $$renderer8.push("<!--[-->");
                                        Table_row($$renderer8, {
                                          children: ($$renderer9) => {
                                            if (Table_head) {
                                              $$renderer9.push("<!--[-->");
                                              Table_head($$renderer9, {
                                                children: ($$renderer10) => {
                                                  $$renderer10.push(`<!---->Reference`);
                                                },
                                                $$slots: { default: true }
                                              });
                                              $$renderer9.push("<!--]-->");
                                            } else {
                                              $$renderer9.push("<!--[!-->");
                                              $$renderer9.push("<!--]-->");
                                            }
                                            $$renderer9.push(` `);
                                            if (Table_head) {
                                              $$renderer9.push("<!--[-->");
                                              Table_head($$renderer9, {
                                                children: ($$renderer10) => {
                                                  $$renderer10.push(`<!---->Name`);
                                                },
                                                $$slots: { default: true }
                                              });
                                              $$renderer9.push("<!--]-->");
                                            } else {
                                              $$renderer9.push("<!--[!-->");
                                              $$renderer9.push("<!--]-->");
                                            }
                                            $$renderer9.push(` `);
                                            if (Table_head) {
                                              $$renderer9.push("<!--[-->");
                                              Table_head($$renderer9, {
                                                children: ($$renderer10) => {
                                                  $$renderer10.push(`<!---->Basis`);
                                                },
                                                $$slots: { default: true }
                                              });
                                              $$renderer9.push("<!--]-->");
                                            } else {
                                              $$renderer9.push("<!--[!-->");
                                              $$renderer9.push("<!--]-->");
                                            }
                                            $$renderer9.push(` `);
                                            if (Table_head) {
                                              $$renderer9.push("<!--[-->");
                                              Table_head($$renderer9, {
                                                children: ($$renderer10) => {
                                                  $$renderer10.push(`<!---->Stage`);
                                                },
                                                $$slots: { default: true }
                                              });
                                              $$renderer9.push("<!--]-->");
                                            } else {
                                              $$renderer9.push("<!--[!-->");
                                              $$renderer9.push("<!--]-->");
                                            }
                                          },
                                          $$slots: { default: true }
                                        });
                                        $$renderer8.push("<!--]-->");
                                      } else {
                                        $$renderer8.push("<!--[!-->");
                                        $$renderer8.push("<!--]-->");
                                      }
                                    },
                                    $$slots: { default: true }
                                  });
                                  $$renderer7.push("<!--]-->");
                                } else {
                                  $$renderer7.push("<!--[!-->");
                                  $$renderer7.push("<!--]-->");
                                }
                                $$renderer7.push(` `);
                                if (Table_body) {
                                  $$renderer7.push("<!--[-->");
                                  Table_body($$renderer7, {
                                    children: ($$renderer8) => {
                                      $$renderer8.push(`<!--[-->`);
                                      const each_array_1 = ensure_array_like(lists.potential);
                                      for (let $$index_2 = 0, $$length = each_array_1.length; $$index_2 < $$length; $$index_2++) {
                                        let row = each_array_1[$$index_2];
                                        if (Table_row) {
                                          $$renderer8.push("<!--[-->");
                                          Table_row($$renderer8, {
                                            children: ($$renderer9) => {
                                              if (Table_cell) {
                                                $$renderer9.push("<!--[-->");
                                                Table_cell($$renderer9, {
                                                  class: "font-mono text-xs",
                                                  children: ($$renderer10) => {
                                                    $$renderer10.push(`<!---->${escape_html(row.reference_code)}`);
                                                  },
                                                  $$slots: { default: true }
                                                });
                                                $$renderer9.push("<!--]-->");
                                              } else {
                                                $$renderer9.push("<!--[!-->");
                                                $$renderer9.push("<!--]-->");
                                              }
                                              $$renderer9.push(` `);
                                              if (Table_cell) {
                                                $$renderer9.push("<!--[-->");
                                                Table_cell($$renderer9, {
                                                  class: "font-medium",
                                                  children: ($$renderer10) => {
                                                    $$renderer10.push(`<!---->${escape_html(row.name)}`);
                                                  },
                                                  $$slots: { default: true }
                                                });
                                                $$renderer9.push("<!--]-->");
                                              } else {
                                                $$renderer9.push("<!--[!-->");
                                                $$renderer9.push("<!--]-->");
                                              }
                                              $$renderer9.push(` `);
                                              if (Table_cell) {
                                                $$renderer9.push("<!--[-->");
                                                Table_cell($$renderer9, {
                                                  children: ($$renderer10) => {
                                                    $$renderer10.push(`<div class="flex flex-wrap gap-1">`);
                                                    if ((row.basis ?? []).length === 0) {
                                                      $$renderer10.push("<!--[0-->");
                                                      $$renderer10.push(`<span class="text-muted-foreground">—</span>`);
                                                    } else {
                                                      $$renderer10.push("<!--[-1-->");
                                                      $$renderer10.push(`<!--[-->`);
                                                      const each_array_2 = ensure_array_like(row.basis);
                                                      for (let $$index_1 = 0, $$length2 = each_array_2.length; $$index_1 < $$length2; $$index_1++) {
                                                        let basis = each_array_2[$$index_1];
                                                        Badge($$renderer10, {
                                                          variant: "outline",
                                                          children: ($$renderer11) => {
                                                            $$renderer11.push(`<!---->${escape_html(basis)}`);
                                                          },
                                                          $$slots: { default: true }
                                                        });
                                                      }
                                                      $$renderer10.push(`<!--]-->`);
                                                    }
                                                    $$renderer10.push(`<!--]--></div>`);
                                                  },
                                                  $$slots: { default: true }
                                                });
                                                $$renderer9.push("<!--]-->");
                                              } else {
                                                $$renderer9.push("<!--[!-->");
                                                $$renderer9.push("<!--]-->");
                                              }
                                              $$renderer9.push(` `);
                                              if (Table_cell) {
                                                $$renderer9.push("<!--[-->");
                                                Table_cell($$renderer9, {
                                                  children: ($$renderer10) => {
                                                    Badge($$renderer10, {
                                                      variant: stageVariant(row.stage),
                                                      children: ($$renderer11) => {
                                                        $$renderer11.push(`<!---->${escape_html(stageLabel(row.stage))}`);
                                                      },
                                                      $$slots: { default: true }
                                                    });
                                                  },
                                                  $$slots: { default: true }
                                                });
                                                $$renderer9.push("<!--]-->");
                                              } else {
                                                $$renderer9.push("<!--[!-->");
                                                $$renderer9.push("<!--]-->");
                                              }
                                            },
                                            $$slots: { default: true }
                                          });
                                          $$renderer8.push("<!--]-->");
                                        } else {
                                          $$renderer8.push("<!--[!-->");
                                          $$renderer8.push("<!--]-->");
                                        }
                                      }
                                      $$renderer8.push(`<!--]-->`);
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
                  $$renderer5.push(` `);
                  if (Tabs_content) {
                    $$renderer5.push("<!--[-->");
                    Tabs_content($$renderer5, {
                      value: "upcoming",
                      class: "pt-4",
                      children: ($$renderer6) => {
                        if (lists.upcoming.length === 0) {
                          $$renderer6.push("<!--[0-->");
                          $$renderer6.push(`<p class="text-muted-foreground py-10 text-center">No upcoming candidates for this program.</p>`);
                        } else {
                          $$renderer6.push("<!--[-1-->");
                          if (Table) {
                            $$renderer6.push("<!--[-->");
                            Table($$renderer6, {
                              class: "w-full min-w-[720px] text-sm",
                              children: ($$renderer7) => {
                                if (Table_header) {
                                  $$renderer7.push("<!--[-->");
                                  Table_header($$renderer7, {
                                    children: ($$renderer8) => {
                                      if (Table_row) {
                                        $$renderer8.push("<!--[-->");
                                        Table_row($$renderer8, {
                                          children: ($$renderer9) => {
                                            if (Table_head) {
                                              $$renderer9.push("<!--[-->");
                                              Table_head($$renderer9, {
                                                children: ($$renderer10) => {
                                                  $$renderer10.push(`<!---->Reference`);
                                                },
                                                $$slots: { default: true }
                                              });
                                              $$renderer9.push("<!--]-->");
                                            } else {
                                              $$renderer9.push("<!--[!-->");
                                              $$renderer9.push("<!--]-->");
                                            }
                                            $$renderer9.push(` `);
                                            if (Table_head) {
                                              $$renderer9.push("<!--[-->");
                                              Table_head($$renderer9, {
                                                children: ($$renderer10) => {
                                                  $$renderer10.push(`<!---->Name`);
                                                },
                                                $$slots: { default: true }
                                              });
                                              $$renderer9.push("<!--]-->");
                                            } else {
                                              $$renderer9.push("<!--[!-->");
                                              $$renderer9.push("<!--]-->");
                                            }
                                            $$renderer9.push(` `);
                                            if (Table_head) {
                                              $$renderer9.push("<!--[-->");
                                              Table_head($$renderer9, {
                                                children: ($$renderer10) => {
                                                  $$renderer10.push(`<!---->Basis`);
                                                },
                                                $$slots: { default: true }
                                              });
                                              $$renderer9.push("<!--]-->");
                                            } else {
                                              $$renderer9.push("<!--[!-->");
                                              $$renderer9.push("<!--]-->");
                                            }
                                            $$renderer9.push(` `);
                                            if (Table_head) {
                                              $$renderer9.push("<!--[-->");
                                              Table_head($$renderer9, {
                                                children: ($$renderer10) => {
                                                  $$renderer10.push(`<!---->Stage`);
                                                },
                                                $$slots: { default: true }
                                              });
                                              $$renderer9.push("<!--]-->");
                                            } else {
                                              $$renderer9.push("<!--[!-->");
                                              $$renderer9.push("<!--]-->");
                                            }
                                          },
                                          $$slots: { default: true }
                                        });
                                        $$renderer8.push("<!--]-->");
                                      } else {
                                        $$renderer8.push("<!--[!-->");
                                        $$renderer8.push("<!--]-->");
                                      }
                                    },
                                    $$slots: { default: true }
                                  });
                                  $$renderer7.push("<!--]-->");
                                } else {
                                  $$renderer7.push("<!--[!-->");
                                  $$renderer7.push("<!--]-->");
                                }
                                $$renderer7.push(` `);
                                if (Table_body) {
                                  $$renderer7.push("<!--[-->");
                                  Table_body($$renderer7, {
                                    children: ($$renderer8) => {
                                      $$renderer8.push(`<!--[-->`);
                                      const each_array_3 = ensure_array_like(lists.upcoming);
                                      for (let $$index_4 = 0, $$length = each_array_3.length; $$index_4 < $$length; $$index_4++) {
                                        let row = each_array_3[$$index_4];
                                        if (Table_row) {
                                          $$renderer8.push("<!--[-->");
                                          Table_row($$renderer8, {
                                            children: ($$renderer9) => {
                                              if (Table_cell) {
                                                $$renderer9.push("<!--[-->");
                                                Table_cell($$renderer9, {
                                                  class: "font-mono text-xs",
                                                  children: ($$renderer10) => {
                                                    $$renderer10.push(`<!---->${escape_html(row.reference_code)}`);
                                                  },
                                                  $$slots: { default: true }
                                                });
                                                $$renderer9.push("<!--]-->");
                                              } else {
                                                $$renderer9.push("<!--[!-->");
                                                $$renderer9.push("<!--]-->");
                                              }
                                              $$renderer9.push(` `);
                                              if (Table_cell) {
                                                $$renderer9.push("<!--[-->");
                                                Table_cell($$renderer9, {
                                                  class: "font-medium",
                                                  children: ($$renderer10) => {
                                                    $$renderer10.push(`<!---->${escape_html(row.name)}`);
                                                  },
                                                  $$slots: { default: true }
                                                });
                                                $$renderer9.push("<!--]-->");
                                              } else {
                                                $$renderer9.push("<!--[!-->");
                                                $$renderer9.push("<!--]-->");
                                              }
                                              $$renderer9.push(` `);
                                              if (Table_cell) {
                                                $$renderer9.push("<!--[-->");
                                                Table_cell($$renderer9, {
                                                  children: ($$renderer10) => {
                                                    $$renderer10.push(`<div class="flex flex-wrap gap-1">`);
                                                    if ((row.basis ?? []).length === 0) {
                                                      $$renderer10.push("<!--[0-->");
                                                      $$renderer10.push(`<span class="text-muted-foreground">—</span>`);
                                                    } else {
                                                      $$renderer10.push("<!--[-1-->");
                                                      $$renderer10.push(`<!--[-->`);
                                                      const each_array_4 = ensure_array_like(row.basis);
                                                      for (let $$index_3 = 0, $$length2 = each_array_4.length; $$index_3 < $$length2; $$index_3++) {
                                                        let basis = each_array_4[$$index_3];
                                                        Badge($$renderer10, {
                                                          variant: "outline",
                                                          children: ($$renderer11) => {
                                                            $$renderer11.push(`<!---->${escape_html(basis)}`);
                                                          },
                                                          $$slots: { default: true }
                                                        });
                                                      }
                                                      $$renderer10.push(`<!--]-->`);
                                                    }
                                                    $$renderer10.push(`<!--]--></div>`);
                                                  },
                                                  $$slots: { default: true }
                                                });
                                                $$renderer9.push("<!--]-->");
                                              } else {
                                                $$renderer9.push("<!--[!-->");
                                                $$renderer9.push("<!--]-->");
                                              }
                                              $$renderer9.push(` `);
                                              if (Table_cell) {
                                                $$renderer9.push("<!--[-->");
                                                Table_cell($$renderer9, {
                                                  children: ($$renderer10) => {
                                                    Badge($$renderer10, {
                                                      variant: stageVariant(row.stage),
                                                      children: ($$renderer11) => {
                                                        $$renderer11.push(`<!---->${escape_html(stageLabel(row.stage))}`);
                                                      },
                                                      $$slots: { default: true }
                                                    });
                                                  },
                                                  $$slots: { default: true }
                                                });
                                                $$renderer9.push("<!--]-->");
                                              } else {
                                                $$renderer9.push("<!--[!-->");
                                                $$renderer9.push("<!--]-->");
                                              }
                                            },
                                            $$slots: { default: true }
                                          });
                                          $$renderer8.push("<!--]-->");
                                        } else {
                                          $$renderer8.push("<!--[!-->");
                                          $$renderer8.push("<!--]-->");
                                        }
                                      }
                                      $$renderer8.push(`<!--]-->`);
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
                  $$renderer5.push(` `);
                  if (Tabs_content) {
                    $$renderer5.push("<!--[-->");
                    Tabs_content($$renderer5, {
                      value: "follow_ups",
                      class: "pt-4",
                      children: ($$renderer6) => {
                        if (lists.follow_ups.length === 0) {
                          $$renderer6.push("<!--[0-->");
                          $$renderer6.push(`<p class="text-muted-foreground py-10 text-center">No open follow-ups for this program.</p>`);
                        } else {
                          $$renderer6.push("<!--[-1-->");
                          $$renderer6.push(`<div class="space-y-3"><!--[-->`);
                          const each_array_5 = ensure_array_like(lists.follow_ups);
                          for (let $$index_6 = 0, $$length = each_array_5.length; $$index_6 < $$length; $$index_6++) {
                            let row = each_array_5[$$index_6];
                            if (Card) {
                              $$renderer6.push("<!--[-->");
                              Card($$renderer6, {
                                children: ($$renderer7) => {
                                  if (Card_content) {
                                    $$renderer7.push("<!--[-->");
                                    Card_content($$renderer7, {
                                      class: "space-y-4 p-4 sm:p-5",
                                      children: ($$renderer8) => {
                                        $$renderer8.push(`<div class="flex flex-wrap items-start justify-between gap-3"><div class="min-w-0"><h3 class="font-semibold">${escape_html(row.name)}</h3> <p class="mt-1 font-mono text-xs text-muted-foreground">${escape_html(row.reference_code)}</p> `);
                                        if (row.overlaps_enrolling) {
                                          $$renderer8.push("<!--[0-->");
                                          Badge($$renderer8, {
                                            variant: "secondary",
                                            class: "mt-2",
                                            children: ($$renderer9) => {
                                              $$renderer9.push(`<!---->Overlaps enrolling`);
                                            },
                                            $$slots: { default: true }
                                          });
                                        } else {
                                          $$renderer8.push("<!--[-1-->");
                                        }
                                        $$renderer8.push(`<!--]--></div> <div class="flex flex-wrap items-center gap-2">`);
                                        Badge($$renderer8, {
                                          variant: followUpStatusVariant(row.status),
                                          children: ($$renderer9) => {
                                            $$renderer9.push(`<!---->${escape_html(followUpStatusLabel(row.status))}`);
                                          },
                                          $$slots: { default: true }
                                        });
                                        $$renderer8.push(`<!----> <span class="text-xs text-muted-foreground">Opened ${escape_html(formatDateTime(row.opened_at))}</span></div></div> <div class="grid gap-4 md:grid-cols-[minmax(0,1fr)_minmax(0,1.5fr)]"><section aria-label="Applicant academic profile" class="rounded-md border bg-muted/20 p-3"><h4 class="text-xs font-semibold uppercase tracking-wide text-muted-foreground">Applicant profile</h4> <dl class="mt-2 grid grid-cols-2 gap-3 text-sm"><div><dt class="text-xs text-muted-foreground">SHS strand</dt><dd class="font-medium">${escape_html(row.strand ?? "—")}</dd></div> <div><dt class="text-xs text-muted-foreground">GWA</dt><dd class="font-medium">${escape_html(formatScore(row.gwa))}</dd></div></dl></section> <section aria-label="Exam scores compared with program cutoffs" class="rounded-md border bg-muted/20 p-3"><h4 class="text-xs font-semibold uppercase tracking-wide text-muted-foreground">Exam scores vs program cutoffs</h4> `);
                                        if (scoreEntries(row).length) {
                                          $$renderer8.push("<!--[0-->");
                                          $$renderer8.push(`<ul class="mt-2 grid gap-2 sm:grid-cols-2"><!--[-->`);
                                          const each_array_6 = ensure_array_like(scoreEntries(row));
                                          for (let index = 0, $$length2 = each_array_6.length; index < $$length2; index++) {
                                            let score = each_array_6[index];
                                            const cutoff = scoreCutoff(row, score);
                                            $$renderer8.push(`<li class="flex min-w-0 items-center justify-between gap-2 rounded bg-background px-2.5 py-2 text-sm"><span class="truncate text-muted-foreground">${escape_html(score.area_name ?? score.area_code ?? score.name ?? `Subtest ${index + 1}`)}</span> <span class="shrink-0 font-medium">${escape_html(formatScore(score.score))} <span class="text-xs text-muted-foreground">/ cutoff ${escape_html(formatScore(cutoff))}</span></span></li>`);
                                          }
                                          $$renderer8.push(`<!--]--></ul>`);
                                        } else {
                                          $$renderer8.push("<!--[-1-->");
                                          $$renderer8.push(`<p class="mt-2 text-sm text-muted-foreground">Released exam score breakdown is unavailable.</p>`);
                                        }
                                        $$renderer8.push(`<!--]--></section></div> <div class="rounded-md border-l-2 border-primary/50 bg-primary/5 px-3 py-2.5"><p class="text-xs font-semibold uppercase tracking-wide text-muted-foreground">Referral reason from Guidance</p> <p class="mt-1 whitespace-pre-wrap text-sm">${escape_html(row.reason || "No referral reason was recorded.")}</p></div> <div class="flex flex-wrap justify-end gap-2 border-t pt-3">`);
                                        Link($$renderer8, {
                                          href: applicantUrl(row.attempt_id),
                                          children: ($$renderer9) => {
                                            Button($$renderer9, {
                                              variant: "outline",
                                              size: "sm",
                                              children: ($$renderer10) => {
                                                $$renderer10.push(`<!---->Open applicant`);
                                              },
                                              $$slots: { default: true }
                                            });
                                          },
                                          $$slots: { default: true }
                                        });
                                        $$renderer8.push(`<!----> `);
                                        Button($$renderer8, {
                                          variant: "default",
                                          size: "sm",
                                          class: "gap-1.5",
                                          onclick: () => openResolve(row),
                                          children: ($$renderer9) => {
                                            Message_square_warning($$renderer9, { class: "size-4" });
                                            $$renderer9.push(`<!----> Resolve`);
                                          },
                                          $$slots: { default: true }
                                        });
                                        $$renderer8.push(`<!----></div>`);
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
                          }
                          $$renderer6.push(`<!--]--></div>`);
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
                  $$renderer5.push(` `);
                  if (Tabs_content) {
                    $$renderer5.push("<!--[-->");
                    Tabs_content($$renderer5, {
                      value: "enrolling",
                      class: "pt-4",
                      children: ($$renderer6) => {
                        $$renderer6.push(`<div class="mb-4 flex flex-wrap items-center justify-between gap-3 rounded-xl border border-border/70 bg-card p-4"><div><h3 class="text-base font-semibold text-foreground">Official Pre-Enrollment List</h3> <p class="text-xs text-muted-foreground">Candidates committed to ${escape_html(dashboard?.program?.name ?? "program")} awaiting formal departmental endorsement.</p></div> <div class="flex items-center gap-2">`);
                        Button($$renderer6, {
                          variant: "default",
                          size: "sm",
                          class: "gap-1.5 font-semibold",
                          disabled: lists.enrolling.length === 0,
                          onclick: () => {
                            endorseDialogOpen = true;
                          },
                          children: ($$renderer7) => {
                            Circle_check($$renderer7, { class: "size-4" });
                            $$renderer7.push(`<!----> Endorse Pre-Enrollment List (${escape_html(lists.enrolling.length)})`);
                          },
                          $$slots: { default: true }
                        });
                        $$renderer6.push(`<!----></div></div> `);
                        if (lists.enrolling.length === 0) {
                          $$renderer6.push("<!--[0-->");
                          $$renderer6.push(`<p class="text-muted-foreground py-10 text-center">No applicants are currently in the pre-enrollment list.</p>`);
                        } else {
                          $$renderer6.push("<!--[-1-->");
                          if (Table) {
                            $$renderer6.push("<!--[-->");
                            Table($$renderer6, {
                              class: "w-full min-w-[560px] text-sm",
                              children: ($$renderer7) => {
                                if (Table_header) {
                                  $$renderer7.push("<!--[-->");
                                  Table_header($$renderer7, {
                                    children: ($$renderer8) => {
                                      if (Table_row) {
                                        $$renderer8.push("<!--[-->");
                                        Table_row($$renderer8, {
                                          children: ($$renderer9) => {
                                            if (Table_head) {
                                              $$renderer9.push("<!--[-->");
                                              Table_head($$renderer9, {
                                                children: ($$renderer10) => {
                                                  $$renderer10.push(`<!---->Reference`);
                                                },
                                                $$slots: { default: true }
                                              });
                                              $$renderer9.push("<!--]-->");
                                            } else {
                                              $$renderer9.push("<!--[!-->");
                                              $$renderer9.push("<!--]-->");
                                            }
                                            $$renderer9.push(` `);
                                            if (Table_head) {
                                              $$renderer9.push("<!--[-->");
                                              Table_head($$renderer9, {
                                                children: ($$renderer10) => {
                                                  $$renderer10.push(`<!---->Name`);
                                                },
                                                $$slots: { default: true }
                                              });
                                              $$renderer9.push("<!--]-->");
                                            } else {
                                              $$renderer9.push("<!--[!-->");
                                              $$renderer9.push("<!--]-->");
                                            }
                                            $$renderer9.push(` `);
                                            if (Table_head) {
                                              $$renderer9.push("<!--[-->");
                                              Table_head($$renderer9, {
                                                children: ($$renderer10) => {
                                                  $$renderer10.push(`<!---->Enrolling since`);
                                                },
                                                $$slots: { default: true }
                                              });
                                              $$renderer9.push("<!--]-->");
                                            } else {
                                              $$renderer9.push("<!--[!-->");
                                              $$renderer9.push("<!--]-->");
                                            }
                                          },
                                          $$slots: { default: true }
                                        });
                                        $$renderer8.push("<!--]-->");
                                      } else {
                                        $$renderer8.push("<!--[!-->");
                                        $$renderer8.push("<!--]-->");
                                      }
                                    },
                                    $$slots: { default: true }
                                  });
                                  $$renderer7.push("<!--]-->");
                                } else {
                                  $$renderer7.push("<!--[!-->");
                                  $$renderer7.push("<!--]-->");
                                }
                                $$renderer7.push(` `);
                                if (Table_body) {
                                  $$renderer7.push("<!--[-->");
                                  Table_body($$renderer7, {
                                    children: ($$renderer8) => {
                                      $$renderer8.push(`<!--[-->`);
                                      const each_array_7 = ensure_array_like(lists.enrolling);
                                      for (let $$index_7 = 0, $$length = each_array_7.length; $$index_7 < $$length; $$index_7++) {
                                        let row = each_array_7[$$index_7];
                                        if (Table_row) {
                                          $$renderer8.push("<!--[-->");
                                          Table_row($$renderer8, {
                                            children: ($$renderer9) => {
                                              if (Table_cell) {
                                                $$renderer9.push("<!--[-->");
                                                Table_cell($$renderer9, {
                                                  class: "font-mono text-xs",
                                                  children: ($$renderer10) => {
                                                    $$renderer10.push(`<!---->${escape_html(row.reference_code)}`);
                                                  },
                                                  $$slots: { default: true }
                                                });
                                                $$renderer9.push("<!--]-->");
                                              } else {
                                                $$renderer9.push("<!--[!-->");
                                                $$renderer9.push("<!--]-->");
                                              }
                                              $$renderer9.push(` `);
                                              if (Table_cell) {
                                                $$renderer9.push("<!--[-->");
                                                Table_cell($$renderer9, {
                                                  class: "font-medium",
                                                  children: ($$renderer10) => {
                                                    $$renderer10.push(`<!---->${escape_html(row.name)}`);
                                                  },
                                                  $$slots: { default: true }
                                                });
                                                $$renderer9.push("<!--]-->");
                                              } else {
                                                $$renderer9.push("<!--[!-->");
                                                $$renderer9.push("<!--]-->");
                                              }
                                              $$renderer9.push(` `);
                                              if (Table_cell) {
                                                $$renderer9.push("<!--[-->");
                                                Table_cell($$renderer9, {
                                                  class: "text-muted-foreground",
                                                  children: ($$renderer10) => {
                                                    $$renderer10.push(`<!---->${escape_html(formatDateTime(row.stamped_at))}`);
                                                  },
                                                  $$slots: { default: true }
                                                });
                                                $$renderer9.push("<!--]-->");
                                              } else {
                                                $$renderer9.push("<!--[!-->");
                                                $$renderer9.push("<!--]-->");
                                              }
                                            },
                                            $$slots: { default: true }
                                          });
                                          $$renderer8.push("<!--]-->");
                                        } else {
                                          $$renderer8.push("<!--[!-->");
                                          $$renderer8.push("<!--]-->");
                                        }
                                      }
                                      $$renderer8.push(`<!--]-->`);
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
                  $$renderer5.push(` `);
                  if (Tabs_content) {
                    $$renderer5.push("<!--[-->");
                    Tabs_content($$renderer5, {
                      value: "enrolled",
                      class: "pt-4",
                      children: ($$renderer6) => {
                        if (lists.enrolled.length === 0) {
                          $$renderer6.push("<!--[0-->");
                          $$renderer6.push(`<p class="text-muted-foreground py-10 text-center">No applicants are enrolled in this program.</p>`);
                        } else {
                          $$renderer6.push("<!--[-1-->");
                          if (Table) {
                            $$renderer6.push("<!--[-->");
                            Table($$renderer6, {
                              class: "w-full min-w-[560px] text-sm",
                              children: ($$renderer7) => {
                                if (Table_header) {
                                  $$renderer7.push("<!--[-->");
                                  Table_header($$renderer7, {
                                    children: ($$renderer8) => {
                                      if (Table_row) {
                                        $$renderer8.push("<!--[-->");
                                        Table_row($$renderer8, {
                                          children: ($$renderer9) => {
                                            if (Table_head) {
                                              $$renderer9.push("<!--[-->");
                                              Table_head($$renderer9, {
                                                children: ($$renderer10) => {
                                                  $$renderer10.push(`<!---->Reference`);
                                                },
                                                $$slots: { default: true }
                                              });
                                              $$renderer9.push("<!--]-->");
                                            } else {
                                              $$renderer9.push("<!--[!-->");
                                              $$renderer9.push("<!--]-->");
                                            }
                                            $$renderer9.push(` `);
                                            if (Table_head) {
                                              $$renderer9.push("<!--[-->");
                                              Table_head($$renderer9, {
                                                children: ($$renderer10) => {
                                                  $$renderer10.push(`<!---->Name`);
                                                },
                                                $$slots: { default: true }
                                              });
                                              $$renderer9.push("<!--]-->");
                                            } else {
                                              $$renderer9.push("<!--[!-->");
                                              $$renderer9.push("<!--]-->");
                                            }
                                            $$renderer9.push(` `);
                                            if (Table_head) {
                                              $$renderer9.push("<!--[-->");
                                              Table_head($$renderer9, {
                                                children: ($$renderer10) => {
                                                  $$renderer10.push(`<!---->Enrolled`);
                                                },
                                                $$slots: { default: true }
                                              });
                                              $$renderer9.push("<!--]-->");
                                            } else {
                                              $$renderer9.push("<!--[!-->");
                                              $$renderer9.push("<!--]-->");
                                            }
                                          },
                                          $$slots: { default: true }
                                        });
                                        $$renderer8.push("<!--]-->");
                                      } else {
                                        $$renderer8.push("<!--[!-->");
                                        $$renderer8.push("<!--]-->");
                                      }
                                    },
                                    $$slots: { default: true }
                                  });
                                  $$renderer7.push("<!--]-->");
                                } else {
                                  $$renderer7.push("<!--[!-->");
                                  $$renderer7.push("<!--]-->");
                                }
                                $$renderer7.push(` `);
                                if (Table_body) {
                                  $$renderer7.push("<!--[-->");
                                  Table_body($$renderer7, {
                                    children: ($$renderer8) => {
                                      $$renderer8.push(`<!--[-->`);
                                      const each_array_8 = ensure_array_like(lists.enrolled);
                                      for (let $$index_8 = 0, $$length = each_array_8.length; $$index_8 < $$length; $$index_8++) {
                                        let row = each_array_8[$$index_8];
                                        if (Table_row) {
                                          $$renderer8.push("<!--[-->");
                                          Table_row($$renderer8, {
                                            children: ($$renderer9) => {
                                              if (Table_cell) {
                                                $$renderer9.push("<!--[-->");
                                                Table_cell($$renderer9, {
                                                  class: "font-mono text-xs",
                                                  children: ($$renderer10) => {
                                                    $$renderer10.push(`<!---->${escape_html(row.reference_code)}`);
                                                  },
                                                  $$slots: { default: true }
                                                });
                                                $$renderer9.push("<!--]-->");
                                              } else {
                                                $$renderer9.push("<!--[!-->");
                                                $$renderer9.push("<!--]-->");
                                              }
                                              $$renderer9.push(` `);
                                              if (Table_cell) {
                                                $$renderer9.push("<!--[-->");
                                                Table_cell($$renderer9, {
                                                  class: "font-medium",
                                                  children: ($$renderer10) => {
                                                    $$renderer10.push(`<!---->${escape_html(row.name)}`);
                                                  },
                                                  $$slots: { default: true }
                                                });
                                                $$renderer9.push("<!--]-->");
                                              } else {
                                                $$renderer9.push("<!--[!-->");
                                                $$renderer9.push("<!--]-->");
                                              }
                                              $$renderer9.push(` `);
                                              if (Table_cell) {
                                                $$renderer9.push("<!--[-->");
                                                Table_cell($$renderer9, {
                                                  class: "text-muted-foreground",
                                                  children: ($$renderer10) => {
                                                    $$renderer10.push(`<!---->${escape_html(formatDateTime(row.stamped_at))}`);
                                                  },
                                                  $$slots: { default: true }
                                                });
                                                $$renderer9.push("<!--]-->");
                                              } else {
                                                $$renderer9.push("<!--[!-->");
                                                $$renderer9.push("<!--]-->");
                                              }
                                            },
                                            $$slots: { default: true }
                                          });
                                          $$renderer8.push("<!--]-->");
                                        } else {
                                          $$renderer8.push("<!--[!-->");
                                          $$renderer8.push("<!--]-->");
                                        }
                                      }
                                      $$renderer8.push(`<!--]-->`);
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
            $$renderer4.push(` <p class="text-xs text-muted-foreground">Source: ${escape_html(dashboard.source ?? "committed domain records")} · as of ${escape_html(formatDateTime(dashboard.as_of))}</p>`);
          } else {
            $$renderer4.push("<!--[-1-->");
          }
          $$renderer4.push(`<!--]--> `);
          if (Dialog) {
            $$renderer4.push("<!--[-->");
            Dialog($$renderer4, {
              get open() {
                return endorseDialogOpen;
              },
              set open($$value) {
                endorseDialogOpen = $$value;
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
                                  $$renderer8.push(`<!---->Endorse Official Pre-Enrollment List`);
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
                                  $$renderer8.push(`<!---->Formal departmental sign-off transmitting the ${escape_html(lists?.enrolling?.length ?? 0)} qualified candidate(s) for section assignment and final registration.`);
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
                      $$renderer6.push(` <form class="space-y-4 py-2"><div class="space-y-1.5">`);
                      Label($$renderer6, {
                        for: "endorse_notes",
                        children: ($$renderer7) => {
                          $$renderer7.push(`<!---->Department Endorsement Notes (Optional)`);
                        },
                        $$slots: { default: true }
                      });
                      $$renderer6.push(`<!----> `);
                      Textarea($$renderer6, {
                        id: "endorse_notes",
                        placeholder: "e.g. Endorsed by College Dean for 1st Semester Sectioning; prerequisites verified.",
                        rows: 3,
                        get value() {
                          return endorseNotes;
                        },
                        set value($$value) {
                          endorseNotes = $$value;
                          $$settled = false;
                        }
                      });
                      $$renderer6.push(`<!----></div> `);
                      if (Dialog_footer) {
                        $$renderer6.push("<!--[-->");
                        Dialog_footer($$renderer6, {
                          children: ($$renderer7) => {
                            Button($$renderer7, {
                              type: "button",
                              variant: "outline",
                              onclick: () => {
                                endorseDialogOpen = false;
                              },
                              children: ($$renderer8) => {
                                $$renderer8.push(`<!---->Cancel`);
                              },
                              $$slots: { default: true }
                            });
                            $$renderer7.push(`<!----> `);
                            Button($$renderer7, {
                              type: "submit",
                              disabled: endorseForm.processing,
                              children: ($$renderer8) => {
                                $$renderer8.push(`<!---->Confirm &amp; Transmit Endorsement`);
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
                      $$renderer6.push(`</form>`);
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
          $$renderer4.push(`</div> `);
          if (Dialog) {
            $$renderer4.push("<!--[-->");
            Dialog($$renderer4, {
              get open() {
                return resolveDialogOpen;
              },
              set open($$value) {
                resolveDialogOpen = $$value;
                $$settled = false;
              },
              children: ($$renderer5) => {
                if (Dialog_content) {
                  $$renderer5.push("<!--[-->");
                  Dialog_content($$renderer5, {
                    class: "max-h-[90vh] overflow-y-auto sm:max-w-xl",
                    children: ($$renderer6) => {
                      if (Dialog_header) {
                        $$renderer6.push("<!--[-->");
                        Dialog_header($$renderer6, {
                          children: ($$renderer7) => {
                            if (Dialog_title) {
                              $$renderer7.push("<!--[-->");
                              Dialog_title($$renderer7, {
                                children: ($$renderer8) => {
                                  $$renderer8.push(`<!---->Resolve follow-up`);
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
                                  $$renderer8.push(`<!---->${escape_html(activeFollowUp ? `${activeFollowUp.name} (${activeFollowUp.reference_code})` : "")} — record the program head's resolution. The server re-checks scope and state.`);
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
                      $$renderer6.push(` <div class="space-y-5"><fieldset class="space-y-2"><legend class="text-sm font-semibold">Choose an action</legend> <div class="grid gap-2"><!--[-->`);
                      const each_array_9 = ensure_array_like(resolveOutcomes);
                      for (let $$index_9 = 0, $$length = each_array_9.length; $$index_9 < $$length; $$index_9++) {
                        let outcome = each_array_9[$$index_9];
                        Button($$renderer6, {
                          type: "button",
                          variant: resolveForm.outcome === outcome.value ? outcome.value === "declined_by_ph" ? "destructive" : "default" : "outline",
                          "aria-pressed": resolveForm.outcome === outcome.value,
                          class: `h-auto w-full items-start justify-start whitespace-normal p-3 text-left ${resolveForm.outcome === outcome.value ? "ring-2 ring-ring ring-offset-2" : ""}`,
                          onclick: () => resolveForm.outcome = outcome.value,
                          children: ($$renderer7) => {
                            $$renderer7.push(`<span class="flex w-full flex-col items-start gap-1"><span class="font-semibold">${escape_html(outcome.label)}</span> <span${attr_class(`text-xs font-normal ${outcome.variant === "destructive" ? "text-destructive-foreground/90" : resolveForm.outcome === outcome.value ? "text-primary-foreground/90" : "text-muted-foreground"}`)}>${escape_html(outcome.description)}</span></span>`);
                          },
                          $$slots: { default: true }
                        });
                      }
                      $$renderer6.push(`<!--]--></div></fieldset> <div class="space-y-2"><label class="text-sm font-medium" for="resolve-reason">Resolution reason `);
                      if (resolveForm.outcome !== "appearance_requested") {
                        $$renderer6.push("<!--[0-->");
                        $$renderer6.push(`<span aria-hidden="true" class="text-destructive">*</span><span class="sr-only">required</span>`);
                      } else {
                        $$renderer6.push("<!--[-1-->");
                        $$renderer6.push(`<span class="text-muted-foreground">(optional)</span>`);
                      }
                      $$renderer6.push(`<!--]--></label> `);
                      Textarea($$renderer6, {
                        id: "resolve-reason",
                        rows: 3,
                        autofocus: true,
                        required: resolveForm.outcome !== "appearance_requested",
                        "aria-required": resolveForm.outcome !== "appearance_requested",
                        "aria-invalid": Boolean(resolveForm.errors?.reason),
                        "aria-describedby": "resolve-reason-help",
                        placeholder: resolveForm.outcome === "appearance_requested" ? "Add an optional note…" : "Explain why this applicant is cleared or declined…",
                        get value() {
                          return resolveForm.reason;
                        },
                        set value($$value) {
                          resolveForm.reason = $$value;
                          $$settled = false;
                        }
                      });
                      $$renderer6.push(`<!----> <p id="resolve-reason-help" class="text-xs text-muted-foreground">Clear and decline require a reason. Clearing marks the applicant advised and ready for Registrar Enrollment; declining returns them to Guidance for alternative placement.</p></div> `);
                      if (resolveForm.errors?.outcome || resolveForm.errors?.reason) {
                        $$renderer6.push("<!--[0-->");
                        $$renderer6.push(`<p class="border-destructive/40 bg-destructive/10 text-destructive rounded-md border px-3 py-2 text-xs">${escape_html(resolveForm.errors.outcome ?? resolveForm.errors.reason)}</p>`);
                      } else {
                        $$renderer6.push("<!--[-1-->");
                      }
                      $$renderer6.push(`<!--]--></div> `);
                      if (Dialog_footer) {
                        $$renderer6.push("<!--[-->");
                        Dialog_footer($$renderer6, {
                          children: ($$renderer7) => {
                            Button($$renderer7, {
                              type: "button",
                              variant: "outline",
                              onclick: () => resolveDialogOpen = false,
                              children: ($$renderer8) => {
                                $$renderer8.push(`<!---->Back`);
                              },
                              $$slots: { default: true }
                            });
                            $$renderer7.push(`<!----> `);
                            Button($$renderer7, {
                              type: "button",
                              disabled: resolveReasonRequired() || resolveForm.processing,
                              onclick: submitResolve,
                              children: ($$renderer8) => {
                                $$renderer8.push(`<!---->${escape_html(resolveForm.processing ? "Resolving…" : "Confirm resolution")}`);
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
  ProgramShow as default
};
