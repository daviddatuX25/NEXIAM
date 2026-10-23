import { o as onDestroy, B as Button, c as attr_class, a as ensure_array_like, s as stringify, b as attr, e as escape_html } from "../app.js";
import { E as Ellipsis_vertical } from "./settings-C5xOKpV3.js";
function ActionDropdown($$renderer, $$props) {
  $$renderer.component(($$renderer2) => {
    let {
      items = [],
      triggerIcon: TriggerIcon = Ellipsis_vertical,
      align = "right",
      confirmDelete = false
    } = $$props;
    let isOpen = false;
    function toggle() {
      if (items.length === 0) {
        isOpen = false;
        return;
      }
      isOpen = !isOpen;
    }
    function close() {
      isOpen = false;
    }
    function handleKeydown(e) {
      if (e.key === "Escape") close();
    }
    function handleClickOutside(e) {
    }
    onDestroy(() => {
      if (typeof window !== "undefined") {
        window.removeEventListener("click", handleClickOutside);
        window.removeEventListener("keydown", handleKeydown);
      }
    });
    $$renderer2.push(`<div class="relative inline-block text-left">`);
    Button($$renderer2, {
      variant: "ghost",
      size: "icon",
      class: "h-8 w-8 rounded-full hover:bg-muted transition-colors",
      onclick: toggle,
      "aria-haspopup": "true",
      "aria-expanded": isOpen,
      children: ($$renderer3) => {
        if (TriggerIcon) {
          $$renderer3.push("<!--[-->");
          TriggerIcon($$renderer3, { class: "h-4 w-4" });
          $$renderer3.push("<!--]-->");
        } else {
          $$renderer3.push("<!--[!-->");
          $$renderer3.push("<!--]-->");
        }
        $$renderer3.push(` <span class="sr-only">More actions</span>`);
      },
      $$slots: { default: true }
    });
    $$renderer2.push(`<!----> `);
    if (isOpen && items.length > 0) {
      $$renderer2.push("<!--[0-->");
      $$renderer2.push(`<div${attr_class(`absolute z-[100] mt-1 w-48 rounded-lg border border-border bg-card shadow-xl ring-1 ring-black/5 focus:outline-none overflow-hidden animate-in fade-in zoom-in-95 duration-100 ${align === "right" ? "right-0" : "left-0"}`)} role="menu" aria-orientation="vertical"><div class="py-1" role="none"><!--[-->`);
      const each_array = ensure_array_like(items);
      for (let $$index = 0, $$length = each_array.length; $$index < $$length; $$index++) {
        let item = each_array[$$index];
        if (item.method === "delete") {
          $$renderer2.push("<!--[0-->");
          $$renderer2.push(`<button type="button"${attr_class(`group flex w-full items-center px-3 py-2 text-left text-sm hover:bg-accent transition-colors disabled:opacity-50 disabled:cursor-not-allowed ${item.danger ? "text-destructive hover:text-destructive" : "text-foreground"} ${stringify(item.class ?? "")}`)} role="menuitem"${attr("disabled", item.disabled, true)}>`);
          if (item.icon) {
            $$renderer2.push("<!--[0-->");
            if (item.icon) {
              $$renderer2.push("<!--[-->");
              item.icon($$renderer2, {
                class: `mr-2.5 h-4 w-4 ${item.danger ? "text-destructive" : "text-muted-foreground group-hover:text-foreground"}`
              });
              $$renderer2.push("<!--]-->");
            } else {
              $$renderer2.push("<!--[!-->");
              $$renderer2.push("<!--]-->");
            }
          } else {
            $$renderer2.push("<!--[-1-->");
          }
          $$renderer2.push(`<!--]--> ${escape_html(item.label)}</button>`);
        } else if (item.method && item.method !== "get" && item.method !== "delete" && item.href) {
          $$renderer2.push("<!--[1-->");
          $$renderer2.push(`<form${attr("action", item.href)} method="post" class="contents"><input type="hidden" name="_method"${attr("value", item.method)}/> <button type="submit"${attr_class(`group flex w-full items-center px-3 py-2 text-left text-sm text-foreground hover:bg-accent transition-colors disabled:opacity-50 disabled:cursor-not-allowed ${stringify(item.class ?? "")}`)} role="menuitem"${attr("disabled", item.disabled, true)}>`);
          if (item.icon) {
            $$renderer2.push("<!--[0-->");
            if (item.icon) {
              $$renderer2.push("<!--[-->");
              item.icon($$renderer2, {
                class: "mr-2.5 h-4 w-4 text-muted-foreground group-hover:text-foreground transition-colors"
              });
              $$renderer2.push("<!--]-->");
            } else {
              $$renderer2.push("<!--[!-->");
              $$renderer2.push("<!--]-->");
            }
          } else {
            $$renderer2.push("<!--[-1-->");
          }
          $$renderer2.push(`<!--]--> ${escape_html(item.label)}</button></form>`);
        } else if (item.href && item.external) {
          $$renderer2.push("<!--[2-->");
          $$renderer2.push(`<a${attr("href", item.href)} target="_blank" rel="noopener noreferrer"${attr_class(`group flex w-full items-center px-3 py-2 text-left text-sm text-foreground hover:bg-accent transition-colors ${stringify(item.class ?? "")}`)} role="menuitem">`);
          if (item.icon) {
            $$renderer2.push("<!--[0-->");
            if (item.icon) {
              $$renderer2.push("<!--[-->");
              item.icon($$renderer2, {
                class: "mr-2.5 h-4 w-4 text-muted-foreground group-hover:text-foreground transition-colors"
              });
              $$renderer2.push("<!--]-->");
            } else {
              $$renderer2.push("<!--[!-->");
              $$renderer2.push("<!--]-->");
            }
          } else {
            $$renderer2.push("<!--[-1-->");
          }
          $$renderer2.push(`<!--]--> ${escape_html(item.label)}</a>`);
        } else if (item.href) {
          $$renderer2.push("<!--[3-->");
          $$renderer2.push(`<button type="button"${attr_class(`group flex w-full items-center px-3 py-2 text-left text-sm text-foreground hover:bg-accent transition-colors disabled:opacity-50 disabled:cursor-not-allowed ${stringify(item.class ?? "")}`)} role="menuitem"${attr("disabled", item.disabled, true)}>`);
          if (item.icon) {
            $$renderer2.push("<!--[0-->");
            if (item.icon) {
              $$renderer2.push("<!--[-->");
              item.icon($$renderer2, {
                class: "mr-2.5 h-4 w-4 text-muted-foreground group-hover:text-foreground"
              });
              $$renderer2.push("<!--]-->");
            } else {
              $$renderer2.push("<!--[!-->");
              $$renderer2.push("<!--]-->");
            }
          } else {
            $$renderer2.push("<!--[-1-->");
          }
          $$renderer2.push(`<!--]--> ${escape_html(item.label)}</button>`);
        } else if (item.onclick) {
          $$renderer2.push("<!--[4-->");
          $$renderer2.push(`<button type="button"${attr_class(`group flex w-full items-center px-3 py-2 text-left text-sm text-foreground hover:bg-accent transition-colors disabled:opacity-50 disabled:cursor-not-allowed ${stringify(item.class ?? "")}`)} role="menuitem"${attr("disabled", item.disabled, true)}>`);
          if (item.icon) {
            $$renderer2.push("<!--[0-->");
            if (item.icon) {
              $$renderer2.push("<!--[-->");
              item.icon($$renderer2, {
                class: "mr-2.5 h-4 w-4 text-muted-foreground group-hover:text-foreground"
              });
              $$renderer2.push("<!--]-->");
            } else {
              $$renderer2.push("<!--[!-->");
              $$renderer2.push("<!--]-->");
            }
          } else {
            $$renderer2.push("<!--[-1-->");
          }
          $$renderer2.push(`<!--]--> ${escape_html(item.label)}</button>`);
        } else {
          $$renderer2.push("<!--[-1-->");
        }
        $$renderer2.push(`<!--]-->`);
      }
      $$renderer2.push(`<!--]--></div></div>`);
    } else {
      $$renderer2.push("<!--[-1-->");
    }
    $$renderer2.push(`<!--]--></div>`);
  });
}
export {
  ActionDropdown as A
};
