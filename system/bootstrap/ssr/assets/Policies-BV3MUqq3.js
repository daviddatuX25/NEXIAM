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
function Policies($$renderer, $$props) {
  $$renderer.component(($$renderer2) => {
    let { institution = {}, alerts = [], publicPolicies = [] } = $$props;
    head("1b8mged", $$renderer2, ($$renderer3) => {
      $$renderer3.title(($$renderer4) => {
        $$renderer4.push(`<title>Public policies — ${escape_html(institution.name ?? "Institution")}</title>`);
      });
    });
    HomeLayout($$renderer2, {
      institution,
      alerts,
      children: ($$renderer3) => {
        $$renderer3.push(`<div class="mx-auto w-full max-w-7xl px-4 py-12 sm:px-6 sm:py-16 lg:px-8"><header class="max-w-[72ch]">`);
        Link($$renderer3, {
          href: "/",
          class: "text-primary mb-4 inline-flex min-h-[44px] items-center text-sm font-semibold underline underline-offset-4",
          children: ($$renderer4) => {
            $$renderer4.push(`<!---->Back to Home`);
          },
          $$slots: { default: true }
        });
        $$renderer3.push(`<!----> <h1 class="text-foreground text-4xl font-semibold tracking-tight sm:text-5xl">Public policies</h1> <p class="text-muted-foreground mt-4 text-base leading-7">Current canonical policies available for public reading.</p></header> `);
        if (publicPolicies.length > 0) {
          $$renderer3.push("<!--[0-->");
          $$renderer3.push(`<div class="mt-8 grid grid-cols-1 gap-5 sm:grid-cols-2 lg:grid-cols-3"><!--[-->`);
          const each_array = ensure_array_like(publicPolicies);
          for (let $$index = 0, $$length = each_array.length; $$index < $$length; $$index++) {
            let policy = each_array[$$index];
            PolicyBulletinCard($$renderer3, { policy });
          }
          $$renderer3.push(`<!--]--></div>`);
        } else {
          $$renderer3.push("<!--[-1-->");
          $$renderer3.push(`<p role="status" class="border-border text-muted-foreground mt-8 max-w-3xl rounded-lg border border-dashed p-6 text-sm leading-6">No active public policy is available at this time.</p>`);
        }
        $$renderer3.push(`<!--]--></div>`);
      }
    });
  });
}
export {
  Policies as default
};
