import { h as head, B as Button, a as ensure_array_like, e as escape_html, d as derived } from "../app.js";
import { S as StaffLayout } from "./StaffLayout-CR-MZopa.js";
import { P as PageHeaderBar } from "./PageHeaderBar-DJhT5vOR.js";
import { B as Badge } from "./badge-D9Vgs7xh.js";
import { T as Table, a as Table_header, b as Table_row, c as Table_head, d as Table_body, e as Table_cell } from "./table-row-_ffWKrps.js";
import "clsx";
import { S as Scroll_text, F as File_plus_corner } from "./scroll-text-BHmIg59K.js";
import { L as Link } from "./Link-B7ks7rBK.js";
import { E as Eye } from "./eye-DyG-RiwW.js";
import { P as Pencil } from "./pencil-0tPPVae8.js";
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
  let { page_state = "ready", norm_packages = [] } = $$props;
  const packages = derived(() => Array.isArray(norm_packages) ? norm_packages : []);
  const breadcrumbs = [
    { label: "Setup", href: "/setup" },
    { label: "Aptitude Areas", href: "/setup/aptitude" },
    { label: "Norm packages" }
  ];
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
  head("l3zne3", $$renderer, ($$renderer2) => {
    $$renderer2.title(($$renderer3) => {
      $$renderer3.push(`<title>Norm packages · Setup</title>`);
    });
  });
  StaffLayout($$renderer, {
    breadcrumbs,
    children: ($$renderer2) => {
      $$renderer2.push(`<div class="min-w-0 space-y-6 p-4 sm:p-6">`);
      {
        let actions = function($$renderer3) {
          Link($$renderer3, {
            href: "/setup/norm-packages/create",
            children: ($$renderer4) => {
              Button($$renderer4, {
                class: "min-h-10 gap-2",
                children: ($$renderer5) => {
                  File_plus_corner($$renderer5, { class: "h-4 w-4" });
                  $$renderer5.push(`<!---->Create package`);
                },
                $$slots: { default: true }
              });
            },
            $$slots: { default: true }
          });
        };
        PageHeaderBar($$renderer2, {
          title: "Norm packages",
          description: "Manage confidential, versioned norm tables for verified forms. Drafts remain unavailable for conversion until an authorized source review and approval.",
          actions
        });
      }
      $$renderer2.push(`<!----> <div class="border-border bg-muted/20 text-muted-foreground rounded-xl border p-4 text-sm">Norm tables are confidential Guidance data. Package names and entered rows do not establish scientific validity or applicability; verify the source and form before approval.</div> `);
      if (page_state === "empty" || packages().length === 0) {
        $$renderer2.push("<!--[0-->");
        $$renderer2.push(`<section class="craft-card-depth border-border/70 rounded-xl border border-dashed px-6 py-14 text-center" aria-labelledby="empty-norm-packages-heading">`);
        Scroll_text($$renderer2, {
          class: "text-muted-foreground mx-auto h-8 w-8",
          "aria-hidden": "true"
        });
        $$renderer2.push(`<!----> <h2 id="empty-norm-packages-heading" class="mt-4 font-semibold">No norm packages yet</h2> <p class="text-muted-foreground mx-auto mt-1 max-w-xl text-sm">Create a draft package to record its form, source, population, and conversion tables. The package will remain unapproved until reviewed.</p> `);
        Link($$renderer2, {
          href: "/setup/norm-packages/create",
          class: "mt-5 inline-flex",
          children: ($$renderer3) => {
            Button($$renderer3, {
              class: "min-h-11 gap-2",
              children: ($$renderer4) => {
                File_plus_corner($$renderer4, { class: "h-4 w-4" });
                $$renderer4.push(`<!---->Create first draft`);
              },
              $$slots: { default: true }
            });
          },
          $$slots: { default: true }
        });
        $$renderer2.push(`<!----></section>`);
      } else {
        $$renderer2.push("<!--[-1-->");
        $$renderer2.push(`<div class="craft-card-depth border-border/70 hidden overflow-x-auto rounded-xl border md:block">`);
        if (Table) {
          $$renderer2.push("<!--[-->");
          Table($$renderer2, {
            class: "min-w-240",
            children: ($$renderer3) => {
              if (Table_header) {
                $$renderer3.push("<!--[-->");
                Table_header($$renderer3, {
                  children: ($$renderer4) => {
                    if (Table_row) {
                      $$renderer4.push("<!--[-->");
                      Table_row($$renderer4, {
                        children: ($$renderer5) => {
                          if (Table_head) {
                            $$renderer5.push("<!--[-->");
                            Table_head($$renderer5, {
                              children: ($$renderer6) => {
                                $$renderer6.push(`<!---->Code`);
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
                              children: ($$renderer6) => {
                                $$renderer6.push(`<!---->Norm package`);
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
                              children: ($$renderer6) => {
                                $$renderer6.push(`<!---->Publisher`);
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
                              children: ($$renderer6) => {
                                $$renderer6.push(`<!---->Form`);
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
                              children: ($$renderer6) => {
                                $$renderer6.push(`<!---->Version`);
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
                              class: "text-right",
                              children: ($$renderer6) => {
                                $$renderer6.push(`<!---->Actions`);
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
                    const each_array = ensure_array_like(packages());
                    for (let $$index = 0, $$length = each_array.length; $$index < $$length; $$index++) {
                      let item = each_array[$$index];
                      if (Table_row) {
                        $$renderer4.push("<!--[-->");
                        Table_row($$renderer4, {
                          children: ($$renderer5) => {
                            if (Table_cell) {
                              $$renderer5.push("<!--[-->");
                              Table_cell($$renderer5, {
                                class: "font-mono text-sm",
                                children: ($$renderer6) => {
                                  $$renderer6.push(`<!---->${escape_html(item.code)}`);
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
                                  $$renderer6.push(`<p class="font-medium">${escape_html(item.display_name)}</p> `);
                                  if (item.is_synthetic) {
                                    $$renderer6.push("<!--[0-->");
                                    $$renderer6.push(`<p class="text-muted-foreground mt-1 text-xs">Synthetic · software only</p>`);
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
                                children: ($$renderer6) => {
                                  $$renderer6.push(`<!---->${escape_html(item.publisher_name || "—")}`);
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
                                  $$renderer6.push(`<!---->${escape_html(item.form_code || "—")}`);
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
                                  $$renderer6.push(`<!---->${escape_html(item.version_number ?? item.version ?? "—")}`);
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
                                    variant: statusVariant(item.status),
                                    children: ($$renderer7) => {
                                      $$renderer7.push(`<!---->${escape_html(statusLabel(item.status))}`);
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
                                class: "text-right",
                                children: ($$renderer6) => {
                                  $$renderer6.push(`<div class="flex justify-end gap-2">`);
                                  Link($$renderer6, {
                                    href: `/setup/norm-packages/${item.id}`,
                                    children: ($$renderer7) => {
                                      Button($$renderer7, {
                                        size: "sm",
                                        variant: "outline",
                                        class: "min-h-9 gap-1.5",
                                        children: ($$renderer8) => {
                                          Eye($$renderer8, { class: "h-3.5 w-3.5" });
                                          $$renderer8.push(`<!---->View`);
                                        },
                                        $$slots: { default: true }
                                      });
                                    },
                                    $$slots: { default: true }
                                  });
                                  $$renderer6.push(`<!----> `);
                                  Link($$renderer6, {
                                    href: `/setup/norm-packages/${item.id}/edit`,
                                    children: ($$renderer7) => {
                                      Button($$renderer7, {
                                        size: "sm",
                                        variant: "ghost",
                                        class: "min-h-9 gap-1.5",
                                        children: ($$renderer8) => {
                                          Pencil($$renderer8, { class: "h-3.5 w-3.5" });
                                          $$renderer8.push(`<!---->Edit`);
                                        },
                                        $$slots: { default: true }
                                      });
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
        $$renderer2.push(`</div> <ul class="grid gap-3 md:hidden" role="list"><!--[-->`);
        const each_array_1 = ensure_array_like(packages());
        for (let $$index_1 = 0, $$length = each_array_1.length; $$index_1 < $$length; $$index_1++) {
          let item = each_array_1[$$index_1];
          $$renderer2.push(`<li class="border-border bg-card min-w-0 rounded-xl border p-4"><div class="flex items-start justify-between gap-3"><div class="min-w-0"><h2 class="font-semibold">${escape_html(item.display_name)}</h2> <p class="text-muted-foreground mt-1 font-mono text-xs">${escape_html(item.code)}</p></div> `);
          Badge($$renderer2, {
            variant: statusVariant(item.status),
            children: ($$renderer3) => {
              $$renderer3.push(`<!---->${escape_html(statusLabel(item.status))}`);
            },
            $$slots: { default: true }
          });
          $$renderer2.push(`<!----></div> `);
          if (item.is_synthetic) {
            $$renderer2.push("<!--[0-->");
            $$renderer2.push(`<p class="text-muted-foreground mt-2 text-xs">Synthetic · software only</p>`);
          } else {
            $$renderer2.push("<!--[-1-->");
          }
          $$renderer2.push(`<!--]--> <dl class="mt-4 grid grid-cols-2 gap-3 text-sm"><div><dt class="text-muted-foreground">Publisher</dt> <dd class="font-medium">${escape_html(item.publisher_name || "—")}</dd></div> <div><dt class="text-muted-foreground">Form</dt> <dd class="font-medium">${escape_html(item.form_code || "—")}</dd></div> <div><dt class="text-muted-foreground">Version</dt> <dd class="font-medium">${escape_html(item.version_number ?? item.version ?? "—")}</dd></div></dl> <div class="mt-4 flex gap-2">`);
          Link($$renderer2, {
            href: `/setup/norm-packages/${item.id}`,
            class: "flex-1",
            children: ($$renderer3) => {
              Button($$renderer3, {
                variant: "outline",
                class: "min-h-10 w-full gap-2",
                children: ($$renderer4) => {
                  Eye($$renderer4, { class: "h-4 w-4" });
                  $$renderer4.push(`<!---->View`);
                },
                $$slots: { default: true }
              });
            },
            $$slots: { default: true }
          });
          $$renderer2.push(`<!----> `);
          Link($$renderer2, {
            href: `/setup/norm-packages/${item.id}/edit`,
            class: "flex-1",
            children: ($$renderer3) => {
              Button($$renderer3, {
                variant: "ghost",
                class: "min-h-10 w-full gap-2",
                children: ($$renderer4) => {
                  Pencil($$renderer4, { class: "h-4 w-4" });
                  $$renderer4.push(`<!---->Edit`);
                },
                $$slots: { default: true }
              });
            },
            $$slots: { default: true }
          });
          $$renderer2.push(`<!----></div></li>`);
        }
        $$renderer2.push(`<!--]--></ul>`);
      }
      $$renderer2.push(`<!--]--></div>`);
    }
  });
}
export {
  Index as default
};
