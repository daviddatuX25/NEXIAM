import { g as sanitize_props, i as spread_props, j as slot, d as derived, D as props_id, z as attributes, l as bind_props, e as escape_html, b as attr, A as clsx, C as cn, h as head, c as attr_class, B as Button, a as ensure_array_like, u as usePage, s as stringify, f as attr_style, q as success, v as error, p as info } from "../app.js";
import { T as ToastManager, C as Circle_check_big } from "./ToastManager-C7oO_4Jn.js";
import { N as NotificationBell } from "./NotificationBell-Cgc59min.js";
import { M as Menu, P as PreferencesMenu, S as Sliders_horizontal, i as Switch, e as Popover, f as Popover_trigger, h as Popover_content } from "./PreferencesMenu-CCPeta-5.js";
import { I as InstitutionalBackdrop } from "./InstitutionalBackdrop-BjxeRqDu.js";
import { L as Link } from "./Link-B7ks7rBK.js";
import { L as Layout_dashboard, S as Search, A as Activity, F as File_spreadsheet, a as Settings } from "./settings-C5xOKpV3.js";
import { I as Icon } from "./Icon-Dal7PqKK.js";
import { C as Chevron_down } from "./chevron-down-CkQQZIhu.js";
import { C as Chevron_right } from "./chevron-right-BHu-PGOg.js";
import { B as Badge } from "./badge-D9Vgs7xh.js";
import { C as Card, a as Card_content } from "./card-content-Brg2wRkV.js";
import { C as Card_description } from "./card-description-BGy_v5-d.js";
import { C as Card_footer } from "./card-footer-Dl7GHCIG.js";
import { C as Card_header, a as Card_title } from "./card-title-BNY2ZPje.js";
import { C as Checkbox } from "./checkbox-D_0Lz-jK.js";
import { w as watch } from "./watch.svelte-BcPoqckX.js";
import { C as Context, E as ENTER, l as END, H as HOME, j as ARROW_LEFT, A as ARROW_UP, n as h, o as k, p, h as ARROW_RIGHT, k as ARROW_DOWN, q as l, r as j, s as n } from "./is-C6cm64pF.js";
import { s as snapshot } from "./clone-BJz-lxsY.js";
import { a as attachRef, y as boolToStrTrueOrUndef, e as createBitsAttrs, f as createId, g as boxWith, m as mergeProps, d as boolToStr, b as boolToEmptyStrOrUndef } from "./create-id-CUJmDlea.js";
import { b as afterTick, g as getFirstNonCommentChild, c as afterSleep, u as useId } from "./scroll-lock-B1ibcKMx.js";
import { s as srOnlyStyles } from "./hidden-input-GSZDfM3n.js";
import { n as noop } from "./noop-BQS7OFs8.js";
import { D as Dialog, b as Dialog_header, c as Dialog_title, d as Dialog_description, a as Dialog_content } from "./dialog-description-D12L0QA9.js";
import { C as Check } from "./check-DK45Y9SJ.js";
import "clsx";
import { D as Dialog_footer } from "./dialog-footer-kNnwKT2h.js";
import { F as File_upload } from "./file-upload-B9oVsy17.js";
import { I as Input } from "./input-BUZAZKIK.js";
import { L as Label } from "./label-dGY6L9vm.js";
import { S as Select, a as Select_trigger, b as Select_content, c as Select_item } from "./select-trigger-rRjpYIEn.js";
import { T as Table, a as Table_header, b as Table_row, c as Table_head, d as Table_body, e as Table_cell } from "./table-row-_ffWKrps.js";
import { T as Table_caption } from "./table-caption-Cs_SOvQm.js";
import { T as Tabs, a as Tabs_list, b as Tabs_trigger, c as Tabs_content } from "./tabs-trigger-DxnTaeSp.js";
import { T as Textarea } from "./textarea-Bv3Na1wO.js";
import { T as Toggle, a as Toggle_group, b as Toggle_group_item, S as SwitchableListView } from "./SwitchableListView-DHKJpEKr.js";
import { T as Trending_up, a as Trending_down, K as KpiCard } from "./KpiCard-uSVmkmNZ.js";
import { M as Minus } from "./minus-Dqkhcxt7.js";
import { S as SimplePagination } from "./SimplePagination-Bcq5R_Kb.js";
import { I as InfoPopover } from "./InfoPopover-CdH5YJnv.js";
import { P as PasswordStrengthInput } from "./PasswordStrengthInput-DagPPumI.js";
import { A as ActionDropdown } from "./ActionDropdown-DmJUv-tL.js";
import { P as PageHeaderBar } from "./PageHeaderBar-DJhT5vOR.js";
import { F as FilterToolbar } from "./FilterToolbar-Cl8Y1Mdp.js";
import { B as BulkActionBar } from "./BulkActionBar-CIsDAPcP.js";
import { M as ModeBanner } from "./ModeBanner-CuWMSWxe.js";
import { A as ActiveFilterChips } from "./ActiveFilterChips-BQD2-ACV.js";
import { C as Circle_question_mark } from "./circle-question-mark-DY-RU614.js";
import { T as Triangle_alert } from "./triangle-alert-CUvuB-HL.js";
import { I as Info } from "./info-C3MCzlQW.js";
import { A as Arrow_right } from "./arrow-right-CzynHiIo.js";
import { C as Clipboard_list } from "./clipboard-list-YgthURdB.js";
import { S as Shield_check } from "./shield-check-CLSg-ako.js";
import { G as Graduation_cap } from "./graduation-cap-Bc9amiZ0.js";
import { R as Rotate_ccw } from "./rotate-ccw-D6Dm_efx.js";
import { B as Building_2 } from "./building-2-BaeEMGXC.js";
import { F as File_text } from "./file-text-PXEtmXm9.js";
import { C as Circle_check } from "./circle-check-C59ZS7Zv.js";
import { L as Lock } from "./lock-DAA4kTLl.js";
import { C as Circle_x } from "./circle-x-BQrhluGu.js";
import { S as Sparkles } from "./sparkles-DbQIaVG6.js";
import { L as Layers } from "./layers-_Ie6y7G_.js";
import { U as Users } from "./users-DWlguTa_.js";
import { F as File_check } from "./file-check-BSLEMs1I.js";
import { R as Refresh_cw } from "./refresh-cw-CYdk8aE0.js";
import { U as Upload } from "./upload-CwqN2Utd.js";
import { M as MediaFrame, S as StorySection, P as ProgramExplorer, A as AdmissionsGateway } from "./StorySection-0tCF3NOv.js";
import { F as Folder_open } from "./folder-open-BF8o-1TF.js";
import { P as Plus } from "./plus-DjyPLC7A.js";
import { E as Eye } from "./eye-DyG-RiwW.js";
import { P as Pencil } from "./pencil-0tPPVae8.js";
import { T as Trash_2 } from "./trash-2-CjFp3otc.js";
import { D as Download } from "./download-n32NlL8c.js";
import { P as Printer } from "./printer-Bx0z1CRb.js";
import "axios";
import "@inertiajs/core";
import "laravel-precognition";
import "tailwind-merge";
import "tailwind-variants";
import "./x-CnxrMbMn.js";
import "./inbox-AmUVtgA2.js";
import "./mail-DBtkjaCt.js";
import "tabbable";
import "@floating-ui/dom";
import "./zoom-out-DsJCISKt.js";
import "style-to-object";
import "./chevron-up-lhASJLRD.js";
import "./previous.svelte-Ch7Tz-Mv.js";
import "./arrays-CkOR4vx_.js";
import "./roving-focus-group-BJDH9kUk.js";
import "./table-2-BLgeaPqt.js";
import "./layout-grid-CXJfSBqV.js";
import "./chevron-left-DCSOZwZl.js";
import "./eye-off-Bx_raPQb.js";
import "./XiamNpcDialog-DoqCSHj9.js";
/* empty css                                                        */
import "./key-round-BcGN1PBU.js";
import "./calendar-days-CnJjYUSZ.js";
import "./arrow-up-right-BtyWsOyK.js";
function Blocks($$renderer, $$props) {
  const $$sanitized_props = sanitize_props($$props);
  const iconNode = [
    [
      "path",
      {
        "d": "M10 22V7a1 1 0 0 0-1-1H4a2 2 0 0 0-2 2v12a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2v-5a1 1 0 0 0-1-1H2"
      }
    ],
    [
      "rect",
      { "x": "14", "y": "2", "width": "8", "height": "8", "rx": "1" }
    ]
  ];
  Icon($$renderer, spread_props([
    { name: "blocks" },
    $$sanitized_props,
    {
      /**
       * @component @name Blocks
       * @description Lucide SVG icon component, renders SVG Element with children.
       *
       * @preview ![img](data:image/svg+xml;base64,PHN2ZyAgeG1sbnM9Imh0dHA6Ly93d3cudzMub3JnLzIwMDAvc3ZnIgogIHdpZHRoPSIyNCIKICBoZWlnaHQ9IjI0IgogIHZpZXdCb3g9IjAgMCAyNCAyNCIKICBmaWxsPSJub25lIgogIHN0cm9rZT0iIzAwMCIgc3R5bGU9ImJhY2tncm91bmQtY29sb3I6ICNmZmY7IGJvcmRlci1yYWRpdXM6IDJweCIKICBzdHJva2Utd2lkdGg9IjIiCiAgc3Ryb2tlLWxpbmVjYXA9InJvdW5kIgogIHN0cm9rZS1saW5lam9pbj0icm91bmQiCj4KICA8cGF0aCBkPSJNMTAgMjJWN2ExIDEgMCAwIDAtMS0xSDRhMiAyIDAgMCAwLTIgMnYxMmEyIDIgMCAwIDAgMiAyaDEyYTIgMiAwIDAgMCAyLTJ2LTVhMSAxIDAgMCAwLTEtMUgyIiAvPgogIDxyZWN0IHg9IjE0IiB5PSIyIiB3aWR0aD0iOCIgaGVpZ2h0PSI4IiByeD0iMSIgLz4KPC9zdmc+Cg==) - https://lucide.dev/icons/blocks
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
function Clipboard_check($$renderer, $$props) {
  const $$sanitized_props = sanitize_props($$props);
  const iconNode = [
    [
      "rect",
      {
        "width": "8",
        "height": "4",
        "x": "8",
        "y": "2",
        "rx": "1",
        "ry": "1"
      }
    ],
    [
      "path",
      {
        "d": "M16 4h2a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2V6a2 2 0 0 1 2-2h2"
      }
    ],
    ["path", { "d": "m9 14 2 2 4-4" }]
  ];
  Icon($$renderer, spread_props([
    { name: "clipboard-check" },
    $$sanitized_props,
    {
      /**
       * @component @name ClipboardCheck
       * @description Lucide SVG icon component, renders SVG Element with children.
       *
       * @preview ![img](data:image/svg+xml;base64,PHN2ZyAgeG1sbnM9Imh0dHA6Ly93d3cudzMub3JnLzIwMDAvc3ZnIgogIHdpZHRoPSIyNCIKICBoZWlnaHQ9IjI0IgogIHZpZXdCb3g9IjAgMCAyNCAyNCIKICBmaWxsPSJub25lIgogIHN0cm9rZT0iIzAwMCIgc3R5bGU9ImJhY2tncm91bmQtY29sb3I6ICNmZmY7IGJvcmRlci1yYWRpdXM6IDJweCIKICBzdHJva2Utd2lkdGg9IjIiCiAgc3Ryb2tlLWxpbmVjYXA9InJvdW5kIgogIHN0cm9rZS1saW5lam9pbj0icm91bmQiCj4KICA8cmVjdCB3aWR0aD0iOCIgaGVpZ2h0PSI0IiB4PSI4IiB5PSIyIiByeD0iMSIgcnk9IjEiIC8+CiAgPHBhdGggZD0iTTE2IDRoMmEyIDIgMCAwIDEgMiAydjE0YTIgMiAwIDAgMS0yIDJINmEyIDIgMCAwIDEtMi0yVjZhMiAyIDAgMCAxIDItMmgyIiAvPgogIDxwYXRoIGQ9Im05IDE0IDIgMiA0LTQiIC8+Cjwvc3ZnPgo=) - https://lucide.dev/icons/clipboard-check
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
function Lightbulb($$renderer, $$props) {
  const $$sanitized_props = sanitize_props($$props);
  const iconNode = [
    [
      "path",
      {
        "d": "M15 14c.2-1 .7-1.7 1.5-2.5 1-.9 1.5-2.2 1.5-3.5A6 6 0 0 0 6 8c0 1 .2 2.2 1.5 3.5.7.7 1.3 1.5 1.5 2.5"
      }
    ],
    ["path", { "d": "M9 18h6" }],
    ["path", { "d": "M10 22h4" }]
  ];
  Icon($$renderer, spread_props([
    { name: "lightbulb" },
    $$sanitized_props,
    {
      /**
       * @component @name Lightbulb
       * @description Lucide SVG icon component, renders SVG Element with children.
       *
       * @preview ![img](data:image/svg+xml;base64,PHN2ZyAgeG1sbnM9Imh0dHA6Ly93d3cudzMub3JnLzIwMDAvc3ZnIgogIHdpZHRoPSIyNCIKICBoZWlnaHQ9IjI0IgogIHZpZXdCb3g9IjAgMCAyNCAyNCIKICBmaWxsPSJub25lIgogIHN0cm9rZT0iIzAwMCIgc3R5bGU9ImJhY2tncm91bmQtY29sb3I6ICNmZmY7IGJvcmRlci1yYWRpdXM6IDJweCIKICBzdHJva2Utd2lkdGg9IjIiCiAgc3Ryb2tlLWxpbmVjYXA9InJvdW5kIgogIHN0cm9rZS1saW5lam9pbj0icm91bmQiCj4KICA8cGF0aCBkPSJNMTUgMTRjLjItMSAuNy0xLjcgMS41LTIuNSAxLS45IDEuNS0yLjIgMS41LTMuNUE2IDYgMCAwIDAgNiA4YzAgMSAuMiAyLjIgMS41IDMuNS43LjcgMS4zIDEuNSAxLjUgMi41IiAvPgogIDxwYXRoIGQ9Ik05IDE4aDYiIC8+CiAgPHBhdGggZD0iTTEwIDIyaDQiIC8+Cjwvc3ZnPgo=) - https://lucide.dev/icons/lightbulb
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
const separatorAttrs = createBitsAttrs({ component: "separator", parts: ["root"] });
class SeparatorRootState {
  static create(opts) {
    return new SeparatorRootState(opts);
  }
  opts;
  attachment;
  constructor(opts) {
    this.opts = opts;
    this.attachment = attachRef(opts.ref);
  }
  #props = derived(() => ({
    id: this.opts.id.current,
    role: this.opts.decorative.current ? "none" : "separator",
    "aria-orientation": this.opts.orientation.current,
    "aria-hidden": boolToStrTrueOrUndef(this.opts.decorative.current),
    "data-orientation": this.opts.orientation.current,
    [separatorAttrs.root]: "",
    ...this.attachment
  }));
  get props() {
    return this.#props();
  }
  set props($$value) {
    return this.#props($$value);
  }
}
function Separator$1($$renderer, $$props) {
  $$renderer.component(($$renderer2) => {
    const uid = props_id($$renderer2);
    let {
      id = createId(uid),
      ref = null,
      child,
      children,
      decorative = false,
      orientation = "horizontal",
      $$slots,
      $$events,
      ...restProps
    } = $$props;
    const rootState = SeparatorRootState.create({
      ref: boxWith(() => ref, (v) => ref = v),
      id: boxWith(() => id),
      decorative: boxWith(() => decorative),
      orientation: boxWith(() => orientation)
    });
    const mergedProps = derived(() => mergeProps(restProps, rootState.props));
    if (child) {
      $$renderer2.push("<!--[0-->");
      child($$renderer2, { props: mergedProps() });
      $$renderer2.push(`<!---->`);
    } else {
      $$renderer2.push("<!--[-1-->");
      $$renderer2.push(`<div${attributes({ ...mergedProps() })}>`);
      children?.($$renderer2);
      $$renderer2.push(`<!----></div>`);
    }
    $$renderer2.push(`<!--]-->`);
    bind_props($$props, { ref });
  });
}
function findNextSibling(el, selector) {
  let sibling = el.nextElementSibling;
  while (sibling) {
    if (sibling.matches(selector))
      return sibling;
    sibling = sibling.nextElementSibling;
  }
}
function findPreviousSibling(el, selector) {
  let sibling = el.previousElementSibling;
  while (sibling) {
    if (sibling.matches(selector))
      return sibling;
    sibling = sibling.previousElementSibling;
  }
}
function cssEscape(value) {
  if (typeof CSS !== "undefined" && typeof CSS.escape === "function") {
    return CSS.escape(value);
  }
  const length = value.length;
  let index = -1;
  let codeUnit;
  let result = "";
  const firstCodeUnit = value.charCodeAt(0);
  if (length === 1 && firstCodeUnit === 45)
    return "\\" + value;
  while (++index < length) {
    codeUnit = value.charCodeAt(index);
    if (codeUnit === 0) {
      result += "�";
      continue;
    }
    if (
      // If the character is in the range [\1-\1F] (U+0001 to U+001F) or is U+007F
      codeUnit >= 1 && codeUnit <= 31 || codeUnit === 127 || // If the character is the first character and is in the range [0-9] (U+0030 to U+0039)
      index === 0 && codeUnit >= 48 && codeUnit <= 57 || // If the character is the second character and is in the range [0-9] (U+0030 to U+0039)
      // and the first character is a `-` (U+002D)
      index === 1 && codeUnit >= 48 && codeUnit <= 57 && firstCodeUnit === 45
    ) {
      result += "\\" + codeUnit.toString(16) + " ";
      continue;
    }
    if (codeUnit >= 128 || codeUnit === 45 || codeUnit === 95 || codeUnit >= 48 && codeUnit <= 57 || codeUnit >= 65 && codeUnit <= 90 || codeUnit >= 97 && codeUnit <= 122) {
      result += value.charAt(index);
      continue;
    }
    result += "\\" + value.charAt(index);
  }
  return result;
}
const COMMAND_VALUE_ATTR = "data-value";
const commandAttrs = createBitsAttrs({
  component: "command",
  parts: [
    "root",
    "list",
    "input",
    "separator",
    "loading",
    "empty",
    "group",
    "group-items",
    "group-heading",
    "item",
    "viewport",
    "input-label"
  ]
});
const COMMAND_GROUP_SELECTOR = commandAttrs.selector("group");
const COMMAND_GROUP_ITEMS_SELECTOR = commandAttrs.selector("group-items");
const COMMAND_GROUP_HEADING_SELECTOR = commandAttrs.selector("group-heading");
const COMMAND_ITEM_SELECTOR = commandAttrs.selector("item");
const COMMAND_VALID_ITEM_SELECTOR = `${commandAttrs.selector("item")}:not([aria-disabled="true"])`;
const CommandRootContext = new Context("Command.Root");
const CommandListContext = new Context("Command.List");
const CommandGroupContainerContext = new Context("Command.Group");
const defaultState = {
  search: "",
  value: "",
  filtered: { count: 0, items: /* @__PURE__ */ new Map(), groups: /* @__PURE__ */ new Set() }
};
class CommandRootState {
  static create(opts) {
    return CommandRootContext.set(new CommandRootState(opts));
  }
  opts;
  attachment;
  #updateScheduled = false;
  #isInitialMount = true;
  sortAfterTick = false;
  sortAndFilterAfterTick = false;
  allItems = /* @__PURE__ */ new Set();
  allGroups = /* @__PURE__ */ new Map();
  allIds = /* @__PURE__ */ new Map();
  // attempt to prevent the harsh delay when user is typing fast
  key = 0;
  viewportNode = null;
  inputNode = null;
  labelNode = null;
  // published state that the components and other things can react to
  commandState = defaultState;
  // internal state that we mutate in batches and publish to the `state` at once
  _commandState = defaultState;
  #snapshot() {
    return snapshot(this._commandState);
  }
  #scheduleUpdate() {
    if (this.#updateScheduled) return;
    this.#updateScheduled = true;
    afterTick(() => {
      this.#updateScheduled = false;
      const currentState = this.#snapshot();
      const hasStateChanged = !Object.is(this.commandState, currentState);
      if (hasStateChanged) {
        this.commandState = currentState;
        this.opts.onStateChange?.current?.(currentState);
      }
    });
  }
  setState(key, value, preventScroll) {
    if (Object.is(this._commandState[key], value)) return;
    this._commandState[key] = value;
    if (key === "search") {
      this.#filterItems();
      this.#sort();
    } else if (key === "value") {
      if (!preventScroll) this.#scrollSelectedIntoView();
    }
    this.#scheduleUpdate();
  }
  constructor(opts) {
    this.opts = opts;
    this.attachment = attachRef(this.opts.ref);
    const defaults = { ...this._commandState, value: this.opts.value.current ?? "" };
    this._commandState = defaults;
    this.commandState = defaults;
    this.onkeydown = this.onkeydown.bind(this);
  }
  /**
   * Calculates score for an item based on search text and keywords.
   * Higher score = better match.
   *
   * @param value - Item's display text
   * @param keywords - Optional keywords to boost scoring
   * @returns Score from 0-1, where 0 = no match
   */
  #score(value, keywords) {
    const filter = this.opts.filter.current ?? computeCommandScore;
    const score = value ? filter(value, this._commandState.search, keywords) : 0;
    return score;
  }
  /**
   * Sorts items and groups based on search scores.
   * Groups are sorted by their highest scoring item.
   * When no search active, selects first item.
   */
  #sort() {
    if (!this._commandState.search || this.opts.shouldFilter.current === false) {
      if (!this._commandState.value || !this.#isInitialMount) {
        this.#selectFirstItem();
      } else if (this.#isInitialMount && this._commandState.value) {
        this.#scrollInitialValue();
      }
      return;
    }
    const scores = this._commandState.filtered.items;
    const groups = [];
    for (const value of this._commandState.filtered.groups) {
      const items = this.allGroups.get(value);
      let max = 0;
      if (!items) {
        groups.push([value, max]);
        continue;
      }
      for (const item of items) {
        const score = scores.get(item);
        max = Math.max(score ?? 0, max);
      }
      groups.push([value, max]);
    }
    const listInsertionElement = this.viewportNode;
    const sorted = this.getValidItems().sort((a, b) => {
      const valueA = a.getAttribute("data-value");
      const valueB = b.getAttribute("data-value");
      const scoresA = scores.get(valueA) ?? 0;
      const scoresB = scores.get(valueB) ?? 0;
      return scoresB - scoresA;
    });
    for (const item of sorted) {
      const group = item.closest(COMMAND_GROUP_ITEMS_SELECTOR);
      if (group) {
        const itemToAppend = item.parentElement === group ? item : item.closest(`${COMMAND_GROUP_ITEMS_SELECTOR} > *`);
        if (itemToAppend) {
          group.appendChild(itemToAppend);
        }
      } else {
        const itemToAppend = item.parentElement === listInsertionElement ? item : item.closest(`${COMMAND_GROUP_ITEMS_SELECTOR} > *`);
        if (itemToAppend) {
          listInsertionElement?.appendChild(itemToAppend);
        }
      }
    }
    const sortedGroups = groups.sort((a, b) => b[1] - a[1]);
    for (const group of sortedGroups) {
      const element = listInsertionElement?.querySelector(`${COMMAND_GROUP_SELECTOR}[${COMMAND_VALUE_ATTR}="${cssEscape(group[0])}"]`);
      element?.parentElement?.appendChild(element);
    }
    this.#selectFirstItem();
  }
  /**
   * Sets current value and triggers re-render if cleared.
   *
   * @param value - New value to set
   */
  setValue(value, opts) {
    if (value !== this.opts.value.current && value === "") {
      afterTick(() => {
        this.key++;
      });
    }
    this.setState("value", value, opts);
    this.opts.value.current = value;
  }
  /**
   * Selects first non-disabled item on next tick.
   */
  #selectFirstItem() {
    afterTick(() => {
      const item = this.getValidItems().find((item2) => item2.getAttribute("aria-disabled") !== "true");
      const value = item?.getAttribute(COMMAND_VALUE_ATTR);
      const shouldPreventScroll = this.#isInitialMount && this.opts.disableInitialScroll.current;
      this.setValue(value ?? "", shouldPreventScroll);
      this.#isInitialMount = false;
    });
  }
  /**
   * Scrolls the initial value into view if it exists and is not the first item.
   * Called during initial mount when a value is provided.
   */
  #scrollInitialValue() {
    afterTick(() => {
      const shouldPreventScroll = this.opts.disableInitialScroll.current;
      if (!shouldPreventScroll) {
        this.#scrollSelectedIntoView();
      }
      this.#isInitialMount = false;
    });
  }
  /**
   * Updates filtered items/groups based on search.
   * Recalculates scores and filtered count.
   */
  #filterItems() {
    if (!this._commandState.search || this.opts.shouldFilter.current === false) {
      this._commandState.filtered.count = this.allItems.size;
      return;
    }
    this._commandState.filtered.groups = /* @__PURE__ */ new Set();
    let itemCount = 0;
    for (const id of this.allItems) {
      const value = this.allIds.get(id)?.value ?? "";
      const keywords = this.allIds.get(id)?.keywords ?? [];
      const rank = this.#score(value, keywords);
      this._commandState.filtered.items.set(id, rank);
      if (rank > 0) itemCount++;
    }
    for (const [groupId, group] of this.allGroups) {
      for (const itemId of group) {
        const currItem = this._commandState.filtered.items.get(itemId);
        if (currItem && currItem > 0) {
          this._commandState.filtered.groups.add(groupId);
          break;
        }
      }
    }
    this._commandState.filtered.count = itemCount;
  }
  /**
   * Gets all non-disabled, visible command items.
   *
   * @returns Array of valid item elements
   * @remarks Exposed for direct item access and bound checking
   */
  getValidItems() {
    const node = this.opts.ref.current;
    if (!node) return [];
    const validItems = Array.from(node.querySelectorAll(COMMAND_VALID_ITEM_SELECTOR)).filter((el) => !!el);
    return validItems;
  }
  /**
   * Gets all visible command items.
   *
   * @returns Array of valid item elements
   * @remarks Exposed for direct item access and bound checking
   */
  getVisibleItems() {
    const node = this.opts.ref.current;
    if (!node) return [];
    const visibleItems = Array.from(node.querySelectorAll(COMMAND_ITEM_SELECTOR)).filter((el) => !!el);
    return visibleItems;
  }
  /** Returns all visible items in a matrix structure
   *
   * @remarks Returns empty if the command isn't configured as a grid
   *
   * @returns
   */
  get itemsGrid() {
    if (!this.isGrid) return [];
    const columns = this.opts.columns.current ?? 1;
    const items = this.getVisibleItems();
    const grid = [[]];
    let currentGroup = items[0]?.getAttribute("data-group");
    let column = 0;
    let row = 0;
    for (let i = 0; i < items.length; i++) {
      const item = items[i];
      const itemGroup = item?.getAttribute("data-group");
      if (currentGroup !== itemGroup) {
        currentGroup = itemGroup;
        column = 1;
        row++;
        grid.push([{ index: i, firstRowOfGroup: true, ref: item }]);
      } else {
        column++;
        if (column > columns) {
          row++;
          column = 1;
          grid.push([]);
        }
        grid[row]?.push({
          index: i,
          firstRowOfGroup: grid[row]?.[0]?.firstRowOfGroup ?? i === 0,
          ref: item
        });
      }
    }
    return grid;
  }
  /**
   * Gets currently selected command item.
   *
   * @returns Selected element or undefined
   */
  #getSelectedItem() {
    const node = this.opts.ref.current;
    if (!node) return;
    const selectedNode = node.querySelector(`${COMMAND_VALID_ITEM_SELECTOR}[data-selected]`);
    if (!selectedNode) return;
    return selectedNode;
  }
  /**
   * Scrolls selected item into view.
   * Special handling for first items in groups.
   */
  #scrollSelectedIntoView() {
    afterTick(() => {
      const item = this.#getSelectedItem();
      if (!item) return;
      const grandparent = item.parentElement?.parentElement;
      if (!grandparent) return;
      if (this.isGrid) {
        const isFirstRowOfGroup = this.#itemIsFirstRowOfGroup(item);
        item.scrollIntoView({ block: "nearest" });
        if (isFirstRowOfGroup) {
          const closestGroupHeader = item?.closest(COMMAND_GROUP_SELECTOR)?.querySelector(COMMAND_GROUP_HEADING_SELECTOR);
          closestGroupHeader?.scrollIntoView({ block: "nearest" });
          return;
        }
      } else {
        const firstChildOfParent = getFirstNonCommentChild(grandparent);
        if (firstChildOfParent && firstChildOfParent.dataset?.value === item.dataset?.value) {
          const closestGroupHeader = item?.closest(COMMAND_GROUP_SELECTOR)?.querySelector(COMMAND_GROUP_HEADING_SELECTOR);
          closestGroupHeader?.scrollIntoView({ block: "nearest" });
          return;
        }
      }
      item.scrollIntoView({ block: "nearest" });
    });
  }
  #itemIsFirstRowOfGroup(item) {
    const grid = this.itemsGrid;
    if (grid.length === 0) return false;
    for (let r = 0; r < grid.length; r++) {
      const row = grid[r];
      if (row === void 0) continue;
      for (let c = 0; c < row.length; c++) {
        const column = row[c];
        if (column === void 0 || column.ref !== item) continue;
        return column.firstRowOfGroup;
      }
    }
    return false;
  }
  /**
   * Sets selection to item at specified index in valid items array.
   * If index is out of bounds, does nothing.
   *
   * @param index - Zero-based index of item to select
   * @remarks
   * Uses `getValidItems()` to get selectable items, filtering out disabled/hidden ones.
   * Access valid items directly via `getValidItems()` to check bounds before calling.
   *
   * @example
   * // get valid items length for bounds check
   * const items = getValidItems()
   * if (index < items.length) {
   *   updateSelectedToIndex(index)
   * }
   */
  updateSelectedToIndex(index) {
    const item = this.getValidItems()[index];
    if (!item) return;
    this.setValue(item.getAttribute(COMMAND_VALUE_ATTR) ?? "");
  }
  /**
   * Updates selected item by moving up/down relative to current selection.
   * Handles wrapping when loop option is enabled.
   *
   * @param change - Direction to move: 1 for next item, -1 for previous item
   * @remarks
   * The loop behavior wraps:
   * - From last item to first when moving next
   * - From first item to last when moving previous
   *
   * Uses `getValidItems()` to get all selectable items, which filters out disabled/hidden items.
   * You can call `getValidItems()` directly to get the current valid items array.
   *
   * @example
   * // select next item
   * updateSelectedByItem(1)
   *
   * // get all valid items
   * const items = getValidItems()
   */
  updateSelectedByItem(change) {
    const selected = this.#getSelectedItem();
    const items = this.getValidItems();
    const index = items.findIndex((item) => item === selected);
    let newSelected = items[index + change];
    if (this.opts.loop.current) {
      newSelected = index + change < 0 ? items[items.length - 1] : index + change === items.length ? items[0] : items[index + change];
    }
    if (newSelected) {
      this.setValue(newSelected.getAttribute(COMMAND_VALUE_ATTR) ?? "");
    }
  }
  /**
   * Moves selection to the first valid item in the next/previous group.
   * If no group is found, falls back to selecting the next/previous item globally.
   *
   * @param change - Direction to move: 1 for next group, -1 for previous group
   * @example
   * // move to first item in next group
   * updateSelectedByGroup(1)
   *
   * // move to first item in previous group
   * updateSelectedByGroup(-1)
   */
  updateSelectedByGroup(change) {
    const selected = this.#getSelectedItem();
    let group = selected?.closest(COMMAND_GROUP_SELECTOR);
    let item;
    while (group && !item) {
      group = change > 0 ? findNextSibling(group, COMMAND_GROUP_SELECTOR) : findPreviousSibling(group, COMMAND_GROUP_SELECTOR);
      item = group?.querySelector(COMMAND_VALID_ITEM_SELECTOR);
    }
    if (item) {
      this.setValue(item.getAttribute(COMMAND_VALUE_ATTR) ?? "");
    } else {
      this.updateSelectedByItem(change);
    }
  }
  /**
   * Maps item id to display value and search keywords.
   * Returns cleanup function to remove mapping.
   *
   * @param id - Unique item identifier
   * @param value - Display text
   * @param keywords - Optional search boost terms
   * @returns Cleanup function
   */
  registerValue(value, keywords) {
    if (!(value && value === this.allIds.get(value)?.value)) {
      this.allIds.set(value, { value, keywords });
    }
    this._commandState.filtered.items.set(value, this.#score(value, keywords));
    if (!this.sortAfterTick) {
      this.sortAfterTick = true;
      afterTick(() => {
        this.#sort();
        this.sortAfterTick = false;
      });
    }
    return () => {
      this.allIds.delete(value);
    };
  }
  /**
   * Registers item in command list and its group.
   * Handles filtering, sorting and selection updates.
   *
   * @param id - Item identifier
   * @param groupId - Optional group to add item to
   * @returns Cleanup function that handles selection
   */
  registerItem(id, groupId) {
    this.allItems.add(id);
    if (groupId) {
      if (!this.allGroups.has(groupId)) {
        this.allGroups.set(groupId, /* @__PURE__ */ new Set([id]));
      } else {
        this.allGroups.get(groupId).add(id);
      }
    }
    if (!this.sortAndFilterAfterTick) {
      this.sortAndFilterAfterTick = true;
      afterTick(() => {
        this.#filterItems();
        this.#sort();
        this.sortAndFilterAfterTick = false;
      });
    }
    this.#scheduleUpdate();
    return () => {
      const selectedItem = this.#getSelectedItem();
      this.allItems.delete(id);
      this.commandState.filtered.items.delete(id);
      this.#filterItems();
      if (selectedItem?.getAttribute("id") === id) {
        this.#selectFirstItem();
      }
      this.#scheduleUpdate();
    };
  }
  /**
   * Creates empty group if not exists.
   *
   * @param id - Group identifier
   * @returns Cleanup function
   */
  registerGroup(id) {
    if (!this.allGroups.has(id)) {
      this.allGroups.set(id, /* @__PURE__ */ new Set());
    }
    return () => {
      this.allIds.delete(id);
      this.allGroups.delete(id);
    };
  }
  get isGrid() {
    return this.opts.columns.current !== null;
  }
  /**
   * Selects last valid item.
   */
  #last() {
    return this.updateSelectedToIndex(this.getValidItems().length - 1);
  }
  /**
   * Handles next item selection:
   * - Meta: Jump to last
   * - Alt: Next group
   * - Default: Next item
   *
   * @param e - Keyboard event
   */
  #next(e) {
    e.preventDefault();
    if (e.metaKey) {
      this.#last();
    } else if (e.altKey) {
      this.updateSelectedByGroup(1);
    } else {
      this.updateSelectedByItem(1);
    }
  }
  #down(e) {
    if (this.opts.columns.current === null) return;
    e.preventDefault();
    if (e.metaKey) {
      this.updateSelectedByGroup(1);
    } else {
      this.updateSelectedByItem(this.#nextRowColumnOffset(e));
    }
  }
  #getColumn(item, grid) {
    if (grid.length === 0) return null;
    for (let r = 0; r < grid.length; r++) {
      const row = grid[r];
      if (row === void 0) continue;
      for (let c = 0; c < row.length; c++) {
        const column = row[c];
        if (column === void 0 || column.ref !== item) continue;
        return { columnIndex: c, rowIndex: r };
      }
    }
    return null;
  }
  #nextRowColumnOffset(e) {
    const grid = this.itemsGrid;
    const selected = this.#getSelectedItem();
    if (!selected) return 0;
    const column = this.#getColumn(selected, grid);
    if (!column) return 0;
    let newItem = null;
    const skipRows = e.altKey ? 1 : 0;
    if (e.altKey && column.rowIndex === grid.length - 2 && !this.opts.loop.current) {
      newItem = this.#findNextNonDisabledItem({
        start: grid.length - 1,
        end: grid.length,
        expectedColumnIndex: column.columnIndex,
        grid
      });
    } else if (column.rowIndex === grid.length - 1) {
      if (!this.opts.loop.current) return 0;
      newItem = this.#findNextNonDisabledItem({
        start: 0 + skipRows,
        end: column.rowIndex,
        expectedColumnIndex: column.columnIndex,
        grid
      });
    } else {
      newItem = this.#findNextNonDisabledItem({
        start: column.rowIndex + 1 + skipRows,
        end: grid.length,
        expectedColumnIndex: column.columnIndex,
        grid
      });
      if (newItem === null && this.opts.loop.current) {
        newItem = this.#findNextNonDisabledItem({
          start: 0,
          end: column.rowIndex,
          expectedColumnIndex: column.columnIndex,
          grid
        });
      }
    }
    return this.#calculateOffset(selected, newItem);
  }
  /** Attempts to find the next non-disabled column that matches the expected column.
   *
   * @remarks
   * - Skips over disabled columns
   * - When a row is shorter than the expected column it defaults to the last item in the row
   *
   * @param param0
   * @returns
   */
  #findNextNonDisabledItem({ start, end, grid, expectedColumnIndex }) {
    let newItem = null;
    for (let r = start; r < end; r++) {
      const row = grid[r];
      newItem = row[expectedColumnIndex]?.ref ?? null;
      if (newItem !== null && itemIsDisabled(newItem)) {
        newItem = null;
        continue;
      }
      if (newItem === null) {
        for (let i = row.length - 1; i >= 0; i--) {
          const item = row[row.length - 1];
          if (item === void 0 || itemIsDisabled(item.ref)) continue;
          newItem = item.ref;
          break;
        }
      }
      break;
    }
    return newItem;
  }
  #calculateOffset(selected, newSelected) {
    if (newSelected === null) return 0;
    const items = this.getValidItems();
    const ogIndex = items.findIndex((item) => item === selected);
    const newIndex = items.findIndex((item) => item === newSelected);
    return newIndex - ogIndex;
  }
  #up(e) {
    if (this.opts.columns.current === null) return;
    e.preventDefault();
    if (e.metaKey) {
      this.updateSelectedByGroup(-1);
    } else {
      this.updateSelectedByItem(this.#previousRowColumnOffset(e));
    }
  }
  #previousRowColumnOffset(e) {
    const grid = this.itemsGrid;
    const selected = this.#getSelectedItem();
    if (selected === void 0) return 0;
    const column = this.#getColumn(selected, grid);
    if (column === null) return 0;
    let newItem = null;
    const skipRows = e.altKey ? 1 : 0;
    if (e.altKey && column.rowIndex === 1 && this.opts.loop.current === false) {
      newItem = this.#findNextNonDisabledItemDesc({
        start: 0,
        end: 0,
        expectedColumnIndex: column.columnIndex,
        grid
      });
    } else if (column.rowIndex === 0) {
      if (this.opts.loop.current === false) return 0;
      newItem = this.#findNextNonDisabledItemDesc({
        start: grid.length - 1 - skipRows,
        end: column.rowIndex + 1,
        expectedColumnIndex: column.columnIndex,
        grid
      });
    } else {
      newItem = this.#findNextNonDisabledItemDesc({
        start: column.rowIndex - 1 - skipRows,
        end: 0,
        expectedColumnIndex: column.columnIndex,
        grid
      });
      if (newItem === null && this.opts.loop.current) {
        newItem = this.#findNextNonDisabledItemDesc({
          start: grid.length - 1,
          end: column.rowIndex + 1,
          expectedColumnIndex: column.columnIndex,
          grid
        });
      }
    }
    return this.#calculateOffset(selected, newItem);
  }
  /**
   * Attempts to find the next non-disabled column that matches the expected column.
   *
   * @remarks
   * - Skips over disabled columns
   * - When a row is shorter than the expected column it defaults to the last item in the row
   */
  #findNextNonDisabledItemDesc({ start, end, grid, expectedColumnIndex }) {
    let newItem = null;
    for (let r = start; r >= end; r--) {
      const row = grid[r];
      if (row === void 0) continue;
      newItem = row[expectedColumnIndex]?.ref ?? null;
      if (newItem !== null && itemIsDisabled(newItem)) {
        newItem = null;
        continue;
      }
      if (newItem === null) {
        for (let i = row.length - 1; i >= 0; i--) {
          const item = row[row.length - 1];
          if (item === void 0 || itemIsDisabled(item.ref)) continue;
          newItem = item.ref;
          break;
        }
      }
      break;
    }
    return newItem;
  }
  /**
   * Handles previous item selection:
   * - Meta: Jump to first
   * - Alt: Previous group
   * - Default: Previous item
   *
   * @param e - Keyboard event
   */
  #prev(e) {
    e.preventDefault();
    if (e.metaKey) {
      this.updateSelectedToIndex(0);
    } else if (e.altKey) {
      this.updateSelectedByGroup(-1);
    } else {
      this.updateSelectedByItem(-1);
    }
  }
  onkeydown(e) {
    const isVim = this.opts.vimBindings.current && e.ctrlKey;
    switch (e.key) {
      case n:
      case j: {
        if (isVim) {
          if (this.isGrid) {
            this.#down(e);
          } else {
            this.#next(e);
          }
        }
        break;
      }
      case l: {
        if (isVim) {
          if (this.isGrid) {
            this.#next(e);
          }
        }
        break;
      }
      case ARROW_DOWN:
        if (this.isGrid) {
          this.#down(e);
        } else {
          this.#next(e);
        }
        break;
      case ARROW_RIGHT:
        if (!this.isGrid) break;
        this.#next(e);
        break;
      case p:
      case k: {
        if (isVim) {
          if (this.isGrid) {
            this.#up(e);
          } else {
            this.#prev(e);
          }
        }
        break;
      }
      case h: {
        if (isVim && this.isGrid) {
          this.#prev(e);
        }
        break;
      }
      case ARROW_UP:
        if (this.isGrid) {
          this.#up(e);
        } else {
          this.#prev(e);
        }
        break;
      case ARROW_LEFT:
        if (!this.isGrid) break;
        this.#prev(e);
        break;
      case HOME:
        e.preventDefault();
        this.updateSelectedToIndex(0);
        break;
      case END:
        e.preventDefault();
        this.#last();
        break;
      case ENTER: {
        if (!e.isComposing && e.keyCode !== 229) {
          e.preventDefault();
          const item = this.#getSelectedItem();
          if (item) {
            item?.click();
          }
        }
      }
    }
  }
  #props = derived(() => ({
    id: this.opts.id.current,
    role: "application",
    [commandAttrs.root]: "",
    tabindex: -1,
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
function itemIsDisabled(item) {
  return item.getAttribute("aria-disabled") === "true";
}
class CommandEmptyState {
  static create(opts) {
    return new CommandEmptyState(opts, CommandRootContext.get());
  }
  opts;
  root;
  attachment;
  #shouldRender = derived(() => {
    return this.root._commandState.filtered.count === 0 && this.#isInitialRender === false || this.opts.forceMount.current;
  });
  get shouldRender() {
    return this.#shouldRender();
  }
  set shouldRender($$value) {
    return this.#shouldRender($$value);
  }
  #isInitialRender = true;
  constructor(opts, root) {
    this.opts = opts;
    this.root = root;
    this.attachment = attachRef(this.opts.ref);
  }
  #props = derived(() => ({
    id: this.opts.id.current,
    role: "presentation",
    [commandAttrs.empty]: "",
    ...this.attachment
  }));
  get props() {
    return this.#props();
  }
  set props($$value) {
    return this.#props($$value);
  }
}
class CommandGroupContainerState {
  static create(opts) {
    return CommandGroupContainerContext.set(new CommandGroupContainerState(opts, CommandRootContext.get()));
  }
  opts;
  root;
  attachment;
  #shouldRender = derived(() => {
    if (this.opts.forceMount.current) return true;
    if (this.root.opts.shouldFilter.current === false) return true;
    if (!this.root.commandState.search) return true;
    return this.root._commandState.filtered.groups.has(this.trueValue);
  });
  get shouldRender() {
    return this.#shouldRender();
  }
  set shouldRender($$value) {
    return this.#shouldRender($$value);
  }
  headingNode = null;
  trueValue = "";
  constructor(opts, root) {
    this.opts = opts;
    this.root = root;
    this.attachment = attachRef(this.opts.ref);
    this.trueValue = opts.value.current ?? opts.id.current;
    watch(() => this.trueValue, () => {
      return this.root.registerGroup(this.trueValue);
    });
  }
  #props = derived(() => ({
    id: this.opts.id.current,
    role: "presentation",
    hidden: this.shouldRender ? void 0 : true,
    "data-value": this.trueValue,
    [commandAttrs.group]: "",
    ...this.attachment
  }));
  get props() {
    return this.#props();
  }
  set props($$value) {
    return this.#props($$value);
  }
}
class CommandGroupHeadingState {
  static create(opts) {
    return new CommandGroupHeadingState(opts, CommandGroupContainerContext.get());
  }
  opts;
  group;
  attachment;
  constructor(opts, group) {
    this.opts = opts;
    this.group = group;
    this.attachment = attachRef(this.opts.ref, (v) => this.group.headingNode = v);
  }
  #props = derived(() => ({
    id: this.opts.id.current,
    [commandAttrs["group-heading"]]: "",
    ...this.attachment
  }));
  get props() {
    return this.#props();
  }
  set props($$value) {
    return this.#props($$value);
  }
}
class CommandGroupItemsState {
  static create(opts) {
    return new CommandGroupItemsState(opts, CommandGroupContainerContext.get());
  }
  opts;
  group;
  attachment;
  constructor(opts, group) {
    this.opts = opts;
    this.group = group;
    this.attachment = attachRef(this.opts.ref);
  }
  #props = derived(() => ({
    id: this.opts.id.current,
    role: "group",
    [commandAttrs["group-items"]]: "",
    "aria-labelledby": this.group.headingNode?.id ?? void 0,
    ...this.attachment
  }));
  get props() {
    return this.#props();
  }
  set props($$value) {
    return this.#props($$value);
  }
}
class CommandInputState {
  static create(opts) {
    return new CommandInputState(opts, CommandRootContext.get());
  }
  opts;
  root;
  attachment;
  #selectedItemId = derived(() => {
    const item = this.root.viewportNode?.querySelector(`${COMMAND_ITEM_SELECTOR}[${COMMAND_VALUE_ATTR}="${cssEscape(this.root.opts.value.current)}"]`);
    if (item === void 0 || item === null) return;
    return item.getAttribute("id") ?? void 0;
  });
  constructor(opts, root) {
    this.opts = opts;
    this.root = root;
    this.attachment = attachRef(this.opts.ref, (v) => this.root.inputNode = v);
    watch(() => this.opts.ref.current, () => {
      const node = this.opts.ref.current;
      if (node && this.opts.autofocus.current) {
        afterSleep(10, () => node.focus());
      }
    });
    watch(() => this.opts.value.current, () => {
      if (this.root.commandState.search !== this.opts.value.current) {
        this.root.setState("search", this.opts.value.current);
      }
    });
  }
  #props = derived(() => ({
    id: this.opts.id.current,
    type: "text",
    [commandAttrs.input]: "",
    autocomplete: "off",
    autocorrect: "off",
    spellcheck: false,
    "aria-autocomplete": "list",
    role: "combobox",
    "aria-expanded": boolToStr(true),
    "aria-controls": this.root.viewportNode?.id ?? void 0,
    "aria-labelledby": this.root.labelNode?.id ?? void 0,
    "aria-activedescendant": this.#selectedItemId(),
    ...this.attachment
  }));
  get props() {
    return this.#props();
  }
  set props($$value) {
    return this.#props($$value);
  }
}
class CommandItemState {
  static create(opts) {
    const group = CommandGroupContainerContext.getOr(null);
    return new CommandItemState({ ...opts, group }, CommandRootContext.get());
  }
  opts;
  root;
  attachment;
  #group = null;
  #trueForceMount = derived(() => {
    return this.opts.forceMount.current || this.#group?.opts.forceMount.current === true;
  });
  #shouldRender = derived(() => {
    this.opts.ref.current;
    if (this.#trueForceMount() || this.root.opts.shouldFilter.current === false || !this.root.commandState.search) {
      return true;
    }
    const currentScore = this.root.commandState.filtered.items.get(this.trueValue);
    if (currentScore === void 0) return false;
    return currentScore > 0;
  });
  get shouldRender() {
    return this.#shouldRender();
  }
  set shouldRender($$value) {
    return this.#shouldRender($$value);
  }
  #isSelected = derived(() => this.root.opts.value.current === this.trueValue && this.trueValue !== "");
  get isSelected() {
    return this.#isSelected();
  }
  set isSelected($$value) {
    return this.#isSelected($$value);
  }
  trueValue = "";
  constructor(opts, root) {
    this.opts = opts;
    this.root = root;
    this.#group = CommandGroupContainerContext.getOr(null);
    this.trueValue = opts.value.current;
    this.attachment = attachRef(this.opts.ref);
    watch(
      [
        () => this.trueValue,
        () => this.#group?.trueValue,
        () => this.opts.forceMount.current
      ],
      () => {
        if (this.opts.forceMount.current || !this.trueValue) return;
        return this.root.registerItem(this.trueValue, this.#group?.trueValue);
      }
    );
    watch([() => this.opts.value.current, () => this.opts.ref.current], () => {
      if (this.opts.value.current) {
        this.trueValue = this.opts.value.current;
      } else if (this.opts.ref.current?.textContent) {
        this.trueValue = this.opts.ref.current.textContent.trim();
      }
      if (this.trueValue) {
        this.root.registerValue(this.trueValue, opts.keywords.current.map((kw) => kw.trim()));
        this.opts.ref.current?.setAttribute(COMMAND_VALUE_ATTR, this.trueValue);
      }
    });
    this.onclick = this.onclick.bind(this);
    this.onpointermove = this.onpointermove.bind(this);
  }
  #onSelect() {
    if (this.opts.disabled.current) return;
    this.#select();
    this.opts.onSelect?.current();
  }
  #select() {
    if (this.opts.disabled.current) return;
    this.root.setValue(this.trueValue, true);
  }
  onpointermove(_) {
    if (this.opts.disabled.current || this.root.opts.disablePointerSelection.current) return;
    this.#select();
  }
  onclick(_) {
    if (this.opts.disabled.current) return;
    this.#onSelect();
  }
  #props = derived(() => ({
    id: this.opts.id.current,
    "aria-disabled": boolToStr(this.opts.disabled.current),
    "aria-selected": boolToStr(this.isSelected),
    "data-disabled": boolToEmptyStrOrUndef(this.opts.disabled.current),
    "data-selected": boolToEmptyStrOrUndef(this.isSelected),
    "data-value": this.trueValue,
    "data-group": this.#group?.trueValue,
    [commandAttrs.item]: "",
    role: "option",
    onpointermove: this.onpointermove,
    onclick: this.onclick,
    ...this.attachment
  }));
  get props() {
    return this.#props();
  }
  set props($$value) {
    return this.#props($$value);
  }
}
class CommandLoadingState {
  static create(opts) {
    return new CommandLoadingState(opts);
  }
  opts;
  attachment;
  constructor(opts) {
    this.opts = opts;
    this.attachment = attachRef(this.opts.ref);
  }
  #props = derived(() => ({
    id: this.opts.id.current,
    role: "progressbar",
    "aria-valuenow": this.opts.progress.current,
    "aria-valuemin": 0,
    "aria-valuemax": 100,
    "aria-label": "Loading...",
    [commandAttrs.loading]: "",
    ...this.attachment
  }));
  get props() {
    return this.#props();
  }
  set props($$value) {
    return this.#props($$value);
  }
}
class CommandListState {
  static create(opts) {
    return CommandListContext.set(new CommandListState(opts, CommandRootContext.get()));
  }
  opts;
  root;
  attachment;
  constructor(opts, root) {
    this.opts = opts;
    this.root = root;
    this.attachment = attachRef(this.opts.ref);
  }
  #props = derived(() => ({
    id: this.opts.id.current,
    role: "listbox",
    "aria-label": this.opts.ariaLabel.current,
    [commandAttrs.list]: "",
    ...this.attachment
  }));
  get props() {
    return this.#props();
  }
  set props($$value) {
    return this.#props($$value);
  }
}
class CommandLabelState {
  static create(opts) {
    return new CommandLabelState(opts, CommandRootContext.get());
  }
  opts;
  root;
  attachment;
  constructor(opts, root) {
    this.opts = opts;
    this.root = root;
    this.attachment = attachRef(this.opts.ref, (v) => this.root.labelNode = v);
  }
  #props = derived(() => ({
    id: this.opts.id.current,
    [commandAttrs["input-label"]]: "",
    for: this.opts.for?.current,
    style: srOnlyStyles,
    ...this.attachment
  }));
  get props() {
    return this.#props();
  }
  set props($$value) {
    return this.#props($$value);
  }
}
function _command_label($$renderer, $$props) {
  $$renderer.component(($$renderer2) => {
    const uid = props_id($$renderer2);
    let {
      id = createId(uid),
      ref = null,
      children,
      $$slots,
      $$events,
      ...restProps
    } = $$props;
    const labelState = CommandLabelState.create({
      id: boxWith(() => id),
      ref: boxWith(() => ref, (v) => ref = v)
    });
    const mergedProps = derived(() => mergeProps(restProps, labelState.props));
    $$renderer2.push(`<label${attributes({ ...mergedProps() })}>`);
    children?.($$renderer2);
    $$renderer2.push(`<!----></label>`);
    bind_props($$props, { ref });
  });
}
function Command$1($$renderer, $$props) {
  $$renderer.component(($$renderer2) => {
    const uid = props_id($$renderer2);
    let {
      id = createId(uid),
      ref = null,
      value = "",
      onValueChange = noop,
      onStateChange = noop,
      loop = false,
      shouldFilter = true,
      filter = computeCommandScore,
      label = "",
      vimBindings = true,
      disablePointerSelection = false,
      disableInitialScroll = false,
      columns = null,
      children,
      child,
      $$slots,
      $$events,
      ...restProps
    } = $$props;
    const rootState = CommandRootState.create({
      id: boxWith(() => id),
      ref: boxWith(() => ref, (v) => ref = v),
      filter: boxWith(() => filter),
      shouldFilter: boxWith(() => shouldFilter),
      loop: boxWith(() => loop),
      value: boxWith(() => value, (v) => {
        if (value !== v) {
          value = v;
          onValueChange(v);
        }
      }),
      vimBindings: boxWith(() => vimBindings),
      disablePointerSelection: boxWith(() => disablePointerSelection),
      disableInitialScroll: boxWith(() => disableInitialScroll),
      onStateChange: boxWith(() => onStateChange),
      columns: boxWith(() => columns)
    });
    const updateSelectedToIndex = (i) => rootState.updateSelectedToIndex(i);
    const updateSelectedByGroup = (c) => rootState.updateSelectedByGroup(c);
    const updateSelectedByItem = (c) => rootState.updateSelectedByItem(c);
    const getValidItems = () => rootState.getValidItems();
    const mergedProps = derived(() => mergeProps(restProps, rootState.props));
    function Label2($$renderer3) {
      _command_label($$renderer3, {
        children: ($$renderer4) => {
          $$renderer4.push(`<!---->${escape_html(label)}`);
        },
        $$slots: { default: true }
      });
    }
    if (child) {
      $$renderer2.push("<!--[0-->");
      Label2($$renderer2);
      $$renderer2.push(`<!----> `);
      child($$renderer2, { props: mergedProps() });
      $$renderer2.push(`<!---->`);
    } else {
      $$renderer2.push("<!--[-1-->");
      $$renderer2.push(`<div${attributes({ ...mergedProps() })}>`);
      Label2($$renderer2);
      $$renderer2.push(`<!----> `);
      children?.($$renderer2);
      $$renderer2.push(`<!----></div>`);
    }
    $$renderer2.push(`<!--]-->`);
    bind_props($$props, {
      ref,
      value,
      updateSelectedToIndex,
      updateSelectedByGroup,
      updateSelectedByItem,
      getValidItems
    });
  });
}
function Command_empty$1($$renderer, $$props) {
  $$renderer.component(($$renderer2) => {
    const uid = props_id($$renderer2);
    let {
      id = createId(uid),
      ref = null,
      children,
      child,
      forceMount = false,
      $$slots,
      $$events,
      ...restProps
    } = $$props;
    const emptyState = CommandEmptyState.create({
      id: boxWith(() => id),
      ref: boxWith(() => ref, (v) => ref = v),
      forceMount: boxWith(() => forceMount)
    });
    const mergedProps = derived(() => mergeProps(emptyState.props, restProps));
    if (emptyState.shouldRender) {
      $$renderer2.push("<!--[0-->");
      if (child) {
        $$renderer2.push("<!--[0-->");
        child($$renderer2, { props: mergedProps() });
        $$renderer2.push(`<!---->`);
      } else {
        $$renderer2.push("<!--[-1-->");
        $$renderer2.push(`<div${attributes({ ...mergedProps() })}>`);
        children?.($$renderer2);
        $$renderer2.push(`<!----></div>`);
      }
      $$renderer2.push(`<!--]-->`);
    } else {
      $$renderer2.push("<!--[-1-->");
    }
    $$renderer2.push(`<!--]-->`);
    bind_props($$props, { ref });
  });
}
function Command_group$1($$renderer, $$props) {
  $$renderer.component(($$renderer2) => {
    const uid = props_id($$renderer2);
    let {
      id = createId(uid),
      ref = null,
      value = "",
      forceMount = false,
      children,
      child,
      $$slots,
      $$events,
      ...restProps
    } = $$props;
    const groupState = CommandGroupContainerState.create({
      id: boxWith(() => id),
      ref: boxWith(() => ref, (v) => ref = v),
      forceMount: boxWith(() => forceMount),
      value: boxWith(() => value)
    });
    const mergedProps = derived(() => mergeProps(restProps, groupState.props));
    if (child) {
      $$renderer2.push("<!--[0-->");
      child($$renderer2, { props: mergedProps() });
      $$renderer2.push(`<!---->`);
    } else {
      $$renderer2.push("<!--[-1-->");
      $$renderer2.push(`<div${attributes({ ...mergedProps() })}>`);
      children?.($$renderer2);
      $$renderer2.push(`<!----></div>`);
    }
    $$renderer2.push(`<!--]-->`);
    bind_props($$props, { ref });
  });
}
function Command_group_heading($$renderer, $$props) {
  $$renderer.component(($$renderer2) => {
    const uid = props_id($$renderer2);
    let {
      id = createId(uid),
      ref = null,
      children,
      child,
      $$slots,
      $$events,
      ...restProps
    } = $$props;
    const headingState = CommandGroupHeadingState.create({
      id: boxWith(() => id),
      ref: boxWith(() => ref, (v) => ref = v)
    });
    const mergedProps = derived(() => mergeProps(restProps, headingState.props));
    if (child) {
      $$renderer2.push("<!--[0-->");
      child($$renderer2, { props: mergedProps() });
      $$renderer2.push(`<!---->`);
    } else {
      $$renderer2.push("<!--[-1-->");
      $$renderer2.push(`<div${attributes({ ...mergedProps() })}>`);
      children?.($$renderer2);
      $$renderer2.push(`<!----></div>`);
    }
    $$renderer2.push(`<!--]-->`);
    bind_props($$props, { ref });
  });
}
function Command_group_items($$renderer, $$props) {
  $$renderer.component(($$renderer2) => {
    const uid = props_id($$renderer2);
    let {
      id = createId(uid),
      ref = null,
      children,
      child,
      $$slots,
      $$events,
      ...restProps
    } = $$props;
    const groupItemsState = CommandGroupItemsState.create({
      id: boxWith(() => id),
      ref: boxWith(() => ref, (v) => ref = v)
    });
    const mergedProps = derived(() => mergeProps(restProps, groupItemsState.props));
    $$renderer2.push(`<div style="display: contents;">`);
    if (child) {
      $$renderer2.push("<!--[0-->");
      child($$renderer2, { props: mergedProps() });
      $$renderer2.push(`<!---->`);
    } else {
      $$renderer2.push("<!--[-1-->");
      $$renderer2.push(`<div${attributes({ ...mergedProps() })}>`);
      children?.($$renderer2);
      $$renderer2.push(`<!----></div>`);
    }
    $$renderer2.push(`<!--]--></div>`);
    bind_props($$props, { ref });
  });
}
function Command_input$1($$renderer, $$props) {
  $$renderer.component(($$renderer2) => {
    const uid = props_id($$renderer2);
    let {
      value = "",
      autofocus = false,
      id = createId(uid),
      ref = null,
      child,
      $$slots,
      $$events,
      ...restProps
    } = $$props;
    const inputState = CommandInputState.create({
      id: boxWith(() => id),
      ref: boxWith(() => ref, (v) => ref = v),
      value: boxWith(() => value, (v) => {
        value = v;
      }),
      autofocus: boxWith(() => autofocus ?? false)
    });
    const mergedProps = derived(() => mergeProps(restProps, inputState.props));
    if (child) {
      $$renderer2.push("<!--[0-->");
      child($$renderer2, { props: mergedProps() });
      $$renderer2.push(`<!---->`);
    } else {
      $$renderer2.push("<!--[-1-->");
      $$renderer2.push(`<input${attributes({ ...mergedProps(), value }, void 0, void 0, void 0, 4)}/>`);
    }
    $$renderer2.push(`<!--]-->`);
    bind_props($$props, { value, ref });
  });
}
function Command_item$1($$renderer, $$props) {
  $$renderer.component(($$renderer2) => {
    const uid = props_id($$renderer2);
    let {
      id = createId(uid),
      ref = null,
      value = "",
      disabled = false,
      children,
      child,
      onSelect = noop,
      forceMount = false,
      keywords = [],
      $$slots,
      $$events,
      ...restProps
    } = $$props;
    const itemState = CommandItemState.create({
      id: boxWith(() => id),
      ref: boxWith(() => ref, (v) => ref = v),
      value: boxWith(() => value),
      disabled: boxWith(() => disabled),
      onSelect: boxWith(() => onSelect),
      forceMount: boxWith(() => forceMount),
      keywords: boxWith(() => keywords)
    });
    const mergedProps = derived(() => mergeProps(restProps, itemState.props));
    $$renderer2.push(`<!---->`);
    {
      $$renderer2.push(`<div style="display: contents;" data-item-wrapper=""${attr("data-value", itemState.trueValue)}>`);
      if (itemState.shouldRender) {
        $$renderer2.push("<!--[0-->");
        if (child) {
          $$renderer2.push("<!--[0-->");
          child($$renderer2, { props: mergedProps() });
          $$renderer2.push(`<!---->`);
        } else {
          $$renderer2.push("<!--[-1-->");
          $$renderer2.push(`<div${attributes({ ...mergedProps() })}>`);
          children?.($$renderer2);
          $$renderer2.push(`<!----></div>`);
        }
        $$renderer2.push(`<!--]-->`);
      } else {
        $$renderer2.push("<!--[-1-->");
      }
      $$renderer2.push(`<!--]--></div>`);
    }
    $$renderer2.push(`<!---->`);
    bind_props($$props, { ref });
  });
}
function Command_list$1($$renderer, $$props) {
  $$renderer.component(($$renderer2) => {
    const uid = props_id($$renderer2);
    let {
      id = createId(uid),
      ref = null,
      child,
      children,
      "aria-label": ariaLabel,
      $$slots,
      $$events,
      ...restProps
    } = $$props;
    const listState = CommandListState.create({
      id: boxWith(() => id),
      ref: boxWith(() => ref, (v) => ref = v),
      ariaLabel: boxWith(() => ariaLabel ?? "Suggestions...")
    });
    const mergedProps = derived(() => mergeProps(restProps, listState.props));
    $$renderer2.push(`<!---->`);
    {
      if (child) {
        $$renderer2.push("<!--[0-->");
        child($$renderer2, { props: mergedProps() });
        $$renderer2.push(`<!---->`);
      } else {
        $$renderer2.push("<!--[-1-->");
        $$renderer2.push(`<div${attributes({ ...mergedProps() })}>`);
        children?.($$renderer2);
        $$renderer2.push(`<!----></div>`);
      }
      $$renderer2.push(`<!--]-->`);
    }
    $$renderer2.push(`<!---->`);
    bind_props($$props, { ref });
  });
}
function Command_loading$1($$renderer, $$props) {
  $$renderer.component(($$renderer2) => {
    const uid = props_id($$renderer2);
    let {
      progress = 0,
      id = createId(uid),
      ref = null,
      children,
      child,
      $$slots,
      $$events,
      ...restProps
    } = $$props;
    const loadingState = CommandLoadingState.create({
      id: boxWith(() => id),
      ref: boxWith(() => ref, (v) => ref = v),
      progress: boxWith(() => progress)
    });
    const mergedProps = derived(() => mergeProps(restProps, loadingState.props));
    if (child) {
      $$renderer2.push("<!--[0-->");
      child($$renderer2, { props: mergedProps() });
      $$renderer2.push(`<!---->`);
    } else {
      $$renderer2.push("<!--[-1-->");
      $$renderer2.push(`<div${attributes({ ...mergedProps() })}>`);
      children?.($$renderer2);
      $$renderer2.push(`<!----></div>`);
    }
    $$renderer2.push(`<!--]-->`);
    bind_props($$props, { ref });
  });
}
const SCORE_CONTINUE_MATCH = 1;
const SCORE_SPACE_WORD_JUMP = 0.9;
const SCORE_NON_SPACE_WORD_JUMP = 0.8;
const SCORE_CHARACTER_JUMP = 0.17;
const SCORE_TRANSPOSITION = 0.1;
const PENALTY_SKIPPED = 0.999;
const PENALTY_CASE_MISMATCH = 0.9999;
const PENALTY_NOT_COMPLETE = 0.99;
const IS_GAP_REGEXP = /[\\/_+.#"@[({&]/;
const COUNT_GAPS_REGEXP = /[\\/_+.#"@[({&]/g;
const IS_SPACE_REGEXP = /[\s-]/;
const COUNT_SPACE_REGEXP = /[\s-]/g;
function computeCommandScoreInner(string, abbreviation, lowerString, lowerAbbreviation, stringIndex, abbreviationIndex, memoizedResults) {
  if (abbreviationIndex === abbreviation.length) {
    if (stringIndex === string.length)
      return SCORE_CONTINUE_MATCH;
    return PENALTY_NOT_COMPLETE;
  }
  const memoizeKey = `${stringIndex},${abbreviationIndex}`;
  if (memoizedResults[memoizeKey] !== void 0)
    return memoizedResults[memoizeKey];
  const abbreviationChar = lowerAbbreviation.charAt(abbreviationIndex);
  let index = lowerString.indexOf(abbreviationChar, stringIndex);
  let highScore = 0;
  let score, transposedScore, wordBreaks, spaceBreaks;
  while (index >= 0) {
    score = computeCommandScoreInner(string, abbreviation, lowerString, lowerAbbreviation, index + 1, abbreviationIndex + 1, memoizedResults);
    if (score > highScore) {
      if (index === stringIndex) {
        score *= SCORE_CONTINUE_MATCH;
      } else if (IS_GAP_REGEXP.test(string.charAt(index - 1))) {
        score *= SCORE_NON_SPACE_WORD_JUMP;
        wordBreaks = string.slice(stringIndex, index - 1).match(COUNT_GAPS_REGEXP);
        if (wordBreaks && stringIndex > 0) {
          score *= PENALTY_SKIPPED ** wordBreaks.length;
        }
      } else if (IS_SPACE_REGEXP.test(string.charAt(index - 1))) {
        score *= SCORE_SPACE_WORD_JUMP;
        spaceBreaks = string.slice(stringIndex, index - 1).match(COUNT_SPACE_REGEXP);
        if (spaceBreaks && stringIndex > 0) {
          score *= PENALTY_SKIPPED ** spaceBreaks.length;
        }
      } else {
        score *= SCORE_CHARACTER_JUMP;
        if (stringIndex > 0) {
          score *= PENALTY_SKIPPED ** (index - stringIndex);
        }
      }
      if (string.charAt(index) !== abbreviation.charAt(abbreviationIndex)) {
        score *= PENALTY_CASE_MISMATCH;
      }
    }
    if (score < SCORE_TRANSPOSITION && lowerString.charAt(index - 1) === lowerAbbreviation.charAt(abbreviationIndex + 1) || lowerAbbreviation.charAt(abbreviationIndex + 1) === lowerAbbreviation.charAt(abbreviationIndex) && lowerString.charAt(index - 1) !== lowerAbbreviation.charAt(abbreviationIndex)) {
      transposedScore = computeCommandScoreInner(string, abbreviation, lowerString, lowerAbbreviation, index + 1, abbreviationIndex + 2, memoizedResults);
      if (transposedScore * SCORE_TRANSPOSITION > score) {
        score = transposedScore * SCORE_TRANSPOSITION;
      }
    }
    if (score > highScore) {
      highScore = score;
    }
    index = lowerString.indexOf(abbreviationChar, index + 1);
  }
  memoizedResults[memoizeKey] = highScore;
  return highScore;
}
function formatInput(string) {
  return string.toLowerCase().replace(COUNT_SPACE_REGEXP, " ");
}
function computeCommandScore(command, search, commandKeywords) {
  command = commandKeywords && commandKeywords.length > 0 ? `${`${command} ${commandKeywords?.join(" ")}`}` : command;
  return computeCommandScoreInner(command, search, formatInput(command), formatInput(search), 0, 0, {});
}
function Popover_description($$renderer, $$props) {
  $$renderer.component(($$renderer2) => {
    let {
      ref = null,
      class: className,
      children,
      $$slots,
      $$events,
      ...restProps
    } = $$props;
    $$renderer2.push(`<div${attributes({
      "data-slot": "popover-description",
      class: clsx(cn("text-muted-foreground", className)),
      ...restProps
    })}>`);
    children?.($$renderer2);
    $$renderer2.push(`<!----></div>`);
    bind_props($$props, { ref });
  });
}
function Popover_header($$renderer, $$props) {
  $$renderer.component(($$renderer2) => {
    let {
      ref = null,
      class: className,
      children,
      $$slots,
      $$events,
      ...restProps
    } = $$props;
    $$renderer2.push(`<div${attributes({
      "data-slot": "popover-header",
      class: clsx(cn("flex flex-col gap-0.5 text-sm", className)),
      ...restProps
    })}>`);
    children?.($$renderer2);
    $$renderer2.push(`<!----></div>`);
    bind_props($$props, { ref });
  });
}
function Popover_title($$renderer, $$props) {
  $$renderer.component(($$renderer2) => {
    let {
      ref = null,
      class: className,
      children,
      $$slots,
      $$events,
      ...restProps
    } = $$props;
    $$renderer2.push(`<div${attributes({
      "data-slot": "popover-title",
      class: clsx(cn("font-medium", className)),
      ...restProps
    })}>`);
    children?.($$renderer2);
    $$renderer2.push(`<!----></div>`);
    bind_props($$props, { ref });
  });
}
function Card_action($$renderer, $$props) {
  $$renderer.component(($$renderer2) => {
    let {
      ref = null,
      class: className,
      children,
      $$slots,
      $$events,
      ...restProps
    } = $$props;
    $$renderer2.push(`<div${attributes({
      "data-slot": "card-action",
      class: clsx(cn("col-start-2 row-span-2 row-start-1 self-start justify-self-end", className)),
      ...restProps
    })}>`);
    children?.($$renderer2);
    $$renderer2.push(`<!----></div>`);
    bind_props($$props, { ref });
  });
}
function Separator($$renderer, $$props) {
  $$renderer.component(($$renderer2) => {
    let {
      ref = null,
      class: className,
      "data-slot": dataSlot = "separator",
      $$slots,
      $$events,
      ...restProps
    } = $$props;
    let $$settled = true;
    let $$inner_renderer;
    function $$render_inner($$renderer3) {
      if (Separator$1) {
        $$renderer3.push("<!--[-->");
        Separator$1($$renderer3, spread_props([
          {
            "data-slot": dataSlot,
            class: cn("bg-border shrink-0 data-[orientation=horizontal]:h-px data-[orientation=horizontal]:w-full data-[orientation=vertical]:min-h-full data-[orientation=vertical]:w-px", className)
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
function AuthenticatedLayout($$renderer, $$props) {
  $$renderer.component(($$renderer2) => {
    let { children, breadcrumbs = [] } = $$props;
    const page = usePage();
    const user = derived(() => page.props.auth?.user ?? null);
    const headTitle = derived(() => breadcrumbs.length > 0 ? `${breadcrumbs[breadcrumbs.length - 1].label} - NEXIAM` : "NEXIAM");
    let sidebarOpen = false;
    let breadcrumbOpen = false;
    let userDropdownOpen = false;
    const navItems = [
      {
        href: "/staff",
        label: "Home",
        icon: Layout_dashboard,
        activeFor: ["/staff"]
      },
      {
        href: "/ui-kit",
        label: "UI Kit",
        icon: Blocks,
        activeFor: ["/ui-kit"]
      }
    ];
    function closeDropdowns() {
      userDropdownOpen = false;
      sidebarOpen = false;
    }
    function isNavActive(item) {
      const rawUrl = page.url || "";
      const url = rawUrl.split("?")[0].split("#")[0];
      const href = item.href ?? "";
      if (url === href || url === href + "/") return true;
      if (href !== "/" && url.startsWith(href + "/")) return true;
      if (item.activeFor) {
        for (const prefix of item.activeFor) {
          if (url === prefix || url === prefix + "/") return true;
          if (prefix !== "/" && url.startsWith(prefix + "/")) return true;
        }
      }
      return false;
    }
    head("thdohf", $$renderer2, ($$renderer3) => {
      $$renderer3.title(($$renderer4) => {
        $$renderer4.push(`<title>${escape_html(headTitle())}</title>`);
      });
    });
    $$renderer2.push(`<div class="h-dvh w-full max-w-[100vw] flex overflow-hidden relative">`);
    InstitutionalBackdrop($$renderer2, { fixed: true });
    $$renderer2.push(`<!----> `);
    if (sidebarOpen) {
      $$renderer2.push("<!--[0-->");
      $$renderer2.push(`<button type="button" class="fixed inset-0 z-40 bg-black/50 lg:hidden print:hidden" aria-label="Close sidebar"></button>`);
    } else {
      $$renderer2.push("<!--[-1-->");
    }
    $$renderer2.push(`<!--]--> <aside${attr_class(`fixed inset-y-0 left-0 z-50 w-64 flex-shrink-0 glass-panel border-r flex flex-col transform transition-transform duration-200 ease-in-out -translate-x-full md:translate-x-0 ${sidebarOpen ? "translate-x-0" : ""} print:hidden`)}><div class="flex flex-col h-full overflow-hidden"><div class="h-20 flex items-center px-6 border-b border-border/50 shrink-0">`);
    Link($$renderer2, {
      href: "/staff",
      class: "flex items-center gap-3",
      onclick: closeDropdowns,
      children: ($$renderer3) => {
        $$renderer3.push(`<span class="flex size-10 items-center justify-center rounded-xl bg-primary text-primary-foreground font-bold text-lg shrink-0" aria-hidden="true">N</span> <span class="font-bold text-xl tracking-tight text-foreground">NEXIAM</span>`);
      },
      $$slots: { default: true }
    });
    $$renderer2.push(`<!----> `);
    Button($$renderer2, {
      variant: "ghost",
      size: "icon",
      class: "md:hidden ml-auto",
      onclick: () => sidebarOpen = !sidebarOpen,
      "aria-label": "Close menu",
      children: ($$renderer3) => {
        Menu($$renderer3, { class: "h-5 w-5" });
      },
      $$slots: { default: true }
    });
    $$renderer2.push(`<!----></div> <nav class="flex-1 overflow-y-auto scrollbar-hide p-4 space-y-2 svelte-thdohf"><!--[-->`);
    const each_array = ensure_array_like(navItems);
    for (let $$index = 0, $$length = each_array.length; $$index < $$length; $$index++) {
      let item = each_array[$$index];
      Link($$renderer2, {
        href: item.href,
        class: `nav-item flex items-center gap-3 px-4 py-3 rounded-lg font-medium transition-all duration-300 ${isNavActive(item) ? "bg-primary text-primary-foreground shadow-md shadow-primary/40" : "text-foreground/80 hover:text-foreground hover:bg-accent hover:translate-x-1"}`,
        onclick: closeDropdowns,
        children: ($$renderer3) => {
          if (item.icon) {
            $$renderer3.push("<!--[-->");
            item.icon($$renderer3, { class: "w-5 h-5 shrink-0" });
            $$renderer3.push("<!--]-->");
          } else {
            $$renderer3.push("<!--[!-->");
            $$renderer3.push("<!--]-->");
          }
          $$renderer3.push(` ${escape_html(item.label)}`);
        },
        $$slots: { default: true }
      });
    }
    $$renderer2.push(`<!--]--></nav></div> <div class="p-4 border-t border-border/50 shrink-0"><div class="relative"><button type="button" class="flex items-center gap-3 px-4 py-3 rounded-xl bg-muted/50 hover:bg-muted w-full text-left transition-colors min-h-[44px]"${attr("aria-expanded", userDropdownOpen)} aria-haspopup="true"><div class="w-10 h-10 rounded-xl bg-primary/20 border-2 border-primary/20 flex items-center justify-center text-sm font-semibold text-foreground shrink-0">${escape_html((user()?.name ?? "U").split(/\s+/).map((n2) => n2[0]).join("").slice(0, 2).toUpperCase())}</div> <div class="flex-1 min-w-0"><p class="text-sm font-semibold truncate text-foreground">${escape_html(user()?.name ?? "User")}</p> <p class="text-xs text-muted-foreground truncate">${escape_html(user()?.email ?? "")}</p></div> `);
    Chevron_down($$renderer2, { class: "h-4 w-4 text-muted-foreground shrink-0" });
    $$renderer2.push(`<!----></button> `);
    if (userDropdownOpen) {
      $$renderer2.push("<!--[0-->");
      $$renderer2.push(`<div class="absolute left-0 right-0 bottom-full mb-1 rounded-lg border border-border bg-card-solid py-1 shadow-lg z-50" role="menu"><button type="button" class="w-full px-4 py-2 text-left text-sm text-foreground hover:bg-accent transition-colors min-h-[44px] rounded-lg" role="menuitem">Sign out</button></div>`);
    } else {
      $$renderer2.push("<!--[-1-->");
    }
    $$renderer2.push(`<!--]--></div></div></aside> <div class="flex min-w-0 flex-1 flex-col md:pl-64 relative print:pl-0"><header class="shrink-0 z-20 h-20 glass-panel border-b border-l-0 flex items-center justify-between px-4 lg:px-8 relative print:hidden"><div class="flex items-center gap-4">`);
    Button($$renderer2, {
      variant: "ghost",
      size: "icon",
      class: "md:hidden",
      onclick: () => sidebarOpen = true,
      "aria-label": "Open menu",
      children: ($$renderer3) => {
        Menu($$renderer3, { class: "h-5 w-5" });
      },
      $$slots: { default: true }
    });
    $$renderer2.push(`<!----> `);
    if (breadcrumbs.length === 0) {
      $$renderer2.push("<!--[0-->");
      $$renderer2.push(`<span class="text-2xl font-semibold tracking-tight text-foreground">NEXIAM</span>`);
    } else if (breadcrumbs.length === 1) {
      $$renderer2.push("<!--[1-->");
      $$renderer2.push(`<h1 class="text-2xl font-semibold tracking-tight text-foreground">${escape_html(breadcrumbs[0].label)}</h1>`);
    } else {
      $$renderer2.push("<!--[-1-->");
      $$renderer2.push(`<nav class="hidden md:flex items-center gap-2 text-sm" aria-label="Breadcrumb"><!--[-->`);
      const each_array_1 = ensure_array_like(breadcrumbs);
      for (let i = 0, $$length = each_array_1.length; i < $$length; i++) {
        let crumb = each_array_1[i];
        if (i > 0) {
          $$renderer2.push("<!--[0-->");
          Chevron_right($$renderer2, {
            class: "h-4 w-4 text-muted-foreground/50 shrink-0",
            "aria-hidden": "true"
          });
        } else {
          $$renderer2.push("<!--[-1-->");
        }
        $$renderer2.push(`<!--]--> `);
        if (crumb.href && i < breadcrumbs.length - 1) {
          $$renderer2.push("<!--[0-->");
          Link($$renderer2, {
            href: crumb.href,
            class: "text-muted-foreground hover:text-foreground transition-colors font-medium shrink-0",
            children: ($$renderer3) => {
              $$renderer3.push(`<!---->${escape_html(crumb.label)}`);
            },
            $$slots: { default: true }
          });
        } else {
          $$renderer2.push("<!--[-1-->");
          $$renderer2.push(`<span class="font-semibold text-foreground truncate max-w-[180px] sm:max-w-none"${attr("aria-current", i === breadcrumbs.length - 1 ? "page" : void 0)}>${escape_html(crumb.label)}</span>`);
        }
        $$renderer2.push(`<!--]-->`);
      }
      $$renderer2.push(`<!--]--></nav> <div class="flex items-center md:hidden"><button type="button" class="flex items-center gap-2 text-sm"${attr("aria-expanded", breadcrumbOpen)} aria-label="Breadcrumb navigation"><span class="rounded bg-muted px-2 py-0.5 text-xs font-semibold text-muted-foreground">•••</span> `);
      Chevron_right($$renderer2, {
        class: "h-4 w-4 text-muted-foreground/50 shrink-0",
        "aria-hidden": "true"
      });
      $$renderer2.push(`<!----> <span class="font-semibold text-foreground">${escape_html(breadcrumbs[breadcrumbs.length - 1].label)}</span> `);
      Chevron_down($$renderer2, {
        class: `h-4 w-4 text-muted-foreground ml-1 transition-transform ${""}`
      });
      $$renderer2.push(`<!----></button> `);
      {
        $$renderer2.push("<!--[-1-->");
      }
      $$renderer2.push(`<!--]--></div>`);
    }
    $$renderer2.push(`<!--]--></div> <div class="flex items-center gap-2 sm:gap-4">`);
    NotificationBell($$renderer2);
    $$renderer2.push(`<!----> `);
    PreferencesMenu($$renderer2);
    $$renderer2.push(`<!----> `);
    ToastManager($$renderer2, { placement: "below-anchor" });
    $$renderer2.push(`<!----></div></header> <main class="flex-1 min-w-0 overflow-x-hidden overflow-y-auto p-4 lg:p-8 scroll-smooth" id="main-content"><div class="min-w-0 w-full max-w-full">`);
    children?.($$renderer2);
    $$renderer2.push(`<!----></div></main></div></div>`);
  });
}
function Command($$renderer, $$props) {
  $$renderer.component(($$renderer2) => {
    let {
      api = null,
      ref = null,
      value = "",
      class: className,
      $$slots,
      $$events,
      ...restProps
    } = $$props;
    let $$settled = true;
    let $$inner_renderer;
    function $$render_inner($$renderer3) {
      if (Command$1) {
        $$renderer3.push("<!--[-->");
        Command$1($$renderer3, spread_props([
          {
            "data-slot": "command",
            class: cn("bg-popover text-popover-foreground rounded-xl! p-1 flex size-full flex-col overflow-hidden", className)
          },
          restProps,
          {
            get value() {
              return value;
            },
            set value($$value) {
              value = $$value;
              $$settled = false;
            },
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
    bind_props($$props, { api, ref, value });
  });
}
function Command_loading($$renderer, $$props) {
  $$renderer.component(($$renderer2) => {
    let { ref = null, $$slots, $$events, ...restProps } = $$props;
    let $$settled = true;
    let $$inner_renderer;
    function $$render_inner($$renderer3) {
      if (Command_loading$1) {
        $$renderer3.push("<!--[-->");
        Command_loading$1($$renderer3, spread_props([
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
function Command_dialog($$renderer, $$props) {
  $$renderer.component(($$renderer2) => {
    let {
      open = false,
      ref = null,
      value = "",
      title = "Command Palette",
      description = "Search for a command to run...",
      showCloseButton = false,
      portalProps,
      children,
      class: className,
      $$slots,
      $$events,
      ...restProps
    } = $$props;
    let $$settled = true;
    let $$inner_renderer;
    function $$render_inner($$renderer3) {
      if (Dialog) {
        $$renderer3.push("<!--[-->");
        Dialog($$renderer3, spread_props([
          restProps,
          {
            get open() {
              return open;
            },
            set open($$value) {
              open = $$value;
              $$settled = false;
            },
            children: ($$renderer4) => {
              if (Dialog_header) {
                $$renderer4.push("<!--[-->");
                Dialog_header($$renderer4, {
                  class: "sr-only",
                  children: ($$renderer5) => {
                    if (Dialog_title) {
                      $$renderer5.push("<!--[-->");
                      Dialog_title($$renderer5, {
                        children: ($$renderer6) => {
                          $$renderer6.push(`<!---->${escape_html(title)}`);
                        },
                        $$slots: { default: true }
                      });
                      $$renderer5.push("<!--]-->");
                    } else {
                      $$renderer5.push("<!--[!-->");
                      $$renderer5.push("<!--]-->");
                    }
                    $$renderer5.push(` `);
                    if (Dialog_description) {
                      $$renderer5.push("<!--[-->");
                      Dialog_description($$renderer5, {
                        children: ($$renderer6) => {
                          $$renderer6.push(`<!---->${escape_html(description)}`);
                        },
                        $$slots: { default: true }
                      });
                      $$renderer5.push("<!--]-->");
                    } else {
                      $$renderer5.push("<!--[!-->");
                      $$renderer5.push("<!--]-->");
                    }
                  },
                  $$slots: { default: true }
                });
                $$renderer4.push("<!--]-->");
              } else {
                $$renderer4.push("<!--[!-->");
                $$renderer4.push("<!--]-->");
              }
              $$renderer4.push(` `);
              if (Dialog_content) {
                $$renderer4.push("<!--[-->");
                Dialog_content($$renderer4, {
                  class: cn("rounded-xl! top-1/3 translate-y-0 overflow-hidden p-0", className),
                  showCloseButton,
                  portalProps,
                  children: ($$renderer5) => {
                    Command($$renderer5, spread_props([
                      restProps,
                      {
                        children,
                        get value() {
                          return value;
                        },
                        set value($$value) {
                          value = $$value;
                          $$settled = false;
                        },
                        get ref() {
                          return ref;
                        },
                        set ref($$value) {
                          ref = $$value;
                          $$settled = false;
                        }
                      }
                    ]));
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
    bind_props($$props, { open, ref, value });
  });
}
function Command_empty($$renderer, $$props) {
  $$renderer.component(($$renderer2) => {
    let {
      ref = null,
      class: className,
      $$slots,
      $$events,
      ...restProps
    } = $$props;
    let $$settled = true;
    let $$inner_renderer;
    function $$render_inner($$renderer3) {
      if (Command_empty$1) {
        $$renderer3.push("<!--[-->");
        Command_empty$1($$renderer3, spread_props([
          {
            "data-slot": "command-empty",
            class: cn("py-6 text-center text-sm", className)
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
function Command_group($$renderer, $$props) {
  $$renderer.component(($$renderer2) => {
    let {
      ref = null,
      class: className,
      children,
      heading,
      value,
      $$slots,
      $$events,
      ...restProps
    } = $$props;
    let $$settled = true;
    let $$inner_renderer;
    function $$render_inner($$renderer3) {
      if (Command_group$1) {
        $$renderer3.push("<!--[-->");
        Command_group$1($$renderer3, spread_props([
          {
            "data-slot": "command-group",
            class: cn("text-foreground **:[[cmdk-group-heading]]:text-muted-foreground overflow-hidden p-1 **:[[cmdk-group-heading]]:px-2 **:[[cmdk-group-heading]]:py-1.5 **:[[cmdk-group-heading]]:text-xs **:[[cmdk-group-heading]]:font-medium", className),
            value: value ?? heading ?? `----${useId()}`
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
            children: ($$renderer4) => {
              if (heading) {
                $$renderer4.push("<!--[0-->");
                if (Command_group_heading) {
                  $$renderer4.push("<!--[-->");
                  Command_group_heading($$renderer4, {
                    class: "text-muted-foreground px-2 py-1.5 text-xs font-medium",
                    children: ($$renderer5) => {
                      $$renderer5.push(`<!---->${escape_html(heading)}`);
                    },
                    $$slots: { default: true }
                  });
                  $$renderer4.push("<!--]-->");
                } else {
                  $$renderer4.push("<!--[!-->");
                  $$renderer4.push("<!--]-->");
                }
              } else {
                $$renderer4.push("<!--[-1-->");
              }
              $$renderer4.push(`<!--]--> `);
              if (Command_group_items) {
                $$renderer4.push("<!--[-->");
                Command_group_items($$renderer4, { children });
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
    bind_props($$props, { ref });
  });
}
function Command_item($$renderer, $$props) {
  $$renderer.component(($$renderer2) => {
    let {
      ref = null,
      class: className,
      children,
      $$slots,
      $$events,
      ...restProps
    } = $$props;
    let $$settled = true;
    let $$inner_renderer;
    function $$render_inner($$renderer3) {
      if (Command_item$1) {
        $$renderer3.push("<!--[-->");
        Command_item$1($$renderer3, spread_props([
          {
            "data-slot": "command-item",
            class: cn("group/command-item data-selected:bg-muted data-selected:text-foreground data-selected:*:[svg]:text-foreground relative flex cursor-default items-center gap-2 rounded-sm px-2 py-1.5 text-sm outline-hidden select-none in-data-[slot=dialog-content]:rounded-lg! data-[disabled=true]:pointer-events-none data-[disabled=true]:opacity-50 [&_svg]:pointer-events-none [&_svg]:shrink-0 [&_svg:not([class*='size-'])]:size-4", className)
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
            children: ($$renderer4) => {
              children?.($$renderer4);
              $$renderer4.push(`<!----> `);
              Check($$renderer4, {
                class: "cn-command-item-indicator ml-auto opacity-0 group-has-[[data-slot=command-shortcut]]/command-item:hidden group-data-[checked=true]/command-item:opacity-100"
              });
              $$renderer4.push(`<!---->`);
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
    bind_props($$props, { ref });
  });
}
function Command_input($$renderer, $$props) {
  $$renderer.component(($$renderer2) => {
    let {
      ref = null,
      class: className,
      value = "",
      $$slots,
      $$events,
      ...restProps
    } = $$props;
    let $$settled = true;
    let $$inner_renderer;
    function $$render_inner($$renderer3) {
      $$renderer3.push(`<div class="flex items-center border-b px-3" data-slot="command-input-wrapper">`);
      Search($$renderer3, { class: "mr-2 size-4 shrink-0 opacity-50" });
      $$renderer3.push(`<!----> `);
      if (Command_input$1) {
        $$renderer3.push("<!--[-->");
        Command_input$1($$renderer3, spread_props([
          {
            "data-slot": "command-input",
            class: cn("flex h-11 w-full rounded-md bg-transparent py-3 text-sm outline-hidden placeholder:text-muted-foreground disabled:cursor-not-allowed disabled:opacity-50", className)
          },
          restProps,
          {
            get value() {
              return value;
            },
            set value($$value) {
              value = $$value;
              $$settled = false;
            },
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
      $$renderer3.push(`</div>`);
    }
    do {
      $$settled = true;
      $$inner_renderer = $$renderer2.copy();
      $$render_inner($$inner_renderer);
    } while (!$$settled);
    $$renderer2.subsume($$inner_renderer);
    bind_props($$props, { ref, value });
  });
}
function Command_list($$renderer, $$props) {
  $$renderer.component(($$renderer2) => {
    let {
      ref = null,
      class: className,
      $$slots,
      $$events,
      ...restProps
    } = $$props;
    let $$settled = true;
    let $$inner_renderer;
    function $$render_inner($$renderer3) {
      if (Command_list$1) {
        $$renderer3.push("<!--[-->");
        Command_list$1($$renderer3, spread_props([
          {
            "data-slot": "command-list",
            class: cn("no-scrollbar max-h-72 scroll-py-1 outline-none overflow-x-hidden overflow-y-auto", className)
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
function Command_shortcut($$renderer, $$props) {
  $$renderer.component(($$renderer2) => {
    let {
      ref = null,
      class: className,
      children,
      $$slots,
      $$events,
      ...restProps
    } = $$props;
    $$renderer2.push(`<span${attributes({
      "data-slot": "command-shortcut",
      class: clsx(cn("text-muted-foreground group-data-selected/command-item:text-foreground ml-auto text-xs tracking-widest", className)),
      ...restProps
    })}>`);
    children?.($$renderer2);
    $$renderer2.push(`<!----></span>`);
    bind_props($$props, { ref });
  });
}
function StatCard($$renderer, $$props) {
  $$renderer.component(($$renderer2) => {
    let {
      label,
      value,
      icon: Icon2,
      trend,
      trendLabel,
      href,
      accent = "primary",
      class: className
    } = $$props;
    const trendPositive = derived(() => trend === "up");
    const trendNegative = derived(() => trend === "down");
    const trendNeutral = derived(() => trend === "neutral" || trend == null);
    if (href) {
      $$renderer2.push("<!--[0-->");
      Link($$renderer2, {
        href,
        class: cn("block rounded-[var(--radius,0.5rem)] border border-border/80 bg-card p-6 shadow-sm dark:shadow-[inset_0_1px_0_0_rgba(255,255,255,0.08)] motion-safe:hover:-translate-y-0.5 hover:border-primary/40 transition-all duration-200 relative overflow-hidden group", className),
        children: ($$renderer3) => {
          $$renderer3.push(`<div class="flex justify-between items-start mb-4 relative"><div><p class="text-xs font-mono font-bold uppercase tracking-wider text-muted-foreground mb-1">${escape_html(label)}</p> <h3 class="text-3xl font-extrabold font-mono tabular-nums tracking-tight text-foreground">${escape_html(value)}</h3></div> `);
          if (Icon2) {
            $$renderer3.push("<!--[0-->");
            $$renderer3.push(`<div class="inline-flex size-10 shrink-0 items-center justify-center rounded-xl bg-primary/10 text-primary border border-primary/20">`);
            if (Icon2) {
              $$renderer3.push("<!--[-->");
              Icon2($$renderer3, { class: "size-5" });
              $$renderer3.push("<!--]-->");
            } else {
              $$renderer3.push("<!--[!-->");
              $$renderer3.push("<!--]-->");
            }
            $$renderer3.push(`</div>`);
          } else {
            $$renderer3.push("<!--[-1-->");
          }
          $$renderer3.push(`<!--]--></div> `);
          if (trendLabel) {
            $$renderer3.push("<!--[0-->");
            $$renderer3.push(`<div${attr_class(clsx(cn("flex items-center gap-2 text-sm font-medium", trendPositive() && "text-green-600 dark:text-green-400", trendNegative() && "text-red-500", trendNeutral() && "text-muted-foreground")))}>`);
            if (trendPositive()) {
              $$renderer3.push("<!--[0-->");
              Trending_up($$renderer3, { class: "w-4 h-4" });
            } else if (trendNegative()) {
              $$renderer3.push("<!--[1-->");
              Trending_down($$renderer3, { class: "w-4 h-4" });
            } else {
              $$renderer3.push("<!--[-1-->");
              Minus($$renderer3, { class: "w-4 h-4" });
            }
            $$renderer3.push(`<!--]--> <span>${escape_html(trendLabel)}</span></div>`);
          } else {
            $$renderer3.push("<!--[-1-->");
          }
          $$renderer3.push(`<!--]-->`);
        },
        $$slots: { default: true }
      });
    } else {
      $$renderer2.push("<!--[-1-->");
      $$renderer2.push(`<div${attr_class(clsx(cn("rounded-[var(--radius,0.5rem)] border border-border/80 bg-card p-6 shadow-sm dark:shadow-[inset_0_1px_0_0_rgba(255,255,255,0.08)] transition-all duration-200 relative overflow-hidden", className)))}><div class="flex justify-between items-start mb-4 relative"><div><p class="text-xs font-mono font-bold uppercase tracking-wider text-muted-foreground mb-1">${escape_html(label)}</p> <h3 class="text-3xl font-extrabold font-mono tabular-nums tracking-tight text-foreground">${escape_html(value)}</h3></div> `);
      if (Icon2) {
        $$renderer2.push("<!--[0-->");
        $$renderer2.push(`<div class="inline-flex size-10 shrink-0 items-center justify-center rounded-xl bg-primary/10 text-primary border border-primary/20">`);
        if (Icon2) {
          $$renderer2.push("<!--[-->");
          Icon2($$renderer2, { class: "size-5" });
          $$renderer2.push("<!--]-->");
        } else {
          $$renderer2.push("<!--[!-->");
          $$renderer2.push("<!--]-->");
        }
        $$renderer2.push(`</div>`);
      } else {
        $$renderer2.push("<!--[-1-->");
      }
      $$renderer2.push(`<!--]--></div> `);
      if (trendLabel) {
        $$renderer2.push("<!--[0-->");
        $$renderer2.push(`<div${attr_class(clsx(cn("flex items-center gap-2 text-sm font-medium", trendPositive() && "text-green-600 dark:text-green-400", trendNegative() && "text-red-500", trendNeutral() && "text-muted-foreground")))}>`);
        if (trendPositive()) {
          $$renderer2.push("<!--[0-->");
          Trending_up($$renderer2, { class: "w-4 h-4" });
        } else if (trendNegative()) {
          $$renderer2.push("<!--[1-->");
          Trending_down($$renderer2, { class: "w-4 h-4" });
        } else {
          $$renderer2.push("<!--[-1-->");
          Minus($$renderer2, { class: "w-4 h-4" });
        }
        $$renderer2.push(`<!--]--> <span>${escape_html(trendLabel)}</span></div>`);
      } else {
        $$renderer2.push("<!--[-1-->");
      }
      $$renderer2.push(`<!--]--></div>`);
    }
    $$renderer2.push(`<!--]-->`);
  });
}
function GuidePanel($$renderer, $$props) {
  $$renderer.component(($$renderer2) => {
    let {
      title,
      icon: Icon2 = Circle_question_mark,
      defaultOpen = false,
      class: className = "",
      children
    } = $$props;
    let open = defaultOpen;
    const panelId = "guide-panel-" + Math.random().toString(36).slice(2, 9);
    $$renderer2.push(`<div${attr_class(`overflow-hidden rounded-xl border border-border/70 bg-card shadow-sm ${stringify(className)}`)}><button type="button" class="flex w-full items-center justify-between gap-3 px-4 py-3.5 text-left transition-colors hover:bg-muted/40 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring"${attr("aria-expanded", open)}${attr("aria-controls", panelId)}><div class="flex items-center gap-2.5 font-medium text-foreground"><span class="inline-flex size-8 items-center justify-center rounded-lg bg-muted text-muted-foreground" aria-hidden="true">`);
    if (Icon2) {
      $$renderer2.push("<!--[-->");
      Icon2($$renderer2, { class: "h-4 w-4" });
      $$renderer2.push("<!--]-->");
    } else {
      $$renderer2.push("<!--[!-->");
      $$renderer2.push("<!--]-->");
    }
    $$renderer2.push(`</span> ${escape_html(title)}</div> `);
    Chevron_down($$renderer2, {
      class: `h-4 w-4 shrink-0 text-muted-foreground transition-transform duration-200 ${open ? "rotate-180" : ""}`
    });
    $$renderer2.push(`<!----></button> `);
    if (open) {
      $$renderer2.push("<!--[0-->");
      $$renderer2.push(`<div${attr("id", panelId)} role="region"><div class="space-y-4 border-t border-border/60 px-4 py-4">`);
      children?.($$renderer2);
      $$renderer2.push(`<!----></div></div>`);
    } else {
      $$renderer2.push("<!--[-1-->");
    }
    $$renderer2.push(`<!--]--></div>`);
  });
}
function GuideSection($$renderer, $$props) {
  let { title, visible = void 0, class: className = "", children } = $$props;
  if (visible !== false) {
    $$renderer.push("<!--[0-->");
    $$renderer.push(`<div${attr_class(`space-y-2.5 ${stringify(className)}`)}><h4 class="text-sm font-semibold tracking-tight text-foreground">${escape_html(title)}</h4> <div class="space-y-2 text-sm leading-relaxed text-muted-foreground">`);
    children?.($$renderer);
    $$renderer.push(`<!----></div></div>`);
  } else {
    $$renderer.push("<!--[-1-->");
  }
  $$renderer.push(`<!--]-->`);
}
function GuideNote($$renderer, $$props) {
  let {
    variant = "info",
    title = "",
    class: className = "",
    children
  } = $$props;
  const styles = {
    info: {
      shell: "border-border/70 bg-muted/40",
      iconWrap: "bg-sky-500/10 text-sky-700 dark:text-sky-300",
      icon: Info
    },
    warning: {
      shell: "border-amber-500/25 bg-amber-500/8",
      iconWrap: "bg-amber-500/15 text-amber-800 dark:text-amber-300",
      icon: Triangle_alert
    },
    tip: {
      shell: "border-primary/25 bg-primary/6",
      iconWrap: "bg-primary/12 text-primary",
      icon: Lightbulb
    }
  };
  const config = derived(() => styles[variant] ?? styles.info);
  const Icon2 = derived(() => config().icon);
  $$renderer.push(`<div${attr_class(`rounded-xl border p-4 text-sm shadow-[inset_0_1px_0_oklch(1_0_0/0.35)] ${stringify(config().shell)} ${stringify(className)}`)} role="note"><div class="flex items-start gap-3"><span${attr_class(`inline-flex size-8 shrink-0 items-center justify-center rounded-lg ${stringify(config().iconWrap)}`)} aria-hidden="true">`);
  if (Icon2()) {
    $$renderer.push("<!--[-->");
    Icon2()($$renderer, { class: "h-4 w-4" });
    $$renderer.push("<!--]-->");
  } else {
    $$renderer.push("<!--[!-->");
    $$renderer.push("<!--]-->");
  }
  $$renderer.push(`</span> <div class="min-w-0 space-y-1 pt-0.5">`);
  if (title) {
    $$renderer.push("<!--[0-->");
    $$renderer.push(`<h5 class="font-semibold tracking-tight text-foreground">${escape_html(title)}</h5>`);
  } else {
    $$renderer.push("<!--[-1-->");
  }
  $$renderer.push(`<!--]--> <div class="leading-relaxed text-muted-foreground">`);
  children?.($$renderer);
  $$renderer.push(`<!----></div></div></div></div>`);
}
function MarketingHomePreview($$renderer) {
  const pillars = [
    {
      icon: Clipboard_list,
      title: "Apply once, track clearly",
      body: "Applicants see status without guessing. Staff work from one scoped queue."
    },
    {
      icon: Shield_check,
      title: "Capability-bound access",
      body: "Every sensitive action is gated by office, program, and duty — not role labels alone."
    },
    {
      icon: Graduation_cap,
      title: "Exam to release, intact",
      body: "Scheduling, proctoring, grading, and release stay connected with recoverable failure paths."
    }
  ];
  $$renderer.push(`<div class="overflow-hidden rounded-2xl bg-background shadow-sm ring-1 ring-border/40"><section class="relative isolate min-h-[min(78vh,620px)] overflow-hidden px-6 py-16 sm:px-10 sm:py-24 lg:px-16"><div class="pointer-events-none absolute inset-0 -z-10" style="background-image: radial-gradient(ellipse 85% 55% at 8% -10%, oklch(0.86 0.07 145 / 0.55), transparent 58%), radial-gradient(ellipse 60% 45% at 100% 10%, oklch(0.93 0.03 95 / 0.4), transparent 52%), radial-gradient(ellipse 50% 40% at 70% 100%, oklch(0.90 0.04 145 / 0.25), transparent 55%), linear-gradient(180deg, oklch(0.985 0.008 90), var(--background));" aria-hidden="true"></div> <p class="text-sm font-semibold tracking-[0.28em] text-primary uppercase">NEXIAM</p> <h2 class="mt-5 max-w-[14ch] text-[clamp(2.25rem,5vw,3.75rem)] font-semibold leading-[1.05] tracking-tight text-foreground">Admissions that stay fair when the season gets loud.</h2> <p class="mt-5 max-w-xl text-base leading-relaxed text-muted-foreground sm:text-lg">Computerized admission and testing for campuses that need clarity, auditability, and calm staff tooling.</p> <div class="mt-10 flex flex-wrap gap-3">`);
  Button($$renderer, {
    href: "#home-sample-pillars",
    class: "min-h-[48px] gap-2 px-6 text-base",
    children: ($$renderer2) => {
      $$renderer2.push(`<!---->See how it works `);
      Arrow_right($$renderer2, { class: "h-4 w-4" });
      $$renderer2.push(`<!---->`);
    },
    $$slots: { default: true }
  });
  $$renderer.push(`<!----> `);
  Button($$renderer, {
    href: "/login",
    variant: "outline",
    class: "min-h-[48px] px-6 text-base",
    children: ($$renderer2) => {
      $$renderer2.push(`<!---->Staff sign in`);
    },
    $$slots: { default: true }
  });
  $$renderer.push(`<!----></div></section> <section id="home-sample-pillars" class="border-t border-border/40 px-6 py-16 sm:px-10 lg:px-16"><p data-reveal="" class="text-xs font-semibold tracking-[0.2em] text-muted-foreground uppercase">Built for the season</p> <h3 data-reveal="" class="mt-3 max-w-lg text-2xl font-semibold tracking-tight text-foreground sm:text-3xl">One connected journey — without the fragile shortcuts.</h3> <ul class="mt-12 grid gap-8 sm:grid-cols-3" role="list"><!--[-->`);
  const each_array = ensure_array_like(pillars);
  for (let $$index = 0, $$length = each_array.length; $$index < $$length; $$index++) {
    let pillar = each_array[$$index];
    const Icon2 = pillar.icon;
    $$renderer.push(`<li data-reveal="" class="min-w-0"><span class="inline-flex size-11 items-center justify-center rounded-xl bg-primary/10 text-primary" aria-hidden="true">`);
    Icon2($$renderer, { class: "h-5 w-5" });
    $$renderer.push(`<!----></span> <h4 class="mt-5 text-lg font-semibold text-foreground">${escape_html(pillar.title)}</h4> <p class="mt-2 text-sm leading-relaxed text-muted-foreground">${escape_html(pillar.body)}</p></li>`);
  }
  $$renderer.push(`<!--]--></ul></section> <section class="border-t border-border/40 px-6 py-14 sm:px-10 lg:px-16"><div class="glass-panel flex flex-col gap-6 rounded-2xl border-0 p-6 sm:flex-row sm:items-end sm:justify-between sm:p-8"><div class="max-w-lg"><h3 class="text-xl font-semibold tracking-tight text-foreground sm:text-2xl">Ready when your academic cycle is.</h3> <p class="mt-2 text-sm text-muted-foreground">Kit sample for Motion + brand surfaces before journey pages ship. Glass sits on paper — one edge, not nested borders.</p></div> `);
  Button($$renderer, {
    href: "/login",
    class: "min-h-[44px] shrink-0 px-5",
    children: ($$renderer2) => {
      $$renderer2.push(`<!---->Open staff console`);
    },
    $$slots: { default: true }
  });
  $$renderer.push(`<!----></div></section></div>`);
}
function XiamWalkthroughPreview($$renderer) {
  let activeView = "access";
  let channels = {
    registrar: { inquiry: true, assistant: true },
    guidance: { inquiry: true, assistant: false },
    // Narrowed by guidance staff
    applicant: { inquiry: true, assistant: false }
  };
  let officialResultsReleased = false;
  const effectiveStatus = derived(() => ({
    registrar: channels.registrar.inquiry && channels.registrar.assistant ? "Full Office Service (Inquiry + Task Assistance)" : channels.registrar.inquiry ? "Inquiry Only (Task assistance paused by office)" : channels.registrar.assistant ? "Task Assistance Only" : "Service Paused by Office",
    guidance: channels.guidance.inquiry && channels.guidance.assistant ? "Full Office Service (Inquiry + Consultation Assistance)" : channels.guidance.inquiry ? "Inquiry Only (Guidance office paused task drafting)" : channels.guidance.assistant ? "Consultation Assistance Only" : "Service Paused by Office",
    applicant: channels.applicant.inquiry && channels.applicant.assistant ? "Inquiry + Guided Self-Service" : channels.applicant.inquiry ? "General Inquiries Only" : "Applicant Portal Service Paused"
  }));
  let usageLevel = "normal";
  const usageInfo = derived(() => {
    switch (usageLevel) {
      case "near_cap":
        return {
          percent: 86,
          label: "Near Monthly Cap (86%)",
          badgeVariant: "outline",
          badgeClass: "border-amber-500/50 text-amber-700 dark:text-amber-300 bg-amber-500/10",
          staffAction: "High volume period. Assistant continues operating. Office staff should prioritize urgent admissions inquiries."
        };
      case "at_limit":
        return {
          percent: 100,
          label: "Monthly Limit Reached (100%)",
          badgeVariant: "destructive",
          badgeClass: "bg-rose-500 text-white",
          staffAction: "Assistant queries are paused until the monthly reset. Core desk operations are unaffected: all standard forms, manual document verification, and scoring remain fully operational."
        };
      case "reconciling":
        return {
          percent: 74,
          label: "Checking Disconnected Requests",
          badgeVariant: "secondary",
          badgeClass: "bg-muted text-foreground",
          staffAction: "Two interrupted requests are being safely verified by the system. Normal office operations continue as usual."
        };
      default:
        return {
          percent: 64,
          label: "Healthy Operation (64%)",
          badgeVariant: "default",
          badgeClass: "bg-emerald-600 text-white",
          staffAction: "Capacity is optimal across all office desks. Standard inquiry and task assistance are running normally."
        };
    }
  });
  let registrarShare = 45;
  let guidanceShare = 35;
  const allocationMath = derived(() => {
    const totalAssigned = registrarShare + guidanceShare;
    const reserve = 100 - totalAssigned;
    const isOver = totalAssigned > 100;
    return {
      totalAssigned,
      reserve,
      isOver,
      overAmount: Math.abs(reserve)
    };
  });
  let guidanceDocApproved = false;
  let jointPolicyApproved = false;
  const readinessScore = derived(() => {
    let passed = 2;
    if (guidanceDocApproved) passed += 1;
    if (jointPolicyApproved) passed += 1;
    return { passed, total: 4, percent: Math.round(passed / 4 * 100) };
  });
  function resetAll() {
    activeView = "access";
    channels = {
      registrar: { inquiry: true, assistant: true },
      guidance: { inquiry: true, assistant: false },
      applicant: { inquiry: true, assistant: false }
    };
    officialResultsReleased = false;
    usageLevel = "normal";
    registrarShare = 45;
    guidanceShare = 35;
    guidanceDocApproved = false;
    jointPolicyApproved = false;
  }
  let $$settled = true;
  let $$inner_renderer;
  function $$render_inner($$renderer2) {
    $$renderer2.push(`<div class="overflow-hidden rounded-2xl border border-border/70 bg-card shadow-xs"><div class="flex flex-col gap-2.5 border-b border-border/60 bg-muted/20 px-4 py-3.5 sm:flex-row sm:items-center sm:justify-between sm:px-6"><div><div class="flex flex-wrap items-center gap-2"><h3 class="text-sm font-semibold text-foreground sm:text-base">Office Operations &amp; Controls Preview</h3> `);
    Badge($$renderer2, {
      variant: "outline",
      class: "border-amber-500/40 bg-amber-500/10 text-[11px] text-amber-700 dark:text-amber-300",
      children: ($$renderer3) => {
        $$renderer3.push(`<!---->Planned · Synthetic Preview`);
      },
      $$slots: { default: true }
    });
    $$renderer2.push(`<!----></div> <p class="mt-0.5 text-xs text-muted-foreground">Simulate office service switches, capacity limits, unapproved quota proposals, and knowledge reviews.</p></div> `);
    Button($$renderer2, {
      variant: "outline",
      size: "sm",
      class: "h-8 gap-1.5 self-start text-xs text-muted-foreground hover:text-foreground sm:self-center",
      onclick: resetAll,
      "aria-label": "Reset simulation to initial values",
      children: ($$renderer3) => {
        Rotate_ccw($$renderer3, { class: "size-3.5" });
        $$renderer3.push(`<!----> <span>Reset Defaults</span>`);
      },
      $$slots: { default: true }
    });
    $$renderer2.push(`<!----></div> <nav class="flex border-b border-border/60 bg-background px-3 py-1.5 overflow-x-auto sm:px-5" aria-label="Staff operating sections"><div class="flex gap-1.5 min-w-max"><!--[-->`);
    const each_array = ensure_array_like([
      { id: "access", label: "Office Access", icon: Building_2 },
      { id: "usage", label: "Usage & Limits", icon: Activity },
      {
        id: "allocation",
        label: "Office Quotas (What-If)",
        icon: Sliders_horizontal
      },
      {
        id: "knowledge",
        label: "Knowledge & Readiness",
        icon: File_text
      }
    ]);
    for (let $$index = 0, $$length = each_array.length; $$index < $$length; $$index++) {
      let tab = each_array[$$index];
      $$renderer2.push(`<button type="button"${attr("aria-pressed", activeView === tab.id)}${attr_class(`flex items-center gap-2 rounded-lg px-3 py-1.5 text-xs font-medium transition-all ${activeView === tab.id ? "bg-primary text-primary-foreground shadow-xs" : "text-muted-foreground hover:bg-muted/80 hover:text-foreground"}`)}>`);
      if (tab.icon) {
        $$renderer2.push("<!--[-->");
        tab.icon($$renderer2, { class: "size-3.5" });
        $$renderer2.push("<!--]-->");
      } else {
        $$renderer2.push("<!--[!-->");
        $$renderer2.push("<!--]-->");
      }
      $$renderer2.push(` <span>${escape_html(tab.label)}</span></button>`);
    }
    $$renderer2.push(`<!--]--></div></nav> `);
    if (activeView === "access") {
      $$renderer2.push("<!--[0-->");
      $$renderer2.push(`<div class="p-4 sm:p-6 space-y-6"><div class="rounded-xl border border-primary/20 bg-primary/5 p-3.5 text-xs text-muted-foreground"><strong class="text-foreground">Office Policy Rule:</strong> Office staff can pause or limit Xiam on their desk (e.g. during busy intake hours), but office switches cannot grant permissions beyond official university policy.</div> <div class="grid grid-cols-1 gap-4 md:grid-cols-3"><div class="rounded-xl border border-border/70 bg-card p-4 space-y-3"><div class="flex items-start justify-between"><div><h4 class="text-sm font-semibold text-foreground">Registrar Desk</h4> <p class="text-[11px] text-muted-foreground">Admissions, Credentials, Records</p></div> `);
      Badge($$renderer2, {
        variant: "outline",
        class: "text-[10px]",
        children: ($$renderer3) => {
          $$renderer3.push(`<!---->Office Desk`);
        },
        $$slots: { default: true }
      });
      $$renderer2.push(`<!----></div> <div class="space-y-2.5 rounded-lg border border-border/50 bg-muted/20 p-3 text-xs"><div class="flex items-center justify-between"><label for="reg-inquiry" class="cursor-pointer"><span class="font-medium text-foreground block">Inquiry Assistant</span> <span class="text-[10px] text-muted-foreground">Requirements &amp; FAQ</span></label> `);
      Switch($$renderer2, {
        id: "reg-inquiry",
        "aria-label": "Toggle Registrar inquiry",
        get checked() {
          return channels.registrar.inquiry;
        },
        set checked($$value) {
          channels.registrar.inquiry = $$value;
          $$settled = false;
        }
      });
      $$renderer2.push(`<!----></div> <div class="border-t border-border/40 pt-2 flex items-center justify-between"><label for="reg-assist" class="cursor-pointer"><span class="font-medium text-foreground block">Task Assistance</span> <span class="text-[10px] text-muted-foreground">Checklist proposals</span></label> `);
      Switch($$renderer2, {
        id: "reg-assist",
        "aria-label": "Toggle Registrar task assistant",
        get checked() {
          return channels.registrar.assistant;
        },
        set checked($$value) {
          channels.registrar.assistant = $$value;
          $$settled = false;
        }
      });
      $$renderer2.push(`<!----></div></div> <div class="text-xs"><span class="text-[10px] text-muted-foreground uppercase font-semibold block">Effective Status:</span> <span class="text-foreground font-medium">${escape_html(effectiveStatus().registrar)}</span></div></div> <div class="rounded-xl border border-border/70 bg-card p-4 space-y-3"><div class="flex items-start justify-between"><div><h4 class="text-sm font-semibold text-foreground">Guidance Desk</h4> <p class="text-[11px] text-muted-foreground">Testing, Diagnostics, Counseling</p></div> `);
      Badge($$renderer2, {
        variant: "outline",
        class: "text-[10px]",
        children: ($$renderer3) => {
          $$renderer3.push(`<!---->Office Desk`);
        },
        $$slots: { default: true }
      });
      $$renderer2.push(`<!----></div> <div class="space-y-2.5 rounded-lg border border-border/50 bg-muted/20 p-3 text-xs"><div class="flex items-center justify-between"><label for="gui-inquiry" class="cursor-pointer"><span class="font-medium text-foreground block">Inquiry Assistant</span> <span class="text-[10px] text-muted-foreground">Exam rules &amp; guidance</span></label> `);
      Switch($$renderer2, {
        id: "gui-inquiry",
        "aria-label": "Toggle Guidance inquiry",
        get checked() {
          return channels.guidance.inquiry;
        },
        set checked($$value) {
          channels.guidance.inquiry = $$value;
          $$settled = false;
        }
      });
      $$renderer2.push(`<!----></div> <div class="border-t border-border/40 pt-2 flex items-center justify-between"><label for="gui-assist" class="cursor-pointer"><span class="font-medium text-foreground block">Task Assistance</span> <span class="text-[10px] text-muted-foreground">Interview scheduling</span></label> `);
      Switch($$renderer2, {
        id: "gui-assist",
        "aria-label": "Toggle Guidance task assistant",
        get checked() {
          return channels.guidance.assistant;
        },
        set checked($$value) {
          channels.guidance.assistant = $$value;
          $$settled = false;
        }
      });
      $$renderer2.push(`<!----></div></div> <div class="text-xs"><span class="text-[10px] text-muted-foreground uppercase font-semibold block">Effective Status:</span> <span class="text-foreground font-medium">${escape_html(effectiveStatus().guidance)}</span></div></div> <div class="rounded-xl border border-border/70 bg-card p-4 space-y-3"><div class="flex items-start justify-between"><div><h4 class="text-sm font-semibold text-foreground">Applicant Portal</h4> <p class="text-[11px] text-muted-foreground">Candidate Self-Service</p></div> `);
      Badge($$renderer2, {
        variant: "outline",
        class: "text-[10px]",
        children: ($$renderer3) => {
          $$renderer3.push(`<!---->Student Facing`);
        },
        $$slots: { default: true }
      });
      $$renderer2.push(`<!----></div> <div class="space-y-2.5 rounded-lg border border-border/50 bg-muted/20 p-3 text-xs"><div class="flex items-center justify-between"><label for="app-inquiry" class="cursor-pointer"><span class="font-medium text-foreground block">General Inquiries</span> <span class="text-[10px] text-muted-foreground">Public admissions questions</span></label> `);
      Switch($$renderer2, {
        id: "app-inquiry",
        "aria-label": "Toggle Applicant inquiry",
        get checked() {
          return channels.applicant.inquiry;
        },
        set checked($$value) {
          channels.applicant.inquiry = $$value;
          $$settled = false;
        }
      });
      $$renderer2.push(`<!----></div> <div class="border-t border-border/40 pt-2 flex items-center justify-between"><label for="app-assist" class="cursor-pointer"><span class="font-medium text-foreground block">Task Guidance</span> <span class="text-[10px] text-muted-foreground">Step-by-step submission</span></label> `);
      Switch($$renderer2, {
        id: "app-assist",
        "aria-label": "Toggle Applicant task guidance",
        get checked() {
          return channels.applicant.assistant;
        },
        set checked($$value) {
          channels.applicant.assistant = $$value;
          $$settled = false;
        }
      });
      $$renderer2.push(`<!----></div></div> <div class="text-xs"><span class="text-[10px] text-muted-foreground uppercase font-semibold block">Effective Status:</span> <span class="text-foreground font-medium">${escape_html(effectiveStatus().applicant)}</span></div></div></div> <div class="rounded-xl border border-border/70 bg-muted/10 p-4 space-y-3"><div class="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between"><div><h4 class="text-sm font-semibold text-foreground">Official Exam Results Release Gate</h4> <p class="text-xs text-muted-foreground">Experience how local switches respect university release authority.</p></div> <div class="flex items-center gap-2.5 rounded-lg border border-border/60 bg-background px-3 py-1.5"><label for="switch-gate" class="text-xs font-medium cursor-pointer">Official Results Released:</label> `);
      Switch($$renderer2, {
        id: "switch-gate",
        "aria-label": "Toggle official results release state",
        get checked() {
          return officialResultsReleased;
        },
        set checked($$value) {
          officialResultsReleased = $$value;
          $$settled = false;
        }
      });
      $$renderer2.push(`<!----> <span${attr_class(`text-xs font-semibold ${officialResultsReleased ? "text-emerald-600 dark:text-emerald-400" : "text-amber-600 dark:text-amber-400"}`)}>${escape_html(officialResultsReleased ? "Released" : "Withheld")}</span></div></div> <div class="rounded-lg border border-border/50 bg-background p-3.5 text-xs space-y-2"><p class="text-muted-foreground italic">Applicant asks: "Can I view my entrance examination rating?"</p> `);
      if (officialResultsReleased) {
        $$renderer2.push("<!--[0-->");
        $$renderer2.push(`<div class="rounded-md border border-emerald-500/30 bg-emerald-500/5 p-2.5 text-emerald-800 dark:text-emerald-200 flex items-center gap-2 font-medium">`);
        Circle_check($$renderer2, { class: "size-4 text-emerald-500 shrink-0" });
        $$renderer2.push(`<!----> Official results are released. Authorized result is available only for the applicant's own released record.</div>`);
      } else {
        $$renderer2.push("<!--[-1-->");
        $$renderer2.push(`<div class="rounded-md border border-amber-500/40 bg-amber-500/5 p-2.5 text-amber-800 dark:text-amber-200 flex items-center gap-2 font-medium">`);
        Triangle_alert($$renderer2, { class: "size-4 text-amber-500 shrink-0" });
        $$renderer2.push(`<!----> Results unreleased by university policy. Xiam refuses disclosure even if office switches are active.</div>`);
      }
      $$renderer2.push(`<!--]--></div></div></div>`);
    } else if (activeView === "usage") {
      $$renderer2.push("<!--[1-->");
      $$renderer2.push(`<div class="p-4 sm:p-6 space-y-6"><div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between"><div><h4 class="text-sm font-semibold text-foreground">Monthly Capacity &amp; Limit Feedback</h4> <p class="text-xs text-muted-foreground">Select a scenario to preview system behavior and staff instructions.</p></div> <div class="flex flex-wrap gap-1 rounded-lg border border-border/60 bg-muted/40 p-1"><!--[-->`);
      const each_array_1 = ensure_array_like([
        { id: "normal", label: "Normal (64%)" },
        { id: "near_cap", label: "Near Cap (86%)" },
        { id: "at_limit", label: "At Limit (100%)" },
        { id: "reconciling", label: "Interrupted Request" }
      ]);
      for (let $$index_1 = 0, $$length = each_array_1.length; $$index_1 < $$length; $$index_1++) {
        let sc = each_array_1[$$index_1];
        $$renderer2.push(`<button type="button"${attr("aria-pressed", usageLevel === sc.id)}${attr_class(`rounded px-2.5 py-1 text-xs font-medium transition-colors ${usageLevel === sc.id ? "bg-background text-foreground shadow-xs" : "text-muted-foreground hover:text-foreground"}`)}>${escape_html(sc.label)}</button>`);
      }
      $$renderer2.push(`<!--]--></div></div> <div class="rounded-xl border border-border/70 bg-card p-5 space-y-4"><div class="flex items-center justify-between"><div class="space-y-0.5"><span class="text-xs text-muted-foreground font-medium uppercase">Current Monthly Capacity</span> <div class="text-2xl font-bold text-foreground">${escape_html(usageInfo().percent)}% Used</div></div> `);
      Badge($$renderer2, {
        class: `text-xs ${stringify(usageInfo().badgeClass)}`,
        children: ($$renderer3) => {
          $$renderer3.push(`<!---->${escape_html(usageInfo().label)}`);
        },
        $$slots: { default: true }
      });
      $$renderer2.push(`<!----></div> <div class="h-2.5 w-full overflow-hidden rounded-full bg-muted"><div${attr_class(`h-full rounded-full transition-all duration-300 ${usageInfo().percent >= 100 ? "bg-rose-500" : usageInfo().percent >= 85 ? "bg-amber-500" : "bg-primary"}`)}${attr_style(`width: ${stringify(usageInfo().percent)}%`)}></div></div> <div class="rounded-lg border border-border/50 bg-background p-3.5 text-xs space-y-1.5"><strong class="text-foreground block">Staff Guidance &amp; Action:</strong> <p class="text-muted-foreground leading-relaxed">${escape_html(usageInfo().staffAction)}</p></div> <div class="rounded-lg border border-border/40 bg-muted/20 p-3 text-[11px] text-muted-foreground"><strong>Non-Interruption Assurance:</strong> AI assistance is an optional aid. University operations never depend on Xiam: desk staff can always complete admissions, reviews, and registrations using regular web forms.</div></div></div>`);
    } else if (activeView === "allocation") {
      $$renderer2.push("<!--[2-->");
      $$renderer2.push(`<div class="p-4 sm:p-6 space-y-6"><div class="rounded-xl border border-amber-500/40 bg-amber-500/10 p-4 space-y-1.5 text-xs text-amber-900 dark:text-amber-200"><div class="flex items-center gap-2 font-semibold">`);
      Triangle_alert($$renderer2, { class: "size-4 text-amber-600 dark:text-amber-400 shrink-0" });
      $$renderer2.push(`<!----> <span>Proposed Quota Model — Pending Founder Policy Decision</span></div> <p class="leading-relaxed">This what-if tool illustrates a proposed allocation model for separate monthly shares across individual offices so busy enrollment periods in one office do not deplete another, pending policy review.</p></div> <div class="grid grid-cols-1 gap-6 md:grid-cols-2"><div class="rounded-xl border border-border/70 bg-card p-4 space-y-4"><h4 class="text-sm font-semibold text-foreground">Adjust Office Allowances</h4> <div class="space-y-2"><div class="flex justify-between text-xs"><label for="slider-reg" class="font-medium text-foreground">Registrar Office Allowance:</label> <span class="font-semibold text-primary">${escape_html(registrarShare)}%</span></div> <input id="slider-reg" type="range" min="10" max="80" step="5"${attr("value", registrarShare)} class="w-full accent-primary h-2 bg-muted rounded-lg cursor-pointer"/></div> <div class="space-y-2"><div class="flex justify-between text-xs"><label for="slider-gui" class="font-medium text-foreground">Guidance &amp; Testing Allowance:</label> <span class="font-semibold text-primary">${escape_html(guidanceShare)}%</span></div> <input id="slider-gui" type="range" min="10" max="80" step="5"${attr("value", guidanceShare)} class="w-full accent-primary h-2 bg-muted rounded-lg cursor-pointer"/></div> <p class="text-[11px] text-muted-foreground">Remaining unallocated share is preserved for applicant self-service and emergency buffer.</p></div> <div class="rounded-xl border border-border/70 bg-card p-4 space-y-3"><h4 class="text-sm font-semibold text-foreground">Ceiling Check</h4> `);
      if (allocationMath().isOver) {
        $$renderer2.push("<!--[0-->");
        $$renderer2.push(`<div class="rounded-lg border border-rose-500/40 bg-rose-500/10 p-3 text-xs text-rose-800 dark:text-rose-200 space-y-1"><div class="font-semibold flex items-center gap-1.5">`);
        Circle_x($$renderer2, { class: "size-4 text-rose-500" });
        $$renderer2.push(`<!----> Exceeds 100% Institution Ceiling</div> <p>Combined allocations equal <strong>${escape_html(allocationMath().totalAssigned)}%</strong> (+${escape_html(allocationMath().overAmount)}% over budget). Lower one office share to fit within university capacity.</p></div>`);
      } else {
        $$renderer2.push("<!--[-1-->");
        $$renderer2.push(`<div class="rounded-lg border border-emerald-500/30 bg-emerald-500/10 p-3 text-xs text-emerald-800 dark:text-emerald-200 space-y-1"><div class="font-semibold flex items-center gap-1.5">`);
        Circle_check($$renderer2, { class: "size-4 text-emerald-500" });
        $$renderer2.push(`<!----> Balanced Allocation Plan</div> <p><strong>${escape_html(allocationMath().reserve)}%</strong> remains reserved for student portal inquiries and shared buffer.</p></div>`);
      }
      $$renderer2.push(`<!--]--> <div class="space-y-1 text-xs"><div class="flex h-3 w-full overflow-hidden rounded-md bg-muted text-[10px]"><div class="bg-blue-600 transition-all"${attr_style(`width: ${stringify(registrarShare)}%`)}></div> <div class="bg-emerald-600 transition-all"${attr_style(`width: ${stringify(guidanceShare)}%`)}></div> `);
      if (!allocationMath().isOver) {
        $$renderer2.push("<!--[0-->");
        $$renderer2.push(`<div class="bg-slate-400 dark:bg-slate-600 transition-all"${attr_style(`width: ${stringify(allocationMath().reserve)}%`)}></div>`);
      } else {
        $$renderer2.push("<!--[-1-->");
      }
      $$renderer2.push(`<!--]--></div> <div class="flex justify-between text-[11px] text-muted-foreground pt-1"><span>Registrar: ${escape_html(registrarShare)}%</span> <span>Guidance: ${escape_html(guidanceShare)}%</span> <span>Buffer: ${escape_html(allocationMath().reserve)}%</span></div></div></div></div></div>`);
    } else if (activeView === "knowledge") {
      $$renderer2.push("<!--[3-->");
      $$renderer2.push(`<div class="p-4 sm:p-6 space-y-6"><div class="flex flex-col gap-2 rounded-xl border border-border/70 bg-card p-4 sm:flex-row sm:items-center sm:justify-between"><div><h4 class="text-sm font-semibold text-foreground">Setup Hub: Knowledge &amp; Governance Readiness</h4> <p class="text-xs text-muted-foreground">Office guidance documents must be signed off by authorized reviewers before assistant answers adopt them.</p></div> `);
      Badge($$renderer2, {
        variant: readinessScore().passed === 4 ? "default" : "secondary",
        class: "text-xs",
        children: ($$renderer3) => {
          $$renderer3.push(`<!---->${escape_html(readinessScore().percent)}% Ready (${escape_html(readinessScore().passed)}/4 items)`);
        },
        $$slots: { default: true }
      });
      $$renderer2.push(`<!----></div> <div class="space-y-3"><h5 class="text-xs font-semibold uppercase tracking-wider text-muted-foreground">Office Knowledge Sources</h5> <div class="rounded-xl border border-border/70 bg-card p-3.5 text-xs flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between"><div><div class="flex items-center gap-2"><span class="font-semibold text-foreground">Freshman Admission &amp; Document Guide 2026</span> `);
      Badge($$renderer2, {
        variant: "outline",
        class: "text-[10px] text-emerald-600 dark:text-emerald-400 border-emerald-500/30",
        children: ($$renderer3) => {
          $$renderer3.push(`<!---->Active`);
        },
        $$slots: { default: true }
      });
      $$renderer2.push(`<!----></div> <p class="text-[11px] text-muted-foreground">Owned by Registrar Office · Approved by Head Officer (Separation of duty verified)</p></div> <span class="text-[11px] text-muted-foreground">Published</span></div> <div class="rounded-xl border border-border/70 bg-card p-3.5 text-xs flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between"><div><div class="flex items-center gap-2"><span class="font-semibold text-foreground">Aptitude Diagnostic Rubric &amp; Counseling Guide</span> `);
      Badge($$renderer2, {
        variant: "outline",
        class: `text-[10px] ${guidanceDocApproved ? "text-emerald-600 dark:text-emerald-400" : "text-amber-600 dark:text-amber-400"}`,
        children: ($$renderer3) => {
          $$renderer3.push(`<!---->${escape_html(guidanceDocApproved ? "Approved" : "Pending Review")}`);
        },
        $$slots: { default: true }
      });
      $$renderer2.push(`<!----></div> <p class="text-[11px] text-muted-foreground">Owned by Guidance Office · Created by Testing Counselor</p></div> `);
      Button($$renderer2, {
        size: "sm",
        variant: guidanceDocApproved ? "outline" : "default",
        class: "h-7 text-xs",
        onclick: () => guidanceDocApproved = !guidanceDocApproved,
        children: ($$renderer3) => {
          $$renderer3.push(`<!---->${escape_html(guidanceDocApproved ? "Revoke Approval" : "Approve as Guidance Head")}`);
        },
        $$slots: { default: true }
      });
      $$renderer2.push(`<!----></div> <div class="rounded-xl border border-border/70 bg-card p-3.5 text-xs flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between"><div><div class="flex items-center gap-2"><span class="font-semibold text-foreground">Joint Cutoff Criteria &amp; Program Roster Guidelines</span> `);
      Badge($$renderer2, {
        variant: "outline",
        class: `text-[10px] ${jointPolicyApproved ? "text-emerald-600 dark:text-emerald-400" : "text-amber-600 dark:text-amber-400"}`,
        children: ($$renderer3) => {
          $$renderer3.push(`<!---->${escape_html(jointPolicyApproved ? "Dual Sign-off Complete" : "Awaiting Guidance Sign-off")}`);
        },
        $$slots: { default: true }
      });
      $$renderer2.push(`<!----></div> <p class="text-[11px] text-muted-foreground">Cross-Office Policy · Registrar signed off; requires Guidance sign-off</p></div> `);
      Button($$renderer2, {
        size: "sm",
        variant: jointPolicyApproved ? "outline" : "default",
        class: "h-7 text-xs",
        onclick: () => jointPolicyApproved = !jointPolicyApproved,
        children: ($$renderer3) => {
          $$renderer3.push(`<!---->${escape_html(jointPolicyApproved ? "Withdraw Sign-off" : "Sign off as Guidance Officer")}`);
        },
        $$slots: { default: true }
      });
      $$renderer2.push(`<!----></div></div></div>`);
    } else {
      $$renderer2.push("<!--[-1-->");
    }
    $$renderer2.push(`<!--]--> <div class="border-t border-border/60 bg-muted/20 px-4 py-2.5 sm:px-6 text-xs text-muted-foreground flex flex-col sm:flex-row sm:items-center sm:justify-between gap-1"><span class="flex items-center gap-1.5">`);
    Lock($$renderer2, { class: "size-3.5 text-muted-foreground" });
    $$renderer2.push(`<!----> Deployment settings (model, prompts, credentials, emergency stop) are managed by the deployment and operations team.</span> <span class="font-mono text-[10px]">Synthetic UI Lab Preview</span></div></div>`);
  }
  do {
    $$settled = true;
    $$inner_renderer = $$renderer.copy();
    $$render_inner($$inner_renderer);
  } while (!$$settled);
  $$renderer.subsume($$inner_renderer);
}
function ImportEnginePreview($$renderer, $$props) {
  $$renderer.component(($$renderer2) => {
    let sheets = [
      {
        id: "sheet1",
        name: "Batch 1 - Morning (Room 101)",
        rows: 142,
        enabled: true,
        sessionMapping: "EX-2026-001 (08:00 AM)"
      },
      {
        id: "sheet2",
        name: "Batch 2 - Afternoon (Room 102)",
        rows: 118,
        enabled: true,
        sessionMapping: "EX-2026-002 (01:00 PM)"
      },
      {
        id: "sheet3",
        name: "Vendor Meta & Legend",
        rows: 0,
        enabled: false,
        sessionMapping: "Skip"
      }
    ];
    const activeSheetsCount = derived(() => sheets.filter((s) => s.enabled).length);
    const totalRowsCount = derived(() => sheets.filter((s) => s.enabled).reduce((acc, s) => acc + s.rows, 0));
    const checks = derived(() => [
      {
        id: "format",
        label: "File format & encoding",
        status: "pass",
        detail: "XLSX (Microsoft Excel OpenXML) · UTF-8 safe"
      },
      {
        id: "sheets",
        label: "Multi-sheet structure",
        status: "pass",
        detail: `${sheets.length} sheets detected (${activeSheetsCount()} active, ${totalRowsCount()} total examinee rows)`
      },
      {
        id: "identity",
        label: "Applicant identity columns",
        status: "pass",
        detail: "first_name, last_name, birthdate, sex, strand, course_preference_1 detected"
      },
      {
        id: "scores",
        label: "Aptitude score columns",
        status: "pass",
        detail: "4 aptitude areas detected: MATH, SCIENCE, ENGLISH, ABSTRACT"
      },
      {
        id: "fuzzy",
        label: "Fuzzy alias mappings",
        status: "warn",
        detail: '"Family Name" → last_name; "Gender" → sex; "Math Raw" → MATH'
      },
      {
        id: "sessions",
        label: "Session allocation",
        status: "pass",
        detail: "Direct Assessment intake context (auto-binds exam assignment & grading)"
      }
    ]);
    const columnMappings = derived(() => [
      {
        raw: "Applicant No / LRN",
        mapped: "applicant_number",
        type: "Applicant",
        status: "Required"
      },
      {
        raw: "First Name",
        mapped: "first_name",
        type: "Applicant",
        status: "Required"
      },
      {
        raw: "Family Name",
        mapped: "last_name",
        type: "Applicant",
        status: "Fuzzy Match"
      },
      {
        raw: "Date of Birth",
        mapped: "birthdate",
        type: "Applicant",
        status: "Optional"
      },
      {
        raw: "Gender",
        mapped: "sex",
        type: "Applicant",
        status: "Fuzzy Match"
      },
      {
        raw: "SHS Strand",
        mapped: "strand",
        type: "Applicant",
        status: "Optional"
      },
      {
        raw: "Course Choice 1",
        mapped: "course_preference_1",
        type: "Applicant",
        status: "Required"
      },
      ...[
        {
          raw: "Math Raw Score",
          mapped: "score.MATH",
          type: "Scoring",
          status: "Aptitude"
        },
        {
          raw: "Science Raw Score",
          mapped: "score.SCIENCE",
          type: "Scoring",
          status: "Aptitude"
        },
        {
          raw: "English Raw Score",
          mapped: "score.ENGLISH",
          type: "Scoring",
          status: "Aptitude"
        },
        {
          raw: "Abstract Raw Score",
          mapped: "score.ABSTRACT",
          type: "Scoring",
          status: "Aptitude"
        }
      ]
    ]);
    let selectedRowIds = /* @__PURE__ */ new Set([1, 2, 4]);
    let expandedRowId = 3;
    const sampleRows = [
      {
        id: 1,
        sheet: "Batch 1 - Morning",
        row: 2,
        ref: "2026-0041",
        name: "Maria Clara Santos",
        strand: "STEM",
        course: "BSN",
        scores: { MATH: 88, SCIENCE: 92, ENGLISH: 85, ABSTRACT: 90 },
        isValid: true,
        error: null
      },
      {
        id: 2,
        sheet: "Batch 1 - Morning",
        row: 3,
        ref: "2026-0042",
        name: "Juan Dela Cruz",
        strand: "ABM",
        course: "BSBA",
        scores: { MATH: 74, SCIENCE: 80, ENGLISH: 89, ABSTRACT: 82 },
        isValid: true,
        error: null
      },
      {
        id: 3,
        sheet: "Batch 1 - Morning",
        row: 4,
        ref: "2026-0043",
        name: "Roberto Alvarez Jr.",
        strand: "HUMSS",
        course: "BSPSY",
        scores: { MATH: "N/A", SCIENCE: 70, ENGLISH: 88, ABSTRACT: 75 },
        isValid: false,
        error: 'Score for MATH must be a valid numeric score (got "N/A"). Correct or exclude row.'
      },
      {
        id: 4,
        sheet: "Batch 2 - Afternoon",
        row: 2,
        ref: "2026-0044",
        name: "Alyssa Nicole Tan",
        strand: "STEM",
        course: "BSCS",
        scores: { MATH: 95, SCIENCE: 94, ENGLISH: 91, ABSTRACT: 96 },
        isValid: true,
        error: null
      }
    ];
    const displayedRows = derived(() => sampleRows);
    function toggleRow(id) {
      const next = new Set(selectedRowIds);
      if (next.has(id)) next.delete(id);
      else next.add(id);
      selectedRowIds = next;
    }
    function toggleAll() {
      if (selectedRowIds.size === sampleRows.filter((r) => r.isValid).length) {
        selectedRowIds = /* @__PURE__ */ new Set();
      } else {
        selectedRowIds = new Set(sampleRows.filter((r) => r.isValid).map((r) => r.id));
      }
    }
    let simulating = false;
    let simulatedSuccess = false;
    function runSimulation() {
      simulating = true;
      simulatedSuccess = false;
      setTimeout(
        () => {
          simulating = false;
          simulatedSuccess = true;
        },
        1200
      );
    }
    $$renderer2.push(`<div class="space-y-6 rounded-2xl border border-border bg-card p-6 shadow-sm"><div class="flex flex-col gap-4 border-b border-border/60 pb-5 sm:flex-row sm:items-center sm:justify-between"><div><div class="flex items-center gap-2"><span class="inline-flex size-9 items-center justify-center rounded-xl bg-primary/10 text-primary">`);
    File_spreadsheet($$renderer2, { class: "size-5" });
    $$renderer2.push(`<!----></span> <div><h2 class="text-xl font-bold tracking-tight text-foreground">Next-Gen Ingestion Pipeline</h2> <p class="text-xs text-muted-foreground">Interactive UI Lab prototype · multi-sheet, multi-session, all-in-one</p></div></div></div> <div class="flex flex-wrap items-center gap-1.5 rounded-xl border border-border/80 bg-muted/40 p-1"><button type="button"${attr_class(`flex items-center gap-1.5 rounded-lg px-3 py-1.5 text-xs font-semibold transition-all ${"bg-background text-foreground shadow-sm ring-1 ring-border/50"}`)}>`);
    Sparkles($$renderer2, { class: "size-3.5 text-primary" });
    $$renderer2.push(`<!----> All-in-One Intake</button> <button type="button"${attr_class(`flex items-center gap-1.5 rounded-lg px-3 py-1.5 text-xs font-semibold transition-all ${"text-muted-foreground hover:text-foreground"}`)}>`);
    Layers($$renderer2, { class: "size-3.5 text-info" });
    $$renderer2.push(`<!----> Multi-Session Sheets</button> <button type="button"${attr_class(`flex items-center gap-1.5 rounded-lg px-3 py-1.5 text-xs font-semibold transition-all ${"text-muted-foreground hover:text-foreground"}`)}>`);
    Users($$renderer2, { class: "size-3.5 text-muted-foreground" });
    $$renderer2.push(`<!----> Contextual (Applicants Only)</button></div></div> <div class="rounded-xl border border-primary/20 bg-primary/5 p-4"><div class="flex items-start gap-3">`);
    Sparkles($$renderer2, { class: "mt-0.5 size-5 shrink-0 text-primary" });
    $$renderer2.push(`<!----> <div class="space-y-1 text-xs"><p class="font-semibold text-foreground">`);
    {
      $$renderer2.push("<!--[0-->");
      $$renderer2.push(`All-in-One Pipeline Active: Single-Pass Ingestion`);
    }
    $$renderer2.push(`<!--]--></p> <p class="text-muted-foreground leading-relaxed">`);
    {
      $$renderer2.push("<!--[0-->");
      $$renderer2.push(`Applicant demographics, course preferences, and raw exam scores are ingested in a <strong>single database transaction</strong>. No manual stops at scheduling, monitoring, or grading session creation required.`);
    }
    $$renderer2.push(`<!--]--></p></div></div></div> <div class="space-y-3"><div class="flex items-center justify-between"><h3 class="text-xs font-bold uppercase tracking-wider text-muted-foreground flex items-center gap-1.5">`);
    Layers($$renderer2, { class: "size-3.5" });
    $$renderer2.push(`<!----> Step 1: Workbook &amp; Sheet Detection</h3> <span class="text-xs font-medium text-foreground">Active payload: <strong class="font-bold text-primary">${escape_html(totalRowsCount())}</strong> examinee rows</span></div> <div class="grid gap-2.5 sm:grid-cols-3"><!--[-->`);
    const each_array = ensure_array_like(sheets);
    for (let $$index = 0, $$length = each_array.length; $$index < $$length; $$index++) {
      let sheet = each_array[$$index];
      $$renderer2.push(`<div${attr_class(`flex flex-col justify-between rounded-xl border p-3.5 transition-all ${sheet.enabled ? "border-primary/40 bg-card shadow-sm ring-1 ring-primary/20" : "border-border/60 bg-muted/20 opacity-60"}`)}><div class="flex items-start justify-between gap-2"><div class="space-y-0.5"><p class="text-xs font-bold text-foreground truncate max-w-[170px]"${attr("title", sheet.name)}>${escape_html(sheet.name)}</p> <p class="text-[11px] text-muted-foreground">${escape_html(sheet.rows)} rows detected</p></div> `);
      Checkbox($$renderer2, {
        checked: sheet.enabled,
        onCheckedChange: (v) => {
          sheet.enabled = Boolean(v);
        }
      });
      $$renderer2.push(`<!----></div> <div class="mt-3 pt-2 border-t border-border/40 text-[10px]"><span class="text-muted-foreground block">Session target:</span> <span class="font-medium font-mono text-foreground">${escape_html(sheet.sessionMapping)}</span></div></div>`);
    }
    $$renderer2.push(`<!--]--></div></div> <div class="space-y-3"><h3 class="text-xs font-bold uppercase tracking-wider text-muted-foreground flex items-center gap-1.5">`);
    Circle_check($$renderer2, { class: "size-3.5" });
    $$renderer2.push(`<!----> Step 2: Pre-Upload Health Checklist</h3> <div class="grid gap-2 sm:grid-cols-2"><!--[-->`);
    const each_array_1 = ensure_array_like(checks());
    for (let $$index_1 = 0, $$length = each_array_1.length; $$index_1 < $$length; $$index_1++) {
      let check = each_array_1[$$index_1];
      $$renderer2.push(`<div class="flex items-start gap-2.5 rounded-xl border border-border/60 bg-muted/30 p-3">`);
      if (check.status === "pass") {
        $$renderer2.push("<!--[0-->");
        Circle_check($$renderer2, {
          class: "mt-0.5 size-4 shrink-0 text-emerald-600 dark:text-emerald-400"
        });
      } else if (check.status === "warn") {
        $$renderer2.push("<!--[1-->");
        Triangle_alert($$renderer2, {
          class: "mt-0.5 size-4 shrink-0 text-amber-600 dark:text-amber-400"
        });
      } else {
        $$renderer2.push("<!--[-1-->");
        Sparkles($$renderer2, { class: "mt-0.5 size-4 shrink-0 text-primary" });
      }
      $$renderer2.push(`<!--]--> <div class="min-w-0 flex-1 space-y-0.5"><p class="text-xs font-semibold text-foreground">${escape_html(check.label)}</p> <p class="text-[11px] text-muted-foreground leading-tight">${escape_html(check.detail)}</p></div></div>`);
    }
    $$renderer2.push(`<!--]--></div></div> <div class="space-y-3"><div class="flex items-center justify-between"><h3 class="text-xs font-bold uppercase tracking-wider text-muted-foreground flex items-center gap-1.5">`);
    Sliders_horizontal($$renderer2, { class: "size-3.5" });
    $$renderer2.push(`<!----> Step 3: Column Mapping &amp; Aliases</h3> `);
    Badge($$renderer2, {
      variant: "outline",
      class: "text-[10px] font-mono",
      children: ($$renderer3) => {
        $$renderer3.push(`<!---->${escape_html(columnMappings().length)} columns recognized`);
      },
      $$slots: { default: true }
    });
    $$renderer2.push(`<!----></div> <div class="overflow-hidden rounded-xl border border-border bg-card"><div class="overflow-x-auto"><table class="w-full text-left text-xs"><thead class="border-b border-border bg-muted/50 text-muted-foreground"><tr><th class="px-3.5 py-2 font-semibold">Your Excel Header</th><th class="px-2 py-2 text-center"></th><th class="px-3.5 py-2 font-semibold">Mapped System Property</th><th class="px-3.5 py-2 font-semibold">Scope</th><th class="px-3.5 py-2 text-right font-semibold">Status</th></tr></thead><tbody class="divide-y divide-border/60 font-sans"><!--[-->`);
    const each_array_2 = ensure_array_like(columnMappings());
    for (let $$index_2 = 0, $$length = each_array_2.length; $$index_2 < $$length; $$index_2++) {
      let col = each_array_2[$$index_2];
      $$renderer2.push(`<tr class="hover:bg-muted/20"><td class="px-3.5 py-2 font-mono font-medium text-foreground">${escape_html(col.raw)}</td><td class="px-2 py-2 text-center text-muted-foreground">`);
      Arrow_right($$renderer2, { class: "inline size-3" });
      $$renderer2.push(`<!----></td><td class="px-3.5 py-2 font-mono font-semibold text-primary">${escape_html(col.mapped)}</td><td class="px-3.5 py-2"><span${attr_class(`inline-flex rounded px-1.5 py-0.5 text-[10px] font-medium ${col.type === "Scoring" ? "bg-amber-500/10 text-amber-700 dark:text-amber-300" : "bg-primary/10 text-primary"}`)}>${escape_html(col.type)}</span></td><td class="px-3.5 py-2 text-right"><span${attr_class(`text-[11px] ${col.status === "Required" ? "font-semibold text-emerald-600 dark:text-emerald-400" : "text-muted-foreground"}`)}>${escape_html(col.status)}</span></td></tr>`);
    }
    $$renderer2.push(`<!--]--></tbody></table></div></div></div> <div class="space-y-3"><div class="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between"><h3 class="text-xs font-bold uppercase tracking-wider text-muted-foreground flex items-center gap-1.5">`);
    File_check($$renderer2, { class: "size-3.5" });
    $$renderer2.push(`<!----> Step 4: Staged Batch Preview &amp; Validation</h3> <div class="flex items-center gap-2"><button type="button" class="rounded-lg border border-border px-2.5 py-1 text-[11px] font-medium text-foreground hover:bg-accent">${escape_html("Show Only Invalid (1)")}</button> <span class="text-xs text-muted-foreground">Selected: <strong class="text-foreground">${escape_html(selectedRowIds.size)}</strong> of ${escape_html(sampleRows.filter((r) => r.isValid).length)} valid</span></div></div> <div class="overflow-hidden rounded-xl border border-border bg-card"><div class="overflow-x-auto"><table class="w-full text-left text-xs"><thead class="border-b border-border bg-muted/50 text-muted-foreground"><tr><th class="w-8 px-3 py-2 text-center">`);
    Checkbox($$renderer2, {
      checked: selectedRowIds.size > 0 && selectedRowIds.size === sampleRows.filter((r) => r.isValid).length,
      onCheckedChange: toggleAll
    });
    $$renderer2.push(`<!----></th><th class="px-3 py-2 font-semibold">Ref Code</th><th class="px-3 py-2 font-semibold">Examinee Name</th><th class="px-3 py-2 font-semibold">Strand</th><th class="px-3 py-2 font-semibold">Target Course</th>`);
    {
      $$renderer2.push("<!--[0-->");
      $$renderer2.push(`<th class="px-3 py-2 font-semibold">Raw Scores</th>`);
    }
    $$renderer2.push(`<!--]--><th class="px-3 py-2 text-right font-semibold">Status</th></tr></thead><tbody class="divide-y divide-border/60"><!--[-->`);
    const each_array_3 = ensure_array_like(displayedRows());
    for (let $$index_3 = 0, $$length = each_array_3.length; $$index_3 < $$length; $$index_3++) {
      let row = each_array_3[$$index_3];
      $$renderer2.push(`<tr${attr_class(clsx(row.isValid ? "hover:bg-muted/20" : "bg-destructive/5 hover:bg-destructive/10"))}><td class="px-3 py-2 text-center">`);
      Checkbox($$renderer2, {
        checked: selectedRowIds.has(row.id),
        disabled: !row.isValid,
        onCheckedChange: () => toggleRow(row.id)
      });
      $$renderer2.push(`<!----></td><td class="px-3 py-2 font-mono font-semibold text-foreground">${escape_html(row.ref)}</td><td class="px-3 py-2 font-medium text-foreground">${escape_html(row.name)} <span class="block text-[10px] text-muted-foreground">${escape_html(row.sheet)} · Row ${escape_html(row.row)}</span></td><td class="px-3 py-2 text-muted-foreground">${escape_html(row.strand)}</td><td class="px-3 py-2 font-semibold text-foreground">${escape_html(row.course)}</td>`);
      {
        $$renderer2.push("<!--[0-->");
        $$renderer2.push(`<td class="px-3 py-2 font-mono text-[11px]"><span class="text-foreground">M:${escape_html(row.scores.MATH)}</span> <span class="text-muted-foreground">·</span> <span class="text-foreground">S:${escape_html(row.scores.SCIENCE)}</span> <span class="text-muted-foreground">·</span> <span class="text-foreground">E:${escape_html(row.scores.ENGLISH)}</span> <span class="text-muted-foreground">·</span> <span class="text-foreground">A:${escape_html(row.scores.ABSTRACT)}</span></td>`);
      }
      $$renderer2.push(`<!--]--><td class="px-3 py-2 text-right">`);
      if (row.isValid) {
        $$renderer2.push("<!--[0-->");
        $$renderer2.push(`<span class="inline-flex items-center gap-1 font-semibold text-emerald-600 dark:text-emerald-400">`);
        Check($$renderer2, { class: "size-3" });
        $$renderer2.push(`<!----> Valid</span>`);
      } else {
        $$renderer2.push("<!--[-1-->");
        $$renderer2.push(`<button type="button" class="inline-flex items-center gap-1 font-semibold text-destructive underline">`);
        Circle_x($$renderer2, { class: "size-3" });
        $$renderer2.push(`<!----> Fix Issue</button>`);
      }
      $$renderer2.push(`<!--]--></td></tr> `);
      if (!row.isValid && expandedRowId === row.id) {
        $$renderer2.push("<!--[0-->");
        $$renderer2.push(`<tr class="bg-destructive/10"><td${attr("colspan", 7)} class="px-4 py-2 text-destructive"><div class="flex items-center justify-between"><span class="font-medium text-[11px]">${escape_html(row.error)}</span> <span class="text-[10px] uppercase font-bold tracking-wider">Blocked from import</span></div></td></tr>`);
      } else {
        $$renderer2.push("<!--[-1-->");
      }
      $$renderer2.push(`<!--]-->`);
    }
    $$renderer2.push(`<!--]--></tbody></table></div></div></div> <div class="flex flex-col gap-3 pt-3 border-t border-border sm:flex-row sm:items-center sm:justify-between"><div class="text-xs text-muted-foreground">Ready to commit <strong>${escape_html(selectedRowIds.size)}</strong> examinee record${escape_html(selectedRowIds.size === 1 ? "" : "s")}. <span class="block text-[11px] text-muted-foreground/80">Idempotency guaranteed via deterministic batch hashing.</span></div> <div class="flex items-center gap-2">`);
    Button($$renderer2, {
      variant: "outline",
      size: "sm",
      class: "h-9 gap-1.5",
      onclick: () => {
        selectedRowIds = /* @__PURE__ */ new Set([1, 2, 4]);
        simulatedSuccess = false;
      },
      children: ($$renderer3) => {
        Refresh_cw($$renderer3, { class: "size-3.5" });
        $$renderer3.push(`<!----> Reset Demo`);
      },
      $$slots: { default: true }
    });
    $$renderer2.push(`<!----> `);
    Button($$renderer2, {
      size: "sm",
      class: "h-9 gap-2 font-semibold shadow-md shadow-primary/20",
      disabled: simulating || selectedRowIds.size === 0,
      onclick: runSimulation,
      children: ($$renderer3) => {
        if (simulating) {
          $$renderer3.push("<!--[0-->");
          Refresh_cw($$renderer3, { class: "size-3.5 animate-spin" });
          $$renderer3.push(`<!----> Committing Single-Pass Transaction...`);
        } else {
          $$renderer3.push("<!--[-1-->");
          Upload($$renderer3, { class: "size-3.5" });
          $$renderer3.push(`<!----> Execute Single-Pass Import (${escape_html(selectedRowIds.size)})`);
        }
        $$renderer3.push(`<!--]-->`);
      },
      $$slots: { default: true }
    });
    $$renderer2.push(`<!----></div></div> `);
    if (simulatedSuccess) {
      $$renderer2.push("<!--[0-->");
      $$renderer2.push(`<div class="rounded-xl border border-emerald-500/30 bg-emerald-500/10 p-4 text-emerald-800 dark:text-emerald-300"><div class="flex items-start gap-3">`);
      Circle_check($$renderer2, {
        class: "mt-0.5 size-5 shrink-0 text-emerald-600 dark:text-emerald-400"
      });
      $$renderer2.push(`<!----> <div class="space-y-1 text-xs"><p class="font-bold text-emerald-950 dark:text-emerald-100">Single-Pass Ingestion Successful (Batch #ING-2026-X9)</p> <p class="leading-relaxed">${escape_html(selectedRowIds.size)} examinees created and bound to Academic Cycle 2025-2026.
            Exam assignments, direct assessment session records, and encrypted score records committed atomically with zero manual page trips.</p></div></div></div>`);
    } else {
      $$renderer2.push("<!--[-1-->");
    }
    $$renderer2.push(`<!--]--></div>`);
  });
}
function AnnouncementRail($$renderer, $$props) {
  $$renderer.component(($$renderer2) => {
    let { announcements = [] } = $$props;
    $$renderer2.push(`<section id="announcements" class="scroll-mt-24"><div class="border-border/60 flex flex-col gap-4 border-b pb-6 sm:flex-row sm:items-end sm:justify-between"><div><h2 class="text-foreground text-3xl font-semibold tracking-[-0.035em] sm:text-4xl">Announcements</h2> <p class="text-muted-foreground mt-3 max-w-[65ch] text-sm leading-6">Recent updates from campus offices and academic programs.</p></div> `);
    Link($$renderer2, {
      href: "/announcements",
      class: "text-primary decoration-primary/40 inline-flex min-h-11 shrink-0 items-center text-sm font-semibold underline underline-offset-4",
      children: ($$renderer3) => {
        $$renderer3.push(`<!---->All announcements`);
      },
      $$slots: { default: true }
    });
    $$renderer2.push(`<!----></div> `);
    if (announcements.length > 0) {
      $$renderer2.push("<!--[0-->");
      $$renderer2.push(`<div class="divide-border/60 mt-5 divide-y"><!--[-->`);
      const each_array = ensure_array_like(announcements.slice(0, 3));
      for (let $$index = 0, $$length = each_array.length; $$index < $$length; $$index++) {
        let announcement = each_array[$$index];
        $$renderer2.push(`<article data-reveal="" class="grid min-w-0 gap-3 py-6 sm:grid-cols-[minmax(10rem,0.35fr)_minmax(0,1fr)_auto] sm:items-start sm:gap-8"><div class="text-primary flex flex-wrap items-center gap-x-2 text-xs font-semibold"><span>${escape_html(announcement.publisher_label)}</span> `);
        if (announcement.published_at) {
          $$renderer2.push("<!--[0-->");
          $$renderer2.push(`<time${attr("datetime", announcement.published_at)}>${escape_html(new Date(announcement.published_at).toLocaleDateString())}</time>`);
        } else {
          $$renderer2.push("<!--[-1-->");
        }
        $$renderer2.push(`<!--]--></div> <div class="min-w-0"><h3 class="text-foreground text-lg font-semibold tracking-[-0.02em] break-words">${escape_html(announcement.title)}</h3> <p class="text-muted-foreground mt-2 max-w-[65ch] text-sm leading-6 break-words whitespace-pre-wrap">${escape_html(announcement.summary)}</p></div> `);
        Link($$renderer2, {
          href: announcement.href,
          class: "text-foreground decoration-primary/40 hover:text-primary inline-flex min-h-11 items-center text-sm font-semibold underline underline-offset-4 sm:justify-self-end",
          children: ($$renderer3) => {
            $$renderer3.push(`<!---->Read announcement`);
          },
          $$slots: { default: true }
        });
        $$renderer2.push(`<!----></article>`);
      }
      $$renderer2.push(`<!--]--></div>`);
    } else {
      $$renderer2.push("<!--[-1-->");
      $$renderer2.push(`<div class="border-border mt-6 border-y border-dashed py-6"><p class="text-foreground font-medium">No announcements are published yet.</p></div>`);
    }
    $$renderer2.push(`<!--]--></section>`);
  });
}
function Gallery($$renderer, $$props) {
  $$renderer.component(($$renderer2) => {
    const breadcrumbs = [{ label: "UI Kit" }];
    const sections = [
      { id: "public-foundation", label: "Public foundation" },
      { id: "home-sample", label: "Homepage sample" },
      { id: "xiam-sample", label: "Xiam assist" },
      { id: "import-engine", label: "Import pipeline" },
      { id: "locked-set", label: "Locked start set" },
      { id: "tokens", label: "Color tokens" },
      { id: "typography", label: "Typography" },
      { id: "radius-surfaces", label: "Radius & surfaces" },
      { id: "layout-chrome", label: "Layout chrome" },
      { id: "dark-mode", label: "Dark mode" },
      { id: "states", label: "Page states" },
      { id: "buttons", label: "Button" },
      { id: "badges", label: "Badge" },
      { id: "inputs", label: "Input & Label" },
      { id: "textarea", label: "Textarea" },
      { id: "toggles", label: "Checkbox / Switch / Toggle" },
      { id: "select", label: "Select" },
      { id: "tabs", label: "Tabs" },
      { id: "cards", label: "Card" },
      { id: "table", label: "Table & Pagination" },
      { id: "dialog", label: "Dialog" },
      { id: "popover", label: "Popover & Info" },
      { id: "command", label: "Command" },
      { id: "file-upload", label: "File upload" },
      { id: "separator", label: "Separator" },
      { id: "stat-kpi", label: "StatCard / KpiCard" },
      { id: "view-mode", label: "View mode / list" },
      { id: "password", label: "Password strength" },
      { id: "toasts", label: "Toasts" },
      { id: "guide", label: "Guide panels" },
      { id: "management-list", label: "Management list page" },
      { id: "page-header", label: "Page header actions" },
      { id: "filter-toolbar", label: "Filter toolbar" },
      { id: "bulk-bar", label: "Bulk action bar" },
      { id: "mode-banner", label: "Mode banner" },
      { id: "action-dropdown", label: "Action dropdown" }
    ];
    const colorTokens = [
      { name: "background", className: "bg-background border" },
      { name: "foreground", className: "bg-foreground" },
      { name: "primary", className: "bg-primary" },
      { name: "secondary", className: "bg-secondary border" },
      { name: "muted", className: "bg-muted border" },
      { name: "accent", className: "bg-accent border" },
      { name: "destructive", className: "bg-destructive" },
      { name: "success", className: "bg-success" },
      { name: "warning", className: "bg-warning" },
      { name: "info", className: "bg-info" },
      { name: "card", className: "bg-card border" },
      { name: "border", className: "bg-border" }
    ];
    let buttonLoading = false;
    function simulateLoading() {
      buttonLoading = true;
      setTimeout(() => buttonLoading = false, 1500);
    }
    let inputValue = "registrar.clerk@nexiam.test";
    let invalidValue = "not-an-email";
    let textareaValue = "Synthetic multi-line demo content for the textarea primitive.";
    let checkboxChecked = true;
    let checkboxIndeterminate = false;
    let switchOn = true;
    let togglePressed = false;
    let toggleGroupValue = "bold";
    const selectOptions = [
      { value: "registrar", label: "Registrar" },
      { value: "proctor", label: "Proctor" },
      { value: "test-administrator", label: "Test administrator" }
    ];
    let selectValue = "registrar";
    const selectLabel = derived(() => selectOptions.find((o) => o.value === selectValue)?.label ?? "Select an office…");
    let tabsValue = "overview";
    const tableRows = [
      {
        id: 1,
        name: "A. Dela Cruz",
        program: "BS Computer Science",
        status: "Passed"
      },
      {
        id: 2,
        name: "B. Santos",
        program: "BS Accountancy",
        status: "For review"
      },
      {
        id: 3,
        name: "C. Reyes",
        program: "BS Civil Engineering",
        status: "Failed"
      }
    ];
    const paginationDemoPage2of5 = {
      current_page: 2,
      last_page: 5,
      from: 11,
      to: 20,
      total: 48,
      prev_page_url: "/ui-kit#table",
      next_page_url: "/ui-kit#table"
    };
    const paginationDemoPage1of3 = {
      current_page: 1,
      last_page: 3,
      from: 1,
      to: 10,
      total: 27,
      prev_page_url: null,
      next_page_url: "/ui-kit#table"
    };
    const publicPreview = {
      hero: {
        src: "https://images.unsplash.com/photo-1498243691581-b145c3f54a5a?auto=format&fit=crop&w=1200&q=80",
        alt: "A quiet library aisle with tall shelves and warm overhead light",
        source: "simulated stock photography"
      },
      story: {
        eyebrow: "Editorial media frame",
        title: "Public composites keep the institution in view.",
        body: "This static twin exercises the public media, story, program, admissions, and announcement primitives without calling domain APIs.",
        image: {
          src: "https://images.unsplash.com/photo-1517486808906-6ca8b3f04846?auto=format&fit=crop&w=1000&q=80",
          alt: "A group of students smiling together outdoors",
          source: "simulated stock photography"
        },
        align: "left"
      },
      programs: [
        {
          code: "BSN",
          name: "Bachelor of Science in Nursing",
          discipline: "Health and human care",
          summary: "Static preview catalog data."
        },
        {
          code: "BSPSY",
          name: "Bachelor of Science in Psychology",
          discipline: "Mind and community",
          summary: "Static preview catalog data."
        }
      ],
      announcements: [
        {
          eyebrow: "Preview",
          title: "Replace with approved campus notices.",
          body: "The empty state remains available when no notices are published.",
          href: "#public-foundation",
          label: "Inspect primitive"
        }
      ]
    };
    let dialogOpen = false;
    let commandDialogOpen = false;
    let commandLoading = false;
    const commandItems = [
      "Applications",
      "Exam scheduling",
      "Grading",
      "Reports",
      "Setup"
    ];
    function simulateCommandLoading() {
      commandLoading = true;
      setTimeout(() => commandLoading = false, 1200);
    }
    let uploadFiles = [];
    let uploadError = "";
    let viewMode = "responsive";
    const listRows = [
      { id: 1, label: "Room 101 — Main building" },
      { id: 2, label: "Room 204 — Annex" },
      { id: 3, label: "Room 305 — Science wing" }
    ];
    let demoPassword = "";
    let demoPasswordConfirmation = "";
    let mgmtSearch = "";
    let mgmtStatus = "";
    let mgmtSeason = "";
    let mgmtDateFrom = "";
    let mgmtDateTo = "";
    let mgmtViewMode = "responsive";
    let mgmtSelected = (
      /** @type {number[]} */
      [1, 3]
    );
    const mgmtStatusOptions = [
      { value: "", label: "All statuses" },
      { value: "pending", label: "Pending" },
      { value: "accepted", label: "Accepted" },
      { value: "dismissed", label: "Dismissed" }
    ];
    const mgmtSeasonOptions = [
      { value: "", label: "Active season" },
      { value: "2026", label: "AY 2026–2027" },
      { value: "2025", label: "AY 2025–2026" }
    ];
    const mgmtRows = [
      {
        id: 1,
        name: "A. Dela Cruz",
        email: "a.delacruz@example.test",
        status: "pending"
      },
      {
        id: 2,
        name: "B. Santos",
        email: "b.santos@example.test",
        status: "accepted"
      },
      {
        id: 3,
        name: "C. Reyes",
        email: "c.reyes@example.test",
        status: "pending"
      },
      {
        id: 4,
        name: "D. Lim",
        email: "d.lim@example.test",
        status: "dismissed"
      }
    ];
    const mgmtFiltered = derived(() => mgmtRows.filter((row) => {
      const q = mgmtSearch.trim().toLowerCase();
      if (q && !`${row.name} ${row.email}`.toLowerCase().includes(q)) return false;
      if (mgmtStatus && row.status !== mgmtStatus) return false;
      return true;
    }));
    const mgmtStatusLabel = derived(() => mgmtStatusOptions.find((o) => o.value === mgmtStatus)?.label ?? "All statuses");
    const mgmtSeasonLabel = derived(() => mgmtSeasonOptions.find((o) => o.value === mgmtSeason)?.label ?? "Active season");
    const mgmtChips = derived(() => [
      mgmtStatus ? { id: "status", label: `Status: ${mgmtStatusLabel()}` } : null,
      mgmtSeason ? { id: "season", label: `Season: ${mgmtSeasonLabel()}` } : null,
      mgmtDateFrom ? { id: "from", label: `From: ${mgmtDateFrom}` } : null,
      mgmtDateTo ? { id: "to", label: `To: ${mgmtDateTo}` } : null
    ].filter(Boolean));
    const mgmtAllSelected = derived(() => mgmtFiltered().length > 0 && mgmtFiltered().every((r) => mgmtSelected.includes(r.id)));
    const mgmtSomeSelected = derived(() => mgmtFiltered().some((r) => mgmtSelected.includes(r.id)) && !mgmtAllSelected());
    function mgmtToggleRow(id) {
      mgmtSelected = mgmtSelected.includes(id) ? mgmtSelected.filter((x) => x !== id) : [...mgmtSelected, id];
    }
    function mgmtToggleAll(checked) {
      const ids = mgmtFiltered().map((r) => r.id);
      mgmtSelected = checked ? [.../* @__PURE__ */ new Set([...mgmtSelected, ...ids])] : mgmtSelected.filter((id) => !ids.includes(id));
    }
    function mgmtClearFilters() {
      mgmtSearch = "";
      mgmtStatus = "";
      mgmtSeason = "";
      mgmtDateFrom = "";
      mgmtDateTo = "";
    }
    function mgmtRemoveChip(id) {
      if (id === "status") mgmtStatus = "";
      if (id === "season") mgmtSeason = "";
      if (id === "from") mgmtDateFrom = "";
      if (id === "to") mgmtDateTo = "";
    }
    function mgmtStatusVariant(status) {
      if (status === "accepted") return "success";
      if (status === "dismissed") return "danger";
      if (status === "pending") return "warning";
      return "muted";
    }
    const actionItems = [
      {
        label: "View",
        icon: Eye,
        onclick: () => info("Demo: view action clicked.")
      },
      {
        label: "Edit",
        icon: Pencil,
        onclick: () => info("Demo: edit action clicked.")
      },
      {
        label: "Delete",
        icon: Trash_2,
        danger: true,
        method: "delete",
        onclick: () => error("Demo: delete action confirmed (no data was changed).")
      }
    ];
    let $$settled = true;
    let $$inner_renderer;
    function $$render_inner($$renderer3) {
      head("1j1ufz2", $$renderer3, ($$renderer4) => {
        $$renderer4.title(($$renderer5) => {
          $$renderer5.push(`<title>UI kit gallery</title>`);
        });
      });
      AuthenticatedLayout($$renderer3, {
        breadcrumbs,
        children: ($$renderer4) => {
          $$renderer4.push(`<div class="mx-auto max-w-6xl space-y-10"><div><div class="flex flex-wrap items-center gap-3"><h1 class="text-foreground text-3xl font-semibold tracking-tight">UI kit gallery</h1> `);
          Badge($$renderer4, {
            variant: "muted",
            children: ($$renderer5) => {
              $$renderer5.push(`<!---->Presentational only`);
            },
            $$slots: { default: true }
          });
          $$renderer4.push(`<!----> `);
          Badge($$renderer4, {
            variant: "outline",
            children: ($$renderer5) => {
              $$renderer5.push(`<!---->Synthetic data`);
            },
            $$slots: { default: true }
          });
          $$renderer4.push(`<!----> `);
          Badge($$renderer4, {
            variant: "success",
            children: ($$renderer5) => {
              $$renderer5.push(`<!---->Locked start set`);
            },
            $$slots: { default: true }
          });
          $$renderer4.push(`<!----></div> <p class="text-muted-foreground mt-2 max-w-3xl">Full staff UI suite: locked tokens (IBM Plex Sans, radius
                0.5rem, layered paper + glass), every ported <code>Components/ui/*</code> primitive and <code>Components/kit/*</code> composite, responsive chrome
                notes, and a scoped dark-mode panel. Synthetic data only — no
                domain APIs. Authority: <code>docs/greenfield-foundation/evidence/ui-kit-locked-start-set.md</code>. Use the header theme toggle for full-app dark; use the Dark
                mode section for side-by-side referral without leaving the page.</p></div> <nav aria-label="Gallery sections" class="border-border bg-card rounded-lg border p-4"><ul class="flex flex-wrap gap-2 text-sm"><!--[-->`);
          const each_array = ensure_array_like(sections);
          for (let $$index = 0, $$length = each_array.length; $$index < $$length; $$index++) {
            let section = each_array[$$index];
            $$renderer4.push(`<li><a${attr("href", `#${stringify(section.id)}`)} class="border-border/60 text-muted-foreground hover:border-primary/40 hover:text-foreground inline-flex rounded-md border px-2.5 py-1 transition-colors">${escape_html(section.label)}</a></li>`);
          }
          $$renderer4.push(`<!--]--></ul></nav> <section id="home-sample" class="space-y-4"><div><h2 class="text-foreground text-xl font-semibold">Homepage sample</h2> <p class="text-muted-foreground mt-1 max-w-3xl text-sm">Rebuilt marketing surface (not a port of the weak legacy
                    Home blocks). Uses Motion.dev <code>inView</code> scroll reveals so we can judge real section
                    motion before journey pages ship.</p></div> `);
          MarketingHomePreview($$renderer4);
          $$renderer4.push(`<!----></section> <section id="public-foundation" class="space-y-6"><div><h2 class="text-foreground text-xl font-semibold">Public foundation</h2> <p class="text-muted-foreground mt-1 max-w-3xl text-sm">Static reference states for the institution-first public
                    shell. Media is deterministic simulated content;
                    reduced-motion and fallback states are part of the component
                    contract.</p></div> <div class="grid gap-6 lg:grid-cols-[0.8fr_1.2fr]">`);
          MediaFrame($$renderer4, {
            image: publicPreview.hero,
            ratio: "aspect-[4/3]",
            priority: true,
            showSource: true
          });
          $$renderer4.push(`<!----> `);
          StorySection($$renderer4, { story: publicPreview.story, preview: true });
          $$renderer4.push(`<!----></div> `);
          ProgramExplorer($$renderer4, { programs: publicPreview.programs, preview: true });
          $$renderer4.push(`<!----> `);
          AdmissionsGateway($$renderer4, { activeSeason: { academic_year: "2026–2027", is_open: true } });
          $$renderer4.push(`<!----> `);
          AnnouncementRail($$renderer4, { announcements: publicPreview.announcements });
          $$renderer4.push(`<!----></section> <section id="xiam-sample" class="space-y-4"><div class="space-y-1"><div class="flex flex-wrap items-center gap-2"><h2 class="text-foreground text-xl font-semibold">Xiam Assistant &amp; Office Governance Lab</h2> `);
          Badge($$renderer4, {
            variant: "outline",
            class: "border-primary/40 text-primary text-xs",
            children: ($$renderer5) => {
              $$renderer5.push(`<!---->Delivered Cuts 0–6 Live Dock`);
            },
            $$slots: { default: true }
          });
          $$renderer4.push(`<!----> `);
          Badge($$renderer4, {
            variant: "outline",
            class: "border-amber-500/40 text-amber-600 dark:text-amber-400 text-xs",
            children: ($$renderer5) => {
              $$renderer5.push(`<!---->Planned Cuts 8–10 Staff Lab`);
            },
            $$slots: { default: true }
          });
          $$renderer4.push(`<!----></div> <p class="text-muted-foreground max-w-3xl text-sm">Xiam operates as an auxiliary assistant across staff desks and applicant surfaces. Operational controls adhere to strict authority boundaries: office policy can narrow channel availability, but cannot widen domain capabilities or bypass institutional gates.</p></div> <div class="border-border/50 bg-muted/20 text-muted-foreground rounded-xl border p-4 text-sm"><p>Test the live runtime helper at the bottom-right floating button, or use the simulated controls below to preview upcoming office channel, usage, quota, and knowledge governance settings.</p></div> `);
          XiamWalkthroughPreview($$renderer4);
          $$renderer4.push(`<!----></section> <section id="import-engine" class="space-y-4"><div class="space-y-1"><h2 class="text-foreground text-xl font-semibold">Dynamic Ingestion &amp; Import Pipeline</h2> <p class="text-muted-foreground text-sm">Interactive prototype: multi-sheet workbook inspector, pre-upload health checks, column mapping, and single-pass All-in-One intake.</p></div> `);
          ImportEnginePreview($$renderer4);
          $$renderer4.push(`<!----></section> <section id="locked-set" class="space-y-4"><h2 class="text-foreground text-xl font-semibold">Locked start set</h2> `);
          if (Card) {
            $$renderer4.push("<!--[-->");
            Card($$renderer4, {
              variant: "glass",
              children: ($$renderer5) => {
                if (Card_header) {
                  $$renderer5.push("<!--[-->");
                  Card_header($$renderer5, {
                    children: ($$renderer6) => {
                      if (Card_title) {
                        $$renderer6.push("<!--[-->");
                        Card_title($$renderer6, {
                          children: ($$renderer7) => {
                            $$renderer7.push(`<!---->Institutional staff chrome`);
                          },
                          $$slots: { default: true }
                        });
                        $$renderer6.push("<!--]-->");
                      } else {
                        $$renderer6.push("<!--[!-->");
                        $$renderer6.push("<!--]-->");
                      }
                      $$renderer6.push(` `);
                      if (Card_description) {
                        $$renderer6.push("<!--[-->");
                        Card_description($$renderer6, {
                          children: ($$renderer7) => {
                            $$renderer7.push(`<!---->Trust-first dials 3 / 2 / 5. Change only via founder
                        decision + this gallery + app.css together.`);
                          },
                          $$slots: { default: true }
                        });
                        $$renderer6.push("<!--]-->");
                      } else {
                        $$renderer6.push("<!--[!-->");
                        $$renderer6.push("<!--]-->");
                      }
                    },
                    $$slots: { default: true }
                  });
                  $$renderer5.push("<!--]-->");
                } else {
                  $$renderer5.push("<!--[!-->");
                  $$renderer5.push("<!--]-->");
                }
                $$renderer5.push(` `);
                if (Card_content) {
                  $$renderer5.push("<!--[-->");
                  Card_content($$renderer5, {
                    children: ($$renderer6) => {
                      $$renderer6.push(`<dl class="grid gap-3 text-sm sm:grid-cols-2"><div><dt class="text-foreground font-medium">Sans</dt> <dd class="text-muted-foreground">IBM Plex Sans</dd></div> <div><dt class="text-foreground font-medium">Mono</dt> <dd class="text-muted-foreground">IBM Plex Mono (code only)</dd></div> <div><dt class="text-foreground font-medium">Radius</dt> <dd class="text-muted-foreground">0.5rem (+ sm/md/lg/xl scale)</dd></div> <div><dt class="text-foreground font-medium">Background</dt> <dd class="text-muted-foreground">Layered paper — forest wash + warm bone</dd></div> <div><dt class="text-foreground font-medium">Surfaces</dt> <dd class="text-muted-foreground">glass-panel + solid card</dd></div> <div><dt class="text-foreground font-medium">Rejected</dt> <dd class="text-muted-foreground">Montserrat, radius 0, meta banners above header</dd></div></dl>`);
                    },
                    $$slots: { default: true }
                  });
                  $$renderer5.push("<!--]-->");
                } else {
                  $$renderer5.push("<!--[!-->");
                  $$renderer5.push("<!--]-->");
                }
              },
              $$slots: { default: true }
            });
            $$renderer4.push("<!--]-->");
          } else {
            $$renderer4.push("<!--[!-->");
            $$renderer4.push("<!--]-->");
          }
          $$renderer4.push(`</section> <section id="tokens" class="space-y-4"><h2 class="text-foreground text-xl font-semibold">Color tokens</h2> `);
          if (Card) {
            $$renderer4.push("<!--[-->");
            Card($$renderer4, {
              children: ($$renderer5) => {
                if (Card_content) {
                  $$renderer5.push("<!--[-->");
                  Card_content($$renderer5, {
                    children: ($$renderer6) => {
                      $$renderer6.push(`<div class="grid grid-cols-2 gap-3 sm:grid-cols-3 md:grid-cols-4 lg:grid-cols-6"><!--[-->`);
                      const each_array_1 = ensure_array_like(colorTokens);
                      for (let $$index_1 = 0, $$length = each_array_1.length; $$index_1 < $$length; $$index_1++) {
                        let token = each_array_1[$$index_1];
                        $$renderer6.push(`<div class="space-y-2"><div${attr_class(`h-14 w-full rounded-lg ${stringify(token.className)}`)} aria-hidden="true"></div> <p class="text-muted-foreground font-mono text-xs">${escape_html(token.name)}</p></div>`);
                      }
                      $$renderer6.push(`<!--]--></div>`);
                    },
                    $$slots: { default: true }
                  });
                  $$renderer5.push("<!--]-->");
                } else {
                  $$renderer5.push("<!--[!-->");
                  $$renderer5.push("<!--]-->");
                }
              },
              $$slots: { default: true }
            });
            $$renderer4.push("<!--]-->");
          } else {
            $$renderer4.push("<!--[!-->");
            $$renderer4.push("<!--]-->");
          }
          $$renderer4.push(`</section> <section id="typography" class="space-y-4"><h2 class="text-foreground text-xl font-semibold">Typography</h2> `);
          if (Card) {
            $$renderer4.push("<!--[-->");
            Card($$renderer4, {
              children: ($$renderer5) => {
                if (Card_content) {
                  $$renderer5.push("<!--[-->");
                  Card_content($$renderer5, {
                    class: "space-y-4",
                    children: ($$renderer6) => {
                      $$renderer6.push(`<p class="text-4xl font-semibold tracking-tight">Display / page title</p> <p class="text-2xl font-semibold tracking-tight">Section heading</p> <p class="text-xl font-semibold">Card title</p> <p class="text-foreground text-base">Body — IBM Plex Sans at comfortable reading size for
                        staff forms and tables.</p> <p class="text-muted-foreground text-sm">Muted helper / description copy. Keep line length
                        readable on wide layouts.</p> <p class="text-muted-foreground text-xs font-semibold tracking-[0.2em] uppercase">Eyebrow / brand lockup</p> <p class="font-mono text-sm">Mono tabular: APP-2026-0042 · never for chrome captions</p>`);
                    },
                    $$slots: { default: true }
                  });
                  $$renderer5.push("<!--]-->");
                } else {
                  $$renderer5.push("<!--[!-->");
                  $$renderer5.push("<!--]-->");
                }
              },
              $$slots: { default: true }
            });
            $$renderer4.push("<!--]-->");
          } else {
            $$renderer4.push("<!--[!-->");
            $$renderer4.push("<!--]-->");
          }
          $$renderer4.push(`</section> <section id="radius-surfaces" class="space-y-4"><h2 class="text-foreground text-xl font-semibold">Radius &amp; surfaces</h2> <div class="grid gap-4 md:grid-cols-2">`);
          if (Card) {
            $$renderer4.push("<!--[-->");
            Card($$renderer4, {
              children: ($$renderer5) => {
                if (Card_header) {
                  $$renderer5.push("<!--[-->");
                  Card_header($$renderer5, {
                    children: ($$renderer6) => {
                      if (Card_title) {
                        $$renderer6.push("<!--[-->");
                        Card_title($$renderer6, {
                          children: ($$renderer7) => {
                            $$renderer7.push(`<!---->Solid card`);
                          },
                          $$slots: { default: true }
                        });
                        $$renderer6.push("<!--]-->");
                      } else {
                        $$renderer6.push("<!--[!-->");
                        $$renderer6.push("<!--]-->");
                      }
                      $$renderer6.push(` `);
                      if (Card_description) {
                        $$renderer6.push("<!--[-->");
                        Card_description($$renderer6, {
                          children: ($$renderer7) => {
                            $$renderer7.push(`<!---->Default opaque surface for dense content.`);
                          },
                          $$slots: { default: true }
                        });
                        $$renderer6.push("<!--]-->");
                      } else {
                        $$renderer6.push("<!--[!-->");
                        $$renderer6.push("<!--]-->");
                      }
                    },
                    $$slots: { default: true }
                  });
                  $$renderer5.push("<!--]-->");
                } else {
                  $$renderer5.push("<!--[!-->");
                  $$renderer5.push("<!--]-->");
                }
                $$renderer5.push(` `);
                if (Card_content) {
                  $$renderer5.push("<!--[-->");
                  Card_content($$renderer5, {
                    class: "flex flex-wrap gap-3",
                    children: ($$renderer6) => {
                      $$renderer6.push(`<div class="border-border bg-muted flex size-16 items-end rounded-sm border p-1 text-[10px]">sm</div> <div class="border-border bg-muted flex size-16 items-end rounded-md border p-1 text-[10px]">md</div> <div class="border-border bg-muted flex size-16 items-end rounded-lg border p-1 text-[10px]">lg</div> <div class="border-border bg-muted flex size-16 items-end rounded-xl border p-1 text-[10px]">xl</div>`);
                    },
                    $$slots: { default: true }
                  });
                  $$renderer5.push("<!--]-->");
                } else {
                  $$renderer5.push("<!--[!-->");
                  $$renderer5.push("<!--]-->");
                }
              },
              $$slots: { default: true }
            });
            $$renderer4.push("<!--]-->");
          } else {
            $$renderer4.push("<!--[!-->");
            $$renderer4.push("<!--]-->");
          }
          $$renderer4.push(` `);
          if (Card) {
            $$renderer4.push("<!--[-->");
            Card($$renderer4, {
              variant: "glass",
              children: ($$renderer5) => {
                if (Card_header) {
                  $$renderer5.push("<!--[-->");
                  Card_header($$renderer5, {
                    children: ($$renderer6) => {
                      if (Card_title) {
                        $$renderer6.push("<!--[-->");
                        Card_title($$renderer6, {
                          children: ($$renderer7) => {
                            $$renderer7.push(`<!---->Glass panel`);
                          },
                          $$slots: { default: true }
                        });
                        $$renderer6.push("<!--]-->");
                      } else {
                        $$renderer6.push("<!--[!-->");
                        $$renderer6.push("<!--]-->");
                      }
                      $$renderer6.push(` `);
                      if (Card_description) {
                        $$renderer6.push("<!--[-->");
                        Card_description($$renderer6, {
                          children: ($$renderer7) => {
                            $$renderer7.push(`<!---->Sidebar, header, and elevated overlays — paper
                            shows through.`);
                          },
                          $$slots: { default: true }
                        });
                        $$renderer6.push("<!--]-->");
                      } else {
                        $$renderer6.push("<!--[!-->");
                        $$renderer6.push("<!--]-->");
                      }
                    },
                    $$slots: { default: true }
                  });
                  $$renderer5.push("<!--]-->");
                } else {
                  $$renderer5.push("<!--[!-->");
                  $$renderer5.push("<!--]-->");
                }
                $$renderer5.push(` `);
                if (Card_content) {
                  $$renderer5.push("<!--[-->");
                  Card_content($$renderer5, {
                    children: ($$renderer6) => {
                      $$renderer6.push(`<p class="text-muted-foreground text-sm">Uses <code>.glass-panel</code> (blur + inset
                            highlight). Solid fallback when <code>prefers-reduced-transparency</code> is set.</p>`);
                    },
                    $$slots: { default: true }
                  });
                  $$renderer5.push("<!--]-->");
                } else {
                  $$renderer5.push("<!--[!-->");
                  $$renderer5.push("<!--]-->");
                }
              },
              $$slots: { default: true }
            });
            $$renderer4.push("<!--]-->");
          } else {
            $$renderer4.push("<!--[!-->");
            $$renderer4.push("<!--]-->");
          }
          $$renderer4.push(`</div></section> <section id="layout-chrome" class="space-y-4"><h2 class="text-foreground text-xl font-semibold">Layout chrome (responsive)</h2> `);
          if (Card) {
            $$renderer4.push("<!--[-->");
            Card($$renderer4, {
              children: ($$renderer5) => {
                if (Card_content) {
                  $$renderer5.push("<!--[-->");
                  Card_content($$renderer5, {
                    class: "text-muted-foreground space-y-3 text-sm",
                    children: ($$renderer6) => {
                      $$renderer6.push(`<p>This page already sits inside <code>AuthenticatedLayout</code>. Header is flush top — never place design meta banners
                        above it. Breakpoints match legacy:</p> <ul class="list-disc space-y-1 pl-5"><li><strong class="text-foreground">&lt; md</strong> — sidebar
                            off-canvas; hamburger + backdrop</li> <li><strong class="text-foreground">md+</strong> — fixed <code>w-64</code> sidebar; main <code>md:pl-64</code></li> <li><strong class="text-foreground">Header</strong> — <code>h-20</code> glass; breadcrumbs hidden on small screens</li> <li><strong class="text-foreground">Main</strong> — <code>p-4 lg:p-8</code> scroll region</li></ul> <p>Resize the browser below the <code>md</code> breakpoint
                        and use the menu button to verify drawer behavior.
                        Static twins: <code>docs/greenfield-foundation/evidence/ui-kit-reference/</code>.</p>`);
                    },
                    $$slots: { default: true }
                  });
                  $$renderer5.push("<!--]-->");
                } else {
                  $$renderer5.push("<!--[!-->");
                  $$renderer5.push("<!--]-->");
                }
              },
              $$slots: { default: true }
            });
            $$renderer4.push("<!--]-->");
          } else {
            $$renderer4.push("<!--[!-->");
            $$renderer4.push("<!--]-->");
          }
          $$renderer4.push(`</section> <section id="dark-mode" class="space-y-4"><h2 class="text-foreground text-xl font-semibold">Dark mode (scoped referral)</h2> <p class="text-muted-foreground text-sm">Nested <code>.dark</code> island — does not flip the whole app. For
                full chrome dark, use the sun/moon control in the header.</p> <div class="dark border-border overflow-hidden rounded-xl border"><div class="bg-background text-foreground space-y-4 p-4 sm:p-6"><div class="flex flex-wrap items-center gap-2">`);
          Badge($$renderer4, {
            children: ($$renderer5) => {
              $$renderer5.push(`<!---->Default`);
            },
            $$slots: { default: true }
          });
          $$renderer4.push(`<!----> `);
          Badge($$renderer4, {
            variant: "success",
            children: ($$renderer5) => {
              $$renderer5.push(`<!---->Success`);
            },
            $$slots: { default: true }
          });
          $$renderer4.push(`<!----> `);
          Badge($$renderer4, {
            variant: "warning",
            children: ($$renderer5) => {
              $$renderer5.push(`<!---->Warning`);
            },
            $$slots: { default: true }
          });
          $$renderer4.push(`<!----> `);
          Badge($$renderer4, {
            variant: "muted",
            children: ($$renderer5) => {
              $$renderer5.push(`<!---->Muted`);
            },
            $$slots: { default: true }
          });
          $$renderer4.push(`<!----></div> <div class="flex flex-wrap gap-2">`);
          Button($$renderer4, {
            children: ($$renderer5) => {
              $$renderer5.push(`<!---->Primary`);
            },
            $$slots: { default: true }
          });
          $$renderer4.push(`<!----> `);
          Button($$renderer4, {
            variant: "outline",
            children: ($$renderer5) => {
              $$renderer5.push(`<!---->Outline`);
            },
            $$slots: { default: true }
          });
          $$renderer4.push(`<!----> `);
          Button($$renderer4, {
            variant: "secondary",
            children: ($$renderer5) => {
              $$renderer5.push(`<!---->Secondary`);
            },
            $$slots: { default: true }
          });
          $$renderer4.push(`<!----> `);
          Button($$renderer4, {
            variant: "destructive",
            children: ($$renderer5) => {
              $$renderer5.push(`<!---->Destructive`);
            },
            $$slots: { default: true }
          });
          $$renderer4.push(`<!----></div> `);
          if (Card) {
            $$renderer4.push("<!--[-->");
            Card($$renderer4, {
              variant: "glass",
              children: ($$renderer5) => {
                if (Card_header) {
                  $$renderer5.push("<!--[-->");
                  Card_header($$renderer5, {
                    children: ($$renderer6) => {
                      if (Card_title) {
                        $$renderer6.push("<!--[-->");
                        Card_title($$renderer6, {
                          children: ($$renderer7) => {
                            $$renderer7.push(`<!---->Dark glass card`);
                          },
                          $$slots: { default: true }
                        });
                        $$renderer6.push("<!--]-->");
                      } else {
                        $$renderer6.push("<!--[!-->");
                        $$renderer6.push("<!--]-->");
                      }
                      $$renderer6.push(` `);
                      if (Card_description) {
                        $$renderer6.push("<!--[-->");
                        Card_description($$renderer6, {
                          children: ($$renderer7) => {
                            $$renderer7.push(`<!---->Same structure as light — tokens swap under
                                .dark.`);
                          },
                          $$slots: { default: true }
                        });
                        $$renderer6.push("<!--]-->");
                      } else {
                        $$renderer6.push("<!--[!-->");
                        $$renderer6.push("<!--]-->");
                      }
                    },
                    $$slots: { default: true }
                  });
                  $$renderer5.push("<!--]-->");
                } else {
                  $$renderer5.push("<!--[!-->");
                  $$renderer5.push("<!--]-->");
                }
                $$renderer5.push(` `);
                if (Card_content) {
                  $$renderer5.push("<!--[-->");
                  Card_content($$renderer5, {
                    class: "grid gap-3 sm:grid-cols-2",
                    children: ($$renderer6) => {
                      $$renderer6.push(`<div class="space-y-2">`);
                      Label($$renderer6, {
                        for: "dark-demo-input",
                        children: ($$renderer7) => {
                          $$renderer7.push(`<!---->Email`);
                        },
                        $$slots: { default: true }
                      });
                      $$renderer6.push(`<!----> `);
                      Input($$renderer6, { id: "dark-demo-input", value: "registrar.clerk@nexiam.test" });
                      $$renderer6.push(`<!----></div> <div class="space-y-2">`);
                      Label($$renderer6, {
                        for: "dark-demo-select",
                        children: ($$renderer7) => {
                          $$renderer7.push(`<!---->Office`);
                        },
                        $$slots: { default: true }
                      });
                      $$renderer6.push(`<!----> `);
                      if (Select) {
                        $$renderer6.push("<!--[-->");
                        Select($$renderer6, {
                          type: "single",
                          value: "registrar",
                          children: ($$renderer7) => {
                            if (Select_trigger) {
                              $$renderer7.push("<!--[-->");
                              Select_trigger($$renderer7, {
                                class: "w-full",
                                children: ($$renderer8) => {
                                  $$renderer8.push(`<!---->Registrar`);
                                },
                                $$slots: { default: true }
                              });
                              $$renderer7.push("<!--]-->");
                            } else {
                              $$renderer7.push("<!--[!-->");
                              $$renderer7.push("<!--]-->");
                            }
                            $$renderer7.push(` `);
                            if (Select_content) {
                              $$renderer7.push("<!--[-->");
                              Select_content($$renderer7, {
                                children: ($$renderer8) => {
                                  if (Select_item) {
                                    $$renderer8.push("<!--[-->");
                                    Select_item($$renderer8, { value: "registrar", label: "Registrar" });
                                    $$renderer8.push("<!--]-->");
                                  } else {
                                    $$renderer8.push("<!--[!-->");
                                    $$renderer8.push("<!--]-->");
                                  }
                                  $$renderer8.push(` `);
                                  if (Select_item) {
                                    $$renderer8.push("<!--[-->");
                                    Select_item($$renderer8, { value: "proctor", label: "Proctor" });
                                    $$renderer8.push("<!--]-->");
                                  } else {
                                    $$renderer8.push("<!--[!-->");
                                    $$renderer8.push("<!--]-->");
                                  }
                                },
                                $$slots: { default: true }
                              });
                              $$renderer7.push("<!--]-->");
                            } else {
                              $$renderer7.push("<!--[!-->");
                              $$renderer7.push("<!--]-->");
                            }
                          },
                          $$slots: { default: true }
                        });
                        $$renderer6.push("<!--]-->");
                      } else {
                        $$renderer6.push("<!--[!-->");
                        $$renderer6.push("<!--]-->");
                      }
                      $$renderer6.push(`</div>`);
                    },
                    $$slots: { default: true }
                  });
                  $$renderer5.push("<!--]-->");
                } else {
                  $$renderer5.push("<!--[!-->");
                  $$renderer5.push("<!--]-->");
                }
              },
              $$slots: { default: true }
            });
            $$renderer4.push("<!--]-->");
          } else {
            $$renderer4.push("<!--[!-->");
            $$renderer4.push("<!--]-->");
          }
          $$renderer4.push(` <div class="grid gap-3 sm:grid-cols-3">`);
          StatCard($$renderer4, { label: "Open apps", value: "128", icon: Folder_open });
          $$renderer4.push(`<!----> `);
          StatCard($$renderer4, { label: "Scheduled", value: "42", icon: Clipboard_check });
          $$renderer4.push(`<!----> `);
          StatCard($$renderer4, { label: "Trend", value: "+12%", icon: Trending_up });
          $$renderer4.push(`<!----></div></div></div></section> <section id="states" class="space-y-4"><h2 class="text-foreground text-xl font-semibold">Page states (patterns)</h2> <div class="grid gap-4 md:grid-cols-2">`);
          if (Card) {
            $$renderer4.push("<!--[-->");
            Card($$renderer4, {
              children: ($$renderer5) => {
                if (Card_header) {
                  $$renderer5.push("<!--[-->");
                  Card_header($$renderer5, {
                    children: ($$renderer6) => {
                      if (Card_title) {
                        $$renderer6.push("<!--[-->");
                        Card_title($$renderer6, {
                          children: ($$renderer7) => {
                            $$renderer7.push(`<!---->Empty`);
                          },
                          $$slots: { default: true }
                        });
                        $$renderer6.push("<!--]-->");
                      } else {
                        $$renderer6.push("<!--[!-->");
                        $$renderer6.push("<!--]-->");
                      }
                      $$renderer6.push(` `);
                      if (Card_description) {
                        $$renderer6.push("<!--[-->");
                        Card_description($$renderer6, {
                          children: ($$renderer7) => {
                            $$renderer7.push(`<!---->No rows yet — keep actions obvious.`);
                          },
                          $$slots: { default: true }
                        });
                        $$renderer6.push("<!--]-->");
                      } else {
                        $$renderer6.push("<!--[!-->");
                        $$renderer6.push("<!--]-->");
                      }
                    },
                    $$slots: { default: true }
                  });
                  $$renderer5.push("<!--]-->");
                } else {
                  $$renderer5.push("<!--[!-->");
                  $$renderer5.push("<!--]-->");
                }
                $$renderer5.push(` `);
                if (Card_content) {
                  $$renderer5.push("<!--[-->");
                  Card_content($$renderer5, {
                    class: "text-muted-foreground py-8 text-center text-sm",
                    children: ($$renderer6) => {
                      $$renderer6.push(`<!---->No applications match these filters. <div class="mt-4">`);
                      Button($$renderer6, {
                        variant: "outline",
                        size: "sm",
                        children: ($$renderer7) => {
                          $$renderer7.push(`<!---->Clear filters`);
                        },
                        $$slots: { default: true }
                      });
                      $$renderer6.push(`<!----></div>`);
                    },
                    $$slots: { default: true }
                  });
                  $$renderer5.push("<!--]-->");
                } else {
                  $$renderer5.push("<!--[!-->");
                  $$renderer5.push("<!--]-->");
                }
              },
              $$slots: { default: true }
            });
            $$renderer4.push("<!--]-->");
          } else {
            $$renderer4.push("<!--[!-->");
            $$renderer4.push("<!--]-->");
          }
          $$renderer4.push(` `);
          if (Card) {
            $$renderer4.push("<!--[-->");
            Card($$renderer4, {
              children: ($$renderer5) => {
                if (Card_header) {
                  $$renderer5.push("<!--[-->");
                  Card_header($$renderer5, {
                    children: ($$renderer6) => {
                      if (Card_title) {
                        $$renderer6.push("<!--[-->");
                        Card_title($$renderer6, {
                          children: ($$renderer7) => {
                            $$renderer7.push(`<!---->Error / denied`);
                          },
                          $$slots: { default: true }
                        });
                        $$renderer6.push("<!--]-->");
                      } else {
                        $$renderer6.push("<!--[!-->");
                        $$renderer6.push("<!--]-->");
                      }
                      $$renderer6.push(` `);
                      if (Card_description) {
                        $$renderer6.push("<!--[-->");
                        Card_description($$renderer6, {
                          children: ($$renderer7) => {
                            $$renderer7.push(`<!---->Visible failure — never silent omit.`);
                          },
                          $$slots: { default: true }
                        });
                        $$renderer6.push("<!--]-->");
                      } else {
                        $$renderer6.push("<!--[!-->");
                        $$renderer6.push("<!--]-->");
                      }
                    },
                    $$slots: { default: true }
                  });
                  $$renderer5.push("<!--]-->");
                } else {
                  $$renderer5.push("<!--[!-->");
                  $$renderer5.push("<!--]-->");
                }
                $$renderer5.push(` `);
                if (Card_content) {
                  $$renderer5.push("<!--[-->");
                  Card_content($$renderer5, {
                    class: "space-y-2 text-sm",
                    children: ($$renderer6) => {
                      $$renderer6.push(`<p class="text-destructive font-medium">You do not have capability to view this list.</p> <p class="text-muted-foreground">GF-21 denial is authoritative; UI only reflects it.</p>`);
                    },
                    $$slots: { default: true }
                  });
                  $$renderer5.push("<!--]-->");
                } else {
                  $$renderer5.push("<!--[!-->");
                  $$renderer5.push("<!--]-->");
                }
              },
              $$slots: { default: true }
            });
            $$renderer4.push("<!--]-->");
          } else {
            $$renderer4.push("<!--[!-->");
            $$renderer4.push("<!--]-->");
          }
          $$renderer4.push(` `);
          if (Card) {
            $$renderer4.push("<!--[-->");
            Card($$renderer4, {
              children: ($$renderer5) => {
                if (Card_header) {
                  $$renderer5.push("<!--[-->");
                  Card_header($$renderer5, {
                    children: ($$renderer6) => {
                      if (Card_title) {
                        $$renderer6.push("<!--[-->");
                        Card_title($$renderer6, {
                          children: ($$renderer7) => {
                            $$renderer7.push(`<!---->Loading`);
                          },
                          $$slots: { default: true }
                        });
                        $$renderer6.push("<!--]-->");
                      } else {
                        $$renderer6.push("<!--[!-->");
                        $$renderer6.push("<!--]-->");
                      }
                      $$renderer6.push(` `);
                      if (Card_description) {
                        $$renderer6.push("<!--[-->");
                        Card_description($$renderer6, {
                          children: ($$renderer7) => {
                            $$renderer7.push(`<!---->Prefer skeletons over blank flash.`);
                          },
                          $$slots: { default: true }
                        });
                        $$renderer6.push("<!--]-->");
                      } else {
                        $$renderer6.push("<!--[!-->");
                        $$renderer6.push("<!--]-->");
                      }
                    },
                    $$slots: { default: true }
                  });
                  $$renderer5.push("<!--]-->");
                } else {
                  $$renderer5.push("<!--[!-->");
                  $$renderer5.push("<!--]-->");
                }
                $$renderer5.push(` `);
                if (Card_content) {
                  $$renderer5.push("<!--[-->");
                  Card_content($$renderer5, {
                    class: "space-y-2",
                    children: ($$renderer6) => {
                      $$renderer6.push(`<div class="bg-muted h-4 w-2/3 animate-pulse rounded-md"></div> <div class="bg-muted h-4 w-1/2 animate-pulse rounded-md"></div> <div class="bg-muted h-24 animate-pulse rounded-lg"></div>`);
                    },
                    $$slots: { default: true }
                  });
                  $$renderer5.push("<!--]-->");
                } else {
                  $$renderer5.push("<!--[!-->");
                  $$renderer5.push("<!--]-->");
                }
              },
              $$slots: { default: true }
            });
            $$renderer4.push("<!--]-->");
          } else {
            $$renderer4.push("<!--[!-->");
            $$renderer4.push("<!--]-->");
          }
          $$renderer4.push(` `);
          if (Card) {
            $$renderer4.push("<!--[-->");
            Card($$renderer4, {
              children: ($$renderer5) => {
                if (Card_header) {
                  $$renderer5.push("<!--[-->");
                  Card_header($$renderer5, {
                    children: ($$renderer6) => {
                      if (Card_title) {
                        $$renderer6.push("<!--[-->");
                        Card_title($$renderer6, {
                          children: ($$renderer7) => {
                            $$renderer7.push(`<!---->Success / conflict`);
                          },
                          $$slots: { default: true }
                        });
                        $$renderer6.push("<!--]-->");
                      } else {
                        $$renderer6.push("<!--[!-->");
                        $$renderer6.push("<!--]-->");
                      }
                      $$renderer6.push(` `);
                      if (Card_description) {
                        $$renderer6.push("<!--[-->");
                        Card_description($$renderer6, {
                          children: ($$renderer7) => {
                            $$renderer7.push(`<!---->Toast + inline status where durable.`);
                          },
                          $$slots: { default: true }
                        });
                        $$renderer6.push("<!--]-->");
                      } else {
                        $$renderer6.push("<!--[!-->");
                        $$renderer6.push("<!--]-->");
                      }
                    },
                    $$slots: { default: true }
                  });
                  $$renderer5.push("<!--]-->");
                } else {
                  $$renderer5.push("<!--[!-->");
                  $$renderer5.push("<!--]-->");
                }
                $$renderer5.push(` `);
                if (Card_content) {
                  $$renderer5.push("<!--[-->");
                  Card_content($$renderer5, {
                    class: "flex flex-wrap gap-2",
                    children: ($$renderer6) => {
                      Button($$renderer6, {
                        size: "sm",
                        onclick: () => success("Saved (demo)."),
                        children: ($$renderer7) => {
                          $$renderer7.push(`<!---->Success toast`);
                        },
                        $$slots: { default: true }
                      });
                      $$renderer6.push(`<!----> `);
                      Button($$renderer6, {
                        size: "sm",
                        variant: "outline",
                        onclick: () => error("Conflict (demo)."),
                        children: ($$renderer7) => {
                          $$renderer7.push(`<!---->Conflict toast`);
                        },
                        $$slots: { default: true }
                      });
                      $$renderer6.push(`<!---->`);
                    },
                    $$slots: { default: true }
                  });
                  $$renderer5.push("<!--]-->");
                } else {
                  $$renderer5.push("<!--[!-->");
                  $$renderer5.push("<!--]-->");
                }
              },
              $$slots: { default: true }
            });
            $$renderer4.push("<!--]-->");
          } else {
            $$renderer4.push("<!--[!-->");
            $$renderer4.push("<!--]-->");
          }
          $$renderer4.push(`</div></section> <section id="buttons" class="space-y-4"><h2 class="text-foreground text-xl font-semibold">Button</h2> `);
          if (Card) {
            $$renderer4.push("<!--[-->");
            Card($$renderer4, {
              children: ($$renderer5) => {
                if (Card_content) {
                  $$renderer5.push("<!--[-->");
                  Card_content($$renderer5, {
                    class: "space-y-4",
                    children: ($$renderer6) => {
                      $$renderer6.push(`<div class="flex flex-wrap items-center gap-3">`);
                      Button($$renderer6, {
                        variant: "default",
                        children: ($$renderer7) => {
                          $$renderer7.push(`<!---->Default`);
                        },
                        $$slots: { default: true }
                      });
                      $$renderer6.push(`<!----> `);
                      Button($$renderer6, {
                        variant: "outline",
                        children: ($$renderer7) => {
                          $$renderer7.push(`<!---->Outline`);
                        },
                        $$slots: { default: true }
                      });
                      $$renderer6.push(`<!----> `);
                      Button($$renderer6, {
                        variant: "secondary",
                        children: ($$renderer7) => {
                          $$renderer7.push(`<!---->Secondary`);
                        },
                        $$slots: { default: true }
                      });
                      $$renderer6.push(`<!----> `);
                      Button($$renderer6, {
                        variant: "ghost",
                        children: ($$renderer7) => {
                          $$renderer7.push(`<!---->Ghost`);
                        },
                        $$slots: { default: true }
                      });
                      $$renderer6.push(`<!----> `);
                      Button($$renderer6, {
                        variant: "destructive",
                        children: ($$renderer7) => {
                          $$renderer7.push(`<!---->Destructive`);
                        },
                        $$slots: { default: true }
                      });
                      $$renderer6.push(`<!----> `);
                      Button($$renderer6, {
                        variant: "link",
                        children: ($$renderer7) => {
                          $$renderer7.push(`<!---->Link`);
                        },
                        $$slots: { default: true }
                      });
                      $$renderer6.push(`<!----></div> <div class="flex flex-wrap items-center gap-3">`);
                      Button($$renderer6, {
                        size: "xs",
                        children: ($$renderer7) => {
                          $$renderer7.push(`<!---->Extra small`);
                        },
                        $$slots: { default: true }
                      });
                      $$renderer6.push(`<!----> `);
                      Button($$renderer6, {
                        size: "sm",
                        children: ($$renderer7) => {
                          $$renderer7.push(`<!---->Small`);
                        },
                        $$slots: { default: true }
                      });
                      $$renderer6.push(`<!----> `);
                      Button($$renderer6, {
                        size: "default",
                        children: ($$renderer7) => {
                          $$renderer7.push(`<!---->Default size`);
                        },
                        $$slots: { default: true }
                      });
                      $$renderer6.push(`<!----> `);
                      Button($$renderer6, {
                        size: "lg",
                        children: ($$renderer7) => {
                          $$renderer7.push(`<!---->Large`);
                        },
                        $$slots: { default: true }
                      });
                      $$renderer6.push(`<!----> `);
                      Button($$renderer6, {
                        size: "icon-xs",
                        "aria-label": "Settings xs",
                        children: ($$renderer7) => {
                          Settings($$renderer7, {});
                        },
                        $$slots: { default: true }
                      });
                      $$renderer6.push(`<!----> `);
                      Button($$renderer6, {
                        size: "icon-sm",
                        "aria-label": "Settings sm",
                        children: ($$renderer7) => {
                          Settings($$renderer7, {});
                        },
                        $$slots: { default: true }
                      });
                      $$renderer6.push(`<!----> `);
                      Button($$renderer6, {
                        size: "icon",
                        "aria-label": "Settings",
                        children: ($$renderer7) => {
                          Settings($$renderer7, {});
                        },
                        $$slots: { default: true }
                      });
                      $$renderer6.push(`<!----> `);
                      Button($$renderer6, {
                        size: "icon-lg",
                        "aria-label": "Settings lg",
                        children: ($$renderer7) => {
                          Settings($$renderer7, {});
                        },
                        $$slots: { default: true }
                      });
                      $$renderer6.push(`<!----></div> <div class="flex flex-wrap items-center gap-3">`);
                      Button($$renderer6, {
                        variant: "outline",
                        children: ($$renderer7) => {
                          Upload($$renderer7, { "data-icon": "inline-start" });
                          $$renderer7.push(`<!----> Upload`);
                        },
                        $$slots: { default: true }
                      });
                      $$renderer6.push(`<!----> `);
                      Button($$renderer6, {
                        variant: "secondary",
                        children: ($$renderer7) => {
                          $$renderer7.push(`<!---->Continue `);
                          Users($$renderer7, { "data-icon": "inline-end" });
                          $$renderer7.push(`<!---->`);
                        },
                        $$slots: { default: true }
                      });
                      $$renderer6.push(`<!----></div> <div class="flex flex-wrap items-center gap-3">`);
                      Button($$renderer6, {
                        disabled: true,
                        children: ($$renderer7) => {
                          $$renderer7.push(`<!---->Disabled`);
                        },
                        $$slots: { default: true }
                      });
                      $$renderer6.push(`<!----> `);
                      Button($$renderer6, {
                        href: "/ui-kit#buttons",
                        variant: "outline",
                        children: ($$renderer7) => {
                          $$renderer7.push(`<!---->As link`);
                        },
                        $$slots: { default: true }
                      });
                      $$renderer6.push(`<!----> `);
                      Button($$renderer6, {
                        onclick: simulateLoading,
                        disabled: buttonLoading,
                        children: ($$renderer7) => {
                          $$renderer7.push(`<!---->${escape_html(buttonLoading ? "Loading…" : "Simulate loading")}`);
                        },
                        $$slots: { default: true }
                      });
                      $$renderer6.push(`<!----></div>`);
                    },
                    $$slots: { default: true }
                  });
                  $$renderer5.push("<!--]-->");
                } else {
                  $$renderer5.push("<!--[!-->");
                  $$renderer5.push("<!--]-->");
                }
              },
              $$slots: { default: true }
            });
            $$renderer4.push("<!--]-->");
          } else {
            $$renderer4.push("<!--[!-->");
            $$renderer4.push("<!--]-->");
          }
          $$renderer4.push(`</section> <section id="badges" class="space-y-4"><h2 class="text-foreground text-xl font-semibold">Badge</h2> `);
          if (Card) {
            $$renderer4.push("<!--[-->");
            Card($$renderer4, {
              children: ($$renderer5) => {
                if (Card_content) {
                  $$renderer5.push("<!--[-->");
                  Card_content($$renderer5, {
                    class: "flex flex-wrap gap-2",
                    children: ($$renderer6) => {
                      Badge($$renderer6, {
                        children: ($$renderer7) => {
                          $$renderer7.push(`<!---->Default`);
                        },
                        $$slots: { default: true }
                      });
                      $$renderer6.push(`<!----> `);
                      Badge($$renderer6, {
                        variant: "secondary",
                        children: ($$renderer7) => {
                          $$renderer7.push(`<!---->Secondary`);
                        },
                        $$slots: { default: true }
                      });
                      $$renderer6.push(`<!----> `);
                      Badge($$renderer6, {
                        variant: "destructive",
                        children: ($$renderer7) => {
                          $$renderer7.push(`<!---->Destructive`);
                        },
                        $$slots: { default: true }
                      });
                      $$renderer6.push(`<!----> `);
                      Badge($$renderer6, {
                        variant: "outline",
                        children: ($$renderer7) => {
                          $$renderer7.push(`<!---->Outline`);
                        },
                        $$slots: { default: true }
                      });
                      $$renderer6.push(`<!----> `);
                      Badge($$renderer6, {
                        variant: "success",
                        children: ($$renderer7) => {
                          $$renderer7.push(`<!---->Success`);
                        },
                        $$slots: { default: true }
                      });
                      $$renderer6.push(`<!----> `);
                      Badge($$renderer6, {
                        variant: "warning",
                        children: ($$renderer7) => {
                          $$renderer7.push(`<!---->Warning`);
                        },
                        $$slots: { default: true }
                      });
                      $$renderer6.push(`<!----> `);
                      Badge($$renderer6, {
                        variant: "danger",
                        children: ($$renderer7) => {
                          $$renderer7.push(`<!---->Danger`);
                        },
                        $$slots: { default: true }
                      });
                      $$renderer6.push(`<!----> `);
                      Badge($$renderer6, {
                        variant: "muted",
                        children: ($$renderer7) => {
                          $$renderer7.push(`<!---->Muted`);
                        },
                        $$slots: { default: true }
                      });
                      $$renderer6.push(`<!---->`);
                    },
                    $$slots: { default: true }
                  });
                  $$renderer5.push("<!--]-->");
                } else {
                  $$renderer5.push("<!--[!-->");
                  $$renderer5.push("<!--]-->");
                }
              },
              $$slots: { default: true }
            });
            $$renderer4.push("<!--]-->");
          } else {
            $$renderer4.push("<!--[!-->");
            $$renderer4.push("<!--]-->");
          }
          $$renderer4.push(`</section> <section id="inputs" class="space-y-4"><h2 class="text-foreground text-xl font-semibold">Input &amp; Label</h2> `);
          if (Card) {
            $$renderer4.push("<!--[-->");
            Card($$renderer4, {
              children: ($$renderer5) => {
                if (Card_content) {
                  $$renderer5.push("<!--[-->");
                  Card_content($$renderer5, {
                    class: "grid gap-6 sm:grid-cols-2",
                    children: ($$renderer6) => {
                      $$renderer6.push(`<div class="space-y-2">`);
                      Label($$renderer6, {
                        for: "demo-input-default",
                        children: ($$renderer7) => {
                          $$renderer7.push(`<!---->Default`);
                        },
                        $$slots: { default: true }
                      });
                      $$renderer6.push(`<!----> `);
                      Input($$renderer6, {
                        id: "demo-input-default",
                        get value() {
                          return inputValue;
                        },
                        set value($$value) {
                          inputValue = $$value;
                          $$settled = false;
                        }
                      });
                      $$renderer6.push(`<!----></div> <div class="space-y-2">`);
                      Label($$renderer6, {
                        for: "demo-input-disabled",
                        children: ($$renderer7) => {
                          $$renderer7.push(`<!---->Disabled`);
                        },
                        $$slots: { default: true }
                      });
                      $$renderer6.push(`<!----> `);
                      Input($$renderer6, {
                        id: "demo-input-disabled",
                        value: "Cannot edit",
                        disabled: true
                      });
                      $$renderer6.push(`<!----></div> <div class="space-y-2">`);
                      Label($$renderer6, {
                        for: "demo-input-invalid",
                        children: ($$renderer7) => {
                          $$renderer7.push(`<!---->Invalid`);
                        },
                        $$slots: { default: true }
                      });
                      $$renderer6.push(`<!----> `);
                      Input($$renderer6, {
                        id: "demo-input-invalid",
                        "aria-invalid": true,
                        get value() {
                          return invalidValue;
                        },
                        set value($$value) {
                          invalidValue = $$value;
                          $$settled = false;
                        }
                      });
                      $$renderer6.push(`<!----> <p class="text-destructive text-sm">Enter a valid email address.</p></div> <div class="space-y-2">`);
                      Label($$renderer6, {
                        for: "demo-input-placeholder",
                        children: ($$renderer7) => {
                          $$renderer7.push(`<!---->Placeholder only`);
                        },
                        $$slots: { default: true }
                      });
                      $$renderer6.push(`<!----> `);
                      Input($$renderer6, {
                        id: "demo-input-placeholder",
                        placeholder: "e.g. registrar@nexiam.test"
                      });
                      $$renderer6.push(`<!----></div>`);
                    },
                    $$slots: { default: true }
                  });
                  $$renderer5.push("<!--]-->");
                } else {
                  $$renderer5.push("<!--[!-->");
                  $$renderer5.push("<!--]-->");
                }
              },
              $$slots: { default: true }
            });
            $$renderer4.push("<!--]-->");
          } else {
            $$renderer4.push("<!--[!-->");
            $$renderer4.push("<!--]-->");
          }
          $$renderer4.push(`</section> <section id="textarea" class="space-y-4"><h2 class="text-foreground text-xl font-semibold">Textarea</h2> `);
          if (Card) {
            $$renderer4.push("<!--[-->");
            Card($$renderer4, {
              children: ($$renderer5) => {
                if (Card_content) {
                  $$renderer5.push("<!--[-->");
                  Card_content($$renderer5, {
                    class: "grid gap-6 sm:grid-cols-2",
                    children: ($$renderer6) => {
                      $$renderer6.push(`<div class="space-y-2">`);
                      Label($$renderer6, {
                        for: "demo-textarea",
                        children: ($$renderer7) => {
                          $$renderer7.push(`<!---->Default`);
                        },
                        $$slots: { default: true }
                      });
                      $$renderer6.push(`<!----> `);
                      Textarea($$renderer6, {
                        id: "demo-textarea",
                        rows: 4,
                        get value() {
                          return textareaValue;
                        },
                        set value($$value) {
                          textareaValue = $$value;
                          $$settled = false;
                        }
                      });
                      $$renderer6.push(`<!----></div> <div class="space-y-2">`);
                      Label($$renderer6, {
                        for: "demo-textarea-disabled",
                        children: ($$renderer7) => {
                          $$renderer7.push(`<!---->Disabled`);
                        },
                        $$slots: { default: true }
                      });
                      $$renderer6.push(`<!----> `);
                      Textarea($$renderer6, {
                        id: "demo-textarea-disabled",
                        value: "Read-only demo content.",
                        disabled: true,
                        rows: 4
                      });
                      $$renderer6.push(`<!----></div>`);
                    },
                    $$slots: { default: true }
                  });
                  $$renderer5.push("<!--]-->");
                } else {
                  $$renderer5.push("<!--[!-->");
                  $$renderer5.push("<!--]-->");
                }
              },
              $$slots: { default: true }
            });
            $$renderer4.push("<!--]-->");
          } else {
            $$renderer4.push("<!--[!-->");
            $$renderer4.push("<!--]-->");
          }
          $$renderer4.push(`</section> <section id="toggles" class="space-y-4"><h2 class="text-foreground text-xl font-semibold">Checkbox, Switch, Toggle &amp; Toggle group</h2> `);
          if (Card) {
            $$renderer4.push("<!--[-->");
            Card($$renderer4, {
              children: ($$renderer5) => {
                if (Card_content) {
                  $$renderer5.push("<!--[-->");
                  Card_content($$renderer5, {
                    class: "space-y-6",
                    children: ($$renderer6) => {
                      $$renderer6.push(`<div class="flex flex-wrap items-center gap-6"><label class="flex items-center gap-2 text-sm">`);
                      Checkbox($$renderer6, {
                        get checked() {
                          return checkboxChecked;
                        },
                        set checked($$value) {
                          checkboxChecked = $$value;
                          $$settled = false;
                        }
                      });
                      $$renderer6.push(`<!----> Checked</label> <label class="flex items-center gap-2 text-sm">`);
                      Checkbox($$renderer6, { checked: false });
                      $$renderer6.push(`<!----> Unchecked</label> <label class="flex items-center gap-2 text-sm">`);
                      Checkbox($$renderer6, {
                        checked: false,
                        get indeterminate() {
                          return checkboxIndeterminate;
                        },
                        set indeterminate($$value) {
                          checkboxIndeterminate = $$value;
                          $$settled = false;
                        }
                      });
                      $$renderer6.push(`<!----> Indeterminate</label> <label class="text-muted-foreground flex items-center gap-2 text-sm">`);
                      Checkbox($$renderer6, { checked: true, disabled: true });
                      $$renderer6.push(`<!----> Disabled</label></div> <div class="flex flex-wrap items-center gap-6"><label class="flex items-center gap-2 text-sm">`);
                      Switch($$renderer6, {
                        get checked() {
                          return switchOn;
                        },
                        set checked($$value) {
                          switchOn = $$value;
                          $$settled = false;
                        }
                      });
                      $$renderer6.push(`<!----> ${escape_html(switchOn ? "On" : "Off")}</label> <label class="text-muted-foreground flex items-center gap-2 text-sm">`);
                      Switch($$renderer6, { checked: true, disabled: true });
                      $$renderer6.push(`<!----> Disabled (on)</label></div> <div class="flex flex-wrap items-center gap-6">`);
                      Toggle($$renderer6, {
                        variant: "outline",
                        "aria-label": "Toggle bold",
                        get pressed() {
                          return togglePressed;
                        },
                        set pressed($$value) {
                          togglePressed = $$value;
                          $$settled = false;
                        },
                        children: ($$renderer7) => {
                          $$renderer7.push(`<!---->${escape_html(togglePressed ? "Pressed" : "Not pressed")}`);
                        },
                        $$slots: { default: true }
                      });
                      $$renderer6.push(`<!----> `);
                      Toggle($$renderer6, {
                        pressed: true,
                        disabled: true,
                        variant: "outline",
                        children: ($$renderer7) => {
                          $$renderer7.push(`<!---->Disabled`);
                        },
                        $$slots: { default: true }
                      });
                      $$renderer6.push(`<!----></div> <div class="space-y-2">`);
                      Label($$renderer6, {
                        children: ($$renderer7) => {
                          $$renderer7.push(`<!---->Toggle group (single select)`);
                        },
                        $$slots: { default: true }
                      });
                      $$renderer6.push(`<!----> `);
                      if (Toggle_group) {
                        $$renderer6.push("<!--[-->");
                        Toggle_group($$renderer6, {
                          type: "single",
                          variant: "outline",
                          size: "sm",
                          get value() {
                            return toggleGroupValue;
                          },
                          set value($$value) {
                            toggleGroupValue = $$value;
                            $$settled = false;
                          },
                          children: ($$renderer7) => {
                            if (Toggle_group_item) {
                              $$renderer7.push("<!--[-->");
                              Toggle_group_item($$renderer7, {
                                value: "bold",
                                children: ($$renderer8) => {
                                  $$renderer8.push(`<!---->Bold`);
                                },
                                $$slots: { default: true }
                              });
                              $$renderer7.push("<!--]-->");
                            } else {
                              $$renderer7.push("<!--[!-->");
                              $$renderer7.push("<!--]-->");
                            }
                            $$renderer7.push(` `);
                            if (Toggle_group_item) {
                              $$renderer7.push("<!--[-->");
                              Toggle_group_item($$renderer7, {
                                value: "italic",
                                children: ($$renderer8) => {
                                  $$renderer8.push(`<!---->Italic`);
                                },
                                $$slots: { default: true }
                              });
                              $$renderer7.push("<!--]-->");
                            } else {
                              $$renderer7.push("<!--[!-->");
                              $$renderer7.push("<!--]-->");
                            }
                            $$renderer7.push(` `);
                            if (Toggle_group_item) {
                              $$renderer7.push("<!--[-->");
                              Toggle_group_item($$renderer7, {
                                value: "underline",
                                children: ($$renderer8) => {
                                  $$renderer8.push(`<!---->Underline`);
                                },
                                $$slots: { default: true }
                              });
                              $$renderer7.push("<!--]-->");
                            } else {
                              $$renderer7.push("<!--[!-->");
                              $$renderer7.push("<!--]-->");
                            }
                          },
                          $$slots: { default: true }
                        });
                        $$renderer6.push("<!--]-->");
                      } else {
                        $$renderer6.push("<!--[!-->");
                        $$renderer6.push("<!--]-->");
                      }
                      $$renderer6.push(` <p class="text-muted-foreground text-sm">Selected: ${escape_html(toggleGroupValue)}</p></div>`);
                    },
                    $$slots: { default: true }
                  });
                  $$renderer5.push("<!--]-->");
                } else {
                  $$renderer5.push("<!--[!-->");
                  $$renderer5.push("<!--]-->");
                }
              },
              $$slots: { default: true }
            });
            $$renderer4.push("<!--]-->");
          } else {
            $$renderer4.push("<!--[!-->");
            $$renderer4.push("<!--]-->");
          }
          $$renderer4.push(`</section> <section id="select" class="space-y-4"><h2 class="text-foreground text-xl font-semibold">Select</h2> `);
          if (Card) {
            $$renderer4.push("<!--[-->");
            Card($$renderer4, {
              children: ($$renderer5) => {
                if (Card_content) {
                  $$renderer5.push("<!--[-->");
                  Card_content($$renderer5, {
                    class: "grid gap-6 sm:grid-cols-2",
                    children: ($$renderer6) => {
                      $$renderer6.push(`<div class="space-y-2">`);
                      Label($$renderer6, {
                        children: ($$renderer7) => {
                          $$renderer7.push(`<!---->Office (default)`);
                        },
                        $$slots: { default: true }
                      });
                      $$renderer6.push(`<!----> `);
                      if (Select) {
                        $$renderer6.push("<!--[-->");
                        Select($$renderer6, {
                          type: "single",
                          get value() {
                            return selectValue;
                          },
                          set value($$value) {
                            selectValue = $$value;
                            $$settled = false;
                          },
                          children: ($$renderer7) => {
                            if (Select_trigger) {
                              $$renderer7.push("<!--[-->");
                              Select_trigger($$renderer7, {
                                class: "w-56",
                                children: ($$renderer8) => {
                                  $$renderer8.push(`<!---->${escape_html(selectLabel())}`);
                                },
                                $$slots: { default: true }
                              });
                              $$renderer7.push("<!--]-->");
                            } else {
                              $$renderer7.push("<!--[!-->");
                              $$renderer7.push("<!--]-->");
                            }
                            $$renderer7.push(` `);
                            if (Select_content) {
                              $$renderer7.push("<!--[-->");
                              Select_content($$renderer7, {
                                children: ($$renderer8) => {
                                  $$renderer8.push(`<!--[-->`);
                                  const each_array_2 = ensure_array_like(selectOptions);
                                  for (let $$index_2 = 0, $$length = each_array_2.length; $$index_2 < $$length; $$index_2++) {
                                    let option = each_array_2[$$index_2];
                                    if (Select_item) {
                                      $$renderer8.push("<!--[-->");
                                      Select_item($$renderer8, { value: option.value, label: option.label });
                                      $$renderer8.push("<!--]-->");
                                    } else {
                                      $$renderer8.push("<!--[!-->");
                                      $$renderer8.push("<!--]-->");
                                    }
                                  }
                                  $$renderer8.push(`<!--]-->`);
                                },
                                $$slots: { default: true }
                              });
                              $$renderer7.push("<!--]-->");
                            } else {
                              $$renderer7.push("<!--[!-->");
                              $$renderer7.push("<!--]-->");
                            }
                          },
                          $$slots: { default: true }
                        });
                        $$renderer6.push("<!--]-->");
                      } else {
                        $$renderer6.push("<!--[!-->");
                        $$renderer6.push("<!--]-->");
                      }
                      $$renderer6.push(`</div> <div class="space-y-2">`);
                      Label($$renderer6, {
                        children: ($$renderer7) => {
                          $$renderer7.push(`<!---->Disabled`);
                        },
                        $$slots: { default: true }
                      });
                      $$renderer6.push(`<!----> `);
                      if (Select) {
                        $$renderer6.push("<!--[-->");
                        Select($$renderer6, {
                          type: "single",
                          disabled: true,
                          children: ($$renderer7) => {
                            if (Select_trigger) {
                              $$renderer7.push("<!--[-->");
                              Select_trigger($$renderer7, {
                                class: "w-56",
                                children: ($$renderer8) => {
                                  $$renderer8.push(`<!---->Locked selection`);
                                },
                                $$slots: { default: true }
                              });
                              $$renderer7.push("<!--]-->");
                            } else {
                              $$renderer7.push("<!--[!-->");
                              $$renderer7.push("<!--]-->");
                            }
                            $$renderer7.push(` `);
                            if (Select_content) {
                              $$renderer7.push("<!--[-->");
                              Select_content($$renderer7, {
                                children: ($$renderer8) => {
                                  if (Select_item) {
                                    $$renderer8.push("<!--[-->");
                                    Select_item($$renderer8, { value: "locked", label: "Locked selection" });
                                    $$renderer8.push("<!--]-->");
                                  } else {
                                    $$renderer8.push("<!--[!-->");
                                    $$renderer8.push("<!--]-->");
                                  }
                                },
                                $$slots: { default: true }
                              });
                              $$renderer7.push("<!--]-->");
                            } else {
                              $$renderer7.push("<!--[!-->");
                              $$renderer7.push("<!--]-->");
                            }
                          },
                          $$slots: { default: true }
                        });
                        $$renderer6.push("<!--]-->");
                      } else {
                        $$renderer6.push("<!--[!-->");
                        $$renderer6.push("<!--]-->");
                      }
                      $$renderer6.push(`</div>`);
                    },
                    $$slots: { default: true }
                  });
                  $$renderer5.push("<!--]-->");
                } else {
                  $$renderer5.push("<!--[!-->");
                  $$renderer5.push("<!--]-->");
                }
              },
              $$slots: { default: true }
            });
            $$renderer4.push("<!--]-->");
          } else {
            $$renderer4.push("<!--[!-->");
            $$renderer4.push("<!--]-->");
          }
          $$renderer4.push(`</section> <section id="tabs" class="space-y-4"><h2 class="text-foreground text-xl font-semibold">Tabs</h2> `);
          if (Card) {
            $$renderer4.push("<!--[-->");
            Card($$renderer4, {
              children: ($$renderer5) => {
                if (Card_content) {
                  $$renderer5.push("<!--[-->");
                  Card_content($$renderer5, {
                    class: "space-y-8",
                    children: ($$renderer6) => {
                      $$renderer6.push(`<div><p class="text-muted-foreground mb-3 text-xs font-medium tracking-wide uppercase">Line (default) — institutional</p> `);
                      if (Tabs) {
                        $$renderer6.push("<!--[-->");
                        Tabs($$renderer6, {
                          get value() {
                            return tabsValue;
                          },
                          set value($$value) {
                            tabsValue = $$value;
                            $$settled = false;
                          },
                          children: ($$renderer7) => {
                            if (Tabs_list) {
                              $$renderer7.push("<!--[-->");
                              Tabs_list($$renderer7, {
                                children: ($$renderer8) => {
                                  if (Tabs_trigger) {
                                    $$renderer8.push("<!--[-->");
                                    Tabs_trigger($$renderer8, {
                                      value: "overview",
                                      children: ($$renderer9) => {
                                        $$renderer9.push(`<!---->Overview`);
                                      },
                                      $$slots: { default: true }
                                    });
                                    $$renderer8.push("<!--]-->");
                                  } else {
                                    $$renderer8.push("<!--[!-->");
                                    $$renderer8.push("<!--]-->");
                                  }
                                  $$renderer8.push(` `);
                                  if (Tabs_trigger) {
                                    $$renderer8.push("<!--[-->");
                                    Tabs_trigger($$renderer8, {
                                      value: "details",
                                      children: ($$renderer9) => {
                                        $$renderer9.push(`<!---->Details`);
                                      },
                                      $$slots: { default: true }
                                    });
                                    $$renderer8.push("<!--]-->");
                                  } else {
                                    $$renderer8.push("<!--[!-->");
                                    $$renderer8.push("<!--]-->");
                                  }
                                  $$renderer8.push(` `);
                                  if (Tabs_trigger) {
                                    $$renderer8.push("<!--[-->");
                                    Tabs_trigger($$renderer8, {
                                      value: "disabled",
                                      disabled: true,
                                      children: ($$renderer9) => {
                                        $$renderer9.push(`<!---->Disabled`);
                                      },
                                      $$slots: { default: true }
                                    });
                                    $$renderer8.push("<!--]-->");
                                  } else {
                                    $$renderer8.push("<!--[!-->");
                                    $$renderer8.push("<!--]-->");
                                  }
                                },
                                $$slots: { default: true }
                              });
                              $$renderer7.push("<!--]-->");
                            } else {
                              $$renderer7.push("<!--[!-->");
                              $$renderer7.push("<!--]-->");
                            }
                            $$renderer7.push(` `);
                            if (Tabs_content) {
                              $$renderer7.push("<!--[-->");
                              Tabs_content($$renderer7, {
                                value: "overview",
                                class: "text-muted-foreground pt-4 text-sm",
                                children: ($$renderer8) => {
                                  $$renderer8.push(`<!---->Synthetic overview panel content.`);
                                },
                                $$slots: { default: true }
                              });
                              $$renderer7.push("<!--]-->");
                            } else {
                              $$renderer7.push("<!--[!-->");
                              $$renderer7.push("<!--]-->");
                            }
                            $$renderer7.push(` `);
                            if (Tabs_content) {
                              $$renderer7.push("<!--[-->");
                              Tabs_content($$renderer7, {
                                value: "details",
                                class: "text-muted-foreground pt-4 text-sm",
                                children: ($$renderer8) => {
                                  $$renderer8.push(`<!---->Synthetic details panel content.`);
                                },
                                $$slots: { default: true }
                              });
                              $$renderer7.push("<!--]-->");
                            } else {
                              $$renderer7.push("<!--[!-->");
                              $$renderer7.push("<!--]-->");
                            }
                          },
                          $$slots: { default: true }
                        });
                        $$renderer6.push("<!--]-->");
                      } else {
                        $$renderer6.push("<!--[!-->");
                        $$renderer6.push("<!--]-->");
                      }
                      $$renderer6.push(`</div> <div><p class="text-muted-foreground mb-3 text-xs font-medium tracking-wide uppercase">Segmented — optional dense toolbars</p> `);
                      if (Tabs) {
                        $$renderer6.push("<!--[-->");
                        Tabs($$renderer6, {
                          value: "a",
                          children: ($$renderer7) => {
                            if (Tabs_list) {
                              $$renderer7.push("<!--[-->");
                              Tabs_list($$renderer7, {
                                variant: "default",
                                children: ($$renderer8) => {
                                  if (Tabs_trigger) {
                                    $$renderer8.push("<!--[-->");
                                    Tabs_trigger($$renderer8, {
                                      value: "a",
                                      children: ($$renderer9) => {
                                        $$renderer9.push(`<!---->Accepted`);
                                      },
                                      $$slots: { default: true }
                                    });
                                    $$renderer8.push("<!--]-->");
                                  } else {
                                    $$renderer8.push("<!--[!-->");
                                    $$renderer8.push("<!--]-->");
                                  }
                                  $$renderer8.push(` `);
                                  if (Tabs_trigger) {
                                    $$renderer8.push("<!--[-->");
                                    Tabs_trigger($$renderer8, {
                                      value: "b",
                                      children: ($$renderer9) => {
                                        $$renderer9.push(`<!---->Pending`);
                                      },
                                      $$slots: { default: true }
                                    });
                                    $$renderer8.push("<!--]-->");
                                  } else {
                                    $$renderer8.push("<!--[!-->");
                                    $$renderer8.push("<!--]-->");
                                  }
                                  $$renderer8.push(` `);
                                  if (Tabs_trigger) {
                                    $$renderer8.push("<!--[-->");
                                    Tabs_trigger($$renderer8, {
                                      value: "c",
                                      children: ($$renderer9) => {
                                        $$renderer9.push(`<!---->Dismissed`);
                                      },
                                      $$slots: { default: true }
                                    });
                                    $$renderer8.push("<!--]-->");
                                  } else {
                                    $$renderer8.push("<!--[!-->");
                                    $$renderer8.push("<!--]-->");
                                  }
                                },
                                $$slots: { default: true }
                              });
                              $$renderer7.push("<!--]-->");
                            } else {
                              $$renderer7.push("<!--[!-->");
                              $$renderer7.push("<!--]-->");
                            }
                          },
                          $$slots: { default: true }
                        });
                        $$renderer6.push("<!--]-->");
                      } else {
                        $$renderer6.push("<!--[!-->");
                        $$renderer6.push("<!--]-->");
                      }
                      $$renderer6.push(`</div>`);
                    },
                    $$slots: { default: true }
                  });
                  $$renderer5.push("<!--]-->");
                } else {
                  $$renderer5.push("<!--[!-->");
                  $$renderer5.push("<!--]-->");
                }
              },
              $$slots: { default: true }
            });
            $$renderer4.push("<!--]-->");
          } else {
            $$renderer4.push("<!--[!-->");
            $$renderer4.push("<!--]-->");
          }
          $$renderer4.push(`</section> <section id="cards" class="space-y-4"><h2 class="text-foreground text-xl font-semibold">Card</h2> <div class="grid gap-4 sm:grid-cols-2">`);
          if (Card) {
            $$renderer4.push("<!--[-->");
            Card($$renderer4, {
              children: ($$renderer5) => {
                if (Card_header) {
                  $$renderer5.push("<!--[-->");
                  Card_header($$renderer5, {
                    children: ($$renderer6) => {
                      if (Card_title) {
                        $$renderer6.push("<!--[-->");
                        Card_title($$renderer6, {
                          children: ($$renderer7) => {
                            $$renderer7.push(`<!---->Default card`);
                          },
                          $$slots: { default: true }
                        });
                        $$renderer6.push("<!--]-->");
                      } else {
                        $$renderer6.push("<!--[!-->");
                        $$renderer6.push("<!--]-->");
                      }
                      $$renderer6.push(` `);
                      if (Card_description) {
                        $$renderer6.push("<!--[-->");
                        Card_description($$renderer6, {
                          children: ($$renderer7) => {
                            $$renderer7.push(`<!---->Standard elevated surface.`);
                          },
                          $$slots: { default: true }
                        });
                        $$renderer6.push("<!--]-->");
                      } else {
                        $$renderer6.push("<!--[!-->");
                        $$renderer6.push("<!--]-->");
                      }
                      $$renderer6.push(` `);
                      if (Card_action) {
                        $$renderer6.push("<!--[-->");
                        Card_action($$renderer6, {
                          children: ($$renderer7) => {
                            Badge($$renderer7, {
                              variant: "outline",
                              children: ($$renderer8) => {
                                $$renderer8.push(`<!---->Default`);
                              },
                              $$slots: { default: true }
                            });
                          },
                          $$slots: { default: true }
                        });
                        $$renderer6.push("<!--]-->");
                      } else {
                        $$renderer6.push("<!--[!-->");
                        $$renderer6.push("<!--]-->");
                      }
                    },
                    $$slots: { default: true }
                  });
                  $$renderer5.push("<!--]-->");
                } else {
                  $$renderer5.push("<!--[!-->");
                  $$renderer5.push("<!--]-->");
                }
                $$renderer5.push(` `);
                if (Card_content) {
                  $$renderer5.push("<!--[-->");
                  Card_content($$renderer5, {
                    children: ($$renderer6) => {
                      $$renderer6.push(`<p class="text-muted-foreground text-sm">Body content area — used for most panel layouts
                            across the kit.</p>`);
                    },
                    $$slots: { default: true }
                  });
                  $$renderer5.push("<!--]-->");
                } else {
                  $$renderer5.push("<!--[!-->");
                  $$renderer5.push("<!--]-->");
                }
                $$renderer5.push(` `);
                if (Card_footer) {
                  $$renderer5.push("<!--[-->");
                  Card_footer($$renderer5, {
                    class: "border-t pt-6",
                    children: ($$renderer6) => {
                      Button($$renderer6, {
                        size: "sm",
                        variant: "outline",
                        children: ($$renderer7) => {
                          $$renderer7.push(`<!---->Action`);
                        },
                        $$slots: { default: true }
                      });
                    },
                    $$slots: { default: true }
                  });
                  $$renderer5.push("<!--]-->");
                } else {
                  $$renderer5.push("<!--[!-->");
                  $$renderer5.push("<!--]-->");
                }
              },
              $$slots: { default: true }
            });
            $$renderer4.push("<!--]-->");
          } else {
            $$renderer4.push("<!--[!-->");
            $$renderer4.push("<!--]-->");
          }
          $$renderer4.push(` <div class="rounded-xl p-5 sm:p-6" style="background-image: radial-gradient(ellipse 90% 70% at 0% 0%, oklch(0.88 0.06 145 / 0.55), transparent 55%), radial-gradient(ellipse 70% 60% at 100% 100%, oklch(0.92 0.03 95 / 0.45), transparent 50%), linear-gradient(180deg, oklch(0.97 0.01 87), oklch(0.94 0.02 145 / 0.9));">`);
          if (Card) {
            $$renderer4.push("<!--[-->");
            Card($$renderer4, {
              variant: "glass",
              class: "border-0",
              children: ($$renderer5) => {
                if (Card_header) {
                  $$renderer5.push("<!--[-->");
                  Card_header($$renderer5, {
                    children: ($$renderer6) => {
                      if (Card_title) {
                        $$renderer6.push("<!--[-->");
                        Card_title($$renderer6, {
                          children: ($$renderer7) => {
                            $$renderer7.push(`<!---->Glass panel card`);
                          },
                          $$slots: { default: true }
                        });
                        $$renderer6.push("<!--]-->");
                      } else {
                        $$renderer6.push("<!--[!-->");
                        $$renderer6.push("<!--]-->");
                      }
                      $$renderer6.push(` `);
                      if (Card_description) {
                        $$renderer6.push("<!--[-->");
                        Card_description($$renderer6, {
                          children: ($$renderer7) => {
                            $$renderer7.push(`<!---->Single quiet edge from <code>.glass-panel</code> only
                                — no second wrapper border.`);
                          },
                          $$slots: { default: true }
                        });
                        $$renderer6.push("<!--]-->");
                      } else {
                        $$renderer6.push("<!--[!-->");
                        $$renderer6.push("<!--]-->");
                      }
                    },
                    $$slots: { default: true }
                  });
                  $$renderer5.push("<!--]-->");
                } else {
                  $$renderer5.push("<!--[!-->");
                  $$renderer5.push("<!--]-->");
                }
                $$renderer5.push(` `);
                if (Card_content) {
                  $$renderer5.push("<!--[-->");
                  Card_content($$renderer5, {
                    children: ($$renderer6) => {
                      $$renderer6.push(`<p class="text-muted-foreground text-sm">Sit glass on layered paper so blur reads; never
                                nest inside another bordered card.</p>`);
                    },
                    $$slots: { default: true }
                  });
                  $$renderer5.push("<!--]-->");
                } else {
                  $$renderer5.push("<!--[!-->");
                  $$renderer5.push("<!--]-->");
                }
              },
              $$slots: { default: true }
            });
            $$renderer4.push("<!--]-->");
          } else {
            $$renderer4.push("<!--[!-->");
            $$renderer4.push("<!--]-->");
          }
          $$renderer4.push(`</div></div></section> <section id="table" class="space-y-4"><h2 class="text-foreground text-xl font-semibold">Table &amp; pagination</h2> `);
          if (Card) {
            $$renderer4.push("<!--[-->");
            Card($$renderer4, {
              children: ($$renderer5) => {
                if (Card_content) {
                  $$renderer5.push("<!--[-->");
                  Card_content($$renderer5, {
                    children: ($$renderer6) => {
                      if (Table) {
                        $$renderer6.push("<!--[-->");
                        Table($$renderer6, {
                          children: ($$renderer7) => {
                            if (Table_caption) {
                              $$renderer7.push("<!--[-->");
                              Table_caption($$renderer7, {
                                children: ($$renderer8) => {
                                  $$renderer8.push(`<!---->Synthetic applicant rows — demo data only.`);
                                },
                                $$slots: { default: true }
                              });
                              $$renderer7.push("<!--]-->");
                            } else {
                              $$renderer7.push("<!--[!-->");
                              $$renderer7.push("<!--]-->");
                            }
                            $$renderer7.push(` `);
                            if (Table_header) {
                              $$renderer7.push("<!--[-->");
                              Table_header($$renderer7, {
                                children: ($$renderer8) => {
                                  if (Table_row) {
                                    $$renderer8.push("<!--[-->");
                                    Table_row($$renderer8, {
                                      children: ($$renderer9) => {
                                        if (Table_head) {
                                          $$renderer9.push("<!--[-->");
                                          Table_head($$renderer9, {
                                            children: ($$renderer10) => {
                                              $$renderer10.push(`<!---->Name`);
                                            },
                                            $$slots: { default: true }
                                          });
                                          $$renderer9.push("<!--]-->");
                                        } else {
                                          $$renderer9.push("<!--[!-->");
                                          $$renderer9.push("<!--]-->");
                                        }
                                        $$renderer9.push(` `);
                                        if (Table_head) {
                                          $$renderer9.push("<!--[-->");
                                          Table_head($$renderer9, {
                                            children: ($$renderer10) => {
                                              $$renderer10.push(`<!---->Program`);
                                            },
                                            $$slots: { default: true }
                                          });
                                          $$renderer9.push("<!--]-->");
                                        } else {
                                          $$renderer9.push("<!--[!-->");
                                          $$renderer9.push("<!--]-->");
                                        }
                                        $$renderer9.push(` `);
                                        if (Table_head) {
                                          $$renderer9.push("<!--[-->");
                                          Table_head($$renderer9, {
                                            children: ($$renderer10) => {
                                              $$renderer10.push(`<!---->Status`);
                                            },
                                            $$slots: { default: true }
                                          });
                                          $$renderer9.push("<!--]-->");
                                        } else {
                                          $$renderer9.push("<!--[!-->");
                                          $$renderer9.push("<!--]-->");
                                        }
                                      },
                                      $$slots: { default: true }
                                    });
                                    $$renderer8.push("<!--]-->");
                                  } else {
                                    $$renderer8.push("<!--[!-->");
                                    $$renderer8.push("<!--]-->");
                                  }
                                },
                                $$slots: { default: true }
                              });
                              $$renderer7.push("<!--]-->");
                            } else {
                              $$renderer7.push("<!--[!-->");
                              $$renderer7.push("<!--]-->");
                            }
                            $$renderer7.push(` `);
                            if (Table_body) {
                              $$renderer7.push("<!--[-->");
                              Table_body($$renderer7, {
                                children: ($$renderer8) => {
                                  $$renderer8.push(`<!--[-->`);
                                  const each_array_3 = ensure_array_like(tableRows);
                                  for (let $$index_3 = 0, $$length = each_array_3.length; $$index_3 < $$length; $$index_3++) {
                                    let row = each_array_3[$$index_3];
                                    if (Table_row) {
                                      $$renderer8.push("<!--[-->");
                                      Table_row($$renderer8, {
                                        children: ($$renderer9) => {
                                          if (Table_cell) {
                                            $$renderer9.push("<!--[-->");
                                            Table_cell($$renderer9, {
                                              children: ($$renderer10) => {
                                                $$renderer10.push(`<!---->${escape_html(row.name)}`);
                                              },
                                              $$slots: { default: true }
                                            });
                                            $$renderer9.push("<!--]-->");
                                          } else {
                                            $$renderer9.push("<!--[!-->");
                                            $$renderer9.push("<!--]-->");
                                          }
                                          $$renderer9.push(` `);
                                          if (Table_cell) {
                                            $$renderer9.push("<!--[-->");
                                            Table_cell($$renderer9, {
                                              children: ($$renderer10) => {
                                                $$renderer10.push(`<!---->${escape_html(row.program)}`);
                                              },
                                              $$slots: { default: true }
                                            });
                                            $$renderer9.push("<!--]-->");
                                          } else {
                                            $$renderer9.push("<!--[!-->");
                                            $$renderer9.push("<!--]-->");
                                          }
                                          $$renderer9.push(` `);
                                          if (Table_cell) {
                                            $$renderer9.push("<!--[-->");
                                            Table_cell($$renderer9, {
                                              children: ($$renderer10) => {
                                                Badge($$renderer10, {
                                                  variant: row.status === "Passed" ? "success" : row.status === "Failed" ? "destructive" : "muted",
                                                  children: ($$renderer11) => {
                                                    $$renderer11.push(`<!---->${escape_html(row.status)}`);
                                                  },
                                                  $$slots: { default: true }
                                                });
                                              },
                                              $$slots: { default: true }
                                            });
                                            $$renderer9.push("<!--]-->");
                                          } else {
                                            $$renderer9.push("<!--[!-->");
                                            $$renderer9.push("<!--]-->");
                                          }
                                        },
                                        $$slots: { default: true }
                                      });
                                      $$renderer8.push("<!--]-->");
                                    } else {
                                      $$renderer8.push("<!--[!-->");
                                      $$renderer8.push("<!--]-->");
                                    }
                                  }
                                  $$renderer8.push(`<!--]-->`);
                                },
                                $$slots: { default: true }
                              });
                              $$renderer7.push("<!--]-->");
                            } else {
                              $$renderer7.push("<!--[!-->");
                              $$renderer7.push("<!--]-->");
                            }
                          },
                          $$slots: { default: true }
                        });
                        $$renderer6.push("<!--]-->");
                      } else {
                        $$renderer6.push("<!--[!-->");
                        $$renderer6.push("<!--]-->");
                      }
                      $$renderer6.push(` `);
                      SimplePagination($$renderer6, { data: paginationDemoPage2of5 });
                      $$renderer6.push(`<!----> `);
                      SimplePagination($$renderer6, {
                        data: paginationDemoPage1of3,
                        variant: "centered",
                        class: "mt-6"
                      });
                      $$renderer6.push(`<!---->`);
                    },
                    $$slots: { default: true }
                  });
                  $$renderer5.push("<!--]-->");
                } else {
                  $$renderer5.push("<!--[!-->");
                  $$renderer5.push("<!--]-->");
                }
              },
              $$slots: { default: true }
            });
            $$renderer4.push("<!--]-->");
          } else {
            $$renderer4.push("<!--[!-->");
            $$renderer4.push("<!--]-->");
          }
          $$renderer4.push(`</section> <section id="dialog" class="space-y-4"><h2 class="text-foreground text-xl font-semibold">Dialog</h2> `);
          if (Card) {
            $$renderer4.push("<!--[-->");
            Card($$renderer4, {
              children: ($$renderer5) => {
                if (Card_content) {
                  $$renderer5.push("<!--[-->");
                  Card_content($$renderer5, {
                    class: "flex items-center gap-3",
                    children: ($$renderer6) => {
                      Button($$renderer6, {
                        variant: "outline",
                        onclick: () => dialogOpen = true,
                        children: ($$renderer7) => {
                          $$renderer7.push(`<!---->Open dialog`);
                        },
                        $$slots: { default: true }
                      });
                      $$renderer6.push(`<!----> `);
                      if (Dialog) {
                        $$renderer6.push("<!--[-->");
                        Dialog($$renderer6, {
                          get open() {
                            return dialogOpen;
                          },
                          set open($$value) {
                            dialogOpen = $$value;
                            $$settled = false;
                          },
                          children: ($$renderer7) => {
                            if (Dialog_content) {
                              $$renderer7.push("<!--[-->");
                              Dialog_content($$renderer7, {
                                class: "sm:max-w-md",
                                children: ($$renderer8) => {
                                  if (Dialog_header) {
                                    $$renderer8.push("<!--[-->");
                                    Dialog_header($$renderer8, {
                                      children: ($$renderer9) => {
                                        if (Dialog_title) {
                                          $$renderer9.push("<!--[-->");
                                          Dialog_title($$renderer9, {
                                            children: ($$renderer10) => {
                                              $$renderer10.push(`<!---->Synthetic confirmation`);
                                            },
                                            $$slots: { default: true }
                                          });
                                          $$renderer9.push("<!--]-->");
                                        } else {
                                          $$renderer9.push("<!--[!-->");
                                          $$renderer9.push("<!--]-->");
                                        }
                                        $$renderer9.push(` `);
                                        if (Dialog_description) {
                                          $$renderer9.push("<!--[-->");
                                          Dialog_description($$renderer9, {
                                            children: ($$renderer10) => {
                                              $$renderer10.push(`<!---->This dialog is presentational only — closing
                                    it changes no data.`);
                                            },
                                            $$slots: { default: true }
                                          });
                                          $$renderer9.push("<!--]-->");
                                        } else {
                                          $$renderer9.push("<!--[!-->");
                                          $$renderer9.push("<!--]-->");
                                        }
                                      },
                                      $$slots: { default: true }
                                    });
                                    $$renderer8.push("<!--]-->");
                                  } else {
                                    $$renderer8.push("<!--[!-->");
                                    $$renderer8.push("<!--]-->");
                                  }
                                  $$renderer8.push(` `);
                                  if (Dialog_footer) {
                                    $$renderer8.push("<!--[-->");
                                    Dialog_footer($$renderer8, {
                                      children: ($$renderer9) => {
                                        Button($$renderer9, {
                                          variant: "outline",
                                          onclick: () => dialogOpen = false,
                                          children: ($$renderer10) => {
                                            $$renderer10.push(`<!---->Cancel`);
                                          },
                                          $$slots: { default: true }
                                        });
                                        $$renderer9.push(`<!----> `);
                                        Button($$renderer9, {
                                          onclick: () => dialogOpen = false,
                                          children: ($$renderer10) => {
                                            $$renderer10.push(`<!---->Confirm`);
                                          },
                                          $$slots: { default: true }
                                        });
                                        $$renderer9.push(`<!---->`);
                                      },
                                      $$slots: { default: true }
                                    });
                                    $$renderer8.push("<!--]-->");
                                  } else {
                                    $$renderer8.push("<!--[!-->");
                                    $$renderer8.push("<!--]-->");
                                  }
                                },
                                $$slots: { default: true }
                              });
                              $$renderer7.push("<!--]-->");
                            } else {
                              $$renderer7.push("<!--[!-->");
                              $$renderer7.push("<!--]-->");
                            }
                          },
                          $$slots: { default: true }
                        });
                        $$renderer6.push("<!--]-->");
                      } else {
                        $$renderer6.push("<!--[!-->");
                        $$renderer6.push("<!--]-->");
                      }
                      $$renderer6.push(` <span class="text-muted-foreground text-sm">Opens via <code>bind:open</code> (no nested button trigger).</span>`);
                    },
                    $$slots: { default: true }
                  });
                  $$renderer5.push("<!--]-->");
                } else {
                  $$renderer5.push("<!--[!-->");
                  $$renderer5.push("<!--]-->");
                }
              },
              $$slots: { default: true }
            });
            $$renderer4.push("<!--]-->");
          } else {
            $$renderer4.push("<!--[!-->");
            $$renderer4.push("<!--]-->");
          }
          $$renderer4.push(`</section> <section id="popover" class="space-y-4"><h2 class="text-foreground text-xl font-semibold">Popover &amp; info popover</h2> `);
          if (Card) {
            $$renderer4.push("<!--[-->");
            Card($$renderer4, {
              children: ($$renderer5) => {
                if (Card_content) {
                  $$renderer5.push("<!--[-->");
                  Card_content($$renderer5, {
                    class: "flex flex-wrap items-center gap-6",
                    children: ($$renderer6) => {
                      if (Popover) {
                        $$renderer6.push("<!--[-->");
                        Popover($$renderer6, {
                          children: ($$renderer7) => {
                            {
                              let child = function($$renderer8, { props }) {
                                Button($$renderer8, spread_props([
                                  { variant: "outline" },
                                  props,
                                  {
                                    children: ($$renderer9) => {
                                      $$renderer9.push(`<!---->Open popover`);
                                    },
                                    $$slots: { default: true }
                                  }
                                ]));
                              };
                              if (Popover_trigger) {
                                $$renderer7.push("<!--[-->");
                                Popover_trigger($$renderer7, { child, $$slots: { child: true } });
                                $$renderer7.push("<!--]-->");
                              } else {
                                $$renderer7.push("<!--[!-->");
                                $$renderer7.push("<!--]-->");
                              }
                            }
                            $$renderer7.push(` `);
                            if (Popover_content) {
                              $$renderer7.push("<!--[-->");
                              Popover_content($$renderer7, {
                                children: ($$renderer8) => {
                                  if (Popover_header) {
                                    $$renderer8.push("<!--[-->");
                                    Popover_header($$renderer8, {
                                      children: ($$renderer9) => {
                                        if (Popover_title) {
                                          $$renderer9.push("<!--[-->");
                                          Popover_title($$renderer9, {
                                            children: ($$renderer10) => {
                                              $$renderer10.push(`<!---->Synthetic popover`);
                                            },
                                            $$slots: { default: true }
                                          });
                                          $$renderer9.push("<!--]-->");
                                        } else {
                                          $$renderer9.push("<!--[!-->");
                                          $$renderer9.push("<!--]-->");
                                        }
                                        $$renderer9.push(` `);
                                        if (Popover_description) {
                                          $$renderer9.push("<!--[-->");
                                          Popover_description($$renderer9, {
                                            children: ($$renderer10) => {
                                              $$renderer10.push(`<!---->Non-modal contextual content. Inline help
                                    uses the question-mark InfoPopover only.`);
                                            },
                                            $$slots: { default: true }
                                          });
                                          $$renderer9.push("<!--]-->");
                                        } else {
                                          $$renderer9.push("<!--[!-->");
                                          $$renderer9.push("<!--]-->");
                                        }
                                      },
                                      $$slots: { default: true }
                                    });
                                    $$renderer8.push("<!--]-->");
                                  } else {
                                    $$renderer8.push("<!--[!-->");
                                    $$renderer8.push("<!--]-->");
                                  }
                                },
                                $$slots: { default: true }
                              });
                              $$renderer7.push("<!--]-->");
                            } else {
                              $$renderer7.push("<!--[!-->");
                              $$renderer7.push("<!--]-->");
                            }
                          },
                          $$slots: { default: true }
                        });
                        $$renderer6.push("<!--]-->");
                      } else {
                        $$renderer6.push("<!--[!-->");
                        $$renderer6.push("<!--]-->");
                      }
                      $$renderer6.push(` <div class="flex items-center gap-2 text-sm"><span>Aptitude area weight</span> `);
                      InfoPopover($$renderer6, {
                        content: "Synthetic help text: this value has no effect on any real computation in the gallery. Hover or focus the question mark."
                      });
                      $$renderer6.push(`<!----></div>`);
                    },
                    $$slots: { default: true }
                  });
                  $$renderer5.push("<!--]-->");
                } else {
                  $$renderer5.push("<!--[!-->");
                  $$renderer5.push("<!--]-->");
                }
              },
              $$slots: { default: true }
            });
            $$renderer4.push("<!--]-->");
          } else {
            $$renderer4.push("<!--[!-->");
            $$renderer4.push("<!--]-->");
          }
          $$renderer4.push(`</section> <section id="command" class="space-y-4"><h2 class="text-foreground text-xl font-semibold">Command</h2> `);
          if (Card) {
            $$renderer4.push("<!--[-->");
            Card($$renderer4, {
              children: ($$renderer5) => {
                if (Card_content) {
                  $$renderer5.push("<!--[-->");
                  Card_content($$renderer5, {
                    class: "space-y-4",
                    children: ($$renderer6) => {
                      $$renderer6.push(`<div class="flex items-center gap-3">`);
                      Button($$renderer6, {
                        variant: "outline",
                        onclick: () => commandDialogOpen = true,
                        children: ($$renderer7) => {
                          $$renderer7.push(`<!---->Open command palette`);
                        },
                        $$slots: { default: true }
                      });
                      $$renderer6.push(`<!----> `);
                      Button($$renderer6, {
                        variant: "ghost",
                        size: "sm",
                        onclick: simulateCommandLoading,
                        children: ($$renderer7) => {
                          $$renderer7.push(`<!---->Simulate loading state`);
                        },
                        $$slots: { default: true }
                      });
                      $$renderer6.push(`<!----></div> <div class="border-border max-w-md rounded-lg border">`);
                      if (Command) {
                        $$renderer6.push("<!--[-->");
                        Command($$renderer6, {
                          children: ($$renderer7) => {
                            if (Command_input) {
                              $$renderer7.push("<!--[-->");
                              Command_input($$renderer7, { placeholder: "Search sections…" });
                              $$renderer7.push("<!--]-->");
                            } else {
                              $$renderer7.push("<!--[!-->");
                              $$renderer7.push("<!--]-->");
                            }
                            $$renderer7.push(` `);
                            if (Command_list) {
                              $$renderer7.push("<!--[-->");
                              Command_list($$renderer7, {
                                children: ($$renderer8) => {
                                  if (commandLoading) {
                                    $$renderer8.push("<!--[0-->");
                                    if (Command_loading) {
                                      $$renderer8.push("<!--[-->");
                                      Command_loading($$renderer8, {
                                        children: ($$renderer9) => {
                                          $$renderer9.push(`<!---->Loading…`);
                                        },
                                        $$slots: { default: true }
                                      });
                                      $$renderer8.push("<!--]-->");
                                    } else {
                                      $$renderer8.push("<!--[!-->");
                                      $$renderer8.push("<!--]-->");
                                    }
                                  } else {
                                    $$renderer8.push("<!--[-1-->");
                                    if (Command_empty) {
                                      $$renderer8.push("<!--[-->");
                                      Command_empty($$renderer8, {
                                        children: ($$renderer9) => {
                                          $$renderer9.push(`<!---->No sections found.`);
                                        },
                                        $$slots: { default: true }
                                      });
                                      $$renderer8.push("<!--]-->");
                                    } else {
                                      $$renderer8.push("<!--[!-->");
                                      $$renderer8.push("<!--]-->");
                                    }
                                    $$renderer8.push(` `);
                                    if (Command_group) {
                                      $$renderer8.push("<!--[-->");
                                      Command_group($$renderer8, {
                                        heading: "Gallery sections",
                                        children: ($$renderer9) => {
                                          $$renderer9.push(`<!--[-->`);
                                          const each_array_4 = ensure_array_like(commandItems);
                                          for (let $$index_4 = 0, $$length = each_array_4.length; $$index_4 < $$length; $$index_4++) {
                                            let item = each_array_4[$$index_4];
                                            if (Command_item) {
                                              $$renderer9.push("<!--[-->");
                                              Command_item($$renderer9, {
                                                value: item,
                                                children: ($$renderer10) => {
                                                  $$renderer10.push(`<!---->${escape_html(item)} `);
                                                  if (Command_shortcut) {
                                                    $$renderer10.push("<!--[-->");
                                                    Command_shortcut($$renderer10, {
                                                      children: ($$renderer11) => {
                                                        $$renderer11.push(`<!---->⌘K`);
                                                      },
                                                      $$slots: { default: true }
                                                    });
                                                    $$renderer10.push("<!--]-->");
                                                  } else {
                                                    $$renderer10.push("<!--[!-->");
                                                    $$renderer10.push("<!--]-->");
                                                  }
                                                },
                                                $$slots: { default: true }
                                              });
                                              $$renderer9.push("<!--]-->");
                                            } else {
                                              $$renderer9.push("<!--[!-->");
                                              $$renderer9.push("<!--]-->");
                                            }
                                          }
                                          $$renderer9.push(`<!--]-->`);
                                        },
                                        $$slots: { default: true }
                                      });
                                      $$renderer8.push("<!--]-->");
                                    } else {
                                      $$renderer8.push("<!--[!-->");
                                      $$renderer8.push("<!--]-->");
                                    }
                                  }
                                  $$renderer8.push(`<!--]-->`);
                                },
                                $$slots: { default: true }
                              });
                              $$renderer7.push("<!--]-->");
                            } else {
                              $$renderer7.push("<!--[!-->");
                              $$renderer7.push("<!--]-->");
                            }
                          },
                          $$slots: { default: true }
                        });
                        $$renderer6.push("<!--]-->");
                      } else {
                        $$renderer6.push("<!--[!-->");
                        $$renderer6.push("<!--]-->");
                      }
                      $$renderer6.push(`</div> `);
                      if (Command_dialog) {
                        $$renderer6.push("<!--[-->");
                        Command_dialog($$renderer6, {
                          title: "UI kit command palette",
                          get open() {
                            return commandDialogOpen;
                          },
                          set open($$value) {
                            commandDialogOpen = $$value;
                            $$settled = false;
                          },
                          children: ($$renderer7) => {
                            if (Command_input) {
                              $$renderer7.push("<!--[-->");
                              Command_input($$renderer7, { placeholder: "Type a section name…" });
                              $$renderer7.push("<!--]-->");
                            } else {
                              $$renderer7.push("<!--[!-->");
                              $$renderer7.push("<!--]-->");
                            }
                            $$renderer7.push(` `);
                            if (Command_list) {
                              $$renderer7.push("<!--[-->");
                              Command_list($$renderer7, {
                                children: ($$renderer8) => {
                                  if (Command_empty) {
                                    $$renderer8.push("<!--[-->");
                                    Command_empty($$renderer8, {
                                      children: ($$renderer9) => {
                                        $$renderer9.push(`<!---->No sections found.`);
                                      },
                                      $$slots: { default: true }
                                    });
                                    $$renderer8.push("<!--]-->");
                                  } else {
                                    $$renderer8.push("<!--[!-->");
                                    $$renderer8.push("<!--]-->");
                                  }
                                  $$renderer8.push(` `);
                                  if (Command_group) {
                                    $$renderer8.push("<!--[-->");
                                    Command_group($$renderer8, {
                                      heading: "Gallery sections",
                                      children: ($$renderer9) => {
                                        $$renderer9.push(`<!--[-->`);
                                        const each_array_5 = ensure_array_like(commandItems);
                                        for (let $$index_5 = 0, $$length = each_array_5.length; $$index_5 < $$length; $$index_5++) {
                                          let item = each_array_5[$$index_5];
                                          if (Command_item) {
                                            $$renderer9.push("<!--[-->");
                                            Command_item($$renderer9, {
                                              value: item,
                                              onSelect: () => commandDialogOpen = false,
                                              children: ($$renderer10) => {
                                                $$renderer10.push(`<!---->${escape_html(item)}`);
                                              },
                                              $$slots: { default: true }
                                            });
                                            $$renderer9.push("<!--]-->");
                                          } else {
                                            $$renderer9.push("<!--[!-->");
                                            $$renderer9.push("<!--]-->");
                                          }
                                        }
                                        $$renderer9.push(`<!--]-->`);
                                      },
                                      $$slots: { default: true }
                                    });
                                    $$renderer8.push("<!--]-->");
                                  } else {
                                    $$renderer8.push("<!--[!-->");
                                    $$renderer8.push("<!--]-->");
                                  }
                                },
                                $$slots: { default: true }
                              });
                              $$renderer7.push("<!--]-->");
                            } else {
                              $$renderer7.push("<!--[!-->");
                              $$renderer7.push("<!--]-->");
                            }
                          },
                          $$slots: { default: true }
                        });
                        $$renderer6.push("<!--]-->");
                      } else {
                        $$renderer6.push("<!--[!-->");
                        $$renderer6.push("<!--]-->");
                      }
                    },
                    $$slots: { default: true }
                  });
                  $$renderer5.push("<!--]-->");
                } else {
                  $$renderer5.push("<!--[!-->");
                  $$renderer5.push("<!--]-->");
                }
              },
              $$slots: { default: true }
            });
            $$renderer4.push("<!--]-->");
          } else {
            $$renderer4.push("<!--[!-->");
            $$renderer4.push("<!--]-->");
          }
          $$renderer4.push(`</section> <section id="file-upload" class="space-y-4"><h2 class="text-foreground text-xl font-semibold">File upload</h2> `);
          if (Card) {
            $$renderer4.push("<!--[-->");
            Card($$renderer4, {
              children: ($$renderer5) => {
                if (Card_content) {
                  $$renderer5.push("<!--[-->");
                  Card_content($$renderer5, {
                    class: "grid gap-6 sm:grid-cols-2",
                    children: ($$renderer6) => {
                      $$renderer6.push(`<div class="space-y-2">`);
                      Label($$renderer6, {
                        children: ($$renderer7) => {
                          $$renderer7.push(`<!---->Default`);
                        },
                        $$slots: { default: true }
                      });
                      $$renderer6.push(`<!----> `);
                      File_upload($$renderer6, {
                        label: "Upload applicant list",
                        sublabel: "Drag and drop or click to browse",
                        accept: ".csv,.xlsx",
                        maxSize: "5mb",
                        onfiles: () => info("Demo: file selected (no upload occurs)."),
                        get files() {
                          return uploadFiles;
                        },
                        set files($$value) {
                          uploadFiles = $$value;
                          $$settled = false;
                        },
                        get error() {
                          return uploadError;
                        },
                        set error($$value) {
                          uploadError = $$value;
                          $$settled = false;
                        }
                      });
                      $$renderer6.push(`<!----> `);
                      if (uploadFiles.length > 0) {
                        $$renderer6.push("<!--[0-->");
                        $$renderer6.push(`<p class="text-muted-foreground text-sm">Selected: ${escape_html(uploadFiles.map((f) => f.name).join(", "))}</p>`);
                      } else {
                        $$renderer6.push("<!--[-1-->");
                      }
                      $$renderer6.push(`<!--]--></div> <div class="space-y-2">`);
                      Label($$renderer6, {
                        children: ($$renderer7) => {
                          $$renderer7.push(`<!---->Disabled`);
                        },
                        $$slots: { default: true }
                      });
                      $$renderer6.push(`<!----> `);
                      File_upload($$renderer6, {
                        label: "Upload disabled",
                        sublabel: "Not accepting files right now",
                        disabled: true
                      });
                      $$renderer6.push(`<!----></div>`);
                    },
                    $$slots: { default: true }
                  });
                  $$renderer5.push("<!--]-->");
                } else {
                  $$renderer5.push("<!--[!-->");
                  $$renderer5.push("<!--]-->");
                }
              },
              $$slots: { default: true }
            });
            $$renderer4.push("<!--]-->");
          } else {
            $$renderer4.push("<!--[!-->");
            $$renderer4.push("<!--]-->");
          }
          $$renderer4.push(`</section> <section id="separator" class="space-y-4"><h2 class="text-foreground text-xl font-semibold">Separator</h2> `);
          if (Card) {
            $$renderer4.push("<!--[-->");
            Card($$renderer4, {
              children: ($$renderer5) => {
                if (Card_content) {
                  $$renderer5.push("<!--[-->");
                  Card_content($$renderer5, {
                    class: "space-y-4",
                    children: ($$renderer6) => {
                      $$renderer6.push(`<p class="text-muted-foreground text-sm">Above the horizontal separator.</p> `);
                      Separator($$renderer6, {});
                      $$renderer6.push(`<!----> <p class="text-muted-foreground text-sm">Below the horizontal separator.</p> <div class="flex h-8 items-center gap-4"><span class="text-sm">Left</span> `);
                      Separator($$renderer6, { orientation: "vertical" });
                      $$renderer6.push(`<!----> <span class="text-sm">Right</span></div>`);
                    },
                    $$slots: { default: true }
                  });
                  $$renderer5.push("<!--]-->");
                } else {
                  $$renderer5.push("<!--[!-->");
                  $$renderer5.push("<!--]-->");
                }
              },
              $$slots: { default: true }
            });
            $$renderer4.push("<!--]-->");
          } else {
            $$renderer4.push("<!--[!-->");
            $$renderer4.push("<!--]-->");
          }
          $$renderer4.push(`</section> <section id="stat-kpi" class="space-y-4"><h2 class="text-foreground text-xl font-semibold">StatCard &amp; KpiCard</h2> <div class="grid gap-4 sm:grid-cols-3">`);
          StatCard($$renderer4, {
            label: "Applicants (synthetic)",
            value: "1,284",
            icon: Users,
            trend: "up",
            trendLabel: "+12% vs last cycle"
          });
          $$renderer4.push(`<!----> `);
          StatCard($$renderer4, {
            label: "Sessions scheduled",
            value: "42",
            icon: Clipboard_check,
            trend: "down",
            trendLabel: "-3 vs last week"
          });
          $$renderer4.push(`<!----> `);
          StatCard($$renderer4, {
            label: "Avg. score",
            value: "76.4",
            icon: Trending_up,
            trend: "neutral",
            trendLabel: "No change",
            href: "#stat-kpi"
          });
          $$renderer4.push(`<!----></div> <div class="grid gap-4 sm:grid-cols-3">`);
          KpiCard($$renderer4, {
            label: "Capacity used",
            value: "82%",
            status: "ok",
            delta: 4,
            deltaLabel: "vs last cycle",
            secondaryItems: [{ label: "Rooms", value: 12 }]
          });
          $$renderer4.push(`<!----> `);
          KpiCard($$renderer4, {
            label: "Pending grading",
            value: "16",
            status: "warn",
            delta: 2,
            deltaLabel: "new today"
          });
          $$renderer4.push(`<!----> `);
          KpiCard($$renderer4, {
            label: "Overdue release",
            value: "3",
            status: "critical",
            delta: -1,
            deltaLabel: "vs yesterday"
          });
          $$renderer4.push(`<!----></div></section> <section id="view-mode" class="space-y-4"><h2 class="text-foreground text-xl font-semibold">View mode toggle &amp; switchable list</h2> `);
          if (Card) {
            $$renderer4.push("<!--[-->");
            Card($$renderer4, {
              children: ($$renderer5) => {
                if (Card_content) {
                  $$renderer5.push("<!--[-->");
                  Card_content($$renderer5, {
                    children: ($$renderer6) => {
                      {
                        let table = function($$renderer7) {
                          if (Table) {
                            $$renderer7.push("<!--[-->");
                            Table($$renderer7, {
                              children: ($$renderer8) => {
                                if (Table_header) {
                                  $$renderer8.push("<!--[-->");
                                  Table_header($$renderer8, {
                                    children: ($$renderer9) => {
                                      if (Table_row) {
                                        $$renderer9.push("<!--[-->");
                                        Table_row($$renderer9, {
                                          children: ($$renderer10) => {
                                            if (Table_head) {
                                              $$renderer10.push("<!--[-->");
                                              Table_head($$renderer10, {
                                                children: ($$renderer11) => {
                                                  $$renderer11.push(`<!---->Room`);
                                                },
                                                $$slots: { default: true }
                                              });
                                              $$renderer10.push("<!--]-->");
                                            } else {
                                              $$renderer10.push("<!--[!-->");
                                              $$renderer10.push("<!--]-->");
                                            }
                                          },
                                          $$slots: { default: true }
                                        });
                                        $$renderer9.push("<!--]-->");
                                      } else {
                                        $$renderer9.push("<!--[!-->");
                                        $$renderer9.push("<!--]-->");
                                      }
                                    },
                                    $$slots: { default: true }
                                  });
                                  $$renderer8.push("<!--]-->");
                                } else {
                                  $$renderer8.push("<!--[!-->");
                                  $$renderer8.push("<!--]-->");
                                }
                                $$renderer8.push(` `);
                                if (Table_body) {
                                  $$renderer8.push("<!--[-->");
                                  Table_body($$renderer8, {
                                    children: ($$renderer9) => {
                                      $$renderer9.push(`<!--[-->`);
                                      const each_array_6 = ensure_array_like(listRows);
                                      for (let $$index_6 = 0, $$length = each_array_6.length; $$index_6 < $$length; $$index_6++) {
                                        let row = each_array_6[$$index_6];
                                        if (Table_row) {
                                          $$renderer9.push("<!--[-->");
                                          Table_row($$renderer9, {
                                            children: ($$renderer10) => {
                                              if (Table_cell) {
                                                $$renderer10.push("<!--[-->");
                                                Table_cell($$renderer10, {
                                                  children: ($$renderer11) => {
                                                    $$renderer11.push(`<!---->${escape_html(row.label)}`);
                                                  },
                                                  $$slots: { default: true }
                                                });
                                                $$renderer10.push("<!--]-->");
                                              } else {
                                                $$renderer10.push("<!--[!-->");
                                                $$renderer10.push("<!--]-->");
                                              }
                                            },
                                            $$slots: { default: true }
                                          });
                                          $$renderer9.push("<!--]-->");
                                        } else {
                                          $$renderer9.push("<!--[!-->");
                                          $$renderer9.push("<!--]-->");
                                        }
                                      }
                                      $$renderer9.push(`<!--]-->`);
                                    },
                                    $$slots: { default: true }
                                  });
                                  $$renderer8.push("<!--]-->");
                                } else {
                                  $$renderer8.push("<!--[!-->");
                                  $$renderer8.push("<!--]-->");
                                }
                              },
                              $$slots: { default: true }
                            });
                            $$renderer7.push("<!--]-->");
                          } else {
                            $$renderer7.push("<!--[!-->");
                            $$renderer7.push("<!--]-->");
                          }
                        }, cards = function($$renderer7) {
                          $$renderer7.push(`<div class="grid gap-3 sm:grid-cols-2"><!--[-->`);
                          const each_array_7 = ensure_array_like(listRows);
                          for (let $$index_7 = 0, $$length = each_array_7.length; $$index_7 < $$length; $$index_7++) {
                            let row = each_array_7[$$index_7];
                            if (Card) {
                              $$renderer7.push("<!--[-->");
                              Card($$renderer7, {
                                children: ($$renderer8) => {
                                  if (Card_content) {
                                    $$renderer8.push("<!--[-->");
                                    Card_content($$renderer8, {
                                      class: "flex items-center gap-2",
                                      children: ($$renderer9) => {
                                        Folder_open($$renderer9, { class: "text-muted-foreground size-4" });
                                        $$renderer9.push(`<!----> ${escape_html(row.label)}`);
                                      },
                                      $$slots: { default: true }
                                    });
                                    $$renderer8.push("<!--]-->");
                                  } else {
                                    $$renderer8.push("<!--[!-->");
                                    $$renderer8.push("<!--]-->");
                                  }
                                },
                                $$slots: { default: true }
                              });
                              $$renderer7.push("<!--]-->");
                            } else {
                              $$renderer7.push("<!--[!-->");
                              $$renderer7.push("<!--]-->");
                            }
                          }
                          $$renderer7.push(`<!--]--></div>`);
                        };
                        SwitchableListView($$renderer6, {
                          get viewMode() {
                            return viewMode;
                          },
                          set viewMode($$value) {
                            viewMode = $$value;
                            $$settled = false;
                          },
                          table,
                          cards,
                          $$slots: { table: true, cards: true }
                        });
                      }
                    },
                    $$slots: { default: true }
                  });
                  $$renderer5.push("<!--]-->");
                } else {
                  $$renderer5.push("<!--[!-->");
                  $$renderer5.push("<!--]-->");
                }
              },
              $$slots: { default: true }
            });
            $$renderer4.push("<!--]-->");
          } else {
            $$renderer4.push("<!--[!-->");
            $$renderer4.push("<!--]-->");
          }
          $$renderer4.push(`</section> <section id="password" class="space-y-4"><h2 class="text-foreground text-xl font-semibold">Password strength input</h2> `);
          if (Card) {
            $$renderer4.push("<!--[-->");
            Card($$renderer4, {
              children: ($$renderer5) => {
                if (Card_content) {
                  $$renderer5.push("<!--[-->");
                  Card_content($$renderer5, {
                    class: "max-w-md",
                    children: ($$renderer6) => {
                      PasswordStrengthInput($$renderer6, {
                        get password() {
                          return demoPassword;
                        },
                        set password($$value) {
                          demoPassword = $$value;
                          $$settled = false;
                        },
                        get passwordConfirmation() {
                          return demoPasswordConfirmation;
                        },
                        set passwordConfirmation($$value) {
                          demoPasswordConfirmation = $$value;
                          $$settled = false;
                        }
                      });
                    },
                    $$slots: { default: true }
                  });
                  $$renderer5.push("<!--]-->");
                } else {
                  $$renderer5.push("<!--[!-->");
                  $$renderer5.push("<!--]-->");
                }
              },
              $$slots: { default: true }
            });
            $$renderer4.push("<!--]-->");
          } else {
            $$renderer4.push("<!--[!-->");
            $$renderer4.push("<!--]-->");
          }
          $$renderer4.push(`</section> <section id="toasts" class="space-y-4"><h2 class="text-foreground text-xl font-semibold">Toast pattern</h2> `);
          if (Card) {
            $$renderer4.push("<!--[-->");
            Card($$renderer4, {
              children: ($$renderer5) => {
                if (Card_content) {
                  $$renderer5.push("<!--[-->");
                  Card_content($$renderer5, {
                    class: "flex flex-wrap gap-3",
                    children: ($$renderer6) => {
                      Button($$renderer6, {
                        variant: "outline",
                        onclick: () => success("Demo: synthetic success toast."),
                        children: ($$renderer7) => {
                          $$renderer7.push(`<!---->Trigger success`);
                        },
                        $$slots: { default: true }
                      });
                      $$renderer6.push(`<!----> `);
                      Button($$renderer6, {
                        variant: "outline",
                        onclick: () => error("Demo: synthetic error toast."),
                        children: ($$renderer7) => {
                          $$renderer7.push(`<!---->Trigger error`);
                        },
                        $$slots: { default: true }
                      });
                      $$renderer6.push(`<!----> `);
                      Button($$renderer6, {
                        variant: "outline",
                        onclick: () => info("Demo: synthetic info toast."),
                        children: ($$renderer7) => {
                          $$renderer7.push(`<!---->Trigger info`);
                        },
                        $$slots: { default: true }
                      });
                      $$renderer6.push(`<!----> <p class="text-muted-foreground w-full text-sm">The rendering surface (<code>&lt;ToastManager /></code>) is already mounted once in <code>AuthenticatedLayout</code>; these buttons just
                        push onto the shared store.</p>`);
                    },
                    $$slots: { default: true }
                  });
                  $$renderer5.push("<!--]-->");
                } else {
                  $$renderer5.push("<!--[!-->");
                  $$renderer5.push("<!--]-->");
                }
              },
              $$slots: { default: true }
            });
            $$renderer4.push("<!--]-->");
          } else {
            $$renderer4.push("<!--[!-->");
            $$renderer4.push("<!--]-->");
          }
          $$renderer4.push(`</section> <section id="guide" class="space-y-4"><h2 class="text-foreground text-xl font-semibold">Guide panels</h2> `);
          GuidePanel($$renderer4, {
            title: "How to read this gallery",
            defaultOpen: true,
            children: ($$renderer5) => {
              GuideSection($$renderer5, {
                title: "What this page is",
                children: ($$renderer6) => {
                  $$renderer6.push(`<p>Interactive catalogue of staff primitives and
                        composites. Synthetic data only — nothing here calls a
                        domain API or mutates production state.</p>`);
                }
              });
              $$renderer5.push(`<!----> <div class="grid gap-3 sm:grid-cols-3">`);
              GuideNote($$renderer5, {
                variant: "info",
                title: "Informational",
                children: ($$renderer6) => {
                  $$renderer6.push(`<!---->Quiet context for operators — soft tinted surface, no
                        left stripe.`);
                }
              });
              $$renderer5.push(`<!----> `);
              GuideNote($$renderer5, {
                variant: "warning",
                title: "Watch this",
                children: ($$renderer6) => {
                  $$renderer6.push(`<!---->Use for caution that is not yet a blocking error.`);
                }
              });
              $$renderer5.push(`<!----> `);
              GuideNote($$renderer5, {
                variant: "tip",
                title: "Tip",
                children: ($$renderer6) => {
                  $$renderer6.push(`<!---->Prefer kit pieces before inventing one-off chrome.`);
                }
              });
              $$renderer5.push(`<!----></div>`);
            }
          });
          $$renderer4.push(`<!----></section>  <section id="management-list" class="space-y-4"><h2 class="text-foreground text-xl font-semibold">Management list page</h2> <p class="text-muted-foreground max-w-3xl text-sm">Full composed pattern from legacy Index pages (Applications,
                Users, Exam Scheduling, Release): page header actions,
                responsive filter toolbar, active chips, bulk bar, mode banner,
                and SwitchableListView table/cards with selection. Resize below <code>md</code> to exercise the Filters drawer. Synthetic data only — no Inertia
                visits.</p> <div class="border-border bg-card/40 space-y-4 rounded-xl border p-4 sm:p-6">`);
          {
            let actions = function($$renderer5) {
              Button($$renderer5, {
                variant: "outline",
                class: "min-h-[44px] gap-2",
                children: ($$renderer6) => {
                  Upload($$renderer6, { class: "h-4 w-4" });
                  $$renderer6.push(`<!----> <span class="hidden sm:inline">Import</span>`);
                },
                $$slots: { default: true }
              });
              $$renderer5.push(`<!----> `);
              Button($$renderer5, {
                variant: "outline",
                class: "min-h-[44px] gap-2",
                children: ($$renderer6) => {
                  Download($$renderer6, { class: "h-4 w-4" });
                  $$renderer6.push(`<!----> <span class="hidden sm:inline">Export</span>`);
                },
                $$slots: { default: true }
              });
              $$renderer5.push(`<!----> `);
              Button($$renderer5, {
                class: "min-h-[44px] gap-2",
                children: ($$renderer6) => {
                  Plus($$renderer6, { class: "h-4 w-4" });
                  $$renderer6.push(`<!----> <span class="hidden sm:inline">Create</span>`);
                },
                $$slots: { default: true }
              });
              $$renderer5.push(`<!---->`);
            };
            PageHeaderBar($$renderer4, {
              description: "View and manage synthetic applicants (gallery demo)",
              actions
            });
          }
          $$renderer4.push(`<!----> `);
          ModeBanner($$renderer4, {
            variant: "primary",
            title: "Online mode:",
            children: ($$renderer5) => {
              $$renderer5.push(`<!---->Releasing would notify applicants — demo only, no side
                    effects.`);
            }
          });
          $$renderer4.push(`<!----> `);
          {
            let desktopFilters = function($$renderer5) {
              $$renderer5.push(`<div><label class="text-muted-foreground mb-1 block text-xs font-medium" for="mgmt-status-desk">Status</label> `);
              if (Select) {
                $$renderer5.push("<!--[-->");
                Select($$renderer5, {
                  type: "single",
                  get value() {
                    return mgmtStatus;
                  },
                  set value($$value) {
                    mgmtStatus = $$value;
                    $$settled = false;
                  },
                  children: ($$renderer6) => {
                    if (Select_trigger) {
                      $$renderer6.push("<!--[-->");
                      Select_trigger($$renderer6, {
                        id: "mgmt-status-desk",
                        class: "min-h-[40px] w-[150px]",
                        children: ($$renderer7) => {
                          $$renderer7.push(`<!---->${escape_html(mgmtStatusLabel())}`);
                        },
                        $$slots: { default: true }
                      });
                      $$renderer6.push("<!--]-->");
                    } else {
                      $$renderer6.push("<!--[!-->");
                      $$renderer6.push("<!--]-->");
                    }
                    $$renderer6.push(` `);
                    if (Select_content) {
                      $$renderer6.push("<!--[-->");
                      Select_content($$renderer6, {
                        children: ($$renderer7) => {
                          $$renderer7.push(`<!--[-->`);
                          const each_array_8 = ensure_array_like(mgmtStatusOptions);
                          for (let $$index_8 = 0, $$length = each_array_8.length; $$index_8 < $$length; $$index_8++) {
                            let o = each_array_8[$$index_8];
                            if (Select_item) {
                              $$renderer7.push("<!--[-->");
                              Select_item($$renderer7, {
                                value: o.value,
                                label: o.label,
                                children: ($$renderer8) => {
                                  $$renderer8.push(`<!---->${escape_html(o.label)}`);
                                },
                                $$slots: { default: true }
                              });
                              $$renderer7.push("<!--]-->");
                            } else {
                              $$renderer7.push("<!--[!-->");
                              $$renderer7.push("<!--]-->");
                            }
                          }
                          $$renderer7.push(`<!--]-->`);
                        },
                        $$slots: { default: true }
                      });
                      $$renderer6.push("<!--]-->");
                    } else {
                      $$renderer6.push("<!--[!-->");
                      $$renderer6.push("<!--]-->");
                    }
                  },
                  $$slots: { default: true }
                });
                $$renderer5.push("<!--]-->");
              } else {
                $$renderer5.push("<!--[!-->");
                $$renderer5.push("<!--]-->");
              }
              $$renderer5.push(`</div> <div><label class="text-muted-foreground mb-1 block text-xs font-medium" for="mgmt-season-desk">Season</label> `);
              if (Select) {
                $$renderer5.push("<!--[-->");
                Select($$renderer5, {
                  type: "single",
                  get value() {
                    return mgmtSeason;
                  },
                  set value($$value) {
                    mgmtSeason = $$value;
                    $$settled = false;
                  },
                  children: ($$renderer6) => {
                    if (Select_trigger) {
                      $$renderer6.push("<!--[-->");
                      Select_trigger($$renderer6, {
                        id: "mgmt-season-desk",
                        class: "min-h-[40px] w-[200px]",
                        children: ($$renderer7) => {
                          $$renderer7.push(`<!---->${escape_html(mgmtSeasonLabel())}`);
                        },
                        $$slots: { default: true }
                      });
                      $$renderer6.push("<!--]-->");
                    } else {
                      $$renderer6.push("<!--[!-->");
                      $$renderer6.push("<!--]-->");
                    }
                    $$renderer6.push(` `);
                    if (Select_content) {
                      $$renderer6.push("<!--[-->");
                      Select_content($$renderer6, {
                        children: ($$renderer7) => {
                          $$renderer7.push(`<!--[-->`);
                          const each_array_9 = ensure_array_like(mgmtSeasonOptions);
                          for (let $$index_9 = 0, $$length = each_array_9.length; $$index_9 < $$length; $$index_9++) {
                            let o = each_array_9[$$index_9];
                            if (Select_item) {
                              $$renderer7.push("<!--[-->");
                              Select_item($$renderer7, {
                                value: o.value,
                                label: o.label,
                                children: ($$renderer8) => {
                                  $$renderer8.push(`<!---->${escape_html(o.label)}`);
                                },
                                $$slots: { default: true }
                              });
                              $$renderer7.push("<!--]-->");
                            } else {
                              $$renderer7.push("<!--[!-->");
                              $$renderer7.push("<!--]-->");
                            }
                          }
                          $$renderer7.push(`<!--]-->`);
                        },
                        $$slots: { default: true }
                      });
                      $$renderer6.push("<!--]-->");
                    } else {
                      $$renderer6.push("<!--[!-->");
                      $$renderer6.push("<!--]-->");
                    }
                  },
                  $$slots: { default: true }
                });
                $$renderer5.push("<!--]-->");
              } else {
                $$renderer5.push("<!--[!-->");
                $$renderer5.push("<!--]-->");
              }
              $$renderer5.push(`</div> <div><label class="text-muted-foreground mb-1 block text-xs font-medium" for="mgmt-from-desk">From</label> `);
              Input($$renderer5, {
                id: "mgmt-from-desk",
                type: "date",
                class: "min-h-[40px] max-w-[160px]",
                get value() {
                  return mgmtDateFrom;
                },
                set value($$value) {
                  mgmtDateFrom = $$value;
                  $$settled = false;
                }
              });
              $$renderer5.push(`<!----></div> <div><label class="text-muted-foreground mb-1 block text-xs font-medium" for="mgmt-to-desk">To</label> `);
              Input($$renderer5, {
                id: "mgmt-to-desk",
                type: "date",
                class: "min-h-[40px] max-w-[160px]",
                get value() {
                  return mgmtDateTo;
                },
                set value($$value) {
                  mgmtDateTo = $$value;
                  $$settled = false;
                }
              });
              $$renderer5.push(`<!----></div>`);
            }, mobileFilters = function($$renderer5) {
              $$renderer5.push(`<div><label class="mb-1 block text-sm font-medium" for="mgmt-status-mob">Status</label> `);
              if (Select) {
                $$renderer5.push("<!--[-->");
                Select($$renderer5, {
                  type: "single",
                  get value() {
                    return mgmtStatus;
                  },
                  set value($$value) {
                    mgmtStatus = $$value;
                    $$settled = false;
                  },
                  children: ($$renderer6) => {
                    if (Select_trigger) {
                      $$renderer6.push("<!--[-->");
                      Select_trigger($$renderer6, {
                        id: "mgmt-status-mob",
                        class: "min-h-[44px] w-full",
                        children: ($$renderer7) => {
                          $$renderer7.push(`<!---->${escape_html(mgmtStatusLabel())}`);
                        },
                        $$slots: { default: true }
                      });
                      $$renderer6.push("<!--]-->");
                    } else {
                      $$renderer6.push("<!--[!-->");
                      $$renderer6.push("<!--]-->");
                    }
                    $$renderer6.push(` `);
                    if (Select_content) {
                      $$renderer6.push("<!--[-->");
                      Select_content($$renderer6, {
                        children: ($$renderer7) => {
                          $$renderer7.push(`<!--[-->`);
                          const each_array_10 = ensure_array_like(mgmtStatusOptions);
                          for (let $$index_10 = 0, $$length = each_array_10.length; $$index_10 < $$length; $$index_10++) {
                            let o = each_array_10[$$index_10];
                            if (Select_item) {
                              $$renderer7.push("<!--[-->");
                              Select_item($$renderer7, {
                                value: o.value,
                                label: o.label,
                                children: ($$renderer8) => {
                                  $$renderer8.push(`<!---->${escape_html(o.label)}`);
                                },
                                $$slots: { default: true }
                              });
                              $$renderer7.push("<!--]-->");
                            } else {
                              $$renderer7.push("<!--[!-->");
                              $$renderer7.push("<!--]-->");
                            }
                          }
                          $$renderer7.push(`<!--]-->`);
                        },
                        $$slots: { default: true }
                      });
                      $$renderer6.push("<!--]-->");
                    } else {
                      $$renderer6.push("<!--[!-->");
                      $$renderer6.push("<!--]-->");
                    }
                  },
                  $$slots: { default: true }
                });
                $$renderer5.push("<!--]-->");
              } else {
                $$renderer5.push("<!--[!-->");
                $$renderer5.push("<!--]-->");
              }
              $$renderer5.push(`</div> <div><label class="mb-1 block text-sm font-medium" for="mgmt-season-mob">Season</label> `);
              if (Select) {
                $$renderer5.push("<!--[-->");
                Select($$renderer5, {
                  type: "single",
                  get value() {
                    return mgmtSeason;
                  },
                  set value($$value) {
                    mgmtSeason = $$value;
                    $$settled = false;
                  },
                  children: ($$renderer6) => {
                    if (Select_trigger) {
                      $$renderer6.push("<!--[-->");
                      Select_trigger($$renderer6, {
                        id: "mgmt-season-mob",
                        class: "min-h-[44px] w-full",
                        children: ($$renderer7) => {
                          $$renderer7.push(`<!---->${escape_html(mgmtSeasonLabel())}`);
                        },
                        $$slots: { default: true }
                      });
                      $$renderer6.push("<!--]-->");
                    } else {
                      $$renderer6.push("<!--[!-->");
                      $$renderer6.push("<!--]-->");
                    }
                    $$renderer6.push(` `);
                    if (Select_content) {
                      $$renderer6.push("<!--[-->");
                      Select_content($$renderer6, {
                        children: ($$renderer7) => {
                          $$renderer7.push(`<!--[-->`);
                          const each_array_11 = ensure_array_like(mgmtSeasonOptions);
                          for (let $$index_11 = 0, $$length = each_array_11.length; $$index_11 < $$length; $$index_11++) {
                            let o = each_array_11[$$index_11];
                            if (Select_item) {
                              $$renderer7.push("<!--[-->");
                              Select_item($$renderer7, {
                                value: o.value,
                                label: o.label,
                                children: ($$renderer8) => {
                                  $$renderer8.push(`<!---->${escape_html(o.label)}`);
                                },
                                $$slots: { default: true }
                              });
                              $$renderer7.push("<!--]-->");
                            } else {
                              $$renderer7.push("<!--[!-->");
                              $$renderer7.push("<!--]-->");
                            }
                          }
                          $$renderer7.push(`<!--]-->`);
                        },
                        $$slots: { default: true }
                      });
                      $$renderer6.push("<!--]-->");
                    } else {
                      $$renderer6.push("<!--[!-->");
                      $$renderer6.push("<!--]-->");
                    }
                  },
                  $$slots: { default: true }
                });
                $$renderer5.push("<!--]-->");
              } else {
                $$renderer5.push("<!--[!-->");
                $$renderer5.push("<!--]-->");
              }
              $$renderer5.push(`</div> <div><span class="mb-1 block text-sm font-medium">Date range</span> <div class="grid grid-cols-2 gap-2">`);
              Input($$renderer5, {
                type: "date",
                class: "min-h-[44px]",
                "aria-label": "From date",
                get value() {
                  return mgmtDateFrom;
                },
                set value($$value) {
                  mgmtDateFrom = $$value;
                  $$settled = false;
                }
              });
              $$renderer5.push(`<!----> `);
              Input($$renderer5, {
                type: "date",
                class: "min-h-[44px]",
                "aria-label": "To date",
                get value() {
                  return mgmtDateTo;
                },
                set value($$value) {
                  mgmtDateTo = $$value;
                  $$settled = false;
                }
              });
              $$renderer5.push(`<!----></div></div>`);
            };
            FilterToolbar($$renderer4, {
              searchPlaceholder: "Search applicants…",
              searchLabel: "Search",
              get search() {
                return mgmtSearch;
              },
              set search($$value) {
                mgmtSearch = $$value;
                $$settled = false;
              },
              desktopFilters,
              mobileFilters,
              $$slots: { desktopFilters: true, mobileFilters: true }
            });
          }
          $$renderer4.push(`<!----> `);
          ActiveFilterChips($$renderer4, {
            chips: mgmtChips(),
            onRemove: mgmtRemoveChip,
            onClearAll: mgmtClearFilters
          });
          $$renderer4.push(`<!----> `);
          {
            let actions = function($$renderer5) {
              Button($$renderer5, {
                size: "sm",
                class: "gap-1.5 bg-green-600 text-white hover:bg-green-700",
                onclick: () => success("Demo: accept selected."),
                children: ($$renderer6) => {
                  Circle_check_big($$renderer6, { class: "h-3.5 w-3.5" });
                  $$renderer6.push(`<!----> Accept`);
                },
                $$slots: { default: true }
              });
              $$renderer5.push(`<!----> `);
              Button($$renderer5, {
                size: "sm",
                variant: "destructive",
                class: "gap-1.5",
                onclick: () => error("Demo: dismiss selected."),
                children: ($$renderer6) => {
                  Circle_x($$renderer6, { class: "h-3.5 w-3.5" });
                  $$renderer6.push(`<!----> Dismiss`);
                },
                $$slots: { default: true }
              });
              $$renderer5.push(`<!----> `);
              Button($$renderer5, {
                size: "sm",
                variant: "outline",
                class: "gap-1.5",
                onclick: () => info("Demo: print slips."),
                children: ($$renderer6) => {
                  Printer($$renderer6, { class: "h-3.5 w-3.5" });
                  $$renderer6.push(`<!----> Print`);
                },
                $$slots: { default: true }
              });
              $$renderer5.push(`<!---->`);
            };
            BulkActionBar($$renderer4, {
              count: mgmtSelected.length,
              hint: "Bulk actions apply to the current selection only (demo).",
              onClear: () => mgmtSelected = [],
              actions
            });
          }
          $$renderer4.push(`<!----> `);
          {
            let table = function($$renderer5) {
              $$renderer5.push(`<div class="border-border overflow-x-auto rounded-lg border">`);
              if (Table) {
                $$renderer5.push("<!--[-->");
                Table($$renderer5, {
                  class: "w-full min-w-[640px] text-sm",
                  children: ($$renderer6) => {
                    if (Table_header) {
                      $$renderer6.push("<!--[-->");
                      Table_header($$renderer6, {
                        class: "bg-muted/50",
                        children: ($$renderer7) => {
                          if (Table_row) {
                            $$renderer7.push("<!--[-->");
                            Table_row($$renderer7, {
                              children: ($$renderer8) => {
                                if (Table_head) {
                                  $$renderer8.push("<!--[-->");
                                  Table_head($$renderer8, {
                                    class: "w-10 px-2 py-3",
                                    children: ($$renderer9) => {
                                      Checkbox($$renderer9, {
                                        checked: mgmtAllSelected(),
                                        indeterminate: mgmtSomeSelected(),
                                        onCheckedChange: (v) => mgmtToggleAll(!!v),
                                        "aria-label": "Select all on page"
                                      });
                                    },
                                    $$slots: { default: true }
                                  });
                                  $$renderer8.push("<!--]-->");
                                } else {
                                  $$renderer8.push("<!--[!-->");
                                  $$renderer8.push("<!--]-->");
                                }
                                $$renderer8.push(` `);
                                if (Table_head) {
                                  $$renderer8.push("<!--[-->");
                                  Table_head($$renderer8, {
                                    class: "px-4 py-3",
                                    children: ($$renderer9) => {
                                      $$renderer9.push(`<!---->Name`);
                                    },
                                    $$slots: { default: true }
                                  });
                                  $$renderer8.push("<!--]-->");
                                } else {
                                  $$renderer8.push("<!--[!-->");
                                  $$renderer8.push("<!--]-->");
                                }
                                $$renderer8.push(` `);
                                if (Table_head) {
                                  $$renderer8.push("<!--[-->");
                                  Table_head($$renderer8, {
                                    class: "px-4 py-3",
                                    children: ($$renderer9) => {
                                      $$renderer9.push(`<!---->Email`);
                                    },
                                    $$slots: { default: true }
                                  });
                                  $$renderer8.push("<!--]-->");
                                } else {
                                  $$renderer8.push("<!--[!-->");
                                  $$renderer8.push("<!--]-->");
                                }
                                $$renderer8.push(` `);
                                if (Table_head) {
                                  $$renderer8.push("<!--[-->");
                                  Table_head($$renderer8, {
                                    class: "px-4 py-3",
                                    children: ($$renderer9) => {
                                      $$renderer9.push(`<!---->Status`);
                                    },
                                    $$slots: { default: true }
                                  });
                                  $$renderer8.push("<!--]-->");
                                } else {
                                  $$renderer8.push("<!--[!-->");
                                  $$renderer8.push("<!--]-->");
                                }
                                $$renderer8.push(` `);
                                if (Table_head) {
                                  $$renderer8.push("<!--[-->");
                                  Table_head($$renderer8, {
                                    class: "px-4 py-3 text-center",
                                    children: ($$renderer9) => {
                                      $$renderer9.push(`<!---->Actions`);
                                    },
                                    $$slots: { default: true }
                                  });
                                  $$renderer8.push("<!--]-->");
                                } else {
                                  $$renderer8.push("<!--[!-->");
                                  $$renderer8.push("<!--]-->");
                                }
                              },
                              $$slots: { default: true }
                            });
                            $$renderer7.push("<!--]-->");
                          } else {
                            $$renderer7.push("<!--[!-->");
                            $$renderer7.push("<!--]-->");
                          }
                        },
                        $$slots: { default: true }
                      });
                      $$renderer6.push("<!--]-->");
                    } else {
                      $$renderer6.push("<!--[!-->");
                      $$renderer6.push("<!--]-->");
                    }
                    $$renderer6.push(` `);
                    if (Table_body) {
                      $$renderer6.push("<!--[-->");
                      Table_body($$renderer6, {
                        children: ($$renderer7) => {
                          const each_array_12 = ensure_array_like(mgmtFiltered());
                          if (each_array_12.length !== 0) {
                            $$renderer7.push("<!--[-->");
                            for (let $$index_12 = 0, $$length = each_array_12.length; $$index_12 < $$length; $$index_12++) {
                              let row = each_array_12[$$index_12];
                              if (Table_row) {
                                $$renderer7.push("<!--[-->");
                                Table_row($$renderer7, {
                                  children: ($$renderer8) => {
                                    if (Table_cell) {
                                      $$renderer8.push("<!--[-->");
                                      Table_cell($$renderer8, {
                                        class: "px-2",
                                        children: ($$renderer9) => {
                                          Checkbox($$renderer9, {
                                            checked: mgmtSelected.includes(row.id),
                                            onCheckedChange: () => mgmtToggleRow(row.id),
                                            "aria-label": `Select ${stringify(row.name)}`
                                          });
                                        },
                                        $$slots: { default: true }
                                      });
                                      $$renderer8.push("<!--]-->");
                                    } else {
                                      $$renderer8.push("<!--[!-->");
                                      $$renderer8.push("<!--]-->");
                                    }
                                    $$renderer8.push(` `);
                                    if (Table_cell) {
                                      $$renderer8.push("<!--[-->");
                                      Table_cell($$renderer8, {
                                        class: "px-4 font-medium",
                                        children: ($$renderer9) => {
                                          $$renderer9.push(`<!---->${escape_html(row.name)}`);
                                        },
                                        $$slots: { default: true }
                                      });
                                      $$renderer8.push("<!--]-->");
                                    } else {
                                      $$renderer8.push("<!--[!-->");
                                      $$renderer8.push("<!--]-->");
                                    }
                                    $$renderer8.push(` `);
                                    if (Table_cell) {
                                      $$renderer8.push("<!--[-->");
                                      Table_cell($$renderer8, {
                                        class: "text-muted-foreground px-4",
                                        children: ($$renderer9) => {
                                          $$renderer9.push(`<!---->${escape_html(row.email)}`);
                                        },
                                        $$slots: { default: true }
                                      });
                                      $$renderer8.push("<!--]-->");
                                    } else {
                                      $$renderer8.push("<!--[!-->");
                                      $$renderer8.push("<!--]-->");
                                    }
                                    $$renderer8.push(` `);
                                    if (Table_cell) {
                                      $$renderer8.push("<!--[-->");
                                      Table_cell($$renderer8, {
                                        class: "px-4",
                                        children: ($$renderer9) => {
                                          Badge($$renderer9, {
                                            variant: mgmtStatusVariant(row.status),
                                            class: "capitalize",
                                            children: ($$renderer10) => {
                                              $$renderer10.push(`<!---->${escape_html(row.status)}`);
                                            },
                                            $$slots: { default: true }
                                          });
                                        },
                                        $$slots: { default: true }
                                      });
                                      $$renderer8.push("<!--]-->");
                                    } else {
                                      $$renderer8.push("<!--[!-->");
                                      $$renderer8.push("<!--]-->");
                                    }
                                    $$renderer8.push(` `);
                                    if (Table_cell) {
                                      $$renderer8.push("<!--[-->");
                                      Table_cell($$renderer8, {
                                        class: "px-4",
                                        children: ($$renderer9) => {
                                          $$renderer9.push(`<div class="flex justify-center gap-1">`);
                                          Button($$renderer9, {
                                            variant: "ghost",
                                            size: "sm",
                                            class: "h-8 px-2 text-xs",
                                            children: ($$renderer10) => {
                                              Pencil($$renderer10, { class: "mr-1 h-3.5 w-3.5" });
                                              $$renderer10.push(`<!----> Edit`);
                                            },
                                            $$slots: { default: true }
                                          });
                                          $$renderer9.push(`<!----> `);
                                          Button($$renderer9, {
                                            variant: "ghost",
                                            size: "sm",
                                            class: "text-destructive hover:text-destructive h-8 px-2 text-xs",
                                            children: ($$renderer10) => {
                                              Trash_2($$renderer10, { class: "mr-1 h-3.5 w-3.5" });
                                              $$renderer10.push(`<!----> Delete`);
                                            },
                                            $$slots: { default: true }
                                          });
                                          $$renderer9.push(`<!----></div>`);
                                        },
                                        $$slots: { default: true }
                                      });
                                      $$renderer8.push("<!--]-->");
                                    } else {
                                      $$renderer8.push("<!--[!-->");
                                      $$renderer8.push("<!--]-->");
                                    }
                                  },
                                  $$slots: { default: true }
                                });
                                $$renderer7.push("<!--]-->");
                              } else {
                                $$renderer7.push("<!--[!-->");
                                $$renderer7.push("<!--]-->");
                              }
                            }
                          } else {
                            $$renderer7.push("<!--[!-->");
                            if (Table_row) {
                              $$renderer7.push("<!--[-->");
                              Table_row($$renderer7, {
                                children: ($$renderer8) => {
                                  if (Table_cell) {
                                    $$renderer8.push("<!--[-->");
                                    Table_cell($$renderer8, {
                                      colspan: 5,
                                      class: "text-muted-foreground px-4 py-10 text-center",
                                      children: ($$renderer9) => {
                                        $$renderer9.push(`<!---->No rows match these filters.`);
                                      },
                                      $$slots: { default: true }
                                    });
                                    $$renderer8.push("<!--]-->");
                                  } else {
                                    $$renderer8.push("<!--[!-->");
                                    $$renderer8.push("<!--]-->");
                                  }
                                },
                                $$slots: { default: true }
                              });
                              $$renderer7.push("<!--]-->");
                            } else {
                              $$renderer7.push("<!--[!-->");
                              $$renderer7.push("<!--]-->");
                            }
                          }
                          $$renderer7.push(`<!--]-->`);
                        },
                        $$slots: { default: true }
                      });
                      $$renderer6.push("<!--]-->");
                    } else {
                      $$renderer6.push("<!--[!-->");
                      $$renderer6.push("<!--]-->");
                    }
                  },
                  $$slots: { default: true }
                });
                $$renderer5.push("<!--]-->");
              } else {
                $$renderer5.push("<!--[!-->");
                $$renderer5.push("<!--]-->");
              }
              $$renderer5.push(`</div>`);
            }, cards = function($$renderer5) {
              $$renderer5.push(`<div class="mb-3 flex items-center gap-2 md:hidden">`);
              Checkbox($$renderer5, {
                checked: mgmtAllSelected(),
                indeterminate: mgmtSomeSelected(),
                onCheckedChange: (v) => mgmtToggleAll(!!v),
                "aria-label": "Select all on page"
              });
              $$renderer5.push(`<!----> <span class="text-muted-foreground text-sm">Select all on page</span></div> `);
              if (mgmtFiltered().length > 0) {
                $$renderer5.push("<!--[0-->");
                $$renderer5.push(`<ul class="grid gap-3 sm:grid-cols-2 lg:grid-cols-3" role="list"><!--[-->`);
                const each_array_13 = ensure_array_like(mgmtFiltered());
                for (let $$index_13 = 0, $$length = each_array_13.length; $$index_13 < $$length; $$index_13++) {
                  let row = each_array_13[$$index_13];
                  $$renderer5.push(`<li class="border-border/80 bg-card flex flex-col gap-3.5 rounded-[1.25rem] border p-5 shadow-sm dark:shadow-[inset_0_1px_0_0_rgba(255,255,255,0.08)] hover:-translate-y-0.5 hover:border-primary/40 transition-all duration-200"><div class="flex items-start gap-3">`);
                  Checkbox($$renderer5, {
                    checked: mgmtSelected.includes(row.id),
                    onCheckedChange: () => mgmtToggleRow(row.id),
                    "aria-label": `Select ${stringify(row.name)}`
                  });
                  $$renderer5.push(`<!----> <div class="min-w-0 flex-1"><p class="truncate font-semibold">${escape_html(row.name)}</p> <p class="text-muted-foreground truncate text-sm">${escape_html(row.email)}</p></div> `);
                  Badge($$renderer5, {
                    variant: mgmtStatusVariant(row.status),
                    class: "shrink-0 capitalize",
                    children: ($$renderer6) => {
                      $$renderer6.push(`<!---->${escape_html(row.status)}`);
                    },
                    $$slots: { default: true }
                  });
                  $$renderer5.push(`<!----></div> <div class="flex gap-2 pt-1 border-t border-border/60">`);
                  Button($$renderer5, {
                    variant: "outline",
                    size: "sm",
                    class: "min-h-[38px] flex-1 rounded-xl text-xs font-semibold",
                    children: ($$renderer6) => {
                      $$renderer6.push(`<!---->Edit`);
                    },
                    $$slots: { default: true }
                  });
                  $$renderer5.push(`<!----> `);
                  Button($$renderer5, {
                    variant: "ghost",
                    size: "sm",
                    class: "text-destructive min-h-[38px] rounded-xl text-xs font-semibold",
                    children: ($$renderer6) => {
                      $$renderer6.push(`<!---->Delete`);
                    },
                    $$slots: { default: true }
                  });
                  $$renderer5.push(`<!----></div></li>`);
                }
                $$renderer5.push(`<!--]--></ul>`);
              } else {
                $$renderer5.push("<!--[-1-->");
                $$renderer5.push(`<p class="text-muted-foreground py-8 text-center text-sm">No rows match these filters.</p>`);
              }
              $$renderer5.push(`<!--]-->`);
            };
            SwitchableListView($$renderer4, {
              get viewMode() {
                return mgmtViewMode;
              },
              set viewMode($$value) {
                mgmtViewMode = $$value;
                $$settled = false;
              },
              table,
              cards,
              $$slots: { table: true, cards: true }
            });
          }
          $$renderer4.push(`<!----> `);
          SimplePagination($$renderer4, { data: paginationDemoPage1of3 });
          $$renderer4.push(`<!----></div></section> <section id="page-header" class="space-y-4"><h2 class="text-foreground text-xl font-semibold">Page header actions</h2> `);
          if (Card) {
            $$renderer4.push("<!--[-->");
            Card($$renderer4, {
              children: ($$renderer5) => {
                if (Card_content) {
                  $$renderer5.push("<!--[-->");
                  Card_content($$renderer5, {
                    class: "space-y-4",
                    children: ($$renderer6) => {
                      {
                        let actions = function($$renderer7) {
                          Button($$renderer7, {
                            class: "min-h-[44px] gap-2",
                            children: ($$renderer8) => {
                              Plus($$renderer8, { class: "h-4 w-4" });
                              $$renderer8.push(`<!----> <span class="hidden sm:inline">Add Room</span>`);
                            },
                            $$slots: { default: true }
                          });
                        };
                        PageHeaderBar($$renderer6, {
                          title: "Rooms",
                          description: "Short description sits left; actions cluster right.",
                          actions
                        });
                      }
                      $$renderer6.push(`<!----> <p class="text-muted-foreground text-xs">On narrow viewports only the icon shows (<code>hidden sm:inline</code> on labels) — same as legacy Create/Import/Export.</p>`);
                    },
                    $$slots: { default: true }
                  });
                  $$renderer5.push("<!--]-->");
                } else {
                  $$renderer5.push("<!--[!-->");
                  $$renderer5.push("<!--]-->");
                }
              },
              $$slots: { default: true }
            });
            $$renderer4.push("<!--]-->");
          } else {
            $$renderer4.push("<!--[!-->");
            $$renderer4.push("<!--]-->");
          }
          $$renderer4.push(`</section> <section id="filter-toolbar" class="space-y-4"><h2 class="text-foreground text-xl font-semibold">Filter toolbar</h2> `);
          if (Card) {
            $$renderer4.push("<!--[-->");
            Card($$renderer4, {
              children: ($$renderer5) => {
                if (Card_header) {
                  $$renderer5.push("<!--[-->");
                  Card_header($$renderer5, {
                    children: ($$renderer6) => {
                      if (Card_title) {
                        $$renderer6.push("<!--[-->");
                        Card_title($$renderer6, {
                          children: ($$renderer7) => {
                            $$renderer7.push(`<!---->Desktop row / mobile Filters panel`);
                          },
                          $$slots: { default: true }
                        });
                        $$renderer6.push("<!--]-->");
                      } else {
                        $$renderer6.push("<!--[!-->");
                        $$renderer6.push("<!--]-->");
                      }
                      $$renderer6.push(` `);
                      if (Card_description) {
                        $$renderer6.push("<!--[-->");
                        Card_description($$renderer6, {
                          children: ($$renderer7) => {
                            $$renderer7.push(`<!---->Search always visible. Extra selects and dates collapse
                        into a Filters <code>details</code> below <code>md</code>.`);
                          },
                          $$slots: { default: true }
                        });
                        $$renderer6.push("<!--]-->");
                      } else {
                        $$renderer6.push("<!--[!-->");
                        $$renderer6.push("<!--]-->");
                      }
                    },
                    $$slots: { default: true }
                  });
                  $$renderer5.push("<!--]-->");
                } else {
                  $$renderer5.push("<!--[!-->");
                  $$renderer5.push("<!--]-->");
                }
                $$renderer5.push(` `);
                if (Card_content) {
                  $$renderer5.push("<!--[-->");
                  Card_content($$renderer5, {
                    children: ($$renderer6) => {
                      {
                        let desktopFilters = function($$renderer7) {
                          if (Select) {
                            $$renderer7.push("<!--[-->");
                            Select($$renderer7, {
                              type: "single",
                              get value() {
                                return mgmtStatus;
                              },
                              set value($$value) {
                                mgmtStatus = $$value;
                                $$settled = false;
                              },
                              children: ($$renderer8) => {
                                if (Select_trigger) {
                                  $$renderer8.push("<!--[-->");
                                  Select_trigger($$renderer8, {
                                    class: "min-h-[40px] w-[150px]",
                                    children: ($$renderer9) => {
                                      $$renderer9.push(`<!---->${escape_html(mgmtStatusLabel())}`);
                                    },
                                    $$slots: { default: true }
                                  });
                                  $$renderer8.push("<!--]-->");
                                } else {
                                  $$renderer8.push("<!--[!-->");
                                  $$renderer8.push("<!--]-->");
                                }
                                $$renderer8.push(` `);
                                if (Select_content) {
                                  $$renderer8.push("<!--[-->");
                                  Select_content($$renderer8, {
                                    children: ($$renderer9) => {
                                      $$renderer9.push(`<!--[-->`);
                                      const each_array_14 = ensure_array_like(mgmtStatusOptions);
                                      for (let $$index_14 = 0, $$length = each_array_14.length; $$index_14 < $$length; $$index_14++) {
                                        let o = each_array_14[$$index_14];
                                        if (Select_item) {
                                          $$renderer9.push("<!--[-->");
                                          Select_item($$renderer9, {
                                            value: o.value,
                                            label: o.label,
                                            children: ($$renderer10) => {
                                              $$renderer10.push(`<!---->${escape_html(o.label)}`);
                                            },
                                            $$slots: { default: true }
                                          });
                                          $$renderer9.push("<!--]-->");
                                        } else {
                                          $$renderer9.push("<!--[!-->");
                                          $$renderer9.push("<!--]-->");
                                        }
                                      }
                                      $$renderer9.push(`<!--]-->`);
                                    },
                                    $$slots: { default: true }
                                  });
                                  $$renderer8.push("<!--]-->");
                                } else {
                                  $$renderer8.push("<!--[!-->");
                                  $$renderer8.push("<!--]-->");
                                }
                              },
                              $$slots: { default: true }
                            });
                            $$renderer7.push("<!--]-->");
                          } else {
                            $$renderer7.push("<!--[!-->");
                            $$renderer7.push("<!--]-->");
                          }
                        }, mobileFilters = function($$renderer7) {
                          $$renderer7.push(`<label class="mb-1 block text-sm font-medium" for="iso-status-mob">Status</label> `);
                          if (Select) {
                            $$renderer7.push("<!--[-->");
                            Select($$renderer7, {
                              type: "single",
                              get value() {
                                return mgmtStatus;
                              },
                              set value($$value) {
                                mgmtStatus = $$value;
                                $$settled = false;
                              },
                              children: ($$renderer8) => {
                                if (Select_trigger) {
                                  $$renderer8.push("<!--[-->");
                                  Select_trigger($$renderer8, {
                                    id: "iso-status-mob",
                                    class: "min-h-[44px] w-full",
                                    children: ($$renderer9) => {
                                      $$renderer9.push(`<!---->${escape_html(mgmtStatusLabel())}`);
                                    },
                                    $$slots: { default: true }
                                  });
                                  $$renderer8.push("<!--]-->");
                                } else {
                                  $$renderer8.push("<!--[!-->");
                                  $$renderer8.push("<!--]-->");
                                }
                                $$renderer8.push(` `);
                                if (Select_content) {
                                  $$renderer8.push("<!--[-->");
                                  Select_content($$renderer8, {
                                    children: ($$renderer9) => {
                                      $$renderer9.push(`<!--[-->`);
                                      const each_array_15 = ensure_array_like(mgmtStatusOptions);
                                      for (let $$index_15 = 0, $$length = each_array_15.length; $$index_15 < $$length; $$index_15++) {
                                        let o = each_array_15[$$index_15];
                                        if (Select_item) {
                                          $$renderer9.push("<!--[-->");
                                          Select_item($$renderer9, {
                                            value: o.value,
                                            label: o.label,
                                            children: ($$renderer10) => {
                                              $$renderer10.push(`<!---->${escape_html(o.label)}`);
                                            },
                                            $$slots: { default: true }
                                          });
                                          $$renderer9.push("<!--]-->");
                                        } else {
                                          $$renderer9.push("<!--[!-->");
                                          $$renderer9.push("<!--]-->");
                                        }
                                      }
                                      $$renderer9.push(`<!--]-->`);
                                    },
                                    $$slots: { default: true }
                                  });
                                  $$renderer8.push("<!--]-->");
                                } else {
                                  $$renderer8.push("<!--[!-->");
                                  $$renderer8.push("<!--]-->");
                                }
                              },
                              $$slots: { default: true }
                            });
                            $$renderer7.push("<!--]-->");
                          } else {
                            $$renderer7.push("<!--[!-->");
                            $$renderer7.push("<!--]-->");
                          }
                        };
                        FilterToolbar($$renderer6, {
                          searchPlaceholder: "Search…",
                          get search() {
                            return mgmtSearch;
                          },
                          set search($$value) {
                            mgmtSearch = $$value;
                            $$settled = false;
                          },
                          desktopFilters,
                          mobileFilters,
                          $$slots: { desktopFilters: true, mobileFilters: true }
                        });
                      }
                      $$renderer6.push(`<!----> <p class="text-muted-foreground mt-3 text-xs">Wire <code>lib/auto-filter.js</code> debounced/immediate watches
                        on real pages (search debounce ~300ms; selects immediate).</p>`);
                    },
                    $$slots: { default: true }
                  });
                  $$renderer5.push("<!--]-->");
                } else {
                  $$renderer5.push("<!--[!-->");
                  $$renderer5.push("<!--]-->");
                }
              },
              $$slots: { default: true }
            });
            $$renderer4.push("<!--]-->");
          } else {
            $$renderer4.push("<!--[!-->");
            $$renderer4.push("<!--]-->");
          }
          $$renderer4.push(`</section> <section id="bulk-bar" class="space-y-4"><h2 class="text-foreground text-xl font-semibold">Bulk action bar</h2> `);
          {
            let actions = function($$renderer5) {
              Button($$renderer5, {
                size: "sm",
                class: "gap-1.5",
                children: ($$renderer6) => {
                  $$renderer6.push(`<!---->Accept All`);
                },
                $$slots: { default: true }
              });
              $$renderer5.push(`<!----> `);
              Button($$renderer5, {
                size: "sm",
                variant: "destructive",
                class: "gap-1.5",
                children: ($$renderer6) => {
                  $$renderer6.push(`<!---->Dismiss All`);
                },
                $$slots: { default: true }
              });
              $$renderer5.push(`<!---->`);
            };
            BulkActionBar($$renderer4, {
              count: 3,
              hint: "Accept or dismiss pending rows.",
              onClear: () => info("Demo: cleared."),
              actions
            });
          }
          $$renderer4.push(`<!----> <p class="text-muted-foreground text-xs">Hidden when count is 0 (component returns nothing).</p></section> <section id="mode-banner" class="space-y-4"><h2 class="text-foreground text-xl font-semibold">Mode banner</h2> <div class="space-y-3">`);
          ModeBanner($$renderer4, {
            variant: "info",
            title: "F2F mode:",
            children: ($$renderer5) => {
              $$renderer5.push(`<!---->Results provided in person. Email delivery disabled.`);
            }
          });
          $$renderer4.push(`<!----> `);
          ModeBanner($$renderer4, {
            variant: "primary",
            title: "Online mode:",
            children: ($$renderer5) => {
              $$renderer5.push(`<!---->Portal notification + email on release.`);
            }
          });
          $$renderer4.push(`<!----> `);
          ModeBanner($$renderer4, {
            variant: "warning",
            title: "Attention:",
            children: ($$renderer5) => {
              $$renderer5.push(`<!---->Season rolls over in 7 days (demo).`);
            }
          });
          $$renderer4.push(`<!----></div></section> <section id="action-dropdown" class="space-y-4"><h2 class="text-foreground text-xl font-semibold">Action dropdown</h2> `);
          if (Card) {
            $$renderer4.push("<!--[-->");
            Card($$renderer4, {
              children: ($$renderer5) => {
                if (Card_content) {
                  $$renderer5.push("<!--[-->");
                  Card_content($$renderer5, {
                    class: "flex items-center justify-between",
                    children: ($$renderer6) => {
                      $$renderer6.push(`<span class="text-muted-foreground text-sm">Row-level actions shell (generic items, no domain
                        binding)</span> `);
                      ActionDropdown($$renderer6, { items: actionItems, confirmDelete: true });
                      $$renderer6.push(`<!---->`);
                    },
                    $$slots: { default: true }
                  });
                  $$renderer5.push("<!--]-->");
                } else {
                  $$renderer5.push("<!--[!-->");
                  $$renderer5.push("<!--]-->");
                }
              },
              $$slots: { default: true }
            });
            $$renderer4.push("<!--]-->");
          } else {
            $$renderer4.push("<!--[!-->");
            $$renderer4.push("<!--]-->");
          }
          $$renderer4.push(`</section></div>`);
        }
      });
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
  Gallery as default
};
