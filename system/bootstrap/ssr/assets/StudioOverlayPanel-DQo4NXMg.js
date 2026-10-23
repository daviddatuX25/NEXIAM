import { l as bind_props, e as escape_html, b as attr, a as ensure_array_like, c as attr_class, f as attr_style } from "../app.js";
import StudioTip from "./StudioTip-evy-6JcG.js";
import StudioFontPicker from "./StudioFontPicker-5wWjSEPZ.js";
import StudioQrReservationControls from "./StudioQrReservationControls-C1jYP4Jw.js";
import StudioFiducialInsetControls from "./StudioFiducialInsetControls-CdTiPYqb.js";
import { P as Plus } from "./plus-DjyPLC7A.js";
import { L as Lock } from "./lock-DAA4kTLl.js";
import { L as Lock_open } from "./lock-open-5Nny5RV8.js";
import { T as Text_align_start, a as Text_align_center, b as Text_align_end } from "./text-align-start-Cy9u9QqD.js";
import { T as Trash_2 } from "./trash-2-CjFp3otc.js";
import { C as Chevron_left } from "./chevron-left-DCSOZwZl.js";
import { C as Chevron_right } from "./chevron-right-BHu-PGOg.js";
import "axios";
import "@inertiajs/core";
import "clsx";
import "laravel-precognition";
import "tailwind-merge";
import "tailwind-variants";
import "./info-C3MCzlQW.js";
import "./Icon-Dal7PqKK.js";
import "./chevron-down-CkQQZIhu.js";
function StudioOverlayPanel($$renderer, $$props) {
  $$renderer.component(($$renderer2) => {
    let {
      marginMm = 15,
      markerPattern = "square",
      markerInsetsMm = { top: 12, right: 12, bottom: 14, left: 12 },
      markerInsetsDisabled = false,
      onMarkerInsetsPreview = null,
      onMarkerInsetsCommit = null,
      markerProfiles = [],
      selectedMarkerProfileId = null,
      onMarkerProfileChange = null,
      showFiducialControls = true,
      showQrControls = true,
      showHeaderOverlay = true,
      headerTitle = "EXAMINATION ANSWER SHEET",
      headerSubtitle = "AUTOMATED OMR EVALUATION FORM",
      headerFontSize = 14,
      headerFontFamily = "Inter, sans-serif",
      headerColor = "#0f172a",
      headerAlign = "left",
      headerLocked = false,
      showSignatureOverlay = true,
      signatureContent = "EXAMINEE SIGNATURE",
      signatureFontSize = 8.5,
      signatureFontFamily = "Inter, sans-serif",
      signatureColor = "#475569",
      sigLocked = false,
      showInstructionsOverlay = true,
      instructionsContent = "Use 2B pencil only. Shade bubbles completely.",
      instructionsFontSize = 9,
      instructionsFontFamily = "Inter, sans-serif",
      instructionsColor = "#334155",
      instLocked = false,
      showQrOverlay = true,
      qrX = 162,
      qrY = 18,
      qrW = 26,
      qrH = 26,
      qrLocked = false,
      customTextItems = [],
      onChanged = null,
      onBack = null,
      onNext = null
    } = $$props;
    const PRESET_COLORS = [
      { name: "Slate Dark", value: "#0f172a" },
      { name: "Charcoal", value: "#334155" },
      { name: "Muted", value: "#64748b" },
      { name: "Navy", value: "#1e3a8a" },
      { name: "Emerald", value: "#065f46" },
      { name: "Crimson", value: "#991b1b" }
    ];
    function handleInput() {
      if (onChanged) onChanged();
    }
    let $$settled = true;
    let $$inner_renderer;
    function $$render_inner($$renderer3) {
      $$renderer3.push(`<div class="p-5 space-y-5"><div><h2 class="text-sm font-bold text-slate-900">Overlays &amp; Margins</h2> <p class="text-[11px] text-slate-500 mt-0.5">Content boundaries, independently positioned profile fiducials, and customizable vector print typography.</p></div> <div class="space-y-1.5 p-3 bg-slate-50 rounded-xl border border-slate-200"><div class="flex justify-between text-xs font-semibold text-slate-700"><span class="flex items-center gap-1">Content Margin `);
      StudioTip($$renderer3, {
        text: "Keeps editable content away from the paper edge. Use Fiducial Insets below to position the scanner-controlled registration marks independently."
      });
      $$renderer3.push(`<!----></span> <span class="font-mono bg-white border px-1.5 py-0.5 rounded">${escape_html(marginMm)} mm</span></div> <input type="range" min="8" max="30" step="1"${attr("value", marginMm)} class="w-full accent-slate-900"/></div> `);
      if (showFiducialControls) {
        $$renderer3.push("<!--[0-->");
        $$renderer3.push(`<div class="space-y-1.5 border-t border-slate-200 pt-3"><span class="text-xs font-bold text-slate-800 uppercase tracking-wider block">Fiducial Marker</span> <div class="rounded-lg border border-slate-200 bg-white px-2.5 py-2 text-[10px] leading-relaxed text-slate-600">The selected scanner profile supplies the bullseye shape. Adjust its four page-edge positions below.</div></div> <label class="block space-y-1.5 border-t border-slate-200 pt-3"><span class="text-xs font-bold text-slate-800 uppercase tracking-wider">OMRChecker fiducial profile</span> `);
        $$renderer3.select(
          {
            value: selectedMarkerProfileId ?? "",
            onchange: (event) => onMarkerProfileChange?.(Number(event.currentTarget.value)),
            class: "w-full rounded-lg border border-slate-200 bg-white px-2 py-1.5 text-xs text-slate-800"
          },
          ($$renderer4) => {
            $$renderer4.option({ value: "", disabled: true }, ($$renderer5) => {
              $$renderer5.push(`Select a marker profile`);
            });
            $$renderer4.push(`<!--[-->`);
            const each_array = ensure_array_like(markerProfiles);
            for (let $$index = 0, $$length = each_array.length; $$index < $$length; $$index++) {
              let profile = each_array[$$index];
              $$renderer4.option({ value: profile.id }, ($$renderer5) => {
                $$renderer5.push(`${escape_html(profile.code)} v${escape_html(profile.version)} · ${escape_html(profile.status)}`);
              });
            }
            $$renderer4.push(`<!--]-->`);
          }
        );
        $$renderer3.push(` <span class="text-[10px] text-slate-500">The selected profile supplies the physical marker asset used by print and alignment.</span></label> `);
        StudioFiducialInsetControls($$renderer3, {
          disabled: markerInsetsDisabled,
          onPreview: onMarkerInsetsPreview,
          onCommit: onMarkerInsetsCommit,
          get markerInsetsMm() {
            return markerInsetsMm;
          },
          set markerInsetsMm($$value) {
            markerInsetsMm = $$value;
            $$settled = false;
          }
        });
        $$renderer3.push(`<!---->`);
      } else {
        $$renderer3.push("<!--[-1-->");
      }
      $$renderer3.push(`<!--]--> <div class="space-y-3.5 border-t border-slate-200 pt-3"><div class="flex items-center justify-between"><span class="text-xs font-bold text-slate-800 uppercase tracking-wider">Printable Overlays</span> <button type="button" class="text-[11px] font-semibold text-blue-600 hover:text-blue-800 flex items-center gap-1 bg-blue-50 px-2 py-0.5 rounded-lg border border-blue-200/60">`);
      Plus($$renderer3, { class: "size-3" });
      $$renderer3.push(`<!----> <span>Add Text</span></button></div> <div class="p-3.5 bg-slate-50 rounded-xl border border-slate-200 space-y-3"><div class="flex items-center justify-between"><label class="flex items-center space-x-2 cursor-pointer"><input type="checkbox"${attr("checked", showHeaderOverlay, true)} class="rounded text-slate-900"/> <span class="text-xs font-bold text-slate-900">Document Header</span></label> `);
      if (showHeaderOverlay) {
        $$renderer3.push("<!--[0-->");
        $$renderer3.push(`<button type="button"${attr_class(`px-2 py-0.5 rounded text-[10px] font-semibold flex items-center gap-1 border transition ${headerLocked ? "bg-amber-100 text-amber-900 border-amber-300" : "bg-white text-slate-700 border-slate-200"}`)}${attr("title", headerLocked ? "Header position is locked" : "Header can be dragged and resized")}>`);
        if (headerLocked) {
          $$renderer3.push("<!--[0-->");
          Lock($$renderer3, { class: "size-2.5 text-amber-700" });
          $$renderer3.push(`<!----> <span>Locked</span>`);
        } else {
          $$renderer3.push("<!--[-1-->");
          Lock_open($$renderer3, { class: "size-2.5 text-slate-500" });
          $$renderer3.push(`<!----> <span>Unlocked</span>`);
        }
        $$renderer3.push(`<!--]--></button>`);
      } else {
        $$renderer3.push("<!--[-1-->");
      }
      $$renderer3.push(`<!--]--></div> `);
      if (showHeaderOverlay) {
        $$renderer3.push("<!--[0-->");
        $$renderer3.push(`<div class="space-y-2.5 pt-1"><div class="space-y-1"><input type="text"${attr("value", headerTitle)} placeholder="Header Title Text" class="w-full text-xs p-2 bg-white rounded-lg border border-slate-200 font-bold"/> <input type="text"${attr("value", headerSubtitle)} placeholder="Subtitle (e.g. Code, Batch, Form No.)" class="w-full text-[11px] p-1.5 bg-white rounded-lg border border-slate-200 font-mono text-slate-600"/></div> <div class="grid grid-cols-2 gap-2">`);
        StudioFontPicker($$renderer3, {
          label: "Title Font",
          onChanged: handleInput,
          get value() {
            return headerFontFamily;
          },
          set value($$value) {
            headerFontFamily = $$value;
            $$settled = false;
          }
        });
        $$renderer3.push(`<!----> <div class="space-y-1"><div class="flex justify-between text-[11px] font-semibold text-slate-700"><span>Font Size</span> <span class="font-mono text-[10px] bg-white border px-1 rounded">${escape_html(headerFontSize)} pt</span></div> <input type="range" min="9" max="28" step="0.5"${attr("value", headerFontSize)} class="w-full accent-slate-900"/></div></div> <div class="flex items-center justify-between pt-1 border-t border-slate-200/60"><div class="flex items-center space-x-1"><!--[-->`);
        const each_array_1 = ensure_array_like(PRESET_COLORS);
        for (let $$index_1 = 0, $$length = each_array_1.length; $$index_1 < $$length; $$index_1++) {
          let c = each_array_1[$$index_1];
          $$renderer3.push(`<button type="button"${attr_style(`background-color: ${c.value};`)}${attr_class(`size-4 rounded-full border transition ${headerColor === c.value ? "ring-2 ring-slate-900 scale-110" : "border-white/80 opacity-80 hover:opacity-100"}`)}${attr("title", c.name)}></button>`);
        }
        $$renderer3.push(`<!--]--></div> <div class="flex items-center space-x-0.5 bg-white border border-slate-200 rounded-lg p-0.5"><!--[-->`);
        const each_array_2 = ensure_array_like([
          { id: "left", icon: Text_align_start },
          { id: "center", icon: Text_align_center },
          { id: "right", icon: Text_align_end }
        ]);
        for (let $$index_2 = 0, $$length = each_array_2.length; $$index_2 < $$length; $$index_2++) {
          let al = each_array_2[$$index_2];
          $$renderer3.push(`<button type="button"${attr_class(`p-1 rounded transition ${headerAlign === al.id ? "bg-slate-900 text-white" : "text-slate-500 hover:text-slate-900"}`)}>`);
          if (al.icon) {
            $$renderer3.push("<!--[-->");
            al.icon($$renderer3, { class: "size-3" });
            $$renderer3.push("<!--]-->");
          } else {
            $$renderer3.push("<!--[!-->");
            $$renderer3.push("<!--]-->");
          }
          $$renderer3.push(`</button>`);
        }
        $$renderer3.push(`<!--]--></div></div></div>`);
      } else {
        $$renderer3.push("<!--[-1-->");
      }
      $$renderer3.push(`<!--]--></div> <div class="p-3.5 bg-slate-50 rounded-xl border border-slate-200 space-y-3"><div class="flex items-center justify-between"><label class="flex items-center space-x-2 cursor-pointer"><input type="checkbox"${attr("checked", showSignatureOverlay, true)} class="rounded text-slate-900"/> <span class="text-xs font-bold text-slate-900">Candidate Signature Box</span></label> `);
      if (showSignatureOverlay) {
        $$renderer3.push("<!--[0-->");
        $$renderer3.push(`<button type="button"${attr_class(`px-2 py-0.5 rounded text-[10px] font-semibold flex items-center gap-1 border transition ${sigLocked ? "bg-amber-100 text-amber-900 border-amber-300" : "bg-white text-slate-700 border-slate-200"}`)}>`);
        if (sigLocked) {
          $$renderer3.push("<!--[0-->");
          Lock($$renderer3, { class: "size-2.5 text-amber-700" });
          $$renderer3.push(`<!----> <span>Locked</span>`);
        } else {
          $$renderer3.push("<!--[-1-->");
          Lock_open($$renderer3, { class: "size-2.5 text-slate-500" });
          $$renderer3.push(`<!----> <span>Unlocked</span>`);
        }
        $$renderer3.push(`<!--]--></button>`);
      } else {
        $$renderer3.push("<!--[-1-->");
      }
      $$renderer3.push(`<!--]--></div> `);
      if (showSignatureOverlay) {
        $$renderer3.push("<!--[0-->");
        $$renderer3.push(`<div class="space-y-2.5 pt-1"><input type="text"${attr("value", signatureContent)} placeholder="Signature Box Label" class="w-full text-xs p-2 bg-white rounded-lg border border-slate-200 font-semibold"/> <div class="grid grid-cols-2 gap-2">`);
        StudioFontPicker($$renderer3, {
          label: "Font",
          onChanged: handleInput,
          get value() {
            return signatureFontFamily;
          },
          set value($$value) {
            signatureFontFamily = $$value;
            $$settled = false;
          }
        });
        $$renderer3.push(`<!----> <div class="space-y-1"><div class="flex justify-between text-[11px] font-semibold text-slate-700"><span>Font Size</span> <span class="font-mono text-[10px] bg-white border px-1 rounded">${escape_html(signatureFontSize)} pt</span></div> <input type="range" min="6" max="16" step="0.5"${attr("value", signatureFontSize)} class="w-full accent-slate-900"/></div></div></div>`);
      } else {
        $$renderer3.push("<!--[-1-->");
      }
      $$renderer3.push(`<!--]--></div> <div class="p-3.5 bg-slate-50 rounded-xl border border-slate-200 space-y-3"><div class="flex items-center justify-between"><label class="flex items-center space-x-2 cursor-pointer"><input type="checkbox"${attr("checked", showInstructionsOverlay, true)} class="rounded text-slate-900"/> <span class="text-xs font-bold text-slate-900">Instructions Notice</span></label> `);
      if (showInstructionsOverlay) {
        $$renderer3.push("<!--[0-->");
        $$renderer3.push(`<button type="button"${attr_class(`px-2 py-0.5 rounded text-[10px] font-semibold flex items-center gap-1 border transition ${instLocked ? "bg-amber-100 text-amber-900 border-amber-300" : "bg-white text-slate-700 border-slate-200"}`)}>`);
        if (instLocked) {
          $$renderer3.push("<!--[0-->");
          Lock($$renderer3, { class: "size-2.5 text-amber-700" });
          $$renderer3.push(`<!----> <span>Locked</span>`);
        } else {
          $$renderer3.push("<!--[-1-->");
          Lock_open($$renderer3, { class: "size-2.5 text-slate-500" });
          $$renderer3.push(`<!----> <span>Unlocked</span>`);
        }
        $$renderer3.push(`<!--]--></button>`);
      } else {
        $$renderer3.push("<!--[-1-->");
      }
      $$renderer3.push(`<!--]--></div> `);
      if (showInstructionsOverlay) {
        $$renderer3.push("<!--[0-->");
        $$renderer3.push(`<div class="space-y-2.5 pt-1"><textarea rows="2" class="w-full text-xs p-2 bg-white rounded-lg border border-slate-200 font-medium">`);
        const $$body = escape_html(instructionsContent);
        if ($$body) {
          $$renderer3.push(`${$$body}`);
        }
        $$renderer3.push(`</textarea> <div class="grid grid-cols-2 gap-2">`);
        StudioFontPicker($$renderer3, {
          label: "Font",
          onChanged: handleInput,
          get value() {
            return instructionsFontFamily;
          },
          set value($$value) {
            instructionsFontFamily = $$value;
            $$settled = false;
          }
        });
        $$renderer3.push(`<!----> <div class="space-y-1"><div class="flex justify-between text-[11px] font-semibold text-slate-700"><span>Font Size</span> <span class="font-mono text-[10px] bg-white border px-1 rounded">${escape_html(instructionsFontSize)} pt</span></div> <input type="range" min="6" max="14" step="0.5"${attr("value", instructionsFontSize)} class="w-full accent-slate-900"/></div></div></div>`);
      } else {
        $$renderer3.push("<!--[-1-->");
      }
      $$renderer3.push(`<!--]--></div> `);
      if (showQrControls) {
        $$renderer3.push("<!--[0-->");
        $$renderer3.push(`<div class="p-3.5 bg-slate-50 rounded-xl border border-slate-200">`);
        StudioQrReservationControls($$renderer3, {
          onChanged: handleInput,
          get showQrOverlay() {
            return showQrOverlay;
          },
          set showQrOverlay($$value) {
            showQrOverlay = $$value;
            $$settled = false;
          },
          get qrX() {
            return qrX;
          },
          set qrX($$value) {
            qrX = $$value;
            $$settled = false;
          },
          get qrY() {
            return qrY;
          },
          set qrY($$value) {
            qrY = $$value;
            $$settled = false;
          },
          get qrW() {
            return qrW;
          },
          set qrW($$value) {
            qrW = $$value;
            $$settled = false;
          },
          get qrH() {
            return qrH;
          },
          set qrH($$value) {
            qrH = $$value;
            $$settled = false;
          },
          get qrLocked() {
            return qrLocked;
          },
          set qrLocked($$value) {
            qrLocked = $$value;
            $$settled = false;
          }
        });
        $$renderer3.push(`<!----></div>`);
      } else {
        $$renderer3.push("<!--[-1-->");
      }
      $$renderer3.push(`<!--]--> <!--[-->`);
      const each_array_3 = ensure_array_like(customTextItems);
      for (let idx = 0, $$length = each_array_3.length; idx < $$length; idx++) {
        let t = each_array_3[idx];
        $$renderer3.push(`<div class="p-3.5 bg-slate-50 rounded-xl border border-slate-200 space-y-2.5"><div class="flex items-center justify-between"><span class="text-xs font-bold text-slate-900">${escape_html(t.name || `Text ${idx + 1}`)}</span> <div class="flex items-center space-x-1.5"><button type="button"${attr_class(`px-2 py-0.5 rounded text-[10px] font-semibold flex items-center gap-1 border transition ${t.isLocked ? "bg-amber-100 text-amber-900 border-amber-300" : "bg-white text-slate-700 border-slate-200"}`)}>`);
        if (t.isLocked) {
          $$renderer3.push("<!--[0-->");
          Lock($$renderer3, { class: "size-2.5 text-amber-700" });
          $$renderer3.push(`<!----> <span>Locked</span>`);
        } else {
          $$renderer3.push("<!--[-1-->");
          Lock_open($$renderer3, { class: "size-2.5 text-slate-500" });
          $$renderer3.push(`<!----> <span>Unlocked</span>`);
        }
        $$renderer3.push(`<!--]--></button> <button type="button" class="text-red-500 hover:text-red-700 p-0.5">`);
        Trash_2($$renderer3, { class: "size-3.5" });
        $$renderer3.push(`<!----></button></div></div> <input type="text"${attr("value", t.content)} placeholder="Custom text content" class="w-full text-xs p-2 bg-white rounded-lg border border-slate-200"/> <div class="grid grid-cols-2 gap-2">`);
        StudioFontPicker($$renderer3, {
          label: "Font",
          onChanged: handleInput,
          get value() {
            return t.fontFamily;
          },
          set value($$value) {
            t.fontFamily = $$value;
            $$settled = false;
          }
        });
        $$renderer3.push(`<!----> <div class="space-y-1"><div class="flex justify-between text-[11px] font-semibold text-slate-700"><span>Font Size</span> <span class="font-mono text-[10px] bg-white border px-1 rounded">${escape_html(t.fontSize ?? 10)} pt</span></div> <input type="range" min="6" max="24" step="0.5"${attr("value", t.fontSize)} class="w-full accent-slate-900"/></div></div></div>`);
      }
      $$renderer3.push(`<!--]--></div> <div class="pt-3 border-t border-slate-200 flex items-center justify-between gap-3"><button type="button" class="px-3 py-2 border border-slate-200 hover:bg-slate-50 text-slate-700 font-semibold rounded-xl text-xs transition flex items-center gap-1.5">`);
      Chevron_left($$renderer3, { class: "size-3.5" });
      $$renderer3.push(`<!----> <span>Back</span></button> <button type="button" class="px-4 py-2 bg-slate-900 hover:bg-slate-800 text-white font-semibold rounded-xl text-xs transition flex items-center gap-1.5 shadow-xs"><span>Recognition Mapping</span> `);
      Chevron_right($$renderer3, { class: "size-3.5" });
      $$renderer3.push(`<!----></button></div></div>`);
    }
    do {
      $$settled = true;
      $$inner_renderer = $$renderer2.copy();
      $$render_inner($$inner_renderer);
    } while (!$$settled);
    $$renderer2.subsume($$inner_renderer);
    bind_props($$props, {
      marginMm,
      markerPattern,
      markerInsetsMm,
      showHeaderOverlay,
      headerTitle,
      headerSubtitle,
      headerFontSize,
      headerFontFamily,
      headerColor,
      headerAlign,
      headerLocked,
      showSignatureOverlay,
      signatureContent,
      signatureFontSize,
      signatureFontFamily,
      signatureColor,
      sigLocked,
      showInstructionsOverlay,
      instructionsContent,
      instructionsFontSize,
      instructionsFontFamily,
      instructionsColor,
      instLocked,
      showQrOverlay,
      qrX,
      qrY,
      qrW,
      qrH,
      qrLocked,
      customTextItems
    });
  });
}
export {
  StudioOverlayPanel as default
};
