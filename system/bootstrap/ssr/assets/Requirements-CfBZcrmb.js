import { h as head, e as escape_html, b as attr, B as Button, a as ensure_array_like } from "../app.js";
import { S as StaffLayout } from "./StaffLayout-CR-MZopa.js";
import { P as PageHeaderBar } from "./PageHeaderBar-DJhT5vOR.js";
import { B as Badge } from "./badge-D9Vgs7xh.js";
import { I as Input } from "./input-BUZAZKIK.js";
import { L as Label } from "./label-dGY6L9vm.js";
import { T as Table, a as Table_header, b as Table_row, c as Table_head, d as Table_body, e as Table_cell } from "./table-row-_ffWKrps.js";
import "clsx";
import { u as useForm } from "./useForm.svelte-C0OkDFGu.js";
import { L as Link } from "./Link-B7ks7rBK.js";
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
function Requirements($$renderer, $$props) {
  $$renderer.component(($$renderer2) => {
    let { requirements = [], tracks = [], kinds = [] } = $$props;
    const form = useForm({
      code: "",
      label: "",
      applicant_track: "freshman",
      item_kind: "document",
      sort_order: 100,
      is_mandatory: true,
      is_active: true
    });
    function toggleActive(req) {
      router.put(`/setup/requirements/${req.id}`, {
        label: req.label,
        applicant_track: req.applicant_track,
        item_kind: req.item_kind,
        is_mandatory: req.is_mandatory,
        is_active: !req.is_active,
        sort_order: req.sort_order
      });
    }
    const breadcrumbs = [
      { label: "Setup", href: "/setup" },
      { label: "Document requirements" }
    ];
    let $$settled = true;
    let $$inner_renderer;
    function $$render_inner($$renderer3) {
      head("10837ij", $$renderer3, ($$renderer4) => {
        $$renderer4.title(($$renderer5) => {
          $$renderer5.push(`<title>Document requirements · Setup</title>`);
        });
      });
      StaffLayout($$renderer3, {
        breadcrumbs,
        children: ($$renderer4) => {
          $$renderer4.push(`<div class="min-w-0 space-y-6 p-4 sm:p-6">`);
          {
            let actions = function($$renderer5) {
              Link($$renderer5, {
                href: "/setup",
                children: ($$renderer6) => {
                  Button($$renderer6, {
                    variant: "outline",
                    size: "sm",
                    children: ($$renderer7) => {
                      $$renderer7.push(`<!---->Back to readiness`);
                    },
                    $$slots: { default: true }
                  });
                },
                $$slots: { default: true }
              });
            };
            PageHeaderBar($$renderer4, {
              title: "Intake document requirements",
              description: "Configure mandatory physical credentials verified during applicant intake. Staff cross-check these items before issuing exam permits and releasing results.",
              actions
            });
          }
          $$renderer4.push(`<!----> <form class="grid gap-3 rounded-xl border border-border bg-card p-4 sm:grid-cols-4 lg:grid-cols-7"><div class="space-y-1.5 sm:col-span-2 lg:col-span-1">`);
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
            placeholder: "e.g. form_138",
            class: "min-h-10 font-mono text-xs",
            required: true,
            get value() {
              return form.code;
            },
            set value($$value) {
              form.code = $$value;
              $$settled = false;
            }
          });
          $$renderer4.push(`<!----> `);
          if (form.errors.code) {
            $$renderer4.push("<!--[0-->");
            $$renderer4.push(`<p class="text-destructive text-xs">${escape_html(form.errors.code)}</p>`);
          } else {
            $$renderer4.push("<!--[-1-->");
          }
          $$renderer4.push(`<!--]--></div> <div class="space-y-1.5 sm:col-span-2 lg:col-span-2">`);
          Label($$renderer4, {
            for: "label",
            children: ($$renderer5) => {
              $$renderer5.push(`<!---->Label`);
            },
            $$slots: { default: true }
          });
          $$renderer4.push(`<!----> `);
          Input($$renderer4, {
            id: "label",
            placeholder: "e.g. Report Card (Form 138 / SF9)",
            class: "min-h-10 text-xs",
            required: true,
            get value() {
              return form.label;
            },
            set value($$value) {
              form.label = $$value;
              $$settled = false;
            }
          });
          $$renderer4.push(`<!----> `);
          if (form.errors.label) {
            $$renderer4.push("<!--[0-->");
            $$renderer4.push(`<p class="text-destructive text-xs">${escape_html(form.errors.label)}</p>`);
          } else {
            $$renderer4.push("<!--[-1-->");
          }
          $$renderer4.push(`<!--]--></div> <div class="space-y-1.5">`);
          Label($$renderer4, {
            for: "track",
            children: ($$renderer5) => {
              $$renderer5.push(`<!---->Track`);
            },
            $$slots: { default: true }
          });
          $$renderer4.push(`<!----> `);
          $$renderer4.select(
            {
              id: "track",
              value: form.applicant_track,
              class: "border-input bg-background flex min-h-10 w-full rounded-md border px-3 text-xs"
            },
            ($$renderer5) => {
              $$renderer5.option({ value: "all" }, ($$renderer6) => {
                $$renderer6.push(`All Applicants`);
              });
              $$renderer5.option({ value: "freshman" }, ($$renderer6) => {
                $$renderer6.push(`Freshman`);
              });
              $$renderer5.option({ value: "transferee" }, ($$renderer6) => {
                $$renderer6.push(`Transferee`);
              });
              $$renderer5.option({ value: "als" }, ($$renderer6) => {
                $$renderer6.push(`ALS Passer`);
              });
            }
          );
          $$renderer4.push(`</div> <div class="space-y-1.5">`);
          Label($$renderer4, {
            for: "kind",
            children: ($$renderer5) => {
              $$renderer5.push(`<!---->Kind`);
            },
            $$slots: { default: true }
          });
          $$renderer4.push(`<!----> `);
          $$renderer4.select(
            {
              id: "kind",
              value: form.item_kind,
              class: "border-input bg-background flex min-h-10 w-full rounded-md border px-3 text-xs"
            },
            ($$renderer5) => {
              $$renderer5.option({ value: "document" }, ($$renderer6) => {
                $$renderer6.push(`Document / Paper`);
              });
              $$renderer5.option({ value: "container" }, ($$renderer6) => {
                $$renderer6.push(`Folder / Envelope`);
              });
            }
          );
          $$renderer4.push(`</div> <div class="space-y-1.5">`);
          Label($$renderer4, {
            for: "sort",
            children: ($$renderer5) => {
              $$renderer5.push(`<!---->Sort`);
            },
            $$slots: { default: true }
          });
          $$renderer4.push(`<!----> `);
          Input($$renderer4, {
            id: "sort",
            type: "number",
            class: "min-h-10 text-xs",
            get value() {
              return form.sort_order;
            },
            set value($$value) {
              form.sort_order = $$value;
              $$settled = false;
            }
          });
          $$renderer4.push(`<!----></div> <div class="flex items-center gap-2 sm:col-span-4 lg:col-span-1 pt-2 lg:pt-6"><label class="flex items-center gap-2 text-xs cursor-pointer select-none"><input type="checkbox"${attr("checked", form.is_mandatory, true)} class="accent-primary h-4 w-4 rounded"/> <span class="font-medium">Mandatory</span></label></div> <div class="flex items-end sm:col-span-4 lg:col-span-7">`);
          Button($$renderer4, {
            type: "submit",
            class: "min-h-10 w-full text-xs",
            disabled: form.processing,
            children: ($$renderer5) => {
              $$renderer5.push(`<!---->Add requirement`);
            },
            $$slots: { default: true }
          });
          $$renderer4.push(`<!----></div></form> <div class="border-border hidden overflow-x-auto rounded-xl border bg-card md:block">`);
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
                                  $$renderer8.push(`<!---->Requirement item`);
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
                                  $$renderer8.push(`<!---->Applies to`);
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
                                  $$renderer8.push(`<!---->Kind`);
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
                                  $$renderer8.push(`<!---->Mandatory`);
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
                      const each_array = ensure_array_like(requirements);
                      for (let $$index = 0, $$length = each_array.length; $$index < $$length; $$index++) {
                        let req = each_array[$$index];
                        if (Table_row) {
                          $$renderer6.push("<!--[-->");
                          Table_row($$renderer6, {
                            children: ($$renderer7) => {
                              if (Table_cell) {
                                $$renderer7.push("<!--[-->");
                                Table_cell($$renderer7, {
                                  class: "font-mono text-xs font-semibold",
                                  children: ($$renderer8) => {
                                    $$renderer8.push(`<!---->${escape_html(req.code)}`);
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
                                  class: "text-sm font-medium",
                                  children: ($$renderer8) => {
                                    $$renderer8.push(`<!---->${escape_html(req.label)}`);
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
                                      variant: "outline",
                                      class: "text-[11px] capitalize",
                                      children: ($$renderer9) => {
                                        $$renderer9.push(`<!---->${escape_html(req.applicant_track)}`);
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
                                  class: "text-xs text-muted-foreground capitalize",
                                  children: ($$renderer8) => {
                                    $$renderer8.push(`<!---->${escape_html(req.item_kind)}`);
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
                                    if (req.is_mandatory) {
                                      $$renderer8.push("<!--[0-->");
                                      $$renderer8.push(`<span class="text-xs font-semibold text-emerald-600 dark:text-emerald-400">Yes</span>`);
                                    } else {
                                      $$renderer8.push("<!--[-1-->");
                                      $$renderer8.push(`<span class="text-xs text-muted-foreground">Optional</span>`);
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
                                      variant: req.is_active ? "default" : "secondary",
                                      children: ($$renderer9) => {
                                        $$renderer9.push(`<!---->${escape_html(req.is_active ? "Active" : "Inactive")}`);
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
                                    Button($$renderer8, {
                                      size: "sm",
                                      variant: "outline",
                                      onclick: () => toggleActive(req),
                                      children: ($$renderer9) => {
                                        $$renderer9.push(`<!---->${escape_html(req.is_active ? "Deactivate" : "Activate")}`);
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
          $$renderer4.push(`</div> <ul class="grid gap-3 md:hidden" role="list"><!--[-->`);
          const each_array_1 = ensure_array_like(requirements);
          for (let $$index_1 = 0, $$length = each_array_1.length; $$index_1 < $$length; $$index_1++) {
            let req = each_array_1[$$index_1];
            $$renderer4.push(`<li class="border-border bg-card rounded-xl border p-4 space-y-2.5"><div class="flex items-start justify-between gap-2"><div><p class="font-semibold text-sm">${escape_html(req.label)}</p> <p class="text-muted-foreground font-mono text-xs">${escape_html(req.code)}</p></div> `);
            Badge($$renderer4, {
              variant: req.is_active ? "default" : "secondary",
              children: ($$renderer5) => {
                $$renderer5.push(`<!---->${escape_html(req.is_active ? "Active" : "Inactive")}`);
              },
              $$slots: { default: true }
            });
            $$renderer4.push(`<!----></div> <div class="flex items-center gap-2 text-xs font-mono text-muted-foreground"><span class="capitalize">${escape_html(req.applicant_track)}</span> <span>•</span> <span class="capitalize">${escape_html(req.item_kind)}</span> <span>•</span> <span>${escape_html(req.is_mandatory ? "Mandatory" : "Optional")}</span></div> <div class="pt-2 border-t border-border/60 flex justify-end">`);
            Button($$renderer4, {
              size: "sm",
              variant: "outline",
              onclick: () => toggleActive(req),
              children: ($$renderer5) => {
                $$renderer5.push(`<!---->${escape_html(req.is_active ? "Deactivate" : "Activate")}`);
              },
              $$slots: { default: true }
            });
            $$renderer4.push(`<!----></div></li>`);
          }
          $$renderer4.push(`<!--]--></ul></div>`);
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
  Requirements as default
};
