import { h as head, c as attr_class, e as escape_html, b as attr, a as ensure_array_like, d as derived } from "../app.js";
import { B as Badge } from "./badge-D9Vgs7xh.js";
import { L as Link } from "./Link-B7ks7rBK.js";
import "axios";
import "@inertiajs/core";
import "clsx";
import "laravel-precognition";
import "tailwind-merge";
import "tailwind-variants";
function Glossary($$renderer, $$props) {
  $$renderer.component(($$renderer2) => {
    let {
      groups = [],
      sources = [],
      stats = {
        groups: 0,
        terms: 0,
        sources: 0,
        dispositions: {
          canonical: 0,
          reconcile: 0,
          "to-remove": 0,
          contextual: 0,
          "legacy-only": 0
        }
      }
    } = $$props;
    let query = "";
    let activeGroup = "all";
    let surfaceFilter = "all";
    let viewMode = "cards";
    const sourceMap = derived(() => Object.fromEntries(sources.map((source) => [source.id, source])));
    const filteredGroups = derived(() => {
      const needle = query.trim().toLowerCase();
      return groups.filter((group) => activeGroup === "all").map((group) => ({
        ...group,
        terms: group.terms.filter((term) => {
          const searchable = [
            term.term,
            term.definition,
            term.disposition,
            term.category ?? "",
            term.target_term ?? "",
            term.reconciliation_action ?? "",
            ...term.aliases ?? [],
            ...(term.surfaces ?? []).flatMap((s) => [s.surface, s.wording, s.role])
          ].join(" ").toLowerCase();
          return !needle || searchable.includes(needle);
        })
      })).filter((group) => group.terms.length > 0);
    });
    const visibleTerms = derived(() => filteredGroups().flatMap((group) => group.terms));
    const visibleTermCount = derived(() => visibleTerms().length);
    function dispositionLabel(disp) {
      return {
        canonical: "Preferred Standard",
        reconcile: "Needs Reconciliation",
        "to-remove": "Slated for Removal",
        contextual: "Context-Specific",
        "legacy-only": "Legacy Only"
      }[disp] ?? disp;
    }
    function dispositionBadgeClass(disp) {
      return {
        canonical: "border-emerald-500/40 bg-emerald-500/10 text-emerald-300",
        reconcile: "border-amber-500/40 bg-amber-500/10 text-amber-300",
        "to-remove": "border-rose-500/40 bg-rose-500/10 text-rose-300",
        contextual: "border-sky-500/40 bg-sky-500/10 text-sky-300",
        "legacy-only": "border-slate-600 bg-slate-800 text-slate-400"
      }[disp] ?? "border-slate-700 bg-slate-800 text-slate-300";
    }
    function roleBadgeClass(role) {
      return {
        target: "border-emerald-500/30 bg-emerald-500/10 text-emerald-300",
        schema: "border-blue-500/30 bg-blue-500/10 text-blue-300",
        observed_drift: "border-amber-500/30 bg-amber-500/10 text-amber-300",
        evidence: "border-purple-500/30 bg-purple-500/10 text-purple-300",
        legacy: "border-rose-500/30 bg-rose-500/10 text-rose-400"
      }[role] ?? "border-slate-700 bg-slate-800 text-slate-400";
    }
    function sourceFor(id) {
      return sourceMap()[id] ?? { label: id, kind: "Unclassified source", ref: id };
    }
    head("zhdyhz", $$renderer2, ($$renderer3) => {
      $$renderer3.title(($$renderer4) => {
        $$renderer4.push(`<title>Vocabulary &amp; Reconciliation Registry · NEXIAM Capstone</title>`);
      });
      $$renderer3.push(`<meta name="description" content="Complete source-backed vocabulary registry, reconciliation matrix, and UI terminology triage for NEXIAM."/>`);
    });
    $$renderer2.push(`<div class="min-h-screen bg-slate-950 text-slate-100"><main class="mx-auto max-w-7xl px-5 py-10 sm:px-8 lg:px-10"><header class="flex flex-col gap-6 border-b border-slate-800 pb-8 lg:flex-row lg:items-end lg:justify-between"><div><div class="flex flex-wrap items-center gap-2 text-xs font-semibold tracking-[0.18em] uppercase">`);
    Link($$renderer2, {
      href: "/capstone/status",
      class: "text-slate-400 transition hover:text-white",
      children: ($$renderer3) => {
        $$renderer3.push(`<!---->Feature Roadmap`);
      },
      $$slots: { default: true }
    });
    $$renderer2.push(`<!----> <span class="text-slate-600" aria-hidden="true">/</span> <span class="text-blue-300">Master Vocabulary Registry</span></div> <h1 class="mt-3 text-3xl font-semibold tracking-tight text-white sm:text-5xl">Vocabulary &amp; Term Crosswalk</h1> <p class="mt-4 max-w-4xl text-base leading-7 text-slate-300">The single source of truth for all terminology across NEXIAM. Classifies every term as <span class="text-emerald-300 font-medium">Preferred Standard</span>, <span class="text-amber-300 font-medium">Needs Reconciliation</span>, or <span class="text-rose-300 font-medium">Slated for Removal</span> so the team can systematically audit and harmonize
          UI copy, capstone documentation, DFDs, and schema.</p></div> <nav class="flex flex-wrap gap-2" aria-label="Capstone workspace">`);
    Link($$renderer2, {
      href: "/capstone/demo-guide",
      class: "rounded-lg border border-slate-700 bg-slate-900 px-3.5 py-2 text-xs font-semibold text-slate-300 transition hover:border-slate-500 hover:text-white",
      children: ($$renderer3) => {
        $$renderer3.push(`<!---->Demo guide`);
      },
      $$slots: { default: true }
    });
    $$renderer2.push(`<!----> `);
    Link($$renderer2, {
      href: "/capstone/glossary",
      class: "rounded-lg border border-blue-400/40 bg-blue-400/10 px-3.5 py-2 text-xs font-semibold text-blue-200",
      "aria-current": "page",
      children: ($$renderer3) => {
        $$renderer3.push(`<!---->Glossary &amp; Registry`);
      },
      $$slots: { default: true }
    });
    $$renderer2.push(`<!----></nav></header> <section class="mt-8 grid gap-3 grid-cols-2 sm:grid-cols-3 lg:grid-cols-6" aria-label="Vocabulary triage overview"><button type="button"${attr_class(`text-left rounded-2xl border p-4 transition ${"border-blue-400 bg-blue-500/10 ring-2 ring-blue-500/20"}`)}><p class="text-[10px] font-semibold tracking-[0.16em] text-slate-400 uppercase">Total Terms</p> <p class="mt-1 text-2xl font-bold text-white">${escape_html(stats.terms)}</p> <p class="mt-0.5 text-xs text-slate-400">All registered concepts</p></button> <button type="button"${attr_class(`text-left rounded-2xl border p-4 transition ${"border-slate-800 bg-slate-900/80 hover:border-slate-700"}`)}><p class="text-[10px] font-semibold tracking-[0.16em] text-emerald-400 uppercase">Preferred Standard</p> <p class="mt-1 text-2xl font-bold text-emerald-300">${escape_html(stats.dispositions?.canonical ?? 0)}</p> <p class="mt-0.5 text-xs text-slate-400">Target terminology to keep</p></button> <button type="button"${attr_class(`text-left rounded-2xl border p-4 transition ${"border-slate-800 bg-slate-900/80 hover:border-slate-700"}`)}><p class="text-[10px] font-semibold tracking-[0.16em] text-amber-400 uppercase">Reconcile Across UI/Docs</p> <p class="mt-1 text-2xl font-bold text-amber-300">${escape_html(stats.dispositions?.reconcile ?? 0)}</p> <p class="mt-0.5 text-xs text-slate-400">Inconsistent usage to align</p></button> <button type="button"${attr_class(`text-left rounded-2xl border p-4 transition ${"border-slate-800 bg-slate-900/80 hover:border-slate-700"}`)}><p class="text-[10px] font-semibold tracking-[0.16em] text-rose-400 uppercase">Slated for Removal</p> <p class="mt-1 text-2xl font-bold text-rose-300">${escape_html(stats.dispositions?.["to-remove"] ?? 0)}</p> <p class="mt-0.5 text-xs text-slate-400">Jargon to drop from UI</p></button> <button type="button"${attr_class(`text-left rounded-2xl border p-4 transition ${"border-slate-800 bg-slate-900/80 hover:border-slate-700"}`)}><p class="text-[10px] font-semibold tracking-[0.16em] text-sky-400 uppercase">Context-Specific</p> <p class="mt-1 text-2xl font-bold text-sky-300">${escape_html(stats.dispositions?.contextual ?? 0)}</p> <p class="mt-0.5 text-xs text-slate-400">Valid in bounded phase only</p></button> <button type="button"${attr_class(`text-left rounded-2xl border p-4 transition ${"border-slate-800 bg-slate-900/80 hover:border-slate-700"}`)}><p class="text-[10px] font-semibold tracking-[0.16em] text-slate-400 uppercase">Legacy Only</p> <p class="mt-1 text-2xl font-bold text-slate-300">${escape_html(stats.dispositions?.["legacy-only"] ?? 0)}</p> <p class="mt-0.5 text-xs text-slate-400">Forbidden in greenfield</p></button></section> <section class="mt-8 rounded-2xl border border-slate-800 bg-slate-900/80 p-5 shadow-2xl shadow-slate-950/20 sm:p-6" aria-labelledby="filters-title"><div class="flex flex-col gap-4 lg:flex-row lg:items-end"><div class="min-w-0 flex-1"><label for="glossary-search" id="filters-title" class="text-xs font-semibold tracking-[0.18em] text-slate-400 uppercase">Search terms, surfaces &amp; actions</label> <input id="glossary-search" type="search"${attr("value", query)} placeholder="Search: intake, dossier, canonical, NOA, examinee, quota, slot, OMR..." class="mt-2 min-h-11 w-full rounded-xl border border-slate-700 bg-slate-950 px-4 text-sm text-white outline-none placeholder:text-slate-600 focus:border-blue-400 focus:ring-2 focus:ring-blue-400/20"/></div> <div class="w-full lg:w-56"><label for="surface-filter" class="text-xs font-semibold tracking-[0.18em] text-slate-400 uppercase">Surface Filter</label> `);
    $$renderer2.select(
      {
        id: "surface-filter",
        value: surfaceFilter,
        class: "mt-2 min-h-11 w-full rounded-xl border border-slate-700 bg-slate-950 px-3 text-sm text-white outline-none focus:border-blue-400 focus:ring-2 focus:ring-blue-400/20"
      },
      ($$renderer3) => {
        $$renderer3.option({ value: "all" }, ($$renderer4) => {
          $$renderer4.push(`All Surfaces`);
        });
        $$renderer3.option({ value: "ui" }, ($$renderer4) => {
          $$renderer4.push(`System UI (Svelte / Views)`);
        });
        $$renderer3.option({ value: "docs" }, ($$renderer4) => {
          $$renderer4.push(`Documentation &amp; Contracts`);
        });
        $$renderer3.option({ value: "schema" }, ($$renderer4) => {
          $$renderer4.push(`Database Schema &amp; Migrations`);
        });
        $$renderer3.option({ value: "diagram" }, ($$renderer4) => {
          $$renderer4.push(`Diagrams (DFDs &amp; ERD)`);
        });
        $$renderer3.option({ value: "evidence" }, ($$renderer4) => {
          $$renderer4.push(`Institutional Ground Truth`);
        });
      }
    );
    $$renderer2.push(`</div> <div class="flex flex-wrap items-center gap-2"><div class="inline-flex rounded-xl border border-slate-700 bg-slate-950 p-1"><button type="button"${attr_class(`rounded-lg px-3 py-1.5 text-xs font-semibold transition ${"bg-blue-600 text-white shadow-sm"}`)}${attr("aria-pressed", viewMode === "cards")}>Card View</button> <button type="button"${attr_class(`rounded-lg px-3 py-1.5 text-xs font-semibold transition ${"text-slate-400 hover:text-white"}`)}${attr("aria-pressed", viewMode === "table")}>Triage Matrix</button></div> <button type="button" class="min-h-11 rounded-xl border border-slate-700 bg-slate-950 px-3.5 text-xs font-semibold text-slate-300 transition hover:border-slate-500 hover:text-white">${escape_html("Copy Matrix")}</button> <button type="button" class="min-h-11 rounded-xl border border-slate-700 px-3.5 text-xs font-semibold text-slate-300 transition hover:border-slate-500 hover:text-white">Reset</button></div></div> <div class="mt-5 flex gap-2 overflow-x-auto pb-1" aria-label="Vocabulary Domains"><button type="button"${attr_class(`shrink-0 rounded-lg px-3 py-2 text-xs font-semibold transition ${"bg-blue-600 text-white"}`)}${attr("aria-pressed", activeGroup === "all")}>All Domains (${escape_html(stats.terms)})</button> <!--[-->`);
    const each_array = ensure_array_like(groups);
    for (let $$index = 0, $$length = each_array.length; $$index < $$length; $$index++) {
      let group = each_array[$$index];
      $$renderer2.push(`<button type="button"${attr_class(`shrink-0 rounded-lg px-3 py-2 text-xs font-semibold transition ${activeGroup === group.key ? "bg-blue-600 text-white" : "bg-slate-950 text-slate-400 hover:bg-slate-800 hover:text-slate-200"}`)}${attr("aria-pressed", activeGroup === group.key)}>${escape_html(group.title)} (${escape_html(group.terms.length)})</button>`);
    }
    $$renderer2.push(`<!--]--></div> <p class="mt-4 text-sm text-slate-400" aria-live="polite">Showing <span class="font-semibold text-slate-200">${escape_html(visibleTermCount())}</span> of ${escape_html(stats.terms)} terms `);
    {
      $$renderer2.push("<!--[-1-->");
    }
    $$renderer2.push(`<!--]--> `);
    {
      $$renderer2.push("<!--[-1-->");
    }
    $$renderer2.push(`<!--]--> `);
    if (query.trim()) {
      $$renderer2.push("<!--[0-->");
      $$renderer2.push(`for “${escape_html(query.trim())}”`);
    } else {
      $$renderer2.push("<!--[-1-->");
    }
    $$renderer2.push(`<!--]-->.</p></section> `);
    if (filteredGroups().length === 0) {
      $$renderer2.push("<!--[0-->");
      $$renderer2.push(`<section class="mt-8 rounded-2xl border border-dashed border-slate-700 bg-slate-900/50 px-6 py-16 text-center" aria-live="polite"><h2 class="text-lg font-semibold text-white">No matching terms found</h2> <p class="mt-2 text-sm text-slate-400">Try broadening your search term or clearing the disposition/surface filters.</p> <button type="button" class="mt-4 rounded-xl border border-slate-700 bg-slate-800 px-4 py-2 text-xs font-semibold text-slate-200 hover:bg-slate-700">Clear all filters</button></section>`);
    } else {
      $$renderer2.push("<!--[-1-->");
      $$renderer2.push(`<div class="mt-8 space-y-10"><!--[-->`);
      const each_array_3 = ensure_array_like(filteredGroups());
      for (let $$index_7 = 0, $$length = each_array_3.length; $$index_7 < $$length; $$index_7++) {
        let group = each_array_3[$$index_7];
        $$renderer2.push(`<section${attr("aria-labelledby", `${group.key}-heading`)}><div class="mb-4 flex flex-col gap-2 border-b border-slate-800 pb-4 sm:flex-row sm:items-end sm:justify-between"><div><h2${attr("id", `${group.key}-heading`)} class="text-2xl font-semibold tracking-tight text-white">${escape_html(group.title)}</h2> <p class="mt-1 max-w-3xl text-sm leading-6 text-slate-400">${escape_html(group.summary)}</p></div> `);
        Badge($$renderer2, {
          class: "w-fit border-slate-700 bg-slate-900 text-slate-300",
          children: ($$renderer3) => {
            $$renderer3.push(`<!---->${escape_html(group.terms.length)} terms`);
          },
          $$slots: { default: true }
        });
        $$renderer2.push(`<!----></div> <div class="grid gap-5 xl:grid-cols-2"><!--[-->`);
        const each_array_4 = ensure_array_like(group.terms);
        for (let $$index_6 = 0, $$length2 = each_array_4.length; $$index_6 < $$length2; $$index_6++) {
          let term = each_array_4[$$index_6];
          $$renderer2.push(`<article class="min-w-0 rounded-2xl border border-slate-800 bg-slate-900/80 p-5 shadow-xl shadow-slate-950/10 sm:p-6 flex flex-col justify-between"><div><div class="flex flex-wrap items-start justify-between gap-3"><div class="min-w-0"><div class="flex flex-wrap items-center gap-2"><h3 class="text-lg font-semibold text-white">${escape_html(term.term)}</h3> `);
          if (term.category) {
            $$renderer2.push("<!--[0-->");
            $$renderer2.push(`<span class="rounded bg-slate-800 px-2 py-0.5 text-[10px] font-mono text-slate-400">${escape_html(term.category)}</span>`);
          } else {
            $$renderer2.push("<!--[-1-->");
          }
          $$renderer2.push(`<!--]--></div> <div class="mt-2.5 flex flex-wrap items-center gap-2"><span${attr_class(`rounded-full border px-2.5 py-1 text-[11px] font-semibold ${dispositionBadgeClass(term.disposition)}`)}>${escape_html(dispositionLabel(term.disposition))}</span> `);
          if (term.target_term) {
            $$renderer2.push("<!--[0-->");
            $$renderer2.push(`<span class="inline-flex items-center gap-1 rounded-full border border-emerald-500/30 bg-emerald-500/10 px-2.5 py-1 text-[11px] text-emerald-300"><span>Target:</span> <strong class="font-semibold">${escape_html(term.target_term)}</strong></span>`);
          } else {
            $$renderer2.push("<!--[-1-->");
          }
          $$renderer2.push(`<!--]--> <!--[-->`);
          const each_array_5 = ensure_array_like(term.aliases ?? []);
          for (let $$index_3 = 0, $$length3 = each_array_5.length; $$index_3 < $$length3; $$index_3++) {
            let alias = each_array_5[$$index_3];
            $$renderer2.push(`<span class="rounded-full border border-slate-700 bg-slate-950 px-2 py-0.5 text-[10px] text-slate-400">${escape_html(alias)}</span>`);
          }
          $$renderer2.push(`<!--]--></div></div> <span class="font-mono text-[10px] text-slate-600">${escape_html(term.key)}</span></div> <p class="mt-4 text-sm leading-6 text-slate-300">${escape_html(term.definition)}</p> `);
          if (term.reconciliation_action) {
            $$renderer2.push("<!--[0-->");
            $$renderer2.push(`<div class="mt-4 rounded-xl border border-amber-500/20 bg-amber-500/5 p-3.5 text-xs leading-5"><p class="font-semibold tracking-wide uppercase text-amber-300">Reconciliation Directive</p> <p class="mt-1 text-amber-200/90">${escape_html(term.reconciliation_action)}</p></div>`);
          } else {
            $$renderer2.push("<!--[-1-->");
          }
          $$renderer2.push(`<!--]--> `);
          if (term.surfaces?.length) {
            $$renderer2.push("<!--[0-->");
            $$renderer2.push(`<div class="mt-5 border-t border-slate-800/80 pt-4"><h4 class="text-xs font-semibold tracking-[0.16em] text-slate-400 uppercase">Observed Surfaces &amp; Current Wording (${escape_html(term.surfaces.length)})</h4> <div class="mt-3 space-y-2"><!--[-->`);
            const each_array_6 = ensure_array_like(term.surfaces);
            for (let $$index_4 = 0, $$length3 = each_array_6.length; $$index_4 < $$length3; $$index_4++) {
              let s = each_array_6[$$index_4];
              $$renderer2.push(`<div class="rounded-lg border border-slate-800/80 bg-slate-950/60 p-2.5 text-xs"><div class="flex flex-wrap items-center justify-between gap-2"><span class="font-medium text-slate-200">${escape_html(s.surface)}</span> <span${attr_class(`rounded px-1.5 py-0.5 text-[9px] font-mono uppercase ${roleBadgeClass(s.role)}`)}>${escape_html(s.role)}</span></div> <div class="mt-1 flex items-baseline gap-1.5"><span class="text-slate-500 text-[11px]">Wording:</span> <code class="text-blue-300 font-mono text-[11px]">${escape_html(s.wording)}</code></div></div>`);
            }
            $$renderer2.push(`<!--]--></div></div>`);
          } else {
            $$renderer2.push("<!--[-1-->");
          }
          $$renderer2.push(`<!--]--></div> `);
          if (term.sources?.length) {
            $$renderer2.push("<!--[0-->");
            $$renderer2.push(`<details class="mt-5 rounded-lg border border-slate-800 bg-slate-950/40 px-3 py-2"><summary class="cursor-pointer text-xs font-semibold text-slate-400 hover:text-slate-200">View source evidence references (${escape_html(term.sources.length)})</summary> <ul class="mt-3 space-y-2 border-t border-slate-800 pt-3"><!--[-->`);
            const each_array_7 = ensure_array_like(term.sources);
            for (let $$index_5 = 0, $$length3 = each_array_7.length; $$index_5 < $$length3; $$index_5++) {
              let sourceId = each_array_7[$$index_5];
              const source = sourceFor(sourceId);
              $$renderer2.push(`<li class="text-xs"><span class="font-semibold text-slate-300">${escape_html(source.label)}</span> <span class="ml-1 text-[10px] text-slate-500 font-mono">(${escape_html(source.kind)})</span> <span class="mt-0.5 block break-words font-mono text-[10px] leading-4 text-slate-500">${escape_html(source.ref)}</span></li>`);
            }
            $$renderer2.push(`<!--]--></ul></details>`);
          } else {
            $$renderer2.push("<!--[-1-->");
          }
          $$renderer2.push(`<!--]--></article>`);
        }
        $$renderer2.push(`<!--]--></div></section>`);
      }
      $$renderer2.push(`<!--]--></div>`);
    }
    $$renderer2.push(`<!--]--> <footer class="mt-12 border-t border-slate-800 pt-6 text-xs leading-5 text-slate-500 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4"><p><span class="text-emerald-300 font-medium">Preferred Standard</span> marks approved greenfield terms. <span class="text-amber-300 font-medium">Needs Reconciliation</span> marks cross-surface inconsistencies. <span class="text-rose-300 font-medium">Slated for Removal</span> marks jargon scheduled for elimination.</p> <p class="font-mono text-slate-600">NEXIAM Capstone Master Terminology Registry · 131 Terms · 57 Sources</p></footer></main></div>`);
  });
}
export {
  Glossary as default
};
