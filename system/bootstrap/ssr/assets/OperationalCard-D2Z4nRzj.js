import { e as escape_html, b as attr, c as attr_class, l as bind_props, d as derived } from "../app.js";
function OperationalCard($$renderer, $$props) {
  $$renderer.component(($$renderer2) => {
    let {
      id = "",
      title = "",
      subtitle = "",
      status = null,
      selection = null,
      summary = null,
      details = null,
      actions = null,
      expanded = void 0
    } = $$props;
    const isExpanded = derived(() => Boolean(expanded));
    const detailsId = `card-details-${id}`;
    $$renderer2.push(`<li class="group flex flex-col justify-between rounded-xl border border-border/80 bg-card p-4 shadow-xs transition-all hover:border-primary/40 hover:shadow-sm"><div class="space-y-2.5"><div class="flex items-start justify-between gap-3"><div class="flex min-w-0 items-start gap-2.5">`);
    if (selection) {
      $$renderer2.push("<!--[0-->");
      $$renderer2.push(`<div class="pt-0.5">`);
      selection($$renderer2);
      $$renderer2.push(`<!----></div>`);
    } else {
      $$renderer2.push("<!--[-1-->");
    }
    $$renderer2.push(`<!--]--> <div class="min-w-0"><h3 class="truncate text-sm font-bold text-foreground">${escape_html(title)}</h3> `);
    if (subtitle) {
      $$renderer2.push("<!--[0-->");
      $$renderer2.push(`<p class="truncate text-xs text-muted-foreground">${escape_html(subtitle)}</p>`);
    } else {
      $$renderer2.push("<!--[-1-->");
    }
    $$renderer2.push(`<!--]--></div></div> `);
    if (status) {
      $$renderer2.push("<!--[0-->");
      $$renderer2.push(`<div class="shrink-0">`);
      status($$renderer2);
      $$renderer2.push(`<!----></div>`);
    } else {
      $$renderer2.push("<!--[-1-->");
    }
    $$renderer2.push(`<!--]--></div> `);
    if (summary) {
      $$renderer2.push("<!--[0-->");
      $$renderer2.push(`<div class="text-xs text-muted-foreground">`);
      summary($$renderer2);
      $$renderer2.push(`<!----></div>`);
    } else {
      $$renderer2.push("<!--[-1-->");
    }
    $$renderer2.push(`<!--]--></div> `);
    if (isExpanded() && details) {
      $$renderer2.push("<!--[0-->");
      $$renderer2.push(`<div${attr("id", detailsId)} class="mt-3 space-y-2.5 border-t border-border/60 pt-3 text-xs animate-in fade-in-50 duration-150">`);
      details($$renderer2);
      $$renderer2.push(`<!----></div>`);
    } else {
      $$renderer2.push("<!--[-1-->");
    }
    $$renderer2.push(`<!--]--> <div class="mt-3 flex items-center justify-between gap-2 border-t border-border/50 pt-2.5"><div class="flex-1">`);
    if (actions) {
      $$renderer2.push("<!--[0-->");
      actions($$renderer2);
      $$renderer2.push(`<!---->`);
    } else {
      $$renderer2.push("<!--[-1-->");
    }
    $$renderer2.push(`<!--]--></div> `);
    if (details) {
      $$renderer2.push("<!--[0-->");
      $$renderer2.push(`<button type="button" class="inline-flex min-h-[36px] items-center gap-1 rounded-md px-2.5 text-xs font-semibold text-muted-foreground hover:bg-muted hover:text-foreground focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring"${attr("aria-expanded", isExpanded())}${attr("aria-controls", detailsId)}><span>${escape_html(isExpanded() ? "Less" : "Details")}</span> <span${attr_class(`text-[10px] transition-transform ${isExpanded() ? "rotate-180" : ""}`)}>▼</span></button>`);
    } else {
      $$renderer2.push("<!--[-1-->");
    }
    $$renderer2.push(`<!--]--></div></li>`);
    bind_props($$props, { expanded });
  });
}
export {
  OperationalCard as O
};
