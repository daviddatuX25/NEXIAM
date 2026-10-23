import { h as head, b as attr, c as attr_class, e as escape_html, a as ensure_array_like, B as Button } from "../app.js";
import { S as StaffLayout } from "./StaffLayout-CR-MZopa.js";
import { P as PageHeaderBar } from "./PageHeaderBar-DJhT5vOR.js";
import { N as NoticeFamilyList } from "./NoticeFamilyList-CQLzNv8n.js";
import { C as Card, a as Card_content } from "./card-content-Brg2wRkV.js";
import { C as Card_description } from "./card-description-BGy_v5-d.js";
import "clsx";
import { C as Card_header, a as Card_title } from "./card-title-BNY2ZPje.js";
import { B as Badge } from "./badge-D9Vgs7xh.js";
import { i as Switch } from "./PreferencesMenu-CCPeta-5.js";
import { u as useForm } from "./useForm.svelte-C0OkDFGu.js";
import { L as Link } from "./Link-B7ks7rBK.js";
import "axios";
import "@inertiajs/core";
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
import "./inbox-AmUVtgA2.js";
import "./mail-DBtkjaCt.js";
import "./AvatarBadge-DQ5Qru9A.js";
import "./input-BUZAZKIK.js";
import "./dialog-description-D12L0QA9.js";
import "./noop-BQS7OFs8.js";
import "./create-id-CUJmDlea.js";
import "style-to-object";
import "./scroll-lock-B1ibcKMx.js";
import "./watch.svelte-BcPoqckX.js";
import "./is-C6cm64pF.js";
import "tabbable";
import "./circle-alert-jWXJ7oC0.js";
import "./refresh-cw-CYdk8aE0.js";
import "./camera-C09Eyogi.js";
import "./settings-C5xOKpV3.js";
import "./arrow-right-CzynHiIo.js";
import "./circle-check-C59ZS7Zv.js";
import "./dialog-footer-kNnwKT2h.js";
import "./clock-B4fXoAi9.js";
import "./InstitutionalBackdrop-BjxeRqDu.js";
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
import "@floating-ui/dom";
import "./hidden-input-GSZDfM3n.js";
import "./zoom-out-DsJCISKt.js";
import "./rotate-ccw-D6Dm_efx.js";
import "es-toolkit";
import "./clone-BJz-lxsY.js";
import "es-toolkit/compat";
function Examination($$renderer, $$props) {
  $$renderer.component(($$renderer2) => {
    let {
      active_cycle = null,
      policy = {},
      custom_sessions = [],
      notices = [],
      capabilities = { can_manage: false }
    } = $$props;
    const form = useForm({
      expected_version: policy.version ?? 0,
      publication_email_enabled: policy.publication_email_enabled ?? true,
      reminder_email_enabled: policy.reminder_email_enabled ?? true,
      reminder_offsets_minutes: policy.reminder_offsets_minutes ?? [4320, 1440, 120]
    });
    const reminderOptions = [
      { minutes: 4320, label: "72h" },
      { minutes: 1440, label: "24h" },
      { minutes: 120, label: "2h" },
      { minutes: 60, label: "1h" }
    ];
    let policyStatus = "";
    let activeTab = "notices";
    const breadcrumbs = [
      { label: "Setup", href: "/setup" },
      { label: "Email & Notifications", href: "/setup/notifications" },
      { label: "Examination Desk" }
    ];
    function toggleReminder(minutes) {
      const selected = form.reminder_offsets_minutes;
      form.reminder_offsets_minutes = selected.includes(minutes) ? selected.filter((offset) => offset !== minutes) : [...selected, minutes].sort((left, right) => right - left);
    }
    let $$settled = true;
    let $$inner_renderer;
    function $$render_inner($$renderer3) {
      head("6xrrjd", $$renderer3, ($$renderer4) => {
        $$renderer4.title(($$renderer5) => {
          $$renderer5.push(`<title>Examination Desk · Notifications</title>`);
        });
      });
      StaffLayout($$renderer3, {
        breadcrumbs,
        children: ($$renderer4) => {
          $$renderer4.push(`<div class="min-w-0 space-y-7 p-4 sm:p-6">`);
          PageHeaderBar($$renderer4, {
            title: "Examination Desk",
            description: "Canonical examination notices, academic year policy and session-specific overrides."
          });
          $$renderer4.push(`<!----> <div role="tablist" aria-label="Examination setup sections" class="flex w-full gap-1 rounded-lg border border-border bg-muted/30 p-1 sm:w-auto"><button type="button" id="examination-tab-notices" role="tab"${attr("aria-selected", activeTab === "notices")} aria-controls="examination-notices-panel"${attr("tabindex", 0)}${attr_class(`min-h-10 flex-1 rounded-md px-4 text-sm font-medium transition-colors focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring sm:flex-none ${"bg-background text-foreground shadow-sm"}`)}>Notices</button> <button type="button" id="examination-tab-rules" role="tab"${attr("aria-selected", activeTab === "rules")} aria-controls="examination-rules-panel"${attr("tabindex", -1)}${attr_class(`min-h-10 flex-1 rounded-md px-4 text-sm font-medium transition-colors focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring sm:flex-none ${"text-muted-foreground hover:text-foreground"}`)}>Rules &amp; Overrides</button></div> <section role="tabpanel" id="examination-notices-panel" aria-labelledby="examination-tab-notices" tabindex="0"${attr("hidden", activeTab !== "notices")} class="min-w-0">`);
          NoticeFamilyList($$renderer4, {
            notices,
            domain: "examination",
            canEdit: capabilities.can_manage
          });
          $$renderer4.push(`<!----></section> <section role="tabpanel" id="examination-rules-panel" aria-labelledby="examination-tab-rules" tabindex="0"${attr("hidden", activeTab !== "rules")}><div class="grid min-w-0 gap-6 xl:grid-cols-2">`);
          if (Card) {
            $$renderer4.push("<!--[-->");
            Card($$renderer4, {
              class: "min-w-0 border-border/70",
              children: ($$renderer5) => {
                if (Card_header) {
                  $$renderer5.push("<!--[-->");
                  Card_header($$renderer5, {
                    children: ($$renderer6) => {
                      if (Card_title) {
                        $$renderer6.push("<!--[-->");
                        Card_title($$renderer6, {
                          children: ($$renderer7) => {
                            $$renderer7.push(`<!---->Examination communication rules`);
                          },
                          $$slots: { default: true }
                        });
                        $$renderer6.push("<!--]-->");
                      } else {
                        $$renderer6.push("<!--[!-->");
                        $$renderer6.push("<!--]-->");
                      }
                      if (Card_description) {
                        $$renderer6.push("<!--[-->");
                        Card_description($$renderer6, {
                          children: ($$renderer7) => {
                            $$renderer7.push(`<!---->${escape_html(active_cycle?.label ?? "No active academic year")} · Required inbox delivery is unaffected by optional email settings.`);
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
                if (Card_content) {
                  $$renderer5.push("<!--[-->");
                  Card_content($$renderer5, {
                    children: ($$renderer6) => {
                      $$renderer6.push(`<form class="space-y-5"><fieldset${attr("disabled", !capabilities.can_manage || form.processing || !active_cycle, true)} class="min-w-0 space-y-4"><legend class="sr-only">Examination policy settings</legend> <label class="flex items-start justify-between gap-4 rounded-lg border border-border p-4"><span class="text-sm font-medium">Email candidates when their examination schedule is published</span>`);
                      Switch($$renderer6, {
                        "aria-label": "Email examination schedule publication",
                        get checked() {
                          return form.publication_email_enabled;
                        },
                        set checked($$value) {
                          form.publication_email_enabled = $$value;
                          $$settled = false;
                        }
                      });
                      $$renderer6.push(`<!----></label> <label class="flex items-start justify-between gap-4 rounded-lg border border-border p-4"><span class="text-sm font-medium">Send countdown reminder emails in addition to required inbox reminders</span>`);
                      Switch($$renderer6, {
                        "aria-label": "Send countdown reminder emails",
                        get checked() {
                          return form.reminder_email_enabled;
                        },
                        set checked($$value) {
                          form.reminder_email_enabled = $$value;
                          $$settled = false;
                        }
                      });
                      $$renderer6.push(`<!----></label> <fieldset class="space-y-3 rounded-lg border border-border p-4"><legend class="px-1 text-sm font-semibold">Reminder countdown intervals</legend> <p class="text-sm text-muted-foreground">These windows govern in-app reminders and optional mail before the assigned session.</p> <div class="flex flex-wrap gap-2"><!--[-->`);
                      const each_array = ensure_array_like(reminderOptions);
                      for (let $$index = 0, $$length = each_array.length; $$index < $$length; $$index++) {
                        let option = each_array[$$index];
                        Button($$renderer6, {
                          type: "button",
                          variant: form.reminder_offsets_minutes.includes(option.minutes) ? "default" : "outline",
                          "aria-pressed": form.reminder_offsets_minutes.includes(option.minutes),
                          onclick: () => toggleReminder(option.minutes),
                          children: ($$renderer7) => {
                            $$renderer7.push(`<!---->${escape_html(option.label)}`);
                          },
                          $$slots: { default: true }
                        });
                      }
                      $$renderer6.push(`<!--]--></div></fieldset></fieldset> <!--[-->`);
                      const each_array_1 = ensure_array_like(Object.values(form.errors ?? {}));
                      for (let $$index_1 = 0, $$length = each_array_1.length; $$index_1 < $$length; $$index_1++) {
                        let message = each_array_1[$$index_1];
                        $$renderer6.push(`<p role="alert" class="text-sm text-destructive">${escape_html(message)}</p>`);
                      }
                      $$renderer6.push(`<!--]--> <div class="flex flex-wrap items-center justify-between gap-3"><p class="text-sm text-muted-foreground">Persisted policy version ${escape_html(policy.version ?? 0)}</p>`);
                      Button($$renderer6, {
                        type: "submit",
                        disabled: !capabilities.can_manage || form.processing || !active_cycle,
                        children: ($$renderer7) => {
                          $$renderer7.push(`<!---->${escape_html(form.processing ? "Saving…" : "Save Examination Rules")}`);
                        },
                        $$slots: { default: true }
                      });
                      $$renderer6.push(`<!----></div> <p role="status" class="text-sm text-muted-foreground">${escape_html(policyStatus)}</p></form>`);
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
          $$renderer4.push(` `);
          if (Card) {
            $$renderer4.push("<!--[-->");
            Card($$renderer4, {
              class: "min-w-0 border-border/70",
              children: ($$renderer5) => {
                if (Card_header) {
                  $$renderer5.push("<!--[-->");
                  Card_header($$renderer5, {
                    children: ($$renderer6) => {
                      if (Card_title) {
                        $$renderer6.push("<!--[-->");
                        Card_title($$renderer6, {
                          children: ($$renderer7) => {
                            $$renderer7.push(`<!---->Active session overrides`);
                          },
                          $$slots: { default: true }
                        });
                        $$renderer6.push("<!--]-->");
                      } else {
                        $$renderer6.push("<!--[!-->");
                        $$renderer6.push("<!--]-->");
                      }
                      if (Card_description) {
                        $$renderer6.push("<!--[-->");
                        Card_description($$renderer6, {
                          children: ($$renderer7) => {
                            $$renderer7.push(`<!---->Existing session settings and publication snapshots retain precedence over academic year defaults.`);
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
                if (Card_content) {
                  $$renderer5.push("<!--[-->");
                  Card_content($$renderer5, {
                    children: ($$renderer6) => {
                      $$renderer6.push(`<div class="divide-y divide-border">`);
                      const each_array_2 = ensure_array_like(custom_sessions);
                      if (each_array_2.length !== 0) {
                        $$renderer6.push("<!--[-->");
                        for (let $$index_2 = 0, $$length = each_array_2.length; $$index_2 < $$length; $$index_2++) {
                          let session = each_array_2[$$index_2];
                          $$renderer6.push(`<article class="space-y-2 py-4"><div class="flex flex-wrap items-center justify-between gap-2"><h3 class="break-words text-sm font-semibold">${escape_html(session.code)}</h3>`);
                          Badge($$renderer6, {
                            variant: "outline",
                            children: ($$renderer7) => {
                              $$renderer7.push(`<!---->${escape_html(session.status)}`);
                            },
                            $$slots: { default: true }
                          });
                          $$renderer6.push(`<!----></div> <p class="break-words text-sm text-muted-foreground">${escape_html(session.room_name ?? "Venue not set")} · ${escape_html(session.starts_at ?? "Time not set")}</p> <p class="text-sm text-muted-foreground">Custom countdowns: ${escape_html(reminderOptions.filter((option) => (session.reminder_offsets_minutes ?? []).includes(option.minutes)).map((option) => option.label).join(", ") || "None")}</p> `);
                          Link($$renderer6, {
                            href: `/exams/sessions/${session.id}/edit`,
                            class: "inline-flex min-h-11 items-center text-sm font-medium text-primary underline underline-offset-4",
                            children: ($$renderer7) => {
                              $$renderer7.push(`<!---->Edit session`);
                            },
                            $$slots: { default: true }
                          });
                          $$renderer6.push(`<!----></article>`);
                        }
                      } else {
                        $$renderer6.push("<!--[!-->");
                        $$renderer6.push(`<p class="py-5 text-sm text-muted-foreground">No session-specific overrides are configured. Sessions inherit academic year defaults.</p>`);
                      }
                      $$renderer6.push(`<!--]--></div>`);
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
          $$renderer4.push(`</div></section></div>`);
        }
      });
    }
    do {
      $$settled = true;
      $$inner_renderer = $$renderer2.copy();
      $$render_inner($$inner_renderer);
    } while (!$$settled);
    $$renderer2.subsume($$inner_renderer);
  });
}
export {
  Examination as default
};
