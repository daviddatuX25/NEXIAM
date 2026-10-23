import { g as sanitize_props, i as spread_props, j as slot, a as ensure_array_like, h as head, e as escape_html, b as attr, d as derived } from "../app.js";
import { M as MediaFrame, P as ProgramExplorer, S as StorySection, A as AdmissionsGateway } from "./StorySection-0tCF3NOv.js";
import { P as PolicyBulletinCard } from "./PolicyBulletinCard-C1afddWy.js";
import { L as Link } from "./Link-B7ks7rBK.js";
import { H as HomeLayout } from "./HomeLayout-Cd36uhjl.js";
import { S as Sparkles } from "./sparkles-DbQIaVG6.js";
import { A as Arrow_right } from "./arrow-right-CzynHiIo.js";
import { I as Icon } from "./Icon-Dal7PqKK.js";
import { M as Map_pin } from "./map-pin-BSPrEyzp.js";
import "axios";
import "@inertiajs/core";
import "clsx";
import "laravel-precognition";
import "tailwind-merge";
import "tailwind-variants";
import "./key-round-BcGN1PBU.js";
import "./shield-check-CLSg-ako.js";
import "./calendar-days-CnJjYUSZ.js";
import "./graduation-cap-Bc9amiZ0.js";
import "./arrow-up-right-BtyWsOyK.js";
import "./ToastManager-C7oO_4Jn.js";
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
function Arrow_down_right($$renderer, $$props) {
  const $$sanitized_props = sanitize_props($$props);
  const iconNode = [
    ["path", { "d": "m7 7 10 10" }],
    ["path", { "d": "M17 7v10H7" }]
  ];
  Icon($$renderer, spread_props([
    { name: "arrow-down-right" },
    $$sanitized_props,
    {
      /**
       * @component @name ArrowDownRight
       * @description Lucide SVG icon component, renders SVG Element with children.
       *
       * @preview ![img](data:image/svg+xml;base64,PHN2ZyAgeG1sbnM9Imh0dHA6Ly93d3cudzMub3JnLzIwMDAvc3ZnIgogIHdpZHRoPSIyNCIKICBoZWlnaHQ9IjI0IgogIHZpZXdCb3g9IjAgMCAyNCAyNCIKICBmaWxsPSJub25lIgogIHN0cm9rZT0iIzAwMCIgc3R5bGU9ImJhY2tncm91bmQtY29sb3I6ICNmZmY7IGJvcmRlci1yYWRpdXM6IDJweCIKICBzdHJva2Utd2lkdGg9IjIiCiAgc3Ryb2tlLWxpbmVjYXA9InJvdW5kIgogIHN0cm9rZS1saW5lam9pbj0icm91bmQiCj4KICA8cGF0aCBkPSJtNyA3IDEwIDEwIiAvPgogIDxwYXRoIGQ9Ik0xNyA3djEwSDciIC8+Cjwvc3ZnPgo=) - https://lucide.dev/icons/arrow-down-right
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
function PublicPolicyLinks($$renderer, $$props) {
  $$renderer.component(($$renderer2) => {
    let { publicPolicies = [] } = $$props;
    $$renderer2.push(`<section id="policies" aria-labelledby="public-policies-heading" class="border-border/60 scroll-mt-[calc(var(--public-header-height,4rem)+1rem)] border-t pt-8"><div class="flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between"><div><h2 id="public-policies-heading" class="text-foreground text-2xl font-semibold tracking-tight">Public policies</h2> <p class="text-muted-foreground mt-2 max-w-[65ch] text-sm leading-6">Read the current public policies and their published revisions.</p></div> `);
    Link($$renderer2, {
      href: "/policies",
      class: "text-primary inline-flex min-h-[44px] items-center gap-2 text-sm font-semibold underline underline-offset-4",
      children: ($$renderer3) => {
        $$renderer3.push(`<!---->View all policies`);
      },
      $$slots: { default: true }
    });
    $$renderer2.push(`<!----></div> `);
    if (publicPolicies.length > 0) {
      $$renderer2.push("<!--[0-->");
      $$renderer2.push(`<div class="mt-6 grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-3"><!--[-->`);
      const each_array = ensure_array_like(publicPolicies);
      for (let $$index = 0, $$length = each_array.length; $$index < $$length; $$index++) {
        let policy = each_array[$$index];
        PolicyBulletinCard($$renderer2, { policy });
      }
      $$renderer2.push(`<!--]--></div>`);
    } else {
      $$renderer2.push("<!--[-1-->");
      $$renderer2.push(`<p class="text-muted-foreground border-border mt-4 rounded-lg border border-dashed p-4 text-sm">No active public policy is available.</p>`);
    }
    $$renderer2.push(`<!--]--></section>`);
  });
}
function Home($$renderer, $$props) {
  $$renderer.component(($$renderer2) => {
    let {
      institution = {
        name: "Ilocos Sur Polytechnic State College",
        short_name: "ISPSC",
        campus: "Tagudin Campus",
        location: "Tagudin, Ilocos Sur",
        kicker: "A campus to study, belong, and begin",
        welcome: "Start with a question. Find a direction. Make this campus part of your story.",
        mission: "At Tagudin Campus, your first step can be practical: explore a program, ask a question, and meet the people who can help you decide."
      },
      hero = {},
      activeSeason = null,
      programs = [],
      stories = [],
      alerts = [],
      publicPolicies = [],
      preview = {},
      homeHref = "/",
      navigation = null
    } = $$props;
    const previewEnabled = derived(() => Boolean(preview?.enabled));
    head("1xcnxpw", $$renderer2, ($$renderer3) => {
      $$renderer3.title(($$renderer4) => {
        $$renderer4.push(`<title>${escape_html(institution.name)} — Welcome</title>`);
      });
      $$renderer3.push(`<meta name="description"${attr("content", `${institution.name}, ${institution.campus}. Explore programs, campus life, and a clear path into admissions.`)}/>`);
    });
    HomeLayout($$renderer2, {
      institution,
      alerts,
      homeHref,
      navigation,
      children: ($$renderer3) => {
        $$renderer3.push(`<div class="overflow-hidden"><section class="border-border/60 relative isolate border-b"><div class="pointer-events-none absolute inset-0 -z-10 bg-[radial-gradient(circle_at_78%_18%,oklch(0.82_0.12_145_/_0.28),transparent_30%),radial-gradient(circle_at_8%_88%,oklch(0.9_0.08_85_/_0.36),transparent_32%)] dark:bg-[radial-gradient(circle_at_78%_18%,oklch(0.38_0.1_145_/_0.25),transparent_30%),radial-gradient(circle_at_8%_88%,oklch(0.35_0.07_85_/_0.2),transparent_32%)]"></div> <div class="mx-auto grid min-h-[calc(100svh-4rem)] max-w-7xl items-center gap-12 px-4 py-14 sm:px-6 sm:py-20 lg:grid-cols-[0.9fr_1.1fr] lg:gap-20 lg:px-8 lg:py-24"><div class="max-w-2xl min-w-0">`);
        if (previewEnabled()) {
          $$renderer3.push("<!--[0-->");
          $$renderer3.push(`<div class="border-primary/20 bg-primary/8 text-primary mb-7 inline-flex items-center gap-2 rounded-full border px-3 py-1.5 text-xs font-semibold">`);
          Sparkles($$renderer3, { class: "size-3.5" });
          $$renderer3.push(`<!----> <span>${escape_html(preview.label ?? "Preview content")}</span></div>`);
        } else {
          $$renderer3.push("<!--[-1-->");
        }
        $$renderer3.push(`<!--]--> <p class="text-primary max-w-full text-xs leading-5 font-bold tracking-[0.22em] break-words uppercase">${escape_html(hero.eyebrow ?? `${institution.name} · ${institution.campus}`)}</p> <h1 class="text-foreground mt-6 max-w-[11ch] text-5xl leading-[0.96] font-semibold tracking-[-0.06em] text-balance sm:text-6xl lg:text-7xl">${escape_html(hero.title ?? "Find your next beginning.")}</h1> <p class="text-muted-foreground mt-7 max-w-full text-lg leading-8 sm:max-w-[48ch] sm:text-xl">${escape_html(hero.lede ?? institution.welcome)}</p> `);
        if (activeSeason?.is_open) {
          $$renderer3.push("<!--[0-->");
          $$renderer3.push(`<p class="text-primary mt-7 text-sm font-medium">Applications for A.Y. ${escape_html(activeSeason.academic_year)} are
                            now open.</p>`);
        } else if (activeSeason) {
          $$renderer3.push("<!--[1-->");
          $$renderer3.push(`<p class="text-muted-foreground mt-7 text-sm">A.Y. ${escape_html(activeSeason.academic_year)}</p>`);
        } else {
          $$renderer3.push("<!--[-1-->");
        }
        $$renderer3.push(`<!--]--> <div class="mt-9 flex flex-col gap-3 sm:flex-row sm:flex-wrap sm:items-center">`);
        Link($$renderer3, {
          href: "/apply",
          class: "group bg-primary text-primary-foreground shadow-primary/20 hover:bg-primary/90 focus-visible:ring-ring inline-flex min-h-14 items-center justify-center gap-4 rounded-full px-6 py-3 text-sm font-bold tracking-[0.08em] uppercase shadow-lg transition focus-visible:ring-2 focus-visible:outline-none",
          children: ($$renderer4) => {
            $$renderer4.push(`<!---->Start your application `);
            Arrow_right($$renderer4, { class: "size-5" });
            $$renderer4.push(`<!---->`);
          },
          $$slots: { default: true }
        });
        $$renderer3.push(`<!----> <a href="#programs" class="text-foreground hover:text-primary focus-visible:ring-ring inline-flex min-h-14 items-center justify-center gap-2 px-4 py-3 text-sm font-semibold transition focus-visible:ring-2 focus-visible:outline-none">Explore programs `);
        Arrow_down_right($$renderer3, { class: "size-4" });
        $$renderer3.push(`<!----></a></div> <div class="text-muted-foreground mt-6 flex flex-wrap items-center gap-x-4 gap-y-2 text-sm">`);
        Link($$renderer3, {
          href: "/applicant/login",
          class: "hover:text-primary decoration-primary/30 font-medium underline underline-offset-4 transition",
          children: ($$renderer4) => {
            $$renderer4.push(`<!---->Applicant Portal`);
          },
          $$slots: { default: true }
        });
        $$renderer3.push(`<!----></div></div> <div class="relative min-w-0"><div class="border-primary/25 bg-background/85 text-primary pointer-events-none absolute -top-5 right-8 z-10 hidden rounded-full border px-4 py-2 text-xs font-semibold shadow-sm backdrop-blur sm:block">${escape_html(institution.kicker)}</div> `);
        MediaFrame($$renderer3, {
          image: hero.image,
          ratio: "aspect-[4/5] sm:aspect-[5/4]",
          priority: true,
          showSource: previewEnabled(),
          class: "shadow-[0_34px_100px_-45px_oklch(0.22_0.04_155_/_0.65)]"
        });
        $$renderer3.push(`<!----> <div class="bg-background/90 border-border/70 absolute -bottom-5 left-4 z-10 max-w-[15rem] rounded-[1rem] border p-4 shadow-xl backdrop-blur sm:left-8"><div class="flex items-start gap-3">`);
        Map_pin($$renderer3, { class: "text-primary mt-0.5 size-4 shrink-0" });
        $$renderer3.push(`<!----> <div><p class="text-foreground text-sm font-semibold">${escape_html(institution.campus)}</p> <p class="text-muted-foreground mt-1 text-xs leading-5">${escape_html(institution.location)}</p></div></div></div></div></div> <a href="#mission" class="text-muted-foreground hover:text-primary focus-visible:ring-ring mx-auto flex min-h-11 w-fit items-center gap-2 px-4 pb-6 text-xs font-semibold tracking-[0.18em] uppercase transition focus-visible:ring-2 focus-visible:outline-none">Discover the institution `);
        Arrow_down_right($$renderer3, { class: "size-4" });
        $$renderer3.push(`<!----></a></section> <section id="mission" class="border-border/60 scroll-mt-24 border-b"><div class="mx-auto grid max-w-7xl gap-10 px-4 py-20 sm:px-6 sm:py-28 lg:grid-cols-[0.75fr_1.25fr] lg:gap-24 lg:px-8"><div><p class="text-primary text-xs font-bold tracking-[0.22em] uppercase">Why this place matters</p> <h2 class="text-foreground mt-4 max-w-[12ch] text-4xl leading-[1.02] font-semibold tracking-[-0.05em] sm:text-5xl">Learn close to what matters.</h2></div> <div class="max-w-2xl"><p class="text-foreground text-xl leading-8 tracking-[-0.015em] sm:text-2xl sm:leading-9">${escape_html(institution.mission)}</p> <div class="border-border/60 mt-9 grid gap-4 border-t pt-7 sm:grid-cols-3"><div><p class="text-primary text-xs font-bold tracking-[0.18em] uppercase">People</p> <p class="text-muted-foreground mt-2 text-sm leading-6">Find guides, classmates, and conversations that
                                open a next step.</p></div> <div><p class="text-primary text-xs font-bold tracking-[0.18em] uppercase">Place</p> <p class="text-muted-foreground mt-2 text-sm leading-6">See how a campus can make learning feel grounded
                                and close to home.</p></div> <div><p class="text-primary text-xs font-bold tracking-[0.18em] uppercase">Practice</p> <p class="text-muted-foreground mt-2 text-sm leading-6">Choose a direction, test your questions, and
                                keep moving.</p></div></div></div></div></section> <div class="mx-auto grid max-w-7xl gap-24 px-4 py-20 sm:px-6 sm:py-28 lg:gap-32 lg:px-8">`);
        ProgramExplorer($$renderer3, { programs, preview: previewEnabled() });
        $$renderer3.push(`<!----> <section id="campus-life" class="scroll-mt-24 space-y-16"><div class="max-w-2xl"><p class="text-primary text-xs font-bold tracking-[0.22em] uppercase">Campus life</p> <h2 class="text-foreground mt-3 text-3xl leading-tight font-semibold tracking-[-0.035em] sm:text-4xl">The experience is more than a timetable.</h2></div> `);
        if (stories.length > 0) {
          $$renderer3.push("<!--[0-->");
          $$renderer3.push(`<!--[-->`);
          const each_array = ensure_array_like(stories);
          for (let $$index = 0, $$length = each_array.length; $$index < $$length; $$index++) {
            let story = each_array[$$index];
            StorySection($$renderer3, { story, preview: previewEnabled() });
          }
          $$renderer3.push(`<!--]-->`);
        } else {
          $$renderer3.push("<!--[-1-->");
          $$renderer3.push(`<div class="border-border bg-muted/20 rounded-[1.25rem] border border-dashed p-8"><p class="text-foreground font-semibold">Campus stories are being prepared.</p> <p class="text-muted-foreground mt-2 text-sm">Approved student and campus experiences will appear
                            here.</p></div>`);
        }
        $$renderer3.push(`<!--]--></section> `);
        AdmissionsGateway($$renderer3, { activeSeason });
        $$renderer3.push(`<!----> `);
        PublicPolicyLinks($$renderer3, { publicPolicies });
        $$renderer3.push(`<!----></div> `);
        if (previewEnabled()) {
          $$renderer3.push("<!--[0-->");
          $$renderer3.push(`<aside class="border-primary/15 bg-primary/5 border-y"><div class="mx-auto flex max-w-7xl flex-col gap-2 px-4 py-5 text-sm sm:flex-row sm:items-center sm:justify-between sm:px-6 lg:px-8"><p class="text-foreground font-medium">${escape_html(preview.label ?? "Preview content")}</p> <p class="text-muted-foreground max-w-2xl leading-6">${escape_html(preview.notice ?? "Replace simulated content with approved institutional copy and media before public launch.")}</p></div></aside>`);
        } else {
          $$renderer3.push("<!--[-1-->");
        }
        $$renderer3.push(`<!--]--></div>`);
      }
    });
  });
}
export {
  Home as default
};
