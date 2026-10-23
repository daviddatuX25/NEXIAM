import { h as head, e as escape_html, a as ensure_array_like, B as Button, d as derived, u as usePage } from "../app.js";
import { S as StaffLayout } from "./StaffLayout-CR-MZopa.js";
import { P as PageHeaderBar } from "./PageHeaderBar-DJhT5vOR.js";
import { M as ModeBanner } from "./ModeBanner-CuWMSWxe.js";
import { B as Badge } from "./badge-D9Vgs7xh.js";
import { T as Table, a as Table_header, b as Table_row, c as Table_head, d as Table_body, e as Table_cell } from "./table-row-_ffWKrps.js";
import "clsx";
import { C as Circle_check } from "./circle-check-C59ZS7Zv.js";
import { C as Circle } from "./circle-DadbbVvn.js";
import { L as Link } from "./Link-B7ks7rBK.js";
import { S as Scan_line } from "./scan-line-CiwhUtYK.js";
import { P as Printer } from "./printer-Bx0z1CRb.js";
import { U as User_check } from "./user-check-Dl-wciTR.js";
import { L as Lock } from "./lock-DAA4kTLl.js";
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
import "./dialog-description-D12L0QA9.js";
import "./circle-alert-jWXJ7oC0.js";
import "./refresh-cw-CYdk8aE0.js";
import "./camera-C09Eyogi.js";
import "./settings-C5xOKpV3.js";
import "./arrow-right-CzynHiIo.js";
import "./dialog-footer-kNnwKT2h.js";
import "./clock-B4fXoAi9.js";
import "./qr-code-C5X8qcH6.js";
import "./chevron-right-BHu-PGOg.js";
import "./chevron-down-CkQQZIhu.js";
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
function GradingSession($$renderer, $$props) {
  $$renderer.component(($$renderer2) => {
    let {
      page_state = "ready",
      degraded = false,
      session = {},
      applicants = [],
      focus_attempt_id = null,
      roster_stats = {
        assigned: 0,
        present_and_submitted: 0,
        absent: 0,
        pending_attendance: 0,
        no_submission: 0
      },
      progress_stats = { total: 0, scored: 0, pending: 0 },
      aptitude_areas_count = 0,
      can_finalize = false
    } = $$props;
    const page = usePage();
    const finalizeError = derived(() => page.props.errors?.finalize ?? "");
    let finalizing = false;
    const isFinalized = derived(() => session.status === "finalized");
    const breadcrumbs = [
      { label: "Scoring", href: "/grading" },
      { label: `Session ${session.exam_code}` }
    ];
    function submitFinalize() {
      if (!can_finalize || finalizing) return;
      finalizing = true;
      router.post(`/grading/sessions/${session.id}/finalize`, { expected_version: session.version }, {
        onFinish: () => {
          finalizing = false;
        }
      });
    }
    head("1s9zr0u", $$renderer2, ($$renderer3) => {
      $$renderer3.title(($$renderer4) => {
        $$renderer4.push(`<title>Session ${escape_html(session.exam_code ?? "")} — Scoring</title>`);
      });
    });
    StaffLayout($$renderer2, {
      breadcrumbs,
      children: ($$renderer3) => {
        $$renderer3.push(`<div class="min-w-0 space-y-6 p-4 sm:p-6">`);
        if (degraded) {
          $$renderer3.push("<!--[0-->");
          ModeBanner($$renderer3, {
            variant: "warning",
            title: "Manual grading mode",
            children: ($$renderer4) => {
              $$renderer4.push(`<!---->Inference provider unavailable; manual entry remains authoritative.`);
            }
          });
        } else {
          $$renderer3.push("<!--[-1-->");
        }
        $$renderer3.push(`<!--]--> `);
        {
          let actions = function($$renderer4) {
            $$renderer4.push(`<div class="flex flex-wrap items-center gap-2">`);
            Link($$renderer4, {
              href: `/grading/sessions/${session.id}/omr`,
              class: "inline-flex min-h-10 items-center gap-2 rounded-md border border-primary/40 px-4 text-sm font-medium text-primary transition-colors hover:bg-primary/10",
              children: ($$renderer5) => {
                Scan_line($$renderer5, { class: "size-4" });
                $$renderer5.push(`<!----> Open scanner`);
              },
              $$slots: { default: true }
            });
            $$renderer4.push(`<!----> `);
            Link($$renderer4, {
              href: `/release/print/batches/${session.id}`,
              class: "inline-flex min-h-10 items-center gap-2 rounded-md border border-border px-3 text-sm font-medium text-foreground transition-colors hover:bg-muted",
              children: ($$renderer5) => {
                Printer($$renderer5, { class: "size-4 text-muted-foreground" });
                $$renderer5.push(`<!----> Print results`);
              },
              $$slots: { default: true }
            });
            $$renderer4.push(`<!----> <div class="flex items-center gap-2 rounded-lg bg-muted/50 px-3 py-2">`);
            User_check($$renderer4, { class: "size-4 text-muted-foreground" });
            $$renderer4.push(`<!----> <span class="text-sm font-medium">${escape_html(progress_stats.scored)} of ${escape_html(progress_stats.total)} scored</span></div> `);
            if (!isFinalized()) {
              $$renderer4.push("<!--[0-->");
              Button($$renderer4, {
                class: "min-h-10 gap-2",
                disabled: !can_finalize || finalizing,
                title: !can_finalize ? `Every applicant must be scored for all ${aptitude_areas_count} aptitude areas first` : "Finalize grading",
                onclick: submitFinalize,
                children: ($$renderer5) => {
                  Lock($$renderer5, { class: "size-4" });
                  $$renderer5.push(`<!----> ${escape_html(finalizing ? "Finalizing…" : "Finalize grading")}`);
                },
                $$slots: { default: true }
              });
            } else {
              $$renderer4.push("<!--[-1-->");
            }
            $$renderer4.push(`<!--]--></div>`);
          };
          PageHeaderBar($$renderer3, {
            title: `Session ${session.exam_code ?? "—"} — ${session.room_name ?? "—"}`,
            description: `${session.starts_at ?? "—"} · Opened ${session.opened_at ? new Date(session.opened_at).toLocaleDateString() : "—"}`,
            actions
          });
        }
        $$renderer3.push(`<!----> <div class="grid grid-cols-2 gap-3 sm:grid-cols-4"><div class="rounded-xl border border-border bg-card px-4 py-3"><p class="text-2xl font-semibold">${escape_html(roster_stats.assigned)}</p> <p class="text-xs text-muted-foreground">Assigned</p></div> <div class="rounded-xl border border-border bg-card px-4 py-3"><p class="text-2xl font-semibold">${escape_html(roster_stats.present_and_submitted)}</p> <p class="text-xs text-muted-foreground">Present &amp; submitted</p></div> <div class="rounded-xl border border-border bg-card px-4 py-3"><p class="text-2xl font-semibold">${escape_html(roster_stats.absent)} `);
        if (roster_stats.pending_attendance > 0) {
          $$renderer3.push("<!--[0-->");
          $$renderer3.push(`<span class="text-sm font-normal text-muted-foreground">+ ${escape_html(roster_stats.pending_attendance)} pending</span>`);
        } else {
          $$renderer3.push("<!--[-1-->");
        }
        $$renderer3.push(`<!--]--></p> <p class="text-xs text-muted-foreground">Absent</p></div> <div class="rounded-xl border border-border bg-card px-4 py-3"><p class="text-2xl font-semibold">${escape_html(roster_stats.no_submission)}</p> <p class="text-xs text-muted-foreground">Present, no submission</p></div></div> `);
        if (roster_stats.absent > 0 || roster_stats.no_submission > 0) {
          $$renderer3.push("<!--[0-->");
          $$renderer3.push(`<p class="text-sm text-muted-foreground">${escape_html(roster_stats.absent)} absent and ${escape_html(roster_stats.no_submission)} present-without-submission
        applicant${escape_html(roster_stats.absent + roster_stats.no_submission === 1 ? "" : "s")} are not part of the grading
        roster and do not require scores.</p>`);
        } else {
          $$renderer3.push("<!--[-1-->");
        }
        $$renderer3.push(`<!--]--> `);
        if (isFinalized()) {
          $$renderer3.push("<!--[0-->");
          ModeBanner($$renderer3, {
            variant: "info",
            title: "Finalized",
            children: ($$renderer4) => {
              $$renderer4.push(`<!---->Scores are locked and no longer editable. Finalized by ${escape_html(session.finalized_by_name ?? "—")} on
        ${escape_html(session.finalized_at ? new Date(session.finalized_at).toLocaleString() : "—")}.`);
            }
          });
        } else {
          $$renderer3.push("<!--[-1-->");
        }
        $$renderer3.push(`<!--]--> `);
        if (finalizeError()) {
          $$renderer3.push("<!--[0-->");
          $$renderer3.push(`<div class="rounded-md border border-destructive/40 bg-destructive/5 px-4 py-3 text-sm text-destructive">${escape_html(finalizeError())}</div>`);
        } else {
          $$renderer3.push("<!--[-1-->");
        }
        $$renderer3.push(`<!--]--> `);
        if (!isFinalized() && progress_stats.pending > 0) {
          $$renderer3.push("<!--[0-->");
          $$renderer3.push(`<p class="text-sm text-muted-foreground">${escape_html(progress_stats.pending)} applicant${escape_html(progress_stats.pending === 1 ? "" : "s")} still need
        scores in all ${escape_html(aptitude_areas_count)} aptitude areas before finalizing.</p>`);
        } else {
          $$renderer3.push("<!--[-1-->");
        }
        $$renderer3.push(`<!--]--> <div class="rounded-xl border border-border bg-card"><div class="p-6"><h2 class="text-lg font-semibold tracking-tight">Applicants</h2> <p class="mt-1 text-sm text-muted-foreground">Click an applicant to input or edit aptitude area scores.</p></div> <div class="overflow-x-auto">`);
        if (Table) {
          $$renderer3.push("<!--[-->");
          Table($$renderer3, {
            class: "w-full min-w-[560px] text-sm",
            children: ($$renderer4) => {
              if (Table_header) {
                $$renderer4.push("<!--[-->");
                Table_header($$renderer4, {
                  class: "bg-muted/50",
                  children: ($$renderer5) => {
                    if (Table_row) {
                      $$renderer5.push("<!--[-->");
                      Table_row($$renderer5, {
                        children: ($$renderer6) => {
                          if (Table_head) {
                            $$renderer6.push("<!--[-->");
                            Table_head($$renderer6, {
                              class: "px-4 py-3",
                              children: ($$renderer7) => {
                                $$renderer7.push(`<!---->Reference`);
                              },
                              $$slots: { default: true }
                            });
                            $$renderer6.push("<!--]-->");
                          } else {
                            $$renderer6.push("<!--[!-->");
                            $$renderer6.push("<!--]-->");
                          }
                          $$renderer6.push(` `);
                          if (Table_head) {
                            $$renderer6.push("<!--[-->");
                            Table_head($$renderer6, {
                              class: "px-4 py-3",
                              children: ($$renderer7) => {
                                $$renderer7.push(`<!---->Name`);
                              },
                              $$slots: { default: true }
                            });
                            $$renderer6.push("<!--]-->");
                          } else {
                            $$renderer6.push("<!--[!-->");
                            $$renderer6.push("<!--]-->");
                          }
                          $$renderer6.push(` `);
                          if (Table_head) {
                            $$renderer6.push("<!--[-->");
                            Table_head($$renderer6, {
                              class: "px-4 py-3",
                              children: ($$renderer7) => {
                                $$renderer7.push(`<!---->Progress`);
                              },
                              $$slots: { default: true }
                            });
                            $$renderer6.push("<!--]-->");
                          } else {
                            $$renderer6.push("<!--[!-->");
                            $$renderer6.push("<!--]-->");
                          }
                          $$renderer6.push(` `);
                          if (Table_head) {
                            $$renderer6.push("<!--[-->");
                            Table_head($$renderer6, {
                              class: "px-4 py-3 text-center",
                              children: ($$renderer7) => {
                                $$renderer7.push(`<!---->Action`);
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
              if (Table_body) {
                $$renderer4.push("<!--[-->");
                Table_body($$renderer4, {
                  children: ($$renderer5) => {
                    const each_array = ensure_array_like(applicants);
                    if (each_array.length !== 0) {
                      $$renderer5.push("<!--[-->");
                      for (let $$index = 0, $$length = each_array.length; $$index < $$length; $$index++) {
                        let app = each_array[$$index];
                        if (Table_row) {
                          $$renderer5.push("<!--[-->");
                          Table_row($$renderer5, {
                            id: "grading-attempt-" + app.attempt_id,
                            class: `border-t border-border hover:bg-muted/30 ${String(app.attempt_id) === String(focus_attempt_id) ? "ring-2 ring-primary ring-offset-2 bg-primary/5" : ""}`,
                            children: ($$renderer6) => {
                              if (Table_cell) {
                                $$renderer6.push("<!--[-->");
                                Table_cell($$renderer6, {
                                  class: "px-4 py-3 font-medium",
                                  children: ($$renderer7) => {
                                    $$renderer7.push(`<!---->${escape_html(app.reference_code)}`);
                                  },
                                  $$slots: { default: true }
                                });
                                $$renderer6.push("<!--]-->");
                              } else {
                                $$renderer6.push("<!--[!-->");
                                $$renderer6.push("<!--]-->");
                              }
                              $$renderer6.push(` `);
                              if (Table_cell) {
                                $$renderer6.push("<!--[-->");
                                Table_cell($$renderer6, {
                                  class: "px-4 py-3",
                                  children: ($$renderer7) => {
                                    $$renderer7.push(`<!---->${escape_html(app.name)}`);
                                  },
                                  $$slots: { default: true }
                                });
                                $$renderer6.push("<!--]-->");
                              } else {
                                $$renderer6.push("<!--[!-->");
                                $$renderer6.push("<!--]-->");
                              }
                              $$renderer6.push(` `);
                              if (Table_cell) {
                                $$renderer6.push("<!--[-->");
                                Table_cell($$renderer6, {
                                  class: "px-4 py-3",
                                  children: ($$renderer7) => {
                                    if (app.scored) {
                                      $$renderer7.push("<!--[0-->");
                                      Badge($$renderer7, {
                                        variant: "success",
                                        class: "gap-1",
                                        children: ($$renderer8) => {
                                          Circle_check($$renderer8, { class: "size-3" });
                                          $$renderer8.push(`<!----> Complete`);
                                        },
                                        $$slots: { default: true }
                                      });
                                    } else if (app.domains_complete > 0) {
                                      $$renderer7.push("<!--[1-->");
                                      Badge($$renderer7, {
                                        variant: "warning",
                                        children: ($$renderer8) => {
                                          $$renderer8.push(`<!---->${escape_html(app.domains_complete)} of ${escape_html(app.domains_total)} aptitude areas`);
                                        },
                                        $$slots: { default: true }
                                      });
                                    } else {
                                      $$renderer7.push("<!--[-1-->");
                                      Badge($$renderer7, {
                                        variant: "outline",
                                        class: "gap-1",
                                        children: ($$renderer8) => {
                                          Circle($$renderer8, { class: "size-3" });
                                          $$renderer8.push(`<!----> Not started`);
                                        },
                                        $$slots: { default: true }
                                      });
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
                              $$renderer6.push(` `);
                              if (Table_cell) {
                                $$renderer6.push("<!--[-->");
                                Table_cell($$renderer6, {
                                  class: "px-4 py-3 text-center",
                                  children: ($$renderer7) => {
                                    Link($$renderer7, {
                                      href: `/grading/sessions/${session.id}/attempts/${app.attempt_id}`,
                                      children: ($$renderer8) => {
                                        Button($$renderer8, {
                                          variant: "outline",
                                          size: "sm",
                                          class: "h-8 px-2 text-xs font-semibold",
                                          children: ($$renderer9) => {
                                            if (isFinalized()) {
                                              $$renderer9.push("<!--[0-->");
                                              $$renderer9.push(`View scores`);
                                            } else {
                                              $$renderer9.push("<!--[-1-->");
                                              $$renderer9.push(`${escape_html(app.scored ? "Edit scores" : "Input scores")}`);
                                            }
                                            $$renderer9.push(`<!--]-->`);
                                          },
                                          $$slots: { default: true }
                                        });
                                      },
                                      $$slots: { default: true }
                                    });
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
                      }
                    } else {
                      $$renderer5.push("<!--[!-->");
                      if (Table_row) {
                        $$renderer5.push("<!--[-->");
                        Table_row($$renderer5, {
                          children: ($$renderer6) => {
                            if (Table_cell) {
                              $$renderer6.push("<!--[-->");
                              Table_cell($$renderer6, {
                                colspan: 4,
                                class: "px-4 py-12 text-center text-muted-foreground",
                                children: ($$renderer7) => {
                                  if (roster_stats.assigned === 0) {
                                    $$renderer7.push("<!--[0-->");
                                    $$renderer7.push(`No applicants assigned to this exam session yet.`);
                                  } else {
                                    $$renderer7.push("<!--[-1-->");
                                    $$renderer7.push(`No present-and-submitted applicants — nothing to grade.`);
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
        $$renderer3.push(`</div></div></div>`);
      }
    });
  });
}
export {
  GradingSession as default
};
