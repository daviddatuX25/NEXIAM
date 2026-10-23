import { c as attr_class, e as escape_html, B as Button, s as stringify } from "../app.js";
import { L as Link } from "./Link-B7ks7rBK.js";
import { C as Chevron_left } from "./chevron-left-DCSOZwZl.js";
import { C as Chevron_right } from "./chevron-right-BHu-PGOg.js";
function SimplePagination($$renderer, $$props) {
  $$renderer.component(($$renderer2) => {
    let { data, variant = "table", class: className = "" } = $$props;
    if (data != null && data.last_page > 1) {
      $$renderer2.push("<!--[0-->");
      $$renderer2.push(`<div${attr_class(`mt-4 pt-4 border-t border-border/50 flex items-center ${variant === "centered" ? "justify-center" : "justify-between"} ${stringify(className)}`)}>`);
      if (variant === "table") {
        $$renderer2.push("<!--[0-->");
        $$renderer2.push(`<div class="text-sm text-muted-foreground hidden sm:block">Showing <span class="font-medium">${escape_html(data.from || 0)}</span> to <span class="font-medium">${escape_html(data.to || 0)}</span> of <span class="font-medium">${escape_html(data.total)}</span> results</div>`);
      } else {
        $$renderer2.push("<!--[-1-->");
      }
      $$renderer2.push(`<!--]--> <nav aria-label="Pagination Navigation" class="flex items-center space-x-2">`);
      Link($$renderer2, {
        href: data.prev_page_url,
        preserveState: true,
        preserveScroll: true,
        disabled: !data.prev_page_url,
        children: ($$renderer3) => {
          Button($$renderer3, {
            variant: "outline",
            size: "sm",
            class: "h-8 w-8 p-0",
            disabled: !data.prev_page_url,
            children: ($$renderer4) => {
              $$renderer4.push(`<span class="sr-only">Previous</span> `);
              Chevron_left($$renderer4, { class: "h-4 w-4" });
              $$renderer4.push(`<!---->`);
            },
            $$slots: { default: true }
          });
        },
        $$slots: { default: true }
      });
      $$renderer2.push(`<!----> <div class="text-sm font-medium">Page ${escape_html(data.current_page)} of ${escape_html(data.last_page)}</div> `);
      Link($$renderer2, {
        href: data.next_page_url,
        preserveState: true,
        preserveScroll: true,
        disabled: !data.next_page_url,
        children: ($$renderer3) => {
          Button($$renderer3, {
            variant: "outline",
            size: "sm",
            class: "h-8 w-8 p-0",
            disabled: !data.next_page_url,
            children: ($$renderer4) => {
              $$renderer4.push(`<span class="sr-only">Next</span> `);
              Chevron_right($$renderer4, { class: "h-4 w-4" });
              $$renderer4.push(`<!---->`);
            },
            $$slots: { default: true }
          });
        },
        $$slots: { default: true }
      });
      $$renderer2.push(`<!----></nav></div>`);
    } else {
      $$renderer2.push("<!--[-1-->");
    }
    $$renderer2.push(`<!--]-->`);
  });
}
export {
  SimplePagination as S
};
