import { g as sanitize_props, i as spread_props, j as slot, h as head, e as escape_html, c as attr_class, B as Button, a as ensure_array_like, b as attr, d as derived, n as newIdempotencyKey } from "../app.js";
import { S as StaffLayout, C as Calendar, Q as QrScanner } from "./StaffLayout-CR-MZopa.js";
import { P as PageHeaderBar } from "./PageHeaderBar-DJhT5vOR.js";
import { B as Badge } from "./badge-D9Vgs7xh.js";
import { D as Dialog, a as Dialog_content, b as Dialog_header, c as Dialog_title, d as Dialog_description } from "./dialog-description-D12L0QA9.js";
import { D as Dialog_footer } from "./dialog-footer-kNnwKT2h.js";
import { T as Table, a as Table_header, b as Table_row, c as Table_head, d as Table_body, e as Table_cell } from "./table-row-_ffWKrps.js";
import { f as formatDate, a as formatTimeRange } from "./date-utils-Bc5VPPy9.js";
import { I as Icon } from "./Icon-Dal7PqKK.js";
import { C as Clock } from "./clock-B4fXoAi9.js";
import { U as User_plus } from "./user-plus-CCVf9WDs.js";
import { Q as Qr_code } from "./qr-code-C5X8qcH6.js";
import { P as Play } from "./play-DnSz4pBE.js";
import { C as Circle_x } from "./circle-x-BQrhluGu.js";
import { L as List } from "./list-DlzkoO4B.js";
import { G as Grid_3x3 } from "./grid-3x3-D2MHQ08_.js";
import { C as Circle_check } from "./circle-check-C59ZS7Zv.js";
import { U as User_x } from "./user-x-D3BlNLVK.js";
import { C as Circle } from "./circle-DadbbVvn.js";
import { U as User_check } from "./user-check-Dl-wciTR.js";
import { C as Camera } from "./camera-C09Eyogi.js";
import { T as Triangle_alert } from "./triangle-alert-CUvuB-HL.js";
import { A as Arrow_left } from "./arrow-left-DbWnQR3t.js";
import { B as Book_open } from "./book-open-B6eMguqY.js";
import { router } from "@inertiajs/core";
import "axios";
import "clsx";
import "laravel-precognition";
import "tailwind-merge";
import "tailwind-variants";
import "./ToastManager-C7oO_4Jn.js";
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
import "./settings-C5xOKpV3.js";
import "./arrow-right-CzynHiIo.js";
import "./Link-B7ks7rBK.js";
import "./chevron-right-BHu-PGOg.js";
import "./chevron-down-CkQQZIhu.js";
import "./printer-Bx0z1CRb.js";
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
function Wifi_off($$renderer, $$props) {
  const $$sanitized_props = sanitize_props($$props);
  const iconNode = [
    ["path", { "d": "M12 20h.01" }],
    ["path", { "d": "M8.5 16.429a5 5 0 0 1 7 0" }],
    ["path", { "d": "M5 12.859a10 10 0 0 1 5.17-2.69" }],
    ["path", { "d": "M19 12.859a10 10 0 0 0-2.007-1.523" }],
    ["path", { "d": "M2 8.82a15 15 0 0 1 4.177-2.643" }],
    ["path", { "d": "M22 8.82a15 15 0 0 0-11.288-3.764" }],
    ["path", { "d": "m2 2 20 20" }]
  ];
  Icon($$renderer, spread_props([
    { name: "wifi-off" },
    $$sanitized_props,
    {
      /**
       * @component @name WifiOff
       * @description Lucide SVG icon component, renders SVG Element with children.
       *
       * @preview ![img](data:image/svg+xml;base64,PHN2ZyAgeG1sbnM9Imh0dHA6Ly93d3cudzMub3JnLzIwMDAvc3ZnIgogIHdpZHRoPSIyNCIKICBoZWlnaHQ9IjI0IgogIHZpZXdCb3g9IjAgMCAyNCAyNCIKICBmaWxsPSJub25lIgogIHN0cm9rZT0iIzAwMCIgc3R5bGU9ImJhY2tncm91bmQtY29sb3I6ICNmZmY7IGJvcmRlci1yYWRpdXM6IDJweCIKICBzdHJva2Utd2lkdGg9IjIiCiAgc3Ryb2tlLWxpbmVjYXA9InJvdW5kIgogIHN0cm9rZS1saW5lam9pbj0icm91bmQiCj4KICA8cGF0aCBkPSJNMTIgMjBoLjAxIiAvPgogIDxwYXRoIGQ9Ik04LjUgMTYuNDI5YTUgNSAwIDAgMSA3IDAiIC8+CiAgPHBhdGggZD0iTTUgMTIuODU5YTEwIDEwIDAgMCAxIDUuMTctMi42OSIgLz4KICA8cGF0aCBkPSJNMTkgMTIuODU5YTEwIDEwIDAgMCAwLTIuMDA3LTEuNTIzIiAvPgogIDxwYXRoIGQ9Ik0yIDguODJhMTUgMTUgMCAwIDEgNC4xNzctMi42NDMiIC8+CiAgPHBhdGggZD0iTTIyIDguODJhMTUgMTUgMCAwIDAtMTEuMjg4LTMuNzY0IiAvPgogIDxwYXRoIGQ9Im0yIDIgMjAgMjAiIC8+Cjwvc3ZnPgo=) - https://lucide.dev/icons/wifi-off
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
function Wifi($$renderer, $$props) {
  const $$sanitized_props = sanitize_props($$props);
  const iconNode = [
    ["path", { "d": "M12 20h.01" }],
    ["path", { "d": "M2 8.82a15 15 0 0 1 20 0" }],
    ["path", { "d": "M5 12.859a10 10 0 0 1 14 0" }],
    ["path", { "d": "M8.5 16.429a5 5 0 0 1 7 0" }]
  ];
  Icon($$renderer, spread_props([
    { name: "wifi" },
    $$sanitized_props,
    {
      /**
       * @component @name Wifi
       * @description Lucide SVG icon component, renders SVG Element with children.
       *
       * @preview ![img](data:image/svg+xml;base64,PHN2ZyAgeG1sbnM9Imh0dHA6Ly93d3cudzMub3JnLzIwMDAvc3ZnIgogIHdpZHRoPSIyNCIKICBoZWlnaHQ9IjI0IgogIHZpZXdCb3g9IjAgMCAyNCAyNCIKICBmaWxsPSJub25lIgogIHN0cm9rZT0iIzAwMCIgc3R5bGU9ImJhY2tncm91bmQtY29sb3I6ICNmZmY7IGJvcmRlci1yYWRpdXM6IDJweCIKICBzdHJva2Utd2lkdGg9IjIiCiAgc3Ryb2tlLWxpbmVjYXA9InJvdW5kIgogIHN0cm9rZS1saW5lam9pbj0icm91bmQiCj4KICA8cGF0aCBkPSJNMTIgMjBoLjAxIiAvPgogIDxwYXRoIGQ9Ik0yIDguODJhMTUgMTUgMCAwIDEgMjAgMCIgLz4KICA8cGF0aCBkPSJNNSAxMi44NTlhMTAgMTAgMCAwIDEgMTQgMCIgLz4KICA8cGF0aCBkPSJNOC41IDE2LjQyOWE1IDUgMCAwIDEgNyAwIiAvPgo8L3N2Zz4K) - https://lucide.dev/icons/wifi
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
function Monitoring($$renderer, $$props) {
  $$renderer.component(($$renderer2) => {
    let {
      session,
      roster = [],
      focus_assignment_id = null,
      desks = [],
      can_manage = false,
      can_record = false,
      is_physical_seating = false,
      has_multiple_sets = false,
      grandfathered_alternate_sets = false,
      page_state = "ready",
      return_to = null
    } = $$props;
    let busy = {};
    let showScanModal = false;
    let scanInputMode = "camera";
    let scanResult = null;
    let qrCodeInput = "";
    let scanBusy = false;
    let isOnline = typeof navigator !== "undefined" ? navigator.onLine : true;
    let liveNow = Date.now();
    let showCloseConfirm = false;
    let closeBusy = false;
    let closeError = "";
    let showAccommodateModal = false;
    let lookupCode = "";
    let lookupBusy = false;
    let lookupResult = null;
    let lookupError = "";
    let reasonCategory = "travel_distance";
    let reasonNotes = "";
    let accommodateBusy = false;
    let showDistribution = false;
    let distribution = null;
    let distributionBusy = false;
    let distributionError = "";
    let distributionRequest = null;
    const canViewDistribution = derived(() => page_state !== "denied" && (["published", "in_progress"].includes(session?.status) || session?.status === "completed" && can_manage) && (new Set(session?.material?.required_set_codes ?? []).size > 1 || has_multiple_sets));
    function clearDistribution() {
      distributionRequest?.abort();
      distributionRequest = null;
      distribution = null;
      distributionBusy = false;
      distributionError = "";
    }
    function closeDistribution() {
      showDistribution = false;
      clearDistribution();
    }
    async function openDistribution() {
      if (!canViewDistribution()) return;
      clearDistribution();
      showDistribution = true;
      distributionBusy = true;
      const request = new AbortController();
      distributionRequest = request;
      const revision = session.material?.revision;
      const query = revision == null ? "" : `?expected_revision=${encodeURIComponent(revision)}`;
      try {
        const response = await fetch(`/exams/sessions/${session.id}/monitoring/distribution${query}`, {
          headers: { Accept: "application/json" },
          cache: "no-store",
          signal: request.signal
        });
        const data = await response.json();
        if (!response.ok) throw new Error(data.message || "Unable to load the booklet distribution list. Close and reopen it to try again.");
        if (request.signal.aborted) return;
        if (revision != null && data.revision !== revision) {
          throw new Error("The exam material snapshot has changed. Refresh monitoring before reopening the list.");
        }
        distribution = data;
      } catch (error) {
        if (!request.signal.aborted) {
          distributionError = error instanceof Error ? error.message : "Unable to load the booklet distribution list.";
        }
      } finally {
        if (distributionRequest === request) {
          distributionBusy = false;
          distributionRequest = null;
        }
      }
    }
    const remainingMs = derived(() => {
      if (!session?.effective_end_at) return null;
      const end = new Date(session.effective_end_at).getTime();
      return Math.max(0, end - liveNow);
    });
    const isOvertime = derived(() => {
      if (!session?.effective_end_at) return false;
      return liveNow > new Date(session.effective_end_at).getTime();
    });
    const formattedRemaining = derived(() => {
      if (remainingMs() === null) return "—";
      const totalSec = Math.floor(remainingMs() / 1e3);
      const hours = Math.floor(totalSec / 3600);
      const mins = Math.floor(totalSec % 3600 / 60);
      const secs = totalSec % 60;
      return `${String(hours).padStart(2, "0")}:${String(mins).padStart(2, "0")}:${String(secs).padStart(2, "0")}`;
    });
    const unrecordedCount = derived(() => roster.filter((r) => !r.attendance).length);
    function confirmCloseRoom() {
      if (!session?.version) return;
      closeBusy = true;
      closeError = "";
      router.post(`/exams/sessions/${session.id}/close`, { expected_version: session.version }, {
        preserveScroll: true,
        onError: (errs) => {
          closeError = errs?.close || "Failed to close session.";
        },
        onSuccess: () => {
          showCloseConfirm = false;
        },
        onFinish: () => {
          closeBusy = false;
        }
      });
    }
    function markAllUnrecordedAbsent() {
      roster.filter((row) => !row.attendance).forEach((row) => {
        recordAttendance(row.id, "absent");
      });
    }
    if (typeof window !== "undefined") {
      window.addEventListener("online", () => isOnline = true);
      window.addEventListener("offline", () => isOnline = false);
    }
    function setBusy(assignmentId, key, on) {
      busy = { ...busy, [`${assignmentId}:${key}`]: on };
    }
    function isBusy(assignmentId, key) {
      return busy[`${assignmentId}:${key}`] === true;
    }
    function recordAttendance(assignmentId, outcome) {
      setBusy(assignmentId, "attendance", true);
      router.post(`/exams/sessions/${session.id}/monitoring/assignments/${assignmentId}/attendance`, { outcome, idempotency_key: newIdempotencyKey() }, {
        preserveScroll: true,
        onFinish: () => setBusy(assignmentId, "attendance", false)
      });
    }
    function startSession() {
      if (!session?.version) return;
      router.post(`/exams/sessions/${session.id}/start`, { expected_version: session.version }, { preserveScroll: true });
    }
    function extendSession() {
      if (!session?.version || !session?.effective_end_at) return;
      const newEnd = new Date(new Date(session.effective_end_at).getTime() + 15 * 60 * 1e3).toISOString();
      router.post(`/exams/sessions/${session.id}/extend`, { expected_version: session.version, effective_end_at: newEnd }, { preserveScroll: true });
    }
    function submitQrCheckin() {
      if (!qrCodeInput.trim()) return;
      scanBusy = true;
      scanResult = null;
      fetch(`/exams/sessions/${session.id}/monitoring/qr-checkin`, {
        method: "POST",
        headers: {
          "Content-Type": "application/json",
          "X-XSRF-TOKEN": decodeURIComponent(document.cookie.match(/XSRF-TOKEN=([^;]+)/)?.[1] ?? ""),
          "Accept": "application/json"
        },
        body: JSON.stringify({
          qr_code: qrCodeInput.trim(),
          idempotency_key: newIdempotencyKey()
        })
      }).then((r) => r.json()).then((data) => {
        scanResult = data;
        if (data.status === "ok") {
          qrCodeInput = "";
          router.reload({ only: ["roster", "desks"] });
        }
      }).catch(() => {
        scanResult = { status: "error", message: "Network error" };
      }).finally(() => {
        scanBusy = false;
      });
    }
    async function lookupApplicant() {
      if (!lookupCode.trim()) return;
      lookupBusy = true;
      lookupError = "";
      lookupResult = null;
      try {
        const response = await fetch(`/exams/sessions/${session.id}/monitoring/lookup-applicant?reference_code=${encodeURIComponent(lookupCode.trim())}`, { headers: { Accept: "application/json" } });
        const data = await response.json();
        if (!response.ok) {
          throw new Error(data.message || "Unable to find an applicant with that reference code.");
        }
        lookupResult = data;
      } catch (error) {
        lookupError = error instanceof Error ? error.message : "Network error. Please try again.";
      } finally {
        lookupBusy = false;
      }
    }
    async function accommodateApplicant() {
      if (!lookupResult || lookupResult.has_prior_submission || reasonNotes.trim().length < 3) return;
      accommodateBusy = true;
      lookupError = "";
      try {
        const response = await fetch(`/exams/sessions/${session.id}/monitoring/accommodate`, {
          method: "POST",
          headers: {
            "Content-Type": "application/json",
            "X-XSRF-TOKEN": decodeURIComponent(document.cookie.match(/XSRF-TOKEN=([^;]+)/)?.[1] ?? ""),
            Accept: "application/json"
          },
          body: JSON.stringify({
            reference_code: lookupResult.reference_code,
            reason_category: reasonCategory,
            reason_notes: reasonNotes.trim()
          })
        });
        const data = await response.json();
        if (!response.ok) {
          throw new Error(data.message || Object.values(data.errors ?? {}).flat().join(" ") || "Unable to accommodate this applicant.");
        }
        showAccommodateModal = false;
        lookupCode = "";
        lookupResult = null;
        reasonNotes = "";
        router.reload({ only: ["roster", "desks"] });
      } catch (error) {
        lookupError = error instanceof Error ? error.message : "Network error. Please try again.";
      } finally {
        accommodateBusy = false;
      }
    }
    function resetAccommodation() {
      lookupCode = "";
      lookupResult = null;
      lookupError = "";
      reasonCategory = "travel_distance";
      reasonNotes = "";
    }
    function attendanceBadge(attendance) {
      if (attendance === "present") {
        return { label: "Present", variant: "default" };
      }
      if (attendance === "absent") {
        return { label: "Absent", variant: "secondary" };
      }
      if (attendance === "void") {
        return { label: "Void / Walkout", variant: "destructive" };
      }
      return { label: "Not recorded", variant: "outline" };
    }
    function statusVariant(status) {
      if (status === "published") return "default";
      if (status === "in_progress") return "outline";
      return "outline";
    }
    function statusLabel(status) {
      if (status === "published") return "Published";
      if (status === "in_progress") return "In progress";
      return status;
    }
    const presentCount = derived(() => roster.filter((r) => r.attendance === "present").length);
    const absentCount = derived(() => roster.filter((r) => r.attendance === "absent").length);
    const voidCount = derived(() => roster.filter((r) => r.attendance === "void").length);
    const submittedCount = derived(() => roster.filter((r) => r.submitted).length);
    const pendingCount = derived(() => roster.filter((r) => r.attendance === "present" && !r.submitted).length);
    const breadcrumbs = [
      { label: "Exam Monitoring", href: "/exams/monitoring" },
      { label: session?.code ?? "Session" }
    ];
    let $$settled = true;
    let $$inner_renderer;
    function $$render_inner($$renderer3) {
      head("1jpxl65", $$renderer3, ($$renderer4) => {
        $$renderer4.title(($$renderer5) => {
          $$renderer5.push(`<title>${escape_html(session?.code ?? "Session")} · Monitoring</title>`);
        });
      });
      StaffLayout($$renderer3, {
        breadcrumbs,
        children: ($$renderer4) => {
          $$renderer4.push(`<div class="min-w-0 space-y-6 p-4 sm:p-6">`);
          {
            let actions = function($$renderer5) {
              $$renderer5.push(`<div class="flex flex-wrap items-center gap-2">`);
              if (return_to) {
                $$renderer5.push("<!--[0-->");
                Button($$renderer5, {
                  variant: "outline",
                  onclick: () => router.visit(return_to),
                  children: ($$renderer6) => {
                    Arrow_left($$renderer6, { class: "size-4" });
                    $$renderer6.push(`<!----> Return to OMR scan`);
                  },
                  $$slots: { default: true }
                });
              } else {
                $$renderer5.push("<!--[-1-->");
              }
              $$renderer5.push(`<!--]--> `);
              if (canViewDistribution()) {
                $$renderer5.push("<!--[0-->");
                Button($$renderer5, {
                  variant: "outline",
                  onclick: openDistribution,
                  disabled: !isOnline,
                  class: "gap-1.5",
                  children: ($$renderer6) => {
                    Book_open($$renderer6, { class: "size-4", "aria-hidden": "true" });
                    $$renderer6.push(`<!----> Booklet distribution list`);
                  },
                  $$slots: { default: true }
                });
              } else {
                $$renderer5.push("<!--[-1-->");
              }
              $$renderer5.push(`<!--]--> `);
              if (session.status === "in_progress" && session.effective_end_at) {
                $$renderer5.push("<!--[0-->");
                $$renderer5.push(`<div class="flex items-center gap-2 font-mono text-xs px-3 py-1.5 rounded-xl bg-muted/60 border border-border"><span class="text-[10px] text-muted-foreground uppercase font-bold">Time Left:</span> <span${attr_class(`font-bold ${isOvertime() ? "text-rose-500 animate-pulse" : "text-foreground"}`)}>${escape_html(isOvertime() ? "OVERTIME" : formattedRemaining())}</span></div>`);
              } else {
                $$renderer5.push("<!--[-1-->");
              }
              $$renderer5.push(`<!--]--> `);
              Badge($$renderer5, {
                variant: statusVariant(session.status),
                children: ($$renderer6) => {
                  $$renderer6.push(`<!---->${escape_html(statusLabel(session.status))}`);
                },
                $$slots: { default: true }
              });
              $$renderer5.push(`<!----></div>`);
            };
            PageHeaderBar($$renderer4, {
              title: `${session.code} · Monitoring`,
              description: "Record attendance for each assigned applicant. Room closure records submissions for present examinees.",
              actions
            });
          }
          $$renderer4.push(`<!----> <div${attr_class(`flex items-center justify-between rounded-xl border px-4 py-2.5 font-mono text-xs ${isOnline ? "border-emerald-500/30 bg-emerald-500/5" : "border-amber-500/30 bg-amber-500/5"}`)}><div class="flex items-center gap-2">`);
          if (isOnline) {
            $$renderer4.push("<!--[0-->");
            Wifi($$renderer4, { class: "size-4 text-emerald-600 dark:text-emerald-400" });
            $$renderer4.push(`<!----> <span class="font-bold text-emerald-700 dark:text-emerald-300 uppercase tracking-wider">Online Conduct Station</span>`);
          } else {
            $$renderer4.push("<!--[-1-->");
            Wifi_off($$renderer4, { class: "size-4 text-amber-600 dark:text-amber-400" });
            $$renderer4.push(`<!----> <span class="font-bold text-amber-700 dark:text-amber-300 uppercase tracking-wider">Offline PWA Mode</span> `);
            Badge($$renderer4, {
              variant: "secondary",
              class: "ml-2 text-[10px]",
              children: ($$renderer5) => {
                $$renderer5.push(`<!---->Outbox: 0`);
              },
              $$slots: { default: true }
            });
            $$renderer4.push(`<!---->`);
          }
          $$renderer4.push(`<!--]--></div> <span class="text-muted-foreground">As of ${escape_html((/* @__PURE__ */ new Date()).toLocaleTimeString())}</span></div> <div class="rounded-xl border border-border bg-card p-6"><dl class="grid gap-3 sm:grid-cols-2"><div><dt class="text-xs text-muted-foreground font-medium">Date &amp; Time</dt> <dd class="mt-0.5 space-y-0.5 font-sans"><div class="flex items-center gap-1.5 font-medium text-foreground text-sm">`);
          Calendar($$renderer4, { class: "size-4 text-primary shrink-0" });
          $$renderer4.push(`<!----> <span>${escape_html(formatDate(session.date, "long"))}</span></div> `);
          if (session.start_time) {
            $$renderer4.push("<!--[0-->");
            $$renderer4.push(`<div class="flex items-center gap-1.5 text-xs text-muted-foreground font-mono">`);
            Clock($$renderer4, { class: "size-3.5 text-muted-foreground shrink-0" });
            $$renderer4.push(`<!----> <span>${escape_html(formatTimeRange(session.start_time, session.end_time))}</span></div>`);
          } else {
            $$renderer4.push("<!--[-1-->");
          }
          $$renderer4.push(`<!--]--></dd></div> <div><dt class="text-sm text-muted-foreground">Room</dt> <dd class="font-medium">${escape_html(session.room?.name ?? session.room?.code ?? "—")} (cap. ${escape_html(session.room?.capacity ?? "—")})</dd></div> <div><dt class="text-sm text-muted-foreground">Academic year</dt> <dd class="font-medium">${escape_html(session.academic_cycle?.label ?? "—")}</dd></div> <div><dt class="text-sm text-muted-foreground">Assigned applicants</dt> <dd class="font-medium">${escape_html(roster.length)}</dd></div></dl></div> `);
          if (roster.length > 0) {
            $$renderer4.push("<!--[0-->");
            $$renderer4.push(`<div class="grid grid-cols-2 sm:grid-cols-5 gap-3 text-xs font-mono"><div class="p-3 rounded-xl border border-border bg-card text-center"><div class="text-xl font-extrabold text-emerald-600 dark:text-emerald-400">${escape_html(presentCount())}</div> <div class="text-[10px] text-muted-foreground uppercase tracking-wider">Present</div></div> <div class="p-3 rounded-xl border border-border bg-card text-center"><div class="text-xl font-extrabold text-rose-500">${escape_html(absentCount())}</div> <div class="text-[10px] text-muted-foreground uppercase tracking-wider">Absent</div></div> <div class="p-3 rounded-xl border border-border bg-card text-center"><div class="text-xl font-extrabold text-amber-700 dark:text-amber-300">${escape_html(voidCount())}</div> <div class="text-[10px] text-muted-foreground uppercase tracking-wider">Void / Incomplete</div></div> <div class="p-3 rounded-xl border border-border bg-card text-center"><div class="text-xl font-extrabold text-blue-600 dark:text-blue-400">${escape_html(submittedCount())}</div> <div class="text-[10px] text-muted-foreground uppercase tracking-wider">Submitted</div></div> <div class="p-3 rounded-xl border border-border bg-card text-center"><div class="text-xl font-extrabold text-amber-600 dark:text-amber-400">${escape_html(pendingCount())}</div> <div class="text-[10px] text-muted-foreground uppercase tracking-wider">Will submit on close</div></div></div>`);
          } else {
            $$renderer4.push("<!--[-1-->");
          }
          $$renderer4.push(`<!--]--> `);
          if (can_record || can_manage) {
            $$renderer4.push("<!--[0-->");
            $$renderer4.push(`<div class="flex flex-wrap items-center gap-2">`);
            if (can_manage) {
              $$renderer4.push("<!--[0-->");
              Button($$renderer4, {
                variant: "outline",
                class: "gap-1.5",
                onclick: () => showAccommodateModal = true,
                children: ($$renderer5) => {
                  User_plus($$renderer5, { class: "size-4" });
                  $$renderer5.push(`<!----> Accommodate Walk-in / Transfer`);
                },
                $$slots: { default: true }
              });
            } else {
              $$renderer4.push("<!--[-1-->");
            }
            $$renderer4.push(`<!--]--> `);
            if (can_record) {
              $$renderer4.push("<!--[0-->");
              Button($$renderer4, {
                variant: "outline",
                class: "gap-1.5",
                onclick: () => showScanModal = true,
                children: ($$renderer5) => {
                  Qr_code($$renderer5, { class: "size-4" });
                  $$renderer5.push(`<!----> Scan Examinee QR (Door)`);
                },
                $$slots: { default: true }
              });
              $$renderer4.push(`<!----> `);
              if (session.status === "published") {
                $$renderer4.push("<!--[0-->");
                Button($$renderer4, {
                  class: "gap-1.5",
                  disabled: !isOnline,
                  onclick: startSession,
                  children: ($$renderer5) => {
                    Play($$renderer5, { class: "size-4" });
                    $$renderer5.push(`<!----> Start Session`);
                  },
                  $$slots: { default: true }
                });
              } else {
                $$renderer4.push("<!--[-1-->");
              }
              $$renderer4.push(`<!--]--> `);
              if (session.status === "in_progress") {
                $$renderer4.push("<!--[0-->");
                Button($$renderer4, {
                  variant: "outline",
                  class: "gap-1.5",
                  disabled: !isOnline,
                  onclick: extendSession,
                  children: ($$renderer5) => {
                    Clock($$renderer5, { class: "size-4" });
                    $$renderer5.push(`<!----> Extend +15m`);
                  },
                  $$slots: { default: true }
                });
                $$renderer4.push(`<!----> `);
                Button($$renderer4, {
                  variant: "destructive",
                  class: "gap-1.5",
                  disabled: !isOnline,
                  onclick: () => showCloseConfirm = true,
                  children: ($$renderer5) => {
                    Circle_x($$renderer5, { class: "size-4" });
                    $$renderer5.push(`<!----> Close Room`);
                  },
                  $$slots: { default: true }
                });
                $$renderer4.push(`<!---->`);
              } else {
                $$renderer4.push("<!--[-1-->");
              }
              $$renderer4.push(`<!--]-->`);
            } else {
              $$renderer4.push("<!--[-1-->");
            }
            $$renderer4.push(`<!--]--></div>`);
          } else {
            $$renderer4.push("<!--[-1-->");
          }
          $$renderer4.push(`<!--]--> `);
          if (page_state === "denied") {
            $$renderer4.push("<!--[0-->");
            $$renderer4.push(`<p class="text-sm text-muted-foreground">You do not have permission to monitor this session.</p>`);
          } else {
            $$renderer4.push("<!--[-1-->");
            $$renderer4.push(`<div class="rounded-xl border border-border bg-card p-6"><div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between"><div><h2 class="text-lg font-semibold tracking-tight">Conduct roster</h2> <p class="mt-1 text-sm text-muted-foreground">`);
            if (can_manage) {
              $$renderer4.push("<!--[0-->");
              $$renderer4.push(`Mark attendance for each applicant. Closing the room records submissions for present examinees and opens grading.`);
            } else {
              $$renderer4.push("<!--[-1-->");
              $$renderer4.push(`You can record attendance for this assigned session only.`);
            }
            $$renderer4.push(`<!--]--></p></div> `);
            if (desks.length > 0) {
              $$renderer4.push("<!--[0-->");
              $$renderer4.push(`<div class="flex rounded-lg border border-border overflow-hidden"><button${attr_class(`px-3 py-1.5 text-xs font-medium flex items-center gap-1.5 ${"bg-primary text-primary-foreground"}`)}>`);
              List($$renderer4, { class: "size-3.5" });
              $$renderer4.push(`<!----> Roster List</button> <button${attr_class(`px-3 py-1.5 text-xs font-medium flex items-center gap-1.5 ${"bg-card text-muted-foreground hover:bg-muted"}`)}>`);
              Grid_3x3($$renderer4, { class: "size-3.5" });
              $$renderer4.push(`<!----> Spatial Grid</button></div>`);
            } else {
              $$renderer4.push("<!--[-1-->");
            }
            $$renderer4.push(`<!--]--></div> `);
            if (roster.length > 0) {
              $$renderer4.push("<!--[1-->");
              $$renderer4.push(`<div class="mt-4 overflow-x-auto">`);
              if (Table) {
                $$renderer4.push("<!--[-->");
                Table($$renderer4, {
                  children: ($$renderer5) => {
                    if (Table_header) {
                      $$renderer5.push("<!--[-->");
                      Table_header($$renderer5, {
                        class: "bg-muted/50",
                        children: ($$renderer6) => {
                          if (Table_row) {
                            $$renderer6.push("<!--[-->");
                            Table_row($$renderer6, {
                              children: ($$renderer7) => {
                                if (Table_head) {
                                  $$renderer7.push("<!--[-->");
                                  Table_head($$renderer7, {
                                    children: ($$renderer8) => {
                                      $$renderer8.push(`<!---->Reference`);
                                    },
                                    $$slots: { default: true }
                                  });
                                  $$renderer7.push("<!--]-->");
                                } else {
                                  $$renderer7.push("<!--[!-->");
                                  $$renderer7.push("<!--]-->");
                                }
                                $$renderer7.push(` `);
                                if (Table_head) {
                                  $$renderer7.push("<!--[-->");
                                  Table_head($$renderer7, {
                                    children: ($$renderer8) => {
                                      $$renderer8.push(`<!---->Name`);
                                    },
                                    $$slots: { default: true }
                                  });
                                  $$renderer7.push("<!--]-->");
                                } else {
                                  $$renderer7.push("<!--[!-->");
                                  $$renderer7.push("<!--]-->");
                                }
                                $$renderer7.push(` `);
                                if (is_physical_seating) {
                                  $$renderer7.push("<!--[0-->");
                                  if (Table_head) {
                                    $$renderer7.push("<!--[-->");
                                    Table_head($$renderer7, {
                                      children: ($$renderer8) => {
                                        $$renderer8.push(`<!---->Seat`);
                                      },
                                      $$slots: { default: true }
                                    });
                                    $$renderer7.push("<!--]-->");
                                  } else {
                                    $$renderer7.push("<!--[!-->");
                                    $$renderer7.push("<!--]-->");
                                  }
                                } else {
                                  $$renderer7.push("<!--[-1-->");
                                }
                                $$renderer7.push(`<!--]--> `);
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
                                $$renderer7.push(` `);
                                if (can_record) {
                                  $$renderer7.push("<!--[0-->");
                                  if (Table_head) {
                                    $$renderer7.push("<!--[-->");
                                    Table_head($$renderer7, {
                                      class: "text-center",
                                      children: ($$renderer8) => {
                                        $$renderer8.push(`<!---->Actions`);
                                      },
                                      $$slots: { default: true }
                                    });
                                    $$renderer7.push("<!--]-->");
                                  } else {
                                    $$renderer7.push("<!--[!-->");
                                    $$renderer7.push("<!--]-->");
                                  }
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
                    $$renderer5.push(` `);
                    if (Table_body) {
                      $$renderer5.push("<!--[-->");
                      Table_body($$renderer5, {
                        children: ($$renderer6) => {
                          $$renderer6.push(`<!--[-->`);
                          const each_array_1 = ensure_array_like(roster);
                          for (let $$index_1 = 0, $$length = each_array_1.length; $$index_1 < $$length; $$index_1++) {
                            let row = each_array_1[$$index_1];
                            const att = attendanceBadge(row.attendance);
                            if (Table_row) {
                              $$renderer6.push("<!--[-->");
                              Table_row($$renderer6, {
                                id: "monitoring-assignment-" + row.id,
                                class: String(row.id) === String(focus_assignment_id) ? "ring-2 ring-primary ring-offset-2 bg-primary/5" : "",
                                children: ($$renderer7) => {
                                  if (Table_cell) {
                                    $$renderer7.push("<!--[-->");
                                    Table_cell($$renderer7, {
                                      children: ($$renderer8) => {
                                        $$renderer8.push(`<!---->${escape_html(row.reference_number)}`);
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
                                      class: "font-medium",
                                      children: ($$renderer8) => {
                                        $$renderer8.push(`<!---->${escape_html(row.name)}`);
                                      },
                                      $$slots: { default: true }
                                    });
                                    $$renderer7.push("<!--]-->");
                                  } else {
                                    $$renderer7.push("<!--[!-->");
                                    $$renderer7.push("<!--]-->");
                                  }
                                  $$renderer7.push(` `);
                                  if (is_physical_seating) {
                                    $$renderer7.push("<!--[0-->");
                                    if (Table_cell) {
                                      $$renderer7.push("<!--[-->");
                                      Table_cell($$renderer7, {
                                        class: "font-mono text-xs",
                                        children: ($$renderer8) => {
                                          $$renderer8.push(`<!---->${escape_html(row.seat_label ?? "—")}`);
                                        },
                                        $$slots: { default: true }
                                      });
                                      $$renderer7.push("<!--]-->");
                                    } else {
                                      $$renderer7.push("<!--[!-->");
                                      $$renderer7.push("<!--]-->");
                                    }
                                  } else {
                                    $$renderer7.push("<!--[-1-->");
                                  }
                                  $$renderer7.push(`<!--]--> `);
                                  if (Table_cell) {
                                    $$renderer7.push("<!--[-->");
                                    Table_cell($$renderer7, {
                                      children: ($$renderer8) => {
                                        if (row.attendance === "void") {
                                          $$renderer8.push("<!--[0-->");
                                          Badge($$renderer8, {
                                            variant: "destructive",
                                            children: ($$renderer9) => {
                                              $$renderer9.push(`<!---->Void / Incomplete`);
                                            },
                                            $$slots: { default: true }
                                          });
                                        } else if (row.submitted) {
                                          $$renderer8.push("<!--[1-->");
                                          $$renderer8.push(`<span class="inline-flex items-center gap-1.5 px-2.5 py-0.5 rounded-full bg-emerald-500/10 text-emerald-600 dark:text-emerald-400 font-bold text-xs border border-emerald-500/20 font-mono">`);
                                          Circle_check($$renderer8, { class: "size-3.5" });
                                          $$renderer8.push(`<!----> Submitted</span>`);
                                        } else if (row.attendance === "present") {
                                          $$renderer8.push("<!--[2-->");
                                          $$renderer8.push(`<span class="inline-flex items-center gap-1.5 px-2.5 py-0.5 rounded-full bg-blue-500/10 text-blue-600 dark:text-blue-400 font-bold text-xs border border-blue-500/20 font-mono"><span class="size-1.5 rounded-full bg-blue-500 animate-pulse"></span> Present (Writing)</span>`);
                                        } else if (row.attendance === "absent") {
                                          $$renderer8.push("<!--[3-->");
                                          $$renderer8.push(`<span class="inline-flex items-center gap-1.5 px-2.5 py-0.5 rounded-full bg-rose-500/10 text-rose-500 font-bold text-xs border border-rose-500/20 font-mono">`);
                                          User_x($$renderer8, { class: "size-3.5" });
                                          $$renderer8.push(`<!----> Absent</span>`);
                                        } else {
                                          $$renderer8.push("<!--[-1-->");
                                          Badge($$renderer8, {
                                            variant: att.variant,
                                            children: ($$renderer9) => {
                                              Circle($$renderer9, { class: "size-3" });
                                              $$renderer9.push(`<!----> ${escape_html(att.label)}`);
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
                                  if (can_record) {
                                    $$renderer7.push("<!--[0-->");
                                    if (Table_cell) {
                                      $$renderer7.push("<!--[-->");
                                      Table_cell($$renderer7, {
                                        class: "text-center",
                                        children: ($$renderer8) => {
                                          $$renderer8.push(`<div class="inline-flex flex-wrap justify-center gap-1">`);
                                          Button($$renderer8, {
                                            variant: "ghost",
                                            size: "sm",
                                            class: "h-8 px-2 text-xs",
                                            disabled: row.attendance === "present" || isBusy(row.id, "attendance"),
                                            onclick: () => recordAttendance(row.id, "present"),
                                            children: ($$renderer9) => {
                                              User_check($$renderer9, { class: "mr-1.5 size-3.5" });
                                              $$renderer9.push(`<!----> Present`);
                                            },
                                            $$slots: { default: true }
                                          });
                                          $$renderer8.push(`<!----> `);
                                          Button($$renderer8, {
                                            variant: "ghost",
                                            size: "sm",
                                            class: "h-8 px-2 text-xs",
                                            disabled: row.attendance === "absent" || isBusy(row.id, "attendance"),
                                            onclick: () => recordAttendance(row.id, "absent"),
                                            children: ($$renderer9) => {
                                              User_x($$renderer9, { class: "mr-1.5 size-3.5" });
                                              $$renderer9.push(`<!----> Absent`);
                                            },
                                            $$slots: { default: true }
                                          });
                                          $$renderer8.push(`<!----> `);
                                          Button($$renderer8, {
                                            variant: "destructive",
                                            size: "sm",
                                            class: "h-8 px-2 text-xs",
                                            disabled: row.attendance === "void" || isBusy(row.id, "attendance"),
                                            onclick: () => recordAttendance(row.id, "void"),
                                            children: ($$renderer9) => {
                                              $$renderer9.push(`<!---->Void`);
                                            },
                                            $$slots: { default: true }
                                          });
                                          $$renderer8.push(`<!----></div>`);
                                        },
                                        $$slots: { default: true }
                                      });
                                      $$renderer7.push("<!--]-->");
                                    } else {
                                      $$renderer7.push("<!--[!-->");
                                      $$renderer7.push("<!--]-->");
                                    }
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
              $$renderer4.push(`</div>`);
            } else {
              $$renderer4.push("<!--[-1-->");
              $$renderer4.push(`<p class="mt-4 text-sm text-muted-foreground">No assigned applicants to monitor.</p>`);
            }
            $$renderer4.push(`<!--]--></div>`);
          }
          $$renderer4.push(`<!--]--> `);
          if (Dialog) {
            $$renderer4.push("<!--[-->");
            Dialog($$renderer4, {
              open: showScanModal,
              onOpenChange: (open) => {
                if (!open) {
                  showScanModal = false;
                  scanResult = null;
                  qrCodeInput = "";
                }
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
                          class: "pr-8",
                          children: ($$renderer7) => {
                            if (Dialog_title) {
                              $$renderer7.push("<!--[-->");
                              Dialog_title($$renderer7, {
                                children: ($$renderer8) => {
                                  $$renderer8.push(`<!---->Scan Examinee QR (Door Check-in)`);
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
                                  $$renderer8.push(`<!---->Scan or enter applicant's QR code to verify and check them into this session.`);
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
                      $$renderer6.push(` <div class="space-y-3"><div class="flex items-center justify-between border-b border-border/40 pb-2"><span class="text-xs text-muted-foreground font-medium">Capture method:</span> <div class="flex items-center gap-1">`);
                      Button($$renderer6, {
                        type: "button",
                        size: "sm",
                        variant: scanInputMode === "camera" ? "default" : "outline",
                        class: "h-6 px-2 text-xs font-semibold gap-1",
                        onclick: () => scanInputMode = "camera",
                        children: ($$renderer7) => {
                          Camera($$renderer7, { class: "size-3" });
                          $$renderer7.push(`<!----> Camera`);
                        },
                        $$slots: { default: true }
                      });
                      $$renderer6.push(`<!----> `);
                      Button($$renderer6, {
                        type: "button",
                        size: "sm",
                        variant: scanInputMode === "manual" ? "default" : "outline",
                        class: "h-6 px-2 text-xs font-semibold gap-1",
                        onclick: () => scanInputMode = "manual",
                        children: ($$renderer7) => {
                          Qr_code($$renderer7, { class: "size-3" });
                          $$renderer7.push(`<!----> Manual`);
                        },
                        $$slots: { default: true }
                      });
                      $$renderer6.push(`<!----></div></div> `);
                      if (scanInputMode === "camera") {
                        $$renderer6.push("<!--[0-->");
                        QrScanner($$renderer6, {
                          active: showScanModal && scanInputMode === "camera",
                          paused: scanBusy,
                          onScan: (code) => {
                            qrCodeInput = code;
                            submitQrCheckin();
                          }
                        });
                      } else {
                        $$renderer6.push("<!--[-1-->");
                        $$renderer6.push(`<input type="text" class="w-full rounded-md border border-input bg-background px-3 py-2 text-sm font-mono"${attr("value", qrCodeInput)} placeholder="Paste or scan QR code..."/>`);
                      }
                      $$renderer6.push(`<!--]--> `);
                      if (scanResult) {
                        $$renderer6.push("<!--[0-->");
                        if (scanResult.status === "ok") {
                          $$renderer6.push("<!--[0-->");
                          $$renderer6.push(`<div class="rounded-lg border border-emerald-500/30 bg-emerald-500/5 p-3 text-sm"><div class="font-bold text-emerald-700 dark:text-emerald-300">Check-in confirmed</div> <div class="mt-1 font-mono text-xs space-y-0.5"><div>${escape_html(scanResult.candidate?.name)} (${escape_html(scanResult.candidate?.reference_number)})</div> `);
                          if (scanResult.candidate?.desk_label) {
                            $$renderer6.push("<!--[0-->");
                            $$renderer6.push(`<div>Desk: ${escape_html(scanResult.candidate.desk_label)}</div>`);
                          } else {
                            $$renderer6.push("<!--[-1-->");
                          }
                          $$renderer6.push(`<!--]--></div></div>`);
                        } else if (scanResult.status === "mismatch") {
                          $$renderer6.push("<!--[1-->");
                          $$renderer6.push(`<div class="rounded-lg border border-amber-500/30 bg-amber-500/5 p-3 text-sm"><div class="font-bold text-amber-700 dark:text-amber-300">Wrong room</div> <div class="mt-1 text-xs">Assigned to <strong>${escape_html(scanResult.room)}</strong> (Session: ${escape_html(scanResult.session_code)})</div></div>`);
                        } else {
                          $$renderer6.push("<!--[-1-->");
                          $$renderer6.push(`<div class="rounded-lg border border-rose-500/30 bg-rose-500/5 p-3 text-sm"><div class="font-bold text-rose-700 dark:text-rose-300">Error</div> <div class="mt-1 text-xs">${escape_html(scanResult.message)}</div></div>`);
                        }
                        $$renderer6.push(`<!--]-->`);
                      } else {
                        $$renderer6.push("<!--[-1-->");
                      }
                      $$renderer6.push(`<!--]--></div> `);
                      if (Dialog_footer) {
                        $$renderer6.push("<!--[-->");
                        Dialog_footer($$renderer6, {
                          children: ($$renderer7) => {
                            Button($$renderer7, {
                              variant: "outline",
                              onclick: () => showScanModal = false,
                              children: ($$renderer8) => {
                                $$renderer8.push(`<!---->Close`);
                              },
                              $$slots: { default: true }
                            });
                            $$renderer7.push(`<!----> `);
                            Button($$renderer7, {
                              onclick: submitQrCheckin,
                              disabled: !qrCodeInput.trim() || scanBusy,
                              children: ($$renderer8) => {
                                $$renderer8.push(`<!---->${escape_html(scanBusy ? "Verifying..." : "Check In")}`);
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
              open: showAccommodateModal,
              onOpenChange: (open) => {
                showAccommodateModal = open;
                if (!open) resetAccommodation();
              },
              children: ($$renderer5) => {
                if (Dialog_content) {
                  $$renderer5.push("<!--[-->");
                  Dialog_content($$renderer5, {
                    class: "sm:max-w-lg",
                    children: ($$renderer6) => {
                      if (Dialog_header) {
                        $$renderer6.push("<!--[-->");
                        Dialog_header($$renderer6, {
                          children: ($$renderer7) => {
                            if (Dialog_title) {
                              $$renderer7.push("<!--[-->");
                              Dialog_title($$renderer7, {
                                children: ($$renderer8) => {
                                  $$renderer8.push(`<!---->Accommodate Walk-in / Transfer`);
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
                                  $$renderer8.push(`<!---->Look up an applicant by reference code, review their current assignment, and record why they are being accommodated into this room.`);
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
                      $$renderer6.push(` <div class="space-y-4"><div class="flex gap-2"><input id="walkin-reference-code" type="text" class="min-w-0 flex-1 rounded-md border border-input bg-background px-3 py-2 text-sm font-mono"${attr("value", lookupCode)} placeholder="Enter Reference Code" aria-label="Applicant reference code"${attr("disabled", lookupBusy || accommodateBusy, true)}/> `);
                      Button($$renderer6, {
                        variant: "outline",
                        onclick: lookupApplicant,
                        disabled: !lookupCode.trim() || lookupBusy || accommodateBusy,
                        children: ($$renderer7) => {
                          $$renderer7.push(`<!---->${escape_html(lookupBusy ? "Searching..." : "Search")}`);
                        },
                        $$slots: { default: true }
                      });
                      $$renderer6.push(`<!----></div> `);
                      if (lookupResult) {
                        $$renderer6.push("<!--[0-->");
                        $$renderer6.push(`<div class="space-y-4 rounded-xl border border-border bg-muted/30 p-4"><div><div class="font-semibold">${escape_html(lookupResult.name)}</div> <div class="mt-0.5 font-mono text-xs text-muted-foreground">${escape_html(lookupResult.reference_code)}</div></div> `);
                        if (lookupResult.has_prior_submission) {
                          $$renderer6.push("<!--[0-->");
                          $$renderer6.push(`<div class="rounded-lg border border-destructive/40 bg-destructive/10 p-3 text-sm text-destructive" role="alert">Ineligible: Applicant has already submitted an exam paper in another session.</div>`);
                        } else {
                          $$renderer6.push("<!--[-1-->");
                          $$renderer6.push(`<div class="rounded-lg border border-amber-500/30 bg-amber-500/5 p-3 text-sm text-amber-800 dark:text-amber-200">`);
                          if (lookupResult.attendance_outcome === "absent") {
                            $$renderer6.push("<!--[0-->");
                            $$renderer6.push(`Marked Absent in previous session`);
                          } else if (lookupResult.current_session_code) {
                            $$renderer6.push("<!--[1-->");
                            $$renderer6.push(`Currently scheduled: ${escape_html(lookupResult.current_session_code)}`);
                          } else {
                            $$renderer6.push("<!--[-1-->");
                            $$renderer6.push(`No current assignment`);
                          }
                          $$renderer6.push(`<!--]--></div> <div class="space-y-2"><label for="accommodation-reason-category" class="text-sm font-medium">Reason category</label> `);
                          $$renderer6.select(
                            {
                              id: "accommodation-reason-category",
                              class: "w-full rounded-md border border-input bg-background px-3 py-2 text-sm",
                              value: reasonCategory,
                              disabled: accommodateBusy
                            },
                            ($$renderer7) => {
                              $$renderer7.option({ value: "travel_distance" }, ($$renderer8) => {
                                $$renderer8.push(`Travel Distance / Early Arrival`);
                              });
                              $$renderer7.option({ value: "tardiness_consideration" }, ($$renderer8) => {
                                $$renderer8.push(`Transport Delay / Tardy Consideration`);
                              });
                              $$renderer7.option({ value: "administrative_override" }, ($$renderer8) => {
                                $$renderer8.push(`Administrative Override`);
                              });
                            }
                          );
                          $$renderer6.push(`</div> <div class="space-y-2"><label for="accommodation-reason-notes" class="text-sm font-medium">Reason notes</label> <textarea id="accommodation-reason-notes" class="min-h-24 w-full rounded-md border border-input bg-background px-3 py-2 text-sm" minlength="3" placeholder="e.g. Arrived from remote municipality at 7 AM; authorized for morning sitting"${attr("disabled", accommodateBusy, true)}>`);
                          const $$body = escape_html(reasonNotes);
                          if ($$body) {
                            $$renderer6.push(`${$$body}`);
                          }
                          $$renderer6.push(`</textarea></div>`);
                        }
                        $$renderer6.push(`<!--]--></div>`);
                      } else {
                        $$renderer6.push("<!--[-1-->");
                      }
                      $$renderer6.push(`<!--]--> `);
                      if (lookupError) {
                        $$renderer6.push("<!--[0-->");
                        $$renderer6.push(`<div class="rounded-lg border border-destructive/40 bg-destructive/10 p-3 text-sm text-destructive" role="alert">${escape_html(lookupError)}</div>`);
                      } else {
                        $$renderer6.push("<!--[-1-->");
                      }
                      $$renderer6.push(`<!--]--></div> `);
                      if (Dialog_footer) {
                        $$renderer6.push("<!--[-->");
                        Dialog_footer($$renderer6, {
                          children: ($$renderer7) => {
                            Button($$renderer7, {
                              variant: "outline",
                              onclick: () => showAccommodateModal = false,
                              disabled: accommodateBusy,
                              children: ($$renderer8) => {
                                $$renderer8.push(`<!---->Cancel`);
                              },
                              $$slots: { default: true }
                            });
                            $$renderer7.push(`<!----> `);
                            if (lookupResult && !lookupResult.has_prior_submission) {
                              $$renderer7.push("<!--[0-->");
                              Button($$renderer7, {
                                onclick: accommodateApplicant,
                                disabled: reasonNotes.trim().length < 3 || accommodateBusy,
                                children: ($$renderer8) => {
                                  $$renderer8.push(`<!---->${escape_html(accommodateBusy ? "Accommodating..." : "Accommodate into this Room")}`);
                                },
                                $$slots: { default: true }
                              });
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
                return showCloseConfirm;
              },
              set open($$value) {
                showCloseConfirm = $$value;
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
                                  $$renderer8.push(`<!---->Close Room &amp; Open Scoring`);
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
                                  $$renderer8.push(`<!---->Seals this examination room's attendance roster and automatically records exam submissions for all present examinees, transitioning the session to the Scoring Hub.`);
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
                      $$renderer6.push(` <div class="space-y-4 py-2 text-xs font-mono"><div class="grid grid-cols-2 sm:grid-cols-5 gap-2 text-center p-3 rounded-xl bg-muted/40 border border-border"><div><div class="text-[10px] text-muted-foreground uppercase font-bold">Present</div><div class="text-base font-bold text-emerald-500">${escape_html(presentCount())}</div></div> <div><div class="text-[10px] text-muted-foreground uppercase font-bold">Void / Incomplete</div><div class="text-base font-bold text-amber-600 dark:text-amber-300">${escape_html(voidCount())}</div></div> <div><div class="text-[10px] text-muted-foreground uppercase font-bold">Absent</div><div class="text-base font-bold text-rose-500">${escape_html(absentCount())}</div></div> <div><div class="text-[10px] text-muted-foreground uppercase font-bold">Submitted</div><div class="text-base font-bold text-blue-500">${escape_html(submittedCount())}</div></div> <div><div class="text-[10px] text-muted-foreground uppercase font-bold">Unrecorded</div><div${attr_class(`text-base font-bold ${unrecordedCount() > 0 ? "text-amber-500 font-extrabold" : "text-foreground"}`)}>${escape_html(unrecordedCount())}</div></div></div> `);
                      if (unrecordedCount() > 0) {
                        $$renderer6.push("<!--[0-->");
                        $$renderer6.push(`<div class="p-3 rounded-xl border border-amber-500/40 bg-amber-500/10 text-amber-600 dark:text-amber-400 space-y-2"><div class="flex items-center gap-2 font-bold">`);
                        Triangle_alert($$renderer6, { class: "size-4 shrink-0" });
                        $$renderer6.push(`<!----> <span>Cannot close room yet (${escape_html(unrecordedCount())} unrecorded)</span></div> <p class="text-[11px] leading-relaxed">Every examinee must have attendance recorded before closing to derive the grading roster. You can mark all unrecorded examinees as absent (no-show) below:</p> `);
                        Button($$renderer6, {
                          variant: "outline",
                          size: "sm",
                          class: "w-full text-xs font-bold border-amber-500/40 hover:bg-amber-500/20 text-amber-600 dark:text-amber-400",
                          onclick: markAllUnrecordedAbsent,
                          children: ($$renderer7) => {
                            $$renderer7.push(`<!---->Mark ${escape_html(unrecordedCount())} Unrecorded as Absent (No-Show)`);
                          },
                          $$slots: { default: true }
                        });
                        $$renderer6.push(`<!----></div>`);
                      } else {
                        $$renderer6.push("<!--[-1-->");
                        $$renderer6.push(`<div class="p-3 rounded-xl border border-emerald-500/40 bg-emerald-500/10 text-emerald-600 dark:text-emerald-400 flex items-center gap-2 text-xs">`);
                        Circle_check($$renderer6, { class: "size-4 shrink-0" });
                        $$renderer6.push(`<!----> <span>All examinees accounted for. Ready to seal roster and open grading.</span></div>`);
                      }
                      $$renderer6.push(`<!--]--> `);
                      if (closeError) {
                        $$renderer6.push("<!--[0-->");
                        $$renderer6.push(`<div class="p-3 rounded-xl border border-destructive/40 bg-destructive/10 text-destructive text-xs">${escape_html(closeError)}</div>`);
                      } else {
                        $$renderer6.push("<!--[-1-->");
                      }
                      $$renderer6.push(`<!--]--></div> `);
                      if (Dialog_footer) {
                        $$renderer6.push("<!--[-->");
                        Dialog_footer($$renderer6, {
                          class: "flex gap-2",
                          children: ($$renderer7) => {
                            Button($$renderer7, {
                              variant: "outline",
                              onclick: () => showCloseConfirm = false,
                              children: ($$renderer8) => {
                                $$renderer8.push(`<!---->Cancel`);
                              },
                              $$slots: { default: true }
                            });
                            $$renderer7.push(`<!----> `);
                            Button($$renderer7, {
                              variant: "destructive",
                              disabled: unrecordedCount() > 0 || closeBusy,
                              onclick: confirmCloseRoom,
                              class: "gap-1.5 font-bold",
                              children: ($$renderer8) => {
                                if (closeBusy) {
                                  $$renderer8.push("<!--[0-->");
                                  $$renderer8.push(`Closing...`);
                                } else {
                                  $$renderer8.push("<!--[-1-->");
                                  $$renderer8.push(`Confirm &amp; Close Room`);
                                }
                                $$renderer8.push(`<!--]-->`);
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
                return showDistribution;
              },
              set open($$value) {
                showDistribution = $$value;
                $$settled = false;
              },
              children: ($$renderer5) => {
                if (Dialog_content) {
                  $$renderer5.push("<!--[-->");
                  Dialog_content($$renderer5, {
                    class: "max-h-[85dvh] overflow-y-auto sm:max-w-3xl",
                    children: ($$renderer6) => {
                      if (Dialog_header) {
                        $$renderer6.push("<!--[-->");
                        Dialog_header($$renderer6, {
                          children: ($$renderer7) => {
                            if (Dialog_title) {
                              $$renderer7.push("<!--[-->");
                              Dialog_title($$renderer7, {
                                children: ($$renderer8) => {
                                  $$renderer8.push(`<!---->Booklet Distribution List`);
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
                                  $$renderer8.push(`<!---->Match each applicant to the Booklet Set frozen for this exam. This protected list is loaded only when opened.`);
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
                      if (grandfathered_alternate_sets) {
                        $$renderer6.push("<!--[0-->");
                        $$renderer6.push(`<p class="text-sm text-muted-foreground">Grandfathered: This exam was published with alternate Booklet Sets.</p>`);
                      } else {
                        $$renderer6.push("<!--[-1-->");
                      }
                      $$renderer6.push(`<!--]--> `);
                      if (distributionBusy) {
                        $$renderer6.push("<!--[0-->");
                        $$renderer6.push(`<p role="status" class="py-6 text-sm text-muted-foreground">Loading booklet distribution list…</p>`);
                      } else if (distributionError) {
                        $$renderer6.push("<!--[1-->");
                        $$renderer6.push(`<p role="alert" class="text-sm text-destructive">${escape_html(distributionError)}</p>`);
                      } else if (distribution) {
                        $$renderer6.push("<!--[2-->");
                        $$renderer6.push(`<div class="space-y-4"><p class="text-sm text-muted-foreground">Snapshot revision ${escape_html(distribution.revision)} · Generated ${escape_html(new Date(distribution.generated_at).toLocaleString())}</p> <dl class="flex flex-wrap gap-x-6 gap-y-2 text-sm" aria-label="Booklet Set counts"><!--[-->`);
                        const each_array_2 = ensure_array_like(Object.entries(distribution.sets_summary ?? {}));
                        for (let $$index_2 = 0, $$length = each_array_2.length; $$index_2 < $$length; $$index_2++) {
                          let [code, count] = each_array_2[$$index_2];
                          $$renderer6.push(`<div class="flex gap-2"><dt class="font-medium">Set ${escape_html(code)}:</dt> <dd class="tabular-nums">${escape_html(count)}</dd></div>`);
                        }
                        $$renderer6.push(`<!--]--></dl> <div class="overflow-x-auto">`);
                        if (Table) {
                          $$renderer6.push("<!--[-->");
                          Table($$renderer6, {
                            children: ($$renderer7) => {
                              if (Table_header) {
                                $$renderer7.push("<!--[-->");
                                Table_header($$renderer7, {
                                  children: ($$renderer8) => {
                                    if (Table_row) {
                                      $$renderer8.push("<!--[-->");
                                      Table_row($$renderer8, {
                                        children: ($$renderer9) => {
                                          if (Table_head) {
                                            $$renderer9.push("<!--[-->");
                                            Table_head($$renderer9, {
                                              children: ($$renderer10) => {
                                                $$renderer10.push(`<!---->Applicant Reference`);
                                              },
                                              $$slots: { default: true }
                                            });
                                            $$renderer9.push("<!--]-->");
                                          } else {
                                            $$renderer9.push("<!--[!-->");
                                            $$renderer9.push("<!--]-->");
                                          }
                                          $$renderer9.push(` `);
                                          if (Table_head) {
                                            $$renderer9.push("<!--[-->");
                                            Table_head($$renderer9, {
                                              children: ($$renderer10) => {
                                                $$renderer10.push(`<!---->Name`);
                                              },
                                              $$slots: { default: true }
                                            });
                                            $$renderer9.push("<!--]-->");
                                          } else {
                                            $$renderer9.push("<!--[!-->");
                                            $$renderer9.push("<!--]-->");
                                          }
                                          $$renderer9.push(` `);
                                          if (Table_head) {
                                            $$renderer9.push("<!--[-->");
                                            Table_head($$renderer9, {
                                              children: ($$renderer10) => {
                                                $$renderer10.push(`<!---->Assigned Set Code`);
                                              },
                                              $$slots: { default: true }
                                            });
                                            $$renderer9.push("<!--]-->");
                                          } else {
                                            $$renderer9.push("<!--[!-->");
                                            $$renderer9.push("<!--]-->");
                                          }
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
                              $$renderer7.push(` `);
                              if (Table_body) {
                                $$renderer7.push("<!--[-->");
                                Table_body($$renderer7, {
                                  children: ($$renderer8) => {
                                    const each_array_3 = ensure_array_like(distribution.roster ?? []);
                                    if (each_array_3.length !== 0) {
                                      $$renderer8.push("<!--[-->");
                                      for (let $$index_3 = 0, $$length = each_array_3.length; $$index_3 < $$length; $$index_3++) {
                                        let candidate = each_array_3[$$index_3];
                                        if (Table_row) {
                                          $$renderer8.push("<!--[-->");
                                          Table_row($$renderer8, {
                                            children: ($$renderer9) => {
                                              if (Table_cell) {
                                                $$renderer9.push("<!--[-->");
                                                Table_cell($$renderer9, {
                                                  children: ($$renderer10) => {
                                                    $$renderer10.push(`<!---->${escape_html(candidate.applicant_reference ?? "—")}`);
                                                  },
                                                  $$slots: { default: true }
                                                });
                                                $$renderer9.push("<!--]-->");
                                              } else {
                                                $$renderer9.push("<!--[!-->");
                                                $$renderer9.push("<!--]-->");
                                              }
                                              $$renderer9.push(` `);
                                              if (Table_cell) {
                                                $$renderer9.push("<!--[-->");
                                                Table_cell($$renderer9, {
                                                  class: "font-medium",
                                                  children: ($$renderer10) => {
                                                    $$renderer10.push(`<!---->${escape_html(candidate.applicant_name)}`);
                                                  },
                                                  $$slots: { default: true }
                                                });
                                                $$renderer9.push("<!--]-->");
                                              } else {
                                                $$renderer9.push("<!--[!-->");
                                                $$renderer9.push("<!--]-->");
                                              }
                                              $$renderer9.push(` `);
                                              if (Table_cell) {
                                                $$renderer9.push("<!--[-->");
                                                Table_cell($$renderer9, {
                                                  children: ($$renderer10) => {
                                                    $$renderer10.push(`<!---->${escape_html(candidate.set_code)}`);
                                                  },
                                                  $$slots: { default: true }
                                                });
                                                $$renderer9.push("<!--]-->");
                                              } else {
                                                $$renderer9.push("<!--[!-->");
                                                $$renderer9.push("<!--]-->");
                                              }
                                            },
                                            $$slots: { default: true }
                                          });
                                          $$renderer8.push("<!--]-->");
                                        } else {
                                          $$renderer8.push("<!--[!-->");
                                          $$renderer8.push("<!--]-->");
                                        }
                                      }
                                    } else {
                                      $$renderer8.push("<!--[!-->");
                                      if (Table_row) {
                                        $$renderer8.push("<!--[-->");
                                        Table_row($$renderer8, {
                                          children: ($$renderer9) => {
                                            if (Table_cell) {
                                              $$renderer9.push("<!--[-->");
                                              Table_cell($$renderer9, {
                                                colspan: 3,
                                                class: "text-muted-foreground",
                                                children: ($$renderer10) => {
                                                  $$renderer10.push(`<!---->No candidates assigned.`);
                                                },
                                                $$slots: { default: true }
                                              });
                                              $$renderer9.push("<!--]-->");
                                            } else {
                                              $$renderer9.push("<!--[!-->");
                                              $$renderer9.push("<!--]-->");
                                            }
                                          },
                                          $$slots: { default: true }
                                        });
                                        $$renderer8.push("<!--]-->");
                                      } else {
                                        $$renderer8.push("<!--[!-->");
                                        $$renderer8.push("<!--]-->");
                                      }
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
                            },
                            $$slots: { default: true }
                          });
                          $$renderer6.push("<!--]-->");
                        } else {
                          $$renderer6.push("<!--[!-->");
                          $$renderer6.push("<!--]-->");
                        }
                        $$renderer6.push(`</div></div>`);
                      } else {
                        $$renderer6.push("<!--[-1-->");
                      }
                      $$renderer6.push(`<!--]--> `);
                      if (Dialog_footer) {
                        $$renderer6.push("<!--[-->");
                        Dialog_footer($$renderer6, {
                          children: ($$renderer7) => {
                            Button($$renderer7, {
                              variant: "outline",
                              onclick: closeDistribution,
                              children: ($$renderer8) => {
                                $$renderer8.push(`<!---->Close`);
                              },
                              $$slots: { default: true }
                            });
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
          $$renderer4.push(`</div>`);
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
  Monitoring as default
};
