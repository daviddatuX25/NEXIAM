import { d as derived, h as head, e as escape_html, b as attr, B as Button, a as ensure_array_like } from "../app.js";
import { S as StaffLayout } from "./StaffLayout-CR-MZopa.js";
import { P as PageHeaderBar } from "./PageHeaderBar-DJhT5vOR.js";
import { B as Badge } from "./badge-D9Vgs7xh.js";
import { I as Input } from "./input-BUZAZKIK.js";
import { L as Label } from "./label-dGY6L9vm.js";
import { T as Table, a as Table_header, b as Table_row, c as Table_head, d as Table_body, e as Table_cell } from "./table-row-_ffWKrps.js";
import "clsx";
import { u as useForm } from "./useForm.svelte-C0OkDFGu.js";
import { S as Shield_check } from "./shield-check-CLSg-ako.js";
import { P as Plus } from "./plus-DjyPLC7A.js";
import { L as Link } from "./Link-B7ks7rBK.js";
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
function Show($$renderer, $$props) {
  $$renderer.component(($$renderer2) => {
    let { instrument, request_id } = $$props;
    let draft = derived(() => instrument.draft_version);
    let approved = derived(() => instrument.current_approved_version);
    const approvalForm = useForm({
      request_id,
      expected_revision: draft()?.lock_version ?? null,
      source_review_attestation: false
    });
    const successorForm = useForm({
      request_id,
      expected_revision: approved()?.lock_version ?? null
    });
    const retirementForm = useForm({
      request_id,
      expected_revision: approved()?.lock_version ?? null,
      reason: ""
    });
    const breadcrumbs = [
      { label: "Setup", href: "/setup" },
      { label: "Test instruments", href: "/setup/test-instruments" },
      { label: instrument.code }
    ];
    let $$settled = true;
    let $$inner_renderer;
    function $$render_inner($$renderer3) {
      head("1bz4c65", $$renderer3, ($$renderer4) => {
        $$renderer4.title(($$renderer5) => {
          $$renderer5.push(`<title>${escape_html(instrument.code)} · Test instruments</title>`);
        });
      });
      StaffLayout($$renderer3, {
        breadcrumbs,
        children: ($$renderer4) => {
          $$renderer4.push(`<div class="min-w-0 space-y-6 p-4 sm:p-6">`);
          {
            let actions = function($$renderer5) {
              Link($$renderer5, {
                href: "/setup/test-instruments",
                children: ($$renderer6) => {
                  Button($$renderer6, {
                    variant: "outline",
                    class: "min-h-10",
                    children: ($$renderer7) => {
                      $$renderer7.push(`<!---->Back to instruments`);
                    },
                    $$slots: { default: true }
                  });
                },
                $$slots: { default: true }
              });
              $$renderer5.push(`<!----> `);
              if (draft()) {
                $$renderer5.push("<!--[0-->");
                Link($$renderer5, {
                  href: `/setup/test-instruments/${instrument.id}/edit`,
                  children: ($$renderer6) => {
                    Button($$renderer6, {
                      class: "min-h-10",
                      children: ($$renderer7) => {
                        $$renderer7.push(`<!---->Edit draft`);
                      },
                      $$slots: { default: true }
                    });
                  },
                  $$slots: { default: true }
                });
              } else {
                $$renderer5.push("<!--[-1-->");
              }
              $$renderer5.push(`<!--]-->`);
            };
            PageHeaderBar($$renderer4, {
              title: draft()?.display_name ?? approved()?.display_name ?? instrument.code,
              description: `Instrument ${instrument.code}${approved()?.form_code ? ` · ${approved().form_code}` : ""}`,
              actions
            });
          }
          $$renderer4.push(`<!----> `);
          if (instrument.is_synthetic) {
            $$renderer4.push("<!--[0-->");
            $$renderer4.push(`<div class="rounded-xl border border-amber-500/40 bg-amber-500/10 p-4 text-sm" role="status"><strong>Synthetic software fixture.</strong> This profile is never
                eligible for institution approval, exam assignment, scoring, or normative
                results.</div>`);
          } else {
            $$renderer4.push("<!--[-1-->");
          }
          $$renderer4.push(`<!--]--> <section class="grid gap-3 sm:grid-cols-2 lg:grid-cols-3" aria-label="Profile readiness"><div class="border-border bg-card rounded-xl border p-4"><p class="text-muted-foreground text-xs font-medium tracking-wide uppercase">Source review</p> <p class="mt-2 font-semibold">${escape_html(instrument.source_readiness === "reviewed" ? "Reviewed" : "Not verified")}</p> <p class="text-muted-foreground mt-1 text-sm">Approval requires the official identity and permitted-use
                    references to be reviewed by Guidance.</p></div> <div class="border-border bg-card rounded-xl border p-4"><p class="text-muted-foreground text-xs font-medium tracking-wide uppercase">Raw-scoring profile</p> <p class="mt-2 font-semibold">${escape_html(instrument.raw_readiness === "approved" ? "Profile rules approved" : "Draft only")}</p> <p class="text-muted-foreground mt-1 text-sm">An approved profile still needs an exact
                    material/administration binding before scoring.</p></div> <div class="border-border bg-card rounded-xl border p-4"><p class="text-muted-foreground text-xs font-medium tracking-wide uppercase">Norm conversion</p> <p class="mt-2 font-semibold">${escape_html(instrument.norm_readiness === "missing_package" ? "Norm package missing" : "Not available for this profile")}</p> <p class="text-muted-foreground mt-1 text-sm">Profile approval does not approve a norm package or enable
                    finalization.</p></div></section> `);
          if (draft()) {
            $$renderer4.push("<!--[0-->");
            $$renderer4.push(`<section class="border-border bg-card space-y-3 rounded-xl border p-4 sm:p-5" aria-labelledby="draft-heading"><div class="flex flex-wrap items-start justify-between gap-3"><div><h2 id="draft-heading" class="font-semibold">Draft version ${escape_html(draft().version_number)}</h2> <p class="text-muted-foreground text-sm">Revision ${escape_html(draft().lock_version)} · not available for scoring
                            or administration binding</p></div> `);
            Badge($$renderer4, {
              variant: "secondary",
              children: ($$renderer5) => {
                $$renderer5.push(`<!---->Draft`);
              },
              $$slots: { default: true }
            });
            $$renderer4.push(`<!----></div> <dl class="grid gap-3 text-sm sm:grid-cols-2 lg:grid-cols-3"><div><dt class="text-muted-foreground">Publisher</dt> <dd>${escape_html(draft().publisher_name ?? "Unverified")}</dd></div> <div><dt class="text-muted-foreground">Form</dt> <dd>${escape_html(draft().form_code ?? "Unspecified")}</dd></div> <div><dt class="text-muted-foreground">Edition / language</dt> <dd>${escape_html(draft().edition ?? "Unspecified")} · ${escape_html(draft().language ?? "Unspecified")}</dd></div> <div><dt class="text-muted-foreground">Source reference</dt> <dd class="break-words">${escape_html(draft().source_reference ?? "Not recorded")}</dd></div> <div><dt class="text-muted-foreground">Permitted-use reference</dt> <dd class="break-words">${escape_html(draft().permitted_use_reference ?? "Not recorded")}</dd></div></dl> `);
            if (instrument.is_synthetic) {
              $$renderer4.push("<!--[0-->");
              $$renderer4.push(`<p class="border-border bg-muted/30 text-muted-foreground rounded-lg border p-3 text-sm">Synthetic fixtures cannot be approved.</p>`);
            } else {
              $$renderer4.push("<!--[-1-->");
              $$renderer4.push(`<form class="border-border space-y-3 border-t pt-4"><label class="border-border flex items-start gap-3 rounded-lg border p-3 text-sm"><input type="checkbox"${attr("checked", approvalForm.source_review_attestation, true)} class="accent-primary mt-1 size-4"/> <span>I reviewed the official instrument/form/scoring
                                source and permitted-use evidence referenced
                                above. This attestation does not accept
                                unverified values.</span></label> `);
              if (approvalForm.errors.source_review_attestation) {
                $$renderer4.push("<!--[0-->");
                $$renderer4.push(`<p class="text-destructive text-sm" role="alert">${escape_html(approvalForm.errors.source_review_attestation)}</p>`);
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
                type: "submit",
                disabled: approvalForm.processing || !approvalForm.source_review_attestation,
                class: "min-h-10 gap-2",
                children: ($$renderer5) => {
                  Shield_check($$renderer5, { class: "h-4 w-4" });
                  $$renderer5.push(`<!---->Approve version`);
                },
                $$slots: { default: true }
              });
              $$renderer4.push(`<!----></form>`);
            }
            $$renderer4.push(`<!--]--></section>`);
          } else if (approved()) {
            $$renderer4.push("<!--[1-->");
            $$renderer4.push(`<section class="flex flex-wrap items-center justify-between gap-3 rounded-xl border border-emerald-500/30 bg-emerald-500/5 p-4" aria-label="Current approved version"><div><h2 class="font-semibold">Current approved version ${escape_html(approved().version_number)}</h2> <p class="text-muted-foreground text-sm">Approved ${escape_html(approved().approved_at ?? "after source review")}. This exact version remains
                        immutable.</p></div> `);
            Badge($$renderer4, {
              children: ($$renderer5) => {
                $$renderer5.push(`<!---->Approved`);
              },
              $$slots: { default: true }
            });
            $$renderer4.push(`<!----></section>`);
          } else {
            $$renderer4.push("<!--[-1-->");
            $$renderer4.push(`<section class="border-border text-muted-foreground rounded-xl border border-dashed p-5 text-sm">No approved version is current. Existing profile history remains
                available below.</section>`);
          }
          $$renderer4.push(`<!--]--> `);
          if (approved()) {
            $$renderer4.push("<!--[0-->");
            $$renderer4.push(`<section class="border-border bg-card space-y-4 rounded-xl border p-4 sm:p-5" aria-labelledby="lifecycle-heading"><h2 id="lifecycle-heading" class="font-semibold">Version lifecycle</h2> `);
            if (!draft()) {
              $$renderer4.push("<!--[0-->");
              $$renderer4.push(`<form class="flex flex-wrap items-end gap-2">`);
              if (successorForm.errors.version) {
                $$renderer4.push("<!--[0-->");
                $$renderer4.push(`<p class="text-destructive w-full text-sm" role="alert">${escape_html(successorForm.errors.version)}</p>`);
              } else {
                $$renderer4.push("<!--[-1-->");
              }
              $$renderer4.push(`<!--]--> `);
              Button($$renderer4, {
                type: "submit",
                variant: "outline",
                disabled: successorForm.processing,
                class: "min-h-10 gap-2",
                children: ($$renderer5) => {
                  Plus($$renderer5, { class: "h-4 w-4" });
                  $$renderer5.push(`<!---->Create successor draft`);
                },
                $$slots: { default: true }
              });
              $$renderer4.push(`<!----></form>`);
            } else {
              $$renderer4.push("<!--[-1-->");
            }
            $$renderer4.push(`<!--]--> <form class="grid gap-3 sm:grid-cols-2 sm:items-end"><div class="space-y-1.5">`);
            Label($$renderer4, {
              for: "retirement-reason",
              children: ($$renderer5) => {
                $$renderer5.push(`<!---->Retirement reason`);
              },
              $$slots: { default: true }
            });
            $$renderer4.push(`<!----> `);
            Input($$renderer4, {
              id: "retirement-reason",
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
            $$renderer4.push(`<!--]--></div> `);
            Button($$renderer4, {
              type: "submit",
              variant: "destructive",
              disabled: retirementForm.processing,
              class: "min-h-10",
              children: ($$renderer5) => {
                $$renderer5.push(`<!---->Retire current version`);
              },
              $$slots: { default: true }
            });
            $$renderer4.push(`<!----></form></section>`);
          } else {
            $$renderer4.push("<!--[-1-->");
          }
          $$renderer4.push(`<!--]--> <section class="space-y-4" aria-labelledby="version-history-heading"><h2 id="version-history-heading" class="text-lg font-semibold">Version history</h2> <!--[-->`);
          const each_array = ensure_array_like(instrument.versions);
          for (let $$index_2 = 0, $$length = each_array.length; $$index_2 < $$length; $$index_2++) {
            let version = each_array[$$index_2];
            $$renderer4.push(`<article class="border-border bg-card space-y-4 rounded-xl border p-4 sm:p-5"><div class="flex flex-wrap items-start justify-between gap-3"><div><h3 class="font-semibold">${escape_html(version.display_name)} · version ${escape_html(version.version_number)}</h3> <p class="text-muted-foreground text-sm">${escape_html(version.form_code ?? "Form unspecified")} · ${escape_html(version.edition ?? "Edition unspecified")} · ${escape_html(version.language ?? "Language unspecified")}</p></div> `);
            Badge($$renderer4, {
              variant: version.status === "approved" ? "default" : "secondary",
              children: ($$renderer5) => {
                $$renderer5.push(`<!---->${escape_html(version.status)}`);
              },
              $$slots: { default: true }
            });
            $$renderer4.push(`<!----></div> <p class="text-muted-foreground text-sm">Source: ${escape_html(version.source_reference ?? "Not recorded")} · Permitted
                        use: ${escape_html(version.permitted_use_reference ?? "Not recorded")}</p> `);
            if (version.subtests.length > 0) {
              $$renderer4.push("<!--[0-->");
              $$renderer4.push(`<div class="border-border overflow-x-auto rounded-lg border">`);
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
                                      $$renderer8.push(`<!---->Test section / subtest`);
                                    },
                                    $$slots: { default: true }
                                  });
                                  $$renderer7.push("<!--]-->");
                                } else {
                                  $$renderer7.push("<!--[!-->");
                                  $$renderer7.push("<!--]-->");
                                }
                                if (Table_head) {
                                  $$renderer7.push("<!--[-->");
                                  Table_head($$renderer7, {
                                    children: ($$renderer8) => {
                                      $$renderer8.push(`<!---->Items`);
                                    },
                                    $$slots: { default: true }
                                  });
                                  $$renderer7.push("<!--]-->");
                                } else {
                                  $$renderer7.push("<!--[!-->");
                                  $$renderer7.push("<!--]-->");
                                }
                                if (Table_head) {
                                  $$renderer7.push("<!--[-->");
                                  Table_head($$renderer7, {
                                    children: ($$renderer8) => {
                                      $$renderer8.push(`<!---->Raw metrics`);
                                    },
                                    $$slots: { default: true }
                                  });
                                  $$renderer7.push("<!--]-->");
                                } else {
                                  $$renderer7.push("<!--[!-->");
                                  $$renderer7.push("<!--]-->");
                                }
                                if (Table_head) {
                                  $$renderer7.push("<!--[-->");
                                  Table_head($$renderer7, {
                                    children: ($$renderer8) => {
                                      $$renderer8.push(`<!---->Order policy`);
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
                          const each_array_1 = ensure_array_like(version.subtests);
                          for (let $$index = 0, $$length2 = each_array_1.length; $$index < $$length2; $$index++) {
                            let subtest = each_array_1[$$index];
                            if (Table_row) {
                              $$renderer6.push("<!--[-->");
                              Table_row($$renderer6, {
                                children: ($$renderer7) => {
                                  if (Table_cell) {
                                    $$renderer7.push("<!--[-->");
                                    Table_cell($$renderer7, {
                                      children: ($$renderer8) => {
                                        $$renderer8.push(`<span class="font-medium">${escape_html(subtest.name)}</span><span class="text-muted-foreground ml-2 font-mono text-xs">· ${escape_html(subtest.code)}</span>`);
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
                                        $$renderer8.push(`<!---->${escape_html(subtest.items.length)} · ${escape_html(subtest.items.filter((item) => item.is_scored).length)} scored`);
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
                                        $$renderer8.push(`<!---->${escape_html(version.metrics.filter((metric) => metric.subtest_code === subtest.code).map((metric) => metric.code).join(", ") || "None")}`);
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
                                        $$renderer8.push(`<!---->${escape_html(subtest.item_order_policy === "fixed" ? "Fixed" : "Within subtest")}`);
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
              $$renderer4.push(`</div>`);
            } else {
              $$renderer4.push("<!--[-1-->");
            }
            $$renderer4.push(`<!--]--> `);
            if (version.metrics.length > 0) {
              $$renderer4.push("<!--[0-->");
              $$renderer4.push(`<div class="space-y-2"><h4 class="text-sm font-semibold">Raw metrics and derived relationships</h4> <ul class="grid gap-2 md:grid-cols-2" role="list"><!--[-->`);
              const each_array_2 = ensure_array_like(version.metrics);
              for (let $$index_1 = 0, $$length2 = each_array_2.length; $$index_1 < $$length2; $$index_1++) {
                let metric = each_array_2[$$index_1];
                $$renderer4.push(`<li class="border-border rounded-lg border p-3 text-sm"><div class="flex flex-wrap items-center justify-between gap-2"><span class="font-medium">${escape_html(metric.name)}</span> `);
                Badge($$renderer4, {
                  variant: "outline",
                  children: ($$renderer5) => {
                    $$renderer5.push(`<!---->${escape_html(metric.rule_kind)}`);
                  },
                  $$slots: { default: true }
                });
                $$renderer4.push(`<!----></div> <p class="text-muted-foreground mt-1 font-mono text-xs">${escape_html(metric.code)} · ${escape_html(metric.min_raw_value ?? "bounds pending")}–${escape_html(metric.max_raw_value ?? "pending")}
                                            ${escape_html(metric.unit ?? "unit pending")} · precision
                                            ${escape_html(metric.precision_scale ?? "pending")}</p> `);
                if (metric.input_codes.length > 0) {
                  $$renderer4.push("<!--[0-->");
                  $$renderer4.push(`<p class="text-muted-foreground mt-1 text-xs">Inputs: ${escape_html(metric.input_codes.join(" + "))}</p>`);
                } else {
                  $$renderer4.push("<!--[-1-->");
                }
                $$renderer4.push(`<!--]--> <p class="text-muted-foreground mt-1 text-xs">Required outputs: ${escape_html(metric.required_output_scales.join(", ") || "Not declared")}</p></li>`);
              }
              $$renderer4.push(`<!--]--></ul></div>`);
            } else {
              $$renderer4.push("<!--[-1-->");
            }
            $$renderer4.push(`<!--]--> `);
            if (version.status === "approved") {
              $$renderer4.push("<!--[0-->");
              $$renderer4.push(`<p class="text-muted-foreground text-xs">Immutable content SHA-256: <span class="font-mono">${escape_html(version.content_sha256)}</span></p>`);
            } else {
              $$renderer4.push("<!--[-1-->");
            }
            $$renderer4.push(`<!--]--></article>`);
          }
          $$renderer4.push(`<!--]--></section></div>`);
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
