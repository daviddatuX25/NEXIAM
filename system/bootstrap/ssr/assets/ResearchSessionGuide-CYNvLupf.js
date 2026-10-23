import { h as head, e as escape_html, a as ensure_array_like, b as attr, c as attr_class, B as Button, d as derived } from "../app.js";
import DemoReferenceViewer from "./DemoReferenceViewer-BpDsrsw7.js";
import { s as safeMarkdownHtml } from "./safeMarkdownHtml-BkQMeMEI.js";
import { B as Book_open } from "./book-open-B6eMguqY.js";
import { C as Clipboard_list } from "./clipboard-list-YgthURdB.js";
import { F as File_text } from "./file-text-PXEtmXm9.js";
import { A as Arrow_right } from "./arrow-right-CzynHiIo.js";
import { A as Arrow_left } from "./arrow-left-DbWnQR3t.js";
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
import "./download-n32NlL8c.js";
import "./external-link-DmB8ndP3.js";
import "./minus-Dqkhcxt7.js";
import "./plus-DjyPLC7A.js";
import "./rotate-ccw-D6Dm_efx.js";
import "dompurify";
import "marked";
function ResearchSessionGuide($$renderer, $$props) {
  $$renderer.component(($$renderer2) => {
    let {
      groups = [],
      selected_role = "student",
      participant = {},
      questionnaire = {},
      after_instructions = ""
    } = $$props;
    let section = "before";
    let referenceOpen = false;
    let selectedReference = null;
    let taskIndex = 0;
    const roleGroups = derived(() => Array.isArray(groups) ? groups : []);
    const currentTasks = derived(() => Array.isArray(participant?.tasks) ? participant.tasks : []);
    const currentTask = derived(() => currentTasks()[taskIndex] ?? null);
    const instructionHtml = derived(() => safeMarkdownHtml(String(after_instructions ?? "")));
    function changeRole(event) {
      const role = event.currentTarget.value;
      if (!roleGroups().some((group) => group.key === role) || role === selected_role) return;
      router.get("/demo/research", { role }, { preserveState: false, preserveScroll: false });
    }
    function moveTask(direction) {
      const nextIndex = taskIndex + direction;
      if (nextIndex < 0 || nextIndex >= currentTasks().length) return;
      taskIndex = nextIndex;
    }
    function openQuestionnaire() {
      selectedReference = {
        ...questionnaire,
        kind: "material",
        note: questionnaire?.note ?? "Read-only research instrument. No responses are collected or saved here."
      };
      referenceOpen = true;
    }
    let $$settled = true;
    let $$inner_renderer;
    function $$render_inner($$renderer3) {
      head("fmaoa2", $$renderer3, ($$renderer4) => {
        $$renderer4.title(($$renderer5) => {
          $$renderer5.push(`<title>Research session guide · NEXIAM</title>`);
        });
        $$renderer4.push(`<meta name="description" content="Read the source-bound research session instructions for your assigned participant role."/>`);
      });
      $$renderer3.push(`<div class="min-h-screen bg-slate-950 text-slate-100"><main class="mx-auto w-full max-w-5xl px-4 py-6 sm:px-7 sm:py-10 lg:px-10"><header class="border-b border-slate-800 pb-6"><div class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between"><div class="min-w-0"><p class="text-xs font-semibold uppercase tracking-[0.16em] text-blue-300">Research session</p> <h1 class="mt-2 break-words text-3xl font-semibold tracking-tight text-white sm:text-4xl">${escape_html(participant?.title ?? "Session guide")}</h1> <p class="mt-3 max-w-3xl text-sm leading-6 text-slate-300">Read the assigned instructions before using the separately provided research workspace. This guide does not change access or record responses.</p></div> <div class="w-full shrink-0 sm:w-64"><label for="research-role" class="text-sm font-semibold text-slate-200">Participant role</label> `);
      $$renderer3.select(
        {
          id: "research-role",
          value: selected_role,
          onchange: changeRole,
          class: "mt-2 flex min-h-11 w-full rounded-lg border border-slate-700 bg-slate-900 px-3 py-2 text-sm text-slate-100 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-blue-400"
        },
        ($$renderer4) => {
          $$renderer4.push(`<!--[-->`);
          const each_array = ensure_array_like(roleGroups());
          for (let $$index = 0, $$length = each_array.length; $$index < $$length; $$index++) {
            let group = each_array[$$index];
            $$renderer4.option({ value: group.key }, ($$renderer5) => {
              $$renderer5.push(`${escape_html(group.label)}`);
            });
          }
          $$renderer4.push(`<!--]-->`);
        }
      );
      $$renderer3.push(`</div></div> <div class="mt-5 flex flex-wrap items-center gap-2" role="group" aria-label="Research guide sections"><button type="button"${attr("aria-pressed", section === "before")}${attr_class(`inline-flex min-h-10 items-center gap-2 rounded-lg border px-3 py-2 text-sm font-semibold transition focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-blue-400 ${section === "before" ? "border-blue-400/50 bg-blue-500/15 text-blue-100" : "border-slate-700 bg-slate-900 text-slate-300 hover:bg-slate-800"}`)}>`);
      Book_open($$renderer3, { class: "size-4", "aria-hidden": "true" });
      $$renderer3.push(`<!----> Before</button> <button type="button"${attr("aria-pressed", section === "tasks")}${attr_class(`inline-flex min-h-10 items-center gap-2 rounded-lg border px-3 py-2 text-sm font-semibold transition focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-blue-400 ${section === "tasks" ? "border-blue-400/50 bg-blue-500/15 text-blue-100" : "border-slate-700 bg-slate-900 text-slate-300 hover:bg-slate-800"}`)}>`);
      Clipboard_list($$renderer3, { class: "size-4", "aria-hidden": "true" });
      $$renderer3.push(`<!----> Tasks</button> <button type="button"${attr("aria-pressed", section === "after")}${attr_class(`inline-flex min-h-10 items-center gap-2 rounded-lg border px-3 py-2 text-sm font-semibold transition focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-blue-400 ${section === "after" ? "border-blue-400/50 bg-blue-500/15 text-blue-100" : "border-slate-700 bg-slate-900 text-slate-300 hover:bg-slate-800"}`)}>`);
      File_text($$renderer3, { class: "size-4", "aria-hidden": "true" });
      $$renderer3.push(`<!----> After</button></div></header> `);
      if (section === "before") {
        $$renderer3.push("<!--[0-->");
        $$renderer3.push(`<section class="mt-6 space-y-4" aria-labelledby="before-heading"><h2 id="before-heading" class="text-xl font-semibold text-white">Before you begin</h2> <div class="rounded-xl border border-slate-800 bg-slate-900/70 p-4 sm:p-6"><h3 class="text-sm font-semibold uppercase tracking-wide text-blue-200">Standard briefing</h3> <p class="mt-3 whitespace-pre-wrap break-words text-sm leading-7 text-slate-200">${escape_html(participant?.standard_briefing ?? "")}</p></div> <div class="rounded-xl border border-slate-800 bg-slate-900/70 p-4 sm:p-6"><h3 class="text-sm font-semibold uppercase tracking-wide text-blue-200">Role briefing</h3> <p class="mt-3 whitespace-pre-wrap break-words text-sm leading-7 text-slate-200">${escape_html(participant?.briefing ?? "")}</p></div> <div class="rounded-xl border border-amber-400/20 bg-amber-400/5 p-4 sm:p-6"><h3 class="text-sm font-semibold uppercase tracking-wide text-amber-100">Required context</h3> <p class="mt-3 whitespace-pre-wrap break-words text-sm leading-7 text-slate-200">${escape_html(participant?.context ?? "")}</p> `);
        if (participant?.restrictions) {
          $$renderer3.push("<!--[0-->");
          $$renderer3.push(`<p class="mt-4 border-t border-amber-200/10 pt-4 whitespace-pre-wrap break-words text-sm leading-7 text-slate-300">${escape_html(participant.restrictions)}</p>`);
        } else {
          $$renderer3.push("<!--[-1-->");
        }
        $$renderer3.push(`<!--]--></div> <div class="flex justify-end pt-1">`);
        Button($$renderer3, {
          type: "button",
          variant: "outline",
          class: "min-h-11 border-slate-700 bg-slate-900 text-slate-100 hover:bg-slate-800",
          onclick: () => section = "tasks",
          children: ($$renderer4) => {
            $$renderer4.push(`<!---->Continue to tasks `);
            Arrow_right($$renderer4, { class: "ml-2 size-4", "aria-hidden": "true" });
            $$renderer4.push(`<!---->`);
          },
          $$slots: { default: true }
        });
        $$renderer3.push(`<!----></div></section>`);
      } else if (section === "tasks") {
        $$renderer3.push("<!--[1-->");
        $$renderer3.push(`<section class="mt-6" aria-labelledby="tasks-heading"><div class="flex flex-col gap-2 sm:flex-row sm:items-end sm:justify-between"><div><p class="text-xs font-semibold uppercase tracking-[0.16em] text-blue-300">Assigned activity</p> <h2 id="tasks-heading" class="mt-1 text-xl font-semibold text-white">Research tasks</h2></div> `);
        if (currentTask()) {
          $$renderer3.push("<!--[0-->");
          $$renderer3.push(`<p class="text-sm tabular-nums text-slate-400">Task ${escape_html(taskIndex + 1)} of ${escape_html(currentTasks().length)}</p>`);
        } else {
          $$renderer3.push("<!--[-1-->");
        }
        $$renderer3.push(`<!--]--></div> `);
        if (currentTask()) {
          $$renderer3.push("<!--[0-->");
          $$renderer3.push(`<article class="mt-4 min-h-44 rounded-xl border border-slate-800 bg-slate-900/70 p-5 sm:p-7"><p class="text-xs font-semibold uppercase tracking-wide text-slate-400">Task ${escape_html(currentTask().id)}</p> <p class="mt-4 whitespace-pre-wrap break-words text-base leading-7 text-slate-100 sm:text-lg">${escape_html(currentTask().text)}</p></article> <div class="mt-4 flex flex-col-reverse gap-3 sm:flex-row sm:items-center sm:justify-between">`);
          Button($$renderer3, {
            type: "button",
            variant: "outline",
            class: "min-h-11 w-full border-slate-700 bg-slate-900 text-slate-100 hover:bg-slate-800 sm:w-auto",
            disabled: taskIndex === 0,
            onclick: () => moveTask(-1),
            children: ($$renderer4) => {
              Arrow_left($$renderer4, { class: "mr-2 size-4", "aria-hidden": "true" });
              $$renderer4.push(`<!----> Previous task`);
            },
            $$slots: { default: true }
          });
          $$renderer3.push(`<!----> <p class="text-center text-xs leading-5 text-slate-400">Task navigation changes only this displayed reference.</p> `);
          Button($$renderer3, {
            type: "button",
            variant: "outline",
            class: "min-h-11 w-full border-slate-700 bg-slate-900 text-slate-100 hover:bg-slate-800 sm:w-auto",
            disabled: taskIndex >= currentTasks().length - 1,
            onclick: () => moveTask(1),
            children: ($$renderer4) => {
              $$renderer4.push(`<!---->Next task `);
              Arrow_right($$renderer4, { class: "ml-2 size-4", "aria-hidden": "true" });
              $$renderer4.push(`<!---->`);
            },
            $$slots: { default: true }
          });
          $$renderer3.push(`<!----></div>`);
        } else {
          $$renderer3.push("<!--[-1-->");
          $$renderer3.push(`<p class="mt-4 rounded-xl border border-slate-800 bg-slate-900/70 p-5 text-sm leading-6 text-slate-300">No tasks are listed for this role in the published reference.</p>`);
        }
        $$renderer3.push(`<!--]--> <div class="mt-6 flex justify-end">`);
        Button($$renderer3, {
          type: "button",
          variant: "outline",
          class: "min-h-11 border-slate-700 bg-slate-900 text-slate-100 hover:bg-slate-800",
          onclick: () => section = "after",
          children: ($$renderer4) => {
            $$renderer4.push(`<!---->Continue to after session `);
            Arrow_right($$renderer4, { class: "ml-2 size-4", "aria-hidden": "true" });
            $$renderer4.push(`<!---->`);
          },
          $$slots: { default: true }
        });
        $$renderer3.push(`<!----></div></section>`);
      } else {
        $$renderer3.push("<!--[-1-->");
        $$renderer3.push(`<section class="mt-6 space-y-4" aria-labelledby="after-heading"><div><p class="text-xs font-semibold uppercase tracking-[0.16em] text-blue-300">Read-only reference</p> <h2 id="after-heading" class="mt-1 text-xl font-semibold text-white">After the session</h2> <p class="mt-2 max-w-3xl text-sm leading-6 text-slate-300">Use the canonical instructions below for blank, neutral, or failure observations. This page does not collect answers or store session notes.</p></div> <article class="guide-research-markdown rounded-xl border border-slate-800 bg-slate-900/70 p-4 sm:p-6">${html(instructionHtml())}</article> <div class="flex flex-col gap-3 sm:flex-row sm:flex-wrap">`);
        Button($$renderer3, {
          type: "button",
          variant: "outline",
          class: "min-h-11 w-full border-slate-700 bg-slate-900 text-slate-100 hover:bg-slate-800 sm:w-auto",
          onclick: openQuestionnaire,
          children: ($$renderer4) => {
            $$renderer4.push(`<!---->Open ${escape_html(questionnaire?.title ?? "questionnaire")} `);
            File_text($$renderer4, { class: "ml-2 size-4", "aria-hidden": "true" });
            $$renderer4.push(`<!---->`);
          },
          $$slots: { default: true }
        });
        $$renderer3.push(`<!----> <a${attr("href", questionnaire?.url)} download="" class="inline-flex min-h-11 items-center justify-center gap-2 rounded-lg border border-slate-700 bg-slate-900 px-4 py-2 text-sm font-semibold text-slate-100 transition hover:bg-slate-800 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-blue-400">Download blank questionnaire `);
        File_text($$renderer3, { class: "size-4", "aria-hidden": "true" });
        $$renderer3.push(`<!----></a> `);
        Button($$renderer3, {
          type: "button",
          variant: "outline",
          class: "min-h-11 w-full border-slate-700 bg-slate-900 text-slate-100 hover:bg-slate-800 sm:w-auto",
          onclick: () => section = "before",
          children: ($$renderer4) => {
            Arrow_left($$renderer4, { class: "mr-2 size-4", "aria-hidden": "true" });
            $$renderer4.push(`<!----> Back to before`);
          },
          $$slots: { default: true }
        });
        $$renderer3.push(`<!----></div> <p class="text-xs leading-5 text-slate-500">${escape_html(questionnaire?.note ?? "")}</p></section>`);
      }
      $$renderer3.push(`<!--]--></main></div> `);
      DemoReferenceViewer($$renderer3, {
        reference: selectedReference,
        get open() {
          return referenceOpen;
        },
        set open($$value) {
          referenceOpen = $$value;
          $$settled = false;
        }
      });
      $$renderer3.push(`<!---->`);
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
  ResearchSessionGuide as default
};
