import { g as sanitize_props, i as spread_props, j as slot } from "../app.js";
import { I as Icon } from "./Icon-Dal7PqKK.js";
function Scan_line($$renderer, $$props) {
  const $$sanitized_props = sanitize_props($$props);
  const iconNode = [
    ["path", { "d": "M3 7V5a2 2 0 0 1 2-2h2" }],
    ["path", { "d": "M17 3h2a2 2 0 0 1 2 2v2" }],
    ["path", { "d": "M21 17v2a2 2 0 0 1-2 2h-2" }],
    ["path", { "d": "M7 21H5a2 2 0 0 1-2-2v-2" }],
    ["path", { "d": "M7 12h10" }]
  ];
  Icon($$renderer, spread_props([
    { name: "scan-line" },
    $$sanitized_props,
    {
      /**
       * @component @name ScanLine
       * @description Lucide SVG icon component, renders SVG Element with children.
       *
       * @preview ![img](data:image/svg+xml;base64,PHN2ZyAgeG1sbnM9Imh0dHA6Ly93d3cudzMub3JnLzIwMDAvc3ZnIgogIHdpZHRoPSIyNCIKICBoZWlnaHQ9IjI0IgogIHZpZXdCb3g9IjAgMCAyNCAyNCIKICBmaWxsPSJub25lIgogIHN0cm9rZT0iIzAwMCIgc3R5bGU9ImJhY2tncm91bmQtY29sb3I6ICNmZmY7IGJvcmRlci1yYWRpdXM6IDJweCIKICBzdHJva2Utd2lkdGg9IjIiCiAgc3Ryb2tlLWxpbmVjYXA9InJvdW5kIgogIHN0cm9rZS1saW5lam9pbj0icm91bmQiCj4KICA8cGF0aCBkPSJNMyA3VjVhMiAyIDAgMCAxIDItMmgyIiAvPgogIDxwYXRoIGQ9Ik0xNyAzaDJhMiAyIDAgMCAxIDIgMnYyIiAvPgogIDxwYXRoIGQ9Ik0yMSAxN3YyYTIgMiAwIDAgMS0yIDJoLTIiIC8+CiAgPHBhdGggZD0iTTcgMjFINWEyIDIgMCAwIDEtMi0ydi0yIiAvPgogIDxwYXRoIGQ9Ik03IDEyaDEwIiAvPgo8L3N2Zz4K) - https://lucide.dev/icons/scan-line
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
  Scan_line as S
};
