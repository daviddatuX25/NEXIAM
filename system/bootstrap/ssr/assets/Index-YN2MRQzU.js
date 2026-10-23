import { g as sanitize_props, i as spread_props, j as slot, h as head, B as Button, e as escape_html, a as ensure_array_like, c as attr_class, A as clsx } from "../app.js";
import { S as StaffLayout } from "./StaffLayout-CR-MZopa.js";
import { P as PageHeaderBar } from "./PageHeaderBar-DJhT5vOR.js";
import { S as SwitchableListView } from "./SwitchableListView-DHKJpEKr.js";
import { S as SimplePagination } from "./SimplePagination-Bcq5R_Kb.js";
import { I as Input } from "./input-BUZAZKIK.js";
import { S as Select, a as Select_trigger, b as Select_content, c as Select_item } from "./select-trigger-rRjpYIEn.js";
import "clsx";
import { T as Table, a as Table_header, b as Table_row, c as Table_head, d as Table_body, e as Table_cell } from "./table-row-_ffWKrps.js";
import VoucherDialog from "./VoucherDialog-COLHI7dj.js";
import { L as Link } from "./Link-B7ks7rBK.js";
import { P as Plus } from "./plus-DjyPLC7A.js";
import { S as Search } from "./settings-C5xOKpV3.js";
import { I as Icon } from "./Icon-Dal7PqKK.js";
import { P as Pencil } from "./pencil-0tPPVae8.js";
import { T as Ticket_check } from "./ticket-check-j0koCZml.js";
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
import "./badge-D9Vgs7xh.js";
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
import "./roving-focus-group-BJDH9kUk.js";
import "./table-2-BLgeaPqt.js";
import "./layout-grid-CXJfSBqV.js";
import "./check-DK45Y9SJ.js";
import "./chevron-up-lhASJLRD.js";
import "./previous.svelte-Ch7Tz-Mv.js";
import "./arrays-CkOR4vx_.js";
import "qrcode";
import "./shield-check-CLSg-ako.js";
import "./copy-D9_YDhto.js";
function User_cog($$renderer, $$props) {
  const $$sanitized_props = sanitize_props($$props);
  const iconNode = [
    ["path", { "d": "M10 15H6a4 4 0 0 0-4 4v2" }],
    ["path", { "d": "m14.305 16.53.923-.382" }],
    ["path", { "d": "m15.228 13.852-.923-.383" }],
    ["path", { "d": "m16.852 12.228-.383-.923" }],
    ["path", { "d": "m16.852 17.772-.383.924" }],
    ["path", { "d": "m19.148 12.228.383-.923" }],
    ["path", { "d": "m19.53 18.696-.382-.924" }],
    ["path", { "d": "m20.772 13.852.924-.383" }],
    ["path", { "d": "m20.772 16.148.924.383" }],
    ["circle", { "cx": "18", "cy": "15", "r": "3" }],
    ["circle", { "cx": "9", "cy": "7", "r": "4" }]
  ];
  Icon($$renderer, spread_props([
    { name: "user-cog" },
    $$sanitized_props,
    {
      /**
       * @component @name UserCog
       * @description Lucide SVG icon component, renders SVG Element with children.
       *
       * @preview ![img](data:image/svg+xml;base64,PHN2ZyAgeG1sbnM9Imh0dHA6Ly93d3cudzMub3JnLzIwMDAvc3ZnIgogIHdpZHRoPSIyNCIKICBoZWlnaHQ9IjI0IgogIHZpZXdCb3g9IjAgMCAyNCAyNCIKICBmaWxsPSJub25lIgogIHN0cm9rZT0iIzAwMCIgc3R5bGU9ImJhY2tncm91bmQtY29sb3I6ICNmZmY7IGJvcmRlci1yYWRpdXM6IDJweCIKICBzdHJva2Utd2lkdGg9IjIiCiAgc3Ryb2tlLWxpbmVjYXA9InJvdW5kIgogIHN0cm9rZS1saW5lam9pbj0icm91bmQiCj4KICA8cGF0aCBkPSJNMTAgMTVINmE0IDQgMCAwIDAtNCA0djIiIC8+CiAgPHBhdGggZD0ibTE0LjMwNSAxNi41My45MjMtLjM4MiIgLz4KICA8cGF0aCBkPSJtMTUuMjI4IDEzLjg1Mi0uOTIzLS4zODMiIC8+CiAgPHBhdGggZD0ibTE2Ljg1MiAxMi4yMjgtLjM4My0uOTIzIiAvPgogIDxwYXRoIGQ9Im0xNi44NTIgMTcuNzcyLS4zODMuOTI0IiAvPgogIDxwYXRoIGQ9Im0xOS4xNDggMTIuMjI4LjM4My0uOTIzIiAvPgogIDxwYXRoIGQ9Im0xOS41MyAxOC42OTYtLjM4Mi0uOTI0IiAvPgogIDxwYXRoIGQ9Im0yMC43NzIgMTMuODUyLjkyNC0uMzgzIiAvPgogIDxwYXRoIGQ9Im0yMC43NzIgMTYuMTQ4LjkyNC4zODMiIC8+CiAgPGNpcmNsZSBjeD0iMTgiIGN5PSIxNSIgcj0iMyIgLz4KICA8Y2lyY2xlIGN4PSI5IiBjeT0iNyIgcj0iNCIgLz4KPC9zdmc+Cg==) - https://lucide.dev/icons/user-cog
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
    let {
      page_state = "ready",
      users,
      roles = [],
      filters = {},
      can_full_hr = false,
      can_manage_personnel = false,
      can_delegate_ta = false
    } = $$props;
    let viewMode = "responsive";
    let filterSearch = filters.search ?? "";
    let filterRole = filters.role ?? "";
    let busyTa = null;
    let voucherTarget = null;
    let voucherDialogOpen = false;
    let debounceTimer;
    function applyFilters() {
      router.get(
        "/staff/personnel",
        {
          search: filterSearch || void 0,
          role: filterRole || void 0,
          page: 1
        },
        { preserveState: true }
      );
    }
    function onSearchInput() {
      clearTimeout(debounceTimer);
      debounceTimer = setTimeout(applyFilters, 300);
    }
    function openVoucher(user) {
      voucherTarget = user;
      voucherDialogOpen = true;
    }
    function deactivate(id) {
      if (!confirm("Deactivate this staff account? They will not be able to sign in.")) return;
      router.post(`/staff/personnel/${id}/deactivate`);
    }
    function activate(id) {
      router.post(`/staff/personnel/${id}/activate`);
    }
    function grantTa(id) {
      if (!confirm("Grant full Test Administrator capabilities to this staff member?")) return;
      busyTa = id;
      router.post(`/staff/personnel/${id}/grant-ta`, {}, {
        onFinish: () => {
          busyTa = null;
        }
      });
    }
    function revokeTa(id) {
      if (!confirm("Revoke delegated Test Administrator capabilities? Role-helper base access remains.")) return;
      busyTa = id;
      router.post(`/staff/personnel/${id}/revoke-ta`, {}, {
        onFinish: () => {
          busyTa = null;
        }
      });
    }
    function taLabel(status) {
      if (status === "base_role") return "Base TA";
      return "Standard";
    }
    function initials(name) {
      const parts = String(name ?? "").trim().split(/\s+/).filter(Boolean);
      if (parts.length === 0) return "ST";
      if (parts.length === 1) return parts[0].slice(0, 2).toUpperCase();
      return (parts[0][0] + parts[parts.length - 1][0]).toUpperCase();
    }
    const breadcrumbs = [{ label: "Staff Directory" }];
    let $$settled = true;
    let $$inner_renderer;
    function $$render_inner($$renderer3) {
      head("f2s65r", $$renderer3, ($$renderer4) => {
        $$renderer4.title(($$renderer5) => {
          $$renderer5.push(`<title>Staff Directory</title>`);
        });
      });
      StaffLayout($$renderer3, {
        breadcrumbs,
        children: ($$renderer4) => {
          $$renderer4.push(`<div class="space-y-6 min-w-0 p-6"><div class="flex flex-wrap items-start justify-between gap-3">`);
          PageHeaderBar($$renderer4, {
            title: "Staff Directory",
            description: can_manage_personnel ? "Manage staff accounts across offices, assign Test Administrator duties, and configure personnel access." : "Directory of institutional staff accounts and assigned office responsibilities."
          });
          $$renderer4.push(`<!----> `);
          if (can_manage_personnel) {
            $$renderer4.push("<!--[0-->");
            Link($$renderer4, {
              href: "/staff/personnel/create",
              children: ($$renderer5) => {
                Button($$renderer5, {
                  class: "min-h-11 gap-2",
                  children: ($$renderer6) => {
                    Plus($$renderer6, { class: "h-4 w-4" });
                    $$renderer6.push(`<!----> Create staff`);
                  },
                  $$slots: { default: true }
                });
              },
              $$slots: { default: true }
            });
          } else {
            $$renderer4.push("<!--[-1-->");
          }
          $$renderer4.push(`<!--]--></div> `);
          if (page_state === "empty" && !filterSearch && !filterRole) {
            $$renderer4.push("<!--[0-->");
            $$renderer4.push(`<p class="text-sm text-muted-foreground">No staff accounts yet.</p>`);
          } else {
            $$renderer4.push("<!--[-1-->");
          }
          $$renderer4.push(`<!--]--> <div class="flex flex-wrap items-center gap-3"><div class="relative">`);
          Search($$renderer4, {
            class: "pointer-events-none absolute left-2.5 top-1/2 h-4 w-4 -translate-y-1/2 text-muted-foreground"
          });
          $$renderer4.push(`<!----> `);
          Input($$renderer4, {
            type: "search",
            placeholder: "Search staff...",
            class: "h-10 min-w-[180px] pl-8",
            oninput: onSearchInput,
            get value() {
              return filterSearch;
            },
            set value($$value) {
              filterSearch = $$value;
              $$settled = false;
            }
          });
          $$renderer4.push(`<!----></div> `);
          if (Select) {
            $$renderer4.push("<!--[-->");
            Select($$renderer4, {
              type: "single",
              value: filterRole,
              onValueChange: (v) => {
                filterRole = v ?? "";
                applyFilters();
              },
              children: ($$renderer5) => {
                if (Select_trigger) {
                  $$renderer5.push("<!--[-->");
                  Select_trigger($$renderer5, {
                    class: "min-h-10 w-[180px]",
                    children: ($$renderer6) => {
                      if (filterRole) {
                        $$renderer6.push("<!--[0-->");
                        $$renderer6.push(`${escape_html(roles.find((r) => r.name === filterRole)?.display_name || "All roles")}`);
                      } else {
                        $$renderer6.push("<!--[-1-->");
                        $$renderer6.push(`<span class="text-muted-foreground">All roles</span>`);
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
                if (Select_content) {
                  $$renderer5.push("<!--[-->");
                  Select_content($$renderer5, {
                    children: ($$renderer6) => {
                      if (Select_item) {
                        $$renderer6.push("<!--[-->");
                        Select_item($$renderer6, {
                          value: "",
                          label: "All roles",
                          children: ($$renderer7) => {
                            $$renderer7.push(`<!---->All roles`);
                          },
                          $$slots: { default: true }
                        });
                        $$renderer6.push("<!--]-->");
                      } else {
                        $$renderer6.push("<!--[!-->");
                        $$renderer6.push("<!--]-->");
                      }
                      $$renderer6.push(` <!--[-->`);
                      const each_array = ensure_array_like(roles);
                      for (let $$index = 0, $$length = each_array.length; $$index < $$length; $$index++) {
                        let r = each_array[$$index];
                        if (Select_item) {
                          $$renderer6.push("<!--[-->");
                          Select_item($$renderer6, {
                            value: r.name,
                            label: r.display_name,
                            children: ($$renderer7) => {
                              $$renderer7.push(`<!---->${escape_html(r.display_name)}`);
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
          $$renderer4.push(`</div> `);
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
                                    class: "px-4 py-3",
                                    children: ($$renderer9) => {
                                      $$renderer9.push(`<!---->Name`);
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
                                    class: "px-4 py-3",
                                    children: ($$renderer9) => {
                                      $$renderer9.push(`<!---->Email`);
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
                                    class: "px-4 py-3",
                                    children: ($$renderer9) => {
                                      $$renderer9.push(`<!---->Office`);
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
                                    class: "px-4 py-3",
                                    children: ($$renderer9) => {
                                      $$renderer9.push(`<!---->Roles`);
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
                                    class: "px-4 py-3",
                                    children: ($$renderer9) => {
                                      $$renderer9.push(`<!---->Test Administrator`);
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
                                    class: "px-4 py-3",
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
                                    class: "px-4 py-3 text-center",
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
                          $$renderer7.push(`<!--[-->`);
                          const each_array_1 = ensure_array_like(users.data);
                          for (let $$index_2 = 0, $$length = each_array_1.length; $$index_2 < $$length; $$index_2++) {
                            let user = each_array_1[$$index_2];
                            if (Table_row) {
                              $$renderer7.push("<!--[-->");
                              Table_row($$renderer7, {
                                children: ($$renderer8) => {
                                  if (Table_cell) {
                                    $$renderer8.push("<!--[-->");
                                    Table_cell($$renderer8, {
                                      class: "px-4 py-3",
                                      children: ($$renderer9) => {
                                        $$renderer9.push(`<!---->${escape_html(user.name)}`);
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
                                      class: "px-4 py-3",
                                      children: ($$renderer9) => {
                                        $$renderer9.push(`<!---->${escape_html(user.email)}`);
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
                                      class: "px-4 py-3 capitalize",
                                      children: ($$renderer9) => {
                                        $$renderer9.push(`<!---->${escape_html(user.office)}`);
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
                                      class: "px-4 py-3",
                                      children: ($$renderer9) => {
                                        $$renderer9.push(`<span class="inline-flex flex-wrap gap-1"><!--[-->`);
                                        const each_array_2 = ensure_array_like(user.roles ?? []);
                                        for (let $$index_1 = 0, $$length2 = each_array_2.length; $$index_1 < $$length2; $$index_1++) {
                                          let r = each_array_2[$$index_1];
                                          $$renderer9.push(`<span class="rounded-full bg-primary/10 px-2 py-0.5 text-xs font-medium text-primary">${escape_html(r.display_name)}</span>`);
                                        }
                                        $$renderer9.push(`<!--]--></span>`);
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
                                      class: "px-4 py-3",
                                      children: ($$renderer9) => {
                                        $$renderer9.push(`<span class="inline-flex items-center gap-2"><span class="inline-flex items-center gap-1.5">`);
                                        if (user.ta_status === "inherent") {
                                          $$renderer9.push("<!--[0-->");
                                          User_cog($$renderer9, { class: "h-3.5 w-3.5 text-primary" });
                                        } else {
                                          $$renderer9.push("<!--[-1-->");
                                        }
                                        $$renderer9.push(`<!--]--> <span${attr_class(clsx(user.ta_status === "none" ? "text-muted-foreground" : "font-medium"))}>${escape_html(taLabel(user.ta_status))}</span></span> `);
                                        if (can_delegate_ta && user.can_delegate_ta) {
                                          $$renderer9.push("<!--[0-->");
                                          if (user.ta_status === "delegated") {
                                            $$renderer9.push("<!--[0-->");
                                            Button($$renderer9, {
                                              variant: "outline",
                                              size: "sm",
                                              class: "h-8 px-2 text-xs",
                                              disabled: busyTa === user.id,
                                              onclick: () => revokeTa(user.id),
                                              children: ($$renderer10) => {
                                                $$renderer10.push(`<!---->Revoke TA`);
                                              },
                                              $$slots: { default: true }
                                            });
                                          } else {
                                            $$renderer9.push("<!--[-1-->");
                                            Button($$renderer9, {
                                              variant: "ghost",
                                              size: "sm",
                                              class: "h-8 px-2 text-xs",
                                              disabled: busyTa === user.id,
                                              onclick: () => grantTa(user.id),
                                              children: ($$renderer10) => {
                                                $$renderer10.push(`<!---->Grant TA`);
                                              },
                                              $$slots: { default: true }
                                            });
                                          }
                                          $$renderer9.push(`<!--]-->`);
                                        } else {
                                          $$renderer9.push("<!--[-1-->");
                                        }
                                        $$renderer9.push(`<!--]--></span>`);
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
                                      class: "px-4 py-3 capitalize",
                                      children: ($$renderer9) => {
                                        $$renderer9.push(`<!---->${escape_html(user.status)}`);
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
                                      class: "px-4 py-3",
                                      children: ($$renderer9) => {
                                        if (user.is_self) {
                                          $$renderer9.push("<!--[0-->");
                                          $$renderer9.push(`<div class="flex justify-center gap-2">`);
                                          Button($$renderer9, {
                                            variant: "ghost",
                                            size: "sm",
                                            class: "h-8 px-2 text-xs",
                                            disabled: true,
                                            title: "Manage your own account from your profile page.",
                                            children: ($$renderer10) => {
                                              Pencil($$renderer10, { class: "mr-1 h-3.5 w-3.5" });
                                              $$renderer10.push(`<!----> Edit`);
                                            },
                                            $$slots: { default: true }
                                          });
                                          $$renderer9.push(`<!----> `);
                                          Button($$renderer9, {
                                            variant: "ghost",
                                            size: "sm",
                                            class: "h-8 px-2 text-xs text-destructive",
                                            disabled: true,
                                            title: "Deactivate your own account from your profile page.",
                                            children: ($$renderer10) => {
                                              $$renderer10.push(`<!---->Deactivate`);
                                            },
                                            $$slots: { default: true }
                                          });
                                          $$renderer9.push(`<!----> `);
                                          Link($$renderer9, {
                                            href: "/staff/profile",
                                            children: ($$renderer10) => {
                                              Button($$renderer10, {
                                                variant: "outline",
                                                size: "sm",
                                                class: "h-8 px-2 text-xs",
                                                children: ($$renderer11) => {
                                                  $$renderer11.push(`<!---->Manage Profile`);
                                                },
                                                $$slots: { default: true }
                                              });
                                            },
                                            $$slots: { default: true }
                                          });
                                          $$renderer9.push(`<!----></div>`);
                                        } else if (can_manage_personnel && user.can_manage_user) {
                                          $$renderer9.push("<!--[1-->");
                                          $$renderer9.push(`<div class="flex justify-center gap-2">`);
                                          Link($$renderer9, {
                                            href: `/staff/personnel/${user.id}/edit`,
                                            children: ($$renderer10) => {
                                              Button($$renderer10, {
                                                variant: "ghost",
                                                size: "sm",
                                                class: "h-8 px-2 text-xs",
                                                children: ($$renderer11) => {
                                                  Pencil($$renderer11, { class: "mr-1 h-3.5 w-3.5" });
                                                  $$renderer11.push(`<!----> Edit`);
                                                },
                                                $$slots: { default: true }
                                              });
                                            },
                                            $$slots: { default: true }
                                          });
                                          $$renderer9.push(`<!----> `);
                                          if (user.can_issue_voucher) {
                                            $$renderer9.push("<!--[0-->");
                                            Button($$renderer9, {
                                              type: "button",
                                              variant: "outline",
                                              size: "sm",
                                              class: "h-8 px-2 text-xs",
                                              onclick: () => openVoucher(user),
                                              children: ($$renderer10) => {
                                                Ticket_check($$renderer10, { class: "mr-1 size-3.5" });
                                                $$renderer10.push(`<!----> Voucher`);
                                              },
                                              $$slots: { default: true }
                                            });
                                          } else {
                                            $$renderer9.push("<!--[-1-->");
                                          }
                                          $$renderer9.push(`<!--]--> `);
                                          if (user.status === "active") {
                                            $$renderer9.push("<!--[0-->");
                                            Button($$renderer9, {
                                              variant: "ghost",
                                              size: "sm",
                                              class: "h-8 px-2 text-xs text-destructive",
                                              onclick: () => deactivate(user.id),
                                              children: ($$renderer10) => {
                                                $$renderer10.push(`<!---->Deactivate`);
                                              },
                                              $$slots: { default: true }
                                            });
                                          } else {
                                            $$renderer9.push("<!--[-1-->");
                                            Button($$renderer9, {
                                              variant: "ghost",
                                              size: "sm",
                                              class: "h-8 px-2 text-xs",
                                              onclick: () => activate(user.id),
                                              children: ($$renderer10) => {
                                                $$renderer10.push(`<!---->Activate`);
                                              },
                                              $$slots: { default: true }
                                            });
                                          }
                                          $$renderer9.push(`<!--]--></div>`);
                                        } else {
                                          $$renderer9.push("<!--[-1-->");
                                        }
                                        $$renderer9.push(`<!--]-->`);
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
              $$renderer5.push(`<ul class="grid gap-5 sm:grid-cols-2 xl:grid-cols-3" role="list"><!--[-->`);
              const each_array_3 = ensure_array_like(users.data ?? []);
              for (let $$index_3 = 0, $$length = each_array_3.length; $$index_3 < $$length; $$index_3++) {
                let user = each_array_3[$$index_3];
                $$renderer5.push(`<li class="craft-card-depth p-5 flex flex-col justify-between gap-3.5 group"><div class="space-y-3"><div class="flex items-start justify-between gap-2.5 pb-2.5 border-b border-border/50"><div class="flex items-center gap-2.5 min-w-0"><div class="flex size-9 shrink-0 items-center justify-center rounded-full bg-primary/10 text-primary border border-primary/20 text-xs font-bold font-sans">${escape_html(initials(user.name))}</div> <div class="min-w-0"><p class="truncate font-bold font-sans text-base text-foreground group-hover:text-primary transition-colors">${escape_html(user.name)}</p> <p class="text-xs text-muted-foreground font-sans truncate">${escape_html(user.email)}</p></div></div> <span${attr_class(`inline-flex items-center px-2 py-0.5 rounded-full text-[10px] font-bold font-sans shrink-0 ${user.status === "active" ? "bg-emerald-500/10 text-emerald-600 dark:text-emerald-400 border border-emerald-500/20" : "bg-rose-500/10 text-rose-600 border border-rose-500/20"}`)}>${escape_html(user.status === "active" ? "ACTIVE" : "INACTIVE")}</span></div> <div class="recessed-well p-3 rounded-lg space-y-1.5 text-xs font-sans"><div class="flex items-center justify-between"><span class="text-muted-foreground">Office Assignment:</span> <strong class="text-foreground capitalize">${escape_html(user.office ?? "General Staff")}</strong></div> <div class="flex items-center justify-between text-[11px] text-muted-foreground"><span>Test Administration:</span> <span${attr_class(`font-medium ${user.ta_status === "delegated" ? "text-primary font-bold" : "text-foreground"}`)}>${escape_html(taLabel(user.ta_status))}</span></div> `);
                if ((user.roles?.length ?? 0) > 0) {
                  $$renderer5.push("<!--[0-->");
                  $$renderer5.push(`<div class="flex items-center justify-between text-[11px] text-muted-foreground pt-1 border-t border-border/50"><span>Assigned Roles:</span> <span class="font-medium text-foreground">${escape_html(user.roles.join(" · "))}</span></div>`);
                } else {
                  $$renderer5.push("<!--[-1-->");
                }
                $$renderer5.push(`<!--]--></div></div> <div class="flex flex-wrap items-center gap-2 pt-2 border-t border-border/40">`);
                if (user.is_self) {
                  $$renderer5.push("<!--[0-->");
                  Link($$renderer5, {
                    href: "/staff/profile",
                    class: "flex-1",
                    children: ($$renderer6) => {
                      Button($$renderer6, {
                        variant: "outline",
                        size: "sm",
                        class: "min-h-9 w-full font-semibold font-sans",
                        children: ($$renderer7) => {
                          $$renderer7.push(`<!---->Manage Profile`);
                        },
                        $$slots: { default: true }
                      });
                    },
                    $$slots: { default: true }
                  });
                } else if (can_manage_personnel && user.can_manage_user) {
                  $$renderer5.push("<!--[1-->");
                  Link($$renderer5, {
                    href: `/staff/personnel/${user.id}/edit`,
                    class: "flex-1",
                    children: ($$renderer6) => {
                      Button($$renderer6, {
                        variant: "outline",
                        size: "sm",
                        class: "min-h-9 w-full font-semibold font-sans",
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
                  if (user.can_issue_voucher) {
                    $$renderer5.push("<!--[0-->");
                    Button($$renderer5, {
                      variant: "outline",
                      size: "sm",
                      class: "min-h-9 text-xs",
                      onclick: () => openVoucher(user),
                      children: ($$renderer6) => {
                        Ticket_check($$renderer6, { class: "size-3.5" });
                        $$renderer6.push(`<!----> Voucher`);
                      },
                      $$slots: { default: true }
                    });
                  } else {
                    $$renderer5.push("<!--[-1-->");
                  }
                  $$renderer5.push(`<!--]--> `);
                  if (user.status === "active") {
                    $$renderer5.push("<!--[0-->");
                    Button($$renderer5, {
                      variant: "ghost",
                      size: "sm",
                      class: "min-h-9 text-xs text-destructive hover:bg-destructive/10",
                      onclick: () => deactivate(user.id),
                      children: ($$renderer6) => {
                        $$renderer6.push(`<!---->Deactivate`);
                      },
                      $$slots: { default: true }
                    });
                  } else {
                    $$renderer5.push("<!--[-1-->");
                    Button($$renderer5, {
                      variant: "ghost",
                      size: "sm",
                      class: "min-h-9 text-xs text-emerald-600 dark:text-emerald-400 hover:bg-emerald-500/10",
                      onclick: () => activate(user.id),
                      children: ($$renderer6) => {
                        $$renderer6.push(`<!---->Activate`);
                      },
                      $$slots: { default: true }
                    });
                  }
                  $$renderer5.push(`<!--]-->`);
                } else {
                  $$renderer5.push("<!--[-1-->");
                }
                $$renderer5.push(`<!--]--> `);
                if (can_delegate_ta && user.can_delegate_ta) {
                  $$renderer5.push("<!--[0-->");
                  if (user.ta_status === "delegated") {
                    $$renderer5.push("<!--[0-->");
                    Button($$renderer5, {
                      variant: "outline",
                      size: "sm",
                      class: "min-h-9 text-xs",
                      onclick: () => revokeTa(user.id),
                      children: ($$renderer6) => {
                        $$renderer6.push(`<!---->Revoke TA`);
                      },
                      $$slots: { default: true }
                    });
                  } else {
                    $$renderer5.push("<!--[-1-->");
                    Button($$renderer5, {
                      variant: "outline",
                      size: "sm",
                      class: "min-h-9 text-xs font-bold text-primary border-primary/30 hover:bg-primary/10",
                      onclick: () => grantTa(user.id),
                      children: ($$renderer6) => {
                        $$renderer6.push(`<!---->Grant TA`);
                      },
                      $$slots: { default: true }
                    });
                  }
                  $$renderer5.push(`<!--]-->`);
                } else {
                  $$renderer5.push("<!--[-1-->");
                }
                $$renderer5.push(`<!--]--></div></li>`);
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
          SimplePagination($$renderer4, { data: users });
          $$renderer4.push(`<!----> `);
          VoucherDialog($$renderer4, {
            open: voucherDialogOpen,
            user: voucherTarget,
            onOpenChange: (value) => voucherDialogOpen = value
          });
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
