import { h as head, e as escape_html, B as Button, d as derived } from "../app.js";
import { P as PageHeaderBar } from "./PageHeaderBar-DJhT5vOR.js";
import { B as Badge } from "./badge-D9Vgs7xh.js";
import { S as StaffLayout } from "./StaffLayout-CR-MZopa.js";
import { F as File_text } from "./file-text-PXEtmXm9.js";
import { B as Book_open } from "./book-open-B6eMguqY.js";
import { F as Flask_conical } from "./flask-conical-CPKNi8ld.js";
import "axios";
import "@inertiajs/core";
import "clsx";
import "laravel-precognition";
import "tailwind-merge";
import "tailwind-variants";
import "./XiamNpcDialog-DoqCSHj9.js";
/* empty css                                                        */
import "./circle-question-mark-DY-RU614.js";
import "./Icon-Dal7PqKK.js";
import "./x-CnxrMbMn.js";
import "./sparkles-DbQIaVG6.js";
import "./chevron-left-DCSOZwZl.js";
import "./chevron-right-BHu-PGOg.js";
import "./ToastManager-C7oO_4Jn.js";
import "./circle-x-BQrhluGu.js";
import "./triangle-alert-CUvuB-HL.js";
import "./info-C3MCzlQW.js";
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
import "./chevron-down-CkQQZIhu.js";
import "./printer-Bx0z1CRb.js";
import "./megaphone-C8QUuT5s.js";
import "./file-check-BSLEMs1I.js";
import "./shield-DTtuUihb.js";
import "./clipboard-list-YgthURdB.js";
import "./users-DWlguTa_.js";
import "./user-round-Kft0Cy_Y.js";
function ExamMaterials($$renderer, $$props) {
  $$renderer.component(($$renderer2) => {
    let { answer_sheet = null, booklets = [], omr_test = {} } = $$props;
    const breadcrumbs = [
      { label: "Setup", href: "/setup" },
      { label: "Exam materials" }
    ];
    const finalizedBooklets = derived(() => booklets.filter((booklet) => booklet.status === "finalized").length);
    const materialsContext = derived(() => {
      const hasSheet = Boolean(answer_sheet);
      const sheetName = answer_sheet?.display_name ?? null;
      const status = answer_sheet?.status_label ?? null;
      const testReady = Boolean(omr_test?.is_ready);
      const safetyKey = `${hasSheet ? "sheet" : "nosheet"}:${booklets.length}:${finalizedBooklets()}:${testReady ? "testok" : "testno"}:${omr_test?.status ?? "none"}`;
      return {
        entityKey: "exam-materials-hub",
        safetyKey,
        freshness: "current",
        hasAnswerSheet: hasSheet,
        answerSheetName: sheetName,
        answerSheetStatus: status,
        bookletsCount: booklets.length,
        finalizedBookletsCount: finalizedBooklets(),
        isOmrTestReady: testReady,
        omrTestStatus: omr_test?.status ?? "needs_setup"
      };
    });
    head("1difr74", $$renderer2, ($$renderer3) => {
      $$renderer3.title(($$renderer4) => {
        $$renderer4.push(`<title>Exam Materials · Setup</title>`);
      });
    });
    StaffLayout($$renderer2, {
      breadcrumbs,
      children: ($$renderer3) => {
        $$renderer3.push(`<div class="min-w-0 space-y-8 p-4 sm:p-6">`);
        PageHeaderBar($$renderer3, {
          title: "Exam Materials",
          description: "Manage optical answer sheets, question booklets, and answer-key configurations for examination operations.",
          tourKey: "exam-materials",
          context: materialsContext()
        });
        $$renderer3.push(`<!----> <div class="grid grid-cols-1 gap-6 lg:grid-cols-12"><section class="group relative flex flex-col justify-between rounded-2xl border border-border/80 bg-card p-6 sm:p-7 shadow-xs transition-all duration-200 hover:-translate-y-0.5 hover:border-emerald-500/30 dark:shadow-[inset_0_1px_0_0_rgba(255,255,255,0.06),0_8px_24px_-12px_rgba(0,0,0,0.5)] lg:col-span-6" aria-labelledby="answer-sheet-title" data-tour="exam-materials-templates"><div class="space-y-4"><div class="flex items-start gap-3.5"><div class="rounded-xl border border-emerald-500/20 bg-emerald-500/10 p-2.5 text-emerald-600 dark:text-emerald-400 shrink-0">`);
        File_text($$renderer3, { class: "size-5" });
        $$renderer3.push(`<!----></div> <div class="min-w-0 flex-1 space-y-1"><h2 id="answer-sheet-title" class="text-lg font-semibold tracking-tight text-foreground">Answer Sheet Studio</h2> <p class="text-sm text-muted-foreground leading-relaxed">Calibrate geometric bubble grids, timing fiducials, and sub-test aptitude sections for official scoring.</p></div></div> <div class="rounded-xl border border-border/60 bg-muted/20 p-4">`);
        if (answer_sheet) {
          $$renderer3.push("<!--[0-->");
          $$renderer3.push(`<div class="space-y-2"><div class="flex flex-wrap items-center justify-between gap-2"><h3 class="font-semibold text-sm text-foreground">${escape_html(answer_sheet.display_name)}</h3> `);
          Badge($$renderer3, {
            class: "border-emerald-500/30 bg-emerald-500/10 text-emerald-700 dark:text-emerald-400 font-medium text-xs",
            children: ($$renderer4) => {
              $$renderer4.push(`<!---->${escape_html(answer_sheet.status_label)}`);
            },
            $$slots: { default: true }
          });
          $$renderer3.push(`<!----></div> <p class="text-xs text-muted-foreground font-mono">${escape_html(answer_sheet.physical_status)}</p></div>`);
        } else {
          $$renderer3.push("<!--[-1-->");
          $$renderer3.push(`<div class="space-y-1"><h3 class="font-semibold text-sm text-foreground">No Answer Sheet active</h3> <p class="text-xs text-muted-foreground">Create and activate a master sheet before finalizing exam booklets.</p></div>`);
        }
        $$renderer3.push(`<!--]--></div></div> <div class="mt-6 pt-2">`);
        if (answer_sheet) {
          $$renderer3.push("<!--[0-->");
          Button($$renderer3, {
            href: answer_sheet.open_url,
            class: "min-h-11 w-full sm:w-auto",
            variant: "outline",
            children: ($$renderer4) => {
              $$renderer4.push(`<!---->Open Answer Sheet Studio`);
            },
            $$slots: { default: true }
          });
        } else {
          $$renderer3.push("<!--[-1-->");
          Button($$renderer3, {
            href: "/setup/exam-materials/answer-sheet",
            class: "min-h-11 w-full sm:w-auto",
            variant: "outline",
            children: ($$renderer4) => {
              $$renderer4.push(`<!---->Create Answer Sheet`);
            },
            $$slots: { default: true }
          });
        }
        $$renderer3.push(`<!--]--></div></section> <section class="group relative flex flex-col justify-between rounded-2xl border border-border/80 bg-card p-6 sm:p-7 shadow-xs transition-all duration-200 hover:-translate-y-0.5 hover:border-sky-500/30 dark:shadow-[inset_0_1px_0_0_rgba(255,255,255,0.06),0_8px_24px_-12px_rgba(0,0,0,0.5)] lg:col-span-6" aria-labelledby="booklets-title" data-tour="booklets-inventory"><div class="space-y-4"><div class="flex items-start gap-3.5"><div class="rounded-xl border border-sky-500/20 bg-sky-500/10 p-2.5 text-sky-600 dark:text-sky-400 shrink-0">`);
        Book_open($$renderer3, { class: "size-5" });
        $$renderer3.push(`<!----></div> <div class="min-w-0 flex-1 space-y-1"><div class="flex flex-wrap items-center gap-2"><h2 id="booklets-title" class="text-lg font-semibold tracking-tight text-foreground">Booklets</h2> `);
        Badge($$renderer3, {
          variant: "outline",
          class: "border-sky-500/30 bg-sky-500/10 text-sky-700 dark:text-sky-300 text-[11px] font-semibold",
          children: ($$renderer4) => {
            $$renderer4.push(`<!---->Default: Answer Keys Only`);
          },
          $$slots: { default: true }
        });
        $$renderer3.push(`<!----></div> <p class="text-sm text-muted-foreground leading-relaxed">Maintain official answer keys for externally printed test booklets (default), or author stored question items in the dedicated workspace.</p></div></div> <div class="rounded-xl border border-border/60 bg-muted/20 p-4 space-y-2.5"><div class="flex flex-wrap items-center justify-between gap-2"><div class="flex items-center gap-2">`);
        Badge($$renderer3, {
          variant: "secondary",
          children: ($$renderer4) => {
            $$renderer4.push(`<!---->${escape_html(booklets.length)} ${escape_html(booklets.length === 1 ? "Booklet" : "Booklets")}`);
          },
          $$slots: { default: true }
        });
        $$renderer3.push(`<!----> `);
        if (finalizedBooklets() > 0) {
          $$renderer3.push("<!--[0-->");
          Badge($$renderer3, {
            class: "border-emerald-500/30 bg-emerald-500/10 text-emerald-700 dark:text-emerald-400 font-medium text-xs",
            children: ($$renderer4) => {
              $$renderer4.push(`<!---->${escape_html(finalizedBooklets())} in production`);
            },
            $$slots: { default: true }
          });
        } else {
          $$renderer3.push("<!--[-1-->");
        }
        $$renderer3.push(`<!--]--></div> <span class="text-[11px] font-medium text-sky-700 dark:text-sky-300">External paper booklets</span></div> <p class="text-xs text-muted-foreground leading-relaxed">Questions remain printed externally on paper. Staff enter and map correct scoring keys (A–D) to evaluate scannable answer sheets.</p></div></div> <div class="mt-6 pt-2">`);
        Button($$renderer3, {
          href: "/setup/exam-materials/booklets",
          class: "min-h-11 w-full sm:w-auto",
          children: ($$renderer4) => {
            $$renderer4.push(`<!---->Open Booklets`);
          },
          $$slots: { default: true }
        });
        $$renderer3.push(`<!----></div></section> <section class="group relative flex flex-col justify-between rounded-2xl border border-border/80 bg-card p-6 sm:p-7 shadow-xs transition-all duration-200 hover:-translate-y-0.5 hover:border-violet-500/30 dark:shadow-[inset_0_1px_0_0_rgba(255,255,255,0.06),0_8px_24px_-12px_rgba(0,0,0,0.5)] lg:col-span-12" aria-labelledby="omr-test-title"><div class="flex flex-col gap-6 lg:flex-row lg:items-center lg:justify-between"><div class="flex items-start gap-3.5 min-w-0 max-w-2xl"><div class="rounded-xl border border-violet-500/20 bg-violet-500/10 p-2.5 text-violet-600 dark:text-violet-400 shrink-0">`);
        Flask_conical($$renderer3, { class: "size-5" });
        $$renderer3.push(`<!----></div> <div class="min-w-0 space-y-1"><div class="flex flex-wrap items-center gap-2"><h2 id="omr-test-title" class="text-lg font-semibold tracking-tight text-foreground">OMR Test Sandbox</h2> `);
        Badge($$renderer3, {
          variant: omr_test.is_ready ? "secondary" : "outline",
          class: omr_test.is_ready ? "border-emerald-500/30 bg-emerald-500/10 text-emerald-700 dark:text-emerald-400 font-semibold text-xs" : "border-amber-500/30 bg-amber-500/10 text-amber-700 dark:text-amber-300 font-semibold text-xs",
          children: ($$renderer4) => {
            $$renderer4.push(`<!---->${escape_html(omr_test.is_ready ? "Ready to Test" : "Setup Required")}`);
          },
          $$slots: { default: true }
        });
        $$renderer3.push(`<!----></div> <p class="text-sm text-muted-foreground leading-relaxed">Run real optical capture, fiducial alignment, and detection evidence projection safely in a read-only sandbox without recording live applicant scans or scores.</p> <p class="text-xs text-muted-foreground pt-1">`);
        if (omr_test.status === "needs_answer_sheet") {
          $$renderer3.push("<!--[0-->");
          $$renderer3.push(`Activate an Answer Sheet template to enable diagnostic testing.`);
        } else if (omr_test.status === "needs_booklet") {
          $$renderer3.push("<!--[1-->");
          $$renderer3.push(`Finalize a compatible Booklet to complete material readiness.`);
        } else {
          $$renderer3.push("<!--[-1-->");
          $$renderer3.push(`${escape_html(omr_test.finalized_compatible_booklet_count)} compatible finalized ${escape_html(omr_test.finalized_compatible_booklet_count === 1 ? "Booklet" : "Booklets")} available for test runs.`);
        }
        $$renderer3.push(`<!--]--></p></div></div> <div class="shrink-0 pt-2 lg:pt-0">`);
        Button($$renderer3, {
          href: omr_test.open_url ?? "/setup/exam-materials/omr-test",
          class: "min-h-11 w-full sm:w-auto",
          variant: "outline",
          children: ($$renderer4) => {
            $$renderer4.push(`<!---->Open OMR Test`);
          },
          $$slots: { default: true }
        });
        $$renderer3.push(`<!----></div></div></section></div></div>`);
      }
    });
  });
}
export {
  ExamMaterials as default
};
