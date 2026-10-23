import { h as head, e as escape_html, a as ensure_array_like, d as derived } from "../app.js";
import { S as StaffLayout } from "./StaffLayout-CR-MZopa.js";
import { P as PageHeaderBar } from "./PageHeaderBar-DJhT5vOR.js";
import { B as Badge } from "./badge-D9Vgs7xh.js";
import { C as Cpu } from "./cpu-B3JGJjJ9.js";
import { L as Link } from "./Link-B7ks7rBK.js";
import { A as Arrow_right } from "./arrow-right-CzynHiIo.js";
import { P as Plus } from "./plus-DjyPLC7A.js";
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
function Index($$renderer, $$props) {
  $$renderer.component(($$renderer2) => {
    let {
      cycle = null,
      providers = [],
      benchmark_snapshot = null,
      history = [],
      programs = [],
      aptitude_areas = []
    } = $$props;
    const usable = derived(() => benchmark_snapshot?.usable === true);
    const tabFmManifest = derived(() => providers.find((provider) => provider.provider_id === "tabfm_pairwise") ?? null);
    const readiness = derived(() => !cycle ? "Choose an academic year and semester" : usable() ? "Benchmarks in use" : benchmark_snapshot ? "Needs review" : "No benchmarks in use");
    const studioHref = derived(() => `/setup/admission-support/tabfm${cycle ? `?cycle_id=${cycle.id}` : ""}`);
    const supportContext = derived(() => ({
      entityKey: "admission-support-hub",
      safetyKey: `${cycle?.id ?? "no-cycle"}:${usable()}:${benchmark_snapshot?.version ?? ""}:${history.length}`,
      freshness: "current",
      hasActiveCycle: Boolean(cycle),
      cycleName: cycle?.name ?? null,
      hasActiveBenchmark: usable(),
      activeBenchmarkVersion: usable() ? String(benchmark_snapshot.version) : null,
      activeBenchmarkHash: usable() ? benchmark_snapshot.content_sha256 : null,
      programsCount: programs.length,
      aptitudeAreasCount: aptitude_areas.length,
      historySetsCount: history.length,
      tabFmStatus: usable() ? tabFmManifest()?.status ?? "active" : "review-required"
    }));
    const breadcrumbs = [
      { label: "Setup", href: "/setup" },
      { label: "Admission support" }
    ];
    function formatDate(value) {
      if (!value) return "—";
      const date = new Date(value);
      return Number.isNaN(date.getTime()) ? "—" : date.toLocaleDateString("en-US", { month: "short", day: "numeric", year: "numeric" });
    }
    head("12ickau", $$renderer2, ($$renderer3) => {
      $$renderer3.title(($$renderer4) => {
        $$renderer4.push(`<title>Admission support · Setup · NEXIAM</title>`);
      });
    });
    StaffLayout($$renderer2, {
      breadcrumbs,
      children: ($$renderer3) => {
        $$renderer3.push(`<div class="min-w-0 space-y-8 p-4 sm:p-6 lg:p-8">`);
        PageHeaderBar($$renderer3, {
          title: "Admission support",
          description: "Choose a model to manage program benchmarks.",
          tourKey: "admission-support",
          context: supportContext()
        });
        $$renderer3.push(`<!----> <div class="flex flex-wrap items-center justify-between gap-3" data-tour="benchmark-status-badge"><p class="text-sm"><span class="text-muted-foreground">Academic year and semester:</span> <span class="font-medium">${escape_html(cycle?.name ?? "Not selected")}</span></p> `);
        Badge($$renderer3, {
          variant: usable() ? "default" : "secondary",
          children: ($$renderer4) => {
            $$renderer4.push(`<!---->${escape_html(readiness())}`);
          },
          $$slots: { default: true }
        });
        $$renderer3.push(`<!----></div> <section aria-labelledby="models-heading" class="space-y-4"><h2 id="models-heading" class="text-lg font-semibold">Models</h2> <div class="grid gap-4 md:grid-cols-2"><article class="flex min-w-0 flex-col gap-6 rounded-lg border border-border bg-card p-5 sm:p-6" data-tour="tabfm-studio-card" aria-labelledby="tabfm-heading"><div class="flex items-start justify-between gap-3"><div class="flex items-center gap-3">`);
        Cpu($$renderer3, { class: "size-6 shrink-0 text-primary", "aria-hidden": "true" });
        $$renderer3.push(`<!----> <h3 id="tabfm-heading" class="text-xl font-semibold">TabFM</h3></div> `);
        Badge($$renderer3, {
          variant: usable() ? "default" : "secondary",
          children: ($$renderer4) => {
            $$renderer4.push(`<!---->${escape_html(usable() ? "In use" : benchmark_snapshot ? "Needs review" : "Needs setup")}`);
          },
          $$slots: { default: true }
        });
        $$renderer3.push(`<!----></div> <div class="space-y-2"><p class="max-w-prose text-sm leading-6">Compare applicant results with program benchmarks.</p> <p class="text-sm text-muted-foreground">Results support counseling. Staff make admission decisions.</p> `);
        if (benchmark_snapshot && !usable()) {
          $$renderer3.push("<!--[0-->");
          $$renderer3.push(`<p class="text-sm text-amber-800 dark:text-amber-300">Review the saved benchmarks before using them.</p>`);
        } else {
          $$renderer3.push("<!--[-1-->");
        }
        $$renderer3.push(`<!--]--></div> `);
        Link($$renderer3, {
          href: studioHref(),
          class: "mt-auto inline-flex min-h-11 items-center justify-center gap-2 rounded-lg bg-primary px-4 py-2 text-sm font-medium text-primary-foreground transition-colors hover:bg-primary/90 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-ring",
          children: ($$renderer4) => {
            $$renderer4.push(`<!---->Open TabFM `);
            Arrow_right($$renderer4, { class: "size-4", "aria-hidden": "true" });
            $$renderer4.push(`<!---->`);
          },
          $$slots: { default: true }
        });
        $$renderer3.push(`<!----></article> <article class="flex min-w-0 flex-col justify-center gap-3 rounded-lg border border-dashed border-border bg-muted/20 p-5 sm:p-6" aria-labelledby="new-model-heading">`);
        Plus($$renderer3, { class: "size-6 text-muted-foreground", "aria-hidden": "true" });
        $$renderer3.push(`<!----> <h3 id="new-model-heading" class="text-lg font-semibold">Add another model</h3> <p class="max-w-prose text-sm leading-6">Contact the developer to add a new admission model.</p> <p class="text-sm text-muted-foreground">No other model is installed.</p></article></div></section> <details class="rounded-lg border border-border bg-card"><summary class="min-h-11 cursor-pointer rounded-lg px-4 py-3 text-sm font-medium focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-ring">Previous versions</summary> <div class="space-y-4 border-t border-border p-4">`);
        if (history.length === 0) {
          $$renderer3.push("<!--[0-->");
          $$renderer3.push(`<p class="text-sm text-muted-foreground">No versions have been published. Open TabFM to prepare a draft.</p>`);
        } else {
          $$renderer3.push("<!--[-1-->");
          $$renderer3.push(`<ul class="divide-y divide-border"><!--[-->`);
          const each_array = ensure_array_like(history);
          for (let $$index = 0, $$length = each_array.length; $$index < $$length; $$index++) {
            let item = each_array[$$index];
            $$renderer3.push(`<li class="flex flex-wrap items-start justify-between gap-3 py-3 first:pt-0"><div class="min-w-0 space-y-1"><p class="break-words text-sm font-medium">Version ${escape_html(item.version)} · ${escape_html(item.name)}</p> <p class="text-xs text-muted-foreground">Published ${escape_html(formatDate(item.published_at ?? item.created_at))}</p></div> `);
            Badge($$renderer3, {
              variant: "secondary",
              children: ($$renderer4) => {
                $$renderer4.push(`<!---->${escape_html(item.is_active_for_cycle ? usable() && String(item.id) === String(benchmark_snapshot?.set_id) ? "In use" : "Needs review" : item.status === "published" ? "Published" : item.status)}`);
              },
              $$slots: { default: true }
            });
            $$renderer3.push(`<!----></li>`);
          }
          $$renderer3.push(`<!--]--></ul>`);
        }
        $$renderer3.push(`<!--]--> `);
        Link($$renderer3, {
          href: studioHref(),
          class: "inline-flex min-h-11 items-center gap-2 rounded-lg px-2 text-sm font-medium text-primary underline underline-offset-4 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-ring",
          children: ($$renderer4) => {
            $$renderer4.push(`<!---->Manage versions `);
            Arrow_right($$renderer4, { class: "size-4", "aria-hidden": "true" });
            $$renderer4.push(`<!---->`);
          },
          $$slots: { default: true }
        });
        $$renderer3.push(`<!----></div></details></div>`);
      }
    });
  });
}
export {
  Index as default
};
