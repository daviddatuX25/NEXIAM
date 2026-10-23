import { h as head, e as escape_html } from "../app.js";
import { S as StaffLayout } from "./StaffLayout-CR-MZopa.js";
import { P as PageHeaderBar } from "./PageHeaderBar-DJhT5vOR.js";
import SeatingArrangementForm from "./SeatingArrangementForm-BLmmZFaK.js";
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
import "./label-dGY6L9vm.js";
import "./SeatingArrangementPreview-BlLfpBBH.js";
import "./useForm.svelte-C0OkDFGu.js";
import "es-toolkit";
import "./clone-BJz-lxsY.js";
import "es-toolkit/compat";
function Edit($$renderer, $$props) {
  $$renderer.component(($$renderer2) => {
    let {
      arrangement,
      rooms = [],
      patterns = [],
      availability_modes = [],
      preview = [],
      referenced_facts = null,
      published_facts = null,
      conflict = null
    } = $$props;
    const breadcrumbs = [
      { label: "Setup", href: "/setup" },
      {
        label: "Seating Arrangements",
        href: "/setup/seating-arrangements"
      },
      {
        label: arrangement?.name ?? "Arrangement",
        href: `/setup/seating-arrangements/${arrangement?.id}/edit`
      },
      { label: "Edit" }
    ];
    head("5sbp4e", $$renderer2, ($$renderer3) => {
      $$renderer3.title(($$renderer4) => {
        $$renderer4.push(`<title>Edit ${escape_html(arrangement?.name ?? "Seating Arrangement")} · Setup</title>`);
      });
    });
    StaffLayout($$renderer2, {
      breadcrumbs,
      children: ($$renderer3) => {
        $$renderer3.push(`<div class="min-w-0 space-y-6 p-4 sm:p-6">`);
        PageHeaderBar($$renderer3, {
          title: "Edit Seating Arrangement",
          description: "Modify desk patterns and room assignments for future exam sessions without altering published manifests."
        });
        $$renderer3.push(`<!----> `);
        SeatingArrangementForm($$renderer3, {
          arrangement,
          rooms,
          patterns,
          availability_modes,
          referenced_facts,
          published_facts,
          conflict
        });
        $$renderer3.push(`<!----></div>`);
      }
    });
  });
}
export {
  Edit as default
};
