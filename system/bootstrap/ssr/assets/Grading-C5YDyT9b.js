import { h as head, e as escape_html, a as ensure_array_like, B as Button, f as attr_style, s as stringify, d as derived, u as usePage } from "../app.js";
import { S as StaffLayout } from "./StaffLayout-CR-MZopa.js";
import { P as PageHeaderBar } from "./PageHeaderBar-DJhT5vOR.js";
import { H as HeaderActions } from "./HeaderActions-Dxxt_yha.js";
import { S as SwitchableListView } from "./SwitchableListView-DHKJpEKr.js";
import { M as ModeBanner } from "./ModeBanner-CuWMSWxe.js";
import { E as EntityRowActions } from "./EntityRowActions-X8rVBAZZ.js";
import { B as Badge } from "./badge-D9Vgs7xh.js";
import { D as Dialog, a as Dialog_content, b as Dialog_header, c as Dialog_title, d as Dialog_description } from "./dialog-description-D12L0QA9.js";
import { D as Dialog_footer } from "./dialog-footer-kNnwKT2h.js";
import "clsx";
import { T as Table, a as Table_header, b as Table_row, c as Table_head, d as Table_body, e as Table_cell } from "./table-row-_ffWKrps.js";
import { C as ContextualImportModal } from "./ContextualImportModal-BWVGg4FM.js";
import { L as Layers } from "./layers-_Ie6y7G_.js";
import { L as Link } from "./Link-B7ks7rBK.js";
import { S as Scan_line } from "./scan-line-CiwhUtYK.js";
import { F as File_spreadsheet } from "./settings-C5xOKpV3.js";
import { P as Plus } from "./plus-DjyPLC7A.js";
import { router } from "@inertiajs/core";
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
import "./input-BUZAZKIK.js";
import "./circle-alert-jWXJ7oC0.js";
import "./refresh-cw-CYdk8aE0.js";
import "./camera-C09Eyogi.js";
import "./arrow-right-CzynHiIo.js";
import "./circle-check-C59ZS7Zv.js";
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
import "./roving-focus-group-BJDH9kUk.js";
import "./table-2-BLgeaPqt.js";
import "./layout-grid-CXJfSBqV.js";
import "./ellipsis-CPRDNN0k.js";
import "./file-upload-B9oVsy17.js";
import "./upload-CwqN2Utd.js";
import "./loader-circle-_UEl10Lx.js";
function Grading($$renderer, $$props) {
  $$renderer.component(($$renderer2) => {
    let {
      page_state = "ready",
      sessions = [],
      candidate_exam_sessions = [],
      aptitude_areas_count = 0,
      degraded = false,
      aptitude_areas = [],
      active_academic_cycle = null,
      can_import_scores = false
    } = $$props;
    const page = usePage();
    const openError = derived(() => page.props.errors?.open ?? "");
    const inProgressCount = derived(() => (sessions ?? []).filter((s) => s.status !== "finalized").length);
    const finalizedCount = derived(() => (sessions ?? []).filter((s) => s.status === "finalized").length);
    const gradingContext = derived(() => {
      const entityKey = "grading-hub";
      const safetyKey = `${page_state}:${aptitude_areas_count}:${degraded ? "deg" : "norm"}:${candidate_exam_sessions.length}:${inProgressCount()}:${finalizedCount()}`;
      return {
        entityKey,
        safetyKey,
        freshness: "current",
        pageState: page_state,
        sessionsCount: sessions?.length ?? 0,
        candidateSessionsCount: candidate_exam_sessions?.length ?? 0,
        aptitudeAreasCount: aptitude_areas_count,
        isDegraded: Boolean(degraded),
        inProgressCount: inProgressCount(),
        finalizedCount: finalizedCount(),
        openError: openError() || null
      };
    });
    let viewMode = "responsive";
    let openDialog = false;
    let selectedSessionId = "";
    let importModalOpen = false;
    function statusVariant(status) {
      return status === "finalized" ? "success" : "warning";
    }
    function statusLabel(status) {
      if (status === "finalized") return "Finalized";
      if (status === "review") return "In review";
      return "In progress";
    }
    function formatDateTime(dtStr) {
      if (!dtStr) return "—";
      try {
        const cleanStr = dtStr.replace(" ", "T");
        const d = new Date(cleanStr);
        if (isNaN(d.getTime())) return dtStr;
        const datePart = d.toLocaleDateString("en-US", { month: "short", day: "numeric", year: "numeric" });
        const timePart = d.toLocaleTimeString("en-US", { hour: "numeric", minute: "2-digit", hour12: true });
        return `${datePart} · ${timePart}`;
      } catch {
        return dtStr;
      }
    }
    function scoringPercentage(scored, total) {
      if (!total || total <= 0) return 0;
      return Math.min(100, Math.round((scored ?? 0) / total * 100));
    }
    function submitOpen() {
      return;
    }
    let $$settled = true;
    let $$inner_renderer;
    function $$render_inner($$renderer3) {
      head("1h79mou", $$renderer3, ($$renderer4) => {
        $$renderer4.title(($$renderer5) => {
          $$renderer5.push(`<title>Scoring</title>`);
        });
      });
      StaffLayout($$renderer3, {
        breadcrumbs: [{ label: "Scoring" }],
        children: ($$renderer4) => {
          $$renderer4.push(`<div class="min-w-0 space-y-6 p-4 sm:p-6">`);
          if (degraded) {
            $$renderer4.push("<!--[0-->");
            ModeBanner($$renderer4, {
              variant: "warning",
              title: "Manual scoring mode",
              children: ($$renderer5) => {
                $$renderer5.push(`<!---->Inference provider unavailable; manual entry remains
                authoritative.`);
              }
            });
          } else {
            $$renderer4.push("<!--[-1-->");
          }
          $$renderer4.push(`<!--]--> `);
          if (page_state === "denied") {
            $$renderer4.push("<!--[0-->");
            $$renderer4.push(`<p class="text-muted-foreground text-sm">You do not have permission to access scoring.</p>`);
          } else if (aptitude_areas_count === 0) {
            $$renderer4.push("<!--[1-->");
            $$renderer4.push(`<div class="rounded-xl border border-amber-500/40 bg-amber-500/5 p-6"><div class="flex items-center gap-2 text-amber-700 dark:text-amber-400">`);
            Layers($$renderer4, { class: "size-5" });
            $$renderer4.push(`<!----> <h2 class="text-lg font-semibold">No aptitude areas configured</h2></div> <p class="text-muted-foreground mt-1 text-sm">Aptitude areas must be set up before you can open scoring
                    sessions.</p></div>`);
          } else {
            $$renderer4.push("<!--[-1-->");
            {
              let actions = function($$renderer5) {
                {
                  let primaryAction = function($$renderer6) {
                    Button($$renderer6, {
                      type: "button",
                      class: "min-h-10 gap-2 font-bold text-xs",
                      "data-tour": "grading-open-session",
                      onclick: () => openDialog = true,
                      children: ($$renderer7) => {
                        Plus($$renderer7, { class: "size-4" });
                        $$renderer7.push(`<!----> Open session`);
                      },
                      $$slots: { default: true }
                    });
                  }, secondaryActions = function($$renderer6) {
                    Link($$renderer6, {
                      href: "/grading/omr",
                      children: ($$renderer7) => {
                        Button($$renderer7, {
                          variant: "outline",
                          class: "min-h-10 gap-2 w-full sm:w-auto",
                          children: ($$renderer8) => {
                            Scan_line($$renderer8, { class: "size-4" });
                            $$renderer8.push(`<!----> Scan any paper`);
                          },
                          $$slots: { default: true }
                        });
                      },
                      $$slots: { default: true }
                    });
                    $$renderer6.push(`<!----> `);
                    if (can_import_scores) {
                      $$renderer6.push("<!--[0-->");
                      Button($$renderer6, {
                        type: "button",
                        variant: "outline",
                        class: "min-h-10 gap-2 w-full sm:w-auto",
                        "data-tour": "grading-import-scores",
                        onclick: () => importModalOpen = true,
                        children: ($$renderer7) => {
                          File_spreadsheet($$renderer7, { class: "size-4" });
                          $$renderer7.push(`<!----> Import CSV scores`);
                        },
                        $$slots: { default: true }
                      });
                    } else {
                      $$renderer6.push("<!--[-1-->");
                    }
                    $$renderer6.push(`<!--]-->`);
                  };
                  HeaderActions($$renderer5, {
                    primaryAction,
                    secondaryActions
                  });
                }
              };
              PageHeaderBar($$renderer4, {
                title: "Scoring",
                description: "Manage active scoring sessions, record examinee raw scores, and verify converted aptitude results.",
                tourKey: "assessment-grading-dashboard",
                context: gradingContext(),
                actions
              });
            }
            $$renderer4.push(`<!----> <div data-tour="grading-sessions-list">`);
            {
              let table = function($$renderer5) {
                if (Table) {
                  $$renderer5.push("<!--[-->");
                  Table($$renderer5, {
                    class: "w-full min-w-205 text-sm",
                    children: ($$renderer6) => {
                      if (Table_header) {
                        $$renderer6.push("<!--[-->");
                        Table_header($$renderer6, {
                          class: "bg-muted/50",
                          children: ($$renderer7) => {
                            if (Table_row) {
                              $$renderer7.push("<!--[-->");
                              Table_row($$renderer7, {
                                children: ($$renderer8) => {
                                  if (Table_head) {
                                    $$renderer8.push("<!--[-->");
                                    Table_head($$renderer8, {
                                      class: "px-4 py-3 text-left font-semibold",
                                      children: ($$renderer9) => {
                                        $$renderer9.push(`<!---->Session`);
                                      },
                                      $$slots: { default: true }
                                    });
                                    $$renderer8.push("<!--]-->");
                                  } else {
                                    $$renderer8.push("<!--[!-->");
                                    $$renderer8.push("<!--]-->");
                                  }
                                  $$renderer8.push(` `);
                                  if (Table_head) {
                                    $$renderer8.push("<!--[-->");
                                    Table_head($$renderer8, {
                                      class: "px-4 py-3 text-left font-semibold",
                                      children: ($$renderer9) => {
                                        $$renderer9.push(`<!---->Date &amp; Time`);
                                      },
                                      $$slots: { default: true }
                                    });
                                    $$renderer8.push("<!--]-->");
                                  } else {
                                    $$renderer8.push("<!--[!-->");
                                    $$renderer8.push("<!--]-->");
                                  }
                                  $$renderer8.push(` `);
                                  if (Table_head) {
                                    $$renderer8.push("<!--[-->");
                                    Table_head($$renderer8, {
                                      class: "px-4 py-3 text-right font-semibold",
                                      children: ($$renderer9) => {
                                        $$renderer9.push(`<!---->Scoring Progress`);
                                      },
                                      $$slots: { default: true }
                                    });
                                    $$renderer8.push("<!--]-->");
                                  } else {
                                    $$renderer8.push("<!--[!-->");
                                    $$renderer8.push("<!--]-->");
                                  }
                                  $$renderer8.push(` `);
                                  if (Table_head) {
                                    $$renderer8.push("<!--[-->");
                                    Table_head($$renderer8, {
                                      class: "px-4 py-3 text-center font-semibold",
                                      children: ($$renderer9) => {
                                        $$renderer9.push(`<!---->Status`);
                                      },
                                      $$slots: { default: true }
                                    });
                                    $$renderer8.push("<!--]-->");
                                  } else {
                                    $$renderer8.push("<!--[!-->");
                                    $$renderer8.push("<!--]-->");
                                  }
                                  $$renderer8.push(` `);
                                  if (Table_head) {
                                    $$renderer8.push("<!--[-->");
                                    Table_head($$renderer8, {
                                      class: "sr-only px-4 py-3 pr-6 text-right font-semibold",
                                      children: ($$renderer9) => {
                                        $$renderer9.push(`<!---->Action`);
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
                          },
                          $$slots: { default: true }
                        });
                        $$renderer6.push("<!--]-->");
                      } else {
                        $$renderer6.push("<!--[!-->");
                        $$renderer6.push("<!--]-->");
                      }
                      $$renderer6.push(` `);
                      if (Table_body) {
                        $$renderer6.push("<!--[-->");
                        Table_body($$renderer6, {
                          children: ($$renderer7) => {
                            const each_array = ensure_array_like(sessions);
                            if (each_array.length !== 0) {
                              $$renderer7.push("<!--[-->");
                              for (let $$index = 0, $$length = each_array.length; $$index < $$length; $$index++) {
                                let gs = each_array[$$index];
                                if (Table_row) {
                                  $$renderer7.push("<!--[-->");
                                  Table_row($$renderer7, {
                                    class: "border-border hover:bg-muted/30 cursor-pointer border-t",
                                    onclick: (e) => {
                                      if (e.target.closest("[data-action-cell]")) return;
                                      router.visit(`/grading/sessions/${gs.id}`);
                                    },
                                    children: ($$renderer8) => {
                                      if (Table_cell) {
                                        $$renderer8.push("<!--[-->");
                                        Table_cell($$renderer8, {
                                          class: "px-4 py-3 text-left font-mono font-medium",
                                          children: ($$renderer9) => {
                                            $$renderer9.push(`<span class="text-foreground font-bold">${escape_html(gs.exam_code)}</span> <span class="text-muted-foreground block font-sans text-xs">${escape_html(gs.room_name)}</span>`);
                                          },
                                          $$slots: { default: true }
                                        });
                                        $$renderer8.push("<!--]-->");
                                      } else {
                                        $$renderer8.push("<!--[!-->");
                                        $$renderer8.push("<!--]-->");
                                      }
                                      $$renderer8.push(` `);
                                      if (Table_cell) {
                                        $$renderer8.push("<!--[-->");
                                        Table_cell($$renderer8, {
                                          class: "text-muted-foreground px-4 py-3 text-left font-mono text-xs",
                                          children: ($$renderer9) => {
                                            $$renderer9.push(`<!---->${escape_html(formatDateTime(gs.starts_at))}`);
                                          },
                                          $$slots: { default: true }
                                        });
                                        $$renderer8.push("<!--]-->");
                                      } else {
                                        $$renderer8.push("<!--[!-->");
                                        $$renderer8.push("<!--]-->");
                                      }
                                      $$renderer8.push(` `);
                                      if (Table_cell) {
                                        $$renderer8.push("<!--[-->");
                                        Table_cell($$renderer8, {
                                          class: "px-4 py-3 text-right",
                                          children: ($$renderer9) => {
                                            $$renderer9.push(`<div class="min-w-[140px] space-y-1 font-mono"><div class="flex items-center justify-between text-xs"><span class="text-foreground font-bold">${escape_html(gs.applicants_scored ?? 0)} / ${escape_html(gs.applicants_total ?? 0)} Scored</span> <span class="text-muted-foreground">${escape_html(scoringPercentage(gs.applicants_scored, gs.applicants_total))}%</span></div> <div class="bg-muted h-1.5 w-full overflow-hidden rounded-full"><div class="h-1.5 rounded-full bg-emerald-500 transition-all duration-300"${attr_style(`width: ${stringify(scoringPercentage(gs.applicants_scored, gs.applicants_total))}%`)}></div></div> `);
                                            if ((gs.applicants_absent ?? 0) > 0) {
                                              $$renderer9.push("<!--[0-->");
                                              $$renderer9.push(`<span class="text-muted-foreground block text-right text-[10px]">${escape_html(gs.applicants_absent)} absent
                                                        excluded</span>`);
                                            } else {
                                              $$renderer9.push("<!--[-1-->");
                                            }
                                            $$renderer9.push(`<!--]--></div>`);
                                          },
                                          $$slots: { default: true }
                                        });
                                        $$renderer8.push("<!--]-->");
                                      } else {
                                        $$renderer8.push("<!--[!-->");
                                        $$renderer8.push("<!--]-->");
                                      }
                                      $$renderer8.push(` `);
                                      if (Table_cell) {
                                        $$renderer8.push("<!--[-->");
                                        Table_cell($$renderer8, {
                                          class: "px-4 py-3 text-center",
                                          children: ($$renderer9) => {
                                            Badge($$renderer9, {
                                              variant: statusVariant(gs.status),
                                              class: "font-mono text-[10px] font-bold",
                                              children: ($$renderer10) => {
                                                $$renderer10.push(`<!---->${escape_html(statusLabel(gs.status))}`);
                                              },
                                              $$slots: { default: true }
                                            });
                                          },
                                          $$slots: { default: true }
                                        });
                                        $$renderer8.push("<!--]-->");
                                      } else {
                                        $$renderer8.push("<!--[!-->");
                                        $$renderer8.push("<!--]-->");
                                      }
                                      $$renderer8.push(` `);
                                      if (Table_cell) {
                                        $$renderer8.push("<!--[-->");
                                        Table_cell($$renderer8, {
                                          class: "px-4 py-3 pr-6 text-right",
                                          "data-action-cell": true,
                                          children: ($$renderer9) => {
                                            EntityRowActions($$renderer9, {
                                              primaryAction: {
                                                label: gs.status === "finalized" ? "Results" : "Scores",
                                                href: `/grading/sessions/${gs.id}`,
                                                variant: "outline"
                                              },
                                              overflowItems: [
                                                {
                                                  label: "View Session Details",
                                                  href: `/grading/sessions/${gs.id}`
                                                },
                                                {
                                                  label: "Scan OMR Paper",
                                                  href: `/grading/sessions/${gs.id}/omr`
                                                }
                                              ],
                                              dense: true
                                            });
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
                              }
                            } else {
                              $$renderer7.push("<!--[!-->");
                              if (Table_row) {
                                $$renderer7.push("<!--[-->");
                                Table_row($$renderer7, {
                                  children: ($$renderer8) => {
                                    if (Table_cell) {
                                      $$renderer8.push("<!--[-->");
                                      Table_cell($$renderer8, {
                                        colspan: 5,
                                        class: "text-muted-foreground px-4 py-12 text-center",
                                        children: ($$renderer9) => {
                                          $$renderer9.push(`<!---->No grading sessions yet. Open one
                                            for a completed exam session.`);
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
              }, cards = function($$renderer5) {
                $$renderer5.push(`<div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">`);
                const each_array_1 = ensure_array_like(sessions);
                if (each_array_1.length !== 0) {
                  $$renderer5.push("<!--[-->");
                  for (let $$index_1 = 0, $$length = each_array_1.length; $$index_1 < $$length; $$index_1++) {
                    let gs = each_array_1[$$index_1];
                    $$renderer5.push(`<div class="craft-card-depth group flex flex-col justify-between gap-3.5 p-5"><div class="space-y-2.5"><div class="border-border/50 flex items-start justify-between gap-2 border-b pb-2.5"><div class="min-w-0"><p class="text-foreground group-hover:text-primary truncate font-mono text-base font-bold tracking-tight transition-colors">${escape_html(gs.exam_code)}</p> <p class="text-muted-foreground mt-0.5 font-mono text-xs">${escape_html(formatDateTime(gs.starts_at))}</p></div> `);
                    Badge($$renderer5, {
                      variant: statusVariant(gs.status),
                      class: "font-mono text-[10px] font-bold",
                      children: ($$renderer6) => {
                        $$renderer6.push(`<!---->${escape_html(statusLabel(gs.status))}`);
                      },
                      $$slots: { default: true }
                    });
                    $$renderer5.push(`<!----></div> <div class="text-foreground flex items-center justify-between text-xs font-semibold"><span>Room: ${escape_html(gs.room_name ?? "—")}</span></div> <div class="border-border/50 space-y-1.5 border-t pt-2 font-mono"><div class="flex justify-between text-xs"><span class="text-muted-foreground text-[11px] tracking-wider uppercase">Scoring Progress</span> <span class="text-foreground font-bold">${escape_html(gs.applicants_scored ?? 0)} /
                                                    ${escape_html(gs.applicants_total ?? 0)} (${escape_html(scoringPercentage(gs.applicants_scored, gs.applicants_total))}%)</span></div> <div class="bg-muted h-1.5 w-full overflow-hidden rounded-full"><div class="h-1.5 rounded-full bg-emerald-500 transition-all duration-300"${attr_style(`width: ${stringify(scoringPercentage(gs.applicants_scored, gs.applicants_total))}%`)}></div></div> `);
                    if ((gs.applicants_absent ?? 0) > 0) {
                      $$renderer5.push("<!--[0-->");
                      $$renderer5.push(`<div class="text-muted-foreground flex items-center justify-between pt-0.5 text-[10px]"><span>Attendance Filter:</span> <span class="font-semibold text-rose-500">${escape_html(gs.applicants_absent)} absent
                                                        excluded</span></div>`);
                    } else {
                      $$renderer5.push("<!--[-1-->");
                    }
                    $$renderer5.push(`<!--]--></div></div> <div class="border-border/40 mt-auto flex items-center justify-between border-t pt-3" role="presentation"><span class="text-muted-foreground font-mono text-xs">${escape_html(gs.applicants_scored ?? 0)} / ${escape_html(gs.applicants_total ?? 0)} scored</span> `);
                    EntityRowActions($$renderer5, {
                      primaryAction: {
                        label: gs.status === "finalized" ? "View Results" : "Input Scores",
                        href: `/grading/sessions/${gs.id}`,
                        variant: "outline"
                      },
                      overflowItems: [
                        {
                          label: "Scan OMR Paper",
                          href: `/grading/sessions/${gs.id}/omr`
                        }
                      ]
                    });
                    $$renderer5.push(`<!----></div></div>`);
                  }
                } else {
                  $$renderer5.push("<!--[!-->");
                  $$renderer5.push(`<div class="border-border bg-muted/20 text-muted-foreground col-span-full rounded-[var(--radius,0.5rem)] border border-dashed py-12 text-center">No grading sessions yet. Open one for a
                                    completed exam session.</div>`);
                }
                $$renderer5.push(`<!--]--></div>`);
              };
              SwitchableListView($$renderer4, {
                overflow: "auto",
                get viewMode() {
                  return viewMode;
                },
                set viewMode($$value) {
                  viewMode = $$value;
                  $$settled = false;
                },
                table,
                cards,
                $$slots: { table: true, cards: true }
              });
            }
            $$renderer4.push(`<!----></div>`);
          }
          $$renderer4.push(`<!--]--></div> `);
          if (Dialog) {
            $$renderer4.push("<!--[-->");
            Dialog($$renderer4, {
              get open() {
                return openDialog;
              },
              set open($$value) {
                openDialog = $$value;
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
                                  $$renderer8.push(`<!---->Open scoring session`);
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
                                  $$renderer8.push(`<!---->Choose an exam session to start entering scores. Each exam
                    session has one scoring session.`);
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
                      if (openError()) {
                        $$renderer6.push("<!--[0-->");
                        $$renderer6.push(`<div class="border-destructive/40 bg-destructive/5 text-destructive rounded-md border px-3 py-2 text-sm">${escape_html(openError())}</div>`);
                      } else {
                        $$renderer6.push("<!--[-1-->");
                      }
                      $$renderer6.push(`<!--]--> <label class="block text-sm font-medium" for="open-grading-session">Exam session</label> `);
                      $$renderer6.select(
                        {
                          id: "open-grading-session",
                          class: "border-input bg-background flex min-h-10 w-full rounded-md border px-3 text-sm",
                          value: selectedSessionId
                        },
                        ($$renderer7) => {
                          $$renderer7.option({ value: "" }, ($$renderer8) => {
                            $$renderer8.push(`Select an exam session…`);
                          });
                          $$renderer7.push(`<!--[-->`);
                          const each_array_2 = ensure_array_like(candidate_exam_sessions);
                          for (let $$index_2 = 0, $$length = each_array_2.length; $$index_2 < $$length; $$index_2++) {
                            let candidate = each_array_2[$$index_2];
                            $$renderer7.option({ value: String(candidate.id) }, ($$renderer8) => {
                              $$renderer8.push(`${escape_html(candidate.code)} · ${escape_html(candidate.starts_at)} · ${escape_html(candidate.room_name)}`);
                            });
                          }
                          $$renderer7.push(`<!--]-->`);
                        }
                      );
                      $$renderer6.push(` `);
                      if (candidate_exam_sessions.length === 0) {
                        $$renderer6.push("<!--[0-->");
                        $$renderer6.push(`<p class="text-muted-foreground text-xs">No exam sessions left to open. Closing an exam session
                        opens its grading session automatically.</p>`);
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
                              onclick: () => openDialog = false,
                              children: ($$renderer8) => {
                                $$renderer8.push(`<!---->Cancel`);
                              },
                              $$slots: { default: true }
                            });
                            $$renderer7.push(`<!----> `);
                            Button($$renderer7, {
                              type: "button",
                              disabled: !selectedSessionId,
                              onclick: submitOpen,
                              children: ($$renderer8) => {
                                $$renderer8.push(`<!---->${escape_html("Open session")}`);
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
          if (can_import_scores) {
            $$renderer4.push("<!--[0-->");
            ContextualImportModal($$renderer4, {
              importType: "scores_only",
              academicCycle: active_academic_cycle,
              aptitudeAreas: aptitude_areas,
              get open() {
                return importModalOpen;
              },
              set open($$value) {
                importModalOpen = $$value;
                $$settled = false;
              }
            });
          } else {
            $$renderer4.push("<!--[-1-->");
          }
          $$renderer4.push(`<!--]-->`);
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
  Grading as default
};
