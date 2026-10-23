import { h as head, e as escape_html, b as attr, a as ensure_array_like, B as Button } from "../app.js";
import { S as StaffLayout } from "./StaffLayout-CR-MZopa.js";
import { P as PageHeaderBar } from "./PageHeaderBar-DJhT5vOR.js";
import { N as NoticeFamilyList } from "./NoticeFamilyList-CQLzNv8n.js";
import { B as Badge } from "./badge-D9Vgs7xh.js";
import { C as Card, a as Card_content } from "./card-content-Brg2wRkV.js";
import { C as Card_description } from "./card-description-BGy_v5-d.js";
import "clsx";
import { C as Card_header, a as Card_title } from "./card-title-BNY2ZPje.js";
import { i as Switch } from "./PreferencesMenu-CCPeta-5.js";
import { u as useForm } from "./useForm.svelte-C0OkDFGu.js";
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
import "@floating-ui/dom";
import "./hidden-input-GSZDfM3n.js";
import "./zoom-out-DsJCISKt.js";
import "./rotate-ccw-D6Dm_efx.js";
import "es-toolkit";
import "./clone-BJz-lxsY.js";
import "es-toolkit/compat";
function Admissions($$renderer, $$props) {
  $$renderer.component(($$renderer2) => {
    let {
      active_cycle = null,
      policy = {},
      notices = [],
      capabilities = { can_manage: false }
    } = $$props;
    const form = useForm({
      expected_version: policy.version ?? 0,
      applicant_status_email_enabled: policy.applicant_status_email_enabled ?? false,
      registrar_new_submission_alert_enabled: policy.registrar_new_submission_alert_enabled ?? false
    });
    let policyStatus = "";
    const breadcrumbs = [
      { label: "Setup", href: "/setup" },
      { label: "Email & Notifications", href: "/setup/notifications" },
      { label: "Admissions Desk" }
    ];
    let $$settled = true;
    let $$inner_renderer;
    function $$render_inner($$renderer3) {
      head("1hq7lcy", $$renderer3, ($$renderer4) => {
        $$renderer4.title(($$renderer5) => {
          $$renderer5.push(`<title>Admissions Desk · Notifications</title>`);
        });
      });
      StaffLayout($$renderer3, {
        breadcrumbs,
        children: ($$renderer4) => {
          $$renderer4.push(`<div class="min-w-0 space-y-7 p-4 sm:p-6">`);
          PageHeaderBar($$renderer4, {
            title: "Admissions Desk",
            description: "Canonical admissions notices and authoritative academic year communication rules."
          });
          $$renderer4.push(`<!----> `);
          NoticeFamilyList($$renderer4, {
            notices,
            domain: "admissions",
            canEdit: capabilities.can_manage
          });
          $$renderer4.push(`<!----> `);
          if (Card) {
            $$renderer4.push("<!--[-->");
            Card($$renderer4, {
              class: "border-border/70",
              children: ($$renderer5) => {
                if (Card_header) {
                  $$renderer5.push("<!--[-->");
                  Card_header($$renderer5, {
                    children: ($$renderer6) => {
                      $$renderer6.push(`<div class="flex flex-wrap items-start justify-between gap-3"><div>`);
                      if (Card_title) {
                        $$renderer6.push("<!--[-->");
                        Card_title($$renderer6, {
                          children: ($$renderer7) => {
                            $$renderer7.push(`<!---->Admissions communication rules`);
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
                            $$renderer7.push(`<!---->These academic year settings govern delivery. Editing a notice does not change channels.`);
                          },
                          $$slots: { default: true }
                        });
                        $$renderer6.push("<!--]-->");
                      } else {
                        $$renderer6.push("<!--[!-->");
                        $$renderer6.push("<!--]-->");
                      }
                      $$renderer6.push(`</div> `);
                      Badge($$renderer6, {
                        variant: "outline",
                        children: ($$renderer7) => {
                          $$renderer7.push(`<!---->${escape_html(active_cycle ? `Active cycle · ${active_cycle.label ?? active_cycle.name}` : "No active academic year")}`);
                        },
                        $$slots: { default: true }
                      });
                      $$renderer6.push(`<!----></div>`);
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
                      $$renderer6.push(`<form class="space-y-5"><fieldset${attr("disabled", !capabilities.can_manage || form.processing || !active_cycle, true)} class="grid min-w-0 gap-4 lg:grid-cols-2"><legend class="sr-only">Admissions policy settings</legend> <label class="flex items-start justify-between gap-4 rounded-lg border border-border p-4"><span class="space-y-1"><span class="block text-sm font-medium">Email applicants upon status change</span><span class="block text-sm text-muted-foreground">Accepted, dismissed and correction-requested notices follow this optional email setting. Required inbox delivery is unchanged.</span></span> `);
                      Switch($$renderer6, {
                        "aria-label": "Email applicants upon status change",
                        get checked() {
                          return form.applicant_status_email_enabled;
                        },
                        set checked($$value) {
                          form.applicant_status_email_enabled = $$value;
                          $$settled = false;
                        }
                      });
                      $$renderer6.push(`<!----></label> <label class="flex items-start justify-between gap-4 rounded-lg border border-border p-4"><span class="space-y-1"><span class="block text-sm font-medium">Alert Registrar queue about new submissions</span><span class="block text-sm text-muted-foreground">Notify the authorized Registrar queue when an application is submitted.</span></span> `);
                      Switch($$renderer6, {
                        "aria-label": "Alert Registrar queue about new submissions",
                        get checked() {
                          return form.registrar_new_submission_alert_enabled;
                        },
                        set checked($$value) {
                          form.registrar_new_submission_alert_enabled = $$value;
                          $$settled = false;
                        }
                      });
                      $$renderer6.push(`<!----></label></fieldset> <!--[-->`);
                      const each_array = ensure_array_like(Object.values(form.errors ?? {}));
                      for (let $$index = 0, $$length = each_array.length; $$index < $$length; $$index++) {
                        let message = each_array[$$index];
                        $$renderer6.push(`<p role="alert" class="text-sm text-destructive">${escape_html(message)}</p>`);
                      }
                      $$renderer6.push(`<!--]--> <div class="flex flex-wrap items-center justify-between gap-3"><p class="text-sm text-muted-foreground">Persisted policy version ${escape_html(policy.version ?? 0)}</p>`);
                      Button($$renderer6, {
                        type: "submit",
                        disabled: !capabilities.can_manage || form.processing || !active_cycle,
                        children: ($$renderer7) => {
                          $$renderer7.push(`<!---->${escape_html(form.processing ? "Saving…" : "Save Admissions Rules")}`);
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
          $$renderer4.push(`</div>`);
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
  Admissions as default
};
