import { g as sanitize_props, i as spread_props, j as slot, e as escape_html, a as ensure_array_like, B as Button, b as attr, f as attr_style, s as stringify, c as attr_class, o as onDestroy, h as head, d as derived, n as newIdempotencyKey, a3 as csrfToken, u as usePage } from "../app.js";
import { S as StaffLayout } from "./StaffLayout-CR-MZopa.js";
import { P as PageHeaderBar } from "./PageHeaderBar-DJhT5vOR.js";
import { S as Square_check_big, R as Rotate_cw, M as MarkPrintedDialog } from "./MarkPrintedDialog-BNiMAKov.js";
import { C as Card, a as Card_content } from "./card-content-Brg2wRkV.js";
import "clsx";
import { B as Badge } from "./badge-D9Vgs7xh.js";
import { C as Circle_check } from "./circle-check-C59ZS7Zv.js";
import { D as Download } from "./download-n32NlL8c.js";
import { C as Circle_alert } from "./circle-alert-jWXJ7oC0.js";
import { T as Table, a as Table_header, b as Table_row, c as Table_head, d as Table_body, e as Table_cell } from "./table-row-_ffWKrps.js";
import { H as History } from "./history-D15LFKXr.js";
import { I as Icon } from "./Icon-Dal7PqKK.js";
import { P as Printer } from "./printer-Bx0z1CRb.js";
import { E as Eye } from "./eye-DyG-RiwW.js";
import { A as Arrow_left } from "./arrow-left-DbWnQR3t.js";
import { router } from "@inertiajs/core";
import "axios";
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
import "./dialog-description-D12L0QA9.js";
import "./refresh-cw-CYdk8aE0.js";
import "./camera-C09Eyogi.js";
import "./settings-C5xOKpV3.js";
import "./arrow-right-CzynHiIo.js";
import "./dialog-footer-kNnwKT2h.js";
import "./clock-B4fXoAi9.js";
import "./Link-B7ks7rBK.js";
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
function Square($$renderer, $$props) {
  const $$sanitized_props = sanitize_props($$props);
  const iconNode = [
    [
      "rect",
      { "width": "18", "height": "18", "x": "3", "y": "3", "rx": "2" }
    ]
  ];
  Icon($$renderer, spread_props([
    { name: "square" },
    $$sanitized_props,
    {
      /**
       * @component @name Square
       * @description Lucide SVG icon component, renders SVG Element with children.
       *
       * @preview ![img](data:image/svg+xml;base64,PHN2ZyAgeG1sbnM9Imh0dHA6Ly93d3cudzMub3JnLzIwMDAvc3ZnIgogIHdpZHRoPSIyNCIKICBoZWlnaHQ9IjI0IgogIHZpZXdCb3g9IjAgMCAyNCAyNCIKICBmaWxsPSJub25lIgogIHN0cm9rZT0iIzAwMCIgc3R5bGU9ImJhY2tncm91bmQtY29sb3I6ICNmZmY7IGJvcmRlci1yYWRpdXM6IDJweCIKICBzdHJva2Utd2lkdGg9IjIiCiAgc3Ryb2tlLWxpbmVjYXA9InJvdW5kIgogIHN0cm9rZS1saW5lam9pbj0icm91bmQiCj4KICA8cmVjdCB3aWR0aD0iMTgiIGhlaWdodD0iMTgiIHg9IjMiIHk9IjMiIHJ4PSIyIiAvPgo8L3N2Zz4K) - https://lucide.dev/icons/square
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
function PrintJobCard($$renderer, $$props) {
  $$renderer.component(($$renderer2) => {
    let {
      job = null,
      onretry = () => {
      },
      onmarkprinted = () => {
      },
      ondownload = () => {
      },
      class: className = ""
    } = $$props;
    function download(format = "pdf") {
      window.open(`/printing/jobs/${job.id}/download?format=${format}`, "_blank", "noopener");
      ondownload(format);
    }
    if (job) {
      $$renderer2.push("<!--[0-->");
      if (Card) {
        $$renderer2.push("<!--[-->");
        Card($$renderer2, {
          class: `overflow-hidden border-border/80 bg-card shadow-xs ${stringify(className)}`,
          children: ($$renderer3) => {
            if (Card_content) {
              $$renderer3.push("<!--[-->");
              Card_content($$renderer3, {
                class: "p-5",
                children: ($$renderer4) => {
                  if (job.status === "ready") {
                    $$renderer4.push("<!--[0-->");
                    $$renderer4.push(`<div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between"><div class="flex items-start gap-3.5"><div class="rounded-full bg-green-500/10 p-2 text-green-600 dark:text-green-400">`);
                    Circle_check($$renderer4, { class: "size-6" });
                    $$renderer4.push(`<!----></div> <div><div class="flex items-center gap-2"><h3 class="font-semibold text-foreground">Print Job #${escape_html(job.id)} is Ready</h3> `);
                    Badge($$renderer4, {
                      variant: "default",
                      class: "bg-green-600 text-[11px] text-white hover:bg-green-600",
                      children: ($$renderer5) => {
                        $$renderer5.push(`<!---->Ready`);
                      },
                      $$slots: { default: true }
                    });
                    $$renderer4.push(`<!----></div> <p class="mt-0.5 text-sm text-muted-foreground">All ${escape_html(job.progress_total ?? 1)} result sheet(s) have been rendered into a merged PDF.</p></div></div> <div class="flex flex-wrap items-center gap-2 pt-2 sm:pt-0"><!--[-->`);
                    const each_array = ensure_array_like(job.outputs ?? ["pdf"]);
                    for (let $$index = 0, $$length = each_array.length; $$index < $$length; $$index++) {
                      let output = each_array[$$index];
                      Button($$renderer4, {
                        class: "min-h-10 gap-2 font-semibold",
                        onclick: () => download(output),
                        children: ($$renderer5) => {
                          Download($$renderer5, { class: "size-4" });
                          $$renderer5.push(`<!----> ${escape_html(output === "pdf" ? "Download PDF" : output === "docx" ? "Download Word" : "Download Word ZIP")}`);
                        },
                        $$slots: { default: true }
                      });
                    }
                    $$renderer4.push(`<!--]--> `);
                    Button($$renderer4, {
                      variant: "outline",
                      class: "min-h-10 gap-2",
                      onclick: onmarkprinted,
                      children: ($$renderer5) => {
                        Square_check_big($$renderer5, { class: "size-4 text-muted-foreground" });
                        $$renderer5.push(`<!----> Mark as printed`);
                      },
                      $$slots: { default: true }
                    });
                    $$renderer4.push(`<!----></div></div> `);
                    if (job.items?.length) {
                      $$renderer4.push("<!--[0-->");
                      $$renderer4.push(`<div class="mt-3 grid gap-1.5 border-t border-border/40 pt-3 sm:grid-cols-2"><!--[-->`);
                      const each_array_1 = ensure_array_like(job.items);
                      for (let $$index_1 = 0, $$length = each_array_1.length; $$index_1 < $$length; $$index_1++) {
                        let item = each_array_1[$$index_1];
                        $$renderer4.push(`<div class="flex items-center justify-between rounded-md bg-muted/30 px-2.5 py-1.5 text-[11px]"><span class="truncate text-muted-foreground">${escape_html(item.name ?? `Item ${item.id}`)}</span> <div class="flex items-center gap-1">`);
                        Badge($$renderer4, {
                          variant: item.status === "ready" ? "default" : "destructive",
                          class: "text-[9px] uppercase",
                          children: ($$renderer5) => {
                            $$renderer5.push(`<!---->${escape_html(item.status)}`);
                          },
                          $$slots: { default: true }
                        });
                        $$renderer4.push(`<!----> `);
                        if (item.status === "ready") {
                          $$renderer4.push("<!--[0-->");
                          $$renderer4.push(`<a${attr("href", `/printing/jobs/${job.id}/items/${item.id}/download`)} target="_blank" rel="noopener" class="text-primary hover:underline">PDF</a> `);
                          if (job.outputs?.includes("docx")) {
                            $$renderer4.push("<!--[0-->");
                            $$renderer4.push(`<a${attr("href", `/printing/jobs/${job.id}/items/${item.id}/download?format=docx`)} target="_blank" rel="noopener" class="text-primary hover:underline">DOCX</a>`);
                          } else {
                            $$renderer4.push("<!--[-1-->");
                          }
                          $$renderer4.push(`<!--]-->`);
                        } else {
                          $$renderer4.push("<!--[-1-->");
                        }
                        $$renderer4.push(`<!--]--></div></div>`);
                      }
                      $$renderer4.push(`<!--]--></div>`);
                    } else {
                      $$renderer4.push("<!--[-1-->");
                    }
                    $$renderer4.push(`<!--]-->`);
                  } else if (job.status === "queued" || job.status === "rendering") {
                    $$renderer4.push("<!--[1-->");
                    $$renderer4.push(`<div class="space-y-3"><div class="flex items-center justify-between"><div class="flex items-center gap-2.5">`);
                    Rotate_cw($$renderer4, { class: "size-4 animate-spin text-primary" });
                    $$renderer4.push(`<!----> <h3 class="text-sm font-semibold text-foreground">Generating Print Job #${escape_html(job.id)}…</h3> `);
                    Badge($$renderer4, {
                      variant: "secondary",
                      class: "text-[11px] capitalize",
                      children: ($$renderer5) => {
                        $$renderer5.push(`<!---->${escape_html(job.status)}`);
                      },
                      $$slots: { default: true }
                    });
                    $$renderer4.push(`<!----></div> <span class="font-mono text-xs text-muted-foreground">${escape_html(job.progress_done ?? 0)} / ${escape_html(job.progress_total ?? 1)} sheets</span></div> <div class="h-2 w-full overflow-hidden rounded-full bg-muted"><div class="h-full bg-primary transition-all duration-300"${attr_style(`width: ${Math.max(8, Math.round((job.progress_done ?? 0) / (job.progress_total || 1) * 100))}%`)}></div></div> <p class="text-xs text-muted-foreground">Rendering result sheets in isolated worker jobs. This progress updates automatically.</p> `);
                    if (job.items?.length) {
                      $$renderer4.push("<!--[0-->");
                      $$renderer4.push(`<div class="mt-3 grid gap-1.5 border-t border-border/40 pt-3 sm:grid-cols-2"><!--[-->`);
                      const each_array_2 = ensure_array_like(job.items);
                      for (let $$index_2 = 0, $$length = each_array_2.length; $$index_2 < $$length; $$index_2++) {
                        let item = each_array_2[$$index_2];
                        $$renderer4.push(`<div class="flex items-center justify-between rounded-md bg-muted/30 px-2.5 py-1.5 text-[11px]"><span class="truncate text-muted-foreground">${escape_html(item.name ?? `Item ${item.id}`)}</span> `);
                        Badge($$renderer4, {
                          variant: item.status === "ready" ? "default" : item.status === "failed" ? "destructive" : "secondary",
                          class: "ml-2 text-[9px] uppercase",
                          children: ($$renderer5) => {
                            $$renderer5.push(`<!---->${escape_html(item.status)}`);
                          },
                          $$slots: { default: true }
                        });
                        $$renderer4.push(`<!----></div>`);
                      }
                      $$renderer4.push(`<!--]--></div>`);
                    } else {
                      $$renderer4.push("<!--[-1-->");
                    }
                    $$renderer4.push(`<!--]--></div>`);
                  } else if (job.status === "failed") {
                    $$renderer4.push("<!--[2-->");
                    $$renderer4.push(`<div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between"><div class="flex items-start gap-3"><div class="rounded-full bg-destructive/10 p-2 text-destructive">`);
                    Circle_alert($$renderer4, { class: "size-5" });
                    $$renderer4.push(`<!----></div> <div><h3 class="text-sm font-semibold text-destructive">Print Job #${escape_html(job.id)} Failed</h3> <p class="text-xs text-muted-foreground">${escape_html(job.safe_error ?? "PDF generation could not complete")}</p></div></div> `);
                    Button($$renderer4, {
                      size: "sm",
                      variant: "outline",
                      class: "gap-1.5",
                      onclick: onretry,
                      children: ($$renderer5) => {
                        Rotate_cw($$renderer5, { class: "size-3.5" });
                        $$renderer5.push(`<!----> Retry Print`);
                      },
                      $$slots: { default: true }
                    });
                    $$renderer4.push(`<!----></div>`);
                  } else {
                    $$renderer4.push("<!--[-1-->");
                  }
                  $$renderer4.push(`<!--]-->`);
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
function PrintJobHistory($$renderer, $$props) {
  $$renderer.component(($$renderer2) => {
    let { history = [], class: className = "" } = $$props;
    if (history.length > 0) {
      $$renderer2.push("<!--[0-->");
      $$renderer2.push(`<div${attr_class(`rounded-xl border border-border bg-card p-4 shadow-xs ${stringify(className)}`)}><div class="mb-3 flex items-center gap-2 border-b border-border/40 pb-2">`);
      History($$renderer2, { class: "size-4 text-primary" });
      $$renderer2.push(`<!----> <h3 class="text-xs font-semibold uppercase tracking-wider text-foreground">Session Print History</h3></div> <div class="overflow-x-auto">`);
      if (Table) {
        $$renderer2.push("<!--[-->");
        Table($$renderer2, {
          class: "w-full text-xs",
          children: ($$renderer3) => {
            if (Table_header) {
              $$renderer3.push("<!--[-->");
              Table_header($$renderer3, {
                class: "bg-muted/40",
                children: ($$renderer4) => {
                  if (Table_row) {
                    $$renderer4.push("<!--[-->");
                    Table_row($$renderer4, {
                      children: ($$renderer5) => {
                        if (Table_head) {
                          $$renderer5.push("<!--[-->");
                          Table_head($$renderer5, {
                            class: "py-2",
                            children: ($$renderer6) => {
                              $$renderer6.push(`<!---->Job`);
                            },
                            $$slots: { default: true }
                          });
                          $$renderer5.push("<!--]-->");
                        } else {
                          $$renderer5.push("<!--[!-->");
                          $$renderer5.push("<!--]-->");
                        }
                        $$renderer5.push(` `);
                        if (Table_head) {
                          $$renderer5.push("<!--[-->");
                          Table_head($$renderer5, {
                            class: "py-2",
                            children: ($$renderer6) => {
                              $$renderer6.push(`<!---->Status`);
                            },
                            $$slots: { default: true }
                          });
                          $$renderer5.push("<!--]-->");
                        } else {
                          $$renderer5.push("<!--[!-->");
                          $$renderer5.push("<!--]-->");
                        }
                        $$renderer5.push(` `);
                        if (Table_head) {
                          $$renderer5.push("<!--[-->");
                          Table_head($$renderer5, {
                            class: "py-2",
                            children: ($$renderer6) => {
                              $$renderer6.push(`<!---->Items`);
                            },
                            $$slots: { default: true }
                          });
                          $$renderer5.push("<!--]-->");
                        } else {
                          $$renderer5.push("<!--[!-->");
                          $$renderer5.push("<!--]-->");
                        }
                        $$renderer5.push(` `);
                        if (Table_head) {
                          $$renderer5.push("<!--[-->");
                          Table_head($$renderer5, {
                            class: "py-2",
                            children: ($$renderer6) => {
                              $$renderer6.push(`<!---->Requested By`);
                            },
                            $$slots: { default: true }
                          });
                          $$renderer5.push("<!--]-->");
                        } else {
                          $$renderer5.push("<!--[!-->");
                          $$renderer5.push("<!--]-->");
                        }
                        $$renderer5.push(` `);
                        if (Table_head) {
                          $$renderer5.push("<!--[-->");
                          Table_head($$renderer5, {
                            class: "py-2",
                            children: ($$renderer6) => {
                              $$renderer6.push(`<!---->Time`);
                            },
                            $$slots: { default: true }
                          });
                          $$renderer5.push("<!--]-->");
                        } else {
                          $$renderer5.push("<!--[!-->");
                          $$renderer5.push("<!--]-->");
                        }
                        $$renderer5.push(` `);
                        if (Table_head) {
                          $$renderer5.push("<!--[-->");
                          Table_head($$renderer5, {
                            class: "py-2 text-right",
                            children: ($$renderer6) => {
                              $$renderer6.push(`<!---->Action`);
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
            if (Table_body) {
              $$renderer3.push("<!--[-->");
              Table_body($$renderer3, {
                children: ($$renderer4) => {
                  $$renderer4.push(`<!--[-->`);
                  const each_array = ensure_array_like(history);
                  for (let $$index_1 = 0, $$length = each_array.length; $$index_1 < $$length; $$index_1++) {
                    let h = each_array[$$index_1];
                    if (Table_row) {
                      $$renderer4.push("<!--[-->");
                      Table_row($$renderer4, {
                        children: ($$renderer5) => {
                          if (Table_cell) {
                            $$renderer5.push("<!--[-->");
                            Table_cell($$renderer5, {
                              class: "font-mono font-medium",
                              children: ($$renderer6) => {
                                $$renderer6.push(`<!---->#${escape_html(h.id)}`);
                              },
                              $$slots: { default: true }
                            });
                            $$renderer5.push("<!--]-->");
                          } else {
                            $$renderer5.push("<!--[!-->");
                            $$renderer5.push("<!--]-->");
                          }
                          $$renderer5.push(` `);
                          if (Table_cell) {
                            $$renderer5.push("<!--[-->");
                            Table_cell($$renderer5, {
                              children: ($$renderer6) => {
                                Badge($$renderer6, {
                                  variant: h.status === "ready" ? "default" : h.status === "failed" ? "destructive" : "secondary",
                                  class: "text-[10px] uppercase",
                                  children: ($$renderer7) => {
                                    $$renderer7.push(`<!---->${escape_html(h.status)}`);
                                  },
                                  $$slots: { default: true }
                                });
                              },
                              $$slots: { default: true }
                            });
                            $$renderer5.push("<!--]-->");
                          } else {
                            $$renderer5.push("<!--[!-->");
                            $$renderer5.push("<!--]-->");
                          }
                          $$renderer5.push(` `);
                          if (Table_cell) {
                            $$renderer5.push("<!--[-->");
                            Table_cell($$renderer5, {
                              children: ($$renderer6) => {
                                $$renderer6.push(`<!---->${escape_html(h.ready_items)} / ${escape_html(h.progress_total)} ready `);
                                if (h.failed_items > 0) {
                                  $$renderer6.push("<!--[0-->");
                                  $$renderer6.push(`<span class="ml-1 text-destructive font-medium">(${escape_html(h.failed_items)} failed)</span>`);
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
                          $$renderer5.push(` `);
                          if (Table_cell) {
                            $$renderer5.push("<!--[-->");
                            Table_cell($$renderer5, {
                              class: "text-muted-foreground",
                              children: ($$renderer6) => {
                                $$renderer6.push(`<!---->${escape_html(h.requested_by)}`);
                              },
                              $$slots: { default: true }
                            });
                            $$renderer5.push("<!--]-->");
                          } else {
                            $$renderer5.push("<!--[!-->");
                            $$renderer5.push("<!--]-->");
                          }
                          $$renderer5.push(` `);
                          if (Table_cell) {
                            $$renderer5.push("<!--[-->");
                            Table_cell($$renderer5, {
                              class: "text-muted-foreground",
                              children: ($$renderer6) => {
                                $$renderer6.push(`<!---->${escape_html(h.requested_at)}`);
                              },
                              $$slots: { default: true }
                            });
                            $$renderer5.push("<!--]-->");
                          } else {
                            $$renderer5.push("<!--[!-->");
                            $$renderer5.push("<!--]-->");
                          }
                          $$renderer5.push(` `);
                          if (Table_cell) {
                            $$renderer5.push("<!--[-->");
                            Table_cell($$renderer5, {
                              class: "text-right",
                              children: ($$renderer6) => {
                                $$renderer6.push(`<!--[-->`);
                                const each_array_1 = ensure_array_like(h.outputs ?? (h.download_url ? ["pdf"] : []));
                                for (let $$index = 0, $$length2 = each_array_1.length; $$index < $$length2; $$index++) {
                                  let output = each_array_1[$$index];
                                  $$renderer6.push(`<a${attr("href", `${h.download_url}?format=${output}`)} target="_blank" rel="noopener">`);
                                  Button($$renderer6, {
                                    variant: "ghost",
                                    size: "sm",
                                    class: "h-7 gap-1 px-2 text-[11px]",
                                    children: ($$renderer7) => {
                                      Download($$renderer7, { class: "size-3" });
                                      $$renderer7.push(`<!----> ${escape_html(output === "pdf" ? "PDF" : output === "docx" ? "Word" : "ZIP")}`);
                                    },
                                    $$slots: { default: true }
                                  });
                                  $$renderer6.push(`<!----></a>`);
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
                  }
                  $$renderer4.push(`<!--]-->`);
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
      $$renderer2.push(`</div></div>`);
    } else {
      $$renderer2.push("<!--[-1-->");
    }
    $$renderer2.push(`<!--]-->`);
  });
}
function PrintBatch($$renderer, $$props) {
  $$renderer.component(($$renderer2) => {
    let {
      session = {},
      applicants = [],
      template_present = true,
      job_history = []
    } = $$props;
    const page = usePage();
    const token = derived(() => page.props.csrf_token || csrfToken());
    const printableApplicants = derived(() => applicants.filter((a) => a.document_ready && a.document_id));
    let selected = new Set(printableApplicants().filter((a) => !a.printed).map((a) => a.attempt_id));
    let copies = 1;
    let job = null;
    let isRequesting = false;
    let jobError = null;
    let pollTimer = null;
    let showMarkDialog = false;
    let markDialogDocumentIds = [];
    let markDialogCount = 1;
    onDestroy(() => {
      if (pollTimer) {
        clearInterval(pollTimer);
        pollTimer = null;
      }
    });
    const allSelected = derived(() => printableApplicants().length > 0 && printableApplicants().every((a) => selected.has(a.attempt_id)));
    const selectedApplicants = derived(() => applicants.filter((a) => selected.has(a.attempt_id)));
    const selectedUnprinted = derived(() => selectedApplicants().filter((a) => a.document_ready && a.document_id && !a.printed));
    const breadcrumbs = [
      { label: "Release", href: "/release" },
      { label: `Session ${session.code ?? session.id}` },
      { label: "Print result sheets" }
    ];
    function toggleAll() {
      selected = allSelected() ? /* @__PURE__ */ new Set() : new Set(printableApplicants().map((a) => a.attempt_id));
    }
    async function requestPrint(printedOnly = false) {
      jobError = null;
      const rows = applicants.filter((a) => selected.has(a.attempt_id) && a.document_ready && a.document_id && (!printedOnly || !a.printed));
      const documentIds = rows.map((a) => a.document_id);
      if (documentIds.length === 0) return;
      isRequesting = true;
      try {
        const res = await fetch("/release/print", {
          method: "POST",
          headers: {
            "Content-Type": "application/json",
            Accept: "application/json",
            "X-Requested-With": "XMLHttpRequest",
            "X-CSRF-TOKEN": token()
          },
          credentials: "same-origin",
          body: JSON.stringify({ document_ids: documentIds, copies })
        });
        if (!res.ok) {
          const data2 = await res.json().catch(() => ({}));
          jobError = data2.message ?? `Print request failed (${res.status})`;
          isRequesting = false;
          return;
        }
        const data = await res.json();
        job = data;
        isRequesting = false;
        pollJob(data.id);
      } catch (err) {
        jobError = String(err);
        isRequesting = false;
      }
    }
    async function pollJob(id) {
      if (pollTimer) clearInterval(pollTimer);
      pollTimer = setInterval(
        async () => {
          try {
            const res = await fetch(`/printing/jobs/${id}`, {
              headers: {
                Accept: "application/json",
                "X-Requested-With": "XMLHttpRequest"
              },
              credentials: "same-origin"
            });
            if (!res.ok) return;
            const data = await res.json();
            job = data;
            if (data.status === "ready" || data.status === "failed") {
              clearInterval(pollTimer);
              pollTimer = null;
            }
          } catch {
            clearInterval(pollTimer);
            pollTimer = null;
          }
        },
        1200
      );
    }
    function promptMarkForReadyJob() {
      if (!job) return;
      const documentIds = applicants.filter((a) => selected.has(a.attempt_id) && a.document_ready && a.document_id).map((a) => a.document_id);
      markDialogDocumentIds = documentIds;
      markDialogCount = documentIds.length;
      showMarkDialog = true;
    }
    function promptMarkSelected() {
      const documentIds = selectedApplicants().filter((a) => a.document_ready && a.document_id).map((a) => a.document_id);
      markDialogDocumentIds = documentIds;
      markDialogCount = documentIds.length;
      showMarkDialog = true;
    }
    async function togglePrintedSingle(app) {
      if (!app.document_id) return;
      let reason;
      if (app.printed) {
        reason = window.prompt("Reason for correcting the physical print confirmation?");
        if (!reason?.trim()) return;
      }
      await fetch("/release/print/mark-printed", {
        method: "POST",
        headers: {
          "Content-Type": "application/json",
          Accept: "application/json",
          "X-Requested-With": "XMLHttpRequest",
          "X-CSRF-TOKEN": token()
        },
        credentials: "same-origin",
        body: JSON.stringify({
          document_ids: [app.document_id],
          printed: !app.printed,
          idempotency_key: newIdempotencyKey(),
          ...reason ? { reason } : {}
        })
      });
      router.reload();
    }
    const canPrint = derived(() => template_present && selectedUnprinted().length > 0);
    const previewUrl = derived(() => selectedUnprinted().length > 0 ? `/release/print/batches/${session.id}/preview?document_ids=${selectedUnprinted().map((a) => a.document_id).join(",")}` : null);
    head("1sh4o4k", $$renderer2, ($$renderer3) => {
      $$renderer3.title(($$renderer4) => {
        $$renderer4.push(`<title>Print result sheets · Release</title>`);
      });
    });
    StaffLayout($$renderer2, {
      breadcrumbs,
      children: ($$renderer3) => {
        $$renderer3.push(`<div class="min-w-0 space-y-6 p-4 sm:p-6">`);
        {
          let actions = function($$renderer4) {
            Button($$renderer4, {
              variant: "outline",
              size: "sm",
              class: "gap-1.5",
              onclick: () => router.visit("/release"),
              children: ($$renderer5) => {
                Arrow_left($$renderer5, { class: "size-4" });
                $$renderer5.push(`<!----> Back to release`);
              },
              $$slots: { default: true }
            });
          };
          PageHeaderBar($$renderer3, {
            title: "Print result sheets — Batch",
            description: `Session ${session.code ?? ""} · ${session.exam_date ?? ""} · ${session.room_name ?? "—"}. Select prepared result documents, generate PDFs, and confirm physical printing.`,
            actions
          });
        }
        $$renderer3.push(`<!----> <div class="flex flex-wrap items-center gap-3 rounded-xl border border-border bg-card p-4 shadow-xs">`);
        Button($$renderer3, {
          variant: "outline",
          size: "sm",
          class: "min-h-10",
          onclick: toggleAll,
          children: ($$renderer4) => {
            if (allSelected()) {
              $$renderer4.push("<!--[0-->");
              Square($$renderer4, { class: "size-4 mr-2" });
            } else {
              $$renderer4.push("<!--[-1-->");
              Square_check_big($$renderer4, { class: "size-4 mr-2" });
            }
            $$renderer4.push(`<!--]--> ${escape_html(allSelected() ? "Deselect all" : "Select all")}`);
          },
          $$slots: { default: true }
        });
        $$renderer3.push(`<!----> `);
        Button($$renderer3, {
          variant: "outline",
          size: "sm",
          class: "min-h-10",
          onclick: () => selected = new Set(printableApplicants().filter((a) => !a.printed).map((a) => a.attempt_id)),
          children: ($$renderer4) => {
            $$renderer4.push(`<!---->Select All Unprinted`);
          },
          $$slots: { default: true }
        });
        $$renderer3.push(`<!----> `);
        Button($$renderer3, {
          variant: "outline",
          size: "sm",
          class: "min-h-10",
          onclick: () => selected = /* @__PURE__ */ new Set(),
          children: ($$renderer4) => {
            $$renderer4.push(`<!---->Deselect All`);
          },
          $$slots: { default: true }
        });
        $$renderer3.push(`<!----> `);
        Button($$renderer3, {
          variant: "default",
          size: "sm",
          class: "min-h-10 gap-2 font-semibold",
          disabled: !canPrint() || isRequesting || job && (job.status === "queued" || job.status === "rendering"),
          onclick: () => requestPrint(true),
          children: ($$renderer4) => {
            if (isRequesting || job && (job.status === "queued" || job.status === "rendering")) {
              $$renderer4.push("<!--[0-->");
              Rotate_cw($$renderer4, { class: "size-4 animate-spin" });
              $$renderer4.push(`<!----> Rendering…`);
            } else {
              $$renderer4.push("<!--[-1-->");
              Printer($$renderer4, { class: "size-4" });
              $$renderer4.push(`<!----> Print ${escape_html(selectedUnprinted().length > 0 ? `(${selectedUnprinted().length})` : "")}`);
            }
            $$renderer4.push(`<!--]-->`);
          },
          $$slots: { default: true }
        });
        $$renderer3.push(`<!----> <div class="flex items-center gap-2 rounded-lg border border-border/80 bg-muted/20 px-3 py-1.5 text-xs"><label for="copies" class="font-medium text-muted-foreground">Copies</label> <input id="copies" type="number" min="1" max="10"${attr("value", copies)} class="w-12 rounded border border-input bg-card px-2 py-0.5 font-mono text-xs font-semibold text-center"/></div> `);
        if (previewUrl()) {
          $$renderer3.push("<!--[0-->");
          $$renderer3.push(`<a${attr("href", previewUrl())} target="_blank" rel="noopener">`);
          Button($$renderer3, {
            variant: "outline",
            size: "sm",
            class: "min-h-10 gap-1.5",
            children: ($$renderer4) => {
              Eye($$renderer4, { class: "size-4 text-muted-foreground" });
              $$renderer4.push(`<!----> Preview stack`);
            },
            $$slots: { default: true }
          });
          $$renderer3.push(`<!----></a>`);
        } else {
          $$renderer3.push("<!--[-1-->");
        }
        $$renderer3.push(`<!--]--> `);
        if (selectedApplicants().length > 0) {
          $$renderer3.push("<!--[0-->");
          Button($$renderer3, {
            variant: "outline",
            size: "sm",
            class: "min-h-10 gap-1.5",
            onclick: promptMarkSelected,
            children: ($$renderer4) => {
              Square_check_big($$renderer4, { class: "size-4 text-muted-foreground" });
              $$renderer4.push(`<!----> Mark ${escape_html(selectedApplicants().length)} as printed…`);
            },
            $$slots: { default: true }
          });
        } else {
          $$renderer3.push("<!--[-1-->");
        }
        $$renderer3.push(`<!--]--> `);
        if (jobError) {
          $$renderer3.push("<!--[0-->");
          $$renderer3.push(`<p class="text-xs font-medium text-destructive">${escape_html(jobError)}</p>`);
        } else {
          $$renderer3.push("<!--[-1-->");
        }
        $$renderer3.push(`<!--]--></div> `);
        if (job) {
          $$renderer3.push("<!--[0-->");
          PrintJobCard($$renderer3, {
            job,
            onretry: () => requestPrint(false),
            onmarkprinted: promptMarkForReadyJob
          });
        } else {
          $$renderer3.push("<!--[-1-->");
        }
        $$renderer3.push(`<!--]--> <div class="overflow-hidden rounded-xl border border-border bg-card shadow-xs">`);
        if (Table) {
          $$renderer3.push("<!--[-->");
          Table($$renderer3, {
            class: "w-full min-w-[900px]",
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
                              class: "w-10 px-4 py-3",
                              children: ($$renderer7) => {
                                $$renderer7.push(`<button type="button"${attr("aria-label", allSelected() ? "Deselect all" : "Select all")} class="p-1">`);
                                if (allSelected()) {
                                  $$renderer7.push("<!--[0-->");
                                  Square_check_big($$renderer7, { class: "size-5 text-primary" });
                                } else {
                                  $$renderer7.push("<!--[-1-->");
                                  Square($$renderer7, { class: "size-5 text-muted-foreground" });
                                }
                                $$renderer7.push(`<!--]--></button>`);
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
                              class: "px-4 py-3 font-semibold",
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
                              class: "px-4 py-3 font-semibold",
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
                              class: "px-4 py-3 font-semibold",
                              children: ($$renderer7) => {
                                $$renderer7.push(`<!---->Document Status`);
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
                              class: "px-4 py-3 font-semibold",
                              children: ($$renderer7) => {
                                $$renderer7.push(`<!---->Printed Status`);
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
                              class: "px-4 py-3 font-semibold",
                              children: ($$renderer7) => {
                                $$renderer7.push(`<!---->Delivery Status`);
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
                              class: "px-4 py-3 text-right font-semibold",
                              children: ($$renderer7) => {
                                $$renderer7.push(`<!---->Actions`);
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
                    $$renderer5.push(`<!--[-->`);
                    const each_array = ensure_array_like(applicants);
                    for (let $$index_1 = 0, $$length = each_array.length; $$index_1 < $$length; $$index_1++) {
                      let app = each_array[$$index_1];
                      if (Table_row) {
                        $$renderer5.push("<!--[-->");
                        Table_row($$renderer5, {
                          children: ($$renderer6) => {
                            if (Table_cell) {
                              $$renderer6.push("<!--[-->");
                              Table_cell($$renderer6, {
                                class: "px-4 py-3",
                                children: ($$renderer7) => {
                                  $$renderer7.push(`<button type="button"${attr("disabled", !app.document_ready || !app.document_id, true)} aria-label="Toggle select" class="p-1 disabled:opacity-30">`);
                                  if (selected.has(app.attempt_id)) {
                                    $$renderer7.push("<!--[0-->");
                                    Square_check_big($$renderer7, { class: "size-5 text-primary" });
                                  } else {
                                    $$renderer7.push("<!--[-1-->");
                                    Square($$renderer7, { class: "size-5 text-muted-foreground" });
                                  }
                                  $$renderer7.push(`<!--]--></button>`);
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
                                class: "px-4 py-3 font-mono text-xs font-medium",
                                children: ($$renderer7) => {
                                  $$renderer7.push(`<!---->${escape_html(app.reference)}`);
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
                                class: "px-4 py-3 font-medium",
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
                                  Badge($$renderer7, {
                                    variant: app.document_ready ? "default" : "secondary",
                                    class: "text-[11px]",
                                    children: ($$renderer8) => {
                                      $$renderer8.push(`<!---->${escape_html(app.document_ready ? "Prepared" : "Not prepared")}`);
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
                            $$renderer6.push(` `);
                            if (Table_cell) {
                              $$renderer6.push("<!--[-->");
                              Table_cell($$renderer6, {
                                class: "px-4 py-3",
                                children: ($$renderer7) => {
                                  Badge($$renderer7, {
                                    variant: app.printed ? "default" : "outline",
                                    class: "text-[11px]",
                                    children: ($$renderer8) => {
                                      $$renderer8.push(`<!---->${escape_html(app.printed ? "Printed" : "Not printed")}`);
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
                            $$renderer6.push(` `);
                            if (Table_cell) {
                              $$renderer6.push("<!--[-->");
                              Table_cell($$renderer6, {
                                class: "px-4 py-3",
                                children: ($$renderer7) => {
                                  if (app.delivery_channels?.length) {
                                    $$renderer7.push("<!--[0-->");
                                    $$renderer7.push(`<div class="flex flex-wrap gap-1"><!--[-->`);
                                    const each_array_1 = ensure_array_like(app.delivery_channels);
                                    for (let $$index = 0, $$length2 = each_array_1.length; $$index < $$length2; $$index++) {
                                      let channel = each_array_1[$$index];
                                      Badge($$renderer7, {
                                        variant: "secondary",
                                        class: "text-[11px]",
                                        children: ($$renderer8) => {
                                          $$renderer8.push(`<!---->${escape_html(channel === "portal" ? "Portal" : channel === "physical" ? "Physical" : channel)}`);
                                        },
                                        $$slots: { default: true }
                                      });
                                    }
                                    $$renderer7.push(`<!--]--></div>`);
                                  } else {
                                    $$renderer7.push("<!--[-1-->");
                                    Badge($$renderer7, {
                                      variant: "outline",
                                      class: "text-[11px]",
                                      children: ($$renderer8) => {
                                        $$renderer8.push(`<!---->Not Delivered`);
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
                                class: "px-4 py-3 text-right",
                                children: ($$renderer7) => {
                                  $$renderer7.push(`<div class="flex items-center justify-end gap-1.5"><a${attr("href", `/release/print/batches/${session.id}/applicants/${app.attempt_id}`)} target="_blank" rel="noopener">`);
                                  Button($$renderer7, {
                                    variant: "outline",
                                    size: "sm",
                                    class: "h-8 gap-1 px-2.5 text-xs",
                                    disabled: !app.document_ready,
                                    children: ($$renderer8) => {
                                      Eye($$renderer8, { class: "size-3.5 text-muted-foreground" });
                                      $$renderer8.push(`<!----> View`);
                                    },
                                    $$slots: { default: true }
                                  });
                                  $$renderer7.push(`<!----></a> `);
                                  if (app.document_ready) {
                                    $$renderer7.push("<!--[0-->");
                                    if (app.printed) {
                                      $$renderer7.push("<!--[0-->");
                                      Button($$renderer7, {
                                        variant: "ghost",
                                        size: "sm",
                                        class: "h-8 px-2 text-xs text-muted-foreground",
                                        onclick: () => togglePrintedSingle(app),
                                        children: ($$renderer8) => {
                                          $$renderer8.push(`<!---->Correct print`);
                                        },
                                        $$slots: { default: true }
                                      });
                                    } else {
                                      $$renderer7.push("<!--[-1-->");
                                      Button($$renderer7, {
                                        variant: "outline",
                                        size: "sm",
                                        class: "h-8 px-2 text-xs",
                                        onclick: () => togglePrintedSingle(app),
                                        children: ($$renderer8) => {
                                          $$renderer8.push(`<!---->Mark printed`);
                                        },
                                        $$slots: { default: true }
                                      });
                                    }
                                    $$renderer7.push(`<!--]-->`);
                                  } else {
                                    $$renderer7.push("<!--[-1-->");
                                  }
                                  $$renderer7.push(`<!--]--></div>`);
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
        $$renderer3.push(`</div> `);
        if (job_history?.length > 0) {
          $$renderer3.push("<!--[0-->");
          PrintJobHistory($$renderer3, { history: job_history });
        } else {
          $$renderer3.push("<!--[-1-->");
        }
        $$renderer3.push(`<!--]--></div> `);
        MarkPrintedDialog($$renderer3, {
          open: showMarkDialog,
          documentIds: markDialogDocumentIds,
          count: markDialogCount,
          onclose: () => showMarkDialog = false,
          onmarked: () => router.reload()
        });
        $$renderer3.push(`<!---->`);
      }
    });
  });
}
export {
  PrintBatch as default
};
