import { c as attr_class, A as clsx, C as cn, e as escape_html } from "../app.js";
function ModeBanner($$renderer, $$props) {
  $$renderer.component(($$renderer2) => {
    let {
      variant = "info",
      title = "",
      class: className = "",
      children
    } = $$props;
    const variants = {
      info: "border-border bg-muted/40",
      primary: "border-primary/30 bg-primary/5",
      warning: "border-amber-500/40 bg-amber-500/10",
      danger: "border-destructive/30 bg-destructive/5"
    };
    $$renderer2.push(`<div${attr_class(clsx(cn("rounded-lg border px-4 py-3 text-sm", variants[variant] ?? variants.info, className)))} role="note">`);
    if (title) {
      $$renderer2.push("<!--[0-->");
      $$renderer2.push(`<strong class="text-foreground">${escape_html(title)}</strong> `);
      if (children) {
        $$renderer2.push("<!--[0-->");
        $$renderer2.push(`<span class="text-muted-foreground"></span>`);
      } else {
        $$renderer2.push("<!--[-1-->");
      }
      $$renderer2.push(`<!--]-->`);
    } else {
      $$renderer2.push("<!--[-1-->");
    }
    $$renderer2.push(`<!--]--> `);
    if (children) {
      $$renderer2.push("<!--[0-->");
      $$renderer2.push(`<span class="text-foreground/90">`);
      children($$renderer2);
      $$renderer2.push(`<!----></span>`);
    } else {
      $$renderer2.push("<!--[-1-->");
    }
    $$renderer2.push(`<!--]--></div>`);
  });
}
export {
  ModeBanner as M
};
