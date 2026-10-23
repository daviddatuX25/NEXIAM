import { h as head, e as escape_html, n as newIdempotencyKey } from "../app.js";
import { S as StaffLayout } from "./StaffLayout-CR-MZopa.js";
/* empty css                                                        */
import OmrTemplateStudio from "./OmrTemplateStudio-BBkx-Myi.js";
import BlankInstanceHistoryPanel from "./BlankInstanceHistoryPanel-bm926Pvr.js";
import BlankInstancePrintPanel from "./BlankInstancePrintPanel-BWqLawsb.js";
import { router } from "@inertiajs/core";
import "axios";
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
import "./omrStudioGeometry-DyCkNaaw.js";
import "./omrComponents-D81Zk9FJ.js";
import "./StudioCanvas-BPLCVpSp.js";
import "./minus-Dqkhcxt7.js";
import "./plus-DjyPLC7A.js";
import "./lock-DAA4kTLl.js";
import "./loader-circle-_UEl10Lx.js";
import "./StudioOverlayPanel-DQo4NXMg.js";
import "./StudioTip-evy-6JcG.js";
import "./StudioFontPicker-5wWjSEPZ.js";
import "./StudioQrReservationControls-C1jYP4Jw.js";
import "./lock-open-5Nny5RV8.js";
import "./StudioFiducialInsetControls-CdTiPYqb.js";
import "./text-align-start-Cy9u9QqD.js";
import "./trash-2-CjFp3otc.js";
import "./chevron-left-DCSOZwZl.js";
import "./StudioBubblePanel-dUcvtVr8.js";
import "./chevron-up-lhASJLRD.js";
import "./scan-DTyWR4bt.js";
import "./StudioExportVerifyPanel-CZarLenW.js";
import "./StudioShadingGuideModal-BfmTaRFq.js";
import "./sparkles-DbQIaVG6.js";
import "./check-DK45Y9SJ.js";
import "./shield-alert-CXtDdte9.js";
import "./file-image-BGlN21-8.js";
import "./arrow-left-DbWnQR3t.js";
import "./OmrPhysicalTestMethodCard-hibzJL3T.js";
import "./OmrCaptureInput-CmVb1QoH.js";
/* empty css                                                       */
import "./upload-CwqN2Utd.js";
import "./file-text-PXEtmXm9.js";
import "./eye-DyG-RiwW.js";
import "./OmrDiagnosticEvidencePanel-CvO7kqrK.js";
import "./maximize-2-u72Ahym6.js";
import "./eye-off-Bx_raPQb.js";
import "./save-DAlMQk-Q.js";
import "./scan-line-CiwhUtYK.js";
import "./layers-_Ie6y7G_.js";
import "./StudioTypographyControls-D2aqSkxQ.js";
import "./text-align-justify-CFymVZbq.js";
import "./StudioComponentAppearanceControls-CMSXg1au.js";
import "./sliders-vertical-DiYN4y-t.js";
import "./undo-2-CzBOZ6l_.js";
import "./download-n32NlL8c.js";
import "./clone-BJz-lxsY.js";
function CanonicalWorkflow($$renderer, $$props) {
  $$renderer.component(($$renderer2) => {
    let {
      draft = {},
      marker_profiles = [],
      aptitude_areas = [],
      test_instrument = null,
      stages = [],
      urls = {},
      verification_run = null,
      physical_run_id = null
    } = $$props;
    const isUploadedSource = ["pdf", "image", "docx"].includes(draft.source_kind);
    const domainView = {
      draft: draft.source_kind === "preset" ? "map" : "draft",
      source_confirmed: "convert_inspect",
      page_prepared: "map",
      mapping_complete: "map",
      digital_verified: "compile",
      compiled: draft.compiled_version_status === "active" && !isUploadedSource ? "blank_print" : "physical_activate"
    };
    const breadcrumbs = [
      { label: "Setup", href: "/setup" },
      { label: "Exam materials", href: "/setup/exam-materials" },
      { label: "Answer Sheet Studio" }
    ];
    let activeStage = domainView[draft.stage] ?? "draft";
    let busy = false;
    draft.source_kind ?? "image";
    draft.authoring?.marker?.profile_id ?? marker_profiles[0]?.id ?? null;
    draft.lock_version ?? 0;
    draft.authoring?.mapping?.input ?? {};
    draft.authoring?.overlay ?? {};
    let quantity = 1;
    let copyPolicy = "unique_instance";
    draft.authoring?.source_run ?? null;
    function setResult(nextDraft, text = "Saved") {
      busy = false;
    }
    function fail(formError) {
      busy = false;
      formError?.message ?? "The operation failed. No staged data was discarded.";
    }
    function printBlankInstances() {
      if (!draft.compiled_version_id) return;
      busy = true;
      router.post(
        `/setup/omr/templates/${draft.compiled_version_id}/blank/print`,
        {
          quantity: Number(quantity),
          copy_policy: copyPolicy,
          idempotency_key: newIdempotencyKey()
        },
        {
          preserveScroll: true,
          onSuccess: () => setResult(null, "Blank print job queued in deterministic chunks."),
          onError: fail,
          onFinish: () => busy = false
        }
      );
    }
    head("1c4z7f4", $$renderer2, ($$renderer3) => {
      $$renderer3.title(($$renderer4) => {
        $$renderer4.push(`<title>${escape_html(draft.name)} · Answer Sheet Studio</title>`);
      });
    });
    StaffLayout($$renderer2, {
      breadcrumbs,
      children: ($$renderer3) => {
        $$renderer3.push(`<div class="flex h-[calc(100vh-3.5rem)] w-full flex-col overflow-hidden">`);
        if (activeStage === "blank_print") {
          $$renderer3.push("<!--[0-->");
          $$renderer3.push(`<div class="mx-auto w-full max-w-5xl space-y-6 overflow-y-auto p-6">`);
          BlankInstancePrintPanel($$renderer3, {
            quantity,
            qrMode: draft.qr_mode,
            copyPolicy,
            busy,
            disabled: !draft.can_edit || draft.compiled_version_status !== "active",
            onquantity: (value) => quantity = value,
            oncopyPolicy: (value) => copyPolicy = value,
            onprint: printBlankInstances
          });
          $$renderer3.push(`<!----> `);
          BlankInstanceHistoryPanel($$renderer3, { templateVersionId: draft.compiled_version_id });
          $$renderer3.push(`<!----></div>`);
        } else {
          $$renderer3.push("<!--[-1-->");
          OmrTemplateStudio($$renderer3, {
            draft,
            marker_profiles,
            aptitude_areas,
            test_instrument,
            urls,
            verification_run,
            physical_run_id,
            onMappingSaved: () => {
            }
          });
        }
        $$renderer3.push(`<!--]--></div>`);
      }
    });
  });
}
export {
  CanonicalWorkflow as default
};
