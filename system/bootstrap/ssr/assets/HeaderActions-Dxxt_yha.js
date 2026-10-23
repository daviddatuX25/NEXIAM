import "clsx";
import { e as Popover, f as Popover_trigger, h as Popover_content } from "./PreferencesMenu-CCPeta-5.js";
import { B as Button } from "../app.js";
import { E as Ellipsis_vertical } from "./settings-C5xOKpV3.js";
function HeaderActions($$renderer, $$props) {
  let { primaryAction = null, secondaryActions = null } = $$props;
  let open = false;
  let $$settled = true;
  let $$inner_renderer;
  function $$render_inner($$renderer2) {
    $$renderer2.push(`<div class="flex items-center gap-2"><div class="hidden items-center gap-2 sm:flex">`);
    if (secondaryActions) {
      $$renderer2.push("<!--[0-->");
      secondaryActions($$renderer2);
      $$renderer2.push(`<!---->`);
    } else {
      $$renderer2.push("<!--[-1-->");
    }
    $$renderer2.push(`<!--]--> `);
    if (primaryAction) {
      $$renderer2.push("<!--[0-->");
      primaryAction($$renderer2);
      $$renderer2.push(`<!---->`);
    } else {
      $$renderer2.push("<!--[-1-->");
    }
    $$renderer2.push(`<!--]--></div> <div class="flex min-w-0 flex-1 items-center gap-2 sm:hidden">`);
    if (primaryAction) {
      $$renderer2.push("<!--[0-->");
      $$renderer2.push(`<div class="min-w-0 flex-1">`);
      primaryAction($$renderer2);
      $$renderer2.push(`<!----></div>`);
    } else {
      $$renderer2.push("<!--[-1-->");
    }
    $$renderer2.push(`<!--]--> `);
    if (secondaryActions) {
      $$renderer2.push("<!--[0-->");
      if (Popover) {
        $$renderer2.push("<!--[-->");
        Popover($$renderer2, {
          get open() {
            return open;
          },
          set open($$value) {
            open = $$value;
            $$settled = false;
          },
          children: ($$renderer3) => {
            if (Popover_trigger) {
              $$renderer3.push("<!--[-->");
              Popover_trigger($$renderer3, {
                children: ($$renderer4) => {
                  Button($$renderer4, {
                    variant: "outline",
                    size: "icon",
                    class: "min-h-10 min-w-10",
                    "aria-label": "More actions",
                    children: ($$renderer5) => {
                      Ellipsis_vertical($$renderer5, { class: "size-4" });
                    },
                    $$slots: { default: true }
                  });
                },
                $$slots: { default: true }
              });
              $$renderer3.push("<!--]-->");
            } else {
              $$renderer3.push("<!--[!-->");
              $$renderer3.push("<!--]-->");
            }
            $$renderer3.push(` `);
            if (Popover_content) {
              $$renderer3.push("<!--[-->");
              Popover_content($$renderer3, {
                class: "w-56 p-2 space-y-1",
                align: "end",
                children: ($$renderer4) => {
                  secondaryActions($$renderer4);
                  $$renderer4.push(`<!---->`);
                },
                $$slots: { default: true }
              });
              $$renderer3.push("<!--]-->");
            } else {
              $$renderer3.push("<!--[!-->");
              $$renderer3.push("<!--]-->");
            }
          },
          $$slots: { default: true }
        });
        $$renderer2.push("<!--]-->");
      } else {
        $$renderer2.push("<!--[!-->");
        $$renderer2.push("<!--]-->");
      }
    } else {
      $$renderer2.push("<!--[-1-->");
    }
    $$renderer2.push(`<!--]--></div></div>`);
  }
  do {
    $$settled = true;
    $$inner_renderer = $$renderer.copy();
    $$render_inner($$inner_renderer);
  } while (!$$settled);
  $$renderer.subsume($$inner_renderer);
}
export {
  HeaderActions as H
};
