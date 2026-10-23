import { h as head, e as escape_html, b as attr, d as derived } from "../app.js";
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
function Announcement($$renderer, $$props) {
  $$renderer.component(($$renderer2) => {
    let { institution = {}, alerts = [], announcement, program = null } = $$props;
    const announcementsHref = derived(() => program ? `/programs/${encodeURIComponent(program.code)}/announcements` : "/announcements");
    const announcementsLabel = derived(() => program ? `All ${program.code} announcements` : "All announcements");
    head("abubqc", $$renderer2, ($$renderer3) => {
      $$renderer3.title(($$renderer4) => {
        $$renderer4.push(`<title>${escape_html(announcement.title)} — ${escape_html(institution.name ?? "Institution")}</title>`);
      });
    });
    HomeLayout($$renderer2, {
      institution,
      alerts,
      children: ($$renderer3) => {
        $$renderer3.push(`<article class="mx-auto w-full max-w-7xl px-4 py-12 sm:px-6 sm:py-16 lg:px-8"><div class="max-w-[72ch]">`);
        Link($$renderer3, {
          href: announcementsHref(),
          class: "text-primary inline-flex min-h-[44px] items-center text-sm font-semibold underline underline-offset-4",
          children: ($$renderer4) => {
            $$renderer4.push(`<!---->${escape_html(announcementsLabel())}`);
          },
          $$slots: { default: true }
        });
        $$renderer3.push(`<!----> <p class="text-primary mt-6 text-sm font-semibold">${escape_html(announcement.publisher_label)}</p> <h1 class="text-foreground mt-3 text-4xl font-semibold tracking-tight break-words sm:text-5xl">${escape_html(announcement.title)}</h1> `);
        if (announcement.published_at) {
          $$renderer3.push("<!--[0-->");
          $$renderer3.push(`<p class="text-muted-foreground mt-4 text-sm"><time${attr("datetime", announcement.published_at)}>${escape_html(new Date(announcement.published_at).toLocaleString())}</time></p>`);
        } else {
          $$renderer3.push("<!--[-1-->");
        }
        $$renderer3.push(`<!--]--> <p class="text-foreground mt-8 text-lg leading-8 break-words whitespace-pre-wrap">${escape_html(announcement.summary)}</p> <div class="text-foreground mt-8 max-w-[72ch] text-base leading-7 break-words whitespace-pre-wrap">${escape_html(announcement.body)}</div></div></article>`);
      }
    });
  });
}
export {
  Announcement as default
};
