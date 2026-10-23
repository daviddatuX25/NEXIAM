import { h as head } from "../app.js";
import { S as StaffLayout } from "./StaffLayout-CR-MZopa.js";
import { R as ResultSheetTemplateEditor } from "./ResultSheetTemplateEditor-CwI_FPY3.js";
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
import "./label-dGY6L9vm.js";
import "./PlaceholderPicker-CH_aVvK3.js";
import "./arrays-CkOR4vx_.js";
import "./html-BMEe54ZQ.js";
import "./text-align-justify-CFymVZbq.js";
import "./text-align-start-Cy9u9QqD.js";
import "./minus-Dqkhcxt7.js";
import "./plus-DjyPLC7A.js";
import "./maximize-2-u72Ahym6.js";
import "./circle-question-mark-DY-RU614.js";
import "./chevron-left-DCSOZwZl.js";
import "./layers-_Ie6y7G_.js";
import "./lock-DAA4kTLl.js";
import "./trash-2-CjFp3otc.js";
import "./lock-open-5Nny5RV8.js";
import "./copy-D9_YDhto.js";
import "./file-text-PXEtmXm9.js";
import "./sticky-note-CKw7DId8.js";
import "./pen-line-Bqgfmrgp.js";
import "./sparkles-DbQIaVG6.js";
import "./table-2-BLgeaPqt.js";
import "./calendar-clock-D72LURiz.js";
import "./clipboard-BXuTlHdz.js";
import "./check-DK45Y9SJ.js";
import "./clock-3-CBFB3kOE.js";
import "./useForm.svelte-C0OkDFGu.js";
import "es-toolkit";
import "./clone-BJz-lxsY.js";
import "es-toolkit/compat";
import "./save-DAlMQk-Q.js";
import "./shield-alert-CXtDdte9.js";
import "./play-DnSz4pBE.js";
function Create($$renderer, $$props) {
  let {
    draft = null,
    placeholderGroups = {},
    domainPlaceholders = [],
    builderCatalog = {},
    builderCapabilities = {},
    builderDefaults = {},
    sampleContext = {},
    builderCompilerVersion = "result-sheet-builder-1"
  } = $$props;
  const breadcrumbs = [
    { label: "Setup", href: "/setup" },
    {
      label: "Result Sheet Templates",
      href: "/setup/result-templates"
    },
    { label: "Create" }
  ];
  head("e4vh7f", $$renderer, ($$renderer2) => {
    $$renderer2.title(($$renderer3) => {
      $$renderer3.push(`<title>New Result Sheet Template · Setup</title>`);
    });
  });
  StaffLayout($$renderer, {
    breadcrumbs,
    children: ($$renderer2) => {
      $$renderer2.push(`<div class="min-w-0 p-4 sm:p-6">`);
      ResultSheetTemplateEditor($$renderer2, {
        mode: "create",
        placeholderGroups,
        builderCatalog,
        builderCapabilities,
        builderDefaults,
        builderCompilerVersion,
        sampleContext,
        draft
      });
      $$renderer2.push(`<!----></div>`);
    }
  });
}
export {
  Create as default
};
