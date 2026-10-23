import { g as sanitize_props, i as spread_props, j as slot, h as head, e as escape_html, a as ensure_array_like, b as attr, B as Button } from "../app.js";
import { S as StaffLayout } from "./StaffLayout-CR-MZopa.js";
import { P as PageHeaderBar } from "./PageHeaderBar-DJhT5vOR.js";
import { B as Badge } from "./badge-D9Vgs7xh.js";
import { I as Input } from "./input-BUZAZKIK.js";
import { L as Label } from "./label-dGY6L9vm.js";
import { T as Table, a as Table_header, b as Table_row, c as Table_head, d as Table_body, e as Table_cell } from "./table-row-_ffWKrps.js";
import "clsx";
import { u as useForm } from "./useForm.svelte-C0OkDFGu.js";
import { S as Shield_check } from "./shield-check-CLSg-ako.js";
import { I as Icon } from "./Icon-Dal7PqKK.js";
import { L as Link } from "./Link-B7ks7rBK.js";
import { A as Arrow_left } from "./arrow-left-DbWnQR3t.js";
import { P as Pencil } from "./pencil-0tPPVae8.js";
import "axios";
import "@inertiajs/core";
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
import "./dialog-description-D12L0QA9.js";
import "./circle-alert-jWXJ7oC0.js";
import "./refresh-cw-CYdk8aE0.js";
import "./camera-C09Eyogi.js";
import "./settings-C5xOKpV3.js";
import "./arrow-right-CzynHiIo.js";
import "./circle-check-C59ZS7Zv.js";
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
import "es-toolkit";
import "./clone-BJz-lxsY.js";
import "es-toolkit/compat";
function Archive($$renderer, $$props) {
  const $$sanitized_props = sanitize_props($$props);
  const iconNode = [
    [
      "rect",
      { "width": "20", "height": "5", "x": "2", "y": "3", "rx": "1" }
    ],
    ["path", { "d": "M4 8v11a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8" }],
    ["path", { "d": "M10 12h4" }]
  ];
  Icon($$renderer, spread_props([
    { name: "archive" },
    $$sanitized_props,
    {
      /**
       * @component @name Archive
       * @description Lucide SVG icon component, renders SVG Element with children.
       *
       * @preview ![img](data:image/svg+xml;base64,PHN2ZyAgeG1sbnM9Imh0dHA6Ly93d3cudzMub3JnLzIwMDAvc3ZnIgogIHdpZHRoPSIyNCIKICBoZWlnaHQ9IjI0IgogIHZpZXdCb3g9IjAgMCAyNCAyNCIKICBmaWxsPSJub25lIgogIHN0cm9rZT0iIzAwMCIgc3R5bGU9ImJhY2tncm91bmQtY29sb3I6ICNmZmY7IGJvcmRlci1yYWRpdXM6IDJweCIKICBzdHJva2Utd2lkdGg9IjIiCiAgc3Ryb2tlLWxpbmVjYXA9InJvdW5kIgogIHN0cm9rZS1saW5lam9pbj0icm91bmQiCj4KICA8cmVjdCB3aWR0aD0iMjAiIGhlaWdodD0iNSIgeD0iMiIgeT0iMyIgcng9IjEiIC8+CiAgPHBhdGggZD0iTTQgOHYxMWEyIDIgMCAwIDAgMiAyaDEyYTIgMiAwIDAgMCAyLTJWOCIgLz4KICA8cGF0aCBkPSJNMTAgMTJoNCIgLz4KPC9zdmc+Cg==) - https://lucide.dev/icons/archive
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
function Show($$renderer, $$props) {
  $$renderer.component(($$renderer2) => {
    let { norm_package, request_id } = $$props;
    const breadcrumbs = [
      { label: "Setup", href: "/setup" },
      { label: "Norm packages", href: "/setup/norm-packages" },
      { label: norm_package.code }
    ];
    const approvalForm = useForm({
      request_id,
      expected_revision: null,
      source_review_attestation: false
    });
    const retirementForm = useForm({ request_id, expected_revision: null, reason: "" });
    function statusLabel(status) {
      if (status === "approved") return "Approved";
      if (status === "draft") return "Draft";
      return "Retired";
    }
    function statusVariant(status) {
      if (status === "approved") return "default";
      if (status === "draft") return "secondary";
      return "outline";
    }
    function groupIntervals(tables = []) {
      const metrics = /* @__PURE__ */ new Map();
      for (const table of tables) {
        if (!metrics.has(table.metric_code)) {
          metrics.set(table.metric_code, /* @__PURE__ */ new Map());
        }
        const intervals = metrics.get(table.metric_code);
        for (const interval of table.intervals_json ?? []) {
          const key = `${interval.min_score}:${interval.max_score}`;
          const row = intervals.get(key) ?? {
            min: interval.min_score,
            max: interval.max_score,
            percentile_rank: null,
            stanine: null
          };
          if (table.output_scale === "percentile_rank" || table.output_scale === "stanine") {
            row[table.output_scale] = interval.output_value;
          }
          intervals.set(key, row);
        }
      }
      return [...metrics.entries()].map(([metric_code, intervals]) => ({
        metric_code,
        rows: [...intervals.values()].sort((left, right) => left.min - right.min)
      }));
    }
    function approve(version) {
      approvalForm.expected_revision = version.lock_version;
      approvalForm.post(`/setup/norm-packages/${norm_package.id}/versions/${version.id}/approve`);
    }
    let $$settled = true;
    let $$inner_renderer;
    function $$render_inner($$renderer3) {
      head("137iz16", $$renderer3, ($$renderer4) => {
        $$renderer4.title(($$renderer5) => {
          $$renderer5.push(`<title>${escape_html(norm_package.display_name)} · Norm packages</title>`);
        });
      });
      StaffLayout($$renderer3, {
        breadcrumbs,
        children: ($$renderer4) => {
          $$renderer4.push(`<div class="min-w-0 space-y-6 p-4 sm:p-6">`);
          {
            let actions = function($$renderer5) {
              Link($$renderer5, {
                href: "/setup/norm-packages",
                children: ($$renderer6) => {
                  Button($$renderer6, {
                    variant: "outline",
                    class: "min-h-10 gap-2",
                    children: ($$renderer7) => {
                      Arrow_left($$renderer7, { class: "h-4 w-4" });
                      $$renderer7.push(`<!---->Back to packages`);
                    },
                    $$slots: { default: true }
                  });
                },
                $$slots: { default: true }
              });
              $$renderer5.push(`<!----> `);
              Link($$renderer5, {
                href: `/setup/norm-packages/${norm_package.id}/edit`,
                children: ($$renderer6) => {
                  Button($$renderer6, {
                    variant: "outline",
                    class: "min-h-10 gap-2",
                    children: ($$renderer7) => {
                      Pencil($$renderer7, { class: "h-4 w-4" });
                      $$renderer7.push(`<!---->${escape_html(norm_package.status === "draft" ? "Edit draft" : "Create successor draft")}`);
                    },
                    $$slots: { default: true }
                  });
                },
                $$slots: { default: true }
              });
              $$renderer5.push(`<!---->`);
            };
            PageHeaderBar($$renderer4, {
              title: norm_package.display_name,
              description: `${norm_package.code}${norm_package.version_number ? ` · Version ${norm_package.version_number}` : ""} · Confidential norm package`,
              actions
            });
          }
          $$renderer4.push(`<!----> `);
          if (norm_package.is_synthetic) {
            $$renderer4.push("<!--[0-->");
            $$renderer4.push(`<div class="rounded-xl border border-amber-500/40 bg-amber-500/10 p-4 text-sm" role="status">Synthetic · software only. This package is not eligible for institutional assessment, conversion, finalization, or release.</div>`);
          } else {
            $$renderer4.push("<!--[-1-->");
          }
          $$renderer4.push(`<!--]--> <section class="border-border bg-card rounded-xl border p-4 sm:p-6" aria-labelledby="package-details-heading"><div class="flex flex-wrap items-center justify-between gap-3"><h2 id="package-details-heading" class="font-semibold">Package details</h2> `);
          Badge($$renderer4, {
            variant: statusVariant(norm_package.status),
            children: ($$renderer5) => {
              $$renderer5.push(`<!---->${escape_html(statusLabel(norm_package.status))}`);
            },
            $$slots: { default: true }
          });
          $$renderer4.push(`<!----></div> <dl class="mt-4 grid gap-4 text-sm sm:grid-cols-2 xl:grid-cols-3"><div><dt class="text-muted-foreground">Code</dt> <dd class="mt-1 font-mono font-medium">${escape_html(norm_package.code)}</dd></div> <div><dt class="text-muted-foreground">Publisher</dt> <dd class="mt-1 font-medium">${escape_html(norm_package.publisher_name || "—")}</dd></div> <div><dt class="text-muted-foreground">Form code</dt> <dd class="mt-1 font-medium">${escape_html(norm_package.form_code || "—")}</dd></div> <div><dt class="text-muted-foreground">Edition</dt> <dd class="mt-1 font-medium">${escape_html(norm_package.edition || "—")}</dd></div> <div><dt class="text-muted-foreground">Language</dt> <dd class="mt-1 font-medium">${escape_html(norm_package.language || "—")}</dd></div> <div><dt class="text-muted-foreground">Applicable population</dt> <dd class="mt-1 font-medium">${escape_html(norm_package.applicable_population || "—")}</dd></div> <div class="sm:col-span-2 xl:col-span-3"><dt class="text-muted-foreground">Source reference</dt> <dd class="mt-1 whitespace-pre-wrap break-words">${escape_html(norm_package.source_reference || "Not recorded")}</dd></div> <div class="sm:col-span-2 xl:col-span-3"><dt class="text-muted-foreground">Permitted use reference</dt> <dd class="mt-1 whitespace-pre-wrap break-words">${escape_html(norm_package.permitted_use_reference || "Not recorded")}</dd></div></dl></section> <section class="space-y-4" aria-labelledby="versions-heading"><div><h2 id="versions-heading" class="text-lg font-semibold">Versions and conversion tables</h2> <p class="text-muted-foreground mt-1 text-sm">Approved versions are immutable. A later edit creates a successor draft; accepted conversions retain their original version and table identities.</p></div> `);
          const each_array = ensure_array_like(norm_package.versions);
          if (each_array.length !== 0) {
            $$renderer4.push("<!--[-->");
            for (let $$index_2 = 0, $$length = each_array.length; $$index_2 < $$length; $$index_2++) {
              let version = each_array[$$index_2];
              $$renderer4.push(`<article class="border-border bg-card min-w-0 space-y-5 rounded-xl border p-4 sm:p-6"><div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between"><div class="min-w-0"><h3 class="font-semibold">Version ${escape_html(version.version_number)}: ${escape_html(version.display_name)}</h3> <p class="text-muted-foreground mt-1 text-sm">${escape_html(version.publisher_name || "Publisher not recorded")}
                                ${escape_html(version.form_code ? ` · Form ${version.form_code}` : "")}
                                ${escape_html(version.edition ? ` · ${version.edition}` : "")}
                                ${escape_html(version.language ? ` · ${version.language}` : "")}</p></div> `);
              Badge($$renderer4, {
                variant: statusVariant(version.status),
                children: ($$renderer5) => {
                  $$renderer5.push(`<!---->${escape_html(statusLabel(version.status))}`);
                },
                $$slots: { default: true }
              });
              $$renderer4.push(`<!----></div> <dl class="grid gap-3 text-sm sm:grid-cols-2 xl:grid-cols-3"><div><dt class="text-muted-foreground">Applicable population</dt> <dd class="mt-1">${escape_html(version.applicable_population || "—")}</dd></div> <div><dt class="text-muted-foreground">Source review</dt> <dd class="mt-1">${escape_html(version.source_reviewed_at || "Not reviewed")}</dd></div> <div><dt class="text-muted-foreground">Approved</dt> <dd class="mt-1">${escape_html(version.approved_at || "Not approved")}</dd></div> `);
              if (version.retired_at) {
                $$renderer4.push("<!--[0-->");
                $$renderer4.push(`<div><dt class="text-muted-foreground">Retired</dt> <dd class="mt-1">${escape_html(version.retired_at)}</dd></div>`);
              } else {
                $$renderer4.push("<!--[-1-->");
              }
              $$renderer4.push(`<!--]--> <div class="sm:col-span-2 xl:col-span-3"><dt class="text-muted-foreground">Source and permitted use</dt> <dd class="mt-1 whitespace-pre-wrap break-words">${escape_html(version.source_reference || "Source not recorded")}</dd> <dd class="text-muted-foreground mt-1 whitespace-pre-wrap break-words">${escape_html(version.permitted_use_reference || "Permitted use not recorded")}</dd></div></dl> `);
              if (version.tables.length === 0) {
                $$renderer4.push("<!--[0-->");
                $$renderer4.push(`<p class="border-border text-muted-foreground rounded-lg border border-dashed p-4 text-sm">No conversion tables are recorded in this version.</p>`);
              } else {
                $$renderer4.push("<!--[-1-->");
                $$renderer4.push(`<div class="space-y-4"><!--[-->`);
                const each_array_1 = ensure_array_like(groupIntervals(version.tables));
                for (let $$index_1 = 0, $$length2 = each_array_1.length; $$index_1 < $$length2; $$index_1++) {
                  let metric = each_array_1[$$index_1];
                  $$renderer4.push(`<div class="min-w-0"><h4 class="mb-2 text-sm font-semibold">Raw metric: <span class="font-mono">${escape_html(metric.metric_code)}</span></h4> <div class="overflow-x-auto rounded-lg border border-border">`);
                  if (Table) {
                    $$renderer4.push("<!--[-->");
                    Table($$renderer4, {
                      class: "min-w-160",
                      children: ($$renderer5) => {
                        if (Table_header) {
                          $$renderer5.push("<!--[-->");
                          Table_header($$renderer5, {
                            children: ($$renderer6) => {
                              if (Table_row) {
                                $$renderer6.push("<!--[-->");
                                Table_row($$renderer6, {
                                  children: ($$renderer7) => {
                                    if (Table_head) {
                                      $$renderer7.push("<!--[-->");
                                      Table_head($$renderer7, {
                                        children: ($$renderer8) => {
                                          $$renderer8.push(`<!---->min`);
                                        },
                                        $$slots: { default: true }
                                      });
                                      $$renderer7.push("<!--]-->");
                                    } else {
                                      $$renderer7.push("<!--[!-->");
                                      $$renderer7.push("<!--]-->");
                                    }
                                    $$renderer7.push(` `);
                                    if (Table_head) {
                                      $$renderer7.push("<!--[-->");
                                      Table_head($$renderer7, {
                                        children: ($$renderer8) => {
                                          $$renderer8.push(`<!---->max`);
                                        },
                                        $$slots: { default: true }
                                      });
                                      $$renderer7.push("<!--]-->");
                                    } else {
                                      $$renderer7.push("<!--[!-->");
                                      $$renderer7.push("<!--]-->");
                                    }
                                    $$renderer7.push(` `);
                                    if (Table_head) {
                                      $$renderer7.push("<!--[-->");
                                      Table_head($$renderer7, {
                                        children: ($$renderer8) => {
                                          $$renderer8.push(`<!---->percentile_rank`);
                                        },
                                        $$slots: { default: true }
                                      });
                                      $$renderer7.push("<!--]-->");
                                    } else {
                                      $$renderer7.push("<!--[!-->");
                                      $$renderer7.push("<!--]-->");
                                    }
                                    $$renderer7.push(` `);
                                    if (Table_head) {
                                      $$renderer7.push("<!--[-->");
                                      Table_head($$renderer7, {
                                        children: ($$renderer8) => {
                                          $$renderer8.push(`<!---->stanine`);
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
                            },
                            $$slots: { default: true }
                          });
                          $$renderer5.push("<!--]-->");
                        } else {
                          $$renderer5.push("<!--[!-->");
                          $$renderer5.push("<!--]-->");
                        }
                        $$renderer5.push(` `);
                        if (Table_body) {
                          $$renderer5.push("<!--[-->");
                          Table_body($$renderer5, {
                            children: ($$renderer6) => {
                              $$renderer6.push(`<!--[-->`);
                              const each_array_2 = ensure_array_like(metric.rows);
                              for (let rowIndex = 0, $$length3 = each_array_2.length; rowIndex < $$length3; rowIndex++) {
                                let row = each_array_2[rowIndex];
                                if (Table_row) {
                                  $$renderer6.push("<!--[-->");
                                  Table_row($$renderer6, {
                                    children: ($$renderer7) => {
                                      if (Table_cell) {
                                        $$renderer7.push("<!--[-->");
                                        Table_cell($$renderer7, {
                                          children: ($$renderer8) => {
                                            $$renderer8.push(`<!---->${escape_html(row.min)}`);
                                          },
                                          $$slots: { default: true }
                                        });
                                        $$renderer7.push("<!--]-->");
                                      } else {
                                        $$renderer7.push("<!--[!-->");
                                        $$renderer7.push("<!--]-->");
                                      }
                                      $$renderer7.push(` `);
                                      if (Table_cell) {
                                        $$renderer7.push("<!--[-->");
                                        Table_cell($$renderer7, {
                                          children: ($$renderer8) => {
                                            $$renderer8.push(`<!---->${escape_html(row.max)}`);
                                          },
                                          $$slots: { default: true }
                                        });
                                        $$renderer7.push("<!--]-->");
                                      } else {
                                        $$renderer7.push("<!--[!-->");
                                        $$renderer7.push("<!--]-->");
                                      }
                                      $$renderer7.push(` `);
                                      if (Table_cell) {
                                        $$renderer7.push("<!--[-->");
                                        Table_cell($$renderer7, {
                                          children: ($$renderer8) => {
                                            $$renderer8.push(`<!---->${escape_html(row.percentile_rank ?? "—")}`);
                                          },
                                          $$slots: { default: true }
                                        });
                                        $$renderer7.push("<!--]-->");
                                      } else {
                                        $$renderer7.push("<!--[!-->");
                                        $$renderer7.push("<!--]-->");
                                      }
                                      $$renderer7.push(` `);
                                      if (Table_cell) {
                                        $$renderer7.push("<!--[-->");
                                        Table_cell($$renderer7, {
                                          children: ($$renderer8) => {
                                            $$renderer8.push(`<!---->${escape_html(row.stanine ?? "—")}`);
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
                  $$renderer4.push(`</div></div>`);
                }
                $$renderer4.push(`<!--]--></div>`);
              }
              $$renderer4.push(`<!--]--> `);
              if (version.status === "draft") {
                $$renderer4.push("<!--[0-->");
                $$renderer4.push(`<div class="border-border bg-muted/20 space-y-3 rounded-lg border p-4"><label class="flex items-start gap-3 text-sm"><input type="checkbox"${attr("checked", approvalForm.source_review_attestation, true)} class="accent-primary mt-1 size-4"/> <span>I affirm that I reviewed and verified this version's source, permitted use, form, applicability, and conversion table content before approval.</span></label> `);
                if (approvalForm.errors.source_review_attestation) {
                  $$renderer4.push("<!--[0-->");
                  $$renderer4.push(`<p class="text-destructive text-sm" role="alert">${escape_html(approvalForm.errors.source_review_attestation)}</p>`);
                } else {
                  $$renderer4.push("<!--[-1-->");
                }
                $$renderer4.push(`<!--]--> `);
                if (approvalForm.errors.tables) {
                  $$renderer4.push("<!--[0-->");
                  $$renderer4.push(`<p class="text-destructive text-sm" role="alert">${escape_html(approvalForm.errors.tables)}</p>`);
                } else {
                  $$renderer4.push("<!--[-1-->");
                }
                $$renderer4.push(`<!--]--> `);
                if (approvalForm.errors.expected_revision) {
                  $$renderer4.push("<!--[0-->");
                  $$renderer4.push(`<p class="text-destructive text-sm" role="alert">${escape_html(approvalForm.errors.expected_revision)}</p>`);
                } else {
                  $$renderer4.push("<!--[-1-->");
                }
                $$renderer4.push(`<!--]--> `);
                if (approvalForm.errors.version) {
                  $$renderer4.push("<!--[0-->");
                  $$renderer4.push(`<p class="text-destructive text-sm" role="alert">${escape_html(approvalForm.errors.version)}</p>`);
                } else {
                  $$renderer4.push("<!--[-1-->");
                }
                $$renderer4.push(`<!--]--> `);
                Button($$renderer4, {
                  class: "min-h-10 gap-2",
                  disabled: approvalForm.processing || !approvalForm.source_review_attestation,
                  onclick: () => approve(version),
                  children: ($$renderer5) => {
                    Shield_check($$renderer5, { class: "h-4 w-4" });
                    $$renderer5.push(`<!---->Approve version`);
                  },
                  $$slots: { default: true }
                });
                $$renderer4.push(`<!----></div>`);
              } else if (version.status === "approved") {
                $$renderer4.push("<!--[1-->");
                $$renderer4.push(`<form class="grid gap-3 border-t border-border pt-4 sm:grid-cols-[minmax(0,1fr)_auto] sm:items-end"><div class="space-y-1.5">`);
                Label($$renderer4, {
                  for: `retire-reason-${version.id}`,
                  children: ($$renderer5) => {
                    $$renderer5.push(`<!---->Retirement reason`);
                  },
                  $$slots: { default: true }
                });
                $$renderer4.push(`<!----> `);
                Input($$renderer4, {
                  id: `retire-reason-${version.id}`,
                  maxlength: "500",
                  required: true,
                  get value() {
                    return retirementForm.reason;
                  },
                  set value($$value) {
                    retirementForm.reason = $$value;
                    $$settled = false;
                  }
                });
                $$renderer4.push(`<!----> `);
                if (retirementForm.errors.reason) {
                  $$renderer4.push("<!--[0-->");
                  $$renderer4.push(`<p class="text-destructive text-sm" role="alert">${escape_html(retirementForm.errors.reason)}</p>`);
                } else {
                  $$renderer4.push("<!--[-1-->");
                }
                $$renderer4.push(`<!--]--> `);
                if (retirementForm.errors.expected_revision) {
                  $$renderer4.push("<!--[0-->");
                  $$renderer4.push(`<p class="text-destructive text-sm" role="alert">${escape_html(retirementForm.errors.expected_revision)}</p>`);
                } else {
                  $$renderer4.push("<!--[-1-->");
                }
                $$renderer4.push(`<!--]--> `);
                if (retirementForm.errors.version) {
                  $$renderer4.push("<!--[0-->");
                  $$renderer4.push(`<p class="text-destructive text-sm" role="alert">${escape_html(retirementForm.errors.version)}</p>`);
                } else {
                  $$renderer4.push("<!--[-1-->");
                }
                $$renderer4.push(`<!--]--></div> `);
                Button($$renderer4, {
                  type: "submit",
                  variant: "destructive",
                  class: "min-h-10 gap-2",
                  disabled: retirementForm.processing,
                  children: ($$renderer5) => {
                    Archive($$renderer5, { class: "h-4 w-4" });
                    $$renderer5.push(`<!---->Retire version`);
                  },
                  $$slots: { default: true }
                });
                $$renderer4.push(`<!----></form>`);
              } else {
                $$renderer4.push("<!--[-1-->");
              }
              $$renderer4.push(`<!--]--></article>`);
            }
          } else {
            $$renderer4.push("<!--[!-->");
            $$renderer4.push(`<div class="border-border text-muted-foreground rounded-xl border border-dashed p-8 text-center text-sm">This package has no version history.</div>`);
          }
          $$renderer4.push(`<!--]--></section> <div class="border-border bg-muted/20 text-muted-foreground rounded-xl border p-4 text-sm">Norm package tables are restricted to authorized Guidance catalog staff. Scorer-facing selection and accepted outputs must not expose these table rows.</div></div>`);
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
  Show as default
};
