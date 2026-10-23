import { o as onDestroy, g as sanitize_props, i as spread_props, j as slot, b as attr, e as escape_html, d as derived, u as usePage, a as ensure_array_like, c as attr_class } from "../app.js";
import { router } from "@inertiajs/core";
import { B as Bell } from "./PreferencesMenu-CCPeta-5.js";
import { I as Icon } from "./Icon-Dal7PqKK.js";
import { I as Inbox } from "./inbox-AmUVtgA2.js";
import { M as Mail } from "./mail-DBtkjaCt.js";
import { T as Triangle_alert } from "./triangle-alert-CUvuB-HL.js";
function usePoll(interval, requestOptions = {}, options = {
  keepAlive: false,
  autoStart: true
}) {
  const { stop, start, destroy } = router.poll(interval, requestOptions, {
    ...options,
    autoStart: false
  });
  onDestroy(() => {
    destroy();
  });
  return { stop, start };
}
function Check_check($$renderer, $$props) {
  const $$sanitized_props = sanitize_props($$props);
  const iconNode = [
    ["path", { "d": "M18 6 7 17l-5-5" }],
    ["path", { "d": "m22 10-7.5 7.5L13 16" }]
  ];
  Icon($$renderer, spread_props([
    { name: "check-check" },
    $$sanitized_props,
    {
      /**
       * @component @name CheckCheck
       * @description Lucide SVG icon component, renders SVG Element with children.
       *
       * @preview ![img](data:image/svg+xml;base64,PHN2ZyAgeG1sbnM9Imh0dHA6Ly93d3cudzMub3JnLzIwMDAvc3ZnIgogIHdpZHRoPSIyNCIKICBoZWlnaHQ9IjI0IgogIHZpZXdCb3g9IjAgMCAyNCAyNCIKICBmaWxsPSJub25lIgogIHN0cm9rZT0iIzAwMCIgc3R5bGU9ImJhY2tncm91bmQtY29sb3I6ICNmZmY7IGJvcmRlci1yYWRpdXM6IDJweCIKICBzdHJva2Utd2lkdGg9IjIiCiAgc3Ryb2tlLWxpbmVjYXA9InJvdW5kIgogIHN0cm9rZS1saW5lam9pbj0icm91bmQiCj4KICA8cGF0aCBkPSJNMTggNiA3IDE3bC01LTUiIC8+CiAgPHBhdGggZD0ibTIyIDEwLTcuNSA3LjVMMTMgMTYiIC8+Cjwvc3ZnPgo=) - https://lucide.dev/icons/check-check
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
function NotificationBell($$renderer, $$props) {
  $$renderer.component(($$renderer2) => {
    const page = usePage();
    const notifications = derived(() => page.props.notifications ?? null);
    const unreadCount = derived(() => notifications()?.unread_count ?? 0);
    const recent = derived(() => notifications()?.recent ?? []);
    let isOpen = false;
    let projectionError = null;
    usePoll(
      3e4,
      () => ({
        only: ["notifications"],
        onSuccess: () => projectionError = null,
        onHttpException: () => {
          projectionError = "Notifications are temporarily unavailable.";
          return false;
        },
        onNetworkError: () => {
          projectionError = "Could not refresh notifications. Check your connection and retry.";
          return false;
        }
      }),
      { mode: "rest" }
    );
    function close() {
      isOpen = false;
    }
    function handleClickOutside(e) {
    }
    function handleKeydown(e) {
      if (e.key === "Escape") close();
    }
    onDestroy(() => {
      if (typeof window !== "undefined") {
        window.removeEventListener("click", handleClickOutside);
        window.removeEventListener("keydown", handleKeydown);
      }
    });
    function emailDeliveryLabel(status) {
      return {
        pending: "Email queued",
        processing: "Email sending",
        retrying: "Email retry scheduled",
        delivered: "Email accepted by mail service",
        failed_terminal: "Email delivery failed",
        outcome_unknown: "Email outcome unknown"
      }[status] ?? "Email status unavailable";
    }
    $$renderer2.push(`<div class="relative"><button type="button" class="relative flex h-11 w-11 items-center justify-center rounded-full text-foreground transition-colors hover:bg-accent min-h-[44px] min-w-[44px]" aria-haspopup="true"${attr("aria-expanded", isOpen)}${attr("aria-label", `Notifications${unreadCount() > 0 ? ` (${unreadCount()} unread)` : ""}`)}>`);
    Bell($$renderer2, { class: "h-5 w-5" });
    $$renderer2.push(`<!----> `);
    if (unreadCount() > 0) {
      $$renderer2.push("<!--[0-->");
      $$renderer2.push(`<span class="absolute top-1 right-1 flex h-4 min-w-4 items-center justify-center rounded-full bg-destructive px-1 text-[10px] font-bold leading-none text-white" aria-hidden="true">${escape_html(unreadCount() > 99 ? "99+" : unreadCount())}</span>`);
    } else {
      $$renderer2.push("<!--[-1-->");
    }
    $$renderer2.push(`<!--]--></button> `);
    if (isOpen) {
      $$renderer2.push("<!--[0-->");
      $$renderer2.push(`<div class="absolute right-0 top-full z-50 mt-2 w-80 sm:w-96 max-w-[calc(100vw-2rem)] max-h-[min(80dvh,32rem)] rounded-xl border border-border bg-card shadow-xl ring-1 ring-black/5 overflow-hidden flex flex-col animate-in fade-in zoom-in-95 duration-100" role="region" aria-label="Notifications"><div class="flex items-center justify-between border-b border-border/60 px-4 py-3"><h2 class="text-sm font-semibold text-foreground">Notifications</h2> `);
      if (unreadCount() > 0) {
        $$renderer2.push("<!--[0-->");
        $$renderer2.push(`<button type="button" class="flex items-center gap-1.5 text-xs font-medium text-primary hover:text-primary/80 transition-colors min-h-[36px] px-2 rounded-md hover:bg-accent">`);
        Check_check($$renderer2, { class: "h-4 w-4" });
        $$renderer2.push(`<!----> Mark all read</button>`);
      } else {
        $$renderer2.push("<!--[-1-->");
      }
      $$renderer2.push(`<!--]--></div> `);
      if (projectionError) {
        $$renderer2.push("<!--[0-->");
        $$renderer2.push(`<div role="alert" class="flex items-center justify-between gap-3 border-b border-border px-4 py-3 text-xs text-destructive"><span>${escape_html(projectionError)}</span> <button type="button" class="min-h-9 rounded-md px-2 font-medium text-primary hover:bg-accent">Retry</button></div>`);
      } else {
        $$renderer2.push("<!--[-1-->");
      }
      $$renderer2.push(`<!--]--> <div class="flex-1 min-h-0 overflow-y-auto">`);
      if (notifications() === null) {
        $$renderer2.push("<!--[0-->");
        $$renderer2.push(`<div role="status" class="flex flex-col items-center gap-2 px-4 py-10 text-center">`);
        Inbox($$renderer2, { class: "h-8 w-8 text-muted-foreground/50" });
        $$renderer2.push(`<!----> <p class="text-sm text-muted-foreground">Notification status is unavailable.</p> <button type="button" class="min-h-9 rounded-md px-3 text-xs font-medium text-primary hover:bg-accent">Retry</button></div>`);
      } else if (recent().length === 0) {
        $$renderer2.push("<!--[1-->");
        $$renderer2.push(`<div class="flex flex-col items-center gap-2 px-4 py-10 text-center">`);
        Inbox($$renderer2, { class: "h-8 w-8 text-muted-foreground/50" });
        $$renderer2.push(`<!----> <p class="text-sm text-muted-foreground">No notifications yet.</p></div>`);
      } else {
        $$renderer2.push("<!--[-1-->");
        $$renderer2.push(`<ul class="divide-y divide-border/50"><!--[-->`);
        const each_array = ensure_array_like(recent());
        for (let $$index = 0, $$length = each_array.length; $$index < $$length; $$index++) {
          let item = each_array[$$index];
          $$renderer2.push(`<li><button type="button"${attr_class(`flex w-full items-start gap-3 px-4 py-3 text-left transition-colors hover:bg-accent/60 min-h-[52px] ${item.read ? "" : "bg-primary/[0.03]"}`)}><span${attr_class(`mt-1 flex h-8 w-8 shrink-0 items-center justify-center rounded-full ${item.channel === "mail" ? "bg-muted text-muted-foreground" : "bg-primary/15 text-primary"}`)} aria-hidden="true">`);
          if (item.channel === "mail") {
            $$renderer2.push("<!--[0-->");
            Mail($$renderer2, { class: "h-4 w-4" });
          } else {
            $$renderer2.push("<!--[-1-->");
            $$renderer2.push(`<span class="h-2 w-2 rounded-full bg-current"></span>`);
          }
          $$renderer2.push(`<!--]--></span> <span class="min-w-0 flex-1"><span class="flex items-center gap-2"><span class="truncate text-sm font-medium text-foreground">${escape_html(item.subject)}</span> `);
          if (!item.read) {
            $$renderer2.push("<!--[0-->");
            $$renderer2.push(`<span class="h-1.5 w-1.5 shrink-0 rounded-full bg-primary" aria-label="Unread"></span>`);
          } else {
            $$renderer2.push("<!--[-1-->");
          }
          $$renderer2.push(`<!--]--></span> <span class="mt-0.5 line-clamp-2 text-xs leading-relaxed text-muted-foreground">${escape_html(item.body)}</span> <span class="mt-1 flex flex-wrap items-center gap-x-2 gap-y-1 text-[11px] text-muted-foreground/80">`);
          if (item.failed) {
            $$renderer2.push("<!--[0-->");
            $$renderer2.push(`<span class="inline-flex items-center gap-1 text-destructive">`);
            Triangle_alert($$renderer2, { class: "h-3 w-3" });
            $$renderer2.push(`<!----> In-app delivery failed</span>`);
          } else if (item.delivered_at) {
            $$renderer2.push("<!--[1-->");
            $$renderer2.push(`<span>In-app available</span>`);
          } else if (item.status === "processing") {
            $$renderer2.push("<!--[2-->");
            $$renderer2.push(`<span>Preparing in-app notice</span>`);
          } else if (item.status === "retrying") {
            $$renderer2.push("<!--[3-->");
            $$renderer2.push(`<span>In-app retry scheduled</span>`);
          } else {
            $$renderer2.push("<!--[-1-->");
            $$renderer2.push(`<span>In-app queued</span>`);
          }
          $$renderer2.push(`<!--]--> `);
          if (item.email_delivery) {
            $$renderer2.push("<!--[0-->");
            $$renderer2.push(`<span${attr_class(`inline-flex items-center gap-1 ${item.email_delivery.failed || item.email_delivery.outcome_unknown ? "text-destructive" : ""}`)}>`);
            Mail($$renderer2, { class: "h-3 w-3" });
            $$renderer2.push(`<!----> ${escape_html(emailDeliveryLabel(item.email_delivery.status))}</span>`);
          } else {
            $$renderer2.push("<!--[-1-->");
          }
          $$renderer2.push(`<!--]--></span></span></button></li>`);
        }
        $$renderer2.push(`<!--]--></ul>`);
      }
      $$renderer2.push(`<!--]--></div> <div class="border-t border-border/60 px-4 py-2"><a href="/notifications" class="block py-2 text-center text-xs font-medium text-primary hover:text-primary/80 transition-colors">View all notifications</a></div></div>`);
    } else {
      $$renderer2.push("<!--[-1-->");
    }
    $$renderer2.push(`<!--]--></div>`);
  });
}
export {
  Check_check as C,
  NotificationBell as N,
  usePoll as u
};
