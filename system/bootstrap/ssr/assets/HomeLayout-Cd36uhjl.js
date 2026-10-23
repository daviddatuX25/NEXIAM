import { a as ensure_array_like, d as derived, e as escape_html, f as attr_style, b as attr, c as attr_class, u as usePage } from "../app.js";
import { T as ToastManager } from "./ToastManager-C7oO_4Jn.js";
import { P as PreferencesMenu, M as Menu } from "./PreferencesMenu-CCPeta-5.js";
import { L as Link } from "./Link-B7ks7rBK.js";
import { T as Triangle_alert } from "./triangle-alert-CUvuB-HL.js";
import { I as InstitutionalBackdrop } from "./InstitutionalBackdrop-BjxeRqDu.js";
import { X } from "./x-CnxrMbMn.js";
function NoticeContent($$renderer, alert) {
  $$renderer.push(`<div class="inline-flex items-center gap-2 text-xs">`);
  if (alert.priority === "urgent") {
    $$renderer.push("<!--[0-->");
    $$renderer.push(`<span class="inline-flex items-center gap-1 font-bold text-amber-300 text-xs shrink-0">`);
    Triangle_alert($$renderer, {
      class: "size-3.5 text-amber-300 shrink-0",
      "aria-hidden": "true"
    });
    $$renderer.push(`<!----> <span class="uppercase tracking-wider text-[11px]">Urgent</span></span>`);
  } else {
    $$renderer.push("<!--[-1-->");
  }
  $$renderer.push(`<!--]--> <span class="font-medium text-white tracking-tight max-w-[380px] truncate sm:max-w-none hover:underline underline-offset-2">${escape_html(alert.title)}</span> <span class="text-white/40 pl-3 shrink-0 select-none" aria-hidden="true">✦</span></div>`);
}
function AnnouncementTicker($$renderer, $$props) {
  $$renderer.component(($$renderer2) => {
    let { alerts = [] } = $$props;
    const items = derived(() => alerts.slice(0, 3));
    if (items().length > 0) {
      $$renderer2.push("<!--[0-->");
      $$renderer2.push(`<div class="border-b border-[#5e0c0e] bg-[#7B1113] text-white shadow-xs"><div class="mx-auto flex min-h-[44px] max-w-7xl px-3 sm:px-6 lg:px-8" role="region" aria-label="Public announcements" title="Hold or press Escape to pause moving titles."><span class="sr-only">Moving announcement titles stop when hovered or focused. Press
                Escape or press and hold here to stop them for this visit.
                Scroll horizontally or use the links to browse titles.</span> <div class="focus-visible:ring-white min-w-0 flex-1 overflow-x-hidden scrollbar-none sm:overflow-x-auto sm:scrollbar-none [&amp;::-webkit-scrollbar]:hidden [-ms-overflow-style:none] [scrollbar-width:none] focus-visible:ring-2 focus-visible:outline-none" role="region" aria-label="Announcement titles; scroll horizontally to browse" tabindex="0"><div class="flex w-max items-center whitespace-nowrap"><div class="flex shrink-0 items-center"><!--[-->`);
      const each_array = ensure_array_like(items());
      for (let $$index = 0, $$length = each_array.length; $$index < $$length; $$index++) {
        let alert = each_array[$$index];
        Link($$renderer2, {
          href: alert.href,
          class: "text-white/95 hover:text-white focus-visible:ring-white inline-flex min-h-[44px] shrink-0 items-center gap-3 py-1 pr-6 text-sm focus-visible:ring-2 focus-visible:outline-none sm:pr-8",
          children: ($$renderer3) => {
            NoticeContent($$renderer3, alert);
          },
          $$slots: { default: true }
        });
      }
      $$renderer2.push(`<!--]--></div> `);
      {
        $$renderer2.push("<!--[-1-->");
      }
      $$renderer2.push(`<!--]--></div></div></div></div>`);
    } else {
      $$renderer2.push("<!--[-1-->");
    }
    $$renderer2.push(`<!--]-->`);
  });
}
function HomeLayout($$renderer, $$props) {
  $$renderer.component(($$renderer2) => {
    let {
      children,
      alerts = [],
      homeHref = "/",
      navigation = null,
      institution = {
        name: "Ilocos Sur Polytechnic State College",
        short_name: "ISPSC",
        campus: "Tagudin Campus"
      }
    } = $$props;
    const page = usePage();
    const user = derived(() => page.props.auth?.user ?? null);
    let mobileNavOpen = false;
    let headerHeight = 56;
    const portalHref = derived(() => user()?.is_applicant ? "/applicant/portal" : "/applicant/login");
    const defaultNavigation = [
      { href: "/#mission", label: "About" },
      { href: "/#programs", label: "Programs" },
      { href: "/#admissions", label: "Admissions" },
      { href: "/#campus-life", label: "Campus life" },
      { href: "/#policies", label: "Public policies" }
    ];
    const navItems = derived(() => navigation ? [
      ...navigation.filter((item) => item.href !== "/#policies" && !item.href.includes("announcement")),
      { href: "/#policies", label: "Public policies" }
    ] : defaultNavigation);
    function closeMenu() {
      mobileNavOpen = false;
    }
    function isActive(href) {
      const [path, hash] = href.split("#");
      const current = page.url ?? "/";
      if (path && path !== current.split("?")[0]) return false;
      return hash ? current.includes(`#${hash}`) : current === "/";
    }
    $$renderer2.push(`<div class="public-home-shell bg-background/85 flex min-h-dvh flex-col font-sans relative svelte-1d9zxl5"${attr_style("", { "--public-header-height": `${headerHeight}px` })}>`);
    InstitutionalBackdrop($$renderer2, {
      fixed: true,
      opacity: "opacity-[0.22] sm:opacity-[0.28] dark:opacity-[0.15]"
    });
    $$renderer2.push(`<!----> `);
    ToastManager($$renderer2, {});
    $$renderer2.push(`<!----> <header class="border-border/70 bg-background/85 sticky top-0 z-50 border-b backdrop-blur-xl"><div class="mx-auto flex min-h-16 max-w-7xl items-center justify-between gap-2 px-4 sm:gap-4 sm:px-6 lg:px-8">`);
    Link($$renderer2, {
      href: homeHref,
      class: "group flex min-w-0 flex-1 items-center gap-3 xl:flex-none",
      "aria-label": `${institution.name} home`,
      onclick: closeMenu,
      children: ($$renderer3) => {
        $$renderer3.push(`<span class="bg-primary text-primary-foreground flex size-9 shrink-0 items-center justify-center rounded-xl text-base font-bold shadow-sm transition group-hover:-rotate-3" aria-hidden="true">${escape_html(institution.short_name?.slice(0, 1) ?? "I")}</span> <span class="min-w-0"><span class="text-foreground block truncate text-sm font-bold tracking-[-0.02em] sm:text-base">${escape_html(institution.name)}</span> <span class="text-muted-foreground hidden text-[0.68rem] font-medium tracking-[0.14em] uppercase sm:block">${escape_html(institution.campus)}</span></span>`);
      },
      $$slots: { default: true }
    });
    $$renderer2.push(`<!----> <nav class="hidden items-center gap-4 text-sm font-medium xl:flex" aria-label="Institutional navigation"><!--[-->`);
    const each_array = ensure_array_like(navItems());
    for (let $$index = 0, $$length = each_array.length; $$index < $$length; $$index++) {
      let item = each_array[$$index];
      $$renderer2.push(`<a${attr("href", item.href)}${attr_class(`text-muted-foreground hover:text-foreground min-h-11 px-1 py-3 whitespace-nowrap transition-colors ${isActive(item.href) ? "text-primary" : ""}`)}>${escape_html(item.label)}</a>`);
    }
    $$renderer2.push(`<!--]--></nav> <div class="hidden shrink-0 items-center gap-3 xl:flex">`);
    Link($$renderer2, {
      href: portalHref(),
      class: "text-muted-foreground hover:text-primary focus-visible:ring-ring inline-flex min-h-[44px] items-center text-sm font-medium whitespace-nowrap focus-visible:ring-2 focus-visible:outline-none",
      children: ($$renderer3) => {
        $$renderer3.push(`<!---->Applicant Portal`);
      },
      $$slots: { default: true }
    });
    $$renderer2.push(`<!----> `);
    Link($$renderer2, {
      href: "/apply",
      class: "bg-primary text-primary-foreground hover:bg-primary/90 focus-visible:ring-ring inline-flex min-h-[44px] items-center rounded-lg px-4 py-2 text-sm font-semibold focus-visible:ring-2 focus-visible:outline-none",
      children: ($$renderer3) => {
        $$renderer3.push(`<!---->Apply`);
      },
      $$slots: { default: true }
    });
    $$renderer2.push(`<!----> `);
    PreferencesMenu($$renderer2);
    $$renderer2.push(`<!----></div> <div class="flex items-center gap-2 xl:hidden">`);
    PreferencesMenu($$renderer2);
    $$renderer2.push(`<!----> <button type="button" class="text-foreground border-border focus-visible:ring-ring inline-flex min-h-[44px] min-w-[44px] shrink-0 items-center justify-center rounded-full border focus-visible:ring-2 focus-visible:outline-none"${attr("aria-label", mobileNavOpen ? "Close navigation menu" : "Open navigation menu")}${attr("aria-expanded", mobileNavOpen)} aria-controls="public-mobile-navigation">`);
    if (mobileNavOpen) {
      $$renderer2.push("<!--[0-->");
      X($$renderer2, { class: "size-5" });
    } else {
      $$renderer2.push("<!--[-1-->");
      Menu($$renderer2, { class: "size-5" });
    }
    $$renderer2.push(`<!--]--></button></div></div> `);
    if (mobileNavOpen) {
      $$renderer2.push("<!--[0-->");
      $$renderer2.push(`<div id="public-mobile-navigation" class="border-border/70 border-t xl:hidden"><nav class="mx-auto grid max-w-7xl gap-1 px-4 py-4 sm:px-6" aria-label="Mobile institutional navigation"><!--[-->`);
      const each_array_1 = ensure_array_like(navItems());
      for (let $$index_1 = 0, $$length = each_array_1.length; $$index_1 < $$length; $$index_1++) {
        let item = each_array_1[$$index_1];
        $$renderer2.push(`<a${attr("href", item.href)}${attr_class(`text-foreground hover:bg-muted flex min-h-[44px] items-center rounded-lg px-3 py-2 text-sm font-semibold transition ${isActive(item.href) ? "bg-primary/10 text-primary" : ""}`)}>${escape_html(item.label)}</a>`);
      }
      $$renderer2.push(`<!--]--> <div class="border-border/60 mt-2 grid gap-2 border-t pt-3">`);
      Link($$renderer2, {
        href: "/apply",
        class: "bg-primary text-primary-foreground inline-flex min-h-[44px] items-center justify-center rounded-lg px-4 py-2 text-sm font-semibold",
        onclick: closeMenu,
        children: ($$renderer3) => {
          $$renderer3.push(`<!---->Apply`);
        },
        $$slots: { default: true }
      });
      $$renderer2.push(`<!----> `);
      Link($$renderer2, {
        href: portalHref(),
        class: "text-muted-foreground hover:text-primary inline-flex min-h-[44px] items-center px-3 py-2 text-sm font-medium",
        onclick: closeMenu,
        children: ($$renderer3) => {
          $$renderer3.push(`<!---->Applicant Portal`);
        },
        $$slots: { default: true }
      });
      $$renderer2.push(`<!----></div></nav></div>`);
    } else {
      $$renderer2.push("<!--[-1-->");
    }
    $$renderer2.push(`<!--]--> `);
    if (alerts.length > 0) {
      $$renderer2.push("<!--[0-->");
      AnnouncementTicker($$renderer2, { alerts });
    } else {
      $$renderer2.push("<!--[-1-->");
    }
    $$renderer2.push(`<!--]--></header> <main class="flex-grow">`);
    children($$renderer2);
    $$renderer2.push(`<!----></main> <footer id="contact" class="border-border/70 bg-card/50 mt-auto border-t"><div class="mx-auto grid max-w-7xl gap-10 px-4 py-12 sm:px-6 md:grid-cols-[1.15fr_0.85fr_0.85fr] lg:px-8"><div><p class="text-primary text-xs font-bold tracking-[0.22em] uppercase">Visit / contact</p> <h2 class="text-foreground mt-3 max-w-[16ch] text-2xl leading-tight font-semibold tracking-[-0.03em]">Begin with a conversation.</h2> <p class="text-muted-foreground mt-4 max-w-[38ch] text-sm leading-6">${escape_html(institution.visit_note ?? "Contact the campus for approved visit and inquiry details.")}</p></div> <div><p class="text-foreground text-sm font-semibold">Explore</p> <div class="mt-4 grid gap-2 text-sm"><a href="/#mission" class="text-muted-foreground hover:text-primary py-1 transition">About the institution</a> <a href="/#programs" class="text-muted-foreground hover:text-primary py-1 transition">Programs</a> <a href="/#admissions" class="text-muted-foreground hover:text-primary py-1 transition">Admissions</a> <a href="/#campus-life" class="text-muted-foreground hover:text-primary py-1 transition">Campus life</a> `);
    Link($$renderer2, {
      href: "/announcements",
      class: "text-muted-foreground hover:text-primary py-1 transition",
      children: ($$renderer3) => {
        $$renderer3.push(`<!---->Announcements`);
      },
      $$slots: { default: true }
    });
    $$renderer2.push(`<!----> `);
    Link($$renderer2, {
      href: "/policies",
      class: "text-muted-foreground hover:text-primary py-1 transition",
      children: ($$renderer3) => {
        $$renderer3.push(`<!---->Public policies`);
      },
      $$slots: { default: true }
    });
    $$renderer2.push(`<!----></div></div> <div><p class="text-foreground text-sm font-semibold">NEXIAM services</p> <div class="mt-4 grid gap-2 text-sm">`);
    Link($$renderer2, {
      href: "/apply",
      class: "text-muted-foreground hover:text-primary py-1 transition",
      children: ($$renderer3) => {
        $$renderer3.push(`<!---->Start an application`);
      },
      $$slots: { default: true }
    });
    $$renderer2.push(`<!----> `);
    Link($$renderer2, {
      href: portalHref(),
      class: "text-muted-foreground hover:text-primary py-1 transition",
      children: ($$renderer3) => {
        $$renderer3.push(`<!---->Applicant Portal`);
      },
      $$slots: { default: true }
    });
    $$renderer2.push(`<!----></div></div></div> <div class="border-border/60 border-t"><div class="text-muted-foreground mx-auto flex max-w-7xl flex-col gap-2 px-4 py-5 text-xs sm:flex-row sm:items-center sm:justify-between sm:px-6 lg:px-8"><p>© ${escape_html((/* @__PURE__ */ new Date()).getFullYear())}
                    ${escape_html(institution.name)} · ${escape_html(institution.campus)}.</p> <p class="text-muted-foreground/70">Admissions and testing powered by NEXIAM.</p></div></div></footer></div>`);
  });
}
export {
  HomeLayout as H
};
