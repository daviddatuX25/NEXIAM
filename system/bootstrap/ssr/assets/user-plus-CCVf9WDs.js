import { g as sanitize_props, i as spread_props, j as slot } from "../app.js";
import { I as Icon } from "./Icon-Dal7PqKK.js";
function User_plus($$renderer, $$props) {
  const $$sanitized_props = sanitize_props($$props);
  const iconNode = [
    ["path", { "d": "M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2" }],
    ["circle", { "cx": "9", "cy": "7", "r": "4" }],
    ["line", { "x1": "19", "x2": "19", "y1": "8", "y2": "14" }],
    ["line", { "x1": "22", "x2": "16", "y1": "11", "y2": "11" }]
  ];
  Icon($$renderer, spread_props([
    { name: "user-plus" },
    $$sanitized_props,
    {
      /**
       * @component @name UserPlus
       * @description Lucide SVG icon component, renders SVG Element with children.
       *
       * @preview ![img](data:image/svg+xml;base64,PHN2ZyAgeG1sbnM9Imh0dHA6Ly93d3cudzMub3JnLzIwMDAvc3ZnIgogIHdpZHRoPSIyNCIKICBoZWlnaHQ9IjI0IgogIHZpZXdCb3g9IjAgMCAyNCAyNCIKICBmaWxsPSJub25lIgogIHN0cm9rZT0iIzAwMCIgc3R5bGU9ImJhY2tncm91bmQtY29sb3I6ICNmZmY7IGJvcmRlci1yYWRpdXM6IDJweCIKICBzdHJva2Utd2lkdGg9IjIiCiAgc3Ryb2tlLWxpbmVjYXA9InJvdW5kIgogIHN0cm9rZS1saW5lam9pbj0icm91bmQiCj4KICA8cGF0aCBkPSJNMTYgMjF2LTJhNCA0IDAgMCAwLTQtNEg2YTQgNCAwIDAgMC00IDR2MiIgLz4KICA8Y2lyY2xlIGN4PSI5IiBjeT0iNyIgcj0iNCIgLz4KICA8bGluZSB4MT0iMTkiIHgyPSIxOSIgeTE9IjgiIHkyPSIxNCIgLz4KICA8bGluZSB4MT0iMjIiIHgyPSIxNiIgeTE9IjExIiB5Mj0iMTEiIC8+Cjwvc3ZnPgo=) - https://lucide.dev/icons/user-plus
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
  User_plus as U
};
