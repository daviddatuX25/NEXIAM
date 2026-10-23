import { h as head, e as escape_html, B as Button } from "../app.js";
import { S as StaffLayout } from "./StaffLayout-CR-MZopa.js";
import { P as PageHeaderBar } from "./PageHeaderBar-DJhT5vOR.js";
import { I as Input } from "./input-BUZAZKIK.js";
import { L as Label } from "./label-dGY6L9vm.js";
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
function Create($$renderer, $$props) {
  $$renderer.component(($$renderer2) => {
    const form = useForm({
      school_year: "",
      semester: "1",
      application_start_date: "",
      application_end_date: ""
    });
    const breadcrumbs = [
      { label: "Setup", href: "/setup" },
      { label: "Academic Years", href: "/setup/cycles" },
      { label: "Create" }
    ];
    let $$settled = true;
    let $$inner_renderer;
    function $$render_inner($$renderer3) {
      head("1qxl4zz", $$renderer3, ($$renderer4) => {
        $$renderer4.title(($$renderer5) => {
          $$renderer5.push(`<title>Add Academic Year · Setup</title>`);
        });
      });
      StaffLayout($$renderer3, {
        breadcrumbs,
        children: ($$renderer4) => {
          $$renderer4.push(`<div class="min-w-0 space-y-6 p-4 sm:p-6">`);
          PageHeaderBar($$renderer4, {
            title: "Add Academic Year",
            description: "Configure a school year, semester, and application intake window dates, then activate it from the cycle list."
          });
          $$renderer4.push(`<!----> <form class="space-y-6"><div class="grid gap-6 lg:grid-cols-[minmax(0,1.2fr)_minmax(18rem,0.8fr)] max-w-6xl items-start"><div class="space-y-4 rounded-xl border border-border bg-card p-6 shadow-xs"><div class="space-y-1.5">`);
          Label($$renderer4, {
            for: "school_year",
            children: ($$renderer5) => {
              $$renderer5.push(`<!---->Academic year`);
            },
            $$slots: { default: true }
          });
          $$renderer4.push(`<!----> `);
          Input($$renderer4, {
            id: "school_year",
            placeholder: "e.g. 2025-2026",
            class: "min-h-10",
            maxlength: 20,
            required: true,
            get value() {
              return form.school_year;
            },
            set value($$value) {
              form.school_year = $$value;
              $$settled = false;
            }
          });
          $$renderer4.push(`<!----> `);
          if (form.errors.school_year) {
            $$renderer4.push("<!--[0-->");
            $$renderer4.push(`<p class="text-sm text-destructive">${escape_html(form.errors.school_year)}</p>`);
          } else {
            $$renderer4.push("<!--[-1-->");
          }
          $$renderer4.push(`<!--]--></div> <div class="space-y-1.5">`);
          Label($$renderer4, {
            for: "semester",
            children: ($$renderer5) => {
              $$renderer5.push(`<!---->Semester`);
            },
            $$slots: { default: true }
          });
          $$renderer4.push(`<!----> `);
          $$renderer4.select(
            {
              id: "semester",
              value: form.semester,
              class: "border-input bg-background flex min-h-10 w-full rounded-md border px-3 text-sm",
              required: true
            },
            ($$renderer5) => {
              $$renderer5.option({ value: "1" }, ($$renderer6) => {
                $$renderer6.push(`1st Semester`);
              });
              $$renderer5.option({ value: "2" }, ($$renderer6) => {
                $$renderer6.push(`2nd Semester`);
              });
            }
          );
          $$renderer4.push(`</div> <div class="space-y-1.5">`);
          Label($$renderer4, {
            for: "application_start_date",
            children: ($$renderer5) => {
              $$renderer5.push(`<!---->Application window start`);
            },
            $$slots: { default: true }
          });
          $$renderer4.push(`<!----> `);
          Input($$renderer4, {
            id: "application_start_date",
            type: "date",
            class: "min-h-10",
            get value() {
              return form.application_start_date;
            },
            set value($$value) {
              form.application_start_date = $$value;
              $$settled = false;
            }
          });
          $$renderer4.push(`<!----></div> <div class="space-y-1.5">`);
          Label($$renderer4, {
            for: "application_end_date",
            children: ($$renderer5) => {
              $$renderer5.push(`<!---->Application window end`);
            },
            $$slots: { default: true }
          });
          $$renderer4.push(`<!----> `);
          Input($$renderer4, {
            id: "application_end_date",
            type: "date",
            class: "min-h-10",
            get value() {
              return form.application_end_date;
            },
            set value($$value) {
              form.application_end_date = $$value;
              $$settled = false;
            }
          });
          $$renderer4.push(`<!----></div> <div class="flex gap-2 pt-2">`);
          Button($$renderer4, {
            type: "submit",
            class: "min-h-10",
            disabled: form.processing,
            children: ($$renderer5) => {
              $$renderer5.push(`<!---->${escape_html(form.processing ? "Creating..." : "Create academic year")}`);
            },
            $$slots: { default: true }
          });
          $$renderer4.push(`<!----> `);
          Link($$renderer4, {
            href: "/setup/cycles",
            children: ($$renderer5) => {
              Button($$renderer5, {
                type: "button",
                variant: "outline",
                class: "min-h-10",
                children: ($$renderer6) => {
                  $$renderer6.push(`<!---->Cancel`);
                },
                $$slots: { default: true }
              });
            },
            $$slots: { default: true }
          });
          $$renderer4.push(`<!----></div></div> <div class="space-y-5"><div class="rounded-xl border border-border bg-card p-6 shadow-xs space-y-4"><div class="space-y-1 border-b border-border pb-3"><h2 class="text-sm font-semibold text-foreground">Cycle Lifecycle Overview</h2> <p class="text-xs text-muted-foreground">Institutional admission context</p></div> <p class="text-xs text-muted-foreground leading-relaxed">Creating an academic year establishes the temporal boundary for applicant admissions, exam session scheduling, and final enrollment endorsements.</p> <div class="rounded-lg border border-dashed border-border bg-muted/20 p-3.5 text-xs text-muted-foreground leading-relaxed">After creation, new cycles remain inactive drafts until explicitly activated from the Academic Years hub. Only one cycle is active across the institution at any given time.</div></div></div></div></form></div>`);
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
  Create as default
};
