import { g as sanitize_props, i as spread_props, j as slot, d as derived, D as props_id, z as attributes, l as bind_props, C as cn, a0 as setContext, $ as getContext, c as attr_class } from "../app.js";
import { tv } from "tailwind-variants";
import { a as attachRef, c as boolToTrueOrUndef, d as boolToStr, b as boolToEmptyStrOrUndef, e as createBitsAttrs, f as createId, g as boxWith, m as mergeProps, o as getAriaChecked } from "./create-id-CUJmDlea.js";
import { n as noop } from "./noop-BQS7OFs8.js";
import { w as watch } from "./watch.svelte-BcPoqckX.js";
import { C as Context, E as ENTER, S as SPACE } from "./is-C6cm64pF.js";
import { R as RovingFocusGroup } from "./roving-focus-group-BJDH9kUk.js";
import { I as Icon } from "./Icon-Dal7PqKK.js";
import { T as Table_2 } from "./table-2-BLgeaPqt.js";
import { L as Layout_grid } from "./layout-grid-CXJfSBqV.js";
function Monitor_smartphone($$renderer, $$props) {
  const $$sanitized_props = sanitize_props($$props);
  const iconNode = [
    [
      "path",
      {
        "d": "M18 8V6a2 2 0 0 0-2-2H4a2 2 0 0 0-2 2v7a2 2 0 0 0 2 2h8"
      }
    ],
    ["path", { "d": "M10 19v-3.96 3.15" }],
    ["path", { "d": "M7 19h5" }],
    [
      "rect",
      {
        "width": "6",
        "height": "10",
        "x": "16",
        "y": "12",
        "rx": "2"
      }
    ]
  ];
  Icon($$renderer, spread_props([
    { name: "monitor-smartphone" },
    $$sanitized_props,
    {
      /**
       * @component @name MonitorSmartphone
       * @description Lucide SVG icon component, renders SVG Element with children.
       *
       * @preview ![img](data:image/svg+xml;base64,PHN2ZyAgeG1sbnM9Imh0dHA6Ly93d3cudzMub3JnLzIwMDAvc3ZnIgogIHdpZHRoPSIyNCIKICBoZWlnaHQ9IjI0IgogIHZpZXdCb3g9IjAgMCAyNCAyNCIKICBmaWxsPSJub25lIgogIHN0cm9rZT0iIzAwMCIgc3R5bGU9ImJhY2tncm91bmQtY29sb3I6ICNmZmY7IGJvcmRlci1yYWRpdXM6IDJweCIKICBzdHJva2Utd2lkdGg9IjIiCiAgc3Ryb2tlLWxpbmVjYXA9InJvdW5kIgogIHN0cm9rZS1saW5lam9pbj0icm91bmQiCj4KICA8cGF0aCBkPSJNMTggOFY2YTIgMiAwIDAgMC0yLTJINGEyIDIgMCAwIDAtMiAydjdhMiAyIDAgMCAwIDIgMmg4IiAvPgogIDxwYXRoIGQ9Ik0xMCAxOXYtMy45NiAzLjE1IiAvPgogIDxwYXRoIGQ9Ik03IDE5aDUiIC8+CiAgPHJlY3Qgd2lkdGg9IjYiIGhlaWdodD0iMTAiIHg9IjE2IiB5PSIxMiIgcng9IjIiIC8+Cjwvc3ZnPgo=) - https://lucide.dev/icons/monitor-smartphone
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
const toggleAttrs = createBitsAttrs({ component: "toggle", parts: ["root"] });
class ToggleRootState {
  static create(opts) {
    return new ToggleRootState(opts);
  }
  opts;
  attachment;
  constructor(opts) {
    this.opts = opts;
    this.attachment = attachRef(this.opts.ref);
    this.onclick = this.onclick.bind(this);
  }
  onclick(_) {
    if (this.opts.disabled.current) return;
    this.opts.pressed.current = !this.opts.pressed.current;
  }
  #snippetProps = derived(() => ({ pressed: this.opts.pressed.current }));
  get snippetProps() {
    return this.#snippetProps();
  }
  set snippetProps($$value) {
    return this.#snippetProps($$value);
  }
  #props = derived(() => ({
    [toggleAttrs.root]: "",
    id: this.opts.id.current,
    "data-disabled": boolToEmptyStrOrUndef(this.opts.disabled.current),
    "aria-pressed": boolToStr(this.opts.pressed.current),
    "data-state": getToggleDataState(this.opts.pressed.current),
    disabled: boolToTrueOrUndef(this.opts.disabled.current),
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
function getToggleDataState(condition) {
  return condition ? "on" : "off";
}
function Toggle$1($$renderer, $$props) {
  $$renderer.component(($$renderer2) => {
    const uid = props_id($$renderer2);
    let {
      ref = null,
      id = createId(uid),
      pressed = false,
      onPressedChange = noop,
      disabled = false,
      type = "button",
      children,
      child,
      $$slots,
      $$events,
      ...restProps
    } = $$props;
    const toggleState = ToggleRootState.create({
      pressed: boxWith(() => pressed, (v) => {
        pressed = v;
        onPressedChange(v);
      }),
      disabled: boxWith(() => disabled ?? false),
      id: boxWith(() => id),
      ref: boxWith(() => ref, (v) => ref = v)
    });
    const mergedProps = derived(() => mergeProps(restProps, toggleState.props, { type }));
    if (child) {
      $$renderer2.push("<!--[0-->");
      child($$renderer2, { props: mergedProps(), ...toggleState.snippetProps });
      $$renderer2.push(`<!---->`);
    } else {
      $$renderer2.push("<!--[-1-->");
      $$renderer2.push(`<button${attributes({ ...mergedProps() })}>`);
      children?.($$renderer2, toggleState.snippetProps);
      $$renderer2.push(`<!----></button>`);
    }
    $$renderer2.push(`<!--]-->`);
    bind_props($$props, { ref, pressed });
  });
}
const toggleGroupAttrs = createBitsAttrs({ component: "toggle-group", parts: ["root", "item"] });
const ToggleGroupRootContext = new Context("ToggleGroup.Root");
class ToggleGroupBaseState {
  opts;
  rovingFocusGroup;
  attachment;
  constructor(opts) {
    this.opts = opts;
    this.attachment = attachRef(this.opts.ref);
    this.rovingFocusGroup = new RovingFocusGroup({
      candidateAttr: toggleGroupAttrs.item,
      rootNode: opts.ref,
      loop: opts.loop,
      orientation: opts.orientation
    });
  }
  #props = derived(() => ({
    id: this.opts.id.current,
    [toggleGroupAttrs.root]: "",
    role: "group",
    "data-orientation": this.opts.orientation.current,
    "data-disabled": boolToEmptyStrOrUndef(this.opts.disabled.current),
    ...this.attachment
  }));
  get props() {
    return this.#props();
  }
  set props($$value) {
    return this.#props($$value);
  }
}
class ToggleGroupSingleState extends ToggleGroupBaseState {
  opts;
  isMulti = false;
  #anyPressed = derived(() => this.opts.value.current !== "");
  get anyPressed() {
    return this.#anyPressed();
  }
  set anyPressed($$value) {
    return this.#anyPressed($$value);
  }
  constructor(opts) {
    super(opts);
    this.opts = opts;
  }
  includesItem(item) {
    return this.opts.value.current === item;
  }
  toggleItem(item, id) {
    if (this.includesItem(item)) {
      this.opts.value.current = "";
    } else {
      this.opts.value.current = item;
      this.rovingFocusGroup.setCurrentTabStopId(id);
    }
  }
}
class ToggleGroupMultipleState extends ToggleGroupBaseState {
  opts;
  isMulti = true;
  #anyPressed = derived(() => this.opts.value.current.length > 0);
  get anyPressed() {
    return this.#anyPressed();
  }
  set anyPressed($$value) {
    return this.#anyPressed($$value);
  }
  constructor(opts) {
    super(opts);
    this.opts = opts;
  }
  includesItem(item) {
    return this.opts.value.current.includes(item);
  }
  toggleItem(item, id) {
    if (this.includesItem(item)) {
      this.opts.value.current = this.opts.value.current.filter((v) => v !== item);
    } else {
      this.opts.value.current = [...this.opts.value.current, item];
      this.rovingFocusGroup.setCurrentTabStopId(id);
    }
  }
}
class ToggleGroupRootState {
  static create(opts) {
    const { type, ...rest } = opts;
    const rootState = type === "single" ? new ToggleGroupSingleState(rest) : new ToggleGroupMultipleState(rest);
    return ToggleGroupRootContext.set(rootState);
  }
}
class ToggleGroupItemState {
  static create(opts) {
    return new ToggleGroupItemState(opts, ToggleGroupRootContext.get());
  }
  opts;
  root;
  attachment;
  #isDisabled = derived(() => this.opts.disabled.current || this.root.opts.disabled.current);
  #isPressed = derived(() => this.root.includesItem(this.opts.value.current));
  get isPressed() {
    return this.#isPressed();
  }
  set isPressed($$value) {
    return this.#isPressed($$value);
  }
  #ariaChecked = derived(() => {
    return this.root.isMulti ? void 0 : getAriaChecked(this.isPressed, false);
  });
  #ariaPressed = derived(() => {
    return this.root.isMulti ? boolToStr(this.isPressed) : void 0;
  });
  constructor(opts, root) {
    this.opts = opts;
    this.root = root;
    this.attachment = attachRef(this.opts.ref);
    this.onclick = this.onclick.bind(this);
    this.onkeydown = this.onkeydown.bind(this);
  }
  #toggleItem() {
    if (this.#isDisabled()) return;
    this.root.toggleItem(this.opts.value.current, this.opts.id.current);
  }
  onclick(_) {
    if (this.#isDisabled()) return;
    this.root.toggleItem(this.opts.value.current, this.opts.id.current);
  }
  onkeydown(e) {
    if (this.#isDisabled()) return;
    if (e.key === ENTER || e.key === SPACE) {
      e.preventDefault();
      this.#toggleItem();
      return;
    }
    if (!this.root.opts.rovingFocus.current) return;
    this.root.rovingFocusGroup.handleKeydown(this.opts.ref.current, e);
  }
  #tabIndex = 0;
  #snippetProps = derived(() => ({ pressed: this.isPressed }));
  get snippetProps() {
    return this.#snippetProps();
  }
  set snippetProps($$value) {
    return this.#snippetProps($$value);
  }
  #props = derived(() => ({
    id: this.opts.id.current,
    role: this.root.isMulti ? void 0 : "radio",
    tabindex: this.#tabIndex,
    "data-orientation": this.root.opts.orientation.current,
    "data-disabled": boolToEmptyStrOrUndef(this.#isDisabled()),
    "data-state": getToggleItemDataState(this.isPressed),
    "data-value": this.opts.value.current,
    "aria-pressed": this.#ariaPressed(),
    "aria-checked": this.#ariaChecked(),
    disabled: boolToTrueOrUndef(this.#isDisabled()),
    [toggleGroupAttrs.item]: "",
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
function getToggleItemDataState(condition) {
  return condition ? "on" : "off";
}
function Toggle_group$1($$renderer, $$props) {
  $$renderer.component(($$renderer2) => {
    const uid = props_id($$renderer2);
    let {
      id = createId(uid),
      ref = null,
      value = void 0,
      onValueChange = noop,
      type,
      disabled = false,
      loop = true,
      orientation = "horizontal",
      rovingFocus = true,
      child,
      children,
      $$slots,
      $$events,
      ...restProps
    } = $$props;
    function handleDefaultValue() {
      if (value !== void 0) return;
      value = type === "single" ? "" : [];
    }
    handleDefaultValue();
    watch.pre(() => value, () => {
      handleDefaultValue();
    });
    const rootState = ToggleGroupRootState.create({
      id: boxWith(() => id),
      value: boxWith(() => value, (v) => {
        value = v;
        onValueChange(v);
      }),
      disabled: boxWith(() => disabled),
      loop: boxWith(() => loop),
      orientation: boxWith(() => orientation),
      rovingFocus: boxWith(() => rovingFocus),
      type,
      ref: boxWith(() => ref, (v) => ref = v)
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
    bind_props($$props, { ref, value });
  });
}
function Toggle_group_item$1($$renderer, $$props) {
  $$renderer.component(($$renderer2) => {
    const uid = props_id($$renderer2);
    let {
      children,
      child,
      ref = null,
      value,
      disabled = false,
      id = createId(uid),
      type = "button",
      $$slots,
      $$events,
      ...restProps
    } = $$props;
    const itemState = ToggleGroupItemState.create({
      id: boxWith(() => id),
      value: boxWith(() => value),
      disabled: boxWith(() => disabled ?? false),
      ref: boxWith(() => ref, (v) => ref = v)
    });
    const mergedProps = derived(() => mergeProps(restProps, itemState.props, { type }));
    if (child) {
      $$renderer2.push("<!--[0-->");
      child($$renderer2, { props: mergedProps(), ...itemState.snippetProps });
      $$renderer2.push(`<!---->`);
    } else {
      $$renderer2.push("<!--[-1-->");
      $$renderer2.push(`<button${attributes({ ...mergedProps() })}>`);
      children?.($$renderer2, itemState.snippetProps);
      $$renderer2.push(`<!----></button>`);
    }
    $$renderer2.push(`<!--]-->`);
    bind_props($$props, { ref });
  });
}
const toggleVariants = tv({
  base: "hover:bg-muted hover:text-muted-foreground data-[state=on]:bg-accent data-[state=on]:text-accent-foreground focus-visible:border-ring focus-visible:ring-ring/50 aria-invalid:ring-destructive/20 dark:aria-invalid:ring-destructive/40 aria-invalid:border-destructive inline-flex items-center justify-center gap-2 rounded-md text-sm font-medium whitespace-nowrap transition-[color,box-shadow] outline-none focus-visible:ring-[3px] disabled:pointer-events-none disabled:opacity-50 [&_svg]:pointer-events-none [&_svg]:shrink-0 [&_svg:not([class*='size-'])]:size-4",
  variants: {
    variant: {
      default: "bg-transparent",
      outline: "border-input hover:bg-accent hover:text-accent-foreground border bg-transparent shadow-xs"
    },
    size: {
      default: "h-9 min-w-9 px-2",
      sm: "h-8 min-w-8 px-1.5",
      lg: "h-10 min-w-10 px-2.5"
    }
  },
  defaultVariants: { variant: "default", size: "default" }
});
function Toggle($$renderer, $$props) {
  $$renderer.component(($$renderer2) => {
    let {
      ref = null,
      pressed = false,
      class: className,
      size = "default",
      variant = "default",
      $$slots,
      $$events,
      ...restProps
    } = $$props;
    let $$settled = true;
    let $$inner_renderer;
    function $$render_inner($$renderer3) {
      if (Toggle$1) {
        $$renderer3.push("<!--[-->");
        Toggle$1($$renderer3, spread_props([
          {
            "data-slot": "toggle",
            class: cn(toggleVariants({ variant, size }), className)
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
            get pressed() {
              return pressed;
            },
            set pressed($$value) {
              pressed = $$value;
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
    bind_props($$props, { ref, pressed });
  });
}
function setToggleGroupCtx(props) {
  setContext("toggleGroup", props);
}
function getToggleGroupCtx() {
  return getContext("toggleGroup");
}
function Toggle_group($$renderer, $$props) {
  $$renderer.component(($$renderer2) => {
    let {
      ref = null,
      value = void 0,
      class: className,
      size = "default",
      spacing = 0,
      variant = "default",
      $$slots,
      $$events,
      ...restProps
    } = $$props;
    setToggleGroupCtx({ variant, size, spacing });
    let $$settled = true;
    let $$inner_renderer;
    function $$render_inner($$renderer3) {
      if (Toggle_group$1) {
        $$renderer3.push("<!--[-->");
        Toggle_group$1($$renderer3, spread_props([
          {
            "data-slot": "toggle-group",
            "data-variant": variant,
            "data-size": size,
            "data-spacing": spacing,
            style: `--gap: ${spacing}`,
            class: cn("group/toggle-group flex w-fit items-center gap-[--spacing(var(--gap))] rounded-md data-[spacing=default]:data-[variant=outline]:shadow-xs", className)
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
    bind_props($$props, { ref, value });
  });
}
function Toggle_group_item($$renderer, $$props) {
  $$renderer.component(($$renderer2) => {
    let {
      ref = null,
      value = void 0,
      class: className,
      size,
      variant,
      children,
      $$slots,
      $$events,
      ...restProps
    } = $$props;
    const ctx = getToggleGroupCtx();
    let $$settled = true;
    let $$inner_renderer;
    function $$render_inner($$renderer3) {
      if (Toggle_group_item$1) {
        $$renderer3.push("<!--[-->");
        Toggle_group_item$1($$renderer3, spread_props([
          {
            value,
            "data-slot": "toggle-group-item",
            "data-variant": ctx.variant || variant,
            "data-size": ctx.size || size,
            "data-spacing": ctx.spacing,
            class: cn(toggleVariants({ variant: ctx.variant || variant, size: ctx.size || size }), "w-auto min-w-0 shrink-0 px-3 focus:z-10 focus-visible:z-10 data-[spacing=0]:rounded-none data-[spacing=0]:shadow-none data-[spacing=0]:first:rounded-l-md data-[spacing=0]:last:rounded-r-md data-[spacing=0]:data-[variant=outline]:border-l-0 data-[spacing=0]:data-[variant=outline]:first:border-l", className)
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
    bind_props($$props, { ref, value });
  });
}
function ViewModeToggle($$renderer, $$props) {
  $$renderer.component(($$renderer2) => {
    let { value = "responsive" } = $$props;
    let $$settled = true;
    let $$inner_renderer;
    function $$render_inner($$renderer3) {
      if (Toggle_group) {
        $$renderer3.push("<!--[-->");
        Toggle_group($$renderer3, {
          type: "single",
          variant: "outline",
          size: "sm",
          class: "min-h-[36px] rounded-md border border-border",
          "aria-label": "View layout",
          get value() {
            return value;
          },
          set value($$value) {
            value = $$value;
            $$settled = false;
          },
          children: ($$renderer4) => {
            if (Toggle_group_item) {
              $$renderer4.push("<!--[-->");
              Toggle_group_item($$renderer4, {
                value: "responsive",
                "aria-label": "Auto (responsive)",
                class: "min-h-[36px] px-2",
                title: "Auto",
                children: ($$renderer5) => {
                  Monitor_smartphone($$renderer5, { class: "h-4 w-4" });
                },
                $$slots: { default: true }
              });
              $$renderer4.push("<!--]-->");
            } else {
              $$renderer4.push("<!--[!-->");
              $$renderer4.push("<!--]-->");
            }
            $$renderer4.push(` `);
            if (Toggle_group_item) {
              $$renderer4.push("<!--[-->");
              Toggle_group_item($$renderer4, {
                value: "table",
                "aria-label": "Table view",
                class: "min-h-[36px] px-2",
                title: "Table",
                children: ($$renderer5) => {
                  Table_2($$renderer5, { class: "h-4 w-4" });
                },
                $$slots: { default: true }
              });
              $$renderer4.push("<!--]-->");
            } else {
              $$renderer4.push("<!--[!-->");
              $$renderer4.push("<!--]-->");
            }
            $$renderer4.push(` `);
            if (Toggle_group_item) {
              $$renderer4.push("<!--[-->");
              Toggle_group_item($$renderer4, {
                value: "cards",
                "aria-label": "Card view",
                class: "min-h-[36px] px-2",
                title: "Cards",
                children: ($$renderer5) => {
                  Layout_grid($$renderer5, { class: "h-4 w-4" });
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
    bind_props($$props, { value });
  });
}
function SwitchableListView($$renderer, $$props) {
  $$renderer.component(($$renderer2) => {
    let {
      viewMode = "responsive",
      table,
      cards,
      controls = null,
      cardsInset = true
    } = $$props;
    let $$settled = true;
    let $$inner_renderer;
    function $$render_inner($$renderer3) {
      $$renderer3.push(`<div class="space-y-3"><div class="flex flex-wrap items-center justify-between gap-3"><div>`);
      if (controls) {
        $$renderer3.push("<!--[0-->");
        controls($$renderer3);
        $$renderer3.push(`<!---->`);
      } else {
        $$renderer3.push("<!--[-1-->");
      }
      $$renderer3.push(`<!--]--></div> <div class="ml-auto">`);
      ViewModeToggle($$renderer3, {
        get value() {
          return viewMode;
        },
        set value($$value) {
          viewMode = $$value;
          $$settled = false;
        }
      });
      $$renderer3.push(`<!----></div></div> <div class="min-w-0"><div${attr_class(`w-full min-w-0 overflow-x-scroll overscroll-x-contain ${viewMode === "cards" ? "hidden" : viewMode === "table" ? "block" : "hidden md:block"}`)}>`);
      table($$renderer3);
      $$renderer3.push(`<!----></div></div> <div${attr_class(`${viewMode === "table" ? "hidden" : viewMode === "cards" ? "block" : "block md:hidden"} ${cardsInset ? "p-4" : ""}`)}>`);
      cards($$renderer3);
      $$renderer3.push(`<!----></div></div>`);
    }
    do {
      $$settled = true;
      $$inner_renderer = $$renderer2.copy();
      $$render_inner($$inner_renderer);
    } while (!$$settled);
    $$renderer2.subsume($$inner_renderer);
    bind_props($$props, { viewMode });
  });
}
export {
  SwitchableListView as S,
  Toggle as T,
  Toggle_group as a,
  Toggle_group_item as b
};
