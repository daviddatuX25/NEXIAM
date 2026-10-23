import { h as head, a as ensure_array_like, e as escape_html, c as attr_class } from "../app.js";
import { S as StaffLayout } from "./StaffLayout-CR-MZopa.js";
import { B as BentoCard } from "./BentoCard-COpmyjxA.js";
import { M as MaterialDocumentPreview } from "./MaterialDocumentPreview-8R9H-zgk.js";
import { D as DoughnutChart } from "./DoughnutChart-DGhkPrlH.js";
import { D as Download } from "./download-n32NlL8c.js";
import { L as Link } from "./Link-B7ks7rBK.js";
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
import "./badge-D9Vgs7xh.js";
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
import "chart.js/auto";
function Index($$renderer, $$props) {
  $$renderer.component(($$renderer2) => {
    let { report } = $$props;
    const breadcrumbs = [{ label: "Reports & Analytics" }];
    let selectedCycleId = report.selected_cycle_id ? String(report.selected_cycle_id) : "";
    let activeTab = report.can_registrar ? "registrar" : "guidance";
    function onCycleChange(e) {
      const val = e.target.value;
      selectedCycleId = val;
      router.get("/reports", { cycle_id: val }, { preserveState: true, preserveScroll: true });
    }
    head("fcirv9", $$renderer2, ($$renderer3) => {
      $$renderer3.title(($$renderer4) => {
        $$renderer4.push(`<title>Reports &amp; Official Exports • NEXIAM</title>`);
      });
    });
    StaffLayout($$renderer2, {
      breadcrumbs,
      children: ($$renderer3) => {
        $$renderer3.push(`<div class="min-w-0 space-y-8 p-4 sm:p-6 lg:p-8"><div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 pb-4 border-b border-border/60"><div><h1 class="text-2xl sm:text-3xl font-bold tracking-tight text-foreground">Reports &amp; Analytics Hub</h1> <p class="text-xs text-muted-foreground mt-1">Official institutional census, demographic breakdowns, and certified export files.</p></div> <div class="flex items-center gap-3"><label class="text-xs font-mono text-muted-foreground font-bold">Cycle:</label> `);
        $$renderer3.select(
          {
            value: selectedCycleId,
            onchange: onCycleChange,
            class: "text-xs rounded-xl border border-border bg-card px-3 py-1.5 font-mono text-foreground focus:ring-2 focus:ring-primary/20"
          },
          ($$renderer4) => {
            $$renderer4.push(`<!--[-->`);
            const each_array = ensure_array_like(report.cycles);
            for (let $$index = 0, $$length = each_array.length; $$index < $$length; $$index++) {
              let cycle = each_array[$$index];
              $$renderer4.option({ value: String(cycle.id) }, ($$renderer5) => {
                $$renderer5.push(`${escape_html(cycle.name)} ${escape_html(cycle.is_active ? "(Active)" : "")}`);
              });
            }
            $$renderer4.push(`<!--]-->`);
          }
        );
        $$renderer3.push(`</div></div> <section class="space-y-3"><div class="flex items-center justify-between"><span class="text-xs font-mono font-bold uppercase tracking-wider text-muted-foreground">Demographic &amp; Intake Distributions</span> <span class="text-[11px] font-mono text-muted-foreground">Fresh as of: ${escape_html(new Date(report.as_of).toLocaleTimeString())}</span></div> <div class="grid grid-cols-1 md:grid-cols-3 gap-6">`);
        BentoCard($$renderer3, {
          class: "p-6 space-y-3",
          children: ($$renderer4) => {
            $$renderer4.push(`<div class="flex items-center justify-between"><h3 class="font-bold text-sm text-foreground">Pipeline Breakdown</h3> <span class="text-xs font-mono text-primary font-bold">11 Stages</span></div> `);
            DoughnutChart($$renderer4, { data: report.pipeline, height: 190 });
            $$renderer4.push(`<!---->`);
          },
          $$slots: { default: true }
        });
        $$renderer3.push(`<!----> `);
        BentoCard($$renderer3, {
          class: "p-6 space-y-3",
          children: ($$renderer4) => {
            $$renderer4.push(`<div class="flex items-center justify-between"><h3 class="font-bold text-sm text-foreground">Demographics by Sex</h3> <span class="text-xs font-mono text-muted-foreground">Applicants</span></div> `);
            DoughnutChart($$renderer4, { data: report.demographics.sex, height: 190 });
            $$renderer4.push(`<!---->`);
          },
          $$slots: { default: true }
        });
        $$renderer3.push(`<!----> `);
        BentoCard($$renderer3, {
          class: "p-6 space-y-3",
          children: ($$renderer4) => {
            $$renderer4.push(`<div class="flex items-center justify-between"><h3 class="font-bold text-sm text-foreground">Age Distribution</h3> <span class="text-xs font-mono text-muted-foreground">Bands</span></div> `);
            DoughnutChart($$renderer4, { data: report.demographics.age, height: 190 });
            $$renderer4.push(`<!---->`);
          },
          $$slots: { default: true }
        });
        $$renderer3.push(`<!----></div></section> <section class="space-y-4"><div class="flex items-center gap-2 border-b border-border/60 pb-2">`);
        if (report.can_registrar) {
          $$renderer3.push("<!--[0-->");
          $$renderer3.push(`<button${attr_class(`px-3.5 py-1.5 rounded-xl font-bold text-xs transition-all ${activeTab === "registrar" ? "bg-primary text-primary-foreground shadow-sm" : "bg-muted text-muted-foreground hover:text-foreground"}`)}>Registrar Reports (${escape_html(report.registrar_reports.length)})</button>`);
        } else {
          $$renderer3.push("<!--[-1-->");
        }
        $$renderer3.push(`<!--]--> `);
        if (report.can_guidance) {
          $$renderer3.push("<!--[0-->");
          $$renderer3.push(`<button${attr_class(`px-3.5 py-1.5 rounded-xl font-bold text-xs transition-all ${activeTab === "guidance" ? "bg-primary text-primary-foreground shadow-sm" : "bg-muted text-muted-foreground hover:text-foreground"}`)}>Guidance Testing Reports (${escape_html(report.guidance_reports.length)})</button>`);
        } else {
          $$renderer3.push("<!--[-1-->");
        }
        $$renderer3.push(`<!--]--></div> <div class="grid grid-cols-1 md:grid-cols-3 gap-5">`);
        if (activeTab === "registrar") {
          $$renderer3.push("<!--[0-->");
          $$renderer3.push(`<!--[-->`);
          const each_array_1 = ensure_array_like(report.registrar_reports);
          for (let $$index_2 = 0, $$length = each_array_1.length; $$index_2 < $$length; $$index_2++) {
            let r = each_array_1[$$index_2];
            BentoCard($$renderer3, {
              class: "p-6 space-y-3 flex flex-col justify-between text-xs",
              children: ($$renderer4) => {
                $$renderer4.push(`<div class="flex items-start gap-4">`);
                if (r.surface_key) {
                  $$renderer4.push("<!--[0-->");
                  MaterialDocumentPreview($$renderer4, {
                    src: r.active_template?.thumbnail_url,
                    kind: "report",
                    mode: r.active_template?.mode?.toUpperCase() ?? "HTML",
                    title: r.active_template?.name ?? r.title,
                    builder_json: r.active_template?.builder_json,
                    orientation: r.active_template?.orientation ?? "portrait",
                    alt: `${r.title} template preview`
                  });
                } else {
                  $$renderer4.push("<!--[-1-->");
                }
                $$renderer4.push(`<!--]--> <div class="min-w-0 space-y-1.5"><span class="text-[10px] font-mono font-bold uppercase text-blue-500">Registrar Office</span> <div class="flex flex-wrap items-center gap-2"><h4 class="text-base font-bold text-foreground tracking-tight">${escape_html(r.title)}</h4> `);
                if (r.active_template) {
                  $$renderer4.push("<!--[0-->");
                  $$renderer4.push(`<span class="rounded-md bg-muted px-2 py-1 text-[10px] font-semibold text-muted-foreground">${escape_html(r.active_template.name)}</span>`);
                } else {
                  $$renderer4.push("<!--[-1-->");
                }
                $$renderer4.push(`<!--]--></div> <p class="text-muted-foreground leading-relaxed text-[11px]">${escape_html(r.description)}</p></div></div> <div class="flex flex-wrap items-center gap-2 border-t border-border/60 pt-3">`);
                if (r.surface_key) {
                  $$renderer4.push("<!--[0-->");
                  $$renderer4.push(`<!--[-->`);
                  const each_array_2 = ensure_array_like(["docx", "pdf", "csv"]);
                  for (let $$index_1 = 0, $$length2 = each_array_2.length; $$index_1 < $$length2; $$index_1++) {
                    let format = each_array_2[$$index_1];
                    $$renderer4.push(`<button class="rounded-xl bg-primary px-3 py-1.5 font-bold text-primary-foreground shadow-sm hover:opacity-90">`);
                    Download($$renderer4, { class: "mr-1 inline size-3.5" });
                    $$renderer4.push(`<!----> ${escape_html(format.toUpperCase())}</button>`);
                  }
                  $$renderer4.push(`<!--]-->`);
                } else {
                  $$renderer4.push("<!--[-1-->");
                  $$renderer4.push(`<button class="flex-1 rounded-xl bg-primary py-1.5 font-bold text-primary-foreground shadow-sm hover:opacity-90">`);
                  Download($$renderer4, { class: "mr-1 inline size-3.5" });
                  $$renderer4.push(`<!----> CSV</button>`);
                }
                $$renderer4.push(`<!--]--> `);
                if (r.can_configure_template && r.surface_key) {
                  $$renderer4.push("<!--[0-->");
                  Link($$renderer4, {
                    href: `/setup/report-templates/${r.surface_key}`,
                    class: "ml-auto rounded-xl border border-border px-3 py-1.5 font-semibold text-foreground hover:bg-muted",
                    children: ($$renderer5) => {
                      $$renderer5.push(`<!---->Configure Template`);
                    },
                    $$slots: { default: true }
                  });
                } else {
                  $$renderer4.push("<!--[-1-->");
                }
                $$renderer4.push(`<!--]--></div>`);
              },
              $$slots: { default: true }
            });
          }
          $$renderer3.push(`<!--]-->`);
        } else if (activeTab === "guidance") {
          $$renderer3.push("<!--[1-->");
          $$renderer3.push(`<!--[-->`);
          const each_array_3 = ensure_array_like(report.guidance_reports);
          for (let $$index_4 = 0, $$length = each_array_3.length; $$index_4 < $$length; $$index_4++) {
            let r = each_array_3[$$index_4];
            BentoCard($$renderer3, {
              class: "p-6 space-y-3 flex flex-col justify-between text-xs",
              children: ($$renderer4) => {
                $$renderer4.push(`<div class="flex items-start gap-4">`);
                if (r.surface_key) {
                  $$renderer4.push("<!--[0-->");
                  MaterialDocumentPreview($$renderer4, {
                    src: r.active_template?.thumbnail_url,
                    kind: "report",
                    mode: r.active_template?.mode?.toUpperCase() ?? "HTML",
                    title: r.active_template?.name ?? r.title,
                    builder_json: r.active_template?.builder_json,
                    orientation: r.active_template?.orientation ?? "portrait",
                    alt: `${r.title} template preview`
                  });
                } else {
                  $$renderer4.push("<!--[-1-->");
                }
                $$renderer4.push(`<!--]--> <div class="min-w-0 space-y-1.5"><span class="text-[10px] font-mono font-bold uppercase text-purple-500">Guidance &amp; Testing</span> <div class="flex flex-wrap items-center gap-2"><h4 class="text-base font-bold text-foreground tracking-tight">${escape_html(r.title)}</h4> `);
                if (r.active_template) {
                  $$renderer4.push("<!--[0-->");
                  $$renderer4.push(`<span class="rounded-md bg-muted px-2 py-1 text-[10px] font-semibold text-muted-foreground">${escape_html(r.active_template.name)}</span>`);
                } else {
                  $$renderer4.push("<!--[-1-->");
                }
                $$renderer4.push(`<!--]--></div> <p class="text-muted-foreground leading-relaxed text-[11px]">${escape_html(r.description)}</p></div></div> <div class="flex flex-wrap items-center gap-2 border-t border-border/60 pt-3">`);
                if (r.surface_key) {
                  $$renderer4.push("<!--[0-->");
                  $$renderer4.push(`<!--[-->`);
                  const each_array_4 = ensure_array_like(["docx", "pdf", "csv"]);
                  for (let $$index_3 = 0, $$length2 = each_array_4.length; $$index_3 < $$length2; $$index_3++) {
                    let format = each_array_4[$$index_3];
                    $$renderer4.push(`<button class="rounded-xl bg-primary px-3 py-1.5 font-bold text-primary-foreground shadow-sm hover:opacity-90">`);
                    Download($$renderer4, { class: "mr-1 inline size-3.5" });
                    $$renderer4.push(`<!----> ${escape_html(format.toUpperCase())}</button>`);
                  }
                  $$renderer4.push(`<!--]-->`);
                } else {
                  $$renderer4.push("<!--[-1-->");
                  $$renderer4.push(`<button class="flex-1 rounded-xl bg-primary py-1.5 font-bold text-primary-foreground shadow-sm hover:opacity-90">`);
                  Download($$renderer4, { class: "mr-1 inline size-3.5" });
                  $$renderer4.push(`<!----> CSV</button>`);
                }
                $$renderer4.push(`<!--]--> `);
                if (r.can_configure_template && r.surface_key) {
                  $$renderer4.push("<!--[0-->");
                  Link($$renderer4, {
                    href: `/setup/report-templates/${r.surface_key}`,
                    class: "ml-auto rounded-xl border border-border px-3 py-1.5 font-semibold text-foreground hover:bg-muted",
                    children: ($$renderer5) => {
                      $$renderer5.push(`<!---->Configure Template`);
                    },
                    $$slots: { default: true }
                  });
                } else {
                  $$renderer4.push("<!--[-1-->");
                }
                $$renderer4.push(`<!--]--></div>`);
              },
              $$slots: { default: true }
            });
          }
          $$renderer3.push(`<!--]-->`);
        } else {
          $$renderer3.push("<!--[-1-->");
        }
        $$renderer3.push(`<!--]--></div></section></div>`);
      }
    });
  });
}
export {
  Index as default
};
