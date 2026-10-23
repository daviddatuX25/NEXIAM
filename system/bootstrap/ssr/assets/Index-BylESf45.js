import { g as sanitize_props, i as spread_props, j as slot, h as head, e as escape_html, B as Button, a as ensure_array_like, c as attr_class, d as derived } from "../app.js";
import { S as StaffLayout } from "./StaffLayout-CR-MZopa.js";
import { P as PageHeaderBar } from "./PageHeaderBar-DJhT5vOR.js";
import { F as FilterToolbar } from "./FilterToolbar-Cl8Y1Mdp.js";
import { A as ActiveFilterChips } from "./ActiveFilterChips-BQD2-ACV.js";
import { S as SwitchableListView } from "./SwitchableListView-DHKJpEKr.js";
import { S as SimplePagination } from "./SimplePagination-Bcq5R_Kb.js";
import { A as ActionDropdown } from "./ActionDropdown-DmJUv-tL.js";
import { B as Badge } from "./badge-D9Vgs7xh.js";
import { T as Table, a as Table_header, b as Table_row, c as Table_head, d as Table_body, e as Table_cell } from "./table-row-_ffWKrps.js";
import "clsx";
import { G as Grid_3x3 } from "./grid-3x3-D2MHQ08_.js";
import { L as Link } from "./Link-B7ks7rBK.js";
import { P as Plus } from "./plus-DjyPLC7A.js";
import { P as Pencil } from "./pencil-0tPPVae8.js";
import { I as Icon } from "./Icon-Dal7PqKK.js";
import { router } from "@inertiajs/core";
import "axios";
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
function Power_off($$renderer, $$props) {
  const $$sanitized_props = sanitize_props($$props);
  const iconNode = [
    ["path", { "d": "M18.36 6.64A9 9 0 0 1 20.77 15" }],
    ["path", { "d": "M6.16 6.16a9 9 0 1 0 12.68 12.68" }],
    ["path", { "d": "M12 2v4" }],
    ["path", { "d": "m2 2 20 20" }]
  ];
  Icon($$renderer, spread_props([
    { name: "power-off" },
    $$sanitized_props,
    {
      /**
       * @component @name PowerOff
       * @description Lucide SVG icon component, renders SVG Element with children.
       *
       * @preview ![img](data:image/svg+xml;base64,PHN2ZyAgeG1sbnM9Imh0dHA6Ly93d3cudzMub3JnLzIwMDAvc3ZnIgogIHdpZHRoPSIyNCIKICBoZWlnaHQ9IjI0IgogIHZpZXdCb3g9IjAgMCAyNCAyNCIKICBmaWxsPSJub25lIgogIHN0cm9rZT0iIzAwMCIgc3R5bGU9ImJhY2tncm91bmQtY29sb3I6ICNmZmY7IGJvcmRlci1yYWRpdXM6IDJweCIKICBzdHJva2Utd2lkdGg9IjIiCiAgc3Ryb2tlLWxpbmVjYXA9InJvdW5kIgogIHN0cm9rZS1saW5lam9pbj0icm91bmQiCj4KICA8cGF0aCBkPSJNMTguMzYgNi42NEE5IDkgMCAwIDEgMjAuNzcgMTUiIC8+CiAgPHBhdGggZD0iTTYuMTYgNi4xNmE5IDkgMCAxIDAgMTIuNjggMTIuNjgiIC8+CiAgPHBhdGggZD0iTTEyIDJ2NCIgLz4KICA8cGF0aCBkPSJtMiAyIDIwIDIwIiAvPgo8L3N2Zz4K) - https://lucide.dev/icons/power-off
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
function Power($$renderer, $$props) {
  const $$sanitized_props = sanitize_props($$props);
  const iconNode = [
    ["path", { "d": "M12 2v10" }],
    ["path", { "d": "M18.4 6.6a9 9 0 1 1-12.77.04" }]
  ];
  Icon($$renderer, spread_props([
    { name: "power" },
    $$sanitized_props,
    {
      /**
       * @component @name Power
       * @description Lucide SVG icon component, renders SVG Element with children.
       *
       * @preview ![img](data:image/svg+xml;base64,PHN2ZyAgeG1sbnM9Imh0dHA6Ly93d3cudzMub3JnLzIwMDAvc3ZnIgogIHdpZHRoPSIyNCIKICBoZWlnaHQ9IjI0IgogIHZpZXdCb3g9IjAgMCAyNCAyNCIKICBmaWxsPSJub25lIgogIHN0cm9rZT0iIzAwMCIgc3R5bGU9ImJhY2tncm91bmQtY29sb3I6ICNmZmY7IGJvcmRlci1yYWRpdXM6IDJweCIKICBzdHJva2Utd2lkdGg9IjIiCiAgc3Ryb2tlLWxpbmVjYXA9InJvdW5kIgogIHN0cm9rZS1saW5lam9pbj0icm91bmQiCj4KICA8cGF0aCBkPSJNMTIgMnYxMCIgLz4KICA8cGF0aCBkPSJNMTguNCA2LjZhOSA5IDAgMSAxLTEyLjc3LjA0IiAvPgo8L3N2Zz4K) - https://lucide.dev/icons/power
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
function Index($$renderer, $$props) {
  $$renderer.component(($$renderer2) => {
    let { page_state = "ready", arrangements = [], filters = {} } = $$props;
    let viewMode = "responsive";
    let search = filters.search ?? "";
    let status = filters.status ?? "";
    const rows = derived(() => Array.isArray(arrangements) ? arrangements : arrangements?.data ?? []);
    const hasPagination = derived(() => !Array.isArray(arrangements) && arrangements?.last_page);
    const activeFilterChips = derived(() => [
      ...search ? [{ id: "search", label: `Search: ${search}` }] : [],
      ...status ? [
        {
          id: "status",
          label: status === "active" ? "Active" : "Inactive"
        }
      ] : []
    ]);
    function applyFilters() {
      router.get("/setup/seating-arrangements", { search: search || void 0, status: status || void 0 }, { preserveState: true, preserveScroll: true, replace: true });
    }
    function clearFilter(id) {
      if (id === "search") search = "";
      if (id === "status") status = "";
      applyFilters();
    }
    function clearAllFilters() {
      search = "";
      status = "";
      applyFilters();
    }
    function arrangementLabel(arrangement) {
      return arrangement.name ?? arrangement.label ?? `Arrangement ${arrangement.id}`;
    }
    function patternLabel(pattern) {
      return {
        uniform: "Uniform A",
        checkerboard: "Checkerboard A/B",
        latin_four: "Latin four A–D",
        linear_alternating: "Linear alternating A/B"
      }[pattern] ?? pattern ?? "—";
    }
    function availabilityLabel(arrangement) {
      if (arrangement.availability_label) return arrangement.availability_label;
      return arrangement.availability_mode === "selected_rooms" ? `Selected rooms (${arrangement.rooms_count ?? arrangement.room_count ?? (arrangement.rooms ?? []).length})` : "All compatible rooms";
    }
    function actionItems(arrangement) {
      return [
        {
          label: "Edit arrangement",
          href: `/setup/seating-arrangements/${arrangement.id}/edit`,
          icon: Pencil
        },
        {
          label: arrangement.is_active ? "Deactivate" : "Activate",
          href: `/setup/seating-arrangements/${arrangement.id}/${arrangement.is_active ? "deactivate" : "activate"}`,
          method: "post",
          icon: arrangement.is_active ? Power_off : Power,
          danger: arrangement.is_active
        }
      ];
    }
    const breadcrumbs = [
      { label: "Setup", href: "/setup" },
      { label: "Seating Arrangements" }
    ];
    let $$settled = true;
    let $$inner_renderer;
    function $$render_inner($$renderer3) {
      head("w49uh6", $$renderer3, ($$renderer4) => {
        $$renderer4.title(($$renderer5) => {
          $$renderer5.push(`<title>Seating Arrangements · Setup</title>`);
        });
      });
      StaffLayout($$renderer3, {
        breadcrumbs,
        children: ($$renderer4) => {
          $$renderer4.push(`<div class="min-w-0 space-y-6 p-4 sm:p-6">`);
          {
            let actions = function($$renderer5) {
              Link($$renderer5, {
                href: "/setup/seating-arrangements/create",
                children: ($$renderer6) => {
                  Button($$renderer6, {
                    class: "min-h-11 gap-2",
                    children: ($$renderer7) => {
                      Plus($$renderer7, { class: "h-4 w-4" });
                      $$renderer7.push(`<!----><span class="hidden sm:inline">Create arrangement</span>`);
                    },
                    $$slots: { default: true }
                  });
                },
                $$slots: { default: true }
              });
            };
            PageHeaderBar($$renderer4, {
              title: "Seating Arrangements",
              description: "Create and maintain reusable seating layouts and desk arrangements for examination venues.",
              actions
            });
          }
          $$renderer4.push(`<!----> <div>`);
          {
            let desktopFilters = function($$renderer5) {
              $$renderer5.push(`<label class="space-y-1.5"><span class="text-xs font-medium text-muted-foreground">Status</span> `);
              $$renderer5.select(
                {
                  value: status,
                  class: "border-input bg-background min-h-10 rounded-md border px-3 text-sm"
                },
                ($$renderer6) => {
                  $$renderer6.option({ value: "" }, ($$renderer7) => {
                    $$renderer7.push(`All statuses`);
                  });
                  $$renderer6.option({ value: "active" }, ($$renderer7) => {
                    $$renderer7.push(`Active`);
                  });
                  $$renderer6.option({ value: "inactive" }, ($$renderer7) => {
                    $$renderer7.push(`Inactive`);
                  });
                }
              );
              $$renderer5.push(`</label>`);
            }, mobileFilters = function($$renderer5) {
              $$renderer5.push(`<label class="space-y-1.5"><span class="text-xs font-medium text-muted-foreground">Status</span> `);
              $$renderer5.select(
                {
                  value: status,
                  class: "border-input bg-background min-h-11 w-full rounded-md border px-3 text-sm"
                },
                ($$renderer6) => {
                  $$renderer6.option({ value: "" }, ($$renderer7) => {
                    $$renderer7.push(`All statuses`);
                  });
                  $$renderer6.option({ value: "active" }, ($$renderer7) => {
                    $$renderer7.push(`Active`);
                  });
                  $$renderer6.option({ value: "inactive" }, ($$renderer7) => {
                    $$renderer7.push(`Inactive`);
                  });
                }
              );
              $$renderer5.push(`</label>`);
            };
            FilterToolbar($$renderer4, {
              searchPlaceholder: "Search arrangement names",
              searchLabel: "Search seating arrangements",
              get search() {
                return search;
              },
              set search($$value) {
                search = $$value;
                $$settled = false;
              },
              desktopFilters,
              mobileFilters,
              $$slots: { desktopFilters: true, mobileFilters: true }
            });
          }
          $$renderer4.push(`<!----></div> `);
          ActiveFilterChips($$renderer4, {
            chips: activeFilterChips(),
            onRemove: clearFilter,
            onClearAll: clearAllFilters
          });
          $$renderer4.push(`<!----> `);
          if (page_state === "denied") {
            $$renderer4.push("<!--[0-->");
            $$renderer4.push(`<div class="border-border bg-card rounded-xl border p-6 text-sm text-muted-foreground" role="status">You do not have permission to manage seating arrangements.</div>`);
          } else if (page_state === "loading") {
            $$renderer4.push("<!--[1-->");
            $$renderer4.push(`<div class="border-border bg-card animate-pulse rounded-xl border p-6" aria-label="Loading seating arrangements"><div class="bg-muted h-5 w-48 rounded"></div> <div class="bg-muted mt-4 h-12 rounded"></div> <div class="bg-muted mt-2 h-12 rounded"></div></div>`);
          } else if (page_state === "error") {
            $$renderer4.push("<!--[2-->");
            $$renderer4.push(`<div class="border-destructive/40 bg-destructive/10 text-destructive rounded-xl border p-6 text-sm" role="alert">Seating arrangements could not be loaded. Refresh the page and try again.</div>`);
          } else if (rows().length === 0) {
            $$renderer4.push("<!--[3-->");
            $$renderer4.push(`<div class="border-border bg-card rounded-xl border border-dashed px-6 py-14 text-center">`);
            Grid_3x3($$renderer4, { class: "text-muted-foreground mx-auto h-8 w-8" });
            $$renderer4.push(`<!----> <h2 class="mt-4 font-semibold">${escape_html(search || status ? "No matching arrangements" : "No seating arrangements yet")}</h2> <p class="text-muted-foreground mx-auto mt-1 max-w-md text-sm">${escape_html(search || status ? "Try a different search or status filter." : "Create the first reusable arrangement to define deterministic room seating.")}</p> `);
            if (!search && !status) {
              $$renderer4.push("<!--[0-->");
              Link($$renderer4, {
                href: "/setup/seating-arrangements/create",
                class: "mt-5 inline-flex",
                children: ($$renderer5) => {
                  Button($$renderer5, {
                    class: "min-h-11 gap-2",
                    children: ($$renderer6) => {
                      Plus($$renderer6, { class: "h-4 w-4" });
                      $$renderer6.push(`<!---->Create arrangement`);
                    },
                    $$slots: { default: true }
                  });
                },
                $$slots: { default: true }
              });
            } else {
              $$renderer4.push("<!--[-1-->");
            }
            $$renderer4.push(`<!--]--></div>`);
          } else {
            $$renderer4.push("<!--[-1-->");
            {
              let table = function($$renderer5) {
                if (Table) {
                  $$renderer5.push("<!--[-->");
                  Table($$renderer5, {
                    class: "min-w-[760px]",
                    children: ($$renderer6) => {
                      if (Table_header) {
                        $$renderer6.push("<!--[-->");
                        Table_header($$renderer6, {
                          children: ($$renderer7) => {
                            if (Table_row) {
                              $$renderer7.push("<!--[-->");
                              Table_row($$renderer7, {
                                children: ($$renderer8) => {
                                  if (Table_head) {
                                    $$renderer8.push("<!--[-->");
                                    Table_head($$renderer8, {
                                      children: ($$renderer9) => {
                                        $$renderer9.push(`<!---->Arrangement`);
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
                                        $$renderer9.push(`<!---->Desk pattern`);
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
                                        $$renderer9.push(`<!---->Availability`);
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
                                      class: "text-right",
                                      children: ($$renderer9) => {
                                        $$renderer9.push(`<!---->Action`);
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
                            $$renderer7.push(`<!--[-->`);
                            const each_array = ensure_array_like(rows());
                            for (let $$index = 0, $$length = each_array.length; $$index < $$length; $$index++) {
                              let arrangement = each_array[$$index];
                              if (Table_row) {
                                $$renderer7.push("<!--[-->");
                                Table_row($$renderer7, {
                                  class: !arrangement.is_active ? "text-muted-foreground/75" : "",
                                  children: ($$renderer8) => {
                                    if (Table_cell) {
                                      $$renderer8.push("<!--[-->");
                                      Table_cell($$renderer8, {
                                        children: ($$renderer9) => {
                                          $$renderer9.push(`<p class="font-medium text-foreground">${escape_html(arrangementLabel(arrangement))}</p> <p class="text-muted-foreground text-xs">Version ${escape_html(arrangement.lock_version ?? "—")}</p>`);
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
                                          $$renderer9.push(`<p>${escape_html(arrangement.rows ?? "—")} × ${escape_html(arrangement.columns ?? "—")} · ${escape_html(arrangement.desk_count ?? (arrangement.rows ?? 0) * (arrangement.columns ?? 0))} desks</p> <p class="text-muted-foreground text-xs">${escape_html(patternLabel(arrangement.booklet_pattern))}</p>`);
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
                                          $$renderer9.push(`<!---->${escape_html(availabilityLabel(arrangement))}`);
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
                                            variant: arrangement.is_active ? "default" : "secondary",
                                            children: ($$renderer10) => {
                                              $$renderer10.push(`<!---->${escape_html(arrangement.is_active ? "Active" : "Inactive")}`);
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
                                        class: "text-right",
                                        children: ($$renderer9) => {
                                          ActionDropdown($$renderer9, { items: actionItems(arrangement) });
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
                $$renderer5.push(`<ul class="grid gap-3" role="list"><!--[-->`);
                const each_array_1 = ensure_array_like(rows());
                for (let $$index_1 = 0, $$length = each_array_1.length; $$index_1 < $$length; $$index_1++) {
                  let arrangement = each_array_1[$$index_1];
                  $$renderer5.push(`<li${attr_class(`border-border bg-card rounded-xl border p-4 ${arrangement.is_active ? "" : "opacity-80"}`)}><div class="flex items-start justify-between gap-3"><div class="min-w-0"><h2 class="truncate font-semibold">${escape_html(arrangementLabel(arrangement))}</h2> <p class="text-muted-foreground mt-1 text-sm">${escape_html(arrangement.rows ?? "—")} × ${escape_html(arrangement.columns ?? "—")} · ${escape_html(arrangement.desk_count ?? (arrangement.rows ?? 0) * (arrangement.columns ?? 0))} desks</p></div> `);
                  Badge($$renderer5, {
                    variant: arrangement.is_active ? "default" : "secondary",
                    children: ($$renderer6) => {
                      $$renderer6.push(`<!---->${escape_html(arrangement.is_active ? "Active" : "Inactive")}`);
                    },
                    $$slots: { default: true }
                  });
                  $$renderer5.push(`<!----></div> <dl class="mt-4 grid grid-cols-2 gap-3 text-sm"><div><dt class="text-muted-foreground">Pattern</dt><dd class="font-medium">${escape_html(patternLabel(arrangement.booklet_pattern))}</dd></div> <div><dt class="text-muted-foreground">Availability</dt><dd class="font-medium">${escape_html(availabilityLabel(arrangement))}</dd></div></dl> <div class="mt-4 flex items-center justify-between gap-2">`);
                  Link($$renderer5, {
                    href: `/setup/seating-arrangements/${arrangement.id}/edit`,
                    children: ($$renderer6) => {
                      Button($$renderer6, {
                        variant: "outline",
                        class: "min-h-11 gap-2",
                        children: ($$renderer7) => {
                          Pencil($$renderer7, { class: "h-4 w-4" });
                          $$renderer7.push(`<!---->Edit`);
                        },
                        $$slots: { default: true }
                      });
                    },
                    $$slots: { default: true }
                  });
                  $$renderer5.push(`<!----> `);
                  ActionDropdown($$renderer5, { items: actionItems(arrangement) });
                  $$renderer5.push(`<!----></div></li>`);
                }
                $$renderer5.push(`<!--]--></ul>`);
              };
              SwitchableListView($$renderer4, {
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
            $$renderer4.push(`<!----> `);
            if (hasPagination()) {
              $$renderer4.push("<!--[0-->");
              SimplePagination($$renderer4, { data: arrangements });
            } else {
              $$renderer4.push("<!--[-1-->");
            }
            $$renderer4.push(`<!--]-->`);
          }
          $$renderer4.push(`<!--]--></div>`);
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
