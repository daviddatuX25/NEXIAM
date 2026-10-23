import { o as onDestroy, h as head, e as escape_html, B as Button, a as ensure_array_like, f as attr_style, b as attr } from "../app.js";
import { S as StaffLayout } from "./StaffLayout-CR-MZopa.js";
import { R as Rotate_cw, M as MarkPrintedDialog } from "./MarkPrintedDialog-BNiMAKov.js";
import "clsx";
import "./badge-D9Vgs7xh.js";
import { A as Arrow_left } from "./arrow-left-DbWnQR3t.js";
import { P as Printer } from "./printer-Bx0z1CRb.js";
import { C as Circle_alert } from "./circle-alert-jWXJ7oC0.js";
import { router } from "@inertiajs/core";
import { h as html } from "./html-BMEe54ZQ.js";
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
function ResultSheetBulk($$renderer, $$props) {
  $$renderer.component(($$renderer2) => {
    let {
      session = {},
      pdfPreviews = [],
      sheetsHtml = [],
      document_ids = [],
      paper_size = "a4",
      template_error = null
    } = $$props;
    let previews = pdfPreviews.map((p) => ({ ...p }));
    let showMarkDialog = false;
    const paperWidth = { a4: 210, legal: 216, letter: 216 }[paper_size] ?? 210;
    const breadcrumbs = [
      { label: "Release", href: "/release" },
      {
        label: `Session ${session.code ?? session.id}`,
        href: `/release/print/batches/${session.id}`
      },
      { label: "Print preview" }
    ];
    onDestroy(() => {
    });
    function handlePrint() {
      window.print();
      setTimeout(() => showMarkDialog = true, 500);
    }
    head("151jihj", $$renderer2, ($$renderer3) => {
      $$renderer3.title(($$renderer4) => {
        $$renderer4.push(`<title>Result sheet stack · Release</title>`);
      });
    });
    StaffLayout($$renderer2, {
      breadcrumbs,
      children: ($$renderer3) => {
        $$renderer3.push(`<div class="min-w-0 space-y-4 p-4 sm:p-6"><div class="no-print flex flex-wrap items-center justify-between gap-3 rounded-xl border border-border bg-card p-4 shadow-xs svelte-151jihj"><div><h2 class="text-sm font-semibold text-foreground">Result Sheet Stack Preview</h2> <p class="text-xs text-muted-foreground">${escape_html(previews.length || sheetsHtml.length)} sheet(s) · ${escape_html(paper_size.toUpperCase())} · exact PDF representation</p></div> <div class="flex items-center gap-2">`);
        Button($$renderer3, {
          variant: "outline",
          size: "sm",
          class: "min-h-10 gap-1.5",
          onclick: () => router.visit(`/release/print/batches/${session.id}`),
          children: ($$renderer4) => {
            Arrow_left($$renderer4, { class: "size-4" });
            $$renderer4.push(`<!---->Back to batch`);
          },
          $$slots: { default: true }
        });
        $$renderer3.push(`<!----> `);
        Button($$renderer3, {
          size: "sm",
          class: "min-h-10 gap-2 font-semibold",
          onclick: handlePrint,
          children: ($$renderer4) => {
            Printer($$renderer4, { class: "size-4" });
            $$renderer4.push(`<!---->Print all sheets`);
          },
          $$slots: { default: true }
        });
        $$renderer3.push(`<!----></div></div> `);
        if (template_error) {
          $$renderer3.push("<!--[0-->");
          $$renderer3.push(`<div class="rounded-xl border border-destructive/50 bg-destructive/10 p-6 text-destructive">${escape_html(template_error)}</div>`);
        } else if (previews.length > 0) {
          $$renderer3.push("<!--[1-->");
          $$renderer3.push(`<div class="space-y-8"><!--[-->`);
          const each_array = ensure_array_like(previews);
          for (let i = 0, $$length = each_array.length; i < $$length; i++) {
            let preview = each_array[i];
            $$renderer3.push(`<div class="sheet-page mx-auto overflow-hidden rounded-sm bg-white shadow-xs ring-1 ring-border svelte-151jihj"${attr_style(`width: ${paperWidth}mm;`)}>`);
            if (preview.status === "ready" && preview.src) {
              $$renderer3.push("<!--[0-->");
              $$renderer3.push(`<iframe${attr("title", `Result sheet ${i + 1}`)}${attr("src", preview.src)} class="h-[900px] w-full border-0 svelte-151jihj"></iframe>`);
            } else if (preview.status === "queued") {
              $$renderer3.push("<!--[1-->");
              $$renderer3.push(`<div class="flex h-[500px] flex-col items-center justify-center gap-3 text-center text-muted-foreground">`);
              Rotate_cw($$renderer3, { class: "size-7 animate-spin text-primary" });
              $$renderer3.push(`<!----> <p class="text-xs">Rendering ${escape_html(preview.name ?? "result sheet")}…</p></div>`);
            } else {
              $$renderer3.push("<!--[-1-->");
              $$renderer3.push(`<div class="flex h-[500px] flex-col items-center justify-center gap-2 text-center text-destructive">`);
              Circle_alert($$renderer3, { class: "size-7" });
              $$renderer3.push(`<!----> <p class="text-xs">Preview failed: ${escape_html(preview.error ?? "unknown error")}</p></div>`);
            }
            $$renderer3.push(`<!--]--></div>`);
          }
          $$renderer3.push(`<!--]--></div>`);
        } else if (sheetsHtml.length > 0) {
          $$renderer3.push("<!--[2-->");
          $$renderer3.push(`<div class="space-y-8"><!--[-->`);
          const each_array_1 = ensure_array_like(sheetsHtml);
          for (let i = 0, $$length = each_array_1.length; i < $$length; i++) {
            let html$1 = each_array_1[i];
            $$renderer3.push(`<div class="sheet-page mx-auto rounded-sm bg-white p-4 shadow-xs ring-1 ring-border svelte-151jihj"${attr_style(`width: ${paperWidth}mm;`)}>${html(html$1)}</div>`);
          }
          $$renderer3.push(`<!--]--></div>`);
        } else {
          $$renderer3.push("<!--[-1-->");
          $$renderer3.push(`<p class="text-sm text-muted-foreground">No sheets selected.</p>`);
        }
        $$renderer3.push(`<!--]--></div> `);
        MarkPrintedDialog($$renderer3, {
          open: showMarkDialog,
          documentIds: document_ids,
          count: document_ids.length || previews.length || sheetsHtml.length,
          onmarked: () => router.reload()
        });
        $$renderer3.push(`<!---->`);
      }
    });
  });
}
export {
  ResultSheetBulk as default
};
