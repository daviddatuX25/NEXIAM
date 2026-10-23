import { h as head, e as escape_html } from "../app.js";
import { H as HomeLayout } from "./HomeLayout-Cd36uhjl.js";
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
function Policy($$renderer, $$props) {
  $$renderer.component(($$renderer2) => {
    let { policy, owningProgram = null, institution = {}, alerts = [] } = $$props;
    head("lhak7h", $$renderer2, ($$renderer3) => {
      $$renderer3.title(($$renderer4) => {
        $$renderer4.push(`<title>${escape_html(policy.title)} — ${escape_html(institution.name ?? "Public policy")}</title>`);
      });
    });
    HomeLayout($$renderer2, {
      institution,
      alerts,
      children: ($$renderer3) => {
        $$renderer3.push(`<main class="mx-auto w-full max-w-4xl px-4 py-10 sm:px-6 sm:py-14 lg:px-8"><nav aria-label="Breadcrumb" class="mb-6 text-sm break-words">`);
        Link($$renderer3, {
          href: owningProgram ? `/programs/${encodeURIComponent(owningProgram.code)}/policies` : "/policies",
          class: "text-primary inline-flex min-h-[44px] items-center underline-offset-4 hover:underline",
          children: ($$renderer4) => {
            $$renderer4.push(`<!---->${escape_html(owningProgram ? `${owningProgram.name} public policies` : "Public policies")}`);
          },
          $$slots: { default: true }
        });
        $$renderer3.push(`<!----> <span class="text-muted-foreground px-2" aria-hidden="true">/</span> <span aria-current="page">Policy</span></nav> <article class="border-border/80 bg-card rounded-xl border p-6 shadow-xs sm:p-10"><header class="border-border/60 border-b pb-5"><p class="text-muted-foreground text-sm break-words">${escape_html(owningProgram ? `${owningProgram.code} · program policy` : "General public policy")} · ${escape_html(policy.publisher_label)}</p> <h1 class="text-foreground mt-2 text-3xl font-semibold tracking-tight break-words sm:text-4xl">${escape_html(policy.title)}</h1> <p class="text-muted-foreground mt-3 text-sm break-words">Published ${escape_html(policy.published_at ? new Date(policy.published_at).toLocaleDateString() : "")} · Version ${escape_html(policy.revision)}</p></header> <div class="text-foreground/90 py-8 text-base leading-7 break-words whitespace-pre-wrap">${escape_html(policy.content)}</div> <footer class="border-border/60 border-t pt-5">`);
        Link($$renderer3, {
          href: owningProgram ? `/programs/${encodeURIComponent(owningProgram.code)}/policies` : "/policies",
          class: "text-primary inline-flex min-h-[44px] items-center underline underline-offset-4",
          children: ($$renderer4) => {
            $$renderer4.push(`<!---->Back to ${escape_html(owningProgram ? owningProgram.name : "public")} policies`);
          },
          $$slots: { default: true }
        });
        $$renderer3.push(`<!----></footer></article></main>`);
      }
    });
  });
}
export {
  Policy as default
};
