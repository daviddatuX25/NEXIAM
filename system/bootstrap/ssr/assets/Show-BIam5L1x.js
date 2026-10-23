import { i as spread_props } from "../app.js";
import ApplicantLayout from "./ApplicantLayout-DKnOpLAK.js";
import { O as OfficeInquiryWorkspace } from "./OfficeInquiryWorkspace-DrL3tcHf.js";
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
import "./Link-B7ks7rBK.js";
import "./qr-code-C5X8qcH6.js";
import "./megaphone-C8QUuT5s.js";
import "./file-text-PXEtmXm9.js";
import "./printer-Bx0z1CRb.js";
import "./shield-DTtuUihb.js";
import "./graduation-cap-Bc9amiZ0.js";
import "./user-check-Dl-wciTR.js";
import "./calendar-clock-D72LURiz.js";
import "./input-BUZAZKIK.js";
import "./textarea-Bv3Na1wO.js";
import "./badge-D9Vgs7xh.js";
function Show($$renderer, $$props) {
  $$renderer.component(($$renderer2) => {
    let { $$slots, $$events, ...props } = $$props;
    ApplicantLayout($$renderer2, {
      title: "Office inquiry",
      wide: true,
      breadcrumbs: [
        { label: "Office inquiries", href: props.urls.index },
        { label: "Conversation" }
      ],
      children: ($$renderer3) => {
        OfficeInquiryWorkspace($$renderer3, spread_props([props]));
      }
    });
  });
}
export {
  Show as default
};
