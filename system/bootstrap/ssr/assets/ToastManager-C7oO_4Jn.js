import { g as sanitize_props, i as spread_props, j as slot, c as attr_class, b as attr, a as ensure_array_like, x as store_get, s as stringify, e as escape_html, y as unsubscribe_stores, d as derived, F as toasts } from "../app.js";
import { I as Icon } from "./Icon-Dal7PqKK.js";
import { C as Circle_x } from "./circle-x-BQrhluGu.js";
import { T as Triangle_alert } from "./triangle-alert-CUvuB-HL.js";
import { I as Info } from "./info-C3MCzlQW.js";
import { X } from "./x-CnxrMbMn.js";
function Circle_check_big($$renderer, $$props) {
  const $$sanitized_props = sanitize_props($$props);
  const iconNode = [
    ["path", { "d": "M21.801 10A10 10 0 1 1 17 3.335" }],
    ["path", { "d": "m9 11 3 3L22 4" }]
  ];
  Icon($$renderer, spread_props([
    { name: "circle-check-big" },
    $$sanitized_props,
    {
      /**
       * @component @name CircleCheckBig
       * @description Lucide SVG icon component, renders SVG Element with children.
       *
       * @preview ![img](data:image/svg+xml;base64,PHN2ZyAgeG1sbnM9Imh0dHA6Ly93d3cudzMub3JnLzIwMDAvc3ZnIgogIHdpZHRoPSIyNCIKICBoZWlnaHQ9IjI0IgogIHZpZXdCb3g9IjAgMCAyNCAyNCIKICBmaWxsPSJub25lIgogIHN0cm9rZT0iIzAwMCIgc3R5bGU9ImJhY2tncm91bmQtY29sb3I6ICNmZmY7IGJvcmRlci1yYWRpdXM6IDJweCIKICBzdHJva2Utd2lkdGg9IjIiCiAgc3Ryb2tlLWxpbmVjYXA9InJvdW5kIgogIHN0cm9rZS1saW5lam9pbj0icm91bmQiCj4KICA8cGF0aCBkPSJNMjEuODAxIDEwQTEwIDEwIDAgMSAxIDE3IDMuMzM1IiAvPgogIDxwYXRoIGQ9Im05IDExIDMgM0wyMiA0IiAvPgo8L3N2Zz4K) - https://lucide.dev/icons/circle-check-big
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
function ToastManager($$renderer, $$props) {
  $$renderer.component(($$renderer2) => {
    var $$store_subs;
    let { placement = "viewport" } = $$props;
    const toastPlacement = derived(() => placement === "below-anchor" ? "below-anchor" : "viewport");
    $$renderer2.push(`<div${attr_class(
      `toast-manager pointer-events-none z-[99999] flex flex-col gap-2 ${toastPlacement() === "below-anchor" ? "toast-placement-below-anchor" : "toast-placement-viewport"}`,
      "svelte-p76qm4"
    )}${attr("data-toast-placement", toastPlacement())}><!--[-->`);
    const each_array = ensure_array_like(store_get($$store_subs ??= {}, "$toasts", toasts));
    for (let $$index = 0, $$length = each_array.length; $$index < $$length; $$index++) {
      let toast = each_array[$$index];
      $$renderer2.push(`<div${attr_class(`toast-${stringify(toast.type)} pointer-events-auto flex min-w-0 items-start gap-3 rounded-lg border px-4 py-3 shadow-xl backdrop-blur-sm transition-all duration-200`, "svelte-p76qm4")}${attr("role", toast.type === "warning" || toast.type === "error" ? "alert" : "status")}${attr("aria-live", toast.type === "warning" || toast.type === "error" ? "assertive" : "polite")} aria-atomic="true"><div class="shrink-0">`);
      if (toast.type === "success") {
        $$renderer2.push("<!--[0-->");
        Circle_check_big($$renderer2, { class: "text-success size-5", "aria-hidden": "true" });
      } else if (toast.type === "error") {
        $$renderer2.push("<!--[1-->");
        Circle_x($$renderer2, { class: "text-destructive size-5", "aria-hidden": "true" });
      } else if (toast.type === "warning") {
        $$renderer2.push("<!--[2-->");
        Triangle_alert($$renderer2, {
          class: "text-warning-foreground size-5",
          "aria-hidden": "true"
        });
      } else {
        $$renderer2.push("<!--[-1-->");
        Info($$renderer2, { class: "text-info size-5", "aria-hidden": "true" });
      }
      $$renderer2.push(`<!--]--></div> <p class="text-foreground min-w-0 flex-1 text-sm font-medium">${escape_html(toast.message)}</p> <button type="button" class="hover:bg-accent focus:ring-ring flex min-h-[44px] min-w-[44px] shrink-0 items-center justify-center rounded-md transition-colors focus:ring-2 focus:outline-none" aria-label="Dismiss notification" title="Dismiss notification">`);
      X($$renderer2, { class: "text-muted-foreground size-4", "aria-hidden": "true" });
      $$renderer2.push(`<!----></button></div>`);
    }
    $$renderer2.push(`<!--]--></div>`);
    if ($$store_subs) unsubscribe_stores($$store_subs);
  });
}
export {
  Circle_check_big as C,
  ToastManager as T
};
