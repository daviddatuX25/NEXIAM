import { h as head, B as Button } from "../app.js";
import { S as StaffLayout } from "./StaffLayout-CR-MZopa.js";
import { P as PageHeaderBar } from "./PageHeaderBar-DJhT5vOR.js";
import NormPackageEditor from "./NormPackageEditor-D6Rh_Mj5.js";
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
import "./label-dGY6L9vm.js";
import "./useForm.svelte-C0OkDFGu.js";
import "es-toolkit";
import "./clone-BJz-lxsY.js";
import "es-toolkit/compat";
import "./plus-DjyPLC7A.js";
import "./trash-2-CjFp3otc.js";
function Create($$renderer, $$props) {
  let { request_id } = $$props;
  const breadcrumbs = [
    { label: "Setup", href: "/setup" },
    { label: "Norm packages", href: "/setup/norm-packages" },
    { label: "Create draft" }
  ];
  head("14s9wnb", $$renderer, ($$renderer2) => {
    $$renderer2.title(($$renderer3) => {
      $$renderer3.push(`<title>Create norm package · Setup</title>`);
    });
  });
  StaffLayout($$renderer, {
    breadcrumbs,
    children: ($$renderer2) => {
      $$renderer2.push(`<div class="min-w-0 space-y-6 p-4 sm:p-6">`);
      {
        let actions = function($$renderer3) {
          Link($$renderer3, {
            href: "/setup/norm-packages",
            children: ($$renderer4) => {
              Button($$renderer4, {
                variant: "outline",
                class: "min-h-10",
                children: ($$renderer5) => {
                  $$renderer5.push(`<!---->Back to norm packages`);
                },
                $$slots: { default: true }
              });
            },
            $$slots: { default: true }
          });
        };
        PageHeaderBar($$renderer2, {
          title: "Create norm package draft",
          description: "Record package identity, source references, applicable population, and versioned conversion tables. Saving creates an unapproved draft.",
          actions
        });
      }
      $$renderer2.push(`<!----> `);
      NormPackageEditor($$renderer2, { mode: "create", request_id });
      $$renderer2.push(`<!----></div>`);
    }
  });
}
export {
  Create as default
};
