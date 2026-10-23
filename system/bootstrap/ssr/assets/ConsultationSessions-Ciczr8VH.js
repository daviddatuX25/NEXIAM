import { h as head, e as escape_html, a as ensure_array_like, B as Button, b as attr, x as store_get, y as unsubscribe_stores, d as derived } from "../app.js";
import { S as StaffLayout, a as Send } from "./StaffLayout-CR-MZopa.js";
import { P as PageHeaderBar } from "./PageHeaderBar-DJhT5vOR.js";
import { S as SwitchableListView } from "./SwitchableListView-DHKJpEKr.js";
import { B as BulkActionBar } from "./BulkActionBar-CIsDAPcP.js";
import { C as Checkbox } from "./checkbox-D_0Lz-jK.js";
import { B as Badge } from "./badge-D9Vgs7xh.js";
import { D as Dialog, a as Dialog_content, b as Dialog_header, c as Dialog_title, d as Dialog_description } from "./dialog-description-D12L0QA9.js";
import { D as Dialog_footer } from "./dialog-footer-kNnwKT2h.js";
import "clsx";
import { I as Input } from "./input-BUZAZKIK.js";
import { S as Select, a as Select_trigger, b as Select_content, c as Select_item } from "./select-trigger-rRjpYIEn.js";
import { T as Table, a as Table_header, b as Table_row, c as Table_head, d as Table_body, e as Table_cell } from "./table-row-_ffWKrps.js";
import { T as Textarea } from "./textarea-Bv3Na1wO.js";
import { C as CohortAutoDistributeModal } from "./CohortAutoDistributeModal-yVYa-9cp.js";
import { E as EntityRowActions } from "./EntityRowActions-X8rVBAZZ.js";
import { b as bookingUnlocked } from "./booking-visibility-DG8T2RPN.js";
import { S as Search } from "./settings-C5xOKpV3.js";
import { S as Sparkles } from "./sparkles-DbQIaVG6.js";
import { L as Link } from "./Link-B7ks7rBK.js";
import { P as Plus } from "./plus-DjyPLC7A.js";
import { P as Pencil } from "./pencil-0tPPVae8.js";
import { L as Lock } from "./lock-DAA4kTLl.js";
import { L as Lock_open } from "./lock-open-5Nny5RV8.js";
import { C as Circle_check_big } from "./ToastManager-C7oO_4Jn.js";
import { B as Ban } from "./ban-CUQ_jw2o.js";
import { router } from "@inertiajs/core";
import "axios";
import "laravel-precognition";
import "tailwind-merge";
import "tailwind-variants";
import "./NotificationBell-Cgc59min.js";
import "./PreferencesMenu-CCPeta-5.js";
import "./Icon-Dal7PqKK.js";
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
import "./triangle-alert-CUvuB-HL.js";
import "./AvatarBadge-DQ5Qru9A.js";
import "./circle-alert-jWXJ7oC0.js";
import "./refresh-cw-CYdk8aE0.js";
import "./camera-C09Eyogi.js";
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
import "./x-CnxrMbMn.js";
import "./chevron-left-DCSOZwZl.js";
import "./roving-focus-group-BJDH9kUk.js";
import "./table-2-BLgeaPqt.js";
import "./layout-grid-CXJfSBqV.js";
import "./clone-BJz-lxsY.js";
import "./check-DK45Y9SJ.js";
import "./minus-Dqkhcxt7.js";
import "./chevron-up-lhASJLRD.js";
import "./previous.svelte-Ch7Tz-Mv.js";
import "./arrays-CkOR4vx_.js";
import "./date-utils-Bc5VPPy9.js";
import "./graduation-cap-Bc9amiZ0.js";
import "./zap-CyG8UtsE.js";
import "./loader-circle-_UEl10Lx.js";
import "./ellipsis-CPRDNN0k.js";
import "./circle-x-BQrhluGu.js";
import "./info-C3MCzlQW.js";
function ConsultationSessions($$renderer, $$props) {
  $$renderer.component(($$renderer2) => {
    var $$store_subs;
    let {
      page_state = "ready",
      sessions = [],
      filters = {},
      statuses = [],
      cycles = [],
      booking_modes = [],
      errors = {},
      distribution = {}
    } = $$props;
    let autoDistributeOpen = false;
    let viewMode = "responsive";
    let filterSearch = filters.search ?? "";
    let filterStatus = filters.status ?? "";
    let filterCycleId = filters.academic_cycle_id ? String(filters.academic_cycle_id) : "";
    let debounceTimer;
    let cancelDialogOpen = false;
    let cancelReason = "";
    let activeSession = null;
    let selectedSessionIds = [];
    let bulkCancelDialogOpen = false;
    let bulkCancelReason = "";
    let visibleSessionIds = derived(() => sessions.map((session) => session.id));
    let isAllVisibleSelected = derived(() => visibleSessionIds().length > 0 && visibleSessionIds().every((id) => selectedSessionIds.includes(id)));
    let isSomeVisibleSelected = derived(() => visibleSessionIds().some((id) => selectedSessionIds.includes(id)) && !isAllVisibleSelected());
    const draftCount = derived(() => (sessions ?? []).filter((s) => s.status === "draft").length);
    const publishedCount = derived(() => (sessions ?? []).filter((s) => s.status === "published").length);
    const openReservationCount = derived(() => (sessions ?? []).filter((s) => s.booking_mode === "open_for_reservation").length);
    const staffOnlyCount = derived(() => (sessions ?? []).filter((s) => s.booking_mode === "staff_only").length);
    const totalCapacity = derived(() => (sessions ?? []).reduce((acc, s) => acc + (s.capacity || 0), 0));
    const totalAssigned = derived(() => (sessions ?? []).reduce((acc, s) => acc + (s.assigned_count || 0), 0));
    const schedulingContext = derived(() => {
      const safetyKey = `${page_state}:${sessions?.length ?? 0}:${draftCount()}:${publishedCount()}:${selectedSessionIds.length}:${// fallback to raw value
      actionError() ?? "none"}`;
      return {
        entityKey: "consultation-scheduling-hub",
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
        totalAssigned: totalAssigned(),
        actionError: actionError()
      };
    });
    function toggleAllVisible() {
      selectedSessionIds = isAllVisibleSelected() ? selectedSessionIds.filter((id) => !visibleSessionIds().includes(id)) : [.../* @__PURE__ */ new Set([...selectedSessionIds, ...visibleSessionIds()])];
    }
    function submitBulkAction(url, payload) {
      router.post(url, payload, { onSuccess: () => selectedSessionIds = [] });
    }
    function submitBulkCancel() {
      submitBulkAction("/consultation/sessions/bulk-cancel", {
        session_ids: selectedSessionIds,
        reason: bulkCancelReason.trim()
      });
      bulkCancelDialogOpen = false;
    }
    function applyFilters() {
      router.get(
        "/consultation/sessions",
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
      const found = statuses.find((s) => s.value === value);
      if (found) return found.label;
      if (value === "draft") return "Draft";
      if (value === "published") return "Published";
      if (value === "in_progress") return "In progress";
      if (value === "completed") return "Completed";
      if (value === "cancelled") return "Cancelled";
      return value ?? "—";
    }
    function bookingModeLabel(mode) {
      const found = booking_modes.find((m) => m.value === mode);
      if (found) return found.label;
      if (mode === "open_for_reservation") return "Self-booking (students reserve)";
      if (mode === "staff_only") return "Staff-only (office assigns)";
      return mode ?? "—";
    }
    function formatScheduledAt(scheduledAt) {
      if (!scheduledAt) return "—";
      try {
        const d = new Date(scheduledAt.includes("T") ? scheduledAt : scheduledAt.replace(" ", "T"));
        if (!isNaN(d.getTime())) {
          return d.toLocaleDateString(void 0, {
            year: "numeric",
            month: "short",
            day: "numeric",
            hour: "numeric",
            minute: "2-digit"
          });
        }
      } catch {
      }
      return scheduledAt;
    }
    function publishSession(session) {
      router.post(`/consultation/sessions/${session.id}/publish`, { expected_version: session.version });
    }
    function openBooking(session) {
      router.post(`/consultation/sessions/${session.id}/open-reservation`, { expected_version: session.version });
    }
    function closeBooking(session) {
      router.post(`/consultation/sessions/${session.id}/close-reservation`, { expected_version: session.version });
    }
    function completeSession(session) {
      router.post(`/consultation/sessions/${session.id}/complete`, { expected_version: session.version });
    }
    function promptCancel(session) {
      activeSession = session;
      cancelReason = "";
      cancelDialogOpen = true;
    }
    function submitCancel() {
      if (!activeSession || !cancelReason.trim()) return;
      router.post(
        `/consultation/sessions/${activeSession.id}/cancel`,
        {
          expected_version: activeSession.version,
          reason: cancelReason.trim()
        },
        {
          onFinish: () => {
            cancelDialogOpen = false;
            cancelReason = "";
            activeSession = null;
          }
        }
      );
    }
    const breadcrumbs = [
      { label: "Counselling", href: "/consultation/sessions" },
      { label: "Consultation Scheduling" }
    ];
    const actionError = derived(() => errors?.publish || errors?.open_reservation || errors?.close_reservation || errors?.cancel || errors?.complete || errors?.unpublish || errors?.schedule || null);
    let $$settled = true;
    let $$inner_renderer;
    function $$render_inner($$renderer3) {
      head("tufqcj", $$renderer3, ($$renderer4) => {
        $$renderer4.title(($$renderer5) => {
          $$renderer5.push(`<title>Consultation Scheduling · NEXIAM</title>`);
        });
      });
      StaffLayout($$renderer3, {
        breadcrumbs,
        children: ($$renderer4) => {
          $$renderer4.push(`<div class="min-w-0 space-y-6 p-4 sm:p-6">`);
          {
            let actions = function($$renderer5) {
              $$renderer5.push(`<div class="flex flex-wrap items-center gap-2">`);
              Button($$renderer5, {
                type: "button",
                class: "min-h-10 gap-2 font-bold text-xs",
                onclick: () => autoDistributeOpen = true,
                "data-tour": "consultation-auto-distribute",
                children: ($$renderer6) => {
                  Sparkles($$renderer6, { class: "size-4" });
                  $$renderer6.push(`<!----> Auto-Distribute Examinees`);
                },
                $$slots: { default: true }
              });
              $$renderer5.push(`<!----> `);
              Link($$renderer5, {
                href: "/consultation/sessions/create",
                children: ($$renderer6) => {
                  Button($$renderer6, {
                    variant: "outline",
                    class: "min-h-10 gap-2",
                    "data-tour": "consultation-create-session",
                    children: ($$renderer7) => {
                      Plus($$renderer7, { class: "size-4" });
                      $$renderer7.push(`<!----> Create session`);
                    },
                    $$slots: { default: true }
                  });
                },
                $$slots: { default: true }
              });
              $$renderer5.push(`<!----></div>`);
            };
            PageHeaderBar($$renderer4, {
              title: "Consultation Scheduling",
              description: "Schedule post-exam guidance sessions, assign eligible candidates to consultation rooms, and publish session schedules.",
              tourKey: "consultation-scheduling",
              context: schedulingContext(),
              actions
            });
          }
          $$renderer4.push(`<!----> `);
          if (actionError()) {
            $$renderer4.push("<!--[0-->");
            $$renderer4.push(`<div class="border-destructive/40 bg-destructive/10 text-destructive rounded-md border px-4 py-3 text-sm">${escape_html(actionError())}</div>`);
          } else {
            $$renderer4.push("<!--[-1-->");
          }
          $$renderer4.push(`<!--]--> `);
          if (page_state === "denied") {
            $$renderer4.push("<!--[0-->");
            $$renderer4.push(`<p class="text-muted-foreground text-sm">You do not have permission to manage consultation sessions.</p>`);
          } else {
            $$renderer4.push("<!--[-1-->");
            $$renderer4.push(`<div class="flex flex-wrap items-end gap-3"><div><label class="text-muted-foreground mb-1 block text-xs font-medium" for="consultation-search">Search</label> <div class="relative">`);
            Search($$renderer4, {
              class: "text-muted-foreground pointer-events-none absolute top-1/2 left-2.5 size-4 -translate-y-1/2"
            });
            $$renderer4.push(`<!----> `);
            Input($$renderer4, {
              id: "consultation-search",
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
            $$renderer4.push(`<!----></div></div> <div><label class="text-muted-foreground mb-1 block text-xs font-medium" for="consultation-status">Status</label> `);
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
                      id: "consultation-status",
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
            $$renderer4.push(`</div> <div><label class="text-muted-foreground mb-1 block text-xs font-medium" for="consultation-cycle">Academic year</label> `);
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
                      id: "consultation-cycle",
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
                    class: "w-full min-w-205 text-sm",
                    "data-tour": "consultation-session-list",
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
                                      class: "px-4 py-3 font-semibold text-left",
                                      children: ($$renderer9) => {
                                        $$renderer9.push(`<!---->When`);
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
                                      class: "px-4 py-3 font-semibold text-left",
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
                                      class: "px-4 py-3 font-semibold text-left",
                                      children: ($$renderer9) => {
                                        $$renderer9.push(`<!---->Code`);
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
                                      class: "px-4 py-3 font-semibold text-center",
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
                                      class: "px-4 py-3 font-semibold text-right",
                                      children: ($$renderer9) => {
                                        $$renderer9.push(`<!---->Assigned`);
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
                                      class: "px-4 py-3 font-semibold text-right pr-6 sr-only",
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
                                    class: "hover:bg-muted/30 cursor-pointer",
                                    onclick: (e) => {
                                      if (e.target.closest("[data-action-cell]")) return;
                                      router.visit(`/consultation/sessions/${session.id}`);
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
                                          class: "px-4 py-3 font-sans text-xs text-foreground text-left",
                                          children: ($$renderer9) => {
                                            $$renderer9.push(`<!---->${escape_html(formatScheduledAt(session.scheduled_at))}`);
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
                                          class: "px-4 py-3 font-medium text-xs text-left",
                                          children: ($$renderer9) => {
                                            $$renderer9.push(`<!---->${escape_html(session.room?.name ?? session.room?.code ?? "—")}`);
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
                                          class: "px-4 py-3 font-mono font-bold text-xs text-left",
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
                                          class: "px-4 py-3 text-center",
                                          children: ($$renderer9) => {
                                            $$renderer9.push(`<div class="flex flex-col items-center gap-1">`);
                                            Badge($$renderer9, {
                                              variant: statusVariant(session.status),
                                              class: "font-mono text-xs font-bold",
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
                                                class: "text-[10px] text-primary border-primary/30",
                                                children: ($$renderer10) => {
                                                  $$renderer10.push(`<!---->Self-booking`);
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
                                          class: "px-4 py-3 text-right font-mono tabular-nums font-semibold",
                                          children: ($$renderer9) => {
                                            $$renderer9.push(`<!---->${escape_html(session.assigned_count ?? 0)}`);
                                            if (session.room?.capacity) {
                                              $$renderer9.push("<!--[0-->");
                                              $$renderer9.push(`/ ${escape_html(session.room.capacity)}`);
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
                                          class: "px-4 py-3 text-right pr-6",
                                          "data-action-cell": true,
                                          children: ($$renderer9) => {
                                            EntityRowActions($$renderer9, {
                                              primaryAction: {
                                                label: "Roster",
                                                href: `/consultation/sessions/${session.id}`,
                                                variant: "outline"
                                              },
                                              overflowItems: [
                                                session.status === "draft" ? {
                                                  label: "Edit Session",
                                                  href: `/consultation/sessions/${session.id}/edit`,
                                                  icon: Pencil
                                                } : null,
                                                session.status === "draft" && (session.assigned_count ?? 0) > 0 ? {
                                                  label: "Publish Session",
                                                  onclick: () => publishSession(session),
                                                  icon: Send
                                                } : null,
                                                session.status !== "completed" && session.status !== "cancelled" && (session.booking_mode === "open_for_reservation" || store_get($$store_subs ??= {}, "$bookingUnlocked", bookingUnlocked)) ? {
                                                  label: session.booking_mode === "open_for_reservation" ? "Close Booking" : "Open Booking",
                                                  onclick: () => session.booking_mode === "open_for_reservation" ? closeBooking(session) : openBooking(session),
                                                  icon: session.booking_mode === "open_for_reservation" ? Lock : Lock_open
                                                } : null,
                                                session.status === "published" || session.status === "in_progress" ? {
                                                  label: "Complete Session",
                                                  onclick: () => completeSession(session),
                                                  icon: Circle_check_big
                                                } : null,
                                                session.status !== "completed" && session.status !== "cancelled" ? {
                                                  label: "Cancel Session",
                                                  onclick: () => promptCancel(session),
                                                  destructive: true,
                                                  icon: Ban
                                                } : null
                                              ],
                                              dense: true
                                            });
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
                                        colspan: 7,
                                        children: ($$renderer9) => {
                                          $$renderer9.push(`<!---->No consultation sessions yet. Create one
                                        to get started.`);
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
                  $$renderer5.push(`<!----> <span class="text-muted-foreground text-sm">Select all visible sessions</span></div> <ul class="grid gap-3 sm:grid-cols-2 lg:grid-cols-3" role="list" data-tour="consultation-session-list"><!--[-->`);
                  const each_array_3 = ensure_array_like(sessions);
                  for (let $$index_3 = 0, $$length = each_array_3.length; $$index_3 < $$length; $$index_3++) {
                    let session = each_array_3[$$index_3];
                    $$renderer5.push(`<li class="border-border bg-card flex flex-col gap-3 rounded-xl border p-4 shadow-sm"><div class="flex items-start justify-between gap-2"><div class="min-w-0"><div class="flex items-center gap-2"><input type="checkbox"${attr("aria-label", `Select session ${session.code}`)}${attr("checked", selectedSessionIds.includes(session.id), true)} class="size-4 shrink-0 rounded border-border accent-primary"/> <p class="truncate font-semibold tracking-tight">${escape_html(session.code)}</p></div> <p class="text-muted-foreground text-sm">${escape_html(formatScheduledAt(session.scheduled_at))}</p> <p class="text-muted-foreground text-sm">${escape_html(session.room?.name ?? session.room?.code ?? "—")}</p></div> <div class="flex shrink-0 flex-col items-end gap-1">`);
                    Badge($$renderer5, {
                      variant: statusVariant(session.status),
                      children: ($$renderer6) => {
                        $$renderer6.push(`<!---->${escape_html(statusLabel(session.status))}`);
                      },
                      $$slots: { default: true }
                    });
                    $$renderer5.push(`<!----> `);
                    if (session.booking_mode === "open_for_reservation") {
                      $$renderer5.push("<!--[0-->");
                      Badge($$renderer5, {
                        variant: "outline",
                        class: "text-[10px] text-primary border-primary/30",
                        children: ($$renderer6) => {
                          $$renderer6.push(`<!---->Self-booking`);
                        },
                        $$slots: { default: true }
                      });
                    } else if (store_get($$store_subs ??= {}, "$bookingUnlocked", bookingUnlocked)) {
                      $$renderer5.push("<!--[1-->");
                      Badge($$renderer5, {
                        variant: "outline",
                        class: "text-[10px]",
                        children: ($$renderer6) => {
                          $$renderer6.push(`<!---->${escape_html(bookingModeLabel(session.booking_mode))}`);
                        },
                        $$slots: { default: true }
                      });
                    } else {
                      $$renderer5.push("<!--[-1-->");
                    }
                    $$renderer5.push(`<!--]--></div></div> <p class="text-muted-foreground text-sm">Assigned ${escape_html(session.assigned_count ?? 0)}`);
                    if (session.room?.capacity) {
                      $$renderer5.push("<!--[0-->");
                      $$renderer5.push(`/ ${escape_html(session.room.capacity)}`);
                    } else {
                      $$renderer5.push("<!--[-1-->");
                    }
                    $$renderer5.push(`<!--]--></p> <div class="mt-auto pt-3 border-t border-border/40 flex items-center justify-between" role="presentation"><span class="text-xs text-muted-foreground font-mono">${escape_html(session.assigned_count ?? 0)}`);
                    if (session.room?.capacity) {
                      $$renderer5.push("<!--[0-->");
                      $$renderer5.push(`/ ${escape_html(session.room.capacity)} seats`);
                    } else {
                      $$renderer5.push("<!--[-1-->");
                    }
                    $$renderer5.push(`<!--]--></span> `);
                    EntityRowActions($$renderer5, {
                      primaryAction: {
                        label: "Review Roster",
                        href: `/consultation/sessions/${session.id}`,
                        variant: "outline"
                      },
                      overflowItems: [
                        session.status === "draft" ? {
                          label: "Edit Session",
                          href: `/consultation/sessions/${session.id}/edit`,
                          icon: Pencil
                        } : null,
                        session.status === "draft" && (session.assigned_count ?? 0) > 0 ? {
                          label: "Publish Session",
                          onclick: () => publishSession(session),
                          icon: Send
                        } : null,
                        session.status !== "completed" && session.status !== "cancelled" ? {
                          label: session.booking_mode === "open_for_reservation" ? "Close Booking" : "Open Booking",
                          onclick: () => session.booking_mode === "open_for_reservation" ? closeBooking(session) : openBooking(session),
                          icon: session.booking_mode === "open_for_reservation" ? Lock : Lock_open
                        } : null,
                        session.status === "published" || session.status === "in_progress" ? {
                          label: "Complete Session",
                          onclick: () => completeSession(session),
                          icon: Circle_check_big
                        } : null,
                        session.status !== "completed" && session.status !== "cancelled" ? {
                          label: "Cancel Session",
                          onclick: () => promptCancel(session),
                          destructive: true,
                          icon: Ban
                        } : null
                      ]
                    });
                    $$renderer5.push(`<!----></div></li>`);
                  }
                  $$renderer5.push(`<!--]--></ul>`);
                } else {
                  $$renderer5.push("<!--[-1-->");
                  $$renderer5.push(`<p class="text-muted-foreground py-12 text-center">No consultation sessions yet. Create one to get
                            started.</p>`);
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
            $$renderer4.push(`<!---->`);
          }
          $$renderer4.push(`<!--]--> `);
          if (selectedSessionIds.length > 0) {
            $$renderer4.push("<!--[0-->");
            {
              let actions = function($$renderer5) {
                Button($$renderer5, {
                  size: "sm",
                  onclick: () => submitBulkAction("/consultation/sessions/bulk-publish", { session_ids: selectedSessionIds }),
                  children: ($$renderer6) => {
                    $$renderer6.push(`<!---->Bulk Publish`);
                  },
                  $$slots: { default: true }
                });
                $$renderer5.push(`<!----> `);
                Button($$renderer5, {
                  size: "sm",
                  variant: "outline",
                  onclick: () => submitBulkAction("/consultation/sessions/bulk-booking-mode", {
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
                  onclick: () => submitBulkAction("/consultation/sessions/bulk-booking-mode", { session_ids: selectedSessionIds, booking_mode: "staff_only" }),
                  children: ($$renderer6) => {
                    $$renderer6.push(`<!---->Staff Only`);
                  },
                  $$slots: { default: true }
                });
                $$renderer5.push(`<!----> `);
                Button($$renderer5, {
                  size: "sm",
                  variant: "destructive",
                  onclick: () => bulkCancelDialogOpen = true,
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
          } else {
            $$renderer4.push("<!--[-1-->");
          }
          $$renderer4.push(`<!--]--></div> `);
          if (Dialog) {
            $$renderer4.push("<!--[-->");
            Dialog($$renderer4, {
              get open() {
                return cancelDialogOpen;
              },
              set open($$value) {
                cancelDialogOpen = $$value;
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
                                  $$renderer8.push(`<!---->Cancel consultation session`);
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
                                  $$renderer8.push(`<!---->Terminal cancel. Frees active assignments and open
                    reservations. Requires a reason.`);
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
                      Textarea($$renderer6, {
                        rows: 3,
                        placeholder: "Reason for cancellation…",
                        get value() {
                          return cancelReason;
                        },
                        set value($$value) {
                          cancelReason = $$value;
                          $$settled = false;
                        }
                      });
                      $$renderer6.push(`<!----> `);
                      if (Dialog_footer) {
                        $$renderer6.push("<!--[-->");
                        Dialog_footer($$renderer6, {
                          children: ($$renderer7) => {
                            Button($$renderer7, {
                              type: "button",
                              variant: "outline",
                              onclick: () => cancelDialogOpen = false,
                              children: ($$renderer8) => {
                                $$renderer8.push(`<!---->Back`);
                              },
                              $$slots: { default: true }
                            });
                            $$renderer7.push(`<!----> `);
                            Button($$renderer7, {
                              type: "button",
                              variant: "destructive",
                              disabled: !cancelReason.trim(),
                              onclick: submitCancel,
                              children: ($$renderer8) => {
                                $$renderer8.push(`<!---->Confirm cancellation`);
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
          $$renderer4.push(` `);
          if (Dialog) {
            $$renderer4.push("<!--[-->");
            Dialog($$renderer4, {
              get open() {
                return bulkCancelDialogOpen;
              },
              set open($$value) {
                bulkCancelDialogOpen = $$value;
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
                                  $$renderer8.push(`<!---->Cancel selected consultation sessions`);
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
                                  $$renderer8.push(`<!---->This will cancel ${escape_html(selectedSessionIds.length)} selected
                    session(s). Provide a reason to continue.`);
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
                      $$renderer6.push(` <div class="flex flex-wrap gap-2"><!--[-->`);
                      const each_array_4 = ensure_array_like([
                        "Weather / Typhoon Suspension",
                        "Holiday Declaration",
                        "Counselor On Leave / Unavailable",
                        "Administrative Rescheduling"
                      ]);
                      for (let $$index_4 = 0, $$length = each_array_4.length; $$index_4 < $$length; $$index_4++) {
                        let reason = each_array_4[$$index_4];
                        Button($$renderer6, {
                          type: "button",
                          size: "sm",
                          variant: "outline",
                          onclick: () => bulkCancelReason = reason,
                          children: ($$renderer7) => {
                            $$renderer7.push(`<!---->${escape_html(reason)}`);
                          },
                          $$slots: { default: true }
                        });
                      }
                      $$renderer6.push(`<!--]--></div> `);
                      Textarea($$renderer6, {
                        rows: 3,
                        placeholder: "Reason for cancellation…",
                        get value() {
                          return bulkCancelReason;
                        },
                        set value($$value) {
                          bulkCancelReason = $$value;
                          $$settled = false;
                        }
                      });
                      $$renderer6.push(`<!----> `);
                      if (Dialog_footer) {
                        $$renderer6.push("<!--[-->");
                        Dialog_footer($$renderer6, {
                          children: ($$renderer7) => {
                            Button($$renderer7, {
                              type: "button",
                              variant: "outline",
                              onclick: () => bulkCancelDialogOpen = false,
                              children: ($$renderer8) => {
                                $$renderer8.push(`<!---->Back`);
                              },
                              $$slots: { default: true }
                            });
                            $$renderer7.push(`<!----> `);
                            Button($$renderer7, {
                              type: "button",
                              variant: "destructive",
                              disabled: !bulkCancelReason.trim(),
                              onclick: submitBulkCancel,
                              children: ($$renderer8) => {
                                $$renderer8.push(`<!---->Confirm cancellation`);
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
          $$renderer4.push(` `);
          CohortAutoDistributeModal($$renderer4, {
            domain: "consultation",
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
  ConsultationSessions as default
};
