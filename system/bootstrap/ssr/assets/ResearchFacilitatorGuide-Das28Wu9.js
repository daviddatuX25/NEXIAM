import { h as head, B as Button, a as ensure_array_like, e as escape_html, b as attr, c as attr_class, d as derived } from "../app.js";
import PracticePreparationPanel from "./PracticePreparationPanel-CFZWv1Kg.js";
import { s as safeMarkdownHtml } from "./safeMarkdownHtml-BkQMeMEI.js";
import { P as Printer } from "./printer-Bx0z1CRb.js";
import { h as html } from "./html-BMEe54ZQ.js";
import { router } from "@inertiajs/core";
import "axios";
import "clsx";
import "laravel-precognition";
import "tailwind-merge";
import "tailwind-variants";
import "./dialog-description-D12L0QA9.js";
import "./noop-BQS7OFs8.js";
import "./create-id-CUJmDlea.js";
import "style-to-object";
import "./scroll-lock-B1ibcKMx.js";
import "./watch.svelte-BcPoqckX.js";
import "./is-C6cm64pF.js";
import "tabbable";
import "./x-CnxrMbMn.js";
import "./Icon-Dal7PqKK.js";
import "./dialog-footer-kNnwKT2h.js";
import "dompurify";
import "marked";
function ResearchFacilitatorGuide($$renderer, $$props) {
  $$renderer.component(($$renderer2) => {
    let {
      groups = [],
      selected_role = null,
      facilitator = {},
      source_version = null,
      can_control = false
    } = $$props;
    let preparationOpen = false;
    let preparationBusy = false;
    const preparationScenario = derived(() => ["student", "registrar", "guidance", "academic-leader"].includes(selected_role) ? selected_role : "student");
    const roleGroups = derived(() => Array.isArray(groups) ? groups : []);
    const guideRoles = derived(() => Array.isArray(facilitator?.roles) ? facilitator.roles : []);
    const taskRows = derived(() => guideRoles().flatMap((role) => (Array.isArray(role.tasks) ? role.tasks : []).map((task) => ({ role, task }))));
    const caseTrace = derived(() => Array.isArray(facilitator?.case_trace) ? facilitator.case_trace : []);
    const preparationHtml = derived(() => safeMarkdownHtml(String(facilitator?.preparation_markdown ?? "")));
    const briefingHtml = derived(() => safeMarkdownHtml(String(facilitator?.standard_briefing ?? "")));
    const observationHtml = derived(() => safeMarkdownHtml(String(facilitator?.observation_markdown ?? "")));
    const rulesHtml = derived(() => safeMarkdownHtml(String(facilitator?.rules_markdown ?? "")));
    function changeFilter(event) {
      const role = event.currentTarget.value;
      if (role && !roleGroups().some((group) => group.key === role)) return;
      router.get("/demo/research/facilitator", role ? { role } : {}, { preserveState: false, preserveScroll: true });
    }
    function printReference() {
      window.print();
    }
    head("aia818", $$renderer2, ($$renderer3) => {
      $$renderer3.title(($$renderer4) => {
        $$renderer4.push(`<title>Research facilitator reference · NEXIAM</title>`);
      });
      $$renderer3.push(`<meta name="description" content="Source-bound facilitator preparation, observation, and case trace reference."/>`);
    });
    $$renderer2.push(`<div class="min-h-screen bg-slate-950 text-slate-100 svelte-aia818"><main class="mx-auto w-full max-w-6xl px-4 py-6 sm:px-7 sm:py-10 lg:px-10 svelte-aia818"><header class="no-print flex flex-col gap-5 border-b border-slate-800 pb-6 sm:flex-row sm:items-start sm:justify-between svelte-aia818"><div class="min-w-0"><p class="text-xs font-semibold uppercase tracking-[0.16em] text-amber-200">Facilitator-only reference</p> <h1 class="mt-2 break-words text-3xl font-semibold tracking-tight text-white sm:text-4xl">Research session preparation</h1> <p class="mt-3 max-w-3xl text-sm leading-6 text-slate-300">Source-bound facilitator material and current use-case trace. This is a reference, not a session tracker; no observation or participant information is entered here.</p></div> `);
    Button($$renderer2, {
      type: "button",
      variant: "outline",
      class: "min-h-11 shrink-0 border-slate-700 bg-slate-900 text-slate-100 hover:bg-slate-800",
      onclick: printReference,
      children: ($$renderer3) => {
        Printer($$renderer3, { class: "mr-2 size-4", "aria-hidden": "true" });
        $$renderer3.push(`<!----> Print reference`);
      },
      $$slots: { default: true }
    });
    $$renderer2.push(`<!----></header> <div class="no-print mt-5 max-w-sm svelte-aia818"><label for="facilitator-role-filter" class="text-sm font-semibold text-slate-200">Role filter <span class="font-normal text-slate-400">(display only)</span></label> `);
    $$renderer2.select(
      {
        id: "facilitator-role-filter",
        value: selected_role ?? "",
        onchange: changeFilter,
        disabled: preparationBusy,
        class: "mt-2 flex min-h-11 w-full rounded-lg border border-slate-700 bg-slate-900 px-3 py-2 text-sm text-slate-100 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-blue-400"
      },
      ($$renderer3) => {
        $$renderer3.option({ value: "" }, ($$renderer4) => {
          $$renderer4.push(`All roles`);
        });
        $$renderer3.push(`<!--[-->`);
        const each_array = ensure_array_like(roleGroups());
        for (let $$index = 0, $$length = each_array.length; $$index < $$length; $$index++) {
          let group = each_array[$$index];
          $$renderer3.option({ value: group.key }, ($$renderer4) => {
            $$renderer4.push(`${escape_html(group.label)}`);
          });
        }
        $$renderer3.push(`<!--]-->`);
      }
    );
    $$renderer2.push(`</div> `);
    if (can_control) {
      $$renderer2.push("<!--[0-->");
      $$renderer2.push(`<section class="no-print mt-5 border-y border-slate-800 py-4 svelte-aia818" aria-label="Researcher practice preparation"><button type="button"${attr("aria-expanded", preparationOpen)} aria-controls="research-practice-panel"${attr("disabled", preparationBusy, true)} class="inline-flex min-h-11 items-center text-sm font-medium text-blue-300 underline underline-offset-4 hover:text-blue-200 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-blue-400 disabled:opacity-60">${escape_html("Prepare research practice")}</button> <p class="mt-1 max-w-prose text-sm leading-6 text-slate-400">Protected fictional practice is separate from the canonical reference. A new set does not erase prior attempts or establish study approval.</p> <div id="research-practice-panel"${attr_class("", void 0, { "mt-5": preparationOpen })}>`);
      PracticePreparationPanel($$renderer2, {
        purpose: "research",
        scenario: preparationScenario(),
        active: preparationOpen,
        can_control,
        onbusychange: (busy) => {
          preparationBusy = busy;
        }
      });
      $$renderer2.push(`<!----></div></section>`);
    } else {
      $$renderer2.push("<!--[-1-->");
    }
    $$renderer2.push(`<!--]--> <div class="print-reference svelte-aia818"><section class="mt-7 grid gap-4 lg:grid-cols-2 svelte-aia818" aria-label="Preparation and briefing"><article class="guide-facilitator-markdown min-w-0 rounded-xl border border-slate-800 bg-slate-900/70 p-4 sm:p-6 svelte-aia818"><p class="mb-2 text-xs font-semibold uppercase tracking-wide text-blue-200">Preparation required</p> ${html(preparationHtml())}</article> <article class="guide-facilitator-markdown min-w-0 rounded-xl border border-slate-800 bg-slate-900/70 p-4 sm:p-6 svelte-aia818"><h2 class="text-lg font-semibold text-white">Standard briefing</h2> ${html(briefingHtml())}</article></section> <section class="mt-8 svelte-aia818" aria-labelledby="role-task-heading"><h2 id="role-task-heading" class="text-xl font-semibold text-white">Role and task reference</h2> `);
    if (selected_role) {
      $$renderer2.push("<!--[0-->");
      $$renderer2.push(`<p class="mt-1 text-sm text-slate-400">Showing ${escape_html(roleGroups().find((group) => group.key === selected_role)?.label ?? selected_role)}. The filter changes what is displayed; it does not grant access.</p>`);
    } else {
      $$renderer2.push("<!--[-1-->");
      $$renderer2.push(`<p class="mt-1 text-sm text-slate-400">All roles are shown. Participant wording and task sequence are reproduced from the published source.</p>`);
    }
    $$renderer2.push(`<!--]--> <div class="mt-4 grid gap-3 md:grid-cols-2"><!--[-->`);
    const each_array_1 = ensure_array_like(guideRoles().filter((role) => !selected_role || role.key === selected_role));
    for (let $$index_1 = 0, $$length = each_array_1.length; $$index_1 < $$length; $$index_1++) {
      let role = each_array_1[$$index_1];
      $$renderer2.push(`<article class="rounded-xl border border-slate-800 bg-slate-900/70 p-4 svelte-aia818"><h3 class="text-base font-semibold text-white">${escape_html(role.label)}</h3> <p class="mt-1 text-xs font-medium text-slate-400">${escape_html(role.scenario)}</p> <dl class="mt-4 space-y-3 text-sm"><div><dt class="font-semibold text-blue-200">Required context</dt><dd class="mt-1 whitespace-pre-wrap break-words leading-6 text-slate-300">${escape_html(role.context)}</dd></div> <div><dt class="font-semibold text-blue-200">Role briefing</dt><dd class="mt-1 whitespace-pre-wrap break-words leading-6 text-slate-300">${escape_html(role.briefing)}</dd></div> <div><dt class="font-semibold text-amber-200">Restrictions</dt><dd class="mt-1 whitespace-pre-wrap break-words leading-6 text-slate-300">${escape_html(role.restrictions)}</dd></div></dl></article>`);
    }
    $$renderer2.push(`<!--]--></div> <div class="mt-4 rounded-xl border border-border/80 bg-card/60 backdrop-blur-md overflow-hidden"><div class="overflow-x-auto"><table class="w-full min-w-[720px] border-collapse text-left text-sm"><thead class="bg-muted/50 text-xs uppercase tracking-wide text-muted-foreground"><tr><th scope="col" class="px-4 py-3">Role / scenario</th><th scope="col" class="px-4 py-3">Task</th><th scope="col" class="px-4 py-3">Source item mapping</th></tr></thead><tbody class="divide-y divide-border/60 bg-transparent">`);
    const each_array_2 = ensure_array_like(taskRows().filter(({ role }) => !selected_role || role.key === selected_role));
    if (each_array_2.length !== 0) {
      $$renderer2.push("<!--[-->");
      for (let $$index_3 = 0, $$length = each_array_2.length; $$index_3 < $$length; $$index_3++) {
        let row = each_array_2[$$index_3];
        $$renderer2.push(`<tr class="align-top"><td class="px-4 py-3"><p class="font-semibold text-slate-100">${escape_html(row.role.label)}</p><p class="mt-1 text-xs text-slate-400">${escape_html(row.role.scenario)}</p></td><td class="max-w-xl whitespace-pre-wrap break-words px-4 py-3"><span class="font-semibold text-blue-200">${escape_html(row.task.id)}</span><p class="mt-1 leading-6 text-slate-200">${escape_html(row.task.text)}</p></td><td class="px-4 py-3"><div class="flex flex-wrap gap-1.5">`);
        if (row.task.item_ids?.length) {
          $$renderer2.push("<!--[0-->");
          $$renderer2.push(`<!--[-->`);
          const each_array_3 = ensure_array_like(row.task.item_ids);
          for (let $$index_2 = 0, $$length2 = each_array_3.length; $$index_2 < $$length2; $$index_2++) {
            let item = each_array_3[$$index_2];
            $$renderer2.push(`<code class="rounded border border-slate-700 bg-slate-900 px-2 py-1 text-xs text-slate-200">${escape_html(item)}</code>`);
          }
          $$renderer2.push(`<!--]-->`);
        } else {
          $$renderer2.push("<!--[-1-->");
          $$renderer2.push(`<span class="text-slate-400">No item IDs published</span>`);
        }
        $$renderer2.push(`<!--]--></div></td></tr>`);
      }
    } else {
      $$renderer2.push("<!--[!-->");
      $$renderer2.push(`<tr><td colspan="3" class="px-4 py-6 text-sm text-slate-400">No role/task mapping is published for this selection.</td></tr>`);
    }
    $$renderer2.push(`<!--]--></tbody></table></div></div></section> <section class="mt-8 svelte-aia818" aria-labelledby="observation-heading"><h2 id="observation-heading" class="text-xl font-semibold text-white">Observation reference</h2> <p class="mt-1 text-sm leading-6 text-slate-400">Blank, read-only reference only. No fields below accept or retain observations.</p> <article class="guide-facilitator-markdown mt-4 rounded-xl border border-slate-800 bg-slate-900/70 p-4 sm:p-6 svelte-aia818">${html(observationHtml())}</article></section> <section class="mt-8 svelte-aia818" aria-labelledby="rules-heading"><h2 id="rules-heading" class="text-xl font-semibold text-white">Facilitation rules</h2> <article class="guide-facilitator-markdown mt-4 rounded-xl border border-slate-800 bg-slate-900/70 p-4 sm:p-6 svelte-aia818">${html(rulesHtml())}</article></section> <section class="mt-8 svelte-aia818" aria-labelledby="paired-exchange-heading"><h2 id="paired-exchange-heading" class="text-xl font-semibold text-white">Paired exchange</h2> <article class="mt-4 rounded-xl border border-slate-800 bg-slate-900/70 p-4 sm:p-6 svelte-aia818"><p class="text-sm font-semibold text-slate-100">Tasks ${escape_html((facilitator?.paired_exchange?.tasks ?? []).join(" and "))}</p> <p class="mt-2 whitespace-pre-wrap break-words text-sm leading-6 text-slate-300">${escape_html(facilitator?.paired_exchange?.note ?? "Paired-exchange guidance is not included in the published reference.")}</p></article></section> <section class="mt-8 svelte-aia818" aria-labelledby="trace-heading"><h2 id="trace-heading" class="text-xl font-semibold text-white">Current use-case trace and limitations</h2> <p class="mt-2 text-sm leading-6 text-amber-100">Source-derived boundaries, not exercised task outcomes. Evaluation accounts, fixtures, permissions, physical outputs and institutional approval remain UNVERIFIED.</p> `);
    if (caseTrace().length > 0) {
      $$renderer2.push("<!--[0-->");
      $$renderer2.push(`<div class="mt-4 grid gap-4"><!--[-->`);
      const each_array_4 = ensure_array_like(caseTrace());
      for (let $$index_4 = 0, $$length = each_array_4.length; $$index_4 < $$length; $$index_4++) {
        let trace = each_array_4[$$index_4];
        $$renderer2.push(`<article class="min-w-0 rounded-xl border border-slate-800 bg-slate-900/70 p-4 sm:p-6 svelte-aia818"><h3 class="text-base font-semibold text-white">${escape_html(trace.task_id)}: ${escape_html(trace.goal)}</h3> <dl class="mt-4 grid gap-x-5 gap-y-3 text-sm sm:grid-cols-2"><div><dt class="font-semibold text-blue-200">Workspace</dt><dd class="mt-1 whitespace-pre-wrap break-words leading-6 text-slate-300">${escape_html(trace.workspace)}</dd></div> <div><dt class="font-semibold text-blue-200">Capability</dt><dd class="mt-1 whitespace-pre-wrap break-words leading-6 text-slate-300">${escape_html(trace.capability)}</dd></div> <div><dt class="font-semibold text-blue-200">Scope</dt><dd class="mt-1 whitespace-pre-wrap break-words leading-6 text-slate-300">${escape_html(trace.scope)}</dd></div> <div><dt class="font-semibold text-blue-200">Prerequisites</dt><dd class="mt-1 whitespace-pre-wrap break-words leading-6 text-slate-300">${escape_html(trace.prerequisites)}</dd></div> <div><dt class="font-semibold text-blue-200">Intended outcome</dt><dd class="mt-1 whitespace-pre-wrap break-words leading-6 text-slate-300">${escape_html(trace.outcome)}</dd></div> <div><dt class="font-semibold text-amber-200">Limitations</dt><dd class="mt-1 whitespace-pre-wrap break-words leading-6 text-slate-300">${escape_html(trace.limitations)}</dd></div></dl> `);
        if (trace.source) {
          $$renderer2.push("<!--[0-->");
          $$renderer2.push(`<p class="mt-4 break-words border-t border-slate-800 pt-3 text-xs leading-5 text-slate-400">Source: ${escape_html(Array.isArray(trace.source) ? trace.source.join("; ") : trace.source)}</p>`);
        } else {
          $$renderer2.push("<!--[-1-->");
        }
        $$renderer2.push(`<!--]--></article>`);
      }
      $$renderer2.push(`<!--]--></div>`);
    } else {
      $$renderer2.push("<!--[-1-->");
      $$renderer2.push(`<p class="mt-4 rounded-xl border border-amber-400/20 bg-amber-400/5 p-4 text-sm leading-6 text-amber-100" role="status"><strong>UNVERIFIED:</strong> No current use-case trace is present in the published facilitator reference. Do not treat this absence as evidence that a workspace, capability, scope, or case is ready.</p>`);
    }
    $$renderer2.push(`<!--]--></section> `);
    if (source_version) {
      $$renderer2.push("<!--[0-->");
      $$renderer2.push(`<section class="mt-8 rounded-xl border border-slate-800 bg-slate-900/70 p-4 sm:p-6 svelte-aia818" aria-label="Reference provenance"><h2 class="text-sm font-semibold text-slate-200">Reference provenance</h2> <dl class="mt-3 grid gap-3 text-xs sm:grid-cols-2"><!--[-->`);
      const each_array_5 = ensure_array_like(Object.entries(source_version));
      for (let $$index_5 = 0, $$length = each_array_5.length; $$index_5 < $$length; $$index_5++) {
        let [name, value] = each_array_5[$$index_5];
        $$renderer2.push(`<div class="min-w-0"><dt class="font-medium text-slate-400">${escape_html(name)}</dt><dd class="mt-1 break-all font-mono text-slate-300">${escape_html(value)}</dd></div>`);
      }
      $$renderer2.push(`<!--]--></dl></section>`);
    } else {
      $$renderer2.push("<!--[-1-->");
    }
    $$renderer2.push(`<!--]--></div></main></div>`);
  });
}
export {
  ResearchFacilitatorGuide as default
};
