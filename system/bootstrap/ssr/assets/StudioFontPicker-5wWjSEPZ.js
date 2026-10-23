import { e as escape_html, c as attr_class, f as attr_style, l as bind_props, d as derived } from "../app.js";
import { C as Chevron_down } from "./chevron-down-CkQQZIhu.js";
import "axios";
import "@inertiajs/core";
import "clsx";
import "laravel-precognition";
import "tailwind-merge";
import "tailwind-variants";
import "./Icon-Dal7PqKK.js";
function StudioFontPicker($$renderer, $$props) {
  $$renderer.component(($$renderer2) => {
    let {
      value = "Inter, sans-serif",
      label = "Font Family",
      compact = false,
      onChanged = null
    } = $$props;
    const FONTS = [
      {
        name: "Inter",
        value: "Inter, system-ui, sans-serif",
        category: "sans",
        sample: "Clean Modern Sans"
      },
      {
        name: "Arial",
        value: "Arial, sans-serif",
        category: "sans",
        sample: "Standard Universal"
      },
      {
        name: "Helvetica",
        value: "Helvetica, Arial, sans-serif",
        category: "sans",
        sample: "Swiss Typography"
      },
      {
        name: "Roboto",
        value: "Roboto, sans-serif",
        category: "sans",
        sample: "Geometric Neo-Grotesque"
      },
      {
        name: "Times New Roman",
        value: '"Times New Roman", Times, serif',
        category: "serif",
        sample: "Formal Academic"
      },
      {
        name: "Georgia",
        value: "Georgia, serif",
        category: "serif",
        sample: "Editorial Warm Serif"
      },
      {
        name: "Garamond",
        value: 'Garamond, "EB Garamond", serif',
        category: "serif",
        sample: "Classic Literary"
      },
      {
        name: "Courier New",
        value: '"Courier New", Courier, monospace',
        category: "mono",
        sample: "Typewriter Monospace"
      },
      {
        name: "JetBrains Mono",
        value: '"JetBrains Mono", monospace',
        category: "mono",
        sample: "Modern Technical Mono"
      },
      {
        name: "Oswald",
        value: "Oswald, sans-serif",
        category: "display",
        sample: "CONDENSED DISPLAY"
      },
      {
        name: "Playfair Display",
        value: '"Playfair Display", serif',
        category: "display",
        sample: "Elegant Headline"
      }
    ];
    let currentFontName = derived(() => FONTS.find((f) => f.value === value || value?.startsWith(f.name))?.name || value?.split(",")[0]?.replace(/["']/g, "") || "Default");
    $$renderer2.push(`<div class="relative w-full">`);
    if (label && !compact) {
      $$renderer2.push("<!--[0-->");
      $$renderer2.push(`<label class="text-[11px] font-semibold text-slate-700 block mb-1">${escape_html(label)}</label>`);
    } else {
      $$renderer2.push("<!--[-1-->");
    }
    $$renderer2.push(`<!--]--> <button type="button"${attr_class(`w-full flex items-center justify-between bg-white border border-slate-200 rounded-lg text-left transition shadow-2xs hover:border-slate-300 ${compact ? "px-2 py-1 text-[11px]" : "px-2.5 py-1.5 text-xs"}`)}><div class="flex items-center space-x-2 truncate"><span class="font-medium text-slate-900 truncate"${attr_style(`font-family: ${value};`)}>${escape_html(currentFontName())}</span></div> `);
    Chevron_down($$renderer2, { class: "size-3.5 text-slate-400 shrink-0 ml-1" });
    $$renderer2.push(`<!----></button> `);
    {
      $$renderer2.push("<!--[-1-->");
    }
    $$renderer2.push(`<!--]--></div>`);
    bind_props($$props, { value });
  });
}
export {
  StudioFontPicker as default
};
