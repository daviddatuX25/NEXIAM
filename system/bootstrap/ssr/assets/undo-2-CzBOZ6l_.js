import { g as sanitize_props, i as spread_props, j as slot } from "../app.js";
import { I as Icon } from "./Icon-Dal7PqKK.js";
function Undo_2($$renderer, $$props) {
  const $$sanitized_props = sanitize_props($$props);
  const iconNode = [
    ["path", { "d": "M9 14 4 9l5-5" }],
    [
      "path",
      {
        "d": "M4 9h10.5a5.5 5.5 0 0 1 5.5 5.5a5.5 5.5 0 0 1-5.5 5.5H11"
      }
    ]
  ];
  Icon($$renderer, spread_props([
    { name: "undo-2" },
    $$sanitized_props,
    {
      /**
       * @component @name Undo2
       * @description Lucide SVG icon component, renders SVG Element with children.
       *
       * @preview ![img](data:image/svg+xml;base64,PHN2ZyAgeG1sbnM9Imh0dHA6Ly93d3cudzMub3JnLzIwMDAvc3ZnIgogIHdpZHRoPSIyNCIKICBoZWlnaHQ9IjI0IgogIHZpZXdCb3g9IjAgMCAyNCAyNCIKICBmaWxsPSJub25lIgogIHN0cm9rZT0iIzAwMCIgc3R5bGU9ImJhY2tncm91bmQtY29sb3I6ICNmZmY7IGJvcmRlci1yYWRpdXM6IDJweCIKICBzdHJva2Utd2lkdGg9IjIiCiAgc3Ryb2tlLWxpbmVjYXA9InJvdW5kIgogIHN0cm9rZS1saW5lam9pbj0icm91bmQiCj4KICA8cGF0aCBkPSJNOSAxNCA0IDlsNS01IiAvPgogIDxwYXRoIGQ9Ik00IDloMTAuNWE1LjUgNS41IDAgMCAxIDUuNSA1LjVhNS41IDUuNSAwIDAgMS01LjUgNS41SDExIiAvPgo8L3N2Zz4K) - https://lucide.dev/icons/undo-2
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
export {
  Undo_2 as U
};
