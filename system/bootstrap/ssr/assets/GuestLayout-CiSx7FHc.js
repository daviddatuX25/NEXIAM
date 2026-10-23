import { h as head, e as escape_html, B as Button, a1 as toggleTheme, a2 as isDarkMode, s as stringify, c as attr_class, d as derived, u as usePage } from "../app.js";
import { T as ToastManager } from "./ToastManager-C7oO_4Jn.js";
import { I as InstitutionalBackdrop, S as Sun, M as Moon } from "./InstitutionalBackdrop-BjxeRqDu.js";
import { L as Link } from "./Link-B7ks7rBK.js";
function GuestLayout($$renderer, $$props) {
  $$renderer.component(($$renderer2) => {
    let { children, maxWidth = "max-w-md" } = $$props;
    const page = usePage();
    const institution = derived(() => page.props.institution ?? {
      name: "Ilocos Sur Polytechnic State College",
      short_name: "ISPSC",
      campus: "Tagudin Campus"
    });
    head("8s5214", $$renderer2, ($$renderer3) => {
      $$renderer3.title(($$renderer4) => {
        $$renderer4.push(`<title>${escape_html(institution().short_name ?? "ISPSC")} · Portal</title>`);
      });
    });
    $$renderer2.push(`<div class="relative flex min-h-dvh flex-col items-center justify-center overflow-hidden px-4 py-12 sm:px-6 lg:px-8">`);
    InstitutionalBackdrop($$renderer2, {
      opacity: "opacity-[0.32] sm:opacity-[0.38] dark:opacity-[0.22]"
    });
    $$renderer2.push(`<!----> <div class="pointer-events-none absolute -top-[10%] -right-[10%] h-[380px] w-[380px] rounded-full bg-gradient-to-tr from-primary/15 to-emerald-400/10 blur-3xl" aria-hidden="true"></div> <div class="pointer-events-none absolute bottom-[5%] -left-[12%] h-[420px] w-[420px] rounded-full bg-gradient-to-bl from-primary/10 to-amber-200/10 blur-3xl" aria-hidden="true"></div> `);
    Button($$renderer2, {
      variant: "ghost",
      size: "icon",
      onclick: toggleTheme,
      "aria-label": isDarkMode() ? "Switch to light mode" : "Switch to dark mode",
      class: "absolute top-4 right-4 z-20 rounded-full min-h-[44px] min-w-[44px]",
      children: ($$renderer3) => {
        Sun($$renderer3, { class: "h-5 w-5 dark:hidden" });
        $$renderer3.push(`<!----> `);
        Moon($$renderer3, { class: "h-5 w-5 hidden dark:block" });
        $$renderer3.push(`<!---->`);
      },
      $$slots: { default: true }
    });
    $$renderer2.push(`<!----> `);
    Link($$renderer2, {
      href: "/",
      class: "group relative z-10 mb-8 flex items-center gap-3.5 no-underline transition-transform hover:scale-[1.01]",
      "aria-label": `${stringify(institution().name)} home`,
      children: ($$renderer3) => {
        $$renderer3.push(`<span class="flex size-11 shrink-0 items-center justify-center rounded-2xl bg-primary text-primary-foreground font-extrabold text-xl shadow-md shadow-primary/25 transition-transform group-hover:-rotate-3" aria-hidden="true">${escape_html(institution().short_name?.slice(0, 1) ?? "I")}</span> <span class="text-left"><span class="block text-lg font-bold tracking-tight text-foreground sm:text-xl leading-tight">${escape_html(institution().name)}</span> <span class="block text-[0.68rem] font-semibold uppercase tracking-[0.18em] text-muted-foreground">${escape_html(institution().campus)}</span></span>`);
      },
      $$slots: { default: true }
    });
    $$renderer2.push(`<!----> <div${attr_class(`relative z-10 w-full ${stringify(maxWidth)}`)}>`);
    children?.($$renderer2);
    $$renderer2.push(`<!----></div> <div class="relative z-10 mt-8 flex flex-col items-center gap-2 text-center text-xs text-muted-foreground">`);
    Link($$renderer2, {
      href: "/",
      class: "text-xs font-medium text-foreground hover:text-primary transition-colors underline underline-offset-4",
      children: ($$renderer3) => {
        $$renderer3.push(`<!---->← Back to ${escape_html(institution().short_name ?? "institutional")} home`);
      },
      $$slots: { default: true }
    });
    $$renderer2.push(`<!----> <p class="mt-1 flex items-center gap-1.5 text-muted-foreground/75 text-[11px]"><span>Admissions &amp; testing powered by</span> <span class="inline-flex items-center gap-1 font-bold text-foreground/85"><span class="inline-flex size-3.5 items-center justify-center rounded bg-primary text-[9px] font-black text-primary-foreground">N</span> NEXIAM</span></p></div></div> `);
    ToastManager($$renderer2, {});
    $$renderer2.push(`<!---->`);
  });
}
export {
  GuestLayout as G
};
