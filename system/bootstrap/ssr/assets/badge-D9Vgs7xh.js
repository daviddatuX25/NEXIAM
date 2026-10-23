import { z as attributes, A as clsx, C as cn } from "../app.js";
import { tv } from "tailwind-variants";
const badgeVariants = tv({
  base: "inline-flex rounded-md border px-2.5 py-1 text-xs font-medium transition-colors",
  variants: {
    variant: {
      default: "border-transparent bg-primary text-primary-foreground",
      secondary: "border-transparent bg-secondary text-secondary-foreground",
      destructive: "border-transparent bg-destructive text-destructive-foreground",
      outline: "border-border text-foreground dark:border-border",
      success: "border-emerald-500 text-emerald-700 dark:border-emerald-400 dark:text-emerald-400",
      warning: "border-amber-500 text-amber-700 dark:border-amber-400 dark:text-amber-400",
      danger: "border-red-500 text-red-700 dark:border-red-400 dark:text-red-400",
      muted: "border-border text-muted-foreground dark:border-muted-foreground/50"
    }
  },
  defaultVariants: { variant: "default" }
});
function Badge($$renderer, $$props) {
  $$renderer.component(($$renderer2) => {
    let {
      class: className,
      variant = "default",
      children,
      $$slots,
      $$events,
      ...restProps
    } = $$props;
    $$renderer2.push(`<span${attributes({
      class: clsx(cn(badgeVariants({ variant }), className)),
      "data-slot": "badge",
      ...restProps
    })}>`);
    children?.($$renderer2);
    $$renderer2.push(`<!----></span>`);
  });
}
export {
  Badge as B
};
