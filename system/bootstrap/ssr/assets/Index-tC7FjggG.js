import { h as head, B as Button, a as ensure_array_like, e as escape_html } from "../app.js";
import { S as StaffLayout } from "./StaffLayout-CR-MZopa.js";
import { P as PageHeaderBar } from "./PageHeaderBar-DJhT5vOR.js";
import { B as Badge } from "./badge-D9Vgs7xh.js";
import { T as Table, a as Table_header, b as Table_row, c as Table_head, d as Table_body, e as Table_cell } from "./table-row-_ffWKrps.js";
import "clsx";
import { S as Scroll_text, F as File_plus_corner } from "./scroll-text-BHmIg59K.js";
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
import "./input-BUZAZKIK.js";
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
function Index($$renderer, $$props) {
  $$renderer.component(($$renderer2) => {
    let { page_state = "ready", instruments = [] } = $$props;
    const breadcrumbs = [
      { label: "Setup", href: "/setup" },
      { label: "Aptitude Areas", href: "/setup/aptitude" },
      { label: "Test instruments" }
    ];
    function statusLabel(status) {
      return status === "approved" ? "Approved" : status === "draft" ? "Draft" : "No approved version";
    }
    function normLabel(readiness) {
      return readiness === "missing_package" ? "Norm package missing" : "Not configured";
    }
    head("i3nnom", $$renderer2, ($$renderer3) => {
      $$renderer3.title(($$renderer4) => {
        $$renderer4.push(`<title>Test instruments · Setup</title>`);
      });
    });
    StaffLayout($$renderer2, {
      breadcrumbs,
      children: ($$renderer3) => {
        $$renderer3.push(`<div class="min-w-0 space-y-6 p-4 sm:p-6">`);
        {
          let actions = function($$renderer4) {
            Link($$renderer4, {
              href: "/setup/test-instruments/create",
              children: ($$renderer5) => {
                Button($$renderer5, {
                  class: "min-h-10 gap-2",
                  children: ($$renderer6) => {
                    File_plus_corner($$renderer6, { class: "h-4 w-4" });
                    $$renderer6.push(`<!---->Create instrument`);
                  },
                  $$slots: { default: true }
                });
              },
              $$slots: { default: true }
            });
          };
          PageHeaderBar($$renderer3, {
            title: "Test instruments",
            description: "Manage versioned instrument and form profiles. Profiles describe test sections, item identities, and raw metrics; question content and answer keys stay in Booklets.",
            actions
          });
        }
        $$renderer3.push(`<!----> <div class="border-border bg-muted/20 text-muted-foreground rounded-xl border p-4 text-sm">Raw-scoring readiness and norm-conversion readiness are separate. No
            profile is usable for assessment until its source and rule contract
            are reviewed and approved. This workspace does not contain question
            text, keys, or norm tables.</div> `);
        if (page_state === "empty_scope" || instruments.length === 0) {
          $$renderer3.push("<!--[0-->");
          $$renderer3.push(`<section class="craft-card-depth border-border/70 rounded-xl border border-dashed px-6 py-14 text-center" aria-labelledby="empty-instruments-heading">`);
          Scroll_text($$renderer3, {
            class: "text-muted-foreground mx-auto h-8 w-8",
            "aria-hidden": "true"
          });
          $$renderer3.push(`<!----> <h2 id="empty-instruments-heading" class="mt-4 font-semibold">No instrument profiles yet</h2> <p class="text-muted-foreground mx-auto mt-1 max-w-xl text-sm">Create a draft profile to define a form's sections and raw
                    measures. Unverified sources remain drafts and cannot be
                    assigned to an assessment.</p> `);
          Link($$renderer3, {
            href: "/setup/test-instruments/create",
            class: "mt-5 inline-flex",
            children: ($$renderer4) => {
              Button($$renderer4, {
                class: "min-h-11 gap-2",
                children: ($$renderer5) => {
                  File_plus_corner($$renderer5, { class: "h-4 w-4" });
                  $$renderer5.push(`<!---->Create first draft`);
                },
                $$slots: { default: true }
              });
            },
            $$slots: { default: true }
          });
          $$renderer3.push(`<!----></section>`);
        } else {
          $$renderer3.push("<!--[-1-->");
          $$renderer3.push(`<div class="craft-card-depth border-border/70 hidden overflow-x-auto rounded-xl border md:block">`);
          if (Table) {
            $$renderer3.push("<!--[-->");
            Table($$renderer3, {
              class: "min-w-270",
              children: ($$renderer4) => {
                if (Table_header) {
                  $$renderer4.push("<!--[-->");
                  Table_header($$renderer4, {
                    children: ($$renderer5) => {
                      if (Table_row) {
                        $$renderer5.push("<!--[-->");
                        Table_row($$renderer5, {
                          children: ($$renderer6) => {
                            if (Table_head) {
                              $$renderer6.push("<!--[-->");
                              Table_head($$renderer6, {
                                children: ($$renderer7) => {
                                  $$renderer7.push(`<!---->Instrument / form`);
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
                                children: ($$renderer7) => {
                                  $$renderer7.push(`<!---->Version`);
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
                                children: ($$renderer7) => {
                                  $$renderer7.push(`<!---->Status`);
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
                                children: ($$renderer7) => {
                                  $$renderer7.push(`<!---->Source review`);
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
                                children: ($$renderer7) => {
                                  $$renderer7.push(`<!---->Raw scoring`);
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
                                children: ($$renderer7) => {
                                  $$renderer7.push(`<!---->Norm conversion`);
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
                                class: "text-right",
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
                      $$renderer5.push(`<!--[-->`);
                      const each_array = ensure_array_like(instruments);
                      for (let $$index = 0, $$length = each_array.length; $$index < $$length; $$index++) {
                        let instrument = each_array[$$index];
                        if (Table_row) {
                          $$renderer5.push("<!--[-->");
                          Table_row($$renderer5, {
                            children: ($$renderer6) => {
                              if (Table_cell) {
                                $$renderer6.push("<!--[-->");
                                Table_cell($$renderer6, {
                                  children: ($$renderer7) => {
                                    $$renderer7.push(`<p class="font-medium">${escape_html(instrument.display_name)}</p> <p class="text-muted-foreground font-mono text-xs">${escape_html(instrument.code)}${escape_html(instrument.form_code ? ` · ${instrument.form_code}` : "")}</p> `);
                                    if (instrument.is_synthetic) {
                                      $$renderer7.push("<!--[0-->");
                                      Badge($$renderer7, {
                                        variant: "secondary",
                                        class: "mt-1",
                                        children: ($$renderer8) => {
                                          $$renderer8.push(`<!---->Synthetic · software only`);
                                        },
                                        $$slots: { default: true }
                                      });
                                    } else {
                                      $$renderer7.push("<!--[-1-->");
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
                                  children: ($$renderer7) => {
                                    $$renderer7.push(`<!---->${escape_html(instrument.version_number ?? "—")}`);
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
                                  children: ($$renderer7) => {
                                    Badge($$renderer7, {
                                      variant: instrument.status === "approved" ? "default" : "secondary",
                                      children: ($$renderer8) => {
                                        $$renderer8.push(`<!---->${escape_html(statusLabel(instrument.status))}`);
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
                                  children: ($$renderer7) => {
                                    Badge($$renderer7, {
                                      variant: instrument.source_readiness === "reviewed" ? "default" : "outline",
                                      children: ($$renderer8) => {
                                        $$renderer8.push(`<!---->${escape_html(instrument.source_readiness === "reviewed" ? "Reviewed" : "Source hold")}`);
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
                                  children: ($$renderer7) => {
                                    Badge($$renderer7, {
                                      variant: instrument.raw_readiness === "approved" ? "default" : "outline",
                                      children: ($$renderer8) => {
                                        $$renderer8.push(`<!---->${escape_html(instrument.raw_readiness === "approved" ? "Profile approved" : `${instrument.subtest_count} test ${instrument.subtest_count === 1 ? "section" : "sections"} · ${instrument.required_metric_count} required ${instrument.required_metric_count === 1 ? "metric" : "metrics"}`)}`);
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
                                  children: ($$renderer7) => {
                                    Badge($$renderer7, {
                                      variant: "outline",
                                      children: ($$renderer8) => {
                                        $$renderer8.push(`<!---->${escape_html(normLabel(instrument.norm_readiness))}`);
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
                                  class: "text-right",
                                  children: ($$renderer7) => {
                                    Link($$renderer7, {
                                      href: `/setup/test-instruments/${instrument.id}`,
                                      children: ($$renderer8) => {
                                        Button($$renderer8, {
                                          size: "sm",
                                          variant: "outline",
                                          children: ($$renderer9) => {
                                            $$renderer9.push(`<!---->Manage`);
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
          $$renderer3.push(`</div> <ul class="grid gap-3 md:hidden" role="list"><!--[-->`);
          const each_array_1 = ensure_array_like(instruments);
          for (let $$index_1 = 0, $$length = each_array_1.length; $$index_1 < $$length; $$index_1++) {
            let instrument = each_array_1[$$index_1];
            $$renderer3.push(`<li class="border-border bg-card rounded-xl border p-4"><div class="flex items-start justify-between gap-3"><div class="min-w-0"><h2 class="font-semibold">${escape_html(instrument.display_name)}</h2> <p class="text-muted-foreground font-mono text-xs">${escape_html(instrument.code)}${escape_html(instrument.form_code ? ` · ${instrument.form_code}` : "")}</p></div> `);
            Badge($$renderer3, {
              variant: instrument.status === "approved" ? "default" : "secondary",
              children: ($$renderer4) => {
                $$renderer4.push(`<!---->${escape_html(statusLabel(instrument.status))}`);
              },
              $$slots: { default: true }
            });
            $$renderer3.push(`<!----></div> `);
            if (instrument.is_synthetic) {
              $$renderer3.push("<!--[0-->");
              Badge($$renderer3, {
                variant: "secondary",
                class: "mt-3",
                children: ($$renderer4) => {
                  $$renderer4.push(`<!---->Synthetic · software only`);
                },
                $$slots: { default: true }
              });
            } else {
              $$renderer3.push("<!--[-1-->");
            }
            $$renderer3.push(`<!--]--> <dl class="mt-4 grid grid-cols-1 gap-3 text-sm sm:grid-cols-3"><div><dt class="text-muted-foreground">Source review</dt> <dd class="font-medium">${escape_html(instrument.source_readiness === "reviewed" ? "Reviewed" : "Source hold")}</dd></div> <div><dt class="text-muted-foreground">Raw scoring</dt> <dd class="font-medium">${escape_html(instrument.raw_readiness === "approved" ? "Profile approved" : `${instrument.subtest_count} test ${instrument.subtest_count === 1 ? "section" : "sections"}`)}</dd></div> <div><dt class="text-muted-foreground">Norm conversion</dt> <dd class="font-medium">${escape_html(normLabel(instrument.norm_readiness))}</dd></div></dl> `);
            Link($$renderer3, {
              href: `/setup/test-instruments/${instrument.id}`,
              class: "mt-4 block",
              children: ($$renderer4) => {
                Button($$renderer4, {
                  variant: "outline",
                  class: "min-h-11 w-full",
                  children: ($$renderer5) => {
                    $$renderer5.push(`<!---->Manage instrument`);
                  },
                  $$slots: { default: true }
                });
              },
              $$slots: { default: true }
            });
            $$renderer3.push(`<!----></li>`);
          }
          $$renderer3.push(`<!--]--></ul>`);
        }
        $$renderer3.push(`<!--]--></div>`);
      }
    });
  });
}
export {
  Index as default
};
