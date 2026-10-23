import { d as derived, D as props_id, z as attributes, l as bind_props, i as spread_props, C as cn, a as ensure_array_like, e as escape_html } from "../app.js";
import { w as watch } from "./watch.svelte-BcPoqckX.js";
import { P as Previous } from "./previous.svelte-Ch7Tz-Mv.js";
import "clsx";
import { s as simpleBox, a as attachRef, c as boolToTrueOrUndef, e as createBitsAttrs, f as createId, g as boxWith, m as mergeProps } from "./create-id-CUJmDlea.js";
import { D as DOMContext, n as noop } from "./noop-BQS7OFs8.js";
const PWM_BADGE_SPACE_WIDTH_PX = 40;
const PWM_BADGE_SPACE_WIDTH = `${PWM_BADGE_SPACE_WIDTH_PX}px`;
function usePasswordManagerBadge({
  containerRef,
  inputRef,
  pushPasswordManagerStrategy,
  isFocused,
  domContext
}) {
  let hasPwmBadge = false;
  function willPushPwmBadge() {
    const strategy = pushPasswordManagerStrategy.current;
    if (strategy === "none") return false;
    const increaseWidthCase = strategy === "increase-width" && hasPwmBadge;
    return increaseWidthCase;
  }
  return {
    get hasPwmBadge() {
      return hasPwmBadge;
    },
    get willPushPwmBadge() {
      return willPushPwmBadge();
    },
    PWM_BADGE_SPACE_WIDTH
  };
}
const pinInputAttrs = createBitsAttrs({ component: "pin-input", parts: ["root", "cell"] });
const KEYS_TO_IGNORE = [
  "Backspace",
  "Delete",
  "ArrowLeft",
  "ArrowRight",
  "ArrowUp",
  "ArrowDown",
  "Home",
  "End",
  "Escape",
  "Enter",
  "Tab",
  "Shift",
  "Control",
  "Meta"
];
class PinInputRootState {
  static create(opts) {
    return new PinInputRootState(opts);
  }
  opts;
  attachment;
  inputAttachment;
  #isHoveringInput = false;
  #isFocused = simpleBox(false);
  #mirrorSelectionStart = null;
  #mirrorSelectionEnd = null;
  #previousValue = new Previous(() => this.opts.value.current ?? "");
  #regexPattern = derived(() => {
    if (typeof this.opts.pattern.current === "string") {
      return new RegExp(this.opts.pattern.current);
    } else {
      return this.opts.pattern.current;
    }
  });
  #prevInputMetadata = { prev: [null, null, "none"], willSyntheticBlur: false };
  #pwmb;
  #initialLoad;
  domContext;
  constructor(opts) {
    this.opts = opts;
    this.attachment = attachRef(this.opts.ref);
    this.inputAttachment = attachRef(this.opts.inputRef);
    this.domContext = new DOMContext(opts.ref);
    this.#initialLoad = {
      value: this.opts.value,
      isIOS: typeof window !== "undefined" && window?.CSS?.supports("-webkit-touch-callout", "none")
    };
    this.#pwmb = usePasswordManagerBadge({
      containerRef: this.opts.ref,
      inputRef: this.opts.inputRef,
      isFocused: this.#isFocused,
      pushPasswordManagerStrategy: this.opts.pushPasswordManagerStrategy,
      domContext: this.domContext
    });
    watch(
      [
        () => this.opts.value.current,
        () => this.opts.inputRef.current
      ],
      () => {
        syncTimeouts(
          () => {
            const input = this.opts.inputRef.current;
            if (!input) return;
            input.dispatchEvent(new Event("input"));
            const start = input.selectionStart;
            const end = input.selectionEnd;
            const dir = input.selectionDirection ?? "none";
            if (start !== null && end !== null) {
              this.#mirrorSelectionStart = start;
              this.#mirrorSelectionEnd = end;
              this.#prevInputMetadata.prev = [start, end, dir];
            }
          },
          this.domContext
        );
      }
    );
  }
  onkeydown = (e) => {
    const key = e.key;
    if (KEYS_TO_IGNORE.includes(key)) return;
    if (e.ctrlKey || e.metaKey) return;
    if (key && this.#regexPattern() && !this.#regexPattern().test(key)) {
      e.preventDefault();
    }
  };
  #rootStyles = derived(() => ({
    position: "relative",
    cursor: this.opts.disabled.current ? "default" : "text",
    userSelect: "none",
    WebkitUserSelect: "none",
    pointerEvents: "none"
  }));
  #rootProps = derived(() => ({
    id: this.opts.id.current,
    [pinInputAttrs.root]: "",
    style: this.#rootStyles(),
    ...this.attachment
  }));
  get rootProps() {
    return this.#rootProps();
  }
  set rootProps($$value) {
    return this.#rootProps($$value);
  }
  #inputWrapperProps = derived(() => ({
    style: { position: "absolute", inset: 0, pointerEvents: "none" }
  }));
  get inputWrapperProps() {
    return this.#inputWrapperProps();
  }
  set inputWrapperProps($$value) {
    return this.#inputWrapperProps($$value);
  }
  #inputStyle = derived(() => ({
    position: "absolute",
    inset: 0,
    width: this.#pwmb.willPushPwmBadge ? `calc(100% + ${this.#pwmb.PWM_BADGE_SPACE_WIDTH})` : "100%",
    clipPath: this.#pwmb.willPushPwmBadge ? `inset(0 ${this.#pwmb.PWM_BADGE_SPACE_WIDTH} 0 0)` : void 0,
    height: "100%",
    display: "flex",
    textAlign: this.opts.textAlign.current,
    opacity: "1",
    color: "transparent",
    pointerEvents: "all",
    background: "transparent",
    caretColor: "transparent",
    border: "0 solid transparent",
    outline: "0 solid transparent",
    boxShadow: "none",
    lineHeight: "1",
    letterSpacing: "-.5em",
    fontSize: "var(--bits-pin-input-root-height)",
    fontFamily: "monospace",
    fontVariantNumeric: "tabular-nums"
  }));
  #applyStyles() {
    const doc = this.domContext.getDocument();
    const styleEl = doc.createElement("style");
    styleEl.id = "pin-input-style";
    doc.head.appendChild(styleEl);
    if (styleEl.sheet) {
      const autoFillStyles = "background: transparent !important; color: transparent !important; border-color: transparent !important; opacity: 0 !important; box-shadow: none !important; -webkit-box-shadow: none !important; -webkit-text-fill-color: transparent !important;";
      safeInsertRule(styleEl.sheet, "[data-pin-input-input]::selection { background: transparent !important; color: transparent !important; }");
      safeInsertRule(styleEl.sheet, `[data-pin-input-input]:autofill { ${autoFillStyles} }`);
      safeInsertRule(styleEl.sheet, `[data-pin-input-input]:-webkit-autofill { ${autoFillStyles} }`);
      safeInsertRule(styleEl.sheet, `@supports (-webkit-touch-callout: none) { [data-pin-input-input] { letter-spacing: -.6em !important; font-weight: 100 !important; font-stretch: ultra-condensed; font-optical-sizing: none !important; left: -1px !important; right: 1px !important; } }`);
      safeInsertRule(styleEl.sheet, `[data-pin-input-input] + * { pointer-events: all !important; }`);
    }
  }
  #onDocumentSelectionChange = () => {
    const input = this.opts.inputRef.current;
    const container = this.opts.ref.current;
    if (!input || !container) return;
    if (this.domContext.getActiveElement() !== input) {
      this.#mirrorSelectionStart = null;
      this.#mirrorSelectionEnd = null;
      return;
    }
    const selStart = input.selectionStart;
    const selEnd = input.selectionEnd;
    const selDir = input.selectionDirection ?? "none";
    const maxLength = input.maxLength;
    const val = input.value;
    const prev = this.#prevInputMetadata.prev;
    let start = -1;
    let end = -1;
    let direction;
    if (val.length !== 0 && selStart !== null && selEnd !== null) {
      const isSingleCaret = selStart === selEnd;
      const isInsertMode = selStart === val.length && val.length < maxLength;
      if (isSingleCaret && !isInsertMode) {
        const c = selStart;
        if (c === 0) {
          start = 0;
          end = 1;
          direction = "forward";
        } else if (c === maxLength) {
          start = c - 1;
          end = c;
          direction = "backward";
        } else if (maxLength > 1 && val.length > 1) {
          let offset = 0;
          if (prev[0] !== null && prev[1] !== null) {
            direction = c < prev[1] ? "backward" : "forward";
            const wasPreviouslyInserting = prev[0] === prev[1] && prev[0] < maxLength;
            if (direction === "backward" && !wasPreviouslyInserting) {
              offset = -1;
            }
          }
          start = offset + c;
          end = offset + c + 1;
        }
      }
      if (start !== -1 && end !== -1 && start !== end) {
        this.opts.inputRef.current?.setSelectionRange(start, end, direction);
      }
    }
    const s = start !== -1 ? start : selStart;
    const e = end !== -1 ? end : selEnd;
    const dir = direction ?? selDir;
    this.#mirrorSelectionStart = s;
    this.#mirrorSelectionEnd = e;
    this.#prevInputMetadata.prev = [s, e, dir];
  };
  oninput = (e) => {
    const newValue = e.currentTarget.value.slice(0, this.opts.maxLength.current);
    if (newValue.length > 0 && this.#regexPattern() && !this.#regexPattern().test(newValue)) {
      e.preventDefault();
      return;
    }
    const maybeHasDeleted = typeof this.#previousValue.current === "string" && newValue.length < this.#previousValue.current.length;
    if (maybeHasDeleted) {
      this.domContext.getDocument().dispatchEvent(new Event("selectionchange"));
    }
    this.opts.value.current = newValue;
  };
  onfocus = (_) => {
    const input = this.opts.inputRef.current;
    if (input) {
      const start = Math.min(input.value.length, this.opts.maxLength.current - 1);
      const end = input.value.length;
      input.setSelectionRange(start, end);
      this.#mirrorSelectionStart = start;
      this.#mirrorSelectionEnd = end;
    }
    this.#isFocused.current = true;
  };
  onpaste = (e) => {
    const input = this.opts.inputRef.current;
    if (!input) return;
    const getNewValue = (finalContent) => {
      const start = input.selectionStart === null ? void 0 : input.selectionStart;
      const end = input.selectionEnd === null ? void 0 : input.selectionEnd;
      const isReplacing = start !== end;
      const initNewVal = this.opts.value.current;
      const newValueUncapped = isReplacing ? initNewVal.slice(0, start) + finalContent + initNewVal.slice(end) : initNewVal.slice(0, start) + finalContent + initNewVal.slice(start);
      return newValueUncapped.slice(0, this.opts.maxLength.current);
    };
    const isValueInvalid = (newValue2) => {
      return newValue2.length > 0 && this.#regexPattern() && !this.#regexPattern().test(newValue2);
    };
    if (!this.opts.pasteTransformer?.current && (!this.#initialLoad.isIOS || !e.clipboardData || !input)) {
      const newValue2 = getNewValue(e.clipboardData?.getData("text/plain"));
      if (isValueInvalid(newValue2)) {
        e.preventDefault();
      }
      return;
    }
    const _content = e.clipboardData?.getData("text/plain") ?? "";
    const content = this.opts.pasteTransformer?.current ? this.opts.pasteTransformer.current(_content) : _content;
    e.preventDefault();
    const newValue = getNewValue(content);
    if (isValueInvalid(newValue)) return;
    input.value = newValue;
    this.opts.value.current = newValue;
    const selStart = Math.min(newValue.length, this.opts.maxLength.current - 1);
    const selEnd = newValue.length;
    input.setSelectionRange(selStart, selEnd);
    this.#mirrorSelectionStart = selStart;
    this.#mirrorSelectionEnd = selEnd;
  };
  onmouseover = (_) => {
    this.#isHoveringInput = true;
  };
  onmouseleave = (_) => {
    this.#isHoveringInput = false;
  };
  onblur = (_) => {
    if (this.#prevInputMetadata.willSyntheticBlur) {
      this.#prevInputMetadata.willSyntheticBlur = false;
      return;
    }
    this.#isFocused.current = false;
  };
  #inputProps = derived(() => ({
    id: this.opts.inputId.current,
    style: this.#inputStyle(),
    autocomplete: this.opts.autocomplete.current || "one-time-code",
    "data-pin-input-input": "",
    "data-pin-input-input-mss": this.#mirrorSelectionStart,
    "data-pin-input-input-mse": this.#mirrorSelectionEnd,
    inputmode: this.opts.inputmode.current,
    pattern: this.#regexPattern()?.source,
    maxlength: this.opts.maxLength.current,
    value: this.opts.value.current,
    disabled: boolToTrueOrUndef(this.opts.disabled.current),
    //
    onpaste: this.onpaste,
    oninput: this.oninput,
    onkeydown: this.onkeydown,
    onmouseover: this.onmouseover,
    onmouseleave: this.onmouseleave,
    onfocus: this.onfocus,
    onblur: this.onblur,
    ...this.inputAttachment
  }));
  get inputProps() {
    return this.#inputProps();
  }
  set inputProps($$value) {
    return this.#inputProps($$value);
  }
  #cells = derived(() => Array.from({ length: this.opts.maxLength.current }).map((_, idx) => {
    const isActive = this.#isFocused.current && this.#mirrorSelectionStart !== null && this.#mirrorSelectionEnd !== null && (this.#mirrorSelectionStart === this.#mirrorSelectionEnd && idx === this.#mirrorSelectionStart || idx >= this.#mirrorSelectionStart && idx < this.#mirrorSelectionEnd);
    const char = this.opts.value.current[idx] !== void 0 ? this.opts.value.current[idx] : null;
    return { char, isActive, hasFakeCaret: isActive && char === null };
  }));
  #snippetProps = derived(() => ({
    cells: this.#cells(),
    isFocused: this.#isFocused.current,
    isHovering: this.#isHoveringInput
  }));
  get snippetProps() {
    return this.#snippetProps();
  }
  set snippetProps($$value) {
    return this.#snippetProps($$value);
  }
}
class PinInputCellState {
  static create(opts) {
    return new PinInputCellState(opts);
  }
  opts;
  attachment;
  constructor(opts) {
    this.opts = opts;
    this.attachment = attachRef(this.opts.ref);
  }
  #props = derived(() => ({
    id: this.opts.id.current,
    [pinInputAttrs.cell]: "",
    "data-active": this.opts.cell.current.isActive ? "" : void 0,
    "data-inactive": !this.opts.cell.current.isActive ? "" : void 0,
    ...this.attachment
  }));
  get props() {
    return this.#props();
  }
  set props($$value) {
    return this.#props($$value);
  }
}
function syncTimeouts(cb, domContext) {
  const t1 = domContext.setTimeout(cb, 0);
  const t2 = domContext.setTimeout(cb, 10);
  const t3 = domContext.setTimeout(cb, 50);
  return [t1, t2, t3];
}
function safeInsertRule(sheet, rule) {
  try {
    sheet.insertRule(rule);
  } catch {
    console.error("pin input could not insert CSS rule:", rule);
  }
}
function Pin_input$1($$renderer, $$props) {
  $$renderer.component(($$renderer2) => {
    const uid = props_id($$renderer2);
    let {
      id = createId(uid),
      inputId = `${createId(uid)}-input`,
      ref = null,
      inputRef = null,
      maxlength = 6,
      textalign = "left",
      pattern,
      inputmode = "numeric",
      onComplete = noop,
      pushPasswordManagerStrategy = "increase-width",
      class: containerClass = "",
      children,
      autocomplete = "one-time-code",
      disabled = false,
      value = "",
      onValueChange = noop,
      pasteTransformer,
      $$slots,
      $$events,
      ...restProps
    } = $$props;
    const rootState = PinInputRootState.create({
      id: boxWith(() => id),
      ref: boxWith(() => ref, (v) => ref = v),
      inputRef: boxWith(() => inputRef, (v) => inputRef = v),
      inputId: boxWith(() => inputId),
      autocomplete: boxWith(() => autocomplete),
      maxLength: boxWith(() => maxlength),
      textAlign: boxWith(() => textalign),
      disabled: boxWith(() => disabled),
      inputmode: boxWith(() => inputmode),
      pattern: boxWith(() => pattern),
      onComplete: boxWith(() => onComplete),
      value: boxWith(() => value, (v) => {
        value = v;
        onValueChange(v);
      }),
      pushPasswordManagerStrategy: boxWith(() => pushPasswordManagerStrategy),
      pasteTransformer: boxWith(() => pasteTransformer)
    });
    const mergedInputProps = derived(() => mergeProps(restProps, rootState.inputProps));
    const mergedRootProps = derived(() => mergeProps(rootState.rootProps, { class: containerClass }));
    const mergedInputWrapperProps = derived(() => mergeProps(rootState.inputWrapperProps, {}));
    $$renderer2.push(`<div${attributes({ ...mergedRootProps() })}>`);
    children?.($$renderer2, rootState.snippetProps);
    $$renderer2.push(`<!----> <div${attributes({ ...mergedInputWrapperProps() })}><input${attributes({ ...mergedInputProps() }, void 0, void 0, void 0, 4)}/></div></div>`);
    bind_props($$props, { ref, inputRef, value });
  });
}
function Pin_input_cell($$renderer, $$props) {
  $$renderer.component(($$renderer2) => {
    const uid = props_id($$renderer2);
    let {
      id = createId(uid),
      ref = null,
      cell,
      child,
      children,
      $$slots,
      $$events,
      ...restProps
    } = $$props;
    const cellState = PinInputCellState.create({
      id: boxWith(() => id),
      ref: boxWith(() => ref, (v) => ref = v),
      cell: boxWith(() => cell)
    });
    const mergedProps = derived(() => mergeProps(restProps, cellState.props));
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
function Pin_input($$renderer, $$props) {
  $$renderer.component(($$renderer2) => {
    let {
      value = "",
      maxlength = 6,
      disabled = false,
      autofocus = false,
      onComplete,
      class: className,
      $$slots,
      $$events,
      ...restProps
    } = $$props;
    function pasteTransformer(text) {
      return text.replace(/\D/g, "").slice(0, maxlength);
    }
    let $$settled = true;
    let $$inner_renderer;
    function $$render_inner($$renderer3) {
      {
        let children = function($$renderer4, { cells }) {
          $$renderer4.push(`<div class="flex gap-1.5 sm:gap-2"><!--[-->`);
          const each_array = ensure_array_like(cells.slice(0, 3));
          for (let $$index = 0, $$length = each_array.length; $$index < $$length; $$index++) {
            let cell = each_array[$$index];
            if (Pin_input_cell) {
              $$renderer4.push("<!--[-->");
              Pin_input_cell($$renderer4, {
                cell,
                class: cn("relative flex size-10 sm:size-12 items-center justify-center rounded-lg border bg-background/50 font-mono text-base sm:text-lg font-semibold shadow-xs transition-all pointer-events-none select-none", cell.isActive ? "border-primary ring-2 ring-primary/40 ring-offset-1 ring-offset-background" : "border-input data-[active]:border-primary data-[active]:ring-2 data-[active]:ring-primary/40"),
                children: ($$renderer5) => {
                  if (cell.char !== null) {
                    $$renderer5.push("<!--[0-->");
                    $$renderer5.push(`${escape_html(cell.char)}`);
                  } else {
                    $$renderer5.push("<!--[-1-->");
                  }
                  $$renderer5.push(`<!--]--> `);
                  if (cell.hasFakeCaret) {
                    $$renderer5.push("<!--[0-->");
                    $$renderer5.push(`<div class="pointer-events-none absolute inset-0 flex items-center justify-center"><div class="h-5 sm:h-6 w-[2px] animate-caret-blink bg-primary rounded-full pointer-events-none" style="animation: caret-blink 1.2s ease-out infinite;"></div></div>`);
                  } else if (cell.isActive) {
                    $$renderer5.push("<!--[1-->");
                    $$renderer5.push(`<div class="pointer-events-none absolute inset-x-2 bottom-1.5 flex justify-center"><div class="h-0.5 w-4 animate-caret-blink bg-primary rounded-full pointer-events-none" style="animation: caret-blink 1.2s ease-out infinite;"></div></div>`);
                  } else {
                    $$renderer5.push("<!--[-1-->");
                  }
                  $$renderer5.push(`<!--]-->`);
                },
                $$slots: { default: true }
              });
              $$renderer4.push("<!--]-->");
            } else {
              $$renderer4.push("<!--[!-->");
              $$renderer4.push("<!--]-->");
            }
          }
          $$renderer4.push(`<!--]--></div> <div class="flex size-4 items-center justify-center text-muted-foreground/60 font-bold">•</div> <div class="flex gap-1.5 sm:gap-2"><!--[-->`);
          const each_array_1 = ensure_array_like(cells.slice(3, 6));
          for (let $$index_1 = 0, $$length = each_array_1.length; $$index_1 < $$length; $$index_1++) {
            let cell = each_array_1[$$index_1];
            if (Pin_input_cell) {
              $$renderer4.push("<!--[-->");
              Pin_input_cell($$renderer4, {
                cell,
                class: cn("relative flex size-10 sm:size-12 items-center justify-center rounded-lg border bg-background/50 font-mono text-base sm:text-lg font-semibold shadow-xs transition-all pointer-events-none select-none", cell.isActive ? "border-primary ring-2 ring-primary/40 ring-offset-1 ring-offset-background" : "border-input data-[active]:border-primary data-[active]:ring-2 data-[active]:ring-primary/40"),
                children: ($$renderer5) => {
                  if (cell.char !== null) {
                    $$renderer5.push("<!--[0-->");
                    $$renderer5.push(`${escape_html(cell.char)}`);
                  } else {
                    $$renderer5.push("<!--[-1-->");
                  }
                  $$renderer5.push(`<!--]--> `);
                  if (cell.hasFakeCaret) {
                    $$renderer5.push("<!--[0-->");
                    $$renderer5.push(`<div class="pointer-events-none absolute inset-0 flex items-center justify-center"><div class="h-5 sm:h-6 w-[2px] animate-caret-blink bg-primary rounded-full pointer-events-none" style="animation: caret-blink 1.2s ease-out infinite;"></div></div>`);
                  } else if (cell.isActive) {
                    $$renderer5.push("<!--[1-->");
                    $$renderer5.push(`<div class="pointer-events-none absolute inset-x-2 bottom-1.5 flex justify-center"><div class="h-0.5 w-4 animate-caret-blink bg-primary rounded-full pointer-events-none" style="animation: caret-blink 1.2s ease-out infinite;"></div></div>`);
                  } else {
                    $$renderer5.push("<!--[-1-->");
                  }
                  $$renderer5.push(`<!--]-->`);
                },
                $$slots: { default: true }
              });
              $$renderer4.push("<!--]-->");
            } else {
              $$renderer4.push("<!--[!-->");
              $$renderer4.push("<!--]-->");
            }
          }
          $$renderer4.push(`<!--]--></div>`);
        };
        if (Pin_input$1) {
          $$renderer3.push("<!--[-->");
          Pin_input$1($$renderer3, spread_props([
            {
              maxlength,
              disabled,
              autofocus,
              onComplete,
              pasteTransformer,
              class: cn("flex items-center gap-1.5 sm:gap-2 cursor-text", className)
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
              children,
              $$slots: { default: true }
            }
          ]));
          $$renderer3.push("<!--]-->");
        } else {
          $$renderer3.push("<!--[!-->");
          $$renderer3.push("<!--]-->");
        }
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
export {
  Pin_input as P
};
