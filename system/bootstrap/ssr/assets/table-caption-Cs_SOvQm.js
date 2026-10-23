import { z as attributes, A as clsx, C as cn, l as bind_props } from "../app.js";
function Table_caption($$renderer, $$props) {
  $$renderer.component(($$renderer2) => {
    let {
      ref = null,
      class: className,
      children,
      $$slots,
      $$events,
      ...restProps
    } = $$props;
    $$renderer2.push(`<caption${attributes({
      "data-slot": "table-caption",
      class: clsx(cn("text-muted-foreground mt-4 text-sm", className)),
      ...restProps
    })}>`);
    children?.($$renderer2);
    $$renderer2.push(`<!----></caption>`);
    bind_props($$props, { ref });
  });
}
export {
  Table_caption as T
};
