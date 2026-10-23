import { g as sanitize_props, i as spread_props, j as slot, h as head, B as Button, a as ensure_array_like, e as escape_html, b as attr, c as attr_class, d as derived, m as csrfHeaders } from "../app.js";
import { D as Dialog, a as Dialog_content, b as Dialog_header, c as Dialog_title, d as Dialog_description } from "./dialog-description-D12L0QA9.js";
import { D as Dialog_footer } from "./dialog-footer-kNnwKT2h.js";
import { L as Label } from "./label-dGY6L9vm.js";
import { B as Badge } from "./badge-D9Vgs7xh.js";
import { T as ToastManager } from "./ToastManager-C7oO_4Jn.js";
import DemoReferenceViewer from "./DemoReferenceViewer-BpDsrsw7.js";
import PracticePreparationPanel from "./PracticePreparationPanel-CFZWv1Kg.js";
import { Z as Zap } from "./zap-CyG8UtsE.js";
import { U as Users } from "./users-DWlguTa_.js";
import { L as Link } from "./Link-B7ks7rBK.js";
import { E as External_link } from "./external-link-DmB8ndP3.js";
import { S as Shield } from "./shield-DTtuUihb.js";
import { R as Rotate_ccw } from "./rotate-ccw-D6Dm_efx.js";
import { C as Clock } from "./clock-B4fXoAi9.js";
import { C as Copy } from "./copy-D9_YDhto.js";
import { I as Icon } from "./Icon-Dal7PqKK.js";
import { K as Key } from "./key-C6JS4UCQ.js";
import { C as Circle_check } from "./circle-check-C59ZS7Zv.js";
import { A as Arrow_right } from "./arrow-right-CzynHiIo.js";
import { A as Arrow_left } from "./arrow-left-DbWnQR3t.js";
import { B as Book_open } from "./book-open-B6eMguqY.js";
import { F as File_text } from "./file-text-PXEtmXm9.js";
import { C as Check } from "./check-DK45Y9SJ.js";
import "axios";
import "@inertiajs/core";
import "clsx";
import "laravel-precognition";
import "tailwind-merge";
import "tailwind-variants";
import "./noop-BQS7OFs8.js";
import "./create-id-CUJmDlea.js";
import "style-to-object";
import "./scroll-lock-B1ibcKMx.js";
import "./watch.svelte-BcPoqckX.js";
import "./is-C6cm64pF.js";
import "tabbable";
import "./x-CnxrMbMn.js";
import "./circle-x-BQrhluGu.js";
import "./triangle-alert-CUvuB-HL.js";
import "./info-C3MCzlQW.js";
import "./safeMarkdownHtml-BkQMeMEI.js";
import "dompurify";
import "marked";
import "./download-n32NlL8c.js";
import "./minus-Dqkhcxt7.js";
import "./plus-DjyPLC7A.js";
function Wrench($$renderer, $$props) {
  const $$sanitized_props = sanitize_props($$props);
  const iconNode = [
    [
      "path",
      {
        "d": "M14.7 6.3a1 1 0 0 0 0 1.4l1.6 1.6a1 1 0 0 0 1.4 0l3.106-3.105c.32-.322.863-.22.983.218a6 6 0 0 1-8.259 7.057l-7.91 7.91a1 1 0 0 1-2.999-3l7.91-7.91a6 6 0 0 1 7.057-8.259c.438.12.54.662.219.984z"
      }
    ]
  ];
  Icon($$renderer, spread_props([
    { name: "wrench" },
    $$sanitized_props,
    {
      /**
       * @component @name Wrench
       * @description Lucide SVG icon component, renders SVG Element with children.
       *
       * @preview ![img](data:image/svg+xml;base64,PHN2ZyAgeG1sbnM9Imh0dHA6Ly93d3cudzMub3JnLzIwMDAvc3ZnIgogIHdpZHRoPSIyNCIKICBoZWlnaHQ9IjI0IgogIHZpZXdCb3g9IjAgMCAyNCAyNCIKICBmaWxsPSJub25lIgogIHN0cm9rZT0iIzAwMCIgc3R5bGU9ImJhY2tncm91bmQtY29sb3I6ICNmZmY7IGJvcmRlci1yYWRpdXM6IDJweCIKICBzdHJva2Utd2lkdGg9IjIiCiAgc3Ryb2tlLWxpbmVjYXA9InJvdW5kIgogIHN0cm9rZS1saW5lam9pbj0icm91bmQiCj4KICA8cGF0aCBkPSJNMTQuNyA2LjNhMSAxIDAgMCAwIDAgMS40bDEuNiAxLjZhMSAxIDAgMCAwIDEuNCAwbDMuMTA2LTMuMTA1Yy4zMi0uMzIyLjg2My0uMjIuOTgzLjIxOGE2IDYgMCAwIDEtOC4yNTkgNy4wNTdsLTcuOTEgNy45MWExIDEgMCAwIDEtMi45OTktM2w3LjkxLTcuOTFhNiA2IDAgMCAxIDcuMDU3LTguMjU5Yy40MzguMTIuNTQuNjYyLjIxOS45ODR6IiAvPgo8L3N2Zz4K) - https://lucide.dev/icons/wrench
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
function DemoGuide($$renderer, $$props) {
  $$renderer.component(($$renderer2) => {
    let {
      workflows = [],
      materials = [],
      can_control = false,
      preparation_url = null,
      persona_directory = null
    } = $$props;
    let selectedWorkflowKey = "application-review";
    let selectedStepIndex = 0;
    let operatorOpen = false;
    let directoryOpen = false;
    let preparationBusy = false;
    let referenceOpen = false;
    let selectedReference = null;
    let inlineTriggerBusy = false;
    let inlineTriggerStatus = "";
    let inlineTriggerError = "";
    let copiedField = "";
    const workflowList = derived(() => Array.isArray(workflows) ? workflows : []);
    const materialList = derived(() => Array.isArray(materials) ? materials : []);
    const selectedWorkflow = derived(() => workflowList().find((workflow) => workflow.key === selectedWorkflowKey) ?? workflowList()[0] ?? null);
    const selectedStep = derived(() => selectedWorkflow()?.steps?.[selectedStepIndex] ?? null);
    const activePersona = derived(() => selectedStep()?.actor_persona ?? null);
    const timelinePresets = [
      { key: "72h-reminder", label: "72h" },
      { key: "24h-reminder", label: "24h" },
      { key: "2h-reminder", label: "2h" },
      { key: "door-open", label: "Door Check-in" },
      { key: "live", label: "Live" },
      { key: "ended", label: "Ended" }
    ];
    const hasTimelineControls = derived(() => [
      "exam-scheduling-attendance",
      "scoring-results",
      "consultation-referral"
    ].includes(selectedWorkflow()?.key ?? ""));
    function selectWorkflow(event) {
      selectedWorkflowKey = event.currentTarget.value;
      selectedStepIndex = 0;
      inlineTriggerStatus = "";
      inlineTriggerError = "";
    }
    function selectStep(index) {
      if (!selectedWorkflow()?.steps || index < 0 || index >= selectedWorkflow().steps.length) return;
      selectedStepIndex = index;
    }
    async function triggerInlineOperation(action, preset = null) {
      if (inlineTriggerBusy || !can_control || !selectedWorkflow()) return;
      inlineTriggerBusy = true;
      inlineTriggerStatus = action === "time" ? `Warping session to ${preset}…` : `Seeding ${selectedWorkflow().title} fixture…`;
      inlineTriggerError = "";
      try {
        const previewRes = await fetch("/demo/preparation/preview", {
          method: "POST",
          headers: {
            "Content-Type": "application/json",
            "Accept": "application/json",
            "X-Requested-With": "XMLHttpRequest",
            ...csrfHeaders()
          },
          body: JSON.stringify({
            purpose: "demo",
            scenario: selectedWorkflow().scenario_key ?? selectedWorkflow().key,
            action: action === "time" ? "time" : "prepare",
            ...action === "time" && preset ? { preset } : {}
          })
        });
        const previewData = await previewRes.json();
        if (!previewRes.ok) {
          throw new Error(previewData.message ?? "Preview request failed.");
        }
        if (!previewData.operation?.confirm_url) {
          throw new Error("Server did not return a confirmation URL.");
        }
        const confirmRes = await fetch(previewData.operation.confirm_url, {
          method: "POST",
          headers: {
            "Content-Type": "application/json",
            "Accept": "application/json",
            "X-Requested-With": "XMLHttpRequest",
            ...csrfHeaders()
          },
          body: JSON.stringify({})
        });
        const confirmData = await confirmRes.json();
        if (!confirmRes.ok) {
          throw new Error(confirmData.message ?? "Confirmation failed.");
        }
        inlineTriggerStatus = `✓ Fixture ready: ${confirmData.message ?? "Operation committed successfully."}`;
        setTimeout(
          () => {
            if (inlineTriggerStatus.startsWith("✓")) {
              inlineTriggerStatus = "";
            }
          },
          5e3
        );
      } catch (err) {
        inlineTriggerError = err.message || "Operation could not be completed.";
        inlineTriggerStatus = "";
      } finally {
        inlineTriggerBusy = false;
      }
    }
    let $$settled = true;
    let $$inner_renderer;
    function $$render_inner($$renderer3) {
      head("vytzns", $$renderer3, ($$renderer4) => {
        $$renderer4.title(($$renderer5) => {
          $$renderer5.push(`<title>Development Meta Guide &amp; Workflow Deck · NEXIAM</title>`);
        });
      });
      $$renderer3.push(`<div class="min-h-screen bg-slate-950 text-slate-100">`);
      ToastManager($$renderer3, {});
      $$renderer3.push(`<!----> <main class="mx-auto w-full max-w-6xl px-4 py-7 sm:px-8 sm:py-10 lg:px-10"><header class="flex flex-col gap-5 border-b border-slate-800 pb-7 sm:flex-row sm:items-end sm:justify-between"><div class="min-w-0"><div class="flex items-center gap-2">`);
      Badge($$renderer3, {
        variant: "outline",
        class: "border-blue-400/40 bg-blue-500/10 text-blue-300",
        children: ($$renderer4) => {
          $$renderer4.push(`<!---->Developer Meta Guide`);
        },
        $$slots: { default: true }
      });
      $$renderer3.push(`<!----> `);
      if (can_control) {
        $$renderer3.push("<!--[0-->");
        Badge($$renderer3, {
          variant: "outline",
          class: "border-emerald-400/40 bg-emerald-500/10 text-emerald-300",
          children: ($$renderer4) => {
            Zap($$renderer4, { class: "mr-1 size-3" });
            $$renderer4.push(`<!----> Operator Controls Active`);
          },
          $$slots: { default: true }
        });
      } else {
        $$renderer3.push("<!--[-1-->");
      }
      $$renderer3.push(`<!--]--></div> <h1 class="mt-2 text-2xl font-bold tracking-tight text-white sm:text-3xl">System Workflows &amp; Development Deck</h1> <p class="mt-1.5 max-w-3xl text-sm leading-6 text-slate-400">Step-by-step guides, verified actor credentials, and pipeline controls for local development and testing.</p></div> <nav class="flex flex-wrap items-center gap-2" aria-label="Quick links">`);
      if (persona_directory) {
        $$renderer3.push("<!--[0-->");
        Button($$renderer3, {
          type: "button",
          variant: "outline",
          class: "min-h-9 gap-1.5 border-slate-700 bg-slate-900 text-xs font-semibold text-slate-200 hover:border-slate-500 hover:bg-slate-800",
          onclick: () => directoryOpen = true,
          children: ($$renderer4) => {
            Users($$renderer4, { class: "size-3.5 text-blue-300", "aria-hidden": "true" });
            $$renderer4.push(`<!----> Accounts Directory`);
          },
          $$slots: { default: true }
        });
      } else {
        $$renderer3.push("<!--[-1-->");
      }
      $$renderer3.push(`<!--]--> `);
      Link($$renderer3, {
        href: "/",
        class: "inline-flex min-h-9 items-center gap-1.5 rounded-lg border border-slate-700 bg-slate-900 px-3 py-1.5 text-xs font-semibold text-slate-200 transition hover:border-slate-500 hover:bg-slate-800 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-blue-400",
        children: ($$renderer4) => {
          External_link($$renderer4, { class: "size-3 text-slate-400", "aria-hidden": "true" });
          $$renderer4.push(`<!----> Open System`);
        },
        $$slots: { default: true }
      });
      $$renderer3.push(`<!----> `);
      Link($$renderer3, {
        href: "/login",
        class: "inline-flex min-h-9 items-center gap-1.5 rounded-lg border border-slate-700 bg-slate-900 px-3 py-1.5 text-xs font-semibold text-slate-200 transition hover:border-slate-500 hover:bg-slate-800 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-blue-400",
        children: ($$renderer4) => {
          $$renderer4.push(`<!---->Staff Sign In`);
        },
        $$slots: { default: true }
      });
      $$renderer3.push(`<!----> `);
      Link($$renderer3, {
        href: "/applicant/login",
        class: "inline-flex min-h-9 items-center gap-1.5 rounded-lg border border-slate-700 bg-slate-900 px-3 py-1.5 text-xs font-semibold text-slate-200 transition hover:border-slate-500 hover:bg-slate-800 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-blue-400",
        children: ($$renderer4) => {
          $$renderer4.push(`<!---->Applicant Portal`);
        },
        $$slots: { default: true }
      });
      $$renderer3.push(`<!----></nav></header> `);
      if (!can_control) {
        $$renderer3.push("<!--[0-->");
        $$renderer3.push(`<div class="mt-4 flex flex-col justify-between gap-3 rounded-xl border border-slate-800 bg-slate-900/40 p-3.5 text-xs text-slate-400 sm:flex-row sm:items-center"><div class="flex items-center gap-2">`);
        Shield($$renderer3, { class: "size-4 shrink-0 text-blue-400" });
        $$renderer3.push(`<!----> <span>Viewing as guest reader. Sign in as an authorized office lead (Guidance Counselor or Registrar Admin) to unlock active step personas, the accounts directory, and pipeline triggers.</span></div> `);
        Link($$renderer3, {
          href: "/login",
          class: "inline-flex min-h-8 shrink-0 items-center justify-center rounded-lg border border-slate-700 bg-slate-800 px-3 py-1 font-semibold text-slate-200 transition hover:bg-slate-700",
          children: ($$renderer4) => {
            $$renderer4.push(`<!---->Sign In as Staff →`);
          },
          $$slots: { default: true }
        });
        $$renderer3.push(`<!----></div>`);
      } else {
        $$renderer3.push("<!--[-1-->");
      }
      $$renderer3.push(`<!--]--> <section class="mt-6 rounded-xl border border-slate-800 bg-slate-900/50 p-4 sm:p-5" aria-labelledby="workflow-heading"><div class="grid gap-4 sm:grid-cols-[minmax(0,1.2fr)_minmax(0,1.8fr)] sm:items-center"><div>`);
      Label($$renderer3, {
        for: "workflow-selector",
        class: "text-xs font-semibold uppercase tracking-wider text-slate-400",
        children: ($$renderer4) => {
          $$renderer4.push(`<!---->Select Workflow Focus`);
        },
        $$slots: { default: true }
      });
      $$renderer3.push(`<!----> `);
      $$renderer3.select(
        {
          id: "workflow-selector",
          value: selectedWorkflow()?.key ?? "",
          onchange: selectWorkflow,
          class: "mt-1.5 flex min-h-10 w-full rounded-lg border border-slate-700 bg-slate-900 px-3 py-2 text-sm font-medium text-slate-100 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-blue-400"
        },
        ($$renderer4) => {
          $$renderer4.push(`<!--[-->`);
          const each_array = ensure_array_like(workflowList());
          for (let $$index = 0, $$length = each_array.length; $$index < $$length; $$index++) {
            let workflow = each_array[$$index];
            $$renderer4.option({ value: workflow.key }, ($$renderer5) => {
              $$renderer5.push(`${escape_html(workflow.title)}`);
            });
          }
          $$renderer4.push(`<!--]-->`);
        }
      );
      $$renderer3.push(`</div> `);
      if (selectedWorkflow()) {
        $$renderer3.push("<!--[0-->");
        $$renderer3.push(`<div class="border-t border-slate-800 pt-3 sm:border-t-0 sm:border-l sm:pl-5 sm:pt-0"><span class="text-xs font-semibold uppercase tracking-wider text-slate-400">Scenario Objective</span> <p class="mt-1 text-sm leading-6 text-slate-300">${escape_html(selectedWorkflow().summary)}</p></div>`);
      } else {
        $$renderer3.push("<!--[-1-->");
      }
      $$renderer3.push(`<!--]--></div> `);
      if (selectedWorkflow()) {
        $$renderer3.push("<!--[0-->");
        $$renderer3.push(`<div class="mt-4 border-t border-slate-800/80 pt-4"><div class="flex flex-wrap items-center justify-between gap-3"><div class="flex flex-wrap items-center gap-2"><span class="mr-1 inline-flex items-center gap-1 text-xs font-semibold uppercase tracking-wider text-slate-400">`);
        Zap($$renderer3, { class: "size-3.5 text-amber-400" });
        $$renderer3.push(`<!----> Pipeline:</span> `);
        if (can_control) {
          $$renderer3.push("<!--[0-->");
          Button($$renderer3, {
            type: "button",
            variant: "outline",
            size: "sm",
            class: "min-h-8 border-blue-500/40 bg-blue-500/10 text-xs font-medium text-blue-200 hover:bg-blue-500/20",
            disabled: inlineTriggerBusy,
            onclick: () => triggerInlineOperation("prepare"),
            children: ($$renderer4) => {
              $$renderer4.push(`<!---->⚡ Seed / Refresh Fixture`);
            },
            $$slots: { default: true }
          });
          $$renderer3.push(`<!----> `);
          Button($$renderer3, {
            type: "button",
            variant: "outline",
            size: "sm",
            class: "min-h-8 border-slate-700 bg-slate-900 text-xs font-medium text-slate-300 hover:bg-slate-800",
            disabled: inlineTriggerBusy,
            onclick: () => triggerInlineOperation("replace"),
            children: ($$renderer4) => {
              Rotate_ccw($$renderer4, { class: "mr-1 size-3 text-slate-400" });
              $$renderer4.push(`<!----> Retire &amp; Replace`);
            },
            $$slots: { default: true }
          });
          $$renderer3.push(`<!----> `);
          if (hasTimelineControls()) {
            $$renderer3.push("<!--[0-->");
            $$renderer3.push(`<div class="ml-2 flex items-center gap-1.5 rounded-lg border border-slate-800 bg-slate-950 px-2 py-1">`);
            Clock($$renderer3, { class: "size-3 text-indigo-300" });
            $$renderer3.push(`<!----> <span class="text-[11px] font-medium text-slate-400">Warp:</span> <!--[-->`);
            const each_array_1 = ensure_array_like(timelinePresets);
            for (let $$index_1 = 0, $$length = each_array_1.length; $$index_1 < $$length; $$index_1++) {
              let presetItem = each_array_1[$$index_1];
              $$renderer3.push(`<button type="button"${attr("disabled", inlineTriggerBusy, true)} class="rounded px-1.5 py-0.5 text-[11px] font-semibold text-slate-300 hover:bg-slate-800 hover:text-white disabled:opacity-50">${escape_html(presetItem.label)}</button>`);
            }
            $$renderer3.push(`<!--]--></div>`);
          } else {
            $$renderer3.push("<!--[-1-->");
          }
          $$renderer3.push(`<!--]-->`);
        } else {
          $$renderer3.push("<!--[-1-->");
          Link($$renderer3, {
            href: "/login",
            class: "inline-flex min-h-8 items-center gap-1.5 rounded-lg border border-blue-500/40 bg-blue-500/10 px-3 py-1 text-xs font-medium text-blue-200 hover:bg-blue-500/20",
            children: ($$renderer4) => {
              Zap($$renderer4, { class: "size-3 text-amber-400" });
              $$renderer4.push(`<!----> Operator Sign In to Trigger Browser Seed ↗`);
            },
            $$slots: { default: true }
          });
        }
        $$renderer3.push(`<!--]--></div> <div class="flex items-center gap-2">`);
        if (selectedWorkflow().cli_command) {
          $$renderer3.push("<!--[0-->");
          $$renderer3.push(`<button type="button" class="inline-flex min-h-8 items-center gap-1.5 rounded-md border border-slate-800 bg-slate-950 px-2.5 py-1 font-mono text-[11px] text-slate-400 transition hover:border-slate-700 hover:text-slate-200" title="Copy terminal command"><span>${escape_html(selectedWorkflow().cli_command)}</span> `);
          {
            $$renderer3.push("<!--[-1-->");
            Copy($$renderer3, { class: "size-3 text-slate-500" });
          }
          $$renderer3.push(`<!--]--></button>`);
        } else {
          $$renderer3.push("<!--[-1-->");
        }
        $$renderer3.push(`<!--]--> <button type="button" class="inline-flex min-h-8 items-center gap-1 text-xs text-slate-400 underline underline-offset-4 hover:text-slate-200">`);
        Wrench($$renderer3, { class: "size-3" });
        $$renderer3.push(`<!----> Drawer</button></div></div> `);
        if (inlineTriggerStatus) {
          $$renderer3.push("<!--[0-->");
          $$renderer3.push(`<p role="status" class="mt-2 text-xs font-medium text-blue-300 animate-pulse">${escape_html(inlineTriggerStatus)}</p>`);
        } else {
          $$renderer3.push("<!--[-1-->");
        }
        $$renderer3.push(`<!--]--> `);
        if (inlineTriggerError) {
          $$renderer3.push("<!--[0-->");
          $$renderer3.push(`<p role="alert" class="mt-2 text-xs font-medium text-rose-300">${escape_html(inlineTriggerError)}</p>`);
        } else {
          $$renderer3.push("<!--[-1-->");
        }
        $$renderer3.push(`<!--]--></div>`);
      } else {
        $$renderer3.push("<!--[-1-->");
      }
      $$renderer3.push(`<!--]--></section> `);
      if (selectedWorkflow()?.steps?.length) {
        $$renderer3.push("<!--[0-->");
        $$renderer3.push(`<section class="mt-6" aria-labelledby="steps-nav-heading"><h2 id="steps-nav-heading" class="sr-only">Activity Sequence</h2> <ol class="grid grid-cols-1 gap-2 sm:grid-cols-3 lg:grid-cols-3" aria-label="Workflow activity steps"><!--[-->`);
        const each_array_2 = ensure_array_like(selectedWorkflow().steps);
        for (let index = 0, $$length = each_array_2.length; index < $$length; index++) {
          let step = each_array_2[index];
          $$renderer3.push(`<li class="min-w-0"><button type="button"${attr("aria-current", selectedStepIndex === index ? "step" : void 0)}${attr_class(`flex min-h-14 w-full items-center gap-3 rounded-xl border p-3 text-left transition focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-blue-400 ${selectedStepIndex === index ? "border-blue-400/60 bg-blue-500/15 text-blue-100 shadow-sm" : "border-slate-800 bg-slate-900/40 text-slate-400 hover:border-slate-700 hover:bg-slate-900/80 hover:text-slate-200"}`)}><span${attr_class(`flex size-6 shrink-0 items-center justify-center rounded-lg text-xs font-bold tabular-nums ${selectedStepIndex === index ? "bg-blue-400 text-slate-950" : "bg-slate-800 text-slate-400"}`)}>${escape_html(index + 1)}</span> <span class="min-w-0 flex-1 truncate"><span class="block truncate text-[11px] font-semibold uppercase tracking-wider text-slate-400">${escape_html(step.actor)}</span> <span class="mt-0.5 block truncate text-sm font-medium text-slate-200">${escape_html(step.title)}</span></span></button></li>`);
        }
        $$renderer3.push(`<!--]--></ol></section> `);
        if (selectedStep()) {
          $$renderer3.push("<!--[0-->");
          $$renderer3.push(`<article class="mt-5 rounded-2xl border border-slate-800 bg-slate-900/30 p-5 sm:p-7" aria-live="polite"><div class="flex flex-col justify-between gap-3 border-b border-slate-800 pb-5 sm:flex-row sm:items-start"><div class="min-w-0"><div class="flex flex-wrap items-center gap-2"><span class="text-xs font-bold uppercase tracking-wider text-blue-400">Step ${escape_html(selectedStepIndex + 1)} of ${escape_html(selectedWorkflow().steps.length)}</span> <span class="text-slate-600">·</span> <span class="text-xs font-semibold text-slate-400">Actor: ${escape_html(selectedStep().actor)}</span></div> <h3 class="mt-1.5 text-xl font-bold tracking-tight text-white sm:text-2xl">${escape_html(selectedStep().title)}</h3></div> `);
          if (selectedStep().url) {
            $$renderer3.push("<!--[0-->");
            $$renderer3.push(`<a${attr("href", selectedStep().url)} target="_blank" rel="noopener noreferrer" class="inline-flex min-h-10 items-center gap-2 rounded-lg border border-blue-500/40 bg-blue-500/10 px-4 py-2 text-xs font-semibold text-blue-200 transition hover:bg-blue-500/20 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-blue-400"><span>Open ${escape_html(selectedStep().workspace_label || "Workspace")}</span> `);
            External_link($$renderer3, { class: "size-3.5", "aria-hidden": "true" });
            $$renderer3.push(`<!----></a>`);
          } else {
            $$renderer3.push("<!--[-1-->");
          }
          $$renderer3.push(`<!--]--></div> `);
          if (activePersona()) {
            $$renderer3.push("<!--[0-->");
            $$renderer3.push(`<div class="mt-5 rounded-xl border border-blue-500/30 bg-blue-950/20 p-4 text-xs"><div class="flex flex-wrap items-center justify-between gap-3"><div class="flex items-center gap-2"><div class="flex size-7 items-center justify-center rounded-lg bg-blue-500/20 text-blue-300">`);
            Key($$renderer3, { class: "size-3.5" });
            $$renderer3.push(`<!----></div> <div><span class="font-bold text-white">${escape_html(activePersona().role_label)}</span> <span class="ml-1.5 text-slate-400">(${escape_html(activePersona().name)})</span></div></div> <div class="flex flex-wrap items-center gap-2">`);
            if (activePersona().type === "staff") {
              $$renderer3.push("<!--[0-->");
              $$renderer3.push(`<button type="button" class="inline-flex items-center gap-1.5 rounded-md border border-slate-700 bg-slate-900 px-2.5 py-1 text-slate-200 hover:border-slate-500" title="Copy email"><span class="font-mono text-[11px]">${escape_html(activePersona().email)}</span> `);
              {
                $$renderer3.push("<!--[-1-->");
                Copy($$renderer3, { class: "size-3 text-slate-400" });
              }
              $$renderer3.push(`<!--]--></button> <button type="button" class="inline-flex items-center gap-1.5 rounded-md border border-slate-700 bg-slate-900 px-2.5 py-1 text-slate-200 hover:border-slate-500" title="Copy default password"><span class="text-slate-400">PW:</span> <span class="font-mono text-[11px]">${escape_html(activePersona().password)}</span> `);
              {
                $$renderer3.push("<!--[-1-->");
                Copy($$renderer3, { class: "size-3 text-slate-400" });
              }
              $$renderer3.push(`<!--]--></button>`);
            } else {
              $$renderer3.push("<!--[-1-->");
              $$renderer3.push(`<button type="button" class="inline-flex items-center gap-1.5 rounded-md border border-slate-700 bg-slate-900 px-2.5 py-1 text-slate-200 hover:border-slate-500" title="Copy access key"><span class="text-slate-400">Key:</span> <span class="font-mono text-[11px]">${escape_html(activePersona().access_key)}</span> `);
              {
                $$renderer3.push("<!--[-1-->");
                Copy($$renderer3, { class: "size-3 text-slate-400" });
              }
              $$renderer3.push(`<!--]--></button> <button type="button" class="inline-flex items-center gap-1.5 rounded-md border border-slate-700 bg-slate-900 px-2.5 py-1 text-slate-200 hover:border-slate-500" title="Copy birthdate"><span class="text-slate-400">Birthdate:</span> <span class="font-mono text-[11px]">${escape_html(activePersona().birthdate)}</span> `);
              {
                $$renderer3.push("<!--[-1-->");
                Copy($$renderer3, { class: "size-3 text-slate-400" });
              }
              $$renderer3.push(`<!--]--></button>`);
            }
            $$renderer3.push(`<!--]--> <a${attr("href", activePersona().login_url)} target="_blank" rel="noopener noreferrer" class="ml-1 inline-flex items-center gap-1 font-semibold text-blue-300 underline underline-offset-4 hover:text-blue-200">Sign In ↗</a></div></div></div>`);
          } else {
            $$renderer3.push("<!--[-1-->");
          }
          $$renderer3.push(`<!--]--> <div class="mt-6 grid gap-5 sm:grid-cols-1 lg:grid-cols-3"><div class="rounded-xl border border-slate-800 bg-slate-900/60 p-4"><span class="flex items-center gap-1.5 text-xs font-bold uppercase tracking-wider text-slate-400">`);
          Circle_check($$renderer3, { class: "size-3.5 text-blue-400" });
          $$renderer3.push(`<!----> Prerequisites</span> <p class="mt-3 text-xs leading-6 text-slate-300">${escape_html(selectedStep().before)}</p></div> <div class="rounded-xl border border-slate-800 bg-slate-900/60 p-4 lg:col-span-1"><span class="flex items-center gap-1.5 text-xs font-bold uppercase tracking-wider text-slate-400">`);
          Zap($$renderer3, { class: "size-3.5 text-amber-400" });
          $$renderer3.push(`<!----> Required Actions</span> <ol class="mt-3 space-y-2 text-xs leading-6 text-slate-200"><!--[-->`);
          const each_array_3 = ensure_array_like(selectedStep().actions ?? []);
          for (let index = 0, $$length = each_array_3.length; index < $$length; index++) {
            let action = each_array_3[index];
            $$renderer3.push(`<li class="flex items-start gap-2"><span class="mt-0.5 inline-flex size-4 shrink-0 items-center justify-center rounded-full bg-slate-800 text-[10px] font-bold text-slate-400">${escape_html(index + 1)}</span> <span>${escape_html(action)}</span></li>`);
          }
          $$renderer3.push(`<!--]--></ol></div> <div class="rounded-xl border border-slate-800 bg-slate-900/60 p-4"><span class="flex items-center gap-1.5 text-xs font-bold uppercase tracking-wider text-slate-400">`);
          Arrow_right($$renderer3, { class: "size-3.5 text-emerald-400" });
          $$renderer3.push(`<!----> Next Handoff</span> <p class="mt-3 text-xs leading-6 text-slate-300">${escape_html(selectedStep().expected)}</p> <div class="mt-3 border-t border-slate-800/80 pt-2.5"><span class="text-[11px] font-semibold text-slate-400">Hands off to:</span> <span class="ml-1 text-xs font-medium text-emerald-300">${escape_html(selectedStep().next_actor)}</span></div></div></div> <div class="mt-6 flex flex-wrap items-center justify-between gap-3 border-t border-slate-800 pt-4"><span class="text-xs text-slate-500">Step ${escape_html(selectedStepIndex + 1)} of ${escape_html(selectedWorkflow().steps.length)}</span> <div class="flex gap-2">`);
          Button($$renderer3, {
            type: "button",
            variant: "outline",
            size: "sm",
            class: "min-h-9 gap-1.5 border-slate-700 bg-slate-900 text-xs font-medium text-slate-200 hover:bg-slate-800",
            disabled: selectedStepIndex <= 0,
            onclick: () => selectStep(selectedStepIndex - 1),
            children: ($$renderer4) => {
              Arrow_left($$renderer4, { class: "size-3.5", "aria-hidden": "true" });
              $$renderer4.push(`<!----> Previous Step`);
            },
            $$slots: { default: true }
          });
          $$renderer3.push(`<!----> `);
          Button($$renderer3, {
            type: "button",
            variant: "outline",
            size: "sm",
            class: "min-h-9 gap-1.5 border-slate-700 bg-slate-900 text-xs font-medium text-slate-200 hover:bg-slate-800",
            disabled: selectedStepIndex >= selectedWorkflow().steps.length - 1,
            onclick: () => selectStep(selectedStepIndex + 1),
            children: ($$renderer4) => {
              $$renderer4.push(`<!---->Next Step `);
              Arrow_right($$renderer4, { class: "size-3.5", "aria-hidden": "true" });
              $$renderer4.push(`<!---->`);
            },
            $$slots: { default: true }
          });
          $$renderer3.push(`<!----></div></div></article>`);
        } else {
          $$renderer3.push("<!--[-1-->");
        }
        $$renderer3.push(`<!--]-->`);
      } else {
        $$renderer3.push("<!--[-1-->");
      }
      $$renderer3.push(`<!--]--> `);
      if (selectedWorkflow()?.diagrams?.length || materialList().length) {
        $$renderer3.push("<!--[0-->");
        $$renderer3.push(`<section class="mt-8 grid gap-6 border-t border-slate-800 pt-6 sm:grid-cols-2" aria-label="Reference Materials"><div><h2 class="flex items-center gap-2 text-xs font-bold uppercase tracking-wider text-slate-400">`);
        Book_open($$renderer3, { class: "size-3.5 text-blue-400" });
        $$renderer3.push(`<!----> Architectural Diagrams</h2> `);
        if (selectedWorkflow()?.diagrams?.length) {
          $$renderer3.push("<!--[0-->");
          $$renderer3.push(`<ul class="mt-3 space-y-2"><!--[-->`);
          const each_array_4 = ensure_array_like(selectedWorkflow().diagrams);
          for (let $$index_4 = 0, $$length = each_array_4.length; $$index_4 < $$length; $$index_4++) {
            let diagram = each_array_4[$$index_4];
            $$renderer3.push(`<li class="flex items-center justify-between rounded-lg border border-slate-800/80 bg-slate-900/40 p-2.5 text-xs"><button type="button" class="truncate font-medium text-blue-300 hover:underline">${escape_html(diagram.title)}</button> <span class="ml-2 font-mono text-[10px] text-slate-500">${escape_html(diagram.id)}</span></li>`);
          }
          $$renderer3.push(`<!--]--></ul>`);
        } else {
          $$renderer3.push("<!--[-1-->");
        }
        $$renderer3.push(`<!--]--></div> <div><h2 class="flex items-center gap-2 text-xs font-bold uppercase tracking-wider text-slate-400">`);
        File_text($$renderer3, { class: "size-3.5 text-indigo-400" });
        $$renderer3.push(`<!----> Research References</h2> `);
        if (materialList().length) {
          $$renderer3.push("<!--[0-->");
          $$renderer3.push(`<ul class="mt-3 space-y-2"><!--[-->`);
          const each_array_5 = ensure_array_like(materialList());
          for (let $$index_5 = 0, $$length = each_array_5.length; $$index_5 < $$length; $$index_5++) {
            let material = each_array_5[$$index_5];
            $$renderer3.push(`<li class="flex items-center justify-between rounded-lg border border-slate-800/80 bg-slate-900/40 p-2.5 text-xs"><button type="button" class="truncate font-medium text-blue-300 hover:underline">${escape_html(material.title)}</button> <span class="ml-2 text-[10px] text-slate-500">${escape_html(material.id)}</span></li>`);
          }
          $$renderer3.push(`<!--]--></ul>`);
        } else {
          $$renderer3.push("<!--[-1-->");
        }
        $$renderer3.push(`<!--]--></div></section>`);
      } else {
        $$renderer3.push("<!--[-1-->");
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
      $$renderer3.push(`<!----> `);
      if (persona_directory) {
        $$renderer3.push("<!--[0-->");
        if (Dialog) {
          $$renderer3.push("<!--[-->");
          Dialog($$renderer3, {
            get open() {
              return directoryOpen;
            },
            set open($$value) {
              directoryOpen = $$value;
              $$settled = false;
            },
            children: ($$renderer4) => {
              if (Dialog_content) {
                $$renderer4.push("<!--[-->");
                Dialog_content($$renderer4, {
                  class: "max-h-[85dvh] max-w-3xl overflow-y-auto border-slate-800 bg-slate-950 p-6 text-slate-100 sm:rounded-2xl",
                  children: ($$renderer5) => {
                    if (Dialog_header) {
                      $$renderer5.push("<!--[-->");
                      Dialog_header($$renderer5, {
                        children: ($$renderer6) => {
                          $$renderer6.push(`<div class="flex items-center gap-2"><div class="flex size-8 items-center justify-center rounded-lg bg-blue-500/20 text-blue-300">`);
                          Users($$renderer6, { class: "size-4" });
                          $$renderer6.push(`<!----></div> <div>`);
                          if (Dialog_title) {
                            $$renderer6.push("<!--[-->");
                            Dialog_title($$renderer6, {
                              class: "text-lg font-bold text-white",
                              children: ($$renderer7) => {
                                $$renderer7.push(`<!---->Development Accounts Directory`);
                              },
                              $$slots: { default: true }
                            });
                            $$renderer6.push("<!--]-->");
                          } else {
                            $$renderer6.push("<!--[!-->");
                            $$renderer6.push("<!--]-->");
                          }
                          $$renderer6.push(` `);
                          if (Dialog_description) {
                            $$renderer6.push("<!--[-->");
                            Dialog_description($$renderer6, {
                              class: "text-xs text-slate-400",
                              children: ($$renderer7) => {
                                $$renderer7.push(`<!---->Verified seeded testing accounts for each domain and workflow role.`);
                              },
                              $$slots: { default: true }
                            });
                            $$renderer6.push("<!--]-->");
                          } else {
                            $$renderer6.push("<!--[!-->");
                            $$renderer6.push("<!--]-->");
                          }
                          $$renderer6.push(`</div></div>`);
                        },
                        $$slots: { default: true }
                      });
                      $$renderer5.push("<!--]-->");
                    } else {
                      $$renderer5.push("<!--[!-->");
                      $$renderer5.push("<!--]-->");
                    }
                    $$renderer5.push(` <div class="mt-5 space-y-6"><div><h4 class="text-xs font-bold uppercase tracking-wider text-blue-400">Staff Personas (Default Password: password)</h4> <div class="mt-3 divide-y divide-slate-800 rounded-xl border border-slate-800 bg-slate-900/50"><!--[-->`);
                    const each_array_6 = ensure_array_like(persona_directory.staff ?? []);
                    for (let $$index_6 = 0, $$length = each_array_6.length; $$index_6 < $$length; $$index_6++) {
                      let staffItem = each_array_6[$$index_6];
                      $$renderer5.push(`<div class="flex flex-col justify-between gap-2 p-3 sm:flex-row sm:items-center"><div class="min-w-0"><div class="flex items-center gap-2"><span class="text-xs font-bold text-white">${escape_html(staffItem.role_label)}</span> `);
                      Badge($$renderer5, {
                        variant: "outline",
                        class: "border-slate-700 text-[10px] text-slate-400",
                        children: ($$renderer6) => {
                          $$renderer6.push(`<!---->${escape_html(staffItem.office)}`);
                        },
                        $$slots: { default: true }
                      });
                      $$renderer5.push(`<!----></div> <p class="mt-0.5 text-xs text-slate-400">${escape_html(staffItem.name)}</p></div> <div class="flex items-center gap-2"><button type="button" class="inline-flex items-center gap-1 rounded border border-slate-700 bg-slate-950 px-2 py-0.5 font-mono text-[11px] text-slate-300 hover:border-slate-500" title="Copy Email"><span>${escape_html(staffItem.email)}</span> `);
                      if (copiedField === `staff-${staffItem.key}`) {
                        $$renderer5.push("<!--[0-->");
                        Check($$renderer5, { class: "size-3 text-emerald-400" });
                      } else {
                        $$renderer5.push("<!--[-1-->");
                        Copy($$renderer5, { class: "size-3 text-slate-500" });
                      }
                      $$renderer5.push(`<!--]--></button> <a${attr("href", staffItem.login_url)} target="_blank" rel="noopener noreferrer" class="rounded border border-blue-500/30 bg-blue-500/10 px-2.5 py-0.5 text-[11px] font-semibold text-blue-300 hover:bg-blue-500/20">Sign In ↗</a></div></div>`);
                    }
                    $$renderer5.push(`<!--]--></div></div> <div><h4 class="text-xs font-bold uppercase tracking-wider text-emerald-400">Applicant Portals (Access Key + Birthdate)</h4> <div class="mt-3 divide-y divide-slate-800 rounded-xl border border-slate-800 bg-slate-900/50"><!--[-->`);
                    const each_array_7 = ensure_array_like(persona_directory.applicants ?? []);
                    for (let $$index_7 = 0, $$length = each_array_7.length; $$index_7 < $$length; $$index_7++) {
                      let applicantItem = each_array_7[$$index_7];
                      $$renderer5.push(`<div class="flex flex-col justify-between gap-2 p-3 sm:flex-row sm:items-center"><div class="min-w-0"><span class="text-xs font-bold text-white">${escape_html(applicantItem.role_label)}</span> <p class="mt-0.5 text-xs text-slate-400">${escape_html(applicantItem.description)}</p></div> <div class="flex items-center gap-2"><button type="button" class="inline-flex items-center gap-1 rounded border border-slate-700 bg-slate-950 px-2 py-0.5 font-mono text-[11px] text-slate-300 hover:border-slate-500" title="Copy Access Key"><span class="text-slate-500">Key:</span> <span>${escape_html(applicantItem.access_key)}</span> `);
                      if (copiedField === `app-key-${applicantItem.key}`) {
                        $$renderer5.push("<!--[0-->");
                        Check($$renderer5, { class: "size-3 text-emerald-400" });
                      } else {
                        $$renderer5.push("<!--[-1-->");
                        Copy($$renderer5, { class: "size-3 text-slate-500" });
                      }
                      $$renderer5.push(`<!--]--></button> <span class="font-mono text-[11px] text-slate-500">${escape_html(applicantItem.birthdate)}</span> <a${attr("href", applicantItem.login_url)} target="_blank" rel="noopener noreferrer" class="rounded border border-emerald-500/30 bg-emerald-500/10 px-2.5 py-0.5 text-[11px] font-semibold text-emerald-300 hover:bg-emerald-500/20">Portal ↗</a></div></div>`);
                    }
                    $$renderer5.push(`<!--]--></div></div></div> `);
                    if (Dialog_footer) {
                      $$renderer5.push("<!--[-->");
                      Dialog_footer($$renderer5, {
                        class: "mt-6",
                        children: ($$renderer6) => {
                          Button($$renderer6, {
                            type: "button",
                            variant: "outline",
                            class: "border-slate-700 bg-slate-900 text-xs text-slate-200 hover:bg-slate-800",
                            onclick: () => directoryOpen = false,
                            children: ($$renderer7) => {
                              $$renderer7.push(`<!---->Close Directory`);
                            },
                            $$slots: { default: true }
                          });
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
            },
            $$slots: { default: true }
          });
          $$renderer3.push("<!--]-->");
        } else {
          $$renderer3.push("<!--[!-->");
          $$renderer3.push("<!--]-->");
        }
      } else {
        $$renderer3.push("<!--[-1-->");
      }
      $$renderer3.push(`<!--]--> `);
      if (can_control) {
        $$renderer3.push("<!--[0-->");
        if (Dialog) {
          $$renderer3.push("<!--[-->");
          Dialog($$renderer3, {
            get open() {
              return operatorOpen;
            },
            set open($$value) {
              operatorOpen = $$value;
              $$settled = false;
            },
            children: ($$renderer4) => {
              if (Dialog_content) {
                $$renderer4.push("<!--[-->");
                Dialog_content($$renderer4, {
                  showCloseButton: !preparationBusy,
                  class: "fixed inset-y-0 right-0 left-auto top-0 z-[75] flex h-dvh w-full max-w-none translate-x-0 translate-y-0 flex-col gap-0 overflow-hidden rounded-none border-y-0 border-r-0 border-l border-slate-800 bg-slate-950 p-0 text-slate-100 shadow-2xl sm:max-w-2xl",
                  onEscapeKeydown: (event) => {
                    if (preparationBusy) event.preventDefault();
                  },
                  onInteractOutside: (event) => {
                    if (preparationBusy) event.preventDefault();
                  },
                  children: ($$renderer5) => {
                    if (Dialog_header) {
                      $$renderer5.push("<!--[-->");
                      Dialog_header($$renderer5, {
                        class: "shrink-0 border-b border-slate-800 p-5 pr-14 sm:p-6 sm:pr-16",
                        children: ($$renderer6) => {
                          if (Dialog_title) {
                            $$renderer6.push("<!--[-->");
                            Dialog_title($$renderer6, {
                              class: "text-lg font-semibold text-white",
                              children: ($$renderer7) => {
                                $$renderer7.push(`<!---->Granular Preparation Controls`);
                              },
                              $$slots: { default: true }
                            });
                            $$renderer6.push("<!--]-->");
                          } else {
                            $$renderer6.push("<!--[!-->");
                            $$renderer6.push("<!--]-->");
                          }
                          $$renderer6.push(` `);
                          if (Dialog_description) {
                            $$renderer6.push("<!--[-->");
                            Dialog_description($$renderer6, {
                              class: "mt-1 text-xs leading-5 text-slate-400",
                              children: ($$renderer7) => {
                                $$renderer7.push(`<!---->Configure exact practice fixture sets, timeline offsets, and review audit history.`);
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
                    $$renderer5.push(` <div class="min-h-0 flex-1 overflow-y-auto p-5 sm:p-6">`);
                    PracticePreparationPanel($$renderer5, {
                      purpose: "demo",
                      scenario: selectedWorkflow()?.key ?? "application-review",
                      active: operatorOpen,
                      can_control,
                      onbusychange: (busy) => {
                        preparationBusy = busy;
                      }
                    });
                    $$renderer5.push(`<!----></div> <div class="shrink-0 border-t border-slate-800 p-4 text-right sm:px-6">`);
                    Button($$renderer5, {
                      type: "button",
                      variant: "outline",
                      class: "min-h-9 border-slate-700 bg-slate-900 text-xs text-slate-100 hover:bg-slate-800",
                      disabled: preparationBusy,
                      onclick: () => operatorOpen = false,
                      children: ($$renderer6) => {
                        $$renderer6.push(`<!---->Close Drawer`);
                      },
                      $$slots: { default: true }
                    });
                    $$renderer5.push(`<!----></div>`);
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
      } else {
        $$renderer3.push("<!--[-1-->");
      }
      $$renderer3.push(`<!--]-->`);
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
  DemoGuide as default
};
