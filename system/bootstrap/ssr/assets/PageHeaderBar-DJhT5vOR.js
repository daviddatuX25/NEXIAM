import { c as attr_class, e as escape_html, s as stringify } from "../app.js";
import { T as TourTriggerGlyph, X as XiamNpcDialog } from "./XiamNpcDialog-DoqCSHj9.js";
function PageHeaderBar($$renderer, $$props) {
  let {
    title = "",
    description = "",
    actions,
    class: className = "",
    tourKey = null,
    context = void 0
  } = $$props;
  let isTourOpen = false;
  let $$settled = true;
  let $$inner_renderer;
  function $$render_inner($$renderer2) {
    $$renderer2.push(`<div${attr_class(`flex flex-col sm:flex-row sm:items-start justify-between gap-3 min-w-0 ${stringify(className)}`)}><div class="min-w-0">`);
    if (title) {
      $$renderer2.push("<!--[0-->");
      $$renderer2.push(`<div class="flex items-center gap-2"><h1 class="text-2xl font-semibold tracking-tight text-foreground sm:text-3xl">${escape_html(title)}</h1> `);
      if (tourKey) {
        $$renderer2.push("<!--[0-->");
        TourTriggerGlyph($$renderer2, { tourKey, onclick: () => isTourOpen = true });
      } else {
        $$renderer2.push("<!--[-1-->");
      }
      $$renderer2.push(`<!--]--></div>`);
    } else {
      $$renderer2.push("<!--[-1-->");
    }
    $$renderer2.push(`<!--]--> `);
    if (description) {
      $$renderer2.push("<!--[0-->");
      $$renderer2.push(`<p${attr_class(`text-sm text-muted-foreground ${title ? "mt-1" : ""}`)}>${escape_html(description)}</p>`);
    } else {
      $$renderer2.push("<!--[-1-->");
    }
    $$renderer2.push(`<!--]--></div> `);
    if (actions) {
      $$renderer2.push("<!--[0-->");
      $$renderer2.push(`<div class="flex flex-wrap items-center sm:justify-end gap-2 sm:gap-3 w-full sm:w-auto shrink-0">`);
      actions($$renderer2);
      $$renderer2.push(`<!----></div>`);
    } else {
      $$renderer2.push("<!--[-1-->");
    }
    $$renderer2.push(`<!--]--></div> `);
    if (tourKey) {
      $$renderer2.push("<!--[0-->");
      XiamNpcDialog($$renderer2, {
        tourKey,
        context,
        get open() {
          return isTourOpen;
        },
        set open($$value) {
          isTourOpen = $$value;
          $$settled = false;
        }
      });
    } else {
      $$renderer2.push("<!--[-1-->");
    }
    $$renderer2.push(`<!--]-->`);
  }
  do {
    $$settled = true;
    $$inner_renderer = $$renderer.copy();
    $$render_inner($$inner_renderer);
  } while (!$$settled);
  $$renderer.subsume($$inner_renderer);
}
export {
  PageHeaderBar as P
};
