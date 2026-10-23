import { g as sanitize_props, i as spread_props, j as slot, l as bind_props, c as attr_class, s as stringify, b as attr, e as escape_html } from "../app.js";
import { I as Input } from "./input-BUZAZKIK.js";
import { S as Search } from "./settings-C5xOKpV3.js";
import { I as Icon } from "./Icon-Dal7PqKK.js";
import { C as Chevron_down } from "./chevron-down-CkQQZIhu.js";
function Funnel($$renderer, $$props) {
  const $$sanitized_props = sanitize_props($$props);
  const iconNode = [
    [
      "path",
      {
        "d": "M10 20a1 1 0 0 0 .553.895l2 1A1 1 0 0 0 14 21v-7a2 2 0 0 1 .517-1.341L21.74 4.67A1 1 0 0 0 21 3H3a1 1 0 0 0-.742 1.67l7.225 7.989A2 2 0 0 1 10 14z"
      }
    ]
  ];
  Icon($$renderer, spread_props([
    { name: "funnel" },
    $$sanitized_props,
    {
      /**
       * @component @name Funnel
       * @description Lucide SVG icon component, renders SVG Element with children.
       *
       * @preview ![img](data:image/svg+xml;base64,PHN2ZyAgeG1sbnM9Imh0dHA6Ly93d3cudzMub3JnLzIwMDAvc3ZnIgogIHdpZHRoPSIyNCIKICBoZWlnaHQ9IjI0IgogIHZpZXdCb3g9IjAgMCAyNCAyNCIKICBmaWxsPSJub25lIgogIHN0cm9rZT0iIzAwMCIgc3R5bGU9ImJhY2tncm91bmQtY29sb3I6ICNmZmY7IGJvcmRlci1yYWRpdXM6IDJweCIKICBzdHJva2Utd2lkdGg9IjIiCiAgc3Ryb2tlLWxpbmVjYXA9InJvdW5kIgogIHN0cm9rZS1saW5lam9pbj0icm91bmQiCj4KICA8cGF0aCBkPSJNMTAgMjBhMSAxIDAgMCAwIC41NTMuODk1bDIgMUExIDEgMCAwIDAgMTQgMjF2LTdhMiAyIDAgMCAxIC41MTctMS4zNDFMMjEuNzQgNC42N0ExIDEgMCAwIDAgMjEgM0gzYTEgMSAwIDAgMC0uNzQyIDEuNjdsNy4yMjUgNy45ODlBMiAyIDAgMCAxIDEwIDE0eiIgLz4KPC9zdmc+Cg==) - https://lucide.dev/icons/funnel
       * @see https://lucide.dev/guide/packages/lucide-svelte - Documentation
       *
       * @param {Object} props - Lucide icons props and any valid SVG attribute
       * @returns {FunctionalComponent} Svelte component
       *
       */
      iconNode,
      children: ($$renderer2) => {
        $$renderer2.push(`<!--[-->`);
        slot($$renderer2, $$props, "default", {});
        $$renderer2.push(`<!--]-->`);
      },
      $$slots: { default: true }
    }
  ]));
}
function FilterToolbar($$renderer, $$props) {
  $$renderer.component(($$renderer2) => {
    let {
      search = "",
      searchPlaceholder = "Search…",
      searchLabel = "",
      inputId = "",
      /** Snippet: desktop filter controls (status, season, dates, …) */
      desktopFilters,
      /** Snippet: mobile panel body (same controls, stacked, full width) */
      mobileFilters,
      searchSuggestions,
      class: className = ""
    } = $$props;
    const resolvedId = inputId || "filter-search-" + Math.random().toString(36).slice(2, 8);
    let filtersOpen = false;
    let $$settled = true;
    let $$inner_renderer;
    function $$render_inner($$renderer3) {
      $$renderer3.push(`<div${attr_class(`flex flex-col gap-3 ${stringify(className)}`)}><div class="flex flex-wrap items-end gap-3"><div class="relative min-w-[160px] max-w-none md:max-w-[220px] flex-1 min-w-0 md:flex-none w-full sm:w-auto">`);
      if (searchLabel) {
        $$renderer3.push("<!--[0-->");
        $$renderer3.push(`<label class="mb-1 block text-xs font-medium text-muted-foreground"${attr("for", resolvedId)}>${escape_html(searchLabel)}</label>`);
      } else {
        $$renderer3.push("<!--[-1-->");
      }
      $$renderer3.push(`<!--]--> <div class="relative">`);
      Search($$renderer3, {
        class: "pointer-events-none absolute left-2.5 top-1/2 h-4 w-4 -translate-y-1/2 text-muted-foreground"
      });
      $$renderer3.push(`<!----> `);
      Input($$renderer3, {
        id: resolvedId,
        type: "search",
        placeholder: searchPlaceholder,
        class: "h-10 w-full min-h-[44px] pl-8 md:min-h-[40px]",
        get value() {
          return search;
        },
        set value($$value) {
          search = $$value;
          $$settled = false;
        }
      });
      $$renderer3.push(`<!----> `);
      if (searchSuggestions) {
        $$renderer3.push("<!--[0-->");
        searchSuggestions($$renderer3);
        $$renderer3.push(`<!---->`);
      } else {
        $$renderer3.push("<!--[-1-->");
      }
      $$renderer3.push(`<!--]--></div></div> `);
      if (mobileFilters) {
        $$renderer3.push("<!--[0-->");
        $$renderer3.push(`<details class="relative md:hidden"${attr("open", filtersOpen, true)}><summary class="flex min-h-[44px] min-w-[44px] cursor-pointer list-none items-center gap-2 rounded-lg border border-input bg-background px-3 py-2 text-sm font-medium hover:bg-muted/50">`);
        Funnel($$renderer3, { class: "h-4 w-4" });
        $$renderer3.push(`<!----> <span>Filters</span> `);
        Chevron_down($$renderer3, {
          class: "h-4 w-4 transition-transform [details[open]_&]:rotate-180"
        });
        $$renderer3.push(`<!----></summary> <div class="absolute right-0 top-full z-20 mt-1 flex w-[min(320px,calc(100vw-2rem))] flex-col gap-3 rounded-lg border border-border bg-card p-4 shadow-lg">`);
        mobileFilters($$renderer3);
        $$renderer3.push(`<!----></div></details>`);
      } else {
        $$renderer3.push("<!--[-1-->");
      }
      $$renderer3.push(`<!--]--> `);
      if (desktopFilters) {
        $$renderer3.push("<!--[0-->");
        $$renderer3.push(`<div class="hidden md:flex md:flex-wrap md:items-end md:gap-3">`);
        desktopFilters($$renderer3);
        $$renderer3.push(`<!----></div>`);
      } else {
        $$renderer3.push("<!--[-1-->");
      }
      $$renderer3.push(`<!--]--></div></div>`);
    }
    do {
      $$settled = true;
      $$inner_renderer = $$renderer2.copy();
      $$render_inner($$inner_renderer);
    } while (!$$settled);
    $$renderer2.subsume($$inner_renderer);
    bind_props($$props, { search });
  });
}
export {
  FilterToolbar as F
};
