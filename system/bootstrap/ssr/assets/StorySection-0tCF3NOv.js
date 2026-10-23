import { e as escape_html, c as attr_class, b as attr, d as derived, s as stringify, a as ensure_array_like, A as clsx } from "../app.js";
import "clsx";
import { L as Link } from "./Link-B7ks7rBK.js";
import { A as Arrow_right } from "./arrow-right-CzynHiIo.js";
import { K as Key_round } from "./key-round-BcGN1PBU.js";
import { S as Shield_check } from "./shield-check-CLSg-ako.js";
import { C as Calendar_days } from "./calendar-days-CnJjYUSZ.js";
import { G as Graduation_cap } from "./graduation-cap-Bc9amiZ0.js";
import { A as Arrow_up_right } from "./arrow-up-right-BtyWsOyK.js";
function AdmissionsGateway($$renderer, $$props) {
  $$renderer.component(($$renderer2) => {
    let { activeSeason = null } = $$props;
    $$renderer2.push(`<section id="admissions" class="scroll-mt-24"><div class="border-primary/20 overflow-hidden rounded-[1.5rem] border bg-[linear-gradient(135deg,oklch(0.95_0.04_145),oklch(0.89_0.07_150))] p-6 shadow-[0_28px_80px_-44px_oklch(0.32_0.12_150_/_0.6)] sm:p-8 lg:p-10 dark:bg-[linear-gradient(135deg,oklch(0.24_0.06_150),oklch(0.18_0.05_155))]"><div class="grid gap-10 lg:grid-cols-[1.05fr_0.95fr] lg:items-end"><div><p class="text-primary text-xs font-bold tracking-[0.22em] uppercase">A clear way in</p> <h2 class="text-foreground mt-3 max-w-[13ch] text-3xl leading-[1.02] font-semibold tracking-[-0.045em] sm:text-4xl">Ready when you are.</h2> <p class="text-muted-foreground mt-5 max-w-[48ch] text-base leading-7">Explore the requirements, submit your application, and keep
                    your access details close as your next steps take shape.</p> `);
    if (activeSeason?.is_open) {
      $$renderer2.push("<!--[0-->");
      $$renderer2.push(`<p class="text-primary mt-6 text-sm font-medium">Applications for A.Y. ${escape_html(activeSeason.academic_year)} are now
                        open.</p>`);
    } else {
      $$renderer2.push("<!--[-1-->");
      $$renderer2.push(`<p class="text-muted-foreground mt-6 text-sm">The next admissions window will appear here when
                        applications open.</p>`);
    }
    $$renderer2.push(`<!--]--></div> <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-1">`);
    Link($$renderer2, {
      href: "/apply",
      class: "group bg-primary text-primary-foreground shadow-primary/20 hover:bg-primary/90 focus-visible:ring-ring flex min-h-14 items-center justify-between gap-4 rounded-full px-5 py-3 font-semibold shadow-lg transition hover:-translate-y-0.5 focus-visible:ring-2 focus-visible:outline-none",
      children: ($$renderer3) => {
        $$renderer3.push(`<span>Start your application</span> `);
        Arrow_right($$renderer3, {
          class: "size-5 transition-transform group-hover:translate-x-1"
        });
        $$renderer3.push(`<!---->`);
      },
      $$slots: { default: true }
    });
    $$renderer2.push(`<!----> `);
    Link($$renderer2, {
      href: "/applicant/login",
      class: "group border-border/80 bg-background/65 text-foreground hover:border-primary/50 hover:text-primary focus-visible:ring-ring flex min-h-14 items-center justify-between gap-4 rounded-full border px-5 py-3 font-semibold transition focus-visible:ring-2 focus-visible:outline-none",
      children: ($$renderer3) => {
        $$renderer3.push(`<span class="inline-flex items-center gap-2">`);
        Key_round($$renderer3, { class: "size-4" });
        $$renderer3.push(`<!----> Applicant Portal</span> `);
        Arrow_right($$renderer3, {
          class: "size-5 transition-transform group-hover:translate-x-1"
        });
        $$renderer3.push(`<!---->`);
      },
      $$slots: { default: true }
    });
    $$renderer2.push(`<!----></div></div> <div class="border-primary/15 mt-9 grid gap-4 border-t pt-6 sm:grid-cols-3"><div class="flex gap-3">`);
    Shield_check($$renderer2, { class: "text-primary mt-0.5 size-5 shrink-0" });
    $$renderer2.push(`<!----> <div><p class="text-foreground text-sm font-semibold">Prepare once</p> <p class="text-muted-foreground mt-1 text-xs leading-5">Review the published requirements before you submit.</p></div></div> <div class="flex gap-3">`);
    Calendar_days($$renderer2, { class: "text-primary mt-0.5 size-5 shrink-0" });
    $$renderer2.push(`<!----> <div><p class="text-foreground text-sm font-semibold">Keep your place</p> <p class="text-muted-foreground mt-1 text-xs leading-5">Use the portal to follow schedules and status.</p></div></div> <div class="flex gap-3">`);
    Key_round($$renderer2, { class: "text-primary mt-0.5 size-5 shrink-0" });
    $$renderer2.push(`<!----> <div><p class="text-foreground text-sm font-semibold">Follow your progress</p> <p class="text-muted-foreground mt-1 text-xs leading-5">Return to the applicant portal for your next steps.</p></div></div></div></div></section>`);
  });
}
function MediaFrame($$renderer, $$props) {
  $$renderer.component(($$renderer2) => {
    let {
      image = null,
      ratio = "aspect-[4/3]",
      alt = "",
      loading = "lazy",
      priority = false,
      showSource = false,
      class: className = ""
    } = $$props;
    const imageAlt = derived(() => image?.alt ?? alt);
    const sourceLabel = derived(() => image?.source ?? null);
    $$renderer2.push(`<div${attr_class(`group relative isolate overflow-hidden rounded-[1.25rem] border border-border/60 bg-muted/70 shadow-[0_18px_60px_-32px_oklch(0.22_0.04_155_/_0.42)] ${stringify(ratio)} ${stringify(className)}`)}>`);
    if (image?.src && true) {
      $$renderer2.push("<!--[0-->");
      $$renderer2.push(`<img${attr("src", image.src)}${attr("alt", imageAlt())}${attr("loading", priority ? "eager" : loading)} decoding="async"${attr("fetchpriority", priority ? "high" : "auto")} class="absolute inset-0 h-full w-full object-cover transition duration-700 ease-out group-hover:scale-[1.025]" onerror="this.__e=event"/>`);
    } else {
      $$renderer2.push("<!--[-1-->");
      $$renderer2.push(`<div class="absolute inset-0 flex items-end bg-[radial-gradient(circle_at_20%_20%,oklch(0.78_0.12_150_/_0.42),transparent_32%),linear-gradient(135deg,oklch(0.29_0.08_155),oklch(0.16_0.04_160))] p-6" role="img"${attr("aria-label", imageAlt() || "Image placeholder")}><span class="max-w-[18rem] text-sm font-medium leading-relaxed text-white/80">Image placeholder — replace with approved campus media.</span></div>`);
    }
    $$renderer2.push(`<!--]--> <div class="pointer-events-none absolute inset-0 bg-gradient-to-t from-black/35 via-transparent to-white/10"></div> `);
    if (showSource && sourceLabel()) {
      $$renderer2.push("<!--[0-->");
      $$renderer2.push(`<span class="absolute right-3 bottom-3 rounded-full border border-white/20 bg-black/35 px-2.5 py-1 text-[0.65rem] font-semibold tracking-[0.12em] text-white/80 uppercase backdrop-blur">${escape_html(sourceLabel())}</span>`);
    } else {
      $$renderer2.push("<!--[-1-->");
    }
    $$renderer2.push(`<!--]--></div>`);
  });
}
function ProgramExplorer($$renderer, $$props) {
  $$renderer.component(($$renderer2) => {
    let { programs = [], preview = false } = $$props;
    const rows = derived(() => {
      if (programs.length < 2) return programs.length ? [programs] : [];
      const split = Math.ceil(programs.length / 2);
      return [programs.slice(0, split), programs.slice(split)];
    });
    let overflowing = [];
    let failedImages = {};
    function ProgramCard($$renderer3, program) {
      $$renderer3.push(`<article data-program-card="" class="border-border/70 bg-card group relative isolate flex h-full min-h-[13rem] flex-col justify-between overflow-hidden rounded-[1.25rem] border p-4 sm:p-5">`);
      if (program.image?.src && !failedImages[program.image.src]) {
        $$renderer3.push("<!--[0-->");
        $$renderer3.push(`<img${attr("src", program.image.src)} alt="" aria-hidden="true"${attr("data-source", program.image.source ?? void 0)} loading="lazy" decoding="async" class="absolute inset-0 -z-10 h-full w-full object-cover" onerror="this.__e=event"/>`);
      } else {
        $$renderer3.push("<!--[-1-->");
      }
      $$renderer3.push(`<!--]--> <div class="from-card/95 via-card/90 to-card/85 pointer-events-none absolute inset-0 -z-10 bg-gradient-to-r"></div> <div><div class="flex items-start justify-between gap-3"><span class="bg-primary/10 text-primary flex size-9 shrink-0 items-center justify-center rounded-full" aria-hidden="true">`);
      Graduation_cap($$renderer3, { class: "size-4" });
      $$renderer3.push(`<!----></span> <span class="text-muted-foreground font-mono text-[0.7rem] font-semibold tracking-[0.12em]">${escape_html(program.code)}</span></div> <h3 class="text-foreground mt-4 text-lg leading-snug font-semibold tracking-[-0.02em]">${escape_html(program.name)}</h3> `);
      if (program.summary) {
        $$renderer3.push("<!--[0-->");
        $$renderer3.push(`<p class="text-muted-foreground mt-2 text-sm leading-5">${escape_html(program.summary)}</p>`);
      } else {
        $$renderer3.push("<!--[-1-->");
      }
      $$renderer3.push(`<!--]--></div> `);
      Link($$renderer3, {
        href: `/programs/${encodeURIComponent(program.code)}`,
        class: "text-foreground border-border hover:border-primary/50 hover:text-primary focus-visible:ring-ring mt-4 inline-flex min-h-[44px] w-fit items-center gap-2 rounded-lg px-3 py-2 text-sm font-semibold focus-visible:ring-2 focus-visible:outline-none",
        children: ($$renderer4) => {
          $$renderer4.push(`<!---->View program `);
          Arrow_up_right($$renderer4, { class: "size-4" });
          $$renderer4.push(`<!---->`);
        },
        $$slots: { default: true }
      });
      $$renderer3.push(`<!----></article>`);
    }
    $$renderer2.push(`<section id="programs" class="min-w-0 scroll-mt-24"><div class="border-border/60 flex flex-col gap-5 border-b pb-7 md:flex-row md:items-end md:justify-between"><div class="max-w-2xl"><p class="text-primary text-xs font-bold tracking-[0.22em] uppercase">Find your direction</p> <h2 class="text-foreground mt-3 text-3xl leading-tight font-semibold tracking-[-0.035em] sm:text-4xl">Programs that make room for your questions.</h2></div> <p class="text-muted-foreground max-w-sm text-sm leading-6">Start with an area that feels close to your interests, then ask the
            people who can help you see what comes next.</p></div> `);
    if (programs.length) {
      $$renderer2.push("<!--[0-->");
      $$renderer2.push(`<!--[-->`);
      const each_array = ensure_array_like(rows());
      for (let index = 0, $$length = each_array.length; index < $$length; index++) {
        let row = each_array[index];
        $$renderer2.push(`<div class="mt-6"><div${attr("data-program-row", index)} class="focus-visible:ring-ring flex [scrollbar-width:none] gap-3 overflow-x-auto overscroll-x-contain pb-2 focus-visible:ring-2 focus-visible:outline-none [&amp;::-webkit-scrollbar]:hidden" role="region"${attr("aria-label", `${index === 0 ? "First" : "Second"} row of programs`)} tabindex="0"><div data-program-cycle="" class="flex shrink-0 gap-3"><!--[-->`);
        const each_array_1 = ensure_array_like(row);
        for (let $$index = 0, $$length2 = each_array_1.length; $$index < $$length2; $$index++) {
          let program = each_array_1[$$index];
          $$renderer2.push(`<div class="w-[min(76vw,19rem)] shrink-0">`);
          ProgramCard($$renderer2, program);
          $$renderer2.push(`<!----></div>`);
        }
        $$renderer2.push(`<!--]--></div> `);
        if (overflowing[index]) {
          $$renderer2.push("<!--[0-->");
          $$renderer2.push(`<div class="flex shrink-0 gap-3" aria-hidden="true" inert=""><!--[-->`);
          const each_array_2 = ensure_array_like(row);
          for (let $$index_1 = 0, $$length2 = each_array_2.length; $$index_1 < $$length2; $$index_1++) {
            let program = each_array_2[$$index_1];
            $$renderer2.push(`<div class="w-[min(76vw,19rem)] shrink-0">`);
            ProgramCard($$renderer2, program);
            $$renderer2.push(`<!----></div>`);
          }
          $$renderer2.push(`<!--]--></div>`);
        } else {
          $$renderer2.push("<!--[-1-->");
        }
        $$renderer2.push(`<!--]--></div></div>`);
      }
      $$renderer2.push(`<!--]--> `);
      if (preview) {
        $$renderer2.push("<!--[0-->");
        $$renderer2.push(`<p class="text-muted-foreground mt-4 text-xs">Program names come from the seeded preview catalog and can be
                replaced by approved catalog content.</p>`);
      } else {
        $$renderer2.push("<!--[-1-->");
      }
      $$renderer2.push(`<!--]-->`);
    } else {
      $$renderer2.push("<!--[-1-->");
      $$renderer2.push(`<div class="border-border bg-muted/30 mt-8 rounded-[1.25rem] border border-dashed p-8 text-center"><p class="text-foreground font-semibold">Program information is being prepared.</p> <p class="text-muted-foreground mt-2 text-sm">Contact the campus for the latest approved program list.</p></div>`);
    }
    $$renderer2.push(`<!--]--></section>`);
  });
}
function StorySection($$renderer, $$props) {
  $$renderer.component(($$renderer2) => {
    let { story = {}, preview = false, eyebrow = null } = $$props;
    const copyFirst = derived(() => (story.align ?? "right") === "right");
    $$renderer2.push(`<section${attr_class(clsx(copyFirst() ? "grid items-center gap-8 lg:grid-cols-[1.1fr_0.9fr] lg:gap-16" : "grid items-center gap-8 lg:grid-cols-[0.9fr_1.1fr] lg:gap-16"))}><div${attr_class(clsx(copyFirst() ? "lg:order-2" : ""))}>`);
    MediaFrame($$renderer2, {
      image: story.image,
      ratio: "aspect-[5/4]",
      showSource: preview
    });
    $$renderer2.push(`<!----></div> <div${attr_class(`max-w-xl ${copyFirst() ? "lg:order-1" : ""}`)}><p class="text-primary text-xs font-bold tracking-[0.22em] uppercase">${escape_html(eyebrow ?? story.eyebrow)}</p> <h2 class="text-foreground mt-4 text-3xl font-semibold leading-[1.05] tracking-[-0.035em] text-balance sm:text-4xl">${escape_html(story.title)}</h2> <p class="text-muted-foreground mt-5 max-w-[55ch] text-base leading-8 sm:text-lg">${escape_html(story.body)}</p> <div class="mt-7 h-px w-16 bg-primary/50" aria-hidden="true"></div></div></section>`);
  });
}
export {
  AdmissionsGateway as A,
  MediaFrame as M,
  ProgramExplorer as P,
  StorySection as S
};
