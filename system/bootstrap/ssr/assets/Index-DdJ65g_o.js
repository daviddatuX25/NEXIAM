import { o as onDestroy, h as head, a as ensure_array_like, c as attr_class, b as attr, e as escape_html, B as Button, d as derived, v as error, q as success, n as newIdempotencyKey } from "../app.js";
import { S as StaffLayout } from "./StaffLayout-CR-MZopa.js";
import { P as PageHeaderBar } from "./PageHeaderBar-DJhT5vOR.js";
import { H as HeaderActions } from "./HeaderActions-Dxxt_yha.js";
import { F as FilterToolbar } from "./FilterToolbar-Cl8Y1Mdp.js";
import { A as ActiveFilterChips } from "./ActiveFilterChips-BQD2-ACV.js";
import { S as SwitchableListView } from "./SwitchableListView-DHKJpEKr.js";
import { O as OperationalCard } from "./OperationalCard-D2Z4nRzj.js";
import { A as ActionDropdown } from "./ActionDropdown-DmJUv-tL.js";
import { S as SimplePagination } from "./SimplePagination-Bcq5R_Kb.js";
import { B as Badge } from "./badge-D9Vgs7xh.js";
import { T as Table, a as Table_header, b as Table_row, c as Table_head, d as Table_body, e as Table_cell } from "./table-row-_ffWKrps.js";
import { D as Dialog, a as Dialog_content, b as Dialog_header, c as Dialog_title, d as Dialog_description } from "./dialog-description-D12L0QA9.js";
import { D as Dialog_footer } from "./dialog-footer-kNnwKT2h.js";
import { L as Link } from "./Link-B7ks7rBK.js";
import { E as External_link } from "./external-link-DmB8ndP3.js";
import { P as Plus } from "./plus-DjyPLC7A.js";
import { router } from "@inertiajs/core";
import "axios";
import "clsx";
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
import "./roving-focus-group-BJDH9kUk.js";
import "./table-2-BLgeaPqt.js";
import "./layout-grid-CXJfSBqV.js";
function Index($$renderer, $$props) {
  $$renderer.component(($$renderer2) => {
    let {
      announcements = { data: [], current_page: 1, last_page: 1, total: 0 },
      filters = { status: "all", search: "", placement: "all" },
      status_counts = {},
      capabilities = { manage: false, publish: false, campus: false },
      publishers = []
    } = $$props;
    const statuses = [
      { value: "all", label: "All" },
      { value: "published", label: "Published" },
      { value: "draft", label: "Draft" },
      { value: "scheduled", label: "Scheduled" },
      { value: "withdrawn", label: "Withdrawn" },
      { value: "expired", label: "Expired" }
    ];
    let status = filters.status ?? "all";
    let filterSearch = filters.search ?? "";
    let placement = filters.placement ?? "all";
    let viewMode = "responsive";
    let filterTimer;
    let actionListPage = 1;
    let withdrawOpen = false;
    let withdrawTarget = null;
    let withdrawReason = "";
    let withdrawProcessing = false;
    let withdrawError = "";
    let activeMutation = null;
    let actionError = "";
    const commandRequestIds = /* @__PURE__ */ Object.create(null);
    const rows = derived(() => announcements?.data ?? []);
    const statusCounts = derived(() => ({
      all: status_counts.all ?? announcements.total ?? 0,
      published: status_counts.published ?? 0,
      draft: status_counts.draft ?? 0,
      scheduled: status_counts.scheduled ?? 0,
      withdrawn: status_counts.withdrawn ?? 0,
      expired: status_counts.expired ?? 0
    }));
    const activeFilterChips = derived(() => [
      ...filterSearch.trim() ? [{ id: "search", label: `Search: ${filterSearch.trim()}` }] : [],
      ...status !== "all" ? [
        {
          id: "status",
          label: statuses.find((item) => item.value === status)?.label ?? status
        }
      ] : [],
      ...placement !== "all" ? [
        {
          id: "placement",
          label: placement === "program" ? "Program placement" : "General placement"
        }
      ] : []
    ]);
    const hasActiveFilters = derived(() => activeFilterChips().length > 0);
    onDestroy(() => clearTimeout(filterTimer));
    function applyFilters() {
      clearTimeout(filterTimer);
      actionListPage = 1;
      router.get(
        "/communications",
        {
          search: filterSearch.trim() || void 0,
          status: status === "all" ? void 0 : status,
          placement: placement === "all" ? void 0 : placement,
          page: 1
        },
        { preserveState: true, preserveScroll: true, replace: true }
      );
    }
    function clearFilter(id) {
      if (id === "search") filterSearch = "";
      if (id === "status") status = "all";
      if (id === "placement") placement = "all";
      applyFilters();
    }
    function clearAllFilters() {
      filterSearch = "";
      status = "all";
      placement = "all";
      applyFilters();
    }
    function refreshFilteredList() {
      router.get(
        "/communications",
        {
          search: filterSearch.trim() || void 0,
          status: status === "all" ? void 0 : status,
          placement: placement === "all" ? void 0 : placement,
          page: actionListPage
        },
        {
          only: ["announcements", "filters"],
          preserveState: true,
          preserveScroll: true,
          replace: true
        }
      );
    }
    function requestId(key) {
      return commandRequestIds[key] ??= newIdempotencyKey();
    }
    function publish(row) {
      if (activeMutation) return;
      const key = `${row.id}:publish:${row.revision}`;
      actionError = "";
      activeMutation = row.id;
      actionListPage = announcements.current_page ?? 1;
      router.post(`/communications/${row.id}/publish`, { request_id: requestId(key), expected_revision: row.revision }, {
        preserveScroll: true,
        onSuccess: () => {
          actionError = "";
          delete commandRequestIds[key];
          success("Announcement published.");
        },
        onError: (errors) => {
          actionError = errors?.expected_revision || errors?.request_id || errors?.message || "The announcement could not be published. Review the current status and retry.";
          error(actionError);
        },
        onFinish: () => {
          activeMutation = null;
          refreshFilteredList();
        }
      });
    }
    function openWithdraw(row) {
      actionListPage = announcements.current_page ?? 1;
      withdrawTarget = row;
      withdrawReason = "";
      withdrawError = "";
      withdrawOpen = true;
    }
    function closeWithdraw() {
      withdrawOpen = false;
      withdrawTarget = null;
      withdrawReason = "";
      withdrawError = "";
    }
    function statusLabel(row) {
      const effectiveStatus = row.effective_status ?? row.status;
      return statuses.find((option) => option.value === effectiveStatus)?.label ?? effectiveStatus;
    }
    function placementLabel(row) {
      const placement2 = row.placement ?? (row.program_code ? "program" : "general");
      if (placement2 === "program" && row.program_code) {
        return `Program: ${row.program_code}`;
      }
      return placement2 === "general" ? "General" : placement2;
    }
    function actionItems(row) {
      return [
        ...row.can_edit || row.can_view ? [{ label: "Edit", href: `/communications/${row.id}/edit` }] : [],
        ...row.effective_status === "published" ? [
          {
            label: "View live",
            href: `/announcements/${row.id}`,
            external: true
          }
        ] : [],
        ...row.can_publish && row.status === "draft" ? [
          {
            label: "Publish",
            onclick: () => publish(row),
            disabled: activeMutation !== null
          }
        ] : [],
        ...row.can_withdraw ? [
          {
            label: "Withdraw",
            onclick: () => openWithdraw(row),
            danger: true,
            disabled: activeMutation !== null
          }
        ] : []
      ];
    }
    function dateLabel(value) {
      if (!value) return "—";
      const date = new Date(value);
      return Number.isNaN(date.getTime()) ? value : date.toLocaleString();
    }
    const breadcrumbs = [
      { label: "Setup", href: "/setup" },
      { label: "Announcements" }
    ];
    let $$settled = true;
    let $$inner_renderer;
    function $$render_inner($$renderer3) {
      head("c1nfc7", $$renderer3, ($$renderer4) => {
        $$renderer4.title(($$renderer5) => {
          $$renderer5.push(`<title>Announcements</title>`);
        });
      });
      StaffLayout($$renderer3, {
        breadcrumbs,
        children: ($$renderer4) => {
          function rowActions($$renderer5, row) {
            ActionDropdown($$renderer5, { items: actionItems(row) });
          }
          $$renderer4.push(`<div class="mx-auto grid w-full max-w-7xl gap-6">`);
          {
            let actions = function($$renderer5) {
              {
                let primaryAction = function($$renderer6) {
                  if (capabilities.manage && publishers.length > 0) {
                    $$renderer6.push("<!--[0-->");
                    Button($$renderer6, {
                      href: "/communications/create",
                      variant: "default",
                      class: "min-h-[44px]",
                      children: ($$renderer7) => {
                        Plus($$renderer7, { class: "size-4" });
                        $$renderer7.push(`<!----> New announcement`);
                      },
                      $$slots: { default: true }
                    });
                  } else {
                    $$renderer6.push("<!--[-1-->");
                  }
                  $$renderer6.push(`<!--]-->`);
                }, secondaryActions = function($$renderer6) {
                  Button($$renderer6, {
                    href: "/announcements",
                    variant: "ghost",
                    class: "min-h-[44px]",
                    children: ($$renderer7) => {
                      External_link($$renderer7, { class: "size-4" });
                      $$renderer7.push(`<!----> View public board ↗`);
                    },
                    $$slots: { default: true }
                  });
                };
                HeaderActions($$renderer5, {
                  primaryAction,
                  secondaryActions
                });
              }
            };
            PageHeaderBar($$renderer4, {
              title: "Announcements",
              description: "Create and publish updates for your authorized office or academic program.",
              actions
            });
          }
          $$renderer4.push(`<!----> <section class="border-border bg-card/70 min-w-0 rounded-lg border p-4 sm:p-5" aria-label="Announcement management list">`);
          if (actionError) {
            $$renderer4.push("<!--[0-->");
            $$renderer4.push(`<p class="border-destructive/30 bg-destructive/5 text-foreground mb-4 rounded-md border p-3 text-sm" role="alert">${escape_html(actionError)}</p>`);
          } else {
            $$renderer4.push("<!--[-1-->");
          }
          $$renderer4.push(`<!--]--> <div class="mb-4 flex flex-wrap gap-2" role="group" aria-label="Filter announcements by status"><!--[-->`);
          const each_array = ensure_array_like(statuses);
          for (let $$index = 0, $$length = each_array.length; $$index < $$length; $$index++) {
            let option = each_array[$$index];
            $$renderer4.push(`<button type="button"${attr_class(`border-border inline-flex min-h-10 items-center gap-2 rounded-full border px-3 text-sm font-medium transition-colors ${status === option.value ? "bg-primary text-primary-foreground border-primary" : "bg-background text-foreground hover:bg-muted"}`)}${attr("aria-pressed", status === option.value)}>${escape_html(option.label)} <span${attr_class(`rounded-full px-1.5 py-0.5 text-xs ${status === option.value ? "bg-primary-foreground/15" : "bg-muted"}`)}>${escape_html(statusCounts()[option.value])}</span></button>`);
          }
          $$renderer4.push(`<!--]--></div> <div>`);
          {
            let desktopFilters = function($$renderer5) {
              $$renderer5.push(`<label class="grid gap-1 text-sm"><span class="text-muted-foreground text-xs font-medium">Placement / program</span> `);
              $$renderer5.select(
                {
                  value: placement,
                  class: "border-input bg-background min-h-10 rounded-md border px-3 text-sm"
                },
                ($$renderer6) => {
                  $$renderer6.option({ value: "all" }, ($$renderer7) => {
                    $$renderer7.push(`All placements`);
                  });
                  $$renderer6.option({ value: "general" }, ($$renderer7) => {
                    $$renderer7.push(`General`);
                  });
                  $$renderer6.option({ value: "program" }, ($$renderer7) => {
                    $$renderer7.push(`Program`);
                  });
                }
              );
              $$renderer5.push(`</label>`);
            }, mobileFilters = function($$renderer5) {
              $$renderer5.push(`<label class="grid gap-1 text-sm"><span class="text-muted-foreground text-xs font-medium">Placement / program</span> `);
              $$renderer5.select(
                {
                  value: placement,
                  class: "border-input bg-background min-h-11 w-full rounded-md border px-3 text-sm"
                },
                ($$renderer6) => {
                  $$renderer6.option({ value: "all" }, ($$renderer7) => {
                    $$renderer7.push(`All placements`);
                  });
                  $$renderer6.option({ value: "general" }, ($$renderer7) => {
                    $$renderer7.push(`General`);
                  });
                  $$renderer6.option({ value: "program" }, ($$renderer7) => {
                    $$renderer7.push(`Program`);
                  });
                }
              );
              $$renderer5.push(`</label>`);
            };
            FilterToolbar($$renderer4, {
              searchLabel: "Search announcements",
              searchPlaceholder: "Search title, issuer, summary or program",
              get search() {
                return filterSearch;
              },
              set search($$value) {
                filterSearch = $$value;
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
          $$renderer4.push(`<!----> <p class="text-muted-foreground mt-3 text-sm" aria-live="polite">${escape_html(announcements.total ?? 0)} announcement${escape_html(announcements.total === 1 ? "" : "s")}</p> `);
          if (rows().length === 0) {
            $$renderer4.push("<!--[0-->");
            $$renderer4.push(`<div class="border-border mt-5 rounded-lg border border-dashed px-5 py-8 text-center" role="status"><h2 class="text-foreground font-semibold">${escape_html(announcements.total > 0 ? "No announcements on this page." : hasActiveFilters() ? "No announcements match these filters in your authorized scope." : "No announcements in this authorized scope yet.")}</h2> <p class="text-muted-foreground mx-auto mt-2 max-w-[60ch] text-sm leading-6">${escape_html(announcements.total > 0 ? "Return to the first page to continue browsing." : hasActiveFilters() ? "Change or clear a filter to see other announcements in your authorized scope." : "Create a draft for one of your authorized office or program sources.")}</p> `);
            if (announcements.total > 0) {
              $$renderer4.push("<!--[0-->");
              Button($$renderer4, {
                type: "button",
                variant: "outline",
                class: "mt-4 min-h-11",
                onclick: applyFilters,
                children: ($$renderer5) => {
                  $$renderer5.push(`<!---->Return to first page`);
                },
                $$slots: { default: true }
              });
            } else if (hasActiveFilters()) {
              $$renderer4.push("<!--[1-->");
              Button($$renderer4, {
                type: "button",
                variant: "outline",
                class: "mt-4 min-h-11",
                onclick: clearAllFilters,
                children: ($$renderer5) => {
                  $$renderer5.push(`<!---->Clear filters`);
                },
                $$slots: { default: true }
              });
            } else if (capabilities.manage && publishers.length > 0) {
              $$renderer4.push("<!--[2-->");
              Button($$renderer4, {
                href: "/communications/create",
                class: "mt-4 min-h-11",
                children: ($$renderer5) => {
                  $$renderer5.push(`<!---->Create announcement`);
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
                $$renderer5.push(`<div class="overflow-hidden rounded-xl border border-border/80 bg-card/60 shadow-xs backdrop-blur-md">`);
                if (Table) {
                  $$renderer5.push("<!--[-->");
                  Table($$renderer5, {
                    class: "w-full min-w-[960px] text-sm",
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
                                        $$renderer9.push(`<!---->Announcement`);
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
                                        $$renderer9.push(`<!---->Target`);
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
                                        $$renderer9.push(`<!---->Effective status`);
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
                                        $$renderer9.push(`<!---->Schedule`);
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
                            const each_array_1 = ensure_array_like(rows());
                            for (let $$index_1 = 0, $$length = each_array_1.length; $$index_1 < $$length; $$index_1++) {
                              let row = each_array_1[$$index_1];
                              if (Table_row) {
                                $$renderer7.push("<!--[-->");
                                Table_row($$renderer7, {
                                  children: ($$renderer8) => {
                                    if (Table_cell) {
                                      $$renderer8.push("<!--[-->");
                                      Table_cell($$renderer8, {
                                        class: "min-w-56",
                                        children: ($$renderer9) => {
                                          Link($$renderer9, {
                                            href: `/communications/${row.id}/edit`,
                                            class: "text-foreground max-w-[36ch] font-semibold break-words hover:underline",
                                            children: ($$renderer10) => {
                                              $$renderer10.push(`<!---->${escape_html(row.title)}`);
                                            },
                                            $$slots: { default: true }
                                          });
                                          $$renderer9.push(`<!----> <p class="text-muted-foreground mt-1 max-w-[42ch] text-xs break-words whitespace-pre-wrap">${escape_html(row.summary)}</p>`);
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
                                        class: "min-w-36",
                                        children: ($$renderer9) => {
                                          if (row.program_code) {
                                            $$renderer9.push("<!--[0-->");
                                            Badge($$renderer9, {
                                              variant: "secondary",
                                              children: ($$renderer10) => {
                                                $$renderer10.push(`<!---->${escape_html(row.program_code)} Program`);
                                              },
                                              $$slots: { default: true }
                                            });
                                          } else {
                                            $$renderer9.push("<!--[-1-->");
                                            Badge($$renderer9, {
                                              variant: "outline",
                                              children: ($$renderer10) => {
                                                $$renderer10.push(`<!---->General`);
                                              },
                                              $$slots: { default: true }
                                            });
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
                                    $$renderer8.push(` `);
                                    if (Table_cell) {
                                      $$renderer8.push("<!--[-->");
                                      Table_cell($$renderer8, {
                                        children: ($$renderer9) => {
                                          Badge($$renderer9, {
                                            variant: row.effective_status === "published" ? "default" : "secondary",
                                            children: ($$renderer10) => {
                                              $$renderer10.push(`<!---->${escape_html(statusLabel(row))}`);
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
                                        class: "min-w-44 text-xs",
                                        children: ($$renderer9) => {
                                          $$renderer9.push(`<!---->Starts ${escape_html(dateLabel(row.starts_at))}<br/> Ends ${escape_html(dateLabel(row.ends_at))}`);
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
                                          rowActions($$renderer9, row);
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
                $$renderer5.push(`</div>`);
              }, cards = function($$renderer5) {
                $$renderer5.push(`<ul class="grid grid-cols-1 gap-4 md:grid-cols-2 xl:grid-cols-3" role="list"><!--[-->`);
                const each_array_2 = ensure_array_like(rows());
                for (let $$index_2 = 0, $$length = each_array_2.length; $$index_2 < $$length; $$index_2++) {
                  let row = each_array_2[$$index_2];
                  {
                    let status2 = function($$renderer6) {
                      Badge($$renderer6, {
                        variant: row.effective_status === "published" ? "default" : "secondary",
                        children: ($$renderer7) => {
                          $$renderer7.push(`<!---->${escape_html(statusLabel(row))}`);
                        },
                        $$slots: { default: true }
                      });
                    }, summary = function($$renderer6) {
                      $$renderer6.push(`<p class="text-muted-foreground line-clamp-2 text-sm leading-5 break-words">${escape_html(row.summary || "No summary provided.")}</p>`);
                    }, details = function($$renderer6) {
                      $$renderer6.push(`<dl class="grid grid-cols-1 gap-3 text-sm sm:grid-cols-2"><div class="sm:col-span-2"><dt class="text-muted-foreground text-xs">Schedule</dt> <dd class="text-xs">Starts ${escape_html(dateLabel(row.starts_at))}<br/> Ends ${escape_html(dateLabel(row.ends_at))}</dd></div> `);
                      if (row.published_at) {
                        $$renderer6.push("<!--[0-->");
                        $$renderer6.push(`<div><dt class="text-muted-foreground text-xs">Published</dt> <dd class="text-xs">${escape_html(dateLabel(row.published_at))}</dd></div>`);
                      } else {
                        $$renderer6.push("<!--[-1-->");
                      }
                      $$renderer6.push(`<!--]--> <div><dt class="text-muted-foreground text-xs">Program</dt> <dd class="text-xs">${escape_html(row.program_code ? `${row.program_code} program` : "General announcement")}</dd></div> <div><dt class="text-muted-foreground text-xs">Target audience</dt> <dd class="text-xs">${escape_html(row.program_code ? `Visitors to the ${row.program_code} program page` : "Visitors to the general announcement board")}</dd></div> <div><dt class="text-muted-foreground text-xs">Revision</dt> <dd class="text-xs">v${escape_html(row.revision)}${escape_html(row.priority === "urgent" ? " · Urgent" : "")}${escape_html(row.is_featured ? " · Featured" : "")}</dd></div></dl>`);
                    }, actions = function($$renderer6) {
                      $$renderer6.push(`<div class="flex min-w-0 items-center gap-2">`);
                      if (row.can_edit) {
                        $$renderer6.push("<!--[0-->");
                        Button($$renderer6, {
                          href: `/communications/${row.id}/edit`,
                          variant: "default",
                          class: "min-h-10",
                          children: ($$renderer7) => {
                            $$renderer7.push(`<!---->Edit announcement`);
                          },
                          $$slots: { default: true }
                        });
                      } else if (row.can_view) {
                        $$renderer6.push("<!--[1-->");
                        Button($$renderer6, {
                          href: `/communications/${row.id}/edit`,
                          variant: "outline",
                          class: "min-h-10",
                          children: ($$renderer7) => {
                            $$renderer7.push(`<!---->View details`);
                          },
                          $$slots: { default: true }
                        });
                      } else if (row.effective_status === "published") {
                        $$renderer6.push("<!--[2-->");
                        Button($$renderer6, {
                          href: `/announcements/${row.id}`,
                          variant: "outline",
                          class: "min-h-10",
                          children: ($$renderer7) => {
                            $$renderer7.push(`<!---->View live ↗`);
                          },
                          $$slots: { default: true }
                        });
                      } else {
                        $$renderer6.push("<!--[-1-->");
                      }
                      $$renderer6.push(`<!--]--> `);
                      rowActions($$renderer6, row);
                      $$renderer6.push(`<!----></div>`);
                    };
                    OperationalCard($$renderer5, {
                      id: row.id,
                      title: row.title,
                      subtitle: `${row.publisher_label} · ${placementLabel(row)}`,
                      status: status2,
                      summary,
                      details,
                      actions,
                      $$slots: { status: true, summary: true, details: true, actions: true }
                    });
                  }
                }
                $$renderer5.push(`<!--]--></ul>`);
              };
              SwitchableListView($$renderer4, {
                cardsInset: false,
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
          }
          $$renderer4.push(`<!--]--> `);
          SimplePagination($$renderer4, { data: announcements });
          $$renderer4.push(`<!----></section></div> `);
          if (Dialog) {
            $$renderer4.push("<!--[-->");
            Dialog($$renderer4, {
              onOpenChange: (open) => {
                withdrawOpen = open;
                if (!open && !withdrawProcessing) withdrawTarget = null;
              },
              get open() {
                return withdrawOpen;
              },
              set open($$value) {
                withdrawOpen = $$value;
                $$settled = false;
              },
              children: ($$renderer5) => {
                if (Dialog_content) {
                  $$renderer5.push("<!--[-->");
                  Dialog_content($$renderer5, {
                    class: "max-h-[calc(100dvh-2rem)] overflow-y-auto sm:max-w-lg",
                    children: ($$renderer6) => {
                      if (Dialog_header) {
                        $$renderer6.push("<!--[-->");
                        Dialog_header($$renderer6, {
                          children: ($$renderer7) => {
                            if (Dialog_title) {
                              $$renderer7.push("<!--[-->");
                              Dialog_title($$renderer7, {
                                children: ($$renderer8) => {
                                  $$renderer8.push(`<!---->Withdraw announcement?`);
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
                                  $$renderer8.push(`<!---->This immediately removes “${escape_html(withdrawTarget?.title ?? "this announcement")}” from public pages. Its publication
                    history is retained.`);
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
                      $$renderer6.push(` <form class="space-y-4"><div class="space-y-2"><label for="withdraw-reason" class="text-sm font-medium">Reason for withdrawal</label> <textarea id="withdraw-reason" minlength="3" maxlength="500" required="" rows="4"${attr("disabled", withdrawProcessing, true)} class="border-input bg-background text-foreground focus-visible:ring-ring min-h-24 w-full rounded-md border px-3 py-2 text-sm focus-visible:ring-2 focus-visible:outline-none disabled:opacity-60">`);
                      const $$body = escape_html(withdrawReason);
                      if ($$body) {
                        $$renderer6.push(`${$$body}`);
                      }
                      $$renderer6.push(`</textarea> <p class="text-muted-foreground text-xs">${escape_html(withdrawReason.trim().length)}/500 characters</p></div> `);
                      if (withdrawError) {
                        $$renderer6.push("<!--[0-->");
                        $$renderer6.push(`<p role="alert" class="text-destructive text-sm">${escape_html(withdrawError)}</p>`);
                      } else {
                        $$renderer6.push("<!--[-1-->");
                      }
                      $$renderer6.push(`<!--]--> `);
                      if (Dialog_footer) {
                        $$renderer6.push("<!--[-->");
                        Dialog_footer($$renderer6, {
                          children: ($$renderer7) => {
                            Button($$renderer7, {
                              type: "button",
                              variant: "outline",
                              class: "min-h-[44px]",
                              disabled: withdrawProcessing,
                              onclick: closeWithdraw,
                              children: ($$renderer8) => {
                                $$renderer8.push(`<!---->Keep announcement`);
                              },
                              $$slots: { default: true }
                            });
                            $$renderer7.push(`<!----> `);
                            Button($$renderer7, {
                              type: "submit",
                              variant: "destructive",
                              class: "min-h-[44px]",
                              disabled: withdrawReason.trim().length < 3 || withdrawReason.trim().length > 500,
                              children: ($$renderer8) => {
                                $$renderer8.push(`<!---->${escape_html("Confirm withdrawal")}`);
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
  Index as default
};
