import { b as attr, a as ensure_array_like, e as escape_html, c as attr_class, l as bind_props } from "../app.js";
import { F as FONT_FAMILY_OPTIONS } from "./omrComponents-D81Zk9FJ.js";
import { B as Bold, T as Text_align_justify } from "./text-align-justify-CFymVZbq.js";
import { T as Text_align_start, a as Text_align_center, b as Text_align_end } from "./text-align-start-Cy9u9QqD.js";
import "axios";
import "@inertiajs/core";
import "clsx";
import "laravel-precognition";
import "tailwind-merge";
import "tailwind-variants";
import "./omrStudioGeometry-DyCkNaaw.js";
import "./Icon-Dal7PqKK.js";
function StudioTypographyControls($$renderer, $$props) {
  $$renderer.component(($$renderer2) => {
    let { typography = null, onChanged = null } = $$props;
    function handleInput() {
      if (onChanged) onChanged();
    }
    if (typography) {
      $$renderer2.push("<!--[0-->");
      $$renderer2.push(`<div class="space-y-2 border-t border-slate-200/80 pt-2"><div class="flex items-center justify-between"><span class="text-[10px] font-bold text-slate-500 uppercase tracking-wide">Typography</span></div> <div class="grid grid-cols-2 gap-2"><div class="space-y-0.5"><label class="text-[10px] font-semibold text-slate-600 block">Font Size (pt)</label> <input type="number" min="6" max="28" step="0.5"${attr("value", typography.fontSizePt)} class="w-full px-2 py-1 bg-white border border-slate-200 rounded-lg text-xs font-semibold"/></div> <div class="space-y-0.5"><label class="text-[10px] font-semibold text-slate-600 block">Font Family</label> `);
      $$renderer2.select(
        {
          value: typography.fontFamily,
          onchange: handleInput,
          class: "w-full px-2 py-1 bg-white border border-slate-200 rounded-lg text-[11px]"
        },
        ($$renderer3) => {
          $$renderer3.push(`<!--[-->`);
          const each_array = ensure_array_like(FONT_FAMILY_OPTIONS);
          for (let $$index = 0, $$length = each_array.length; $$index < $$length; $$index++) {
            let font = each_array[$$index];
            $$renderer3.option({ value: font.value }, ($$renderer4) => {
              $$renderer4.push(`${escape_html(font.label)}`);
            });
          }
          $$renderer3.push(`<!--]-->`);
        }
      );
      $$renderer2.push(`</div></div> <div class="flex items-center gap-1.5"><button type="button"${attr_class(`p-1.5 rounded-lg border transition ${(typography.fontWeight ?? 400) >= 700 ? "bg-slate-900 text-white border-slate-900" : "bg-white text-slate-600 border-slate-200 hover:bg-slate-100"}`)} title="Bold">`);
      Bold($$renderer2, { class: "size-3.5" });
      $$renderer2.push(`<!----></button> <div class="flex items-center bg-slate-100 rounded-lg border border-slate-200 p-0.5"><button type="button"${attr_class(`p-1 rounded-md transition ${typography.textAlign === "left" ? "bg-white shadow-xs" : "text-slate-500"}`)} title="Align left">`);
      Text_align_start($$renderer2, { class: "size-3" });
      $$renderer2.push(`<!----></button> <button type="button"${attr_class(`p-1 rounded-md transition ${typography.textAlign === "center" ? "bg-white shadow-xs" : "text-slate-500"}`)} title="Align center">`);
      Text_align_center($$renderer2, { class: "size-3" });
      $$renderer2.push(`<!----></button> <button type="button"${attr_class(`p-1 rounded-md transition ${typography.textAlign === "right" ? "bg-white shadow-xs" : "text-slate-500"}`)} title="Align right">`);
      Text_align_end($$renderer2, { class: "size-3" });
      $$renderer2.push(`<!----></button> <button type="button"${attr_class(`p-1 rounded-md transition ${typography.textAlign === "justify" ? "bg-white shadow-xs" : "text-slate-500"}`)} title="Justify text">`);
      Text_align_justify($$renderer2, { class: "size-3" });
      $$renderer2.push(`<!----></button></div> <div class="flex items-center gap-1 ml-auto"><input type="color"${attr("value", typography.textColor)} class="w-7 h-7 rounded-lg border border-slate-200 bg-white cursor-pointer p-0.5" title="Text color"/> <span class="text-[9.5px] font-mono text-slate-500">${escape_html(typography.textColor)}</span></div></div></div>`);
    } else {
      $$renderer2.push("<!--[-1-->");
    }
    $$renderer2.push(`<!--]-->`);
    bind_props($$props, { typography });
  });
}
export {
  StudioTypographyControls as default
};
