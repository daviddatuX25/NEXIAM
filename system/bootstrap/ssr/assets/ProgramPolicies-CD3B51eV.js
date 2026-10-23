import { h as head, e as escape_html, a as ensure_array_like } from "../app.js";
import { H as HomeLayout } from "./HomeLayout-Cd36uhjl.js";
import { P as PolicyBulletinCard } from "./PolicyBulletinCard-C1afddWy.js";
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
function ProgramPolicies($$renderer, $$props) {
  $$renderer.component(($$renderer2) => {
    let { program, policies = [], institution = {}, alerts = [] } = $$props;
    head("13fv4f1", $$renderer2, ($$renderer3) => {
      $$renderer3.title(($$renderer4) => {
        $$renderer4.push(`<title>${escape_html(program.name)} policies — ${escape_html(institution.name ?? "Public policies")}</title>`);
      });
    });
    HomeLayout($$renderer2, {
      institution,
      alerts,
      children: ($$renderer3) => {
        $$renderer3.push(`<main class="mx-auto w-full max-w-7xl px-4 py-10 sm:px-6 sm:py-14 lg:px-8"><nav aria-label="Breadcrumb" class="mb-6 text-sm">`);
        Link($$renderer3, {
          href: `/programs/${encodeURIComponent(program.code)}`,
          class: "text-primary underline-offset-4 hover:underline",
          children: ($$renderer4) => {
            $$renderer4.push(`<!---->${escape_html(program.name)}`);
          },
          $$slots: { default: true }
        });
        $$renderer3.push(`<!----><span class="text-muted-foreground px-2" aria-hidden="true">/</span><span aria-current="page">Public policies</span></nav> <header class="max-w-3xl"><span class="bg-primary/10 text-primary inline-flex max-w-full rounded-md px-3 py-1 text-sm font-medium break-words">${escape_html(program.code)}</span> <h1 class="mt-2 text-3xl font-semibold tracking-tight break-words sm:text-4xl">${escape_html(program.name)} public policies</h1> <p class="text-muted-foreground mt-3 text-base leading-7">Published documents issued for this active program.</p> <div class="mt-4">`);
        Link($$renderer3, {
          href: "/policies",
          class: "text-primary inline-flex min-h-[44px] items-center text-sm underline underline-offset-4",
          children: ($$renderer4) => {
            $$renderer4.push(`<!---->View university-wide policies`);
          },
          $$slots: { default: true }
        });
        $$renderer3.push(`<!----></div></header> `);
        if (policies.length > 0) {
          $$renderer3.push("<!--[0-->");
          $$renderer3.push(`<div class="mt-8 grid grid-cols-1 gap-5 sm:grid-cols-2 lg:grid-cols-3"><!--[-->`);
          const each_array = ensure_array_like(policies);
          for (let $$index = 0, $$length = each_array.length; $$index < $$length; $$index++) {
            let policy = each_array[$$index];
            PolicyBulletinCard($$renderer3, { policy });
          }
          $$renderer3.push(`<!--]--></div>`);
        } else {
          $$renderer3.push("<!--[-1-->");
          $$renderer3.push(`<p role="status" class="border-border text-muted-foreground mt-8 max-w-3xl rounded-lg border border-dashed p-6 text-sm leading-6">No public policies have been published for this program.</p>`);
        }
        $$renderer3.push(`<!--]--></main>`);
      }
    });
  });
}
export {
  ProgramPolicies as default
};
