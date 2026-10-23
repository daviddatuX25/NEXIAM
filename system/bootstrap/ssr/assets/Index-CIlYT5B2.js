import { o as onDestroy, h as head, a as ensure_array_like, b as attr, c as attr_class, e as escape_html, B as Button, d as derived, n as newIdempotencyKey } from "../app.js";
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
function Index($$renderer, $$props) {
  $$renderer.component(($$renderer2) => {
    let {
      policies = { data: [], total: 0 },
      publishers = [],
      capabilities = { manage: false, publish: false },
      filters = { status: "all", search: "", placement: "all" },
      status_counts = {}
    } = $$props;
    let filterSearch = filters.search ?? "";
    let status = filters.status ?? "all";
    let placement = filters.placement ?? "all";
    let viewMode = "responsive";
    let withdrawTarget = null;
    let withdrawReason = "";
    let withdrawError = "";
    let processing = false;
    let publishingId = null;
    let actionError = "";
    let filterTimer;
    let requestSignature = "";
    let requestToken = "";
    let actionListPage = 1;
    let refreshAfterAction = false;
    const rows = derived(() => policies.data ?? []);
    const statuses = [
      { value: "all", label: "All" },
      { value: "published", label: "Published" },
      { value: "draft", label: "Draft" },
      { value: "withdrawn", label: "Withdrawn" }
    ];
    const statusCounts = derived(() => ({
      all: status_counts.all ?? policies.total ?? rows().length,
      published: status_counts.published ?? 0,
      draft: status_counts.draft ?? 0,
      withdrawn: status_counts.withdrawn ?? 0
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
    const breadcrumbs = [
      { label: "Setup", href: "/setup" },
      { label: "Public Policies" }
    ];
    onDestroy(() => clearTimeout(filterTimer));
    function applyFilters() {
      clearTimeout(filterTimer);
      actionListPage = 1;
      router.get(
        "/communications/policies",
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
        "/communications/policies",
        {
          search: filterSearch.trim() || void 0,
          status: status === "all" ? void 0 : status,
          placement: placement === "all" ? void 0 : placement,
          page: actionListPage
        },
        {
          only: ["policies", "filters"],
          preserveState: true,
          preserveScroll: true,
          replace: true
        }
      );
    }
    function stableRequestId(payload) {
      const signature = JSON.stringify(payload);
      if (signature !== requestSignature) {
        requestSignature = signature;
        requestToken = newIdempotencyKey();
      }
      return requestToken;
    }
    function canPublish(row) {
      return Boolean(row.can_publish) && publishingId === null && !processing;
    }
    function hasPendingDraft(row) {
      return row.revision > (row.published_revision ?? 0);
    }
    function policyStatusLabel(status2) {
      return {
        published: "Published",
        draft: "Draft",
        withdrawn: "Withdrawn"
      }[status2] ?? status2;
    }
    function placementLabel(row) {
      return row.placement ?? (row.program_code ? `${row.program_code} program` : "General");
    }
    function programDetailsLabel(row) {
      if (!row.program_code) return "General policy";
      return row.program_name ? `${row.program_code} · ${row.program_name}` : row.program_code;
    }
    function policyExcerpt(row) {
      return row.excerpt ?? row.summary ?? "";
    }
    function actionItems(row) {
      return [
        {
          label: "Edit",
          href: `/communications/policies/${row.id}/edit`
        },
        ...row.status === "published" ? [
          {
            label: "View live",
            href: `/policies/${row.id}`,
            external: true
          }
        ] : [],
        ...hasPendingDraft(row) && canPublish(row) ? [{ label: "Publish", onclick: () => publish(row) }] : [],
        ...row.status === "published" && canPublish(row) ? [
          {
            label: "Withdraw",
            onclick: () => openWithdraw(row),
            danger: true
          }
        ] : []
      ];
    }
    function dateLabel(value) {
      if (!value) return "—";
      const date = new Date(value);
      return Number.isNaN(date.getTime()) ? value : date.toLocaleDateString();
    }
    function timestampLabel(value) {
      if (!value) return "—";
      const date = new Date(value);
      return Number.isNaN(date.getTime()) ? value : date.toLocaleString();
    }
    function publish(row) {
      if (publishingId !== null || processing) return;
      actionError = "";
      publishingId = row.id;
      actionListPage = policies.current_page ?? 1;
      refreshAfterAction = false;
      const expected_revision = row.revision;
      const request_id = stableRequestId({ operation: "publish", policy_id: row.id, expected_revision });
      router.post(`/communications/policies/${row.id}/publish`, { request_id, expected_revision }, {
        preserveScroll: true,
        onSuccess: () => {
          requestSignature = "";
          requestToken = "";
          refreshAfterAction = true;
        },
        onError: (errors) => {
          actionError = errors?.expected_revision || errors?.request_id || errors?.message || "The policy could not be published. Reload and retry.";
        },
        onFinish: () => {
          publishingId = null;
          if (refreshAfterAction) {
            refreshAfterAction = false;
            refreshFilteredList();
          }
        }
      });
    }
    function openWithdraw(row) {
      if (publishingId !== null || processing) return;
      actionListPage = policies.current_page ?? 1;
      withdrawTarget = row;
      withdrawReason = "";
      withdrawError = "";
    }
    function closeWithdraw() {
      withdrawTarget = null;
    }
    let $$settled = true;
    let $$inner_renderer;
    function $$render_inner($$renderer3) {
      head("df73i", $$renderer3, ($$renderer4) => {
        $$renderer4.title(($$renderer5) => {
          $$renderer5.push(`<title>Public policies</title>`);
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
                      href: "/communications/policies/create",
                      variant: "default",
                      class: "min-h-11",
                      children: ($$renderer7) => {
                        Plus($$renderer7, { class: "size-4" });
                        $$renderer7.push(`<!----> New policy`);
                      },
                      $$slots: { default: true }
                    });
                  } else {
                    $$renderer6.push("<!--[-1-->");
                  }
                  $$renderer6.push(`<!--]-->`);
                }, secondaryActions = function($$renderer6) {
                  Button($$renderer6, {
                    href: "/policies",
                    variant: "ghost",
                    class: "min-h-11 w-full sm:w-auto",
                    children: ($$renderer7) => {
                      External_link($$renderer7, { class: "size-4" });
                      $$renderer7.push(`<!----> View public policies ↗`);
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
              title: "Public policies",
              description: "Manage general and program-specific public documents. Each document has a separate saved draft and publication history.",
              actions
            });
          }
          $$renderer4.push(`<!----> <section class="border-border bg-card/70 min-w-0 rounded-lg border p-4 sm:p-5" aria-label="Public policy management list">`);
          if (actionError) {
            $$renderer4.push("<!--[0-->");
            $$renderer4.push(`<p class="mb-4 rounded-lg border border-amber-500/40 bg-amber-500/10 p-3 text-sm" role="alert">${escape_html(actionError)}</p>`);
          } else {
            $$renderer4.push("<!--[-1-->");
          }
          $$renderer4.push(`<!--]--> <div class="mb-4 flex flex-wrap gap-2" role="group" aria-label="Filter policies by status"><!--[-->`);
          const each_array = ensure_array_like(statuses);
          for (let $$index = 0, $$length = each_array.length; $$index < $$length; $$index++) {
            let option = each_array[$$index];
            $$renderer4.push(`<button type="button"${attr("aria-pressed", status === option.value)}${attr_class(`inline-flex min-h-10 items-center gap-2 rounded-full border px-4 text-sm font-medium ${status === option.value ? "border-primary bg-primary text-primary-foreground" : "border-border bg-background hover:bg-muted"}`)}>${escape_html(option.label)} <span class="rounded-full bg-background/20 px-2 py-0.5 text-xs">${escape_html(statusCounts()[option.value])}</span></button>`);
          }
          $$renderer4.push(`<!--]--></div> <div>`);
          {
            let desktopFilters = function($$renderer5) {
              $$renderer5.push(`<label class="grid gap-1 text-sm"><span class="text-muted-foreground text-xs font-medium">Placement</span> `);
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
              $$renderer5.push(`<label class="grid gap-1 text-sm"><span class="text-muted-foreground text-xs font-medium">Placement</span> `);
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
              searchLabel: "Search public policies",
              searchPlaceholder: "Search title, issuer or program",
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
          $$renderer4.push(`<!----> <div class="mt-3 flex flex-wrap items-center justify-between gap-2"><p class="text-sm font-semibold">Policy catalog</p> <p class="text-muted-foreground text-sm" aria-live="polite">${escape_html(policies.total ?? rows().length)} document${escape_html((policies.total ?? rows().length) === 1 ? "" : "s")}</p></div> `);
          if (rows().length === 0) {
            $$renderer4.push("<!--[0-->");
            $$renderer4.push(`<div class="border-border rounded-lg border border-dashed p-8 text-center" role="status"><h2 class="font-semibold">${escape_html(policies.total > 0 ? "No policies on this page." : hasActiveFilters() ? "No policies match these filters in your authorized scope." : "No policies in this authorized scope yet.")}</h2> <p class="text-muted-foreground mt-1 text-sm">${escape_html(policies.total > 0 ? "Return to the first page to continue browsing." : hasActiveFilters() ? "Change or clear a filter to see other policies in your authorized scope." : "Create a draft when you are ready to prepare an authorized public document.")}</p> `);
            if (policies.total > 0) {
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
                href: "/communications/policies/create",
                class: "mt-4 min-h-11",
                children: ($$renderer5) => {
                  $$renderer5.push(`<!---->New policy`);
                },
                $$slots: { default: true }
              });
            } else {
              $$renderer4.push("<!--[-1-->");
            }
            $$renderer4.push(`<!--]--></div> `);
            if (policies.total > 0) {
              $$renderer4.push("<!--[0-->");
              SimplePagination($$renderer4, { data: policies });
            } else {
              $$renderer4.push("<!--[-1-->");
            }
            $$renderer4.push(`<!--]-->`);
          } else {
            $$renderer4.push("<!--[-1-->");
            {
              let table = function($$renderer5) {
                if (Table) {
                  $$renderer5.push("<!--[-->");
                  Table($$renderer5, {
                    class: "w-full min-w-[960px] text-left text-sm",
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
                                        $$renderer9.push(`<!---->Title / issuer`);
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
                                        $$renderer9.push(`<!---->Placement`);
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
                                        $$renderer9.push(`<!---->Status and versions`);
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
                                        $$renderer9.push(`<!---->Publication`);
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
                                        class: "max-w-[24rem]",
                                        children: ($$renderer9) => {
                                          Link($$renderer9, {
                                            href: `/communications/policies/${row.id}/edit`,
                                            class: "text-primary font-semibold underline-offset-4 hover:underline",
                                            children: ($$renderer10) => {
                                              $$renderer10.push(`<!---->${escape_html(row.title)}`);
                                            },
                                            $$slots: { default: true }
                                          });
                                          $$renderer9.push(`<!----> <p class="text-muted-foreground mt-1 text-xs">${escape_html(row.publisher_label)}</p> `);
                                          if (row.status === "withdrawn" && row.withdrawal_reason) {
                                            $$renderer9.push("<!--[0-->");
                                            $$renderer9.push(`<p class="text-muted-foreground mt-2 text-xs break-words">Withdrawal reason: ${escape_html(row.withdrawal_reason)}</p>`);
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
                                    $$renderer8.push(` `);
                                    if (Table_cell) {
                                      $$renderer8.push("<!--[-->");
                                      Table_cell($$renderer8, {
                                        children: ($$renderer9) => {
                                          $$renderer9.push(`<!---->${escape_html(row.program_code ? `${row.program_code} program` : "General")}`);
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
                                        class: "min-w-48",
                                        children: ($$renderer9) => {
                                          Badge($$renderer9, {
                                            variant: row.status === "published" ? "default" : "secondary",
                                            children: ($$renderer10) => {
                                              $$renderer10.push(`<!---->${escape_html(row.status)}`);
                                            },
                                            $$slots: { default: true }
                                          });
                                          $$renderer9.push(`<!----> <dl class="mt-2 space-y-1 text-xs"><div><dt class="text-muted-foreground inline">Saved:</dt> <dd class="inline font-medium">v${escape_html(row.revision)}${escape_html(hasPendingDraft(row) ? " · draft" : "")}</dd></div> <div><dt class="text-muted-foreground inline">${escape_html(row.status === "withdrawn" ? "Last public: " : "Current public: ")}</dt> <dd class="inline font-medium">${escape_html(row.published_revision ? `v${row.published_revision}` : "Not published")}</dd></div></dl>`);
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
                                        class: "min-w-32 text-sm",
                                        children: ($$renderer9) => {
                                          $$renderer9.push(`<!---->${escape_html(dateLabel(row.published_at))}`);
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
                                        class: "min-w-52",
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
              }, cards = function($$renderer5) {
                $$renderer5.push(`<ul class="grid grid-cols-1 gap-4 md:grid-cols-2 xl:grid-cols-3" role="list"><!--[-->`);
                const each_array_2 = ensure_array_like(rows());
                for (let $$index_2 = 0, $$length = each_array_2.length; $$index_2 < $$length; $$index_2++) {
                  let row = each_array_2[$$index_2];
                  {
                    let status2 = function($$renderer6) {
                      $$renderer6.push(`<div class="flex shrink-0 flex-col items-end gap-1">`);
                      Badge($$renderer6, {
                        variant: row.status === "published" ? "default" : "secondary",
                        children: ($$renderer7) => {
                          $$renderer7.push(`<!---->${escape_html(policyStatusLabel(row.status))}`);
                        },
                        $$slots: { default: true }
                      });
                      $$renderer6.push(`<!----> `);
                      if (row.kind === "privacy") {
                        $$renderer6.push("<!--[0-->");
                        Badge($$renderer6, {
                          variant: "outline",
                          children: ($$renderer7) => {
                            $$renderer7.push(`<!---->Privacy`);
                          },
                          $$slots: { default: true }
                        });
                      } else {
                        $$renderer6.push("<!--[-1-->");
                      }
                      $$renderer6.push(`<!--]--></div>`);
                    }, summary = function($$renderer6) {
                      if (policyExcerpt(row)) {
                        $$renderer6.push("<!--[0-->");
                        $$renderer6.push(`<p class="line-clamp-2 break-words">${escape_html(policyExcerpt(row))}</p>`);
                      } else {
                        $$renderer6.push("<!--[-1-->");
                      }
                      $$renderer6.push(`<!--]-->`);
                    }, details = function($$renderer6) {
                      $$renderer6.push(`<dl class="grid grid-cols-2 gap-x-3 gap-y-2 text-xs"><div><dt class="text-muted-foreground">Revision</dt> <dd class="font-medium">v${escape_html(row.revision)}${escape_html(hasPendingDraft(row) ? " · draft" : "")}</dd></div> <div><dt class="text-muted-foreground">Publisher</dt> <dd class="font-medium">${escape_html(row.publisher_label)}</dd></div> <div class="col-span-2"><dt class="text-muted-foreground">Program</dt> <dd class="font-medium">${escape_html(programDetailsLabel(row))}</dd></div> <div><dt class="text-muted-foreground">${escape_html(row.status === "withdrawn" ? "Last public" : "Current public")}</dt> <dd class="font-medium">${escape_html(row.published_revision ? `v${row.published_revision}` : "Not published")}</dd></div> <div><dt class="text-muted-foreground">Published</dt> <dd>${escape_html(dateLabel(row.published_at))}</dd></div> <div class="col-span-2"><dt class="text-muted-foreground">Last updated</dt> <dd>${escape_html(timestampLabel(row.updated_at))}</dd></div></dl> `);
                      if (row.status === "withdrawn" && row.withdrawal_reason) {
                        $$renderer6.push("<!--[0-->");
                        $$renderer6.push(`<p class="text-muted-foreground break-words">Withdrawal reason: ${escape_html(row.withdrawal_reason)}</p>`);
                      } else {
                        $$renderer6.push("<!--[-1-->");
                      }
                      $$renderer6.push(`<!--]-->`);
                    }, actions = function($$renderer6) {
                      $$renderer6.push(`<div class="flex items-center gap-2">`);
                      Link($$renderer6, {
                        href: `/communications/policies/${row.id}/edit`,
                        class: "flex-1",
                        children: ($$renderer7) => {
                          Button($$renderer7, {
                            variant: "default",
                            class: "min-h-9 w-full",
                            children: ($$renderer8) => {
                              $$renderer8.push(`<!---->Edit policy`);
                            },
                            $$slots: { default: true }
                          });
                        },
                        $$slots: { default: true }
                      });
                      $$renderer6.push(`<!----> `);
                      rowActions($$renderer6, row);
                      $$renderer6.push(`<!----></div>`);
                    };
                    OperationalCard($$renderer5, {
                      id: row.id,
                      title: row.title,
                      subtitle: `${row.publisher_label} · ${row.placement ?? placementLabel(row)}`,
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
            $$renderer4.push(`<!----> `);
            SimplePagination($$renderer4, { data: policies });
            $$renderer4.push(`<!---->`);
          }
          $$renderer4.push(`<!--]--></section></div> <dialog class="border-border bg-card text-card-foreground m-auto w-[min(92vw,34rem)] rounded-xl border p-0 shadow-xl backdrop:bg-black/50">`);
          if (withdrawTarget) {
            $$renderer4.push("<!--[0-->");
            $$renderer4.push(`<form class="space-y-4 p-5 sm:p-6"><div><h2 class="text-lg font-semibold">Withdraw this public policy?</h2> <p class="text-muted-foreground mt-1 text-sm">“${escape_html(withdrawTarget.title)}” will stop appearing publicly.
                        Its released content and history are retained; withdrawn
                        policies cannot be republished.</p></div> <label class="grid gap-2 text-sm font-medium" for="withdraw-reason">Reason for withdrawal <textarea id="withdraw-reason" maxlength="1000" required=""${attr("disabled", processing, true)} class="border-input bg-background min-h-28 rounded-md border px-3 py-2 font-normal">`);
            const $$body = escape_html(withdrawReason);
            if ($$body) {
              $$renderer4.push(`${$$body}`);
            }
            $$renderer4.push(`</textarea> <span class="text-muted-foreground text-xs">${escape_html(withdrawReason.length)}/1000</span></label> `);
            if (withdrawError) {
              $$renderer4.push("<!--[0-->");
              $$renderer4.push(`<p role="alert" class="text-destructive text-sm">${escape_html(withdrawError)}</p>`);
            } else {
              $$renderer4.push("<!--[-1-->");
            }
            $$renderer4.push(`<!--]--> <div class="flex flex-wrap justify-end gap-2">`);
            Button($$renderer4, {
              type: "button",
              variant: "outline",
              class: "min-h-11",
              disabled: processing,
              onclick: closeWithdraw,
              children: ($$renderer5) => {
                $$renderer5.push(`<!---->Keep policy`);
              },
              $$slots: { default: true }
            });
            $$renderer4.push(`<!----> `);
            Button($$renderer4, {
              type: "submit",
              variant: "destructive",
              class: "min-h-11",
              disabled: !withdrawReason.trim(),
              children: ($$renderer5) => {
                $$renderer5.push(`<!---->${escape_html("Withdraw policy")}`);
              },
              $$slots: { default: true }
            });
            $$renderer4.push(`<!----></div></form>`);
          } else {
            $$renderer4.push("<!--[-1-->");
          }
          $$renderer4.push(`<!--]--></dialog>`);
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
