import { h as head, e as escape_html, B as Button, a as ensure_array_like } from "../app.js";
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
function Privacy($$renderer, $$props) {
  $$renderer.component(($$renderer2) => {
    let { policies = [] } = $$props;
    const form = useForm({ title: "", content: "", is_active: false });
    function activate(policy) {
      router.post(`/setup/privacy/${policy.id}/activate`);
    }
    const breadcrumbs = [{ label: "Setup", href: "/setup" }, { label: "Privacy" }];
    let $$settled = true;
    let $$inner_renderer;
    function $$render_inner($$renderer3) {
      head("1upkhp3", $$renderer3, ($$renderer4) => {
        $$renderer4.title(($$renderer5) => {
          $$renderer5.push(`<title>Privacy Policy · Setup</title>`);
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
              title: "Privacy Policy",
              description: "Author institutional data privacy statements and designate the active policy required for applicant intake consent.",
              actions
            });
          }
          $$renderer4.push(`<!----> <form class="grid gap-3 rounded-xl border border-border bg-card p-4"><div class="space-y-1.5">`);
          Label($$renderer4, {
            for: "title",
            children: ($$renderer5) => {
              $$renderer5.push(`<!---->Title`);
            },
            $$slots: { default: true }
          });
          $$renderer4.push(`<!----> `);
          Input($$renderer4, {
            id: "title",
            class: "min-h-10",
            get value() {
              return form.title;
            },
            set value($$value) {
              form.title = $$value;
              $$settled = false;
            }
          });
          $$renderer4.push(`<!----></div> <div class="space-y-1.5">`);
          Label($$renderer4, {
            for: "content",
            children: ($$renderer5) => {
              $$renderer5.push(`<!---->Content`);
            },
            $$slots: { default: true }
          });
          $$renderer4.push(`<!----> <textarea id="content" rows="6" class="border-input bg-background flex w-full rounded-md border px-3 py-2 text-sm">`);
          const $$body = escape_html(form.content);
          if ($$body) {
            $$renderer4.push(`${$$body}`);
          }
          $$renderer4.push(`</textarea></div> `);
          Button($$renderer4, {
            type: "submit",
            class: "min-h-10 w-fit",
            disabled: form.processing,
            children: ($$renderer5) => {
              $$renderer5.push(`<!---->Create policy`);
            },
            $$slots: { default: true }
          });
          $$renderer4.push(`<!----></form> <div class="rounded-xl border border-border/80 bg-card/60 backdrop-blur-md shadow-xs overflow-hidden">`);
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
                                  $$renderer8.push(`<!---->Title`);
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
                      const each_array = ensure_array_like(policies);
                      for (let $$index = 0, $$length = each_array.length; $$index < $$length; $$index++) {
                        let p = each_array[$$index];
                        if (Table_row) {
                          $$renderer6.push("<!--[-->");
                          Table_row($$renderer6, {
                            children: ($$renderer7) => {
                              if (Table_cell) {
                                $$renderer7.push("<!--[-->");
                                Table_cell($$renderer7, {
                                  children: ($$renderer8) => {
                                    $$renderer8.push(`<!---->${escape_html(p.title)}`);
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
                                    if (!p.is_active) {
                                      $$renderer8.push("<!--[0-->");
                                      Button($$renderer8, {
                                        size: "sm",
                                        variant: "outline",
                                        onclick: () => activate(p),
                                        children: ($$renderer9) => {
                                          $$renderer9.push(`<!---->Activate`);
                                        },
                                        $$slots: { default: true }
                                      });
                                    } else {
                                      $$renderer8.push("<!--[-1-->");
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
  Privacy as default
};
