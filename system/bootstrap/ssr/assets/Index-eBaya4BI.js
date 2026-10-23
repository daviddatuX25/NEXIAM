import { h as head, e as escape_html, B as Button, a as ensure_array_like, d as derived } from "../app.js";
import { S as StaffLayout, M as Messages_square } from "./StaffLayout-CR-MZopa.js";
import { P as PageHeaderBar } from "./PageHeaderBar-DJhT5vOR.js";
import { S as SwitchableListView } from "./SwitchableListView-DHKJpEKr.js";
import { O as OperationalCard } from "./OperationalCard-D2Z4nRzj.js";
import { H as HeaderActions } from "./HeaderActions-Dxxt_yha.js";
import E2PageState from "./E2PageState-yFavAmgl.js";
import { B as Badge } from "./badge-D9Vgs7xh.js";
import { C as Card, a as Card_content } from "./card-content-Brg2wRkV.js";
import "clsx";
import { T as Table, a as Table_header, b as Table_row, c as Table_head, d as Table_body, e as Table_cell } from "./table-row-_ffWKrps.js";
import { F as File_text } from "./file-text-PXEtmXm9.js";
import { L as Link } from "./Link-B7ks7rBK.js";
import { A as Arrow_right } from "./arrow-right-CzynHiIo.js";
import { C as Circle_check } from "./circle-check-C59ZS7Zv.js";
import { L as Lock } from "./lock-DAA4kTLl.js";
import { P as Printer } from "./printer-Bx0z1CRb.js";
import "axios";
import "@inertiajs/core";
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
import "./roving-focus-group-BJDH9kUk.js";
import "./table-2-BLgeaPqt.js";
import "./layout-grid-CXJfSBqV.js";
import "./card-description-BGy_v5-d.js";
import "./card-title-BNY2ZPje.js";
import "./shield-alert-CXtDdte9.js";
function Index($$renderer, $$props) {
  $$renderer.component(($$renderer2) => {
    let {
      page_state = "ready",
      sessions = [],
      active_template = null,
      can_manage_templates = false
    } = $$props;
    let viewMode = "responsive";
    const totalExaminees = derived(() => (sessions ?? []).reduce((acc, s) => acc + (s.examinees_count || 0), 0));
    const totalPrepared = derived(() => (sessions ?? []).reduce((acc, s) => acc + (s.prepared_count || 0), 0));
    const totalPrinted = derived(() => (sessions ?? []).reduce((acc, s) => acc + (s.printed_count || 0), 0));
    const totalDelivered = derived(() => (sessions ?? []).reduce((acc, s) => acc + (s.delivered_count || 0), 0));
    const finalizedSessionsCount = derived(() => (sessions ?? []).filter((s) => s.status === "finalized").length);
    const releaseContext = derived(() => {
      const entityKey = "release-hub";
      const safetyKey = `${page_state}:${active_template ? "tmpl" : "notmpl"}:${sessions?.length ?? 0}:${totalExaminees()}:${totalPrepared()}:${totalPrinted()}:${totalDelivered()}:${finalizedSessionsCount()}`;
      return {
        entityKey,
        safetyKey,
        freshness: "current",
        pageState: page_state,
        hasActiveTemplate: active_template !== null,
        activeTemplateName: active_template?.name ?? null,
        canManageTemplates: Boolean(can_manage_templates),
        sessionsCount: sessions?.length ?? 0,
        totalExaminees: totalExaminees(),
        totalPrepared: totalPrepared(),
        totalPrinted: totalPrinted(),
        totalDelivered: totalDelivered(),
        finalizedSessionsCount: finalizedSessionsCount()
      };
    });
    const breadcrumbs = [{ label: "Result Documents & Printing" }];
    let $$settled = true;
    let $$inner_renderer;
    function $$render_inner($$renderer3) {
      head("24fzgj", $$renderer3, ($$renderer4) => {
        $$renderer4.title(($$renderer5) => {
          $$renderer5.push(`<title>Result Documents &amp; Printing · NEXIAM</title>`);
        });
      });
      StaffLayout($$renderer3, {
        breadcrumbs,
        children: ($$renderer4) => {
          $$renderer4.push(`<div class="min-w-0 space-y-6 p-4 sm:p-6">`);
          {
            let actions = function($$renderer5) {
              $$renderer5.push(`<div class="w-full min-w-0 sm:w-auto">`);
              if (can_manage_templates) {
                $$renderer5.push("<!--[0-->");
                {
                  let primaryAction = function($$renderer6) {
                    Link($$renderer6, {
                      href: "/consultation",
                      children: ($$renderer7) => {
                        Button($$renderer7, {
                          variant: "outline",
                          class: "min-h-10 w-full gap-2 font-medium sm:w-auto",
                          children: ($$renderer8) => {
                            Messages_square($$renderer8, { class: "size-4 text-muted-foreground" });
                            $$renderer8.push(`<!----> Consultation queue`);
                          },
                          $$slots: { default: true }
                        });
                      },
                      $$slots: { default: true }
                    });
                  }, secondaryActions = function($$renderer6) {
                    Link($$renderer6, {
                      href: "/setup/result-templates",
                      children: ($$renderer7) => {
                        Button($$renderer7, {
                          variant: "outline",
                          class: "min-h-10 w-full gap-2 font-medium sm:w-auto",
                          children: ($$renderer8) => {
                            Printer($$renderer8, { class: "size-4 text-muted-foreground" });
                            $$renderer8.push(`<!----> Result templates`);
                          },
                          $$slots: { default: true }
                        });
                      },
                      $$slots: { default: true }
                    });
                  };
                  HeaderActions($$renderer5, {
                    primaryAction,
                    secondaryActions
                  });
                }
              } else {
                $$renderer5.push("<!--[-1-->");
                {
                  let primaryAction = function($$renderer6) {
                    Link($$renderer6, {
                      href: "/consultation",
                      children: ($$renderer7) => {
                        Button($$renderer7, {
                          variant: "outline",
                          class: "min-h-10 w-full gap-2 font-medium sm:w-auto",
                          children: ($$renderer8) => {
                            Messages_square($$renderer8, { class: "size-4 text-muted-foreground" });
                            $$renderer8.push(`<!----> Consultation queue`);
                          },
                          $$slots: { default: true }
                        });
                      },
                      $$slots: { default: true }
                    });
                  };
                  HeaderActions($$renderer5, { primaryAction });
                }
              }
              $$renderer5.push(`<!--]--></div>`);
            };
            PageHeaderBar($$renderer4, {
              title: "Result Documents & Printing",
              description: "Prepare, print and track result document delivery by examination session.",
              tourKey: "release-batch-releasing",
              context: releaseContext(),
              actions
            });
          }
          $$renderer4.push(`<!----> `);
          E2PageState($$renderer4, { state: page_state });
          $$renderer4.push(`<!----> `);
          if (page_state === "ready") {
            $$renderer4.push("<!--[0-->");
            if (Card) {
              $$renderer4.push("<!--[-->");
              Card($$renderer4, {
                class: "border-border/70 bg-card",
                "data-tour": "release-active-template",
                children: ($$renderer5) => {
                  if (Card_content) {
                    $$renderer5.push("<!--[-->");
                    Card_content($$renderer5, {
                      class: "flex flex-col gap-4 p-4 sm:flex-row sm:items-center sm:justify-between",
                      children: ($$renderer6) => {
                        $$renderer6.push(`<div class="flex items-start gap-3"><div class="rounded-lg bg-primary/10 p-2.5 text-primary">`);
                        File_text($$renderer6, { class: "size-5" });
                        $$renderer6.push(`<!----></div> <div><div class="flex items-center gap-2"><h2 class="text-sm font-semibold text-foreground">Active Print Template</h2> `);
                        if (active_template) {
                          $$renderer6.push("<!--[0-->");
                          Badge($$renderer6, {
                            variant: "default",
                            class: "text-[11px]",
                            children: ($$renderer7) => {
                              $$renderer7.push(`<!---->Active`);
                            },
                            $$slots: { default: true }
                          });
                        } else {
                          $$renderer6.push("<!--[-1-->");
                          Badge($$renderer6, {
                            variant: "destructive",
                            class: "text-[11px]",
                            children: ($$renderer7) => {
                              $$renderer7.push(`<!---->No template active`);
                            },
                            $$slots: { default: true }
                          });
                        }
                        $$renderer6.push(`<!--]--></div> <p class="mt-0.5 text-xs text-muted-foreground">`);
                        if (active_template) {
                          $$renderer6.push("<!--[0-->");
                          $$renderer6.push(`<span class="font-medium text-foreground">${escape_html(active_template.name)}</span> (${escape_html(active_template.mode.toUpperCase())} · ${escape_html(active_template.paper_size.toUpperCase())} ${escape_html(active_template.orientation)})`);
                        } else {
                          $$renderer6.push("<!--[-1-->");
                          $$renderer6.push(`Result printing requires an active template. Create or activate one in Result Templates setup.`);
                        }
                        $$renderer6.push(`<!--]--></p></div></div> `);
                        if (can_manage_templates) {
                          $$renderer6.push("<!--[0-->");
                          Link($$renderer6, {
                            href: "/setup/result-templates",
                            class: "shrink-0",
                            children: ($$renderer7) => {
                              Button($$renderer7, {
                                variant: "outline",
                                size: "sm",
                                class: "gap-1.5 text-xs",
                                children: ($$renderer8) => {
                                  $$renderer8.push(`<!---->Configure templates `);
                                  Arrow_right($$renderer8, { class: "size-3" });
                                  $$renderer8.push(`<!---->`);
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
            $$renderer4.push(` <div class="space-y-3" data-tour="release-batch-sessions"><div class="flex items-center justify-between"><div><h2 class="text-base font-semibold text-foreground">Examination sessions</h2> <p class="text-xs text-muted-foreground">Select an examination session to open its dedicated batch printing workspace.</p></div></div> `);
            {
              let table = function($$renderer5) {
                $$renderer5.push(`<div class="rounded-xl border border-border/80 bg-card/60 backdrop-blur-md shadow-xs overflow-hidden">`);
                if (Table) {
                  $$renderer5.push("<!--[-->");
                  Table($$renderer5, {
                    children: ($$renderer6) => {
                      if (Table_header) {
                        $$renderer6.push("<!--[-->");
                        Table_header($$renderer6, {
                          children: ($$renderer7) => {
                            if (Table_row) {
                              $$renderer7.push("<!--[-->");
                              Table_row($$renderer7, {
                                children: ($$renderer8) => {
                                  if (Table_head) {
                                    $$renderer8.push("<!--[-->");
                                    Table_head($$renderer8, {
                                      children: ($$renderer9) => {
                                        $$renderer9.push(`<!---->Session &amp; Room`);
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
                                      children: ($$renderer9) => {
                                        $$renderer9.push(`<!---->Grading Status`);
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
                                      class: "text-right",
                                      children: ($$renderer9) => {
                                        $$renderer9.push(`<!---->Candidates`);
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
                                      class: "text-right",
                                      children: ($$renderer9) => {
                                        $$renderer9.push(`<!---->Document Progress`);
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
                                      class: "text-right",
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
                            if (sessions.length === 0) {
                              $$renderer7.push("<!--[0-->");
                              if (Table_row) {
                                $$renderer7.push("<!--[-->");
                                Table_row($$renderer7, {
                                  children: ($$renderer8) => {
                                    if (Table_cell) {
                                      $$renderer8.push("<!--[-->");
                                      Table_cell($$renderer8, {
                                        colspan: 5,
                                        class: "py-8 text-center text-sm text-muted-foreground",
                                        children: ($$renderer9) => {
                                          $$renderer9.push(`<!---->No examination grading sessions recorded. Complete an examination session and score examinees first.`);
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
                            } else {
                              $$renderer7.push("<!--[-1-->");
                              $$renderer7.push(`<!--[-->`);
                              const each_array = ensure_array_like(sessions);
                              for (let $$index = 0, $$length = each_array.length; $$index < $$length; $$index++) {
                                let session = each_array[$$index];
                                if (Table_row) {
                                  $$renderer7.push("<!--[-->");
                                  Table_row($$renderer7, {
                                    children: ($$renderer8) => {
                                      if (Table_cell) {
                                        $$renderer8.push("<!--[-->");
                                        Table_cell($$renderer8, {
                                          class: "min-w-[200px]",
                                          children: ($$renderer9) => {
                                            $$renderer9.push(`<div class="font-semibold text-foreground text-sm">${escape_html(session.starts_at)}</div> <div class="text-xs text-muted-foreground font-mono">${escape_html(session.room_name)} · Ref ${escape_html(session.exam_code)}</div>`);
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
                                          children: ($$renderer9) => {
                                            if (session.status === "finalized") {
                                              $$renderer9.push("<!--[0-->");
                                              Badge($$renderer9, {
                                                variant: "default",
                                                class: "gap-1 text-xs",
                                                children: ($$renderer10) => {
                                                  Circle_check($$renderer10, { class: "size-3" });
                                                  $$renderer10.push(`<!----> Finalized`);
                                                },
                                                $$slots: { default: true }
                                              });
                                            } else {
                                              $$renderer9.push("<!--[-1-->");
                                              Badge($$renderer9, {
                                                variant: "secondary",
                                                class: "gap-1 text-xs",
                                                children: ($$renderer10) => {
                                                  Lock($$renderer10, { class: "size-3" });
                                                  $$renderer10.push(`<!----> In Progress`);
                                                },
                                                $$slots: { default: true }
                                              });
                                            }
                                            $$renderer9.push(`<!--]-->`);
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
                                          class: "text-right font-mono tabular-nums font-medium",
                                          children: ($$renderer9) => {
                                            $$renderer9.push(`<!---->${escape_html(session.examinees_count)}`);
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
                                          class: "text-right",
                                          children: ($$renderer9) => {
                                            $$renderer9.push(`<div class="text-xs text-muted-foreground space-x-1.5"><span class="tabular-nums">Prep <strong class="text-foreground">${escape_html(session.prepared_count)}</strong></span> · <span class="tabular-nums">Print <strong class="text-primary">${escape_html(session.printed_count)}</strong></span> · <span class="tabular-nums">Deliv <strong class="text-foreground">${escape_html(session.delivered_count)}</strong></span></div>`);
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
                                          class: "text-right",
                                          children: ($$renderer9) => {
                                            Button($$renderer9, {
                                              href: route("release.batch", session.id),
                                              variant: "outline",
                                              size: "sm",
                                              class: "gap-1.5 min-h-9",
                                              children: ($$renderer10) => {
                                                Printer($$renderer10, { class: "size-3.5" });
                                                $$renderer10.push(`<!----> Batch Print`);
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
                $$renderer5.push(`</div>`);
              }, cards = function($$renderer5) {
                if (sessions.length === 0) {
                  $$renderer5.push("<!--[0-->");
                  $$renderer5.push(`<p class="py-12 text-center text-muted-foreground">No examination grading sessions recorded.</p>`);
                } else {
                  $$renderer5.push("<!--[-1-->");
                  $$renderer5.push(`<ul class="grid grid-cols-1 gap-4 md:grid-cols-2 xl:grid-cols-3" role="list"><!--[-->`);
                  const each_array_1 = ensure_array_like(sessions);
                  for (let $$index_1 = 0, $$length = each_array_1.length; $$index_1 < $$length; $$index_1++) {
                    let session = each_array_1[$$index_1];
                    {
                      let status = function($$renderer6) {
                        Badge($$renderer6, {
                          variant: session.status === "finalized" ? "default" : "secondary",
                          class: "text-[10px]",
                          children: ($$renderer7) => {
                            $$renderer7.push(`<!---->${escape_html(session.status === "finalized" ? "Finalized" : "In Progress")}`);
                          },
                          $$slots: { default: true }
                        });
                      }, summary = function($$renderer6) {
                        $$renderer6.push(`<div class="space-y-1"><div class="font-semibold text-foreground">Candidates: ${escape_html(session.examinees_count)}</div> <div class="text-xs text-muted-foreground">Prep <strong>${escape_html(session.prepared_count)}</strong> · Print <strong>${escape_html(session.printed_count)}</strong> · Deliv <strong>${escape_html(session.delivered_count)}</strong></div></div>`);
                      }, actions = function($$renderer6) {
                        Button($$renderer6, {
                          href: route("release.batch", session.id),
                          variant: "outline",
                          size: "sm",
                          class: "w-full gap-1.5 min-h-9",
                          children: ($$renderer7) => {
                            Printer($$renderer7, { class: "size-3.5" });
                            $$renderer7.push(`<!----> Batch Print`);
                          },
                          $$slots: { default: true }
                        });
                      };
                      OperationalCard($$renderer5, {
                        id: session.id,
                        title: session.starts_at,
                        subtitle: `${session.room_name} · Ref ${session.exam_code}`,
                        status,
                        summary,
                        actions,
                        $$slots: { status: true, summary: true, actions: true }
                      });
                    }
                  }
                  $$renderer5.push(`<!--]--></ul>`);
                }
                $$renderer5.push(`<!--]-->`);
              };
              SwitchableListView($$renderer4, {
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
  Index as default
};
