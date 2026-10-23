import { g as sanitize_props, i as spread_props, j as slot, d as derived, r as run, D as props_id, z as attributes, l as bind_props, A as clsx, C as cn, b as attr, e as escape_html, c as attr_class, f as attr_style, a as ensure_array_like } from "../app.js";
import { u as useId } from "./scroll-lock-B1ibcKMx.js";
import { w as watch } from "./watch.svelte-BcPoqckX.js";
import { C as Context, j as ARROW_LEFT, h as ARROW_RIGHT, A as ARROW_UP, k as ARROW_DOWN, H as HOME, l as END, d as isElementOrSVGElement } from "./is-C6cm64pF.js";
import { a as attachRef, b as boolToEmptyStrOrUndef, d as boolToStr, e as createBitsAttrs, f as createId, g as boxWith, m as mergeProps } from "./create-id-CUJmDlea.js";
import { i as isValidIndex } from "./arrays-CkOR4vx_.js";
import "clsx";
import { D as DOMContext, n as noop } from "./noop-BQS7OFs8.js";
import { h as html } from "./html-BMEe54ZQ.js";
import { B as Bold, T as Text_align_justify } from "./text-align-justify-CFymVZbq.js";
import { T as Text_align_start, a as Text_align_center, b as Text_align_end } from "./text-align-start-Cy9u9QqD.js";
import { I as Icon } from "./Icon-Dal7PqKK.js";
import { X } from "./x-CnxrMbMn.js";
import { T as Triangle_alert } from "./triangle-alert-CUvuB-HL.js";
import { M as Minus } from "./minus-Dqkhcxt7.js";
import { P as Plus } from "./plus-DjyPLC7A.js";
import { M as Maximize_2, I as Image } from "./maximize-2-u72Ahym6.js";
import { C as Circle_question_mark } from "./circle-question-mark-DY-RU614.js";
import { C as Chevron_left } from "./chevron-left-DCSOZwZl.js";
import { S as Search } from "./settings-C5xOKpV3.js";
import { L as Layers } from "./layers-_Ie6y7G_.js";
import { L as Lock } from "./lock-DAA4kTLl.js";
import { T as Trash_2 } from "./trash-2-CjFp3otc.js";
import { L as Lock_open } from "./lock-open-5Nny5RV8.js";
import { C as Copy } from "./copy-D9_YDhto.js";
import { S as Sliders_horizontal } from "./PreferencesMenu-CCPeta-5.js";
import { F as File_text } from "./file-text-PXEtmXm9.js";
import { S as Sticky_note } from "./sticky-note-CKw7DId8.js";
import { P as Pen_line } from "./pen-line-Bqgfmrgp.js";
import { S as Sparkles } from "./sparkles-DbQIaVG6.js";
import { T as Table_2 } from "./table-2-BLgeaPqt.js";
import { C as Calendar_clock } from "./calendar-clock-D72LURiz.js";
import { U as User_round } from "./user-round-Kft0Cy_Y.js";
import { C as Clipboard } from "./clipboard-BXuTlHdz.js";
import { C as Check } from "./check-DK45Y9SJ.js";
import { C as Clock_3 } from "./clock-3-CBFB3kOE.js";
function Align_horizontal_distribute_center($$renderer, $$props) {
  const $$sanitized_props = sanitize_props($$props);
  const iconNode = [
    [
      "rect",
      { "width": "6", "height": "14", "x": "4", "y": "5", "rx": "2" }
    ],
    [
      "rect",
      { "width": "6", "height": "10", "x": "14", "y": "7", "rx": "2" }
    ],
    ["path", { "d": "M17 22v-5" }],
    ["path", { "d": "M17 7V2" }],
    ["path", { "d": "M7 22v-3" }],
    ["path", { "d": "M7 5V2" }]
  ];
  Icon($$renderer, spread_props([
    { name: "align-horizontal-distribute-center" },
    $$sanitized_props,
    {
      /**
       * @component @name AlignHorizontalDistributeCenter
       * @description Lucide SVG icon component, renders SVG Element with children.
       *
       * @preview ![img](data:image/svg+xml;base64,PHN2ZyAgeG1sbnM9Imh0dHA6Ly93d3cudzMub3JnLzIwMDAvc3ZnIgogIHdpZHRoPSIyNCIKICBoZWlnaHQ9IjI0IgogIHZpZXdCb3g9IjAgMCAyNCAyNCIKICBmaWxsPSJub25lIgogIHN0cm9rZT0iIzAwMCIgc3R5bGU9ImJhY2tncm91bmQtY29sb3I6ICNmZmY7IGJvcmRlci1yYWRpdXM6IDJweCIKICBzdHJva2Utd2lkdGg9IjIiCiAgc3Ryb2tlLWxpbmVjYXA9InJvdW5kIgogIHN0cm9rZS1saW5lam9pbj0icm91bmQiCj4KICA8cmVjdCB3aWR0aD0iNiIgaGVpZ2h0PSIxNCIgeD0iNCIgeT0iNSIgcng9IjIiIC8+CiAgPHJlY3Qgd2lkdGg9IjYiIGhlaWdodD0iMTAiIHg9IjE0IiB5PSI3IiByeD0iMiIgLz4KICA8cGF0aCBkPSJNMTcgMjJ2LTUiIC8+CiAgPHBhdGggZD0iTTE3IDdWMiIgLz4KICA8cGF0aCBkPSJNNyAyMnYtMyIgLz4KICA8cGF0aCBkPSJNNyA1VjIiIC8+Cjwvc3ZnPgo=) - https://lucide.dev/icons/align-horizontal-distribute-center
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
function Align_horizontal_justify_center($$renderer, $$props) {
  const $$sanitized_props = sanitize_props($$props);
  const iconNode = [
    [
      "rect",
      { "width": "6", "height": "14", "x": "2", "y": "5", "rx": "2" }
    ],
    [
      "rect",
      { "width": "6", "height": "10", "x": "16", "y": "7", "rx": "2" }
    ],
    ["path", { "d": "M12 2v20" }]
  ];
  Icon($$renderer, spread_props([
    { name: "align-horizontal-justify-center" },
    $$sanitized_props,
    {
      /**
       * @component @name AlignHorizontalJustifyCenter
       * @description Lucide SVG icon component, renders SVG Element with children.
       *
       * @preview ![img](data:image/svg+xml;base64,PHN2ZyAgeG1sbnM9Imh0dHA6Ly93d3cudzMub3JnLzIwMDAvc3ZnIgogIHdpZHRoPSIyNCIKICBoZWlnaHQ9IjI0IgogIHZpZXdCb3g9IjAgMCAyNCAyNCIKICBmaWxsPSJub25lIgogIHN0cm9rZT0iIzAwMCIgc3R5bGU9ImJhY2tncm91bmQtY29sb3I6ICNmZmY7IGJvcmRlci1yYWRpdXM6IDJweCIKICBzdHJva2Utd2lkdGg9IjIiCiAgc3Ryb2tlLWxpbmVjYXA9InJvdW5kIgogIHN0cm9rZS1saW5lam9pbj0icm91bmQiCj4KICA8cmVjdCB3aWR0aD0iNiIgaGVpZ2h0PSIxNCIgeD0iMiIgeT0iNSIgcng9IjIiIC8+CiAgPHJlY3Qgd2lkdGg9IjYiIGhlaWdodD0iMTAiIHg9IjE2IiB5PSI3IiByeD0iMiIgLz4KICA8cGF0aCBkPSJNMTIgMnYyMCIgLz4KPC9zdmc+Cg==) - https://lucide.dev/icons/align-horizontal-justify-center
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
function Align_horizontal_justify_end($$renderer, $$props) {
  const $$sanitized_props = sanitize_props($$props);
  const iconNode = [
    [
      "rect",
      { "width": "6", "height": "14", "x": "2", "y": "5", "rx": "2" }
    ],
    [
      "rect",
      { "width": "6", "height": "10", "x": "12", "y": "7", "rx": "2" }
    ],
    ["path", { "d": "M22 2v20" }]
  ];
  Icon($$renderer, spread_props([
    { name: "align-horizontal-justify-end" },
    $$sanitized_props,
    {
      /**
       * @component @name AlignHorizontalJustifyEnd
       * @description Lucide SVG icon component, renders SVG Element with children.
       *
       * @preview ![img](data:image/svg+xml;base64,PHN2ZyAgeG1sbnM9Imh0dHA6Ly93d3cudzMub3JnLzIwMDAvc3ZnIgogIHdpZHRoPSIyNCIKICBoZWlnaHQ9IjI0IgogIHZpZXdCb3g9IjAgMCAyNCAyNCIKICBmaWxsPSJub25lIgogIHN0cm9rZT0iIzAwMCIgc3R5bGU9ImJhY2tncm91bmQtY29sb3I6ICNmZmY7IGJvcmRlci1yYWRpdXM6IDJweCIKICBzdHJva2Utd2lkdGg9IjIiCiAgc3Ryb2tlLWxpbmVjYXA9InJvdW5kIgogIHN0cm9rZS1saW5lam9pbj0icm91bmQiCj4KICA8cmVjdCB3aWR0aD0iNiIgaGVpZ2h0PSIxNCIgeD0iMiIgeT0iNSIgcng9IjIiIC8+CiAgPHJlY3Qgd2lkdGg9IjYiIGhlaWdodD0iMTAiIHg9IjEyIiB5PSI3IiByeD0iMiIgLz4KICA8cGF0aCBkPSJNMjIgMnYyMCIgLz4KPC9zdmc+Cg==) - https://lucide.dev/icons/align-horizontal-justify-end
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
function Align_horizontal_justify_start($$renderer, $$props) {
  const $$sanitized_props = sanitize_props($$props);
  const iconNode = [
    [
      "rect",
      { "width": "6", "height": "14", "x": "6", "y": "5", "rx": "2" }
    ],
    [
      "rect",
      { "width": "6", "height": "10", "x": "16", "y": "7", "rx": "2" }
    ],
    ["path", { "d": "M2 2v20" }]
  ];
  Icon($$renderer, spread_props([
    { name: "align-horizontal-justify-start" },
    $$sanitized_props,
    {
      /**
       * @component @name AlignHorizontalJustifyStart
       * @description Lucide SVG icon component, renders SVG Element with children.
       *
       * @preview ![img](data:image/svg+xml;base64,PHN2ZyAgeG1sbnM9Imh0dHA6Ly93d3cudzMub3JnLzIwMDAvc3ZnIgogIHdpZHRoPSIyNCIKICBoZWlnaHQ9IjI0IgogIHZpZXdCb3g9IjAgMCAyNCAyNCIKICBmaWxsPSJub25lIgogIHN0cm9rZT0iIzAwMCIgc3R5bGU9ImJhY2tncm91bmQtY29sb3I6ICNmZmY7IGJvcmRlci1yYWRpdXM6IDJweCIKICBzdHJva2Utd2lkdGg9IjIiCiAgc3Ryb2tlLWxpbmVjYXA9InJvdW5kIgogIHN0cm9rZS1saW5lam9pbj0icm91bmQiCj4KICA8cmVjdCB3aWR0aD0iNiIgaGVpZ2h0PSIxNCIgeD0iNiIgeT0iNSIgcng9IjIiIC8+CiAgPHJlY3Qgd2lkdGg9IjYiIGhlaWdodD0iMTAiIHg9IjE2IiB5PSI3IiByeD0iMiIgLz4KICA8cGF0aCBkPSJNMiAydjIwIiAvPgo8L3N2Zz4K) - https://lucide.dev/icons/align-horizontal-justify-start
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
function Align_vertical_distribute_center($$renderer, $$props) {
  const $$sanitized_props = sanitize_props($$props);
  const iconNode = [
    ["path", { "d": "M22 17h-3" }],
    ["path", { "d": "M22 7h-5" }],
    ["path", { "d": "M5 17H2" }],
    ["path", { "d": "M7 7H2" }],
    [
      "rect",
      { "x": "5", "y": "14", "width": "14", "height": "6", "rx": "2" }
    ],
    [
      "rect",
      { "x": "7", "y": "4", "width": "10", "height": "6", "rx": "2" }
    ]
  ];
  Icon($$renderer, spread_props([
    { name: "align-vertical-distribute-center" },
    $$sanitized_props,
    {
      /**
       * @component @name AlignVerticalDistributeCenter
       * @description Lucide SVG icon component, renders SVG Element with children.
       *
       * @preview ![img](data:image/svg+xml;base64,PHN2ZyAgeG1sbnM9Imh0dHA6Ly93d3cudzMub3JnLzIwMDAvc3ZnIgogIHdpZHRoPSIyNCIKICBoZWlnaHQ9IjI0IgogIHZpZXdCb3g9IjAgMCAyNCAyNCIKICBmaWxsPSJub25lIgogIHN0cm9rZT0iIzAwMCIgc3R5bGU9ImJhY2tncm91bmQtY29sb3I6ICNmZmY7IGJvcmRlci1yYWRpdXM6IDJweCIKICBzdHJva2Utd2lkdGg9IjIiCiAgc3Ryb2tlLWxpbmVjYXA9InJvdW5kIgogIHN0cm9rZS1saW5lam9pbj0icm91bmQiCj4KICA8cGF0aCBkPSJNMjIgMTdoLTMiIC8+CiAgPHBhdGggZD0iTTIyIDdoLTUiIC8+CiAgPHBhdGggZD0iTTUgMTdIMiIgLz4KICA8cGF0aCBkPSJNNyA3SDIiIC8+CiAgPHJlY3QgeD0iNSIgeT0iMTQiIHdpZHRoPSIxNCIgaGVpZ2h0PSI2IiByeD0iMiIgLz4KICA8cmVjdCB4PSI3IiB5PSI0IiB3aWR0aD0iMTAiIGhlaWdodD0iNiIgcng9IjIiIC8+Cjwvc3ZnPgo=) - https://lucide.dev/icons/align-vertical-distribute-center
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
function Align_vertical_justify_center($$renderer, $$props) {
  const $$sanitized_props = sanitize_props($$props);
  const iconNode = [
    [
      "rect",
      { "width": "14", "height": "6", "x": "5", "y": "16", "rx": "2" }
    ],
    [
      "rect",
      { "width": "10", "height": "6", "x": "7", "y": "2", "rx": "2" }
    ],
    ["path", { "d": "M2 12h20" }]
  ];
  Icon($$renderer, spread_props([
    { name: "align-vertical-justify-center" },
    $$sanitized_props,
    {
      /**
       * @component @name AlignVerticalJustifyCenter
       * @description Lucide SVG icon component, renders SVG Element with children.
       *
       * @preview ![img](data:image/svg+xml;base64,PHN2ZyAgeG1sbnM9Imh0dHA6Ly93d3cudzMub3JnLzIwMDAvc3ZnIgogIHdpZHRoPSIyNCIKICBoZWlnaHQ9IjI0IgogIHZpZXdCb3g9IjAgMCAyNCAyNCIKICBmaWxsPSJub25lIgogIHN0cm9rZT0iIzAwMCIgc3R5bGU9ImJhY2tncm91bmQtY29sb3I6ICNmZmY7IGJvcmRlci1yYWRpdXM6IDJweCIKICBzdHJva2Utd2lkdGg9IjIiCiAgc3Ryb2tlLWxpbmVjYXA9InJvdW5kIgogIHN0cm9rZS1saW5lam9pbj0icm91bmQiCj4KICA8cmVjdCB3aWR0aD0iMTQiIGhlaWdodD0iNiIgeD0iNSIgeT0iMTYiIHJ4PSIyIiAvPgogIDxyZWN0IHdpZHRoPSIxMCIgaGVpZ2h0PSI2IiB4PSI3IiB5PSIyIiByeD0iMiIgLz4KICA8cGF0aCBkPSJNMiAxMmgyMCIgLz4KPC9zdmc+Cg==) - https://lucide.dev/icons/align-vertical-justify-center
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
function Align_vertical_justify_end($$renderer, $$props) {
  const $$sanitized_props = sanitize_props($$props);
  const iconNode = [
    [
      "rect",
      { "width": "14", "height": "6", "x": "5", "y": "12", "rx": "2" }
    ],
    [
      "rect",
      { "width": "10", "height": "6", "x": "7", "y": "2", "rx": "2" }
    ],
    ["path", { "d": "M2 22h20" }]
  ];
  Icon($$renderer, spread_props([
    { name: "align-vertical-justify-end" },
    $$sanitized_props,
    {
      /**
       * @component @name AlignVerticalJustifyEnd
       * @description Lucide SVG icon component, renders SVG Element with children.
       *
       * @preview ![img](data:image/svg+xml;base64,PHN2ZyAgeG1sbnM9Imh0dHA6Ly93d3cudzMub3JnLzIwMDAvc3ZnIgogIHdpZHRoPSIyNCIKICBoZWlnaHQ9IjI0IgogIHZpZXdCb3g9IjAgMCAyNCAyNCIKICBmaWxsPSJub25lIgogIHN0cm9rZT0iIzAwMCIgc3R5bGU9ImJhY2tncm91bmQtY29sb3I6ICNmZmY7IGJvcmRlci1yYWRpdXM6IDJweCIKICBzdHJva2Utd2lkdGg9IjIiCiAgc3Ryb2tlLWxpbmVjYXA9InJvdW5kIgogIHN0cm9rZS1saW5lam9pbj0icm91bmQiCj4KICA8cmVjdCB3aWR0aD0iMTQiIGhlaWdodD0iNiIgeD0iNSIgeT0iMTIiIHJ4PSIyIiAvPgogIDxyZWN0IHdpZHRoPSIxMCIgaGVpZ2h0PSI2IiB4PSI3IiB5PSIyIiByeD0iMiIgLz4KICA8cGF0aCBkPSJNMiAyMmgyMCIgLz4KPC9zdmc+Cg==) - https://lucide.dev/icons/align-vertical-justify-end
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
function Align_vertical_justify_start($$renderer, $$props) {
  const $$sanitized_props = sanitize_props($$props);
  const iconNode = [
    [
      "rect",
      { "width": "14", "height": "6", "x": "5", "y": "16", "rx": "2" }
    ],
    [
      "rect",
      { "width": "10", "height": "6", "x": "7", "y": "6", "rx": "2" }
    ],
    ["path", { "d": "M2 2h20" }]
  ];
  Icon($$renderer, spread_props([
    { name: "align-vertical-justify-start" },
    $$sanitized_props,
    {
      /**
       * @component @name AlignVerticalJustifyStart
       * @description Lucide SVG icon component, renders SVG Element with children.
       *
       * @preview ![img](data:image/svg+xml;base64,PHN2ZyAgeG1sbnM9Imh0dHA6Ly93d3cudzMub3JnLzIwMDAvc3ZnIgogIHdpZHRoPSIyNCIKICBoZWlnaHQ9IjI0IgogIHZpZXdCb3g9IjAgMCAyNCAyNCIKICBmaWxsPSJub25lIgogIHN0cm9rZT0iIzAwMCIgc3R5bGU9ImJhY2tncm91bmQtY29sb3I6ICNmZmY7IGJvcmRlci1yYWRpdXM6IDJweCIKICBzdHJva2Utd2lkdGg9IjIiCiAgc3Ryb2tlLWxpbmVjYXA9InJvdW5kIgogIHN0cm9rZS1saW5lam9pbj0icm91bmQiCj4KICA8cmVjdCB3aWR0aD0iMTQiIGhlaWdodD0iNiIgeD0iNSIgeT0iMTYiIHJ4PSIyIiAvPgogIDxyZWN0IHdpZHRoPSIxMCIgaGVpZ2h0PSI2IiB4PSI3IiB5PSI2IiByeD0iMiIgLz4KICA8cGF0aCBkPSJNMiAyaDIwIiAvPgo8L3N2Zz4K) - https://lucide.dev/icons/align-vertical-justify-start
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
function Badge_percent($$renderer, $$props) {
  const $$sanitized_props = sanitize_props($$props);
  const iconNode = [
    [
      "path",
      {
        "d": "M3.85 8.62a4 4 0 0 1 4.78-4.77 4 4 0 0 1 6.74 0 4 4 0 0 1 4.78 4.78 4 4 0 0 1 0 6.74 4 4 0 0 1-4.77 4.78 4 4 0 0 1-6.75 0 4 4 0 0 1-4.78-4.77 4 4 0 0 1 0-6.76Z"
      }
    ],
    ["path", { "d": "m15 9-6 6" }],
    ["path", { "d": "M9 9h.01" }],
    ["path", { "d": "M15 15h.01" }]
  ];
  Icon($$renderer, spread_props([
    { name: "badge-percent" },
    $$sanitized_props,
    {
      /**
       * @component @name BadgePercent
       * @description Lucide SVG icon component, renders SVG Element with children.
       *
       * @preview ![img](data:image/svg+xml;base64,PHN2ZyAgeG1sbnM9Imh0dHA6Ly93d3cudzMub3JnLzIwMDAvc3ZnIgogIHdpZHRoPSIyNCIKICBoZWlnaHQ9IjI0IgogIHZpZXdCb3g9IjAgMCAyNCAyNCIKICBmaWxsPSJub25lIgogIHN0cm9rZT0iIzAwMCIgc3R5bGU9ImJhY2tncm91bmQtY29sb3I6ICNmZmY7IGJvcmRlci1yYWRpdXM6IDJweCIKICBzdHJva2Utd2lkdGg9IjIiCiAgc3Ryb2tlLWxpbmVjYXA9InJvdW5kIgogIHN0cm9rZS1saW5lam9pbj0icm91bmQiCj4KICA8cGF0aCBkPSJNMy44NSA4LjYyYTQgNCAwIDAgMSA0Ljc4LTQuNzcgNCA0IDAgMCAxIDYuNzQgMCA0IDQgMCAwIDEgNC43OCA0Ljc4IDQgNCAwIDAgMSAwIDYuNzQgNCA0IDAgMCAxLTQuNzcgNC43OCA0IDQgMCAwIDEtNi43NSAwIDQgNCAwIDAgMS00Ljc4LTQuNzcgNCA0IDAgMCAxIDAtNi43NloiIC8+CiAgPHBhdGggZD0ibTE1IDktNiA2IiAvPgogIDxwYXRoIGQ9Ik05IDloLjAxIiAvPgogIDxwYXRoIGQ9Ik0xNSAxNWguMDEiIC8+Cjwvc3ZnPgo=) - https://lucide.dev/icons/badge-percent
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
function Brackets($$renderer, $$props) {
  const $$sanitized_props = sanitize_props($$props);
  const iconNode = [
    ["path", { "d": "M16 3h3a1 1 0 0 1 1 1v16a1 1 0 0 1-1 1h-3" }],
    ["path", { "d": "M8 21H5a1 1 0 0 1-1-1V4a1 1 0 0 1 1-1h3" }]
  ];
  Icon($$renderer, spread_props([
    { name: "brackets" },
    $$sanitized_props,
    {
      /**
       * @component @name Brackets
       * @description Lucide SVG icon component, renders SVG Element with children.
       *
       * @preview ![img](data:image/svg+xml;base64,PHN2ZyAgeG1sbnM9Imh0dHA6Ly93d3cudzMub3JnLzIwMDAvc3ZnIgogIHdpZHRoPSIyNCIKICBoZWlnaHQ9IjI0IgogIHZpZXdCb3g9IjAgMCAyNCAyNCIKICBmaWxsPSJub25lIgogIHN0cm9rZT0iIzAwMCIgc3R5bGU9ImJhY2tncm91bmQtY29sb3I6ICNmZmY7IGJvcmRlci1yYWRpdXM6IDJweCIKICBzdHJva2Utd2lkdGg9IjIiCiAgc3Ryb2tlLWxpbmVjYXA9InJvdW5kIgogIHN0cm9rZS1saW5lam9pbj0icm91bmQiCj4KICA8cGF0aCBkPSJNMTYgM2gzYTEgMSAwIDAgMSAxIDF2MTZhMSAxIDAgMCAxLTEgMWgtMyIgLz4KICA8cGF0aCBkPSJNOCAyMUg1YTEgMSAwIDAgMS0xLTFWNGExIDEgMCAwIDEgMS0xaDMiIC8+Cjwvc3ZnPgo=) - https://lucide.dev/icons/brackets
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
function Copy_plus($$renderer, $$props) {
  const $$sanitized_props = sanitize_props($$props);
  const iconNode = [
    ["line", { "x1": "15", "x2": "15", "y1": "12", "y2": "18" }],
    ["line", { "x1": "12", "x2": "18", "y1": "15", "y2": "15" }],
    [
      "rect",
      {
        "width": "14",
        "height": "14",
        "x": "8",
        "y": "8",
        "rx": "2",
        "ry": "2"
      }
    ],
    [
      "path",
      {
        "d": "M4 16c-1.1 0-2-.9-2-2V4c0-1.1.9-2 2-2h10c1.1 0 2 .9 2 2"
      }
    ]
  ];
  Icon($$renderer, spread_props([
    { name: "copy-plus" },
    $$sanitized_props,
    {
      /**
       * @component @name CopyPlus
       * @description Lucide SVG icon component, renders SVG Element with children.
       *
       * @preview ![img](data:image/svg+xml;base64,PHN2ZyAgeG1sbnM9Imh0dHA6Ly93d3cudzMub3JnLzIwMDAvc3ZnIgogIHdpZHRoPSIyNCIKICBoZWlnaHQ9IjI0IgogIHZpZXdCb3g9IjAgMCAyNCAyNCIKICBmaWxsPSJub25lIgogIHN0cm9rZT0iIzAwMCIgc3R5bGU9ImJhY2tncm91bmQtY29sb3I6ICNmZmY7IGJvcmRlci1yYWRpdXM6IDJweCIKICBzdHJva2Utd2lkdGg9IjIiCiAgc3Ryb2tlLWxpbmVjYXA9InJvdW5kIgogIHN0cm9rZS1saW5lam9pbj0icm91bmQiCj4KICA8bGluZSB4MT0iMTUiIHgyPSIxNSIgeTE9IjEyIiB5Mj0iMTgiIC8+CiAgPGxpbmUgeDE9IjEyIiB4Mj0iMTgiIHkxPSIxNSIgeTI9IjE1IiAvPgogIDxyZWN0IHdpZHRoPSIxNCIgaGVpZ2h0PSIxNCIgeD0iOCIgeT0iOCIgcng9IjIiIHJ5PSIyIiAvPgogIDxwYXRoIGQ9Ik00IDE2Yy0xLjEgMC0yLS45LTItMlY0YzAtMS4xLjktMiAyLTJoMTBjMS4xIDAgMiAuOSAyIDIiIC8+Cjwvc3ZnPgo=) - https://lucide.dev/icons/copy-plus
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
function Hand($$renderer, $$props) {
  const $$sanitized_props = sanitize_props($$props);
  const iconNode = [
    ["path", { "d": "M18 11V6a2 2 0 0 0-2-2a2 2 0 0 0-2 2" }],
    ["path", { "d": "M14 10V4a2 2 0 0 0-2-2a2 2 0 0 0-2 2v2" }],
    ["path", { "d": "M10 10.5V6a2 2 0 0 0-2-2a2 2 0 0 0-2 2v8" }],
    [
      "path",
      {
        "d": "M18 8a2 2 0 1 1 4 0v6a8 8 0 0 1-8 8h-2c-2.8 0-4.5-.86-5.99-2.34l-3.6-3.6a2 2 0 0 1 2.83-2.82L7 15"
      }
    ]
  ];
  Icon($$renderer, spread_props([
    { name: "hand" },
    $$sanitized_props,
    {
      /**
       * @component @name Hand
       * @description Lucide SVG icon component, renders SVG Element with children.
       *
       * @preview ![img](data:image/svg+xml;base64,PHN2ZyAgeG1sbnM9Imh0dHA6Ly93d3cudzMub3JnLzIwMDAvc3ZnIgogIHdpZHRoPSIyNCIKICBoZWlnaHQ9IjI0IgogIHZpZXdCb3g9IjAgMCAyNCAyNCIKICBmaWxsPSJub25lIgogIHN0cm9rZT0iIzAwMCIgc3R5bGU9ImJhY2tncm91bmQtY29sb3I6ICNmZmY7IGJvcmRlci1yYWRpdXM6IDJweCIKICBzdHJva2Utd2lkdGg9IjIiCiAgc3Ryb2tlLWxpbmVjYXA9InJvdW5kIgogIHN0cm9rZS1saW5lam9pbj0icm91bmQiCj4KICA8cGF0aCBkPSJNMTggMTFWNmEyIDIgMCAwIDAtMi0yYTIgMiAwIDAgMC0yIDIiIC8+CiAgPHBhdGggZD0iTTE0IDEwVjRhMiAyIDAgMCAwLTItMmEyIDIgMCAwIDAtMiAydjIiIC8+CiAgPHBhdGggZD0iTTEwIDEwLjVWNmEyIDIgMCAwIDAtMi0yYTIgMiAwIDAgMC0yIDJ2OCIgLz4KICA8cGF0aCBkPSJNMTggOGEyIDIgMCAxIDEgNCAwdjZhOCA4IDAgMCAxLTggOGgtMmMtMi44IDAtNC41LS44Ni01Ljk5LTIuMzRsLTMuNi0zLjZhMiAyIDAgMCAxIDIuODMtMi44Mkw3IDE1IiAvPgo8L3N2Zz4K) - https://lucide.dev/icons/hand
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
function Keyboard($$renderer, $$props) {
  const $$sanitized_props = sanitize_props($$props);
  const iconNode = [
    ["path", { "d": "M10 8h.01" }],
    ["path", { "d": "M12 12h.01" }],
    ["path", { "d": "M14 8h.01" }],
    ["path", { "d": "M16 12h.01" }],
    ["path", { "d": "M18 8h.01" }],
    ["path", { "d": "M6 8h.01" }],
    ["path", { "d": "M7 16h10" }],
    ["path", { "d": "M8 12h.01" }],
    [
      "rect",
      { "width": "20", "height": "16", "x": "2", "y": "4", "rx": "2" }
    ]
  ];
  Icon($$renderer, spread_props([
    { name: "keyboard" },
    $$sanitized_props,
    {
      /**
       * @component @name Keyboard
       * @description Lucide SVG icon component, renders SVG Element with children.
       *
       * @preview ![img](data:image/svg+xml;base64,PHN2ZyAgeG1sbnM9Imh0dHA6Ly93d3cudzMub3JnLzIwMDAvc3ZnIgogIHdpZHRoPSIyNCIKICBoZWlnaHQ9IjI0IgogIHZpZXdCb3g9IjAgMCAyNCAyNCIKICBmaWxsPSJub25lIgogIHN0cm9rZT0iIzAwMCIgc3R5bGU9ImJhY2tncm91bmQtY29sb3I6ICNmZmY7IGJvcmRlci1yYWRpdXM6IDJweCIKICBzdHJva2Utd2lkdGg9IjIiCiAgc3Ryb2tlLWxpbmVjYXA9InJvdW5kIgogIHN0cm9rZS1saW5lam9pbj0icm91bmQiCj4KICA8cGF0aCBkPSJNMTAgOGguMDEiIC8+CiAgPHBhdGggZD0iTTEyIDEyaC4wMSIgLz4KICA8cGF0aCBkPSJNMTQgOGguMDEiIC8+CiAgPHBhdGggZD0iTTE2IDEyaC4wMSIgLz4KICA8cGF0aCBkPSJNMTggOGguMDEiIC8+CiAgPHBhdGggZD0iTTYgOGguMDEiIC8+CiAgPHBhdGggZD0iTTcgMTZoMTAiIC8+CiAgPHBhdGggZD0iTTggMTJoLjAxIiAvPgogIDxyZWN0IHdpZHRoPSIyMCIgaGVpZ2h0PSIxNiIgeD0iMiIgeT0iNCIgcng9IjIiIC8+Cjwvc3ZnPgo=) - https://lucide.dev/icons/keyboard
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
function Mouse_pointer_2($$renderer, $$props) {
  const $$sanitized_props = sanitize_props($$props);
  const iconNode = [
    [
      "path",
      {
        "d": "M4.037 4.688a.495.495 0 0 1 .651-.651l16 6.5a.5.5 0 0 1-.063.947l-6.124 1.58a2 2 0 0 0-1.438 1.435l-1.579 6.126a.5.5 0 0 1-.947.063z"
      }
    ]
  ];
  Icon($$renderer, spread_props([
    { name: "mouse-pointer-2" },
    $$sanitized_props,
    {
      /**
       * @component @name MousePointer2
       * @description Lucide SVG icon component, renders SVG Element with children.
       *
       * @preview ![img](data:image/svg+xml;base64,PHN2ZyAgeG1sbnM9Imh0dHA6Ly93d3cudzMub3JnLzIwMDAvc3ZnIgogIHdpZHRoPSIyNCIKICBoZWlnaHQ9IjI0IgogIHZpZXdCb3g9IjAgMCAyNCAyNCIKICBmaWxsPSJub25lIgogIHN0cm9rZT0iIzAwMCIgc3R5bGU9ImJhY2tncm91bmQtY29sb3I6ICNmZmY7IGJvcmRlci1yYWRpdXM6IDJweCIKICBzdHJva2Utd2lkdGg9IjIiCiAgc3Ryb2tlLWxpbmVjYXA9InJvdW5kIgogIHN0cm9rZS1saW5lam9pbj0icm91bmQiCj4KICA8cGF0aCBkPSJNNC4wMzcgNC42ODhhLjQ5NS40OTUgMCAwIDEgLjY1MS0uNjUxbDE2IDYuNWEuNS41IDAgMCAxLS4wNjMuOTQ3bC02LjEyNCAxLjU4YTIgMiAwIDAgMC0xLjQzOCAxLjQzNWwtMS41NzkgNi4xMjZhLjUuNSAwIDAgMS0uOTQ3LjA2M3oiIC8+Cjwvc3ZnPgo=) - https://lucide.dev/icons/mouse-pointer-2
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
class SvelteResizeObserver {
  #node;
  #onResize;
  constructor(node, onResize) {
    this.#node = node;
    this.#onResize = onResize;
    this.handler = this.handler.bind(this);
  }
  handler() {
    let rAF = 0;
    const _node = this.#node();
    if (!_node) return;
    const resizeObserver = new ResizeObserver(() => {
      cancelAnimationFrame(rAF);
      rAF = window.requestAnimationFrame(this.#onResize);
    });
    resizeObserver.observe(_node);
    return () => {
      window.cancelAnimationFrame(rAF);
      resizeObserver.unobserve(_node);
    };
  }
}
function getRangeStyles(direction, min, max) {
  const styles = {
    position: "absolute"
  };
  if (direction === "lr") {
    styles.left = `${min}%`;
    styles.right = `${max}%`;
  } else if (direction === "rl") {
    styles.right = `${min}%`;
    styles.left = `${max}%`;
  } else if (direction === "bt") {
    styles.bottom = `${min}%`;
    styles.top = `${max}%`;
  } else {
    styles.top = `${min}%`;
    styles.bottom = `${max}%`;
  }
  return styles;
}
function getThumbStyles(direction, thumbPos) {
  const styles = {
    position: "absolute"
  };
  if (direction === "lr") {
    styles.left = `${thumbPos}%`;
    styles.translate = "-50% 0";
  } else if (direction === "rl") {
    styles.right = `${thumbPos}%`;
    styles.translate = "50% 0";
  } else if (direction === "bt") {
    styles.bottom = `${thumbPos}%`;
    styles.translate = "0 50%";
  } else {
    styles.top = `${thumbPos}%`;
    styles.translate = "0 -50%";
  }
  return styles;
}
function getTickStyles(direction, tickPosition, offsetPercentage) {
  const style = {
    position: "absolute"
  };
  if (direction === "lr") {
    style.left = `${tickPosition}%`;
    style.translate = `${offsetPercentage}% 0`;
  } else if (direction === "rl") {
    style.right = `${tickPosition}%`;
    style.translate = `${-offsetPercentage}% 0`;
  } else if (direction === "bt") {
    style.bottom = `${tickPosition}%`;
    style.translate = `0 ${-offsetPercentage}%`;
  } else {
    style.top = `${tickPosition}%`;
    style.translate = `0 ${offsetPercentage}%`;
  }
  return style;
}
function getDecimalPlaces(num) {
  if (Math.floor(num) === num)
    return 0;
  const str = num.toString();
  if (str.indexOf(".") !== -1 && str.indexOf("e-") === -1) {
    return str.split(".")[1].length;
  } else if (str.indexOf("e-") !== -1) {
    const parts = str.split("e-");
    return parseInt(parts[1], 10);
  }
  return 0;
}
function roundToPrecision(num, precision) {
  const factor = Math.pow(10, precision);
  return Math.round(num * factor) / factor;
}
function normalizeSteps(step, min, max) {
  if (typeof step === "number") {
    const difference = max - min;
    let count = Math.ceil(difference / step);
    const precision = getDecimalPlaces(step);
    const factor = Math.pow(10, precision);
    const intDifference = Math.round(difference * factor);
    const intStep = Math.round(step * factor);
    if (intDifference % intStep === 0) {
      count++;
    }
    const steps = [];
    for (let i = 0; i < count; i++) {
      const value = min + i * step;
      const roundedValue = roundToPrecision(value, precision);
      steps.push(roundedValue);
    }
    return steps;
  }
  return [...new Set(step)].filter((value) => value >= min && value <= max).sort((a, b) => a - b);
}
function snapValueToCustomSteps(value, steps) {
  if (steps.length === 0)
    return value;
  let closest = steps[0];
  let minDistance = Math.abs(value - closest);
  for (const step of steps) {
    const distance = Math.abs(value - step);
    if (distance < minDistance) {
      minDistance = distance;
      closest = step;
    }
  }
  return closest;
}
function getAdjacentStepValue(currentValue, steps, direction) {
  const currentIndex = steps.indexOf(currentValue);
  if (currentIndex === -1) {
    return snapValueToCustomSteps(currentValue, steps);
  }
  if (direction === "next") {
    return currentIndex < steps.length - 1 ? steps[currentIndex + 1] : currentValue;
  } else {
    return currentIndex > 0 ? steps[currentIndex - 1] : currentValue;
  }
}
function linearScale(domain, range, clamp2 = true) {
  const [d0, d1] = domain;
  const [r0, r1] = range;
  const slope = (r1 - r0) / (d1 - d0);
  return (x) => {
    const result = r0 + slope * (x - d0);
    if (!clamp2)
      return result;
    if (result > Math.max(r0, r1))
      return Math.max(r0, r1);
    if (result < Math.min(r0, r1))
      return Math.min(r0, r1);
    return result;
  };
}
const sliderAttrs = createBitsAttrs({
  component: "slider",
  parts: [
    "root",
    "thumb",
    "range",
    "tick",
    "tick-label",
    "thumb-label"
  ]
});
const SliderRootContext = new Context("Slider.Root");
class SliderBaseRootState {
  opts;
  attachment;
  isActive = false;
  #layoutVersion = 0;
  #direction = derived(() => {
    if (this.opts.orientation.current === "horizontal") {
      return this.opts.dir.current === "rtl" ? "rl" : "lr";
    } else {
      return this.opts.dir.current === "rtl" ? "tb" : "bt";
    }
  });
  get direction() {
    return this.#direction();
  }
  set direction($$value) {
    return this.#direction($$value);
  }
  #normalizedSteps = derived(() => {
    return normalizeSteps(this.opts.step.current, this.opts.min.current, this.opts.max.current);
  });
  get normalizedSteps() {
    return this.#normalizedSteps();
  }
  set normalizedSteps($$value) {
    return this.#normalizedSteps($$value);
  }
  domContext;
  constructor(opts) {
    this.opts = opts;
    this.attachment = attachRef(opts.ref);
    this.domContext = new DOMContext(this.opts.ref);
    new SvelteResizeObserver(() => this.opts.ref.current, this.#handleLayoutChange);
  }
  #handleLayoutChange = () => {
    this.#layoutVersion += 1;
  };
  isThumbActive(_index) {
    return this.isActive;
  }
  #touchAction = derived(() => {
    if (this.opts.disabled.current) return void 0;
    return this.opts.orientation.current === "horizontal" ? "pan-y" : "pan-x";
  });
  getAllThumbs = () => {
    const node = this.opts.ref.current;
    if (!node) return [];
    return Array.from(node.querySelectorAll(sliderAttrs.selector("thumb")));
  };
  getThumbScale = () => {
    void this.#layoutVersion;
    const trackPadding = this.opts.trackPadding?.current;
    if (trackPadding !== void 0 && trackPadding > 0) {
      return [trackPadding, 100 - trackPadding];
    }
    if (this.opts.thumbPositioning.current === "exact") {
      return [0, 100];
    }
    const isVertical = this.opts.orientation.current === "vertical";
    const activeThumb = this.getAllThumbs()[0];
    const thumbSize = isVertical ? activeThumb?.offsetHeight : activeThumb?.offsetWidth;
    if (thumbSize === void 0 || Number.isNaN(thumbSize) || thumbSize === 0) return [0, 100];
    const trackSize = isVertical ? this.opts.ref.current?.offsetHeight : this.opts.ref.current?.offsetWidth;
    if (trackSize === void 0 || Number.isNaN(trackSize) || trackSize === 0) return [0, 100];
    const percentPadding = thumbSize / 2 / trackSize * 100;
    const min = percentPadding;
    const max = 100 - percentPadding;
    return [min, max];
  };
  getPositionFromValue = (thumbValue) => {
    const thumbScale = this.getThumbScale();
    const scale = linearScale([this.opts.min.current, this.opts.max.current], thumbScale);
    return scale(thumbValue);
  };
  #props = derived(() => ({
    id: this.opts.id.current,
    "data-orientation": this.opts.orientation.current,
    "data-disabled": boolToEmptyStrOrUndef(this.opts.disabled.current),
    style: { touchAction: this.#touchAction() },
    [sliderAttrs.root]: "",
    ...this.attachment
  }));
  get props() {
    return this.#props();
  }
  set props($$value) {
    return this.#props($$value);
  }
}
class SliderSingleRootState extends SliderBaseRootState {
  opts;
  isMulti = false;
  constructor(opts) {
    super(opts);
    this.opts = opts;
    watch(
      [
        () => this.opts.step.current,
        () => this.opts.min.current,
        () => this.opts.max.current,
        () => this.opts.value.current
      ],
      ([step, min, max, value]) => {
        const steps = normalizeSteps(step, min, max);
        const isValidValue = (v) => {
          return steps.includes(v);
        };
        const gcv = (v) => {
          return snapValueToCustomSteps(v, steps);
        };
        if (!isValidValue(value)) {
          this.opts.value.current = gcv(value);
        }
      }
    );
  }
  isTickValueSelected = (tickValue) => {
    return this.opts.value.current === tickValue;
  };
  applyPosition({ clientXY, start, end }) {
    const min = this.opts.min.current;
    const max = this.opts.max.current;
    const percent = (clientXY - start) / (end - start);
    const val = percent * (max - min) + min;
    if (val < min) {
      this.updateValue(min);
    } else if (val > max) {
      this.updateValue(max);
    } else {
      const steps = this.normalizedSteps;
      const newValue = snapValueToCustomSteps(val, steps);
      this.updateValue(newValue);
    }
  }
  updateValue = (newValue) => {
    this.opts.value.current = snapValueToCustomSteps(newValue, this.normalizedSteps);
  };
  handlePointerMove = (e) => {
    if (!this.isActive || this.opts.disabled.current) return;
    e.preventDefault();
    e.stopPropagation();
    const sliderNode = this.opts.ref.current;
    const activeThumb = this.getAllThumbs()[0];
    if (!sliderNode || !activeThumb) return;
    activeThumb.focus();
    const { left, right, top, bottom } = sliderNode.getBoundingClientRect();
    if (this.direction === "lr") {
      this.applyPosition({ clientXY: e.clientX, start: left, end: right });
    } else if (this.direction === "rl") {
      this.applyPosition({ clientXY: e.clientX, start: right, end: left });
    } else if (this.direction === "bt") {
      this.applyPosition({ clientXY: e.clientY, start: bottom, end: top });
    } else if (this.direction === "tb") {
      this.applyPosition({ clientXY: e.clientY, start: top, end: bottom });
    }
  };
  handlePointerDown = (e) => {
    if (e.button !== 0 || this.opts.disabled.current) return;
    const sliderNode = this.opts.ref.current;
    const closestThumb = this.getAllThumbs()[0];
    if (!closestThumb || !sliderNode) return;
    const target = e.composedPath()[0] ?? e.target;
    if (!isElementOrSVGElement(target) || !sliderNode.contains(target)) return;
    e.preventDefault();
    closestThumb.focus();
    this.isActive = true;
    this.handlePointerMove(e);
  };
  handlePointerUp = () => {
    if (this.opts.disabled.current) return;
    if (this.isActive) {
      this.opts.onValueCommit.current(run(() => this.opts.value.current));
    }
    this.isActive = false;
  };
  #thumbsPropsArr = derived(() => {
    const currValue = this.opts.value.current;
    return Array.from({ length: 1 }, () => {
      const thumbValue = currValue;
      const thumbPosition = this.getPositionFromValue(thumbValue);
      const style = getThumbStyles(this.direction, thumbPosition);
      return {
        role: "slider",
        "aria-valuemin": this.opts.min.current,
        "aria-valuemax": this.opts.max.current,
        "aria-valuenow": thumbValue,
        "aria-disabled": boolToStr(this.opts.disabled.current),
        "aria-orientation": this.opts.orientation.current,
        "data-value": thumbValue,
        "data-orientation": this.opts.orientation.current,
        style,
        [sliderAttrs.thumb]: ""
      };
    });
  });
  get thumbsPropsArr() {
    return this.#thumbsPropsArr();
  }
  set thumbsPropsArr($$value) {
    return this.#thumbsPropsArr($$value);
  }
  #thumbsRenderArr = derived(() => {
    return this.thumbsPropsArr.map((_, i) => i);
  });
  get thumbsRenderArr() {
    return this.#thumbsRenderArr();
  }
  set thumbsRenderArr($$value) {
    return this.#thumbsRenderArr($$value);
  }
  #ticksPropsArr = derived(() => {
    const steps = this.normalizedSteps;
    const currValue = this.opts.value.current;
    return steps.map((tickValue, i) => {
      const tickPosition = this.getPositionFromValue(tickValue);
      const isFirst = i === 0;
      const isLast = i === steps.length - 1;
      const offsetPercentage = isFirst ? 0 : isLast ? -100 : -50;
      const style = getTickStyles(this.direction, tickPosition, offsetPercentage);
      const bounded = tickValue <= currValue;
      return {
        "data-disabled": boolToEmptyStrOrUndef(this.opts.disabled.current),
        "data-orientation": this.opts.orientation.current,
        "data-bounded": bounded ? "" : void 0,
        "data-value": tickValue,
        "data-selected": this.isTickValueSelected(tickValue) ? "" : void 0,
        style,
        [sliderAttrs.tick]: ""
      };
    });
  });
  get ticksPropsArr() {
    return this.#ticksPropsArr();
  }
  set ticksPropsArr($$value) {
    return this.#ticksPropsArr($$value);
  }
  #ticksRenderArr = derived(() => {
    return this.ticksPropsArr.map((_, i) => i);
  });
  get ticksRenderArr() {
    return this.#ticksRenderArr();
  }
  set ticksRenderArr($$value) {
    return this.#ticksRenderArr($$value);
  }
  #tickItemsArr = derived(() => {
    return this.ticksPropsArr.map((tick, i) => ({ value: tick["data-value"], index: i }));
  });
  get tickItemsArr() {
    return this.#tickItemsArr();
  }
  set tickItemsArr($$value) {
    return this.#tickItemsArr($$value);
  }
  #thumbItemsArr = derived(() => {
    const currValue = this.opts.value.current;
    return [{ value: currValue, index: 0 }];
  });
  get thumbItemsArr() {
    return this.#thumbItemsArr();
  }
  set thumbItemsArr($$value) {
    return this.#thumbItemsArr($$value);
  }
  #snippetProps = derived(() => ({
    ticks: this.ticksRenderArr,
    thumbs: this.thumbsRenderArr,
    tickItems: this.tickItemsArr,
    thumbItems: this.thumbItemsArr
  }));
  get snippetProps() {
    return this.#snippetProps();
  }
  set snippetProps($$value) {
    return this.#snippetProps($$value);
  }
}
class SliderMultiRootState extends SliderBaseRootState {
  opts;
  isMulti = true;
  activeThumb = null;
  currentThumbIdx = 0;
  constructor(opts) {
    super(opts);
    this.opts = opts;
    watch(
      [
        () => this.opts.step.current,
        () => this.opts.min.current,
        () => this.opts.max.current,
        () => this.opts.value.current
      ],
      ([step, min, max, value]) => {
        const steps = normalizeSteps(step, min, max);
        const isValidValue = (v) => {
          return steps.includes(v);
        };
        const gcv = (v) => {
          return snapValueToCustomSteps(v, steps);
        };
        if (value.some((v) => !isValidValue(v))) {
          this.opts.value.current = value.map(gcv);
        }
      }
    );
  }
  isTickValueSelected = (tickValue) => {
    return this.opts.value.current.includes(tickValue);
  };
  isThumbActive(index) {
    return this.isActive && this.activeThumb?.idx === index;
  }
  applyPosition({ clientXY, activeThumbIdx, start, end }) {
    const min = this.opts.min.current;
    const max = this.opts.max.current;
    const percent = (clientXY - start) / (end - start);
    const val = percent * (max - min) + min;
    if (val < min) {
      this.updateValue(min, activeThumbIdx);
    } else if (val > max) {
      this.updateValue(max, activeThumbIdx);
    } else {
      const steps = this.normalizedSteps;
      const newValue = snapValueToCustomSteps(val, steps);
      this.updateValue(newValue, activeThumbIdx);
    }
  }
  #getClosestThumb = (e) => {
    const thumbs = this.getAllThumbs();
    if (!thumbs.length) return;
    for (const thumb of thumbs) {
      thumb.blur();
    }
    const distances = thumbs.map((thumb) => {
      if (this.opts.orientation.current === "horizontal") {
        const { left, right } = thumb.getBoundingClientRect();
        return Math.abs(e.clientX - (left + right) / 2);
      } else {
        const { top, bottom } = thumb.getBoundingClientRect();
        return Math.abs(e.clientY - (top + bottom) / 2);
      }
    });
    const node = thumbs[distances.indexOf(Math.min(...distances))];
    const idx = thumbs.indexOf(node);
    return { node, idx };
  };
  handlePointerMove = (e) => {
    if (!this.isActive || this.opts.disabled.current) return;
    e.preventDefault();
    e.stopPropagation();
    const sliderNode = this.opts.ref.current;
    const activeThumb = this.activeThumb;
    if (!sliderNode || !activeThumb) return;
    activeThumb.node.focus();
    const { left, right, top, bottom } = sliderNode.getBoundingClientRect();
    const direction = this.direction;
    if (direction === "lr") {
      this.applyPosition({
        clientXY: e.clientX,
        activeThumbIdx: activeThumb.idx,
        start: left,
        end: right
      });
    } else if (direction === "rl") {
      this.applyPosition({
        clientXY: e.clientX,
        activeThumbIdx: activeThumb.idx,
        start: right,
        end: left
      });
    } else if (direction === "bt") {
      this.applyPosition({
        clientXY: e.clientY,
        activeThumbIdx: activeThumb.idx,
        start: bottom,
        end: top
      });
    } else if (direction === "tb") {
      this.applyPosition({
        clientXY: e.clientY,
        activeThumbIdx: activeThumb.idx,
        start: top,
        end: bottom
      });
    }
  };
  handlePointerDown = (e) => {
    if (e.button !== 0 || this.opts.disabled.current) return;
    const sliderNode = this.opts.ref.current;
    const closestThumb = this.#getClosestThumb(e);
    if (!closestThumb || !sliderNode) return;
    const target = e.composedPath()[0] ?? e.target;
    if (!isElementOrSVGElement(target) || !sliderNode.contains(target)) return;
    e.preventDefault();
    this.activeThumb = closestThumb;
    closestThumb.node.focus();
    this.isActive = true;
    this.handlePointerMove(e);
  };
  handlePointerUp = () => {
    if (this.opts.disabled.current) return;
    if (this.isActive) {
      this.opts.onValueCommit.current(run(() => this.opts.value.current));
    }
    this.isActive = false;
  };
  getAllThumbs = () => {
    const node = this.opts.ref.current;
    if (!node) return [];
    const thumbs = Array.from(node.querySelectorAll(sliderAttrs.selector("thumb")));
    return thumbs;
  };
  updateValue = (thumbValue, idx) => {
    const currValue = this.opts.value.current;
    if (!currValue.length) {
      this.opts.value.current.push(thumbValue);
      return;
    }
    const valueAtIndex = currValue[idx];
    if (valueAtIndex === thumbValue) return;
    const newValue = [...currValue];
    if (!isValidIndex(idx, newValue)) return;
    const direction = newValue[idx] > thumbValue ? -1 : 1;
    const swap = () => {
      const diffIndex = idx + direction;
      newValue[idx] = newValue[diffIndex];
      newValue[diffIndex] = thumbValue;
      const thumbs = this.getAllThumbs();
      if (!thumbs.length) return;
      thumbs[diffIndex]?.focus();
      this.activeThumb = { node: thumbs[diffIndex], idx: diffIndex };
    };
    if (this.opts.autoSort.current && (direction === -1 && thumbValue < newValue[idx - 1] || direction === 1 && thumbValue > newValue[idx + 1])) {
      swap();
      this.opts.value.current = newValue;
      return;
    }
    const steps = this.normalizedSteps;
    newValue[idx] = snapValueToCustomSteps(thumbValue, steps);
    this.opts.value.current = newValue;
  };
  #thumbsPropsArr = derived(() => {
    const currValue = this.opts.value.current;
    return Array.from({ length: currValue.length || 1 }, (_, i) => {
      const currThumb = run(() => this.currentThumbIdx);
      if (currThumb < currValue.length) {
        run(() => {
          this.currentThumbIdx = currThumb + 1;
        });
      }
      const thumbValue = currValue[i];
      const thumbPosition = this.getPositionFromValue(thumbValue ?? 0);
      const style = getThumbStyles(this.direction, thumbPosition);
      return {
        role: "slider",
        "aria-valuemin": this.opts.min.current,
        "aria-valuemax": this.opts.max.current,
        "aria-valuenow": thumbValue,
        "aria-disabled": boolToStr(this.opts.disabled.current),
        "aria-orientation": this.opts.orientation.current,
        "data-value": thumbValue,
        "data-orientation": this.opts.orientation.current,
        style,
        [sliderAttrs.thumb]: ""
      };
    });
  });
  get thumbsPropsArr() {
    return this.#thumbsPropsArr();
  }
  set thumbsPropsArr($$value) {
    return this.#thumbsPropsArr($$value);
  }
  #thumbsRenderArr = derived(() => {
    return this.thumbsPropsArr.map((_, i) => i);
  });
  get thumbsRenderArr() {
    return this.#thumbsRenderArr();
  }
  set thumbsRenderArr($$value) {
    return this.#thumbsRenderArr($$value);
  }
  #ticksPropsArr = derived(() => {
    const steps = this.normalizedSteps;
    const currValue = this.opts.value.current;
    return steps.map((tickValue, i) => {
      const tickPosition = this.getPositionFromValue(tickValue);
      const isFirst = i === 0;
      const isLast = i === steps.length - 1;
      const offsetPercentage = isFirst ? 0 : isLast ? -100 : -50;
      const style = getTickStyles(this.direction, tickPosition, offsetPercentage);
      const bounded = currValue.length === 1 ? tickValue <= currValue[0] : currValue[0] <= tickValue && tickValue <= currValue[currValue.length - 1];
      return {
        "data-disabled": boolToEmptyStrOrUndef(this.opts.disabled.current),
        "data-orientation": this.opts.orientation.current,
        "data-bounded": bounded ? "" : void 0,
        "data-value": tickValue,
        style,
        [sliderAttrs.tick]: ""
      };
    });
  });
  get ticksPropsArr() {
    return this.#ticksPropsArr();
  }
  set ticksPropsArr($$value) {
    return this.#ticksPropsArr($$value);
  }
  #ticksRenderArr = derived(() => {
    return this.ticksPropsArr.map((_, i) => i);
  });
  get ticksRenderArr() {
    return this.#ticksRenderArr();
  }
  set ticksRenderArr($$value) {
    return this.#ticksRenderArr($$value);
  }
  #tickItemsArr = derived(() => {
    return this.ticksPropsArr.map((tick, i) => ({ value: tick["data-value"], index: i }));
  });
  get tickItemsArr() {
    return this.#tickItemsArr();
  }
  set tickItemsArr($$value) {
    return this.#tickItemsArr($$value);
  }
  #thumbItemsArr = derived(() => {
    const currValue = this.opts.value.current;
    return currValue.map((value, index) => ({ value, index }));
  });
  get thumbItemsArr() {
    return this.#thumbItemsArr();
  }
  set thumbItemsArr($$value) {
    return this.#thumbItemsArr($$value);
  }
  #snippetProps = derived(() => ({
    ticks: this.ticksRenderArr,
    thumbs: this.thumbsRenderArr,
    tickItems: this.tickItemsArr,
    thumbItems: this.thumbItemsArr
  }));
  get snippetProps() {
    return this.#snippetProps();
  }
  set snippetProps($$value) {
    return this.#snippetProps($$value);
  }
}
class SliderRootState {
  static create(opts) {
    const { type, ...rest } = opts;
    const rootState = type === "single" ? new SliderSingleRootState(rest) : new SliderMultiRootState(rest);
    return SliderRootContext.set(rootState);
  }
}
const VALID_SLIDER_KEYS = [
  ARROW_LEFT,
  ARROW_RIGHT,
  ARROW_UP,
  ARROW_DOWN,
  HOME,
  END
];
class SliderRangeState {
  static create(opts) {
    return new SliderRangeState(opts, SliderRootContext.get());
  }
  opts;
  root;
  attachment;
  constructor(opts, root) {
    this.opts = opts;
    this.root = root;
    this.attachment = attachRef(opts.ref);
  }
  #rangeStyles = derived(() => {
    if (Array.isArray(this.root.opts.value.current)) {
      const min = this.root.opts.value.current.length > 1 ? this.root.getPositionFromValue(Math.min(...this.root.opts.value.current) ?? 0) : 0;
      const max = 100 - this.root.getPositionFromValue(Math.max(...this.root.opts.value.current) ?? 0);
      return {
        position: "absolute",
        ...getRangeStyles(this.root.direction, min, max)
      };
    } else {
      const trackPadding = this.root.opts.trackPadding?.current;
      const currentValue = this.root.opts.value.current;
      const maxValue = this.root.opts.max.current;
      const min = 0;
      const max = trackPadding !== void 0 && trackPadding > 0 && currentValue === maxValue ? 0 : (
        // 100% - 0% = full width
        100 - this.root.getPositionFromValue(currentValue)
      );
      return {
        position: "absolute",
        ...getRangeStyles(this.root.direction, min, max)
      };
    }
  });
  get rangeStyles() {
    return this.#rangeStyles();
  }
  set rangeStyles($$value) {
    return this.#rangeStyles($$value);
  }
  #props = derived(() => ({
    id: this.opts.id.current,
    "data-orientation": this.root.opts.orientation.current,
    "data-disabled": boolToEmptyStrOrUndef(this.root.opts.disabled.current),
    style: this.rangeStyles,
    [sliderAttrs.range]: "",
    ...this.attachment
  }));
  get props() {
    return this.#props();
  }
  set props($$value) {
    return this.#props($$value);
  }
}
class SliderThumbState {
  static create(opts) {
    return new SliderThumbState(opts, SliderRootContext.get());
  }
  opts;
  root;
  attachment;
  #isDisabled = derived(() => this.root.opts.disabled.current || this.opts.disabled.current);
  constructor(opts, root) {
    this.opts = opts;
    this.root = root;
    this.attachment = attachRef(opts.ref);
    this.onkeydown = this.onkeydown.bind(this);
  }
  #updateValue(newValue) {
    if (this.root.isMulti) {
      this.root.updateValue(newValue, this.opts.index.current);
    } else {
      this.root.updateValue(newValue);
    }
  }
  onkeydown(e) {
    if (this.#isDisabled()) return;
    const currNode = this.opts.ref.current;
    if (!currNode) return;
    const thumbs = this.root.getAllThumbs();
    if (!thumbs.length) return;
    const idx = thumbs.indexOf(currNode);
    if (this.root.isMulti) {
      this.root.currentThumbIdx = idx;
    }
    if (!VALID_SLIDER_KEYS.includes(e.key)) return;
    e.preventDefault();
    const min = this.root.opts.min.current;
    const max = this.root.opts.max.current;
    const value = this.root.opts.value.current;
    const thumbValue = Array.isArray(value) ? value[idx] : value;
    const orientation = this.root.opts.orientation.current;
    const direction = this.root.direction;
    const steps = this.root.normalizedSteps;
    switch (e.key) {
      case HOME:
        this.#updateValue(min);
        break;
      case END:
        this.#updateValue(max);
        break;
      case ARROW_LEFT:
        if (orientation !== "horizontal") break;
        if (e.metaKey) {
          const newValue = direction === "rl" ? max : min;
          this.#updateValue(newValue);
        } else {
          const stepDirection = direction === "rl" ? "next" : "prev";
          const newValue = getAdjacentStepValue(thumbValue, steps, stepDirection);
          this.#updateValue(newValue);
        }
        break;
      case ARROW_RIGHT:
        if (orientation !== "horizontal") break;
        if (e.metaKey) {
          const newValue = direction === "rl" ? min : max;
          this.#updateValue(newValue);
        } else {
          const stepDirection = direction === "rl" ? "prev" : "next";
          const newValue = getAdjacentStepValue(thumbValue, steps, stepDirection);
          this.#updateValue(newValue);
        }
        break;
      case ARROW_UP:
        if (e.metaKey) {
          const newValue = direction === "tb" ? min : max;
          this.#updateValue(newValue);
        } else {
          const stepDirection = direction === "tb" ? "prev" : "next";
          const newValue = getAdjacentStepValue(thumbValue, steps, stepDirection);
          this.#updateValue(newValue);
        }
        break;
      case ARROW_DOWN:
        if (e.metaKey) {
          const newValue = direction === "tb" ? max : min;
          this.#updateValue(newValue);
        } else {
          const stepDirection = direction === "tb" ? "next" : "prev";
          const newValue = getAdjacentStepValue(thumbValue, steps, stepDirection);
          this.#updateValue(newValue);
        }
        break;
    }
    this.root.opts.onValueCommit.current(this.root.opts.value.current);
  }
  #props = derived(() => ({
    ...this.root.thumbsPropsArr[this.opts.index.current],
    id: this.opts.id.current,
    onkeydown: this.onkeydown,
    "data-active": this.root.isThumbActive(this.opts.index.current) ? "" : void 0,
    "data-disabled": boolToEmptyStrOrUndef(this.opts.disabled.current || this.root.opts.disabled.current),
    tabindex: this.opts.disabled.current || this.root.opts.disabled.current ? -1 : 0,
    ...this.attachment
  }));
  get props() {
    return this.#props();
  }
  set props($$value) {
    return this.#props($$value);
  }
}
function Slider($$renderer, $$props) {
  $$renderer.component(($$renderer2) => {
    const uid = props_id($$renderer2);
    let {
      children,
      child,
      id = createId(uid),
      ref = null,
      value = void 0,
      type,
      onValueChange = noop,
      onValueCommit = noop,
      disabled = false,
      min: minProp,
      max: maxProp,
      step = 1,
      dir = "ltr",
      autoSort = true,
      orientation = "horizontal",
      thumbPositioning = "contain",
      trackPadding,
      $$slots,
      $$events,
      ...restProps
    } = $$props;
    const min = derived(() => {
      if (minProp !== void 0) return minProp;
      if (Array.isArray(step)) return Math.min(...step);
      return 0;
    });
    const max = derived(() => {
      if (maxProp !== void 0) return maxProp;
      if (Array.isArray(step)) return Math.max(...step);
      return 100;
    });
    function handleDefaultValue() {
      if (value !== void 0) return;
      if (type === "single") {
        return min();
      }
      return [];
    }
    handleDefaultValue();
    watch.pre(() => value, () => {
      handleDefaultValue();
    });
    const rootState = SliderRootState.create({
      id: boxWith(() => id),
      ref: boxWith(() => ref, (v) => ref = v),
      value: boxWith(() => value, (v) => {
        value = v;
        onValueChange(v);
      }),
      // @ts-expect-error - we know
      onValueCommit: boxWith(() => onValueCommit),
      disabled: boxWith(() => disabled),
      min: boxWith(() => min()),
      max: boxWith(() => max()),
      step: boxWith(() => step),
      dir: boxWith(() => dir),
      autoSort: boxWith(() => autoSort),
      orientation: boxWith(() => orientation),
      thumbPositioning: boxWith(() => thumbPositioning),
      type,
      trackPadding: boxWith(() => trackPadding)
    });
    const mergedProps = derived(() => mergeProps(restProps, rootState.props));
    if (child) {
      $$renderer2.push("<!--[0-->");
      child($$renderer2, { props: mergedProps(), ...rootState.snippetProps });
      $$renderer2.push(`<!---->`);
    } else {
      $$renderer2.push("<!--[-1-->");
      $$renderer2.push(`<span${attributes({ ...mergedProps() })}>`);
      children?.($$renderer2, rootState.snippetProps);
      $$renderer2.push(`<!----></span>`);
    }
    $$renderer2.push(`<!--]-->`);
    bind_props($$props, { ref, value });
  });
}
function Slider_range($$renderer, $$props) {
  $$renderer.component(($$renderer2) => {
    const uid = props_id($$renderer2);
    let {
      children,
      child,
      ref = null,
      id = createId(uid),
      $$slots,
      $$events,
      ...restProps
    } = $$props;
    const rangeState = SliderRangeState.create({
      id: boxWith(() => id),
      ref: boxWith(() => ref, (v) => ref = v)
    });
    const mergedProps = derived(() => mergeProps(restProps, rangeState.props));
    if (child) {
      $$renderer2.push("<!--[0-->");
      child($$renderer2, { props: mergedProps() });
      $$renderer2.push(`<!---->`);
    } else {
      $$renderer2.push("<!--[-1-->");
      $$renderer2.push(`<span${attributes({ ...mergedProps() })}>`);
      children?.($$renderer2);
      $$renderer2.push(`<!----></span>`);
    }
    $$renderer2.push(`<!--]-->`);
    bind_props($$props, { ref });
  });
}
function Slider_thumb($$renderer, $$props) {
  $$renderer.component(($$renderer2) => {
    const uid = props_id($$renderer2);
    let {
      children,
      child,
      ref = null,
      id = createId(uid),
      index,
      disabled = false,
      $$slots,
      $$events,
      ...restProps
    } = $$props;
    const thumbState = SliderThumbState.create({
      id: boxWith(() => id),
      ref: boxWith(() => ref, (v) => ref = v),
      index: boxWith(() => index),
      disabled: boxWith(() => disabled)
    });
    const mergedProps = derived(() => mergeProps(restProps, thumbState.props));
    if (child) {
      $$renderer2.push("<!--[0-->");
      child($$renderer2, {
        active: thumbState.root.isThumbActive(thumbState.opts.index.current),
        props: mergedProps()
      });
      $$renderer2.push(`<!---->`);
    } else {
      $$renderer2.push("<!--[-1-->");
      $$renderer2.push(`<span${attributes({ ...mergedProps() })}>`);
      children?.($$renderer2, {
        active: thumbState.root.isThumbActive(thumbState.opts.index.current)
      });
      $$renderer2.push(`<!----></span>`);
    }
    $$renderer2.push(`<!--]-->`);
    bind_props($$props, { ref });
  });
}
const RESULT_SHEET_FONT_OPTIONS = [
  { value: "Arial, Helvetica, sans-serif", label: "Arial" },
  { value: "Inter, Arial, sans-serif", label: "Inter" },
  { value: "Georgia, serif", label: "Georgia" },
  { value: "Times New Roman, serif", label: "Times New Roman" },
  { value: "Verdana, sans-serif", label: "Verdana" }
];
const RESULT_SHEET_TEXT_ALIGN_OPTIONS = [
  { value: "left", label: "Left" },
  { value: "center", label: "Center" },
  { value: "right", label: "Right" },
  { value: "justify", label: "Justify" }
];
const RESULT_SHEET_BORDER_STYLE_OPTIONS = [
  { value: "solid", label: "Solid" },
  { value: "dashed", label: "Dashed" },
  { value: "dotted", label: "Dotted" }
];
const TEXT_PARTS_BY_TYPE = {
  header: [
    { value: "title", label: "Title" },
    { value: "subtitle", label: "Subtitle" }
  ],
  identity: [
    { value: "label", label: "Labels" },
    { value: "value", label: "Values" }
  ],
  exam_details: [{ value: "body", label: "Details" }],
  field: [
    { value: "label", label: "Label" },
    { value: "value", label: "Value" }
  ],
  score_table: [
    { value: "title", label: "Title" },
    { value: "heading", label: "Column headings" },
    { value: "body", label: "Rows" }
  ],
  data_table: [
    { value: "title", label: "Title" },
    { value: "heading", label: "Column headers" },
    { value: "body", label: "Data rows" }
  ],
  kpi_grid: [
    { value: "label", label: "Metric labels" },
    { value: "value", label: "Metric values" }
  ],
  overall: [
    { value: "label", label: "Label" },
    { value: "value", label: "Value" }
  ],
  recommendation: [
    { value: "heading", label: "Heading" },
    { value: "body", label: "Program" },
    { value: "note", label: "Counselor note" }
  ],
  signature: [
    { value: "name", label: "Name" },
    { value: "role", label: "Role" }
  ],
  note: [{ value: "body", label: "Note" }],
  footer: [{ value: "body", label: "Footer text" }],
  text: [{ value: "body", label: "Text" }]
};
function textPartOptions(type = "text") {
  return TEXT_PARTS_BY_TYPE[type] ?? [];
}
function typographyDefaults(type = "text") {
  const defaults = {
    font_family: "Arial, Helvetica, sans-serif",
    font_size_pt: 10,
    font_weight: 400,
    text_color: "#0f172a",
    text_align: "left",
    line_height: 1.2,
    letter_spacing_px: 0
  };
  return {
    ...defaults,
    ...{
      header: { font_size_pt: 20, font_weight: 700, text_align: "center" },
      exam_details: { text_align: "center" },
      overall: { font_size_pt: 12, font_weight: 700 },
      signature: { text_align: "center" },
      footer: { font_size_pt: 8.5, text_color: "#64748b", text_align: "center" },
      field: { font_size_pt: 10.5 },
      score_table: { font_size_pt: 9.5 },
      recommendation: { font_size_pt: 10.5 },
      note: { font_size_pt: 9.5 }
    }[type] ?? {}
  };
}
function textPartDefaults(type = "text", part = "body") {
  const base = typographyDefaults(type);
  const parts = {
    header: {
      title: { ...base },
      subtitle: { ...base, font_size_pt: 12, font_weight: 500 }
    },
    identity: {
      label: { ...base, font_size_pt: 8.5, font_weight: 700, text_color: "#334155" },
      value: { ...base, font_size_pt: 9.5 }
    },
    field: {
      label: { ...base, font_size_pt: 9, font_weight: 700 },
      value: { ...base, font_size_pt: 10 }
    },
    score_table: {
      title: { ...base, font_size_pt: 12, font_weight: 700 },
      heading: { ...base, font_size_pt: 8.5, font_weight: 700, text_color: "#334155" },
      body: { ...base, font_size_pt: 9 }
    },
    data_table: {
      title: { ...base, font_size_pt: 12, font_weight: 700 },
      heading: { ...base, font_size_pt: 8.5, font_weight: 700, text_color: "#334155" },
      body: { ...base, font_size_pt: 9 }
    },
    kpi_grid: {
      label: { ...base, font_size_pt: 8.5, font_weight: 700, text_color: "#64748b" },
      value: { ...base, font_size_pt: 14, font_weight: 800 }
    },
    overall: {
      label: { ...base, font_size_pt: 11, font_weight: 700 },
      value: { ...base, font_size_pt: 12, font_weight: 700 }
    },
    recommendation: {
      heading: { ...base, font_size_pt: 10, font_weight: 700 },
      body: { ...base, font_size_pt: 11, font_weight: 600 },
      note: { ...base, font_size_pt: 9.5, text_color: "#334155" }
    },
    signature: {
      name: { ...base, font_size_pt: 10, font_weight: 700, text_align: "center" },
      role: { ...base, font_size_pt: 8.5, text_color: "#64748b", text_align: "center" }
    },
    note: { body: { ...base, font_size_pt: 9.5 } },
    footer: { body: { ...base, font_size_pt: 8.5, text_color: "#64748b", text_align: "center" } },
    text: { body: { ...base } }
  };
  return parts[type]?.[part] ?? base;
}
function appearanceDefaults(type = "text") {
  return {
    background_color: ["identity", "score_table", "overall"].includes(type) ? "#f8fafc" : type === "recommendation" ? "#ecfdf5" : "transparent",
    border_color: type === "recommendation" ? "#a7f3d0" : "#cbd5e1",
    border_width_px: ["identity", "score_table", "overall", "recommendation", "data_table", "kpi_grid"].includes(type) ? 1 : 0,
    border_style: "solid",
    border_radius_mm: type === "recommendation" ? 1 : 0
  };
}
function spacingDefaults(type = "text") {
  return {
    padding_mm: type === "header" ? 2.5 : ["identity", "score_table", "recommendation"].includes(type) ? 3 : ["divider", "spacer"].includes(type) ? 0 : 2
  };
}
function safeColor(value, fallback = "#0f172a") {
  const color = String(value ?? "").trim();
  return color === "transparent" || /^#[0-9a-f]{3,8}$/i.test(color) ? color : fallback;
}
function normalizeTypography(raw = {}, fallback = typographyDefaults("text")) {
  const typography = { ...fallback, ...raw };
  const fontFamilies = new Set(RESULT_SHEET_FONT_OPTIONS.map((font) => font.value));
  typography.font_family = fontFamilies.has(typography.font_family) ? typography.font_family : fallback.font_family;
  typography.font_size_pt = clamp(Number(typography.font_size_pt), 6, 48, fallback.font_size_pt);
  typography.font_weight = [400, 500, 600, 700, 800].includes(Number(typography.font_weight)) ? Number(typography.font_weight) : fallback.font_weight;
  typography.text_color = safeColor(typography.text_color, fallback.text_color);
  typography.text_align = RESULT_SHEET_TEXT_ALIGN_OPTIONS.some((option) => option.value === typography.text_align) ? typography.text_align : fallback.text_align;
  typography.line_height = clamp(Number(typography.line_height), 0.8, 2.4, fallback.line_height);
  typography.letter_spacing_px = clamp(Number(typography.letter_spacing_px), -2, 8, fallback.letter_spacing_px);
  return typography;
}
function normalizeComponentProps(type, props = {}) {
  const typography = normalizeTypography({
    ...typographyDefaults(type),
    ...props.typography ?? {}
  }, typographyDefaults(type));
  const appearance = {
    ...appearanceDefaults(type),
    ...props.appearance ?? {}
  };
  const spacing = {
    ...spacingDefaults(type),
    ...props.spacing ?? {}
  };
  if (props.font_size !== void 0 && props.typography?.font_size_pt === void 0) typography.font_size_pt = props.font_size;
  if (props.bold !== void 0 && props.typography?.font_weight === void 0) typography.font_weight = props.bold ? 700 : 400;
  if (props.background !== void 0 && props.appearance?.background_color === void 0) appearance.background_color = props.background;
  if (props.border && props.appearance?.border_width_px === void 0) appearance.border_width_px = 1;
  appearance.background_color = safeColor(appearance.background_color, "transparent");
  appearance.border_color = safeColor(appearance.border_color, "#cbd5e1");
  appearance.border_width_px = clamp(Number(appearance.border_width_px), 0, 4, 0);
  appearance.border_style = RESULT_SHEET_BORDER_STYLE_OPTIONS.some((option) => option.value === appearance.border_style) ? appearance.border_style : "solid";
  appearance.border_radius_mm = clamp(Number(appearance.border_radius_mm), 0, 8, 0);
  spacing.padding_mm = clamp(Number(spacing.padding_mm), 0, 12, 2);
  const textStyles = Object.fromEntries(textPartOptions(type).map((part) => [
    part.value,
    normalizeTypography({ ...textPartDefaults(type, part.value), ...props.text_styles?.[part.value] ?? {} }, textPartDefaults(type, part.value))
  ]));
  return { ...props, typography, text_styles: textStyles, appearance, spacing };
}
function componentStyle(component, props = component.props ?? {}, pageMarginMm = 0) {
  const normalized = normalizeComponentProps(component.type, props);
  const typography = normalized.typography;
  const appearance = normalized.appearance;
  const spacing = normalized.spacing;
  const border = appearance.border_width_px > 0 ? `${appearance.border_width_px}px ${appearance.border_style} ${appearance.border_color}` : "0 solid transparent";
  return [
    "position:absolute",
    "box-sizing:border-box",
    "min-width:0",
    "overflow-wrap:anywhere",
    `left:calc(${pageMarginMm}mm + ${component.x}mm)`,
    `top:calc(${pageMarginMm}mm + ${component.y}mm)`,
    `width:${component.w}mm`,
    `min-height:${component.h}mm`,
    `padding:${spacing.padding_mm}mm`,
    `font-family:${typography.font_family}`,
    `font-size:${typography.font_size_pt}pt`,
    `font-weight:${typography.font_weight}`,
    `color:${typography.text_color}`,
    `text-align:${typography.text_align}`,
    `line-height:${typography.line_height}`,
    `letter-spacing:${typography.letter_spacing_px}px`,
    `background-color:${appearance.background_color}`,
    `border:${border}`,
    `border-radius:${appearance.border_radius_mm}mm`
  ].join(";") + ";";
}
function inlineTypographyStyle(typography = {}) {
  const normalized = normalizeTypography(typography, typographyDefaults("text"));
  return `font-family:${normalized.font_family};font-size:${normalized.font_size_pt}pt;font-weight:${normalized.font_weight};color:${normalized.text_color};text-align:${normalized.text_align};line-height:${normalized.line_height};letter-spacing:${normalized.letter_spacing_px}px;`;
}
function sampleValue(binding, fallback = "Sample value") {
  return {
    institution_name: "NEXIAM",
    institution_exam_name: "ICAT Examination",
    institution_exam_acronym: "ICAT",
    applicant_reference: "2026-00042",
    family_name: "DOE",
    first_name: "JUAN",
    middle_name: "DELA CRUZ",
    suffix: "",
    sex: "M",
    gwa: "92.40",
    course_applied_code: "BSCS",
    strand: "STEM",
    exam_date: "22 Aug 2026",
    exam_time: "08:00",
    room_name: "Room 1",
    overall_pct: "88.40%",
    recommended_course: "BS Computer Science",
    counselor_name: "Guidance Counselor",
    counselor_comments: "Applicant meets the recommended program criteria."
  }[binding] ?? fallback;
}
function replaceMacros(value, fallback = "Sample value") {
  return String(value ?? "").replace(/\{\{\s*([a-zA-Z0-9_]+)\s*\}\}/g, (_, binding) => sampleValue(binding, fallback));
}
function clamp(value, min, max, fallback) {
  return Number.isFinite(value) ? Math.min(max, Math.max(min, value)) : fallback;
}
const COMPONENT_TEMPLATE_ALLOWED_TAGS = /* @__PURE__ */ new Set(["section", "div", "span", "p", "h1", "h2", "h3", "strong", "em", "small", "br", "table", "thead", "tbody", "tr", "th", "td"]);
function sanitizeComponentTemplate(html2) {
  let source = String(html2 ?? "").replace(/<\s*(script|iframe|object|embed|style|link)\b[^>]*>[\s\S]*?<\/\s*\1\s*>/gi, "").replace(/\son[a-z]+\s*=\s*("[^"]*"|'[^']*'|[^\s>]+)/gi, "").replace(/javascript\s*:/gi, "");
  source = source.replace(/<\/?\s*([a-z0-9-]+)([^>]*)>/gi, (full, tag, attrs) => {
    if (!COMPONENT_TEMPLATE_ALLOWED_TAGS.has(String(tag).toLowerCase())) return "";
    return `<${full.startsWith("</") ? "/" : ""}${tag}${attrs}>`;
  });
  return source;
}
function renderComponentTemplate(template, component, props = {}) {
  const normalized = normalizeComponentProps(component.type, props);
  const slots = {
    title: replaceMacros(normalized.title ?? normalized.text ?? "{{institution_name}}"),
    subtitle: replaceMacros(normalized.subtitle ?? "{{institution_exam_name}}"),
    label: replaceMacros(normalized.label ?? component.type),
    value: replaceMacros(normalized.binding ? `{{${normalized.binding}}}` : "Sample value"),
    text: replaceMacros(normalized.text ?? normalized.title ?? "Sample value")
  };
  let html2 = sanitizeComponentTemplate(template);
  html2 = html2.replace(/\{\{slot:([a-z_]+)\}\}/gi, (_, name) => escapeHtml(slots[name] ?? "Sample value"));
  return html2;
}
function escapeHtml(value) {
  return String(value ?? "").replace(/[&<>"']/g, (character) => ({ "&": "&amp;", "<": "&lt;", ">": "&gt;", '"': "&quot;", "'": "&#039;" })[character]);
}
function normalizeRect(rect) {
  const x = rect.width < 0 ? rect.x + rect.width : rect.x;
  const y = rect.height < 0 ? rect.y + rect.height : rect.y;
  const width = Math.abs(rect.width);
  const height = Math.abs(rect.height);
  return { x, y, width, height };
}
function rectIntersects(a, b) {
  const na = normalizeRect(a);
  const nb = normalizeRect(b);
  return na.x < nb.x + nb.width && na.x + na.width > nb.x && na.y < nb.y + nb.height && na.y + na.height > nb.y;
}
function rectContains(container, inner) {
  const nc = normalizeRect(container);
  const ni = normalizeRect(inner);
  return ni.x >= nc.x && ni.y >= nc.y && ni.x + ni.width <= nc.x + nc.width && ni.y + ni.height <= nc.y + nc.height;
}
function defaultClone(value) {
  if (typeof structuredClone === "function") {
    try {
      return structuredClone(value);
    } catch {
    }
  }
  return JSON.parse(JSON.stringify(value));
}
function defaultEqual(a, b) {
  if (a === b) return true;
  if (a === null || b === null || typeof a !== "object" || typeof b !== "object") {
    return false;
  }
  return JSON.stringify(a) === JSON.stringify(b);
}
class StudioHistory {
  maxDepth;
  coalesceWindowMs;
  cloneFn;
  equalityFn;
  current;
  cleanBaseline;
  past = [];
  future = [];
  lastCoalesceKey;
  lastCoalesceAt = 0;
  activeGesture = null;
  constructor(initial, options = {}) {
    this.maxDepth = Math.max(1, Math.floor(options.maxDepth ?? 100));
    this.coalesceWindowMs = Math.max(0, options.coalesceWindowMs ?? 400);
    this.cloneFn = options.cloneFn ?? defaultClone;
    this.equalityFn = options.equalityFn ?? defaultEqual;
    this.current = this.cloneFn(initial);
    this.cleanBaseline = this.cloneFn(initial);
  }
  get present() {
    return this.cloneFn(this.current);
  }
  getPresent() {
    return this.cloneFn(this.current);
  }
  resetCoalescing() {
    this.lastCoalesceKey = void 0;
    this.lastCoalesceAt = 0;
  }
  /**
   * Records a new state in the history stack.
   * If coalesceKey matches within coalesceWindowMs, updates present without adding a new history entry.
   */
  record(next, label, options = {}) {
    const nextState = this.cloneFn(next);
    if (this.equalityFn(this.current, nextState)) {
      return false;
    }
    const now = Date.now();
    const windowMs = options.windowMs ?? this.coalesceWindowMs;
    const canCoalesce = Boolean(
      options.coalesceKey && options.coalesceKey === this.lastCoalesceKey && now - this.lastCoalesceAt <= windowMs && this.past.length > 0
    );
    if (!canCoalesce) {
      this.past.push({
        state: this.cloneFn(this.current),
        label,
        timestamp: now,
        coalesceKey: options.coalesceKey
      });
      if (this.past.length > this.maxDepth) {
        this.past = this.past.slice(-this.maxDepth);
      }
    } else {
      const lastEntry = this.past[this.past.length - 1];
      this.past[this.past.length - 1] = {
        ...lastEntry,
        label
      };
    }
    this.current = nextState;
    this.future = [];
    this.lastCoalesceKey = options.coalesceKey;
    this.lastCoalesceAt = now;
    return true;
  }
  /**
   * Convenience alias matching push(next, coalesceKey, windowMs, label).
   */
  push(next, coalesceKey, windowMs, label = "Change") {
    return this.record(next, label, { coalesceKey, windowMs });
  }
  /**
   * Begins a continuous gesture transaction (e.g. pointer drag, resize, marquee).
   * Intermediate updates can be applied via updateGesture() and reverted cleanly with cancelGesture().
   */
  beginGesture(label, coalesceKey) {
    this.activeGesture = {
      label,
      coalesceKey,
      initialState: this.cloneFn(this.current),
      isCommitted: false,
      isCancelled: false
    };
  }
  /**
   * Updates state during an active gesture without recording history entries.
   */
  updateGesture(next) {
    this.current = this.cloneFn(next);
  }
  /**
   * Commits the active gesture transaction. If state changed from gesture start,
   * records a single history entry with the gesture label.
   */
  commitGesture() {
    if (!this.activeGesture || this.activeGesture.isCancelled) {
      this.activeGesture = null;
      return false;
    }
    const { label, coalesceKey, initialState } = this.activeGesture;
    this.activeGesture = null;
    if (this.equalityFn(initialState, this.current)) {
      return false;
    }
    const now = Date.now();
    this.past.push({
      state: initialState,
      label,
      timestamp: now,
      coalesceKey
    });
    if (this.past.length > this.maxDepth) {
      this.past = this.past.slice(-this.maxDepth);
    }
    this.future = [];
    this.lastCoalesceKey = coalesceKey;
    this.lastCoalesceAt = now;
    return true;
  }
  /**
   * Cancels the active gesture and restores state to the initial gesture state.
   */
  cancelGesture() {
    if (!this.activeGesture) {
      return this.present;
    }
    this.current = this.cloneFn(this.activeGesture.initialState);
    this.activeGesture = null;
    return this.present;
  }
  isInGesture() {
    return this.activeGesture !== null;
  }
  getGestureTransaction() {
    return this.activeGesture ? { ...this.activeGesture } : null;
  }
  /**
   * Undoes the last recorded history step.
   */
  undo() {
    if (this.past.length === 0) {
      return null;
    }
    const entry = this.past.pop();
    this.future.unshift({
      state: this.cloneFn(this.current),
      label: entry.label,
      timestamp: Date.now()
    });
    this.current = this.cloneFn(entry.state);
    this.resetCoalescing();
    return this.present;
  }
  /**
   * Redoes the next available future step.
   */
  redo() {
    if (this.future.length === 0) {
      return null;
    }
    const entry = this.future.shift();
    this.past.push({
      state: this.cloneFn(this.current),
      label: entry.label,
      timestamp: Date.now()
    });
    if (this.past.length > this.maxDepth) {
      this.past = this.past.slice(-this.maxDepth);
    }
    this.current = this.cloneFn(entry.state);
    this.resetCoalescing();
    return this.present;
  }
  canUndo() {
    return this.past.length > 0;
  }
  canRedo() {
    return this.future.length > 0;
  }
  /**
   * Resets history completely and replaces current present with state.
   * Updates clean baseline for isDirty.
   */
  reset(state) {
    this.current = this.cloneFn(state);
    this.cleanBaseline = this.cloneFn(state);
    this.past = [];
    this.future = [];
    this.activeGesture = null;
    this.resetCoalescing();
  }
  /**
   * Clears past and future undo stacks while preserving current present.
   */
  clear() {
    this.past = [];
    this.future = [];
    this.activeGesture = null;
    this.resetCoalescing();
  }
  /**
   * Marks current present as clean baseline for isDirty check.
   */
  markClean() {
    this.cleanBaseline = this.cloneFn(this.current);
  }
  /**
   * Evaluates if current present differs from clean baseline or provided initial state.
   */
  isDirty(initial) {
    const baseline = initial !== void 0 ? initial : this.cleanBaseline;
    return !this.equalityFn(this.current, baseline);
  }
  /**
   * Returns labeled status summary for editor toolbars.
   */
  status() {
    const undoEntry = this.past[this.past.length - 1];
    const redoEntry = this.future[0];
    return {
      canUndo: this.past.length > 0,
      canRedo: this.future.length > 0,
      undoLabel: undoEntry ? undoEntry.label : null,
      redoLabel: redoEntry ? redoEntry.label : null,
      pastCount: this.past.length,
      futureCount: this.future.length,
      isDirty: this.isDirty()
    };
  }
}
function createStudioHistory(initial, options) {
  const normalizedOptions = { maxDepth: options };
  return new StudioHistory(initial, normalizedOptions);
}
class StudioSelection {
  selectedIds;
  primaryId;
  constructor(initialIds = [], initialPrimary = null) {
    this.selectedIds = new Set(initialIds);
    if (initialPrimary && this.selectedIds.has(initialPrimary)) {
      this.primaryId = initialPrimary;
    } else {
      this.primaryId = initialIds.length > 0 ? initialIds[initialIds.length - 1] : null;
    }
  }
  get count() {
    return this.selectedIds.size;
  }
  getCount() {
    return this.selectedIds.size;
  }
  isEmpty() {
    return this.selectedIds.size === 0;
  }
  hasSingle() {
    return this.selectedIds.size === 1;
  }
  hasMultiple() {
    return this.selectedIds.size > 1;
  }
  isSelected(id) {
    return this.selectedIds.has(id);
  }
  getPrimaryId() {
    return this.primaryId;
  }
  setPrimaryId(id) {
    if (id === null) {
      this.primaryId = null;
      return true;
    }
    if (this.selectedIds.has(id)) {
      this.primaryId = id;
      return true;
    }
    return false;
  }
  getSelection() {
    return Array.from(this.selectedIds);
  }
  getSelectedIds() {
    return new Set(this.selectedIds);
  }
  getState() {
    return {
      selectedIds: new Set(this.selectedIds),
      primaryId: this.primaryId
    };
  }
  /**
   * Selects a single item exclusively and sets it as primary.
   */
  select(id) {
    this.selectedIds.clear();
    this.selectedIds.add(id);
    this.primaryId = id;
    return this;
  }
  /**
   * Toggles an item in the selection.
   * If added, becomes the primary ID.
   * If removed and it was primary, sets primary to the last remaining item or null.
   */
  toggle(id) {
    if (this.selectedIds.has(id)) {
      this.selectedIds.delete(id);
      if (this.primaryId === id) {
        const remaining = Array.from(this.selectedIds);
        this.primaryId = remaining.length > 0 ? remaining[remaining.length - 1] : null;
      }
    } else {
      this.selectedIds.add(id);
      this.primaryId = id;
    }
    return this;
  }
  /**
   * Adds one or more IDs to the selection without clearing existing items.
   */
  add(idOrIds) {
    const ids = Array.isArray(idOrIds) ? idOrIds : [idOrIds];
    for (const id of ids) {
      this.selectedIds.add(id);
      this.primaryId = id;
    }
    return this;
  }
  /**
   * Removes one or more IDs from the selection.
   */
  remove(idOrIds) {
    const ids = Array.isArray(idOrIds) ? idOrIds : [idOrIds];
    let removedPrimary = false;
    for (const id of ids) {
      if (this.selectedIds.delete(id)) {
        if (this.primaryId === id) {
          removedPrimary = true;
        }
      }
    }
    if (removedPrimary) {
      const remaining = Array.from(this.selectedIds);
      this.primaryId = remaining.length > 0 ? remaining[remaining.length - 1] : null;
    }
    return this;
  }
  /**
   * Sets exact selection list and optional primary item.
   */
  setSelection(ids, primaryId) {
    this.selectedIds = new Set(ids);
    if (primaryId !== void 0) {
      if (primaryId !== null && this.selectedIds.has(primaryId)) {
        this.primaryId = primaryId;
      } else {
        this.primaryId = null;
      }
    } else {
      const arr = Array.from(this.selectedIds);
      this.primaryId = arr.length > 0 ? arr[arr.length - 1] : null;
    }
    return this;
  }
  /**
   * Selects all given IDs.
   */
  selectAll(allIds) {
    this.selectedIds = new Set(allIds);
    if (this.primaryId === null || !this.selectedIds.has(this.primaryId)) {
      this.primaryId = allIds.length > 0 ? allIds[0] : null;
    }
    return this;
  }
  /**
   * Clears selection completely.
   */
  clear() {
    this.selectedIds.clear();
    this.primaryId = null;
    return this;
  }
  /**
   * Queries which items intersect or are contained within a marquee box.
   */
  queryMarquee(items, marquee, mode = "intersect") {
    const matchingIds = [];
    for (const item of items) {
      const matches = mode === "contain" ? rectContains(marquee, item) : rectIntersects(marquee, item);
      if (matches) {
        matchingIds.push(item.id);
      }
    }
    return matchingIds;
  }
  /**
   * Performs marquee selection over items.
   * If additive is true (e.g. Shift held), adds to existing selection; otherwise replaces.
   */
  selectMarquee(items, marquee, additive = false, mode = "intersect") {
    const matchingIds = this.queryMarquee(items, marquee, mode);
    if (additive) {
      for (const id of matchingIds) {
        this.selectedIds.add(id);
      }
      if (matchingIds.length > 0) {
        this.primaryId = matchingIds[matchingIds.length - 1];
      }
    } else {
      this.setSelection(matchingIds);
    }
    return matchingIds;
  }
  toState() {
    return {
      selectedIds: Array.from(this.selectedIds),
      primaryId: this.primaryId
    };
  }
  fromState(state) {
    return this.setSelection(state.selectedIds, state.primaryId);
  }
  clone() {
    return new StudioSelection(Array.from(this.selectedIds), this.primaryId);
  }
}
function createStudioSelection(initialIds, initialPrimary) {
  return new StudioSelection(initialIds, initialPrimary);
}
function NumericControl($$renderer, $$props) {
  $$renderer.component(($$renderer2) => {
    if (typeof window !== "undefined" && typeof window.ResizeObserver === "undefined") {
      globalThis.ResizeObserver = class {
        observe() {
        }
        unobserve() {
        }
        disconnect() {
        }
      };
    }
    const uid = useId();
    let {
      value = 0,
      label = "",
      min = 0,
      max = 100,
      step = 1,
      unit = "",
      disabled = false,
      id = void 0,
      name = void 0,
      class: className = "",
      oninput = void 0,
      oncommit = void 0,
      $$slots,
      $$events,
      ...restProps
    } = $$props;
    const controlId = derived(() => id || `numeric-control-${uid}`);
    const effectiveMin = derived(() => Number.isFinite(min) ? min : 0);
    const effectiveMax = derived(() => Number.isFinite(max) ? Math.max(effectiveMin(), max) : 100);
    const effectiveStep = derived(() => Number.isFinite(step) && step > 0 ? step : 1);
    function getStepPrecision(stepVal) {
      if (!Number.isFinite(stepVal) || stepVal <= 0) return 0;
      const str = stepVal.toString();
      if (str.includes(".")) {
        return str.split(".")[1].length;
      }
      const expMatch = str.match(/e-(\d+)/);
      if (expMatch) {
        return parseInt(expMatch[1], 10);
      }
      return 0;
    }
    function roundToStep(val, stepVal, minVal = 0) {
      if (!Number.isFinite(val)) return minVal;
      if (!Number.isFinite(stepVal) || stepVal <= 0) return val;
      const precision = getStepPrecision(stepVal);
      const offset = val - minVal;
      const numSteps = Math.round(offset / stepVal);
      const snapped = minVal + numSteps * stepVal;
      return Number(snapped.toFixed(precision));
    }
    function formatNumber(val, stepVal) {
      if (!Number.isFinite(val)) return "";
      const precision = getStepPrecision(stepVal);
      if (precision > 0) {
        return Number(val.toFixed(precision)).toString();
      }
      return val.toString();
    }
    const clampedValue = derived(() => Math.max(effectiveMin(), Math.min(effectiveMax(), Number.isFinite(value) ? value : effectiveMin())));
    let inputValue = "";
    const isInvalid = derived(() => {
      const trimmed = inputValue.trim();
      if (trimmed === "" || trimmed === "-" || trimmed === ".") return true;
      const num = Number(trimmed);
      if (Number.isNaN(num)) return true;
      if (num < effectiveMin() || num > effectiveMax()) return true;
      return false;
    });
    function handleSliderChange(newVal) {
      if (disabled) return;
      const valNum = Array.isArray(newVal) ? newVal[0] : newVal;
      const snapped = roundToStep(valNum, effectiveStep(), effectiveMin());
      const clamped = Math.max(effectiveMin(), Math.min(effectiveMax(), snapped));
      value = clamped;
      inputValue = formatNumber(clamped, effectiveStep());
      oninput?.(clamped);
    }
    function handleSliderCommit(newVal) {
      if (disabled) return;
      const valNum = Array.isArray(newVal) ? newVal[0] : newVal;
      const snapped = roundToStep(valNum, effectiveStep(), effectiveMin());
      const clamped = Math.max(effectiveMin(), Math.min(effectiveMax(), snapped));
      value = clamped;
      inputValue = formatNumber(clamped, effectiveStep());
      oncommit?.(clamped);
    }
    $$renderer2.push(`<div${attributes({
      class: clsx(cn("group/numeric-control flex flex-col gap-1.5 text-xs text-studio-panel-foreground", disabled && "opacity-50 pointer-events-none cursor-not-allowed select-none", className)),
      ...restProps
    })}><div class="flex items-center justify-between gap-2">`);
    if (label) {
      $$renderer2.push("<!--[0-->");
      $$renderer2.push(`<label${attr("for", controlId())} class="font-medium text-studio-panel-foreground/80 select-none truncate text-xs cursor-pointer"${attr("title", label)}>${escape_html(label)}</label>`);
    } else {
      $$renderer2.push("<!--[-1-->");
    }
    $$renderer2.push(`<!--]--> <div class="relative ml-auto flex items-center"><input${attr("id", controlId())}${attr("name", name)} type="text" inputmode="decimal" role="spinbutton"${attr("disabled", disabled, true)}${attr("aria-label", label || "Numeric value")}${attr("aria-valuemin", effectiveMin())}${attr("aria-valuemax", effectiveMax())}${attr("aria-valuenow", clampedValue())}${attr("aria-invalid", isInvalid())}${attr("value", inputValue)}${attr_class(clsx(cn("h-7 w-20 rounded-md border border-studio-border bg-studio-panel px-2 font-mono text-xs text-right text-studio-panel-foreground shadow-xs transition-colors motion-reduce:transition-none outline-hidden focus-visible:border-studio-focus focus-visible:ring-2 focus-visible:ring-studio-focus/40", unit ? "pr-6" : "pr-2", isInvalid() && "border-destructive text-destructive focus-visible:border-destructive focus-visible:ring-destructive/30", disabled && "cursor-not-allowed opacity-50")))}/> `);
    if (unit) {
      $$renderer2.push("<!--[0-->");
      $$renderer2.push(`<span class="pointer-events-none absolute right-2 select-none text-[10px] font-medium text-studio-panel-muted-foreground" aria-hidden="true">${escape_html(unit)}</span>`);
    } else {
      $$renderer2.push("<!--[-1-->");
    }
    $$renderer2.push(`<!--]--></div></div> <div class="flex items-center gap-2 pt-0.5">`);
    {
      let children = function($$renderer3) {
        $$renderer3.push(`<span class="relative h-1.5 w-full grow overflow-hidden rounded-full bg-studio-panel-muted">`);
        if (Slider_range) {
          $$renderer3.push("<!--[-->");
          Slider_range($$renderer3, {
            class: "absolute h-full bg-studio-focus transition-all motion-reduce:transition-none"
          });
          $$renderer3.push("<!--]-->");
        } else {
          $$renderer3.push("<!--[!-->");
          $$renderer3.push("<!--]-->");
        }
        $$renderer3.push(`</span> `);
        if (Slider_thumb) {
          $$renderer3.push("<!--[-->");
          Slider_thumb($$renderer3, {
            index: 0,
            class: "block size-3.5 rounded-full border border-studio-focus/50 bg-studio-panel shadow-xs ring-offset-studio-panel transition-transform motion-reduce:transition-none hover:scale-110 focus-visible:outline-hidden focus-visible:ring-2 focus-visible:ring-studio-focus focus-visible:ring-offset-1 disabled:pointer-events-none disabled:opacity-50",
            "aria-label": label ? `${label} thumb` : "Slider thumb"
          });
          $$renderer3.push("<!--]-->");
        } else {
          $$renderer3.push("<!--[!-->");
          $$renderer3.push("<!--]-->");
        }
      };
      if (Slider) {
        $$renderer2.push("<!--[-->");
        Slider($$renderer2, {
          type: "single",
          value: clampedValue(),
          min: effectiveMin(),
          max: effectiveMax(),
          step: effectiveStep(),
          disabled,
          onValueChange: handleSliderChange,
          onValueCommit: handleSliderCommit,
          class: "relative flex w-full touch-none select-none items-center py-1 cursor-pointer disabled:cursor-not-allowed",
          "aria-label": label ? `${label} slider` : "Range slider",
          children,
          $$slots: { default: true }
        });
        $$renderer2.push("<!--]-->");
      } else {
        $$renderer2.push("<!--[!-->");
        $$renderer2.push("<!--]-->");
      }
    }
    $$renderer2.push(`</div></div>`);
    bind_props($$props, { value });
  });
}
function ResultSheetComponentPreview($$renderer, $$props) {
  $$renderer.component(($$renderer2) => {
    let {
      component = {},
      componentTemplate = "",
      sampleContext = {},
      noticeDocumentImagePreview = false,
      noticeDocumentImageAssets = []
    } = $$props;
    const safeNoticeImageSrc = derived(() => {
      if (typeof window === "undefined" || !noticeDocumentImagePreview) return null;
      const verified = noticeDocumentImageAssets.find((asset) => asset.asset_id === component.props?.asset_id);
      if (!verified || typeof verified.src !== "string") return null;
      try {
        const candidate = new URL(verified.src, window.location.origin);
        return candidate.origin === window.location.origin && !candidate.username && !candidate.password && ["http:", "https:"].includes(candidate.protocol) ? candidate.href : null;
      } catch {
        return null;
      }
    });
    const props = derived(() => normalizeComponentProps(component.type, component.props ?? {}));
    const typography = derived(() => props().typography);
    const textStyles = derived(() => props().text_styles ?? {});
    const fallbackScoreRows = [
      { domain: "Verbal", raw: "82", converted: "Good" },
      { domain: "Quantitative", raw: "91", converted: "Excellent" },
      { domain: "Spatial", raw: "76", converted: "Good" }
    ];
    const scoreRows = derived(() => Array.isArray(sampleContext?.scores_rows) && sampleContext.scores_rows.length > 0 ? sampleContext.scores_rows : fallbackScoreRows);
    const defaultKpiMetrics = [
      { label: "Total Examinees", binding: "total_examinees" },
      { label: "Total Passed", binding: "total_passed" },
      { label: "Passing Rate", binding: "passing_rate" }
    ];
    const defaultTableColumns = [
      { label: "Row #", binding: "row_no" },
      { label: "Reference", binding: "reference_code" },
      { label: "Applicant Name", binding: "applicant_name" },
      { label: "Program", binding: "program_code" },
      { label: "Score", binding: "composite_score" },
      { label: "Remarks", binding: "remarks" }
    ];
    const placeholderTableRows = [
      {
        row_no: 1,
        reference_code: "APP-001",
        applicant_name: "Sample Applicant",
        program_code: "Sample Program",
        composite_score: "—",
        remarks: "Pending"
      },
      {
        row_no: 2,
        reference_code: "APP-002",
        applicant_name: "Sample Applicant",
        program_code: "Sample Program",
        composite_score: "—",
        remarks: "Pending"
      },
      {
        row_no: 3,
        reference_code: "APP-003",
        applicant_name: "Sample Applicant",
        program_code: "Sample Program",
        composite_score: "—",
        remarks: "Pending"
      }
    ];
    const kpiMetrics = derived(() => Array.isArray(props().metrics) && props().metrics.length ? props().metrics : defaultKpiMetrics);
    const tableColumns = derived(() => Array.isArray(props().columns) && props().columns.length ? props().columns : defaultTableColumns);
    const tableRows = derived(() => Array.isArray(sampleContext?.[props().repeat_key || "passers"]) && sampleContext[props().repeat_key || "passers"].length ? sampleContext[props().repeat_key || "passers"] : placeholderTableRows);
    function columnLabel(column) {
      return typeof column === "string" ? column : column?.label ?? column?.title ?? column?.key ?? "";
    }
    function columnBinding(column) {
      if (typeof column !== "string") {
        return column?.binding ?? column?.key ?? column?.field ?? "";
      }
      return {
        "Row #": "row_no",
        Reference: "reference_code",
        "Applicant Name": "applicant_name",
        Program: "program_code",
        Score: "composite_score",
        Remarks: "remarks"
      }[column] ?? column;
    }
    function rowValue(row, column) {
      const binding = columnBinding(column);
      const key = binding.split(".").reduce((current, part) => current?.[part], row);
      return key ?? "";
    }
    const appearance = derived(() => props().appearance);
    const cellStyle = derived(() => `border:1px solid ${appearance().border_color};padding:1.5mm;`);
    function value(binding, fallback = "Sample value") {
      if (sampleContext && typeof sampleContext === "object" && binding in sampleContext) {
        return sampleContext[binding];
      }
      return sampleValue(binding, fallback);
    }
    function partStyle(part, extra = "") {
      return `${inlineTypographyStyle(textStyles()[part] ?? typography())}${extra}`;
    }
    $$renderer2.push(`<div class="h-full w-full min-w-0 overflow-hidden"${attr_style(inlineTypographyStyle(typography()))}>`);
    if (componentTemplate) {
      $$renderer2.push("<!--[0-->");
      $$renderer2.push(`${html(renderComponentTemplate(componentTemplate, component, props()))}`);
    } else if (component.type === "header") {
      $$renderer2.push("<!--[1-->");
      $$renderer2.push(`<div class="flex h-full min-w-0 flex-col justify-center"><div${attr_style(partStyle("title", "margin:0;line-height:1.1;"))}>${escape_html(replaceMacros(props().title ?? "{{institution_name}}"))}</div> <div${attr_style(partStyle("subtitle", "margin-top:1.5mm;"))}>${escape_html(replaceMacros(props().subtitle ?? "{{institution_exam_name}}"))}</div></div>`);
    } else if (component.type === "identity") {
      $$renderer2.push("<!--[2-->");
      $$renderer2.push(`<table class="w-full border-collapse"${attr_style(partStyle("value"))}><tbody><tr><td${attr_style(`${cellStyle()}${partStyle("label")}`)}>Applicant No.</td><td${attr_style(`${cellStyle()}${partStyle("value")}`)}>${escape_html(value("applicant_reference"))}</td><td${attr_style(`${cellStyle()}${partStyle("label")}`)}>Name</td><td${attr_style(`${cellStyle()}${partStyle("value")}`)}>${escape_html(value("family_name"))}, ${escape_html(value("first_name"))}</td></tr><tr><td${attr_style(`${cellStyle()}${partStyle("label")}`)}>Sex / GWA</td><td${attr_style(`${cellStyle()}${partStyle("value")}`)}>${escape_html(value("sex"))} / ${escape_html(value("gwa"))}</td><td${attr_style(`${cellStyle()}${partStyle("label")}`)}>Course / Strand</td><td${attr_style(`${cellStyle()}${partStyle("value")}`)}>${escape_html(value("course_applied_code"))} / ${escape_html(value("strand"))}</td></tr></tbody></table>`);
    } else if (component.type === "exam_details") {
      $$renderer2.push("<!--[3-->");
      $$renderer2.push(`<div class="flex h-full min-w-0 items-center"${attr_style(partStyle("body"))}>Date of Examination: ${escape_html(value("exam_date"))} (${escape_html(value("exam_time"))}) ·
            Room: ${escape_html(value("room_name"))} · Course: ${escape_html(value("course_applied_code"))}</div>`);
    } else if (component.type === "field") {
      $$renderer2.push("<!--[4-->");
      $$renderer2.push(`<div class="flex h-full min-w-0 items-center gap-1"><span${attr_style(partStyle("label"))}>${escape_html(props().label ?? "Field")}:</span><span${attr_style(partStyle("value"))}>${escape_html(value(props().binding, "Sample value"))}</span></div>`);
    } else if (component.type === "kpi_grid") {
      $$renderer2.push("<!--[5-->");
      $$renderer2.push(`<div class="flex h-full min-w-0 items-stretch gap-2"><!--[-->`);
      const each_array = ensure_array_like(kpiMetrics());
      for (let $$index = 0, $$length = each_array.length; $$index < $$length; $$index++) {
        let metric = each_array[$$index];
        const label = typeof metric === "string" ? metric : metric.label ?? metric.title ?? "Metric";
        const binding = typeof metric === "string" ? metric : metric.binding ?? metric.key ?? "";
        $$renderer2.push(`<div class="flex min-w-0 flex-1 flex-col justify-center rounded border px-2 py-1"${attr_style(`border-color:${appearance().border_color};`)}><div${attr_style(partStyle("label"))}>${escape_html(label)}</div> <div${attr_style(partStyle("value"))}>${escape_html(binding ? value(binding, metric.value ?? "—") : metric.value ?? "—")}</div></div>`);
      }
      $$renderer2.push(`<!--]--></div>`);
    } else if (component.type === "data_table") {
      $$renderer2.push("<!--[6-->");
      if (props().title) {
        $$renderer2.push("<!--[0-->");
        $$renderer2.push(`<div${attr_style(partStyle("title"))}>${escape_html(props().title)}</div>`);
      } else {
        $$renderer2.push("<!--[-1-->");
      }
      $$renderer2.push(`<!--]--> <table class="w-full border-collapse"${attr_style(partStyle("body"))}><thead><tr><!--[-->`);
      const each_array_1 = ensure_array_like(tableColumns());
      for (let $$index_1 = 0, $$length = each_array_1.length; $$index_1 < $$length; $$index_1++) {
        let column = each_array_1[$$index_1];
        $$renderer2.push(`<th class="text-left"${attr_style(`${cellStyle()}${partStyle("heading")}`)}>${escape_html(columnLabel(column))}</th>`);
      }
      $$renderer2.push(`<!--]--></tr></thead><tbody><!--[-->`);
      const each_array_2 = ensure_array_like(tableRows());
      for (let rowIndex = 0, $$length = each_array_2.length; rowIndex < $$length; rowIndex++) {
        let row = each_array_2[rowIndex];
        $$renderer2.push(`<tr><!--[-->`);
        const each_array_3 = ensure_array_like(tableColumns());
        for (let $$index_2 = 0, $$length2 = each_array_3.length; $$index_2 < $$length2; $$index_2++) {
          let column = each_array_3[$$index_2];
          $$renderer2.push(`<td${attr_style(`${cellStyle()}${partStyle("body")}`)}>${escape_html(rowValue(row, column) || (columnBinding(column) === "row_no" ? rowIndex + 1 : ""))}</td>`);
        }
        $$renderer2.push(`<!--]--></tr>`);
      }
      $$renderer2.push(`<!--]--></tbody></table>`);
    } else if (component.type === "score_table") {
      $$renderer2.push("<!--[7-->");
      $$renderer2.push(`<div${attr_style(partStyle("title"))}>${escape_html(props().title ?? "Record of Scores")}</div> <table class="mt-2 w-full border-collapse"><thead><tr><th class="text-left"${attr_style(`${cellStyle()}${partStyle("heading")}`)}>Aptitude Factor</th><th class="text-left"${attr_style(`${cellStyle()}${partStyle("heading")}`)}>Raw Score</th><th class="text-left"${attr_style(`${cellStyle()}${partStyle("heading")}`)}>Descriptive Rating</th></tr></thead><tbody><!--[-->`);
      const each_array_4 = ensure_array_like(scoreRows());
      for (let $$index_4 = 0, $$length = each_array_4.length; $$index_4 < $$length; $$index_4++) {
        let row = each_array_4[$$index_4];
        $$renderer2.push(`<tr><td${attr_style(`${cellStyle()}${partStyle("body")}`)}>${escape_html(row.domain ?? "Aptitude")}</td><td${attr_style(`${cellStyle()}${partStyle("body")}`)}>${escape_html(row.raw ?? "")}</td><td${attr_style(`${cellStyle()}${partStyle("body")}`)}>${escape_html(row.converted ?? "")}</td></tr>`);
      }
      $$renderer2.push(`<!--]--></tbody></table>`);
    } else if (component.type === "overall") {
      $$renderer2.push("<!--[8-->");
      $$renderer2.push(`<div class="flex h-full min-w-0 items-center gap-2"><span${attr_style(partStyle("label"))}>Overall Score:</span><span${attr_style(partStyle("value"))}>${escape_html(value("overall_pct"))}</span></div>`);
    } else if (component.type === "recommendation") {
      $$renderer2.push("<!--[9-->");
      $$renderer2.push(`<div${attr_style(partStyle("heading"))}>RECOMMENDED PROGRAM</div> <div class="mt-1"${attr_style(partStyle("body"))}>${escape_html(value("recommended_course"))}</div> <div class="mt-2"${attr_style(partStyle("note"))}>${escape_html(value("counselor_comments"))}</div>`);
    } else if (component.type === "signature") {
      $$renderer2.push("<!--[10-->");
      $$renderer2.push(`<div class="flex h-full min-w-0 flex-col items-center justify-center text-center"><div class="mb-1 h-[8mm] w-[70%] border-b border-slate-400"></div> <div${attr_style(partStyle("name"))}>${escape_html(value(props().binding, "Guidance Counselor"))}</div> <div${attr_style(partStyle("role"))}>${escape_html(props().label ?? "Counselor")}</div></div>`);
    } else if (component.type === "note") {
      $$renderer2.push("<!--[11-->");
      $$renderer2.push(`<div${attr_style(partStyle("body", "font-style:italic;"))}>${escape_html(replaceMacros(props().text ?? "Policy or operational note."))}</div>`);
    } else if (component.type === "footer") {
      $$renderer2.push("<!--[12-->");
      $$renderer2.push(`<div class="flex h-full min-w-0 items-center justify-center"${attr_style(partStyle("body", "opacity:.7;"))}>${escape_html(replaceMacros(props().text ?? "Generated from the NEXIAM result-sheet template."))}</div>`);
    } else if (component.type === "text") {
      $$renderer2.push("<!--[13-->");
      $$renderer2.push(`<div class="flex h-full min-w-0 items-center"${attr_style(partStyle("body"))}>${escape_html(replaceMacros(props().text ?? "Add text"))}</div>`);
    } else if (component.type === "divider") {
      $$renderer2.push("<!--[14-->");
      $$renderer2.push(`<div class="mt-1 w-full border-t"${attr_style(`border-color:${appearance().border_color};border-top-width:${Math.max(1, appearance().border_width_px)}px;border-top-style:${appearance().border_style};`)}></div>`);
    } else if (component.type === "spacer") {
      $$renderer2.push("<!--[15-->");
      $$renderer2.push(`<div class="h-full"></div>`);
    } else if (component.type === "dynamic_image") {
      $$renderer2.push("<!--[16-->");
      $$renderer2.push(`<div class="flex h-full flex-col items-center justify-center border-2 border-dashed border-slate-300 bg-slate-50 text-[10px] font-medium text-slate-500"><span>2x2 Photo</span> <span class="text-[8px] opacity-75">${escape_html(component.props?.binding ?? "applicant_photo_2x2")}</span></div>`);
    } else if (component.type === "dynamic_qr") {
      $$renderer2.push("<!--[17-->");
      $$renderer2.push(`<div class="flex h-full flex-col items-center justify-center border border-slate-300 bg-white p-1 font-mono text-[10px] text-slate-700"><div class="flex size-6 items-center justify-center border-2 border-black text-[7px] font-bold">QR</div> <span class="mt-0.5 max-w-full truncate text-[8px] opacity-75">${escape_html(component.props?.binding ?? "verification_qr")}</span></div>`);
    } else if (component.type === "image") {
      $$renderer2.push("<!--[18-->");
      if (noticeDocumentImagePreview && safeNoticeImageSrc()) {
        $$renderer2.push("<!--[0-->");
        $$renderer2.push(`<img${attr("src", safeNoticeImageSrc())}${attr("alt", component.props?.alt ?? "Document image")} class="h-full w-full"${attr_style(`object-fit:${props().fit ?? "contain"};`)}/>`);
      } else {
        $$renderer2.push("<!--[-1-->");
        $$renderer2.push(`<div class="flex h-full items-center justify-center border border-dashed border-slate-300 text-xs opacity-60">${escape_html(noticeDocumentImagePreview ? "Verified image unavailable" : "Approved image")}</div>`);
      }
      $$renderer2.push(`<!--]-->`);
    } else {
      $$renderer2.push("<!--[-1-->");
      $$renderer2.push(`<div>${escape_html(component.type)}</div>`);
    }
    $$renderer2.push(`<!--]--></div>`);
  });
}
function ResultSheetTypographyControls($$renderer, $$props) {
  $$renderer.component(($$renderer2) => {
    let { typography = {}, onChanged = () => {
    }, emailFlow = false } = $$props;
    const emailFonts = [
      "Arial",
      "Verdana",
      "Georgia",
      "Times New Roman",
      "Tahoma",
      "Trebuchet MS"
    ].map((value) => ({ value, label: value }));
    function change(key, value) {
      onChanged({ ...typography, [key]: value });
    }
    $$renderer2.push(`<section class="space-y-2.5 border-t border-studio-border pt-3"><div class="flex items-center justify-between"><h4 class="text-[10px] font-bold uppercase tracking-wider text-studio-panel-muted-foreground">Typography</h4> <span class="font-mono text-[9px] text-studio-panel-muted-foreground">${escape_html(emailFlow ? typography.font_size ?? 16 : typography.font_size_pt ?? 10)} ${escape_html(emailFlow ? "px" : "pt")}</span></div> <div class="grid grid-cols-2 gap-2"><div>`);
    NumericControl($$renderer2, {
      label: "Font size",
      min: emailFlow ? 8 : 6,
      max: emailFlow ? 72 : 48,
      step: emailFlow ? 1 : 0.5,
      unit: emailFlow ? "px" : "pt",
      value: emailFlow ? typography.font_size ?? 16 : typography.font_size_pt ?? 10,
      oninput: (val) => change(emailFlow ? "font_size" : "font_size_pt", val),
      oncommit: (val) => change(emailFlow ? "font_size" : "font_size_pt", val)
    });
    $$renderer2.push(`<!----></div> <div><label for="typography-font-family" class="block text-[10px] font-semibold text-studio-panel-muted-foreground mb-1">Font family</label> `);
    $$renderer2.select(
      {
        id: "typography-font-family",
        class: "w-full rounded border border-studio-border bg-studio-panel px-2 py-1 text-xs text-studio-panel-foreground",
        value: typography.font_family ?? RESULT_SHEET_FONT_OPTIONS[0].value,
        onchange: (event) => change("font_family", event.currentTarget.value)
      },
      ($$renderer3) => {
        $$renderer3.push(`<!--[-->`);
        const each_array = ensure_array_like(emailFlow ? emailFonts : RESULT_SHEET_FONT_OPTIONS);
        for (let $$index = 0, $$length = each_array.length; $$index < $$length; $$index++) {
          let font = each_array[$$index];
          $$renderer3.option({ value: font.value }, ($$renderer4) => {
            $$renderer4.push(`${escape_html(font.label)}`);
          });
        }
        $$renderer3.push(`<!--]-->`);
      }
    );
    $$renderer2.push(`</div></div> <div class="flex items-center gap-2 pt-1">`);
    if (!emailFlow) {
      $$renderer2.push("<!--[0-->");
      $$renderer2.push(`<button type="button"${attr_class(`rounded border p-1.5 transition ${Number(typography.font_weight) >= 700 ? "border-studio-focus bg-studio-toolbar text-studio-toolbar-foreground" : "border-studio-border bg-studio-panel text-studio-panel-foreground hover:bg-studio-panel-muted"}`)} title="Toggle bold" aria-label="Toggle bold">`);
      Bold($$renderer2, { class: "size-3.5" });
      $$renderer2.push(`<!----></button>`);
    } else {
      $$renderer2.push("<!--[-1-->");
    }
    $$renderer2.push(`<!--]--> <div class="flex items-center rounded border border-studio-border bg-studio-panel-muted p-0.5"><!--[-->`);
    const each_array_1 = ensure_array_like(RESULT_SHEET_TEXT_ALIGN_OPTIONS);
    for (let $$index_1 = 0, $$length = each_array_1.length; $$index_1 < $$length; $$index_1++) {
      let option = each_array_1[$$index_1];
      $$renderer2.push(`<button type="button"${attr_class(`rounded p-1 transition ${typography.text_align === option.value ? "bg-studio-panel shadow-sm text-studio-panel-foreground" : "text-studio-panel-muted-foreground hover:text-studio-panel-foreground"}`)}${attr("title", `Align ${option.label.toLowerCase()}`)}${attr("aria-label", `Align ${option.label.toLowerCase()}`)}>`);
      if (option.value === "left") {
        $$renderer2.push("<!--[0-->");
        Text_align_start($$renderer2, { class: "size-3" });
      } else if (option.value === "center") {
        $$renderer2.push("<!--[1-->");
        Text_align_center($$renderer2, { class: "size-3" });
      } else if (option.value === "right") {
        $$renderer2.push("<!--[2-->");
        Text_align_end($$renderer2, { class: "size-3" });
      } else {
        $$renderer2.push("<!--[-1-->");
        Text_align_justify($$renderer2, { class: "size-3" });
      }
      $$renderer2.push(`<!--]--></button>`);
    }
    $$renderer2.push(`<!--]--></div> <label class="ml-auto flex items-center gap-1.5 text-[10px] text-studio-panel-muted-foreground font-semibold cursor-pointer"><span>Color</span> <input type="color"${attr("value", typography.text_color ?? "#0f172a")} class="size-7 cursor-pointer rounded border border-studio-border bg-studio-panel p-0.5" aria-label="Text color"/></label></div> `);
    if (!emailFlow) {
      $$renderer2.push("<!--[0-->");
      $$renderer2.push(`<div class="grid grid-cols-2 gap-2"><div>`);
      NumericControl($$renderer2, {
        label: "Line height",
        min: 0.8,
        max: 2.4,
        step: 0.05,
        unit: "",
        value: typography.line_height ?? 1.2,
        oninput: (val) => change("line_height", val),
        oncommit: (val) => change("line_height", val)
      });
      $$renderer2.push(`<!----></div> <div>`);
      NumericControl($$renderer2, {
        label: "Letter spacing",
        min: -2,
        max: 8,
        step: 0.5,
        unit: "px",
        value: typography.letter_spacing_px ?? 0,
        oninput: (val) => change("letter_spacing_px", val),
        oncommit: (val) => change("letter_spacing_px", val)
      });
      $$renderer2.push(`<!----></div></div>`);
    } else {
      $$renderer2.push("<!--[-1-->");
    }
    $$renderer2.push(`<!--]--></section>`);
  });
}
function ResultSheetAppearanceControls($$renderer, $$props) {
  $$renderer.component(($$renderer2) => {
    let {
      appearance = {},
      spacing = {},
      onAppearanceChanged = () => {
      },
      onSpacingChanged = () => {
      }
    } = $$props;
    function changeAppearance(key, value) {
      onAppearanceChanged({ ...appearance, [key]: value });
    }
    $$renderer2.push(`<section class="space-y-2.5 border-t border-studio-border pt-3"><div class="flex items-center justify-between"><h4 class="text-[10px] font-bold uppercase tracking-wider text-studio-panel-muted-foreground">Appearance &amp; spacing</h4> <span class="font-mono text-[9px] text-studio-panel-muted-foreground">${escape_html(spacing.padding_mm ?? 2)} mm pad</span></div> <div class="space-y-2.5 rounded-lg border border-studio-border bg-studio-panel-muted p-2.5"><div class="flex items-center justify-between"><label class="flex items-center gap-1.5 text-[10px] font-semibold text-studio-panel-muted-foreground cursor-pointer"><input type="checkbox"${attr("checked", appearance.background_color === "transparent", true)} class="rounded border border-studio-border text-studio-toolbar-foreground focus:ring-studio-focus"/> <span>Transparent background</span></label> <input type="color"${attr("disabled", appearance.background_color === "transparent", true)}${attr("value", appearance.background_color === "transparent" ? "#ffffff" : appearance.background_color ?? "#ffffff")} class="size-7 cursor-pointer rounded border border-studio-border bg-studio-panel p-0.5 disabled:cursor-not-allowed disabled:opacity-40" aria-label="Background color"/></div> <div class="grid grid-cols-2 gap-2"><div class="space-y-1">`);
    NumericControl($$renderer2, {
      label: "Border width",
      min: 0,
      max: 4,
      step: 1,
      unit: "px",
      value: appearance.border_width_px ?? 0,
      oninput: (val) => changeAppearance("border_width_px", val),
      oncommit: (val) => changeAppearance("border_width_px", val)
    });
    $$renderer2.push(`<!----></div> <div><label for="appearance-border-style" class="block text-[10px] font-semibold text-studio-panel-muted-foreground mb-1">Border style</label> `);
    $$renderer2.select(
      {
        id: "appearance-border-style",
        class: "w-full rounded border border-studio-border bg-studio-panel px-2 py-1 text-xs text-studio-panel-foreground",
        value: appearance.border_style ?? "solid",
        onchange: (event) => changeAppearance("border_style", event.currentTarget.value)
      },
      ($$renderer3) => {
        $$renderer3.push(`<!--[-->`);
        const each_array = ensure_array_like(RESULT_SHEET_BORDER_STYLE_OPTIONS);
        for (let $$index = 0, $$length = each_array.length; $$index < $$length; $$index++) {
          let option = each_array[$$index];
          $$renderer3.option({ value: option.value }, ($$renderer4) => {
            $$renderer4.push(`${escape_html(option.label)}`);
          });
        }
        $$renderer3.push(`<!--]-->`);
      }
    );
    $$renderer2.push(`</div></div> <div class="grid grid-cols-2 gap-2 items-end"><div><label for="appearance-border-color" class="block text-[10px] font-semibold text-studio-panel-muted-foreground mb-1">Border color</label> <input id="appearance-border-color" class="h-7 w-full cursor-pointer rounded border border-studio-border bg-studio-panel p-0.5" type="color"${attr("value", appearance.border_color ?? "#cbd5e1")} aria-label="Border color"/></div> <div>`);
    NumericControl($$renderer2, {
      label: "Radius",
      min: 0,
      max: 8,
      step: 0.5,
      unit: "mm",
      value: appearance.border_radius_mm ?? 0,
      oninput: (val) => changeAppearance("border_radius_mm", val),
      oncommit: (val) => changeAppearance("border_radius_mm", val)
    });
    $$renderer2.push(`<!----></div></div></div> <div>`);
    NumericControl($$renderer2, {
      label: "Inner padding",
      min: 0,
      max: 12,
      step: 0.5,
      unit: "mm",
      value: spacing.padding_mm ?? 2,
      oninput: (val) => onSpacingChanged({ ...spacing, padding_mm: val }),
      oncommit: (val) => onSpacingChanged({ ...spacing, padding_mm: val })
    });
    $$renderer2.push(`<!----></div></section>`);
  });
}
function ResultSheetShortcutHelp($$renderer, $$props) {
  $$renderer.component(($$renderer2) => {
    let { open = false } = $$props;
    const isMac = typeof navigator !== "undefined" && /Mac|iPod|iPhone|iPad/.test(navigator.platform || navigator.userAgent);
    const modKey = isMac ? "⌘" : "Ctrl";
    const altKey = isMac ? "⌥" : "Alt";
    const shiftKey = isMac ? "⇧" : "Shift";
    const shortcutSections = [
      {
        title: "Selection & Tools",
        shortcuts: [
          { keys: ["V"], description: "Select / Marquee tool" },
          { keys: ["H"], description: "Pan / Hand tool" },
          { keys: ["Click"], description: "Select component exclusively" },
          {
            keys: [shiftKey, "Click"],
            description: "Toggle component in selection"
          },
          {
            keys: ["Drag"],
            description: "Marquee select on empty canvas"
          },
          {
            keys: [shiftKey, "Drag"],
            description: "Additive marquee selection"
          },
          { keys: [modKey, "A"], description: "Select all components" },
          {
            keys: ["Esc"],
            description: "Deselect all or cancel active drag"
          }
        ]
      },
      {
        title: "History & Duplication",
        shortcuts: [
          { keys: [modKey, "Z"], description: "Undo last change" },
          {
            keys: [modKey, shiftKey, "Z"],
            description: "Redo last change"
          },
          {
            keys: [modKey, "D"],
            description: "Duplicate selected components"
          },
          {
            keys: [altKey, "Drag"],
            description: "Duplicate while dragging"
          }
        ]
      },
      {
        title: "Movement & Alignment",
        shortcuts: [
          { keys: ["Arrow keys"], description: "Nudge selection 1 mm" },
          {
            keys: [shiftKey, "Arrows"],
            description: "Large nudge selection 5 mm"
          },
          {
            keys: [shiftKey, "Drag"],
            description: "Lock drag to horizontal / vertical axis"
          },
          { keys: ["Space", "Drag"], description: "Pan canvas workspace" }
        ]
      },
      {
        title: "Layers & Organization",
        shortcuts: [
          { keys: [modKey, "]"], description: "Bring forward one step" },
          { keys: [modKey, "["], description: "Send backward one step" },
          {
            keys: [modKey, shiftKey, "]"],
            description: "Bring to front (top layer)"
          },
          {
            keys: [modKey, shiftKey, "["],
            description: "Send to back (bottom layer)"
          }
        ]
      },
      {
        title: "Lock & Delete",
        shortcuts: [
          {
            keys: [modKey, "L"],
            description: "Toggle lock state of selection"
          },
          {
            keys: ["Delete / ⌫"],
            description: "Delete selected unlocked components"
          }
        ]
      },
      {
        title: "Help & Navigation",
        shortcuts: [
          {
            keys: ["? / F1"],
            description: "Open this shortcut reference"
          }
        ]
      }
    ];
    if (open) {
      $$renderer2.push("<!--[0-->");
      $$renderer2.push(`<div class="fixed inset-0 z-50 flex items-center justify-center bg-studio-overlay p-4 backdrop-blur-sm animate-in fade-in duration-150 motion-reduce:animate-none" role="dialog" tabindex="-1" aria-modal="true" aria-labelledby="shortcut-help-title"><div class="relative flex max-h-[85vh] w-full max-w-2xl flex-col rounded-2xl border border-studio-border bg-studio-panel shadow-2xl overflow-hidden"><div class="flex items-center justify-between border-b border-studio-border px-5 py-4"><div class="flex items-center gap-2.5"><div class="flex size-8 items-center justify-center rounded-lg bg-studio-selection text-studio-selection-foreground">`);
      Keyboard($$renderer2, { class: "size-4" });
      $$renderer2.push(`<!----></div> <div><h2 id="shortcut-help-title" class="text-sm font-bold text-studio-panel-foreground">Studio Keyboard Shortcuts</h2> <p class="text-xs text-studio-panel-muted-foreground">Fast, precision authoring with keyboard and gesture commands</p></div></div> <button type="button" class="rounded-lg p-1.5 text-studio-panel-muted-foreground hover:bg-studio-panel-muted hover:text-studio-panel-foreground transition-colors motion-reduce:transition-none" aria-label="Close shortcut dialog">`);
      X($$renderer2, { class: "size-4" });
      $$renderer2.push(`<!----></button></div> <div class="flex-1 overflow-y-auto p-5"><div class="grid grid-cols-1 gap-6 sm:grid-cols-2"><!--[-->`);
      const each_array = ensure_array_like(shortcutSections);
      for (let $$index_2 = 0, $$length = each_array.length; $$index_2 < $$length; $$index_2++) {
        let section = each_array[$$index_2];
        $$renderer2.push(`<div class="space-y-2.5"><h3 class="text-[11px] font-bold uppercase tracking-wider text-studio-panel-muted-foreground">${escape_html(section.title)}</h3> <div class="space-y-1.5"><!--[-->`);
        const each_array_1 = ensure_array_like(section.shortcuts);
        for (let $$index_1 = 0, $$length2 = each_array_1.length; $$index_1 < $$length2; $$index_1++) {
          let item = each_array_1[$$index_1];
          $$renderer2.push(`<div class="flex items-center justify-between gap-3 text-xs"><span class="text-studio-panel-foreground/80 text-[11px] leading-snug">${escape_html(item.description)}</span> <div class="flex items-center gap-1 shrink-0"><!--[-->`);
          const each_array_2 = ensure_array_like(item.keys);
          for (let $$index = 0, $$length3 = each_array_2.length; $$index < $$length3; $$index++) {
            let key = each_array_2[$$index];
            $$renderer2.push(`<kbd class="inline-flex min-w-5 items-center justify-center rounded border border-studio-border bg-studio-panel-muted px-1.5 py-0.5 font-mono text-[10px] font-semibold text-studio-panel-foreground shadow-sm">${escape_html(key)}</kbd>`);
          }
          $$renderer2.push(`<!--]--></div></div>`);
        }
        $$renderer2.push(`<!--]--></div></div>`);
      }
      $$renderer2.push(`<!--]--></div></div> <div class="flex items-center justify-between border-t border-studio-border bg-studio-panel-muted px-5 py-3 text-xs text-studio-panel-muted-foreground"><span>Shortcuts are active when canvas or controls are focused.</span> <button type="button" class="rounded-lg bg-studio-toolbar px-3 py-1.5 text-xs font-semibold text-studio-toolbar-foreground hover:bg-studio-shell transition-colors motion-reduce:transition-none">Got it</button></div></div></div>`);
    } else {
      $$renderer2.push("<!--[-1-->");
    }
    $$renderer2.push(`<!--]-->`);
  });
}
function ResultSheetStudio($$renderer, $$props) {
  $$renderer.component(($$renderer2) => {
    let {
      builderDocument = null,
      builderDefaults = {},
      builderCatalog = {},
      placeholderGroups = {},
      legacyContent = "",
      components: legacyComponents = null,
      activeComponentId = null,
      activeComponentIds = [],
      onChange = () => {
      },
      onConvertLegacy = () => {
      },
      onOpenPlaceholders = () => {
      },
      onBinding = () => {
      },
      onSelectionChange = () => {
      },
      onViewportChange = () => {
      },
      fullBleed = false,
      adapterActions = null,
      builderCapabilities = {},
      sampleContext = {},
      readOnly = false,
      allowNoticeDocumentImages = false,
      noticeDocumentImageAssets = [],
      onNoticeDocumentImageUpload = null
    } = $$props;
    let noticeImageUploading = false;
    const legacyMode = legacyComponents !== null;
    function normalizePage(page2 = {}) {
      const paperSize = ["a4", "legal", "letter"].includes(page2.paper_size) ? page2.paper_size : "a4";
      const orientation = ["portrait", "landscape"].includes(page2.orientation) ? page2.orientation : "portrait";
      const base = paperSize === "legal" ? { width_mm: 216, height_mm: 356 } : paperSize === "letter" ? { width_mm: 216, height_mm: 279 } : { width_mm: 210, height_mm: 297 };
      const dimensions = orientation === "landscape" ? { width_mm: base.height_mm, height_mm: base.width_mm } : base;
      return {
        paper_size: paperSize,
        orientation,
        ...dimensions,
        margin_mm: Math.min(30, Math.max(5, Number(page2.margin_mm ?? 12)))
      };
    }
    function clone(value) {
      return value ? JSON.parse(JSON.stringify(value)) : {};
    }
    let localDocument = null;
    function normalizeDocument(value) {
      if (Array.isArray(value)) {
        const fallback = [localDocument, builderDocument, builderDefaults].find((candidate) => candidate && typeof candidate === "object" && !Array.isArray(candidate)) ?? {};
        const base = clone(fallback);
        base.components = value.map((component) => ({
          ...component,
          props: normalizeComponentProps(component.type, component.props ?? {})
        }));
        return base;
      }
      const document = clone(value);
      document.components = Array.isArray(document?.components) ? document.components.map((component) => ({
        ...component,
        props: normalizeComponentProps(component.type, component.props ?? {})
      })) : [];
      return document;
    }
    localDocument = normalizeDocument(builderDocument ?? (legacyMode ? { components: legacyComponents } : builderDefaults));
    let historyEngine = createStudioHistory(normalizeDocument(localDocument), 100);
    let historyStatus = historyEngine.status();
    createStudioSelection();
    let selectedIds = new Set(Array.isArray(activeComponentIds) && activeComponentIds.length > 0 ? activeComponentIds : activeComponentId ? [activeComponentId] : []);
    let primaryId = activeComponentId ?? activeComponentIds?.at?.(-1) ?? null;
    let sidebarView = "inspector";
    let isSpacePressed = false;
    let zoom = 0.72;
    let zoomMode = "fit";
    let panX = 0;
    let panY = 0;
    let mobileSurfaceHeight = 0;
    let fitInvalidation = 0;
    let selectedTextPart = "body";
    let activeMobilePanel = null;
    let paletteQuery = "";
    let paletteState = fullBleed && typeof window !== "undefined" && window.innerWidth < 1024 ? "closed" : "rail";
    let shortcutHelpOpen = false;
    let liveAnnouncement = "";
    let inspectorContextOpen = false;
    let mobileFocusMode = false;
    let viewportWidth = typeof window === "undefined" ? 1024 : window.innerWidth;
    let canvasSafeTop = 0;
    let canvasSafeBottom = 0;
    let canvasSafeRight = 0;
    const paletteRailOpen = derived(() => paletteState === "rail");
    const paletteExpanded = derived(() => paletteState === "expanded");
    const belowDesktop = derived(() => viewportWidth < 1280);
    const mobileSurfaceOpen = derived(() => fullBleed && belowDesktop() && activeMobilePanel !== null);
    const mobileSurfaceInset = derived(() => mobileSurfaceOpen() ? mobileSurfaceHeight + 12 : 0);
    const canvasSafeLeft = derived(() => 0);
    let activeSnapGuides = [];
    const MM_TO_PX = 96 / 25.4;
    const page = derived(() => normalizePage(localDocument?.page ?? builderDefaults?.page ?? {}));
    const components = derived(() => Array.isArray(localDocument?.components) ? localDocument.components : []);
    const selectedCount = derived(() => selectedIds.size);
    const studioCatalog = derived(() => builderCatalog);
    const paletteEntries = derived(() => Object.entries(studioCatalog()).filter(([type, item]) => {
      const query = paletteQuery.trim().toLowerCase();
      if (!query) return true;
      return `${type} ${item?.label ?? ""} ${item?.description ?? ""}`.toLowerCase().includes(query);
    }));
    const paletteGroups = derived(() => {
      const groups = /* @__PURE__ */ new Map();
      for (const entry of paletteEntries()) {
        const category = entry[1]?.category ?? "Components";
        if (!groups.has(category)) groups.set(category, []);
        groups.get(category).push(entry);
      }
      return [...groups.entries()];
    });
    const paletteTypeCount = derived(() => Object.keys(builderCatalog).length);
    const paletteResultCount = derived(() => paletteEntries().length);
    const paletteCountLabel = derived(() => paletteQuery.trim() ? `${paletteResultCount()} of ${paletteTypeCount()} types` : `${paletteTypeCount()} available types`);
    const inspectorPanelVisible = derived(() => inspectorContextOpen);
    const selectedList = derived(() => components().filter((component) => selectedIds.has(component.id)));
    const primaryComponent = derived(() => (primaryId ? components().find((c) => c.id === primaryId) : null) ?? selectedList()[0] ?? null);
    const primaryIsLocked = derived(() => Boolean(primaryComponent()?.locked));
    const textParts = derived(() => textPartOptions(primaryComponent()?.type ?? "text"));
    const activeTextPart = derived(() => textParts().some((part) => part.value === selectedTextPart) ? selectedTextPart : textParts()[0]?.value ?? "body");
    const activeTextStyle = derived(() => primaryComponent()?.props?.text_styles?.[activeTextPart()] ?? primaryComponent()?.props?.typography ?? {});
    const diagnostics = derived(() => diagnosticList(localDocument));
    const pagePixelWidth = derived(() => Number(page().width_mm) * MM_TO_PX);
    const pagePixelHeight = derived(() => Number(page().height_mm) * MM_TO_PX);
    const canvasReservedBottom = derived(() => canvasSafeBottom + mobileSurfaceInset());
    const fitZoom = derived(() => zoom);
    const effectiveZoom = derived(() => fitZoom());
    const pageFrameWidth = derived(() => pagePixelWidth() * effectiveZoom());
    const pageFrameHeight = derived(() => pagePixelHeight() * effectiveZoom());
    const pageStyle = derived(() => `width:${page().width_mm}mm;height:${page().height_mm}mm;transform:translate(${panX}px,${panY}px) scale(${effectiveZoom()});transform-origin:top left;`);
    const pageFrameStyle = derived(() => `width:${pageFrameWidth()}px;height:${pageFrameHeight()}px;margin-left:max(0px, calc((100% - ${canvasSafeLeft()}px - ${canvasSafeRight}px - ${pageFrameWidth()}px) / 2));`);
    const printableWidth = derived(() => Math.max(0, Number(page().width_mm) - Number(page().margin_mm) * 2));
    const printableHeight = derived(() => Math.max(0, Number(page().height_mm) - Number(page().margin_mm) * 2));
    function emit() {
      const snapshot = clone(localDocument);
      JSON.stringify(snapshot);
      onChange(snapshot);
    }
    function commit(next, label = "Update document") {
      if (readOnly) return;
      const snapshot = normalizeDocument(next);
      localDocument = snapshot;
      historyEngine.record(snapshot, label);
      historyStatus = historyEngine.status();
      emit();
    }
    function updatePageCoalesced(key, value, label = "Update page") {
      const snapshot = normalizeDocument({
        ...localDocument,
        page: { ...localDocument.page ?? {}, [key]: value }
      });
      localDocument = snapshot;
      historyEngine.record(snapshot, label, { coalesceKey: `page-${key}`, windowMs: 500 });
      historyStatus = historyEngine.status();
      emit();
    }
    function updateComponent(id, patch, label = "Update component") {
      commit(
        {
          ...localDocument,
          components: localDocument.components.map((component) => {
            if (component.id !== id) return component;
            const next = { ...component, ...patch };
            if (patch.props) next.props = normalizeComponentProps(next.type, patch.props);
            return next;
          })
        },
        label
      );
    }
    function updateComponentCoalesced(id, patch, propName, label = "Update component") {
      const nextComponents = localDocument.components.map((component) => {
        if (component.id !== id) return component;
        const next = { ...component, ...patch };
        if (patch.props) next.props = normalizeComponentProps(next.type, patch.props);
        return next;
      });
      const snapshot = normalizeDocument({ ...localDocument, components: nextComponents });
      localDocument = snapshot;
      historyEngine.record(snapshot, label, { coalesceKey: `${id}-${propName}`, windowMs: 500 });
      historyStatus = historyEngine.status();
      emit();
    }
    function updateSelectedProps(patch, label = "Update properties") {
      if (!primaryComponent() || primaryComponent().locked) return;
      updateComponent(primaryComponent().id, { props: { ...primaryComponent().props, ...patch } }, label);
    }
    function updateSelectedTypography(typography) {
      if (!primaryComponent() || primaryComponent().locked) return;
      updateSelectedProps(
        {
          typography,
          text_styles: {
            ...primaryComponent().props?.text_styles ?? {},
            [activeTextPart()]: typography
          }
        },
        "Update typography"
      );
    }
    function updateSelectedAppearance(appearance) {
      if (!primaryComponent() || primaryComponent().locked) return;
      updateSelectedProps({ appearance }, "Update appearance");
    }
    function updateSelectedSpacing(spacing) {
      if (!primaryComponent() || primaryComponent().locked) return;
      updateSelectedProps({ spacing }, "Update spacing");
    }
    function componentLabel(type) {
      return studioCatalog()[type]?.label ?? String(type).replace(/_/g, " ");
    }
    function componentIcon(type) {
      return {
        image: Image,
        header: File_text,
        identity: User_round,
        exam_details: Calendar_clock,
        field: Brackets,
        score_table: Table_2,
        overall: Badge_percent,
        recommendation: Sparkles,
        signature: Pen_line,
        note: Sticky_note,
        footer: File_text,
        text: Text_align_start
      }[type] ?? File_text;
    }
    function diagnosticList(document) {
      const pageData = document?.page ?? {};
      const width = Number(pageData.width_mm ?? 210) - Number(pageData.margin_mm ?? 12) * 2;
      const height = Number(pageData.height_mm ?? 297) - Number(pageData.margin_mm ?? 12) * 2;
      const documentComponents = Array.isArray(document?.components) ? document.components : [];
      const sampleRows = Array.isArray(sampleContext?.scores_rows) ? Math.max(3, sampleContext.scores_rows.length) : 3;
      return documentComponents.flatMap((component) => {
        const errors = [];
        const capabilities = builderCapabilities?.[component.type] ?? {};
        if (Number(component.x) < 0 || Number(component.y) < 0 || Number(component.x) + Number(component.w) > width || Number(component.y) + Number(component.h) > height) {
          errors.push(`${componentLabel(component.type)} exceeds the printable area`);
        }
        if (component.type === "field" && !component.props?.binding) errors.push("Field needs a binding");
        if (component.type === "score_table" && capabilities.dynamic_rows) {
          const fixedHeight = Number(capabilities.dynamic_fixed_height_mm ?? 12);
          const rowHeight = Number(capabilities.dynamic_row_height_mm ?? 6);
          const requiredHeight = fixedHeight + sampleRows * rowHeight;
          if (Number(component.h) + 0.01 < requiredHeight) {
            errors.push(`Score table needs at least ${requiredHeight}mm for ${sampleRows} sample rows`);
          }
        }
        return errors;
      });
    }
    $$renderer2.push(`<div class="sr-only" aria-live="polite" aria-atomic="true">${escape_html(liveAnnouncement)}</div> `);
    ResultSheetShortcutHelp($$renderer2, {
      open: shortcutHelpOpen
    });
    $$renderer2.push(`<!----> <div data-result-sheet-studio="" data-editor-surface=""${attr("data-result-sheet-mobile-focus", "false")}${attr("data-result-sheet-zoom-mode", zoomMode)}${attr("data-result-sheet-zoom", zoom)}${attr("data-result-sheet-pan-x", panX)}${attr("data-result-sheet-pan-y", panY)}${attr_class(clsx(fullBleed ? "bg-studio-shell text-studio-shell-foreground relative flex min-h-0 min-w-0 flex-1 basis-0 flex-col overflow-hidden select-none" : "border-studio-border bg-studio-shell text-studio-shell-foreground min-w-0 space-y-3 rounded-xl border p-3 shadow-inner"))}>`);
    if (!builderDocument && !mobileFocusMode) {
      $$renderer2.push("<!--[0-->");
      $$renderer2.push(`<div class="border-studio-warning bg-studio-warning text-studio-warning-foreground flex items-start gap-3 rounded-lg border p-3 text-xs">`);
      Triangle_alert($$renderer2, { class: "mt-0.5 size-4 shrink-0" });
      $$renderer2.push(`<!----> <div class="flex-1"><strong>Legacy HTML template.</strong> Convert it into the visual
                builder to start editing components; the original HTML remains untouched
                until you save the conversion.</div> <button type="button" class="bg-studio-warning-foreground text-studio-warning hover:bg-studio-warning-foreground/90 rounded-md px-2.5 py-1.5 font-semibold transition-colors motion-reduce:transition-none">Convert</button></div>`);
    } else {
      $$renderer2.push("<!--[-1-->");
    }
    $$renderer2.push(`<!--]--> `);
    if (!fullBleed || !belowDesktop() || !mobileFocusMode) {
      $$renderer2.push("<!--[0-->");
      $$renderer2.push(`<div data-result-sheet-toolbar=""${attr("data-result-sheet-floating-toolbar", fullBleed && belowDesktop() ? "true" : void 0)}${attr("data-result-sheet-toolbar-layout", fullBleed ? "generic-responsive" : "compact-wrap")}${attr_class(clsx(fullBleed ? belowDesktop() ? "border-studio-border/90 bg-studio-toolbar absolute inset-x-3 top-3 z-20 flex w-auto max-w-full min-w-0 shrink-0 flex-nowrap items-center gap-1.5 overflow-x-auto overflow-y-visible overscroll-contain rounded-xl border p-1.5 max-sm:gap-1" : "border-studio-border/90 bg-studio-toolbar relative mx-3 flex w-auto max-w-full min-w-0 shrink-0 flex-wrap items-center gap-1.5 overflow-x-hidden overflow-y-visible overscroll-contain rounded-xl border p-1.5 max-sm:gap-1 lg:mx-4 lg:grid lg:grid-cols-[minmax(0,1fr)_auto_minmax(0,1fr)] lg:flex-nowrap" : "border-studio-border bg-studio-toolbar flex flex-wrap items-center justify-between gap-2 rounded-lg border p-1.5 shadow-sm"))}><div data-result-sheet-toolbar-left="" role="group" aria-label="Studio navigation and tools"${attr_class(clsx(fullBleed && belowDesktop() ? "order-none flex min-w-0 shrink-0 flex-nowrap items-center gap-1" : "order-2 flex min-w-0 flex-1 flex-wrap items-center gap-1 max-sm:gap-0.5 lg:order-none lg:col-start-1 lg:flex-nowrap"))}><div role="group" aria-label="Canvas tools" class="border-studio-border bg-studio-panel-muted flex items-center rounded-md border p-0.5"><button type="button"${attr_class(`rounded text-xs transition ${fullBleed && belowDesktop() ? "min-h-11 min-w-11 p-2" : "p-1.5"} ${"bg-studio-selection text-studio-selection-foreground font-bold shadow-sm"}`)} title="Select &amp; Marquee tool (V)" aria-label="Select tool"${attr("aria-pressed", !isSpacePressed)}>`);
      Mouse_pointer_2($$renderer2, { class: "size-3.5" });
      $$renderer2.push(`<!----></button> <button type="button"${attr_class(`rounded text-xs transition ${fullBleed && belowDesktop() ? "min-h-11 min-w-11 p-2" : "p-1.5"} ${"text-studio-panel-muted-foreground hover:text-studio-panel-foreground"}`)} title="Pan / Hand tool (H or Space+Drag)" aria-label="Pan tool"${attr("aria-pressed", isSpacePressed)}>`);
      Hand($$renderer2, { class: "size-3.5" });
      $$renderer2.push(`<!----></button></div></div> <div data-result-sheet-toolbar-right="" data-result-sheet-toolbar-actions="" role="group" aria-label="History and canvas actions"${attr_class(clsx(fullBleed && belowDesktop() ? "order-none flex min-w-0 shrink-0 flex-nowrap items-center justify-end gap-1" : "order-3 flex min-w-0 flex-1 flex-wrap items-center justify-end gap-1 max-sm:gap-0.5 lg:order-none lg:col-start-3 lg:flex-nowrap"))}><button type="button"${attr_class(`text-studio-toolbar-foreground/80 hover:bg-studio-panel-muted rounded-md px-2 py-1 text-[11px] font-semibold transition-colors disabled:pointer-events-none disabled:opacity-30 motion-reduce:transition-none ${fullBleed && belowDesktop() ? "min-h-11 min-w-11" : ""}`)}${attr("disabled", !historyStatus.canUndo, true)} title="Undo (Ctrl+Z)" aria-label="Undo">Undo</button> <button type="button"${attr_class(`text-studio-toolbar-foreground/80 hover:bg-studio-panel-muted rounded-md px-2 py-1 text-[11px] font-semibold transition-colors disabled:pointer-events-none disabled:opacity-30 motion-reduce:transition-none ${fullBleed && belowDesktop() ? "min-h-11 min-w-11" : ""}`)}${attr("disabled", !historyStatus.canRedo, true)} title="Redo (Ctrl+Shift+Z)" aria-label="Redo">Redo</button> <button type="button"${attr_class(`text-studio-toolbar-foreground/80 hover:bg-studio-panel-muted rounded-md transition-colors motion-reduce:transition-none ${fullBleed && belowDesktop() ? "min-h-11 min-w-11 p-2" : "p-1.5"}`)} title="Zoom out" aria-label="Zoom out">`);
      Minus($$renderer2, { class: "size-3.5" });
      $$renderer2.push(`<!----></button> <span class="min-w-12 text-center font-mono text-[11px] font-bold">${escape_html(Math.round(effectiveZoom() * 100))}%</span> <button type="button"${attr_class(`text-studio-toolbar-foreground/80 hover:bg-studio-panel-muted rounded-md transition-colors motion-reduce:transition-none ${fullBleed && belowDesktop() ? "min-h-11 min-w-11 p-2" : "p-1.5"}`)} title="Zoom in" aria-label="Zoom in">`);
      Plus($$renderer2, { class: "size-3.5" });
      $$renderer2.push(`<!----></button> <button type="button"${attr_class(`text-studio-toolbar-foreground/80 hover:bg-studio-panel-muted rounded-md transition-colors motion-reduce:transition-none ${fullBleed && belowDesktop() ? "min-h-11 min-w-11 p-2" : "p-1.5"}`)} title="Fit page" aria-label="Fit page">`);
      Maximize_2($$renderer2, { class: "size-3.5" });
      $$renderer2.push(`<!----></button> <button type="button"${attr_class(`text-studio-toolbar-foreground/80 hover:bg-studio-panel-muted ml-0.5 rounded-md transition-colors motion-reduce:transition-none ${fullBleed && belowDesktop() ? "min-h-11 min-w-11 p-2" : "p-1.5"}`)} title="Keyboard shortcuts (? / F1)" aria-label="Keyboard shortcuts">`);
      Circle_question_mark($$renderer2, { class: "size-3.5" });
      $$renderer2.push(`<!----></button> `);
      if (adapterActions) {
        $$renderer2.push("<!--[0-->");
        $$renderer2.push(`<div data-result-sheet-adapter-actions="" class="relative shrink-0">`);
        adapterActions($$renderer2);
        $$renderer2.push(`<!----></div>`);
      } else {
        $$renderer2.push("<!--[-1-->");
      }
      $$renderer2.push(`<!--]--></div></div>`);
    }
    $$renderer2.push(`<!--]--> <div data-result-sheet-workspace="" id="result-sheet-studio-view"${attr_class(clsx(fullBleed ? belowDesktop() ? "relative flex min-h-0 min-w-0 flex-1 basis-0 overflow-hidden px-3 pb-3" : inspectorPanelVisible() ? paletteExpanded() ? "relative grid min-h-0 min-w-0 flex-1 basis-0 grid-cols-[260px_minmax(0,1fr)_330px] grid-rows-[minmax(0,1fr)] items-stretch gap-3 overflow-hidden px-4 pb-4" : "relative grid min-h-0 min-w-0 flex-1 basis-0 grid-cols-[88px_minmax(0,1fr)_330px] grid-rows-[minmax(0,1fr)] items-stretch gap-3 overflow-hidden px-4 pb-4" : paletteExpanded() ? "relative grid min-h-0 min-w-0 flex-1 basis-0 grid-cols-[260px_minmax(0,1fr)] grid-rows-[minmax(0,1fr)] items-stretch gap-3 overflow-hidden px-4 pb-4" : "relative grid min-h-0 min-w-0 flex-1 basis-0 grid-cols-[88px_minmax(0,1fr)] grid-rows-[minmax(0,1fr)] items-stretch gap-3 overflow-hidden px-4 pb-4" : "grid min-w-0 gap-3 lg:grid-cols-[190px_minmax(0,1fr)_280px]"))}><div data-result-sheet-mobile-surface=""${attr("data-result-sheet-mobile-surface-panel", "closed")}${attr("data-result-sheet-mobile-surface-state", mobileSurfaceOpen() ? "open" : "closed")}${attr("data-result-sheet-inspector-snap", void 0)}${attr_class(clsx(fullBleed && belowDesktop() && mobileSurfaceOpen() ? "border-studio-border bg-studio-panel absolute inset-x-3 bottom-3 z-30 flex max-h-[min(62dvh,34rem)] min-h-0 flex-col overflow-hidden rounded-xl border shadow-2xl" : fullBleed && belowDesktop() ? "hidden" : "contents"))}><aside id="result-sheet-component-palette" role="complementary"${attr("aria-label", paletteExpanded() ? "Add components" : "Add component")} data-result-sheet-panel="palette"${attr("data-result-sheet-panel-state", paletteState)}${attr("data-result-sheet-panel-position", belowDesktop() ? "bottom-surface" : paletteRailOpen() ? "left-rail" : "left-shelf")}${attr("data-result-sheet-panel-surface", belowDesktop() ? "bottom-surface" : paletteState)}${attr("data-result-sheet-palette-width", paletteExpanded() ? "260" : "88")}${attr_class(`border-studio-border bg-studio-panel min-h-0 min-w-0 rounded-lg border ${fullBleed ? belowDesktop() ? "hidden" : "order-first flex h-full w-full flex-col overflow-hidden" : paletteExpanded() ? "order-2 flex w-[260px] max-w-[260px] flex-col overflow-hidden" : "order-2 flex w-[88px] max-w-[88px] flex-col overflow-hidden"}`)}><div data-result-sheet-palette-header=""${attr_class(`flex min-h-11 shrink-0 items-center ${paletteRailOpen() ? "justify-center" : "justify-between"} border-studio-border gap-2 border-b p-2.5`)}>`);
    if (paletteExpanded()) {
      $$renderer2.push("<!--[0-->");
      $$renderer2.push(`<div class="min-w-0"><h3 id="result-sheet-component-palette-title" class="truncate text-xs font-bold">Add components</h3> <p class="text-studio-panel-muted-foreground mt-0.5 text-[10px]">${escape_html(paletteCountLabel())}</p></div>`);
    } else {
      $$renderer2.push("<!--[-1-->");
      $$renderer2.push(`<span class="sr-only">Add components</span>`);
    }
    $$renderer2.push(`<!--]--> `);
    if (paletteRailOpen()) {
      $$renderer2.push("<!--[0-->");
      $$renderer2.push(`<button type="button" data-result-sheet-add-component="" class="border-studio-border bg-studio-panel-muted text-studio-panel-foreground hover:bg-studio-selection/50 focus-visible:ring-studio-focus inline-flex min-h-11 min-w-11 shrink-0 items-center justify-center gap-1.5 rounded-md border px-2 text-[10px] font-semibold focus-visible:ring-2 focus-visible:outline-none" aria-controls="result-sheet-component-palette" aria-expanded="false" aria-label="Add component" title="Add component">`);
      Plus($$renderer2, { class: "size-4", "aria-hidden": "true" });
      $$renderer2.push(`<!----> <span class="sr-only">Add component</span></button>`);
    } else {
      $$renderer2.push("<!--[-1-->");
      $$renderer2.push(`<button type="button" data-result-sheet-palette-collapse="" class="border-studio-border text-studio-panel-muted-foreground hover:bg-studio-panel-muted hover:text-studio-panel-foreground focus-visible:ring-studio-focus inline-flex min-h-11 min-w-11 shrink-0 items-center justify-center rounded-md border focus-visible:ring-2 focus-visible:outline-none" aria-controls="result-sheet-component-palette" aria-expanded="true"${attr("aria-label", belowDesktop() ? "Close Add components" : "Collapse Add components to rail")}${attr("title", belowDesktop() ? "Close Add components" : "Collapse Add components to rail")}>`);
      Chevron_left($$renderer2, { class: "size-4", "aria-hidden": "true" });
      $$renderer2.push(`<!----></button>`);
    }
    $$renderer2.push(`<!--]--></div> `);
    if (paletteExpanded()) {
      $$renderer2.push("<!--[0-->");
      $$renderer2.push(`<label class="relative block shrink-0 p-2.5"><span class="sr-only">Search component types</span> `);
      Search($$renderer2, {
        class: "text-studio-panel-muted-foreground pointer-events-none absolute top-1/2 left-5 size-3.5 -translate-y-1/2",
        "aria-hidden": "true"
      });
      $$renderer2.push(`<!----> <input${attr("value", paletteQuery)} type="search" class="border-studio-border bg-studio-panel-muted text-studio-panel-foreground focus-visible:ring-studio-focus min-h-11 w-full rounded-md border pr-2 pl-8 text-xs outline-none focus-visible:ring-2" placeholder="Search component types" aria-label="Search component types"/></label>`);
    } else {
      $$renderer2.push("<!--[-1-->");
    }
    $$renderer2.push(`<!--]--> <div${attr_class(`min-h-0 flex-1 overflow-y-auto p-2 ${paletteExpanded() ? "space-y-3" : "flex flex-col items-center gap-1.5"}`)}>`);
    if (paletteExpanded()) {
      $$renderer2.push("<!--[0-->");
      const each_array = ensure_array_like(paletteGroups());
      if (each_array.length !== 0) {
        $$renderer2.push("<!--[-->");
        for (let $$index_1 = 0, $$length = each_array.length; $$index_1 < $$length; $$index_1++) {
          let [category, entries] = each_array[$$index_1];
          $$renderer2.push(`<section class="space-y-1.5"${attr("aria-labelledby", `result-sheet-palette-category-${category}`)}><h4${attr("id", `result-sheet-palette-category-${category}`)} class="text-studio-panel-muted-foreground px-1 text-[10px] font-bold tracking-wider uppercase">${escape_html(category)}</h4> <!--[-->`);
          const each_array_1 = ensure_array_like(entries);
          for (let $$index = 0, $$length2 = each_array_1.length; $$index < $$length2; $$index++) {
            let [type, item] = each_array_1[$$index];
            const Icon2 = componentIcon(type);
            const descriptionId = `result-sheet-palette-description-${type}`;
            $$renderer2.push(`<button type="button"${attr("data-result-sheet-palette", type)}${attr("data-result-sheet-palette-state", paletteState)} draggable="true" class="group border-studio-border bg-studio-panel hover:border-studio-focus hover:bg-studio-selection/40 focus-visible:ring-studio-focus flex min-h-11 w-full touch-none items-start gap-2 rounded-md border p-2 text-left transition-colors focus-visible:ring-2 focus-visible:outline-none motion-reduce:transition-none"${attr("aria-label", item.label)}${attr("aria-describedby", descriptionId)}>`);
            if (Icon2) {
              $$renderer2.push("<!--[-->");
              Icon2($$renderer2, {
                class: "text-studio-focus mt-0.5 size-4 shrink-0",
                "aria-hidden": "true"
              });
              $$renderer2.push("<!--]-->");
            } else {
              $$renderer2.push("<!--[!-->");
              $$renderer2.push("<!--]-->");
            }
            $$renderer2.push(` <span${attr("id", descriptionId)}${attr("data-result-sheet-palette-description", type)} class="min-w-0"><span class="block text-[11px] leading-tight font-semibold">${escape_html(item.label)}</span> <span class="text-studio-panel-muted-foreground mt-0.5 block text-[9px] leading-snug">${escape_html(item.description)}</span></span></button>`);
          }
          $$renderer2.push(`<!--]--></section>`);
        }
      } else {
        $$renderer2.push("<!--[!-->");
        $$renderer2.push(`<p class="text-studio-panel-muted-foreground p-3 text-center text-[11px]">No component types match this search.</p>`);
      }
      $$renderer2.push(`<!--]-->`);
    } else {
      $$renderer2.push("<!--[-1-->");
      $$renderer2.push(`<!--[-->`);
      const each_array_2 = ensure_array_like(paletteEntries());
      for (let $$index_2 = 0, $$length = each_array_2.length; $$index_2 < $$length; $$index_2++) {
        let [type, item] = each_array_2[$$index_2];
        const Icon2 = componentIcon(type);
        $$renderer2.push(`<button type="button"${attr("data-result-sheet-palette", type)}${attr("data-result-sheet-palette-state", paletteState)} draggable="true" class="group border-studio-border bg-studio-panel hover:border-studio-focus hover:bg-studio-selection/40 focus-visible:ring-studio-focus flex min-h-11 min-w-11 touch-none items-center justify-center rounded-md border p-2 transition-colors focus-visible:ring-2 focus-visible:outline-none motion-reduce:transition-none"${attr("aria-label", item.label)}${attr("title", `${item.label}: ${item.description}`)}>`);
        if (Icon2) {
          $$renderer2.push("<!--[-->");
          Icon2($$renderer2, { class: "text-studio-focus size-4", "aria-hidden": "true" });
          $$renderer2.push("<!--]-->");
        } else {
          $$renderer2.push("<!--[!-->");
          $$renderer2.push("<!--]-->");
        }
        $$renderer2.push(`</button>`);
      }
      $$renderer2.push(`<!--]-->`);
    }
    $$renderer2.push(`<!--]--></div></aside> <aside id="result-sheet-inspector" tabindex="-1" role="complementary" data-result-sheet-panel="inspector"${attr("data-result-sheet-panel-state", inspectorPanelVisible() ? "expanded" : "closed")}${attr("data-result-sheet-panel-position", belowDesktop() ? "bottom-surface" : "desktop-rail")}${attr("data-result-sheet-panel-surface", belowDesktop() ? "bottom-surface" : "rail")}${attr("data-result-sheet-inspector-snap", void 0)}${attr_class(`border-studio-border bg-studio-panel flex min-h-0 min-w-0 flex-col space-y-3 rounded-lg border p-3 ${fullBleed ? belowDesktop() ? "hidden" : "order-3 h-full w-[330px] overflow-y-auto" : "order-3 lg:static lg:flex lg:flex-col"} ${inspectorPanelVisible() ? "flex" : "hidden"}`)}><div class="border-studio-border flex shrink-0 items-center justify-between border-b pb-2">`);
    if (belowDesktop() && mobileFocusMode) ;
    else {
      $$renderer2.push("<!--[-1-->");
    }
    $$renderer2.push(`<!--]--> <div role="tablist" aria-label="Studio panels" class="flex items-center gap-1"><span id="result-sheet-inspector-title" class="sr-only">Result sheet ${escape_html("inspector")} panel</span> <button type="button" role="tab"${attr_class(`focus-visible:ring-studio-focus rounded px-2 py-1 text-xs font-bold transition focus-visible:ring-2 focus-visible:outline-none ${"bg-studio-selection text-studio-selection-foreground"}`)}${attr("aria-selected", sidebarView === "inspector")}${attr("aria-pressed", sidebarView === "inspector")} aria-controls="result-sheet-inspector">Inspector</button> <button type="button" role="tab"${attr_class(`focus-visible:ring-studio-focus flex items-center gap-1 rounded px-2 py-1 text-xs font-bold transition focus-visible:ring-2 focus-visible:outline-none ${"text-studio-panel-muted-foreground hover:bg-studio-panel-muted"}`)}${attr("aria-selected", sidebarView === "layers")}${attr("aria-pressed", sidebarView === "layers")} aria-controls="result-sheet-inspector">`);
    Layers($$renderer2, { class: "size-3" });
    $$renderer2.push(`<!----> <span>Layers</span> <span class="text-studio-panel-muted-foreground font-mono text-[10px]">(${escape_html(components().length)} placed)</span></button></div> <button type="button" data-result-sheet-panel-close="inspector" class="border-studio-border text-studio-panel-muted-foreground hover:bg-studio-panel-muted focus-visible:ring-studio-focus rounded border px-2 py-1 text-[10px] font-semibold focus-visible:ring-2 focus-visible:outline-none"${attr("aria-label", `Close ${"inspector"} panel`)}>Close</button></div> `);
    {
      $$renderer2.push("<!--[-1-->");
      if (selectedCount() > 1) {
        $$renderer2.push("<!--[0-->");
        $$renderer2.push(`<div class="space-y-3"><div class="bg-studio-success text-studio-success-foreground border-studio-success flex items-center justify-between rounded-lg border p-2.5"><div class="flex items-center gap-2"><span class="bg-studio-success-foreground size-2 rounded-full"></span> <span class="text-xs font-bold">${escape_html(selectedCount())} components selected</span></div> <div class="flex items-center gap-1"><button type="button" class="text-studio-success-foreground hover:bg-studio-panel rounded p-1" title="Duplicate selected (Ctrl+D)">`);
        Copy_plus($$renderer2, { class: "size-3.5" });
        $$renderer2.push(`<!----></button> <button type="button" class="text-studio-success-foreground hover:bg-studio-panel rounded p-1" title="Toggle lock (Ctrl+L)">`);
        Lock($$renderer2, { class: "size-3.5" });
        $$renderer2.push(`<!----></button> <button type="button" class="text-destructive hover:bg-destructive/10 rounded p-1" title="Delete selected (Delete)">`);
        Trash_2($$renderer2, { class: "size-3.5" });
        $$renderer2.push(`<!----></button></div></div> <div class="border-studio-border space-y-1.5 border-t pt-2"><span class="text-studio-panel-muted-foreground block text-[10px] font-bold tracking-wider uppercase">Align edges</span> <div class="border-studio-border bg-studio-panel-muted flex items-center justify-between gap-1 rounded-lg border p-1"><button type="button" class="text-studio-panel-muted-foreground hover:bg-studio-panel flex-1 rounded p-1 hover:shadow-sm" title="Align left" aria-label="Align left">`);
        Align_horizontal_justify_start($$renderer2, { class: "mx-auto size-3.5" });
        $$renderer2.push(`<!----></button> <button type="button" class="text-studio-panel-muted-foreground hover:bg-studio-panel flex-1 rounded p-1 hover:shadow-sm" title="Align horizontal center" aria-label="Align horizontal center">`);
        Align_horizontal_justify_center($$renderer2, { class: "mx-auto size-3.5" });
        $$renderer2.push(`<!----></button> <button type="button" class="text-studio-panel-muted-foreground hover:bg-studio-panel flex-1 rounded p-1 hover:shadow-sm" title="Align right" aria-label="Align right">`);
        Align_horizontal_justify_end($$renderer2, { class: "mx-auto size-3.5" });
        $$renderer2.push(`<!----></button> <div class="bg-studio-border h-4 w-px"></div> <button type="button" class="text-studio-panel-muted-foreground hover:bg-studio-panel flex-1 rounded p-1 hover:shadow-sm" title="Align top" aria-label="Align top">`);
        Align_vertical_justify_start($$renderer2, { class: "mx-auto size-3.5" });
        $$renderer2.push(`<!----></button> <button type="button" class="text-studio-panel-muted-foreground hover:bg-studio-panel flex-1 rounded p-1 hover:shadow-sm" title="Align vertical middle" aria-label="Align vertical middle">`);
        Align_vertical_justify_center($$renderer2, { class: "mx-auto size-3.5" });
        $$renderer2.push(`<!----></button> <button type="button" class="text-studio-panel-muted-foreground hover:bg-studio-panel flex-1 rounded p-1 hover:shadow-sm" title="Align bottom" aria-label="Align bottom">`);
        Align_vertical_justify_end($$renderer2, { class: "mx-auto size-3.5" });
        $$renderer2.push(`<!----></button></div></div> `);
        if (selectedCount() >= 3) {
          $$renderer2.push("<!--[0-->");
          $$renderer2.push(`<div class="border-studio-border space-y-1.5 border-t pt-2"><span class="text-studio-panel-muted-foreground block text-[10px] font-bold tracking-wider uppercase">Distribute spacing</span> <div class="border-studio-border bg-studio-panel-muted flex items-center gap-1 rounded-lg border p-1"><button type="button" class="text-studio-panel-foreground hover:bg-studio-panel flex flex-1 items-center justify-center gap-1.5 rounded py-1 text-[10px] font-semibold hover:shadow-sm">`);
          Align_horizontal_distribute_center($$renderer2, { class: "size-3.5" });
          $$renderer2.push(`<!----> <span>Horizontal</span></button> <button type="button" class="text-studio-panel-foreground hover:bg-studio-panel flex flex-1 items-center justify-center gap-1.5 rounded py-1 text-[10px] font-semibold hover:shadow-sm">`);
          Align_vertical_distribute_center($$renderer2, { class: "size-3.5" });
          $$renderer2.push(`<!----> <span>Vertical</span></button></div></div>`);
        } else {
          $$renderer2.push("<!--[-1-->");
        }
        $$renderer2.push(`<!--]--></div>`);
      } else {
        $$renderer2.push("<!--[-1-->");
      }
      $$renderer2.push(`<!--]--> `);
      if (primaryComponent()) {
        $$renderer2.push("<!--[0-->");
        $$renderer2.push(`<div class="space-y-3"><div class="border-studio-border flex items-center justify-between gap-2 border-b pb-2"><div class="flex min-w-0 items-center gap-1.5"><span class="bg-studio-success size-2 rounded-full"></span> <h3 class="truncate text-xs font-bold">${escape_html(componentLabel(primaryComponent().type))}</h3> `);
        if (primaryComponent().locked) {
          $$renderer2.push("<!--[0-->");
          $$renderer2.push(`<span class="bg-studio-warning text-studio-warning-foreground rounded px-1.5 py-0.5 text-[9px] font-semibold">Locked</span>`);
        } else {
          $$renderer2.push("<!--[-1-->");
        }
        $$renderer2.push(`<!--]--></div> <div class="flex items-center gap-0.5"><button type="button" class="text-studio-panel-foreground/80 hover:bg-studio-panel-muted rounded p-1" title="Duplicate (Ctrl+D)">`);
        Copy_plus($$renderer2, { class: "size-3.5" });
        $$renderer2.push(`<!----></button> <button type="button" class="text-studio-panel-foreground/80 hover:bg-studio-panel-muted rounded p-1"${attr("title", primaryComponent().locked ? "Unlock component" : "Lock component")}>`);
        if (primaryComponent().locked) {
          $$renderer2.push("<!--[0-->");
          Lock($$renderer2, { class: "text-studio-warning-foreground size-3.5" });
        } else {
          $$renderer2.push("<!--[-1-->");
          Lock_open($$renderer2, { class: "size-3.5" });
        }
        $$renderer2.push(`<!--]--></button> <button type="button" class="text-studio-panel-foreground hover:bg-destructive/10 hover:text-destructive rounded p-1" title="Delete component (Delete)">`);
        Trash_2($$renderer2, { class: "size-3.5" });
        $$renderer2.push(`<!----></button></div></div> <div class="grid grid-cols-2 gap-2">`);
        NumericControl($$renderer2, {
          label: "X position",
          min: 0,
          max: Math.max(0, printableWidth() - Number(primaryComponent().w)),
          step: 0.5,
          unit: "mm",
          disabled: primaryIsLocked(),
          value: Number(primaryComponent().x),
          oninput: (val) => updateComponentCoalesced(primaryComponent().id, { x: val }, "x", "Move X"),
          oncommit: (val) => updateComponentCoalesced(primaryComponent().id, { x: val }, "x", "Move X")
        });
        $$renderer2.push(`<!----> `);
        NumericControl($$renderer2, {
          label: "Y position",
          min: 0,
          max: Math.max(0, printableHeight() - Number(primaryComponent().h)),
          step: 0.5,
          unit: "mm",
          disabled: primaryIsLocked(),
          value: Number(primaryComponent().y),
          oninput: (val) => updateComponentCoalesced(primaryComponent().id, { y: val }, "y", "Move Y"),
          oncommit: (val) => updateComponentCoalesced(primaryComponent().id, { y: val }, "y", "Move Y")
        });
        $$renderer2.push(`<!----> `);
        NumericControl($$renderer2, {
          label: "Width",
          min: 20,
          max: printableWidth(),
          step: 0.5,
          unit: "mm",
          disabled: primaryIsLocked(),
          value: Number(primaryComponent().w),
          oninput: (val) => updateComponentCoalesced(primaryComponent().id, { w: val }, "w", "Resize width"),
          oncommit: (val) => updateComponentCoalesced(primaryComponent().id, { w: val }, "w", "Resize width")
        });
        $$renderer2.push(`<!----> `);
        NumericControl($$renderer2, {
          label: "Height",
          min: 8,
          max: printableHeight(),
          step: 0.5,
          unit: "mm",
          disabled: primaryIsLocked(),
          value: Number(primaryComponent().h),
          oninput: (val) => updateComponentCoalesced(primaryComponent().id, { h: val }, "h", "Resize height"),
          oncommit: (val) => updateComponentCoalesced(primaryComponent().id, { h: val }, "h", "Resize height")
        });
        $$renderer2.push(`<!----></div> <button type="button"${attr("disabled", primaryIsLocked(), true)} class="border-studio-success bg-studio-success/30 text-studio-success-foreground hover:bg-studio-success/50 w-full rounded border px-2 py-1.5 text-[10px] font-semibold transition-colors disabled:pointer-events-none disabled:opacity-40 motion-reduce:transition-none">Fit height to content</button> `);
        if (primaryComponent().type === "field" || primaryComponent().type === "signature") {
          $$renderer2.push("<!--[0-->");
          $$renderer2.push(`<button type="button"${attr("disabled", primaryIsLocked(), true)} class="border-studio-success bg-studio-success/30 text-studio-success-foreground hover:bg-studio-success/50 flex w-full items-center justify-between rounded-md border px-2.5 py-2 text-left text-xs font-semibold transition-colors disabled:pointer-events-none disabled:opacity-40 motion-reduce:transition-none"><span>${escape_html(primaryComponent().props?.binding ? `{{${primaryComponent().props.binding}}}` : "Choose binding")}</span> `);
          Copy($$renderer2, { class: "size-3.5" });
          $$renderer2.push(`<!----></button>`);
        } else {
          $$renderer2.push("<!--[-1-->");
        }
        $$renderer2.push(`<!--]--> `);
        if (primaryComponent().type === "image" && allowNoticeDocumentImages) {
          $$renderer2.push("<!--[0-->");
          $$renderer2.push(`<label class="block text-[10px]">Verified document image `);
          $$renderer2.select(
            {
              disabled: readOnly || primaryIsLocked() || noticeImageUploading,
              class: "border-studio-border bg-studio-panel text-studio-panel-foreground mt-1 w-full rounded border px-1.5 py-1 text-xs disabled:opacity-70",
              value: primaryComponent().props?.asset_id ?? "",
              onchange: (event) => {
                const asset = noticeDocumentImageAssets.find((item) => item.asset_id === event.currentTarget.value);
                if (asset) updateSelectedProps({ asset_id: asset.asset_id, src: asset.src }, "Choose verified document image");
              }
            },
            ($$renderer3) => {
              if (!noticeDocumentImageAssets.some((item) => item.asset_id === primaryComponent().props?.asset_id)) {
                $$renderer3.push("<!--[0-->");
                $$renderer3.option({ value: "" }, ($$renderer4) => {
                  $$renderer4.push(`Choose a verified image`);
                });
              } else {
                $$renderer3.push("<!--[-1-->");
              }
              $$renderer3.push(`<!--]--><!--[-->`);
              const each_array_4 = ensure_array_like(noticeDocumentImageAssets);
              for (let $$index_4 = 0, $$length = each_array_4.length; $$index_4 < $$length; $$index_4++) {
                let asset = each_array_4[$$index_4];
                $$renderer3.option({ value: asset.asset_id }, ($$renderer4) => {
                  $$renderer4.push(`${escape_html(asset.label ?? `Image ${asset.asset_id.slice(0, 8)}`)}`);
                });
              }
              $$renderer3.push(`<!--]-->`);
            }
          );
          $$renderer2.push(`</label> <label class="block text-[10px]">Alternative text<input class="border-studio-border bg-studio-panel text-studio-panel-foreground mt-1 w-full rounded border px-1.5 py-1 text-xs"${attr("disabled", primaryIsLocked(), true)}${attr("value", primaryComponent().props?.alt ?? "")}/></label> `);
          if (onNoticeDocumentImageUpload) {
            $$renderer2.push("<!--[0-->");
            $$renderer2.push(`<label class="block text-[10px]">Upload verified PNG/JPEG (up to 2 MiB) <input aria-label="Upload image for selected document component" type="file" accept="image/png,image/jpeg,.png,.jpg,.jpeg"${attr("disabled", readOnly || primaryIsLocked() || noticeImageUploading, true)} class="mt-1 block w-full min-w-0 rounded border p-1 text-xs"/></label>`);
          } else {
            $$renderer2.push("<!--[-1-->");
          }
          $$renderer2.push(`<!--]--> `);
          {
            $$renderer2.push("<!--[-1-->");
          }
          $$renderer2.push(`<!--]--> `);
          {
            $$renderer2.push("<!--[-1-->");
          }
          $$renderer2.push(`<!--]--> `);
          if (!noticeDocumentImageAssets.some((asset) => asset.asset_id === primaryComponent().props?.asset_id)) {
            $$renderer2.push("<!--[0-->");
            $$renderer2.push(`<p role="alert" class="text-xs">This image is unverified. Choose or upload a same-family verified image, or remove it before checking the PDF.</p>`);
          } else {
            $$renderer2.push("<!--[-1-->");
          }
          $$renderer2.push(`<!--]-->`);
        } else {
          $$renderer2.push("<!--[-1-->");
        }
        $$renderer2.push(`<!--]--> `);
        if (["dynamic_image", "dynamic_qr"].includes(primaryComponent().type)) {
          $$renderer2.push("<!--[0-->");
          $$renderer2.push(`<label class="block text-[10px]">Data binding <input${attr("disabled", primaryIsLocked(), true)} class="border-studio-border bg-studio-panel text-studio-panel-foreground disabled:bg-studio-panel-muted mt-1 w-full rounded border px-1.5 py-1 text-xs disabled:opacity-70"${attr("value", primaryComponent().props?.binding ?? "")}/></label> `);
          if (primaryComponent().type === "dynamic_image") {
            $$renderer2.push("<!--[0-->");
            $$renderer2.push(`<label class="block text-[10px]">Fit `);
            $$renderer2.select(
              {
                disabled: primaryIsLocked(),
                class: "border-studio-border bg-studio-panel text-studio-panel-foreground disabled:bg-studio-panel-muted mt-1 w-full rounded border px-1.5 py-1 text-xs disabled:opacity-70",
                value: primaryComponent().props?.fit ?? "cover",
                onchange: (event) => updateSelectedProps({ fit: event.currentTarget.value }, "Update image fit")
              },
              ($$renderer3) => {
                $$renderer3.option({ value: "cover" }, ($$renderer4) => {
                  $$renderer4.push(`Cover`);
                });
                $$renderer3.option({ value: "contain" }, ($$renderer4) => {
                  $$renderer4.push(`Contain`);
                });
                $$renderer3.option({ value: "fill" }, ($$renderer4) => {
                  $$renderer4.push(`Fill`);
                });
              }
            );
            $$renderer2.push(`</label> <label class="block text-[10px]">Fallback `);
            $$renderer2.select(
              {
                disabled: primaryIsLocked(),
                class: "border-studio-border bg-studio-panel text-studio-panel-foreground disabled:bg-studio-panel-muted mt-1 w-full rounded border px-1.5 py-1 text-xs disabled:opacity-70",
                value: primaryComponent().props?.fallback ?? "affix_box",
                onchange: (event) => updateSelectedProps({ fallback: event.currentTarget.value }, "Update image fallback")
              },
              ($$renderer3) => {
                $$renderer3.option({ value: "affix_box" }, ($$renderer4) => {
                  $$renderer4.push(`Affix box`);
                });
              }
            );
            $$renderer2.push(`</label>`);
          } else {
            $$renderer2.push("<!--[-1-->");
          }
          $$renderer2.push(`<!--]-->`);
        } else {
          $$renderer2.push("<!--[-1-->");
        }
        $$renderer2.push(`<!--]--> `);
        if (["text", "note", "footer"].includes(primaryComponent().type)) {
          $$renderer2.push("<!--[0-->");
          $$renderer2.push(`<textarea${attr("disabled", primaryIsLocked(), true)} class="border-studio-border bg-studio-panel text-studio-panel-foreground disabled:bg-studio-panel-muted min-h-20 w-full rounded border p-2 text-xs disabled:opacity-70">`);
          const $$body = escape_html(primaryComponent().props?.text ?? "");
          if ($$body) {
            $$renderer2.push(`${$$body}`);
          }
          $$renderer2.push(`</textarea>`);
        } else {
          $$renderer2.push("<!--[-1-->");
        }
        $$renderer2.push(`<!--]--> `);
        if (primaryComponent().type === "header") {
          $$renderer2.push("<!--[0-->");
          $$renderer2.push(`<label class="block text-[10px]">Title <input${attr("disabled", primaryIsLocked(), true)} class="border-studio-border bg-studio-panel text-studio-panel-foreground disabled:bg-studio-panel-muted mt-1 w-full rounded border px-1.5 py-1 text-xs disabled:opacity-70"${attr("value", primaryComponent().props?.title ?? "")}/></label> <label class="block text-[10px]">Subtitle <input${attr("disabled", primaryIsLocked(), true)} class="border-studio-border bg-studio-panel text-studio-panel-foreground disabled:bg-studio-panel-muted mt-1 w-full rounded border px-1.5 py-1 text-xs disabled:opacity-70"${attr("value", primaryComponent().props?.subtitle ?? "")}/></label>`);
        } else {
          $$renderer2.push("<!--[-1-->");
        }
        $$renderer2.push(`<!--]--> `);
        if (textParts().length > 1) {
          $$renderer2.push("<!--[0-->");
          $$renderer2.push(`<label class="text-studio-panel-muted-foreground block text-[10px] font-semibold">Text part `);
          $$renderer2.select(
            {
              class: "border-studio-border bg-studio-panel text-studio-panel-foreground mt-1 w-full rounded border px-2 py-1 text-xs",
              value: activeTextPart(),
              onchange: (event) => selectedTextPart = event.currentTarget.value
            },
            ($$renderer3) => {
              $$renderer3.push(`<!--[-->`);
              const each_array_5 = ensure_array_like(textParts());
              for (let $$index_5 = 0, $$length = each_array_5.length; $$index_5 < $$length; $$index_5++) {
                let part = each_array_5[$$index_5];
                $$renderer3.option({ value: part.value }, ($$renderer4) => {
                  $$renderer4.push(`${escape_html(part.label)}`);
                });
              }
              $$renderer3.push(`<!--]-->`);
            }
          );
          $$renderer2.push(`</label>`);
        } else {
          $$renderer2.push("<!--[-1-->");
        }
        $$renderer2.push(`<!--]--> `);
        ResultSheetTypographyControls($$renderer2, {
          typography: activeTextStyle(),
          onChanged: updateSelectedTypography
        });
        $$renderer2.push(`<!----> `);
        ResultSheetAppearanceControls($$renderer2, {
          appearance: primaryComponent().props?.appearance ?? {},
          spacing: primaryComponent().props?.spacing ?? {},
          onAppearanceChanged: updateSelectedAppearance,
          onSpacingChanged: updateSelectedSpacing
        });
        $$renderer2.push(`<!----></div>`);
      } else {
        $$renderer2.push("<!--[-1-->");
        $$renderer2.push(`<div class="border-studio-border text-studio-panel-muted-foreground rounded-lg border border-dashed p-5 text-center text-xs leading-relaxed">Select one or more components to inspect geometry,
                            alignment, bindings, and appearance.</div>`);
      }
      $$renderer2.push(`<!--]-->`);
    }
    $$renderer2.push(`<!--]--> <div class="border-studio-border border-t pt-3">`);
    NumericControl($$renderer2, {
      label: "Page margin",
      min: 5,
      max: 30,
      step: 1,
      unit: "mm",
      value: page().margin_mm,
      oninput: (val) => updatePageCoalesced("margin_mm", val, "Update margin"),
      oncommit: (val) => updatePageCoalesced("margin_mm", val, "Update margin")
    });
    $$renderer2.push(`<!----> <div class="text-studio-panel-muted-foreground mt-1 flex justify-between text-[10px]"><span>Page</span> <span class="font-mono">${escape_html(page().paper_size)} · ${escape_html(page().orientation)} · ${escape_html(page().margin_mm)}mm</span></div></div> `);
    if (diagnostics().length) {
      $$renderer2.push("<!--[0-->");
      $$renderer2.push(`<div class="border-studio-warning bg-studio-warning text-studio-warning-foreground rounded-md border p-2 text-[10px] leading-relaxed"><strong>${escape_html(diagnostics().length)} verification warning${escape_html(diagnostics().length === 1 ? "" : "s")}</strong> <ul class="mt-1 list-disc pl-4"><!--[-->`);
      const each_array_6 = ensure_array_like(diagnostics().slice(0, 4));
      for (let $$index_6 = 0, $$length = each_array_6.length; $$index_6 < $$length; $$index_6++) {
        let diagnostic = each_array_6[$$index_6];
        $$renderer2.push(`<li>${escape_html(diagnostic)}</li>`);
      }
      $$renderer2.push(`<!--]--></ul></div>`);
    } else {
      $$renderer2.push("<!--[-1-->");
    }
    $$renderer2.push(`<!--]--> <button type="button" class="border-studio-success bg-studio-success/30 text-studio-success-foreground hover:bg-studio-success/50 w-full rounded-md border px-3 py-2 text-xs font-semibold transition-colors motion-reduce:transition-none">Open field picker</button></aside></div> <div role="region" aria-label="Visual document canvas" tabindex="-1"${attr("data-result-sheet-safe-top", canvasSafeTop)}${attr("data-result-sheet-safe-bottom", canvasReservedBottom())}${attr("data-result-sheet-safe-left", canvasSafeLeft())}${attr("data-result-sheet-safe-right", canvasSafeRight)}${attr("data-result-sheet-fit-invalidation", fitInvalidation)}${attr_class(`border-studio-border bg-studio-canvas text-studio-canvas-foreground order-1 min-h-0 min-w-0 flex-1 overscroll-contain rounded-lg border p-3 sm:p-5 ${"cursor-crosshair"} ${fullBleed ? "relative order-first min-h-0 w-full min-w-0 flex-1 basis-0 overflow-auto rounded-2xl lg:order-none lg:w-auto" : "h-[min(65vh,640px)] min-h-[380px] overflow-auto sm:h-[min(76vh,760px)] sm:min-h-[520px]"}`)}><div data-result-sheet-page-track="" class="flex min-w-0 items-start"><div aria-hidden="true" data-result-sheet-safe-top-inset="" class="pointer-events-none shrink-0"${attr_style(`height:${canvasSafeTop}px;`)}></div> <div aria-hidden="true" data-result-sheet-safe-left-inset="" class="pointer-events-none shrink-0"${attr_style(`width:${canvasSafeLeft()}px;`)}></div> <div data-result-sheet-page-frame="" class="relative shrink-0"${attr_style(pageFrameStyle())}><div class="bg-studio-sheet origin-top-left shadow-xl"${attr_style(pageStyle())}><div data-result-sheet-page="" class="relative h-full"${attr_style(`width:${page().width_mm}mm;height:${page().height_mm}mm;padding:${page().margin_mm}mm;`)}><!--[-->`);
    const each_array_7 = ensure_array_like(components());
    for (let index = 0, $$length = each_array_7.length; index < $$length; index++) {
      let component = each_array_7[index];
      const isComponentSelected = selectedIds.has(component.id);
      const isPrimary = primaryId === component.id;
      $$renderer2.push(`<button type="button"${attr("data-result-sheet-component", component.id)}${attr("data-component-id", legacyMode ? component.id : void 0)}${attr("aria-pressed", isComponentSelected)}${attr("aria-label", `${componentLabel(component.type)} component (${index + 1} of ${components().length})`)}${attr_class(`border-studio-border focus-visible:ring-studio-focus absolute overflow-hidden rounded border text-left leading-tight transition-colors select-none focus-visible:ring-2 focus-visible:outline-none motion-reduce:transition-none ${isComponentSelected ? legacyMode ? "border-studio-focus shadow-md ring-2 ring-indigo-500" : "ring-studio-focus border-studio-focus shadow-md ring-2" : "hover:border-studio-border"} ${isPrimary ? "ring-offset-studio-sheet ring-offset-1" : ""} ${component.locked ? "cursor-not-allowed border-dashed" : "cursor-move touch-none"}`)}${attr_style(componentStyle(component, void 0, page().margin_mm))}>`);
      ResultSheetComponentPreview($$renderer2, {
        component,
        sampleContext,
        noticeDocumentImagePreview: allowNoticeDocumentImages,
        noticeDocumentImageAssets,
        componentTemplate: localDocument.component_templates?.[component.type]?.html ?? ""
      });
      $$renderer2.push(`<!----> `);
      if (isComponentSelected && !component.locked && (selectedCount() === 1 || isPrimary)) {
        $$renderer2.push("<!--[0-->");
        $$renderer2.push(`<span class="bg-studio-focus ring-studio-sheet absolute top-0 left-0 z-30 size-3 cursor-nwse-resize rounded-br shadow ring-2" title="Resize component (NW)" aria-hidden="true"></span> <span class="bg-studio-focus ring-studio-sheet absolute top-0 right-0 z-30 size-3 cursor-nesw-resize rounded-bl shadow ring-2" title="Resize component (NE)" aria-hidden="true"></span> <span class="bg-studio-focus ring-studio-sheet absolute bottom-0 left-0 z-30 size-3 cursor-nesw-resize rounded-tr shadow ring-2" title="Resize component (SW)" aria-hidden="true"></span> <span class="bg-studio-focus ring-studio-sheet absolute right-0 bottom-0 z-30 size-3 cursor-nwse-resize rounded-tl shadow ring-2" title="Resize component (SE)" aria-hidden="true"></span>`);
      } else {
        $$renderer2.push("<!--[-1-->");
      }
      $$renderer2.push(`<!--]--> `);
      if (component.locked) {
        $$renderer2.push("<!--[0-->");
        $$renderer2.push(`<div class="bg-studio-shell/80 text-studio-shell-foreground absolute top-1 right-1 z-30 rounded p-0.5 shadow-sm" title="Locked">`);
        Lock($$renderer2, { class: "size-2.5" });
        $$renderer2.push(`<!----></div>`);
      } else {
        $$renderer2.push("<!--[-1-->");
      }
      $$renderer2.push(`<!--]--></button>`);
    }
    $$renderer2.push(`<!--]--> `);
    {
      $$renderer2.push("<!--[-1-->");
    }
    $$renderer2.push(`<!--]--> <!--[-->`);
    const each_array_8 = ensure_array_like(activeSnapGuides);
    for (let $$index_8 = 0, $$length = each_array_8.length; $$index_8 < $$length; $$index_8++) {
      let guide = each_array_8[$$index_8];
      if (guide.axis === "x") {
        $$renderer2.push("<!--[0-->");
        $$renderer2.push(`<div class="border-studio-focus pointer-events-none absolute top-0 bottom-0 z-40 border-l-2 border-dashed"${attr_style(`left:${guide.position + Number(page().margin_mm)}mm;`)}></div>`);
      } else {
        $$renderer2.push("<!--[-1-->");
        $$renderer2.push(`<div class="border-studio-focus pointer-events-none absolute right-0 left-0 z-40 border-t-2 border-dashed"${attr_style(`top:${guide.position + Number(page().margin_mm)}mm;`)}></div>`);
      }
      $$renderer2.push(`<!--]-->`);
    }
    $$renderer2.push(`<!--]--> `);
    if (components().length === 0) {
      $$renderer2.push("<!--[0-->");
      $$renderer2.push(`<div class="text-studio-canvas-foreground/70 flex h-full min-h-[260px] items-center justify-center text-center text-xs">Drop a component here<br/>or choose one
                                    from the palette.</div>`);
    } else {
      $$renderer2.push("<!--[-1-->");
    }
    $$renderer2.push(`<!--]--></div></div></div> <div aria-hidden="true" data-result-sheet-safe-right-inset="" class="pointer-events-none shrink-0"${attr_style(`width:${canvasSafeRight}px;`)}></div></div> <div aria-hidden="true" data-result-sheet-safe-inset="" class="pointer-events-none shrink-0"${attr_style(`height:${canvasReservedBottom()}px;`)}></div></div></div> `);
    if (fullBleed && belowDesktop() && !mobileFocusMode) {
      $$renderer2.push("<!--[0-->");
      $$renderer2.push(`<nav data-result-sheet-command-dock="" class="border-studio-border bg-studio-panel grid w-full shrink-0 grid-cols-3 border-t p-1.5 pb-[calc(0.375rem+env(safe-area-inset-bottom))]" aria-label="Studio commands"><button type="button" data-result-sheet-command="add" class="text-studio-panel-foreground hover:bg-studio-panel-muted focus-visible:ring-studio-focus flex min-h-11 flex-col items-center justify-center gap-0.5 rounded-md px-2 py-1 text-[10px] font-semibold transition-colors focus-visible:ring-2 focus-visible:outline-none" aria-controls="result-sheet-component-palette"${attr("aria-expanded", activeMobilePanel === "add")}${attr("aria-pressed", activeMobilePanel === "add")}>`);
      Plus($$renderer2, { class: "size-4", "aria-hidden": "true" });
      $$renderer2.push(`<!----> <span>Add</span></button> <button type="button" data-result-sheet-command="layers" class="text-studio-panel-foreground hover:bg-studio-panel-muted focus-visible:ring-studio-focus flex min-h-11 flex-col items-center justify-center gap-0.5 rounded-md px-2 py-1 text-[10px] font-semibold transition-colors focus-visible:ring-2 focus-visible:outline-none disabled:pointer-events-none disabled:opacity-40" aria-controls="result-sheet-inspector"${attr("aria-expanded", activeMobilePanel === "layers")}${attr("aria-pressed", activeMobilePanel === "layers")}${attr("disabled", components().length === 0, true)}>`);
      Layers($$renderer2, { class: "size-4", "aria-hidden": "true" });
      $$renderer2.push(`<!----> <span>Layers</span></button> <button type="button" data-result-sheet-command="inspector" class="text-studio-panel-foreground hover:bg-studio-panel-muted focus-visible:ring-studio-focus flex min-h-11 flex-col items-center justify-center gap-0.5 rounded-md px-2 py-1 text-[10px] font-semibold transition-colors focus-visible:ring-2 focus-visible:outline-none disabled:pointer-events-none disabled:opacity-40" aria-controls="result-sheet-inspector"${attr("aria-expanded", activeMobilePanel === "inspector")}${attr("aria-pressed", activeMobilePanel === "inspector")}${attr("disabled", selectedCount() === 0, true)}>`);
      Sliders_horizontal($$renderer2, { class: "size-4", "aria-hidden": "true" });
      $$renderer2.push(`<!----> <span>Inspector</span></button></nav>`);
    } else {
      $$renderer2.push("<!--[-1-->");
    }
    $$renderer2.push(`<!--]--></div>`);
  });
}
function PlaceholderPicker($$renderer, $$props) {
  $$renderer.component(($$renderer2) => {
    let {
      open = false,
      placeholderGroups = {},
      usedKeys = [],
      recentKeys = [],
      mode = "copy",
      onclose = () => {
      },
      oncopy = () => {
      },
      oninsert = () => {
      },
      embedded = false,
      missingKeys = []
    } = $$props;
    let query = "";
    let category = "all";
    const groupKeys = derived(() => Object.keys(placeholderGroups).filter((key) => key !== "dual"));
    const categories = derived(() => [
      { key: "all", label: "All fields" },
      ...groupKeys().map((key) => ({
        key,
        label: key === "aliases" ? "Legacy aliases" : labelFor(key)
      }))
    ]);
    const allItems = derived(() => groupKeys().flatMap((key) => placeholderGroups[key] ?? []));
    const missingItems = derived(() => missingKeys.map((key) => allItems().find((item) => keyFor(item) === key) ?? {
      placeholder: key,
      description: "Required field detected as missing"
    }));
    const items = derived(() => {
      const allKeys = groupKeys().filter((key) => key !== "aliases");
      const source = allKeys.flatMap((key) => placeholderGroups[key] ?? []);
      const needle = query.trim().toLowerCase();
      const filtered = source.filter((item) => {
        if (!needle) return true;
        const aliasText = Array.isArray(item.aliases) ? item.aliases.join(" ") : "";
        return `${item.placeholder} ${item.description ?? ""} ${aliasText} ${labelFor(item.placeholder)}`.toLowerCase().includes(needle);
      });
      return [
        ...new Map(filtered.map((item) => [keyFor(item), item])).values()
      ];
    });
    function labelFor(value) {
      return String(value ?? "").replace(/^\{\{|\}\}$/g, "").replace(/_/g, " ").replace(/\b\w/g, (letter) => letter.toUpperCase());
    }
    function keyFor(item) {
      return String(item.placeholder ?? item.value ?? "").replace(/^\{\{|\}\}$/g, "");
    }
    function macroFor(item) {
      return `{{${keyFor(item)}}}`;
    }
    function isUsed(item) {
      return usedKeys.includes(keyFor(item));
    }
    function isRecent(item) {
      return recentKeys.includes(keyFor(item));
    }
    if (open) {
      $$renderer2.push("<!--[0-->");
      $$renderer2.push(`<div${attr_class(clsx(embedded ? "h-full w-full" : "fixed inset-0 z-[80] flex items-center justify-center bg-studio-overlay p-4 backdrop-blur-[2px]"))}${attr("role", embedded ? void 0 : "presentation")}><section${attr_class(`flex flex-col overflow-hidden border border-studio-border bg-studio-panel ${embedded ? "h-full max-h-full w-full max-w-none rounded-xl shadow-none" : "max-h-[min(760px,calc(100vh-2rem))] w-full max-w-4xl rounded-2xl shadow-2xl"}`)} role="dialog"${attr("aria-modal", embedded ? void 0 : "true")} aria-labelledby="placeholder-picker-title"><header class="flex items-start justify-between gap-4 border-b border-studio-border px-5 py-4"><div class="min-w-0"><p class="text-[10px] font-bold uppercase tracking-[0.18em] text-muted-foreground">Template fields</p> <h2 id="placeholder-picker-title" class="mt-1 text-lg font-semibold text-foreground">${escape_html(mode === "insert" ? "Insert a field" : "Copy a placeholder")}</h2> <p class="mt-1 text-xs text-muted-foreground">${escape_html(embedded ? "Copy exact fields into Word or LibreOffice before uploading the document." : "Use human labels first. The exact binding is copied or inserted unchanged.")}</p></div> `);
      if (!embedded) {
        $$renderer2.push("<!--[0-->");
        $$renderer2.push(`<button type="button" class="rounded-lg p-2 text-studio-panel-muted-foreground hover:bg-studio-panel-muted hover:text-studio-panel-foreground" aria-label="Close placeholder picker">`);
        X($$renderer2, { class: "size-4" });
        $$renderer2.push(`<!----></button>`);
      } else {
        $$renderer2.push("<!--[-1-->");
      }
      $$renderer2.push(`<!--]--></header> <div class="grid min-h-0 flex-1 lg:grid-cols-[minmax(0,1fr)_220px]"><div class="flex min-h-0 flex-col border-b border-studio-border lg:border-b-0 lg:border-r"><div class="space-y-3 px-5 py-4"><label class="relative block"><span class="sr-only">Search placeholders</span> `);
      Search($$renderer2, {
        class: "pointer-events-none absolute left-3 top-1/2 size-4 -translate-y-1/2 text-muted-foreground"
      });
      $$renderer2.push(`<!----> <input${attr("value", query)} class="min-h-10 w-full rounded-lg border border-studio-border bg-studio-panel-muted pl-9 pr-3 text-sm text-studio-panel-foreground outline-none ring-offset-studio-panel focus-visible:ring-2 focus-visible:ring-studio-focus" placeholder="Search applicant, score, exam date…"/></label> <div class="flex gap-1.5 overflow-x-auto pb-1" role="tablist" aria-label="Placeholder categories"><!--[-->`);
      const each_array = ensure_array_like(categories());
      for (let $$index = 0, $$length = each_array.length; $$index < $$length; $$index++) {
        let item = each_array[$$index];
        $$renderer2.push(`<button type="button" role="tab"${attr("aria-selected", category === item.key)}${attr_class(`shrink-0 rounded-full border px-3 py-1.5 text-xs font-medium transition-colors motion-reduce:transition-none ${category === item.key ? "border-studio-focus bg-studio-selection text-studio-selection-foreground" : "border-studio-border bg-studio-panel-muted text-studio-panel-muted-foreground hover:bg-studio-panel"}`)}>${escape_html(item.label)}</button>`);
      }
      $$renderer2.push(`<!--]--></div></div> <div class="min-h-0 flex-1 overflow-y-auto px-5 pb-5">`);
      if (missingItems().length) {
        $$renderer2.push("<!--[0-->");
        $$renderer2.push(`<div class="mb-4 space-y-2 rounded-xl border border-studio-warning bg-studio-warning p-3" role="status"><div class="flex items-center gap-2 text-xs font-black text-studio-warning-foreground">`);
        Triangle_alert($$renderer2, { class: "size-3.5" });
        $$renderer2.push(`<!----> Missing required fields</div><p class="text-[10px] leading-relaxed text-studio-warning-foreground/90">Copy these into Word, upload the corrected document, then analyze again.</p><!--[-->`);
        const each_array_1 = ensure_array_like(missingItems());
        for (let $$index_1 = 0, $$length = each_array_1.length; $$index_1 < $$length; $$index_1++) {
          let item = each_array_1[$$index_1];
          const key = keyFor(item);
          $$renderer2.push(`<div class="flex items-center gap-3 rounded-lg border border-studio-warning/50 bg-studio-panel px-3 py-2"><div class="min-w-0 flex-1"><span class="block truncate text-sm font-semibold text-foreground">${escape_html(labelFor(key))}</span><code class="block truncate text-[11px] text-muted-foreground">${escape_html(macroFor(item))}</code></div><button type="button" class="inline-flex min-h-9 shrink-0 items-center gap-1.5 rounded-lg bg-studio-warning-foreground px-3 text-xs font-semibold text-studio-warning hover:bg-studio-warning-foreground/90">`);
          Clipboard($$renderer2, { class: "size-3.5" });
          $$renderer2.push(`<!----> Copy</button></div>`);
        }
        $$renderer2.push(`<!--]--></div>`);
      } else {
        $$renderer2.push("<!--[-1-->");
      }
      $$renderer2.push(`<!--]--> `);
      if (items().length === 0) {
        $$renderer2.push("<!--[0-->");
        $$renderer2.push(`<div class="rounded-xl border border-dashed border-border p-8 text-center text-sm text-muted-foreground">No matching fields.</div>`);
      } else {
        $$renderer2.push("<!--[-1-->");
        $$renderer2.push(`<div class="space-y-2"><!--[-->`);
        const each_array_2 = ensure_array_like(items());
        for (let $$index_3 = 0, $$length = each_array_2.length; $$index_3 < $$length; $$index_3++) {
          let item = each_array_2[$$index_3];
          const key = keyFor(item);
          $$renderer2.push(`<div${attr_class(`flex items-center gap-3 rounded-xl border px-3 py-3 transition-colors motion-reduce:transition-none ${isUsed(item) ? "border-studio-success/80 bg-studio-success/30" : "border-studio-border/70 bg-studio-panel hover:bg-studio-panel-muted/50"}`)}><div class="min-w-0 flex-1"><div class="flex items-center gap-2"><span class="truncate text-sm font-semibold text-foreground">${escape_html(labelFor(key))}</span> `);
          if (isUsed(item)) {
            $$renderer2.push("<!--[0-->");
            Check($$renderer2, {
              class: "size-3.5 shrink-0 text-studio-success-foreground",
              "aria-label": "Used in template"
            });
          } else {
            $$renderer2.push("<!--[-1-->");
          }
          $$renderer2.push(`<!--]--> `);
          if (isRecent(item)) {
            $$renderer2.push("<!--[0-->");
            Clock_3($$renderer2, {
              class: "size-3.5 shrink-0 text-studio-focus",
              "aria-label": "Recently copied"
            });
          } else {
            $$renderer2.push("<!--[-1-->");
          }
          $$renderer2.push(`<!--]--></div> <p class="mt-0.5 truncate text-xs text-muted-foreground">${escape_html(item.description || "Template binding")}</p> <code class="mt-1 block truncate text-[11px] text-muted-foreground/80">${escape_html(macroFor(item))}</code> `);
          if (item.aliases && item.aliases.length > 0) {
            $$renderer2.push("<!--[0-->");
            $$renderer2.push(`<div class="mt-1.5 flex flex-wrap items-center gap-1"><span class="text-[10px] text-muted-foreground/80 font-medium">Short:</span> <!--[-->`);
            const each_array_3 = ensure_array_like(item.aliases);
            for (let $$index_2 = 0, $$length2 = each_array_3.length; $$index_2 < $$length2; $$index_2++) {
              let alias = each_array_3[$$index_2];
              $$renderer2.push(`<button type="button" class="font-mono text-[10px] bg-studio-panel-muted hover:bg-studio-panel text-studio-panel-foreground px-1.5 py-0.5 rounded border border-studio-border hover:border-studio-focus transition-colors"${attr("title", `Copy {{${alias}}}`)}>${escape_html(`{{${alias}}}`)}</button>`);
            }
            $$renderer2.push(`<!--]--></div>`);
          } else {
            $$renderer2.push("<!--[-1-->");
          }
          $$renderer2.push(`<!--]--></div> `);
          if (mode === "insert") {
            $$renderer2.push("<!--[0-->");
            $$renderer2.push(`<button type="button" class="inline-flex min-h-9 shrink-0 items-center gap-1.5 rounded-lg bg-primary px-3 text-xs font-semibold text-primary-foreground hover:bg-primary/90">`);
            Plus($$renderer2, { class: "size-3.5" });
            $$renderer2.push(`<!----> Insert</button>`);
          } else {
            $$renderer2.push("<!--[-1-->");
            $$renderer2.push(`<button type="button" class="inline-flex min-h-9 shrink-0 items-center gap-1.5 rounded-lg border border-studio-border bg-studio-panel px-3 text-xs font-semibold text-studio-panel-foreground hover:bg-studio-panel-muted">`);
            Clipboard($$renderer2, { class: "size-3.5" });
            $$renderer2.push(`<!----> Copy</button>`);
          }
          $$renderer2.push(`<!--]--></div>`);
        }
        $$renderer2.push(`<!--]--></div>`);
      }
      $$renderer2.push(`<!--]--></div></div> <aside class="min-h-0 overflow-y-auto bg-studio-muted-panel px-5 py-4"><div><div class="flex items-center justify-between gap-2"><h3 class="text-xs font-bold uppercase tracking-wider text-foreground">Used in template</h3> <span class="rounded-full bg-studio-success px-2 py-0.5 text-[10px] font-semibold text-studio-success-foreground">${escape_html(usedKeys.length)}</span></div> `);
      if (usedKeys.length === 0) {
        $$renderer2.push("<!--[0-->");
        $$renderer2.push(`<p class="mt-3 text-xs leading-relaxed text-muted-foreground">No fields detected yet. Insert a field or add one to the template.</p>`);
      } else {
        $$renderer2.push("<!--[-1-->");
        $$renderer2.push(`<div class="mt-3 flex flex-wrap gap-1.5"><!--[-->`);
        const each_array_4 = ensure_array_like(usedKeys);
        for (let $$index_4 = 0, $$length = each_array_4.length; $$index_4 < $$length; $$index_4++) {
          let key = each_array_4[$$index_4];
          $$renderer2.push(`<span class="rounded-md border border-studio-success/50 bg-studio-panel px-2 py-1 text-[11px] text-studio-panel-foreground">${escape_html(labelFor(key))}</span>`);
        }
        $$renderer2.push(`<!--]--></div>`);
      }
      $$renderer2.push(`<!--]--></div> <div class="mt-6 border-t border-border/70 pt-4"><div class="flex items-center justify-between gap-2"><h3 class="text-xs font-bold uppercase tracking-wider text-foreground">Recently copied</h3> <span class="rounded-full bg-info/15 px-2 py-0.5 text-[10px] font-semibold text-info">${escape_html(recentKeys.length)}</span></div> `);
      if (recentKeys.length === 0) {
        $$renderer2.push("<!--[0-->");
        $$renderer2.push(`<p class="mt-3 text-xs leading-relaxed text-muted-foreground">Copied fields will appear here during this session.</p>`);
      } else {
        $$renderer2.push("<!--[-1-->");
        $$renderer2.push(`<div class="mt-3 space-y-1.5"><!--[-->`);
        const each_array_5 = ensure_array_like(recentKeys);
        for (let $$index_5 = 0, $$length = each_array_5.length; $$index_5 < $$length; $$index_5++) {
          let key = each_array_5[$$index_5];
          $$renderer2.push(`<div class="flex items-center gap-2 rounded-lg border border-studio-border/70 bg-studio-panel px-2.5 py-2 text-xs">`);
          Check($$renderer2, { class: "size-3.5 text-info" });
          $$renderer2.push(`<!---->${escape_html(labelFor(key))}</div>`);
        }
        $$renderer2.push(`<!--]--></div>`);
      }
      $$renderer2.push(`<!--]--></div></aside></div></section></div>`);
    } else {
      $$renderer2.push("<!--[-1-->");
    }
    $$renderer2.push(`<!--]-->`);
  });
}
export {
  PlaceholderPicker as P,
  ResultSheetStudio as R
};
