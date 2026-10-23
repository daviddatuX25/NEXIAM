import { h as head, B as Button, a as ensure_array_like, e as escape_html, c as attr_class, d as derived } from "../app.js";
import { S as StaffLayout } from "./StaffLayout-CR-MZopa.js";
import { P as PageHeaderBar } from "./PageHeaderBar-DJhT5vOR.js";
import { S as SwitchableListView } from "./SwitchableListView-DHKJpEKr.js";
import { B as Badge } from "./badge-D9Vgs7xh.js";
import { T as Table, a as Table_header, b as Table_row, c as Table_head, d as Table_body, e as Table_cell } from "./table-row-_ffWKrps.js";
import "clsx";
import { L as Link } from "./Link-B7ks7rBK.js";
import { P as Plus } from "./plus-DjyPLC7A.js";
import { P as Pencil } from "./pencil-0tPPVae8.js";
import { P as Pause } from "./pause-DNvJ1Xre.js";
import { P as Play } from "./play-DnSz4pBE.js";
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
import "./roving-focus-group-BJDH9kUk.js";
import "./table-2-BLgeaPqt.js";
import "./layout-grid-CXJfSBqV.js";
function Cycles($$renderer, $$props) {
  $$renderer.component(($$renderer2) => {
    let { cycles = [] } = $$props;
    let viewMode = "responsive";
    const hasActive = derived(() => (cycles ?? []).some((ay) => ay.is_active));
    const breadcrumbs = [
      { label: "Setup", href: "/setup" },
      { label: "Academic Years" }
    ];
    let $$settled = true;
    let $$inner_renderer;
    function $$render_inner($$renderer3) {
      head("14h4xbu", $$renderer3, ($$renderer4) => {
        $$renderer4.title(($$renderer5) => {
          $$renderer5.push(`<title>Academic Years · Setup</title>`);
        });
      });
      StaffLayout($$renderer3, {
        breadcrumbs,
        children: ($$renderer4) => {
          $$renderer4.push(`<div class="min-w-0 space-y-6 p-4 sm:p-6">`);
          {
            let actions = function($$renderer5) {
              Link($$renderer5, {
                href: "/setup/cycles/create",
                children: ($$renderer6) => {
                  Button($$renderer6, {
                    class: "min-h-10 gap-2",
                    children: ($$renderer7) => {
                      Plus($$renderer7, { class: "size-4" });
                      $$renderer7.push(`<!----> Add academic year`);
                    },
                    $$slots: { default: true }
                  });
                },
                $$slots: { default: true }
              });
            };
            PageHeaderBar($$renderer4, {
              title: "Academic Years",
              description: "Manage institutional academic years and semesters, configure application windows, and set the active admissions cycle.",
              actions
            });
          }
          $$renderer4.push(`<!----> `);
          if (!hasActive()) {
            $$renderer4.push("<!--[0-->");
            $$renderer4.push(`<div class="rounded-md border border-amber-500/50 bg-amber-500/10 px-4 py-3 text-sm text-foreground">No academic year is currently active. Applications stay closed until one is activated.</div>`);
          } else {
            $$renderer4.push("<!--[-1-->");
          }
          $$renderer4.push(`<!--]--> `);
          {
            let table = function($$renderer5) {
              if (Table) {
                $$renderer5.push("<!--[-->");
                Table($$renderer5, {
                  class: "w-full min-w-[640px] text-sm",
                  children: ($$renderer6) => {
                    if (Table_header) {
                      $$renderer6.push("<!--[-->");
                      Table_header($$renderer6, {
                        class: "bg-muted/50",
                        children: ($$renderer7) => {
                          if (Table_row) {
                            $$renderer7.push("<!--[-->");
                            Table_row($$renderer7, {
                              children: ($$renderer8) => {
                                if (Table_head) {
                                  $$renderer8.push("<!--[-->");
                                  Table_head($$renderer8, {
                                    children: ($$renderer9) => {
                                      $$renderer9.push(`<!---->Academic year`);
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
                                      $$renderer9.push(`<!---->Application window`);
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
                                      $$renderer9.push(`<!---->Status`);
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
                                    class: "text-center",
                                    children: ($$renderer9) => {
                                      $$renderer9.push(`<!---->Actions`);
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
                          const each_array = ensure_array_like(cycles);
                          if (each_array.length !== 0) {
                            $$renderer7.push("<!--[-->");
                            for (let $$index = 0, $$length = each_array.length; $$index < $$length; $$index++) {
                              let ay = each_array[$$index];
                              if (Table_row) {
                                $$renderer7.push("<!--[-->");
                                Table_row($$renderer7, {
                                  class: ay.is_active ? "" : "text-muted-foreground/70",
                                  children: ($$renderer8) => {
                                    if (Table_cell) {
                                      $$renderer8.push("<!--[-->");
                                      Table_cell($$renderer8, {
                                        class: "font-semibold tracking-tight",
                                        children: ($$renderer9) => {
                                          $$renderer9.push(`<!---->${escape_html(ay.label)}`);
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
                                        class: "text-muted-foreground",
                                        children: ($$renderer9) => {
                                          $$renderer9.push(`<!---->${escape_html(ay.application_window)}`);
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
                                          Badge($$renderer9, {
                                            variant: ay.is_active ? "default" : "secondary",
                                            children: ($$renderer10) => {
                                              $$renderer10.push(`<!---->${escape_html(ay.is_active ? "Active" : "Inactive")}`);
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
                                    $$renderer8.push(` `);
                                    if (Table_cell) {
                                      $$renderer8.push("<!--[-->");
                                      Table_cell($$renderer8, {
                                        class: "text-center",
                                        children: ($$renderer9) => {
                                          $$renderer9.push(`<div class="inline-flex flex-wrap justify-center gap-1">`);
                                          Link($$renderer9, {
                                            href: `/setup/cycles/${ay.id}/edit`,
                                            children: ($$renderer10) => {
                                              Button($$renderer10, {
                                                variant: "ghost",
                                                size: "sm",
                                                class: "h-8 px-2 text-xs font-semibold",
                                                children: ($$renderer11) => {
                                                  Pencil($$renderer11, { class: "mr-1.5 size-3.5" });
                                                  $$renderer11.push(`<!----> Edit`);
                                                },
                                                $$slots: { default: true }
                                              });
                                            },
                                            $$slots: { default: true }
                                          });
                                          $$renderer9.push(`<!----> `);
                                          Button($$renderer9, {
                                            variant: "ghost",
                                            size: "sm",
                                            class: `h-8 px-2 text-xs font-semibold ${ay.is_active ? "text-amber-700 hover:bg-amber-50 dark:text-amber-400" : "text-primary hover:bg-primary/5"}`,
                                            onclick: () => ay.is_active ? router.post(`/setup/cycles/${ay.id}/deactivate`) : router.post(`/setup/cycles/${ay.id}/activate`),
                                            children: ($$renderer10) => {
                                              if (ay.is_active) {
                                                $$renderer10.push("<!--[0-->");
                                                Pause($$renderer10, { class: "mr-1.5 size-3.5" });
                                                $$renderer10.push(`<!----> Deactivate`);
                                              } else {
                                                $$renderer10.push("<!--[-1-->");
                                                Play($$renderer10, { class: "mr-1.5 size-3.5" });
                                                $$renderer10.push(`<!----> Activate`);
                                              }
                                              $$renderer10.push(`<!--]-->`);
                                            },
                                            $$slots: { default: true }
                                          });
                                          $$renderer9.push(`<!----></div>`);
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
                          } else {
                            $$renderer7.push("<!--[!-->");
                            if (Table_row) {
                              $$renderer7.push("<!--[-->");
                              Table_row($$renderer7, {
                                children: ($$renderer8) => {
                                  if (Table_cell) {
                                    $$renderer8.push("<!--[-->");
                                    Table_cell($$renderer8, {
                                      colspan: 4,
                                      class: "py-12 text-center text-muted-foreground",
                                      children: ($$renderer9) => {
                                        $$renderer9.push(`<!---->No academic years yet.`);
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
            }, cards = function($$renderer5) {
              if (cycles.length > 0) {
                $$renderer5.push("<!--[0-->");
                $$renderer5.push(`<ul class="grid gap-3 sm:grid-cols-2 lg:grid-cols-3" role="list"><!--[-->`);
                const each_array_1 = ensure_array_like(cycles);
                for (let $$index_1 = 0, $$length = each_array_1.length; $$index_1 < $$length; $$index_1++) {
                  let ay = each_array_1[$$index_1];
                  $$renderer5.push(`<li${attr_class(`flex flex-col gap-3 rounded-xl border border-border bg-card p-4 ${ay.is_active ? "shadow-sm" : "opacity-70"}`)}><div class="flex items-start justify-between gap-2"><div class="min-w-0"><p class="truncate font-semibold tracking-tight">${escape_html(ay.label)}</p> <p class="text-sm text-muted-foreground">${escape_html(ay.application_window)}</p></div> `);
                  Badge($$renderer5, {
                    variant: ay.is_active ? "default" : "secondary",
                    class: "shrink-0",
                    children: ($$renderer6) => {
                      $$renderer6.push(`<!---->${escape_html(ay.is_active ? "Active" : "Inactive")}`);
                    },
                    $$slots: { default: true }
                  });
                  $$renderer5.push(`<!----></div> <div class="mt-auto flex gap-2 pt-2">`);
                  Link($$renderer5, {
                    href: `/setup/cycles/${ay.id}/edit`,
                    class: "flex-1",
                    children: ($$renderer6) => {
                      Button($$renderer6, {
                        variant: "outline",
                        size: "sm",
                        class: "min-h-10 w-full font-semibold",
                        children: ($$renderer7) => {
                          Pencil($$renderer7, { class: "mr-1.5 size-3.5" });
                          $$renderer7.push(`<!----> Edit`);
                        },
                        $$slots: { default: true }
                      });
                    },
                    $$slots: { default: true }
                  });
                  $$renderer5.push(`<!----> `);
                  Button($$renderer5, {
                    variant: "outline",
                    size: "sm",
                    class: "min-h-10 flex-1 font-semibold",
                    onclick: () => ay.is_active ? router.post(`/setup/cycles/${ay.id}/deactivate`) : router.post(`/setup/cycles/${ay.id}/activate`),
                    children: ($$renderer6) => {
                      if (ay.is_active) {
                        $$renderer6.push("<!--[0-->");
                        Pause($$renderer6, { class: "mr-1.5 size-3.5" });
                        $$renderer6.push(`<!----> Deactivate`);
                      } else {
                        $$renderer6.push("<!--[-1-->");
                        Play($$renderer6, { class: "mr-1.5 size-3.5" });
                        $$renderer6.push(`<!----> Activate`);
                      }
                      $$renderer6.push(`<!--]-->`);
                    },
                    $$slots: { default: true }
                  });
                  $$renderer5.push(`<!----></div></li>`);
                }
                $$renderer5.push(`<!--]--></ul>`);
              } else {
                $$renderer5.push("<!--[-1-->");
                $$renderer5.push(`<p class="py-12 text-center text-muted-foreground">No academic years yet.</p>`);
              }
              $$renderer5.push(`<!--]-->`);
            };
            SwitchableListView($$renderer4, {
              overflow: "auto",
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
  Cycles as default
};
