import { z as attributes, A as clsx, C as cn, l as bind_props } from "../app.js";
function Card($$renderer, $$props) {
  $$renderer.component(($$renderer2) => {
    let {
      ref = null,
      class: className,
      variant = "default",
      children,
      $$slots,
      $$events,
      ...restProps
    } = $$props;
    $$renderer2.push(`<div${attributes({
      "data-slot": "card",
      class: clsx(cn(
        "text-card-foreground flex flex-col gap-5 py-5 rounded-[var(--radius,0.5rem)] transition-[box-shadow,transform,border-color] duration-200",
        variant === "glass" ? "glass-panel border-border/60 shadow-md hover:shadow-lg" : variant === "craft" ? "craft-card-depth" : variant === "solid" ? "border border-border/80 bg-card shadow-[0_1px_2px_oklch(0_0_0/0.04),0_8px_24px_-12px_oklch(0.31_0.02_148/0.18)] dark:shadow-[inset_0_1px_0_0_rgba(255,255,255,0.08),0_16px_32px_-16px_oklch(0.31_0.02_148/0.22)]" : "border border-border/70 bg-card/85 dark:bg-card/75 backdrop-blur-md shadow-[inset_0_1px_0_0_rgba(255,255,255,0.45),0_1px_2px_oklch(0_0_0/0.04),0_8px_24px_-12px_oklch(0.31_0.02_148/0.18)] dark:shadow-[inset_0_1px_0_0_rgba(255,255,255,0.08),0_16px_32px_-16px_oklch(0.31_0.02_148/0.22)] motion-safe:hover:-translate-y-0.5 hover:border-primary/40 hover:shadow-lg",
        className
      )),
      ...restProps
    })}>`);
    children?.($$renderer2);
    $$renderer2.push(`<!----></div>`);
    bind_props($$props, { ref });
  });
}
function Card_content($$renderer, $$props) {
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
      "data-slot": "card-content",
      class: clsx(cn("px-6", className)),
      ...restProps
    })}>`);
    children?.($$renderer2);
    $$renderer2.push(`<!----></div>`);
    bind_props($$props, { ref });
  });
}
export {
  Card as C,
  Card_content as a
};
