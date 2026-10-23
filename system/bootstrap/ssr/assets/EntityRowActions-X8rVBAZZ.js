import { o as onDestroy, c as attr_class, B as Button, e as escape_html, d as derived, s as stringify, a as ensure_array_like, b as attr } from "../app.js";
import { A as Arrow_right } from "./arrow-right-CzynHiIo.js";
import { L as Link } from "./Link-B7ks7rBK.js";
import { E as Ellipsis } from "./ellipsis-CPRDNN0k.js";
function EntityRowActions($$renderer, $$props) {
  $$renderer.component(($$renderer2) => {
    let {
      primaryAction = null,
      overflowItems = [],
      dense = false,
      align = "right",
      class: className = ""
    } = $$props;
    let isOpen = false;
    const visibleOverflowItems = derived(() => overflowItems.filter((item) => item && !item.hidden));
    const standardItems = derived(() => visibleOverflowItems().filter((item) => !item.destructive));
    const destructiveItems = derived(() => visibleOverflowItems().filter((item) => item.destructive));
    function toggleDropdown(e) {
      e.stopPropagation();
      isOpen = !isOpen;
    }
    function closeDropdown() {
      isOpen = false;
    }
    function handleClickOutside(e) {
    }
    function handleKeydown(e) {
      if (e.key === "Escape") {
        closeDropdown();
      }
    }
    onDestroy(() => {
      if (typeof window !== "undefined") {
        window.removeEventListener("click", handleClickOutside);
        window.removeEventListener("keydown", handleKeydown);
      }
    });
    $$renderer2.push(`<div${attr_class(`inline-flex items-center gap-1.5 ${stringify(className)}`)} data-action-cell="" role="presentation">`);
    if (primaryAction && primaryAction.label) {
      $$renderer2.push("<!--[0-->");
      const PrimaryIcon = primaryAction.icon ?? Arrow_right;
      if (primaryAction.href) {
        $$renderer2.push("<!--[0-->");
        Link($$renderer2, {
          href: primaryAction.href,
          class: "inline-block",
          children: ($$renderer3) => {
            Button($$renderer3, {
              size: dense ? "xs" : "sm",
              variant: primaryAction.variant ?? "outline",
              class: "font-semibold transition-all active:scale-[0.98]",
              children: ($$renderer4) => {
                $$renderer4.push(`<span>${escape_html(primaryAction.label)}</span> `);
                if (primaryAction.icon !== false) {
                  $$renderer4.push("<!--[0-->");
                  if (PrimaryIcon) {
                    $$renderer4.push("<!--[-->");
                    PrimaryIcon($$renderer4, { class: "ml-1 size-3.5" });
                    $$renderer4.push("<!--]-->");
                  } else {
                    $$renderer4.push("<!--[!-->");
                    $$renderer4.push("<!--]-->");
                  }
                } else {
                  $$renderer4.push("<!--[-1-->");
                }
                $$renderer4.push(`<!--]-->`);
              },
              $$slots: { default: true }
            });
          },
          $$slots: { default: true }
        });
      } else {
        $$renderer2.push("<!--[-1-->");
        Button($$renderer2, {
          size: dense ? "xs" : "sm",
          variant: primaryAction.variant ?? "outline",
          class: "font-semibold transition-all active:scale-[0.98]",
          onclick: (e) => {
            e.stopPropagation();
            primaryAction.onclick?.();
          },
          children: ($$renderer3) => {
            $$renderer3.push(`<span>${escape_html(primaryAction.label)}</span> `);
            if (primaryAction.icon !== false) {
              $$renderer3.push("<!--[0-->");
              if (PrimaryIcon) {
                $$renderer3.push("<!--[-->");
                PrimaryIcon($$renderer3, { class: "ml-1 size-3.5" });
                $$renderer3.push("<!--]-->");
              } else {
                $$renderer3.push("<!--[!-->");
                $$renderer3.push("<!--]-->");
              }
            } else {
              $$renderer3.push("<!--[-1-->");
            }
            $$renderer3.push(`<!--]-->`);
          },
          $$slots: { default: true }
        });
      }
      $$renderer2.push(`<!--]-->`);
    } else {
      $$renderer2.push("<!--[-1-->");
    }
    $$renderer2.push(`<!--]--> `);
    if (visibleOverflowItems().length > 0) {
      $$renderer2.push("<!--[0-->");
      $$renderer2.push(`<div class="relative">`);
      Button($$renderer2, {
        type: "button",
        size: dense ? "icon-xs" : "icon-sm",
        variant: "ghost",
        class: "text-muted-foreground hover:text-foreground active:scale-[0.95]",
        "aria-label": "More actions",
        "aria-haspopup": "menu",
        "aria-expanded": isOpen,
        onclick: toggleDropdown,
        children: ($$renderer3) => {
          Ellipsis($$renderer3, { class: "size-4" });
        },
        $$slots: { default: true }
      });
      $$renderer2.push(`<!----> `);
      if (isOpen) {
        $$renderer2.push("<!--[0-->");
        $$renderer2.push(`<div${attr_class(`absolute ${align === "right" ? "right-0" : "left-0"} top-full z-40 mt-1 min-w-48 rounded-xl border border-border bg-popover p-1 shadow-lg text-xs space-y-0.5`)} role="menu"><!--[-->`);
        const each_array = ensure_array_like(standardItems());
        for (let idx = 0, $$length = each_array.length; idx < $$length; idx++) {
          let item = each_array[idx];
          if (item.href) {
            $$renderer2.push("<!--[0-->");
            Link($$renderer2, {
              href: item.href,
              class: "flex w-full items-center gap-2 rounded-lg px-2.5 py-1.5 font-medium text-foreground hover:bg-muted transition-colors text-left",
              role: "menuitem",
              onclick: closeDropdown,
              children: ($$renderer3) => {
                if (item.icon) {
                  $$renderer3.push("<!--[0-->");
                  const Icon = item.icon;
                  if (Icon) {
                    $$renderer3.push("<!--[-->");
                    Icon($$renderer3, { class: "size-3.5 text-muted-foreground shrink-0" });
                    $$renderer3.push("<!--]-->");
                  } else {
                    $$renderer3.push("<!--[!-->");
                    $$renderer3.push("<!--]-->");
                  }
                } else {
                  $$renderer3.push("<!--[-1-->");
                }
                $$renderer3.push(`<!--]--> <span class="truncate">${escape_html(item.label)}</span>`);
              },
              $$slots: { default: true }
            });
          } else {
            $$renderer2.push("<!--[-1-->");
            $$renderer2.push(`<button type="button" class="flex w-full items-center gap-2 rounded-lg px-2.5 py-1.5 font-medium text-foreground hover:bg-muted transition-colors text-left disabled:opacity-50 disabled:cursor-not-allowed" role="menuitem"${attr("disabled", item.disabled, true)}>`);
            if (item.icon) {
              $$renderer2.push("<!--[0-->");
              const Icon = item.icon;
              if (Icon) {
                $$renderer2.push("<!--[-->");
                Icon($$renderer2, { class: "size-3.5 text-muted-foreground shrink-0" });
                $$renderer2.push("<!--]-->");
              } else {
                $$renderer2.push("<!--[!-->");
                $$renderer2.push("<!--]-->");
              }
            } else {
              $$renderer2.push("<!--[-1-->");
            }
            $$renderer2.push(`<!--]--> <span class="truncate">${escape_html(item.label)}</span></button>`);
          }
          $$renderer2.push(`<!--]-->`);
        }
        $$renderer2.push(`<!--]--> `);
        if (destructiveItems().length > 0) {
          $$renderer2.push("<!--[0-->");
          if (standardItems().length > 0) {
            $$renderer2.push("<!--[0-->");
            $$renderer2.push(`<div class="my-1 border-t border-border/60"></div>`);
          } else {
            $$renderer2.push("<!--[-1-->");
          }
          $$renderer2.push(`<!--]--> <!--[-->`);
          const each_array_1 = ensure_array_like(destructiveItems());
          for (let idx = 0, $$length = each_array_1.length; idx < $$length; idx++) {
            let item = each_array_1[idx];
            if (item.href) {
              $$renderer2.push("<!--[0-->");
              Link($$renderer2, {
                href: item.href,
                class: "flex w-full items-center gap-2 rounded-lg px-2.5 py-1.5 font-medium text-destructive hover:bg-destructive/10 transition-colors text-left",
                role: "menuitem",
                onclick: closeDropdown,
                children: ($$renderer3) => {
                  if (item.icon) {
                    $$renderer3.push("<!--[0-->");
                    const Icon = item.icon;
                    if (Icon) {
                      $$renderer3.push("<!--[-->");
                      Icon($$renderer3, { class: "size-3.5 text-destructive shrink-0" });
                      $$renderer3.push("<!--]-->");
                    } else {
                      $$renderer3.push("<!--[!-->");
                      $$renderer3.push("<!--]-->");
                    }
                  } else {
                    $$renderer3.push("<!--[-1-->");
                  }
                  $$renderer3.push(`<!--]--> <span class="truncate">${escape_html(item.label)}</span>`);
                },
                $$slots: { default: true }
              });
            } else {
              $$renderer2.push("<!--[-1-->");
              $$renderer2.push(`<button type="button" class="flex w-full items-center gap-2 rounded-lg px-2.5 py-1.5 font-medium text-destructive hover:bg-destructive/10 transition-colors text-left disabled:opacity-50 disabled:cursor-not-allowed" role="menuitem"${attr("disabled", item.disabled, true)}>`);
              if (item.icon) {
                $$renderer2.push("<!--[0-->");
                const Icon = item.icon;
                if (Icon) {
                  $$renderer2.push("<!--[-->");
                  Icon($$renderer2, { class: "size-3.5 text-destructive shrink-0" });
                  $$renderer2.push("<!--]-->");
                } else {
                  $$renderer2.push("<!--[!-->");
                  $$renderer2.push("<!--]-->");
                }
              } else {
                $$renderer2.push("<!--[-1-->");
              }
              $$renderer2.push(`<!--]--> <span class="truncate">${escape_html(item.label)}</span></button>`);
            }
            $$renderer2.push(`<!--]-->`);
          }
          $$renderer2.push(`<!--]-->`);
        } else {
          $$renderer2.push("<!--[-1-->");
        }
        $$renderer2.push(`<!--]--></div>`);
      } else {
        $$renderer2.push("<!--[-1-->");
      }
      $$renderer2.push(`<!--]--></div>`);
    } else {
      $$renderer2.push("<!--[-1-->");
    }
    $$renderer2.push(`<!--]--></div>`);
  });
}
export {
  EntityRowActions as E
};
