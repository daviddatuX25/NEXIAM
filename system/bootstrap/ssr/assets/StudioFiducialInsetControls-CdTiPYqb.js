import { a as ensure_array_like, e as escape_html, b as attr, l as bind_props } from "../app.js";
import "axios";
import "@inertiajs/core";
import "clsx";
import "laravel-precognition";
import "tailwind-merge";
import "tailwind-variants";
function StudioFiducialInsetControls($$renderer, $$props) {
  $$renderer.component(($$renderer2) => {
    let {
      markerInsetsMm = { top: 12, right: 12, bottom: 14, left: 12 },
      disabled = false,
      onPreview = null,
      onCommit = null
    } = $$props;
    const sides = [
      { key: "top", label: "Top" },
      { key: "right", label: "Right" },
      { key: "bottom", label: "Bottom" },
      { key: "left", label: "Left" }
    ];
    $$renderer2.push(`<div class="space-y-2.5 rounded-xl border border-slate-200 bg-slate-50 p-2.5"><div class="space-y-0.5"><div class="flex items-center justify-between gap-2"><span class="text-[11px] font-bold text-slate-700">Fiducial Insets</span> `);
    if (disabled) {
      $$renderer2.push("<!--[0-->");
      $$renderer2.push(`<span class="text-[9px] font-semibold text-amber-700">Select a profile first</span>`);
    } else {
      $$renderer2.push("<!--[-1-->");
    }
    $$renderer2.push(`<!--]--></div> <p class="text-[9.5px] leading-relaxed text-slate-500">Moves the profile-owned bullseyes independently from the content margin. Marker shape remains scanner-controlled.</p></div> <div class="grid grid-cols-2 gap-x-3 gap-y-2"><!--[-->`);
    const each_array = ensure_array_like(sides);
    for (let $$index = 0, $$length = each_array.length; $$index < $$length; $$index++) {
      let side = each_array[$$index];
      $$renderer2.push(`<label class="min-w-0 space-y-1"><span class="flex items-center justify-between gap-2 text-[10px] font-semibold text-slate-600"><span>${escape_html(side.label)}</span> <span class="font-mono text-[9px] text-slate-700">${escape_html(markerInsetsMm[side.key])} mm</span></span> <input type="range" min="3" max="40" step="1"${attr("value", markerInsetsMm[side.key])}${attr("disabled", disabled, true)}${attr("aria-label", `${side.label} fiducial inset`)} class="w-full accent-slate-900 disabled:cursor-not-allowed disabled:opacity-40"/></label>`);
    }
    $$renderer2.push(`<!--]--></div></div>`);
    bind_props($$props, { markerInsetsMm });
  });
}
export {
  StudioFiducialInsetControls as default
};
