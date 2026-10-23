import { h as head, d as derived } from "../app.js";
import PracticePreparationPanel from "./PracticePreparationPanel-CFZWv1Kg.js";
import { L as Link } from "./Link-B7ks7rBK.js";
import "axios";
import "@inertiajs/core";
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
function PracticePreparation($$renderer, $$props) {
  $$renderer.component(($$renderer2) => {
    let {
      purpose = "demo",
      scenario = "application-review",
      scenarios = [],
      context = {},
      sets = [],
      selected_set = null,
      materials = null,
      prerequisites = [],
      actions = [],
      preserved = [],
      endpoints = {}
    } = $$props;
    const initial = derived(() => ({
      purpose,
      scenario,
      scenarios,
      context,
      sets,
      selected_set,
      materials,
      prerequisites,
      actions,
      preserved,
      endpoints
    }));
    head("1x50ygp", $$renderer2, ($$renderer3) => {
      $$renderer3.title(($$renderer4) => {
        $$renderer4.push(`<title>Practice preparation · NEXIAM</title>`);
      });
    });
    $$renderer2.push(`<div class="min-h-screen bg-slate-950 text-slate-100"><main class="mx-auto w-full max-w-4xl px-4 py-7 sm:px-8 sm:py-10"><header class="border-b border-slate-800 pb-6"><h1 class="text-3xl font-semibold tracking-tight text-white sm:text-4xl">Practice preparation</h1> <nav class="mt-4 flex flex-wrap gap-x-5 gap-y-2 text-sm" aria-label="Practice references">`);
    Link($$renderer2, {
      href: "/demo",
      class: "inline-flex min-h-11 items-center text-blue-300 underline underline-offset-4 hover:text-blue-200 focus-visible:ring-2 focus-visible:ring-blue-400",
      children: ($$renderer3) => {
        $$renderer3.push(`<!---->Workflow guide`);
      },
      $$slots: { default: true }
    });
    $$renderer2.push(`<!----> `);
    Link($$renderer2, {
      href: "/demo/research/facilitator",
      class: "inline-flex min-h-11 items-center text-blue-300 underline underline-offset-4 hover:text-blue-200 focus-visible:ring-2 focus-visible:ring-blue-400",
      children: ($$renderer3) => {
        $$renderer3.push(`<!---->Facilitator reference`);
      },
      $$slots: { default: true }
    });
    $$renderer2.push(`<!----></nav></header> <div class="mt-6">`);
    PracticePreparationPanel($$renderer2, {
      purpose,
      scenario,
      initial: initial(),
      can_control: true,
      index_url: endpoints.index ?? "/demo/preparation"
    });
    $$renderer2.push(`<!----></div></main></div>`);
  });
}
export {
  PracticePreparation as default
};
