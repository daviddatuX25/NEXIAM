import { h as head, e as escape_html, b as attr, c as attr_class, a as ensure_array_like, B as Button } from "../app.js";
import { S as StaffLayout } from "./StaffLayout-CR-MZopa.js";
import { P as PageHeaderBar } from "./PageHeaderBar-DJhT5vOR.js";
import { B as Badge } from "./badge-D9Vgs7xh.js";
import { I as Input } from "./input-BUZAZKIK.js";
import { T as Table, a as Table_header, b as Table_row, c as Table_head, d as Table_body, e as Table_cell } from "./table-row-_ffWKrps.js";
import "clsx";
import { S as SimplePagination } from "./SimplePagination-Bcq5R_Kb.js";
import { S as Search } from "./settings-C5xOKpV3.js";
import { B as Building_2 } from "./building-2-BaeEMGXC.js";
import { L as Link } from "./Link-B7ks7rBK.js";
import { P as Plus } from "./plus-DjyPLC7A.js";
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
    let {
      rooms,
      filters = {},
      kind_counts = {},
      enable_seating_arrangements = false,
      exam_feature_policy_version = 1,
      errors = {}
    } = $$props;
    let seatingPolicySaving = false;
    let search = filters.search ?? "";
    let selectedKind = filters.kind ?? "";
    let timer;
    function filterRooms() {
      clearTimeout(timer);
      timer = setTimeout(
        () => router.get(
          "/setup/rooms",
          {
            search: search || void 0,
            kind: selectedKind || void 0,
            page: 1
          },
          { preserveState: true, replace: true }
        ),
        250
      );
    }
    const breadcrumbs = [
      { label: "Setup", href: "/setup" },
      { label: "Rooms & Seating" }
    ];
    let $$settled = true;
    let $$inner_renderer;
    function $$render_inner($$renderer3) {
      head("ush63w", $$renderer3, ($$renderer4) => {
        $$renderer4.title(($$renderer5) => {
          $$renderer5.push(`<title>Rooms &amp; Seating · Setup</title>`);
        });
      });
      StaffLayout($$renderer3, {
        breadcrumbs,
        children: ($$renderer4) => {
          $$renderer4.push(`<div class="min-w-0 space-y-6 p-4 sm:p-6">`);
          {
            let actions = function($$renderer5) {
              Link($$renderer5, {
                href: "/setup/rooms/create",
                children: ($$renderer6) => {
                  Button($$renderer6, {
                    class: "min-h-11 gap-2",
                    children: ($$renderer7) => {
                      Plus($$renderer7, { class: "h-4 w-4" });
                      $$renderer7.push(`<!---->Add room`);
                    },
                    $$slots: { default: true }
                  });
                },
                $$slots: { default: true }
              });
            };
            PageHeaderBar($$renderer4, {
              title: "Rooms & Seating",
              description: "Manage examination venues, room capacities, and default seating arrangements configured for test administrations.",
              actions
            });
          }
          $$renderer4.push(`<!----> <section class="craft-card-depth flex flex-col gap-4 rounded-xl border border-border/70 p-4 sm:flex-row sm:items-start sm:justify-between"><div class="min-w-0 space-y-1"><div class="flex flex-wrap items-center gap-2"><h2 class="text-sm font-semibold">Assigned seating availability</h2> `);
          Badge($$renderer4, {
            variant: enable_seating_arrangements ? "default" : "secondary",
            children: ($$renderer5) => {
              $$renderer5.push(`<!---->${escape_html(enable_seating_arrangements ? "Available for new sessions" : "Simple capacity default")}`);
            },
            $$slots: { default: true }
          });
          $$renderer4.push(`<!----></div> <p id="seating-policy-help" class="text-muted-foreground max-w-2xl text-sm">${escape_html(enable_seating_arrangements ? "New sessions still use room capacity until staff explicitly assigns physical seats. Published seating stays unchanged." : "New sessions use room capacity. Existing arrangements and published exam records are retained.")}</p></div> <label class="flex min-h-11 shrink-0 cursor-pointer items-center gap-2.5 text-sm font-medium select-none"><input type="checkbox" class="accent-primary size-4 rounded focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-ring"${attr("checked", enable_seating_arrangements, true)} aria-describedby="seating-policy-help"${attr("disabled", seatingPolicySaving, true)}/> <span>Allow assigned seating</span> `);
          {
            $$renderer4.push("<!--[-1-->");
          }
          $$renderer4.push(`<!--]--></label></section> `);
          if (errors.policy) {
            $$renderer4.push("<!--[0-->");
            $$renderer4.push(`<p class="text-destructive text-sm" role="alert">${escape_html(errors.policy)}</p>`);
          } else {
            $$renderer4.push("<!--[-1-->");
          }
          $$renderer4.push(`<!--]--> <div class="flex flex-wrap items-center gap-1.5 border-b border-border pb-2 text-xs font-mono"><button type="button"${attr_class(`rounded-lg px-3 py-1.5 transition-all ${selectedKind === "" ? "bg-primary text-primary-foreground font-bold shadow-xs" : "text-muted-foreground hover:text-foreground"}`)}>All (${escape_html(kind_counts.all ?? 0)})</button> <button type="button"${attr_class(`rounded-lg px-3 py-1.5 transition-all ${selectedKind === "room" ? "bg-primary text-primary-foreground font-bold shadow-xs" : "text-muted-foreground hover:text-foreground"}`)}>Exam Rooms (${escape_html(kind_counts.room ?? 0)})</button> <button type="button"${attr_class(`rounded-lg px-3 py-1.5 transition-all ${selectedKind === "assembly_venue" ? "bg-primary text-primary-foreground font-bold shadow-xs" : "text-muted-foreground hover:text-foreground"}`)}>Assembly Venues (${escape_html(kind_counts.assembly_venue ?? 0)})</button> <button type="button"${attr_class(`rounded-lg px-3 py-1.5 transition-all ${selectedKind === "service_point" ? "bg-primary text-primary-foreground font-bold shadow-xs" : "text-muted-foreground hover:text-foreground"}`)}>Service Windows (${escape_html(kind_counts.service_point ?? 0)})</button></div> <div class="relative max-w-sm">`);
          Search($$renderer4, {
            class: "text-muted-foreground pointer-events-none absolute top-1/2 left-3 h-4 w-4 -translate-y-1/2"
          });
          $$renderer4.push(`<!----> `);
          Input($$renderer4, {
            type: "search",
            oninput: filterRooms,
            placeholder: "Search space code or name",
            class: "min-h-11 pl-9",
            get value() {
              return search;
            },
            set value($$value) {
              search = $$value;
              $$settled = false;
            }
          });
          $$renderer4.push(`<!----></div> `);
          if (rooms.data.length === 0) {
            $$renderer4.push("<!--[0-->");
            $$renderer4.push(`<div class="craft-card-depth rounded-xl border border-dashed border-border/70 px-6 py-14 text-center">`);
            Building_2($$renderer4, { class: "text-muted-foreground mx-auto h-8 w-8" });
            $$renderer4.push(`<!----> <h2 class="mt-4 font-semibold">${escape_html(search ? "No matching rooms" : "No rooms yet")}</h2> <p class="text-muted-foreground mt-1 text-sm">${escape_html(search ? "Try a different code or room name." : "Add the first exam room to define seating layouts.")}</p></div>`);
          } else {
            $$renderer4.push("<!--[-1-->");
            $$renderer4.push(`<div class="craft-card-depth hidden rounded-xl border border-border/80 bg-card/60 backdrop-blur-md shadow-xs overflow-x-auto md:block">`);
            if (Table) {
              $$renderer4.push("<!--[-->");
              Table($$renderer4, {
                class: "min-w-[720px]",
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
                                    $$renderer8.push(`<!---->Space`);
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
                                    $$renderer8.push(`<!---->Kind`);
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
                                    $$renderer8.push(`<!---->Capacity`);
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
                                    $$renderer8.push(`<!---->Seating arrangements`);
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
                                    $$renderer8.push(`<!---->Status`);
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
                        const each_array = ensure_array_like(rooms.data);
                        for (let $$index = 0, $$length = each_array.length; $$index < $$length; $$index++) {
                          let room = each_array[$$index];
                          if (Table_row) {
                            $$renderer6.push("<!--[-->");
                            Table_row($$renderer6, {
                              children: ($$renderer7) => {
                                if (Table_cell) {
                                  $$renderer7.push("<!--[-->");
                                  Table_cell($$renderer7, {
                                    children: ($$renderer8) => {
                                      $$renderer8.push(`<p class="font-medium">${escape_html(room.name)}</p> <p class="text-muted-foreground font-mono text-xs">${escape_html(room.code)}</p>`);
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
                                      if (room.space_kind === "assembly_venue") {
                                        $$renderer8.push("<!--[0-->");
                                        Badge($$renderer8, {
                                          variant: "outline",
                                          class: "border-blue-500/30 text-blue-600 dark:text-blue-400",
                                          children: ($$renderer9) => {
                                            $$renderer9.push(`<!---->Assembly Venue`);
                                          },
                                          $$slots: { default: true }
                                        });
                                      } else if (room.space_kind === "service_point") {
                                        $$renderer8.push("<!--[1-->");
                                        Badge($$renderer8, {
                                          variant: "outline",
                                          class: "border-amber-500/30 text-amber-600 dark:text-amber-400",
                                          children: ($$renderer9) => {
                                            $$renderer9.push(`<!---->Service Window`);
                                          },
                                          $$slots: { default: true }
                                        });
                                      } else {
                                        $$renderer8.push("<!--[-1-->");
                                        Badge($$renderer8, {
                                          variant: "secondary",
                                          children: ($$renderer9) => {
                                            $$renderer9.push(`<!---->Exam Room`);
                                          },
                                          $$slots: { default: true }
                                        });
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
                                      $$renderer8.push(`<!---->${escape_html(room.capacity)} seats`);
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
                                      if (!enable_seating_arrangements) {
                                        $$renderer8.push("<!--[0-->");
                                        $$renderer8.push(`<span class="text-muted-foreground text-xs font-mono">Simple Capacity Mode</span>`);
                                      } else if (room.space_kind && room.space_kind !== "room") {
                                        $$renderer8.push("<!--[1-->");
                                        $$renderer8.push(`<span class="text-muted-foreground text-xs font-mono">Bypassed (Open/Deskless)</span>`);
                                      } else {
                                        $$renderer8.push("<!--[-1-->");
                                        $$renderer8.push(`${escape_html(room.active_seating_arrangements_count ?? room.active_seating_profiles_count ?? 0)} active ·
                                        ${escape_html(room.seating_arrangements_count ?? room.seating_profiles_count ?? 0)} total`);
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
                                        variant: room.is_active ? "default" : "secondary",
                                        children: ($$renderer9) => {
                                          $$renderer9.push(`<!---->${escape_html(room.is_active ? "Active" : "Inactive")}`);
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
                                        href: `/setup/rooms/${room.id}`,
                                        children: ($$renderer9) => {
                                          Button($$renderer9, {
                                            size: "sm",
                                            variant: "outline",
                                            children: ($$renderer10) => {
                                              $$renderer10.push(`<!---->Manage`);
                                            },
                                            $$slots: { default: true }
                                          });
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
            const each_array_1 = ensure_array_like(rooms.data);
            for (let $$index_1 = 0, $$length = each_array_1.length; $$index_1 < $$length; $$index_1++) {
              let room = each_array_1[$$index_1];
              $$renderer4.push(`<li class="border-border bg-card rounded-xl border p-4"><div class="flex items-start justify-between gap-3"><div><h2 class="font-semibold">${escape_html(room.name)}</h2> <p class="text-muted-foreground font-mono text-xs">${escape_html(room.code)}</p></div> `);
              Badge($$renderer4, {
                variant: room.is_active ? "default" : "secondary",
                children: ($$renderer5) => {
                  $$renderer5.push(`<!---->${escape_html(room.is_active ? "Active" : "Inactive")}`);
                },
                $$slots: { default: true }
              });
              $$renderer4.push(`<!----></div> <dl class="mt-4 grid grid-cols-2 gap-3 text-sm"><div><dt class="text-muted-foreground">Capacity</dt> <dd class="font-medium">${escape_html(room.capacity)} seats</dd></div> <div><dt class="text-muted-foreground">Arrangements</dt> <dd class="font-medium">${escape_html(room.active_seating_arrangements_count ?? room.active_seating_profiles_count ?? 0)} active</dd></div></dl> `);
              Link($$renderer4, {
                href: `/setup/rooms/${room.id}`,
                class: "mt-4 block",
                children: ($$renderer5) => {
                  Button($$renderer5, {
                    variant: "outline",
                    class: "min-h-11 w-full",
                    children: ($$renderer6) => {
                      $$renderer6.push(`<!---->Manage room`);
                    },
                    $$slots: { default: true }
                  });
                },
                $$slots: { default: true }
              });
              $$renderer4.push(`<!----></li>`);
            }
            $$renderer4.push(`<!--]--></ul>`);
          }
          $$renderer4.push(`<!--]--> `);
          SimplePagination($$renderer4, { data: rooms });
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
  Index as default
};
