import { e as escape_html, b as attr, l as bind_props } from "../app.js";
import "axios";
import "@inertiajs/core";
import "clsx";
import "laravel-precognition";
import "tailwind-merge";
import "tailwind-variants";
function StudioComponentAppearanceControls($$renderer, $$props) {
  $$renderer.component(($$renderer2) => {
    let { backgroundColor = "transparent", onChanged = null } = $$props;
    $$renderer2.push(`<div class="space-y-1.5 bg-slate-50/80 p-2 rounded-xl border border-slate-100"><div class="flex items-center justify-between"><span class="text-[10px] font-semibold text-slate-600">Component Background</span> <span class="text-[9px] font-mono text-slate-400">${escape_html(backgroundColor)}</span></div> <div class="flex items-center gap-2"><label class="flex items-center gap-1.5 text-[10px] text-slate-600 cursor-pointer"><input type="checkbox"${attr("checked", backgroundColor === "transparent", true)} class="rounded text-slate-900"/> Transparent</label> <input type="color"${attr("value", backgroundColor === "transparent" ? "#ffffff" : backgroundColor)}${attr("disabled", backgroundColor === "transparent", true)} class="h-7 w-12 p-0.5 bg-white border border-slate-200 rounded cursor-pointer disabled:opacity-40" aria-label="Component background color"/> <span class="text-[9px] text-slate-400">White / black / custom</span></div></div>`);
    bind_props($$props, { backgroundColor });
  });
}
export {
  StudioComponentAppearanceControls as default
};
