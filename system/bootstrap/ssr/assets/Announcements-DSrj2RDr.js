import { h as head, e as escape_html, a as ensure_array_like, b as attr, d as derived } from "../app.js";
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
function Announcements($$renderer, $$props) {
  $$renderer.component(($$renderer2) => {
    let {
      institution = {},
      alerts = [],
      announcements = { data: [], links: [] },
      program = null
    } = $$props;
    const rows = derived(() => announcements?.data ?? []);
    function paginationLabel(label) {
      return String(label ?? "").replace(/<[^>]*>/g, "").replaceAll("&laquo;", "«").replaceAll("&raquo;", "»");
    }
    head("1lxeh6z", $$renderer2, ($$renderer3) => {
      $$renderer3.title(($$renderer4) => {
        $$renderer4.push(`<title>
        ${escape_html(program ? `${program.name} announcements` : "Announcements")} — ${escape_html(institution.name ?? "Institution")}
    </title>`);
      });
    });
    HomeLayout($$renderer2, {
      institution,
      alerts,
      children: ($$renderer3) => {
        $$renderer3.push(`<div class="mx-auto w-full max-w-7xl px-4 py-12 sm:px-6 sm:py-16 lg:px-8"><header class="max-w-[72ch]">`);
        if (program) {
          $$renderer3.push("<!--[0-->");
          Link($$renderer3, {
            href: `/programs/${encodeURIComponent(program.code)}`,
            class: "text-primary inline-flex min-h-[44px] items-center text-sm font-semibold underline underline-offset-4",
            children: ($$renderer4) => {
              $$renderer4.push(`<!---->Back to ${escape_html(program.code)}`);
            },
            $$slots: { default: true }
          });
        } else {
          $$renderer3.push("<!--[-1-->");
        }
        $$renderer3.push(`<!--]--> <h1 class="text-foreground text-4xl font-semibold tracking-tight sm:text-5xl">${escape_html(program ? `${program.name} announcements` : "Announcements")}</h1> <p class="text-muted-foreground mt-4 text-base leading-7">${escape_html(program ? `Announcements for ${program.code}.` : "General updates from Registrar and Guidance.")}</p></header> `);
        if (rows().length > 0) {
          $$renderer3.push("<!--[0-->");
          $$renderer3.push(`<ol class="divide-border/60 border-border/60 mt-8 divide-y border-y"><!--[-->`);
          const each_array = ensure_array_like(rows());
          for (let $$index = 0, $$length = each_array.length; $$index < $$length; $$index++) {
            let announcement = each_array[$$index];
            $$renderer3.push(`<li class="grid min-w-0 gap-3 py-6 sm:grid-cols-[minmax(12rem,0.35fr)_minmax(0,1fr)_auto] sm:items-start sm:gap-8"><div class="text-primary flex flex-wrap items-center gap-x-3 gap-y-1 text-xs font-semibold"><span class="break-words">${escape_html(announcement.publisher_label)}</span> `);
            if (announcement.published_at) {
              $$renderer3.push("<!--[0-->");
              $$renderer3.push(`<time${attr("datetime", announcement.published_at)}>${escape_html(new Date(announcement.published_at).toLocaleDateString())}</time>`);
            } else {
              $$renderer3.push("<!--[-1-->");
            }
            $$renderer3.push(`<!--]--></div> <div class="min-w-0"><h2 class="text-foreground text-xl font-semibold break-words">${escape_html(announcement.title)}</h2> <p class="text-muted-foreground mt-2 max-w-[65ch] text-sm leading-6 break-words whitespace-pre-wrap">${escape_html(announcement.summary)}</p></div> `);
            Link($$renderer3, {
              href: announcement.href,
              class: "text-primary inline-flex min-h-[44px] items-center text-sm font-semibold underline underline-offset-4 sm:justify-self-end",
              children: ($$renderer4) => {
                $$renderer4.push(`<!---->Read announcement`);
              },
              $$slots: { default: true }
            });
            $$renderer3.push(`<!----></li>`);
          }
          $$renderer3.push(`<!--]--></ol> `);
          if (announcements.links?.length > 0) {
            $$renderer3.push("<!--[0-->");
            $$renderer3.push(`<nav aria-label="Announcement pages" class="mt-6 flex flex-wrap items-center gap-2"><!--[-->`);
            const each_array_1 = ensure_array_like(announcements.links);
            for (let $$index_1 = 0, $$length = each_array_1.length; $$index_1 < $$length; $$index_1++) {
              let pageLink = each_array_1[$$index_1];
              if (pageLink.url) {
                $$renderer3.push("<!--[0-->");
                Link($$renderer3, {
                  href: pageLink.url,
                  preserveScroll: true,
                  class: `border-border inline-flex min-h-[44px] items-center rounded-md border px-3 text-sm ${pageLink.active ? "bg-primary text-primary-foreground" : "text-foreground hover:bg-muted"}`,
                  children: ($$renderer4) => {
                    $$renderer4.push(`<!---->${escape_html(paginationLabel(pageLink.label))}`);
                  },
                  $$slots: { default: true }
                });
              } else {
                $$renderer3.push("<!--[-1-->");
                $$renderer3.push(`<span aria-disabled="true" class="border-border/50 text-muted-foreground inline-flex min-h-[44px] items-center rounded-md border px-3 text-sm">${escape_html(paginationLabel(pageLink.label))}</span>`);
              }
              $$renderer3.push(`<!--]-->`);
            }
            $$renderer3.push(`<!--]--></nav>`);
          } else {
            $$renderer3.push("<!--[-1-->");
          }
          $$renderer3.push(`<!--]-->`);
        } else {
          $$renderer3.push("<!--[-1-->");
          $$renderer3.push(`<p role="status" class="border-border text-muted-foreground mt-8 rounded-lg border border-dashed p-6 text-sm">There are no published announcements to display right now.</p>`);
        }
        $$renderer3.push(`<!--]--></div>`);
      }
    });
  });
}
export {
  Announcements as default
};
