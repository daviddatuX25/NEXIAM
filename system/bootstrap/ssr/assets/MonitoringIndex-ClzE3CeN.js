import { g as sanitize_props, i as spread_props, j as slot, d as derived, h as head, e as escape_html, c as attr_class, a as ensure_array_like, b as attr, B as Button } from "../app.js";
import { S as StaffLayout, C as Calendar } from "./StaffLayout-CR-MZopa.js";
import { H as HeaderActions } from "./HeaderActions-Dxxt_yha.js";
import { T as TourTriggerGlyph, X as XiamNpcDialog } from "./XiamNpcDialog-DoqCSHj9.js";
import { S as SwitchableListView } from "./SwitchableListView-DHKJpEKr.js";
import { O as OperationalCard } from "./OperationalCard-D2Z4nRzj.js";
import { S as SimplePagination } from "./SimplePagination-Bcq5R_Kb.js";
import { B as Badge } from "./badge-D9Vgs7xh.js";
import { I as Input } from "./input-BUZAZKIK.js";
import { T as Table, a as Table_header, b as Table_row, c as Table_head, d as Table_body, e as Table_cell } from "./table-row-_ffWKrps.js";
import "clsx";
import { e as Popover, f as Popover_trigger, h as Popover_content } from "./PreferencesMenu-CCPeta-5.js";
import { f as formatDate, a as formatTimeRange } from "./date-utils-Bc5VPPy9.js";
import { u as usePoll } from "./NotificationBell-Cgc59min.js";
import { I as Icon } from "./Icon-Dal7PqKK.js";
import { U as Users } from "./users-DWlguTa_.js";
import { C as Circle_check } from "./circle-check-C59ZS7Zv.js";
import { U as User_x } from "./user-x-D3BlNLVK.js";
import { T as Triangle_alert } from "./triangle-alert-CUvuB-HL.js";
import { A as Activity, S as Search } from "./settings-C5xOKpV3.js";
import { C as Clock } from "./clock-B4fXoAi9.js";
import { X } from "./x-CnxrMbMn.js";
import { L as Link } from "./Link-B7ks7rBK.js";
import { C as Check } from "./check-DK45Y9SJ.js";
import { E as Eye } from "./eye-DyG-RiwW.js";
import { S as Settings_2 } from "./settings-2-B2lTvmrM.js";
import { I as Inbox } from "./inbox-AmUVtgA2.js";
import { E as External_link } from "./external-link-DmB8ndP3.js";
import { L as Layers } from "./layers-_Ie6y7G_.js";
import { router } from "@inertiajs/core";
import "axios";
import "laravel-precognition";
import "tailwind-merge";
import "tailwind-variants";
import "./ToastManager-C7oO_4Jn.js";
import "./circle-x-BQrhluGu.js";
import "./info-C3MCzlQW.js";
import "./AvatarBadge-DQ5Qru9A.js";
import "./dialog-description-D12L0QA9.js";
import "./noop-BQS7OFs8.js";
import "./create-id-CUJmDlea.js";
import "style-to-object";
import "./scroll-lock-B1ibcKMx.js";
import "./watch.svelte-BcPoqckX.js";
import "./is-C6cm64pF.js";
import "tabbable";
import "./circle-alert-jWXJ7oC0.js";
import "./refresh-cw-CYdk8aE0.js";
import "./camera-C09Eyogi.js";
import "./arrow-right-CzynHiIo.js";
import "./dialog-footer-kNnwKT2h.js";
import "./InstitutionalBackdrop-BjxeRqDu.js";
import "./qr-code-C5X8qcH6.js";
import "./chevron-right-BHu-PGOg.js";
import "./chevron-down-CkQQZIhu.js";
import "./printer-Bx0z1CRb.js";
import "./book-open-B6eMguqY.js";
import "./megaphone-C8QUuT5s.js";
import "./file-check-BSLEMs1I.js";
import "./shield-DTtuUihb.js";
import "./clipboard-list-YgthURdB.js";
import "./user-round-Kft0Cy_Y.js";
/* empty css                                                        */
import "./circle-question-mark-DY-RU614.js";
import "./sparkles-DbQIaVG6.js";
import "./chevron-left-DCSOZwZl.js";
import "./roving-focus-group-BJDH9kUk.js";
import "./table-2-BLgeaPqt.js";
import "./layout-grid-CXJfSBqV.js";
import "@floating-ui/dom";
import "./hidden-input-GSZDfM3n.js";
import "./zoom-out-DsJCISKt.js";
import "./rotate-ccw-D6Dm_efx.js";
import "./mail-DBtkjaCt.js";
function School($$renderer, $$props) {
  const $$sanitized_props = sanitize_props($$props);
  const iconNode = [
    ["path", { "d": "M14 21v-3a2 2 0 0 0-4 0v3" }],
    ["path", { "d": "M18 5v16" }],
    ["path", { "d": "m4 6 7.106-3.79a2 2 0 0 1 1.788 0L20 6" }],
    [
      "path",
      {
        "d": "m6 11-3.52 2.147a1 1 0 0 0-.48.854V19a2 2 0 0 0 2 2h16a2 2 0 0 0 2-2v-5a1 1 0 0 0-.48-.853L18 11"
      }
    ],
    ["path", { "d": "M6 5v16" }],
    ["circle", { "cx": "12", "cy": "9", "r": "2" }]
  ];
  Icon($$renderer, spread_props([
    { name: "school" },
    $$sanitized_props,
    {
      /**
       * @component @name School
       * @description Lucide SVG icon component, renders SVG Element with children.
       *
       * @preview ![img](data:image/svg+xml;base64,PHN2ZyAgeG1sbnM9Imh0dHA6Ly93d3cudzMub3JnLzIwMDAvc3ZnIgogIHdpZHRoPSIyNCIKICBoZWlnaHQ9IjI0IgogIHZpZXdCb3g9IjAgMCAyNCAyNCIKICBmaWxsPSJub25lIgogIHN0cm9rZT0iIzAwMCIgc3R5bGU9ImJhY2tncm91bmQtY29sb3I6ICNmZmY7IGJvcmRlci1yYWRpdXM6IDJweCIKICBzdHJva2Utd2lkdGg9IjIiCiAgc3Ryb2tlLWxpbmVjYXA9InJvdW5kIgogIHN0cm9rZS1saW5lam9pbj0icm91bmQiCj4KICA8cGF0aCBkPSJNMTQgMjF2LTNhMiAyIDAgMCAwLTQgMHYzIiAvPgogIDxwYXRoIGQ9Ik0xOCA1djE2IiAvPgogIDxwYXRoIGQ9Im00IDYgNy4xMDYtMy43OWEyIDIgMCAwIDEgMS43ODggMEwyMCA2IiAvPgogIDxwYXRoIGQ9Im02IDExLTMuNTIgMi4xNDdhMSAxIDAgMCAwLS40OC44NTRWMTlhMiAyIDAgMCAwIDIgMmgxNmEyIDIgMCAwIDAgMi0ydi01YTEgMSAwIDAgMC0uNDgtLjg1M0wxOCAxMSIgLz4KICA8cGF0aCBkPSJNNiA1djE2IiAvPgogIDxjaXJjbGUgY3g9IjEyIiBjeT0iOSIgcj0iMiIgLz4KPC9zdmc+Cg==) - https://lucide.dev/icons/school
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
function MonitoringIndex($$renderer, $$props) {
  $$renderer.component(($$renderer2) => {
    let {
      page_state = "ready",
      sessions = [],
      active_sessions = [],
      history_sessions = null,
      filters = {},
      rooms = [],
      can_manage = false,
      telemetry = null,
      thresholds = {}
    } = $$props;
    let tourOpen = false;
    let expandedCards = {};
    const activeList = derived(() => active_sessions?.length > 0 ? active_sessions : sessions ?? []);
    const historyList = derived(() => history_sessions?.data ?? (Array.isArray(history_sessions) ? history_sessions : []));
    const historyTotal = derived(() => history_sessions?.total ?? historyList().length);
    let activeTab = filters?.tab ? filters.tab : activeList().length > 0 ? "active" : historyTotal() > 0 ? "history" : "active";
    function selectTab(tab) {
      activeTab = tab;
      const url = new URL(window.location.href);
      url.searchParams.set("tab", tab);
      window.history.replaceState({}, "", url.toString());
    }
    let searchTerm = filters.search ?? "";
    let selectedRoomId = filters.room_id ? String(filters.room_id) : "";
    let selectedDate = filters.date ?? "";
    let debounceTimer;
    function applyFilters() {
      clearTimeout(debounceTimer);
      debounceTimer = setTimeout(
        () => {
          router.get(
            "/exams/monitoring",
            {
              search: searchTerm || void 0,
              room_id: selectedRoomId || void 0,
              date: selectedDate || void 0,
              tab: activeTab
            },
            { preserveState: true, preserveScroll: true, replace: true }
          );
        },
        300
      );
    }
    function clearFilters() {
      searchTerm = "";
      selectedRoomId = "";
      selectedDate = "";
      router.get("/exams/monitoring", { tab: activeTab }, { preserveState: true, preserveScroll: true, replace: true });
    }
    const hasActiveFilters = derived(() => Boolean(searchTerm || selectedRoomId || selectedDate));
    usePoll(
      15e3,
      {
        only: [
          "sessions",
          "active_sessions",
          "history_sessions",
          "telemetry",
          "page_state",
          "can_manage",
          "thresholds"
        ]
      },
      { autoStart: page_state !== "denied", keepAlive: false }
    );
    function statusVariant(status) {
      if (status === "published") return "default";
      if (status === "in_progress") return "outline";
      if (status === "completed") return "secondary";
      if (status === "cancelled") return "destructive";
      return "outline";
    }
    function statusLabel(status) {
      if (status === "published") return "Published";
      if (status === "in_progress") return "In Progress";
      if (status === "completed") return "Completed";
      if (status === "cancelled") return "Cancelled";
      return status;
    }
    function monitoringCardStatusLabel(status) {
      if (status === "in_progress") return "LIVE";
      if (status === "published") return "STANDBY";
      if (status === "completed") return "Completed";
      if (status === "cancelled") return "Cancelled";
      return status;
    }
    function monitoringCardStatusVariant(status) {
      if (status === "in_progress") return "default";
      if (status === "published") return "outline";
      return statusVariant(status);
    }
    function hasUnrecordedAttendance(session) {
      if (session.status === "cancelled") return false;
      if (session.submission_status === "unrecorded_attendance") return true;
      const hasAttendanceData = session.present_count != null || session.absent_count != null || session.void_count != null;
      if (!hasAttendanceData) return false;
      const recordedCount = Number(session.present_count ?? 0) + Number(session.absent_count ?? 0) + Number(session.void_count ?? 0);
      return Number(session.assigned_count ?? 0) > recordedCount;
    }
    function formatTime(isoString) {
      if (!isoString) return "—";
      try {
        const d = new Date(isoString);
        return d.toLocaleTimeString([], { hour: "2-digit", minute: "2-digit" });
      } catch {
        return "—";
      }
    }
    const scheduledForAbsence = derived(() => telemetry?.conduct_scheduled ?? telemetry?.total_scheduled ?? 0);
    const absencePercent = derived(() => telemetry && scheduledForAbsence() > 0 ? Math.round(telemetry.absent_count / scheduledForAbsence() * 100) : 0);
    const absenceWarning = derived(() => absencePercent() >= (thresholds.absence_warning_threshold_percent ?? 20));
    const monitoringContext = derived(() => {
      const isLv = Boolean(telemetry?.is_live);
      const safetyKey = `${page_state}:${isLv ? "live" : "stdby"}:${absenceWarning() ? "abswarn" : "ok"}:${activeList().length}:${historyTotal()}:${telemetry?.present_count ?? 0}:${telemetry?.submitted_count ?? 0}`;
      return {
        entityKey: "exam-monitoring",
        safetyKey,
        freshness: "current",
        pageState: page_state,
        isLive: isLv,
        sessionsCount: activeList().length,
        historyCount: historyTotal(),
        activeRoomsCount: telemetry?.active_rooms ?? 0,
        totalRoomsCount: telemetry?.total_rooms ?? 0,
        totalScheduled: telemetry?.total_scheduled ?? 0,
        presentCount: telemetry?.present_count ?? 0,
        absentCount: telemetry?.absent_count ?? 0,
        submittedCount: telemetry?.submitted_count ?? 0,
        absencePercent: absencePercent(),
        absenceWarning: absenceWarning(),
        canManage: Boolean(can_manage)
      };
    });
    const breadcrumbs = [{ label: "Exam Monitoring" }];
    let $$settled = true;
    let $$inner_renderer;
    function $$render_inner($$renderer3) {
      head("12so871", $$renderer3, ($$renderer4) => {
        $$renderer4.title(($$renderer5) => {
          $$renderer5.push(`<title>Exam Monitoring</title>`);
        });
      });
      StaffLayout($$renderer3, {
        breadcrumbs,
        children: ($$renderer4) => {
          $$renderer4.push(`<div class="min-w-0 space-y-6 p-4 sm:p-6"><div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 pb-2 border-b border-border/60"><div><div class="flex items-center gap-3"><h1 class="text-2xl sm:text-3xl font-bold tracking-tight text-foreground">Exam Monitoring</h1> `);
          TourTriggerGlyph($$renderer4, { tourKey: "exam-monitoring", onclick: () => tourOpen = true });
          $$renderer4.push(`<!----> `);
          if (telemetry?.is_live) {
            $$renderer4.push("<!--[0-->");
            $$renderer4.push(`<span class="inline-flex items-center gap-1.5 px-2.5 py-0.5 rounded-full bg-emerald-500/10 text-emerald-600 dark:text-emerald-400 border border-emerald-500/20 text-xs font-mono font-bold motion-safe:animate-pulse"><span class="h-2 w-2 rounded-full bg-emerald-500"></span> LIVE</span>`);
          } else {
            $$renderer4.push("<!--[-1-->");
            $$renderer4.push(`<span class="inline-flex items-center gap-1.5 px-2.5 py-0.5 rounded-full bg-muted text-muted-foreground border border-border text-xs font-mono font-semibold">STANDBY</span>`);
          }
          $$renderer4.push(`<!--]--></div> <p class="text-xs text-muted-foreground mt-1">Testing-day conduct hub: live room status, examinee attendance intake, and session reconciliation.</p></div> <div class="flex items-center gap-3">`);
          {
            let primaryAction = function($$renderer5) {
              if (Popover) {
                $$renderer5.push("<!--[-->");
                Popover($$renderer5, {
                  children: ($$renderer6) => {
                    {
                      let child = function($$renderer7, { props }) {
                        Button($$renderer7, spread_props([
                          props,
                          {
                            variant: "outline",
                            size: "sm",
                            class: "h-8 gap-1.5 text-xs font-mono font-medium hover:bg-muted transition-colors",
                            title: "Conduct Policies & Cadence",
                            children: ($$renderer8) => {
                              Settings_2($$renderer8, { class: "size-3.5" });
                              $$renderer8.push(`<!----> <span>Conduct Policies</span>`);
                            },
                            $$slots: { default: true }
                          }
                        ]));
                      };
                      if (Popover_trigger) {
                        $$renderer6.push("<!--[-->");
                        Popover_trigger($$renderer6, { child, $$slots: { child: true } });
                        $$renderer6.push("<!--]-->");
                      } else {
                        $$renderer6.push("<!--[!-->");
                        $$renderer6.push("<!--]-->");
                      }
                    }
                    $$renderer6.push(` `);
                    if (Popover_content) {
                      $$renderer6.push("<!--[-->");
                      Popover_content($$renderer6, {
                        class: "w-84 sm:w-96 p-4 text-xs font-mono space-y-4",
                        align: "end",
                        children: ($$renderer7) => {
                          $$renderer7.push(`<div class="space-y-1 border-b border-border/60 pb-2"><div class="flex items-center justify-between"><h3 class="font-semibold text-foreground text-sm flex items-center gap-1.5">`);
                          Settings_2($$renderer7, { class: "size-4 text-primary" });
                          $$renderer7.push(`<!----> <span>Conduct Policies</span></h3> `);
                          Badge($$renderer7, {
                            variant: "outline",
                            class: "text-[10px] font-mono",
                            children: ($$renderer8) => {
                              $$renderer8.push(`<!---->${escape_html(thresholds.cycle_label ?? "Active Cycle")}`);
                            },
                            $$slots: { default: true }
                          });
                          $$renderer7.push(`<!----></div> <p class="text-[11px] text-muted-foreground font-sans">Campus testing-day thresholds and communication cadence.</p></div> <div class="space-y-2"><div class="text-[10px] uppercase font-bold tracking-wider text-muted-foreground">Testing-Day Conduct Rules</div> <div class="grid grid-cols-3 gap-2"><div class="p-2.5 rounded-lg border border-border bg-muted/30"><div class="text-[10px] text-muted-foreground">Check-in Lead</div> <div class="text-sm font-bold text-foreground mt-0.5">${escape_html(thresholds.door_checkin_lead_minutes ?? 30)}m</div> <div class="text-[9px] text-muted-foreground mt-0.5 font-sans">Door opens early</div></div> <div class="p-2.5 rounded-lg border border-border bg-muted/30"><div class="text-[10px] text-muted-foreground">Auto-Close</div> <div class="text-sm font-bold text-foreground mt-0.5">${escape_html(thresholds.auto_close_grace_minutes ?? 15)}m</div> <div class="text-[9px] text-muted-foreground mt-0.5 font-sans">Grace after end</div></div> <div class="p-2.5 rounded-lg border border-border bg-muted/30"><div class="text-[10px] text-muted-foreground">Absence Alert</div> <div class="text-sm font-bold text-foreground mt-0.5">≥ ${escape_html(thresholds.absence_warning_threshold_percent ?? 20)}%</div> <div class="text-[9px] text-muted-foreground mt-0.5 font-sans">Triggers alert</div></div></div></div> <div class="space-y-2 border-t border-border/60 pt-3"><div class="flex items-center justify-between"><span class="text-[10px] uppercase font-bold tracking-wider text-muted-foreground">Candidate Reminders</span> <span class="text-[10px] text-muted-foreground">Cycle default</span></div> <div class="p-2.5 rounded-lg border border-border bg-muted/30 space-y-1"><div class="font-bold text-foreground">Offsets: ${escape_html((thresholds.reminder_windows ?? ["72h", "24h", "2h", "1h"]).join(", "))}</div> <p class="text-[10px] text-muted-foreground font-sans leading-tight">Dispatched before scheduled start. Reminder policies freeze when an exam session is published.</p></div></div> `);
                          if (thresholds.notifications_setup_url || thresholds.cycle_notifications_url) {
                            $$renderer7.push("<!--[0-->");
                            $$renderer7.push(`<div class="border-t border-border/60 pt-3 space-y-1.5 font-sans">`);
                            if (thresholds.notifications_setup_url) {
                              $$renderer7.push("<!--[0-->");
                              Link($$renderer7, {
                                href: thresholds.notifications_setup_url,
                                class: "flex items-center justify-between p-2 rounded-lg hover:bg-muted/50 text-xs font-medium text-foreground transition-colors group",
                                children: ($$renderer8) => {
                                  $$renderer8.push(`<div class="flex items-center gap-2">`);
                                  Inbox($$renderer8, { class: "size-3.5 text-primary" });
                                  $$renderer8.push(`<!----> <span>Notification Operations Hub</span></div> `);
                                  External_link($$renderer8, {
                                    class: "size-3 text-muted-foreground group-hover:text-foreground transition-colors"
                                  });
                                  $$renderer8.push(`<!---->`);
                                },
                                $$slots: { default: true }
                              });
                            } else {
                              $$renderer7.push("<!--[-1-->");
                            }
                            $$renderer7.push(`<!--]--> `);
                            if (thresholds.cycle_notifications_url) {
                              $$renderer7.push("<!--[0-->");
                              Link($$renderer7, {
                                href: thresholds.cycle_notifications_url,
                                class: "flex items-center justify-between p-2 rounded-lg hover:bg-muted/50 text-xs font-medium text-foreground transition-colors group",
                                children: ($$renderer8) => {
                                  $$renderer8.push(`<div class="flex items-center gap-2">`);
                                  Layers($$renderer8, { class: "size-3.5 text-primary" });
                                  $$renderer8.push(`<!----> <span>Edit Cycle Communication Policy</span></div> `);
                                  External_link($$renderer8, {
                                    class: "size-3 text-muted-foreground group-hover:text-foreground transition-colors"
                                  });
                                  $$renderer8.push(`<!---->`);
                                },
                                $$slots: { default: true }
                              });
                            } else {
                              $$renderer7.push("<!--[-1-->");
                            }
                            $$renderer7.push(`<!--]--></div>`);
                          } else {
                            $$renderer7.push("<!--[-1-->");
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
            };
            HeaderActions($$renderer4, { primaryAction });
          }
          $$renderer4.push(`<!----> <div class="flex items-center gap-2 text-[11px] font-mono text-muted-foreground"><span class="relative flex h-2 w-2"><span class="absolute inline-flex h-full w-full rounded-full bg-emerald-400 opacity-75 motion-safe:animate-ping"></span> <span class="relative inline-flex h-2 w-2 rounded-full bg-emerald-500"></span></span> <span>Auto-refreshing (15s)</span></div></div></div> `);
          if (telemetry) {
            $$renderer4.push("<!--[0-->");
            $$renderer4.push(`<div class="grid grid-cols-2 sm:grid-cols-5 gap-3 text-xs font-mono" data-tour="monitoring-telemetry"><div class="craft-card-depth p-3.5 rounded-xl border border-border/70 text-center space-y-1"><div class="text-[10px] text-muted-foreground uppercase tracking-wider flex items-center justify-center gap-1 font-semibold">`);
            School($$renderer4, { class: "size-3 text-muted-foreground" });
            $$renderer4.push(`<!----> Active Rooms</div> <div class="text-2xl font-extrabold text-foreground tabular-nums">${escape_html(telemetry.active_rooms)} / ${escape_html(telemetry.total_rooms)}</div> <div class="text-[10px] text-muted-foreground font-sans">${escape_html(telemetry.active_sessions ?? 0)} active · ${escape_html(telemetry.completed_sessions ?? 0)} completed</div></div> <div class="craft-card-depth p-3.5 rounded-xl border border-border/70 text-center space-y-1"><div class="text-[10px] text-muted-foreground uppercase tracking-wider flex items-center justify-center gap-1 font-semibold">`);
            Users($$renderer4, { class: "size-3 text-muted-foreground" });
            $$renderer4.push(`<!----> Scheduled</div> <div class="text-2xl font-extrabold text-foreground tabular-nums">${escape_html(telemetry.total_scheduled)}</div> <div class="text-[10px] text-muted-foreground font-sans truncate">${escape_html(thresholds.cycle_label ?? "Academic cycle")}</div></div> <div class="craft-card-depth p-3.5 rounded-xl border border-border/70 text-center space-y-1"><div class="text-[10px] text-muted-foreground uppercase tracking-wider flex items-center justify-center gap-1 font-semibold">`);
            Circle_check($$renderer4, { class: "size-3 text-emerald-500" });
            $$renderer4.push(`<!----> Present</div> <div class="text-2xl font-extrabold text-emerald-600 dark:text-emerald-400 tabular-nums">${escape_html(telemetry.present_count)}</div> <div class="text-[10px] text-muted-foreground font-sans">`);
            if ((telemetry.conduct_scheduled ?? telemetry.total_scheduled) > 0) {
              $$renderer4.push("<!--[0-->");
              $$renderer4.push(`${escape_html(Math.round(telemetry.present_count / (telemetry.conduct_scheduled ?? telemetry.total_scheduled) * 100))}% turnout`);
            } else {
              $$renderer4.push("<!--[-1-->");
              $$renderer4.push(`0% turnout`);
            }
            $$renderer4.push(`<!--]--></div></div> <div${attr_class(`craft-card-depth p-3.5 rounded-xl border border-border/70 text-center space-y-1 ${absenceWarning() ? "border-rose-500/40 bg-rose-500/5 ring-1 ring-rose-500/20" : ""}`)}><div class="text-[10px] text-muted-foreground uppercase tracking-wider flex items-center justify-center gap-1 font-semibold">`);
            User_x($$renderer4, {
              class: `size-3 ${absenceWarning() ? "text-rose-500" : "text-muted-foreground"}`
            });
            $$renderer4.push(`<!----> Absent `);
            if (absenceWarning()) {
              $$renderer4.push("<!--[0-->");
              Triangle_alert($$renderer4, { class: "size-3 text-rose-500 animate-pulse" });
            } else {
              $$renderer4.push("<!--[-1-->");
            }
            $$renderer4.push(`<!--]--></div> <div${attr_class(`text-2xl font-extrabold ${absenceWarning() ? "text-rose-500" : "text-foreground"} tabular-nums`)}>${escape_html(telemetry.absent_count)}</div> `);
            if (absenceWarning()) {
              $$renderer4.push("<!--[0-->");
              $$renderer4.push(`<div class="text-[9px] text-rose-500 font-bold">${escape_html(absencePercent())}% — threshold exceeded</div>`);
            } else {
              $$renderer4.push("<!--[-1-->");
              $$renderer4.push(`<div class="text-[10px] text-muted-foreground font-sans">${escape_html(telemetry.void_count ? `${telemetry.void_count} voided` : "Within policy")}</div>`);
            }
            $$renderer4.push(`<!--]--></div> <div class="craft-card-depth p-3.5 rounded-xl border border-border/70 text-center space-y-1"><div class="text-[10px] text-muted-foreground uppercase tracking-wider flex items-center justify-center gap-1 font-semibold">`);
            Circle_check($$renderer4, { class: "size-3 text-blue-500" });
            $$renderer4.push(`<!----> Submitted</div> <div class="text-2xl font-extrabold text-blue-600 dark:text-blue-400 tabular-nums">${escape_html(telemetry.submitted_count ?? 0)}</div> <div class="text-[10px] text-muted-foreground font-sans">${escape_html(telemetry.present_count > 0 ? `${telemetry.submitted_count}/${telemetry.present_count} accounted` : "Awaiting start")}</div></div></div>`);
          } else {
            $$renderer4.push("<!--[-1-->");
          }
          $$renderer4.push(`<!--]--> `);
          if (page_state === "denied") {
            $$renderer4.push("<!--[0-->");
            $$renderer4.push(`<div class="rounded-xl border border-border bg-card p-6"><p class="text-sm text-muted-foreground">You do not have permission to view exam monitoring.</p></div>`);
          } else {
            $$renderer4.push("<!--[-1-->");
            $$renderer4.push(`<div class="flex flex-col sm:flex-row sm:items-center justify-between border-b border-border/80 pb-3 gap-3"><div class="flex items-center gap-2"><button type="button"${attr_class(`inline-flex items-center gap-2 px-3 py-1.5 rounded-lg text-xs font-semibold transition-all ${activeTab === "active" ? "bg-primary text-primary-foreground shadow-xs" : "text-muted-foreground hover:text-foreground hover:bg-muted/50"}`)}>`);
            Activity($$renderer4, { class: "size-3.5" });
            $$renderer4.push(`<!----> <span>Active Conduct</span> <span${attr_class(`ml-0.5 px-1.5 py-0.2 rounded-full text-[10px] font-mono ${activeTab === "active" ? "bg-primary-foreground/20 text-primary-foreground" : "bg-muted text-muted-foreground"}`)}>${escape_html(activeList().length)}</span></button> <button type="button"${attr_class(`inline-flex items-center gap-2 px-3 py-1.5 rounded-lg text-xs font-semibold transition-all ${activeTab === "history" ? "bg-primary text-primary-foreground shadow-xs" : "text-muted-foreground hover:text-foreground hover:bg-muted/50"}`)}>`);
            Clock($$renderer4, { class: "size-3.5" });
            $$renderer4.push(`<!----> <span>Conduct History</span> <span${attr_class(`ml-0.5 px-1.5 py-0.2 rounded-full text-[10px] font-mono ${activeTab === "history" ? "bg-primary-foreground/20 text-primary-foreground" : "bg-muted text-muted-foreground"}`)}>${escape_html(historyTotal())}</span></button></div> <div class="flex flex-wrap items-center gap-2"><div class="relative min-w-[180px] sm:min-w-[220px]">`);
            Search($$renderer4, {
              class: "absolute left-2.5 top-2.5 size-3.5 text-muted-foreground"
            });
            $$renderer4.push(`<!----> `);
            Input($$renderer4, {
              type: "text",
              placeholder: "Search code, room, proctor...",
              class: "pl-8 h-8 text-xs font-mono",
              oninput: applyFilters,
              get value() {
                return searchTerm;
              },
              set value($$value) {
                searchTerm = $$value;
                $$settled = false;
              }
            });
            $$renderer4.push(`<!----> `);
            if (searchTerm) {
              $$renderer4.push("<!--[0-->");
              $$renderer4.push(`<button type="button" class="absolute right-2.5 top-2.5 text-muted-foreground hover:text-foreground">`);
              X($$renderer4, { class: "size-3.5" });
              $$renderer4.push(`<!----></button>`);
            } else {
              $$renderer4.push("<!--[-1-->");
            }
            $$renderer4.push(`<!--]--></div> `);
            if (rooms.length > 0) {
              $$renderer4.push("<!--[0-->");
              $$renderer4.select(
                {
                  class: "h-8 rounded-md border border-input bg-background px-2.5 py-1 text-xs text-foreground shadow-xs focus-visible:outline-none focus-visible:ring-1 focus-visible:ring-ring font-sans",
                  value: selectedRoomId,
                  onchange: applyFilters
                },
                ($$renderer5) => {
                  $$renderer5.option({ value: "" }, ($$renderer6) => {
                    $$renderer6.push(`All Rooms`);
                  });
                  $$renderer5.push(`<!--[-->`);
                  const each_array = ensure_array_like(rooms);
                  for (let $$index = 0, $$length = each_array.length; $$index < $$length; $$index++) {
                    let room = each_array[$$index];
                    $$renderer5.option({ value: String(room.id) }, ($$renderer6) => {
                      $$renderer6.push(`${escape_html(room.name ?? room.code)}`);
                    });
                  }
                  $$renderer5.push(`<!--]-->`);
                }
              );
            } else {
              $$renderer4.push("<!--[-1-->");
            }
            $$renderer4.push(`<!--]--> <input type="date" class="h-8 rounded-md border border-input bg-background px-2.5 py-1 text-xs text-foreground shadow-xs focus-visible:outline-none focus-visible:ring-1 focus-visible:ring-ring font-mono"${attr("value", selectedDate)} title="Filter by session date"/> `);
            if (hasActiveFilters()) {
              $$renderer4.push("<!--[0-->");
              Button($$renderer4, {
                variant: "ghost",
                size: "sm",
                class: "h-8 px-2 text-xs",
                onclick: clearFilters,
                children: ($$renderer5) => {
                  $$renderer5.push(`<!---->Reset`);
                },
                $$slots: { default: true }
              });
            } else {
              $$renderer4.push("<!--[-1-->");
            }
            $$renderer4.push(`<!--]--></div></div> `);
            if (activeTab === "active") {
              $$renderer4.push("<!--[0-->");
              if (activeList().length > 0) {
                $$renderer4.push("<!--[0-->");
                $$renderer4.push(`<div data-tour="monitoring-checkin">`);
                {
                  let table = function($$renderer5) {
                    if (Table) {
                      $$renderer5.push("<!--[-->");
                      Table($$renderer5, {
                        class: "w-full min-w-[720px] text-sm",
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
                                $$renderer7.push(`<!--[-->`);
                                const each_array_1 = ensure_array_like(activeList());
                                for (let $$index_1 = 0, $$length = each_array_1.length; $$index_1 < $$length; $$index_1++) {
                                  let session = each_array_1[$$index_1];
                                  if (Table_row) {
                                    $$renderer7.push("<!--[-->");
                                    Table_row($$renderer7, {
                                      class: "cursor-pointer hover:bg-muted/30",
                                      onclick: (e) => {
                                        if (e.target.closest("[data-action-cell]")) return;
                                        router.visit(`/exams/sessions/${session.id}/monitoring`);
                                      },
                                      children: ($$renderer8) => {
                                        if (Table_cell) {
                                          $$renderer8.push("<!--[-->");
                                          Table_cell($$renderer8, {
                                            class: "font-sans",
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
                                            class: "font-medium tracking-tight font-mono",
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
                                            children: ($$renderer9) => {
                                              Badge($$renderer9, {
                                                variant: statusVariant(session.status),
                                                children: ($$renderer10) => {
                                                  $$renderer10.push(`<!---->${escape_html(statusLabel(session.status))}`);
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
                                            class: "text-center",
                                            "data-action-cell": true,
                                            children: ($$renderer9) => {
                                              Link($$renderer9, {
                                                href: `/exams/sessions/${session.id}/monitoring`,
                                                children: ($$renderer10) => {
                                                  Button($$renderer10, {
                                                    variant: "ghost",
                                                    size: "sm",
                                                    class: "h-8 px-2 text-xs font-semibold",
                                                    children: ($$renderer11) => {
                                                      Activity($$renderer11, { class: "mr-1.5 size-3.5" });
                                                      $$renderer11.push(`<!----> Open Station`);
                                                    },
                                                    $$slots: { default: true }
                                                  });
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
                    $$renderer5.push(`<ul class="grid gap-3 sm:grid-cols-2 lg:grid-cols-3" role="list"><!--[-->`);
                    const each_array_2 = ensure_array_like(activeList());
                    for (let $$index_2 = 0, $$length = each_array_2.length; $$index_2 < $$length; $$index_2++) {
                      let session = each_array_2[$$index_2];
                      {
                        let status = function($$renderer6) {
                          Badge($$renderer6, {
                            variant: monitoringCardStatusVariant(session.status),
                            class: "font-mono text-[10px] uppercase font-bold",
                            children: ($$renderer7) => {
                              $$renderer7.push(`<!---->${escape_html(monitoringCardStatusLabel(session.status))}`);
                            },
                            $$slots: { default: true }
                          });
                        }, summary = function($$renderer6) {
                          $$renderer6.push(`<div class="flex flex-wrap items-center gap-2">`);
                          if (session.present_count != null) {
                            $$renderer6.push("<!--[0-->");
                            $$renderer6.push(`<span class="font-medium text-foreground">Turnout: ${escape_html(session.present_count)} / ${escape_html(session.assigned_count ?? 0)} `);
                            if (session.turnout_percent != null) {
                              $$renderer6.push("<!--[0-->");
                              $$renderer6.push(`<span class="text-muted-foreground">(${escape_html(session.turnout_percent)}%)</span>`);
                            } else {
                              $$renderer6.push("<!--[-1-->");
                            }
                            $$renderer6.push(`<!--]--></span>`);
                          } else {
                            $$renderer6.push("<!--[-1-->");
                            $$renderer6.push(`<span class="font-medium text-foreground">${escape_html(session.assigned_count ?? 0)} assigned</span>`);
                          }
                          $$renderer6.push(`<!--]--> `);
                          if (hasUnrecordedAttendance(session)) {
                            $$renderer6.push("<!--[0-->");
                            Badge($$renderer6, {
                              variant: "outline",
                              class: "border-rose-500/40 bg-rose-500/5 text-[10px] text-rose-600 dark:text-rose-400",
                              children: ($$renderer7) => {
                                Triangle_alert($$renderer7, { class: "mr-1 size-3" });
                                $$renderer7.push(`<!----> Attendance unrecorded`);
                              },
                              $$slots: { default: true }
                            });
                          } else {
                            $$renderer6.push("<!--[-1-->");
                          }
                          $$renderer6.push(`<!--]--></div>`);
                        }, details = function($$renderer6) {
                          $$renderer6.push(`<div class="grid gap-2 sm:grid-cols-2"><div class="space-y-0.5"><p class="text-[10px] font-semibold uppercase tracking-wide text-muted-foreground">Assigned proctors</p> <p class="text-foreground">${escape_html(session.proctors?.length ? session.proctors.map((proctor) => proctor.name).join(", ") : "None assigned")}</p></div> <div class="space-y-0.5"><p class="text-[10px] font-semibold uppercase tracking-wide text-muted-foreground">Scheduled</p> <p class="text-foreground">${escape_html(session.date ? formatDate(session.date) : "—")} `);
                          if (session.start_time) {
                            $$renderer6.push("<!--[0-->");
                            $$renderer6.push(`· ${escape_html(formatTimeRange(session.start_time, session.end_time))}`);
                          } else {
                            $$renderer6.push("<!--[-1-->");
                          }
                          $$renderer6.push(`<!--]--></p></div> `);
                          if (session.closed_at) {
                            $$renderer6.push("<!--[0-->");
                            $$renderer6.push(`<div class="space-y-0.5"><p class="text-[10px] font-semibold uppercase tracking-wide text-muted-foreground">Closed</p> <p class="text-foreground">${escape_html(formatDate(session.closed_at))} · ${escape_html(formatTime(session.closed_at))}</p></div>`);
                          } else {
                            $$renderer6.push("<!--[-1-->");
                          }
                          $$renderer6.push(`<!--]--> `);
                          if (session.absent_count != null) {
                            $$renderer6.push("<!--[0-->");
                            $$renderer6.push(`<div class="space-y-0.5"><p class="text-[10px] font-semibold uppercase tracking-wide text-muted-foreground">Absent</p> <p class="text-foreground">${escape_html(session.absent_count)}</p></div>`);
                          } else {
                            $$renderer6.push("<!--[-1-->");
                          }
                          $$renderer6.push(`<!--]--> `);
                          if (session.void_count != null) {
                            $$renderer6.push("<!--[0-->");
                            $$renderer6.push(`<div class="space-y-0.5"><p class="text-[10px] font-semibold uppercase tracking-wide text-muted-foreground">Voided</p> <p class="text-foreground">${escape_html(session.void_count)}</p></div>`);
                          } else {
                            $$renderer6.push("<!--[-1-->");
                          }
                          $$renderer6.push(`<!--]--> <div class="space-y-0.5"><p class="text-[10px] font-semibold uppercase tracking-wide text-muted-foreground">Capacity</p> <p class="text-foreground">${escape_html(session.assigned_count ?? 0)} assigned / ${escape_html(session.room?.capacity ?? "—")} seats</p></div></div>`);
                        }, actions = function($$renderer6) {
                          Link($$renderer6, {
                            href: `/exams/sessions/${session.id}/monitoring`,
                            class: "flex-1",
                            children: ($$renderer7) => {
                              Button($$renderer7, {
                                variant: "outline",
                                size: "sm",
                                class: "min-h-10 w-full font-semibold",
                                children: ($$renderer8) => {
                                  Activity($$renderer8, { class: "mr-1.5 size-3.5" });
                                  $$renderer8.push(`<!----> Open Station`);
                                },
                                $$slots: { default: true }
                              });
                            },
                            $$slots: { default: true }
                          });
                        };
                        OperationalCard($$renderer5, {
                          id: session.id,
                          title: session.room_name || session.code,
                          subtitle: `${session.starts_at || session.date || ""} · Ref ${session.code}`,
                          get expanded() {
                            return expandedCards[session.id];
                          },
                          set expanded($$value) {
                            expandedCards[session.id] = $$value;
                            $$settled = false;
                          },
                          status,
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
                    viewMode: "responsive",
                    overflow: "auto",
                    table,
                    cards,
                    $$slots: { table: true, cards: true }
                  });
                }
                $$renderer4.push(`<!----></div>`);
              } else {
                $$renderer4.push("<!--[-1-->");
                $$renderer4.push(`<div class="rounded-2xl border border-border bg-card p-8 text-center space-y-4 shadow-xs"><div class="mx-auto size-12 rounded-full bg-muted/60 flex items-center justify-center text-muted-foreground">`);
                School($$renderer4, { class: "size-6 text-muted-foreground/70" });
                $$renderer4.push(`<!----></div> <div class="max-w-md mx-auto space-y-1.5"><h3 class="text-base font-bold text-foreground">No Sessions in Active Conduct</h3> <p class="text-xs text-muted-foreground leading-relaxed">`);
                if (can_manage) {
                  $$renderer4.push("<!--[0-->");
                  $$renderer4.push(`All examination rooms are currently on standby for ${escape_html(thresholds.cycle_label ?? "the active academic cycle")}. `);
                  if (historyTotal() > 0) {
                    $$renderer4.push("<!--[0-->");
                    $$renderer4.push(`You can review ${escape_html(historyTotal())} completed session records in Conduct History.`);
                  } else {
                    $$renderer4.push("<!--[-1-->");
                    $$renderer4.push(`Publish an exam session under Exam Scheduling to initiate live testing-day monitoring.`);
                  }
                  $$renderer4.push(`<!--]-->`);
                } else {
                  $$renderer4.push("<!--[-1-->");
                  $$renderer4.push(`You currently have no active proctor duties for published or in-progress rooms.`);
                }
                $$renderer4.push(`<!--]--></p></div> <div class="flex flex-wrap items-center justify-center gap-3 pt-2">`);
                if (historyTotal() > 0) {
                  $$renderer4.push("<!--[0-->");
                  Button($$renderer4, {
                    variant: "outline",
                    size: "sm",
                    onclick: () => selectTab("history"),
                    children: ($$renderer5) => {
                      Clock($$renderer5, { class: "size-3.5 mr-1.5" });
                      $$renderer5.push(`<!----> <span>View Conduct History (${escape_html(historyTotal())})</span>`);
                    },
                    $$slots: { default: true }
                  });
                } else {
                  $$renderer4.push("<!--[-1-->");
                }
                $$renderer4.push(`<!--]--> `);
                if (can_manage) {
                  $$renderer4.push("<!--[0-->");
                  Link($$renderer4, {
                    href: "/exams/sessions",
                    children: ($$renderer5) => {
                      Button($$renderer5, {
                        variant: "default",
                        size: "sm",
                        children: ($$renderer6) => {
                          Calendar($$renderer6, { class: "size-3.5 mr-1.5" });
                          $$renderer6.push(`<!----> <span>Go to Exam Scheduling</span>`);
                        },
                        $$slots: { default: true }
                      });
                    },
                    $$slots: { default: true }
                  });
                } else {
                  $$renderer4.push("<!--[-1-->");
                }
                $$renderer4.push(`<!--]--></div></div>`);
              }
              $$renderer4.push(`<!--]-->`);
            } else {
              $$renderer4.push("<!--[-1-->");
              if (historyList().length > 0) {
                $$renderer4.push("<!--[0-->");
                {
                  let table = function($$renderer5) {
                    if (Table) {
                      $$renderer5.push("<!--[-->");
                      Table($$renderer5, {
                        class: "w-full min-w-[760px] text-sm",
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
                                          children: ($$renderer9) => {
                                            $$renderer9.push(`<!---->Turnout`);
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
                                            $$renderer9.push(`<!---->Submissions`);
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
                                $$renderer7.push(`<!--[-->`);
                                const each_array_3 = ensure_array_like(historyList());
                                for (let $$index_3 = 0, $$length = each_array_3.length; $$index_3 < $$length; $$index_3++) {
                                  let session = each_array_3[$$index_3];
                                  if (Table_row) {
                                    $$renderer7.push("<!--[-->");
                                    Table_row($$renderer7, {
                                      class: "cursor-pointer hover:bg-muted/30",
                                      onclick: (e) => {
                                        if (e.target.closest("[data-action-cell]")) return;
                                        router.visit(`/exams/sessions/${session.id}/monitoring`);
                                      },
                                      children: ($$renderer8) => {
                                        if (Table_cell) {
                                          $$renderer8.push("<!--[-->");
                                          Table_cell($$renderer8, {
                                            class: "font-sans",
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
                                              $$renderer9.push(`<!--]--> `);
                                              if (session.closed_at) {
                                                $$renderer9.push("<!--[0-->");
                                                $$renderer9.push(`<div class="text-[10px] text-muted-foreground font-mono">Closed: ${escape_html(formatTime(session.closed_at))}</div>`);
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
                                            class: "font-medium tracking-tight font-mono",
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
                                            children: ($$renderer9) => {
                                              Badge($$renderer9, {
                                                variant: statusVariant(session.status),
                                                children: ($$renderer10) => {
                                                  $$renderer10.push(`<!---->${escape_html(statusLabel(session.status))}`);
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
                                            children: ($$renderer9) => {
                                              $$renderer9.push(`<div class="font-mono text-xs"><span class="font-semibold text-emerald-600 dark:text-emerald-400">${escape_html(session.present_count ?? 0)}</span> <span class="text-muted-foreground">/${escape_html(session.assigned_count ?? 0)} (${escape_html(session.turnout_percent ?? 0)}%)</span></div> `);
                                              if ((session.absent_count ?? 0) > 0) {
                                                $$renderer9.push("<!--[0-->");
                                                $$renderer9.push(`<div class="text-[10px] text-rose-500 font-mono">${escape_html(session.absent_count)} absent</div>`);
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
                                              if (session.submission_status === "complete") {
                                                $$renderer9.push("<!--[0-->");
                                                Badge($$renderer9, {
                                                  variant: "outline",
                                                  class: "text-[10px] font-mono border-emerald-500/40 text-emerald-600 dark:text-emerald-400 bg-emerald-500/5",
                                                  children: ($$renderer10) => {
                                                    Check($$renderer10, { class: "size-3 mr-1" });
                                                    $$renderer10.push(`<!----> Complete (${escape_html(session.submitted_count ?? 0)})`);
                                                  },
                                                  $$slots: { default: true }
                                                });
                                              } else if (session.submission_status === "missing_records") {
                                                $$renderer9.push("<!--[1-->");
                                                Badge($$renderer9, {
                                                  variant: "outline",
                                                  class: "text-[10px] font-mono border-amber-500/40 text-amber-600 dark:text-amber-400 bg-amber-500/5",
                                                  children: ($$renderer10) => {
                                                    Triangle_alert($$renderer10, { class: "size-3 mr-1" });
                                                    $$renderer10.push(`<!----> Missing (${escape_html(Math.max(0, (session.present_count ?? 0) - (session.submitted_count ?? 0)))})`);
                                                  },
                                                  $$slots: { default: true }
                                                });
                                              } else if (session.submission_status === "unrecorded_attendance") {
                                                $$renderer9.push("<!--[2-->");
                                                Badge($$renderer9, {
                                                  variant: "outline",
                                                  class: "text-[10px] font-mono border-rose-500/40 text-rose-600 dark:text-rose-400 bg-rose-500/5",
                                                  children: ($$renderer10) => {
                                                    $$renderer10.push(`<!---->Unrecorded`);
                                                  },
                                                  $$slots: { default: true }
                                                });
                                              } else {
                                                $$renderer9.push("<!--[-1-->");
                                                Badge($$renderer9, {
                                                  variant: "outline",
                                                  class: "text-[10px] font-mono text-muted-foreground",
                                                  children: ($$renderer10) => {
                                                    $$renderer10.push(`<!---->${escape_html(session.submission_status)}`);
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
                                            class: "text-center",
                                            "data-action-cell": true,
                                            children: ($$renderer9) => {
                                              Link($$renderer9, {
                                                href: `/exams/sessions/${session.id}/monitoring`,
                                                children: ($$renderer10) => {
                                                  Button($$renderer10, {
                                                    variant: "ghost",
                                                    size: "sm",
                                                    class: "h-8 px-2 text-xs font-semibold",
                                                    children: ($$renderer11) => {
                                                      Eye($$renderer11, { class: "mr-1.5 size-3.5" });
                                                      $$renderer11.push(`<!----> Review Roster`);
                                                    },
                                                    $$slots: { default: true }
                                                  });
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
                    $$renderer5.push(`<ul class="grid gap-3 sm:grid-cols-2 lg:grid-cols-3" role="list"><!--[-->`);
                    const each_array_4 = ensure_array_like(historyList());
                    for (let $$index_4 = 0, $$length = each_array_4.length; $$index_4 < $$length; $$index_4++) {
                      let session = each_array_4[$$index_4];
                      {
                        let status = function($$renderer6) {
                          Badge($$renderer6, {
                            variant: monitoringCardStatusVariant(session.status),
                            class: "font-mono text-[10px] uppercase font-bold",
                            children: ($$renderer7) => {
                              $$renderer7.push(`<!---->${escape_html(monitoringCardStatusLabel(session.status))}`);
                            },
                            $$slots: { default: true }
                          });
                        }, summary = function($$renderer6) {
                          $$renderer6.push(`<div class="flex flex-wrap items-center gap-2"><span class="font-medium text-foreground">Turnout: ${escape_html(session.present_count ?? 0)} / ${escape_html(session.assigned_count ?? 0)} <span class="text-muted-foreground">(${escape_html(session.turnout_percent ?? 0)}%)</span></span> `);
                          if (session.submission_status === "complete") {
                            $$renderer6.push("<!--[0-->");
                            Badge($$renderer6, {
                              variant: "outline",
                              class: "border-emerald-500/40 bg-emerald-500/5 text-[10px] text-emerald-600 dark:text-emerald-400",
                              children: ($$renderer7) => {
                                Check($$renderer7, { class: "mr-1 size-3" });
                                $$renderer7.push(`<!----> Submissions complete`);
                              },
                              $$slots: { default: true }
                            });
                          } else if (session.submission_status === "missing_records") {
                            $$renderer6.push("<!--[1-->");
                            Badge($$renderer6, {
                              variant: "outline",
                              class: "border-amber-500/40 bg-amber-500/5 text-[10px] text-amber-600 dark:text-amber-400",
                              children: ($$renderer7) => {
                                Triangle_alert($$renderer7, { class: "mr-1 size-3" });
                                $$renderer7.push(`<!----> Missing ${escape_html(Math.max(0, (session.present_count ?? 0) - (session.submitted_count ?? 0)))} submissions`);
                              },
                              $$slots: { default: true }
                            });
                          } else {
                            $$renderer6.push("<!--[-1-->");
                          }
                          $$renderer6.push(`<!--]--> `);
                          if (hasUnrecordedAttendance(session)) {
                            $$renderer6.push("<!--[0-->");
                            Badge($$renderer6, {
                              variant: "outline",
                              class: "border-rose-500/40 bg-rose-500/5 text-[10px] text-rose-600 dark:text-rose-400",
                              children: ($$renderer7) => {
                                Triangle_alert($$renderer7, { class: "mr-1 size-3" });
                                $$renderer7.push(`<!----> Attendance unrecorded`);
                              },
                              $$slots: { default: true }
                            });
                          } else {
                            $$renderer6.push("<!--[-1-->");
                          }
                          $$renderer6.push(`<!--]--></div>`);
                        }, details = function($$renderer6) {
                          $$renderer6.push(`<div class="grid gap-2 sm:grid-cols-2"><div class="space-y-0.5"><p class="text-[10px] font-semibold uppercase tracking-wide text-muted-foreground">Assigned proctors</p> <p class="text-foreground">${escape_html(session.proctors?.length ? session.proctors.map((proctor) => proctor.name).join(", ") : "None assigned")}</p></div> <div class="space-y-0.5"><p class="text-[10px] font-semibold uppercase tracking-wide text-muted-foreground">Scheduled</p> <p class="text-foreground">${escape_html(session.date ? formatDate(session.date) : "—")} `);
                          if (session.start_time) {
                            $$renderer6.push("<!--[0-->");
                            $$renderer6.push(`· ${escape_html(formatTimeRange(session.start_time, session.end_time))}`);
                          } else {
                            $$renderer6.push("<!--[-1-->");
                          }
                          $$renderer6.push(`<!--]--></p></div> <div class="space-y-0.5"><p class="text-[10px] font-semibold uppercase tracking-wide text-muted-foreground">Closed</p> <p class="text-foreground">`);
                          if (session.closed_at) {
                            $$renderer6.push("<!--[0-->");
                            $$renderer6.push(`${escape_html(formatDate(session.closed_at))} · ${escape_html(formatTime(session.closed_at))}`);
                          } else {
                            $$renderer6.push("<!--[-1-->");
                            $$renderer6.push(`—`);
                          }
                          $$renderer6.push(`<!--]--></p></div> <div class="space-y-0.5"><p class="text-[10px] font-semibold uppercase tracking-wide text-muted-foreground">Absent</p> <p class="text-foreground">${escape_html(session.absent_count ?? 0)}</p></div> <div class="space-y-0.5"><p class="text-[10px] font-semibold uppercase tracking-wide text-muted-foreground">Voided</p> <p class="text-foreground">${escape_html(session.void_count ?? 0)}</p></div> <div class="space-y-0.5"><p class="text-[10px] font-semibold uppercase tracking-wide text-muted-foreground">Capacity</p> <p class="text-foreground">${escape_html(session.assigned_count ?? 0)} assigned / ${escape_html(session.room?.capacity ?? "—")} seats</p></div></div>`);
                        }, actions = function($$renderer6) {
                          Link($$renderer6, {
                            href: `/exams/sessions/${session.id}/monitoring`,
                            class: "flex-1",
                            children: ($$renderer7) => {
                              Button($$renderer7, {
                                variant: "outline",
                                size: "sm",
                                class: "min-h-10 w-full font-semibold",
                                children: ($$renderer8) => {
                                  Eye($$renderer8, { class: "mr-1.5 size-3.5" });
                                  $$renderer8.push(`<!----> Review Roster`);
                                },
                                $$slots: { default: true }
                              });
                            },
                            $$slots: { default: true }
                          });
                        };
                        OperationalCard($$renderer5, {
                          id: session.id,
                          title: session.room_name || session.code,
                          subtitle: `${session.starts_at || session.date || ""} · Ref ${session.code}`,
                          get expanded() {
                            return expandedCards[session.id];
                          },
                          set expanded($$value) {
                            expandedCards[session.id] = $$value;
                            $$settled = false;
                          },
                          status,
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
                    viewMode: "responsive",
                    overflow: "auto",
                    table,
                    cards,
                    $$slots: { table: true, cards: true }
                  });
                }
                $$renderer4.push(`<!----> `);
                if (history_sessions) {
                  $$renderer4.push("<!--[0-->");
                  SimplePagination($$renderer4, { data: history_sessions });
                } else {
                  $$renderer4.push("<!--[-1-->");
                }
                $$renderer4.push(`<!--]-->`);
              } else {
                $$renderer4.push("<!--[-1-->");
                $$renderer4.push(`<div class="rounded-xl border border-border bg-card p-8 text-center space-y-2"><p class="text-sm font-medium text-foreground">No completed sessions match your current filter</p> <p class="text-xs text-muted-foreground">Try clearing your search term, date, or room selection.</p> `);
                if (hasActiveFilters()) {
                  $$renderer4.push("<!--[0-->");
                  Button($$renderer4, {
                    variant: "outline",
                    size: "sm",
                    class: "mt-2 text-xs",
                    onclick: clearFilters,
                    children: ($$renderer5) => {
                      $$renderer5.push(`<!---->Clear All Filters`);
                    },
                    $$slots: { default: true }
                  });
                } else {
                  $$renderer4.push("<!--[-1-->");
                }
                $$renderer4.push(`<!--]--></div>`);
              }
              $$renderer4.push(`<!--]-->`);
            }
            $$renderer4.push(`<!--]-->`);
          }
          $$renderer4.push(`<!--]--></div> `);
          XiamNpcDialog($$renderer4, {
            tourKey: "exam-monitoring",
            context: monitoringContext(),
            get open() {
              return tourOpen;
            },
            set open($$value) {
              tourOpen = $$value;
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
  });
}
export {
  MonitoringIndex as default
};
