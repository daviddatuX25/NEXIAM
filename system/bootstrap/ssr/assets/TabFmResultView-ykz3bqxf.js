import { e as escape_html, a as ensure_array_like, b as attr, d as derived, c as attr_class } from "../app.js";
import { B as Badge } from "./badge-D9Vgs7xh.js";
import { C as Card, a as Card_content } from "./card-content-Brg2wRkV.js";
import { C as Card_description } from "./card-description-BGy_v5-d.js";
import "clsx";
import { C as Card_header, a as Card_title } from "./card-title-BNY2ZPje.js";
import { C as Chevron_down } from "./chevron-down-CkQQZIhu.js";
function AptitudeRadarHexagon($$renderer, $$props) {
  $$renderer.component(($$renderer2) => {
    let {
      applicantRadar = {},
      applicantRadarSources = {},
      selectedProgram = null
    } = $$props;
    const AXES = [
      {
        key: "general_ability",
        label: "General ability",
        short: "General",
        angleIdx: 0,
        anchor: "middle",
        dy: -12,
        dx: 0
      },
      {
        key: "verbal",
        label: "Verbal",
        short: "Verbal",
        angleIdx: 1,
        anchor: "start",
        dy: -4,
        dx: 10
      },
      {
        key: "numerical",
        label: "Numerical",
        short: "Numerical",
        angleIdx: 2,
        anchor: "start",
        dy: 14,
        dx: 10
      },
      {
        key: "spatial",
        label: "Spatial",
        short: "Spatial",
        angleIdx: 3,
        anchor: "middle",
        dy: 20,
        dx: 0
      },
      {
        key: "perceptual",
        label: "Perceptual",
        short: "Perceptual",
        angleIdx: 4,
        anchor: "end",
        dy: 14,
        dx: -10
      },
      {
        key: "manual_dexterity",
        label: "Manual dexterity",
        short: "Manual",
        angleIdx: 5,
        anchor: "end",
        dy: -4,
        dx: -10
      }
    ];
    const CX = 160;
    const CY = 145;
    const RADIUS = 95;
    const LEVELS = [25, 50, 75, 100];
    function getAngle(index) {
      return index * (Math.PI / 3) - Math.PI / 2;
    }
    function getWebPoints(level) {
      const radius = level / 100 * RADIUS;
      return AXES.map((_, index) => {
        const angle = getAngle(index);
        return `${(CX + radius * Math.cos(angle)).toFixed(1)},${(CY + radius * Math.sin(angle)).toFixed(1)}`;
      }).join(" ");
    }
    const spokes = derived(() => AXES.map((axis, index) => {
      const angle = getAngle(index);
      return {
        ...axis,
        x2: Number((CX + RADIUS * Math.cos(angle)).toFixed(1)),
        y2: Number((CY + RADIUS * Math.sin(angle)).toFixed(1)),
        labelX: Number((CX + (RADIUS + 12) * Math.cos(angle) + axis.dx).toFixed(1)),
        labelY: Number((CY + (RADIUS + 12) * Math.sin(angle) + axis.dy).toFixed(1))
      };
    }));
    function profileData(profile) {
      const vertices = AXES.flatMap((axis, index) => {
        const rawValue = profile?.[axis.key];
        if (rawValue === null || rawValue === void 0 || !Number.isFinite(Number(rawValue))) return [];
        const value = Math.max(0, Math.min(100, Number(rawValue)));
        const angle = getAngle(index);
        return [
          {
            key: axis.key,
            value,
            x: Number((CX + value / 100 * RADIUS * Math.cos(angle)).toFixed(1)),
            y: Number((CY + value / 100 * RADIUS * Math.sin(angle)).toFixed(1))
          }
        ];
      });
      return {
        complete: vertices.length === AXES.length,
        pointsString: vertices.length === AXES.length ? vertices.map((vertex) => `${vertex.x},${vertex.y}`).join(" ") : "",
        vertices
      };
    }
    const applicantData = derived(() => profileData(applicantRadar));
    const benchmarkData = derived(() => profileData(selectedProgram?.benchmark_radar));
    const axisRows = derived(() => AXES.map((axis) => {
      const applicantValue = applicantRadar?.[axis.key];
      const benchmarkValue = selectedProgram?.benchmark_radar?.[axis.key];
      const hasApplicant = applicantValue !== null && applicantValue !== void 0 && Number.isFinite(Number(applicantValue));
      const hasBenchmark = benchmarkValue !== null && benchmarkValue !== void 0 && Number.isFinite(Number(benchmarkValue));
      const score = hasApplicant ? Number(applicantValue) : null;
      const cutoff = hasBenchmark ? Number(benchmarkValue) : null;
      const delta = hasApplicant && hasBenchmark ? score - cutoff : null;
      const source = applicantRadarSources?.[axis.key];
      return {
        ...axis,
        label: typeof source?.label === "string" && source.label.trim() ? source.label : axis.label,
        sourceKey: source?.source_key ?? null,
        score,
        cutoff,
        delta
      };
    }));
    $$renderer2.push(`<section class="rounded-lg border border-border/50 bg-background/50 p-3" aria-label="Aptitude comparison"><div class="flex flex-wrap items-start justify-between gap-2 border-b border-border/40 pb-2"><div><h3 class="text-sm font-semibold text-foreground">Aptitude profile</h3> <p class="text-xs text-muted-foreground">Values from the result’s six-axis profile; missing axes are not filled.</p></div> `);
    if (selectedProgram) {
      $$renderer2.push("<!--[0-->");
      Badge($$renderer2, {
        variant: "outline",
        class: "shrink-0",
        children: ($$renderer3) => {
          $$renderer3.push(`<!---->${escape_html(selectedProgram.program_code)} · cutoff profile`);
        },
        $$slots: { default: true }
      });
    } else {
      $$renderer2.push("<!--[-1-->");
    }
    $$renderer2.push(`<!--]--></div> <div><svg viewBox="0 0 320 290" class="h-56 w-full max-w-[320px] overflow-visible" role="img" aria-label="Six-axis aptitude comparison. Missing axes are not plotted."><!--[-->`);
    const each_array = ensure_array_like(LEVELS);
    for (let $$index = 0, $$length = each_array.length; $$index < $$length; $$index++) {
      let level = each_array[$$index];
      $$renderer2.push(`<polygon${attr("points", getWebPoints(level))} fill="none" class="stroke-border/70"${attr("stroke-width", level === 100 ? "1.5" : "1")}${attr("stroke-dasharray", level === 100 ? "none" : "3 3")}></polygon><text${attr("x", CX)}${attr("y", CY - level / 100 * RADIUS + 8)} text-anchor="middle" class="fill-muted-foreground/70 text-[9px] font-mono">${escape_html(level)}</text>`);
    }
    $$renderer2.push(`<!--]--><!--[-->`);
    const each_array_1 = ensure_array_like(spokes());
    for (let $$index_1 = 0, $$length = each_array_1.length; $$index_1 < $$length; $$index_1++) {
      let spoke = each_array_1[$$index_1];
      $$renderer2.push(`<line${attr("x1", CX)}${attr("y1", CY)}${attr("x2", spoke.x2)}${attr("y2", spoke.y2)} class="stroke-border/80" stroke-width="1"></line><text${attr("x", spoke.labelX)}${attr("y", spoke.labelY)}${attr("text-anchor", spoke.anchor)} dominant-baseline="central" class="fill-foreground text-[9px] font-medium">${escape_html(spoke.short)}</text>`);
    }
    $$renderer2.push(`<!--]-->`);
    if (benchmarkData().complete) {
      $$renderer2.push("<!--[0-->");
      $$renderer2.push(`<polygon${attr("points", benchmarkData().pointsString)} fill="rgba(16, 185, 129, 0.12)" stroke="#059669" stroke-width="1.8" stroke-dasharray="4 3"></polygon>`);
    } else {
      $$renderer2.push("<!--[-1-->");
    }
    $$renderer2.push(`<!--]--><!--[-->`);
    const each_array_2 = ensure_array_like(benchmarkData().vertices);
    for (let $$index_2 = 0, $$length = each_array_2.length; $$index_2 < $$length; $$index_2++) {
      let vertex = each_array_2[$$index_2];
      $$renderer2.push(`<circle${attr("cx", vertex.x)}${attr("cy", vertex.y)} r="3" fill="#059669"><title>${escape_html(vertex.key)}: ${escape_html(vertex.value)} benchmark</title></circle>`);
    }
    $$renderer2.push(`<!--]-->`);
    if (applicantData().complete) {
      $$renderer2.push("<!--[0-->");
      $$renderer2.push(`<polygon${attr("points", applicantData().pointsString)} fill="rgba(99, 102, 241, 0.16)" stroke="#4f46e5" stroke-width="2"></polygon>`);
    } else {
      $$renderer2.push("<!--[-1-->");
    }
    $$renderer2.push(`<!--]--><!--[-->`);
    const each_array_3 = ensure_array_like(applicantData().vertices);
    for (let $$index_3 = 0, $$length = each_array_3.length; $$index_3 < $$length; $$index_3++) {
      let vertex = each_array_3[$$index_3];
      $$renderer2.push(`<circle${attr("cx", vertex.x)}${attr("cy", vertex.y)} r="3.5" fill="#4f46e5"><title>${escape_html(vertex.key)}: ${escape_html(vertex.value)} applicant</title></circle>`);
    }
    $$renderer2.push(`<!--]--></svg></div> `);
    if (applicantData().vertices.length < AXES.length || selectedProgram && benchmarkData().vertices.length < AXES.length) {
      $$renderer2.push("<!--[0-->");
      $$renderer2.push(`<p class="border-t border-border/30 pt-2 text-xs text-muted-foreground">Partial profile: ${escape_html(applicantData().vertices.length)} of 6 applicant axes and ${escape_html(selectedProgram ? `${benchmarkData().vertices.length} of 6 benchmark axes` : "no programme cutoff selected")} have values. No filled shape is drawn for an incomplete profile.</p>`);
    } else {
      $$renderer2.push("<!--[-1-->");
    }
    $$renderer2.push(`<!--]--> <div class="flex flex-wrap justify-center gap-x-4 gap-y-1 border-t border-border/30 pt-2 text-xs"><span class="inline-flex items-center gap-1.5"><span class="size-2 rounded-full bg-indigo-600"></span>Applicant values</span> `);
    if (selectedProgram) {
      $$renderer2.push("<!--[0-->");
      $$renderer2.push(`<span class="inline-flex items-center gap-1.5"><span class="size-2 rounded-full bg-emerald-600"></span>${escape_html(selectedProgram.program_code)} cutoff values</span>`);
    } else {
      $$renderer2.push("<!--[-1-->");
    }
    $$renderer2.push(`<!--]--></div> <div class="mt-3 space-y-1 border-t border-border/30 pt-2"><div class="grid grid-cols-[minmax(0,1fr)_auto_auto] gap-2 text-[11px] font-medium text-muted-foreground"><span>Current catalog area</span><span>Applicant / cutoff</span><span>Difference</span></div> <!--[-->`);
    const each_array_4 = ensure_array_like(axisRows());
    for (let $$index_4 = 0, $$length = each_array_4.length; $$index_4 < $$length; $$index_4++) {
      let row = each_array_4[$$index_4];
      $$renderer2.push(`<div class="grid grid-cols-[minmax(0,1fr)_auto_auto] items-center gap-2 rounded-md bg-muted/20 px-2 py-1.5 text-xs"><span class="min-w-0 truncate"${attr("title", row.sourceKey ? `${row.label} (${row.sourceKey})` : row.label)}>${escape_html(row.label)}`);
      if (row.sourceKey) {
        $$renderer2.push("<!--[0-->");
        $$renderer2.push(`<span class="ml-1 font-mono text-[10px] text-muted-foreground">${escape_html(row.sourceKey)}</span>`);
      } else {
        $$renderer2.push("<!--[-1-->");
      }
      $$renderer2.push(`<!--]--></span> <span class="font-mono tabular-nums">${escape_html(row.score ?? "—")}${escape_html(selectedProgram ? ` / ${row.cutoff ?? "—"}` : "")}</span> <span class="min-w-8 text-right font-mono tabular-nums text-muted-foreground">${escape_html(row.delta === null ? "—" : row.delta > 0 ? `+${row.delta}` : row.delta)}</span></div>`);
    }
    $$renderer2.push(`<!--]--></div></section>`);
  });
}
function TabFmResultView($$renderer, $$props) {
  $$renderer.component(($$renderer2) => {
    let { model, sourceKind = "active" } = $$props;
    let selectedProgramCode = null;
    let showAllPrograms = false;
    const recommendations = derived(() => Array.isArray(model?.recommendations) ? model.recommendations : []);
    const topPrograms = derived(() => recommendations().slice(0, 4));
    const remainingPrograms = derived(() => recommendations().slice(4));
    const isEvaluated = derived(() => model?.status === "evaluated" && model?.evaluated !== false);
    const selectedProgram = derived(() => {
      if (!recommendations().length) return null;
      return recommendations().find((recommendation) => recommendation.program_code === selectedProgramCode) ?? recommendations()[0];
    });
    const sourceVersion = derived(() => model?.source_version ?? model?.benchmark_version ?? null);
    const sourceVersionLabel = derived(() => sourceVersion() === null || sourceVersion() === void 0 ? null : sourceKind === "draft" || String(sourceVersion()).startsWith("v") ? String(sourceVersion()) : `v${sourceVersion()}`);
    const sourceLabel = derived(() => sourceKind === "draft" ? "Saved draft" : model?.benchmark_context_changed ? "Recorded benchmark" : "Active benchmark");
    function bandLabel(band) {
      if (band === "high_match") return "High match";
      if (band === "moderate_match") return "Moderate match";
      if (band === "below_threshold") return "Below threshold";
      return "Band unavailable";
    }
    function bandClass(band) {
      if (band === "high_match") return "border-emerald-600/40 bg-emerald-600/10 text-emerald-700 dark:text-emerald-300";
      if (band === "moderate_match") return "border-amber-500/40 bg-amber-500/10 text-amber-700 dark:text-amber-300";
      if (band === "below_threshold") return "border-rose-500/40 bg-rose-500/10 text-rose-700 dark:text-rose-300";
      return "border-border bg-muted text-muted-foreground";
    }
    function indexValue(recommendation) {
      if (recommendation?.compatibility_index === null || recommendation?.compatibility_index === void 0) return null;
      const value = Number(recommendation.compatibility_index);
      return Number.isFinite(value) ? value : null;
    }
    function backendLabel(backend) {
      if (backend === "tabpfn_pytorch") return "TabPFN transformer";
      if (backend === "bayesian_prior_kernel_v1" || backend === "in_process_fallback") return "Fallback evaluation";
      if (backend === "in_process_heuristics") return "In-process heuristics";
      return backend || "Backend not reported";
    }
    if (Card) {
      $$renderer2.push("<!--[-->");
      Card($$renderer2, {
        class: "overflow-hidden border-border/70 shadow-sm",
        children: ($$renderer3) => {
          if (Card_header) {
            $$renderer3.push("<!--[-->");
            Card_header($$renderer3, {
              class: "border-b bg-muted/20 pb-4",
              children: ($$renderer4) => {
                $$renderer4.push(`<div class="flex flex-wrap items-start justify-between gap-3"><div class="min-w-0 flex-1">`);
                if (Card_title) {
                  $$renderer4.push("<!--[-->");
                  Card_title($$renderer4, {
                    class: "text-base font-semibold",
                    children: ($$renderer5) => {
                      $$renderer5.push(`<!---->${escape_html(model?.label ?? "TabFM Course Match Index")}`);
                    },
                    $$slots: { default: true }
                  });
                  $$renderer4.push("<!--]-->");
                } else {
                  $$renderer4.push("<!--[!-->");
                  $$renderer4.push("<!--]-->");
                }
                $$renderer4.push(` `);
                if (Card_description) {
                  $$renderer4.push("<!--[-->");
                  Card_description($$renderer4, {
                    class: "mt-1 text-xs",
                    children: ($$renderer5) => {
                      $$renderer5.push(`<!---->${escape_html(model?.subtitle ?? "Advisory programme compatibility against the selected benchmark.")}`);
                    },
                    $$slots: { default: true }
                  });
                  $$renderer4.push("<!--]-->");
                } else {
                  $$renderer4.push("<!--[!-->");
                  $$renderer4.push("<!--]-->");
                }
                $$renderer4.push(`</div> <div class="flex flex-wrap items-center gap-2">`);
                Badge($$renderer4, {
                  variant: "outline",
                  children: ($$renderer5) => {
                    $$renderer5.push(`<!---->${escape_html(sourceLabel())}${escape_html(sourceVersionLabel() ? ` · ${sourceVersionLabel()}` : " · version unavailable")}`);
                  },
                  $$slots: { default: true }
                });
                $$renderer4.push(`<!----> `);
                if (isEvaluated()) {
                  $$renderer4.push("<!--[0-->");
                  Badge($$renderer4, {
                    variant: "secondary",
                    children: ($$renderer5) => {
                      $$renderer5.push(`<!---->Advisory result`);
                    },
                    $$slots: { default: true }
                  });
                } else {
                  $$renderer4.push("<!--[-1-->");
                }
                $$renderer4.push(`<!--]--></div></div>`);
              },
              $$slots: { default: true }
            });
            $$renderer3.push("<!--]-->");
          } else {
            $$renderer3.push("<!--[!-->");
            $$renderer3.push("<!--]-->");
          }
          $$renderer3.push(` `);
          if (Card_content) {
            $$renderer3.push("<!--[-->");
            Card_content($$renderer3, {
              class: "space-y-4 pt-4",
              children: ($$renderer4) => {
                if (!isEvaluated()) {
                  $$renderer4.push("<!--[0-->");
                  $$renderer4.push(`<div class="rounded-lg border border-border/60 bg-muted/20 p-4" role="status"><h3 class="text-sm font-semibold">TabFM result not available</h3> <p class="mt-1 text-sm text-muted-foreground">${escape_html(model?.message ?? "A TabFM result has not been generated for this applicant.")}</p></div>`);
                } else {
                  $$renderer4.push("<!--[-1-->");
                  if (model?.fallback_reason || model?.inference_backend === "bayesian_prior_kernel_v1" || model?.inference_backend === "in_process_fallback") {
                    $$renderer4.push("<!--[0-->");
                    $$renderer4.push(`<p class="rounded-md border border-amber-500/30 bg-amber-500/5 px-3 py-2 text-xs text-amber-800 dark:text-amber-200">A fallback method produced this advisory result${escape_html(model?.fallback_reason ? ". The recorded reason is in Technical details." : ".")}</p>`);
                  } else if (model?.inference_backend === "in_process_heuristics") {
                    $$renderer4.push("<!--[1-->");
                    $$renderer4.push(`<p class="text-xs text-muted-foreground">This result used in-process heuristics; it was not produced by the TabPFN transformer.</p>`);
                  } else if (model?.inference_backend === "tabpfn_pytorch") {
                    $$renderer4.push("<!--[2-->");
                    $$renderer4.push(`<p class="text-xs text-muted-foreground">The evaluation reports the TabPFN transformer backend. This result remains advisory.</p>`);
                  } else {
                    $$renderer4.push("<!--[-1-->");
                  }
                  $$renderer4.push(`<!--]--> <div class="flex flex-wrap items-center justify-between gap-2"><div><h3 class="text-sm font-semibold">Ranked programme matches</h3> `);
                  if (model?.benchmark_name) {
                    $$renderer4.push("<!--[0-->");
                    $$renderer4.push(`<p class="text-xs text-muted-foreground">Benchmark: ${escape_html(model.benchmark_name)}</p>`);
                  } else {
                    $$renderer4.push("<!--[-1-->");
                  }
                  $$renderer4.push(`<!--]--></div> <p class="text-xs text-muted-foreground">CMI is shown on its 0–100 index scale.</p></div> `);
                  if (recommendations().length === 0) {
                    $$renderer4.push("<!--[0-->");
                    $$renderer4.push(`<p class="rounded-lg border border-border/60 bg-muted/20 p-4 text-sm text-muted-foreground" role="status">The evaluation did not return ranked programmes.</p> `);
                    AptitudeRadarHexagon($$renderer4, {
                      applicantRadar: model?.applicant_radar,
                      applicantRadarSources: model?.applicant_radar_sources,
                      selectedProgram: null
                    });
                    $$renderer4.push(`<!---->`);
                  } else {
                    $$renderer4.push("<!--[-1-->");
                    $$renderer4.push(`<div class="grid grid-cols-1 items-start gap-5 lg:grid-cols-12"><div class="min-w-0 space-y-2 lg:col-span-7"><ol class="space-y-2" aria-label="Ranked programme matches"><!--[-->`);
                    const each_array = ensure_array_like(topPrograms());
                    for (let $$index_1 = 0, $$length = each_array.length; $$index_1 < $$length; $$index_1++) {
                      let recommendation = each_array[$$index_1];
                      const isSelected = selectedProgram()?.program_code === recommendation.program_code;
                      const index = indexValue(recommendation);
                      $$renderer4.push(`<li><button type="button"${attr_class(`w-full rounded-lg border p-3 text-left transition-colors ${isSelected ? "border-primary/50 bg-primary/5 ring-1 ring-primary/20" : "border-border/60 bg-background hover:bg-muted/30"}`)}${attr("aria-pressed", isSelected)}><div class="flex flex-wrap items-start justify-between gap-2"><div class="flex min-w-0 items-start gap-2"><span class="shrink-0 rounded border px-1.5 py-0.5 font-mono text-xs">#${escape_html(recommendation.rank)}</span> <span class="min-w-0"><span class="font-semibold">${escape_html(recommendation.program_code)}</span><span class="ml-2 text-sm text-muted-foreground">${escape_html(recommendation.program_name)}</span></span></div> <div class="flex flex-wrap items-center gap-1.5">`);
                      Badge($$renderer4, {
                        variant: "outline",
                        class: bandClass(recommendation.band),
                        children: ($$renderer5) => {
                          $$renderer5.push(`<!---->${escape_html(bandLabel(recommendation.band))}`);
                        },
                        $$slots: { default: true }
                      });
                      $$renderer4.push(`<!----> `);
                      if (index !== null) {
                        $$renderer4.push("<!--[0-->");
                        $$renderer4.push(`<span class="font-mono text-sm font-semibold tabular-nums">${escape_html(index)}<span class="text-xs font-normal text-muted-foreground">/ 100 CMI</span></span>`);
                      } else {
                        $$renderer4.push("<!--[-1-->");
                        $$renderer4.push(`<span class="text-xs text-muted-foreground">CMI unavailable</span>`);
                      }
                      $$renderer4.push(`<!--]--> `);
                      if (recommendation.quota?.configured) {
                        $$renderer4.push("<!--[0-->");
                        $$renderer4.push(`<span class="rounded border border-border/60 px-2 py-1 text-xs text-muted-foreground"${attr("title", recommendation.quota.capacity !== null && recommendation.quota.capacity !== void 0 ? `${recommendation.quota.committed}/${recommendation.quota.capacity} committed` : "Capacity not reported")}>${escape_html(recommendation.quota.status === "full" ? "Full" : typeof recommendation.quota.remaining === "number" ? `${recommendation.quota.remaining} left` : "Capacity unavailable")}</span>`);
                      } else {
                        $$renderer4.push("<!--[-1-->");
                      }
                      $$renderer4.push(`<!--]--></div></div> <p${attr_class(`mt-2 text-xs ${recommendation.passed_cutoff === true ? "text-emerald-700 dark:text-emerald-300" : recommendation.passed_cutoff === false ? "text-amber-700 dark:text-amber-300" : "text-muted-foreground"}`)}>${escape_html(recommendation.passed_cutoff === true ? "Met the recorded cutoff" : recommendation.passed_cutoff === false ? "Did not meet the recorded cutoff" : "Cutoff result unavailable")}</p> `);
                      if (recommendation.drivers?.length) {
                        $$renderer4.push("<!--[0-->");
                        $$renderer4.push(`<ul class="mt-2 flex flex-wrap gap-1.5" aria-label="Match factors"><!--[-->`);
                        const each_array_1 = ensure_array_like(recommendation.drivers);
                        for (let $$index = 0, $$length2 = each_array_1.length; $$index < $$length2; $$index++) {
                          let driver = each_array_1[$$index];
                          $$renderer4.push(`<li class="rounded bg-muted/70 px-2 py-1 text-xs text-muted-foreground">${escape_html(driver)}</li>`);
                        }
                        $$renderer4.push(`<!--]--></ul>`);
                      } else {
                        $$renderer4.push("<!--[-1-->");
                      }
                      $$renderer4.push(`<!--]--></button></li>`);
                    }
                    $$renderer4.push(`<!--]--></ol> `);
                    if (remainingPrograms().length > 0) {
                      $$renderer4.push("<!--[0-->");
                      $$renderer4.push(`<div><button type="button" class="flex min-h-10 w-full items-center justify-between rounded-lg border border-dashed border-border/70 px-3 py-2 text-sm text-muted-foreground hover:bg-muted/30 hover:text-foreground"${attr("aria-expanded", showAllPrograms)}><span>${escape_html("Show")} ${escape_html(remainingPrograms().length)} more ranked programmes</span> `);
                      Chevron_down($$renderer4, {
                        class: `size-4 transition-transform ${""}`
                      });
                      $$renderer4.push(`<!----></button> `);
                      {
                        $$renderer4.push("<!--[-1-->");
                      }
                      $$renderer4.push(`<!--]--></div>`);
                    } else {
                      $$renderer4.push("<!--[-1-->");
                    }
                    $$renderer4.push(`<!--]--></div> <div class="min-w-0 lg:col-span-5 lg:sticky lg:top-4">`);
                    AptitudeRadarHexagon($$renderer4, {
                      applicantRadar: model?.applicant_radar,
                      applicantRadarSources: model?.applicant_radar_sources,
                      selectedProgram: selectedProgram()
                    });
                    $$renderer4.push(`<!----></div></div>`);
                  }
                  $$renderer4.push(`<!--]--> <p class="border-t border-border/40 pt-3 text-sm text-muted-foreground">${escape_html(model?.message ?? "Advisory result only. Staff make all consultation and admission decisions.")}</p>`);
                }
                $$renderer4.push(`<!--]--> <details class="border-t border-border/40 pt-3 text-xs text-muted-foreground"><summary class="cursor-pointer font-medium text-foreground">Technical details</summary> <dl class="mt-3 grid gap-x-3 gap-y-1 sm:grid-cols-[max-content_minmax(0,1fr)]"><dt>Result status</dt><dd>${escape_html(model?.status ?? "not reported")}</dd> <dt>Source</dt><dd>${escape_html(sourceLabel())}${escape_html(sourceVersionLabel() ? ` ${sourceVersionLabel()}` : " version unavailable")}</dd> <dt>Inference backend</dt><dd>${escape_html(backendLabel(model?.inference_backend))}</dd> `);
                if (model?.engine_version) {
                  $$renderer4.push("<!--[0-->");
                  $$renderer4.push(`<dt>Engine version</dt><dd>${escape_html(model.engine_version)}</dd>`);
                } else {
                  $$renderer4.push("<!--[-1-->");
                }
                $$renderer4.push(`<!--]--> `);
                if (model?.evaluated_at) {
                  $$renderer4.push("<!--[0-->");
                  $$renderer4.push(`<dt>Evaluated</dt><dd>${escape_html(model.evaluated_at)}</dd>`);
                } else {
                  $$renderer4.push("<!--[-1-->");
                }
                $$renderer4.push(`<!--]--> `);
                if (model?.fallback_reason) {
                  $$renderer4.push("<!--[0-->");
                  $$renderer4.push(`<dt>Fallback reason</dt><dd>${escape_html(model.fallback_reason)}</dd>`);
                } else {
                  $$renderer4.push("<!--[-1-->");
                }
                $$renderer4.push(`<!--]--> `);
                if (model?.message) {
                  $$renderer4.push("<!--[0-->");
                  $$renderer4.push(`<dt>Provider message</dt><dd>${escape_html(model.message)}</dd>`);
                } else {
                  $$renderer4.push("<!--[-1-->");
                }
                $$renderer4.push(`<!--]--></dl></details>`);
              },
              $$slots: { default: true }
            });
            $$renderer3.push("<!--]-->");
          } else {
            $$renderer3.push("<!--[!-->");
            $$renderer3.push("<!--]-->");
          }
        },
        $$slots: { default: true }
      });
      $$renderer2.push("<!--]-->");
    } else {
      $$renderer2.push("<!--[!-->");
      $$renderer2.push("<!--]-->");
    }
  });
}
export {
  TabFmResultView as T
};
