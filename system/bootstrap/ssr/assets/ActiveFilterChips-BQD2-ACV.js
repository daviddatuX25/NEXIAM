import { c as attr_class, a as ensure_array_like, e as escape_html, b as attr, s as stringify, B as Button } from "../app.js";
import { B as Badge } from "./badge-D9Vgs7xh.js";
import { X } from "./x-CnxrMbMn.js";
function ActiveFilterChips($$renderer, $$props) {
  $$renderer.component(($$renderer2) => {
    let {
      chips = (
        /** @type {Chip[]} */
        []
      ),
      onRemove = (
        /** @type {(id: string) => void} */
        () => {
        }
      ),
      onClearAll = void 0,
      class: className = ""
    } = $$props;
    if (chips.length > 0) {
      $$renderer2.push("<!--[0-->");
      $$renderer2.push(`<div${attr_class(`flex flex-wrap items-center gap-2 ${stringify(className)}`)} aria-label="Active filters"><!--[-->`);
      const each_array = ensure_array_like(chips);
      for (let $$index = 0, $$length = each_array.length; $$index < $$length; $$index++) {
        let chip = each_array[$$index];
        Badge($$renderer2, {
          variant: "outline",
          class: "gap-1 pr-1 font-normal",
          children: ($$renderer3) => {
            $$renderer3.push(`<span>${escape_html(chip.label)}</span> <button type="button" class="inline-flex size-5 items-center justify-center rounded-md hover:bg-muted"${attr("aria-label", `Remove filter ${stringify(chip.label)}`)}>`);
            X($$renderer3, { class: "h-3 w-3" });
            $$renderer3.push(`<!----></button>`);
          },
          $$slots: { default: true }
        });
      }
      $$renderer2.push(`<!--]--> `);
      if (onClearAll) {
        $$renderer2.push("<!--[0-->");
        Button($$renderer2, {
          size: "sm",
          variant: "ghost",
          class: "h-7 px-2 text-xs text-muted-foreground",
          onclick: onClearAll,
          children: ($$renderer3) => {
            $$renderer3.push(`<!---->Clear all`);
          },
          $$slots: { default: true }
        });
      } else {
        $$renderer2.push("<!--[-1-->");
      }
      $$renderer2.push(`<!--]--></div>`);
    } else {
      $$renderer2.push("<!--[-1-->");
    }
    $$renderer2.push(`<!--]-->`);
  });
}
export {
  ActiveFilterChips as A
};
