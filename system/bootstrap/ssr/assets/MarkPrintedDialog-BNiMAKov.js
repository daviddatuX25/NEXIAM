import { g as sanitize_props, i as spread_props, j as slot, e as escape_html, B as Button, a3 as csrfToken, n as newIdempotencyKey, q as success, v as error, u as usePage } from "../app.js";
import { I as Icon } from "./Icon-Dal7PqKK.js";
import "clsx";
import { X } from "./x-CnxrMbMn.js";
function Rotate_cw($$renderer, $$props) {
  const $$sanitized_props = sanitize_props($$props);
  const iconNode = [
    [
      "path",
      { "d": "M21 12a9 9 0 1 1-9-9c2.52 0 4.93 1 6.74 2.74L21 8" }
    ],
    ["path", { "d": "M21 3v5h-5" }]
  ];
  Icon($$renderer, spread_props([
    { name: "rotate-cw" },
    $$sanitized_props,
    {
      /**
       * @component @name RotateCw
       * @description Lucide SVG icon component, renders SVG Element with children.
       *
       * @preview ![img](data:image/svg+xml;base64,PHN2ZyAgeG1sbnM9Imh0dHA6Ly93d3cudzMub3JnLzIwMDAvc3ZnIgogIHdpZHRoPSIyNCIKICBoZWlnaHQ9IjI0IgogIHZpZXdCb3g9IjAgMCAyNCAyNCIKICBmaWxsPSJub25lIgogIHN0cm9rZT0iIzAwMCIgc3R5bGU9ImJhY2tncm91bmQtY29sb3I6ICNmZmY7IGJvcmRlci1yYWRpdXM6IDJweCIKICBzdHJva2Utd2lkdGg9IjIiCiAgc3Ryb2tlLWxpbmVjYXA9InJvdW5kIgogIHN0cm9rZS1saW5lam9pbj0icm91bmQiCj4KICA8cGF0aCBkPSJNMjEgMTJhOSA5IDAgMSAxLTktOWMyLjUyIDAgNC45MyAxIDYuNzQgMi43NEwyMSA4IiAvPgogIDxwYXRoIGQ9Ik0yMSAzdjVoLTUiIC8+Cjwvc3ZnPgo=) - https://lucide.dev/icons/rotate-cw
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
function Square_check_big($$renderer, $$props) {
  const $$sanitized_props = sanitize_props($$props);
  const iconNode = [
    [
      "path",
      {
        "d": "M21 10.656V19a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h12.344"
      }
    ],
    ["path", { "d": "m9 11 3 3L22 4" }]
  ];
  Icon($$renderer, spread_props([
    { name: "square-check-big" },
    $$sanitized_props,
    {
      /**
       * @component @name SquareCheckBig
       * @description Lucide SVG icon component, renders SVG Element with children.
       *
       * @preview ![img](data:image/svg+xml;base64,PHN2ZyAgeG1sbnM9Imh0dHA6Ly93d3cudzMub3JnLzIwMDAvc3ZnIgogIHdpZHRoPSIyNCIKICBoZWlnaHQ9IjI0IgogIHZpZXdCb3g9IjAgMCAyNCAyNCIKICBmaWxsPSJub25lIgogIHN0cm9rZT0iIzAwMCIgc3R5bGU9ImJhY2tncm91bmQtY29sb3I6ICNmZmY7IGJvcmRlci1yYWRpdXM6IDJweCIKICBzdHJva2Utd2lkdGg9IjIiCiAgc3Ryb2tlLWxpbmVjYXA9InJvdW5kIgogIHN0cm9rZS1saW5lam9pbj0icm91bmQiCj4KICA8cGF0aCBkPSJNMjEgMTAuNjU2VjE5YTIgMiAwIDAgMS0yIDJINWEyIDIgMCAwIDEtMi0yVjVhMiAyIDAgMCAxIDItMmgxMi4zNDQiIC8+CiAgPHBhdGggZD0ibTkgMTEgMyAzTDIyIDQiIC8+Cjwvc3ZnPgo=) - https://lucide.dev/icons/square-check-big
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
function MarkPrintedDialog($$renderer, $$props) {
  $$renderer.component(($$renderer2) => {
    let {
      open = false,
      documentIds = [],
      count = 1,
      onclose = () => {
      },
      onmarked = () => {
      }
    } = $$props;
    const page = usePage();
    let loading = false;
    async function handleMark() {
      if (documentIds.length === 0) {
        onclose();
        return;
      }
      loading = true;
      const token = csrfToken() || page.props.csrf_token || "";
      try {
        const res = await fetch("/release/print/mark-printed", {
          method: "POST",
          headers: {
            "Content-Type": "application/json",
            Accept: "application/json",
            "X-Requested-With": "XMLHttpRequest",
            "X-CSRF-TOKEN": token
          },
          credentials: "same-origin",
          body: JSON.stringify({
            document_ids: documentIds,
            printed: true,
            idempotency_key: newIdempotencyKey()
          })
        });
        if (res.ok) {
          success(`Marked ${count} result sheet(s) as printed.`);
          onmarked();
        } else {
          error("Failed to update printed status.");
        }
      } catch {
        error("Network error updating printed status.");
      } finally {
        loading = false;
        onclose();
      }
    }
    if (open) {
      $$renderer2.push("<!--[0-->");
      $$renderer2.push(`<div class="fixed inset-0 z-50 flex items-center justify-center bg-black/60 p-4 backdrop-blur-xs" role="dialog" aria-modal="true"><div class="w-full max-w-md space-y-4 rounded-xl border border-border bg-card p-6 shadow-xl animate-in fade-in zoom-in-95 duration-150"><div class="flex items-start justify-between"><div class="flex items-center gap-2.5"><div class="rounded-full bg-primary/10 p-2 text-primary">`);
      Square_check_big($$renderer2, { class: "size-5" });
      $$renderer2.push(`<!----></div> <div><h3 class="text-base font-semibold text-foreground">Mark as printed?</h3> <p class="text-xs text-muted-foreground">Keep staff records synchronized with physical paper</p></div></div> <button type="button" class="rounded-md p-1 text-muted-foreground hover:bg-muted" aria-label="Close">`);
      X($$renderer2, { class: "size-4" });
      $$renderer2.push(`<!----></button></div> <p class="text-sm text-muted-foreground">Confirm that <strong class="text-foreground">${escape_html(count)}</strong> result sheet${escape_html(count === 1 ? "" : "s")} were physically printed. This does not record applicant handover.</p> <div class="flex items-center justify-end gap-2 pt-2">`);
      Button($$renderer2, {
        variant: "outline",
        size: "sm",
        onclick: onclose,
        disabled: loading,
        children: ($$renderer3) => {
          $$renderer3.push(`<!---->Dismiss`);
        },
        $$slots: { default: true }
      });
      $$renderer2.push(`<!----> `);
      Button($$renderer2, {
        size: "sm",
        class: "gap-1.5 font-semibold",
        onclick: handleMark,
        disabled: loading,
        children: ($$renderer3) => {
          Square_check_big($$renderer3, { class: "size-4" });
          $$renderer3.push(`<!----> ${escape_html(loading ? "Marking…" : "Mark as printed")}`);
        },
        $$slots: { default: true }
      });
      $$renderer2.push(`<!----></div></div></div>`);
    } else {
      $$renderer2.push("<!--[-1-->");
    }
    $$renderer2.push(`<!--]-->`);
  });
}
export {
  MarkPrintedDialog as M,
  Rotate_cw as R,
  Square_check_big as S
};
