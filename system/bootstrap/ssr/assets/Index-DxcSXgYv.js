import { h as head, B as Button, a as ensure_array_like, c as attr_class, e as escape_html, d as derived, u as usePage } from "../app.js";
import { S as StaffLayout } from "./StaffLayout-CR-MZopa.js";
import ApplicantLayout from "./ApplicantLayout-DKnOpLAK.js";
import { P as PageHeaderBar } from "./PageHeaderBar-DJhT5vOR.js";
import { I as Inbox } from "./inbox-AmUVtgA2.js";
import { T as Triangle_alert } from "./triangle-alert-CUvuB-HL.js";
import { M as Mail } from "./mail-DBtkjaCt.js";
import { C as Check_check } from "./NotificationBell-Cgc59min.js";
import { router } from "@inertiajs/core";
import "axios";
import "clsx";
import "laravel-precognition";
import "tailwind-merge";
import "tailwind-variants";
import "./ToastManager-C7oO_4Jn.js";
import "./Icon-Dal7PqKK.js";
import "./circle-x-BQrhluGu.js";
import "./info-C3MCzlQW.js";
import "./x-CnxrMbMn.js";
import "./PreferencesMenu-CCPeta-5.js";
import "./watch.svelte-BcPoqckX.js";
import "./is-C6cm64pF.js";
import "./create-id-CUJmDlea.js";
import "style-to-object";
import "./scroll-lock-B1ibcKMx.js";
import "./noop-BQS7OFs8.js";
import "tabbable";
import "@floating-ui/dom";
import "./hidden-input-GSZDfM3n.js";
import "./InstitutionalBackdrop-BjxeRqDu.js";
import "./zoom-out-DsJCISKt.js";
import "./rotate-ccw-D6Dm_efx.js";
import "./AvatarBadge-DQ5Qru9A.js";
import "./badge-D9Vgs7xh.js";
import "./input-BUZAZKIK.js";
import "./dialog-description-D12L0QA9.js";
import "./circle-alert-jWXJ7oC0.js";
import "./refresh-cw-CYdk8aE0.js";
import "./camera-C09Eyogi.js";
import "./settings-C5xOKpV3.js";
import "./arrow-right-CzynHiIo.js";
import "./circle-check-C59ZS7Zv.js";
import "./dialog-footer-kNnwKT2h.js";
import "./clock-B4fXoAi9.js";
import "./Link-B7ks7rBK.js";
import "./qr-code-C5X8qcH6.js";
import "./chevron-right-BHu-PGOg.js";
import "./chevron-down-CkQQZIhu.js";
import "./printer-Bx0z1CRb.js";
import "./book-open-B6eMguqY.js";
import "./megaphone-C8QUuT5s.js";
import "./file-check-BSLEMs1I.js";
import "./shield-DTtuUihb.js";
import "./clipboard-list-YgthURdB.js";
import "./users-DWlguTa_.js";
import "./user-round-Kft0Cy_Y.js";
import "./XiamNpcDialog-DoqCSHj9.js";
/* empty css                                                        */
import "./circle-question-mark-DY-RU614.js";
import "./sparkles-DbQIaVG6.js";
import "./chevron-left-DCSOZwZl.js";
import "qrcode";
import "./file-text-PXEtmXm9.js";
import "./graduation-cap-Bc9amiZ0.js";
import "./user-check-Dl-wciTR.js";
import "./calendar-clock-D72LURiz.js";
function Index($$renderer, $$props) {
  $$renderer.component(($$renderer2) => {
    let { notifications = null } = $$props;
    const page = usePage();
    const isApplicant = derived(() => page.props.auth?.user?.is_applicant ?? false);
    const unreadCount = derived(() => notifications?.unread_count ?? 0);
    const items = derived(() => notifications?.recent ?? []);
    let projectionError = null;
    function retryProjection() {
      router.reload({
        only: ["notifications"],
        onSuccess: () => projectionError = null,
        onHttpException: () => {
          projectionError = "Notifications are temporarily unavailable.";
          return false;
        },
        onNetworkError: () => {
          projectionError = "Could not load notifications. Check your connection and retry.";
          return false;
        }
      });
    }
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
    function markAllRead() {
      projectionError = null;
      router.post("/notifications/read-all", {}, {
        preserveScroll: true,
        onSuccess: () => projectionError = null,
        onError: () => {
          projectionError = "Could not mark notifications read. Retry the action.";
        },
        onHttpException: () => {
          projectionError = "Could not mark notifications read. Retry the action.";
          return false;
        },
        onNetworkError: () => {
          projectionError = "Could not mark notifications read. Check your connection and retry.";
          return false;
        }
      });
    }
    function formatWhen(value) {
      if (!value) return "";
      const date = new Date(value);
      return date.toLocaleString(void 0, {
        month: "short",
        day: "numeric",
        hour: "2-digit",
        minute: "2-digit"
      });
    }
    head("1neeqsu", $$renderer2, ($$renderer3) => {
      $$renderer3.title(($$renderer4) => {
        $$renderer4.push(`<title>Notifications</title>`);
      });
    });
    if (isApplicant()) {
      $$renderer2.push("<!--[0-->");
      ApplicantLayout($$renderer2, {
        title: "Notifications",
        children: ($$renderer3) => {
          $$renderer3.push(`<div class="min-w-0 space-y-6 p-4 sm:p-6">`);
          {
            let actions = function($$renderer4) {
              if (unreadCount() > 0) {
                $$renderer4.push("<!--[0-->");
                Button($$renderer4, {
                  variant: "outline",
                  class: "min-h-10 gap-2",
                  onclick: markAllRead,
                  children: ($$renderer5) => {
                    Check_check($$renderer5, { class: "size-4" });
                    $$renderer5.push(`<!----> Mark all read`);
                  },
                  $$slots: { default: true }
                });
              } else {
                $$renderer4.push("<!--[-1-->");
              }
              $$renderer4.push(`<!--]-->`);
            };
            PageHeaderBar($$renderer3, {
              title: "Notifications",
              description: "Updates about your application, exam schedule, and results.",
              actions
            });
          }
          $$renderer3.push(`<!----> `);
          if (projectionError) {
            $$renderer3.push("<!--[0-->");
            $$renderer3.push(`<div role="alert" class="flex flex-wrap items-center justify-between gap-3 rounded-lg border border-destructive/30 bg-destructive/5 px-4 py-3 text-sm text-destructive"><span>${escape_html(projectionError)}</span> `);
            Button($$renderer3, {
              variant: "outline",
              class: "min-h-9",
              onclick: retryProjection,
              children: ($$renderer4) => {
                $$renderer4.push(`<!---->Retry`);
              },
              $$slots: { default: true }
            });
            $$renderer3.push(`<!----></div>`);
          } else {
            $$renderer3.push("<!--[-1-->");
          }
          $$renderer3.push(`<!--]--> `);
          if (notifications === null) {
            $$renderer3.push("<!--[0-->");
            $$renderer3.push(`<div role="status" class="flex flex-col items-center gap-3 rounded-xl border border-border bg-card px-6 py-14 text-center">`);
            Inbox($$renderer3, { class: "size-10 text-muted-foreground/50" });
            $$renderer3.push(`<!----> <p class="font-medium text-foreground">Notification status is unavailable.</p> `);
            Button($$renderer3, {
              variant: "outline",
              class: "min-h-10",
              onclick: retryProjection,
              children: ($$renderer4) => {
                $$renderer4.push(`<!---->Retry`);
              },
              $$slots: { default: true }
            });
            $$renderer3.push(`<!----></div>`);
          } else if (items().length === 0) {
            $$renderer3.push("<!--[1-->");
            $$renderer3.push(`<div class="flex flex-col items-center gap-3 rounded-xl border border-border bg-card px-6 py-14 text-center">`);
            Inbox($$renderer3, { class: "size-10 text-muted-foreground/50" });
            $$renderer3.push(`<!----> <p class="font-medium text-foreground">No notifications yet.</p> <p class="text-sm text-muted-foreground">Updates about your exam schedule and results will appear here.</p></div>`);
          } else {
            $$renderer3.push("<!--[-1-->");
            $$renderer3.push(`<ul class="divide-y divide-border/60 rounded-xl border border-border bg-card"><!--[-->`);
            const each_array = ensure_array_like(items());
            for (let $$index = 0, $$length = each_array.length; $$index < $$length; $$index++) {
              let item = each_array[$$index];
              $$renderer3.push(`<li><button type="button"${attr_class(`flex w-full items-start gap-3 px-4 py-4 text-left transition-colors hover:bg-accent/60 ${item.read ? "" : "bg-primary/[0.03]"}`)}><span class="mt-0.5 flex size-9 shrink-0 items-center justify-center rounded-full bg-primary/15 text-primary" aria-hidden="true"><span class="size-2 rounded-full bg-current"></span></span> <span class="min-w-0 flex-1"><span class="flex items-center gap-2"><span class="font-medium text-foreground">${escape_html(item.subject)}</span> `);
              if (!item.read) {
                $$renderer3.push("<!--[0-->");
                $$renderer3.push(`<span class="size-1.5 shrink-0 rounded-full bg-primary" aria-label="Unread"></span>`);
              } else {
                $$renderer3.push("<!--[-1-->");
              }
              $$renderer3.push(`<!--]--></span> <span class="mt-0.5 text-sm leading-relaxed text-muted-foreground">${escape_html(item.body)}</span> <span class="mt-1 flex flex-wrap items-center gap-x-2 gap-y-1 text-xs text-muted-foreground/80"><span>${escape_html(formatWhen(item.created_at))}</span> `);
              if (item.failed) {
                $$renderer3.push("<!--[0-->");
                $$renderer3.push(`<span class="inline-flex items-center gap-1 font-medium text-destructive">`);
                Triangle_alert($$renderer3, { class: "size-3" });
                $$renderer3.push(`<!----> In-app delivery failed</span>`);
              } else if (item.delivered_at) {
                $$renderer3.push("<!--[1-->");
                $$renderer3.push(`<span>· In-app available</span>`);
              } else if (item.status === "processing") {
                $$renderer3.push("<!--[2-->");
                $$renderer3.push(`<span>· Preparing in-app notice</span>`);
              } else if (item.status === "retrying") {
                $$renderer3.push("<!--[3-->");
                $$renderer3.push(`<span>· In-app retry scheduled</span>`);
              } else {
                $$renderer3.push("<!--[-1-->");
                $$renderer3.push(`<span>· In-app queued</span>`);
              }
              $$renderer3.push(`<!--]--> `);
              if (item.email_delivery) {
                $$renderer3.push("<!--[0-->");
                $$renderer3.push(`<span${attr_class(`inline-flex items-center gap-1 ${item.email_delivery.failed || item.email_delivery.outcome_unknown ? "font-medium text-destructive" : ""}`)}>`);
                Mail($$renderer3, { class: "size-3" });
                $$renderer3.push(`<!----> ${escape_html(emailDeliveryLabel(item.email_delivery.status))}</span>`);
              } else {
                $$renderer3.push("<!--[-1-->");
              }
              $$renderer3.push(`<!--]--></span></span></button></li>`);
            }
            $$renderer3.push(`<!--]--></ul>`);
          }
          $$renderer3.push(`<!--]--></div>`);
        }
      });
    } else {
      $$renderer2.push("<!--[-1-->");
      StaffLayout($$renderer2, {
        breadcrumbs: [{ label: "Notifications" }],
        children: ($$renderer3) => {
          $$renderer3.push(`<div class="min-w-0 space-y-6 p-4 sm:p-6">`);
          {
            let actions = function($$renderer4) {
              if (unreadCount() > 0) {
                $$renderer4.push("<!--[0-->");
                Button($$renderer4, {
                  variant: "outline",
                  class: "min-h-10 gap-2",
                  onclick: markAllRead,
                  children: ($$renderer5) => {
                    Check_check($$renderer5, { class: "size-4" });
                    $$renderer5.push(`<!----> Mark all read`);
                  },
                  $$slots: { default: true }
                });
              } else {
                $$renderer4.push("<!--[-1-->");
              }
              $$renderer4.push(`<!--]-->`);
            };
            PageHeaderBar($$renderer3, {
              title: "Notifications",
              description: "Updates about exam operations and your assigned duties.",
              actions
            });
          }
          $$renderer3.push(`<!----> `);
          if (projectionError) {
            $$renderer3.push("<!--[0-->");
            $$renderer3.push(`<div role="alert" class="flex flex-wrap items-center justify-between gap-3 rounded-lg border border-destructive/30 bg-destructive/5 px-4 py-3 text-sm text-destructive"><span>${escape_html(projectionError)}</span> `);
            Button($$renderer3, {
              variant: "outline",
              class: "min-h-9",
              onclick: retryProjection,
              children: ($$renderer4) => {
                $$renderer4.push(`<!---->Retry`);
              },
              $$slots: { default: true }
            });
            $$renderer3.push(`<!----></div>`);
          } else {
            $$renderer3.push("<!--[-1-->");
          }
          $$renderer3.push(`<!--]--> `);
          if (notifications === null) {
            $$renderer3.push("<!--[0-->");
            $$renderer3.push(`<div role="status" class="flex flex-col items-center gap-3 rounded-xl border border-border bg-card px-6 py-14 text-center">`);
            Inbox($$renderer3, { class: "size-10 text-muted-foreground/50" });
            $$renderer3.push(`<!----> <p class="font-medium text-foreground">Notification status is unavailable.</p> `);
            Button($$renderer3, {
              variant: "outline",
              class: "min-h-10",
              onclick: retryProjection,
              children: ($$renderer4) => {
                $$renderer4.push(`<!---->Retry`);
              },
              $$slots: { default: true }
            });
            $$renderer3.push(`<!----></div>`);
          } else if (items().length === 0) {
            $$renderer3.push("<!--[1-->");
            $$renderer3.push(`<div class="flex flex-col items-center gap-3 rounded-xl border border-border bg-card px-6 py-14 text-center">`);
            Inbox($$renderer3, { class: "size-10 text-muted-foreground/50" });
            $$renderer3.push(`<!----> <p class="font-medium text-foreground">No notifications yet.</p> <p class="text-sm text-muted-foreground">Proctor assignments and exam lifecycle updates will appear here.</p></div>`);
          } else {
            $$renderer3.push("<!--[-1-->");
            $$renderer3.push(`<ul class="divide-y divide-border/60 rounded-xl border border-border bg-card"><!--[-->`);
            const each_array_1 = ensure_array_like(items());
            for (let $$index_1 = 0, $$length = each_array_1.length; $$index_1 < $$length; $$index_1++) {
              let item = each_array_1[$$index_1];
              $$renderer3.push(`<li><button type="button"${attr_class(`flex w-full items-start gap-3 px-4 py-4 text-left transition-colors hover:bg-accent/60 ${item.read ? "" : "bg-primary/[0.03]"}`)}><span class="mt-0.5 flex size-9 shrink-0 items-center justify-center rounded-full bg-primary/15 text-primary" aria-hidden="true"><span class="size-2 rounded-full bg-current"></span></span> <span class="min-w-0 flex-1"><span class="flex items-center gap-2"><span class="font-medium text-foreground">${escape_html(item.subject)}</span> `);
              if (!item.read) {
                $$renderer3.push("<!--[0-->");
                $$renderer3.push(`<span class="size-1.5 shrink-0 rounded-full bg-primary" aria-label="Unread"></span>`);
              } else {
                $$renderer3.push("<!--[-1-->");
              }
              $$renderer3.push(`<!--]--></span> <span class="mt-0.5 text-sm leading-relaxed text-muted-foreground">${escape_html(item.body)}</span> <span class="mt-1 flex flex-wrap items-center gap-x-2 gap-y-1 text-xs text-muted-foreground/80"><span>${escape_html(formatWhen(item.created_at))}</span> `);
              if (item.failed) {
                $$renderer3.push("<!--[0-->");
                $$renderer3.push(`<span class="inline-flex items-center gap-1 font-medium text-destructive">`);
                Triangle_alert($$renderer3, { class: "size-3" });
                $$renderer3.push(`<!----> In-app delivery failed</span>`);
              } else if (item.delivered_at) {
                $$renderer3.push("<!--[1-->");
                $$renderer3.push(`<span>· In-app available</span>`);
              } else if (item.status === "processing") {
                $$renderer3.push("<!--[2-->");
                $$renderer3.push(`<span>· Preparing in-app notice</span>`);
              } else if (item.status === "retrying") {
                $$renderer3.push("<!--[3-->");
                $$renderer3.push(`<span>· In-app retry scheduled</span>`);
              } else {
                $$renderer3.push("<!--[-1-->");
                $$renderer3.push(`<span>· In-app queued</span>`);
              }
              $$renderer3.push(`<!--]--> `);
              if (item.email_delivery) {
                $$renderer3.push("<!--[0-->");
                $$renderer3.push(`<span${attr_class(`inline-flex items-center gap-1 ${item.email_delivery.failed || item.email_delivery.outcome_unknown ? "font-medium text-destructive" : ""}`)}>`);
                Mail($$renderer3, { class: "size-3" });
                $$renderer3.push(`<!----> ${escape_html(emailDeliveryLabel(item.email_delivery.status))}</span>`);
              } else {
                $$renderer3.push("<!--[-1-->");
              }
              $$renderer3.push(`<!--]--></span></span></button></li>`);
            }
            $$renderer3.push(`<!--]--></ul>`);
          }
          $$renderer3.push(`<!--]--></div>`);
        }
      });
    }
    $$renderer2.push(`<!--]-->`);
  });
}
export {
  Index as default
};
