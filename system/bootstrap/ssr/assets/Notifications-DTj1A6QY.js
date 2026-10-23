import { h as head, s as stringify, b as attr, e as escape_html, B as Button, a as ensure_array_like } from "../app.js";
import { S as StaffLayout } from "./StaffLayout-CR-MZopa.js";
import { P as PageHeaderBar } from "./PageHeaderBar-DJhT5vOR.js";
import { u as useForm } from "./useForm.svelte-C0OkDFGu.js";
import { L as Link } from "./Link-B7ks7rBK.js";
import "axios";
import "@inertiajs/core";
import "clsx";
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
import "es-toolkit";
import "./clone-BJz-lxsY.js";
import "es-toolkit/compat";
function Notifications($$renderer, $$props) {
  $$renderer.component(($$renderer2) => {
    let {
      cycle,
      can_manage_admissions = false,
      can_manage_examination = false,
      admission_policy = null,
      exam_policy = null,
      page_state = "ready"
    } = $$props;
    const admissionForm = useForm({
      expected_version: admission_policy?.version ?? 0,
      applicant_status_email_enabled: admission_policy?.applicant_status_email_enabled ?? true,
      registrar_new_submission_alert_enabled: admission_policy?.registrar_new_submission_alert_enabled ?? true
    });
    const examForm = useForm({
      expected_version: exam_policy?.version ?? 0,
      publication_email_enabled: exam_policy?.publication_email_enabled ?? true,
      reminder_offsets_minutes: exam_policy?.reminder_offsets_minutes ?? [4320, 1440, 120],
      reminder_email_enabled: exam_policy?.reminder_email_enabled ?? true
    });
    const reminderOffsets = [
      { minutes: 4320, label: "72 hours before" },
      { minutes: 1440, label: "24 hours before" },
      { minutes: 120, label: "2 hours before" },
      { minutes: 60, label: "1 hour before" }
    ];
    const breadcrumbs = [
      { label: "Setup", href: "/setup" },
      { label: "Academic years", href: "/setup/cycles" },
      { label: cycle.label, href: `/setup/cycles/${cycle.id}/edit` },
      { label: "Communication" }
    ];
    head("1b57y97", $$renderer2, ($$renderer3) => {
      $$renderer3.title(($$renderer4) => {
        $$renderer4.push(`<title>Communication · ${escape_html(cycle.label)}</title>`);
      });
    });
    StaffLayout($$renderer2, {
      breadcrumbs,
      children: ($$renderer3) => {
        $$renderer3.push(`<div class="min-w-0 space-y-6 p-4 sm:p-6">`);
        {
          let actions = function($$renderer4) {
            Link($$renderer4, {
              href: `/setup/cycles/${cycle.id}/edit`,
              children: ($$renderer5) => {
                Button($$renderer5, {
                  type: "button",
                  variant: "outline",
                  class: "min-h-10",
                  children: ($$renderer6) => {
                    $$renderer6.push(`<!---->Back to academic year`);
                  },
                  $$slots: { default: true }
                });
              },
              $$slots: { default: true }
            });
          };
          PageHeaderBar($$renderer3, {
            title: "Communication policy",
            description: `Settings for ${stringify(cycle.label)}. Applicant-facing notices stay in the portal; email is a separate delivery channel.`,
            actions
          });
        }
        $$renderer3.push(`<!----> `);
        if (page_state === "denied") {
          $$renderer3.push("<!--[0-->");
          $$renderer3.push(`<div role="alert" class="rounded-lg border border-destructive/30 bg-destructive/5 p-4 text-sm text-destructive">You do not have permission to manage communication policy for this academic year.</div>`);
        } else {
          $$renderer3.push("<!--[-1-->");
          $$renderer3.push(`<div class="grid grid-cols-1 gap-6 lg:grid-cols-2 lg:items-start max-w-7xl">`);
          if (can_manage_admissions) {
            $$renderer3.push("<!--[0-->");
            $$renderer3.push(`<form class="space-y-5 rounded-xl border border-border bg-card p-5 sm:p-6"><div class="space-y-1"><h2 class="text-base font-semibold">Admissions communication</h2> <p class="text-sm text-muted-foreground">Owned by Registrar staff. These controls do not change admission decisions or access-key handling.</p></div> <label class="flex items-start gap-3 rounded-lg border border-border p-4"><input type="checkbox"${attr("checked", admissionForm.applicant_status_email_enabled, true)} class="mt-1 size-4 accent-primary"/> <span class="space-y-1"><span class="block text-sm font-medium">Email applicants about supported status changes</span> <span class="block text-xs text-muted-foreground">In-app notices remain separate. This setting never emails or stores an access key.</span></span></label> <label class="flex items-start gap-3 rounded-lg border border-border p-4"><input type="checkbox"${attr("checked", admissionForm.registrar_new_submission_alert_enabled, true)} class="mt-1 size-4 accent-primary"/> <span class="space-y-1"><span class="block text-sm font-medium">Show new-submission notices to Registrar reviewers</span> <span class="block text-xs text-muted-foreground">A subtle in-app notice goes only to active, institution-scoped staff with application-view access.</span></span></label> `);
            if (admissionForm.errors.policy || admissionForm.errors.version || admissionForm.errors.expected_version) {
              $$renderer3.push("<!--[0-->");
              $$renderer3.push(`<p role="alert" class="text-sm text-destructive">${escape_html(admissionForm.errors.policy ?? admissionForm.errors.version ?? admissionForm.errors.expected_version)}</p>`);
            } else {
              $$renderer3.push("<!--[-1-->");
            }
            $$renderer3.push(`<!--]--> `);
            if (admissionForm.errors.applicant_status_email_enabled) {
              $$renderer3.push("<!--[0-->");
              $$renderer3.push(`<p role="alert" class="text-sm text-destructive">${escape_html(admissionForm.errors.applicant_status_email_enabled)}</p>`);
            } else {
              $$renderer3.push("<!--[-1-->");
            }
            $$renderer3.push(`<!--]--> `);
            if (admissionForm.errors.registrar_new_submission_alert_enabled) {
              $$renderer3.push("<!--[0-->");
              $$renderer3.push(`<p role="alert" class="text-sm text-destructive">${escape_html(admissionForm.errors.registrar_new_submission_alert_enabled)}</p>`);
            } else {
              $$renderer3.push("<!--[-1-->");
            }
            $$renderer3.push(`<!--]--> `);
            Button($$renderer3, {
              type: "submit",
              class: "min-h-10",
              disabled: admissionForm.processing,
              children: ($$renderer4) => {
                $$renderer4.push(`<!---->${escape_html(admissionForm.processing ? "Saving…" : "Save Admissions policy")}`);
              },
              $$slots: { default: true }
            });
            $$renderer3.push(`<!----></form>`);
          } else {
            $$renderer3.push("<!--[-1-->");
          }
          $$renderer3.push(`<!--]--> `);
          if (can_manage_examination) {
            $$renderer3.push("<!--[0-->");
            $$renderer3.push(`<form class="space-y-5 rounded-xl border border-border bg-card p-5 sm:p-6"><div class="space-y-1"><h2 class="text-base font-semibold">Exam communication</h2> <p class="text-sm text-muted-foreground">Owned by Guidance/Test Administration. Each draft session can inherit these reminder defaults or override them.</p></div> <label class="flex items-start gap-3 rounded-lg border border-border p-4"><input type="checkbox"${attr("checked", examForm.publication_email_enabled, true)} class="mt-1 size-4 accent-primary"/> <span class="space-y-1"><span class="block text-sm font-medium">Email assigned applicants when a schedule is published</span> <span class="block text-xs text-muted-foreground">The in-app schedule notice remains independent and cannot be disabled here.</span></span></label> <fieldset class="space-y-3 rounded-lg border border-border p-4"><legend class="px-1 text-sm font-medium">Reminder timing defaults</legend> <div class="grid gap-3 sm:grid-cols-2"><!--[-->`);
            const each_array = ensure_array_like(reminderOffsets);
            for (let $$index = 0, $$length = each_array.length; $$index < $$length; $$index++) {
              let reminder = each_array[$$index];
              $$renderer3.push(`<label class="flex items-center gap-3 rounded-md border border-border/70 px-3 py-2.5 text-sm"><input type="checkbox"${attr("checked", examForm.reminder_offsets_minutes.includes(reminder.minutes), true)} class="size-4 accent-primary"/> ${escape_html(reminder.label)}</label>`);
            }
            $$renderer3.push(`<!--]--></div> `);
            if (examForm.errors.policy || examForm.errors.reminder_offsets_minutes || examForm.errors["reminder_offsets_minutes.0"]) {
              $$renderer3.push("<!--[0-->");
              $$renderer3.push(`<p role="alert" class="text-sm text-destructive">${escape_html(examForm.errors.policy ?? examForm.errors.reminder_offsets_minutes ?? examForm.errors["reminder_offsets_minutes.0"])}</p>`);
            } else {
              $$renderer3.push("<!--[-1-->");
            }
            $$renderer3.push(`<!--]--></fieldset> <label class="flex items-start gap-3 rounded-lg border border-border p-4"><input type="checkbox"${attr("checked", examForm.reminder_email_enabled, true)} class="mt-1 size-4 accent-primary"/> <span class="space-y-1"><span class="block text-sm font-medium">Email scheduled reminders</span> <span class="block text-xs text-muted-foreground">Applicants still receive an in-app reminder for each enabled timing window.</span></span></label> `);
            if (examForm.errors.version || examForm.errors.expected_version) {
              $$renderer3.push("<!--[0-->");
              $$renderer3.push(`<p role="alert" class="text-sm text-destructive">${escape_html(examForm.errors.version ?? examForm.errors.expected_version)}</p>`);
            } else {
              $$renderer3.push("<!--[-1-->");
            }
            $$renderer3.push(`<!--]--> `);
            if (examForm.errors.publication_email_enabled) {
              $$renderer3.push("<!--[0-->");
              $$renderer3.push(`<p role="alert" class="text-sm text-destructive">${escape_html(examForm.errors.publication_email_enabled)}</p>`);
            } else {
              $$renderer3.push("<!--[-1-->");
            }
            $$renderer3.push(`<!--]--> `);
            if (examForm.errors.reminder_email_enabled) {
              $$renderer3.push("<!--[0-->");
              $$renderer3.push(`<p role="alert" class="text-sm text-destructive">${escape_html(examForm.errors.reminder_email_enabled)}</p>`);
            } else {
              $$renderer3.push("<!--[-1-->");
            }
            $$renderer3.push(`<!--]--> `);
            Button($$renderer3, {
              type: "submit",
              class: "min-h-10",
              disabled: examForm.processing,
              children: ($$renderer4) => {
                $$renderer4.push(`<!---->${escape_html(examForm.processing ? "Saving…" : "Save Exam policy")}`);
              },
              $$slots: { default: true }
            });
            $$renderer3.push(`<!----></form>`);
          } else {
            $$renderer3.push("<!--[-1-->");
          }
          $$renderer3.push(`<!--]--></div>`);
        }
        $$renderer3.push(`<!--]--></div>`);
      }
    });
  });
}
export {
  Notifications as default
};
