import { h as head, e as escape_html } from "../app.js";
import { S as StaffLayout } from "./StaffLayout-CR-MZopa.js";
import { P as PageHeaderBar } from "./PageHeaderBar-DJhT5vOR.js";
import { N as NoticeFamilyList } from "./NoticeFamilyList-CQLzNv8n.js";
import { B as Badge } from "./badge-D9Vgs7xh.js";
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
function Results($$renderer, $$props) {
  $$renderer.component(($$renderer2) => {
    let {
      active_cycle = null,
      notices = [],
      capabilities = { can_manage: false }
    } = $$props;
    const breadcrumbs = [
      { label: "Setup", href: "/setup" },
      { label: "Email & Notifications", href: "/setup/notifications" },
      { label: "Results Desk" }
    ];
    head("ufti3a", $$renderer2, ($$renderer3) => {
      $$renderer3.title(($$renderer4) => {
        $$renderer4.push(`<title>Results &amp; Consultation Communications · Setup</title>`);
      });
    });
    StaffLayout($$renderer2, {
      breadcrumbs,
      children: ($$renderer3) => {
        $$renderer3.push(`<div class="min-w-0 space-y-7 p-4 sm:p-6">`);
        PageHeaderBar($$renderer3, {
          title: "Results & Consultation Communications",
          description: "Canonical notices governed by official release and consultation workflows."
        });
        $$renderer3.push(`<!----> `);
        NoticeFamilyList($$renderer3, { notices, domain: "results", canEdit: capabilities.can_manage });
        $$renderer3.push(`<!----> <section class="space-y-3 rounded-xl border border-border bg-card p-4 sm:p-5" aria-labelledby="release-governance"><div class="flex flex-wrap items-center justify-between gap-3"><h2 id="release-governance" class="text-lg font-semibold">Release governance</h2>`);
        Badge($$renderer3, {
          variant: "outline",
          children: ($$renderer4) => {
            $$renderer4.push(`<!---->${escape_html(capabilities.can_manage ? "Release-authorized authoring" : "Metadata-only access")}`);
          },
          $$slots: { default: true }
        });
        $$renderer3.push(`<!----></div> <p class="max-w-3xl text-sm leading-6 text-muted-foreground">Notices do not publish, amend or author examination scores. Delivery channels remain owned by the official release and consultation workflows; this domain has no standalone policy form.</p> <p class="max-w-3xl text-sm leading-6 text-muted-foreground">Only the explicitly eligible online release family may configure an official PDF. Its result data must come from canonical released-result authority, not a notice-written score. Face-to-face and unlisted families remain document-free.</p> <p class="text-sm text-muted-foreground">Active academic year: ${escape_html(active_cycle?.label ?? active_cycle?.name ?? "None")}</p></section></div>`);
      }
    });
  });
}
export {
  Results as default
};
