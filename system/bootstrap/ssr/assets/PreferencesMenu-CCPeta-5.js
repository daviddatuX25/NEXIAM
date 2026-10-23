import { g as sanitize_props, i as spread_props, j as slot, d as derived, D as props_id, l as bind_props, z as attributes, C as cn, k as sound, B as Button, a as ensure_array_like, e as escape_html, G as setThemePreference, H as zoomOut, I as canZoomOut, J as zoomIn, K as canZoomIn, L as resetScale, M as getThemePreference, N as getScale } from "../app.js";
import { I as Icon } from "./Icon-Dal7PqKK.js";
import { c as createSubscriber, d as defaultWindow, w as watch } from "./watch.svelte-BcPoqckX.js";
import { C as Context, i as isNotNull, a as isElement, b as isTouch, E as ENTER, S as SPACE } from "./is-C6cm64pF.js";
import { s as simpleBox, j as boxFrom, a as attachRef, k as cssToStyleObj, l as styleToString, g as boxWith, m as mergeProps, h as getDataOpenClosed, d as boolToStr, i as getDataTransitionAttrs, e as createBitsAttrs, f as createId, b as boolToEmptyStrOrUndef, n as getDataChecked, o as getAriaChecked, c as boolToTrueOrUndef } from "./create-id-CUJmDlea.js";
import { u as useId, S as Scroll_lock, F as Focus_scope, E as Escape_layer, D as Dismissible_layer, T as Text_selection_layer, o as on, P as PresenceManager, a as Portal } from "./scroll-lock-B1ibcKMx.js";
import "clsx";
import { g as getWindow, a as getDocument, D as DOMContext, n as noop } from "./noop-BQS7OFs8.js";
import { isTabbable } from "tabbable";
import { computePosition, offset, shift, limitShift, flip, size, arrow, hide } from "@floating-ui/dom";
import { H as Hidden_input } from "./hidden-input-GSZDfM3n.js";
import { S as Sun, M as Moon } from "./InstitutionalBackdrop-BjxeRqDu.js";
import { Z as Zoom_out, a as Zoom_in } from "./zoom-out-DsJCISKt.js";
import { R as Rotate_ccw } from "./rotate-ccw-D6Dm_efx.js";
function Bell_off($$renderer, $$props) {
  const $$sanitized_props = sanitize_props($$props);
  const iconNode = [
    ["path", { "d": "M10.268 21a2 2 0 0 0 3.464 0" }],
    [
      "path",
      {
        "d": "M17 17H4a1 1 0 0 1-.74-1.673C4.59 13.956 6 12.499 6 8a6 6 0 0 1 .258-1.742"
      }
    ],
    ["path", { "d": "m2 2 20 20" }],
    [
      "path",
      {
        "d": "M8.668 3.01A6 6 0 0 1 18 8c0 2.687.77 4.653 1.707 6.05"
      }
    ]
  ];
  Icon($$renderer, spread_props([
    { name: "bell-off" },
    $$sanitized_props,
    {
      /**
       * @component @name BellOff
       * @description Lucide SVG icon component, renders SVG Element with children.
       *
       * @preview ![img](data:image/svg+xml;base64,PHN2ZyAgeG1sbnM9Imh0dHA6Ly93d3cudzMub3JnLzIwMDAvc3ZnIgogIHdpZHRoPSIyNCIKICBoZWlnaHQ9IjI0IgogIHZpZXdCb3g9IjAgMCAyNCAyNCIKICBmaWxsPSJub25lIgogIHN0cm9rZT0iIzAwMCIgc3R5bGU9ImJhY2tncm91bmQtY29sb3I6ICNmZmY7IGJvcmRlci1yYWRpdXM6IDJweCIKICBzdHJva2Utd2lkdGg9IjIiCiAgc3Ryb2tlLWxpbmVjYXA9InJvdW5kIgogIHN0cm9rZS1saW5lam9pbj0icm91bmQiCj4KICA8cGF0aCBkPSJNMTAuMjY4IDIxYTIgMiAwIDAgMCAzLjQ2NCAwIiAvPgogIDxwYXRoIGQ9Ik0xNyAxN0g0YTEgMSAwIDAgMS0uNzQtMS42NzNDNC41OSAxMy45NTYgNiAxMi40OTkgNiA4YTYgNiAwIDAgMSAuMjU4LTEuNzQyIiAvPgogIDxwYXRoIGQ9Im0yIDIgMjAgMjAiIC8+CiAgPHBhdGggZD0iTTguNjY4IDMuMDFBNiA2IDAgMCAxIDE4IDhjMCAyLjY4Ny43NyA0LjY1MyAxLjcwNyA2LjA1IiAvPgo8L3N2Zz4K) - https://lucide.dev/icons/bell-off
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
function Bell($$renderer, $$props) {
  const $$sanitized_props = sanitize_props($$props);
  const iconNode = [
    ["path", { "d": "M10.268 21a2 2 0 0 0 3.464 0" }],
    [
      "path",
      {
        "d": "M3.262 15.326A1 1 0 0 0 4 17h16a1 1 0 0 0 .74-1.673C19.41 13.956 18 12.499 18 8A6 6 0 0 0 6 8c0 4.499-1.411 5.956-2.738 7.326"
      }
    ]
  ];
  Icon($$renderer, spread_props([
    { name: "bell" },
    $$sanitized_props,
    {
      /**
       * @component @name Bell
       * @description Lucide SVG icon component, renders SVG Element with children.
       *
       * @preview ![img](data:image/svg+xml;base64,PHN2ZyAgeG1sbnM9Imh0dHA6Ly93d3cudzMub3JnLzIwMDAvc3ZnIgogIHdpZHRoPSIyNCIKICBoZWlnaHQ9IjI0IgogIHZpZXdCb3g9IjAgMCAyNCAyNCIKICBmaWxsPSJub25lIgogIHN0cm9rZT0iIzAwMCIgc3R5bGU9ImJhY2tncm91bmQtY29sb3I6ICNmZmY7IGJvcmRlci1yYWRpdXM6IDJweCIKICBzdHJva2Utd2lkdGg9IjIiCiAgc3Ryb2tlLWxpbmVjYXA9InJvdW5kIgogIHN0cm9rZS1saW5lam9pbj0icm91bmQiCj4KICA8cGF0aCBkPSJNMTAuMjY4IDIxYTIgMiAwIDAgMCAzLjQ2NCAwIiAvPgogIDxwYXRoIGQ9Ik0zLjI2MiAxNS4zMjZBMSAxIDAgMCAwIDQgMTdoMTZhMSAxIDAgMCAwIC43NC0xLjY3M0MxOS40MSAxMy45NTYgMTggMTIuNDk5IDE4IDhBNiA2IDAgMCAwIDYgOGMwIDQuNDk5LTEuNDExIDUuOTU2LTIuNzM4IDcuMzI2IiAvPgo8L3N2Zz4K) - https://lucide.dev/icons/bell
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
function Laptop($$renderer, $$props) {
  const $$sanitized_props = sanitize_props($$props);
  const iconNode = [
    [
      "path",
      {
        "d": "M18 5a2 2 0 0 1 2 2v8.526a2 2 0 0 0 .212.897l1.068 2.127a1 1 0 0 1-.9 1.45H3.62a1 1 0 0 1-.9-1.45l1.068-2.127A2 2 0 0 0 4 15.526V7a2 2 0 0 1 2-2z"
      }
    ],
    ["path", { "d": "M20.054 15.987H3.946" }]
  ];
  Icon($$renderer, spread_props([
    { name: "laptop" },
    $$sanitized_props,
    {
      /**
       * @component @name Laptop
       * @description Lucide SVG icon component, renders SVG Element with children.
       *
       * @preview ![img](data:image/svg+xml;base64,PHN2ZyAgeG1sbnM9Imh0dHA6Ly93d3cudzMub3JnLzIwMDAvc3ZnIgogIHdpZHRoPSIyNCIKICBoZWlnaHQ9IjI0IgogIHZpZXdCb3g9IjAgMCAyNCAyNCIKICBmaWxsPSJub25lIgogIHN0cm9rZT0iIzAwMCIgc3R5bGU9ImJhY2tncm91bmQtY29sb3I6ICNmZmY7IGJvcmRlci1yYWRpdXM6IDJweCIKICBzdHJva2Utd2lkdGg9IjIiCiAgc3Ryb2tlLWxpbmVjYXA9InJvdW5kIgogIHN0cm9rZS1saW5lam9pbj0icm91bmQiCj4KICA8cGF0aCBkPSJNMTggNWEyIDIgMCAwIDEgMiAydjguNTI2YTIgMiAwIDAgMCAuMjEyLjg5N2wxLjA2OCAyLjEyN2ExIDEgMCAwIDEtLjkgMS40NUgzLjYyYTEgMSAwIDAgMS0uOS0xLjQ1bDEuMDY4LTIuMTI3QTIgMiAwIDAgMCA0IDE1LjUyNlY3YTIgMiAwIDAgMSAyLTJ6IiAvPgogIDxwYXRoIGQ9Ik0yMC4wNTQgMTUuOTg3SDMuOTQ2IiAvPgo8L3N2Zz4K) - https://lucide.dev/icons/laptop
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
function Menu($$renderer, $$props) {
  const $$sanitized_props = sanitize_props($$props);
  const iconNode = [
    ["path", { "d": "M4 5h16" }],
    ["path", { "d": "M4 12h16" }],
    ["path", { "d": "M4 19h16" }]
  ];
  Icon($$renderer, spread_props([
    { name: "menu" },
    $$sanitized_props,
    {
      /**
       * @component @name Menu
       * @description Lucide SVG icon component, renders SVG Element with children.
       *
       * @preview ![img](data:image/svg+xml;base64,PHN2ZyAgeG1sbnM9Imh0dHA6Ly93d3cudzMub3JnLzIwMDAvc3ZnIgogIHdpZHRoPSIyNCIKICBoZWlnaHQ9IjI0IgogIHZpZXdCb3g9IjAgMCAyNCAyNCIKICBmaWxsPSJub25lIgogIHN0cm9rZT0iIzAwMCIgc3R5bGU9ImJhY2tncm91bmQtY29sb3I6ICNmZmY7IGJvcmRlci1yYWRpdXM6IDJweCIKICBzdHJva2Utd2lkdGg9IjIiCiAgc3Ryb2tlLWxpbmVjYXA9InJvdW5kIgogIHN0cm9rZS1saW5lam9pbj0icm91bmQiCj4KICA8cGF0aCBkPSJNNCA1aDE2IiAvPgogIDxwYXRoIGQ9Ik00IDEyaDE2IiAvPgogIDxwYXRoIGQ9Ik00IDE5aDE2IiAvPgo8L3N2Zz4K) - https://lucide.dev/icons/menu
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
function Sliders_horizontal($$renderer, $$props) {
  const $$sanitized_props = sanitize_props($$props);
  const iconNode = [
    ["path", { "d": "M10 5H3" }],
    ["path", { "d": "M12 19H3" }],
    ["path", { "d": "M14 3v4" }],
    ["path", { "d": "M16 17v4" }],
    ["path", { "d": "M21 12h-9" }],
    ["path", { "d": "M21 19h-5" }],
    ["path", { "d": "M21 5h-7" }],
    ["path", { "d": "M8 10v4" }],
    ["path", { "d": "M8 12H3" }]
  ];
  Icon($$renderer, spread_props([
    { name: "sliders-horizontal" },
    $$sanitized_props,
    {
      /**
       * @component @name SlidersHorizontal
       * @description Lucide SVG icon component, renders SVG Element with children.
       *
       * @preview ![img](data:image/svg+xml;base64,PHN2ZyAgeG1sbnM9Imh0dHA6Ly93d3cudzMub3JnLzIwMDAvc3ZnIgogIHdpZHRoPSIyNCIKICBoZWlnaHQ9IjI0IgogIHZpZXdCb3g9IjAgMCAyNCAyNCIKICBmaWxsPSJub25lIgogIHN0cm9rZT0iIzAwMCIgc3R5bGU9ImJhY2tncm91bmQtY29sb3I6ICNmZmY7IGJvcmRlci1yYWRpdXM6IDJweCIKICBzdHJva2Utd2lkdGg9IjIiCiAgc3Ryb2tlLWxpbmVjYXA9InJvdW5kIgogIHN0cm9rZS1saW5lam9pbj0icm91bmQiCj4KICA8cGF0aCBkPSJNMTAgNUgzIiAvPgogIDxwYXRoIGQ9Ik0xMiAxOUgzIiAvPgogIDxwYXRoIGQ9Ik0xNCAzdjQiIC8+CiAgPHBhdGggZD0iTTE2IDE3djQiIC8+CiAgPHBhdGggZD0iTTIxIDEyaC05IiAvPgogIDxwYXRoIGQ9Ik0yMSAxOWgtNSIgLz4KICA8cGF0aCBkPSJNMjEgNWgtNyIgLz4KICA8cGF0aCBkPSJNOCAxMHY0IiAvPgogIDxwYXRoIGQ9Ik04IDEySDMiIC8+Cjwvc3ZnPgo=) - https://lucide.dev/icons/sliders-horizontal
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
function Volume_2($$renderer, $$props) {
  const $$sanitized_props = sanitize_props($$props);
  const iconNode = [
    [
      "path",
      {
        "d": "M11 4.702a.705.705 0 0 0-1.203-.498L6.413 7.587A1.4 1.4 0 0 1 5.416 8H3a1 1 0 0 0-1 1v6a1 1 0 0 0 1 1h2.416a1.4 1.4 0 0 1 .997.413l3.383 3.384A.705.705 0 0 0 11 19.298z"
      }
    ],
    ["path", { "d": "M16 9a5 5 0 0 1 0 6" }],
    ["path", { "d": "M19.364 18.364a9 9 0 0 0 0-12.728" }]
  ];
  Icon($$renderer, spread_props([
    { name: "volume-2" },
    $$sanitized_props,
    {
      /**
       * @component @name Volume2
       * @description Lucide SVG icon component, renders SVG Element with children.
       *
       * @preview ![img](data:image/svg+xml;base64,PHN2ZyAgeG1sbnM9Imh0dHA6Ly93d3cudzMub3JnLzIwMDAvc3ZnIgogIHdpZHRoPSIyNCIKICBoZWlnaHQ9IjI0IgogIHZpZXdCb3g9IjAgMCAyNCAyNCIKICBmaWxsPSJub25lIgogIHN0cm9rZT0iIzAwMCIgc3R5bGU9ImJhY2tncm91bmQtY29sb3I6ICNmZmY7IGJvcmRlci1yYWRpdXM6IDJweCIKICBzdHJva2Utd2lkdGg9IjIiCiAgc3Ryb2tlLWxpbmVjYXA9InJvdW5kIgogIHN0cm9rZS1saW5lam9pbj0icm91bmQiCj4KICA8cGF0aCBkPSJNMTEgNC43MDJhLjcwNS43MDUgMCAwIDAtMS4yMDMtLjQ5OEw2LjQxMyA3LjU4N0ExLjQgMS40IDAgMCAxIDUuNDE2IDhIM2ExIDEgMCAwIDAtMSAxdjZhMSAxIDAgMCAwIDEgMWgyLjQxNmExLjQgMS40IDAgMCAxIC45OTcuNDEzbDMuMzgzIDMuMzg0QS43MDUuNzA1IDAgMCAwIDExIDE5LjI5OHoiIC8+CiAgPHBhdGggZD0iTTE2IDlhNSA1IDAgMCAxIDAgNiIgLz4KICA8cGF0aCBkPSJNMTkuMzY0IDE4LjM2NGE5IDkgMCAwIDAgMC0xMi43MjgiIC8+Cjwvc3ZnPgo=) - https://lucide.dev/icons/volume-2
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
function Volume_x($$renderer, $$props) {
  const $$sanitized_props = sanitize_props($$props);
  const iconNode = [
    [
      "path",
      {
        "d": "M11 4.702a.705.705 0 0 0-1.203-.498L6.413 7.587A1.4 1.4 0 0 1 5.416 8H3a1 1 0 0 0-1 1v6a1 1 0 0 0 1 1h2.416a1.4 1.4 0 0 1 .997.413l3.383 3.384A.705.705 0 0 0 11 19.298z"
      }
    ],
    ["line", { "x1": "22", "x2": "16", "y1": "9", "y2": "15" }],
    ["line", { "x1": "16", "x2": "22", "y1": "9", "y2": "15" }]
  ];
  Icon($$renderer, spread_props([
    { name: "volume-x" },
    $$sanitized_props,
    {
      /**
       * @component @name VolumeX
       * @description Lucide SVG icon component, renders SVG Element with children.
       *
       * @preview ![img](data:image/svg+xml;base64,PHN2ZyAgeG1sbnM9Imh0dHA6Ly93d3cudzMub3JnLzIwMDAvc3ZnIgogIHdpZHRoPSIyNCIKICBoZWlnaHQ9IjI0IgogIHZpZXdCb3g9IjAgMCAyNCAyNCIKICBmaWxsPSJub25lIgogIHN0cm9rZT0iIzAwMCIgc3R5bGU9ImJhY2tncm91bmQtY29sb3I6ICNmZmY7IGJvcmRlci1yYWRpdXM6IDJweCIKICBzdHJva2Utd2lkdGg9IjIiCiAgc3Ryb2tlLWxpbmVjYXA9InJvdW5kIgogIHN0cm9rZS1saW5lam9pbj0icm91bmQiCj4KICA8cGF0aCBkPSJNMTEgNC43MDJhLjcwNS43MDUgMCAwIDAtMS4yMDMtLjQ5OEw2LjQxMyA3LjU4N0ExLjQgMS40IDAgMCAxIDUuNDE2IDhIM2ExIDEgMCAwIDAtMSAxdjZhMSAxIDAgMCAwIDEgMWgyLjQxNmExLjQgMS40IDAgMCAxIC45OTcuNDEzbDMuMzgzIDMuMzg0QS43MDUuNzA1IDAgMCAwIDExIDE5LjI5OHoiIC8+CiAgPGxpbmUgeDE9IjIyIiB4Mj0iMTYiIHkxPSI5IiB5Mj0iMTUiIC8+CiAgPGxpbmUgeDE9IjE2IiB4Mj0iMjIiIHkxPSI5IiB5Mj0iMTUiIC8+Cjwvc3ZnPgo=) - https://lucide.dev/icons/volume-x
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
function isFunction(value) {
  return typeof value === "function";
}
function get$1(value) {
  if (isFunction(value)) {
    return value();
  }
  return value;
}
class ElementSize {
  // no need to use `$state` here since we are using createSubscriber
  #size = { width: 0, height: 0 };
  #observed = false;
  #options;
  #node;
  #window;
  // we use a derived here to extract the width so that if the width doesn't change we don't get a state update
  // which we would get if we would just use a getter since the version of the subscriber will be changing
  #width = derived(() => {
    this.#subscribe()?.();
    return this.getSize().width;
  });
  // we use a derived here to extract the height so that if the height doesn't change we don't get a state update
  // which we would get if we would just use a getter since the version of the subscriber will be changing
  #height = derived(() => {
    this.#subscribe()?.();
    return this.getSize().height;
  });
  // we need to use a derived here because the class will be created before the node is bound to the ref
  #subscribe = derived(() => {
    const node$ = get$1(this.#node);
    if (!node$) return;
    return createSubscriber();
  });
  constructor(node, options = { box: "border-box" }) {
    this.#window = options.window ?? defaultWindow;
    this.#options = options;
    this.#node = node;
    this.#size = { width: 0, height: 0 };
  }
  calculateSize() {
    const element = get$1(this.#node);
    if (!element || !this.#window) {
      return;
    }
    const offsetWidth = element.offsetWidth;
    const offsetHeight = element.offsetHeight;
    if (this.#options.box === "border-box") {
      return { width: offsetWidth, height: offsetHeight };
    }
    const style = this.#window.getComputedStyle(element);
    const paddingWidth = parseFloat(style.paddingLeft) + parseFloat(style.paddingRight);
    const paddingHeight = parseFloat(style.paddingTop) + parseFloat(style.paddingBottom);
    const borderWidth = parseFloat(style.borderLeftWidth) + parseFloat(style.borderRightWidth);
    const borderHeight = parseFloat(style.borderTopWidth) + parseFloat(style.borderBottomWidth);
    const contentWidth = offsetWidth - paddingWidth - borderWidth;
    const contentHeight = offsetHeight - paddingHeight - borderHeight;
    return { width: contentWidth, height: contentHeight };
  }
  getSize() {
    return this.#observed ? this.#size : this.calculateSize() ?? this.#size;
  }
  get current() {
    this.#subscribe()?.();
    return this.getSize();
  }
  get width() {
    return this.#width();
  }
  get height() {
    return this.#height();
  }
}
function get(valueOrGetValue) {
  return typeof valueOrGetValue === "function" ? valueOrGetValue() : valueOrGetValue;
}
function getDPR(element) {
  if (typeof window === "undefined") return 1;
  const win = element.ownerDocument.defaultView || window;
  return win.devicePixelRatio || 1;
}
function roundByDPR(element, value) {
  const dpr = getDPR(element);
  return Math.round(value * dpr) / dpr;
}
function getFloatingContentCSSVars(name) {
  return {
    [`--bits-${name}-content-transform-origin`]: `var(--bits-floating-transform-origin)`,
    [`--bits-${name}-content-available-width`]: `var(--bits-floating-available-width)`,
    [`--bits-${name}-content-available-height`]: `var(--bits-floating-available-height)`,
    [`--bits-${name}-anchor-width`]: `var(--bits-floating-anchor-width)`,
    [`--bits-${name}-anchor-height`]: `var(--bits-floating-anchor-height)`
  };
}
function useFloating(options) {
  const openOption = derived(() => get(options.open) ?? true);
  const middlewareOption = derived(() => get(options.middleware));
  const transformOption = derived(() => get(options.transform) ?? true);
  const placementOption = derived(() => get(options.placement) ?? "bottom");
  const strategyOption = derived(() => get(options.strategy) ?? "absolute");
  const sideOffsetOption = derived(() => get(options.sideOffset) ?? 0);
  const alignOffsetOption = derived(() => get(options.alignOffset) ?? 0);
  const reference = options.reference;
  let x = 0;
  let y = 0;
  const floating = simpleBox(null);
  let strategy = strategyOption();
  let placement = placementOption();
  let middlewareData = {};
  let isPositioned = false;
  let updateRequestId = 0;
  const floatingStyles = derived(() => {
    const xVal = floating.current ? roundByDPR(floating.current, x) : x;
    const yVal = floating.current ? roundByDPR(floating.current, y) : y;
    if (transformOption()) {
      return {
        position: strategy,
        left: "0",
        top: "0",
        transform: `translate(${xVal}px, ${yVal}px)`,
        ...floating.current && getDPR(floating.current) >= 1.5 && { willChange: "transform" }
      };
    }
    return { position: strategy, left: `${xVal}px`, top: `${yVal}px` };
  });
  function update() {
    if (reference.current === null || floating.current === null) return;
    const referenceNode = reference.current;
    const floatingNode = floating.current;
    const requestId = ++updateRequestId;
    computePosition(referenceNode, floatingNode, {
      middleware: middlewareOption(),
      placement: placementOption(),
      strategy: strategyOption()
    }).then((position) => {
      if (requestId !== updateRequestId) return;
      if (reference.current !== referenceNode || floating.current !== floatingNode) return;
      const referenceHidden = isReferenceHidden(referenceNode);
      if (referenceHidden) {
        middlewareData = {
          ...middlewareData,
          hide: {
            // oxlint-disable-next-line no-explicit-any
            ...middlewareData.hide,
            referenceHidden: true
          }
        };
        return;
      }
      if (!openOption() && x !== 0 && y !== 0) {
        const maxExpectedOffset = Math.max(Math.abs(sideOffsetOption()), Math.abs(alignOffsetOption()), 15);
        if (position.x <= maxExpectedOffset && position.y <= maxExpectedOffset) return;
      }
      x = position.x;
      y = position.y;
      strategy = position.strategy;
      placement = position.placement;
      middlewareData = position.middlewareData;
      isPositioned = true;
    });
  }
  return {
    floating,
    reference,
    get strategy() {
      return strategy;
    },
    get placement() {
      return placement;
    },
    get middlewareData() {
      return middlewareData;
    },
    get isPositioned() {
      return isPositioned;
    },
    get floatingStyles() {
      return floatingStyles();
    },
    get update() {
      return update;
    }
  };
}
function isReferenceHidden(node) {
  if (!(node instanceof Element)) return false;
  if (!node.isConnected) return true;
  if (node instanceof HTMLElement && node.hidden) return true;
  return node.getClientRects().length === 0;
}
const OPPOSITE_SIDE = { top: "bottom", right: "left", bottom: "top", left: "right" };
const FloatingRootContext = new Context("Floating.Root");
const FloatingContentContext = new Context("Floating.Content");
const FloatingTooltipRootContext = new Context("Floating.Root");
class FloatingRootState {
  static create(tooltip = false) {
    return tooltip ? FloatingTooltipRootContext.set(new FloatingRootState()) : FloatingRootContext.set(new FloatingRootState());
  }
  anchorNode = simpleBox(null);
  customAnchorNode = simpleBox(null);
  triggerNode = simpleBox(null);
  constructor() {
  }
}
class FloatingContentState {
  static create(opts, tooltip = false) {
    return tooltip ? FloatingContentContext.set(new FloatingContentState(opts, FloatingTooltipRootContext.get())) : FloatingContentContext.set(new FloatingContentState(opts, FloatingRootContext.get()));
  }
  opts;
  root;
  // nodes
  contentRef = simpleBox(null);
  wrapperRef = simpleBox(null);
  arrowRef = simpleBox(null);
  contentAttachment = attachRef(this.contentRef);
  wrapperAttachment = attachRef(this.wrapperRef);
  arrowAttachment = attachRef(this.arrowRef);
  // ids
  arrowId = simpleBox(useId());
  #transformedStyle = derived(() => {
    if (typeof this.opts.style === "string") return cssToStyleObj(this.opts.style);
    if (!this.opts.style) return {};
  });
  #updatePositionStrategy = void 0;
  #arrowSize = new ElementSize(() => this.arrowRef.current ?? void 0);
  #arrowWidth = derived(() => this.#arrowSize?.width ?? 0);
  #arrowHeight = derived(() => this.#arrowSize?.height ?? 0);
  #desiredPlacement = derived(() => this.opts.side?.current + (this.opts.align.current !== "center" ? `-${this.opts.align.current}` : ""));
  #boundary = derived(() => Array.isArray(this.opts.collisionBoundary.current) ? this.opts.collisionBoundary.current : [this.opts.collisionBoundary.current]);
  #hasExplicitBoundaries = derived(() => this.#boundary().length > 0);
  get hasExplicitBoundaries() {
    return this.#hasExplicitBoundaries();
  }
  set hasExplicitBoundaries($$value) {
    return this.#hasExplicitBoundaries($$value);
  }
  #detectOverflowOptions = derived(() => ({
    padding: this.opts.collisionPadding.current,
    boundary: this.#boundary().filter(isNotNull),
    altBoundary: this.hasExplicitBoundaries
  }));
  get detectOverflowOptions() {
    return this.#detectOverflowOptions();
  }
  set detectOverflowOptions($$value) {
    return this.#detectOverflowOptions($$value);
  }
  #availableWidth = void 0;
  #availableHeight = void 0;
  #anchorWidth = void 0;
  #anchorHeight = void 0;
  #middleware = derived(() => [
    offset({
      mainAxis: this.opts.sideOffset.current + this.#arrowHeight(),
      alignmentAxis: this.opts.alignOffset.current
    }),
    this.opts.avoidCollisions.current && shift({
      mainAxis: true,
      crossAxis: false,
      limiter: this.opts.sticky.current === "partial" ? limitShift() : void 0,
      ...this.detectOverflowOptions
    }),
    this.opts.avoidCollisions.current && flip({ ...this.detectOverflowOptions }),
    size({
      ...this.detectOverflowOptions,
      apply: ({ rects, availableWidth, availableHeight }) => {
        const { width: anchorWidth, height: anchorHeight } = rects.reference;
        this.#availableWidth = availableWidth;
        this.#availableHeight = availableHeight;
        this.#anchorWidth = anchorWidth;
        this.#anchorHeight = anchorHeight;
      }
    }),
    this.arrowRef.current && arrow({
      element: this.arrowRef.current,
      padding: this.opts.arrowPadding.current
    }),
    transformOrigin({
      arrowWidth: this.#arrowWidth(),
      arrowHeight: this.#arrowHeight()
    }),
    this.opts.hideWhenDetached.current && hide({ strategy: "referenceHidden", ...this.detectOverflowOptions })
  ].filter(Boolean));
  get middleware() {
    return this.#middleware();
  }
  set middleware($$value) {
    return this.#middleware($$value);
  }
  floating;
  #placedSide = derived(() => getSideFromPlacement(this.floating.placement));
  get placedSide() {
    return this.#placedSide();
  }
  set placedSide($$value) {
    return this.#placedSide($$value);
  }
  #placedAlign = derived(() => getAlignFromPlacement(this.floating.placement));
  get placedAlign() {
    return this.#placedAlign();
  }
  set placedAlign($$value) {
    return this.#placedAlign($$value);
  }
  #arrowX = derived(() => this.floating.middlewareData.arrow?.x ?? 0);
  get arrowX() {
    return this.#arrowX();
  }
  set arrowX($$value) {
    return this.#arrowX($$value);
  }
  #arrowY = derived(() => this.floating.middlewareData.arrow?.y ?? 0);
  get arrowY() {
    return this.#arrowY();
  }
  set arrowY($$value) {
    return this.#arrowY($$value);
  }
  #cannotCenterArrow = derived(() => this.floating.middlewareData.arrow?.centerOffset !== 0);
  get cannotCenterArrow() {
    return this.#cannotCenterArrow();
  }
  set cannotCenterArrow($$value) {
    return this.#cannotCenterArrow($$value);
  }
  contentZIndex;
  #arrowBaseSide = derived(() => OPPOSITE_SIDE[this.placedSide]);
  get arrowBaseSide() {
    return this.#arrowBaseSide();
  }
  set arrowBaseSide($$value) {
    return this.#arrowBaseSide($$value);
  }
  #wrapperProps = derived(() => ({
    id: this.opts.wrapperId.current,
    "data-bits-floating-content-wrapper": "",
    style: {
      ...this.floating.floatingStyles,
      transform: this.floating.isPositioned ? this.floating.floatingStyles.transform : "translate(0, -200%)",
      minWidth: "max-content",
      zIndex: this.contentZIndex,
      "--bits-floating-transform-origin": `${this.floating.middlewareData.transformOrigin?.x} ${this.floating.middlewareData.transformOrigin?.y}`,
      "--bits-floating-available-width": `${this.#availableWidth}px`,
      "--bits-floating-available-height": `${this.#availableHeight}px`,
      "--bits-floating-anchor-width": `${this.#anchorWidth}px`,
      "--bits-floating-anchor-height": `${this.#anchorHeight}px`,
      ...this.floating.middlewareData.hide?.referenceHidden && { visibility: "hidden", "pointer-events": "none" },
      ...this.#transformedStyle()
    },
    dir: this.opts.dir.current,
    ...this.wrapperAttachment
  }));
  get wrapperProps() {
    return this.#wrapperProps();
  }
  set wrapperProps($$value) {
    return this.#wrapperProps($$value);
  }
  #props = derived(() => ({
    "data-side": this.placedSide,
    "data-align": this.placedAlign,
    style: styleToString({ ...this.#transformedStyle() }),
    ...this.contentAttachment
  }));
  get props() {
    return this.#props();
  }
  set props($$value) {
    return this.#props($$value);
  }
  #arrowStyle = derived(() => ({
    position: "absolute",
    left: this.arrowX ? `${this.arrowX}px` : void 0,
    top: this.arrowY ? `${this.arrowY}px` : void 0,
    [this.arrowBaseSide]: 0,
    "transform-origin": { top: "", right: "0 0", bottom: "center 0", left: "100% 0" }[this.placedSide],
    transform: {
      top: "translateY(100%)",
      right: "translateY(50%) rotate(90deg) translateX(-50%)",
      bottom: "rotate(180deg)",
      left: "translateY(50%) rotate(-90deg) translateX(50%)"
    }[this.placedSide],
    visibility: this.cannotCenterArrow ? "hidden" : void 0
  }));
  get arrowStyle() {
    return this.#arrowStyle();
  }
  set arrowStyle($$value) {
    return this.#arrowStyle($$value);
  }
  constructor(opts, root) {
    this.opts = opts;
    this.root = root;
    this.#updatePositionStrategy = opts.updatePositionStrategy;
    if (opts.customAnchor) {
      this.root.customAnchorNode.current = opts.customAnchor.current;
    }
    watch(() => opts.customAnchor.current, (customAnchor) => {
      this.root.customAnchorNode.current = customAnchor;
    });
    this.floating = useFloating({
      strategy: () => this.opts.strategy.current,
      placement: () => this.#desiredPlacement(),
      middleware: () => this.middleware,
      reference: this.root.anchorNode,
      open: () => this.opts.enabled.current,
      sideOffset: () => this.opts.sideOffset.current,
      alignOffset: () => this.opts.alignOffset.current
    });
    watch(() => this.contentRef.current, (contentNode) => {
      if (!contentNode || !this.opts.enabled.current) return;
      const win = getWindow(contentNode);
      const rafId = win.requestAnimationFrame(() => {
        if (this.contentRef.current !== contentNode || !this.opts.enabled.current) return;
        const zIndex = win.getComputedStyle(contentNode).zIndex;
        if (zIndex !== this.contentZIndex) {
          this.contentZIndex = zIndex;
        }
      });
      return () => {
        win.cancelAnimationFrame(rafId);
      };
    });
  }
}
class FloatingAnchorState {
  static create(opts, tooltip = false) {
    return tooltip ? new FloatingAnchorState(opts, FloatingTooltipRootContext.get()) : new FloatingAnchorState(opts, FloatingRootContext.get());
  }
  opts;
  root;
  constructor(opts, root) {
    this.opts = opts;
    this.root = root;
    if (opts.virtualEl && opts.virtualEl.current) {
      root.triggerNode = boxFrom(opts.virtualEl.current);
    } else {
      root.triggerNode = opts.ref;
    }
  }
}
function transformOrigin(options) {
  return {
    name: "transformOrigin",
    options,
    fn(data) {
      const { placement, rects, middlewareData } = data;
      const cannotCenterArrow = middlewareData.arrow?.centerOffset !== 0;
      const isArrowHidden = cannotCenterArrow;
      const arrowWidth = isArrowHidden ? 0 : options.arrowWidth;
      const arrowHeight = isArrowHidden ? 0 : options.arrowHeight;
      const [placedSide, placedAlign] = getSideAndAlignFromPlacement(placement);
      const noArrowAlign = { start: "0%", center: "50%", end: "100%" }[placedAlign];
      const arrowXCenter = (middlewareData.arrow?.x ?? 0) + arrowWidth / 2;
      const arrowYCenter = (middlewareData.arrow?.y ?? 0) + arrowHeight / 2;
      let x = "";
      let y = "";
      if (placedSide === "bottom") {
        x = isArrowHidden ? noArrowAlign : `${arrowXCenter}px`;
        y = `${-arrowHeight}px`;
      } else if (placedSide === "top") {
        x = isArrowHidden ? noArrowAlign : `${arrowXCenter}px`;
        y = `${rects.floating.height + arrowHeight}px`;
      } else if (placedSide === "right") {
        x = `${-arrowHeight}px`;
        y = isArrowHidden ? noArrowAlign : `${arrowYCenter}px`;
      } else if (placedSide === "left") {
        x = `${rects.floating.width + arrowHeight}px`;
        y = isArrowHidden ? noArrowAlign : `${arrowYCenter}px`;
      }
      return { data: { x, y } };
    }
  };
}
function getSideAndAlignFromPlacement(placement) {
  const [side, align = "center"] = placement.split("-");
  return [side, align];
}
function getSideFromPlacement(placement) {
  return getSideAndAlignFromPlacement(placement)[0];
}
function getAlignFromPlacement(placement) {
  return getSideAndAlignFromPlacement(placement)[1];
}
function Floating_layer($$renderer, $$props) {
  $$renderer.component(($$renderer2) => {
    let { children, tooltip = false } = $$props;
    FloatingRootState.create(tooltip);
    children?.($$renderer2);
    $$renderer2.push(`<!---->`);
  });
}
function Floating_layer_anchor($$renderer, $$props) {
  $$renderer.component(($$renderer2) => {
    let { id, children, virtualEl, ref, tooltip = false } = $$props;
    FloatingAnchorState.create(
      {
        id: boxWith(() => id),
        virtualEl: boxWith(() => virtualEl),
        ref
      },
      tooltip
    );
    children?.($$renderer2);
    $$renderer2.push(`<!---->`);
  });
}
function Floating_layer_content($$renderer, $$props) {
  $$renderer.component(($$renderer2) => {
    let {
      content,
      side = "bottom",
      sideOffset = 0,
      align = "center",
      alignOffset = 0,
      id,
      arrowPadding = 0,
      avoidCollisions = true,
      collisionBoundary = [],
      collisionPadding = 0,
      hideWhenDetached = false,
      onPlaced = () => {
      },
      sticky = "partial",
      updatePositionStrategy = "optimized",
      strategy = "fixed",
      dir = "ltr",
      style = {},
      wrapperId = useId(),
      customAnchor = null,
      enabled,
      tooltip = false
    } = $$props;
    const contentState = FloatingContentState.create(
      {
        side: boxWith(() => side),
        sideOffset: boxWith(() => sideOffset),
        align: boxWith(() => align),
        alignOffset: boxWith(() => alignOffset),
        id: boxWith(() => id),
        arrowPadding: boxWith(() => arrowPadding),
        avoidCollisions: boxWith(() => avoidCollisions),
        collisionBoundary: boxWith(() => collisionBoundary),
        collisionPadding: boxWith(() => collisionPadding),
        hideWhenDetached: boxWith(() => hideWhenDetached),
        onPlaced: boxWith(() => onPlaced),
        sticky: boxWith(() => sticky),
        updatePositionStrategy: boxWith(() => updatePositionStrategy),
        strategy: boxWith(() => strategy),
        dir: boxWith(() => dir),
        style: boxWith(() => style),
        enabled: boxWith(() => enabled),
        wrapperId: boxWith(() => wrapperId),
        customAnchor: boxWith(() => customAnchor)
      },
      tooltip
    );
    const mergedProps = derived(() => mergeProps(contentState.wrapperProps, { style: { pointerEvents: "auto" } }));
    content?.($$renderer2, { props: contentState.props, wrapperProps: mergedProps() });
    $$renderer2.push(`<!---->`);
  });
}
function Floating_layer_content_static($$renderer, $$props) {
  $$renderer.component(($$renderer2) => {
    let { content } = $$props;
    content?.($$renderer2, { props: {}, wrapperProps: {} });
    $$renderer2.push(`<!---->`);
  });
}
function Popper_content($$renderer, $$props) {
  let {
    content,
    isStatic = false,
    onPlaced,
    $$slots,
    $$events,
    ...restProps
  } = $$props;
  if (isStatic) {
    $$renderer.push("<!--[0-->");
    Floating_layer_content_static($$renderer, { content });
  } else {
    $$renderer.push("<!--[-1-->");
    Floating_layer_content($$renderer, spread_props([{ content, onPlaced }, restProps]));
  }
  $$renderer.push(`<!--]-->`);
}
function Popper_layer_inner($$renderer, $$props) {
  $$renderer.component(($$renderer2) => {
    let {
      popper,
      onEscapeKeydown,
      escapeKeydownBehavior,
      preventOverflowTextSelection,
      id,
      onPointerDown,
      onPointerUp,
      side,
      sideOffset,
      align,
      alignOffset,
      arrowPadding,
      avoidCollisions,
      collisionBoundary,
      collisionPadding,
      sticky,
      hideWhenDetached,
      updatePositionStrategy,
      strategy,
      dir,
      preventScroll,
      wrapperId,
      style,
      onPlaced,
      onInteractOutside,
      onCloseAutoFocus,
      onOpenAutoFocus,
      onFocusOutside,
      interactOutsideBehavior = "close",
      loop,
      trapFocus = true,
      isValidEvent = () => false,
      customAnchor = null,
      isStatic = false,
      enabled,
      ref,
      tooltip = false,
      contentPointerEvents = "auto",
      $$slots,
      $$events,
      ...restProps
    } = $$props;
    const resolvedPreventScroll = derived(() => preventScroll ?? true);
    const effectiveStrategy = derived(() => strategy ?? (resolvedPreventScroll() ? "fixed" : "absolute"));
    {
      let content = function($$renderer3, { props: floatingProps, wrapperProps }) {
        if (restProps.forceMount && enabled) {
          $$renderer3.push("<!--[0-->");
          Scroll_lock($$renderer3, { preventScroll: resolvedPreventScroll() });
        } else if (!restProps.forceMount) {
          $$renderer3.push("<!--[1-->");
          Scroll_lock($$renderer3, { preventScroll: resolvedPreventScroll() });
        } else {
          $$renderer3.push("<!--[-1-->");
        }
        $$renderer3.push(`<!--]--> `);
        {
          let focusScope = function($$renderer4, { props: focusScopeProps }) {
            Escape_layer($$renderer4, {
              onEscapeKeydown,
              escapeKeydownBehavior,
              enabled,
              ref,
              children: ($$renderer5) => {
                {
                  let children = function($$renderer6, { props: dismissibleProps }) {
                    Text_selection_layer($$renderer6, {
                      id,
                      preventOverflowTextSelection,
                      onPointerDown,
                      onPointerUp,
                      enabled,
                      ref,
                      children: ($$renderer7) => {
                        popper?.($$renderer7, {
                          props: mergeProps(restProps, floatingProps, dismissibleProps, focusScopeProps, { style: { pointerEvents: contentPointerEvents } }),
                          wrapperProps
                        });
                        $$renderer7.push(`<!---->`);
                      }
                    });
                  };
                  Dismissible_layer($$renderer5, {
                    id,
                    onInteractOutside,
                    onFocusOutside,
                    interactOutsideBehavior,
                    isValidEvent,
                    enabled,
                    ref,
                    children
                  });
                }
              }
            });
          };
          Focus_scope($$renderer3, {
            onOpenAutoFocus,
            onCloseAutoFocus,
            loop,
            enabled,
            trapFocus,
            forceMount: restProps.forceMount,
            ref,
            focusScope
          });
        }
        $$renderer3.push(`<!---->`);
      };
      Popper_content($$renderer2, {
        isStatic,
        id,
        side,
        sideOffset,
        align,
        alignOffset,
        arrowPadding,
        avoidCollisions,
        collisionBoundary,
        collisionPadding,
        sticky,
        hideWhenDetached,
        updatePositionStrategy,
        strategy: effectiveStrategy(),
        dir,
        wrapperId,
        style,
        onPlaced,
        customAnchor,
        enabled,
        tooltip,
        content,
        $$slots: { content: true }
      });
    }
  });
}
function Popper_layer($$renderer, $$props) {
  let {
    popper,
    open,
    onEscapeKeydown,
    escapeKeydownBehavior,
    preventOverflowTextSelection,
    id,
    onPointerDown,
    onPointerUp,
    side,
    sideOffset,
    align,
    alignOffset,
    arrowPadding,
    avoidCollisions,
    collisionBoundary,
    collisionPadding,
    sticky,
    hideWhenDetached,
    updatePositionStrategy,
    strategy,
    dir,
    preventScroll,
    wrapperId,
    style,
    onPlaced,
    onInteractOutside,
    onCloseAutoFocus,
    onOpenAutoFocus,
    onFocusOutside,
    interactOutsideBehavior = "close",
    loop,
    trapFocus = true,
    isValidEvent = () => false,
    customAnchor = null,
    isStatic = false,
    ref,
    shouldRender,
    $$slots,
    $$events,
    ...restProps
  } = $$props;
  if (shouldRender) {
    $$renderer.push("<!--[0-->");
    Popper_layer_inner($$renderer, spread_props([
      {
        popper,
        onEscapeKeydown,
        escapeKeydownBehavior,
        preventOverflowTextSelection,
        id,
        onPointerDown,
        onPointerUp,
        side,
        sideOffset,
        align,
        alignOffset,
        arrowPadding,
        avoidCollisions,
        collisionBoundary,
        collisionPadding,
        sticky,
        hideWhenDetached,
        updatePositionStrategy,
        strategy,
        dir,
        preventScroll,
        wrapperId,
        style,
        onPlaced,
        customAnchor,
        isStatic,
        enabled: open,
        onInteractOutside,
        onCloseAutoFocus,
        onOpenAutoFocus,
        interactOutsideBehavior,
        loop,
        trapFocus,
        isValidEvent,
        onFocusOutside,
        forceMount: false,
        ref
      },
      restProps
    ]));
  } else {
    $$renderer.push("<!--[-1-->");
  }
  $$renderer.push(`<!--]-->`);
}
function Popper_layer_force_mount($$renderer, $$props) {
  let {
    popper,
    onEscapeKeydown,
    escapeKeydownBehavior,
    preventOverflowTextSelection,
    id,
    onPointerDown,
    onPointerUp,
    side,
    sideOffset,
    align,
    alignOffset,
    arrowPadding,
    avoidCollisions,
    collisionBoundary,
    collisionPadding,
    sticky,
    hideWhenDetached,
    updatePositionStrategy,
    strategy,
    dir,
    preventScroll,
    wrapperId,
    style,
    onPlaced,
    onInteractOutside,
    onCloseAutoFocus,
    onOpenAutoFocus,
    onFocusOutside,
    interactOutsideBehavior = "close",
    loop,
    trapFocus = true,
    isValidEvent = () => false,
    customAnchor = null,
    isStatic = false,
    enabled,
    $$slots,
    $$events,
    ...restProps
  } = $$props;
  Popper_layer_inner($$renderer, spread_props([
    {
      popper,
      onEscapeKeydown,
      escapeKeydownBehavior,
      preventOverflowTextSelection,
      id,
      onPointerDown,
      onPointerUp,
      side,
      sideOffset,
      align,
      alignOffset,
      arrowPadding,
      avoidCollisions,
      collisionBoundary,
      collisionPadding,
      sticky,
      hideWhenDetached,
      updatePositionStrategy,
      strategy,
      dir,
      preventScroll,
      wrapperId,
      style,
      onPlaced,
      customAnchor,
      isStatic,
      enabled,
      onInteractOutside,
      onCloseAutoFocus,
      onOpenAutoFocus,
      interactOutsideBehavior,
      loop,
      trapFocus,
      isValidEvent,
      onFocusOutside
    },
    restProps,
    { forceMount: true }
  ]));
}
function isPointInPolygon(point, polygon) {
  const [x, y] = point;
  let isInside = false;
  const length = polygon.length;
  for (let i = 0, j = length - 1; i < length; j = i++) {
    const [xi, yi] = polygon[i] ?? [0, 0];
    const [xj, yj] = polygon[j] ?? [0, 0];
    const intersect = yi >= y !== yj >= y && x <= (xj - xi) * (y - yi) / (yj - yi) + xi;
    if (intersect) {
      isInside = !isInside;
    }
  }
  return isInside;
}
function isInsideRect(point, rect) {
  return point[0] >= rect.left && point[0] <= rect.right && point[1] >= rect.top && point[1] <= rect.bottom;
}
function getSide(triggerRect, contentRect) {
  const triggerCenterX = triggerRect.left + triggerRect.width / 2;
  const triggerCenterY = triggerRect.top + triggerRect.height / 2;
  const contentCenterX = contentRect.left + contentRect.width / 2;
  const contentCenterY = contentRect.top + contentRect.height / 2;
  const deltaX = contentCenterX - triggerCenterX;
  const deltaY = contentCenterY - triggerCenterY;
  if (Math.abs(deltaX) > Math.abs(deltaY)) {
    return deltaX > 0 ? "right" : "left";
  }
  return deltaY > 0 ? "bottom" : "top";
}
class SafePolygon {
  #opts;
  #buffer;
  #transitIntentTimeout;
  // tracks the cursor position when leaving trigger or content
  #exitPoint = null;
  // tracks what we're moving toward: "content" when leaving trigger, "trigger" when leaving content
  #exitTarget = null;
  #transitTargets = [];
  #trackedTriggerNode = null;
  #leaveFallbackRafId = null;
  #transitIntentTimeoutId = null;
  #cancelLeaveFallback() {
    if (this.#leaveFallbackRafId !== null) {
      cancelAnimationFrame(this.#leaveFallbackRafId);
      this.#leaveFallbackRafId = null;
    }
  }
  #scheduleLeaveFallback() {
    this.#cancelLeaveFallback();
    this.#leaveFallbackRafId = requestAnimationFrame(() => {
      this.#leaveFallbackRafId = null;
      if (!this.#exitPoint || !this.#exitTarget) return;
      this.#clearTracking();
      this.#opts.onPointerExit();
    });
  }
  #cancelTransitIntentTimeout() {
    if (this.#transitIntentTimeoutId !== null) {
      clearTimeout(this.#transitIntentTimeoutId);
      this.#transitIntentTimeoutId = null;
    }
  }
  #scheduleTransitIntentTimeout() {
    if (this.#transitIntentTimeout === null) return;
    this.#cancelTransitIntentTimeout();
    this.#transitIntentTimeoutId = window.setTimeout(
      () => {
        this.#transitIntentTimeoutId = null;
        if (!this.#exitPoint || !this.#exitTarget) return;
        this.#clearTracking();
        this.#opts.onPointerExit();
      },
      this.#transitIntentTimeout
    );
  }
  constructor(opts) {
    this.#opts = opts;
    this.#buffer = opts.buffer ?? 1;
    const transitIntentTimeout = opts.transitIntentTimeout;
    this.#transitIntentTimeout = typeof transitIntentTimeout === "number" && transitIntentTimeout > 0 ? transitIntentTimeout : null;
    watch([opts.triggerNode, opts.contentNode, opts.enabled], ([triggerNode, contentNode, enabled]) => {
      if (!triggerNode || !contentNode || !enabled) {
        this.#trackedTriggerNode = null;
        this.#clearTracking();
        return;
      }
      if (this.#trackedTriggerNode && this.#trackedTriggerNode !== triggerNode) {
        this.#clearTracking();
      }
      this.#trackedTriggerNode = triggerNode;
      const doc = getDocument(triggerNode);
      const handlePointerMove = (e) => {
        this.#onPointerMove([e.clientX, e.clientY], triggerNode, contentNode);
      };
      const handleTriggerLeave = (e) => {
        const target = e.relatedTarget;
        if (isElement(target) && contentNode.contains(target)) {
          return;
        }
        const ignoredTargets = this.#opts.ignoredTargets?.() ?? [];
        if (isElement(target) && ignoredTargets.some((n) => n === target || n.contains(target))) {
          return;
        }
        this.#transitTargets = isElement(target) && ignoredTargets.length > 0 ? ignoredTargets.filter((n) => target.contains(n)) : [];
        this.#exitPoint = [e.clientX, e.clientY];
        this.#exitTarget = "content";
        this.#scheduleLeaveFallback();
      };
      const handleTriggerEnter = () => {
        this.#clearTracking();
      };
      const handleContentEnter = () => {
        this.#clearTracking();
      };
      const handleContentLeave = (e) => {
        const target = e.relatedTarget;
        if (isElement(target) && triggerNode.contains(target)) {
          return;
        }
        this.#exitPoint = [e.clientX, e.clientY];
        this.#exitTarget = "trigger";
        this.#scheduleLeaveFallback();
      };
      return [
        on(doc, "pointermove", handlePointerMove),
        on(triggerNode, "pointerleave", handleTriggerLeave),
        on(triggerNode, "pointerenter", handleTriggerEnter),
        on(contentNode, "pointerenter", handleContentEnter),
        on(contentNode, "pointerleave", handleContentLeave)
      ].reduce(
        (acc, cleanup) => () => {
          acc();
          cleanup();
        },
        () => {
        }
      );
    });
  }
  #onPointerMove(clientPoint, triggerNode, contentNode) {
    if (!this.#exitPoint || !this.#exitTarget) return;
    this.#cancelLeaveFallback();
    this.#scheduleTransitIntentTimeout();
    const triggerRect = triggerNode.getBoundingClientRect();
    const contentRect = contentNode.getBoundingClientRect();
    if (this.#exitTarget === "content" && isInsideRect(clientPoint, contentRect)) {
      this.#clearTracking();
      return;
    }
    if (this.#exitTarget === "trigger" && isInsideRect(clientPoint, triggerRect)) {
      this.#clearTracking();
      return;
    }
    if (this.#exitTarget === "content" && this.#transitTargets.length > 0) {
      for (const transitTarget of this.#transitTargets) {
        const transitRect = transitTarget.getBoundingClientRect();
        if (isInsideRect(clientPoint, transitRect)) return;
        const transitSide = getSide(triggerRect, transitRect);
        const transitCorridor = this.#getCorridorPolygon(triggerRect, transitRect, transitSide);
        if (transitCorridor && isPointInPolygon(clientPoint, transitCorridor)) return;
      }
    }
    const side = getSide(triggerRect, contentRect);
    const corridorPoly = this.#getCorridorPolygon(triggerRect, contentRect, side);
    if (corridorPoly && isPointInPolygon(clientPoint, corridorPoly)) {
      return;
    }
    const targetRect = this.#exitTarget === "content" ? contentRect : triggerRect;
    const safePoly = this.#getSafePolygon(this.#exitPoint, targetRect, side, this.#exitTarget);
    if (isPointInPolygon(clientPoint, safePoly)) {
      return;
    }
    this.#clearTracking();
    this.#opts.onPointerExit();
  }
  #clearTracking() {
    this.#exitPoint = null;
    this.#exitTarget = null;
    this.#transitTargets = [];
    this.#cancelLeaveFallback();
    this.#cancelTransitIntentTimeout();
  }
  /**
   * Creates a rectangular corridor between trigger and content
   * This prevents closing when cursor is in the gap between them
   */
  #getCorridorPolygon(triggerRect, contentRect, side) {
    const buffer = this.#buffer;
    switch (side) {
      case "top":
        return [
          [
            Math.min(triggerRect.left, contentRect.left) - buffer,
            triggerRect.top
          ],
          [
            Math.min(triggerRect.left, contentRect.left) - buffer,
            contentRect.bottom
          ],
          [
            Math.max(triggerRect.right, contentRect.right) + buffer,
            contentRect.bottom
          ],
          [
            Math.max(triggerRect.right, contentRect.right) + buffer,
            triggerRect.top
          ]
        ];
      case "bottom":
        return [
          [
            Math.min(triggerRect.left, contentRect.left) - buffer,
            triggerRect.bottom
          ],
          [
            Math.min(triggerRect.left, contentRect.left) - buffer,
            contentRect.top
          ],
          [
            Math.max(triggerRect.right, contentRect.right) + buffer,
            contentRect.top
          ],
          [
            Math.max(triggerRect.right, contentRect.right) + buffer,
            triggerRect.bottom
          ]
        ];
      case "left":
        return [
          [
            triggerRect.left,
            Math.min(triggerRect.top, contentRect.top) - buffer
          ],
          [
            contentRect.right,
            Math.min(triggerRect.top, contentRect.top) - buffer
          ],
          [
            contentRect.right,
            Math.max(triggerRect.bottom, contentRect.bottom) + buffer
          ],
          [
            triggerRect.left,
            Math.max(triggerRect.bottom, contentRect.bottom) + buffer
          ]
        ];
      case "right":
        return [
          [
            triggerRect.right,
            Math.min(triggerRect.top, contentRect.top) - buffer
          ],
          [
            contentRect.left,
            Math.min(triggerRect.top, contentRect.top) - buffer
          ],
          [
            contentRect.left,
            Math.max(triggerRect.bottom, contentRect.bottom) + buffer
          ],
          [
            triggerRect.right,
            Math.max(triggerRect.bottom, contentRect.bottom) + buffer
          ]
        ];
    }
  }
  /**
   * Creates a triangular/trapezoidal safe zone from the exit point to the target
   */
  #getSafePolygon(exitPoint, targetRect, side, exitTarget) {
    const buffer = this.#buffer * 4;
    const [x, y] = exitPoint;
    const effectiveSide = exitTarget === "trigger" ? this.#flipSide(side) : side;
    switch (effectiveSide) {
      case "top":
        return [
          [x - buffer, y + buffer],
          [x + buffer, y + buffer],
          [targetRect.right + buffer, targetRect.bottom],
          [targetRect.right + buffer, targetRect.top],
          [targetRect.left - buffer, targetRect.top],
          [targetRect.left - buffer, targetRect.bottom]
        ];
      case "bottom":
        return [
          [x - buffer, y - buffer],
          [x + buffer, y - buffer],
          [targetRect.right + buffer, targetRect.top],
          [targetRect.right + buffer, targetRect.bottom],
          [targetRect.left - buffer, targetRect.bottom],
          [targetRect.left - buffer, targetRect.top]
        ];
      case "left":
        return [
          [x + buffer, y - buffer],
          [x + buffer, y + buffer],
          [targetRect.right, targetRect.bottom + buffer],
          [targetRect.left, targetRect.bottom + buffer],
          [targetRect.left, targetRect.top - buffer],
          [targetRect.right, targetRect.top - buffer]
        ];
      case "right":
        return [
          [x - buffer, y - buffer],
          [x - buffer, y + buffer],
          [targetRect.left, targetRect.bottom + buffer],
          [targetRect.right, targetRect.bottom + buffer],
          [targetRect.right, targetRect.top - buffer],
          [targetRect.left, targetRect.top - buffer]
        ];
    }
  }
  #flipSide(side) {
    switch (side) {
      case "top":
        return "bottom";
      case "bottom":
        return "top";
      case "left":
        return "right";
      case "right":
        return "left";
    }
  }
}
const popoverAttrs = createBitsAttrs({
  component: "popover",
  parts: ["root", "trigger", "content", "close", "overlay"]
});
const PopoverRootContext = new Context("Popover.Root");
class PopoverRootState {
  static create(opts) {
    return PopoverRootContext.set(new PopoverRootState(opts));
  }
  opts;
  contentNode = null;
  contentPresence;
  triggerNode = null;
  overlayNode = null;
  overlayPresence;
  // hover tracking state
  openedViaHover = false;
  hasInteractedWithContent = false;
  hoverCooldown = false;
  closeDelay = 0;
  #closeTimeout = null;
  #domContext = null;
  constructor(opts) {
    this.opts = opts;
    this.contentPresence = new PresenceManager({
      ref: boxWith(() => this.contentNode),
      open: this.opts.open,
      onComplete: () => {
        this.opts.onOpenChangeComplete.current(this.opts.open.current);
      }
    });
    this.overlayPresence = new PresenceManager({ ref: boxWith(() => this.overlayNode), open: this.opts.open });
    watch(() => this.opts.open.current, (isOpen) => {
      if (!isOpen) {
        this.openedViaHover = false;
        this.hasInteractedWithContent = false;
        this.#clearCloseTimeout();
      }
    });
  }
  setDomContext(ctx) {
    this.#domContext = ctx;
  }
  #clearCloseTimeout() {
    if (this.#closeTimeout !== null && this.#domContext) {
      this.#domContext.clearTimeout(this.#closeTimeout);
      this.#closeTimeout = null;
    }
  }
  toggleOpen() {
    this.#clearCloseTimeout();
    this.opts.open.current = !this.opts.open.current;
  }
  handleClose() {
    this.#clearCloseTimeout();
    if (!this.opts.open.current) return;
    this.opts.open.current = false;
  }
  handleHoverOpen() {
    this.#clearCloseTimeout();
    if (this.opts.open.current) return;
    this.openedViaHover = true;
    this.opts.open.current = true;
  }
  handleHoverClose() {
    if (!this.opts.open.current) return;
    if (this.openedViaHover && !this.hasInteractedWithContent) {
      this.opts.open.current = false;
    }
  }
  handleDelayedHoverClose() {
    if (!this.opts.open.current) return;
    if (!this.openedViaHover || this.hasInteractedWithContent) return;
    this.#clearCloseTimeout();
    if (this.closeDelay <= 0) {
      this.opts.open.current = false;
    } else if (this.#domContext) {
      this.#closeTimeout = this.#domContext.setTimeout(
        () => {
          if (this.openedViaHover && !this.hasInteractedWithContent) {
            this.opts.open.current = false;
          }
          this.#closeTimeout = null;
        },
        this.closeDelay
      );
    }
  }
  cancelDelayedClose() {
    this.#clearCloseTimeout();
  }
  markInteraction() {
    this.hasInteractedWithContent = true;
    this.#clearCloseTimeout();
  }
}
class PopoverTriggerState {
  static create(opts) {
    return new PopoverTriggerState(opts, PopoverRootContext.get());
  }
  opts;
  root;
  attachment;
  domContext;
  #openTimeout = null;
  #closeTimeout = null;
  #isHovering = false;
  constructor(opts, root) {
    this.opts = opts;
    this.root = root;
    this.attachment = attachRef(this.opts.ref, (v) => this.root.triggerNode = v);
    this.domContext = new DOMContext(opts.ref);
    this.root.setDomContext(this.domContext);
    this.onclick = this.onclick.bind(this);
    this.onkeydown = this.onkeydown.bind(this);
    this.onpointerenter = this.onpointerenter.bind(this);
    this.onpointerleave = this.onpointerleave.bind(this);
    watch(() => this.opts.closeDelay.current, (delay) => {
      this.root.closeDelay = delay;
    });
  }
  #clearOpenTimeout() {
    if (this.#openTimeout !== null) {
      this.domContext.clearTimeout(this.#openTimeout);
      this.#openTimeout = null;
    }
  }
  #clearCloseTimeout() {
    if (this.#closeTimeout !== null) {
      this.domContext.clearTimeout(this.#closeTimeout);
      this.#closeTimeout = null;
    }
  }
  #clearAllTimeouts() {
    this.#clearOpenTimeout();
    this.#clearCloseTimeout();
  }
  onpointerenter(e) {
    if (this.opts.disabled.current) return;
    if (!this.opts.openOnHover.current) return;
    if (isTouch(e)) return;
    this.#isHovering = true;
    this.#clearCloseTimeout();
    this.root.cancelDelayedClose();
    if (this.root.opts.open.current || this.root.hoverCooldown) return;
    const delay = this.opts.openDelay.current;
    if (delay <= 0) {
      this.root.handleHoverOpen();
    } else {
      this.#openTimeout = this.domContext.setTimeout(
        () => {
          this.root.handleHoverOpen();
          this.#openTimeout = null;
        },
        delay
      );
    }
  }
  onpointerleave(e) {
    if (this.opts.disabled.current) return;
    if (!this.opts.openOnHover.current) return;
    if (isTouch(e)) return;
    this.#isHovering = false;
    this.#clearOpenTimeout();
    this.root.hoverCooldown = false;
  }
  onclick(e) {
    if (this.opts.disabled.current) return;
    if (e.button !== 0) return;
    this.#clearAllTimeouts();
    if (this.#isHovering && this.root.opts.open.current && this.root.openedViaHover) {
      this.root.openedViaHover = false;
      this.root.hasInteractedWithContent = true;
      return;
    }
    if (this.#isHovering && this.opts.openOnHover.current && this.root.opts.open.current) {
      this.root.hoverCooldown = true;
    }
    if (this.root.hoverCooldown && !this.root.opts.open.current) {
      this.root.hoverCooldown = false;
    }
    this.root.toggleOpen();
  }
  onkeydown(e) {
    if (this.opts.disabled.current) return;
    if (!(e.key === ENTER || e.key === SPACE)) return;
    e.preventDefault();
    this.#clearAllTimeouts();
    this.root.toggleOpen();
  }
  #getAriaControls() {
    if (this.root.opts.open.current && this.root.contentNode?.id) {
      return this.root.contentNode?.id;
    }
  }
  #props = derived(() => ({
    id: this.opts.id.current,
    "aria-haspopup": "dialog",
    "aria-expanded": boolToStr(this.root.opts.open.current),
    "data-state": getDataOpenClosed(this.root.opts.open.current),
    "aria-controls": this.#getAriaControls(),
    [popoverAttrs.trigger]: "",
    disabled: this.opts.disabled.current,
    //
    onkeydown: this.onkeydown,
    onclick: this.onclick,
    onpointerenter: this.onpointerenter,
    onpointerleave: this.onpointerleave,
    ...this.attachment
  }));
  get props() {
    return this.#props();
  }
  set props($$value) {
    return this.#props($$value);
  }
}
class PopoverContentState {
  static create(opts) {
    return new PopoverContentState(opts, PopoverRootContext.get());
  }
  opts;
  root;
  attachment;
  constructor(opts, root) {
    this.opts = opts;
    this.root = root;
    this.attachment = attachRef(this.opts.ref, (v) => this.root.contentNode = v);
    this.onpointerdown = this.onpointerdown.bind(this);
    this.onfocusin = this.onfocusin.bind(this);
    this.onpointerenter = this.onpointerenter.bind(this);
    this.onpointerleave = this.onpointerleave.bind(this);
    new SafePolygon({
      triggerNode: () => this.root.triggerNode,
      contentNode: () => this.root.contentNode,
      enabled: () => this.root.opts.open.current && this.root.openedViaHover && !this.root.hasInteractedWithContent,
      onPointerExit: () => {
        this.root.handleDelayedHoverClose();
      }
    });
  }
  onpointerdown(_) {
    this.root.markInteraction();
  }
  onfocusin(e) {
    const target = e.target;
    if (isElement(target) && isTabbable(target)) {
      this.root.markInteraction();
    }
  }
  onpointerenter(e) {
    if (isTouch(e)) return;
    this.root.cancelDelayedClose();
  }
  onpointerleave(e) {
    if (isTouch(e)) return;
  }
  onInteractOutside = (e) => {
    this.opts.onInteractOutside.current(e);
    if (e.defaultPrevented) return;
    if (!isElement(e.target)) return;
    const closestTrigger = e.target.closest(popoverAttrs.selector("trigger"));
    if (closestTrigger && closestTrigger === this.root.triggerNode) return;
    if (this.opts.customAnchor.current) {
      if (isElement(this.opts.customAnchor.current)) {
        if (this.opts.customAnchor.current.contains(e.target)) return;
      } else if (typeof this.opts.customAnchor.current === "string") {
        const el = document.querySelector(this.opts.customAnchor.current);
        if (el && el.contains(e.target)) return;
      }
    }
    this.root.handleClose();
  };
  onEscapeKeydown = (e) => {
    this.opts.onEscapeKeydown.current(e);
    if (e.defaultPrevented) return;
    this.root.handleClose();
  };
  get shouldRender() {
    return this.root.contentPresence.shouldRender;
  }
  get shouldTrapFocus() {
    if (this.root.openedViaHover && !this.root.hasInteractedWithContent) return false;
    return true;
  }
  #snippetProps = derived(() => ({ open: this.root.opts.open.current }));
  get snippetProps() {
    return this.#snippetProps();
  }
  set snippetProps($$value) {
    return this.#snippetProps($$value);
  }
  #props = derived(() => ({
    id: this.opts.id.current,
    tabindex: -1,
    "data-state": getDataOpenClosed(this.root.opts.open.current),
    ...getDataTransitionAttrs(this.root.contentPresence.transitionStatus),
    [popoverAttrs.content]: "",
    style: { pointerEvents: "auto", contain: "layout style" },
    onpointerdown: this.onpointerdown,
    onfocusin: this.onfocusin,
    onpointerenter: this.onpointerenter,
    onpointerleave: this.onpointerleave,
    ...this.attachment
  }));
  get props() {
    return this.#props();
  }
  set props($$value) {
    return this.#props($$value);
  }
  popperProps = {
    onInteractOutside: this.onInteractOutside,
    onEscapeKeydown: this.onEscapeKeydown
  };
}
function Popover_content$1($$renderer, $$props) {
  $$renderer.component(($$renderer2) => {
    const uid = props_id($$renderer2);
    let {
      child,
      children,
      ref = null,
      id = createId(uid),
      forceMount = false,
      onOpenAutoFocus = noop,
      onCloseAutoFocus = noop,
      onEscapeKeydown = noop,
      onInteractOutside = noop,
      trapFocus = true,
      preventScroll = false,
      customAnchor = null,
      style,
      $$slots,
      $$events,
      ...restProps
    } = $$props;
    const contentState = PopoverContentState.create({
      id: boxWith(() => id),
      ref: boxWith(() => ref, (v) => ref = v),
      onInteractOutside: boxWith(() => onInteractOutside),
      onEscapeKeydown: boxWith(() => onEscapeKeydown),
      customAnchor: boxWith(() => customAnchor)
    });
    const mergedProps = derived(() => mergeProps(restProps, contentState.props));
    const effectiveTrapFocus = derived(() => trapFocus && contentState.shouldTrapFocus);
    function handleOpenAutoFocus(e) {
      if (!contentState.shouldTrapFocus) {
        e.preventDefault();
      }
      onOpenAutoFocus(e);
    }
    if (forceMount) {
      $$renderer2.push("<!--[0-->");
      {
        let popper = function($$renderer3, { props, wrapperProps }) {
          const finalProps = mergeProps(props, { style: getFloatingContentCSSVars("popover") }, { style });
          if (child) {
            $$renderer3.push("<!--[0-->");
            child($$renderer3, {
              props: finalProps,
              wrapperProps,
              ...contentState.snippetProps
            });
            $$renderer3.push(`<!---->`);
          } else {
            $$renderer3.push("<!--[-1-->");
            $$renderer3.push(`<div${attributes({ ...wrapperProps })}><div${attributes({ ...finalProps })}>`);
            children?.($$renderer3);
            $$renderer3.push(`<!----></div></div>`);
          }
          $$renderer3.push(`<!--]-->`);
        };
        Popper_layer_force_mount($$renderer2, spread_props([
          mergedProps(),
          contentState.popperProps,
          {
            ref: contentState.opts.ref,
            enabled: contentState.root.opts.open.current,
            id,
            trapFocus: effectiveTrapFocus(),
            preventScroll,
            loop: true,
            forceMount: true,
            customAnchor,
            onOpenAutoFocus: handleOpenAutoFocus,
            onCloseAutoFocus,
            shouldRender: contentState.shouldRender,
            popper,
            $$slots: { popper: true }
          }
        ]));
      }
    } else if (!forceMount) {
      $$renderer2.push("<!--[1-->");
      {
        let popper = function($$renderer3, { props, wrapperProps }) {
          const finalProps = mergeProps(props, { style: getFloatingContentCSSVars("popover") }, { style });
          if (child) {
            $$renderer3.push("<!--[0-->");
            child($$renderer3, {
              props: finalProps,
              wrapperProps,
              ...contentState.snippetProps
            });
            $$renderer3.push(`<!---->`);
          } else {
            $$renderer3.push("<!--[-1-->");
            $$renderer3.push(`<div${attributes({ ...wrapperProps })}><div${attributes({ ...finalProps })}>`);
            children?.($$renderer3);
            $$renderer3.push(`<!----></div></div>`);
          }
          $$renderer3.push(`<!--]-->`);
        };
        Popper_layer($$renderer2, spread_props([
          mergedProps(),
          contentState.popperProps,
          {
            ref: contentState.opts.ref,
            open: contentState.root.opts.open.current,
            id,
            trapFocus: effectiveTrapFocus(),
            preventScroll,
            loop: true,
            forceMount: false,
            customAnchor,
            onOpenAutoFocus: handleOpenAutoFocus,
            onCloseAutoFocus,
            shouldRender: contentState.shouldRender,
            popper,
            $$slots: { popper: true }
          }
        ]));
      }
    } else {
      $$renderer2.push("<!--[-1-->");
    }
    $$renderer2.push(`<!--]-->`);
    bind_props($$props, { ref });
  });
}
function Popover_trigger$1($$renderer, $$props) {
  $$renderer.component(($$renderer2) => {
    const uid = props_id($$renderer2);
    let {
      children,
      child,
      id = createId(uid),
      ref = null,
      type = "button",
      disabled = false,
      openOnHover = false,
      openDelay = 700,
      closeDelay = 300,
      $$slots,
      $$events,
      ...restProps
    } = $$props;
    const triggerState = PopoverTriggerState.create({
      id: boxWith(() => id),
      ref: boxWith(() => ref, (v) => ref = v),
      disabled: boxWith(() => Boolean(disabled)),
      openOnHover: boxWith(() => openOnHover),
      openDelay: boxWith(() => openDelay),
      closeDelay: boxWith(() => closeDelay)
    });
    const mergedProps = derived(() => mergeProps(restProps, triggerState.props, { type }));
    Floating_layer_anchor($$renderer2, {
      id,
      ref: triggerState.opts.ref,
      children: ($$renderer3) => {
        if (child) {
          $$renderer3.push("<!--[0-->");
          child($$renderer3, { props: mergedProps() });
          $$renderer3.push(`<!---->`);
        } else {
          $$renderer3.push("<!--[-1-->");
          $$renderer3.push(`<button${attributes({ ...mergedProps() })}>`);
          children?.($$renderer3);
          $$renderer3.push(`<!----></button>`);
        }
        $$renderer3.push(`<!--]-->`);
      }
    });
    bind_props($$props, { ref });
  });
}
function Popover$1($$renderer, $$props) {
  $$renderer.component(($$renderer2) => {
    let {
      open = false,
      onOpenChange = noop,
      onOpenChangeComplete = noop,
      children
    } = $$props;
    PopoverRootState.create({
      open: boxWith(() => open, (v) => {
        open = v;
        onOpenChange(v);
      }),
      onOpenChangeComplete: boxWith(() => onOpenChangeComplete)
    });
    Floating_layer($$renderer2, {
      children: ($$renderer3) => {
        children?.($$renderer3);
        $$renderer3.push(`<!---->`);
      }
    });
    bind_props($$props, { open });
  });
}
const switchAttrs = createBitsAttrs({ component: "switch", parts: ["root", "thumb"] });
const SwitchRootContext = new Context("Switch.Root");
class SwitchRootState {
  static create(opts) {
    return SwitchRootContext.set(new SwitchRootState(opts));
  }
  opts;
  attachment;
  constructor(opts) {
    this.opts = opts;
    this.attachment = attachRef(opts.ref);
    this.onkeydown = this.onkeydown.bind(this);
    this.onclick = this.onclick.bind(this);
  }
  #toggle() {
    this.opts.checked.current = !this.opts.checked.current;
  }
  onkeydown(e) {
    if (!(e.key === ENTER || e.key === SPACE) || this.opts.disabled.current) return;
    e.preventDefault();
    this.#toggle();
  }
  onclick(_) {
    if (this.opts.disabled.current) return;
    this.#toggle();
  }
  #sharedProps = derived(() => ({
    "data-disabled": boolToEmptyStrOrUndef(this.opts.disabled.current),
    "data-state": getDataChecked(this.opts.checked.current),
    "data-required": boolToEmptyStrOrUndef(this.opts.required.current)
  }));
  get sharedProps() {
    return this.#sharedProps();
  }
  set sharedProps($$value) {
    return this.#sharedProps($$value);
  }
  #snippetProps = derived(() => ({ checked: this.opts.checked.current }));
  get snippetProps() {
    return this.#snippetProps();
  }
  set snippetProps($$value) {
    return this.#snippetProps($$value);
  }
  #props = derived(() => ({
    ...this.sharedProps,
    id: this.opts.id.current,
    role: "switch",
    disabled: boolToTrueOrUndef(this.opts.disabled.current),
    "aria-checked": getAriaChecked(this.opts.checked.current, false),
    "aria-required": boolToStr(this.opts.required.current),
    [switchAttrs.root]: "",
    onclick: this.onclick,
    onkeydown: this.onkeydown,
    ...this.attachment
  }));
  get props() {
    return this.#props();
  }
  set props($$value) {
    return this.#props($$value);
  }
}
class SwitchInputState {
  static create() {
    return new SwitchInputState(SwitchRootContext.get());
  }
  root;
  #shouldRender = derived(() => this.root.opts.name.current !== void 0);
  get shouldRender() {
    return this.#shouldRender();
  }
  set shouldRender($$value) {
    return this.#shouldRender($$value);
  }
  constructor(root) {
    this.root = root;
  }
  #props = derived(() => ({
    type: "checkbox",
    name: this.root.opts.name.current,
    value: this.root.opts.value.current,
    checked: this.root.opts.checked.current,
    disabled: this.root.opts.disabled.current,
    required: this.root.opts.required.current
  }));
  get props() {
    return this.#props();
  }
  set props($$value) {
    return this.#props($$value);
  }
}
class SwitchThumbState {
  static create(opts) {
    return new SwitchThumbState(opts, SwitchRootContext.get());
  }
  opts;
  root;
  attachment;
  constructor(opts, root) {
    this.opts = opts;
    this.root = root;
    this.attachment = attachRef(opts.ref);
  }
  #snippetProps = derived(() => ({ checked: this.root.opts.checked.current }));
  get snippetProps() {
    return this.#snippetProps();
  }
  set snippetProps($$value) {
    return this.#snippetProps($$value);
  }
  #props = derived(() => ({
    ...this.root.sharedProps,
    id: this.opts.id.current,
    [switchAttrs.thumb]: "",
    ...this.attachment
  }));
  get props() {
    return this.#props();
  }
  set props($$value) {
    return this.#props($$value);
  }
}
function Switch_input($$renderer, $$props) {
  $$renderer.component(($$renderer2) => {
    const inputState = SwitchInputState.create();
    if (inputState.shouldRender) {
      $$renderer2.push("<!--[0-->");
      Hidden_input($$renderer2, spread_props([inputState.props]));
    } else {
      $$renderer2.push("<!--[-1-->");
    }
    $$renderer2.push(`<!--]-->`);
  });
}
function Switch$1($$renderer, $$props) {
  $$renderer.component(($$renderer2) => {
    const uid = props_id($$renderer2);
    let {
      child,
      children,
      ref = null,
      id = createId(uid),
      disabled = false,
      required = false,
      checked = false,
      value = "on",
      name = void 0,
      type = "button",
      onCheckedChange = noop,
      $$slots,
      $$events,
      ...restProps
    } = $$props;
    const rootState = SwitchRootState.create({
      checked: boxWith(() => checked, (v) => {
        checked = v;
        onCheckedChange?.(v);
      }),
      disabled: boxWith(() => disabled ?? false),
      required: boxWith(() => required),
      value: boxWith(() => value),
      name: boxWith(() => name),
      id: boxWith(() => id),
      ref: boxWith(() => ref, (v) => ref = v)
    });
    const mergedProps = derived(() => mergeProps(restProps, rootState.props, { type }));
    if (child) {
      $$renderer2.push("<!--[0-->");
      child($$renderer2, { props: mergedProps(), ...rootState.snippetProps });
      $$renderer2.push(`<!---->`);
    } else {
      $$renderer2.push("<!--[-1-->");
      $$renderer2.push(`<button${attributes({ ...mergedProps() })}>`);
      children?.($$renderer2, rootState.snippetProps);
      $$renderer2.push(`<!----></button>`);
    }
    $$renderer2.push(`<!--]--> `);
    Switch_input($$renderer2);
    $$renderer2.push(`<!---->`);
    bind_props($$props, { ref, checked });
  });
}
function Switch_thumb($$renderer, $$props) {
  $$renderer.component(($$renderer2) => {
    const uid = props_id($$renderer2);
    let {
      child,
      children,
      ref = null,
      id = createId(uid),
      $$slots,
      $$events,
      ...restProps
    } = $$props;
    const thumbState = SwitchThumbState.create({
      id: boxWith(() => id),
      ref: boxWith(() => ref, (v) => ref = v)
    });
    const mergedProps = derived(() => mergeProps(restProps, thumbState.props));
    if (child) {
      $$renderer2.push("<!--[0-->");
      child($$renderer2, { props: mergedProps(), ...thumbState.snippetProps });
      $$renderer2.push(`<!---->`);
    } else {
      $$renderer2.push("<!--[-1-->");
      $$renderer2.push(`<span${attributes({ ...mergedProps() })}>`);
      children?.($$renderer2, thumbState.snippetProps);
      $$renderer2.push(`<!----></span>`);
    }
    $$renderer2.push(`<!--]-->`);
    bind_props($$props, { ref });
  });
}
function Popover($$renderer, $$props) {
  $$renderer.component(($$renderer2) => {
    let { open = false, $$slots, $$events, ...restProps } = $$props;
    let $$settled = true;
    let $$inner_renderer;
    function $$render_inner($$renderer3) {
      if (Popover$1) {
        $$renderer3.push("<!--[-->");
        Popover$1($$renderer3, spread_props([
          restProps,
          {
            get open() {
              return open;
            },
            set open($$value) {
              open = $$value;
              $$settled = false;
            }
          }
        ]));
        $$renderer3.push("<!--]-->");
      } else {
        $$renderer3.push("<!--[!-->");
        $$renderer3.push("<!--]-->");
      }
    }
    do {
      $$settled = true;
      $$inner_renderer = $$renderer2.copy();
      $$render_inner($$inner_renderer);
    } while (!$$settled);
    $$renderer2.subsume($$inner_renderer);
    bind_props($$props, { open });
  });
}
function Popover_portal($$renderer, $$props) {
  let { $$slots, $$events, ...restProps } = $$props;
  if (Portal) {
    $$renderer.push("<!--[-->");
    Portal($$renderer, spread_props([restProps]));
    $$renderer.push("<!--]-->");
  } else {
    $$renderer.push("<!--[!-->");
    $$renderer.push("<!--]-->");
  }
}
function Popover_content($$renderer, $$props) {
  $$renderer.component(($$renderer2) => {
    let {
      ref = null,
      class: className,
      sideOffset = 4,
      align = "center",
      portalProps,
      $$slots,
      $$events,
      ...restProps
    } = $$props;
    let $$settled = true;
    let $$inner_renderer;
    function $$render_inner($$renderer3) {
      Popover_portal($$renderer3, spread_props([
        portalProps,
        {
          children: ($$renderer4) => {
            if (Popover_content$1) {
              $$renderer4.push("<!--[-->");
              Popover_content$1($$renderer4, spread_props([
                {
                  "data-slot": "popover-content",
                  sideOffset,
                  align,
                  class: cn("bg-popover text-popover-foreground data-open:animate-in data-closed:animate-out data-closed:fade-out-0 data-open:fade-in-0 data-closed:zoom-out-95 data-open:zoom-in-95 data-[side=bottom]:slide-in-from-top-2 data-[side=left]:slide-in-from-right-2 data-[side=right]:slide-in-from-left-2 data-[side=top]:slide-in-from-bottom-2 ring-foreground/10 flex flex-col gap-2.5 rounded-lg p-2.5 text-sm shadow-md ring-1 duration-100 data-[side=inline-start]:slide-in-from-right-2 data-[side=inline-end]:slide-in-from-left-2 z-50 w-72 origin-(--transform-origin) outline-hidden", className)
                },
                restProps,
                {
                  get ref() {
                    return ref;
                  },
                  set ref($$value) {
                    ref = $$value;
                    $$settled = false;
                  }
                }
              ]));
              $$renderer4.push("<!--]-->");
            } else {
              $$renderer4.push("<!--[!-->");
              $$renderer4.push("<!--]-->");
            }
          },
          $$slots: { default: true }
        }
      ]));
    }
    do {
      $$settled = true;
      $$inner_renderer = $$renderer2.copy();
      $$render_inner($$inner_renderer);
    } while (!$$settled);
    $$renderer2.subsume($$inner_renderer);
    bind_props($$props, { ref });
  });
}
function Popover_trigger($$renderer, $$props) {
  $$renderer.component(($$renderer2) => {
    let {
      ref = null,
      class: className,
      child,
      children,
      $$slots,
      $$events,
      ...restProps
    } = $$props;
    let $$settled = true;
    let $$inner_renderer;
    function $$render_inner($$renderer3) {
      if (Popover_trigger$1) {
        $$renderer3.push("<!--[-->");
        Popover_trigger$1($$renderer3, spread_props([
          {
            "data-slot": "popover-trigger",
            class: cn("", className),
            child,
            children
          },
          restProps,
          {
            get ref() {
              return ref;
            },
            set ref($$value) {
              ref = $$value;
              $$settled = false;
            }
          }
        ]));
        $$renderer3.push("<!--]-->");
      } else {
        $$renderer3.push("<!--[!-->");
        $$renderer3.push("<!--]-->");
      }
    }
    do {
      $$settled = true;
      $$inner_renderer = $$renderer2.copy();
      $$render_inner($$inner_renderer);
    } while (!$$settled);
    $$renderer2.subsume($$inner_renderer);
    bind_props($$props, { ref });
  });
}
function Switch($$renderer, $$props) {
  $$renderer.component(($$renderer2) => {
    let {
      ref = null,
      class: className,
      checked = false,
      $$slots,
      $$events,
      ...restProps
    } = $$props;
    let $$settled = true;
    let $$inner_renderer;
    function $$render_inner($$renderer3) {
      if (Switch$1) {
        $$renderer3.push("<!--[-->");
        Switch$1($$renderer3, spread_props([
          {
            "data-slot": "switch",
            class: cn("data-[state=checked]:bg-primary data-[state=unchecked]:bg-input focus-visible:border-ring focus-visible:ring-ring/50 dark:data-[state=unchecked]:bg-input/80 peer inline-flex h-[1.15rem] w-8 shrink-0 items-center rounded-full border border-transparent shadow-xs transition-all outline-none focus-visible:ring-[3px] disabled:cursor-not-allowed disabled:opacity-50", className)
          },
          restProps,
          {
            get ref() {
              return ref;
            },
            set ref($$value) {
              ref = $$value;
              $$settled = false;
            },
            get checked() {
              return checked;
            },
            set checked($$value) {
              checked = $$value;
              $$settled = false;
            },
            children: ($$renderer4) => {
              if (Switch_thumb) {
                $$renderer4.push("<!--[-->");
                Switch_thumb($$renderer4, {
                  "data-slot": "switch-thumb",
                  class: cn("bg-background dark:data-[state=unchecked]:bg-foreground dark:data-[state=checked]:bg-primary-foreground pointer-events-none block size-4 rounded-full ring-0 transition-transform data-[state=checked]:translate-x-[calc(100%-2px)] data-[state=unchecked]:translate-x-0")
                });
                $$renderer4.push("<!--]-->");
              } else {
                $$renderer4.push("<!--[!-->");
                $$renderer4.push("<!--]-->");
              }
            },
            $$slots: { default: true }
          }
        ]));
        $$renderer3.push("<!--]-->");
      } else {
        $$renderer3.push("<!--[!-->");
        $$renderer3.push("<!--]-->");
      }
    }
    do {
      $$settled = true;
      $$inner_renderer = $$renderer2.copy();
      $$render_inner($$inner_renderer);
    } while (!$$settled);
    $$renderer2.subsume($$inner_renderer);
    bind_props($$props, { ref, checked });
  });
}
const preferenceKey = "nexiam.staff.idle.browser_alerts_enabled";
let activeNotification = null;
function browserApisAvailable() {
  try {
    return typeof window !== "undefined" && window.isSecureContext === true && typeof Notification !== "undefined";
  } catch {
    return false;
  }
}
function hasOptIn() {
  try {
    return window.localStorage.getItem(preferenceKey) === "true";
  } catch {
    return false;
  }
}
function storeOptIn(enabled) {
  try {
    if (enabled) {
      window.localStorage.setItem(preferenceKey, "true");
    } else {
      window.localStorage.removeItem(preferenceKey);
    }
    return true;
  } catch {
    return false;
  }
}
function getStaffIdleAlertStatus() {
  if (!browserApisAvailable()) {
    return "unsupported";
  }
  try {
    if (Notification.permission === "denied") {
      return "permission-denied";
    }
    if (Notification.permission === "default") {
      return "permission-required";
    }
    return hasOptIn() ? "enabled" : "disabled";
  } catch {
    return "unsupported";
  }
}
async function enableStaffIdleAlertsFromUserGesture() {
  if (!browserApisAvailable()) {
    return "unsupported";
  }
  try {
    let permission = Notification.permission;
    if (permission === "default") {
      permission = await Notification.requestPermission();
    }
    if (permission === "denied") {
      storeOptIn(false);
      return "permission-denied";
    }
    if (permission !== "granted") {
      storeOptIn(false);
      return "permission-required";
    }
    return storeOptIn(true) ? "enabled" : "disabled";
  } catch {
    storeOptIn(false);
    return getStaffIdleAlertStatus();
  }
}
function disableStaffIdleAlerts() {
  storeOptIn(false);
  closeStaffIdleAlert();
}
function showStaffIdleAlert(generation, warningEpisodeId) {
  {
    return;
  }
}
function closeStaffIdleAlert() {
  const notification = activeNotification;
  activeNotification = null;
  if (notification) {
    try {
      notification.close();
    } catch {
    }
  }
}
function PreferencesMenu($$renderer, $$props) {
  $$renderer.component(($$renderer2) => {
    let soundEnabled = sound.isEnabled();
    let alertStatus = getStaffIdleAlertStatus();
    let preferencesOpen = false;
    const currentTheme = derived(getThemePreference);
    const currentScale = derived(getScale);
    const themes = [
      { value: "light", label: "Light", Icon: Sun },
      { value: "dark", label: "Dark", Icon: Moon },
      { value: "system", label: "Auto", Icon: Laptop }
    ];
    const alertStatusText = derived(() => {
      switch (alertStatus) {
        case "enabled":
          return "Enabled. Browser permission is granted; alerts appear only while this tab is hidden.";
        case "permission-required":
          return "Browser permission is required to enable alerts.";
        case "permission-denied":
          return "Browser permission is denied. Change it in your browser settings.";
        case "unsupported":
          return "Browser alerts are unavailable in this browser or context.";
        default:
          return "Off. Browser permission is granted; in-page warnings and sounds remain available.";
      }
    });
    async function enableBrowserAlertsFromClick() {
      alertStatus = await enableStaffIdleAlertsFromUserGesture();
    }
    function disableBrowserAlertsFromClick() {
      disableStaffIdleAlerts();
      alertStatus = getStaffIdleAlertStatus();
    }
    let $$settled = true;
    let $$inner_renderer;
    function $$render_inner($$renderer3) {
      if (Popover) {
        $$renderer3.push("<!--[-->");
        Popover($$renderer3, {
          get open() {
            return preferencesOpen;
          },
          set open($$value) {
            preferencesOpen = $$value;
            $$settled = false;
          },
          children: ($$renderer4) => {
            if (Popover_trigger) {
              $$renderer4.push("<!--[-->");
              Popover_trigger($$renderer4, {
                children: ($$renderer5) => {
                  Button($$renderer5, {
                    variant: "ghost",
                    size: "icon",
                    class: "min-h-[44px] min-w-[44px] rounded-full",
                    "aria-label": "Accessibility and display preferences",
                    "aria-haspopup": "dialog",
                    children: ($$renderer6) => {
                      Sliders_horizontal($$renderer6, { class: "h-5 w-5" });
                    },
                    $$slots: { default: true }
                  });
                },
                $$slots: { default: true }
              });
              $$renderer4.push("<!--]-->");
            } else {
              $$renderer4.push("<!--[!-->");
              $$renderer4.push("<!--]-->");
            }
            $$renderer4.push(` `);
            if (Popover_content) {
              $$renderer4.push("<!--[-->");
              Popover_content($$renderer4, {
                class: "max-h-[calc(100dvh-2rem)] w-80 max-w-[calc(100vw-2rem)] space-y-4 overflow-y-auto p-4",
                align: "end",
                sideOffset: 8,
                children: ($$renderer5) => {
                  $$renderer5.push(`<header class="space-y-1"><h2 class="text-foreground text-sm font-semibold">Display &amp; Accessibility</h2> <p class="text-muted-foreground text-xs">Preferences for theme, acoustics, and scale</p></header> <section class="space-y-2" aria-labelledby="preferences-theme-label"><h3 id="preferences-theme-label" class="text-muted-foreground text-xs font-medium tracking-wider uppercase">Theme</h3> <div class="bg-muted/60 border-border/40 grid grid-cols-3 gap-1 rounded-lg border p-1" role="group" aria-label="Theme"><!--[-->`);
                  const each_array = ensure_array_like(themes);
                  for (let $$index = 0, $$length = each_array.length; $$index < $$length; $$index++) {
                    let theme = each_array[$$index];
                    Button($$renderer5, {
                      variant: "ghost",
                      size: "sm",
                      class: `h-auto flex-col gap-1 py-2 ${currentTheme() === theme.value ? "bg-background text-foreground shadow-xs" : "text-muted-foreground"}`,
                      "aria-pressed": currentTheme() === theme.value,
                      onclick: () => setThemePreference(theme.value),
                      children: ($$renderer6) => {
                        if (theme.Icon) {
                          $$renderer6.push("<!--[-->");
                          theme.Icon($$renderer6, { class: "h-4 w-4", "aria-hidden": "true" });
                          $$renderer6.push("<!--]-->");
                        } else {
                          $$renderer6.push("<!--[!-->");
                          $$renderer6.push("<!--]-->");
                        }
                        $$renderer6.push(` <span>${escape_html(theme.label)}</span>`);
                      },
                      $$slots: { default: true }
                    });
                  }
                  $$renderer5.push(`<!--]--></div></section> <section class="flex items-center justify-between gap-3" aria-labelledby="preferences-sound-label"><div class="space-y-1"><h3 id="preferences-sound-label" class="text-foreground flex items-center gap-1.5 text-xs font-semibold">`);
                  if (soundEnabled) {
                    $$renderer5.push("<!--[0-->");
                    Volume_2($$renderer5, { class: "h-3.5 w-3.5", "aria-hidden": "true" });
                  } else {
                    $$renderer5.push("<!--[-1-->");
                    Volume_x($$renderer5, { class: "h-3.5 w-3.5", "aria-hidden": "true" });
                  }
                  $$renderer5.push(`<!--]--> Interface Sounds</h3> <p class="text-muted-foreground text-[11px] leading-tight">Tactile clicks and inactivity alerts</p></div> `);
                  Switch($$renderer5, {
                    checked: soundEnabled,
                    "aria-label": "Interface sounds",
                    onCheckedChange: () => {
                      soundEnabled = sound.toggle();
                      if (soundEnabled) sound.press();
                    }
                  });
                  $$renderer5.push(`<!----></section> <section class="space-y-2" aria-labelledby="preferences-browser-alert-label"><div class="space-y-1"><h3 id="preferences-browser-alert-label" class="text-foreground flex items-center gap-1.5 text-xs font-semibold">`);
                  if (alertStatus === "enabled") {
                    $$renderer5.push("<!--[0-->");
                    Bell($$renderer5, { class: "h-3.5 w-3.5", "aria-hidden": "true" });
                  } else {
                    $$renderer5.push("<!--[-1-->");
                    Bell_off($$renderer5, { class: "h-3.5 w-3.5", "aria-hidden": "true" });
                  }
                  $$renderer5.push(`<!--]--> Browser alert when tab is hidden</h3> <p class="text-muted-foreground text-[11px] leading-tight" aria-live="polite">${escape_html(alertStatusText())}</p></div> `);
                  if (alertStatus === "enabled") {
                    $$renderer5.push("<!--[0-->");
                    Button($$renderer5, {
                      variant: "outline",
                      size: "sm",
                      class: "w-full",
                      onclick: disableBrowserAlertsFromClick,
                      children: ($$renderer6) => {
                        $$renderer6.push(`<!---->Turn off browser alerts`);
                      },
                      $$slots: { default: true }
                    });
                  } else {
                    $$renderer5.push("<!--[-1-->");
                    Button($$renderer5, {
                      variant: "outline",
                      size: "sm",
                      class: "w-full",
                      disabled: alertStatus === "unsupported" || alertStatus === "permission-denied",
                      onclick: enableBrowserAlertsFromClick,
                      children: ($$renderer6) => {
                        $$renderer6.push(`<!---->${escape_html(alertStatus === "permission-required" ? "Enable browser alerts" : "Turn on browser alerts")}`);
                      },
                      $$slots: { default: true }
                    });
                  }
                  $$renderer5.push(`<!--]--></section> <section class="flex items-center justify-between gap-3" aria-labelledby="preferences-scale-label"><div class="space-y-1"><h3 id="preferences-scale-label" class="text-foreground text-xs font-semibold">Interface Scale</h3> <p class="text-primary font-mono text-xs font-bold" aria-live="polite">${escape_html(currentScale())}%</p></div> <div class="flex items-center gap-1" aria-label="Interface scale controls">`);
                  Button($$renderer5, {
                    variant: "outline",
                    size: "icon-sm",
                    "aria-label": "Decrease interface scale",
                    disabled: !canZoomOut(),
                    onclick: () => zoomOut(),
                    children: ($$renderer6) => {
                      Zoom_out($$renderer6, { class: "h-4 w-4", "aria-hidden": "true" });
                    },
                    $$slots: { default: true }
                  });
                  $$renderer5.push(`<!----> `);
                  Button($$renderer5, {
                    variant: "outline",
                    size: "icon-sm",
                    "aria-label": "Increase interface scale",
                    disabled: !canZoomIn(),
                    onclick: () => zoomIn(),
                    children: ($$renderer6) => {
                      Zoom_in($$renderer6, { class: "h-4 w-4", "aria-hidden": "true" });
                    },
                    $$slots: { default: true }
                  });
                  $$renderer5.push(`<!----> `);
                  if (currentScale() !== 100) {
                    $$renderer5.push("<!--[0-->");
                    Button($$renderer5, {
                      variant: "ghost",
                      size: "icon-sm",
                      "aria-label": "Reset interface scale",
                      onclick: () => resetScale(),
                      children: ($$renderer6) => {
                        Rotate_ccw($$renderer6, { class: "h-4 w-4", "aria-hidden": "true" });
                      },
                      $$slots: { default: true }
                    });
                  } else {
                    $$renderer5.push("<!--[-1-->");
                  }
                  $$renderer5.push(`<!--]--></div></section>`);
                },
                $$slots: { default: true }
              });
              $$renderer4.push("<!--]-->");
            } else {
              $$renderer4.push("<!--[!-->");
              $$renderer4.push("<!--]-->");
            }
          },
          $$slots: { default: true }
        });
        $$renderer3.push("<!--]-->");
      } else {
        $$renderer3.push("<!--[!-->");
        $$renderer3.push("<!--]-->");
      }
    }
    do {
      $$settled = true;
      $$inner_renderer = $$renderer2.copy();
      $$render_inner($$inner_renderer);
    } while (!$$settled);
    $$renderer2.subsume($$inner_renderer);
  });
}
export {
  Bell as B,
  Floating_layer as F,
  Menu as M,
  PreferencesMenu as P,
  Sliders_horizontal as S,
  Popper_layer_force_mount as a,
  Popper_layer as b,
  closeStaffIdleAlert as c,
  Floating_layer_anchor as d,
  Popover as e,
  Popover_trigger as f,
  getFloatingContentCSSVars as g,
  Popover_content as h,
  Switch as i,
  showStaffIdleAlert as s
};
