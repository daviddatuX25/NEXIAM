import { h as head, e as escape_html, a as ensure_array_like, b as attr, f as attr_style, s as stringify, x as store_get, B as Button, y as unsubscribe_stores, d as derived } from "../app.js";
import { S as StaffLayout, C as Calendar } from "./StaffLayout-CR-MZopa.js";
import { P as PageHeaderBar } from "./PageHeaderBar-DJhT5vOR.js";
import { H as HeaderActions } from "./HeaderActions-Dxxt_yha.js";
import { B as BulkActionBar } from "./BulkActionBar-CIsDAPcP.js";
import { C as Checkbox } from "./checkbox-D_0Lz-jK.js";
import { S as SwitchableListView } from "./SwitchableListView-DHKJpEKr.js";
import { O as OperationalCard } from "./OperationalCard-D2Z4nRzj.js";
import { B as Badge } from "./badge-D9Vgs7xh.js";
import { I as Input } from "./input-BUZAZKIK.js";
import { S as Select, a as Select_trigger, b as Select_content, c as Select_item } from "./select-trigger-rRjpYIEn.js";
import "clsx";
import { T as Table, a as Table_header, b as Table_row, c as Table_head, d as Table_body, e as Table_cell } from "./table-row-_ffWKrps.js";
import { C as CohortAutoDistributeModal } from "./CohortAutoDistributeModal-yVYa-9cp.js";
import { f as formatDate, a as formatTimeRange, b as formatHumanSchedule } from "./date-utils-Bc5VPPy9.js";
import { b as bookingUnlocked } from "./booking-visibility-DG8T2RPN.js";
import { S as Search } from "./settings-C5xOKpV3.js";
import { C as Clock } from "./clock-B4fXoAi9.js";
import { L as Link } from "./Link-B7ks7rBK.js";
import { E as Eye } from "./eye-DyG-RiwW.js";
import { P as Pencil } from "./pencil-0tPPVae8.js";
import { B as Building_2 } from "./building-2-BaeEMGXC.js";
import { C as Clipboard_list } from "./clipboard-list-YgthURdB.js";
import { P as Plus } from "./plus-DjyPLC7A.js";
import { S as Sparkles } from "./sparkles-DbQIaVG6.js";
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
import "./qr-code-C5X8qcH6.js";
import "./chevron-right-BHu-PGOg.js";
import "./chevron-down-CkQQZIhu.js";
import "./printer-Bx0z1CRb.js";
import "./book-open-B6eMguqY.js";
import "./megaphone-C8QUuT5s.js";
import "./file-check-BSLEMs1I.js";
import "./shield-DTtuUihb.js";
import "./users-DWlguTa_.js";
import "./user-round-Kft0Cy_Y.js";
import "./XiamNpcDialog-DoqCSHj9.js";
/* empty css                                                        */
import "./circle-question-mark-DY-RU614.js";
import "./chevron-left-DCSOZwZl.js";
import "./clone-BJz-lxsY.js";
import "./check-DK45Y9SJ.js";
import "./minus-Dqkhcxt7.js";
import "./roving-focus-group-BJDH9kUk.js";
import "./table-2-BLgeaPqt.js";
import "./layout-grid-CXJfSBqV.js";
import "./chevron-up-lhASJLRD.js";
import "./previous.svelte-Ch7Tz-Mv.js";
import "./arrays-CkOR4vx_.js";
import "./graduation-cap-Bc9amiZ0.js";
import "./zap-CyG8UtsE.js";
import "./loader-circle-_UEl10Lx.js";
function Index($$renderer, $$props) {
  $$renderer.component(($$renderer2) => {
    var $$store_subs;
    let {
      page_state = "ready",
      sessions = [],
      filters = {},
      statuses = [],
      cycles = [],
      distribution = {}
    } = $$props;
    let autoDistributeOpen = false;
    let viewMode = "responsive";
    let filterSearch = filters.search ?? "";
    let filterStatus = filters.status ?? "";
    let filterCycleId = filters.academic_cycle_id ? String(filters.academic_cycle_id) : "";
    let debounceTimer;
    let selectedSessionIds = [];
    let cancelDialogOpen = false;
    let cancelReason = "";
    let visibleSessionIds = derived(() => sessions.map((session) => session.id));
    let isAllVisibleSelected = derived(() => visibleSessionIds().length > 0 && visibleSessionIds().every((id) => selectedSessionIds.includes(id)));
    let isSomeVisibleSelected = derived(() => visibleSessionIds().some((id) => selectedSessionIds.includes(id)) && !isAllVisibleSelected());
    const draftCount = derived(() => (sessions ?? []).filter((s) => s.status === "draft").length);
    const publishedCount = derived(() => (sessions ?? []).filter((s) => s.status === "published").length);
    const openReservationCount = derived(() => (sessions ?? []).filter((s) => s.booking_mode === "open_for_reservation").length);
    const staffOnlyCount = derived(() => (sessions ?? []).filter((s) => s.booking_mode === "staff_only").length);
    const totalCapacity = derived(() => (sessions ?? []).reduce((acc, s) => acc + (s.capacity || 0), 0));
    const totalAssigned = derived(() => (sessions ?? []).reduce((acc, s) => acc + (s.assigned_count || 0), 0));
    const sessionsContext = derived(() => {
      const safetyKey = `${page_state}:${sessions?.length ?? 0}:${draftCount()}:${publishedCount()}:${selectedSessionIds.length}:${openReservationCount()}`;
      return {
        entityKey: "exam-sessions-hub",
        safetyKey,
        freshness: "current",
        pageState: page_state,
        sessionsCount: sessions?.length ?? 0,
        draftCount: draftCount(),
        publishedCount: publishedCount(),
        openReservationCount: openReservationCount(),
        staffOnlyCount: staffOnlyCount(),
        selectedSessionsCount: selectedSessionIds.length,
        totalCapacity: totalCapacity(),
        totalAssigned: totalAssigned()
      };
    });
    function toggleAllVisible() {
      selectedSessionIds = isAllVisibleSelected() ? selectedSessionIds.filter((id) => !visibleSessionIds().includes(id)) : [.../* @__PURE__ */ new Set([...selectedSessionIds, ...visibleSessionIds()])];
    }
    function submitBulkAction(url, payload) {
      router.post(url, payload, { onSuccess: () => selectedSessionIds = [] });
    }
    function submitBulkCancel() {
      if (!cancelReason.trim()) return;
      submitBulkAction("/exams/sessions/bulk-cancel", { session_ids: selectedSessionIds, reason: cancelReason.trim() });
      cancelDialogOpen = false;
      cancelReason = "";
    }
    function occupancyPercent(session) {
      return session.room?.capacity ? Math.round((session.assigned_count ?? 0) / session.room.capacity * 100) : 0;
    }
    function applyFilters() {
      router.get(
        "/exams/sessions",
        {
          search: filterSearch || void 0,
          status: filterStatus || void 0,
          academic_cycle_id: filterCycleId || void 0
        },
        { preserveState: true }
      );
    }
    function onSearchInput() {
      clearTimeout(debounceTimer);
      debounceTimer = setTimeout(applyFilters, 300);
    }
    function statusVariant(status) {
      if (status === "draft") return "secondary";
      if (status === "published") return "default";
      if (status === "in_progress") return "outline";
      if (status === "completed") return "outline";
      if (status === "cancelled") return "destructive";
      return "outline";
    }
    function statusLabel(value) {
      return statuses.find((s) => s.value === value)?.label ?? value;
    }
    function bookingModeLabel(mode) {
      if (mode === "open_for_reservation") return "Open for reservation";
      return "Staff only";
    }
    const breadcrumbs = [{ label: "Exam Scheduling" }];
    let $$settled = true;
    let $$inner_renderer;
    function $$render_inner($$renderer3) {
      head("b2ati3", $$renderer3, ($$renderer4) => {
        $$renderer4.title(($$renderer5) => {
          $$renderer5.push(`<title>Exam Scheduling</title>`);
        });
      });
      StaffLayout($$renderer3, {
        breadcrumbs,
        children: ($$renderer4) => {
          $$renderer4.push(`<div class="min-w-0 space-y-6 p-4 sm:p-6">`);
          {
            let actions = function($$renderer5) {
              {
                let primaryAction = function($$renderer6) {
                  Button($$renderer6, {
                    type: "button",
                    class: "min-h-10 gap-2 font-bold text-xs",
                    "data-tour": "auto-distribute-button",
                    onclick: () => autoDistributeOpen = true,
                    children: ($$renderer7) => {
                      Sparkles($$renderer7, { class: "size-4" });
                      $$renderer7.push(`<!----> Auto-Distribute Applicants`);
                    },
                    $$slots: { default: true }
                  });
                }, secondaryActions = function($$renderer6) {
                  Link($$renderer6, {
                    href: "/exams/direct-assessment",
                    children: ($$renderer7) => {
                      Button($$renderer7, {
                        variant: "outline",
                        class: "min-h-10 gap-2",
                        children: ($$renderer8) => {
                          Clipboard_list($$renderer8, { class: "size-4" });
                          $$renderer8.push(`<!----> Direct assessment`);
                        },
                        $$slots: { default: true }
                      });
                    },
                    $$slots: { default: true }
                  });
                  $$renderer6.push(`<!----> `);
                  Link($$renderer6, {
                    href: "/exams/sessions/create",
                    children: ($$renderer7) => {
                      Button($$renderer7, {
                        variant: "outline",
                        class: "min-h-10 gap-2",
                        children: ($$renderer8) => {
                          Plus($$renderer8, { class: "size-4" });
                          $$renderer8.push(`<!----> Create session`);
                        },
                        $$slots: { default: true }
                      });
                    },
                    $$slots: { default: true }
                  });
                  $$renderer6.push(`<!---->`);
                };
                HeaderActions($$renderer5, {
                  primaryAction,
                  secondaryActions
                });
              }
            };
            PageHeaderBar($$renderer4, {
              title: "Exam Scheduling",
              description: "Schedule examination sessions, allocate seating arrangements, assign applicants, and publish sessions for exam-day administration.",
              tourKey: "exam-sessions",
              context: sessionsContext(),
              actions
            });
          }
          $$renderer4.push(`<!----> `);
          if (page_state === "denied") {
            $$renderer4.push("<!--[0-->");
            $$renderer4.push(`<p class="text-sm text-muted-foreground">You do not have permission to manage exam sessions.</p>`);
          } else {
            $$renderer4.push("<!--[-1-->");
            $$renderer4.push(`<div class="space-y-6" data-tour="sessions-list-container"><div class="flex flex-wrap items-end gap-3"><div><label class="mb-1 block text-xs font-medium text-muted-foreground" for="exam-search">Search</label> <div class="relative">`);
            Search($$renderer4, {
              class: "pointer-events-none absolute left-2.5 top-1/2 size-4 -translate-y-1/2 text-muted-foreground"
            });
            $$renderer4.push(`<!----> `);
            Input($$renderer4, {
              id: "exam-search",
              type: "search",
              placeholder: "Code or room...",
              class: "min-h-10 min-w-[180px] pl-8",
              oninput: onSearchInput,
              get value() {
                return filterSearch;
              },
              set value($$value) {
                filterSearch = $$value;
                $$settled = false;
              }
            });
            $$renderer4.push(`<!----></div></div> <div><label class="mb-1 block text-xs font-medium text-muted-foreground" for="exam-status">Status</label> `);
            if (Select) {
              $$renderer4.push("<!--[-->");
              Select($$renderer4, {
                type: "single",
                value: filterStatus,
                onValueChange: (v) => {
                  filterStatus = v ?? "";
                  applyFilters();
                },
                children: ($$renderer5) => {
                  if (Select_trigger) {
                    $$renderer5.push("<!--[-->");
                    Select_trigger($$renderer5, {
                      id: "exam-status",
                      class: "min-h-10 w-[160px]",
                      children: ($$renderer6) => {
                        if (filterStatus) {
                          $$renderer6.push("<!--[0-->");
                          $$renderer6.push(`${escape_html(statusLabel(filterStatus))}`);
                        } else {
                          $$renderer6.push("<!--[-1-->");
                          $$renderer6.push(`<span class="text-muted-foreground">All statuses</span>`);
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
                            label: "All statuses",
                            children: ($$renderer7) => {
                              $$renderer7.push(`<!---->All statuses`);
                            },
                            $$slots: { default: true }
                          });
                          $$renderer6.push("<!--]-->");
                        } else {
                          $$renderer6.push("<!--[!-->");
                          $$renderer6.push("<!--]-->");
                        }
                        $$renderer6.push(` <!--[-->`);
                        const each_array = ensure_array_like(statuses);
                        for (let $$index = 0, $$length = each_array.length; $$index < $$length; $$index++) {
                          let s = each_array[$$index];
                          if (Select_item) {
                            $$renderer6.push("<!--[-->");
                            Select_item($$renderer6, {
                              value: s.value,
                              label: s.label,
                              children: ($$renderer7) => {
                                $$renderer7.push(`<!---->${escape_html(s.label)}`);
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
            $$renderer4.push(`</div> <div><label class="mb-1 block text-xs font-medium text-muted-foreground" for="exam-cycle">Academic year</label> `);
            if (Select) {
              $$renderer4.push("<!--[-->");
              Select($$renderer4, {
                type: "single",
                value: filterCycleId,
                onValueChange: (v) => {
                  filterCycleId = v ?? "";
                  applyFilters();
                },
                children: ($$renderer5) => {
                  if (Select_trigger) {
                    $$renderer5.push("<!--[-->");
                    Select_trigger($$renderer5, {
                      id: "exam-cycle",
                      class: "min-h-10 w-[220px]",
                      children: ($$renderer6) => {
                        if (filterCycleId) {
                          $$renderer6.push("<!--[0-->");
                          $$renderer6.push(`${escape_html(cycles.find((c) => String(c.id) === filterCycleId)?.label ?? "Academic year")}`);
                        } else {
                          $$renderer6.push("<!--[-1-->");
                          $$renderer6.push(`<span class="text-muted-foreground">All academic years</span>`);
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
                            label: "All academic years",
                            children: ($$renderer7) => {
                              $$renderer7.push(`<!---->All academic years`);
                            },
                            $$slots: { default: true }
                          });
                          $$renderer6.push("<!--]-->");
                        } else {
                          $$renderer6.push("<!--[!-->");
                          $$renderer6.push("<!--]-->");
                        }
                        $$renderer6.push(` <!--[-->`);
                        const each_array_1 = ensure_array_like(cycles);
                        for (let $$index_1 = 0, $$length = each_array_1.length; $$index_1 < $$length; $$index_1++) {
                          let c = each_array_1[$$index_1];
                          if (Select_item) {
                            $$renderer6.push("<!--[-->");
                            Select_item($$renderer6, {
                              value: String(c.id),
                              label: c.label,
                              children: ($$renderer7) => {
                                $$renderer7.push(`<!---->${escape_html(c.label)}`);
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
            $$renderer4.push(`</div></div> `);
            {
              let table = function($$renderer5) {
                if (Table) {
                  $$renderer5.push("<!--[-->");
                  Table($$renderer5, {
                    class: "w-full min-w-[820px] text-sm",
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
                                      class: "w-12 px-4 py-3",
                                      children: ($$renderer9) => {
                                        Checkbox($$renderer9, {
                                          "aria-label": "Select all visible sessions",
                                          checked: isAllVisibleSelected(),
                                          indeterminate: isSomeVisibleSelected(),
                                          onCheckedChange: toggleAllVisible
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
                                  if (Table_head) {
                                    $$renderer8.push("<!--[-->");
                                    Table_head($$renderer8, {
                                      class: "px-4 py-3 font-sans",
                                      children: ($$renderer9) => {
                                        $$renderer9.push(`<!---->Session Code`);
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
                                      class: "px-4 py-3 font-sans",
                                      children: ($$renderer9) => {
                                        $$renderer9.push(`<!---->Date &amp; Time`);
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
                                      class: "px-4 py-3 font-sans",
                                      children: ($$renderer9) => {
                                        $$renderer9.push(`<!---->Room`);
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
                                      class: "px-4 py-3 font-sans text-right",
                                      children: ($$renderer9) => {
                                        $$renderer9.push(`<!---->Capacity Occupancy`);
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
                                      class: "px-4 py-3 font-sans",
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
                                      class: "px-4 py-3 font-sans text-right pr-6",
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
                            const each_array_2 = ensure_array_like(sessions);
                            if (each_array_2.length !== 0) {
                              $$renderer7.push("<!--[-->");
                              for (let $$index_2 = 0, $$length = each_array_2.length; $$index_2 < $$length; $$index_2++) {
                                let session = each_array_2[$$index_2];
                                if (Table_row) {
                                  $$renderer7.push("<!--[-->");
                                  Table_row($$renderer7, {
                                    class: "cursor-pointer hover:bg-muted/30",
                                    onclick: (e) => {
                                      if (e.target.closest('input[type="checkbox"], [data-action-cell]')) return;
                                      router.visit(`/exams/sessions/${session.id}`);
                                    },
                                    children: ($$renderer8) => {
                                      if (Table_cell) {
                                        $$renderer8.push("<!--[-->");
                                        Table_cell($$renderer8, {
                                          class: "w-12 px-4 py-3",
                                          "data-action-cell": true,
                                          children: ($$renderer9) => {
                                            $$renderer9.push(`<input type="checkbox"${attr("aria-label", `Select session ${session.code}`)}${attr("checked", selectedSessionIds.includes(session.id), true)} class="size-4 rounded border-border accent-primary"/>`);
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
                                          class: "px-4 py-3 font-bold font-mono text-foreground",
                                          children: ($$renderer9) => {
                                            $$renderer9.push(`<!---->${escape_html(session.code)}`);
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
                                          class: "px-4 py-3 font-sans",
                                          children: ($$renderer9) => {
                                            $$renderer9.push(`<div class="space-y-0.5"><div class="flex items-center gap-1.5 font-medium text-foreground text-xs">`);
                                            Calendar($$renderer9, { class: "size-3.5 text-primary shrink-0" });
                                            $$renderer9.push(`<!----> <span>${escape_html(formatDate(session.date))}</span></div> `);
                                            if (session.start_time) {
                                              $$renderer9.push("<!--[0-->");
                                              $$renderer9.push(`<div class="flex items-center gap-1.5 text-[11px] text-muted-foreground font-mono">`);
                                              Clock($$renderer9, { class: "size-3 text-muted-foreground shrink-0" });
                                              $$renderer9.push(`<!----> <span>${escape_html(formatTimeRange(session.start_time, session.end_time))}</span></div>`);
                                            } else {
                                              $$renderer9.push("<!--[-1-->");
                                            }
                                            $$renderer9.push(`<!--]--></div>`);
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
                                          class: "px-4 py-3 font-sans text-xs",
                                          children: ($$renderer9) => {
                                            $$renderer9.push(`<span class="font-medium text-foreground">${escape_html(session.room?.name ?? session.room?.code ?? "—")}</span> `);
                                            if (session.room?.building) {
                                              $$renderer9.push("<!--[0-->");
                                              $$renderer9.push(`<span class="text-muted-foreground block text-[11px]">${escape_html(session.room.building)}</span>`);
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
                                          class: "px-4 py-3",
                                          children: ($$renderer9) => {
                                            $$renderer9.push(`<div class="space-y-1 min-w-[130px] font-mono"><div class="flex justify-between text-xs"><span class="font-bold text-foreground">${escape_html(session.assigned_count ?? 0)} / ${escape_html(session.room?.capacity ?? "—")}</span> <span class="text-muted-foreground">${escape_html(session.room?.capacity ? Math.round((session.assigned_count ?? 0) / session.room.capacity * 100) : 0)}%</span></div> <div class="w-full bg-muted rounded-full h-1.5 overflow-hidden"><div class="bg-emerald-500 h-1.5 rounded-full transition-all duration-300"${attr_style(`width: ${stringify(session.room?.capacity ? Math.min(100, Math.round((session.assigned_count ?? 0) / session.room.capacity * 100)) : 0)}%`)}></div></div></div>`);
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
                                            $$renderer9.push(`<div class="flex flex-col gap-1 items-start">`);
                                            Badge($$renderer9, {
                                              variant: statusVariant(session.status),
                                              class: "font-mono text-[10px] font-bold",
                                              children: ($$renderer10) => {
                                                $$renderer10.push(`<!---->${escape_html(statusLabel(session.status))}`);
                                              },
                                              $$slots: { default: true }
                                            });
                                            $$renderer9.push(`<!----> `);
                                            if (session.booking_mode === "open_for_reservation") {
                                              $$renderer9.push("<!--[0-->");
                                              Badge($$renderer9, {
                                                variant: "outline",
                                                class: "font-sans text-[10px] text-primary border-primary/30",
                                                children: ($$renderer10) => {
                                                  $$renderer10.push(`<!---->Open for reservation`);
                                                },
                                                $$slots: { default: true }
                                              });
                                            } else if (store_get($$store_subs ??= {}, "$bookingUnlocked", bookingUnlocked)) {
                                              $$renderer9.push("<!--[1-->");
                                              $$renderer9.push(`<span class="text-[10px] text-muted-foreground font-sans">Staff only</span>`);
                                            } else {
                                              $$renderer9.push("<!--[-1-->");
                                            }
                                            $$renderer9.push(`<!--]--></div>`);
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
                                          class: "px-4 py-3 text-right pr-6",
                                          "data-action-cell": true,
                                          children: ($$renderer9) => {
                                            $$renderer9.push(`<div class="inline-flex items-center justify-end gap-1.5">`);
                                            Link($$renderer9, {
                                              href: `/exams/sessions/${session.id}`,
                                              children: ($$renderer10) => {
                                                Button($$renderer10, {
                                                  variant: "outline",
                                                  size: "sm",
                                                  class: "h-8 px-2.5 text-xs font-semibold font-sans",
                                                  children: ($$renderer11) => {
                                                    Eye($$renderer11, { class: "mr-1.5 size-3.5" });
                                                    $$renderer11.push(`<!----> Show Roster`);
                                                  },
                                                  $$slots: { default: true }
                                                });
                                              },
                                              $$slots: { default: true }
                                            });
                                            $$renderer9.push(`<!----> `);
                                            if (session.status === "draft") {
                                              $$renderer9.push("<!--[0-->");
                                              Link($$renderer9, {
                                                href: `/exams/sessions/${session.id}/edit`,
                                                children: ($$renderer10) => {
                                                  Button($$renderer10, {
                                                    variant: "ghost",
                                                    size: "sm",
                                                    class: "h-8 px-2 text-xs font-semibold font-sans",
                                                    children: ($$renderer11) => {
                                                      Pencil($$renderer11, { class: "mr-1 size-3.5" });
                                                      $$renderer11.push(`<!----> Edit`);
                                                    },
                                                    $$slots: { default: true }
                                                  });
                                                },
                                                $$slots: { default: true }
                                              });
                                            } else {
                                              $$renderer9.push("<!--[-1-->");
                                            }
                                            $$renderer9.push(`<!--]--></div>`);
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
                                        colspan: 6,
                                        class: "py-12 text-center text-muted-foreground font-sans",
                                        children: ($$renderer9) => {
                                          $$renderer9.push(`<!---->No exam sessions yet. Create one to get started.`);
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
                if (sessions.length > 0) {
                  $$renderer5.push("<!--[0-->");
                  $$renderer5.push(`<div class="mb-3 flex items-center gap-2">`);
                  Checkbox($$renderer5, {
                    checked: isAllVisibleSelected(),
                    indeterminate: isSomeVisibleSelected(),
                    onCheckedChange: toggleAllVisible,
                    "aria-label": "Select all visible sessions"
                  });
                  $$renderer5.push(`<!----> <span class="text-sm text-muted-foreground">Select all visible sessions</span></div> <ul class="grid grid-cols-1 gap-5 text-xs md:grid-cols-2 xl:grid-cols-3" role="list"><!--[-->`);
                  const each_array_3 = ensure_array_like(sessions);
                  for (let $$index_3 = 0, $$length = each_array_3.length; $$index_3 < $$length; $$index_3++) {
                    let session = each_array_3[$$index_3];
                    const percent = occupancyPercent(session);
                    {
                      let selection = function($$renderer6) {
                        $$renderer6.push(`<input type="checkbox"${attr("aria-label", `Select session ${session.code}`)}${attr("checked", selectedSessionIds.includes(session.id), true)} class="size-4 shrink-0 rounded border-border accent-primary"/>`);
                      }, status = function($$renderer6) {
                        $$renderer6.push(`<div class="flex shrink-0 flex-col items-end gap-1">`);
                        Badge($$renderer6, {
                          variant: statusVariant(session.status),
                          class: "font-mono text-[10px] font-bold",
                          children: ($$renderer7) => {
                            $$renderer7.push(`<!---->${escape_html(statusLabel(session.status))}`);
                          },
                          $$slots: { default: true }
                        });
                        $$renderer6.push(`<!----> `);
                        Badge($$renderer6, {
                          variant: "outline",
                          class: "font-sans text-[10px]",
                          children: ($$renderer7) => {
                            $$renderer7.push(`<!---->${escape_html(bookingModeLabel(session.booking_mode))}`);
                          },
                          $$slots: { default: true }
                        });
                        $$renderer6.push(`<!----></div>`);
                      }, summary = function($$renderer6) {
                        $$renderer6.push(`<div class="space-y-1.5"><div class="font-mono text-[11px] font-semibold text-foreground">Occupancy: ${escape_html(session.assigned_count ?? 0)} / ${escape_html(session.room?.capacity ?? "—")} (${escape_html(percent)}%)</div> <div class="h-1.5 overflow-hidden rounded-full bg-muted" role="progressbar"${attr("aria-label", `Session occupancy for ${session.code}`)}${attr("aria-valuenow", Math.min(100, percent))} aria-valuemin="0" aria-valuemax="100"><div class="h-full rounded-full bg-emerald-500 transition-all"${attr_style(`width: ${stringify(Math.min(100, percent))}%`)}></div></div></div>`);
                      }, details = function($$renderer6) {
                        $$renderer6.push(`<div class="space-y-2.5"><div class="flex items-start gap-2">`);
                        Building_2($$renderer6, { class: "mt-0.5 size-3.5 shrink-0 text-muted-foreground" });
                        $$renderer6.push(`<!----> <div class="min-w-0"><div class="font-semibold text-foreground">${escape_html(session.room?.name ?? session.room?.code ?? "Room TBD")}</div> <div class="text-[11px] text-muted-foreground">${escape_html(session.room?.building ?? "Building not specified")}</div></div></div> <div class="text-[11px] text-muted-foreground">Capacity: ${escape_html(session.room?.capacity ?? "—")} seats</div> <div class="flex items-center gap-2 text-muted-foreground">`);
                        Calendar($$renderer6, { class: "size-3.5 shrink-0 text-primary" });
                        $$renderer6.push(`<!----> <span>${escape_html(formatDate(session.date))}</span></div> `);
                        if (session.start_time) {
                          $$renderer6.push("<!--[0-->");
                          $$renderer6.push(`<div class="flex items-center gap-2 text-muted-foreground">`);
                          Clock($$renderer6, { class: "size-3.5 shrink-0" });
                          $$renderer6.push(`<!----> <span class="font-mono">${escape_html(formatTimeRange(session.start_time, session.end_time))}</span></div>`);
                        } else {
                          $$renderer6.push("<!--[-1-->");
                        }
                        $$renderer6.push(`<!--]--></div>`);
                      }, actions = function($$renderer6) {
                        $$renderer6.push(`<div class="flex items-center gap-2">`);
                        Link($$renderer6, {
                          href: `/exams/sessions/${session.id}`,
                          class: "flex-1",
                          children: ($$renderer7) => {
                            Button($$renderer7, {
                              variant: "outline",
                              size: "sm",
                              class: "min-h-9 w-full font-semibold",
                              children: ($$renderer8) => {
                                Eye($$renderer8, { class: "mr-1.5 size-3.5" });
                                $$renderer8.push(`<!----> Show Roster`);
                              },
                              $$slots: { default: true }
                            });
                          },
                          $$slots: { default: true }
                        });
                        $$renderer6.push(`<!----> `);
                        if (session.status === "draft") {
                          $$renderer6.push("<!--[0-->");
                          Link($$renderer6, {
                            href: `/exams/sessions/${session.id}/edit`,
                            children: ($$renderer7) => {
                              Button($$renderer7, {
                                variant: "ghost",
                                size: "sm",
                                class: "min-h-9 font-semibold",
                                children: ($$renderer8) => {
                                  Pencil($$renderer8, { class: "mr-1 size-3.5" });
                                  $$renderer8.push(`<!----> Edit`);
                                },
                                $$slots: { default: true }
                              });
                            },
                            $$slots: { default: true }
                          });
                        } else {
                          $$renderer6.push("<!--[-1-->");
                        }
                        $$renderer6.push(`<!--]--></div>`);
                      };
                      OperationalCard($$renderer5, {
                        id: session.id,
                        title: formatHumanSchedule(session.date, session.start_time, session.end_time),
                        subtitle: `${session.room?.name ?? session.room?.code ?? "Room TBD"} · ${session.code}`,
                        selection,
                        status,
                        summary,
                        details,
                        actions,
                        $$slots: {
                          selection: true,
                          status: true,
                          summary: true,
                          details: true,
                          actions: true
                        }
                      });
                    }
                  }
                  $$renderer5.push(`<!--]--></ul>`);
                } else {
                  $$renderer5.push("<!--[-1-->");
                  $$renderer5.push(`<p class="py-12 text-center text-muted-foreground">No exam sessions yet. Create one to get started.</p>`);
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
          $$renderer4.push(`<!--]--></div> `);
          {
            let actions = function($$renderer5) {
              Button($$renderer5, {
                size: "sm",
                onclick: () => submitBulkAction("/exams/sessions/bulk-publish", { session_ids: selectedSessionIds }),
                children: ($$renderer6) => {
                  $$renderer6.push(`<!---->Bulk Publish`);
                },
                $$slots: { default: true }
              });
              $$renderer5.push(`<!----> `);
              Button($$renderer5, {
                size: "sm",
                variant: "outline",
                onclick: () => submitBulkAction("/exams/sessions/bulk-booking-mode", {
                  session_ids: selectedSessionIds,
                  booking_mode: "open_for_reservation"
                }),
                children: ($$renderer6) => {
                  $$renderer6.push(`<!---->Open Reservation`);
                },
                $$slots: { default: true }
              });
              $$renderer5.push(`<!----> `);
              Button($$renderer5, {
                size: "sm",
                variant: "outline",
                onclick: () => submitBulkAction("/exams/sessions/bulk-booking-mode", { session_ids: selectedSessionIds, booking_mode: "staff_only" }),
                children: ($$renderer6) => {
                  $$renderer6.push(`<!---->Staff Only`);
                },
                $$slots: { default: true }
              });
              $$renderer5.push(`<!----> `);
              Button($$renderer5, {
                size: "sm",
                variant: "destructive",
                onclick: () => cancelDialogOpen = true,
                children: ($$renderer6) => {
                  $$renderer6.push(`<!---->Bulk Cancel`);
                },
                $$slots: { default: true }
              });
              $$renderer5.push(`<!---->`);
            };
            BulkActionBar($$renderer4, {
              count: selectedSessionIds.length,
              onClear: () => selectedSessionIds = [],
              actions
            });
          }
          $$renderer4.push(`<!----> `);
          if (cancelDialogOpen) {
            $$renderer4.push("<!--[0-->");
            $$renderer4.push(`<div class="fixed inset-0 z-50 grid place-items-center bg-black/50 p-4" role="presentation"><section class="w-full max-w-lg space-y-4 rounded-xl border border-border bg-card p-5 shadow-xl" role="dialog" aria-modal="true" aria-labelledby="bulk-cancel-title" aria-describedby="bulk-cancel-description"><div><h2 id="bulk-cancel-title" class="text-lg font-semibold">Cancel selected sessions</h2> <p id="bulk-cancel-description" class="mt-1 text-sm text-muted-foreground">This will cancel ${escape_html(selectedSessionIds.length)} selected session${escape_html(selectedSessionIds.length === 1 ? "" : "s")}. Provide a reason to continue.</p></div> <label for="bulk-cancel-reason" class="block text-sm font-medium">Cancellation reason</label> <div class="flex flex-wrap gap-2" aria-label="Cancellation reason presets"><!--[-->`);
            const each_array_4 = ensure_array_like([
              "Weather / Typhoon Suspension",
              "Holiday Declaration",
              "Venue / Room Maintenance",
              "Administrative Rescheduling"
            ]);
            for (let $$index_4 = 0, $$length = each_array_4.length; $$index_4 < $$length; $$index_4++) {
              let preset = each_array_4[$$index_4];
              Button($$renderer4, {
                type: "button",
                variant: "outline",
                size: "sm",
                onclick: () => cancelReason = preset,
                children: ($$renderer5) => {
                  $$renderer5.push(`<!---->${escape_html(preset)}`);
                },
                $$slots: { default: true }
              });
            }
            $$renderer4.push(`<!--]--></div> <textarea id="bulk-cancel-reason" rows="4" required="" class="w-full rounded-md border border-input bg-background px-3 py-2 text-sm" placeholder="Enter the reason for cancellation">`);
            const $$body = escape_html(cancelReason);
            if ($$body) {
              $$renderer4.push(`${$$body}`);
            }
            $$renderer4.push(`</textarea> <div class="flex justify-end gap-2">`);
            Button($$renderer4, {
              type: "button",
              variant: "outline",
              onclick: () => {
                cancelDialogOpen = false;
                cancelReason = "";
              },
              children: ($$renderer5) => {
                $$renderer5.push(`<!---->Back`);
              },
              $$slots: { default: true }
            });
            $$renderer4.push(`<!----> `);
            Button($$renderer4, {
              type: "button",
              variant: "destructive",
              disabled: !cancelReason.trim(),
              onclick: submitBulkCancel,
              children: ($$renderer5) => {
                $$renderer5.push(`<!---->Confirm cancellation`);
              },
              $$slots: { default: true }
            });
            $$renderer4.push(`<!----></div></section></div>`);
          } else {
            $$renderer4.push("<!--[-1-->");
          }
          $$renderer4.push(`<!--]--> `);
          CohortAutoDistributeModal($$renderer4, {
            domain: "exam",
            academicCycleId: distribution?.active_cycle_id ?? filters?.academic_cycle_id,
            academicCycleName: cycles.find((c) => String(c.value) === String(distribution?.active_cycle_id ?? filters?.academic_cycle_id))?.label ?? "Current Academic Cycle",
            sessions,
            distribution,
            get open() {
              return autoDistributeOpen;
            },
            set open($$value) {
              autoDistributeOpen = $$value;
              $$settled = false;
            }
          });
          $$renderer4.push(`<!---->`);
        }
      });
    }
    do {
      $$settled = true;
      $$inner_renderer = $$renderer2.copy();
      $$render_inner($$inner_renderer);
    } while (!$$settled);
    $$renderer2.subsume($$inner_renderer);
    if ($$store_subs) unsubscribe_stores($$store_subs);
  });
}
export {
  Index as default
};
