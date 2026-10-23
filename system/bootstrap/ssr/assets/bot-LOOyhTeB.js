import { g as sanitize_props, i as spread_props, j as slot } from "../app.js";
import { I as Icon } from "./Icon-Dal7PqKK.js";
function Bot($$renderer, $$props) {
  const $$sanitized_props = sanitize_props($$props);
  const iconNode = [
    ["path", { "d": "M12 8V4H8" }],
    [
      "rect",
      { "width": "16", "height": "12", "x": "4", "y": "8", "rx": "2" }
    ],
    ["path", { "d": "M2 14h2" }],
    ["path", { "d": "M20 14h2" }],
    ["path", { "d": "M15 13v2" }],
    ["path", { "d": "M9 13v2" }]
  ];
  Icon($$renderer, spread_props([
    { name: "bot" },
    $$sanitized_props,
    {
      /**
       * @component @name Bot
       * @description Lucide SVG icon component, renders SVG Element with children.
       *
       * @preview ![img](data:image/svg+xml;base64,PHN2ZyAgeG1sbnM9Imh0dHA6Ly93d3cudzMub3JnLzIwMDAvc3ZnIgogIHdpZHRoPSIyNCIKICBoZWlnaHQ9IjI0IgogIHZpZXdCb3g9IjAgMCAyNCAyNCIKICBmaWxsPSJub25lIgogIHN0cm9rZT0iIzAwMCIgc3R5bGU9ImJhY2tncm91bmQtY29sb3I6ICNmZmY7IGJvcmRlci1yYWRpdXM6IDJweCIKICBzdHJva2Utd2lkdGg9IjIiCiAgc3Ryb2tlLWxpbmVjYXA9InJvdW5kIgogIHN0cm9rZS1saW5lam9pbj0icm91bmQiCj4KICA8cGF0aCBkPSJNMTIgOFY0SDgiIC8+CiAgPHJlY3Qgd2lkdGg9IjE2IiBoZWlnaHQ9IjEyIiB4PSI0IiB5PSI4IiByeD0iMiIgLz4KICA8cGF0aCBkPSJNMiAxNGgyIiAvPgogIDxwYXRoIGQ9Ik0yMCAxNGgyIiAvPgogIDxwYXRoIGQ9Ik0xNSAxM3YyIiAvPgogIDxwYXRoIGQ9Ik05IDEzdjIiIC8+Cjwvc3ZnPgo=) - https://lucide.dev/icons/bot
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
  Bot as B
};
