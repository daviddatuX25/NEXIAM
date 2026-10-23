import { h as head, a as ensure_array_like, e as escape_html, B as Button } from "../app.js";
import { S as StaffLayout } from "./StaffLayout-CR-MZopa.js";
import { P as PageHeaderBar } from "./PageHeaderBar-DJhT5vOR.js";
import { B as Badge } from "./badge-D9Vgs7xh.js";
import { I as Input } from "./input-BUZAZKIK.js";
import { L as Label } from "./label-dGY6L9vm.js";
import { T as Table, a as Table_header, b as Table_row, c as Table_head, d as Table_body, e as Table_cell } from "./table-row-_ffWKrps.js";
import "clsx";
import { u as useForm } from "./useForm.svelte-C0OkDFGu.js";
import { L as Link } from "./Link-B7ks7rBK.js";
import { L as Layout_grid } from "./layout-grid-CXJfSBqV.js";
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
function Programs($$renderer, $$props) {
  $$renderer.component(($$renderer2) => {
    let { programs = [], departments = [] } = $$props;
    const form = useForm({ code: "", name: "", department_id: "", is_active: true });
    function toggleActive(program) {
      router.put(`/setup/programs/${program.id}`, {
        name: program.name,
        department_id: program.department_id,
        is_active: !program.is_active
      });
    }
    const breadcrumbs = [{ label: "Setup", href: "/setup" }, { label: "Programs" }];
    let $$settled = true;
    let $$inner_renderer;
    function $$render_inner($$renderer3) {
      head("10enfeo", $$renderer3, ($$renderer4) => {
        $$renderer4.title(($$renderer5) => {
          $$renderer5.push(`<title>Programs &amp; Courses · Setup</title>`);
        });
      });
      StaffLayout($$renderer3, {
        breadcrumbs,
        children: ($$renderer4) => {
          $$renderer4.push(`<div class="min-w-0 space-y-6 p-4 sm:p-6">`);
          {
            let actions = function($$renderer5) {
              $$renderer5.push(`<div class="flex flex-wrap items-center gap-2">`);
              Link($$renderer5, {
                href: "/setup/departments",
                children: ($$renderer6) => {
                  Button($$renderer6, {
                    variant: "outline",
                    size: "sm",
                    class: "font-medium",
                    children: ($$renderer7) => {
                      $$renderer7.push(`<!---->Manage Colleges &amp; Departments`);
                    },
                    $$slots: { default: true }
                  });
                },
                $$slots: { default: true }
              });
              $$renderer5.push(`<!----> `);
              Link($$renderer5, {
                href: "/setup",
                children: ($$renderer6) => {
                  Button($$renderer6, {
                    variant: "ghost",
                    size: "sm",
                    children: ($$renderer7) => {
                      $$renderer7.push(`<!---->Back to readiness`);
                    },
                    $$slots: { default: true }
                  });
                },
                $$slots: { default: true }
              });
              $$renderer5.push(`<!----></div>`);
            };
            PageHeaderBar($$renderer4, {
              title: "Programs & Courses",
              description: "Manage degree programs and academic courses, and designate programs open for applicant admissions intake.",
              actions
            });
          }
          $$renderer4.push(`<!----> <form class="grid gap-3 rounded-xl border border-border bg-card p-4 sm:grid-cols-5"><div class="space-y-1.5">`);
          Label($$renderer4, {
            for: "code",
            children: ($$renderer5) => {
              $$renderer5.push(`<!---->Code`);
            },
            $$slots: { default: true }
          });
          $$renderer4.push(`<!----> `);
          Input($$renderer4, {
            id: "code",
            class: "min-h-10 font-mono",
            placeholder: "BSN",
            get value() {
              return form.code;
            },
            set value($$value) {
              form.code = $$value;
              $$settled = false;
            }
          });
          $$renderer4.push(`<!----></div> <div class="space-y-1.5 sm:col-span-2">`);
          Label($$renderer4, {
            for: "name",
            children: ($$renderer5) => {
              $$renderer5.push(`<!---->Name`);
            },
            $$slots: { default: true }
          });
          $$renderer4.push(`<!----> `);
          Input($$renderer4, {
            id: "name",
            class: "min-h-10",
            get value() {
              return form.name;
            },
            set value($$value) {
              form.name = $$value;
              $$settled = false;
            }
          });
          $$renderer4.push(`<!----></div> <div class="space-y-1.5">`);
          Label($$renderer4, {
            for: "department_id",
            children: ($$renderer5) => {
              $$renderer5.push(`<!---->Department / College`);
            },
            $$slots: { default: true }
          });
          $$renderer4.push(`<!----> `);
          $$renderer4.select(
            {
              id: "department_id",
              value: form.department_id,
              class: "flex h-10 w-full rounded-md border border-input bg-background px-3 py-2 text-sm ring-offset-background placeholder:text-muted-foreground focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2"
            },
            ($$renderer5) => {
              $$renderer5.option({ value: "" }, ($$renderer6) => {
                $$renderer6.push(`No Department`);
              });
              $$renderer5.push(`<!--[-->`);
              const each_array = ensure_array_like(departments);
              for (let $$index = 0, $$length = each_array.length; $$index < $$length; $$index++) {
                let d = each_array[$$index];
                $$renderer5.option({ value: d.id }, ($$renderer6) => {
                  $$renderer6.push(`${escape_html(d.code)} - ${escape_html(d.name)}`);
                });
              }
              $$renderer5.push(`<!--]-->`);
            }
          );
          $$renderer4.push(`</div> <div class="flex items-end">`);
          Button($$renderer4, {
            type: "submit",
            class: "min-h-10 w-full",
            disabled: form.processing,
            children: ($$renderer5) => {
              $$renderer5.push(`<!---->Add program`);
            },
            $$slots: { default: true }
          });
          $$renderer4.push(`<!----></div></form> <div class="rounded-xl border border-border/80 bg-card/60 backdrop-blur-md shadow-xs overflow-hidden">`);
          if (Table) {
            $$renderer4.push("<!--[-->");
            Table($$renderer4, {
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
                                  $$renderer8.push(`<!---->Code`);
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
                                  $$renderer8.push(`<!---->Name`);
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
                                  $$renderer8.push(`<!---->Department`);
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
                                  $$renderer8.push(`<!---->Status`);
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
                                class: "text-right",
                                children: ($$renderer8) => {
                                  $$renderer8.push(`<!---->Action`);
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
                      const each_array_1 = ensure_array_like(programs);
                      for (let $$index_1 = 0, $$length = each_array_1.length; $$index_1 < $$length; $$index_1++) {
                        let p = each_array_1[$$index_1];
                        if (Table_row) {
                          $$renderer6.push("<!--[-->");
                          Table_row($$renderer6, {
                            children: ($$renderer7) => {
                              if (Table_cell) {
                                $$renderer7.push("<!--[-->");
                                Table_cell($$renderer7, {
                                  class: "font-mono text-xs",
                                  children: ($$renderer8) => {
                                    $$renderer8.push(`<!---->${escape_html(p.code)}`);
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
                                    $$renderer8.push(`<!---->${escape_html(p.name)}`);
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
                                    if (p.department) {
                                      $$renderer8.push("<!--[0-->");
                                      Badge($$renderer8, {
                                        variant: "outline",
                                        class: "font-mono text-xs",
                                        children: ($$renderer9) => {
                                          $$renderer9.push(`<!---->${escape_html(p.department.code)}`);
                                        },
                                        $$slots: { default: true }
                                      });
                                    } else {
                                      $$renderer8.push("<!--[-1-->");
                                      $$renderer8.push(`<span class="text-xs text-muted-foreground">—</span>`);
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
                              $$renderer7.push(` `);
                              if (Table_cell) {
                                $$renderer7.push("<!--[-->");
                                Table_cell($$renderer7, {
                                  children: ($$renderer8) => {
                                    Badge($$renderer8, {
                                      variant: p.is_active ? "default" : "secondary",
                                      children: ($$renderer9) => {
                                        $$renderer9.push(`<!---->${escape_html(p.is_active ? "Active" : "Inactive")}`);
                                      },
                                      $$slots: { default: true }
                                    });
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
                                  class: "text-right",
                                  children: ($$renderer8) => {
                                    Link($$renderer8, {
                                      href: `/setup/programs/${p.id}/hub`,
                                      children: ($$renderer9) => {
                                        Button($$renderer9, {
                                          variant: "outline",
                                          size: "sm",
                                          class: "gap-1",
                                          children: ($$renderer10) => {
                                            Layout_grid($$renderer10, { class: "size-3.5" });
                                            $$renderer10.push(`<!----> Program Hub`);
                                          },
                                          $$slots: { default: true }
                                        });
                                      },
                                      $$slots: { default: true }
                                    });
                                    $$renderer8.push(`<!----> `);
                                    if (p.public_details_href) {
                                      $$renderer8.push("<!--[0-->");
                                      Link($$renderer8, {
                                        href: p.public_details_href,
                                        children: ($$renderer9) => {
                                          Button($$renderer9, {
                                            size: "sm",
                                            variant: "outline",
                                            children: ($$renderer10) => {
                                              $$renderer10.push(`<!---->Edit public details`);
                                            },
                                            $$slots: { default: true }
                                          });
                                        },
                                        $$slots: { default: true }
                                      });
                                    } else {
                                      $$renderer8.push("<!--[-1-->");
                                    }
                                    $$renderer8.push(`<!--]--> `);
                                    Button($$renderer8, {
                                      size: "sm",
                                      variant: "outline",
                                      onclick: () => toggleActive(p),
                                      children: ($$renderer9) => {
                                        $$renderer9.push(`<!---->${escape_html(p.is_active ? "Deactivate" : "Activate")}`);
                                      },
                                      $$slots: { default: true }
                                    });
                                    $$renderer8.push(`<!---->`);
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
  Programs as default
};
