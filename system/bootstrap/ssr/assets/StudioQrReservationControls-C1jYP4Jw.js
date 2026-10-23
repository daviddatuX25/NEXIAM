import { b as attr, c as attr_class, e as escape_html, l as bind_props } from "../app.js";
import { L as Lock } from "./lock-DAA4kTLl.js";
import { L as Lock_open } from "./lock-open-5Nny5RV8.js";
import "axios";
import "@inertiajs/core";
import "clsx";
import "laravel-precognition";
import "tailwind-merge";
import "tailwind-variants";
import "./Icon-Dal7PqKK.js";
function StudioQrReservationControls($$renderer, $$props) {
  $$renderer.component(($$renderer2) => {
    let {
      showQrOverlay = true,
      qrX = 162,
      qrY = 18,
      qrW = 26,
      qrH = 26,
      qrLocked = false,
      onChanged = null
    } = $$props;
    $$renderer2.push(`<div class="space-y-2.5"><div class="flex items-center justify-between"><label class="flex items-center gap-2 cursor-pointer"><input type="checkbox"${attr("checked", showQrOverlay, true)} class="rounded text-slate-900"/> <span class="text-[11px] font-bold text-slate-800">Blank Instance QR</span></label> `);
    if (showQrOverlay) {
      $$renderer2.push("<!--[0-->");
      $$renderer2.push(`<button type="button"${attr_class(`px-2 py-0.5 rounded text-[10px] font-semibold flex items-center gap-1 border transition ${qrLocked ? "bg-amber-100 text-amber-900 border-amber-300" : "bg-white text-slate-700 border-slate-200"}`)}${attr("title", qrLocked ? "Unlock QR reservation" : "Lock QR reservation")}>`);
      if (qrLocked) {
        $$renderer2.push("<!--[0-->");
        Lock($$renderer2, { class: "size-2.5 text-amber-700" });
        $$renderer2.push(`<!----> <span>Locked</span>`);
      } else {
        $$renderer2.push("<!--[-1-->");
        Lock_open($$renderer2, { class: "size-2.5 text-slate-500" });
        $$renderer2.push(`<!----> <span>Unlocked</span>`);
      }
      $$renderer2.push(`<!--]--></button>`);
    } else {
      $$renderer2.push("<!--[-1-->");
    }
    $$renderer2.push(`<!--]--></div> `);
    if (showQrOverlay) {
      $$renderer2.push("<!--[0-->");
      $$renderer2.push(`<div class="grid grid-cols-2 gap-2 text-[10px]"><div><label class="font-semibold text-slate-600 block mb-0.5">X (mm)</label> <input type="number" min="10" max="190"${attr("value", qrX)} class="w-full px-2 py-1 bg-white border border-slate-200 rounded-lg text-xs"/></div> <div><label class="font-semibold text-slate-600 block mb-0.5">Y (mm)</label> <input type="number" min="10" max="280"${attr("value", qrY)} class="w-full px-2 py-1 bg-white border border-slate-200 rounded-lg text-xs"/></div></div> <div class="space-y-1.5"><div class="flex items-center justify-between text-[10px] font-semibold text-slate-700"><span>QR Size</span> <span class="font-mono bg-white border px-1.5 py-0.5 rounded">${escape_html(qrW)} mm</span></div> <input type="range" min="20" max="50" step="1"${attr("value", qrW)} class="w-full accent-slate-900" aria-label="QR size in millimetres"/> <p class="text-[9.5px] text-slate-500 leading-relaxed">Reserves a square area for the signed instance QR added at print time.</p></div>`);
    } else {
      $$renderer2.push("<!--[-1-->");
    }
    $$renderer2.push(`<!--]--></div>`);
    bind_props($$props, { showQrOverlay, qrX, qrY, qrW, qrH, qrLocked });
  });
}
export {
  StudioQrReservationControls as default
};
