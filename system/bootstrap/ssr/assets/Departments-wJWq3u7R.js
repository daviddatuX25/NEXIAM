import { h as head, a as ensure_array_like, e as escape_html, B as Button, b as attr } from "../app.js";
import { S as StaffLayout } from "./StaffLayout-CR-MZopa.js";
import { P as PageHeaderBar } from "./PageHeaderBar-DJhT5vOR.js";
import { B as Badge } from "./badge-D9Vgs7xh.js";
import { I as Input } from "./input-BUZAZKIK.js";
import { L as Label } from "./label-dGY6L9vm.js";
import { T as Table, a as Table_header, b as Table_row, c as Table_head, d as Table_body, e as Table_cell } from "./table-row-_ffWKrps.js";
import "clsx";
import { D as Dialog, a as Dialog_content, b as Dialog_header, c as Dialog_title, d as Dialog_description } from "./dialog-description-D12L0QA9.js";
import { D as Dialog_footer } from "./dialog-footer-kNnwKT2h.js";
import { u as useForm } from "./useForm.svelte-C0OkDFGu.js";
import { U as User_check } from "./user-check-Dl-wciTR.js";
import { L as Link } from "./Link-B7ks7rBK.js";
import { G as Graduation_cap } from "./graduation-cap-Bc9amiZ0.js";
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
import "./circle-alert-jWXJ7oC0.js";
import "./refresh-cw-CYdk8aE0.js";
import "./camera-C09Eyogi.js";
import "./settings-C5xOKpV3.js";
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
import "es-toolkit";
import "./clone-BJz-lxsY.js";
import "es-toolkit/compat";
function Departments($$renderer, $$props) {
  $$renderer.component(($$renderer2) => {
    let { departments = [], eligible_deans = [] } = $$props;
    const form = useForm({ code: "", name: "", dean_user_id: "", is_active: true });
    function toggleActive(dept) {
      router.post(`/setup/departments/${dept.id}/toggle`);
    }
    let editDialogOpen = false;
    let activeDept = null;
    const editForm = useForm({ name: "", dean_user_id: "", is_active: true });
    function openEdit(dept) {
      activeDept = dept;
      editForm.name = dept.name;
      editForm.dean_user_id = dept.dean_user_id ? String(dept.dean_user_id) : "";
      editForm.is_active = dept.is_active;
      editDialogOpen = true;
    }
    const breadcrumbs = [
      { label: "Setup", href: "/setup" },
      { label: "Colleges & Departments" }
    ];
    let $$settled = true;
    let $$inner_renderer;
    function $$render_inner($$renderer3) {
      head("1m7nsno", $$renderer3, ($$renderer4) => {
        $$renderer4.title(($$renderer5) => {
          $$renderer5.push(`<title>Colleges &amp; Departments · Setup</title>`);
        });
      });
      StaffLayout($$renderer3, {
        breadcrumbs,
        children: ($$renderer4) => {
          $$renderer4.push(`<div class="min-w-0 space-y-6 p-4 sm:p-6">`);
          {
            let actions = function($$renderer5) {
              $$renderer5.push(`<div class="flex items-center gap-2">`);
              Link($$renderer5, {
                href: "/setup/programs",
                children: ($$renderer6) => {
                  Button($$renderer6, {
                    variant: "outline",
                    size: "sm",
                    class: "gap-1.5 font-medium",
                    children: ($$renderer7) => {
                      Graduation_cap($$renderer7, { class: "size-4" });
                      $$renderer7.push(`<!----> View Programs &amp; Courses`);
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
              title: "Colleges & Academic Departments",
              description: "Manage institutional academic colleges, appoint college deans, and oversee department degree program jurisdictions.",
              actions
            });
          }
          $$renderer4.push(`<!----> <form class="grid gap-3 rounded-xl border border-border bg-card p-4 sm:grid-cols-5"><div class="space-y-1.5">`);
          Label($$renderer4, {
            for: "dept_code",
            children: ($$renderer5) => {
              $$renderer5.push(`<!---->College Code`);
            },
            $$slots: { default: true }
          });
          $$renderer4.push(`<!----> `);
          Input($$renderer4, {
            id: "dept_code",
            class: "min-h-10 font-mono uppercase",
            placeholder: "CTE",
            required: true,
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
            for: "dept_name",
            children: ($$renderer5) => {
              $$renderer5.push(`<!---->College / Department Name`);
            },
            $$slots: { default: true }
          });
          $$renderer4.push(`<!----> `);
          Input($$renderer4, {
            id: "dept_name",
            class: "min-h-10",
            placeholder: "e.g. College of Teacher Education",
            required: true,
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
            for: "dean_select",
            children: ($$renderer5) => {
              $$renderer5.push(`<!---->Appointed College Dean`);
            },
            $$slots: { default: true }
          });
          $$renderer4.push(`<!----> `);
          $$renderer4.select(
            {
              id: "dean_select",
              value: form.dean_user_id,
              class: "flex h-10 w-full rounded-md border border-input bg-background px-3 py-2 text-sm ring-offset-background placeholder:text-muted-foreground focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2"
            },
            ($$renderer5) => {
              $$renderer5.option({ value: "" }, ($$renderer6) => {
                $$renderer6.push(`No Dean Appointed`);
              });
              $$renderer5.push(`<!--[-->`);
              const each_array = ensure_array_like(eligible_deans);
              for (let $$index = 0, $$length = each_array.length; $$index < $$length; $$index++) {
                let user = each_array[$$index];
                $$renderer5.option({ value: user.id }, ($$renderer6) => {
                  $$renderer6.push(`${escape_html(user.name)} (${escape_html(user.email)})`);
                });
              }
              $$renderer5.push(`<!--]-->`);
            }
          );
          $$renderer4.push(`</div> <div class="flex items-end">`);
          Button($$renderer4, {
            type: "submit",
            class: "min-h-10 w-full font-semibold",
            disabled: form.processing,
            children: ($$renderer5) => {
              $$renderer5.push(`<!---->Add College`);
            },
            $$slots: { default: true }
          });
          $$renderer4.push(`<!----></div></form> <div class="rounded-xl border border-border bg-card overflow-hidden shadow-2xs">`);
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
                          class: "bg-muted/40",
                          children: ($$renderer7) => {
                            if (Table_head) {
                              $$renderer7.push("<!--[-->");
                              Table_head($$renderer7, {
                                class: "w-24",
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
                                  $$renderer8.push(`<!---->College / Department Name`);
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
                                  $$renderer8.push(`<!---->Appointed Dean`);
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
                                  $$renderer8.push(`<!---->Assigned Programs`);
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
                                  $$renderer8.push(`<!---->Actions`);
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
                      if (departments.length === 0) {
                        $$renderer6.push("<!--[0-->");
                        if (Table_row) {
                          $$renderer6.push("<!--[-->");
                          Table_row($$renderer6, {
                            children: ($$renderer7) => {
                              if (Table_cell) {
                                $$renderer7.push("<!--[-->");
                                Table_cell($$renderer7, {
                                  colspan: "6",
                                  class: "py-10 text-center text-sm text-muted-foreground",
                                  children: ($$renderer8) => {
                                    $$renderer8.push(`<!---->No colleges or departments registered yet.`);
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
                      } else {
                        $$renderer6.push("<!--[-1-->");
                        $$renderer6.push(`<!--[-->`);
                        const each_array_1 = ensure_array_like(departments);
                        for (let $$index_2 = 0, $$length = each_array_1.length; $$index_2 < $$length; $$index_2++) {
                          let d = each_array_1[$$index_2];
                          if (Table_row) {
                            $$renderer6.push("<!--[-->");
                            Table_row($$renderer6, {
                              children: ($$renderer7) => {
                                if (Table_cell) {
                                  $$renderer7.push("<!--[-->");
                                  Table_cell($$renderer7, {
                                    children: ($$renderer8) => {
                                      Badge($$renderer8, {
                                        variant: "outline",
                                        class: "font-mono text-xs font-bold",
                                        children: ($$renderer9) => {
                                          $$renderer9.push(`<!---->${escape_html(d.code)}`);
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
                                    class: "font-medium text-foreground",
                                    children: ($$renderer8) => {
                                      $$renderer8.push(`<!---->${escape_html(d.name)}`);
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
                                      if (d.dean) {
                                        $$renderer8.push("<!--[0-->");
                                        $$renderer8.push(`<div class="flex items-center gap-1.5">`);
                                        User_check($$renderer8, { class: "size-3.5 text-primary shrink-0" });
                                        $$renderer8.push(`<!----> <div><p class="text-xs font-semibold leading-none">${escape_html(d.dean.name)}</p> <p class="text-[10px] text-muted-foreground">${escape_html(d.dean.email)}</p></div></div>`);
                                      } else {
                                        $$renderer8.push("<!--[-1-->");
                                        $$renderer8.push(`<span class="text-xs italic text-muted-foreground">None appointed</span>`);
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
                                      $$renderer8.push(`<div class="flex flex-wrap items-center gap-1"><span class="font-semibold text-xs mr-1">${escape_html(d.programs_count ?? d.programs?.length ?? 0)}</span> <!--[-->`);
                                      const each_array_2 = ensure_array_like(d.programs ?? []);
                                      for (let $$index_1 = 0, $$length2 = each_array_2.length; $$index_1 < $$length2; $$index_1++) {
                                        let prog = each_array_2[$$index_1];
                                        Link($$renderer8, {
                                          href: `/setup/programs/${prog.id}/hub`,
                                          children: ($$renderer9) => {
                                            Badge($$renderer9, {
                                              variant: "secondary",
                                              class: "font-mono text-[10px] px-1.5 py-0",
                                              children: ($$renderer10) => {
                                                $$renderer10.push(`<!---->${escape_html(prog.code)}`);
                                              },
                                              $$slots: { default: true }
                                            });
                                          },
                                          $$slots: { default: true }
                                        });
                                      }
                                      $$renderer8.push(`<!--]--></div>`);
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
                                        variant: d.is_active ? "default" : "secondary",
                                        children: ($$renderer9) => {
                                          $$renderer9.push(`<!---->${escape_html(d.is_active ? "Active" : "Inactive")}`);
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
                                      $$renderer8.push(`<div class="flex items-center justify-end gap-1.5">`);
                                      Button($$renderer8, {
                                        size: "sm",
                                        variant: "ghost",
                                        onclick: () => openEdit(d),
                                        children: ($$renderer9) => {
                                          $$renderer9.push(`<!---->Edit`);
                                        },
                                        $$slots: { default: true }
                                      });
                                      $$renderer8.push(`<!----> `);
                                      Button($$renderer8, {
                                        size: "sm",
                                        variant: "outline",
                                        onclick: () => toggleActive(d),
                                        children: ($$renderer9) => {
                                          $$renderer9.push(`<!---->${escape_html(d.is_active ? "Deactivate" : "Activate")}`);
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
                        $$renderer6.push(`<!--]-->`);
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
          $$renderer4.push(`</div></div> `);
          if (Dialog) {
            $$renderer4.push("<!--[-->");
            Dialog($$renderer4, {
              get open() {
                return editDialogOpen;
              },
              set open($$value) {
                editDialogOpen = $$value;
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
                                  $$renderer8.push(`<!---->Edit College / Department`);
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
                                  $$renderer8.push(`<!---->Update department title, appointed Dean, or operational status.`);
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
                        for: "edit_dept_code",
                        children: ($$renderer7) => {
                          $$renderer7.push(`<!---->College Code`);
                        },
                        $$slots: { default: true }
                      });
                      $$renderer6.push(`<!----> `);
                      Input($$renderer6, {
                        id: "edit_dept_code",
                        value: activeDept?.code,
                        class: "min-h-10 font-mono uppercase bg-muted/40",
                        disabled: true
                      });
                      $$renderer6.push(`<!----></div> <div class="space-y-1.5">`);
                      Label($$renderer6, {
                        for: "edit_dept_name",
                        children: ($$renderer7) => {
                          $$renderer7.push(`<!---->College Name`);
                        },
                        $$slots: { default: true }
                      });
                      $$renderer6.push(`<!----> `);
                      Input($$renderer6, {
                        id: "edit_dept_name",
                        class: "min-h-10",
                        required: true,
                        get value() {
                          return editForm.name;
                        },
                        set value($$value) {
                          editForm.name = $$value;
                          $$settled = false;
                        }
                      });
                      $$renderer6.push(`<!----></div> <div class="space-y-1.5">`);
                      Label($$renderer6, {
                        for: "edit_dean_select",
                        children: ($$renderer7) => {
                          $$renderer7.push(`<!---->Appointed College Dean`);
                        },
                        $$slots: { default: true }
                      });
                      $$renderer6.push(`<!----> `);
                      $$renderer6.select(
                        {
                          id: "edit_dean_select",
                          value: editForm.dean_user_id,
                          class: "flex h-10 w-full rounded-md border border-input bg-background px-3 py-2 text-sm ring-offset-background placeholder:text-muted-foreground focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2"
                        },
                        ($$renderer7) => {
                          $$renderer7.option({ value: "" }, ($$renderer8) => {
                            $$renderer8.push(`No Dean Appointed`);
                          });
                          $$renderer7.push(`<!--[-->`);
                          const each_array_3 = ensure_array_like(eligible_deans);
                          for (let $$index_3 = 0, $$length = each_array_3.length; $$index_3 < $$length; $$index_3++) {
                            let user = each_array_3[$$index_3];
                            $$renderer7.option({ value: user.id }, ($$renderer8) => {
                              $$renderer8.push(`${escape_html(user.name)} (${escape_html(user.email)})`);
                            });
                          }
                          $$renderer7.push(`<!--]-->`);
                        }
                      );
                      $$renderer6.push(`</div> <div class="flex items-center gap-2 pt-1"><input type="checkbox" id="edit_is_active"${attr("checked", editForm.is_active, true)} class="h-4 w-4 rounded border-border"/> `);
                      Label($$renderer6, {
                        for: "edit_is_active",
                        class: "cursor-pointer",
                        children: ($$renderer7) => {
                          $$renderer7.push(`<!---->Active College / Department`);
                        },
                        $$slots: { default: true }
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
                                editDialogOpen = false;
                              },
                              children: ($$renderer8) => {
                                $$renderer8.push(`<!---->Cancel`);
                              },
                              $$slots: { default: true }
                            });
                            $$renderer7.push(`<!----> `);
                            Button($$renderer7, {
                              type: "submit",
                              disabled: editForm.processing,
                              children: ($$renderer8) => {
                                $$renderer8.push(`<!---->Save Changes`);
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
  Departments as default
};
