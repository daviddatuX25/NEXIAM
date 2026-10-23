import { g as sanitize_props, i as spread_props, j as slot, c as attr_class, e as escape_html, a as ensure_array_like } from "../app.js";
import { L as Link } from "./Link-B7ks7rBK.js";
import { I as Icon } from "./Icon-Dal7PqKK.js";
import { M as Minus } from "./minus-Dqkhcxt7.js";
function Trending_down($$renderer, $$props) {
  const $$sanitized_props = sanitize_props($$props);
  const iconNode = [
    ["path", { "d": "M16 17h6v-6" }],
    ["path", { "d": "m22 17-8.5-8.5-5 5L2 7" }]
  ];
  Icon($$renderer, spread_props([
    { name: "trending-down" },
    $$sanitized_props,
    {
      /**
       * @component @name TrendingDown
       * @description Lucide SVG icon component, renders SVG Element with children.
       *
       * @preview ![img](data:image/svg+xml;base64,PHN2ZyAgeG1sbnM9Imh0dHA6Ly93d3cudzMub3JnLzIwMDAvc3ZnIgogIHdpZHRoPSIyNCIKICBoZWlnaHQ9IjI0IgogIHZpZXdCb3g9IjAgMCAyNCAyNCIKICBmaWxsPSJub25lIgogIHN0cm9rZT0iIzAwMCIgc3R5bGU9ImJhY2tncm91bmQtY29sb3I6ICNmZmY7IGJvcmRlci1yYWRpdXM6IDJweCIKICBzdHJva2Utd2lkdGg9IjIiCiAgc3Ryb2tlLWxpbmVjYXA9InJvdW5kIgogIHN0cm9rZS1saW5lam9pbj0icm91bmQiCj4KICA8cGF0aCBkPSJNMTYgMTdoNnYtNiIgLz4KICA8cGF0aCBkPSJtMjIgMTctOC41LTguNS01IDVMMiA3IiAvPgo8L3N2Zz4K) - https://lucide.dev/icons/trending-down
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
function Trending_up($$renderer, $$props) {
  const $$sanitized_props = sanitize_props($$props);
  const iconNode = [
    ["path", { "d": "M16 7h6v6" }],
    ["path", { "d": "m22 7-8.5 8.5-5-5L2 17" }]
  ];
  Icon($$renderer, spread_props([
    { name: "trending-up" },
    $$sanitized_props,
    {
      /**
       * @component @name TrendingUp
       * @description Lucide SVG icon component, renders SVG Element with children.
       *
       * @preview ![img](data:image/svg+xml;base64,PHN2ZyAgeG1sbnM9Imh0dHA6Ly93d3cudzMub3JnLzIwMDAvc3ZnIgogIHdpZHRoPSIyNCIKICBoZWlnaHQ9IjI0IgogIHZpZXdCb3g9IjAgMCAyNCAyNCIKICBmaWxsPSJub25lIgogIHN0cm9rZT0iIzAwMCIgc3R5bGU9ImJhY2tncm91bmQtY29sb3I6ICNmZmY7IGJvcmRlci1yYWRpdXM6IDJweCIKICBzdHJva2Utd2lkdGg9IjIiCiAgc3Ryb2tlLWxpbmVjYXA9InJvdW5kIgogIHN0cm9rZS1saW5lam9pbj0icm91bmQiCj4KICA8cGF0aCBkPSJNMTYgN2g2djYiIC8+CiAgPHBhdGggZD0ibTIyIDctOC41IDguNS01LTVMMiAxNyIgLz4KPC9zdmc+Cg==) - https://lucide.dev/icons/trending-up
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
function KpiCard($$renderer, $$props) {
  $$renderer.component(($$renderer2) => {
    let {
      label,
      value,
      status = "ok",
      // 'ok' | 'warn' | 'critical'
      delta = null,
      deltaLabel = "",
      secondaryItems = [],
      // Array<{ label: string, value: string|number }>
      href = null
    } = $$props;
    const statusStyles = {
      ok: "border-l-4 border-l-primary/60 bg-primary/5",
      warn: "border-l-4 border-l-amber-500/60 bg-amber-500/5",
      critical: "border-l-4 border-l-destructive/60 bg-destructive/5"
    };
    $$renderer2.push(`<div${attr_class(`relative overflow-hidden rounded-[var(--radius,0.5rem)] border border-border/80 bg-card p-5 transition-all duration-200 motion-safe:hover:-translate-y-0.5 hover:border-primary/40 shadow-sm dark:shadow-[inset_0_1px_0_0_rgba(255,255,255,0.08)] ${statusStyles[status] || statusStyles.ok}`)}>`);
    if (href) {
      $$renderer2.push("<!--[0-->");
      Link($$renderer2, {
        href,
        class: "absolute inset-0 z-10",
        children: ($$renderer3) => {
          $$renderer3.push(`<span class="sr-only">View ${escape_html(label)}</span>`);
        },
        $$slots: { default: true }
      });
    } else {
      $$renderer2.push("<!--[-1-->");
    }
    $$renderer2.push(`<!--]--> <div class="relative z-0"><div class="flex items-center justify-between"><div class="text-sm font-medium text-muted-foreground">${escape_html(label)}</div> `);
    if (delta !== null) {
      $$renderer2.push("<!--[0-->");
      $$renderer2.push(`<div${attr_class(`flex items-center gap-1 text-xs font-semibold px-2 py-0.5 rounded-full ${delta > 0 ? "bg-emerald-500/10 text-emerald-600 dark:text-emerald-400" : delta < 0 ? "bg-rose-500/10 text-rose-600 dark:text-rose-400" : "bg-muted text-muted-foreground"}`)}>`);
      if (delta > 0) {
        $$renderer2.push("<!--[0-->");
        Trending_up($$renderer2, { class: "h-3 w-3 shrink-0" });
        $$renderer2.push(`<!----> <span>+${escape_html(delta)}</span>`);
      } else if (delta < 0) {
        $$renderer2.push("<!--[1-->");
        Trending_down($$renderer2, { class: "h-3 w-3 shrink-0" });
        $$renderer2.push(`<!----> <span>${escape_html(delta)}</span>`);
      } else {
        $$renderer2.push("<!--[-1-->");
        Minus($$renderer2, { class: "h-3 w-3 shrink-0" });
        $$renderer2.push(`<!----> <span>0</span>`);
      }
      $$renderer2.push(`<!--]--> `);
      if (deltaLabel) {
        $$renderer2.push("<!--[0-->");
        $$renderer2.push(`<span class="ml-0.5 font-normal opacity-70 truncate">${escape_html(deltaLabel)}</span>`);
      } else {
        $$renderer2.push("<!--[-1-->");
      }
      $$renderer2.push(`<!--]--></div>`);
    } else {
      $$renderer2.push("<!--[-1-->");
    }
    $$renderer2.push(`<!--]--></div> <div class="mt-2 text-3xl font-extrabold font-mono tabular-nums tracking-tight text-foreground">${escape_html(value)}</div> `);
    if (secondaryItems.length > 0) {
      $$renderer2.push("<!--[0-->");
      $$renderer2.push(`<div class="mt-3 flex flex-wrap gap-x-4 gap-y-1 text-xs text-muted-foreground"><!--[-->`);
      const each_array = ensure_array_like(secondaryItems);
      for (let $$index = 0, $$length = each_array.length; $$index < $$length; $$index++) {
        let item = each_array[$$index];
        $$renderer2.push(`<div>${escape_html(item.label)} <span class="tabular-nums font-semibold text-foreground/90">${escape_html(item.value)}</span></div>`);
      }
      $$renderer2.push(`<!--]--></div>`);
    } else {
      $$renderer2.push("<!--[-1-->");
    }
    $$renderer2.push(`<!--]--></div></div>`);
  });
}
export {
  KpiCard as K,
  Trending_up as T,
  Trending_down as a
};
