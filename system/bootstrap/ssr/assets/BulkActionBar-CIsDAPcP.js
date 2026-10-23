import { c as attr_class, e as escape_html, B as Button, s as stringify } from "../app.js";
import { X } from "./x-CnxrMbMn.js";
function BulkActionBar($$renderer, $$props) {
  let {
    count = 0,
    hint = "",
    onClear = void 0,
    actions,
    class: className = ""
  } = $$props;
  if (count > 0) {
    $$renderer.push("<!--[0-->");
    $$renderer.push(`<div${attr_class(`flex flex-wrap items-center gap-3 rounded-lg border border-primary/20 bg-primary/5 px-4 py-3 text-sm ${stringify(className)}`)} role="status" aria-live="polite"><div class="flex items-center gap-2 font-medium"><span class="inline-flex h-6 min-w-[1.5rem] items-center justify-center rounded-full bg-primary px-1.5 text-xs font-bold text-primary-foreground">${escape_html(count)}</span> <span>selected</span></div> `);
    if (hint) {
      $$renderer.push("<!--[0-->");
      $$renderer.push(`<span class="hidden text-xs text-muted-foreground sm:inline">·</span> <span class="hidden text-xs text-muted-foreground sm:inline">${escape_html(hint)}</span>`);
    } else {
      $$renderer.push("<!--[-1-->");
    }
    $$renderer.push(`<!--]--> <div class="ml-auto flex flex-wrap items-center gap-2">`);
    if (actions) {
      $$renderer.push("<!--[0-->");
      actions($$renderer);
      $$renderer.push(`<!---->`);
    } else {
      $$renderer.push("<!--[-1-->");
    }
    $$renderer.push(`<!--]--> `);
    if (onClear) {
      $$renderer.push("<!--[0-->");
      Button($$renderer, {
        size: "sm",
        variant: "ghost",
        class: "gap-1 text-muted-foreground",
        onclick: onClear,
        children: ($$renderer2) => {
          X($$renderer2, { class: "h-3.5 w-3.5" });
          $$renderer2.push(`<!----> Clear`);
        },
        $$slots: { default: true }
      });
    } else {
      $$renderer.push("<!--[-1-->");
    }
    $$renderer.push(`<!--]--></div></div>`);
  } else {
    $$renderer.push("<!--[-1-->");
  }
  $$renderer.push(`<!--]-->`);
}
export {
  BulkActionBar as B
};
