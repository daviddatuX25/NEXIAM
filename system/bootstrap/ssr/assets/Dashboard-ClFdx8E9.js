import { g as sanitize_props, i as spread_props, j as slot, h as head, e as escape_html, f as attr_style, s as stringify, c as attr_class, b as attr, a as ensure_array_like, B as Button, d as derived } from "../app.js";
import { S as StaffLayout, C as Calendar } from "./StaffLayout-CR-MZopa.js";
import { P as PageHeaderBar } from "./PageHeaderBar-DJhT5vOR.js";
import { B as BentoCard } from "./BentoCard-COpmyjxA.js";
import E2PageState from "./E2PageState-yFavAmgl.js";
import { B as Badge } from "./badge-D9Vgs7xh.js";
import { C as Card, a as Card_content } from "./card-content-Brg2wRkV.js";
import { C as Card_description } from "./card-description-BGy_v5-d.js";
import "clsx";
import { C as Card_header, a as Card_title } from "./card-title-BNY2ZPje.js";
import { I as Input } from "./input-BUZAZKIK.js";
import { A as ApplicantLookupDialog, C as ConsultationMobileDock } from "./ApplicantLookupDialog-DWMtphZu.js";
import { U as User_check } from "./user-check-Dl-wciTR.js";
import { C as Clock_3 } from "./clock-3-CBFB3kOE.js";
import { C as Circle_alert } from "./circle-alert-jWXJ7oC0.js";
import { I as Icon } from "./Icon-Dal7PqKK.js";
import { L as Layers } from "./layers-_Ie6y7G_.js";
import { C as Circle_check } from "./circle-check-C59ZS7Zv.js";
import { S as Search } from "./settings-C5xOKpV3.js";
import { X } from "./x-CnxrMbMn.js";
import { A as Arrow_right } from "./arrow-right-CzynHiIo.js";
import { C as Chevron_left } from "./chevron-left-DCSOZwZl.js";
import { C as Chevron_right } from "./chevron-right-BHu-PGOg.js";
import { L as Link } from "./Link-B7ks7rBK.js";
import { U as User_plus } from "./user-plus-CCVf9WDs.js";
import { S as Settings_2 } from "./settings-2-B2lTvmrM.js";
import "axios";
import "@inertiajs/core";
import "laravel-precognition";
import "tailwind-merge";
import "tailwind-variants";
import "./ToastManager-C7oO_4Jn.js";
import "./circle-x-BQrhluGu.js";
import "./triangle-alert-CUvuB-HL.js";
import "./info-C3MCzlQW.js";
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
import "./refresh-cw-CYdk8aE0.js";
import "./camera-C09Eyogi.js";
import "./dialog-footer-kNnwKT2h.js";
import "./clock-B4fXoAi9.js";
import "./qr-code-C5X8qcH6.js";
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
import "./shield-alert-CXtDdte9.js";
import "./arrow-left-DbWnQR3t.js";
function Door_open($$renderer, $$props) {
  const $$sanitized_props = sanitize_props($$props);
  const iconNode = [
    ["path", { "d": "M11 20H2" }],
    [
      "path",
      {
        "d": "M11 4.562v16.157a1 1 0 0 0 1.242.97L19 20V5.562a2 2 0 0 0-1.515-1.94l-4-1A2 2 0 0 0 11 4.561z"
      }
    ],
    ["path", { "d": "M11 4H8a2 2 0 0 0-2 2v14" }],
    ["path", { "d": "M14 12h.01" }],
    ["path", { "d": "M22 20h-3" }]
  ];
  Icon($$renderer, spread_props([
    { name: "door-open" },
    $$sanitized_props,
    {
      /**
       * @component @name DoorOpen
       * @description Lucide SVG icon component, renders SVG Element with children.
       *
       * @preview ![img](data:image/svg+xml;base64,PHN2ZyAgeG1sbnM9Imh0dHA6Ly93d3cudzMub3JnLzIwMDAvc3ZnIgogIHdpZHRoPSIyNCIKICBoZWlnaHQ9IjI0IgogIHZpZXdCb3g9IjAgMCAyNCAyNCIKICBmaWxsPSJub25lIgogIHN0cm9rZT0iIzAwMCIgc3R5bGU9ImJhY2tncm91bmQtY29sb3I6ICNmZmY7IGJvcmRlci1yYWRpdXM6IDJweCIKICBzdHJva2Utd2lkdGg9IjIiCiAgc3Ryb2tlLWxpbmVjYXA9InJvdW5kIgogIHN0cm9rZS1saW5lam9pbj0icm91bmQiCj4KICA8cGF0aCBkPSJNMTEgMjBIMiIgLz4KICA8cGF0aCBkPSJNMTEgNC41NjJ2MTYuMTU3YTEgMSAwIDAgMCAxLjI0Mi45N0wxOSAyMFY1LjU2MmEyIDIgMCAwIDAtMS41MTUtMS45NGwtNC0xQTIgMiAwIDAgMCAxMSA0LjU2MXoiIC8+CiAgPHBhdGggZD0iTTExIDRIOGEyIDIgMCAwIDAtMiAydjE0IiAvPgogIDxwYXRoIGQ9Ik0xNCAxMmguMDEiIC8+CiAgPHBhdGggZD0iTTIyIDIwaC0zIiAvPgo8L3N2Zz4K) - https://lucide.dev/icons/door-open
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
function Users_round($$renderer, $$props) {
  const $$sanitized_props = sanitize_props($$props);
  const iconNode = [
    ["path", { "d": "M18 21a8 8 0 0 0-16 0" }],
    ["circle", { "cx": "10", "cy": "8", "r": "5" }],
    ["path", { "d": "M22 20c0-3.37-2-6.5-4-8a5 5 0 0 0-.45-8.3" }]
  ];
  Icon($$renderer, spread_props([
    { name: "users-round" },
    $$sanitized_props,
    {
      /**
       * @component @name UsersRound
       * @description Lucide SVG icon component, renders SVG Element with children.
       *
       * @preview ![img](data:image/svg+xml;base64,PHN2ZyAgeG1sbnM9Imh0dHA6Ly93d3cudzMub3JnLzIwMDAvc3ZnIgogIHdpZHRoPSIyNCIKICBoZWlnaHQ9IjI0IgogIHZpZXdCb3g9IjAgMCAyNCAyNCIKICBmaWxsPSJub25lIgogIHN0cm9rZT0iIzAwMCIgc3R5bGU9ImJhY2tncm91bmQtY29sb3I6ICNmZmY7IGJvcmRlci1yYWRpdXM6IDJweCIKICBzdHJva2Utd2lkdGg9IjIiCiAgc3Ryb2tlLWxpbmVjYXA9InJvdW5kIgogIHN0cm9rZS1saW5lam9pbj0icm91bmQiCj4KICA8cGF0aCBkPSJNMTggMjFhOCA4IDAgMCAwLTE2IDAiIC8+CiAgPGNpcmNsZSBjeD0iMTAiIGN5PSI4IiByPSI1IiAvPgogIDxwYXRoIGQ9Ik0yMiAyMGMwLTMuMzctMi02LjUtNC04YTUgNSAwIDAgMC0uNDUtOC4zIiAvPgo8L3N2Zz4K) - https://lucide.dev/icons/users-round
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
function Dashboard($$renderer, $$props) {
  $$renderer.component(($$renderer2) => {
    let {
      page_state = "ready",
      dashboard = null,
      can_plan = false,
      settings = { policy_url: null, admission_support_url: null }
    } = $$props;
    const sessionsUrl = typeof route === "function" ? route("consultation.sessions.index") : "/consultation/sessions";
    let applicantLookupOpen = false;
    let selectedHour = "all";
    let batchSearchQuery = "";
    let batchStatusFilter = "all";
    let batchPage = 1;
    const batchPageSize = 10;
    let reAdvisingFilter = "";
    let reAdvisingSort = "name-asc";
    let reAdvisingPage = 1;
    let unscheduledFilter = "";
    let unscheduledSort = "name-asc";
    let unscheduledPage = 1;
    function getQueuePage(items, filter, sort, requestedPage) {
      const query = filter.trim().toLocaleLowerCase();
      const sortKey = sort.startsWith("reference-") ? "reference_code" : "name";
      const direction = sort.endsWith("-desc") ? -1 : 1;
      const filtered = items.filter((item) => {
        return !query || String(item.name ?? "").toLocaleLowerCase().includes(query) || String(item.reference_code ?? "").toLocaleLowerCase().includes(query);
      }).sort((left, right) => direction * String(left[sortKey] ?? "").localeCompare(String(right[sortKey] ?? ""), void 0, { numeric: true, sensitivity: "base" }));
      const pageCount = Math.max(1, Math.ceil(filtered.length / 6));
      const currentPage = Math.min(requestedPage, pageCount);
      return {
        items: filtered.slice((currentPage - 1) * 6, currentPage * 6),
        currentPage,
        pageCount,
        total: filtered.length
      };
    }
    const reAdvisingPageData = derived(() => getQueuePage(dashboard?.re_advising ?? [], reAdvisingFilter, reAdvisingSort, reAdvisingPage));
    const unscheduledPageData = derived(() => getQueuePage(dashboard?.not_yet_scheduled ?? [], unscheduledFilter, unscheduledSort, unscheduledPage));
    function consultationStatus(applicant) {
      const outcome = String(applicant?.outcome ?? "").toLowerCase();
      const label = String(applicant?.status_label ?? "").toLowerCase();
      if (outcome === "advised" || label === "advised" || applicant?.advised) return "Advised";
      if (outcome === "deferred" || label === "deferred") return "Deferred";
      if (outcome === "referred" || outcome === "referred to department" || label === "referred to department") return "Referred to Department";
      if (label === "in progress" || outcome === "in progress") return "In Progress";
      return "Scheduled";
    }
    function consultationStatusVariant(status) {
      if (status === "Advised") return "default";
      if (status === "Referred to Department") return "outline";
      return "secondary";
    }
    function consultationStatusClass(status) {
      if (status === "Advised") return "shrink-0 border-emerald-600/30 bg-emerald-500/10 text-emerald-700 dark:text-emerald-400 font-semibold";
      if (status === "Deferred") return "shrink-0 border-amber-300 bg-amber-100 text-amber-950 dark:bg-amber-950/40 dark:text-amber-300 font-semibold";
      if (status === "Referred to Department") return "shrink-0 border-blue-300 bg-blue-50 text-blue-800 dark:bg-blue-950/40 dark:text-blue-300 font-semibold";
      if (status === "In Progress") return "shrink-0 border-purple-300 bg-purple-50 text-purple-800 dark:bg-purple-950/40 dark:text-purple-300 font-semibold";
      return "shrink-0 border-border bg-muted/60 text-muted-foreground font-medium";
    }
    function reAdvisingReason(reason) {
      return reason === "program_declined_by_department" ? "Declined by department head" : "Deferred by counselor";
    }
    function reAdvisingVariant(reason) {
      return reason === "program_declined_by_department" ? "outline" : "secondary";
    }
    function reAdvisingClass(reason) {
      return reason === "program_declined_by_department" ? "shrink-0 border-blue-300 bg-blue-50 text-blue-800 dark:bg-blue-950/40 dark:text-blue-300" : "shrink-0 border-amber-300 bg-amber-100 text-amber-950 dark:bg-amber-950/40 dark:text-amber-300";
    }
    const hourlySchedule = derived(() => {
      const schedule = dashboard?.today_schedule ?? [];
      if (!schedule.length) return [];
      const buckets = /* @__PURE__ */ new Map();
      for (const session of schedule) {
        let hourKey = "Flexible / Unspecified";
        let hourNum = 99;
        if (session.time && session.time.includes(":")) {
          const [hStr] = session.time.split(":");
          const h = parseInt(hStr, 10);
          if (!isNaN(h)) {
            hourNum = h;
            const period = h >= 12 ? "PM" : "AM";
            const hour12 = h % 12 === 0 ? 12 : h % 12;
            hourKey = `${String(hour12).padStart(2, "0")}:00 ${period}`;
          }
        } else if (session.scheduled_at) {
          const date = new Date(session.scheduled_at);
          const h = date.getHours();
          hourNum = h;
          const period = h >= 12 ? "PM" : "AM";
          const hour12 = h % 12 === 0 ? 12 : h % 12;
          hourKey = `${String(hour12).padStart(2, "0")}:00 ${period}`;
        }
        if (!buckets.has(hourKey)) {
          buckets.set(hourKey, {
            hourKey,
            hourNum,
            sessions: [],
            allApplicants: [],
            totalApplicants: 0,
            advisedCount: 0,
            deferredCount: 0,
            referredCount: 0,
            inProgressCount: 0,
            waitingCount: 0,
            rooms: /* @__PURE__ */ new Set()
          });
        }
        const bucket = buckets.get(hourKey);
        bucket.sessions.push(session);
        if (session.room) bucket.rooms.add(session.room);
        const sessionApplicants = session.applicants ?? [];
        for (const app of sessionApplicants) {
          const item = {
            ...app,
            session_id: session.id,
            room: session.room,
            session_time: session.time
          };
          bucket.allApplicants.push(item);
          const st = consultationStatus(app);
          if (st === "Advised") bucket.advisedCount++;
          else if (st === "Deferred") bucket.deferredCount++;
          else if (st === "Referred to Department") bucket.referredCount++;
          else if (st === "In Progress") bucket.inProgressCount++;
          else bucket.waitingCount++;
        }
        bucket.totalApplicants = bucket.allApplicants.length;
      }
      const currentHourNum = (/* @__PURE__ */ new Date()).getHours();
      const sorted = Array.from(buckets.values()).sort((a, b) => a.hourNum - b.hourNum);
      return sorted.map((b) => ({
        ...b,
        roomsList: Array.from(b.rooms).join(", ") || "General Desk",
        completionRate: b.totalApplicants > 0 ? Math.round(b.advisedCount / b.totalApplicants * 100) : 0,
        isCurrentHour: currentHourNum === b.hourNum
      }));
    });
    const activeHourData = derived(() => {
      if (!hourlySchedule().length) return null;
      {
        const allApps = hourlySchedule().flatMap((b) => b.allApplicants);
        const total = allApps.length;
        const advised = hourlySchedule().reduce((acc, b) => acc + b.advisedCount, 0);
        const inProg = hourlySchedule().reduce((acc, b) => acc + b.inProgressCount, 0);
        const waiting = hourlySchedule().reduce((acc, b) => acc + b.waitingCount, 0);
        const referred = hourlySchedule().reduce((acc, b) => acc + (b.deferredCount + b.referredCount), 0);
        const rooms = Array.from(new Set(hourlySchedule().flatMap((b) => Array.from(b.rooms)))).join(", ");
        return {
          title: "Today's Complete Schedule",
          subtitle: `${dashboard?.today_schedule?.length ?? 0} sessions across ${rooms || "all counseling rooms"}`,
          totalApplicants: total,
          advisedCount: advised,
          inProgressCount: inProg,
          waitingCount: waiting,
          referredCount: referred,
          completionRate: total > 0 ? Math.round(advised / total * 100) : 0,
          allApplicants: allApps,
          sessions: dashboard?.today_schedule ?? []
        };
      }
    });
    const filteredBatchApplicants = derived(() => {
      const pool = activeHourData()?.allApplicants ?? [];
      const query = batchSearchQuery.trim().toLowerCase();
      return pool.filter((applicant) => {
        consultationStatus(applicant);
        if (query) {
          const name = String(applicant.name ?? "").toLowerCase();
          const code = String(applicant.reference_code ?? "").toLowerCase();
          const room = String(applicant.room ?? "").toLowerCase();
          if (!name.includes(query) && !code.includes(query) && !room.includes(query)) {
            return false;
          }
        }
        return true;
      });
    });
    const batchPageCount = derived(() => Math.max(1, Math.ceil(filteredBatchApplicants().length / batchPageSize)));
    const currentBatchPage = derived(() => Math.min(batchPage, batchPageCount()));
    const pagedBatchApplicants = derived(() => filteredBatchApplicants().slice((currentBatchPage() - 1) * batchPageSize, currentBatchPage() * batchPageSize));
    const consultationContext = derived(() => {
      const sched = dashboard?.stats?.today_scheduled ?? dashboard?.scheduled_count ?? 0;
      const unassigned = dashboard?.stats?.not_yet_scheduled ?? dashboard?.unassigned_count ?? 0;
      const inProg = dashboard?.stats?.sessions_open ?? dashboard?.in_progress_count ?? 0;
      const advised = dashboard?.stats?.today_scheduled ? Math.max(0, dashboard.stats.today_scheduled - (dashboard.stats.pending_advise ?? 0)) : dashboard?.advised_count ?? 0;
      const reAdv = dashboard?.stats?.re_advising ?? dashboard?.re_advising_count ?? 0;
      const sessionsLen = dashboard?.today_schedule?.length ?? 0;
      const safetyKey = `${page_state}:${sched}:${unassigned}:${inProg}:${advised}:${reAdv}:${sessionsLen}`;
      return {
        entityKey: "consultation-hub",
        safetyKey,
        freshness: "current",
        pageState: page_state,
        scheduledCount: sched,
        unassignedCount: unassigned,
        inProgressCount: inProg,
        advisedCount: advised,
        reAdvisingCount: reAdv,
        todaySessionsCount: sessionsLen,
        canPlan: Boolean(can_plan)
      };
    });
    function formatDate(iso) {
      if (!iso) return null;
      return new Date(iso).toLocaleDateString(void 0, { month: "short", day: "numeric", year: "numeric" });
    }
    const todayAdvisedCount = derived(() => dashboard?.stats?.today_scheduled ? Math.max(0, dashboard.stats.today_scheduled - (dashboard.stats.pending_advise ?? 0)) : 0);
    const overallCompletionPercent = derived(() => dashboard?.stats?.today_scheduled > 0 ? Math.round(todayAdvisedCount() / dashboard.stats.today_scheduled * 100) : 0);
    let $$settled = true;
    let $$inner_renderer;
    function $$render_inner($$renderer3) {
      head("1ugs0ez", $$renderer3, ($$renderer4) => {
        $$renderer4.title(($$renderer5) => {
          $$renderer5.push(`<title>Consultation &amp; Counselling · NEXIAM</title>`);
        });
      });
      StaffLayout($$renderer3, {
        breadcrumbs: [
          { label: "Counselling", href: "/consultation/sessions" },
          { label: "Consultation" }
        ],
        children: ($$renderer4) => {
          $$renderer4.push(`<div class="min-w-0 space-y-6 p-4 pb-[calc(7rem+env(safe-area-inset-bottom))] sm:p-6 sm:pb-[calc(7rem+env(safe-area-inset-bottom))] lg:pb-6">`);
          {
            let actions = function($$renderer5) {
              $$renderer5.push(`<div class="flex flex-wrap items-center gap-2">`);
              if (page_state === "ready" && dashboard) {
                $$renderer5.push("<!--[0-->");
                Button($$renderer5, {
                  variant: "default",
                  class: "min-h-10 gap-2 font-semibold shadow-sm",
                  "data-tour": "direct-consult-button",
                  onclick: () => applicantLookupOpen = true,
                  children: ($$renderer6) => {
                    User_plus($$renderer6, { class: "size-4" });
                    $$renderer6.push(`<!----> <span>Quick Lookup &amp; Scanner</span>`);
                  },
                  $$slots: { default: true }
                });
              } else {
                $$renderer5.push("<!--[-1-->");
              }
              $$renderer5.push(`<!--]--> `);
              if (can_plan) {
                $$renderer5.push("<!--[0-->");
                Link($$renderer5, {
                  href: sessionsUrl,
                  children: ($$renderer6) => {
                    Button($$renderer6, {
                      variant: "outline",
                      class: "min-h-10 gap-2 font-semibold",
                      children: ($$renderer7) => {
                        Calendar($$renderer7, { class: "size-4" });
                        $$renderer7.push(`<!----> <span>Manage sessions</span>`);
                      },
                      $$slots: { default: true }
                    });
                  },
                  $$slots: { default: true }
                });
              } else {
                $$renderer5.push("<!--[-1-->");
              }
              $$renderer5.push(`<!--]--> `);
              if (page_state === "ready" && (settings?.policy_url || settings?.admission_support_url)) {
                $$renderer5.push("<!--[0-->");
                $$renderer5.push(`<details class="relative"><summary class="flex size-11 cursor-pointer list-none items-center justify-center rounded-md border bg-background text-foreground hover:bg-muted focus-visible:outline focus-visible:outline-2 focus-visible:outline-ring" aria-label="Consultation settings" title="Consultation settings">`);
                Settings_2($$renderer5, { class: "size-4", "aria-hidden": "true" });
                $$renderer5.push(`<!----></summary> <div class="absolute right-0 z-30 mt-2 w-64 rounded-lg border bg-background p-3 shadow-lg" aria-label="Consultation settings destinations"><p class="font-medium">Consultation settings</p> <p class="mt-1 text-sm text-muted-foreground">Choose a configuration area you can access. These are not global preferences.</p> <div class="mt-3 grid gap-1">`);
                if (settings.policy_url) {
                  $$renderer5.push("<!--[0-->");
                  Link($$renderer5, {
                    href: settings.policy_url,
                    class: "flex min-h-11 items-center rounded-md px-2 text-sm hover:bg-muted focus-visible:outline focus-visible:outline-2 focus-visible:outline-ring",
                    children: ($$renderer6) => {
                      $$renderer6.push(`<!---->Policy settings`);
                    },
                    $$slots: { default: true }
                  });
                } else {
                  $$renderer5.push("<!--[-1-->");
                }
                $$renderer5.push(`<!--]--> `);
                if (settings.admission_support_url) {
                  $$renderer5.push("<!--[0-->");
                  Link($$renderer5, {
                    href: settings.admission_support_url,
                    class: "flex min-h-11 items-center rounded-md px-2 text-sm hover:bg-muted focus-visible:outline focus-visible:outline-2 focus-visible:outline-ring",
                    children: ($$renderer6) => {
                      $$renderer6.push(`<!---->Admission support`);
                    },
                    $$slots: { default: true }
                  });
                } else {
                  $$renderer5.push("<!--[-1-->");
                }
                $$renderer5.push(`<!--]--></div></div></details>`);
              } else {
                $$renderer5.push("<!--[-1-->");
              }
              $$renderer5.push(`<!--]--></div>`);
            };
            PageHeaderBar($$renderer4, {
              title: "Consultation & Counselling",
              description: "Monitor daily consultation queues, review candidate score percentiles, and confirm degree program recommendations.",
              tourKey: "consultation-hub",
              context: consultationContext(),
              actions
            });
          }
          $$renderer4.push(`<!----> `);
          E2PageState($$renderer4, { state: page_state });
          $$renderer4.push(`<!----> `);
          if (page_state === "ready" && dashboard) {
            $$renderer4.push("<!--[0-->");
            $$renderer4.push(`<div class="grid grid-cols-2 gap-3 sm:grid-cols-2 lg:grid-cols-12" data-tour="consultation-metrics-grid">`);
            BentoCard($$renderer4, {
              class: "col-span-2 p-4 lg:col-span-4 flex flex-col justify-between",
              children: ($$renderer5) => {
                $$renderer5.push(`<div><div class="flex items-center justify-between"><span class="text-[11px] font-mono font-bold uppercase tracking-wider text-muted-foreground flex items-center gap-1.5">`);
                User_check($$renderer5, { class: "size-3.5 text-primary" });
                $$renderer5.push(`<!----> Today's Scheduled</span> `);
                Badge($$renderer5, {
                  variant: "outline",
                  class: "font-mono text-[10px] text-emerald-600 dark:text-emerald-400 border-emerald-500/20",
                  children: ($$renderer6) => {
                    $$renderer6.push(`<!---->${escape_html(overallCompletionPercent())}% Done`);
                  },
                  $$slots: { default: true }
                });
                $$renderer5.push(`<!----></div> <div class="mt-2 flex items-baseline gap-2"><span class="font-mono text-3xl font-extrabold tabular-nums tracking-tight text-foreground">${escape_html(dashboard.stats.today_scheduled)}</span> <span class="text-xs text-muted-foreground">examinees (${escape_html(todayAdvisedCount())} advised)</span></div></div> <div class="mt-3"><div class="h-1.5 w-full overflow-hidden rounded-full bg-muted/60"><div class="h-full rounded-full bg-primary transition-all duration-500"${attr_style(`width: ${stringify(overallCompletionPercent())}%`)}></div></div></div>`);
              },
              $$slots: { default: true }
            });
            $$renderer4.push(`<!----> `);
            BentoCard($$renderer4, {
              class: "col-span-1 p-4 lg:col-span-2",
              children: ($$renderer5) => {
                $$renderer5.push(`<span class="text-[11px] font-mono font-bold uppercase tracking-wider text-muted-foreground flex items-center gap-1.5">`);
                Clock_3($$renderer5, { class: "size-3.5 text-amber-500" });
                $$renderer5.push(`<!----> Pending Advise</span> <p class="mt-2 font-mono text-3xl font-extrabold tabular-nums tracking-tight text-amber-600 dark:text-amber-400">${escape_html(dashboard.stats.pending_advise)}</p> <p class="mt-1 text-[11px] text-muted-foreground truncate">Awaiting decision</p>`);
              },
              $$slots: { default: true }
            });
            $$renderer4.push(`<!----> `);
            BentoCard($$renderer4, {
              class: "col-span-1 p-4 lg:col-span-2",
              children: ($$renderer5) => {
                $$renderer5.push(`<span class="text-[11px] font-mono font-bold uppercase tracking-wider text-muted-foreground flex items-center gap-1.5">`);
                Circle_alert($$renderer5, { class: "size-3.5 text-blue-500" });
                $$renderer5.push(`<!----> Re-Advising</span> <p class="mt-2 font-mono text-3xl font-extrabold tabular-nums tracking-tight text-foreground">${escape_html(dashboard.stats.re_advising ?? 0)}</p> <p class="mt-1 text-[11px] text-muted-foreground truncate">Needs alternative</p>`);
              },
              $$slots: { default: true }
            });
            $$renderer4.push(`<!----> `);
            BentoCard($$renderer4, {
              class: "col-span-1 p-4 lg:col-span-2",
              children: ($$renderer5) => {
                $$renderer5.push(`<span class="text-[11px] font-mono font-bold uppercase tracking-wider text-muted-foreground flex items-center gap-1.5">`);
                Users_round($$renderer5, { class: "size-3.5 text-purple-500" });
                $$renderer5.push(`<!----> Walk-In Ready</span> <p class="mt-2 font-mono text-3xl font-extrabold tabular-nums tracking-tight text-foreground">${escape_html(dashboard.stats.not_yet_scheduled)}</p> <p class="mt-1 text-[11px] text-muted-foreground truncate">Unscheduled candidates</p>`);
              },
              $$slots: { default: true }
            });
            $$renderer4.push(`<!----> `);
            BentoCard($$renderer4, {
              class: "col-span-1 p-4 lg:col-span-2",
              children: ($$renderer5) => {
                $$renderer5.push(`<span class="text-[11px] font-mono font-bold uppercase tracking-wider text-muted-foreground flex items-center gap-1.5">`);
                Door_open($$renderer5, { class: "size-3.5 text-emerald-500" });
                $$renderer5.push(`<!----> Rooms Active</span> <div class="mt-2 flex items-center gap-2"><span class="font-mono text-2xl font-extrabold tabular-nums tracking-tight text-foreground">${escape_html(dashboard.stats.sessions_open)}</span> <span class="text-xs text-muted-foreground font-mono">/ ${escape_html(dashboard.stats.sessions_closed)} closed</span> `);
                if (dashboard.stats.sessions_open > 0) {
                  $$renderer5.push("<!--[0-->");
                  $$renderer5.push(`<span class="relative flex h-2 w-2 ml-auto" title="Active session in progress"><span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-emerald-400 opacity-75"></span> <span class="relative inline-flex rounded-full h-2 w-2 bg-emerald-500"></span></span>`);
                } else {
                  $$renderer5.push("<!--[-1-->");
                }
                $$renderer5.push(`<!--]--></div> <p class="mt-1 text-[11px] text-muted-foreground truncate">Counselling suites</p>`);
              },
              $$slots: { default: true }
            });
            $$renderer4.push(`<!----></div> <div class="flex items-center rounded-lg border bg-muted/40 p-1 lg:hidden"><button type="button"${attr_class(`flex-1 rounded-md py-1.5 text-center text-xs font-semibold transition-colors ${"bg-background text-foreground shadow-xs"}`)}>Schedule (${escape_html(dashboard.stats.today_scheduled)})</button> <button type="button"${attr_class(`flex-1 rounded-md py-1.5 text-center text-xs font-semibold transition-colors ${"text-muted-foreground hover:text-foreground"}`)}>Walk-In Desk (${escape_html(dashboard.not_yet_scheduled?.length ?? 0)})</button> <button type="button"${attr_class(`flex-1 rounded-md py-1.5 text-center text-xs font-semibold transition-colors ${"text-muted-foreground hover:text-foreground"}`)}>Re-Advising (${escape_html(dashboard.re_advising?.length ?? 0)})</button></div> <div class="grid grid-cols-1 gap-6 lg:grid-cols-12"><div${attr_class(`space-y-6 lg:col-span-8 ${""}`)}>`);
            if (Card) {
              $$renderer4.push("<!--[-->");
              Card($$renderer4, {
                class: "overflow-hidden border-border/80 shadow-xs",
                children: ($$renderer5) => {
                  if (Card_header) {
                    $$renderer5.push("<!--[-->");
                    Card_header($$renderer5, {
                      class: "border-b border-border/40 pb-4",
                      children: ($$renderer6) => {
                        $$renderer6.push(`<div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between"><div>`);
                        if (Card_title) {
                          $$renderer6.push("<!--[-->");
                          Card_title($$renderer6, {
                            class: "flex items-center gap-2 text-base font-bold text-foreground",
                            children: ($$renderer7) => {
                              Clock_3($$renderer7, { class: "size-4 text-primary" });
                              $$renderer7.push(`<!----> <span>Today's Consultation Schedule &amp; Hourly Breakdown</span>`);
                            },
                            $$slots: { default: true }
                          });
                          $$renderer6.push("<!--]-->");
                        } else {
                          $$renderer6.push("<!--[!-->");
                          $$renderer6.push("<!--]-->");
                        }
                        $$renderer6.push(` `);
                        if (Card_description) {
                          $$renderer6.push("<!--[-->");
                          Card_description($$renderer6, {
                            class: "text-xs",
                            children: ($$renderer7) => {
                              $$renderer7.push(`<!---->${escape_html(formatDate((/* @__PURE__ */ new Date()).toISOString()))} — Filter by hour/batch or examine the combined room queues.`);
                            },
                            $$slots: { default: true }
                          });
                          $$renderer6.push("<!--]-->");
                        } else {
                          $$renderer6.push("<!--[!-->");
                          $$renderer6.push("<!--]-->");
                        }
                        $$renderer6.push(`</div> `);
                        if (hourlySchedule().length) {
                          $$renderer6.push("<!--[0-->");
                          Badge($$renderer6, {
                            variant: "outline",
                            class: "font-mono text-xs self-start sm:self-center",
                            children: ($$renderer7) => {
                              $$renderer7.push(`<!---->${escape_html(hourlySchedule().length)} Hourly ${escape_html(hourlySchedule().length === 1 ? "Slot" : "Slots")}`);
                            },
                            $$slots: { default: true }
                          });
                        } else {
                          $$renderer6.push("<!--[-1-->");
                        }
                        $$renderer6.push(`<!--]--></div> `);
                        if (hourlySchedule().length) {
                          $$renderer6.push("<!--[0-->");
                          $$renderer6.push(`<div class="mt-4 flex items-center gap-2 overflow-x-auto pb-1 pt-1 scrollbar-none" role="tablist" aria-label="Hourly schedule slots"><button type="button" role="tab"${attr("aria-selected", selectedHour === "all")}${attr_class(`flex shrink-0 items-center gap-2 rounded-lg border px-3 py-1.5 text-xs font-medium transition-all ${"border-primary bg-primary text-primary-foreground shadow-xs font-semibold"}`)}>`);
                          Layers($$renderer6, { class: "size-3.5" });
                          $$renderer6.push(`<!----> <span>All Day</span> `);
                          Badge($$renderer6, {
                            variant: "secondary",
                            class: "ml-0.5 px-1.5 py-0 text-[10px] font-mono",
                            children: ($$renderer7) => {
                              $$renderer7.push(`<!---->${escape_html(dashboard.stats.today_scheduled)}`);
                            },
                            $$slots: { default: true }
                          });
                          $$renderer6.push(`<!----></button> <!--[-->`);
                          const each_array = ensure_array_like(hourlySchedule());
                          for (let $$index = 0, $$length = each_array.length; $$index < $$length; $$index++) {
                            let hour = each_array[$$index];
                            $$renderer6.push(`<button type="button" role="tab"${attr("aria-selected", selectedHour === hour.hourKey)}${attr_class(`flex shrink-0 items-center gap-2 rounded-lg border px-3 py-1.5 text-xs font-medium transition-all ${selectedHour === hour.hourKey ? "border-primary bg-primary text-primary-foreground shadow-xs font-semibold" : "border-border/80 bg-background text-muted-foreground hover:bg-muted/60 hover:text-foreground"}`)}>`);
                            if (hour.isCurrentHour) {
                              $$renderer6.push("<!--[0-->");
                              $$renderer6.push(`<span class="relative flex h-2 w-2"><span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-emerald-400 opacity-75"></span> <span class="relative inline-flex rounded-full h-2 w-2 bg-emerald-500"></span></span>`);
                            } else {
                              $$renderer6.push("<!--[-1-->");
                            }
                            $$renderer6.push(`<!--]--> <span>${escape_html(hour.hourKey)}</span> <span${attr_class(`rounded-full px-1.5 py-0.2 text-[10px] font-mono font-bold ${selectedHour === hour.hourKey ? "bg-primary-foreground/20 text-primary-foreground" : "bg-muted text-muted-foreground"}`)}>${escape_html(hour.totalApplicants)}</span> `);
                            if (hour.completionRate === 100 && hour.totalApplicants > 0) {
                              $$renderer6.push("<!--[0-->");
                              Circle_check($$renderer6, { class: "size-3 text-emerald-400" });
                            } else {
                              $$renderer6.push("<!--[-1-->");
                            }
                            $$renderer6.push(`<!--]--></button>`);
                          }
                          $$renderer6.push(`<!--]--></div>`);
                        } else {
                          $$renderer6.push("<!--[-1-->");
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
                  if (Card_content) {
                    $$renderer5.push("<!--[-->");
                    Card_content($$renderer5, {
                      class: "space-y-4 pt-4",
                      children: ($$renderer6) => {
                        if (activeHourData() && activeHourData().totalApplicants > 0) {
                          $$renderer6.push("<!--[0-->");
                          $$renderer6.push(`<div class="rounded-xl border border-border/60 bg-muted/20 p-4"><div class="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between"><div><h4 class="text-sm font-bold text-foreground flex items-center gap-2"><span>${escape_html(activeHourData().title)}</span> `);
                          {
                            $$renderer6.push("<!--[-1-->");
                          }
                          $$renderer6.push(`<!--]--></h4> <p class="text-xs text-muted-foreground mt-0.5">${escape_html(activeHourData().advisedCount)} of ${escape_html(activeHourData().totalApplicants)} advised · ${escape_html(activeHourData().waitingCount)} waiting · ${escape_html(activeHourData().inProgressCount)} in progress</p></div> <div class="flex items-center gap-3"><span class="font-mono text-xs font-bold text-foreground">${escape_html(activeHourData().completionRate)}% Done</span> <div class="w-24 sm:w-32 h-2 rounded-full bg-muted overflow-hidden flex"><div class="h-full bg-emerald-500 transition-all duration-300"${attr_style(`width: ${stringify(activeHourData().completionRate)}%`)}></div> <div class="h-full bg-amber-400 transition-all duration-300"${attr_style(`width: ${stringify(activeHourData().totalApplicants > 0 ? activeHourData().inProgressCount / activeHourData().totalApplicants * 100 : 0)}%`)}></div></div></div></div></div> <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between"><div class="relative flex-1">`);
                          Search($$renderer6, {
                            class: "pointer-events-none absolute left-3 top-1/2 size-3.5 -translate-y-1/2 text-muted-foreground"
                          });
                          $$renderer6.push(`<!----> `);
                          Input($$renderer6, {
                            oninput: () => batchPage = 1,
                            placeholder: "Search examinee by name, reference code, or room...",
                            class: "h-9 pl-8 pr-8 text-xs",
                            "aria-label": "Filter examinees in active batch",
                            get value() {
                              return batchSearchQuery;
                            },
                            set value($$value) {
                              batchSearchQuery = $$value;
                              $$settled = false;
                            }
                          });
                          $$renderer6.push(`<!----> `);
                          if (batchSearchQuery) {
                            $$renderer6.push("<!--[0-->");
                            $$renderer6.push(`<button type="button" class="absolute right-2.5 top-1/2 -translate-y-1/2 text-muted-foreground hover:text-foreground" aria-label="Clear filter">`);
                            X($$renderer6, { class: "size-3.5" });
                            $$renderer6.push(`<!----></button>`);
                          } else {
                            $$renderer6.push("<!--[-1-->");
                          }
                          $$renderer6.push(`<!--]--></div> <div class="flex items-center gap-1 overflow-x-auto pb-0.5 sm:shrink-0" role="group" aria-label="Filter by advising status"><button type="button"${attr_class(`rounded-md px-2.5 py-1 text-[11px] font-semibold transition-colors ${"bg-primary text-primary-foreground"}`)}>All (${escape_html(activeHourData().totalApplicants)})</button> <button type="button"${attr_class(`rounded-md px-2.5 py-1 text-[11px] font-semibold transition-colors ${"bg-muted/60 text-muted-foreground hover:bg-muted"}`)}>Waiting (${escape_html(activeHourData().waitingCount)})</button> <button type="button"${attr_class(`rounded-md px-2.5 py-1 text-[11px] font-semibold transition-colors ${"bg-muted/60 text-muted-foreground hover:bg-muted"}`)}>In Progress (${escape_html(activeHourData().inProgressCount)})</button> <button type="button"${attr_class(`rounded-md px-2.5 py-1 text-[11px] font-semibold transition-colors ${"bg-muted/60 text-muted-foreground hover:bg-muted"}`)}>Advised (${escape_html(activeHourData().advisedCount)})</button></div></div> `);
                          if (pagedBatchApplicants().length) {
                            $$renderer6.push("<!--[0-->");
                            $$renderer6.push(`<div class="grid grid-cols-1 gap-2.5 sm:grid-cols-2"><!--[-->`);
                            const each_array_1 = ensure_array_like(pagedBatchApplicants());
                            for (let $$index_1 = 0, $$length = each_array_1.length; $$index_1 < $$length; $$index_1++) {
                              let applicant = each_array_1[$$index_1];
                              const status = consultationStatus(applicant);
                              $$renderer6.push(`<div class="group flex flex-col justify-between rounded-lg border border-border/70 bg-card p-3 transition-all hover:border-primary/40 hover:shadow-xs"><div><div class="flex items-start justify-between gap-2"><div class="min-w-0"><div class="flex items-center gap-1.5"><span class="font-mono text-xs font-bold text-foreground">${escape_html(applicant.reference_code)}</span> `);
                              if (applicant.room) {
                                $$renderer6.push("<!--[0-->");
                                $$renderer6.push(`<span class="text-[10px] text-muted-foreground font-medium">· ${escape_html(applicant.room)}</span>`);
                              } else {
                                $$renderer6.push("<!--[-1-->");
                              }
                              $$renderer6.push(`<!--]--></div> <p class="truncate text-sm font-semibold text-foreground mt-0.5">${escape_html(applicant.name ?? "Unregistered Examinee")}</p></div> `);
                              Badge($$renderer6, {
                                variant: consultationStatusVariant(status),
                                class: consultationStatusClass(status),
                                children: ($$renderer7) => {
                                  $$renderer7.push(`<!---->${escape_html(status)}`);
                                },
                                $$slots: { default: true }
                              });
                              $$renderer6.push(`<!----></div></div> <div class="mt-3 flex flex-wrap items-center justify-between gap-2 border-t border-border/40 pt-2"><span class="text-[11px] text-muted-foreground">`);
                              if (applicant.session_time) {
                                $$renderer6.push("<!--[0-->");
                                $$renderer6.push(`Slot: ${escape_html(applicant.session_time)}`);
                              } else {
                                $$renderer6.push("<!--[-1-->");
                              }
                              $$renderer6.push(`<!--]--></span> <div class="flex flex-wrap items-center gap-2"><button type="button" class="inline-flex items-center gap-1 text-xs font-semibold text-primary group-hover:underline focus-visible:outline-none"><span>${escape_html(status === "Advised" ? "View Decision" : "Start Advising")}</span> `);
                              Arrow_right($$renderer6, {
                                class: "size-3 transition-transform group-hover:translate-x-0.5"
                              });
                              $$renderer6.push(`<!----></button></div></div></div>`);
                            }
                            $$renderer6.push(`<!--]--></div> <div class="flex flex-col gap-2 pt-2 sm:flex-row sm:items-center sm:justify-between border-t border-border/40"><p class="text-xs text-muted-foreground" aria-live="polite">Showing ${escape_html((currentBatchPage() - 1) * batchPageSize + 1)}–${escape_html(Math.min(currentBatchPage() * batchPageSize, filteredBatchApplicants().length))} of ${escape_html(filteredBatchApplicants().length)} examinees `);
                            if (batchSearchQuery || batchStatusFilter !== "all") {
                              $$renderer6.push("<!--[0-->");
                              $$renderer6.push(`<span class="text-primary font-medium">(Filtered)</span>`);
                            } else {
                              $$renderer6.push("<!--[-1-->");
                            }
                            $$renderer6.push(`<!--]--></p> `);
                            if (batchPageCount() > 1) {
                              $$renderer6.push("<!--[0-->");
                              $$renderer6.push(`<div class="flex items-center gap-1 self-end sm:self-center">`);
                              Button($$renderer6, {
                                variant: "outline",
                                size: "sm",
                                class: "h-7 px-2 text-xs",
                                disabled: currentBatchPage() <= 1,
                                onclick: () => batchPage = Math.max(1, currentBatchPage() - 1),
                                children: ($$renderer7) => {
                                  Chevron_left($$renderer7, { class: "size-3 mr-0.5" });
                                  $$renderer7.push(`<!----> Previous`);
                                },
                                $$slots: { default: true }
                              });
                              $$renderer6.push(`<!----> <span class="px-2 text-xs text-muted-foreground">Page ${escape_html(currentBatchPage())} of ${escape_html(batchPageCount())}</span> `);
                              Button($$renderer6, {
                                variant: "outline",
                                size: "sm",
                                class: "h-7 px-2 text-xs",
                                disabled: currentBatchPage() >= batchPageCount(),
                                onclick: () => batchPage = Math.min(batchPageCount(), currentBatchPage() + 1),
                                children: ($$renderer7) => {
                                  $$renderer7.push(`<!---->Next `);
                                  Chevron_right($$renderer7, { class: "size-3 ml-0.5" });
                                  $$renderer7.push(`<!---->`);
                                },
                                $$slots: { default: true }
                              });
                              $$renderer6.push(`<!----></div>`);
                            } else {
                              $$renderer6.push("<!--[-1-->");
                            }
                            $$renderer6.push(`<!--]--></div>`);
                          } else {
                            $$renderer6.push("<!--[-1-->");
                            $$renderer6.push(`<div class="rounded-lg border border-dashed py-10 text-center text-sm text-muted-foreground">No examinees found in this batch matching the selected filter.</div>`);
                          }
                          $$renderer6.push(`<!--]-->`);
                        } else {
                          $$renderer6.push("<!--[-1-->");
                          $$renderer6.push(`<div class="rounded-xl border border-dashed py-12 text-center text-sm text-muted-foreground"><p class="font-medium text-foreground">No consultation sessions scheduled for today.</p> <p class="mt-1 text-xs">Examinees can still be assisted via Direct Walk-in Intake or booked into new sessions.</p> `);
                          if (can_plan) {
                            $$renderer6.push("<!--[0-->");
                            Link($$renderer6, {
                              href: sessionsUrl,
                              class: "mt-4 inline-block",
                              children: ($$renderer7) => {
                                Button($$renderer7, {
                                  size: "sm",
                                  variant: "outline",
                                  class: "gap-1.5",
                                  children: ($$renderer8) => {
                                    Calendar($$renderer8, { class: "size-3.5" });
                                    $$renderer8.push(`<!----> Create Consultation Session`);
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
            $$renderer4.push(`</div>  <div class="space-y-6 lg:col-span-4">`);
            if (Card) {
              $$renderer4.push("<!--[-->");
              Card($$renderer4, {
                class: `border-border/80 shadow-xs ${"hidden lg:block"}`,
                children: ($$renderer5) => {
                  if (Card_header) {
                    $$renderer5.push("<!--[-->");
                    Card_header($$renderer5, {
                      class: "pb-3 border-b border-border/40",
                      children: ($$renderer6) => {
                        $$renderer6.push(`<div class="flex items-center justify-between"><div class="flex items-center gap-2"><div class="flex size-7 items-center justify-center rounded-lg bg-primary/10 text-primary">`);
                        Users_round($$renderer6, { class: "size-4" });
                        $$renderer6.push(`<!----></div> <div>`);
                        if (Card_title) {
                          $$renderer6.push("<!--[-->");
                          Card_title($$renderer6, {
                            class: "text-sm font-bold text-foreground",
                            children: ($$renderer7) => {
                              $$renderer7.push(`<!---->Walk-In Candidates Queue`);
                            },
                            $$slots: { default: true }
                          });
                          $$renderer6.push("<!--]-->");
                        } else {
                          $$renderer6.push("<!--[!-->");
                          $$renderer6.push("<!--]-->");
                        }
                        $$renderer6.push(` `);
                        if (Card_description) {
                          $$renderer6.push("<!--[-->");
                          Card_description($$renderer6, {
                            class: "text-[11px]",
                            children: ($$renderer7) => {
                              $$renderer7.push(`<!---->Result-ready examinees awaiting advising without appointment.`);
                            },
                            $$slots: { default: true }
                          });
                          $$renderer6.push("<!--]-->");
                        } else {
                          $$renderer6.push("<!--[!-->");
                          $$renderer6.push("<!--]-->");
                        }
                        $$renderer6.push(`</div></div> `);
                        Badge($$renderer6, {
                          variant: "outline",
                          class: "font-mono text-xs font-bold text-emerald-600 dark:text-emerald-400 border-emerald-500/30 bg-emerald-50 dark:bg-emerald-950/30",
                          children: ($$renderer7) => {
                            $$renderer7.push(`<!---->${escape_html(dashboard.stats.not_yet_scheduled)} Ready`);
                          },
                          $$slots: { default: true }
                        });
                        $$renderer6.push(`<!----></div>`);
                      },
                      $$slots: { default: true }
                    });
                    $$renderer5.push("<!--]-->");
                  } else {
                    $$renderer5.push("<!--[!-->");
                    $$renderer5.push("<!--]-->");
                  }
                  $$renderer5.push(` `);
                  if (Card_content) {
                    $$renderer5.push("<!--[-->");
                    Card_content($$renderer5, {
                      class: "space-y-3 pt-3",
                      children: ($$renderer6) => {
                        if (unscheduledPageData().items.length) {
                          $$renderer6.push("<!--[0-->");
                          $$renderer6.push(`<ul class="divide-y rounded-xl border border-border/60 bg-card overflow-hidden"><!--[-->`);
                          const each_array_2 = ensure_array_like(unscheduledPageData().items);
                          for (let $$index_2 = 0, $$length = each_array_2.length; $$index_2 < $$length; $$index_2++) {
                            let a = each_array_2[$$index_2];
                            $$renderer6.push(`<li class="p-3 transition-colors hover:bg-muted/30"><div class="flex items-start justify-between gap-2"><div class="min-w-0"><p class="truncate text-xs font-semibold text-foreground">${escape_html(a.name)}</p> <p class="font-mono text-[10px] text-muted-foreground">${escape_html(a.reference_code)}</p></div> `);
                            Badge($$renderer6, {
                              variant: "outline",
                              class: "text-[10px] text-emerald-700 dark:text-emerald-400 border-emerald-500/20 bg-emerald-50 dark:bg-emerald-950/30",
                              children: ($$renderer7) => {
                                $$renderer7.push(`<!---->Ready`);
                              },
                              $$slots: { default: true }
                            });
                            $$renderer6.push(`<!----></div> <div class="mt-2 flex items-center justify-between border-t border-border/30 pt-2"><span class="text-[10px] text-muted-foreground">Walk-in arrival</span> <button type="button" class="inline-flex items-center gap-1 text-xs font-semibold text-primary hover:underline"><span>Start Advising</span> `);
                            Arrow_right($$renderer6, { class: "size-3" });
                            $$renderer6.push(`<!----></button></div></li>`);
                          }
                          $$renderer6.push(`<!--]--></ul> `);
                          if (unscheduledPageData().pageCount > 1) {
                            $$renderer6.push("<!--[0-->");
                            $$renderer6.push(`<div class="flex items-center justify-between pt-1"><span class="text-[11px] text-muted-foreground">Page ${escape_html(unscheduledPageData().currentPage)} of ${escape_html(unscheduledPageData().pageCount)}</span> <div class="flex gap-1">`);
                            Button($$renderer6, {
                              variant: "outline",
                              size: "sm",
                              class: "h-6 px-2 text-xs",
                              disabled: unscheduledPageData().currentPage <= 1,
                              onclick: () => unscheduledPage = Math.max(1, unscheduledPageData().currentPage - 1),
                              children: ($$renderer7) => {
                                $$renderer7.push(`<!---->Prev`);
                              },
                              $$slots: { default: true }
                            });
                            $$renderer6.push(`<!----> `);
                            Button($$renderer6, {
                              variant: "outline",
                              size: "sm",
                              class: "h-6 px-2 text-xs",
                              disabled: unscheduledPageData().currentPage >= unscheduledPageData().pageCount,
                              onclick: () => unscheduledPage = Math.min(unscheduledPageData().pageCount, unscheduledPageData().currentPage + 1),
                              children: ($$renderer7) => {
                                $$renderer7.push(`<!---->Next`);
                              },
                              $$slots: { default: true }
                            });
                            $$renderer6.push(`<!----></div></div>`);
                          } else {
                            $$renderer6.push("<!--[-1-->");
                          }
                          $$renderer6.push(`<!--]-->`);
                        } else {
                          $$renderer6.push("<!--[-1-->");
                          $$renderer6.push(`<div class="rounded-xl border border-dashed py-8 px-3 text-center text-xs text-muted-foreground"><p class="font-medium text-foreground">No walk-in examinees in queue.</p> <p class="mt-1">All result-ready examinees are either scheduled or completed.</p></div>`);
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
            $$renderer4.push(` `);
            if (dashboard?.re_advising?.length) {
              $$renderer4.push("<!--[0-->");
              if (Card) {
                $$renderer4.push("<!--[-->");
                Card($$renderer4, {
                  class: `border-amber-400/40 bg-amber-500/5 shadow-xs ${"hidden lg:block"}`,
                  children: ($$renderer5) => {
                    if (Card_header) {
                      $$renderer5.push("<!--[-->");
                      Card_header($$renderer5, {
                        class: "pb-3",
                        children: ($$renderer6) => {
                          $$renderer6.push(`<div class="flex items-center justify-between">`);
                          if (Card_title) {
                            $$renderer6.push("<!--[-->");
                            Card_title($$renderer6, {
                              class: "flex items-center gap-2 text-sm font-bold text-amber-950 dark:text-amber-200",
                              children: ($$renderer7) => {
                                Circle_alert($$renderer7, { class: "size-4 text-amber-600 dark:text-amber-400" });
                                $$renderer7.push(`<!----> <span>Re-Advising Queue</span>`);
                              },
                              $$slots: { default: true }
                            });
                            $$renderer6.push("<!--]-->");
                          } else {
                            $$renderer6.push("<!--[!-->");
                            $$renderer6.push("<!--]-->");
                          }
                          $$renderer6.push(` `);
                          Badge($$renderer6, {
                            variant: "secondary",
                            class: "font-mono text-xs",
                            children: ($$renderer7) => {
                              $$renderer7.push(`<!---->${escape_html(dashboard.re_advising.length)}`);
                            },
                            $$slots: { default: true }
                          });
                          $$renderer6.push(`<!----></div> `);
                          if (Card_description) {
                            $$renderer6.push("<!--[-->");
                            Card_description($$renderer6, {
                              class: "text-xs",
                              children: ($$renderer7) => {
                                $$renderer7.push(`<!---->Declined by department head or deferred; ready for reassignment.`);
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
                    if (Card_content) {
                      $$renderer5.push("<!--[-->");
                      Card_content($$renderer5, {
                        class: "space-y-3",
                        children: ($$renderer6) => {
                          $$renderer6.push(`<div class="relative">`);
                          Search($$renderer6, {
                            class: "pointer-events-none absolute left-3 top-1/2 size-3.5 -translate-y-1/2 text-muted-foreground"
                          });
                          $$renderer6.push(`<!----> `);
                          Input($$renderer6, {
                            oninput: () => reAdvisingPage = 1,
                            placeholder: "Filter candidates...",
                            class: "h-8 pl-8 pr-3 text-xs",
                            "aria-label": "Filter re-advising candidates",
                            get value() {
                              return reAdvisingFilter;
                            },
                            set value($$value) {
                              reAdvisingFilter = $$value;
                              $$settled = false;
                            }
                          });
                          $$renderer6.push(`<!----></div> <ul class="divide-y rounded-md border border-amber-300/30 bg-card overflow-hidden">`);
                          const each_array_3 = ensure_array_like(reAdvisingPageData().items);
                          if (each_array_3.length !== 0) {
                            $$renderer6.push("<!--[-->");
                            for (let $$index_3 = 0, $$length = each_array_3.length; $$index_3 < $$length; $$index_3++) {
                              let applicant = each_array_3[$$index_3];
                              $$renderer6.push(`<li class="p-2.5 transition-colors hover:bg-muted/40"><div class="flex items-start justify-between gap-2"><div class="min-w-0"><p class="truncate text-xs font-semibold text-foreground">${escape_html(applicant.name)}</p> <p class="font-mono text-[10px] text-muted-foreground">${escape_html(applicant.reference_code)}</p></div> `);
                              Badge($$renderer6, {
                                variant: reAdvisingVariant(applicant.reason),
                                class: `text-[10px] ${stringify(reAdvisingClass(applicant.reason))}`,
                                children: ($$renderer7) => {
                                  $$renderer7.push(`<!---->${escape_html(reAdvisingReason(applicant.reason))}`);
                                },
                                $$slots: { default: true }
                              });
                              $$renderer6.push(`<!----></div> <div class="mt-2 flex flex-wrap justify-end gap-2">`);
                              Link($$renderer6, {
                                href: `/consultation/applicants/${applicant.attempt_id}`,
                                children: ($$renderer7) => {
                                  Button($$renderer7, {
                                    size: "sm",
                                    variant: "ghost",
                                    class: "h-6 px-2 text-[11px] text-primary font-semibold gap-1",
                                    children: ($$renderer8) => {
                                      $$renderer8.push(`<!---->Re-Advise `);
                                      Arrow_right($$renderer8, { class: "size-3" });
                                      $$renderer8.push(`<!---->`);
                                    },
                                    $$slots: { default: true }
                                  });
                                },
                                $$slots: { default: true }
                              });
                              $$renderer6.push(`<!----></div></li>`);
                            }
                          } else {
                            $$renderer6.push("<!--[!-->");
                            $$renderer6.push(`<li class="p-4 text-center text-xs text-muted-foreground">No candidates match filter.</li>`);
                          }
                          $$renderer6.push(`<!--]--></ul> `);
                          if (reAdvisingPageData().pageCount > 1) {
                            $$renderer6.push("<!--[0-->");
                            $$renderer6.push(`<div class="flex items-center justify-between pt-1"><span class="text-[11px] text-muted-foreground">Page ${escape_html(reAdvisingPageData().currentPage)} of ${escape_html(reAdvisingPageData().pageCount)}</span> <div class="flex gap-1">`);
                            Button($$renderer6, {
                              variant: "outline",
                              size: "sm",
                              class: "h-6 px-2 text-xs",
                              disabled: reAdvisingPageData().currentPage <= 1,
                              onclick: () => reAdvisingPage = Math.max(1, reAdvisingPageData().currentPage - 1),
                              children: ($$renderer7) => {
                                $$renderer7.push(`<!---->Prev`);
                              },
                              $$slots: { default: true }
                            });
                            $$renderer6.push(`<!----> `);
                            Button($$renderer6, {
                              variant: "outline",
                              size: "sm",
                              class: "h-6 px-2 text-xs",
                              disabled: reAdvisingPageData().currentPage >= reAdvisingPageData().pageCount,
                              onclick: () => reAdvisingPage = Math.min(reAdvisingPageData().pageCount, reAdvisingPageData().currentPage + 1),
                              children: ($$renderer7) => {
                                $$renderer7.push(`<!---->Next`);
                              },
                              $$slots: { default: true }
                            });
                            $$renderer6.push(`<!----></div></div>`);
                          } else {
                            $$renderer6.push("<!--[-1-->");
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
            } else {
              $$renderer4.push("<!--[-1-->");
            }
            $$renderer4.push(`<!--]--></div></div>`);
          } else {
            $$renderer4.push("<!--[-1-->");
          }
          $$renderer4.push(`<!--]--> `);
          if (page_state === "ready" && dashboard) {
            $$renderer4.push("<!--[0-->");
            ApplicantLookupDialog($$renderer4, {
              open: applicantLookupOpen,
              onOpenChange: (open) => applicantLookupOpen = open
            });
            $$renderer4.push(`<!----> `);
            ConsultationMobileDock($$renderer4, { mode: "index", onSearch: () => applicantLookupOpen = true });
            $$renderer4.push(`<!---->`);
          } else {
            $$renderer4.push("<!--[-1-->");
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
  Dashboard as default
};
