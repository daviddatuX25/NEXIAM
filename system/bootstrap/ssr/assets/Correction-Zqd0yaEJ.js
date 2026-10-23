import { h as head, d as derived, e as escape_html, B as Button } from "../app.js";
import ApplicantLayout from "./ApplicantLayout-DKnOpLAK.js";
import IntakeCorrectionFields from "./IntakeCorrectionFields-BT17XBCB.js";
import { u as useForm } from "./useForm.svelte-C0OkDFGu.js";
import { L as Link } from "./Link-B7ks7rBK.js";
import { A as Arrow_left } from "./arrow-left-DbWnQR3t.js";
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
import "./NotificationBell-Cgc59min.js";
import "./inbox-AmUVtgA2.js";
import "./mail-DBtkjaCt.js";
import "./XiamNpcDialog-DoqCSHj9.js";
/* empty css                                                        */
import "./circle-question-mark-DY-RU614.js";
import "./sparkles-DbQIaVG6.js";
import "./chevron-left-DCSOZwZl.js";
import "./chevron-right-BHu-PGOg.js";
import "./dialog-description-D12L0QA9.js";
import "./dialog-footer-kNnwKT2h.js";
import "qrcode";
import "./qr-code-C5X8qcH6.js";
import "./megaphone-C8QUuT5s.js";
import "./file-text-PXEtmXm9.js";
import "./printer-Bx0z1CRb.js";
import "./shield-DTtuUihb.js";
import "./graduation-cap-Bc9amiZ0.js";
import "./user-check-Dl-wciTR.js";
import "./calendar-clock-D72LURiz.js";
import "./input-BUZAZKIK.js";
import "./label-dGY6L9vm.js";
import "./textarea-Bv3Na1wO.js";
import "./PhilippineAddressSelector-UGmHumOi.js";
import "es-toolkit";
import "./clone-BJz-lxsY.js";
import "es-toolkit/compat";
function Correction($$renderer, $$props) {
  $$renderer.component(($$renderer2) => {
    let {
      page_state = "ready",
      mode = "resubmit",
      attempt,
      programs = [],
      grade_fields = []
    } = $$props;
    const isAmend = derived(() => mode === "amend");
    const initialGrades = Object.fromEntries((grade_fields ?? []).map((f) => [
      f.code,
      attempt.grades?.find((g) => g.code === f.code)?.value ?? ""
    ]));
    const form = useForm({
      expected_version: attempt.version,
      applicant_type: attempt.applicant_type ?? "new",
      contact_email: attempt.contact_email ?? "",
      contact_phone: attempt.contact_phone ?? "",
      program_preference_1_code: attempt.program_preference_1_code ?? "",
      program_preference_2_code: attempt.program_preference_2_code ?? "",
      program_preference_3_code: attempt.program_preference_3_code ?? "",
      address_line: attempt.address_line ?? "",
      city: attempt.city ?? "",
      province: attempt.province ?? "",
      zip_code: attempt.zip_code ?? "",
      last_school_enrolled: attempt.last_school_enrolled ?? "",
      strand: attempt.strand ?? "",
      grades: initialGrades,
      resubmission_notes: ""
    });
    head("b1dpk4", $$renderer2, ($$renderer3) => {
      $$renderer3.title(($$renderer4) => {
        $$renderer4.push(`<title>${escape_html(isAmend() ? "Update details" : "Correct application")} · ${escape_html(attempt.reference_code)}</title>`);
      });
    });
    ApplicantLayout($$renderer2, {
      title: isAmend() ? "Update details" : "Correct application",
      children: ($$renderer3) => {
        $$renderer3.push(`<div class="mx-auto max-w-2xl space-y-6"><div class="space-y-2">`);
        Link($$renderer3, {
          href: "/applicant/schedule",
          class: "inline-flex items-center gap-1 text-sm text-muted-foreground hover:text-foreground",
          children: ($$renderer4) => {
            Arrow_left($$renderer4, { class: "size-4" });
            $$renderer4.push(`<!----> Back to portal`);
          },
          $$slots: { default: true }
        });
        $$renderer3.push(`<!----> <h1 class="text-2xl font-semibold tracking-tight">${escape_html(isAmend() ? "Update my details" : "Respond to correction request")}</h1> <p class="text-sm text-muted-foreground">Reference <span class="font-mono">${escape_html(attempt.reference_code)}</span>. `);
        if (isAmend()) {
          $$renderer3.push("<!--[0-->");
          $$renderer3.push(`Fix a typo or contact detail while your application is still pending. This stays in review
          — no loud status change.`);
        } else {
          $$renderer3.push("<!--[-1-->");
          $$renderer3.push(`Update the fields the registrar asked for, then resubmit for review.`);
        }
        $$renderer3.push(`<!--]--></p></div> `);
        if (!isAmend() && attempt.correction_notes) {
          $$renderer3.push("<!--[0-->");
          $$renderer3.push(`<p class="rounded-lg border border-border bg-muted/30 px-3 py-2 text-sm">Registrar request: ${escape_html(attempt.correction_notes)}</p>`);
        } else {
          $$renderer3.push("<!--[-1-->");
        }
        $$renderer3.push(`<!--]--> <form class="space-y-6">`);
        IntakeCorrectionFields($$renderer3, {
          form,
          programs,
          grade_fields,
          identity: attempt.identity,
          showResubmissionNotes: !isAmend(),
          idPrefix: "correct"
        });
        $$renderer3.push(`<!----> `);
        if (form.errors.version) {
          $$renderer3.push("<!--[0-->");
          $$renderer3.push(`<p class="text-sm text-destructive">${escape_html(form.errors.version)}</p>`);
        } else {
          $$renderer3.push("<!--[-1-->");
        }
        $$renderer3.push(`<!--]--> <div class="flex flex-wrap gap-2">`);
        Button($$renderer3, {
          type: "submit",
          class: "min-h-11",
          disabled: form.processing,
          children: ($$renderer4) => {
            $$renderer4.push(`<!---->${escape_html(isAmend() ? "Save details" : "Resubmit for review")}`);
          },
          $$slots: { default: true }
        });
        $$renderer3.push(`<!----> `);
        Link($$renderer3, {
          href: "/applicant/schedule",
          children: ($$renderer4) => {
            Button($$renderer4, {
              type: "button",
              variant: "outline",
              class: "min-h-11",
              children: ($$renderer5) => {
                $$renderer5.push(`<!---->Cancel`);
              },
              $$slots: { default: true }
            });
          },
          $$slots: { default: true }
        });
        $$renderer3.push(`<!----></div></form></div>`);
      }
    });
  });
}
export {
  Correction as default
};
