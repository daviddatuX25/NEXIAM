import { h as head, e as escape_html, b as attr, a as ensure_array_like, c as attr_class } from "../app.js";
import { B as Badge } from "./badge-D9Vgs7xh.js";
import "axios";
import "@inertiajs/core";
import "clsx";
import "laravel-precognition";
import "tailwind-merge";
import "tailwind-variants";
function Status($$renderer, $$props) {
  $$renderer.component(($$renderer2) => {
    let { foundation = {}, roadmap = [] } = $$props;
    let refreshing = false;
    function statusLabel(status) {
      return {
        complete: "Complete",
        in_progress: "In progress",
        next: "Next",
        planned: "Planned",
        verify: "To verify"
      }[status] ?? status;
    }
    function statusClasses(status) {
      return {
        complete: "border-emerald-400/30 bg-emerald-400/10 text-emerald-300",
        in_progress: "border-amber-400/30 bg-amber-400/10 text-amber-300",
        next: "border-blue-400/30 bg-blue-400/10 text-blue-300",
        planned: "border-slate-700 bg-slate-800/70 text-slate-300",
        verify: "border-slate-700 bg-slate-950/60 text-slate-400"
      }[status] ?? "border-slate-700 bg-slate-800/70 text-slate-300";
    }
    function statusMark(status) {
      return status === "complete" ? "✓" : status === "next" ? "→" : "";
    }
    function checkMark(status) {
      return status === "complete" ? "✓" : "□";
    }
    function completedCount(group) {
      return group.items.filter((item) => item.status === "complete").length;
    }
    head("1x4li1", $$renderer2, ($$renderer3) => {
      $$renderer3.title(($$renderer4) => {
        $$renderer4.push(`<title>Feature roadmap · NEXIAM</title>`);
      });
    });
    $$renderer2.push(`<div class="min-h-screen bg-slate-950 text-slate-100"><main class="mx-auto max-w-6xl px-5 py-10 sm:px-8 lg:px-10"><header class="flex flex-col gap-5 border-b border-slate-800 pb-8 sm:flex-row sm:items-end sm:justify-between"><div><p class="text-sm font-semibold tracking-[0.24em] text-blue-300 uppercase">NEXIAM system</p> <h1 class="mt-3 text-3xl font-semibold tracking-tight text-white sm:text-5xl">Feature roadmap</h1> <p class="mt-4 max-w-3xl text-base leading-7 text-slate-300">A project-management checklist: what is being re-adopted from legacy, what is new flagship work, and what is next for the capstone.</p></div> <div class="flex flex-wrap items-center gap-3">`);
    Badge($$renderer2, {
      class: "border-blue-400/30 bg-blue-400/10 px-3 py-1.5 text-blue-300",
      children: ($$renderer3) => {
        $$renderer3.push(`<!---->${escape_html(foundation.phase)}`);
      },
      $$slots: { default: true }
    });
    $$renderer2.push(`<!----> <a href="/capstone/prototype" class="inline-flex min-h-10 items-center gap-2 rounded-lg border border-sky-500/40 bg-sky-500/10 px-3.5 py-2 text-sm font-semibold text-sky-300 transition hover:border-sky-400 hover:bg-sky-500/20"><span>Interactive Prototype</span> <span>→</span></a> <a href="/capstone/office-guide" class="inline-flex min-h-10 items-center gap-2 rounded-lg border border-emerald-500/40 bg-emerald-500/10 px-3.5 py-2 text-sm font-semibold text-emerald-300 transition hover:border-emerald-400 hover:bg-emerald-500/20"><span>Office Field Guide</span> <span>→</span></a> <a href="/capstone/demo-guide" class="inline-flex min-h-10 items-center gap-2 rounded-lg border border-blue-500/40 bg-blue-500/10 px-3.5 py-2 text-sm font-semibold text-blue-300 transition hover:border-blue-400 hover:bg-blue-500/20"><span>Master Demo Guide</span> <span>→</span></a> <button type="button" class="min-h-10 rounded-lg border border-slate-700 px-3 py-2 text-sm font-semibold text-slate-200 transition hover:border-slate-500 hover:bg-slate-800 disabled:cursor-wait disabled:opacity-60"${attr("disabled", refreshing, true)}>${escape_html("Refresh")}</button></div></header> <section class="mt-8 grid gap-4 md:grid-cols-3" aria-label="Roadmap summary"><div class="rounded-xl border border-slate-800 bg-slate-900/80 p-5"><p class="text-xs font-semibold tracking-[0.18em] text-blue-300 uppercase">System boundary</p> <p class="mt-2 font-medium text-white">${escape_html(foundation.runtime)}</p> <p class="mt-1 text-sm text-slate-400">${escape_html(foundation.database)} · greenfield code in system/</p></div> <div class="rounded-xl border border-slate-800 bg-slate-900/80 p-5"><p class="text-xs font-semibold tracking-[0.18em] text-blue-300 uppercase">Checklist groups</p> <p class="mt-2 font-medium text-white">${escape_html(roadmap.length)} macro areas</p> <p class="mt-1 text-sm text-slate-400">Legacy parity, flagship capabilities, and capstone gates.</p></div> <div class="rounded-xl border border-slate-800 bg-slate-900/80 p-5"><p class="text-xs font-semibold tracking-[0.18em] text-blue-300 uppercase">Working rule</p> <p class="mt-2 font-medium text-white">Macro first, details on click</p> <p class="mt-1 text-sm text-slate-400">Expand a feature to see checklists, subfeatures, interfaces, and behavior.</p></div></section> <section class="mt-8 space-y-5" aria-labelledby="roadmap-checklist"><div><p class="text-xs font-semibold tracking-[0.2em] text-blue-300 uppercase">Project management</p> <h2 id="roadmap-checklist" class="mt-2 text-2xl font-semibold text-white">Feature checklist</h2></div> <!--[-->`);
    const each_array = ensure_array_like(roadmap);
    for (let $$index_3 = 0, $$length = each_array.length; $$index_3 < $$length; $$index_3++) {
      let group = each_array[$$index_3];
      $$renderer2.push(`<section class="rounded-2xl border border-slate-800 bg-slate-900/80 p-5 shadow-2xl shadow-slate-950/20 sm:p-7"${attr("aria-labelledby", `${group.key}-title`)}><div class="flex flex-col gap-2 border-b border-slate-800 pb-4 sm:flex-row sm:items-end sm:justify-between"><div><h3${attr("id", `${group.key}-title`)} class="text-xl font-semibold text-white">${escape_html(group.title)}</h3> <p class="mt-1 text-sm text-slate-400">${escape_html(group.description)}</p></div> <p class="text-sm text-slate-400">${escape_html(completedCount(group))} of ${escape_html(group.items.length)} complete</p></div> <ol class="mt-4 space-y-3"><!--[-->`);
      const each_array_1 = ensure_array_like(group.items);
      for (let index = 0, $$length2 = each_array_1.length; index < $$length2; index++) {
        let item = each_array_1[index];
        $$renderer2.push(`<li><details class="group/feature rounded-xl border border-slate-800 bg-slate-950/40 open:border-slate-600"><summary class="flex cursor-pointer list-none items-start gap-3 p-4 [&amp;::-webkit-details-marker]:hidden"><span${attr_class(`mt-0.5 flex size-6 shrink-0 items-center justify-center rounded-md border text-sm font-bold ${statusClasses(item.status)}`)} role="checkbox"${attr("aria-checked", item.status === "complete")}${attr("aria-label", `${item.name}: ${statusLabel(item.status)}`)}>${escape_html(statusMark(item.status) || index + 1)}</span> <span class="min-w-0 flex-1"><span class="flex flex-wrap items-center gap-2"><span class="font-semibold text-white">${escape_html(item.name)}</span> `);
        Badge($$renderer2, {
          class: statusClasses(item.status),
          children: ($$renderer3) => {
            $$renderer3.push(`<!---->${escape_html(statusLabel(item.status))}`);
          },
          $$slots: { default: true }
        });
        $$renderer2.push(`<!----></span> <span class="mt-1 block text-sm leading-6 text-slate-300">${escape_html(item.detail)}</span></span> <span class="mt-1 text-slate-500 transition group-open/feature:rotate-180" aria-hidden="true">⌄</span></summary> <div class="border-t border-slate-800 px-4 pb-5 pt-4 sm:pl-[4.25rem]">`);
        if (item.interface_checks?.length) {
          $$renderer2.push("<!--[0-->");
          $$renderer2.push(`<p class="text-xs font-semibold tracking-[0.16em] text-blue-300 uppercase">Interface checklist</p> <ul class="mt-3 grid gap-2 text-sm text-slate-300 sm:grid-cols-2"><!--[-->`);
          const each_array_2 = ensure_array_like(item.interface_checks);
          for (let $$index = 0, $$length3 = each_array_2.length; $$index < $$length3; $$index++) {
            let check = each_array_2[$$index];
            $$renderer2.push(`<li class="flex items-center gap-2 rounded-lg border border-slate-800 px-3 py-2"><span class="font-mono text-base text-slate-400" aria-hidden="true">${escape_html(checkMark(check.status))}</span> <span class="flex-1">${escape_html(check.label)}</span> <span class="text-xs text-slate-500">${escape_html(statusLabel(check.status))}</span></li>`);
          }
          $$renderer2.push(`<!--]--></ul>`);
        } else {
          $$renderer2.push("<!--[-1-->");
        }
        $$renderer2.push(`<!--]--> `);
        if (item.subfeature_checks?.length) {
          $$renderer2.push("<!--[0-->");
          $$renderer2.push(`<p class="mt-5 text-xs font-semibold tracking-[0.16em] text-blue-300 uppercase">Subfeature checklist</p> <ul class="mt-3 grid gap-2 text-sm text-slate-300 sm:grid-cols-2"><!--[-->`);
          const each_array_3 = ensure_array_like(item.subfeature_checks);
          for (let $$index_1 = 0, $$length3 = each_array_3.length; $$index_1 < $$length3; $$index_1++) {
            let check = each_array_3[$$index_1];
            $$renderer2.push(`<li class="flex items-center gap-2 rounded-lg border border-slate-800 px-3 py-2"><span class="font-mono text-base text-slate-400" aria-hidden="true">${escape_html(checkMark(check.status))}</span> <span class="flex-1">${escape_html(check.label)}</span> <span class="text-xs text-slate-500">${escape_html(statusLabel(check.status))}</span></li>`);
          }
          $$renderer2.push(`<!--]--></ul>`);
        } else {
          $$renderer2.push("<!--[-1-->");
        }
        $$renderer2.push(`<!--]--> `);
        if (item.sequence) {
          $$renderer2.push("<!--[0-->");
          $$renderer2.push(`<p class="mt-5 text-xs font-semibold tracking-[0.16em] text-blue-300 uppercase">Behavior sequence</p> <div class="mermaid-slot mt-3 overflow-x-auto rounded-lg border border-slate-800 bg-slate-950/60 p-4"${attr("data-name", item.name)}${attr("data-sequence", item.sequence)}></div> <pre class="mermaid-raw mt-3 hidden overflow-x-auto rounded-lg border border-slate-800 bg-slate-950 p-4 text-xs leading-5 text-slate-300"><code>${escape_html(item.sequence)}</code></pre>`);
        } else {
          $$renderer2.push("<!--[-1-->");
        }
        $$renderer2.push(`<!--]--></div></details></li>`);
      }
      $$renderer2.push(`<!--]--></ol></section>`);
    }
    $$renderer2.push(`<!--]--></section> <footer class="mt-8 flex flex-col gap-3 border-t border-slate-800 pt-5 text-sm text-slate-400 sm:flex-row sm:items-center sm:justify-between"><p>${escape_html(foundation.legacyReference)}</p> <div class="flex flex-wrap gap-2"><a class="rounded-lg border border-slate-700 px-3 py-2 text-slate-200 hover:bg-slate-800" href="/capstone-interview-guide.html">Interview guide</a> <a class="rounded-lg border border-slate-700 px-3 py-2 text-slate-200 hover:bg-slate-800" href="/capstone/client-brief">Client brief</a> <a class="rounded-lg border border-slate-700 px-3 py-2 text-slate-200 hover:bg-slate-800" href="/capstone-manuscript-brief.html">Manuscript brief</a> <a class="rounded-lg border border-slate-700 px-3 py-2 text-slate-200 hover:bg-slate-800" href="/capstone-wireframe-review.html">Wireframe review</a> <a class="rounded-lg border border-slate-700 px-3 py-2 text-slate-200 hover:bg-slate-800" href="/ui-kit">UI kit</a></div></footer></main></div>`);
  });
}
export {
  Status as default
};
