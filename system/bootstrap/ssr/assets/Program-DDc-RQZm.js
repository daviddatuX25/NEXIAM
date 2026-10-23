import { h as head, e as escape_html, b as attr, a as ensure_array_like, d as derived, i as spread_props } from "../app.js";
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
function ProgramDetail($$renderer, $$props) {
  $$renderer.component(($$renderer2) => {
    let {
      institution = {},
      program,
      publicProfile = null,
      alerts = [],
      announcements = [],
      programPolicies = [],
      homeHref = "/",
      navigation = null,
      programIndex = null,
      programPolicyIndex = null
    } = $$props;
    const programAnnouncementIndex = derived(() => programIndex ?? null);
    const programsHref = derived(() => homeHref.includes("#") ? homeHref : homeHref === "/" ? "/#programs" : `${homeHref.replace(/\/$/, "")}#programs`);
    const paragraphs = derived(() => String(publicProfile?.description ?? "").split(/\n+/).map((paragraph) => paragraph.trim()).filter(Boolean));
    const photos = derived(() => publicProfile?.photos ?? []);
    const cover = derived(() => photos()[0] ?? null);
    const gallery = derived(() => photos().slice(1));
    head("3ab98o", $$renderer2, ($$renderer3) => {
      $$renderer3.title(($$renderer4) => {
        $$renderer4.push(`<title>${escape_html(program.name)} (${escape_html(program.code)}) — ${escape_html(institution.name)}</title>`);
      });
      $$renderer3.push(`<meta name="description"${attr("content", publicProfile?.short_description ?? "")}/>`);
    });
    HomeLayout($$renderer2, {
      institution,
      alerts,
      homeHref,
      navigation,
      children: ($$renderer3) => {
        $$renderer3.push(`<article class="mx-auto w-full max-w-7xl space-y-12 px-4 py-8 sm:space-y-16 sm:px-6 sm:py-12 lg:px-8"><header class="space-y-5">`);
        Link($$renderer3, {
          href: programsHref(),
          class: "text-primary focus-visible:ring-ring inline-flex min-h-[44px] items-center rounded-lg px-3 py-2 text-sm font-semibold underline underline-offset-4 focus-visible:ring-2 focus-visible:outline-none",
          children: ($$renderer4) => {
            $$renderer4.push(`<!---->Back to programs`);
          },
          $$slots: { default: true }
        });
        $$renderer3.push(`<!----> <div class="border-border/60 border-b pb-6"><p class="text-muted-foreground font-mono text-sm font-semibold tracking-[0.12em]">${escape_html(program.code)}</p> <h1 class="text-foreground mt-2 text-3xl leading-tight font-semibold tracking-[-0.035em] sm:text-5xl">${escape_html(program.name)}</h1> `);
        if (publicProfile?.short_description) {
          $$renderer3.push("<!--[0-->");
          $$renderer3.push(`<p class="text-muted-foreground mt-5 max-w-[65ch] text-base leading-7 sm:text-lg">${escape_html(publicProfile.short_description)}</p>`);
        } else {
          $$renderer3.push("<!--[-1-->");
        }
        $$renderer3.push(`<!--]--></div></header> `);
        if (cover()) {
          $$renderer3.push("<!--[0-->");
          $$renderer3.push(`<figure class="mx-auto w-full max-w-5xl"><img${attr("src", cover().src)}${attr("alt", cover().alt_text)} class="border-border/70 max-h-[min(72svh,42rem)] w-full rounded-lg border object-cover" loading="eager" decoding="async"/> `);
          if (cover().caption) {
            $$renderer3.push("<!--[0-->");
            $$renderer3.push(`<figcaption class="text-muted-foreground mt-3 text-sm leading-6">${escape_html(cover().caption)}</figcaption>`);
          } else {
            $$renderer3.push("<!--[-1-->");
          }
          $$renderer3.push(`<!--]--></figure>`);
        } else {
          $$renderer3.push("<!--[-1-->");
          $$renderer3.push(`<p class="text-muted-foreground border-border rounded-lg border border-dashed p-5 text-sm leading-6">No program photos are published yet.</p>`);
        }
        $$renderer3.push(`<!--]--> <section aria-labelledby="program-about-heading" class="max-w-3xl space-y-5"><h2 id="program-about-heading" class="text-foreground text-2xl font-semibold tracking-tight sm:text-3xl">About this program</h2> `);
        if (paragraphs().length > 0) {
          $$renderer3.push("<!--[0-->");
          $$renderer3.push(`<!--[-->`);
          const each_array = ensure_array_like(paragraphs());
          for (let $$index = 0, $$length = each_array.length; $$index < $$length; $$index++) {
            let paragraph = each_array[$$index];
            $$renderer3.push(`<p class="text-foreground text-base leading-7 break-words whitespace-pre-wrap">${escape_html(paragraph)}</p>`);
          }
          $$renderer3.push(`<!--]-->`);
        } else {
          $$renderer3.push("<!--[-1-->");
          $$renderer3.push(`<p class="text-muted-foreground border-border rounded-lg border border-dashed p-5 text-sm leading-6">A full program description has not been published.</p>`);
        }
        $$renderer3.push(`<!--]--></section> `);
        if (gallery().length > 0) {
          $$renderer3.push("<!--[0-->");
          $$renderer3.push(`<section aria-labelledby="program-photos-heading" class="space-y-5"><h2 id="program-photos-heading" class="text-foreground text-2xl font-semibold tracking-tight sm:text-3xl">Program photos</h2> <div class="grid min-w-0 gap-6 sm:grid-cols-2 lg:grid-cols-3"><!--[-->`);
          const each_array_1 = ensure_array_like(gallery());
          for (let $$index_1 = 0, $$length = each_array_1.length; $$index_1 < $$length; $$index_1++) {
            let photo = each_array_1[$$index_1];
            $$renderer3.push(`<figure class="min-w-0"><img${attr("src", photo.src)}${attr("alt", photo.alt_text)} class="border-border/70 aspect-[4/3] w-full rounded-lg border object-cover" loading="lazy" decoding="async"/> `);
            if (photo.caption) {
              $$renderer3.push("<!--[0-->");
              $$renderer3.push(`<figcaption class="text-muted-foreground mt-2 text-sm leading-6">${escape_html(photo.caption)}</figcaption>`);
            } else {
              $$renderer3.push("<!--[-1-->");
            }
            $$renderer3.push(`<!--]--></figure>`);
          }
          $$renderer3.push(`<!--]--></div></section>`);
        } else {
          $$renderer3.push("<!--[-1-->");
        }
        $$renderer3.push(`<!--]--> <section aria-labelledby="program-announcements-heading" class="border-border/60 border-t pt-8"><div class="flex flex-wrap items-center justify-between gap-3"><h2 id="program-announcements-heading" class="text-foreground text-2xl font-semibold tracking-tight sm:text-3xl">Program notices</h2> `);
        if (programAnnouncementIndex()) {
          $$renderer3.push("<!--[0-->");
          Link($$renderer3, {
            href: programAnnouncementIndex(),
            class: "text-primary inline-flex min-h-[44px] items-center text-sm font-semibold underline underline-offset-4",
            children: ($$renderer4) => {
              $$renderer4.push(`<!---->All program notices`);
            },
            $$slots: { default: true }
          });
        } else {
          $$renderer3.push("<!--[-1-->");
        }
        $$renderer3.push(`<!--]--></div> `);
        if (announcements.length > 0) {
          $$renderer3.push("<!--[0-->");
          $$renderer3.push(`<ul class="divide-border/60 border-border/60 mt-4 divide-y border-y"><!--[-->`);
          const each_array_2 = ensure_array_like(announcements);
          for (let $$index_2 = 0, $$length = each_array_2.length; $$index_2 < $$length; $$index_2++) {
            let announcement = each_array_2[$$index_2];
            $$renderer3.push(`<li class="py-4">`);
            Link($$renderer3, {
              href: announcement.href,
              class: "text-foreground hover:text-primary focus-visible:ring-ring inline-flex min-h-[44px] items-center rounded-lg px-2 text-base font-semibold underline underline-offset-4 focus-visible:ring-2 focus-visible:outline-none",
              children: ($$renderer4) => {
                $$renderer4.push(`<!---->${escape_html(announcement.title)}`);
              },
              $$slots: { default: true }
            });
            $$renderer3.push(`<!----> `);
            if (announcement.summary) {
              $$renderer3.push("<!--[0-->");
              $$renderer3.push(`<p class="text-muted-foreground px-2 pb-1 text-sm leading-6">${escape_html(announcement.summary)}</p>`);
            } else {
              $$renderer3.push("<!--[-1-->");
            }
            $$renderer3.push(`<!--]--></li>`);
          }
          $$renderer3.push(`<!--]--></ul>`);
        } else {
          $$renderer3.push("<!--[-1-->");
          $$renderer3.push(`<p class="text-muted-foreground border-border mt-4 rounded-lg border border-dashed p-5 text-sm leading-6">No program notices are currently available.</p>`);
        }
        $$renderer3.push(`<!--]--></section> <section aria-labelledby="program-policies-heading" class="border-border/60 border-t pt-8"><div class="flex flex-wrap items-center justify-between gap-3"><h2 id="program-policies-heading" class="text-foreground text-2xl font-semibold tracking-tight sm:text-3xl">Program policies</h2> `);
        if (programPolicyIndex) {
          $$renderer3.push("<!--[0-->");
          Link($$renderer3, {
            href: programPolicyIndex,
            class: "text-primary inline-flex min-h-[44px] items-center text-sm font-semibold underline underline-offset-4",
            children: ($$renderer4) => {
              $$renderer4.push(`<!---->View all ${escape_html(program.code)} policies`);
            },
            $$slots: { default: true }
          });
        } else {
          $$renderer3.push("<!--[-1-->");
        }
        $$renderer3.push(`<!--]--></div> <p class="text-muted-foreground mt-3 text-sm leading-6">Published documents issued for this active program.</p> `);
        if (programPolicies.length > 0) {
          $$renderer3.push("<!--[0-->");
          $$renderer3.push(`<div class="mt-6 grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-3"><!--[-->`);
          const each_array_3 = ensure_array_like(programPolicies);
          for (let $$index_3 = 0, $$length = each_array_3.length; $$index_3 < $$length; $$index_3++) {
            let policy = each_array_3[$$index_3];
            PolicyBulletinCard($$renderer3, { policy });
          }
          $$renderer3.push(`<!--]--></div>`);
        } else {
          $$renderer3.push("<!--[-1-->");
          $$renderer3.push(`<p class="text-muted-foreground border-border mt-4 rounded-lg border border-dashed p-5 text-sm leading-6">No program policies are currently available.</p>`);
        }
        $$renderer3.push(`<!--]--></section></article>`);
      }
    });
  });
}
function Program($$renderer, $$props) {
  let { $$slots, $$events, ...props } = $$props;
  ProgramDetail($$renderer, spread_props([props]));
}
export {
  Program as default
};
