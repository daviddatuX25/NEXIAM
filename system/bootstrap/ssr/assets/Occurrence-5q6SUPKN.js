import { h as head, e as escape_html, s as stringify, a as ensure_array_like, B as Button, d as derived, b as attr, t as tick } from "../app.js";
import { S as StaffLayout } from "./StaffLayout-CR-MZopa.js";
import { P as PageHeaderBar } from "./PageHeaderBar-DJhT5vOR.js";
import { S as SimplePagination } from "./SimplePagination-Bcq5R_Kb.js";
import { C as Card, a as Card_content } from "./card-content-Brg2wRkV.js";
import { C as Card_description } from "./card-description-BGy_v5-d.js";
import "clsx";
import { C as Card_header, a as Card_title } from "./card-title-BNY2ZPje.js";
import { B as Badge } from "./badge-D9Vgs7xh.js";
import { T as Table, a as Table_header, b as Table_row, c as Table_head, d as Table_body, e as Table_cell } from "./table-row-_ffWKrps.js";
import { U as Users } from "./users-DWlguTa_.js";
import { H as History } from "./history-D15LFKXr.js";
import { L as Link } from "./Link-B7ks7rBK.js";
import { A as Arrow_left } from "./arrow-left-DbWnQR3t.js";
import { router } from "@inertiajs/core";
import "axios";
import "laravel-precognition";
import "tailwind-merge";
import "tailwind-variants";
import "./ToastManager-C7oO_4Jn.js";
import "./Icon-Dal7PqKK.js";
import "./circle-x-BQrhluGu.js";
import "./triangle-alert-CUvuB-HL.js";
import "./info-C3MCzlQW.js";
import "./x-CnxrMbMn.js";
import "./NotificationBell-Cgc59min.js";
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
import "./inbox-AmUVtgA2.js";
import "./mail-DBtkjaCt.js";
import "./AvatarBadge-DQ5Qru9A.js";
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
import "./qr-code-C5X8qcH6.js";
import "./chevron-right-BHu-PGOg.js";
import "./chevron-down-CkQQZIhu.js";
import "./printer-Bx0z1CRb.js";
import "./book-open-B6eMguqY.js";
import "./megaphone-C8QUuT5s.js";
import "./file-check-BSLEMs1I.js";
import "./shield-DTtuUihb.js";
import "./clipboard-list-YgthURdB.js";
import "./user-round-Kft0Cy_Y.js";
import "./XiamNpcDialog-DoqCSHj9.js";
/* empty css                                                        */
import "./circle-question-mark-DY-RU614.js";
import "./sparkles-DbQIaVG6.js";
import "./chevron-left-DCSOZwZl.js";
function Occurrence($$renderer, $$props) {
  $$renderer.component(($$renderer2) => {
    async function focusDialog() {
      await tick();
    }
    let {
      occurrence = {},
      deliveries = { data: [] },
      history = { data: [] },
      can_retry = false
    } = $$props;
    let isOpen = false;
    let activeDelivery = null;
    let reason = "";
    let acknowledgeRisk = false;
    let isSubmitting = false;
    let errorMessage = "";
    let modalType = "retry";
    const deliveryRows = derived(() => deliveries?.data ?? []);
    const historyRows = derived(() => history?.data ?? []);
    const breadcrumbs = [
      { label: "Setup", href: "/setup" },
      { label: "Email & Notifications", href: "/setup/notifications" },
      { label: "Occurrence details" }
    ];
    function domainLabel(value) {
      return {
        admissions: "Admissions",
        examination: "Examinations",
        results: "Results"
      }[value] ?? value ?? "Notification";
    }
    function statusLabel(value) {
      return {
        pending: "In Flight",
        queued: "In Flight",
        processing: "In Flight",
        in_progress: "In Flight",
        retrying: "In Flight",
        retry_scheduled: "In Flight",
        delivered: "Delivered",
        accepted_mail: "Delivered",
        available_in_app: "Delivered",
        failed: "Bounced / Failed",
        failed_terminal: "Bounced / Failed",
        outcome_unknown: "In Flight",
        superseded: "Notice Outdated",
        unknown: "In Flight",
        mixed: "Mixed status"
      }[value] ?? value ?? "Unknown";
    }
    function recipientTypeLabel(value) {
      return value === "candidate" ? "Candidate" : value === "staff" ? "Staff" : value ?? "—";
    }
    function channelLabel(value) {
      return value === "email" ? "Email" : value === "in_app" ? "Portal Inbox" : value ?? "—";
    }
    function humanEventName(event) {
      return {
        "notification.delivery_processing": "Preparing dispatch",
        "notification.delivery_delivered": "Delivered to recipient",
        "notification.delivery_failed": "Delivery failed",
        "notification.delivery_superseded": "Marked outdated (superseded)",
        "notification.delivery_manually_retried": "Manually resent by staff",
        "notification.outcome_unknown": "Unconfirmed delivery outcome"
      }[event] ?? event ?? "Operational update";
    }
    function statusVariant(value) {
      if (value === "delivered" || value === "accepted_mail" || value === "available_in_app") return "default";
      if (value === "failed" || value === "failed_terminal") return "destructive";
      if (value === "pending" || value === "queued") return "secondary";
      return "outline";
    }
    function statusClass(value) {
      if (value === "retrying" || value === "retry_scheduled") return "border-amber-500/40 bg-amber-500/10 text-amber-700 dark:text-amber-300";
      if (value === "processing" || value === "in_progress") return "border-blue-500/40 bg-blue-500/10 text-blue-700 dark:text-blue-300";
      return "";
    }
    function occurredAt(value) {
      if (!value) return "—";
      const date = new Date(value);
      return Number.isNaN(date.getTime()) ? value : date.toLocaleString();
    }
    function openRetryModal(delivery) {
      modalType = "retry";
      activeDelivery = delivery;
      reason = "";
      acknowledgeRisk = false;
      errorMessage = "";
      isOpen = true;
      focusDialog();
    }
    function openReviewOutcomeModal(delivery) {
      modalType = "review";
      activeDelivery = delivery;
      reason = "";
      acknowledgeRisk = false;
      errorMessage = "";
      isOpen = true;
      focusDialog();
    }
    function closeModals() {
      if (isSubmitting) return;
      isOpen = false;
      activeDelivery = null;
      reason = "";
      acknowledgeRisk = false;
      errorMessage = "";
    }
    function submitRetry() {
      if (!activeDelivery || isSubmitting) return;
      if (modalType === "review" && (!acknowledgeRisk || !reason.trim())) return;
      isSubmitting = true;
      errorMessage = "";
      const payload = {
        reason: reason.trim(),
        expected_version: activeDelivery.version
      };
      if (modalType === "review") {
        payload.acknowledge_duplicate_risk = true;
      }
      router.post(`/setup/notifications/occurrences/${occurrence.occurrence_id}/deliveries/${activeDelivery.delivery_id}/retry`, payload, {
        preserveScroll: true,
        onSuccess: () => {
          isSubmitting = false;
          closeModals();
        },
        onError: (errors) => {
          const message = errors?.reason ?? errors?.acknowledge_duplicate_risk ?? errors?.delivery ?? "Unable to queue this delivery for retry. Please review the delivery and try again.";
          errorMessage = Array.isArray(message) ? message[0] : message;
        },
        onHttpException: (error) => {
          isSubmitting = false;
          if (error.status === 409) {
            errorMessage = "The delivery status was updated by another process. Please refresh the page.";
          } else if (error.status === 422) {
            errorMessage = error.response?.data?.message ?? "This delivery cannot be retried.";
          } else if (error.status === 403) {
            errorMessage = "You do not have permission to retry this delivery.";
          } else {
            errorMessage = "An error occurred while queueing the retry.";
          }
        },
        onNetworkError: () => {
          isSubmitting = false;
          errorMessage = "Network error. Please check your connection and try again.";
        },
        onFinish: () => {
          isSubmitting = false;
        }
      });
    }
    head("6660g3", $$renderer2, ($$renderer3) => {
      $$renderer3.title(($$renderer4) => {
        $$renderer4.push(`<title>Notification Occurrence · Setup</title>`);
      });
    });
    StaffLayout($$renderer2, {
      breadcrumbs,
      children: ($$renderer3) => {
        $$renderer3.push(`<div class="min-w-0 space-y-6 p-4 pb-40 sm:p-6 sm:pb-40">`);
        {
          let actions = function($$renderer4) {
            Link($$renderer4, {
              href: "/setup/notifications",
              class: "inline-flex min-h-11 items-center gap-2 rounded-lg border border-border/80 bg-card/85 px-3 py-2 text-sm font-medium text-foreground transition-colors hover:border-primary/50 hover:bg-muted focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring",
              children: ($$renderer5) => {
                Arrow_left($$renderer5, { class: "size-4" });
                $$renderer5.push(`<!---->Back to notifications`);
              },
              $$slots: { default: true }
            });
          };
          PageHeaderBar($$renderer3, {
            title: "Notification Occurrence",
            description: "Scoped read-only delivery details. Recipient addresses and raw provider payloads are not shown.",
            actions
          });
        }
        $$renderer3.push(`<!----> `);
        if (Card) {
          $$renderer3.push("<!--[-->");
          Card($$renderer3, {
            class: "border-border/70 shadow-xs",
            children: ($$renderer4) => {
              if (Card_header) {
                $$renderer4.push("<!--[-->");
                Card_header($$renderer4, {
                  class: "border-b border-border/60",
                  children: ($$renderer5) => {
                    $$renderer5.push(`<div class="flex flex-wrap items-start justify-between gap-3"><div class="space-y-1">`);
                    if (Card_description) {
                      $$renderer5.push("<!--[-->");
                      Card_description($$renderer5, {
                        children: ($$renderer6) => {
                          $$renderer6.push(`<!---->${escape_html(domainLabel(occurrence.domain))} · ${escape_html(occurredAt(occurrence.occurred_at))}`);
                        },
                        $$slots: { default: true }
                      });
                      $$renderer5.push("<!--]-->");
                    } else {
                      $$renderer5.push("<!--[!-->");
                      $$renderer5.push("<!--]-->");
                    }
                    $$renderer5.push(` `);
                    if (Card_title) {
                      $$renderer5.push("<!--[-->");
                      Card_title($$renderer5, {
                        class: "text-base",
                        children: ($$renderer6) => {
                          $$renderer6.push(`<!---->${escape_html(occurrence.safe_summary ?? "Notification details unavailable")}`);
                        },
                        $$slots: { default: true }
                      });
                      $$renderer5.push("<!--]-->");
                    } else {
                      $$renderer5.push("<!--[!-->");
                      $$renderer5.push("<!--]-->");
                    }
                    $$renderer5.push(`</div> `);
                    Badge($$renderer5, {
                      variant: statusVariant(occurrence.status),
                      class: `capitalize ${stringify(statusClass(occurrence.status))}`,
                      children: ($$renderer6) => {
                        $$renderer6.push(`<!---->${escape_html(statusLabel(occurrence.status))}`);
                      },
                      $$slots: { default: true }
                    });
                    $$renderer5.push(`<!----></div>`);
                  },
                  $$slots: { default: true }
                });
                $$renderer4.push("<!--]-->");
              } else {
                $$renderer4.push("<!--[!-->");
                $$renderer4.push("<!--]-->");
              }
              $$renderer4.push(` `);
              if (Card_content) {
                $$renderer4.push("<!--[-->");
                Card_content($$renderer4, {
                  class: "grid gap-4 p-4 sm:grid-cols-2",
                  children: ($$renderer5) => {
                    $$renderer5.push(`<div class="flex items-center gap-3 rounded-lg bg-muted/40 p-3">`);
                    Users($$renderer5, { class: "size-4 text-muted-foreground" });
                    $$renderer5.push(`<!----> <div><p class="text-xs text-muted-foreground">Recipient deliveries</p><p class="font-semibold">${escape_html(occurrence.recipient_count ?? deliveryRows().length)}</p></div></div> <div class="rounded-lg bg-muted/40 p-3"><p class="text-xs text-muted-foreground">Channels</p> <div class="mt-1 flex flex-wrap gap-1.5">`);
                    const each_array = ensure_array_like(occurrence.channel_summaries ?? []);
                    if (each_array.length !== 0) {
                      $$renderer5.push("<!--[-->");
                      for (let $$index = 0, $$length = each_array.length; $$index < $$length; $$index++) {
                        let item = each_array[$$index];
                        Badge($$renderer5, {
                          variant: "outline",
                          children: ($$renderer6) => {
                            $$renderer6.push(`<!---->${escape_html(item.channel)}: ${escape_html(statusLabel(item.status))}${escape_html(item.count != null ? ` · ${item.count}` : "")}`);
                          },
                          $$slots: { default: true }
                        });
                      }
                    } else {
                      $$renderer5.push("<!--[!-->");
                      $$renderer5.push(`<span class="text-sm">No channel summary available</span>`);
                    }
                    $$renderer5.push(`<!--]--></div></div>`);
                  },
                  $$slots: { default: true }
                });
                $$renderer4.push("<!--]-->");
              } else {
                $$renderer4.push("<!--[!-->");
                $$renderer4.push("<!--]-->");
              }
            },
            $$slots: { default: true }
          });
          $$renderer3.push("<!--]-->");
        } else {
          $$renderer3.push("<!--[!-->");
          $$renderer3.push("<!--]-->");
        }
        $$renderer3.push(` <section aria-labelledby="recipient-deliveries-heading" class="space-y-3"><div class="space-y-1"><h2 id="recipient-deliveries-heading" class="text-base font-semibold text-foreground">Recipient deliveries</h2> <p class="text-sm text-muted-foreground">Delivery status and resend options for this notification.</p></div> `);
        if (Card) {
          $$renderer3.push("<!--[-->");
          Card($$renderer3, {
            class: "overflow-hidden border-border/70 shadow-xs",
            children: ($$renderer4) => {
              if (deliveryRows().length > 0) {
                $$renderer4.push("<!--[0-->");
                $$renderer4.push(`<div class="overflow-x-auto">`);
                if (Table) {
                  $$renderer4.push("<!--[-->");
                  Table($$renderer4, {
                    class: "min-w-[720px]",
                    children: ($$renderer5) => {
                      if (Table_header) {
                        $$renderer5.push("<!--[-->");
                        Table_header($$renderer5, {
                          class: "bg-muted/40",
                          children: ($$renderer6) => {
                            if (Table_row) {
                              $$renderer6.push("<!--[-->");
                              Table_row($$renderer6, {
                                children: ($$renderer7) => {
                                  if (Table_head) {
                                    $$renderer7.push("<!--[-->");
                                    Table_head($$renderer7, {
                                      children: ($$renderer8) => {
                                        $$renderer8.push(`<!---->Recipient`);
                                      },
                                      $$slots: { default: true }
                                    });
                                    $$renderer7.push("<!--]-->");
                                  } else {
                                    $$renderer7.push("<!--[!-->");
                                    $$renderer7.push("<!--]-->");
                                  }
                                  if (Table_head) {
                                    $$renderer7.push("<!--[-->");
                                    Table_head($$renderer7, {
                                      children: ($$renderer8) => {
                                        $$renderer8.push(`<!---->Role / Type`);
                                      },
                                      $$slots: { default: true }
                                    });
                                    $$renderer7.push("<!--]-->");
                                  } else {
                                    $$renderer7.push("<!--[!-->");
                                    $$renderer7.push("<!--]-->");
                                  }
                                  if (Table_head) {
                                    $$renderer7.push("<!--[-->");
                                    Table_head($$renderer7, {
                                      children: ($$renderer8) => {
                                        $$renderer8.push(`<!---->Channel`);
                                      },
                                      $$slots: { default: true }
                                    });
                                    $$renderer7.push("<!--]-->");
                                  } else {
                                    $$renderer7.push("<!--[!-->");
                                    $$renderer7.push("<!--]-->");
                                  }
                                  if (Table_head) {
                                    $$renderer7.push("<!--[-->");
                                    Table_head($$renderer7, {
                                      children: ($$renderer8) => {
                                        $$renderer8.push(`<!---->Status`);
                                      },
                                      $$slots: { default: true }
                                    });
                                    $$renderer7.push("<!--]-->");
                                  } else {
                                    $$renderer7.push("<!--[!-->");
                                    $$renderer7.push("<!--]-->");
                                  }
                                  if (Table_head) {
                                    $$renderer7.push("<!--[-->");
                                    Table_head($$renderer7, {
                                      children: ($$renderer8) => {
                                        $$renderer8.push(`<!---->Dispatched`);
                                      },
                                      $$slots: { default: true }
                                    });
                                    $$renderer7.push("<!--]-->");
                                  } else {
                                    $$renderer7.push("<!--[!-->");
                                    $$renderer7.push("<!--]-->");
                                  }
                                  if (Table_head) {
                                    $$renderer7.push("<!--[-->");
                                    Table_head($$renderer7, {
                                      class: "text-right",
                                      children: ($$renderer8) => {
                                        $$renderer8.push(`<!---->Actions`);
                                      },
                                      $$slots: { default: true }
                                    });
                                    $$renderer7.push("<!--]-->");
                                  } else {
                                    $$renderer7.push("<!--[!-->");
                                    $$renderer7.push("<!--]-->");
                                  }
                                },
                                $$slots: { default: true }
                              });
                              $$renderer6.push("<!--]-->");
                            } else {
                              $$renderer6.push("<!--[!-->");
                              $$renderer6.push("<!--]-->");
                            }
                          },
                          $$slots: { default: true }
                        });
                        $$renderer5.push("<!--]-->");
                      } else {
                        $$renderer5.push("<!--[!-->");
                        $$renderer5.push("<!--]-->");
                      }
                      $$renderer5.push(` `);
                      if (Table_body) {
                        $$renderer5.push("<!--[-->");
                        Table_body($$renderer5, {
                          children: ($$renderer6) => {
                            $$renderer6.push(`<!--[-->`);
                            const each_array_1 = ensure_array_like(deliveryRows());
                            for (let $$index_1 = 0, $$length = each_array_1.length; $$index_1 < $$length; $$index_1++) {
                              let delivery = each_array_1[$$index_1];
                              if (Table_row) {
                                $$renderer6.push("<!--[-->");
                                Table_row($$renderer6, {
                                  children: ($$renderer7) => {
                                    if (Table_cell) {
                                      $$renderer7.push("<!--[-->");
                                      Table_cell($$renderer7, {
                                        class: "font-medium",
                                        children: ($$renderer8) => {
                                          $$renderer8.push(`<div>${escape_html(delivery.recipient_label ?? "Recipient label unavailable")}</div> <details class="mt-1 text-xs font-normal text-muted-foreground"><summary class="cursor-pointer">Troubleshooting details</summary> <div class="mt-1 space-y-1"><p>Diagnostic: ${escape_html(delivery.diagnostic_redacted ?? "Not available")}</p> <p>Provider reference: ${escape_html(delivery.provider_reference_redacted ?? "Not available")}</p> <p>Attempts: ${escape_html(delivery.attempt_count ?? "—")}</p></div></details>`);
                                        },
                                        $$slots: { default: true }
                                      });
                                      $$renderer7.push("<!--]-->");
                                    } else {
                                      $$renderer7.push("<!--[!-->");
                                      $$renderer7.push("<!--]-->");
                                    }
                                    $$renderer7.push(` `);
                                    if (Table_cell) {
                                      $$renderer7.push("<!--[-->");
                                      Table_cell($$renderer7, {
                                        children: ($$renderer8) => {
                                          $$renderer8.push(`<!---->${escape_html(recipientTypeLabel(delivery.recipient_type))}`);
                                        },
                                        $$slots: { default: true }
                                      });
                                      $$renderer7.push("<!--]-->");
                                    } else {
                                      $$renderer7.push("<!--[!-->");
                                      $$renderer7.push("<!--]-->");
                                    }
                                    $$renderer7.push(` `);
                                    if (Table_cell) {
                                      $$renderer7.push("<!--[-->");
                                      Table_cell($$renderer7, {
                                        children: ($$renderer8) => {
                                          $$renderer8.push(`<!---->${escape_html(channelLabel(delivery.channel))}`);
                                        },
                                        $$slots: { default: true }
                                      });
                                      $$renderer7.push("<!--]-->");
                                    } else {
                                      $$renderer7.push("<!--[!-->");
                                      $$renderer7.push("<!--]-->");
                                    }
                                    $$renderer7.push(` `);
                                    if (Table_cell) {
                                      $$renderer7.push("<!--[-->");
                                      Table_cell($$renderer7, {
                                        children: ($$renderer8) => {
                                          Badge($$renderer8, {
                                            variant: statusVariant(delivery.status),
                                            class: `capitalize ${stringify(statusClass(delivery.status))}`,
                                            children: ($$renderer9) => {
                                              $$renderer9.push(`<!---->${escape_html(statusLabel(delivery.status))}`);
                                            },
                                            $$slots: { default: true }
                                          });
                                        },
                                        $$slots: { default: true }
                                      });
                                      $$renderer7.push("<!--]-->");
                                    } else {
                                      $$renderer7.push("<!--[!-->");
                                      $$renderer7.push("<!--]-->");
                                    }
                                    $$renderer7.push(` `);
                                    if (Table_cell) {
                                      $$renderer7.push("<!--[-->");
                                      Table_cell($$renderer7, {
                                        class: "whitespace-nowrap",
                                        children: ($$renderer8) => {
                                          $$renderer8.push(`<!---->${escape_html(occurredAt(delivery.accepted_at))}`);
                                        },
                                        $$slots: { default: true }
                                      });
                                      $$renderer7.push("<!--]-->");
                                    } else {
                                      $$renderer7.push("<!--[!-->");
                                      $$renderer7.push("<!--]-->");
                                    }
                                    $$renderer7.push(` `);
                                    if (Table_cell) {
                                      $$renderer7.push("<!--[-->");
                                      Table_cell($$renderer7, {
                                        class: "text-right",
                                        children: ($$renderer8) => {
                                          if (delivery.status === "superseded") {
                                            $$renderer8.push("<!--[0-->");
                                            Badge($$renderer8, {
                                              variant: "outline",
                                              class: "text-muted-foreground text-xs",
                                              children: ($$renderer9) => {
                                                $$renderer9.push(`<!---->Notice Outdated`);
                                              },
                                              $$slots: { default: true }
                                            });
                                          } else if (delivery.status === "failed_terminal" || delivery.status === "failed") {
                                            $$renderer8.push("<!--[1-->");
                                            if (can_retry) {
                                              $$renderer8.push("<!--[0-->");
                                              Button($$renderer8, {
                                                variant: "outline",
                                                size: "sm",
                                                class: "h-8 text-xs",
                                                onclick: () => openRetryModal(delivery),
                                                children: ($$renderer9) => {
                                                  $$renderer9.push(`<!---->Resend Notice`);
                                                },
                                                $$slots: { default: true }
                                              });
                                            } else {
                                              $$renderer8.push("<!--[-1-->");
                                              $$renderer8.push(`<span class="text-xs text-muted-foreground">—</span>`);
                                            }
                                            $$renderer8.push(`<!--]-->`);
                                          } else if (delivery.status === "outcome_unknown") {
                                            $$renderer8.push("<!--[2-->");
                                            if (can_retry) {
                                              $$renderer8.push("<!--[0-->");
                                              Button($$renderer8, {
                                                variant: "outline",
                                                size: "sm",
                                                class: "h-8 border-amber-500/50 text-amber-700 hover:bg-amber-500/10 dark:text-amber-300 text-xs",
                                                onclick: () => openReviewOutcomeModal(delivery),
                                                children: ($$renderer9) => {
                                                  $$renderer9.push(`<!---->Review outcome`);
                                                },
                                                $$slots: { default: true }
                                              });
                                            } else {
                                              $$renderer8.push("<!--[-1-->");
                                              $$renderer8.push(`<span class="text-xs text-muted-foreground">—</span>`);
                                            }
                                            $$renderer8.push(`<!--]-->`);
                                          } else {
                                            $$renderer8.push("<!--[-1-->");
                                            $$renderer8.push(`<span class="text-xs text-muted-foreground">—</span>`);
                                          }
                                          $$renderer8.push(`<!--]-->`);
                                        },
                                        $$slots: { default: true }
                                      });
                                      $$renderer7.push("<!--]-->");
                                    } else {
                                      $$renderer7.push("<!--[!-->");
                                      $$renderer7.push("<!--]-->");
                                    }
                                  },
                                  $$slots: { default: true }
                                });
                                $$renderer6.push("<!--]-->");
                              } else {
                                $$renderer6.push("<!--[!-->");
                                $$renderer6.push("<!--]-->");
                              }
                            }
                            $$renderer6.push(`<!--]-->`);
                          },
                          $$slots: { default: true }
                        });
                        $$renderer5.push("<!--]-->");
                      } else {
                        $$renderer5.push("<!--[!-->");
                        $$renderer5.push("<!--]-->");
                      }
                    },
                    $$slots: { default: true }
                  });
                  $$renderer4.push("<!--]-->");
                } else {
                  $$renderer4.push("<!--[!-->");
                  $$renderer4.push("<!--]-->");
                }
                $$renderer4.push(`</div>`);
              } else {
                $$renderer4.push("<!--[-1-->");
                if (Card_content) {
                  $$renderer4.push("<!--[-->");
                  Card_content($$renderer4, {
                    class: "p-8 text-center text-sm text-muted-foreground",
                    children: ($$renderer5) => {
                      $$renderer5.push(`<!---->No recipient deliveries are available for this occurrence.`);
                    },
                    $$slots: { default: true }
                  });
                  $$renderer4.push("<!--]-->");
                } else {
                  $$renderer4.push("<!--[!-->");
                  $$renderer4.push("<!--]-->");
                }
              }
              $$renderer4.push(`<!--]-->`);
            },
            $$slots: { default: true }
          });
          $$renderer3.push("<!--]-->");
        } else {
          $$renderer3.push("<!--[!-->");
          $$renderer3.push("<!--]-->");
        }
        $$renderer3.push(` `);
        SimplePagination($$renderer3, { data: deliveries });
        $$renderer3.push(`<!----></section> <section aria-labelledby="history-heading" class="space-y-3"><div class="flex items-center gap-2">`);
        History($$renderer3, { class: "size-4 text-primary" });
        $$renderer3.push(`<!----> <h2 id="history-heading" class="text-base font-semibold text-foreground">Redacted operational history</h2></div> `);
        if (Card) {
          $$renderer3.push("<!--[-->");
          Card($$renderer3, {
            class: "border-border/70 shadow-xs",
            children: ($$renderer4) => {
              if (historyRows().length > 0) {
                $$renderer4.push("<!--[0-->");
                if (Card_content) {
                  $$renderer4.push("<!--[-->");
                  Card_content($$renderer4, {
                    class: "divide-y divide-border/60 p-0",
                    children: ($$renderer5) => {
                      $$renderer5.push(`<!--[-->`);
                      const each_array_2 = ensure_array_like(historyRows());
                      for (let index = 0, $$length = each_array_2.length; index < $$length; index++) {
                        let entry = each_array_2[index];
                        $$renderer5.push(`<div class="flex flex-wrap items-center justify-between gap-2 p-4"><p class="text-sm font-medium">${escape_html(humanEventName(entry.event))}</p> <time class="text-xs text-muted-foreground">${escape_html(occurredAt(entry.occurred_at))}</time></div>`);
                      }
                      $$renderer5.push(`<!--]-->`);
                    },
                    $$slots: { default: true }
                  });
                  $$renderer4.push("<!--]-->");
                } else {
                  $$renderer4.push("<!--[!-->");
                  $$renderer4.push("<!--]-->");
                }
              } else {
                $$renderer4.push("<!--[-1-->");
                if (Card_content) {
                  $$renderer4.push("<!--[-->");
                  Card_content($$renderer4, {
                    class: "p-4 text-sm text-muted-foreground",
                    children: ($$renderer5) => {
                      $$renderer5.push(`<!---->No matching delivery history is available for this occurrence.`);
                    },
                    $$slots: { default: true }
                  });
                  $$renderer4.push("<!--]-->");
                } else {
                  $$renderer4.push("<!--[!-->");
                  $$renderer4.push("<!--]-->");
                }
              }
              $$renderer4.push(`<!--]-->`);
            },
            $$slots: { default: true }
          });
          $$renderer3.push("<!--]-->");
        } else {
          $$renderer3.push("<!--[!-->");
          $$renderer3.push("<!--]-->");
        }
        $$renderer3.push(` `);
        SimplePagination($$renderer3, { data: history });
        $$renderer3.push(`<!----></section> `);
        if (isOpen) {
          $$renderer3.push("<!--[0-->");
          $$renderer3.push(`<div class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4" role="presentation"><div class="max-h-[90vh] w-full max-w-lg space-y-4 overflow-y-auto rounded-xl border border-border bg-card p-5 shadow-xl" role="dialog" aria-modal="true" aria-labelledby="retry-dialog-title" aria-describedby="retry-dialog-description" tabindex="-1">`);
          if (modalType === "review") {
            $$renderer3.push("<!--[0-->");
            $$renderer3.push(`<div class="space-y-1"><h2 id="retry-dialog-title" class="text-lg font-semibold">Confirm Resend Notice</h2> <p id="retry-dialog-description" class="sr-only">Review duplicate-delivery risk before resending.</p></div> <div class="rounded-lg border border-amber-500/40 bg-amber-500/10 p-3 text-sm text-amber-800 dark:text-amber-200" role="alert">The mail delivery was interrupted earlier. The recipient may have already received this notification. Resending may deliver a duplicate copy.</div> <label class="flex items-start gap-2 text-sm"><input type="checkbox" class="mt-1 size-4 accent-primary"${attr("checked", acknowledgeRisk, true)} required=""/> <span>I understand this delivery may produce a duplicate notice and accept the risk</span></label> <div class="space-y-2"><label for="retry-reason" class="text-sm font-medium">Reason for resending (required)</label> <textarea id="retry-reason" class="min-h-24 w-full rounded-md border border-input bg-background px-3 py-2 text-sm outline-none focus-visible:ring-2 focus-visible:ring-ring"${attr("disabled", isSubmitting, true)} required="">`);
            const $$body = escape_html(reason);
            if ($$body) {
              $$renderer3.push(`${$$body}`);
            }
            $$renderer3.push(`</textarea></div>`);
          } else {
            $$renderer3.push("<!--[-1-->");
            $$renderer3.push(`<div class="space-y-1"><h2 id="retry-dialog-title" class="text-lg font-semibold">Resend Notice</h2> <p id="retry-dialog-description" class="text-sm text-muted-foreground">Send a fresh copy of this notification to ${escape_html(activeDelivery.recipient_label)}.</p></div> <div class="space-y-2"><label for="retry-reason" class="text-sm font-medium">Audit note (optional)</label> <textarea id="retry-reason" class="min-h-24 w-full rounded-md border border-input bg-background px-3 py-2 text-sm outline-none focus-visible:ring-2 focus-visible:ring-ring"${attr("disabled", isSubmitting, true)}>`);
            const $$body_1 = escape_html(reason);
            if ($$body_1) {
              $$renderer3.push(`${$$body_1}`);
            }
            $$renderer3.push(`</textarea></div>`);
          }
          $$renderer3.push(`<!--]--> `);
          if (errorMessage) {
            $$renderer3.push("<!--[0-->");
            $$renderer3.push(`<p class="text-sm text-destructive" role="alert">${escape_html(errorMessage)}</p>`);
          } else {
            $$renderer3.push("<!--[-1-->");
          }
          $$renderer3.push(`<!--]--> <div class="flex justify-end gap-2">`);
          Button($$renderer3, {
            variant: "outline",
            type: "button",
            onclick: closeModals,
            disabled: isSubmitting,
            children: ($$renderer4) => {
              $$renderer4.push(`<!---->Cancel`);
            },
            $$slots: { default: true }
          });
          $$renderer3.push(`<!----> `);
          Button($$renderer3, {
            type: "button",
            onclick: submitRetry,
            disabled: isSubmitting || modalType === "review" && (!acknowledgeRisk || !reason.trim()),
            children: ($$renderer4) => {
              if (isSubmitting) {
                $$renderer4.push("<!--[0-->");
                $$renderer4.push(`Queueing...`);
              } else if (modalType === "review") {
                $$renderer4.push("<!--[1-->");
                $$renderer4.push(`Confirm &amp; Resend`);
              } else {
                $$renderer4.push("<!--[-1-->");
                $$renderer4.push(`Resend Notice Now`);
              }
              $$renderer4.push(`<!--]-->`);
            },
            $$slots: { default: true }
          });
          $$renderer3.push(`<!----></div></div></div>`);
        } else {
          $$renderer3.push("<!--[-1-->");
        }
        $$renderer3.push(`<!--]--></div>`);
      }
    });
  });
}
export {
  Occurrence as default
};
