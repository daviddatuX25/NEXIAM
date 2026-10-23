import { o as onDestroy, h as head, e as escape_html, B as Button, f as attr_style, b as attr } from "../app.js";
import { S as StaffLayout } from "./StaffLayout-CR-MZopa.js";
import { S as Square_check_big, R as Rotate_cw, M as MarkPrintedDialog } from "./MarkPrintedDialog-BNiMAKov.js";
import "clsx";
import "./badge-D9Vgs7xh.js";
import { P as Printer } from "./printer-Bx0z1CRb.js";
import { h as html } from "./html-BMEe54ZQ.js";
import { router } from "@inertiajs/core";
import "axios";
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
import "./book-open-B6eMguqY.js";
import "./megaphone-C8QUuT5s.js";
import "./file-check-BSLEMs1I.js";
import "./shield-DTtuUihb.js";
import "./clipboard-list-YgthURdB.js";
import "./users-DWlguTa_.js";
import "./user-round-Kft0Cy_Y.js";
function ResultSheet($$renderer, $$props) {
  $$renderer.component(($$renderer2) => {
    let {
      applicant = {},
      sheetHtml = null,
      pdf_base64 = null,
      pdf_url = null,
      preview_job_id = null,
      paper_size = "a4",
      template_error = null
    } = $$props;
    const paperWidth = { a4: 210, legal: 216, letter: 216 }[paper_size] ?? 210;
    let pdfSrc = pdf_base64 ? `data:application/pdf;base64,${pdf_base64}#toolbar=0` : pdf_url ?? "";
    let previewStatus = preview_job_id ? "queued" : pdfSrc ? "ready" : "none";
    let showMarkDialog = false;
    const breadcrumbs = [
      { label: "Release", href: "/release" },
      { label: "Result sheet" },
      { label: applicant.name ?? "Applicant" }
    ];
    onDestroy(() => {
    });
    function handlePrint() {
      if (pdfSrc) window.open(pdfSrc, "_blank", "noopener");
      else window.print();
      setTimeout(() => showMarkDialog = true, 500);
    }
    head("w52pdv", $$renderer2, ($$renderer3) => {
      $$renderer3.title(($$renderer4) => {
        $$renderer4.push(`<title>Result sheet · ${escape_html(applicant.name ?? "Release")}</title>`);
      });
    });
    StaffLayout($$renderer2, {
      breadcrumbs,
      children: ($$renderer3) => {
        $$renderer3.push(`<div class="min-w-0 space-y-4 p-4 sm:p-6"><div class="no-print flex flex-wrap items-center justify-between gap-3 rounded-xl border border-border bg-card p-4 shadow-xs svelte-w52pdv"><div><h2 class="text-sm font-semibold text-foreground">${escape_html(applicant.name ?? "")}</h2> <p class="font-mono text-xs text-muted-foreground">${escape_html(applicant.reference ?? "")}</p></div> <div class="flex items-center gap-2">`);
        Button($$renderer3, {
          variant: "outline",
          size: "sm",
          class: "min-h-10 gap-2",
          onclick: () => showMarkDialog = true,
          children: ($$renderer4) => {
            Square_check_big($$renderer4, { class: "size-4 text-muted-foreground" });
            $$renderer4.push(`<!---->Mark as printed…`);
          },
          $$slots: { default: true }
        });
        $$renderer3.push(`<!----> `);
        Button($$renderer3, {
          size: "sm",
          class: "min-h-10 gap-2 font-semibold",
          onclick: handlePrint,
          disabled: previewStatus === "queued",
          children: ($$renderer4) => {
            if (previewStatus === "queued") {
              $$renderer4.push("<!--[0-->");
              Rotate_cw($$renderer4, { class: "size-4 animate-spin" });
              $$renderer4.push(`<!---->Preparing PDF…`);
            } else {
              $$renderer4.push("<!--[-1-->");
              Printer($$renderer4, { class: "size-4" });
              $$renderer4.push(`<!---->Print result sheet`);
            }
            $$renderer4.push(`<!--]-->`);
          },
          $$slots: { default: true }
        });
        $$renderer3.push(`<!----></div></div> `);
        if (template_error) {
          $$renderer3.push("<!--[0-->");
          $$renderer3.push(`<div class="rounded-xl border border-destructive/50 bg-destructive/10 p-6 text-destructive">${escape_html(template_error)}</div>`);
        } else if (previewStatus === "queued") {
          $$renderer3.push("<!--[1-->");
          $$renderer3.push(`<div class="mx-auto flex h-[700px] max-w-3xl flex-col items-center justify-center gap-3 rounded-sm border border-border bg-card text-muted-foreground">`);
          Rotate_cw($$renderer3, { class: "size-8 animate-spin text-primary" });
          $$renderer3.push(`<!----> <p class="text-sm">Generating the exact DOCX PDF preview…</p></div>`);
        } else if (previewStatus === "failed") {
          $$renderer3.push("<!--[2-->");
          $$renderer3.push(`<div class="mx-auto max-w-3xl rounded-xl border border-destructive/30 bg-destructive/10 p-6 text-sm text-destructive">PDF preview failed. Use the batch retry flow.</div>`);
        } else if (pdfSrc) {
          $$renderer3.push("<!--[3-->");
          $$renderer3.push(`<div class="sheet-print-container mx-auto overflow-hidden rounded-sm bg-white shadow-xs ring-1 ring-border svelte-w52pdv"${attr_style(`width: ${paperWidth}mm;`)}><iframe title="Result sheet PDF"${attr("src", pdfSrc)} class="h-[900px] w-full border-0"></iframe></div>`);
        } else if (sheetHtml) {
          $$renderer3.push("<!--[4-->");
          $$renderer3.push(`<div class="sheet-print-container mx-auto rounded-sm bg-white p-4 shadow-xs ring-1 ring-border svelte-w52pdv"${attr_style(`width: ${paperWidth}mm;`)}><div class="print-content" style="width: 100%;">${html(sheetHtml)}</div></div>`);
        } else {
          $$renderer3.push("<!--[-1-->");
          $$renderer3.push(`<p class="text-sm text-muted-foreground">No sheet rendered.</p>`);
        }
        $$renderer3.push(`<!--]--></div> `);
        MarkPrintedDialog($$renderer3, {
          open: showMarkDialog,
          documentIds: applicant.document_id ? [applicant.document_id] : [],
          count: 1,
          onclose: () => showMarkDialog = false,
          onmarked: () => router.reload()
        });
        $$renderer3.push(`<!---->`);
      }
    });
  });
}
export {
  ResultSheet as default
};
