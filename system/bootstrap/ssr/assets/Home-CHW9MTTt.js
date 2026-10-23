import { o as onDestroy, e as escape_html, f as attr_style, h as head, a as ensure_array_like, s as stringify, d as derived } from "../app.js";
import { S as StaffLayout } from "./StaffLayout-CR-MZopa.js";
import { T as TourTriggerGlyph, X as XiamNpcDialog } from "./XiamNpcDialog-DoqCSHj9.js";
import { B as BentoCard } from "./BentoCard-COpmyjxA.js";
import "chart.js/auto";
import { D as DoughnutChart } from "./DoughnutChart-DGhkPrlH.js";
import "./badge-D9Vgs7xh.js";
import { L as Link } from "./Link-B7ks7rBK.js";
import "axios";
import "@inertiajs/core";
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
/* empty css                                                        */
import "./circle-question-mark-DY-RU614.js";
import "./sparkles-DbQIaVG6.js";
import "./chevron-left-DCSOZwZl.js";
function LineChart($$renderer, $$props) {
  $$renderer.component(($$renderer2) => {
    let {
      title = "",
      subtitle = "",
      labels = [],
      datasets = [],
      values = null,
      height = 240,
      color = "#10b981"
    } = $$props;
    onDestroy(() => {
    });
    $$renderer2.push(`<div class="space-y-2">`);
    if (title) {
      $$renderer2.push("<!--[0-->");
      $$renderer2.push(`<div class="flex items-center justify-between"><div><h4 class="text-sm font-bold tracking-tight text-foreground">${escape_html(title)}</h4> `);
      if (subtitle) {
        $$renderer2.push("<!--[0-->");
        $$renderer2.push(`<p class="text-xs text-muted-foreground font-mono">${escape_html(subtitle)}</p>`);
      } else {
        $$renderer2.push("<!--[-1-->");
      }
      $$renderer2.push(`<!--]--></div></div>`);
    } else {
      $$renderer2.push("<!--[-1-->");
    }
    $$renderer2.push(`<!--]--> <div${attr_style(`height: ${height}px`)} class="w-full relative"><canvas></canvas></div></div>`);
  });
}
function Home($$renderer, $$props) {
  $$renderer.component(($$renderer2) => {
    let { staff, dashboard } = $$props;
    let tourOpen = false;
    const breadcrumbs = [{ label: "Operations Dashboard" }];
    const registrar = derived(() => dashboard?.registrar ?? null);
    const guidance = derived(() => dashboard?.guidance ?? null);
    const programHead = derived(() => dashboard?.program_head ?? null);
    const myActivity = derived(() => dashboard?.my_activity ?? []);
    const activeCycle = derived(() => dashboard?.active_cycle ?? null);
    const operationsContext = derived(() => {
      const hasReg = Boolean(registrar());
      const hasGui = Boolean(guidance());
      const hasPh = Boolean(programHead());
      const office = staff?.scope?.office ?? "Campus Administration";
      const name = staff?.name ?? "Colleague";
      const safetyKey = `${office}:${hasReg ? "reg" : "noreg"}:${hasGui ? "gui" : "nogui"}:${hasPh ? "ph" : "noph"}:${activeCycle()?.name ?? "nocycle"}`;
      return {
        entityKey: "staff-operations-hub",
        safetyKey,
        freshness: "current",
        staffOffice: office,
        staffName: name,
        activeCycleName: activeCycle()?.name ?? null,
        hasRegistrar: hasReg,
        hasGuidance: hasGui,
        hasProgramHead: hasPh,
        activityCount: myActivity().length
      };
    });
    function formatTime(iso) {
      if (!iso) return "";
      try {
        const d = new Date(iso);
        return d.toLocaleTimeString([], { hour: "2-digit", minute: "2-digit" });
      } catch {
        return iso;
      }
    }
    let $$settled = true;
    let $$inner_renderer;
    function $$render_inner($$renderer3) {
      head("1o1nwrl", $$renderer3, ($$renderer4) => {
        $$renderer4.title(($$renderer5) => {
          $$renderer5.push(`<title>Staff Operations Dashboard • NEXIAM</title>`);
        });
      });
      StaffLayout($$renderer3, {
        breadcrumbs,
        children: ($$renderer4) => {
          $$renderer4.push(`<div class="min-w-0 space-y-8 p-4 sm:p-6 lg:p-8" data-tour="operations-dashboard-summary"><div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 pb-4 border-b border-border/60"><div><div class="flex flex-wrap items-center gap-2 min-w-0"><h1 class="text-2xl sm:text-3xl font-bold tracking-tight text-foreground">Operations Dashboard</h1> `);
          TourTriggerGlyph($$renderer4, {
            tourKey: "staff-operations-dashboard",
            label: "Open the staff operations guide",
            onclick: () => tourOpen = true
          });
          $$renderer4.push(`<!----> `);
          if (activeCycle()) {
            $$renderer4.push("<!--[0-->");
            $$renderer4.push(`<span class="inline-flex items-center gap-1.5 px-2.5 py-0.5 rounded-full bg-primary/10 text-primary border border-primary/20 text-xs font-mono font-semibold max-w-full"><span class="h-1.5 w-1.5 rounded-full bg-primary shrink-0"></span> <span class="truncate">${escape_html(activeCycle().name)}</span></span>`);
          } else {
            $$renderer4.push("<!--[-1-->");
          }
          $$renderer4.push(`<!--]--></div> <p class="text-xs text-muted-foreground mt-1">Signed in as <strong class="text-foreground">${escape_html(staff?.name)}</strong> • ${escape_html(staff?.scope?.office ?? "Campus Administration")}</p></div> <div class="flex items-center gap-2"><span class="text-[11px] font-mono text-muted-foreground">Fresh as of: ${escape_html(formatTime(dashboard?.as_of))}</span></div></div> `);
          if (registrar()) {
            $$renderer4.push("<!--[0-->");
            $$renderer4.push(`<section class="space-y-4" data-tour="operations-registrar"><div class="flex items-center justify-between"><span class="text-xs font-mono font-bold uppercase tracking-wider text-muted-foreground">Admissions Inflow &amp; Pipeline</span> `);
            Link($$renderer4, {
              href: "/admissions",
              class: "text-xs text-primary font-bold hover:underline font-mono",
              children: ($$renderer5) => {
                $$renderer5.push(`<!---->Open Admissions Registry →`);
              },
              $$slots: { default: true }
            });
            $$renderer4.push(`<!----></div> <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">`);
            BentoCard($$renderer4, {
              class: "p-5 space-y-2",
              children: ($$renderer5) => {
                $$renderer5.push(`<div class="flex items-center justify-between gap-2 min-w-0"><span class="text-[10px] font-mono font-bold uppercase tracking-wider text-muted-foreground shrink-0">Total Inflow</span> <span class="text-[10px] font-mono font-bold text-emerald-600 dark:text-emerald-400 bg-emerald-500/10 px-2 py-0.5 rounded-full border border-emerald-500/20 shrink-0">${escape_html(registrar().kpis.yoy_delta ?? registrar().kpis.yoy_growth)}</span></div> <div class="text-3xl font-extrabold font-mono tracking-tight text-foreground tabular-nums">${escape_html(registrar().kpis.total.toLocaleString())}</div> <div class="space-y-0.5 pt-1 border-t border-border/50 text-[10px] font-mono text-muted-foreground"><div>${escape_html(registrar().kpis.quota_percentage)}% of ${escape_html(registrar().kpis.quota_target.toLocaleString())} Quota</div> `);
                if (registrar().kpis.yoy_comparison) {
                  $$renderer5.push("<!--[0-->");
                  $$renderer5.push(`<div class="text-[9px] text-muted-foreground/80 truncate">(${escape_html(registrar().kpis.yoy_comparison)})</div>`);
                } else {
                  $$renderer5.push("<!--[-1-->");
                }
                $$renderer5.push(`<!--]--></div>`);
              },
              $$slots: { default: true }
            });
            $$renderer4.push(`<!----> `);
            BentoCard($$renderer4, {
              class: "p-5 space-y-2",
              children: ($$renderer5) => {
                $$renderer5.push(`<div class="flex items-center justify-between"><span class="text-[10px] font-mono font-bold uppercase tracking-wider text-muted-foreground">In Pipeline</span> <span class="text-[10px] font-mono font-bold text-amber-600 dark:text-amber-400 bg-amber-500/10 px-2 py-0.5 rounded-full border border-amber-500/20">Active</span></div> <div class="text-3xl font-extrabold font-mono tracking-tight text-foreground tabular-nums">${escape_html(registrar().kpis.in_pipeline.toLocaleString())}</div> <div class="text-[10px] font-mono text-muted-foreground pt-1 border-t border-border/50">Pending verification / exam</div>`);
              },
              $$slots: { default: true }
            });
            $$renderer4.push(`<!----> `);
            BentoCard($$renderer4, {
              class: "p-5 space-y-2",
              children: ($$renderer5) => {
                $$renderer5.push(`<div class="flex items-center justify-between"><span class="text-[10px] font-mono font-bold uppercase tracking-wider text-muted-foreground">Finalized</span> <span class="text-[10px] font-mono font-bold text-emerald-600 dark:text-emerald-400 bg-emerald-500/10 px-2 py-0.5 rounded-full border border-emerald-500/20">Accepted</span></div> <div class="text-3xl font-extrabold font-mono tracking-tight text-emerald-600 dark:text-emerald-400 tabular-nums">${escape_html(registrar().kpis.completed.toLocaleString())}</div> <div class="text-[10px] font-mono text-muted-foreground pt-1 border-t border-border/50">Cleared for slot booking</div>`);
              },
              $$slots: { default: true }
            });
            $$renderer4.push(`<!----> `);
            BentoCard($$renderer4, {
              class: "p-5 space-y-2",
              children: ($$renderer5) => {
                $$renderer5.push(`<div class="flex items-center justify-between"><span class="text-[10px] font-mono font-bold uppercase tracking-wider text-muted-foreground">Dismissed</span> <span class="text-[10px] font-mono font-bold text-rose-500 bg-rose-500/10 px-2 py-0.5 rounded-full border border-rose-500/20">Ineligible</span></div> <div class="text-3xl font-extrabold font-mono tracking-tight text-foreground tabular-nums">${escape_html(registrar().kpis.dismissed.toLocaleString())}</div> <div class="text-[10px] font-mono text-muted-foreground pt-1 border-t border-border/50">Withdrawn or disqualified</div>`);
              },
              $$slots: { default: true }
            });
            $$renderer4.push(`<!----></div> <div class="grid grid-cols-1 md:grid-cols-12 gap-5">`);
            BentoCard($$renderer4, {
              class: "md:col-span-8 p-6 space-y-4 flex flex-col justify-between",
              children: ($$renderer5) => {
                $$renderer5.push(`<div class="flex items-center justify-between"><div><h3 class="font-bold text-sm text-foreground">Application Growth Trajectory</h3> <p class="text-xs text-muted-foreground font-mono">Monthly intake submissions over rolling 6-month window</p></div></div> <p class="sr-only">Monthly submissions: ${escape_html(registrar().growth_trend.labels.map((l, i) => `${l}: ${registrar().growth_trend.values[i] ?? 0}`).join(", "))}</p> `);
                LineChart($$renderer5, {
                  labels: registrar().growth_trend.labels,
                  values: registrar().growth_trend.values,
                  height: 220,
                  color: "#10b981"
                });
                $$renderer5.push(`<!---->`);
              },
              $$slots: { default: true }
            });
            $$renderer4.push(`<!----> `);
            BentoCard($$renderer4, {
              class: "md:col-span-4 p-6 space-y-4 flex flex-col justify-between",
              children: ($$renderer5) => {
                $$renderer5.push(`<div><h3 class="font-bold text-sm text-foreground">Pipeline Breakdown</h3> <p class="text-xs text-muted-foreground font-mono">Status distribution across stages</p></div> <p class="sr-only">Pipeline distribution: ${escape_html(registrar().pipeline_distribution.map((d) => `${d.label}: ${d.value}`).join(", "))}</p> `);
                DoughnutChart($$renderer5, { data: registrar().pipeline_distribution, height: 190 });
                $$renderer5.push(`<!---->`);
              },
              $$slots: { default: true }
            });
            $$renderer4.push(`<!----></div> `);
            if (registrar().top_courses.length > 0) {
              $$renderer4.push("<!--[0-->");
              $$renderer4.push(`<div class="grid grid-cols-1 md:grid-cols-12 gap-5">`);
              BentoCard($$renderer4, {
                class: "md:col-span-6 p-6 space-y-4",
                children: ($$renderer5) => {
                  $$renderer5.push(`<div><h3 class="font-bold text-sm text-foreground">Top Degree Program Preferences</h3> <p class="text-xs text-muted-foreground font-mono">First-choice applicant volume</p></div> `);
                  DoughnutChart($$renderer5, { data: registrar().top_courses, height: 180 });
                  $$renderer5.push(`<!---->`);
                },
                $$slots: { default: true }
              });
              $$renderer4.push(`<!----> `);
              BentoCard($$renderer4, {
                class: "md:col-span-6 p-6 space-y-4 flex flex-col justify-between",
                children: ($$renderer5) => {
                  $$renderer5.push(`<div><h3 class="font-bold text-sm text-foreground">Program Preference Breakdown</h3> <p class="text-xs text-muted-foreground font-mono">Ranked by applicant demand</p></div> <div class="space-y-2 font-mono text-xs"><!--[-->`);
                  const each_array = ensure_array_like(registrar().top_courses);
                  for (let i = 0, $$length = each_array.length; i < $$length; i++) {
                    let course = each_array[i];
                    $$renderer5.push(`<div class="flex items-center justify-between p-2.5 rounded-xl bg-muted/40 border border-border/60"><span class="font-bold text-foreground flex items-center gap-2"><span class="text-[10px] px-1.5 py-0.5 rounded bg-primary/10 text-primary">#${escape_html(i + 1)}</span> ${escape_html(course.label)}</span> <span class="font-bold text-foreground">${escape_html(course.value)} applicants</span></div>`);
                  }
                  $$renderer5.push(`<!--]--></div>`);
                },
                $$slots: { default: true }
              });
              $$renderer4.push(`<!----></div>`);
            } else {
              $$renderer4.push("<!--[-1-->");
            }
            $$renderer4.push(`<!--]--></section>`);
          } else {
            $$renderer4.push("<!--[-1-->");
          }
          $$renderer4.push(`<!--]--> `);
          if (guidance()) {
            $$renderer4.push("<!--[0-->");
            $$renderer4.push(`<section class="space-y-4" data-tour="operations-guidance"><div class="flex items-center justify-between"><span class="text-xs font-mono font-bold uppercase tracking-wider text-muted-foreground">Examination &amp; Testing Operations</span> `);
            Link($$renderer4, {
              href: "/exams/sessions",
              class: "text-xs text-primary font-bold hover:underline font-mono",
              children: ($$renderer5) => {
                $$renderer5.push(`<!---->Open Exam Scheduling →`);
              },
              $$slots: { default: true }
            });
            $$renderer4.push(`<!----></div> <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">`);
            BentoCard($$renderer4, {
              class: "p-5 space-y-2",
              children: ($$renderer5) => {
                $$renderer5.push(`<span class="text-[10px] font-mono font-bold uppercase tracking-wider text-muted-foreground">Scheduled Sessions</span> <div class="text-3xl font-extrabold font-mono tracking-tight text-foreground tabular-nums">${escape_html(guidance().kpis.scheduled_sessions)}</div> <p class="text-[10px] text-muted-foreground font-mono">Testing center operations</p>`);
              },
              $$slots: { default: true }
            });
            $$renderer4.push(`<!----> `);
            BentoCard($$renderer4, {
              class: "p-5 space-y-2",
              children: ($$renderer5) => {
                $$renderer5.push(`<div class="flex items-center justify-between"><span class="text-[10px] font-mono font-bold uppercase tracking-wider text-muted-foreground">Tested Candidates</span> <span class="text-[10px] font-mono font-bold text-emerald-600 dark:text-emerald-400">${escape_html(guidance().kpis.attendance_percentage)}%</span></div> <div class="text-3xl font-extrabold font-mono tracking-tight text-emerald-600 dark:text-emerald-400 tabular-nums">${escape_html(guidance().kpis.candidates_tested)}</div> <p class="text-[10px] text-muted-foreground font-mono">Attendance verified</p>`);
              },
              $$slots: { default: true }
            });
            $$renderer4.push(`<!----> `);
            BentoCard($$renderer4, {
              class: "p-5 space-y-2",
              children: ($$renderer5) => {
                $$renderer5.push(`<div class="flex items-center justify-between"><span class="text-[10px] font-mono font-bold uppercase tracking-wider text-muted-foreground">Pending Scoring</span> <span class="text-[10px] font-mono font-bold text-amber-500">Active</span></div> <div class="text-3xl font-extrabold font-mono tracking-tight text-amber-500 tabular-nums">${escape_html(guidance().kpis.pending_scoring)}</div> <p class="text-[10px] text-muted-foreground font-mono">Sessions to finalize</p>`);
              },
              $$slots: { default: true }
            });
            $$renderer4.push(`<!----> `);
            BentoCard($$renderer4, {
              class: "p-5 space-y-2",
              children: ($$renderer5) => {
                $$renderer5.push(`<span class="text-[10px] font-mono font-bold uppercase tracking-wider text-muted-foreground">Scoring Turnaround</span> <div class="text-3xl font-extrabold font-mono tracking-tight text-primary tabular-nums">${escape_html(guidance().kpis.turnaround_days)} <span class="text-base font-normal text-muted-foreground">Days</span></div> <p class="text-[10px] text-muted-foreground font-mono">From conduct to release</p>`);
              },
              $$slots: { default: true }
            });
            $$renderer4.push(`<!----></div> <div class="grid grid-cols-1 md:grid-cols-12 gap-5">`);
            BentoCard($$renderer4, {
              class: "md:col-span-8 p-6 space-y-4",
              children: ($$renderer5) => {
                $$renderer5.push(`<div><h3 class="font-bold text-sm text-foreground">Exam Session Conduct Trends</h3> <p class="text-xs text-muted-foreground font-mono">Scheduled vs Completed weekly sessions</p></div> <p class="sr-only">Session conduct trends: ${escape_html(guidance().session_trends.labels.map((l, i) => `${l}: scheduled ${guidance().session_trends.scheduled[i] ?? 0}, completed ${guidance().session_trends.completed[i] ?? 0}`).join(", "))}</p> `);
                LineChart($$renderer5, {
                  labels: guidance().session_trends.labels,
                  datasets: [
                    {
                      label: "Scheduled",
                      data: guidance().session_trends.scheduled,
                      color: "#6366f1",
                      fill: false
                    },
                    {
                      label: "Completed",
                      data: guidance().session_trends.completed,
                      color: "#10b981",
                      fill: true
                    }
                  ],
                  height: 220
                });
                $$renderer5.push(`<!---->`);
              },
              $$slots: { default: true }
            });
            $$renderer4.push(`<!----> `);
            BentoCard($$renderer4, {
              class: "md:col-span-4 p-6 space-y-4 flex flex-col justify-between",
              children: ($$renderer5) => {
                $$renderer5.push(`<div><h3 class="font-bold text-sm text-foreground">Candidate Attendance</h3> <p class="text-xs text-muted-foreground font-mono">Show-up vs Absent ratio</p></div> <p class="sr-only">Attendance distribution: ${escape_html(guidance().attendance_distribution.map((d) => `${d.label}: ${d.value}`).join(", "))}</p> `);
                DoughnutChart($$renderer5, { data: guidance().attendance_distribution, height: 190 });
                $$renderer5.push(`<!---->`);
              },
              $$slots: { default: true }
            });
            $$renderer4.push(`<!----></div></section>`);
          } else {
            $$renderer4.push("<!--[-1-->");
          }
          $$renderer4.push(`<!--]--> `);
          if (programHead()) {
            $$renderer4.push("<!--[0-->");
            $$renderer4.push(`<section class="space-y-4" data-tour="operations-program-quota"><div class="flex items-center justify-between"><span class="text-xs font-mono font-bold uppercase tracking-wider text-muted-foreground">Program Quota &amp; Advising Pool</span> `);
            Link($$renderer4, {
              href: "/staff/programs",
              class: "text-xs text-primary font-bold hover:underline font-mono",
              children: ($$renderer5) => {
                $$renderer5.push(`<!---->Open Program Workspace`);
              },
              $$slots: { default: true }
            });
            $$renderer4.push(`<!----></div> `);
            BentoCard($$renderer4, {
              class: "p-6 space-y-4",
              children: ($$renderer5) => {
                $$renderer5.push(`<div class="flex items-center justify-between"><div><h3 class="font-bold text-base text-foreground font-sans">${escape_html(programHead().program_code)} Program Quota &amp; Ingress</h3> <p class="text-xs text-muted-foreground font-mono">AY 2026–2027 Admissions Allocation</p></div> <div class="text-right"><div class="text-2xl font-bold font-mono text-foreground">${escape_html(programHead().allocated)} / ${escape_html(programHead().quota)}</div> <div class="text-[11px] text-emerald-600 dark:text-emerald-400 font-bold font-mono">${escape_html(programHead().percentage)}% Filled</div></div></div> <div class="w-full bg-muted rounded-full h-2.5 overflow-hidden"><div class="bg-gradient-to-r from-blue-500 to-emerald-400 h-2.5 rounded-full"${attr_style(`width: ${stringify(programHead().percentage)}%`)}></div></div> <div class="grid grid-cols-1 sm:grid-cols-3 gap-4 pt-3 border-t border-border/60 text-xs font-mono"><div><span class="text-muted-foreground text-[10px] uppercase">Potential Candidates</span> <div class="text-lg font-bold text-foreground mt-0.5">${escape_html(programHead().potential_candidates)}</div></div> <div><span class="text-muted-foreground text-[10px] uppercase">Advising Pool</span> <div class="text-lg font-bold text-emerald-600 dark:text-emerald-400 mt-0.5">${escape_html(programHead().advising_pool)}</div></div> <div><span class="text-muted-foreground text-[10px] uppercase">Edge Follow-ups</span> <div class="text-lg font-bold text-amber-500 mt-0.5">${escape_html(programHead().pending_follow_ups)}</div></div></div>`);
              },
              $$slots: { default: true }
            });
            $$renderer4.push(`<!----></section>`);
          } else {
            $$renderer4.push("<!--[-1-->");
          }
          $$renderer4.push(`<!--]--> <section class="space-y-4" data-tour="operations-activity"><div class="flex items-center justify-between"><span class="text-xs font-mono font-bold uppercase tracking-wider text-muted-foreground">My Recent Activity</span> <span class="text-xs text-muted-foreground font-mono">Audit Log Stream</span></div> `);
          BentoCard($$renderer4, {
            class: "p-6 space-y-3",
            children: ($$renderer5) => {
              if (myActivity().length > 0) {
                $$renderer5.push("<!--[0-->");
                $$renderer5.push(`<div class="divide-y divide-border/60 text-xs"><!--[-->`);
                const each_array_1 = ensure_array_like(myActivity());
                for (let $$index_1 = 0, $$length = each_array_1.length; $$index_1 < $$length; $$index_1++) {
                  let entry = each_array_1[$$index_1];
                  $$renderer5.push(`<div class="py-2.5 flex items-start justify-between gap-4"><div class="flex items-start gap-2.5 min-w-0"><span class="h-2 w-2 rounded-full bg-primary mt-1.5 shrink-0"></span> <div class="min-w-0"><p class="font-medium text-foreground truncate">${escape_html(entry.summary)}</p> <p class="text-[10px] font-mono text-muted-foreground">${escape_html(entry.event)}</p></div></div> <span class="text-[10px] font-mono text-muted-foreground shrink-0">${escape_html(formatTime(entry.created_at))}</span></div>`);
                }
                $$renderer5.push(`<!--]--></div>`);
              } else {
                $$renderer5.push("<!--[-1-->");
                $$renderer5.push(`<p class="text-xs text-muted-foreground text-center py-4">No recent activity recorded.</p>`);
              }
              $$renderer5.push(`<!--]-->`);
            },
            $$slots: { default: true }
          });
          $$renderer4.push(`<!----></section> `);
          XiamNpcDialog($$renderer4, {
            tourKey: "staff-operations-dashboard",
            context: operationsContext(),
            get open() {
              return tourOpen;
            },
            set open($$value) {
              tourOpen = $$value;
              $$settled = false;
            }
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
  Home as default
};
