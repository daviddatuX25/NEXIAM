import { g as sanitize_props, i as spread_props, j as slot, l as bind_props, e as escape_html, c as attr_class, a as ensure_array_like, b as attr, B as Button, d as derived, f as attr_style, s as stringify } from "../app.js";
import { D as Dialog, a as Dialog_content } from "./dialog-description-D12L0QA9.js";
import "clsx";
import { f as formatDate } from "./date-utils-Bc5VPPy9.js";
import { S as Sparkles } from "./sparkles-DbQIaVG6.js";
import { X } from "./x-CnxrMbMn.js";
import { C as Clock } from "./clock-B4fXoAi9.js";
import { I as Icon } from "./Icon-Dal7PqKK.js";
import { G as Graduation_cap } from "./graduation-cap-Bc9amiZ0.js";
import { Z as Zap } from "./zap-CyG8UtsE.js";
import { U as Users } from "./users-DWlguTa_.js";
import { R as Rotate_ccw } from "./rotate-ccw-D6Dm_efx.js";
import { C as Check } from "./check-DK45Y9SJ.js";
import { C as Circle_alert } from "./circle-alert-jWXJ7oC0.js";
import { L as Loader_circle } from "./loader-circle-_UEl10Lx.js";
import { router } from "@inertiajs/core";
function Type($$renderer, $$props) {
  const $$sanitized_props = sanitize_props($$props);
  const iconNode = [
    ["path", { "d": "M12 4v16" }],
    ["path", { "d": "M4 7V5a1 1 0 0 1 1-1h14a1 1 0 0 1 1 1v2" }],
    ["path", { "d": "M9 20h6" }]
  ];
  Icon($$renderer, spread_props([
    { name: "type" },
    $$sanitized_props,
    {
      /**
       * @component @name Type
       * @description Lucide SVG icon component, renders SVG Element with children.
       *
       * @preview ![img](data:image/svg+xml;base64,PHN2ZyAgeG1sbnM9Imh0dHA6Ly93d3cudzMub3JnLzIwMDAvc3ZnIgogIHdpZHRoPSIyNCIKICBoZWlnaHQ9IjI0IgogIHZpZXdCb3g9IjAgMCAyNCAyNCIKICBmaWxsPSJub25lIgogIHN0cm9rZT0iIzAwMCIgc3R5bGU9ImJhY2tncm91bmQtY29sb3I6ICNmZmY7IGJvcmRlci1yYWRpdXM6IDJweCIKICBzdHJva2Utd2lkdGg9IjIiCiAgc3Ryb2tlLWxpbmVjYXA9InJvdW5kIgogIHN0cm9rZS1saW5lam9pbj0icm91bmQiCj4KICA8cGF0aCBkPSJNMTIgNHYxNiIgLz4KICA8cGF0aCBkPSJNNCA3VjVhMSAxIDAgMCAxIDEtMWgxNGExIDEgMCAwIDEgMSAxdjIiIC8+CiAgPHBhdGggZD0iTTkgMjBoNiIgLz4KPC9zdmc+Cg==) - https://lucide.dev/icons/type
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
function CohortAutoDistributeModal($$renderer, $$props) {
  $$renderer.component(($$renderer2) => {
    let {
      open = false,
      domain = "exam",
      // 'exam' | 'consultation'
      academicCycleId = null,
      academicCycleName = "Current Academic Cycle",
      sessions = [],
      distribution = {},
      onSuccess = null
    } = $$props;
    let sortStrategy = "fcfs";
    let packingStrategy = "sequential";
    let autoAssignEnabled = false;
    let selectedSessionIds = [];
    let previewLoading = false;
    let commitLoading = false;
    let previewData = null;
    let errorMessage = "";
    let stalePlan = false;
    let committedResult = null;
    function getXsrfToken() {
      if (typeof document === "undefined") return "";
      const match = document.cookie.match(new RegExp("(^|;\\s*)XSRF-TOKEN=([^;]+)"));
      return match ? decodeURIComponent(match[2]) : "";
    }
    let eligibleSessions = derived(() => sessions.filter((s) => {
      const sessionCycleId = s.academic_cycle_id ?? s.academic_cycle?.id;
      const sameCycle = academicCycleId && sessionCycleId ? Number(sessionCycleId) === Number(academicCycleId) : true;
      const isDraft = s.status === "draft";
      const isStaffOnly = s.booking_mode === "staff_only";
      const roomCapacity = Number(s.room?.capacity ?? s.capacity ?? 0);
      const activeCount = Number(s.assigned_count ?? s.active_count ?? 0);
      const hasRoom = Boolean(s.room_id || s.room);
      return sameCycle && isDraft && isStaffOnly && hasRoom && roomCapacity - activeCount > 0;
    }).map((s) => {
      const roomCapacity = Number(s.room?.capacity ?? s.capacity ?? 0);
      const activeCount = Number(s.assigned_count ?? s.active_count ?? 0);
      return {
        id: Number(s.id),
        code: s.code,
        date: s.date ?? s.starts_at ?? s.scheduled_at,
        time: s.time ?? `${s.start_time ?? ""} - ${s.end_time ?? ""}`,
        room_name: s.room?.name ?? "Room",
        room_capacity: roomCapacity,
        active_count: activeCount,
        remaining_capacity: Math.max(0, roomCapacity - activeCount),
        sortKey: s.starts_at ?? s.scheduled_at ?? ""
      };
    }).sort((a, b) => a.sortKey.localeCompare(b.sortKey) || a.id - b.id));
    async function fetchPreview() {
      if (selectedSessionIds.length === 0 || !academicCycleId) {
        previewData = null;
        return;
      }
      previewLoading = true;
      errorMessage = "";
      stalePlan = false;
      try {
        const endpoint = domain === "exam" ? "/exams/sessions/auto-distribute/preview" : "/consultation/sessions/auto-distribute/preview";
        const response = await fetch(endpoint, {
          method: "POST",
          headers: {
            "Content-Type": "application/json",
            "Accept": "application/json",
            "X-XSRF-TOKEN": getXsrfToken()
          },
          body: JSON.stringify({
            academic_cycle_id: Number(academicCycleId),
            target_session_ids: selectedSessionIds,
            sort_strategy: sortStrategy,
            packing_strategy: packingStrategy
          })
        });
        if (!response.ok) {
          const errorData = await response.json();
          throw new Error(errorData.message || "Failed to generate preview.");
        }
        previewData = await response.json();
      } catch (e) {
        errorMessage = e.message;
        previewData = null;
      } finally {
        previewLoading = false;
      }
    }
    async function executeCommit() {
      if (!previewData || commitLoading) return;
      commitLoading = true;
      errorMessage = "";
      stalePlan = false;
      try {
        const endpoint = domain === "exam" ? "/exams/sessions/auto-distribute/commit" : "/consultation/sessions/auto-distribute/commit";
        const expectedVersions = {};
        for (const mapping of previewData.session_mappings) {
          expectedVersions[mapping.session_id] = mapping.version;
        }
        const response = await fetch(endpoint, {
          method: "POST",
          headers: {
            "Content-Type": "application/json",
            "Accept": "application/json",
            "X-XSRF-TOKEN": getXsrfToken()
          },
          body: JSON.stringify({
            academic_cycle_id: Number(academicCycleId),
            target_session_ids: selectedSessionIds,
            sort_strategy: sortStrategy,
            packing_strategy: packingStrategy,
            expected_plan_hash: previewData.plan_hash,
            expected_session_versions: expectedVersions,
            auto_assign_enabled: autoAssignEnabled
          })
        });
        if (response.status === 409) {
          const errorData = await response.json();
          stalePlan = true;
          errorMessage = errorData.message || "The plan is stale because sessions or candidates changed.";
          return;
        }
        if (!response.ok) {
          const errorData = await response.json();
          throw new Error(errorData.message || "Failed to execute distribution.");
        }
        committedResult = await response.json();
        if (onSuccess) {
          onSuccess(committedResult);
        }
        router.reload({ preserveScroll: true });
      } catch (e) {
        errorMessage = e.message;
      } finally {
        commitLoading = false;
      }
    }
    const poolCount = derived(() => previewData?.total_candidates ?? distribution.pool_count ?? 0);
    const eligibleCapacity = derived(() => previewData?.total_capacity ?? distribution.eligible_capacity ?? 0);
    const willBeAssigned = derived(() => previewData?.assignable_count ?? Math.min(poolCount(), eligibleCapacity()));
    const willRemain = derived(() => previewData?.unassigned_count ?? Math.max(0, poolCount() - willBeAssigned()));
    const remainingAutoCapacity = derived(() => Math.max(0, eligibleCapacity() - willBeAssigned()));
    const sortStrategyDescriptions = {
      fcfs: "First-Come, First-Served: Priority given to earliest accepted or result-ready applicants.",
      alphabetical: "Alphabetical: Candidates ordered alphabetically by surname (A to Z) across rooms.",
      program: "Program Track: Candidates grouped by course preference or Senior High strand."
    };
    let previewCandidates = derived(() => previewData?.candidates ?? []);
    let $$settled = true;
    let $$inner_renderer;
    function $$render_inner($$renderer3) {
      if (Dialog) {
        $$renderer3.push("<!--[-->");
        Dialog($$renderer3, {
          get open() {
            return open;
          },
          set open($$value) {
            open = $$value;
            $$settled = false;
          },
          children: ($$renderer4) => {
            if (Dialog_content) {
              $$renderer4.push("<!--[-->");
              Dialog_content($$renderer4, {
                class: "w-full sm:max-w-2xl max-h-[90vh] overflow-y-auto p-0 font-sans border-border bg-card shadow-2xl rounded-2xl",
                showCloseButton: false,
                children: ($$renderer5) => {
                  $$renderer5.push(`<div class="sticky top-0 z-10 bg-card/95 backdrop-blur-md border-b border-border/80 px-6 py-4 flex items-center justify-between"><div class="flex items-center gap-3"><div class="size-8 rounded-lg bg-primary/10 text-primary flex items-center justify-center shrink-0">`);
                  Sparkles($$renderer5, { class: "size-4" });
                  $$renderer5.push(`<!----></div> <div><h3 class="font-semibold text-foreground text-sm tracking-tight">${escape_html(domain === "exam" ? "Auto-Distribute Applicants" : "Auto-Distribute Examinees")}</h3> <p class="text-[11px] text-muted-foreground font-mono">${escape_html(domain === "exam" ? "Entrance Examination" : "Interview & Consultation")} • ${escape_html(academicCycleName)}</p></div></div> <button type="button" class="size-7 rounded-md border border-border/60 hover:bg-muted/80 text-muted-foreground hover:text-foreground flex items-center justify-center transition-colors cursor-pointer" aria-label="Close">`);
                  X($$renderer5, { class: "size-3.5" });
                  $$renderer5.push(`<!----></button></div> <div class="px-6 py-5 space-y-5">`);
                  if (committedResult) {
                    $$renderer5.push("<!--[0-->");
                    $$renderer5.push(`<div class="p-6 rounded-xl bg-primary/[0.04] border border-primary/20 text-center space-y-4"><div class="size-10 rounded-full bg-primary text-primary-foreground mx-auto flex items-center justify-center shadow-sm">`);
                    Check($$renderer5, { class: "size-5 stroke-[2.5]" });
                    $$renderer5.push(`<!----></div> <div><h4 class="text-sm font-semibold text-foreground">Distribution Completed Successfully</h4> <p class="text-xs text-muted-foreground mt-1">Assigned <span class="font-semibold text-foreground">${escape_html(committedResult.assigned_count)}</span> applicants. <span class="font-semibold text-foreground">${escape_html(committedResult.unassigned_count)}</span> remain in pool.</p></div> <div class="grid grid-cols-2 sm:grid-cols-3 gap-2 text-left font-mono text-xs pt-1"><!--[-->`);
                    const each_array = ensure_array_like(committedResult.session_mappings);
                    for (let $$index = 0, $$length = each_array.length; $$index < $$length; $$index++) {
                      let mapping = each_array[$$index];
                      $$renderer5.push(`<div class="p-2.5 rounded-lg bg-card border border-border/60"><p class="font-bold text-foreground text-[11px]">${escape_html(mapping.session_code)}</p> <p class="text-[10px] text-muted-foreground truncate">${escape_html(mapping.room_name)}</p> <p class="text-primary font-semibold text-[11px] mt-1">+${escape_html(mapping.allocated_count)} assigned</p></div>`);
                    }
                    $$renderer5.push(`<!--]--></div> `);
                    Button($$renderer5, {
                      size: "sm",
                      class: "mt-2 text-xs",
                      onclick: () => open = false,
                      children: ($$renderer6) => {
                        $$renderer6.push(`<!---->Done`);
                      },
                      $$slots: { default: true }
                    });
                    $$renderer5.push(`<!----></div>`);
                  } else {
                    $$renderer5.push("<!--[-1-->");
                    $$renderer5.push(`<div class="flex items-center justify-between p-3 rounded-xl border border-border/80 bg-muted/20 font-mono text-xs"><div class="space-y-0.5"><p class="text-[10px] uppercase font-bold text-muted-foreground tracking-wider">Unassigned Pool</p> <p class="text-lg font-bold text-foreground tabular-nums">${escape_html(poolCount())}</p></div> <div class="h-7 w-px bg-border/80"></div> <div class="space-y-0.5"><p class="text-[10px] uppercase font-bold text-muted-foreground tracking-wider">Available Capacity</p> <p class="text-lg font-bold text-foreground tabular-nums">${escape_html(eligibleCapacity())}</p></div> <div class="h-7 w-px bg-border/80"></div> <div class="space-y-0.5"><p class="text-[10px] uppercase font-bold text-muted-foreground tracking-wider">Batch Allocation</p> <div class="flex items-baseline gap-1.5"><span class="text-lg font-bold text-primary tabular-nums">${escape_html(willBeAssigned())}</span> `);
                    if (willRemain() > 0) {
                      $$renderer5.push("<!--[0-->");
                      $$renderer5.push(`<span class="text-[10px] text-muted-foreground font-sans">(${escape_html(willRemain())} remain)</span>`);
                    } else {
                      $$renderer5.push("<!--[-1-->");
                    }
                    $$renderer5.push(`<!--]--></div></div></div> `);
                    if (stalePlan) {
                      $$renderer5.push("<!--[0-->");
                      $$renderer5.push(`<div class="p-3.5 rounded-xl bg-destructive/10 border border-destructive/20 flex items-start gap-2.5 text-xs text-destructive">`);
                      Circle_alert($$renderer5, { class: "size-4 shrink-0 mt-0.5" });
                      $$renderer5.push(`<!----> <div class="flex-1"><p class="font-semibold">Plan is stale</p> <p class="text-[11px] opacity-90 mt-0.5">${escape_html(errorMessage)}</p> `);
                      Button($$renderer5, {
                        size: "xs",
                        variant: "outline",
                        class: "mt-2 text-[11px] gap-1.5 border-destructive/30 text-destructive hover:bg-destructive/10",
                        onclick: fetchPreview,
                        children: ($$renderer6) => {
                          Rotate_ccw($$renderer6, { class: "size-3" });
                          $$renderer6.push(`<!----> Reload Preview`);
                        },
                        $$slots: { default: true }
                      });
                      $$renderer5.push(`<!----></div></div>`);
                    } else if (errorMessage) {
                      $$renderer5.push("<!--[1-->");
                      $$renderer5.push(`<div class="p-3 rounded-xl bg-destructive/10 border border-destructive/20 text-xs text-destructive">${escape_html(errorMessage)}</div>`);
                    } else {
                      $$renderer5.push("<!--[-1-->");
                    }
                    $$renderer5.push(`<!--]--> <div class="p-3.5 rounded-xl border border-border/80 bg-muted/10 space-y-2"><div class="flex items-center justify-between"><span class="text-[10px] font-bold text-muted-foreground uppercase tracking-wider font-mono">Candidate Sorting</span> <span class="text-[10px] font-medium text-primary font-mono capitalize">${escape_html(
                      "First-Come, First-Served"
                    )}</span></div> <div class="grid grid-cols-3 gap-1.5 p-1 bg-muted/40 rounded-lg border border-border/60"><button type="button"${attr_class(`flex items-center justify-center gap-1.5 py-1.5 px-2 rounded-md text-xs font-medium transition-all cursor-pointer ${"bg-card text-foreground shadow-xs border border-border/60 font-semibold"}`)}>`);
                    Clock($$renderer5, { class: "size-3.5 text-primary" });
                    $$renderer5.push(`<!----> <span>Earliest (FCFS)</span></button> <button type="button"${attr_class(`flex items-center justify-center gap-1.5 py-1.5 px-2 rounded-md text-xs font-medium transition-all cursor-pointer ${"text-muted-foreground hover:text-foreground"}`)}>`);
                    Type($$renderer5, { class: "size-3.5 text-primary" });
                    $$renderer5.push(`<!----> <span>Surname A–Z</span></button> <button type="button"${attr_class(`flex items-center justify-center gap-1.5 py-1.5 px-2 rounded-md text-xs font-medium transition-all cursor-pointer ${"text-muted-foreground hover:text-foreground"}`)}>`);
                    Graduation_cap($$renderer5, { class: "size-3.5 text-primary" });
                    $$renderer5.push(`<!----> <span>Program Track</span></button></div> <p class="text-[11px] text-muted-foreground leading-relaxed pl-0.5">${escape_html(sortStrategyDescriptions[sortStrategy])}</p></div> <div class="space-y-1.5"><span class="text-[10px] font-bold text-muted-foreground uppercase tracking-wider font-mono block">Distribution Packing Method</span> <div class="grid grid-cols-1 sm:grid-cols-2 gap-2.5"><button type="button"${attr_class(`flex items-start gap-3 p-3 rounded-xl border text-left transition-all cursor-pointer ${"border-primary bg-primary/[0.03] ring-1 ring-primary/30"}`)}><div class="size-10 rounded-lg bg-muted/30 border border-border/60 flex items-center justify-center shrink-0 mt-0.5"><svg class="w-7 h-5" viewBox="0 0 28 20" fill="none"><rect x="2" y="2" width="6" height="16" rx="1.5" class="fill-primary"></rect><rect x="11" y="2" width="6" height="16" rx="1.5" class="fill-muted/60"></rect><rect x="11" y="10" width="6" height="8" rx="1.5" class="fill-primary"></rect><rect x="20" y="2" width="6" height="16" rx="1.5" class="fill-muted/40"></rect></svg></div> <div class="min-w-0"><div class="flex items-center gap-1.5"><span class="text-xs font-semibold text-foreground">Sequential Fill</span> <span class="text-[9px] font-mono text-primary font-semibold">100% First</span></div> <p class="text-[10px] text-muted-foreground mt-0.5 leading-snug">Fills earlier rooms completely before assigning into subsequent rooms.</p></div></button> <button type="button"${attr_class(`flex items-start gap-3 p-3 rounded-xl border text-left transition-all cursor-pointer ${"border-border/80 bg-card hover:border-border hover:bg-muted/20"}`)}><div class="size-10 rounded-lg bg-muted/30 border border-border/60 flex items-center justify-center shrink-0 mt-0.5"><svg class="w-7 h-5" viewBox="0 0 28 20" fill="none"><rect x="2" y="2" width="6" height="16" rx="1.5" class="fill-muted/60"></rect><rect x="2" y="10" width="6" height="8" rx="1.5" class="fill-primary"></rect><rect x="11" y="2" width="6" height="16" rx="1.5" class="fill-muted/60"></rect><rect x="11" y="10" width="6" height="8" rx="1.5" class="fill-primary"></rect><rect x="20" y="2" width="6" height="16" rx="1.5" class="fill-muted/60"></rect><rect x="20" y="10" width="6" height="8" rx="1.5" class="fill-primary"></rect></svg></div> <div class="min-w-0"><div class="flex items-center gap-1.5"><span class="text-xs font-semibold text-foreground">Balanced Load</span> <span class="text-[9px] font-mono text-primary font-semibold">Equal Split</span></div> <p class="text-[10px] text-muted-foreground mt-0.5 leading-snug">Splits the cohort evenly across all selected rooms to balance proctor load.</p></div></button></div></div> <div class="space-y-2"><div class="flex items-center justify-between text-xs"><span class="text-[10px] font-bold text-muted-foreground uppercase tracking-wider font-mono">Available Target Sessions (${escape_html(selectedSessionIds.length)} of ${escape_html(eligibleSessions().length)})</span> <button type="button" class="text-[11px] text-primary hover:underline font-medium cursor-pointer">${escape_html(selectedSessionIds.length === eligibleSessions().length ? "Deselect All" : "Select All")}</button></div> `);
                    if (eligibleSessions().length === 0) {
                      $$renderer5.push("<!--[0-->");
                      $$renderer5.push(`<div class="p-6 rounded-xl border border-dashed border-border/80 text-center text-xs text-muted-foreground font-mono space-y-1"><p class="font-medium text-foreground">No available draft sessions found</p> <p class="text-[11px]">Create an unpublished staff-only session or reopen capacity to distribute.</p></div>`);
                    } else {
                      $$renderer5.push("<!--[-1-->");
                      $$renderer5.push(`<div class="space-y-1.5 max-h-48 overflow-y-auto pr-1"><!--[-->`);
                      const each_array_1 = ensure_array_like(eligibleSessions());
                      for (let index = 0, $$length = each_array_1.length; index < $$length; index++) {
                        let session = each_array_1[index];
                        $$renderer5.push(`<div role="button" tabindex="0" class="flex items-center gap-3 p-2.5 rounded-lg border border-border/80 bg-card hover:border-primary/40 transition-all cursor-pointer text-xs"><input type="checkbox"${attr("checked", selectedSessionIds.includes(session.id), true)} class="size-3.5 rounded accent-primary pointer-events-none"/> <span class="size-5 rounded-full bg-primary/10 text-primary font-mono text-[10px] font-bold flex items-center justify-center shrink-0">${escape_html(index + 1)}</span> <div class="flex-1 min-w-0 font-mono"><div class="flex items-center gap-2"><span class="font-bold text-foreground text-[11px]">${escape_html(session.code)}</span> <span class="text-[10px] text-muted-foreground truncate">${escape_html(session.room_name)}</span></div> <p class="text-[10px] text-muted-foreground truncate">${escape_html(session.date ? formatDate(session.date) : "Date TBD")} ${escape_html(session.time ? `· ${session.time}` : "")}</p></div> <div class="text-right font-mono text-[11px] shrink-0"><span class="font-semibold text-primary">${escape_html(session.remaining_capacity)} open</span> <span class="text-muted-foreground text-[10px] block">(${escape_html(session.active_count)}/${escape_html(session.room_capacity)})</span></div></div>`);
                      }
                      $$renderer5.push(`<!--]--></div>`);
                    }
                    $$renderer5.push(`<!--]--></div> <div class="p-3.5 rounded-xl border border-primary/30 bg-primary/[0.03] flex items-center justify-between gap-3"><div class="flex items-start gap-2.5 min-w-0"><div class="size-8 rounded-lg bg-primary/10 text-primary flex items-center justify-center shrink-0 mt-0.5">`);
                    Zap($$renderer5, { class: "size-4" });
                    $$renderer5.push(`<!----></div> <div class="min-w-0"><div class="flex items-center gap-2"><span class="text-xs font-semibold text-foreground">Continuous Auto-Assign</span> `);
                    {
                      $$renderer5.push("<!--[-1-->");
                    }
                    $$renderer5.push(`<!--]--></div> <p class="text-[11px] text-muted-foreground mt-0.5 leading-snug">`);
                    {
                      $$renderer5.push("<!--[-1-->");
                      $$renderer5.push(`Toggle on to automatically schedule future applicants into remaining seats without running another batch.`);
                    }
                    $$renderer5.push(`<!--]--></p></div></div> <button type="button" role="switch"${attr("aria-checked", autoAssignEnabled)}${attr_class(`w-11 h-6 rounded-full transition-colors relative cursor-pointer shrink-0 ${"bg-muted-foreground/30"}`)}><span${attr_class(`size-4 rounded-full bg-white absolute top-1 transition-transform shadow-xs ${"left-1"}`)}></span></button></div> `);
                    if (previewData && previewData.session_mappings.length > 0) {
                      $$renderer5.push("<!--[0-->");
                      $$renderer5.push(`<div class="space-y-2 p-3.5 rounded-xl border border-border/80 bg-muted/10 font-mono text-xs"><div class="flex items-center justify-between text-[10px] text-muted-foreground uppercase font-bold tracking-wider"><span>Projected Allocation</span> <span>Immediate (${escape_html(willBeAssigned())}) • Remaining Open (${escape_html(remainingAutoCapacity())})</span></div> <div class="space-y-2 pt-0.5"><!--[-->`);
                      const each_array_2 = ensure_array_like(previewData.session_mappings);
                      for (let $$index_2 = 0, $$length = each_array_2.length; $$index_2 < $$length; $$index_2++) {
                        let mapping = each_array_2[$$index_2];
                        const newTotal = mapping.current_active + mapping.allocated_count;
                        const allocatedPct = mapping.room_capacity > 0 ? mapping.allocated_count / mapping.room_capacity * 100 : 0;
                        const priorPct = mapping.room_capacity > 0 ? mapping.current_active / mapping.room_capacity * 100 : 0;
                        Math.max(0, mapping.room_capacity - newTotal);
                        $$renderer5.push(`<div class="space-y-1"><div class="flex items-center justify-between text-[11px]"><span class="text-foreground font-semibold">${escape_html(mapping.session_code)} <span class="text-muted-foreground text-[10px] font-normal">(${escape_html(mapping.room_name)})</span></span> <span class="text-[11px]"><strong class="text-primary">+${escape_html(mapping.allocated_count)} batch</strong> <span class="text-muted-foreground text-[10px]">(${escape_html(newTotal)} / ${escape_html(mapping.room_capacity)})</span></span></div> <div class="w-full bg-muted rounded-full h-1.5 overflow-hidden flex">`);
                        if (priorPct > 0) {
                          $$renderer5.push("<!--[0-->");
                          $$renderer5.push(`<div class="bg-muted-foreground/40 h-full"${attr_style(`width: ${stringify(priorPct)}%`)}></div>`);
                        } else {
                          $$renderer5.push("<!--[-1-->");
                        }
                        $$renderer5.push(`<!--]--> <div class="bg-primary h-full transition-all duration-300"${attr_style(`width: ${stringify(allocatedPct)}%`)}></div></div> `);
                        {
                          $$renderer5.push("<!--[-1-->");
                        }
                        $$renderer5.push(`<!--]--></div>`);
                      }
                      $$renderer5.push(`<!--]--></div></div>`);
                    } else {
                      $$renderer5.push("<!--[-1-->");
                    }
                    $$renderer5.push(`<!--]--> `);
                    if (previewCandidates().length > 0) {
                      $$renderer5.push("<!--[0-->");
                      $$renderer5.push(`<div class="rounded-xl border border-border/80 overflow-hidden font-mono text-xs"><button type="button" class="w-full flex items-center justify-between p-3 bg-muted/20 hover:bg-muted/40 transition-all cursor-pointer text-left font-semibold text-foreground text-xs"><div class="flex items-center gap-2">`);
                      Users($$renderer5, { class: "size-4 text-primary" });
                      $$renderer5.push(`<!----> <span>Inspect Candidate Cohort (${escape_html(previewCandidates().length)})</span></div> <span class="text-primary text-[11px] font-normal">${escape_html("Show Candidates ↓")}</span></button> `);
                      {
                        $$renderer5.push("<!--[-1-->");
                      }
                      $$renderer5.push(`<!--]--></div>`);
                    } else {
                      $$renderer5.push("<!--[-1-->");
                    }
                    $$renderer5.push(`<!--]--> <div class="flex items-center justify-between pt-2 border-t border-border/60">`);
                    Button($$renderer5, {
                      variant: "ghost",
                      size: "sm",
                      class: "text-xs",
                      onclick: () => open = false,
                      children: ($$renderer6) => {
                        $$renderer6.push(`<!---->Cancel`);
                      },
                      $$slots: { default: true }
                    });
                    $$renderer5.push(`<!----> <div class="flex items-center gap-2">`);
                    Button($$renderer5, {
                      variant: "outline",
                      size: "sm",
                      class: "gap-1.5 text-xs",
                      onclick: fetchPreview,
                      disabled: previewLoading || commitLoading || selectedSessionIds.length === 0,
                      children: ($$renderer6) => {
                        if (previewLoading) {
                          $$renderer6.push("<!--[0-->");
                          Loader_circle($$renderer6, { class: "size-3 animate-spin" });
                        } else {
                          $$renderer6.push("<!--[-1-->");
                          Rotate_ccw($$renderer6, { class: "size-3" });
                        }
                        $$renderer6.push(`<!--]--> Recalculate`);
                      },
                      $$slots: { default: true }
                    });
                    $$renderer5.push(`<!----> `);
                    Button($$renderer5, {
                      size: "sm",
                      class: "gap-1.5 text-xs font-semibold",
                      disabled: !previewData || previewData.assignable_count === 0 || commitLoading || previewLoading,
                      onclick: executeCommit,
                      children: ($$renderer6) => {
                        if (commitLoading) {
                          $$renderer6.push("<!--[0-->");
                          Loader_circle($$renderer6, { class: "size-3.5 animate-spin" });
                          $$renderer6.push(`<!----> Distributing…`);
                        } else {
                          $$renderer6.push("<!--[-1-->");
                          Sparkles($$renderer6, { class: "size-3.5" });
                          $$renderer6.push(`<!----> Distribute (${escape_html(willBeAssigned())})`);
                        }
                        $$renderer6.push(`<!--]-->`);
                      },
                      $$slots: { default: true }
                    });
                    $$renderer5.push(`<!----></div></div>`);
                  }
                  $$renderer5.push(`<!--]--></div>`);
                },
                $$slots: { default: true }
              });
              $$renderer4.push("<!--]-->");
            } else {
              $$renderer4.push("<!--[!-->");
              $$renderer4.push("<!--]-->");
            }
          },
          $$slots: { default: true }
        });
        $$renderer3.push("<!--]-->");
      } else {
        $$renderer3.push("<!--[!-->");
        $$renderer3.push("<!--]-->");
      }
    }
    do {
      $$settled = true;
      $$inner_renderer = $$renderer2.copy();
      $$render_inner($$inner_renderer);
    } while (!$$settled);
    $$renderer2.subsume($$inner_renderer);
    bind_props($$props, { open });
  });
}
export {
  CohortAutoDistributeModal as C
};
